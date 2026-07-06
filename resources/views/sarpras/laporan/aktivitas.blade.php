@extends('sarpras.layouts.app')
@section('title', 'Log Aktivitas Sarpras')

@section('sarpras_body')
<h2 class="text-lg font-semibold text-gray-800 mb-4">Laporan Aktivitas Sarpras</h2>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Waktu</th><th>Aksi</th><th>Objek</th><th>Oleh</th>
        </tr></thead>
        <tbody>
        @foreach($aktivitas as $a)
            <tr class="border-b">
                <td class="py-2 px-4">{{ $a->created_at->format('d/m/Y H:i') }}</td>
                <td class="capitalize">{{ $a->description ?? $a->event }}</td>
                <td>{{ class_basename($a->subject_type) }} #{{ \Illuminate\Support\Str::limit($a->subject_id, 8, '') }}</td>
                <td>{{ $a->causer?->name ?? 'sistem' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@endsection
