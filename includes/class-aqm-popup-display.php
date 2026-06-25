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

        if ( ! $this->has_content( $settings ) ) {
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

    private function has_content( $settings ) {
        return (int) $settings['content_image_id'] > 0
            || (int) $settings['style_bg_image_id'] > 0
            || '' !== trim( (string) $settings['content_heading'] )
            || '' !== trim( (string) $settings['content_body'] )
            || ( '' !== trim( (string) $settings['content_button_label'] ) && '' !== trim( (string) $settings['content_button_url'] ) );
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
        $settings = aqm_popup_get_settings();

        $content = $this->build_content( $settings );
        if ( '' === $content ) {
            return;
        }

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

        // User-customizable inline styles: emit one <style> block scoped to
        // the popup elements. Selectors use IDs (1,0,0) so values reliably
        // beat the class-based defaults in popup.css (0,1,0).
        $close_size    = max( 16, min( 200, (int) $settings['close_size_px'] ) );
        $close_offset  = max( -100, min( 100, (int) $settings['close_offset_px'] ) );
        $close_bg      = (string) $settings['close_background'];
        $close_color   = (string) $settings['close_icon_color'];
        $close_radius  = max( 0, min( 100, (int) $settings['close_border_radius_px'] ) );
        $close_icon_sz = (int) round( $close_size * 0.5 );
        $popup_border  = (string) $settings['popup_border'];
        $popup_radius  = max( 0, min( 200, (int) $settings['popup_border_radius_px'] ) );

        // Body styling (validated hex colors + clamped ints + enum align).
        $body_bg     = $this->safe_hex( $settings['style_bg_color'], '#ffffff' );
        $body_text   = $this->safe_hex( $settings['style_text_color'], '#1d2327' );
        $btn_bg      = $this->safe_hex( $settings['style_button_bg'], '#c10f30' );
        $btn_text    = $this->safe_hex( $settings['style_button_text_color'], '#ffffff' );
        $max_width   = max( 240, min( 1200, (int) $settings['style_max_width'] ) );
        $padding     = max( 0, min( 96, (int) $settings['style_padding'] ) );
        $align       = ( 'left' === $settings['style_align'] ) ? 'left' : 'center';

        // Optional popup background image (cover). cover/position/repeat live in
        // popup.css on .aqm-popup-built--bg; only the URL is injected here.
        $bg_image_id  = (int) $settings['style_bg_image_id'];
        $bg_image_url = $bg_image_id > 0 ? wp_get_attachment_image_url( $bg_image_id, 'large' ) : '';

        // Optional tint laid over the background image (behind the content) for
        // text legibility. Implemented as a flat gradient layered above the image
        // in the same background-image property — no extra element needed.
        $ov_color   = $this->safe_hex( $settings['style_bg_overlay_color'], '#000000' );
        $ov_opacity = min( 1, max( 0, (float) $settings['style_bg_overlay_opacity'] ) );

        $inline_rules   = array();
        $inline_rules[] = sprintf(
            '#aqm-popup-close{width:%1$dpx;height:%1$dpx;top:%2$dpx;right:%2$dpx;background:%3$s;color:%4$s;border-radius:%5$dpx}',
            $close_size,
            $close_offset,
            $close_bg,
            $close_color,
            $close_radius
        );
        $inline_rules[] = sprintf(
            '#aqm-popup-close .aqm-popup-close-icon{width:%1$dpx;height:%1$dpx}',
            $close_icon_sz
        );

        // Container: width only. It stays overflow:visible (popup.css) so the
        // close button can be positioned OUTSIDE the popup with a negative
        // offset without being clipped.
        $inline_rules[] = sprintf( '#aqm-popup-container{width:min(%dpx,94vw)}', $max_width );

        // Popup body styling. Border + radius go on the card itself, not the
        // container, so the container never clips an outside close button. The
        // card already has overflow-y:auto, which clips its image to the radius.
        $built_decls = sprintf( 'background-color:%1$s;color:%2$s;text-align:%3$s', $body_bg, $body_text, $align );
        if ( '' !== $bg_image_url ) {
            $img_layer = sprintf( 'url(%s)', esc_url( $bg_image_url ) );
            if ( $ov_opacity > 0 ) {
                $rgb   = $this->hex_to_rgb( $ov_color );
                $a     = rtrim( rtrim( number_format( $ov_opacity, 2, '.', '' ), '0' ), '.' );
                $scrim = sprintf( 'linear-gradient(rgba(%1$s,%2$s),rgba(%1$s,%2$s))', $rgb, $a );
                $built_decls .= sprintf( ';background-image:%s,%s', $scrim, $img_layer );
            } else {
                $built_decls .= ';background-image:' . $img_layer;
            }
        }
        if ( '' !== $popup_border ) {
            $built_decls .= sprintf( ';border:%s', $popup_border );
        }
        if ( $popup_radius > 0 ) {
            $built_decls .= sprintf( ';border-radius:%dpx', $popup_radius );
        }
        $inline_rules[] = sprintf( '#aqm-popup-content .aqm-popup-built{%s}', $built_decls );
        $inline_rules[] = sprintf(
            '#aqm-popup-content .aqm-popup-built__body{padding:%dpx}',
            $padding
        );
        $inline_rules[] = sprintf(
            '#aqm-popup-content .aqm-popup-built__btn{background:%1$s;color:%2$s}',
            $btn_bg,
            $btn_text
        );

        echo '<style id="aqm-popup-inline-style">' . implode( '', $inline_rules ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput -- values are validated hex colors + clamped ints + enum align in the admin sanitize callback and re-validated here.
        ?>
        <div id="aqm-popup-overlay" class="<?php echo esc_attr( implode( ' ', $overlay_classes ) ); ?>" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Popup', 'aqm-popup' ); ?>" style="<?php echo esc_attr( $style_overlay ); ?>">
            <div id="aqm-popup-container" class="aqm-popup-container">
                <button type="button" id="aqm-popup-close" class="aqm-popup-close" aria-label="<?php esc_attr_e( 'Close', 'aqm-popup' ); ?>">
                    <svg class="aqm-popup-close-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                        <path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M6 6 L18 18 M18 6 L6 18"/>
                    </svg>
                </button>
                <div id="aqm-popup-content" class="aqm-popup-content">
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- $content is assembled in build_content() entirely from escaped pieces (esc_html / esc_url / wp_get_attachment_image). ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Assemble the popup body HTML from the saved content fields. Every dynamic
     * value is escaped at the point of output, so the returned string is safe to
     * echo directly.
     */
    private function build_content( $settings ) {
        $image_id    = (int) $settings['content_image_id'];
        $bg_image_id = (int) $settings['style_bg_image_id'];
        $heading     = trim( (string) $settings['content_heading'] );
        $body        = trim( (string) $settings['content_body'] );
        $label       = trim( (string) $settings['content_button_label'] );
        $url         = trim( (string) $settings['content_button_url'] );
        $new_tab     = ! empty( $settings['content_button_new_tab'] );

        $parts = '';

        if ( $image_id > 0 ) {
            $img = wp_get_attachment_image( $image_id, 'large', false, array(
                'class' => 'aqm-popup-built__img',
                'alt'   => '',
            ) );
            if ( $img ) {
                $parts .= $img;
            }
        }

        $inner = '';
        if ( '' !== $heading ) {
            $inner .= '<h2 class="aqm-popup-built__heading">' . esc_html( $heading ) . '</h2>';
        }
        if ( '' !== $body ) {
            $inner .= '<p class="aqm-popup-built__text">' . nl2br( esc_html( $body ) ) . '</p>';
        }
        if ( '' !== $label && '' !== $url ) {
            $target = $new_tab ? ' target="_blank" rel="noopener noreferrer"' : '';
            $inner .= '<a class="aqm-popup-built__btn" href="' . esc_url( $url ) . '"' . $target . '>' . esc_html( $label ) . '</a>';
        }
        if ( '' !== $inner ) {
            $parts .= '<div class="aqm-popup-built__body">' . $inner . '</div>';
        }

        // A background image alone is enough to render (it gets a min-height in
        // CSS so it stays visible even with no text on top).
        if ( '' === $parts && $bg_image_id <= 0 ) {
            return '';
        }

        $classes = 'aqm-popup-built';
        if ( $bg_image_id > 0 ) {
            $classes .= ' aqm-popup-built--bg';
        }
        return '<div class="' . esc_attr( $classes ) . '">' . $parts . '</div>';
    }

    /**
     * Re-validate a stored hex color at render time. Returns #rrggbb or the
     * fallback. Defensive: settings are already sanitized on save.
     */
    private function hex_to_rgb( $hex ) {
        $hex = ltrim( (string) $hex, '#' );
        if ( 6 !== strlen( $hex ) ) {
            return '0,0,0';
        }
        return hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) );
    }

    private function safe_hex( $value, $fallback ) {
        $value = is_string( $value ) ? trim( $value ) : '';
        return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : $fallback;
    }
}
