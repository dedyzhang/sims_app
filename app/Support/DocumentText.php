<?php

namespace App\Support;

use Smalot\PdfParser\Parser as PdfParser;
use ZipArchive;

/*
| Ekstraksi teks mentah dari dokumen unggahan (PDF, DOCX, DOC lama, teks polos).
|
| Dipakai bersama oleh AiTeacherController (Generator Soal / RPM) dan RagService
| (ingest RAG). Sebelumnya logika ini hanya ada di controller, sehingga RagService
| membaca .docx sebagai biner zip mentah dan meng-embed sampah.
*/
class DocumentText
{
    /**
     * Ekstrak teks dari file.
     *
     * @param  string  $extension  Ekstensi file (pdf|docx|doc|txt|...). Selain yang
     *                             dikenali diperlakukan sebagai teks polos.
     * @param  bool  $preserveNewlines  Pertahankan paragraf (untuk dokumen yang akan
     *                                  dibaca manusia); false merapatkan semua spasi.
     */
    public static function extract(string $path, string $extension, bool $preserveNewlines = false): string
    {
        $extension = strtolower($extension);

        try {
            $text = match ($extension) {
                'pdf' => (new PdfParser)->parseFile($path)->getText(),
                'docx' => self::fromDocx($path),
                'doc' => self::fromLegacyDoc($path),
                default => (string) file_get_contents($path),
            };
        } catch (\Throwable) {
            return '';
        }

        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', ' ', (string) $text);

        if ($preserveNewlines) {
            $text = preg_replace("/[ \t]+/u", ' ', (string) $text);
            $text = preg_replace("/\n{3,}/u", "\n\n", (string) $text);

            return trim((string) $text);
        }

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Tebak ekstensi dari MIME saat nama file tidak tersedia. */
    public static function extensionFromMime(?string $mime): string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/msword' => 'doc',
            default => '',
        };
    }

    private static function fromDocx(string $path): string
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return '';
        }

        $parts = ['word/document.xml'];
        $text = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/(header|footer|footnotes|endnotes)\d*\.xml$#', $name)) {
                $parts[] = $name;
            }
        }

        foreach (array_unique($parts) as $part) {
            $xml = $zip->getFromName($part);
            if ($xml === false) {
                continue;
            }

            $xml = preg_replace('/<w:(tab|br|cr)[^>]*\/>/i', ' ', $xml);
            $xml = preg_replace('/<\/w:t>\s*<w:t[^>]*>/i', ' ', $xml);
            $xml = preg_replace('/<\/w:p>/i', "\n", $xml);
            $text .= ' '.html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
        }

        $zip->close();

        return $text;
    }

    private static function fromLegacyDoc(string $path): string
    {
        $raw = (string) file_get_contents($path);
        if ($raw === '') {
            return '';
        }

        preg_match_all('/[\x20-\x7E]{3,}/', $raw, $matches);

        return implode(' ', $matches[0] ?? []);
    }
}
