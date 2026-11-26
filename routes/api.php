<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\CloudPasswordController;
use App\Http\Controllers\Auth\SessionSecurityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogSyncController;
use App\Http\Controllers\CourierHandoverController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmergencyWipeController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\IncomingLeadController;
use App\Http\Controllers\IncomingOrderController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\OrderCommentController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderMessageController;
use App\Http\Controllers\PersonnelController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TelegramController;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::post('/auth/tma', [AuthController::class, 'tma'])
    ->middleware(['throttle:tma-auth', EncryptCookies::class, StartSession::class]);
Route::post('/auth/confirm-ip', [SessionSecurityController::class, 'confirmIp'])
    ->middleware(['throttle:10,1', EncryptCookies::class, StartSession::class]);
Route::post('/auth/cloud-password/verify', [CloudPasswordController::class, 'verify'])
    ->middleware(['throttle:10,1', EncryptCookies::class, StartSession::class]);
Route::post('/auth/cloud-password/setup', [CloudPasswordController::class, 'setup'])
    ->middleware(['throttle:10,1', EncryptCookies::class, StartSession::class]);
Route::post('/auth/cloud-password/change', [CloudPasswordController::class, 'change'])
    ->middleware(['tma.auth', 'throttle:10,1']);
Route::post('/auth/block-session', [SessionSecurityController::class, 'blockSession'])
    ->middleware(['throttle:10,1', EncryptCookies::class, StartSession::class]);
Route::get('/auth/me', [AuthController::class, 'me'])->middleware('tma.auth');
Route::post('/auth/session/sync', [AuthController::class, 'syncSession'])
    ->middleware([EncryptCookies::class, StartSession::class, 'tma.auth']);
