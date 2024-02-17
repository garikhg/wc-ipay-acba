<?php
/**
 * Plugin Name: iPay ACBA
 * Plugin URI: https://garikhg.github.io/woo-ipay-acba/
 * Description: Pay with ACBA Bank is a seamless payment system tailored for transactions in Armenian Dram. Pay with ACBA Bank ensures swift and secure payments for various goods and services.
 * Version: 1.0.0-beta2
 * Author: Garegin Hakobyan
 * Author URI: #
 * Text Domain: ipay-acba
 * Domain Path: /languages
 * License: GPLv3 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'WOOPAY_ACBA_PLUGIN_VERSION', '1.0.0-beta2' );
define( 'WOOPAY_ACBA_PLUGIN_DIR', dirname( __FILE__ ) );

/**
 * Adds the iPay ACBA Payment Gateway to the list of available gateways.
 *
 * This function appends the WooPay_Acba_Payment_Gateway class to the $gateways array.
 *
 * @param array $gateways An array of available gateways.
 *
 * @return array Updated array with WooPay ACBA Payment Gateway added.
 */
function ipay_acba_payment_gateway( $gateways ) {
	$gateways[] = 'iPayAcba_Payment_Gateway';

	return $gateways;
}

add_filter( 'woocommerce_payment_gateways', 'ipay_acba_payment_gateway' );


/**
 * Initializes the iPay ACBA Gateway plugin.
 *
 * Loads the plugin text domain for translation support and includes the necessary files.
 *
 * @return void
 */
function ipay_acba_init_class() {
	load_plugin_textdomain( 'ipay-acba', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	require WOOPAY_ACBA_PLUGIN_DIR . '/inc/class-ipay-acba-gateway.php';
}

add_action( 'plugins_loaded', 'ipay_acba_init_class' );

require WOOPAY_ACBA_PLUGIN_DIR . '/inc/ipay-acba-helpers.php';
require WOOPAY_ACBA_PLUGIN_DIR . '/inc/ipay-acba-thankyou.php';
