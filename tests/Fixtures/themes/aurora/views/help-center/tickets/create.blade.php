<x-theme-aurora::shell title="Create ticket">
    <section class="aurora-card">
        <h1>Create a ticket</h1>
        <form action="{{ route('help-center.ticket.store') }}" method="POST">
            @csrf
            <div class="aurora-field"><label for="category_id">Category</label><select id="category_id" name="category_id">@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></div>
            <div class="aurora-field"><label for="title">Title</label><input id="title" name="title" value="{{ old('title') }}"></div>
            <div class="aurora-field"><label for="content">Description</label><textarea id="content" name="content">{{ old('content') }}</textarea></div>
            <button class="aurora-button" type="submit">Submit ticket</button>
        </form>
        <h2>Open tickets</h2>
        <ul class="aurora-list">@forelse ($openTickets as $ticket)<li><a href="{{ route('help-center.ticket.show', $ticket) }}">{{ $ticket->title }}</a></li>@empty<li>No open tickets.</li>@endforelse</ul>
    </section>
</x-theme-aurora::shell>
