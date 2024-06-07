<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Http\Requests\Order\StoreRequest;
use App\Http\Requests\Order\UpdateRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Получить список заказов с учётом фильтров и пагинации
     **/
    public function index(Request $request)
    {
        $perPage = $request->query('per_page');

        $filterParams = $request->query('filter', []);

        // Выбрать список заказов с обратной сортировкой по дате создания
        // и с фильтрацией по различным полям:
        $ordersQuery = Order::query();

        // - ФИО покупателя
        if (!empty($filterParams['customer'])) {
            $ordersQuery->where('customer', 'like', '%' . $filterParams['customer'] . '%');
        }

        // - Статус
        if (!empty($filterParams['status'])) {
            $ordersQuery->where('status', $filterParams['status']);
        }

        // - ID склада
        if (!empty($filterParams['warehouse'])) {
            $ordersQuery->where('warehouse_id', $filterParams['warehouse']);
        }

        $orders = $ordersQuery
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return OrderResource::collection($orders)->resolve();
    }


    /**
     * Создать заказ
     *
     * @param StoreRequest $request
     * @param OrderService $orderService
     * @return OrderResource
     * @throws \Throwable
     **/
    public function store(StoreRequest $request, OrderService $orderService)
    {
        $data = $request->validated();

        // Добавить заказ и его элементы в рамках транзакции
        try {
            DB::beginTransaction();

            $order = Order::create([
                'customer' => $data['customer'],
                'created_at' => Carbon::now(),
                'warehouse_id' => $data['warehouse_id'],
                'status' => OrderStatus::ACTIVE,
            ]);

            foreach ($data['products'] as $productData) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $productData['id'],
                    'count' => $productData['count'],
                ]);

                $orderService->writeOffAllFromWarehouse($order);
            }

            DB::commit();

            return (new OrderResource($order))->resolve();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }


    /**
     * Обновить заказ
     *
     * @param int $id
     * @param UpdateRequest $request
     * @param OrderService $orderService
     * @return OrderResource
     * @throws \Throwable
     **/
    public function update(int $id, UpdateRequest $request, OrderService $orderService)
    {
        try {
            $order = Order::findOrFail($id);
        } catch (\Throwable $th) {
            throw new HttpResponseException(
                response()->json([
                    'error' => 'Entity not found.'
                ], 404)
            );
        }

        $data = $request->validated();

        try {
            DB::beginTransaction();

            $orderService->updateOrderItems($order, $data['products']);

            $order->customer = $data['customer'];
            $order->save();

            DB::commit();

            $order->refresh();

            return (new OrderResource($order))->resolve();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }


    /**
     * Завершить заказ
     *
     * @param int $id ID заказа
     * @return OrderResource
     **/
    public function complete(int $id, OrderService $orderService)
    {
        try {
            $order = Order::findOrFail($id);
        } catch (\Throwable $th) {
            throw new HttpResponseException(
                response()->json([
                    'error' => 'Entity not found.'
                ], 404)
            );
        }

        try {
            DB::beginTransaction();

            if ($order->status !== OrderStatus::COMPLETED) {
                // Списать товар со склада,
                // если статус изменён с отменённого сразу на завершённый
                if ($order->status === OrderStatus::CANCELLED) {
                    $orderService->writeOffAllFromWarehouse($order);
                }

                $order->status = OrderStatus::COMPLETED;
                $order->completed_at = Carbon::now();

                $order->save();
            }

            DB::commit();

            $order->refresh();

            return (new OrderResource($order))->resolve();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }


    /**
     * Отменить заказ
     *
     * @param int $id ID заказа
     * @return OrderResource
     **/
    public function cancel(int $id, OrderService $orderService)
    {
        try {
            $order = Order::findOrFail($id);
        } catch (\Throwable $th) {
            throw new HttpResponseException(
                response()->json([
                    'error' => 'Entity not found.'
                ], 404)
            );
        }

        if ($order->status !== OrderStatus::CANCELLED) {
            // Вернуть товар на склад
            $orderService->returnAllToWarehouse($order);

            $order->status = OrderStatus::CANCELLED;
            $order->completed_at = null;

            $order->save();
        }

        $order->refresh();

        return (new OrderResource($order))->resolve();
    }


    /**
     * Возобновить заказ
     *
     * @param int $id ID заказа
     * @return OrderResource
     **/
    public function restore(int $id, OrderService $orderService)
    {
        try {
            $order = Order::findOrFail($id);
        } catch (\Throwable $th) {
            throw new HttpResponseException(
                response()->json([
                    'error' => 'Entity not found.'
                ], 404)
            );
        }

        try {
            DB::beginTransaction();

            if ($order->status !== OrderStatus::ACTIVE) {
                // Списать товар со склада
                if ($order->status === OrderStatus::CANCELLED) {
                    $orderService->writeOffAllFromWarehouse($order);
                }

                $order->status = OrderStatus::ACTIVE;
                $order->completed_at = null;

                $order->save();
            }

            DB::commit();

            $order->refresh();

            return (new OrderResource($order))->resolve();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
