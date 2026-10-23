<?php

declare(strict_types=1);

function history_excel_xml_escape(string $value): string
{
    $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $escaped) ?? '';
}

function history_excel_column_name(int $column): string
{
    $name = '';
    while ($column > 0) {
        $column--;
        $name = chr(65 + ($column % 26)) . $name;
        $column = intdiv($column, 26);
    }

    return $name;
}

function history_excel_cell_xml(string $reference, mixed $value, int $style, bool $numeric = false): string
{
    $styleAttribute = ' s="' . $style . '"';
    if ($value === null || $value === '') {
        return '<c r="' . $reference . '"' . $styleAttribute . '/>';
    }

    if ($numeric && (is_int($value) || (is_float($value) && is_finite($value)))) {
        $serializedValue = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        return '<c r="' . $reference . '"' . $styleAttribute . ' t="n"><v>' . $serializedValue . '</v></c>';
    }

    return '<c r="' . $reference . '"' . $styleAttribute . ' t="inlineStr"><is><t xml:space="preserve">'
        . history_excel_xml_escape((string) $value)
        . '</t></is></c>';
}

function history_excel_create_sheet_xml(array $records): string
{
    $headers = [
        'ID Pemindaian',
        'Waktu Pemindaian',
        'ID Pengguna',
        'Nama Pengguna',
        'Email Pengguna',
        'Nama Penyakit',
        'Skor Kepercayaan (0-1)',
        'Tingkat Kepercayaan (%)',
        'Keparahan',
        'Area Terinfeksi (%)',
        'Deskripsi Keparahan',
        'Path Citra',
    ];
    $lastRow = count($records) + 6;
    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="A1:L' . $lastRow . '"/>'
        . '<sheetViews><sheetView showGridLines="0" tabSelected="1" workbookViewId="0">'
        . '<pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A7" sqref="A7"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="22"/>'
        . '<cols>'
        . '<col min="1" max="1" width="15" customWidth="1"/>'
        . '<col min="2" max="2" width="22" customWidth="1"/>'
        . '<col min="3" max="3" width="14" customWidth="1"/>'
        . '<col min="4" max="4" width="24" customWidth="1"/>'
        . '<col min="5" max="5" width="28" customWidth="1"/>'
        . '<col min="6" max="6" width="24" customWidth="1"/>'
        . '<col min="7" max="8" width="22" customWidth="1"/>'
        . '<col min="9" max="9" width="17" customWidth="1"/>'
        . '<col min="10" max="10" width="19" customWidth="1"/>'
        . '<col min="11" max="11" width="34" customWidth="1"/>'
        . '<col min="12" max="12" width="38" customWidth="1"/>'
        . '</cols><sheetData>';

    $xml .= '<row r="1" ht="42" customHeight="1">'
        . history_excel_cell_xml('A1', '', 13)
        . history_excel_cell_xml('B1', 'TILIK PADI', 13)
        . '</row><row r="2" ht="24" customHeight="1">'
        . history_excel_cell_xml('A2', '', 14)
        . history_excel_cell_xml('B2', 'Laporan riwayat pemantauan tanaman padi', 14)
        . '</row><row r="3" ht="10" customHeight="1"/>'
        . '<row r="4" ht="30" customHeight="1">'
        . history_excel_cell_xml('A4', 'TOTAL PEMINDAIAN   |   ' . count($records) . ' data terbaru', 15)
        . history_excel_cell_xml('G4', 'DIEKSPOR   |   ' . date('d/m/Y H:i') . ' WIB', 15)
        . '</row><row r="5" ht="10" customHeight="1"/>'
        . '<row r="6" ht="36" customHeight="1">';

    foreach ($headers as $index => $header) {
        $xml .= history_excel_cell_xml(history_excel_column_name($index + 1) . '6', $header, 4);
    }
    $xml .= '</row>';

    foreach ($records as $recordIndex => $record) {
        $rowNumber = $recordIndex + 7;
        $alternate = ($recordIndex % 2) === 1;
        $textStyle = $alternate ? 6 : 5;
        $numberStyle = $alternate ? 8 : 7;
        $percentStyle = $alternate ? 10 : 9;
        $dateStyle = $alternate ? 12 : 11;
        $detectedAt = null;
        if (is_string($record['tanggal_deteksi'] ?? null)) {
            $dateTime = new DateTimeImmutable((string) $record['tanggal_deteksi'], new DateTimeZone('Asia/Jakarta'));
            $detectedAt = ($dateTime->getTimestamp() + $dateTime->getOffset()) / 86400 + 25569;
        }

        $confidence = $record['confidence'] === null ? null : (float) $record['confidence'];
        $infectedRatio = $record['infected_ratio_percent'] === null
            ? null
            : (float) $record['infected_ratio_percent'];
        $values = [
            [$record['id_detection'] === null ? null : (int) $record['id_detection'], $numberStyle, true],
            [$detectedAt, $dateStyle, true],
            [$record['id_user'] === null ? null : (int) $record['id_user'], $numberStyle, true],
            [is_string($record['user_name'] ?? null) ? $record['user_name'] : '', $textStyle, false],
            [is_string($record['user_email'] ?? null) ? $record['user_email'] : '', $textStyle, false],
            [is_string($record['nama_penyakit'] ?? null) ? $record['nama_penyakit'] : '', $textStyle, false],
            [$confidence, $numberStyle, true],
            [$confidence === null ? null : $confidence * 100, $percentStyle, true],
            [is_string($record['label_keparahan'] ?? null) ? $record['label_keparahan'] : '', $textStyle, false],
            [$infectedRatio, $percentStyle, true],
            [is_string($record['severity_description'] ?? null) ? $record['severity_description'] : '', $textStyle, false],
            [is_string($record['image_path'] ?? null) ? $record['image_path'] : '', $textStyle, false],
        ];

        $xml .= '<row r="' . $rowNumber . '" ht="32" customHeight="1">';
        foreach ($values as $index => [$value, $style, $numeric]) {
            $xml .= history_excel_cell_xml(
                history_excel_column_name($index + 1) . $rowNumber,
                $value,
                $style,
                $numeric
            );
        }
        $xml .= '</row>';
    }

    return $xml . '</sheetData>'
        . '<autoFilter ref="A6:L' . $lastRow . '"/>'
        . '<mergeCells count="4"><mergeCell ref="B1:L1"/><mergeCell ref="B2:L2"/>'
        . '<mergeCell ref="A4:F4"/><mergeCell ref="G4:L4"/></mergeCells>'
        . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
        . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0" paperSize="9"/>'
        . '<drawing r:id="rId1"/></worksheet>';
}

