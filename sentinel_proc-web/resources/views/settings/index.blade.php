@extends('layouts.app')

@section('title', 'SentinelProc - System Configuration')

@section('content')
    <div class="mb-6">
        <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Administration</p>
        <h1 class="mt-2 text-3xl font-bold">System Configuration</h1>
        <p class="mt-1 text-sm text-slate-400">Current environment and integration status.</p>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-3 gap-6">

        {{-- Left: read-only config --}}
        <div class="col-span-2 space-y-6">

            {{-- Database --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Database</h2>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold
                        {{ $config['db_status'] === 'connected' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300' }}">
                        {{ $config['db_status'] === 'connected' ? '● Connected' : '● Error' }}
                    </span>
                </div>
                <dl class="divide-y divide-slate-800 text-sm">
                    <div class="flex justify-between py-3"><dt class="text-slate-400">Host</dt><dd class="font-mono">{{ $config['db_host'] }}:{{ $config['db_port'] }}</dd></div>
                    <div class="flex justify-between py-3"><dt class="text-slate-400">Database</dt><dd class="font-mono">{{ $config['db_name'] }}</dd></div>
                    <div class="flex justify-between py-3"><dt class="text-slate-400">Username</dt><dd class="font-mono">{{ $config['db_username'] }}</dd></div>
                    @if ($config['db_status'] !== 'connected')
                        <div class="py-3"><dt class="text-red-400 mb-1">Error</dt><dd class="text-xs text-red-300 break-all">{{ $config['db_status'] }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Application --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-4 text-lg font-semibold">Application</h2>
                <dl class="divide-y divide-slate-800 text-sm">
                    <div class="flex justify-between py-3"><dt class="text-slate-400">Environment</dt><dd class="font-mono">{{ $config['app_env'] }}</dd></div>
                    <div class="flex justify-between py-3"><dt class="text-slate-400">Debug Mode</dt>
                        <dd>
                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $config['app_debug'] ? 'bg-amber-500/20 text-amber-300' : 'bg-emerald-500/20 text-emerald-300' }}">
                                {{ $config['app_debug'] ? 'ON' : 'OFF' }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between py-3"><dt class="text-slate-400">App URL</dt><dd class="font-mono">{{ $config['app_url'] }}</dd></div>
                </dl>
            </div>

            {{-- Python agent --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-semibold">Monitoring Agent</h2>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold
                        {{ $config['agent_found'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300' }}">
                        {{ $config['agent_found'] ? '● Found' : '● Not Found' }}
                    </span>
                </div>
                <dl class="divide-y divide-slate-800 text-sm">
                    <div class="py-3">
                        <dt class="text-slate-400 mb-1">Script Path</dt>
                        <dd class="break-all font-mono text-xs text-slate-300">{{ $config['agent_path'] }}</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Right: editable settings --}}
        <div class="space-y-6">

            {{-- VT Key --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-1 text-lg font-semibold">VirusTotal</h2>
                <p class="mb-4 text-sm text-slate-400">Used by the monitoring agent to check file hashes against 70+ AV engines.</p>

                <div class="mb-4 flex items-center gap-2">
                    <span class="rounded-full px-2 py-1 text-xs font-semibold
                        {{ $config['vt_key_set'] ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-600/40 text-slate-400' }}">
                        {{ $config['vt_key_set'] ? '● Key configured' : '● No key set' }}
                    </span>
                </div>

                @if ($errors->has('vt_api_key'))
                    <p class="mb-3 text-sm text-red-400">{{ $errors->first('vt_api_key') }}</p>
                @endif

                <form method="POST" action="{{ route('settings.vt-key') }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="vt_api_key" class="mb-1 block text-sm text-slate-400">
                            API Key <span class="text-slate-500">(leave blank to clear)</span>
                        </label>
                        <input id="vt_api_key" name="vt_api_key" type="password"
                            placeholder="{{ $config['vt_key_set'] ? '••••••••••••••••' : 'Paste your VT API key' }}"
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <button type="submit"
                        class="w-full rounded-lg bg-cyan-500 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition">
                        Save Key
                    </button>
                </form>

                <p class="mt-3 text-xs text-slate-500">
                    Get a free key at <a href="https://www.virustotal.com" target="_blank" class="text-cyan-400 hover:underline">virustotal.com</a>
                    (free tier: 4 requests/min).
                </p>
            </div>

            {{-- Email Alerts --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-1 text-lg font-semibold">Email Alerts</h2>
                <p class="mb-4 text-sm text-slate-400">
                    Sends an email when HIGH risk processes are detected. Leave blank to notify all Admin accounts.
                </p>

                <dl class="divide-y divide-slate-800 text-sm mb-4">
                    <div class="flex justify-between py-3">
                        <dt class="text-slate-400">Mail Driver</dt>
                        <dd class="font-mono">{{ $config['mail_mailer'] }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-slate-400">Mail Host</dt>
                        <dd class="font-mono">{{ $config['mail_host'] }}:{{ $config['mail_port'] }}</dd>
                    </div>
                    <div class="flex justify-between py-3">
                        <dt class="text-slate-400">Current Recipient</dt>
                        <dd class="font-mono text-xs">{{ $config['alert_email'] ?: 'All Admin accounts' }}</dd>
                    </div>
                </dl>

                @if ($errors->has('alert_email'))
                    <p class="mb-3 text-sm text-red-400">{{ $errors->first('alert_email') }}</p>
                @endif

                <form method="POST" action="{{ route('settings.alert-email') }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label for="alert_email" class="mb-1 block text-sm text-slate-400">
                            Alert Recipient <span class="text-slate-500">(leave blank for all admins)</span>
                        </label>
                        <input id="alert_email" name="alert_email" type="email"
                            value="{{ old('alert_email', $config['alert_email']) }}"
                            placeholder="alerts@yourcompany.com"
                            class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
                    </div>
                    <button type="submit"
                        class="w-full rounded-lg bg-cyan-500 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition">
                        Save Email
                    </button>
                </form>

                <div class="mt-4 rounded-lg bg-slate-800 p-3 text-xs text-slate-400 space-y-1">
                    <p class="font-semibold text-slate-300">To send real emails, update .env:</p>
                    <p><code>MAIL_MAILER=smtp</code></p>
                    <p><code>MAIL_HOST=smtp.gmail.com</code></p>
                    <p><code>MAIL_PORT=587</code></p>
                    <p><code>MAIL_USERNAME=you@gmail.com</code></p>
                    <p><code>MAIL_PASSWORD=your-app-password</code></p>
                    <p class="mt-1 text-slate-500">Until SMTP is set, emails are written to <code>storage/logs/laravel.log</code>.</p>
                </div>
            </div>

            {{-- Scheduler --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 p-5">
                <h2 class="mb-1 text-lg font-semibold">Auto-Scan Schedule</h2>
                <p class="mb-4 text-sm text-slate-400">
                    Runs the monitoring agent automatically every
                    <span class="font-semibold text-cyan-400">{{ env('SCAN_INTERVAL_MINUTES', 5) }} minutes</span>.
                </p>

                <dl class="divide-y divide-slate-800 text-sm mb-4">
                    <div class="flex justify-between py-3">
                        <dt class="text-slate-400">Interval</dt>
                        <dd class="font-mono">{{ env('SCAN_INTERVAL_MINUTES', 5) }} min</dd>
                    </div>
                    <div class="py-3">
                        <dt class="text-slate-400 mb-1">Scan Log</dt>
                        <dd class="break-all font-mono text-xs text-slate-300">storage/logs/sentinel-scan.log</dd>
                    </div>
                </dl>

                <div class="space-y-2">
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">To activate auto-scan:</p>

                    <div class="rounded-lg bg-slate-800 p-3">
                        <p class="mb-1 text-xs text-slate-400">Option 1 — Run in terminal (stays running while open):</p>
                        <code class="text-xs text-cyan-300">php artisan schedule:work</code>
                    </div>

                    <div class="rounded-lg bg-slate-800 p-3">
                        <p class="mb-1 text-xs text-slate-400">Option 2 — Register with Windows Task Scheduler (runs permanently):</p>
                        <code class="text-xs text-cyan-300">php artisan sentinel:schedule-setup</code>
                        <p class="mt-1 text-xs text-slate-500">Run as Administrator in your terminal.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
