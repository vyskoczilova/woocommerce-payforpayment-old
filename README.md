WooCommerce Pay for Payment
===========================

About
-----
Add individual charges for each payment method as a flat rate and/or as a percentage of the cart total.
The plugin first calculates the percentage rate and then adds the fixed rate on top.
Coupons are not supported. (Sorry guys. I tried, but no way.)

Features
--------
- Fixed charge and/or a percentage of cart total
- Translation ready
- German, Spanish ([muchas graçias!](https://github.com/GosserBox)) and Turkish localization ([çok teşekkürler!](https://github.com/TRRF))

Plugin API
----------
##### Filter `woocommerce_pay4pay_{$current_gateway_id}_amount`: #####
Applied to the payment gateway fee before it is added to woocomerce' cart.

**Usage:**

	function my_pay4pay_amount( $amount , $cart_subtotal , $current_payment_gateway ) {
		if ( my_customer_complained_too_much() )
			return $amount * 10;
		else
			return $amount;
	}
	$current_gateway_id = 'cod';
	add_filter( "woocommerce_pay4pay_{$current_gateway_id}_amount", 'my_pay4pay_amount' , 10 , 3 );


##### Filter `woocommerce_pay4pay_applyfor_{$current_gateway_id}`: #####
Handle if a payment fee should be applied.

**Usage:**

	function my_pay4pay_apply( $do_apply , $amount , $cart_subtotal , $current_payment_gateway ) {
		if ( my_customer_is_a_nice_guy() )
			return false;
		else
			return $do_apply;
	}
	$current_gateway_id = 'cod';
	add_filter( "woocommerce_pay4pay_applyfor_{$current_gateway_id}", 'my_pay4pay_apply' , 10 , 4 );



Compatibility
-------------
- Developed with WP 3.6 and woocommerce 2.0.14
- Tested up to WP 3.7.1 / WC 2.0.19
- Not compatible with PayPal policy. Details: [PayPal User Agreement](https://www.paypal.com/webapps/mpp/ua/useragreement-full?country.x=US&locale.x=en_US#4), > "4.6 No Surcharges". You have been warned.

