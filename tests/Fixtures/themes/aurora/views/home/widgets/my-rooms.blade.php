<div class="aurora-card"><h2>Rooms</h2>@forelse ($user->rooms as $room)<p>{{ $room->name }} — {{ $room->description }}</p>@empty<p class="aurora-muted">No rooms yet.</p>@endforelse</div>
