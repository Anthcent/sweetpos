<?php
// Minimal, dependency-free XLSX writer for report exports.
// XLSX is an OOXML package; ZIP entries are stored uncompressed so this works
// without the optional PHP zip extension in the runtime image.

function sweetposXlsxEscape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function sweetposXlsxColumnName(int $number): string {
    $name = '';
    while ($number > 0) {
        $number--;
        $name = chr(65 + ($number % 26)) . $name;
        $number = intdiv($number, 26);
    }
    return $name;
}

function sweetposXlsxLimitUtf8(string $value, int $characters): string {
    if (!preg_match_all('/./us', $value, $matches)) return substr($value, 0, $characters);
    return implode('', array_slice($matches[0], 0, $characters));
}

function sweetposXlsxCellXml($value, string $reference, int $style = 0): string {
    $styleAttribute = $style > 0 ? ' s="' . $style . '"' : '';
    if ($value === null || $value === '') return '<c r="' . $reference . '"' . $styleAttribute . '/>';
    if (is_bool($value)) return '<c r="' . $reference . '"' . $styleAttribute . ' t="b"><v>' . ($value ? '1' : '0') . '</v></c>';
    if (is_int($value) || (is_float($value) && is_finite($value))) {
        return '<c r="' . $reference . '"' . $styleAttribute . '><v>' . (string)$value . '</v></c>';
    }
    return '<c r="' . $reference . '"' . $styleAttribute . ' t="inlineStr"><is><t xml:space="preserve">'
        . sweetposXlsxEscape((string)$value) . '</t></is></c>';
}

function sweetposXlsxWorksheetXml(array $rows): string {
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/><sheetData>';
    $lastColumn = 1;
    foreach ($rows as $rowIndex => $row) {
        $rowNumber = $rowIndex + 1;
        $row = is_array($row) ? array_values($row) : [$row];
        $lastColumn = max($lastColumn, count($row));
        $xml .= '<row r="' . $rowNumber . '">';
        foreach ($row as $columnIndex => $value) {
            $reference = sweetposXlsxColumnName($columnIndex + 1) . $rowNumber;
            $xml .= sweetposXlsxCellXml($value, $reference, $rowNumber === 1 ? 1 : 0);
        }
        $xml .= '</row>';
    }
    $xml .= '</sheetData>';
    if ($rows) {
        $xml .= '<autoFilter ref="A1:' . sweetposXlsxColumnName($lastColumn) . count($rows) . '"/>';
    }
    return $xml . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/></worksheet>';
}

function sweetposXlsxZip(array $files): string {
    $localEntries = '';
    $centralEntries = '';
    $entryCount = 0;
    $dosDate = 33; // 1980-01-01, the minimum ZIP timestamp.
    foreach ($files as $path => $contents) {
        $path = str_replace('\\', '/', (string)$path);
        $contents = (string)$contents;
        $nameLength = strlen($path);
        $size = strlen($contents);
        $crc = crc32($contents);
        $offset = strlen($localEntries);
        $localEntries .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, $dosDate, $crc, $size, $size, $nameLength, 0)
            . $path . $contents;
        $centralEntries .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, $dosDate, $crc,
            $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $path;
        $entryCount++;
    }
    $centralOffset = strlen($localEntries);
    $centralSize = strlen($centralEntries);
    $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, $centralSize, $centralOffset, 0);
    return $localEntries . $centralEntries . $end;
}

/** Build a basic but standards-compliant Excel workbook from named sheets and row arrays. */
function sweetposBuildXlsx(array $sheets): string {
    if (!$sheets) $sheets = ['Reporte' => [['Sin datos']]];
    $sheetNames = [];
    $sheetFiles = [];
    $sheetRels = [];
    $contentOverrides = '';
    $workbookSheets = '';
    $relationshipId = 1;
    foreach ($sheets as $name => $rows) {
        $safeName = strtr((string)$name, ['[' => '_', ']' => '_', ':' => '_', '*' => '_', '?' => '_', '/' => '_', '\\' => '_']);
        $safeName = trim(sweetposXlsxLimitUtf8($safeName !== '' ? $safeName : 'Reporte', 31));
        if ($safeName === '') $safeName = 'Reporte';
        $baseName = $safeName;
        $suffix = 2;
        while (in_array($safeName, $sheetNames, true)) {
            $tail = ' ' . $suffix++;
            $safeName = sweetposXlsxLimitUtf8($baseName, 31 - strlen($tail)) . $tail;
        }
        $sheetNames[] = $safeName;
        $sheetId = count($sheetNames);
        $sheetPath = 'xl/worksheets/sheet' . $sheetId . '.xml';
        $sheetFiles[$sheetPath] = sweetposXlsxWorksheetXml(is_array($rows) ? $rows : []);
        $workbookSheets .= '<sheet name="' . sweetposXlsxEscape($safeName) . '" sheetId="' . $sheetId . '" r:id="rId' . $relationshipId . '"/>';
        $sheetRels[] = '<Relationship Id="rId' . $relationshipId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetId . '.xml"/>';
        $contentOverrides .= '<Override PartName="/' . $sheetPath . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $relationshipId++;
    }
    $styleRelationship = '<Relationship Id="rId' . $relationshipId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $contentOverrides
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView/></bookViews><sheets>' . $workbookSheets . '</sheets><calcPr calcId="191029"/></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . implode('', $sheetRels) . $styleRelationship . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDB2777"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>',
    ];
    foreach ($sheetFiles as $path => $contents) $files[$path] = $contents;
    return sweetposXlsxZip($files);
}
