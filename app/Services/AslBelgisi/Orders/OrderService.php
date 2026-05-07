<?php

namespace App\Services\AslBelgisi\Orders;

use App\Models\KmCode;
use App\Models\KmOrder;
use App\Models\KmOrderItem;
use App\Services\AslBelgisi\AslBelgisiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService extends AslBelgisiClient
{
    // Verify paths against your API spec (OMS = Order Management System)
    private const ORDERS_ENDPOINT = '/public/api/v1/oms/orders';

    public function syncFromApi(): int
    {
        $page   = 0;
        $size   = 50;
        $synced = 0;

        do {
            $response   = $this->businessRequest('GET', self::ORDERS_ENDPOINT, ['page' => $page, 'size' => $size]);
            $orders     = data_get($response, 'orders', data_get($response, 'content', $response));
            $totalPages = data_get($response, 'totalPages', 1);

            if (! is_array($orders) || empty($orders)) {
                break;
            }

            foreach ($orders as $orderData) {
                $this->upsertOrder($orderData);
                $synced++;
            }

            $page++;
        } while ($page < $totalPages);

        return $synced;
    }

    private function upsertOrder(array $data): KmOrder
    {
        $orderId = data_get($data, 'orderId') ?? data_get($data, 'id', '');

        $order = KmOrder::updateOrCreate(
            ['external_order_id' => $orderId],
            [
                'product_group'       => data_get($data, 'productGroup') ?? data_get($data, 'productGroupId'),
                'release_method_type' => data_get($data, 'releaseMethodType'),
                'status'              => $this->mapStatus(data_get($data, 'status', 'PENDING')),
                'raw_data'            => $data,
            ]
        );

        // Fetch and sync buffers for this order
        try {
            $buffers = $this->fetchBuffers($orderId);
            $this->syncBuffers($order, $buffers);
        } catch (\Throwable $e) {
            Log::channel('aslbelgisi')->warning("Could not fetch buffers for order {$orderId}: " . $e->getMessage());
        }

        return $order;
    }

    public function fetchBuffers(string $orderId): array
    {
        $response = $this->businessRequest('GET', self::ORDERS_ENDPOINT . "/{$orderId}/buffers");
        return data_get($response, 'buffers', $response);
    }

    private function syncBuffers(KmOrder $order, array $buffers): void
    {
        $totalRequested = 0;

        foreach ($buffers as $buffer) {
            $bufferId = data_get($buffer, 'bufferId') ?? data_get($buffer, 'id');
            $qty      = (int) (data_get($buffer, 'quantity') ?? data_get($buffer, 'totalCount', 0));
            $status   = $this->mapBufferStatus(data_get($buffer, 'status', 'PENDING'));

            KmOrderItem::updateOrCreate(
                ['km_order_id' => $order->id, 'buffer_id' => $bufferId],
                [
                    'gtin'     => data_get($buffer, 'gtin'),
                    'quantity' => $qty,
                    'status'   => $status,
                    'raw_data' => $buffer,
                ]
            );

            $totalRequested += $qty;
        }

        $order->update(['total_codes_requested' => $totalRequested]);
    }

    public function downloadCodes(KmOrder $order, KmOrderItem $item): int
    {
        if ($item->status === 'DOWNLOADED') {
            return $item->codes_downloaded;
        }

        $path = self::ORDERS_ENDPOINT . "/{$order->external_order_id}/buffers/{$item->buffer_id}/codes";

        // API may return codes as JSON array or newline-separated text
        $response = $this->businessRequest('GET', $path);

        // Handle array response or codes key
        $codes = is_array($response)
            ? (isset($response[0]) ? $response : data_get($response, 'codes', []))
            : [];

        // If codes are strings directly
        if (is_string($response)) {
            $codes = array_filter(explode("\n", trim($response)));
        }

        $count = 0;
        DB::transaction(function () use ($order, $item, $codes, &$count) {
            foreach ($codes as $code) {
                $codeStr = is_array($code) ? data_get($code, 'code', json_encode($code)) : (string) $code;
                if (empty(trim($codeStr))) {
                    continue;
                }

                KmCode::create([
                    'km_order_id'      => $order->id,
                    'km_order_item_id' => $item->id,
                    'code'             => trim($codeStr),
                    'status'           => 'available',
                ]);
                $count++;
            }

            $item->update([
                'status'           => 'DOWNLOADED',
                'codes_downloaded' => $count,
            ]);
        });

        // Update order totals
        $order->refresh();
        $downloaded = $order->items()->where('status', 'DOWNLOADED')->sum('codes_downloaded');
        $allDone    = $order->items()->whereNotIn('status', ['DOWNLOADED', 'CLOSED', 'DEPLETED'])->doesntExist();

        $order->update([
            'total_codes_downloaded' => $downloaded,
            'status'                 => $allDone ? 'DOWNLOADED' : $order->status,
        ]);

        Log::channel('aslbelgisi')->info("Downloaded {$count} codes for order {$order->external_order_id} buffer {$item->buffer_id}");

        return $count;
    }

    public function importFromCsv(\Illuminate\Http\UploadedFile $file): int
    {
        $name  = str_replace('_', ' ', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $lines = explode("\n", str_replace("\r", '', $file->get()));

        $parsed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (strlen($line) >= 18 && str_starts_with($line, '01')) {
                $parsed[] = $this->parseDataMatrix($line);
            }
        }

        if (empty($parsed)) {
            throw new \InvalidArgumentException('No valid marking codes found in the file.');
        }

        // Group parsed codes by GTIN → one KmOrderItem per GTIN
        $groups = [];
        foreach ($parsed as $item) {
            $groups[$item['gtin']][] = $item;
        }

        $total = count($parsed);

        DB::transaction(function () use ($name, $groups, $total) {
            $order = KmOrder::create([
                'name'                   => $name,
                'status'                 => 'DOWNLOADED',
                'total_codes_requested'  => $total,
                'total_codes_downloaded' => $total,
            ]);

            foreach ($groups as $gtin => $items) {
                $orderItem = KmOrderItem::create([
                    'km_order_id'      => $order->id,
                    'gtin'             => $gtin,
                    'quantity'         => count($items),
                    'status'           => 'DOWNLOADED',
                    'codes_downloaded' => count($items),
                ]);

                foreach (array_chunk($items, 500) as $chunk) {
                    KmCode::insert(array_map(fn($p) => [
                        'km_order_id'      => $order->id,
                        'km_order_item_id' => $orderItem->id,
                        'code'             => $p['code'],
                        'cis'              => $p['cis'],
                        'gtin'             => $p['gtin'],
                        'serial_number'    => $p['serial'],
                        'expiry_date'      => $p['expiry'],
                        'batch'            => $p['batch'],
                        'status'           => 'available',
                    ], $chunk));
                }
            }
        });

        return $total;
    }

    /**
     * Parse a GS1 DataMatrix code into its Application Identifier components.
     * Handles GS (\x1D) separators for variable-length AIs.
     */
    private function parseDataMatrix(string $raw): array
    {
        $cis    = str_replace("\x1D", '', $raw);
        $gtin   = null;
        $serial = null;
        $expiry = null;
        $batch  = null;

        $pos = 0;
        $len = strlen($raw);

        while ($pos < $len - 1) {
            $ai = substr($raw, $pos, 2);

            switch ($ai) {
                case '01': // GTIN-14, fixed 14 chars
                    $gtin = substr($raw, $pos + 2, 14);
                    $pos += 16;
                    break;

                case '21': // Serial number, variable length, GS terminated
                    $pos += 2;
                    $gs     = strpos($raw, "\x1D", $pos);
                    $end    = $gs !== false ? $gs : $len;
                    $serial = substr($raw, $pos, $end - $pos);
                    $pos    = $gs !== false ? $gs + 1 : $len;
                    break;

                case '17': // Expiry date YYMMDD, fixed 6 chars
                    $yymmdd = substr($raw, $pos + 2, 6);
                    $expiry = '20' . substr($yymmdd, 0, 2) . '-'
                            . substr($yymmdd, 2, 2) . '-'
                            . substr($yymmdd, 4, 2);
                    $pos += 8;
                    break;

                case '10': // Batch/lot, variable length, GS terminated
                    $pos  += 2;
                    $gs    = strpos($raw, "\x1D", $pos);
                    $end   = $gs !== false ? $gs : $len;
                    $batch = substr($raw, $pos, $end - $pos);
                    $pos   = $gs !== false ? $gs + 1 : $len;
                    break;

                default:
                    $pos = $len; // Unknown AI — stop parsing
            }
        }

        return compact('raw', 'cis', 'gtin', 'serial', 'expiry', 'batch')
            + ['code' => $raw];
    }

    public function refreshOrderStatus(KmOrder $order): void
    {
        if ($order->isDone()) {
            return; // never re-poll completed orders
        }

        try {
            $data    = $this->businessRequest('GET', self::ORDERS_ENDPOINT . "/{$order->external_order_id}");
            $status  = $this->mapStatus(data_get($data, 'status', $order->status));
            $buffers = $this->fetchBuffers($order->external_order_id);
            $this->syncBuffers($order, $buffers);
            $order->update(['status' => $status, 'raw_data' => $data]);
        } catch (\Throwable $e) {
            Log::channel('aslbelgisi')->error("Status refresh failed for order {$order->external_order_id}: " . $e->getMessage());
        }
    }

    private function mapStatus(string $apiStatus): string
    {
        return match (strtoupper($apiStatus)) {
            'CREATED', 'IN_PROGRESS', 'PROCESSING' => 'PENDING',
            'READY', 'COMPLETED'                    => 'READY',
            'CLOSED', 'EXPIRED'                     => 'CLOSED',
            default                                 => strtoupper($apiStatus),
        };
    }

    private function mapBufferStatus(string $apiStatus): string
    {
        return match (strtoupper($apiStatus)) {
            'CREATED', 'IN_PROGRESS', 'PROCESSING' => 'PENDING',
            'READY', 'AVAILABLE'                    => 'READY',
            'EXHAUSTED', 'DEPLETED'                 => 'DEPLETED',
            'CLOSED', 'EXPIRED'                     => 'CLOSED',
            default                                 => strtoupper($apiStatus),
        };
    }
}
