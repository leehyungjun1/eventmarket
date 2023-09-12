<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class lg_mobile extends front_base {

	public function __construct(){
		parent::__construct();
		$this->load->helper('order');
		$this->load->helper('shipping');
		$this->load->model('ordermodel');
		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		$this->cfgPg = get_pg_config($this->config_system['pgCompany'], 'mobile');
	}

	public function auth()
	{
		// byuncs 세션만료 오류 2018-05-25
		session_start();

		global $pg;
		header("Content-Type: text/html; charset=EUC-KR");
		$pg = $this->cfgPg;
		$aPostParams = $this->input->post();

		if( isset($pg['pcCardCompanyCode']) ){
			foreach($pg['pcCardCompanyCode'] as $key => $code){
				$arr = explode(',',$pg['pcCardCompanyTerms'][$key]);
				$terms = array();
				foreach($arr as $term){
					$terms[] = sprintf('%02d',$term);
				}
				$codes[] = $code . '-' . implode(':',$terms);
			}
			$pg_param['lg_noint_quota'] = implode(',',$codes);
		}
		$pg_param['quotaopt']  = $pg['mobileInterestTerms'];
		$pg_param	= array_merge($pg_param,$this->pg_param);
		$payment	= str_replace('escrow_','',$pg_param['payment']);
		if($payment != $pg_param['payment']){
			$pg_param['escorw'] = 1;
			$pg_param['payment'] = $payment;
		}

		//$LGD_INSTALLRANGE = "0:2:3:4:5:6:7:8:9:10:11:12";		// LG유플러스 할부 기본값 (일시불 ~ 12개월)  2019-08-23 sms
		$quotaopt = (int)$pg['mobileInterestTerms'];
		if(1 < $quotaopt){
			$installrange = "0";
			for($i=2; $i<=$quotaopt; $i++){
				$installrange = $installrange.":".$i;
			}
			$LGD_INSTALLRANGE = $installrange;
		}else{
			$LGD_INSTALLRANGE = "0:2:3:4:5:6:7:8:9:10:11:12";
		}

		$orders = $this->ordermodel->get_order($aPostParams['order_seq']);
		if	($orders['pg_currency'] == 'KRW'){
			$orders['settleprice']	= floor($orders['settleprice']);
			$orders['freeprice']	= floor($orders['freeprice']);
		}
		$CST_PLATFORM = "service";

		if( $pg['mallCode'] == 'gb_gabiatest01' ) $pg['mallCode'] = "gabiatest01";	//gabia test
		//if( $pg['mallCode'] == 'gabiatest01' ) $CST_PLATFORM = "test"; //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
		$CST_MID					= $pg['mallCode'];   //상점아이디(LG유플러스으로 부터 발급받으신 상점아이디를 입력하세요)
		$LGD_MID					= $CST_MID;  //상점아이디(자동생성)
		$LGD_OID					= $orders['order_seq'];		   //주문번호(상점정의 유니크한 주문번호를 입력하세요)
		$LGD_AMOUNT				 = $orders['settleprice'];		//결제금액("," 를 제외한 결제금액을 입력하세요)
		$LGD_TAXFREEAMOUNT			= $orders['freeprice'];			//비과세 금액
		$LGD_BUYER				  = $orders['order_user_name'];	//구매자명
		$LGD_PRODUCTINFO			= $aPostParams['goods_name'];   			//상품명
		$LGD_BUYEREMAIL			 = $orders['order_email'];		//구매자 이메일
		$LGD_TIMESTAMP			  = date(YmdHms);						 //타임스탬프
		$LGD_CUSTOM_SKIN			= "SMART_XPAY2";							   //상점정의 결제창 스킨 (red, blue, cyan, green, yellow)
		$configPath 				= BASEPATH."../pg/lgdacom"; 		//LG유플러스에서 제공한 환경파일("/conf/lgdacom.conf") 위치 지정.

		/*
		 * 가상계좌(무통장) 결제 연동을 하시는 경우 아래 LGD_CASNOTEURL 을 설정하여 주시기 바랍니다.
		 */
		$LGD_CASNOTEURL				=  get_connet_protocol().$_SERVER['HTTP_HOST']."/lg_mobile/cas_noteurl";

		// 만료일시 추가
		$LGD_CLOSEDATE 			= $aPostParams['VanExpirationDate']; // 2022.02.15 가상계좌 만료일시추가 YYYYMMDDHHiiss

		/*
		 * LGD_RETURNURL 을 설정하여 주시기 바랍니다. 반드시 현재 페이지와 동일한 프로트콜 및  호스트이어야 합니다. 아래 부분을 반드시 수정하십시요.
		 */
		$LGD_RETURNURL				= get_connet_protocol().$_SERVER['HTTP_HOST']."/lg_mobile/returnurl";

		/*
		 * ISP 카드결제 연동중 모바일ISP방식(고객세션을 유지하지않는 비동기방식)의 경우, LGD_KVPMISPNOTEURL/LGD_KVPMISPWAPURL/LGD_KVPMISPCANCELURL를 설정하여 주시기 바랍니다.
		 */
		$LGD_KVPMISPNOTEURL	   	= get_connet_protocol().$_SERVER['HTTP_HOST']."/lg_mobile/note_url";
		//$LGD_KVPMISPWAPURL			= "http://".$_SERVER['HTTP_HOST']."/order/complete?no=".$LGD_OID;
		$LGD_KVPMISPWAPURL			= "";
		//$LGD_KVPMISPCANCELURL	 	= "http://".$_SERVER['HTTP_HOST']."/order/complete?no=".$LGD_OID;
		$LGD_KVPMISPCANCELURL	 	= "";

		/**IOS 처리 부분**/
		$this->load->library('user_agent');
		$platform = trim($this->agent->platform());
		$macCheck = stripos($platform, "Mac");
		$mobileCheck = $this->agent->is_mobile();

		if( $mobileCheck === true && $macCheck  !== false){
			$LGD_KVPMISPAUTOAPPYN = "N";
		}else{
			$LGD_KVPMISPAUTOAPPYN = "A";
		}

		require_once(BASEPATH."../pg/lgdacom/XPayClient.php");
		$xpay = new XPayClient($configPath, $LGD_PLATFORM);
		$xpay->Init_TX($LGD_MID);
		$LGD_HASHDATA = md5($LGD_MID.$LGD_OID.$LGD_AMOUNT.$LGD_TIMESTAMP.$xpay->config[$LGD_MID]);
		$LGD_CUSTOM_PROCESSTYPE = "TWOTR";
		$CST_WINDOW_TYPE = "submit";
		/*
		 *************************************************
		 * 2. MD5 해쉬암호화 (수정하지 마세요) - END
		 *************************************************
		 */
		$tpl_param = array(
			'CST_PLATFORM'=>$CST_PLATFORM,
			'LGD_BUYER'=>$LGD_BUYER,
			'LGD_PRODUCTINFO'=>$LGD_PRODUCTINFO,
			'LGD_AMOUNT'=>$LGD_AMOUNT,
			'LGD_INSTALLRANGE'=>$LGD_INSTALLRANGE,
			'LGD_TAXFREEAMOUNT'=>$LGD_TAXFREEAMOUNT,
			'LGD_BUYEREMAIL'=>$LGD_BUYEREMAIL,
			'LGD_OID'=>$LGD_OID,
			'CST_MID'=>$CST_MID,
			'LGD_MID'=>$LGD_MID,
			'LGD_CUSTOM_SKIN'=>$LGD_CUSTOM_SKIN,
			'LGD_CUSTOM_PROCESSTYPE'=>$LGD_CUSTOM_PROCESSTYPE,
			'LGD_TIMESTAMP'=>$LGD_TIMESTAMP,
			'LGD_HASHDATA'=>$LGD_HASHDATA,
			'LGD_RETURNURL'=>$LGD_RETURNURL,
			'LGD_CASNOTEURL'=>$LGD_CASNOTEURL,
			'LGD_KVPMISPNOTEURL'=>$LGD_KVPMISPNOTEURL,
			'LGD_KVPMISPWAPURL'=>$LGD_KVPMISPWAPURL,
			'LGD_KVPMISPCANCELURL'=>$LGD_KVPMISPCANCELURL,
			'LGD_KVPMISPAUTOAPPYN'=>$LGD_KVPMISPAUTOAPPYN,
			'LGD_CUSTOM_SWITCHINGTYPE'=>"SUBMIT",
			'LGD_WINDOW_TYPE'=>$CST_WINDOW_TYPE,
			'CST_WINDOW_TYPE'=>$CST_WINDOW_TYPE,
			'payment'=>$orders['payment'],
		);

		// 가상계좌일때만
		$parayment_virtual_check = ['escrow_virtual','virtual'];
		if (in_array( $orders['payment'], $parayment_virtual_check)) {
			$tpl_param['LGD_CLOSEDATE'] = $LGD_CLOSEDATE; // 가상계좌 만료일시 추가
		}

		/**IOS 처리 부분**/

		foreach($tpl_param as $k => $data){
			$tpl_param[$k] = mb_convert_encoding($data,'EUC-KR','UTF-8');
		}

		$this->template->assign($tpl_param);
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_lg_auth.html'));
		$this->template->print_('tpl');
	}

	public function returnurl()
	{
		global $pg;
		header("Content-Type: text/html; charset=EUC-KR");

		$this->template->assign($tpl_param);
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_lg_returnurl.html'));
		$this->template->print_('tpl');
	}

	public function payres()
	{
		$this->load->model('reDepositmodel');
		$this->load->model('cartmodel');
		$this->load->model('membermodel');
		$this->load->model('couponmodel');
		$this->load->model('promotionmodel');
		$this->load->model('goodsmodel');
		$this->load->library('added_payment');

		$aPost = $this->input->post();
		$aPostLog = $aPost;

		// 요청 파라미터 파일 로그
		unset($aPostLog['LGD_BUYER'], $aPostLog['LGD_RESPMSG'], $aPostLog['LGD_FINANCENAME'], $aPostLog['LGD_PRODUCTINFO']);
		$this->added_payment->write_log($aPost['LGD_OID'], 'M', 'lg', 'payres', 'process0100', $aPostLog);

		global $pg; // 전역변수 선언:XPayClinet 사용
		$pg = $this->cfgPg;
		$add_log = "";
		$configPath = ROOTPATH . "/pg/lgdacom";
		$bDuplicateTransaction = false;
		$r_reservation_goods_seq = array();
		$this->coupon_reciver_sms = array();
		$this->coupon_order_sms = array();

		require_once ($configPath . "/XPayClient.php");

		// 주문 정보 불러오기
		$orders = $this->ordermodel->get_order($aPost['LGD_OID']);
		$result_option = $this->ordermodel->get_item_option($orders['order_seq']);
		$result_suboption = $this->ordermodel->get_item_suboption($orders['order_seq']);
		$result_shipping = $this->ordermodel->get_order_shipping($orders['order_seq']);
		$orders['mpayment'] = $this->arr_payment[$orders['payment']];
		if(!$orders['order_user_name']) {
			$orders['order_user_name'] = "주문자";
		}
		if ($orders['orign_order_seq']) {
			$add_log = "[재주문]";
		}
		if ($orders['admin_order']) {
			$add_log = "[관리자주문]";
		}
		if ($orders['person_seq']) {
			$add_log = "[개인결제]";
		}

		try {
			// 필수값 체크
			if ( ! $aPost['LGD_OID']) {
				throw new Exception('Require LGD_OID : [LGD_OID]'.$aPost['LGD_OID']);
			}

			if ( ! $aPost['LGD_MID']) {
				throw new Exception('Require LGD_MID : [LGD_MID]'.$aPost['LGD_MID']);
			}
			if ( ! $aPost['LGD_PAYKEY']) {
				throw new Exception('Require LGD_PAYKEY : [LGD_PAYKEY]'.$aPost['LGD_PAYKEY']);
			}
			if ( ! $aPost['LGD_AMOUNT']) {
				throw new Exception('Require LGD_AMOUNT : [LGD_AMOUNT]'.$aPost['LGD_AMOUNT']);
			}
			if ( ! $orders['order_seq']) {
				throw new Exception('Require order_seq : [order_seq]'.$orders['order_seq']);
			}
			if ($orders['pg_currency'] == 'KRW') {
				$orders['settleprice'] = floor($orders['settleprice']);
			}
			if ($orders['settleprice'] != $aPost['LGD_AMOUNT']) {
				$log_title = '결제실패';
				$log = "LGU+ 모바일 결제 실패" . chr(10) . "[금액불일치]";
				$this->ordermodel->set_log($orders['order_seq'], 'pay', '시스템', $log_title, $log);
				throw new Exception('Require order_seq : [settleprice]' . $orders['settleprice'] . '[LGD_AMOUNT]' .  $aPost['LGD_AMOUNT']);
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
			if ( $aPost['LGD_OID'] )  {
				$reDepositSeq = $this->reDepositmodel->insert(
					array(
						'order_seq' => $aPost['LGD_OID'],
						'pg' => $this->config_system['pgCompany'],
						'params' => json_encode($aPost),
						'regist_date' => date('Y-m-d H:i:s')
					)
				);
			}

			// 이전 거래 상태 확인
			if ($orders['pg_transaction_number']) {
				$xpayCheck = new XPayClient($configPath, $aPost['CST_PLATFORM']);
				$aCheck = $this->added_payment->view($orders['sitetype'], 'lg', $orders['payment'], $orders['order_seq'], $orders['pg_transaction_number']);
				$this->added_payment->write_log($aPost['LGD_OID'], 'M', 'lg', 'payres', 'process0200', $aCheck); // 이전 거래 체크 결과 파일 로그
				if ($aCheck) {
					// 처리 코드 선언
					$aResult['LGD_RESPCODE'] = '0000';
					$aResult['LGD_PAYTYPE'] = $aCheck['LGD_PAYTYPE'];
					if (preg_match('escrow', $orders['order_payment'])) {
						$aResult['LGD_ESCROWYN'] = 'Y';
					}
					$bDuplicateTransaction = true;
				}
			}

			// 결제 처리 (PG)
			if (! $bDuplicateTransaction) {
				$xpay = new XPayClient($configPath, $aPost['CST_PLATFORM']);
				$xpay->Init_TX($aPost['LGD_MID']);
				$xpay->Set('LGD_TXNAME', 'PaymentByKey');
				$xpay->Set('LGD_PAYKEY', $aPost['LGD_PAYKEY']);
				if ($xpay->TX()) {
					$aResult['LGD_RESPCODE'] = $xpay->Response_Code();
					$aResult['LGD_RESPMSG'] = $xpay->Response("LGD_RESPMSG", 0);
					$aResult['LGD_TID'] = $xpay->Response("LGD_TID", 0);
					$aResult['LGD_AMOUNT'] = $xpay->Response("LGD_AMOUNT", 0);
					$aResult['LGD_PAYDATE'] = $xpay->Response("LGD_PAYDATE", 0);
					$aResult['LGD_FINANCECODE'] = $xpay->Response("LGD_FINANCECODE", 0);
					$aResult['LGD_FINANCENAME'] = $xpay->Response("LGD_FINANCENAME", 0);
					$aResult['LGD_ESCROWYN'] = $xpay->Response("LGD_ESCROWYN", 0);
					$aResult['LGD_CARDINSTALLMONTH'] = $xpay->Response("LGD_CARDINSTALLMONTH", 0);
					$aResult['LGD_CARDNOINTYN'] = $xpay->Response("LGD_CARDNOINTYN", 0);
					$aResult['LGD_FINANCEAUTHNUM'] = $xpay->Response("LGD_FINANCEAUTHNUM", 0);
					$aResult['LGD_BUYER'] = $xpay->Response("LGD_BUYER", 0);
					$aResult['LGD_ACCOUNTNUM'] = $xpay->Response("LGD_ACCOUNTNUM", 0);
					$aResult['LGD_PAYER'] = $xpay->Response("LGD_PAYER", 0);

					$aResultLog = $aResult;
					$aResultLog['LGD_RESPMSG'] = convert_to_utf8($aResult['LGD_RESPMSG']);
					$aResultLog['LGD_PRODUCTINFO'] = convert_to_utf8($aResult['LGD_PRODUCTINFO']);
					$aResultLog['LGD_FINANCENAME'] = convert_to_utf8($aResult['LGD_FINANCENAME']);
					$aResultLog['LGD_BUYER'] = convert_to_utf8($aResult['LGD_BUYER']);
					$aResultLog['LGD_PAYER'] = convert_to_utf8($aResult['LGD_PAYER']);
					$this->added_payment->write_log($aPost['LGD_OID'], 'M', 'lg', 'payres', 'process0300', $aResultLog);
				}
			}

			// DB 재연결
			$this->db->reconnect();

			// 주문서 실패 처리
			if (! $bDuplicateTransaction && $aResult['LGD_RESPCODE'] != "S007" && $aResult['LGD_RESPCODE'] != "XC01" && $aResult['LGD_RESPCODE'] != "0000") {
				$this->ordermodel->set_step($orders['order_seq'], '99');
				$log = "[" . $aResult['LGD_RESPCODE'] .$aResult['LGD_RESPMSG'] . "] 결제실패";
				$log_title = '결제실패[' . $aResult['LGD_RESPCODE'] . ']';
				$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $log_title, $log);
				throw new Exception('Payment Fail : [LGD_TID]' . $aResult['LGD_TID'] . '[LGD_MID]' . $aResult['LGD_MID'] . '[OID]' . $aPost['order_seq'] . '[LGD_RESPCODE]' . $aResult['LGD_RESPCODE']);
			}

			// 주문서 성공 처리
			if ($aResult['LGD_RESPCODE'] == "0000") {
				// 주문서 거래번호 저장
				$aModify = '';
				if ($aResult['LGD_TID']) {
					$aModify['pg_transaction_number'] = $aResult['LGD_TID'];
				}
				if ($aModify) {
					$this->db->update('fm_order', $aModify, array(
						'order_seq' => $orders['order_seq']
					));
				}

				if (preg_match('/virtual/', $orders['payment'])) {
					// 주문서 주문접수 처리
					$data = array('virtual_account' => convert_to_utf8($aResult['LGD_FINANCENAME'] . " " . $aResult['LGD_ACCOUNTNUM'] . " " . $aResult['LGD_PAYER']));
					$this->ordermodel->set_step($orders['order_seq'], '15', $data);
					$log = "LGU+ 가상계좌 주문접수" . chr(10) . "[" . $aResult['LGD_RESPCODE'] . convert_to_utf8($aResult['LGD_RESPMSG']) . "]" . chr(10) . implode(chr(10), $data);
					$log_title = $add_log . "주문접수(" . $orders['mpayment'] . ")";
					$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $log_title, $log);
					$mail_step = '15';
				} else {
					// 주문서 결제확인 처리
					$this->ordermodel->set_step($orders['order_seq'], '25');
					$log = "LGU+ 결제 확인" . chr(10) . "[" . $aResult['LGD_RESPCODE'] . convert_to_utf8($aResult['LGD_RESPMSG']) . "]" . chr(10) . implode(chr(10), $data);
					if (preg_match('/account/', $orders['payment'])) {
						$log .= chr(10) . "계좌이체 은행:" . convert_to_utf8($aResult['LGD_FINANCENAME']);
					}
					$log_title = $add_log . "결제확인" . "(" . $orders['mpayment'] . ")";
					$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $log_title, $log);
					if (preg_match('/account/', $orders['payment'])) {
						typereceipt_setting($orders['order_seq']);
					}
					ticket_payexport_ck($orders['order_seq']);
					$mail_step = '25';
				}

				// 마일리지 예치금 쿠폰 프로모션코드
				if ($orders['emoney'] > 0 && $orders['member_seq'] && $orders['emoney_use'] == 'none') {
					$params = array(
						'gb' => 'minus',
						'type' => 'order',
						'emoney' => $orders['emoney'],
						'ordno' => $orders['order_seq'],
						'memo' => "[차감]주문 (" . $orders['order_seq'] . ")에 의한 마일리지 차감",
						'memo_lang' => $this->membermodel->make_json_for_getAlert("mp260", $orders['order_seq'])
					);
					$this->membermodel->emoney_insert($params, $orders['member_seq']);
					$this->ordermodel->set_emoney_use($orders['order_seq'], 'use');
				}
				if ($orders['cash'] > 0 && $orders['member_seq'] && $orders['cash_use'] == 'none') {
					$params = array(
						'gb' => 'minus',
						'type' => 'order',
						'cash' => $orders['cash'],
						'ordno' => $orders['order_seq'],
						'memo' => "[차감]주문 (" . $orders['order_seq'] . ")에 의한 예치금 차감",
						'memo_lang' => $this->membermodel->make_json_for_getAlert("mp261", $orders['order_seq'])
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

				// 재고처리
				if ($result_option) {
					foreach ($result_option as $data_option) {
						if (! in_array($data_option['goods_seq'], $r_reservation_goods_seq)) {
							$r_reservation_goods_seq[] = $data_option['goods_seq'];
						}
					}
				}
				if ($result_suboption) {
					foreach ($result_suboption as $data_suboption) {
						if (! in_array($data_suboption['goods_seq'], $r_reservation_goods_seq)) {
							$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
						}
					}
				}
				foreach ($r_reservation_goods_seq as $goods_seq) {
					$this->goodsmodel->modify_reservation_real($goods_seq);
				}

				// 메시지 처리
				if ($mail_step == '25') {
					$commonSmsData = array();
					if (count($this->coupon_reciver_sms['order_cellphone']) > 0) {
						$order_count = 0;
						foreach(array_keys($this->coupon_reciver_sms['order_cellphone']) as $key){
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
						foreach(array_keys($this->coupon_order_sms['order_cellphone']) as $key){
							$reciver_arr_params[$order_count] = $this->coupon_order_sms['params'][$key];
							$reciver_order_no[$order_count] = $this->coupon_order_sms['order_no'][$key];
							$reciver_order_cellphones[$order_count] = $this->coupon_order_sms['order_cellphone'][$key];
							$order_count = $order_count + 1;
						}
						$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;;
						$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
						$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;
					}
					if (count($commonSmsData) > 0) {
						commonSendSMS($commonSmsData);
					}
				}
			}
		} catch (Exception $e) {
			$errorMsg = $e->getMessage();
			$this->added_payment->write_log($orders['order_seq'], 'M', 'lg', 'payres', 'process0400', array('errorMsg' => $errorMsg)); // 파일 로그 저장
		}

		// 결제 종료 마킹
		if ($reDepositSeq) {
			$this->reDepositmodel->del(array('re_deposit_seq' => $reDepositSeq));
		}

		$resultLog = array(
			'pg' => 'lg',
			'order_seq' => $aPost['LGD_OID'],
			'tno' => $aResult['LGD_TID'],
			'amount' => $aResult['LGD_AMOUNT'],
			'app_time' => $aResult['LGD_PAYDATE'],
			'bank_code' => $aResult['LGD_FINANCECODE'],
			'bank_name' => convert_to_utf8($aResult['LGD_FINANCENAME']),
			'escw_yn' => $aResult['LGD_ESCROWYN'],
			'quota' => $aResult['LGD_CARDINSTALLMONTH'],
			'noinf' => $aResult['LGD_CARDNOINTYN'],
			'card_cd' => $aResult['LGD_FINANCEAUTHNUM'],
			'depositor' => convert_to_utf8($aResult['LGD_PAYER']),
			'account' => $aResult['LGD_ACCOUNTNUM'],
			'biller' => convert_to_utf8($aResult['LGD_BUYER']),
			'res_cd' => $aResult['LGD_RESPCODE'],
			'res_msg' => convert_to_utf8($aResult['LGD_RESPMSG'])
		);
		$this->added_payment->set_pg_log($resultLog);

		echo ("<script>parent.parent.location.href='../order/complete?no=" . $orders['order_seq'] . "';</script>");
	}

	## 신용카드, 계좌이체  처리
	public function note_url()
	{
		$this->load->model('membermodel');
		$this->load->model('couponmodel');
		$this->load->model('goodsmodel');

		$pg = $this->cfgPg;
		$orders = $this->ordermodel->get_order($_POST["LGD_OID"]);
		if	($orders['pg_currency'] == 'KRW'){
			$orders['settleprice']	= floor($orders['settleprice']);
		}

		/*
		 * 공통결제결과 정보
		 */
		$LGD_RESPCODE = "";		   			// 응답코드: 0000(성공) 그외 실패
		$LGD_RESPMSG = "";						// 응답메세지
		$LGD_MID = "";							// 상점아이디
		$LGD_OID = "";							// 주문번호
		$LGD_AMOUNT = "";			 			// 거래금액
		$LGD_TID = "";							// LG유플러스에서 부여한 거래번호
		$LGD_PAYTYPE = "";						// 결제수단코드
		$LGD_PAYDATE = "";						// 거래일시(승인일시/이체일시)
		$LGD_HASHDATA = "";		   			// 해쉬값
		$LGD_FINANCECODE = "";					// 결제기관코드(카드종류/은행코드/이통사코드)
		$LGD_FINANCENAME = "";					// 결제기관이름(카드이름/은행이름/이통사이름)
		$LGD_ESCROWYN = "";		   			// 에스크로 적용여부
		$LGD_TIMESTAMP = "";		  			// 타임스탬프
		$LGD_FINANCEAUTHNUM = "";	 			// 결제기관 승인번호(신용카드, 계좌이체, 상품권)

		/*
		 * 신용카드 결제결과 정보
		 */
		$LGD_CARDNUM = "";						// 카드번호(신용카드)
		$LGD_CARDINSTALLMONTH = "";   			// 할부개월수(신용카드)
		$LGD_CARDNOINTYN = "";					// 무이자할부여부(신용카드) - '1'이면 무이자할부 '0'이면 일반할부
		$LGD_TRANSAMOUNT = "";					// 환율적용금액(신용카드)
		$LGD_EXCHANGERATE = "";	   			// 환율(신용카드)

		/*
		 * 휴대폰
		 */
		$LGD_PAYTELNUM = "";		  			// 결제에 이용된전화번호

		/*
		 * 계좌이체, 무통장
		 */
		$LGD_ACCOUNTNUM = "";		 			// 계좌번호(계좌이체, 무통장입금)
		$LGD_CASTAMOUNT = "";		 			// 입금총액(무통장입금)
		$LGD_CASCAMOUNT = "";		 			// 현입금액(무통장입금)
		$LGD_CASFLAG = "";						// 무통장입금 플래그(무통장입금) - 'R':계좌할당, 'I':입금, 'C':입금취소
		$LGD_CASSEQNO = "";		   			// 입금순서(무통장입금)
		$LGD_CASHRECEIPTNUM = "";	 			// 현금영수증 승인번호
		$LGD_CASHRECEIPTSELFYN = "";  			// 현금영수증자진발급제유무 Y: 자진발급제 적용, 그외 : 미적용
		$LGD_CASHRECEIPTKIND = "";				// 현금영수증 종류 0: 소득공제용 , 1: 지출증빙용

		/*
		 * OK캐쉬백
		 */
		$LGD_OCBSAVEPOINT = "";	   			// OK캐쉬백 적립포인트
		$LGD_OCBTOTALPOINT = "";	  			// OK캐쉬백 누적포인트
		$LGD_OCBUSABLEPOINT = "";	 			// OK캐쉬백 사용가능 포인트

		/*
		 * 구매정보
		 */
		$LGD_BUYER = "";			  			// 구매자
		$LGD_PRODUCTINFO = "";					// 상품명
		$LGD_BUYERID = "";						// 구매자 ID
		$LGD_BUYERADDRESS = "";	   			// 구매자 주소
		$LGD_BUYERPHONE = "";		 			// 구매자 전화번호
		$LGD_BUYEREMAIL = "";		 			// 구매자 이메일
		$LGD_BUYERSSN = "";		   			// 구매자 주민번호
		$LGD_PRODUCTCODE = "";					// 상품코드
		$LGD_RECEIVER = "";		   			// 수취인
		$LGD_RECEIVERPHONE = "";	  			// 수취인 전화번호
		$LGD_DELIVERYINFO = "";	   			// 배송지

		$LGD_RESPCODE			= $_POST["LGD_RESPCODE"];
		$LGD_RESPMSG			 = $_POST["LGD_RESPMSG"];
		$LGD_MID				 = $_POST["LGD_MID"];
		$LGD_OID				 = $_POST["LGD_OID"];
		$LGD_AMOUNT			  = $_POST["LGD_AMOUNT"];
		$LGD_TID				 = $_POST["LGD_TID"];
		$LGD_PAYTYPE			 = $_POST["LGD_PAYTYPE"];
		$LGD_PAYDATE			 = $_POST["LGD_PAYDATE"];
		$LGD_HASHDATA			= $_POST["LGD_HASHDATA"];
		$LGD_FINANCECODE		 = $_POST["LGD_FINANCECODE"];
		$LGD_FINANCENAME		 = $_POST["LGD_FINANCENAME"];
		$LGD_ESCROWYN			= $_POST["LGD_ESCROWYN"];
		$LGD_TRANSAMOUNT		 = $_POST["LGD_TRANSAMOUNT"];
		$LGD_EXCHANGERATE		= $_POST["LGD_EXCHANGERATE"];
		$LGD_CARDNUM			 = $_POST["LGD_CARDNUM"];
		$LGD_CARDINSTALLMONTH	= $_POST["LGD_CARDINSTALLMONTH"];
		$LGD_CARDNOINTYN		 = $_POST["LGD_CARDNOINTYN"];
		$LGD_TIMESTAMP		   = $_POST["LGD_TIMESTAMP"];
		$LGD_FINANCEAUTHNUM	  = $_POST["LGD_FINANCEAUTHNUM"];
		$LGD_PAYTELNUM		   = $_POST["LGD_PAYTELNUM"];
		$LGD_ACCOUNTNUM		  = $_POST["LGD_ACCOUNTNUM"];
		$LGD_CASTAMOUNT		  = $_POST["LGD_CASTAMOUNT"];
		$LGD_CASCAMOUNT		  = $_POST["LGD_CASCAMOUNT"];
		$LGD_CASFLAG			 = $_POST["LGD_CASFLAG"];
		$LGD_CASSEQNO			= $_POST["LGD_CASSEQNO"];
		$LGD_CASHRECEIPTNUM	  = $_POST["LGD_CASHRECEIPTNUM"];
		$LGD_CASHRECEIPTSELFYN   = $_POST["LGD_CASHRECEIPTSELFYN"];
		$LGD_CASHRECEIPTKIND	 = $_POST["LGD_CASHRECEIPTKIND"];
		$LGD_OCBSAVEPOINT		= $_POST["LGD_OCBSAVEPOINT"];
		$LGD_OCBTOTALPOINT	   = $_POST["LGD_OCBTOTALPOINT"];
		$LGD_OCBUSABLEPOINT	  = $_POST["LGD_OCBUSABLEPOINT"];

		$LGD_BUYER			   = $_POST["LGD_BUYER"];
		$LGD_PRODUCTINFO		 = $_POST["LGD_PRODUCTINFO"];
		$LGD_BUYERID			 = $_POST["LGD_BUYERID"];
		$LGD_BUYERADDRESS		= $_POST["LGD_BUYERADDRESS"];
		$LGD_BUYERPHONE		  = $_POST["LGD_BUYERPHONE"];
		$LGD_BUYEREMAIL		  = $_POST["LGD_BUYEREMAIL"];
		$LGD_BUYERSSN			= $_POST["LGD_BUYERSSN"];
		$LGD_PRODUCTCODE		 = $_POST["LGD_PRODUCTCODE"];
		$LGD_RECEIVER			= $_POST["LGD_RECEIVER"];
		$LGD_RECEIVERPHONE	   = $_POST["LGD_RECEIVERPHONE"];
		$LGD_DELIVERYINFO		= $_POST["LGD_DELIVERYINFO"];

		$LGD_MERTKEY 			= $pg['merchantKey'];  //LG유플러스에서 발급한 상점키로 변경해 주시기 바랍니다.
		$LGD_HASHDATA2 			= md5($LGD_MID.$LGD_OID.$orders['settleprice'].$LGD_RESPCODE.$LGD_TIMESTAMP.$LGD_MERTKEY);

		if($LGD_RESPCODE == "0000"){
			$res_msg = mb_convert_encoding("성공", "EUC-KR","UTF-8");
		}else{
			$res_msg = mb_convert_encoding("실패", "EUC-KR","UTF-8");
		}

			## 로그저장
			$pg_log['pg']			= $this->config_system['pgCompany'];
			$pg_log['res_cd'] 		= $LGD_RESPCODE;
			$pg_log['res_msg'] 		= $LGD_RESPMSG." ".$res_msg;
			$pg_log['order_seq'] 	= $LGD_OID;
			$pg_log['amount'] 		= $LGD_AMOUNT;
			$pg_log['tno'] 			= $LGD_TID;
			$pg_log['app_time'] 	= $LGD_PAYDATE;
			$pg_log['bank_code'] 	= $LGD_FINANCECODE;
			$pg_log['bank_name'] 	= $LGD_FINANCENAME;
			$pg_log['escw_yn'] 		= $LGD_ESCROWYN;
			$pg_log['quota'] 		= $LGD_CARDINSTALLMONTH;
			$pg_log['noinf'] 		= $LGD_CARDNOINTYN;
			$pg_log['card_cd'] 		= $LGD_FINANCEAUTHNUM;
			$pg_log['biller'] 		= $LGD_BUYER;
			$pg_log['regist_date'] = date('Y-m-d H:i:s');
			$this->db->insert('fm_order_pg_log', $pg_log);


		/*
		 * 상점 처리결과 리턴메세지
		 *
		 * OK   : 상점 처리결과 성공
		 * 그외 : 상점 처리결과 실패
		 *
		 * ※ 주의사항 : 성공시 'OK' 문자이외의 다른문자열이 포함되면 실패처리 되오니 주의하시기 바랍니다.
		 */
		$resultMSG =  mb_convert_encoding("해쉬값 불일치",'EUC-KR','UTF-8');

		if ($LGD_HASHDATA2 == $LGD_HASHDATA) {	  //해쉬값 검증이 성공하면

			$resultMSG = mb_convert_encoding("결제실패",'EUC-KR','UTF-8');

			if($LGD_RESPCODE == "0000"){			//결제가 성공이면

				$resultMSG = "OK";

				// 주문 상품 재고 체크
				$runout = false;
				$cfg['order'] = config_load('order');

				$providerList = array();
				// 출고량 업데이트를 위한 변수선언
				$r_reservation_goods_seq = array();
				$result_option		= $this->ordermodel->get_item_option($LGD_OID);
				$result_suboption	= $this->ordermodel->get_item_suboption($LGD_OID);
				$data_shipping		= $this->ordermodel->get_order_shipping($LGD_OID);

				if($result_option){
					foreach($result_option as $data_option){
						if($data_option['provider_seq']) $providerList[$data_option['provider_seq']]	= 1;
						// 출고량 업데이트를 위한 변수정의
						if(!in_array($data_option['goods_seq'],$r_reservation_goods_seq)){
							$r_reservation_goods_seq[] = $data_option['goods_seq'];
						}
					}
				}
				if($result_suboption){
					foreach($result_suboption as $data_suboption){
						// 출고량 업데이트를 위한 변수정의
						if(!in_array($data_suboption['goods_seq'],$r_reservation_goods_seq)){
							$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
						}
					}
				}

				// 회원 마일리지 차감
				if( $orders['emoney']>0 && $orders['member_seq'] && $orders['emoney_use']=='none')
				{
					$params = array(
							'gb'		=> 'minus',
							'type'		=> 'order',
							'emoney'	=> $orders['emoney'],
							'ordno'		=> $LGD_OID,
							'memo'		=> "[차감]주문 ({$LGD_OID})에 의한 마일리지 차감",
							'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp260",$LGD_OID),   // [차감]주문 (%s)에 의한 마일리지 차감
					);
					$this->membermodel->emoney_insert($params, $orders['member_seq']);
					$this->ordermodel->set_emoney_use($LGD_OID,'use');
				}

				// 회원 예치금 차감
				if( $orders['cash']>0 && $orders['member_seq'] && $orders['cash_use']=='none')
				{
					$params = array(
							'gb'		=> 'minus',
							'type'		=> 'order',
							'cash'		=> $orders['cash'],
							'ordno'		=> $LGD_OID,
							'memo'		=> "[차감]주문 ({$LGD_OID})에 의한 예치금 차감",
							'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp261",$LGD_OID),   // [차감]주문 (%s)에 의한 예치금 차감
					);

					$this->membermodel->cash_insert($params, $orders['member_seq']);
					$this->ordermodel->set_cash_use($LGD_OID,'use');
				}

				//상품쿠폰사용
				if($result_option) foreach($result_option as $item_option){
					if($item_option['download_seq']) $this->couponmodel->set_download_use_status($item_option['download_seq'],'used');
				}

				//배송비쿠폰사용 @2015-06-22 pjm
				if($data_shipping) foreach($data_shipping as $shipping){
					if($shipping['shipping_coupon_down_seq']) $this->couponmodel->set_download_use_status($shipping['shipping_coupon_down_seq'],'used');
				}

				//배송비쿠폰사용(사용안함)
				if($orders['download_seq']) $this->couponmodel->set_download_use_status($orders['download_seq'],'used');

				//주문서쿠폰 사용 처리 by hed
				if($orders['ordersheet_seq']) $this->couponmodel->set_download_use_status($orders['ordersheet_seq'],'used');

				$data = array('pg_transaction_number' => $LGD_TID);

				// sms 발송을 위한 변수 저장
				$this->coupon_reciver_sms = array();
				$this->coupon_order_sms = array();
				$this->send_for_provider = array();
				$this->ordermodel->set_step($LGD_OID,25,$data);

				$log = "LGU+ 결제 확인". chr(10)."[" .$LGD_RESPCODE . $LGD_RESPMSG . "]" . chr(10). implode(chr(10),$data);
				if( preg_match('/account/',$orders['payment']))
				{
					$log .= chr(10) . "계좌이체 은행:" . $LGD_FINANCENAME;
				}

				$add_log = "";
				if($orders['orign_order_seq']) $add_log = "[재주문]";
				if($orders['admin_order']) $add_log = "[관리자주문]";
				if($orders['person_seq']) $add_log = "[개인결제]";
				$log_title =  $add_log."결제확인"."(".$orders['mpayment'].")";
				$this->ordermodel->set_log($LGD_OID,'pay',$orders['order_user_name'],$log_title,$log);

				// 출고예약량 업데이트
				foreach($r_reservation_goods_seq as $goods_seq){
					$this->goodsmodel->modify_reservation_real($goods_seq);
				}

				// 현금영수증
				if( $orders['step'] < '25' || $orders['step'] > '85' ){
					typereceipt_setting($orders['order_seq']);
				}

				// 결제확인 sms발송
				$commonSmsData = array();
				send_mail_step25($orders['order_seq']);
				if($orders['sms_25_YN'] != 'Y'){
					$params['shopName'] = $this->config_basic['shopName'];
					$params['ordno']	= $orders['order_seq'];
					$params['user_name']		= $orders['order_user_name'];

					if( $orders['order_cellphone'])	{
						$commonSmsData['settle']['phone'][] = $orders['order_cellphone'];
						$commonSmsData['settle']['params'][] = $params;
						$commonSmsData['settle']['order_seq'][] = $orders['order_seq'];
					}

					sendSMS_for_provider('settle', $providerList, $params);
					//입점관리자 SMS 데이터
					if(count($this->send_for_provider['order_cellphone']) > 0){
						$provider_count = 0;
						foreach($this->send_for_provider['order_cellphone'] as $key=>$value){
							$provider_msg[$provider_count]			= $this->send_for_provider['msg'][$key];
							$provider_order_cellphones[$provider_count] = $this->send_for_provider['order_cellphone'][$key];
							$provider_count					=$provider_count+1;
						}
						$commonSmsData['provider']['phone'] = $provider_order_cellphones;
						$commonSmsData['provider']['msg']	= $provider_msg;
					}
				}

				//티켓상품 자동 출고처리구문 순차진행을 위해 분리함 @2017-08-16
				ticket_payexport_ck($orders['order_seq']);

				//받는 사람 티켓상품 SMS 데이터
				if(count($this->coupon_reciver_sms['order_cellphone']) > 0){
					$order_count = 0;
					foreach($this->coupon_reciver_sms['order_cellphone'] as $key=>$value){
						$coupon_arr_params[$order_count]		= $this->coupon_reciver_sms['params'][$key];
						$coupon_order_no[$order_count]			= $this->coupon_reciver_sms['order_no'][$key];
						$coupon_order_cellphones[$order_count] = $this->coupon_reciver_sms['order_cellphone'][$key];
						$order_count					=$order_count+1;
					}

					$commonSmsData['coupon_released']['phone'] = $coupon_order_cellphones;;
					$commonSmsData['coupon_released']['params'] = $coupon_arr_params;
					$commonSmsData['coupon_released']['order_no'] = $coupon_order_no;
				}

				//주문자 티켓상품 SMS 데이터
				if(count($this->coupon_order_sms['order_cellphone']) > 0){
					$order_count = 0;
					foreach($this->coupon_order_sms['order_cellphone'] as $key=>$value){
						$reciver_arr_params[$order_count]		= $this->coupon_order_sms['params'][$key];
						$reciver_order_no[$order_count]			= $this->coupon_order_sms['order_no'][$key];
						$reciver_order_cellphones[$order_count] = $this->coupon_order_sms['order_cellphone'][$key];
						$order_count					=$order_count+1;
					}

					$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;;
					$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
					$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;
				}
				if(count($commonSmsData) > 0){
					commonSendSMS($commonSmsData);
					$this->db->where('order_seq', $orders['order_seq']);
					$this->db->update('fm_order', array('sms_25_YN'=>'Y'));
				}

				// [판매지수 EP] 쿠키로 ep 등록 처리된 주문건인지 확인 후 EP 수집 :: 2018-09-18 pjw
				if(!$this->statsmodel) $this->load->model('statsmodel');
				$this->statsmodel->set_order_sale_ep($order_seq);

			}
		}

		echo $resultMSG;
	}

	# 무통장(가상계좌) : 계좌할당, 입금, 취소 처리
	public function cas_noteurl()
	{
		$this->load->model('membermodel');
		$this->load->model('couponmodel');
		$this->load->model('goodsmodel');

		$pg = $this->cfgPg;
		$orders = $this->ordermodel->get_order($_POST["LGD_OID"]);
		if	($orders['pg_currency'] == 'KRW'){
			$orders['settleprice']	= floor($orders['settleprice']);
		}

		/*
		 * [상점 결제결과처리(DB) 페이지]
		 *
		 * 1) 위변조 방지를 위한 hashdata값 검증은 반드시 적용하셔야 합니다.
		 *
		 */
		$LGD_RESPCODE			= $_POST["LGD_RESPCODE"];			 // 응답코드: 0000(성공) 그외 실패
		$LGD_RESPMSG			 = $_POST["LGD_RESPMSG"];			  // 응답메세지
		$LGD_MID				 = $_POST["LGD_MID"];				  // 상점아이디
		$LGD_OID				 = $_POST["LGD_OID"];				  // 주문번호
		$LGD_AMOUNT			  = $_POST["LGD_AMOUNT"];			   // 거래금액
		$LGD_TID				 = $_POST["LGD_TID"];				  // LG유플러스에서 부여한 거래번호
		$LGD_PAYTYPE			 = $_POST["LGD_PAYTYPE"];			  // 결제수단코드
		$LGD_PAYDATE			 = $_POST["LGD_PAYDATE"];			  // 거래일시(승인일시/이체일시)
		$LGD_HASHDATA			= $_POST["LGD_HASHDATA"];			 // 해쉬값
		$LGD_FINANCECODE		 = $_POST["LGD_FINANCECODE"];		  // 결제기관코드(은행코드)
		$LGD_FINANCENAME		 = $_POST["LGD_FINANCENAME"];		  // 결제기관이름(은행이름)
		$LGD_ESCROWYN			= $_POST["LGD_ESCROWYN"];			 // 에스크로 적용여부
		$LGD_TIMESTAMP		   = $_POST["LGD_TIMESTAMP"];			// 타임스탬프
		$LGD_ACCOUNTNUM		  = $_POST["LGD_ACCOUNTNUM"];		   // 계좌번호(무통장입금)
		$LGD_CASTAMOUNT		  = $_POST["LGD_CASTAMOUNT"];		   // 입금총액(무통장입금)
		$LGD_CASCAMOUNT		  = $_POST["LGD_CASCAMOUNT"];		   // 현입금액(무통장입금)
		$LGD_CASFLAG			 = $_POST["LGD_CASFLAG"];			  // 무통장입금 플래그(무통장입금) - 'R':계좌할당, 'I':입금, 'C':입금취소
		$LGD_CASSEQNO			= $_POST["LGD_CASSEQNO"];			 // 입금순서(무통장입금)
		$LGD_CASHRECEIPTNUM	  = $_POST["LGD_CASHRECEIPTNUM"];	   // 현금영수증 승인번호
		$LGD_CASHRECEIPTSELFYN   = $_POST["LGD_CASHRECEIPTSELFYN"];	// 현금영수증자진발급제유무 Y: 자진발급제 적용, 그외 : 미적용
		$LGD_CASHRECEIPTKIND	 = $_POST["LGD_CASHRECEIPTKIND"];	  // 현금영수증 종류 0: 소득공제용 , 1: 지출증빙용
		$LGD_PAYER	 			 = $_POST["LGD_PAYER"];	  			// 입금자명

		/*
		 * 구매정보
		 */
		$LGD_BUYER			   = $_POST["LGD_BUYER"];				// 구매자
		$LGD_PRODUCTINFO		 = $_POST["LGD_PRODUCTINFO"];		  // 상품명
		$LGD_BUYERID			 = $_POST["LGD_BUYERID"];			  // 구매자 ID
		$LGD_BUYERADDRESS		= $_POST["LGD_BUYERADDRESS"];		 // 구매자 주소
		$LGD_BUYERPHONE		  = $_POST["LGD_BUYERPHONE"];		   // 구매자 전화번호
		$LGD_BUYEREMAIL		  = $_POST["LGD_BUYEREMAIL"];		   // 구매자 이메일
		$LGD_BUYERSSN			= $_POST["LGD_BUYERSSN"];			 // 구매자 주민번호
		$LGD_PRODUCTCODE		 = $_POST["LGD_PRODUCTCODE"];		  // 상품코드
		$LGD_RECEIVER			= $_POST["LGD_RECEIVER"];			 // 수취인
		$LGD_RECEIVERPHONE	   = $_POST["LGD_RECEIVERPHONE"];		// 수취인 전화번호
		$LGD_DELIVERYINFO		= $_POST["LGD_DELIVERYINFO"];		 // 배송지

		$LGD_MERTKEY 			= $pg['merchantKey'];  //LG유플러스에서 발급한 상점키로 변경해 주시기 바랍니다.
		$LGD_HASHDATA2 			= md5($LGD_MID.$LGD_OID.$orders['settleprice'].$LGD_RESPCODE.$LGD_TIMESTAMP.$LGD_MERTKEY);

		/*
		 * 상점 처리결과 리턴메세지
		 *
		 * OK  : 상점 처리결과 성공
		 * 그외 : 상점 처리결과 실패
		 *
		 * ※ 주의사항 : 성공시 'OK' 문자이외의 다른문자열이 포함되면 실패처리 되오니 주의하시기 바랍니다.
		 */
		$resultMSG				=  mb_convert_encoding("해쉬값 불일치",'EUC-KR','UTF-8');

		if ( $LGD_HASHDATA2 == $LGD_HASHDATA ) { //해쉬값 검증이 성공이면

			if ( "0000" == $LGD_RESPCODE ){ //결제가 성공이면
				$orders['mpayment'] = $this->arr_payment[$orders['payment']];

				$r_reservation_goods_seq = array();
				$result_option		= $this->ordermodel->get_item_option($LGD_OID);
				$result_suboption	= $this->ordermodel->get_item_suboption($LGD_OID);
				$data_shipping		= $this->ordermodel->get_order_shipping($LGD_OID);

				$providerList				= array();
				## 출고예약량 업데이트 대상 상품번호
				if($result_option){
					foreach($result_option as $data_option){
						if($data_option['provider_seq']) $providerList[$data_option['provider_seq']]	= 1;
						// 출고량 업데이트를 위한 변수정의
						if(!in_array($data_option['goods_seq'],$r_reservation_goods_seq)){
							$r_reservation_goods_seq[] = $data_option['goods_seq'];
						}
					}
				}
				if($result_suboption){
					foreach($result_suboption as $data_suboption){
						// 출고량 업데이트를 위한 변수정의
						if(!in_array($data_suboption['goods_seq'],$r_reservation_goods_seq)){
							$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
						}
					}
				}

				if( "R" == $LGD_CASFLAG ) {

					/*
					 * 무통장 할당 성공 결과 상점 처리(DB) 부분
					 * 상점 결과 처리가 정상이면 "OK"
					 */
					//if( 무통장 할당 성공 상점처리결과 성공 )
					if(  preg_match('/virtual/',$orders['payment'])){
						// payres() 단에서 처리됨 @2017-07-13
						$isDBOK = true;
					}

					$resultMSG = "OK";

				}else if( "I" == $LGD_CASFLAG ) {
	 				/*
					 * 무통장 입금 성공 결과 상점 처리(DB) 부분
					 * 상점 결과 처리가 정상이면 "OK"
					 */
					//if( 무통장 입금 성공 상점처리결과 성공 )
					// 쿠폰 sms 발송을 위한 변수 저장
					if($orders['step'] < '25' || $orders['step'] > '85'){ // 결제이후 프로세스 진행 이후에는 결제확인 처리 하지 않음
						$this->coupon_reciver_sms = array();
						$this->coupon_order_sms = array();
						$this->send_for_provider = array();
						$this->ordermodel->set_step($LGD_OID,25,$data);
					
						$log		= "LGU+ 결제 확인". chr(10)."[" .$LGD_RESPCODE . $LGD_RESPMSG . "]" . chr(10). implode(chr(10),$data);
						$log_title	= "결제확인"."(".$orders['mpayment'].")";
						$this->ordermodel->set_log($LGD_OID,'pay',$orders['order_user_name'],$log_title,$log);
	
						// 가상계좌 결제의 경우 현금영수증
						if( $orders['step'] < '25' || $orders['step'] > '85' ){
							typereceipt_setting($orders['order_seq']);
						}
	
						// 출고예약량 업데이트
						foreach($r_reservation_goods_seq as $goods_seq){
							$this->goodsmodel->modify_reservation_real($goods_seq);
						}
	
						// 결제확인 mail/sms발송
						send_mail_step25($orders['order_seq']);
						if($orders['sms_25_YN'] != 'Y'){
							$params['shopName'] = $this->config_basic['shopName'];
							$params['ordno']	= $orders['order_seq'];
							if( $orders['order_cellphone'] ){
								$commonSmsData['settle']['phone'][] = $orders['order_cellphone'];
								$commonSmsData['settle']['params'][] = $params;
								$commonSmsData['settle']['order_seq'][] = $orders['order_seq'];
							}
	
							sendSMS_for_provider('settle', $providerList, $params);
							//입점관리자 SMS 데이터
							if(count($this->send_for_provider['order_cellphone']) > 0){
								$provider_count = 0;
								foreach($this->send_for_provider['order_cellphone'] as $key=>$value){
									$provider_msg[$provider_count]			= $this->send_for_provider['msg'][$key];
									$provider_order_cellphones[$provider_count] = $this->send_for_provider['order_cellphone'][$key];
									$provider_count					=$provider_count+1;
								}
								$commonSmsData['provider']['phone'] = $provider_order_cellphones;
								$commonSmsData['provider']['msg']	= $provider_msg;
							}
	
							$this->db->where('order_seq', $orders['order_seq']);
							$this->db->update('fm_order', array('sms_25_YN'=>'Y'));
						}
	
						//티켓상품 자동 출고처리구문 순차진행을 위해 분리함 @2017-08-16
						ticket_payexport_ck($orders['order_seq']);
	
						//받는 사람 티켓상품 SMS 데이터
						if(count($this->coupon_reciver_sms['order_cellphone']) > 0){
							$order_count = 0;
							foreach($this->coupon_reciver_sms['order_cellphone'] as $key=>$value){
								$coupon_arr_params[$order_count]		= $this->coupon_reciver_sms['params'][$key];
								$coupon_order_no[$order_count]			= $this->coupon_reciver_sms['order_no'][$key];
								$coupon_order_cellphones[$order_count] = $this->coupon_reciver_sms['order_cellphone'][$key];
								$order_count					=$order_count+1;
							}
	
							$commonSmsData['coupon_released']['phone'] = $coupon_order_cellphones;;
							$commonSmsData['coupon_released']['params'] = $coupon_arr_params;
							$commonSmsData['coupon_released']['order_no'] = $coupon_order_no;
						}
	
						//주문자 티켓상품 SMS 데이터
						if(count($this->coupon_order_sms['order_cellphone']) > 0){
							$order_count = 0;
							foreach($this->coupon_order_sms['order_cellphone'] as $key=>$value){
								$reciver_arr_params[$order_count]		= $this->coupon_order_sms['params'][$key];
								$reciver_order_no[$order_count]			= $this->coupon_order_sms['order_no'][$key];
								$reciver_order_cellphones[$order_count] = $this->coupon_order_sms['order_cellphone'][$key];
								$order_count					=$order_count+1;
							}
	
							$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;;
							$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
							$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;
						}
						if(count($commonSmsData) > 0){
							commonSendSMS($commonSmsData);
						}
	
						// [판매지수 EP] 쿠키로 ep 등록 처리된 주문건인지 확인 후 EP 수집 :: 2018-09-18 pjw
						if(!$this->statsmodel) $this->load->model('statsmodel');
						$this->statsmodel->set_order_sale_ep($order_seq);
	
						$resultMSG = "OK";
	
					} else {
						$error = [
							"msg" => "이미 처리된 결제건입니다.",
							"data" => $orders,
						];
						$this->writeLog('payres_mobile_err', $error);
						$resultMSG = mb_convert_encoding("이미 처리된 결제건입니다(LGD_CASNOTEURL).",'EUC-KR','UTF-8');
					}
				}else if( "C" == $LGD_CASFLAG ) {
	 				/*
					 * 무통장 입금취소 성공 결과 상점 처리(DB) 부분
					 * 상점 결과 처리가 정상이면 "OK"
					 */
					//if( 무통장 입금취소 성공 상점처리결과 성공 )
					// 주문취소
					$this->ordermodel->set_step($LGD_OID,85);
					$log				= "[" .$LGD_RESPCODE . $LGD_RESPMSG . "] 입금취소";
					$log_title			= "입금취소"."(".$orders['mpayment'].")";
					$this->ordermodel->set_log($LGD_OID,'pay',$orders['order_user_name'],$log_title,$log);

					$resultMSG = "OK";
				}

			} else { //결제가 실패이면
				/*
				 * 거래실패 결과 상점 처리(DB) 부분
				 * 상점결과 처리가 정상이면 "OK"
				 */
				//if( 결제실패 상점처리결과 성공 )
				$resultMSG = "OK";
			}
		} else { //해쉬값이 검증이 실패이면
			/*
			 * hashdata검증 실패 로그를 처리하시기 바랍니다.
			 */
			$resultMSG =  mb_convert_encoding("결제결과 상점 DB처리(LGD_CASNOTEURL) 해쉬값 검증이 실패하였습니다.",'EUC-KR','UTF-8');
		}

		echo $resultMSG;
	}
}