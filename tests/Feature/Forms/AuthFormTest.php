<?php

use App\Models\User;

beforeEach(function () {
    app()->setLocale('uk');
});

test('RegisterRequest rules', function (array $overrides, array $errors) {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->post(route('register'), [
        'name' => 'Леся',
        'email' => 'lesia@example.com',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
        ...$overrides,
    ]);

    $errors ? $response->assertSessionHasErrors($errors) : $response->assertSessionHasNoErrors();
})->with([
    'valid' => [[], []],
    'no name' => [['name' => ''], ['name']],
    'not an email' => [['email' => 'lesia'], ['email']],
    'upper-case email' => [['email' => 'Lesia@Example.com'], ['email']],
    'email taken' => [['email' => 'taken@example.com'], ['email']],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], ['password']],
    'confirmation mismatch' => [['password_confirmation' => 'other-pass'], ['password']],
]);

test('validation messages and attribute names are Ukrainian', function () {
    $this->post(route('register'), ['name' => '', 'email' => 'x', 'password' => '1'])
        ->assertSessionHasErrors([
            'name' => __('validation.required', ['attribute' => 'ім\'я']),
            'email' => __('validation.email', ['attribute' => 'електронна пошта']),
        ]);

    expect(session('errors')->first('password'))->toMatch('/\p{Cyrillic}/u');
});

test('LoginRequest: stopOnFirstFailure reports only the first failing field', function () {
    $this->post(route('login'), ['email' => 'not-an-email', 'password' => ''])
        ->assertSessionHasErrors('email')
        ->assertSessionDoesntHaveErrors('password');

    // Without the email error, the password one shows up.
    $this->post(route('login'), ['email' => 'a@example.com', 'password' => ''])
        ->assertSessionHasErrors('password');
});

test('LoginRequest counts failed attempts only; a successful login resets the counter', function () {
    $user = User::factory()->create();
    $fail = fn () => $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong']);

    foreach (range(1, 4) as $attempt) {
        $fail()->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
    $this->post(route('logout'));

    // Counter cleared: four more failures are still "wrong credentials", not "throttled".
    foreach (range(1, 4) as $attempt) {
        $fail()->assertSessionHasErrors(['email' => __('auth.failed')]);
    }
});

test('the old email is kept after a failed login, the password is not', function () {
    $this->from(route('login'))->post(route('login'), ['email' => 'nobody@example.com', 'password' => 'wrong']);

    $this->get(route('login'))
        ->assertSee('value="nobody@example.com"', false)
        ->assertDontSee('value="wrong"', false);
});
