function calculateTotals() {
   var boxes = document.getElementsByName("box[]");
   var weights = document.getElementsByName("weight[]");
   var xbox = 0;
   var xweight = 0;

   for (var i = 0; i < boxes.length; i++) {
      xbox += parseInt(boxes[i].value) || 0;
      xweight += parseFloat(weights[i].value) || 0;
   }

   document.getElementById("xbox").value = xbox;
   document.getElementById("xweight").value = xweight.toFixed(2);

   // Aktifkan tombol Submit setelah mengklik Calculate
   document.getElementById("submit-btn").disabled = false;
}