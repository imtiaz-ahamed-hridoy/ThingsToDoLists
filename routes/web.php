<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\ProfileController;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/welcome', function () {
    return view('welcome');
})->name('welcome');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/run-migrate', function () {
    // Run the database migration
    Artisan::call('migrate');
    return 'Database migration successfully!';
});
// Run Migrate Fresh Route
Route::get('/run-migrate-fresh', function () {
    // Run the database migration
    Artisan::call('migrate:fresh');
    return 'Database migration fresh successfully!';
});
// Run Seeder Route
Route::get('/run-seed', function () {
    // Run the database seeding
    Artisan::call('db:seed');
    return 'Database seeding completed successfully!';
});

// Run Migrate + Seed in one route
Route::get('/run-migrate-seed', function () {
    Artisan::call('migrate', [
        '--seed'  => true,
        '--force' => true,
    ]);

    return 'Database migrated and seeded successfully!';
});

// Clear Config Cache Route
Route::get('/clear-config', function () {
    // Clear the config cache
    Artisan::call('optimize:clear');
    Artisan::call('view:clear');
    Artisan::call('cache:clear');
    Artisan::call('config:cache');
    Artisan::call('route:cache');
    return 'Config cache cleared successfully!';
});


Route::get('/db-tables', function () {
    $databaseName = config('database.connections.' . config('database.default') . '.database');
    $tables = DB::select("SHOW TABLES");

    $key = 'Tables_in_' . $databaseName;
    $tableNames = array_map(fn($table) => $table->$key, $tables);

    return response()->json($tableNames);
});

Route::middleware('auth')->get('/storage-link', function () {
    Artisan::call('storage:link');
    return 'Storage link created successfully!';
});

Route::get('/show/{table}', function ($table) {
    $data = DB::table($table)->get();
    return $data;
});


require __DIR__ . '/auth.php';

