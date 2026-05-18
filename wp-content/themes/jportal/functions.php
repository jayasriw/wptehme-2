<?php
if (!defined('ABSPATH')) { exit; }

define('JPORTAL_THEME_VERSION', '1.0.0');

function jportal_setup() {
    load_theme_textdomain('jportal', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height'=>80,'width'=>260,'flex-width'=>true,'flex-height'=>true]);
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('automatic-feed-links');
    register_nav_menus(['primary'=>__('Primary Menu','jportal'), 'footer'=>__('Footer Menu','jportal')]);
}
add_action('after_setup_theme', 'jportal_setup');

function jportal_assets() {
    wp_enqueue_style('jportal-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap', [], null);
    wp_enqueue_style('jportal-theme', get_template_directory_uri() . '/assets/css/theme.css', [], JPORTAL_THEME_VERSION);
    wp_enqueue_script('jportal-theme', get_template_directory_uri() . '/assets/js/theme.js', ['jquery'], JPORTAL_THEME_VERSION, true);
}
add_action('wp_enqueue_scripts', 'jportal_assets');

function jportal_body_open() { if (function_exists('wp_body_open')) { wp_body_open(); } }

function jportal_is_core_active() { return class_exists('JPortal_Core'); }

function jportal_render_core_notice() {
    if (!jportal_is_core_active()) {
        echo '<div class="jp-theme-notice"><strong>jPortal Core plugin required.</strong> Install and activate jPortal Core to enable jobs, dashboards, applications, messaging, plans, and analytics.</div>';
    }
}
add_action('wp_body_open', 'jportal_render_core_notice');

function jportal_excerpt($words = 24) { return esc_html(wp_trim_words(get_the_excerpt() ?: get_the_content(), $words)); }
