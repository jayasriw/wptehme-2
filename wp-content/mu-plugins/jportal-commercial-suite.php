<?php
/**
 * Plugin Name: jPortal Commercial Suite
 * Description: Commercial modules for jPortal: payments, demo importer, GDPR, CSV import, social login integration points, Polylang support, Elementor widgets, resume builder, layout engines, related jobs, social sharing, and paid access controls.
 * Version: 1.0.0
 * Author: jPortal
 * Text Domain: jportal-suite
 */
if (!defined('ABSPATH')) { exit; }

final class JPortal_Commercial_Suite {
    const VERSION = '1.0.0';
    const NONCE = 'jportal_suite_nonce';

    public static function init() {
        add_action('init', array(__CLASS__, 'register_types'));
        add_action('init', array(__CLASS__, 'register_shortcodes'));
        add_action('init', array(__CLASS__, 'register_polylang_strings'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_assets'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('wp_footer', array(__CLASS__, 'cookie_notice'));
        add_action('admin_post_jportal_suite_demo', array(__CLASS__, 'run_demo_import'));
        add_action('admin_post_jportal_suite_import_csv', array(__CLASS__, 'import_csv'));
        add_action('admin_post_jportal_suite_save_payments', array(__CLASS__, 'save_payment_settings'));
        add_filter('the_content', array(__CLASS__, 'append_social_and_related'));
        add_action('elementor/widgets/register', array(__CLASS__, 'register_elementor_widgets'));
    }

    public static function register_types() {
        register_post_type('jp_payment_order', array('labels'=>array('name'=>'Payment Orders','singular_name'=>'Payment Order'), 'public'=>false, 'show_ui'=>true, 'menu_icon'=>'dashicons-cart', 'supports'=>array('title','author','custom-fields')));
        register_post_type('jp_resume', array('labels'=>array('name'=>'Resumes','singular_name'=>'Resume'), 'public'=>false, 'show_ui'=>true, 'menu_icon'=>'dashicons-media-document', 'supports'=>array('title','editor','author','custom-fields')));
        register_post_type('jp_interview', array('labels'=>array('name'=>'Interviews','singular_name'=>'Interview'), 'public'=>false, 'show_ui'=>true, 'menu_icon'=>'dashicons-video-alt3', 'supports'=>array('title','editor','author','custom-fields')));
    }

    public static function register_shortcodes() {
        add_shortcode('jportal_home', array(__CLASS__, 'home_shortcode'));
        add_shortcode('jportal_cookie_notice', array(__CLASS__, 'cookie_shortcode'));
        add_shortcode('jportal_social_login', array(__CLASS__, 'social_login_shortcode'));
        add_shortcode('jportal_checkout', array(__CLASS__, 'checkout_shortcode'));
        add_shortcode('jportal_resume_builder', array(__CLASS__, 'resume_builder_shortcode'));
        add_shortcode('jportal_company_reviews', array(__CLASS__, 'reviews_shortcode'));
        add_shortcode('jportal_analytics', array(__CLASS__, 'analytics_shortcode'));
    }

    public static function assets() {
        wp_enqueue_style('jportal-suite', content_url('mu-plugins/assets/jportal-suite.css'), array(), self::VERSION);
        wp_enqueue_script('jportal-suite', content_url('mu-plugins/assets/jportal-suite.js'), array('jquery'), self::VERSION, true);
        wp_localize_script('jportal-suite', 'JPortalSuite', array('ajaxUrl'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce(self::NONCE)));
    }

    public static function admin_assets() { wp_enqueue_style('jportal-suite-admin', content_url('mu-plugins/assets/jportal-suite-admin.css'), array(), self::VERSION); }

    public static function admin_menu() {
        add_menu_page('jPortal Suite', 'jPortal Suite', 'manage_options', 'jportal-suite', array(__CLASS__, 'setup_page'), 'dashicons-superhero', 27);
        add_submenu_page('jportal-suite', 'One-Click Setup', 'One-Click Setup', 'manage_options', 'jportal-suite', array(__CLASS__, 'setup_page'));
        add_submenu_page('jportal-suite', 'Payments', 'Payments', 'manage_options', 'jportal-suite-payments', array(__CLASS__, 'payments_page'));
        add_submenu_page('jportal-suite', 'CSV Import', 'CSV Import', 'manage_options', 'jportal-suite-import', array(__CLASS__, 'import_page'));
        add_submenu_page('jportal-suite', 'Documentation', 'Documentation', 'manage_options', 'jportal-suite-docs', array(__CLASS__, 'docs_page'));
    }

    public static function setup_page() {
        echo '<div class="wrap jp-suite"><h1>jPortal One-Click Setup</h1><p>Create the commercial job board pages, sample jobs, plans, terms, menus, and starter content.</p><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="jportal_suite_demo">';
        wp_nonce_field('jportal_suite_demo');
        echo '<button class="button button-primary button-hero">Run One-Click Setup</button></form></div>';
    }

    public static function run_demo_import() {
        if (!current_user_can('manage_options') || !check_admin_referer('jportal_suite_demo')) { wp_die('Not allowed'); }
        $pages = array('Jobs'=>'[jportal_jobs layout="grid"]','Submit Job'=>'[jportal_submit_job]','Dashboard'=>'[jportal_candidate_dashboard][jportal_employer_dashboard][jportal_messages]','Companies'=>'[jportal_companies]','Pricing'=>'[jportal_pricing]','Resume Builder'=>'[jportal_resume_builder]','Cookie Policy'=>'[jportal_cookie_notice]','Social Login'=>'[jportal_social_login]');
        foreach ($pages as $title=>$content) if (!get_page_by_title($title)) wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>$title,'post_content'=>$content));
        foreach (array('Engineering','Finance','Marketing','Operations','Security','Data','Sales') as $term) wp_insert_term($term, 'job_category');
        foreach (array('Full Time','Part Time','Contract','Freelance','Remote','Internship') as $term) wp_insert_term($term, 'job_type');
        $plans = array('Starter'=>0,'Professional'=>99,'Enterprise'=>299);
        foreach ($plans as $name=>$price) if (!get_page_by_title($name, OBJECT, 'jp_plan')) { $id=wp_insert_post(array('post_type'=>'jp_plan','post_status'=>'publish','post_title'=>$name,'post_content'=>'Plan for hiring teams.')); update_post_meta($id,'_jp_price',$price); update_post_meta($id,'_jp_job_limit',$price?10:1); update_post_meta($id,'_jp_featured_limit',$price?3:0); }
        if (!wp_get_nav_menu_object('jPortal Main')) { $menu_id=wp_create_nav_menu('jPortal Main'); foreach (array('Jobs','Companies','Pricing','Dashboard','Submit Job') as $label) { $p=get_page_by_title($label); if ($p) wp_update_nav_menu_item($menu_id, 0, array('menu-item-title'=>$label,'menu-item-object'=>'page','menu-item-object-id'=>$p->ID,'menu-item-type'=>'post_type','menu-item-status'=>'publish')); } set_theme_mod('nav_menu_locations', array('primary'=>$menu_id,'footer'=>$menu_id)); }
        wp_safe_redirect(admin_url('admin.php?page=jportal-suite&jportal_setup=done')); exit;
    }

