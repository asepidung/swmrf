<?php
// new_quotes.php
// Halaman form untuk menambah quote (1 field) + emoji support

session_start();
require 'verifications/auth.php';

// Ambil pesan flash (jika ada)
$success = $_SESSION['flash_success'] ?? null;
$error   = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tambah Quote - SWM</title>

    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="dist/css/adminlte.min.css">

    <style>
        body {
            font-size: 13px;
        }

        .emoji-picker {
            cursor: pointer;
            font-size: 18px;
            margin-right: 6px;
            transition: transform 0.1s ease;
        }

        .emoji-picker:hover {
            transform: scale(1.3);
        }

        .emoji-wrapper {
            padding: 6px 0;
            user-select: none;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <div class="content-wrapper">
            <section class="content pt-3">
                <div class="container-fluid">

                    <div class="card card-primary shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-plus"></i> Add Your Quote
                            </h3>
                            <div class="card-tools">
                                <a href="index.php" class="btn btn-tool" title="Back to dashboard">
                                    <i class="fas fa-arrow-left"></i>
                                </a>
                            </div>
                        </div>

                        <div class="card-body">

                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <form action="store_quotes.php" method="post">
                                <input type="hidden" name="action" value="store_quote">

                                <div class="form-group">
                                    <label for="isiquote">Quote</label>

                                    <!-- Emoji picker -->
                                    <div class="emoji-wrapper mb-2">
                                        <span class="emoji-picker" onclick="addEmoji('😊')">😊</span>
                                        <span class="emoji-picker" onclick="addEmoji('😌')">😌</span>
                                        <span class="emoji-picker" onclick="addEmoji('🔥')">🔥</span>
                                        <span class="emoji-picker" onclick="addEmoji('💭')">💭</span>
                                        <span class="emoji-picker" onclick="addEmoji('💪')">💪</span>
                                        <span class="emoji-picker" onclick="addEmoji('🙏')">🙏</span>
                                        <span class="emoji-picker" onclick="addEmoji('😏')">😏</span>
                                        <span class="emoji-picker" onclick="addEmoji('❤️')">❤️</span>
                                    </div>

                                    <textarea
                                        id="isiquote"
                                        name="isiquote"
                                        class="form-control"
                                        rows="4"
                                        maxlength="1000"
                                        required
                                        placeholder="Tulis quotes Anda di sini..."></textarea>

                                    <small class="form-text text-muted">
                                        Maksimum 1000 karakter.
                                    </small>
                                </div>

                                <div class="form-group mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Simpan
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">
                                        Batal
                                    </a>
                                </div>

                            </form>
                        </div>
                    </div>

                </div>
            </section>
        </div>

    </div>

    <script src="plugins/jquery/jquery.min.js"></script>
    <script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="dist/js/adminlte.min.js"></script>

    <script>
        function addEmoji(emoji) {
            const textarea = document.getElementById('isiquote');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;

            textarea.value = text.substring(0, start) + emoji + text.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + emoji.length;
        }
    </script>

</body>

</html>