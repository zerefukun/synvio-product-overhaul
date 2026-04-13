<?php
/**
 * Custom footer — replaces Flatsome's footer.php entirely.
 * Closes <main id="main"> and <div id="wrapper"> opened by header.php.
 *
 * @package OzTheme
 */

$site_name = get_bloginfo( 'name' );
?>

</main>

<footer class="oz-footer" id="oz-footer">

	<!-- Trust bar — same USPs as old Flatsome footer -->
	<div class="oz-footer__trust">
		<div class="oz-footer__trust-inner">
			<div class="oz-footer__trust-item">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/></svg>
				<span>Voor 14:00 besteld, dezelfde werkdag verzonden</span>
			</div>
			<div class="oz-footer__trust-item">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
				<span>Altijd een specialist beschikbaar</span>
			</div>
			<div class="oz-footer__trust-item">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
				<span>Project ondersteuning</span>
			</div>
			<div class="oz-footer__trust-item">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><path d="M3.27 6.96L12 12.01l8.73-5.05"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
				<span>Gratis verzending vanaf &euro;100</span>
			</div>
		</div>
	</div>

	<!-- Main footer columns -->
	<div class="oz-footer__inner">

		<!-- Column 1: brand + contact details -->
		<div class="oz-footer__col oz-footer__brand">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="oz-footer__logo">
				<?php
				$logo_id  = get_theme_mod( 'site_logo' ) ?: get_theme_mod( 'custom_logo' );
				$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
				if ( $logo_url ) : ?>
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="140" height="auto" loading="lazy">
				<?php else : ?>
					<span><?php echo esc_html( $site_name ); ?></span>
				<?php endif; ?>
			</a>
			<p class="oz-footer__tagline">Naadloze betonlook. Zelf aangebracht.</p>
			<div class="oz-footer__contact">
				<p><a href="https://www.google.com/maps/place/Beton+cire+webshop/" target="_blank" rel="noopener">Laan van 's-Gravenmade 42L</a><br>2495 AJ Den Haag, Nederland</p>
				<p>Bezoek alleen op afspraak</p>
				<p>
					<a href="mailto:info@beton-cire-webshop.nl">info@beton-cire-webshop.nl</a><br>
					<a href="tel:0850270090">085 - 027 00 90</a>
				</p>
				<p class="oz-footer__legal">KVK: 83646248 &middot; BTW: NL862945811 B01</p>
			</div>
		</div>

		<!-- Column 2: quick links -->
		<div class="oz-footer__col">
			<h4 class="oz-footer__heading">Producten</h4>
			<ul class="oz-footer__links">
				<li><a href="/product-categorie/microcement/">Microcement</a></li>
				<li><a href="/beton-cire-all-in-one-easyline-standaard-kleuren/">All-In-One</a></li>
				<li><a href="/beton-cire-original-kleuren/">Original</a></li>
				<li><a href="/product-categorie/lavasteen/">Lavasteen</a></li>
				<li><a href="/product-categorie/primer/">Primer</a></li>
				<li><a href="/product-categorie/pu-coating/">PU Coating</a></li>
				<li><a href="/kleurstalen/">Kleurstalen</a></li>
			</ul>
		</div>

		<!-- Column 3: info links -->
		<div class="oz-footer__col">
			<h4 class="oz-footer__heading">Informatie</h4>
			<ul class="oz-footer__links">
				<li><a href="/kennisbank/">Kennisbank</a></li>
				<li><a href="/inspiratie/">Inspiratie</a></li>
				<li><a href="/beton-cire-showroom/">Showroom</a></li>
				<li><a href="/contact/">Contact</a></li>
				<li><a href="/veelgestelde-vragen/">FAQ</a></li>
			</ul>
		</div>

		<!-- Column 4: klantenservice -->
		<div class="oz-footer__col">
			<h4 class="oz-footer__heading">Klantenservice</h4>
			<ul class="oz-footer__links">
				<li><a href="/mijn-account/">Mijn account</a></li>
				<li><a href="/mijn-account/orders/">Bestellingen</a></li>
				<li><a href="/retourneren/">Retourneren</a></li>
				<li><a href="/verzending/">Verzending</a></li>
				<li><a href="/algemene-voorwaarden/">Algemene voorwaarden</a></li>
				<li><a href="/privacybeleid/">Privacybeleid</a></li>
			</ul>
		</div>

	</div>

	<!-- Bottom bar -->
	<div class="oz-footer__bottom">
		<p>&copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $site_name ); ?>. Alle rechten voorbehouden.</p>
	</div>
</footer>

</div><!-- #wrapper -->

<?php wp_footer(); ?>

</body>
</html>
