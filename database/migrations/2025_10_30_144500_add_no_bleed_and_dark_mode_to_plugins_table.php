<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugins', function (Blueprint $table): void {
            if (! Schema::hasColumn('plugins', 'no_bleed')) {
                $table->boolean('no_bleed')->default(false)->after('configuration_template');
            }
            if (! Schema::hasColumn('plugins', 'dark_mode')) {
                $table->boolean('dark_mode')->default(false)->after('no_bleed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table): void {
            if (Schema::hasColumn('plugins', 'dark_mode')) {
                $table->dropColumn('dark_mode');
            }
            if (Schema::hasColumn('plugins', 'no_bleed')) {
                $table->dropColumn('no_bleed');
            }
        });
    }
};
