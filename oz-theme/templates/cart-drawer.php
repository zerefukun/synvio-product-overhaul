<?php
/**
 * Cart Drawer Template
 *
 * Slide-in cart panel injected via wp_footer.
 * All dynamic content (items, upsells, totals) rendered by JS via AJAX.
 * This template provides the shell structure only.
 *
 * @package OzTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get payment gateway icons for the footer
$oz_drawer_gateways = [
    'mollie_wc_gateway_ideal',
    'mollie_wc_gateway_creditcard',
    'mollie_wc_gateway_paypal',
    'mollie_wc_gateway_applepay',
    'mollie_wc_gateway_bancontact',
];
$oz_drawer_icons = [];
if (function_exists('WC') && WC()->payment_gateways()) {
    $available = WC()->payment_gateways()->get_available_payment_gateways();
    foreach ($oz_drawer_gateways as $gw_id) {
        if (isset($available[$gw_id])) {
            $icon_html = $available[$gw_id]->get_icon();
            if ($icon_html) {
                $oz_drawer_icons[] = $icon_html;
            }
        }
    }
}
?>

<!-- Cart Drawer Overlay -->
<div class="oz-drawer-overlay" id="ozDrawerOverlay"></div>

<!-- Cart Drawer Panel -->
<div class="oz-drawer" id="ozDrawer" role="dialog" aria-modal="true" aria-label="Winkelwagen">

    <!-- Header -->
    <div class="oz-drawer-header">
        <div>
            <span class="oz-drawer-title">Winkelwagen</span>
            <span class="oz-drawer-count" id="ozDrawerCount" aria-live="polite" aria-atomic="true">0</span>
        </div>
        <button class="oz-drawer-close" id="ozDrawerClose" aria-label="Sluiten" title="Sluiten">
            <svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                <path d="M1 1l12 12M13 1L1 13"/>
            </svg>
        </button>
    </div>

    <!-- Free shipping progress bar -->
    <div class="oz-shipping-bar" id="ozShippingBar">
        <div class="oz-shipping-text" id="ozShippingText"></div>
        <div class="oz-shipping-track">
            <div class="oz-shipping-fill" id="ozShippingFill" style="width:0%"></div>
        </div>
    </div>

    <!-- Scrollable cart items region -->
    <div class="oz-drawer-body" id="ozDrawerBody">

        <!-- Loading skeleton — shown during initial fetch -->
        <div class="oz-cart-skeleton" id="ozCartSkeleton" style="display:none" aria-hidden="true">
            <div class="oz-cart-skeleton-item">
                <div class="oz-cart-skeleton-img"></div>
                <div class="oz-cart-skeleton-lines">
                    <div class="oz-cart-skeleton-line"></div>
                    <div class="oz-cart-skeleton-line"></div>
                    <div class="oz-cart-skeleton-line"></div>
                </div>
            </div>
            <div class="oz-cart-skeleton-item">
                <div class="oz-cart-skeleton-img"></div>
                <div class="oz-cart-skeleton-lines">
                    <div class="oz-cart-skeleton-line"></div>
                    <div class="oz-cart-skeleton-line"></div>
                    <div class="oz-cart-skeleton-line"></div>
                </div>
            </div>
        </div>

        <!-- Cart items — populated by JS -->
        <div class="oz-cart-items" id="ozCartItems"></div>

        <!-- Empty cart state -->
        <div class="oz-cart-empty" id="ozCartEmpty" style="display:none">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <div class="oz-cart-empty-title">Je winkelmand is leeg</div>
            <div class="oz-cart-empty-text">Voeg producten toe om te beginnen met je beton ciré project.</div>
            <button class="oz-cart-empty-btn" id="ozEmptyShopBtn">Verder winkelen</button>
        </div>
    </div>

    <!-- Upsell section — own scrollable region -->
    <div class="oz-drawer-upsells" id="ozUpsellSection" style="display:none">
        <div class="oz-drawer-upsells-title">Vakmannen bestellen ook</div>
        <div class="oz-drawer-upsell-list" id="ozUpsellList"></div>
    </div>

    <!-- Sticky footer -->
    <div class="oz-drawer-footer" id="ozDrawerFooter">
        <div class="oz-drawer-footer-row">
            <span class="oz-drawer-footer-label">Verzending</span>
            <span class="oz-drawer-footer-value">Berekend bij afrekenen</span>
        </div>
        <div class="oz-drawer-footer-row subtotal">
            <span class="oz-drawer-footer-label">Subtotaal <small style="font-weight:400;font-size:11px;color:var(--oz-text-muted,#999)">(incl. BTW)</small></span>
            <span class="oz-drawer-footer-value" id="ozFooterSubtotal" aria-live="polite">&euro;0,00</span>
        </div>
        <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="oz-checkout-btn" id="ozCheckoutBtn">Doorgaan naar afrekenen</a>

        <?php if (!empty($oz_drawer_icons)) : ?>
        <div class="oz-drawer-payment-icons">
            <?php foreach ($oz_drawer_icons as $icon) : ?>
                <div class="oz-drawer-payment-icon"><?php echo $icon; ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>
