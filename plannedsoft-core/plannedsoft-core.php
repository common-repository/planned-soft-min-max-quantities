<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Bail if already loaded other way.
if ( defined( 'plannedsoft_core_PLUGIN_FILE' ) || defined( 'plannedsoft_core_VERSION' ) ) {
	return;
}

// Define base file.
define( 'plannedsoft_core_PLUGIN_FILE', __FILE__ );
// Define plugin version. (test use).
define( 'plannedsoft_core_VERSION', '1.0.0' );


/**
 * Intialize everything after plugins_loaded action.
 *
 * @return void
 */
function plannedsoft_core_init() {
	// Load the main plug class.
	if ( ! class_exists( 'plannedsoft_core' ) ) {
		require dirname( __FILE__ ) . '/includes/class-plannedsoft-core.php';
	}

	plannedsoft_core();
}
plannedsoft_core_init();

/**
 * Get an instance of plugin main class.
 *
 * @return plannedsoft_core Instance of main class.
 */
function plannedsoft_core() {
	return plannedsoft_core::get_instance();
}
