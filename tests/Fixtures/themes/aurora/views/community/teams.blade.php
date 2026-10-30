<x-theme-aurora::shell title="Teams">
    <section class="aurora-card">
        <h1>Teams</h1>
        @forelse ($employees as $employee)
            <section class="aurora-card">
                <h2>{{ $employee->rank_name }}</h2>
                <p>{{ $employee->job_description }}</p>
                <ul class="aurora-list">
                    @forelse ($employee->users as $staff)
                        <li>{{ $staff->username }}</li>
                    @empty
                        <li class="aurora-muted">No members in this team.</li>
                    @endforelse
                </ul>
            </section>
        @empty
            <p class="aurora-muted">No teams are available.</p>
        @endforelse
    </section>
</x-theme-aurora::shell>
