<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

class naverpayment extends front_base {

	protected $pg_param; // 넘겨받은 pg 변수 array

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
		$this->load->model('refundmodel');
		$this->load->library('added_payment');
		$this->load->model('reDepositmodel');
		$this->load->library('naverpaymentlib');
	}

	// 결제 인증요청 페이지 - STEP1
	public function request(){
		$error = false;

		// 네이버페이 사용여부 확인
		if ($this->config_system['not_use_naverpayment'] === 'y') {
			$error = true;
			$message = getAlert('os178');
		}

		session_start();

		//app/order.php function pay에서 전달 해준 데이터
		$this->pg_param = json_decode(base64_decode($this->input->get('jsonParam')),true);

		if(!$this->session->naverpay_pg) {
			$pg_param = $this->pg_param;
			$orders = $this->ordermodel->get_order($pg_param['order_seq']);
			$options = $this->ordermodel->get_item_option($pg_param['order_seq']);
			$suboptions = $this->ordermodel->get_item_suboption($pg_param['order_seq']);
	
			// 결제금액이 100원 미만 결제 불가
			if ($orders['settleprice'] < 100) {
				$error = true;
				$message = $this->naverpaymentlib->getMessage('minAmountUnder');
			}
	
			foreach ($options as $k => $option) {
				if ($option['goods_seq']) {
					$options[$k]['goods_seq'] = $option['goods_seq'].'_'.$option['item_option_seq'];
				}
	
				if ($option['option1']) {
					$option['goods_name'] = $option['goods_name'].'_'.$option['option1'];
				}
	
				$options[$k]['goods_name'] = getSubstring($option['goods_name'], '256');
			}
	
			foreach ($suboptions as $k => $suboption) {
				if ($suboption['goods_seq']) {
					$suboptions[$k]['goods_seq'] = $suboption['goods_seq'].'_'.$suboption['item_option_seq'];
				}
	
				if ($suboption['suboption']) {
					$suboption['goods_name'] = $suboption['goods_name'].'_'.$suboption['suboption'];
				}
	
				$suboptions[$k]['goods_name'] = getSubstring($suboption['goods_name'], '256');
			}
	
			// Payment 변수 설정
			$params['merchantPayKey'] = $orders['order_seq']; // 가맹점 주문번호
			$params['productName'] = getSubstring($pg_param['product_name'], '256'); // 상품명
			$params['productCount'] = $orders['total_ea']; // 상품 수량
			$params['totalPayAmount'] = $orders['settleprice']; // 상품 총액
			$params['taxScopeAmount'] = floor($pg_param['comm_tax_mny']) + floor($pg_param['comm_vat_mny']); // 과세 대상 금액
			$params['taxExScopeAmount']	= floor($orders['freeprice']); // 면세 대상 금액
	
			if ($this->_is_mobile_agent) {
				$openType = 'page';
			} else {
				$openType = 'popup';
			}
	
			// 2-1. 세션 생성
			$pg_map = ['order_seq'=>$orders['order_seq'], 'mode'=>$pg_param['mode']];
			$this->session->set_userdata(['naverpay_pg'=>$pg_map]);
	
			$params['returnUrl'] = get_connet_protocol().$_SERVER['HTTP_HOST']."/naverpayment/auth";
	
			$this->naverpaymentCfg = config_load('naverpayment');
	
			// 연동키 설정
			if ($this->naverpaymentCfg['use'] == 'y') {
				$params['mode'] = 'production';
			} elseif ($this->naverpaymentCfg['use'] == 'test') {
				$params['mode'] = 'development';
			}
	
			$params['clientId'] = $this->naverpaymentCfg['client_id'];
		} else {
			$this->session->unset_userdata('naverpay_pg');
			$returnUrl = '/order/settle?mode='.$this->pg_param['mode'];
			pageLocation($returnUrl, $message);
			exit;
		}

		if ($error) {
			pageReload($message, 'parent'); 
			exit;
		}

		$this->template->assign('options',$options);
		$this->template->assign('suboptions',$suboptions);
		$this->template->assign('openType',$openType);
		$this->template->assign('params',$params);
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir = BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_naverpayment.html'));
		$this->template->print_('tpl');
	}

	// 결제 인증 및 결제 요청 - STEP2
	public function auth(){
		session_start();

		$error = false;
		$aGetParams = $this->input->get();

		$platForm = 'P';
		$pageUrl = $this->uri->segment(2);
		$returnUrl = '';

		if ($this->_is_mobile_agent) {
			$platForm = 'M';
		}

		try {
			/*
			request 에서 세션에 저장했던 파라미터 값이 유효한지 체크
			세션 유지 시간(로그인 유지시간)을 적당히 유지 하거나 세션을 사용하지 않는 경우 DB처리 하시기 바랍니다.
			*/
			// 1-1. 전달값 체크
			$param = $this->session->naverpay_pg;
			// PC 팝업창 오픈 방식일 때 referer 검증
			$referer = $this->input->server('HTTP_REFERER');
			if ($platForm == 'P' && !strstr($referer,'/naverpayment/request')) {
				$returnUrl = '/main/index';
				throw new Exception('Wrong referer : [referer] ' . $referer);
			}

			// Mobile 현재창 이동 방식일 때 GET Param 검증
			if ($platForm == 'M' && $aGetParams['resultCode'] != 'Success' && $this->naverpaymentlib->getMessage($aGetParams['resultCode'])) {
				$returnUrl = '/order/settle?mode='.$param['mode'];
				$msg = $this->naverpaymentlib->getMessage($aGetParams['resultCode']);
				throw new Exception('Wrong request : [request] ' . $aGetParams['resultCode'] . $aGetParams['resultMessage']);
			}

			if ( ! $param['order_seq'] || ! $aGetParams['paymentId']) {
				throw new Exception('Require param : [order_seq]' . $param['order_seq'] . '[paymentId]' . $aGetParams['paymentId']);
			}

			$this->added_payment->write_log($param['order_seq'], $platForm, 'naverpayment', $pageUrl, 'process0100', $aGetParams); // 파일 로그 저장

			// 결제 시작 마킹
			if ($param['order_seq']) {
				$reDepositSeq = $this->reDepositmodel->insert(
					array(
						'order_seq' => $param['order_seq'],
						'pg' => 'naverpayment',
						'params' => json_encode($param),
						'regist_date' => date('Y-m-d H:i:s')
					)
				);
			}

			$orders	= $this->ordermodel->get_order($param['order_seq']);

			// 주문 체크
			if ( ! $orders['order_seq']) {
				throw new Exception('Require order : [order_seq]' . $orders['order_seq']);
			}

			// 주문 상태 검증
			if (preg_match('/virtual/', $orders['payment'])){
				if ($orders['step'] >= '15' && $orders['step'] <= '75') {
					throw new Exception('Wrong order status : [step]' . $orders['step'] . '[payment]' . $orders['payment']);
				}
			} else {
				if ($orders['step'] >= '25' && $orders['step'] <= '75') {
					throw new Exception('Wrong order status : [step]' . $orders['step'] . '[payment]' . $orders['payment']);
				}
			}

			// 1-2. 전달 정보 설정
			$body_data['paymentId'] = $aGetParams['paymentId'];

			$read_data = $this->naverpaymentlib->callApi('approve', $body_data, 60);
			$response = $read_data['body']['detail'];

			// 2-1. 리턴값 수신
			// 응답 체크
			if ($read_data['error'] == true) {
				throw new Exception('Error Code : [code]' . $read_data['httpCode'] . ' , message : ' . json_decode($read_data['result'])->message);
			}

			// 네이버페이 결과코드와 결제 승인 최종결과 값 체크
			if ($read_data['code'] === 'Success' && $response['admissionState'] === 'SUCCESS') {
				$resultCode = $response['admissionState']; // 결제 시도에 대한 최종결과
				$tid = $response['paymentId']; // 네이버페이 결제 이력 번호
				$amt = $response['totalPayAmount']; // 결제 승인 요청 금액
				
				if ($response['npointPayAmount'] > 0) { // 네이버페이 포인트 결제 금액
					$response['primaryPayMeans'] = 'point';
				}

				if ($response['primaryPayMeans'] == 'CARD') { // 카드 간편결제
					$response['primaryPayMeans'] = 'card';

					$cardCode = $response['cardCorpCode']; // 결제 수단 카드사
					$cardName = $this->naverpaymentlib->getCardName($response['cardCorpCode']); // 결제카드사명
					$enc = mb_detect_encoding($cardName);
					if($enc != 'UTF-8'){
						$cardName = iconv($enc, "UTF-8", $cardName);
					}
					$approved_id = $response['cardAuthNo']; // 카드사 승인번호
					$approved_at = $response['tradeConfirmYmdt']; // 결제승인시각
					$cardQuota = $response['cardInstCount']; // 할부개월수
				}

				if ($response['primaryPayMeans'] == 'BANK') { // 계좌 간편결제
					$response['primaryPayMeans'] = 'account';

					$bankCode = $response['bankCorpCode']; // 결제 수단 은행
					$bankName = $this->naverpaymentlib->getBankName($response['bankCorpCode']); // 결제은행명
					$enc = mb_detect_encoding($bankName);
					if($enc != 'UTF-8'){
						$bankName = iconv($enc, "UTF-8", $cardName);
					}
					$account = $response['bankAccountNo']; // 일부 마스킹 된 계좌 번호
				}

				if ($response['primaryPayMeans']) {
					$set_params['payment'] = $response['primaryPayMeans'];
					$set_params['pg'] = 'naverpayment';
					$set_params['pg_transaction_number'] = $tid;
					$where_params['order_seq'] = $orders['order_seq'];

					$this->ordermodel->set_order($set_params, $where_params);
				}
			} else {
				/* = ----------------------------------------------------------------- = */
				/* =   결제 실패 DB 업데이트 로직											   = */
				/* = ----------------------------------------------------------------- = */
				$resultCode	 = $read_data['error_code'] ?: $read_data['code']; // 결과 코드
				$returnUrl = '/order/settle?mode='.$param['mode'];

				// 결제요청 실패
				if($read_data['message']){
					$failMsg = $read_data['message'];
				}

				$this->ordermodel->set_step($orders['order_seq'], '99');
				$log_title =  '결제실패[' . $resultCode . ']';
				$log = "네이버페이 결제 실패" . chr(10) . "[" . $resultCode ." - " . $failMsg . "]";
				$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $log_title, $log);
				$naverpay_cancel_flag = 'Y';
			}

			// 최종 결제승인처리 (퍼스트몰 로직) - STEP3
			if ($naverpay_cancel_flag != 'Y') {
				if (!$orders['order_seq']) {
					throw new Exception('Wrong order status : [order_seq]' . $orders['order_seq']);
				}

				// $tid 와 $authCode 선언
				$tid			= $response['paymentId']; // 네이버페이 거래고유번호
				$authCode		= $response['cardAuthNo']; // 카드사 승인번호
		
				// 주문 상품 재고 체크
				$runout = false;
				$result = $this->ordermodel->get_item_option($orders['order_seq']);
				$data_item_option = $result;
				$result_option = $result;
				$result = $this->ordermodel->get_item_suboption($orders['order_seq']);
				$result_suboption = $result;
				$data_shipping	= $this->ordermodel->get_order_shipping($orders['order_seq']);
		
				// 회원 마일리지 차감
				if ($orders['emoney']>0 && $orders['member_seq'] && $orders['emoney_use']=='none')
				{
					$params = array(
						'gb'		=> 'minus',
						'type'		=> 'order',
						'emoney'	=> $orders['emoney'],
						'ordno'		=> $orders['order_seq'],
						'memo'		=> "[차감]주문 ({$orders['order_seq']})에 의한 마일리지 차감",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp260",$orders['order_seq']), // [차감]주문 (%s)에 의한 마일리지 차감
					);
					$this->membermodel->emoney_insert($params, $orders['member_seq']);
					$this->ordermodel->set_emoney_use($orders['order_seq'],'use');
				}
		
				// 회원 예치금 차감
				if ($orders['cash']>0 && $orders['member_seq'] && $orders['cash_use']=='none')
				{
					$params = array(
						'gb'		=> 'minus',
						'type'		=> 'order',
						'cash'		=> $orders['cash'],
						'ordno'		=> $orders['order_seq'],
						'memo'		=> "[차감]주문 ({$orders['order_seq']})에 의한 예치금 차감",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp261",$orders['order_seq']), // [차감]주문 (%s)에 의한 예치금 차감
					);
					$this->membermodel->cash_insert($params, $orders['member_seq']);
					$this->ordermodel->set_cash_use($orders['order_seq'],'use');
				}
		
				//상품쿠폰사용
				if($data_item_option) foreach($data_item_option as $item_option){
					if($item_option['download_seq']){
						$this->couponmodel->set_download_use_status($item_option['download_seq'],'used');
					}
				}
				//배송비쿠폰사용 @2015-06-22 pjm
				if($data_shipping) foreach($data_shipping as $shipping){
					if($shipping['shipping_coupon_down_seq']) $this->couponmodel->set_download_use_status($shipping['shipping_coupon_down_seq'],'used');
				}
				//배송비쿠폰사용(사용안함)
				if($orders['download_seq']){
					$this->couponmodel->set_download_use_status($orders['download_seq'],'used');
				}
		
				//주문서쿠폰 사용 처리 by hed
				if($orders['ordersheet_seq']) $this->couponmodel->set_download_use_status($orders['ordersheet_seq'],'used');
		
				//프로모션코드 상품/배송비 할인 사용처리
				$this->promotionmodel->setPromotionpayment($orders);
		
				// 장바구니 비우기
				if( $orders['mode'] ){
					$this->cartmodel->delete_mode($orders['mode']);
				}
		
				// 주문 상태 업데이트
				$data = array(
					'pg_transaction_number' => $tid,		// 네이버페이 거래고유번호
					'pg_approval_number'	=> $authCode	// 승인번호
				);
				$this->coupon_reciver_sms = array();
				$this->coupon_order_sms = array();
				$order_count = 0;
		
				$this->ordermodel->set_step($orders['order_seq'],25,$data);
		
				// DB 로그 기록
				$add_log = "";
				if($orders['orign_order_seq'])	$add_log = "[재주문]";
				if($orders['admin_order'])		$add_log = "[관리자주문]";
				if($orders['person_seq'])		$add_log = "[개인결제]";
		
				$log_title =  $add_log."결제확인"."(네이버페이)";
		
				$log = "네이버페이 결제 확인". chr(10)."[" . $response['admissionState'] . ":" . $tid ."]" . chr(10). implode(chr(10),$data);
				$this->ordermodel->set_log($orders['order_seq'],'pay',$orders['order_user_name'],$log_title,$log);
		
				// 출고량 업데이트를 위한 변수선언
				$r_reservation_goods_seq = array();
		
				// 해당 주문 상품의 출고예약량 업데이트
				if($result_option){
					foreach($result_option as $data_option){
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
				// 출고예약량 업데이트
				foreach($r_reservation_goods_seq as $goods_seq){
					$this->goodsmodel->modify_reservation_real($goods_seq);
				}
		
				//티켓상품 자동 출고처리구문 순차진행을 위해 분리함 @2017-08-16
				ticket_payexport_ck($orders['order_seq']);
		
				//받는 사람 티켓상품 SMS 데이터
				if(count($this->coupon_reciver_sms['order_cellphone']) > 0){
					$order_count = 0;
					foreach($this->coupon_reciver_sms['order_cellphone'] as $key=>$value){
						$coupon_arr_params[$order_count]		= $this->coupon_reciver_sms['params'][$key];
						$coupon_order_no[$order_count]			= $this->coupon_reciver_sms['order_no'][$key];
						$coupon_order_cellphones[$order_count]	= $this->coupon_reciver_sms['order_cellphone'][$key];
						$order_count					=$order_count+1;
					}
		
					$commonSmsData['coupon_released']['phone']		= $coupon_order_cellphones;
					$commonSmsData['coupon_released']['params']		= $coupon_arr_params;
					$commonSmsData['coupon_released']['order_no']	= $coupon_order_no;
		
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
		
					$commonSmsData['coupon_released2']['phone']		= $reciver_order_cellphones;;
					$commonSmsData['coupon_released2']['params']	= $reciver_arr_params;
					$commonSmsData['coupon_released2']['order_no']	= $reciver_order_no;
		
				}
		
				if(count($commonSmsData) > 0){
					commonSendSMS($commonSmsData);
				}
			} else {
				$tid = $body_data['paymentId'];
				$params = '{"paymentId": "'.$tid.'"}';
				$result = $this->naverpaymentlib->callApi('list', $params);

				// 실패응답인데 결제내역이 있으면...
				if ($result['error'] != true && $result['code'] == 'Success') {
					// 결제취소 처리
					$orders['pg_log']['tno'] = $tid;
					$data_refund['cancel_type'] = 'full';
					$data_refund['refund_reason'] = '관리자취소(결제실패)';
					$this->refundmodel->naverpayment_cancel($orders, $data_refund);
				}
			}

			$this->added_payment->write_log($orders['order_seq'], $platForm, 'naverpayment', $pageUrl, 'process0200', $response); // 파일 로그 저장
		} catch (Exception $e) {
			$this->added_payment->write_log($orders['order_seq'], $platForm, 'naverpayment', $pageUrl, 'process0300', array('errorMsg' => $e->getMessage())); // 파일 로그 저장
			$error = true;
		}

		// 결제 종료 마킹
		if ($reDepositSeq) {
			$this->reDepositmodel->del(array('re_deposit_seq' => $reDepositSeq));
		}

		if ($error) {
			$msg = $msg ?? getAlert('os217');
			if ($returnUrl) {
				pageLocation($returnUrl, $msg);
			} else {
				pageLocation('/', $msg);
			}
			exit;
		}

		// 로그 저장
		$this->added_payment->set_pg_log(
			array(
				'pg' => 'naverpayment',
				'order_seq' => $orders['order_seq'],
				'tno' => $tid,
				'amount' => $amt,
				'app_time' => date('YmdHis', strtotime($approved_at)),
				'app_no' => $approved_id,
				'card_cd' => $cardCode,
				'card_name' => $cardName,
				'quota' => $cardQuota,
				'bank_name' => $bankCode,
				'bank_code' => $bankName,
				'account'	=> $account,
				'escw_yn' => 'N',
				'biller' => 'naverpayment',
				'payment_cd' => $response['primaryPayMeans'],
				'res_cd' => $resultCode,
				'res_msg' => $failMsg
			)
		);

		if ($naverpay_cancel_flag == 'Y') { // 네이버페이 결제 실패
			if ($platForm == 'P') {
				echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
				openDialogAlert(getAlert('os217') . "<br /><font color=red>[{$resultCode}] - {$failMsg})</font>", 400, 160, 'parent', $this->pg_cancel_script());
			} else{
				pageLocation($returnUrl, "[{$resultCode}] - {$failMsg}");
			}
		} else {
			pageRedirect('../order/complete?no=' . $orders['order_seq'], '', 'parent');
		}
	}

	// 결제 취소 시 리턴
	public function cancel(){
		// 결제를 취소하셨습니다.
		echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
		echo '<script type="text/javascript">alert("'.getAlert('os217').'");'.$this->pg_cancel_script().'</script>';
	}

	// 결제 실패 시 리턴
	public function payfail(){
		$aGetParams = $this->input->get();
		if($aGetParams['resultCode']) {
			$message = $this->naverpaymentlib->getMessage($aGetParams['resultCode']);
		}

		// 결제 실패
		echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
		echo '<script type="text/javascript">alert("'.$message.'");'.$this->pg_cancel_script().'</script>';
	}

	public function pg_cancel_script(){
		$js_echo = '$("#wrap",parent.document).show();$("div.pay_layer",parent.document).eq(0).show();$("div.pay_layer",parent.document).eq(1).hide();$("#layer_pay",parent.document).hide();$("#kakaopay_layer",parent.document).css("display","none");$("#lay_mask",parent.document).remove();';

		return $js_echo;
	}
}