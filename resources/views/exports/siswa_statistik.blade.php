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
                    <td rowspan="{{ count($ages) }}" style="background-color: #b4c6e7;">Laki-laki</td>
                @endif
                <td style="background-color: #b4c6e7;">{{ $age }}</td>
                <td style="background-color: #b4c6e7;">{{ $statsUsia['L'][$age] ?? 0 }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($ages) }}" style="background-color: #b4c6e7;">{{ $totalGender['L'] }}</td>
                    <td rowspan="{{ count($ages) * 2 }}">{{ $totalGender['L'] + $totalGender['P'] }}</td>
                @endif
            </tr>
        @endforeach
        
        <!-- Perempuan -->
        @foreach($ages as $index => $age)
            <tr>
                @if($index === 0)
                    <td rowspan="{{ count($ages) }}" style="background-color: #e6b8b7;">Perempuan</td>
                @endif
                <td style="background-color: #e6b8b7;">{{ $age }}</td>
                <td style="background-color: #e6b8b7;">{{ $statsUsia['P'][$age] ?? 0 }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($ages) }}" style="background-color: #e6b8b7;">{{ $totalGender['P'] }}</td>
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
                    <td rowspan="{{ count($religions) }}" style="background-color: #b4c6e7;">Laki-laki</td>
                @endif
                <td style="background-color: #b4c6e7;">{{ ucfirst($agama) }}</td>
                <td style="background-color: #b4c6e7;">{{ $statsAgama['L'][$agama] ?? 0 }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($religions) }}" style="background-color: #b4c6e7;">{{ $totalGender['L'] }}</td>
                    <td rowspan="{{ count($religions) * 2 }}">{{ $totalGender['L'] + $totalGender['P'] }}</td>
                @endif
            </tr>
        @endforeach
        
        <!-- Perempuan -->
        @foreach($religions as $index => $agama)
            <tr>
                @if($index === 0)
                    <td rowspan="{{ count($religions) }}" style="background-color: #e6b8b7;">Perempuan</td>
                @endif
                <td style="background-color: #e6b8b7;">{{ ucfirst($agama) }}</td>
                <td style="background-color: #e6b8b7;">{{ $statsAgama['P'][$agama] ?? 0 }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($religions) }}" style="background-color: #e6b8b7;">{{ $totalGender['P'] }}</td>
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
            <td style="background-color: #b4c6e7;">Laki-laki</td>
            <td>{{ $totalGender['L'] }}</td>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td style="background-color: #e6b8b7;">Perempuan</td>
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
                <td style="background-color: #b4c6e7;">Laki-laki</td>
                <td style="background-color: #b4c6e7;">{{ $statsKelas['L'][$kelas] ?? 0 }}</td>
                <td rowspan="2" style="vertical-align: middle;">{{ ($statsKelas['L'][$kelas] ?? 0) + ($statsKelas['P'][$kelas] ?? 0) }}</td>
                @if($index === 0)
                    <td rowspan="{{ count($classes) * 2 }}" style="vertical-align: middle;">{{ $totalGender['L'] + $totalGender['P'] }}</td>
                @endif
            </tr>
            <tr>
                <td style="background-color: #e6b8b7;">Perempuan</td>
                <td style="background-color: #e6b8b7;">{{ $statsKelas['P'][$kelas] ?? 0 }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

