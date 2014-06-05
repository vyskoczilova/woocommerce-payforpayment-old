<?php
/*
Plugin Name: WooCommerce Pay for Payment
Plugin URI: http://wordpress.org/plugins/woocommerce-pay-for-payment
Description: Setup individual charges for each payment method in woocommerce.
Version: 1.2.0
Author: Jörn Lund
Author URI: https://github.com/mcguffin
License: GPL
Text Domain: pay4pay
Domain Path: /languages/
*/

/**
 * Pay4Pay
 *
 * @package	Pay4Pay
 */

class Pay4Pay {

	private static $_instance = null;

	public static function instance(){
		if ( is_null(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	public static function get_default_settings() {
		return array(
			'pay4pay_item_title' => __( 'Extra Charge' , 'pay4pay' ),
			'pay4pay_charges_fixed' => 0,
			'pay4pay_charges_percentage' => 0,
			'pay4pay_taxes' => 'incl',
			'pay4pay_include_shipping' => 'no',
			'pay4pay_disable_on_free_shipping' => 'no',
		);

	}

	private function __construct() {
		load_plugin_textdomain( 'pay4pay' , false, dirname( plugin_basename( __FILE__ )) . '/lang' );

		add_action( 'woocommerce_cart_calculate_fees' , array($this,'add_pay4payment' ) );
		add_action( 'woocommerce_review_order_after_submit' , array($this,'print_autoload_js') );
	}

	function print_autoload_js(){
		?><script type="text/javascript">
jQuery(document).ready(function($){
	$(document.body).on('change', 'input[name="payment_method"]', function() {
		$('body').trigger('update_checkout');
		$.ajax( $fragment_refresh );
	});
});
 		</script><?php 
	}
	
	function add_pay4payment( ) {
		if ( ( $current_gateway = $this->get_current_gateway() ) && ( $settings = $this->get_current_gateway_settings()) ) {
			if ( $settings['pay4pay_charges_fixed'] || $settings['pay4pay_charges_percentage'] ) {
				$cost = $settings['pay4pay_charges_fixed'];
				$subtotal = WC()->cart->cart_contents_total;
				$disable_on_free_shipping = $settings['pay4pay_disable_on_free_shipping'] == 'yes';
				if ( ! $disable_on_free_shipping || ! in_array( 'free_shipping' , WC()->session->get( 'chosen_shipping_methods' )) ) {
					if ( $settings['pay4pay_include_shipping'] == 'yes' )
						$subtotal += WC()->cart->shipping_total;

					if ( $percent  = $settings['pay4pay_charges_percentage'] ) {
						$cost += $subtotal * ($percent / 100 );
					}
					if ( ! $settings['pay4pay_taxes'] ) {
						$taxable = false;
						$taxes = 0;
					} else {
						$taxable = true;
						$tax = new WC_Tax();
						$base_rate = $tax->get_shop_base_rate();
						$taxrates = array_shift( $base_rate );
						$taxrate = floatval( $taxrates['rate']) / 100;
						if ( $settings['pay4pay_taxes'] == 'incl' ) {
							$taxes = $cost - ($cost / (1+$taxrate));
							$cost -= $taxes;
						} else {
							$taxes = $cost * $taxrate;
						}
					}
				
					$item_title = $settings['pay4pay_item_title'] ? $settings['pay4pay_item_title'] : $current_gateway->title;
				
					$cost = apply_filters( "woocommerce_pay4pay_{$current_gateway->id}_amount" , $cost , $subtotal , $current_gateway );
					$do_apply = $cost != 0;
					$do_apply = apply_filters( "woocommerce_pay4pay_apply" , $do_apply , $cost , $subtotal , $current_gateway );
					$do_apply = apply_filters( "woocommerce_pay4pay_applyfor_{$current_gateway->id}" , $do_apply , $cost , $subtotal , $current_gateway );
	
					if ( $do_apply && ! $this->cart_has_fee( WC()->cart , $item_title , $cost ) ) {
						$cost = number_format($cost,2,'.','');
						WC()->cart->add_fee( $item_title , $cost, $taxable );
					}
				}
			}
		}
	}
	
	function get_current_gateway(){
		
		$available_gateways = WC()->payment_gateways->get_available_payment_gateways();
		$current_gateway = null;
		$default_gateway = get_option( 'woocommerce_default_gateway' );
		if ( ! empty( $available_gateways ) ) {
			
		   // Chosen Method
			if ( isset( WC()->session->chosen_payment_method ) && isset( $available_gateways[ WC()->session->chosen_payment_method ] ) ) {
				$current_gateway = $available_gateways[ WC()->session->chosen_payment_method ];
			} elseif ( isset( $available_gateways[ $default_gateway ] ) ) {
				$current_gateway = $available_gateways[ $default_gateway ];
			} else {
				$current_gateway = current( $available_gateways );
			}
		}
		if ( ! is_null( $current_gateway ) )
			return $current_gateway;
		else 
			return false;
	}
	function get_current_gateway_settings( ) {
		if ( $current_gateway = $this->get_current_gateway() ) {
			$defaults = self::get_default_settings();
			$settings = $current_gateway->settings + $defaults;
			return $settings;
		}
		return false;
	}
	
	function cart_has_fee( &$cart , $item_title , $amount ) {
		$fees = $cart->get_fees();
		$item_id = sanitize_title($item_title);
		$amount = (float) esc_attr( $amount );
		foreach ( $fees as $fee )
			if ( $fee->amount == $amount && $fee->id == $item_id )
				return true;
		return false;
	}

}

Pay4Pay::instance();

if ( is_admin() )
	require_once plugin_dir_path(__FILE__) . '/admin/class-pay4pay-admin.php';
