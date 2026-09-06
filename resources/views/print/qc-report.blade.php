{{--
    Laporan QC untuk dicetak.

    Permintaan Owner, 7 September 2026: "ia buatkan per laporan". Yang diminta
    auditor biasanya justru berkas semacam ini -- bukan layar.

    Berbahasa Indonesia mengikuti keputusan yang sudah berlaku untuk seluruh
    dokumen cetak: bahasa sebuah dokumen ditentukan oleh siapa yang
    membacanya, bukan oleh setelan operator yang menekan tombol cetak.
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $record->document_number }}</title>
    <style>
        @page { size: A4; margin: 16mm 14mm; }

        body {
            font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #111;
            margin: 0;
        }

        h1 { font-size: 15px; margin: 0 0 2px; letter-spacing: .3px; }

        .perusahaan { font-size: 10px; color: #555; margin-bottom: 12px; }

        table { width: 100%; border-collapse: collapse; }

        .kepala td { padding: 2px 0; vertical-align: top; }
        .kepala td:first-child { width: 130px; color: #555; }
        .kepala td:nth-child(2) { width: 10px; }

        .temuan { margin-top: 14px; }
        .temuan th, .temuan td {
            border: 1px solid #bbb;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
        }
        .temuan th { background: #eee; font-size: 10px; text-transform: uppercase; }
        .temuan td.nomor { width: 26px; text-align: center; }
        .temuan td.jumlah { width: 70px; text-align: right; }

        .catatan {
            margin-top: 12px;
            border: 1px solid #bbb;
            padding: 8px;
            white-space: pre-wrap;
            min-height: 40px;
        }

        .judul-kecil {
            font-size: 10px;
            text-transform: uppercase;
            color: #555;
            margin: 14px 0 4px;
        }

        .ttd { margin-top: 34px; width: 100%; }
        .ttd td { width: 50%; vertical-align: top; font-size: 10px; }
        .garis-ttd { margin-top: 46px; border-top: 1px solid #333; width: 170px; padding-top: 3px; }

        .kaki { margin-top: 18px; font-size: 9px; color: #777; }
    </style>
</head>
<body>
    <h1>LAPORAN QC</h1>
    <div class="perusahaan">PT SANTI WIJAYA MEAT</div>

    <table class="kepala">
        <tr>
            <td>Nomor</td><td>:</td>
            <td><strong>{{ $record->document_number }}</strong></td>
        </tr>
        <tr>
            <td>Dokumen didampingi</td><td>:</td>
            <td>{{ $record->jenisDokumen() }} &mdash; {{ $record->nomorDokumen() }}</td>
        </tr>
        <tr>
            <td>Waktu kejadian</td><td>:</td>
            <td>
                {{-- Waktu KEJADIAN, bukan waktu dokumen ini diketik. --}}
                {{ $record->occurred_at?->format('d M Y H:i') ?? '-' }}
            </td>
        </tr>
        <tr>
            <td>Pemeriksa</td><td>:</td>
            <td>{{ $record->creator?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td>Dilaporkan</td><td>:</td>
            <td>{{ $record->submitted_at?->format('d M Y H:i') ?? 'Belum dilaporkan' }}</td>
        </tr>
    </table>

    <div class="judul-kecil">Catatan umum</div>
    <div class="catatan">{{ $record->note ?: '-' }}</div>

    <div class="judul-kecil">Temuan</div>

    @if ($record->findings->isEmpty())
        {{--
            Tidak ada temuan itu HASIL PEMERIKSAAN, bukan bagian yang lupa
            diisi. Kalimatnya ditulis apa adanya supaya yang membaca berkas
            ini setahun lagi tidak menyangka halamannya terpotong.
        --}}
        <div class="catatan">Tidak ada temuan. Prosesnya berjalan tanpa masalah.</div>
    @else
        <table class="temuan">
            <thead>
                <tr>
                    <th class="nomor">#</th>
                    <th>Temuan</th>
                    <th class="jumlah">Jumlah</th>
                    <th>Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($record->findings as $nomor => $temuan)
                    <tr>
                        <td class="nomor">{{ $nomor + 1 }}</td>
                        <td>{{ $temuan->description }}</td>
                        <td class="jumlah">{{ $temuan->affected_count !== null ? number_format($temuan->affected_count) : '-' }}</td>
                        <td>{{ $temuan->action_taken ?: '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="ttd">
        <tr>
            <td>
                Pemeriksa QC
                <div class="garis-ttd">{{ $record->creator?->name ?? '' }}</div>
            </td>
            <td>
                Mengetahui
                <div class="garis-ttd">&nbsp;</div>
            </td>
        </tr>
    </table>

    <div class="kaki">
        Dicetak {{ now()->format('d M Y H:i') }}.
    </div>
</body>
</html>
