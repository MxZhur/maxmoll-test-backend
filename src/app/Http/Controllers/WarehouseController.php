<?php

namespace App\Http\Controllers;

use App\Http\Resources\WarehouseCollection;
use App\Models\Warehouse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    /**
     * Просмореть список складов
     **/
    public function index(Request $request)
    {
        $perPage = $request->query('per_page');

        // Выбрать список складов с сортировкой по алфавиту
        // и пагинацией на случай, если складов будет много
        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->paginate($perPage);

        return (new WarehouseCollection($warehouses))->resolve();
    }
}
