<?php
/*
Plugin Name: WooCommerce Pay for Payment
Plugin URI: http://wordpress.org/plugins/woocommerce-pay-for-payment
Description: Setup individual charges for each payment method in woocommerce.
Version: 1.1.1
Author: Jörn Lund
Author URI: https://github.com/mcguffin

Text Domain: pay4pay
Domain Path: /lang/
*/


class Pay4Pay {

	private static $_instance = null;

	public static function instance(){
		if ( is_null(self::$_instance) )
			self::$_instance = new Pay4Pay();
		return self::$_instance;
	}

	private function __construct() {
		load_plugin_textdomain( 'pay4pay' , false, dirname( plugin_basename( __FILE__ )) . '/lang' );
		add_action( 'woocommerce_init' , array($this, 'add_payment_options') );
		add_action( 'woocommerce_update_options_checkout' , array($this, 'add_payment_options') );
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
		global $woocommerce;

		if ( $current_gateway = $this->get_current_gateway() ) {
			
			if ( isset( $current_gateway->settings['pay4pay_charges_fixed']) ) {
				$cost = $current_gateway->settings['pay4pay_charges_fixed'];
				$subtotal = $woocommerce->cart->cart_contents_total;
				if ( $current_gateway->settings['pay4pay_include_shipping'] == 'yes' )
					$subtotal += $woocommerce->cart->shipping_total;

				if ( $percent = $current_gateway->settings['pay4pay_charges_percentage'] ) {
					$cost += $subtotal * ($percent / 100 );
				}
				if ( ! $current_gateway->settings['pay4pay_taxes'] ) {
					$taxable = false;
					$taxes = 0;
				} else {
					$taxable = true;
					$tax = new WC_Tax();
					$base_rate = $tax->get_shop_base_rate();
					$taxrates = array_shift( $base_rate );
					$taxrate = floatval( $taxrates['rate']) / 100;
					if ( $current_gateway->settings['pay4pay_taxes'] == 'incl' ) {
						$taxes = $cost - ($cost / (1+$taxrate));
						$cost -= $taxes;
					} else {
						$taxes = $cost * $taxrate;
					}
				}
				
				$item_title = isset($current_gateway->settings['pay4pay_item_title']) ? $current_gateway->settings['pay4pay_item_title'] : $current_gateway->title;
				
				$cost = apply_filters( "woocommerce_pay4pay_{$current_gateway->id}_amount" , $cost , $subtotal , $current_gateway );
				$do_apply = apply_filters( "woocommerce_pay4pay_applyfor_{$current_gateway->id}" , $cost != 0 , $cost , $subtotal , $current_gateway );
	
				if ( $do_apply && ! $this->cart_has_fee( $woocommerce->cart , $item_title , $cost ) ) {
					$cost = number_format($cost,2,'.','');
					$woocommerce->cart->add_fee( $item_title , $cost, $taxable );
				}
			}
		}
	}
	
	function get_current_gateway(){
		global $woocommerce;
		
		$available_gateways = $woocommerce->payment_gateways->get_available_payment_gateways();
		$current_gateway = null;
		$default_gateway = get_option( 'woocommerce_default_gateway' );
		if ( ! empty( $available_gateways ) ) {
			
		   // Chosen Method
			if ( isset( $woocommerce->session->chosen_payment_method ) && isset( $available_gateways[ $woocommerce->session->chosen_payment_method ] ) ) {
				$current_gateway = $available_gateways[ $woocommerce->session->chosen_payment_method ];
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
	
	function cart_has_fee( &$cart , $item_title , $amount ) {
		$fees = $cart->get_fees();
		$item_id = sanitize_title($item_title);
		$amount = (float) esc_attr( $amount );
		foreach ( $fees as $fee )
			if ( $fee->amount == $amount && $fee->id == $item_id )
				return true;
		return false;
	}
	

	function add_payment_options( ) {
		global $woocommerce;
		
		foreach ( $woocommerce->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
			$gateway->form_fields += array(
				'pay4pay_title' => array(
					'title' => __( 'Extra Charge', 'pay4pay' ),
					'type' => 'title',
					'description' => '',
				),
				'pay4pay_item_title' => array(
					'title' => __( 'Item Title', 'pay4pay' ),
					'type' => 'text',
					'description' => __( 'This will show up in the shopping basket.', 'pay4pay' ),
					'default' => $gateway->title,
					'desc_tip' => true,
					'custom_attributes' => array(
					),
				),
				'pay4pay_charges_fixed' => array(
					'title' => __( 'Fixed charge', 'pay4pay' ),
					'type' => 'number',
					'description' => __( 'Extra charge to be added to cart when this payment method is selected.', 'pay4pay' ),
					'default' => 0,
					'desc_tip' => true,
					'custom_attributes' => array(
						'step' => 'any',
					),
				),
				'pay4pay_charges_percentage' => array(
					'title' => __( 'Percent charge', 'pay4pay' ),
					'type' => 'number',
					'description' => __( 'Percentage of cart total to be added to payment.', 'pay4pay' ),
					'default' => 0,
					'desc_tip' => true,
					'custom_attributes' => array(
						'step' => 'any',
					),
				),
				'pay4pay_taxes' => array(
					'title' => __('Includes Taxes','pay4pay'),
					'type' => 'select',
					'description' => __( 'Select an option to handle taxes for the extra charges specified above.', 'pay4pay' ),
					'options' => array(
						0 => __( 'No taxes', 'pay4pay' ),
						'incl' => __( 'Including tax', 'woocommerce' ),
						'excl' => __( 'Excluding tax', 'woocommerce' ),
					),
					'default' => 'incl',
					'desc_tip' => true,
				),
				'pay4pay_include_shipping' => array(
					'title' => __('Include Shipping','pay4pay'),
					'type' => 'checkbox',
					'description' => __( 'Check this if shipping cost should be included in the calculation of the payment fee.', 'pay4pay' ),
					'default' => 'no',
					'desc_tip' => true,
				),
			);
			add_action( 'woocommerce_update_options_payment_gateways_'.$gateway->id , array($this,'update_payment_options') , 20 );
		}
	}
	
	function update_payment_options(  ) {
		global $woocommerce, $woocommerce_settings, $current_section, $current_tab;
		$class = new $current_section();
		$prefix = 'woocommerce_'.$class->id;
		$opt_name = $prefix.'_settings';
		$options = get_option( $opt_name );
		// validate!
		$extra = array(
			'pay4pay_item_title' => sanitize_text_field( $_POST[$prefix.'_pay4pay_item_title'] ),
			'pay4pay_charges_fixed' => floatval( $_POST[$prefix.'_pay4pay_charges_fixed'] ),
			'pay4pay_charges_percentage' => floatval( $_POST[$prefix.'_pay4pay_charges_percentage'] ),
			'pay4pay_taxes' => $this->_sanitize_tax_option($_POST[$prefix.'_pay4pay_taxes']), // 0, incl, excl
			'pay4pay_include_shipping' => isset($_POST[$prefix.'_pay4pay_include_shipping']) && $_POST[$prefix.'_pay4pay_include_shipping'] === '1' ? 'yes' : 'no' ,
		);
		$options += $extra;
		update_option( $opt_name , $options );
	}
	private function _sanitize_tax_option( $tax_option , $default = 'incl' ) {
		if ( in_array( $tax_option , array(0,'incl','excl') ) )
			return $tax_option;
		return $default;
	}
}

$woocommerce_pay4pay = Pay4Pay::instance();