function history_excel_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="3">'
        . '<numFmt numFmtId="164" formatCode="0.0000"/>'
        . '<numFmt numFmtId="165" formatCode=\'0.00&quot;%&quot;\'/>'
        . '<numFmt numFmtId="166" formatCode="dd/mm/yyyy hh:mm:ss"/>'
        . '</numFmts>'
        .         '<fonts count="8">'
        . '<font><sz val="11"/><color rgb="FF202820"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="20"/><color rgb="FF245A35"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><sz val="11"/><color rgb="FF56635A"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FF245A35"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="20"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><sz val="10"/><color rgb="FFDCEEE0"/><name val="Calibri"/><family val="2"/></font>'
        . '<font><b/><sz val="10"/><color rgb="FF285B3B"/><name val="Calibri"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="6"><fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF2F6B45"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F7F2"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF20583A"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFEAF4EC"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left/><right/><top/><bottom style="thin"><color rgb="FFDCE5DE"/></bottom><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="16">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="4" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="164" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="165" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="166" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="166" fontId="0" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFill="1" applyBorder="1"><alignment vertical="center"/></xf>'
        . '<xf numFmtId="0" fontId="5" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" indent="1"/></xf>'
        . '<xf numFmtId="0" fontId="6" fillId="4" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" indent="1"/></xf>'
        . '<xf numFmtId="0" fontId="7" fillId="5" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment vertical="center" indent="1"/></xf>'
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

/**
 * @param resource $stream
 */
function history_excel_write_all($stream, string $contents): void
{
    $length = strlen($contents);
    $offset = 0;
    while ($offset < $length) {
        $written = fwrite($stream, substr($contents, $offset));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Gagal menulis berkas ekspor Excel.');
        }
        $offset += $written;
    }
}

