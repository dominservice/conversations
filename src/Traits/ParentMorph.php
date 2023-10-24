<?php

namespace Dominservice\Conversations\Traits;

trait ParentMorph
{
    public function scopeWhereParent($query, $parent)
    {
        return $query->whereParentType($parent->getMorphClass())
            ->whereParentId($parent->{$parent->getKeyName()});
    }
    public function scopeWhereUlidParent($query, $parent)
    {
        return $query->whereUlidParentType($parent->getMorphClass())
            ->whereUlidParentId($parent->{$parent->getKeyName()});
    }
    public function scopeWhereUuidParent($query, $parent)
    {
        return $query->whereUuidParentType($parent->getMorphClass())
            ->whereUuidParentId($parent->{$parent->getKeyName()});
    }
}
