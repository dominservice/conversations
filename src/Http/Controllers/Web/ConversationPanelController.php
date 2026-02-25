<?php

namespace Dominservice\Conversations\Http\Controllers\Web;

use Dominservice\Conversations\Http\Controllers\Controller;
use Dominservice\Conversations\Models\Eloquent\ConversationUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ConversationPanelController extends Controller
{
    /**
     * Render conversation panel.
     *
     * @param Request $request
     * @param string|null $uuid
     * @return View
     */
    public function index(Request $request, ?string $uuid = null): View
    {
        $authUser = $request->user();
        $userId = $this->resolveCurrentUserId();
        $conversations = $this->resolveConversationsForPanel($request, $authUser, $userId);

        $currentConversation = null;
        if (!empty($uuid)) {
            $currentConversation = $conversations->firstWhere('uuid', $uuid);
        }
        if (!$currentConversation) {
            $currentConversation = $conversations->first();
        }

        if ($currentConversation) {
            app('conversations')->markReadAll($currentConversation->uuid, $userId);
        }

        $conversationApiPrefix = trim((string) config('conversations.api.prefix', 'api/conversations'), '/');
        $conversationApiBase = url($conversationApiPrefix);
        $contactsEndpoint = (string) (config('conversations.ui.contacts_endpoint')
            ?: ($conversationApiBase . '/contacts'));

        $participantsMap = [];
        if ($currentConversation) {
            foreach ($currentConversation->users ?? [] as $conversationUser) {
                $id = (string) ($conversationUser->{$conversationUser->getKeyName()} ?? '');
                if ($id === '') {
                    continue;
                }

                $participantsMap[$id] = [
                    'id' => $id,
                    'uuid' => (string) ($conversationUser->uuid ?? $id),
                    'username' => $this->resolveUsername($conversationUser),
                    'full_name' => $this->resolveFullName($conversationUser),
                    'name' => $this->resolveFullName($conversationUser),
                    'avatar_path' => $this->resolveAvatar($conversationUser),
                    'url' => $this->resolveProfileUrl($conversationUser),
                ];
            }
        }

        $currentUserMap = [
            'id' => (string) $userId,
            'uuid' => (string) ($authUser->uuid ?? $userId),
            'username' => $this->resolveUsername($authUser),
            'full_name' => $this->resolveFullName($authUser),
            'name' => $this->resolveFullName($authUser),
            'avatar_path' => $this->resolveAvatar($authUser),
            'url' => $this->resolveProfileUrl($authUser),
        ];

        return view((string) config('conversations.ui.view', 'conversations::panel.index'), [
            'conversations' => $conversations,
            'currentConversation' => $currentConversation,
            'conversationApiBase' => $conversationApiBase,
            'contactsEndpoint' => $contactsEndpoint,
            'participantsMap' => $participantsMap,
            'currentUserMap' => $currentUserMap,
            'webRouteIndexName' => $this->webRouteName('index'),
            'webRouteDeleteName' => $this->webRouteName('delete'),
            'webRouteCreateName' => $this->webRouteName('create'),
        ]);
    }

    /**
     * Create or open conversation with target user.
     *
     * @param Request $request
     * @param string $userIdentifier
     * @param string|null $relationType
     * @param string|null $relationId
     * @return RedirectResponse
     */
    public function create(Request $request, string $userIdentifier, ?string $relationType = null, ?string $relationId = null): RedirectResponse
    {
        $authUser = $request->user();
        $userId = $this->resolveCurrentUserId();
        $targetUser = $this->resolveTargetUser($userIdentifier);

        if (!$targetUser) {
            return redirect()->route($this->webRouteName('index'))
                ->withErrors(['conversation' => trans('conversations::conversations.user.not_found')]);
        }

        $targetUserId = (string) ($targetUser->{$targetUser->getKeyName()} ?? '');
        if ($targetUserId === '' || $targetUserId === (string) $userId) {
            return redirect()->route($this->webRouteName('index'))
                ->withErrors(['conversation' => trans('conversations::conversations.conversation.participants_required')]);
        }

        $participants = [$targetUserId];
        if (!$this->isContactRelationshipAuthorized($authUser, $participants, $request)) {
            return redirect()->route($this->webRouteName('index'))
                ->withErrors(['conversation' => trans('conversations::conversations.conversation.contacts_required')]);
        }

        $uuid = app('conversations')->getIdBetweenUsers(
            array_values(array_unique(array_merge($participants, [(string) $userId]))),
            $relationType,
            $relationId
        );

        $created = false;
        if (!$uuid) {
            $conversation = app('conversations')->create(
                $participants,
                $relationType,
                $relationId,
                $request->input('content'),
                true
            );

            if (!$conversation) {
                return redirect()->route($this->webRouteName('index'))
                    ->withErrors(['conversation' => trans('conversations::conversations.conversation.create_failed')]);
            }

            if ($request->filled('type')) {
                $conversation->setType((string) $request->input('type'));
            }

            if ($request->filled('title')) {
                $conversation->title = (string) $request->input('title');
                $conversation->save();
            }

            $uuid = $conversation->uuid;
            $created = true;
        }

        app('conversations')->restoreConversationForUser($uuid, $userId);
        $this->autoAcceptRelationships($authUser, $participants, $request, $relationType, $relationId);

        $conversation = app('conversations')->get($uuid);
        $this->dispatchBusinessNotification('conversation.started', [
            'conversation_uuid' => $uuid,
            'conversation' => $conversation,
            'created' => $created,
            'initiator_id' => $userId,
            'participants' => $participants,
            'relation_type' => $relationType,
            'relation_id' => $relationId,
        ]);

        return redirect()->route($this->webRouteName('index'), $uuid);
    }

    /**
     * Soft delete conversation for current user.
     *
     * @param Request $request
     * @param string $uuid
     * @return RedirectResponse
     */
    public function delete(Request $request, string $uuid): RedirectResponse
    {
        $authUser = $request->user();
        $userId = $this->resolveCurrentUserId();

        $conversation = app('conversations')->get($uuid);
        if (!$conversation || !app('conversations')->existsUser($uuid, $userId)) {
            return redirect()->route($this->webRouteName('index'))
                ->withErrors(['conversation' => trans('conversations::conversations.conversation.not_found')]);
        }

        $customDeleteResult = $this->executeIntegrationCallback(
            'conversations.integrations.ui.conversation_delete',
            [
                'conversationUuid' => $uuid,
                'authUser' => $authUser,
                'request' => $request,
            ],
            null
        );

        if ($customDeleteResult === false) {
            return redirect()->route($this->webRouteName('index'))
                ->withErrors(['conversation' => trans('conversations::conversations.conversation.unauthorized')]);
        }

        $conversationUsersTable = (string) config('conversations.tables.conversation_users', 'conversation_users');
        if (Schema::hasColumn($conversationUsersTable, 'is_conversation_deleted')) {
            ConversationUser::query()
                ->where('conversation_uuid', $uuid)
                ->where(get_user_key(), $userId)
                ->update(['is_conversation_deleted' => 1]);

            $hasVisibleUsers = ConversationUser::query()
                ->where('conversation_uuid', $uuid)
                ->where('is_conversation_deleted', 0)
                ->exists();

            if (!$hasVisibleUsers) {
                $conversation->messages()->delete();
                $conversation->relations()->delete();
                ConversationUser::query()->where('conversation_uuid', $uuid)->delete();
                $conversation->forceDelete();
            }
        } else {
            app('conversations')->delete($uuid, $userId);
        }

        return redirect()->route($this->webRouteName('index'))
            ->with('success', trans('conversations::conversations.conversation.deleted'));
    }

    /**
     * @param Request $request
     * @param mixed $authUser
     * @param mixed $userId
     * @return Collection
     */
    protected function resolveConversationsForPanel(Request $request, $authUser, $userId): Collection
    {
        $provided = $this->executeIntegrationCallback(
            'conversations.integrations.ui.conversations_provider',
            [
                'authUser' => $authUser,
                'request' => $request,
            ],
            null
        );

        if ($provided instanceof AbstractPaginator) {
            return collect($provided->items());
        }
        if ($provided instanceof Collection) {
            return $provided->values();
        }
        if (is_iterable($provided)) {
            return collect($provided)->values();
        }

        $conversations = collect();
        if (is_object($authUser) && method_exists($authUser, 'conversationsByLastMessage')) {
            $relation = $authUser->conversationsByLastMessage();
            if (method_exists($relation, 'get')) {
                $conversations = $relation->get();
            }
        } elseif (is_object($authUser) && method_exists($authUser, 'conversations')) {
            $relation = $authUser->conversations();
            if (method_exists($relation, 'with') && method_exists($relation, 'get')) {
                $conversations = $relation
                    ->with(['type', 'owner', 'participants', 'users', 'lastMessage' => function ($query) {
                        $query->with('sender');
                    }])
                    ->get();
            } elseif (method_exists($relation, 'get')) {
                $conversations = $relation->get();
            }
        } else {
            $conversations = collect(app('conversations')->getConversations($userId));
        }

        return $conversations
            ->sortByDesc(function ($conversation) {
                return $conversation->last_massage_created_at
                    ?? $conversation->lastMessage?->created_at
                    ?? $conversation->updated_at
                    ?? null;
            })
            ->values();
    }

    /**
     * @param string $identifier
     * @return mixed|null
     */
    protected function resolveTargetUser(string $identifier)
    {
        $userModelClass = (string) config('conversations.user_model', \App\Models\User::class);
        if (!class_exists($userModelClass)) {
            return null;
        }

        $query = $userModelClass::query();
        $model = new $userModelClass();
        $table = $model->getTable();
        $primaryKey = $model->getKeyName();

        $target = $query->where($primaryKey, $identifier)->first();

        if (!$target && Schema::hasColumn($table, 'uuid')) {
            $target = $userModelClass::query()->where('uuid', $identifier)->first();
        }
        if (!$target && Schema::hasColumn($table, 'id')) {
            $target = $userModelClass::query()->where('id', $identifier)->first();
        }

        return $target;
    }

    /**
     * @param mixed $authUser
     * @param array<int, mixed> $participants
     * @param Request $request
     * @return bool
     */
    protected function isContactRelationshipAuthorized($authUser, array $participants, Request $request): bool
    {
        $mustBeContacts = (bool) config('conversations.integrations.conversation_start.require_contact_relationship', false);
        if (!$mustBeContacts) {
            return true;
        }

        $authorized = $this->executeIntegrationCallback(
            'conversations.integrations.conversation_start.contact_authorizer',
            [
                'authUser' => $authUser,
                'participants' => $participants,
                'request' => $request,
            ],
            null
        );

        if (is_bool($authorized)) {
            return $authorized;
        }

        $relationMethod = (string) config('conversations.integrations.conversation_start.contact_relation_method', '');
        if ($relationMethod !== '' && is_object($authUser) && method_exists($authUser, $relationMethod)) {
            $relationQuery = $authUser->{$relationMethod}();
            $relationKey = (string) config('conversations.integrations.conversation_start.contact_relation_key', $authUser->getKeyName());

            if (method_exists($relationQuery, 'whereIn') && method_exists($relationQuery, 'count')) {
                return (clone $relationQuery)->whereIn($relationKey, $participants)->count() === count($participants);
            }
        }

        return false;
    }

    /**
     * @param mixed $authUser
     * @param array<int, mixed> $participants
     * @param Request $request
     * @param string|null $relationType
     * @param mixed|null $relationId
     * @return void
     */
    protected function autoAcceptRelationships($authUser, array $participants, Request $request, ?string $relationType = null, $relationId = null): void
    {
        if (!(bool) config('conversations.integrations.conversation_start.auto_accept_relationships', false)) {
            return;
        }

        $this->executeIntegrationCallback(
            'conversations.integrations.conversation_start.auto_acceptor',
            [
                'authUser' => $authUser,
                'participants' => $participants,
                'request' => $request,
                'relationType' => $relationType,
                'relationId' => $relationId,
            ]
        );
    }

    /**
     * @param mixed $user
     * @return string
     */
    protected function resolveUsername($user): string
    {
        if (is_object($user) && method_exists($user, 'getUsername')) {
            return (string) $user->getUsername();
        }

        return (string) ($user->username ?? $user->name ?? '@user');
    }

    /**
     * @param mixed $user
     * @return string
     */
    protected function resolveFullName($user): string
    {
        return (string) ($user->full_name ?? $user->name ?? $this->resolveUsername($user));
    }

    /**
     * @param mixed $user
     * @return string
     */
    protected function resolveAvatar($user): string
    {
        return (string) ($user->avatar_path ?? config('global.empty_user_avatar') ?? '/assets/theme/media/logos/empty-user.webp');
    }

    /**
     * @param mixed $user
     * @return string
     */
    protected function resolveProfileUrl($user): string
    {
        return (string) ($user->url ?? '#');
    }

    /**
     * @param string $route
     * @return string
     */
    protected function webRouteName(string $route): string
    {
        $prefix = (string) config('conversations.web.route_name_prefix', 'conversations.web.');

        return $prefix . $route;
    }
}

