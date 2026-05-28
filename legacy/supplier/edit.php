<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Mengecek apakah ID supplier diterima dari URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = $_GET['id'];

    // Query untuk mengambil data supplier berdasarkan ID
    $sql = "SELECT * FROM supplier WHERE idsupplier = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $supplier = $result->fetch_assoc();
    } else {
        echo "<script>alert('Data tidak ditemukan.'); window.location.href='supplier.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID supplier tidak diterima.'); window.location.href='supplier.php';</script>";
    exit;
}
?>

<div class="content-wrapper">
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- left column -->
                <div class="col-md-6">
                    <!-- general form elements -->
                    <div class="card card-dark mt-3">
                        <div class="card-header">
                            <h3 class="card-title">Edit Data Supplier</h3>
                        </div>
                        <!-- /.card-header -->
                        <!-- form start -->
                        <form method="POST" action="update.php">
                            <div class=" card-body">
                                <div class="form-group">
                                    <label for="nmsupplier">Nama Supplier <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nmsupplier" id="nmsupplier" value="<?= htmlspecialchars($supplier['nmsupplier']) ?>" autofocus required>
                                </div>
                                <div class="form-group">
                                    <label for="alamat">Alamat</label>
                                    <input type="text" class="form-control" name="alamat" id="alamat" value="<?= htmlspecialchars($supplier['alamat']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="jenis_usaha">Barang Yang Disuplai <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="jenis_usaha" id="jenis_usaha" value="<?= htmlspecialchars($supplier['jenis_usaha']) ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="telepon">No Telepon</label>
                                    <input type="text" class="form-control" name="telepon" id="telepon" value="<?= htmlspecialchars($supplier['telepon']) ?>">
                                </div>
                                <div class="form-group">
                                    <label for="npwp">NPWP</label>
                                    <input type="text" class="form-control" name="npwp" id="npwp" value="<?= htmlspecialchars($supplier['npwp']) ?>">
                                </div>
                                <!-- Hidden field untuk ID supplier -->
                                <input type="hidden" name="id" value="<?= htmlspecialchars($supplier['idsupplier']) ?>">
                                <div class="form-group mr-3 text-right">
                                    <button type="submit" class="btn bg-gradient-primary">Update</button>
                                </div>
                            </div>
                            <!-- /.card-body -->
                        </form>
                    </div>
                    <!-- /.card -->
                </div>
            </div>
    </section>
</div>

<?php include "../footer.php"; ?>