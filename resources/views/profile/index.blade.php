@extends('layouts.app')

{{-- Profile page allows administrators and legal users to update their avatar and password. --}}
@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8 col-xl-6">
            <!-- Card containing avatar and password update options for the current user. -->
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="fw-bold">Profile</div>
                    <span class="badge bg-primary text-uppercase">Secure Area</span>
                </div>
                <div class="card-body p-md-5">
                    {{-- Display confirmation banners after successful updates. --}}
                    @if(session('status_avatar'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status_avatar') }}
                        </div>
                    @endif
                    @if(session('status_password'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status_password') }}
                        </div>
                    @endif

                    <h3 class="py-2">Upload avatar</h3>

                    <!-- Avatar upload form with preview and helper text. -->
                    <form method="POST" action="{{ route('profile.avatar') }}" class="needs-validation pb-3" novalidate enctype="multipart/form-data">
                        @csrf
                        @php
                            $avatarPath = $user->avatar_path ? asset($user->avatar_path) : asset('images/avatar-placeholder.svg');
                        @endphp
                        <div class="d-flex align-items-center gap-3 mb-3 p-3 border rounded">
                            <img src="{{ $avatarPath . '?v=' . time() }}" alt="Profile avatar" class="rounded-circle avatar-50">
                            <div class="flex-grow-1">
                                <input type="file" name="avatar" id="avatar" class="form-control @error('avatar') is-invalid @enderror" accept="image/*" required>
                                @error('avatar')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="reset" class="btn btn-outline-secondary">Clear</button>
                            <button type="submit" class="btn btn-primary">Update Avatar</button>
                        </div>
                    </form>

                    <hr>

                    <!-- Password update form kept separate from avatar handling. -->
                    <h3 class="pt-4 pb-2">Change password</h3>
                    <form method="POST" action="{{ route('profile.password') }}" class="needs-validation" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" minlength="8" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @else
                                <div class="form-text">Enter a secure password with at least eight characters.</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" minlength="8" required>
                            <div class="form-text">Re-enter the password to confirm accuracy.</div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="reset" class="btn btn-outline-secondary">Clear</button>
                            <button type="submit" class="btn btn-primary">Update Password</button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
