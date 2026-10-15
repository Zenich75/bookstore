<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

test('register creates an unverified reader, logs in and sends the verification email', function () {
    Notification::fake();

    $this->post(route('register'), [
        'name' => 'Тарас',
        'email' => 'taras@example.com',
        'password' => 'secret-pass',
        'password_confirmation' => 'secret-pass',
        'role' => 'admin', // not validated => never reaches User::create()
    ])->assertRedirect(route('verification.notice'));

    $user = User::firstWhere('email', 'taras@example.com');

    expect($user->fresh()->role)->toBe(UserRole::Reader)
        ->and($user->hasVerifiedEmail())->toBeFalse()
        ->and(password_verify('secret-pass', $user->password))->toBeTrue();
    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('register validates input', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->post(route('register'), ['name' => '', 'email' => 'taken@example.com', 'password' => 'short', 'password_confirmation' => 'other'])
        ->assertSessionHasErrors(['name', 'email', 'password']);

    $this->assertGuest();
});

test('login regenerates the session and redirects to the intended page', function () {
    $manager = User::factory()->manager()->create();

    // The "auth" middleware stores the URL the guest wanted.
    $this->get(route('manage.warehouses.index'))->assertRedirect(route('login'));

    $sessionId = session()->getId();

    $this->post(route('login'), ['email' => $manager->email, 'password' => 'password'])
        ->assertRedirect(route('manage.warehouses.index'))
        ->assertSessionHas('status');

    $this->assertAuthenticatedAs($manager);
    expect(session()->getId())->not->toBe($sessionId);
});

test('wrong credentials: one generic error, no login', function () {
    $user = User::factory()->create();

    $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
    $this->post(route('login'), ['email' => 'nobody@example.com', 'password' => 'wrong'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('remember me sets the recaller cookie', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login'), ['email' => $user->email, 'password' => 'password', 'remember' => '1']);

    $response->assertCookie(auth()->guard()->getRecallerName());
    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('logout invalidates the session', function () {
    asReader();

    $this->post(route('logout'))->assertRedirect(route('home'));

    $this->assertGuest();
});

test('login is throttled after 5 attempts per email + IP', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
    }

    // 6th attempt: the limiter answers before the controller runs — even with the right password.
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');
    expect(session('errors')->first('email'))->toStartWith(Str::before(__('auth.throttle'), ':seconds'));
    $this->assertGuest();

    // Another email from the same IP has its own counter.
    $other = User::factory()->create();
    $this->post(route('login'), ['email' => $other->email, 'password' => 'password']);
    $this->assertAuthenticatedAs($other);
});

test('guests are redirected to login; logged-in users away from login/register', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->get(route('reports.stock'))->assertRedirect(route('login'));

    asReader();
    $this->get(route('login'))->assertRedirect(route('home'));
    $this->get(route('register'))->assertRedirect(route('home'));
});

test('unverified users are sent to the verification notice', function () {
    $this->actingAs(User::factory()->manager()->unverified()->create());

    $this->get(route('manage.warehouses.index'))->assertRedirect(route('verification.notice'));
    $this->get(route('verification.notice'))->assertOk()->assertSee(__('ui.auth.resend'));
});

test('the signed link verifies the email; a tampered link is rejected', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->get($url.'x')->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();

    $this->get($url)->assertRedirect(route('home'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('resend sends a new verification email', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status');

    Notification::assertSentTo($user, VerifyEmail::class);
});
