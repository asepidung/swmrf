<script>
    // Dipasang SEKALI saja.
    //
    // Dengan navigasi Livewire, berkas ini ikut dirender setiap kali
    // halamannya dibuka. Tanpa penjagaan ini pendengarnya bertambah satu
    // setiap kunjungan -- sesudah lima kali membuka halaman, satu tekan Enter
    // dijalankan lima kali, dan kursornya melompat lima kolom.
    if (! window.__stockTakeEnterBound) {
        window.__stockTakeEnterBound = true;

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            const activeElement = document.activeElement;
            // Check if active element is a text input inside a table body cell
            if (activeElement && activeElement.tagName === 'INPUT' && activeElement.type === 'text' && activeElement.closest('tbody')) {
                e.preventDefault();
                const inputs = Array.from(document.querySelectorAll('tbody input[type="text"]:not([disabled])'));
                const index = inputs.indexOf(activeElement);
                if (index > -1 && index < inputs.length - 1) {
                    activeElement.blur(); // Trigger Livewire save
                    // Small delay to allow blur event to process
                    setTimeout(() => {
                        inputs[index + 1].focus();
                        inputs[index + 1].select();
                    }, 50);
                }
            }
        }
    });

    }
</script>
