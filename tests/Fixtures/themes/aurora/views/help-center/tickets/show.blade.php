<x-theme-aurora::shell :title="$ticket->title">
    <article class="aurora-card">
        <h1>{{ $ticket->title }}</h1>
        <p>{{ $ticket->category->name }} — {{ $ticket->open ? 'Open' : 'Closed' }}</p>
        <div>{{ $ticket->content }}</div>
        @can('update', $ticket)<a href="{{ route('help-center.ticket.edit', $ticket) }}">Edit ticket</a>@endcan
        <form action="{{ route('help-center.ticket.toggle-status', $ticket) }}" method="POST">
            @method('PUT')
            @csrf
            <button class="aurora-button" type="submit">{{ $ticket->open ? 'Close' : 'Re-open' }}</button>
        </form>
        @can('delete', $ticket)
            <form action="{{ route('help-center.ticket.destroy', $ticket) }}" method="POST">@method('DELETE') @csrf <button type="submit">Delete ticket</button></form>
        @endcan
    </article>
    <section class="aurora-card">
        <h2>Replies</h2>
        <ul class="aurora-list">@forelse ($ticket->replies as $reply)<li>{{ $reply->user->username }}: {{ $reply->content }}</li>@empty<li>No replies.</li>@endforelse</ul>
        @if ($ticket->open)
            <form action="{{ route('help-center.ticket.reply.store', $ticket) }}" method="POST">@csrf<textarea name="content"></textarea><button class="aurora-button" type="submit">Reply</button></form>
        @endif
    </section>
</x-theme-aurora::shell>
