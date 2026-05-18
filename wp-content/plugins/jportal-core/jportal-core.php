<?php
/**
 * Plugin Name: jPortal Core
 * Description: Commercial-grade job board engine for the jPortal WordPress theme: jobs, companies, candidates, applications, saved jobs, alerts, messaging, reviews, plans, analytics, import/export, and dashboard shortcodes.
 * Version: 1.0.0
 * Author: jPortal
 * Text Domain: jportal-core
 */

if (!defined('ABSPATH')) { exit; }

final class JPortal_Core {
    const VERSION = '1.0.0';
    const NONCE = 'jportal_core_nonce';

    public static function init() {
        add_action('init', [__CLASS__, 'register_roles']);
        add_action('init', [__CLASS__, 'register_content_types']);
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post', [__CLASS__, 'save_meta_boxes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'admin_assets']);
        add_action('admin_menu', [__CLASS__, 'admin_pages']);
        add_action('wp', [__CLASS__, 'schedule_events']);
        add_action('jportal_expire_jobs_daily', [__CLASS__, 'expire_jobs']);
        add_action('jportal_send_job_alerts_daily', [__CLASS__, 'send_job_alerts']);
        add_shortcode('jportal_jobs', [__CLASS__, 'shortcode_jobs']);
        add_shortcode('jportal_submit_job', [__CLASS__, 'shortcode_submit_job']);
        add_shortcode('jportal_candidate_dashboard', [__CLASS__, 'shortcode_candidate_dashboard']);
        add_shortcode('jportal_employer_dashboard', [__CLASS__, 'shortcode_employer_dashboard']);
        add_shortcode('jportal_companies', [__CLASS__, 'shortcode_companies']);
        add_shortcode('jportal_pricing', [__CLASS__, 'shortcode_pricing']);
        add_shortcode('jportal_messages', [__CLASS__, 'shortcode_messages']);
        add_action('wp_ajax_jportal_search_jobs', [__CLASS__, 'ajax_search_jobs']);
        add_action('wp_ajax_nopriv_jportal_search_jobs', [__CLASS__, 'ajax_search_jobs']);
        add_action('wp_ajax_jportal_apply_job', [__CLASS__, 'ajax_apply_job']);
        add_action('wp_ajax_jportal_save_job', [__CLASS__, 'ajax_save_job']);
        add_action('wp_ajax_jportal_create_alert', [__CLASS__, 'ajax_create_alert']);
        add_action('wp_ajax_jportal_send_message', [__CLASS__, 'ajax_send_message']);
        add_action('wp_ajax_jportal_review_company', [__CLASS__, 'ajax_review_company']);
        add_filter('manage_job_posts_columns', [__CLASS__, 'job_columns']);
        add_action('manage_job_posts_custom_column', [__CLASS__, 'job_column_content'], 10, 2);
    }

    public static function activate() {
        self::register_roles();
        self::register_content_types();
        flush_rewrite_rules();
    }

    public static function deactivate() { flush_rewrite_rules(); }

    public static function register_roles() {
        add_role('jportal_employer', __('Employer', 'jportal-core'), [
            'read' => true, 'upload_files' => true, 'edit_posts' => true,
        ]);
        add_role('jportal_candidate', __('Candidate', 'jportal-core'), [
            'read' => true, 'upload_files' => true,
        ]);
    }

    public static function register_content_types() {
        $post_types = [
            'job' => ['Jobs', 'Job', 'dashicons-id'],
            'company' => ['Companies', 'Company', 'dashicons-building'],
            'candidate_profile' => ['Candidates', 'Candidate', 'dashicons-businessperson'],
            'jp_application' => ['Applications', 'Application', 'dashicons-portfolio'],
            'jp_message' => ['Messages', 'Message', 'dashicons-email-alt'],
            'jp_plan' => ['Plans', 'Plan', 'dashicons-money-alt'],
            'jp_review' => ['Reviews', 'Review', 'dashicons-star-filled'],
            'jp_alert' => ['Job Alerts', 'Job Alert', 'dashicons-megaphone'],
        ];
        foreach ($post_types as $type => $data) {
            $public = in_array($type, ['job','company','candidate_profile','jp_plan'], true);
            register_post_type($type, [
                'labels' => ['name' => __($data[0], 'jportal-core'), 'singular_name' => __($data[1], 'jportal-core'), 'add_new_item' => sprintf(__('Add New %s', 'jportal-core'), $data[1]), 'edit_item' => sprintf(__('Edit %s', 'jportal-core'), $data[1])],
                'public' => $public,
                'show_ui' => true,
                'show_in_menu' => true,
                'menu_icon' => $data[2],
                'has_archive' => in_array($type, ['job','company','candidate_profile'], true),
                'rewrite' => ['slug' => str_replace('_', '-', $type)],
                'supports' => ['title','editor','thumbnail','author','excerpt','custom-fields'],
                'show_in_rest' => true,
            ]);
        }
        register_taxonomy('job_category', 'job', ['label' => __('Job Categories','jportal-core'), 'hierarchical' => true, 'show_admin_column' => true, 'show_in_rest' => true, 'rewrite' => ['slug' => 'job-category']]);
        register_taxonomy('job_type', 'job', ['label' => __('Job Types','jportal-core'), 'hierarchical' => false, 'show_admin_column' => true, 'show_in_rest' => true, 'rewrite' => ['slug' => 'job-type']]);
        register_taxonomy('job_skill', ['job','candidate_profile'], ['label' => __('Skills','jportal-core'), 'hierarchical' => false, 'show_admin_column' => true, 'show_in_rest' => true, 'rewrite' => ['slug' => 'skill']]);
        register_taxonomy('job_location', 'job', ['label' => __('Locations','jportal-core'), 'hierarchical' => true, 'show_admin_column' => true, 'show_in_rest' => true, 'rewrite' => ['slug' => 'job-location']]);
    }

    public static function assets() {
        wp_enqueue_style('jportal-core', plugins_url('assets/jportal-core.css', __FILE__), [], self::VERSION);
        wp_enqueue_script('jportal-core', plugins_url('assets/jportal-core.js', __FILE__), ['jquery'], self::VERSION, true);
        wp_localize_script('jportal-core', 'jPortalCore', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE),
            'loginUrl' => wp_login_url(),
        ]);
    }

    public static function admin_assets() { wp_enqueue_style('jportal-core-admin', plugins_url('assets/jportal-admin.css', __FILE__), [], self::VERSION); }

    public static function add_meta_boxes() {
        add_meta_box('jp_job_details', __('Job Details','jportal-core'), [__CLASS__, 'render_job_details'], 'job', 'normal', 'high');
        add_meta_box('jp_company_details', __('Company Details','jportal-core'), [__CLASS__, 'render_company_details'], 'company', 'normal', 'high');
        add_meta_box('jp_candidate_details', __('Candidate Details','jportal-core'), [__CLASS__, 'render_candidate_details'], 'candidate_profile', 'normal', 'high');
        add_meta_box('jp_plan_details', __('Plan Details','jportal-core'), [__CLASS__, 'render_plan_details'], 'jp_plan', 'normal', 'high');
        add_meta_box('jp_application_details', __('Application Details','jportal-core'), [__CLASS__, 'render_application_details'], 'jp_application', 'normal', 'high');
    }

    public static function field($post_id, $key, $label, $type = 'text', $placeholder = '') {
        $value = get_post_meta($post_id, $key, true);
        echo '<p><label><strong>'.esc_html($label).'</strong><br>';
        if ($type === 'textarea') echo '<textarea class="widefat" rows="4" name="'.esc_attr($key).'">'.esc_textarea($value).'</textarea>';
        elseif ($type === 'checkbox') echo '<input type="checkbox" name="'.esc_attr($key).'" value="1" '.checked($value, '1', false).'> '.esc_html($placeholder);
        else echo '<input class="widefat" type="'.esc_attr($type).'" name="'.esc_attr($key).'" value="'.esc_attr($value).'" placeholder="'.esc_attr($placeholder).'">';
        echo '</label></p>';
    }

    public static function render_job_details($post) {
        wp_nonce_field('jp_save_meta', 'jp_meta_nonce');
        self::field($post->ID, '_jp_company_id', 'Company Post ID', 'number');
        self::field($post->ID, '_jp_salary_min', 'Salary Minimum', 'number');
        self::field($post->ID, '_jp_salary_max', 'Salary Maximum', 'number');
        self::field($post->ID, '_jp_currency', 'Currency', 'text', 'USD');
        self::field($post->ID, '_jp_location_text', 'Location Text', 'text', 'New York, NY');
        self::field($post->ID, '_jp_remote', 'Remote / Hybrid', 'checkbox', 'Mark as remote-friendly');
        self::field($post->ID, '_jp_deadline', 'Application Deadline', 'date');
        self::field($post->ID, '_jp_featured', 'Featured Listing', 'checkbox', 'Promote this listing');
        self::field($post->ID, '_jp_apply_url', 'External Apply URL', 'url');
        self::field($post->ID, '_jp_video_url', 'Video Job Description URL', 'url');
        self::field($post->ID, '_jp_audio_url', 'Audio Interview URL', 'url');
        self::field($post->ID, '_jp_moderation_status', 'Moderation Status', 'text', 'approved / pending / rejected');
    }

    public static function render_company_details($post) {
        wp_nonce_field('jp_save_meta', 'jp_meta_nonce');
        foreach (['_jp_website'=>'Website','_jp_email'=>'Public Email','_jp_phone'=>'Phone','_jp_industry'=>'Industry','_jp_size'=>'Company Size','_jp_founded'=>'Founded Year','_jp_address'=>'Address','_jp_video_url'=>'Company Video URL'] as $k=>$l) self::field($post->ID, $k, $l, $k === '_jp_email' ? 'email' : 'text');
    }

    public static function render_candidate_details($post) {
        wp_nonce_field('jp_save_meta', 'jp_meta_nonce');
        foreach (['_jp_headline'=>'Professional Headline','_jp_location'=>'Location','_jp_expected_salary'=>'Expected Salary','_jp_resume_url'=>'Resume/CV URL','_jp_portfolio_url'=>'Portfolio URL','_jp_linkedin_url'=>'LinkedIn URL'] as $k=>$l) self::field($post->ID, $k, $l, 'text');
        self::field($post->ID, '_jp_available', 'Available Now', 'checkbox', 'Candidate is actively available');
    }

    public static function render_plan_details($post) {
        wp_nonce_field('jp_save_meta', 'jp_meta_nonce');
        self::field($post->ID, '_jp_price', 'Price', 'number');
        self::field($post->ID, '_jp_duration_days', 'Duration Days', 'number');
        self::field($post->ID, '_jp_job_limit', 'Job Listing Limit', 'number');
        self::field($post->ID, '_jp_featured_limit', 'Featured Job Limit', 'number');
        self::field($post->ID, '_jp_candidate_access', 'Candidate Contact Access', 'checkbox', 'Allow employers to view candidate contact info');
    }

    public static function render_application_details($post) {
        wp_nonce_field('jp_save_meta', 'jp_meta_nonce');
        self::field($post->ID, '_jp_job_id', 'Job ID', 'number');
        self::field($post->ID, '_jp_candidate_user_id', 'Candidate User ID', 'number');
        self::field($post->ID, '_jp_status', 'Status', 'text', 'new / reviewed / shortlisted / rejected / hired');
        self::field($post->ID, '_jp_resume_url', 'Resume URL', 'url');
        self::field($post->ID, '_jp_cover_letter', 'Cover Letter', 'textarea');
    }

    public static function save_meta_boxes($post_id) {
        if (!isset($_POST['jp_meta_nonce']) || !wp_verify_nonce($_POST['jp_meta_nonce'], 'jp_save_meta')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        foreach ($_POST as $key => $value) {
            if (strpos($key, '_jp_') === 0) {
                $clean = is_array($value) ? array_map('sanitize_text_field', $value) : (strpos($key, 'cover_letter') !== false ? sanitize_textarea_field($value) : sanitize_text_field($value));
                update_post_meta($post_id, $key, $clean);
            }
        }
        foreach (['_jp_remote','_jp_featured','_jp_available','_jp_candidate_access'] as $cb) if (!isset($_POST[$cb])) update_post_meta($post_id, $cb, '0');
    }

    public static function admin_pages() {
        add_menu_page('jPortal', 'jPortal', 'manage_options', 'jportal', [__CLASS__, 'admin_dashboard'], 'dashicons-groups', 26);
        add_submenu_page('jportal', 'Analytics', 'Analytics', 'manage_options', 'jportal', [__CLASS__, 'admin_dashboard']);
        add_submenu_page('jportal', 'Import / Export', 'Import / Export', 'manage_options', 'jportal-import-export', [__CLASS__, 'admin_import_export']);
        add_submenu_page('jportal', 'Settings', 'Settings', 'manage_options', 'jportal-settings', [__CLASS__, 'admin_settings']);
    }

    public static function count_posts($type, $status = 'publish') { $c = wp_count_posts($type); return isset($c->{$status}) ? intval($c->{$status}) : 0; }

    public static function admin_dashboard() {
        echo '<div class="wrap jp-admin"><h1>jPortal Analytics</h1><div class="jp-metrics">';
        foreach ([['Jobs',self::count_posts('job')],['Companies',self::count_posts('company')],['Candidates',self::count_posts('candidate_profile')],['Applications',self::count_posts('jp_application','publish')],['Messages',self::count_posts('jp_message','publish')],['Alerts',self::count_posts('jp_alert','publish')]] as $m) echo '<div class="jp-metric"><strong>'.esc_html($m[1]).'</strong><span>'.esc_html($m[0]).'</span></div>';
        echo '</div><p>Use shortcodes: <code>[jportal_jobs]</code>, <code>[jportal_submit_job]</code>, <code>[jportal_candidate_dashboard]</code>, <code>[jportal_employer_dashboard]</code>, <code>[jportal_companies]</code>, <code>[jportal_pricing]</code>, <code>[jportal_messages]</code>.</p></div>';
    }

    public static function admin_import_export() {
        if (isset($_GET['jp_export']) && current_user_can('manage_options')) {
            header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="jportal-jobs.csv"');
            $out = fopen('php://output', 'w'); fputcsv($out, ['ID','Title','Location','Salary Min','Salary Max','Deadline','Featured']);
            foreach (get_posts(['post_type'=>'job','numberposts'=>-1]) as $job) fputcsv($out, [$job->ID,$job->post_title,get_post_meta($job->ID,'_jp_location_text',true),get_post_meta($job->ID,'_jp_salary_min',true),get_post_meta($job->ID,'_jp_salary_max',true),get_post_meta($job->ID,'_jp_deadline',true),get_post_meta($job->ID,'_jp_featured',true)]);
            exit;
        }
        echo '<div class="wrap"><h1>Import / Export</h1><p><a class="button button-primary" href="'.esc_url(admin_url('admin.php?page=jportal-import-export&jp_export=1')).'">Export Jobs CSV</a></p><p>CSV import can be performed using WordPress import tools or extended through this page.</p></div>';
    }

    public static function admin_settings() {
        if (isset($_POST['jp_settings_nonce']) && wp_verify_nonce($_POST['jp_settings_nonce'], 'jp_save_settings')) {
            update_option('jp_currency', sanitize_text_field($_POST['jp_currency'] ?? 'USD'));
            update_option('jp_jobs_require_moderation', isset($_POST['jp_jobs_require_moderation']) ? '1' : '0');
            echo '<div class="updated"><p>Settings saved.</p></div>';
        }
        echo '<div class="wrap"><h1>jPortal Settings</h1><form method="post">'; wp_nonce_field('jp_save_settings','jp_settings_nonce');
        echo '<p><label>Default Currency <input name="jp_currency" value="'.esc_attr(get_option('jp_currency','USD')).'"></label></p><p><label><input type="checkbox" name="jp_jobs_require_moderation" value="1" '.checked(get_option('jp_jobs_require_moderation','1'),'1',false).'> Require job moderation</label></p><button class="button button-primary">Save Settings</button></form></div>';
    }

    public static function schedule_events() {
        if (!wp_next_scheduled('jportal_expire_jobs_daily')) wp_schedule_event(time(), 'daily', 'jportal_expire_jobs_daily');
        if (!wp_next_scheduled('jportal_send_job_alerts_daily')) wp_schedule_event(time()+3600, 'daily', 'jportal_send_job_alerts_daily');
    }

    public static function expire_jobs() {
        $jobs = get_posts(['post_type'=>'job','post_status'=>'publish','numberposts'=>-1,'meta_query'=>[['key'=>'_jp_deadline','value'=>current_time('Y-m-d'),'compare'=>'<','type'=>'DATE']]]);
        foreach ($jobs as $job) wp_update_post(['ID'=>$job->ID,'post_status'=>'draft']);
    }

    public static function send_job_alerts() {
        $alerts = get_posts(['post_type'=>'jp_alert','post_status'=>'publish','numberposts'=>-1]);
        foreach ($alerts as $alert) {
            $email = get_post_meta($alert->ID, '_jp_email', true); if (!$email) continue;
            $keyword = get_post_meta($alert->ID, '_jp_keyword', true);
            $jobs = get_posts(['post_type'=>'job','post_status'=>'publish','s'=>$keyword,'numberposts'=>5]);
            if (!$jobs) continue;
            $body = "New jobs matching your alert:\n\n";
            foreach ($jobs as $job) $body .= $job->post_title.' - '.get_permalink($job->ID)."\n";
            wp_mail($email, 'jPortal Job Alert: '.$keyword, $body);
        }
    }

    public static function query_jobs($args = []) {
        $meta_query = ['relation' => 'AND'];
        if (!empty($args['location'])) $meta_query[] = ['key'=>'_jp_location_text','value'=>sanitize_text_field($args['location']),'compare'=>'LIKE'];
        if (!empty($args['remote'])) $meta_query[] = ['key'=>'_jp_remote','value'=>'1'];
        if (!empty($args['salary_min'])) $meta_query[] = ['key'=>'_jp_salary_max','value'=>intval($args['salary_min']),'compare'=>'>=','type'=>'NUMERIC'];
        $tax_query = ['relation' => 'AND'];
        foreach (['job_category','job_type','job_skill','job_location'] as $tax) if (!empty($args[$tax])) $tax_query[] = ['taxonomy'=>$tax,'field'=>'slug','terms'=>sanitize_text_field($args[$tax])];
        return new WP_Query(['post_type'=>'job','post_status'=>'publish','s'=>sanitize_text_field($args['keyword'] ?? ''),'posts_per_page'=>intval($args['limit'] ?? 12),'meta_query'=>count($meta_query)>1?$meta_query:[],'tax_query'=>count($tax_query)>1?$tax_query:[],'orderby'=>!empty($args['featured_first'])?'meta_value date':'date','meta_key'=>!empty($args['featured_first'])?'_jp_featured':'','order'=>'DESC']);
    }

    public static function job_card($post_id) {
        $salary = self::salary($post_id); $loc = get_post_meta($post_id, '_jp_location_text', true); $featured = get_post_meta($post_id, '_jp_featured', true);
        ob_start(); ?>
        <article class="jp-card jp-job-card">
            <?php if ($featured === '1') echo '<span class="jp-badge">Featured</span>'; ?>
            <h3><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h3>
            <div class="jp-meta"><span><?php echo esc_html($loc ?: 'Remote / Flexible'); ?></span><span><?php echo esc_html($salary); ?></span></div>
            <p><?php echo esc_html(wp_trim_words(get_post_field('post_excerpt', $post_id) ?: get_post_field('post_content', $post_id), 22)); ?></p>
            <div class="jp-actions"><a class="jp-btn jp-btn-primary" href="<?php echo esc_url(get_permalink($post_id)); ?>">View Job</a><button class="jp-btn jp-save-job" data-job="<?php echo esc_attr($post_id); ?>">Save</button></div>
        </article><?php return ob_get_clean();
    }

    public static function salary($post_id) {
        $min = get_post_meta($post_id, '_jp_salary_min', true); $max = get_post_meta($post_id, '_jp_salary_max', true); $cur = get_post_meta($post_id, '_jp_currency', true) ?: get_option('jp_currency','USD');
        if ($min && $max) return $cur.' '.number_format_i18n($min).' - '.number_format_i18n($max);
        if ($min) return $cur.' '.number_format_i18n($min).'+'; return __('Salary not disclosed','jportal-core');
    }

    public static function shortcode_jobs($atts) {
        $atts = shortcode_atts(['limit'=>12,'featured_first'=>1], $atts);
        ob_start(); ?>
        <section class="jp-jobs">
            <form class="jp-search" data-target="#jp-job-results">
                <input name="keyword" placeholder="Keyword, title, company">
                <input name="location" placeholder="Location">
                <select name="job_type"><option value="">Job Type</option><?php self::term_options('job_type'); ?></select>
                <select name="job_category"><option value="">Category</option><?php self::term_options('job_category'); ?></select>
                <label class="jp-check"><input type="checkbox" name="remote" value="1"> Remote</label>
                <button class="jp-btn jp-btn-primary">Search Jobs</button>
            </form>
            <div id="jp-job-results" class="jp-grid">
                <?php $q = self::query_jobs($atts); while ($q->have_posts()) { $q->the_post(); echo self::job_card(get_the_ID()); } wp_reset_postdata(); ?>
            </div>
        </section><?php return ob_get_clean();
    }

    public static function term_options($tax) { foreach (get_terms(['taxonomy'=>$tax,'hide_empty'=>false]) as $t) echo '<option value="'.esc_attr($t->slug).'">'.esc_html($t->name).'</option>'; }

    public static function shortcode_submit_job() {
        if (!is_user_logged_in()) return '<div class="jp-notice">Please <a href="'.esc_url(wp_login_url(get_permalink())).'">log in</a> as an employer to submit a job.</div>';
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['jp_submit_job_nonce']) && wp_verify_nonce($_POST['jp_submit_job_nonce'], 'jp_submit_job')) {
            $status = get_option('jp_jobs_require_moderation','1') === '1' ? 'pending' : 'publish';
            $job_id = wp_insert_post(['post_type'=>'job','post_status'=>$status,'post_author'=>get_current_user_id(),'post_title'=>sanitize_text_field($_POST['job_title'] ?? ''),'post_content'=>wp_kses_post($_POST['job_description'] ?? ''),'post_excerpt'=>sanitize_textarea_field($_POST['job_summary'] ?? '')]);
            if ($job_id && !is_wp_error($job_id)) {
                foreach (['_jp_salary_min'=>'salary_min','_jp_salary_max'=>'salary_max','_jp_location_text'=>'location','_jp_deadline'=>'deadline','_jp_apply_url'=>'apply_url','_jp_video_url'=>'video_url'] as $meta=>$field) update_post_meta($job_id,$meta,sanitize_text_field($_POST[$field] ?? ''));
                update_post_meta($job_id,'_jp_remote',isset($_POST['remote'])?'1':'0');
                if (!empty($_POST['job_type'])) wp_set_object_terms($job_id, sanitize_text_field($_POST['job_type']), 'job_type');
                if (!empty($_POST['job_category'])) wp_set_object_terms($job_id, sanitize_text_field($_POST['job_category']), 'job_category');
                wp_mail(get_option('admin_email'), 'New job submitted', 'A new jPortal job was submitted: '.get_the_title($job_id));
                return '<div class="jp-success">Job submitted successfully and is awaiting review.</div>';
            }
        }
        ob_start(); ?>
        <form class="jp-form jp-submit-job" method="post">
            <?php wp_nonce_field('jp_submit_job','jp_submit_job_nonce'); ?>
            <div class="jp-two"><input name="job_title" required placeholder="Job Title"><input name="location" placeholder="Location"></div>
            <textarea name="job_summary" placeholder="Short summary"></textarea>
            <textarea name="job_description" required placeholder="Full job description"></textarea>
            <div class="jp-two"><input name="salary_min" type="number" placeholder="Salary Min"><input name="salary_max" type="number" placeholder="Salary Max"></div>
            <div class="jp-two"><input name="deadline" type="date"><input name="apply_url" type="url" placeholder="External apply URL"></div>
            <div class="jp-two"><select name="job_type"><option value="">Job Type</option><?php self::term_options('job_type'); ?></select><select name="job_category"><option value="">Category</option><?php self::term_options('job_category'); ?></select></div>
            <input name="video_url" type="url" placeholder="Video job description URL">
            <label><input type="checkbox" name="remote" value="1"> Remote / Hybrid friendly</label>
            <button class="jp-btn jp-btn-primary">Submit Job</button>
        </form><?php return ob_get_clean();
    }

    public static function shortcode_candidate_dashboard() {
        if (!is_user_logged_in()) return '<div class="jp-notice">Please log in to view your candidate dashboard.</div>';
        $uid = get_current_user_id(); $apps = get_posts(['post_type'=>'jp_application','author'=>$uid,'numberposts'=>20]); $saved = get_user_meta($uid, '_jp_saved_jobs', true); if (!is_array($saved)) $saved = [];
        ob_start(); echo '<div class="jp-dashboard"><h2>Candidate Dashboard</h2><div class="jp-tabs"><section><h3>Applications</h3>';
        foreach ($apps as $app) { $job = get_post_meta($app->ID,'_jp_job_id',true); echo '<div class="jp-row"><strong>'.esc_html(get_the_title($job)).'</strong><span>'.esc_html(get_post_meta($app->ID,'_jp_status',true) ?: 'new').'</span></div>'; }
        echo '</section><section><h3>Saved Jobs</h3>'; foreach ($saved as $job_id) echo self::job_card($job_id); echo '</section></div></div>'; return ob_get_clean();
    }

    public static function shortcode_employer_dashboard() {
        if (!is_user_logged_in()) return '<div class="jp-notice">Please log in to view your employer dashboard.</div>';
        $uid = get_current_user_id(); $jobs = get_posts(['post_type'=>'job','author'=>$uid,'numberposts'=>20,'post_status'=>['publish','pending','draft']]);
        ob_start(); echo '<div class="jp-dashboard"><h2>Employer Dashboard</h2><div class="jp-table"><div class="jp-table-head"><span>Job</span><span>Status</span><span>Applications</span></div>';
        foreach ($jobs as $job) { $apps = get_posts(['post_type'=>'jp_application','meta_key'=>'_jp_job_id','meta_value'=>$job->ID,'numberposts'=>-1]); echo '<div class="jp-table-row"><span>'.esc_html($job->post_title).'</span><span>'.esc_html($job->post_status).'</span><span>'.count($apps).'</span></div>'; }
        echo '</div></div>'; return ob_get_clean();
    }

    public static function shortcode_companies($atts) {
        $q = new WP_Query(['post_type'=>'company','post_status'=>'publish','posts_per_page'=>intval($atts['limit'] ?? 12)]); ob_start(); echo '<div class="jp-grid">';
        while($q->have_posts()) { $q->the_post(); $rating = self::average_rating(get_the_ID()); echo '<article class="jp-card"><h3><a href="'.esc_url(get_permalink()).'">'.esc_html(get_the_title()).'</a></h3><div class="jp-stars">'.esc_html(str_repeat('★',round($rating))).'</div><p>'.esc_html(wp_trim_words(get_the_excerpt() ?: get_the_content(),20)).'</p></article>'; }
        wp_reset_postdata(); echo '</div>'; return ob_get_clean();
    }

    public static function shortcode_pricing() {
        $q = new WP_Query(['post_type'=>'jp_plan','post_status'=>'publish','posts_per_page'=>12]); ob_start(); echo '<div class="jp-pricing jp-grid">';
        while($q->have_posts()) { $q->the_post(); echo '<article class="jp-card jp-plan"><h3>'.esc_html(get_the_title()).'</h3><div class="jp-price">$'.esc_html(get_post_meta(get_the_ID(),'_jp_price',true)).'</div><p>'.wp_kses_post(get_the_content()).'</p><a class="jp-btn jp-btn-primary" href="#">Choose Plan</a></article>'; }
        wp_reset_postdata(); echo '</div>'; return ob_get_clean();
    }

    public static function shortcode_messages() {
        if (!is_user_logged_in()) return '<div class="jp-notice">Please log in to use private messaging.</div>';
        $uid = get_current_user_id(); $msgs = get_posts(['post_type'=>'jp_message','numberposts'=>20,'meta_query'=>['relation'=>'OR',['key'=>'_jp_to_user','value'=>$uid],['key'=>'_jp_from_user','value'=>$uid]]]);
        ob_start(); echo '<div class="jp-messages"><h2>Private Messages</h2>'; foreach ($msgs as $m) echo '<div class="jp-message"><strong>'.esc_html($m->post_title).'</strong><p>'.esc_html(wp_trim_words($m->post_content,30)).'</p></div>'; echo '</div>'; return ob_get_clean();
    }

    public static function ajax_search_jobs() { check_ajax_referer(self::NONCE, 'nonce'); $q = self::query_jobs($_GET); ob_start(); while($q->have_posts()){ $q->the_post(); echo self::job_card(get_the_ID()); } wp_reset_postdata(); wp_send_json_success(['html'=>ob_get_clean()]); }

    public static function ajax_apply_job() {
        check_ajax_referer(self::NONCE, 'nonce'); if (!is_user_logged_in()) wp_send_json_error(['message'=>'Please log in first.']);
        $job_id = intval($_POST['job_id'] ?? 0); if (!$job_id) wp_send_json_error(['message'=>'Invalid job.']);
        $app_id = wp_insert_post(['post_type'=>'jp_application','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>'Application: '.get_the_title($job_id)]);
        update_post_meta($app_id,'_jp_job_id',$job_id); update_post_meta($app_id,'_jp_candidate_user_id',get_current_user_id()); update_post_meta($app_id,'_jp_status','new'); update_post_meta($app_id,'_jp_cover_letter',sanitize_textarea_field($_POST['message'] ?? ''));
        wp_mail(get_the_author_meta('user_email', get_post_field('post_author', $job_id)), 'New application received', 'A candidate applied to '.get_the_title($job_id));
        wp_send_json_success(['message'=>'Application submitted.']);
    }

    public static function ajax_save_job() { check_ajax_referer(self::NONCE, 'nonce'); if (!is_user_logged_in()) wp_send_json_error(['message'=>'Please log in first.']); $job_id = intval($_POST['job_id'] ?? 0); $saved = get_user_meta(get_current_user_id(), '_jp_saved_jobs', true); if (!is_array($saved)) $saved = []; if (!in_array($job_id, $saved, true)) $saved[] = $job_id; update_user_meta(get_current_user_id(), '_jp_saved_jobs', $saved); wp_send_json_success(['message'=>'Job saved.']); }

    public static function ajax_create_alert() { check_ajax_referer(self::NONCE, 'nonce'); $email = sanitize_email($_POST['email'] ?? ''); if (!$email) wp_send_json_error(['message'=>'Email required.']); $alert = wp_insert_post(['post_type'=>'jp_alert','post_status'=>'publish','post_title'=>'Alert: '.sanitize_text_field($_POST['keyword'] ?? '')]); update_post_meta($alert,'_jp_email',$email); update_post_meta($alert,'_jp_keyword',sanitize_text_field($_POST['keyword'] ?? '')); wp_send_json_success(['message'=>'Job alert created.']); }

    public static function ajax_send_message() { check_ajax_referer(self::NONCE, 'nonce'); if (!is_user_logged_in()) wp_send_json_error(['message'=>'Please log in first.']); $to = intval($_POST['to_user'] ?? 0); $msg = wp_insert_post(['post_type'=>'jp_message','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>sanitize_text_field($_POST['subject'] ?? 'Message'),'post_content'=>sanitize_textarea_field($_POST['message'] ?? '')]); update_post_meta($msg,'_jp_from_user',get_current_user_id()); update_post_meta($msg,'_jp_to_user',$to); wp_send_json_success(['message'=>'Message sent.']); }

    public static function ajax_review_company() { check_ajax_referer(self::NONCE, 'nonce'); if (!is_user_logged_in()) wp_send_json_error(['message'=>'Please log in first.']); $company = intval($_POST['company_id'] ?? 0); $review = wp_insert_post(['post_type'=>'jp_review','post_status'=>'publish','post_author'=>get_current_user_id(),'post_title'=>'Review for '.get_the_title($company),'post_content'=>sanitize_textarea_field($_POST['review'] ?? '')]); update_post_meta($review,'_jp_company_id',$company); update_post_meta($review,'_jp_rating',max(1,min(5,intval($_POST['rating'] ?? 5)))); wp_send_json_success(['message'=>'Review submitted.']); }

    public static function average_rating($company_id) { $reviews = get_posts(['post_type'=>'jp_review','numberposts'=>-1,'meta_key'=>'_jp_company_id','meta_value'=>$company_id]); if (!$reviews) return 0; $sum=0; foreach($reviews as $r) $sum += intval(get_post_meta($r->ID,'_jp_rating',true)); return $sum/count($reviews); }

    public static function job_columns($cols) { $cols['jp_location']='Location'; $cols['jp_deadline']='Deadline'; $cols['jp_featured']='Featured'; return $cols; }
    public static function job_column_content($col, $post_id) { if ($col==='jp_location') echo esc_html(get_post_meta($post_id,'_jp_location_text',true)); if ($col==='jp_deadline') echo esc_html(get_post_meta($post_id,'_jp_deadline',true)); if ($col==='jp_featured') echo get_post_meta($post_id,'_jp_featured',true)==='1'?'Yes':'No'; }
}

JPortal_Core::init();
register_activation_hook(__FILE__, ['JPortal_Core','activate']);
register_deactivation_hook(__FILE__, ['JPortal_Core','deactivate']);
