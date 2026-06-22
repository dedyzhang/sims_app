<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassroomMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $max = (int) config('classroom.max_files', 10);
        $maxKb = (int) config('classroom.max_file_mb', 20) * 1024;

        return [
            'title'       => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'body'        => ['nullable', 'string', 'max:200000'],
            'link_url'    => ['nullable', 'url', 'max:500'],
            'meet_url'    => ['nullable', 'string', 'max:300', 'regex:/(meet\.google\.com|^[a-z]{3}-[a-z]{4}-[a-z]{3}$)/i'],
            'kelas'        => ['required', 'array', 'min:1'],
            'kelas.*'      => ['string', 'exists:kelas,uuid'],
            'is_locked'    => ['nullable', 'boolean'],
            'access_token' => ['nullable', 'string', 'max:16', 'required_if:is_locked,1'],
            'files'       => ['nullable', 'array', "max:{$max}"],
            'files.*'     => ['file', 'mimes:jpg,jpeg,png,webp,heic,pdf', "max:{$maxKb}"],
        ];
    }

    public function attributes(): array
    {
        return ['title' => 'judul materi', 'files.*' => 'file'];
    }
}
