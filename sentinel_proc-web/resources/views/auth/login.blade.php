<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SentinelProc - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center">
    <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
        <div class="mb-8 text-center">
            <div class="text-3xl font-bold text-cyan-400">SentinelProc</div>
            <p class="mt-2 text-sm text-slate-400">Sign in with your assigned role</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm text-slate-400">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm text-slate-400">Password</label>
                <input id="password" name="password" type="password" required
                    class="w-full rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-slate-100 focus:border-cyan-500 focus:outline-none">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-400">
                <input type="checkbox" name="remember" class="rounded border-slate-600 bg-slate-800">
                Remember me
            </label>
            <button type="submit" class="w-full rounded-lg bg-cyan-500 py-2 font-semibold text-slate-950 hover:bg-cyan-400">
                Sign In
            </button>
        </form>

        <div class="mt-8 rounded-lg border border-slate-800 bg-slate-800/50 p-4 text-xs text-slate-400">
            <p class="font-semibold text-slate-300">Demo accounts</p>
            <p class="mt-2">admin@sentinel.local — Administrator</p>
            <p>analyst@sentinel.local — Security Analyst</p>
            <p>viewer@sentinel.local — Viewer</p>
            <p class="mt-2 text-slate-500">Password: <span class="text-slate-300">password</span></p>
        </div>
    </div>
</body>
</html>
