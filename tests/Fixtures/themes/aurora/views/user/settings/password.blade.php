<x-theme-aurora::shell title="Password settings">
    <section class="aurora-card">
        <h1>Change password</h1>
        <form action="{{ route('settings.password.update') }}" method="POST">
            @method('PUT')
            @csrf
            <div class="aurora-field"><label for="current_password">Current password</label><input id="current_password" name="current_password" type="password"></div>
            <div class="aurora-field"><label for="password">New password</label><input id="password" name="password" type="password"></div>
            <div class="aurora-field"><label for="password_confirmation">Repeat password</label><input id="password_confirmation" name="password_confirmation" type="password"></div>
            <button class="aurora-button" type="submit">Change password</button>
        </form>
    </section>
</x-theme-aurora::shell>
