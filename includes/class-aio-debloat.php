<?php
defined( 'ABSPATH' ) || exit;

class AIO_Debloat {

    private array $opts;

    public function __construct( array $opts ) {
        $this->opts = $opts;
    }

    public function init(): void {
        if ( $this->opts['debloat_emoji'] ) {
            $this->remove_emoji();
        }
        if ( $this->opts['debloat_jquery_migrate'] ) {
            add_action( 'wp_default_scripts', [ $this, 'remove_jquery_migrate' ] );
        }
        if ( $this->opts['debloat_xmlrpc'] ) {
            add_filter( 'xmlrpc_enabled', '__return_false' );
        }
        if ( $this->opts['debloat_generator'] ) {
            remove_action( 'wp_head', 'wp_generator' );
        }
        if ( $this->opts['debloat_wlwmanifest'] ) {
            remove_action( 'wp_head', 'wlwmanifest_link' );
        }
        if ( $this->opts['debloat_rsd'] ) {
            remove_action( 'wp_head', 'rsd_link' );
        }
        if ( $this->opts['debloat_rest_links'] ) {
            remove_action( 'wp_head', 'rest_output_link_wp_head' );
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
            remove_action( 'template_redirect', 'rest_output_link_header', 11 );
        }
        if ( $this->opts['debloat_shortlink'] ) {
            remove_action( 'wp_head', 'wp_shortlink_wp_head' );
            remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
        }
        if ( $this->opts['debloat_self_ping'] ) {
            add_action( 'pre_ping', [ $this, 'disable_self_ping' ] );
        }
        if ( $this->opts['debloat_query_strings'] ) {
            add_filter( 'script_loader_src', [ $this, 'remove_query_string' ], 15 );
            add_filter( 'style_loader_src',  [ $this, 'remove_query_string' ], 15 );
        }
        if ( $this->opts['debloat_heartbeat'] ) {
            add_action( 'init', [ $this, 'control_heartbeat' ] );
        }
        if ( $this->opts['debloat_oembed'] ) {
            add_filter( 'rewrite_rules_array', [ $this, 'disable_oembed_rewrite' ] );
            remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
            remove_action( 'wp_head', 'wp_oembed_add_host_js' );
            add_filter( 'embed_oembed_discover', '__return_false' );
        }
    }

    // -------------------------------------------------------------------------
    // Emoji
    // -------------------------------------------------------------------------

    private function remove_emoji(): void {
        remove_action( 'wp_head',             'print_emoji_detection_script', 7 );
        remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
        remove_action( 'wp_print_styles',     'print_emoji_styles' );
        remove_action( 'admin_print_styles',  'print_emoji_styles' );
        remove_filter( 'the_content_feed',    'wp_staticize_emoji' );
        remove_filter( 'comment_text_rss',    'wp_staticize_emoji' );
        remove_filter( 'wp_mail',             'wp_staticize_emoji_for_email' );
        add_filter( 'tiny_mce_plugins', [ $this, 'remove_tinymce_emoji' ] );
        add_filter( 'wp_resource_hints', [ $this, 'remove_emoji_dns_prefetch' ], 10, 2 );
    }

    public function remove_tinymce_emoji( array $plugins ): array {
        return array_diff( $plugins, [ 'wpemoji' ] );
    }

    public function remove_emoji_dns_prefetch( array $urls, string $relation_type ): array {
        if ( 'dns-prefetch' === $relation_type ) {
            $urls = array_filter( $urls, static fn( $url ) => ! str_contains( (string) $url, 'twemoji' ) );
        }
        return $urls;
    }

    // -------------------------------------------------------------------------
    // jQuery Migrate
    // -------------------------------------------------------------------------

    public function remove_jquery_migrate( \WP_Scripts $scripts ): void {
        if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
            $script = $scripts->registered['jquery'];
            if ( $script->deps ) {
                $script->deps = array_diff( $script->deps, [ 'jquery-migrate' ] );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Self-pingbacks
    // -------------------------------------------------------------------------

    public function disable_self_ping( array &$links ): void {
        $home = get_option( 'home' );
        foreach ( $links as $key => $link ) {
            if ( str_starts_with( $link, $home ) ) {
                unset( $links[ $key ] );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Query strings
    // -------------------------------------------------------------------------

    public function remove_query_string( string $src ): string {
        if ( str_contains( $src, '?ver=' ) || str_contains( $src, '&ver=' ) ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    // -------------------------------------------------------------------------
    // Heartbeat
    // -------------------------------------------------------------------------

    public function control_heartbeat(): void {
        if ( ! is_admin() ) {
            wp_deregister_script( 'heartbeat' );
        } else {
            add_filter( 'heartbeat_settings', static function ( array $settings ): array {
                $settings['interval'] = 60;
                return $settings;
            } );
        }
    }

    // -------------------------------------------------------------------------
    // oEmbed rewrite
    // -------------------------------------------------------------------------

    public function disable_oembed_rewrite( array $rules ): array {
        foreach ( $rules as $rule => $rewrite ) {
            if ( str_contains( $rewrite, 'embed=true' ) ) {
                unset( $rules[ $rule ] );
            }
        }
        return $rules;
    }
}
