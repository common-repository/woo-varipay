=== WooCommerce Varipay Payment Gateway ===
Contributors: varipay
Tags: credit card, varipay, mastercard, visa, payment, woocommerce
Requires at least: 4.4
Tested up to: 4.9
Requires PHP: 5.6
Stable tag: 1.0.1
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Take credit card payments on your store using Varipay.

== Description ==
Accept Visa, MasterCard, directly on your store with the Varipay payment gateway for WooCommerce.

= Take Credit card payments easily and directly on your store =
The Varipay plugin extends WooCommerce allowing you to take payments directly on your store via Varipay.

= Why choose Varipay? =

Varipay also supports the [Subscriptions extension](https://woocommerce.com/products/woocommerce-subscriptions/) and re-using cards. If they create another order, they can check out using the same card. Card details are saved on Varipay servers and not on your store. A massive timesaver for returning customers.
With a [Varipay account](https://www.varipay.com/) you also get a virtual terminal and access to other processing options. 

== Installation ==

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To
do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “WooCommerce Varipay Payment Gateway” and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Frequently Asked Questions ==

= Does this support recurring payments, like for subscriptions? =

Yes!

= Does this require an SSL certificate? =

Yes! In live mode, an SSL certificate must be installed on your site to use Varipay.

= Does this support both production mode and test mode for testing? =

Yes it does - production and test mode is driven by the API keys you use.

= Configure the plugin =
To configure the plugin, go to __WooCommerce__ > __Settings__ > __Payments__ > __Varipay__ in the WordPress admin dashboard.

* __Enable/Disable__ - check the box to enable Varipay Payment Gateway.
* __Title__ - allows you to determine what your customers will see this payment option as on the checkout page.
* __Description__ - controls the message that appears under the payment fields on the checkout page. Here you can list the types of cards you accept.
* __Test Mode__ - Check to enable test mode. Test mode enables you to test payments before going live. If you ready to start receiving real payment on your site, kindly uncheck this.
* __Test Merchant ID__ - Enter your Test Merchant ID here.
* __Test Subscription Key__ - Enter your Test Subscription Key here.
* __Live Merchant ID__ - Enter your Live Merchant ID here.
* __Live Subscription Key__ - Enter your Live Subscription Key here.
* Click on __Save Changes__ for the changes you made to be effected.

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Screenshots ==

1. The settings panel used to configure the gateway.
2. Normal checkout with Varipay.
3. Checking out with a saved card.

== Changelog ==

= 1.0.0 =
*   First release

= 1.0.1 =
*   Fix: Can't use saved card to make payment

== Upgrade Notice ==

= 1.0.1 =
*   Fix: Can't use saved card to make payment
