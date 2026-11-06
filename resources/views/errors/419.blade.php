<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Sesi berakhir</title>
        <style>
            html, body {
                height: 100%;
                margin: 0;
                background: #0b0e14;
                color: #e5e7eb;
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
            .wrap {
                min-height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }
            .card {
                max-width: 26rem;
                width: 100%;
                text-align: center;
                background: #151a23;
                border: 1px solid rgba(255,255,255,0.08);
                border-top: 6px solid #d97706;
                border-radius: 0.75rem;
                padding: 2rem 1.5rem;
                box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            }
            .code {
                font-size: 3rem;
                font-weight: 800;
                color: #f59e0b;
                line-height: 1;
                margin-bottom: 0.5rem;
            }
            .title {
                font-size: 1.25rem;
                font-weight: 700;
                margin-bottom: 0.75rem;
            }
            .message {
                color: #9ca3af;
                margin-bottom: 1.75rem;
                line-height: 1.5;
            }
            .btn {
                display: inline-block;
                background: #d97706;
                color: #111827;
                font-weight: 600;
                text-decoration: none;
                padding: 0.65rem 1.5rem;
                border-radius: 0.5rem;
            }
            .btn:hover {
                background: #b45309;
            }
        </style>
    </head>
    <body>
        <div class="wrap" role="main">
            <div class="card">
                <div class="code">419</div>
                <div class="title">Sesi Anda sudah berakhir</div>
                <p class="message">
                    Sudah cukup lama tidak ada aktivitas, jadi aplikasi mengakhiri sesi demi keamanan. Masuk lagi untuk melanjutkan.
                </p>
                <a class="btn" href="{{ route('filament.admin.auth.login') }}">
                    Masuk lagi
                </a>
            </div>
        </div>
    </body>
</html>
