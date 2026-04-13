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
	<div class="oz-footer__inner">

		<!-- Column 1: brand + short description -->
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
		</div>

		<!-- Column 2: quick links -->
		<div class="oz-footer__col">
			<h4 class="oz-footer__heading">Producten</h4>
			<ul class="oz-footer__links">
				<li><a href="/product-categorie/microcement/">Microcement</a></li>
				<li><a href="/beton-cire-all-in-one-easyline-standaard-kleuren/">All-In-One</a></li>
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
				<li><a href="/showroom/">Showroom</a></li>
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
