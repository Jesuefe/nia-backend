<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MemoryController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\WhatsAppController;
use App\Http\Controllers\Admin\AdminController;

// ─── Public routes ────────────────────────────────────────────────────────────
Route::post('/auth/register',   [AuthController::class, 'register']);
Route::post('/auth/login',      [AuthController::class, 'login']);
Route::get('/plans',            [SubscriptionController::class, 'plans']);

// Paystack webhook (no auth - verified by signature)
Route::post('/webhook/paystack', [SubscriptionController::class, 'webhook']);

// ─── Authenticated routes ─────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout',           [AuthController::class, 'logout']);
    Route::get('/auth/me',                [AuthController::class, 'me']);
    Route::put('/auth/profile',           [AuthController::class, 'updateProfile']);
    Route::put('/auth/change-password',   [AuthController::class, 'changePassword']);

    // Memory
    Route::get('/memory',     [MemoryController::class, 'get']);
    Route::put('/memory',     [MemoryController::class, 'update']);
    Route::delete('/memory',  [MemoryController::class, 'clear']);
    Route::get('/memory/export', [MemoryController::class, 'export']);

    // Conversations
    Route::get('/conversations',    [ConversationController::class, 'index']);
    Route::post('/conversations',   [ConversationController::class, 'store']);
    Route::delete('/conversations', [ConversationController::class, 'clear']);

    // Subscriptions
    Route::get('/subscription',             [SubscriptionController::class, 'status']);
    Route::post('/subscription/initialize', [SubscriptionController::class, 'initialize']);
    Route::post('/subscription/verify',     [SubscriptionController::class, 'verify']);
    Route::post('/subscription/cancel',     [SubscriptionController::class, 'cancel']);

    // WhatsApp
    Route::post('/whatsapp/send',              [WhatsAppController::class, 'send']);
    Route::post('/whatsapp/schedule',          [WhatsAppController::class, 'scheduleReminder']);

    // ─── Admin routes ─────────────────────────────────────────────────────────
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/stats',                    [AdminController::class, 'stats']);
        Route::get('/users',                    [AdminController::class, 'users']);
        Route::get('/users/{user}',             [AdminController::class, 'user']);
        Route::post('/users/{user}/grant-pro',  [AdminController::class, 'grantPro']);
        Route::post('/users/{user}/revoke-pro', [AdminController::class, 'revokePro']);
        Route::delete('/users/{user}',          [AdminController::class, 'deleteUser']);
        Route::get('/subscriptions',            [AdminController::class, 'subscriptions']);
        Route::get('/activity',                 [AdminController::class, 'activity']);
    });
});
