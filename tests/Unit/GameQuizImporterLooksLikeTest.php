<?php

namespace Tests\Unit;

use App\Services\GameQuizImporter;
use Tests\TestCase;

class GameQuizImporterLooksLikeTest extends TestCase
{
    public function test_mengenali_dokumen_soal_dan_menolak_rangkuman(): void
    {
        $soal = "SOAL EVALUASI IPA\n1. Apa?\nA. Satu\nB. Dua\n\nKunci Jawaban\n1. A";
        $ringkas = "RANGKUMAN MATERI\n- Fotosintesis adalah proses tumbuhan.";

        $this->assertTrue(GameQuizImporter::looksLikeImportableQuiz($soal));
        $this->assertTrue(GameQuizImporter::looksLikeImportableQuiz("1. Ibu kota?\nA. Bandung\nB. Jakarta\n\nKunci Jawaban\n1. B"));
        $this->assertFalse(GameQuizImporter::looksLikeImportableQuiz($ringkas));
        $this->assertFalse(GameQuizImporter::looksLikeImportableQuiz(''));
    }
}
