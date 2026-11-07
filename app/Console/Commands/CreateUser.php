<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateUser extends Command
{
    protected $signature = 'jokot:create-user {--name=} {--email=} {--password=}';

    protected $description = 'Create a board member who can sign in to the admin site (/admin)';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? text('Naam', required: true),
            'email' => $this->option('email') ?? text('E-mail', required: true),
            'password' => $this->option('password') ?? password('Wachtwoord (min. 8 tekens)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        User::query()->create([...$data, 'is_active' => true]);
        $this->components->info("Gebruiker {$data['email']} aangemaakt. Aanmelden via ".url('/admin'));

        return self::SUCCESS;
    }
}
