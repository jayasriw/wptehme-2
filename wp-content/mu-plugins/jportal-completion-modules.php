<?php
/**
 * Plugin Name: jPortal Completion Modules
 * Description: Final commercial feature modules for jPortal: payment callbacks, plan enforcement helpers, import mapping, review moderation, threaded inbox, demo layouts, onboarding, and QA diagnostics.
 * Version: 1.2.0
 * Author: jPortal
 */
if (!defined('ABSPATH')) { exit; }

final class JPortal_Completion_Modules {
    const VERSION = '1.2.0';
    const NONCE = 'jportal_completion_nonce';

    public static function init() {
        add_action('init', array(__CLASS__, 'shortcodes'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('rest_api_init', array(__CLASS__, 'rest_routes'));
        add_action('wp_ajax_jpc_create_thread', array(__CLASS__, 'ajax_create_thread'));
        add_action('wp_ajax_jpc_reply_thread', array(__CLASS__, 'ajax_reply_thread'));
        add_action('wp_ajax_jpc_moderate_review', array(__CLASS__, 'ajax_moderate_review'));
        add_action('wp_ajax_jpc_save_cookie_prefs', array(__CLASS__, 'ajax_cookie_prefs'));
        add_action('admin_post_jpc_import_mapped_csv', array(__CLASS__, 'import_mapped_csv'));
        add_action('admin_post_jpc_mark_payment_paid', array(__CLASS__, 'mark_payment_paid'));
        add_filter('jportal_plan_usage', array(__CLASS__, 'plan_usage'), 10, 2);
    }

    public static function shortcodes() {
        add_shortcode('jportal_threaded_inbox', array(__CLASS__, 'threaded_inbox'));
        add_shortcode('jportal_import_mapper', array(__CLASS__, 'import_mapper'));
        add_shortcode('jportal_review_moderation', array(__CLASS__, 'review_moderation'));
        add_shortcode('jportal_onboarding', array(__CLASS__, 'onboarding'));
        add_shortcode('jportal_diagnostics', array(__CLASS__, 'diagnostics'));
        add_shortcode('jportal_demo_layouts', array(__CLASS__, 'demo_layouts'));
        add_shortcode('jportal_payment_return', array(__CLASS__, 'payment_return'));
    }

    public static function admin_menu() {
        add_submenu_page('jportal-suite', 'Diagnostics', 'Diagnostics', 'manage_options', 'jportal-diagnostics', array(__CLASS__, 'diagnostics_page'));
        add_submenu_page('jportal-suite', 'Import Mapper', 'Import Mapper', 'manage_options', 'jportal-import-mapper', array(__CLASS__, 'import_mapper_page'));
    }

    public static function rest_routes() {
        register_rest_route('jportal/v1', '/payment/callback', array('methods'=>'POST', 'callback'=>array(__CLASS__, 'payment_callback'), 'permission_callback'=>'__return_true'));
        register_rest_route('jportal/v1', '/diagnostics', array('methods'=>'GET', 'callback'=>array(__CLASS__, 'diagnostics_data'), 'permission_callback'=>function(){ return current_user_can('manage_options'); }));
    }

    public static function payment_callback($request) {
        $payload = $request->get_json_params();
        $order_id = absint($payload['order_id'] ?? 0);
        $status = sanitize_text_field($payload['status'] ?? 'paid');
        $plan_id = absint($payload['plan_id'] ?? 0);
        $user_id = absint($payload['user_id'] ?? 0);
        if ($order_id) update_post_meta($order_id, '_jp_status', $status);
        if ($status === 'paid' && $user_id && $plan_id) self::activate_subscription($user_id, $plan_id, $order_id);
        return array('success'=>true, 'order_id'=>$order_id, 'status'=>$status);
    }

    public static function activate_subscription($user_id, $plan_id, $order_id = 0) {
        $sub_id = wp_insert_post(array('post_type'=>'jp_subscription','post_status'=>'publish','post_author'=>$user_id,'post_title'=>'Subscription - User '.$user_id));
        update_post_meta($sub_id, '_jp_plan_id', $plan_id);
        update_post_meta($sub_id, '_jp_order_id', $order_id);
        update_post_meta($sub_id, '_jp_status', 'active');
        update_post_meta($sub_id, '_jp_started', current_time('mysql'));
        update_post_meta($sub_id, '_jp_expires', date('Y-m-d', strtotime('+30 days')));
        return $sub_id;
    }

    public static function mark_payment_paid() {
        if (!current_user_can('manage_options')) wp_die('Not allowed');
        $order_id = absint($_GET['order_id'] ?? 0);
        $plan_id = absint($_GET['plan_id'] ?? 0);
        $user_id = absint($_GET['user_id'] ?? 0);
        if ($order_id) update_post_meta($order_id, '_jp_status', 'paid');
        if ($plan_id && $user_id) self::activate_subscription($user_id, $plan_id, $order_id);
        wp_safe_redirect(admin_url('admin.php?page=jportal-pro-revenue&paid=1'));
        exit;
    }

    public static function plan_usage($usage, $user_id) {
        $jobs = count(get_posts(array('post_type'=>'job','author'=>$user_id,'numberposts'=>-1,'fields'=>'ids','post_status'=>array('publish','pending','draft'))));
        $featured = count(get_posts(array('post_type'=>'job','author'=>$user_id,'numberposts'=>-1,'fields'=>'ids','post_status'=>array('publish','pending','draft'),'meta_query'=>array(array('key'=>'_jp_featured','value'=>'1')))));
        return array('jobs'=>$jobs, 'featured'=>$featured);
    }

    public static function threaded_inbox() {
        if (!is_user_logged_in()) return '<div class="jp-notice">Please log in.</div>';
        $uid = get_current_user_id();
        $threads = get_posts(array('post_type'=>'jp_message','numberposts'=>30,'meta_query'=>array('relation'=>'OR',array('key'=>'_jp_to_user','value'=>$uid),array('key'=>'_jp_from_user','value'=>$uid))));
        $html = '<div class="jpc-inbox"><h2>Inbox</h2><form class="jpc-thread-form"><input name="to_user" placeholder="Recipient user ID"><input name="subject" placeholder="Subject"><textarea name="message" placeholder="Message"></textarea><button class="jp-btn jp-btn-primary">Send</button></form>';
        foreach ($threads as $t) $html .= '<article class="jpc-thread"><h3>'.esc_html($t->post_title).'</h3><p>'.esc_html(wp_trim_words($t->post_content, 30)).'</p><small>From user '.esc_html(get_post_meta($t->ID,'_jp_from_user',true)).' to user '.esc_html(get_post_meta($t->ID,'_jp_to_user',true)).'</small></article>';
        return $html.'</div>';
    }

    public static function ajax_create_thread() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message'=>'Login required'));
        $id = wp_insert_post(array('post_type'=>'jp_message','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>sanitize_text_field($_POST['subject'] ?? 'Message'),'post_content'=>sanitize_textarea_field($_POST['message'] ?? '')));
        update_post_meta($id, '_jp_from_user', get_current_user_id());
        update_post_meta($id, '_jp_to_user', absint($_POST['to_user'] ?? 0));
        update_post_meta($id, '_jp_thread_id', $id);
        wp_send_json_success(array('message'=>'Message sent', 'id'=>$id));
    }

    public static function ajax_reply_thread() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!is_user_logged_in()) wp_send_json_error(array('message'=>'Login required'));
        $thread_id = absint($_POST['thread_id'] ?? 0);
        $id = wp_insert_post(array('post_type'=>'jp_message','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>'Re: '.get_the_title($thread_id),'post_content'=>sanitize_textarea_field($_POST['message'] ?? '')));
        update_post_meta($id, '_jp_thread_id', $thread_id);
        wp_send_json_success(array('message'=>'Reply sent'));
    }

    public static function review_moderation() {
        if (!current_user_can('edit_posts')) return '';
        $reviews = get_posts(array('post_type'=>'jp_review','numberposts'=>30,'post_status'=>array('publish','pending','draft')));
        $html = '<div class="jpc-review-mod"><h2>Review Moderation</h2>';
        foreach ($reviews as $r) $html .= '<article class="jp-card"><strong>'.esc_html($r->post_title).'</strong><p>'.esc_html($r->post_content).'</p><button class="jpc-moderate-review jp-btn" data-review="'.esc_attr($r->ID).'" data-status="publish">Approve</button><button class="jpc-moderate-review jp-btn" data-review="'.esc_attr($r->ID).'" data-status="draft">Reject</button></article>';
        return $html.'</div>';
    }

    public static function ajax_moderate_review() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('edit_posts')) wp_send_json_error(array('message'=>'Not allowed'));
        wp_update_post(array('ID'=>absint($_POST['review_id'] ?? 0), 'post_status'=>sanitize_key($_POST['status'] ?? 'pending')));
        wp_send_json_success(array('message'=>'Review updated'));
    }

    public static function import_mapper_page() { echo '<div class="wrap"><h1>Import Mapper</h1>'.self::import_mapper().'</div>'; }
    public static function import_mapper() {
        return '<form method="post" enctype="multipart/form-data" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="jpc_import_mapped_csv">'.wp_nonce_field('jpc_import_mapped_csv','_wpnonce',true,false).'<p><input type="file" name="csv" accept=".csv" required></p><p><input name="mapping" class="regular-text" value="title,description,location,type,category,salary_min,salary_max,deadline" placeholder="Column mapping"></p><button class="button button-primary">Import with Mapping</button></form>';
    }

    public static function import_mapped_csv() {
        if (!current_user_can('manage_options') || !check_admin_referer('jpc_import_mapped_csv')) wp_die('Not allowed');
        $mapping = array_map('sanitize_key', explode(',', sanitize_text_field($_POST['mapping'] ?? 'title,description')));
        $count = 0;
        if (!empty($_FILES['csv']['tmp_name'])) {
            $h = fopen($_FILES['csv']['tmp_name'], 'r');
            while (($row = fgetcsv($h)) !== false) {
                $data = array_combine($mapping, array_pad($row, count($mapping), ''));
                $id = wp_insert_post(array('post_type'=>'job','post_status'=>'publish','post_title'=>sanitize_text_field($data['title'] ?? 'Imported Job'),'post_content'=>wp_kses_post($data['description'] ?? '')));
                if ($id) { update_post_meta($id, '_jp_location_text', sanitize_text_field($data['location'] ?? '')); update_post_meta($id, '_jp_deadline', sanitize_text_field($data['deadline'] ?? '')); $count++; }
            }
            fclose($h);
        }
        wp_safe_redirect(admin_url('admin.php?page=jportal-import-mapper&imported='.$count)); exit;
    }

    public static function onboarding() {
        return '<div class="jpc-onboarding"><h2>jPortal Onboarding</h2><ol><li>Create employer and candidate roles.</li><li>Create plans.</li><li>Run one-click setup.</li><li>Configure payments.</li><li>Publish jobs and companies.</li><li>Test applications, alerts, messaging, reviews, and subscriptions.</li></ol></div>';
    }

    public static function demo_layouts() {
        $html = '<div class="jpc-demo-layouts">';
        for ($i=1; $i<=12; $i++) $html .= do_shortcode('[jportal_home layout="'.$i.'"]');
        return $html.'</div>';
    }

    public static function payment_return() { return '<div class="jp-success"><h2>Payment received</h2><p>Your plan is being activated. Check your dashboard for subscription details.</p></div>'; }

    public static function diagnostics_page() { echo '<div class="wrap"><h1>jPortal Diagnostics</h1>'.self::diagnostics().'</div>'; }
    public static function diagnostics() {
        $checks = self::diagnostics_data(); $html='<table class="widefat"><tbody>';
        foreach ($checks as $k=>$v) $html.='<tr><th>'.esc_html($k).'</th><td>'.esc_html(is_bool($v)?($v?'OK':'Missing'):$v).'</td></tr>';
        return $html.'</tbody></table>';
    }
    public static function diagnostics_data() { return array('WordPress'=>get_bloginfo('version'),'PHP'=>PHP_VERSION,'jPortal Core'=>class_exists('JPortal_Core'),'WooCommerce'=>class_exists('WooCommerce'),'Elementor'=>did_action('elementor/loaded')>0,'Polylang'=>function_exists('pll_register_string'),'Jobs'=>wp_count_posts('job')->publish ?? 0,'Applications'=>wp_count_posts('jp_application')->publish ?? 0); }
    public static function ajax_cookie_prefs(){ check_ajax_referer(self::NONCE,'nonce'); setcookie('jp_cookie_ok','1',time()+YEAR_IN_SECONDS,COOKIEPATH,COOKIE_DOMAIN,is_ssl(),true); wp_send_json_success(array('message'=>'Preferences saved')); }
}
JPortal_Completion_Modules::init();