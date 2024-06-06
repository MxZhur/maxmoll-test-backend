<?php

namespace App\Http\Controllers;

use App\Http\Resources\StockHistoryEntryResource;
use App\Models\StockHistoryEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StockHistoryController extends Controller
{
    /**
     * Просмотреть историю движения товаров
     **/
    public function index(Request $request)
    {
        $perPage = $request->query('per_page');
        $filter = $request->query('filter');

        $historyEntriesQuery = StockHistoryEntry::query();

        // Фильтр по товару
        if (!empty($filter['product'])) {
            $historyEntriesQuery->where('product_id', $filter['product']);
        }

        // Фильтр по складу
        if (!empty($filter['warehouse'])) {
            $historyEntriesQuery->where('warehouse_id', $filter['warehouse']);
        }

        // Фильтр для просмотра записей за день
        if (!empty($filter['date'])) {
            $historyEntriesQuery->whereBetween(
                'date',
                [$filter['date'], Carbon::parse($filter['date'])->addDay()]
            );
        }

        // Фильтр по датам "от/до"
        if (!empty($filter['date_since'])) {
            $historyEntriesQuery->where('date', '>=', $filter['date_since']);
        }

        if (!empty($filter['date_until'])) {
            $historyEntriesQuery->where('date', '<=', Carbon::parse($filter['date_until'])->addDay());
        }

        // Обратная сортировка по дате (сначала новые записи) и пагинация
        $historyEntries = $historyEntriesQuery
            ->orderByDesc('date')
            ->paginate($perPage);

        return StockHistoryEntryResource::collection($historyEntries)->resolve();
    }
}
