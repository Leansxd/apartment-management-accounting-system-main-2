<?php

namespace App\Support;

use App\Models\Apartment;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Biçimli Excel (.xlsx) çıktısı; ek paket gerektirmez (PHP zip). Üstte site, rapor adı ve dönem; altında gri
 * başlık satırı, çizgili hücreler, ₺ biçimli tutarlar ve kalın toplam satırı. Başlık satırı sabitlenir ve
 * filtrelenebilir, A4'e sığacak şekilde basılır ve her basılan sayfada tekrar eder.
 *
 * Hücre türleri değerden çıkar: float → TL tutar, int → sayı, diğerleri metin. Metinler formül olarak
 * yazılmaz, "=..." ile başlayan sakin girdisi Excel'de çalışmaz.
 */
final class SpreadsheetExport
{
    private const HEADER_ROW = 5;

    // Stil sıraları styles.xml içindeki cellXfs ile aynıdır
    private const TITLE = 1;

    private const SUBTITLE = 2;

    private const META = 3;

    private const HEAD = 4;

    private const TEXT = 5;

    private const MONEY = 6;

    private const NUMBER = 7;

    private const TOTAL_TEXT = 8;

    private const TOTAL_MONEY = 9;

    private const TOTAL_NUMBER = 10;

    /**
     * @param  array<string, string>  $columns  anahtar => başlık
     * @param  iterable<array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $totals  son satır (ör. TOPLAM)
     * @param  array{title?: string, period?: string|null, document_no?: string|null, apartment?: Apartment|null}  $meta
     */
    public static function download(string $filename, array $columns, iterable $rows, ?array $totals = null, array $meta = []): BinaryFileResponse
    {
        $apartment = $meta['apartment'] ?? (request()->route('apartment') instanceof Apartment ? request()->route('apartment') : null);
        $title = $meta['title'] ?? pathinfo($filename, PATHINFO_FILENAME);
        $filename = preg_replace('/\.(csv|xlsx)$/i', '', $filename).'.xlsx';

        $keys = array_keys($columns);
        $last = self::column(count($keys));
        $widths = array_map(fn (string $label) => mb_strlen($label) + 4, array_values($columns));
        $kinds = array_fill(0, count($keys), 'text');

        $data = tmpfile();
        $rowNumber = self::HEADER_ROW;
        $header = '';
        foreach (array_values($columns) as $i => $label) {
            $header .= self::cell(self::column($i + 1).self::HEADER_ROW, $label, self::HEAD);
        }
        fwrite($data, '<row r="'.self::HEADER_ROW.'" ht="22" customHeight="1">'.$header.'</row>');

        $writeRow = function (array $row, bool $isTotal) use ($keys, &$rowNumber, &$widths, &$kinds, $data): void {
            $rowNumber++;
            $cells = '';
            foreach ($keys as $i => $key) {
                $value = $row[$key] ?? null;
                $style = match (true) {
                    is_float($value) => $isTotal ? self::TOTAL_MONEY : self::MONEY,
                    is_int($value) => $isTotal ? self::TOTAL_NUMBER : self::NUMBER,
                    default => $isTotal ? self::TOTAL_TEXT : self::TEXT,
                };
                if (is_float($value)) {
                    $kinds[$i] = 'money';
                }
                $display = is_float($value) ? number_format($value, 2, ',', '.').' ₺' : (string) ($value ?? '');
                $widths[$i] = max($widths[$i], min(mb_strlen($display) + 2, 60));
                $cells .= self::cell(self::column($i + 1).$rowNumber, $value, $style);
            }
            fwrite($data, '<row r="'.$rowNumber.'">'.$cells.'</row>');
        };
        foreach ($rows as $row) {
            $writeRow($row, false);
        }
        $lastDataRow = $rowNumber;
        if ($totals) {
            $writeRow($totals, true);
        }

        $period = array_filter([
            $meta['period'] ?? null,
            'Düzenlenme: '.now()->format('d.m.Y H:i'),
            ($meta['document_no'] ?? null) ? 'Belge No: '.$meta['document_no'] : null,
            auth()->user() ? 'Düzenleyen: '.auth()->user()->name : null,
        ]);
        $top = '<row r="1" ht="24" customHeight="1">'.self::cell('A1', mb_strtoupper($apartment->name ?? config('app.name')), self::TITLE).'</row>'
            .'<row r="2" ht="20" customHeight="1">'.self::cell('A2', $title, self::SUBTITLE).'</row>'
            .'<row r="3">'.self::cell('A3', implode('  ·  ', $period), self::META).'</row>';

        $cols = '';
        foreach ($widths as $i => $width) {
            $width = $kinds[$i] === 'money' ? max($width, 16) : max($width, 8);
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }
        $merges = count($keys) > 1 ? '<mergeCells count="3"><mergeCell ref="A1:'.$last.'1"/><mergeCell ref="A2:'.$last.'2"/><mergeCell ref="A3:'.$last.'3"/></mergeCells>' : '';
        $orientation = count($keys) > 7 ? 'landscape' : 'portrait';
        $footer = self::xml(($apartment->name ?? '').' · '.$title);

        rewind($data);
        $sheetPath = tempnam(sys_get_temp_dir(), 'xlsx-sheet');
        $sheet = fopen($sheetPath, 'w');
        fwrite($sheet, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<dimension ref="A1:'.$last.max($rowNumber, self::HEADER_ROW).'"/>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="'.self::HEADER_ROW.'" topLeftCell="A'.(self::HEADER_ROW + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="16"/>'
            .'<cols>'.$cols.'</cols><sheetData>'.$top);
        stream_copy_to_stream($data, $sheet);
        fclose($data);
        fwrite($sheet, '</sheetData>'
            .'<autoFilter ref="A'.self::HEADER_ROW.':'.$last.$lastDataRow.'"/>'
            .$merges
            .'<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.6" header="0.3" footer="0.3"/>'
            .'<pageSetup paperSize="9" orientation="'.$orientation.'" fitToWidth="1" fitToHeight="0"/>'
            .'<headerFooter><oddFooter>&amp;L'.$footer.'&amp;RSayfa &amp;P / &amp;N</oddFooter></headerFooter>'
            .'</worksheet>');
        fclose($sheet);

        $zipPath = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Excel dosyası oluşturulamadı.');
        }
        $sheetName = self::xml(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', ' ', $title), 0, 31));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'</Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.self::xml($title).'</dc:title><dc:creator>'.self::xml(auth()->user()?->name ?? config('app.name')).'</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.now()->utc()->format('Y-m-d\TH:i:s\Z').'</dcterms:created>'
            .'</cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$sheetName.'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.$sheetName.'\'!$A$'.self::HEADER_ROW.':$'.$last.'$'.$lastDataRow.'</definedName>'
            .'<definedName name="_xlnm.Print_Titles" localSheetId="0">\''.$sheetName.'\'!$'.self::HEADER_ROW.':$'.self::HEADER_ROW.'</definedName></definedNames>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/styles.xml', self::styles());
        $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($sheetPath);

