{{-- Cetak label otomatis lewat iframe tersembunyi (dipakai Input Hasil Repack dan Labeling Boning). Label datang sebagai
     HTML di event 'auto-print', jadi tidak ada tab baru dan tidak ada permintaan halaman kedua. Bila HTML tidak ada,
     jatuh ke cara lama: membuka url di tab baru. --}}
<script>
    (function () {
        if (window.__autoPrintLabelReady) return;
        window.__autoPrintLabelReady = true;

        let antrean = Promise.resolve();

        function cetakSatu(html) {
            return new Promise((selesai) => {
                let frame = document.getElementById('label-print-frame');

                if (!frame) {
                    frame = document.createElement('iframe');
                    frame.id = 'label-print-frame';
                    frame.setAttribute('aria-hidden', 'true');
                    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
                    document.body.appendChild(frame);
                }

                frame.onload = () => {
                    frame.onload = null;
                    try {
                        frame.contentWindow.focus();
                        frame.contentWindow.print();
                    } catch (e) {}
                    selesai();
                };
                frame.srcdoc = html;
            });
        }

        document.addEventListener('auto-print', (event) => {
            const detail = event.detail || {};
            const htmls = detail.htmls || (detail[0] && detail[0].htmls) || null;
            const url = detail.url || (detail[0] && detail[0].url) || null;

            if (htmls && htmls.length) {
                htmls.forEach((html) => {
                    antrean = antrean.then(() => cetakSatu(html));
                });
            } else if (url) {
                window.open(url, '_blank');
            }
        });
    })();
</script>
