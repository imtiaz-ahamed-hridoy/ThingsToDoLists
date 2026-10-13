<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('category_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('status', [0, 1])->default('1')->comment('0 = Deactivate, 1 = Active');
            $table->timestamps();
        });

        $now = now();

        DB::table('category_types')->insert([
            'name' => 'Product',
            'slug' => 'product',
            'status' => '1',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $existingTypes = DB::table('categories')
            ->select('type')
            ->whereNotNull('type')
            ->get()
            ->pluck('type')
            ->map(fn ($type) => trim((string) $type))
            ->filter()
            ->unique()
            ->values();

        foreach ($existingTypes as $type) {
            $slug = Str::slug($type, '_');

            DB::table('category_types')->updateOrInsert(
                ['slug' => $slug],
                [
                    'name' => Str::headline(str_replace('_', ' ', $type)),
                    'status' => '1',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            DB::table('categories')
                ->where('type', $type)
                ->update(['type' => $slug]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_types');
    }
};
