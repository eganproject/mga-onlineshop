<?php

use App\Services\Catalog\SoldCountService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Total terjual (pcs) dari pesanan yang sudah dibayar & tidak dibatalkan.
            $table->unsignedInteger('sold_count')->default(0)->after('review_count');
            $table->index('sold_count');
        });

        app(SoldCountService::class)->sync();
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['sold_count']);
            $table->dropColumn('sold_count');
        });
    }
};
