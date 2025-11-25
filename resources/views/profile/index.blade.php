@extends('layouts.app')

{{-- Profile page allows administrators and legal users to update their avatar and password. --}}
@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Card containing avatar and password update options for the current user. -->
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
                    <p class="text-muted">Use the form below to upload a square profile photo and update your password. Passwords should be at least eight characters and match the confirmation field.</p>

                    <!-- Profile update form with avatar upload and password change controls. -->
                    <form method="POST" action="{{ route('profile.update') }}" class="needs-validation" novalidate enctype="multipart/form-data">
                        @csrf
                        <div class="row g-4 align-items-center">
                            <div class="col-12 col-md-4 text-center">
                                {{-- Current avatar preview with a fallback placeholder. --}}
                                @php
                                    $avatarPath = $user->avatar_path ? asset($user->avatar_path) : asset('images/avatar-placeholder.svg');
                                @endphp
                                <img src="{{ $avatarPath }}" alt="Profile avatar" class="rounded-circle avatar-50 mb-3">
                                <div class="mb-3">
                                    <label for="avatar" class="form-label">Upload avatar</label>
                                    <input type="file" name="avatar" id="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/*">
                                    @error('avatar')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <div class="form-text">Upload an image that will be cropped to a square JPEG.</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-12 col-md-8">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" minlength="8">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @else
                                        <div class="form-text">Enter a secure password with at least eight characters.</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" minlength="8">
                                    <div class="form-text">Re-enter the password to confirm accuracy.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-outline-secondary">Clear</button>
                            <button type="submit" class="btn btn-primary">Save Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
