<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Получить список товаров с остатками по складам
     **/
    public function stocks(Request $request)
    {
        $perPage = $request->query('per_page');

        // Выбрать список товаров с сортировкой по алфавиту
        // и пагинацией на случай, если товаров будет много
        $products = Product::query()
            ->orderBy('name')
            ->paginate($perPage);

        return ProductResource::collection($products)->resolve();
    }
}
