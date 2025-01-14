/**
 * Debloat plugin's defer load.
 * @preserve
 * @copyright asadkn 2021
 */
"use strict";

(() => {
    const n = true;
    const e = [ ...document.querySelectorAll("script[defer]") ];
    if (e.length && document.readyState !== "complete") {
        let t = document.readyState;
        Object.defineProperty(document, "readyState", {
            configurable: true,
            get() {
                return t;
            },
            set(e) {
                return t = e;
            }
        });
        let e = false;
        document.addEventListener("DOMContentLoaded", () => {
            t = "interactive";
            n && console.log("DCL Ready.");
            e = true;
            document.dispatchEvent(new Event("readystatechange"));
            e = false;
        });
        document.addEventListener("readystatechange", () => {
            if (!e && t === "interactive") {
                t = "complete";
            }
        });
    }
})();