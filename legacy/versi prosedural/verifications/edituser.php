<?php
// Include necessary files for session and database connection
include 'auth.php'; // for session checking
require "../konak/conn.php"; // pastikan file koneksi berada di path yang benar

// Check if ID is passed through URL
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // MODIFIKASI: Query JOIN dengan tabel role untuk mengambil data akses
    $query = "SELECT u.*, r.cattle, r.produksi, r.warehouse, r.stock, r.distributions, 
                     r.purchase_module, r.sales, r.finance, r.data_report, r.master_data, r.qc
              FROM users u
              LEFT JOIN role r ON u.idusers = r.idusers
              WHERE u.idusers = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        $fullname = $user['fullname'];
        $userid = $user['userid'];
        // Simpan data role ke dalam variabel untuk digunakan di checkbox
        $roles = [
            'cattle' => $user['cattle'],
            'produksi' => $user['produksi'],
            'warehouse' => $user['warehouse'],
            'stock' => $user['stock'],
            'distributions' => $user['distributions'],
            'purchase_module' => $user['purchase_module'],
            'sales' => $user['sales'],
            'finance' => $user['finance'],
            'data_report' => $user['data_report'],
            'master_data' => $user['master_data'],
            'qc' => $user['qc'] // Tambahan role QC
        ];
    } else {
        echo "User tidak ditemukan.";
        exit();
    }
} else {
    echo "ID pengguna tidak ditemukan.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pengguna</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #333;
        }

        label {
            font-size: 16px;
            margin-bottom: 8px;
            display: block;
            color: #555;
        }

        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .role-group {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #eee;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .checkbox-item input {
            margin-right: 10px;
            width: 18px;
            height: 18px;
        }

        button {
            background-color: #4CAF50;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
        }

        button:hover {
            background-color: #45a049;
        }

        .form-group span {
            color: #888;
            font-size: 13px;
            display: block;
            margin-top: -10px;
            margin-bottom: 15px;
        }
    </style>
</head>

<body>

    <div class="container">
        <h2>Edit Pengguna: <?= htmlspecialchars($fullname) ?></h2>

        <form method="POST" action="updateuser.php">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="form-group">
                <label for="userid">ID Pengguna:</label>
                <input type="text" id="userid" name="userid" value="<?= htmlspecialchars($userid) ?>" required>
            </div>

            <div class="form-group">
                <label for="fullname">Nama Lengkap:</label>
                <input type="text" id="fullname" name="fullname" value="<?= htmlspecialchars($fullname) ?>" required>
            </div>

            <div class="role-group">
                <label><strong>Hak Akses Menu:</strong></label>
                <?php foreach ($roles as $key => $value): ?>
                    <div class="checkbox-item">
                        <input type="checkbox" name="menu_access[]" id="<?= $key ?>" value="<?= $key ?>" <?= $value == 1 ? 'checked' : '' ?>>
                        <label style="display:inline; margin:0;" for="<?= $key ?>"><?= ucwords(str_replace('_', ' ', $key)) ?></label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form-group">
                <label for="password">Password Konfirmasi:</label>
                <input type="password" id="password" name="password" required>
                <span>Masukkan password lama (konfirmasi admin) untuk menyimpan perubahan</span>
            </div>

            <div class="form-group">
                <label for="new_password">Password Baru:</label>
                <input type="password" id="new_password" name="new_password">
                <span>Isi jika ingin mengganti password user ini</span>
            </div>

            <button type="submit">Simpan Perubahan</button>
        </form>
    </div>

</body>

</html>