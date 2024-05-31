<?php

namespace Includes\settings;

use Includes\baseClasses\SVBase;
use Includes\settings\Options\SVBuddyPress;
use Includes\settings\Options\SVFireBaseNotification;
use Includes\settings\Options\SVGeneral;
use Includes\settings\Options\SVLearnPress;
use Includes\settings\Options\SVMemberProfile;
use Includes\settings\Options\SVMembership;
use Includes\settings\Options\SVNotificationsSettings;
use Includes\settings\Options\SVWooCommerce;
use Redux;

class SVSettings extends SVBase
{

    protected $opt_name                 = "sv_app_settings";
    protected $page_slug                = "sv_settings";
    protected static $sv_option_prefix  = "svo_";
    private $is_customizer;
    /**
     * Adds the action and filter hooks to integrate with WordPress.
     */
    public function init()
    {
        $this->is_customizer = is_customize_preview();
        $redux_opt_name = $this->opt_name;
        add_action('after_setup_theme', array($this, 'sv_action_add_redux'));

        /* redux styles */
        add_action('redux/page/' . $this->opt_name . '/enqueue', array($this, 'sv_redux_admin_styles'));
        add_action('wp_ajax_sv_save_redux_style_action', [$this, 'sv_save_redux_style']);
        add_action('wp_ajax_nopriv_sv_save_redux_style_action', [$this, 'sv_save_redux_style']);

        add_action("admin_enqueue_scripts", [$this, "sv_dequeue_unnecessary_scripts"], 11);

        /* redux fields overload */
        if (!$this->is_customizer) {
            add_filter("redux/$redux_opt_name/field/class/dimensions", function () {
                return dirname(__FILE__) . "/fields/dimensions/class-redux-dimensions.php";
            });
            add_filter("redux/$redux_opt_name/field/class/spacing", function () {
                return dirname(__FILE__) . "/fields/spacing/class-redux-spacing.php";
            });
            add_filter("redux/$redux_opt_name/field/class/media", function () {
                return dirname(__FILE__) . "/fields/media/class-redux-media.php";
            });
            add_filter("redux/$redux_opt_name/field/class/raw", function () {
                return dirname(__FILE__) . "/fields/raw/class-redux-raw.php";
            });
        }
    }

    function sv_dequeue_unnecessary_scripts($screen)
    {
        if ($screen !== "toplevel_page_" . $this->page_slug) return;

        wp_deregister_style("select2");
        wp_deregister_script("select2");
        wp_deregister_script("gamipress-select2-js");
    }

