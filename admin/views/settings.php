<?php
defined( 'ABSPATH' ) || exit;

$opts   = aio_get_options();
$active = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'debloat';
$tabs   = [
    'debloat'      => __( 'Debloat', 'aio-optimizer' ),
    'autoptimize'  => __( 'Autoptimize', 'aio-optimizer' ),
    'lazyload'     => __( 'Lazy Load', 'aio-optimizer' ),
    'flyingpages'  => __( 'Flying Pages', 'aio-optimizer' ),
    'flyingimages' => __( 'Flying Images', 'aio-optimizer' ),
    'spa'          => __( 'SPA', 'aio-optimizer' ),
    'diagnostics'  => __( 'Diagnostics', 'aio-optimizer' ),
];

// Build tab URLs explicitly so they survive form-save redirects.
$base_url = admin_url( 'options-general.php?page=aio-optimizer' );

// Check if a manual update check or cache clear was just triggered.
$just_checked  = ! empty( $_GET['aio_checked'] );
$just_cleared  = ! empty( $_GET['aio_cache_cleared'] );

// Get cached release info for version status (no new API call here).
$release    = class_exists( 'AIO_Updater' ) ? ( new AIO_Updater() )->get_release() : null;
$has_update = $release && version_compare( $release['version'], AIO_VERSION, '>' );
?>
<div class="wrap aio-wrap">
    <div class="aio-header">
        <h1><?php esc_html_e( 'All-in-One Optimizer', 'aio-optimizer' ); ?></h1>
        <div class="aio-version-bar">
            <span class="aio-version-current">
                <?php
                printf(
                    /* translators: %s = version number */
                    esc_html__( 'Version %s', 'aio-optimizer' ),
                    esc_html( AIO_VERSION )
                );
                ?>
            </span>

            <?php if ( $has_update ) : ?>
                <?php
                $update_url = wp_nonce_url(
                    admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( 'all-in-one-optimizer/all-in-one-optimizer.php' ) ),
                    'upgrade-plugin_all-in-one-optimizer/all-in-one-optimizer.php'
                );
                ?>
                <span class="aio-update-badge">
                    <?php
                    printf(
                        esc_html__( 'Update available: %s', 'aio-optimizer' ),
                        esc_html( $release['version'] )
                    );
                    ?>
                    &mdash;
                    <a href="<?php echo esc_url( $update_url ); ?>" class="button button-primary button-small">
                        <?php esc_html_e( 'Update Now', 'aio-optimizer' ); ?>
                    </a>
                </span>
            <?php elseif ( $just_checked ) : ?>
                <span class="aio-up-to-date"><?php esc_html_e( 'You are up to date.', 'aio-optimizer' ); ?></span>
            <?php elseif ( $just_cleared ) : ?>
                <span class="aio-up-to-date"><?php esc_html_e( 'Cache cleared.', 'aio-optimizer' ); ?></span>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                <?php wp_nonce_field( 'aio_clear_cache' ); ?>
                <input type="hidden" name="action" value="aio_clear_cache">
                <button type="submit" class="button button-small">
                    <?php esc_html_e( 'Clear Cache', 'aio-optimizer' ); ?>
                </button>
            </form>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                <?php wp_nonce_field( 'aio_check_update' ); ?>
                <input type="hidden" name="action" value="aio_check_update">
                <button type="submit" class="button button-small aio-check-update-btn">
                    <?php esc_html_e( 'Check for Updates', 'aio-optimizer' ); ?>
                </button>
            </form>

            <button type="button" id="aio-open-tour" class="button button-small" style="margin-left:2px" title="<?php esc_attr_e( 'Open the plugin walkthrough guide', 'aio-optimizer' ); ?>">
                <?php esc_html_e( 'View Guide', 'aio-optimizer' ); ?>
            </button>
        </div>
    </div>

    <?php
    // After saving settings: show a combined "saved + update" notice if a
    // newer version is available.
    if ( ! empty( $_GET['settings-updated'] ) && $has_update ) :
        $update_url = wp_nonce_url(
            admin_url( 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( 'all-in-one-optimizer/all-in-one-optimizer.php' ) ),
            'upgrade-plugin_all-in-one-optimizer/all-in-one-optimizer.php'
        );
        ?>
        <div class="notice notice-warning is-dismissible" style="margin-top:12px">
            <p>
                <strong><?php esc_html_e( 'Plugin update available:', 'aio-optimizer' ); ?></strong>
                <?php
                printf(
                    /* translators: 1: current version, 2: new version */
                    esc_html__( 'You are on v%1$s — v%2$s is available.', 'aio-optimizer' ),
                    esc_html( AIO_VERSION ),
                    esc_html( $release['version'] )
                );
                ?>
                &nbsp;<a href="<?php echo esc_url( $update_url ); ?>" class="button button-primary button-small">
                    <?php esc_html_e( 'Update Now', 'aio-optimizer' ); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php // ================================================================
          // PRESET BAR
          // ================================================================ ?>
    <div class="aio-preset-bar">
        <span class="aio-preset-label"><?php esc_html_e( 'Quick Preset:', 'aio-optimizer' ); ?></span>

        <button type="button" class="aio-preset-btn button" data-preset="safe">
            <?php esc_html_e( 'Safe', 'aio-optimizer' ); ?>
            <span class="aio-preset-tag"><?php esc_html_e( 'Debloat only', 'aio-optimizer' ); ?></span>
        </button>

        <button type="button" class="aio-preset-btn button" data-preset="balanced">
            <?php esc_html_e( 'Balanced', 'aio-optimizer' ); ?>
            <span class="aio-preset-tag"><?php esc_html_e( '+ Defer + Lazy + Prefetch', 'aio-optimizer' ); ?></span>
        </button>

        <button type="button" class="aio-preset-btn button button-primary" data-preset="full">
            <?php esc_html_e( 'Full Performance', 'aio-optimizer' ); ?>
            <span class="aio-preset-tag"><?php esc_html_e( 'Everything on', 'aio-optimizer' ); ?></span>
        </button>

        <span id="aio-preset-notice" class="aio-preset-notice" style="display:none"></span>
    </div>

    <?php // ================================================================
          // TABS — JS-powered, no page reload
          // ================================================================ ?>
    <nav class="aio-tabs">
        <?php foreach ( $tabs as $slug => $label ) : ?>
            <a href="<?php echo esc_url( $base_url . '&tab=' . $slug ); ?>"
               class="aio-tab<?php echo $active === $slug ? ' aio-tab--active' : ''; ?>"
               data-tab="<?php echo esc_attr( $slug ); ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <?php // All tab panels are rendered at once; JS controls visibility. ?>
    <form method="post" action="options.php">
        <?php settings_fields( 'aio_optimizer_group' ); ?>

        <?php // ================================================================
              // PANEL: DEBLOAT
              // ================================================================ ?>
        <div id="aio-panel-debloat" class="aio-tab-panel"<?php echo $active !== 'debloat' ? ' style="display:none"' : ''; ?>>
            <div class="aio-card">
                <h2><?php esc_html_e( 'Debloat WordPress', 'aio-optimizer' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Conditionally remove unnecessary scripts, styles and meta tags. Compatible with any theme — only safe deregistrations applied.', 'aio-optimizer' ); ?></p>
                <table class="form-table" role="presentation">
                    <?php
                    $debloat_opts = [
                        'debloat_emoji'          => __( 'Remove Emoji scripts &amp; styles', 'aio-optimizer' ),
                        'debloat_jquery_migrate' => __( 'Remove jQuery Migrate', 'aio-optimizer' ),
                        'debloat_xmlrpc'         => __( 'Disable XML-RPC', 'aio-optimizer' ),
                        'debloat_generator'      => __( 'Remove &lt;meta name="generator"&gt;', 'aio-optimizer' ),
                        'debloat_wlwmanifest'    => __( 'Remove wlwmanifest link', 'aio-optimizer' ),
                        'debloat_rsd'            => __( 'Remove RSD link', 'aio-optimizer' ),
                        'debloat_rest_links'     => __( 'Remove REST API links from &lt;head&gt;', 'aio-optimizer' ),
                        'debloat_shortlink'      => __( 'Remove shortlink', 'aio-optimizer' ),
                        'debloat_self_ping'      => __( 'Disable self-pingbacks', 'aio-optimizer' ),
                        'debloat_query_strings'  => __( 'Remove query strings from static assets', 'aio-optimizer' ),
                        'debloat_heartbeat'      => __( 'Disable Heartbeat on front-end / throttle in admin', 'aio-optimizer' ),
                        'debloat_oembed'         => __( 'Remove oEmbed discovery links', 'aio-optimizer' ),
                    ];
                    foreach ( $debloat_opts as $key => $label ) : ?>
                    <tr>
                        <th scope="row"><?php echo wp_kses( $label, [] ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox"
                                       name="<?php echo esc_attr( AIO_OPTION . '[' . $key . ']' ); ?>"
                                       value="1"
                                       <?php checked( 1, $opts[ $key ] ?? 0 ); ?>>
                                <?php esc_html_e( 'Enable', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>

        <?php // ================================================================
              // PANEL: AUTOPTIMIZE
              // ================================================================ ?>
        <div id="aio-panel-autoptimize" class="aio-tab-panel"<?php echo $active !== 'autoptimize' ? ' style="display:none"' : ''; ?>>
            <div class="aio-card">
                <h2><?php esc_html_e( 'Autoptimize JS / CSS', 'aio-optimizer' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Optimize how scripts and styles are loaded — no file rewriting required.', 'aio-optimizer' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Defer JS', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[auto_defer_js]' ); ?>" value="1" <?php checked( 1, $opts['auto_defer_js'] ); ?>>
                                <?php esc_html_e( 'Add defer attribute to all non-excluded scripts', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Async JS', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[auto_async_js]' ); ?>" value="1" <?php checked( 1, $opts['auto_async_js'] ); ?>>
                                <?php esc_html_e( 'Use async instead of defer (overrides defer if both are enabled)', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Move JS to Footer', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[auto_footer_js]' ); ?>" value="1" <?php checked( 1, $opts['auto_footer_js'] ); ?>>
                                <?php esc_html_e( 'Force enqueued scripts to load in the footer', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Clean style tags', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[auto_clean_style_tag]' ); ?>" value="1" <?php checked( 1, $opts['auto_clean_style_tag'] ); ?>>
                                <?php esc_html_e( 'Remove type="text/css" from &lt;link&gt; tags (HTML5)', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="auto_exclude_handles"><?php esc_html_e( 'Exclude script handles', 'aio-optimizer' ); ?></label></th>
                        <td>
                            <input type="text" id="auto_exclude_handles"
                                   name="<?php echo esc_attr( AIO_OPTION . '[auto_exclude_handles]' ); ?>"
                                   value="<?php echo esc_attr( $opts['auto_exclude_handles'] ); ?>"
                                   class="regular-text">
                            <p class="description"><?php esc_html_e( 'Comma-separated WordPress script handles to skip (e.g. jquery,woocommerce).', 'aio-optimizer' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php // ================================================================
              // PANEL: LAZY LOAD
              // ================================================================ ?>
        <div id="aio-panel-lazyload" class="aio-tab-panel"<?php echo $active !== 'lazyload' ? ' style="display:none"' : ''; ?>>
            <div class="aio-card">
                <h2><?php esc_html_e( 'Image &amp; iFrame Lazy Load', 'aio-optimizer' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Replaces src with data-src and loads via IntersectionObserver. Adds &lt;noscript&gt; for non-JS browsers.', 'aio-optimizer' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Lazy load images', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[lazy_images]' ); ?>" value="1" <?php checked( 1, $opts['lazy_images'] ); ?>>
                                <?php esc_html_e( 'Defer &lt;img&gt; loading via IntersectionObserver', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Lazy load iframes', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[lazy_iframes]' ); ?>" value="1" <?php checked( 1, $opts['lazy_iframes'] ); ?>>
                                <?php esc_html_e( 'Defer &lt;iframe&gt; loading (YouTube, maps, etc.)', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Lazy load background images', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[lazy_bg]' ); ?>" value="1" <?php checked( 1, $opts['lazy_bg'] ?? 1 ); ?>>
                                <?php esc_html_e( 'Defer inline style background-image via data-bg attribute', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Skip first image', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[lazy_skip_first]' ); ?>" value="1" <?php checked( 1, $opts['lazy_skip_first'] ); ?>>
                                <?php esc_html_e( 'Skip the first image (usually the LCP / hero image)', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                <div class="aio-notice aio-notice--info">
                    <strong><?php esc_html_e( 'Note:', 'aio-optimizer' ); ?></strong>
                    <?php esc_html_e( 'Auto-disabled if a conflicting plugin is detected (Smush, EWWW, Rocket Lazy Load, etc.).', 'aio-optimizer' ); ?>
                </div>
            </div>
        </div>

        <?php // ================================================================
              // PANEL: FLYING PAGES
              // ================================================================ ?>
        <div id="aio-panel-flyingpages" class="aio-tab-panel"<?php echo $active !== 'flyingpages' ? ' style="display:none"' : ''; ?>>
            <div class="aio-card">
                <h2><?php esc_html_e( 'Flying Pages — Link Prefetch', 'aio-optimizer' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Fetches pages in the background on hover. Pre-warmed pages are served instantly by the SPA module.', 'aio-optimizer' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Flying Pages', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[fly_enable]' ); ?>" value="1" <?php checked( 1, $opts['fly_enable'] ); ?>>
                                <?php esc_html_e( 'Enable hover prefetching via fetch API', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fly_delay"><?php esc_html_e( 'Hover delay (ms)', 'aio-optimizer' ); ?></label></th>
                        <td>
                            <input type="number" id="fly_delay" min="0" max="2000"
                                   name="<?php echo esc_attr( AIO_OPTION . '[fly_delay]' ); ?>"
                                   value="<?php echo esc_attr( $opts['fly_delay'] ); ?>"
                                   class="small-text">
                            <p class="description"><?php esc_html_e( 'Milliseconds before prefetch fires after hover. 0 = instant. Default: 65.', 'aio-optimizer' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable on mobile', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[fly_mobile]' ); ?>" value="1" <?php checked( 1, $opts['fly_mobile'] ); ?>>
                                <?php esc_html_e( 'Prefetch on touchstart for mobile (uses more data)', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php // ================================================================
              // PANEL: FLYING IMAGES
              // ================================================================ ?>
        <div id="aio-panel-flyingimages" class="aio-tab-panel"<?php echo $active !== 'flyingimages' ? ' style="display:none"' : ''; ?>>
            <div class="aio-card">
                <h2><?php esc_html_e( 'Flying Images — Shared Element Transitions', 'aio-optimizer' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Images that appear on both the current and next page animate from their old position to the new one during SPA navigation.', 'aio-optimizer' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable Flying Images', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[fly_images]' ); ?>" value="1" <?php checked( 1, $opts['fly_images'] ?? 1 ); ?>>
                                <?php esc_html_e( 'Animate shared images between SPA page transitions', 'aio-optimizer' ); ?>
                            </label>
                            <p class="description"><?php esc_html_e( 'Requires SPA navigation to be enabled. Respects prefers-reduced-motion.', 'aio-optimizer' ); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <?php // ================================================================
              // PANEL: SPA
              // ================================================================ ?>
        <div id="aio-panel-spa" class="aio-tab-panel"<?php echo $active !== 'spa' ? ' style="display:none"' : ''; ?>>
            <div class="aio-card">
                <h2><?php esc_html_e( 'SPA Navigation', 'aio-optimizer' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Intercepts link clicks and swaps page content via fetch — no full reload. Includes fade transitions, a progress bar, and automatic fallback on errors.', 'aio-optimizer' ); ?></p>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e( 'Enable SPA', 'aio-optimizer' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[spa_enable]' ); ?>" value="1" <?php checked( 1, $opts['spa_enable'] ); ?>>
                                <?php esc_html_e( 'Enable fetch-based page navigation', 'aio-optimizer' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="spa_selector"><?php esc_html_e( 'Content selector', 'aio-optimizer' ); ?></label></th>
                        <td>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                                <input type="text" id="spa_selector"
                                       name="<?php echo esc_attr( AIO_OPTION . '[spa_selector]' ); ?>"
                                       value="<?php echo esc_attr( $opts['spa_selector'] ); ?>"
                                       class="regular-text">
                                <button type="button" id="aio-detect-selector" class="button">
                                    <?php esc_html_e( 'Auto-detect', 'aio-optimizer' ); ?>
                                </button>
                            </div>
                            <p id="aio-detect-result" class="description" style="margin-top:4px"></p>
                            <p class="description">
                                <?php esc_html_e( 'Auto-detect scans your homepage to find the correct content wrapper. Or enter manually:', 'aio-optimizer' ); ?><br>
                                <strong><?php esc_html_e( 'Most themes:', 'aio-optimizer' ); ?></strong> <code>#content, main, .site-main</code><br>
                                <strong><?php esc_html_e( 'Hello + Elementor (canvas/full-width):', 'aio-optimizer' ); ?></strong> <code>[data-elementor-type="wp-page"]</code>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="spa_exclude"><?php esc_html_e( 'Exclude URL patterns', 'aio-optimizer' ); ?></label></th>
                        <td>
                            <textarea id="spa_exclude"
                                      name="<?php echo esc_attr( AIO_OPTION . '[spa_exclude]' ); ?>"
                                      rows="5" class="large-text"><?php echo esc_textarea( $opts['spa_exclude'] ); ?></textarea>
                            <p class="description"><?php esc_html_e( 'Comma-separated URL path prefixes to exclude. E.g. /wp-admin, /cart, /checkout', 'aio-optimizer' ); ?></p>
                        </td>
                    </tr>
                </table>
                <div class="aio-notice aio-notice--info">
                    <strong><?php esc_html_e( 'Escape hatch:', 'aio-optimizer' ); ?></strong>
                    <?php esc_html_e( 'Add data-no-spa to any &lt;a&gt; tag to force a normal page load for that link.', 'aio-optimizer' ); ?>
                </div>
            </div>
        </div>

        <?php // ================================================================
              // PANEL: DIAGNOSTICS (read-only — no form fields)
              // ================================================================ ?>
        <div id="aio-panel-diagnostics" class="aio-tab-panel"<?php echo $active !== 'diagnostics' ? ' style="display:none"' : ''; ?>>
            <?php
            // Module status based on saved options.
            $diag_modules = [
                'Debloat'        => array_sum( array_intersect_key( $opts, array_flip( [
                    'debloat_emoji','debloat_jquery_migrate','debloat_xmlrpc','debloat_generator',
                    'debloat_wlwmanifest','debloat_rsd','debloat_rest_links','debloat_shortlink',
                    'debloat_self_ping','debloat_query_strings','debloat_heartbeat','debloat_oembed',
                ] ) ) ) > 0,
                'Autoptimize'    => (bool) ( $opts['auto_defer_js'] || $opts['auto_async_js'] ),
                'Lazy Load'      => (bool) ( $opts['lazy_images'] || $opts['lazy_iframes'] ),
                'Flying Pages'   => (bool) $opts['fly_enable'],
                'Flying Images'  => (bool) ( ( $opts['fly_images'] ?? 1 ) && $opts['spa_enable'] ),
                'SPA Navigation' => (bool) $opts['spa_enable'],
            ];

            // Detect common conflicting plugins.
            $conflicts    = [];
            $conflict_map = [
                'Smush'           => 'wp-smushit/wp-smush.php',
                'EWWW Image Opt.' => 'ewww-image-optimizer/ewww-image-optimizer.php',
                'Rocket Lazy Load'=> 'rocket-lazy-load/rocket-lazy-load.php',
                'WP Rocket'       => 'wp-rocket/wp-rocket.php',
                'Autoptimize'     => 'autoptimize/autoptimize.php',
                'W3 Total Cache'  => 'w3-total-cache/w3-total-cache.php',
                'WP Super Cache'  => 'wp-super-cache/wp-cache.php',
                'LiteSpeed Cache' => 'litespeed-cache/litespeed-cache.php',
            ];
            if ( function_exists( 'is_plugin_active' ) ) {
                foreach ( $conflict_map as $name => $file ) {
                    if ( is_plugin_active( $file ) ) {
                        $conflicts[] = $name;
                    }
                }
            }

            $front_end_url = add_query_arg( 'aio_diag', '1', home_url( '/' ) );
            ?>

            <!-- Server Status -->
            <div class="aio-card">
                <h2><?php esc_html_e( 'Server Status', 'aio-optimizer' ); ?></h2>
                <table class="aio-diag-table widefat striped">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'Plugin Version', 'aio-optimizer' ); ?></th>
                            <td><code><?php echo esc_html( AIO_VERSION ); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'PHP Version', 'aio-optimizer' ); ?></th>
                            <td><code><?php echo esc_html( PHP_VERSION ); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'WordPress Version', 'aio-optimizer' ); ?></th>
                            <td><code><?php echo esc_html( get_bloginfo( 'version' ) ); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Options saved', 'aio-optimizer' ); ?></th>
                            <td><?php echo get_option( AIO_OPTION ) ? '<span class="aio-status-ok">&#10003; Yes</span>' : '<span class="aio-status-off">&#9675; Using defaults</span>'; ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Output buffering', 'aio-optimizer' ); ?></th>
                            <td>
                                <?php
                                $ob = ob_get_level() > 0;
                                echo $ob
                                    ? '<span class="aio-status-ok">&#10003; Active (level: ' . esc_html( ob_get_level() ) . ')</span>'
                                    : '<span class="aio-status-fail">&#10007; Not active — Lazy Load may not work</span>';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Module Status -->
            <div class="aio-card">
                <h2><?php esc_html_e( 'Module Status', 'aio-optimizer' ); ?></h2>
                <table class="aio-diag-table widefat striped">
                    <tbody>
                        <?php foreach ( $diag_modules as $name => $enabled ) : ?>
                        <tr>
                            <th><?php echo esc_html( $name ); ?></th>
                            <td>
                                <?php if ( $enabled ) : ?>
                                    <span class="aio-status-ok">&#9679; <?php esc_html_e( 'Enabled', 'aio-optimizer' ); ?></span>
                                <?php else : ?>
                                    <span class="aio-status-off">&#9675; <?php esc_html_e( 'Disabled', 'aio-optimizer' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ( ! empty( $conflicts ) ) : ?>
                <div class="aio-notice aio-notice--warn">
                    <strong><?php esc_html_e( 'Potential Conflicts Detected:', 'aio-optimizer' ); ?></strong>
                    <?php echo esc_html( implode( ', ', $conflicts ) ); ?> —
                    <?php esc_html_e( 'these plugins may overlap with AIO Optimizer features.', 'aio-optimizer' ); ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- SPA Configuration -->
            <div class="aio-card">
                <h2><?php esc_html_e( 'SPA Configuration', 'aio-optimizer' ); ?></h2>
                <table class="aio-diag-table widefat striped">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'SPA Enabled', 'aio-optimizer' ); ?></th>
                            <td><?php echo $opts['spa_enable'] ? '<span class="aio-status-ok">&#10003; Yes</span>' : '<span class="aio-status-off">&#9675; No</span>'; ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Content Selector', 'aio-optimizer' ); ?></th>
                            <td><code><?php echo esc_html( $opts['spa_selector'] ); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Excluded Paths', 'aio-optimizer' ); ?></th>
                            <td><code><?php echo esc_html( $opts['spa_exclude'] ?: '—' ); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Flying Pages (Prefetch)', 'aio-optimizer' ); ?></th>
                            <td><?php echo $opts['fly_enable'] ? '<span class="aio-status-ok">&#10003; Enabled (delay: ' . esc_html( $opts['fly_delay'] ) . 'ms)</span>' : '<span class="aio-status-off">&#9675; Disabled</span>'; ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Front-end Diagnostics -->
            <div class="aio-card">
                <h2><?php esc_html_e( 'Front-end Diagnostics', 'aio-optimizer' ); ?></h2>
                <p class="description">
                    <?php esc_html_e( 'Open the front-end with a live diagnostic overlay to validate JavaScript modules are running correctly. Only visible to logged-in administrators.', 'aio-optimizer' ); ?>
                </p>
                <a href="<?php echo esc_url( $front_end_url ); ?>" target="_blank" class="button button-primary">
                    <?php esc_html_e( 'Open Front-end Diagnostics', 'aio-optimizer' ); ?>
                </a>

                <h3 style="margin-top:20px"><?php esc_html_e( 'Browser Console Checks', 'aio-optimizer' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Open DevTools (F12) on any front-end page and run these commands:', 'aio-optimizer' ); ?></p>
                <table class="aio-diag-table widefat" style="margin-top:8px">
                    <tbody>
                        <tr>
                            <th><?php esc_html_e( 'SPA config', 'aio-optimizer' ); ?></th>
                            <td><code>window.aioSpaConfig</code></td>
                            <td class="aio-diag-hint"><?php esc_html_e( 'Should show selector, exclude, home, adminPath', 'aio-optimizer' ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'SPA disabled?', 'aio-optimizer' ); ?></th>
                            <td><code>window.__aioSpaDisabled</code></td>
                            <td class="aio-diag-hint"><?php esc_html_e( 'Should return undefined (not true)', 'aio-optimizer' ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Flying Pages config', 'aio-optimizer' ); ?></th>
                            <td><code>window.aioFlyConfig</code></td>
                            <td class="aio-diag-hint"><?php esc_html_e( 'Should show delay and mobile settings', 'aio-optimizer' ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Prefetch cache size', 'aio-optimizer' ); ?></th>
                            <td><code>window.aioPageCache?.size</code></td>
                            <td class="aio-diag-hint"><?php esc_html_e( 'Increases as you hover over links', 'aio-optimizer' ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Content selector', 'aio-optimizer' ); ?></th>
                            <td><code>document.querySelector('<?php echo esc_js( $opts['spa_selector'] ); ?>')</code></td>
                            <td class="aio-diag-hint"><?php esc_html_e( 'Must return an element — if null, SPA cannot swap content', 'aio-optimizer' ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Captured AIO logs', 'aio-optimizer' ); ?></th>
                            <td><code>window.__aioLogs</code></td>
                            <td class="aio-diag-hint"><?php esc_html_e( 'Available when front-end diagnostics panel is open', 'aio-optimizer' ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php // Submit row — hidden on diagnostics tab ?>
        <div id="aio-submit-row"<?php echo $active === 'diagnostics' ? ' style="display:none"' : ''; ?>>
            <?php submit_button( __( 'Save Changes', 'aio-optimizer' ) ); ?>
        </div>

    </form>
</div>
