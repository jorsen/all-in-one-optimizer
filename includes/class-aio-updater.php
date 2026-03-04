<?php
defined( 'ABSPATH' ) || exit;

/**
 * GitHub-based auto-updater for All-in-One Optimizer.
 *
 * How it works:
 *  1. Hooks into WordPress's update transient to inject update info.
 *  2. Calls the GitHub releases API to get the latest version tag.
 *  3. If newer than AIO_VERSION, WordPress shows the standard update badge.
 *  4. User clicks "Update Now" from the Plugins page — WordPress downloads
 *     the zip from GitHub and installs it automatically.
 *  5. A source-selection filter renames the extracted folder to the correct
 *     slug so WordPress doesn't treat it as a new plugin.
 *
 * To publish an update:
 *  1. Bump AIO_VERSION in all-in-one-optimizer.php.
 *  2. Push to GitHub.
 *  3. Create a GitHub Release with tag v{version} (e.g. v1.1.0).
 *  4. WordPress sites will see the update within 12 hours (or immediately
 *     after clicking "Check for Updates" in Settings → AIO Optimizer).
 */
class AIO_Updater {

    const GITHUB_USER  = 'jorsen';
    const GITHUB_REPO  = 'all-in-one-optimizer';
    const PLUGIN_SLUG  = 'all-in-one-optimizer';
    const PLUGIN_FILE  = 'all-in-one-optimizer/all-in-one-optimizer.php';
    const TRANSIENT    = 'aio_github_release';

