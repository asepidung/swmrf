# Basis data legacy produksi -- hasil jelajah 18 September 2026

Dibaca langsung dari server produksi (`u525862761_swm`, 90 tabel), **hanya
baca**, lewat PHP di server yang memakai `konak/conn.php` -- kredensial tidak
pernah keluar dari server dan tidak boleh disalin ke mana pun. Ditulis sebagai
bahan pertimbangan sesi import master data (sesi khusus Owner + Hafizh).
Data transaksi berjalan sejak **1 November 2023** dan masih hidup (tally
terakhir 18 Sep 2026 pagi).

## Fakta yang mengubah asumsi sebelumnya

- **Price list legacy ADA tetapi tidak berguna untuk HPP**: 3 daftar
  (ASEP OFFAL 15 baris, JAMAL 2, YADI 16) -- bukan LION/HYPERMART. Jadi
  `hpp.md` bagian 15 butir 4 tetap berlaku: harga per grup harus diketik dari
  kolom GROSS PRICE costing.
- **`groupcs.terms` kosong untuk seluruh 54 grup** -- trading terms LION 6% /
  HYPERMART 16,45% tidak tercatat di legacy, harus diisi tangan.
- **Grup pelanggan legacy = hampir satu grup per pelanggan** (54 grup, 51
  dipakai, 304 customer): LION, HPM (= Hypermart), REGULER, UNGROUP, SAMPLE,
  dan nama-nama hotel/restoran. Hanya **1 customer tanpa grup**. Migrasi
  `customer_group_id` wajib (#406) akan mulus.
- `segment` (4 baris) di legacy = **rekening bank penagihan** (BCA, BCA PT,
  BNI, BNI PT), bukan segmen pasar. Jangan dipetakan mentah ke
  `customer_segments` swmrf tanpa dibaca ulang artinya.
- `barang` 158; **113 punya BOM** (`bom_rawmate` 417 baris), 45 tidak.
  `bom_rawmate.qty` bilangan, `is_active` ada -- cocok dengan
  `product_materials` (#344) yang qty-nya nullable untuk "tidak tetap".
- `rawmate` 225 material, `unit` teks bebas (Ikat, Box, ...), `barmin` =
  stok minimum, `stock` tersimpan di master (yang di swmrf sengaja tidak).
- `grade`: J01 J02 J03 P01 P02 P03 (6). `cuts` 7: PRIME CUT, SECONDARY CUT,
  BONES, OFFAL, FAT, PROCESSING, MANUFACTURING -- ini kandidat
  `product_categories`.
- `stock` 586 baris = barcode yang sedang ada di gudang (punya `price`).
  `labelboning` 174.677 baris = seluruh label yang pernah dicetak.
- `customers` menyimpan bendera sertifikasi per pelanggan: `nkv, halal, sv,
  joss, phd, ujilab`, plus `tukarfaktur`, `top`, `pajak`, `sales_referensi`.
- `role` legacy = 12 kolom boolean per modul per user (20 user) -- jauh lebih
  kasar dari izin swmrf; tidak bisa dipetakan otomatis, Owner mencentang ulang.
- `returjual` 35 dokumen / 130 baris sejak 2023 -- retur jual memang jarang.
- `pricelist.up` dan `groupcs.terms` ada kolomnya tetapi tidak dipakai.

## Kandidat urutan import (master saja, transaksi TIDAK diimpor)

1. `cuts` -> `product_categories`; `grade` -> `grades` (cocokkan nama yang
   sudah ada, jangan dobel).
2. `barang` -> `products` (`kdbarang`, `nmbarang`, `karton/drylog/plastik`
   adalah petunjuk BOM lama per produk).
3. `rawcategory` -> `material_categories`; `rawmate` -> `materials` (+ unit
   dipetakan ke `material_units`, `barmin` -> min_stock; **stok tidak
   diimpor**, stok awal lewat opname).
4. `bom_rawmate` -> `product_materials` (417 baris; `is_active=0` dilewati).
5. `groupcs` -> `customer_groups`; `customers` -> `customers` (segment perlu
   keputusan; bendera sertifikasi perlu kolom tujuan atau dilewati).
6. `supplier` -> `suppliers`; `cattle_class` -> `cattle_classes`.
7. Stok awal daging: **bukan** dari `stock`, melainkan opname fisik di
   swmrf -- 586 barcode lama bisa jadi daftar bantu saat opname.

## Daftar tabel (nama | baris | kolom)

- `adjustment` (56): idadjustment,noadjustment,tgladjustment,eventadjustment,xweight,idusers,creatime
- `adjustmentdetail` (347): idadjustmentdetail,idadjustment,idgrade,idbarang,weight,notes
- `barang` (158): idbarang,kdbarang,kodeinduk,nmbarang,iduser,idcut,karton,drylog,plastik
- `bom_rawmate` (417): idbom,idbarang,idrawmate,qty,is_active,iduser,createtime,updatetime
- `boning` (451): idboning,batchboning,idsupplier,tglboning,qtysapi,dibuat,iduser,keterangan,kunci,is_deleted
- `carcase` (315): idcarcase,killdate,idsupplier,idweight,note,idusers,is_deleted
- `carcasedetail` (7682): iddetail,idcarcase,idweightdetail,breed,berat,eartag,carcase1,carcase2,hides,tail
- `cattle_class` (7): idclass,class_name,created_at,updated_at
- `cattle_loss_receive` (103): idloss,idreceive,idweigh,loss_no,loss_date,note,total_receive_weight,total_actual_weight,total_loss_weight,total_loss_cost,is_deleted,creatime,createby,updatetime,updateby
- `cattle_loss_receive_detail` (3734): idlossdetail,idloss,idreceivedetail,idweighdetail,eartag,cattle_class,receive_weight,actual_weight,loss_weight,price_perkg,loss_cost,notes,creatime,createby,updatetime,updateby
- `cattle_receive` (105): idreceive,idpo,receipt_date,doc_no,sv_ok,skkh_ok,note,is_deleted,creatime,createby,updatetime,updateby
- `cattle_receive_detail` (3848): idreceivedetail,idreceive,eartag,weight,class,rfid,notes,creatime,createby,updatetime,updateby
- `customers` (304): idcustomer,nama_customer,alamat1,idsegment,top,sales_referensi,pajak,telepon,email,catatan,tanggal_update,tukarfaktur,alamat2,alamat3,idgroup,invoice,nkv,halal,sv,joss,phd,ujilab
- `cuts` (7): idcut,nmcut
- `delivery_plan_detail` (743): id,idso,idcustomer,deliverydate,driver,armada,loadtime,note,created_at
- `detailbahan` (29213): iddetailbahan,idrepack,barcode,idbarang,idgrade,qty,pcs,pod,origin,creatime
- `detailhasil` (36876): iddetailhasil,idrepack,kdbarcode,idbarang,idgrade,qty,pcs,ph,packdate,exp,creatime,note,is_deleted
- `do` (8600): iddo,donumber,idcustomer,idso,idtally,po,deliverydate,driver,plat,note,sealnumb,status,xbox,xweight,idusers,created,rweight,is_deleted
- `dodetail` (23183): iddodetail,iddo,idbarang,box,weight,notes
- `doreceipt` (8552): iddoreceipt,iddo,donumber,deliverydate,idcustomer,po,driver,plat,note,status,xbox,xweight,idusers,created,alamat,idso,is_deleted
- `doreceiptdetail` (23091): iddoreceiptdetail,iddoreceipt,idbarang,box,weight,notes
- `gr` (121): idgr,idpo,grnumber,receivedate,idsupplier,idnumber,note,iduser,creatime,is_deleted
- `grade` (6): idgrade,nmgrade
- `grbeef` (178): idgr,idpo,grnumber,receivedate,idsupplier,note,suppcode,idusers,creatime,is_deleted
- `grbeefdetail` (1053): idgrbeefdetail,idgr,idbarang,idgrade,kdbarcode,orderqty,qty,pcs,pod,creatime,is_deleted
- `grdetail` (258): idgrdetail,idgr,idgrade,idbarang,kdbarcode,pcs,qty,pod,creatime,is_deleted
- `groupcs` (54): idgroup,nmgroup,terms
- `grraw` (161): idgr,idpo,grnumber,receivedate,idsupplier,suppcode,note,idusers,creatime,is_deleted
- `grrawdetail` (537): idgrrawdetail,idgr,idrawmate,orderqty,qty,creatime,idtransaksi,is_deleted
- `inbound` (110): idinbound,noinbound,tglinbound,xweight,xbox,note,proses,idusers,creatime
- `inbounddetail` (241): idinbounddetail,idinbound,idgrade,idbarang,box,weight,notes
- `invoice` (8523): idinvoice,noinvoice,iddoreceipt,top,duedate,status,tgltf,idsegment,invoice_date,idcustomer,pocustomer,donumber,note,xweight,xamount,xdiscount,tax,charge,downpayment,balance,creatime,is_deleted
- `invoicedetail` (22967): idinvoicedetail,idinvoice,idbarang,weight,price,discount,discountrp,amount
- `labelboning` (174677): idlabelboning,idboning,idbarang,qty,pcs,ph,packdate,exp,kdbarcode,creatime,iduser,idgrade,is_deleted
- `logactivity` (80157): idlog,iduser,event,docnumb,waktu
- `manualstock` (83): idmanual,idst,kdbarcode,idgrade,idbarang,qty,pcs,pod,origin,timescan,ph
- `missing_stock` (430): idmissing,idst,kdbarcode,idgrade,idbarang,qty,pcs,pod,origin,created_at
- `monitoring_produksi` (587): idmonitoring,idso,idcustomer,status_qc,catatan_qc,created_at,updated_at,is_deleted
- `monitoring_produksidetail` (1447): idmonitoringdetail,idmonitoring,idbarang,weight,notes
- `mutasi` (176): idmutasi,nomutasi,tglmutasi,driver,nopol,gudang,note,creatime,idusers,is_deleted
- `mutasidetail` (4179): idmutasidetail,idmutasi,kdbarcode,idbarang,idgrade,qty,pcs,pod,creatime
- `outbound` (100): idoutbound,nooutbound,tgloutbound,xweight,xbox,note,proses,idusers,creatime
- `outbounddetail` (157): idoutbounddetail,idoutbound,idgrade,idbarang,box,weight,notes
- `piutang` (7959): idpiutang,idgroup,idinvoice,idcustomer
- `plandev` (6563): idplandev,plandelivery,idcustomer,weight,driver_name,armada,loadtime,note,idso
- `po` (349): idpo,nopo,idrequest,idsupplier,xamount,taxrp,tax,duedate,note,top,is_deleted,creatime,stat
- `pobeef` (195): idpo,nopo,idrequest,idsupplier,xamount,taxrp,tax,duedate,note,is_deleted,creatime,top,stat
- `pobeefdetail` (227): idpodetail,idpo,idbarang,qty,price,subtotal,notes
- `pocattle` (147): idpo,nopo,podate,arrival_date,idsupplier,note,creatime,createby,updatetime,updateby,is_deleted
- `pocattledetail` (238): idpodetail,idpo,class,qty,price,notes,creatime,createby,updatetime,updateby,is_deleted
- `podetail` (944): idpodetail,idpo,idrawmate,qty,price,subtotal,notes
- `pomaterial` (17): idpomaterial,nopomaterial,idsupplier,tglpomaterial,deliveryat,Terms,note,stat,idusers,creatime,is_deleted
- `pomaterialdetail` (37): idpomaterialdetail,idpomaterial,idrawmate,qty,price,amount,notes
- `poproduct` (72): idpoproduct,nopoproduct,idsupplier,tglpoproduct,deliveryat,xweight,xamount,Terms,note,stat,idusers,creatime,is_deleted
- `poproductdetail` (74): idpoproductdetail,idpoproduct,idbarang,qty,price,amount,notes
- `pricelist` (3): idpricelist,idgroup,latestupdate,up,note,idusers,creatime
- `pricelistdetail` (33): idpricelistdetail,idpricelist,idbarang,price,notes
- `production_requests` (1): idrequest,idboning,idusers,spesifications,creatime,is_deleted
- `production_request_items` (1): iddetail,idrequest,idbarang,qty,satuan,notes
- `quotes` (10): idquote,isiquote,idusers,created_at
- `rawcategory` (28): idrawcategory,nmcategory
- `rawmate` (225): idrawmate,kdrawmate,nmrawmate,iduser,idrawcategory,stock,unit,barmin
- `raw_stock_out` (107): idstockout,nostockout,tgl,kegiatan,ref_id,ref_no,kegiatan_note,idusers,createtime,updatetime,is_deleted
- `raw_stock_out_detail` (1225): iddetail,idstockout,idrawmate,qty,note
- `raw_usage` (388): idusage,sumber,idsumber,idrawmate,qty,note,iduser,createtime
- `relabel` (6422): idrelabel,idbarang,qty,pcs,packdate,exp,kdbarcode,dibuat,iduser,idgrade,ph,xpcs,xpackdate
- `repack` (1223): idrepack,norepack,tglrepack,note,idusers,is_deleted,dibuat,kunci,donumber
- `request` (388): idrequest,norequest,duedate,iduser,idsupplier,note,stat,is_deleted,xamount,taxrp,creatime,tax,top
- `requestbeef` (192): idrequest,norequest,duedate,iduser,idsupplier,note,stat,is_deleted,xamount,taxrp,creatime,tax,top
- `requestbeefdetail` (224): iddetail,idrequest,idbarang,qty,price,notes
- `requestdetail` (1014): iddetail,idrequest,idrawmate,qty,price,notes
- `returjual` (35): idreturjual,returnnumber,returdate,idcustomer,note,idusers,creatime,is_deleted,status,donumber
- `returjualdetail` (130): idreturjualdetail,idreturjual,idgrade,idbarang,kdbarcode,qty,pcs,pod,creatime,is_deleted,ph
- `role` (20): idrole,idusers,produksi,warehouse,distributions,purchase_module,sales,finance,data_report,master_data,stock,cattle,qc
- `salesorder` (8159): idso,sonumber,idcustomer,deliverydate,po,alamat,note,progress,idusers,creatime,is_deleted
- `salesorderdetail` (21933): idsodetail,idso,idbarang,weight,price,discount,notes
- `segment` (4): idsegment,nmsegment,banksegment,accname,accnumber
- `stock` (586): id,kdbarcode,idgrade,idbarang,qty,pcs,ph,pod,origin,creatime,price
- `stockin` (205): id,kdbarcode,idgrade,idbarang,qty,pcs,pod,origin,is_deleted,creatime,ph
- `stockraw` (690): id,idtransaksi,idrawmate,qty,creatime,is_deleted
- `stocktake` (32): idst,nost,tglst,note,creatime,stocked
- `stocktakedetail` (23082): idstdetail,idst,kdbarcode,idgrade,idbarang,qty,pcs,pod,origin,timescan,ph
- `supplier` (43): idsupplier,nmsupplier,jenis_usaha,alamat,telepon,npwp,iduser
- `tally` (8251): idtally,idso,sonumber,notally,deliverydate,idcustomer,po,stat,sealnumb,creatime,is_deleted
- `tallydetail` (163568): idtallydetail,idtally,barcode,idgrade,ph,idbarang,weight,pcs,pod,origin,creatime,exp
- `trading` (95): idtrading,idbarang,qty,pcs,packdate,exp,kdbarcode,dibuat,iduser,idgrade
- `usage_other` (1): idother,noother,tgl,note,idusers,createtime
- `users` (20): idusers,userid,passuser,fullname,status
- `weight_cattle` (105): idweigh,idreceive,weigh_no,weigh_date,idweigher,note,is_deleted,creatime,createby,updatetime,updateby
- `weight_cattle_detail` (3837): idweighdetail,idweigh,idreceivedetail,eartag,weight,notes,creatime,createby,updatetime,updateby

## Jelajah lanjutan, 18 September 2026 malam

Data mentah (daftar barang, material, BOM, customer, supplier, harga per grup)
ada di scratchpad Hafizh, **sengaja tidak dimasukkan repo** -- repositori ini
publik dan isinya nama pelanggan serta harga. Minta ke Hafizh saat sesi import.

### Material legacy = katalog belanja, bukan hanya bahan produksi
`rawcategory` 28 kategori; yang benar-benar bahan produksi/kemasan: KARTON (13),
PLASTIK (20), LABEL (5), MIKA (2), STYROFOAM (6), TRAY (2), PACKAGING SUPPORT
(8), MEAT PROCESSING (85), LAKBAN (1), PRODUKSI (8). Sisanya belanja
operasional: ATK (16), INTERNET (2), MAINTENANCE (2), SPAREPART MOBIL (4),
PERAWATAN ARMADA (5), PERCETAKAN (10), LAIN-LAIN (31), dan 9 kategori kosong.
**Konsekuensi:** (a) import `materials` harus disaring per kategori, bukan
semua 225; (b) kategori operasional itu adalah daftar kategori awal yang
bagus untuk modul **Expense** (#453). `unit` NULL pada 100 dari 225 material;
yang terisi: Pcs 39, Box 25, Pack 19, Kg 15, Ikat 14, Set 13.

### Grup pelanggan yang hidup (SO sejak Jun 2026)
REGULER 141 SO / 79 customer, UNGROUP 64 / 23, GRAND LUCKY 51 / 12, AEON 44 /
12, ABUBA 32 / 32, **LION 32 SO / 28 toko**, EL ASADOR 22, MIKE PIZZA 10.
**HPM (Hypermart) 31 toko tetapi hanya 8 baris SO dalam 3 bulan** -- volume
kecil sekarang. REGULER dan UNGROUP adalah dua grup "harga umum" yang harus
dilebur jadi satu pengertian di swmrf (nullable = harga umum, `hpp.md` §10).

**Penegasan Owner, 18 Sep 2026:** di swmrf **semua customer akan bergrup**,
tanpa kecuali. Selain grup pelanggan komersial akan ada grup **UMUM**, grup
**KARYAWAN**, dan grup **WARGA** (masing-masing dengan harga sendiri untuk
transaksi penjualan). Ini menguatkan #406 (`customer_group_id` wajib) dan
batasan `hpp.md` §9: grup KARYAWAN/WARGA **tidak boleh** dipakai sebagai acuan
costing -- acuan HPP hanya LION, HYPERMART, atau harga umum.

### Diskon & trading terms TIDAK ada di legacy
`salesorderdetail.discount` = 0 di seluruh 3 bulan terakhir, semua grup.
Diskon di invoice hanya LION ~2,04% (itu diskon Lion DC yang sudah ada di
swmrf) dan AEON 0,23%. **Trading terms 6% / 16,45% tidak pernah masuk sistem
legacy** -- hidup hanya di spreadsheet costing. Harus diisi tangan di
`customer_groups`.

### Harga per grup bisa diturunkan dari SO 3 bulan terakhir
Untuk LION, HPM, REGULER tersedia min/max/harga terakhir per produk (89 baris,
di scratchpad). Ini kandidat sumber `price_list_items` awal selain kolom
GROSS PRICE costing -- keduanya perlu dicocokkan Owner.

### Nilai status legacy (untuk memahami data, bukan untuk diimpor)
- `tally.stat`: DO 7.881, kosong/NULL 356, Rejected 11, Approved 3.
- `do.status`: Invoiced 8.359, Unapproved 228, Rejected 10, Approved 3.
- `salesorder.progress`: Delivered 8.013, Waiting 95, Cancel 20, On Delivery
  15, Rejected 10, DRAFT 4, On Process 2.
- `invoice.status`: "-" 4.983, Sudah TF 3.474, Belum TF 66 (tukar faktur).
- `returjual.status`: POSTED 33, DRAFT 2.
- `stock.origin`: 1 = 378, 3 = 189, 2 = 11, 7 = 5, 6 = 3 (digit asal barcode;
  cocokkan dengan konstanta origin swmrf sebelum opname awal).

### Jebakan data yang terlihat
- **Panjang barcode tidak seragam**: 18, 19, dan 20 digit bercampur di
  `stock` (375 baris 19 digit awalan 1; 152 baris 20 digit awalan 3). Pemindai
  swmrf yang memvalidasi panjang tetap akan menolak sebagian barcode lama saat
  opname awal -- putuskan: relabel semua, atau longgarkan validasi untuk
  barcode lama.
- `customers.top` berisi nilai sampah: -1, 0, 2147483647, di samping 1..30.
  Import harus membersihkan (NULL bila di luar 0..60).
- `customers.pajak` dan `tukarfaktur` = 0 untuk semua 304 customer (tidak
  pernah dipakai); bendera sertifikasi terisi sedikit (nkv 14, halal 15, sv 5,
  joss 3, phd 4, ujilab 5) -- swmrf belum punya kolomnya; putuskan diimpor ke
  catatan atau dilewati.
- `segment` = rekening penagihan (BCA/BNI, pribadi/PT) -- padanannya di swmrf
  adalah `bank_accounts` + pilihan rekening di invoice, bukan
  `customer_segments`.
- `supplier` 43 baris bercampur supplier sapi, material, dan jasa; `jenis_usaha`
  teks bebas -- swmrf memisahkan supplier per jenis lewat PO, jadi cukup impor
  apa adanya.

## Perintah `legacy:import-master` -- hasil dry-run pertama, 19 September 2026

Perintahnya ada (#472, #473): `php artisan legacy:import-master --source=<dump.sql>`
(dry-run; `--apply` untuk menulis). Sumber: snapshot master produksi 19 Sep
di `legacy/versi prosedural/konak/SWM-master-2026-09-19.sql` (gitignored).
Dry-run terhadap DB lokal Hafizh:

| Target | dibuat | dilewati | konflik | Yang harus diputuskan di sesi berdua |
|---|---|---|---|---|
| product_categories | 4 | 1 | 2 | dua kategori legacy yang nama-nya bertabrakan dengan yang sudah ada |
| grades | 6 | 0 | 0 | -- |
| suppliers | 42 | 0 | 3 | duplikat beda spasi CV. SAMUDERA KARUNIA RIZKY; NPWP tidak punya kolom |
| cattle_classes | 4 | 3 | 0 | -- |
| products | 59 | 3 | 96 | 9 barang tanpa kode legacy (STYROFOAM, ICE GELL, DELIVERY COST, LUNG, ...) -- sebagian bukan produk daging; sisanya periksa di laporan |
| material_categories | 9 | 1 | 0 | -- |
| materials | 89 | 2 | 59 | 59 material MEAT PROCESSING tanpa satuan (bumbu, tepung, saus) -- satuan harus ditetapkan |
| product_materials (BOM) | 141 | 0 | 276 | basis box vs pcs untuk PLASTIK/LABEL/MIKA (ditandai TINJAU), sisanya baris is_active=0 / produk yang dilewati |

Total konflik 436 -- itulah agenda sesi import: bukan menulis kode lagi,
melainkan memutuskan satu per satu dari laporan dry-run. Customers, grup,
segment, dan barcode stok TIDAK termasuk perintah ini (sengaja).

