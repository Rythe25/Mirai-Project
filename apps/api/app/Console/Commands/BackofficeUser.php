<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/** Create a backoffice account or change its role (until the backoffice can manage users). */
class BackofficeUser extends Command
{
    protected $signature = 'backoffice:user
        {email : Account email}
        {role : admin, owner, employee, or none to remove access}';

    protected $description = 'Create a backoffice account or change its role';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        $roleName = strtolower($this->argument('role'));
        $role = UserRole::tryFrom($roleName);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Not a valid email.');

            return self::FAILURE;
        }
        if ($role === null && $roleName !== 'none') {
            $this->error('Role must be admin, owner, employee or none.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->role = $role;
            $user->save();
            $this->info($role ? "{$email} is now {$role->getLabel()}." : "{$email} can no longer open the backoffice.");

            return self::SUCCESS;
        }

        if ($role === null) {
            $this->error("No account for {$email}.");

            return self::FAILURE;
        }

        $name = text('Name', required: true);
        $password = password('Password', required: true,
            validate: fn (string $value) => strlen($value) < 12 ? 'Use at least 12 characters.' : null);
        if (password('Confirm password', required: true) !== $password) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = $role;
        $user->email_verified_at = now();
        $user->save();

        $this->info("Created {$role->getLabel()} account {$email}.");

        return self::SUCCESS;
    }
}
