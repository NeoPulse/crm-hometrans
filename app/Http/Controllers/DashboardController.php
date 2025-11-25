<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends Controller
{
    /**
     * Display the administrative dashboard with charts and active cases.
     */
    public function index(Request $request): Response
    {
        // Restrict access strictly to administrators to protect sensitive analytics.
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can access the dashboard.');
        }

        // Log the dashboard view to provide visibility into administrative activity.
        DB::table('activity_logs')->insert([
            'user_id' => $request->user()->id,
            'action' => 'view',
            'target_type' => 'dashboard',
            'target_id' => null,
            'location' => 'dashboard',
            'details' => 'Viewed the admin dashboard with metrics.',
            'ip_address' => $request->ip(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Prepare the date range for client registrations (last 30 days inclusive).
        $today = Carbon::now()->startOfDay();
        $clientsStartDate = $today->copy()->subDays(29);

        // Aggregate client registrations grouped by date to feed the column chart.
        $clientRegistrationRows = DB::table('users')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
            ->where('role', 'client')
            ->whereDate('created_at', '>=', $clientsStartDate)
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Build ordered labels and values ensuring missing days are represented with zero.
        $clientLabels = [];
        $clientValues = [];
        for ($cursor = $clientsStartDate->copy(); $cursor->lte($today); $cursor->addDay()) {
            $dateKey = $cursor->toDateString();
            $clientLabels[] = $cursor->format('d M');
            $clientValues[] = (int) ($clientRegistrationRows[$dateKey]->total ?? 0);
        }

        // Calculate case creations per month for the current year to populate the annual chart.
        $yearStart = Carbon::now()->startOfYear();
        $caseLabels = [];
        $caseValues = [];
        $caseQueryFormatter = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        // Aggregate case creations per month using the correct SQL function per driver.
        $caseCreationRows = DB::table('cases')
            ->selectRaw("{$caseQueryFormatter} as month_key, COUNT(*) as total")
            ->whereDate('created_at', '>=', $yearStart)
            ->groupBy('month_key')
            ->orderBy('month_key')
            ->get()
            ->keyBy('month_key');

        // Generate labels and totals for each calendar month in the year for consistent charting.
        for ($i = 0; $i < 12; $i++) {
            $monthPoint = $yearStart->copy()->addMonths($i);
            $monthKey = $monthPoint->format('Y-m');
            $caseLabels[] = $monthPoint->format('M');
            $caseValues[] = (int) ($caseCreationRows[$monthKey]->total ?? 0);
        }

        // Retrieve in-progress cases ordered by their deadline for quick access.
        $progressCases = CaseFile::query()
            ->with('attentions')
            ->where('status', 'progress')
            ->orderByRaw('CASE WHEN deadline IS NULL THEN 1 ELSE 0 END')
            ->orderBy('deadline')
            ->paginate(20);

        // Render the dashboard view with chart datasets and active cases.
        return response()->view('dashboard.index', [
            'clientLabels' => $clientLabels,
            'clientValues' => $clientValues,
            'caseLabels' => $caseLabels,
            'caseValues' => $caseValues,
            'progressCases' => $progressCases,
        ]);
    }
}
