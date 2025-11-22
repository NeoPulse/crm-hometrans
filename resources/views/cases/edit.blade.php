@extends('layouts.app')

@section('content')
<div class="row g-3">
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5">Participants</h2>
                <form method="POST" action="{{ route('cases.update', $case) }}" class="row g-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label">Sell legal</label>
                        <select class="form-select" name="sell_legal_id">
                            <option value="">-- none --</option>
                            @foreach($users->where('role','legal') as $user)
                                <option value="{{ $user->id }}" @selected($case->sell_legal_id==$user->id)>{{ $user->id }} - {{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Sell client</label>
                        <select class="form-select" name="sell_client_id">
                            <option value="">-- none --</option>
                            @foreach($users->where('role','client') as $user)
                                <option value="{{ $user->id }}" @selected($case->sell_client_id==$user->id)>{{ $user->id }} - {{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Buy legal</label>
                        <select class="form-select" name="buy_legal_id">
                            <option value="">-- none --</option>
                            @foreach($users->where('role','legal') as $user)
                                <option value="{{ $user->id }}" @selected($case->buy_legal_id==$user->id)>{{ $user->id }} - {{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Buy client</label>
                        <select class="form-select" name="buy_client_id">
                            <option value="">-- none --</option>
                            @foreach($users->where('role','client') as $user)
                                <option value="{{ $user->id }}" @selected($case->buy_client_id==$user->id)>{{ $user->id }} - {{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary">Save</button>
                    </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-body">
                <h2 class="h5">Case details</h2>
                <div class="mb-3">
                    <label class="form-label">P/Code</label>
                    <input type="text" name="postal_code" class="form-control" value="{{ $case->postal_code }}" required>
                    @error('postal_code')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Property</label>
                    <input type="text" name="property" class="form-control" value="{{ $case->property }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select" required>
                        @foreach(['new','progress','completed','cancelled'] as $status)
                            <option value="{{ $status }}" @selected($case->status===$status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Deadline</label>
                    <input type="date" name="deadline" class="form-control" value="{{ optional($case->deadline)->format('Y-m-d') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Headline</label>
                    <input type="text" name="headline" class="form-control" value="{{ $case->headline }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3">{{ $case->notes }}</textarea>
                </div>
                <p class="small mb-1">Public link: {{ url('/case/'.$case->id.'?token='.$case->public_link) }}</p>
                <button class="btn btn-primary">Save</button>
                </form>
            </div>
        </div>
    </div>
</div>
<div class="card">
    <div class="card-body">
        <h2 class="h5">Stages</h2>
        <form method="POST" action="{{ route('cases.stages.store', $case) }}" class="row g-2 mb-3">
            @csrf
            <div class="col-md-10"><input class="form-control" name="name" placeholder="Stage name" required></div>
            <div class="col-md-2"><button class="btn btn-outline-primary w-100">Add stage</button></div>
        </form>
        @forelse($stages as $index => $stage)
            <div class="border rounded p-3 mb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <strong>{{ $index + 1 }}. {{ $stage->name }}</strong>
                    <span class="badge bg-secondary">Progress: {{ $stage->completedTaskRatio() }}%</span>
                </div>
                <div class="table-responsive mt-2">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Side</th>
                                <th>Status</th>
                                <th>Deadline</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stage->tasks as $i => $task)
                                <tr>
                                    <td>{{ $i+1 }}</td>
                                    <td>{{ $task->name }}</td>
                                    <td>{{ ucfirst($task->side) }}</td>
                                    <td>{{ ucfirst($task->status) }}</td>
                                    <td>{{ optional($task->deadline)->format('d/m/Y') ?: '00/00' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center">No tasks yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('cases.tasks.store', $case) }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="stage_id" value="{{ $stage->id }}">
                    <div class="col-md-4"><input class="form-control" name="name" placeholder="Task name" required></div>
                    <div class="col-md-2">
                        <select name="side" class="form-select">
                            <option value="seller">Seller</option>
                            <option value="buyer">Buyer</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="status" class="form-select">
                            <option value="new">New</option>
                            <option value="progress">Progress</option>
                            <option value="done">Done</option>
                        </select>
                    </div>
                    <div class="col-md-2"><input type="date" name="deadline" class="form-control"></div>
                    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Add task</button></div>
                </form>
            </div>
        @empty
            <p class="text-muted">No stages yet.</p>
        @endforelse
    </div>
</div>
@endsection
