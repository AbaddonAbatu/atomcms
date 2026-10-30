<x-theme-aurora::shell title="Log in">
    <section class="aurora-card">
        <h1>Log in</h1>
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <div class="aurora-field"><label for="username">Username</label><input id="username" name="username" value="{{ old('username') }}" data-aurora-focus></div>
            <div class="aurora-field"><label for="password">Password</label><input id="password" name="password" type="password"></div>
            <button class="aurora-button" type="submit">Log in</button>
        </form>
        <a href="{{ route('forgot.password.get') }}">Forgot your password?</a>
    </section>
</x-theme-aurora::shell>
