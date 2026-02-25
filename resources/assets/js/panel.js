"use strict";

var Conversations = function () {
    let sendRoute;
    let containerConv;
    let containerMessages;
    let token;
    let conversationUuid;
    let markAsReadRoute;
    let deleteRoute;
    let contactsRoute;
    let createConversationRoute;
    let conversationTitleRoute;
    let addParticipantRoute;
    let conversationsRootRoute;
    let deleteMessageRoute;
    let getMessagesRoute;
    let typingRoute;
    let addMessageCallback;
    let modalContacts = null;
    let messagesOffset = 0;
    let messagesHasOlder = true;
    let attachmentInputSelector = '[name="attachments[]"]';
    let participantsMap = {};
    let currentUser = {};
    let typingDebounce = null;
    let typingUsersTimers = {};
    let messagesRequestInFlight = false;
    let messagesRefreshQueued = false;
    let texts = {};

    const MESSAGE_BATCH_SIZE = 20;
    const DEFAULT_AVATAR = '/assets/theme/media/logos/empty-user.webp';

    const normalizeAvatarPath = (avatarPath) => {
        const value = (avatarPath || '').toString().trim();
        if (value === '') {
            return DEFAULT_AVATAR;
        }

        if (/^(https?:)?\/\//i.test(value) || value.startsWith('data:') || value.startsWith('blob:')) {
            return value;
        }

        if (value.startsWith('/')) {
            return value;
        }

        return '/' + value.replace(/^\/+/, '');
    };

    const resolveDSO = () => {
        if (typeof window !== 'undefined' && window.DSO) {
            return window.DSO;
        }

        return {
            getUserData: () => null,
            config: () => null,
            lang: (key) => key,
            info: (message) => {
                if (typeof console !== 'undefined') {
                    console.log('[Conversations]', message);
                }
            },
            confirmDelete: (url, callback, bearerToken) => {
                const isConfirmed = window.confirm(texts.confirm_delete || 'Are you sure?');
                if (!isConfirmed) {
                    return;
                }

                axios.post(url, { _method: 'DELETE' }, {
                    headers: resolveAuthHeaders(bearerToken),
                }).then(callback);
            },
        };
    };

    const debugRealtime = function () {
        try {
            if (typeof window !== 'undefined' && window.localStorage) {
                const value = window.localStorage.getItem('dso_realtime_debug');
                if (value === '0' || value === 'false') {
                    return;
                }
            }
        } catch (e) {
            // ignore
        }

        const args = Array.prototype.slice.call(arguments);
        args.unshift('[ConversationsRealtime]');
        console.log.apply(console, args);
    };

    const setConfig = (params) => {
        if (typeof params.send_route !== 'undefined') { sendRoute = params.send_route; }
        if (typeof params.mark_as_read_route !== 'undefined') { markAsReadRoute = params.mark_as_read_route; }
        if (typeof params.delete_route !== 'undefined') { deleteRoute = params.delete_route; }
        if (typeof params.contacts_route !== 'undefined') { contactsRoute = params.contacts_route; }
        if (typeof params.create_conversation_route !== 'undefined') { createConversationRoute = params.create_conversation_route; }
        if (typeof params.conversation_title_route !== 'undefined') { conversationTitleRoute = params.conversation_title_route; }
        if (typeof params.add_participant_route !== 'undefined') { addParticipantRoute = params.add_participant_route; }
        if (typeof params.conversations_root_route !== 'undefined') { conversationsRootRoute = params.conversations_root_route; }
        if (typeof params.delete_message_route !== 'undefined') { deleteMessageRoute = params.delete_message_route; }
        if (typeof params.get_messages_route !== 'undefined') { getMessagesRoute = params.get_messages_route; }
        if (typeof params.typing_route !== 'undefined') { typingRoute = params.typing_route; }
        if (typeof params.container !== 'undefined') { containerConv = params.container; }
        if (typeof params.messages_container !== 'undefined') { containerMessages = params.messages_container; }
        if (typeof params.token !== 'undefined') { token = params.token; }
        if (typeof params.conversation_uuid !== 'undefined') { conversationUuid = params.conversation_uuid; }
        if (typeof params.add_message_callback !== 'undefined') { addMessageCallback = params.add_message_callback; }
        if (typeof params.attachment_input_selector !== 'undefined') { attachmentInputSelector = params.attachment_input_selector; }
        if (typeof params.participants_map !== 'undefined') {
            participantsMap = params.participants_map || {};
            Object.keys(participantsMap).forEach((key) => {
                if (participantsMap[key]) {
                    participantsMap[key].avatar_path = normalizeAvatarPath(participantsMap[key].avatar_path);
                }
            });
        }
        if (typeof params.current_user !== 'undefined') {
            currentUser = params.current_user || {};
            currentUser.avatar_path = normalizeAvatarPath(currentUser.avatar_path);
        }
        if (typeof params.texts !== 'undefined') { texts = params.texts || {}; }
    };

    const resolveAuthHeaders = (overrideToken = null, withMultipart = false) => {
        const headers = {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr("content"),
        };

        const bearerToken = overrideToken || token;
        if (bearerToken) {
            headers.Authorization = 'Bearer ' + bearerToken;
        }
        if (withMultipart) {
            headers['Content-Type'] = 'multipart/form-data';
        }

        return headers;
    };

    const getAuthHeaders = (withMultipart = false) => resolveAuthHeaders(null, withMultipart);

    const getUserIdentifier = () => {
        const dso = resolveDSO();
        const dsoUser = (dso.getUserData('uuid') || dso.getUserData('id') || '').toString();
        if (dsoUser !== '') {
            return dsoUser;
        }

        return (currentUser.uuid || currentUser.id || '').toString();
    };

    const t = (key, fallback = null) => {
        if (texts[key]) {
            return texts[key];
        }

        const dso = resolveDSO();
        const value = dso.lang(key);
        if (value && value !== key) {
            return value;
        }

        return fallback !== null ? fallback : key;
    };

    const notify = (message, type = 'info') => {
        const dso = resolveDSO();
        if (typeof dso.info === 'function') {
            dso.info(message, type);
            return;
        }

        if (typeof window !== 'undefined' && window.toastr && typeof window.toastr[type] === 'function') {
            window.toastr[type](message);
            return;
        }

        if (type === 'error') {
            console.error(message);
        } else {
            console.log(message);
        }
    };

    const humanizeDate = () => {
        $('.conversation-message-date').each(function (_k, v) {
            const dateValue = $(v).data('messageDate');
            if (!dateValue) {
                return;
            }

            if (typeof moment !== 'undefined') {
                const dso = resolveDSO();
                const timezone = dso.config('timezone') || 'UTC';
                const locale = dso.config('locale') || 'en';
                $(v).html(moment(dateValue).tz(timezone).locale(locale).fromNow());
                return;
            }

            const parsed = new Date(dateValue);
            if (!Number.isNaN(parsed.getTime())) {
                $(v).text(parsed.toLocaleString());
            }
        });
    };

    const getSelectedAttachments = () => {
        const input = $(containerConv).find(attachmentInputSelector)[0];
        if (!input || !input.files) {
            return [];
        }

        return Array.from(input.files);
    };

    const updateAttachmentsCounter = () => {
        const files = getSelectedAttachments();
        const counter = $(containerConv).find('.conversation-attachments-counter');
        if (!counter.length) {
            return;
        }
        counter.text(files.length > 0 ? String(files.length) : '');
    };

    const normalizeAttachments = (attachments) => {
        if (!Array.isArray(attachments)) {
            return [];
        }

        return attachments.map((attachment) => ({
            id: attachment.id,
            filename: attachment.filename,
            original_filename: attachment.original_filename || attachment.filename,
            mime_type: attachment.mime_type,
            extension: attachment.extension,
            type: attachment.type || 'file',
            size: attachment.size,
            human_size: attachment.human_size,
            url: attachment.url,
            thumbnail_small_url: attachment.thumbnail_small_url,
            thumbnail_medium_url: attachment.thumbnail_medium_url,
            requires_warning: Boolean(attachment.requires_warning),
            is_safe: typeof attachment.is_safe === 'boolean' ? attachment.is_safe : true,
        }));
    };

    const resolveUserMeta = (userId) => {
        const key = (userId || '').toString();
        if (key !== '' && typeof participantsMap[key] !== 'undefined') {
            return participantsMap[key];
        }

        const currentId = (currentUser.uuid || currentUser.id || '').toString();
        if (key !== '' && currentId !== '' && key === currentId) {
            return currentUser;
        }

        return null;
    };

    const normalizeMessage = (messageOrContent, createdAt, id, name, avatar, direction, attachments) => {
        if (typeof messageOrContent === 'object' && messageOrContent !== null) {
            const message = messageOrContent;
            const currentUserId = getUserIdentifier();
            const messageId = message.id || message.message_id;
            const senderId = (message.sender_uuid || message.user_uuid || message.sender_id || message.user_id || message.sender?.uuid || message.sender?.id || '').toString();
            const senderMeta = resolveUserMeta(senderId);
            const computedDirection = senderId !== '' && senderId === currentUserId ? 'from' : 'to';

            return {
                id: messageId,
                content: (message.content || '').toString(),
                created_at: message.created_at || message.createdAt,
                name: message.sender_name || message.sender?.full_name || message.sender?.username || message.sender?.name || senderMeta?.full_name || senderMeta?.username || senderMeta?.name || '@user',
                avatar: normalizeAvatarPath(message.sender_avatar || message.sender?.avatar_path || message.sender?.avatar || senderMeta?.avatar_path || senderMeta?.avatar || DEFAULT_AVATAR),
                direction: direction || computedDirection,
                attachments: normalizeAttachments(message.attachments || []),
            };
        }

        return {
            id: id,
            content: (messageOrContent || '').toString(),
            created_at: createdAt,
            name: name,
            avatar: normalizeAvatarPath(avatar),
            direction: direction,
            attachments: normalizeAttachments(attachments || []),
        };
    };

    const setMessage = (container, messageOrContent, createdAt, id, name, avatar, direction, isLoadMessages, attachments, insertMode) => {
        const messagesEl = containerMessages ? $(containerMessages) : $(container).find('.conversations-messages-items');
        const message = normalizeMessage(messageOrContent, createdAt, id, name, avatar, direction, attachments);

        if (!message.id && message.content.trim() === '') {
            return;
        }

        if (!isLoadMessages && message.id && $('.conversations-messages-item[data-message-id="' + message.id + '"]').length > 0) {
            return;
        }

        let html = typeof addMessageCallback !== 'undefined'
            ? addMessageCallback(
                message.content,
                message.created_at,
                message.id,
                message.name,
                message.avatar,
                message.direction,
                isLoadMessages,
                message.attachments
            )
            : '<li class="conversations-messages-item ' + (message.direction === 'from' ? 'sent' : 'replies') + '" data-message-id="' + message.id + '"><div><img src="' + message.avatar + '" alt="' + message.name + '" /><p>' + message.content + '</p></div></li>';

        if (html === false) {
            return;
        }

        if (insertMode === 'append') {
            messagesEl.append(html);
            return;
        }

        if (isLoadMessages === true || insertMode === 'prepend') {
            messagesEl.prepend(html);
        } else {
            messagesEl.append(html);
        }
    };

    const scrollToEnd = () => {
        const list = $('.conversations-messages-items ul')[0];
        if (!list) {
            return;
        }

        $('.conversations-messages-items').animate({ scrollTop: list.scrollHeight }, 200);
    };

    const markAsRead = (messageId) => {
        if (!markAsReadRoute) {
            return;
        }

        $.ajax({
            type: 'post',
            url: markAsReadRoute,
            data: { message: messageId, conversation: conversationUuid },
            headers: getAuthHeaders(),
            dataType: 'json',
            async: true,
            cache: false,
        });
    };

    const resolveConversationRedirectUrl = (response) => {
        const directUrl = response?.data?.url || response?.data?.data?.url || null;
        if (directUrl) {
            return directUrl;
        }

        const conversationId = response?.data?.data?.uuid || null;
        if (!conversationId || !conversationsRootRoute) {
            return null;
        }

        const hasQuery = conversationsRootRoute.includes('?');
        if (!hasQuery) {
            return conversationsRootRoute.replace(/\/+$/, '') + '/' + conversationId;
        }

        const routeParts = conversationsRootRoute.split('?');
        const routePath = (routeParts[0] || '').replace(/\/+$/, '');
        const routeQuery = routeParts.slice(1).join('?');

        return routePath + '/' + conversationId + (routeQuery ? '?' + routeQuery : '');
    };

    const updateConversationPreview = (message) => {
        const preview = $('.contact.active .preview');
        if (!preview.length || !message) {
            return;
        }

        const senderId = (message.sender_uuid || message.user_uuid || message.sender_id || message.user_id || message.sender?.uuid || message.sender?.id || '').toString();
        const senderMeta = resolveUserMeta(senderId);
        const currentUserId = getUserIdentifier();
        const senderLabel = senderId !== '' && senderId === currentUserId
            ? t('You', 'You')
            : senderMeta?.username || message.sender_name || message.sender?.username || '@user';
        const content = (message.content || '').toString().trim();

        preview.html('<span>' + senderLabel + ': </span>' + (content !== '' ? content : t('Attachment', 'Attachment')));
    };

    const fetchMessageAttachments = (message) => {
        const messageId = message?.id || message?.message_id;
        if (!messageId || !getMessagesRoute) {
            return Promise.resolve(message);
        }

        const attachmentsUrl = getMessagesRoute.replace(/\/$/, '') + '/' + messageId + '/attachments';
        return axios.get(attachmentsUrl, { headers: getAuthHeaders() })
            .then((response) => {
                const attachments = Array.isArray(response?.data?.data) ? response.data.data : [];
                message.attachments = attachments;
                return message;
            })
            .catch(() => message);
    };

    const handleIncomingMessage = (message) => {
        if (!message || message.conversation_uuid !== conversationUuid) {
            return;
        }

        fetchMessageAttachments(message).then((resolvedMessage) => {
            setMessage(containerConv, resolvedMessage, null, null, null, null, null, false, null, 'append');
            scrollToEnd();
            humanizeDate();
            updateConversationPreview(resolvedMessage);
            markAsRead(resolvedMessage.id || resolvedMessage.message_id || null);
        });
    };

    const loadMessages = (isFirstLoad, resetPagination) => {
        if (!getMessagesRoute) {
            return;
        }

        if (messagesRequestInFlight) {
            if (resetPagination === true) {
                messagesRefreshQueued = true;
            }
            return;
        }

        if (resetPagination === true) {
            messagesOffset = 0;
            messagesHasOlder = true;
            $(containerMessages).html('');
        }

        if (!messagesHasOlder) {
            return;
        }

        const requestConfig = {
            headers: getAuthHeaders(),
            params: {
                order: 'desc',
                limit: MESSAGE_BATCH_SIZE,
                start: messagesOffset,
            },
        };

        messagesRequestInFlight = true;
        axios.get(getMessagesRoute, requestConfig)
            .then((response) => {
                const payload = response?.data || {};
                const items = Array.isArray(payload.data) ? payload.data : [];
                const orderedItems = (isFirstLoad || resetPagination === true) ? items.slice().reverse() : items;
                const insertMode = (isFirstLoad || resetPagination === true) ? 'append' : 'prepend';
                const previousHeight = $('.conversations-messages-items')[0]?.scrollHeight || 0;

                orderedItems.forEach((message) => {
                    setMessage(containerConv, message, null, null, null, null, null, true, null, insertMode);
                });

                messagesOffset += items.length;
                messagesHasOlder = items.length === MESSAGE_BATCH_SIZE;

                if (isFirstLoad || resetPagination === true) {
                    scrollToEnd();
                    markAsRead(null);
                } else {
                    const wrapper = $('.conversations-messages-items')[0];
                    if (wrapper) {
                        const nextHeight = wrapper.scrollHeight;
                        wrapper.scrollTop = Math.max(nextHeight - previousHeight, 0);
                    }
                }

                humanizeDate();
            })
            .catch((error) => {
                console.log(error);
            })
            .finally(() => {
                messagesRequestInFlight = false;

                if (messagesRefreshQueued) {
                    messagesRefreshQueued = false;
                    loadMessages(false, true);
                }
            });
    };

    const sendTypingSignal = () => {
        if (!typingRoute || !conversationUuid) {
            return;
        }

        if (typingDebounce) {
            clearTimeout(typingDebounce);
        }

        typingDebounce = setTimeout(() => {
            axios.post(typingRoute, {
                user_name: currentUser.full_name || currentUser.username || currentUser.name || '',
            }, {
                headers: getAuthHeaders(),
            }).catch(() => {});
        }, 350);
    };

    const renderTypingIndicator = () => {
        const userIds = Object.keys(typingUsersTimers);
        const indicator = $(containerConv).find('.conversation-typing-indicator');

        if (userIds.length === 0) {
            indicator.hide();
            return;
        }

        const label = userIds.map((userId) => {
            const userMeta = resolveUserMeta(userId);
            return userMeta?.full_name || userMeta?.username || userMeta?.name || '@user';
        }).join(', ') + '...';

        if (indicator.length) {
            indicator.text(label).show();
            return;
        }

        $(containerConv).find('.conversations-messages-items').after('<div class="conversation-typing-indicator text-muted small px-4 py-2">' + label + '</div>');
    };

    const connectRealtime = () => {
        const dso = resolveDSO();
        const realtimeConfig = dso.config('realtime') || {};
        const connectionName = (realtimeConfig.connection || '').toString().toLowerCase();
        if (!realtimeConfig.key || ['null', 'log', 'redis'].includes(connectionName)) {
            console.warn('Conversations realtime disabled.', realtimeConfig);
            return;
        }

        let pusher = null;
        try {
            if (typeof PusherConnect !== 'undefined' && typeof PusherConnect.getInstance === 'function') {
                pusher = PusherConnect.getInstance(realtimeConfig);
            }
        } catch (error) {
            console.error('Realtime initialization failed:', error);
            return;
        }

        if (!pusher) {
            return;
        }

        const userId = getUserIdentifier();
        if (userId) {
            const userChannel = typeof PusherConnect.subscribe === 'function'
                ? PusherConnect.subscribe(pusher, 'private-conversation.user.' + userId)
                : pusher.subscribe('private-conversation.user.' + userId);

            userChannel.bind('conversation.created', function () {
                debugRealtime('event conversation.created', { channel: userChannel.name });
                window.location.reload();
            });
        }

        if (!conversationUuid) {
            return;
        }

        const conversationChannel = typeof PusherConnect.subscribe === 'function'
            ? PusherConnect.subscribe(pusher, 'private-conversation.' + conversationUuid)
            : pusher.subscribe('private-conversation.' + conversationUuid);

        conversationChannel.bind('message.sent', function (payload) {
            debugRealtime('event message.sent', payload);
            const currentUserId = getUserIdentifier();
            if ((payload.sender_id || payload.sender_uuid || payload.user_id || payload.user_uuid || '').toString() === currentUserId) {
                return;
            }
            handleIncomingMessage(payload);
        });

        conversationChannel.bind('message.deleted', function (payload) {
            debugRealtime('event message.deleted', payload);
            if (payload.conversation_uuid === conversationUuid) {
                $('.conversations-messages-item[data-message-id="' + payload.message_id + '"]').remove();
            }
        });

        conversationChannel.bind('message.edited', function (payload) {
            debugRealtime('event message.edited', payload);
            if (payload.conversation_uuid !== conversationUuid) {
                return;
            }

            const messageId = payload.message_id || payload.id || null;
            if (!messageId) {
                return;
            }

            const messageEl = $('.conversations-messages-item[data-message-id="' + messageId + '"]');
            if (!messageEl.length) {
                return;
            }

            const content = (payload.content || '').toString();
            messageEl.find('p').first().text(content);
            const date = payload.created_at || payload.updated_at || null;
            if (date) {
                const dateEl = messageEl.find('.conversation-message-date').first();
                dateEl.attr('data-message-date', date);
                dateEl.text(date);
                humanizeDate();
            }
        });

        conversationChannel.bind('message.read', function (payload) {
            debugRealtime('event message.read', payload);
            if (payload.conversation_uuid !== conversationUuid) {
                return;
            }

            const listConversation = $('.conversations-list-items').find('[data-conversation-uuid="' + payload.conversation_uuid + '"]');
            if (listConversation.length) {
                listConversation.find('.conversation-count-new-messages').hide();
            }
        });

        conversationChannel.bind('user.typing', function (payload) {
            debugRealtime('event user.typing', payload);
            const currentUserId = getUserIdentifier();
            if ((payload.user_id || '').toString() === currentUserId || payload.conversation_uuid !== conversationUuid) {
                return;
            }

            const typingUserId = (payload.user_id || '').toString();
            if (typingUsersTimers[typingUserId]) {
                clearTimeout(typingUsersTimers[typingUserId]);
            }

            typingUsersTimers[typingUserId] = setTimeout(function () {
                delete typingUsersTimers[typingUserId];
                renderTypingIndicator();
            }, 2500);

            renderTypingIndicator();
        });
    };

    const createModalContacts = (submitUrl, submitLabel, isUpdate, callback) => {
        if (modalContacts !== null) {
            modalContacts.remove();
        }

        $.ajax({
            type: 'get',
            url: contactsRoute,
            headers: getAuthHeaders(),
            dataType: 'json',
            async: true,
            cache: false,
        }).then((response) => {
            const contacts = response.contacts || response.data || [];
            let selectedFromConversation = [];
            $('.conversation-participants').find('a').each(function (_k, v) {
                selectedFromConversation.push($(v).data('participantUuid'));
            });

            const renderContacts = isUpdate === true
                ? contacts.filter((contact) => $.inArray(contact.uuid, selectedFromConversation) < 0)
                : contacts;

            let html = '<div class="modal fade" tabindex="-1"><div class="modal-dialog"><div class="modal-content">';
            html += '<div class="modal-header bg-light"><h5 class="modal-title">' + (submitLabel || t('Select contacts', 'Select contacts')) + '</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>';
            html += '<div class="modal-body dso-contact-list"><form>';

            if (renderContacts.length > 0) {
                html += '<table class="table table-sm align-middle"><tbody>';
                $.each(renderContacts, function (_k, contact) {
                    html += '<tr><td style="width:32px"><input class="form-check-input" type="checkbox" name="contact_uuid[]" value="' + contact.uuid + '"></td>';
                    html += '<td><img alt="@' + contact.username + '" class="img-thumbnail me-2" src="' + normalizeAvatarPath(contact.avatar_path || DEFAULT_AVATAR) + '" width="40"> <a href="' + (contact.url || '#') + '" target="_blank">@' + contact.username + '</a></td></tr>';
                });
                html += '</tbody></table>';
            } else {
                html += '<div class="text-muted small">' + t('No result found', 'No contacts found') + '</div>';
            }

            html += '</form></div>';
            html += '<div class="modal-footer bg-light"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">' + t('Cancel', 'Cancel') + '</button>';
            html += '<button type="submit" class="btn btn-sm btn-primary modal-success-btn">' + submitLabel + '</button></div>';
            html += '</div></div></div>';

            modalContacts = document.createElement('div');
            modalContacts.innerHTML = html;
            document.body.append(modalContacts);

            const bsModal = new bootstrap.Modal(modalContacts.querySelector('.modal'));
            bsModal.show();

            $('.modal-success-btn').on('click', function () {
                const formData = new FormData($('.dso-contact-list form')[0]);
                if (isUpdate === true) {
                    formData.append('_method', 'PUT');
                }

                const selected = renderContacts.filter((contact) => {
                    return $('.dso-contact-list form').find('input[value="' + contact.uuid + '"]:checked').length > 0;
                });

                axios.post(submitUrl, formData, {
                    headers: getAuthHeaders(),
                }).then((submitResponse) => {
                    if (typeof callback === 'function') {
                        callback(submitResponse, selected);
                    }

                    bsModal.hide();
                    const redirectUrl = resolveConversationRedirectUrl(submitResponse);
                    if (redirectUrl) {
                        window.location.href = redirectUrl;
                    }
                }).catch((error) => {
                    console.log(error);
                });
            });
        });
    };

    const handleMessaging = () => {
        const messageInput = $(containerConv).find('[name="message"]');
        const content = (messageInput.val() || '').toString();
        const attachments = getSelectedAttachments();

        if (content.trim().length === 0 && attachments.length === 0) {
            return;
        }

        messageInput.prop('readonly', true);
        $(containerConv).find('.conversation-save-label').hide();
        $(containerConv).find('.conversation-save-progress').show();

        const formData = new FormData();
        formData.append('content', content);
        attachments.forEach((attachment) => {
            formData.append('attachments[]', attachment);
        });

        axios.post(sendRoute, formData, {
            headers: getAuthHeaders(true),
        }).then((response) => {
            const message = response?.data?.data || response?.data?.message || null;
            if (!message) {
                return;
            }

            setMessage(containerConv, message, null, null, null, null, null, false);
            scrollToEnd();
            humanizeDate();
            markAsRead(message.id || message.message_id || null);

            messageInput.val('');
            updateAttachmentsCounter();
            $(containerConv).find(attachmentInputSelector).val('');
        }).catch((error) => {
            console.log(error);
            notify(t('Something went wrong, please try again.', 'Failed to send message.'), 'error');
        }).finally(() => {
            messageInput.prop('readonly', false);
            $(containerConv).find('.conversation-save-label').show();
            $(containerConv).find('.conversation-save-progress').hide();
        });
    };

    return {
        init: (params) => {
            setConfig(params);

            $('.conversation-new-button').on('click', function () {
                createModalContacts(createConversationRoute, t('Start a conversation', 'Start conversation'));
            });

            $('.conversation-add-participant').on('click', function () {
                createModalContacts(addParticipantRoute, t('Add', 'Add participant'), true, function (response, selected) {
                    if (response.data.status === 'FAIL') {
                        notify(response.data.message, 'error');
                        return;
                    }

                    notify(response.data.message || t('Saved', 'Saved'), 'success');
                    $.each(selected, function (_k, contact) {
                        const contactAvatar = normalizeAvatarPath(contact.avatar_path || DEFAULT_AVATAR);
                        $('.conversation-participants').append('<a href="' + (contact.url || '#') + '" target="_blank" data-participant-uuid="' + contact.uuid + '"><img src="' + contactAvatar + '" alt="@' + contact.username + '" title="@' + contact.username + '" /></a>');
                        participantsMap[contact.uuid] = {
                            uuid: contact.uuid,
                            username: '@' + contact.username,
                            avatar_path: contactAvatar,
                            full_name: contact.full_name || contact.username,
                        };
                    });
                });
            });

            $('body').on('click', '.message-delete', function (event) {
                event.preventDefault();

                const messageEl = $(this).closest('.conversations-messages-item');
                const messageId = messageEl.data('messageId');
                if (!messageId) {
                    return;
                }

                resolveDSO().confirmDelete(
                    deleteMessageRoute + messageId,
                    function (response) {
                        if (response.data.status === 'FAIL') {
                            notify(response.data.message, 'error');
                        } else {
                            notify(response.data.message || 'OK', 'success');
                            messageEl.remove();
                        }
                    },
                    token
                );
            });

            if (conversationUuid) {
                $('.conversations-button').on('click', function () {
                    handleMessaging();
                });

                $(containerConv).find('[name="message"]').on('keydown', function (event) {
                    if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault();
                        handleMessaging();
                        return;
                    }

                    sendTypingSignal();
                });

                $(containerConv).find('[name="message"]').on('input', function () {
                    sendTypingSignal();
                });

                $(containerConv).find(attachmentInputSelector).on('change', function () {
                    updateAttachmentsCounter();
                });

                $(containerConv).find('.conversation-attachment-trigger').on('click', function (event) {
                    event.preventDefault();
                    const fileInput = $(containerConv).find(attachmentInputSelector);
                    if (fileInput.length) {
                        fileInput.trigger('click');
                    }
                });

                $('.conversation-change-title').on('click', function () {
                    const titleContainer = $('.conversation-title');
                    const titleText = titleContainer.text();
                    titleContainer.html(
                        '<div class="input-group input-group-sm mt-2">' +
                        '<input type="text" class="form-control conversation-title-value" placeholder="' + titleText + '" value="' + titleText + '">' +
                        '<button class="btn btn-outline-secondary conversation-title-value-save" type="button"><i class="fa fa-check"></i></button>' +
                        '</div>'
                    );
                    $('.conversation-actions').hide();
                });

                $('body').on('click', '.conversation-title-value-save', function () {
                    const title = $('.conversation-title-value').val();
                    const formData = new FormData();
                    formData.append('_method', 'PUT');
                    formData.append('title', title);

                    axios.post(conversationTitleRoute, formData, {
                        headers: getAuthHeaders(),
                    }).then((response) => {
                        if (response.data.status === 'FAIL') {
                            notify(response.data.message, 'error');
                        } else {
                            notify(response.data.message || t('Saved', 'Saved'), 'success');
                            $('.contact.active').find('.title').html(title);
                            $('.conversation-actions').show();
                            $('.conversation-title').html(title);
                        }
                    }).catch((error) => {
                        console.log(error);
                    });
                });

                $('.conversations-messages-items').on('scroll', (event) => {
                    if (event.target.scrollTop === 0) {
                        loadMessages(false, false);
                    }
                });

                loadMessages(true, true);
            }

            connectRealtime();
        },
        setMessage: setMessage,
        handleMessaging: handleMessaging,
        scrollToEnd: scrollToEnd,
        humanizeDate: humanizeDate,
        markAsRead: markAsRead,
        delete: () => {
            $.ajax({
                type: 'post',
                url: deleteRoute,
                data: { _method: 'DELETE' },
                headers: getAuthHeaders(),
                dataType: 'json',
                async: true,
                cache: false,
            });
        },
        createModalContacts: createModalContacts,
    };
}();

if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
    module.exports = Conversations;
}
