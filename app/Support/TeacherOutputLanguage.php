<?php

namespace App\Support;

/**
 * Bahasa output Generator Soal & RPM Asisten Guru.
 */
final class TeacherOutputLanguage
{
    public const DEFAULT = 'id';

    /** @var array<string, string> */
    public const OPTIONS = [
        'id' => 'Bahasa Indonesia',
        'zh-CN' => '中文 (简体) — Mandarin',
        'en' => 'English',
        'ja' => '日本語 — Japanese',
        'ar' => 'العربية — Arabic',
    ];

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::OPTIONS);
    }

    public static function label(string $code): string
    {
        $code = self::normalize($code);

        return self::OPTIONS[$code];
    }

    public static function normalize(?string $code): string
    {
        $code = trim((string) $code);
        if ($code === '') {
            return self::DEFAULT;
        }

        $lower = strtolower($code);
        if (in_array($lower, ['zh', 'zh-hans', 'zh_cn', 'zh-cn'], true)) {
            return 'zh-CN';
        }
        if (in_array($lower, ['ar', 'ar-sa', 'arabic'], true)) {
            return 'ar';
        }

        foreach (array_keys(self::OPTIONS) as $key) {
            if (strtolower($key) === $lower) {
                return $key;
            }
        }

        return self::DEFAULT;
    }

    /** @return list<string> */
    public static function validationRules(): array
    {
        return ['nullable', 'string', 'in:'.implode(',', self::codes())];
    }

    public static function usesGlobalSystemPrompt(string $code): bool
    {
        return self::normalize($code) === self::DEFAULT;
    }

    /** Instruksi bahasa untuk prompt permintaan (quiz/RPM). */
    public static function promptLine(string $code): string
    {
        return match (self::normalize($code)) {
            'zh-CN' => 'Gunakan 简体中文 (Mandarin Simplified) untuk seluruh isi dokumen: soal, petunjuk, rubrik, narasi RPM, dan lampiran. '
                .'Kop sekolah serta baris identitas resmi (nama sekolah, NIP) tetap seperti data SIMS — jangan diterjemahkan.',
            'en' => 'Write the entire document content in English: questions, instructions, rubrics, RPM narrative, and appendices. '
                .'Keep the school letterhead and official identity lines (school name, principal NIP) exactly as provided in SIMS — do not translate those.',
            'ja' => 'ドキュメント全体（問題、指示、ルーブリック、RPMの本文、付録）を日本語で作成してください。'
                .'学校のヘッダーと公式の身分欄（学校名、NIP）はSIMSのデータのままにしてください——翻訳しないでください。',
            'ar' => 'اكتب محتوى الوثيقة كله باللغة العربية الفصحى المبسطة: الأسئلة، التعليمات، rubrik، narasi RPM، والملحقات. '
                .'اترك ترويسة المدرسة وبيانات الهوية الرسمية مثل اسم المدرسة وNIP كما وردت في SIMS — لا تترجمها.',
            default => 'Gunakan Bahasa Indonesia baku, praktis, dan langsung bisa direview guru.',
        };
    }

    /** Catatan tambahan pada system prompt fitur guru (non-Indonesia). */
    public static function systemNote(string $code): string
    {
        return match (self::normalize($code)) {
            'zh-CN' => 'Bahasa output wajib 简体中文 untuk semua isi pembelajaran/soal. Hanya kop dan identitas resmi sekolah yang boleh non-Mandarin.',
            'en' => 'Output language must be English for all learning/quiz content. Only school letterhead and official identity lines may stay as provided.',
            'ja' => '出力言語は学習内容・問題文すべて日本語。学校ヘッダーと公式身分欄のみSIMSのまま。',
            'ar' => 'Output language must be clear Modern Standard Arabic for all learning/quiz content. Only school letterhead and official identity lines may stay as provided.',
            default => '',
        };
    }

    public static function appendSystemNote(string $baseSystem, string $code): string
    {
        $note = self::systemNote($code);

        return $note !== '' ? trim($baseSystem."\n\n".$note) : $baseSystem;
    }

    /** Instruksi tambahan bila guru minta pinyin pada output Mandarin. */
    public static function pinyinLine(bool $include): string
    {
        if (! $include) {
            return '';
        }

        return 'Sertakan baris pinyin (Hanyu Pinyin) di bawah setiap kalimat atau frasa Hanzi utama '
            .'pada soal, petunjuk, rubrik, dan lampiran — Hanzi di baris atas, pinyin di baris berikutnya.';
    }

    public static function topicPlaceholder(string $code): string
    {
        return match (self::normalize($code)) {
            'zh-CN' => 'mis. 第三课 打招呼, 数字和时间, 我的爱好',
            'en' => 'e.g. Linear Equations, Photosynthesis, Reading Comprehension',
            'ja' => '例: 自己紹介, 数字と時間',
            'ar' => 'مثال: التعارف، الأرقام والوقت، النظام البيئي',
            default => 'mis. Bab 5 — Ekosistem, Fotosintesis, Pecahan...',
        };
    }

    /** @return list<string> */
    public static function hsk1TopicExamples(): array
    {
        return [
            '第三课 打招呼 — Salam & perkenalan',
            '数字和时间 — Angka & waktu',
            '交通和位置 — Transportasi & lokasi',
            '天气 — Cuaca',
        ];
    }

    /**
     * Petunjuk khusus RPM Bahasa Mandarin agar AI selaras KM 2026 + parser SIMS.
     * Judul bagian RPM tetap Indonesia; isi narasi/soal dalam 简体中文.
     */
    public static function rpmMandarinHints(): string
    {
        return <<<'TXT'
            Petunjuk RPM Bahasa Mandarin (Kurikulum Merdeka / PM 2025–2026):
            - Acuan CP: 听/说/读/写 (menyimak, berbicara, membaca, menulis) setara HSK 1; integrasi lintas budaya.
            - Judul bagian WAJIB tetap Indonesia: IDENTIFIKASI, DESAIN PEMBELAJARAN, PENGALAMAN BELAJAR, ASESMEN PEMBELAJARAN, LAMPIRAN 1–3, label DPL 1–8.
            - Isi Murid, Materi, Capaian Pembelajaran, Tujuan Pembelajaran, kegiatan, soal, dan rubrik lampiran tulis dalam 简体中文.
            - Tujuan Pembelajaran sebutkan keterampilan 听/说/读/写 bila relevan; kegiatan INTI memakai tahap MEMAHAMI, MENGAPLIKASI, MEREFLEKSI.
            - Topik HSK 1 umum: 打招呼, 数字和时间, 交通和位置, 爱好, 天气.
            - Contoh referensi guru: docs/contoh-rpm-mandarin-km2026.md
            TXT;
    }
}