function history_excel_create_zip(array $files): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'tilik-history-');
    if ($temporaryPath === false) {
        throw new RuntimeException('Tidak dapat menyiapkan berkas sementara untuk ekspor.');
    }

    $stream = fopen($temporaryPath, 'wb');
    if ($stream === false) {
        unlink($temporaryPath);
        throw new RuntimeException('Tidak dapat membuat berkas sementara untuk ekspor.');
    }

    try {
        $centralDirectory = '';
        foreach ($files as $name => $contents) {
            $nameLength = strlen($name);
            $contentLength = strlen($contents);
            $checksum = crc32($contents);
            $localOffset = ftell($stream);

            history_excel_write_all(
                $stream,
                pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 33, $checksum, $contentLength, $contentLength, $nameLength, 0)
                    . $name
                    . $contents
            );
            $centralDirectory .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                20,
                20,
                0,
                0,
                0,
                33,
                $checksum,
                $contentLength,
                $contentLength,
                $nameLength,
                0,
                0,
                0,
                0,
                0,
                $localOffset
            ) . $name;
        }

        $centralOffset = ftell($stream);
        history_excel_write_all($stream, $centralDirectory);
        $entryCount = count($files);
        history_excel_write_all(
            $stream,
            pack('VvvvvVVv', 0x06054b50, 0, 0, $entryCount, $entryCount, strlen($centralDirectory), $centralOffset, 0)
        );
        if (!fclose($stream)) {
            $stream = false;
            throw new RuntimeException('Gagal menyelesaikan berkas ekspor Excel.');
        }
        $stream = false;

        $archive = file_get_contents($temporaryPath);
        if (!is_string($archive)) {
            throw new RuntimeException('Tidak dapat membaca berkas ekspor Excel.');
        }

        return $archive;
    } finally {
        if (is_resource($stream)) {
            fclose($stream);
        }
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
}

function history_excel_create_workbook(array $records, string $logoPath): string
{
    $logo = file_get_contents($logoPath);
    if (!is_string($logo)) {
        throw new RuntimeException('Logo Tilik Padi tidak dapat dibaca.');
    }

        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Default Extension="jpg" ContentType="image/jpeg"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>'
                . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
                . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
                . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
                . '</Relationships>',
            'docProps/core.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
                . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
                . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                . '<dc:title>Laporan Riwayat Pemantauan Tilik Padi</dc:title><dc:creator>Tilik Padi</dc:creator>'
                . '<dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created>'
                . '</cp:coreProperties>',
            'docProps/app.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" '
                . 'xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
                . '<Application>Tilik Padi</Application></Properties>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
                . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<bookViews><workbookView/></bookViews>'
                . '<sheets><sheet name="Riwayat Pemantauan" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => history_excel_styles_xml(),
            'xl/worksheets/sheet1.xml' => history_excel_create_sheet_xml($records),
            'xl/worksheets/_rels/sheet1.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
                . '</Relationships>',
            'xl/drawings/drawing1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" '
                . 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" '
                . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<xdr:oneCellAnchor><xdr:from><xdr:col>0</xdr:col><xdr:colOff>85000</xdr:colOff>'
                . '<xdr:row>0</xdr:row><xdr:rowOff>25000</xdr:rowOff></xdr:from>'
                . '<xdr:ext cx="552450" cy="552450"/>'
                . '<xdr:pic><xdr:nvPicPr><xdr:cNvPr id="1" name="Logo Tilik Padi"/>'
                . '<xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
                . '<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
                . '<xdr:spPr><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr></xdr:pic>'
                . '<xdr:clientData/></xdr:oneCellAnchor></xdr:wsDr>',
            'xl/drawings/_rels/drawing1.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/logo.jpg"/>'
                . '</Relationships>',
            'xl/media/logo.jpg' => $logo,
        ];
    return history_excel_create_zip($files);
}

function history_excel_export(mysqli $connection): never
{
    try {
        $records = $connection->query(
            'SELECT detection.id_detection, detection.id_user, detection.image_path,
                detection.nama_penyakit, detection.confidence, detection.tanggal_deteksi,
                detection.infected_ratio_percent, user.nama AS user_name,
                user.email AS user_email, severity.label_keparahan,
                severity.deskripsi AS severity_description
             FROM detection
             LEFT JOIN `user` AS user ON user.id_user = detection.id_user
             LEFT JOIN tingkat_keparahan AS severity ON severity.id_keparahan = detection.id_keparahan
             ORDER BY detection.tanggal_deteksi DESC, detection.id_detection DESC
             LIMIT 100'
        )->fetch_all(MYSQLI_ASSOC);
        $workbook = history_excel_create_workbook(
            $records,
            dirname(__DIR__) . '/assets/images/logo.jpg'
        );
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="riwayat-pemantauan-' . date('Ymd-His') . '.xlsx"');
        header('Content-Length: ' . strlen($workbook));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $workbook;
        exit;
    } catch (Throwable $exception) {
        error_log('Scan history Excel export failed: ' . $exception->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
        }
        echo 'Ekspor riwayat pemantauan gagal. Silakan periksa koneksi database dan coba kembali.';
        exit;
    }
}
