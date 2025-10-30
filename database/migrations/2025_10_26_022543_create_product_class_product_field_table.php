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
        Schema::create('product_class_product_field', function (Blueprint $table) {
            $table->foreignId('product_class_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_field_id')->constrained()->onDelete('cascade');
            $table->integer('weight')->default(0);
            $table->timestamps();

            $table->primary(['product_class_id', 'product_field_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_class_product_field');
    }
};
