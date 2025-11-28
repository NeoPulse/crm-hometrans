<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CaseFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

trait ManagesCaseAccess
{
    /**
     * Ensure that only administrators can perform write operations.
     */
    protected function assertAdmin(User $user): void
    {
        if ($user->role !== 'admin') {
            abort(403, 'Only administrators can modify the case.');
        }
    }

    /**
     * Verify that the given user can open or interact with the case.
     */
    protected function authorizeCaseAccess(CaseFile $caseFile, User $user): void
    {
        if ($user->role === 'admin') {
            return;
        }

        // Legal and client roles may only access in-progress cases where they participate.
        if ($caseFile->status !== 'progress') {
            abort(403, 'Only in-progress cases are available.');
        }

        $allowedIds = array_filter([
            $caseFile->sell_legal_id,
            $caseFile->buy_legal_id,
            $caseFile->sell_client_id,
            $caseFile->buy_client_id,
        ]);

        if (! in_array($user->id, $allowedIds, true)) {
            abort(403, 'You do not have access to this case.');
        }
    }

    /**
     * Append an entry to the activity log table.
     */
    protected function logAction(User $user, string $action, ?string $targetType, ?int $targetId, ?string $location, string $details): void
    {
        DB::table('activity_logs')->insert([
            'user_id' => $user->id,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'location' => $location,
            'details' => $details,
            'ip_address' => request()->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
