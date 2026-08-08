@extends('sarpras.layouts.app')
@section('title', 'Stok Opname')
@section('sarpras_title', 'Stok Opname ATK')

@section('sarpras_body')
<div class="space-y-4">
    @can('sarpras.pengaturan.kelola')
    <form method="POST" action="{{ route('sarpras.stok-opname.store') }}" class="card p-4 grid md:grid-cols-3 gap-3">
        @csrf
        <input name="periode" placeholder="Periode (2026-S1)" required class="form-input text-sm">
        <input name="judul" placeholder="Judul sesi" required class="form-input text-sm">
        <button class="sarpras-google-btn-primary py-2 text-sm font-bold">Buat Sesi Opname</button>
    </form>
    @endcan
    <div class="card p-5">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-slate-400 border-b"><th class="pb-2">Periode</th><th class="pb-2">Judul</th><th class="pb-2">Status</th><th></th></tr></thead>
            <tbody>
            @foreach($opname as $o)
                <tr class="border-b"><td class="py-2">{{ $o->periode }}</td><td class="py-2">{{ $o->judul }}</td><td class="py-2">{{ $o->status }}</td>
                    <td class="py-2"><a href="{{ route('sarpras.stok-opname.show', $o) }}" class="text-primary text-xs font-bold">Kerjakan</a></td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
