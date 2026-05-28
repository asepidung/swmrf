<?php
require "../verifications/auth.php";
require "../konak/conn.php"; // Koneksi ke database

include "../header.php";
include "../navbar.php";
include "../mainsidebar.php";
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-12 col-md-4 mb-2">
                    <input type="text" class="form-control" name="track" id="track" placeholder="Masukkan kode barcode" autofocus>
                </div>
                <div class="col-12 col-md-2 mb-2">
                    <button class="btn btn-primary" id="btnSearch">Cari</button>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <table id="example1" class="table table-bordered table-striped table-sm">
                                <thead class="text-center">
                                    <tr>
                                        <th>#</th>
                                        <th>Proses</th> <!-- Nama tabel database -->
                                        <th>Waktu</th> <!-- Timestamp atau kosong jika tidak ada -->
                                        <th>Item</th> <!-- nmbarang dari tabel barang -->
                                        <th>Weight</th> <!-- qty atau weight -->
                                        <th>pod</th> <!-- pod -->
                                    </tr>
                                </thead>
                                <tbody id="resultTable">
                                    <tr>
                                        <td colspan="6" class="text-center">Masukkan kode untuk mencari</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        $("#btnSearch").click(function() {
            let barcode = $("#track").val().trim();
            if (barcode !== "") {
                $.ajax({
                    url: "search_history.php",
                    type: "POST",
                    data: {
                        search: barcode
                    },
                    success: function(data) {
                        $("#resultTable").html(data);
                    }
                });
            } else {
                $("#resultTable").html("<tr><td colspan='6' class='text-center'>Masukkan kode untuk mencari</td></tr>");
            }
        });
    });
</script>

<?php
include "../footer.php";
?>