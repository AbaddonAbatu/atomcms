import "./bootstrap";

import.meta.glob(["../images/**"], {
    eager: true,
    query: "?url&no-inline",
    import: "default",
});

import "swiper/css";
import "swiper/css/pagination";

import { Alpine } from "livewire";
import startPublicTheme from "../../../js/public-theme.js";
import packagePurchase from "../../../js/components/PackagePurchase.js";

import ThemeSwitcher from "./components/ThemeSwitcher.js";
import AtomSliders from "./components/AtomSliders.js";

import "../../../js/components/HomeManager.js";

ThemeSwitcher.init();
AtomSliders.init();

Alpine.data("packagePurchase", packagePurchase);
startPublicTheme();
