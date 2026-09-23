<?php

namespace App\Http\Controllers;

use App\Models\ProcessRule;
use App\Models\SystemAuditLog;
use Illuminate\Http\Request;

class ProcessRuleController extends Controller
{
    public function index()
    {
        $whitelist = ProcessRule::where('type', 'whitelist')->latest()->get();
        $blacklist = ProcessRule::where('type', 'blacklist')->latest()->get();

        return view('process-rules.index', compact('whitelist', 'blacklist'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'process_name' => ['required', 'string', 'max:255'],
            'type'         => ['required', 'in:whitelist,blacklist'],
            'reason'       => ['nullable', 'string', 'max:500'],
        ]);

        $rule = ProcessRule::create([
            'process_name' => strtolower(trim($validated['process_name'])),
            'type'         => $validated['type'],
            'reason'       => $validated['reason'] ?? null,
            'created_by'   => auth()->user()->name ?? 'System',
        ]);

        SystemAuditLog::record(
            'add_' . $validated['type'] . '_rule',
            'ProcessRules',
            null,
            $rule->process_name
        );

        return back()->with('success', ucfirst($validated['type']) . ' rule added for process "' . $rule->process_name . '".');
    }

    public function destroy($id)
    {
        $rule = ProcessRule::findOrFail($id);
        $name = $rule->process_name;
        $type = $rule->type;

        $rule->delete();

        SystemAuditLog::record(
            'remove_' . $type . '_rule',
            'ProcessRules',
            null,
            $name
        );

        return back()->with('success', ucfirst($type) . ' rule removed for process "' . $name . '".');
    }
}
