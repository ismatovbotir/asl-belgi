<?php

namespace App\Services\AslBelgisi\ProductRegistry;

use App\Models\Product;
use App\Models\Setting;
use App\Services\AslBelgisi\AslBelgisiClient;
use Illuminate\Support\Carbon;

class ProductRegistryService extends AslBelgisiClient
{
    private const ENDPOINT = '/public/api/v1/product-registry/product';

    /**
     * Sync products from the API into the local DB.
     * $productGroup: e.g. 'appliances' — pass null to skip that filter.
     */
    public function syncAll(?string $productGroup = null): int
    {
        $tin = (string) (Setting::get('aslbelgisi_tin') ?? config('aslbelgisi.tin', ''));

        $params = ['status' => 'PUBLISHED'];
        if ($tin)          $params['inn']          = $tin;
        if ($productGroup) $params['productGroup'] = $productGroup;

        // Response is a flat array of product objects (not paginated)
        $items = $this->businessRequest('GET', self::ENDPOINT, $params);

        if (! is_array($items)) {
            return 0;
        }

        // Some API versions wrap in { content: [...] }
        if (isset($items['content'])) {
            $items = $items['content'];
        }

        $synced = 0;
        $now    = Carbon::now();

        foreach ($items as $item) {
            $gtin = data_get($item, 'gtin');
            if (! $gtin) {
                continue;
            }

            $nameRu = data_get($item, 'productName.ru', '');
            $nameUz = data_get($item, 'productName.uz', '');

            Product::updateOrCreate(['gtin' => $gtin], [
                'external_id'        => data_get($item, 'id'),
                'name'               => $nameRu ?: $nameUz,
                'name_ru'            => $nameRu,
                'name_uz'            => $nameUz,
                'inn'                => data_get($item, 'inn'),
                'product_group'      => data_get($item, 'productGroup.name.ru'),
                'product_group_code' => data_get($item, 'productGroup.code'),
                'category'           => data_get($item, 'productCategory.name.ru'),
                'category_code'      => data_get($item, 'productCategory.code'),
                'tnved_code'         => data_get($item, 'tnved.code'),
                'package_type'       => data_get($item, 'packageType.code'),
                'brand'              => null,
                'attributes'         => $item,
                'synced_at'          => $now,
            ]);

            $synced++;
        }

        return $synced;
    }
}
