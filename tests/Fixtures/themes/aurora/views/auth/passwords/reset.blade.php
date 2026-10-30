<x-theme-aurora::shell title="Reset password">
    <section class="aurora-card">
        <h1>Choose a new password</h1>
        <form method="POST" action="{{ route('reset.password.post', $token) }}">
            @csrf
            <div class="aurora-field"><label for="password">Password</label><input id="password" name="password" type="password" data-aurora-focus></div>
            <div class="aurora-field"><label for="password_confirmation">Repeat password</label><input id="password_confirmation" name="password_confirmation" type="password"></div>
            <button class="aurora-button" type="submit">Reset password</button>
        </form>
    </section>
</x-theme-aurora::shell>
