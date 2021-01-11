<?php

class Developers
{

  /*----------  CREATE PAGE DEVELOPERS  ----------*/
  public function pageDevelopers(){
    if( function_exists('acf_add_options_page') ) {
      acf_add_options_page(array(
        'page_title' => '
          <h1 class="wp-helper-logo">
            <img src="' . PLUGINROOT . '/assets/img/logo.png" class="rotate" alt="Under Construction Page">
            WP Dev Helper
          </h1>
        ',
        'menu_title' => __('Developers', 'wpdevhelper'),
        'menu_slug'  => 'wp-dev-helper',
        'capability' => 'edit_posts',
        'redirect'   => false
      ));
    }
  }

  /*----------  DASHBOARD -> REMOVE WIDGETS  ----------*/
  public function developersDashboardRemoveWidgets(){
    if( is_array(get_field('wpdevhelperDashboard-remove_widgets', 'option')) ){
      add_action('admin_init', function(){
        foreach( get_field('wpdevhelperDashboard-remove_widgets', 'option') as $widget ){
          remove_meta_box($widget, 'dashboard', 'normal');
        }
      });
    }
  }

  /*----------  DASHBOARD -> ADD WIDGETS  ----------*/
  public function developersDashboardAddBox(){
    add_action('wp_dashboard_setup', function($widget){
      $dashboardWidgets = get_posts('post_type=dashboard_widget&posts_per_page=-1&orderby=meta_value&meta_key=order&order=ASC');
      if( count($dashboardWidgets) >= 1 ){
        foreach($dashboardWidgets as $widget){
          $post_content = $widget->post_content;
          wp_add_dashboard_widget('dashwidget_'.$widget->ID, $widget->post_title, function() use ($post_content){
            echo apply_filters('the_content', $post_content);
          });
        }
      }
    });
  }

  /*----------  TAXONOMIES -> HIERARCHICAL ORDER  ----------*/
  public function developersTaxonomiesHierarchical(){
    if( count( explode(',', get_field('wpdevhelperTax-hierarchical_order', 'option')) ) >= 1 ){
      add_action('wp_terms_checklist_args', function($args){
        $getTaxonomies = explode(',', str_replace(' ', '', get_field('wpdevhelperTax-hierarchical_order', 'option')));
        if( in_array($args['taxonomy'], $getTaxonomies) ){
          $args['checked_ontop'] = false;
        } return $args;
      }, 12);
    }
  }

