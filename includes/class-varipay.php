<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Varipay_Gateway extends WC_Payment_Gateway_CC {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id		   			= 'varipay';
		$this->method_title         = __( 'Varipay', 'woo-varipay' );
		$this->method_description   = __( 'Varipay allows customers to checkout using a credit card', 'woo-varipay' );
		$this->has_fields           = true;

		$this->supports             = array(
			'products',
			'tokenization',
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer'
		);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings
		$this->init_settings();

		// Get setting values
		$this->title 				= $this->get_option( 'title' );
		$this->description 			= $this->get_option( 'description' );
		$this->enabled            	= $this->get_option( 'enabled' );
		$this->testmode             = $this->get_option( 'testmode' ) === 'yes' ? true : false;

		$this->test_key             = $this->get_option( 'test_subscription_key' );
		$this->live_key  	        = $this->get_option( 'live_subscription_key' );

		$this->test_merchant_id     = $this->get_option( 'test_merchant_id' );
		$this->live_merchant_id  	= $this->get_option( 'live_merchant_id' );

		$this->merchant_id          = $this->testmode ? $this->test_merchant_id : $this->live_merchant_id;

		if ( $this->testmode ) {

			$this->charge_url           = 'https://api.vpsecureprocessing.com/relay-dev/api/Purchase/';
			$this->recurring_charge_url = 'https://api.vpsecureprocessing.com/relay-dev/api/Recurring/';

			$this->subscription_key     = $this->test_key;

		} else {

			$this->charge_url           = 'https://api.vpsecureprocessing.com/relay-prod/api/Purchase/';
			$this->recurring_charge_url = 'https://api.vpsecureprocessing.com/relay-prod/api/Recurring/';

			$this->subscription_key     = $this->live_key;

		}

		if ( is_admin() ) {

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}

	}

	/**
	 * Display Varipay payment icon
	 */
	public function get_icon() {

		$icon  = '<img src="' . WC_HTTPS::force_https_url( plugins_url( 'assets/images/cards.png' , WC_VARIPAY_MAIN_FILE ) ) . '" alt="cards" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );

	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {

		if ( $this->enabled == "yes" ) {

			if ( ! ( $this->subscription_key && $this->merchant_id ) ) {

				return false;

			}

			if ( ! $this->testmode && ! wc_checkout_is_https() ) {

				return false;

			}

			return true;

		}

		return false;

	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {

		$form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woo-varipay' ),
				'label'       => __( 'Enable Varipay', 'woo-varipay' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable Varipay as a payment option on the checkout page.', 'woo-varipay' ),
				'default'     => 'no',
				'desc_tip'    => true
			),
			'title' => array(
				'title' 		=> __( 'Title', 'woo-varipay' ),
				'type' 			=> 'text',
				'description' 	=> __( 'This controls the payment method title which the user sees during checkout.', 'woo-varipay' ),
				'default' 		=> __( 'Credit Card (Varipay)', 'woo-varipay' ),
				'desc_tip'      => true
			),
			'description' => array(
				'title' 		=> __( 'Description', 'woo-varipay' ),
				'type' 			=> 'text',
				'description' 	=> __( 'This controls the payment method description which the user sees during checkout.', 'woo-varipay' ),
				'default' 		=> __( 'Pay with your credit card via Varipay', 'woo-varipay' ),
				'desc_tip'      => true
			),
			'testmode' => array(
				'title'       => __( 'Test Mode', 'woo-varipay' ),
				'label'       => __( 'Enable Test Mode', 'woo-varipay' ),
				'type'        => 'checkbox',
				'description' => __( 'Test mode enables you to test payments before going live.', 'woo-varipay' ),
				'default'     => 'yes',
				'desc_tip'    => true
			),
			'test_merchant_id' => array(
				'title'       => __( 'Test Merchant ID', 'woo-varipay' ),
				'type'        => 'text',
				'description' => __( 'Enter your Test Merchant ID here', 'woo-varipay' ),
				'default'     => ''
			),
			'test_subscription_key' => array(
				'title'       => __( 'Test Subscription Key', 'woo-varipay' ),
				'type'        => 'text',
				'description' => __( 'Enter your Test Subscription Key here', 'woo-varipay' ),
				'default'     => ''
			),
			'live_merchant_id' => array(
				'title'       => __( 'Live Merchant ID', 'woo-varipay' ),
				'type'        => 'text',
				'description' => __( 'Enter your Live Merchant ID here', 'woo-varipay' ),
				'default'     => ''
			),
			'live_subscription_key' => array(
				'title'       => __( 'Live Subscription Key', 'woo-varipay' ),
				'type'        => 'text',
				'description' => __( 'Enter your Live Subscription Key here.', 'woo-varipay' ),
				'default'     => ''
			)
		);

		$this->form_fields = $form_fields;

	}

	/**
	 * Process the payment
	 */
	public function process_payment( $order_id ) {

		if ( isset( $_POST['wc-' . $this->id . '-payment-token'] ) && 'new' !== $_POST['wc-' . $this->id . '-payment-token'] ) {

			$token_id = wc_clean( $_POST['wc-'. $this->id .'-payment-token'] );

			$token = WC_Payment_Tokens::get( $token_id );

			if ( $token->get_user_id() !== get_current_user_id() ) {

				$localized_message = __( 'Invalid token ID. Use another payment option.', 'woo-varipay' );

				wc_add_notice( $localized_message, 'error' );

				return;

			} else {

				$status = $this->process_saved_card_payment( $token, $order_id );

				if ( $status ) {

					$order = wc_get_order( $order_id );

					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order )
					);

				}

			}
		} else {

			$save_card_details = false;

			if ( is_user_logged_in() && isset( $_POST['wc-'. $this->id .'-new-payment-method'] ) && true === (bool) $_POST['wc-'. $this->id .'-new-payment-method'] ) {

				$save_card_details = true;

			}

			$order = wc_get_order( $order_id );

			$status = $this->process_new_card_payment( $order, $_POST, $save_card_details );

			if ( $status ) {

				$order = wc_get_order( $order_id );

				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order )
				);

			}

		}

	}

	/**
	 * Process a new card payment.
	 */
	public function process_new_card_payment( $order_id, $post, $save_card_details  ) {

		$order = wc_get_order( $order_id );

		$order_id            = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
		$cardExpiry          = explode( '/', sanitize_text_field( $post['varipay-card-expiry'] ) );
		$cardExpirationMonth = trim( $cardExpiry[0] );
		$cardExpirationYear  = '20' . trim( $cardExpiry[1] );
		$cardCVV             = trim( sanitize_text_field( $post['varipay-card-cvc'] ) );
		$cardNumber          = trim( str_replace( array( ' ', '-' ), '', sanitize_text_field( $post['varipay-card-number'] ) ) );
		$cardType            = trim( $this->get_credit_card_type( $cardNumber ) );

		$body = array(
			'merchant-id'       => $this->merchant_id,
			'card-expiry-month' => $cardExpirationMonth,
			'card-expiry-year'  => $cardExpirationYear,
			'card-type'         => $cardType,
			'card-number'       => $cardNumber,
			'card-cvv'          => $cardCVV,
			'order-id'          => $order_id,
			'amount'            => $order->get_total(),
			'currency-code'     => $order->get_currency(),
			'first-name'        => $this->get_order_prop( $order, 'billing_first_name' ),
			'last-name'         => $this->get_order_prop( $order, 'billing_last_name' ),
			'address1'          => $this->get_order_prop( $order, 'billing_address_1' ),
			'city'              => $this->get_order_prop( $order, 'billing_city' ),
			'country-code'      => $this->get_order_prop( $order, 'billing_country' ),
			'state-code'        => $this->get_order_prop( $order, 'billing_state' ),
			'zip-code'          => $this->get_order_prop( $order, 'billing_postcode' )
		);

		if ( $save_card_details || $this->check_if_order_contains_subscription( $order_id ) ) {
			$body['recurring'] = 1;
		}

		if ( ! empty( $this->get_order_prop( $order, 'billing_address_2' ) ) ) {
			$body['address2'] = $this->get_order_prop( $order, 'billing_address_2' );
		}

		$headers = array(
			'Content-Type'              => 'application/json',
			'Ocp-Apim-Subscription-Key' => $this->subscription_key,
		);

		$args = array(
			'body'		=> json_encode( $body ),
			'headers'	=> $headers,
			'timeout'	=> 60
		);

		$request = wp_remote_post( $this->charge_url, $args );

		if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

			$varipay_response = json_decode( wp_remote_retrieve_body( $request ) );

			// Successful transaction
			if ( 100 == $varipay_response->responseCode ) {

				if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

					return true;

				}

				$receipt_id = $varipay_response->receiptId;

				$varipay_txn_details = $this->verify_varipay_transaction( $receipt_id );

				$order->payment_complete( $receipt_id );

				if ( $this->testmode ) {
					$localized_message = sprintf( __( '(TEST MODE) Varipay payment successful. Receipt ID: %s', 'woo-varipay' ), $receipt_id );
				} else {
					$localized_message = sprintf( __( 'Varipay payment successful. Receipt ID: %s', 'woo-varipay' ), $receipt_id );
				}

				$order->add_order_note( $localized_message );

				if ( $varipay_txn_details ) {

					if ( $save_card_details && $varipay_txn_details->request->recurring ) {

						$user_id = $order->get_user_id();

						$this->save_card_details( $varipay_txn_details, $user_id, $order_id );
					}

					$this->save_subscription_payment_token( $order_id, $varipay_txn_details, false );

				}

				wc_empty_cart();

				return true;

			} else {

				if ( $this->testmode ) {
					$localized_message = sprintf( __( '(TEST MODE) Transaction failed. Error: %s', 'woo-varipay' ), $varipay_response->message );
				} else {
					$localized_message = sprintf( __( 'Transaction failed. Error: %s', 'woo-varipay' ), $varipay_response->message );
				}

				$order->add_order_note( $localized_message );

				wc_add_notice( $localized_message, 'error' );

				return false;

			}

		} else {

			$varipay_response = json_decode( wp_remote_retrieve_body( $request ) );

			if ( isset ( $varipay_response->message ) ) {

				if ( $this->testmode ) {
					$localized_message = sprintf( __( '(TEST MODE) Transaction failed. Error: %s', 'woo-varipay' ), $varipay_response->message );
				} else {
					$localized_message = sprintf( __( 'Transaction failed. Error: %s', 'woo-varipay' ), $varipay_response->message );
				}

				$order->add_order_note( $localized_message );

				wc_add_notice( $localized_message, 'error' );

			}

			return false;
		}

	}

	/**
	 * Process a saved card payment.
	 */
	public function process_saved_card_payment( $token, $order_id ) {

		if ( $token && $order_id ) {

			$order         = wc_get_order( $order_id );

			$order_amount  = method_exists( $order, 'get_total' ) ? $order->get_total() : $order->order_total;

			$headers = array(
				'Content-Type'              => 'application/json',
				'Ocp-Apim-Subscription-Key' => $this->subscription_key,
			);

			$body = array(
				'receipt-id'				=> $token->get_token(),
				'merchant-id'               => $this->merchant_id,
				'card-expiry-month'         => $token->get_expiry_month(),
				'card-expiry-year'          => $token->get_expiry_year(),
				'card-number'               => $token->get_last4(),
				'order-id'                  => $order_id,
				'amount'					=> $order_amount
			);

			$args = array(
				'body'		=> json_encode( $body ),
				'headers'	=> $headers,
				'timeout'	=> 60
			);

			$request = wp_remote_post( $this->recurring_charge_url, $args );

			if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

				$response = json_decode( wp_remote_retrieve_body( $request ) );

				if ( $response->responseCode == 100 ) {

					$order = wc_get_order( $order_id );

					if ( in_array( $order->get_status(), array( 'processing', 'completed', 'on-hold' ) ) ) {

						return true;

					}

					$receipt_id = $response->receiptId;

					$order->payment_complete( $receipt_id );

					if ( $this->testmode ) {
						$localized_message = sprintf( __( '(TEST MODE) Varipay Receipt ID: %s', 'woo-varipay' ), $receipt_id );
					} else {
						$localized_message = sprintf( __( 'Varipay Receipt ID: %s', 'woo-varipay' ), $receipt_id );
					}

					$order->add_order_note( $localized_message );

					$this->save_subscription_payment_token( $order_id, false, $token );

					wc_empty_cart();

					return true;

				} else {

					if ( $this->testmode ) {

						$order_notice  = __( '(TEST MODE) Payment was declined by Varipay.', 'woo-varipay' );
						$failed_notice = __( 'Payment failed using the saved card. Kindly use another payment option.', 'woo-varipay' );

						if ( ! is_null ( $response->message ) ) {

							$order_notice  = '(TEST MODE) Payment was declined by Varipay. Reason: ' . $response->message . '.';
							$failed_notice = 'Payment failed using the saved card. Reason: ' . $response->message . ' Kindly use another payment option.';

						}

					} else {

						$order_notice  = __( 'Payment was declined by Varipay.', 'woo-varipay' );
						$failed_notice = __( 'Payment failed using the saved card. Kindly use another payment option.', 'woo-varipay' );

						if ( ! is_null ( $response->message ) ) {

							$order_notice  = 'Payment was declined by Varipay. Reason: ' . $response->message;
							$failed_notice = 'Payment failed using the saved card. Reason: ' . $response->message . ' Kindly use another payment option.';

						}

					}

					$order->update_status( 'failed', $order_notice );

					wc_add_notice( $failed_notice, 'error' );

					return false;

				}

			}
		} else {

			$localized_message = __( 'Payment Failed', 'woo-varipay' );

			wc_add_notice( $localized_message, 'error' );

		}

	}

	/**
	 * Validate Credit Card Fields.
	 */
	public function validate_fields() {

		if( 'new' != $this->get_post( 'wc-varipay-payment-token' )  ) {
			return true;
		}

		$error = false;

		$cardNumber = $this->get_post( 'varipay-card-number' );
		$cardExpiry = $this->get_post( 'varipay-card-expiry' );
		$cardCSC    = $this->get_post( 'varipay-card-cvc' );

		// Check card number.
		$cardNumber = str_replace( array( ' ', '-' ), '', $cardNumber );

		if ( empty( $cardNumber ) || ! ctype_digit( $cardNumber ) ) {

			$message = __( 'Card number is invalid', 'woo-varipay' );
			wc_add_notice( $message, 'error' );
			$error = true;

		}

		$cardExpiry = explode( '/', $cardExpiry );

		if ( count( $cardExpiry ) != 2 ) {

			$cardExpirationMonth = '';
			$cardExpirationYear  = '';

		} else {

			$cardExpirationMonth = trim( $cardExpiry[0] );
			$cardExpirationYear  = '20' . trim( $cardExpiry[1] );

		}

		//check expiration data
		$currentYear = date('Y');

		if ( ! ctype_digit( $cardExpirationMonth ) || ! ctype_digit( $cardExpirationYear ) ||
		     $cardExpirationMonth > 12 ||
		     $cardExpirationMonth < 1 ||
		     $cardExpirationYear < $currentYear ||
		     $cardExpirationYear > $currentYear + 20
		) {

			$message = __( 'Card expiration date is invalid', 'woo-varipay' );
			wc_add_notice( $message, 'error' );
			$error = true;

		}

		//check security code
		if ( ! ctype_digit( $cardCSC ) ) {

			$message = __( 'Card security code is invalid (only digits are allowed)', 'woo-varipay' );
			wc_add_notice( $message, 'error' );
			$error = true;

		}

		if ( $error ) {

			return true;

		}

		return true;
	}

	/**
	 * Output field name HTML
	 */
	public function field_name( $name ) {
		return ' name="' . esc_attr( $this->id . '-' . $name ) . '" ';
	}

	/**
	 * Show new card can only be added when placing an order notice
	 */
	public function add_payment_method() {

		$localized_message = __( 'You can only add a new card when placing an order.', 'woo-varipay' );

		wc_add_notice( $localized_message, 'error' );

		return;

	}

	/**
	 * Save Customer Card Details
	 */
	public function save_card_details( $varipay_txn_details, $user_id, $order_id ) {

		if ( $varipay_txn_details->request->recurring && $user_id ) {

			$order      = wc_get_order( $order_id );

			$gateway_id = $order->get_payment_method();

			$last4      = substr( $varipay_txn_details->request->{'card-number'}, -4 );
			$exp_year   = $varipay_txn_details->request->{'card-expiry-year'};
			$brand      = $varipay_txn_details->request->{'card-type'};
			$exp_month  = $varipay_txn_details->request->{'card-expiry-month'};
			$auth_code  = $varipay_txn_details->receiptId;

			$token = new WC_Payment_Token_CC();
			$token->set_token( $auth_code );
			$token->set_gateway_id( $gateway_id );
			$token->set_card_type( strtolower( $brand ) );
			$token->set_last4( $last4 );
			$token->set_expiry_month( $exp_month  );
			$token->set_expiry_year( $exp_year );
			$token->set_user_id( $user_id );
			$token->save();

		}

	}

	/**
	 * Verify the details of a Varipay transaction.
	 */
	public function verify_varipay_transaction( $varipay_txn_id ) {

		$query_url = $this->charge_url . $varipay_txn_id . '/' . $this->merchant_id;

		$headers = array(
			'Content-Type'              => 'application/json',
			'Ocp-Apim-Subscription-Key' => $this->subscription_key,
		);

		$args = array(
			'headers'	=> $headers,
			'timeout'	=> 60
		);

		$request = wp_remote_get( $query_url, $args );

		if ( ! is_wp_error( $request ) && 200 == wp_remote_retrieve_response_code( $request ) ) {

			$response = json_decode( wp_remote_retrieve_body( $request ) );

			return $response;

		}

		return false;

	}

	/**
	 * Save payment token to the order for automatic renewal for further subscription payment
	 */
	public function save_subscription_payment_token( $order_id, $varipay_txn_details, $token ) {

		if ( ! function_exists ( 'wcs_order_contains_subscription' ) ) {

			return;

		}

		if ( $this->order_contains_subscription( $order_id ) ) {

			if ( $token ) {

				$last4      = $token->get_last4();
				$exp_year   = $token->get_expiry_year();
				$exp_month  = $token->get_expiry_month();
				$receipt_id = $token->get_token();

			} else {

				$last4      = substr( $varipay_txn_details->request->{'card-number'}, -4 );
				$exp_year   = $varipay_txn_details->request->{'card-expiry-year'};
				$exp_month  = $varipay_txn_details->request->{'card-expiry-month'};
				$receipt_id = $varipay_txn_details->receiptId;

			}

			$varipay_auth_code = $receipt_id . '##' .  $last4 .  '##' . $exp_month . '##' . $exp_year;

			// Also store it on the subscriptions being purchased or paid for in the order
			if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_order( $order_id );

			} elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

			} else {

				$subscriptions = array();

			}

			foreach ( $subscriptions as $subscription ) {

				$subscription_id = $subscription->get_id();

				update_post_meta( $subscription_id, '_wc_varipay_auth_code', $varipay_auth_code );

			}

		}

	}

	/**
	 * Check if the order contains a subscription.
	 */
	private function check_if_order_contains_subscription( $order_id ) {

		if ( ! function_exists ( 'wcs_order_contains_subscription' ) ) {

			return false ;

		}

		if ( $this->order_contains_subscription( $order_id ) ) {

			return true;

		}

		return false;
	}

	/**
	 * Gets an order property.
	 *
	 * This method exists for WC 3.0+ compatibility.
	 */
	private function get_order_prop( WC_Order $order, $prop ) {

		$wc_version = defined( 'WC_VERSION' ) && WC_VERSION ? WC_VERSION : null;

		if ( $wc_version && version_compare( $wc_version, '3.0', '>=' ) && is_callable( array( $order, "get_{$prop}" ) ) ) {
			$value = $order->{"get_{$prop}"}( 'edit' );
		} else {
			$value = $order->$prop;
		}

		return $value;
	}

	/**
	 * Get post data if set
	 **/
	private function get_post( $name ) {
		if ( isset( $_POST[$name] ) ) {
			return sanitize_text_field( $_POST[$name] );
		}
		return NULL;
	}

	/**
	 * Get the credit card type.
	 **/
	private function get_credit_card_type( $card_number ) {

		if ( empty( $card_number ) ) {
			return '';
		}

		include_once( WC_VARIPAY_PLUGIN_PATH . '/includes/class-credit-card-type-detector.php' );

		$detector = new WCVaripayCreditCardTypeDetector();

		$card_type = $detector->detect( $card_number );

		return $card_type;
	}

}