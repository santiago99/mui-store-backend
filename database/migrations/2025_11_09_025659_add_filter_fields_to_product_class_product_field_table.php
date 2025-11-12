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
        Schema::table('product_class_product_field', function (Blueprint $table) {
            $table->boolean('is_filter')->default(false)->after('weight');
            $table->string('filter_type')->nullable()->after('is_filter');
            $table->integer('filter_weight')->default(0)->after('filter_type');
            $table->json('options')->nullable()->after('filter_weight');

            $table->index('is_filter');
            $table->index('filter_weight');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_class_product_field', function (Blueprint $table) {
            $table->dropIndex(['is_filter']);
            $table->dropIndex(['filter_weight']);
            $table->dropColumn(['is_filter', 'filter_type', 'filter_weight', 'options']);
        });
    }
};
