@extends('sarpras.layouts.app')
@section('title', 'Jadwal Pemeliharaan')

@section('sarpras_body')
<div class="flex justify-between items-center mb-4">
    <h2 class="text-lg font-semibold text-gray-800">Jadwal Pemeliharaan Rutin</h2>
    <a href="{{ route('sarpras.jadwal.create') }}" class="bg-slate-900 text-white px-4 py-2 rounded text-sm">+ Jadwal</a>
</div>
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="w-full text-sm">
        <thead><tr class="text-left text-gray-500 border-b">
            <th class="py-2 px-4">Nama</th><th>Aset</th><th>Interval</th><th>Berikutnya</th><th>Aktif</th><th></th>
        </tr></thead>
        <tbody>
        @forelse ($jadwal as $j)
            <tr class="border-b @if($j->jatuh_tempo) bg-red-50 @endif">
                <td class="py-2 px-4 font-medium">{{ $j->nama }}</td>
                <td>{{ $j->aset?->nama ?? '-' }}</td>
                <td>{{ $j->interval_hari }} hari</td>
                <td>{{ optional($j->tgl_berikutnya)->format('d/m/Y') }} @if($j->jatuh_tempo)<span class="text-red-600 text-xs">(jatuh tempo)</span>@endif</td>
                <td>{!! $j->aktif ? '✅' : '—' !!}</td>
                <td class="px-4 flex gap-3">
                    <a href="{{ route('sarpras.jadwal.edit', $j) }}" class="text-blue-600 hover:underline">Edit</a>
                    <form method="POST" action="{{ route('sarpras.jadwal.destroy', $j) }}" onsubmit="return confirm('Hapus jadwal?')">
                        @csrf @method('DELETE')<button class="text-red-600 hover:underline">Hapus</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-4 px-4 text-gray-400">Belum ada jadwal.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $jadwal->links() }}</div>
@endsection
