<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMonitoringSnapshotRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Defense in depth: the route already requires auth:sanctum + the
        // monitoring:write token ability. Reject anything unauthenticated here too.
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'snapshot' => 'required|json',
            'scan_uuid' => ['nullable', 'uuid'],
            'hostname' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'snapshot_timestamp' => 'nullable|date_format:Y-m-d H:i:s',
            'process_count' => 'nullable|integer|min:0',
            'cpu_usage' => 'nullable|numeric|between:0,100',
            'memory_usage' => 'nullable|numeric|min:0',
            'disk_usage' => 'nullable|numeric|between:0,100',
            'status' => 'nullable|string|in:normal,warning,critical',

            // API-mode agents send the authoritative scored process list at
            // the top level; the snapshot JSON only carries a display summary.
            'processes' => ['nullable', 'array', 'max:1000'],
            'processes.*.pid' => ['nullable', 'integer', 'min:0'],
            'processes.*.name' => ['nullable', 'string', 'max:255'],
            'processes.*.path' => ['nullable', 'string', 'max:500'],
            'processes.*.cpu_percent' => ['nullable', 'numeric', 'min:0'],
            'processes.*.memory_mb' => ['nullable', 'numeric', 'min:0'],
            'processes.*.status' => ['nullable', 'string', 'max:50'],
            'processes.*.hash' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{64}$/'],
            'processes.*.first_seen' => ['nullable', 'boolean'],
            'processes.*.risk_level' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'processes.*.score' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'processes.*.reasons' => ['nullable', 'array', 'max:20'],
            'processes.*.reasons.*' => ['nullable', 'string', 'max:100'],
            'processes.*.virus_total_data' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            'snapshot.required' => 'The snapshot field is required.',
            'snapshot.json' => 'The snapshot field must be valid JSON.',
            'cpu_usage.between' => 'CPU usage must be between 0 and 100.',
            'disk_usage.between' => 'Disk usage must be between 0 and 100.',
            'status.in' => 'Status must be one of: normal, warning, critical.',
        ];
    }
}
