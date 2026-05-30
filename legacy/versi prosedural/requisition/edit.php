<?php
require "../verifications/auth.php";
require "../konak/conn.php";
include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";

// Ambil ID dari URL
$idrequest = $_GET['id'] ?? null;
if (!$idrequest) {
    die("Error: Missing request ID.");
}

// Ambil data request berdasarkan ID
$query_request = "SELECT * FROM request WHERE idrequest = ?";
$stmt = mysqli_prepare($conn, $query_request);
mysqli_stmt_bind_param($stmt, "i", $idrequest);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result->num_rows === 0) {
    die("Error: Request not found.");
}

$request = mysqli_fetch_assoc($result);

// Ambil data detail permintaan dari tabel requestdetail
$query_details = "SELECT * FROM requestdetail WHERE idrequest = ?";
$stmt_details = mysqli_prepare($conn, $query_details);
mysqli_stmt_bind_param($stmt_details, "i", $idrequest);
mysqli_stmt_execute($stmt_details);
$details_result = mysqli_stmt_get_result($stmt_details);

$request_details = [];
while ($detail = mysqli_fetch_assoc($details_result)) {
    $request_details[] = $detail;
}

mysqli_stmt_close($stmt);
mysqli_stmt_close($stmt_details);
?>

<div class="content-wrapper">
    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col mt-3">
                    <form method="POST" action="update.php">
                        <input type="hidden" name="idrequest" value="<?= htmlspecialchars($idrequest) ?>">

                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-sm-3">
                                        <div class="form-group">
                                            <label for="duedate">Due Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="duedate" id="duedate" value="<?= htmlspecialchars($request['duedate']) ?>" required>
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-3">
                                        <div class="form-group">
                                            <label for="idsupplier">Buy To <span class="text-danger">*</span></label>
                                            <select class="form-control" name="idsupplier" id="idsupplier" required>
                                                <option value="">Pilih Supplier</option>
                                                <?php
                                                $query = "SELECT * FROM supplier ORDER BY nmsupplier ASC";
                                                $result = mysqli_query($conn, $query);
                                                while ($row = mysqli_fetch_assoc($result)) {
                                                    $selected = $row['idsupplier'] == $request['idsupplier'] ? 'selected' : '';
                                                    echo "<option value=\"{$row['idsupplier']}\" $selected>{$row['nmsupplier']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-12 col-sm-3">
                                        <div class="form-group">
                                            <label for="tax">Tax</label>
                                            <select class="form-control" name="tax" id="tax">
                                                <option value="" <?= $request['tax'] == '' ? 'selected' : '' ?>>Pilih Pajak</option>
                                                <option value="No" <?= $request['tax'] == 'No' ? 'selected' : '' ?>>No</option>
                                                <option value="11" <?= $request['tax'] == '11' ? 'selected' : '' ?>>11 %</option>
                                                <option value="12" <?= $request['tax'] == '12' ? 'selected' : '' ?>>12 %</option>
                                            </select>
                                        </div>
                                    </div>


                                    <div class="col-12 col-sm-3">
                                        <div class="form-group">
                                            <label for="top">T.O.P<span class="text-danger">*</span></label>
                                            <input type="number" class="form-control" name="top" id="top" value="<?= htmlspecialchars($request['top']) ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12">
                                        <div class="form-group">
                                            <label for="note">Note</label>
                                            <input type="text" class="form-control" name="note" id="note" value="<?= htmlspecialchars($request['note']) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div id="items-container">
                                    <?php foreach ($request_details as $detail): ?>
                                        <div class="row item-row">
                                            <div class="col-12 col-md-3">
                                                <div class="form-group">
                                                    <select class="form-control" name="idrawmate[]" required>
                                                        <option value="">--Product--</option>
                                                        <?php
                                                        $query = "SELECT * FROM rawmate ORDER BY nmrawmate ASC";
                                                        $result = mysqli_query($conn, $query);
                                                        while ($row = mysqli_fetch_assoc($result)) {
                                                            $selected = $row['idrawmate'] == $detail['idrawmate'] ? 'selected' : '';
                                                            echo "<option value=\"{$row['idrawmate']}\" $selected>{$row['nmrawmate']}</option>";
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-1 mb-1">
                                                <input type="text" name="weight[]" class="form-control text-right" placeholder="Qty" value="<?= htmlspecialchars($detail['qty']) ?>" required>
                                            </div>
                                            <div class="col-6 col-md-2 mb-1">
                                                <input type="text" name="price[]" class="form-control text-right" placeholder="Price" value="<?= htmlspecialchars($detail['price']) ?>" required>
                                            </div>
                                            <div class="col-6 col-md-2 mb-1">
                                                <input type="text" name="amount[]" class="form-control text-right" placeholder="Amount" readonly>
                                            </div>
                                            <div class="col-6 col-md-3 mb-1">
                                                <input type="text" name="notes[]" class="form-control" placeholder="Note" value="<?= htmlspecialchars($detail['notes']) ?>">
                                            </div>
                                            <div class="col mb-1">
                                                <button type="button" class="btn btn-link text-danger btn-remove-item" onclick="removeItem(this)">
                                                    <i class="fas fa-minus-circle"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="row align-items-center mt-3">
                                    <div class="col-12 col-md-2">
                                        <button type="button" class="btn btn-link text-success" onclick="addItem()">
                                            <i class="fas fa-plus-circle"></i> Add Item
                                        </button>
                                    </div>

                                    <div class="col-6 col-md-2 mb-1">
                                        <input type="text" name="xweight" id="xweight" class="form-control text-right" readonly placeholder="Total Qty">
                                    </div>

                                    <div class="col-6 col-md-2 mb-1">
                                        <input type="text" name="taxrp" id="taxrp" class="form-control text-right" readonly placeholder="Tax Amount">
                                    </div>

                                    <div class="col-6 col-md-2 mb-1">
                                        <input type="text" name="xamount" id="xamount" class="form-control text-right" readonly placeholder="Grand Total">
                                    </div>

                                    <div class="col-6 col-md mb-1">
                                        <button type="submit" class="btn btn-primary btn-block" name="update">
                                            Update Request
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</div>
<script>
    // Fungsi untuk memformat angka dengan pemisah ribuan
    function formatNumber(num) {
        return num.toLocaleString('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // Fungsi untuk menghitung Total, Tax, dan Grand Total
    function calculateTotals() {
        const weightInputs = document.querySelectorAll('[name="weight[]"]');
        const priceInputs = document.querySelectorAll('[name="price[]"]');
        const amountInputs = document.querySelectorAll('[name="amount[]"]');
        const xweight = document.getElementById('xweight');
        const taxrp = document.getElementById('taxrp');
        const xamount = document.getElementById('xamount');
        const taxSelect = document.getElementById('tax');

        let totalWeight = 0;
        let totalAmount = 0;

        // Hitung Total Weight dan Amount
        weightInputs.forEach((weightInput, index) => {
            // Hapus pemisah ribuan sebelum perhitungan
            const weight = parseFloat(weightInput.value.replace(/\./g, '').replace(/,/g, '.')) || 0;
            const price = parseFloat(priceInputs[index].value.replace(/\./g, '').replace(/,/g, '.')) || 0;
            const amount = weight * price;

            // Tampilkan hasil Amount dalam format angka dengan pemisah ribuan
            amountInputs[index].value = formatNumber(amount);

            totalWeight += weight;
            totalAmount += amount;
        });

        // Hitung Pajak
        const taxRate = taxSelect.value === '11' ? 0.11 : taxSelect.value === '12' ? 0.12 : 0;
        const taxValue = totalAmount * taxRate;

        // Total Amount dengan Pajak
        const finalAmount = totalAmount + taxValue;

        // Tampilkan Total dengan format angka
        xweight.value = formatNumber(totalWeight);
        taxrp.value = formatNumber(taxValue);
        xamount.value = formatNumber(finalAmount);
    }

    // Fungsi untuk memformat input angka dengan pemisah ribuan saat pengguna mengetik
    function formatInputValue(input) {
        let value = input.value.replace(/\./g, '').replace(/,/g, '.'); // Hapus pemisah ribuan sebelumnya
        if (value) {
            value = parseFloat(value).toLocaleString('id-ID'); // Tambahkan pemisah ribuan
            input.value = value; // Tampilkan nilai yang diformat di input
        }
    }

    // Event listener untuk memformat input weight dan price saat pengguna mengetik
    document.addEventListener('input', function(e) {
        if (e.target.name === 'weight[]' || e.target.name === 'price[]') {
            formatInputValue(e.target); // Format input value langsung
            calculateTotals(); // Hitung total setelah perubahan input
        }
    });

    // Update total saat pajak berubah
    document.getElementById('tax').addEventListener('change', calculateTotals);

    // Fungsi untuk menambah item
    function addItem() {
        const itemsContainer = document.getElementById('items-container');
        const newItemRow = document.createElement('div');
        newItemRow.className = 'row';

        newItemRow.innerHTML = ` 
         <div class="col-12 col-md-3">
            <div class="form-group">
               <select class="form-control" name="idrawmate[]" required>
                  <option value="">--Product--</option>
                  <?php
                    $query = "SELECT * FROM rawmate ORDER BY nmrawmate ASC";
                    $result = mysqli_query($conn, $query);
                    while ($row = mysqli_fetch_assoc($result)) {
                        echo '<option value="' . $row['idrawmate'] . '">' . $row['nmrawmate'] . '</option>';
                    }
                    ?>
               </select>
            </div>
         </div>
         <div class="col-6 col-md-1">
            <div class="form-group">
               <input type="text" name="weight[]" placeholder="Qty" class="form-control text-right" required>
            </div>
         </div>
         <div class="col-6 col-md-2">
            <div class="form-group">
               <input type="text" name="price[]" placeholder="Price" class="form-control text-right" required>
            </div>
         </div>
         <div class="col-6 col-md-2">
            <div class="form-group">
               <input type="text" name="amount[]" placeholder="Amount" class="form-control text-right" readonly>
            </div>
         </div>
         <div class="col-6 col-md-3">
            <div class="form-group">
               <input type="text" placeholder="Note" name="notes[]" class="form-control">
            </div>
         </div>
         <div class="col">
            <button type="button" class="btn btn-link text-danger" onclick="removeItem(this)">
               <i class="fas fa-minus-circle"></i>
            </button>
         </div>
      `;

        itemsContainer.appendChild(newItemRow);
    }

    // Fungsi untuk menghapus item
    function removeItem(button) {
        button.closest('.row').remove();
        calculateTotals();
    }

    // Format semua input saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        const weightInputs = document.querySelectorAll('[name="weight[]"]');
        const priceInputs = document.querySelectorAll('[name="price[]"]');

        // Format setiap input weight dan price
        weightInputs.forEach((input) => formatInputValue(input));
        priceInputs.forEach((input) => formatInputValue(input));

        // Hitung total saat halaman pertama kali dimuat
        calculateTotals();
    });
</script>

<?php include "../footer.php"; ?>