<?php

use Illuminate\Support\Facades\Route;
use Dominservice\Conversations\Http\Controllers\ConversationsController;
use Dominservice\Conversations\Http\Controllers\MessagesController;

$prefix = config('conversations.api.prefix', 'api/conversations');
$middleware = config('conversations.api.middleware', ['api', 'auth:api']);

Route::group(['prefix' => $prefix, 'middleware' => $middleware, 'as' => 'conversations.'], function () {
    // Conversations routes
    Route::get('/', [ConversationsController::class, 'index'])->name('index');
    Route::get('/contacts', [ConversationsController::class, 'contacts'])->name('contacts');
    Route::post('/', [ConversationsController::class, 'store'])->name('store');
    Route::post('/start', [ConversationsController::class, 'start'])->name('start');
    // Backward compatible alias
    Route::post('/new', [ConversationsController::class, 'start'])->name('start.legacy');
    Route::get('/{uuid}', [ConversationsController::class, 'show'])->name('show');
    Route::delete('/{uuid}', [ConversationsController::class, 'destroy'])->name('destroy');
    Route::post('/{uuid}/read', [ConversationsController::class, 'markConversationAsRead'])->name('markReadAll');
    // Backward compatible alias
    Route::post('/{uuid}/mark-as-read', [ConversationsController::class, 'markConversationAsRead'])->name('markReadAll.legacy');
    Route::put('/{uuid}/title', [ConversationsController::class, 'updateTitle'])->name('updateTitle');
    Route::put('/{uuid}/participants', [ConversationsController::class, 'addParticipants'])->name('addParticipants');
    // Backward compatible alias
    Route::put('/{uuid}/add-participant', [ConversationsController::class, 'addParticipants'])->name('addParticipants.legacy');
    // Backward compatible alias (underscore version used by some older integrations)
    Route::put('/{uuid}/add_participant', [ConversationsController::class, 'addParticipants'])->name('addParticipants.legacy_underscore');

    // Messages routes
    Route::get('/{uuid}/messages', [MessagesController::class, 'index'])->name('messages.index');
    Route::post('/{uuid}/messages', [MessagesController::class, 'store'])->name('messages.store');
    Route::get('/{uuid}/messages/unread', [MessagesController::class, 'unread'])->name('messages.unread');
    Route::post('/{uuid}/messages/{messageId}/read', [MessagesController::class, 'markAsRead'])->name('messages.markRead');
    Route::post('/{uuid}/messages/{messageId}/unread', [MessagesController::class, 'markAsUnread'])->name('messages.markUnread');
    Route::delete('/{uuid}/messages/{messageId}', [MessagesController::class, 'destroy'])->name('messages.destroy');

    // Attachments routes
    Route::get('/{uuid}/messages/{messageId}/attachments', [MessagesController::class, 'attachments'])->name('messages.attachments');

    // Read receipts routes
    Route::get('/{uuid}/messages/{messageId}/read-by', [MessagesController::class, 'readBy'])->name('messages.readBy');
    Route::get('/{uuid}/read-by', [MessagesController::class, 'conversationReadBy'])->name('conversationReadBy');

    // Thread routes
    Route::get('/{uuid}/messages/{messageId}/thread', [MessagesController::class, 'thread'])->name('messages.thread');
    Route::post('/{uuid}/messages/{messageId}/reply', [MessagesController::class, 'reply'])->name('messages.reply');

    // Reactions routes
    Route::get('/{uuid}/messages/{messageId}/reactions', [MessagesController::class, 'reactions'])->name('messages.reactions');
    Route::post('/{uuid}/messages/{messageId}/reactions', [MessagesController::class, 'addReaction'])->name('messages.addReaction');
    Route::delete('/{uuid}/messages/{messageId}/reactions/{reaction}', [MessagesController::class, 'removeReaction'])->name('messages.removeReaction');

    // Message editing routes
    Route::put('/{uuid}/messages/{messageId}', [MessagesController::class, 'update'])->name('messages.update');
    Route::get('/{uuid}/messages/{messageId}/editable', [MessagesController::class, 'checkEditable'])->name('messages.checkEditable');

    // Typing indicator
    Route::post('/{uuid}/typing', [MessagesController::class, 'typing'])->name('typing');
});
