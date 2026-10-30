<x-theme-aurora::shell title="Maintenance">
    <section class="aurora-card">
        <h1>Maintenance in progress</h1>
        <p>We are working on the hotel. Please check back soon.</p>
        <ul class="aurora-list">
            @forelse ($tasks as $task)
                <li>{{ $task->task }}</li>
            @empty
                <li class="aurora-muted">No maintenance tasks are listed.</li>
            @endforelse
        </ul>
    </section>
</x-theme-aurora::shell>
