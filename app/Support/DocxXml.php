<?php

namespace App\Support;

use ZipArchive;

/**
 * Blok bangunan WordprocessingML yang dipakai bersama oleh penyusun .docx
 * (LearningDocxBuilder, QuizDocxBuilder): paragraf, run, sel, baris, tabel,
 * dan pembungkus paket .docx minimal.
 */
trait DocxXml
{
    protected static function run(string $text, bool $bold = false, bool $italic = false, int $sz = 20): string
    {
        $props = '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
            .($bold ? '<w:b/>' : '')
            .($italic ? '<w:i/>' : '')
            .'<w:sz w:val="'.$sz.'"/><w:szCs w:val="'.$sz.'"/>';

        return '<w:r><w:rPr>'.$props.'</w:rPr><w:t xml:space="preserve">'.htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8').'</w:t></w:r>';
    }

    /** @param array{align?:string,after?:int,before?:int,left?:int,hanging?:int,rule?:bool} $opts */
    protected static function p(array $runs, array $opts = []): string
    {
        $pPr = '';
        if (! empty($opts['rule'])) {
            $pPr .= '<w:pBdr><w:bottom w:val="single" w:sz="8" w:space="4" w:color="000000"/></w:pBdr>';
        }
        if (isset($opts['left']) || isset($opts['hanging'])) {
            $pPr .= '<w:ind'.(isset($opts['left']) ? ' w:left="'.$opts['left'].'"' : '').(isset($opts['hanging']) ? ' w:hanging="'.$opts['hanging'].'"' : '').'/>';
        }
        $pPr .= '<w:spacing'.(isset($opts['before']) ? ' w:before="'.$opts['before'].'"' : '').' w:after="'.($opts['after'] ?? 80).'"/>';
        if (isset($opts['align'])) {
            $pPr .= '<w:jc w:val="'.$opts['align'].'"/>';
        }

        return '<w:p><w:pPr>'.$pPr.'</w:pPr>'.implode('', $runs).'</w:p>';
    }

    /** @param array{w:int,span?:int,vmerge?:string,fill?:string|null,borders?:bool,border_sz?:int} $opts */
    protected static function tc(array $content, array $opts): string
    {
        $tcPr = '<w:tcW w:w="'.$opts['w'].'" w:type="dxa"/>';
        if (isset($opts['span'])) {
            $tcPr .= '<w:gridSpan w:val="'.$opts['span'].'"/>';
        }
        if (isset($opts['vmerge'])) {
            $tcPr .= $opts['vmerge'] === 'restart' ? '<w:vMerge w:val="restart"/>' : '<w:vMerge/>';
        }
        // Border per-sel wajib agar garis tidak putus (terutama di sel vMerge).
        if ($opts['borders'] ?? true) {
            $sz = (int) ($opts['border_sz'] ?? 4);
            $tcPr .= '<w:tcBorders>'
                .'<w:top w:val="single" w:color="000000" w:sz="'.$sz.'"/>'
                .'<w:left w:val="single" w:color="000000" w:sz="'.$sz.'"/>'
                .'<w:bottom w:val="single" w:color="000000" w:sz="'.$sz.'"/>'
                .'<w:right w:val="single" w:color="000000" w:sz="'.$sz.'"/>'
                .'</w:tcBorders>';
        }
        if (! empty($opts['fill'])) {
            $tcPr .= '<w:shd w:val="clear" w:color="auto" w:fill="'.$opts['fill'].'"/>';
        }

        return '<w:tc><w:tcPr>'.$tcPr.'</w:tcPr>'.implode('', $content).'</w:tc>';
    }

    protected static function tr(string $cells): string
    {
        return '<w:tr>'.$cells.'</w:tr>';
    }

    /** @param int[] $gridCols */
    protected static function tbl(array $gridCols, string $rows, bool $bordered = true, bool $trailingP = true): string
    {
        $borders = $bordered
            ? '<w:tblBorders>'
                .'<w:top w:val="single" w:sz="4" w:color="000000"/>'
                .'<w:left w:val="single" w:sz="4" w:color="000000"/>'
                .'<w:bottom w:val="single" w:sz="4" w:color="000000"/>'
                .'<w:right w:val="single" w:sz="4" w:color="000000"/>'
                .'<w:insideH w:val="single" w:sz="4" w:color="000000"/>'
                .'<w:insideV w:val="single" w:sz="4" w:color="000000"/>'
                .'</w:tblBorders>'
            : '';

        $grid = '';
        foreach ($gridCols as $w) {
            $grid .= '<w:gridCol w:w="'.$w.'"/>';
        }

        return '<w:tbl><w:tblPr><w:tblW w:w="'.array_sum($gridCols).'" w:type="dxa"/>'.$borders
            .'<w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:left w:w="100" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="100" w:type="dxa"/></w:tblCellMar>'
            .'<w:tblLayout w:type="fixed"/></w:tblPr>'
            .'<w:tblGrid>'.$grid.'</w:tblGrid>'
            .$rows
            .'</w:tbl>'
            .($trailingP ? '<w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>' : '');
    }

    protected static function pageBreak(): string
    {
        return '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
    }

    /** Bungkus body WordprocessingML jadi dokumen A4 utuh. */
    protected static function documentXmlFor(string $body): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .$body
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    /** Tulis paket .docx minimal (hanya word/document.xml) ke $path. */
    protected static function writeDocxPackage(string $path, string $documentXml): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', $documentXml);

        return $zip->close();
    }
}