Route::post('/auth/desktop-link', [AuthController::class, 'createDesktopLink'])->middleware(['tma.auth', 'throttle:desktop-link']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('tma.auth');
Route::get('/auth/sessions', [SessionSecurityController::class, 'sessions'])->middleware('tma.auth');
Route::delete('/auth/sessions', [SessionSecurityController::class, 'revokeSession'])->middleware('tma.auth');
Route::post('/telegram/webhook', [TelegramController::class, 'webhook'])
    ->middleware(['telegram.webhook.secret', 'throttle:telegram-webhook']);

Route::middleware(['tma.auth', 'role:owner,manager,courier'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store'])->middleware('role:owner,manager');
    Route::post('/orders/bulk-assign', [OrderController::class, 'bulkAssign'])->middleware(['role:owner,manager', 'throttle:bulk-orders']);
    Route::post('/orders/bulk-accept', [OrderController::class, 'bulkAccept'])->middleware(['role:owner,manager', 'throttle:bulk-orders']);
    Route::post('/orders/bulk-assign-manager', [OrderController::class, 'bulkAssignManager'])->middleware(['role:owner', 'throttle:bulk-orders']);
    Route::post('/orders/bulk-at-warehouse', [OrderController::class, 'bulkAtWarehouse'])->middleware(['role:owner,manager', 'throttle:bulk-orders']);
    Route::post('/orders/bulk-cancel', [OrderController::class, 'bulkCancel'])->middleware(['role:owner,manager', 'throttle:bulk-orders']);
    Route::patch('/orders/{order}', [OrderController::class, 'update'])->middleware('can:update,order')->whereNumber('order');
    Route::patch('/orders/{order}/assign', [OrderController::class, 'assign'])->middleware('can:update,order')->whereNumber('order');
    Route::patch('/orders/{order}/accept', [OrderController::class, 'accept'])->middleware('role:owner,manager')->whereNumber('order');
    Route::patch('/orders/{order}/at-warehouse', [OrderController::class, 'atWarehouse'])->middleware('can:atWarehouse,order')->whereNumber('order');
    Route::patch('/orders/{order}/complete', [OrderController::class, 'complete'])->middleware('can:complete,order')->whereNumber('order');
    Route::post('/orders/{order}/delivery-proof', [OrderController::class, 'uploadProof'])->middleware(['can:uploadProof,order', 'throttle:30,1'])->whereNumber('order');
    Route::delete('/orders/{order}/delivery-proof', [OrderController::class, 'deleteProof'])->middleware(['can:deleteProof,order', 'throttle:30,1'])->whereNumber('order');
    Route::post('/orders/{order}/delivery-signature', [OrderController::class, 'uploadSignature'])->middleware(['can:uploadSignature,order', 'throttle:30,1'])->whereNumber('order');
    Route::delete('/orders/{order}/delivery-signature', [OrderController::class, 'deleteSignature'])->middleware(['can:deleteSignature,order', 'throttle:30,1'])->whereNumber('order');
    Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->middleware('can:delete,order')->whereNumber('order');
    Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel'])->middleware('can:cancel,order')->whereNumber('order');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('can:view,order')->whereNumber('order');
    Route::get('/orders/{order}/comments', [OrderCommentController::class, 'index'])->middleware('can:view,order')->whereNumber('order');
    Route::post('/orders/{order}/comments', [OrderCommentController::class, 'store'])->middleware('can:view,order')->whereNumber('order');
    Route::get('/orders/chat-active-orders', [OrderMessageController::class, 'activeChatOrders']);
    Route::get('/orders/chat-search-orders', [OrderMessageController::class, 'searchChatOrders']);
    Route::get('/orders/chat-unread-summary', [OrderMessageController::class, 'unreadSummary']);
    Route::get('/orders/{order}/messages', [OrderMessageController::class, 'index'])->middleware('can:chat,order')->whereNumber('order');
    Route::post('/orders/{order}/messages', [OrderMessageController::class, 'store'])->middleware(['can:chat,order', 'throttle:60,1'])->whereNumber('order');
    Route::match(['put', 'patch'], '/orders/{order}/messages/{message}', [OrderMessageController::class, 'update'])->middleware(['can:chat,order', 'throttle:60,1'])->whereNumber('order')->whereNumber('message');
    Route::delete('/orders/{order}/messages/{message}', [OrderMessageController::class, 'destroy'])->middleware(['can:chat,order', 'throttle:60,1'])->whereNumber('order')->whereNumber('message');
    Route::get('/personnel/couriers', [PersonnelController::class, 'couriers']);
    Route::get('/personnel/managers', [PersonnelController::class, 'managers'])->middleware('role:owner,manager');
    Route::get('/personnel/search', [PersonnelController::class, 'search']);
    Route::get('/sites', [SiteController::class, 'index']);
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('role:owner,manager');
    Route::get('/customers/lookup', [CustomerController::class, 'lookup'])->middleware('role:owner,manager');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('role:owner,manager')->whereNumber('customer');
    Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->middleware('role:owner,manager')->whereNumber('customer');
    Route::get('/products', [ProductController::class, 'index'])->middleware('role:owner,manager');
    Route::post('/products', [ProductController::class, 'store'])->middleware('role:owner,manager');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('role:owner,manager')->whereNumber('product');
    Route::patch('/products/{product}', [ProductController::class, 'update'])->middleware('role:owner,manager')->whereNumber('product');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('role:owner,manager')->whereNumber('product');
    Route::get('/leads', [LeadController::class, 'index'])->middleware('role:owner,manager');
    Route::get('/leads/{lead}', [LeadController::class, 'show'])->middleware('role:owner,manager')->whereNumber('lead');
    Route::patch('/leads/{lead}', [LeadController::class, 'update'])->middleware('role:owner,manager')->whereNumber('lead');
    Route::delete('/leads/{lead}', [LeadController::class, 'destroy'])->middleware('role:owner,manager')->whereNumber('lead');
    Route::post('/leads/{lead}/convert-to-order', [LeadController::class, 'convertToOrder'])->middleware('role:owner,manager')->whereNumber('lead');
    Route::get('/customers/{customer}/leads', [LeadController::class, 'customerLeads'])->middleware('role:owner,manager')->whereNumber('customer');
    Route::get('/courier/shift-summary', [DashboardController::class, 'courierShiftSummary']);
    Route::get('/courier/handover/orders', [CourierHandoverController::class, 'orders']);
    Route::get('/courier/handover/active', [CourierHandoverController::class, 'active']);
    Route::post('/courier/handover/initiate', [CourierHandoverController::class, 'initiate'])->middleware('throttle:30,1');
    Route::post('/courier/handover/cancel', [CourierHandoverController::class, 'cancel'])->middleware('throttle:30,1');
    Route::get('/courier/handover/preview/{code}', [CourierHandoverController::class, 'preview'])->middleware('throttle:60,1');
    Route::post('/courier/handover/accept', [CourierHandoverController::class, 'accept'])->middleware('throttle:30,1');
});

Route::middleware(['tma.auth', 'role:owner'])->group(function () {
    Route::get('/exports/financial', [ExportController::class, 'exportFinancial'])->middleware('throttle:financial-export');
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/recent-orders', [DashboardController::class, 'recentOrders']);
    Route::get('/dashboard/financial-history', [DashboardController::class, 'financialHistory']);
    Route::get('/dashboard/managers-kpi', [DashboardController::class, 'managersKpi']);
    Route::get('/dashboard/couriers-kpi', [DashboardController::class, 'couriersKpi']);
    Route::get('/dashboard/products-kpi', [DashboardController::class, 'productsKpi']);
    Route::get('/settings/retention', [SettingsController::class, 'retention']);
    Route::post('/settings/retention', [SettingsController::class, 'updateRetention']);
    Route::get('/emergency-wipe/status', [EmergencyWipeController::class, 'status'])->middleware('throttle:emergency-wipe');
    Route::post('/emergency-wipe/pin', [EmergencyWipeController::class, 'setPin'])->middleware('throttle:emergency-wipe');
    Route::post('/emergency-wipe/initiate', [EmergencyWipeController::class, 'initiate'])->middleware('throttle:emergency-wipe');
    Route::post('/emergency-wipe/execute', [EmergencyWipeController::class, 'execute'])->middleware('throttle:emergency-wipe');
    Route::post('/admin/users/{user}/reset-cloud-password', [CloudPasswordController::class, 'reset'])->whereNumber('user');
    Route::get('/personnel', [PersonnelController::class, 'index']);
    Route::post('/personnel', [PersonnelController::class, 'store']);
    Route::patch('/personnel/{id}', [PersonnelController::class, 'update']);
    Route::delete('/personnel/{id}', [PersonnelController::class, 'destroy']);
    Route::get('/audit/logs', [AuditController::class, 'index']);
    Route::get('/audit/logs/{id}', [AuditController::class, 'show']);
    Route::get('/audit/orders/{orderId}/logs', [AuditController::class, 'orderLogs'])->whereNumber('orderId');
    Route::get('/invitations', [InvitationController::class, 'index']);
    Route::post('/invitations', [InvitationController::class, 'store']);
    Route::delete('/invitations/{id}', [InvitationController::class, 'destroy']);
    Route::delete('/orders/{order}/comments/{comment}', [OrderCommentController::class, 'destroy'])->whereNumber(['order', 'comment']);
    Route::patch('/orders/{order}/status', [OrderController::class, 'status'])->middleware('can:change-order-status')->whereNumber('order');
    Route::post('/sites', [SiteController::class, 'store']);
    Route::get('/sites/{site}', [SiteController::class, 'show'])->whereNumber('site');
    Route::patch('/sites/{site}', [SiteController::class, 'update'])->whereNumber('site');
    Route::post('/sites/{site}/rotate-token', [SiteController::class, 'rotateToken'])->whereNumber('site');
    Route::post('/sites/{site}/revoke-token', [SiteController::class, 'revokeToken'])->whereNumber('site');
    Route::delete('/sites/{site}', [SiteController::class, 'destroy'])->whereNumber('site');
});

Route::middleware(['tma.auth'])->get('/settings', [SettingsController::class, 'index']);
Route::middleware(['tma.auth', 'settings.view'])->get('/settings/{key}', [SettingsController::class, 'show']);
Route::middleware(['tma.auth', 'settings.edit'])->post('/settings/{key}', [SettingsController::class, 'update']);

Route::post('/invitations/accept', [InvitationController::class, 'accept'])
    ->middleware(['throttle:invitation-accept', EncryptCookies::class, StartSession::class]);

Route::post('/orders/incoming', [IncomingOrderController::class, 'store'])->middleware('throttle:incoming-orders');
Route::post('/leads/incoming', [IncomingLeadController::class, 'store'])->middleware('throttle:incoming-leads');
Route::post('/catalog/sync', [CatalogSyncController::class, 'sync'])->middleware('throttle:catalog-sync');
Route::post('/catalog/products', [CatalogSyncController::class, 'storeOrUpdateProduct'])->middleware('throttle:catalog-sync');
Route::delete('/catalog/products/{externalId}', [CatalogSyncController::class, 'destroyProduct'])->middleware('throttle:catalog-sync');
