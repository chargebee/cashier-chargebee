<?php

namespace Chargebee\CashierChargebee\Tests\Fixtures;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Chargebee\CashierChargebee\Billable;

class UserSoftDeletable extends User
{
    use Billable, Notifiable, SoftDeletes;
    protected $table = 'users';
}
