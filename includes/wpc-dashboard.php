<?php
if ( ! class_exists( 'WPcleverDashboard' ) ) {
	class WPcleverDashboard {
		function __construct() {
			add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		function enqueue_scripts() {
			wp_enqueue_style( 'wpc-dashboard', WPC_URI . 'assets/css/dashboard.css' );
		}

		function add_dashboard_widget() {
			wp_add_dashboard_widget( 'wpclever_dashboard_widget', 'WPclever.net Plugins', array(
				&$this,
				'dashboard_widget_content'
			) );
		}

		function dashboard_widget_content() {
			if ( false === ( $plugins_arr = get_transient( 'wpclever_plugins' ) ) ) {
				$args    = (object) array(
					'author'   => 'wpclever',
					'per_page' => '20',
					'page'     => '1',
					'fields'   => array( 'slug', 'name', 'version', 'downloaded', 'active_installs' )
				);
				$request = array( 'action' => 'query_plugins', 'timeout' => 15, 'request' => serialize( $args ) );
				//https://codex.wordpress.org/WordPress.org_API
				$url      = 'http://api.wordpress.org/plugins/info/1.0/';
				$response = wp_remote_post( $url, array( 'body' => $request ) );
				if ( ! is_wp_error( $response ) ) {
					$plugins_arr = array();
					$plugins     = unserialize( $response['body'] );
					if ( isset( $plugins->plugins ) && ( count( $plugins->plugins ) > 0 ) ) {
						foreach ( $plugins->plugins as $pl ) {
							$plugins_arr[] = array(
								'slug'            => $pl->slug,
								'name'            => $pl->name,
								'version'         => $pl->version,
								'downloaded'      => $pl->downloaded,
								'active_installs' => $pl->active_installs
							);
						}
					}
					set_transient( 'wpclever_plugins', $plugins_arr, 24 * HOUR_IN_SECONDS );
				}
			}
			if ( is_array( $plugins_arr ) && ( count( $plugins_arr ) > 0 ) ) {
				array_multisort( array_column( $plugins_arr, 'active_installs' ), SORT_DESC, $plugins_arr );
				foreach ( $plugins_arr as $pl ) {
					echo '<div class="item"><a href="https://wordpress.org/plugins/' . $pl['slug'] . '/"><img src="https://ps.w.org/' . $pl['slug'] . '/assets/icon-128x128.png?t=' . current_time( 'd' ) . '"/><span class="title">' . $pl['name'] . '</span><br/><span class="info">Version ' . $pl['version'] . '</span></a></div>';
				}
			} else {
				echo 'https://wpclever.net';
			}
		}
	}

	new WPcleverDashboard();
}