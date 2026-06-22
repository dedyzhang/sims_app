<?php

namespace App\Http\Requests;

use App\Support\Forum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreForumTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // otorisasi dilakukan eksplisit di controller (Policy)
    }

    public function rules(): array
    {
        return [
            'title'        => ['required', 'string', 'max:160'],
            'body'         => ['required', 'string', 'max:20000'],
            'category'     => ['required', Rule::in(array_keys(Forum::CATEGORIES))],
            'audience'     => ['required', Rule::in(array_keys(Forum::AUDIENCES))],
            'id_kelas'     => ['nullable', 'string', 'exists:kelas,uuid'],
            'id_pelajaran' => ['nullable', 'string', 'exists:pelajarans,uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul', 'body' => 'isi', 'id_kelas' => 'kelas', 'id_pelajaran' => 'mata pelajaran',
        ];
    }
}
