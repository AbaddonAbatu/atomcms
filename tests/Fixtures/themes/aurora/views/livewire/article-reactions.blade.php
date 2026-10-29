<div class="aurora-card" wire:key="aurora-article-reactions">
    <h2>Reactions</h2>
    @if ($isAuthenticated)
        <button type="button" class="aurora-button" wire:click="openModal">Add reaction</button>
    @endif
    <ul class="aurora-list">
        @foreach ($articleReactions as $reaction)
            <li>
                <button type="button" wire:click="toggleReaction('{{ $reaction['name'] }}')">{{ $reaction['name'] }} ({{ $reaction['count'] }})</button>
                @if (count($reaction['users']))<span class="aurora-muted">{{ $reaction['users']->join(', ') }}</span>@endif
            </li>
        @endforeach
    </ul>
    @if ($showModal)
        <dialog open aria-label="Add reaction">
            <button type="button" wire:click="closeModal">Close</button>
            @foreach ($availableReactions as $reaction)
                <button type="button" wire:click="toggleReaction('{{ $reaction }}')">{{ $reaction }}</button>
            @endforeach
        </dialog>
    @endif
</div>
