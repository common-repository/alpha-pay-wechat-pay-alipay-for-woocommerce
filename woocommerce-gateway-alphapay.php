<?php
/*
 * Plugin Name: AlphaPay Payment Plugin for WeChat Pay, Alipay, UnionPay, and Credit Card All-in-One Payment（微信支付，支付宝，银联，信用卡支付）
 * Description: Allow Canadian merchants to connect all the mainstream payment channels like WeChat Pay, Alipay, UnionPay, Visa, and MasterCard upon single activation with AlphaPay. 
 * Version: 1.9
 * Author: AlphaPay
 * Author URI: https://www.alphapay.com
 * Text Domain: AlphaPay - WeChat Pay for WooCommerce
 */
if (! defined ( 'ABSPATH' )) exit (); // Exit if accessed directly


// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'alphapay_init', 0 );

function alphapay_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;


	define('ALPHAPAY_FILE',__FILE__);
	define('ALPHAPAY_URL',rtrim(plugin_dir_url(ALPHAPAY_FILE),'/'));

	// If we made it this far, then include our Gateway Class
	include_once( 'includes/class-wc-alphapay-api.php' );
	include_once( 'includes/class-wc-alphapay-gateway.php' );
	include_once( 'includes/class-wc-alphapay-gateway-alipay.php' );
	include_once( 'includes/class-wc-alphapay-gateway-unionpay.php' );
	include_once( 'includes/class-wc-alphapay-gateway-unionpay-express.php' );
  include_once( 'includes/class-wc-alphapay-gateway-credit-card.php' );


	global $AlphaPay;
	$AlphaPay= new WC_AlphaPay();

	add_action ( 'woocommerce_receipt_'.$AlphaPay->id, array ($AlphaPay,'wc_receipt'),10,1);
	// add_action('init', array($AlphaPay,'notify'),10);


	global $AlphaPayAli;
	$AlphaPayAli= new WC_AlphaPay_Alipay();


	global $AlphaPayUnion;
	$AlphaPayUnion= new WC_AlphaPay_UnionPay();

	global $AlphaPayUnionExpress;
	$AlphaPayUnionExpress = new WC_AlphaPay_UnionPay_Express();

  global $AlphaPayCreditCard;
	$AlphaPayCreditCard = new WC_AlphaPay_Credit_Card();


	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'add_alphapay_gateway' );
	function add_alphapay_gateway( $methods ) {
		$methods[] = 'WC_AlphaPay';
		$methods[] = 'WC_AlphaPay_Alipay';
		$methods[] = 'WC_AlphaPay_UnionPay';
		$methods[] = 'WC_AlphaPay_UnionPay_Express';
    $methods[] = 'WC_AlphaPay_Credit_Card';

		return $methods;
	}


	// Add custom action links
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'alphapay_action_links' );
	function alphapay_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=alphapay' ) . '">' .  'Settings' . '</a>',
		);

		// Merge our new link with the default ones
		return array_merge( $plugin_links, $links );
	}

	//Show pay type in edit order page for admin.
	add_action( 'woocommerce_admin_order_data_after_billing_address', 'wc_alphapay_custom_display_admin', 10, 1 );
	function wc_alphapay_custom_display_admin($order){
		$method = get_post_meta( $order->get_id(), '_payment_method', true );
		if($method != 'alphapay' && $method != 'alphapay_alipay' && $method != 'alphapay_unionpay' && $method != 'alphapay_unionpay_express' && $method != 'alphapay_credit_card'){
			return;
		}
		$channel = get_post_meta( $order->get_id(), 'channel', true );
		$alphapay_order_id = get_post_meta( $order->get_id(), 'alphapay_order_id', true );
		echo '<p><strong>'.__( 'Payment Method' ).': </strong> ' . $channel . '</p>';
		echo '<p><strong>'.__( 'AlphaPay Order Id' ).':</strong> ' . $alphapay_order_id . '</p>';
	}

}



?>
