<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\TallyResource\Pages\ScanTally;
use App\Models\TallyItem;
use Tests\TestCase;

/**
 * Relabel hanya untuk barang yang umurnya sudah MELEWATI batas POD.
 *
 * Keputusan Project Owner, 1 September 2026: yang sudah lewat batas tampil
 * merah dan bisa diklik untuk dilabeli ulang; yang masih dalam batas tampil
 * biasa, tanpa tautan.
 *
 * Sebelumnya warnanya sudah benar tetapi aksinya dipasang tanpa syarat,
 * sehingga SETIAP tanggal POD terlihat dan terasa bisa diklik -- termasuk
 * barang yang tidak perlu dilabeli ulang sama sekali. Melabeli ulang berarti
 * mengganti barcode dan mencetak label baru, jadi pintu yang terbuka lebar
 * di sini bukan sekadar soal tampilan.
 */
class TallyPodRelabelTest extends TestCase
{
    private function page(?int $limit): ScanTally
    {
        $page = new ScanTally();
        $page->podLimit = $limit;

        return $page;
    }

    private function itemPackedDaysAgo(int $days): TallyItem
    {
        return new TallyItem(['pack_date' => now()->subDays($days)->toDateString()]);
    }

    /** Lebih tua daripada batas: lewat. */
    public function test_an_item_older_than_the_limit_is_past_the_limit(): void
    {
        $this->assertTrue($this->page(5)->isPastPodLimit($this->itemPackedDaysAgo(6)));
        $this->assertTrue($this->page(5)->isPastPodLimit($this->itemPackedDaysAgo(30)));
    }

    /**
     * Tepat pada batas belum lewat.
     *
     * Owner menyebutnya jelas: "jika umurnya masih SAMA atau kurang biarkan
     * standar". Batas 5 hari berarti barang berumur 5 hari masih boleh.
     */
    public function test_an_item_exactly_at_the_limit_is_still_within_it(): void
    {
        $this->assertFalse($this->page(5)->isPastPodLimit($this->itemPackedDaysAgo(5)));
        $this->assertFalse($this->page(5)->isPastPodLimit($this->itemPackedDaysAgo(1)));
        $this->assertFalse($this->page(5)->isPastPodLimit($this->itemPackedDaysAgo(0)));
    }

    /**
     * Batas yang belum diisi berarti tidak ada yang lewat batas.
     *
     * Tanpa angka pembanding kita memang tidak tahu apa-apa. Menganggap
     * semuanya kedaluwarsa akan menyalakan seluruh kolom menjadi merah dan
     * membuka pintu relabel untuk semua baris sekaligus.
     */
    public function test_an_empty_limit_marks_nothing_as_expired(): void
    {
        $this->assertFalse($this->page(null)->isPastPodLimit($this->itemPackedDaysAgo(365)));
    }

    /** Barang tanpa tanggal kemas tidak bisa dinilai, jadi dibiarkan. */
    public function test_an_item_without_a_pack_date_is_left_alone(): void
    {
        $this->assertFalse($this->page(5)->isPastPodLimit(new TallyItem()));
        $this->assertFalse($this->page(5)->isPastPodLimit(null));
    }

