<?php
defined( 'ABSPATH' ) || exit;

class AIO_Autoptimize {

    private array $opts;
    private array $excluded = [];

    /**
     * Script handles that must never be deferred or moved.
     * Includes WordPress core + all major page builder critical scripts.
     */
    private const SAFE_HANDLES = [
        // WordPress core.
        'jquery', 'jquery-core', 'jquery-migrate',
        'wp-polyfill', 'regenerator-runtime',
        'wp-hooks', 'wp-i18n', 'wp-dom-ready',

        // Elementor.
        'elementor-frontend', 'elementor-pro-frontend',
        'elementor-waypoints', 'elementor-sticky',
        'elementor-dialog', 'elementor-common',
        'elementor-app', 'elementor-editor',

        // WP Bakery (Visual Composer).
        'wpb-js-composer-js-comp', 'vc_js',
        'wpb_composer_front_js', 'vc-frontend',

        // Beaver Builder.
        'fl-builder', 'fl-builder-layout',
        'fl-builder-min', 'fl-animations',

        // Divi / Extra.
        'et-builder-modules-script',
        'et-builder-modules-script-pro',
        'divi-custom-script', 'et_pb_smooth_scroll',

        // Avada / Fusion Builder.
        'fusion-builder', 'fusion-frontend',
        'avada-comment-form', 'avada-live',

        // Brizy.
        'brizy-frontend',

        // Bricks Builder.
        'bricks-scripts',

        // Oxygen Builder.
        'oxygen-frontend', 'ct-builder',

        // GeneratePress / GP Premium.
        'generate-child-script', 'generate-back-to-top',

        // Gravity Forms — core scripts must load synchronously for form init.
        'gform_gravityforms', 'gform_json',
        'gform_conditional_logic', 'gform_placeholder',
        'gforms_jquery_json', 'gform_datepicker_init',
        'gform_masked_input', 'gform_chosen',
        'gform_inputmask', 'gform_textarea_counter',
        'gform_field_filter', 'gform_payment',
        'gform_stripe_frontend', 'gform_paypal_frontend',
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
