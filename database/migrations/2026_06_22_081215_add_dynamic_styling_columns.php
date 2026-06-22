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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('hex_color', 7)->nullable()->after('color');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('emoji', 50)->nullable()->after('promo_tag');
            $table->string('custom_bg', 255)->nullable()->after('bg_gradient');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('hex_color');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['emoji', 'custom_bg']);
        });
    }
};
