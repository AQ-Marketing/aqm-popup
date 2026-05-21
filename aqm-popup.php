<?php
/*
Plugin Name: AQM Popup
Plugin URI: https://aqmarketing.com/
Description: Site-wide popup for Divi 4 sites. Renders a Divi Library layout as the popup content, with configurable triggers (time delay, scroll depth, exit intent, click on element), per-session show cap, and post-dismissal cooldown.
Version: 1.0.12
Author: AQ Marketing
Author URI: https://aqmarketing.com/
GitHub Plugin URI: https://github.com/AQ-Marketing/aqm-popup
Primary Branch: main
Requires at least: 5.2
Requires PHP: 7.2
License: GPL-2.0-or-later
Text Domain: aqm-popup
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AQM_POPUP_VERSION', '1.0.12' );
define( 'AQM_POPUP_FILE', __FILE__ );
define( 'AQM_POPUP_PATH', plugin_dir_path( __FILE__ ) );
define( 'AQM_POPUP_URL', plugin_dir_url( __FILE__ ) );
define( 'AQM_POPUP_BASENAME', plugin_basename( __FILE__ ) );
define( 'AQM_POPUP_GH_USER', 'AQ-Marketing' );
define( 'AQM_POPUP_GH_REPO', 'aqm-popup' );

if ( ! defined( 'AQM_POPUP_DEBUG' ) ) {
    define( 'AQM_POPUP_DEBUG', false );
}

function aqm_popup_debug_log( $message ) {
    if ( defined( 'AQM_POPUP_DEBUG' ) && AQM_POPUP_DEBUG ) {
        error_log( '[AQM POPUP] ' . $message );
    }
}

function aqm_popup_default_settings() {
    return array(
        'enabled'                 => false,
        'layout_id'               => 0,
        'trigger_delay_enabled'   => false,
        'trigger_delay_seconds'   => 10,
        'trigger_scroll_enabled'  => false,
        'trigger_scroll_percent'  => 50,
        'trigger_exit_enabled'    => false,
        'trigger_click_enabled'   => false,
        'trigger_click_selector'  => '',
        'max_per_session'         => 1,
        'cooldown_days'           => 7,
        'close_on_overlay_click'  => true,
        'close_on_esc'            => true,
        'overlay_opacity'         => 0.7,
        'edge_to_edge_mode'       => false,
        'test_mode_enabled'       => false,
        'test_mode_page_id'       => 0,
    );
}

function aqm_popup_get_settings() {
    $stored = get_option( 'aqm_popup_settings', array() );
    if ( ! is_array( $stored ) ) {
        $stored = array();
    }
    return array_merge( aqm_popup_default_settings(), $stored );
}

register_activation_hook( __FILE__, 'aqm_popup_activate' );
function aqm_popup_activate() {
    if ( false === get_option( 'aqm_popup_settings', false ) ) {
        add_option( 'aqm_popup_settings', aqm_popup_default_settings() );
    }
}

require_once AQM_POPUP_PATH . 'includes/class-aqm-popup-admin.php';
require_once AQM_POPUP_PATH . 'includes/class-aqm-popup-display.php';
require_once AQM_POPUP_PATH . 'includes/class-aqm-popup-updater.php';

final class AQM_Popup {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if ( is_admin() ) {
            AQM_Popup_Admin::get_instance();
        }
        AQM_Popup_Display::get_instance();
    }
}

add_action( 'plugins_loaded', array( 'AQM_Popup', 'get_instance' ) );

function aqm_popup_init_github_updater() {
    if ( ! class_exists( 'AQM_Popup_Updater' ) ) {
        error_log( '[AQM POPUP] Updater class not found' );
        return;
    }
    try {
        new AQM_Popup_Updater(
            __FILE__,
            AQM_POPUP_GH_USER,
            AQM_POPUP_GH_REPO,
            ''
        );
    } catch ( Exception $e ) {
        error_log( '[AQM POPUP] Error initializing updater: ' . $e->getMessage() );
    }
}

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
    aqm_popup_init_github_updater();
}
