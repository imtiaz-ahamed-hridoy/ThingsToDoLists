<?php

use App\Http\Controllers\Admin\Settings\MailSettingController;
use App\Http\Controllers\Admin\Settings\ProfileController;
use App\Http\Controllers\Admin\Settings\SystemSettingController;
use Illuminate\Support\Facades\Route;

// ==================== PROFILE SETTINGS ====================
Route::controller(ProfileController::class)->group(function () {
    Route::get('/profile', 'index')->name('profile.setting');
    Route::patch('/update-profile', 'UpdateProfile')->name('update.profile');
    Route::put('/update-profile-password', 'UpdatePassword')->name('update.Password');
    Route::post('/update-profile-picture', 'UpdateProfilePicture')->name('update.profile.picture');
    Route::post('/logout-all-devices', 'logoutAllDevices')->name('profile.logout-all');
});

// ==================== SYSTEM SETTINGS ====================
Route::controller(SystemSettingController::class)->group(function () {
    Route::get('/system-setting', 'index')->name('system.index');
    Route::patch('/system-setting', 'update')->name('system.update');
});

// ==================== MAIL SETTINGS ====================
Route::controller(MailSettingController::class)->group(function () {
    Route::get('/mail-setting', 'index')->name('mail.setting');
    Route::patch('/mail-setting', 'update')->name('mail.update');
});
