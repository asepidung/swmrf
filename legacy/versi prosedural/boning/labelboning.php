<?php
require "../verifications/auth.php";
require "../konak/conn.php";
require "../header.php";
require "../navbar.php";
require "../mainsidebar.php";

/*
|------------------------------------------------------------------
| MULTI-TOKEN ANTI-DUPLICATE (mendukung banyak tab)
|------------------------------------------------------------------
*/
if (!isset($_SESSION['label_form_tokens']) || !is_array($_SESSION['label_form_tokens'])) {
  $_SESSION['label_form_tokens'] = [];
}

// generate token baru untuk halaman ini
$tok = bin2hex(random_bytes(16));

// simpan token dengan timestamp
$_SESSION['label_form_tokens'][$tok] = time();

// batasi maksimal 20 token tersimpan
if (count($_SESSION['label_form_tokens']) > 20) {
  asort($_SESSION['label_form_tokens']); // urutkan dari yang paling lama
  while (count($_SESSION['label_form_tokens']) > 20) {
    array_shift($_SESSION['label_form_tokens']);
  }
}


/*
|------------------------------------------------------------------
| VALIDASI idboning + persiapan data
|------------------------------------------------------------------
*/
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
  die("Jalankan Dari Modul Produksi");
}
$idboning = (int)$_GET['id'];
$idusers  = $_SESSION['idusers'] ?? 0;

// Dropdown data
$qBarang = $conn->query("SELECT idbarang, nmbarang FROM barang ORDER BY nmbarang ASC");
if (!$qBarang) die("Error barang: " . $conn->error);

$qGrade  = $conn->query("SELECT idgrade, nmgrade FROM grade ORDER BY nmgrade ASC");
if (!$qGrade) die("Error grade: " . $conn->error);

// Default tanggal
if (empty($_SESSION['packdate'])) $_SESSION['packdate'] = date('Y-m-d');

// Info batch & kunci
$batch = $conn->query("SELECT batchboning, kunci FROM boning WHERE idboning = $idboning LIMIT 1");
if (!$batch) die("Error boning: " . $conn->error);
$info = $batch->fetch_assoc();

