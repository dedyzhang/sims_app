@extends('layouts.app')
@section('title', 'Jejak Audit Keuangan SPP')

@section('content')
<div class="space-y-5">
    <div>
        <nav class="text-xs text-slate-400 mb-1">
            <a href="{{ route('keuangan.bendahara-ai.index', ['ta'=>$ta]) }}" class="hover:underline">Asisten Bendahara</a> / Jejak Audit
        </nav>
        <h1 class="page-title">Jejak Audit Transisi Keuangan</h1>
        <p class="text-sm text-slate-500 dark:text-slate-400">Perubahan status verifikasi & impor rekening koran</p>
    </div>

    <div class="card overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800 text-xs text-slate-500 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Waktu</th>
                    <th class="px-4 py-3 text-left">Aktor</th>
                    <th class="px-4 py-3 text-left">Event</th>
                    <th class="px-4 py-3 text-left">Detail</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                @forelse($logs as $log)
                @php $props = $log->properties?->toArray() ?? []; @endphp
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                    <td class="px-4 py-2.5 text-xs text-slate-400 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2.5 text-xs">{{ $log->causer?->username ?? '-' }}</td>
                    <td class="px-4 py-2.5">
                        <span class="badge bg-sky-100 dark:bg-sky-900 text-sky-700 text-[11px]">{{ $log->event ?? '-' }}</span>
                    </td>
                    <td class="px-4 py-2.5 text-xs text-slate-600 dark:text-slate-300">
                        {{ $log->description }}
                        @if(isset($props['status_sebelum'], $props['status_sesudah']))
                            <span class="text-slate-400">({{ $props['status_sebelum'] }} → {{ $props['status_sesudah'] }})</span>
                        @endif
                        @if(isset($props['nominal']))
                            · Rp {{ number_format($props['nominal'], 0, ',', '.') }}
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400 text-sm">Belum ada catatan audit.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $logs->links() }}</div>
</div>
@endsection
