@extends('layouts.app')
@section('title', 'Pengumuman')

@section('content')
<div class="max-w-4xl mx-auto space-y-5">
    <div class="flex items-start justify-between flex-wrap gap-3">
        <div>
            <nav class="text-xs text-slate-400 mb-1">Beranda <span class="mx-1">/</span> Pengumuman</nav>
            <h1 class="page-title">Pengumuman Sekolah</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Informasi &amp; pemberitahuan resmi dari sekolah.</p>
        </div>
        @if($bolehKelola)
        <a href="{{ route('pengumuman.create') }}" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg" style="background:var(--cp)">
            <i data-lucide="megaphone" class="w-4 h-4"></i> Buat Pengumuman
        </a>
        @endif
    </div>

    <div class="space-y-3">
        @forelse($pengumuman as $p)
        <div class="card p-5 hover:shadow-md transition">
            <div class="flex items-start gap-4">
                <div class="w-10 h-10 rounded-xl flex-shrink-0 grid place-items-center text-white" style="background:linear-gradient(135deg,var(--cp),var(--ca))">
                    <i data-lucide="megaphone" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <a href="{{ route('pengumuman.show', $p) }}" class="font-bold text-slate-800 dark:text-slate-100 hover:underline">{{ $p->judul }}</a>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-1 line-clamp-2">{{ \Illuminate\Support\Str::limit(strip_tags($p->isi), 160) }}</p>
                    <div class="flex items-center flex-wrap gap-x-3 gap-y-1 mt-2 text-[11px] text-slate-400">
                        <span class="inline-flex items-center gap-1"><i data-lucide="user" class="w-3 h-3"></i>{{ $p->pembuat?->displayName() ?? 'Sistem' }}</span>
                        <span class="inline-flex items-center gap-1"><i data-lucide="clock" class="w-3 h-3"></i>{{ $p->created_at->locale('id')->diffForHumans() }}</span>
                        @if($p->untukSemua())
                            <span class="badge bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300">Semua peran</span>
                        @else
                            @foreach($p->target_roles as $r)
                                <span class="badge bg-primary/10 text-primary" style="color:var(--cp)">{{ \App\Models\Pengumuman::TARGET_ROLES[$r] ?? $r }}</span>
                            @endforeach
                        @endif
                    </div>
                </div>
                @if($bolehKelola)
                <div class="flex items-center gap-1 flex-shrink-0">
                    <a href="{{ route('pengumuman.edit', $p) }}" title="Sunting" class="p-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-500"><i data-lucide="pencil" class="w-4 h-4"></i></a>
                    <form method="POST" action="{{ route('pengumuman.destroy', $p) }}" onsubmit="return confirmDelete(this)">
                        @csrf @method('DELETE')
                        <button type="submit" title="Hapus" class="p-2 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-900/30 text-rose-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
                    </form>
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="card p-12 text-center">
            <i data-lucide="megaphone-off" class="w-10 h-10 mx-auto text-slate-300"></i>
            <p class="text-slate-400 text-sm mt-3 italic">Belum ada pengumuman.</p>
        </div>
        @endforelse
    </div>

    <div>{{ $pengumuman->links() }}</div>
</div>
@endsection
