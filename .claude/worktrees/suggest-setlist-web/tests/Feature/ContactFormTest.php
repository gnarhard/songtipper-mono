<?php

declare(strict_types=1);

use App\Mail\ContactFormSubmission;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    Mail::fake();
});

test('contact form sends email when submitted by a human', function () {
    $form = Livewire::test('contact-form')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('message', 'Hello, this is a test message.');

    $this->travel(5)->seconds();

    $form->call('submit')
        ->assertSet('submitted', true);

    Mail::assertSent(ContactFormSubmission::class);
});

test('contact form does not send email when honeypot field is filled', function () {
    $this->travel(5)->seconds();

    Livewire::test('contact-form')
        ->set('name', 'Bot')
        ->set('email', 'bot@example.com')
        ->set('message', 'Spam message from a bot.')
        ->set('website', 'https://spam.example.com')
        ->call('submit')
        ->assertSet('submitted', true);

    Mail::assertNothingSent();
});

test('contact form does not send email when submitted too quickly', function () {
    Livewire::test('contact-form')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('message', 'Hello, this is a test message.')
        ->call('submit')
        ->assertSet('submitted', true);

    Mail::assertNothingSent();
});
