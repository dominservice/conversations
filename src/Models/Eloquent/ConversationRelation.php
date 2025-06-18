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

use Dominservice\Conversations\Traits\ParentMorph;
use Illuminate\Database\Eloquent\Model;

/**
 * Class ConversationRelation
 * @package Dominservice\Conversations\Models\Eloquent
 */
class ConversationRelation extends Model
{
    use ParentMorph;

    public $timestamps = false;

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('conversations.tables.conversation_relations');
    }

    /**
     * Get the owning commentable model.
     */
    public function parent()
    {
        return $this->morphTo();
    }

    /**
     * Get the owning commentable model.
     */
    public function uuidParent()
    {
        return $this->morphTo('uuid_parent');
    }

    /**
     * Get the owning commentable model.
     */
    public function ulidParent()
    {
        return $this->morphTo('ulid_parent');
    }
}
