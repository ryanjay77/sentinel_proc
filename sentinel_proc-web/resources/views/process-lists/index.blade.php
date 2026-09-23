@extends('layouts.app')

@section('title', 'SentinelProc - Whitelist / Blacklist')

@section('content')
    <div class="mb-6">
        <p class="text-sm uppercase tracking-[0.2em] text-cyan-400">Security Rules</p>
        <h1 class="mt-2 text-3xl font-bold">Whitelist / Blacklist</h1>
        <p class="mt-1 text-sm text-slate-400">
            Whitelist — trusted processes that skip risk scoring.<br>
            Blacklist — always-malicious processes that are forced to HIGH risk regardless of score.
        </p>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-300">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Add Rule Form --}}
    @if (auth()->user()->canManageUsers())
        <div class="mb-8 rounded-xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-4 text-lg font-semibold">Add New Rule</h2>
            <form method="POST" action="{{ route('process-lists.store') }}"
                class="grid grid-cols-1 gap-4 md:grid-cols-5">
                @csrf

                <div>
                    <label class="mb-1 block text-xs uppercase text-slate-400">Type</label>
                    <select name="type" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
                        <option value="whitelist" {{ old('type') === 'whitelist' ? 'selected' : '' }}>Whitelist (Trusted)</option>
                        <option value="blacklist" {{ old('type') === 'blacklist' ? 'selected' : '' }}>Blacklist (Malicious)</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs uppercase text-slate-400">Match By</label>
                    <select name="match_by" required
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
                        <option value="name" {{ old('match_by') === 'name' ? 'selected' : '' }}>Process Name</option>
                        <option value="hash" {{ old('match_by') === 'hash' ? 'selected' : '' }}>File Hash (SHA-256)</option>
                        <option value="path" {{ old('match_by') === 'path' ? 'selected' : '' }}>File Path</option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-xs uppercase text-slate-400">Value</label>
                    <input type="text" name="value" value="{{ old('value') }}"
                        placeholder="e.g. svchost.exe"
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
                </div>

                <div>
                    <label class="mb-1 block text-xs uppercase text-slate-400">Label (optional)</label>
                    <input type="text" name="process_name" value="{{ old('process_name') }}"
                        placeholder="e.g. Windows Host Process"
                        class="w-full rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-100 focus:border-cyan-500 focus:outline-none">
                </div>

                <div class="flex items-end">
                    <button type="submit"
                        class="w-full rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition">
                        Add Rule
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="grid grid-cols-2 gap-6">

        {{-- Whitelist --}}
        <div class="rounded-xl border border-emerald-500/20 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-emerald-300">
                    ✓ Whitelist
                    <span class="ml-2 rounded-full bg-emerald-500/20 px-2 py-0.5 text-xs text-emerald-400">{{ $whitelist->count() }}</span>
                </h2>
                <span class="text-xs text-slate-500">Trusted — risk scoring skipped</span>
            </div>

            @if ($whitelist->isEmpty())
                <p class="py-8 text-center text-sm text-slate-500">No whitelist rules yet.</p>
            @else
                <div class="space-y-2">
                    @foreach ($whitelist as $rule)
                        <div class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-800/50 px-3 py-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-emerald-500/10 px-1.5 py-0.5 text-xs text-emerald-400">{{ $rule->match_by }}</span>
                                    <span class="font-mono text-sm">{{ $rule->value }}</span>
                                </div>
                                @if ($rule->process_name)
                                    <div class="mt-0.5 text-xs text-slate-400">{{ $rule->process_name }}</div>
                                @endif
                                @if ($rule->reason)
                                    <div class="mt-0.5 text-xs text-slate-500 italic">{{ $rule->reason }}</div>
                                @endif
                                <div class="mt-0.5 text-xs text-slate-600">
                                    Added {{ $rule->created_at->diffForHumans() }}
                                    @if ($rule->addedBy) by {{ $rule->addedBy->name }} @endif
                                </div>
                            </div>
                            @if (auth()->user()->canManageUsers())
                                <form method="POST" action="{{ route('process-lists.destroy', $rule->id) }}"
                                    onsubmit="return confirm('Remove whitelist rule for {{ addslashes($rule->value) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition ml-3">Remove</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Blacklist --}}
        <div class="rounded-xl border border-red-500/20 bg-slate-900 p-5">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-red-300">
                    ✕ Blacklist
                    <span class="ml-2 rounded-full bg-red-500/20 px-2 py-0.5 text-xs text-red-400">{{ $blacklist->count() }}</span>
                </h2>
                <span class="text-xs text-slate-500">Malicious — forced to HIGH risk</span>
            </div>

            @if ($blacklist->isEmpty())
                <p class="py-8 text-center text-sm text-slate-500">No blacklist rules yet.</p>
            @else
                <div class="space-y-2">
                    @foreach ($blacklist as $rule)
                        <div class="flex items-center justify-between rounded-lg border border-slate-800 bg-slate-800/50 px-3 py-2">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="rounded bg-red-500/10 px-1.5 py-0.5 text-xs text-red-400">{{ $rule->match_by }}</span>
                                    <span class="font-mono text-sm">{{ $rule->value }}</span>
                                </div>
                                @if ($rule->process_name)
                                    <div class="mt-0.5 text-xs text-slate-400">{{ $rule->process_name }}</div>
                                @endif
                                @if ($rule->reason)
                                    <div class="mt-0.5 text-xs text-slate-500 italic">{{ $rule->reason }}</div>
                                @endif
                                <div class="mt-0.5 text-xs text-slate-600">
                                    Added {{ $rule->created_at->diffForHumans() }}
                                    @if ($rule->addedBy) by {{ $rule->addedBy->name }} @endif
                                </div>
                            </div>
                            @if (auth()->user()->canManageUsers())
                                <form method="POST" action="{{ route('process-lists.destroy', $rule->id) }}"
                                    onsubmit="return confirm('Remove blacklist rule for {{ addslashes($rule->value) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition ml-3">Remove</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    {{-- Common Windows processes quick-add --}}
    @if (auth()->user()->canManageUsers())
        <div class="mt-6 rounded-xl border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-3 text-sm font-semibold text-slate-400 uppercase tracking-wider">Quick-add Common Trusted Windows Processes</h2>
            <div class="flex flex-wrap gap-2">
                @foreach (['svchost.exe','explorer.exe','lsass.exe','csrss.exe','winlogon.exe','services.exe','smss.exe','Registry','System','taskhostw.exe'] as $proc)
                    <form method="POST" action="{{ route('process-lists.store') }}">
                        @csrf
                        <input type="hidden" name="type"     value="whitelist">
                        <input type="hidden" name="match_by" value="name">
                        <input type="hidden" name="value"    value="{{ strtolower($proc) }}">
                        <input type="hidden" name="process_name" value="Windows System Process">
                        <input type="hidden" name="reason"   value="Core Windows process — safe to trust">
                        <button type="submit"
                            class="rounded border border-slate-700 bg-slate-800 px-3 py-1 text-xs text-slate-300 hover:border-emerald-500/40 hover:text-emerald-300 transition">
                            + {{ $proc }}
                        </button>
                    </form>
                @endforeach
            </div>
        </div>
    @endif
@endsection
