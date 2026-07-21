<?php

namespace Tests\Unit;

use App\Support\ArenaMechanics;
use Tests\TestCase;

class ArenaMechanicsTest extends TestCase
{
    public function test_nalar_bundle_tidak_dipanggil_nalar_saja(): void
    {
        $label = ArenaMechanics::label('nalar_bundle');

        $this->assertStringContainsString('cerita', mb_strtolower($label));
        $this->assertStringNotContainsStringIgnoringCase('Nalar Guru', $label);
        $this->assertNotSame('Nalar', $label);
    }

    public function test_labels_mencakup_mekanik_utama(): void
    {
        $labels = ArenaMechanics::labels();

        $this->assertArrayHasKey('nalar_bundle', $labels);
        $this->assertArrayHasKey('recall_quiz_bundle', $labels);
        $this->assertArrayHasKey('puzzle_sequencing', $labels);
        $this->assertArrayNotHasKey('recall_quiz', $labels);
        $this->assertSame('Kuis di dalam misi', ArenaMechanics::label('recall_quiz'));
        $this->assertSame('Misi', ArenaMechanics::label(null));
        $this->assertSame('foo bar', ArenaMechanics::label('foo_bar'));
    }
}
