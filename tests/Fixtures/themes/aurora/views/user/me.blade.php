<x-theme-aurora::shell title="My home">
    <section class="aurora-card">
        <h1>{{ $user->username }}'s home</h1>
        <p>{{ $user->motto }}</p>
        <p><a href="{{ route('settings.account.show') }}">Edit account settings</a></p>
        <form method="POST" action="{{ route('claim.referral-reward') }}">
            @csrf
            <label for="referral">Referral link</label>
            <input id="referral" name="referral" value="{{ config('habbo.site.site_url') }}/register/{{ $user->referral_code }}" readonly>
            <button class="aurora-button" type="submit">Claim referral reward</button>
        </form>
    </section>
    <section class="aurora-card"><h2>Online friends</h2><p>{{ count($onlineFriends) }} friends online.</p></section>
    <section class="aurora-card"><h2>Recent articles</h2><ul class="aurora-list">@forelse ($articles as $article)<li>{{ $article->title }}</li>@empty<li>No recent articles.</li>@endforelse</ul></section>
</x-theme-aurora::shell>
