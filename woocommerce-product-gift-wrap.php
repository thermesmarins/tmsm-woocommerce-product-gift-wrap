<?php
/*
Plugin Name: TMSM WooCommerce Product Gift Wrap
Plugin URI: https://github.com/thermesmarins/tmsm-woocommerce-product-gift-wrap
Description: Add an option to your products to enable gift wrapping. Optionally charge a fee.
Version: 1.1.2
Author: Nicolas Mollet
Author URI: http://www.nicolasmollet.com
Requires at least: 4.5
Tested up to: 4.8
Text Domain: woocommerce-product-gift-wrap
Domain Path: /languages/
Github Plugin URI: https://github.com/thermesmarins/tmsm-woocommerce-product-gift-wrap
Github Branch: master
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Original Author: Mike Jolley
*/

/**
 * Localisation
 */
load_plugin_textdomain( 'woocommerce-product-gift-wrap', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/**
 * WC_Product_Gift_wrap class.
 */
class WC_Product_Gift_Wrap {

	/**
	 * Hook us in :)
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$default_message                 = '{checkbox} '. sprintf( __( 'Gift wrap this item for %s?', 'woocommerce-product-gift-wrap' ), '{price}' );
		$this->gift_wrap_enabled         = get_option( 'product_gift_wrap_enabled' ) == 'yes' ? true : false;
		$this->gift_wrap_cost            = get_option( 'product_gift_wrap_cost', 0 );
		$this->product_gift_wrap_message = get_option( 'product_gift_wrap_message' );

		if ( ! $this->product_gift_wrap_message ) {
			$this->product_gift_wrap_message = $default_message;
		}

		add_option( 'product_gift_wrap_enabled', 'no' );
		add_option( 'product_gift_wrap_cost', '0' );
		add_option( 'product_gift_wrap_message', $default_message );

		// Init settings
		$this->settings = array(
			array(
				'name' 		=> __( 'Gift Wrapping Enabled by Default?', 'woocommerce-product-gift-wrap' ),
				'desc' 		=> __( 'Enable this to allow gift wrapping for products by default.', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_enabled',
				'type' 		=> 'checkbox',
			),
			array(
				'name' 		=> __( 'Default Gift Wrap Cost', 'woocommerce-product-gift-wrap' ),
				'desc' 		=> __( 'The cost of gift wrap unless overridden per-product.', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_cost',
				'type' 		=> 'text',
				'desc_tip'  => true
			),
			array(
				'name' 		=> __( 'Gift Wrap Message', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_message',
				'desc' 		=> __( 'Note: <code>{checkbox}</code> will be replaced with a checkbox and <code>{price}</code> will be replaced with the gift wrap cost.', 'woocommerce-product-gift-wrap' ),
				'type' 		=> 'text',
				'desc_tip'  => __( 'The checkbox and label shown to the user on the frontend.', 'woocommerce-product-gift-wrap' )
			),
		);

		// Display on the front end
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'gift_option_html' ), 30 );

		// Filters for cart actions
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'woocommerce_add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'woocommerce_add_order_item_meta' ), 10, 2 );
		add_action( 'woocommerce_display_item_meta', array( $this, 'woocommerce_display_item_meta' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'woocommerce_checkout_create_order_line_item' ), 10, 4 );
		add_action( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'woocommerce_order_item_get_formatted_meta_data' ), 10, 2 );

		// Write Panels
		add_action( 'woocommerce_product_options_pricing', array( $this, 'write_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'write_panel_save' ) );

		// Admin
		add_action( 'woocommerce_settings_general_options_end', array( $this, 'admin_settings' ) );
		add_action( 'woocommerce_update_options_general', array( $this, 'save_admin_settings' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.1
	 */
	public function enqueue_scripts(){
		wp_enqueue_script( 'woocommerce-product-gift-wrap', plugin_dir_url( __FILE__ ) . 'assets/js/tmsm-woocommerce-product-gift.js', array( 'jquery' ), null, true );
	}


	/**
	 * Show the Gift Checkbox on the frontend
	 *
	 * @access public
	 * @return void
	 */
	public function gift_option_html() {


		global $product;

		// store product in reset variable
		$reset_product = $product;

		$is_wrappable = get_post_meta( $product->get_id(), '_is_gift_wrappable', true );

		$cost = get_post_meta( $product->get_id(), '_gift_wrap_cost', true );

		//Initilize products
		$products = array();

		if ( $product->is_type( 'variable' ) ) { //for variable product
			foreach ( $product->get_children() as $variation_product_id ) {
				$products[] = wc_get_product( $variation_product_id );
			}
		} else {
			$products[] = $product;
		}

		foreach ( $products as $product ) {//For all products
			// Get product ID
			$product_id = $variation_id = $product->get_id();
			if ( $product->is_type( 'variation' ) ) {
				// Get product ID
				//$product_id 	= $woo_vou_model->woo_vou_get_item_productid_from_product($product);
				if ( $product->is_type( 'variable' ) || $product->is_type( 'variation' ) ) {
					$product_id = $product->get_parent_id();

				} else {
					$product_id = $product->get_id();
				}
				// Get variation ID
				$variation_id = $product->get_id();
			}


			$is_virtual = get_post_meta( $variation_id, '_virtual', true );

			if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
				$is_wrappable = 'yes';
			}

			if ( $is_wrappable == 'yes' && $is_virtual != 'yes') {

				$current_value = ! empty( $_REQUEST['gift_wrap'] ) ? 1 : 0;

				if ( $cost == '' ) {
					$cost = $this->gift_wrap_cost;
				}

				$price_text = $cost > 0 ? wc_price( $cost ) : __( 'free', 'woocommerce-product-gift-wrap' );
				$checkbox   = '<input type="checkbox" name="_gift_wrap['.$variation_id.']" value="yes" ' . checked( $current_value, 1, false ) . ' />';

				wc_get_template( 'gift-wrap.php', array(
					'product_gift_wrap_message' => $this->product_gift_wrap_message,
					'product'                  => $product,
					'checkbox'                  => $checkbox,
					'price_text'                => $price_text
				), 'woocommerce-product-gift-wrap', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/' );
			}

		}

		// restore product
		$product = $reset_product;


		/*global $post;


		$is_wrappable = get_post_meta( $post->ID, '_is_gift_wrappable', true );
		$is_virtual = get_post_meta( $post->ID, '_is_virtual', true );


		//echo 'produit '.($is_virtual?'is virtual':'is physical');


		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		if ( $is_wrappable == 'yes' ) {

			$current_value = ! empty( $_REQUEST['gift_wrap'] ) ? 1 : 0;

			$cost = get_post_meta( $post->ID, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$price_text = $cost > 0 ? wc_price( $cost ) : __( 'free', 'woocommerce-product-gift-wrap' );
			$checkbox   = '<input type="checkbox" name="gift_wrap" value="yes" ' . checked( $current_value, 1, false ) . ' />';

			wc_get_template( 'gift-wrap.php', array(
				'product_gift_wrap_message' => $this->product_gift_wrap_message,
				'checkbox'                  => $checkbox,
				'price_text'                => $price_text
			), 'woocommerce-product-gift-wrap', untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/templates/' );
		}*/
	}

	/**
	 * When added to cart, save any gift data
	 *
	 * @access public
	 * @param mixed $cart_item_meta
	 * @param mixed $product_id
	 * @return void
	 */
	public function woocommerce_add_cart_item_data( $cart_item_meta, $product_id ) {
		$is_wrappable = get_post_meta( $product_id, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		if ( ! empty( $_POST['_gift_wrap'] ) && $is_wrappable == 'yes' ) {
			$cart_item_meta['_gift_wrap'] = 'yes';
		}

		return $cart_item_meta;
	}

	/**
	 * Get the gift data from the session on page load
	 *
	 * @access public
	 * @param mixed $cart_item
	 * @param mixed $values
	 * @return void
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {

		if ( ! empty( $values['_gift_wrap'] ) ) {
			$cart_item['_gift_wrap'] = 'yes';

			$cost = get_post_meta( $cart_item['data']->id, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$cart_item['data']->adjust_price( $cost );
		}

		return $cart_item;
	}

	/**
	 * Display gift data if present in the cart
	 *
	 * @access public
	 * @param mixed $other_data
	 * @param mixed $cart_item
	 * @return void
	 */
	public function get_item_data( $item_data, $cart_item ) {
		if ( ! empty( $cart_item['_gift_wrap'] ) && $cart_item['_gift_wrap'] == 'yes')
			$item_data[] = array(
				'name'    => __( 'Gift Wrapped', 'woocommerce-product-gift-wrap' ),
				'value'   => 'yes',
				'display' => __( 'Yes', 'woocommerce-product-gift-wrap' ),
				'hidden' => false
			);

		return $item_data;
	}

	/**
	 * Adjust price after adding to cart
	 *
	 * @access public
	 * @param mixed $cart_item
	 * @return mixed
	 */
	public function add_cart_item( $cart_item ) {
		if ( ! empty( $cart_item['_gift_wrap'] ) &&  $cart_item['_gift_wrap'] == 'yes') {

			$cost = get_post_meta( $cart_item['data']->id, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$cart_item['data']->adjust_price( $cost );
		}

		return $cart_item;
	}

	/**
	 * After ordering, add the data to the order line items.
	 *
	 * @access public
	 * @param mixed $item_id
	 * @param mixed $values
	 * @return void
	 */
	public function woocommerce_add_order_item_meta( $item_id, $cart_item ) {
		if ( ! empty( $cart_item['_gift_wrap'] ) &&  $cart_item['_gift_wrap'] == 'yes') {
			//wc_add_order_item_meta( $item_id, __( 'Gift Wrapped', 'woocommerce-product-gift-wrap' ), __( 'Yes', 'woocommerce-product-gift-wrap' ) );
		}
	}


	/**
	 * Displays hidden delivery date for order item in order view (frontend)
	 * /my-account/view-order/$order_id/
	 * /checkout/order-received/$order_id/
	 *
	 * @since 1.0.0
	 *
	 * @param  string        $html
	 * @param  WC_Order_Item $item
	 * @param  array         $args
	 *
	 * @return string
	 */
	public function woocommerce_display_item_meta( $html, $item, $args ) {

		$strings = [];

		if ( !empty($item->get_meta( '_gift_wrap' )) && $item->get_meta( '_gift_wrap' ) == 'yes') {
			$strings[]           = '<strong class="wc-item-meta-label">' . __( 'Gift Wrapped:', 'woocommerce-product-gift-wrap' ) . '</strong> '
			                       . __( 'Yes', 'woocommerce-product-gift-wrap' );
		}

		if ( $strings != [] ) {
			$html .= $args['before'] . implode( $args['separator'], $strings ) . $args['after'];
		}
		return $html;
	}

	/**
	 * Update order item's meta with recipient data
	 *
	 * @since 1.0.0
	 *
	 * @param WC_Order_Item_Product $item
	 * @param string                $cart_item_key
	 * @param array                 $values
	 * @param WC_Order              $order
	 */
	public function woocommerce_checkout_create_order_line_item( $item, $cart_item_key, $values, $order ) {

		if ( ! empty( $values['_gift_wrap'] ) &&  $values['_gift_wrap'] == 'yes') {
			$item->add_meta_data( '_gift_wrap', 'yes', true );
		}

	}


	/**
	 * write_panel function.
	 *
	 * @access public
	 * @return void
	 */
	public function write_panel() {
		global $post;

		echo '</div><div class="options_group show_if_simple show_if_variable">';

		$is_wrappable = get_post_meta( $post->ID, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		woocommerce_wp_checkbox( array(
				'id'            => '_is_gift_wrappable',
				'wrapper_class' => '',
				'value'         => $is_wrappable,
				'label'         => __( 'Gift Wrappable', 'woocommerce-product-gift-wrap' ),
				'description'   => __( 'Enable this option if the customer can choose gift wrapping.', 'woocommerce-product-gift-wrap' ),
			) );

		woocommerce_wp_text_input( array(
				'id'          => '_gift_wrap_cost',
				'label'       => __( 'Gift Wrap Cost', 'woocommerce-product-gift-wrap' ),
				'placeholder' => $this->gift_wrap_cost,
				'desc_tip'    => true,
				'description' => __( 'Override the default cost by inputting a cost here.', 'woocommerce-product-gift-wrap' ),
			) );

		wc_enqueue_js( "
			jQuery('input#_is_gift_wrappable').change(function(){

				jQuery('._gift_wrap_cost_field').hide();

				if ( jQuery('#_is_gift_wrappable').is(':checked') ) {
					jQuery('._gift_wrap_cost_field').show();
				}

			}).change();
		" );
	}

	/**
	 * write_panel_save function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @return void
	 */
	public function write_panel_save( $post_id ) {
		$_is_gift_wrappable = ! empty( $_POST['_is_gift_wrappable'] ) ? 'yes' : 'no';
		$_gift_wrap_cost   = ! empty( $_POST['_gift_wrap_cost'] ) ? wc_clean( $_POST['_gift_wrap_cost'] ) : '';

		update_post_meta( $post_id, '_is_gift_wrappable', $_is_gift_wrappable );
		update_post_meta( $post_id, '_gift_wrap_cost', $_gift_wrap_cost );
	}

	/**
	 * admin_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_settings() {
		woocommerce_admin_fields( $this->settings );
	}

	/**
	 * save_admin_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function save_admin_settings() {
		woocommerce_update_options( $this->settings );
	}

	/**
	 * Displays recipient item meta on order page
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 *
	 * @param mixed         $formatted_meta
	 * @param WC_Order_Item $order_item
	 *
	 * @return mixed
	 */
	public function woocommerce_order_item_get_formatted_meta_data( $formatted_meta, WC_Order_Item $order_item ) {
		if ( empty( $formatted_meta ) ) {
			return $formatted_meta;
		}

		foreach ( $formatted_meta as $meta ) {

			if($meta->key == '_gift_wrap' && !empty($meta->value) && $meta->value == 'yes'){
				$meta->display_key = __('Gift Wrapped', 'woocommerce-product-gift-wrap');
				$meta->display_value = __('Yes', 'woocommerce-product-gift-wrap');
			}

		}

		return $formatted_meta;
	}
}

new WC_Product_Gift_Wrap();
