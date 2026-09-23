<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SentinelProc — High Risk Alert</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; margin: 0; padding: 32px 16px; }
        .wrapper { max-width: 600px; margin: 0 auto; }
        .card { background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: #0f172a; padding: 28px 32px; }
        .header-brand { color: #22d3ee; font-size: 20px; font-weight: 700; letter-spacing: -0.5px; }
        .header-sub { color: #94a3b8; font-size: 12px; margin-top: 2px; text-transform: uppercase; letter-spacing: 2px; }
        .alert-banner { background: #fef2f2; border-left: 4px solid #ef4444; padding: 16px 32px; }
        .alert-banner h1 { color: #dc2626; font-size: 18px; margin: 0 0 4px 0; }
        .alert-banner p { color: #6b7280; font-size: 14px; margin: 0; }
        .body { padding: 28px 32px; }
        .section-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1.5px; color: #94a3b8; margin-bottom: 12px; }
        .process-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 16px; margin-bottom: 10px; }
        .process-card .name { font-family: monospace; font-size: 15px; font-weight: 600; color: #0f172a; }
        .process-card .pid { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-high { background: #fef2f2; color: #dc2626; }
        .badge-medium { background: #fffbeb; color: #d97706; }
        .reasons { margin-top: 8px; }
        .reason-tag { display: inline-block; background: #f1f5f9; color: #475569; font-size: 11px; padding: 2px 8px; border-radius: 4px; margin: 2px 2px 0 0; }
        .path { font-family: monospace; font-size: 11px; color: #64748b; margin-top: 6px; word-break: break-all; }
        .score-pill { float: right; background: #fef2f2; color: #dc2626; font-size: 13px; font-weight: 700; padding: 4px 10px; border-radius: 6px; }
        .stats { display: flex; gap: 16px; margin-bottom: 24px; }
        .stat-box { flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; text-align: center; }
        .stat-box .val { font-size: 22px; font-weight: 700; color: #0f172a; }
        .stat-box .lbl { font-size: 11px; color: #94a3b8; margin-top: 2px; }
        .stat-box.danger .val { color: #dc2626; }
        .footer { padding: 20px 32px; background: #f8fafc; border-top: 1px solid #e2e8f0; }
        .footer p { font-size: 12px; color: #94a3b8; margin: 0 0 8px 0; }
        .btn { display: inline-block; background: #0891b2; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-size: 14px; font-weight: 600; }
        .timestamp { font-size: 11px; color: #cbd5e1; margin-top: 12px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">

            {{-- Header --}}
            <div class="header">
                <div class="header-brand">SentinelProc</div>
                <div class="header-sub">Threat Monitor — Security Alert</div>
            </div>

            {{-- Alert banner --}}
            <div class="alert-banner">
                <h1>⚠ {{ count($alerts) }} High Risk Process{{ count($alerts) > 1 ? 'es' : '' }} Detected</h1>
                <p>Immediate review recommended. {{ $scanTotal }} processes were scanned in this run.</p>
            </div>

            <div class="body">

                {{-- Stats --}}
                <div class="stats">
                    <div class="stat-box danger">
                        <div class="val">{{ count($alerts) }}</div>
                        <div class="lbl">High Risk</div>
                    </div>
                    <div class="stat-box">
                        <div class="val">{{ $scanTotal }}</div>
                        <div class="lbl">Total Scanned</div>
                    </div>
                    <div class="stat-box">
                        <div class="val">{{ now()->format('H:i') }}</div>
                        <div class="lbl">Detected At</div>
                    </div>
                </div>

                {{-- Process list --}}
                <div class="section-label">Flagged Processes</div>

                @foreach ($alerts as $alert)
                    @php
                        $details = is_array($alert['details']) ? $alert['details'] : json_decode($alert['details'] ?? '{}', true);
                        $reasons = $details['reasons'] ?? [];
                        $score   = $details['score']   ?? '—';
                        $source  = $details['source']  ?? '';
                        $pid     = $details['pid']      ?? '—';
                    @endphp
                    <div class="process-card">
                        <span class="score-pill">Score: {{ $score }}</span>
                        <div class="name">{{ $alert['message'] ?? 'Unknown Process' }}</div>
                        <div class="pid">PID {{ $pid }}</div>

                        @if (!empty($reasons))
                            <div class="reasons">
                                @foreach ($reasons as $r)
                                    <span class="reason-tag">{{ $r }}</span>
                                @endforeach
                            </div>
                        @endif

                        @if ($source)
                            <div class="path">{{ $source }}</div>
                        @endif
                    </div>
                @endforeach

            </div>

            {{-- Footer --}}
            <div class="footer">
                <p>Log in to SentinelProc to acknowledge these alerts and take action.</p>
                <a href="{{ config('app.url') }}/alerts" class="btn">View Alerts →</a>
                <div class="timestamp">
                    Sent {{ now()->toDayDateTimeString() }} · SentinelProc automated alert
                </div>
            </div>

        </div>
    </div>
</body>
</html>
