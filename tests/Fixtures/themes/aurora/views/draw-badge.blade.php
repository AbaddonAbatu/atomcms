<x-theme-aurora::shell title="Badge creator">
    <section class="aurora-card">
        <h1>Badge creator</h1>
        <p>Cost: {{ $cost }} {{ $currencyType }}</p>
        @if ($folderError)<p role="alert">{{ $errorMessage }}</p>@endif
        <p>Design your badge in the client and submit it when ready.</p>
    </section>
</x-theme-aurora::shell>