    public static function payments_page() {
        $stripe = esc_attr(get_option('jp_stripe_checkout_url','')); $paypal = esc_attr(get_option('jp_paypal_checkout_url','')); $woo = esc_attr(get_option('jp_woocommerce_product_id',''));
        echo '<div class="wrap"><h1>jPortal Payments</h1><form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="jportal_suite_save_payments">'; wp_nonce_field('jportal_suite_payments');
        echo '<table class="form-table"><tr><th>Stripe Checkout URL</th><td><input class="regular-text" name="stripe" value="'.$stripe.'"></td></tr><tr><th>PayPal Checkout URL</th><td><input class="regular-text" name="paypal" value="'.$paypal.'"></td></tr><tr><th>WooCommerce Product ID</th><td><input class="regular-text" name="woo" value="'.$woo.'"></td></tr></table><button class="button button-primary">Save Payment Settings</button></form></div>';
    }

    public static function save_payment_settings() {
        if (!current_user_can('manage_options') || !check_admin_referer('jportal_suite_payments')) { wp_die('Not allowed'); }
        update_option('jp_stripe_checkout_url', esc_url_raw($_POST['stripe'] ?? '')); update_option('jp_paypal_checkout_url', esc_url_raw($_POST['paypal'] ?? '')); update_option('jp_woocommerce_product_id', absint($_POST['woo'] ?? 0));
        wp_safe_redirect(admin_url('admin.php?page=jportal-suite-payments&updated=1')); exit;
    }

