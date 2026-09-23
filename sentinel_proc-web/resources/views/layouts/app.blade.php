<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'SentinelProc')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: #020817; }
    </style>
</head>
<body class="min-h-screen text-slate-100">
    <div class="flex min-h-screen">
        <aside class="w-64 border-r border-slate-800 bg-slate-900 p-5">
            <div class="mb-8">
                <div class="text-2xl font-bold text-cyan-400">SentinelProc</div>
                <div class="mt-2 text-xs uppercase tracking-[0.3em] text-slate-400">Threat Monitor</div>
            </div>

            <nav class="space-y-2 text-sm">
                <a href="{{ route('dashboard') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('dashboard', 'monitoring.index') ? 'border border-cyan-500/20 bg-cyan-500/10 text-cyan-300' : 'text-slate-300 hover:bg-slate-800' }}">Dashboard</a>

                @if (auth()->user()->isAnalyst() || auth()->user()->canManageUsers())
                    <div class="pt-2">
                        <p class="mb-1 px-3 text-xs uppercase tracking-[0.2em] text-slate-600">Monitoring</p>
                        <a href="{{ route('processes.index') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('processes.*') ? 'border border-cyan-500/20 bg-cyan-500/10 text-cyan-300' : 'text-slate-300 hover:bg-slate-800' }}">Process Inventory</a>
                        <a href="{{ route('activity.index') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('activity.index') ? 'border border-cyan-500/20 bg-cyan-500/10 text-cyan-300' : 'text-slate-300 hover:bg-slate-800' }}">Process Activity</a>
                        <a href="{{ route('risk-detections.index') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('risk-detections.*') ? 'border border-cyan-500/20 bg-cyan-500/10 text-cyan-300' : 'text-slate-300 hover:bg-slate-800' }}">Risk Detections</a>
                    </div>

                    <div class="pt-2">
                        <p class="mb-1 px-3 text-xs uppercase tracking-[0.2em] text-slate-600">Security</p>
                        <a href="{{ route('alerts.index') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('alerts.*') ? 'border border-cyan-500/20 bg-cyan-500/10 text-cyan-300' : 'text-slate-300 hover:bg-slate-800' }}">Alert Logs</a>
                        <a href="{{ route('virus-total.index') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('virus-total.*') ? 'border border-cyan-500/20 bg-cyan-500/10 text-cyan-300' : 'text-slate-300 hover:bg-slate-800' }}">VT Lookup Results</a>
                    </div>

                    <div class="pt-2">
                        <p class="mb-1 px-3 text-xs uppercase tracking-[0.2em] text-slate-600">Reports</p>
                        <a href="{{ route('reports.index') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('reports.*') ? 'border border-cyan-500/20 bg-cyan-500/10 text-cyan-300' : 'text-slate-300 hover:bg-slate-800' }}">Reports</a>
                    </div>
                @endif

                @if (auth()->user()->canManageUsers())
                    <div class="pt-2">
                        <p class="mb-1 px-3 text-xs uppercase tracking-[0.2em] text-slate-600">Administration</p>
                        <a href="{{ route('users.index') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('users.*') ? 'border border-red-500/20 bg-red-500/10 text-red-300' : 'text-slate-300 hover:bg-slate-800' }}">User Management</a>
                        <a href="{{ route('process-lists.index') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('process-lists.*') ? 'border border-red-500/20 bg-red-500/10 text-red-300' : 'text-slate-300 hover:bg-slate-800' }}">Whitelist / Blacklist</a>
                        <a href="{{ route('activity.system') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('activity.system') ? 'border border-red-500/20 bg-red-500/10 text-red-300' : 'text-slate-300 hover:bg-slate-800' }}">System Activity</a>
                        <a href="{{ route('settings.index') }}" class="block rounded-lg px-3 py-2 {{ request()->routeIs('settings.*') ? 'border border-red-500/20 bg-red-500/10 text-red-300' : 'text-slate-300 hover:bg-slate-800' }}">Configuration</a>
                    </div>
                @endif
            </nav>

            <div class="mt-10 rounded-lg border border-slate-700 bg-slate-800 p-3">
                <div class="text-xs uppercase text-slate-400">Signed in as</div>
                <div class="mt-2 text-sm font-medium">{{ auth()->user()->name }}</div>
                <div class="text-xs text-cyan-400">{{ auth()->user()->role->label() }}</div>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="text-xs text-slate-400 hover:text-slate-200">Sign out</button>
                </form>
            </div>
        </aside>

        <main class="flex-1 p-8">
            @yield('content')
        </main>
    </div>
    @stack('scripts')
</body>
</html>
