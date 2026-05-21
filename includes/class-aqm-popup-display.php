<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AQM_Popup_Display {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue' ) );
        add_action( 'wp_footer', array( $this, 'maybe_render' ), 99 );
    }

    private function should_run() {
        if ( is_admin() ) {
            return false;
        }
        $settings = aqm_popup_get_settings();

        if ( empty( $settings['layout_id'] ) ) {
            return false;
        }
        if ( ! $this->has_any_trigger_enabled( $settings ) ) {
            return false;
        }

        if ( ! empty( $settings['test_mode_enabled'] ) ) {
            $test_page = (int) $settings['test_mode_page_id'];
            if ( $test_page <= 0 ) {
                return false;
            }
            return is_page( $test_page );
        }

        if ( empty( $settings['enabled'] ) ) {
            return false;
        }
        return true;
    }

    private function is_test_mode_active() {
        $settings = aqm_popup_get_settings();
        return ! empty( $settings['test_mode_enabled'] ) && (int) $settings['test_mode_page_id'] > 0;
    }

    private function has_any_trigger_enabled( $settings ) {
        return ! empty( $settings['trigger_delay_enabled'] )
            || ! empty( $settings['trigger_scroll_enabled'] )
            || ! empty( $settings['trigger_exit_enabled'] )
            || ( ! empty( $settings['trigger_click_enabled'] ) && '' !== trim( (string) $settings['trigger_click_selector'] ) );
    }

    public function maybe_enqueue() {
        if ( ! $this->should_run() ) {
            return;
        }
        $settings = aqm_popup_get_settings();

        wp_enqueue_style(
            'aqm-popup',
            AQM_POPUP_URL . 'assets/css/popup.css',
            array(),
            AQM_POPUP_VERSION
        );
        wp_enqueue_script(
            'aqm-popup',
            AQM_POPUP_URL . 'assets/js/popup.js',
            array(),
            AQM_POPUP_VERSION,
            true
        );

        wp_localize_script( 'aqm-popup', 'aqmPopupSettings', array(
            'triggers' => array(
                'delay'   => ! empty( $settings['trigger_delay_enabled'] ) ? array( 'seconds' => (int) $settings['trigger_delay_seconds'] ) : null,
                'scroll'  => ! empty( $settings['trigger_scroll_enabled'] ) ? array( 'percent' => (int) $settings['trigger_scroll_percent'] ) : null,
                'exit'    => ! empty( $settings['trigger_exit_enabled'] ) ? true : null,
                'click'   => ( ! empty( $settings['trigger_click_enabled'] ) && '' !== trim( (string) $settings['trigger_click_selector'] ) )
                    ? array( 'selector' => $settings['trigger_click_selector'] )
                    : null,
            ),
            'frequency' => array(
                'maxPerSession' => max( 1, (int) $settings['max_per_session'] ),
                'cooldownDays'  => max( 0, (float) $settings['cooldown_days'] ),
            ),
            'behavior' => array(
                'closeOnOverlayClick' => ! empty( $settings['close_on_overlay_click'] ),
                'closeOnEsc'          => ! empty( $settings['close_on_esc'] ),
            ),
            'testMode' => $this->is_test_mode_active(),
        ) );
    }

    public function maybe_render() {
        if ( ! $this->should_run() ) {
            return;
        }
        $settings  = aqm_popup_get_settings();
        $layout_id = (int) $settings['layout_id'];
        $layout    = get_post( $layout_id );

        if ( ! $layout || 'et_pb_layout' !== $layout->post_type ) {
            aqm_popup_debug_log( 'Configured layout_id=' . $layout_id . ' is not a valid et_pb_layout.' );
            return;
        }

        $content = apply_filters( 'the_content', $layout->post_content );
        $content = str_replace( ']]>', ']]&gt;', $content );

        $opacity = min( 1, max( 0, (float) $settings['overlay_opacity'] ) );

        // User-configured overlay padding: insets the popup from viewport edges.
        // The dark backdrop fills the padded area (because the backdrop IS the
        // overlay's background-color). Combined with `box-sizing: border-box`
        // on the overlay (set in popup.css) so the overlay still spans the
        // full viewport while padding pushes the content area inward.
        $v = max( 0, (int) $settings['overlay_padding_vertical'] );
        $h = max( 0, (int) $settings['overlay_padding_horizontal'] );

        $overlay_styles   = array();
        $overlay_styles[] = sprintf( 'background-color: rgba(0,0,0,%s)', esc_attr( (string) $opacity ) );
        if ( $v > 0 || $h > 0 ) {
            $overlay_styles[] = sprintf( 'padding: %dpx %dpx', $v, $h );
        }
        // CSS custom properties: the available content size inside the overlay
        // (viewport minus padding). Used by .aqm-popup-container and image
        // scaling rules in popup.css to constrain media to the visible area
        // so images fit the viewport without clipping at the bottom.
        $overlay_styles[] = sprintf( '--aqm-popup-max-h: calc(100vh - %dpx)', $v * 2 );
        $overlay_styles[] = sprintf( '--aqm-popup-max-w: calc(100vw - %dpx)', $h * 2 );
        $style_overlay    = implode( '; ', $overlay_styles ) . ';';

        $overlay_classes = array( 'aqm-popup-overlay' );
        if ( ! empty( $settings['edge_to_edge_mode'] ) ) {
            $overlay_classes[] = 'aqm-popup-edge-to-edge';
        }
        ?>
        <div id="aqm-popup-overlay" class="<?php echo esc_attr( implode( ' ', $overlay_classes ) ); ?>" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Popup', 'aqm-popup' ); ?>" style="<?php echo esc_attr( $style_overlay ); ?>">
            <div id="aqm-popup-container" class="aqm-popup-container">
                <button type="button" id="aqm-popup-close" class="aqm-popup-close" aria-label="<?php esc_attr_e( 'Close', 'aqm-popup' ); ?>">
                    <svg class="aqm-popup-close-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                        <path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M6 6 L18 18 M18 6 L6 18"/>
                    </svg>
                </button>
                <div id="aqm-popup-content" class="aqm-popup-content">
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- Output is Divi shortcode HTML processed via apply_filters('the_content', ...). ?>
                </div>
            </div>
        </div>
        <?php
    }
}
