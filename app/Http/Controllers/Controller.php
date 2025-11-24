<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    /**
     * Persist a short activity trail for auditing.
     */
    protected function logActivity(string $action, ?Model $target = null, ?string $description = null): void
    {
        ActivityLog::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target?->getKey(),
            'description' => $description,
        ]);
    }
}
