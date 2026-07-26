<?php

namespace Tests\Unit;

use App\Support\DocumentText;
use Tests\TestCase;
use ZipArchive;

class DocumentTextTest extends TestCase
{
    public function test_docx_menjaga_aksara_hanzi(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mandarin-docx');
        $this->makeDocx($path, '第三课 打招呼：你好，我叫小明。');

        $text = DocumentText::extract($path, 'docx', true);

        unlink($path);

        $this->assertStringContainsString('第三课', $text);
        $this->assertStringContainsString('你好', $text);
        $this->assertStringContainsString('小明', $text);
    }

    private function makeDocx(string $path, string $body): void
    {
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($body, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
    }
}
