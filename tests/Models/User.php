<?php

namespace Dominservice\Conversations\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Mockery\MockInterface;

class User extends Authenticatable implements MockInterface
{
    use HasFactory;
    use \Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the name of the key for the model.
     *
     * @return string
     */
    public function getKeyName()
    {
        return 'id';
    }

    /**
     * Get the type of the key for the model.
     *
     * @return string
     */
    public function getKeyType()
    {
        return 'int';
    }
}
