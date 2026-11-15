<?php
// lib/validation.php — Server-side field rules. Mirrors assets/validation.js (FieldRules)
// so the browser and the API accept exactly the same values.
//
// Cedula storage convention (kept for backward compatibility with existing rows
// and with every place that prints "V-" + cedula):
//   - Venezuelan nationals (prefix V or no prefix) are stored as digits only: "12345678".
//   - Other prefixes keep their letter: "E12345678", "J12345678", "G...", "P...".

const SWEETPOS_CEDULA_PREFIXES = 'VEJGP';

// Uppercases and strips dots, spaces and dashes: "v-12.345.678" -> "V12345678".
function sweetposCompactCedula(string $value): string {
    return strtoupper(preg_replace('/[\s.\-]+/u', '', trim($value)));
}

// Canonical stored form, or null when the value is not a valid cedula/RIF.
function sweetposNormalizeCedula(string $value): ?string {
    $compact = sweetposCompactCedula($value);
    if (!preg_match('/\A([VEJGP]?)(\d{6,9})\z/', $compact, $m)) {
        return null;
    }
    return ($m[1] === '' || $m[1] === 'V') ? $m[2] : $m[1] . $m[2];
}

// Display form used on receipts and PDFs: "V-12345678", "E-12345678".
function sweetposFormatCedula(?string $stored): string {
    $compact = sweetposCompactCedula((string)$stored);
    if ($compact === '') return '';
    if (preg_match('/\A([VEJGP])(\d+)\z/', $compact, $m)) {
        return $m[1] . '-' . $m[2];
    }
    return 'V-' . $compact;
}

// True when a free-text search term should be treated as a cedula.
function sweetposLooksLikeCedula(string $term): bool {
    return (bool)preg_match('/\A[VEJGP]?\d+\z/', sweetposCompactCedula($term));
}

// Lowercase + accent folding, for accent-insensitive name matching.
function sweetposFoldText(string $value): string {
    $value = mb_strtolower(trim($value), 'UTF-8');
    return strtr($value, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c',
    ]);
}

// Person names: letters (any script, accents, ñ), spaces, apostrophe and hyphen.
function sweetposIsValidPersonName(string $value): bool {
    $value = trim($value);
    if (mb_strlen($value, 'UTF-8') < 2 || mb_strlen($value, 'UTF-8') > 100) return false;
    return (bool)preg_match("/\A\p{L}[\p{L}\p{M}' \-]*\z/u", $value);
}

// Collapses repeated inner spaces: "  Ana   María " -> "Ana María".
function sweetposCleanPersonName(string $value): string {
    return preg_replace('/\s+/u', ' ', trim($value));
}

// Venezuelan phone: 11 digits, mobile 04xx operators or landline 02xx.
function sweetposNormalizePhone(string $value): ?string {
    $digits = preg_replace('/\D+/', '', $value);
    return preg_match('/\A0(?:412|414|416|422|424|426|2\d{2})\d{7}\z/', $digits) ? $digits : null;
}

function sweetposIsValidEmail(string $value): bool {
    return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
}

// Non-negative number check for prices, stock and quantities.
// $integer = true rejects decimals (unit counts).
function sweetposIsNonNegativeNumber($value, bool $integer = false): bool {
    if (is_bool($value) || !is_numeric($value)) return false;
    $number = (float)$value;
    if (!is_finite($number) || $number < 0) return false;
    return !$integer || floor($number) == $number;
}

// Validates a client payload. Returns [cleanData, null] or [null, errorMessage].
function sweetposValidateClient(array $data): array {
    $cedula = sweetposNormalizeCedula((string)($data['cedula'] ?? ''));
    if ($cedula === null) {
        return [null, 'Cédula inválida: usa 6 a 9 dígitos, con prefijo opcional V, E, J, G o P.'];
    }

    $name = sweetposCleanPersonName((string)($data['name'] ?? ''));
    if (!sweetposIsValidPersonName($name)) {
        return [null, 'Nombre inválido: usa solo letras, espacios, apóstrofo o guion (mínimo 2 caracteres).'];
    }

    $phone = null;
    $rawPhone = trim((string)($data['phone'] ?? ''));
    if ($rawPhone !== '') {
        $phone = sweetposNormalizePhone($rawPhone);
        if ($phone === null) {
            return [null, 'Teléfono inválido: usa 11 dígitos, por ejemplo 04121234567 o 02121234567.'];
        }
    }

    $email = null;
    $rawEmail = trim((string)($data['email'] ?? ''));
    if ($rawEmail !== '') {
        if (!sweetposIsValidEmail($rawEmail)) {
            return [null, 'Correo electrónico inválido.'];
        }
        $email = $rawEmail;
    }

    $address = trim((string)($data['address'] ?? ''));

    return [[
        'cedula'  => $cedula,
        'name'    => $name,
        'phone'   => $phone,
        'email'   => $email,
        'address' => $address !== '' ? mb_substr($address, 0, 200, 'UTF-8') : null,
    ], null];
}
