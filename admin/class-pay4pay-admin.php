<?php
/**
 * Pay4Pay Admin
 *
 * @package	Pay4Pay
 * @since	1.2.0
 */

if ( ! class_exists( 'Pay4Pay_Admin' ) ) :

class Pay4Pay_Admin {

	private static $_instance = null;

	public static function instance(){
		if ( is_null(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}

	private function __construct() {
		// handle options
		add_action( 'woocommerce_init' , array( &$this , 'add_payment_options'), 99 );
		add_action( 'woocommerce_update_options_checkout' , array( &$this , 'add_payment_options') );
		
		// payment gateways table
		add_filter( 'woocommerce_payment_gateways_setting_columns' , array( &$this , 'add_extra_fee_column' ) );
		add_action( 'woocommerce_payment_gateways_setting_column_pay4pay_extra' , array( &$this , 'extra_fee_column_content' ) );
		
		// settings script
		add_action( 'load-woocommerce_page_wc-settings' , array( &$this , 'enqueue_checkout_settings_js' ) );
	}

	public function enqueue_checkout_settings_js(){
		if ( isset( $_GET['tab'] ) && $_GET['tab'] == 'checkout' ) {
			wp_enqueue_script( 'pay4pay_settings_checkout' , plugins_url( '/js/pay4pay-settings-checkout.js' , dirname(__FILE__) ) , array('woocommerce_admin') );
			wp_enqueue_style( 'pay4pay_settings_checkout' , plugins_url( '/css/pay4pay-settings-checkout.css' , dirname(__FILE__) ) , array() );
		}
	}

	function add_payment_options( ) {
		$defaults = Pay4Pay::get_default_settings();
		$tax_class_options = Pay4Pay::instance()->get_woocommerce_tax_classes();

		// general
		$form_fields = array(
			'pay4pay_title' => array(
				'title' => __( 'Extra Charge', 'woocommerce-payforpayment' ),
				'type' => 'title',
				'class' => 'pay4pay-title',
				'description' => '',
			),
			'pay4pay_item_title' => array(
				'title' => __( 'Item Title', 'woocommerce-payforpayment' ),
				'type' => 'text',
				'description' => __( 'This will show up in the shopping basket.', 'woocommerce-payforpayment' ),
				'desc_tip' => true,
			),
			'pay4pay_charges_fixed' => array(
				'title' => __( 'Fixed charge', 'woocommerce-payforpayment' ),
				'type' => 'number',
				'description' => __( 'Extra charge to be added to cart when this payment method is selected.', 'woocommerce-payforpayment' ),
				'desc_tip' => true,
				'custom_attributes' => array(
					'step' => 'any',
				),
			),
			'pay4pay_charges_percentage' => array(
				'title' => __( 'Percent charge', 'woocommerce-payforpayment' ),
				'type' => 'number',
				'description' => __( 'Percentage of cart total to be added to payment.', 'woocommerce-payforpayment' ),
				'desc_tip' => true,
				'custom_attributes' => array(
					'step' => 'any',
					'data-setchangehandler' => '1',
					'data-reference-name' => 'woocommerce-pay4pay-percentage',
					
				),
				'id' => 'woocommerce-pay4pay-percentage',
			),
			'pay4pay_charges_minimum' => array(
				'title' => __( 'Charge at least', 'woocommerce-payforpayment' ),
				'type' => 'number',
				'description' => __( 'Minimum extra charge to be added to cart when this payment method is selected.', 'woocommerce-payforpayment' ),
				'desc_tip' => true,
				'custom_attributes' => array(
					'step' => 'any',
					'data-dependency-notzero' => 'woocommerce-pay4pay-percentage',
				),
			),
			'pay4pay_charges_maximum' => array(
				'title' => __( 'Charge at most', 'woocommerce-payforpayment' ),
				'type' => 'number',
				'description' => __( 'Maximum extra charge to be added to cart when this payment method is selected. Enter zero to disable.', 'woocommerce-payforpayment' ),
				'desc_tip' => true,
				'custom_attributes' => array(
					'step' => 'any',
					'data-dependency-notzero' => 'woocommerce-pay4pay-percentage',
				),
			),
			'pay4pay_disable_on_free_shipping' => array(
				'title' => __( 'Disable on Free Shipping' , 'woocommerce-payforpayment' ),
				'label' => __( 'Don’t charge this fee when free shipping is available.' , 'woocommerce-payforpayment' ),
				'type' => 'checkbox',
				'desc_tip' => true,
			),
			
		);
		
		// taxes
		if ( 'yes' == get_option('woocommerce_calc_taxes') ) {
			$form_fields += array(
				'pay4pay_title_taxes' => array(
					'title' => __( 'Extra Charge Taxes', 'woocommerce-payforpayment' ),
					'type' => 'title',
					'class' => 'pay4pay-title',
				),
				'pay4pay_taxes' => array(
					'title' => __('Taxable','woocommerce-payforpayment' ),
					'type' => 'checkbox',
					'label' => __( 'Payment fee is taxable', 'woocommerce-payforpayment' ),
					'custom_attributes' => array( 
						'data-setchangehandler' => '1' ,  
						'data-reference-name' => 'woocommerce-pay4pay-taxes',
					),
				),
				'pay4pay_includes_taxes' => array(
					'title' => __('Inclusive Taxes','woocommerce-payforpayment' ),
					'type' => 'checkbox',
					'label' => __( 'The payment fee is inclusive of taxes.', 'woocommerce-payforpayment' ),
					'description' => __('If you leave this unchecked taxes will be calculated on top of the payment fee.' , 'woocommerce-payforpayment' ),
					'desc_tip' => true,
					'class' => 'pay4pay_taxes',
					'custom_attributes' => array( 'data-dependency-notzero' => 'woocommerce-pay4pay-taxes' ),
				),
				'pay4pay_tax_class' => array(
					'title' => __('Tax class','woocommerce-payforpayment' ),
					'type' => 'select',
					'description' => __( 'Select a the tax class applied to the extra charge.', 'woocommerce-payforpayment' ),
					'options' => $tax_class_options,
					'desc_tip' => true,
					'class' => 'pay4pay_taxes', // display when pay4pay_taxes != 0
					'custom_attributes' => array( 'data-dependency-notzero' => 'woocommerce-pay4pay-taxes' ),
				),
			);
		}
		
		// include in calculation
		$form_fields += array(
			// which cart items to include in calculation
			'pay4pay_title_include' => array(
				'title' => __( 'Include in percental payment fee calculation:', 'woocommerce-payforpayment' ),
				'type' => 'title',
				'class' => 'pay4pay-title dependency-notzero-woocommerce-pay4pay-percentage',
				'custom_attributes' => array( 'data-dependency-notzero' => 'woocommerce-pay4pay-percentage' ),
			),
			'pay4pay_enable_extra_fees' => array( 
				'title' => __('Fees','woocommerce-payforpayment' ),
				'type' => 'checkbox',
				'label' => __( 'Include fees in calculation.', 'woocommerce-payforpayment' ),
				'desc_tip' => true,
				'class' => 'pay4pay_charges_percentage',
				'custom_attributes' => array( 'data-dependency-notzero' => 'woocommerce-pay4pay-percentage' ),
			),
			'pay4pay_include_coupons' => array(
				'title' => __('Coupons','woocommerce-payforpayment' ),
				'type' => 'checkbox',
				'label' => __( 'Include Coupons in calculation.', 'woocommerce-payforpayment' ),
				'desc_tip' => true,
				'class' => 'pay4pay_charges_percentage',
				'custom_attributes' => array( 'data-dependency-notzero' => 'woocommerce-pay4pay-percentage' ),
			),
			'pay4pay_include_shipping' => array(
				'title' => __('Shipping','woocommerce-payforpayment' ),
				'type' => 'checkbox',
				'label' => __( 'Include shipping cost in calculation.', 'woocommerce-payforpayment' ),
				'desc_tip' => true,
				'class' => 'pay4pay_charges_percentage',
				'custom_attributes' => array( 'data-dependency-notzero' => 'woocommerce-pay4pay-percentage' ),
			),
		);
		if ( 'yes' == get_option('woocommerce_calc_taxes') ) {
			$form_fields += array(
				'pay4pay_include_cart_taxes' => array(
					'title' => __('Taxes','woocommerce-payforpayment' ),
					'type' => 'checkbox',
					'label' => __( 'Include taxes in calculation.', 'woocommerce-payforpayment' ),
					'desc_tip' => true,
					'class' => 'pay4pay_charges_percentage',
					'custom_attributes' => array( 'data-dependency-notzero' => 'woocommerce-pay4pay-percentage' ),
				),
			);
		}
		
		
		foreach ( $defaults as $option_key => $default_value )
			if ( array_key_exists( $option_key, $form_fields ) )
				$form_fields[$option_key]['default'] = $default_value;
		
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
			$form_fields['pay4pay_item_title']['default'] = $gateway->title;
			$gateway->form_fields += $form_fields;
			add_action( 'woocommerce_update_options_payment_gateways_'.$gateway->id , array($this,'update_payment_options') , 20 );
		}
	}
	
	function update_payment_options() {
		global $current_section;
		$class = new $current_section();
		$prefix = 'woocommerce_'.$class->id;
		$opt_name = $prefix.'_settings';
		$options = get_option( $opt_name );
		// validate!
		$extra = array(
			'pay4pay_item_title' 				=> sanitize_text_field( $_POST[$prefix.'_pay4pay_item_title'] ),
			'pay4pay_charges_fixed' 			=> floatval( $_POST[$prefix.'_pay4pay_charges_fixed'] ),
			'pay4pay_charges_percentage' 		=> floatval( $_POST[$prefix.'_pay4pay_charges_percentage'] ),
			'pay4pay_charges_minimum'			=> floatval( $_POST[$prefix.'_pay4pay_charges_minimum'] ),
			'pay4pay_charges_maximum'			=> floatval( $_POST[$prefix.'_pay4pay_charges_maximum'] ),
			'pay4pay_disable_on_free_shipping'	=> $this->_get_bool( $prefix.'_pay4pay_disable_on_free_shipping' ), 
			
			'pay4pay_taxes' 					=> $this->_get_bool( $prefix.'_pay4pay_taxes' ),
			'pay4pay_includes_taxes'			=> $this->_get_bool( $prefix.'_pay4pay_includes_taxes'),
			'pay4pay_tax_class' 				=> $this->_sanitize_tax_class($_POST[$prefix.'_pay4pay_tax_class']), // 0, incl, excl
			
			'pay4pay_enable_extra_fees'			=> $this->_get_bool( $prefix.'_pay4pay_enable_extra_fees' ),
			'pay4pay_include_shipping'			=> $this->_get_bool( $prefix.'_pay4pay_include_shipping'),
			'pay4pay_include_coupons'			=> $this->_get_bool( $prefix.'_pay4pay_include_coupons'),
			'pay4pay_include_cart_taxes'		=> $this->_get_bool( $prefix.'_pay4pay_include_cart_taxes'),
		);
		$options += $extra;
		update_option( $opt_name , $options );
	}
	private function _sanitize_tax_option( $tax_option , $default = 'incl' ) {
		if ( in_array( $tax_option , array(0,'incl','excl') ) )
			return $tax_option;
		return $default;
	}
	private function _sanitize_tax_class( $tax_option , $default = 'incl' ) {
		if ( in_array( $tax_option , array(0,'incl','excl') ) )
			return $tax_option;
		return $default;
	}
	private function _get_bool( $key ) {
		return isset($_POST[ $key ]) && $_POST[ $key ] === '1' ? 'yes' : 'no';
	}
	private function _get_float( $key ) {
		return isset($_POST[ $key ]) && $_POST[ $key ] === '1' ? 'yes' : 'no';
	}
	
	/*
	Handline columns in Woocommerce > settings > checkout
	*/
	public function add_extra_fee_column( $columns ){
		$return = array_slice($columns,0,-1,true)
			+ array( 'pay4pay_extra' => __( 'Extra Charge', 'woocommerce-payforpayment' ) )
			+ array_slice($columns,-1,1,true);
		return $return;
	}
	public function extra_fee_column_content( $gateway ) {
		?><td><?php
			if ( isset( $gateway->settings['pay4pay_charges_fixed']) ) {
				$items = array();
//				$items[] = sprintf('<strong>%s</strong>',$gateway->settings['pay4pay_item_title']);
				if (  $gateway->settings['pay4pay_charges_fixed'] )
					$items[] = wc_price($gateway->settings['pay4pay_charges_fixed'] );
				if ( $gateway->settings['pay4pay_charges_percentage'] ) {
					$items[] = sprintf( _x( '%s %% of cart totals', 'Gateway list column' , 'pay4pay' ) , $gateway->settings['pay4pay_charges_percentage'] );
					
					if ( isset( $gateway->settings['pay4pay_charges_minimum'] ) && $gateway->settings['pay4pay_charges_minimum'] )
						$items[] = wc_price($gateway->settings['pay4pay_charges_minimum'] );
					if ( isset($gateway->settings['pay4pay_charges_maximum']) && $gateway->settings['pay4pay_charges_maximum'] )
						$items[] = wc_price($gateway->settings['pay4pay_charges_maximum'] );
				}
				echo implode('<br />',$items);
			}
		?></td><?php
	}
	
}

Pay4Pay_Admin::instance();

endif;
