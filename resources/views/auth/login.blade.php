<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light-style layout-menu-fixed" dir="ltr"
      data-theme="theme-default" data-assets-path="{{ asset('sneat/assets/') }}" data-template="vertical-menu-template-free">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="csrf-token" content="{{ csrf_token() }}"/>

    <title>Login - {{ config('app.name','SalesMP3') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('sneat/assets/img/favicon/favicon.ico') }}"/>

    <!-- Fonts & CSS -->
    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/fonts/boxicons.css') }}"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/core.css') }}"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/vendor/css/theme-default.css') }}"/>
    <link rel="stylesheet" href="{{ asset('sneat/assets/css/demo.css') }}"/>

    @php
        $appName  = config('app.name', 'SalesMP3');
        $appShort = 'MP3';
    @endphp

    <style>
        body {
            background: radial-gradient(circle at top center, #9bb5ff 0, #4f46e5 18%, #f5f7fb 55%);
            min-height: 100vh;
        }

        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .auth-inner {
            width: 100%;
            max-width: 980px;
        }

        .auth-card {
            width: 100%;
            border: 0;
            border-radius: 1.5rem;
            overflow: hidden;
        }

        .auth-hero {
            background: linear-gradient(160deg, #4f46e5, #6366f1);
            color: #fff;
        }

        .brand-text {
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            font-size: .8rem;
            opacity: .85;
        }

        .auth-title {
            font-weight: 700;
            font-size: 1.6rem;
        }

        .auth-subtitle {
            font-size: .9rem;
            opacity: .9;
        }

        .auth-illustration {
            max-width: 350px;
        }

        .form-label {
            font-size: .85rem;
            font-weight: 600;
        }

        .btn-auth {
            border-radius: 9999px;
            font-weight: 600;
            padding-block: .6rem;
        }

        .small-muted {
            font-size: .8rem;
            color: #9ca3af;
        }

        /* logo di atas form (kanan) */
        .form-logo-wrapper {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .form-logo-wrapper img {
            max-height: 80px;
            width: auto;
        }

        @media (max-width: 767.98px) {
            .auth-hero {
                text-align: center;
            }
            .auth-illustration {
                max-width: 250px;
            }
        }
    </style>
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-inner">

        {{-- Flash messages --}}
        @if(session('status'))
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card shadow-lg auth-card">
            <div class="row g-0">
                {{-- Left: hero MP3 ERP --}}
                <div class="col-md-5 auth-hero d-flex flex-column justify-content-start p-4">
                    <div>
                        <div class="brand-text mb-1">{{ $appShort }} ERP • MANDAU</div>
                        <div class="auth-title mb-2">Welcome back 👋</div>
                        <p class="auth-subtitle mb-3">
                            Kelola penjualan, stok, dan operasional cabang lebih rapi.  
                            Masuk untuk melanjutkan aktivitasmu di <strong>{{ $appName }}</strong>.
                        </p>

                        <img
                            src="{{ asset('sneat/assets/img/illustrations/man-with-laptop-light.png') }}"
                            alt="Sales dashboard illustration"
                            class="img-fluid auth-illustration mt-2"
                            onerror="this.style.display='none';"
                        >
                    </div>

                    <p class="small-muted mb-0 mt-4">
                        Tip: Kamu bisa login pakai <strong>name</strong> atau <strong>email</strong> yang sudah terdaftar.
                    </p>

                </div>

                {{-- Right: form + logo di atas --}}
                <div class="col-md-7">
                    <div class="card-body p-4 p-md-5">

                        {{-- LOGO MANDAU ATAS FORM --}}
                        <div class="form-logo-wrapper">
                            <img src="{{ asset('sneat/assets/img/mandau.png') }}" alt="Mandau Logo">
                        </div>

                        <h4 class="mb-1 fw-bold">Sign in</h4>
                        <p class="text-muted mb-4">
                            Masuk menggunakan <strong>name</strong> atau <strong>email</strong> dan password akunmu.
                        </p>

                        <form method="POST" action="{{ route('login.attempt') }}">
                            @csrf

                            {{-- Login (name/email) --}}
                            <div class="mb-3">
                                <label for="login" class="form-label">Name atau Email</label>
                                <input id="login" name="login" type="text"
                                       class="form-control form-control-lg @error('login') is-invalid @enderror"
                                       value="{{ old('login') }}" required autofocus
                                       placeholder="contoh: john atau john@example.com">
                                @error('login')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Password --}}
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input id="password" name="password" type="password"
                                       class="form-control form-control-lg @error('password') is-invalid @enderror"
                                       required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Remember --}}
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="form-check">
                                    <input type="checkbox" name="remember" id="remember"
                                           class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
                                    <label for="remember" class="form-check-label small">
                                        Remember me
                                    </label>
                                </div>
                            </div>

                            {{-- Global login error --}}
                            @if($errors->has('login'))
                                <div class="alert alert-danger py-2 small mb-3">
                                    {{ $errors->first('login') }}
                                </div>
                            @endif

                            <button type="submit" class="btn btn-primary w-100 btn-auth">
                                Login
                            </button>
                        </form>

                        <p class="text-center small-muted mt-4 mb-0">
                            © {{ date('Y') }} {{ $appName }}. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- JS -->
<script src="{{ asset('sneat/assets/vendor/libs/jquery/jquery.js') }}"></script>
<script src="{{ asset('sneat/assets/vendor/js/bootstrap.js') }}"></script>
</body>
</html>
