<x-theme-aurora::shell title="Create account">
    <section class="aurora-card">
        <h1>Create an account</h1>
        <form method="POST" action="{{ route('register.store') }}">
            @csrf
            <div class="aurora-field"><label for="username">Username</label><input id="username" name="username" value="{{ old('username') }}"></div>
            <div class="aurora-field"><label for="mail">Email</label><input id="mail" name="mail" type="email" value="{{ old('mail') }}"></div>
            <div class="aurora-field"><label for="password">Password</label><input id="password" name="password" type="password"></div>
            <div class="aurora-field"><label for="password_confirmation">Repeat password</label><input id="password_confirmation" name="password_confirmation" type="password"></div>
            @if (setting('requires_beta_code'))
                <div class="aurora-field"><label for="beta_code">Beta code</label><input id="beta_code" name="beta_code" value="{{ old('beta_code') }}"></div>
            @endif
            <label><input type="checkbox" name="terms" value="1"> I accept the terms and rules.</label>
            @if ($referral_code)
                <input type="hidden" name="referral_code" value="{{ $referral_code }}">
            @endif
            <button class="aurora-button" type="submit">Create account</button>
        </form>
    </section>
</x-theme-aurora::shell>
