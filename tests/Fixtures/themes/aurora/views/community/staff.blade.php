<x-theme-aurora::shell title="Staff">
    <section class="aurora-card">
        <h1>Staff</h1>
        @forelse ($employees as $employee)
            <section class="aurora-card">
                <h2>{{ $employee->rank_name }}</h2>
                <p>{{ $employee->job_description }}</p>
                <ul class="aurora-list">
                    @forelse ($employee->users as $staff)
                        <li>{{ $staff->username }}</li>
                    @empty
                        <li class="aurora-muted">No staff in this position.</li>
                    @endforelse
                </ul>
            </section>
        @empty
            <p class="aurora-muted">No staff positions are available.</p>
        @endforelse
    </section>
</x-theme-aurora::shell>
