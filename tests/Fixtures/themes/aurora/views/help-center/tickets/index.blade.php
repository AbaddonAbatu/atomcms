<x-theme-aurora::shell title="All tickets">
    <section class="aurora-card">
        <h1>Tickets</h1>
        <ul class="aurora-list">@forelse ($tickets as $ticket)<li><a href="{{ route('help-center.ticket.show', $ticket) }}">{{ $ticket->title }}</a> — {{ $ticket->user->username }}</li>@empty<li>No tickets available.</li>@endforelse</ul>
        {{ $tickets->links() }}
    </section>
</x-theme-aurora::shell>
