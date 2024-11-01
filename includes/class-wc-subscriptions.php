<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Varipay_Subscription extends WC_Varipay_Gateway {

	/**
	 * Constructor
	*/
	public function __construct() {

		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

		}
	}


	/**
	 * Check if an order contains a subscription
	 */
	public function order_contains_subscription( $order_id ) {

		return function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) );

	}


	/**
	 * Process a trial subscription order with 0 total
	 */
	public function process_payment( $order_id ) {

		$order = wc_get_order( $order_id );

		// Check for trial subscription order with 0 total
		if ( $this->order_contains_subscription( $order ) && $order->get_total() == 0 ) {

			$order->payment_complete();

			$localized_message = __( 'This subscription has a free trial, reason for the 0 amount', 'woo-varipay' );

			$order->add_order_note( $localized_message );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);

		} else {

			return parent::process_payment( $order_id );

		}

	}


	/**
	 * Process a subscription renewal
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

		$response = $this->process_subscription_payment( $renewal_order, $amount_to_charge );

		if ( is_wp_error( $response ) ) {

			if ( $this->testmode ) {
				$localized_message = sprintf( __( '(TEST MODE) Varipay transaction failed. Reason: %s', 'woo-varipay' ), $response->get_error_message() );
			} else {
				$localized_message = sprintf( __( 'Varipay transaction failed. Reason: %s', 'woo-varipay' ), $response->get_error_message() );
			}

			$renewal_order->add_order_note( $localized_message );

			$renewal_order->update_status( 'failed' );

		}

	}


	/**
	 * Process a subscription renewal payment
	 */
	public function process_subscription_payment( $order = '', $amount = 0 ) {

		$order_id  = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;

		$auth_code = get_post_meta( $order_id, '_wc_varipay_auth_code', true );

		if ( $auth_code ) {

			$auth_code = explode( '##', $auth_code );

			$headers = array(
				'Content-Type'              => 'application/json',
				'Ocp-Apim-Subscription-Key' => $this->subscription_key,
			);

			$body = array(
				'receipt-id'        => $auth_code[0],
				'merchant-id'       => $this->merchant_id,
				'card-expiry-month' => $auth_code[2],
				'card-expiry-year'  => $auth_code[3],
				'card-number'       => $auth_code[1],
				'order-id'          => $order_id,
				'amount'            => $amount,
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

	        		$receipt_id = $response->receiptId;

			        $order->payment_complete( $receipt_id );

			        if ( $this->testmode ) {
				        $localized_message = sprintf( __( '(TEST MODE) Payment via Varipay successful. Receipt ID: %s', 'woo-varipay' ), $receipt_id );
			        } else {
				        $localized_message = sprintf( __( 'Payment via Varipay successful. Receipt ID: %s', 'woo-varipay' ), $receipt_id );
			        }

					$order->add_order_note( $localized_message );

					return true;

				} else {

					$gateway_response = '';

			        if ( ! is_null ( $response->message ) ) {

				        $gateway_response = $response->message;

			        }

					return new WP_Error( 'varipay_error', $gateway_response );

				}

	        }
		}

		$localized_message = __( 'This subscription can\'t be renewed automatically. The customer will have to login to his account to renew his subscription', 'woo-varipay' );

		return new WP_Error( 'varipay_error',  $localized_message );

	}

}