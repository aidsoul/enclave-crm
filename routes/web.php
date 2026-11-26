<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExternalLinkController;
use App\Http\Controllers\TmaController;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('/up', function () {
    return response()->json(['status' => 'healthy']);
});

Route::get('/robots.txt', function () {
    return response("User-agent: *\nDisallow: /\n", 200, [
        'Content-Type' => 'text/plain',
    ]);
});

$docsMiddleware = app()->environment('production') ? ['tma.auth', 'role:owner'] : [];

Route::get('/docs/api/openapi.yaml', function () {
    return response()->file(base_path('docs/api/openapi.yaml'), [
        'Content-Type' => 'application/yaml',
    ]);
})->middleware($docsMiddleware);

Route::get('/docs/api', function () {
    return response()->view('docs.api');
})->middleware($docsMiddleware);

Route::get('/open/viber', [ExternalLinkController::class, 'viber']);
Route::get('/open/email', [ExternalLinkController::class, 'email']);
Route::get('/open/telegram', [ExternalLinkController::class, 'telegram']);

Route::get('/tma/login/desktop', [AuthController::class, 'desktopLogin'])
    ->middleware([EncryptCookies::class, StartSession::class])
    ->name('tma.desktop-login');

Route::get('/blocked', function () {
    return response()->view('tma.blocked', [
        'message' => 'Доступ разрешён только через Telegram Mini App. Откройте бота в Telegram для продолжения.',
    ], 403);
})->name('tma.blocked');

Route::middleware('tma.auth')->group(function () {
    Route::get('/', [TmaController::class, 'index'])->name('tma.home');
    Route::get('/tma/personnel', [TmaController::class, 'personnel'])->name('tma.personnel');
    Route::get('/tma/audit', [TmaController::class, 'audit'])->name('tma.audit');
    Route::get('/tma/audit/{id}', [TmaController::class, 'auditShow'])->whereNumber('id')->name('tma.audit.show');
    Route::get('/tma/settings', [TmaController::class, 'settings'])->name('tma.settings');
    Route::get('/tma/sites', [TmaController::class, 'sites'])->name('tma.sites');
    Route::get('/tma/financial', [TmaController::class, 'financial'])->name('tma.financial');
    Route::get('/tma/analytics', [TmaController::class, 'analytics'])->name('tma.analytics');
    Route::get('/tma/new', [TmaController::class, 'orderCreate'])->name('tma.orders.create');
    Route::get('/tma/orders', [TmaController::class, 'orderList'])->name('tma.orders.index');
    Route::get('/tma/orders/{id}', [TmaController::class, 'orderShow'])->whereNumber('id')->name('tma.orders.show');
    Route::get('/tma/orders/{id}/edit', [TmaController::class, 'orderEdit'])->whereNumber('id')->name('tma.orders.edit');
    Route::get('/tma/orders/{id}/chat', [TmaController::class, 'orderChat'])->whereNumber('id')->name('tma.orders.chat');
    Route::get('/tma/customers', [TmaController::class, 'customers'])->name('tma.customers.index');
    Route::get('/tma/customers/{id}', [TmaController::class, 'customerShow'])->whereNumber('id')->name('tma.customers.show');
    Route::get('/tma/leads', [TmaController::class, 'leads'])->name('tma.leads.index');
    Route::get('/tma/manager', [TmaController::class, 'index'])->defaults('role', 'manager')->name('tma.manager');
    Route::get('/tma/{role?}', [TmaController::class, 'index'])->name('tma.role');
});
