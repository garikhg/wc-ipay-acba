<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'iPayAcba_Payment_Gateway' ) ) {
	/**
	 * iPayACBA Main Class.
	 *
	 * @param int $order_id The ID of the order to change the status.
	 * @param string $new_status The new status to set for the order.
	 * @param string $old_status The old status of the order.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	class iPayAcba_Payment_Gateway extends WC_Payment_Gateway {
		/**
		 * Variable to store the currency code.
		 *
		 * @var string
		 */
		private string $currency_code;
		/**
		 * Indicates whether the system is in test mode or not.
		 *
		 * @var boolean
		 */
		private bool $testmode;

		/**
		 * The selected language of the system.
		 *
		 * @var string
		 */
		private $language;

		/**
		 * Represents the ID of the shop.
		 *
		 * @var int
		 */
		private $shop_id;

		/**
		 * The password used for authentication in the shop.
		 *
		 * @var string
		 */
		private $shop_password;

		/**
		 * The URL of the API endpoint.
		 *
		 * @var string
		 */
		private string $api_url;

		/**
		 * Initializes the payment gateway.
		 *
		 * Sets the necessary properties for the payment gateway.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function __construct() {
			global $woocommerce;

			$this->id                 = 'ipay_acba';
			$this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name
			$this->has_fields         = true;
			$this->method_title       = __( 'ACBA Bank Payment Gateway', 'ipay-acba' );
			$this->method_description = __( 'Pay with ACBA Bank is a seamless payment system tailored for transactions in Armenian Dram.', 'ipay-acba' );

			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			// gateways can support subscriptions, refunds, saved payment methods,
			// but in this tutorial we begin with simple payments
			$this->supports = array(
				'products',
				'refunds',
			);

			// Method with all the options fields
			$this->init_form_fields();
			$this->init_settings();

			$this->testmode      = $this->get_option( 'testmode' ) == 'yes';
			$this->language      = $this->get_option( 'language' );
			$this->shop_id       = $this->testmode ? $this->get_option( 'test_shop_id' ) : $this->get_option( 'live_shop_id' );
			$this->shop_password = $this->testmode ? $this->get_option( 'test_shop_password' ) : $this->get_option( 'live_shop_password' );
			$this->api_url       = $this->testmode ? 'https://ipaytest.arca.am:8445/payment/rest' : 'https://ipay.arca.am/payment/rest';
			$this->merchants_url = 'https://ipay.arca.am/payment/merchants/';

			$woo_currency        = get_woocommerce_currency();
			$currencies          = array( 'AMD' => '051', 'RUB' => '643', 'USD' => '840', 'EUR' => '978' );
			$this->currency_code = $currencies[ $woo_currency ];

			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
				array( $this, 'process_admin_options' )
			);

			add_action( 'woocommerce_api_ipay_acba_successful', [ $this, 'ipay_acba_pay_successful' ] );
			add_action( 'woocommerce_api_ipay_acba_failed', [ $this, 'ipay_acba_pay_failed' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'payment_scripts' ] );

			// Order statuses
			add_action( 'woocommerce_order_status_changed', [ $this, 'ipay_acba_order_status_change' ], 10, 3 );

			// $this->log = version_compare( WooCommerce::instance()->version, '2.1', '<' ) ? $woocommerce->logger() : new WC_Logger();
		}

		/**
		 * Initializes the form fields for the payment gateway.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'            => [
					'title'       => __( 'Enable/Disable', 'ipay-acba' ),
					'label'       => __( 'Enable Payment Gateway', 'ipay-acba' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				],
				'title'              => [
					'title'       => __( 'Title', 'ipay-acba' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'ipay-acba' ),
					'default'     => __( 'Pay via Credit card / debit card', 'ipay-acba' )
				],
				'description'        => [
					'title'       => __( 'Description', 'ipay-acba' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'ipay-acba' ),
					'default'     => '',
				],
				'language'           => [
					'title'       => __( 'Interface Language', 'ipay-acba' ),
					'type'        => 'select',
					'options'     => [
						'hy' => __( 'Armenian', 'ipay-acba' ),
						'ru' => __( 'Russian', 'ipay-acba' ),
						'en' => __( 'English', 'ipay-acba' ),
					],
					'description' => __( 'The language of the bank purchase interface.', 'ipay-acba' ),
					'default'     => 'hy',
					'desc_tip'    => true,
				],
				'testmode'           => [
					'title'       => __( 'Test mode', 'ipay-acba' ),
					'label'       => __( 'Enable Test Mode', 'ipay-acba' ),
					'type'        => 'checkbox',
					'description' => __( 'Place the payment gateway in test mode using test API keys.', 'ipay-acba' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				],
				'test_shop_id'       => [
					'title' => __( 'Test Shop ID', 'ipay-acba' ),
					'type'  => 'text',
				],
				'test_shop_password' => [
					'title' => __( 'Test Shop Password', 'ipay-acba' ),
					'type'  => 'password',
				],
				'live_shop_id'       => [
					'title' => __( 'Shop ID', 'ipay-acba' ),
					'type'  => 'text',
				],
				'live_shop_password' => [
					'title' => __( 'Shop Password', 'ipay-acba' ),
					'type'  => 'password',
				]
			);
		}

		/**
		 * Displays the payment fields for the payment gateway.
		 * If a description is provided, it is displayed with proper text formatting.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function payment_fields() {
			if ( $description = $this->get_description() ) {
				echo wpautop( wptexturize( $description ) ); // @codingStandardsIgnoreLine.
			}
		}

		/**
		 * Loads the necessary scripts for the payment gateway.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function payment_scripts() {
			// todo: Implement the payment_scripts()
		}

		/**
		 * Validates the form fields for the payment gateway.
		 *
		 * @return bool True if the form fields are valid, false otherwise.
		 * @since 1.0.0
		 */
		public function validate_fields() {
			// todo: Implement the validate_fields()
		}

		/**
		 * Changes the status of an ACBA iPay order.
		 *
		 * @param int $order_id The ID of the order to change the status.
		 * @param string $status_from The current status of the order.
		 * @param string $status_to The desired status to update the order to ('completed' or 'cancelled').
		 *
		 * @return bool True if the status was changed successfully, false otherwise.
		 *
		 * @since 1.0.0
		 */
		public function ipay_acba_order_status_change( $order_id, $status_from, $status_to ) {
			$order = wc_get_order( $order_id );

			if ( wc_get_payment_gateway_by_order( $order )->id === 'ipay_acba' ) {
				if ( $status_to === 'completed' ) {
					return $this->ipay_acba_order_confirm( $order_id, $status_to );
				} elseif ( $status_to === 'cancelled' ) {
					return $this->ipay_acba_cancel_order( $order_id, $status_to );
				}
			}
		}

		/**
		 * Confirms the ACBA iPay order.
		 *
		 * @param int $order_id The ID of the order to confirm.
		 * @param string $status_to The status to update the order to ('completed' or 'active').
		 *
		 * @return bool True if the order was confirmed successfully, false otherwise.
		 *
		 * @link https://garikhg.github.io/woo-ipay-acba/docs/Merchant_Manual_1.55.1.0.pdf
		 *
		 * @since 1.0.0
		 */
		public function ipay_acba_order_confirm( $order_id, $status_to ) {
			$order = wc_get_order( $order_id );

			if ( ! $order->has_status( 'processing' ) ) {
				$params    = [];
				$amount    = floatval( $order->get_total() ) * 100;
				$PaymentID = get_post_meta( $order_id, 'PaymentID', true );

				$params[] = 'amount=' . (int) $amount;
				// $gateway_params[] = 'currency=' . $this->currency_code;
				$params[] = 'orderId=' . $PaymentID;
				$params[] = 'password=' . $this->shop_password;
				$params[] = 'userName=' . $this->shop_id;
				$params[] = 'language=' . $this->language;

				// $response = wp_remote_post( $this->api_url . '/deposit.do?' . implode( '&', $gateway_params ) );
				$response = wp_remote_post( $this->api_url . '/getOrderStatus.do?' . implode( '&', $params ) );
				if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
					if ( ! is_wp_error( $response ) ) {
						$body = json_decode( wp_remote_retrieve_body( $response ), false );

						if ( $body->errorCode == 0 && isset( $body->orderStatus ) && $body->orderStatus == 2 ) {
							if ( $status_to == 'completed' ) {
								$order->update_status( 'completed' );
							}

							return true;
						} else {
							if ( $status_to == 'completed' ) {
								$order->update_status( 'processing', $body->errorMessage );
							} else {
								$order->update_status( 'on-hold', $body->errorMessage );
							}
						}
						wp_die( $body->errorMessage );
					}
				} else {
					if ( $status_to == 'completed' ) {
						$order->update_status( 'processing' );
					} else {
						$order->update_status( 'on-hold' );
					}
					wp_die( sprintf( __( 'Connection error. Order confirm #%s is failed. Please try again.', 'ipay-acba' ), $order_id ) );
				}
			}
		}

		/**
		 * Cancels the order in the iPay ACBA payment gateway.
		 *
		 * @param int $order_id The ID of the order to be cancelled.
		 *
		 * @return bool Returns true if the order was successfully cancelled, false otherwise.
		 * @since 1.0.0
		 *
		 * @see 7.1.3 Order cancellation request
		 * @link https://garikhg.github.io/woo-ipay-acba/docs/Merchant_Manual_1.55.1.0.pdf
		 * @url https://ipay.arca.am/payment/rest/reverse.do
		 *
		 * @since 1.0.0
		 */
		public function ipay_acba_cancel_order( $order_id, $status_to ) {
			$order  = wc_get_order( $order_id );
			$params = [];

			if ( ! $order->has_status( 'processing' ) ) {

				$PaymentID = get_post_meta( $order_id, 'PaymentID', true );
				$amount    = floatval( $order->get_total() ) * 100;

				$params[] = 'amount=' . (int) $amount;
				$params[] = 'language=' . $this->language;
				$params[] = 'password=' . $this->shop_password;
				$params[] = 'userName=' . $this->shop_id;
				$params[] = 'orderId=' . $PaymentID;

				// todo: check and fixed problem api issue (Access denied)
				$response = wp_remote_post( $this->api_url . '/reverse.do?' . implode( '&', $params ) );
				if ( ! is_wp_error( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ), false );

					if ( 0 == $body->errorCode ) {
						// if ( 0 == $order_status->errorCode ) {
						// 	$order->update_status( 'cancelled' );
						// }
						$order->update_status( 'cancelled' );
					} else {
						$order_status = $this->ipay_acba_get_order_status_ext( $order_id );
						if ( $order_status->errorCode == 0 && $order_status != '' ) {
							$payment_state = $order_status->paymentAmountInfo->paymentState;
							if ( $payment_state == 'CREATED' ) {
								$order->update_status( 'pending', sprintf( 'Order #%s is in the process of payment.', $order_id ) );
								wc_add_notice( sprintf( __( 'Order #%s is in the process of payment.', 'ipay-acba' ), $order_id ) );
							} else {
								$order->update_status( 'cancelled', sprintf( 'Order #%s Cancelled.', $order_id ) );
							}
						}
						wp_die( $body->errorMessage );
					}

					return true;
				} else {
					$order->update_status( 'processing' );
					wp_die( sprintf( __( 'Order Cancel paymend #%s failed. Order status changed to "Processing"', 'ipay-acba' ), $order_id ) );
				}
			}
		}

		public function ipay_acba_order_refunded( $order_id ) {

		}

		/**
		 * Handles successful payment confirmation for ACBA iPay.
		 *
		 * This method is responsible for updating the order status and redirecting the customer
		 * to the appropriate return URL after a successful payment confirmation from ACBA iPay.
		 *
		 * @return void
		 *
		 * @since 1.0.0
		 */
		public function ipay_acba_pay_successful() {
			$order         = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : ''; // Unique shop order number
			$bank_order_id = isset( $_REQUEST['orderId'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['orderId'] ) ) : ''; // Unique bank order id

			if ( ! empty( $bank_order_id ) && ! empty( $order ) ) {
				$params   = array();
				$params[] = 'orderId=' . $bank_order_id;
				$params[] = 'language=' . $this->language;
				$params[] = 'password=' . $this->shop_password;
				$params[] = 'userName=' . $this->shop_id;

				$response = wp_remote_post( $this->api_url . '/getOrderStatus.do?' . implode( '&', $params ) );
				if ( ! is_wp_error( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ), false );
					if ( $body->errorCode == 0 ) {
						if ( isset( $body->orderStatus ) && $body->orderStatus == '2' ) {
							update_post_meta( $order, 'PaymentID', $bank_order_id );
							$order = wc_get_order( $body->orderNumber );
							$order->update_status( 'processing' );
							echo $this->get_return_url( $order );
							wp_redirect( $this->get_return_url( $order ) );
							exit();
						}
					} else {
						$order = wc_get_order( $order );
						$order->update_status( 'failed' );
						$order->add_order_note( $body->errorMessage, true );
					}
				}
			}

			wc_add_notice( __( 'Please try a little later.', 'woo-ipay-acba' ), 'error' );
			wp_redirect( get_permalink( get_option( 'woocommerce_checkout_page_id' ) ) );
			exit();
		}

		/**
		 * Handle the failed payment response from ACBA Bank.
		 * Empty the cart, retrieve the order ID and order hash from the query parameters,
		 * verify the order status with ACBA Bank API, update the order status accordingly,
		 * store the error message in order metadata, redirect to the order page with a notice,
		 * or redirect to the checkout page in case of no valid order hash.
		 *
		 * @return void
		 * @since 1.0.0
		 */
		public function ipay_acba_pay_failed() {
			$order_id      = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : ''; // Unique shop order number
			$bank_order_id = isset( $_GET['orderId'] ) ? sanitize_text_field( wp_unslash( $_GET['orderId'] ) ) : ''; // Unique bank order id

			if ( ! empty( $bank_order_id ) && ! empty( $order_id ) ) {
				$order = wc_get_order( $order_id );

				$params   = array();
				$params[] = 'orderId=' . $bank_order_id;
				$params[] = 'language=' . $this->language;
				$params[] = 'password=' . $this->shop_password;
				$params[] = 'userName=' . $this->shop_id;

				$response = wp_remote_post( $this->api_url . '/getOrderStatus.do?' . implode( '&', $params ) );
				if ( ! is_wp_error( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ), false );

					$order->update_status( 'failed' );
					update_post_meta( $order_id, 'ipay_acba_failed_message', $body->errorMessage );
					wp_redirect( $this->get_return_url( $order ) );
					exit();
				} else {
					$order->update_status( 'failed' );
					wc_add_notice( __( 'We regret to inform you that an issue has occurred. Kindly attempt the process again.', 'ipay-acba' ), 'error' );
				}
			}

			wp_redirect( get_permalink( get_option( 'woocommerce_checkout_page_id' ) ) );
			exit();
		}

		/**
		 * Process payment for the iPay ACBA Payment Gateway.
		 *
		 * This method is responsible for processing the payment for the given order ID.
		 *
		 * @param int $order_id The ID of the order to process payment for.
		 *
		 * @return string[]
		 * @since 1.0.1
		 *
		 * @link https://cabinet.arca.am/file_manager/Merchant%20Manual_1.55.1.0.pdf
		 * @api https://ipay.arca.am/payment/rest/register.do
		 */
		public function process_payment( $order_id ) {
			$params = [];

			$order  = wc_get_order( $order_id );
			$amount = floatval( $order->get_total() ) * 100;

			$params[] = 'amount=' . (int) $amount;
			$params[] = 'currency=' . $this->currency_code;
			$params[] = 'orderNumber=' . $order_id;
			$params[] = 'userName=' . $this->shop_id;
			$params[] = 'password=' . $this->shop_password;
			$params[] = 'description=order number ' . $order_id;
			$params[] = 'returnUrl=' . get_site_url() . '/wc-api/ipay_acba_successful?order=' . $order_id;
			$params[] = 'failUrl=' . get_site_url() . '/wc-api/ipay_acba_failed?order=' . $order_id;
			$params[] = 'language=' . $this->language;
			$params[] = 'jsonParams={"FORCE_3DS2":"true"}';

			$response = wp_remote_post( $this->api_url . '/register.do?' . implode( '&', $params ) );

			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				if ( ! is_wp_error( $response ) ) {
					$body = json_decode( wp_remote_retrieve_body( $response ), false );

					if ( $body->errorCode == 0 ) {
						$order->update_status( 'pending' );
						wc_reduce_stock_levels( $order_id );
						// WC()->cart->empty_cart();
						update_post_meta( $order_id, 'PaymentID', $body->orderId );

						return [ 'result' => 'success', 'redirect' => $body->formUrl ];
					} else {
						$order_status = $this->ipay_acba_get_order_status_ext( $order_id );

						if ( 0 == $order_status->errorCode ) {
							$PaymentID     = get_post_meta( $order_id, 'PaymentID', true );
							$payment_state = $order_status->paymentAmountInfo->paymentState;

							if ( $payment_state == 'CREATED' && ! empty( $PaymentID ) ) {
								$formUrl = $this->merchants_url . $order_status->terminalId . '/payment_' . $this->language . '.html?mdOrder=' . $PaymentID;

								return [ 'result' => 'success', 'redirect' => $formUrl ];
							} elseif ( $payment_state == 'DECLINED' && ! empty( $PaymentID ) ) {
								$order->update_status( 'failed', sprintf( 'order #%s is failed. Payment state Declined', $order_id ) );

								wc_add_notice( sprintf( __( 'Your order #%s is failed. Payment state Declined', 'ipay-acba' ), $order_id ) );
							}

						} else {
							$order->update_status( 'failed', sprintf( 'Order #%s is failed.', $order_id ) );
							wc_add_notice( sprintf( __( 'Your order #%s is failed.', 'ipay-acba' ), $order_id ) );
						}
					}
				} else {
					$order->update_status( 'failed' );
					wc_add_notice( __( 'Connection error. Please try again', 'ipay-acba' ), 'error' );
				}
			} else {
				$order->update_status( 'failed' );
				wc_add_notice( __( 'Connection error. Please try again later.', 'ipay-acba' ), 'error' );
				wp_redirect( get_permalink( get_option( 'woocommerce_checkout_page_id' ) ) );
			}

			return [
				'result'   => 'success',
				'redirect' => get_permalink( get_option( 'woocommerce_checkout_page_id' ) )
			];
		}

		/**
		 * Get the status of the ACBA iPay order.
		 *
		 * @param int|null $order_id The ID of the order to get the status for.
		 *
		 * @return object|bool The order status as an object if the request was successful, false otherwise.
		 *
		 * @since 1.0.0
		 */
		protected function ipay_acba_get_order_status( $order_id = null ) {
			if ( empty( $order_id ) ) {
				return false;
			}
			$params = [];
			$body   = '';

			$order     = wc_get_order( $order_id );
			$PaymentID = get_post_meta( $order_id, 'PaymentID', true );
			$amount    = floatval( $order->get_total() ) * 100;

			$params[] = 'amount=' . (int) $amount;
			$params[] = 'currency=' . $this->currency_code;
			$params[] = 'orderId=' . $PaymentID;
			$params[] = 'language=en';
			$params[] = 'password=' . $this->shop_password;
			$params[] = 'userName=' . $this->shop_id;

			$response = wp_remote_post( $this->api_url . '/getOrderStatus.do?' . implode( '&', $params ) );
			if ( ! is_wp_error( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), false );
			}

			return $body;
		}

		/**
		 * Retrieves the extended order status from ACBA iPay.
		 *
		 * @param int|null $order_id The ID of the order to retrieve the status for.
		 *
		 * @return false The response body as a parsed JSON object. If the order ID is empty, false is returned.
		 *
		 * @since 1.0.0
		 */
		protected function ipay_acba_get_order_status_ext( $order_id ) {
			if ( ! $order_id ) {
				return false;
			}

			$params = [];
			$body   = '';

			$order     = wc_get_order( $order_id );
			$PaymentID = get_post_meta( $order_id, 'PaymentID', true );
			$amount    = floatval( $order->get_total() ) * 100;

			$params[] = 'amount=' . (int) $amount;
			$params[] = 'currency=' . $this->currency_code;
			$params[] = 'orderId=' . $PaymentID;
			$params[] = 'language=en';
			$params[] = 'password=' . $this->shop_password;
			$params[] = 'userName=' . $this->shop_id;

			$response = wp_remote_post( $this->api_url . '/getOrderStatusExtended.do?' . implode( '&', $params ) );
			if ( ! is_wp_error( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), false );
			}

			return $body;
		}
	}
}

