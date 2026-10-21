<?php

namespace App\Filament\Admin\Resources\CustomerProductSpecResource\Pages;

use App\Filament\Admin\Resources\CustomerProductSpecResource;
use App\Models\CustomerProductSpec;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateCustomerProductSpec extends CreateRecord
{
    protected static string $resource = CustomerProductSpecResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /** Satu customer, banyak produk: tiap produk menjadi satu baris spek tetap. */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $last = null;

            foreach ($data['items'] ?? [] as $item) {
                $last = CustomerProductSpec::create(
                    ['customer_id' => $data['customer_id'], 'product_id' => $item['product_id']]
                    + array_intersect_key($item, array_flip(CustomerProductSpec::FIELDS))
                );
            }

            return $last;
        });
    }
}
