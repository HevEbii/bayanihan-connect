<?php

namespace App\Console\Commands;

use App\Models\Unit;
use App\Models\User;
use App\Support\Role;
use Database\Seeders\UnitSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Production setup: the standard units of measure and the first Administrator, without any demo data.
 * Safe to run on every deploy: it never overwrites an existing account.
 */
class InstallCommand extends Command
{
    protected $signature = 'bayanihan:install
                            {--name= : Administrator full name (default: ADMIN_NAME)}
                            {--email= : Administrator email (default: ADMIN_EMAIL)}
                            {--password= : Administrator password (default: ADMIN_PASSWORD)}';

    protected $description = 'Set up a live site: standard units of measure and the first Administrator (no demo accounts).';

    public function handle(): int
    {
        (new UnitSeeder)->run();
        $this->info('Units of measure ready ('.Unit::count().').');

        $email = $this->option('email') ?: config('bayanihan.admin.email');
        $password = $this->option('password') ?: config('bayanihan.admin.password');
        $name = $this->option('name') ?: config('bayanihan.admin.name');

        if (! $email && User::where('role', Role::ADMIN)->exists()) {
            $this->info('An administrator already exists. Nothing more to do.');

            return self::SUCCESS;
        }

        if ((! $email || ! $password) && $this->input->isInteractive()) {
            $email = $email ?: $this->ask('Administrator email');
            $password = $password ?: $this->secret('Administrator password (at least 8 characters)');
        }

        if (! $email || ! $password) {
            $this->error('No Administrator account exists yet. Set ADMIN_EMAIL and ADMIN_PASSWORD (and optionally ADMIN_NAME), or pass --email and --password.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => ['required', 'email', 'max:150'],
                'password' => ['required', 'not_in:password', Password::min(8)],
            ],
            ['password.not_in' => 'Choose a password other than "password"; it is the demo password.'],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->info("An account for {$email} already exists; it was left unchanged.");

            return self::SUCCESS;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => Role::ADMIN,
            'is_active' => true,
        ]);
        $this->info("Administrator account created for {$email}.");

        return self::SUCCESS;
    }
}
