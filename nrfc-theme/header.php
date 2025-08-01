<!DOCTYPE html>
<html <?php language_attributes(); ?> lang="enGB">
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php wp_title(); ?></title>
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<header>
  <div class="site-branding">
    <h1><a href="<?php echo esc_url(home_url('/')); ?>">Norwich Rugby Club</a></h1>
  </div>
    <?php
    $sponsors = new WP_Query([
        'post_type' => 'sponsor',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    if ($sponsors->have_posts()) : ?>
        <div class="header-sponsors">
            <?php while ($sponsors->have_posts()) :
                $sponsors->the_post(); ?>
                <div class="sponsor-logo">
                    <?php the_post_thumbnail('medium'); ?>
                </div>
                <?php
                $sponsor_link = get_post_meta(get_the_ID(), '_sponsor_link', true);
                if (!empty($sponsor_link)) :
                    ?>
                    <p><strong>Sponsor:</strong>
                        <a href="<?php echo esc_url($sponsor_link); ?>" target="_blank">View Sponsor</a></p>
                <?php endif; ?>
            <?php endwhile; ?>
            <?php wp_reset_postdata(); ?>
        </div>
    <?php endif; ?>
  <nav>
    <?php wp_nav_menu(array('theme_location' => 'primary')); ?>
  </nav>
</header>
