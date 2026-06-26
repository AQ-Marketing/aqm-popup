<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AQM_Popup_Display {
    private static $instance = null;
    private $design          = null;
    private $resolved        = false;
    private $is_test         = false;

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

    /**
     * Resolve which design (if any) should display in the current request:
     * the manually-active design, gated by test mode OR (master-enable + its
     * date window). Returns the design array, or null.
     */
    private function resolve_design() {
        if ( $this->resolved ) {
            return $this->design;
        }
        $this->resolved = true;

        if ( is_admin() ) {
            return null;
        }

        $s         = aqm_popup_get_settings();
        $active_id = $s['active'];
        if ( '' === $active_id || ! isset( $s['designs'][ $active_id ] ) ) {
            return null;
        }
        $design = $s['designs'][ $active_id ];

        // Test mode: preview the active design on the chosen page only.
        if ( ! empty( $s['test_mode_enabled'] ) && (int) $s['test_mode_page_id'] > 0 ) {
            if ( is_page( (int) $s['test_mode_page_id'] ) ) {
                $this->design  = $design;
                $this->is_test = true;
            }
            return $this->design;
        }

        // Normal: master enable + within the design's date window.
        if ( empty( $s['enabled'] ) ) {
            return null;
        }
        if ( ! $this->within_schedule( $design ) ) {
            return null;
        }

        $this->design = $design;
        return $this->design;
    }

    private function within_schedule( $design ) {
        $start = ! empty( $design['start_date'] ) ? $design['start_date'] : '';
        $end   = ! empty( $design['end_date'] ) ? $design['end_date'] : '';
        if ( '' === $start && '' === $end ) {
            return true;
        }
        // Site-timezone "today" as Y-m-d; lexical compare works for this format.
        $today = current_time( 'Y-m-d' );
        if ( '' !== $start && $today < $start ) {
            return false;
        }
        if ( '' !== $end && $today > $end ) {
            return false;
        }
        return true;
    }

    private function should_run() {
        $design = $this->resolve_design();
        if ( ! $design ) {
            return false;
        }
        if ( ! $this->has_content( $design ) ) {
            return false;
        }
        if ( ! $this->has_any_trigger_enabled( $design ) ) {
            return false;
        }
        return true;
    }

    private function has_content( $design ) {
        return (int) $design['content_image_id'] > 0
            || (int) $design['style_bg_image_id'] > 0
            || '' !== trim( (string) $design['content_heading'] )
            || '' !== trim( (string) $design['content_body'] )
            || ( '' !== trim( (string) $design['content_button_label'] ) && '' !== trim( (string) $design['content_button_url'] ) );
    }

    private function has_any_trigger_enabled( $design ) {
        return ! empty( $design['trigger_delay_enabled'] )
            || ! empty( $design['trigger_scroll_enabled'] )
            || ! empty( $design['trigger_exit_enabled'] )
            || ( ! empty( $design['trigger_click_enabled'] ) && '' !== trim( (string) $design['trigger_click_selector'] ) );
    }

    public function maybe_enqueue() {
        if ( ! $this->should_run() ) {
            return;
        }
        $design = $this->resolve_design();

        wp_enqueue_style( 'aqm-popup', AQM_POPUP_URL . 'assets/css/popup.css', array(), AQM_POPUP_VERSION );

        // Google Font(s) for this design (base + optional headline override).
        $font_url = aqm_popup_google_font_url( isset( $design['style_font_family'] ) ? $design['style_font_family'] : '' );
        if ( $font_url ) {
            wp_enqueue_style( 'aqm-popup-font', $font_url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL is versionless.
        }
        $h_font_url = aqm_popup_google_font_url( isset( $design['style_heading_font_family'] ) ? $design['style_heading_font_family'] : '' );
        if ( $h_font_url && $h_font_url !== $font_url ) {
            wp_enqueue_style( 'aqm-popup-font-heading', $h_font_url, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts URL is versionless.
        }

        wp_enqueue_script( 'aqm-popup', AQM_POPUP_URL . 'assets/js/popup.js', array(), AQM_POPUP_VERSION, true );

        wp_localize_script( 'aqm-popup', 'aqmPopupSettings', array(
            'triggers' => array(
                'delay'  => ! empty( $design['trigger_delay_enabled'] ) ? array( 'seconds' => (int) $design['trigger_delay_seconds'] ) : null,
                'scroll' => ! empty( $design['trigger_scroll_enabled'] ) ? array( 'percent' => (int) $design['trigger_scroll_percent'] ) : null,
                'exit'   => ! empty( $design['trigger_exit_enabled'] ) ? true : null,
                'click'  => ( ! empty( $design['trigger_click_enabled'] ) && '' !== trim( (string) $design['trigger_click_selector'] ) )
                    ? array( 'selector' => $design['trigger_click_selector'] )
                    : null,
            ),
            'frequency' => array(
                'maxPerSession' => max( 1, (int) $design['max_per_session'] ),
                'cooldownDays'  => max( 0, (float) $design['cooldown_days'] ),
            ),
            'behavior' => array(
                'closeOnOverlayClick' => ! empty( $design['close_on_overlay_click'] ),
                'closeOnEsc'          => ! empty( $design['close_on_esc'] ),
            ),
            'testMode' => $this->is_test,
        ) );
    }

    public function maybe_render() {
        if ( ! $this->should_run() ) {
            return;
        }
        $design = $this->resolve_design();

        $content = $this->build_content( $design );
        if ( '' === $content ) {
            return;
        }

        $opacity = min( 1, max( 0, (float) $design['overlay_opacity'] ) );
        $v       = max( 0, (int) $design['overlay_padding_vertical'] );
        $h       = max( 0, (int) $design['overlay_padding_horizontal'] );

        $overlay_styles   = array();
        $overlay_styles[] = sprintf( 'background-color: rgba(0,0,0,%s)', esc_attr( (string) $opacity ) );
        if ( $v > 0 || $h > 0 ) {
            $overlay_styles[] = sprintf( 'padding: %dpx %dpx', $v, $h );
        }
        $overlay_styles[] = sprintf( '--aqm-popup-max-h: calc(100vh - %dpx)', $v * 2 );
        $overlay_styles[] = sprintf( '--aqm-popup-max-w: calc(100vw - %dpx)', $h * 2 );
        $style_overlay    = implode( '; ', $overlay_styles ) . ';';

        // Close icon.
        $close_size    = max( 16, min( 200, (int) $design['close_size_px'] ) );
        $close_offset  = max( -100, min( 100, (int) $design['close_offset_px'] ) );
        $close_bg      = (string) $design['close_background'];
        $close_color   = (string) $design['close_icon_color'];
        $close_radius  = max( 0, min( 100, (int) $design['close_border_radius_px'] ) );
        $close_icon_sz = (int) round( $close_size * 0.5 );
        $popup_radius  = max( 0, min( 200, (int) $design['popup_border_radius_px'] ) );

        // Border: structured width/style/color, or the legacy/advanced CSS
        // override if set.
        $b_width  = max( 0, min( 40, (int) $design['style_border_width'] ) );
        $b_style  = $this->one_of( $design['style_border_style'], array( 'solid', 'dashed', 'dotted', 'double' ), 'solid' );
        $b_color  = $this->safe_hex( $design['style_border_color'], '#ffffff' );
        $legacy_border = (string) $design['popup_border'];
        if ( '' !== $legacy_border ) {
            $popup_border = $legacy_border;
        } elseif ( $b_width > 0 ) {
            $popup_border = $b_width . 'px ' . $b_style . ' ' . $b_color;
        } else {
            $popup_border = '';
        }

        // Body styling.
        $body_bg     = $this->safe_hex( $design['style_bg_color'], '#ffffff' );
        $body_text   = $this->safe_hex( $design['style_text_color'], '#1d2327' );
        $btn_bg      = $this->safe_hex( $design['style_button_bg'], '#c10f30' );
        $btn_text    = $this->safe_hex( $design['style_button_text_color'], '#ffffff' );
        $max_width   = max( 240, min( 1200, (int) $design['style_max_width'] ) );
        $min_height  = max( 0, min( 1200, (int) $design['style_min_height'] ) );
        $padding     = max( 0, min( 96, (int) $design['style_padding'] ) );
        $align       = $this->one_of( $design['style_align'], array( 'left', 'center', 'right' ), 'center' );
        $valign      = $this->one_of( $design['style_vertical_align'], array( 'top', 'center', 'bottom' ), 'top' );
        $font_stack  = aqm_popup_font_stack( isset( $design['style_font_family'] ) ? $design['style_font_family'] : '' );
        $h_size      = max( 10, min( 96, (int) $design['style_heading_size'] ) );
        $h_weight    = (int) $design['style_heading_weight'];
        $b_size      = max( 10, min( 48, (int) $design['style_body_size'] ) );
        $b_weight    = (int) $design['style_body_weight'];

        // Headline-specific typography.
        $h_font        = aqm_popup_font_stack( isset( $design['style_heading_font_family'] ) ? $design['style_heading_font_family'] : '' );
        $h_color       = $this->safe_hex( $design['style_heading_color'], '#1d2327' );
        $h_lh          = min( 3, max( 0.8, (float) $design['style_heading_line_height'] ) );
        $h_ls          = min( 20, max( -5, (float) $design['style_heading_letter_spacing'] ) );
        $h_transform   = $this->one_of( $design['style_heading_transform'], array( 'none', 'uppercase', 'lowercase', 'capitalize' ), 'none' );
        $h_italic      = ! empty( $design['style_heading_italic'] );
        $h_align       = $this->one_of( $design['style_heading_align'], array( 'inherit', 'left', 'center', 'right' ), 'inherit' );
        $h_mb          = max( 0, min( 80, (int) $design['style_heading_margin_bottom'] ) );

        // Background image + optional overlay tint.
        $bg_image_id  = (int) $design['style_bg_image_id'];
        $bg_image_url = $bg_image_id > 0 ? wp_get_attachment_image_url( $bg_image_id, 'large' ) : '';
        $ov_color     = $this->safe_hex( $design['style_bg_overlay_color'], '#000000' );
        $ov_opacity   = min( 1, max( 0, (float) $design['style_bg_overlay_opacity'] ) );

        $justify = ( 'center' === $valign ) ? 'center' : ( ( 'bottom' === $valign ) ? 'flex-end' : 'flex-start' );

        $inline_rules   = array();
        $inline_rules[] = sprintf(
            '#aqm-popup-close{width:%1$dpx;height:%1$dpx;top:%2$dpx;right:%2$dpx;background:%3$s;color:%4$s;border-radius:%5$dpx}',
            $close_size,
            $close_offset,
            $close_bg,
            $close_color,
            $close_radius
        );
        $inline_rules[] = sprintf( '#aqm-popup-close .aqm-popup-close-icon{width:%1$dpx;height:%1$dpx}', $close_icon_sz );

        // Container width only; stays overflow:visible so an outside close button isn't clipped.
        $inline_rules[] = sprintf( '#aqm-popup-container{width:min(%dpx,94vw)}', $max_width );

        // Card styling.
        $built_decls = sprintf( 'background-color:%1$s;color:%2$s;text-align:%3$s;justify-content:%4$s', $body_bg, $body_text, $align, $justify );
        if ( $min_height > 0 ) {
            $built_decls .= sprintf( ';min-height:%dpx', $min_height );
        }
        if ( '' !== $font_stack ) {
            $built_decls .= ';font-family:' . $font_stack;
        }
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
        $inline_rules[] = sprintf( '#aqm-popup-content .aqm-popup-built__body{padding:%dpx}', $padding );
        $lh_str        = rtrim( rtrim( number_format( $h_lh, 2, '.', '' ), '0' ), '.' );
        $ls_str        = rtrim( rtrim( number_format( $h_ls, 2, '.', '' ), '0' ), '.' );
        $heading_decls = sprintf( 'font-size:%1$dpx;font-weight:%2$d;line-height:%3$s;letter-spacing:%4$spx;margin-bottom:%5$dpx', $h_size, $h_weight, $lh_str, $ls_str, $h_mb );
        if ( '' !== $h_font ) {
            $heading_decls .= ';font-family:' . $h_font;
        }
        $heading_decls .= ';color:' . $h_color;
        if ( 'none' !== $h_transform ) {
            $heading_decls .= ';text-transform:' . $h_transform;
        }
        if ( $h_italic ) {
            $heading_decls .= ';font-style:italic';
        }
        if ( 'inherit' !== $h_align ) {
            $heading_decls .= ';text-align:' . $h_align;
        }
        $inline_rules[] = sprintf( '#aqm-popup-content .aqm-popup-built__heading{%s}', $heading_decls );
        $inline_rules[] = sprintf( '#aqm-popup-content .aqm-popup-built__text{font-size:%1$dpx;font-weight:%2$d}', $b_size, $b_weight );
        $inline_rules[] = sprintf( '#aqm-popup-content .aqm-popup-built__btn{background:%1$s;color:%2$s}', $btn_bg, $btn_text );

        echo '<style id="aqm-popup-inline-style">' . implode( '', $inline_rules ) . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput -- values are validated hex/ints/enums + esc_url'd image URL + registry font stacks.
        ?>
        <div id="aqm-popup-overlay" class="aqm-popup-overlay" hidden role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Popup', 'aqm-popup' ); ?>" style="<?php echo esc_attr( $style_overlay ); ?>">
            <div id="aqm-popup-container" class="aqm-popup-container">
                <button type="button" id="aqm-popup-close" class="aqm-popup-close" aria-label="<?php esc_attr_e( 'Close', 'aqm-popup' ); ?>">
                    <svg class="aqm-popup-close-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
                        <path fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" d="M6 6 L18 18 M18 6 L6 18"/>
                    </svg>
                </button>
                <div id="aqm-popup-content" class="aqm-popup-content">
                    <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput -- $content is assembled in build_content() entirely from escaped pieces. ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Assemble the popup body from a design's content fields. Every dynamic
     * value is escaped at output, so the result is safe to echo.
     */
    private function build_content( $design ) {
        $image_id    = (int) $design['content_image_id'];
        $bg_image_id = (int) $design['style_bg_image_id'];
        $heading     = trim( (string) $design['content_heading'] );
        $body        = trim( (string) $design['content_body'] );
        $label       = trim( (string) $design['content_button_label'] );
        $url         = trim( (string) $design['content_button_url'] );
        $new_tab     = ! empty( $design['content_button_new_tab'] );

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

        if ( '' === $parts && $bg_image_id <= 0 ) {
            return '';
        }

        $classes = 'aqm-popup-built';
        if ( $bg_image_id > 0 ) {
            $classes .= ' aqm-popup-built--bg';
        }
        return '<div class="' . esc_attr( $classes ) . '">' . $parts . '</div>';
    }

    private function one_of( $value, $allowed, $fallback ) {
        $value = is_string( $value ) ? $value : '';
        return in_array( $value, $allowed, true ) ? $value : $fallback;
    }

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
