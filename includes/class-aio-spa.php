<?php
defined( 'ABSPATH' ) || exit;

class AIO_SPA {

    private array $opts;

    public function __construct( array $opts ) {
        $this->opts = $opts;
    }

    public function init(): void {
        if ( is_admin() ) {
            return;
        }
        // Elementor editor iframe — bail so SPA doesn't interfere with the preview.
        if ( isset( $_GET['elementor-preview'] ) ) {
            return;
        }
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

        // Chrome 98+ Private Network Access: when the site runs on a local IP
        // (192.168.x.x, 10.x.x, etc.) Chrome sends an OPTIONS preflight with
        // "Access-Control-Request-Private-Network: true" before our fetch()
        // calls. Without this response header the browser shows a permission
        // dialog ("Allow access to devices on your local network").
        add_filter( 'wp_headers', [ $this, 'add_private_network_header' ] );
    }

    public function add_private_network_header( array $headers ): array {
        $headers['Access-Control-Allow-Private-Network'] = 'true';
        return $headers;
    }

    public function enqueue_assets(): void {

        // Flying Pages — fetch-based prefetch with shared page cache.
        if ( $this->opts['fly_enable'] ) {
            wp_enqueue_script(
                'aio-flying-pages',
                AIO_URL . 'assets/js/flying-pages.js',
                [],
                AIO_VERSION,
                true
            );
            wp_localize_script( 'aio-flying-pages', 'aioFlyConfig', [
                'delay'  => (int) $this->opts['fly_delay'],
                'mobile' => (bool) $this->opts['fly_mobile'],
            ] );
        }

        // SPA navigation — depends on flying-pages cache being available.
        if ( $this->opts['spa_enable'] ) {
            $deps = $this->opts['fly_enable'] ? [ 'aio-flying-pages' ] : [];

            wp_enqueue_script(
                'aio-spa',
                AIO_URL . 'assets/js/spa.js',
                $deps,
                AIO_VERSION,
                true
            );

            $exclude_raw = $this->opts['spa_exclude'] ?? '';
            $excludes    = array_values( array_filter( array_map(
                'trim',
                explode( ',', $exclude_raw )
            ) ) );

            // Pass the admin URL path so spa.js can exclude admin pages even
            // when WordPress is installed in a subdirectory (e.g. /blog/wp-admin).
            $admin_path = wp_parse_url( admin_url( '/' ), PHP_URL_PATH );

            wp_localize_script( 'aio-spa', 'aioSpaConfig', [
                'selector'  => sanitize_text_field( $this->opts['spa_selector'] ),
                'exclude'   => $excludes,
                'home'      => home_url( '/' ),
                'adminPath' => $admin_path,
            ] );
        }

        // Diagnostic overlay — admin-only, only when ?aio_diag=1 is in the URL.
        // Loads before spa.js so the console patch captures its init messages.
        if ( ! empty( $_GET['aio_diag'] ) && current_user_can( 'manage_options' ) ) {
            wp_enqueue_script(
                'aio-diag',
                AIO_URL . 'assets/js/diag.js',
                [],
                AIO_VERSION,
                false // in <head> so it patches console before footer scripts run
            );
        }

        // Flying Images — shared element transitions; requires SPA to fire events.
        if ( ! empty( $this->opts['fly_images'] ) && $this->opts['spa_enable'] ) {
            $deps = $this->opts['spa_enable'] ? [ 'aio-spa' ] : [];

            wp_enqueue_script(
                'aio-flying-images',
                AIO_URL . 'assets/js/flying-images.js',
                $deps,
                AIO_VERSION,
                true
            );
        }
    }
}
