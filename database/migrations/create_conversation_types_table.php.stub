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
        if (!Schema::hasTable(config('conversations.tables.conversation_types'))) {
            Schema::create(config('conversations.tables.conversation_types'), function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('color', 10)->nullable();
                $table->boolean('custom')->default(1);
                $table->timestamps();
                $table->softDeletes();
            });

            \DB::table(config('conversations.tables.conversation_types'))->insert([
                ['name' => 'single','custom' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'group','custom' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'support','custom' => 0, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop(config('conversations.tables.conversation_types'));
    }

};
