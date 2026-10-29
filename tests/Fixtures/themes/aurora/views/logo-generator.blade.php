<x-theme-aurora::shell title="Logo generator">
    <section class="aurora-card">
        <h1>Logo generator</h1>
        <p>Create a logo with the Aurora client tools.</p>
        <form action="{{ route('store.generated-logo') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="aurora-field"><label for="logo">Logo file</label><input id="logo" name="logo" type="file"></div>
            <button class="aurora-button" type="submit">Upload logo</button>
        </form>
    </section>
</x-theme-aurora::shell>
