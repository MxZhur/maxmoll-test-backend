<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Stock;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    /**
     * Списать со склада все товары из заказа
     *
     * @param Order $order Объект заказа
     * @return void
     * @throws HttpResponseException
     **/
    public function writeOffAllFromWarehouse(Order $order)
    {
        // Сначала проверяем все позиции на наличие на складе
        // и выдаём ошибку, если хотя бы одного товара не хватает...

        $stockComparison = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('stocks', 'orders.warehouse_id', '=', 'stocks.warehouse_id')
            ->select('order_items.product_id', 'order_items.count', 'stocks.stock')
            ->where('order_items.order_id', $order->id)
            ->whereColumn('order_items.count', '>', 'stocks.stock')
            ->get();

        if (!$stockComparison->isEmpty()) {
            throw new HttpResponseException(response()->json([
                'error' => 'Unable to write off products from the warehouse: some products are out of stocks.',
            ], 400));
        }

        // ... и только потом - списываем

        /*
            NOTE: По идее, можно было бы попробовать сделать update одним запросом через join,
            но это оказалось сложнее на практике.
        */

        $orderItems = $order->orderItems;

        $stocks = Stock::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->whereIn('product_id', $order->orderItems()->select('product_id'))
            ->get();

        foreach ($orderItems as $orderItem) {
            $stock = $stocks->first(function ($stock) use ($orderItem) {
                return $orderItem->product_id === $stock->product_id;
            });

            // Если товара нет в таблице stocks, значит, его нет в наличии. Возвращаем ошибку.

            if ($stock === null) {
                throw new HttpResponseException(response()->json([
                    'error' => 'Unable to write off products from the warehouse: some products are out of stocks.',
                ], 400));
            }

            $stock->stock -= $orderItem->count;
            $stock->save();
        }
    }


    /**
     * Обновить товары заказа
     *
     * @param Order $order Объект заказа
     * @return void
     * @throws HttpResponseException
     **/
    public function updateOrderItems(Order $order, array $updatedProductsData)
    {
        $orderItems = $order->orderItems;

        $oldProductsData = array_map(function ($item) {
            return [
                'id' => $item['product_id'],
                'count' => $item['count'],
            ];
        }, $orderItems->toArray());

        // Заполнить новый набор товаров "нулевыми" остатками удалённых товаров из старого набора.
        // Это понадобится в будущем для определения, какие OrderItem-ы нужно удалить из заказа.

        foreach ($oldProductsData as $oldProduct) {
            $updatedProducts = array_filter($updatedProductsData, function ($item) use ($oldProduct) {
                return $item['id'] === $oldProduct['id'];
            });

            $productIsDeleted = empty($updatedProducts);

            if ($productIsDeleted) {
                $updatedProductsData[] = [
                    'id' => $oldProduct['id'],
                    'count' => 0,
                ];
            }
        }

        // Вычислить разность между старым и новым набором товаров

        $productDiffs = [];

        foreach ($updatedProductsData as $updatedProduct) {
            $oldProducts = array_filter($oldProductsData, function ($item) use ($updatedProduct) {
                return $item['id'] === $updatedProduct['id'];
            });

            if (empty($oldProducts)) {
                $productDiffs[] = [
                    'id' => $updatedProduct['id'],
                    'diff' => $updatedProduct['count'],
                ];
            } else {
                $productDiffs[] = [
                    'id' => $updatedProduct['id'],
                    'diff' => $updatedProduct['count'] - $oldProducts[0]['count'],
                ];
            }
        }

        $productDiffs = array_filter($productDiffs, function ($item) {
            return $item['diff'] !== 0;
        });

        // - Вернуть удаляемые товары на склад
        // - Списать добавляемые товары со склада (с проверкой наличия)
        // - Обновить существующие товары (списать либо вернуть на склад, с проверкой наличия)

        foreach ($productDiffs as $productDiff) {
            $stock = Stock::query()
                ->where('warehouse_id', $order->warehouse_id)
                ->where('product_id', $productDiff['id'])
                ->first();

            if ($productDiff < 0) {

                // Если товара нет в таблице stocks, значит, его надо добавить.
                // Иначе - просто прибавляем к уже существующему остатку.

                if ($stock === null) {
                    $stock = Stock::create([
                        'product_id' => $productDiff['id'],
                        'warehouse_id' => $order->warehouse_id,
                        'stock' => $productDiff['diff'],
                    ]);
                } else {
                    $stock->stock -= $productDiff['diff'];
                    $stock->save();
                }
            } else if ($productDiff > 0) {
                // Если товара нет в таблице stocks, значит, его нет в наличии. Возвращаем ошибку.

                if ($stock === null) {
                    throw new HttpResponseException(response()->json([
                        'error' => 'Unable to write off products from the warehouse: some products are out of stocks.',
                    ], 400));
                }

                if ($stock->stock - $productDiff['diff'] < 0) {
                    throw new HttpResponseException(response()->json([
                        'error' => 'Unable to write off products from the warehouse: some products are out of stocks.',
                    ], 400));
                }

                $stock->stock -= $productDiff['diff'];
                $stock->save();
            }
        }

        // Обновить набор товаров в заказе

        foreach ($updatedProductsData as $updatedProduct) {
            $orderItem = OrderItem::query()
                ->where('order_id', $order->id)
                ->where('product_id', $updatedProduct['id'])
                ->first();

            if ($updatedProduct['count'] > 0) {
                if ($orderItem === null) {
                    // Создать позицию товара
                    $orderItem = OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $updatedProduct['id'],
                        'count' => $updatedProduct['count'],
                    ]);
                } else {
                    // Обновить позицию товара
                    $orderItem->count = $updatedProduct['count'];
                    $orderItem->save();
                }
            } else {
                // Удалить позицию товара
                if ($orderItem !== null) {
                    $orderItem->delete();
                }
            }
        }
    }


    /**
     * Вернуть на склад все товары из заказа
     *
     * @param Order $order Объект заказа
     * @return void
     **/
    public function returnAllToWarehouse(Order $order)
    {
        $orderItems = $order->orderItems;

        $stocks = Stock::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->whereIn('product_id', $order->orderItems()->select('product_id'))
            ->get();

        foreach ($orderItems as $orderItem) {
            $stock = $stocks->first(function ($stock) use ($orderItem) {
                return $orderItem->product_id === $stock->product_id;
            });

            // Если товара нет в таблице stocks, значит, его надо добавить.
            // Иначе - просто прибавляем к уже существующему остатку.

            if ($stock === null) {
                $stock = Stock::create([
                    'product_id' => $orderItem->product_id,
                    'warehouse_id' => $order->warehouse_id,
                    'stock' => $orderItem->count,
                ]);
            } else {
                $stock->stock += $orderItem->count;
                $stock->save();
            }
        }
    }
}
