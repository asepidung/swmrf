<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Analisa Stock Opname {{ $a['record']->document_number }}</title>
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
    $kg = fn ($v) => number_format((float) $v, 2, ',', '.').' kg';
    $rp = fn ($v) => ($v === null ? '-' : 'Rp'.number_format((float) $v, 0, ',', '.'));
    $sg = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '-' : '')).number_format(abs((float) $v), 2, ',', '.');
    $sgRp = fn ($v) => ($v > 0 ? '+' : ($v < 0 ? '-' : '')).'Rp'.number_format(abs((float) $v), 0, ',', '.');
    $pct = $a['system_kg'] > 0 ? abs($a['net_kg']) / $a['system_kg'] * 100 : 0;
    $rec = $a['record'];
    $selesai = $rec->status === 'COMPLETED';
    $adaSelisih = $a['missing_count'] > 0 || $a['found_count'] > 0;
    $arah = $a['net_kg'] > 0 ? 'kelebihan' : ($a['net_kg'] < 0 ? 'kekurangan' : 'seimbang');
    $up = $a['unpaired_missing']->count();
    $uf = $a['unpaired_found']->count();
@endphp

<h1>Analisa Hasil Stock Opname {{ $rec->document_number }}</h1>
<div class="sub">
    PT. Santi Wijaya Meat · Periode {{ $rec->period }} · Tanggal opname {{ $rec->date?->translatedFormat('j F Y') }} ·
    Status: {{ $selesai ? 'SELESAI' : $rec->status }}
    @unless($selesai) <strong>(opname belum selesai, angka masih bisa berubah)</strong> @endunless
</div>

<h2>Kesimpulan singkat</h2>
<div class="box">
    <p>Stok sistem <strong>{{ $kg($a['system_kg']) }}</strong>, hasil hitung fisik <strong>{{ $kg($a['physical_kg']) }}</strong>.
        @if($adaSelisih)
            Selisih bersih <strong class="{{ $a['net_kg'] >= 0 ? 'plus' : 'minus' }}">{{ $sg($a['net_kg']) }} kg</strong>
            (sekitar {{ number_format($pct, 1, ',', '.') }}% dari stok sistem), {{ $arah }}.
        @else
            Tidak ada selisih: semua barang cocok.
        @endif
    </p>
    @if($adaSelisih)
        <p>Barang hilang bernilai sekitar <strong>{{ $rp($a['missing_value']) }}</strong>, barang temuan sekitar <strong>{{ $rp($a['found_value']) }}</strong>.
            Selisih bersih <strong>{{ $sgRp($a['net_value']) }}</strong>, dihitung dengan harga pokok (HPP) per kg barcode di sistem.</p>
    @endif
    @if($a['pair_count'] > 0)
        <p>{{ $a['pair_count'] }} dari {{ $a['missing_count'] }} dus hilang punya pasangan temuan dengan jenis barang dan berat yang sama persis
            ({{ $kg($a['pair_kg']) }}, {{ $rp($a['pair_value']) }}); {{ $a['pair_same_pack_date'] }} di antaranya tanggal pack-nya juga sama.
            Kemungkinan itu karton yang sama dengan barcode/label berganti. Ini dugaan dari data, bukan bukti; perlu dicek fisik oleh gudang.</p>
    @endif
    @if($up > 0 || $uf > 0)
        <p>Yang perlu ditelusuri:
            @if($up > 0) <strong>{{ $up }} dus hilang tanpa pasangan</strong> ({{ $kg($a['unpaired_missing_kg']) }}, {{ $rp($a['unpaired_missing_value']) }}) @endif
            @if($up > 0 && $uf > 0) dan @endif
            @if($uf > 0) <strong>{{ $uf }} dus temuan tanpa pasangan</strong> ({{ $kg($a['unpaired_found_kg']) }}, {{ $rp($a['unpaired_found_value']) }}) @endif.
        </p>
    @elseif($adaSelisih)
        <p>Seluruh barang hilang dan temuan sudah berpasangan, tidak ada sisa yang perlu ditelusuri selain pencocokan fisik pasangan.</p>
    @endif
</div>

