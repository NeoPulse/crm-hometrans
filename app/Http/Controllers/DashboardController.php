<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user->role !== 'admin') {
            abort(403);
        }

        $clientRegistrations = User::where('role', 'client')
            ->where('created_at', '>=', Carbon::now()->subMonth())
            ->get()
            ->groupBy(fn ($u) => $u->created_at->format('d M'))
            ->map->count();

        $caseByMonth = CaseFile::where('created_at', '>=', Carbon::now()->startOfYear())
            ->get()
            ->groupBy(fn ($c) => $c->created_at->format('M'))
            ->map->count();

        $cases = CaseFile::orderBy('deadline')->paginate(20);

        return view('dashboard', [
            'clientRegistrations' => $clientRegistrations,
            'caseByMonth' => $caseByMonth,
            'cases' => $cases,
        ]);
    }
}
