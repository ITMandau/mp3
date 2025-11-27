{{-- resources/views/layouts/partials/navbar.blade.php --}}
@php
    $user      = auth()->user();
    $userName  = $user?->name ?? 'Guest';
    // kalau pakai Spatie Roles
    $userRole  = $user?->roles?->first()?->name ?? '-';
@endphp

<nav class="layout-navbar container-xxl navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme">
  {{-- Toggle menu (mobile) --}}
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="bx bx-menu bx-sm"></i>
    </a>
  </div>

  <div class="navbar-nav-right d-flex align-items-center w-100" id="navbar-collapse">

    {{-- GLOBAL SEARCH --}}
    <div class="navbar-nav align-items-center flex-grow-1">
      <div class="nav-item d-flex align-items-center w-100" style="max-width: 420px;">
        <span class="d-inline-flex align-items-center justify-content-center me-2">
          <i class="bx bx-search fs-4 lh-1 text-muted"></i>
        </span>
        <input
          id="globalSearch"
          type="text"
          class="form-control border-0 shadow-none bg-transparent"
          placeholder="Search..."
          aria-label="Search"
          autocomplete="off"
        >
      </div>
    </div>

    {{-- USER DROPDOWN --}}
    <ul class="navbar-nav flex-row align-items-center ms-auto">
      <li class="nav-item dropdown dropdown-user">
        <a class="nav-link dropdown-toggle hide-arrow" href="#" data-bs-toggle="dropdown">
          <div class="avatar avatar-online">
            <img src="{{ asset('sneat/assets/img/avatars/1.png') }}"
                 class="w-px-40 h-auto rounded-circle"
                 alt="User avatar">
          </div>
        </a>

        <ul class="dropdown-menu dropdown-menu-end">
          <li class="px-3 py-2">
            <div class="d-flex align-items-center">
              <div class="avatar me-3">
                <img src="{{ asset('sneat/assets/img/avatars/1.png') }}"
                     class="w-px-40 h-auto rounded-circle"
                     alt="User avatar">
              </div>
              <div class="d-flex flex-column">
                <span class="fw-semibold">{{ $userName }}</span>
                <small class="text-muted">{{ $userRole }}</small>
              </div>
            </div>
          </li>

          <li><div class="dropdown-divider"></div></li>

          <li>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="dropdown-item">
                <i class="bx bx-power-off me-2"></i>
                <span>Log Out</span>
              </button>
            </form>
          </li>
        </ul>
      </li>
    </ul>
  </div>
</nav>
