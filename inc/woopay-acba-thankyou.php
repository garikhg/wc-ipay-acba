<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'ipay_acba_thankyou' ) ) {
	/**
	 * Displays an error message if the order with the given ID has a status of 'failed'.
	 *
	 * @param int $order_id The ID of the order to check.
	 *
	 * @return bool Returns true.
	 * @since 1.0.0
	 */
	function ipay_acba_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( $order->has_status( 'failed' ) ) {
			$order_failed_message = get_post_meta( $order_id, 'ipay_acba_failed_message', true );
			if ( $order_failed_message ) {
				echo sprintf( '<div class="ipay-error ipay-error-danger"><strong>%s</strong> %s</div>', __( 'Error!', 'woopay-acba' ), $order_failed_message );
			}
		}

		return true;
	}

	add_action( 'woocommerce_thankyou', 'ipay_acba_thankyou', 4 );
}
