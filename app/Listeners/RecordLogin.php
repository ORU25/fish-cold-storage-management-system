<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Login;

class RecordLogin
{
    public function handle(Login $event): void
    {
        ActivityLog::record('user.login', $event->user, user: $event->user);
    }
}
