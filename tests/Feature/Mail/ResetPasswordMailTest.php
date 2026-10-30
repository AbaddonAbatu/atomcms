<?php

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/*
 * Reset mail renders `core::mail.password-reset` independently of HTTP
 * theme selection and public theme files.
 */

test('reset mail rendered outside a request uses the Core body', function () {
    config(['app.url' => 'https://hotel.example']);

    $mail = new ResetPasswordMail('worker-token');

    $mail->assertHasSubject('Reset Password');
    $mail->assertSeeInOrderInHtml([
        '<!DOCTYPE html>',
        '<html lang="en">',
        'Reset your password',
        'Click the link to reset your password:',
        'href="https://hotel.example/reset-password/worker-token"',
        'Reset Password',
        'If you did not request this, you can ignore this email.',
        '</html>',
    ], escape: false);
    $mail->assertDontSeeInHtml('You requested a password reset.');
});

test('reset mail rendered in headless mode uses the Core body with the frontend reset URL', function () {
    config(['atom.mode' => 'headless', 'atom.frontend_url' => 'https://frontend.example.com']);

    $mail = new ResetPasswordMail('headless-token');

    $mail->assertSeeInHtml('Click the link to reset your password:');
    $mail->assertSeeInHtml('href="https://frontend.example.com/reset-password/headless-token"', escape: false);
    $mail->assertDontSeeInHtml('You requested a password reset.');
});

test('reset mail queued from a themed request uses the Core body', function (string $theme) {
    installHotel();
    setSetting('theme', $theme);
    Mail::fake();
    $user = User::factory()->create();

    $this->post(route('forgot.password.post'), ['mail' => $user->mail]);

    Mail::assertQueued(ResetPasswordMail::class, function (ResetPasswordMail $mail): bool {
        $mail->assertSeeInHtml('Click the link to reset your password:');
        $mail->assertDontSeeInHtml('You requested a password reset.');

        return true;
    });
})->with(['atom', 'dusk']);
