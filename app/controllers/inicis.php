<?php
/*
* inicis 크로스 브라우징 결제 모듈
* 2017-05-30 jhs create
* 수정될때 아래에 이력을 남겨주세요 (no. 날짜 이니셜 (내용))
* 1. 2017-06-14 jhs (상세 클래스 구현)
*/

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH . "controllers/base/front_base".EXT);
require_once(ROOTPATH . 'pg/inicis/libs/INIStdPayUtil.php');
require_once(ROOTPATH . 'pg/inicis/libs/HttpClient.php');

class inicis extends front_base {

	protected $pg_param;

	public function __construct() {
		parent::__construct();
		$this->load->helper('order');
		$this->load->helper('shipping');

		$this->load->model('cartmodel');
		$this->load->model('ordermodel');
		$this->load->model('membermodel');
		$this->load->model('couponmodel');
		$this->load->model('goodsmodel');
		$this->load->model('promotionmodel');
		$this->load->model('paymentlog');
		$this->load->helper('readurl');

		$this->cfgPg = config_load($this->config_system['pgCompany']);
	}

	 /*
	 * [결제 인증요청 페이지(STEP1)]
	 *
	 */
	public function request()
	{
		session_start();
		//app/order.php function pay에서 전달 해준 데이터
		$this->pg_param = json_decode(base64_decode($_POST["jsonParam"]),true);

		// #28841 settle_price 위변조 체크 19.02.12 kmj
		$settleSQL = "seLECT settleprice FROM fm_order WHERE order_seq = ?";
		$settle_price = $this->db->query($settleSQL, array($this->pg_param['order_seq']))->result_array();

		if( intval(floor($settle_price[0]['settleprice'])) !== intval($this->pg_param['settle_price']) ){
			echo("<script>alert('결제 금액이 일치하지 않습니다. 다시 한 번 시도해 주세요.');</script>");
			exit;
		}

		$pg_param = array();
		$pg = $this->cfgPg;

		if( $pg['nonInterestTerms'] == 'manual' &&  isset($pg['pcCardCompanyCode']) ){
			foreach($pg['pcCardCompanyCode'] as $key => $code){
				$arr = explode(',',$pg['pcCardCompanyTerms'][$key]);
				$terms = array();
				foreach($arr as $term){
					$terms[] = sprintf('%02d',$term);
				}
				$codes[] = $code . '-' . implode(':',$terms);
			}
			$pg_param['inicis_noint_quota'] = implode(',',$codes);
		}

		$pg_param = array_merge($pg_param,$this->pg_param);

		//과세, 부가세 추가 :: 2018-01-02 lkh
		$pg_param['comm_free_mny']	= $pg_param['freeprice'];
		$pg_param['comm_tax_mny']	= $pg_param['comm_tax_mny'];
		$pg_param['comm_vat_mny']	= $pg_param['comm_vat_mny'];

		$orders = $this->ordermodel -> get_order($pg_param['order_seq']);
		$param['buyername'] = $orders['order_user_name'];
		$param['buyeremail'] = $orders['order_email'];
		$param['buyertel'] = $orders['order_cellphone'];
		$pg_param['quotaopt']  = $pg['interestTerms'];

		//결제수단 입력
		//입력가능한 코드 :
		//Card,DirectBank,HPP,Vbank,kpay,Swallet,Paypin,EasyPay,PhoneBill
		//GiftCard,EWallet,onlypoint,onlyocb,onyocbplus,onlygspt,onlygsptplus,onlyupnt,onlyupntplus
		$payment = str_replace('escrow_','',$pg_param['payment']);
		switch($payment){
			case "card" : $gopaymethod= "Card"; break;
			case "account" : $gopaymethod= "DirectBank"; break;
			case "virtual" : $gopaymethod= "Vbank"; break;
			case "cellphone" : $gopaymethod= "HPP"; break;
		}
		/* ################ 16.12.29 gcs 장다혜 : 가상계좌 사용시 입금기한 고정 s */
		$order_config = config_load('order');
		if($order_config['autocancel'] == 'y') {
			$vcard_date = date('Ymd',strtotime("+".$order_config['cancelDuration']." day", time()));
		}

		/* ################ 16.12.29 gcs 장다혜 : 가상계좌 사용시 입금기한 고정 e */
		if ($vcard_date) {
			$acceptmethod			= "SKIN(ORIGINAL):HPP(2):no_receipt:Vbank({$vcard_date})";
		}else{
			$acceptmethod			= "SKIN(ORIGINAL):HPP(2):no_receipt";
		}
		if($payment != $pg_param['payment']){
			$pg_param['escorw']		= 1;
			$pg_param['payment']	= $payment;
			$pg['mallCode']			= $pg['escrowMallCode'];
			$pg['signKey']		=  $pg['escrowSignKey'];
			$acceptmethod .= ":useescrow";
		}

		// 1000원 이하 결제 허용 옵션 추가
		$acceptmethod .= ":below1000";

		if($pg['mallCode'] == "GBFINIpayTest") $pg['mallCode'] = "INIpayTest";
		if($pg['mallCode'] == "GBF_INIpayTest") $pg['mallCode'] = "INIpayTest";

		$param['mallCode'] = $pg['mallCode'];

		$quotabase	= "";
		$cardNoInterestQuota = "";

		//할부개월 생성
		if($pg['interestTerms']){
			for($inter_i=2;$inter_i <= $pg['interestTerms'];$inter_i++){
				$arr_terms[] = $inter_i;
			}
			$quotabase= implode(':',$arr_terms);
		}

		//무이자 할부개월 생성
		if($pg['nonInterestTerms'] == 'manual'){
			if($pg['pcCardCompanyCode']){
				foreach($pg['pcCardCompanyCode'] as $k_cardCompanyCode => $data_cardCompanyCode){
					if($data_cardCompanyCode && $pg['pcCardCompanyTerms'][$k_cardCompanyCode]){
						$r_cardCompanyCode[] = $data_cardCompanyCode."-".str_replace(",",":",$pg['pcCardCompanyTerms'][$k_cardCompanyCode]);
					}
				}
				if($r_cardCompanyCode){
					$cardNoInterestQuota .= implode(',',$r_cardCompanyCode);
				}
			}
		}

		$SignatureUtil = new INIStdPayUtil();

		//############################################
		// 1.전문 필드 값 설정(***가맹점 개발수정***)
		//############################################
		// 여기에 설정된 값은 Form 필드에 동일한 값으로 설정
		$mid 			= $pg['mallCode'];  							// 가맹점 ID(가맹점 수정후 고정)
		if( $mid== 'GBF_INIpayTest' )	$mid= "INIpayTest";				//gabia test
		if( $mid== 'GBFINIpayTest' )	$mid= "INIpayTest";				//gabia test

		//인증
		$signKey 		= $pg['signKey']; 							// 가맹점에 제공된 키(이니라이트키) (가맹점 수정후 고정) !!!절대!! 전문 데이터로 설정금지
		$timestamp 		= $SignatureUtil->getTimestamp();   			// util에 의해서 자동생성
		$orderNumber 	= $pg_param['order_seq']; 						// 가맹점 주문번호(가맹점에서 직접 설정)
		$price 			= $pg_param['settle_price'];        			// 상품가격(특수기호 제외, 가맹점에서 직접 설정)

		//
		//###################################
		// 2. 가맹점 확인을 위한 signKey를 해시값으로 변경 (SHA-256방식 사용)
		//###################################
		$mKey 					= $SignatureUtil->makeHash($signKey, "sha256");

		/*
		 **** 위변조 방지체크를 signature 생성 ***
		 * oid, price, timestamp 3개의 키와 값을
		 * key=value 형식으로 하여 '&'로 연결한 하여 SHA-256 Hash로 생성 된값
		 * ex) oid=INIpayTest_1432813606995&price=819000&timestamp=2012-02-01 09:19:04.004
		 * key기준 알파벳 정렬
		 * timestamp는 반드시 signature생성에 사용한 timestamp 값을 timestamp input에 그데로 사용하여야함
		 */
		$params = array(
				"oid" => $orderNumber,
				"price" => $price,
				"timestamp" => $timestamp
		);

		$sign		= $SignatureUtil->makeSignature($params);

		$http_host 	= $_SERVER['HTTP_HOST'];
		$http_protocol = $_SERVER['HTTPS'] ? 'https' : 'http';

		/* 기타 */
		$siteDomain = $http_protocol."://".$http_host."/inicis"; //가맹점 도메인 입력

		// 페이지 URL에서 고정된 부분을 적는다.
		// Ex) returnURL이 http://localhost:8082/demo/INIpayStdSample/INIStdPayReturn.jsp 라면
		//                 http://localhost:8082/demo/INIpayStdSample 까지만 기입한다.

		/*Payment 변수 설정*/
		$param["mid"] 			= $mid;
		$param["oid"] 			= $orderNumber;
		$param["price"] 		= $price;
		$param["timestamp"] 	= $timestamp;
		$param["signature"] 	= $sign;
		$param["returnUrl"] 	= $siteDomain."/receive";
		$param["mKey"] 			= $mKey;
		$param["closeUrl"] 		= $siteDomain."/payClose";
		$param["popupUrl"] 		= $siteDomain."/payPopup";
		$param["version"] 		= "1.0";
		$param["goodname"] 		= $pg_param['goods_name'];
		$param["currency"] 		= "WON";
		$param["buyername"] 	= $orders['order_user_name'];
		$param["buyertel"] 		= $orders['order_cellphone'];
		$param["buyeremail"] 	= $orders['order_email'];
		$param["timestamp"] 	= $timestamp;
		$param["gopaymethod"] 	= $gopaymethod;
		$param["nointerest"] 	= $cardNoInterestQuota;
		$param["quotabase"] 	= $quotabase;
		$param["acceptmethod"] 	= $acceptmethod;
		//과세, 부가세 추가 :: 2018-01-02 lkh
		$param['taxfree']		= $pg_param['freeprice']; // 비과세
		//$param['comm_tax_mny']= $pg_param['comm_tax_mny']; // 과세
		$param['tax']			= $pg_param['comm_vat_mny']; // 부가세
		/*Payment 변수 설정*/
		// 모바일 일경우 모바일 결제창
		if( $this->_is_mobile_agent)
		{
			//if($this->pg_param['mobilenew'] == 'y') $this->pg_open_script();
			echo("<form name='mobile_settle_form' method='post' action='../inicis_mobile/inicis'>");
			echo("<input type='hidden' name='order_seq' value='".$this->pg_param['order_seq']."' />");
			echo("<input type='hidden' name='goods_name' value='".$this->pg_param['goods_name']."' />");
			echo("<input type='hidden' name='goods_seq' value='".$this->pg_param['goods_seq']."' />");
			//echo("<input type='hidden' name='mobilenew' value='".$this->pg_param['mobilenew']."' />");
			echo("</form>");
			echo("<script>document.mobile_settle_form.submit();</script>");
			exit;
		}

		$this->template->assign('param',$param);
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir = BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_inicis_nax.html'));
		$this->template->print_('tpl');
	}


