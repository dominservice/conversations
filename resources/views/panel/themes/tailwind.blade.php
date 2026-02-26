@php
    $authUser = request()->user();
    $authUserId = (string) ($authUser?->{$authUser?->getKeyName() ?? 'id'} ?? $authUser?->uuid ?? $authUser?->id ?? '');

    $resolveConversationParticipant = static function ($conversation) use ($authUserId) {
        $users = collect($conversation->users ?? []);
        if ($users->isEmpty()) {
            $users = collect($conversation->participants ?? []);
        }

        $participant = $users->first(function ($user) use ($authUserId) {
            $id = (string) ($user?->{$user?->getKeyName() ?? 'id'} ?? $user->uuid ?? $user->id ?? '');
            return $id !== '' && $id !== $authUserId;
        });

        return $participant ?: $users->first();
    };

    $resolveConversationUserName = static function ($user): string {
        if (!$user) {
            return '';
        }

        if (method_exists($user, 'getUsername')) {
            return (string) $user->getUsername();
        }

        return (string) ($user->username ?? $user->full_name ?? $user->name ?? '');
    };

    $resolveConversationAvatar = static function ($user) use ($normalizeAssetUrl, $defaultAvatarUrl): string {
        if ($user && !empty($user->avatar_path)) {
            return $normalizeAssetUrl((string) $user->avatar_path, $defaultAvatarUrl);
        }

        return $defaultAvatarUrl;
    };

    $resolveConversationUuid = static function ($conversation): string {
        $value = data_get($conversation, 'uuid')
            ?? data_get($conversation, 'conversation_uuid')
            ?? data_get($conversation, 'id')
            ?? data_get($conversation, 'pivot.conversation_uuid');

        return (string) ($value ?? '');
    };

    $resolveConversationUsersCount = static function ($conversation): int {
        $users = collect($conversation->users ?? []);
        if ($users->isNotEmpty()) {
            return $users->count();
        }

        $participants = collect($conversation->participants ?? []);
        return $participants->count();
    };

    $isGroupConversation = static function ($conversation) use ($resolveConversationUsersCount): bool {
        $typeName = (string) ($conversation->type?->name ?? '');
        if ($typeName === 'support') {
            return false;
        }

        if (in_array($typeName, ['single', 'expert', 'cooperation', 'mail'], true)) {
            return false;
        }

        return $resolveConversationUsersCount($conversation) > 2;
    };

    $relationLabelsConfig = (array) config('conversations.ui.relation_labels', []);
    $normalizeRelationKey = static function (?string $type): string {
        $value = trim(mb_strtolower((string) $type));
        if ($value === '') {
            return '';
        }

        $value = str_replace('\\', '.', $value);
        $segments = array_values(array_filter(explode('.', $value)));

        return (string) ($segments ? end($segments) : $value);
    };
    $resolveConversationRelation = static function ($conversation) use ($normalizeRelationKey, $relationLabelsConfig): ?array {
        $relations = collect($conversation->relations ?? []);
        $relation = $relations->first();

        $parent = $relation?->parent ?? $relation?->uuidParent ?? $relation?->ulidParent ?? null;
        $rawType = (string) (
            $relation?->parent_type
            ?? $relation?->uuid_parent_type
            ?? $relation?->ulid_parent_type
            ?? data_get($conversation, 'relation_type')
            ?? ($parent ? get_class($parent) : '')
        );
        $typeKey = $normalizeRelationKey($rawType);

        $announcementTypeRaw = (string) (
            data_get($parent, 'type')
            ?? data_get($parent, 'announcement_type')
            ?? data_get($parent, 'announcementType')
            ?? ''
        );
        $announcementType = $normalizeRelationKey($announcementTypeRaw);

        if ($typeKey === '' && $announcementType === '') {
            return null;
        }

        $canonicalType = $typeKey;
        if (str_contains($typeKey, 'community')) {
            $canonicalType = 'communities';
        } elseif (str_contains($typeKey, 'announcement')) {
            if (in_array($announcementType, ['job', 'jobs', 'job_offers', 'work', 'employment'], true)) {
                $canonicalType = 'job_offers';
            } elseif (in_array($announcementType, ['task', 'tasks', 'assignment', 'assignments', 'order', 'orders', 'service', 'services', 'commission'], true)) {
                $canonicalType = 'assignments';
            } elseif (in_array($announcementType, ['miscellaneous', 'misc', 'other', 'others', 'various'], true)) {
                $canonicalType = 'miscellaneous';
            } else {
                $canonicalType = 'announcements';
            }
        } elseif (str_contains($typeKey, 'job') || str_contains($typeKey, 'employment') || str_contains($typeKey, 'work')) {
            $canonicalType = 'job_offers';
        } elseif (str_contains($typeKey, 'task') || str_contains($typeKey, 'assign') || str_contains($typeKey, 'order') || str_contains($typeKey, 'service')) {
            $canonicalType = 'assignments';
        } elseif (str_contains($typeKey, 'misc') || str_contains($typeKey, 'other') || str_contains($typeKey, 'various')) {
            $canonicalType = 'miscellaneous';
        } elseif ($typeKey === 'announcements') {
            $canonicalType = 'announcements';
        }

        $defaultLabels = [
            'announcements' => __('Announcements'),
            'assignments' => __('Assignments'),
            'job_offers' => __('Job offers'),
            'communities' => __('Communities'),
            'miscellaneous' => __('Miscellaneous'),
        ];

        $configuredLabel = (string) ($relationLabelsConfig[$canonicalType] ?? $relationLabelsConfig[$typeKey] ?? '');
        $label = $configuredLabel !== ''
            ? __($configuredLabel)
            : ($defaultLabels[$canonicalType] ?? Str::headline(str_replace('_', ' ', $canonicalType)));

        $title = trim((string) (data_get($parent, 'name') ?? data_get($parent, 'title') ?? data_get($parent, 'slug') ?? ''));
        $url = trim((string) (data_get($parent, 'url') ?? ''));
        $description = $title !== '' ? ($label . ': ' . $title) : $label;

        return [
            'key' => $canonicalType,
            'label' => $label,
            'title' => $title,
            'url' => $url,
            'description' => $description,
        ];
    };

    $currentConversationPrimaryParticipant = $currentConversation ? $resolveConversationParticipant($currentConversation) : null;
    $currentConversationUuid = $currentConversation ? $resolveConversationUuid($currentConversation) : '';
    $currentConversationIsGroup = $currentConversation ? $isGroupConversation($currentConversation) : false;
    $currentConversationRelation = $currentConversation ? $resolveConversationRelation($currentConversation) : null;
    $authUserIdentifiers = collect([
        $authUser?->{$authUser?->getKeyName() ?? 'id'} ?? null,
        $authUser?->id ?? null,
        $authUser?->uuid ?? null,
    ])->map(static fn ($value) => trim((string) $value))->filter()->unique()->values()->all();
    $ownerIdentifiers = collect([
        $currentConversation?->owner_uuid ?? null,
        $currentConversation?->owner?->{$currentConversation?->owner?->getKeyName() ?? 'id'} ?? null,
        $currentConversation?->owner?->id ?? null,
        $currentConversation?->owner?->uuid ?? null,
    ])->map(static fn ($value) => trim((string) $value))->filter()->unique()->values()->all();
    $canManageConversation = !empty(array_intersect($authUserIdentifiers, $ownerIdentifiers));
    $panelCss = (string) config('conversations.ui.assets.css', 'assets/theme/css/conversation.css');
    $panelJs = (string) config('conversations.ui.assets.js', 'assets/theme/js/conversations.js');
