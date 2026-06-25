<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AQM_Popup_Admin {
    const OPTION_KEY = 'aqm_popup_settings';
    const PAGE_SLUG  = 'aqm-popup';

    private static $instance = null;
    private $hook_suffix     = '';

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

    public function enqueue_assets( $hook ) {
        if ( $hook !== $this->hook_suffix ) {
            return;
        }
        // Media library picker for the popup image field.
        wp_enqueue_media();
        wp_enqueue_style(
            'aqm-popup-admin',
            AQM_POPUP_URL . 'assets/css/admin.css',
            array(),
            AQM_POPUP_VERSION
        );

        // Animation libraries (GSAP core + three.js) for the branded header and
        // motion. Loaded from cdnjs; the UI script checks `window.gsap` /
        // `window.THREE` before use, so a blocked CDN degrades to a clean,
        // fully functional static page.
        wp_enqueue_script(
            'aqm-popup-gsap',
            'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js',
            array(),
            '3.12.5',
            true
        );
        wp_enqueue_script(
            'aqm-popup-three',
            'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js',
            array(),
            'r128',
            true
        );

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

        // Redesign behaviors: scroll-spy nav, live preview, three.js header,
        // GSAP reveals. Depends on the libraries above (loads after them).
        wp_enqueue_script(
            'aqm-popup-admin-ui',
            AQM_POPUP_URL . 'assets/js/admin-ui.js',
            array( 'aqm-popup-gsap', 'aqm-popup-three' ),
            AQM_POPUP_VERSION,
            true
        );
        wp_localize_script( 'aqm-popup-admin-ui', 'aqmPopupUi', array(
            'i18n' => array(
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

        add_settings_section(
            'aqm_popup_content',
            __( 'Content', 'aqm-popup' ),
            array( $this, 'section_content_text' ),
            self::PAGE_SLUG
        );

        add_settings_field( 'enabled',                __( 'Enable popup', 'aqm-popup' ),        array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'enabled', 'description' => __( 'Turn the popup on for the whole site. Use Test mode below to preview before enabling.', 'aqm-popup' ) ) );
        add_settings_field( 'content_image_id',       __( 'Image', 'aqm-popup' ),               array( $this, 'field_image' ),    self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_image_id' ) );
        add_settings_field( 'content_heading',        __( 'Headline', 'aqm-popup' ),            array( $this, 'field_text' ),     self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_heading', 'placeholder' => __( 'e.g. Spring sale — 20% off', 'aqm-popup' ) ) );
        add_settings_field( 'content_body',           __( 'Text', 'aqm-popup' ),                array( $this, 'field_textarea' ), self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_body', 'description' => __( 'A short paragraph. Line breaks are preserved.', 'aqm-popup' ) ) );
        add_settings_field( 'content_button_label',   __( 'Button label', 'aqm-popup' ),        array( $this, 'field_text' ),     self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_button_label', 'placeholder' => __( 'e.g. Shop now', 'aqm-popup' ), 'description' => __( 'Leave empty to hide the button.', 'aqm-popup' ) ) );
        add_settings_field( 'content_button_url',     __( 'Button link', 'aqm-popup' ),         array( $this, 'field_text' ),     self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_button_url', 'placeholder' => 'https://', 'input_type' => 'url' ) );
        add_settings_field( 'content_button_new_tab', __( 'Open link in new tab', 'aqm-popup' ), array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'aqm_popup_content', array( 'key' => 'content_button_new_tab' ) );

        add_settings_section(
            'aqm_popup_style',
            __( 'Popup style', 'aqm-popup' ),
            array( $this, 'section_style_text' ),
            self::PAGE_SLUG
        );

        add_settings_field( 'style_bg_color',          __( 'Background color', 'aqm-popup' ),    array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_style', array( 'key' => 'style_bg_color' ) );
        add_settings_field( 'style_bg_image_id',       __( 'Background image', 'aqm-popup' ),    array( $this, 'field_image' ),  self::PAGE_SLUG, 'aqm_popup_style', array( 'key' => 'style_bg_image_id', 'description' => __( 'Optional. Fills the whole popup behind your text and button (scaled to cover). The background color shows while it loads, or if you remove it. For readable text over a photo, set the text color to contrast.', 'aqm-popup' ) ) );
        add_settings_field( 'style_text_color',        __( 'Text color', 'aqm-popup' ),          array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_style', array( 'key' => 'style_text_color' ) );
        add_settings_field( 'style_button_bg',         __( 'Button color', 'aqm-popup' ),        array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_style', array( 'key' => 'style_button_bg' ) );
        add_settings_field( 'style_button_text_color', __( 'Button text color', 'aqm-popup' ),   array( $this, 'field_color' ),  self::PAGE_SLUG, 'aqm_popup_style', array( 'key' => 'style_button_text_color' ) );
        add_settings_field( 'style_max_width',         __( 'Max width (px)', 'aqm-popup' ),      array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_style', array( 'key' => 'style_max_width', 'min' => 240, 'max' => 1200, 'step' => 10, 'description' => __( 'How wide the popup can grow on larger screens.', 'aqm-popup' ) ) );
        add_settings_field( 'style_padding',           __( 'Inner padding (px)', 'aqm-popup' ),  array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_style', array( 'key' => 'style_padding', 'min' => 0, 'max' => 96, 'step' => 1, 'description' => __( 'Space between the popup edge and the text/button. The image sits flush at the top.', 'aqm-popup' ) ) );
        add_settings_field( 'style_align',             __( 'Text alignment', 'aqm-popup' ),      array( $this, 'field_select' ), self::PAGE_SLUG, 'aqm_popup_style', array( 'key' => 'style_align', 'options' => array( 'left' => __( 'Left', 'aqm-popup' ), 'center' => __( 'Center', 'aqm-popup' ) ) ) );

        add_settings_section(
            'aqm_popup_triggers',
            __( 'Triggers', 'aqm-popup' ),
            array( $this, 'section_triggers_text' ),
            self::PAGE_SLUG
        );

        add_settings_field( 'trigger_delay',  __( 'Time delay',     'aqm-popup' ), array( $this, 'field_trigger_delay' ),  self::PAGE_SLUG, 'aqm_popup_triggers' );
        add_settings_field( 'trigger_scroll', __( 'Scroll depth',   'aqm-popup' ), array( $this, 'field_trigger_scroll' ), self::PAGE_SLUG, 'aqm_popup_triggers' );
        add_settings_field( 'trigger_exit',   __( 'Exit intent',    'aqm-popup' ), array( $this, 'field_trigger_exit' ),   self::PAGE_SLUG, 'aqm_popup_triggers' );
        add_settings_field( 'trigger_click',  __( 'Click selector', 'aqm-popup' ), array( $this, 'field_trigger_click' ),  self::PAGE_SLUG, 'aqm_popup_triggers' );

        add_settings_section(
            'aqm_popup_frequency',
            __( 'Frequency', 'aqm-popup' ),
            array( $this, 'section_frequency_text' ),
            self::PAGE_SLUG
        );

        add_settings_field( 'max_per_session', __( 'Max shows per session', 'aqm-popup' ),    array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_frequency', array( 'key' => 'max_per_session', 'min' => 1, 'step' => 1, 'description' => __( 'How many times the popup can appear during a single browser session.', 'aqm-popup' ) ) );
        add_settings_field( 'cooldown_days',   __( 'Cooldown after dismissal (days)', 'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_frequency', array( 'key' => 'cooldown_days', 'min' => 0, 'step' => '0.5', 'description' => __( 'After a visitor closes the popup, suppress it for this many days. Set to 0 to disable the cooldown.', 'aqm-popup' ) ) );

        add_settings_section(
            'aqm_popup_behavior',
            __( 'Behavior', 'aqm-popup' ),
            '__return_false',
            self::PAGE_SLUG
        );

        add_settings_field( 'close_on_overlay_click', __( 'Close on click outside', 'aqm-popup' ), array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'close_on_overlay_click', 'description' => __( 'Clicking the dark overlay area dismisses the popup and starts the cooldown.', 'aqm-popup' ) ) );
        add_settings_field( 'close_on_esc',           __( 'Close on ESC key',       'aqm-popup' ), array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'close_on_esc' ) );
        add_settings_field( 'overlay_opacity',        __( 'Overlay opacity',        'aqm-popup' ), array( $this, 'field_number' ),   self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'overlay_opacity', 'min' => 0, 'max' => 1, 'step' => '0.05', 'description' => __( 'The dark backdrop behind the popup. Between 0 (transparent) and 1 (opaque black).', 'aqm-popup' ) ) );
        add_settings_field( 'overlay_padding_vertical',   __( 'Overlay padding — top/bottom (px)',   'aqm-popup' ), array( $this, 'field_number' ),   self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'overlay_padding_vertical',   'min' => 0, 'step' => 1, 'description' => __( 'Inset the popup vertically from the viewport edges. The dark backdrop fills the padded area (NOT a white frame). Useful for keeping the popup off the very top/bottom of small screens, or for visually centering tall content.', 'aqm-popup' ) ) );
        add_settings_field( 'overlay_padding_horizontal', __( 'Overlay padding — left/right (px)',   'aqm-popup' ), array( $this, 'field_number' ),   self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'overlay_padding_horizontal', 'min' => 0, 'step' => 1, 'description' => __( 'Inset the popup horizontally from the viewport edges. The dark backdrop fills the padded area.', 'aqm-popup' ) ) );

        add_settings_section(
            'aqm_popup_close_icon',
            __( 'Close icon', 'aqm-popup' ),
            array( $this, 'section_close_icon_text' ),
            self::PAGE_SLUG
        );

        add_settings_field( 'popup_border',           __( 'Popup border',              'aqm-popup' ), array( $this, 'field_text' ),   self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'popup_border',          'placeholder' => 'e.g. 5px solid #ffffff', 'description' => __( 'Optional CSS <code>border</code> shorthand applied around the whole popup. Examples: <code>5px solid #ffffff</code>, <code>2px dashed #c10f30</code>, <code>10px solid rgba(255,255,255,0.5)</code>. Leave empty for no border.', 'aqm-popup' ) ) );
        add_settings_field( 'popup_border_radius_px', __( 'Popup border radius (px)', 'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'popup_border_radius_px', 'min' => 0, 'max' => 200, 'step' => 1, 'description' => __( 'Rounded corners on the popup container (and the border, if set above).', 'aqm-popup' ) ) );

        add_settings_field( 'close_size_px',          __( 'Button size (px)',         'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_size_px',         'min' => 16, 'max' => 200, 'step' => 1, 'description' => __( 'Width and height of the close button. The X icon scales proportionally (icon = button size × 0.5).', 'aqm-popup' ) ) );
        add_settings_field( 'close_offset_px',        __( 'Distance from corner (px)','aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_offset_px',       'min' => -100, 'max' => 100, 'step' => 1, 'description' => __( 'How far the button sits from the popup\'s top-right corner. Use a negative number to place it OUTSIDE the popup — e.g. -16 floats the X just past the corner.', 'aqm-popup' ) ) );
        add_settings_field( 'close_background',       __( 'Background',                'aqm-popup' ), array( $this, 'field_text' ),   self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_background',      'placeholder' => 'transparent', 'description' => __( 'Any valid CSS color. Examples: <code>transparent</code> (default — bare X with a drop-shadow halo), <code>rgba(0,0,0,0.55)</code> (translucent dark circle, the v1.0.0–v1.0.9 look), <code>#ffffff</code> (solid white), <code>#000</code>.', 'aqm-popup' ) ) );
        add_settings_field( 'close_icon_color',       __( 'Icon color',                'aqm-popup' ), array( $this, 'field_text' ),   self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_icon_color',      'placeholder' => '#ffffff',     'description' => __( 'Color of the X mark. Any valid CSS color.', 'aqm-popup' ) ) );
        add_settings_field( 'close_border_radius_px', __( 'Border radius (px)',        'aqm-popup' ), array( $this, 'field_number' ), self::PAGE_SLUG, 'aqm_popup_close_icon', array( 'key' => 'close_border_radius_px','min' => 0, 'max' => 100, 'step' => 1, 'description' => __( 'Roundness of the background. Set to half the button size for a circle (e.g., 18 for a 36px button), 0 for a square.', 'aqm-popup' ) ) );

        add_settings_section(
            'aqm_popup_test_mode',
            __( 'Test mode', 'aqm-popup' ),
            array( $this, 'section_test_mode_text' ),
            self::PAGE_SLUG
        );

        add_settings_field( 'test_mode_enabled', __( 'Enable test mode', 'aqm-popup' ), array( $this, 'field_checkbox' ),       self::PAGE_SLUG, 'aqm_popup_test_mode', array( 'key' => 'test_mode_enabled', 'description' => __( 'While test mode is on, frequency is fully ignored (no cooldown, no session cap) and time-delay re-arms after dismissal — open the popup as many times as you need for debugging.', 'aqm-popup' ) ) );
        add_settings_field( 'test_mode_page_id', __( 'Test page',        'aqm-popup' ), array( $this, 'field_test_mode_page' ), self::PAGE_SLUG, 'aqm_popup_test_mode' );
    }

    public function section_content_text() {
        echo '<p>' . esc_html__( 'Build the popup right here — no page builder needed. Add an image, a headline, a short paragraph, and an optional button. Any field you leave empty is simply skipped.', 'aqm-popup' ) . '</p>';
    }

    public function section_style_text() {
        echo '<p>' . esc_html__( 'Colors and sizing for the popup body. Watch the live preview update as you change these.', 'aqm-popup' ) . '</p>';
    }

    public function section_triggers_text() {
        echo '<p>' . esc_html__( 'Enable any combination of triggers. The popup appears as soon as the first enabled trigger fires.', 'aqm-popup' ) . '</p>';
    }

    public function section_frequency_text() {
        echo '<p>' . esc_html__( 'Control how often the popup shows. Per-session count resets when the browser tab closes; cooldown persists across sessions.', 'aqm-popup' ) . '</p>';
    }

    public function section_close_icon_text() {
        echo '<p>' . esc_html__( 'Style the X button that closes the popup. The button is positioned at the top-right of the popup; these settings control its appearance.', 'aqm-popup' ) . '</p>';
    }

    public function section_test_mode_text() {
        echo '<p>' . esc_html__( 'Preview the popup on a single page without affecting the live site. While test mode is on:', 'aqm-popup' ) . '</p>';
        echo '<ul style="list-style:disc;margin-left:20px;">';
        echo '<li>' . esc_html__( 'The popup shows ONLY on the selected page below. All other pages are suppressed.', 'aqm-popup' ) . '</li>';
        echo '<li>' . esc_html__( 'Frequency is fully ignored — no cooldown after dismissal, no per-session limit. The popup can be opened as many times as needed for debugging.', 'aqm-popup' ) . '</li>';
        echo '<li>' . esc_html__( 'After dismissal, all triggers re-arm immediately (including the time-delay trigger), so reopening on the same page load is one trigger away.', 'aqm-popup' ) . '</li>';
        echo '</ul>';
    }

    public function field_checkbox( $args ) {
        $settings = aqm_popup_get_settings();
        $key      = $args['key'];
        $checked  = ! empty( $settings[ $key ] );
        printf(
            '<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            checked( $checked, true, false ),
            isset( $args['label'] ) ? esc_html( $args['label'] ) : ''
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_text( $args ) {
        $settings    = aqm_popup_get_settings();
        $key         = $args['key'];
        $value       = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
        $type        = ( isset( $args['input_type'] ) && 'url' === $args['input_type'] ) ? 'url' : 'text';
        printf(
            '<input type="%5$s" name="%1$s[%2$s]" value="%3$s" placeholder="%4$s" class="regular-text" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_attr( $value ),
            esc_attr( $placeholder ),
            esc_attr( $type )
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . wp_kses_post( $args['description'] ) . '</p>';
        }
    }

    public function field_textarea( $args ) {
        $settings    = aqm_popup_get_settings();
        $key         = $args['key'];
        $value       = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
        $placeholder = isset( $args['placeholder'] ) ? $args['placeholder'] : '';
        printf(
            '<textarea name="%1$s[%2$s]" rows="3" class="large-text" placeholder="%4$s">%3$s</textarea>',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_textarea( $value ),
            esc_attr( $placeholder )
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_color( $args ) {
        $settings = aqm_popup_get_settings();
        $key      = $args['key'];
        $value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
        if ( '' === $value || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ) {
            $defaults = aqm_popup_default_settings();
            $value    = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '#000000';
        }
        printf(
            '<input type="color" name="%1$s[%2$s]" value="%3$s" class="aqm-color-input" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_attr( $value )
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_select( $args ) {
        $settings = aqm_popup_get_settings();
        $key      = $args['key'];
        $current  = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
        $options  = isset( $args['options'] ) && is_array( $args['options'] ) ? $args['options'] : array();

        echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[' . esc_attr( $key ) . ']">';
        foreach ( $options as $val => $label ) {
            printf(
                '<option value="%1$s" %2$s>%3$s</option>',
                esc_attr( $val ),
                selected( $current, $val, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_image( $args ) {
        $settings = aqm_popup_get_settings();
        $key      = isset( $args['key'] ) ? $args['key'] : 'content_image_id';
        $id       = (int) ( isset( $settings[ $key ] ) ? $settings[ $key ] : 0 );
        $url      = $id ? wp_get_attachment_image_url( $id, 'medium' ) : '';
        $desc     = isset( $args['description'] ) ? $args['description'] : __( 'Optional. Sits flush at the top of the popup. Leave empty for a text-only popup.', 'aqm-popup' );
        ?>
        <div class="aqm-image-field" data-aqm-image-field data-aqm-image-key="<?php echo esc_attr( $key ); ?>">
            <input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $id ); ?>" data-aqm-image-input />
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

    public function field_number( $args ) {
        $settings = aqm_popup_get_settings();
        $key      = $args['key'];
        $value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
        $min      = isset( $args['min'] )  ? ' min="' . esc_attr( $args['min'] ) . '"'   : '';
        $max      = isset( $args['max'] )  ? ' max="' . esc_attr( $args['max'] ) . '"'   : '';
        $step     = isset( $args['step'] ) ? ' step="' . esc_attr( $args['step'] ) . '"' : '';
        printf(
            '<input type="number" name="%1$s[%2$s]" value="%3$s"%4$s%5$s%6$s class="small-text" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $key ),
            esc_attr( $value ),
            $min,
            $max,
            $step
        );
        if ( ! empty( $args['description'] ) ) {
            echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
        }
    }

    public function field_trigger_delay() {
        $settings = aqm_popup_get_settings();
        ?>
        <label class="aqm-popup-trigger-toggle">
            <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[trigger_delay_enabled]" value="1" <?php checked( ! empty( $settings['trigger_delay_enabled'] ) ); ?> data-aqm-trigger="delay" />
            <?php esc_html_e( 'Show after a delay', 'aqm-popup' ); ?>
        </label>
        <div class="aqm-popup-trigger-sub" data-aqm-trigger-sub="delay">
            <label>
                <?php esc_html_e( 'Delay (seconds):', 'aqm-popup' ); ?>
                <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[trigger_delay_seconds]" value="<?php echo esc_attr( $settings['trigger_delay_seconds'] ); ?>" min="0" step="1" class="small-text" />
            </label>
        </div>
        <?php
    }

    public function field_trigger_scroll() {
        $settings = aqm_popup_get_settings();
        ?>
        <label class="aqm-popup-trigger-toggle">
            <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[trigger_scroll_enabled]" value="1" <?php checked( ! empty( $settings['trigger_scroll_enabled'] ) ); ?> data-aqm-trigger="scroll" />
            <?php esc_html_e( 'Show after scrolling', 'aqm-popup' ); ?>
        </label>
        <div class="aqm-popup-trigger-sub" data-aqm-trigger-sub="scroll">
            <label>
                <?php esc_html_e( 'Scroll depth (%):', 'aqm-popup' ); ?>
                <input type="number" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[trigger_scroll_percent]" value="<?php echo esc_attr( $settings['trigger_scroll_percent'] ); ?>" min="1" max="100" step="1" class="small-text" />
            </label>
        </div>
        <?php
    }

    public function field_trigger_exit() {
        $settings = aqm_popup_get_settings();
        ?>
        <label class="aqm-popup-trigger-toggle">
            <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[trigger_exit_enabled]" value="1" <?php checked( ! empty( $settings['trigger_exit_enabled'] ) ); ?> data-aqm-trigger="exit" />
            <?php esc_html_e( 'Show when the visitor moves to leave (desktop only)', 'aqm-popup' ); ?>
        </label>
        <p class="description"><?php esc_html_e( 'Exit-intent detects the cursor moving toward the top of the viewport. Mobile/touch devices have no equivalent and will skip this trigger automatically.', 'aqm-popup' ); ?></p>
        <?php
    }

    public function field_test_mode_page() {
        $settings = aqm_popup_get_settings();
        $current  = (int) $settings['test_mode_page_id'];
        $pages    = get_posts( array(
            'post_type'      => 'page',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
        ) );

        echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[test_mode_page_id]">';
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

        echo '<p class="description">' . esc_html__( 'Drafts are included so you can test in private — only logged-in users with edit access can view a draft page.', 'aqm-popup' ) . '</p>';
    }

    public function field_trigger_click() {
        $settings = aqm_popup_get_settings();
        ?>
        <label class="aqm-popup-trigger-toggle">
            <input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[trigger_click_enabled]" value="1" <?php checked( ! empty( $settings['trigger_click_enabled'] ) ); ?> data-aqm-trigger="click" />
            <?php esc_html_e( 'Show when a specific element is clicked', 'aqm-popup' ); ?>
        </label>
        <div class="aqm-popup-trigger-sub" data-aqm-trigger-sub="click">
            <label>
                <?php esc_html_e( 'CSS selector:', 'aqm-popup' ); ?>
                <input type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[trigger_click_selector]" value="<?php echo esc_attr( $settings['trigger_click_selector'] ); ?>" class="regular-text" placeholder=".open-popup, #cta-button" />
            </label>
            <p class="description"><?php esc_html_e( 'Any standard CSS selector. Clicks on matching elements (including nested children) open the popup. If the element is a link, the default navigation is prevented.', 'aqm-popup' ); ?></p>
            <p class="description"><?php echo wp_kses_post( __( '<strong>Not firing?</strong> While test mode is on, AQM Popup logs every click event to your browser DevTools console (F12 → Console). Click your trigger element — if the log shows <code>matched selector? false</code>, the selector doesn\'t match the button you clicked. Verify it by running <code>document.querySelectorAll(\'your-selector\')</code> in the console — it should return at least one element.', 'aqm-popup' ) ); ?></p>
        </div>
        <?php
    }

    public function sanitize( $input ) {
        $defaults = aqm_popup_default_settings();
        $out      = $defaults;

        if ( ! is_array( $input ) ) {
            return $out;
        }

        $out['enabled']                = ! empty( $input['enabled'] );

        // Content.
        $out['content_image_id']       = isset( $input['content_image_id'] ) ? max( 0, (int) $input['content_image_id'] ) : 0;
        $out['content_heading']        = isset( $input['content_heading'] ) ? sanitize_text_field( $input['content_heading'] ) : '';
        $out['content_body']           = isset( $input['content_body'] ) ? sanitize_textarea_field( $input['content_body'] ) : '';
        $out['content_button_label']   = isset( $input['content_button_label'] ) ? sanitize_text_field( $input['content_button_label'] ) : '';
        $out['content_button_url']     = isset( $input['content_button_url'] ) ? esc_url_raw( trim( (string) $input['content_button_url'] ) ) : '';
        $out['content_button_new_tab'] = ! empty( $input['content_button_new_tab'] );

        // Style — colors validated as #rrggbb (fall back to default), sizes clamped.
        $out['style_bg_color']          = $this->sanitize_hex( isset( $input['style_bg_color'] ) ? $input['style_bg_color'] : '', $defaults['style_bg_color'] );
        $out['style_bg_image_id']       = isset( $input['style_bg_image_id'] ) ? max( 0, (int) $input['style_bg_image_id'] ) : 0;
        $out['style_text_color']        = $this->sanitize_hex( isset( $input['style_text_color'] ) ? $input['style_text_color'] : '', $defaults['style_text_color'] );
        $out['style_button_bg']         = $this->sanitize_hex( isset( $input['style_button_bg'] ) ? $input['style_button_bg'] : '', $defaults['style_button_bg'] );
        $out['style_button_text_color'] = $this->sanitize_hex( isset( $input['style_button_text_color'] ) ? $input['style_button_text_color'] : '', $defaults['style_button_text_color'] );
        $out['style_max_width']         = isset( $input['style_max_width'] ) ? min( 1200, max( 240, (int) $input['style_max_width'] ) ) : $defaults['style_max_width'];
        $out['style_padding']           = isset( $input['style_padding'] ) ? min( 96, max( 0, (int) $input['style_padding'] ) ) : $defaults['style_padding'];
        $out['style_align']             = ( isset( $input['style_align'] ) && 'left' === $input['style_align'] ) ? 'left' : 'center';

        $out['trigger_delay_enabled']  = ! empty( $input['trigger_delay_enabled'] );
        $out['trigger_delay_seconds']  = isset( $input['trigger_delay_seconds'] ) ? max( 0, (int) $input['trigger_delay_seconds'] ) : $defaults['trigger_delay_seconds'];

        $out['trigger_scroll_enabled'] = ! empty( $input['trigger_scroll_enabled'] );
        $out['trigger_scroll_percent'] = isset( $input['trigger_scroll_percent'] ) ? min( 100, max( 1, (int) $input['trigger_scroll_percent'] ) ) : $defaults['trigger_scroll_percent'];

        $out['trigger_exit_enabled']   = ! empty( $input['trigger_exit_enabled'] );

        $out['trigger_click_enabled']  = ! empty( $input['trigger_click_enabled'] );
        $out['trigger_click_selector'] = isset( $input['trigger_click_selector'] ) ? sanitize_text_field( $input['trigger_click_selector'] ) : '';

        $out['max_per_session']        = isset( $input['max_per_session'] ) ? max( 1, (int) $input['max_per_session'] ) : $defaults['max_per_session'];
        $out['cooldown_days']          = isset( $input['cooldown_days'] ) ? max( 0, (float) $input['cooldown_days'] ) : $defaults['cooldown_days'];

        $out['close_on_overlay_click'] = ! empty( $input['close_on_overlay_click'] );
        $out['close_on_esc']           = ! empty( $input['close_on_esc'] );

        $out['overlay_opacity']        = isset( $input['overlay_opacity'] ) ? min( 1, max( 0, (float) $input['overlay_opacity'] ) ) : $defaults['overlay_opacity'];

        $out['overlay_padding_vertical']   = isset( $input['overlay_padding_vertical'] )   ? max( 0, (int) $input['overlay_padding_vertical'] )   : 0;
        $out['overlay_padding_horizontal'] = isset( $input['overlay_padding_horizontal'] ) ? max( 0, (int) $input['overlay_padding_horizontal'] ) : 0;

        $out['popup_border']           = $this->sanitize_css_value( isset( $input['popup_border'] ) ? $input['popup_border'] : '' );
        $out['popup_border_radius_px'] = isset( $input['popup_border_radius_px'] ) ? min( 200, max( 0, (int) $input['popup_border_radius_px'] ) ) : 0;

        $out['close_size_px']          = isset( $input['close_size_px'] )          ? min( 200, max( 16, (int) $input['close_size_px'] ) )        : $defaults['close_size_px'];
        $out['close_offset_px']        = isset( $input['close_offset_px'] )        ? min( 100, max( -100, (int) $input['close_offset_px'] ) )    : $defaults['close_offset_px'];
        $out['close_background']       = $this->sanitize_css_value( isset( $input['close_background'] ) ? $input['close_background'] : $defaults['close_background'] );
        $out['close_icon_color']       = $this->sanitize_css_value( isset( $input['close_icon_color'] ) ? $input['close_icon_color'] : $defaults['close_icon_color'] );
        $out['close_border_radius_px'] = isset( $input['close_border_radius_px'] ) ? min( 100, max( 0, (int) $input['close_border_radius_px'] ) ) : $defaults['close_border_radius_px'];
        // Empty strings after sanitize → fall back to defaults so the popup never breaks.
        if ( '' === $out['close_background'] )  $out['close_background']  = $defaults['close_background'];
        if ( '' === $out['close_icon_color'] )  $out['close_icon_color']  = $defaults['close_icon_color'];

        $out['test_mode_enabled']      = ! empty( $input['test_mode_enabled'] );
        $out['test_mode_page_id']      = isset( $input['test_mode_page_id'] ) ? max( 0, (int) $input['test_mode_page_id'] ) : 0;

        return $out;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings    = aqm_popup_get_settings();
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
                                <p class="aqm-hero__sub"><?php esc_html_e( 'Build a site-wide popup — image, headline, text, button. Set the triggers, how often it shows, and how it looks.', 'aqm-popup' ); ?></p>
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

                    <nav class="aqm-nav" data-aqm-nav aria-label="<?php esc_attr_e( 'Settings sections', 'aqm-popup' ); ?>">
                        <span class="aqm-nav__indicator" data-aqm-nav-indicator aria-hidden="true"></span>
                        <ul class="aqm-nav__list" data-aqm-nav-list></ul>
                    </nav>

                    <div class="aqm-main" data-aqm-sections data-aqm-reveal>
                        <?php do_settings_sections( self::PAGE_SLUG ); ?>

                        <div class="aqm-actions">
                            <?php submit_button( __( 'Save changes', 'aqm-popup' ), 'primary aqm-save', 'submit', false ); ?>
                            <span class="aqm-actions__hint"><?php esc_html_e( 'Changes apply the next time a visitor loads the site.', 'aqm-popup' ); ?></span>
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
                                                <p class="aqm-preview__text" data-aqm-preview-text></p>
                                                <span class="aqm-preview__btn" data-aqm-preview-btn hidden></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <p class="aqm-preview__note"><?php esc_html_e( 'Shows your content and styling as visitors will see it. The dark backdrop and close button are added automatically.', 'aqm-popup' ); ?></p>
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

        // Clear the plugin's own GitHub-data transient (6-hour cache used by AQM_Popup_Updater).
        delete_transient( 'aqm_popup_github_data_' . md5( AQM_POPUP_GH_USER . AQM_POPUP_GH_REPO ) );

        // Clear WordPress's own plugin-update transient and plugin cache so it re-polls.
        delete_site_transient( 'update_plugins' );
        wp_clean_plugins_cache( true );

        if ( ! function_exists( 'wp_update_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }
        wp_update_plugins();

        $transient        = get_site_transient( 'update_plugins' );
        $current_version  = AQM_POPUP_VERSION;
        $new_version      = null;

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
     * emits on render.
     *
     * Two layers:
     * 1. Strip characters that could break out of the `<style>` context or the
     *    surrounding rule: < > ; { } " ' and backslash.
     * 2. Allowlist CSS functions. The only functions these fields legitimately
     *    need are the color functions, so any '(' that isn't part of an
     *    rgb()/rgba()/hsl()/hsla() call is rejected. This blocks url(),
     *    image(), (legacy) expression(), and similar — i.e. anything that could
     *    load an external resource — including nested/obfuscated attempts.
     *
     * Returns trimmed result, or '' if the value is rejected. Empty is OK; the
     * caller falls back to the default (colors) or to "no border" — so the
     * popup never renders broken CSS.
     */
    private function sanitize_css_value( $input ) {
        $input = sanitize_text_field( (string) $input );
        $input = preg_replace( '/[<>;{}"\'\\\\]/', '', $input );
        $input = trim( $input );

        if ( '' === $input ) {
            return '';
        }

        // Function allowlist: remove valid color-function calls, then reject the
        // whole value if any parenthesis remains (a disallowed function, e.g.
        // url(...), or a malformed/nested call).
        if ( false !== strpos( $input, '(' ) || false !== strpos( $input, ')' ) ) {
            $stripped = preg_replace( '/\b(?:rgba?|hsla?)\s*\([^()]*\)/i', '', $input );
            if ( false !== strpos( $stripped, '(' ) || false !== strpos( $stripped, ')' ) ) {
                return '';
            }
        }

        return $input;
    }

    /**
     * Validate a hex color (#rrggbb). Returns the lowercased hex, or the given
     * fallback if the input isn't a valid 6-digit hex. The color inputs already
     * emit #rrggbb, so this mainly guards against tampered/empty POST data.
     */
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
