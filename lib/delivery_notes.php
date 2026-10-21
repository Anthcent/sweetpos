<?php
// lib/delivery_notes.php — Delivery notes ("nota de entrega") shared by the PDF
// (api/pdf.php?type=nota) and the on-screen receipt (api/receipt.php).
// Pure functions over PDO: no session and no output. Both documents load the sale
// through deliveryNoteLoadSale() and number it through deliveryNoteAssign(), so a
// receipt and its PDF always carry the same NE-xxxxxx number.

require_once __DIR__ . '/pdf_base.php';

const PDF_PAYMENT_LABELS = [
    'cash'      => 'Efectivo',
    'card'      => 'Tarjeta',
    'transfer'  => 'Transferencia',
    'pagomovil' => 'Pago Móvil',
];

// Carries the HTTP status for api/pdf.php and api/receipt.php.
class PdfRequestException extends RuntimeException {
    public $status;
    public function __construct(string $message, int $status = 400) {
        parent::__construct($message);
        $this->status = $status;
    }
}

// ---------------------------------------------------------------------------
// Numbering
// ---------------------------------------------------------------------------

// The table is created by migration 14 (api/migrate.php). This fallback only
// covers a PDO that did not go through api/db.php; it runs once per connection.
function deliveryNotesEnsureSchema(PDO $pdo): void {
    static $ready = [];
    $key = spl_object_id($pdo);
    if (isset($ready[$key])) return;
    migrationExec($pdo, "CREATE TABLE IF NOT EXISTS delivery_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sale_id INTEGER NOT NULL UNIQUE,
        seq INTEGER NOT NULL UNIQUE,
        number TEXT NOT NULL UNIQUE,
        username TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sale_id) REFERENCES sales(id)
    )");
    $ready[$key] = true;
}

function deliveryNoteFormat(int $seq): string {
    return 'NE-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
}

// Returns the note of a sale, creating it the first time. BEGIN IMMEDIATE takes
// the write lock before reading MAX(seq), so two concurrent requests can neither
// reuse a number nor create two notes for the same sale.
function deliveryNoteAssign(PDO $pdo, int $saleId, ?string $username): array {
    if (!function_exists('migrationExec')) require_once __DIR__ . '/../api/migrate.php';
    deliveryNotesEnsureSchema($pdo);
    $select = $pdo->prepare("SELECT sale_id, seq, number, username, created_at FROM delivery_notes WHERE sale_id = ?");
    $select->execute([$saleId]);
    $note = $select->fetch(PDO::FETCH_ASSOC);
    if ($note) return $note;

    $isPostgres = sweetposDatabaseDriver($pdo) === 'pgsql';
    if ($isPostgres) $pdo->query('SELECT pg_advisory_lock(728374626)');
    try {
        sweetposBeginWrite($pdo);
        $select->execute([$saleId]);
        $note = $select->fetch(PDO::FETCH_ASSOC);
        if (!$note) {
            $seq = (int)$pdo->query("SELECT COALESCE(MAX(seq), 0) + 1 FROM delivery_notes")->fetchColumn();
            $pdo->prepare("INSERT INTO delivery_notes (sale_id, seq, number, username) VALUES (?, ?, ?, ?)")
                ->execute([$saleId, $seq, deliveryNoteFormat($seq), $username]);
            $select->execute([$saleId]);
            $note = $select->fetch(PDO::FETCH_ASSOC);
        }
        sweetposCommitWrite($pdo);
    } catch (Throwable $e) {
        sweetposRollbackWrite($pdo);
        throw $e;
    } finally {
        if ($isPostgres) $pdo->query('SELECT pg_advisory_unlock(728374626)');
    }
    return $note;
}

// ---------------------------------------------------------------------------
// Loading and access
// ---------------------------------------------------------------------------

function pdfTableLabel($tableNumber): string {
    if ($tableNumber === null || $tableNumber === '') return '';
    return (string)$tableNumber === '999' ? 'Para llevar' : 'Mesa ' . $tableNumber;
}

function deliveryNotePaymentLabel(?string $method): string {
    return PDF_PAYMENT_LABELS[$method] ?? ($method ?: 'Sin registrar');
}

