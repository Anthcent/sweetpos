<?php
require_once __DIR__ . '/pdf_documents.php';

function reportPdfCell($value, array $column): string {
    if ($value === null || $value === '') return '—';
    return match ($column['format'] ?? 'text') {
        'money' => pdfMoney((float)$value),
        'integer' => number_format((int)$value, 0, ',', '.'),
        'quantity' => pdfQty((float)$value),
        'days' => pdfQty((float)$value) . ' d',
        'percent' => number_format((float)$value, 2, ',', '.') . '%',
        default => (string)$value,
    };
}

function reportBuildPdfDocument(array $data): array {
    $pdfTitle = [
        'cierre_diario' => 'CIERRE DIARIO', 'cierre_mensual' => 'CIERRE MENSUAL',
        'mas_vendidos' => 'MAS VENDIDOS', 'reponer' => 'REPOSICION',
    ][$data['type']] ?? 'REPORTE';
    $pdf = new SweetPdf($pdfTitle, $data['period_label'], 'L');
    $pdf->AddPage();
    $pdf->SetFont('Helvetica', '', 8.5);
    $pdf->umulti(0, 4.5, 'Generado: ' . $data['generated_at']);

    $pdf->sectionTitle('Resumen');
    foreach ($data['metrics'] as $metric) {
        $pdf->field($metric['label'], reportPdfCell($metric['value'], ['format' => $metric['format'] ?? 'text']), 55);
    }

    foreach ($data['notes'] as $note) {
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(...SweetPdf::MUTED);
        $pdf->umulti(0, 4.2, '• ' . $note);
        $pdf->SetTextColor(15, 23, 42);
    }

    // SweetPdf uses US Letter in landscape with 15 mm side margins.
    $available = 249.4;
    foreach ($data['tables'] as $table) {
        $pdf->sectionTitle($table['title']);
        $weightTotal = array_sum(array_map(fn($column) => max(1, (int)$column['weight']), $table['columns']));
        $columns = [];
        foreach ($table['columns'] as $index => $column) {
            $isLast = $index === count($table['columns']) - 1;
            $width = $isLast ? 0 : $available * max(1, (int)$column['weight']) / $weightTotal;
            $columns[] = [$column['label'], $width, $column['align'] ?? 'L'];
        }
        $pdf->beginTable($columns);
        foreach ($table['rows'] as $row) {
            $values = [];
            foreach ($table['columns'] as $index => $column) {
                $values[] = reportPdfCell($row[$index] ?? null, $column);
            }
            $pdf->tableRow($values);
        }
        if (!$table['rows']) $pdf->emptyRow('Sin registros para el período seleccionado.');
        $pdf->endTable();
    }

    $slug = [
        'cierre_diario' => 'cierre-diario', 'cierre_mensual' => 'cierre-mensual',
        'mas_vendidos' => 'mas-vendidos', 'reponer' => 'reponer',
    ][$data['type']] ?? 'reporte';
    $period = preg_replace('/[^0-9A-Za-z-]+/', '_', (string)$data['period']);
    return ['filename' => "sweetpos-{$slug}-{$period}.pdf", 'content' => $pdf->Output('S')];
}

function reportBuildWorkbookSheets(array $data): array {
    $summary = [['Indicador', 'Valor'], ['Negocio', $data['business_name'] ?? 'Sweet POS'], ['Reporte', $data['title']], ['Período', $data['period_label']], ['Generado', $data['generated_at']]];
    foreach ($data['metrics'] as $metric) $summary[] = [$metric['label'], $metric['value']];
    $sheets = ['Resumen' => $summary];
    foreach ($data['tables'] as $index => $table) {
        $rows = [array_map(fn($column) => $column['label'], $table['columns'])];
        foreach ($table['rows'] as $row) $rows[] = array_values($row);
        $name = sweetposXlsxLimitUtf8((string)$table['title'], 31);
        $sheets[$name !== '' ? $name : 'Detalle ' . ($index + 1)] = $rows;
    }
    if ($data['notes']) {
        $rows = [['Notas'], ...array_map(fn($note) => [$note], $data['notes'])];
        $sheets['Notas'] = $rows;
    }
    return $sheets;
}
