<?php
/**
 * Get plugins information api url
 *
 * @return string Url address of the API server.
 */
function plannedsoft_core_get_api_url( $path = '' ) {
	return apply_filters( 'plannedsoft_core_api_url', 'https://download.plannedsoft.com/wp-json/plannedsoft-server/v1' ) . $path;
}

function plannedsoft_core_get_plugin_download_link( $slug ) {
	return plannedsoft_core_get_api_url( "/download/" ) . plannedsoft_core_get_license_key() . "/{$slug}.zip?wp_url=" . site_url();
}

/**
 * Get plugin basepath using folder name
 *
 * @param  string $slug Plugin slug/folder name.
 * @return string       Plugin basepath
 */
function plannedsoft_core_get_plugin_file( $slug ) {
	$installed_plugin = get_plugins( '/' . $slug );
	if ( empty( $installed_plugin ) ) {
		return false;
	}

	$key = array_keys( $installed_plugin );
	$key = reset( $key );
	return $slug . '/' . $key;
}

/**
 * Check if plugin active based on folder name.
 *
 * @param  string $slug Plugin slug/folder name.
 * @return boolean      True/False based on active status.
 */
function plannedsoft_core_is_module_active( $slug ) {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';
	return is_plugin_active( plannedsoft_core_get_plugin_file( $slug ) );
}

/**
 * Check if plugin folder exists.
 *
 * @param  string $slug Plugin slug/folder name.
 */
function plannedsoft_core_is_module_installed( $slug ) {
	return is_dir( WP_PLUGIN_DIR . '/' . $slug );
}

/**
 * Get admin page menus displayed on the left side in blue.
 *
 * @return array Array of menu items.
 */
function plannedsoft_core_get_admin_menu_items() {
	$items = array(
		array(
			'name'      => __( 'Modules', 'plannedsoft-core' ),
			'url' 		=> admin_url( 'admin.php?page=plannedsoft-core' ),
			'icon'      => 'ti-package',
			'class'     => isset( $_REQUEST['page'] ) && 'plannedsoft-core' === $_REQUEST['page'] ? 'menu-active' : '',
			'priority'  => 10
		),
		array(
			'name'      => __( 'Activate', 'plannedsoft-core' ),
			'url' 		=> admin_url( 'admin.php?page=plannedsoft-core-license' ),
			'icon'      => 'ti-lock',
			'class'     => isset( $_REQUEST['page'] ) && 'plannedsoft-core-license' === $_REQUEST['page'] ? 'menu-active' : '',
			'priority'  => 9999
		)
	);

	$items = apply_filters( 'plannedsoft_core_admin_menu_items', $items );

	uasort( $items, 'plannedsoft_core_order_by_priority' );

	return $items;
}

/**
 * Order items by priority
 *
 * @param  array $a [description]
 * @param  array $b [description]
 * @return interger [description]
 */
function plannedsoft_core_order_by_priority( $a, $b ) {
	if ( ! isset( $a['priority'] ) || ! isset( $b['priority'] ) ) {
		return -1;
	}
	if ( $a['priority'] == $b['priority'] ) {
		return 0;
	}
	if ( $a['priority'] < $b['priority'] ) {
		return -1;
	}
	return 1;
}


function plannedsoft_core_activate_license( $license_key, $plugin_slug = 'plannedsoft-core' ) {
	$data = plannedsoft_core_api_license_data( $license_key, $plugin_slug );
	if ( is_wp_error( $data ) ) {
		return $data;
	}

	if ( $data->installs_allowed <= $data->installs_active ) {
		return new WP_Error( 'limit_reached', __( 'License usage limit reached. You can not activate this license.' ) );
	}
	# plannedsoft_core_Utils::d( $data );

	update_option( '_plannedsoft_core_license_key', $license_key );
	update_option( '_plannedsoft_core_license_data', $data );

	plannedsoft_core_maybe_send_plugins_data();

	return $data;
}

