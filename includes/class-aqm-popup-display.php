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

        $max_width = max( 200, (int) $settings['max_width_px'] );
        $opacity   = min( 1, max( 0, (float) $settings['overlay_opacity'] ) );

        $style_overlay   = sprintf( 'background-color: rgba(0,0,0,%s);', esc_attr( (string) $opacity ) );
        $style_container = sprintf( 'max-width: %dpx;', $max_width );
        $test_mode       = $this->is_test_mode_active();
        ?>
        <div id="aqm-popup-overlay" class="aqm-popup-overlay<?php echo $test_mode ? ' is-test-mode' : ''; ?>" hidden role="dialog" aria-modal="true" aria-labelledby="aqm-popup-content" style="<?php echo esc_attr( $style_overlay ); ?>">
            <div id="aqm-popup-container" class="aqm-popup-container" style="<?php echo esc_attr( $style_container ); ?>">
                <?php if ( $test_mode ) : ?>
                    <div class="aqm-popup-test-badge" aria-hidden="true"><?php esc_html_e( 'Test mode', 'aqm-popup' ); ?></div>
                <?php endif; ?>
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
