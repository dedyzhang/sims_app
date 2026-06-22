<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreForumCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi reply dicek di controller (Policy)
    }

    public function rules(): array
    {
        return [
            'body'      => ['required', 'string', 'max:10000'],
            'parent_id' => ['nullable', 'string', 'exists:forum_comments,uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['body' => 'komentar'];
    }
}
