function calculateAmounts() {
   var weights = document.getElementsByName('weight[]');
   var prices = document.getElementsByName('price[]');
   var discounts = document.getElementsByName('discount[]');
   var discountrp = document.getElementsByName('discountrp[]');
   var amounts = document.getElementsByName('amount[]');

   var weightsrm = document.getElementsByName('weightrm[]');
   var pricesrm = document.getElementsByName('pricerm[]');
   var discountsrm = document.getElementsByName('discountrm[]');
   var discountrprm = document.getElementsByName('discountrprm[]');
   var amountsrm = document.getElementsByName('amountrm[]');

   var totalWeight = 0;
   var totalAmount = 0;
   var totalDiscount = 0;

   // Menghitung total berat, menghitung discountrp, dan menghitung amounts
   for (var i = 0; i < weights.length; i++) {
      var weight = parseFloat(weights[i].value);
      var price = parseFloat(prices[i].value);
      var discount = parseFloat(discounts[i].value);

      if (!isNaN(weight) && !isNaN(price)) {
         var discountrpValue = (weight * price) * (discount / 100);
         discountrp[i].value = discountrpValue.toFixed(2);

         var amount = (weight * price) - discountrpValue;
         amounts[i].value = amount.toFixed(2);

         totalWeight += weight;
         totalAmount += amount;
         totalDiscount += discountrpValue;
      }
   }

   // Menghitung total berat dan total amount dari kedua set elemen
   for (var i = 0; i < weightsrm.length; i++) {
      var weightrm = parseFloat(weightsrm[i].value);
      var pricerm = parseFloat(pricesrm[i].value);
      var discountrm = parseFloat(discountsrm[i].value);

      if (!isNaN(weightrm) && !isNaN(pricerm)) {
         var discountrpmValue = (weightrm * pricerm) * (discountrm / 100);
         discountrprm[i].value = discountrpmValue.toFixed(2);

         var amountrm = (weightrm * pricerm) - discountrpmValue;
         amountsrm[i].value = amountrm.toFixed(2);

         totalWeight += weightrm;
         totalAmount += amountrm;
         totalDiscount += discountrpmValue;
      }
   }

   // Log totalAmount before calculating tax
   console.log("Total Amount (before calculating tax): " + totalAmount);

   // ... (sisa kode tetap sama seperti sebelumnya)

   // Log totalAmount before calculating tax
   console.log("Total Amount (before calculating tax): " + totalAmount);

   // Log totalAmount before calculating tax
   console.log("Total Amount (before calculating tax): " + totalAmount);

   var pajak = "<?= $pajak ?>";
   var taxElement = document.getElementById('tax');
   var taxAmount = 0;

   if (pajak === 'YES') {
      var taxPercentage = 0.11; // Persentase pajak 11%
      taxAmount = totalAmount * taxPercentage; // Menggunakan totalAmount bukan balance
   }

   taxElement.value = taxAmount.toFixed(2);

   // Calculate balance
   var charge = parseFloat(document.getElementById('charge').value);
   var downPayment = parseFloat(document.getElementById('downpayment').value);
   var balanceElement = document.getElementById('balance');
   var balance = totalAmount + taxAmount + charge - downPayment;

   // Update the relevant fields with the calculated values
   document.getElementById('xamount').value = parseFloat(totalAmount.toFixed(2)).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
   });
   document.getElementById('xweight').value = totalWeight.toFixed(2);
   taxElement.value = taxAmount.toFixed(2);
   balanceElement.value = balance.toFixed(2);

   // Mengubah format discountrp[]
   for (var i = 0; i < discountrp.length; i++) {
      discountrp[i].value = parseFloat(discountrp[i].value).toLocaleString(undefined, {
         minimumFractionDigits: 2,
         maximumFractionDigits: 2
      });
   }

   // Mengubah format amounts[]
   for (var i = 0; i < amounts.length; i++) {
      amounts[i].value = parseFloat(amounts[i].value).toLocaleString(undefined, {
         minimumFractionDigits: 2,
         maximumFractionDigits: 2
      });
   }

   // Hapus toLocaleString() agar nilai taxAmount tetap angka, bukan string dengan tanda koma
   taxElement.value = taxAmount;

   // Mengubah format balance
   balanceElement.value = parseFloat(balance.toFixed(2)).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
   });

   // Mengubah format xdiscount
   document.getElementById('xdiscount').value = parseFloat(totalDiscount.toFixed(2)).toLocaleString(undefined, {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
   });
   document.getElementById('submit-btn').removeAttribute('disabled');
}