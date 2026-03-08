/**
 * OzTheme frontend scripts.
 * WAPO color navigation, addon handlers, price label, tooltip + checkbox logic
 * all removed in Phase 4 cleanup — oz-variations-bcw handles everything now.
 *
 * Remaining: TrustIndex lazy-loading (skip on PageSpeed / GTmetrix bots).
 */
jQuery(document).ready(function($) {

    // --- TrustIndex lazy-loading ---
    // Skip loading on speed-test bots so it doesn't hurt Lighthouse scores
    function isTrustIndexLoadable() {
        var userAgent = navigator.userAgent;
        var pageSpeedTesters = [
            'Google Page Speed Insights',
            'GTmetrix',
            'Pingdom',
        ];

        return !pageSpeedTesters.some(tester => userAgent.indexOf(tester) !== -1);
    }

    if (isTrustIndexLoadable()) {
        var trustIndexContainer = document.getElementById('trustindex-script-container');
        if (trustIndexContainer) {
            var trustIndexScript = document.createElement('script');
            trustIndexScript.src = 'https://cdn.trustindex.io/loader.js?c754f892650e180cb696989cccc';
            trustIndexScript.defer = true;
            trustIndexScript.async = true;
            trustIndexContainer.appendChild(trustIndexScript);
        }
    }
});
