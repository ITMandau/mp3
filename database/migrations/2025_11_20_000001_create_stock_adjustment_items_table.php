<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stock_adjustment_id')
                  ->constrained('stock_adjustments')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();

            $table->foreignId('product_id')
                  ->constrained()          // ->references('id')->on('products')
                  ->cascadeOnUpdate();

            // ==== LOG STOK ====
            $table->integer('qty_before'); // stok sistem sebelum adjustment
            $table->integer('qty_after');  // stok setelah adjustment (bisa sama kalau cuma ubah harga)
            $table->integer('qty_diff');   // qty_after - qty_before (bisa negatif/positif)

            // ==== LOG HARGA (optional sesuai mode) ====
            // null kalau mode adjustment-nya tidak menyentuh harga tsb
            $table->integer('purchase_price_before')->nullable(); // harga beli sebelum
            $table->integer('purchase_price_after')->nullable();  // harga beli sesudah
            $table->integer('selling_price_before')->nullable();  // harga jual sebelum
            $table->integer('selling_price_after')->nullable();   // harga jual sesudah

            // Catatan per item
            $table->string('notes')->nullable();

            $table->timestamps();

            $table->index(['product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
