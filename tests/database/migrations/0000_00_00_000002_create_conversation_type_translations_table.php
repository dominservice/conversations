<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConversationTypeTranslationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable(config('conversations.tables.conversation_type_translations'))) {
            Schema::create(config('conversations.tables.conversation_type_translations'), function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_type_id');
                $table->string('locale')->index();
                $table->string('name');

                $table->unique(['conversation_type_id', 'locale']);
                $table->foreign('conversation_type_id')
                    ->references('id')
                    ->on(config('conversations.tables.conversation_types'))
                    ->onDelete('cascade');
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
        Schema::drop(config('conversations.tables.conversation_type_translations'));
    }
}