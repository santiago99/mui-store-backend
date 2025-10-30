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
        Schema::create('product_field_values', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->foreignId('product_field_id')->constrained()->onDelete('cascade');
            $table->string('value_string')->nullable();
            $table->integer('value_int')->nullable();
            $table->float('value_float')->nullable();
            $table->timestamps();
            $table->index('value_string');
            $table->index('value_int');
            $table->index('value_float');

            $table->primary(['product_id', 'product_field_id']);
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_field_values');
    }
};
