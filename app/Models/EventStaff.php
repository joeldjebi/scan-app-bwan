<?php

namespace App\Models;

use App\Enums\StaffRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EventStaff extends Pivot
{
    protected $table = 'event_staff';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'role' => StaffRole::class,
        ];
    }
}
