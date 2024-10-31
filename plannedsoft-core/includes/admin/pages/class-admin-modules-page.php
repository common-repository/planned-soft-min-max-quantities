<?php
/**
 * Admin Settings Page Class.
 *
 * @package plannedsoft_core
 * @class plannedsoft_core_Admin_Modules_Page
 */

class plannedsoft_core_Admin_Modules_Page {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ), 6 );
	}

	/**
	 * Sanitize settings option
	 */
	public function admin_menu() {
		// Access capability.
		$access_cap = apply_filters( 'plannedsoft_core_admin_page_access_cap', 'manage_options' );

		// Register menu.
		$admin_page = add_submenu_page(
			'plannedsoft-core',
			__( 'Plannedsoft Core', 'plannedsoft-core' ),
			__( 'Plannedsoft', 'plannedsoft-core' ),
			$access_cap,
			'plannedsoft-core',
			array( $this, 'render_page' )
		);

		add_action( "admin_print_styles-{$admin_page}", array( $this, 'print_scripts' ) );
		add_action( "load-{$admin_page}", array( $this, 'handle_actions' ) );
	}

	public function handle_actions() {
		// Maybe send current plugin data.
		plannedsoft_core_maybe_send_plugins_data();
	}

	public function render_page() {
		?>
		<div class="wrap plannedsoft-core-wrap">
			<?php do_action( 'plannedsoft_core_admin_page_top'  ); ?>

			<h1><?php esc_html_e( 'Plannedsoft', 'plannedsoft-core' ); ?></h1>

			<?php do_action( 'plannedsoft_core_admin_page_notices' ); ?>

			<p>
				<a href="https://plannedsoft.com/support/" class="button help-btn" target="_blank">
					<span class="btn-icon dashicons dashicons-editor-help"></span>
					<span class="btn-text"><?php esc_html_e( 'Need Help?', 'plannedsoft-core' ); ?></span>
				</a>
			</p>

			<div class="plannedsoft_core-admin-content">
            	<?php
					
					
				?>
			</div>

			<?php do_action( 'plannedsoft_core_admin_page_bottom'  ); ?>
		</div>
		<?php
	}

	public function print_scripts() {
		wp_localize_script( 'plannedsoft-core-admin', 'plannedsoftCore', [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'pageUrl' => admin_url( 'admin.php?page=plannedsoft-core' )
		] );

		do_action( 'plannedsoft_core_admin_page_scripts' );
	}
}
