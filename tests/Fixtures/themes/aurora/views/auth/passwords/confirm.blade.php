<x-theme-aurora::shell title="Confirm password">
    <section class="aurora-card">
        <h1>Confirm your password</h1>
        <form method="POST" action="/user/confirm-password">
            @csrf
            <div class="aurora-field"><label for="password">Current password</label><input id="password" name="password" type="password" data-aurora-focus></div>
            <button class="aurora-button" type="submit">Confirm password</button>
        </form>
    </section>
</x-theme-aurora::shell>
