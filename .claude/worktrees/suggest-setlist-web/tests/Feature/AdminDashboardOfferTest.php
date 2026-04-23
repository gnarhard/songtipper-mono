<?php

declare(strict_types=1);

use App\Mail\BillingOfferMail;
use App\Models\AdminDesignation;
use App\Models\BillingOffer;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('shows the complimentary access panel on the admin access page', function () {
    $adminUser = billingReadyUser([
        'email' => 'admin@example.com',
    ]);

    AdminDesignation::factory()->create([
        'email' => $adminUser->email,
    ]);

    Livewire::actingAs($adminUser)
        ->test('admin-access-page')
        ->assertSee('Complimentary Access Offers');
});

it('lets an admin send a complimentary access offer email', function () {
    Mail::fake();

    $adminUser = billingReadyUser([
        'email' => 'admin@example.com',
    ]);

    AdminDesignation::factory()->create([
        'email' => $adminUser->email,
    ]);

    $recipient = User::factory()->create([
        'email' => 'artist@example.com',
    ]);

    Livewire::actingAs($adminUser)
        ->test('admin-access-page')
        ->set('adminOfferEmail', $recipient->email)
        ->set('adminOfferPlan', User::BILLING_PLAN_PRO_YEARLY)
        ->set('adminOfferDiscount', User::BILLING_DISCOUNT_LIFETIME)
        ->call('sendBillingOffer')
        ->assertHasNoErrors()
        ->assertSee('Sent lifetime Pro access to artist@example.com.');

    $recipient->refresh();

    $billingOffer = BillingOffer::query()
        ->where('email', $recipient->email)
        ->firstOrFail();

    expect($recipient->billing_plan)->toBe(User::BILLING_PLAN_PRO_YEARLY);
    expect($recipient->billing_discount_type)->toBe(User::BILLING_DISCOUNT_LIFETIME);
    expect($recipient->billing_status)->toBe(User::BILLING_STATUS_DISCOUNTED);
    expect($billingOffer->billing_plan)->toBe(User::BILLING_PLAN_PRO_YEARLY);
    expect($billingOffer->billing_discount_type)->toBe(User::BILLING_DISCOUNT_LIFETIME);
    expect($billingOffer->sent_at)->not->toBeNull();

    Mail::assertSent(BillingOfferMail::class, function (BillingOfferMail $mail) use ($recipient): bool {
        return $mail->hasTo($recipient->email)
            && $mail->planLabel === 'Pro'
            && $mail->durationLabel === 'lifetime';
    });
});

it('guards the admin access page behind admin middleware', function () {
    $this->actingAs(billingReadyUser())
        ->get('/admin/access')
        ->assertStatus(403);
});
