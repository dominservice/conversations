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
    let conversationsIndexRoute;
    let addMessageCallback;
    let modalContacts = null;
    let messagesOffset = 0;
    let messagesHasOlder = true;
    let attachmentInputSelector = '[name="attachments[]"]';
    let participantsMap = {};
    let currentUser = {};
    let currentConversationIsGroup = false;
    let readReceiptsEnabled = true;
    let readReceiptsShowUnreadInGroup = true;
    let typingDebounce = null;
    let typingUsersTimers = {};
    let messagesRequestInFlight = false;
    let messagesRefreshQueued = false;
    let messageSyncIntervalId = null;
    let subscribedConversationChannels = {};
    let processedRealtimeMessageMap = {};
    let texts = {};
    let realtimeConnectAttempts = 0;

    const MESSAGE_BATCH_SIZE = 20;
    const MESSAGE_SYNC_INTERVAL_MS = 4000;
    const REALTIME_BOOT_MAX_ATTEMPTS = 12;
    const REALTIME_BOOT_RETRY_MS = 500;
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
                if (value === '1' || value === 'true') {
                    const args = Array.prototype.slice.call(arguments);
                    args.unshift('[ConversationsRealtime]');
                    console.log.apply(console, args);
                }
            }
        } catch (e) {
            // ignore
        }
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
        if (typeof params.conversations_index_route !== 'undefined') { conversationsIndexRoute = params.conversations_index_route; }
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
        if (typeof params.current_conversation_is_group !== 'undefined') {
            currentConversationIsGroup = Boolean(params.current_conversation_is_group);
        }
        if (typeof params.read_receipts_enabled !== 'undefined') {
            readReceiptsEnabled = Boolean(params.read_receipts_enabled);
        }
        if (typeof params.read_receipts_show_unread_in_group !== 'undefined') {
            readReceiptsShowUnreadInGroup = Boolean(params.read_receipts_show_unread_in_group);
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

    const getCurrentUserIdentifiers = () => {
        const dso = resolveDSO();
        const identifiers = [
            dso.getUserData('uuid'),
            dso.getUserData('id'),
            currentUser?.uuid,
            currentUser?.id,
        ]
            .map((value) => (value ?? '').toString().trim())
            .filter((value) => value !== '');

        return Array.from(new Set(identifiers));
    };

    const isCurrentUserIdentifier = (identifier) => {
        const normalized = (identifier ?? '').toString().trim();
        if (normalized === '') {
            return false;
        }

        return getCurrentUserIdentifiers().includes(normalized);
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

    const hasExplicitTimezone = (value) => {
        const raw = (value || '').toString().trim();
        if (raw === '') {
            return false;
        }

        return /(Z|[+\-]\d{2}:?\d{2})$/i.test(raw);
    };

    const parseDateValue = (dateValue, timezone) => {
        const raw = (dateValue || '').toString().trim();
        if (raw === '') {
            return null;
        }

        if (typeof moment !== 'undefined') {
            let parsedMoment = null;

            if (hasExplicitTimezone(raw)) {
                parsedMoment = moment.parseZone(raw);
            } else {
                // Backend stores conversation timestamps in UTC without offset.
                parsedMoment = moment.utc(raw, moment.ISO_8601, true);
                if (!parsedMoment.isValid()) {
                    parsedMoment = moment.utc(raw);
                }
            }

            if (!parsedMoment.isValid()) {
                parsedMoment = moment(raw);
            }

            if (parsedMoment.isValid() && timezone && typeof parsedMoment.tz === 'function') {
                parsedMoment = parsedMoment.tz(timezone);
            }

            return parsedMoment.isValid() ? parsedMoment : null;
        }

        if (!hasExplicitTimezone(raw) && /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(\.\d+)?$/i.test(raw)) {
            const normalizedUtc = raw.replace(' ', 'T') + 'Z';
            const parsedUtc = new Date(normalizedUtc);
            if (!Number.isNaN(parsedUtc.getTime())) {
                return parsedUtc;
            }
        }

        const parsed = new Date(raw);
        if (!Number.isNaN(parsed.getTime())) {
            return parsed;
        }

        return null;
    };

    const humanizeDate = () => {
        $('.conversation-message-date').each(function (_k, v) {
            const dateValue = $(v).data('messageDate');
            if (!dateValue) {
                return;
            }

            const dso = resolveDSO();
            const timezone = dso.config('timezone') || (Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC');
            const locale = dso.config('locale') || 'en';
            const parsed = parseDateValue(dateValue, timezone);

            if (!parsed) {
                return;
            }

            if (typeof moment !== 'undefined' && typeof parsed.fromNow === 'function') {
                $(v).html(parsed.locale(locale).fromNow());
                return;
            }

            $(v).text(parsed.toLocaleString());
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

    const resolveSenderId = (message) => {
        return (
            message?.sender_uuid
            || message?.user_uuid
            || message?.sender_id
            || message?.user_id
            || message?.sender?.uuid
            || message?.sender?.id
            || ''
        ).toString();
    };

    const normalizeUserId = (value) => {
        return (value ?? '').toString().trim();
    };

    const extractReceiptUserId = (entry) => {
        if (entry === null || typeof entry === 'undefined') {
            return '';
        }

        if (typeof entry === 'string' || typeof entry === 'number') {
            return normalizeUserId(entry);
        }

        if (typeof entry === 'object') {
            return normalizeUserId(
                entry.id
                || entry.uuid
                || entry.user_id
                || entry.user_uuid
                || entry.value
            );
        }

        return '';
    };

    const normalizeReceiptUserIds = (items) => {
        if (!Array.isArray(items)) {
            return [];
        }

        return Array.from(new Set(items
            .map((entry) => extractReceiptUserId(entry))
            .filter((id) => id !== '')));
    };

    const getConversationParticipantIds = () => {
        const ids = Object.keys(participantsMap || {}).map((id) => normalizeUserId(id));
        return Array.from(new Set(ids
            .concat(getCurrentUserIdentifiers())
            .filter((id) => id !== '')));
    };

    const isGroupConversationContext = () => {
        if (currentConversationIsGroup === true) {
            return true;
        }

        const activeConversationItem = getConversationListItem(conversationUuid);
        if (activeConversationItem.length > 0) {
            return (activeConversationItem.attr('data-conversation-is-group') || '').toString() === '1';
        }

        const participantCount = getConversationParticipantIds().length;
        return participantCount > 2;
    };

    const resolveReceiptUserDisplayName = (userId) => {
        const normalizedUserId = normalizeUserId(userId);
        if (normalizedUserId === '') {
            return '@user';
        }

        const userMeta = resolveUserMeta(normalizedUserId);
        if (!userMeta) {
            return normalizedUserId;
        }

        return userMeta.username
            || userMeta.full_name
            || userMeta.name
            || normalizedUserId;
    };

    const resolveReceiptUserAvatar = (userId) => {
        const normalizedUserId = normalizeUserId(userId);
        const userMeta = normalizedUserId !== '' ? resolveUserMeta(normalizedUserId) : null;

        return normalizeAvatarPath(
            userMeta?.avatar_path
            || userMeta?.avatar
            || DEFAULT_AVATAR
        );
    };

    const escapeReceiptText = (value) => {
        return (value ?? '')
            .toString()
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };

    const renderReceiptTickIcon = (isRead) => {
        if (isRead) {
            return '<svg class="conversation-read-receipt-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false">' +
                '<path d="M1.8 8.6l2.1 2.1L9.6 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>' +
                '<path d="M6 8.6l2.1 2.1L13.8 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>' +
                '</svg>';
        }

        return '<svg class="conversation-read-receipt-icon" viewBox="0 0 16 16" aria-hidden="true" focusable="false">' +
            '<path d="M4 8.6l2.1 2.1L12 5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path>' +
            '</svg>';
    };

    const parseReceiptStateValue = (rawValue) => {
        if (Array.isArray(rawValue)) {
            return normalizeReceiptUserIds(rawValue);
        }

        const textValue = (rawValue ?? '').toString().trim();
        if (textValue === '') {
            return [];
        }

        try {
            const parsed = JSON.parse(textValue);
            return normalizeReceiptUserIds(parsed);
        } catch (e) {
            return normalizeReceiptUserIds(textValue.split(','));
        }
    };

    const getMessageReceiptState = (messageEl) => {
        return {
            readBy: parseReceiptStateValue(messageEl.attr('data-read-by')),
            unreadBy: parseReceiptStateValue(messageEl.attr('data-unread-by')),
            senderId: normalizeUserId(messageEl.attr('data-sender-id')),
        };
    };

    const setMessageReceiptState = (messageEl, readBy, unreadBy, senderId = '') => {
        const normalizedReadBy = normalizeReceiptUserIds(readBy);
        const normalizedUnreadBy = normalizeReceiptUserIds(unreadBy)
            .filter((id) => !normalizedReadBy.includes(id));

        messageEl.attr('data-read-by', JSON.stringify(normalizedReadBy));
        messageEl.attr('data-unread-by', JSON.stringify(normalizedUnreadBy));
        if (senderId !== '') {
            messageEl.attr('data-sender-id', senderId);
        }
    };

    const ensureUnreadReceiptUserIds = (readBy, senderId, unreadBy = []) => {
        const normalizedSenderId = normalizeUserId(senderId);
        const existingUnread = normalizeReceiptUserIds(unreadBy);
        if (existingUnread.length > 0) {
            return existingUnread
                .filter((id) => id !== normalizedSenderId && !readBy.includes(id));
        }

        return getConversationParticipantIds()
            .filter((participantId) => participantId !== normalizedSenderId)
            .filter((participantId) => !readBy.includes(participantId));
    };

    const renderMessageReadReceipt = (messageEl) => {
        if (!readReceiptsEnabled || !messageEl.length || !messageEl.hasClass('sent')) {
            messageEl.find('.conversation-message-read-receipt').remove();
            return;
        }

        const state = getMessageReceiptState(messageEl);
        const senderId = state.senderId || normalizeUserId(messageEl.attr('data-sender-id'));
        const readBy = state.readBy.filter((id) => id !== senderId);
        const unreadBy = state.unreadBy.filter((id) => id !== senderId && !readBy.includes(id));
        const isGroupConversation = isGroupConversationContext();

        const readNames = readBy.map((id) => resolveReceiptUserDisplayName(id));
        const unreadNames = unreadBy.map((id) => resolveReceiptUserDisplayName(id));
        const readByLabel = t('read_by', 'Read by');
        const unreadByLabel = t('unread_by', 'Unread by');
        const sentLabel = t('sent', 'Sent');
        const readLabel = t('read', 'Read');

        let receiptHtml = '';
        if (!isGroupConversation) {
            if (readBy.length > 0) {
                const readerId = readBy[readBy.length - 1];
                const readerName = resolveReceiptUserDisplayName(readerId);
                const readerAvatar = resolveReceiptUserAvatar(readerId);
                const tooltip = escapeReceiptText(readByLabel + ': ' + readerName);

                receiptHtml =
                    '<span class="conversation-read-receipt-state is-read" title="' + tooltip + '">' +
                    renderReceiptTickIcon(true) +
                    '</span>' +
                    '<img class="conversation-read-receipt-avatar" src="' + readerAvatar + '" alt="' + escapeReceiptText(readerName) + '" title="' + tooltip + '">';
            } else {
                receiptHtml =
                    '<span class="conversation-read-receipt-state is-sent" title="' + escapeReceiptText(sentLabel) + '">' +
                    renderReceiptTickIcon(false) +
                    '</span>';
            }
        } else {
            const readTooltip = readBy.length > 0
                ? escapeReceiptText(readByLabel + ': ' + readNames.join(', '))
                : escapeReceiptText(readLabel);

            let avatarsHtml = '';
            if (readBy.length > 0) {
                const maxVisible = 3;
                const visibleReaders = readBy.slice(0, maxVisible);
                const extraReaders = readBy.length - visibleReaders.length;
                const avatarItems = visibleReaders.map((readerId) => {
                    const readerName = resolveReceiptUserDisplayName(readerId);
                    const readerAvatar = resolveReceiptUserAvatar(readerId);
                    return '<img class="conversation-read-receipt-avatar" src="' + readerAvatar + '" alt="' + escapeReceiptText(readerName) + '" title="' + readTooltip + '">';
                }).join('');

                const extraBadge = extraReaders > 0
                    ? '<span class="conversation-read-receipt-extra" title="' + readTooltip + '">+' + extraReaders + '</span>'
                    : '';

                avatarsHtml = '<span class="conversation-read-receipt-avatars">' + avatarItems + extraBadge + '</span>';
            }

            let unreadHtml = '';
            if (readReceiptsShowUnreadInGroup && unreadBy.length > 0) {
                unreadHtml = '<span class="conversation-read-receipt-unread" title="' +
                    escapeReceiptText(unreadByLabel + ': ' + unreadNames.join(', ')) +
                    '">' + unreadBy.length + '</span>';
            }

            receiptHtml =
                '<span class="conversation-read-receipt-state ' + (readBy.length > 0 ? 'is-read' : 'is-sent') + '" title="' + readTooltip + '">' +
                renderReceiptTickIcon(readBy.length > 0) +
                '</span>' +
                avatarsHtml +
                unreadHtml;
        }

        let receiptEl = messageEl.find('.conversation-message-read-receipt').first();
        if (!receiptEl.length) {
            const dateEl = messageEl.find('.conversation-message-date').first();
            if (dateEl.length) {
                dateEl.after('<small class="conversation-message-read-receipt text-muted small d-block"></small>');
            } else {
                messageEl.append('<small class="conversation-message-read-receipt text-muted small d-block"></small>');
            }
            receiptEl = messageEl.find('.conversation-message-read-receipt').first();
        }

        receiptEl.html(receiptHtml);
    };

    const applyMessageReadReceipt = (messageEl, message) => {
        if (!messageEl.length || !messageEl.hasClass('sent') || !readReceiptsEnabled) {
            return;
        }

        const senderId = normalizeUserId(message?.sender_id || message?.sender_uuid || messageEl.attr('data-sender-id'));
        const readBy = normalizeReceiptUserIds(message?.read_by || []);
        const unreadBy = ensureUnreadReceiptUserIds(readBy, senderId, message?.unread_by || []);

        setMessageReceiptState(messageEl, readBy, unreadBy, senderId);
        renderMessageReadReceipt(messageEl);
    };

    const applyReadReceiptFromReadEvent = (eventPayload) => {
        if (!readReceiptsEnabled) {
            return;
        }

        const messageId = normalizeUserId(eventPayload?.message_id || eventPayload?.id);
        if (messageId === '') {
            return;
        }

        const messageEl = $('.conversations-messages-item[data-message-id="' + messageId + '"]').first();
        if (!messageEl.length || !messageEl.hasClass('sent')) {
            return;
        }

        const state = getMessageReceiptState(messageEl);
        const readerId = normalizeUserId(eventPayload?.user_id || eventPayload?.userId || extractReceiptUserId(eventPayload?.user));
        const payloadReadBy = normalizeReceiptUserIds(eventPayload?.read_by || []);

        let readBy = state.readBy.slice();
        if (payloadReadBy.length > 0) {
            readBy = payloadReadBy;
        } else if (readerId !== '' && !readBy.includes(readerId)) {
            readBy.push(readerId);
        }

        const unreadBy = ensureUnreadReceiptUserIds(readBy, state.senderId, state.unreadBy);
        setMessageReceiptState(messageEl, readBy, unreadBy, state.senderId);
        renderMessageReadReceipt(messageEl);
    };

    const normalizeMessage = (messageOrContent, createdAt, id, name, avatar, direction, attachments) => {
        if (typeof messageOrContent === 'object' && messageOrContent !== null) {
            const message = messageOrContent;
            const messageId = message.id || message.message_id;
            const senderId = resolveSenderId(message);
            const senderMeta = resolveUserMeta(senderId);
            const computedDirection = isCurrentUserIdentifier(senderId) ? 'from' : 'to';

            return {
                id: messageId,
                content: (message.content || '').toString(),
                created_at: message.created_at || message.createdAt,
                name: message.sender_name || message.sender?.full_name || message.sender?.username || message.sender?.name || senderMeta?.full_name || senderMeta?.username || senderMeta?.name || '@user',
                avatar: normalizeAvatarPath(message.sender_avatar || message.sender?.avatar_path || message.sender?.avatar || senderMeta?.avatar_path || senderMeta?.avatar || DEFAULT_AVATAR),
                direction: direction || computedDirection,
                attachments: normalizeAttachments(message.attachments || []),
                sender_id: senderId,
                read_by: message.read_by || [],
                unread_by: message.unread_by || [],
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
            sender_id: '',
            read_by: [],
            unread_by: [],
        };
    };

    const buildAttachmentsHtml = (attachments) => {
        if (!Array.isArray(attachments) || attachments.length === 0) {
            return '';
        }

        let html = '<div class="conversation-message-attachments mt-2">';
        attachments.forEach((attachment) => {
            const url = attachment.url || '#';
            const fileName = attachment.original_filename || attachment.filename || 'attachment';
            const isImage = (attachment.type || '').toString() === 'image';
            const thumb = attachment.thumbnail_small_url || attachment.thumbnail_medium_url || url;

            if (isImage) {
                html += '<a href="' + url + '" target="_blank" rel="noopener" class="d-inline-block me-2 mb-1">' +
                    '<img src="' + thumb + '" alt="' + fileName + '" style="max-width:120px;max-height:120px;border-radius:8px;">' +
                    '</a>';
            } else {
                html += '<a href="' + url + '" target="_blank" rel="noopener" class="d-inline-flex align-items-center me-2 mb-1">' +
                    '<i class="fa fa-file me-1"></i>' + fileName +
                    '</a>';
            }
        });
        html += '</div>';

        return html;
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

        const hasContent = message.content.trim() !== '';
        const hasAttachments = Array.isArray(message.attachments) && message.attachments.length > 0;

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
            : (function () {
                if (!hasContent && !hasAttachments) {
                    return false;
                }

                const bodyHtml = (hasContent ? '<p>' + message.content + '</p>' : '') + buildAttachmentsHtml(message.attachments);

                return '<li class="conversations-messages-item ' + (message.direction === 'from' ? 'sent' : 'replies') + '" data-message-id="' + message.id + '">' +
                    '<div>' +
                    '<img src="' + message.avatar + '" alt="' + message.name + '" />' +
                    '<div class="conversation-message-body">' + bodyHtml + '</div>' +
                    '</div>' +
                    '</li>';
            })();

        if (html === false) {
            return;
        }

        const messageNode = $(html);
        if (!messageNode.length) {
            return;
        }

        if (insertMode === 'append') {
            messagesEl.append(messageNode);
            if (message.id) {
                const renderedMessage = messagesEl.find('.conversations-messages-item[data-message-id="' + message.id + '"]').last();
                if (renderedMessage.length) {
                    renderedMessage.attr('data-sender-id', message.sender_id || '');
                    applyMessageReadReceipt(renderedMessage, message);
                }
            }
            return;
        }

        if (isLoadMessages === true || insertMode === 'prepend') {
            messagesEl.prepend(messageNode);
        } else {
            messagesEl.append(messageNode);
        }

        if (message.id) {
            const renderedMessage = messagesEl.find('.conversations-messages-item[data-message-id="' + message.id + '"]').first();
            if (renderedMessage.length) {
                renderedMessage.attr('data-sender-id', message.sender_id || '');
                applyMessageReadReceipt(renderedMessage, message);
            }
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
        if (conversationUuid) {
            const activeConversationItem = $('.conversations-list-items').find('.contact[data-conversation-uuid="' + conversationUuid + '"]').first();
            if (activeConversationItem.length) {
                activeConversationItem.find('.conversation-count-new-messages').text('').hide();
            }
        }

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

    const escapeHtml = (value) => {
        return $('<div/>').text((value ?? '').toString()).html();
    };

    const getConversationListItem = (targetConversationUuid) => {
        const normalizedUuid = (targetConversationUuid || '').toString().trim();
        if (normalizedUuid === '') {
            return $();
        }

        return $('.conversations-list-items').find('.contact[data-conversation-uuid="' + normalizedUuid + '"]').first();
    };

    const getConversationUnreadCount = (conversationItem) => {
        const badge = conversationItem.find('.conversation-count-new-messages').first();
        if (!badge.length) {
            return 0;
        }

        const parsed = parseInt((badge.text() || '0').toString().trim(), 10);
        if (Number.isNaN(parsed)) {
            return 0;
        }

        return Math.max(parsed, 0);
    };

    const setConversationUnreadCount = (conversationItem, count) => {
        const badge = conversationItem.find('.conversation-count-new-messages').first();
        if (!badge.length) {
            return;
        }

        const parsed = parseInt((count ?? 0).toString(), 10);
        const normalizedCount = Number.isNaN(parsed) ? 0 : Math.max(parsed, 0);

        if (normalizedCount > 0) {
            badge.text(String(normalizedCount)).show();
            return;
        }

        badge.text('').hide();
    };

    const moveConversationToTop = (conversationItem) => {
        const list = conversationItem.closest('ul');
        if (!list.length) {
            return;
        }

        list.prepend(conversationItem);
    };

    const updateConversationSearchData = (conversationItem) => {
        const name = (conversationItem.find('.name').first().text() || '').toString().trim();
        const preview = (conversationItem.find('.preview').first().text() || '').toString().trim();
        const relation = (conversationItem.find('.conversation-relation-badge').first().text() || '').toString().trim();
        const haystack = [name, preview, relation].filter((chunk) => chunk !== '').join(' ').toLowerCase();
        conversationItem.attr('data-conversation-title', haystack);
    };

    const resolveMessagePreviewText = (message) => {
        const text = (message?.content || '').toString().trim();
        if (text !== '') {
            return text;
        }

        return t('Attachment', 'Attachment');
    };

    const resolvePreviewSenderLabel = (message, conversationItem) => {
        const senderId = resolveSenderId(message);
        if (isCurrentUserIdentifier(senderId)) {
            return t('You', 'You');
        }

        const senderMeta = resolveUserMeta(senderId);
        if (senderMeta?.username) {
            return senderMeta.username;
        }

        const payloadSenderName = (message?.sender_name || message?.sender?.username || message?.sender?.name || '').toString().trim();
        if (payloadSenderName !== '') {
            return payloadSenderName;
        }

        const isGroup = ((conversationItem?.attr('data-conversation-is-group') || '').toString() === '1');
        if (!isGroup) {
            const itemName = (conversationItem?.find('.name').first().text() || '').toString().trim();
            if (itemName !== '') {
                return itemName;
            }
        }

        return '@user';
    };

    const updateConversationListEntry = (targetConversationUuid, message, options = {}) => {
        const conversationItem = getConversationListItem(targetConversationUuid);
        if (!conversationItem.length) {
            return;
        }

        const nameEl = conversationItem.find('.name').first();
        if (nameEl.length && (nameEl.text() || '').toString().trim() === '') {
            nameEl.text(t('Conversation', 'Conversation'));
        }

        const previewEl = conversationItem.find('.preview').first();
        if (previewEl.length && message) {
            const senderLabel = resolvePreviewSenderLabel(message, conversationItem);
            const previewText = resolveMessagePreviewText(message);
            previewEl.html('<span>' + escapeHtml(senderLabel) + ':</span> ' + escapeHtml(previewText));
        }

        if (options.bump !== false) {
            moveConversationToTop(conversationItem);
        }

        if (options.resetUnread === true) {
            setConversationUnreadCount(conversationItem, 0);
        } else if (options.incrementUnread === true) {
            setConversationUnreadCount(conversationItem, getConversationUnreadCount(conversationItem) + 1);
        }

        updateConversationSearchData(conversationItem);
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

    const unwrapEventPayload = (payload) => {
        if (!payload || typeof payload !== 'object') {
            return null;
        }

        if (payload.message && typeof payload.message === 'object') {
            return payload.message;
        }

        if (payload.data && typeof payload.data === 'object') {
            if (payload.data.message && typeof payload.data.message === 'object') {
                return payload.data.message;
            }

            return payload.data;
        }

        return payload;
    };

    const resolveConversationUuidFromPayload = (payload) => {
        return (
            payload?.conversation_uuid
            || payload?.conversationUuid
            || payload?.conversation?.uuid
            || payload?.conversation?.conversation_uuid
            || ''
        ).toString();
    };

    const buildRealtimeMessageKey = (payload) => {
        const normalizedPayload = unwrapEventPayload(payload) || payload || {};
        const conversationKey = resolveConversationUuidFromPayload(normalizedPayload)
            || resolveConversationUuidFromPayload(payload)
            || '';
        const messageId = (
            normalizedPayload?.id
            || normalizedPayload?.message_id
            || payload?.id
            || payload?.message_id
            || ''
        ).toString();

        if (conversationKey === '' || messageId === '') {
            return '';
        }

        return conversationKey + ':' + messageId;
    };

    const shouldProcessRealtimeMessage = (payload) => {
        const key = buildRealtimeMessageKey(payload);
        if (key === '') {
            return true;
        }

        if (processedRealtimeMessageMap[key]) {
            return false;
        }

        processedRealtimeMessageMap[key] = Date.now();

        const cutoff = Date.now() - (5 * 60 * 1000);
        Object.keys(processedRealtimeMessageMap).forEach((storedKey) => {
            if (processedRealtimeMessageMap[storedKey] < cutoff) {
                delete processedRealtimeMessageMap[storedKey];
            }
        });

        return true;
    };

    const handleIncomingMessage = (message) => {
        const eventMessage = unwrapEventPayload(message);
        if (!eventMessage) {
            return;
        }

        const payloadConversationUuid = resolveConversationUuidFromPayload(eventMessage)
            || resolveConversationUuidFromPayload(message);

        if (payloadConversationUuid !== conversationUuid) {
            return;
        }

        const messageId = eventMessage.id || eventMessage.message_id || null;
        if (messageId && $('.conversations-messages-item[data-message-id="' + messageId + '"]').length > 0) {
            return;
        }

        fetchMessageAttachments(eventMessage).then((resolvedMessage) => {
            setMessage(containerConv, resolvedMessage, null, null, null, null, null, false, null, 'append');
            scrollToEnd();
            humanizeDate();
            updateConversationListEntry(payloadConversationUuid, resolvedMessage, {
                resetUnread: true,
            });
            markAsRead(resolvedMessage.id || resolvedMessage.message_id || null);
        });
    };

    const syncLatestMessages = () => {
        if (!getMessagesRoute || !conversationUuid || messagesRequestInFlight) {
            return;
        }

        axios.get(getMessagesRoute, {
            headers: getAuthHeaders(),
            params: {
                order: 'desc',
                limit: MESSAGE_BATCH_SIZE,
                start: 0,
            },
        }).then((response) => {
            const payload = response?.data || {};
            const items = Array.isArray(payload.data) ? payload.data.slice().reverse() : [];

            let appended = false;
            items.forEach((message) => {
                const messageId = message?.id || message?.message_id || null;
                if (!messageId) {
                    return;
                }

                if ($('.conversations-messages-item[data-message-id="' + messageId + '"]').length > 0) {
                    return;
                }

                setMessage(containerConv, message, null, null, null, null, null, false, null, 'append');
                appended = true;
            });

            if (appended) {
                scrollToEnd();
                humanizeDate();
                markAsRead(null);
            }
        }).catch(() => {});
    };

    const startMessageSyncFallback = () => {
        if (messageSyncIntervalId) {
            clearInterval(messageSyncIntervalId);
            messageSyncIntervalId = null;
        }

        if (!conversationUuid || !getMessagesRoute) {
            return;
        }

        messageSyncIntervalId = setInterval(syncLatestMessages, MESSAGE_SYNC_INTERVAL_MS);
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

    const bindConversationRealtimeChannel = (pusher, targetConversationUuid) => {
        const normalizedUuid = (targetConversationUuid || '').toString().trim();
        if (normalizedUuid === '' || subscribedConversationChannels[normalizedUuid]) {
            return;
        }

        debugRealtime('subscribe conversation channel', {
            channel: 'private-conversation.' + normalizedUuid,
            conversation_uuid: normalizedUuid,
        });

        const conversationChannel = typeof PusherConnect.subscribe === 'function'
            ? PusherConnect.subscribe(pusher, 'private-conversation.' + normalizedUuid)
            : pusher.subscribe('private-conversation.' + normalizedUuid);

        if (!conversationChannel) {
            return;
        }

        subscribedConversationChannels[normalizedUuid] = conversationChannel;

        if (typeof conversationChannel.bind_global === 'function') {
            conversationChannel.bind_global(function (eventName, payload) {
                debugRealtime('event *', {
                    channel: 'private-conversation.' + normalizedUuid,
                    event: eventName,
                    payload: payload,
                });
            });
        }

        conversationChannel.bind('message.sent', function (payload) {
            debugRealtime('event message.sent', payload);
            const eventMessage = unwrapEventPayload(payload);
            if (!eventMessage) {
                return;
            }

            if (!shouldProcessRealtimeMessage(eventMessage)) {
                return;
            }

            const payloadConversationUuid = resolveConversationUuidFromPayload(eventMessage)
                || resolveConversationUuidFromPayload(payload)
                || normalizedUuid;
            const senderId = resolveSenderId(eventMessage);
            const isSenderCurrentUser = isCurrentUserIdentifier(senderId);
            const isCurrentConversation = conversationUuid && payloadConversationUuid === conversationUuid;

            updateConversationListEntry(payloadConversationUuid, eventMessage, {
                incrementUnread: !isSenderCurrentUser && !isCurrentConversation,
                resetUnread: isCurrentConversation,
            });

            if (!isCurrentConversation || isSenderCurrentUser) {
                return;
            }

            handleIncomingMessage(eventMessage);
        });

        conversationChannel.bind('message.deleted', function (payload) {
            debugRealtime('event message.deleted', payload);
            const eventPayload = unwrapEventPayload(payload) || {};
            const payloadConversationUuid = resolveConversationUuidFromPayload(eventPayload) || normalizedUuid;
            if (payloadConversationUuid === conversationUuid) {
                $('.conversations-messages-item[data-message-id="' + (eventPayload.message_id || eventPayload.id) + '"]').remove();
            }
        });

        conversationChannel.bind('message.edited', function (payload) {
            debugRealtime('event message.edited', payload);
            const eventPayload = unwrapEventPayload(payload) || {};
            const payloadConversationUuid = resolveConversationUuidFromPayload(eventPayload) || normalizedUuid;

            updateConversationListEntry(payloadConversationUuid, eventPayload, { bump: false });

            if (payloadConversationUuid !== conversationUuid) {
                return;
            }

            const messageId = eventPayload.message_id || eventPayload.id || null;
            if (!messageId) {
                return;
            }

            const messageEl = $('.conversations-messages-item[data-message-id="' + messageId + '"]');
            if (!messageEl.length) {
                return;
            }

            const content = (eventPayload.content || '').toString();
            messageEl.find('p').first().text(content);
            const date = eventPayload.created_at || eventPayload.updated_at || null;
            if (date) {
                const dateEl = messageEl.find('.conversation-message-date').first();
                dateEl.attr('data-message-date', date);
                dateEl.text(date);
                humanizeDate();
            }
        });

        conversationChannel.bind('message.read', function (payload) {
            debugRealtime('event message.read', payload);
            const eventPayload = unwrapEventPayload(payload) || {};
            const payloadConversationUuid = resolveConversationUuidFromPayload(eventPayload) || normalizedUuid;
            const listConversation = getConversationListItem(payloadConversationUuid);
            if (!listConversation.length) {
                return;
            }

            if (payloadConversationUuid === conversationUuid || isCurrentUserIdentifier((eventPayload.user_id || eventPayload.userId || '').toString())) {
                setConversationUnreadCount(listConversation, 0);
            }

            if (payloadConversationUuid === conversationUuid) {
                applyReadReceiptFromReadEvent(eventPayload);
            }
        });

        conversationChannel.bind('user.typing', function (payload) {
            debugRealtime('event user.typing', payload);
            const eventPayload = unwrapEventPayload(payload) || {};
            const payloadConversationUuid = resolveConversationUuidFromPayload(eventPayload) || normalizedUuid;
            if (isCurrentUserIdentifier((eventPayload.user_id || eventPayload.userId || '').toString()) || payloadConversationUuid !== conversationUuid) {
                return;
            }

            const typingUserId = (eventPayload.user_id || eventPayload.userId || '').toString();
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

    const subscribeListedConversationChannels = (pusher) => {
        $('.conversations-list-items .contact').each(function () {
            const rowConversationUuid = ($(this).data('conversationUuid') || $(this).attr('data-conversation-uuid') || '').toString().trim();
            if (rowConversationUuid !== '') {
                bindConversationRealtimeChannel(pusher, rowConversationUuid);
            }
        });

        if (conversationUuid) {
            bindConversationRealtimeChannel(pusher, conversationUuid);
        }
    };

    const connectRealtime = () => {
        const dso = resolveDSO();
        let realtimeConfig = dso.config('realtime') || {};
        if ((!realtimeConfig || Object.keys(realtimeConfig).length === 0)
            && typeof window !== 'undefined'
            && typeof window.DSOConfig !== 'undefined'
            && window.DSOConfig
            && typeof window.DSOConfig === 'object'
        ) {
            realtimeConfig = (window.DSOConfig.config && window.DSOConfig.config.realtime)
                ? window.DSOConfig.config.realtime
                : realtimeConfig;
        }

        const connectionName = (realtimeConfig.connection || '').toString().toLowerCase();

        if (!realtimeConfig.key && realtimeConnectAttempts < REALTIME_BOOT_MAX_ATTEMPTS) {
            realtimeConnectAttempts += 1;
            debugRealtime('waiting for realtime config', {
                attempt: realtimeConnectAttempts,
                max_attempts: REALTIME_BOOT_MAX_ATTEMPTS,
            });

            setTimeout(connectRealtime, REALTIME_BOOT_RETRY_MS);
            return;
        }

        if (!realtimeConfig.key || ['null', 'log', 'redis'].includes(connectionName)) {
            startMessageSyncFallback();
            return;
        }

        realtimeConnectAttempts = 0;

        let pusher = null;
        try {
            if (typeof PusherConnect !== 'undefined' && typeof PusherConnect.getInstance === 'function') {
                pusher = PusherConnect.getInstance(realtimeConfig);
            }
        } catch (error) {
            console.error('Realtime initialization failed:', error);
            startMessageSyncFallback();
            return;
        }

        if (!pusher) {
            startMessageSyncFallback();
            return;
        }

        const userId = getUserIdentifier();
        if (userId) {
            debugRealtime('subscribe user conversation channel', {
                channel: 'private-conversation.user.' + userId,
                user_id: userId,
            });

            const userChannel = typeof PusherConnect.subscribe === 'function'
                ? PusherConnect.subscribe(pusher, 'private-conversation.user.' + userId)
                : pusher.subscribe('private-conversation.user.' + userId);

            if (userChannel && typeof userChannel.bind_global === 'function') {
                userChannel.bind_global(function (eventName, payload) {
                    debugRealtime('event user.*', {
                        channel: 'private-conversation.user.' + userId,
                        event: eventName,
                        payload: payload,
                    });
                });
            }

            userChannel.bind('message.sent', function (payload) {
                debugRealtime('event user.message.sent', payload);
                const eventMessage = unwrapEventPayload(payload);
                if (!eventMessage) {
                    return;
                }

                if (!shouldProcessRealtimeMessage(eventMessage)) {
                    return;
                }

                const payloadConversationUuid = resolveConversationUuidFromPayload(eventMessage)
                    || resolveConversationUuidFromPayload(payload);
                if (!payloadConversationUuid) {
                    return;
                }

                const senderId = resolveSenderId(eventMessage);
                const isSenderCurrentUser = isCurrentUserIdentifier(senderId);
                const isCurrentConversation = conversationUuid && payloadConversationUuid === conversationUuid;

                updateConversationListEntry(payloadConversationUuid, eventMessage, {
                    incrementUnread: !isSenderCurrentUser && !isCurrentConversation,
                    resetUnread: isCurrentConversation,
                });

                if (!isCurrentConversation || isSenderCurrentUser) {
                    return;
                }

                handleIncomingMessage(eventMessage);
            });

            userChannel.bind('message.read', function (payload) {
                debugRealtime('event user.message.read', payload);
                const eventPayload = unwrapEventPayload(payload) || {};
                const payloadConversationUuid = resolveConversationUuidFromPayload(eventPayload)
                    || resolveConversationUuidFromPayload(payload);
                if (!payloadConversationUuid) {
                    return;
                }

                if (payloadConversationUuid === conversationUuid || isCurrentUserIdentifier((eventPayload.user_id || eventPayload.userId || '').toString())) {
                    const listConversation = getConversationListItem(payloadConversationUuid);
                    if (listConversation.length) {
                        setConversationUnreadCount(listConversation, 0);
                    }
                }

                if (payloadConversationUuid === conversationUuid) {
                    applyReadReceiptFromReadEvent(eventPayload);
                }
            });

            userChannel.bind('conversation.created', function (payload) {
                debugRealtime('event conversation.created', { channel: userChannel.name, payload: payload });
                const payloadConversationUuid = resolveConversationUuidFromPayload(payload);
                if (payloadConversationUuid) {
                    bindConversationRealtimeChannel(pusher, payloadConversationUuid);
                }
                window.location.reload();
            });
        }

        subscribeListedConversationChannels(pusher);

        startMessageSyncFallback();
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
            updateConversationListEntry(conversationUuid, message, {
                resetUnread: true,
            });
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
                    const currentTitle = ($('.conversation-title').text() || '').trim();
                    let modal = $('#conversationTitleModal');
                    if (!modal.length) {
                        const modalHtml = '' +
                            '<div class="modal fade" id="conversationTitleModal" tabindex="-1" aria-hidden="true">' +
                            '  <div class="modal-dialog">' +
                            '    <div class="modal-content">' +
                            '      <div class="modal-header">' +
                            '        <h5 class="modal-title">' + t('Edit', 'Edit') + '</h5>' +
                            '        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>' +
                            '      </div>' +
                            '      <div class="modal-body">' +
                            '        <label class="form-label">' + t('Title', 'Title') + '</label>' +
                            '        <input type="text" class="form-control conversation-title-modal-value" maxlength="255">' +
                            '      </div>' +
                            '      <div class="modal-footer">' +
                            '        <button type="button" class="btn btn-light" data-bs-dismiss="modal">' + t('Cancel', 'Cancel') + '</button>' +
                            '        <button type="button" class="btn btn-primary conversation-title-modal-save">' + t('Save', 'Save') + '</button>' +
                            '      </div>' +
                            '    </div>' +
                            '  </div>' +
                            '</div>';

                        $('body').append(modalHtml);
                        modal = $('#conversationTitleModal');
                    }

                    modal.find('.conversation-title-modal-value').val(currentTitle);
                    const bsModal = bootstrap.Modal.getOrCreateInstance(modal[0]);
                    bsModal.show();
                });

                $('body').on('click', '.conversation-title-modal-save', function () {
                    const modal = $('#conversationTitleModal');
                    const title = (modal.find('.conversation-title-modal-value').val() || '').toString().trim();
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
                            $('.conversation-title').html(title);
                            const bsModal = bootstrap.Modal.getOrCreateInstance(modal[0]);
                            bsModal.hide();
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
