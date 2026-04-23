<?php

declare(strict_types=1);

use App\Mail\BillingOfferMail;
use Illuminate\Support\Carbon;

test('billing offer mail has correct subject', function () {
    $mail = new BillingOfferMail(
        recipientEmail: 'user@example.com',
        planLabel: 'Pro',
        durationLabel: '30 days',
        billingDiscountEndsAt: Carbon::now()->addDays(30),
        registerUrl: 'https://example.com/register',
        loginUrl: 'https://example.com/login',
    );

    expect($mail->envelope()->subject)->toBe('Your complimentary Pro access is ready');
});

test('billing offer mail has correct content', function () {
    $mail = new BillingOfferMail(
        recipientEmail: 'user@example.com',
        planLabel: 'Pro',
        durationLabel: '30 days',
        billingDiscountEndsAt: Carbon::now()->addDays(30),
        registerUrl: 'https://example.com/register',
        loginUrl: 'https://example.com/login',
    );

    expect($mail->content()->markdown)->toBe('emails.billing-offer');
});

test('billing offer mail has no attachments', function () {
    $mail = new BillingOfferMail(
        recipientEmail: 'user@example.com',
        planLabel: 'Basic',
        durationLabel: '7 days',
        billingDiscountEndsAt: null,
        registerUrl: 'https://example.com/register',
        loginUrl: 'https://example.com/login',
    );

    expect($mail->attachments())->toBe([]);
});

test('billing offer mail works with null discount end date', function () {
    $mail = new BillingOfferMail(
        recipientEmail: 'user@example.com',
        planLabel: 'Basic',
        durationLabel: '14 days',
        billingDiscountEndsAt: null,
        registerUrl: 'https://example.com/register',
        loginUrl: 'https://example.com/login',
    );

    expect($mail->billingDiscountEndsAt)->toBeNull()
        ->and($mail->envelope()->subject)->toBe('Your complimentary Basic access is ready');
});
