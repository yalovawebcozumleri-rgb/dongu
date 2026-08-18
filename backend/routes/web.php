<?php

use App\Http\Controllers\Admin\AdvertisementController;
use App\Http\Controllers\Admin\AdvertisementPlacementSettingController;
use App\Http\Controllers\Admin\AdMobRuntimeSettingController;
use App\Http\Controllers\Admin\AnnouncementCampaignController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CycleRiskCaseController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MessageReportController;
use App\Http\Controllers\Admin\MarketplaceUsagePolicyController;
use App\Http\Controllers\Admin\ListingManagementController;
use App\Http\Controllers\Admin\ListingReportController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\UserReportController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\AccountDeletionController;
use Illuminate\Support\Facades\Route;

Route::get('/hesap-silme', [AccountDeletionController::class, 'create'])->name('account-deletion.create');
Route::post('/hesap-silme/kod', [AccountDeletionController::class, 'requestCode'])->middleware('throttle:3,10')->name('account-deletion.code');
Route::post('/hesap-silme', [AccountDeletionController::class, 'destroy'])->middleware('throttle:10,10')->name('account-deletion.destroy');
Route::get('/kullanim-sartlari', [LegalDocumentController::class, 'terms'])->name('legal.terms');
Route::get('/gizlilik-politikasi', [LegalDocumentController::class, 'privacy'])->name('legal.privacy');
Route::get('/legal/{document}', [LegalDocumentController::class, 'web'])->whereIn('document', ['terms', 'privacy'])->name('legal.show');

Route::view('/', 'marketing.home')->name('marketing.home');
Route::view('/nasil-calisir', 'marketing.how-it-works')->name('marketing.how-it-works');
Route::view('/hakkimizda', 'marketing.about')->name('marketing.about');
Route::view('/sss', 'marketing.faq')->name('marketing.faq');
Route::view('/iletisim', 'marketing.contact')->name('marketing.contact');
Route::view('/mobil-uygulama', 'marketing.mobile-app')->name('marketing.mobile-app');

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AuthController::class, 'create'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'store'])->name('admin.login.store');
});

Route::prefix('admin')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', DashboardController::class)->name('admin.dashboard');
    Route::get('/users', [UserManagementController::class, 'index'])->name('admin.users.index');
    Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('admin.users.show');
    Route::patch('/users/{user}/account', [UserManagementController::class, 'updateAccount'])->name('admin.users.account.update');
    Route::get('/listings', [ListingManagementController::class, 'index'])->name('admin.listings.index');
    Route::get('/listings/{listing}', [ListingManagementController::class, 'show'])->name('admin.listings.show');
    Route::delete('/listings/{listing}', [ListingManagementController::class, 'destroy'])->name('admin.listings.destroy');
    Route::get('/usage-policies', [MarketplaceUsagePolicyController::class, 'edit'])->name('admin.usage-policies.edit');
    Route::patch('/usage-policies', [MarketplaceUsagePolicyController::class, 'update'])->name('admin.usage-policies.update');
    Route::get('/announcements', [AnnouncementCampaignController::class, 'index'])->name('admin.announcements.index');
    Route::post('/announcements', [AnnouncementCampaignController::class, 'store'])->name('admin.announcements.store');
    Route::patch('/announcements/{announcement}', [AnnouncementCampaignController::class, 'update'])->name('admin.announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementCampaignController::class, 'destroy'])->name('admin.announcements.destroy');
    Route::get('/advertisements', [AdvertisementController::class, 'index'])->name('admin.advertisements.index');
    Route::post('/advertisements', [AdvertisementController::class, 'store'])->name('admin.advertisements.store');
    Route::patch('/advertisements/{advertisement}', [AdvertisementController::class, 'update'])->name('admin.advertisements.update');
    Route::delete('/advertisements/{advertisement}', [AdvertisementController::class, 'destroy'])->name('admin.advertisements.destroy');
    Route::patch('/advertising-runtime', [AdMobRuntimeSettingController::class, 'update'])->name('admin.advertising-runtime.update');
    Route::patch('/advertisement-placements/{setting}', [AdvertisementPlacementSettingController::class, 'update'])->name('admin.advertisement-placements.update');
    Route::get('/cycle-risk-cases', [CycleRiskCaseController::class, 'index'])->name('admin.cycle-risk-cases.index');
    Route::get('/cycle-risk-cases/{cycleRiskCase}', [CycleRiskCaseController::class, 'show'])->name('admin.cycle-risk-cases.show');
    Route::patch('/cycle-risk-cases/{cycleRiskCase}', [CycleRiskCaseController::class, 'update'])->name('admin.cycle-risk-cases.update');
    Route::delete('/cycle-risk-cases/{cycleRiskCase}', [CycleRiskCaseController::class, 'destroy'])->name('admin.cycle-risk-cases.destroy');
    Route::get('/message-reports', [MessageReportController::class, 'index'])->name('admin.message-reports.index');
    Route::get('/listing-reports', [ListingReportController::class, 'index'])->name('admin.listing-reports.index');
    Route::patch('/listing-reports/{listingReport}', [ListingReportController::class, 'update'])->name('admin.listing-reports.update');
    Route::get('/user-reports', [UserReportController::class, 'index'])->name('admin.user-reports.index');
    Route::patch('/user-reports/{userReport}', [UserReportController::class, 'update'])->name('admin.user-reports.update');
    Route::get('/message-reports/{messageReport}', [MessageReportController::class, 'show'])->name('admin.message-reports.show');
    Route::patch('/message-reports/{messageReport}', [MessageReportController::class, 'update'])->name('admin.message-reports.update');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('admin.logout');
});
