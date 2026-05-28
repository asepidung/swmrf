<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Mengamankan ID dengan casting ke integer
$idusers = (int)$_GET['id'];

// Ambil data role dari tabel role berdasarkan idusers
// Menggunakan query yang lebih aman atau pastikan $idusers sudah divalidasi
$ambildata = mysqli_query($conn, "SELECT * FROM role WHERE idusers = $idusers");
$tampil = mysqli_fetch_assoc($ambildata);

// Jika data tidak ditemukan, arahkan kembali
if (!$tampil) {
   echo "<script>alert('Data role tidak ditemukan.'); window.location='index.php';</script>";
   exit();
}

// Daftar role yang tersedia untuk ditampilkan sebagai checkbox
$roles = [
   'cattle'          => 'Cattle',
   'produksi'        => 'Produksi',
   'warehouse'       => 'Warehouse',
   'stock'           => 'Stock',
   'distributions'   => 'Distributions',
   'purchase_module' => 'Purchase Module',
   'sales'           => 'Sales',
   'finance'         => 'Finance',
   'data_report'     => 'Data Report',
   'master_data'     => 'Master Data',
   'qc'              => 'QC' // Menambahkan role QC ke dalam daftar akses
];
?>

<div class="content-wrapper">
   <section class="content">
      <div class="container-fluid">
         <div class="row">
            <div class="col-md-6">
               <div class="card mt-3 shadow">
                  <div class="card-body register-card-body">
                     <p class="login-box-msg font-weight-bold">Pengaturan Hak Akses User</p>
                     <form action="updateuser.php" method="post">
                        <input type="hidden" name="id" value="<?= $idusers ?>">

                        <div class="form-group">
                           <label>Pilih Menu yang Bisa Diakses:</label><br>
                           <div class="row">
                              <?php foreach ($roles as $key => $label) : ?>
                                 <div class="col-sm-6">
                                    <div class="custom-control custom-checkbox mb-2">
                                       <input type="checkbox" class="custom-control-input" name="menu_access[]"
                                          id="<?= $key ?>" value="<?= $key ?>"
                                          <?= (isset($tampil[$key]) && $tampil[$key] == 1) ? 'checked' : '' ?>>
                                       <label class="custom-control-label" for="<?= $key ?>"><?= $label ?></label>
                                    </div>
                                 </div>
                              <?php endforeach; ?>
                           </div>
                        </div>
                        <hr>
                        <div class="row">
                           <div class="col-12">
                              <button type="submit" class="btn btn-primary btn-block">
                                 <i class="fas fa-save mr-2"></i> Update Hak Akses
                              </button>
                           </div>
                        </div>
                     </form>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </section>
</div>

<script>
   document.title = "Edit User Access";
</script>

<?php
include "../footer.php";
?>