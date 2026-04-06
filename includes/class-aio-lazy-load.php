<?php
defined( 'ABSPATH' ) || exit;

/**
 * Lazy Load module.
 *
 * PHP side: output-buffer pass to replace src/srcset with data-src/data-srcset
 * and convert inline background-image styles to data-bg attributes.
 * JS side: lazy-load.js handles IntersectionObserver loading.
 */
class AIO_Lazy_Load {

    private array $opts;
    private int   $img_count = 0;

    // Tiny transparent GIF — preserves layout, works everywhere.
    private const PLACEHOLDER = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

    public function __construct( array $opts ) {
        $this->opts = $opts;
    }

    public function init(): void {
        if ( is_admin() ) {
            return;
        }
        // Elementor editor iframe — bail so lazy load doesn't break the preview.
        if ( isset( $_GET['elementor-preview'] ) ) {
            return;
        }

        $images_active  = $this->opts['lazy_images']  ?? 0;
        $bg_active      = $this->opts['lazy_bg']      ?? 0;
        $iframes_active = $this->opts['lazy_iframes'] ?? 0;

        if ( ! $images_active && ! $bg_active && ! $iframes_active ) {
            return;
        }

        // Detect conflicts: skip if another lazy-load plugin is active.
        if ( $this->has_lazy_conflict() ) {
            return;
        }

        add_action( 'wp_head',           [ $this, 'inject_css' ], 1 );
        add_action( 'template_redirect', [ $this, 'start_buffer' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_script' ] );
    }

    // -------------------------------------------------------------------------
    // Inject minimal frontend CSS — no file, no HTTP request, no conflicts.
    //
    // Only targets our own .aio-lazy* classes.
    // Fade-in runs AFTER the image loads, so images are never invisible.
    // prefers-reduced-motion: no animation, just instant swap.
    // -------------------------------------------------------------------------

    public function inject_css(): void {
        ?>
<style id="aio-lazy-css">
@media(prefers-reduced-motion:no-preference){
    .aio-lazy-loaded{animation:aio-lazy-in .4s ease both}
    .aio-lazy-bg-loaded{animation:aio-lazy-in .4s ease both}
    @keyframes aio-lazy-in{from{opacity:0}to{opacity:1}}
}
</style>
        <?php
    }

    // -------------------------------------------------------------------------
    // Conflict detection
    // -------------------------------------------------------------------------

    private function has_lazy_conflict(): bool {
        // Dedicated lazy-load plugins.
        $conflicting_plugins = [
            'rocket-lazy-load/rocket-lazy-load.php',
            'lazy-load/lazy-load.php',
            'a3-lazy-load/a3-lazy-load.php',
            'bj-lazy-load/bj-lazy-load.php',
            'wp-smushit/wp-smush.php',
            'ewww-image-optimizer/ewww-image-optimizer.php',
            // Optimole has its own lazy load.
            'optimole-wp/optimole-wp.php',
            // ShortPixel Adaptive Images.
            'shortpixel-adaptive-images/shortpixel-adaptive-images.php',
        ];
        foreach ( $conflicting_plugins as $plugin ) {
            if ( is_plugin_active( $plugin ) ) {
                return true;
            }
        }

        // Elementor 3.x+ has a built-in lazy load setting.
        // Only conflict if it is actually turned on.
        if ( is_plugin_active( 'elementor/elementor.php' ) ) {
            $el_settings = get_option( 'elementor_settings', [] );
            if ( is_string( $el_settings ) ) {
                $el_settings = json_decode( $el_settings, true ) ?: [];
            }
            if ( ! empty( $el_settings['lazy_load'] ) ) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Enqueue JS
    // -------------------------------------------------------------------------

    public function enqueue_script(): void {
        wp_enqueue_script(
            'aio-lazy-load',
            AIO_URL . 'assets/js/lazy-load.js',
            [],
            AIO_VERSION,
            true
        );
    }

    // -------------------------------------------------------------------------
    // Output buffer
    // -------------------------------------------------------------------------

    public function start_buffer(): void {
        ob_start( [ $this, 'process_html' ] );
    }

    public function process_html( string $html ): string {
        if ( empty( $html ) ) {
            return $html;
        }

        if ( $this->opts['lazy_images'] ?? 0 ) {
            $html = $this->process_images( $html );
        }

        if ( $this->opts['lazy_iframes'] ?? 0 ) {
            $html = $this->process_iframes( $html );
        }

        if ( $this->opts['lazy_bg'] ?? 0 ) {
            $html = $this->process_backgrounds( $html );
        }

        return $html;
    }

    // -------------------------------------------------------------------------
    // Images: src → data-src, srcset → data-srcset, add <noscript> fallback
    // -------------------------------------------------------------------------

    private function process_images( string $html ): string {
        $skip_first = (bool) ( $this->opts['lazy_skip_first'] ?? 1 );
        $this->img_count = 0;

        return preg_replace_callback(
            '/<img(\s[^>]*?)?\/?>/is',
            function ( array $matches ) use ( $skip_first ): string {
                $this->img_count++;
                $tag = $matches[0];

                // Skip the first image (LCP / hero candidate).
                if ( $skip_first && $this->img_count === 1 ) {
                    return $tag;
                }

                // Already processed or has loading attribute set to eager — skip.
                if ( str_contains( $tag, 'data-src=' ) ) {
                    return $tag;
                }
                if ( preg_match( '/\bloading\s*=\s*["\']eager["\']/i', $tag ) ) {
                    return $tag;
                }

                $original_tag = $tag;

                // Replace src with data-src, inject placeholder src.
                $tag = preg_replace(
                    '/\bsrc\s*=\s*(["\'])(.*?)\1/i',
                    'src="' . self::PLACEHOLDER . '" data-src=$1$2$1',
                    $tag
                );

                // Replace srcset with data-srcset.
                $tag = preg_replace(
                    '/\bsrcset\s*=\s*(["\'])(.*?)\1/i',
                    'data-srcset=$1$2$1',
                    $tag
                );

                // Add aio-lazy class.
                if ( preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/i', $tag ) ) {
                    $tag = preg_replace(
                        '/\bclass\s*=\s*(["\'])(.*?)\1/i',
                        'class=$1$2 aio-lazy$1',
                        $tag
                    );
                } else {
                    $tag = preg_replace( '/(\s*\/?>)$/', ' class="aio-lazy"$1', $tag );
                }

                // Append <noscript> fallback for non-JS browsers.
                $tag .= '<noscript>' . $original_tag . '</noscript>';

                return $tag;
            },
            $html
        );
    }

    // -------------------------------------------------------------------------
    // iFrames: src → data-src
    // -------------------------------------------------------------------------

    private function process_iframes( string $html ): string {
        return preg_replace_callback(
            '/<iframe(\s[^>]*?)?\/?>/is',
            static function ( array $matches ): string {
                $tag = $matches[0];

                if ( str_contains( $tag, 'data-src=' ) ) {
                    return $tag;
                }

                $original = $tag;

                $tag = preg_replace(
                    '/\bsrc\s*=\s*(["\'])(.*?)\1/i',
                    'src="about:blank" data-src=$1$2$1',
                    $tag
                );

                if ( preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/i', $tag ) ) {
                    $tag = preg_replace(
                        '/\bclass\s*=\s*(["\'])(.*?)\1/i',
                        'class=$1$2 aio-lazy$1',
                        $tag
                    );
                } else {
                    $tag = preg_replace( '/(\s*\/?>)$/', ' class="aio-lazy"$1', $tag );
                }

                $tag .= '<noscript>' . $original . '</noscript>';

                return $tag;
            },
            $html
        );
    }

    // -------------------------------------------------------------------------
    // Background images: style="background-image: url(...)" → data-bg
    // -------------------------------------------------------------------------

    private function process_backgrounds( string $html ): string {
        // Match elements with inline background-image in their style attribute.
        return preg_replace_callback(
            '/(<[a-z][^>]*?\bstyle\s*=\s*["\'])([^"\']*?background-image\s*:\s*url\([^)]+\)[^"\']*?)(["\'][^>]*?>)/is',
            static function ( array $m ): string {
                $open_tag_prefix = $m[1]; // e.g. <div style="
                $style_value     = $m[2]; // full style string containing background-image
                $close_part      = $m[3]; // closing quote + rest of tag attributes + >

                // Extract the background-image value.
                preg_match( '/background-image\s*:\s*(url\([^)]+\))/i', $style_value, $bg_match );
                if ( empty( $bg_match[1] ) ) {
                    return $m[0];
                }

                $bg_url = $bg_match[1];

                // Remove background-image from inline style.
                $clean_style = preg_replace(
                    '/\s*background-image\s*:\s*url\([^)]+\)\s*;?/i',
                    '',
                    $style_value
                );
                $clean_style = trim( $clean_style, ' ;' );

                // Build tag: replace style value + inject data-bg.
                $new_tag = $open_tag_prefix . $clean_style . $close_part;

                // Inject data-bg attribute before the closing >.
                $new_tag = preg_replace( '/>$/', ' data-bg="' . esc_attr( $bg_url ) . '">', $new_tag );

                // Add aio-lazy-bg class.
                if ( preg_match( '/\bclass\s*=\s*(["\'])(.*?)\1/i', $new_tag ) ) {
                    $new_tag = preg_replace(
                        '/\bclass\s*=\s*(["\'])(.*?)\1/i',
                        'class=$1$2 aio-lazy-bg$1',
                        $new_tag
                    );
                } else {
                    $new_tag = preg_replace( '/>$/', ' class="aio-lazy-bg">', $new_tag );
                }

                return $new_tag;
            },
            $html
        );
    }
}
