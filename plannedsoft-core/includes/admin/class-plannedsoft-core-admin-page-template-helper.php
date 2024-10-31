<?php
/**
 * Admin page template helper.
 *
 * @package plannedsoft_core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page template helper.
 *
 * @class plannedsoft_core_Admin_Page_Template_Helper
 */

class plannedsoft_core_Admin_Page_Template_Helper {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'plannedsoft_core_admin_page_top', array( $this, 'admin_page_top' ) );
		add_action( 'plannedsoft_core_admin_page_bottom', array( $this, 'admin_page_bottom' ) );
		add_action( 'plannedsoft_core_admin_page_scripts', array( $this, 'admin_page_scripts' ) );
		add_action( 'plannedsoft_core_admin_page_notices', array( $this, 'admin_page_notices' ) );
	}

	/**
	 * Display this on all admin page top section.
	 *
	 * @return void
	 */
	public function admin_page_top() {
		$items = plannedsoft_core_get_admin_menu_items();
		?>
		<div class="plannedsoft-core-inner">
			
			<div class="content-wrap">
		<?php
	}

	private function get_menu_template( $item ) {
		$class = 'menu-item';

		if ( ! empty( $item['class'] ) ) {
			$class .= ' ' . $item['class'];
		}

		if ( $item['icon'] ) {
			$title = sprintf(
				'<span class="menu-icon"><i class="%s"></i></span>
				<span>%s</span>',
				$item['icon'],
				$item['name']
			);
		} else {
			$title = sprintf(
				'<span>%s</span>',
				$item['name']
			);
		}

		$submenu = '';
		if ( ! empty( $item['submenu'] ) ) {
			$submenu .= '<ul class="submenu">';
			foreach ( $item['submenu'] as $submenu_item ) {
				$submenu .= $this->get_menu_template( $submenu_item );
			}
			$submenu .= '</ul>';
		}

		if ( $item['url'] ) {
			$title = sprintf(
				'<a href="%s">%s</a>',
				$item['url'],
				$title
			);
		}

		return sprintf(
			'<li class="%s">%s%s</li>',
			$class,
			$title,
			$submenu
		);
	}


	/**
	 * Display at the bottom of admin page.
	 *
	 * @return void
	 */
	public function admin_page_bottom() {
		?>
			</div><!--.content-wrap-->
		</div><!--.plannedsoft-core-inner-->
		<?php
	}

	/**
	 * Enqueue required admin page scripts.
	 *
	 * @return void
	 */
	public function admin_page_scripts() {
		wp_enqueue_style( array( 'plannedsoft-core-admin' ) );
		wp_enqueue_script( array( 'plannedsoft-core-admin' ) );
	}

	/**
	 * Display notices on page.
	 *
	 * @return void
	 */
	public function admin_page_notices() {
		?>
		<div id="plannedsoft_core-admin-notes">
			<?php 
			$error = !empty( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';
			$message = !empty( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
			?>
			<?php if ( $error ) { ?>
				<div class="notice notice-error settings-error"><p><?php echo esc_html( stripslashes( urldecode( $error ) ) ); ?></p></div>
			<?php } ?>
			
			<?php if ( $message ) { ?>
				<div class="notice notice-success settings-error"><p><?php echo esc_html( stripslashes( urldecode( $message ) ) ); ?></p></div>
			<?php } ?>
		</div>
		<?php
	}
}