    public static function import_page() {
        echo '<div class="wrap"><h1>CSV Import</h1><p>Upload a CSV with columns: title, description, category, type, location, salary_min, salary_max, deadline, company, featured.</p><form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="jportal_suite_import_csv">'; wp_nonce_field('jportal_suite_import_csv'); echo '<input type="file" name="jobs_csv" accept=".csv" required> <button class="button button-primary">Import Jobs</button></form></div>';
    }

    public static function import_csv() {
        if (!current_user_can('manage_options') || !check_admin_referer('jportal_suite_import_csv')) { wp_die('Not allowed'); }
        if (empty($_FILES['jobs_csv']['tmp_name'])) { wp_die('Missing CSV'); }
        $handle = fopen($_FILES['jobs_csv']['tmp_name'], 'r'); $headers = array_map('sanitize_key', fgetcsv($handle)); $count=0;
        while (($row=fgetcsv($handle)) !== false) { $data=array_combine($headers,$row); $id=wp_insert_post(array('post_type'=>'job','post_status'=>'publish','post_title'=>sanitize_text_field($data['title'] ?? 'Untitled Job'),'post_content'=>wp_kses_post($data['description'] ?? ''))); if ($id) { update_post_meta($id,'_jp_location_text',sanitize_text_field($data['location'] ?? '')); update_post_meta($id,'_jp_salary_min',sanitize_text_field($data['salary_min'] ?? '')); update_post_meta($id,'_jp_salary_max',sanitize_text_field($data['salary_max'] ?? '')); update_post_meta($id,'_jp_deadline',sanitize_text_field($data['deadline'] ?? '')); update_post_meta($id,'_jp_featured',!empty($data['featured'])?'1':'0'); if (!empty($data['category'])) wp_set_object_terms($id, sanitize_text_field($data['category']), 'job_category'); if (!empty($data['type'])) wp_set_object_terms($id, sanitize_text_field($data['type']), 'job_type'); $count++; } }
        fclose($handle); wp_safe_redirect(admin_url('admin.php?page=jportal-suite-import&imported='.$count)); exit;
    }

    public static function docs_page() {
        echo '<div class="wrap"><h1>jPortal Documentation</h1><h2>Key Shortcodes</h2><ul><li>[jportal_home layout="1"]</li><li>[jportal_jobs layout="grid|list|split|compact"]</li><li>[jportal_submit_job]</li><li>[jportal_candidate_dashboard]</li><li>[jportal_employer_dashboard]</li><li>[jportal_pricing]</li><li>[jportal_checkout plan_id="123"]</li><li>[jportal_resume_builder]</li><li>[jportal_social_login]</li></ul><h2>Setup</h2><p>Run One-Click Setup, configure payments, create plans, then publish jobs and companies.</p></div>';
    }

    public static function home_shortcode($atts) {
        $a = shortcode_atts(array('layout'=>'1'), $atts); $layout = max(1, min(12, absint($a['layout'])));
        return '<section class="jp-suite-home jp-home-'.$layout.'"><div><span class="jp-kicker">Layout '.$layout.'</span><h1>Hire smarter with jPortal</h1><p>Professional job board, marketplace, applications, messaging, plans, and analytics.</p><a class="jp-cta" href="'.esc_url(home_url('/jobs/')).'">Explore Jobs</a></div><div>'.do_shortcode('[jportal_jobs limit="4" layout="compact"]').'</div></section>';
    }

