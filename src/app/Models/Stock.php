<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'warehouse_id',
        'stock',
    ];

    public $timestamps = false;

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }


    public static function boot()
    {
        parent::boot();

        self::created(function ($model) {
            StockHistoryEntry::create([
                'product_id' => $model->product_id,
                'warehouse_id' => $model->warehouse_id,
                'date' => Carbon::now(),
                'stock' => $model->stock,
            ]);
        });

        self::updated(function ($model) {
            StockHistoryEntry::create([
                'product_id' => $model->product_id,
                'warehouse_id' => $model->warehouse_id,
                'date' => Carbon::now(),
                'stock' => $model->stock,
            ]);
        });
    }
}
