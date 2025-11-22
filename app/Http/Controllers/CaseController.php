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

        return redirect()->route('cases.edit', $case);
    }

    public function edit(CaseFile $case)
    {
        $this->authorizeAdmin();
        $users = User::where('is_active', true)->orderBy('name')->get();
        $stages = $case->stages()->with('tasks')->orderBy('id')->get();
        return view('cases.edit', compact('case', 'users', 'stages'));
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
        return view('cases.show', compact('case', 'stages'));
    }

    protected function authorizeAdmin(): void
    {
        if (Auth::user()->role !== 'admin') {
            abort(403);
        }
    }
}
