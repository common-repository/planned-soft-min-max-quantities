<?php

/**
 * PS_Min_Max_Quantities_Admin class.
 */
class PS_Min_Max_Quantities_Admin {

	/**
	 * Constructor
	 */
	public function __construct() {


		// Get all roles
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		$all_roles = $wp_roles->roles;

		$roles_options = array();

		foreach($all_roles as $role_id => $role){
			$roles_options[$role_id] = $role['name'];
		}

		// ajax handler
		add_action('wp_ajax_add_new_minmax_rule', array($this, 'ajax_add_new_minmax_rule_field'));

		
	}
	
	

	public function ajax_add_new_minmax_rule_field(){
		
		if ( ! isset( $_POST['woocommerce_min_max_quantities_nonce'] ) 
		|| ! wp_verify_nonce( sanitize_text_field($_POST['woocommerce_min_max_quantities_nonce']), 'woocommerce_min_max_quantities_action' ) ) {
			exit("Sorry, You are not verified.");
		} 

		if(isset($_POST['rule_id'])){
			$this->single_minmax_section(sanitize_text_field($_POST['rule_id']));
		}
		wp_die();
	}


	// For displaying single min-max section
	function single_minmax_section($rule_id = 1){

		?>
		<div class="single-minmax-section" id="minmax-rule-<?php echo esc_attr($rule_id); ?>">
			<?php
			if($rule_id !== 1){
				?>
				<div class="single-minmax-close">
					<img src="<?php echo esc_url( plugin_dir_url(dirname(__FILE__)) . 'assets/images/delete.png' ); ?>" alt="">
				</div>
				<?php
			}
			?>
			
			<?php
			if ( ! isset( $wp_roles ) ) {
				$wp_roles = new WP_Roles();
			}
			$all_roles = $wp_roles->roles;
			
			
			

			$all_rules = get_post_meta( get_the_ID(), 'product_minmax_rules', true );
			$applied_to = isset($all_rules[$rule_id]) && isset($all_rules[$rule_id]['product_minmax_apply_to']) ? $all_rules[$rule_id]['product_minmax_apply_to'] : 'everyone';
			$div_style = ($applied_to === 'roles') ?  '' : 'display:none;';
			$rolesarray = isset($all_rules[$rule_id]) && isset($all_rules[$rule_id]['product_minmax_applied_roles_to']) ? $all_rules[$rule_id]['product_minmax_applied_roles_to'] : array();
			?>
			<p class="form-field">
				<label><?php esc_html_e( 'Applies To:', 'woocommerce-min-max-quantities' ); ?></label>

				<select title="<?php echo esc_attr( 'Choose if this rule should apply to everyone, or to specific roles. Useful if you only give discounts to existing customers, or if you have tiered pricing based on the users role.', 'woocommerce-min-max-quantities' ); ?>" class="pricing_rule_apply_to" id="product_minmax_apply_to" name="product_minmax_rules[<?php echo esc_attr($rule_id); ?>][product_minmax_apply_to]">
					<option <?php selected( 'everyone', $applied_to ); ?> value="everyone"><?php esc_html_e( 'Everyone', 'woocommerce-min-max-quantities' ); ?></option>
					<option <?php selected( 'unauthenticated', $applied_to ); ?> value="unauthenticated"><?php esc_html_e( 'Guests', 'woocommerce-min-max-quantities' ); ?></option>
					<option <?php selected( 'roles', $applied_to ); ?> value="roles"><?php esc_html_e( 'Specific Roles', 'woocommerce-min-max-quantities' ); ?></option>
				</select>
			</p>
			<div id="roles-section" class="roles section" style="<?php echo esc_attr($div_style); ?>">
				<p class="form-field">
					<label><?php esc_html_e('Roles:', 'woocommerce-min-max-quantities'); ?></label>
					<select style="width:80%;" name="product_minmax_rules[<?php echo esc_attr($rule_id); ?>][product_minmax_applied_roles_to][]" class="multiselect wc-enhanced-select" multiple="multiple">
						<?php foreach($all_roles as $role_id => $role): ?>
							<?php $role_checked = is_array($rolesarray) && count($rolesarray) > 0 && in_array($role_id, $rolesarray); ?>
							<option <?php selected($role_checked); ?> value="<?php esc_attr_e($role_id); ?>"><?php esc_html_e($role['name']); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>
			<div class="clear"></div>
			
			<?php

			woocommerce_wp_text_input( array( 'id' => $rule_id.'_minimum_allowed_quantity', 'label' => __( 'Minimum quantity', 'woocommerce-min-max-quantities' ), 'description' => __( 'Enter a quantity to prevent the user buying this product if they have fewer than the allowed quantity in their cart', 'woocommerce-min-max-quantities' ), 'desc_tip' => true, 'type' => 'number', 'custom_attributes' => array( 'min' => 0, 'step' => 1 ) ) );

			woocommerce_wp_text_input( array( 'id' => $rule_id.'_maximum_allowed_quantity', 'label' => __( 'Maximum quantity', 'woocommerce-min-max-quantities' ), 'description' => __( 'Enter a quantity to prevent the user buying this product if they have more than the allowed quantity in their cart', 'woocommerce-min-max-quantities' ), 'desc_tip' => true, 'type' => 'number', 'custom_attributes' => array( 'min' => 0, 'step' => 1 ) ) );

			woocommerce_wp_text_input( array( 'id' => $rule_id.'_group_of_quantity', 'label' => __( 'Group of...', 'woocommerce-min-max-quantities' ), 'description' => __( 'Enter a quantity to only allow this product to be purchased in groups of X', 'woocommerce-min-max-quantities' ), 'desc_tip' => true, 'type' => 'number', 'custom_attributes' => array( 'min' => 0, 'step' => 1 ) ) );

			woocommerce_wp_checkbox( array( 'id' => $rule_id.'_minmax_do_not_count', 'label' => __( 'Order rules: Do not count', 'woocommerce-min-max-quantities' ), 'description' => __( 'Don\'t count this product against your minimum order quantity/value rules.', 'woocommerce-min-max-quantities' ) ) );

			woocommerce_wp_checkbox( array( 'id' => $rule_id.'_minmax_cart_exclude', 'label' => __( 'Order rules: Exclude', 'woocommerce-min-max-quantities' ), 'description' => __( 'Exclude this product from minimum order quantity/value rules. If this is the only item in the cart, rules will not apply.', 'woocommerce-min-max-quantities' ) ) );

			woocommerce_wp_checkbox( array( 'id' => $rule_id.'_minmax_category_group_of_exclude', 'label' => __( 'Category rules: Exclude', 'woocommerce-min-max-quantities' ), 'description' => __( 'Exclude this product from category group-of-quantity rules. This product will not be counted towards category groups.', 'woocommerce-min-max-quantities' ) ) );
			?>
		</div>
		<?php
	}
    

	

}

$PS_Min_Max_Quantities_Admin = new PS_Min_Max_Quantities_Admin();
