<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Setup our Gateway's id, description and other values
class WC_AlphaPay extends WC_Payment_Gateway{


	public function __construct(){
		// The global ID for this Payment method
		$this->id = "alphapay";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title='AlphaPay - WeChat Pay, Alipay & UnionPay';

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description='WeChat Pay, Alipay and UnionPay provided by <a href="https://www.alphapay.com" target="_blank">AlphaPay</a>';

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		// $this->icon = ALPHAPAY_URL. '/assets/images/wechat-logo.png';

		$this->has_fields = false;

		// Supports
		$this->supports[]='refunds';

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields ();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		$this->init_settings ();


		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}



		add_action ( "wp_ajax_alphapay_order_status", array($this,'wc_order_is_paid'));
		add_action ( "wp_ajax_nopriv_alphapay_order_status", array($this,'wc_order_is_paid'));

		add_action ( 'woocommerce_api_wc_alphapay_notify', array( 'AlphaPay_API', 'wc_alphapay_notify' ) );
		add_action ( 'wp_enqueue_scripts', array($this, 'my_admin_scripts' ) );
		add_action ( 'woocommerce_thankyou_alphapay', array($this, 'thankyou_page') );
	}

	public function my_admin_scripts() {
		wp_register_style( 'alphapay-style', plugins_url( 'assets/css/alphapay-style.css', ALPHAPAY_FILE ), array());
		wp_enqueue_style( 'alphapay-style' );
	}

	public function get_icon() {

		$icons_str = '<img src="' . ALPHAPAY_URL . '/assets/images/wechat-logo.png" class="right-float" alt="Wechat Pay" />';

		return apply_filters( 'woocommerce_gateway_icon', $icons_str, $this->id );
	}

	public function is_available() {

		try {
            if(!AlphaPay_API::get_partner_code() || !AlphaPay_API::get_credential_code() ){
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

		if($this->enabled == 'no'){
			return false;
		}
		return $this->enabled;
	}


	function init_form_fields() {
		$this->form_fields = array (
				'enabled' => array (
						'title' => 'Enable/Disable',
						'type' => 'checkbox',
						'label' =>'Enable AlphaPay - WeChat Pay',
						'default' => 'no',
						'section'=>'default',
						'description' => sprintf( __( '* To Enable Alipay <a href="%s" target="_blank">here</a>.<br /> * To Enable UnionPay Debit <a href="%s" target="_blank">here</a>.<br /> * To Enable UnionPay Credit <a href="%s" target="_blank">here</a>.<br /> * To Enable Credit Card <a href="%s" target="_blank">here</a>.', 'woocommerce-gateway-alphapay' ),
						admin_url( 'admin.php?page=wc-settings&tab=checkout&section=alphapay_alipay' ),
						admin_url( 'admin.php?page=wc-settings&tab=checkout&section=alphapay_unionpay' ),
						admin_url( 'admin.php?page=wc-settings&tab=checkout&section=alphapay_unionpay_express' ),
            admin_url( 'admin.php?page=wc-settings&tab=checkout&section=alphapay_credit_card' )
					)
				),
                'request_platform' => array (
                    'title' => 'Default Platform',
                    'type' => 'select',
                    'css' => 'width:400px',
                    'default' => 'CAD',
                    'options'=>array(
                        'CAD'=>'CAD',
                        'USD'=>'USD',
                    ),
                    'description' => 'AlphaPay platform requested when the order currency is CNY',
                ),
				'title' => array (
					'title' => 'Title',
					'type' => 'text',
					'default' =>  'WeChat Pay',
					'desc_tip' => true,
					'css' => 'width:400px',
					'section'=>'default'
				),
				'description' => array (
                    'title' => 'Description',
                    'type' => 'textarea',
                    'default' => 'Use WeChat App to Scan QR Code to complete payment',
                    'desc_tip' => true,
                    'css' => 'width:400px',
                    'section'=>'default'
				),

				'qrcode_redirect'=>array(
					'title' => 'WeChat Pay Qrcode Location',
					'type' => 'select',
					'css' => 'width:400px',
					'options'=>array(
						'0'=>'AlphaPay',
						'1'=>'Local'
					)
				),

				'general_setting' => array(
					'title'       => 'AlphaPay General Setting',
					'type'        => 'title',
					'description' => 'The following settings are used for WeChat Pay and Alipay.',
				),

				'partner_code' => array (
					'title' => 'Partner Code (CAD)',
					'type' => 'password',
					'css' => 'width:400px',
					'section'=>'default',
					'description' => '* Register your merchant account from <a href="https://www.alphapay.com/apply" target="_blank">AlphaPay</a> or Contact 1-855-937-7888 to get your Partner Code. ',
				),
				'credential_code' => array (
                    'title' => 'Gateway Credential (CAD)',
                    'type' => 'password',
                    'css' => 'width:400px',
                    'section'=>'default',
                    'description' => '* Register your merchant account from <a href="https://www.alphapay.com/apply" target="_blank">AlphaPay</a> or Contact 1-855-937-7888 to get your Gateway Credential Code. ',
				),
                'partner_code_usd' => array (
                    'title' => 'Partner Code (USD)',
                    'type' => 'password',
                    'css' => 'width:400px',
                    'section'=>'default',
                    'description' => '* Register your merchant account from <a href="https://www.alphapay.com/apply" target="_blank">AlphaPay</a> or Contact 1-855-937-7888 to get your Partner Code. ',
                ),
                'credential_code_usd' => array (
                    'title' => 'Gateway Credential (USD)',
                    'type' => 'password',
                    'css' => 'width:400px',
                    'section'=>'default',
                    'description' => '* Register your merchant account from <a href="https://www.alphapay.com/apply" target="_blank">AlphaPay</a> or Contact 1-855-937-7888 to get your Gateway Credential Code. ',
                ),
				'transport_protocols' => array (
						'title' => 'Transport Protocols',
						'type' => 'select',
						'css' => 'width:400px',
						'options'=>array(
							'none'=>'Follow System',
							// 'http'=>'http',
							'https'=>'https',
						),
						'description' => '* Must have https to get Payment Success Notification',
				),
				'instructions' => array(
					'title'       => 'Instructions',
					'type'        => 'textarea',
					'css' => 'width:400px',
					'description' => 'Instructions that will be added to the thank you page.',
					'default'     => '',
					'section'=>'default'
				)

			);
	}



	/**
	 * Output for the order received page.
	 */
	public function thankyou_page($order_id) {

		if ( $this->instructions ) {
			echo wpautop( wptexturize( $this->instructions ) );
		}

	}

	public  function is_wechat_client(){
		return strripos($_SERVER['HTTP_USER_AGENT'],'micromessenger')!=false;
	}



	/**
	 * Add content to the WC emails.
	 *
	 * @access public
	 * @param WC_Order $order
	 * @param bool $sent_to_admin
	 * @param bool $plain_text
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
	    $method = method_exists($order ,'get_payment_method')?$order->get_payment_method():$order->payment_method;
		if ( $this->instructions && ! $sent_to_admin && $this->id === $method ) {
			echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
		}
	}


	public function process_payment($order_id){
		$order = new WC_Order($order_id);
		if(!$order||!$order->needs_payment()){
			return array(
	             'result'   => 'success',
	             'redirect' => $this->get_return_url($order)
	         );
		}


        $partner_code = AlphaPay_API::get_partner_code();
        $credential_code = AlphaPay_API::get_credential_code();

		try {
			if($this->is_wechat_client()){
				$result = AlphaPay_API::generate_alphapay_order($order,'Wechat',"/api/v1.0/jsapi_gateway/partners/%s/orders/%s");

				$time=time().'000';

				$nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0,10);
				$valid_string="$partner_code&$time&$nonce_str&$credential_code";
				$sign=strtolower(hash('sha256',$valid_string));
				return array(
					'result'   => 'success',
					'redirect' =>$result->pay_url.(strpos($result->pay_url, '?')==false?'?':'&')."directpay=true&time=$time&nonce_str=$nonce_str&sign=$sign&redirect=".urlencode($this->get_return_url($order))
				);
			}else{
				if('1'==$this->get_option('qrcode_redirect')){
					return array(
						'result'   => 'success',
						'redirect' =>$order->get_checkout_payment_url(true)
					);
				}else{
					$result = AlphaPay_API::generate_alphapay_order($order,'Wechat',"/api/v1.0/gateway/partners/%s/orders/%s");
					$time=time().'000';

					$nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0,10);
					$valid_string="$partner_code&$time&$nonce_str&$credential_code";
					$sign=strtolower(hash('sha256',$valid_string));
					return array(
						'result'   => 'success',
						'redirect' =>$result->pay_url.(strpos($result->pay_url, '?')==false?'?':'&')."time=$time&nonce_str=$nonce_str&sign=$sign&redirect=".urlencode($this->get_return_url($order))
					);
				}

			}
		} catch (Exception $e) {
			throw $e;
		}


	}

	public function process_refund( $order_id, $amount = null, $reason = ''){
		$order = new WC_Order ($order_id );
		if(!$order){
			return new WP_Error( 'invalid_order', 'Wrong Order' );
		}

		$total = ( int ) ($order->get_total () * 100);
		$amount = ( int ) ($amount * 100);
		if($amount<=0||$amount>$total){
			return new WP_Error( 'invalid_order','Invalid Amount ');
		}

		$ooid = get_post_meta($order_id, 'alphapay_order_id',true);
		$refund_id=time();


		if($amount == $total){
			// check real fee of order (include service charge)
			$queryresult = AlphaPay_API::query_order_status($ooid);
			$amount = $queryresult->real_fee;
		}

		$resArr = AlphaPay_API::alphapay_refund($amount,$ooid,$refund_id);

		$partner_refund_id = "";
		$partner_refund_id = $resArr->partner_refund_id;


		if(!$resArr){
			return new WP_Error( 'refuse_error', $result);
		}

		//Check if refund status is waiting, if yes, check again until status changes
    if($resArr->result_code == 'WAITING') {

      do{
        $refundResult = AlphaPay_API::query_refund_status($ooid, $partner_refund_id);

        if($refundResult->result_code == 'FINISHED') {
          return true;
        }

        sleep(5); // Make it sleep 5 seconds so as to not spam the server
      }
      while($refundResult->result_code == 'WAITING');
    }

		if($resArr->result_code!='SUCCESS' && $resArr->result_code!='FINISHED'){
			return new WP_Error( 'refuse_error', sprintf('ERROR CODE:%s',empty($resArr->result_code)?$resArr->return_code:$resArr->result_code));
		}
		return true;
	}

	public function wc_order_is_paid(){


		$order_id = isset($_GET['id'])?$_GET['id']:0;

        if(!$order_id){
            echo json_encode(array(
                'status'=>'unpaid'
            ));
            exit;
        }

        $order = new WC_Order($order_id);
        if(!$order||$order->needs_payment()){
            echo json_encode(array(
                'status'=>'unpaid'
            ));
            exit;
        }

        echo json_encode(array(
            'status'=>'paid'
        ));
        exit;
	}


	// AlphaPay WeChat Pay -> local
	public function wc_receipt($order_id){


	    $order = new WC_Order($order_id);
	    if(!$order||!$order->needs_payment()){
	        ?>
	        <script type="text/javascript">
					location.href='<?php echo $this->get_return_url($order);?>';
				</script>
	        <?php
	        return;
		}

	    try {
			$result = AlphaPay_API::generate_alphapay_order($order,'Wechat',"/api/v1.0/gateway/partners/%s/orders/%s");

			if(!$result->code_url){
				?><ul class="woocommerce-error">
        			<li><?php echo 'There is Something Wrong with getting QR Code, Please Try Again!';?></li>
        		</ul><?php
			}

            ?>
            <p>请使用微信”扫一扫”扫描下方二维码进行支付。Please scan the QR Code using the Wechat App to complete payment.</p>

			<div style="padding-top:20px;">
				<div style="display: inline-block; margin: 0;">
				<img style="width:300px;height:300px;display: block;" src="<?php echo ALPHAPAY_URL ?>/includes/qrcode.php?data=<?php echo urlencode($result->code_url); ?>"/>
					<img style="display: block;" src="<?php echo ALPHAPAY_URL ?>/assets/images/wechat-scan-note.png" ／>
				</div>
				<div style="display: inline-block;  margin: 0;">
					<img style="display: block;" src="<?php echo ALPHAPAY_URL ?>/assets/images/wechat-scan.png" ／>
				</div>
			</div>
			<script type="text/javascript">
				(function ($) {
				    function queryOrderStatus() {
				        $.ajax({
				            type: "GET",
				            url: wc_checkout_params.ajax_url,
				            data: {
				                id: <?php print $order_id?>,
				                action: 'alphapay_order_status'
				            },
				            timeout:6000,
				            cache:false,
				            dataType:'json',
				            success:function(data){
				                if (data && data.status === "paid") {
				                    location.href = '<?php echo $this->get_return_url($order)?>';
				                    return;
				                }

				                setTimeout(queryOrderStatus, 2000);
				            },
				            error:function(){
				            	setTimeout(queryOrderStatus, 2000);
				            }
				        });
				    }

				    setTimeout(function(){
    	            	queryOrderStatus();
	    	        },3000);
				})(jQuery);
			</script>
            <?php
	    } catch (Exception $e) {
	        ?><ul class="woocommerce-error">
        			<li><?php echo $e->getMessage();?></li>
        	</ul><?php

	    }

	}


}
