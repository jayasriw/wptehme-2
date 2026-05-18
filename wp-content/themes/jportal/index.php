<?php get_header(); ?>
<main class="jp-container jp-content">
  <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
    <article <?php post_class('jp-post-card'); ?>><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p><?php echo jportal_excerpt(30); ?></p></article>
  <?php endwhile; the_posts_pagination(); else : ?>
    <section class="jp-empty"><h1>Nothing found</h1><p>Try a different search or browse available jobs.</p></section>
  <?php endif; ?>
</main>
<?php get_footer(); ?>