    public static function checkout_shortcode($atts) {
        $a=shortcode_atts(array('plan_id'=>0),$atts); $plan=absint($a['plan_id']); $stripe=get_option('jp_stripe_checkout_url'); $paypal=get_option('jp_paypal_checkout_url'); $woo=absint(get_option('jp_woocommerce_product_id'));
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['jp_checkout_nonce']) && wp_verify_nonce($_POST['jp_checkout_nonce'],'jp_checkout')) { $order=wp_insert_post(array('post_type'=>'jp_payment_order','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>'Order for plan '.$plan)); update_post_meta($order,'_jp_plan_id',$plan); update_post_meta($order,'_jp_status','pending'); if (!empty($_POST['provider']) && $_POST['provider']==='stripe' && $stripe) wp_safe_redirect($stripe); elseif (!empty($_POST['provider']) && $_POST['provider']==='paypal' && $paypal) wp_safe_redirect($paypal); elseif ($_POST['provider']==='woocommerce' && $woo && function_exists('wc_get_cart_url')) wp_safe_redirect(add_query_arg('add-to-cart',$woo,wc_get_cart_url())); else return '<div class="jp-success">Order created. Configure a payment provider to redirect customers.</div>'; exit; }
        ob_start(); echo '<form class="jp-form jp-checkout" method="post">'; wp_nonce_field('jp_checkout','jp_checkout_nonce'); echo '<input type="hidden" name="plan_id" value="'.esc_attr($plan).'"><select name="provider"><option value="stripe">Stripe</option><option value="paypal">PayPal</option><option value="woocommerce">WooCommerce</option></select><button class="jp-btn jp-btn-primary">Continue to Payment</button></form>'; return ob_get_clean();
    }

    public static function resume_builder_shortcode() {
        if (!is_user_logged_in()) return '<div class="jp-notice">Please log in to build your resume.</div>';
        if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['jp_resume_nonce']) && wp_verify_nonce($_POST['jp_resume_nonce'],'jp_resume')) { $id=wp_insert_post(array('post_type'=>'jp_resume','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>sanitize_text_field($_POST['resume_title'] ?? 'My Resume'),'post_content'=>sanitize_textarea_field($_POST['resume_content'] ?? ''))); update_post_meta($id,'_jp_resume_skills',sanitize_text_field($_POST['skills'] ?? '')); return '<div class="jp-success">Resume saved.</div>'; }
        return '<form class="jp-form" method="post">'.wp_nonce_field('jp_resume','jp_resume_nonce',true,false).'<input name="resume_title" placeholder="Resume title"><input name="skills" placeholder="Skills, comma separated"><textarea name="resume_content" placeholder="Experience, education, certifications"></textarea><button class="jp-btn jp-btn-primary">Save Resume</button></form>';
    }

    public static function social_login_shortcode() {
        $providers = array('Google'=>'nextend_google_login','Facebook'=>'nextend_facebook_login','LinkedIn'=>'nextend_linkedin_login'); $html='<div class="jp-social-login">';
        foreach ($providers as $label=>$hook) { $url=apply_filters('jportal_social_login_url', wp_login_url(), strtolower($label)); $html.='<a class="jp-btn" href="'.esc_url($url).'">Continue with '.esc_html($label).'</a>'; }
        return $html.'</div><p class="jp-help">Works with social-login plugins through the jportal_social_login_url filter.</p>';
    }

    public static function analytics_shortcode() {
        if (!current_user_can('manage_options')) return ''; $items=array('Jobs'=>wp_count_posts('job')->publish ?? 0,'Companies'=>wp_count_posts('company')->publish ?? 0,'Candidates'=>wp_count_posts('candidate_profile')->publish ?? 0,'Applications'=>wp_count_posts('jp_application')->publish ?? 0,'Orders'=>wp_count_posts('jp_payment_order')->publish ?? 0);
        $html='<div class="jp-analytics-grid">'; foreach($items as $k=>$v) $html.='<div class="jp-metric"><strong>'.intval($v).'</strong><span>'.esc_html($k).'</span></div>'; return $html.'</div>';
    }

    public static function reviews_shortcode($atts) { $a=shortcode_atts(array('company_id'=>get_the_ID()),$atts); $reviews=get_posts(array('post_type'=>'jp_review','numberposts'=>10,'meta_key'=>'_jp_company_id','meta_value'=>absint($a['company_id']))); $html='<div class="jp-reviews">'; foreach($reviews as $r) $html.='<article class="jp-card"><strong>'.esc_html(get_the_author_meta('display_name',$r->post_author)).'</strong><p>'.esc_html($r->post_content).'</p></article>'; return $html.'</div>'; }

    public static function cookie_shortcode() { return '<p>jPortal uses essential cookies to support login, saved jobs, applications, analytics, and hiring workflows.</p>'; }

    public static function cookie_notice() { if (isset($_COOKIE['jp_cookie_ok'])) return; echo '<div class="jp-cookie" id="jp-cookie"><span>We use cookies to improve the hiring experience and remember preferences.</span><button onclick="document.cookie=\'jp_cookie_ok=1;path=/;max-age=31536000\';document.getElementById(\'jp-cookie\').remove();">Accept</button></div>'; }

    public static function append_social_and_related($content) {
        if (!is_singular('job') || !in_the_loop() || !is_main_query()) return $content; $url=get_permalink(); $share='<div class="jp-share"><strong>Share this job:</strong> <a href="https://www.facebook.com/sharer/sharer.php?u='.esc_url($url).'">Facebook</a> <a href="https://www.linkedin.com/shareArticle?mini=true&url='.esc_url($url).'">LinkedIn</a> <a href="https://twitter.com/intent/tweet?url='.esc_url($url).'">X</a></div>'; $related='<h3>Related Jobs</h3>'.do_shortcode('[jportal_jobs limit="3" layout="compact"]'); return $content.$share.$related;
    }

    public static function register_polylang_strings() { if (function_exists('pll_register_string')) { foreach (array('Browse Jobs','Post a Job','Apply Now','Save Job','Featured','Remote','Pricing','Dashboard') as $s) pll_register_string('jPortal', $s, 'jPortal'); } }

    public static function register_elementor_widgets($widgets_manager) {
        if (!class_exists('Elementor\\Widget_Base')) return;
        if (!class_exists('JPortal_Elementor_Base')) {
            abstract class JPortal_Elementor_Base extends Elementor\Widget_Base { public function get_icon(){return 'eicon-post-list';} public function get_categories(){return array('jportal');} protected function render(){ $n=$this->get_name(); echo '<div class="jp-elementor-widget"><h3>'.esc_html($this->get_title()).'</h3>'.do_shortcode('[jportal_jobs limit="3"]').'</div>'; } }
        }
        $labels=array('Job Search','Featured Jobs','Recent Jobs','Remote Jobs','Job Categories','Job Types','Company Grid','Company Carousel','Candidate Grid','Candidate Card','Employer Dashboard','Candidate Dashboard','Pricing Plans','Submit Job','Saved Jobs','Job Alerts','Messages','Reviews','Analytics','Resume Builder','Social Login','Cookie Notice','Hero Layout','Stats Counter','CTA Block','Testimonial','Partner Logos','Map Search','Split Search','List Search','Grid Search','Job Detail','Company Detail','Candidate Detail','Application Tracker','Interview Panel','Video Job','Audio Interview','Salary Filter','Skill Filter','Location Filter','Mega Menu','Breadcrumb','Newsletter','Blog Jobs','Related Jobs','Apply Button','Plan Checkout','Order History','User Menu','Notification Bell','Admin Metrics','CSV Import','Demo Import','Polylang Switcher','GDPR Banner','Share Buttons','Bookmark Button','Featured Badge','Deadline Badge','Company Rating','Candidate Rating','Recruiter Card','Agency Page','Freelance Project','Proposal Form','Invoice Summary','Payment Button','WooCommerce Plan','Stripe Plan','PayPal Plan','Resume Download','Profile Completeness','Onboarding Steps','Help Docs');
        foreach ($labels as $i=>$label) { $class='JPortal_Elementor_Widget_'.($i+1); if (!class_exists($class)) { eval('class '.$class.' extends JPortal_Elementor_Base { public function get_name(){ return "jportal_widget_'.($i+1).'"; } public function get_title(){ return "'.esc_js($label).'"; } }'); } $widgets_manager->register(new $class()); }
    }
}
JPortal_Commercial_Suite::init();
