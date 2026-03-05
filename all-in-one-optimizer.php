<?php
/**
 * Plugin Name:       All-in-One Optimizer
 * Plugin URI:        https://github.com/jorsen/all-in-one-optimizer
 * Description:       Debloat, Autoptimize (defer/async), Image Lazy Load, Flying Pages prefetch, and SPA navigation — all in one lightweight plugin.
 * Version:           1.5.2
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Jorsen Mejia
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aio-optimizer
 */

defined( 'ABSPATH' ) || exit;

define( 'AIO_VERSION', '1.5.2' );
define( 'AIO_DIR',     plugin_dir_path( __FILE__ ) );
define( 'AIO_URL',     plugin_dir_url( __FILE__ ) );
define( 'AIO_OPTION',  'aio_optimizer_options' );

/**
 * Return plugin options, merged with defaults.
 */
function aio_get_options(): array {
    $defaults = [
        // Debloat
        'debloat_emoji'           => 1,
        'debloat_jquery_migrate'  => 1,
        'debloat_xmlrpc'          => 1,
        'debloat_generator'       => 1,
        'debloat_wlwmanifest'     => 1,
        'debloat_rsd'             => 1,
        'debloat_rest_links'      => 1,
        'debloat_shortlink'       => 1,
        'debloat_self_ping'       => 1,
        'debloat_query_strings'   => 1,
        'debloat_heartbeat'       => 1,
        'debloat_oembed'          => 1,
        // Autoptimize
        'auto_defer_js'           => 1,
        'auto_async_js'           => 0,
        'auto_footer_js'          => 1,
        'auto_clean_style_tag'    => 1,
        'auto_exclude_handles'    => 'jquery',
        // Lazy Load
        'lazy_images'             => 1,
        'lazy_iframes'            => 1,
        'lazy_bg'                 => 1,
        'lazy_skip_first'         => 1,
        // Flying Pages
        'fly_enable'              => 1,
        // Flying Images
        'fly_images'              => 1,
        'fly_delay'               => 65,
        'fly_mobile'              => 0,
        // SPA
        'spa_enable'              => 1,
        'spa_selector'            => '#content, #main-content, #primary, .site-main, .main-content, .content-area, main',
        'spa_exclude'             => '/wp-admin, /wp-login, /cart, /checkout, /my-account',
    ];

    $saved = get_option( AIO_OPTION, [] );
    return wp_parse_args( $saved, $defaults );
}

/**
 * Bootstrap: load modules based on saved options.
 */
function aio_bootstrap(): void {
    $opts = aio_get_options();

    require_once AIO_DIR . 'includes/class-aio-debloat.php';
    require_once AIO_DIR . 'includes/class-aio-autoptimize.php';
    require_once AIO_DIR . 'includes/class-aio-lazy-load.php';
    require_once AIO_DIR . 'includes/class-aio-spa.php';
    require_once AIO_DIR . 'includes/class-aio-updater.php';

    ( new AIO_Debloat( $opts ) )->init();
    ( new AIO_Autoptimize( $opts ) )->init();
    ( new AIO_Lazy_Load( $opts ) )->init();
    ( new AIO_SPA( $opts ) )->init();
    ( new AIO_Updater() )->init();
}
add_action( 'plugins_loaded', 'aio_bootstrap' );

/**
 * Admin: load settings page.
 */
function aio_admin_bootstrap(): void {
    require_once AIO_DIR . 'admin/class-aio-admin.php';
    ( new AIO_Admin() )->init();
}
add_action( 'admin_init', 'aio_admin_bootstrap' );
add_action( 'admin_menu', function () {
    require_once AIO_DIR . 'admin/class-aio-admin.php';
    ( new AIO_Admin() )->register_menu();
} );

/**
 * Activation: set default options.
 */
register_activation_hook( __FILE__, function () {
    if ( ! get_option( AIO_OPTION ) ) {
        update_option( AIO_OPTION, [] ); // defaults applied via wp_parse_args
    }
} );
