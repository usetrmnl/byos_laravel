<?php

use App\Models\Plugin;
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
        // Find and handle duplicate (user_id, trmnlp_id) combinations
        $duplicates = Plugin::query()
            ->selectRaw('user_id, trmnlp_id, COUNT(*) as duplicate_count')
            ->whereNotNull('trmnlp_id')
            ->groupBy('user_id', 'trmnlp_id')
            ->havingRaw('COUNT(*) > ?', [1])
            ->get();

        // For each duplicate combination, keep the first one (by id) and set others to null
        foreach ($duplicates as $duplicate) {
            $plugins = Plugin::query()
                ->where('user_id', $duplicate->user_id)
                ->where('trmnlp_id', $duplicate->trmnlp_id)
                ->orderBy('id')
                ->get();

            // Keep the first one, set the rest to null
            $keepFirst = true;
            foreach ($plugins as $plugin) {
                if ($keepFirst) {
                    $keepFirst = false;

                    continue;
                }

                $plugin->update(['trmnlp_id' => null]);
            }
        }

        Schema::table('plugins', function (Blueprint $table) {
            $table->unique(['user_id', 'trmnlp_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plugins', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'trmnlp_id']);
        });
    }
};
