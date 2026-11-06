<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Analisa Opname Material {{ $a['record']->document_number }}</title>
    <style>
        @page { size: A4; margin: 1.2cm; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #222; line-height: 1.45; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; border-bottom: 1px solid #999; padding-bottom: 2px; }
        .sub { color: #555; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0 10px; }
        th, td { border: 1px solid #bbb; padding: 3px 5px; }
        th { background: #f0f0f0; text-align: left; }
        .r { text-align: right; } .c { text-align: center; }
        .plus { color: #1a7f37; } .minus { color: #c0392b; }
        .box { background: #f7f7f7; border: 1px solid #ddd; padding: 6px 10px; margin: 6px 0; }
        .note { font-size: 10px; color: #555; }
        .pb { page-break-before: always; }
        tr { page-break-inside: avoid; }
        @media screen { body { max-width: 900px; margin: 16px auto; } }
    </style>
</head>
<body>
@php
    $rec = $a['record'];
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $sg = fn ($v) => ($v > 0.0001 ? '+' : '').$n($v);
    $selesai = $rec->status === 'COMPLETED';
    $adaSelisih = $a['over']->count() > 0 || $a['short']->count() > 0;
    $belum = $a['not_counted']->count();
@endphp

<h1>Analisa Hasil Opname Material {{ $rec->document_number }}</h1>
<div class="sub">
    PT. Santi Wijaya Meat · Periode {{ $rec->period }} · Tanggal opname {{ $rec->date?->translatedFormat('j F Y') }} ·
    Status: {{ $selesai ? 'SELESAI' : $rec->status }}
</div>

<h2>Kesimpulan singkat</h2>
<div class="box">
    <p>Dari <strong>{{ $a['total'] }}</strong> barang, <strong>{{ $a['counted'] }}</strong> dihitung
        @if($belum > 0) dan <strong>{{ $belum }}</strong> belum dihitung (tidak ikut dinilai) @endif.</p>
    @if($a['counted'] > 0)
        <p><strong>{{ $a['matches']->count() }}</strong> barang sesuai ({{ number_format($a['accuracy_pct'], 0, ',', '.') }}% dari yang dihitung),
            <strong class="plus">{{ $a['over']->count() }}</strong> lebih, <strong class="minus">{{ $a['short']->count() }}</strong> kurang.</p>
    @endif
    @if($adaSelisih)
        <p>Selisih terbesar:
            @if($a['short']->isNotEmpty()) kurang pada <strong>{{ $a['short']->first()['name'] }}</strong> ({{ $sg($a['short']->first()['diff']) }} {{ $a['short']->first()['unit'] }}) @endif
            @if($a['short']->isNotEmpty() && $a['over']->isNotEmpty()) dan @endif
            @if($a['over']->isNotEmpty()) lebih pada <strong>{{ $a['over']->first()['name'] }}</strong> ({{ $sg($a['over']->first()['diff']) }} {{ $a['over']->first()['unit'] }}) @endif.</p>
    @elseif($a['counted'] > 0)
        <p>Tidak ada selisih: semua barang yang dihitung sesuai dengan stok sistem.</p>
    @endif
    @if($a['recounts']->count() > 0)
        <p>{{ $a['recounts']->count() }} barang pernah diminta dihitung ulang oleh peninjau.</p>
    @endif
</div>
<p class="note">Material tidak punya harga pokok di sistem, jadi laporan ini dalam jumlah, bukan rupiah. Jumlah tidak dijumlahkan lintas satuan.</p>

<h2>1. Angka utama</h2>
<table>
    <tr><th>Keterangan</th><th class="r">Barang</th></tr>
    <tr><td>Seluruh barang di opname</td><td class="r">{{ $a['total'] }}</td></tr>
    <tr><td>Sudah dihitung</td><td class="r">{{ $a['counted'] }}</td></tr>
    <tr><td>Belum dihitung</td><td class="r">{{ $belum }}</td></tr>
    <tr><td>Sesuai</td><td class="r">{{ $a['matches']->count() }}</td></tr>
    <tr><td>Lebih (fisik di atas sistem)</td><td class="r plus">{{ $a['over']->count() }}</td></tr>
    <tr><td>Kurang (fisik di bawah sistem)</td><td class="r minus">{{ $a['short']->count() }}</td></tr>
</table>

@if($a['per_unit']->isNotEmpty() && $adaSelisih)
<p>Total selisih per satuan:</p>
<table>
    <tr><th>Satuan</th><th class="r">Total lebih</th><th class="r">Total kurang</th></tr>
    @foreach($a['per_unit'] as $u)
        <tr><td>{{ $u['unit'] }}</td><td class="r plus">{{ $sg($u['over']) }}</td><td class="r minus">{{ $n($u['short']) }}</td></tr>
    @endforeach
</table>
@endif

<h2>2. Per kategori</h2>
<table>
    <tr><th>Kategori</th><th class="r">Barang</th><th class="r">Dihitung</th><th class="r">Sesuai</th><th class="r">Lebih</th><th class="r">Kurang</th></tr>
    @foreach($a['per_category'] as $c)
        <tr>
            <td>{{ $c['category'] }}</td><td class="r">{{ $c['total'] }}</td><td class="r">{{ $c['counted'] }}</td>
            <td class="r">{{ $c['matches'] }}</td><td class="r plus">{{ $c['over'] }}</td><td class="r minus">{{ $c['short'] }}</td>
        </tr>
    @endforeach
</table>

@if($adaSelisih)
<h2>3. Barang yang selisih</h2>
<table>
    <tr><th>No</th><th>Barang</th><th>Satuan</th><th class="r">Sistem</th><th class="r">Fisik</th><th class="r">Selisih</th><th class="r">%</th></tr>
    @foreach($a['short']->concat($a['over'])->sortByDesc(fn ($b) => abs($b['pct'] ?? abs($b['diff']))) as $i => $b)
        <tr>
            <td class="c">{{ $loop->iteration }}</td>
            <td>{{ $b['name'] }}</td><td>{{ $b['unit'] }}</td>
            <td class="r">{{ $n($b['system']) }}</td><td class="r">{{ $n($b['physical']) }}</td>
            <td class="r {{ $b['diff'] > 0 ? 'plus' : 'minus' }}">{{ $sg($b['diff']) }}</td>
            <td class="r">{{ $b['pct'] === null ? '-' : $sg(round($b['pct'], 1)).'%' }}</td>
        </tr>
    @endforeach
</table>
@endif

@if($a['recounts']->isNotEmpty())
<h2>4. Riwayat hitung ulang</h2>
<table>
    <tr><th>Barang</th><th class="r">Hitungan sebelumnya</th><th class="r">Hitungan akhir</th><th>Alasan peninjau</th></tr>
    @foreach($a['recounts'] as $r)
        <tr>
            <td>{{ $r['name'] }}</td>
            <td class="r">{{ $r['previous'] === null ? '-' : $n($r['previous']) }} {{ $r['unit'] }}</td>
            <td class="r">{{ $r['now'] === null ? 'belum' : $n($r['now']).' '.$r['unit'] }}</td>
            <td>{{ $r['note'] ?: '-' }}</td>
        </tr>
    @endforeach
</table>
@endif

<h2>{{ $a['recounts']->isNotEmpty() ? '5' : '4' }}. Catatan pembukuan</h2>
@if($selesai)
    <p>Selisih sudah diterapkan ke stok saat opname diselesaikan: barang yang lebih menambah stok, barang yang kurang mengurangi stok.
        Barang yang belum dihitung tidak diubah.</p>
@else
    <p>Opname belum diselesaikan, sehingga belum ada penyesuaian stok yang dibukukan.</p>
@endif

@if($adaSelisih || $belum > 0)
<h2>{{ $a['recounts']->isNotEmpty() ? '6' : '5' }}. Usulan tindak lanjut</h2>
<ul>
    @if($a['short']->isNotEmpty())
        <li>Telusuri {{ $a['short']->count() }} barang yang kurang, mulai dari yang selisih persennya terbesar (pemakaian tidak tercatat, salah letak, atau salah hitung).</li>
    @endif
    @if($a['over']->isNotEmpty())
        <li>Cek {{ $a['over']->count() }} barang yang lebih (penerimaan yang belum diinput, atau satuan salah).</li>
    @endif
    @if($belum > 0)
        <li>{{ $belum }} barang belum dihitung dan tidak ikut dinilai; hitung pada opname berikutnya atau lengkapi.</li>
    @endif
</ul>
@endif

<p class="note">Laporan ini disusun otomatis oleh sistem dari dokumen {{ $rec->document_number }} dan data aplikasi (hanya dibaca), dicetak {{ now()->translatedFormat('j F Y, H:i') }}.</p>

<div class="pb"></div>
<h1>Lampiran Detail {{ $rec->document_number }}</h1>
<div class="sub">Seluruh barang, urut abjad.</div>
<table>
    <tr><th>No</th><th>Kode</th><th>Barang</th><th>Kategori</th><th>Satuan</th><th class="r">Sistem</th><th class="r">Fisik</th><th class="r">Selisih</th><th>Catatan</th></tr>
    @foreach($a['rows'] as $b)
        <tr>
            <td class="c">{{ $loop->iteration }}</td>
            <td>{{ $b['code'] }}</td><td>{{ $b['name'] }}</td><td>{{ $b['category'] }}</td><td>{{ $b['unit'] }}</td>
            <td class="r">{{ $n($b['system']) }}</td>
            <td class="r">{{ $b['physical'] === null ? 'belum' : $n($b['physical']) }}</td>
            <td class="r {{ ($b['diff'] ?? 0) > 0.0001 ? 'plus' : (($b['diff'] ?? 0) < -0.0001 ? 'minus' : '') }}">{{ $b['diff'] === null ? '-' : $sg($b['diff']) }}</td>
            <td>{{ $b['item']->note }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
