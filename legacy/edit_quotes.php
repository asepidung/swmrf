<?php
session_start();
require 'verifications/auth.php';
require 'konak/conn.php';

// Ambil ID
$idquote = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($idquote <= 0) {
    die("Invalid quote ID.");
}

// Ambil data quote
$stmt = $conn->prepare("SELECT isiquote FROM quotes WHERE idquote = ?");
$stmt->bind_param("i", $idquote);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Quote not found.");
}

$row = $result->fetch_assoc();
$isiquote = $row['isiquote'];

$stmt->close();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Edit Quote</title>

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
            user-select: none;
        }

        .emoji-picker:hover {
            transform: scale(1.3);
        }

        .emoji-wrapper {
            padding: 6px 0;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">

        <div class="content-wrapper pt-3">
            <section class="content">
                <div class="container-fluid">

                    <div class="card card-warning shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-edit"></i> Edit Quote
                            </h3>
                            <div class="card-tools">
                                <a href="index.php" class="btn btn-tool" title="Back">
                                    <i class="fas fa-arrow-left"></i>
                                </a>
                            </div>
                        </div>

                        <div class="card-body">

                            <?php if (isset($_SESSION['flash_error'])): ?>
                                <div class="alert alert-danger">
                                    <?= htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php unset($_SESSION['flash_error']); ?>
                            <?php endif; ?>

                            <form action="update_quotes.php" method="post">
                                <input type="hidden" name="idquote" value="<?= $idquote ?>">

                                <div class="form-group">
                                    <label for="isiquote">Quote</label>

                                    <!-- Emoji helper -->
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
                                        rows="4"
                                        maxlength="1000"
                                        class="form-control"
                                        required><?= htmlspecialchars($isiquote, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></textarea>

                                    <small class="form-text text-muted">
                                        Maksimum 1000 karakter.
                                        Gunakan <b>Windows + .</b> untuk emoji 🙂
                                    </small>
                                </div>

                                <div class="mt-3">
                                    <button type="submit" class="btn btn-warning rounded-pill shadow-sm px-3">
                                        <i class="fas fa-save mr-1"></i> Update
                                    </button>
                                    <a href="index.php" class="btn btn-secondary">
                                        Cancel
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