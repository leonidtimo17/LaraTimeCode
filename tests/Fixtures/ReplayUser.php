<?php

declare(strict_types=1);

namespace LaraTimeCode\Tests\Fixtures;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

final class ReplayUser extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $table = 'replay_users';

    public $timestamps = false;

    protected $guarded = [];
}
