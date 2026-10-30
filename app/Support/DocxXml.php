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
    protected static function run(string $text, bool $bold = false, bool $italic = false, int $sz = 20, ?string $color = null, bool $underline = false): string
    {
        $props = '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
            .($bold ? '<w:b/>' : '')
            .($italic ? '<w:i/>' : '')
            .($underline ? '<w:u w:val="single"/>' : '')
            .($color !== null ? '<w:color w:val="'.$color.'"/>' : '')
            .'<w:sz w:val="'.$sz.'"/><w:szCs w:val="'.$sz.'"/>';

        return '<w:r><w:rPr>'.$props.'</w:rPr><w:t xml:space="preserve">'.htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8').'</w:t></w:r>';
    }

    /** @param array{align?:string,after?:int,spaceAfter?:int,before?:int,spaceBefore?:int,left?:int,hanging?:int,rule?:bool,bottomBorder?:string,borderSz?:int,border_sz?:int} $opts */
    protected static function p(array $runs, array $opts = []): string
    {
        $pPr = '';
        if (! empty($opts['rule']) || ! empty($opts['bottomBorder'])) {
            $sz = (int) ($opts['borderSz'] ?? $opts['border_sz'] ?? 8);
            $pPr .= '<w:pBdr><w:bottom w:val="single" w:sz="'.$sz.'" w:space="4" w:color="000000"/></w:pBdr>';
        }
        if (isset($opts['left']) || isset($opts['hanging'])) {
            $pPr .= '<w:ind'.(isset($opts['left']) ? ' w:left="'.$opts['left'].'"' : '').(isset($opts['hanging']) ? ' w:hanging="'.$opts['hanging'].'"' : '').'/>';
        }
        $after = $opts['after'] ?? ($opts['spaceAfter'] ?? 80);
        $before = $opts['before'] ?? ($opts['spaceBefore'] ?? null);
        $pPr .= '<w:spacing'.($before !== null ? ' w:before="'.$before.'"' : '').' w:after="'.$after.'"/>';
        if (isset($opts['align'])) {
            $pPr .= '<w:jc w:val="'.$opts['align'].'"/>';
        }

        return '<w:p><w:pPr>'.$pPr.'</w:pPr>'.implode('', $runs).'</w:p>';
    }

    /** @param array{w:int,span?:int,gridSpan?:int,vmerge?:string,vMerge?:string,fill?:string|null,shd?:string|null,borders?:bool,noBorder?:bool,border_sz?:int,valign?:string,vAlign?:string,textDirection?:string} $opts */
    protected static function tc(array $content, array $opts): string
    {
        $tcPr = '<w:tcW w:w="'.$opts['w'].'" w:type="dxa"/>';

        $span = $opts['span'] ?? ($opts['gridSpan'] ?? null);
        if ($span !== null && (int) $span > 1) {
            $tcPr .= '<w:gridSpan w:val="'.$span.'"/>';
        }

        $vmerge = $opts['vmerge'] ?? ($opts['vMerge'] ?? null);
        if ($vmerge !== null) {
            $tcPr .= $vmerge === 'restart' ? '<w:vMerge w:val="restart"/>' : '<w:vMerge/>';
        }

        $hasBorders = ($opts['borders'] ?? true) && empty($opts['noBorder']);
        if ($hasBorders) {
            $sz = (int) ($opts['border_sz'] ?? 4);
            $tcPr .= '<w:tcBorders>'
                .'<w:top w:val="single" w:color="000000" w:sz="'.$sz.'"/>'
                .'<w:left w:val="single" w:color="000000" w:sz="'.$sz.'"/>'
                .'<w:bottom w:val="single" w:color="000000" w:sz="'.$sz.'"/>'
                .'<w:right w:val="single" w:color="000000" w:sz="'.$sz.'"/>'
                .'</w:tcBorders>';
        } else {
            $tcPr .= '<w:tcBorders>'
                .'<w:top w:val="none"/>'
                .'<w:left w:val="none"/>'
                .'<w:bottom w:val="none"/>'
                .'<w:right w:val="none"/>'
                .'</w:tcBorders>';
        }

        $fill = $opts['fill'] ?? ($opts['shd'] ?? null);
        if (! empty($fill)) {
            $tcPr .= '<w:shd w:val="clear" w:color="auto" w:fill="'.ltrim($fill, '#').'"/>';
        }

        if (! empty($opts['textDirection'])) {
            $tcPr .= '<w:textDirection w:val="'.$opts['textDirection'].'"/>';
        }

        $valign = $opts['valign'] ?? ($opts['vAlign'] ?? null);
        if (! empty($valign)) {
            $tcPr .= '<w:vAlign w:val="'.$valign.'"/>';
        }

        // OpenXML mewajibkan sel tabel berisi setidaknya satu unsur block-level (<w:p>)
        $inner = empty($content) ? '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr></w:p>' : implode('', $content);

        return '<w:tc><w:tcPr>'.$tcPr.'</w:tcPr>'.$inner.'</w:tc>';
    }

    /** @param array{header?:bool,cantSplit?:bool} $opts */
    protected static function tr(string $cells, array $opts = []): string
    {
        $trPr = '';
        if (! empty($opts['header'])) {
            $trPr .= '<w:tblHeader/>';
        }
        if ($opts['cantSplit'] ?? true) {
            $trPr .= '<w:cantSplit/>';
        }

        return '<w:tr>'.($trPr !== '' ? '<w:trPr>'.$trPr.'</w:trPr>' : '').$cells.'</w:tr>';
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
            .'<w:tblCellMar><w:top w:w="60" w:type="dxa"/><w:left w:w="60" w:type="dxa"/><w:bottom w:w="60" w:type="dxa"/><w:right w:w="60" w:type="dxa"/></w:tblCellMar>'
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

    /**
     * Bungkus body WordprocessingML jadi dokumen A4 utuh.
     *
     * @param  array{orientation?:string,landscape?:bool,margin?:array{top?:int,right?:int,bottom?:int,left?:int}}  $opts
     */
    protected static function documentXmlFor(string $body, array $opts = []): string
    {
        $landscape = (($opts['orientation'] ?? 'portrait') === 'landscape')
            || (! empty($opts['landscape']));
        $pageW = $landscape ? 16838 : 11906;
        $pageH = $landscape ? 11906 : 16838;
        $orient = $landscape ? ' w:orient="landscape"' : '';
        $margin = $opts['margin'] ?? [];

        // Standar margin landscape: 1140 dxa (~20mm) agar pas dengan lebar konten tabel 14558 dxa
        $defaultMargin = $landscape ? 1140 : 1440;
        $top = (int) ($margin['top'] ?? $defaultMargin);
        $right = (int) ($margin['right'] ?? $defaultMargin);
        $bottom = (int) ($margin['bottom'] ?? $defaultMargin);
        $left = (int) ($margin['left'] ?? $defaultMargin);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'
            .$body
            .'<w:sectPr><w:pgSz w:w="'.$pageW.'" w:h="'.$pageH.'"'.$orient.'/><w:pgMar w:top="'.$top.'" w:right="'.$right.'" w:bottom="'.$bottom.'" w:left="'.$left.'" w:header="720" w:footer="720" w:gutter="0"/></w:sectPr>'
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
