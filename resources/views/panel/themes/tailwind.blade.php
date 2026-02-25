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
@endphp

<link rel="stylesheet" href="{{ asset($panelCss) }}">

<div class="conversations-panel tw-conversations-panel">
    <div class="conversations-container conv-shell tw-shell">
        <aside class="conversations-list conv-sidebar tw-sidebar @if(!$currentConversation) is-open @endif" id="convSidebar">
            <div class="p-3 border-b border-slate-200">
                <div class="flex items-center gap-2">
                    <h3 class="text-base font-semibold m-0 flex-1">{{ __('Conversations') }}</h3>
                    <button class="conversation-new-button inline-flex items-center justify-center h-8 w-8 rounded bg-blue-600 text-white" type="button" title="{{ __('New conversation') }}">+</button>
                </div>
                <div class="mt-3">
                    <input type="search" class="conversation-search-input w-full rounded border border-slate-300 px-3 py-2 text-sm" placeholder="{{ __('Search') }}...">
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
                            <a href="{{ $conversationUuid !== '' ? route($webRouteIndexName, ['uuid' => $conversationUuid]) : route($webRouteIndexName) }}" class="flex gap-2 items-start no-underline">
                                @if($conversation->type?->name === 'group')
                                    <img src="{{ asset('assets/theme/media/group.webp') }}" class="rounded-full conv-avatar" alt="{{ $conversationName }}">
                                @else
                                    <img src="{{ $resolveConversationAvatar($conversationPrimaryParticipant) }}" class="rounded-full conv-avatar" alt="{{ $conversationName }}">
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="name m-0 truncate font-semibold">{{ $conversationName }}</p>
                                        <span class="conversation-count-new-messages inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-xs text-white"
                                              @if(!$conversation->hasUnreadedMessages()) style="display:none" @endif>{{ $conversation->getCountUnreadedMessages() }}</span>
                                    </div>
                                    @if($conversationTitle !== '')
                                        <p class="title m-0 text-xs text-slate-500 truncate">{{ $conversationTitle }}</p>
                                    @endif
                                    <p class="preview m-0 text-xs text-slate-500 truncate">
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
                                @if(in_array($currentConversation->type?->name, ['single', 'expert', 'cooperation'], true))
                                    {{ $resolveConversationUserName($currentConversationPrimaryParticipant) ?: ($currentConversation->title ?: __('Conversation')) }}
                                @elseif($currentConversation->type?->name === 'support')
                                    {{ __('Support') }}
                                @else
                                    {{ __('Group conversation') }}
                                @endif
                            </p>
                            <p class="m-0 truncate text-xs text-slate-500 conversation-title">{{ $currentConversation->title ?? '' }}</p>
                        </div>
                    </div>
                </div>
                <div class="conversations-messages-items conv-messages">
                    <ul class="list-unstyled mb-0"></ul>
                </div>
                <div class="message-input conv-composer border-t border-slate-200">
                    <div class="wrap flex items-center gap-2 p-2">
                        <input type="text" class="flex-1 rounded border border-slate-300 px-3 py-2 text-sm" placeholder="{{ __('Write your message...') }}" name="message">
                        <input type="file" name="attachments[]" class="d-none conversation-attachments-input" multiple>
                        <button class="conversation-attachment-trigger inline-flex items-center justify-center h-9 w-9 rounded border border-slate-300" type="button">&#128206;</button>
                        <button class="conversations-button inline-flex items-center justify-center h-9 w-9 rounded bg-blue-600 text-white" type="button">
                            <span class="conversation-save-label">&#10148;</span>
                            <span class="conversation-save-progress" style="display:none"><span class="spinner-border spinner-border-sm align-middle"></span></span>
                        </button>
                    </div>
                </div>
            @else
                <div class="conv-empty p-4 text-center text-slate-500">{{ __('No result found') }}</div>
            @endif
        </section>
    </div>
</div>

<script src="{{ asset($panelJs) }}"></script>
<script>
    (function () {
        const attachmentFallbackText = @json(__('Attachment'));

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
                attachment: attachmentFallbackText
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
