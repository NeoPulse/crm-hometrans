<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogController extends Controller
{
    /**
     * Display the activity logs for administrators with filtering capabilities.
     */
    public function index(Request $request): Response
    {
        // Limit access strictly to administrators to protect audit information.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can view activity logs.');
        }

        // Capture filter inputs for search keywords and action types.
        $search = $request->input('search');
        $actionFilter = $request->input('action');

        // Build the log query with optional search and action filtering.
        $query = DB::table('activity_logs as logs')
            ->leftJoin('users as users', 'logs.user_id', '=', 'users.id')
            ->select('logs.*', 'users.name as user_name', 'users.role as user_role')
            ->orderByDesc('logs.created_at');

        // Apply search conditions when a keyword is provided by the administrator.
        if ($search) {
            $query->where(function ($innerQuery) use ($search) {
                $innerQuery->where('logs.details', 'like', "%{$search}%")
                    ->orWhere('logs.location', 'like', "%{$search}%")
                    ->orWhere('logs.target_type', 'like', "%{$search}%")
                    ->orWhere('users.name', 'like', "%{$search}%");
            });
        }

        // Narrow down the dataset by action type when requested.
        if ($actionFilter) {
            $query->where('logs.action', $actionFilter);
        }

        // Fetch the log entries with pagination and preserve filter query strings.
        $logs = $query->paginate(20)->withQueryString();

        // Retrieve distinct action types to populate the filter dropdown.
        $actions = DB::table('activity_logs')
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        // Render the logs index view with the paginated results and available filters.
        return response()->view('logs.index', [
            'logs' => $logs,
            'actions' => $actions,
            'search' => $search,
            'actionFilter' => $actionFilter,
        ]);
    }
}