    public function init(): void {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'inject_update' ] );
        add_filter( 'site_transient_update_plugins',         [ $this, 'inject_update_read' ] );
        add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
        add_filter( 'upgrader_source_selection',             [ $this, 'fix_folder_name' ], 10, 4 );
        add_action( 'upgrader_process_complete',             [ $this, 'clear_transient' ], 10, 2 );

        // Enable WordPress automatic background updates for this plugin.
        add_filter( 'auto_update_plugin', [ $this, 'enable_auto_update' ], 10, 2 );

        // Force-write update data into the stored transient on every admin load
        // so the Plugins page always shows the Update Now button.
        add_action( 'admin_init', [ $this, 'force_inject_stored_transient' ] );

        // Handle manual "Check for Updates" button.
        add_action( 'admin_post_aio_check_update', [ $this, 'handle_manual_check' ] );
    }

    // -------------------------------------------------------------------------
    // Enable WordPress automatic background updates
    // -------------------------------------------------------------------------

    public function enable_auto_update( $update, $item ): bool {
        if ( isset( $item->plugin ) && $item->plugin === self::PLUGIN_FILE ) {
            return true;
        }
        return (bool) $update;
    }

    // -------------------------------------------------------------------------
    // Directly write update data into the stored update_plugins transient.
    // This guarantees the Plugins page shows Update Now without waiting for
    // WordPress's next background check cycle.
    // -------------------------------------------------------------------------

    public function force_inject_stored_transient(): void {
        $release = $this->get_release();
        if ( ! $release || ! version_compare( $release['version'], AIO_VERSION, '>' ) ) {
            return;
        }

        $transient = get_site_transient( 'update_plugins' );
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }
        if ( ! isset( $transient->response ) )  { $transient->response  = []; }
        if ( ! isset( $transient->no_update ) ) { $transient->no_update = []; }
        if ( ! isset( $transient->checked ) )   { $transient->checked   = []; }

        // Already injected — no need to write again.
        if ( isset( $transient->response[ self::PLUGIN_FILE ] ) &&
             $transient->response[ self::PLUGIN_FILE ]->new_version === $release['version'] ) {
            return;
        }

        $transient->checked[ self::PLUGIN_FILE ]  = AIO_VERSION;
        $transient->response[ self::PLUGIN_FILE ] = (object) [
            'id'           => 'github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'slug'         => self::PLUGIN_SLUG,
            'plugin'       => self::PLUGIN_FILE,
            'new_version'  => $release['version'],
            'url'          => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'package'      => $release['zip_url'],
            'tested'       => get_bloginfo( 'version' ),
            'requires_php' => '7.4',
            'icons'        => [],
            'banners'      => [],
        ];
        unset( $transient->no_update[ self::PLUGIN_FILE ] );

        set_site_transient( 'update_plugins', $transient );
    }

    // -------------------------------------------------------------------------
    // Fetch latest release from GitHub API (cached for 12 hours)
    // -------------------------------------------------------------------------

    public function get_release(): ?array {
        $cached = get_transient( self::TRANSIENT );
        if ( false !== $cached ) {
            return $cached ?: null; // empty string = "checked, no release found"
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            self::GITHUB_USER,
            self::GITHUB_REPO
        );

        $response = wp_remote_get( $api_url, [
            'timeout'    => 10,
            'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
            'headers'    => [ 'Accept' => 'application/vnd.github.v3+json' ],
        ] );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            set_transient( self::TRANSIENT, '', 30 * MINUTE_IN_SECONDS ); // Back off on failure.
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( empty( $data['tag_name'] ) ) {
            set_transient( self::TRANSIENT, '', HOUR_IN_SECONDS );
            return null;
        }

        $tag     = $data['tag_name'];                     // e.g. "v1.1.0"
        $version = ltrim( $tag, 'vV' );                  // e.g. "1.1.0"

        $release = [
            'version'   => $version,
            'tag'       => $tag,
            'zip_url'   => sprintf(
                'https://github.com/%s/%s/archive/refs/tags/%s.zip',
                self::GITHUB_USER,
                self::GITHUB_REPO,
                $tag
            ),
            'details'   => sprintf(
                'https://github.com/%s/%s/releases/tag/%s',
                self::GITHUB_USER,
                self::GITHUB_REPO,
                $tag
            ),
            'changelog' => $data['body']         ?? '',
            'published' => $data['published_at'] ?? '',
        ];

        set_transient( self::TRANSIENT, $release, 12 * HOUR_IN_SECONDS );
        return $release;
    }

    // -------------------------------------------------------------------------
    // Inject update data into WordPress's plugin update transient
    // Called on pre_set_ (WP update check) — requires checked[] to be present.
    // -------------------------------------------------------------------------

    public function inject_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }
        return $this->do_inject( $transient );
    }

    // -------------------------------------------------------------------------
    // Inject update data when the transient is READ (Plugins page).
    // No checked[] guard — initialises the transient object if needed.
    // -------------------------------------------------------------------------

    public function inject_update_read( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }
        if ( ! isset( $transient->response ) ) {
            $transient->response  = [];
        }
        if ( ! isset( $transient->no_update ) ) {
            $transient->no_update = [];
        }
        // WordPress uses checked[] to know the currently installed version.
        if ( ! isset( $transient->checked ) ) {
            $transient->checked = [];
        }
        $transient->checked[ self::PLUGIN_FILE ] = AIO_VERSION;
        return $this->do_inject( $transient );
    }

    // -------------------------------------------------------------------------
    // Shared injection logic
    // -------------------------------------------------------------------------

    private function do_inject( $transient ) {
        $release = $this->get_release();
        if ( ! $release ) {
            return $transient;
        }

        if ( version_compare( $release['version'], AIO_VERSION, '>' ) ) {
            $transient->response[ self::PLUGIN_FILE ] = (object) [
                'id'            => 'github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
                'slug'          => self::PLUGIN_SLUG,
                'plugin'        => self::PLUGIN_FILE,
                'new_version'   => $release['version'],
                'url'           => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
                'package'       => $release['zip_url'],
                'tested'        => get_bloginfo( 'version' ),
                'requires_php'  => '7.4',
                'icons'         => [],
                'banners'       => [],
            ];
        } else {
            // Mark as "no update available" so WP doesn't keep pinging.
            $transient->no_update[ self::PLUGIN_FILE ] = (object) [
                'id'          => 'github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
                'slug'        => self::PLUGIN_SLUG,
                'plugin'      => self::PLUGIN_FILE,
                'new_version' => $release['version'],
                'url'         => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            ];
        }

        return $transient;
    }

    // -------------------------------------------------------------------------
    // Provide plugin info for the "View version details" popup
    // -------------------------------------------------------------------------

    public function plugin_info( $result, string $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }
        if ( ! isset( $args->slug ) || $args->slug !== self::PLUGIN_SLUG ) {
            return $result;
        }

        $release = $this->get_release();
        if ( ! $release ) {
            return $result;
        }

        $changelog = ! empty( $release['changelog'] )
            ? '<pre>' . esc_html( $release['changelog'] ) . '</pre>'
            : '<p>See <a href="' . esc_url( $release['details'] ) . '" target="_blank">GitHub Release</a> for details.</p>';

        return (object) [
            'name'          => 'All-in-One Optimizer',
            'slug'          => self::PLUGIN_SLUG,
            'version'       => $release['version'],
            'author'        => 'Jorsen Mejia',
            'homepage'      => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'requires'      => '5.8',
            'requires_php'  => '7.4',
            'last_updated'  => $release['published'],
            'download_link' => $release['zip_url'],
            'sections'      => [
                'description' => '<p>Debloat, Autoptimize (defer/async), Image Lazy Load, Flying Pages prefetch, Flying Images transitions, and SPA navigation — all in one lightweight plugin.</p>',
                'changelog'   => $changelog,
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Rename the extracted GitHub zip folder to the correct plugin slug.
    //
    // GitHub archive zips extract to e.g. "all-in-one-optimizer-1.1.0/"
    // but WordPress expects "all-in-one-optimizer/".
    // -------------------------------------------------------------------------

    public function fix_folder_name( string $source, string $remote_source, $upgrader, $hook_extra = [] ): string {
        global $wp_filesystem;

        // Only act on our own plugin update.
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::PLUGIN_FILE ) {
            return $source;
        }

        $corrected = trailingslashit( $remote_source ) . self::PLUGIN_SLUG . '/';

        if ( $source !== $corrected && $wp_filesystem->is_dir( $source ) ) {
            if ( $wp_filesystem->is_dir( $corrected ) ) {
                $wp_filesystem->delete( $corrected, true );
            }
            $wp_filesystem->move( $source, $corrected );
            return $corrected;
        }

        return $source;
    }

    // -------------------------------------------------------------------------
    // Clear cached release data after a successful plugin update
    // -------------------------------------------------------------------------

    public function clear_transient( $upgrader, array $options ): void {
        if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
            $plugins = $options['plugins'] ?? [];
            if ( in_array( self::PLUGIN_FILE, (array) $plugins, true ) ) {
                delete_transient( self::TRANSIENT );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Manual "Check for Updates" button handler
    // -------------------------------------------------------------------------

    public function handle_manual_check(): void {
        if ( ! current_user_can( 'update_plugins' ) ) {
            wp_die( esc_html__( 'Permission denied.', 'aio-optimizer' ) );
        }
        check_admin_referer( 'aio_check_update' );

        delete_transient( self::TRANSIENT );

        // Also flush WordPress's built-in plugin update transient so the
        // Plugins page reflects the new state immediately.
        delete_site_transient( 'update_plugins' );

        wp_safe_redirect( add_query_arg(
            [ 'page' => 'aio-optimizer', 'aio_checked' => '1' ],
            admin_url( 'options-general.php' )
        ) );
        exit;
    }
}
