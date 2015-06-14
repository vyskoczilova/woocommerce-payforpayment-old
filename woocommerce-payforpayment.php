<?php
/*
Plugin Name: WooCommerce Pay for Payment
Plugin URI: http://wordpress.org/plugins/woocommerce-pay-for-payment
Description: Setup individual charges for each payment method in woocommerce.
Version: 1.3.5
Author: Jörn Lund
Author URI: https://github.com/mcguffin
License: GPL
*/

/**
 * Pay4Pay
 *
 * @package	Pay4Pay
 */

class Pay4Pay {

	private static $_instance = null;
	private $_fee = null;
	public $required_wc_version = '2.2.0';

	public static function instance(){
		if ( is_null(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}
	
	public static function get_default_settings() {
		return array(
			'pay4pay_item_title' => __( 'Extra Charge' , 'woocommerce-payforpayment' ),
			'pay4pay_charges_fixed' => 0,
			'pay4pay_charges_percentage' => 0,
			'pay4pay_disable_on_free_shipping' => 'no',
			
			'pay4pay_taxes' => 'no',
			'pay4pay_includes_taxes' => 'yes',
			'pay4pay_tax_class' => '',
			
			'pay4pay_enable_extra_fees' => 'no',
			'pay4pay_include_shipping' => 'no',
			'pay4pay_include_coupons' => 'no',
			'pay4pay_include_cart_taxes' => 'yes',
		);
	}

	private function __construct() {
//		add_action( 'woocommerce_cart_calculate_fees' , array($this,'add_pay4payment' ) , 99 ); // make sure this is the last fee eing added
		add_action( 'woocommerce_calculate_totals' , array($this,'calculate_pay4payment' ) , 99 );
		add_action( 'woocommerce_cart_calculate_fees' , array($this,'add_pay4payment' ) , 99 );
		add_action( 'woocommerce_review_order_after_submit' , array($this,'print_autoload_js') );
		add_action( 'admin_init' , array( &$this , 'check_wc_version' ) );
		add_action( 'plugins_loaded' , array( &$this , 'load_textdomain' ) );
	}
	
	function load_textdomain() {
		load_plugin_textdomain( 'woocommerce-payforpayment' , false, dirname( plugin_basename( __FILE__ )) . '/languages' );
	}
	
	function check_wc_version() {
		if ( ! function_exists( 'WC' ) || version_compare( WC()->version , $this->required_wc_version ) < 0 ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			add_action( 'admin_notices', array( __CLASS__ , 'wc_version_notice' ) );
		}
	}
	public static function wc_version_notice() {
		?><div class="error"><p><?php 
			_e('WooCommerce Pay4Payment requires at least WooCommerce 2.2. Please update!');
		?></p></div><?php
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
	function add_pay4payment( $cart ) {
		if ( ! is_null($this->_fee) ) {
			$cart->add_fee( $this->_fee->fee_title , 
							$this->_fee->cost , 
							$this->_fee->taxable , 
							$this->_fee->tax_class
						);
			
		}
	}
	function calculate_pay4payment( ) {
		if ( ! is_null($this->_fee) ) {
			return;
		}
		if ( ( $current_gateway = $this->get_current_gateway() ) && ( $settings = $this->get_current_gateway_settings() ) ) {
			$settings = wp_parse_args( $settings, self::get_default_settings() );
			
			$disable_on_free_shipping	= 'yes' == $settings['pay4pay_disable_on_free_shipping'];

			$include_shipping			= 'yes' == $settings['pay4pay_include_shipping'];
			$include_fees 				= 'yes' == $settings['pay4pay_enable_extra_fees'];
			$include_coupons			= 'yes' == $settings['pay4pay_include_coupons'];
			$include_cart_taxes 		= 'yes' == $settings['pay4pay_include_cart_taxes'];
			$taxable					= 'yes' == $settings['pay4pay_taxes'];
			// wc tax options
			$calc_taxes					= 'yes' == get_option('woocommerce_calc_taxes');
			$include_taxes				= 'yes' == $settings['pay4pay_includes_taxes'];
			$tax_class					= $settings['pay4pay_tax_class'];

			if ( $settings['pay4pay_charges_fixed'] || $settings['pay4pay_charges_percentage'] ) {
				$cart = WC()->cart;
				$chosen_methods=  WC()->session->get( 'chosen_shipping_methods' );
				if (is_null($chosen_methods))
				{
				$chosen_methods=[];
				}
				
				if ( ! $disable_on_free_shipping || ! in_array( 'free_shipping' , $chosen_methods) ) {
					$cost = floatval($settings['pay4pay_charges_fixed']);
				
					//  √ $this->cart_contents_total + √ $this->tax_total + √ $this->shipping_tax_total + $this->shipping_total + $this->fee_total,
					$calculation_base = 0;
					if ( $percent = floatval($settings['pay4pay_charges_percentage']) ) {
						
						
						$calculation_base = $cart->subtotal_ex_tax;
						
						if ( $include_shipping )
							$calculation_base += $cart->shipping_total;

						if ( $include_fees )
							$calculation_base += $cart->fee_total;

						if ( $include_coupons )
							$calculation_base -= $cart->discount_total + $cart->discount_cart;
						
						if ( $include_cart_taxes ) {
							$calculation_base += $cart->tax_total;
							if ( $include_shipping )
								$calculation_base += $cart->shipping_tax_total;
						}
						
						$cost += $calculation_base * ($percent / 100 );
						
					}
					
					
					$do_apply = $cost != 0;
					$do_apply = apply_filters( "woocommerce_pay4pay_apply" , $do_apply , $cost , $calculation_base , $current_gateway );
					$do_apply = apply_filters( "woocommerce_pay4pay_applyfor_{$current_gateway->id}" , $do_apply , $cost , $calculation_base , $current_gateway );
					
					if ( $do_apply ) {
						// make our fee being displayed in the order total
						$fee_title	= $settings['pay4pay_item_title'] ? $settings['pay4pay_item_title'] : $current_gateway->title;

						$fee_title	= str_replace( 
							array('[FIXED_AMOUNT]','[PERCENT_AMOUNT]','[CART_TOTAL]') , 
							array(
								strip_tags( wc_price( $settings['pay4pay_charges_fixed'] ) ) , 
								floatval( $settings['pay4pay_charges_percentage'] ), 
								strip_tags(wc_price($calculation_base)) , 
							),
							$fee_title );
						$fee_id 	= sanitize_title( $fee_title );
						
						// apply min + max before tax calculation
						// some people may like to use the plugin to apply a discount, so we need to handle negative values correctly
						if ( $settings['pay4pay_charges_percentage'] ) {
							$min_cost = isset( $settings['pay4pay_charges_minimum'] ) ? $settings['pay4pay_charges_minimum'] : -INF;
							$max_cost = isset( $settings['pay4pay_charges_maximum'] ) && (bool) $settings['pay4pay_charges_maximum'] ? $settings['pay4pay_charges_maximum'] : INF;
							$cost = max( $min_cost , $cost );
							$cost = min( $max_cost , $cost );
						}
						
						// WooCommerce Fee is always ex taxes. We need to subtract taxes, WC will add them again later.
						if ( $taxable && $include_taxes ) {
							$tax_rates = $cart->tax->get_rates( $tax_class );
							$factor = 1;
							foreach ( $tax_rates as $rate )
								$factor += $rate['rate']/100;
							$cost /= $factor;
						}
						
						
						$cost = apply_filters( "woocommerce_pay4pay_{$current_gateway->id}_amount" , $cost , $calculation_base , $current_gateway , $taxable , $include_taxes , $tax_class );
						$cost = round($cost,2);
						
						$this->_fee = (object) array(
							'fee_title' => $fee_title,
							'cost' => $cost,
							'taxable' => $taxable,
							'tax_class' => $tax_class,
						);
						$cart->calculate_totals();
						return;
					}
				}
			}
		}
	}
	function get_current_gateway() {
		/**
		 *	The Stripe for woocommerce plugin considers itself unavailable if cart total is below 50 ct.
		 *	At this point the cart total is not yet calculated and equals zero resulting in s4wc being unavaliable.
		 *	We use `WC()->payment_gateways->payment_gateways()` in favor of `WC()->payment_gateways->get_available_payment_gateways()`
		 */
		$available_gateways = WC()->payment_gateways->payment_gateways();

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
	
	public function get_woocommerce_tax_classes() {
		// I can't belive it really works like this!
		$tax_classes = array_filter( array_map('trim', explode( "\n", get_option( 'woocommerce_tax_classes' ) ) ) );
		$tax_class_options = array();
		$tax_class_options[''] = __( 'Standard', 'woocommerce' );
		if ( $tax_classes )
			foreach ( $tax_classes as $class )
				$tax_class_options[ sanitize_title( $class ) ] = esc_attr( $class );
		return $tax_class_options;
	}
}

Pay4Pay::instance();

if ( is_admin() )
	require_once plugin_dir_path(__FILE__) . '/admin/class-pay4pay-admin.php';
