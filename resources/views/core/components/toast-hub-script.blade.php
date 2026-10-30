<script>
    (() => {
        // Site-wide toast API. Accepts the same icons the old SweetAlert
        // mixin did: success, error, or anything else for an info style.
        window.toast = (title, icon = 'success') => {
            window.dispatchEvent(new CustomEvent('toast', { detail: { title, icon } }));
        };

        // Compatibility shim for the previous SweetAlert-based call sites.
        window.Toast = {
            fire: ({ title = '', icon = 'success' } = {}) => window.toast(title, icon),
        };

        // A plain global so Alpine can resolve it from x-data regardless of
        // how the theme bundles Alpine.
        window.toastHub = (initialToasts = []) => ({
            toasts: [],
            nextId: 1,

            init() {
                initialToasts.forEach((toast) => this.push(toast.title, toast.icon));
            },

            push(title, icon = 'success') {
                if (! title) {
                    return;
                }

                this.toasts.push({ id: this.nextId++, title, icon });
            },

            remove(id) {
                this.toasts = this.toasts.filter((toast) => toast.id !== id);
            },
        });
    })();
</script>
