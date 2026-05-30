// Fungsi untuk mengisi select menu "Code" dan "Product" pada baris baru
function fillSelectMenusForRow(row) {
   // Isi select menu idgrade
   var idgradeSelect = row.querySelector('.idgrade-placeholder');
   var xhrIdGrade = new XMLHttpRequest();
   xhrIdGrade.onreadystatechange = function () {
      if (xhrIdGrade.readyState === XMLHttpRequest.DONE) {
         if (xhrIdGrade.status === 200) {
            idgradeSelect.innerHTML = xhrIdGrade.responseText;
         } else {
            idgradeSelect.innerHTML = '<option value="">Failed to load data</option>';
         }
      }
   };
   xhrIdGrade.open('GET', 'get_grade_options.php', true);
   xhrIdGrade.send();

   // Isi select menu idbarang
   var idbarangSelect = row.querySelector('.idbarang-placeholder');
   var xhrIdBarang = new XMLHttpRequest();
   xhrIdBarang.onreadystatechange = function () {
      if (xhrIdBarang.readyState === XMLHttpRequest.DONE) {
         if (xhrIdBarang.status === 200) {
            idbarangSelect.innerHTML = xhrIdBarang.responseText;
         } else {
            idbarangSelect.innerHTML = '<option value="">Failed to load data</option>';
         }
      }
   };
   xhrIdBarang.open('GET', 'get_barang_options.php', true);
   xhrIdBarang.send();
}

function addItem() {
   var itemsContainer = document.getElementById('items-container');

   var newRow = document.createElement('div');
   newRow.className = 'row';

   newRow.innerHTML = `
      <div class="col-1">
         <div class="form-group">
            <div class="input-group">
               <select class="form-control idgrade-placeholder" name="idgrade[]">
                  <option value="">Loading...</option>
               </select>
            </div>
         </div>
      </div>
      <div class="col-4">
         <div class="form-group">
            <div class="input-group">
               <select class="form-control idbarang-placeholder" name="idbarang[]" required>
                  <option value="">Loading...</option>
               </select>
            </div>
         </div>
      </div>
      <div class="col-1">
         <div class="form-group">
            <div class="input-group">
               <input type="text" name="box[]" class="form-control text-center" required onkeydown="moveFocusToNextInput(event, this, 'box[]')">
            </div>
         </div>
      </div>
      <div class="col-2">
         <div class="form-group">
            <div class="input-group">
               <input type="text" name="weight[]" class="form-control text-right" required onkeydown="moveFocusToNextInput(event, this, 'weight[]')">
            </div>
         </div>
      </div>
      <div class="col-3">
         <div class="form-group">
            <div class="input-group">
               <input type="text" name="notes[]" class="form-control">
            </div>
         </div>
      </div>
      <div class="col">
         <button type="button" class="btn btn-link text-danger" onclick="removeItem(this)"><i class="fas fa-minus-circle"></i></button>
      </div>
   `;

   itemsContainer.appendChild(newRow);

   // Isi data ke select menu setelah baris ditambahkan
   fillSelectMenusForRow(newRow);
}

function removeItem(button) {
   var rowToRemove = button.parentNode.parentNode;
   var itemsContainer = document.getElementById('items-container');
   itemsContainer.removeChild(rowToRemove);
}
