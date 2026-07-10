@extends('layouts.app')
@section('title', 'Saran & Masukan')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="page-title">Saran & Masukan</h1>
                @if($canManage && ($newFeedbackCount ?? 0) > 0)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500 px-2.5 py-1 text-xs font-black text-white shadow-sm shadow-rose-500/30">
                    <i data-lucide="mail-warning" class="w-3.5 h-3.5"></i>
                    {{ $newFeedbackCount > 99 ? '99+' : $newFeedbackCount }} baru
                </span>
                @endif
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $canManage ? 'Pantau masukan pengguna, beri status, dan tulis respon tindak lanjut.' : 'Kirim kendala, ide fitur, atau masukan penggunaan SIMS.' }}
            </p>
        </div>
        <a href="{{ route('feedback.create', ['from' => request()->fullUrl()]) }}" class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold">
            <i data-lucide="message-square-plus" class="w-4 h-4"></i>
            Kirim Masukan
        </a>
    </div>

    @if($canManage)
    <form method="GET" action="{{ route('feedback.index') }}" class="card p-4 grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end">
        <div>
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua status</option>
                @foreach($statuses as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label" for="category">Kategori</label>
            <select id="category" name="category" class="form-select">
                <option value="">Semua kategori</option>
                @foreach($categories as $key => $label)
                <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold">
            <i data-lucide="filter" class="w-4 h-4"></i>
            Filter
        </button>
    </form>
    @endif

    <div class="card overflow-hidden">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Masukan</th>
                        <th>Kategori</th>
                        <th>Status</th>
                        @if($canManage)<th>Pengirim</th>@endif
                        <th>Rating</th>
                        <th>Dikirim</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($feedback as $item)
                    <tr>
                        <td class="min-w-[280px]">
                            <div class="font-bold text-slate-800 dark:text-slate-100 whitespace-normal">{{ $item->subject }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2 whitespace-normal">{{ $item->message }}</div>
                        </td>
                        <td>
                            <span class="badge bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $item->categoryLabel() }}</span>
                        </td>
                        <td>
                            @php
                                $statusClass = [
                                    'baru' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
                                    'dibaca' => 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
                                    'diproses' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200',
                                    'selesai' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-200',
                                    'ditolak' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-200',
                                ][$item->status] ?? 'bg-slate-100 text-slate-700';
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ $item->statusLabel() }}</span>
                        </td>
                        @if($canManage)
                        <td>{{ $item->user?->displayName() ?? 'User dihapus' }}</td>
                        @endif
                        <td>{{ $item->rating ? $item->rating.'/5' : '-' }}</td>
                        <td>{{ $item->created_at?->locale('id')->diffForHumans() }}</td>
                        <td>
                            <a href="{{ route('feedback.show', $item) }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary" style="color:var(--cp)">
                                Buka <i data-lucide="arrow-right" class="w-4 h-4"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ $canManage ? 7 : 6 }}" class="text-center py-10 text-slate-400">
                            Belum ada masukan.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-slate-100 dark:border-slate-700">
            {{ $feedback->links() }}
        </div>
    </div>
</div>
@endsection