  /*----------  LOGIN SCREEN -> ENABLE  ----------*/
  public function developersLoginScreenEnable(){
    if( get_field('wpdevhelperLoginScreen-enable_custom_login_screen', 'option') == 'yes' ){
      // Logo Image
      if( trim(get_field('wpdevhelperLoginScreen-logo_image', 'option')) != '' ){
        $loginImage = get_field('wpdevhelperLoginScreen-logo_image', 'option');
        $loginImage = wp_get_attachment_image_src($loginImage, 'full');
        add_action('login_enqueue_scripts', function() use ($loginImage){
          echo '
          <style type="text/css">
            #login h1 a, .login h1 a {
              background-image: url(\''.$loginImage[0].'\');
              background-repeat: no-repeat;
              background-size: 100px;
              width: 100px;
              height: 100px;
              padding-bottom: 30px;
            }
          </style>
          ';
        });
      }
      // Logo URL
      if( trim(get_field('wpdevhelperLoginScreen-logo_url', 'option')) != '' ){
        $loginURL = get_field('wpdevhelperLoginScreen-logo_url', 'option');
        add_filter('login_headerurl', function() use ($loginURL){
          return $loginURL;
        });
      }
      // Logo Title
      if( trim(get_field('wpdevhelperLoginScreen-logo_title', 'option')) != '' ){
        $loginTitle = get_field('wpdevhelperLoginScreen-logo_title', 'option');
        add_filter( 'login_headertitle', function() use ($loginTitle){
          return $loginTitle;
        });
      }
      // Custom Footer
      if( trim(get_field('wpdevhelperLoginScreen-add_footer_content', 'option')) != '' ){
        $loginFooter = get_field('wpdevhelperLoginScreen-add_footer_content', 'option');
        add_action('login_footer', function() use ($loginFooter){
          echo '<div style="border-top: 1px solid #ddd; width:320px; padding:0; margin: 26px auto;">';
            echo '<div style="padding: 15px 26px 0;">'.$loginFooter.'</div>';
          echo '</div>';
        });
      }
      // Login Text Color Style
      if( trim(get_field('wpdevhelperLoginScreen-login_text_color_style', 'option')) != '' && get_field('wpdevhelperLoginScreen-login_text_color_style', 'option') == 'dark' ){
        add_action('login_enqueue_scripts', function(){
          echo '<style>
          body.login { color: #fff !important; }
          .login #backtoblog a, .login #nav a { text-decoration: none; color: #fff !important; }
          </style>';
        });
      }
      // Body Background Color
      if( trim(get_field('wpdevhelperLoginScreen-body_background_color', 'option')) != '' ){
        $bodyBackgroundColor = get_field('wpdevhelperLoginScreen-body_background_color', 'option');
        add_action('login_enqueue_scripts', function() use ($bodyBackgroundColor){
          echo "<style>body.login {background-color: $bodyBackgroundColor}</style>";
        });
      }
    }
  }

  /*----------  WP HEAD -> Meta Description  ----------*/
  public function developersWPHeadMetaDescription(){
    if( trim(get_field('wpdevhelperWPHead-meta_description', 'option')) != '' ) {
      add_action('wp_head', function(){
        # Meta Description
        echo '<meta name="description" content="'.get_field('wpdevhelperWPHead-meta_description', 'option').'">'."\n";
      }, 0);
    }
  }

  /*----------  WP HEAD -> Theme color  ----------*/
  public function developersWPHeadMetaThemeColor() {
    if( trim(get_field('wpdevhelperWPHead-theme_color', 'option')) != '' ){
      add_action('wp_head', function() {
        # Theme Color
        echo '<meta name="theme-color" content="'.get_field('wpdevhelperWPHead-theme_color', 'option').'">'."\n";
        echo '<meta name="msapplication-TileColor" content="'.get_field('wpdevhelperWPHead-theme_color', 'option').'">';
        echo '<meta name="msapplication-navbutton-color" content="'.get_field('wpdevhelperWPHead-theme_color', 'option').'">'."\n\n";
      }, 0);
    }
  }

  public function developersWPHeadPWA() {
    if( get_field('wpdevhelperWPHead-pwa', 'option') == 'yes' ) {
      add_action('wp_head', function(){
        echo '<link rel="manifest" href="">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="application-name" content="'.get_bloginfo('name').'">
        <meta name="apple-mobile-web-app-title" content="'.get_bloginfo('name').'">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="msapplication-starturl" content="'.get_bloginfo('url').'">';
      }, 1);
    }
  }

  /*----------  WP HEAD -> FAVICON / APPLE TOUCH ICON  ----------*/
  public function developersWPHeadFavicon(){
    add_action('wp_head', function(){
      # Favicon
      if( trim(get_field('head-icon-favicon', 'option')) != '' ){
        $favicon = wp_get_attachment_image_src(get_field('head-icon-favicon', 'option'), 'thumbnail', false);
        echo "\n\n".'<link rel="shortcut icon" type="image/png" href="'.$favicon[0].'">'."\n";
      }
      # Apple Touch Icon iPhone
      if( trim(get_field('head-icon-apple-touch-icon-57x57', 'option')) != '' ){
        echo '<link rel="apple-touch-icon" sizes="57x57" href="'.get_field('head-icon-apple-touch-icon-57x57', 'option').'">'."\n";
      }
      
      if( trim(get_field('head-icon-apple-touch-icon-60x60', 'option')) != '' ){
        echo '<link rel="apple-touch-icon" sizes="60x60" href="'.get_field('head-icon-apple-touch-icon-60x60', 'option').'">'."\n";
      }

      if( trim(get_field('head-icon-apple-touch-icon-72x72', 'option')) != '' ){
        echo '<link rel="apple-touch-icon" sizes="72x72" href="'.get_field('head-icon-apple-touch-icon-72x72', 'option').'">'."\n";
      }

      if( trim(get_field('head-icon-apple-touch-icon-76x76', 'option')) != '' ){
        echo '<link rel="apple-touch-icon" sizes="76x76" href="'.get_field('head-icon-apple-touch-icon-76x76', 'option').'">'."\n";
      }

      if( trim(get_field('head-icon-apple-touch-icon-114x114', 'option')) != '' ){
        echo '<link rel="apple-touch-icon" sizes="114x114" href="'.get_field('head-icon-apple-touch-icon-114x114', 'option').'">'."\n";
      }

      if( trim(get_field('head-icon-apple-touch-icon-120x120', 'option')) != '' ){
        echo '<link rel="apple-touch-icon" sizes="120x120" href="'.get_field('head-icon-apple-touch-icon-120x120', 'option').'">'."\n";
      }

      if( trim(get_field('head-icon-apple-touch-icon-144x144', 'option')) != '' ){
        echo '<meta name="msapplication-TileImage" content="'.get_field('head-icon-apple-touch-icon-144x144', 'option').'">
        <link rel="apple-touch-icon" sizes="144x144" href="'.get_field('head-icon-apple-touch-icon-144x144', 'option').'">'."\n";
      }

      if( trim(get_field('head-icon-apple-touch-icon-152x152', 'option')) != '' ){
        echo '<link rel="apple-touch-icon" sizes="152x152" href="'.get_field('head-icon-apple-touch-icon-152x152', 'option').'">'."\n";
      }
      
      if( trim(get_field('head-icon-apple-touch-icon-180x180', 'option')) != '' ){
        echo '<link rel="apple-touch-icon" sizes="180x180" href="'.get_field('head-icon-apple-touch-icon-180x180', 'option').'">'."\n";
      }
      
      if( trim(get_field('head-icon-192x192', 'option')) != '' ){
        echo '<link rel="icon" type="image/png" sizes="192x192" href="'.get_field('head-icon-192x192', 'option').'">'."\n";
      }

      if( trim(get_field('head-icon-96x96', 'option')) != '' ){
        echo '<link rel="icon" type="image/png" sizes="96x96" href="'.get_field('head-icon-96x96', 'option').'">'."\n";
      }
      
      if( trim(get_field('head-icon-16x16', 'option')) != '' ){
        echo '<link rel="icon" type="image/png" sizes="16x16" href="'.get_field('head-icon-16x16', 'option').'">'."\n";
      }
    }, 2);
  }


  /*----------  TEMPLATE SETTINGS -> UNDER CONSTRUCTION  ----------*/
  public function developersTemplateSettingsUnderConstruction(){
    if( get_field('wpdevhelperTemplateSettings-under_construction', 'option') == 'yes' ) {
      require_once(PLUGINPATH.'under-construction-page/under-construction.php');
    }
  }

  /*----------  OTHERS -> DUPLICATE  ----------*/
  public function developersOthersDuplicate(){
    if( get_field('wpdevhelperOthers-duplicate_post', 'option') == 'yes' ){ add_filter( 'post_row_actions', 'rd_duplicate_post_link', 10, 2 ); }
    if( get_field('wpdevhelperOthers-duplicate_page', 'option') == 'yes' ){ add_filter( 'page_row_actions', 'rd_duplicate_post_link', 10, 2 ); }
  }

  /*----------  ADVANCED -> WP HEAD  ----------*/
  public function developersAdvancedWPHead(){
    if( is_array(get_field('wpdevhelperAdvanced-wp_head', 'option')) ){
      # EditURI
      if( in_array('EditURI', get_field('wpdevhelperAdvanced-wp_head', 'option')) ){
        remove_action('wp_head', 'rsd_link');
      }
      # wlwmanifest
      if( in_array('wlwmanifest', get_field('wpdevhelperAdvanced-wp_head', 'option')) ){
        remove_action('wp_head', 'wlwmanifest_link');
      }
      # generator
      if( in_array('wlwmanifest', get_field('wpdevhelperAdvanced-wp_head', 'option')) ){
        remove_action('wp_head', 'wp_generator');
      }
      # canonical
      if( in_array('canonical', get_field('wpdevhelperAdvanced-wp_head', 'option')) ){
        remove_action('wp_head', 'rel_canonical');
      }
      # shortlink
      if( in_array('shortlink', get_field('wpdevhelperAdvanced-wp_head', 'option')) ){
        remove_action('wp_head', 'wp_shortlink_wp_head');
      }
      # Emoji Scripts
      if( in_array('emoji_scripts', get_field('wpdevhelperAdvanced-wp_head', 'option')) ){
        remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
        remove_action( 'wp_print_styles', 'print_emoji_styles' );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'admin_print_styles', 'print_emoji_styles' );
      }
    }
  }