// Sale row (with client columns) and its items. 404 when the sale does not exist;
// 403 when $viewer is a vendedor who neither created nor collected it (checked
// before the status so a vendedor learns nothing about other people's orders);
// 409 while it is still pending. $viewer = ['role' => ..., 'username' => ...],
// or null for trusted callers (CLI tests).
function deliveryNoteLoadSale(PDO $pdo, int $saleId, ?array $viewer = null): array {
    $stmt = $pdo->prepare(
        "SELECT s.*, c.name AS client_name, c.cedula AS client_cedula, c.phone AS client_phone, c.address AS client_address
         FROM sales s LEFT JOIN clients c ON c.id = s.client_id
         WHERE s.id = ?"
    );
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sale) throw new PdfRequestException("La venta #$saleId no existe.", 404);

    if ($viewer !== null && !deliveryNoteViewerCanAccess($sale, $viewer)) {
        throw new PdfRequestException('Solo puedes ver los comprobantes de las ventas que registraste o cobraste.', 403);
    }
    if ($sale['status'] === 'pending') {
        throw new PdfRequestException("La orden #$saleId aún no está cobrada; la nota de entrega se emite al registrar el pago.", 409);
    }

    $items = $pdo->prepare(
        "SELECT si.product_id, si.quantity, si.subtotal, COALESCE(p.name, 'Producto #' || si.product_id) AS name
         FROM sale_items si LEFT JOIN products p ON p.id = si.product_id
         WHERE si.sale_id = ? ORDER BY si.id"
    );
    $items->execute([$saleId]);
    return [$sale, $items->fetchAll(PDO::FETCH_ASSOC)];
}

// admin/gerente see every sale; a vendedor only the ones they created or collected.
function deliveryNoteViewerCanAccess(array $sale, array $viewer): bool {
    $role = (string)($viewer['role'] ?? '');
    if (in_array($role, ['admin', 'gerente'], true)) return true;
    if ($role !== 'vendedor') return false;
    $username = (string)($viewer['username'] ?? '');
    if ($username === '') return false;
    return hash_equals((string)($sale['username'] ?? ''), $username)
        || hash_equals((string)($sale['paid_by'] ?? ''), $username);
}

// ---------------------------------------------------------------------------
// Receipt data (JSON for the on-screen card)
// ---------------------------------------------------------------------------

function deliveryNoteReceipt(PDO $pdo, int $saleId, ?string $username, ?array $viewer = null): array {
    [$sale, $items] = deliveryNoteLoadSale($pdo, $saleId, $viewer);
    $note = deliveryNoteAssign($pdo, $saleId, $username);
    $business = pdfBusinessConfig();
    $voided = $sale['status'] === 'voided';
    $hasClient = !empty($sale['client_id']) && $sale['client_name'] !== null;

    $lines = [];
    foreach ($items as $item) {
        $qty = (int)$item['quantity'];
        $subtotal = round((float)$item['subtotal'], 2);
        $lines[] = [
            'name'       => (string)$item['name'],
            'quantity'   => $qty,
            'unit_price' => $qty > 0 ? round($subtotal / $qty, 2) : 0.0,
            'subtotal'   => $subtotal,
        ];
    }

    return [
        'business' => [
            'name'     => (string)$business['name'],
            'rif'      => (string)$business['rif'],
            'address'  => (string)$business['address'],
            'phone'    => (string)$business['phone'],
            'currency' => (string)$business['currency'],
        ],
        'note' => [
            'number'    => $note['number'],
            'issued_at' => pdfLocalTime($note['created_at']),
        ],
        'sale' => [
            'id'          => (int)$sale['id'],
            'status'      => $sale['status'],
            'created_at'  => pdfLocalTime($sale['created_at']),
            'paid_at'     => pdfLocalTime($sale['paid_at'] ?? null),
            'seller'      => $sale['username'] ?? null,
            'table_label' => pdfTableLabel($sale['table_number']),
        ],
        'client' => $hasClient ? [
            'name'    => (string)$sale['client_name'],
            'cedula'  => (string)($sale['client_cedula'] ?? '') !== '' ? 'V-' . $sale['client_cedula'] : null,
            'phone'   => (string)($sale['client_phone'] ?? ''),
            'address' => (string)($sale['client_address'] ?? ''),
        ] : null,
        'items' => $lines,
        'total' => round((float)$sale['total_amount'], 2),
        'payment' => [
            'method'    => $sale['payment_method'],
            'label'     => deliveryNotePaymentLabel($sale['payment_method']),
            'reference' => $sale['reference_number'] ?: null,
        ],
        'voided' => $voided ? [
            'at'     => pdfLocalTime($sale['voided_at'] ?? null),
            'by'     => $sale['voided_by'] ?? null,
            'reason' => $sale['void_reason'] ?: null,
        ] : null,
        'pdf_url' => 'api/pdf.php?type=nota&sale_id=' . (int)$sale['id'] . '&inline=1',
    ];
}
