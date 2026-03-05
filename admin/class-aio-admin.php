<?php
defined( 'ABSPATH' ) || exit;

class AIO_Admin {

    public function init(): void {
        // register_settings() must run on admin_init. Since init() is already
        // called from an admin_init callback, call it directly here rather than
        // adding another admin_init hook (nested hooks at the same priority don't fire).
        $this->register_settings();
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_aio_clear_cache', [ $this, 'handle_clear_cache' ] );
        add_action( 'admin_bar_menu', [ $this, 'add_admin_bar_node' ], 100 );

        // When settings are saved, clear the cached GitHub release data so the
        // next settings page load always performs a fresh update check.
        if ( ! empty( $_GET['settings-updated'] ) && class_exists( 'AIO_Updater' ) ) {
            delete_transient( AIO_Updater::TRANSIENT );
        }
    }

    // -------------------------------------------------------------------------
    // Clear Cache handler — clears AIO transients + common caching plugins
    // -------------------------------------------------------------------------

    public function handle_clear_cache(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'aio-optimizer' ) );
        }
        check_admin_referer( 'aio_clear_cache' );

        // AIO own transients.
        if ( class_exists( 'AIO_Updater' ) ) {
            delete_transient( AIO_Updater::TRANSIENT );
        }
        delete_site_transient( 'update_plugins' );

        // WordPress object cache.
        wp_cache_flush();

        // WP Rocket.
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();
        }

        // W3 Total Cache.
        if ( function_exists( 'w3tc_flush_all' ) ) {
            w3tc_flush_all();
        }

        // WP Super Cache.
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();
        }

        // LiteSpeed Cache.
        if ( has_action( 'litespeed_purge_all' ) ) {
            do_action( 'litespeed_purge_all' );
        }

        // Autoptimize.
        if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
            autoptimizeCache::clearall();
        }

        // WP Fastest Cache.
        if ( ! empty( $GLOBALS['wp_fastest_cache'] ) && method_exists( $GLOBALS['wp_fastest_cache'], 'deleteCache' ) ) {
            $GLOBALS['wp_fastest_cache']->deleteCache();
        }

        // Cache Enabler.
        if ( has_action( 'cache_enabler_clear_complete_cache' ) ) {
            do_action( 'cache_enabler_clear_complete_cache' );
        }

        // Breeze (Cloudways).
        if ( has_action( 'breeze_clear_varnish' ) ) {
            do_action( 'breeze_clear_varnish' );
        }

        // SG Optimizer.
        if ( has_action( 'sg_cachepress_purge_cache' ) ) {
            do_action( 'sg_cachepress_purge_cache' );
        }

        // Hummingbird.
        if ( has_action( 'wphb_clear_page_cache' ) ) {
            do_action( 'wphb_clear_page_cache' );
        }

        wp_safe_redirect( add_query_arg(
            [ 'page' => 'aio-optimizer', 'aio_cache_cleared' => '1' ],
            admin_url( 'options-general.php' )
        ) );
        exit;
    }

    // -------------------------------------------------------------------------
    // Admin bar node — "Clear Cache" button visible on every page
    // -------------------------------------------------------------------------

    public function add_admin_bar_node( \WP_Admin_Bar $bar ): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $url = wp_nonce_url(
            admin_url( 'admin-post.php?action=aio_clear_cache' ),
            'aio_clear_cache'
        );
        $bar->add_node( [
            'id'    => 'aio-clear-cache',
            'title' => '&#x1F5D1; Clear Cache',
            'href'  => $url,
            'meta'  => [ 'title' => __( 'Clear AIO Optimizer Cache', 'aio-optimizer' ) ],
        ] );
    }

    public function register_menu(): void {
        add_options_page(
            __( 'AIO Optimizer', 'aio-optimizer' ),
            __( 'AIO Optimizer', 'aio-optimizer' ),
            'manage_options',
            'aio-optimizer',
            [ $this, 'render_page' ]
        );
    }

    public function register_settings(): void {
        register_setting(
            'aio_optimizer_group',
            AIO_OPTION,
            [ $this, 'sanitize_options' ]
        );
    }

    public function sanitize_options( $input ): array {
        $clean = [];

        // Checkbox fields (1 or 0).
        $checkboxes = [
            'debloat_emoji', 'debloat_jquery_migrate', 'debloat_xmlrpc',
            'debloat_generator', 'debloat_wlwmanifest', 'debloat_rsd',
            'debloat_rest_links', 'debloat_shortlink', 'debloat_self_ping',
            'debloat_query_strings', 'debloat_heartbeat', 'debloat_oembed',
            'auto_defer_js', 'auto_async_js', 'auto_footer_js', 'auto_clean_style_tag',
            'lazy_images', 'lazy_iframes', 'lazy_bg', 'lazy_skip_first',
            'fly_enable', 'fly_mobile', 'fly_images',
            'spa_enable',
        ];

        foreach ( $checkboxes as $key ) {
            $clean[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
        }

        // Text fields.
        $clean['auto_exclude_handles'] = sanitize_text_field( $input['auto_exclude_handles'] ?? '' );
        $clean['spa_selector']         = sanitize_text_field( $input['spa_selector'] ?? '#content, main, .site-main' );
        $clean['spa_exclude']          = sanitize_textarea_field( $input['spa_exclude'] ?? '' );

        // Integer fields.
        $clean['fly_delay'] = max( 0, (int) ( $input['fly_delay'] ?? 65 ) );

        return $clean;
    }

    public function enqueue_assets( string $hook ): void {
        if ( 'settings_page_aio-optimizer' !== $hook ) {
            return;
        }
        wp_enqueue_style( 'aio-admin', AIO_URL . 'assets/css/admin.css', [], AIO_VERSION );
        wp_enqueue_script( 'aio-admin', AIO_URL . 'assets/js/admin.js', [], AIO_VERSION, true );
        // Consume the activation transient — show tour once, then it's gone.
        $show_tour = (bool) get_transient( 'aio_show_tour' );
        if ( $show_tour ) {
            delete_transient( 'aio_show_tour' );
        }

        wp_localize_script( 'aio-admin', 'aioAdmin', [
            'homeUrl'  => home_url( '/' ),
            'showTour' => $show_tour,
        ] );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once AIO_DIR . 'admin/views/settings.php';
    }
}
