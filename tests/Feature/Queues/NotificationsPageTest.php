<?php

use App\Models\User;
use App\Notifications\LowStockNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

function lowStock(string $warehouse = 'Київ'): LowStockNotification
{
    return new LowStockNotification(1, $warehouse, [], 3, 5);
}

test('staff see a bell with the unread count; readers do not', function () {
    $manager = asManager();
    $manager->notify(lowStock());
    $manager->notify(lowStock('Львів'));

    $this->get(route('home'))->assertSee('data-bell', false)->assertSee('<span class="absolute -right-2 -top-2 rounded-full bg-red-600 px-1.5 text-xs leading-5 text-white" data-unread>2</span>', false);

    asReader();
    $this->get(route('home'))->assertDontSee('data-bell', false);
    $this->get(route('notifications.index'))->assertForbidden();
});

test('list, mark one as read (follows its link), mark all as read', function () {
    app()->setLocale('uk');
    $manager = asManager();
    $manager->notify(lowStock());
    $manager->notify(lowStock('Львів'));
    $first = $manager->notifications()->first();

    $this->get(route('notifications.index'))->assertOk()
        ->assertSee('Склад «Київ»: позицій, що закінчуються, — 3.');

    $this->post(route('notifications.read', $first->id))->assertRedirect($first->data['url']);
    expect($manager->unreadNotifications()->count())->toBe(1);

    $this->post(route('notifications.read-all'))->assertSessionHas('status');
    expect($manager->unreadNotifications()->count())->toBe(0);
});

test('someone else\'s notification is a 404', function () {
    $other = User::factory()->manager()->create();
    $other->notify(lowStock());

    asManager();
    $this->post(route('notifications.read', $other->notifications()->first()->id))->assertNotFound();
});

test('notifications go through the queue with ShouldQueue', function () {
    Notification::fake();
    $manager = User::factory()->manager()->create();

    $manager->notify(lowStock());

    Notification::assertSentTo($manager, LowStockNotification::class);
    expect(lowStock())->toBeInstanceOf(ShouldQueue::class);
});
