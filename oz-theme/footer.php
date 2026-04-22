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

	<!-- Trust bar -->
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

	<!-- Badges row: Google reviews + Webshop keurmerk -->
	<div class="oz-footer__badges">
		<a class="oz-footer__badge oz-footer__badge--reviews" href="https://www.google.com/maps/place/Beton+cire+webshop/" target="_blank" rel="noopener" aria-label="4.8/5.0 Google reviews">
			<span class="oz-footer__badge-stars" aria-hidden="true">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
			</span>
			<span class="oz-footer__badge-text">
				<strong>4.8/5.0</strong>
				<em>Gebaseerd op 200+ Google reviews</em>
			</span>
		</a>
		<a class="oz-footer__badge oz-footer__badge--keurmerk" href="https://www.keurmerk.info/" target="_blank" rel="noopener" aria-label="Webshop Keurmerk">
			<img src="https://beton-cire-webshop.nl/wp-content/uploads/2024/01/webshop-keurmerk-jpg.webp" alt="Webshop Keurmerk" width="120" height="auto" loading="lazy">
		</a>
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
			<p class="oz-footer__trilogy">
				<a href="/beton-cire-easyline-all-in-one/">Beton Ciré All-In-One</a>
				<span class="oz-footer__trilogy-sep">&middot;</span>
				<a href="/beton-cire-easyline-kant-en-klaar/">Easyline</a>
				<span class="oz-footer__trilogy-sep">&middot;</span>
				<a href="/beton-cire-original/">Beton Ciré Original</a>
			</p>
			<div class="oz-footer__contact">
				<p><a href="https://www.google.com/maps/place/Beton+cire+webshop/" target="_blank" rel="noopener">Laan van 's-Gravenmade 42L</a><br>2495 AJ Den Haag, Nederland</p>
				<p>Bezoek alleen op afspraak</p>
				<p>
					<a href="mailto:info@beton-cire-webshop.nl">info@beton-cire-webshop.nl</a><br>
					<a href="tel:+31850270090">085 - 027 00 90</a>
				</p>
				<p class="oz-footer__legal">KVK: 83646248 &middot; BTW: NL862945811 B01</p>
			</div>
		</div>

		<?php
		$oz_chevron = '<svg class="oz-footer__chevron" width="10" height="8" viewBox="0 0 10 8" fill="none" aria-hidden="true"><path d="m2 2 3 3 3-3" stroke="currentColor" stroke-width="1.5"/></svg>';
		?>

		<!-- Column 2: Producten -->
		<details class="oz-footer__col oz-footer__accordion" open>
			<summary class="oz-footer__heading">Producten<?php echo $oz_chevron; ?></summary>
			<ul class="oz-footer__links">
				<li><a href="/beton-cire-easyline-all-in-one/">All-In-One</a></li>
				<li><a href="/beton-cire-easyline-kant-en-klaar/">Easyline</a></li>
				<li><a href="/beton-cire-original/">Beton Ciré Original</a></li>
				<li><a href="/metallic-stuc/">Metallic Velvet</a></li>
				<li><a href="/lavasteen-gietvloer/">Lavasteen</a></li>
				<li><a href="/product-categorie/primer/">Primer</a></li>
				<li><a href="/product-categorie/pu-coating/">PU Coating</a></li>
			</ul>
		</details>

		<!-- Column 3: Navigatie -->
		<details class="oz-footer__col oz-footer__accordion" open>
			<summary class="oz-footer__heading">Navigatie<?php echo $oz_chevron; ?></summary>
			<ul class="oz-footer__links">
				<li><a href="/">Home</a></li>
				<li><a href="/producten/">Producten</a></li>
				<li><a href="/kennisbank/">Kennisbank</a></li>
				<li><a href="/kleuren/">Kleuren</a></li>
				<li><a href="/kleurstalen-aanvragen/">Kleurstalen aanvragen</a></li>
				<li><a href="/blog/">Blog / Nieuws</a></li>
				<li><a href="/sitemap/">Sitemap</a></li>
				<li><a href="/beton-cire-showroom/">Locatie</a></li>
			</ul>
		</details>

		<!-- Column 4: Klantenservice -->
		<details class="oz-footer__col oz-footer__accordion" open>
			<summary class="oz-footer__heading">Klantenservice<?php echo $oz_chevron; ?></summary>
			<ul class="oz-footer__links">
				<li><a href="/offerte/">Offerte aanvragen</a></li>
				<li><a href="/klantervaringen/">Klantervaringen</a></li>
				<li><a href="/veelgestelde-vragen/">Veelgestelde vragen</a></li>
				<li><a href="/contact/">Contact opnemen</a></li>
				<li><a href="/verzending/">Verzending &amp; Retourneren</a></li>
				<li><a href="/algemene-voorwaarden/">Algemene voorwaarden</a></li>
				<li><a href="/privacybeleid/">Privacy beleid</a></li>
			</ul>
		</details>

		<!-- Column 5: Openingstijden -->
		<details class="oz-footer__col oz-footer__accordion" open>
			<summary class="oz-footer__heading">Openingstijden<?php echo $oz_chevron; ?></summary>
			<table class="oz-footer__hours">
				<tbody>
					<tr><th>Maandag</th><td>09:00 &ndash; 15:00</td></tr>
					<tr><th>Dinsdag</th><td>09:00 &ndash; 15:00</td></tr>
					<tr><th>Woensdag</th><td>09:00 &ndash; 15:00</td></tr>
					<tr><th>Donderdag</th><td>09:00 &ndash; 15:00</td></tr>
					<tr><th>Vrijdag</th><td>09:00 &ndash; 15:00</td></tr>
					<tr><th>Zaterdag</th><td>11:00 &ndash; 13:00</td></tr>
					<tr><th>Zondag</th><td><span class="oz-footer__closed">Gesloten</span></td></tr>
				</tbody>
			</table>
		</details>

	</div>

	<script>
	if (window.matchMedia('(max-width: 800px)').matches) {
		document.querySelectorAll('.oz-footer__accordion[open]').forEach(function(d){ d.removeAttribute('open'); });
	}
	</script>

	<!-- Bottom bar -->
	<div class="oz-footer__bottom">
		<p class="oz-footer__co2">Ons bedrijf is CO<sub>2</sub> neutraal en we recyclen zo veel mogelijk.</p>
		<p>&copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $site_name ); ?>. Alle rechten voorbehouden.</p>
	</div>
</footer>

</div><!-- #wrapper -->

<?php wp_footer(); ?>

</body>
</html>
