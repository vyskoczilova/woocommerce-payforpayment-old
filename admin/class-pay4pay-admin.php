<?php
/**
 * Pay4Pay Admin
 *
 * @package	Pay4Pay
 * @since	1.2.0
 */


class Pay4Pay_Admin {

	private static $_instance = null;

	public static function instance(){
		if ( is_null(self::$_instance) )
			self::$_instance = new self();
		return self::$_instance;
	}

	private function __construct() {
		add_action( 'woocommerce_init' , array( &$this , 'add_payment_options') );
		add_action( 'woocommerce_update_options_checkout' , array( &$this , 'add_payment_options') );
		/*
		add_filter('woocommerce_payment_gateways_settings' array(  ) );
		*/
		add_filter('woocommerce_payment_gateways_setting_columns' , array( $this , 'add_extra_fee_column' ) );
		add_action('woocommerce_payment_gateways_setting_column_pay4pay_extra' , array( $this , 'extra_fee_column_content' ) );
	}


	function add_payment_options( ) {
		$defaults = Pay4Pay::get_default_settings();
		$form_fields = array(
			'pay4pay_title' => array(
				'title' => __( 'Extra Charge', 'pay4pay' ),
				'type' => 'title',
				'description' => '',
			),
			'pay4pay_item_title' => array(
				'title' => __( 'Item Title', 'pay4pay' ),
				'type' => 'text',
				'description' => __( 'This will show up in the shopping basket.', 'pay4pay' ),
				'desc_tip' => true,
				'custom_attributes' => array(
				),
			),
			'pay4pay_charges_fixed' => array(
				'title' => __( 'Fixed charge', 'pay4pay' ),
				'type' => 'number',
				'description' => __( 'Extra charge to be added to cart when this payment method is selected.', 'pay4pay' ),
				'desc_tip' => true,
				'custom_attributes' => array(
					'step' => 'any',
				),
			),
			'pay4pay_charges_percentage' => array(
				'title' => __( 'Percent charge', 'pay4pay' ),
				'type' => 'number',
				'description' => __( 'Percentage of cart total to be added to payment.', 'pay4pay' ),
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
				'desc_tip' => true,
			),
			'pay4pay_enable_extra_fees' => array(
				'title' => __('Enable Extra Fees','pay4pay'),
				'type' => 'checkbox',
				'label' => __( 'If checked all extra fees and coupons will be included in the calculation of the fee.', 'pay4pay' ),
				'desc_tip' => true,
			),
			'pay4pay_include_shipping' => array(
				'title' => __('Include Shipping','pay4pay'),
				'type' => 'checkbox',
				'label' => __( 'Check this if shipping cost should be included in the calculation of the payment fee.', 'pay4pay' ),
				'desc_tip' => true,
			),
			'pay4pay_disable_on_free_shipping' => array(
				'title' => __( 'Free Shipping' , 'pay4pay' ),
				'label' => __( 'Don’t charge this fee when free shipping is available.' , 'pay4pay' ),
				'type' => 'checkbox',
				'desc_tip' => true,
			),
		);
		foreach ( $defaults as $option_key => $default_value )
			$form_fields[$option_key]['default'] = $default_value;
		
		foreach ( WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
			$form_fields['pay4pay_item_title']['default'] = $gateway->title;
			$gateway->form_fields += $form_fields;
			add_action( 'woocommerce_update_options_payment_gateways_'.$gateway->id , array($this,'update_payment_options') , 20 );
		}
	}
	
	function update_payment_options(  ) {
		global $current_section;
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
			'pay4pay_enable_extra_fees' => isset($_POST[$prefix.'_pay4pay_enable_extra_fees']) && $_POST[$prefix.'_pay4pay_enable_extra_fees'] === '1' ? 'yes' : 'no' ,
			'pay4pay_include_shipping' => isset($_POST[$prefix.'_pay4pay_include_shipping']) && $_POST[$prefix.'_pay4pay_include_shipping'] === '1' ? 'yes' : 'no' ,
			'pay4pay_disable_on_free_shipping' => isset($_POST[$prefix.'_pay4pay_disable_on_free_shipping']) && $_POST[$prefix.'_pay4pay_disable_on_free_shipping'] === '1' ? 'yes' : 'no' ,
		);
		$options += $extra;
		update_option( $opt_name , $options );
	}
	private function _sanitize_tax_option( $tax_option , $default = 'incl' ) {
		if ( in_array( $tax_option , array(0,'incl','excl') ) )
			return $tax_option;
		return $default;
	}
	
	
	/*
	Handline columns in Woocommerce > settings > checkout
	*/
	public function add_extra_fee_column( $columns ){
		$return = array_slice($columns,0,-1,true)
			+ array( 'pay4pay_extra' => __( 'Extra Charge', 'pay4pay' ) )
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
				}
				echo implode('<br />',$items);
			}
		?></td><?php
	}
	
}

Pay4Pay_Admin::instance();