<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class kspaylib extends CI_Model {
	public function __construct() {
		parent::__construct();
		$this->load->library('added_payment');
		$this->load->model('reDepositmodel');
		$this->kspayMobileMode = false;
	}

	public function wh_result() {
		$aKspayVal = array(
			'authyn',
			'trno',
			'trddt',
			'trdtm',
			'amt',
			'authno',
			'msg1',
			'msg2',
			'ordno',
			'isscd',
			'aqucd',
			'result',
			'halbu',
			'cbtrno',
			'cbauthno'
		);
		$aKspayBank = array(
			'04' => '국민은행',
			'11' => '농협중앙회',
			'26' => '신한은행',
			'20' => '우리은행',
			'23' => 'SC제일은행',
			'03' => '기업은행',
			'71' => '우체국',
			'27' => '시티은행',
			'32' => '부산은행',
			'39' => '경남은행',
			'37' => '전북은행',
			'34' => '광주은행',
			'31' => '대구은행',
			'05' => '외환은행',
			'E0' => '삼성증권'
		);
		$aKspayCard = array(
			'01' => '비씨카드',
			'02' => '국민카드',
			'03' => '외환카드',
			'04' => '삼성카드',
			'05' => '신한카드',
			'08' => '현대카드',
			'09' => '롯데카드',
			'11' => '한미은행',
			'12' => '수협',
			'14' => '우리은행',
			'15' => '농협',
			'16' => '제주은행',
			'17' => '광주은행',
			'18' => '전북은행',
			'19' => '조흥은행',
			'23' => '주택은행',
			'24' => '하나은행',
			'26' => '씨티은행',
			'25' => '해외카드사',
			'99' => '기타'
		);

		$aParams = $this->input->post();

		$platForm = 'P';
		$reCid = $aParams['reWHCid'];
		$pageUrl = $this->uri->segment(2);

		if ($this->kspayMobileMode) {
			$platForm = 'M';
			$reCid = $aParams['reCommConId'];
		}

		try {
			$ipg = new KSPayWebHost($reCid, null);

			if ($this->kspayMobileMode) {
				$ipg->kspay_set_mobile();
			}

			if ($ipg->kspay_send_msg('1')) {
				foreach ($aKspayVal as $sKspayVal) {
					$aResult[$sKspayVal] = $ipg->kspay_get_value($sKspayVal);
				}
				$aResult['msg1'] = convert_to_utf8($aResult['msg1']);
				$aResult['msg2'] = convert_to_utf8($aResult['msg2']);
				if ( ! empty($aResult['authyn']) && 1 == strlen($aResult['authyn'])) {
					$ipg->kspay_send_msg('3');
				}
			}

			$this->added_payment->write_log($aResult['ordno'], $platForm, 'kspay', $pageUrl, 'process0100', $aResult);

			## 주문서 정보 가져오기
			$orders = $this->ordermodel->get_order($aResult['ordno']);
			$result_option = $this->ordermodel->get_item_option($orders['order_seq']);
			$result_suboption = $this->ordermodel->get_item_suboption($orders['order_seq']);
			$data_shipping = $this->ordermodel->get_order_shipping($orders['order_seq']);

			if ( ! $orders['order_user_name']) {
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

			// 필수 값 체크
			if ( ! $orders['order_seq']) {
				throw new Exception('Require order : [order_seq]' . $orders['order_seq']);
			}
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
			if ($orders['order_seq']) {
				$reDepositSeq = $this->reDepositmodel->insert(
					array(
						'order_seq' => $orders['order_seq'],
						'pg' => $this->config_system['pgCompany'],
						'params' => json_encode($aParams),
						'regist_date' => date('Y-m-d H:i:s')
					)
				);
			}

			##가격 검증
			if ($orders['pg_currency'] == 'KRW') {
				$orders['settleprice'] = floor($orders['settleprice']);
			}
			if ($orders['settleprice'] != $aResult['amt']) {
				$this->ordermodel->set_log($orders['order_seq'], 'pay', '시스템', '결제실패', "KSPAY 결제 실패". chr(10)."[금액불일치]");
				throw new Exception('Payment Fail : [settleprice]' . $orders['settleprice'] . '[amt]' . $aResult['amt']);
			}

			if ( ! empty($aResult['authyn']) && 1 == strlen($aResult['authyn']) && $aResult['authyn'] == 'O'){
				/* = -------------------------------------------------------------------------- = */
				/* =   06-1. 승인 결과 DB 처리(res_cd == '0000')								= */
				/* = -------------------------------------------------------------------------- = */
				/* =		각 결제수단을 구분하시어 DB 처리를 하시기 바랍니다.				 = */
				/* = -------------------------------------------------------------------------- = */

				// 회원 마일리지 차감
				if ($orders['emoney'] > 0 && $orders['member_seq'] && $orders['emoney_use'] == 'none') {
					$params	= array(
						'gb' => 'minus',
						'type' => 'order',
						'emoney' => $orders['emoney'],
						'ordno' => $orders['order_seq'],
						'memo' => "[차감]주문 ({$orders['order_seq']})에 의한 마일리지 차감",
						'memo_lang' => $this->membermodel->make_json_for_getAlert("mp260", $orders['order_seq']) // [차감]주문 (%s)에 의한 마일리지 차감
					);
					$this->membermodel->emoney_insert($params, $orders['member_seq']);
					$this->ordermodel->set_emoney_use($orders['order_seq'], 'use');
				}
				// 회원 예치금 차감
				if ($orders['cash'] > 0 && $orders['member_seq'] && $orders['cash_use'] == 'none') {
					$params = array(
						'gb' => 'minus',
						'type' => 'order',
						'cash' => $orders['cash'],
						'ordno' => $orders['order_seq'],
						'memo' => "[차감]주문 ({$orders['order_seq']})에 의한 예치금 차감",
						'memo_lang' => $this->membermodel->make_json_for_getAlert("mp261", $orders['order_seq']) // [차감]주문 (%s)에 의한 예치금 차감
					);
					$this->membermodel->cash_insert($params, $orders['member_seq']);
					$this->ordermodel->set_cash_use($orders['order_seq'], 'use');
				}
				//상품쿠폰사용
				if ($result_option) {
					foreach ($result_option as $item_option){
						if ($item_option['download_seq']) {
							$this->couponmodel->set_download_use_status($item_option['download_seq'], 'used');
						}
					}
				}
				//배송비쿠폰사용 @2015-06-22 pjm
				if ($data_shipping) {
					foreach ($data_shipping as $shipping) {
						if ($shipping['shipping_coupon_down_seq']) {
							$this->couponmodel->set_download_use_status($shipping['shipping_coupon_down_seq'], 'used');
						}
					}
				}

				//배송비쿠폰사용(사용안함)
				if ($orders['download_seq']) {
					$this->couponmodel->set_download_use_status($orders['download_seq'], 'used');
				}

				//주문서쿠폰 사용 처리 by hed
				if ($orders['ordersheet_seq']) {
					$this->couponmodel->set_download_use_status($orders['ordersheet_seq'], 'used');
				}

				//프로모션코드 상품/배송비 할인 사용처리 @2017-01-23
				$this->promotionmodel->setPromotionpayment($orders);

				// 장바구니 비우기
				if ($orders['mode']) {
					$this->cartmodel->delete_mode($orders['mode']);
				}

				$aOrderStep['pg_transaction_number'] = $aResult['trno'];
				if ($aResult['cbauthno']) {
					$aOrderStep['typereceipt'] = 2;
					$aOrderStep['cash_receipts_no'] = $aResult['cbauthno'];
				}
				if (preg_match('/virtual|account/', $orders['payment'])) {
					$bankName = $aKspayBank[$aResult['authno']];
					$bankCode = $aResult['authno'];
					$aOrderStep['virtual_account'] = $bankName;
					if (preg_match('/virtual/', $orders['payment'])) {
						$aOrderStep['virtual_account'] .= " " . $aResult['isscd'];
					}
				} else {
					$aOrderStep['pg_approval_number'] = $aResult['authno'];
				}

				// 06-1-3. 가상계좌
				if (preg_match('/virtual/', $orders['payment'])) {
					$this->ordermodel->set_step($orders['order_seq'], '15', $aOrderStep);
					$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $add_log . "주문접수(" . $orders['payment'] . ")", "KSNET 가상계좌 주문접수". chr(10). '[' .$aResult['result'] . '][' . $aResult['msg1'] . '][' . $aResult['msg2'] . "]");
				}else{
					$this->ordermodel->set_step($orders['order_seq'], '25', $aOrderStep);
					$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $add_log . "결제확인(" . $orders['mpayment'] . ")", "KSNET 결제확인" . chr(10) . '[' . $aResult['result'] . '][' . $aResult['msg1'] . '][' . $aResult['msg2'] . "]");

					$this->coupon_reciver_sms = array();
					$this->coupon_order_sms = array();
					$order_count = 0;

					// 계좌이체 결제의 경우 현금영수증
					if (preg_match('/account/', $orders['payment'])){
						typereceipt_setting($orders['order_seq']);
					}
					//티켓상품 자동 출고처리구문 순차진행을 위해 분리함 @2017-08-16
					ticket_payexport_ck($orders['order_seq']);
					// 받는 사람 티켓상품 SMS 데이터
					if (count($this->coupon_reciver_sms['order_cellphone']) > 0) {
						$order_count = 0;
						foreach ($this->coupon_reciver_sms['order_cellphone'] as $key => $value) {
							$coupon_arr_params[$order_count] = $this->coupon_reciver_sms['params'][$key];
							$coupon_order_no[$order_count] = $this->coupon_reciver_sms['order_no'][$key];
							$coupon_order_cellphones[$order_count] = $this->coupon_reciver_sms['order_cellphone'][$key];
							$order_count++;
						}
						$commonSmsData['coupon_released']['phone'] = $coupon_order_cellphones;;
						$commonSmsData['coupon_released']['params'] = $coupon_arr_params;
						$commonSmsData['coupon_released']['order_no'] = $coupon_order_no;
					}
					//주문자 티켓상품 SMS 데이터
					if (count($this->coupon_order_sms['order_cellphone']) > 0) {
						$order_count = 0;
						foreach($this->coupon_order_sms['order_cellphone'] as $key=>$value){
							$reciver_arr_params[$order_count] = $this->coupon_order_sms['params'][$key];
							$reciver_order_no[$order_count] = $this->coupon_order_sms['order_no'][$key];
							$reciver_order_cellphones[$order_count] = $this->coupon_order_sms['order_cellphone'][$key];
							$order_count++;
						}
						$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;;
						$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
						$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;
					}
					if (count($commonSmsData) > 0) {
						commonSendSMS($commonSmsData);
					}
				}
				$this->added_payment->write_log($orders['order_seq'], $platForm, 'kspay', $pageUrl, 'process0200', $aResult); // 파일 로그 저장
			} else {
				if ($aResult['result'] != "P10H"){
					$this->ordermodel->set_step($orders['order_seq'], '99');
					$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], '결제실패[' . $aResult['result'] . ']', 'KSNET 결제 실패'. chr(10) . '[' . $aResult['result'] . '][' . $aResult['msg1'] . '][' . $aResult['msg2'] . ']');
					throw new Exception('Payment Fail : [result]' . $aResult['result'] . '[msg1]' . $aResult['msg1'] . '[msg2]' . $aResult['msg2']);
				}
			}
		} catch (Exception $e) {
			$this->added_payment->write_log($orders['order_seq'], $platForm, 'kspay', $pageUrl, 'process0300', array('errorMsg' => $e->getMessage())); // 파일 로그 저장
			$error = true;
		}

		// 결제 종료 마킹
		if ($reDepositSeq) {
			$this->reDepositmodel->del(array('re_deposit_seq' => $reDepositSeq));
		}

		if (preg_match('/card/', $orders['payment'])) {
			$cardName = $aKspayCard[$aResult['isscd']];
			$cardCode = $aResult['isscd'];
		}

		## 로그 저장
		$this->added_payment->set_pg_log(
			array(
				'pg' => 'kspay',
				'order_seq' => $orders['order_seq'],
				'tno' => $aResult['trno'],
				'amount' => $aResult['amt'],
				'card_cd' => $cardCode,
				'card_name' => $cardName,
				'bank_code' => $bankCode,
				'bank_name' => $bankName,
				'res_cd' => $aResult['result'],
				'res_msg' => $aResult['msg1']
			)
		);

		if ($error) {
			pageRedirect('../order/complete?no=' . $orders['order_seq'] . '&res_cd=' . $aResult['result'], '', 'parent');
		} else {
			pageRedirect('../order/complete?no=' . $orders['order_seq'], '', 'parent');
		}
	}

	public function wh_return() {
		header("Content-Type: text/html; charset=EUC-KR");
		$data = preg_replace("/^data=/i","",$_SERVER['QUERY_STRING']);
		$data = urldecode($data);

		$this->send_for_provider = array();

		ob_start();
		ob_implicit_flush(0);
		$ret		= 'false';

		//PG에서 보냈는지 IP로 체크
		$IN_IP	= getenv("REMOTE_ADDR");
		if (in_array($IN_IP, array('210.181.28.116', '210.181.29.130')) || developIpCheck()){
			$ipos	= 0;
			$ret	= "false";

			if (strlen($data) >= 300){

				if	(substr($data,$ipos  ,1) >= "0" && substr($data,$ipos  ,1) <= "9" &&
					substr($data,$ipos+1,1) >= "0" && substr($data,$ipos+1,1) <= "9" &&
					substr($data,$ipos+2,1) >= "0" && substr($data,$ipos+2,1) <= "9" &&
					substr($data,$ipos+3,1) >= "0" && substr($data,$ipos+3,1) <= "9"){

					// 신PG 헤더
					$order_seq		= trim(substr($data, 36, 50)); //  주문번호
					$UserName		= trim(substr($data, 86, 50)); //  주문자명
					$IdNum			= trim(substr($data, 136, 13));//  주민번호
					$GoodName		= trim(substr($data, 200, 50));//  제품명
					$InOut			= trim(substr($data, 300, 2)); //  승인구분

					if($InOut == "50" || $InOut == "61"){
						$deal_sele	= substr($data, 437, 2);
						$total_amt	= substr($data, 445, 13);
						$ret		= "OK";
					}
				}

				$orders		= $this->ordermodel->get_order($order_seq);
				##가격검증
				if	($orders['pg_currency'] == 'KRW')
					$orders['settleprice']	= floor($orders['settleprice']);
				if($orders['settleprice'] != (float)$total_amt){
					$log_title	= '결제실패';
					$log		= "KAPAY 결제 실패". chr(10)."[입금통보, 금액불일치]";
					$this->ordermodel->set_log($orders['order_seq'], 'pay', '시스템', $log_title, $log);

					$ret = false;
					$msg	= 'result='.$ret;
					header("Content-Type: text/html; charset=EUC-KR");
					Header("Content-Length: " . strlen($msg));
					echo($msg);
					ob_end_flush();
					exit;
				}

				// DB 처리
				if	($ret == 'OK' && $order_seq && $deal_sele == '20'){
					// 주문 상품 재고 체크
					if	($orders['step'] == '15'){
						$runout				= false;
						$cfg['order']		= config_load('order');
						$result				= $this->ordermodel->get_item_option($order_seq);
						$result_option		= $result;
						$data_item_option	= $result;
						$result				= $this->ordermodel->get_item_suboption($order_seq);
						$result_suboption	= $result;

						if	($log_use == 'Y'){
							if($runout)	fwrite($fobj, 'runout : true' . "\r\n");
							else		fwrite($fobj, 'runout : false' . "\r\n");
						}

						if($runout) exit;
						$data	= array('pg_transaction_number' => $rVTransactionNo);
						$this->coupon_reciver_sms	= array();
						$this->coupon_order_sms		= array();
						$order_count				= 0;

						$this->ordermodel->set_step($order_seq,25,$data);

						$log	= "KSNET 결제 확인". chr(10)."[" .$stan_resp_code . $mess_code . "]" . chr(10). implode(chr(10),$data);
						$add_log = "";
						if($orders['orign_order_seq']) $add_log = "[재주문]";
						if($orders['admin_order']) $add_log = "[관리자".$this->managerInfo['manager_id']."주문]";
						if($orders['person_seq']) $add_log = "[개인결제]";
						$log_title =  $add_log."결제확인"."(".$orders['mpayment'].")";
						$this->ordermodel->set_log($order_seq,'pay',$orders['order_user_name'],$log_title,$log);

						// 가상계좌 결제의 경우 현금영수증
						if( $orders['step'] < '25' || $orders['step'] > '85' ){
							typereceipt_setting($orders['order_seq']);
						}

						$mail_step		= 25;

						// 상품/패키지상품 출고수량 업데이트
						$_release_return	= $this->ordermodel->_release_reservation($order_seq);
						$providerList		= $_release_return['providerList'];

						// 결제확인메일/sms 발송
						send_mail_step25($orders['order_seq']);
						if($orders['sms_25_YN'] != 'Y'){
							$params['shopName']		= $this->config_basic['shopName'];
							$params['ordno']		= $orders['order_seq'];
							$params['user_name']	= $orders['order_user_name'];
							if( $orders['order_cellphone'] ){
								$commonSmsData['settle']['phone'][]		= $orders['order_cellphone'];
								$commonSmsData['settle']['params'][]	= $params;
								$commonSmsData['settle']['order_seq'][] = $orders['order_seq'];
							}

							sendSMS_for_provider('settle', $providerList, $params);
							//입점관리자 SMS 데이터
							if(count($this->send_for_provider['order_cellphone']) > 0){
								$provider_count = 0;
								foreach($this->send_for_provider['order_cellphone'] as $key=>$value){
									$provider_msg[$provider_count]				= $this->send_for_provider['msg'][$key];
									$provider_order_cellphones[$provider_count] = $this->send_for_provider['order_cellphone'][$key];
									$provider_count								= $provider_count+1;
								}
								$commonSmsData['provider']['phone']				= $provider_order_cellphones;
								$commonSmsData['provider']['msg']				= $provider_msg;
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
					}

					// [판매지수 EP] 쿠키로 ep 등록 처리된 주문건인지 확인 후 EP 수집 :: 2018-09-18 pjw
					$this->insert_ep_sales($orders['order_seq']);

					if(count($commonSmsData) > 0){
						commonSendSMS($commonSmsData);
					}

				}
			}
		}

		// kspay 로 결과 전달 ( 단, 현재 charset 문제로 KSNET에서 결과를 못 받는 문제가 있음 )
		$msg	= 'result='.$ret;

		header("Content-Type: text/html; charset=EUC-KR");
		Header("Content-Length: " . strlen($msg));
		echo($msg);
		ob_end_flush();
	}
}