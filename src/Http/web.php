<?php

use Illuminate\Support\Facades\Route;
use Dominservice\Conversations\Http\Controllers\Web\ConversationPanelController;

$enabled = (bool) config('conversations.web.enabled', true);
if (!$enabled) {
    return;
}

$prefix = trim((string) config('conversations.web.prefix', 'conversation'), '/');
$middleware = config('conversations.web.middleware', ['web', 'auth']);
$routeNamePrefix = (string) config('conversations.web.route_name_prefix', 'conversations.web.');

Route::group([
    'prefix' => $prefix,
    'middleware' => $middleware,
    'as' => $routeNamePrefix,
], function () {
    Route::get('/new/{userIdentifier}', [ConversationPanelController::class, 'create'])->name('create');
    Route::get('/new/{userIdentifier}/{relationType}/{relationId}', [ConversationPanelController::class, 'create'])->name('createWithRelation');
    Route::get('/{uuid?}', [ConversationPanelController::class, 'index'])->name('index');
    Route::delete('/{uuid}', [ConversationPanelController::class, 'delete'])->name('delete');
});

