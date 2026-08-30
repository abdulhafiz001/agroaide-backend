<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AgroAide Staff</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-stone-900">
<main class="mx-auto flex min-h-screen max-w-md items-center px-5 py-8">
    <form method="post" action="{{ route('staff.authenticate') }}" class="w-full rounded-2xl border border-stone-200 bg-white p-8 shadow-sm">
        @csrf
        <div class="flex items-center gap-2">
            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L4 5V11.09C4 16.14 7.41 20.85 12 22C16.59 20.85 20 16.14 20 11.09V5L12 2Z"/>
                </svg>
            </div>
            <p class="text-sm font-semibold uppercase tracking-widest text-emerald-700">AgroAide</p>
        </div>
        <h1 class="mt-3 text-2xl font-bold text-stone-900">Staff sign in</h1>
        <p class="mt-1 text-sm text-stone-500">Access the agronomist &amp; staff administration console.</p>

        <label class="mt-6 block text-sm font-medium text-stone-700">Email
            <input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-stone-900 placeholder-stone-400 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
        </label>

        <div class="mt-4">
            <label for="password-input" class="block text-sm font-medium text-stone-700">Password</label>
            <div class="relative mt-1">
                <input id="password-input" name="password" type="password" required class="w-full rounded-lg border border-stone-300 py-2 pl-3 pr-11 text-stone-900 placeholder-stone-400 focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600">
                <button type="button" id="toggle-password" aria-label="Toggle password visibility" class="absolute inset-y-0 right-0 flex items-center pr-3 text-stone-400 transition-colors hover:text-stone-700 focus:outline-none">
                    <!-- Eye Open SVG (shown when password is hidden) -->
                    <svg id="eye-open-icon" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <!-- Eye Slash SVG (shown when password is visible) -->
                    <svg id="eye-closed-icon" class="hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                        <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                        <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                        <line x1="2" y1="2" x2="22" y2="22"/>
                    </svg>
                </button>
            </div>
        </div>

        @error('email')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
        @error('password')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror

        <div class="mt-4 flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-stone-700 cursor-pointer">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-stone-300 text-emerald-600 focus:ring-emerald-500">
                <span>Remember me</span>
            </label>
        </div>

        <button type="submit" class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-2.5 font-semibold text-white shadow-sm transition-colors hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2">
            Sign in
        </button>

        @if ($needsSetup ?? false)
            <p class="mt-5 text-center text-sm text-stone-600">
                No staff yet —
                <a href="{{ route('staff.setup') }}" class="font-medium text-emerald-700 hover:underline">create the first admin</a>
            </p>
        @endif
    </form>
</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggleBtn = document.getElementById('toggle-password');
        const passwordInput = document.getElementById('password-input');
        const eyeOpen = document.getElementById('eye-open-icon');
        const eyeClosed = document.getElementById('eye-closed-icon');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', function () {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                if (eyeOpen && eyeClosed) {
                    eyeOpen.classList.toggle('hidden', isPassword);
                    eyeClosed.classList.toggle('hidden', !isPassword);
                }
            });
        }
    });
</script>
</body>
</html>
