<?php
defined( 'ABSPATH' ) || exit;

$opts    = aio_get_options();
$active  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'debloat';
$tabs    = [
    'debloat'       => __( 'Debloat', 'aio-optimizer' ),
    'autoptimize'   => __( 'Autoptimize', 'aio-optimizer' ),
    'lazyload'      => __( 'Lazy Load', 'aio-optimizer' ),
    'flyingpages'   => __( 'Flying Pages', 'aio-optimizer' ),
    'flyingimages'  => __( 'Flying Images', 'aio-optimizer' ),
    'spa'           => __( 'SPA', 'aio-optimizer' ),
];
?>
<div class="wrap aio-wrap">
    <h1><?php esc_html_e( 'All-in-One Optimizer', 'aio-optimizer' ); ?></h1>

    <nav class="aio-tabs">
        <?php foreach ( $tabs as $slug => $label ) : ?>
            <a href="<?php echo esc_url( add_query_arg( 'tab', $slug ) ); ?>"
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
                    'debloat_emoji'          => __( 'Remove Emoji scripts & styles', 'aio-optimizer' ),
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
                    <th scope="row"><?php echo wp_kses( $label, [ 'meta' => [], 'a' => [] ] ); ?></th>
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
            <p class="description"><?php esc_html_e( 'Optimize how scripts and styles are loaded — no file rewriting required. Excludes can be configured per-handle.', 'aio-optimizer' ); ?></p>
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
            <h2><?php esc_html_e( 'Image & iFrame Lazy Load', 'aio-optimizer' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Replaces src with data-src and loads via IntersectionObserver. Falls back to immediate load if IntersectionObserver is unavailable. &lt;noscript&gt; tags added for non-JS browsers.', 'aio-optimizer' ); ?></p>
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
                            <?php esc_html_e( 'Skip the first image on the page (usually the LCP / hero image)', 'aio-optimizer' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <div class="aio-notice aio-notice--info">
                <strong><?php esc_html_e( 'Note:', 'aio-optimizer' ); ?></strong>
                <?php esc_html_e( 'Lazy load is automatically disabled if a conflicting plugin (Smush, EWWW, Rocket Lazy Load, etc.) is detected.', 'aio-optimizer' ); ?>
            </div>
        </div>

        <?php // ================================================================
              // TAB: FLYING PAGES
              // ================================================================ ?>
        <?php elseif ( 'flyingpages' === $active ) : ?>
        <div class="aio-card">
            <h2><?php esc_html_e( 'Flying Pages — Link Prefetch', 'aio-optimizer' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Uses the fetch API to preload pages when a user hovers over links. Pre-warmed pages are consumed instantly by the SPA module — no second request needed.', 'aio-optimizer' ); ?></p>
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
                        <p class="description"><?php esc_html_e( 'Milliseconds to wait after hover before prefetch fires. 0 = instant. Default: 65.', 'aio-optimizer' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable on mobile', 'aio-optimizer' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[fly_mobile]' ); ?>" value="1" <?php checked( 1, $opts['fly_mobile'] ); ?>>
                            <?php esc_html_e( 'Prefetch on touchstart for mobile devices (uses more data)', 'aio-optimizer' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
            <div class="aio-notice aio-notice--info">
                <strong><?php esc_html_e( 'Performance:', 'aio-optimizer' ); ?></strong>
                <?php esc_html_e( 'Prefetch uses requestIdleCallback so it never blocks rendering. Automatically skipped on Save-Data connections and slow 2G networks.', 'aio-optimizer' ); ?>
            </div>
        </div>

        <?php // ================================================================
              // TAB: FLYING IMAGES
              // ================================================================ ?>
        <?php elseif ( 'flyingimages' === $active ) : ?>
        <div class="aio-card">
            <h2><?php esc_html_e( 'Flying Images — Shared Element Transitions', 'aio-optimizer' ); ?></h2>
            <p class="description"><?php esc_html_e( 'When navigating between pages via SPA, images that appear on both pages animate from their old position to their new position — creating a seamless visual continuity effect. Works with any theme by detecting &lt;img&gt; tags dynamically.', 'aio-optimizer' ); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable Flying Images', 'aio-optimizer' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr( AIO_OPTION . '[fly_images]' ); ?>" value="1" <?php checked( 1, $opts['fly_images'] ?? 1 ); ?>>
                            <?php esc_html_e( 'Animate shared images between SPA page transitions', 'aio-optimizer' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Requires SPA navigation to be enabled. Automatically respects prefers-reduced-motion.', 'aio-optimizer' ); ?></p>
                    </td>
                </tr>
            </table>
            <div class="aio-notice aio-notice--info">
                <strong><?php esc_html_e( 'How it works:', 'aio-optimizer' ); ?></strong>
                <?php esc_html_e( 'Images are matched by src across pages. When the same image appears in a new page at a different size or position, it flies from the old location to the new one using a CSS transform animation. Image bitmaps are also cached in memory to prevent re-decoding.', 'aio-optimizer' ); ?>
            </div>
        </div>

        <?php // ================================================================
              // TAB: SPA
              // ================================================================ ?>
        <?php elseif ( 'spa' === $active ) : ?>
        <div class="aio-card">
            <h2><?php esc_html_e( 'SPA Navigation', 'aio-optimizer' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Fetch and swap page content without a full browser reload. Includes smooth fade transitions, a progress bar, and automatic fallback to normal navigation on errors.', 'aio-optimizer' ); ?></p>
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
                        <p class="description"><?php esc_html_e( 'Comma-separated CSS selectors for the main content area (first match wins). E.g. #content, main, .site-main', 'aio-optimizer' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="spa_exclude"><?php esc_html_e( 'Exclude URL patterns', 'aio-optimizer' ); ?></label></th>
                    <td>
                        <textarea id="spa_exclude"
                                  name="<?php echo esc_attr( AIO_OPTION . '[spa_exclude]' ); ?>"
                                  rows="5" class="large-text"><?php echo esc_textarea( $opts['spa_exclude'] ); ?></textarea>
                        <p class="description"><?php esc_html_e( 'Comma-separated URL path prefixes to exclude from SPA navigation. E.g. /wp-admin, /cart, /checkout', 'aio-optimizer' ); ?></p>
                    </td>
                </tr>
            </table>
            <div class="aio-notice aio-notice--info">
                <strong><?php esc_html_e( 'Escape hatches:', 'aio-optimizer' ); ?></strong>
                <?php esc_html_e( 'Add data-no-spa to any &lt;a&gt; tag to skip SPA for that link. SPA automatically disables itself if a JS error occurs, ensuring the site always works.', 'aio-optimizer' ); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php submit_button( __( 'Save Changes', 'aio-optimizer' ) ); ?>
    </form>
</div>
