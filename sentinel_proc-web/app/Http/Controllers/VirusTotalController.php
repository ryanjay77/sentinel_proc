<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class VirusTotalController extends Controller
{
    public function index()
    {
        $query = DB::table('processes_seen')->orderByDesc('created_at');

        if ($search = request('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('process_name', 'like', "%{$search}%")
                  ->orWhere('file_hash', 'like', "%{$search}%");
            });
        }
        if (request('flagged') === '1') {
            $query->where('vt_malicious_count', '>', 0);
        }
        if (request('checked') === '1') {
            $query->whereNotNull('vt_checked_at');
        }

        $records = $query->paginate(50)->withQueryString();

        $summary = [
            'total'   => DB::table('processes_seen')->count(),
            'checked' => DB::table('processes_seen')->whereNotNull('vt_checked_at')->count(),
            'flagged' => DB::table('processes_seen')->where('vt_malicious_count', '>', 0)->count(),
            'clean'   => DB::table('processes_seen')->whereNotNull('vt_checked_at')->where('vt_malicious_count', 0)->count(),
        ];

        return view('virus-total.index', compact('records', 'summary'));
    }
}