@endphp

<x-slot name="css">
    <link rel="stylesheet" href="{{ asset($panelCss) }}">
</x-slot>

<div class="content conversations-panel-wrap">
    <div class="conversations-panel tw-conversations-panel">
        <div class="conversations-container conv-shell tw-shell">
            <aside class="conversations-list conv-sidebar tw-sidebar @if(!$currentConversation) is-open @endif" id="convSidebar">
                <div class="p-3 border-b border-slate-200">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-semibold m-0 flex-1">@lang('Conversations')</h3>
                        <button class="conversation-new-button inline-flex items-center justify-center h-8 w-8 rounded bg-blue-600 text-white" type="button" title="@lang('New conversation')">+</button>
                    </div>
                    <div class="mt-3">
                        <input type="search" class="conversation-search-input w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="@lang('Search')...">
                    </div>
                </div>

                <div class="conversations-list-items conv-list">
                    <ul class="list-unstyled mb-0">
                        @foreach($conversations as $conversation)
                            @php
                                $conversationUuid = $resolveConversationUuid($conversation);
                                $conversationPrimaryParticipant = $resolveConversationParticipant($conversation);
                                $conversationTitle = (string) ($conversation->title ?? '');
                                $conversationPrimaryParticipantName = $resolveConversationUserName($conversationPrimaryParticipant);
                                $conversationIsGroup = $isGroupConversation($conversation);
                                $conversationTypeName = (string) ($conversation->type?->name ?? '');
                                $conversationName = $conversationTypeName === 'support'
                                    ? __('Support')
                                    : ($conversationIsGroup
                                        ? ($conversationTitle !== '' ? $conversationTitle : __('Group conversation'))
                                        : ($conversationPrimaryParticipantName !== '' ? $conversationPrimaryParticipantName : ($conversationTitle !== '' ? $conversationTitle : __('Conversation'))));
                                $lastMessage = data_get($conversation, 'lastMessage')
                                    ?? data_get($conversation, 'last_message');
                                $previewText = trim((string) (
                                    data_get($lastMessage, 'content')
                                    ?? data_get($conversation, 'last_message_content')
                                    ?? ''
                                ));
                                $conversationRelation = $resolveConversationRelation($conversation);
                                $lastMessageType = mb_strtolower(trim((string) (
                                    data_get($lastMessage, 'message_type')
                                    ?? data_get($conversation, 'last_message_type')
                                    ?? ''
                                )));
                                $lastMessageSender = data_get($lastMessage, 'sender');
                                $lastMessageSenderId = (string) ($lastMessageSender?->{$lastMessageSender?->getKeyName() ?? 'id'} ?? $lastMessageSender?->uuid ?? $lastMessageSender?->id ?? '');
                                $previewTextForDisplay = Str::limit(
                                    $previewText !== ''
                                        ? $previewText
                                        : (in_array($lastMessageType, ['attachment', 'file', 'image', 'document', 'video', 'audio'], true) ? __('Attachment') : ''),
                                    60
                                );
                            @endphp
                            <li
                                class="contact conv-list-item @if($currentConversation && $conversationUuid !== '' && $conversationUuid === $currentConversationUuid) active @endif"
                                data-conversation-uuid="{{ $conversationUuid }}"
                                data-conversation-is-group="{{ $conversationIsGroup ? 1 : 0 }}"
                                data-conversation-title="{{ strtolower($conversationTitle . ' ' . $conversationName . ' ' . $previewTextForDisplay . ' ' . ($conversationRelation['label'] ?? '') . ' ' . ($conversationRelation['title'] ?? '')) }}"
                            >
                                <a href="{{ $conversationUuid !== '' ? route($webRouteIndexName, ['uuid' => $conversationUuid]) : route($webRouteIndexName) }}" class="flex gap-2 items-start no-underline">
                                    @if($conversationIsGroup)
                                        <img src="{{ asset('assets/theme/media/group.webp') }}" class="rounded-full conv-avatar" alt="{{ $conversationName }}">
                                    @else
                                        <img src="{{ $resolveConversationAvatar($conversationPrimaryParticipant) }}" class="rounded-full conv-avatar" alt="{{ $conversationName }}">
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-2 min-w-0 name-row">
                                                <p class="name m-0 truncate font-semibold">{{ $conversationName }}</p>
                                                @if($conversationRelation)
                                                    <span class="conversation-relation-badge inline-flex max-w-full items-center rounded-full border px-2 py-0.5 text-[11px] leading-4"
                                                          title="{{ $conversationRelation['description'] }}">
                                                        {{ $conversationRelation['label'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="conversation-count-new-messages inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs text-white"
                                                  @if(!$conversation->hasUnreadedMessages()) style="display:none" @endif>{{ $conversation->getCountUnreadedMessages() }}</span>
                                        </div>
                                        <p class="preview m-0 text-xs text-slate-500 truncate">
                                            @if($lastMessageSenderId !== '' && $lastMessageSenderId === $authUserId)
                                                <span>@lang('You'):</span>
                                            @elseif($lastMessageSender)
                                                <span>{{ $resolveConversationUserName($lastMessageSender) ?: '@user' }}:</span>
                                            @endif
                                            {{ $previewTextForDisplay }}
                                        </p>
                                    </div>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if($currentConversationUuid !== '')
                    <div class="conv-sidebar-footer border-top p-2">
                        <form method="POST" action="{{ route($webRouteDeleteName, ['uuid' => $currentConversationUuid]) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-light-danger w-100 dso-delete-item" title="@lang('Delete conversation')">
                                <i class="fa fa-trash"></i>
                                <span class="ms-1">@lang('Delete conversation')</span>
                            </button>
                        </form>
                    </div>
                @endif
            </aside>

            <section class="content conv-main tw-main">
                @if($currentConversation && $currentConversationUuid !== '')
                    <div class="contact-profile conv-header border-b border-slate-200">
                        <div class="flex items-center gap-2 p-2">
                            <button type="button" class="conv-mobile-back inline-flex items-center justify-center h-8 w-8 rounded border border-slate-200" data-conv-toggle-sidebar>&larr;</button>
                            <span class="conversation-participants inline-flex items-center">
                                @foreach($currentConversation->participants as $participant)
                                    <a href="{{ $participant->url ?? '#' }}" target="_blank" data-participant-uuid="{{ $participant->uuid }}">
                                        <img src="{{ $resolveConversationAvatar($participant) }}" alt="{{ $resolveConversationUserName($participant) ?: '@user' }}">
                                    </a>
                                @endforeach
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="m-0 truncate text-sm font-semibold">
                                    @if(!$currentConversationIsGroup && $currentConversation->type?->name !== 'support')
                                        {{ $resolveConversationUserName($currentConversationPrimaryParticipant) ?: ($currentConversation->title ?: __('Conversation')) }}
                                    @elseif($currentConversation->type?->name === 'support')
                                        @lang('Support')
                                    @else
                                        @lang('Group conversation')
                                    @endif
                                </p>
                                <p class="m-0 truncate text-xs text-slate-500 conversation-title">{{ $currentConversation->title ?? '' }}</p>
                                @if($currentConversationRelation)
                                    <p class="m-0 truncate text-xs text-slate-500 conversation-relation">
                                        <span>@lang('Relation'):</span>
                                        @if(($currentConversationRelation['url'] ?? '') !== '')
                                            <a href="{{ $currentConversationRelation['url'] }}" class="no-underline" target="_blank" rel="noopener">
                                                {{ $currentConversationRelation['label'] }}
                                            </a>
                                        @else
                                            {{ $currentConversationRelation['label'] }}
                                        @endif
                                        @if(($currentConversationRelation['title'] ?? '') !== '')
                                            <span class="conversation-relation-title">- {{ $currentConversationRelation['title'] }}</span>
                                        @endif
                                    </p>
                                @endif
                            </div>

                            @if($currentConversation->type?->name !== 'support' && $canManageConversation)
                                <span class="conversation-actions d-flex gap-1">
                                    <button class="btn btn-sm btn-outline-primary conversation-change-title" type="button" title="@lang('Edit')">
                                        <i class="fa fa-edit"></i>
                                    </button>
                                    @if($currentConversationIsGroup)
                                        <button class="btn btn-sm btn-outline-success conversation-add-participant" type="button" title="@lang('Add')">
                                            <i class="fa fa-user-plus"></i>
                                        </button>
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="conversations-messages-items conv-messages">
                        <ul class="list-unstyled mb-0"></ul>
                    </div>
                    <div class="message-input conv-composer border-t border-slate-200">
                        <div class="wrap flex items-center gap-2 p-2">
                            <input type="text" class="flex-1 rounded border border-slate-300 px-3 py-2 text-sm" placeholder="@lang('Write your message...')" name="message">
                            <input type="file" name="attachments[]" class="d-none conversation-attachments-input" multiple>
                            <button class="conversation-attachment-trigger inline-flex items-center justify-center h-9 w-9 rounded border border-slate-300" type="button" title="@lang('Add attachments')">
                                <i class="fa fa-paperclip"></i>
                            </button>
                            <button class="conversations-button inline-flex items-center justify-center h-9 w-9 rounded bg-blue-600 text-white" type="button">
                                <span class="conversation-save-label"><i class="fa fa-paper-plane"></i></span>
                                <span class="conversation-save-progress" style="display:none"><span class="spinner-border spinner-border-sm align-middle"></span></span>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="conv-empty p-4 text-center text-slate-500">@lang('No result found')</div>
                @endif
            </section>
        </div>
    </div>
</div>

<x-slot name="js">
    <script src="{{ asset($panelJs) }}"></script>
    <script>
        (function () {
            const attachmentFallbackText = @json(__('Attachment'));
            const deleteConfirmText = @json(__('Are you sure you want to delete?'));

            if (typeof Conversations === 'undefined') {
                return;
            }

            Conversations.init({
                @if($currentConversationUuid !== '')
                send_route: '{{ $conversationApiBase . '/' . $currentConversationUuid . '/messages' }}',
                mark_as_read_route: '{{ $conversationApiBase . '/' . $currentConversationUuid . '/read' }}',
                delete_route: '{{ route($webRouteDeleteName, ['uuid' => $currentConversationUuid]) }}',
                conversation_title_route: '{{ $conversationApiBase . '/' . $currentConversationUuid . '/title' }}',
                add_participant_route: '{{ $conversationApiBase . '/' . $currentConversationUuid . '/participants' }}',
                conversation_uuid: '{{ $currentConversationUuid }}',
                current_conversation_is_group: {{ $currentConversationIsGroup ? 'true' : 'false' }},
                delete_message_route: '{{ $conversationApiBase . '/' . $currentConversationUuid . '/messages/' }}',
                get_messages_route: '{{ $conversationApiBase . '/' . $currentConversationUuid . '/messages' }}',
                typing_route: '{{ $conversationApiBase . '/' . $currentConversationUuid . '/typing' }}',
                @endif
                contacts_route: '{{ $contactsEndpoint }}',
                create_conversation_route: '{{ $conversationApiBase . '/start' }}',
                conversations_index_route: '{{ $conversationApiBase }}',
                conversations_root_route: '{{ route($webRouteIndexName) }}',
                container: '.conversations-container',
                messages_container: '.conversations-container .conversations-messages-items ul',
                attachment_input_selector: '.conversation-attachments-input',
                participants_map: @json($participantsMap),
                current_user: @json($currentUserMap),
                read_receipts_enabled: {{ config('conversations.read_receipts.enabled', true) ? 'true' : 'false' }},
                read_receipts_show_unread_in_group: {{ config('conversations.read_receipts.show_unread_in_group', true) ? 'true' : 'false' }},
                texts: {
                    attachment: attachmentFallbackText,
                    confirm_delete: deleteConfirmText,
                    read: @json(__('Read')),
                    sent: @json(__('Sent')),
                    read_by: @json(__('Read by')),
                    unread_by: @json(__('Unread by'))
                }
            });

            $(document).on('input', '.conversation-search-input', function () {
                const query = ($(this).val() || '').toString().toLowerCase().trim();
                $('.conversations-list-items .contact').each(function () {
                    const haystack = ($(this).data('conversationTitle') || '').toString().toLowerCase();
                    $(this).toggle(haystack.indexOf(query) !== -1);
                });
            });

            $(document).on('click', '[data-conv-toggle-sidebar]', function () {
                $('#convSidebar').toggleClass('is-open');
            });
        })();
    </script>
</x-slot>