<h2>1. Angka utama</h2>
<table>
    <tr><th></th><th class="r">Dus</th><th class="r">Berat</th><th class="r">Nilai (HPP)</th></tr>
    <tr><td>Stok menurut sistem</td><td class="r">{{ $a['matched_count'] + $a['missing_count'] }}</td><td class="r">{{ $kg($a['system_kg']) }}</td><td class="r">-</td></tr>
    <tr><td>Cocok (ditemukan di tempatnya)</td><td class="r">{{ $a['matched_count'] }}</td><td class="r">{{ $kg($a['matched_kg']) }}</td><td class="r">-</td></tr>
    <tr><td>Hilang (tercatat di sistem, tidak ditemukan)</td><td class="r">{{ $a['missing_count'] }}</td><td class="r">{{ $kg($a['missing_kg']) }}</td><td class="r">{{ $rp($a['missing_value']) }}</td></tr>
    <tr><td>Temuan (ada fisik, tidak tercatat di sistem)</td><td class="r">{{ $a['found_count'] }}</td><td class="r">{{ $kg($a['found_kg']) }}</td><td class="r">{{ $rp($a['found_value']) }}{{ $a['found_estimated_count'] ? ' *' : '' }}</td></tr>
    <tr><td>Hasil hitung fisik</td><td class="r">{{ $a['matched_count'] + $a['found_count'] }}</td><td class="r">{{ $kg($a['physical_kg']) }}</td><td class="r">-</td></tr>
    <tr><th>Selisih bersih (temuan - hilang)</th><th class="r">{{ $a['found_count'] - $a['missing_count'] }}</th><th class="r">{{ $sg($a['net_kg']) }} kg</th><th class="r">{{ $sgRp($a['net_value']) }}</th></tr>
</table>
@if($a['found_estimated_count'])
    <p class="note">* {{ $a['found_estimated_count'] }} dus temuan belum punya HPP di sistem; dinilai dengan rata-rata HPP barang hilang dari produk yang sama, sehingga nilai temuan adalah perkiraan.</p>
@endif
@if($a['missing_unvalued_count'] > 0)
    <p class="note">{{ $a['missing_unvalued_count'] }} dus hilang belum punya HPP di sistem, sehingga tidak ikut dalam nilai rupiah.</p>
@endif
@if($a['found_count'] > 0)
    <p class="note">{{ $a['found_manual_count'] }} dari {{ $a['found_count'] }} dus temuan diinput manual saat opname, {{ $a['found_count'] - $a['found_manual_count'] }} lewat scan.</p>
@endif

@if($adaSelisih)
<h2>2. Hilang dan temuan saling berpasangan</h2>
<p>Dus hilang dan dus temuan dengan produk dan berat sama persis dianggap berpasangan (kemungkinan karton yang sama dengan barcode berbeda).
    @if($a['pair_count'] > 0)
        Terdapat {{ $a['pair_count'] }} pasangan: {{ $kg($a['pair_kg']) }}, sekitar {{ $rp($a['pair_value']) }} di tiap sisi.
    @else
        Tidak ada pasangan yang ditemukan.
    @endif
</p>

<h2>3. Per produk (urut dari selisih nilai terbesar)</h2>
<table>
    <tr><th>Produk</th><th class="r">Cocok</th><th class="r">Hilang</th><th class="r">Temuan</th><th class="r">Selisih kg</th><th class="r">Selisih nilai</th></tr>
    @foreach($a['per_product'] as $row)
        @php $d = $row['found'] - $row['missing']; $dv = $row['found_value'] - $row['missing_value']; @endphp
        <tr>
            <td>{{ $row['product'] }}</td>
            <td class="r">{{ number_format($row['matched'], 2, ',', '.') }}</td>
            <td class="r minus">{{ number_format($row['missing'], 2, ',', '.') }}</td>
            <td class="r plus">{{ number_format($row['found'], 2, ',', '.') }}</td>
            <td class="r">{{ $sg($d) }}</td>
            <td class="r">{{ $sgRp($dv) }}</td>
        </tr>
    @endforeach
</table>

<h2>4. Catatan pembukuan</h2>
@if($selesai)
    <p>Penyesuaian stok sudah dibukukan saat opname diselesaikan:
        {{ $a['missing_count'] }} dus hilang dicatat sebagai kerugian stok, dan {{ $a['found_count'] }} dus temuan masuk ke stok sebagai barang tersedia.
        @if($a['pair_count'] > 0)
            Bila gudang memastikan sebagian pasangan adalah karton yang sama, kedua sisi perlu dibereskan: kerugian pada barcode lama dan stok masuk pada barcode baru.
        @endif
    </p>
@else
    <p>Opname belum diselesaikan, sehingga belum ada penyesuaian stok yang dibukukan. Saat diselesaikan, barang hilang dicatat sebagai kerugian stok dan barang temuan masuk ke stok.</p>
