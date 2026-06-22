@extends('layouts.app')
@section('title', 'Pengaturan Akses Forum')

@section('content')
<div class="space-y-5">
    <div class="flex items-center gap-3">
        <a href="{{ route('forum.index') }}" class="p-2 rounded-lg border border-slate-200 dark:border-slate-600 hover:bg-slate-50 dark:hover:bg-slate-700"><i data-lucide="arrow-left" class="w-4 h-4"></i></a>
        <div>
            <h1 class="page-title">Pengaturan Akses Forum</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">Centang izin per role. Perubahan langsung dipakai Policy (bukan hardcode). Role = kolom <code>access</code> pengguna.</p>
        </div>
    </div>

    @if(session('success'))
    <div class="rounded-xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-700 text-emerald-700 dark:text-emerald-300 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('forum.access.update') }}" class="card p-0 overflow-hidden">
        @csrf
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="text-left sticky left-0 bg-inherit">Role</th>
                        @foreach($permissions as $key => $label)
                        <th class="text-center align-bottom" title="{{ $key }}"><span class="text-xs">{{ $label }}</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role => $roleLabel)
                    <tr>
                        <td class="font-semibold text-slate-700 dark:text-slate-200 whitespace-nowrap">{{ $roleLabel }}<span class="block text-[11px] text-slate-400 font-normal">{{ $role }}</span></td>
                        @foreach($permissions as $perm => $permLabel)
                        <td class="text-center">
                            <input type="checkbox" name="perm[{{ $role }}][{{ $perm }}]" value="1"
                                   @checked($matrix[$role][$perm] ?? false)
                                   @if($role==='superadmin') disabled checked @endif
                                   class="w-4 h-4 rounded border-slate-300 text-primary">
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-slate-100 dark:border-slate-700 flex items-center justify-between gap-3 flex-wrap">
            <p class="text-xs text-slate-400">Super Admin selalu memiliki semua izin (tidak dapat dilepas).</p>
            <button class="px-6 py-2.5 rounded-xl text-sm font-bold text-white" style="background:var(--cp)"><i data-lucide="save" class="w-4 h-4 inline"></i> Simpan Matriks</button>
        </div>
    </form>
</div>
@endsection
