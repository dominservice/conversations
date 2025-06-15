# Conversations Translations

This package includes a translation system that allows you to customize all messages in different languages. It also supports model translations using the [astrotomic/laravel-translatable](https://github.com/Astrotomic/laravel-translatable) package.

## Available Translations

By default, the package includes English translations for all messages. You can customize these translations or add new languages as needed.

## Publishing Translations

To customize the translations, you need to publish them to your application:

```bash
php artisan vendor:publish --provider="Dominservice\Conversations\ConversationsServiceProvider" --tag="translations"
```

This will copy the translation files to the `resources/lang/vendor/conversations` directory in your application.

## Customizing Translations

After publishing, you can edit the translation files in the `resources/lang/vendor/conversations` directory. The package will use these translations instead of the default ones.

### Adding New Languages

To add a new language, create a new directory in `resources/lang/vendor/conversations` with the language code (e.g., `de` for German) and copy the structure from the English translations.

For example, to add German translations:

1. Create the directory: `resources/lang/vendor/conversations/de`
2. Create a file: `resources/lang/vendor/conversations/de/conversations.php`
3. Copy the structure from the English file and translate the messages

## Translation Keys

The package uses the following translation keys:

### Conversation Messages

```php
'conversation' => [
    'not_found' => 'Conversation not found',
    'created' => 'Conversation created successfully',
    'create_failed' => 'Failed to create conversation',
    'deleted' => 'Conversation deleted successfully',
    'unauthorized' => 'Unauthorized',
],
```

### Message Messages

```php
'message' => [
    'sent' => 'Message sent successfully',
    'create_failed' => 'Failed to create message',
    'marked_read' => 'Message marked as read',
    'marked_unread' => 'Message marked as unread',
    'deleted' => 'Message deleted successfully',
    'typing_sent' => 'Typing indicator sent',
],
```

## Using Translations in Custom Code

If you're extending the package with custom code, you can use the translation system like this:

```php
// Using the trans helper
$message = trans('conversations::conversations.conversation.not_found');

// Using the Lang facade
$message = Lang::get('conversations::conversations.conversation.not_found');
```

The `conversations::` prefix is required to access the package's translations.

## Model Translations

The package uses the [astrotomic/laravel-translatable](https://github.com/Astrotomic/laravel-translatable) package to provide model translations. Currently, the following models support translations:

- `ConversationType` - Translatable fields: `name`

### Using Translatable Models

You can use the translatable models just like any other model, but with the added ability to access and set translations:

```php
// Get a conversation type
$type = ConversationType::find(1);

// Get the name in the current locale
$name = $type->name;

// Get the name in a specific locale
$name = $type->getTranslation('name', 'fr');

// Set the name for the current locale
$type->name = 'Group Chat';
$type->save();

// Set the name for a specific locale
$type->translateOrNew('fr')->name = 'Chat de groupe';
$type->save();

// Create a new conversation type with translations
$type = new ConversationType();
$type->color = '#FF0000';
$type->custom = true;
$type->fill([
    'en' => ['name' => 'Team Chat'],
    'fr' => ['name' => 'Chat d\'équipe'],
]);
$type->save();
```

For more information on how to use the translatable models, please refer to the [astrotomic/laravel-translatable documentation](https://docs.astrotomic.info/laravel-translatable/).
