<?php
/*
  Plugin Name: WP Dev Helper - Under Construction
  Description: An awesome plugin that help WordPress developers to develop their themes faster than ever.
  Version: 1.0.0
  Author: Alexandre Menin
  Author URI: https://comet.com.br/
  Text Domain: under-construction-page
*/
// this is an include only WP file
if (!defined('ABSPATH')) {
  die;
}

define('UCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('UCP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('UCP_OPTIONS_KEY', 'ucp_options');
define('UCP_META_KEY', 'ucp_meta');
define('UCP_POINTERS_KEY', 'ucp_pointers');
define('UCP_NOTICES_KEY', 'ucp_notices');
define('UCP_SURVEYS_KEY', 'ucp_surveys');

// main plugin class
class UCP {
  static $version = 1;

  // get plugin version from header
  static function get_plugin_version() {
    $plugin_data = get_file_data(__FILE__, array('version' => 'Version'), 'plugin');
    self::$version = $plugin_data['version'];

    return $plugin_data['version'];
  } // get_plugin_version

  // hook things up
  static function init() {

    if (is_admin()) {
      // if the plugin was updated from ver < 1.20 upgrade settings array
      self::maybe_upgrade();

      // add UCP menu to admin tools menu group
      add_action('admin_menu', array(__CLASS__, 'admin_menu'));

      // settings registration
      add_action('admin_init', array(__CLASS__, 'register_settings'));

      // aditional links in plugin description
      add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(__CLASS__, 'plugin_action_links'));
      add_filter('plugin_row_meta', array(__CLASS__, 'plugin_meta_links'), 10, 2);
      add_filter('admin_footer_text', array(__CLASS__, 'admin_footer_text'));

      // manages admin header notifications
      add_action('admin_notices', array(__CLASS__, 'admin_notices'));
      add_action('admin_action_ucp_dismiss_notice', array(__CLASS__, 'dismiss_notice'));
      add_action('admin_action_ucp_change_status', array(__CLASS__, 'change_status'));
      add_action('admin_action_ucp_reset_settings', array(__CLASS__, 'reset_settings'));

      // enqueue admin scripts
      add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_enqueue_scripts'));

      // AJAX endpoints
      add_action('wp_ajax_ucp_dismiss_pointer', array(__CLASS__, 'dismiss_pointer_ajax'));
      add_action('wp_ajax_ucp_dismiss_survey', array(__CLASS__, 'dismiss_survey_ajax'));
      add_action('wp_ajax_ucp_submit_earlybird', array(__CLASS__, 'submit_earlybird_ajax'));
    } else {
      // main plugin logic
      add_action('wp', array(__CLASS__, 'display_construction_page'), 0, 1);

      // disable feeds
      add_action('do_feed_rdf', array(__CLASS__, 'disable_feed'), 0, 1);
      add_action('do_feed_rss', array(__CLASS__, 'disable_feed'), 0, 1);
      add_action('do_feed_rss2', array(__CLASS__, 'disable_feed'), 0, 1);
      add_action('do_feed_atom', array(__CLASS__, 'disable_feed'), 0, 1);

      add_action('wp_footer', array(__CLASS__, 'whitelisted_notice'));
    } // if not admin

    // admin bar notice for frontend & backend
    add_action('wp_before_admin_bar_render', array(__CLASS__, 'admin_bar'));
    add_action('wp_head', array(__CLASS__, 'admin_bar_style'));
    add_action('admin_head', array(__CLASS__, 'admin_bar_style'));

  } // init


  // activate doesn't get fired on upgrades so we have to compensate
  public static function maybe_upgrade() {
    $meta = self::get_meta();
    $options = self::get_options();

    // added in v1.70 to rename roles to whitelisted_roles
    if (isset($options['roles'])) {
      $options['whitelisted_roles'] = $options['roles'];
      unset($options['roles']);
      update_option(UCP_OPTIONS_KEY, $options);
    }

    // check if we need to convert options from the old format to new, or maybe it is already done
    if (isset($meta['options_ver']) && $meta['options_ver'] == self::$version) {
      return;
    }

    if (get_option('set_size') || get_option('set_tweet') || get_option('set_fb') || get_option('set_font') || get_option('set_msg') || get_option('set_opt') || get_option('set_admin')) {
      // convert old options to new
      $options['status'] = (get_option('set_opt') === 'Yes')? '1': '0';
      $options['content'] = trim(get_option('set_msg'));
      $options['whitelisted_roles'] = (get_option('set_admin') === 'No')? array('administrator'): array();
      $options['social_facebook'] = trim(get_option('set_fb'));
      $options['social_twitter'] = trim(get_option('set_tweet'));
      update_option(UCP_OPTIONS_KEY, $options);

      delete_option('set_size');
      delete_option('set_tweet');
      delete_option('set_fb');
      delete_option('set_font');
      delete_option('set_msg');
      delete_option('set_opt');
      delete_option('set_admin');

      self::reset_pointers();
    }

    // we update only once
    $meta['options_ver'] = self::$version;
    update_option(UCP_META_KEY, $meta);
  } // maybe_upgrade


  // get plugin's options
  static function get_options() {
    $options = get_option(UCP_OPTIONS_KEY, array());

    if (!is_array($options)) {
      $options = array();
    }
    $options = array_merge(self::default_options(), $options);

    return $options;
  } // get_options


  // get plugin's meta data
  static function get_meta() {
    $meta = get_option(UCP_META_KEY, array());

    if (!is_array($meta) || empty($meta)) {
      $meta['first_version'] = self::get_plugin_version();
      $meta['first_install'] = time();
      update_option(UCP_META_KEY, $meta);
    }

    return $meta;
  } // get_meta


  // fetch and display the construction page if it's enabled or preview requested
  static function display_construction_page() {
    $options = self::get_options();
    $request_uri = trailingslashit(strtolower(@parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));

    // just to be on the safe side
    if (defined('DOING_CRON') && DOING_CRON) {
      return false;
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
      return false;
    }
    if (defined('WP_CLI') && WP_CLI) {
      return false;
    }

    // some URLs have to be accessible at all times
    if ($request_uri == '/wp-admin/' ||
        $request_uri == '/feed/' ||
        $request_uri == '/feed/rss/' ||
        $request_uri == '/feed/rss2/' ||
        $request_uri == '/feed/rdf/' ||
        $request_uri == '/feed/atom/' ||
        $request_uri == '/admin/' ||
        $request_uri == '/wp-login.php') {
      return;
    }

    if (true == self::is_construction_mode_enabled(false)
        || (is_user_logged_in() && isset($_GET['ucp_preview']))) {
      header(self::wp_get_server_protocol() . ' 200 OK');
      if ($options['end_date'] && $options['end_date'] != '0000-00-00 00:00') {
        header('Retry-After: ' . date('D, d M Y H:i:s T', strtotime($options['end_date'])));
      } else {
        header('Retry-After: ' . DAY_IN_SECONDS);
      }

      $themes = self::get_themes();
      if (!empty($_GET['theme']) && !empty($themes[$_GET['theme']])) {
        $theme = $_GET['theme'];
      } else {
        $theme = $options['theme'];
      }

      echo self::get_template($theme);
      exit;
    }
  } // display_construction_page


  // keeping compatibility with WP < v4.4
  static function wp_get_server_protocol() {
    $protocol = $_SERVER['SERVER_PROTOCOL'];
    if (!in_array($protocol, array('HTTP/1.1', 'HTTP/2', 'HTTP/2.0'))) {
        $protocol = 'HTTP/1.0';
    }

    return $protocol;
  } // wp_get_server_protocol


  // disables feed if necessary
  static function disable_feed() {
    if (true == self::is_construction_mode_enabled(false)) {
      echo '<?xml version="1.0" encoding="UTF-8" ?><status>Service unavailable.</status>';
      exit;
    }
  } // disable_feed


  // enqueue CSS and JS scripts in admin
  static function admin_enqueue_scripts($hook) {
    $surveys = get_option(UCP_SURVEYS_KEY);
    $meta = self::get_meta();
    $pointers = get_option(UCP_POINTERS_KEY);

    // auto remove welcome pointer when options are opened
    if (isset($pointers['welcome']) && 'settings_page_ucp' == $hook) {
      unset($pointers['welcome']);
      update_option(UCP_POINTERS_KEY, $pointers);
    }

    // survey is shown min 5min after install
    // DISABLED
    if (0 && empty($surveys['usage']) && time() - $meta['first_install'] > 300) {
      $open_survey = true;
    } else {
      $open_survey = false;
    }

    $promo = self::is_promo_active();
    if ($promo == 'welcome') {
      $countdown = $meta['first_install'] + HOUR_IN_SECONDS;
    } else {
      $countdown = 0;
    }

    $js_localize = array('undocumented_error' => __('An undocumented error has occured. Please refresh the page and try again.', 'under-construction-page'),
                         'plugin_name' => __('UnderConstructionPage', 'under-construction-page'),
                         'settings_url' => admin_url('options-general.php?page=ucp'),
                         'whitelisted_users_placeholder' => __('Select whitelisted user(s)', 'under-construction-page'),
                         'open_survey' => $open_survey,
                         'promo_countdown' => $countdown,
                         'dialog_upsell_title' => 'WP Dev Helper',
                         'nonce_dismiss_survey' => wp_create_nonce('ucp_dismiss_survey'),
                         'nonce_submit_survey' => wp_create_nonce('ucp_submit_survey'),
                         'nonce_submit_earlybird' => wp_create_nonce('ucp_submit_earlybird'),
                         'nonce_submit_support_message' => wp_create_nonce('ucp_submit_support_message'),
                         'deactivate_confirmation' => __('Are you sure you want to deactivate Under Construction plugin?' . "\n" . 'If you are removing it because of a problem please contact our support. They will be more than happy to help.', 'under-construction-page'));

    if ('settings_page_ucp' == $hook) {
      wp_enqueue_style('wp-jquery-ui-dialog');
      wp_enqueue_style('ucp-select2', UCP_PLUGIN_URL . 'css/select2.min.css', array(), self::$version);
      wp_enqueue_style('ucp-admin', UCP_PLUGIN_URL . 'css/ucp-admin.css', array(), self::$version);

      wp_enqueue_script('jquery-ui-tabs');
      wp_enqueue_script('jquery-ui-dialog');
      wp_enqueue_script('ucp-jquery-plugins', UCP_PLUGIN_URL . 'js/ucp-jquery-plugins.js', array('jquery'), self::$version, true);
      wp_enqueue_script('ucp-select2', UCP_PLUGIN_URL . 'js/select2.min.js', array(), self::$version, true);
      wp_enqueue_script('ucp-admin', UCP_PLUGIN_URL . 'js/ucp-admin.js', array('jquery'), self::$version, true);
      wp_localize_script('ucp-admin', 'ucp', $js_localize);

      // fix for agressive plugins
      wp_dequeue_style('uiStyleSheet');
      wp_dequeue_style('wpcufpnAdmin' );
      wp_dequeue_style('unifStyleSheet' );
      wp_dequeue_style('wpcufpn_codemirror');
      wp_dequeue_style('wpcufpn_codemirrorTheme');
      wp_dequeue_style('collapse-admin-css');
      wp_dequeue_style('jquery-ui-css');
      wp_dequeue_style('tribe-common-admin');
      wp_dequeue_style('file-manager__jquery-ui-css');
      wp_dequeue_style('file-manager__jquery-ui-css-theme');
      wp_dequeue_style('wpmegmaps-jqueryui');
      wp_dequeue_style('wp-botwatch-css');
    }

    // disabled - regular deactivation is back
    if (false && 'plugins.php' == $hook) {
      wp_enqueue_style('wp-jquery-ui-dialog');
      wp_enqueue_style('ucp-admin-plugins', UCP_PLUGIN_URL . 'css/ucp-admin-plugins.css', array(), self::$version);

      wp_enqueue_script('jquery-ui-dialog');
      wp_enqueue_script('ucp-admin-plugins', UCP_PLUGIN_URL . 'js/ucp-admin-plugins.js', array('jquery'), self::$version, true);
      wp_localize_script('ucp-admin-plugins', 'ucp', $js_localize);
    }

    if ($pointers) {
      $pointers['_nonce_dismiss_pointer'] = wp_create_nonce('ucp_dismiss_pointer');
      wp_enqueue_script('wp-pointer');
      wp_enqueue_script('ucp-pointers', plugins_url('js/ucp-admin-pointers.js', __FILE__), array('jquery'), self::$version, true);
      wp_enqueue_style('wp-pointer');
      wp_localize_script('wp-pointer', 'ucp_pointers', $pointers);
      wp_localize_script('jquery', 'ucp', $js_localize);
    }
  } // admin_enqueue_scripts


  // permanently dismiss a pointer
  static function dismiss_pointer_ajax() {
    check_ajax_referer('ucp_dismiss_pointer');

    $pointers = get_option(UCP_POINTERS_KEY);
    $pointer = trim($_POST['pointer']);

    if (empty($pointers) || empty($pointers[$pointer])) {
      wp_send_json_error();
    }

    unset($pointers[$pointer]);
    update_option(UCP_POINTERS_KEY, $pointers);

    wp_send_json_success();
  } // dismiss_pointer_ajax


  // permanently dismiss a survey
  static function dismiss_survey_ajax() {
    check_ajax_referer('ucp_dismiss_survey');

    $surveys = get_option(UCP_SURVEYS_KEY, array());
    $survey = trim($_POST['survey']);

    $surveys[$survey] = -1;
    update_option(UCP_SURVEYS_KEY, $surveys);

    wp_send_json_success();
  } // dismiss_survey_ajax


  // encode email for frontend use
  static function encode_email($email) {
    $len = strlen($email);
    $out = '';

    for ($i = 0; $i < $len; $i++) {
      $out .= '&#'. ord($email[$i]) . ';';
    }

    return $out;
  } // encode_email


  // parse shortcode alike variables
  static function parse_vars($string) {
    $org_string = $string;

    $vars = array('site-title' => get_bloginfo('name'),
                  'site-tagline' => get_bloginfo('description'),
                  'site-description' => get_bloginfo('description'),
                  'site-url' => trailingslashit(get_home_url()),
                  'wp-url' => trailingslashit(get_site_url()),
                  'site-login-url' => get_site_url() . '/wp-login.php');

    foreach ($vars as $var_name => $var_value) {
      $var_name = '[' . $var_name . ']';
      $string = str_ireplace($var_name, $var_value, $string);
    }

    $string = apply_filters('ucp_parse_vars', $string, $org_string, $vars);

    return $string;
  } // parse_vars

  // generate HTML from social icons
  static function generate_social_icons($options, $template_id) {
    $out = '';

    if (!empty($options['social_facebook'])) {
      $out .= '<a title="Facebook" href="' . $options['social_facebook'] . '" target="_blank"><i class="fa fa-facebook-square fa-3x"></i></a>';
    }
    if (!empty($options['social_twitter'])) {
      $out .= '<a title="Twitter" href="' . $options['social_twitter'] . '" target="_blank"><i class="fa fa-twitter-square fa-3x"></i></a>';
    }
    if (!empty($options['social_google'])) {
      $out .= '<a title="Google+" href="' . $options['social_google'] . '" target="_blank"><i class="fa fa-google-plus-square fa-3x"></i></a>';
    }
    if (!empty($options['social_linkedin'])) {
      $out .= '<a title="LinkedIn" href="' . $options['social_linkedin'] . '" target="_blank"><i class="fa fa-linkedin-square fa-3x"></i></a>';
    }
    if (!empty($options['social_youtube'])) {
      $out .= '<a title="YouTube" href="' . $options['social_youtube'] . '" target="_blank"><i class="fa fa-youtube-square fa-3x"></i></a>';
    }
    if (!empty($options['social_vimeo'])) {
      $out .= '<a title="Vimeo" href="' . $options['social_vimeo'] . '" target="_blank"><i class="fa fa-vimeo-square fa-3x"></i></a>';
    }
    if (!empty($options['social_pinterest'])) {
      $out .= '<a title="Pinterest" href="' . $options['social_pinterest'] . '" target="_blank"><i class="fa fa-pinterest-square fa-3x"></i></a>';
    }
    if (!empty($options['social_dribbble'])) {
      $out .= '<a title="Dribbble" href="' . $options['social_dribbble'] . '" target="_blank"><i class="fa fa-dribbble fa-3x"></i></a>';
    }
    if (!empty($options['social_behance'])) {
      $out .= '<a title="Behance" href="' . $options['social_behance'] . '" target="_blank"><i class="fa fa-behance-square fa-3x"></i></a>';
    }
    if (!empty($options['social_instagram'])) {
      $out .= '<a title="Instagram" href="' . $options['social_instagram'] . '" target="_blank"><i class="fa fa-instagram fa-3x"></i></a>';
    }
    if (!empty($options['social_tumblr'])) {
      $out .= '<a title="Tumblr" href="' . $options['social_tumblr'] . '" target="_blank"><i class="fa fa-tumblr-square fa-3x"></i></a>';
    }
    if (!empty($options['social_vk'])) {
      $out .= '<a title="VK" href="' . $options['social_vk'] . '" target="_blank"><i class="fa fa-vk fa-3x"></i></a>';
    }
    if (!empty($options['social_skype'])) {
      $out .= '<a title="Skype" href="skype:' . $options['social_skype'] . '?chat"><i class="fa fa-skype fa-3x"></i></a>';
    }
    if (!empty($options['social_whatsapp'])) {
      $prefix = '55';
      $number = $options['social_whatsapp'];
      $msg = 'Olá!';
      $number = $prefix . $number;
      $number = preg_replace('/\(|\)|\s+|\+|\-/', '', $number);
      $msg = str_replace(' ', '%20', $msg); //mensage
      $url = 'https://api.whatsapp.com/send?phone=' . $number . '&text=' . $msg;

      $out .= '<a title="WhatsApp" href="' . $url . '" target="_blank"><i class="fa fa-whatsapp fa-3x"></i></a>';
    }
    if (!empty($options['social_telegram'])) {
      $out .= '<a title="Telegram" href="' . $options['social_telegram'] . '"><i class="fa fa-telegram fa-3x"></i></a>';
    }
    if (!empty($options['social_email'])) {
      $out .= '<a title="Email" href="mailto:' . self::encode_email($options['social_email']) . '"><i class="fa fa-envelope fa-3x"></i></a>';
    }
    if (!empty($options['social_phone'])) {
      $out .= '<a title="Phone" href="tel:' . $options['social_phone'] . '"><i class="fa fa-phone-square fa-3x"></i></a>';
    }

    return $out;
  } // generate_social_icons


  // shortcode for inserting things in header
  static function generate_head($options, $template_id) {
    $out = '';

    $out .= '<link rel="stylesheet" href="' . trailingslashit(UCP_PLUGIN_URL . 'themes/css') . 'bootstrap.min.css?v=' . self::$version . '" type="text/css">' . "\n";
    $out .= '<link rel="stylesheet" href="' . trailingslashit(UCP_PLUGIN_URL . 'themes/css') . 'common.css?v=' . self::$version . '" type="text/css">' . "\n";
    $out .= '<link rel="stylesheet" href="' . trailingslashit(UCP_PLUGIN_URL . 'themes/' . $template_id) . 'style.css?v=' . self::$version . '" type="text/css">' . "\n";
    $out .= '<link rel="stylesheet" href="' . trailingslashit(UCP_PLUGIN_URL . 'themes/css') . 'font-awesome.min.css?v=' . self::$version . '" type="text/css">' . "\n";

    $out .= '<link rel="shortcut icon" type="image/png" href="' . trailingslashit(UCP_PLUGIN_URL . 'themes/images') . 'favicon.png" />';


    if (!empty($options['ga_tracking_id'])) {
      $out .= "
      <script>
        (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
        (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
        m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
        })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
        ga('create', '{$options['ga_tracking_id']}', 'auto');
        ga('send', 'pageview');
      </script>";
    }

    if (!empty($options['custom_css'])) {
      $out .= "\n" . '<style type="text/css">' . $options['custom_css'] . '</style>';
    }

    $out = apply_filters('ucp_head', $out, $options, $template_id);

    return trim($out);
  } // generate_head


  // shortcode for inserting things in footer
  static function generate_footer($options, $template_id) {
    $out = '';

    // todo translate
    if ($options['linkback'] == '1') {
      $tmp = md5(get_site_url());
      if ($tmp[0] < '4') {
        $out .= '<p id="linkback">Create stunning <a href="' . self::generate_web_link('show-love-1')  . '" target="_blank">under construction pages for WordPress</a>. Completely free.</p>';
      } elseif ($tmp[0] < '8') {
        $out .= '<p id="linkback">Create a <a href="' . self::generate_web_link('show-love-2')  . '" target="_blank">free under construction page for WordPress</a> like this one in under a minute.</p>';
      } elseif ($tmp[0] < 'c') {
        $out .= '<p id="linkback">Join more than 100,000 happy people using the <a href="https://wordpress.org/plugins/under-construction-page/" target="_blank">free Under Construction Page plugin for WordPress</a>.</p>';
      } else {
        $out .= '<p id="linkback">Create free <a href="' . self::generate_web_link('show-love-3')  . '" target="_blank">maintenance mode pages for WordPress</a>.</p>';
      }
    }

    if ($options['login_button'] == '1') {
      if (is_user_logged_in()) {
        $out .= '<div id="login-button" class="loggedin">';
        $out .= '<a title="' . __('Open WordPress admin', 'under-construction-page') . '" href="' . get_site_url() . '/wp-admin/"><i class="fa fa-wordpress fa-2x" aria-hidden="true"></i></a>';
      } else {
        $out .= '<div id="login-button" class="loggedout">';
        $out .= '<a title="' . __('Log in to WordPress admin', 'under-construction-page') . '" href="' . get_site_url() . '/wp-login.php"><i class="fa fa-wordpress fa-2x" aria-hidden="true"></i></a>';
      }
      $out .= '</div>';
    }

    // Remove tags that are not used in script code
    if (!empty($options['custom_footer_code'])) {
      $rm_code = array('<script>', '</script>', '<style>', '</style>');
      $filter_script = str_replace($rm_code, '', $options['custom_footer_code']);
      $out .= "\n" . '<script>' . $filter_script . '</script>';
    }

    $out = apply_filters('ucp_footer', $out, $options, $template_id);

    return $out;
  } // generate_footer


  // returnes parsed template
  static function get_template($template_id) {
    $vars = array();
    $options = self::get_options();

    $vars['version'] = self::$version;
    $vars['site-url'] = trailingslashit(get_home_url());
    $vars['wp-url'] = trailingslashit(get_site_url());
    $vars['theme-url'] = trailingslashit(UCP_PLUGIN_URL . 'themes/' . $template_id);
    $vars['theme-url-common'] = trailingslashit(UCP_PLUGIN_URL . 'themes');
    $vars['title'] = self::parse_vars($options['title']);
    $vars['generator'] = __('Free UnderConstructionPage plugin for WordPress', 'under-construction-page');
    $vars['heading1'] = self::parse_vars($options['heading1']);
    $vars['content'] = nl2br(self::parse_vars($options['content']));
    $vars['description'] = self::parse_vars($options['description']);
    $vars['social-icons'] = self::generate_social_icons($options, $template_id);
    $vars['head'] = self::generate_head($options, $template_id);
    $vars['footer'] = self::generate_footer($options, $template_id);

    $vars = apply_filters('ucp_get_template_vars', $vars, $template_id, $options);

    ob_start();
    require UCP_PLUGIN_DIR . 'themes/' . $template_id . '/index.php';
    $template = ob_get_clean();

    foreach ($vars as $var_name => $var_value) {
      $var_name = '[' . $var_name . ']';
      $template = str_ireplace($var_name, $var_value, $template);
    }

    $template = apply_filters('ucp_get_template', $template, $vars, $options);

    return $template;
  } // get_template


  // checks if construction mode is enabled for the current visitor
  static function is_construction_mode_enabled($settings_only = false) {
    $options = self::get_options();
    $current_user = wp_get_current_user();

    $override_status = apply_filters('ucp_is_construction_mode_enabled', null, $options);
    if (is_bool($override_status)) {
      return $override_status;
    }

    // just check if it's generally enabled
    if ($settings_only) {
      if ($options['status']) {
        return true;
      } else {
        return false;
      }
    } else {
      // check if enabled for current user
      if (!$options['status']) {
        return false;
      } elseif (self::user_has_role($options['whitelisted_roles'])) {
        return false;
      } elseif (in_array($current_user->ID, $options['whitelisted_users'])) {
        return false;
      } elseif (strlen($options['end_date']) === 16 && $options['end_date'] !== '0000-00-00 00:00' && $options['end_date'] < current_time('mysql')) {
        return false;
      } else {
        return true;
      }
    }
  } // is_construction_mode_enabled


  // check if user has the specified role
  static function user_has_role($roles) {
    $current_user = wp_get_current_user();

    if ($current_user->roles) {
      $user_role = $current_user->roles[0];
    } else {
      $user_role = 'guest';
    }

    return in_array($user_role, $roles);
  } // user_has_role


  // frontend notification when UCP is enabled but current user is whitelisted
  static function whitelisted_notice() {
    $notices = get_option(UCP_NOTICES_KEY);
    $dismiss_url = add_query_arg(array('action' => 'ucp_dismiss_notice', 'notice' => 'whitelisted', 'redirect' => urlencode($_SERVER['REQUEST_URI'])), admin_url('admin.php'));

    if (empty($notices['dismiss_whitelisted']) &&
        is_user_logged_in() &&
        self::is_construction_mode_enabled(true) &&
        !self::is_construction_mode_enabled(false))
        // keeping everything inline due to minimal CSS
        echo '<div style="background-color: #333; line-height: 140%; font-size: 14px; position: fixed; display: block; top: 50px; z-index: 99999; color: #fefefe; padding: 20px 35px 20px 20px; width: 500px; border: thin solid #fefefe; left: -1px;"><a style="color: #ef5350; font-weight: 900; text-decoration: none; position: absolute; top: 7px; right: 10px;" href="' . $dismiss_url . '" alt="Dismiss notice" onclick="window.location.href = \'' . $dismiss_url . '\'; return false;" title="Dismiss notice">X</a><b>' . __('<b>Under Construction Mode is enabled</b> but you are whitelisted so you see the normal site.', 'under-construction-page') . '<br><a href="' . get_home_url() . '/?ucp_preview" style="text-decoration: underline; color: #fefefe;">' . __('Preview UnderConstructionPage', 'under-construction-page') . '</a><br><a href="' . admin_url('options-general.php?page=ucp') . '" style="text-decoration: underline; color: #fefefe;">' . __('Configure UnderConstructionPage', 'under-construction-page') . '</a></div>';
  } // whitelisted_notification


  // displays various notices in admin header
  static function admin_notices() {
    $notices = get_option(UCP_NOTICES_KEY);
    $options = self::get_options();
    $meta = self::get_meta();
    $current_user = wp_get_current_user();
    $shown = false;
    $promo = self::is_promo_active();

    $name = '';
    if (!empty($current_user->user_firstname)) {
      $name = ' ' . $current_user->user_firstname;
    }

    // ask for rating; disabled
    // todo: translate strings
    if (false && empty($notices['dismiss_rate']) &&
        (time() - $meta['first_install']) > (DAY_IN_SECONDS * 1.0)) {
      $rate_url = 'https://comet.com.br/contato';
      $dismiss_url = add_query_arg(array('action' => 'ucp_dismiss_notice', 'notice' => 'rate', 'redirect' => urlencode($_SERVER['REQUEST_URI'])), admin_url('admin.php'));

      echo '<div id="ucp_rate_notice" class="notice-info notice"><p>Hi' . $name . '!<br>We saw you\'ve been using the <b class="ucp-logo" style="font-weight: bold;">UnderConstructionPage</b> plugin for a few days (that\'s awesome!) and wanted to ask for your help to <b>make the plugin better</b>.<br>We just need a minute of your time to rate the plugin. It helps us out a lot!';

      echo '<br><a target="_blank" href="' . esc_url($rate_url) . '" style="vertical-align: baseline; margin-top: 15px;" class="button-primary">' . __('Help make the plugin better by rating it', 'under-construction-page') . '</a>';
      echo '&nbsp;&nbsp;&nbsp;&nbsp;<a href="' . esc_url($dismiss_url) . '">' . __('I\'ve already rated the plugin', 'under-construction-page') . '</a>';
      echo '<br><br><b>' . __('Thank you very much! The UCP team', 'under-construction-page') . '</b>';
      echo '</p></div>';
      $shown = true;
    }

    // end date in past
    if (self::is_plugin_page() && self::is_construction_mode_enabled(true) && !empty($options['end_date']) && $options['end_date'] != '0000-00-00 00:00' && $options['end_date'] < current_time('mysql')) {
      echo '<div id="ucp_end_date_notice" class="notice-error notice"><p>Under construction mode is enabled but the <a href="#end_date" class="change_tab" data-tab="0">end date</a> is set to a past date so the <b>under construction page will not be shown</b>. Either move the <a href="#end_date" class="change_tab" data-tab="0">end date</a> to a future date or disable it.</p></div>';
      $shown = true;
    }

  } // notices


  // handle dismiss button for notices
  static function dismiss_notice() {
    if (empty($_GET['notice'])) {
      wp_safe_redirect(admin_url());
      exit;
    }

    $notices = get_option(UCP_NOTICES_KEY, array());

    if ($_GET['notice'] == 'rate') {
      $notices['dismiss_rate'] = true;
    } elseif ($_GET['notice'] == 'translate') {
      $notices['dismiss_translate'] = true;
    } elseif ($_GET['notice'] == 'whitelisted') {
      $notices['dismiss_whitelisted'] = true;
    } elseif ($_GET['notice'] == 'olduser') {
      $notices['dismiss_olduser'] = true;
    } elseif ($_GET['notice'] == 'welcome') {
      $notices['dismiss_welcome'] = true;
    } else {
      wp_safe_redirect(admin_url());
      exit;
    }

    update_option(UCP_NOTICES_KEY, $notices);

    if (!empty($_GET['redirect'])) {
      wp_safe_redirect($_GET['redirect']);
    } else {
      wp_safe_redirect(admin_url());
    }

    exit;
  } // dismiss_notice


  // reset all settings to default values
  static function reset_settings() {
    check_admin_referer('ucp_reset_settings');

    if (false === current_user_can('administrator')) {
      wp_safe_redirect(admin_url());
      exit;
    }

    $options = self::default_options();
    update_option(UCP_OPTIONS_KEY, $options);

    if (!empty($_GET['redirect'])) {
      wp_safe_redirect($_GET['redirect']);
    } else {
      wp_safe_redirect(admin_url());
    }

    exit;
  } // reset_settings


  // change status via admin bar
  static function change_status() {
    check_admin_referer('ucp_change_status');

    if (false === current_user_can('administrator') || empty($_GET['new_status'])) {
      wp_safe_redirect(admin_url());
      exit;
    }

    $options = self::get_options();

    if ($_GET['new_status'] == 'enabled') {
      $options['status'] = '1';
    } else {
      $options['status'] = '0';
    }

    update_option(UCP_OPTIONS_KEY, $options);

    if (!empty($_GET['redirect'])) {
      wp_safe_redirect($_GET['redirect']);
    } else {
      wp_safe_redirect(admin_url());
    }

    exit;
  } // change_status


  static function admin_bar_style() {
    // admin bar has to be anabled, user an admin and custom filter true
    if (false === is_admin_bar_showing() || false === current_user_can('administrator') || false === apply_filters('ucp_show_admin_bar', true)) {
      return;
    }

    // no sense in loading a new CSS file for 2 lines of CSS
    $custom_css = '<style type="text/css">#wpadminbar ul li#wp-admin-bar-ucp-info { padding: 5px 0; } #wpadminbar ul li#wp-admin-bar-ucp-settings, #wpadminbar ul li#wp-admin-bar-ucp-status { padding-bottom: 2px; } #wpadminbar i.ucp-status-dot { font-size: 17px; margin-top: -7px; color: #02ca02; height: 17px; display: inline-block; } #wpadminbar i.ucp-status-dot-enabled { color: #87c826; } #wpadminbar i.ucp-status-dot-disabled { color: #ef5350; } #wpadminbar #ucp-status-wrapper { display: inline; border: 1px solid rgba(240,245,250,.7); padding: 0; margin: 0 0 0 5px; background: rgb(35, 40, 45); } #wpadminbar .ucp-status-btn { padding: 0 7px; color: #fff; } #wpadminbar #ucp-status-wrapper.off #ucp-status-off { background: #ef5350;} #wpadminbar #ucp-status-wrapper.on #ucp-status-on { background: #8bc34a; }#wp-admin-bar-under-construction-page img.logo { height: 17px; margin-bottom: 4px; padding-right: 3px; } body.wp-admin #wp-admin-bar-under-construction-page img.logo { margin-bottom: -4px; }</style>';

    echo $custom_css;
  } // admin_bar_style


  // add admin bar menu and status
  static function admin_bar() {
    global $wp_admin_bar;

    // only show to admins
    if (false === current_user_can('administrator') || false === apply_filters('ucp_show_admin_bar', true)) {
      return;
    }

    if (self::is_construction_mode_enabled(true)) {
      $main_label = '<img style="height: 17px; margin-bottom: -4px; padding-right: 3px;" src="' . UCP_PLUGIN_URL . 'images/ucp_icon.png" alt="' . __('Under construction mode is enabled', 'under-construction-page') . '" title="' . __('Under construction mode is enabled', 'under-construction-page') . '"> <span class="ab-label">' . __('UnderConstruction', 'under-construction-page') . ' <i class="ucp-status-dot ucp-status-dot-enabled">&#9679;</i></span>';
      $class = 'ucp-enabled';
      $action_url = add_query_arg(array('action' => 'ucp_change_status', 'new_status' => 'disabled', 'redirect' => urlencode($_SERVER['REQUEST_URI'])), admin_url('admin.php'));
      $action_url = wp_nonce_url($action_url, 'ucp_change_status');
      $action = __('Under Construction Mode', 'under-construction-page');
      $action .= '<a href="' . $action_url . '" id="ucp-status-wrapper" class="on"><span id="ucp-status-off" class="ucp-status-btn">OFF</span><span id="ucp-status-on" class="ucp-status-btn">ON</span></a>';
    } else {
      $main_label = '<img style="height: 17px; margin-bottom: -4px; padding-right: 3px;" src="' . UCP_PLUGIN_URL . 'images/ucp_icon.png" alt="' . __('Under construction mode is disabled', 'under-construction-page') . '" title="' . __('Under construction mode is disabled', 'under-construction-page') . '"> <span class="ab-label">' . __('UnderConstruction', 'under-construction-page') . ' <i class="ucp-status-dot ucp-status-dot-disabled">&#9679;</i></span>';
      $class = 'ucp-disabled';
      $action_url = add_query_arg(array('action' => 'ucp_change_status', 'new_status' => 'enabled', 'redirect' => urlencode($_SERVER['REQUEST_URI'])), admin_url('admin.php'));
      $action_url = wp_nonce_url($action_url, 'ucp_change_status');
      $action = __('Under Construction Mode', 'under-construction-page');
      $action .= '<a href="' . $action_url . '" id="ucp-status-wrapper" class="off"><span id="ucp-status-off" class="ucp-status-btn">OFF</span><span id="ucp-status-on" class="ucp-status-btn">ON</span></a>';
    }

    $wp_admin_bar->add_menu(array(
      'parent' => '',
      'id'     => 'under-construction-page',
      'title'  => $main_label,
      'href'   => admin_url('options-general.php?page=ucp'),
      'meta'   => array('class' => $class)
    ));
    $wp_admin_bar->add_node( array(
      'id'    => 'ucp-status',
      'title' => $action,
      'href'  => false,
      'parent'=> 'under-construction-page'
    ));
    $wp_admin_bar->add_node( array(
      'id'     => 'ucp-preview',
      'title'  => __('Preview', 'under-construction-page'),
      'meta'   => array('target' => 'blank'),
      'href'   => get_home_url() . '/?ucp_preview',
      'parent' => 'under-construction-page'
    ));
    $wp_admin_bar->add_node( array(
      'id'     => 'ucp-settings',
      'title'  => __('Settings', 'under-construction-page'),
      'href'   => admin_url('options-general.php?page=ucp'),
      'parent' => 'under-construction-page'
    ));
  } // admin_bar


  // add settings link to plugins page
  static function plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=ucp') . '" title="' . __('UnderConstruction Settings', 'under-construction-page') . '">' . __('Settings', 'under-construction-page') . '</a>';

    array_unshift($links, $settings_link);

    return $links;
  } // plugin_action_links


  // add links to plugin's description in plugins table
  static function plugin_meta_links($links, $file) {
    $support_link = '<a target="_blank" href="https://comet.com.br" title="' . __('Get help', 'under-construction-page') . '">' . __('Support', 'under-construction-page') . '</a>';


    if ($file == plugin_basename(__FILE__)) {
      $links[] = $support_link;
    }

    return $links;
  } // plugin_meta_links


  // additional powered by text in admin footer; only on UCP page
  static function admin_footer_text($text) {
    if (!self::is_plugin_page()) {
      return $text;
    }

    $text = '<i>' . __('Under Construction Page', 'under-construction-page') . ' v' . self::$version . ' by <a href="https://www.comet.com.br/" title="' . __('Visit our site', 'under-construction-page') . '" target="_blank">' . __('Comet', 'under-construction-page') . '</a>.</i> '. $text;

    return $text;
  } // admin_footer_text


  // test if we're on plugin's page
  static function is_plugin_page() {
    $current_screen = get_current_screen();

    if ($current_screen->id == 'settings_page_ucp') {
      return true;
    } else {
      return false;
    }
  } // is_plugin_page


  // create the admin menu item
  static function admin_menu() {
    add_options_page(__('UnderConstruction', 'under-construction-page'), __('UnderConstruction', 'under-construction-page'), 'manage_options', 'ucp', array(__CLASS__, 'main_page'));
  } // admin_menu


  // all settings are saved in one option
  static function register_settings() {
    register_setting(UCP_OPTIONS_KEY, UCP_OPTIONS_KEY, array(__CLASS__, 'sanitize_settings'));
  } // register_settings


  // set default settings
  static function default_options() {
    $defaults = array('status' => '0',
                      'end_date' => '',
                      'ga_tracking_id' => '',
                      'theme' => 'mad_designer',
                      'custom_css' => '',
                      'custom_footer_code' => '',
                      'title' => '[site-title] is under construction',
                      'description' => '[site-tagline]',
                      'heading1' => __('Sorry, we\'re doing some work on the site', 'under-construction-page'),
                      'content' => __('Thank you for being patient. We are doing some work on the site and will be back shortly.', 'under-construction-page'),
                      'social_facebook' => '',
                      'social_twitter' => '',
                      'social_google' => '',
                      'social_linkedin' => '',
                      'social_youtube' => '',
                      'social_vimeo' => '',
                      'social_pinterest' => '',
                      'social_dribbble' => '',
                      'social_behance' => '',
                      'social_instagram' => '',
                      'social_tumblr' => '',
                      'social_vk' => '',
                      'social_email' => '',
                      'social_phone' => '',
                      'social_skype' => '',
                      'social_telegram' => '',
                      'social_whatsapp' => '',
                      'login_button' => '1',
                      'linkback' => '0',
                      'whitelisted_roles' => array('administrator'),
                      'whitelisted_users' => array()
                      );

    $defaults_000 = array('status' => '1',
                      'end_date' => '',
                      'ga_tracking_id' => '',
                      'theme' => '000webhost',
                      'custom_css' => '',
                      'custom_footer_code' => '',
                      'title' => '[site-title] is under construction',
                      'description' => '[site-tagline]',
                      'heading1' => __('We\'re building our brand new site', 'under-construction-page'),
                      'content' => __('Powered by <a href="https://www.000webhost.com/" target="_blank">000webhost</a>.', 'under-construction-page'),
                      'social_facebook' => '',
                      'social_twitter' => '',
                      'social_google' => '',
                      'social_linkedin' => '',
                      'social_youtube' => '',
                      'social_vimeo' => '',
                      'social_pinterest' => '',
                      'social_dribbble' => '',
                      'social_behance' => '',
                      'social_instagram' => '',
                      'social_tumblr' => '',
                      'social_vk' => '',
                      'social_email' => '',
                      'social_phone' => '',
                      'social_skype' => '',
                      'social_telegram' => '',
                      'social_whatsapp' => '',
                      'login_button' => '1',
                      'linkback' => '0',
                      'whitelisted_roles' => array('administrator'),
                      'whitelisted_users' => array()
                      );

    if (stripos($_SERVER['HTTP_HOST'], '000webhost') !== false) {
      return $defaults_000;
    } else {
      return $defaults;
    }
  } // default_options


  // sanitize settings on save
  static function sanitize_settings($options) {
    $old_options = self::get_options();

    foreach ($options as $key => $value) {
      switch ($key) {
        case 'title':
        case 'description':
        case 'heading1':
        case 'content':
        case 'custom_css':
        case 'custom_footer_code':
        case 'social_facebook':
        case 'social_twitter':
        case 'social_google':
        case 'social_linkedin':
        case 'social_youtube':
        case 'social_vimeo':
        case 'social_pinterest':
        case 'social_dribbble':
        case 'social_behance':
        case 'social_instagram':
        case 'social_tumblr':
        case 'social_vk':
        case 'social_email':
        case 'social_phone':
        case 'social_telegram':
        case 'social_whatsapp':
          $options[$key] = trim($value);
        break;
        case 'ga_tracking_id':
          $options[$key] = substr(strtoupper(trim($value)), 0, 15);
        break;
        case 'end_date':
          $options[$key] = substr(trim($value), 0, 16);
        break;
      } // switch
    } // foreach

    $options['whitelisted_roles'] = empty($options['whitelisted_roles'])? array(): $options['whitelisted_roles'];
    $options['whitelisted_users'] = empty($options['whitelisted_users'])? array(): $options['whitelisted_users'];
    $options = self::check_var_isset($options, array('status' => 0, 'linkback' => 0, 'login_button' => 0));

    if (empty($options['end_date_toggle'])) {
      $options['end_date'] = '';
    }
    if ($options['end_date'] == '0000-00-00 00:00') {
      $options['end_date'] = '';
    }
    unset($options['end_date_toggle']);

    if (empty($options['ga_tracking_toggle'])) {
      $options['ga_tracking_id'] = '';
    }
    if (!empty($options['ga_tracking_id']) && preg_match('/^UA-\d{3,}-\d{1,3}$/', $options['ga_tracking_id']) === 0) {
      add_settings_error('ucp', 'ga_tracking_id', __('Please enter a valid Google Analytics Tracking ID or disable tracking.', 'under-construction-page'));
    }
    unset($options['ga_tracking_toggle']);


    // empty cache in 3rd party plugins
    if ($options != $old_options) {
      $notices = get_option(UCP_NOTICES_KEY);
      unset($notices['dismiss_whitelisted']);
      update_option(UCP_NOTICES_KEY, $notices);

      if (function_exists('w3tc_pgcache_flush')) {
        w3tc_pgcache_flush();
      }
      if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
      }
      if (class_exists('Endurance_Page_Cache')) {
        $epc = new Endurance_Page_Cache;
        $epc->purge_all();
      }
      if (class_exists('SG_CachePress_Supercacher') && method_exists('SG_CachePress_Supercacher', 'purge_cache')) {
        SG_CachePress_Supercacher::purge_cache(true);
      }
      if (class_exists('SiteGround_Optimizer\Supercacher\Supercacher')) {
        SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();
      }
      if (isset($GLOBALS['wp_fastest_cache']) && method_exists($GLOBALS['wp_fastest_cache'], 'deleteCache')) {
        $GLOBALS['wp_fastest_cache']->deleteCache(true);
      }
      if (is_callable(array('Swift_Performance_Cache', 'clear_all_cache'))) {
        Swift_Performance_Cache::clear_all_cache();
      }
    }

    return array_merge($old_options, $options);
  } // sanitize_settings


  // checkbox helper function
  static function checked($value, $current, $echo = false) {
    $out = '';

    if (!is_array($current)) {
      $current = (array) $current;
    }

    if (in_array($value, $current)) {
      $out = ' checked="checked" ';
    }

    if ($echo) {
      echo $out;
    } else {
      return $out;
    }
  } // checked


  // helper function for saving options, mostly checkboxes
  static function check_var_isset($values, $variables) {
    foreach ($variables as $key => $value) {
      if (!isset($values[$key])) {
        $values[$key] = $value;
      }
    }

    return $values;
  } // check_var_isset


  // helper function for creating dropdowns
  static function create_select_options($options, $selected = null, $output = true) {
    $out = "\n";

    if(!is_array($selected)) {
      $selected = array($selected);
    }

    foreach ($options as $tmp) {
      $data = '';
      if (isset($tmp['disabled'])) {
        $data .= ' disabled="disabled" ';
      }
      if (in_array($tmp['val'], $selected)) {
        $out .= "<option selected=\"selected\" value=\"{$tmp['val']}\"{$data}>{$tmp['label']}&nbsp;</option>\n";
      } else {
        $out .= "<option value=\"{$tmp['val']}\"{$data}>{$tmp['label']}&nbsp;</option>\n";
      }
    } // foreach

    if ($output) {
      echo $out;
    } else {
      return $out;
    }
  } // create_select_options



  // helper function to generate tagged buy links
  static function generate_web_link($placement = '', $page = '/', $params = array(), $anchor = '') {
    $base_url = 'https://comet.com.br';

    if ('/' != $page) {
      $page = '/' . trim($page, '/') . '/';
    }
    if ($page == '//') {
      $page = '/';
    }

    if (stripos($_SERVER['HTTP_HOST'], '000webhost') !== false) {
      $parts = array_merge(array('utm_source' => 'ucp-free-000webhost', 'utm_medium' => 'plugin', 'utm_content' => $placement, 'utm_campaign' => 'ucp-free-v' . self::$version), $params);
    } else {
      $parts = array_merge(array('utm_source' => 'ucp-free', 'utm_medium' => 'plugin', 'utm_content' => $placement, 'utm_campaign' => 'ucp-free-v' . self::$version), $params);
    }

    if (!empty($anchor)) {
      $anchor = '#' . trim($anchor, '#');
    }

    $out = $base_url . $page . '?' . http_build_query($parts, '', '&amp;') . $anchor;

    return $out;
  } // generate_web_link


  // first, main tab content
  static function tab_main() {
    $options = self::get_options();
    $default_options = self::default_options();

    echo '<div class="ucp-tab-content">';
    echo '<table class="form-table">';

    echo '<tr valign="top">
    <th scope="row"><label for="status">' . __('Under Construction Mode', 'under-construction-page') . '</label></th>
    <td>';

    echo '<div class="toggle-wrapper" id="main-status">
      <input type="checkbox" id="status" ' . self::checked(1, $options['status']) . ' type="checkbox" value="1" name="' . UCP_OPTIONS_KEY . '[status]">
      <label for="status" class="toggle"><span class="toggle_handler"></span></label>
    </div>';

    echo '<p class="description">' . __('By enabling construction mode users will not be able to access the site\'s content. They will only see the under construction page. To configure exceptions set <a class="change_tab" data-tab="3" href="#whitelisted-roles">whitelisted user roles</a>.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top">
    <th scope="row"><label for="end_date_toggle">' . __('Automatic End Date &amp; Time', 'under-construction-page') . '</label></th>
    <td>';
    echo '<div class="toggle-wrapper">
      <input type="checkbox" id="end_date_toggle" ' . self::checked(1, (empty($options['end_date']) || $options['end_date'] == '0000-00-00 00:00')? 0: 1) . ' type="checkbox" value="1" name="' . UCP_OPTIONS_KEY . '[end_date_toggle]">
      <label for="end_date_toggle" class="toggle"><span class="toggle_handler"></span></label>
    </div>';
    echo '<div id="end_date_wrapper"><input id="end_date" type="text" class="datepicker" name="' . UCP_OPTIONS_KEY . '[end_date]" value="' . esc_attr($options['end_date']) . '" placeholder="yyyy-mm-dd hh:mm"><span title="' . __('Open date & time picker', 'under-construction-page') . '" alt="' . __('Open date & time picker', 'under-construction-page') . '" class="show-datepicker dashicons dashicons-calendar-alt"></span>';
    echo '<p class="description">' . __('If enabled, construction mode will automatically stop showing on the selected date.
    This option will not "auto-enable" construction mode. Status has to be set to "On".', 'under-construction-page') . '</p></div>';
    echo '</td></tr>';

    echo '<tr valign="top">
    <th scope="row"><label for="ga_tracking_id_toggle">' . __('Google Analytics Tracking', 'under-construction-page') . '</label></th>
    <td>';
    echo '<div class="toggle-wrapper">
      <input type="checkbox" id="ga_tracking_id_toggle" ' . self::checked(1, empty($options['ga_tracking_id'])? 0: 1) . ' type="checkbox" value="1" name="' . UCP_OPTIONS_KEY . '[ga_tracking_toggle]">
      <label for="ga_tracking_id_toggle" class="toggle"><span class="toggle_handler"></span></label>
    </div>';
    echo '<div id="ga_tracking_id_wrapper"><input id="ga_tracking_id" type="text" class="code" name="' . UCP_OPTIONS_KEY . '[ga_tracking_id]" value="' . esc_attr($options['ga_tracking_id']) . '" placeholder="UA-xxxxxx-xx">';
    echo '<p class="description">' . __('Enter the unique tracking ID found in your GA tracking profile settings to track visits to pages.', 'under-construction-page') . '</p></div>';
    echo '</td></tr>';

    $reset_url = add_query_arg(array('action' => 'ucp_reset_settings', 'redirect' => urlencode($_SERVER['REQUEST_URI'])), admin_url('admin.php'));
    $reset_url = wp_nonce_url($reset_url, 'ucp_reset_settings');
    echo '<tr valign="top">
    <th scope="row"><label for="">' . __('Reset Settings', 'under-construction-page') . '</label></th>
    <td>';
    echo '<a href="' . $reset_url . '" class="button button-secondary reset-settings">' . __('Reset all settings to default values', 'under-construction-page') . '</a>';
    echo '<p class="description">' . __('By resetting all settings to their default values any customizations you have done will be lost. There is no undo.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '</table>';
    echo '</div>';

    self::footer_buttons();
  } // tab_main


  static function tab_content() {
    global $wpdb;
    $options = self::get_options();
    $default_options = self::default_options();

    echo '<div class="ucp-tab-content">';
    echo '<table class="form-table">';

    // Title of theme
    echo '<tr valign="top">
    <th scope="row"><label for="title">' . __('Title', 'under-construction-page') . '</label></th>
    <td><input type="text" id="title" class="regular-text" name="' . UCP_OPTIONS_KEY . '[title]" value="' . esc_attr($options['title']) . '" />';
    echo '<p class="description">Page title. Default: ' . $default_options['title'] . '</p>';
    echo '<p><b>Available shortcodes:</b> (only active in UC themes, not on the rest of the site)</p>
    <ul class="ucp-list">
    <li><code>[site-title]</code> - blog title, as set in <a href="options-general.php">Options - General</a></li>
    <li><code>[site-tagline]</code> - blog tagline, as set in <a href="options-general.php">Options - General</a></li>
    <li><code>[site-url]</code> - site address (URL), as set in <a href="options-general.php">Options - General</a></li>
    <li><code>[wp-url]</code> - WordPress address (URL), as set in <a href="options-general.php">Options - General</a></li>
    <li><code>[site-login-url]</code> - URL of the default site login page</li>
    </ul>';
    echo '</td></tr>';

    // Description
    echo '<tr valign="top">
    <th scope="row"><label for="description">' . __('Description', 'under-construction-page') . '</label></th>
    <td><input id="description" type="text" class="large-text" name="' . UCP_OPTIONS_KEY . '[description]" value="' . esc_attr($options['description']) . '" />';
    echo '<p class="description">Description meta tag (see above for available <a href="#title">shortcodes</a>). Default: ' . $default_options['description'] . '</p>';
    echo '</td></tr>';

    // Headline
    echo '<tr valign="top">
    <th scope="row"><label for="heading1">' . __('Headline', 'under-construction-page') . '</label></th>
    <td><input id="heading1" type="text" class="large-text" name="' . UCP_OPTIONS_KEY . '[heading1]" value="' . esc_attr($options['heading1']) . '" />';
    echo '<p class="description">Main heading/title (see above for available <a href="#title">shortcodes</a>). Default: ' . $default_options['heading1'] . '</p>';
    echo '</td></tr>';

    // Content
    echo '<tr valign="top" id="content_wrap">
    <th scope="row"><label for="content">' . __('Content', 'under-construction-page') . '</label></th>
    <td>';
    wp_editor($options['content'], 'content', array('tabfocus_elements' => 'insert-media-button,save-post', 'editor_height' => 250, 'resize' => 1, 'textarea_name' => UCP_OPTIONS_KEY . '[content]', 'drag_drop_upload' => 1));
    echo '<p class="description">All HTML elements are allowed. Shortcodes are not parsed except <a href="#title">UC theme ones</a>. Default: ' . $default_options['content'] . '</p>';
    echo '</td></tr>';

    // Show Some Love
    echo '<tr valign="top">
    <th scope="row"><label for="linkback">' . __('Show Some Love', 'under-construction-page') . '</label></th>
    <td>';
    echo '<div class="toggle-wrapper">
      <input type="checkbox" id="linkback" ' . self::checked(1, $options['linkback']) . ' type="checkbox" value="1" name="' . UCP_OPTIONS_KEY . '[linkback]">
      <label for="linkback" class="toggle"><span class="toggle_handler"></span></label>
    </div>';
    echo '<p class="description">Please help others learn about this free plugin by placing a small link in the footer. Thank you very much!</p>';
    echo '</td></tr>';
    echo '</td></tr>';

    // Login Button
    echo '<tr valign="top" id="login_button_wrap">
    <th scope="row"><label for="login_button">' . __('Login Button', 'under-construction-page') . '</label></th>
    <td>';
    echo '<div class="toggle-wrapper">
      <input type="checkbox" id="login_button" ' . self::checked(1, $options['login_button']) . ' type="checkbox" value="1" name="' . UCP_OPTIONS_KEY . '[login_button]">
      <label for="login_button" class="toggle"><span class="toggle_handler"></span></label>
    </div>';
    echo '<p class="description">Show a discrete link to the login form, or WP admin if you\'re logged in, in the lower right corner of the page.</p>';
    echo '</td></tr>';
    echo '</table>';

    self::footer_buttons();

    echo '<h2 class="title">' . __('Social &amp; Contact Icons', 'under-construction-page') . '</h2>';

    echo '<table class="form-table" id="ucp-social-icons">';
    echo '<tr valign="top">
    <th scope="row"><label for="social_facebook">' . __('Facebook Page', 'under-construction-page') . '</label></th>
    <td><input id="social_facebook" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_facebook]" value="' . esc_attr($options['social_facebook']) . '" placeholder="' . __('Facebook business or personal page URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to Facebook page.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top">
    <th scope="row"><label for="social_twitter">' . __('Twitter Profile', 'under-construction-page') . '</label></th>
    <td><input id="social_twitter" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_twitter]" value="' . esc_attr($options['social_twitter']) . '" placeholder="' . __('Twitter profile URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to Twitter profile page.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top">
    <th scope="row"><label for="social_google">' . __('Google Page', 'under-construction-page') . '</label></th>
    <td><input id="social_google" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_google]" value="' . esc_attr($options['social_google']) . '" placeholder="' . __('Google+ page URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to Google+ page.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top">
    <th scope="row"><label for="social_linkedin">' . __('LinkedIn Profile', 'under-construction-page') . '</label></th>
    <td><input id="social_linkedin" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_linkedin]" value="' . esc_attr($options['social_linkedin']) . '" placeholder="' . __('LinkedIn profile page URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to LinkedIn profile page.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top">
    <th scope="row"><label for="social_youtube">' . __('YouTube Profile Page or Video', 'under-construction-page') . '</label></th>
    <td><input id="social_youtube" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_youtube]" value="' . esc_attr($options['social_youtube']) . '" placeholder="' . __('YouTube page or video URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to YouTube page or video.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_vimeo">' . __('Vimeo Profile Page or Video', 'under-construction-page') . '</label></th>
    <td><input id="social_vimeo" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_vimeo]" value="' . esc_attr($options['social_vimeo']) . '" placeholder="' . __('Vimeo page or video URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to Vimeo page or video.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_pinterest">' . __('Pinterest Profile', 'under-construction-page') . '</label></th>
    <td><input id="social_pinterest" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_pinterest]" value="' . esc_attr($options['social_pinterest']) . '" placeholder="' . __('Pinterest profile URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to Pinterest profile.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_dribbble">' . __('Dribbble Profile', 'under-construction-page') . '</label></th>
    <td><input id="social_dribbble" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_dribbble]" value="' . esc_attr($options['social_dribbble']) . '" placeholder="' . __('Dribbble profile URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to Dribbble profile.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_behance">' . __('Behance Profile', 'under-construction-page') . '</label></th>
    <td><input id="social_behance" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_behance]" value="' . esc_attr($options['social_behance']) . '" placeholder="' . __('Behance profile URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to Behance profile.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_instagram">' . __('Instagram Profile', 'under-construction-page') . '</label></th>
    <td><input id="social_instagram" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_instagram]" value="' . esc_attr($options['social_instagram']) . '" placeholder="' . __('Instagram profile URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to Instagram profile.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_vk">' . __('VK Profile', 'under-construction-page') . '</label></th>
    <td><input id="social_vk" type="url" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_vk]" value="' . esc_attr($options['social_vk']) . '" placeholder="' . __('VK profile URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix, to VK profile.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_telegram">' . __('Telegram Group, Channel or Account', 'under-construction-page') . '</label></th>
    <td><input id="social_telegram" type="text" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_telegram]" value="' . esc_attr($options['social_telegram']) . '" placeholder="' . __('Telegram group, channel or account URL', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Complete URL, with https prefix to Telegram group, channel or account.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_skype">' . __('Skype Username', 'under-construction-page') . '</label></th>
    <td><input id="social_skype" type="text" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_skype]" value="' . esc_attr($options['social_skype']) . '" placeholder="' . __('Skype username or account name', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Skype username or account name.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_whatsapp">' . __('WhatsApp Phone Number', 'under-construction-page') . '</label></th>
    <td><input id="social_whatsapp" type="text" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_whatsapp]" value="' . esc_attr($options['social_whatsapp']) . '" placeholder="' . __('(99) 99999-9999', 'under-construction-page') . '">';
    echo '<p class="description">' . __('WhatsApp phone number in national format without + or 55 prefix.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_email">' . __('Email Address', 'under-construction-page') . '</label></th>
    <td><input id="social_email" type="email" class="regular-text code" name="' . UCP_OPTIONS_KEY . '[social_email]" value="' . esc_attr($options['social_email']) . '" placeholder="' . __('name@domain.com', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Email will be encoded on the page to protect it from email address harvesters.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top" class="hidden">
    <th scope="row"><label for="social_phone">' . __('Phone Number', 'under-construction-page') . '</label></th>
    <td><input id="social_phone" type="tel" class="regular-text" name="' . UCP_OPTIONS_KEY . '[social_phone]" value="' . esc_attr($options['social_phone']) . '" placeholder="' . __('(99) 9999-9999', 'under-construction-page') . '">';
    echo '<p class="description">' . __('Phone number in full international format.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr><th colspan="2"><a id="show-social-icons" href="#" class="js-action">' . __('Show more Social &amp; Contact Icons', 'under-construction-page') . '</a></th></tr>';

    echo '</table>';
    echo '</div>';

    self::footer_buttons();
  } // tab_content


  static function get_themes() {
    $themes = array(
                    'mad_designer' => __('Mad Designer', 'under-construction-page'),
                    'plain_text' => __('Plain Text', 'under-construction-page'),
                    'under_construction' => __('Under Construction', 'under-construction-page'),
                    'dark' => __('Things Went Dark', 'under-construction-page'),
                    'forklift' => __('Forklift at Work', 'under-construction-page'),
                    'under_construction_text' => __('Under Construction Text', 'under-construction-page'),
                    'cyber_chick' => __('Cyber Chick', 'under-construction-page'),
                    'rocket' => __('Rocket Launch', 'under-construction-page'),
                    'loader' => __('Loader at Work', 'under-construction-page'),
                    'cyber_chick_dark' => __('Cyber Chick Dark', 'under-construction-page'),
                    'safe' => __('Safe', 'under-construction-page'),
                    'people' => __('People at Work', 'under-construction-page'),
                    'windmill' => __('Windmill', 'under-construction-page'),
                    'sad_site' => __('Sad Site', 'under-construction-page'),
                    'lighthouse' => __('Lighthouse', 'under-construction-page'),
                    'hot_air_baloon' => __('Hot Air Balloon', 'under-construction-page'),
                    'people_2' => __('People at Work #2', 'under-construction-page'),
                    'rocket_2' => __('Rocket Launch #2', 'under-construction-page'),
                    'light_bulb' => __('Light Bulb', 'under-construction-page'),
                    'ambulance' => __('Ambulance', 'under-construction-page'),
                    'laptop' => __('Laptop', 'under-construction-page'),
                    'puzzles' => __('Puzzles', 'under-construction-page'),
                    'iot' => __('Internet of Things', 'under-construction-page'),
                    'setup' => __('Setup', 'under-construction-page'),
                    'stop' => __('Stop', 'under-construction-page'),
                    'clock' => __('Clock', 'under-construction-page'),
                    'bulldozer' => __('Bulldozer at Work', 'under-construction-page'),
                    'christmas' => __('Christmas Greetings', 'under-construction-page'),
                    'hard_worker' => __('Hard Worker', 'under-construction-page'),
                    'closed' => __('Temporarily Closed', 'under-construction-page'),
                    'dumper_truck' => __('Dumper Truck', 'under-construction-page'),
                    '000webhost' => __('000webhost', 'under-construction-page'),
                    'work_desk' => __('Work Desk', 'under-construction-page'));

    $themes = apply_filters('ucp_themes', $themes);

    return $themes;
  } // get_themes


  static function tab_design() {
    $options = self::get_options();
    $default_options = self::default_options();

    $img_path = UCP_PLUGIN_URL . 'images/thumbnails/';
    $themes = self::get_themes();

    echo '<div class="ucp-notice-small"><p>Choose the best theme to your website.</p></div>';

    echo '<table class="form-table">';
    echo '<tr valign="top">
    <td colspan="2"><b style="margin-bottom: 10px; display: inline-block;">' . __('Choose Your Theme', 'under-construction-page') . '</b><br>';
    echo '<input type="hidden" id="theme_id" name="' . UCP_OPTIONS_KEY . '[theme]" value="' . $options['theme'] . '">';

    foreach ($themes as $theme_id => $theme_name) {
      if ($theme_id === $options['theme']) {
        $class = ' active';
      } else {
        $class = '';
      }
      echo '<div class="ucp-thumb' . $class . '" data-theme-id="' . $theme_id . '"><img src="' . $img_path . $theme_id . '.png" alt="' . $theme_name . '" title="' . $theme_name . '"><span>' . $theme_name . '</span>';
      echo '<div class="buttons"><a href="#" class="button button-primary activate-theme">Activate</a> <a href="' . get_home_url() . '/?ucp_preview&theme=' . $theme_id . '" class="button-secondary" target="_blank">Preview</a></div>';
      echo '</div>';
    } // foreach

    echo '</td></tr>';

    echo '<tr valign="top">
    <th scope="row"><label for="custom_css">' . __('Custom CSS', 'under-construction-page') . '</label></th>
    <td>';
    echo '<textarea data-autoresize="1" rows="3" id="custom_css" class="code large-text" name="' . UCP_OPTIONS_KEY . '[custom_css]" placeholder=".selector { property-name: property-value; }">' . esc_textarea($options['custom_css']) . '</textarea>';
    echo '<p class="description">&lt;style&gt; tags will be added automatically. Do not include them in your code.<br>
    For RTL languages support add: <code>body { direction: rtl; }</code></p>';
    echo '</td></tr>';

    // Custom Footer Code
    echo '<tr valign="top">
    <th scope="row"><label for="custom_footer_code">' . __('Custom Footer Script Code', 'under-construction-page') . '</label></th>
    <td>';

    echo '<textarea data-autoresize="1" rows="3" id="custom_footer_code" class="code large-text" name="' . UCP_OPTIONS_KEY . '[custom_footer_code]" placeholder="Javascript\'s code">' . esc_textarea($options['custom_footer_code']) . '</textarea>';

    echo '<p class="description">Paste any party code here such as tracking scripts. Be sure to include &lt;script&gt; tags as nothing is added automatically.<br>This is NOT a place to add Google Analytics code. Please use the <a href="#ga_tracking_id_toggle" class="change_tab" data-tab="0">GA Tracking setting</a> for that.</p>';
    echo '</td></tr>';

    echo '</table>';

    self::footer_buttons();
  } // tab_design


  // markup & logic for access tab
  static function tab_access() {
    $options = self::get_options();
    $default_options = self::default_options();
    $roles = $users = array();

    $tmp_roles = get_editable_roles();
    foreach ($tmp_roles as $tmp_role => $details) {
      $name = translate_user_role($details['name']);
      $roles[] = array('val' => $tmp_role,  'label' => $name);
    }

    $tmp_users = get_users(array('fields' => array('id', 'display_name')));
    foreach ($tmp_users as $user) {
      $users[] = array('val' => $user->id, 'label' => $user->display_name);
    }

    echo '<div class="ucp-tab-content">';
    echo '<table class="form-table">';

    echo '<tr valign="top" id="whitelisted-roles">
    <th scope="row">' . __('Whitelisted User Roles', 'under-construction-page') . '</th>
    <td>';
    foreach ($roles as $tmp_role) {
      echo  '<input name="' . UCP_OPTIONS_KEY . '[whitelisted_roles][]" id="roles-' . $tmp_role['val'] . '" ' . self::checked($tmp_role['val'], $options['whitelisted_roles'], false) . ' value="' . $tmp_role['val'] . '" type="checkbox" /> <label for="roles-' . $tmp_role['val'] . '">' . $tmp_role['label'] . '</label><br />';
    }
    echo '<p class="description">' . __('Selected user roles will <b>not</b> be affected by the under construction mode and will always see the "normal" site. Default: administrator.', 'under-construction-page') . '</p>';
    echo '</td></tr>';

    echo '<tr valign="top">
    <th scope="row"><label for="whitelisted_users">' . __('Whitelisted Users', 'under-construction-page') . '</label></th>
    <td><select id="whitelisted_users" class="select2" style="width: 50%; max-width: 300px;" name="' . UCP_OPTIONS_KEY . '[whitelisted_users][]" multiple>';
    self::create_select_options($users, $options['whitelisted_users'], true);

    echo '</select><p class="description">' . __('Selected users (when logged in) will <b>not</b> be affected by the under construction mode and will always see the "normal" site.', 'under-construction-page') . '</p>';
    echo '</td></tr>';


    echo '</table>';
    echo '</div>';

    self::footer_buttons();
  } // tab_access


  // output the whole options page
  static function main_page() {
    if (!current_user_can('manage_options'))  {
      wp_die('You do not have sufficient permissions to access this page.');
    }

    $options = self::get_options();
    $default_options = self::default_options();

    echo '<div class="wrap">
          <h1 class="ucp-logo">
          <a href="' . admin_url('options-general.php?page=wp-dev-helper') . '" title="'. __('Back to the plugin panel', 'under-construction-page') .'">
          <img src="' . UCP_PLUGIN_URL . 'images/ucp_logo.png" class="rotate" alt="Under Construction Page"> WP Dev Helper - Under Construction
          </a></h1>';

    echo '<form action="options.php" method="post" id="ucp_form">';
    settings_fields(UCP_OPTIONS_KEY);

    $tabs = array();
    $tabs[] = array('id' => 'ucp_main', 'icon' => 'dashicons-admin-settings', 'class' => '', 'label' => __('Main', 'under-construction-page'), 'callback' => array(__CLASS__, 'tab_main'));
    $tabs[] = array('id' => 'ucp_design', 'icon' => 'dashicons-admin-customizer', 'class' => '', 'label' => __('Design', 'under-construction-page'), 'callback' => array(__CLASS__, 'tab_design'));
    $tabs[] = array('id' => 'ucp_content', 'icon' => 'dashicons-format-aside', 'class' => '', 'label' => __('Content', 'under-construction-page'), 'callback' => array(__CLASS__, 'tab_content'));
    $tabs[] = array('id' => 'ucp_access', 'icon' => 'dashicons-shield', 'class' => '', 'label' => __('Access', 'under-construction-page'), 'callback' => array(__CLASS__, 'tab_access'));
    $tabs = apply_filters('ucp_tabs', $tabs);

    echo '<div id="ucp_tabs" class="ui-tabs" style="display: none;">';
    echo '<ul class="ucp-main-tab">';
    foreach ($tabs as $tab) {
      if(!empty($tab['label'])){
          echo '<li><a href="#' . $tab['id'] . '" class="' . $tab['class'] . '"><span class="icon"><span class="dashicons ' . $tab['icon'] . '"></span></span><span class="label">' . $tab['label'] . '</span></a></li>';
      }
    }
    echo '</ul>';

    foreach ($tabs as $tab) {
      if(is_callable($tab['callback'])) {
        echo '<div style="display: none;" id="' . $tab['id'] . '">';
        call_user_func($tab['callback']);
        echo '</div>';
      }
    } // foreach

    echo '</div>'; // ucp_tabs
    echo '</form>'; // ucp_tabs
    echo '</div>'; // wrap
  } // main_page


  // tests if any of the promotions are active and if so returns the name
  static function is_promo_active() {
    $meta = self::get_meta();

    if ((time() - $meta['first_install']) < HOUR_IN_SECONDS) {
      return 'welcome';
    }

    if ((time() - $meta['first_install']) > DAY_IN_SECONDS * 35) {
      return 'olduser';
    }

    return false;
  } // is_promo_active


  // save and preview buttons
  static function footer_buttons() {
    echo '<p class="submit">';
    echo get_submit_button(__('Save Changes', 'under-construction-page'), 'primary large', 'submit', false);
    echo ' &nbsp; &nbsp; <a id="ucp_preview" href="' . get_home_url() . '/?ucp_preview" class="button button-large button-secondary" target="_blank">' . __('Preview', 'under-construction-page') . '</a>';
    echo '</p>';
  } // footer_buttons


  // reset all pointers to default state - visible
  static function reset_pointers() {
    $pointers = array();
    $pointers['welcome'] = array('target' => '#menu-settings', 'edge' => 'left', 'align' => 'right', 'content' => 'Thank you for active the <b style="font-weight: 800; font-variant: small-caps;">Under Construction Page</b>! Please open <a href="' . admin_url('options-general.php?page=ucp'). '">Settings - UnderConstruction</a> to create a beautiful under construction page.');
    update_option(UCP_POINTERS_KEY, $pointers);
  } // reset_pointers


  static function is_plugin_installed($slug) {
    if (!function_exists('get_plugins')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();

    if (!empty($all_plugins[$slug])) {
      return true;
    } else {
      return false;
    }
  } // is_plugin_installed


  // add single plugin to list of favs
  static function add_plugin_favs($plugin_slug, $res) {
    if (!empty($res->plugins) && is_array($res->plugins)) {
      foreach ($res->plugins as $plugin) {
        if ($plugin->slug == $plugin_slug) {
          return $res;
        }
      } // foreach
    }

    if ($plugin_info = get_transient('wf-plugin-info-' . $plugin_slug)) {
      array_unshift($res->plugins, $plugin_info);
    } else {
      $plugin_info = plugins_api('plugin_information', array(
        'slug'   => $plugin_slug,
        'is_ssl' => is_ssl(),
        'fields' => array(
            'banners'           => true,
            'reviews'           => true,
            'downloaded'        => true,
            'active_installs'   => true,
            'icons'             => true,
            'short_description' => true,
        )
      ));
      if (!is_wp_error($plugin_info)) {
        $res->plugins[] = $plugin_info;
        set_transient('wf-plugin-info-' . $plugin_slug, $plugin_info, DAY_IN_SECONDS * 7);
      }
    }

    return $res;
  } // add_plugin_favs



  // reset pointers on activation
  static function activate() {
    self::reset_pointers();
  } // activate

  // clean up on deactivation
  static function deactivate() {
    delete_option(UCP_POINTERS_KEY);
    delete_option(UCP_NOTICES_KEY);
  } // deactivate

} // class UCP


// hook everything up
register_activation_hook(__FILE__, array('UCP', 'activate'));
register_deactivation_hook(__FILE__, array('UCP', 'deactivate'));
register_uninstall_hook(__FILE__, array('UCP', 'uninstall'));
add_action('init', array('UCP', 'init'));
