<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('customer');
            $table->timestamp('created_at');
            $table->timestamp('completed_at')->nullable(); // Заказ может быть незавершённым, поэтому предусмотрен NULL
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete()->cascadeOnUpdate();
            $table->string('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
