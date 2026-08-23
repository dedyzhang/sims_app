<?php

namespace App\Exports\Keuangan\Rkas\Concerns;

trait EscapesSpreadsheetText
{
    private function safeText(mixed $value): string
    {
        $text = (string) ($value ?? '');
        if ($text === '') {
            return '';
        }

        return preg_match('/^[=\+\-@\t\r]/u', $text) === 1 ? "'".$text : $text;
    }
}
