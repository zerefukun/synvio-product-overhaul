<?php /* Template Name: Sitemap */ get_header(); ?>

<div class="sitemap">

    <h1 class="title">Sitemap</h1>

    <!-- WordPress Posts Loop -->

    <div class="sitemap-posts">

        <h2 class="subtitle">Berichten</h2>

        <ul>

            <?php
                $wpposts = new WP_Query(
                    array(
                        'post_type' => 'post', // slug of posts
                        'posts_per_page' => -1, // -1 shows all posts
                        'post_status' => 'publish', // only shows published posts
                        'orderby' => 'title', // orders by post title
                        'order' => 'ASC' // orders post title alphabetically
                    )
                );
            ?>

            <?php while ( $wpposts->have_posts() ) : $wpposts->the_post(); ?>

            <li>
                <a href="<?php echo get_permalink($post->ID); ?>" rel="dofollow" title="<?php the_title(); ?>">
                    <?php the_title(); ?>
                </a>
            </li>

            <?php endwhile; wp_reset_query(); ?>

        </ul>

    </div><!-- sitemap-posts -->

    <!-- WordPress Pages Loop -->

    <div class="sitemap-pages">

        <h2 class="subtitle">Pagina's</h2>

        <ul>

            <?php
                $wppages = new WP_Query(
                    array(
                        'post_type' => 'page', // slug of pages
                        'posts_per_page' => -1, // -1 shows all pages
                        'post_status' => 'publish', // only shows published pages
                        'orderby' => 'title', // orders by page title
                        'order' => 'ASC' // orders page title alphabetically
                    )
                );
            ?>

            <?php while ( $wppages->have_posts() ) : $wppages->the_post(); ?>

            <li>
                <a href="<?php echo get_permalink($post->ID); ?>" rel="dofollow" title="<?php the_title(); ?>">
                    <?php the_title(); ?>
                </a>
            </li>

            <?php endwhile; wp_reset_query(); ?>

        </ul>

    </div><!-- sitemap-pages -->

    <!-- WordPress Categories Loop -->

    <div class="sitemap-categories">

        <h2 class="subtitle">Categorieën</h2>

        <ul>

            <?php
                $categories = get_categories(
                    array(
                        'orderby' => 'name', // orders by category name
                        'order' => 'ASC' // orders categories alphabetically
                    )
                );

                foreach ( $categories as $category ) {
                    echo '<li><a href="' . get_category_link( $category->term_id ) . '" rel="dofollow" title="' . esc_attr( $category->name ) . '">' . $category->name . '</a></li>';
                }
            ?>

        </ul>

    </div><!-- sitemap-categories -->    
    
    

     <div class="sitemap-pages">
        <h2 class="subtitle">XML Sitemap</h2>
       <a href="/sitemap_index.xml">Sitemap XML</a>  
</div>





</div><!-- sitemap -->

<?php get_footer(); ?>