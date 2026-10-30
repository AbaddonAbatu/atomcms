<?php

use App\Support\FlashToasts;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

test('flash toasts preserve message, default errors, login errors, and success in order', function (): void {
    $session = session()->driver();
    $session->flash('message', 'Something went wrong');
    $session->flash('success', 'Changes saved');
    $errors = (new ViewErrorBag)
        ->put('default', new MessageBag([
            'mail' => ['Enter an email address', 'Use a valid email address'],
            'password' => ['Enter a password'],
        ]))
        ->put('login', new MessageBag(['username' => ['Unknown username', 'Try again']]))
        ->put('other', new MessageBag(['field' => ['Not a toast']]));

    $toasts = FlashToasts::from($session, $errors);

    expect($toasts)->toBe([
        ['title' => 'Something went wrong', 'icon' => 'error'],
        ['title' => 'Enter an email address', 'icon' => 'error'],
        ['title' => 'Use a valid email address', 'icon' => 'error'],
        ['title' => 'Enter a password', 'icon' => 'error'],
        ['title' => 'Unknown username', 'icon' => 'error'],
        ['title' => 'Try again', 'icon' => 'error'],
        ['title' => 'Changes saved', 'icon' => 'success'],
    ]);
});

test('missing or null flashes and empty error bags produce no toasts', function (array $flashes): void {
    $session = session()->driver();
    $session->put($flashes);

    expect(FlashToasts::from($session, new ViewErrorBag))->toBe([]);
})->with([
    'missing flashes' => [[]],
    'null flashes' => [['message' => null, 'success' => null]],
]);

test('empty string flashes remain toast entries', function (): void {
    $session = session()->driver();
    $session->flash('message', '');
    $session->flash('success', '');

    expect(FlashToasts::from($session, new ViewErrorBag))->toBe([
        ['title' => '', 'icon' => 'error'],
        ['title' => '', 'icon' => 'success'],
    ]);
});
