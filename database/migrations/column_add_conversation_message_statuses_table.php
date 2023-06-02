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
 * @version   1.0.0
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
        if (Schema::hasTable(config('conversations.tables.conversation_message_statuses'))) {
            Schema::table(config('conversations.tables.conversation_message_statuses'), function (Blueprint $table) {
                $table->timestamp('notified_at')->after('status')->nullable();
            });
        }
        if (Schema::hasTable(config('conversations.tables.conversations'))) {
            Schema::table(config('conversations.tables.conversations'), function (Blueprint $table) {
                $table->boolean('read_only')->after('type_id')->default(0)();
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
        if (Schema::hasTable(config('conversations.tables.conversations'))) {
            Schema::table(config('conversations.tables.conversation_message_statuses'), function (Blueprint $table) {
                $table->dropColumn('read_only');
            });
        }
        if (Schema::hasTable(config('conversations.tables.conversations'))) {
            Schema::table(config('conversations.tables.conversation_message_statuses'), function (Blueprint $table) {
                $table->dropColumn('notified_at');
            });
        }
    }

};
