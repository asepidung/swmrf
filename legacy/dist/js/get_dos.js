
// Mendapatkan referensi ke elemen select dan customer select
const iddoSelect = document.getElementById("iddo");
const idcustomerSelect = document.getElementById("idcustomer");

// Mendefinisikan fungsi untuk mengisi opsi DO terkait
function fillDODropdown(customerId) {
   // Hapus semua opsi sebelumnya
   iddoSelect.innerHTML = '<option value="">Pilih DO Terkait</option>';

   // Lakukan permintaan AJAX ke server untuk mendapatkan data DO berdasarkan customer
   fetch(`get_dos.php?customer_id=${customerId}`)
      .then(response => response.json())
      .then(data => {
         // Isi opsi DO terkait berdasarkan data yang diterima dari server
         data.forEach(doData => {
            const option = document.createElement("option");
            option.value = doData.iddo;
            option.textContent = doData.donumber;
            iddoSelect.appendChild(option);
         });
      })
      .catch(error => {
         console.error("Error fetching DO data:", error);
      });
}

// Tambahkan event listener untuk perubahan pilihan pelanggan
idcustomerSelect.addEventListener("change", event => {
   const selectedCustomerId = event.target.value;
   if (selectedCustomerId) {
      fillDODropdown(selectedCustomerId);
   } else {
      // Kosongkan opsi DO jika pelanggan tidak dipilih
      iddoSelect.innerHTML = '<option value="">Pilih DO Terkait</option>';
   }
});
