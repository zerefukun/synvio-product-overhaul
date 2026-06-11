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
				<div class="oz-hotspot-carousel__image-wrap" data-slide-stage>
					<img class="oz-hotspot-carousel__image"
					     src="<?php echo esc_url( $p_imgUrl ); ?>"
					     alt="<?php echo esc_attr( $p_title ?: __( 'Inspiratie project', 'oz-theme' ) ); ?>"
					     loading="lazy">

					<?php
					// First pass: render hotspot BUTTONS positioned at x/y.
					// Cards are rendered separately below, centered in the stage,
					// linked by data-hotspot-target / data-hotspot-id pairs.
					foreach ( $hotspots as $j => $hs ) :
						$hx     = isset( $hs['x'] )          ? (float)  $hs['x']          : 50.0;
						$hy     = isset( $hs['y'] )          ? (float)  $hs['y']          : 50.0;
						$hlabel = isset( $hs['label'] )      ? (string) $hs['label']      : '';
						$hpid   = isset( $hs['productId'] )  ? (int)    $hs['productId']  : 0;
						$hurl   = isset( $hs['productUrl'] ) ? (string) $hs['productUrl'] : '';
						if ( ! $hurl && $hpid ) {
							$hurl = get_permalink( $hpid );
						}
						if ( ! $hurl ) continue;
						$hs_id = 'hs-' . $i . '-' . $j;
					?>
						<button type="button" class="oz-hotspot"
						        data-hotspot-target="<?php echo esc_attr( $hs_id ); ?>"
						        style="left:<?php echo esc_attr( $hx ); ?>%;top:<?php echo esc_attr( $hy ); ?>%;"
						        aria-label="<?php echo esc_attr( $hlabel ?: __( 'Bekijk product', 'oz-theme' ) ); ?>"
						        aria-expanded="false">
							<svg class="oz-hotspot__icon" aria-hidden="true" focusable="false" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round">
								<path d="m46.69 10.34-10.55.07-25.8 25.8 17.45 17.45 25.8-25.8.07-10.55-6.97-6.97z"/>
								<circle cx="43.95" cy="20.05" r="3.53" fill="currentColor"/>
								<path d="M14.4 32.15 31.85 49.6"/>
							</svg>
						</button>
					<?php endforeach; ?>

					<?php
					// Second pass: render cards as siblings of the image, all centered
					// in the stage via CSS. JS toggles .is-open on the matched card
					// when its sibling hotspot button is clicked.
					foreach ( $hotspots as $j => $hs ) :
						$hlabel = isset( $hs['label'] )      ? (string) $hs['label']      : '';
						$hpid   = isset( $hs['productId'] )  ? (int)    $hs['productId']  : 0;
						$hurl   = isset( $hs['productUrl'] ) ? (string) $hs['productUrl'] : '';
						if ( ! $hurl && $hpid ) {
							$hurl = get_permalink( $hpid );
						}
						if ( ! $hurl ) continue;
						$resolved_pid = $hpid ?: ( function_exists( 'url_to_postid' ) ? url_to_postid( $hurl ) : 0 );
						$resolved_product = ( $resolved_pid && function_exists( 'wc_get_product' ) ) ? wc_get_product( $resolved_pid ) : null;
						if ( ! $resolved_product ) continue;
						$hs_id    = 'hs-' . $i . '-' . $j;
						$pimg_id  = $resolved_product->get_image_id();
						$pimg_url = $pimg_id ? wp_get_attachment_image_url( $pimg_id, 'medium' ) : wc_placeholder_img_src( 'medium' );
						$pname    = $resolved_product->get_name();
						$pprice   = $resolved_product->get_price_html();
						$purl     = get_permalink( $resolved_product->get_id() );
						$pcats    = wp_get_post_terms( $resolved_product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
						$peyebrow = $hlabel ?: ( ! empty( $pcats ) && ! is_wp_error( $pcats ) ? strtoupper( $pcats[0] ) : '' );
					?>
						<div class="oz-hotspot-card"
						     data-hotspot-id="<?php echo esc_attr( $hs_id ); ?>"
						     role="dialog" aria-modal="false" aria-label="<?php echo esc_attr( $pname ); ?>">
							<button type="button" class="oz-hotspot-card__close" aria-label="<?php esc_attr_e( 'Sluiten', 'oz-theme' ); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
							</button>
							<a href="<?php echo esc_url( $purl ); ?>" class="oz-hotspot-card__image-link">
								<div class="oz-hotspot-card__image">
									<img src="<?php echo esc_url( $pimg_url ); ?>" alt="<?php echo esc_attr( $pname ); ?>" loading="lazy">
								</div>
							</a>
							<div class="oz-hotspot-card__body">
								<?php if ( $peyebrow ) : ?>
									<div class="oz-hotspot-card__eyebrow"><?php echo esc_html( $peyebrow ); ?></div>
								<?php endif; ?>
								<a href="<?php echo esc_url( $purl ); ?>" class="oz-hotspot-card__title-link">
									<div class="oz-hotspot-card__title"><?php echo esc_html( $pname ); ?></div>
								</a>
								<div class="oz-hotspot-card__price"><?php echo wp_kses_post( $pprice ); ?></div>
							</div>
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
