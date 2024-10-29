<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AlphaPay_API{

    private static $partner_code = '';

    private static $credential_code =  '';

    private static $transport_protocols = '';

    private static $order_currency = '';

    public static function get_currency() {
        $options = get_option( 'woocommerce_alphapay_settings' );
        if(empty(self::$order_currency)) self::$order_currency = get_woocommerce_currency();
        return !empty($options['request_platform']) ? $options['request_platform'] : 'CAD';
    }

    public static function get_alphapay_baseurl(){
        $currency_urls = [
            'CAD' => 'https://pay.alphapay.ca',
            'USD' => 'https://pay.alphapay.com'
        ];
        // Test env url
        // $currency_urls = [
        //   'CAD' => 'https://paytest.alphapay.ca',
        //   'USD' => 'https://paytest.alphapay.com'
        // ];
        $currency = self::$order_currency;
        if($currency == 'CNY') $currency = self::get_currency();
        if(empty($currency_urls[$currency])) throw new Exception('Platform must be configured');
        return $currency_urls[$currency];
    }

	/**
	 * Set partner_code.
	 * @param string
	 */
	public static function set_partner_code( $partner_code ) {
		self::$partner_code = $partner_code;
	}

	/**
	 * Get partner_code.
	 * @return string
	 */
	public static function get_partner_code() {
        $key = 'partner_code';
        $defaultPlatform = self::get_currency();
        if(self::$order_currency == 'USD' || (self::$order_currency == 'CNY' && $defaultPlatform == 'USD')) {
            $key .= '_usd';
        }
		if ( ! self::$partner_code ) {
            $options = get_option( 'woocommerce_alphapay_settings' );
			if ( isset( $options[$key]) ) {
				self::set_partner_code( $options[$key] );
			}
		}
        if(!self::$partner_code) throw new Exception("PartnerCode is not configured");
		return self::$partner_code;
    }

    /**
	 * Set credential_code.
	 * @param string
	 */
	public static function set_credential_code( $credential_code ) {
		self::$credential_code = $credential_code;
	}

	/**
	 * Get credential_code.
	 * @return string
	 */
	public static function get_credential_code() {
        $key = 'credential_code';
        $defaultPlatform = self::get_currency();
        if(self::$order_currency == 'USD' || (self::$order_currency == 'CNY' && $defaultPlatform == 'USD')) {
            $key .= '_usd';
        }
		if ( ! self::$credential_code ) {
			$options = get_option( 'woocommerce_alphapay_settings' );

			if ( isset( $options[$key]) ) {
				self::set_credential_code( $options[$key] );
			}
		}
        if(!self::$credential_code) throw new Exception("CredentialCode is not configured");
		return self::$credential_code;
	}


     /**
	 * Set transport_protocols.
	 * @param string
	 */
	public static function set_transport_protocols( $transport_protocols ) {
		self::$transport_protocols = $transport_protocols;
	}

	/**
	 * Get transport_protocols.
	 * @return string
	 */
	public static function get_transport_protocols() {
		if ( ! self::$transport_protocols ) {
			$options = get_option( 'woocommerce_alphapay_settings' );

			if ( isset( $options['transport_protocols']) ) {
				self::set_transport_protocols( $options['transport_protocols'] );
			}
		}
		return self::$transport_protocols;
	}


    public static function generate_alphapay_order($order,$channel,$api_uri){

		$currency =method_exists($order, 'get_currency') ?$order->get_currency():$order->currency;
        self::$order_currency = $currency;

        if(!in_array($currency,['CNY','CAD','USD'])) {
			throw new Exception("Current Payment Method Only Accept Currency: CNY & CAD & USD");
		}


        $order_id = method_exists($order, 'get_id')? $order->get_id():$order->id;

        $partner_code = self::get_partner_code();

        $time=time().'000';

	    $nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0,10);
        $credential_code = self::get_credential_code();


	    $valid_string="$partner_code&$time&$nonce_str&$credential_code";
	    $sign=strtolower(hash('sha256',$valid_string));

	    $new_order_id = date_i18n("ymdHis").$order_id;
		update_post_meta($order_id, 'alphapay_order_id', $new_order_id);
        update_post_meta( $order_id, 'channel', $channel );

        $base_urls = self::get_alphapay_baseurl();
	    $url = sprintf($base_urls.$api_uri,$partner_code,$new_order_id);
      if($channel == 'CreditCard') {
        $url = sprintf('https://cashier.alphapay.ca'.$api_uri,$partner_code);
        // Test env url
        // $url = sprintf('https://cashiertest.alphapay.ca'.$api_uri,$partner_code);
      }

	    $url.="?time=$time&nonce_str=$nonce_str&sign=$sign";
	    $head_arr = array();
	    $head_arr[] = 'Content-Type: application/json';
	    $head_arr[] = 'Accept: application/json';
	    $head_arr[] = 'Accept-Language: '.get_locale();

	    $data =new stdClass();
	    $data->description = self::get_order_title($order);
		
      $data->price = (int)($order->get_total()*100);

		  $data->channel = $channel;

      $data->currency =$currency;

      if($channel == 'CreditCard') {
        $data->body = self::get_order_title($order);
        $data->amount = $order->get_total();
        $data->merchantOrderId = $new_order_id;
      }


		// if choose currency CAD
	    if($data->price < 1 && $currency != 'CNY'){
	        throw new Exception('The payment amount is too little!');
		}

		// if choose currency CNY
		if($data->price < 6 && $currency == 'CNY'){
	        throw new Exception('The payment amount is too little!');
	    }


		$data->notify_url=  get_site_url().'/?wc-api=wc_alphapay_notify';


        $transport_protocols = self::get_transport_protocols();


	    if(!$transport_protocols){
	        $transport_protocols='none';
	    }

	    switch ($transport_protocols){
	        case 'http':
	            if(strpos($data->notify_url, 'https')===0){
	                $data->notify_url = str_replace('https', 'http', $data->notify_url);
	            }
	            break;
	        case 'https':
	            if(strpos($data->notify_url, 'https')!==0){
	                $data->notify_url = str_replace('http', 'https', $data->notify_url);
	            }
	            break;
	    }


        $data =json_encode($data);




        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if($channel == 'CreditCard') {
          curl_setopt($ch, CURLOPT_POST , true);
        } else {
          curl_setopt($ch, CURLOPT_PUT, true);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $head_arr);

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt( $ch, CURLOPT_CAINFO, ABSPATH . WPINC . '/certificates/ca-bundle.crt');

        $temp = tmpfile();
        fwrite($temp, $data);
        fseek($temp, 0);

        curl_setopt($ch, CURLOPT_INFILE, $temp);
        curl_setopt($ch, CURLOPT_INFILESIZE, strlen($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        $response = curl_exec($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error=curl_error($ch);
        curl_close($ch);
        if($httpStatusCode!=200){
            throw new Exception("invalid httpstatus:{$httpStatusCode} ,response:$response,detail_error:".$error,$httpStatusCode);
        }

        $result =$response;

        if($temp){
            fclose($temp);
            unset($temp);
        }

        $resArr = json_decode($result,false);
        if(!$resArr){
            throw new Exception('This request has been rejected by the AlphaPay service!');
        }

        if($channel == 'CreditCard') {
          if(!isset($resArr->pay_url)){
            $errcode =empty($resArr->result_code)?$resArr->return_code:$resArr->result_code;
            throw new Exception(sprintf('ERROR CODE:%s;ERROR MSG:%s.',$errcode,$resArr->return_msg));
          }
        } else {
          if(!isset($resArr->result_code)||$resArr->result_code!='SUCCESS'){
              $errcode =empty($resArr->result_code)?$resArr->return_code:$resArr->result_code;
              throw new Exception(sprintf('ERROR CODE:%s;ERROR MSG:%s.',$errcode,$resArr->return_msg));
        }
		}


       return $resArr;
    }

    public static function alphapay_refund($amount,$ooid,$refund_id){
        $order_id=substr($ooid, 12);
        $order = new WC_Order ($order_id);
        if(!$order) return new WP_Error( 'invalid_order', 'Wrong Order' );
        $currency = method_exists($order, 'get_currency') ?$order->get_currency():$order->currency;
        self::$order_currency = $currency;

        $partner_code = self::get_partner_code();
		$time=time().'000';
		$nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0,10);
		$credential_code = self::get_credential_code();
		$valid_string="$partner_code&$time&$nonce_str&$credential_code";
		$sign=strtolower(hash('sha256',$valid_string));

        $base_urls = self::get_alphapay_baseurl();
		$url ="$base_urls/api/v1.0/gateway/partners/$partner_code/orders/$ooid/refunds/$refund_id";
		$url.="?time=$time&nonce_str=$nonce_str&sign=$sign";

		$head_arr = array();
		$head_arr[] = 'Content-Type: application/json';
		$head_arr[] = 'Accept: application/json';
		$head_arr[] = 'Accept-Language: '.get_locale();

		$data =new stdClass();
		$data->fee = $amount;
		$data=json_encode($data);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_PUT, true);

		//add for https
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		curl_setopt( $ch, CURLOPT_CAINFO, ABSPATH . WPINC . '/certificates/ca-bundle.crt');

		curl_setopt($ch, CURLOPT_HTTPHEADER, $head_arr);
		$temp = tmpfile();
		fwrite($temp, $data);
		fseek($temp, 0);
		curl_setopt($ch, CURLOPT_INFILE, $temp);
		curl_setopt($ch, CURLOPT_INFILESIZE, strlen($data));
		curl_setopt($ch, CURLOPT_TIMEOUT, 120);
		$result = curl_exec($ch);
		curl_close($ch);

		if($temp){
			fclose($temp);
			unset($temp);
		}

        $resArr = json_decode($result,false);

        return $resArr;
    }

    public static function query_order_status($alphapay_order_id){
        $order_id=substr($alphapay_order_id, 12);
        $order = new WC_Order ($order_id);
        if(!$order) return new WP_Error( 'invalid_order', 'Wrong Order' );
        $currency = method_exists($order, 'get_currency') ?$order->get_currency():$order->currency;
        self::$order_currency = $currency;

		$partner_code = self::get_partner_code();
		$time=time().'000';
		$nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0,10);
		$credential_code = self::get_credential_code();
		$valid_string="$partner_code&$time&$nonce_str&$credential_code";
		$sign=strtolower(hash('sha256',$valid_string));

		$head_arr = array();
		$head_arr[] = 'Accept: application/json';
		$head_arr[] = 'Accept-Language: '.get_locale();


        $base_urls = self::get_alphapay_baseurl();
		$url ="$base_urls/api/v1.0/gateway/partners/$partner_code/orders/$alphapay_order_id";
		$url.="?time=$time&nonce_str=$nonce_str&sign=$sign";

		$ch = curl_init();
        //设置超时 set timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $head_arr);
        //GET提交方式
        curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
        //运行curl
		$result = curl_exec($ch);
		curl_close($ch);
		$resArr = json_decode($result,false);

		if(!$resArr){
			return new WP_Error( 'refuse_error', $result);
		}

		return $resArr;

    }

    public static function query_refund_status($alphapay_order_id, $alphapay_refund_id){
        $order_id=substr($alphapay_order_id, 12);
        $order = new WC_Order ($order_id);
        if(!$order) return new WP_Error( 'invalid_order', 'Wrong Order' );
        $currency = method_exists($order, 'get_currency') ?$order->get_currency():$order->currency;
        self::$order_currency = $currency;

		$partner_code = self::get_partner_code();
		$time=time().'000';
		$nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0,10);
		$credential_code = self::get_credential_code();
		$valid_string="$partner_code&$time&$nonce_str&$credential_code";
		$sign=strtolower(hash('sha256',$valid_string));

		$head_arr = array();
		$head_arr[] = 'Accept: application/json';
		$head_arr[] = 'Accept-Language: '.get_locale();


        $base_urls = self::get_alphapay_baseurl();
		$url ="$base_urls/api/v1.0/gateway/partners/$partner_code/orders/$alphapay_order_id/refunds/$alphapay_refund_id";
		$url.="?time=$time&nonce_str=$nonce_str&sign=$sign";

		$ch = curl_init();
        //设置超时 set timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);//严格校验
        //设置header
        curl_setopt($ch, CURLOPT_HTTPHEADER, $head_arr);
        //GET提交方式
        curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
        //运行curl
		$result = curl_exec($ch);
		curl_close($ch);
		$resArr = json_decode($result,false);

		if(!$resArr){
			return new WP_Error( 'refuse_error', $result);
		}

		return $resArr;

    }

    public static function get_order_title($order,$limit=32,$trimmarker='...'){
	    $title ="";
		$order_items = $order->get_items();
		if($order_items){
		    $qty = count($order_items);
		    foreach ($order_items as $item_id =>$item){
		        $title.="{$item['name']}";
		        break;
		    }
		    if($qty>1){
		        $title.='...';
		    }
		}

		$title = mb_strimwidth($title, 0, $limit,'utf-8');
		return apply_filters('payment-get-order-title', $title,$order);
	}

	public static function wc_alphapay_notify(){


		$json =isset($GLOBALS['HTTP_RAW_POST_DATA'])?$GLOBALS['HTTP_RAW_POST_DATA']:'';

		if(empty($json)){
			$json = file_get_contents("php://input");
		}



		if(empty($json)){
			print json_encode(array('return_code'=>'FAIL'));
			exit;
		}


		$response = json_decode($json,false);
		if(!$response){
			print json_encode(array('return_code'=>'FAIL'));
			exit;
		}

		$order_id=substr($response->partner_order_id, 12);
		$order = new WC_Order($order_id);
		if(!$order||!$order->needs_payment()){
			print json_encode(array('return_code'=>'SUCCESS'));
			exit;
		}
        $currency = method_exists($order, 'get_currency') ?$order->get_currency():$order->currency;
        self::$order_currency = $currency;
        $credential_code = self::get_credential_code();
        $partner_code = self::get_partner_code();
        $time=$response->time;
        $nonce_str=$response->nonce_str;

        $valid_string="$partner_code&$time&$nonce_str&$credential_code";
        $sign=strtolower(hash('sha256',$valid_string));
        if($sign!=$response->sign){
            print json_encode(array('return_code'=>'FAIL'));
            exit;
        }

		if(get_post_meta($order_id, 'alphapay_order_id',true)!=$response->partner_order_id){
			update_post_meta($order_id, 'alphapay_order_id', $response->partner_order_id);
		}

		$resArr = AlphaPay_API::query_order_status($response->partner_order_id);

		if(!$resArr){
			print json_encode(array('return_code'=>'FAIL'));
			exit;
		}

		if($resArr->result_code!='PAY_SUCCESS'){
			print json_encode(array('return_code'=>'SUCCESS'));
			exit;
		}

		try {
			$order->payment_complete ($response->order_id);
		} catch (Exception $e) {
			print json_encode(array('return_code'=>'FAIL'));
			exit;
		}

		print json_encode(array('return_code'=>'SUCCESS'));
		exit;
	}


}
