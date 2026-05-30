<?php
require "../verifications/auth.php";
require "../konak/conn.php";
header('Content-Type: application/json');
$conn->set_charset('utf8mb4');

try {
    // ====== INPUT DARI DATATABLES ======
    $draw     = (int)($_POST['draw']   ?? 0);
    $start    = (int)($_POST['start']  ?? 0);
    $length   = (int)($_POST['length'] ?? 10);  // bisa -1 untuk "All"
    $search   = trim($_POST['search']['value'] ?? '');
    $order    = $_POST['order'][0] ?? ['column' => 7, 'dir' => 'desc']; // default sort "Create"
    $idboning = (int)($_POST['idboning'] ?? 0);

    if ($idboning <= 0) {
        echo json_encode(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []]);
        exit;
    }

    // Mapping index kolom (urutannya mengikuti tabel di frontend)
    // 0:#(rownum), 1:barcode, 2:grade, 3:product, 4:qty, 5:pcs, 6:author, 7:create, 8:action
    $cols = [
        0 => 'l.idlabelboning', // klik kolom # -> gunakan id untuk stabil
        1 => 'l.kdbarcode',
        2 => 'g.nmgrade',
        3 => 'b.nmbarang',
        4 => 'l.qty',
        5 => 'l.pcs',
        6 => 'u.fullname',
        7 => 'l.creatime',
        // 8 => action -> tidak dipetakan karena action bukan kolom DB
    ];
    $orderIdx = (int)($order['column'] ?? 7);
    $orderDir = (strtolower($order['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
    $orderCol = $cols[$orderIdx] ?? 'l.creatime';

    // ====== TOTAL TANPA FILTER ======
    $sqlTotal = "SELECT COUNT(*) AS c
               FROM labelboning l
               WHERE l.idboning = ? AND l.is_deleted = 0";
    $stmt = $conn->prepare($sqlTotal);
    $stmt->bind_param("i", $idboning);
    $stmt->execute();
    $recordsTotal = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    // ====== WHERE & PARAMS UNTUK FILTERED ======
    $where  = " WHERE l.idboning = ? AND l.is_deleted = 0 ";
    $params = [$idboning];
    $types  = "i";

    if ($search !== '') {
        // tambahkan qty/pcs ke pencarian dengan CAST ke CHAR supaya LIKE cocok pada angka
        $where .= " AND (
            l.kdbarcode LIKE ? OR
            b.nmbarang LIKE ? OR
            g.nmgrade LIKE ? OR
            u.fullname LIKE ? OR
            CAST(l.qty AS CHAR) LIKE ? OR
            CAST(l.pcs AS CHAR) LIKE ?
        ) ";
        $like = "%{$search}%";
        array_push($params, $like, $like, $like, $like, $like, $like);
        $types .= "ssssss";
    }

    // ====== TOTAL SETELAH FILTER ======
    $sqlFiltered = "
    SELECT COUNT(*) AS c
    FROM labelboning l
    JOIN barang b ON l.idbarang = b.idbarang
    JOIN grade  g ON l.idgrade  = g.idgrade
    JOIN users  u ON l.iduser   = u.idusers
    $where";
    $stmt = $conn->prepare($sqlFiltered);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $recordsFiltered = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();

    // ====== DUKUNG "ALL" (length = -1) ======
    if ($length === -1) {
        $MAX_ALL = 10000;                   // pengaman (ubah sesuai kebutuhan)
        $length  = min($recordsFiltered, $MAX_ALL);
        $start   = 0;
    } else {
        $MAX_PAGE = 1000;                   // batas wajar per halaman
        if ($length <= 0 || $length > $MAX_PAGE) $length = 10;
    }
    if ($start < 0) $start = 0;

    // ====== AMBIL DATA PAGE ======
    $sqlData = "
    SELECT
      l.idlabelboning,
      l.kdbarcode,
      g.nmgrade,
      b.nmbarang,
      l.qty,
      l.pcs,
      u.fullname,
      l.creatime,
      l.idbarang,          -- opsional (untuk URL/diagnostik)
      l.idgrade,           -- opsional
      /* status penggunaan barcode */
      EXISTS(SELECT 1 FROM tallydetail  t WHERE t.barcode = l.kdbarcode LIMIT 1) AS has_tally,
      EXISTS(SELECT 1 FROM detailbahan d WHERE d.barcode = l.kdbarcode LIMIT 1) AS has_detail
    FROM labelboning l
    JOIN barang b ON l.idbarang = b.idbarang
    JOIN grade  g ON l.idgrade  = g.idgrade
    JOIN users  u ON l.iduser   = u.idusers
    $where
    ORDER BY $orderCol $orderDir
    LIMIT ? OFFSET ?";

    $params2 = $params;
    $types2  = $types . "ii";
    $params2[] = $length;
    $params2[] = $start;

    $stmt = $conn->prepare($sqlData);
    $stmt->bind_param($types2, ...$params2);
    $stmt->execute();
    $rs = $stmt->get_result();

    // cek status kunci boning utk tombol aksi
    $stmt2 = $conn->prepare("SELECT kunci FROM boning WHERE idboning = ? LIMIT 1");
    $stmt2->bind_param("i", $idboning);
    $stmt2->execute();
    $is_locked = (int)($stmt2->get_result()->fetch_assoc()['kunci'] ?? 0);
    $stmt2->close();

    // ====== SUSUN OUTPUT ======
    $data   = [];
    $rownum = $start + 1;

    while ($r = $rs->fetch_assoc()) {
        // Kolom Action mengikuti logika lama
        $locked = ((int)$r['has_tally'] === 1 || (int)$r['has_detail'] === 1);

        if ($locked) {
            if ((int)$r['has_tally'] === 1) {
                // Sudah masuk Tally → ikon cek
                $action = '<i class="fas fa-check-circle" data-toggle="tooltip" title="Sudah masuk Tally"></i>';
            } else {
                // Sudah masuk Bahan/Repack → ikon box hijau
                $action = '<i class="fas fa-box-open text-success" data-toggle="tooltip" title="Sudah masuk Bahan/Repack"></i>';
            }
        } else {
            if ($is_locked === 0) {
                $id   = (int)$r['idlabelboning'];
                $kode = urlencode($r['kdbarcode']);

                $delUrl = 'hapus_labelboning.php?id=' . $id . '&idboning=' . $idboning . '&kdbarcode=' . $kode;
                $btnDel = '<a href="' . $delUrl . '" class="text-danger" data-toggle="tooltip" title="Hapus" ' .
                    'onclick="return confirm(\'Apakah anda yakin ingin menghapus label ini?\');">' .
                    '<i class="fas fa-minus-square"></i></a>';

                $action = $btnDel;
            } else {
                $action = '';
            }
        }

        $data[] = [
            'rownum'    => $rownum++,
            'kdbarcode' => htmlspecialchars($r['kdbarcode'], ENT_QUOTES, 'UTF-8'),
            'nmgrade'   => htmlspecialchars($r['nmgrade'],   ENT_QUOTES, 'UTF-8'),
            'nmbarang'  => htmlspecialchars($r['nmbarang'],  ENT_QUOTES, 'UTF-8'),
            'qty'       => number_format((float)$r['qty'], 2),
            'pcs'       => is_null($r['pcs']) ? '' : (int)$r['pcs'],
            'fullname'  => htmlspecialchars($r['fullname'],  ENT_QUOTES, 'UTF-8'),
            'creatime'  => date('H:i:s', strtotime($r['creatime'])),
            'action'    => $action
        ];
    }
    $stmt->close();

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data'            => $data
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'draw' => 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
        'error' => 'server_error'
    ]);
}
