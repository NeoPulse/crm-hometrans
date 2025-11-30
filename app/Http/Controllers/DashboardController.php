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
        $monthExpression = $this->monthGroupExpression();
        $caseCreationRows = DB::table('cases')
            ->selectRaw("{$monthExpression} as month_key, COUNT(*) as total")
            ->whereDate('created_at', '>=', $yearStart)
            ->groupByRaw($monthExpression)
            ->orderBy('month_key')
            ->get()
            ->keyBy('month_key');

        // Assemble month labels and totals ensuring each month up to the current month is represented.
        $caseLabels = [];
        $caseValues = [];
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

    /**
     * Resolve the database-specific expression for grouping by month.
     */
    private function monthGroupExpression(): string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return "strftime('%Y-%m', created_at)";
        }

        if ($driver === 'pgsql') {
            return "to_char(created_at, 'YYYY-MM')";
        }

        return "DATE_FORMAT(created_at, '%Y-%m')";
    }
}
