@extends('sarpras.layouts.app')
@section('title', 'Stok Opname')
@section('sarpras_title', $opname->judul)

@section('sarpras_body')
<div class="card p-5 space-y-3">
    @foreach($opname->items as $item)
    <form method="POST" action="{{ route('sarpras.stok-opname.item', $opname) }}" class="flex flex-wrap gap-2 items-end border-b pb-3">
        @csrf
        <input type="hidden" name="item_id" value="{{ $item->id }}">
        <div class="flex-1 min-w-[200px]">
            <p class="font-bold text-sm">{{ $item->aset?->kode }} — {{ $item->aset?->nama }}</p>
            <p class="text-xs text-slate-500">Sistem: {{ $item->kondisi_sistem }}</p>
        </div>
        <select name="kondisi_fisik" required class="form-input text-sm">
            @foreach(['baik','rusak_ringan','rusak_berat','hilang'] as $k)
                <option value="{{ $k }}" @selected($item->kondisi_fisik===$k)>{{ str_replace('_',' ',$k) }}</option>
            @endforeach
        </select>
        <input name="catatan" placeholder="Catatan" value="{{ $item->catatan }}" class="form-input text-sm w-40">
        <button class="px-3 py-2 bg-slate-800 text-white rounded-lg text-xs font-bold">Simpan</button>
    </form>
    @endforeach
    @can('sarpras.pengaturan.kelola')
    @if($opname->status !== 'selesai')
    <form method="POST" action="{{ route('sarpras.stok-opname.selesai', $opname) }}">@csrf
        <button class="sarpras-google-btn-primary px-4 py-2 text-sm font-bold">Tandai Selesai</button>
    </form>
    @endif
    @endcan
</div>
@endsection
