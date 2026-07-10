@extends('layouts.app')
@section('title', 'Detail Masukan')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-3">
        <div>
            <h1 class="page-title">Detail Masukan</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Status dan respon tindak lanjut dari sekolah.</p>
        </div>
        <a href="{{ route('feedback.index') }}" class="btn-ghost inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2.5 text-sm font-semibold">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            Kembali
        </a>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
        <div class="card p-5 md:p-6 space-y-5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="badge bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ $feedback->categoryLabel() }}</span>
                <span class="badge bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200">{{ $feedback->statusLabel() }}</span>
                @if($feedback->rating)
                <span class="badge bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-200">Rating {{ $feedback->rating }}/5</span>
                @endif
            </div>

            <div>
                <h2 class="text-xl font-extrabold text-slate-800 dark:text-slate-100">{{ $feedback->subject }}</h2>
                <p class="text-xs text-slate-400 mt-1">Dikirim {{ $feedback->created_at?->locale('id')->diffForHumans() }}</p>
            </div>

            <div class="prose prose-sm max-w-none dark:prose-invert">
                <p class="whitespace-pre-line text-slate-700 dark:text-slate-200">{{ $feedback->message }}</p>
            </div>

            @if($feedback->context_url)
            <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 p-3">
                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 mb-1">Halaman asal</p>
                <p class="text-xs text-slate-600 dark:text-slate-300 break-all">{{ $feedback->context_url }}</p>
            </div>
            @endif
        </div>

        <div class="space-y-6">
            <div class="card p-5 space-y-3">
                <h2 class="text-sm font-extrabold text-slate-800 dark:text-slate-100">Respon Sekolah</h2>
                @if($feedback->admin_response)
                    <p class="text-sm text-slate-700 dark:text-slate-200 whitespace-pre-line">{{ $feedback->admin_response }}</p>
                    <p class="text-xs text-slate-400">
                        Oleh {{ $feedback->responder?->displayName() ?? 'Admin' }}
                        @if($feedback->responded_at) - {{ $feedback->responded_at->locale('id')->diffForHumans() }} @endif
                    </p>
                @else
                    <p class="text-sm text-slate-500 dark:text-slate-400">Belum ada respon. Status akan diperbarui setelah admin menindaklanjuti.</p>
                @endif
            </div>

            @if($canManage)
            <form method="POST" action="{{ route('feedback.respond', $feedback) }}" class="card p-5 space-y-4">
                @csrf
                <div>
                    <label for="status" class="form-label">Status</label>
                    <select id="status" name="status" class="form-select" required>
                        @foreach($statuses as $key => $label)
                        <option value="{{ $key }}" @selected(old('status', $feedback->status) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="admin_response" class="form-label">Respon untuk pengguna</label>
                    <textarea id="admin_response" name="admin_response" rows="5" class="form-input" placeholder="Tulis tindak lanjut, solusi sementara, atau keputusan fitur.">{{ old('admin_response', $feedback->admin_response) }}</textarea>
                </div>
                <button type="submit" class="btn-primary inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold">
                    <i data-lucide="save" class="w-4 h-4"></i>
                    Simpan Respon
                </button>
            </form>
            @endif
        </div>
    </div>
</div>
@endsection
