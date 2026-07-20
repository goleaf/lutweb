<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        /** @var array<string, string> $countries */
        $countries = config('countries');

        $email = Str::lower((string) ($input['email'] ?? ''));
        $countryCode = Str::upper((string) ($input['country_code'] ?? ''));
        $acceptedAt = now();

        Validator::make([
            ...$input,
            'email' => $email,
            'country_code' => $countryCode,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'country_code' => ['required', 'string', Rule::in(array_keys($countries))],
            'password' => $this->passwordRules(),
            'accept_terms' => ['required', 'accepted'],
            'accept_privacy' => ['required', 'accepted'],
        ])->validate();

        return User::create([
            'name' => $input['name'],
            'email' => $email,
            'country_code' => $countryCode,
            'password' => $input['password'],
            'terms_accepted_at' => $acceptedAt,
            'privacy_accepted_at' => $acceptedAt,
            'terms_version' => (string) config('legal.terms_version'),
            'privacy_version' => (string) config('legal.privacy_version'),
        ]);
    }
}
