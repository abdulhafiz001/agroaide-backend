<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateStaffAccount extends Command
{
    protected $signature = 'agroaide:staff-account {email?} {--role=}';

    protected $description = 'Interactively create or promote an agronomist/admin account';

    public function handle(): int
    {
        $email = strtolower(trim((string) ($this->argument('email') ?: $this->ask('Email'))));
        $role = (string) ($this->option('role') ?: $this->choice('Role', ['agronomist', 'admin'], 0));
        if (! in_array($role, ['agronomist', 'admin'], true)) {
            $this->error('Role must be agronomist or admin.');

            return self::FAILURE;
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user) {
            if (! $this->confirm("Promote {$email} to {$role}?")) {
                return self::FAILURE;
            }
            $user->update(['role' => $role]);
            $this->info('Staff role updated.');

            return self::SUCCESS;
        }

        $name = trim((string) $this->ask('Full name'));
        $password = (string) $this->secret('Password (hidden)');
        $confirmation = (string) $this->secret('Confirm password (hidden)');
        $validator = Validator::make(compact('email', 'name', 'password', 'confirmation'), [
            'email' => ['required', 'email', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', Password::min(12)->letters()->numbers(), 'same:confirmation'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => $role]);
        $this->info('Staff account created.');

        return self::SUCCESS;
    }
}
