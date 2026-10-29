document.documentElement.dataset.auroraReady = 'true';

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-aurora-focus]').forEach((field) => field.focus());
});
