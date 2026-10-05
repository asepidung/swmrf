{{-- Blok legal label produk: logo Halal + nomor sertifikat/NKV/registrasi. Nomornya di config/label.php. --}}
<div style="width: 100%; max-width: 168px; margin: 0 auto; text-align: left;">
    <img src="{{ asset('img/halalrebah.png') }}" alt="HALAL" style="display: block; width: 100%; height: auto; filter: contrast(3);">
    <table style="width: 100%; height: auto; margin: 3px 0 0 0; font-size: 8.5px; line-height: 1.15; text-align: left;">
        <tr><td style="padding: 0; white-space: nowrap;">Halal Gudang</td><td style="padding: 0 0 0 3px;">: {{ config('label.halal_gudang') }}</td></tr>
        <tr><td style="padding: 0; white-space: nowrap;">NKV RPH</td><td style="padding: 0 0 0 3px;">: {{ config('label.nkv_rph') }}</td></tr>
        <tr><td style="padding: 0; white-space: nowrap;">NKV Gudang</td><td style="padding: 0 0 0 3px;">: {{ config('label.nkv_gudang') }}</td></tr>
        <tr><td style="padding: 0; white-space: nowrap;">Reg Produk</td><td style="padding: 0 0 0 3px;">: {{ config('label.registrasi_produk') }}</td></tr>
    </table>
</div>
