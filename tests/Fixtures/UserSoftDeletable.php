<?php

namespace Chargebee\Cashier\Tests\Fixtures;

use Chargebee\Cashier\Billable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class UserSoftDeletable extends User
{
    use Billable, Notifiable, SoftDeletes;
    protected $table = 'users';
}
