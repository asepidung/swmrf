<?php
// seriallabelboning.php (versi lengkap, retry-safe)
// Format: YYMMDD + 9-digit zero-padded sequence (per-tahun)
// Aman terhadap race: gunakan GET_LOCK dengan retry, namun fallback tetap COUNT(*) agar kompatibel.

// Pastikan koneksi $conn tersedia (file ini diasumsikan di-include setelah require conn)
if (!isset($conn) || !$conn) {
    throw new RuntimeException("Database connection \$conn tidak tersedia untuk seriallabelboning.php");
}

// Prefix YYMMDD (untuk menggabungkan dengan serial)
$prefix = date("ymd"); // contoh: '240629'

// Nama lock per tahun agar scope lock terpisah per tahun
$lockName = 'serial_label_boning_' . date('Y'); // e.g. serial_label_boning_2024

// Konfigurasi retry/get-lock
$singleLockTimeout = 1; // detik menunggu GET_LOCK per percobaan
$maxAttempts = 5;       // jumlah percobaan GET_LOCK
$retrySleepUs = 200000; // microseconds (200ms) antara percobaan

$gotLock = false;
$attempt = 0;
$kode = 1; // default apabila semua gagal (akan diset ulang)

// Cobalah mendapatkan advisory lock beberapa kali
while ($attempt < $maxAttempts && !$gotLock) {
    $attempt++;
    $sqlGetLock = "SELECT GET_LOCK('" . $conn->real_escape_string($lockName) . "', " . (int)$singleLockTimeout . ") AS lk";
    $resLock = $conn->query($sqlGetLock);
    if ($resLock) {
        $rowLock = $resLock->fetch_assoc();
        $resLock->free();
        if (!empty($rowLock['lk']) && (int)$rowLock['lk'] === 1) {
            $gotLock = true;
            break;
        }
    } else {
        // catat error but continue retry
        error_log("seriallabelboning.php: GET_LOCK query failed (attempt {$attempt}): " . $conn->error);
    }
    // tunggu sebentar sebelum retry (kecuali sudah mencapai maxAttempts)
    if (!$gotLock) usleep($retrySleepUs);
}

if ($gotLock) {
    // Dengan lock, lakukan COUNT secara "atomic" (serial)
    $count = 0;
    $sqlCount = "SELECT COUNT(*) AS total FROM labelboning WHERE YEAR(creatime) = YEAR(CURRENT_DATE)";
    $res = $conn->query($sqlCount);
    if ($res) {
        $row = $res->fetch_array();
        $count = (int)($row['total'] ?? 0);
        $res->free();
    } else {
        // jika query count gagal meskipun lock didapat, catat dan fallback ke 0
        error_log("seriallabelboning.php: COUNT query failed under lock: " . $conn->error);
        $count = 0;
    }

    $kode = $count + 1;

    // release lock (jangan lupa)
    $relSql = "SELECT RELEASE_LOCK('" . $conn->real_escape_string($lockName) . "') AS rl";
    $resRel = $conn->query($relSql);
    if ($resRel) $resRel->free();
} else {
    // Gagal mendapatkan lock setelah beberapa percobaan -> fallback aman:
    // lakukan COUNT tanpa lock (lebih rentan race, tapi fallback ini jarang terjadi)
    $count = 0;
    $sqlCount = "SELECT COUNT(*) AS total FROM labelboning WHERE YEAR(creatime) = YEAR(CURRENT_DATE)";
    $res = $conn->query($sqlCount);
    if ($res) {
        $row = $res->fetch_array();
        $count = (int)($row['total'] ?? 0);
        $res->free();
    } else {
        // jika query gagal sama sekali, log dan gunakan 0
        error_log("seriallabelboning.php: COUNT fallback query failed: " . $conn->error);
        $count = 0;
    }
    $kode = $count + 1;
    // catat bahwa lock tidak berhasil (berguna untuk forensik)
    error_log("seriallabelboning.php: Unable to obtain GET_LOCK('{$lockName}') after {$maxAttempts} attempts. Using fallback COUNT method.");
}

// Format 9-digit zero-padded (preserve behaviour sebelumnya)
// %09s dipakai sebelumnya; gunakan %09d untuk numeric padding
$seriallabel = sprintf('%09d', (int)$kode);

// Gabungkan prefix (YYMMDD) dan serial 9-digit menjadi kodeauto
$kodeauto = $prefix . $seriallabel;

// variable $kodeauto siap digunakan oleh insert_labelboning.php
