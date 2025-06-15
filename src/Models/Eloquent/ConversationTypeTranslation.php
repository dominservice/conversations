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

namespace Dominservice\Conversations\Models\Eloquent;

use Illuminate\Database\Eloquent\Model;

/**
 * Class ConversationTypeTranslation
 * @package Dominservice\Conversations\Models\Eloquent
 */
class ConversationTypeTranslation extends Model
{
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name'];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversation_type_translations');
    }
}
