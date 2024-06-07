<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Console\Command;

class GenerateMockData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:mock-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create mock products, warehouses, and stocks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Сгенерировать товары и склады
        echo "Generating products...\n";

        $numProducts = 2000;
        Product::factory($numProducts)->create();

        echo "Generating warehouses...\n";

        $numWarehouses = 100;
        Warehouse::factory($numWarehouses)->create();

        // Сгенерировать остатки на складах

        // NOTE: Проблему повторяющихся записей можно было бы решить
        // путём добавления составного уникального индекса в таблицу stocks,
        // но по условию задачи базу менять нельзя, поэтому пришлось
        // делать дополнительные запросы в базу для проверки на существование той или иной записи.

        $numStocks = 5000; // Фактическое число записей может быть меньше, если при генерации попадутся повторяющиеся записи.

        for ($i = 0; $i < $numStocks; $i++) {
            echo "Generating stocks. Iteration $i / $numStocks\n";

            $productId = fake()->randomElement(Product::pluck('id'));
            $warehouseId = fake()->randomElement(Warehouse::pluck('id'));

            $existingStock = Stock::query()
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if ($existingStock === null) {
                Stock::create([
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'stock' => fake()->randomNumber(3),
                ]);
            }
        }

        echo "Done.\n";
    }
}
