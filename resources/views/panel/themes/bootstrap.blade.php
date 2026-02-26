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
    <div class="conversations-panel container-fluid px-0">
        <div class="conv-shell conversations-container">
            <aside class="conv-sidebar conversations-list @if(!$currentConversation) is-open @endif" id="convSidebar">
                <div class="conv-sidebar-header p-3 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="mb-0 flex-grow-1">@lang('Conversations')</h5>
                        <button class="btn btn-sm btn-primary conversation-new-button" type="button" title="@lang('New conversation')">
                            <i class="fa fa-plus"></i>
                        </button>
                    </div>
                    <div class="mt-3">
                        <input type="search" class="form-control form-control-sm conversation-search-input" placeholder="@lang('Search')...">
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
                                <a href="{{ $conversationUuid !== '' ? route($webRouteIndexName, ['uuid' => $conversationUuid]) : route($webRouteIndexName) }}" class="d-flex gap-2 align-items-start text-decoration-none">
                                    @if($conversationIsGroup)
                                        <img src="{{ asset('assets/theme/media/group.webp') }}" class="rounded-circle conv-avatar" alt="{{ $conversationName }}">
                                    @else
                                        <img src="{{ $resolveConversationAvatar($conversationPrimaryParticipant) }}" class="rounded-circle conv-avatar" alt="{{ $conversationName }}">
                                    @endif

                                    <div class="flex-grow-1 overflow-hidden">
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <div class="d-flex align-items-center gap-2 min-w-0 name-row">
                                                <p class="name mb-0 text-truncate fw-semibold">{{ $conversationName }}</p>
                                                @if($conversationRelation)
                                                    <span class="badge rounded-pill conversation-relation-badge" title="{{ $conversationRelation['description'] }}">
                                                        {{ $conversationRelation['label'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="badge bg-danger conversation-count-new-messages" @if(!$conversation->hasUnreadedMessages()) style="display:none" @endif>{{ $conversation->getCountUnreadedMessages() }}</span>
                                        </div>
                                        <p class="preview mb-0 text-muted small text-truncate">
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

            <section class="conv-main content">
                @if($currentConversation && $currentConversationUuid !== '')
                    <div class="contact-profile conv-header border-bottom">
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-light conv-mobile-back" data-conv-toggle-sidebar>
                                <i class="fa fa-arrow-left"></i>
                            </button>

                            <span class="conversation-participants d-flex align-items-center">
                                @foreach($currentConversation->participants as $participant)
                                    <a href="{{ $participant->url ?? '#' }}" target="_blank" data-participant-uuid="{{ $participant->uuid }}">
                                        <img src="{{ $resolveConversationAvatar($participant) }}" alt="{{ $resolveConversationUserName($participant) ?: '@user' }}" title="{{ $resolveConversationUserName($participant) ?: '@user' }}">
                                    </a>
                                @endforeach
                            </span>

                            <div class="flex-grow-1 overflow-hidden">
                                <p class="mb-0 text-truncate">
                                    @if(!$currentConversationIsGroup && $currentConversation->type?->name !== 'support')
                                        <a href="{{ $currentConversationPrimaryParticipant?->url ?? '#' }}" class="text-decoration-none">
                                            {{ $resolveConversationUserName($currentConversationPrimaryParticipant) ?: ($currentConversation->title ?: __('Conversation')) }}
                                        </a>
                                    @elseif($currentConversation->type?->name === 'support')
                                        @lang('Support')
                                    @else
                                        @lang('Group conversation')
                                    @endif
                                </p>
                                <p class="mb-0 small text-muted conversation-title text-truncate">
                                    {{ $currentConversation->title ?? '' }}
                                </p>
                                @if($currentConversationRelation)
                                    <p class="mb-0 small text-muted conversation-relation text-truncate">
                                        <span>@lang('Relation'):</span>
                                        @if(($currentConversationRelation['url'] ?? '') !== '')
                                            <a href="{{ $currentConversationRelation['url'] }}" class="text-decoration-none" target="_blank" rel="noopener">
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

                    <div class="message-input conv-composer border-top">
                        <div class="wrap d-flex align-items-center gap-2 p-2">
                            <input type="text" class="form-control" placeholder="@lang('Write your message...')" name="message">
                            <input type="file" name="attachments[]" class="d-none conversation-attachments-input" multiple>
                            <button class="btn btn-sm btn-light conversation-attachment-trigger" type="button" title="@lang('Add attachments')">
                                <i class="fa fa-paperclip"></i>
                                <span class="conversation-attachments-counter badge bg-primary ms-1"></span>
                            </button>
                            <button class="btn btn-primary conversations-button" type="button">
                                <span class="conversation-save-label"><i class="fa fa-paper-plane"></i></span>
                                <span class="conversation-save-progress" style="display:none">
                                    <span class="spinner-border spinner-border-sm align-middle"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                @else
                    <div class="conv-empty d-flex align-items-center justify-content-center p-4">
                        <div class="text-center text-muted">
                            <h5 class="mb-2">@lang('Conversations')</h5>
                            <p class="mb-0">@lang('No result found')</p>
                        </div>
                    </div>
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
                texts: {
                    attachment: attachmentFallbackText,
                    confirm_delete: deleteConfirmText
                },
                add_message_callback: function (content, created_at, id, name, avatar, direction, isLoadMessages, attachments) {
                    const hasContent = (content || '').trim() !== '';
                    const hasAttachments = Array.isArray(attachments) && attachments.length > 0;

                    if (!hasContent && !hasAttachments) {
                        return false;
                    }

                    if (isLoadMessages !== true) {
                        $('.message-input input').val(null);
                        $('.contact.active .preview').html('<span>' + name + ': </span>' + (hasContent ? content : attachmentFallbackText));
                    }

                    let attachmentsHtml = '';
                    if (hasAttachments) {
                        attachmentsHtml += '<div class="conversation-message-attachments mt-2">';
                        attachments.forEach((attachment) => {
                            const url = attachment.url || '#';
                            const fileName = attachment.original_filename || attachment.filename || 'attachment';
                            const isImage = (attachment.type || '').toString() === 'image';
                            const thumb = attachment.thumbnail_small_url || attachment.thumbnail_medium_url || url;

                            if (isImage) {
                                attachmentsHtml += '<a href="' + url + '" target="_blank" rel="noopener" class="d-inline-block me-2 mb-1">' +
                                    '<img src="' + thumb + '" alt="' + fileName + '" style="max-width:120px;max-height:120px;border-radius:8px;">' +
                                    '</a>';
                            } else {
                                attachmentsHtml += '<a href="' + url + '" target="_blank" rel="noopener" class="d-inline-flex align-items-center me-2 mb-1">' +
                                    '<i class="fa fa-file me-1"></i>' + fileName +
                                    '</a>';
                            }
                        });
                        attachmentsHtml += '</div>';
                    }

                    const bodyHtml = (hasContent ? '<p>' + content + '</p>' : '') + attachmentsHtml;

                    let html = '<li class="conversations-messages-item ' + (direction === 'from' ? 'sent' : 'replies') + '" data-message-id="' + id + '">' +
                        '<div>' +
                        '<img src="' + avatar + '" alt="' + name + '" title="' + name + '" />' +
                        '<div class="conversation-message-body">' + bodyHtml + '</div>' +
                        '</div>' +
                        '';
                    if (direction === 'from') {
                        html += '<small class="fa fa-trash-alt text-danger text-nowrap ms-2 mt-1 message-delete"></small>';
                    }
                    html += '<small class="text-muted small text-nowrap mt-1 conversation-message-date" data-message-date="' + created_at + '">' + created_at + '</small>' +
                        '</li>';

                    return html;
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
