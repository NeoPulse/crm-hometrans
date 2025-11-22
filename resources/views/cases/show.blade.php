@extends('layouts.app')

@section('content')
<h1 class="h4 mb-3">Case {{ $case->postal_code }} ({{ ucfirst($case->status) }})</h1>
<div class="row">
    <div class="col-lg-4 mb-3">
        <h2 class="h6">Stages</h2>
        @forelse($stages as $index => $stage)
            <div class="border rounded p-2 mb-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span>{{ $index+1 }}. {{ $stage->name }}</span>
                    <span class="badge bg-secondary">{{ $stage->completedTaskRatio() }}%</span>
                </div>
            </div>
        @empty
            <p class="text-muted">Stages not added yet.</p>
        @endforelse
    </div>
    <div class="col-lg-8 mb-3">
        @forelse($stages as $index => $stage)
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="h6 mb-3">{{ $index+1 }}. {{ $stage->name }}</h2>
                    <div class="row">
                        <div class="col-md-6">
                            <h3 class="h6">Seller side</h3>
                            @php $sellerTasks = $stage->tasks->where('side','seller'); @endphp
                            @forelse($sellerTasks as $i => $task)
                                <div class="border rounded p-2 mb-2 {{ $task->status === 'done' ? 'bg-success-subtle' : ($task->status==='progress' ? 'bg-warning-subtle' : 'bg-light') }}">
                                    <div class="d-flex justify-content-between">
                                        <span>{{ $i+1 }}. {{ $task->name }}</span>
                                        <span class="badge bg-secondary">{{ optional($task->deadline)->format('d/m') ?: '00/00' }}</span>
                                    </div>
                                    <small>Status: {{ ucfirst($task->status) }}</small>
                                </div>
                            @empty
                                <p class="text-muted">No seller tasks.</p>
                            @endforelse
                        </div>
                        <div class="col-md-6">
                            <h3 class="h6">Buyer side</h3>
                            @php $buyerTasks = $stage->tasks->where('side','buyer'); @endphp
                            @forelse($buyerTasks as $i => $task)
                                <div class="border rounded p-2 mb-2 {{ $task->status === 'done' ? 'bg-success-subtle' : ($task->status==='progress' ? 'bg-warning-subtle' : 'bg-light') }}">
                                    <div class="d-flex justify-content-between">
                                        <span>{{ $i+1 }}. {{ $task->name }}</span>
                                        <span class="badge bg-secondary">{{ optional($task->deadline)->format('d/m') ?: '00/00' }}</span>
                                    </div>
                                    <small>Status: {{ ucfirst($task->status) }}</small>
                                </div>
                            @empty
                                <p class="text-muted">No buyer tasks.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <p class="text-muted">No tasks available.</p>
        @endforelse
    </div>
</div>
@endsection
