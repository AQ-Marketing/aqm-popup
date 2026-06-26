<?php
/*
Plugin Name: AQM Popup
Plugin URI: https://aqmarketing.com/
Description: Site-wide popup builder. Compose the popup (image, headline, text, button) right in the settings, with configurable triggers (time delay, scroll depth, exit intent, click on element), per-session show cap, and post-dismissal cooldown.
Version: 1.3.0
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

define( 'AQM_POPUP_VERSION', '1.3.0' );
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

/**
 * Defaults for a single design (a full preset: content, style, triggers,
 * frequency, behavior, close icon, plus name + schedule).
 */
function aqm_popup_design_defaults() {
    return array(
        'name'        => __( 'Untitled design', 'aqm-popup' ),
        'archived'    => false,
        'start_date'  => '',
        'end_date'    => '',

        // Content (built in-settings — no page builder required).
        'content_image_id'        => 0,
        'content_heading'         => '',
        'content_body'            => '',
        'content_button_label'    => '',
        'content_button_url'      => '',
        'content_button_new_tab'  => false,

        // Popup body styling.
        'style_bg_color'           => '#ffffff',
        'style_bg_image_id'        => 0,
        'style_bg_overlay_color'   => '#000000',
        'style_bg_overlay_opacity' => 0,
        'style_text_color'         => '#1d2327',
        'style_button_bg'          => '#c10f30',
        'style_button_text_color'  => '#ffffff',
        'style_max_width'          => 480,
        'style_min_height'         => 0,
        'style_padding'            => 32,
        'style_align'              => 'center',
        'style_vertical_align'     => 'top',
        'style_font_family'        => '',
        'style_heading_size'       => 28,
        'style_heading_weight'     => 700,
        // Headline typography (each defaults to "inherit current behavior" so
        // existing designs are unchanged).
        'style_heading_font_family'    => '',      // '' = same as popup font
        'style_heading_color'          => '#1d2327',
        'style_heading_line_height'    => 1.2,
        'style_heading_letter_spacing' => 0,
        'style_heading_transform'      => 'none',
        'style_heading_italic'         => false,
        'style_heading_align'          => 'inherit',
        'style_heading_margin_bottom'  => 10,
        'style_body_size'          => 16,
        'style_body_weight'        => 400,

        // Triggers.
        'trigger_delay_enabled'   => false,
        'trigger_delay_seconds'   => 10,
        'trigger_scroll_enabled'  => false,
        'trigger_scroll_percent'  => 50,
        'trigger_exit_enabled'    => false,
        'trigger_click_enabled'   => false,
        'trigger_click_selector'  => '',

        // Frequency.
        'max_per_session'         => 1,
        'cooldown_days'           => 7,

        // Behavior.
        'close_on_overlay_click'  => true,
        'close_on_esc'            => true,
        'overlay_opacity'         => 0.7,
        'style_backdrop_color'    => '#000000',
        'overlay_padding_vertical'   => 0,
        'overlay_padding_horizontal' => 0,
        'style_border_width'      => 0,          // 0 = no border
        'style_border_style'      => 'solid',
        'style_border_color'      => '#ffffff',
        'popup_border'            => '',         // legacy/advanced CSS override
        'popup_border_radius_px'  => 0,

        // Close icon.
        'close_size_px'           => 36,
        'close_offset_px'         => 10,
        'close_background'        => 'transparent',
        'close_icon_color'        => '#ffffff',
        'close_border_radius_px'  => 0,
    );
}

/**
 * Top-level defaults: a library of designs, a manually-active design, plus
 * global flags (master enable, test mode).
 */
function aqm_popup_default_settings() {
    $id            = 'd1';
    $design        = aqm_popup_design_defaults();
    $design['name'] = __( 'Design 1', 'aqm-popup' );
    return array(
        'schema'            => 3,
        'enabled'           => false,
        'active'            => $id,
        'order'             => array( $id ),
        'designs'           => array( $id => $design ),
        'test_mode_enabled' => false,
        'test_mode_page_id' => 0,
    );
}

