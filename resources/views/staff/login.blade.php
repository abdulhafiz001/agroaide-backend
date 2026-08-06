<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AgroAide Staff</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 text-stone-900">
<main class="mx-auto flex min-h-screen max-w-md items-center px-5">
    <form method="post" action="{{ route('staff.authenticate') }}" class="w-full rounded-2xl border border-stone-200 bg-white p-8 shadow-sm">
        @csrf
        <p class="text-sm font-semibold uppercase tracking-widest text-emerald-700">AgroAide</p>
        <h1 class="mt-2 text-2xl font-bold">Staff sign in</h1>
        <label class="mt-6 block text-sm font-medium">Email
            <input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
        </label>
        <label class="mt-4 block text-sm font-medium">Password
            <input name="password" type="password" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2">
        </label>
        @error('email')<p class="mt-3 text-sm text-red-700">{{ $message }}</p>@enderror
        <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button class="mt-6 w-full rounded-lg bg-emerald-700 px-4 py-2 font-semibold text-white hover:bg-emerald-800">Sign in</button>
    </form>
</main>
</body>
</html>
