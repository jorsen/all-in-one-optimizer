<?php
defined( 'ABSPATH' ) || exit;

class AIO_Autoptimize {

    private array $opts;
    private array $excluded = [];

    /**
     * WordPress core handles that must never be deferred or moved,
     * regardless of user settings. Other plugins depend on these being
     * available synchronously before their own scripts execute.
     */
    private const SAFE_HANDLES = [
        'jquery', 'jquery-core', 'jquery-migrate',
        'wp-polyfill', 'regenerator-runtime',
        'wp-hooks', 'wp-i18n', 'wp-dom-ready',
    ];

    public function __construct( array $opts ) {
        $this->opts = $opts;

        // Parse exclude list once.
        if ( ! empty( $opts['auto_exclude_handles'] ) ) {
            $this->excluded = array_map(
                'trim',
                explode( ',', $opts['auto_exclude_handles'] )
            );
        }
    }

    public function init(): void {
        if ( is_admin() ) {
            return;
        }

        if ( $this->opts['auto_defer_js'] || $this->opts['auto_async_js'] ) {
            add_filter( 'script_loader_tag', [ $this, 'add_script_attributes' ], 10, 2 );
        }

        if ( $this->opts['auto_footer_js'] ) {
            add_action( 'wp_enqueue_scripts', [ $this, 'move_scripts_to_footer' ], 99 );
        }

        if ( $this->opts['auto_clean_style_tag'] ) {
            add_filter( 'style_loader_tag', [ $this, 'clean_style_tag' ], 10, 1 );
        }
    }

    // -------------------------------------------------------------------------
    // Add defer / async to <script> tags
    // -------------------------------------------------------------------------

    public function add_script_attributes( string $tag, string $handle ): string {
        // Never touch excluded handles or WordPress core critical handles.
        if ( in_array( $handle, $this->excluded, true ) || in_array( $handle, self::SAFE_HANDLES, true ) ) {
            return $tag;
        }

        // Don't touch tags that already have defer or async.
        if ( str_contains( $tag, ' defer' ) || str_contains( $tag, ' async' ) ) {
            return $tag;
        }

        // async takes priority if enabled; otherwise defer.
        if ( $this->opts['auto_async_js'] ) {
            return str_replace( ' src=', ' async src=', $tag );
        }

        if ( $this->opts['auto_defer_js'] ) {
            return str_replace( ' src=', ' defer src=', $tag );
        }

        return $tag;
    }

    // -------------------------------------------------------------------------
    // Move all front-end scripts to footer
    // -------------------------------------------------------------------------

    public function move_scripts_to_footer(): void {
        global $wp_scripts;

        if ( empty( $wp_scripts->queue ) ) {
            return;
        }

        foreach ( $wp_scripts->queue as $handle ) {
            if ( in_array( $handle, $this->excluded, true ) || in_array( $handle, self::SAFE_HANDLES, true ) ) {
                continue;
            }
            if ( isset( $wp_scripts->registered[ $handle ] ) ) {
                $wp_scripts->registered[ $handle ]->extra['group'] = 1;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Remove type="text/css" from <link> style tags (HTML5 clean-up)
    // -------------------------------------------------------------------------

    public function clean_style_tag( string $tag ): string {
        return str_replace( " type='text/css'", '', $tag );
    }
}
