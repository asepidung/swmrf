<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if ($assetsPublished)
        <link rel="shortcut icon" href="{{ asset(mix('img/log-viewer-32.png', config('log-viewer.assets_path'))) }}">
    @else
        {!! \Opcodes\LogViewer\Facades\LogViewer::favicon() !!}
    @endif

    <title>Log Viewer{{ config('app.name') ? ' - ' . config('app.name') : '' }}</title>

    <!-- Style sheets-->
    @if ($assetsPublished)
        <link href="{{ asset(mix('app.css', config('log-viewer.assets_path'))) }}" rel="stylesheet" onerror="alert('app.css failed to load. Please refresh the page, re-publish Log Viewer assets, or fix routing for vendor assets.')">
    @else
        {!! \Opcodes\LogViewer\Facades\LogViewer::css() !!}
    @endif

    {{--
        Salinan view bawaan opcodesio/log-viewer, DITIMPA hanya untuk
        menyembunyikan tautan ke repositori pembuat paket.

        Kenapa lewat CSS dan bukan opsi config: tautan "Buy me a coffee" punya
        flag `show_support_link` (sudah dimatikan di config/log-viewer.php),
        tetapi ikon GitHub-nya HARDCODE di komponen Vue yang sudah
        terkompilasi ke app.js. Tidak ada opsi apa pun untuk itu, dan
        menyunting berkas di vendor/ akan hilang setiap `composer install`.

        Selektornya memakai atribut href, bukan kelas CSS, supaya tetap
        bekerja meski paketnya mengubah nama kelas. Ia menjangkau kedua
        tempat sekaligus: ikon di judul dan menu di dropdown pengaturan.

        PERHATIAN saat menaikkan versi paket: berkas ini salinan penuh, jadi
        perubahan view dari paket TIDAK ikut. Bandingkan dengan
        vendor/opcodesio/log-viewer/resources/views/index.blade.php setelah
        upgrade. Ada test yang menjaga aturan CSS-nya tetap ada.
    --}}
    <style>
        #log-viewer a[href*="github.com/opcodesio"] { display: none !important; }
    </style>
</head>

<body class="h-full px-3 lg:px-5 bg-gray-100 dark:bg-gray-900">
<div id="log-viewer" class="flex h-full max-h-screen max-w-full">
    <router-view></router-view>
</div>

<!-- Global LogViewer Object -->
<script>
    window.LogViewer = @json($logViewerScriptVariables);

    // Add additional headers for LogViewer requests like so:
    // window.LogViewer.headers['Authorization'] = 'Bearer xxxxxxx';
</script>
@if ($assetsPublished)
    <script src="{{ asset(mix('app.js', config('log-viewer.assets_path'))) }}" onerror="alert('app.js failed to load. Please refresh the page, re-publish Log Viewer assets, or fix routing for vendor assets.')"></script>
@else
    {!! \Opcodes\LogViewer\Facades\LogViewer::js() !!}
@endif
</body>
</html>
