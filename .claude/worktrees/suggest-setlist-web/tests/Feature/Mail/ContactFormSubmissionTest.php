<?php

declare(strict_types=1);

use App\Mail\ContactFormSubmission;
use Illuminate\Mail\Mailables\Address;

test('contact form submission has correct subject', function () {
    $mail = new ContactFormSubmission('John Doe', 'john@example.com', 'Hello there');

    expect($mail->envelope()->subject)->toBe('Contact Form Submission from John Doe');
});

test('contact form submission has correct reply-to address', function () {
    $mail = new ContactFormSubmission('John Doe', 'john@example.com', 'Hello there');

    $envelope = $mail->envelope();

    expect($envelope->replyTo)->toHaveCount(1)
        ->and($envelope->replyTo[0])->toBeInstanceOf(Address::class)
        ->and($envelope->replyTo[0]->address)->toBe('john@example.com')
        ->and($envelope->replyTo[0]->name)->toBe('John Doe');
});

test('contact form submission has correct content view', function () {
    $mail = new ContactFormSubmission('John Doe', 'john@example.com', 'Hello there');

    expect($mail->content()->view)->toBe('emails.contact-form');
});

test('contact form submission has no attachments', function () {
    $mail = new ContactFormSubmission('John Doe', 'john@example.com', 'Hello there');

    expect($mail->attachments())->toBe([]);
});
