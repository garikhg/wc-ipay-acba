<?php
/**
 * Plugin Name: iPay ACBA Bank Payment Gateway
 * Plugin URI: https://github.com/garikhg/wc-acba-gateway.git
 * Description: Pay with ACBA Bank is a seamless payment system tailored for transactions in Armenian Dram. Pay with ACBA Bank ensures swift and secure payments for various goods and services.
 * Version: 0.0.1
 * Author: Garegin Hakobyan
 * Author URI: #
 * Text Domain: wc-ipay-acba
 * Domain Path: /languages
 * License: GPLv3 or later
 */

defined( 'ABSPATH' ) || exit;

define( 'WCIPAY_ACBA_PLUGIN_VERSION', '0.0.1' );
define( 'WCIPAY_ACBA_PLUGIN_DIR', dirname( __FILE__ ) );

if ( ! function_exists( 'ipay_acba_payment_gateway' ) ) {
	/**
	 * Adds the iPay Acba Payment Gateway to the list of available gateways.
	 *
	 * Appends the 'iPay_Acba_Payment_Gateway' to the array of available gateways, allowing it to be used as a payment method.
	 *
	 * @param array $gateways The array of available gateways.
	 *
	 * @return array The updated array of available gateways, including the iPay Acba Payment Gateway.
	 */
	function ipay_acba_payment_gateway( $gateways ) {
		$gateways[] = 'iPay_Acba_Payment_Gateway';

		return $gateways;
	}

	add_filter( 'woocommerce_payment_gateways', 'ipay_acba_payment_gateway' );
}


if ( ! function_exists( 'ipay_acba_init_class' ) ) {
	/**
	 * Initializes the iPay ACBA Class for the WooCommerce plugin.
	 *
	 * Loads the plugin's text domain and includes the IPay Acba Gateway class file.
	 *
	 * @return void
	 */
	function ipay_acba_init_class() {
		load_plugin_textdomain( 'wc-ipay-acba', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		require WCIPAY_ACBA_PLUGIN_DIR . '/inc/class-ipay-acba-gateway.php';
	}

	add_action( 'plugins_loaded', 'ipay_acba_init_class' );
}

require WCIPAY_ACBA_PLUGIN_DIR . '/inc/wc-ipay-acba-thankyou.php';