/**
 * Migrate the old flat (v1) settings into the v2 designs structure: the whole
 * flat config becomes "Design 1" and is made active. Global flags lift out.
 */
function aqm_popup_migrate_v1( $old ) {
    $design          = aqm_popup_design_defaults();
    $design['name']  = __( 'Design 1', 'aqm-popup' );
    $skip            = array( 'name', 'archived', 'start_date', 'end_date' );
    foreach ( array_keys( aqm_popup_design_defaults() ) as $k ) {
        if ( in_array( $k, $skip, true ) ) {
            continue;
        }
        if ( isset( $old[ $k ] ) ) {
            $design[ $k ] = $old[ $k ];
        }
    }
    return array(
        'schema'            => 2,
        'enabled'           => ! empty( $old['enabled'] ),
        'active'            => 'd1',
        'order'             => array( 'd1' ),
        'designs'           => array( 'd1' => $design ),
        'test_mode_enabled' => ! empty( $old['test_mode_enabled'] ),
        'test_mode_page_id' => isset( $old['test_mode_page_id'] ) ? (int) $old['test_mode_page_id'] : 0,
    );
}

/**
 * Normalize stored settings to the v2 structure (idempotent). Migrates v1 data
 * in memory, fills defaults, and guarantees a valid active id + order.
 */
function aqm_popup_normalize_settings( $stored ) {
    if ( empty( $stored ) || ! is_array( $stored ) ) {
        return aqm_popup_default_settings();
    }
    if ( empty( $stored['schema'] ) || (int) $stored['schema'] < 2 || ! isset( $stored['designs'] ) ) {
        $stored = aqm_popup_migrate_v1( $stored );
    }
    $schema_in = isset( $stored['schema'] ) ? (int) $stored['schema'] : 0;

    $top_defaults = array(
        'schema'            => 2,
        'enabled'           => false,
        'active'            => '',
        'order'             => array(),
        'designs'           => array(),
        'test_mode_enabled' => false,
        'test_mode_page_id' => 0,
    );
    $s = array_merge( $top_defaults, $stored );

    if ( ! is_array( $s['designs'] ) || empty( $s['designs'] ) ) {
        $def              = aqm_popup_default_settings();
        $s['designs']     = $def['designs'];
        $s['order']       = $def['order'];
        $s['active']      = $def['active'];
    }

    $design_defaults = aqm_popup_design_defaults();
    $clean           = array();
    foreach ( $s['designs'] as $id => $design ) {
        if ( ! is_array( $design ) ) {
            continue;
        }
        $clean[ (string) $id ] = array_merge( $design_defaults, $design );
    }

    // Schema 2 → 3: the headline color used to apply only when a (now-removed)
    // "custom" toggle was on; otherwise the headline inherited the text color.
    // Bake that into the headline color so removing the toggle doesn't change
    // how any existing design looks.
    if ( $schema_in < 3 ) {
        foreach ( $clean as $id => $design ) {
            if ( empty( $design['style_heading_color_custom'] ) && isset( $design['style_text_color'] ) ) {
                $clean[ $id ]['style_heading_color'] = $design['style_text_color'];
            }
            unset( $clean[ $id ]['style_heading_color_custom'] );
        }
    }

    $s['designs'] = $clean;

    // Order lists exactly the existing design ids, in a stable sequence.
    $order = array();
    if ( is_array( $s['order'] ) ) {
        foreach ( $s['order'] as $id ) {
            $id = (string) $id;
            if ( isset( $clean[ $id ] ) && ! in_array( $id, $order, true ) ) {
                $order[] = $id;
            }
        }
    }
    foreach ( array_keys( $clean ) as $id ) {
        if ( ! in_array( $id, $order, true ) ) {
            $order[] = $id;
        }
    }
    $s['order'] = $order;

    if ( ! isset( $clean[ $s['active'] ] ) ) {
        $s['active'] = $order ? $order[0] : '';
    }

    $s['enabled']           = ! empty( $s['enabled'] );
    $s['test_mode_enabled'] = ! empty( $s['test_mode_enabled'] );
    $s['test_mode_page_id'] = (int) $s['test_mode_page_id'];
    $s['schema']            = 3;

    return $s;
}