    function sv_redux_admin_styles()
    {
        global $is_dark_mode;
        $root = '';

        // remove admin notice for theme redux option page;
        remove_all_actions("admin_notices");
        wp_enqueue_script('underscore');
        $js_url     = SOCIALV_API_DIR_URI . 'includes/settings/assets/js/redux-template.min.js';
        $version    = SOCIALV_API_VERSION;
        $root_vars  = [
            "--redux-sidebar-color:#121623",
            "--redux-top-header:#f5f7ff",
            "--submenu-border-color:#262b3b",
            "--border-color-light:#ededed",
            "--content-backgrand-color:#fff",
            "--sub-fields-back:#fff;",
            "--input-border-color:#d8e1f5",
            "--input-btn-back:#edeffc",
            "--input-back-color:#f5f7ff",
            "--white-color-nochage:#fff",
            "--redux-text-color:#69748c",/* font color */
            "--text-heading-color:#121623",
            "--submenu-hover-color:#fff",
            "--redux-primary-color:#de3a53",
            "--font-weight-medium:500", /* font weight */
            "--notice-yellow-back:#fbf5e2",
            "--notice-yellow-color:#f7a210",
            "--code-editor-active:#e6edff",
            "--notice-green-back:#d1f1be",
            "--redux-sidebar-color:#f5f7ff",
            "--active-tab-color:#f5f0f0",
            "--no-changeborder-color-light:#ededed",
            "--submenu-hover-color:#de3a53",
            "--submenu-active-color:#de3a53",
            "--submenu-border-color:#e5e9e7",
            "--redux-menu-lable:#aeb1b9",
            "--redux-menu-color:#353840",
            "--wp-content-back:#f0f0f1",
        ];

        wp_enqueue_style('redux-template', SOCIALV_API_DIR_URI . '/includes/settings/assets/css/redux-template.min.css', true);
        wp_enqueue_style('redux-custom-font', SOCIALV_API_DIR_URI . '/includes/settings/assets/css/redux-font/redux-custom-font.css', true);

        $root .= ':root{' . implode(";", $root_vars) . '}';

        $root .= '.redux-brand.logo { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/logo.webp' . ' ) }';
        $root .= ' @media screen and (max-width: 600px) { .redux-brand.logo { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/mobile-logo-light.png' . ' ) } }';

        $root .= '.redux-image-select .one-column { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/one-column.png' . ' ) }';
        $root .= '.redux-image-select .two-column { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/two-column.png' . ' ) }';
        $root .= '.redux-image-select .three-column { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/three-column.png' . ' ) }';
        $root .= '.redux-image-select .right-sidebar { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/right-sidebar.png' . ' ) }';
        $root .= '.redux-image-select .left-sidebar { content: url( ' . SOCIALV_API_DIR_URI . '/ncludes/settings/assets/images/redux/left-sidebar.png' . ' ) }';

        $root .= '.redux-image-select .footer-layout-1 { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/footer-1.png' . ' ) }';
        $root .= '.redux-image-select .footer-layout-2 { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/footer-2.png' . ' ) }';
        $root .= '.redux-image-select .footer-layout-3 { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/footer-3.png' . ' ) }';
        $root .= '.redux-image-select .footer-layout-4 { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/footer-4.png' . ' ) }';

        $root .= '.redux-image-select .title-1 { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/title-1.png' . ' ) }';
        $root .= '.redux-image-select .title-2 { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/title-2.png' . ' ) }';
        $root .= '.redux-image-select .title-3 { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/title-3.png' . ' ) }';
        $root .= '.redux-image-select .title-4 { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/title-4.png' . ' ) }';
        $root .= '.redux-image-select .title-5 { content: url( ' . SOCIALV_API_DIR_URI . '/includes/settings/assets/images/redux/title-5.png' . ' ) }';

        $is_dark_mode = get_option($this->page_slug . "_is_redux_dark_mode", true);

        if (!$is_dark_mode) {
            wp_add_inline_style("redux-template", $root);
        }

        wp_register_script('custom_redux_options', false);
        wp_localize_script('custom_redux_options', 'custom_redux_options_params', array(
            'ajaxUrl'       => admin_url() . 'admin-ajax.php',
            'root'          => $root,
            'action'        => "sv_save_redux_style_action",
            'is_dark_mode'  => $is_dark_mode ? true : false
        ));
        wp_enqueue_script('custom_redux_options');

        wp_enqueue_script('redux-template', $js_url, ['jquery'], $version, true);
    }

    public function sv_save_redux_style()
    {
        $is_dark_mode = isset($_GET['is_dark_mode']) && $_GET['is_dark_mode'] == 1 ? 1 : 0;
        update_option($this->page_slug . "_is_redux_dark_mode", $is_dark_mode);
    }

