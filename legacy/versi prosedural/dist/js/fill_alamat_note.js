function fillAlamatNoteOptions() {
   var selectedCustomerId = document.getElementById('idcustomer').value;
   var alamatSelect = document.getElementById('alamat');
   var noteInput = document.getElementById('note');

   alamatSelect.innerHTML = '<option value="">Memuat Alamat...</option>';
   noteInput.value = 'Memuat Catatan...';

   var xhr = new XMLHttpRequest();
   xhr.onreadystatechange = function () {
      if (xhr.readyState === XMLHttpRequest.DONE) {
         if (xhr.status === 200) {
            var responseData = JSON.parse(xhr.responseText);

            alamatSelect.innerHTML = responseData.alamatOptions;
            noteInput.value = responseData.catatan;
         } else {
            alamatSelect.innerHTML = '<option value="">Gagal Memuat Alamat</option>';
            noteInput.value = 'Gagal Memuat Catatan';
         }
      }
   };
   xhr.open('POST', 'get_alamat_note.php', true);
   xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
   xhr.send('idcustomer=' + encodeURIComponent(selectedCustomerId));
}

document.getElementById('idcustomer').addEventListener('change', fillAlamatNoteOptions);
fillAlamatNoteOptions();