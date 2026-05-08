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

	<div class="oz-hotspot-carousel__track" data-hotspot-carousel>
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
					?>
						<a class="oz-hotspot"
						   href="<?php echo esc_url( $hurl ); ?>"
						   style="left:<?php echo esc_attr( $hx ); ?>%;top:<?php echo esc_attr( $hy ); ?>%;"
						   data-hotspot-index="<?php echo (int) $j; ?>"
						   aria-label="<?php echo esc_attr( $hlabel ?: __( 'Bekijk product', 'oz-theme' ) ); ?>">
							<span class="oz-hotspot__dot" aria-hidden="true"></span>
							<?php if ( $hlabel ) : ?>
								<span class="oz-hotspot__tooltip"><?php echo esc_html( $hlabel ); ?></span>
							<?php endif; ?>
						</a>
					<?php endforeach; ?>
				</div>
				<?php if ( $p_title ) : ?>
					<figcaption class="oz-hotspot-carousel__caption"><?php echo esc_html( $p_title ); ?></figcaption>
				<?php endif; ?>
			</figure>
		<?php endforeach; ?>
	</div>
</section>
