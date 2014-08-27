<?php
/*
Plugin Name: WooCommerce Pay for Payment
Plugin URI: http://wordpress.org/plugins/woocommerce-pay-for-payment
Description: Setup individual charges for each payment method in woocommerce.
Version: 1.3.0
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
	private $payment_fee = null;

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
		load_plugin_textdomain( 'pay4pay' , false, dirname( plugin_basename( __FILE__ )) . '/lang' );
//		add_action( 'woocommerce_cart_calculate_fees' , array($this,'add_pay4payment' ) , 99 ); // make sure this is the last fee eing added
		add_action( 'woocommerce_calculate_totals' , array($this,'add_pay4payment' ) , 99 );
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
				if ( ! $disable_on_free_shipping || ! in_array( 'free_shipping' , WC()->session->get( 'chosen_shipping_methods' )) ) {
					$cost = floatval($settings['pay4pay_charges_fixed']);
				
					//  √ $this->cart_contents_total + √ $this->tax_total + √ $this->shipping_tax_total + $this->shipping_total + $this->fee_total,
					
					if ( $percent = floatval($settings['pay4pay_charges_percentage']) ) {
						
						/*
						// okay! Already present at calculate_fees()
						if ( $include_cart_taxes )
							$calculation_base = $cart->subtotal;
						else 
							$calculation_base = $cart->subtotal_ex_tax;

						if ( $include_shipping ) {
							// okay! Already present at calculate_fees()
							$calculation_base += $cart->shipping_total;
							if ( $include_cart_taxes ) {
								$calculation_base += $cart->shipping_tax_total;
							}

						}

						if ( $include_fees ) {
							$cart_tax = $cart->subtotal - $cart->subtotal_ex_tax;
							$fee_tax = $cart->tax_total - $cart_tax;
							$calculation_base += $cart->fee_total;
							if ( $include_cart_taxes )
								$calculation_base += $fee_tax;
							$calculation_base -= $cart->discount_total + $cart->discount_cart;
						}
						/*/
						
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
						
						//*/
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

						if ( $taxable && $include_taxes ) {
							$tax_rates = $cart->tax->get_rates( $tax_class );
							$factor = 1;
							foreach ( $tax_rates as $rate )
								$factor += $rate['rate']/100;
							$cost /= $factor;
						}
						
						$cost = apply_filters( "woocommerce_pay4pay_{$current_gateway->id}_amount" , $cost , $calculation_base , $current_gateway , $taxable , $include_taxes , $tax_class );
						$cost = round($cost,2);
						
						$cart->add_fee( $fee_title , 
										$cost , 
										$taxable , 
										$tax_class
									);
						
						/*
						woocommerce is calculating the total from:
							$cart->cart_contents_total 
						+	$cart->tax_total 
						+	$cart->shipping_tax_total 
						+	$cart->shipping_total 
						-	$cart->discount_total 
						+	$cart->fee_total
						
						Adding a payment fee affects $cart->fee_total, $cart->tax_total and $cart->taxes.
						so we need to (re-)calculate these values exactly the way woocommerce does.
						
						This is due to change.
						*/
						
						// ### BEGIN woocommerce fee recalculation ####
						// do with payment fee as in WC_Cart->calculate_fees()
						foreach ( $cart->fees as $fee_key => $fee ) {
							// our fee:
							if ( $fee->id == $fee_id ) {
								// add fee to total
								$cart->fee_total += $fee->amount;
								
								if ( $fee->taxable ) {
									$tax_rates = $cart->tax->get_rates( $fee->tax_class );
									$fee_taxes = $cart->tax->calc_tax( $fee->amount, $tax_rates, false );
					
									if ( ! empty( $fee_taxes ) ) {
										// Set the tax total for this fee
										$cart->fees[ $fee_key ]->tax = array_sum( $fee_taxes );

										// Tax rows - merge the totals we just got
										foreach ( array_keys( $cart->taxes + $fee_taxes ) as $key ) {
											$cart->taxes[ $key ] = ( isset( $fee_taxes[ $key ] ) ? $fee_taxes[ $key ] : 0 ) + ( isset( $cart->taxes[ $key ] ) ? $cart->taxes[ $key ] : 0 );
										}
									}
								}
								break;
							}
						}
						// ### END woocommerce fee recalculation ####

						// ### BEGIN woocommerce tax recalculation ####
						// Total up/round taxes and shipping taxes
						// calc taxes as seen in WC_Cart->calculate_totals()
						if ( $cart->round_at_subtotal ) {
							$cart->tax_total          = $cart->tax->get_tax_total( $cart->taxes );
						//	$cart->shipping_tax_total = $cart->tax->get_tax_total( $cart->shipping_taxes );
							$cart->taxes              = array_map( array( $cart->tax, 'round' ), $cart->taxes );
						//	$cart->shipping_taxes     = array_map( array( $cart->tax, 'round' ), $cart->shipping_taxes );
						} else {
							$cart->tax_total          = array_sum( $cart->taxes );
						//	$cart->shipping_tax_total = array_sum( $cart->shipping_taxes );
						}

						// VAT exemption done at this point - so all totals are correct before exemption
						if ( WC()->customer->is_vat_exempt() ) {
							$cart->remove_taxes();
						}
						// ### END woocommerce tax recalculation ####
						
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
