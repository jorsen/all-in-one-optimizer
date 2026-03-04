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
];

// Build tab URLs explicitly so they survive form-save redirects.
$base_url = admin_url( 'options-general.php?page=aio-optimizer' );

// Check if a manual update check was just triggered.
$just_checked = ! empty( $_GET['aio_checked'] );

// Get cached release info for version status (no new API call here).
$release = class_exists( 'AIO_Updater' ) ? ( new AIO_Updater() )->get_release() : null;
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
                <span class="aio-update-badge">
                    <?php
                    printf(
                        /* translators: %s = new version number */
                        esc_html__( 'Update available: %s', 'aio-optimizer' ),
                        esc_html( $release['version'] )
                    );
                    ?>
                    &mdash;
                    <a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">
                        <?php esc_html_e( 'Go to Plugins page', 'aio-optimizer' ); ?>
                    </a>
                </span>
            <?php elseif ( $just_checked ) : ?>
                <span class="aio-up-to-date"><?php esc_html_e( 'You are up to date.', 'aio-optimizer' ); ?></span>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                <?php wp_nonce_field( 'aio_check_update' ); ?>
                <input type="hidden" name="action" value="aio_check_update">
                <button type="submit" class="button button-small aio-check-update-btn">
                    <?php esc_html_e( 'Check for Updates', 'aio-optimizer' ); ?>
                </button>
            </form>
        </div>
    </div>

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
          // TABS
          // ================================================================ ?>
    <nav class="aio-tabs">
        <?php foreach ( $tabs as $slug => $label ) : ?>
            <a href="<?php echo esc_url( $base_url . '&tab=' . $slug ); ?>"
               class="aio-tab<?php echo $active === $slug ? ' aio-tab--active' : ''; ?>">
                <?php echo esc_html( $label ); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="options.php">
        <?php settings_fields( 'aio_optimizer_group' ); ?>

        <?php // ================================================================
              // TAB: DEBLOAT
              // ================================================================ ?>
        <?php if ( 'debloat' === $active ) : ?>
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

        <?php // ================================================================
              // TAB: AUTOPTIMIZE
              // ================================================================ ?>
        <?php elseif ( 'autoptimize' === $active ) : ?>
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

        <?php // ================================================================
              // TAB: LAZY LOAD
              // ================================================================ ?>
        <?php elseif ( 'lazyload' === $active ) : ?>
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

        <?php // ================================================================
              // TAB: FLYING PAGES
              // ================================================================ ?>
        <?php elseif ( 'flyingpages' === $active ) : ?>
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

        <?php // ================================================================
              // TAB: FLYING IMAGES
              // ================================================================ ?>
        <?php elseif ( 'flyingimages' === $active ) : ?>
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

        <?php // ================================================================
              // TAB: SPA
              // ================================================================ ?>
        <?php elseif ( 'spa' === $active ) : ?>
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
                        <input type="text" id="spa_selector"
                               name="<?php echo esc_attr( AIO_OPTION . '[spa_selector]' ); ?>"
                               value="<?php echo esc_attr( $opts['spa_selector'] ); ?>"
                               class="regular-text">
                        <p class="description"><?php esc_html_e( 'Comma-separated CSS selectors (first match wins). E.g. #content, main, .site-main', 'aio-optimizer' ); ?></p>
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
        <?php endif; ?>

        <?php submit_button( __( 'Save Changes', 'aio-optimizer' ) ); ?>
    </form>
</div>
