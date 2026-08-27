<style>
    .table-stat {
        border-collapse: collapse;
        text-align: center;
        vertical-align: middle;
    }
    .table-stat th, .table-stat td {
        border: 1px solid #000000;
        padding: 5px;
    }
    .bg-l {
        background-color: #b4c6e7;
    }
    .bg-p {
        background-color: #e6b8b7;
    }
    .empty-row td {
        border: none;
        height: 20px;
    }
</style>

<table class="table-stat">
    <!-- Tabel Usia -->
    <thead>
        <tr>
            <th colspan="5" style="font-weight: bold; text-align: left; font-size: 14px;">Berdasarkan Usia</th>
        </tr>
    </thead>
    <tbody>
        <!-- Laki-laki -->
        @foreach($ages as $index => $age)
            <tr>
                @if($index === 0)
                    <td rowspan="{{ count($ages) }}" class="bg-l">Laki-laki</td>
                @endif
                <td class="bg-l">{{ $age }}</td>
                <td class="bg-l">{{ $statsUsia['L'][$age] ?? 0 }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($ages) }}" class="bg-l">{{ $totalGender['L'] }}</td>
                    <td rowspan="{{ count($ages) * 2 }}">{{ $totalGender['L'] + $totalGender['P'] }}</td>
                @endif
            </tr>
        @endforeach
        
        <!-- Perempuan -->
        @foreach($ages as $index => $age)
            <tr>
                @if($index === 0)
                    <td rowspan="{{ count($ages) }}" class="bg-p">Perempuan</td>
                @endif
                <td class="bg-p">{{ $age }}</td>
                <td class="bg-p">{{ $statsUsia['P'][$age] ?? 0 }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($ages) }}" class="bg-p">{{ $totalGender['P'] }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>

    <tr class="empty-row"><td colspan="5"></td></tr>
    <tr class="empty-row"><td colspan="5"></td></tr>

    <!-- Tabel Agama -->
    <thead>
        <tr>
            <th colspan="5" style="font-weight: bold; text-align: left; font-size: 14px;">Berdasarkan Agama</th>
        </tr>
    </thead>
    <tbody>
        <!-- Laki-laki -->
        @foreach($religions as $index => $agama)
            <tr>
                @if($index === 0)
                    <td rowspan="{{ count($religions) }}" class="bg-l">Laki-laki</td>
                @endif
                <td class="bg-l">{{ ucfirst($agama) }}</td>
                <td class="bg-l">{{ $statsAgama['L'][$agama] ?? 0 }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($religions) }}" class="bg-l">{{ $totalGender['L'] }}</td>
                    <td rowspan="{{ count($religions) * 2 }}">{{ $totalGender['L'] + $totalGender['P'] }}</td>
                @endif
            </tr>
        @endforeach
        
        <!-- Perempuan -->
        @foreach($religions as $index => $agama)
            <tr>
                @if($index === 0)
                    <td rowspan="{{ count($religions) }}" class="bg-p">Perempuan</td>
                @endif
                <td class="bg-p">{{ ucfirst($agama) }}</td>
                <td class="bg-p">{{ $statsAgama['P'][$agama] ?? 0 }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($religions) }}" class="bg-p">{{ $totalGender['P'] }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>

    <tr class="empty-row"><td colspan="5"></td></tr>
    <tr class="empty-row"><td colspan="5"></td></tr>

    <!-- Tabel Total Gender -->
    <thead>
        <tr>
            <th colspan="2" style="font-weight: bold; text-align: left; font-size: 14px;">Total Siswa</th>
            <th colspan="3"></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="bg-l">Laki-laki</td>
            <td>{{ $totalGender['L'] }}</td>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td class="bg-p">Perempuan</td>
            <td>{{ $totalGender['P'] }}</td>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td style="font-weight: bold;">Total</td>
            <td style="font-weight: bold;">{{ $totalGender['L'] + $totalGender['P'] }}</td>
            <td colspan="3"></td>
        </tr>
    </tbody>

    <tr class="empty-row"><td colspan="5"></td></tr>
    <tr class="empty-row"><td colspan="5"></td></tr>

    <!-- Tabel Kelas -->
    <thead>
        <tr>
            <th colspan="5" style="font-weight: bold; text-align: left; font-size: 14px;">Berdasarkan Kelas</th>
        </tr>
    </thead>
    <tbody>
        @foreach($classes as $index => $kelas)
            <tr>
                <td rowspan="2" style="vertical-align: middle;">{{ $kelas }}</td>
                <td class="bg-l">Laki-laki</td>
                <td class="bg-l">{{ $statsKelas['L'][$kelas] ?? 0 }}</td>
                <td rowspan="2" style="vertical-align: middle;">{{ ($statsKelas['L'][$kelas] ?? 0) + ($statsKelas['P'][$kelas] ?? 0) }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($classes) * 2 }}" style="vertical-align: middle;">{{ $totalGender['L'] + $totalGender['P'] }}</td>
                @endif
            </tr>
            <tr>
                <td class="bg-p">Perempuan</td>
                <td class="bg-p">{{ $statsKelas['P'][$kelas] ?? 0 }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
