<?php

namespace App\Http\Controllers;

use App\Models\CaseFile;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CaseController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = CaseFile::query();
        if ($user->role === 'legal') {
            $query->where(function ($q) use ($user) {
                $q->where('sell_legal_id', $user->id)->orWhere('buy_legal_id', $user->id);
            })->where('status', 'progress');
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('postal_code', 'like', "%$search%")
                    ->orWhere('headline', 'like', "%$search%")
                    ->orWhere('notes', 'like', "%$search%");
            });
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        $cases = $query->orderBy('deadline')->paginate(20)->withQueryString();

        return view('cases.index', compact('cases'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'postal_code' => 'required',
        ]);

        $case = CaseFile::create([
            'postal_code' => $request->postal_code,
            'status' => 'new',
            'public_link' => Str::random(10),
        ]);

        $this->logActivity('case_created', $case, 'Case created');

        return redirect()->route('cases.edit', $case);
    }

    public function edit(CaseFile $case)
    {
        $this->authorizeAdmin();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $stages = $case->stages()->with('tasks')->orderBy('id')->get();
        $caseHeaderData = $this->buildCaseHeaderData($case);
        return view('cases.edit', compact('case', 'users', 'stages', 'caseHeaderData'));
    }

    public function update(Request $request, CaseFile $case)
    {
        $this->authorizeAdmin();
        $validated = $request->validate([
            'postal_code' => 'required',
            'status' => 'required',
            'deadline' => 'nullable|date',
        ]);

        $case->fill([
            'postal_code' => $validated['postal_code'],
            'status' => $validated['status'],
            'deadline' => $validated['deadline'] ?? null,
            'property' => $request->property,
            'headline' => $request->headline,
            'notes' => $request->notes,
            'sell_legal_id' => $request->sell_legal_id,
            'sell_client_id' => $request->sell_client_id,
            'buy_legal_id' => $request->buy_legal_id,
            'buy_client_id' => $request->buy_client_id,
        ])->save();

        $this->logActivity('case_updated', $case, 'Case data saved');

        return back()->with('status', 'Case saved');
    }

    public function addStage(Request $request, CaseFile $case)
    {
        $this->authorizeAdmin();
        $request->validate(['name' => 'required']);
        Stage::create([
            'case_id' => $case->id,
            'name' => $request->name,
        ]);

        $this->logActivity('stage_added', $case, 'Stage added to case');

        return back()->with('status', 'Stage added');
    }

    public function addTask(Request $request, CaseFile $case)
    {
        $this->authorizeAdmin();
        $request->validate([
            'stage_id' => 'required|exists:stages,id',
            'name' => 'required',
            'side' => 'required',
            'status' => 'required',
        ]);

        Task::create([
            'stage_id' => $request->stage_id,
            'name' => $request->name,
            'side' => $request->side,
            'status' => $request->status,
            'deadline' => $request->deadline,
        ]);

        $this->logActivity('task_created', $case, 'Task added to case');

        return back()->with('status', 'Task created');
    }

    public function publicShow(Request $request, CaseFile $case)
    {
        if ($case->status !== 'progress') {
            abort(404);
        }

        if ($request->query('token') !== $case->public_link) {
            abort(403);
        }

        if (! session()->get("case_public_{$case->id}")) {
            if ($request->isMethod('post')) {
                $request->validate(['postal_code' => 'required']);
                if (strcasecmp($request->postal_code, $case->postal_code) === 0) {
                    session(["case_public_{$case->id}" => true]);
                } else {
                    return back()->withErrors(['postal_code' => 'Postal code mismatch']);
                }
            } else {
                return view('cases.public', compact('case'));
            }
        }

        $stages = $case->stages()->with('tasks')->orderBy('id')->get();
        $caseHeaderData = $this->buildCaseHeaderData($case);
        return view('cases.show', compact('case', 'stages', 'caseHeaderData'));
    }

    protected function authorizeAdmin(): void
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }
    }

    /**
     * Prepare header metadata for case pages.
     */
    protected function buildCaseHeaderData(CaseFile $case): array
    {
        $case->loadMissing('sellLegal.legalProfile', 'buyLegal.legalProfile');
        $admin = User::where('role', 'admin')->orderBy('id')->first();

        return [
            'postal_code' => $case->postal_code,
            'deadline' => $case->deadline ? $case->deadline->format('d/M') : 'No deadline',
            'people' => [
                $this->mapPerson($admin, 'Project manager'),
                $this->mapPerson($case->sellLegal, "Seller's solicitor", true),
                $this->mapPerson($case->buyLegal, "Buyer's solicitor", true),
            ],
        ];
    }

    /**
     * Map person data into tooltip-friendly structure.
     */
    protected function mapPerson(?User $user, string $label, bool $includeOffice = false): array
    {
        $name = $user?->name ?? 'Not assigned';
        $office = $includeOffice ? $user?->legalProfile?->office : null;
        $email = $user?->email;
        $phone = $user?->phone;

        $tooltip = '<div><strong>' . e($name) . '</strong></div>';
        if ($office) {
            $tooltip .= '<div>Office: ' . e($office) . '</div>';
        }
        if ($phone) {
            $tooltip .= '<div><a class="text-white" href="tel:' . e($phone) . '">' . e($phone) . '</a></div>';
        }
        if ($email) {
            $tooltip .= '<div><a class="text-white" href="mailto:' . e($email) . '">' . e($email) . '</a></div>';
        }

        return [
            'label' => $label,
            'avatar' => $user?->avatar_url ?? asset('images/avatar-placeholder.svg'),
            'tooltip' => $tooltip,
        ];
    }
}
