WooCommerce Pay for Payment
===========================

About
-----
Add individual charges for each payment method as a flat rate and/or as a percentage of the cart total.
The plugin first calculates the percentage rate and then adds the fixed rate on top.
Coupons are not supported. (Sorry guys. I tried, but no way.)

You will find a stable version in [WordPress plugin directory](http://wordpress.org/plugins/woocommerce-pay-for-payment/).

Features
--------
- Fixed charge and/or a percentage of cart total
- Translation ready
- German, Spanish ([muchas graçias!](https://github.com/GosserBox)) and Turkish localization ([çok teşekkürler!](https://github.com/TRRF))

Plugin API
----------
##### Filter `woocommerce_pay4pay_{$current_gateway_id}_amount`: #####
Applied to the payment gateway fee before it is added to woocomerce' cart.

*Example:*

	function my_pay4pay_amount( $amount , $calculation_base , $current_payment_gateway , $taxable , $include_taxes , $tax_class ) {
		if ( my_customer_complained_too_much() )
			return $amount * 10;
		else
			return $amount;
	}
	$current_gateway_id = 'cod';
	add_filter( "woocommerce_pay4pay_{$current_gateway_id}_amount", 'my_pay4pay_amount' , 10 , 6 );


##### Filter `woocommerce_pay4pay_apply`: #####
Handle if a payment fee is applied.

*Example:*

	function my_pay4pay_handle_christmas( $do_apply , $amount , $cart_subtotal , $current_payment_gateway ) {
		if ( today_is_christmas() )
			return false;
		else
			return $do_apply;
	}
	add_filter( "woocommerce_pay4pay_apply", 'my_pay4pay_handle_christmas' , 10 , 4 );



##### Filter `woocommerce_pay4pay_applyfor_{$current_gateway_id}`: #####
Handle if a payment fee on a specific payment method should be applied.

*Example:*

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
- Tested up to WP 4.0-beta3 / WC 2.1.12
- Requires at least WooCommerce 2.1
- Not compatible with PayPal policy. Details: [PayPal User Agreement](https://www.paypal.com/webapps/mpp/ua/useragreement-full?country.x=US&locale.x=en_US#4), > "4.6 No Surcharges". You have been warned.

Support
-------
You like what you see? Maybe you already make some money with it? 
Here are two options to keep me rocking:

<a href="http://flattr.com/thing/3468992/mcguffinwoocommerce-payforpayment-on-GitHub" target="_blank"><img src="http://api.flattr.com/button/flattr-badge-large.png" alt="Flattr this" title="Flattr this" border="0" /></a>
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=F8NKC6TCASUXE"><img src="https://www.paypalobjects.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!" /></a>
