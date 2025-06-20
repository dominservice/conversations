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
        if (Schema::hasTable(config('conversations.tables.conversations'))) {
            Schema::table(config('conversations.tables.conversations'), function (Blueprint $table) {
                $table->string('title')->after('uuid')->nullable();
                $table->foreignId('type_id')->after('title')->nullable()
                    ->references('id')
                    ->on(config('conversations.tables.conversation_types'))
                    ->onDelete('cascade');

                $userModel = new (config('conversations.user_model'));
                if ($userModel->getKeyType() === 'uuid') {
                    $table->uuid('owner_uuid')->after('uuid')->nullable();
                    $table->foreign('owner_uuid')
                        ->references($userModel->getKeyName())
                        ->on($userModel->getTable())
                        ->onDelete('set null');
                    $table->index('owner_uuid');
                } else {
                    $table->foreignId('owner_id')->after('uuid')
                        ->nullable()
                        ->references($userModel->getKeyName())
                        ->on($userModel->getTable())
                        ->onDelete('set null');
                    $table->index('owner_id');
                }
            });
        }
        if (Schema::hasTable(config('conversations.tables.conversation_messages'))) {
            Schema::table(config('conversations.tables.conversation_messages'), function (Blueprint $table) {
                $table->string('type')->after('content')->default('text');
            });
        }

        if (Schema::hasTable(config('conversations.tables.conversation_users'))) {
            Schema::table(config('conversations.tables.conversation_users'), function (Blueprint $table) {
                $table->boolean('is_conversation_deleted')->default(0);
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
        if (Schema::hasTable(config('conversations.tables.conversation_messages'))) {
            Schema::table(config('conversations.tables.conversation_messages'), function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
        if (Schema::hasTable(config('conversations.tables.conversations'))) {
            Schema::table(config('conversations.tables.conversations'), function (Blueprint $table) {
                $table->dropColumn('title');
                $table->dropColumn('type_id');
                $userModel = new (config('conversations.user_model'));
                if ($userModel->getKeyType() === 'uuid') {
                    $table->dropColumn('owner_uuid');
                } else {
                    $table->dropColumn('owner_id');
                }
            });
        }
    }

};
