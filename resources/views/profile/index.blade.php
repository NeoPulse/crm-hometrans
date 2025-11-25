@extends('layouts.app')

{{-- Profile page allows administrators and legal users to update their own password. --}}
@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <!-- Card containing the password update form. -->
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="fw-bold">Profile</div>
                    <span class="badge bg-primary text-uppercase">Secure Area</span>
                </div>
                <div class="card-body">
                    {{-- Display a confirmation banner after a successful update. --}}
                    @if(session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{-- Provide guidance on the available profile action. --}}
                    <p class="text-muted">Use the form below to update your password. Passwords must be at least eight characters and match the confirmation field.</p>

                    <!-- Password update form restricted to the authenticated user. -->
                    <form method="POST" action="{{ route('profile.update') }}" class="needs-validation" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password<span class="text-danger">*</span></label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <div class="form-text">Enter a secure password with at least eight characters.</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm Password<span class="text-danger">*</span></label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required minlength="8">
                            <div class="form-text">Re-enter the password to confirm accuracy.</div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-outline-secondary">Clear</button>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