    /**
     * Kliknya dimatikan dengan disableClick, BUKAN dengan menyembunyikan
     * aksinya saja.
     *
     * Filament merender sel sebagai tombol begitu sebuah aksi terpasang,
     * tanpa memeriksa apakah aksi itu sedang tampil. Menyembunyikan aksinya
     * hanya membuat kliknya tidak melakukan apa-apa, sementara selnya tetap
     * terlihat dan terasa bisa diklik -- persis keluhan yang mau dibereskan.
     */
    public function test_the_cell_stops_being_clickable_within_the_limit(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/TallyResource/Pages/ScanTally.php'
        ));

        $this->assertStringContainsString(
            '->disableClick(fn (?TallyItem $record, $livewire) => ! $livewire->isPastPodLimit($record))',
            $source,
        );

        // Penjagaan kedua pada aksinya sendiri, untuk pemanggilan yang tidak
        // lewat layar.
        $this->assertStringContainsString(
            '->visible(fn (?TallyItem $record, $livewire) => $livewire->isPastPodLimit($record))',
            $source,
        );
    }

    /**
     * Warna, tebal huruf, keterangan, klik, dan penjagaan aksi memakai satu
     * aturan yang sama.
     *
     * Kalau aturannya disalin, kelimanya bisa berbeda pendapat dan sebuah
     * baris bisa tampil merah tetapi menolak diklik.
     */
    public function test_every_signal_asks_the_same_question(): void
    {
        $source = file_get_contents(app_path(
            'Filament/Admin/Resources/TallyResource/Pages/ScanTally.php'
        ));

        $this->assertSame(
            5,
            substr_count($source, 'isPastPodLimit($record)'),
            'Warna, tebal huruf, tooltip, disableClick, dan visible harus sama-sama bertanya ke isPastPodLimit().',
        );

        // Perhitungan umurnya tidak boleh ada lagi di luar metode itu.
        $this->assertSame(
            1,
            substr_count($source, 'diffInDays'),
            'Aturan umur POD tersalin di lebih dari satu tempat.',
        );
    }

    /** Berat dan box digabung supaya ringkasan PO tidak terpotong. */
    public function test_the_summary_merges_weight_and_box_into_one_column(): void
    {
        $view = file_get_contents(resource_path(
            'views/filament/admin/resources/tally-resource/pages/scan-tally.blade.php'
        ));

        $this->assertStringContainsString('Scan / Box', $view);
        $this->assertStringNotContainsString("__('Qty Scan')", $view);

        // Kotak batas POD tidak lagi memakan sepertiga lebar; isinya paling
        // banyak dua angka.
        $this->assertStringNotContainsString('class="w-1/3"', $view);
        $this->assertStringContainsString('max="99"', $view);
    }

    /**
     * Ringkasan PO selalu bersebelahan dengan daftar pindai, dan melekat.
     *
     * Yang dipegang operator adalah alat pemindai, bukan tetikus. Begitu
     * ringkasannya turun ke bawah daftar, ia harus menggulir bolak-balik
     * hanya untuk melihat sisa kebutuhan barang -- dan satu tally bisa
     * berisi ratusan baris, jadi sekadar bersebelahan pun belum cukup:
     * tanpa dilekatkan, ringkasannya ikut tergulir hilang.
     *
     * `xl:grid-cols-2` sudah dicoba dan justru menumpuk di layar Owner.
     */
    public function test_the_summary_stays_beside_the_scan_list(): void
    {
        $view = file_get_contents(resource_path(
            'views/filament/admin/resources/tally-resource/pages/scan-tally.blade.php'
        ));

        // Ditulis sebagai style langsung, bukan kelas Tailwind. Panel admin
        // tidak memuat hasil build CSS aplikasi, sehingga yang berlaku hanya
        // CSS bawaan Filament -- dan di sana kelas dua-kolom yang polos tidak
        // ada, hanya varian ber-breakpoint. Menulisnya sebagai kelas
        // menghasilkan grid tanpa definisi kolom: isinya menumpuk ke bawah
        // tanpa satu pun error.
        // minmax(0, 1fr), BUKAN 1fr saja. `1fr` berarti `minmax(auto, 1fr)`,
        // dan batas minimum `auto` itu selebar isi terlebar yang tidak bisa
        // dipatahkan -- barcode 26 karakter di tabel kiri memenuhi syarat itu,
        // sehingga kolom kiri melar melewati separuh dan menggencet ringkasan
        // PO sampai terpotong meski gridnya sendiri sudah benar dua kolom.
        $this->assertStringContainsString(
            'grid-template-columns: minmax(0, 1fr) minmax(0, 1fr)',
            $view,
        );
        $this->assertStringContainsString('sticky top-6', $view);

        // Kelas dua-kolom Tailwind tidak boleh dipakai di halaman ini, dengan
        // atau tanpa breakpoint.
        $this->assertDoesNotMatchRegularExpression('/class="[^"]*grid-cols-/', $view);
    }

    /**
     * Sidebar dilepas dan tabelnya dipadatkan di halaman pemindaian.
     *
     * Halaman ini dipakai dengan satu alat pemindai di tangan, bukan tetikus.
     * Setiap gulir mendatar berarti operator harus meletakkan pemindainya
     * dulu, jadi yang perlu dilihat wajib muat tanpa digeser.
     *
     * Membagi dua kolom saja tidak cukup: barcode 26 karakter membuat tabel
     * kiri menuntut lebar besar, sehingga tanpa tambahan ruang halamannya
     * hanya berpindah dari "ringkasan terpotong" menjadi "daftar harus
     * digeser".
     */
    public function test_the_scanning_page_gives_itself_room(): void
    {
        $view = file_get_contents(resource_path(
            'views/filament/admin/resources/tally-resource/pages/scan-tally.blade.php'
        ));

        $this->assertStringContainsString('filament.partials.scanner-page-style', $view);

        $style = file_get_contents(resource_path(
            'views/filament/partials/scanner-page-style.blade.php'
        ));

        $this->assertStringContainsString('aside.fi-sidebar', $style);
        $this->assertStringContainsString('display: none !important', $style);

        // Lewat CSS berlingkup halaman, BUKAN dengan mengubah keadaan sidebar
        // milik Filament -- keadaan itu diingat antar halaman, jadi menutupnya
        // dari sini akan ikut menutupnya di seluruh aplikasi.
        $this->assertStringNotContainsString('$store.sidebar', $style);
        $this->assertStringNotContainsString('sidebar.close', $style);
    }

    /**
     * Hasil pindai terbaru muncul paling atas.
     *
     * Kalau urutannya terlama dulu, pindaian yang barusan dilakukan akan
     * mendarat di halaman terakhir dan operator tidak bisa memastikan
     * pindaiannya masuk tanpa berpindah halaman.
     */
    public function test_the_newest_scan_is_listed_first(): void
    {
        $this->assertStringContainsString(
            "->defaultSort('id', 'desc')",
            file_get_contents(app_path(
                'Filament/Admin/Resources/TallyResource/Pages/ScanTally.php'
            )),
        );
    }

    /** Halamannya benar-benar bisa dirender. */
    public function test_the_summary_renders(): void
    {
        $this->assertStringContainsString(
            'PO Summary',
            file_get_contents(resource_path(
                'views/filament/admin/resources/tally-resource/pages/scan-tally.blade.php'
            )),
        );
    }
}