  /*----------  ADVANCED -> CUSTOM CSS  ----------*/
  public function developersAdvancedCustomCSS($wpdhCssCode){
    if( trim($wpdhCssCode) != '' ){
      add_action('wp_head', function(){
        global $wpdhCssCode;
        echo '<style>'.$wpdhCssCode.'</style>';
      });
    }
  }

  /*----------  ADVANCED -> CUSTOM JS  ----------*/
  public function developersAdvancedCustomJSHead($wpdhJsHeadCode){
    if( trim($wpdhJsHeadCode) != '' ){
      add_action('wp_head', function(){
        global $wpdhJsHeadCode;
        echo '<script>'.$wpdhJsHeadCode.'</script>';
      });
    }
  }

  /*----------  ADVANCED -> CUSTOM JS  ----------*/
  public function developersAdvancedCustomJSFooter($wpdhJsFooterCode){
    if( trim($wpdhJsFooterCode) != '' ){
      add_action('wp_footer', function(){
        global $wpdhJsFooterCode;
        echo '<script>'.$wpdhJsFooterCode.'</script>';
      }, 98);
    }
  }

  /*----------  ADVANCED -> GOOGLE ANALYTICS  ----------*/
  public function developersAdvancedGoogleAnalytics(){
    if( get_field('wpdevhelperAdvanced-google_analyltics', 'option') != '' ){
      add_action('wp_footer', function(){
        echo "
          <!-- Google Analytics -->
          <script>
          (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
          (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
          m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
          })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

          ga('create', '".get_field('wpdevhelperAdvanced-google_analyltics', 'option')."', 'auto');
          ga('send', 'pageview');
          </script>
          <!-- End Google Analytics -->
          ";
      }, 99);
    }
  }
}
