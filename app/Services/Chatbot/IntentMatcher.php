<?php

namespace App\Services\Chatbot;

/**
 * Rule-based intent matcher. Tidak memakai LLM/AI — murni pencocokan kata kunci.
 * Deterministik, gratis, dan tidak mengirim data siswa ke pihak ketiga.
 */
class IntentMatcher
{
    /**
     * Kembalikan nama intent yang cocok, atau null kalau tidak ada (fallback).
     */
    public function match(string $message): ?string
    {
        $normalized = $this->normalize($message);

        if ($normalized === '') {
            return null;
        }

        foreach (config('chatbot.intents') as $intent => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalized, $this->normalize($keyword))) {
                    return $intent;
                }
            }
        }

        return null;
    }

    /**
     * Lowercase + rapikan spasi ganda menjadi satu spasi.
     */
    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return preg_replace('/\s+/', ' ', $text);
    }
}