function plannedsoft_core_deactivate_license() {
	$data = plannedsoft_core_api_license_data( plannedsoft_core_get_license_key() );
#	if ( is_wp_error( $data ) ) {
#		return $data;
#	}

	delete_option( '_plannedsoft_core_license_key' );
	delete_option( '_plannedsoft_core_license_data' );

	plannedsoft_core_maybe_send_plugins_data();

	return true;
}

function plannedsoft_core_get_license_data() {
	return get_option( '_plannedsoft_core_license_data' );
}

function plannedsoft_core_get_license_key() {
	return get_option( '_plannedsoft_core_license_key' );
}

function plannedsoft_core_log( $message, $context = array() ) {
	do_action(
		'w4_loggable_log',
		// string, usually a name from where you are storing this log
		'Plannedsoft Core',
		// string, log message
		$message,
		// array, a data that can be replaced with placeholder inside message.
		$context
	);
}

function plannedsoft_core_get_plugins_data() {
	include_once ABSPATH . 'wp-admin/includes/plugin.php';

	$installed_plugins = get_plugins( '/' );
	if ( empty( $installed_plugins ) ) {
		return array();
	}

	$our_plugins = wp_list_filter(
		$installed_plugins,
		array(
			'Author'    => 'Plannedsoft',
			'AuthorURI' => 'https://plannedsoft.com'
		)
	);
	if ( empty( $our_plugins ) ) {
		return array();
	}

	$plugins_data = array();
	foreach ( $our_plugins as $basename => $plugin ) {
		$status = 'installed';
		if ( is_plugin_active( $basename ) ) {
			$status = 'active';
		}

		$plugins_data[] = array(
			'slug'    => dirname( $basename ),
			'version' => $plugin['Version'],
			'status'  => $status
		);
	}

	return $plugins_data;
}

/**
 * Get license data.
 *
 * @return array Array of available modules.
 */
function plannedsoft_core_maybe_send_plugins_data() {
	$plugins_data = plannedsoft_core_get_plugins_data();

	$data = array(
		'plugins'    => $plugins_data,
		'wp_url'     => esc_url( site_url() ),
		'wp_locale'  => get_locale(),
		'wp_version' => get_bloginfo( 'version', 'display' ),
	);

	// Add license if present.
	if ( plannedsoft_core_get_license_key() ) {
		$data['license_key'] = plannedsoft_core_get_license_key();
	}

	// Check if we had already sent that data.
	if ( $data === get_transient( 'plannedsoft_core_plugins_data' ) ) {
		return true;
	}

	plannedsoft_core_log( 'plannedsoft_core_maybe_send_plugins_data', array(
		'data' => $data
	));

	// Store lastest plugin data payload.
	set_transient( 'plannedsoft_core_plugins_data', $data, DAY_IN_SECONDS );

	$request = wp_remote_post(
		plannedsoft_core_get_api_url( "/install/bulk" ),
		array(
			'timeout' => 3,
			'headers' => array(
				'Content-Type' => 'application/json'
			),
			'body' => json_encode( $data )
		)
	);

	if ( is_wp_error( $request ) ) {
		return $request;
	}

	$body = json_decode( wp_remote_retrieve_body( $request ) );

	if ( ! empty( $body->data ) && ! empty( $body->data->status ) && 200 !== $body->data->status ) {
		return new WP_Error( $body->code, $body->message );
	}

	return true;
}

/**
 * Get license data.
 *
 * @return array Array of available modules.
 */
function plannedsoft_core_api_license_data( $license_key ) {
	$data = array(
		'license_key' => $license_key,
		'wp_url'      => esc_url( site_url() ),
		'wp_locale'   => get_locale(),
		'wp_version'  => get_bloginfo( 'version', 'display' )
	);

	$request = wp_remote_post(
		plannedsoft_core_get_api_url( '/license/' ),
		array(
			'headers' => array(
				'Content-Type' => 'application/json'
			),
			'body' => json_encode( $data )
		)
	);

	if ( is_wp_error( $request ) ) {
		return array();
	}

	$body = json_decode( wp_remote_retrieve_body( $request ) );

	if ( ! empty( $body->data ) && ! empty( $body->data->status ) && 200 !== $body->data->status ) {
		return new WP_Error( $body->code, $body->message );
	}

	return $body;
}
