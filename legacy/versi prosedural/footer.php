<!-- REQUIRED SCRIPTS -->

<!-- jQuery -->
<script src="../plugins/jquery/jquery.min.js"></script>

<!-- Bootstrap 4 -->
<script src="../plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- DataTables -->
<script src="../plugins/datatables/jquery.dataTables.min.js"></script>
<script src="../plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
<script src="../plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
<script src="../plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>

<!-- DataTables Buttons -->
<script src="../plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>
<script src="../plugins/jszip/jszip.min.js"></script>
<script src="../plugins/pdfmake/pdfmake.min.js"></script>
<script src="../plugins/pdfmake/vfs_fonts.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.html5.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.print.min.js"></script>
<script src="../plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

<!-- Plugin tambahan -->
<script src="../plugins/select2/js/select2.full.min.js"></script>
<script src="../plugins/bootstrap4-duallistbox/jquery.bootstrap-duallistbox.min.js"></script>
<script src="../plugins/moment/moment.min.js"></script>
<script src="../plugins/inputmask/jquery.inputmask.min.js"></script>
<script src="../plugins/daterangepicker/daterangepicker.js"></script>
<script src="../plugins/bootstrap-colorpicker/js/bootstrap-colorpicker.min.js"></script>
<script src="../plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src="../plugins/bs-stepper/js/bs-stepper.min.js"></script>
<script src="../plugins/dropzone/min/dropzone.min.js"></script>

<script src="../plugins/chart.js/Chart.min.js"></script>

<!-- AdminLTE App -->
<script src="../dist/js/adminlte.min.js"></script>

<!-- ============================ -->
<!-- PAGE SPECIFIC SCRIPT -->
<!-- ============================ -->
<script>
   $(document).ready(function() {

      // ===== Session Checker =====
      setInterval(function() {
         $.ajax({
            url: "../verifications/session_checker.php",
            method: "GET",
            dataType: "json",
            success: function(response) {
               console.log("Session status:", response.status);
               if (response.status === "expired") {
                  alert("Sesi Anda telah berakhir. Silakan login kembali.");
                  window.location.href = "../verifications/login.php";
               }
            },
            error: function() {
               console.log("Gagal menghubungi server.");
            }
         });
      }, 10000);

      // ===== Select2 =====
      if ($('.select2').length) {
         $('.select2').select2({
            theme: 'bootstrap4'
         });
      }

      // ===== DataTables #example1 =====
      if ($('#example1').length) {
         $("#example1").DataTable({
            responsive: true,
            autoWidth: false,
            lengthChange: true,
            ordering: true,
            paging: true,
            searching: true,
            buttons: ["excel", "pdf", "print", "colvis"]
         }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
      }

      // ===== DataTables #example2 =====
      if ($('#example2').length) {
         $('#example2').DataTable({
            paging: true,
            lengthChange: false,
            searching: false,
            ordering: true,
            info: true,
            autoWidth: true,
            responsive: true,
         });
      }

      // ===== Date Range Picker =====
      if ($('.daterangepicker').length) {
         $('.daterangepicker').daterangepicker();
      }

      // ===== Input Mask =====
      if ($('[data-mask]').length) {
         $('[data-mask]').inputmask();
      }

      // ===== Color Picker =====
      if ($('.colorpicker').length) {
         $('.colorpicker').colorpicker();
      }

      // ===== Tempus Dominus (Datetime Picker) =====
      if ($('.datetimepicker').length) {
         $('.datetimepicker').datetimepicker({
            format: 'L'
         });
      }

      // ===== BS Stepper =====
      if (document.querySelector('.bs-stepper')) {
         window.stepper = new Stepper(document.querySelector('.bs-stepper'));
      }

      // ===== Dropzone =====
      if (typeof Dropzone !== 'undefined') {
         Dropzone.autoDiscover = false;
      }
   });
</script>

</body>

</html>