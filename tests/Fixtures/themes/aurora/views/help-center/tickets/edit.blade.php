<x-theme-aurora::shell title="Edit ticket">
    <section class="aurora-card">
        <h1>Edit {{ $ticket->title }}</h1>
        <form action="{{ route('help-center.ticket.update', $ticket) }}" method="POST">
            @method('PUT')
            @csrf
            <div class="aurora-field"><label for="category_id">Category</label><select id="category_id" name="category_id">@foreach ($categories as $category)<option value="{{ $category->id }}" @selected($category->id === $ticket->category_id)>{{ $category->name }}</option>@endforeach</select></div>
            <div class="aurora-field"><label for="title">Title</label><input id="title" name="title" value="{{ $ticket->title }}"></div>
            <div class="aurora-field"><label for="content">Description</label><textarea id="content" name="content">{{ $ticket->content }}</textarea></div>
            <button class="aurora-button" type="submit">Save ticket</button>
        </form>
        <p>Other open tickets:</p><ul class="aurora-list">@forelse ($openTickets as $openTicket)<li>{{ $openTicket->title }}</li>@empty<li>None.</li>@endforelse</ul>
    </section>
</x-theme-aurora::shell>
