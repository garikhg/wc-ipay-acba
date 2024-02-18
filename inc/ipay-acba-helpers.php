<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ipay_acba_redirect_pay_for_order' ) ) {
	/**
	 * Redirects the user to the payment page for the specified order ID.
	 *
	 * @param int $order_id The ID of the order.
	 *
	 * @return void
	 */
	function ipay_acba_redirect_pay_for_order( $order_id ) {
		$pay_for_order_url = wc_get_checkout_url() . '?pay_for_order=true&order=' . $order_id;

		// Redirect the user
		wp_redirect( $pay_for_order_url );
		exit;
	}
}