    public function sv_action_add_redux()
    {
        // RDX Framework Barebones Sample Config File
        if (!class_exists('Redux')) {
            return;
        }

        $name = "SocialV App";
        $version = SOCIALV_API_VERSION;

        $args = array(
            // TYPICAL -> Change these values as you need/desire
            'opt_name'             => $this->opt_name,
            // This is where your data is stored in the database and also becomes your global variable name.
            'display_name'         => $name,
            // Name that appears at the top of your panel
            'display_version'      => $version,
            // Version that appears at the top of your panel
            'menu_type'            => 'menu',
            //Specify if the admin menu should appear or not. Options: menu or submenu (Under appearance only)
            'allow_sub_menu'       => true,
            // Show the sections below the admin menu item or not
            'menu_title'           => esc_html__('App Configuration', 'socialv-api'),
            'page_title'           => esc_html__('App Configuration', 'socialv-api'),
            // You will need to generate a Google API key to use this feature.
            // Please visit: https://developers.google.com/fonts/docs/developer_api#Auth
            'google_api_key'       => '',
            // Set it you want google fonts to update weekly. A google_api_key value is required.
            'google_update_weekly' => false,
            // Must be defined to add google fonts to the typography module
            'async_typography'     => true,
            // Use a asynchronous font on the front end or font string
            //'disable_google_fonts_link' => true,                    // Disable this in case you want to create your own google fonts loader
            'admin_bar'            => false,
            // Show the panel pages on the admin bar
            'admin_bar_icon'       => 'dashicons-admin-settings',
            // Choose an icon for the admin bar menu
            'admin_bar_priority'   => '',
            // Choose a priority for the admin bar menu
            'global_variable'      => $this->opt_name,
            // Set a different name for your global variable other than the opt_name
            'dev_mode'             => false,
            // Show the time the page took to load, etc
            'update_notice'        => false,
            // If dev_mode is enabled, will notify developer of updated versions available in the GitHub Repo
            'customizer'           => true,
            // Enable basic customizer support
            //'open_expanded'     => true,                    // Allow you to start the panel in an expanded way initially.
            //'disable_save_warn' => true,                    // Disable the save warning when a user changes a field
            'class'                     => 'redux-content',
            // OPTIONAL -> Give you extra features
            'page_priority'        => 3,
            // Order where the menu appears in the admin area. If there is any conflict, something will not show. Warning.
            'page_parent'          => 'plugins.php',
            // For a full list of options, visit: http://codex.wordpress.org/Function_Reference/add_submenu_page#Parameters
            'page_permissions'     => 'manage_options',
            // Permissions needed to access the options panel.
            'menu_icon'            => $this->plugin_url . 'assets/images/options.png',
            // Specify a custom URL to an icon
            'last_tab'             => '',
            // Force your panel to always open to a specific tab (by id)
            'page_icon'            => 'icon-themes',
            // Icon displayed in the admin panel next to your menu_title
            'page_slug'            => $this->page_slug,
            // Page slug used to denote the panel
            'save_defaults'        => true,
            // On load save the defaults to DB before user clicks save or not
            'default_show'         => false,
            // If true, shows the default value next to each field that is not the default value.
            'default_mark'         => '',
            // What to print by the field's title if the value shown is default. Suggested: *
            'show_import_export'   => false,
            // Shows the Import/Export panel when not used as a field.
            'show_options_object'   => true,
            'templates_path'        => !$this->is_customizer ? SOCIALV_API_DIR . 'includes/settings/templates/panel/' : '',
            'use_cdn'                   => true,
            // CAREFUL -> These options are for advanced use only
            'transient_time'        => 60 * MINUTE_IN_SECONDS,
            'output'                => true,
            // Global shut-off for dynamic CSS output by the framework. Will also disable google fonts output
            'output_tag'            => true,
            // Allows dynamic CSS to be generated for customizer and google fonts, but stops the dynamic CSS from going to the head
            // FUTURE -> Not in use yet, but reserved or partially implemented. Use at your own risk.
            'database'              => '',
            // possible: options, theme_mods, theme_mods_expanded, transient. Not fully functional, warning!
            'system_info'           => false,
            // REMOVE
            'hide_expand'           => true,
            // HINTS
            'hints'                => array(
                'icon'          => 'el el-question-sign',
                'icon_position' => 'right',
                'icon_color'    => 'lightgray',
                'icon_size'     => 'normal',
                'tip_style'     => array(
                    'color'   => 'light',
                    'shadow'  => true,
                    'rounded' => false,
                    'style'   => '',
                ),
                'tip_position'  => array(
                    'my' => 'top left',
                    'at' => 'bottom right',
                ),
                'tip_effect'    => array(
                    'show' => array(
                        'effect'   => 'slide',
                        'duration' => '500',
                        'event'    => 'mouseover',
                    ),
                    'hide' => array(
                        'effect'   => 'slide',
                        'duration' => '500',
                        'event'    => 'click mouseleave',
                    ),
                ),
            )
        );

        Redux::set_args($this->opt_name, $args);

        $this->sv_action_add_redux_widgets();
    }

