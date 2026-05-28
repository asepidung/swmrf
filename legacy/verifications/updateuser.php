<?php
// Include necessary files for session and database connection
include 'auth.php'; // for session checking
require "../konak/conn.php"; // pastikan file koneksi berada di path yang benar

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data dari form
    $id = $_POST['id'];
    $userid = mysqli_real_escape_string($conn, $_POST['userid']);
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $password = $_POST['password'];
    $new_password = $_POST['new_password'];

    // Ambil data menu_access dari checkbox (jika ada)
    $menu_access = isset($_POST['menu_access']) ? $_POST['menu_access'] : [];

    // Query untuk mengambil data pengguna berdasarkan idusers
    $query = "SELECT * FROM users WHERE idusers = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user) {
        // 1. Verifikasi password lama
        if (password_verify($password, $user['passuser'])) {

            // Mulai Transaksi agar update users dan role sinkron
            mysqli_begin_transaction($conn);

            try {
                // Tentukan password yang akan disimpan
                if (!empty($new_password)) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                } else {
                    $new_password_hash = $user['passuser'];
                }

                // 2. Update tabel USERS
                $update_query = "UPDATE users SET userid = ?, fullname = ?, passuser = ? WHERE idusers = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sssi", $userid, $fullname, $new_password_hash, $id);
                $update_stmt->execute();

                // 3. Update tabel ROLE
                // Siapkan default 0
                $access = [
                    'cattle' => 0,
                    'produksi' => 0,
                    'warehouse' => 0,
                    'stock' => 0,
                    'distributions' => 0,
                    'purchase_module' => 0,
                    'sales' => 0,
                    'finance' => 0,
                    'data_report' => 0,
                    'master_data' => 0,
                    'qc' => 0
                ];

                // Set nilai 1 jika dicentang
                foreach ($menu_access as $menu) {
                    if (isset($access[$menu])) {
                        $access[$menu] = 1;
                    }
                }

                $role_query = "UPDATE role SET 
                                cattle = ?, produksi = ?, warehouse = ?, stock = ?, 
                                distributions = ?, purchase_module = ?, sales = ?, 
                                finance = ?, data_report = ?, master_data = ?, qc = ? 
                                WHERE idusers = ?";
                $role_stmt = $conn->prepare($role_query);
                $role_stmt->bind_param(
                    "iiiiiiiiiiii",
                    $access['cattle'],
                    $access['produksi'],
                    $access['warehouse'],
                    $access['stock'],
                    $access['distributions'],
                    $access['purchase_module'],
                    $access['sales'],
                    $access['finance'],
                    $access['data_report'],
                    $access['master_data'],
                    $access['qc'],
                    $id
                );
                $role_stmt->execute();

                // Commit Transaksi
                mysqli_commit($conn);
                header('Location: ../index.php?stat=updated');
            } catch (Exception $e) {
                mysqli_rollback($conn);
                echo "Gagal memperbarui data: " . $e->getMessage();
            }
        } else {
            echo "Password lama yang Anda masukkan salah!";
        }
    } else {
        echo "User tidak ditemukan.";
    }
}
