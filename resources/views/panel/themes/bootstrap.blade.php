@php
    $authUser = request()->user();
    $authUserId = (string) ($authUser?->{$authUser?->getKeyName() ?? 'id'} ?? $authUser?->uuid ?? $authUser?->id ?? '');

    $normalizeAssetUrl = static function (?string $path, ?string $fallback = null): string {
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
    };

    $defaultAvatarUrl = $normalizeAssetUrl((string) config('global.empty_user_avatar'), '/assets/theme/media/logos/empty-user.webp');

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

    $currentConversationPrimaryParticipant = $currentConversation ? $resolveConversationParticipant($currentConversation) : null;
    $currentConversationUuid = $currentConversation ? $resolveConversationUuid($currentConversation) : '';
    $panelCss = config('conversations.ui.assets.css');
    $panelJs = config('conversations.ui.assets.js');
    $currentConversationOwnerId = (string) (
        $currentConversation?->owner?->{$currentConversation?->owner?->getKeyName() ?? 'id'}
        ?? $currentConversation?->owner?->uuid
        ?? $currentConversation?->owner?->id
        ?? ''
    );
    $canManageConversation = $currentConversationOwnerId !== '' && $currentConversationOwnerId === $authUserId;
@endphp

<link rel="stylesheet" href="{{ asset($panelCss) }}">

<div class="conversations-panel container-fluid px-0">
    <div class="conv-shell conversations-container">
        <aside class="conv-sidebar conversations-list @if(!$currentConversation) is-open @endif" id="convSidebar">
            <div class="conv-sidebar-header p-3 border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 flex-grow-1">{{ __('Conversations') }}</h5>
                    <button class="btn btn-sm btn-primary conversation-new-button" type="button" title="{{ __('New conversation') }}">
                        <i class="fa fa-plus"></i>
                    </button>
                </div>
                <div class="mt-3">
                    <input type="search" class="form-control form-control-sm conversation-search-input" placeholder="{{ __('Search') }}...">
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
                            $conversationName = match ($conversation->type?->name) {
                                'group' => __('Group conversation'),
                                'support' => __('Support'),
                                default => ($conversationPrimaryParticipantName !== '' ? $conversationPrimaryParticipantName : ($conversationTitle !== '' ? $conversationTitle : __('Conversation'))),
                            };
                            $previewText = trim((string) ($conversation?->lastMessage?->content ?? ''));
                        @endphp
                        <li
                            class="contact conv-list-item @if($currentConversation && $conversationUuid !== '' && $conversationUuid === $currentConversationUuid) active @endif"
                            data-conversation-uuid="{{ $conversationUuid }}"
                            data-conversation-title="{{ strtolower($conversationTitle . ' ' . $conversationName . ' ' . $previewText) }}"
                        >
                            <a href="{{ $conversationUuid !== '' ? route($webRouteIndexName, ['uuid' => $conversationUuid]) : route($webRouteIndexName) }}" class="d-flex gap-2 align-items-start text-decoration-none">
                                @if($conversation->type?->name === 'group')
                                    <img src="{{ asset('assets/theme/media/group.webp') }}" class="rounded-circle conv-avatar" alt="{{ $conversationName }}">
                                @else
                                    <img src="{{ $resolveConversationAvatar($conversationPrimaryParticipant) }}" class="rounded-circle conv-avatar" alt="{{ $conversationName }}">
                                @endif

                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                        <p class="name mb-0 text-truncate fw-semibold">{{ $conversationName }}</p>
                                        <span
                                            class="badge bg-danger conversation-count-new-messages"
                                            @if(!$conversation->hasUnreadedMessages()) style="display:none" @endif
                                        >{{ $conversation->getCountUnreadedMessages() }}</span>
                                    </div>
                                    @if($conversationTitle !== '')
                                        <p class="title mb-0 text-muted small text-truncate">{{ $conversationTitle }}</p>
                                    @endif
                                    <p class="preview mb-0 text-muted small text-truncate">
                                        @php
                                            $lastMessageSender = $conversation?->lastMessage?->sender;
                                            $lastMessageSenderId = (string) ($lastMessageSender?->{$lastMessageSender?->getKeyName() ?? 'id'} ?? $lastMessageSender?->uuid ?? $lastMessageSender?->id ?? '');
                                        @endphp
                                        @if($lastMessageSenderId !== '' && $lastMessageSenderId === $authUserId)
                                            <span>{{ __('You') }}:</span>
                                        @elseif($lastMessageSender)
                                            <span>{{ $resolveConversationUserName($lastMessageSender) ?: '@user' }}:</span>
                                        @endif
                                        {{ Str::limit($previewText, 60) }}
                                    </p>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
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
                                @if(in_array($currentConversation->type?->name, ['single', 'expert', 'cooperation'], true))
                                    <a href="{{ $currentConversationPrimaryParticipant?->url ?? '#' }}" class="text-decoration-none">
                                        {{ $resolveConversationUserName($currentConversationPrimaryParticipant) ?: ($currentConversation->title ?: __('Conversation')) }}
                                    </a>
                                @elseif($currentConversation->type?->name === 'support')
                                    {{ __('Support') }}
                                @else
                                    {{ __('Group conversation') }}
                                @endif
                            </p>
                            <p class="mb-0 small text-muted conversation-title text-truncate">
                                {{ $currentConversation->title ?? '' }}
                            </p>
                        </div>

                        @if($currentConversation->type?->name !== 'support' && $canManageConversation)
                            <span class="conversation-actions d-flex gap-1">
                                <button class="btn btn-sm btn-outline-primary conversation-change-title" type="button" title="{{ __('Edit') }}">
                                    <i class="fa fa-edit"></i>
                                </button>
                                @if($currentConversation->type?->name === 'group')
                                    <button class="btn btn-sm btn-outline-success conversation-add-participant" type="button" title="{{ __('Add') }}">
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
                        <input type="text" class="form-control" placeholder="{{ __('Write your message...') }}" name="message">
                        <input type="file" name="attachments[]" class="d-none conversation-attachments-input" multiple>
                        <button class="btn btn-sm btn-light conversation-attachment-trigger" type="button" title="{{ __('Add attachments') }}">
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
                        <h5 class="mb-2">{{ __('Conversations') }}</h5>
                        <p class="mb-0">{{ __('No result found') }}</p>
                    </div>
                </div>
            @endif
        </section>
    </div>
</div>

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
                    '<div class="clearfix"></div>';
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