/**
 * Full structured settings (always v2-normalized).
 */
function aqm_popup_get_settings() {
    return aqm_popup_normalize_settings( get_option( 'aqm_popup_settings', array() ) );
}

/**
 * One design's fields (merged with design defaults). Defaults to the active
 * design. Returns design defaults if the id is unknown.
 */
function aqm_popup_get_design_settings( $id = null ) {
    $s = aqm_popup_get_settings();
    if ( null === $id || '' === $id ) {
        $id = $s['active'];
    }
    if ( isset( $s['designs'][ $id ] ) ) {
        return $s['designs'][ $id ];
    }
    return aqm_popup_design_defaults();
}

/**
 * Font registry. Key => label + CSS stack + Google Fonts family param ('' for
 * the theme-default / no-Google option). Shared by the admin, preview, and
 * frontend so font choices stay consistent.
 */
function aqm_popup_fonts() {
    return array(
        ''             => array( 'label' => __( 'Theme default', 'aqm-popup' ), 'stack' => '',                          'google' => '' ),
        'inter'        => array( 'label' => 'Inter',                            'stack' => "'Inter', sans-serif",        'google' => 'Inter:wght@400;500;600;700;800' ),
        'poppins'      => array( 'label' => 'Poppins',                          'stack' => "'Poppins', sans-serif",      'google' => 'Poppins:wght@400;500;600;700;800' ),
        'montserrat'   => array( 'label' => 'Montserrat',                       'stack' => "'Montserrat', sans-serif",   'google' => 'Montserrat:wght@400;500;600;700;800' ),
        'roboto'       => array( 'label' => 'Roboto',                           'stack' => "'Roboto', sans-serif",       'google' => 'Roboto:wght@400;500;700;900' ),
        'lato'         => array( 'label' => 'Lato',                             'stack' => "'Lato', sans-serif",         'google' => 'Lato:wght@400;700;900' ),
        'opensans'     => array( 'label' => 'Open Sans',                        'stack' => "'Open Sans', sans-serif",    'google' => 'Open+Sans:wght@400;500;600;700;800' ),
        'playfair'     => array( 'label' => 'Playfair Display',                 'stack' => "'Playfair Display', serif",  'google' => 'Playfair+Display:wght@400;500;600;700;800' ),
        'merriweather' => array( 'label' => 'Merriweather',                     'stack' => "'Merriweather', serif",      'google' => 'Merriweather:wght@400;700;900' ),
    );
}

function aqm_popup_font_stack( $key ) {
    $fonts = aqm_popup_fonts();
    return isset( $fonts[ $key ] ) ? $fonts[ $key ]['stack'] : '';
}

function aqm_popup_google_font_url( $key ) {
    $fonts = aqm_popup_fonts();
    if ( empty( $key ) || empty( $fonts[ $key ] ) || empty( $fonts[ $key ]['google'] ) ) {
        return '';
    }
    return 'https://fonts.googleapis.com/css2?family=' . $fonts[ $key ]['google'] . '&display=swap';
}

register_activation_hook( __FILE__, 'aqm_popup_activate' );
function aqm_popup_activate() {
    $stored = get_option( 'aqm_popup_settings', false );
    if ( false === $stored ) {
        add_option( 'aqm_popup_settings', aqm_popup_default_settings() );
    } else {
        // Persist the migrated structure once so saves/edits work cleanly.
        update_option( 'aqm_popup_settings', aqm_popup_normalize_settings( $stored ) );
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
