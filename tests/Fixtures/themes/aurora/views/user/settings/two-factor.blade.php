<x-theme-aurora::shell title="Two-factor settings">
    <section class="aurora-card">
        <h1>Two-factor settings</h1>
        @if (auth()->user()->hasEnabledTwoFactorAuthentication())
            <p>Two-factor authentication is enabled.</p>
            <form action="{{ route('user.two-factor.disable') }}" method="POST">
                @csrf
                @method('DELETE')
                <div class="aurora-field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password"></div>
                <button class="aurora-button" type="submit">Disable two-factor authentication</button>
            </form>
        @elseif (auth()->user()->two_factor_secret)
            <p>Confirm the pending authenticator setup.</p>
            <p>Secret: {{ auth()->user()->two_factor_secret }}</p>
            <p>Recovery codes: {{ implode(', ', auth()->user()->recoveryCodes()) }}</p>
            <form action="{{ route('two-factor.verify') }}" method="POST">
                @csrf
                <div class="aurora-field"><label for="code">Code</label><input id="code" name="code" type="text"></div>
                <button class="aurora-button" type="submit">Confirm setup</button>
            </form>
        @else
            <p>Protect your account with an authenticator app.</p>
            <form action="{{ route('user.two-factor.enable') }}" method="POST">
                @csrf
                <div class="aurora-field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password"></div>
                <button class="aurora-button" type="submit">Enable two-factor authentication</button>
            </form>
        @endif
    </section>
</x-theme-aurora::shell>
