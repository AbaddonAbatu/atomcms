import.meta.glob(["../images/**"], {
    eager: true,
    query: "?url&no-inline",
    import: "default",
});

import { Alpine } from "livewire";
import startPublicTheme from "../../../js/public-theme.js";
import packagePurchase from "../../../js/components/PackagePurchase.js";

import Swiper from "swiper";
import { Navigation, Pagination } from "swiper/modules";
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";

import "../../../js/components/HomeManager.js";

Alpine.data("packagePurchase", packagePurchase);
startPublicTheme();

// Swiper Initialization
document.addEventListener("DOMContentLoaded", function () {
    const swiper = new Swiper(".swiper", {
        modules: [Navigation, Pagination],
        // Your Swiper options here
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
        pagination: {
            el: ".swiper-pagination",
        },
    });
});
