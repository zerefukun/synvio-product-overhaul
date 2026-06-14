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
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
				<span>Showroom in Den Haag</span>
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
					<img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $site_name ); ?>" width="140" height="140" loading="lazy">
				<?php else : ?>
					<span><?php echo esc_html( $site_name ); ?></span>
				<?php endif; ?>
			</a>
			<p class="oz-footer__tagline">Naadloze betonlook. Zelf aangebracht.</p>
			<div class="oz-footer__contact">
				<p><a href="https://www.google.com/maps/place/Beton+cire+webshop/" target="_blank" rel="noopener">Laan van 's-Gravenmade 42L</a><br>2495 AJ Den Haag, Nederland</p>
				<p>Bezoek alleen op afspraak</p>
				<p>
					<?php /* antispambot(): entity-encoding tegen scrape-bots, geen JS nodig
					   (vervangt Cloudflare Email Obfuscation die HTML herschreef). */ ?>
					<a href="mailto:<?php echo esc_attr( antispambot( 'info@beton-cire-webshop.nl' ) ); ?>"><?php echo antispambot( 'info@beton-cire-webshop.nl' ); ?></a><br>
					<a href="tel:+31850270090">085 - 027 00 90</a><br><a href="https://wa.me/31648926279" target="_blank" rel="noopener" aria-label="WhatsApp 06 48 92 62 79" title="WhatsApp ons" style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:9999px;background:rgba(37,211,102,0.12);color:#25D366;font-size:12px;font-weight:500;line-height:1;text-decoration:none;margin-top:6px;transition:background-color .15s;" onmouseover="this.style.background=>085 - 027 00 90</a>apos;rgba(37,211,102,0.22)>085 - 027 00 90</a>apos;" onmouseout="this.style.background=>085 - 027 00 90</a>apos;rgba(37,211,102,0.12)>085 - 027 00 90</a>apos;"><svg width="13" height="13" viewBox="0 0 24 24" fill="#25D366" aria-hidden="true"><path d="M20.52 3.449C12.831-3.984.106 1.407.101 11.893c0 2.096.549 4.14 1.595 5.945L0 24l6.335-1.652a11.875 11.875 0 005.667 1.443h.005c10.846 0 16.243-13.083 8.515-20.34zm-8.515 18.297h-.005a9.87 9.87 0 01-5.027-1.378l-.36-.214-3.733.978.996-3.638-.235-.374a9.861 9.861 0 01-1.511-5.27c.001-8.747 10.677-13.119 16.875-6.92 6.18 6.123 1.819 16.815-6.999 16.815zm5.422-7.403c-.296-.149-1.758-.868-2.031-.967-.273-.099-.471-.148-.67.15-.197.297-.767.967-.94 1.165-.173.198-.347.223-.644.074-.297-.148-1.254-.462-2.387-1.473-.883-.787-1.478-1.76-1.651-2.057-.173-.296-.018-.456.13-.604.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>WhatsApp</a>
				</p>
				<p class="oz-footer__legal">KVK: 83646248 &middot; BTW: NL862945811 B01</p>
			<style>
				.oz-footer__social { display: flex; flex-wrap: wrap; gap: 0.55rem; margin-top: 1.25rem; }
				.oz-footer__social-link { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 50%; border: 1px solid rgba(255,255,255,0.22); color: rgba(255,255,255,0.85); transition: background-color .2s, color .2s, border-color .2s; }
				.oz-footer__social-link:hover { background: rgba(255,255,255,0.10); color: #fff; border-color: rgba(255,255,255,0.45); }
				.oz-footer__social-link svg { display: block; }
			</style>
			<div class="oz-footer__social" aria-label="Volg ons">
				<a class="oz-footer__social-link" href="https://www.instagram.com/betoncirewebshop/" target="_blank" rel="noopener" aria-label="Instagram"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></a>
				<a class="oz-footer__social-link" href="https://www.facebook.com/betoncirewebshop/" target="_blank" rel="noopener" aria-label="Facebook"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13 22v-8h2.59l.41-3H13V9.13c0-.86.24-1.45 1.49-1.45H16V5c-.29-.04-1.21-.12-2.27-.12-2.24 0-3.78 1.37-3.78 3.88V11H8v3h2v8h3z"/></svg></a>
				<a class="oz-footer__social-link" href="https://nl.pinterest.com/betoncirewebshop/" target="_blank" rel="noopener" aria-label="Pinterest"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-3.65 19.31c-.09-.78-.17-1.99.04-2.85.19-.78 1.21-4.98 1.21-4.98s-.31-.62-.31-1.53c0-1.43.83-2.5 1.87-2.5.88 0 1.31.66 1.31 1.46 0 .89-.57 2.22-.86 3.45-.24 1.03.52 1.87 1.53 1.87 1.84 0 3.25-1.94 3.25-4.74 0-2.48-1.78-4.21-4.33-4.21-2.95 0-4.69 2.21-4.69 4.5 0 .89.34 1.85.77 2.36.09.1.1.19.07.3-.08.32-.25 1.03-.29 1.17-.05.19-.15.23-.34.14-1.28-.6-2.08-2.46-2.08-3.96 0-3.22 2.35-6.18 6.78-6.18 3.56 0 6.32 2.53 6.32 5.92 0 3.53-2.23 6.37-5.32 6.37-1.04 0-2.02-.54-2.36-1.18l-.63 2.43c-.23.89-.85 2-1.27 2.68A10 10 0 1012 2z"/></svg></a>
				<a class="oz-footer__social-link" href="https://www.youtube.com/@Betoncirewebshop" target="_blank" rel="noopener" aria-label="YouTube"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.5 6.5a3 3 0 00-2.1-2.1C19.6 4 12 4 12 4s-7.6 0-9.4.4A3 3 0 00.5 6.5C0 8.3 0 12 0 12s0 3.7.5 5.5a3 3 0 002.1 2.1C4.4 20 12 20 12 20s7.6 0 9.4-.4a3 3 0 002.1-2.1C24 15.7 24 12 24 12s0-3.7-.5-5.5zM9.6 15.5v-7l6.4 3.5-6.4 3.5z"/></svg></a>
				<a class="oz-footer__social-link" href="https://www.tiktok.com/@betoncirewebshop.nl" target="_blank" rel="noopener" aria-label="TikTok"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-5.2 1.74 2.89 2.89 0 012.31-4.64 2.93 2.93 0 01.88.13V9.4a6.84 6.84 0 00-1-.05A6.33 6.33 0 005.8 20.1a6.34 6.34 0 0010.86-4.43V8.94a8.16 8.16 0 004.77 1.52V7.01a4.85 4.85 0 01-1.84-.32z"/></svg></a>
				<a class="oz-footer__social-link" href="https://www.linkedin.com/in/beton-cire-webshop-79916840b/" target="_blank" rel="noopener" aria-label="LinkedIn"><svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.45 20.45h-3.55v-5.57c0-1.33-.03-3.04-1.85-3.04-1.85 0-2.13 1.45-2.13 2.94v5.67H9.36V9h3.41v1.56h.05a3.74 3.74 0 013.37-1.85c3.6 0 4.27 2.37 4.27 5.46v6.28zM5.34 7.43a2.06 2.06 0 11.001-4.12 2.06 2.06 0 010 4.12zM7.12 20.45H3.56V9h3.56v11.45zM22.22 0H1.77C.79 0 0 .77 0 1.72v20.56C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.72V1.72C24 .77 23.2 0 22.22 0z"/></svg></a>
			</div>
			</div>
		</div>

		<?php
		$oz_chevron = '<svg class="oz-footer__chevron" width="10" height="8" viewBox="0 0 10 8" fill="none" aria-hidden="true"><path d="m2 2 3 3 3-3" stroke="currentColor" stroke-width="1.5"/></svg>';
		?>

		<!-- Column 2: Producten -->
		<details class="oz-footer__col oz-footer__accordion" open>
			<summary class="oz-footer__heading">Producten<?php echo $oz_chevron; ?></summary>
			<ul class="oz-footer__links">
				<li><a href="/beton-cire-all-in-one/">All-In-One</a></li>
				<li><a href="/beton-cire-easyline-kant-en-klaar/">Easyline</a></li>
				<li><a href="/beton-cire-original/">Beton Ciré Original</a></li>
				<li><a href="/metallic-stuc/">Metallic Velvet</a></li>
				<li><a href="/lavasteen-gietvloer/">Lavasteen</a></li>
				<li><a href="/product-categorie/pu-color/">PU Coating</a></li>
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

	<!-- Payment icons strip — sitewide, replaces Flatsome's flatsome_footer_payment_icons -->
	<?php if ( function_exists( 'oz_payment_icons_strip' ) ) oz_payment_icons_strip( 'footer' ); ?>

	<!-- Bottom bar -->
	<div class="oz-footer__bottom">
		<p>&copy; <?php echo date( 'Y' ); ?> <?php echo esc_html( $site_name ); ?>. Alle rechten voorbehouden.</p>
	</div>
</footer>

</div><!-- #wrapper -->

<?php wp_footer(); ?>

</body>
</html>
