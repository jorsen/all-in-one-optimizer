<?php
defined( 'ABSPATH' ) || exit;

class AIO_Admin {

    public function init(): void {
        add_action( 'admin_init',         [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
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
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        require_once AIO_DIR . 'admin/views/settings.php';
    }
}