$is_batch  = (int)($info['batchboning'] ?? 0);
$is_locked = (int)($info['kunci'] ?? 0);
?>
<div class="content-wrapper">
  <div class="content-header">
    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-6">
          <a href="databoning.php" class="btn btn-sm btn-success">
            <i class="fas fa-undo-alt"></i> DATA BONING
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="content">
    <div class="container-fluid">
      <div class="row">

        <?php if ($is_locked === 0): ?>
          <div class="col-lg-4">
            <div class="card">
              <div class="card-body">
                <form id="labelForm" method="POST" action="insert_labelboning.php">

                  <!-- token multi-tab -->
                  <input type="hidden" name="form_token" value="<?= htmlspecialchars($tok, ENT_QUOTES) ?>">

                  <!-- Barang -->
                  <div class="form-group">
                    <div class="input-group">
                      <select class="form-control" name="idbarang" id="idbarang" required autofocus>
                        <?php
                        $selBrg = (int)($_SESSION['idbarang'] ?? 0);
                        echo $selBrg
                          ? '<option value="' . $selBrg . '" selected>--Pilih Item--</option>'
                          : '<option value="" selected>--Pilih Item--</option>';

                        while ($r = $qBarang->fetch_assoc()) {
                          $idb = (int)$r['idbarang'];
                          $nm  = htmlspecialchars($r['nmbarang']);
                          $sel = $idb === $selBrg ? 'selected' : '';
                          echo "<option value=\"$idb\" $sel>$nm</option>";
                        }
                        ?>
                      </select>
                      <div class="input-group-append">
                        <a href="../barang/newbarang.php" class="btn btn-primary"><i class="fas fa-plus"></i></a>
                      </div>
                    </div>
                  </div>

                  <!-- Grade -->
                  <div class="form-group">
                    <select class="form-control" name="idgrade" id="idgrade" required>
                      <?php
                      $selGrd = (int)($_SESSION['idgrade'] ?? 0);
                      echo $selGrd
                        ? '<option value="' . $selGrd . '" selected>--Pilih Grade--</option>'
                        : '<option value="" selected>--Pilih Grade--</option>';

                      while ($r = $qGrade->fetch_assoc()) {
                        $idg = (int)$r['idgrade'];
                        $nm  = htmlspecialchars($r['nmgrade']);
                        $sel = $idg === $selGrd ? 'selected' : '';
                        echo "<option value=\"$idg\" $sel>$nm</option>";
                      }
                      ?>
                    </select>
                  </div>
                  <!-- Tanggal & Checkbox Expired (Mengikuti Grid QTY & PH) -->
                  <div class="row">
                    <div class="col-8">
                      <div class="form-group">
                        <input type="date" class="form-control" name="packdate" id="packdate" required value="<?= htmlspecialchars($_SESSION['packdate']) ?>">
                      </div>
                    </div>
                    <div class="col">
                      <div class="form-group mt-2">
                        <div class="custom-control custom-checkbox mb-0">
                          <?php
                          // Cek memori session, kalau nilainya 1 berarti sebelumnya dicentang
                          $is_exp_checked = (isset($_SESSION['print_exp']) && $_SESSION['print_exp'] == 1) ? 'checked' : '';
                          ?>
                          <input type="checkbox" class="custom-control-input" name="print_exp" id="print_exp" value="1" <?= $is_exp_checked ?>>
                          <label class="custom-control-label text-nowrap" for="print_exp">Show Expired</label>
                        </div>
                      </div>
                    </div>
                  </div>

                  <input type="hidden" name="idusers" value="<?= (int)$idusers ?>">
                  <input type="hidden" name="idboning" value="<?= (int)$idboning ?>">

                  <!-- Qty & PH -->
                  <div class="row">
                    <div class="col-8">
                      <div class="form-group">
                        <input type="text" class="form-control" name="qty" id="qty" placeholder="Weight & Pcs" required>
                      </div>
                    </div>
                    <div class="col">
                      <div class="form-group">
                        <input type="number" step="0.1" min="5.4" max="5.7" class="form-control" name="ph" id="ph" placeholder="PH 5.4-5.7" required value="<?= htmlspecialchars($_SESSION['ph'] ?? '', ENT_QUOTES) ?>">
                      </div>
                    </div>
                  </div>

                  <button type="submit" class="btn bg-gradient-primary btn-block" name="submit">Print</button>

                </form>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- LIST -->
        <div class="col-lg">
          <div class="card">
            <div class="card-body">
              <table id="tblLabel" class="table table-bordered table-striped table-sm w-100">
                <thead class="text-center">
                  <tr>
                    <th>#</th>
                    <th>Barcode</th>
                    <th>Grade</th>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Pcs</th>
                    <th>Author</th>
                    <th>Create</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </div>

      </div><!-- /.row -->
    </div><!-- /.container-fluid -->
  </div><!-- /.content -->

  <script>
    document.title = "Label Boning";
    document.addEventListener('DOMContentLoaded', function() {
      var s = document.getElementById('idbarang');
      if (s) s.addEventListener('keydown', function(e) {
        if (e.key === 'Tab' || e.keyCode === 9) {
          e.preventDefault();
          var q = document.getElementById('qty');
          if (q) q.focus();
        }
      });

      const idboning = <?= (int)$idboning ?>;

      const dt = $('#tblLabel').DataTable({
        processing: true,
        serverSide: true,
        deferRender: true,
        responsive: true,
        pageLength: 10,
        lengthMenu: [
          [10, 25, 50, 100, 250, 500, -1],
          [10, 25, 50, 100, 250, 500, 'All']
        ],
        order: [
          [7, 'desc']
        ],
        ajax: {
          url: 'labelboning_data.php',
          type: 'POST',
          data: d => {
            d.idboning = <?= (int)$idboning ?>;
          }
        },
        columns: [{
            data: 'rownum',
            className: 'text-center'
          },
          {
            data: 'kdbarcode',
            className: 'text-center'
          },
          {
            data: 'nmgrade',
            className: 'text-center'
          },
          {
            data: 'nmbarang'
          },
          {
            data: 'qty',
            className: 'text-right'
          },
          {
            data: 'pcs',
            className: 'text-center'
          },
          {
            data: 'fullname',
            className: 'text-center'
          },
          {
            data: 'creatime',
            className: 'text-center'
          },
          {
            data: 'action',
            className: 'text-center',
            orderable: false,
            searchable: false
          }
        ],

        dom: "<'row'<'col-sm-12 col-md-3'l><'col-sm-12 col-md-6'B><'col-sm-12 col-md-3'f>>" +
          "<'row'<'col-sm-12'tr>>" +
          "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [{
            extend: 'copyHtml5',
            className: 'btn btn-secondary btn-sm'
          },
          {
            extend: 'excelHtml5',
            className: 'btn btn-secondary btn-sm'
          },
          {
            extend: 'pdfHtml5',
            className: 'btn btn-secondary btn-sm'
          },
          {
            extend: 'print',
            className: 'btn btn-secondary btn-sm'
          },
          {
            extend: 'colvis',
            className: 'btn btn-secondary btn-sm'
          }
        ]
      });

      dt.buttons().container().appendTo('#tblLabel_wrapper .col-md-6:eq(0)');
    });

    document.addEventListener('DOMContentLoaded', function() {
      var form = document.getElementById('labelForm');
      if (!form) return;

      var btn = form.querySelector('button[type="submit"]');

      form.addEventListener('submit', function(e) {
        if (form.dataset.submitted === '1') {
          e.preventDefault();
          return false;
        }

        form.dataset.submitted = '1';
        if (btn) {
          try {
            btn.disabled = true;
            btn.innerText = 'Processing...';
          } catch (err) {}
        }
      });
    });
  </script>
</div>

<?php require "../footer.php"; ?>