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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            
            // Nested set columns
            $table->unsignedBigInteger('_lft');
            $table->unsignedBigInteger('_rgt');
            $table->unsignedBigInteger('parent_id')->nullable();
            
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['_lft', '_rgt']);
            $table->index('parent_id');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
