<x-theme-aurora::shell title="Two-factor authentication">
    <section class="aurora-card">
        <h1>Two-factor authentication</h1>
        <p>Enter your authenticator code or one of your recovery codes.</p>
        <form method="POST" action="{{ route('two-factor.login') }}">
            @csrf
            <div class="aurora-field"><label for="code">Authentication code</label><input id="code" name="code" type="text"></div>
            <div class="aurora-field"><label for="recovery_code">Recovery code</label><input id="recovery_code" name="recovery_code" type="text"></div>
            <button class="aurora-button" type="submit">Continue</button>
        </form>
    </section>
</x-theme-aurora::shell>
