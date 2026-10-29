<x-theme-aurora::shell title="Account settings">
    <section class="aurora-card">
        <h1>Account settings</h1>
        <form action="{{ route('settings.account.update') }}" method="POST">
            @method('PUT')
            @csrf
            <div class="aurora-field"><label for="mail">Email</label><input id="mail" name="mail" type="email" value="{{ $user->mail }}"></div>
            @if ($canChangeName)
                <div class="aurora-field"><label for="username">Username</label><input id="username" name="username" value="{{ $user->username }}"></div>
            @endif
            <div class="aurora-field"><label for="motto">Motto</label><input id="motto" name="motto" value="{{ $user->motto }}"></div>
            <div class="aurora-field"><label for="current_password">Current password, if required</label><input id="current_password" name="current_password" type="password"></div>
            <button class="aurora-button" type="submit">Save account</button>
        </form>
    </section>
</x-theme-aurora::shell>
