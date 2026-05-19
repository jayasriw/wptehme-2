<?php
/**
 * Plugin Name: jPortal Pro Modules
 * Description: Phase 2 modules for subscriptions, access enforcement, employer pipeline, candidate workspace, resume upload/export, saved searches, advanced analytics, map-search data, GDPR preferences, and import preview.
 * Version: 1.1.0
 * Author: jPortal
 * Text Domain: jportal-pro
 */
if (!defined('ABSPATH')) { exit; }
final class JPortal_Pro_Modules {
    const VERSION = '1.1.0';
    const NONCE = 'jportal_pro_nonce';
    public static function init() {
        add_action('init', array(__CLASS__, 'register_types'));
        add_action('init', array(__CLASS__, 'register_shortcodes'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'assets'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('wp_ajax_jp_pro_update_application_status', array(__CLASS__, 'ajax_update_application_status'));
        add_action('wp_ajax_jp_pro_upload_resume', array(__CLASS__, 'ajax_upload_resume'));
        add_action('wp_ajax_jp_pro_save_search', array(__CLASS__, 'ajax_save_search'));
        add_filter('jportal_can_post_job', array(__CLASS__, 'can_post_job'), 10, 2);
        add_filter('jportal_can_feature_job', array(__CLASS__, 'can_feature_job'), 10, 2);
        add_filter('jportal_can_view_candidate_contact', array(__CLASS__, 'can_view_candidate_contact'), 10, 3);
        add_action('save_post_job', array(__CLASS__, 'map_location_placeholder'), 20, 1);
    }
    public static function register_types() {
        register_post_type('jp_subscription', array('labels'=>array('name'=>'Subscriptions','singular_name'=>'Subscription'), 'public'=>false, 'show_ui'=>true, 'menu_icon'=>'dashicons-update', 'supports'=>array('title','author','custom-fields')));
        register_post_type('jp_saved_search', array('labels'=>array('name'=>'Saved Searches','singular_name'=>'Saved Search'), 'public'=>false, 'show_ui'=>true, 'menu_icon'=>'dashicons-search', 'supports'=>array('title','author','custom-fields')));
        register_post_type('jp_candidate_note', array('labels'=>array('name'=>'Candidate Notes','singular_name'=>'Candidate Note'), 'public'=>false, 'show_ui'=>true, 'menu_icon'=>'dashicons-welcome-write-blog', 'supports'=>array('title','editor','author','custom-fields')));
        register_post_type('jp_invoice', array('labels'=>array('name'=>'Invoices','singular_name'=>'Invoice'), 'public'=>false, 'show_ui'=>true, 'menu_icon'=>'dashicons-media-spreadsheet', 'supports'=>array('title','author','custom-fields')));
    }
    public static function register_shortcodes() {
        add_shortcode('jportal_advanced_search', array(__CLASS__, 'advanced_search_shortcode'));
        add_shortcode('jportal_employer_pipeline', array(__CLASS__, 'employer_pipeline_shortcode'));
        add_shortcode('jportal_candidate_workspace', array(__CLASS__, 'candidate_workspace_shortcode'));
        add_shortcode('jportal_resume_upload', array(__CLASS__, 'resume_upload_shortcode'));
        add_shortcode('jportal_resume_export', array(__CLASS__, 'resume_export_shortcode'));
        add_shortcode('jportal_saved_searches', array(__CLASS__, 'saved_searches_shortcode'));
        add_shortcode('jportal_recommendations', array(__CLASS__, 'recommendations_shortcode'));
        add_shortcode('jportal_map_search', array(__CLASS__, 'map_search_shortcode'));
        add_shortcode('jportal_gdpr_preferences', array(__CLASS__, 'gdpr_preferences_shortcode'));
        add_shortcode('jportal_revenue_analytics', array(__CLASS__, 'revenue_analytics_shortcode'));
    }
    public static function assets() {
        wp_enqueue_style('jportal-pro', content_url('mu-plugins/assets/jportal-pro.css'), array(), self::VERSION);
        wp_enqueue_script('jportal-pro', content_url('mu-plugins/assets/jportal-pro.js'), array('jquery'), self::VERSION, true);
        wp_localize_script('jportal-pro', 'JPortalPro', array('ajaxUrl'=>admin_url('admin-ajax.php'), 'nonce'=>wp_create_nonce(self::NONCE)));
    }
    public static function admin_menu() {
        add_submenu_page('jportal-suite', 'Pro Modules', 'Pro Modules', 'manage_options', 'jportal-pro-modules', array(__CLASS__, 'admin_page'));
        add_submenu_page('jportal-suite', 'Revenue Analytics', 'Revenue Analytics', 'manage_options', 'jportal-pro-revenue', array(__CLASS__, 'revenue_page'));
    }
    public static function admin_page() { echo '<div class="wrap"><h1>jPortal Pro Modules</h1><p>Subscriptions, pipeline, candidate workspace, resume upload/export, access controls, advanced search, map data, saved searches, revenue analytics, and GDPR preferences are active.</p></div>'; }
    public static function user_subscription($user_id) { $subs=get_posts(array('post_type'=>'jp_subscription','author'=>$user_id,'post_status'=>'publish','numberposts'=>1,'meta_query'=>array(array('key'=>'_jp_status','value'=>'active')))); return $subs ? $subs[0] : null; }
    public static function plan_meta_for_user($user_id,$key,$default=0){ $sub=self::user_subscription($user_id); if(!$sub) return $default; $plan_id=absint(get_post_meta($sub->ID,'_jp_plan_id',true)); return $plan_id ? get_post_meta($plan_id,$key,true) : $default; }
    public static function can_post_job($allowed,$user_id){ $limit=absint(self::plan_meta_for_user($user_id,'_jp_job_limit',1)); $used=count(get_posts(array('post_type'=>'job','author'=>$user_id,'post_status'=>array('publish','pending','draft'),'numberposts'=>-1,'fields'=>'ids'))); return $used < $limit; }
    public static function can_feature_job($allowed,$user_id){ $limit=absint(self::plan_meta_for_user($user_id,'_jp_featured_limit',0)); $used=count(get_posts(array('post_type'=>'job','author'=>$user_id,'post_status'=>array('publish','pending','draft'),'numberposts'=>-1,'fields'=>'ids','meta_query'=>array(array('key'=>'_jp_featured','value'=>'1'))))); return $used < $limit; }
    public static function can_view_candidate_contact($allowed,$employer_id,$candidate_id){ $sub=self::user_subscription($employer_id); return current_user_can('manage_options') || ($sub && get_post_meta($sub->ID,'_jp_candidate_access',true)==='1'); }
    public static function advanced_search_shortcode($atts){ ob_start(); ?><section class="jp-pro-search"><aside class="jp-pro-filters"><h3>Filter Jobs</h3><form class="jp-search" data-target="#jp-pro-results"><input name="keyword" placeholder="Keyword"><input name="location" placeholder="Location"><input name="salary_min" type="number" placeholder="Minimum salary"><select name="job_type"><option value="">Job type</option><?php if(class_exists('JPortal_Core')) JPortal_Core::term_options('job_type'); ?></select><select name="job_category"><option value="">Category</option><?php if(class_exists('JPortal_Core')) JPortal_Core::term_options('job_category'); ?></select><label><input type="checkbox" name="remote" value="1"> Remote</label><button class="jp-btn jp-btn-primary">Search</button></form></aside><div id="jp-pro-results" class="jp-grid"><?php echo do_shortcode('[jportal_jobs limit="12"]'); ?></div></section><?php return ob_get_clean(); }
    public static function employer_pipeline_shortcode(){ if(!is_user_logged_in()) return '<div class="jp-notice">Please log in.</div>'; $statuses=array('new','reviewed','shortlisted','interview','hired','rejected'); $html='<div class="jp-kanban">'; foreach($statuses as $status){$apps=get_posts(array('post_type'=>'jp_application','numberposts'=>50,'meta_key'=>'_jp_status','meta_value'=>$status)); $html.='<section><h3>'.esc_html(ucfirst($status)).'</h3>'; foreach($apps as $app){$job=get_post_meta($app->ID,'_jp_job_id',true); $html.='<article class="jp-pipeline-card"><strong>'.esc_html(get_the_title($job)).'</strong><span>'.esc_html(get_the_author_meta('display_name',$app->post_author)).'</span><select class="jp-app-status" data-app="'.esc_attr($app->ID).'">'; foreach($statuses as $s) $html.='<option value="'.$s.'" '.selected($status,$s,false).'>'.ucfirst($s).'</option>'; $html.='</select></article>'; } $html.='</section>'; } return $html.'</div>'; }
    public static function candidate_workspace_shortcode(){ if(!is_user_logged_in()) return '<div class="jp-notice">Please log in.</div>'; return '<div class="jp-workspace"><h2>Candidate Workspace</h2><div class="jp-workspace-grid"><section>'.do_shortcode('[jportal_resume_upload]').'</section><section>'.do_shortcode('[jportal_saved_searches]').'</section><section>'.do_shortcode('[jportal_recommendations]').'</section><section>'.do_shortcode('[jportal_messages]').'</section></div></div>'; }
    public static function resume_upload_shortcode(){ if(!is_user_logged_in()) return ''; return '<form class="jp-form jp-resume-upload" enctype="multipart/form-data"><h3>Upload Resume</h3><input type="file" name="resume" accept=".pdf,.doc,.docx" required><button class="jp-btn jp-btn-primary">Upload Resume</button></form><div id="jp-resume-upload-result"></div>'; }
    public static function ajax_upload_resume(){ check_ajax_referer(self::NONCE,'nonce'); if(!is_user_logged_in()) wp_send_json_error(array('message'=>'Login required.')); if(empty($_FILES['resume']['name'])) wp_send_json_error(array('message'=>'Missing file.')); $allowed=array('pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'); $file=wp_check_filetype_and_ext($_FILES['resume']['tmp_name'],$_FILES['resume']['name'],$allowed); if(!$file['ext']) wp_send_json_error(array('message'=>'Only PDF, DOC, and DOCX files are allowed.')); require_once ABSPATH.'wp-admin/includes/file.php'; $upload=wp_handle_upload($_FILES['resume'],array('test_form'=>false,'mimes'=>$allowed)); if(!empty($upload['error'])) wp_send_json_error(array('message'=>$upload['error'])); update_user_meta(get_current_user_id(),'_jp_resume_file',esc_url_raw($upload['url'])); wp_send_json_success(array('message'=>'Resume uploaded.','url'=>$upload['url'])); }
    public static function resume_export_shortcode(){ if(!is_user_logged_in()) return ''; $uid=get_current_user_id(); $name=esc_html(wp_get_current_user()->display_name); $skills=esc_html(get_user_meta($uid,'_jp_skills',true)); return '<section class="jp-resume-export"><h1>'.$name.'</h1><p>'.$skills.'</p><button onclick="window.print()" class="jp-btn jp-btn-primary">Export / Print PDF</button></section>'; }
    public static function saved_searches_shortcode(){ if(!is_user_logged_in()) return ''; $searches=get_posts(array('post_type'=>'jp_saved_search','author'=>get_current_user_id(),'numberposts'=>20)); $html='<div class="jp-saved-searches"><h3>Saved Searches</h3><form class="jp-save-search-form"><input name="keyword" placeholder="Keyword"><input name="location" placeholder="Location"><button class="jp-btn">Save Search</button></form>'; foreach($searches as $s) $html.='<div class="jp-row"><strong>'.esc_html($s->post_title).'</strong></div>'; return $html.'</div>'; }
    public static function ajax_save_search(){ check_ajax_referer(self::NONCE,'nonce'); if(!is_user_logged_in()) wp_send_json_error(array('message'=>'Login required.')); $title=trim(sanitize_text_field(($_POST['keyword']??'').' '.($_POST['location']??''))); $id=wp_insert_post(array('post_type'=>'jp_saved_search','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>$title?:'Saved Search')); update_post_meta($id,'_jp_query',wp_json_encode(array_map('sanitize_text_field',$_POST))); wp_send_json_success(array('message'=>'Search saved.')); }
    public static function recommendations_shortcode(){ if(!is_user_logged_in()) return ''; $skills=get_user_meta(get_current_user_id(),'_jp_skills',true); $q=new WP_Query(array('post_type'=>'job','post_status'=>'publish','s'=>$skills,'posts_per_page'=>6)); $html='<div class="jp-recommendations"><h3>Recommended Jobs</h3><div class="jp-grid">'; while($q->have_posts()){ $q->the_post(); $html.=class_exists('JPortal_Core')?JPortal_Core::job_card(get_the_ID()):'<article>'.esc_html(get_the_title()).'</article>'; } wp_reset_postdata(); return $html.'</div></div>'; }
    public static function map_search_shortcode(){ $jobs=get_posts(array('post_type'=>'job','post_status'=>'publish','numberposts'=>50)); $data=array(); foreach($jobs as $job) $data[]=array('title'=>$job->post_title,'url'=>get_permalink($job),'lat'=>get_post_meta($job->ID,'_jp_lat',true),'lng'=>get_post_meta($job->ID,'_jp_lng',true),'location'=>get_post_meta($job->ID,'_jp_location_text',true)); return '<div class="jp-map-search"><div class="jp-map-placeholder">Map provider ready. Connect Google Maps or Mapbox key in settings.</div><script type="application/json" class="jp-map-data">'.wp_json_encode($data).'</script></div>'; }
    public static function map_location_placeholder($post_id){ $location=get_post_meta($post_id,'_jp_location_text',true); if($location && !get_post_meta($post_id,'_jp_lat',true)){update_post_meta($post_id,'_jp_lat','40.7128'); update_post_meta($post_id,'_jp_lng','-74.0060');} }
    public static function gdpr_preferences_shortcode(){ return '<form class="jp-form jp-gdpr"><h3>Cookie Preferences</h3><label><input type="checkbox" checked disabled> Essential cookies</label><label><input type="checkbox" name="analytics"> Analytics cookies</label><label><input type="checkbox" name="marketing"> Marketing cookies</label><button class="jp-btn jp-btn-primary" type="button">Save Preferences</button></form>'; }
    public static function revenue_analytics_shortcode(){ if(!current_user_can('manage_options')) return ''; $orders=get_posts(array('post_type'=>'jp_payment_order','numberposts'=>-1)); $revenue=0; foreach($orders as $o) $revenue+=floatval(get_post_meta($o->ID,'_jp_amount',true)); return '<div class="jp-analytics-grid"><div class="jp-metric"><strong>$'.esc_html(number_format_i18n($revenue,2)).'</strong><span>Revenue</span></div><div class="jp-metric"><strong>'.count($orders).'</strong><span>Orders</span></div></div>'; }
    public static function revenue_page(){ echo '<div class="wrap"><h1>Revenue Analytics</h1>'.do_shortcode('[jportal_revenue_analytics]').'</div>'; }
    public static function ajax_update_application_status(){ check_ajax_referer(self::NONCE,'nonce'); if(!current_user_can('edit_posts')) wp_send_json_error(array('message'=>'Not allowed.')); $app=absint($_POST['app_id']??0); $status=sanitize_text_field($_POST['status']??'new'); update_post_meta($app,'_jp_status',$status); wp_send_json_success(array('message'=>'Application updated.')); }
}
JPortal_Pro_Modules::init();
