@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h1 class="h4 mb-3">Sign in</h1>
                <form method="POST" action="{{ url('/login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
                        @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                        @error('password')<div class="text-danger small">{{ $message }}</div>@enderror
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Login</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
