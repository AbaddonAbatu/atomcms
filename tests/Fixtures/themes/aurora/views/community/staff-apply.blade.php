<x-theme-aurora::shell title="Staff application">
    <section class="aurora-card">
        <h1>Apply for {{ $position->permission->rank_name }}</h1>
        <form action="{{ route('staff-applications.store', $position) }}" method="POST">
            @csrf
            <div class="aurora-field"><label for="username">Username</label><input id="username" name="username" value="{{ auth()->user()->username }}" readonly></div>
            <div class="aurora-field"><label for="content">Application</label><textarea id="content" name="content">{{ old('content') }}</textarea></div>
            <button class="aurora-button" type="submit">Submit application</button>
        </form>
    </section>
</x-theme-aurora::shell>
