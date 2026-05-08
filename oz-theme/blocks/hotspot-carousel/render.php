<?php
/**
 * Server-side render for the oz/hotspot-carousel block.
 *
 * Receives $attributes (from block.json schema) and outputs the frontend
 * carousel HTML. Each project is a slide; each project has an image with
 * absolute-positioned hotspots overlaid at saved x/y percentages.
 *
 * @var array  $attributes Block attributes (eyebrow, title, projects[])
 * @var string $content    Inner content (unused — block has no InnerBlocks)
 * @var WP_Block $block    Block instance
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$eyebrow  = isset( $attributes['eyebrow'] )  ? (string) $attributes['eyebrow']  : '';
$title    = isset( $attributes['title'] )    ? (string) $attributes['title']    : '';
$projects = isset( $attributes['projects'] ) && is_array( $attributes['projects'] ) ? $attributes['projects'] : array();

if ( empty( $projects ) ) {
	// Editor placeholder is handled in JS; don't emit anything in front-end if no projects.
	return '';
}

$wrap = get_block_wrapper_attributes( array(
	'class' => 'oz-hotspot-carousel',
) );
?>
<section <?php echo $wrap; ?>>
	<?php if ( $eyebrow || $title ) : ?>
		<header class="oz-hotspot-carousel__header">
			<?php if ( $eyebrow ) : ?>
				<div class="oz-hp-eyebrow"><?php echo esc_html( $eyebrow ); ?></div>
			<?php endif; ?>
			<?php if ( $title ) : ?>
				<h2 class="oz-hotspot-carousel__title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>
		</header>
	<?php endif; ?>

	<div class="oz-hotspot-carousel__viewport" data-hotspot-carousel>
		<button type="button" class="oz-hotspot-carousel__nav oz-hotspot-carousel__nav--prev" aria-label="<?php esc_attr_e( 'Vorige', 'oz-theme' ); ?>" data-direction="prev">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
		</button>
		<button type="button" class="oz-hotspot-carousel__nav oz-hotspot-carousel__nav--next" aria-label="<?php esc_attr_e( 'Volgende', 'oz-theme' ); ?>" data-direction="next">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
		</button>
		<div class="oz-hotspot-carousel__track">
		<?php foreach ( $projects as $i => $project ) :
			$p_title  = isset( $project['title'] )    ? (string) $project['title']    : '';
			$p_imgUrl = isset( $project['imageUrl'] ) ? (string) $project['imageUrl'] : '';
			$p_imgId  = isset( $project['imageId'] )  ? (int)    $project['imageId']  : 0;
			$hotspots = isset( $project['hotspots'] ) && is_array( $project['hotspots'] ) ? $project['hotspots'] : array();
			if ( ! $p_imgUrl && $p_imgId ) {
				$p_imgUrl = wp_get_attachment_image_url( $p_imgId, 'large' );
			}
			if ( ! $p_imgUrl ) continue;
		?>
			<figure class="oz-hotspot-carousel__slide" data-slide-index="<?php echo (int) $i; ?>">
				<div class="oz-hotspot-carousel__image-wrap">
					<img class="oz-hotspot-carousel__image"
					     src="<?php echo esc_url( $p_imgUrl ); ?>"
					     alt="<?php echo esc_attr( $p_title ?: __( 'Inspiratie project', 'oz-theme' ) ); ?>"
					     loading="lazy">

					<?php foreach ( $hotspots as $j => $hs ) :
						$hx     = isset( $hs['x'] )          ? (float)  $hs['x']          : 50.0;
						$hy     = isset( $hs['y'] )          ? (float)  $hs['y']          : 50.0;
						$hlabel = isset( $hs['label'] )      ? (string) $hs['label']      : '';
						$hpid   = isset( $hs['productId'] )  ? (int)    $hs['productId']  : 0;
						$hurl   = isset( $hs['productUrl'] ) ? (string) $hs['productUrl'] : '';
						if ( ! $hurl && $hpid ) {
							$hurl = get_permalink( $hpid );
						}
						if ( ! $hurl ) continue;

						// Try to resolve URL → WC product so we can render a preview card.
						// Falls back to a plain link if the URL doesn't map to a product
						// (external link, archived product, etc.).
						$resolved_pid = $hpid ?: ( function_exists( 'url_to_postid' ) ? url_to_postid( $hurl ) : 0 );
						$resolved_product = ( $resolved_pid && function_exists( 'wc_get_product' ) ) ? wc_get_product( $resolved_pid ) : null;
					?>
						<div class="oz-hotspot-wrap" style="left:<?php echo esc_attr( $hx ); ?>%;top:<?php echo esc_attr( $hy ); ?>%;">
							<button type="button" class="oz-hotspot"
							        data-hotspot-index="<?php echo (int) $j; ?>"
							        <?php if ( $resolved_product ) : ?>data-has-card="1"<?php endif; ?>
							        aria-label="<?php echo esc_attr( $hlabel ?: __( 'Bekijk product', 'oz-theme' ) ); ?>"
							        aria-expanded="false">
								<span class="oz-hotspot__dot" aria-hidden="true"></span>
								<?php if ( $hlabel ) : ?>
									<span class="oz-hotspot__tooltip"><?php echo esc_html( $hlabel ); ?></span>
								<?php endif; ?>
							</button>

							<?php if ( $resolved_product ) :
								$pimg_id  = $resolved_product->get_image_id();
								$pimg_url = $pimg_id ? wp_get_attachment_image_url( $pimg_id, 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
								$pname    = $resolved_product->get_name();
								$pprice   = $resolved_product->get_price_html();
								$purl     = get_permalink( $resolved_product->get_id() );
							?>
								<div class="oz-hotspot-card" role="dialog" aria-modal="false" aria-label="<?php echo esc_attr( $pname ); ?>" hidden>
									<button type="button" class="oz-hotspot-card__close" aria-label="<?php esc_attr_e( 'Sluiten', 'oz-theme' ); ?>">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
									</button>
									<div class="oz-hotspot-card__image">
										<img src="<?php echo esc_url( $pimg_url ); ?>" alt="" loading="lazy">
									</div>
									<div class="oz-hotspot-card__body">
										<?php if ( $hlabel ) : ?>
											<div class="oz-hotspot-card__eyebrow"><?php echo esc_html( $hlabel ); ?></div>
										<?php endif; ?>
										<div class="oz-hotspot-card__title"><?php echo esc_html( $pname ); ?></div>
										<div class="oz-hotspot-card__price"><?php echo wp_kses_post( $pprice ); ?></div>
										<a class="oz-hotspot-card__cta" href="<?php echo esc_url( $purl ); ?>">
											<?php esc_html_e( 'Bekijk product', 'oz-theme' ); ?>
											<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
										</a>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
				<?php if ( $p_title ) : ?>
					<figcaption class="oz-hotspot-carousel__caption"><?php echo esc_html( $p_title ); ?></figcaption>
				<?php endif; ?>
			</figure>
		<?php endforeach; ?>
		</div>
	</div>
	<div class="oz-hotspot-carousel__dots" role="tablist">
		<?php foreach ( $projects as $i => $p ) : ?>
			<button type="button" class="oz-hotspot-carousel__dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
			        data-dot-index="<?php echo (int) $i; ?>"
			        role="tab"
			        aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
			        aria-label="<?php printf( esc_attr__( 'Ga naar slide %d', 'oz-theme' ), $i + 1 ); ?>"></button>
		<?php endforeach; ?>
	</div>
</section>