        return response()->download($zipPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** 1 → A, 27 → AA */
    private static function column(int $index): string
    {
        $name = '';
        for (; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26).$name;
        }

        return $name;
    }

    private static function cell(string $ref, mixed $value, int $style): string
    {
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$ref.'" s="'.$style.'"><v>'.(is_float($value) ? round($value, 2) : $value).'</v></c>';
        }

        return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.self::xml((string) ($value ?? '')).'</t></is></c>';
    }

    /** XML'e güvenli metin: özel karakterler kaçırılır, XML'de geçersiz kontrol karakterleri çıkarılır */
    private static function xml(string $text): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function styles(): string
    {
        $thin = '<left style="thin"><color rgb="FF9CA3AF"/></left><right style="thin"><color rgb="FF9CA3AF"/></right><top style="thin"><color rgb="FF9CA3AF"/></top><bottom style="thin"><color rgb="FF9CA3AF"/></bottom><diagonal/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00\ &quot;₺&quot;;[Red]\-#,##0.00\ &quot;₺&quot;"/><numFmt numFmtId="165" formatCode="#,##0"/></numFmts>'
            .'<fonts count="5">'
            .'<font><sz val="10"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="10"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="14"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><i/><sz val="9"/><color rgb="FF6B7280"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="12"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE5E7EB"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF3F4F6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="3"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border>'.$thin.'</border>'
            .'<border><left style="thin"><color rgb="FF9CA3AF"/></left><right style="thin"><color rgb="FF9CA3AF"/></right><top style="double"><color rgb="FF111827"/></top><bottom style="thin"><color rgb="FF9CA3AF"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="11">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment vertical="top"/></xf>'
            .'<xf numFmtId="165" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="right" vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="1" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="164" fontId="1" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'<xf numFmtId="165" fontId="1" fillId="3" borderId="2" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
