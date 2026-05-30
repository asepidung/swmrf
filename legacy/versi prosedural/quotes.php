<?php
// update.php - menampilkan satu quote acak beserta penulis, waktu, + tombol Add + tombol Edit (bersyarat)
// Prasyarat: session_start() dan $conn sudah aktif dari index.php

// Pastikan koneksi tersedia
if (!isset($conn) || !$conn) {
   echo '<div class="alert alert-warning">Database connection not available.</div>';
   return;
}

$currentUserId = $_SESSION['idusers'] ?? null;

/**
 * Ambil satu quote acak + author + id pembuat + waktu pembuatan
 */
function getRandomQuoteWithAuthor($conn)
{
   $sql = "
        SELECT 
            q.idquote,
            q.isiquote,
            q.idusers AS quote_owner,
            q.created_at,
            u.fullname
        FROM quotes q
        LEFT JOIN users u ON q.idusers = u.idusers
        ORDER BY RAND()
        LIMIT 1
    ";

   $res = $conn->query($sql);
   if ($res && $res->num_rows > 0) {
      return $res->fetch_assoc();
   }
   return null;
}

$row = getRandomQuoteWithAuthor($conn);

$quote       = $row['isiquote'] ?? 'No quotes available.';
$author      = $row['fullname'] ?? null;
$idquote     = $row['idquote'] ?? null;
$quoteOwner  = $row['quote_owner'] ?? null;
$createdAt   = $row['created_at'] ?? null;

// Rule tombol Edit:
// - admin (idusers = 1) selalu boleh edit
// - user biasa hanya jika $quoteOwner === $currentUserId
$showEditButton = false;

if ($currentUserId == 1) {
   $showEditButton = true;
} elseif ($quoteOwner !== null && $quoteOwner == $currentUserId) {
   $showEditButton = true;
}

?>
<div class="card card-danger shadow-lg">
   <div class="card-header">
      <h3 class="card-title">Quotes Of The Day</h3>
      <div class="card-tools">
         <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fas fa-times"></i></button>
      </div>
   </div>

   <div class="card-body">
      <h4>
         <span style="opacity:0.6; font-size:1.3em;">“</span>
         <em><?= htmlspecialchars($quote) ?></em>
         <span style="opacity:0.6; font-size:1.3em;">”</span>
      </h4>

      <div class="mt-2">
         <small class="text-muted">
            — <?= $author ? htmlspecialchars($author, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'Unknown' ?>
            <?php if ($createdAt): ?>
               <span class="ml-1" style="font-size: 0.85em;">
                  (<?= date('d M Y, H:i', strtotime($createdAt)) ?>)
               </span>
            <?php endif; ?>
         </small>
      </div>
   </div>

   <div class="card-footer d-flex justify-content-between">
      <a href="new_quotes.php" class="btn btn-sm btn-light">
         <i class="fas fa-plus"></i> Add Your Quote
      </a>

      <?php if ($showEditButton && $idquote): ?>
         <a href="edit_quotes.php?id=<?= intval($idquote) ?>"
            class="btn btn-sm btn-warning rounded-pill shadow-sm px-3"
            data-toggle="tooltip" title="Edit quote">
            <i class="fas fa-pencil-alt mr-1"></i> Edit
         </a>
      <?php endif; ?>
   </div>
</div>