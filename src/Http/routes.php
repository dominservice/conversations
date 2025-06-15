<?php

use Illuminate\Support\Facades\Route;
use Dominservice\Conversations\Http\Controllers\ConversationsController;
use Dominservice\Conversations\Http\Controllers\MessagesController;

$prefix = config('conversations.api.prefix', 'api/conversations');
$middleware = config('conversations.api.middleware', ['api', 'auth:api']);

Route::group(['prefix' => $prefix, 'middleware' => $middleware], function () {
    // Conversations routes
    Route::get('/', [ConversationsController::class, 'index']);
    Route::post('/', [ConversationsController::class, 'store']);
    Route::get('/{uuid}', [ConversationsController::class, 'show']);
    Route::delete('/{uuid}', [ConversationsController::class, 'destroy']);

    // Messages routes
    Route::get('/{uuid}/messages', [MessagesController::class, 'index']);
    Route::post('/{uuid}/messages', [MessagesController::class, 'store']);
    Route::get('/{uuid}/messages/unread', [MessagesController::class, 'unread']);
    Route::post('/{uuid}/messages/{messageId}/read', [MessagesController::class, 'markAsRead']);
    Route::post('/{uuid}/messages/{messageId}/unread', [MessagesController::class, 'markAsUnread']);
    Route::delete('/{uuid}/messages/{messageId}', [MessagesController::class, 'destroy']);

    // Attachments routes
    Route::get('/{uuid}/messages/{messageId}/attachments', [MessagesController::class, 'attachments']);

    // Read receipts routes
    Route::get('/{uuid}/messages/{messageId}/read-by', [MessagesController::class, 'readBy']);
    Route::get('/{uuid}/read-by', [MessagesController::class, 'conversationReadBy']);

    // Reactions routes
    Route::get('/{uuid}/messages/{messageId}/reactions', [MessagesController::class, 'reactions']);
    Route::post('/{uuid}/messages/{messageId}/reactions', [MessagesController::class, 'addReaction']);
    Route::delete('/{uuid}/messages/{messageId}/reactions/{reaction}', [MessagesController::class, 'removeReaction']);

    // Typing indicator
    Route::post('/{uuid}/typing', [MessagesController::class, 'typing']);
});
