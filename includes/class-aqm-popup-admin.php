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
    }

    public function register_menu() {
        $this->hook_suffix = add_options_page(
            __( 'AQM Popup', 'aqm-popup' ),
            __( 'AQM Popup', 'aqm-popup' ),
            'manage_options',
            self::PAGE_SLUG,
            array( $this, 'render_page' )
        );
    }

    public function plugin_action_links( $links ) {
        $url             = admin_url( 'options-general.php?page=' . self::PAGE_SLUG );
        $settings_link   = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'aqm-popup' ) . '</a>';
        array_unshift( $links, $settings_link );
        return $links;
    }

    public function enqueue_assets( $hook ) {
        if ( $hook !== $this->hook_suffix ) {
            return;
        }
        wp_enqueue_style(
            'aqm-popup-admin',
            AQM_POPUP_URL . 'assets/css/admin.css',
            array(),
            AQM_POPUP_VERSION
        );
        wp_enqueue_script(
            'aqm-popup-admin',
            AQM_POPUP_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            AQM_POPUP_VERSION,
            true
        );
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
            'aqm_popup_general',
            __( 'General', 'aqm-popup' ),
            array( $this, 'section_general_text' ),
            self::PAGE_SLUG
        );

        add_settings_field( 'enabled', __( 'Enable popup', 'aqm-popup' ), array( $this, 'field_checkbox' ), self::PAGE_SLUG, 'aqm_popup_general', array( 'key' => 'enabled' ) );
        add_settings_field( 'layout_id', __( 'Divi Library layout', 'aqm-popup' ), array( $this, 'field_layout' ), self::PAGE_SLUG, 'aqm_popup_general', array( 'key' => 'layout_id' ) );

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
        add_settings_field( 'max_width_px',           __( 'Popup max-width (px)',   'aqm-popup' ), array( $this, 'field_number' ),   self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'max_width_px', 'min' => 200, 'step' => 10 ) );
        add_settings_field( 'overlay_opacity',        __( 'Overlay opacity',        'aqm-popup' ), array( $this, 'field_number' ),   self::PAGE_SLUG, 'aqm_popup_behavior', array( 'key' => 'overlay_opacity', 'min' => 0, 'max' => 1, 'step' => '0.05', 'description' => __( 'Between 0 (transparent) and 1 (opaque black).', 'aqm-popup' ) ) );
    }

    public function section_general_text() {
        echo '<p>' . esc_html__( 'Pick the Divi Library layout that will be rendered inside the popup.', 'aqm-popup' ) . '</p>';
    }

    public function section_triggers_text() {
        echo '<p>' . esc_html__( 'Enable any combination of triggers. The popup appears as soon as the first enabled trigger fires.', 'aqm-popup' ) . '</p>';
    }

    public function section_frequency_text() {
        echo '<p>' . esc_html__( 'Control how often the popup shows. Per-session count resets when the browser tab closes; cooldown persists across sessions.', 'aqm-popup' ) . '</p>';
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

    public function field_layout() {
        $settings  = aqm_popup_get_settings();
        $current   = (int) $settings['layout_id'];
        $divi_active = $this->is_divi_active();
        $layouts   = $divi_active ? get_posts( array(
            'post_type'      => 'et_pb_layout',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'post_status'    => array( 'publish', 'private' ),
        ) ) : array();

        echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[layout_id]">';
        echo '<option value="0">' . esc_html__( '— Select a layout —', 'aqm-popup' ) . '</option>';
        foreach ( $layouts as $layout ) {
            printf(
                '<option value="%1$d" %2$s>%3$s</option>',
                (int) $layout->ID,
                selected( $current, (int) $layout->ID, false ),
                esc_html( $layout->post_title ? $layout->post_title : sprintf( '(no title — #%d)', $layout->ID ) )
            );
        }
        echo '</select>';

        if ( ! $divi_active ) {
            echo '<p class="description" style="color:#b32d2e;">' . esc_html__( 'Divi theme not detected. The popup will not render until Divi is active and at least one Library layout exists.', 'aqm-popup' ) . '</p>';
        } elseif ( empty( $layouts ) ) {
            $url = admin_url( 'edit.php?post_type=et_pb_layout' );
            echo '<p class="description">' . wp_kses_post( sprintf(
                /* translators: %s: link to Divi Library */
                __( 'No Divi Library layouts found. <a href="%s">Create one in the Divi Library</a> first.', 'aqm-popup' ),
                esc_url( $url )
            ) ) . '</p>';
        } else {
            echo '<p class="description">' . esc_html__( 'Build your popup content as a layout in Divi → Divi Library, then select it here.', 'aqm-popup' ) . '</p>';
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
        $out['layout_id']              = isset( $input['layout_id'] ) ? max( 0, (int) $input['layout_id'] ) : 0;

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

        $out['max_width_px']           = isset( $input['max_width_px'] ) ? max( 200, (int) $input['max_width_px'] ) : $defaults['max_width_px'];
        $out['overlay_opacity']        = isset( $input['overlay_opacity'] ) ? min( 1, max( 0, (float) $input['overlay_opacity'] ) ) : $defaults['overlay_opacity'];

        return $out;
    }

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap aqm-popup-settings">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p class="aqm-popup-intro"><?php esc_html_e( 'Render a Divi Library layout as a site-wide popup. Configure the trigger(s), how often visitors see it, and how it dismisses.', 'aqm-popup' ); ?></p>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'aqm_popup_settings_group' );
                do_settings_sections( self::PAGE_SLUG );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private function is_divi_active() {
        $theme  = wp_get_theme();
        $name   = strtolower( (string) $theme->get( 'Name' ) );
        $parent = $theme->parent();
        $parent_name = $parent ? strtolower( (string) $parent->get( 'Name' ) ) : '';
        return ( false !== strpos( $name, 'divi' ) ) || ( false !== strpos( $parent_name, 'divi' ) ) || post_type_exists( 'et_pb_layout' );
    }
}
