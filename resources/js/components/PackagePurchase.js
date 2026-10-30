export default function packagePurchase({ action, giftMessage, chargeMessage, purchaseMessage }) {
    return {
        confirmOpen: false,
        confirmAction: "",
        confirmReceiver: "",
        confirmMessage: "",

        prepareGift(query) {
            if (!query.trim()) {
                return false;
            }

            this.confirmAction = action;
            this.confirmReceiver = query;
            this.confirmMessage = `${giftMessage} ${query}. ${chargeMessage}`;
            return true;
        },

        preparePurchase() {
            this.confirmAction = action;
            this.confirmReceiver = "";
            this.confirmMessage = purchaseMessage;
            this.confirmOpen = true;
        },

        recipientSearch() {
            return {
                query: "",
                results: [],
                activeIndex: -1,
                showDropdown: false,
                loading: false,
                debounceTimer: null,

                search() {
                    clearTimeout(this.debounceTimer);
                    if (this.query.length < 2) {
                        this.results = [];
                        this.showDropdown = false;
                        return;
                    }
                    this.loading = true;
                    this.debounceTimer = setTimeout(async () => {
                        const response = await fetch(`/api/users/search?q=${encodeURIComponent(this.query)}`);
                        this.results = await response.json();
                        this.showDropdown = this.results.length > 0;
                        this.activeIndex = -1;
                        this.loading = false;
                    }, 250);
                },

                select(username) {
                    this.query = username;
                    this.showDropdown = false;
                    this.activeIndex = -1;
                },

                handleKeydown(event) {
                    if (!this.showDropdown) {
                        return;
                    }
                    if (event.key === "ArrowDown") {
                        event.preventDefault();
                        this.activeIndex = (this.activeIndex + 1) % this.results.length;
                    } else if (event.key === "ArrowUp") {
                        event.preventDefault();
                        this.activeIndex = this.activeIndex <= 0 ? this.results.length - 1 : this.activeIndex - 1;
                    } else if (event.key === "Enter" && this.activeIndex >= 0) {
                        event.preventDefault();
                        this.select(this.results[this.activeIndex].username);
                    } else if (event.key === "Tab" && this.activeIndex >= 0) {
                        event.preventDefault();
                        this.select(this.results[this.activeIndex].username);
                    } else if (event.key === "Escape") {
                        this.showDropdown = false;
                        this.activeIndex = -1;
                    }
                },
            };
        },
    };
}
