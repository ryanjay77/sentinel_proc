<?php

namespace App\Http\Controllers;

use App\Models\ProcessList;
use App\Models\SystemAuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProcessListController extends Controller
{
    public function index()
    {
        $whitelist = ProcessList::where('type', 'whitelist')
            ->with('addedBy')->orderByDesc('created_at')->get();

        $blacklist = ProcessList::where('type', 'blacklist')
            ->with('addedBy')->orderByDesc('created_at')->get();

        return view('process-lists.index', compact('whitelist', 'blacklist'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'         => ['required', Rule::in(['whitelist', 'blacklist'])],
            'match_by'     => ['required', Rule::in(['name', 'hash', 'path'])],
            'value'        => ['required', 'string', 'max:512'],
            'process_name' => ['nullable', 'string', 'max:255'],
            'reason'       => ['nullable', 'string', 'max:500'],
        ]);

        // Normalise name/path to lowercase for consistent matching
        if (in_array($data['match_by'], ['name', 'path'])) {
            $data['value'] = strtolower($data['value']);
        }

        $existing = ProcessList::where('type', $data['type'])
            ->where('match_by', $data['match_by'])
            ->where('value', $data['value'])
            ->first();

        if ($existing) {
            return back()->withErrors(['value' => 'This rule already exists.'])->withInput();
        }

        $rule = ProcessList::create([
            'type'         => $data['type'],
            'match_by'     => $data['match_by'],
            'value'        => $data['value'],
            'process_name' => $data['process_name'] ?? null,
            'reason'       => $data['reason'] ?? null,
            'added_by'     => auth()->id(),
        ]);

        SystemAuditLog::record(
            'add_' . $data['type'] . '_rule',
            'ProcessList',
            $rule->id,
            $data['value']
        );

        return back()->with('success', ucfirst($data['type']) . ' rule added for "' . $data['value'] . '".');
    }

    public function destroy(int $id)
    {
        $rule = ProcessList::findOrFail($id);
        $label = $rule->value;
        $type  = $rule->type;
        $rule->delete();

        SystemAuditLog::record('remove_' . $type . '_rule', 'ProcessList', $id, $label);

        return back()->with('success', ucfirst($type) . ' rule for "' . $label . '" removed.');
    }
}
