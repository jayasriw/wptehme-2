<footer class="jp-site-footer">
  <div class="jp-container jp-footer-grid">
    <div><h3>jPortal</h3><p>A premium job board and recruitment marketplace built for employers, candidates, recruiters, and agencies.</p></div>
    <nav><?php wp_nav_menu(array('theme_location'=>'footer','container'=>false,'fallback_cb'=>false)); ?></nav>
    <div><strong>Start hiring faster</strong><p>Post jobs, manage applications, message candidates, and grow your talent pipeline.</p></div>
  </div>
  <div class="jp-container jp-footer-bottom">Copyright <?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?>. All rights reserved.</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
