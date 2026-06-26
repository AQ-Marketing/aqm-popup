<?php
/**
 * AQM Popup Updater
 *
 * Self-contained GitHub Tags updater (matches the pattern used by aqm-blog-post-feed).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AQM_Popup_Updater {
    private $file;
    private $username;
    private $repository;
    private $access_token;
    private $plugin_data;
    private $plugin_basename;

    public function __construct( $file, $username, $repository, $access_token = '' ) {
        $this->file         = $file;
        $this->username     = $username;
        $this->repository   = $repository;
        $this->access_token = $access_token;

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $this->plugin_data     = get_plugin_data( $this->file );
        $this->plugin_basename = plugin_basename( $this->file );

        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_pre_install', array( $this, 'pre_install' ), 10, 2 );
        add_action( 'upgrader_process_complete', array( $this, 'post_install' ), 10, 2 );
        add_filter( 'upgrader_source_selection', array( $this, 'fix_directory_name' ), 10, 4 );
        add_action( 'admin_init', array( $this, 'maybe_reactivate_plugin' ) );

        aqm_popup_debug_log( 'Updater initialized for ' . $this->repository . ' (v' . $this->plugin_data['Version'] . ')' );
    }

    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $update_data    = $this->get_github_update_data();
        $latest_version = $update_data ? ltrim( $update_data->tag_name, 'v' ) : '';

        // Compare against the version WordPress currently sees installed on disk
        // (via $transient->checked), NOT $this->plugin_data['Version']. That
        // property is captured in the constructor at the START of the request;
        // after an update swaps the files mid-request it is stale, so comparing
        // against it re-offers the update we just installed — the cause of the
        // "have to update twice" loop. $transient->checked already reflects the
        // freshly-installed version, so the second offer never appears.
        $installed_version = isset( $transient->checked[ $this->plugin_basename ] )
            ? $transient->checked[ $this->plugin_basename ]
            : $this->plugin_data['Version'];

        if ( $update_data && version_compare( $installed_version, $latest_version, '<' ) ) {
            error_log( '[AQM POPUP UPDATER] New version available: ' . $update_data->tag_name );

            $plugin_info              = new stdClass();
            $plugin_info->slug        = $this->repository;
            $plugin_info->plugin      = $this->plugin_basename;
            $plugin_info->new_version = $latest_version;
            $plugin_info->url         = $update_data->html_url;
            $plugin_info->package     = $update_data->zipball_url;

            if ( ! empty( $this->access_token ) ) {
                $plugin_info->package = add_query_arg( array( 'access_token' => $this->access_token ), $plugin_info->package );
            }

            $transient->response[ $this->plugin_basename ] = $plugin_info;
        } else {
            // No update (or we're already current) — make sure no stale
            // "update available" entry lingers in the transient.
            unset( $transient->response[ $this->plugin_basename ] );
        }

        return $transient;
    }

    private function get_github_update_data( $force_check = false ) {
        $cache_key = 'aqm_popup_github_data_' . md5( $this->username . $this->repository );
        $cache     = get_transient( $cache_key );

        if ( false !== $cache && ! $force_check ) {
            return $cache;
        }

        $api_url = "https://api.github.com/repos/{$this->username}/{$this->repository}/tags";

        $headers = array(
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' ),
        );
        if ( ! empty( $this->access_token ) ) {
            $headers['Authorization'] = 'token ' . $this->access_token;
        }

        $response = wp_remote_get( $api_url, array(
            'headers' => $headers,
            'timeout' => 30,
        ) );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            error_log( '[AQM POPUP UPDATER] Error getting update data: ' . wp_remote_retrieve_response_message( $response ) );
            return false;
        }

        $tags = json_decode( wp_remote_retrieve_body( $response ) );
        if ( empty( $tags ) || ! is_array( $tags ) ) {
            return false;
        }

        $latest_tag        = $tags[0];
        $data              = new stdClass();
        $data->tag_name    = $latest_tag->name;
        $data->html_url    = "https://github.com/{$this->username}/{$this->repository}/releases/tag/{$latest_tag->name}";
        $data->zipball_url = "https://github.com/{$this->username}/{$this->repository}/archive/refs/tags/{$latest_tag->name}.zip";
        $data->published_at = isset( $latest_tag->published_at ) ? $latest_tag->published_at : '';
        $data->body        = '';

        set_transient( $cache_key, $data, 6 * HOUR_IN_SECONDS );
        return $data;
    }

    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || ! isset( $args->slug ) || $args->slug !== $this->repository ) {
            return $result;
        }
        $update_data = $this->get_github_update_data();
        if ( ! $update_data ) {
            return $result;
        }

        $plugin_info               = new stdClass();
        $plugin_info->name         = $this->plugin_data['Name'];
        $plugin_info->slug         = $this->repository;
        $plugin_info->version      = ltrim( $update_data->tag_name, 'v' );
        $plugin_info->author       = $this->plugin_data['Author'];
        $plugin_info->homepage     = $this->plugin_data['PluginURI'];
        $plugin_info->requires     = '5.2';
        $plugin_info->tested       = '6.8';
        $plugin_info->downloaded   = 0;
        $plugin_info->last_updated = $update_data->published_at;
        $plugin_info->sections     = array(
            'description' => $this->plugin_data['Description'],
            'changelog'   => ! empty( $update_data->body ) ? $update_data->body : 'No changelog provided.',
        );
        $plugin_info->download_link = $update_data->zipball_url;
        if ( ! empty( $this->access_token ) ) {
            $plugin_info->download_link = add_query_arg( array( 'access_token' => $this->access_token ), $plugin_info->download_link );
        }
        return $plugin_info;
    }

    public function pre_install( $return, $hook_extra ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
            return $return;
        }
        if ( is_plugin_active( $this->plugin_basename ) ) {
            set_transient( 'aqm_popup_was_active', true, 5 * MINUTE_IN_SECONDS );
            update_option( 'aqm_popup_was_active', true );
        }
        return $return;
    }

    public function post_install( $upgrader_object, $options ) {
        if ( ! isset( $options['action'], $options['type'] ) || 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
            return;
        }
        if ( ! isset( $options['plugins'] ) || ! in_array( $this->plugin_basename, $options['plugins'], true ) ) {
            return;
        }

        // Drop the cached GitHub tag data so the next update check fetches fresh
        // and recomputes against the just-installed version.
        delete_transient( 'aqm_popup_github_data_' . md5( $this->username . $this->repository ) );

        set_transient( 'aqm_popup_reactivate', true, 5 * MINUTE_IN_SECONDS );

        if ( get_transient( 'aqm_popup_was_active' ) ) {
            delete_transient( 'aqm_popup_was_active' );

            if ( ! function_exists( 'activate_plugin' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $result = activate_plugin( $this->plugin_basename );
            if ( is_wp_error( $result ) ) {
                error_log( '[AQM POPUP UPDATER] Reactivation failed: ' . $result->get_error_message() );
            } else {
                delete_transient( 'aqm_popup_reactivate' );
                set_transient( 'aqm_popup_reactivated', true, 30 );
            }
            wp_clean_plugins_cache( true );
        }
    }

    public function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
            return $source;
        }
        $expected = dirname( $this->plugin_basename );
        $current  = basename( $source );

        if ( $current === $expected ) {
            return $source;
        }

        $new_source = trailingslashit( dirname( $source ) ) . trailingslashit( $expected );

        if ( is_dir( $new_source ) ) {
            $fs = $this->get_filesystem();
            if ( $fs ) {
                $fs->delete( $new_source, true );
            }
        }

        if ( @rename( $source, $new_source ) ) {
            return $new_source;
        }

        $fs = $this->get_filesystem();
        if ( $fs && $fs->move( $source, $new_source, true ) ) {
            return $new_source;
        }

        error_log( '[AQM POPUP UPDATER] Directory rename failed for ' . $source );
        return $source;
    }

    public function maybe_reactivate_plugin() {
        if ( ! get_transient( 'aqm_popup_reactivate' ) ) {
            return;
        }
        delete_transient( 'aqm_popup_reactivate' );

        if ( ! function_exists( 'activate_plugin' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $result = activate_plugin( $this->plugin_basename );
        if ( is_wp_error( $result ) ) {
            error_log( '[AQM POPUP UPDATER] Reactivation failed: ' . $result->get_error_message() );
        } else {
            set_transient( 'aqm_popup_reactivated', true, 30 );
        }
        wp_clean_plugins_cache( true );
    }

    private function get_filesystem() {
        global $wp_filesystem;
        if ( empty( $wp_filesystem ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        return $wp_filesystem;
    }
}
