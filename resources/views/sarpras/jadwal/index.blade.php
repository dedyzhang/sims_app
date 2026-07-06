@extends('sarpras.layouts.app')
@section('title', 'Jadwal Pemeliharaan')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Jadwal Pemeliharaan Rutin</h2>
    <a href="{{ route('sarpras.jadwal.create') }}" 
       class="inline-flex items-center gap-2 bg-slate-900 hover:bg-slate-800 text-white px-5 py-2.5 rounded-full text-xs sm:text-sm font-bold shadow-sm hover:shadow transition-all duration-200">
        <i data-lucide="plus" class="w-4 h-4"></i> Jadwal
    </a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Nama</th><th>Aset</th><th>Interval</th><th>Berikutnya</th><th>Aktif</th><th></th>
        </tr></thead>
        <tbody>
        @foreach($jadwal as $j)
            <tr class="border-b @if($j->jatuh_tempo) bg-red-50 @endif">
                <td class="py-2 px-4 font-medium">{{ $j->nama }}</td>
                <td>{{ $j->aset?->nama ?? '-' }}</td>
                <td>{{ $j->interval_hari }} hari</td>
                <td>{{ optional($j->tgl_berikutnya)->format('d/m/Y') }} @if($j->jatuh_tempo)<span class="text-red-600 text-xs">(jatuh tempo)</span>@endif</td>
                <td>{!! $j->aktif ? '✅' : '—' !!}</td>
                <td class="px-4 flex gap-3">
                    <a href="{{ route('sarpras.jadwal.edit', $j) }}" class="text-blue-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('sarpras.jadwal.destroy', $j) }}" onsubmit="return confirmDelete(this)">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@endsection
