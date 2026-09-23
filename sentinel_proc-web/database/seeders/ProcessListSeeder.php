<?php

namespace Database\Seeders;

use App\Models\ProcessList;
use Illuminate\Database\Seeder;

class ProcessListSeeder extends Seeder
{
    /**
     * Seed common trusted Windows system processes into the whitelist.
     * These are core OS processes that will always trigger false positives
     * without whitelisting (high CPU, unsigned, etc.).
     */
    public function run(): void
    {
        $trusted = [
            ['svchost.exe',    'Windows Service Host — core OS process'],
            ['explorer.exe',   'Windows Shell / Desktop'],
            ['lsass.exe',      'Local Security Authority Subsystem'],
            ['csrss.exe',      'Client/Server Runtime Subsystem'],
            ['winlogon.exe',   'Windows Logon Application'],
            ['services.exe',   'Windows Service Control Manager'],
            ['smss.exe',       'Session Manager Subsystem'],
            ['Registry',       'Windows Registry process'],
            ['System',         'Windows System process'],
            ['Idle',           'System Idle Process'],
            ['taskhostw.exe',  'Task Host Window'],
            ['dwm.exe',        'Desktop Window Manager'],
            ['wininit.exe',    'Windows Initialization'],
            ['spoolsv.exe',    'Print Spooler Service'],
            ['SearchIndexer.exe', 'Windows Search Indexer'],
        ];

        foreach ($trusted as [$name, $reason]) {
            ProcessList::updateOrCreate(
                [
                    'type'     => 'whitelist',
                    'match_by' => 'name',
                    'value'    => strtolower($name),
                ],
                [
                    'process_name' => 'Windows System Process',
                    'reason'       => $reason,
                    'added_by'     => null,
                ]
            );
        }

        $this->command->info('Whitelisted ' . count($trusted) . ' common Windows system processes.');
    }
}
