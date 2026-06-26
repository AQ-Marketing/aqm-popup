<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AQM_Popup_Admin {
    const OPTION_KEY = 'aqm_popup_settings';
    const PAGE_SLUG  = 'aqm-popup';

    private static $instance = null;
    private $hook_suffix     = '';
    private $editing_id      = '';

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'plugin_action_links_' . AQM_POPUP_BASENAME, array( $this, 'plugin_action_links' ) );
        add_action( 'wp_ajax_aqm_popup_check_updates', array( $this, 'ajax_check_updates' ) );
        add_action( 'admin_post_aqm_popup_designs', array( $this, 'handle_design_action' ) );
    }

    public function register_menu() {
        $this->hook_suffix = add_menu_page(
            __( 'AQM Popup', 'aqm-popup' ),
            __( 'AQM Popup', 'aqm-popup' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' ),
            'dashicons-megaphone',
            30
        );
    }

    public function plugin_action_links( $links ) {
        $url             = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        $settings_link   = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'aqm-popup' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    /* ----------------------------------------------------------------
     * Which design is being edited (from ?design=, default = active).
     * ---------------------------------------------------------------- */
    private function current_design_id() {
        if ( '' !== $this->editing_id ) {
            return $this->editing_id;
        }
        $s  = aqm_popup_get_settings();
        $id = isset( $_GET['design'] ) ? sanitize_text_field( wp_unslash( $_GET['design'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only selection of which design to display.
        if ( '' === $id || ! isset( $s['designs'][ $id ] ) ) {
            $id = $s['active'];
        }
        $this->editing_id = $id;
        return $id;
    }

    private function editing() {
        return aqm_popup_get_design_settings( $this->current_design_id() );
    }

    private function fname( $key, $global = false ) {
        return $global
            ? self::OPTION_KEY . '[' . $key . ']'
            : self::OPTION_KEY . '[design][' . $key . ']';
    }

    private function fval( $key, $global = false, $default = '' ) {
        if ( $global ) {
            $s = aqm_popup_get_settings();
            return isset( $s[ $key ] ) ? $s[ $key ] : $default;
        }
        $d = $this->editing();
        return isset( $d[ $key ] ) ? $d[ $key ] : $default;
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== $this->hook_suffix ) {
            return;
        }
        wp_enqueue_media();
        if ( function_exists( 'wp_enqueue_editor' ) ) {
            wp_enqueue_editor();
        }
        wp_enqueue_style(
            'aqm-popup-admin',
            AQM_POPUP_URL . 'assets/css/admin.css',
            array(),
            AQM_POPUP_VERSION
        );

        // Google Font(s) for the design being edited, so the preview is accurate.
        $editing  = $this->editing();
        $font_url = aqm_popup_google_font_url( isset( $editing['style_font_family'] ) ? $editing['style_font_family'] : '' );
        if ( $font_url ) {
            wp_enqueue_style( 'aqm-popup-admin-font', $font_url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL is versionless.
        }
        $h_font_url = aqm_popup_google_font_url( isset( $editing['style_heading_font_family'] ) ? $editing['style_heading_font_family'] : '' );
        if ( $h_font_url && $h_font_url !== $font_url ) {
            wp_enqueue_style( 'aqm-popup-admin-font-heading', $h_font_url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL is versionless.
        }

        wp_enqueue_script( 'aqm-popup-gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js', array(), '3.12.5', true );
        wp_enqueue_script( 'aqm-popup-three', 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js', array(), 'r128', true );

        wp_enqueue_script(
            'aqm-popup-admin',
            AQM_POPUP_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            AQM_POPUP_VERSION,
            true
        );
        wp_localize_script( 'aqm-popup-admin', 'aqmPopupAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'aqm_popup_check_updates' ),
            'i18n'    => array(
                'checking' => __( 'Checking…', 'aqm-popup' ),
                'failed'   => __( 'Check failed — see browser console for details.', 'aqm-popup' ),
            ),
        ) );

        wp_enqueue_script(
            'aqm-popup-admin-ui',
            AQM_POPUP_URL . 'assets/js/admin-ui.js',
            array( 'aqm-popup-gsap', 'aqm-popup-three' ),
            AQM_POPUP_VERSION,
            true
        );

        // Font maps for the live preview: key => CSS stack, and key => Google
        // Fonts stylesheet URL (so the preview can lazy-load a newly-picked font).
        $font_stacks = array();
        $font_urls   = array();
        foreach ( aqm_popup_fonts() as $k => $f ) {
            $font_stacks[ $k ] = $f['stack'];
            $font_urls[ $k ]   = aqm_popup_google_font_url( $k );
        }
        wp_localize_script( 'aqm-popup-admin-ui', 'aqmPopupUi', array(
            'fonts'    => $font_stacks,
            'fontUrls' => $font_urls,
            'i18n'  => array(
                'enabled'      => __( 'Live', 'aqm-popup' ),
                'disabled'     => __( 'Off', 'aqm-popup' ),
                'testMode'     => __( 'Test mode', 'aqm-popup' ),
                'previewLabel' => __( 'Live preview', 'aqm-popup' ),
                'replay'       => __( 'Replay open', 'aqm-popup' ),
                'navLabel'     => __( 'Settings sections', 'aqm-popup' ),
                'chooseImage'  => __( 'Choose popup image', 'aqm-popup' ),
                'useImage'     => __( 'Use this image', 'aqm-popup' ),
            ),
        ) );
    }

    public function register_settings() {
        register_setting(
            'aqm_popup_settings_group',
            self::OPTION_KEY,
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize' ),
                'default'           => aqm_popup_default_settings(),
            )
        );

        // Shared option lists.
        $font_options = array();
        foreach ( aqm_popup_fonts() as $k => $f ) {
            $font_options[ $k ] = $f['label'];
        }
        $heading_font_options = array( '' => __( 'Same as base font', 'aqm-popup' ) );
        foreach ( aqm_popup_fonts() as $k => $f ) {
            if ( '' !== $k ) {
                $heading_font_options[ $k ] = $f['label'];
            }
        }
        $weight_options    = array( '400' => __( 'Normal (400)', 'aqm-popup' ), '500' => __( 'Medium (500)', 'aqm-popup' ), '600' => __( 'Semibold (600)', 'aqm-popup' ), '700' => __( 'Bold (700)', 'aqm-popup' ), '800' => __( 'Extrabold (800)', 'aqm-popup' ) );
        $transform_options = array( 'none' => __( 'Normal', 'aqm-popup' ), 'uppercase' => __( 'UPPERCASE', 'aqm-popup' ), 'lowercase' => __( 'lowercase', 'aqm-popup' ), 'capitalize' => __( 'Capitalize Each Word', 'aqm-popup' ) );
        $align_options     = array( 'left' => __( 'Left', 'aqm-popup' ), 'center' => __( 'Center', 'aqm-popup' ), 'right' => __( 'Right', 'aqm-popup' ) );
        $heading_align_options = array( 'inherit' => __( 'Same as body', 'aqm-popup' ) ) + $align_options;
        $border_style_options  = array( 'solid' => __( 'Solid', 'aqm-popup' ), 'dashed' => __( 'Dashed', 'aqm-popup' ), 'dotted' => __( 'Dotted', 'aqm-popup' ), 'double' => __( 'Double', 'aqm-popup' ) );
        $vert_options      = array( 'top' => __( 'Top', 'aqm-popup' ), 'center' => __( 'Center', 'aqm-popup' ), 'bottom' => __( 'Bottom', 'aqm-popup' ) );

        // ---- This design (name + schedule) ----
        add_settings_section( 'aqm_popup_design_meta', __( 'This design', 'aqm-popup' ), array( $this, 'section_design_meta_text' ), self::PAGE_SLUG );
        add_settings_field( 'name',       __( 'Design name', 'aqm-popup' ), array( $this, 'field_text' ), self::PAGE_SLUG, 'aqm_popup_design_meta', array( 'key' => 'name', 'placeholder' => __( 'e.g. Spring sale', 'aqm-popup' ) ) );
        add_settings_field( 'start_date', __( 'Start date', 'aqm-popup' ),  array( $this, 'field_date' ), self::PAGE_SLUG, 'aqm_popup_design_meta', array( 'key' => 'start_date', 'description' => __( 'Optional. The active popup will not show before this date (your site timezone). Leave empty for no start limit.', 'aqm-popup' ) ) );
        add_settings_field( 'end_date',   __( 'End date', 'aqm-popup' ),    array( $this, 'field_date' ), self::PAGE_SLUG, 'aqm_popup_design_meta', array( 'key' => 'end_date', 'description' => __( 'Optional. The active popup stops showing after this date (end of day, site timezone). Leave empty for no end.', 'aqm-popup' ) ) );

        // ---- Content (what the popup says) ----
        add_settings_section( 'aqm_popup_content', __( 'Content', 'aqm-popup' ), array( $this, 'section_content_text' ), self::PAGE_SLUG );
        add_settings_field( 'content_image_id',       __( 'Image', 'aqm-popup' ),               array( $this, 'field_image' ),    self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_image_id' ) );
        add_settings_field( 'content_heading',        __( 'Headline', 'aqm-popup' ),            array( $this, 'field_text' ),     self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_heading', 'placeholder' => __( 'e.g. Spring sale — 20% off', 'aqm-popup' ), 'description' => __( 'Style this in the Headline section below.', 'aqm-popup' ) ) );
        add_settings_field( 'content_body',           __( 'Text', 'aqm-popup' ),                array( $this, 'field_richtext' ), self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_body', 'description' => __( 'Rich text — bold, italic, links, and lists. Size, weight, color, and font are set in the Body & button section.', 'aqm-popup' ) ) );
        add_settings_field( 'content_button_label',   __( 'Button label', 'aqm-popup' ),        array( $this, 'field_text' ),     self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_button_label', 'placeholder' => __( 'e.g. Shop now', 'aqm-popup' ), 'description' => __( 'Leave empty to hide the button.', 'aqm-popup' ) ) );
        add_settings_field( 'content_button_url',     __( 'Button link', 'aqm-popup' ),         array( $this, 'field_text' ),     self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_button_url', 'placeholder' => 'https://', 'input_type' => 'url' ) );
        add_settings_field( 'content_button_new_tab', __( 'Open link in new tab', 'aqm-popup' ), array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_button_new_tab' ) );

        // ---- Headline (everything about the headline) ----
        add_settings_section( 'aqm_popup_headline', __( 'Headline', 'aqm-popup' ), array( $this, 'section_headline_text' ), self::PAGE_SLUG );
        add_settings_field( 'style_heading_color',          __( 'Color', 'aqm-popup' ),         array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_color' ) );
        add_settings_field( 'style_heading_font_family',    __( 'Font', 'aqm-popup' ),          array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_font_family', 'options' => $heading_font_options, 'description' => __( 'Optionally give the headline its own font.', 'aqm-popup' ) ) );
        add_settings_field( 'style_heading_size',           __( 'Size (px)', 'aqm-popup' ),     array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_size', 'min' => 10, 'max' => 96, 'step' => 1 ) );
        add_settings_field( 'style_heading_weight',         __( 'Weight', 'aqm-popup' ),        array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_weight', 'options' => $weight_options ) );
        add_settings_field( 'style_heading_align',          __( 'Alignment', 'aqm-popup' ),     array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_align', 'options' => $heading_align_options ) );
        add_settings_field( 'style_heading_transform',      __( 'Letter case', 'aqm-popup' ),   array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_transform', 'options' => $transform_options ) );
        add_settings_field( 'style_heading_italic',         __( 'Italic', 'aqm-popup' ),        array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_italic' ) );
        add_settings_field( 'style_heading_line_height',    __( 'Line height', 'aqm-popup' ),   array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_line_height', 'min' => '0.8', 'max' => '3', 'step' => '0.05', 'description' => __( 'A multiple of the font size (e.g. 1.2).', 'aqm-popup' ) ) );
        add_settings_field( 'style_heading_letter_spacing', __( 'Letter spacing (px)', 'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_letter_spacing', 'min' => -5, 'max' => 20, 'step' => '0.5', 'description' => __( 'Can be negative to tighten.', 'aqm-popup' ) ) );
        add_settings_field( 'style_heading_margin_bottom',  __( 'Space below (px)', 'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_headline', array( 'key' => 'style_heading_margin_bottom', 'min' => 0, 'max' => 80, 'step' => 1 ) );

        // ---- Body & button (paragraph text + the button) ----
        add_settings_section( 'aqm_popup_body', __( 'Body & button', 'aqm-popup' ), array( $this, 'section_body_text' ), self::PAGE_SLUG );
        add_settings_field( 'style_font_family',       __( 'Base font', 'aqm-popup' ),     array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_body', array( 'key' => 'style_font_family', 'options' => $font_options, 'description' => __( 'The popup\'s default font (used by the text, button, and the headline unless it overrides). Google Fonts load automatically; "Theme default" uses your site font.', 'aqm-popup' ) ) );
        add_settings_field( 'style_text_color',        __( 'Text color', 'aqm-popup' ),    array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_body', array( 'key' => 'style_text_color' ) );
        add_settings_field( 'style_body_size',         __( 'Text size (px)', 'aqm-popup' ),array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_body', array( 'key' => 'style_body_size', 'min' => 10, 'max' => 48, 'step' => 1 ) );
        add_settings_field( 'style_body_weight',       __( 'Text weight', 'aqm-popup' ),   array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_body', array( 'key' => 'style_body_weight', 'options' => $weight_options ) );
        add_settings_field( 'style_align',             __( 'Text alignment', 'aqm-popup' ),array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_body', array( 'key' => 'style_align', 'options' => $align_options, 'description' => __( 'Default alignment for all popup text (the headline can override it).', 'aqm-popup' ) ) );
        add_settings_field( 'style_button_bg',         __( 'Button color', 'aqm-popup' ),      array( $this, 'field_color' ), self::PAGE_SLUG, 'aqm_popup_body', array( 'key' => 'style_button_bg' ) );
        add_settings_field( 'style_button_text_color', __( 'Button text color', 'aqm-popup' ), array( $this, 'field_color' ), self::PAGE_SLUG, 'aqm_popup_body', array( 'key' => 'style_button_text_color' ) );

        // ---- Popup box (the card itself) ----
        add_settings_section( 'aqm_popup_box', __( 'Popup box', 'aqm-popup' ), array( $this, 'section_box_text' ), self::PAGE_SLUG );
        add_settings_field( 'style_bg_color',          __( 'Background color', 'aqm-popup' ),    array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_bg_color' ) );
        add_settings_field( 'style_bg_image_id',       __( 'Background image', 'aqm-popup' ),    array( $this, 'field_image' ),  self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_bg_image_id', 'description' => __( 'Optional. Fills the whole popup behind your text and button (scaled to cover). The background color shows while it loads, or if you remove it.', 'aqm-popup' ) ) );
        add_settings_field( 'style_bg_overlay_color',   __( 'Image overlay color', 'aqm-popup' ),  array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_bg_overlay_color' ) );
        add_settings_field( 'style_bg_overlay_opacity', __( 'Image overlay opacity', 'aqm-popup' ),array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_bg_overlay_opacity', 'min' => 0, 'max' => 1, 'step' => '0.05', 'description' => __( 'A tint laid OVER the background image (behind your text) so text stays readable. 0 = no overlay. Only applies when a background image is set.', 'aqm-popup' ) ) );
        add_settings_field( 'style_max_width',         __( 'Max width (px)', 'aqm-popup' ),      array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_max_width', 'min' => 240, 'max' => 1200, 'step' => 10, 'description' => __( 'How wide the popup can grow on larger screens.', 'aqm-popup' ) ) );
        add_settings_field( 'style_min_height',        __( 'Minimum height (px)', 'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_min_height', 'min' => 0, 'max' => 1200, 'step' => 10, 'description' => __( 'Force the popup to be at least this tall. Needed for vertical centering to have room to work. 0 = fit content.', 'aqm-popup' ) ) );
        add_settings_field( 'style_padding',           __( 'Inner padding (px)', 'aqm-popup' ),  array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_padding', 'min' => 0, 'max' => 96, 'step' => 1, 'description' => __( 'Space between the popup edge and the text/button. The image sits flush at the top.', 'aqm-popup' ) ) );
        add_settings_field( 'style_vertical_align',    __( 'Vertical alignment', 'aqm-popup' ),  array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_vertical_align', 'options' => $vert_options, 'description' => __( 'Where the content sits within the popup. Center/Bottom only differ from Top when the popup is taller than its content (e.g. with a Minimum height or background image).', 'aqm-popup' ) ) );
        add_settings_field( 'style_border_width', __( 'Border width (px)', 'aqm-popup' ),  array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_border_width', 'min' => 0, 'max' => 40, 'step' => 1, 'description' => __( 'A border around the whole popup. 0 = no border.', 'aqm-popup' ) ) );
        add_settings_field( 'style_border_style', __( 'Border style', 'aqm-popup' ),       array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_border_style', 'options' => $border_style_options ) );
        add_settings_field( 'style_border_color', __( 'Border color', 'aqm-popup' ),       array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'style_border_color' ) );
        add_settings_field( 'popup_border_radius_px', __( 'Border radius (px)', 'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'popup_border_radius_px', 'min' => 0, 'max' => 200, 'step' => 1, 'description' => __( 'Rounded corners on the popup (and the border, if set above).', 'aqm-popup' ) ) );
        add_settings_field( 'popup_border',           __( 'Border (advanced CSS)',     'aqm-popup' ), array( $this, 'field_text' ),   self::PAGE_SLUG, 'aqm_popup_box', array( 'key' => 'popup_border',          'placeholder' => 'e.g. 5px solid #ffffff', 'description' => __( 'Optional. Leave empty to use the Border width/style/color above. If set, this CSS <code>border</code> shorthand overrides them — e.g. <code>5px solid #ffffff</code>.', 'aqm-popup' ) ) );

        // ---- Backdrop (the dark area behind the popup) ----
        add_settings_section( 'aqm_popup_backdrop', __( 'Backdrop', 'aqm-popup' ), array( $this, 'section_backdrop_text' ), self::PAGE_SLUG );
        add_settings_field( 'style_backdrop_color',       __( 'Backdrop color',            'aqm-popup' ), array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_backdrop', array( 'key' => 'style_backdrop_color', 'description' => __( 'The screen behind the popup. Combined with the opacity below (e.g. black at 0.7, or a brand color at 0.5).', 'aqm-popup' ) ) );
        add_settings_field( 'overlay_opacity',            __( 'Backdrop opacity',          'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_backdrop', array( 'key' => 'overlay_opacity', 'min' => 0, 'max' => 1, 'step' => '0.05', 'description' => __( 'How opaque the backdrop is. 0 (transparent) to 1 (solid).', 'aqm-popup' ) ) );
        add_settings_field( 'overlay_padding_vertical',   __( 'Edge gap — top/bottom (px)', 'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_backdrop', array( 'key' => 'overlay_padding_vertical',   'min' => 0, 'step' => 1, 'description' => __( 'Inset the popup from the top/bottom of the screen. The backdrop fills the gap.', 'aqm-popup' ) ) );
        add_settings_field( 'overlay_padding_horizontal', __( 'Edge gap — left/right (px)', 'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_backdrop', array( 'key' => 'overlay_padding_horizontal', 'min' => 0, 'step' => 1, 'description' => __( 'Inset the popup from the left/right of the screen.', 'aqm-popup' ) ) );

        // ---- Close button ----
        add_settings_section( 'aqm_popup_close_icon', __( 'Close button', 'aqm-popup' ), array( $this, 'section_close_icon_text' ), self::PAGE_SLUG );
        add_settings_field( 'close_size_px',          __( 'Button size (px)',         'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_size_px',         'min' => 16, 'max' => 200, 'step' => 1, 'description' => __( 'Width and height of the close button. The X icon scales proportionally.', 'aqm-popup' ) ) );
        add_settings_field( 'close_offset_px',        __( 'Distance from corner (px)','aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_offset_px',       'min' => -100, 'max' => 100, 'step' => 1, 'description' => __( 'How far the button sits from the popup\'s top-right corner. Use a negative number to place it OUTSIDE the popup — e.g. -16.', 'aqm-popup' ) ) );
        add_settings_field( 'close_background',       __( 'Background',                'aqm-popup' ), array( $this, 'field_text' ),   self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_background',      'placeholder' => 'transparent', 'description' => __( 'Any valid CSS color. Examples: <code>transparent</code>, <code>rgba(0,0,0,0.55)</code>, <code>#ffffff</code>.', 'aqm-popup' ) ) );
        add_settings_field( 'close_icon_color',       __( 'Icon color',                'aqm-popup' ), array( $this, 'field_text' ),   self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_icon_color',      'placeholder' => '#ffffff',     'description' => __( 'Color of the X mark. Any valid CSS color.', 'aqm-popup' ) ) );
        add_settings_field( 'close_border_radius_px', __( 'Border radius (px)',        'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_border_radius_px','min' => 0, 'max' => 100, 'step' => 1, 'description' => __( 'Roundness of the background. Half the button size = a circle; 0 = a square.', 'aqm-popup' ) ) );

        // ---- Triggers ----
        add_settings_section( 'aqm_popup_triggers', __( 'Triggers', 'aqm-popup' ), array( $this, 'section_triggers_text' ), self::PAGE_SLUG );
        add_settings_field( 'trigger_delay',  __( 'Time delay',     'aqm-popup' ), array( $this, 'field_trigger_delay' ),  self::PAGE_SLUG, 'aqm_popup_triggers' );
        add_settings_field( 'trigger_scroll', __( 'Scroll depth',   'aqm-popup' ), array( $this, 'field_trigger_scroll' ), self::PAGE_SLUG, 'aqm_popup_triggers' );
        add_settings_field( 'trigger_exit',   __( 'Exit intent',    'aqm-popup' ), array( $this, 'field_trigger_exit' ),   self::PAGE_SLUG, 'aqm_popup_triggers' );
        add_settings_field( 'trigger_click',  __( 'Click selector', 'aqm-popup' ), array( $this, 'field_trigger_click' ),  self::PAGE_SLUG, 'aqm_popup_triggers' );

        // ---- Frequency ----
        add_settings_section( 'aqm_popup_frequency', __( 'Frequency', 'aqm-popup' ), array( $this, 'section_frequency_text' ), self::PAGE_SLUG );
        add_settings_field( 'max_per_session', __( 'Max shows per session', 'aqm-popup' ),    array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_frequency', array( 'key' => 'max_per_session', 'min' => 1, 'step' => 1, 'description' => __( 'How many times the popup can appear during a single browser session.', 'aqm-popup' ) ) );
        add_settings_field( 'cooldown_days',   __( 'Cooldown after dismissal (days)', 'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_frequency', array( 'key' => 'cooldown_days', 'min' => 0, 'step' => '0.5', 'description' => __( 'After a visitor closes the popup, suppress it for this many days. Set to 0 to disable the cooldown.', 'aqm-popup' ) ) );

        // ---- Behavior (how it closes) ----
        add_settings_section( 'aqm_popup_behavior', __( 'Behavior', 'aqm-popup' ), array( $this, 'section_behavior_text' ), self::PAGE_SLUG );
        add_settings_field( 'close_on_overlay_click', __( 'Close on click outside', 'aqm-popup' ), array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'close_on_overlay_click', 'description' => __( 'Clicking the dark backdrop dismisses the popup and starts the cooldown.', 'aqm-popup' ) ) );
        add_settings_field( 'close_on_esc',           __( 'Close on ESC key',       'aqm-popup' ), array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'close_on_esc' ) );

        // ---- Test mode (global) ----
        add_settings_section( 'aqm_popup_test_mode', __( 'Test mode', 'aqm-popup' ), array( $this, 'section_test_mode_text' ), self::PAGE_SLUG );
        add_settings_field( 'test_mode_enabled', __( 'Enable test mode', 'aqm-popup' ), array( $this, 'field_checkbox' ),       self::PAGE_SLUG, 'aqm_popup_test_mode', array( 'key' => 'test_mode_enabled', 'global' => true, 'description' => __( 'Preview the ACTIVE design on the selected page only. Frequency is ignored and triggers re-arm after dismissal. Site-wide setting (not per design).', 'aqm-popup' ) ) );
        add_settings_field( 'test_mode_page_id', __( 'Test page',        'aqm-popup' ), array( $this, 'field_test_mode_page' ), self::PAGE_SLUG, 'aqm_popup_test_mode' );
    }

    /* ---------------- section intros ---------------- */
    public function section_design_meta_text() {
        echo '<p>' . esc_html__( 'You are editing one design. Use the Designs list above to switch, add, duplicate, archive, or activate a design. Dates here control when the ACTIVE design is allowed to show.', 'aqm-popup' ) . '</p>';
    }
    public function section_content_text() {
        echo '<p>' . esc_html__( 'What the popup says: an image, a headline, a short paragraph, and an optional button. Any field you leave empty is skipped. Styling lives in the sections below.', 'aqm-popup' ) . '</p>';
    }
    public function section_headline_text() {
        echo '<p>' . esc_html__( 'Everything about the headline — color, font, size, weight, alignment, and spacing. (Only shows when you set a headline in Content.)', 'aqm-popup' ) . '</p>';
    }
    public function section_body_text() {
        echo '<p>' . esc_html__( 'The paragraph text and the button. The base font here is the popup default; the headline can override it.', 'aqm-popup' ) . '</p>';
    }
    public function section_box_text() {
        echo '<p>' . esc_html__( 'The popup card itself — background, size, padding, vertical alignment, and border.', 'aqm-popup' ) . '</p>';
    }
    public function section_backdrop_text() {
        echo '<p>' . esc_html__( 'The dark screen behind the popup, and how far the popup sits from the screen edges.', 'aqm-popup' ) . '</p>';
    }
    public function section_triggers_text() {
        echo '<p>' . esc_html__( 'Enable any combination of triggers. The popup appears as soon as the first enabled trigger fires.', 'aqm-popup' ) . '</p>';
    }
    public function section_frequency_text() {
        echo '<p>' . esc_html__( 'Control how often the popup shows. Per-session count resets when the browser tab closes; cooldown persists across sessions.', 'aqm-popup' ) . '</p>';
    }
    public function section_behavior_text() {
        echo '<p>' . esc_html__( 'How visitors can dismiss the popup.', 'aqm-popup' ) . '</p>';
    }
    public function section_close_icon_text() {
        echo '<p>' . esc_html__( 'Style the X button that closes the popup.', 'aqm-popup' ) . '</p>';
    }
    public function section_test_mode_text() {
        echo '<p>' . esc_html__( 'Preview the active design on a single page without affecting the live site. While test mode is on:', 'aqm-popup' ) . '</p>';
        echo '<ul style="list-style:disc;margin-left:20px;">';
        echo '<li>' . esc_html__( 'The popup shows ONLY on the selected page below.', 'aqm-popup' ) . '</li>';
        echo '<li>' . esc_html__( 'Frequency and dates are ignored — open it as many times as needed.', 'aqm-popup' ) . '</li>';
        echo '</ul>';
    }

    /* ---------------- field renderers ---------------- */
    public function field_checkbox( $args ) {
        $key     = $args['key'];
        $global  = ! empty( $args['global'] );
        $checked = ! empty( $this->fval( $key, $global ) );
        printf(
            '<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
            esc_attr( $this->fname( $key, $global ) ),
            checked( $checked, true, false ),
            isset( $args['label'] ) ? esc_html( $args['label'] ) : ''
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . wp_kses_post( $args['description'] ) . '</p>';
        }
    }

    public function field_text( $args ) {
        $key         = $args['key'];
        $global      = ! empty( $args['global'] );
        $value       = (string) $this->fval( $key, $global );
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
        $type        = ( isset( $args['input_type'] ) && 'url' === $args['input_type'] ) ? 'url' : 'text';
        printf(
            '<input type="%5$s" name="%1$s" value="%3$s" placeholder="%4$s" class="regular-text" />',
            esc_attr( $this->fname( $key, $global ) ),
            '',
            esc_attr( $value ),
            esc_attr( $placeholder ),
            esc_attr( $type )
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . wp_kses_post( $args['description'] ) . '</p>';
        }
    }

    public function field_date( $args ) {
        $key   = $args['key'];
        $value = (string) $this->fval( $key, false );
        printf(
            '<input type="date" name="%1$s" value="%2$s" class="aqm-date-input" />',
            esc_attr( $this->fname( $key, false ) ),
            esc_attr( $value )
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_textarea( $args ) {
        $key         = $args['key'];
        $value       = (string) $this->fval( $key, false );
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
        printf(
            '<textarea name="%1$s" rows="3" class="large-text" placeholder="%3$s">%2$s</textarea>',
            esc_attr( $this->fname( $key, false ) ),
            esc_textarea( $value ),
            esc_attr( $placeholder )
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_richtext( $args ) {
        $key   = isset( $args['key'] ) ? $args['key'] : 'content_body';
        $value = (string) $this->fval( $key, false );
        echo '<div class="aqm-richtext">';
        wp_editor(
            $value,
            'aqm_popup_content_body',
            array(
                'textarea_name' => $this->fname( $key, false ),
                'textarea_rows' => 8,
                'media_buttons' => true,
                'wpautop'       => true,
                'tinymce'       => true,
                'quicktags'     => true,
            )
        );
        echo '</div>';
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . wp_kses_post( $args['description'] ) . '</p>';
        }
    }

    public function field_number( $args ) {
        $key    = $args['key'];
        $global = ! empty( $args['global'] );
        $value  = (string) $this->fval( $key, $global );
        $min    = isset( $args['min'] )  ? ' min="' . esc_attr( $args['min'] ) . '"'   : '';
        $max    = isset( $args['max'] )  ? ' max="' . esc_attr( $args['max'] ) . '"'   : '';
        $step   = isset( $args['step'] ) ? ' step="' . esc_attr( $args['step'] ) . '"' : '';
        printf(
            '<input type="number" name="%1$s" value="%2$s"%3$s%4$s%5$s class="small-text" />',
            esc_attr( $this->fname( $key, $global ) ),
            esc_attr( $value ),
            $min,
            $max,
            $step
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_color( $args ) {
        $key   = $args['key'];
        $value = (string) $this->fval( $key, false );
        if ( '' === $value || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
            $defaults = aqm_popup_design_defaults();
            $value    = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '#000000';
        }
        printf(
            '<input type="color" name="%1$s" value="%2$s" class="aqm-color-input" />',
            esc_attr( $this->fname( $key, false ) ),
            esc_attr( $value )
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_select( $args ) {
        $key     = $args['key'];
        $global  = ! empty( $args['global'] );
        $current = (string) $this->fval( $key, $global );
        $options = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();

        echo '<select name="' . esc_attr( $this->fname( $key, $global ) ) . '">';
        foreach ( $options as $val => $label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $val ),
                selected( $current, (string) $val, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_image( $args ) {
        $key  = isset( $args['key'] ) ? $args['key'] : 'content_image_id';
        $id   = (int) $this->fval( $key, false, 0 );
        $url  = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
        $desc = isset( $args['description'] ) ? $args['description'] : __( 'Optional. Sits flush at the top of the popup. Leave empty for a text-only popup.', 'aqm-popup' );
        ?>
        <div class="aqm-image-field" data-aqm-image-field data-aqm-image-key="<?php echo esc_attr( $key ); ?>">
            <input type="hidden" name="<?php echo esc_attr( $this->fname( $key, false ) ); ?>" value="<?php echo esc_attr( $id ); ?>" data-aqm-image-input />
            <div class="aqm-image-field__preview" data-aqm-image-preview <?php echo $url ? '' : 'hidden'; ?>>
                <?php if ( $url ) : ?><img src="<?php echo esc_url( $url ); ?>" alt="" /><?php endif; ?>
            </div>
            <p class="aqm-image-field__actions">
                <button type="button" class="button" data-aqm-image-choose><?php esc_html_e( 'Choose image', 'aqm-popup' ); ?></button>
                <button type="button" class="button-link aqm-image-field__remove" data-aqm-image-remove <?php echo $url ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'aqm-popup' ); ?></button>
            </p>
            <p class="description"><?php echo wp_kses_post( $desc ); ?></p>
        </div>
        <?php
    }

    public function field_trigger_delay() {
        $d = $this->editing();
        ?>
        <label class="aqm-popup-trigger-toggle">
            <input type="checkbox" name="<?php echo esc_attr( $this->fname( 'trigger_delay_enabled' ) ); ?>" value="1" <?php checked( ! empty( $d['trigger_delay_enabled'] ) ); ?> data-aqm-trigger="delay" />
            <?php esc_html_e( 'Show after a delay', 'aqm-popup' ); ?>
        </label>
        <div class="aqm-popup-trigger-sub" data-aqm-trigger-sub="delay">
            <label>
                <?php esc_html_e( 'Delay (seconds):', 'aqm-popup' ); ?>
                <input type="number" name="<?php echo esc_attr( $this->fname( 'trigger_delay_seconds' ) ); ?>" value="<?php echo esc_attr( $d['trigger_delay_seconds'] ); ?>" min="0" step="1" class="small-text" />
            </label>
        </div>
        <?php
    }

    public function field_trigger_scroll() {
        $d = $this->editing();
        ?>
        <label class="aqm-popup-trigger-toggle">
            <input type="checkbox" name="<?php echo esc_attr( $this->fname( 'trigger_scroll_enabled' ) ); ?>" value="1" <?php checked( ! empty( $d['trigger_scroll_enabled'] ) ); ?> data-aqm-trigger="scroll" />
            <?php esc_html_e( 'Show after scrolling', 'aqm-popup' ); ?>
        </label>
        <div class="aqm-popup-trigger-sub" data-aqm-trigger-sub="scroll">
            <label>
                <?php esc_html_e( 'Scroll depth (%):', 'aqm-popup' ); ?>
                <input type="number" name="<?php echo esc_attr( $this->fname( 'trigger_scroll_percent' ) ); ?>" value="<?php echo esc_attr( $d['trigger_scroll_percent'] ); ?>" min="1" max="100" step="1" class="small-text" />
            </label>
        </div>
        <?php
    }

    public function field_trigger_exit() {
        $d = $this->editing();
        ?>
        <label class="aqm-popup-trigger-toggle">
            <input type="checkbox" name="<?php echo esc_attr( $this->fname( 'trigger_exit_enabled' ) ); ?>" value="1" <?php checked( ! empty( $d['trigger_exit_enabled'] ) ); ?> data-aqm-trigger="exit" />
            <?php esc_html_e( 'Show when the visitor moves to leave (desktop only)', 'aqm-popup' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Exit-intent detects the cursor moving toward the top of the viewport. Mobile/touch devices skip this automatically.', 'aqm-popup' ); ?></p>
        <?php
    }

    public function field_trigger_click() {
        $d = $this->editing();
        ?>
        <label class="aqm-popup-trigger-toggle">
            <input type="checkbox" name="<?php echo esc_attr( $this->fname( 'trigger_click_enabled' ) ); ?>" value="1" <?php checked( ! empty( $d['trigger_click_enabled'] ) ); ?> data-aqm-trigger="click" />
            <?php esc_html_e( 'Show when a specific element is clicked', 'aqm-popup' ); ?>
        </label>
        <div class="aqm-popup-trigger-sub" data-aqm-trigger-sub="click">
            <label>
                <?php esc_html_e( 'CSS selector:', 'aqm-popup' ); ?>
                <input type="text" name="<?php echo esc_attr( $this->fname( 'trigger_click_selector' ) ); ?>" value="<?php echo esc_attr( $d['trigger_click_selector'] ); ?>" class="regular-text" placeholder=".open-popup, #cta-button" />
            </label>
            <p class="description"><?php esc_html_e( 'Any standard CSS selector. Clicks on matching elements open the popup. If the element is a link, navigation is prevented.', 'aqm-popup' ); ?></p>
        </div>
        <?php
    }

    public function field_test_mode_page() {
        $current = (int) $this->fval( 'test_mode_page_id', true, 0 );
        $pages   = get_posts( array(
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
        ) );

        echo '<select name="' . esc_attr( $this->fname( 'test_mode_page_id', true ) ) . '">';
        echo '<option value="0">' . esc_html__( '— Select a page —', 'aqm-popup' ) . '</option>';
        foreach ( $pages as $page ) {
            $title = $page->post_title ? $page->post_title : sprintf( '(no title — #%d)', $page->ID );
            if ( 'publish' !== $page->post_status ) {
                $title .= ' (' . $page->post_status . ')';
            }
            printf(
                '<option value="%1$d" %2$s>%3$s</option>',
                (int) $page->ID,
                selected( $current, (int) $page->ID, false ),
                esc_html( $title )
            );
        }
        echo '</select>';

        if ( $current ) {
            $url = get_permalink( $current );
            if ( $url ) {
                echo ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="button">' . esc_html__( 'Open test page ↗', 'aqm-popup' ) . '</a>';
            }
        }
        echo '<p class="description">' . esc_html__( 'Drafts are included so you can test privately.', 'aqm-popup' ) . '</p>';
    }

    /* ----------------------------------------------------------------
     * Save: merge the edited design back into the full structure.
     * ---------------------------------------------------------------- */
    public function sanitize( $input ) {
        $out = aqm_popup_get_settings(); // existing structure (preserves other designs, order, active).

        if ( ! is_array( $input ) ) {
            return $out;
        }

        // Global flags.
        $out['enabled']           = ! empty( $input['enabled'] );
        $out['test_mode_enabled'] = ! empty( $input['test_mode_enabled'] );
        $out['test_mode_page_id'] = isset( $input['test_mode_page_id'] ) ? max( 0, (int) $input['test_mode_page_id'] ) : 0;

        // Which design was edited.
        $id = isset( $input['editing_design'] ) ? sanitize_text_field( $input['editing_design'] ) : $out['active'];
        if ( '' === $id || ! isset( $out['designs'][ $id ] ) ) {
            $id = $out['active'];
        }

        if ( isset( $out['designs'][ $id ] ) && isset( $input['design'] ) && is_array( $input['design'] ) ) {
            $out['designs'][ $id ] = $this->sanitize_design( $input['design'], $out['designs'][ $id ] );
        }

        return $out;
    }

    /**
     * Sanitize one design's submitted fields, using the existing design as the
     * base (so fields not present in the form — e.g. `archived` — are preserved).
     */
    private function sanitize_design( $in, $existing ) {
        $defaults = aqm_popup_design_defaults();
        $out      = array_merge( $defaults, is_array( $existing ) ? $existing : array() );

        $out['name'] = isset( $in['name'] ) && '' !== trim( (string) $in['name'] )
            ? sanitize_text_field( $in['name'] )
            : ( isset( $existing['name'] ) ? $existing['name'] : $defaults['name'] );

        $out['start_date'] = $this->sanitize_date( isset( $in['start_date'] ) ? $in['start_date'] : '' );
        $out['end_date']   = $this->sanitize_date( isset( $in['end_date'] ) ? $in['end_date'] : '' );

        // Content.
        $out['content_image_id']       = isset( $in['content_image_id'] ) ? max( 0, (int) $in['content_image_id'] ) : 0;
        $out['content_heading']        = isset( $in['content_heading'] ) ? sanitize_text_field( $in['content_heading'] ) : '';
        $out['content_body']           = isset( $in['content_body'] ) ? wp_kses_post( (string) $in['content_body'] ) : '';
        $out['content_button_label']   = isset( $in['content_button_label'] ) ? sanitize_text_field( $in['content_button_label'] ) : '';
        $out['content_button_url']     = isset( $in['content_button_url'] ) ? esc_url_raw( trim( (string) $in['content_button_url'] ) ) : '';
        $out['content_button_new_tab'] = ! empty( $in['content_button_new_tab'] );

        // Style.
        $out['style_bg_color']           = $this->sanitize_hex( isset( $in['style_bg_color'] ) ? $in['style_bg_color'] : '', $defaults['style_bg_color'] );
        $out['style_bg_image_id']        = isset( $in['style_bg_image_id'] ) ? max( 0, (int) $in['style_bg_image_id'] ) : 0;
        $out['style_bg_overlay_color']   = $this->sanitize_hex( isset( $in['style_bg_overlay_color'] ) ? $in['style_bg_overlay_color'] : '', $defaults['style_bg_overlay_color'] );
        $out['style_bg_overlay_opacity'] = isset( $in['style_bg_overlay_opacity'] ) ? min( 1, max( 0, (float) $in['style_bg_overlay_opacity'] ) ) : $defaults['style_bg_overlay_opacity'];
        $out['style_text_color']         = $this->sanitize_hex( isset( $in['style_text_color'] ) ? $in['style_text_color'] : '', $defaults['style_text_color'] );
        $out['style_button_bg']          = $this->sanitize_hex( isset( $in['style_button_bg'] ) ? $in['style_button_bg'] : '', $defaults['style_button_bg'] );
        $out['style_button_text_color']  = $this->sanitize_hex( isset( $in['style_button_text_color'] ) ? $in['style_button_text_color'] : '', $defaults['style_button_text_color'] );
        $out['style_max_width']          = isset( $in['style_max_width'] ) ? min( 1200, max( 240, (int) $in['style_max_width'] ) ) : $defaults['style_max_width'];
        $out['style_min_height']         = isset( $in['style_min_height'] ) ? min( 1200, max( 0, (int) $in['style_min_height'] ) ) : $defaults['style_min_height'];
        $out['style_padding']            = isset( $in['style_padding'] ) ? min( 96, max( 0, (int) $in['style_padding'] ) ) : $defaults['style_padding'];
        $out['style_align']              = $this->sanitize_choice( isset( $in['style_align'] ) ? $in['style_align'] : '', array( 'left', 'center', 'right' ), 'center' );
        $out['style_vertical_align']     = $this->sanitize_choice( isset( $in['style_vertical_align'] ) ? $in['style_vertical_align'] : '', array( 'top', 'center', 'bottom' ), 'top' );

        // Typography.
        $fonts = aqm_popup_fonts();
        $ff    = isset( $in['style_font_family'] ) ? (string) $in['style_font_family'] : '';
        $out['style_font_family']  = isset( $fonts[ $ff ] ) ? $ff : '';
        $out['style_heading_size'] = isset( $in['style_heading_size'] ) ? min( 96, max( 10, (int) $in['style_heading_size'] ) ) : $defaults['style_heading_size'];
        $out['style_body_size']    = isset( $in['style_body_size'] ) ? min( 48, max( 10, (int) $in['style_body_size'] ) ) : $defaults['style_body_size'];
        $weights                   = array( 400, 500, 600, 700, 800 );
        $out['style_heading_weight'] = ( isset( $in['style_heading_weight'] ) && in_array( (int) $in['style_heading_weight'], $weights, true ) ) ? (int) $in['style_heading_weight'] : 700;
        $out['style_body_weight']    = ( isset( $in['style_body_weight'] ) && in_array( (int) $in['style_body_weight'], $weights, true ) ) ? (int) $in['style_body_weight'] : 400;

        // Headline-specific typography.
        $hff = isset( $in['style_heading_font_family'] ) ? (string) $in['style_heading_font_family'] : '';
        $out['style_heading_font_family']    = isset( $fonts[ $hff ] ) ? $hff : '';
        $out['style_heading_color']          = $this->sanitize_hex( isset( $in['style_heading_color'] ) ? $in['style_heading_color'] : '', $defaults['style_heading_color'] );
        $out['style_heading_line_height']    = isset( $in['style_heading_line_height'] ) ? (float) min( 3, max( 0.8, (float) $in['style_heading_line_height'] ) ) : $defaults['style_heading_line_height'];
        $out['style_heading_letter_spacing'] = isset( $in['style_heading_letter_spacing'] ) ? (float) min( 20, max( -5, (float) $in['style_heading_letter_spacing'] ) ) : $defaults['style_heading_letter_spacing'];
        $out['style_heading_transform']      = $this->sanitize_choice( isset( $in['style_heading_transform'] ) ? $in['style_heading_transform'] : '', array( 'none', 'uppercase', 'lowercase', 'capitalize' ), 'none' );
        $out['style_heading_italic']         = ! empty( $in['style_heading_italic'] );
        $out['style_heading_align']          = $this->sanitize_choice( isset( $in['style_heading_align'] ) ? $in['style_heading_align'] : '', array( 'inherit', 'left', 'center', 'right' ), 'inherit' );
        $out['style_heading_margin_bottom']  = isset( $in['style_heading_margin_bottom'] ) ? min( 80, max( 0, (int) $in['style_heading_margin_bottom'] ) ) : $defaults['style_heading_margin_bottom'];

        // Triggers.
        $out['trigger_delay_enabled']  = ! empty( $in['trigger_delay_enabled'] );
        $out['trigger_delay_seconds']  = isset( $in['trigger_delay_seconds'] ) ? max( 0, (int) $in['trigger_delay_seconds'] ) : $defaults['trigger_delay_seconds'];
        $out['trigger_scroll_enabled'] = ! empty( $in['trigger_scroll_enabled'] );
        $out['trigger_scroll_percent'] = isset( $in['trigger_scroll_percent'] ) ? min( 100, max( 1, (int) $in['trigger_scroll_percent'] ) ) : $defaults['trigger_scroll_percent'];
        $out['trigger_exit_enabled']   = ! empty( $in['trigger_exit_enabled'] );
        $out['trigger_click_enabled']  = ! empty( $in['trigger_click_enabled'] );
        $out['trigger_click_selector'] = isset( $in['trigger_click_selector'] ) ? sanitize_text_field( $in['trigger_click_selector'] ) : '';

        // Frequency.
        $out['max_per_session'] = isset( $in['max_per_session'] ) ? max( 1, (int) $in['max_per_session'] ) : $defaults['max_per_session'];
        $out['cooldown_days']   = isset( $in['cooldown_days'] ) ? max( 0, (float) $in['cooldown_days'] ) : $defaults['cooldown_days'];

        // Behavior.
        $out['close_on_overlay_click']     = ! empty( $in['close_on_overlay_click'] );
        $out['close_on_esc']               = ! empty( $in['close_on_esc'] );
        $out['overlay_opacity']            = isset( $in['overlay_opacity'] ) ? min( 1, max( 0, (float) $in['overlay_opacity'] ) ) : $defaults['overlay_opacity'];
        $out['style_backdrop_color']       = $this->sanitize_hex( isset( $in['style_backdrop_color'] ) ? $in['style_backdrop_color'] : '', $defaults['style_backdrop_color'] );
        $out['overlay_padding_vertical']   = isset( $in['overlay_padding_vertical'] ) ? max( 0, (int) $in['overlay_padding_vertical'] ) : 0;
        $out['overlay_padding_horizontal'] = isset( $in['overlay_padding_horizontal'] ) ? max( 0, (int) $in['overlay_padding_horizontal'] ) : 0;
        $out['style_border_width']         = isset( $in['style_border_width'] ) ? min( 40, max( 0, (int) $in['style_border_width'] ) ) : $defaults['style_border_width'];
        $out['style_border_style']         = $this->sanitize_choice( isset( $in['style_border_style'] ) ? $in['style_border_style'] : '', array( 'solid', 'dashed', 'dotted', 'double' ), 'solid' );
        $out['style_border_color']         = $this->sanitize_hex( isset( $in['style_border_color'] ) ? $in['style_border_color'] : '', $defaults['style_border_color'] );
        $out['popup_border']               = $this->sanitize_css_value( isset( $in['popup_border'] ) ? $in['popup_border'] : '' );
        $out['popup_border_radius_px']     = isset( $in['popup_border_radius_px'] ) ? min( 200, max( 0, (int) $in['popup_border_radius_px'] ) ) : 0;

        // Close icon.
        $out['close_size_px']          = isset( $in['close_size_px'] ) ? min( 200, max( 16, (int) $in['close_size_px'] ) ) : $defaults['close_size_px'];
        $out['close_offset_px']        = isset( $in['close_offset_px'] ) ? min( 100, max( -100, (int) $in['close_offset_px'] ) ) : $defaults['close_offset_px'];
        $out['close_background']       = $this->sanitize_css_value( isset( $in['close_background'] ) ? $in['close_background'] : $defaults['close_background'] );
        $out['close_icon_color']       = $this->sanitize_css_value( isset( $in['close_icon_color'] ) ? $in['close_icon_color'] : $defaults['close_icon_color'] );
        $out['close_border_radius_px'] = isset( $in['close_border_radius_px'] ) ? min( 100, max( 0, (int) $in['close_border_radius_px'] ) ) : $defaults['close_border_radius_px'];
        if ( '' === $out['close_background'] ) {
            $out['close_background'] = $defaults['close_background'];
        }
        if ( '' === $out['close_icon_color'] ) {
            $out['close_icon_color'] = $defaults['close_icon_color'];
        }

        return $out;
    }

    private function sanitize_choice( $value, $allowed, $fallback ) {
        $value = is_string( $value ) ? $value : '';
        return in_array( $value, $allowed, true ) ? $value : $fallback;
    }

    private function sanitize_date( $value ) {
        $value = is_string( $value ) ? trim( $value ) : '';
        if ( '' === $value ) {
            return '';
        }
        $d = DateTime::createFromFormat( 'Y-m-d', $value );
        if ( $d && $d->format( 'Y-m-d' ) === $value ) {
            return $value;
        }
        return '';
    }

    /* ----------------------------------------------------------------
     * Design management actions (add / activate / duplicate / delete /
     * archive). Each is capability + nonce protected.
     * ---------------------------------------------------------------- */
    public function handle_design_action() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions.', 'aqm-popup' ) );
        }
        check_admin_referer( 'aqm_popup_designs' );

        $action = isset( $_REQUEST['aqm_action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['aqm_action'] ) ) : '';
        $id     = isset( $_REQUEST['design'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['design'] ) ) : '';

        $settings = aqm_popup_get_settings();
        $redirect = $id;

        switch ( $action ) {
            case 'add':
                $new_id  = $this->new_design_id( $settings );
                $design  = aqm_popup_design_defaults();
                /* translators: %d: design number */
                $design['name']                = sprintf( __( 'Design %d', 'aqm-popup' ), count( $settings['designs'] ) + 1 );
                $settings['designs'][ $new_id ] = $design;
                $settings['order'][]            = $new_id;
                $redirect                       = $new_id;
                break;

            case 'activate':
                if ( isset( $settings['designs'][ $id ] ) ) {
                    $settings['active'] = $id;
                }
                break;

            case 'duplicate':
                if ( isset( $settings['designs'][ $id ] ) ) {
                    $new_id           = $this->new_design_id( $settings );
                    $copy             = $settings['designs'][ $id ];
                    /* translators: %s: original design name */
                    $copy['name']     = sprintf( __( '%s (copy)', 'aqm-popup' ), $copy['name'] );
                    $copy['archived'] = false;
                    $settings['designs'][ $new_id ] = $copy;
                    // Insert right after the original in the order.
                    $pos       = array_search( $id, $settings['order'], true );
                    $new_order = $settings['order'];
                    if ( false !== $pos ) {
                        array_splice( $new_order, $pos + 1, 0, array( $new_id ) );
                    } else {
                        $new_order[] = $new_id;
                    }
                    $settings['order'] = $new_order;
                    $redirect          = $new_id;
                }
                break;

            case 'archive':
            case 'unarchive':
                if ( isset( $settings['designs'][ $id ] ) ) {
                    $settings['designs'][ $id ]['archived'] = ( 'archive' === $action );
                }
                break;

            case 'delete':
                if ( isset( $settings['designs'][ $id ] ) && count( $settings['designs'] ) > 1 ) {
                    unset( $settings['designs'][ $id ] );
                    $settings['order'] = array_values( array_diff( $settings['order'], array( $id ) ) );
                    if ( $settings['active'] === $id ) {
                        $settings['active'] = $settings['order'] ? $settings['order'][0] : '';
                    }
                    $redirect = $settings['active'];
                }
                break;
        }

        update_option( self::OPTION_KEY, aqm_popup_normalize_settings( $settings ) );

        $url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
        if ( $redirect ) {
            $url = add_query_arg( 'design', rawurlencode( $redirect ), $url );
        }
        wp_safe_redirect( $url );
        exit;
    }

    private function new_design_id( $settings ) {
        $max = 0;
        foreach ( array_keys( $settings['designs'] ) as $id ) {
            if ( preg_match( '/^d(\d+)$/', (string) $id, $m ) ) {
                $max = max( $max, (int) $m[1] );
            }
        }
        return 'd' . ( $max + 1 );
    }

    private function design_action_url( $action, $id = '' ) {
        $url = add_query_arg(
            array(
                'action'     => 'aqm_popup_designs',
                'aqm_action' => $action,
                'design'     => $id,
            ),
            admin_url( 'admin-post.php' )
        );
        return wp_nonce_url( $url, 'aqm_popup_designs' );
    }

    private function edit_url( $id ) {
        return admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&design=' . rawurlencode( $id ) );
    }

    /* ----------------------------------------------------------------
     * Designs manager panel (rendered inside the settings form).
     * ---------------------------------------------------------------- */
    private function render_designs_manager( $settings, $editing_id ) {
        ?>
        <h2><?php esc_html_e( 'Designs', 'aqm-popup' ); ?></h2>
        <div class="aqm-designs">
            <p class="aqm-designs__intro"><?php esc_html_e( 'Your library of popup designs. One is active (live). Activate any design at any time; archive the ones you are not using. Each design can have its own start/end dates.', 'aqm-popup' ); ?></p>

            <label class="aqm-master">
                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> data-aqm-master-enable />
                <span><?php esc_html_e( 'Popup enabled site-wide (master switch)', 'aqm-popup' ); ?></span>
            </label>

            <ul class="aqm-designs__list">
                <?php
                foreach ( $settings['order'] as $id ) {
                    if ( ! isset( $settings['designs'][ $id ] ) ) {
                        continue;
                    }
                    $design     = $settings['designs'][ $id ];
                    $is_active  = ( $settings['active'] === $id );
                    $is_editing = ( $editing_id === $id );
                    $archived   = ! empty( $design['archived'] );
                    $classes    = 'aqm-design';
                    if ( $is_active )  { $classes .= ' is-active'; }
                    if ( $is_editing ) { $classes .= ' is-editing'; }
                    if ( $archived )   { $classes .= ' is-archived'; }

                    $sched = $this->schedule_summary( $design );
                    ?>
                    <li class="<?php echo esc_attr( $classes ); ?>">
                        <div class="aqm-design__main">
                            <span class="aqm-design__name"><?php echo esc_html( $design['name'] ); ?></span>
                            <?php if ( $is_active ) : ?><span class="aqm-design__badge aqm-design__badge--active"><?php esc_html_e( 'Active', 'aqm-popup' ); ?></span><?php endif; ?>
                            <?php if ( $archived ) : ?><span class="aqm-design__badge aqm-design__badge--archived"><?php esc_html_e( 'Archived', 'aqm-popup' ); ?></span><?php endif; ?>
                            <?php if ( $is_editing ) : ?><span class="aqm-design__badge aqm-design__badge--editing"><?php esc_html_e( 'Editing', 'aqm-popup' ); ?></span><?php endif; ?>
                            <?php if ( $sched ) : ?><span class="aqm-design__sched"><?php echo esc_html( $sched ); ?></span><?php endif; ?>
                        </div>
                        <div class="aqm-design__actions">
                            <?php if ( ! $is_editing ) : ?>
                                <a class="button button-small" href="<?php echo esc_url( $this->edit_url( $id ) ); ?>"><?php esc_html_e( 'Edit', 'aqm-popup' ); ?></a>
                            <?php endif; ?>
                            <?php if ( ! $is_active ) : ?>
                                <a class="button button-small button-primary" href="<?php echo esc_url( $this->design_action_url( 'activate', $id ) ); ?>"><?php esc_html_e( 'Activate', 'aqm-popup' ); ?></a>
                            <?php endif; ?>
                            <a class="button button-small" href="<?php echo esc_url( $this->design_action_url( 'duplicate', $id ) ); ?>"><?php esc_html_e( 'Duplicate', 'aqm-popup' ); ?></a>
                            <?php if ( $archived ) : ?>
                                <a class="button button-small" href="<?php echo esc_url( $this->design_action_url( 'unarchive', $id ) ); ?>"><?php esc_html_e( 'Unarchive', 'aqm-popup' ); ?></a>
                            <?php else : ?>
                                <a class="button button-small" href="<?php echo esc_url( $this->design_action_url( 'archive', $id ) ); ?>"><?php esc_html_e( 'Archive', 'aqm-popup' ); ?></a>
                            <?php endif; ?>
                            <?php if ( count( $settings['designs'] ) > 1 ) : ?>
                                <a class="button button-small button-link-delete aqm-design__delete" href="<?php echo esc_url( $this->design_action_url( 'delete', $id ) ); ?>" data-aqm-confirm="<?php esc_attr_e( 'Delete this design permanently?', 'aqm-popup' ); ?>"><?php esc_html_e( 'Delete', 'aqm-popup' ); ?></a>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php
                }
                ?>
            </ul>

            <p class="aqm-designs__add">
                <a class="button" href="<?php echo esc_url( $this->design_action_url( 'add' ) ); ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'Add design', 'aqm-popup' ); ?></a>
                <span class="description"><?php esc_html_e( 'Adding, activating, duplicating, or switching designs reloads the page — save your edits first.', 'aqm-popup' ); ?></span>
            </p>
        </div>
        <?php
    }

    private function schedule_summary( $design ) {
        $start = ! empty( $design['start_date'] ) ? $design['start_date'] : '';
        $end   = ! empty( $design['end_date'] ) ? $design['end_date'] : '';
        if ( '' === $start && '' === $end ) {
            return '';
        }
        if ( '' !== $start && '' !== $end ) {
            /* translators: 1: start date, 2: end date */
            return sprintf( __( '%1$s → %2$s', 'aqm-popup' ), $start, $end );
        }
        if ( '' !== $start ) {
            /* translators: %s: start date */
            return sprintf( __( 'from %s', 'aqm-popup' ), $start );
        }
        /* translators: %s: end date */
        return sprintf( __( 'until %s', 'aqm-popup' ), $end );
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings    = aqm_popup_get_settings();
        $editing_id  = $this->current_design_id();
        $is_enabled  = ! empty( $settings['enabled'] );
        $is_test     = ! empty( $settings['test_mode_enabled'] );
        $repo_url    = 'https://github.com/' . AQM_POPUP_GH_USER . '/' . AQM_POPUP_GH_REPO;

        if ( $is_test ) {
            $status_state = 'test';
            $status_text  = __( 'Test mode', 'aqm-popup' );
        } elseif ( $is_enabled ) {
            $status_state = 'live';
            $status_text  = __( 'Live', 'aqm-popup' );
        } else {
            $status_state = 'off';
            $status_text  = __( 'Off', 'aqm-popup' );
        }
        ?>
        <div class="wrap aqm-popup-settings">
            <div class="aqm-ui" data-aqm-ui>

                <header class="aqm-hero" data-aqm-reveal>
                    <canvas class="aqm-hero__canvas" data-aqm-hero-canvas aria-hidden="true"></canvas>
                    <div class="aqm-hero__inner">
                        <div class="aqm-hero__lead">
                            <span class="aqm-hero__mark" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M3 11l15-6v14L3 13z"/><path d="M3 11v2"/><path d="M8 12.5V18a2 2 0 0 0 4 0"/>
                                </svg>
                            </span>
                            <div>
                                <h1 class="aqm-hero__title"><?php echo esc_html( get_admin_page_title() ); ?></h1>
                                <p class="aqm-hero__sub"><?php esc_html_e( 'Build site-wide popups — a library of designs you can schedule and activate any time.', 'aqm-popup' ); ?></p>
                            </div>
                        </div>
                        <div class="aqm-hero__meta">
                            <span class="aqm-chip aqm-chip--<?php echo esc_attr( $status_state ); ?>" data-aqm-status data-state="<?php echo esc_attr( $status_state ); ?>">
                                <span class="aqm-chip__dot" aria-hidden="true"></span>
                                <span class="aqm-chip__text"><?php echo esc_html( $status_text ); ?></span>
                            </span>
                            <span class="aqm-hero__ver">v<?php echo esc_html( AQM_POPUP_VERSION ); ?></span>
                        </div>
                    </div>
                </header>

                <hr class="wp-header-end" />
                <?php settings_errors(); ?>

                <form method="post" action="options.php" class="aqm-shell" data-aqm-shell>
                    <?php settings_fields( 'aqm_popup_settings_group' ); ?>
                    <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[editing_design]" value="<?php echo esc_attr( $editing_id ); ?>" />

                    <nav class="aqm-nav" data-aqm-nav aria-label="<?php esc_attr_e( 'Settings sections', 'aqm-popup' ); ?>">
                        <span class="aqm-nav__indicator" data-aqm-nav-indicator aria-hidden="true"></span>
                        <ul class="aqm-nav__list" data-aqm-nav-list></ul>
                    </nav>

                    <div class="aqm-main" data-aqm-sections data-aqm-reveal>
                        <?php $this->render_designs_manager( $settings, $editing_id ); ?>
                        <?php do_settings_sections( self::PAGE_SLUG ); ?>

                        <div class="aqm-actions">
                            <?php submit_button( __( 'Save changes', 'aqm-popup' ), 'primary aqm-save', 'submit', false ); ?>
                            <span class="aqm-actions__hint"><?php esc_html_e( 'Saves the design you are editing. Changes apply next time a visitor loads the site.', 'aqm-popup' ); ?></span>
                        </div>
                    </div>

                    <aside class="aqm-aside" data-aqm-aside data-aqm-reveal>
                        <section class="aqm-preview" data-aqm-preview aria-label="<?php esc_attr_e( 'Live preview', 'aqm-popup' ); ?>">
                            <div class="aqm-preview__head">
                                <span class="aqm-preview__label"><?php esc_html_e( 'Live preview', 'aqm-popup' ); ?></span>
                                <button type="button" class="aqm-preview__replay" data-aqm-replay>
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 4v5h-5"/></svg>
                                    <?php esc_html_e( 'Replay', 'aqm-popup' ); ?>
                                </button>
                            </div>
                            <div class="aqm-preview__stage" data-aqm-preview-stage>
                                <div class="aqm-preview__overlay" data-aqm-preview-overlay>
                                    <div class="aqm-preview__popup" data-aqm-preview-popup>
                                        <button type="button" class="aqm-preview__close" data-aqm-preview-close aria-hidden="true" tabindex="-1">
                                            <svg viewBox="0 0 24 24" width="100%" height="100%" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M6 6 L18 18 M18 6 L6 18"/></svg>
                                        </button>
                                        <div class="aqm-preview__content" data-aqm-preview-content>
                                            <img class="aqm-preview__img" data-aqm-preview-img alt="" hidden />
                                            <div class="aqm-preview__body" data-aqm-preview-body>
                                                <h3 class="aqm-preview__heading" data-aqm-preview-heading></h3>
                                                <div class="aqm-preview__text" data-aqm-preview-text></div>
                                                <span class="aqm-preview__btn" data-aqm-preview-btn hidden></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="aqm-preview__note"><?php esc_html_e( 'Shows the design you are editing. The dark backdrop and close button are added automatically.', 'aqm-popup' ); ?></p>
                        </section>

                        <section class="aqm-card aqm-card--updates">
                            <div class="aqm-card__head"><h2 class="aqm-card__title"><?php esc_html_e( 'Plugin updates', 'aqm-popup' ); ?></h2></div>
                            <div class="aqm-card__body">
                                <button type="button" class="button button-secondary aqm-update-btn" id="aqm-popup-check-updates"><?php esc_html_e( 'Check for updates now', 'aqm-popup' ); ?></button>
                                <span id="aqm-popup-check-updates-result" class="aqm-update-result"></span>
                                <p class="description">
                                    <?php
                                    printf(
                                        /* translators: 1: current plugin version, 2: GitHub repo link */
                                        esc_html__( 'Running v%1$s. Updates are pulled from %2$s and cached for 6 hours.', 'aqm-popup' ),
                                        esc_html( AQM_POPUP_VERSION ),
                                        '<a href="' . esc_url( $repo_url ) . '" target="_blank" rel="noopener">' . esc_html( AQM_POPUP_GH_USER . '/' . AQM_POPUP_GH_REPO ) . '</a>'
                                    );
                                    ?>
                                </p>
                            </div>
                        </section>
                    </aside>
                </form>
            </div>
        </div>
        <?php
    }

    public function ajax_check_updates() {
        check_ajax_referer( 'aqm_popup_check_updates', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'aqm-popup' ) ), 403 );
        }

        delete_transient( 'aqm_popup_github_data_' . md5( AQM_POPUP_GH_USER . AQM_POPUP_GH_REPO ) );
        delete_site_transient( 'update_plugins' );
        wp_clean_plugins_cache( true );

        if ( ! function_exists( 'wp_update_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        wp_update_plugins();

        $transient       = get_site_transient( 'update_plugins' );
        $current_version = AQM_POPUP_VERSION;
        $new_version     = null;

        if ( $transient && isset( $transient->response[ AQM_POPUP_BASENAME ]->new_version ) ) {
            $new_version = $transient->response[ AQM_POPUP_BASENAME ]->new_version;
        }

        if ( $new_version && version_compare( $new_version, $current_version, '>' ) ) {
            wp_send_json_success( array(
                'message' => sprintf(
                    /* translators: 1: new version, 2: current version */
                    __( 'Update available: v%1$s (you are on v%2$s). Go to Plugins → Installed Plugins to install.', 'aqm-popup' ),
                    $new_version,
                    $current_version
                ),
                'update_available' => true,
                'new_version'      => $new_version,
                'current_version'  => $current_version,
                'updates_url'      => admin_url( 'plugins.php' ),
            ) );
        }

        wp_send_json_success( array(
            'message' => sprintf(
                /* translators: %s: current plugin version */
                __( 'You are running the latest version (v%s).', 'aqm-popup' ),
                $current_version
            ),
            'update_available' => false,
            'current_version'  => $current_version,
        ) );
    }

    /**
     * Sanitize a free-form CSS value (color, length, border shorthand, etc.)
     * for safe injection into the inline `<style>` block the display class
     * emits. Strips characters that could break out of the rule, then allowlists
     * only the color functions (rgb/rgba/hsl/hsla) — blocking url(), etc.
     */
    private function sanitize_css_value( $input ) {
        $input = sanitize_text_field( (string) $input );
        $input = preg_replace( '/[<>;{}"\'\\\\]/', '', $input );
        $input = trim( $input );

        if ( '' === $input ) {
            return '';
        }

        if ( false !== strpos( $input, '(' ) || false !== strpos( $input, ')' ) ) {
            $stripped = preg_replace( '/\b(?:rgba?|hsla?)\s*\([^()]*\)/i', '', $input );
            if ( false !== strpos( $stripped, '(' ) || false !== strpos( $stripped, ')' ) ) {
                return '';
            }
        }

        return $input;
    }

    private function sanitize_hex( $input, $fallback ) {
        $input = is_string( $input ) ? trim( $input ) : '';
        if ( function_exists( 'sanitize_hex_color' ) ) {
            $clean = sanitize_hex_color( $input );
            if ( $clean ) {
                return $clean;
            }
        }
        if ( preg_match( '/^#[0-9a-fA-F]{6}$/', $input ) ) {
            return strtolower( $input );
        }
        return $fallback;
    }
}
