<?php

use App\Http\Controllers\BillitWebhookController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CupLoanRequestController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\InsuranceDocumentController;
use App\Http\Controllers\MemberSelfServiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PurchaseInvoiceFileController;
use App\Http\Controllers\RentalRequestController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PageController::class, 'home'])->name('home');

Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');

Route::get('/agenda/{event:slug}', [EventController::class, 'show'])->name('events.show');
Route::post('/agenda/{event:slug}/tickets', [OrderController::class, 'store'])->middleware('throttle:10,1')->name('orders.store');

// Orders are reached by their random token, never by id or number.
Route::get('/bestelling/{order}', [OrderController::class, 'show'])->name('orders.show');
Route::post('/bestelling/{order}/betalen', [OrderController::class, 'pay'])->middleware('throttle:10,1')->name('orders.pay');
Route::get('/bestelling/{order}/tickets.pdf', [OrderController::class, 'tickets'])->name('orders.tickets');

Route::post('/webhooks/stripe', [PaymentWebhookController::class, 'stripe'])->name('webhooks.stripe');
Route::post('/webhooks/billit', BillitWebhookController::class)->name('webhooks.billit');
Route::post('/webhooks/sumup', [PaymentWebhookController::class, 'sumup'])->name('webhooks.sumup');

Route::get('/contract/{token}', [ContractController::class, 'show'])->name('contracts.show');
Route::post('/contract/{token}', [ContractController::class, 'sign'])->middleware('throttle:10,1')->name('contracts.sign');
Route::get('/contract/{token}/pdf', [ContractController::class, 'pdf'])->name('contracts.pdf');

Route::get('/admin/files/purchase-invoices/{purchaseInvoice}', PurchaseInvoiceFileController::class)->name('purchase-invoices.file');
Route::get('/admin/files/insurances/{insurance}/{index}', InsuranceDocumentController::class)->whereNumber('index')->name('insurances.document');

// The QR on the member card. The random token is the only key, so the page is not indexed.
Route::get('/lid/{token}', [MemberSelfServiceController::class, 'edit'])->name('members.edit');
Route::put('/lid/{token}', [MemberSelfServiceController::class, 'update'])->middleware('throttle:10,1')->name('members.update');

Route::post('/wisselbekers', [CupLoanRequestController::class, 'store'])->middleware('throttle:5,1')->name('cup-loans.store');

Route::get('/kalender', CalendarController::class)->name('calendar');

Route::post('/huuraanvraag', [RentalRequestController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('rental-requests.store');

// Old WordPress addresses that may still be bookmarked or indexed.
Route::permanentRedirect('/wp-booking-calendar', '/verhuren#huuraanvraag');
foreach (['winkelwagen', 'afrekenen', 'mijn-account'] as $oldShopPage) {
    Route::permanentRedirect("/{$oldShopPage}", '/winkel');
}
foreach (['wpbc-booking-received', 'wpbc-my-booking', 'wpbc-my-bookings-listing', 'wpbc-bfb-preview'] as $oldBookingPage) {
    Route::permanentRedirect("/{$oldBookingPage}", '/verhuren');
}

// CMS pages last, so they never shadow the routes above or the admin panel.
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '^(?!admin|livewire|storage|filament|api)[a-z0-9-]+$')
    ->name('page');
