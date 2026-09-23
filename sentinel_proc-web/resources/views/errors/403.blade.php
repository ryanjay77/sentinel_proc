<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SentinelProc - Access Denied</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background: #020817; }</style>
</head>
<body class="min-h-screen text-slate-100 flex items-center justify-center">
    <div class="text-center px-6">
        <div class="mb-6 inline-flex items-center justify-center w-20 h-20 rounded-full border border-red-500/30 bg-red-500/10">
            <svg class="w-10 h-10 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        </div>

        <p class="text-xs uppercase tracking-[0.3em] text-red-400 mb-2">403 — Access Denied</p>
        <h1 class="text-4xl font-bold mb-3">Permission Required</h1>
        <p class="text-slate-400 max-w-sm mx-auto text-sm leading-relaxed">
            {{ $exception->getMessage() ?: 'You do not have permission to access this resource.' }}
        </p>

        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="{{ url()->previous() !== url()->current() ? url()->previous() : route('dashboard') }}"
                class="rounded-lg border border-slate-700 px-5 py-2 text-sm text-slate-300 hover:bg-slate-800 transition">
                &larr; Go Back
            </a>
            <a href="{{ route('dashboard') }}"
                class="rounded-lg bg-cyan-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition">
                Dashboard
            </a>
        </div>
    </div>
</body>
</html>
