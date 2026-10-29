<?php

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('password reset mail renders Core\'s body with Dusk selected and no parent theme', function () {
    installHotel();
    setSetting('theme', 'dusk');
    expect(config('theme.active'))->toBeNull()
        ->and(config('theme.parent'))->toBeNull();
    Mail::fake();

    $user = User::factory()->create();

    $this->post(route('forgot.password.post'), ['mail' => $user->mail])
        ->assertSessionHas('success');

    Mail::assertQueued(ResetPasswordMail::class, function (ResetPasswordMail $mail): bool {
        expect($mail->render())
            ->toContain('Reset your password', 'Click the link to reset your password:', '/reset-password/' . $mail->token);

        return true;
    });
});
