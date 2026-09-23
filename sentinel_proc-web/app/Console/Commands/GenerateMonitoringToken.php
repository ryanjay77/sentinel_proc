<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateMonitoringToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-monitoring-token {--user=monitoring} {--name=monitoring-agent}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate an API token for the monitoring agent';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = $this->option('user');
        $tokenName = $this->option('name');

        // Find or create a user for the monitoring agent
        $user = User::firstOrCreate(
            ['email' => "{$username}@sentinel-proc.local"],
            [
                'name' => 'Monitoring Agent',
                'password' => bcrypt(bin2hex(random_bytes(16))),
            ]
        );

        // Create a new API token scoped to snapshot ingestion only.
        $token = $user->createToken($tokenName, ['monitoring:write']);

        $this->info('');
        $this->info('=== Monitoring Agent API Token ===');
        $this->info('User: ' . $user->email);
        $this->info('Token Name: ' . $tokenName);
        $this->info('');
        $this->line('Token:');
        $this->line('<fg=green>' . $token->plainTextToken . '</>');
        $this->info('');
        $this->warn('Store this token securely. You will not be able to see it again.');
        $this->info('');
        $this->line('To use this token, add it to the Authorization header:');
        $this->line('Authorization: Bearer ' . $token->plainTextToken);
        $this->info('');

        return Command::SUCCESS;
    }
}