	/**
	 * 결제창 클로징
	 */
	public function payClose(){
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir = BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_inicis_close.html'));
		$this->template->print_('tpl');
	}

	/**
	 * popup 결제창 용 함수
	 */
	public function payPopup(){
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir = BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_inicis_popup.html'));
		$this->template->print_('tpl');
	}

	 /*
	 * [최종결제요청 페이지(STEP2)]
	 *
	 */
	public function receive()
	{
		header('Content-Type: text/html; charset=EUC-KR'); // PG 처리를 위해 문서 타입 설정

		$this->load->model('reDepositmodel');
		$this->load->library('added_payment');

		session_start();

		$aPostParams = $this->input->post();
		$this->added_payment->write_log($aPostParams['orderNumber'], 'P', 'inicis', 'receive', 'process0100', $aPostParams);

		$r_use_pay_method = array(
			'VCard'=>'card',
			'Card'=>'card',
			'DirectBank'=>'account',
			'VBank'=>'virtual',
			'HPP'=>'cellphone'
		);
		$escw_yn = 'N';
		$add_log = "";
		$etc_log = "";
		$order_count = 0;
		$this->coupon_reciver_sms = array();
		$this->coupon_order_sms = array();
		$r_reservation_goods_seq = array();
		$bDuplicateTransaction = false;
		$reDepositDelFlag = true;

		## 주문서 정보 가져오기
		$orders = $this->ordermodel->get_order($aPostParams['orderNumber']);
		$result_option = $this->ordermodel->get_item_option($orders['order_seq']);
		$result_suboption = $this->ordermodel->get_item_suboption($orders['order_seq']);
		$result_shipping = $this->ordermodel->get_order_shipping($orders['order_seq']);

		try {
			// 필수값 체크
			if ( ! $aPostParams['orderNumber']) {
				throw new Exception('Require order number : [orderNumber]' . $aPostParams['orderNumber']);
			}

			if ( ! $aPostParams['mid']) {
				throw new Exception('Require mid : [mid]' . $aPostParams['mid']);
			}
			if ( ! $aPostParams['authToken']) {
				throw new Exception('Require auth token : [authToken]' . $aPostParams['authToken']);
			}
			if ( ! $orders['order_seq']) {
				throw new Exception('Require order : [order_seq]'. $orders['order_seq']);
			}

			// 주문서 결제처리 가능 여부
			if (preg_match('/virtual/', $orders['payment'])) {
				if ($orders['step'] >= '15' && $orders['step'] <= '75') {
					throw new Exception('Wrong order status : [step]' . $orders['step'] . '[payment]' . $orders['payment']);
				}
			} else {
				if ($orders['step'] >= '25' && $orders['step'] <= '75') {
					throw new Exception('Wrong order status : [step]' . $orders['step'] . '[payment]' . $orders['payment']);
				}
			}

			// 결제 시작 마킹
			$reDepositSeq = $this->reDepositmodel->insert(
				array(
					'order_seq' => $orders['order_seq'],
					'pg' => $this->config_system['pgCompany'],
					'params' => json_encode($aPostParams),
					'regist_date' => date('Y-m-d H:i:s')
				)
			);

			if ($orders['orign_order_seq']) $add_log = '[재주문]';
			if ($orders['admin_order']) $add_log = '[관리자주문]';
			if ($orders['person_seq']) $add_log = '[개인결제]';

			// 중복 결제 체크
			$this->added_payment->cfg['inicis'] = $this->cfgPg;
			$aCheck = $this->added_payment->view($orders['sitetype'], 'inicis', $orders['payment'], $orders['order_seq'], $orders['pg_transaction_number']);
			$this->added_payment->write_log($orders['order_seq'], 'P', 'inicis', 'receive', 'process0200', $aCheck);

			if ($aCheck['success'] == 'Y') {
				if ($aCheck['paymethod']) $aCheck['payMethod'] = $aCheck['paymethod'];
				if (($aCheck['paymethod'] == 'VBank' && ($aCheck['status'] == 'Y' || $aCheck['status'] == 'N')) || ($aCheck['paymethod'] != 'VBank' && $aCheck['status'] == '0')) {
					$resultMap = $aCheck;
					if ($resultMap['resultCode'] == '00') {
						$resultMap['resultCode'] = '0000';
					}
					$bDuplicateTransaction = true;
				}
			}

			$util = new INIStdPayUtil();

			try {
				if (strcmp("0000", $aPostParams["resultCode"]) == 0) {
					$timestamp = $util->getTimestamp(); // util에 의해서 자동생성
					$charset = "UTF-8"; // 리턴형식[UTF-8,EUC-KR](가맹점 수정후 고정)
					$format = "JSON"; // 리턴형식[XML,JSON,NVP](가맹점 수정후 고정)
					$signParam["authToken"] = $aPostParams['authToken'];  	// 필수
					$signParam["timestamp"] = $timestamp;  	// 필수
					$signature = $util->makeSignature($signParam);
					$authMap["mid"] = $aPostParams['mid'];   		// 필수
					$authMap["authToken"] = $aPostParams['authToken']; 	// 필수
					$authMap["signature"] = $signature; 	// 필수
					$authMap["timestamp"] = $timestamp; 	// 필수
					$authMap["charset"] = $charset;  	// default=UTF-8
					$authMap["format"] = $format;  	// default=XML

					try {
						if ( ! $bDuplicateTransaction) {
							$httpUtil = new HttpClient();
							$authResultString = "";
							if ($httpUtil->processHTTP($aPostParams['authUrl'], $authMap)) {
								$authResultString = $httpUtil->body;
							} else {
								throw new Exception('Http Connect Error');
							}
							$resultMap = json_decode($authResultString, true);
							$secureMap["mid"] = $aPostParams['mid']; // mid
							$secureMap["tstamp"] = $timestamp; // timestemp
							$secureMap["MOID"] = $resultMap["MOID"]; // MOID
							$secureMap["TotPrice"] = $resultMap["TotPrice"]; // TotPrice
							$resultMap['secureSignature'] = $util->makeSignatureAuth($secureMap);
							$this->added_payment->write_log($orders['order_seq'], 'P', 'inicis', 'receive', 'process0300', $resultMap);
						}

						$this->db->reconnect();

						if (! $orders['order_user_name']) {
							$orders['order_user_name'] = "주문자";
						}

						if ($resultMap['payMethod'] == "VBank") {
							$aInicisBank = code_load('inicisBankCode', $resultMap['VACT_BankCode']);
							$bank_name = $aInicisBank[0]['value'];
						}

						// 상세 로그용 파라미터 세팅
						$resultLog = array(
							'pg' => 'inicis',
							'order_seq' => $orders['order_seq'],
							'tno' => $resultMap['tid'],
							'amount' => $resultMap['TotPrice'],
							'app_time' => trim($resultMap['applDate'] . ' ' . $resultMap['applTime']),
							'app_no' => $resultMap['applNum'],
							'card_cd' => $resultMap['CARD_Code'],
							'noinf' => $resultMap['CARD_Interest'],
							'quota' => $resultMap['CARD_Quota'],
							'bank_name' => $bank_name,
							'bank_code' => $resultMap['VACT_BankCode'],
							'depositor' => $resultMap['VACT_Name'],
							'account' => $resultMap['VACT_Num'],
							'biller' => $resultMap['VACT_InputName'],
							'commid' => $resultMap['HPP_Num'],
							'va_date' => trim($resultMap['VACT_Date'] . ' ' . $resultMap['VACT_Time']),
							'res_cd' => $resultMap['resultCode'],
							'res_msg' => $resultMap['resultMsg']
						);

						if (! $bDuplicateTransaction){
							if (strcmp("0000", $resultMap["resultCode"]) !== 0 || strcmp($resultMap['secureSignature'], $resultMap["authSignature"]) !== 0) {

								//416630 : tid중복
								//결제 확인 처리 누락건을 재처리 하기 위해 결제종료마킹 처리안함 _gabia/_cron_deposit 
								if($resultMap['resultCode'] == '416630') {
									$reDepositDelFlag = false;
								}
								
								//TID업데이트 필요 : 거래조회시 주문번호조회는 설정값 필요 오류가 없으려면 TID로 조회해야 함  added_payment->view
								$chkModify = array(
									'pg_transaction_number' => $resultMap['tid']
								);

								// 결제 실패 DB 처리
								$this->ordermodel->set_step($orders['order_seq'], '99', $chkModify);
								$log = "이니시스 결제 실패" . chr(10) . "[" . $resultMap['resultCode'] . $resultMap['resultMsg'] . "]";
								$log_title = '결제실패[' . $resultMap['resultCode'] . ']';
								$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $log_title, $log);

								// 상세 로그 저장
								$this->added_payment->set_pg_log($resultLog);
								throw new Exception('Payment Fail : [resultCode]' . $resultMap['resultCode'] . '[secureSignature]' . $resultMap['secureSignature'] . '[authSignature]' . $resultMap['authSignature']);
							}
						}

						if (strcmp('0000', $resultMap['resultCode']) == 0 && strcmp($resultMap['secureSignature'], $resultMap['authSignature']) == 0) {
							$success = true;

							// 주문 결제수단 업데이트
							$aModify = '';
							if (preg_match('/escrow/', $orders['payment'])) {
								$escw_yn = 'Y';
							}
							if ($resultMap['payMethod']) {
								$order_payment = $r_use_pay_method[$resultMap['payMethod']];
							}
							if ($escw_yn == 'Y' && $order_payment) {
								$order_payment = 'escrow_' . $order_payment;
							}
							if ($resultMap['tid']) {
								$aModify['pg_transaction_number'] = $resultMap['tid'];
							}
							if ($order_payment) {
								$aModify['payment'] = $order_payment;
							}
							if ($aModify) {
								$this->db->update('fm_order', $aModify, array(
									'order_seq' => $orders['order_seq']
								));
							}

							if ($resultMap['payMethod'] == "VBank") {
								$virtual_account = $bank_name . ' ' . $resultMap['VACT_Num'] . ' ' . $resultMap['VACT_InputName'];
								$data = array(
									'virtual_account' => $virtual_account,
									'virtual_date' => $resultMap['VACT_Date'] . ' ' . $resultMap['VACT_Time']
								);
								$this->ordermodel->set_step($orders['order_seq'], '15', $data);
								$mail_step = '15';
								$log = "이니시스 가상계좌 주문접수";
								$log_title = $add_log . "주문접수(" . $orders['mpayment'] . ")" . $etc_log;
							} else {
								$data = array(
									'pg_approval_number' => $resultMap['applNum']
								);
								$this->ordermodel->set_step($orders['order_seq'], '25', $data);
								if (preg_match('/account/', $orders['payment'])) { // 계좌이체 결제의 경우 현금영수증
									$result = typereceipt_setting($orders['order_seq']);
									$this->added_payment->write_log($orders['order_seq'], 'P', 'inicis', 'receive', 'process0400', $result);
								}
								$mail_step = '25';
								$log = "이니시스 결제 확인";
								$log_title = $add_log . "결제확인(" . $orders['mpayment'] . ")";
							}
							$log .= chr(10) . "[" . $resultMap['resultCode'] . $resultMap['resultMsg'] . "]" . chr(10) . implode(chr(10), $data);
							$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $log_title, $log);

							// 마일리지 예치금 프로모션 쿠폰
							if ($orders['emoney'] > 0 && $orders['member_seq'] && $orders['emoney_use'] == 'none') {
								$params = array(
									'gb' => 'minus',
									'type' => 'order',
									'emoney' => $orders['emoney'],
									'ordno' => $orders['order_seq'],
									'memo' => "[차감]주문 (" . $orders['order_seq'] . ")에 의한 마일리지 차감",
									'memo_lang' => $this->membermodel->make_json_for_getAlert('mp260', $orders['order_seq']) // [차감]주문 (%s)에 의한 마일리지 차감
								);
								$this->membermodel->emoney_insert($params, $orders['member_seq']);
								$this->ordermodel->set_emoney_use($orders['order_seq'], 'use');
							}
							if ($orders['cash'] > 0 && $orders['member_seq'] && $orders['cash_use']=='none') {
								$params = array(
									'gb' => 'minus',
									'type' => 'order',
									'cash' => $orders['cash'],
									'ordno' => $orders['order_seq'],
									'memo' => "[차감]주문 (" . $orders['order_seq'] . ")에 의한 예치금 차감",
									'memo_lang' => $this->membermodel->make_json_for_getAlert('mp261', $orders['order_seq']) // [차감]주문 (%s)에 의한 예치금 차감
								);
								$this->membermodel->cash_insert($params, $orders['member_seq']);
								$this->ordermodel->set_cash_use($orders['order_seq'], 'use');
							}
							if ($result_option) {
								foreach ($result_option as $item_option) {
									if ($item_option['download_seq']) {
										$this->couponmodel->set_download_use_status($item_option['download_seq'], 'used');
									}
								}
							}
							if ($result_shipping) {
								foreach ($result_shipping as $shipping) {
									if ($shipping['shipping_coupon_down_seq']) {
										$this->couponmodel->set_download_use_status($shipping['shipping_coupon_down_seq'], 'used');
									}
								}
							}
							if ($orders['download_seq']) {
								$this->couponmodel->set_download_use_status($orders['download_seq'], 'used');
							}
							if ($orders['ordersheet_seq']) {
								$this->couponmodel->set_download_use_status($orders['ordersheet_seq'], 'used');
							}
							$this->promotionmodel->setPromotionpayment($orders);
							if ($orders['mode']) {
								$this->cartmodel->delete_mode($orders['mode']);
							}
							// 재고 처리
							if ($result_option) {
								foreach ($result_option as $data_option) {
									if ( ! in_array($data_option['goods_seq'], $r_reservation_goods_seq)) {
										$r_reservation_goods_seq[] = $data_option['goods_seq'];
									}
								}
							}
							if ($result_suboption) {
								foreach ($result_suboption as $data_suboption) {
									if ( ! in_array($data_suboption['goods_seq'], $r_reservation_goods_seq)) {
										$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
									}
								}
							}
							foreach ($r_reservation_goods_seq as $goods_seq) {
								$this->goodsmodel->modify_reservation_real($goods_seq);
							}

							// 메시지 처리
							$commonSmsData = array();
							if ($mail_step  == '25') {
								ticket_payexport_ck($orders['order_seq']);
								if (count($this->coupon_reciver_sms['order_cellphone']) > 0) {
									$order_count = 0;
									foreach (array_keys($this->coupon_reciver_sms['order_cellphone']) as $key) {
										$coupon_arr_params[$order_count] = $this->coupon_reciver_sms['params'][$key];
										$coupon_order_no[$order_count] = $this->coupon_reciver_sms['order_no'][$key];
										$coupon_order_cellphones[$order_count] = $this->coupon_reciver_sms['order_cellphone'][$key];
										$order_count = $order_count + 1;
									}
									$commonSmsData['coupon_released']['phone'] = $coupon_order_cellphones;;
									$commonSmsData['coupon_released']['params'] = $coupon_arr_params;
									$commonSmsData['coupon_released']['order_no'] = $coupon_order_no;
								}
								if (count($this->coupon_order_sms['order_cellphone']) > 0) {
									$order_count = 0;
									foreach (array_keys($this->coupon_order_sms['order_cellphone']) as $key) {
										$reciver_arr_params[$order_count] = $this->coupon_order_sms['params'][$key];
										$reciver_order_no[$order_count] = $this->coupon_order_sms['order_no'][$key];
										$reciver_order_cellphones[$order_count] = $this->coupon_order_sms['order_cellphone'][$key];
										$order_count = $order_count + 1;
									}
									$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;;
									$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
									$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;
								}
							}
							if(count($commonSmsData) > 0){
								commonSendSMS($commonSmsData);
							}
						}

						// 상세 로그 저장
						$this->added_payment->set_pg_log($resultLog);

					} catch (Exception $e) {
						$errorMsg = $e->getMessage() . ' (오류코드:' . $e->getCode() . ')';
						$this->added_payment->write_log($orders['order_seq'], 'P', 'inicis', 'receive', 'process0500', array('errorMsg' => $errorMsg));
					}
				} else {
					$errorMsg = '인증실패';
					$this->added_payment->write_log($orders['order_seq'], 'P', 'inicis', 'receive', 'process0600', array('errorMsg' => $errorMsg));
				}
			} catch (Exception $e) {
				$errorMsg = $e->getMessage() . ' (오류코드:' . $e->getCode() . ')';
				$this->added_payment->write_log($orders['order_seq'], 'P', 'inicis', 'receive', 'process0700', array('errorMsg' => $errorMsg));
			}

			// 결제 종료 마킹
			if ($reDepositSeq && $reDepositDelFlag) {
				$this->reDepositmodel->del(array('re_deposit_seq' => $reDepositSeq));
			}
		} catch (Exception $e) {
			$errorMsg = $e->getMessage();
			$this->added_payment->write_log($orders['order_seq'], 'P', 'inicis', 'receive', 'process0800', array('errorMsg' => $errorMsg));
		}

		if ($success) {
			pageRedirect('../order/complete?no=' . $orders['order_seq'], '', 'parent');
		}else{
			//'결제 실패하였습니다.'
			pageBack(getAlert('os217') . $errorMsg);
		}
	}

	//조회
	public function status() {}
}

// end file