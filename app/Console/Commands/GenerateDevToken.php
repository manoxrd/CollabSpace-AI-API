<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class GenerateDevToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jwt:dev-token 
                            {--email=mohamed.salah@collabspace.ai : User email} 
                            {--years=100 : Number of years until token expires}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a long-lived JWT access token for API testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->option('email');
        $years = (int) $this->option('years');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email [{$email}] not found.");

            return Command::FAILURE;
        }

        // TTL in minutes (years * 365 days * 24 hours * 60 minutes)
        $ttlMinutes = $years * 365 * 24 * 60;

        $token = auth('api')->setTTL($ttlMinutes)->claims([
            'role' => $user->role->value,
            'email' => $user->email,
            'name' => $user->name,
        ])->fromUser($user);

        $expiresAt = now()->addYears($years)->toFormattedDateString();

        $this->info('====================================================');
        $this->info("JWT Token Generated Successfully (Valid for {$years} years until {$expiresAt})");
        $this->info('====================================================');
        $this->line("User:  {$user->name} ({$user->email})");
        $this->line("Role:  {$user->role->value}");
        $this->line('');
        $this->line($token);
        $this->line('');

        return Command::SUCCESS;
    }
}
