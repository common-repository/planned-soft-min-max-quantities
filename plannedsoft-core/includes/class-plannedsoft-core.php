<?php
/**
 * Main Plugin File.
 *
 * @package plannedsoft_core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class.
 *
 * @class plannedsoft_core
 */
final class plannedsoft_core {

	/**
	 * Plugin name
	 *
	 * @var string
	 */
	public $name = 'Plannedsoft Core';

	/**
	 * Singleton The reference the *Singleton* instance of this class.
	 *
	 * @var plannedsoft_core
	 */
	protected static $instance = null;

	/**
	 * Private clone method to prevent cloning of the instance of the
	 *
	 * @return void
	 */
	private function __clone() {}

	/**
	 * Private unserialize method to prevent unserializing.
	 *
	 * @return void
	 */
	public function __wakeup() {}

	/**
	 * Protected constructor to prevent creating a new instance of the
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	private function __construct() {
		$this->define_constants();
		$this->include_files();
		$this->register_hooks();
		$this->initialize();
	}

	/**
	 * Define constants
	 */
	private function define_constants() {
		define( 'plannedsoft_core_DIR', plugin_dir_path( plannedsoft_core_PLUGIN_FILE ) );
		define( 'plannedsoft_core_URL', plugin_dir_url( plannedsoft_core_PLUGIN_FILE ) );
		define( 'plannedsoft_core_BASENAME', plugin_basename( plannedsoft_core_PLUGIN_FILE ) );
		define( 'plannedsoft_core_NAME', $this->name );
	}

	/**
	 * Include plugin dependency files
	 */
	private function include_files() {
		require plannedsoft_core_DIR . '/includes/functions.php';
		require plannedsoft_core_DIR . '/includes/class-plannedsoft-core-utils.php';
		require plannedsoft_core_DIR . '/includes/class-plannedsoft-core-settings-api.php';

		if ( is_admin() ) {
			require plannedsoft_core_DIR . '/includes/admin/class-plannedsoft-core-admin-main.php';
			require plannedsoft_core_DIR . '/includes/admin/class-plannedsoft-core-admin-dashboard-widget.php';
			require plannedsoft_core_DIR . '/includes/admin/class-plannedsoft-core-admin-page-template-helper.php';
			require plannedsoft_core_DIR . '/includes/admin/pages/class-admin-modules-page.php';
		}
	}

	/**
	 * Initialize the plugin
	 */
	private function initialize() {

		if ( is_admin() ) {
			new plannedsoft_core_Admin_Page_Template_Helper();
			new plannedsoft_core_Admin_Main();
			new plannedsoft_core_Admin_Dashboard_Widget();
			new plannedsoft_core_Admin_Modules_Page();
		}
	}

	/**
	 * Register hooks
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'load_plugin_translations' ) );
	}

	

	/**
	 * Load plugin translation file
	 */
	public function load_plugin_translations() {
		load_plugin_textdomain(
			'plannedsoft-core',
			false,
			basename( dirname( plannedsoft_core_PLUGIN_FILE ) ) . '/languages'
		);
	}

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return Singleton The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
