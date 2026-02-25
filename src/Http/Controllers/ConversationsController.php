<?php

namespace Dominservice\Conversations\Http\Controllers;

use Illuminate\Http\Request;
use Dominservice\Conversations\Facade\ConversationsHooks;

class ConversationsController extends Controller
{
    /**
     * Display a listing of the conversations.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $userId = $this->resolveCurrentUserId();
        $relationType = $request->input('relation_type');
        $relationId = $request->input('relation_id');

        $conversations = app('conversations')->getConversations($userId, $relationType, $relationId);

        // Execute hook after retrieving conversations
        ConversationsHooks::execute('after_get_conversations', [
            'conversations' => $conversations,
            'user_id' => $userId,
            'relation_type' => $relationType,
            'relation_id' => $relationId,
        ]);

        return response()->json([
            'data' => $conversations,
        ]);
    }

    /**
     * Resolve contacts/users list for conversation picker.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function contacts(Request $request)
    {
        $authUser = $request->user();
        $provided = $this->executeIntegrationCallback(
            'conversations.integrations.ui.contacts_provider',
            [
                'authUser' => $authUser,
                'request' => $request,
            ],
            null
        );

        if (is_iterable($provided)) {
            $contacts = collect($provided)->values()->all();

            return response()->json([
                'contacts' => $contacts,
                'data' => $contacts,
            ]);
        }

        $contacts = collect();
        if (is_object($authUser) && method_exists($authUser, 'contacts')) {
            $contacts = $authUser->contacts()->get();
        } else {
            $userModelClass = (string) config('conversations.user_model', \App\Models\User::class);
            $keyName = $authUser->getKeyName();
            $contacts = $userModelClass::query()
                ->where($keyName, '!=', $authUser->{$keyName})
                ->limit(100)
                ->get();
        }

        $normalized = $contacts->map(function ($user) {
            $keyName = $user->getKeyName();
            $id = (string) ($user->{$keyName} ?? '');

            return [
                'id' => $id,
                'uuid' => (string) ($user->uuid ?? $id),
                'username' => method_exists($user, 'getUsername')
                    ? (string) $user->getUsername()
                    : (string) ($user->username ?? $user->name ?? '@user'),
                'full_name' => (string) ($user->full_name ?? $user->name ?? ''),
                'name' => (string) ($user->full_name ?? $user->name ?? ''),
                'avatar_path' => $this->normalizeAssetPath(
                    (string) ($user->avatar_path ?? ''),
                    (string) (config('global.empty_user_avatar') ?? '/assets/theme/media/logos/empty-user.webp')
                ),
                'url' => (string) ($user->url ?? '#'),
            ];
        })->values()->all();

        return response()->json([
            'contacts' => $normalized,
            'data' => $normalized,
        ]);
    }

    /**
     * Store a newly created conversation.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'users' => 'required|array',
            'users.*' => 'required',
            'content' => 'nullable|string',
            'relation_type' => 'nullable|string',
            'relation_id' => 'nullable',
        ]);

        $users = $request->input('users');
        $content = $request->input('content');
        $relationType = $request->input('relation_type');
        $relationId = $request->input('relation_id');

        $conversation = app('conversations')->create($users, $relationType, $relationId, $content, true);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.create_failed'),
            ], 422);
        }

        return response()->json([
            'data' => $conversation,
            'message' => trans('conversations::conversations.conversation.created'),
        ], 201);
    }

    /**
     * Start conversation by participants list (integration-friendly endpoint).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function start(Request $request)
    {
        $userId = $this->resolveCurrentUserId();
        $authUser = $request->user();

        $participants = $this->extractParticipants($request);
        if ($participants === []) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.participants_required'),
            ], 422);
        }

        if (!$this->isContactRelationshipAuthorized($authUser, $participants, $request)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.contacts_required'),
            ], 422);
        }

        $relationType = $request->input('relation_type');
        $relationId = $request->input('relation_id');
        $content = $request->input('content');
        $title = $request->input('title');
        $type = $request->input('type');

        $uuid = app('conversations')->getIdBetweenUsers(
            array_values(array_unique(array_merge($participants, [$userId]))),
            $relationType,
            $relationId
        );

        $created = false;
        if (!$uuid) {
            $conversation = app('conversations')->create($participants, $relationType, $relationId, $content, true);

            if (!$conversation) {
                return response()->json([
                    'message' => trans('conversations::conversations.conversation.create_failed'),
                ], 422);
            }

            if (!empty($type)) {
                $conversation->setType((string) $type);
            }

            if (!is_null($title)) {
                $conversation->title = (string) $title;
                $conversation->save();
            }

            $uuid = $conversation->uuid;
            $created = true;
        }

        app('conversations')->restoreConversationForUser($uuid, $userId);
        $this->autoAcceptRelationships($authUser, $participants, $request);

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

        return response()->json([
            'data' => [
                'uuid' => $uuid,
                'conversation' => $conversation,
                'created' => $created,
            ],
            'message' => trans('conversations::conversations.conversation.created'),
        ], $created ? 201 : 200);
    }

    /**
     * Normalize relative/absolute asset path to browser-safe URL.
     *
     * @param string|null $path
     * @param string|null $fallback
     * @return string
     */
    protected function normalizeAssetPath(?string $path, ?string $fallback = null): string
    {
        $value = trim((string) $path);
        if ($value === '') {
            $value = trim((string) $fallback);
        }
        if ($value === '') {
            $value = '/assets/theme/media/logos/empty-user.webp';
        }

        if (preg_match('~^(https?:)?//~i', $value) === 1 || str_starts_with($value, 'data:') || str_starts_with($value, 'blob:')) {
            return $value;
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        return asset($value);
    }

    /**
     * Mark all conversation messages as read by current user.
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function markConversationAsRead($uuid)
    {
        $userId = $this->resolveCurrentUserId();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        app('conversations')->markReadAll($uuid, $userId);

        return response()->json([
            'message' => trans('conversations::conversations.conversation.marked_read'),
        ]);
    }

    /**
     * Update conversation title.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTitle(Request $request, $uuid)
    {
        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $userId = $this->resolveCurrentUserId();
        $ownerRequired = (bool) config('conversations.integrations.conversation_start.owner_required_for_title_update', true);

        if (!app('conversations')->setConversationTitle($uuid, (string) $request->input('title'), $userId, $ownerRequired)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        $conversation = app('conversations')->get($uuid);
        $this->dispatchBusinessNotification('conversation.title_updated', [
            'conversation_uuid' => $uuid,
            'conversation' => $conversation,
            'user_id' => $userId,
            'title' => (string) $request->input('title'),
        ]);

        return response()->json([
            'data' => $conversation,
            'message' => trans('conversations::conversations.conversation.title_updated'),
        ]);
    }

    /**
     * Add participants to existing conversation.
     *
     * @param Request $request
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function addParticipants(Request $request, $uuid)
    {
        $userId = $this->resolveCurrentUserId();
        $authUser = $request->user();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        $ownerRequired = (bool) config('conversations.integrations.conversation_start.owner_required_for_add_participants', true);
        if ($ownerRequired && !app('conversations')->isOwner($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        $participants = $this->extractParticipants($request);
        if ($participants === []) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.participants_required'),
            ], 422);
        }

        if (!$this->isContactRelationshipAuthorized($authUser, $participants, $request)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.contacts_required'),
            ], 422);
        }

        $addedUsers = app('conversations')->addUsers($uuid, $participants);
        foreach ($addedUsers as $addedUserId) {
            app('conversations')->restoreConversationForUser($uuid, $addedUserId);
        }

        $this->autoAcceptRelationships($authUser, $addedUsers, $request);

        $conversation = app('conversations')->get($uuid);
        $this->dispatchBusinessNotification('conversation.participants_added', [
            'conversation_uuid' => $uuid,
            'conversation' => $conversation,
            'user_id' => $userId,
            'participants' => $addedUsers,
        ]);

        return response()->json([
            'data' => [
                'conversation' => $conversation,
                'added_users' => $addedUsers,
            ],
            'message' => trans('conversations::conversations.conversation.participants_added'),
        ]);
    }

    /**
     * Display the specified conversation.
     *
     * @param  string  $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($uuid)
    {
        $userId = $this->resolveCurrentUserId();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        // Load relations and users
        $conversation->load(['users', 'relations']);
        app('conversations')->getRelations($conversation);

        // Execute hook after retrieving conversation
        ConversationsHooks::execute('after_get_conversation', [
            'conversation' => $conversation,
            'user_id' => $userId,
        ]);

        return response()->json([
            'data' => $conversation,
        ]);
    }

    /**
     * Remove the specified conversation.
     *
     * @param  string  $uuid
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($uuid)
    {
        $userId = $this->resolveCurrentUserId();
        $conversation = app('conversations')->get($uuid);

        if (!$conversation) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.not_found'),
            ], 404);
        }

        // Check if user is part of the conversation
        if (!app('conversations')->existsUser($uuid, $userId)) {
            return response()->json([
                'message' => trans('conversations::conversations.conversation.unauthorized'),
            ], 403);
        }

        app('conversations')->delete($uuid, $userId);

        return response()->json([
            'message' => trans('conversations::conversations.conversation.deleted'),
        ]);
    }

    /**
     * @param Request $request
     * @return array<int, mixed>
     */
    private function extractParticipants(Request $request): array
    {
        $rawParticipants = $request->input('users', $request->input('participants', $request->input('contact_uuid', [])));
        if (!is_array($rawParticipants)) {
            $rawParticipants = [$rawParticipants];
        }

        $currentUserId = (string) $this->resolveCurrentUserId();
        $participants = array_values(array_unique(array_filter($rawParticipants, static function ($participant) use ($currentUserId) {
            if (is_null($participant) || $participant === '') {
                return false;
            }

            return (string) $participant !== $currentUserId;
        })));

        return array_values($participants);
    }

    /**
     * @param mixed $authUser
     * @param array<int, mixed> $participants
     * @param Request $request
     * @return bool
     */
    private function isContactRelationshipAuthorized($authUser, array $participants, Request $request): bool
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
     * @return void
     */
    private function autoAcceptRelationships($authUser, array $participants, Request $request): void
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
                'relationType' => $request->input('relation_type'),
                'relationId' => $request->input('relation_id'),
            ]
        );
    }
}
