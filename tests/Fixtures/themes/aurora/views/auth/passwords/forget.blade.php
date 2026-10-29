<x-theme-aurora::shell title="Forgot password">
    <section class="aurora-card">
        <h1>Forgot your password?</h1>
        <form method="POST" action="{{ route('forgot.password.post') }}">
            @csrf
            <div class="aurora-field"><label for="mail">Email</label><input id="mail" name="mail" type="email" value="{{ old('mail') }}" data-aurora-focus></div>
            <button class="aurora-button" type="submit">Email password reset link</button>
        </form>
    </section>
</x-theme-aurora::shell>
