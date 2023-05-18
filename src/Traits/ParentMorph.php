<?php

namespace Dominservice\Conversations\Traits;

trait ParentMorph
{
    public function scopeWhereParent($query, $parent)
    {
        $alias = $parent->getMorphClass();
//        $class = str_replace('\\', '\\\\', get_class($parent));
        return $query->whereParentType($alias)->whereParentId($parent->{$parent->getKeyName()});
    }
    public function scopeWhereUuidParent($query, $parent)
    {
        $alias = $parent->getMorphClass();
//        $class = str_replace('\\', '\\\\', get_class($parent));
        return $query->whereUuidParentType($alias)->whereUuidParentId($parent->{$parent->getKeyName()});
    }
}
