<?php
/**
 * 404 template — page not found.
 *
 * @package OzTheme
 */

get_header(); ?>

<div class="oz-404 oz-container">
	<div class="oz-404__inner">
		<h1 class="oz-404__title">404</h1>
		<p class="oz-404__text">Deze pagina bestaat niet of is verplaatst. Geen probleem &mdash; je vindt waarschijnlijk wat je zocht hieronder.</p>

		<div class="oz-hp-tip" style="text-align:left;max-width:560px;margin:24px auto 28px;">
			<strong>Veel bezocht:</strong>
			<ul style="margin:10px 0 0 0;padding-left:18px;line-height:1.9;">
				<li><a href="<?php echo esc_url( home_url( "/beton-cire-original/" ) ); ?>">Beton Cir&eacute; Original</a> &mdash; klassieke ambachtelijke betonlook</li>
				<li><a href="<?php echo esc_url( home_url( "/microcement/" ) ); ?>">Microcement</a> &mdash; strakke moderne afwerking</li>
				<li><a href="<?php echo esc_url( home_url( "/lavasteen-gietvloer/" ) ); ?>">Lavasteen Gietvloer</a> &mdash; waterdichte vloer voor natte cellen</li>
				<li><a href="<?php echo esc_url( home_url( "/kleurstalen-aanvragen/" ) ); ?>">Gratis kleurstalen aanvragen</a></li>
				<li><a href="<?php echo esc_url( home_url( "/kennisbank/" ) ); ?>">Kennisbank</a> &mdash; handleidingen, advies en veelgestelde vragen</li>
			</ul>
		</div>

		<div class="oz-404__actions">
			<a href="<?php echo esc_url( home_url( "/" ) ); ?>" class="oz-btn oz-btn--primary">Naar de homepage</a>
			<a href="<?php echo esc_url( home_url( "/contact/" ) ); ?>" class="oz-btn oz-btn--outline">Contact &mdash; wij helpen je verder</a>
		</div>
	</div>
</div>

<?php get_footer(); ?>
