<?php

/**
 * Conversations
 *
 * This package will allow you to add a full user messaging system
 * into your Laravel application.
 *
 * @package   Dominservice\Conversations
 * @author    DSO-IT Mateusz Domin <biuro@dso.biz.pl>
 * @copyright (c) 2021 DSO-IT Mateusz Domin
 * @license   MIT
 * @version   3.0.0
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Add message_type column to conversation_messages table (from add_attachment_support_to_conversation_messages_table.php.stub)
        if (!Schema::hasColumn(config('conversations.tables.conversation_messages'), 'message_type')) {
            Schema::table(config('conversations.tables.conversation_messages'), function (Blueprint $table) {
                $table->string('message_type')->default('text')->after('content');
            });
        }

        // 2. Create conversation_attachments table (from add_attachment_support_to_conversation_messages_table.php.stub)
        if (!Schema::hasTable(config('conversations.tables.conversation_attachments', 'conversation_attachments'))) {
            Schema::create(config('conversations.tables.conversation_attachments', 'conversation_attachments'), function (Blueprint $table) {
                $table->id();
                $table->foreignId('message_id')
                    ->references('id')
                    ->on(config('conversations.tables.conversation_messages'))
                    ->onDelete('cascade');
                $table->string('filename');
                $table->string('original_filename');
                $table->string('mime_type');
                $table->string('extension', 10);
                $table->string('type')->default('file'); // file, image, document, audio, video
                $table->unsignedBigInteger('size')->comment('File size in bytes');
                $table->string('path');
                $table->json('metadata')->nullable()->comment('Additional metadata like dimensions, duration, etc.');
                $table->boolean('is_optimized')->default(false);
                $table->boolean('is_scanned')->default(false);
                $table->boolean('is_safe')->default(true);
                $table->timestamps();
                
                $table->index('message_id');
                $table->index('type');
            });
        }

        // 3. Add edited_at and editable columns to conversation_messages table (from add_message_editing_support.php.stub)
        if (!Schema::hasColumn(config('conversations.tables.conversation_messages'), 'edited_at')) {
            Schema::table(config('conversations.tables.conversation_messages'), function (Blueprint $table) {
                $table->timestamp('edited_at')->nullable()->after('created_at');
                $table->boolean('editable')->default(true)->after('message_type');
            });
        }

        // 4. Create conversation_message_reactions table (from add_message_reactions_support.php.stub)
        $userModel = new (config('conversations.user_model'));

        if (!Schema::hasTable(config('conversations.tables.conversation_message_reactions', 'conversation_message_reactions'))) {
            Schema::create(config('conversations.tables.conversation_message_reactions', 'conversation_message_reactions'), function (Blueprint $table) use($userModel) {
                $table->id();
                $table->foreignId('message_id')
                    ->references('id')
                    ->on(config('conversations.tables.conversation_messages'))
                    ->onDelete('cascade');

                // User foreign key (supports both UUID and integer IDs)
                if ($userModel->getKeyType() === 'uuid') {
                    $table->uuid('user_uuid');
                    $table->foreign('user_uuid')
                        ->references($userModel->getKeyName())
                        ->on($userModel->getTable())
                        ->onDelete('cascade');
                } else {
                    $table->foreignId('user_id')
                        ->references($userModel->getKeyName())
                        ->on($userModel->getTable())
                        ->onDelete('cascade');
                }

                $table->string('reaction', 50); // Store the emoji or reaction code
                $table->timestamps();

                // Add indexes
                $table->index('message_id');
                if ($userModel->getKeyType() === 'uuid') {
                    $table->index('user_uuid');
                    // Ensure a user can only react once with the same emoji to a message
                    $table->unique(['message_id', 'user_uuid', 'reaction'], 'unique_user_reaction_uuid');
                } else {
                    $table->index('user_id');
                    // Ensure a user can only react once with the same emoji to a message
                    $table->unique(['message_id', 'user_id', 'reaction'], 'unique_user_reaction');
                }
            });
        }

        // 5. Add parent_id column to conversation_messages table for thread support (from add_thread_support_to_conversation_messages_table.php.stub)
        if (!Schema::hasColumn(config('conversations.tables.conversation_messages'), 'parent_id')) {
            Schema::table(config('conversations.tables.conversation_messages'), function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('conversation_uuid');
                $table->foreign('parent_id')
                    ->references('id')
                    ->on(config('conversations.tables.conversation_messages'))
                    ->onDelete('set null');
                $table->index('parent_id');
            });
        }

        // 6. Create conversation_type_translations table (from create_conversation_type_translations_table.php.stub)
        if (!Schema::hasTable('conversation_type_translations')) {
            Schema::create('conversation_type_translations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_type_id')
                    ->references('id')
                    ->on(config('conversations.tables.conversation_types'))
                    ->onDelete('cascade');
                $table->string('locale')->index();
                $table->string('name');
                
                $table->unique(['conversation_type_id', 'locale']);
            });

            // Migrate existing data
            if (Schema::hasTable(config('conversations.tables.conversation_types'))) {
                $types = DB::table(config('conversations.tables.conversation_types'))->get();
                $locale = config('app.locale', 'en');
                
                foreach ($types as $type) {
                    DB::table('conversation_type_translations')->insert([
                        'conversation_type_id' => $type->id,
                        'locale' => $locale,
                        'name' => $type->name,
                    ]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // 6. Drop conversation_type_translations table
        Schema::dropIfExists('conversation_type_translations');

        // 5. Remove parent_id column from conversation_messages table
        if (Schema::hasColumn(config('conversations.tables.conversation_messages'), 'parent_id')) {
            Schema::table(config('conversations.tables.conversation_messages'), function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
                $table->dropIndex(['parent_id']);
                $table->dropColumn('parent_id');
            });
        }

        // 4. Drop conversation_message_reactions table
        Schema::dropIfExists(config('conversations.tables.conversation_message_reactions', 'conversation_message_reactions'));

        // 3. Remove edited_at and editable columns from conversation_messages table
        if (Schema::hasColumn(config('conversations.tables.conversation_messages'), 'edited_at')) {
            Schema::table(config('conversations.tables.conversation_messages'), function (Blueprint $table) {
                $table->dropColumn('edited_at');
                $table->dropColumn('editable');
            });
        }

        // 2. Drop conversation_attachments table
        Schema::dropIfExists(config('conversations.tables.conversation_attachments', 'conversation_attachments'));

        // 1. Remove message_type column from conversation_messages table
        if (Schema::hasColumn(config('conversations.tables.conversation_messages'), 'message_type')) {
            Schema::table(config('conversations.tables.conversation_messages'), function (Blueprint $table) {
                $table->dropColumn('message_type');
            });
        }
    }
};