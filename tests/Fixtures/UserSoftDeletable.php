<?php

namespace Chargebee\CashierChargebee\Tests\Fixtures;

use Chargebee\CashierChargebee\Billable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class UserSoftDeletable extends User
{
    use Billable, Notifiable, SoftDeletes;
    protected $table = 'users';
}
