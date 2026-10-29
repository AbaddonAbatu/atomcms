<div class="aurora-card"><h2>Badges</h2>@forelse ($user->badges as $badge)<p>{{ $badge->badge_code }}</p>@empty<p class="aurora-muted">No badges yet.</p>@endforelse{{ $user->badges->links() }}</div>
