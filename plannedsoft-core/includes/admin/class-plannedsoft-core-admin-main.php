<?php
/**
 * Admin main class.
 *
 * @package plannedsoft_core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin Main Class.
 *
 * @class plannedsoft_core_Admin_Main
 */
class plannedsoft_core_Admin_Main {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ), 5 );
		add_action( 'admin_head', array( $this, 'menu_icon_fix' ) );
		add_action( 'plugin_action_links_' . plannedsoft_core_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Fix menu icon issue.
	 */
	public function menu_icon_fix() {
		?>
		<style type="text/css">
		.wp-menu-image > img{
			display: inline;
			border:none !important;
		}
		</style>
		<?php
	}

	/**
	 * Register admin assets.
	 */
	public function register_admin_scripts() {
		wp_register_style( 'themify-icons', plannedsoft_core_URL . 'assets/css/themify-icons.css' );
		wp_register_style( 'plannedsoft-core-admin', plannedsoft_core_URL . 'assets/css/admin.css', array( 'themify-icons' ) );
		wp_register_script( 'plannedsoft-core-admin', plannedsoft_core_URL . 'assets/js/admin.js' );
	}

	/**
	 * Setup parent admin menu
	 */
	public function admin_menu() {
		// Access capability.
		$access_cap    = apply_filters( 'plannedsoft_core_admin_parent_menu_access_cap', 'manage_options' );
		// Menu riority.
		$menu_priority = apply_filters( 'plannedsoft_core_admin_parent_menu_priority', 4.9 );
		// Menu icon.
		$menu_icon     = apply_filters( 'plannedsoft_core_admin_parent_menu_icon', plannedsoft_core_URL . 'assets/images/admin-menu-icon.png' );

		// Register menu.
		$admin_page = add_menu_page(
			__( 'Plannedsoft Core', 'plannedsoft-core' ),
			__( 'Plannedsoft', 'plannedsoft-core' ),
			$access_cap,
			'plannedsoft-core',
			'__return_false',
			$menu_icon,
			$menu_priority
		 );
	}

	/**
	 * Adds plugin action links.
	 */
	public function plugin_action_links( $links ) {
		$links['modules'] = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=plannedsoft-core' ),
			__( 'Modules', 'plannedsoft-core' )
		);
		$links['license'] = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=plannedsoft-core-license' ),
			__( 'License', 'plannedsoft-core' )
		);

		return $links;
	}
}
