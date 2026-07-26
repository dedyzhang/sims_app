<?php

namespace Tests\Unit;

use App\Support\TeacherOutputLanguage;
use PHPUnit\Framework\TestCase;

class TeacherOutputLanguageTest extends TestCase
{
    public function test_normalize_aliases_mandarin(): void
    {
        $this->assertSame('zh-CN', TeacherOutputLanguage::normalize('zh'));
        $this->assertSame('zh-CN', TeacherOutputLanguage::normalize('zh-hans'));
        $this->assertSame('zh-CN', TeacherOutputLanguage::normalize('zh-CN'));
    }

    public function test_unknown_code_falls_back_to_indonesia(): void
    {
        $this->assertSame('id', TeacherOutputLanguage::normalize('fr'));
        $this->assertSame('id', TeacherOutputLanguage::normalize(null));
    }

    public function test_non_indonesia_skips_global_system_prompt(): void
    {
        $this->assertTrue(TeacherOutputLanguage::usesGlobalSystemPrompt('id'));
        $this->assertFalse(TeacherOutputLanguage::usesGlobalSystemPrompt('zh-CN'));
        $this->assertFalse(TeacherOutputLanguage::usesGlobalSystemPrompt('en'));
        $this->assertFalse(TeacherOutputLanguage::usesGlobalSystemPrompt('ja'));
    }

    public function test_prompt_line_contains_target_language_hint(): void
    {
        $this->assertStringContainsString('简体中文', TeacherOutputLanguage::promptLine('zh-CN'));
        $this->assertStringContainsString('English', TeacherOutputLanguage::promptLine('en'));
        $this->assertStringContainsString('日本語', TeacherOutputLanguage::promptLine('ja'));
        $this->assertStringContainsString('Bahasa Indonesia', TeacherOutputLanguage::promptLine('id'));
    }

    public function test_pinyin_line_hanya_saat_diminta(): void
    {
        $this->assertSame('', TeacherOutputLanguage::pinyinLine(false));
        $this->assertStringContainsString('pinyin', TeacherOutputLanguage::pinyinLine(true));
    }

    public function test_hsk1_examples_tidak_kosong(): void
    {
        $this->assertNotEmpty(TeacherOutputLanguage::hsk1TopicExamples());
        $this->assertStringContainsString('打招呼', TeacherOutputLanguage::hsk1TopicExamples()[0]);
    }

    public function test_rpm_mandarin_hints_menyebut_keterampilan_dan_parser_sims(): void
    {
        $hints = TeacherOutputLanguage::rpmMandarinHints();

        $this->assertStringContainsString('听/说/读/写', $hints);
        $this->assertStringContainsString('IDENTIFIKASI', $hints);
        $this->assertStringContainsString('HSK 1', $hints);
        $this->assertStringContainsString('contoh-rpm-mandarin-km2026.md', $hints);
    }
}