    public function sv_action_add_redux_widgets()
    {
        new SVGeneral();
        new SVBuddyPress();

        new SVMemberProfile();
        new SVNotificationsSettings();

        if (class_exists('WooCommerce'))
            new SVWooCommerce();

        if (class_exists('LearnPress'))
            new SVLearnPress();

        if (class_exists("PMPro_Membership_Level"))
            new SVMembership();

        new SVFireBaseNotification();
    }

    public static function is_friends_only_activity()
    {
        global $socialv_options, $sv_app_settings;

        $return = false;

        if (isset($socialv_options["display_activity_showing_friends"])) {
            $return = ($socialv_options["display_activity_showing_friends"] == "no");
        } else {
            $app_option = self::$sv_option_prefix . 'friends_only_activity';
            if (isset($sv_app_settings[$app_option])) {
                $return = ($sv_app_settings[$app_option] == 1);
            }
        }

        return $return;
    }

    public static function is_blog_post_enable()
    {
        global $socialv_options, $sv_app_settings;

        $return = false;

        if (isset($socialv_options["display_blog_post_type"])) {
            $return = ($socialv_options["display_blog_post_type"] == "1");
        } else {
            $app_option = self::$sv_option_prefix . 'display_blog_post';
            if (isset($sv_app_settings[$app_option])) {
                $return = ($sv_app_settings[$app_option] == "1");
            }
        }

        return $return;
    }
    public static function sv_get_onesignal_keys()
    {
        global $sv_app_settings;
        $prefix = self::$sv_option_prefix;
        $fields = get_option("socialv_onesignal_keys");
        if ($fields) return $fields;

        if (isset($sv_app_settings[$prefix . "onesignal_app_id"]) && isset($sv_app_settings[$prefix . "onesignal_api_key"])) {
            $fields = [
                "app_id"    => !empty($sv_app_settings[$prefix . "onesignal_app_id"]) ? $sv_app_settings[$prefix . "onesignal_app_id"] : $fields,
                "api_key"   => !empty($sv_app_settings[$prefix . "onesignal_api_key"]) ? $sv_app_settings[$prefix . "onesignal_api_key"] : $fields
            ];
        }

        return $fields;
    }
    public static function sv_get_option($option_name = '')
    {
        global $sv_app_settings;
        $prefix = self::$sv_option_prefix;

        if (isset($sv_app_settings[$prefix . $option_name]))
            return $sv_app_settings[$prefix . $option_name];

        return '';
    }

    public static function theme_option_ids($key)
    {
        $ids = [
            "posts_count"               => "display_user_post",
            "comments_count"            => "display_user_comments",
            "profile_views"             => "display_user_views",
            "friend_request_btn"        => "display_user_request_btn",
            "defalt_avatar_img"         => "socialv_default_avatar"
        ];

        return $ids[$key];
    }

    public static function sv_get_theme_dependent_options($option_name = '')
    {
        global $socialv_options, $sv_app_settings;
        $prefix = self::$sv_option_prefix;

        $theme_option_id = self::theme_option_ids($option_name);

        if (isset($socialv_options[$theme_option_id]))
            return $socialv_options[$theme_option_id];

        if (isset($sv_app_settings[$prefix . $option_name]))
            return $sv_app_settings[$prefix . $option_name];

        return "";
    }
}
