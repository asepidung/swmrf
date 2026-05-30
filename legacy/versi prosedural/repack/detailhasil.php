<x-filament-panels::page>
   <style>
      /* 1. HIDE SIDEBAR & TOPBAR KHUSUS HALAMAN INI BIAR FULL SCREEN */
      aside.fi-sidebar {
         display: none !important;
      }

      .fi-topbar {
         display: none !important;
      }

      main.fi-main {
         padding-left: 0 !important;
         padding-top: 0 !important;
      }

      .fi-header {
         display: none !important;
      }

      /* Hide Header Bawaan */

      /* 2. GENCET TABEL HISTORY (TENGAH) BIAR SLIM */
      .fi-ta-table tbody tr {
         height: 32px !important;
      }

      .fi-ta-table th,
      .fi-ta-table td {
         padding: 4px 8px !important;
         font-size: 11px !important;
      }

      .fi-ta-cell>div,
      .fi-ta-text,
      .fi-ta-text-item {
         padding: 0 !important;
         margin: 0 !important;
         line-height: 1.1 !important;
      }

      .fi-ta-actions button {
         width: 22px !important;
         height: 22px !important;
      }

      /* 3. STYLE TABEL REKAP MINI (KANAN) MIRIP BOOTSTRAP LAMA LU */
      .table-mini {
         width: 100%;
         border-collapse: collapse;
         font-size: 11px;
         margin-bottom: 1.5rem;
      }

      .table-mini th {
         background-color: #f8fafc;
         padding: 6px;
         border: 1px solid #e2e8f0;
         text-align: center;
         font-weight: bold;
      }

      .table-mini td {
         padding: 6px;
         border: 1px solid #e2e8f0;
      }

      .dark .table-mini th {
         background-color: #1f2937;
         border-color: #374151;
      }

      .dark .table-mini td {
         border-color: #374151;
      }
   </style>

   <div class="p-2 md:p-4">

      <div class="flex items-center justify-between mb-4 pb-2 border-b dark:border-gray-800">
         <div class="flex gap-2">
            <x-filament::button href="{{ \App\Filament\Resources\RepackResource::getUrl('index') }}" tag="a" color="primary" variant="outlined" icon="heroicon-m-arrow-left">
               Kembali
            </x-filament::button>
            <x-filament::button href="{{ \App\Filament\Resources\RepackResource::getUrl('input-bahan', ['record' => $record->id]) }}" tag="a" color="success" variant="outlined" icon="heroicon-m-arrow-right">
               Tambah Bahan
            </x-filament::button>
         </div>
         <div>
            <h4 class="text-primary-600 font-bold text-xl m-0 uppercase tracking-wide">
               PRINT LABEL HASIL - {{ $record->document_no }}
            </h4>
         </div>
      </div>

      <div class="grid grid-cols-12 gap-4">

         <div class="col-span-12 md:col-span-3">
            <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4">
               <form wire:submit.prevent="create">
                  {{ $this->form }}
                  <div class="mt-5">
                     <x-filament::button type="submit" color="primary" class="w-full bg-gradient-to-r from-primary-600 to-primary-500" id="submit_btn_label">
                        PRINT LABEL
                     </x-filament::button>
                  </div>
               </form>
            </div>
         </div>

         <div class="col-span-12 md:col-span-6">
            <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl overflow-hidden h-full">
               {{ $this->table }}
            </div>
         </div>

         <div class="col-span-12 md:col-span-3">
            <div class="bg-white dark:bg-gray-900 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-4">

               <div class="font-bold mb-2 text-sm">BAHAN</div>
               <table class="table-mini">
                  <thead>
                     <tr>
                        <th class="text-left">NAMA BARANG</th>
                        <th>BOX</th>
                        <th class="text-right">QTY</th>
                     </tr>
                  </thead>
                  <tbody>
                     @php
                     $bahan = \App\Models\RepackMaterial::with('product')->where('repack_id', $record->id)->get();
                     $groupedBahan = $bahan->groupBy(fn($item) => $item->product->name ?? 'Unknown');
                     $totalBahanBox = 0;
                     $totalBahanQty = 0;
                     @endphp
                     @forelse($groupedBahan as $name => $items)
                     @php
                     $box = $items->count();
                     $qty = $items->sum('weight');
                     $totalBahanBox += $box;
                     $totalBahanQty += $qty;
                     @endphp
                     <tr>
                        <td>{{ $name }}</td>
                        <td class="text-center">{{ $box }}</td>
                        <td class="text-right">{{ number_format($qty, 2) }}</td>
                     </tr>
                     @empty
                     <tr>
                        <td colspan="3" class="text-center italic text-gray-500 py-3">Belum ada bahan</td>
                     </tr>
                     @endforelse
                  </tbody>
                  <tfoot class="font-bold bg-gray-50 dark:bg-gray-800">
                     <tr>
                        <td class="text-right py-2">TOTAL</td>
                        <td class="text-center py-2">{{ $totalBahanBox }}</td>
                        <td class="text-right py-2">{{ number_format($totalBahanQty, 2) }}</td>
                     </tr>
                  </tfoot>
               </table>

               <div class="font-bold mb-2 text-sm mt-4">HASIL</div>
               <table class="table-mini">
                  <thead>
                     <tr>
                        <th class="text-left">NAMA BARANG</th>
                        <th>BOX</th>
                        <th class="text-right">QTY</th>
                     </tr>
                  </thead>
                  <tbody>
                     @php
                     $hasil = \App\Models\RepackResult::with('product')->where('repack_id', $record->id)->get();
                     $groupedHasil = $hasil->groupBy(fn($item) => $item->product->name);
                     $totalHasilBox = 0;
                     $totalHasilQty = 0;
                     @endphp
                     @forelse($groupedHasil as $name => $items)
                     @php
                     $box = $items->count();
                     $qty = $items->sum('weight');
                     $totalHasilBox += $box;
                     $totalHasilQty += $qty;
                     @endphp
                     <tr>
                        <td>{{ $name }}</td>
                        <td class="text-center">{{ $box }}</td>
                        <td class="text-right">{{ number_format($qty, 2) }}</td>
                     </tr>
                     @empty
                     <tr>
                        <td colspan="3" class="text-center italic text-gray-500 py-3">Belum ada hasil</td>
                     </tr>
                     @endforelse
                  </tbody>
                  <tfoot class="font-bold bg-gray-50 dark:bg-gray-800">
                     <tr>
                        <td class="text-right py-2">TOTAL</td>
                        <td class="text-center py-2">{{ $totalHasilBox }}</td>
                        <td class="text-right py-2">{{ number_format($totalHasilQty, 2) }}</td>
                     </tr>
                  </tfoot>
               </table>

            </div>
         </div>

      </div>
   </div>

   <script>
      function focusInput() {
         setTimeout(() => {
            const qtyInput = document.getElementById('qty_input_field');
            if (qtyInput) qtyInput.focus();
         }, 100);
      }
      document.addEventListener('DOMContentLoaded', focusInput);
      window.addEventListener('auto-print', event => {
         if (event.detail.url) window.open(event.detail.url, '_blank');
         focusInput();
      });
      document.addEventListener('refreshTable', focusInput);
   </script>
</x-filament-panels::page>