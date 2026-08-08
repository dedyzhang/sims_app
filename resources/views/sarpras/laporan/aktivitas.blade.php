@extends('sarpras.layouts.app')
@section('title', 'Log Aktivitas Sarpras')
@section('sarpras_title', 'Log Aktivitas')

@section('sarpras_body')
<div class="card p-5">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-slate-400 border-b"><th class="pb-2">Waktu</th><th class="pb-2">Aksi</th><th class="pb-2">Pelaku</th><th class="pb-2">Detail</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr class="border-b border-slate-50">
                    <td class="py-2">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                    <td class="py-2 font-mono text-xs">{{ $log->aksi }}</td>
                    <td class="py-2">{{ $log->pelaku?->name ?? '—' }}</td>
                    <td class="py-2 text-xs text-slate-500">{{ $log->subjek_tipe ? class_basename($log->subjek_tipe) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="py-8 text-center text-slate-500">Belum ada aktivitas tercatat.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
