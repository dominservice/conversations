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

return new class extends Migration {

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $userModel = new (config('conversations.user_model'));

        if (!Schema::hasTable(config('conversations.tables.conversations'))) {
            Schema::create(config('conversations.tables.conversations'), function (Blueprint $table) {
                $table->uuid()->primary();
                $table->unsignedBigInteger('parent_id');
                $table->string('parent_type');
                $table->timestamps();
                $table->softDeletes();
            });
        }
        if (!Schema::hasTable(config('conversations.tables.conversation_users'))) {
            Schema::create(config('conversations.tables.conversation_users'), function (Blueprint $table) use($userModel) {
                $table->uuid('conversation_uuid');
                $table->foreign('conversation_uuid', 'con_user')
                    ->references('uuid')
                    ->on(config('conversations.tables.conversations'))
                    ->onDelete('cascade');

                if ($userModel->getKeyType() === 'uuid') {
                    $table->uuid('user_uuid');
                    $table->foreign('user_uuid')
                        ->references($userModel->getKeyName())
                        ->on($userModel->getTable())
                        ->onDelete('cascade');
                    $table->primary(array('conversation_uuid', 'user_uuid'));
                } else {
                    $table->foreignId('user_id')
                        ->references($userModel->getKeyName())
                        ->on($userModel->getTable())
                        ->onDelete('cascade');
                    $table->primary(array('conversation_uuid', 'user_id'));
                }
            });
        }
        if (!Schema::hasTable(config('conversations.tables.conversation_messages'))) {
            Schema::create(config('conversations.tables.conversation_messages'), function (Blueprint $table) use($userModel) {
                $table->id();
                $table->uuid('conversation_uuid');
                $table->foreign('conversation_uuid', 'con_mess')
                    ->references('uuid')
                    ->on(config('conversations.tables.conversations'))
                    ->onDelete('cascade');

                if ($userModel->getKeyType() === 'uuid') {
                    $table->uuid('sender_uuid')->nullable();
                    $table->foreign('sender_uuid')
                        ->references($userModel->getKeyName())
                        ->on($userModel->getTable())
                        ->onDelete('set null');
                    $table->index('sender_uuid');
                } else {
                    $table->foreignId('sender_id')
                        ->nullable()
                        ->references($userModel->getKeyName())
                        ->on($userModel->getTable())
                        ->onDelete('set null');
                    $table->index('sender_id');
                }

                $table->text('content');
                $table->timestamps();
                $table->index('conversation_uuid');
            });
        }
        if (!Schema::hasTable(config('conversations.tables.conversation_message_statuses'))) {
            Schema::create(config('conversations.tables.conversation_message_statuses'), function (Blueprint $table) use($userModel) {
                $table->id();

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

                $table->foreignId('message_id')
                    ->references('id')
                    ->on(config('conversations.tables.conversation_messages'))
                    ->onDelete('cascade');
                $table->boolean('self');
                $table->integer('status');
                $table->index('message_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop(config('conversations.tables.conversation_message_statuses'));
        Schema::drop(config('conversations.tables.conversation_messages'));
        Schema::drop(config('conversations.tables.conversation_users'));
        Schema::drop(config('conversations.tables.conversations'));
    }

};
