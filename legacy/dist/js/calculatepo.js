
function calculateTotals() {
   // Dapatkan semua elemen input yang diperlukan
   var weightInputs = document.getElementsByName("weight[]");
   var priceInputs = document.getElementsByName("price[]");
   var amountInputs = document.getElementsByName("amount[]");

   // Inisialisasi total berat (xweight) dan total jumlah (xamount)
   var totalWeight = 0;
   var totalAmount = 0;

   // Lakukan perhitungan untuk setiap baris item
   for (var i = 0; i < weightInputs.length; i++) {
      var weight = parseFloat(weightInputs[i].value.replace(",", ".")); // Ganti koma dengan titik untuk menghindari masalah parsing
      var price = parseFloat(priceInputs[i].value.replace(",", ".")); // Ganti koma dengan titik untuk menghindari masalah parsing

      // Perhitungan jumlah (amount)
      var amount = weight * price;

      // Tampilkan hasil perhitungan dengan format digit grouping (`,` sebagai pemisah digit dan `.` sebagai pemisah desimal)
      amountInputs[i].value = formatCurrency(amount);

      // Akumulasikan total berat (xweight) dan total jumlah (xamount)
      totalWeight += weight;
      totalAmount += amount;
   }

   // Tampilkan total berat (xweight) dan total jumlah (xamount) dengan format digit grouping
   document.getElementById("xweight").value = formatCurrency(totalWeight);
   document.getElementById("xamount").value = formatCurrency(totalAmount);

   // Aktifkan tombol "Submit" jika sudah ada item yang dihitung
   var submitButton = document.getElementById("submit-btn");
   submitButton.disabled = false;
}

// Fungsi untuk mengatur format digit grouping dan pemisah desimal
function formatCurrency(amount) {
   return amount.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}
