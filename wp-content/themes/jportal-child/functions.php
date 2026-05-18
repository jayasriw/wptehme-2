<?php
add_action('wp_enqueue_scripts', function(){ wp_enqueue_style('jportal-child', get_stylesheet_uri(), array('jportal-theme'), '1.0.0'); });
