@extends('layouts.staff')

@section('title', 'My Profile')

@section('content')

{{-- Header --}}
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">My Profile &amp; Account Settings</h1>
    <p class="mt-0.5 text-sm text-slate-500">Manage your administrative credentials, personal details, and account security.</p>
</div>

{{-- Top Profile Banner --}}
<div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-xl font-bold text-emerald-800 uppercase shadow-sm">
                {{ substr($user->name, 0, 1) }}
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-bold text-slate-900">{{ $user->name }}</h2>
                    @if($user->role === 'admin')
                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">Administrator</span>
                    @else
                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">Agronomist</span>
                    @endif
                </div>
                <p class="text-sm text-slate-500">{{ $user->email }} · Staff member since {{ $user->created_at?->format('M Y') }}</p>
            </div>
        </div>
        <div class="text-xs text-slate-400">
            Session authenticated · IP protected
        </div>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-2">

    {{-- ── Card 1: Personal Details ── --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="font-bold text-slate-900">Profile Information</h2>
            <p class="mt-0.5 text-xs text-slate-500">Update your name, contact email, and phone number.</p>
        </div>

        <form method="post" action="{{ route('staff.profile.update') }}" class="p-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Full Name</label>
                <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="email" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Email Address</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="phone_number" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Phone Number</label>
                <input id="phone_number" name="phone_number" type="tel" value="{{ old('phone_number', $user->phone_number) }}"
                       placeholder="+234..."
                       class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                @error('phone_number')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Current Role</label>
                <input value="{{ ucfirst($user->role) }}" readonly
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2 text-sm text-slate-500 cursor-not-allowed">
                <p class="mt-1 text-xs text-slate-400">Role changes are managed via user administration.</p>
            </div>

            <div class="pt-2">
                <button type="submit"
                        class="rounded-lg bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Save Profile Changes
                </button>
            </div>
        </form>
    </div>

    {{-- ── Card 2: Change Password ── --}}
    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="font-bold text-slate-900">Change Password</h2>
            <p class="mt-0.5 text-xs text-slate-500">Ensure your administrative account remains protected with a strong passphrase.</p>
        </div>

        <form method="post" action="{{ route('staff.profile.password') }}" class="p-6 space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label for="current_password" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Current Password</label>
                <div class="relative">
                    <input id="current_password" name="current_password" type="password" required
                           class="w-full rounded-lg border border-slate-300 py-2 pl-3.5 pr-10 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <button type="button" onclick="togglePass('current_password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                @error('current_password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">New Password</label>
                <div class="relative">
                    <input id="password" name="password" type="password" required minlength="12"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-3.5 pr-10 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <button type="button" onclick="togglePass('password', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
                <p class="mt-1 text-xs text-slate-400">Must be at least 12 characters and contain both letters and numbers.</p>
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wide text-slate-500 mb-1.5">Confirm New Password</label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12"
                           class="w-full rounded-lg border border-slate-300 py-2 pl-3.5 pr-10 text-sm text-slate-900 focus:border-emerald-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/20">
                    <button type="button" onclick="togglePass('password_confirmation', this)" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </button>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit"
                        class="rounded-lg bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2">
                    Update Password
                </button>
            </div>
        </form>
    </div>

</div>

<script>
function togglePass(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>

@endsection
