@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-body">
                <h1 class="h5">Public access for case {{ $case->id }}</h1>
                <p class="text-muted">Please confirm the postal code to view this case.</p>
                <form method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Postal code</label>
                        <input type="text" name="postal_code" class="form-control" required>
                        @error('postal_code')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary">Continue</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
