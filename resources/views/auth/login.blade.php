@extends('layouts.app')

@section('content')
    <div class="login row justify-content-around align-items-center">
        <div class="login__left col-lg-6">
            <div class="login-box">
                <div class="row justify-content-around">
                    <div class="col-10 col-sm-8">
                        <h2 class="login__title">Log in</h2>
                        <p class="login-box-msg"></p>
                        @if ($errors->any())
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                {{ $errors->first() }}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        @endif
                        @if (session('status'))
                            <div class="alert alert-primary" role="alert">{{ session('status') }}</div>
                        @endif

                        <form action="{{ route('login') }}" method="post">
                            {{ csrf_field() }}
                            <div class="input-group mb-3">
                                <input type="text" name="email" class="form-control {{ $errors->has('email') ? ' is-invalid' : '' }}" placeholder="{{ __('Email') }}" value="{{ old('email') }}" required autofocus>
                                <div class="input-group-append">
                                    <div class="input-group-text py-2">
                                        <i class="bi bi-envelope-fill login__envelope"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="input-group mb-3">
                                <input type="password" id="password-input1" name="password" class="form-control {{ $errors->has('password') ? ' is-invalid' : '' }}" placeholder="{{ __('Password') }}" required>
                                <div class="input-group-append">
                                    <div class="input-group-text py-2">
                                        <a href="#" class="pwdControl ctrl1"></a>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-warning btn-block text-bold w-100">Log in</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-10 col-lg-4 mt-5 mt-lg-0">
            <h2>Register your account</h2>
            <p class="d-none d-md-block registration__text">
                The registration is commitment-free and only takes 1 minute.<br>
                Please fill in the form to register.
            </p>
            <p class="d-block d-md-none registration__text fs-18 mb-4">
                It is commitment-free and only takes 1 minute.
            </p>
            <a class="btn registration__btn" href="https://hometrans.uk/?register=1" target="_blank">Register</a>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mt-2 mt-lg-5 text-center mb-4">
            <a href="https://hometrans.uk/" target="_blank">hometrans.uk</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const ctrl = document.querySelector('.ctrl1');
            const input = document.getElementById('password-input1');
            ctrl.addEventListener('click', (e) => {
                e.preventDefault();
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                ctrl.classList.toggle('view', isPassword);
            });
        });
    </script>

@endsection