@endif

<h2>5. Usulan tindak lanjut</h2>
<ul>
    @if($a['pair_count'] > 0)
        <li>Gudang mencocokkan fisik {{ $a['pair_count'] }} pasangan hilang-temuan untuk memastikan itu karton yang sama dengan label berganti. Jika benar, bukukan sebagai koreksi, bukan kerugian.</li>
    @endif
    @if($up > 0 || $uf > 0)
        <li>Telusuri {{ $up }} dus hilang dan {{ $uf }} dus temuan yang tidak berpasangan, mulai dari yang bernilai terbesar (lihat Lampiran A dan B).</li>
    @endif
    @if($a['found_manual_count'] > 0)
        <li>Cari penyebab barang temuan diinput manual ({{ $a['found_manual_count'] }} dus) dan barcode berganti (cetak ulang label, salah scan) agar tidak berulang di opname berikutnya.</li>
    @endif
</ul>
@else
<p>Tidak ada barang hilang maupun temuan, sehingga tidak ada rincian maupun tindak lanjut.</p>
@endif

<p class="note">Laporan ini disusun otomatis oleh sistem dari dokumen {{ $rec->document_number }} dan data aplikasi (hanya dibaca), dicetak {{ now()->translatedFormat('j F Y, H:i') }}.
    Nilai uang memakai HPP per kg per barcode. Pasangan dicocokkan berdasarkan produk dan berat yang sama.</p>

@if($adaSelisih)
{{-- ================= LAMPIRAN DETAIL ================= --}}
<div class="pb"></div>
<h1>Lampiran Detail {{ $rec->document_number }}</h1>
<div class="sub">Daftar barcode per kelompok, untuk dicek gudang.</div>

@php
    $groups = [
        ['A. Hilang tanpa pasangan', $a['unpaired_missing'], $a['missing_cost'], []],
        ['B. Temuan tanpa pasangan', $a['unpaired_found'], $a['found_cost'], $a['found_estimated']],
    ];
@endphp
@foreach($groups as [$title, $rows, $costs, $est])
    <h2>{{ $title }} ({{ $rows->count() }} dus)</h2>
    @if($rows->isEmpty())
        <p class="note">Tidak ada.</p>
    @else
        <table>
            <tr><th>No</th><th>Barcode</th><th>Produk</th><th>Tgl pack</th><th class="r">Berat</th><th class="r">Nilai</th></tr>
            @foreach($rows as $i => $row)
                <tr>
                    <td class="c">{{ $i + 1 }}</td>
                    <td>{{ $row->barcode }}{{ $row->is_manual ? ' (manual)' : '' }}</td>
                    <td>{{ $row->product?->name ?? '-' }}</td>
                    <td>{{ $row->pack_date?->format('d-m-y') ?? '-' }}</td>
                    <td class="r">{{ number_format((float) $row->weight, 2, ',', '.') }}</td>
                    <td class="r">{{ $rp($costs[$row->id] ?? null) }}{{ ($est[$row->id] ?? false) ? ' *' : '' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endforeach

<h2>C. Pasangan hilang-temuan ({{ $a['pair_count'] }})</h2>
@if($a['pair_count'] === 0)
    <p class="note">Tidak ada.</p>
@else
<table>
    <tr><th>No</th><th>Produk</th><th class="r">Berat</th><th>Barcode hilang</th><th>Barcode temuan</th><th>Tgl pack sama</th><th class="r">Nilai</th></tr>
    @foreach($a['pairs'] as $i => $p)
        <tr>
            <td class="c">{{ $i + 1 }}</td>
            <td>{{ $p['missing']->product?->name ?? '-' }}</td>
            <td class="r">{{ number_format((float) $p['missing']->weight, 2, ',', '.') }}</td>
            <td>{{ $p['missing']->barcode }}</td>
            <td>{{ $p['found']->barcode }}{{ $p['found']->is_manual ? ' (manual)' : '' }}</td>
            <td class="c">{{ $p['same_pack_date'] ? 'Ya' : 'Tidak' }}</td>
            <td class="r">{{ $rp($p['value']) }}</td>
        </tr>
    @endforeach
</table>
@endif
@if($a['found_estimated_count'])
    <p class="note">* nilai perkiraan (barcode belum punya HPP).</p>
@endif
@endif

<script>window.onload = function () { if (location.search.indexOf('print=1') > -1) window.print(); };</script>
</body>
</html>
