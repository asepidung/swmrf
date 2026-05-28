<?php
require "../verifications/auth.php";
require "../konak/conn.php"; // Koneksi database

$search = $_POST['search'] ?? '';

if (empty($search)) {
    echo "<tr><td colspan='6' class='text-center'>Masukkan kode untuk mencari</td></tr>";
    exit;
}

// Daftar tabel yang akan dicari beserta nama yang lebih mudah dibaca
$tables = [
    "labelboning" => ["barcode" => "kdbarcode", "weight" => "qty", "pod" => "NULL", "alias" => "BONING"],
    "tallydetail" => ["barcode" => "barcode", "weight" => "weight", "pod" => "pod", "alias" => "Tally"],
    "detailbahan" => ["barcode" => "barcode", "weight" => "qty", "pod" => "pod", "alias" => "BAHAN REPACK"],
    "detailhasil" => ["barcode" => "kdbarcode", "weight" => "qty", "pod" => "NULL", "alias" => "HASIL REPACK"],
    "grbeefdetail" => ["barcode" => "kdbarcode", "weight" => "qty", "pod" => "pod", "alias" => "GR"],
    "returjualdetail" => ["barcode" => "kdbarcode", "weight" => "qty", "pod" => "pod", "alias" => "RETUR PENJUALAN"]
];

$results = [];

foreach ($tables as $table => $columns) {
    $pod_column = $columns['pod'] !== "NULL" ? "d.{$columns['pod']}" : "NULL AS pod";

    $query = "
        SELECT 
            '{$columns['alias']}' as source, 
            b.nmbarang as item, 
            d.{$columns['barcode']} as barcode,
            d.{$columns['weight']} as weight,
            $pod_column,
            d.creatime AS real_timestamp
        FROM $table d
        LEFT JOIN barang b ON d.idbarang = b.idbarang
        WHERE d.{$columns['barcode']} = '$search'
    ";

    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Format tanggal untuk creatime (26-Feb-2025 16:24:38)
            if (!empty($row['real_timestamp'])) {
                $row['real_timestamp'] = date("d-M-Y H:i:s", strtotime($row['real_timestamp']));
            } else {
                $row['real_timestamp'] = '-';
            }

            // Format tanggal untuk POD (26-Feb-2025)
            if (!empty($row['pod']) && $row['pod'] !== '0000-00-00') {
                $row['pod'] = date("d-M-Y", strtotime($row['pod']));
            } else {
                $row['pod'] = '-';
            }

            $results[] = $row;
        }
    }
}

// **Sorting berdasarkan timestamp DESC di PHP**
usort($results, function ($a, $b) {
    return strtotime($b['real_timestamp']) - strtotime($a['real_timestamp']);
});

// **Menampilkan hasil pencarian**
if (!empty($results)) {
    $no = 1;
    foreach ($results as $row) {
        echo "<tr class='text-center'>
                <td>" . $no++ . "</td>
                <td class='text-left'>" . $row['source'] . "</td>
                <td>" . $row['real_timestamp'] . "</td>
                <td>" . $row['item'] . "</td>
                <td class='text-right'>" . $row['weight'] . "</td>
                <td>" . $row['pod'] . "</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center'>Data tidak ditemukan</td></tr>";
}
