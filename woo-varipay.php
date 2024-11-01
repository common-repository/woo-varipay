<?php
/*
	Plugin Name:            WooCommerce Varipay Payment Gateway
	Plugin URI:             https://varipay.com/
	Description:            Take credit card payments on your store using Varipay.
	Version:                1.0.1
	Author:                 Varipay
	Author URI:             https://varipay.com/
	License:                GPL-2.0+
	License URI:            http://www.gnu.org/licenses/gpl-2.0.txt
    Requires at least:      4.4
    Tested up to:           4.9
	WC requires at least:   3.0
	WC tested up to:        3.4
    Text Domain:            woo-varipay
    Domain Path:            /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WC_VARIPAY_MAIN_FILE', __FILE__ );
define( 'WC_VARIPAY_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_VARIPAY_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );

define( 'WC_VARIPAY_VERSION', '1.0.1' );

/**
 * Init Varipay Gateway.
 */
function tbz_wc_varipay_init() {

	load_plugin_textdomain( 'woo-varipay', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once( dirname( __FILE__ ) . '/includes/class-varipay.php' );

	include_once( dirname( __FILE__ ) . '/includes/class-wc-subscriptions.php' );

	add_filter( 'woocommerce_payment_gateways', 'tbz_wc_add_varipay_gateway', 99 );

}
add_action( 'plugins_loaded', 'tbz_wc_varipay_init', 99 );

/**
 * Add Settings link to the plugin entry in the plugins menu.
 */
function tbz_woo_varipay_plugin_action_links( $links ) {

	$plugin_links = array(
		'<a href="'. admin_url( 'admin.php?page=wc-settings&tab=checkout&section=varipay' ) .'">' . esc_html__( 'Settings', 'woo-varipay' ) . '</a>',
	);

	return array_merge( $plugin_links, $links );

}
add_filter('plugin_action_links_' . plugin_basename( __FILE__ ), 'tbz_woo_varipay_plugin_action_links' );

/**
 * Add Varipay Gateway to WooCommerce
 */
function tbz_wc_add_varipay_gateway( $methods ) {

	if ( class_exists( 'WC_Subscriptions_Order' ) && class_exists( 'WC_Payment_Gateway_CC' ) ) {
		$methods[] = 'WC_Gateway_Varipay_Subscription';
	} else {
		$methods[] = 'WC_Varipay_Gateway';
	}

	return $methods;

}

/**
 * Varipay Admin Notices
 */
function tbz_wc_varipay_admin_notices() {

	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$varipay_settings = get_option( 'woocommerce_varipay_settings' );
	$enabled          = $varipay_settings['enabled'];

	if ( $enabled == 'no' ) {
		return;
	}

	$test_mode        = $varipay_settings['testmode'] === 'yes' ? true : false;
	$test_key         = $varipay_settings['test_subscription_key'];
	$live_key         = $varipay_settings['live_subscription_key'];
	$test_merchant_id = $varipay_settings['test_merchant_id'];
	$live_merchant_id = $varipay_settings['live_merchant_id'];
	$merchant_id      = $test_mode ? $test_merchant_id : $live_merchant_id;
	$subscription_key = $test_mode ? $test_key : $live_key;

	// Check required fields.
	if ( ! ( $subscription_key && $merchant_id ) ) {
		echo '<div class="error" style="position:relative;"><p>' . sprintf( 'Please enter your Varipay merchant details <a href="%s">here</a> to be able to use the Varipay WooCommerce plugin.', admin_url( 'admin.php?page=wc-settings&tab=checkout&section=varipay' ) ) . '</p></div>';
		return;
	}

	// Check if SSL is setup.
	if ( ! $test_mode && ! wc_checkout_is_https() ) {

		echo '<div class="notice notice-warning" style="position:relative;">';

		$message = sprintf( __( 'Varipay is enabled, but a SSL certificate is not detected. Only test payment can be processed. Please ensure your server has a valid <a href="%1$s" target="_blank">SSL certificate</a>', 'woo-varipay' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' );

		echo '<p>';
		echo wp_kses( $message, array( 'a' => array( 'href' => array() ) ) );
		echo '</p></div>';

	}
}
add_action( 'admin_notices', 'tbz_wc_varipay_admin_notices' );