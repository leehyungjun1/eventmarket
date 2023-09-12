<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class order_process extends admin_base {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('ordermodel');
		$this->load->model('refundmodel');
		$this->load->model('exportmodel');
		$this->load->model('authmodel');
		$this->arr_step 	= config_load('step');
		$this->arr_payment 	= config_load('payment');
		$this->load->helper('order');
		$this->load->model('goodsmodel');
		$this->load->helper('shipping');
	}
	// 배송정보 변경
	public function shipping()
	{
		$aGetParams		= $this->input->get();
		$aPostParams 	= $this->input->post();
		$order_seq 		= $aGetParams['seq'];
		$international 	= $aGetParams['international'];
		$orders			= $this->ordermodel->get_order($order_seq);

		//개인정보 마스킹 표시 권한 체크
		$private_masking = $this->authmodel->manager_limit_act('private_masking');

		$oldData = array();
		if( !$private_masking ) {
			$oldData['recipient_user_name']			= $orders['recipient_user_name'];
			$oldData['recipient_phone']				= $orders['recipient_phone'];
			$oldData['recipient_cellphone']			= $orders['recipient_cellphone'];
			$oldData['recipient_zipcode']			= $orders['recipient_zipcode'];
			$oldData['recipient_address_type']		= $orders['recipient_address_type'];
			$oldData['recipient_address_street']	= $orders['recipient_address_street'];
			$oldData['recipient_address']			= $orders['recipient_address'];
			$oldData['recipient_address_detail']	= $orders['recipient_address_detail'];

			if($oldData['recipient_phone'] == ''){
				$oldData['recipient_phone'] = '--';
			}
				
			if($oldData['recipient_cellphone'] == ''){
				$oldData['recipient_cellphone'] = '--';
			}
		}
		$oldData['memo']	= $orders['memo'];

		# 간편결제 API 주문건 배송정보 변경 불가 처리
		$npay_use		= npay_useck();			//Npay v2.1 사용여부
		$talkbuy_use	= talkbuy_useck();		//카카오페이 구매사용여부
		if(($npay_use && $orders['npay_order_id']) || ($talkbuy_use && $orders['talkbuy_order_id'])) {
			$marketname = order_market_name($orders);
			openDialogAlert("<span class=\'fx12\'>".$marketname." 주문건은 직접 배송지 변경이 불가합니다.<br />".$marketname." 어드민에서 처리할 수 있습니다.</span>",400,180,'parent',"");
			exit;
		}

		if( !in_array($orders['step'],$this->ordermodel->able_step_action['shipping_region']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 배송정보 변경을 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		if( !$private_masking ) {
			$this->validation->set_rules('recipient_user_name','받는이','trim|required|xss_clean');
			// $this->validation->set_rules('recipient_phone[]','전화','trim|numeric|required|xss_clean');
			$this->validation->set_rules('recipient_cellphone[]','휴대폰','trim|numeric|required|xss_clean');

			if($international == 'domestic'){
				$this->validation->set_rules('recipient_zipcode','우편번호','trim|required|xss_clean');
				$this->validation->set_rules('recipient_address','주소','trim|required|xss_clean');
				$this->validation->set_rules('recipient_address_detail','주소','trim|required|xss_clean');
			}

			if($international == 'international'){
				$this->validation->set_rules('region','지역','trim|required|xss_clean');
				$this->validation->set_rules('international_address','주소','trim|required|xss_clean');
				$this->validation->set_rules('international_town_city','시도','trim|required|xss_clean');
				$this->validation->set_rules('international_county','주','trim|required|xss_clean');
				$this->validation->set_rules('international_postcode','우편번호','trim|required|xss_clean');
				$this->validation->set_rules('international_country','국가','trim|required|xss_clean');
			}
		}

		$this->validation->set_rules('memo','요청사항','trim|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if( !$private_masking ) {
			$aPostParams['recipient_phone']		= implode('-',$aPostParams['recipient_phone']);
			$aPostParams['recipient_cellphone']	= implode('-',$aPostParams['recipient_cellphone']);
			$data['recipient_user_name']		= $aPostParams['recipient_user_name'];
			$data['recipient_phone'] 	 		= $aPostParams['recipient_phone'];
			$data['recipient_cellphone'] 		= $aPostParams['recipient_cellphone'];

			if($international == 'domestic'){

				foreach($aPostParams as $k => $row) if($orders[$k]!=$data) $change = 1;
				if($change){
					$data['recipient_zipcode'] 			= $aPostParams['recipient_zipcode'];
					$data['recipient_address_type'] 	= $aPostParams['recipient_address_type'];
					$data['recipient_address'] 			= $aPostParams['recipient_address'];
					$data['recipient_address_street'] 	= $aPostParams['recipient_address_street'];
					$data['recipient_address_detail'] 	= $aPostParams['recipient_address_detail'];
				}
			}

			if($international == 'international'){
				foreach($aPostParams as $k => $row) if($orders[$k]!=$data) $change = 1;
				if($change){
					$data['region'] 					= $aPostParams['region'];
					$data['international_address'] 		= $aPostParams['international_address'];
					$data['international_town_city'] 	= $aPostParams['international_town_city'];
					$data['international_county'] 		= $aPostParams['international_county'];
					$data['international_postcode'] 	= $aPostParams['international_postcode'];
					$data['international_country'] 		= $aPostParams['international_country'];
				}
			}
		}

		$data['memo'] = $aPostParams['memo'];
		foreach($aPostParams as $k => $row) if($orders[$k]!=$data) $change = 1;

		if($change){
			$data['each_msg_yn'] = 'N'; // 배송메세지를 order에서 관리 :: 2017-04-18 lwh
			$this->db->where('order_seq', $order_seq);
			$this->db->update('fm_order', $data);
			$log = "배송지 정보 변경";
            $this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],$log,serialize($data));

			//관리자 로그 남기기
			$is_log = false;
			
			$logData = array();
			$logData['params'] = array('order_seq' => $order_seq);
			foreach($oldData as $k => $v){
				if($v != $data[$k]){
					$logData['params']['target'] .= $k."|";
					$logData['params']['before'] .= $v."|";
					$logData['params']['after'] .= $data[$k]."|";
				}
			}

			// 최초 배송지 등록 시 
			if(!has_recipient_zipcode($orders)) {
				$this->load->library('orderlibrary');
				$this->orderlibrary->first_regist_recipient_zipcode($orders);
			}
			
			if($logData['params']['before']){
				$this->load->library('managerlog');
				$this->managerlog->insertData($logData);
			}
			
			openDialogAlert("배송지 정보가 변경 되었습니다.",400,140,'parent','parent.location.reload();');
		}
	}

	// 배송정보 변경
	public function bank()
	{
		$aGetParams		= $this->input->get();
		$aPostParams	= $this->input->post();
		$order_seq		= $aGetParams['seq'];
		$orders			= $this->ordermodel->get_order($order_seq);

		# 간편결제 API 주문건 배송정보 변경 불가 처리
		$npay_use		= npay_useck();			//Npay v2.1 사용여부
		$talkbuy_use	= talkbuy_useck();		//카카오페이 구매사용여부
		if(($npay_use && $orders['npay_order_id']) || ($talkbuy_use && $orders['talkbuy_order_id'])) {
			$marketname = order_market_name($orders);
			openDialogAlert($marketname." 주문건은 결제정보 변경이 불가합니다",400,160,'parent',"");
			exit;
		}

		if( !in_array($orders['step'],$this->ordermodel->able_step_action['change_bank']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 입금계좌 정보 변경을 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		//개인정보 마스킹 표시 권한 체크
		$private_masking = $this->authmodel->manager_limit_act('private_masking');
		if( !$private_masking ) {
			$this->validation->set_rules('depositor',		'입금자명','trim|required|xss_clean');
		}
		$this->validation->set_rules('bank_account',	'입금계좌','trim|required|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		foreach($aPostParams as $k => $data) if($orders[$k]!=$data) $change = 1;
		if($change){
			$data = array();
			if( !$private_masking ) {
				$data['depositor'] 		= $aPostParams['depositor'];
			}
			$data['bank_account'] 	= $aPostParams['bank_account'];
			$this->db->where('order_seq', $order_seq);
			$this->db->update('fm_order', $data);
			$log = "입금계좌 정보 변경";
			$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],$log,serialize($data));
			openDialogAlert("입금계좌가 변경 되었습니다.",400,140,'parent','');
		}
	}

	// 관리자 메모
	public function admin_memo()
	{
		$iOrder_seq = $this->input->post('seq'); //#16651 2018-07-10 ycg POST방식 및 변수명 변경
		$this->validation->set_rules('admin_memo','관리자메모','trim|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		/* #16651 2018-07-10 ycg 관리자 메모 기능 개선 */
			$data['memo_idx']		= $this->input->post('memo_idx');
			$data['order_seq']		= $iOrder_seq;
			$data['regist_date']		= date("Y-m-d H:i:s");
			$data['mname']	= $this->input->post('mname');
			$data['manager_id']		= $this->input->post('manager_id');
			$data['admin_memo']		= $this->input->post('admin_memo');
			$data['ip']				= $_SERVER['REMOTE_ADDR'];
			//메모 등록시 사용 변수
			$aOrder_memo = array(
				 'order_seq'=>$data['order_seq'],
				 'regist_date'=>$data['regist_date'],
				 'mname'=>$data['mname'],
				 'manager_id'=>$data['manager_id'],
				 'admin_memo'=>$data['admin_memo'],
				 'ip'=>$data['ip']
				);
			//등록시 실행 쿼리
			if(empty($data['memo_idx'])!=false){
				$this->db->where('order_seq', $iOrder_seq);
				$this->db->insert('fm_order_memo', $aOrder_memo);
			}else{
			//수정시 실행 쿼리
				$this->db->where('order_seq', $iOrder_seq);
				$this->db->where('memo_idx', $data['memo_idx']);
				$this->db->update('fm_order_memo',$data);
			}
		/* #16651 2018-07-10 ycg 관리자 메모 기능 개선 */
	}

	// 주문 무효
	public function cancel_order(){

		$order_seq	= $_GET['seq'];
		$orders		= $this->ordermodel->get_order($order_seq);

		# 간편결제 API 주문건 주문 무효 불가 처리
		$npay_use		= npay_useck();			//Npay v2.1 사용여부
		$talkbuy_use	= talkbuy_useck();		//카카오페이 구매사용여부
		if(($npay_use && $orders['npay_order_id']) || ($talkbuy_use && $orders['talkbuy_order_id'])) {
			$marketname = order_market_name($orders);
			openDialogAlert($marketname." 주문 건은 직접 주문무효처리 할 수 없습니다.",400,140,'parent',"");
			exit;
		}

		if( !in_array($orders['step'],$this->ordermodel->able_step_action['cancel_order']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 주문무효를 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		$this->ordermodel->set_step($order_seq,95);
		$options	= $this->ordermodel->get_item_option($order_seq);
		$suboptions	= $this->ordermodel->get_item_suboption($order_seq);
		if($options) foreach($options as $k => $option){
			$tot_ea		+= $option['ea'];
		}
		if($suboptions) foreach($suboptions as $k => $option){
			$tot_ea		+= $option['ea'];
		}

		if($orders['member_seq']){
			$this->load->model('membermodel');
			/* 마일리지 환원 */
			if($orders['emoney_use']=='use' && $orders['emoney'])
			{
				$params = array(
					'gb'		=> 'plus',
					'type'		=> 'cancel',
					'emoney'	=> $orders['emoney'],
					'ordno'		=> $order_seq,
					'memo'		=> "[복원]".$this->arr_step[95]."(".$order_seq.")에 의한 마일리지 환원",
					'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp252",$order_seq),  // $this->arr_step[95] 는 "주문무효"이며 변동되지 않음, [복원]주문무효(%s)에 의한 마일리지 환원
				);
				$this->membermodel->emoney_insert($params, $orders['member_seq']);
				$this->ordermodel->set_emoney_use($order_seq,'return');
			}

			/* 예치금 환원 */
			if($orders['cash_use']=='use' && $orders['cash'])
			{
				$params = array(
					'gb'		=> 'plus',
					'type'		=> 'cancel',
					'cash'		=> $orders['cash'],
					'ordno'		=> $order_seq,
					'memo'		=> "[복원]".$this->arr_step[95]."(".$order_seq.")에 의한 예치금 환원",
					'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp253",$order_seq),  // $this->arr_step[95] 는 "주문무효"이며 변동되지 않음, [복원]주문무효(%s)에 의한 예치금 환원
				);
				$this->membermodel->cash_insert($params, $orders['member_seq']);
				$this->ordermodel->set_cash_use($order_seq,'return');
			}
		}

		/* 프로모션환원 */
		$this->load->model('couponmodel');
		$this->load->model('promotionmodel');

		/* 해당 주문 상품의 출고예약량 업데이트 */
		if($options){
			foreach($options as $data_option){
				// 출고량 업데이트를 위한 변수정의
				if(!in_array($data_option['goods_seq'],$r_reservation_goods_seq)){
					$r_reservation_goods_seq[] = $data_option['goods_seq'];
				}

				//상품별 쿠폰/프로모션코드 복원
				if($data_option['download_seq'] && $data_option['coupon_sale']) $goodscoupon = $this->couponmodel->restore_used_coupon($data_option['download_seq']);
				if($data_option['promotion_code_seq'] && $data_option['promotion_code_sale']) $goodspromotioncode = $this->promotionmodel->restore_used_promotion($data_option['promotion_code_seq']);
			}
		}

		if($suboptions){
			foreach($suboptions as $data_suboption){
				// 출고량 업데이트를 위한 변수정의
				if(!in_array($data_suboption['goods_seq'],$r_reservation_goods_seq)){
					$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
				}
			}
		}

		/* 배송비할인쿠폰 복원*/
		$shipping_coupon	= $this->couponmodel->get_shipping_coupon($orders['order_seq']);
		if($shipping_coupon){
			foreach($shipping_coupon as $row) {
				$shippingcoupon = $this->couponmodel->restore_used_coupon($row['shipping_coupon_down_seq']);
			}
		}

		// 주문서쿠폰 복원
		if($orders['ordersheet_seq']){
			$ordersheetcoupon = $this->couponmodel->restore_used_coupon($orders['ordersheet_seq']);
		}

		/* 배송비프로모션코드 복원 개별코드만 */
		if( $orders['shipping_promotion_code_seq'] ){
			$shippingpromotioncode = $this->promotionmodel->restore_used_promotion($orders['shipping_promotion_code_seq']);
		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		$log = "-";
		$caccel_arr = array(
			'ea'	=> $tot_ea,
			'price'	=> $orders['settleprice']
		);

		$this->ordermodel->set_log($order_seq,'cancel',$this->managerInfo['mname'],'주문무효',$log,$caccel_arr);
		openDialogAlert("주문무효가 완료되었습니다.",400,140,'parent',"parent.location.reload();");
	}

	public function _deposit_exec($order_seq){

		$orders						= $this->ordermodel->get_order($order_seq);

		# 간편결제 API  주문건 주문 무효 불가 처리
		$npay_use		= npay_useck();			//Npay v2.1 사용여부
		$talkbuy_use	= talkbuy_useck();		//카카오페이 구매사용여부
		if(($npay_use && $orders['npay_order_id']) || ($talkbuy_use && $orders['talkbuy_order_id'])) {
			$marketname = order_market_name($orders);
			openDialogAlert($marketname." 주문 건은 직접 결제확인 할 수 없습니다.",400,140,'parent',"");
			exit;
		}

		if( !in_array($orders['step'],$this->ordermodel->able_step_action['order_deposit']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 결제확인를 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}
		$this->coupon_reciver_sms	= array();
		$this->coupon_order_sms		= array();
		$this->send_for_provider = array();
		$this->ordermodel->set_step($order_seq,25);

		$log_str = "관리자가 결제확인을 하였습니다.";
		$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],'결제확인',$log_str);

		/* 해당 주문 상품의 출고예약량 업데이트 */
		$result_option = $this->ordermodel->get_item_option($order_seq);
	   	$result_suboption = $this->ordermodel->get_item_suboption($order_seq);

	   	// 출고량 업데이트를 위한 변수선언
	   	$r_reservation_goods_seq = array();
		$providerArr = array();

		if($result_option){
			foreach($result_option as $data_option){
				if	($data_option['goods_kind'] == 'coupon')	$coupon++;
				else											$goods++;

				if($data_option['provider_seq']) $providerList[$data_option['provider_seq']]	= 1;

				// 출고량 업데이트를 위한 변수정의
				if(!in_array($data_option['goods_seq'],$r_reservation_goods_seq)){
					$r_reservation_goods_seq[] = $data_option['goods_seq'];
				}

				$providerArr[] = $data_option['provider_seq'];
			}
		}
		if($result_suboption){
			foreach($result_suboption as $data_suboption){
				if	($data_suboption['goods_kind'] == 'coupon')	$coupon++;
				else											$goods++;

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

		typereceipt_setting($order_seq);

		// 결제확인 메일링
		send_mail_step25($order_seq);
		if( $orders['order_cellphone'] ){
			$params['shopName']		= $this->config_basic['shopName'];
			$params['ordno']		= $order_seq;
			$params['user_name']	= $orders['order_user_name'];
			$params['member_seq']	= $orders['member_seq'];

			//결제 확인 SMS 데이터 생성
			/**
			- 수동일괄처리시 관리자/입점사 1통 : {설정된 컨텐츠} 외 00건으로 개선
			- @2017-08-17
			**/
			sendSMS_for_provider('settle', $providerList, $params);
			if($this->send_for_provider['order_cellphone']) {
				$params['provider_mobile']	=  $this->send_for_provider['order_cellphone'];
			}
			$commonSmsData['settle']['phone'][] = $orders['order_cellphone'];
			$commonSmsData['settle']['params'][] = $params;
			$commonSmsData['settle']['order_no'][] = $order_seq;

			if( $orders['sms_25_YN'] != 'Y' ) {
				$this->db->where('order_seq', $orders['order_seq']);
				$this->db->update('fm_order', array('sms_25_YN'=>'Y'));
			}
		}
		unset($providerList);


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

			$commonSmsData['coupon_released']['phone'] = $coupon_order_cellphones;
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

			$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;
			$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
			$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;

		}
		if(count($commonSmsData) > 0){
			commonSendSMS($commonSmsData);
		}

		// 관리자 푸시알림 발송 2018-01-02 jhr
		push_for_admin(array(
			'kind'			=> 'order_deposit',
			'unique'		=> $orders['order_seq'],
			'ordno'			=> $orders['order_seq'],
			'member_seq'	=> $orders['member_seq'],
			'provider_list'	=> $providerArr,
			'user_name'		=> $orders['order_user_name']
		));

		return array('coupon' => $coupon, 'goods' => $goods);
	}

	// 결제확인
	public function deposit(){
		$auth = $this->authmodel->manager_limit_act('order_deposit');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}

		$referer = $this->input->server('HTTP_REFERER');
		
		if (!preg_match('/admin\/order\//', $referer)) {
			pageBack("관리자 권한이 없습니다.");
		}

		$order_seq	= $this->input->get('seq');

		$result		= $this->_deposit_exec($order_seq);
		$coupon		= $result['coupon'];
		$goods		= $result['goods'];

		// [판매지수 EP] 쿠키로 ep 등록 처리된 주문건인지 확인 후 EP 수집 :: 2018-09-18 pjw
		if(!$this->statsmodel) $this->load->model('statsmodel');
		$this->statsmodel->set_order_sale_ep($order_seq);

		$endMsg	= "<div><b>결제가 확인되었습니다.</b></div><br/>";
		if		($coupon > 0 && $goods > 0){
			$endMsg	.= "<div style=\"text-align:left;\">▶ 실물상품 : 출고처리하여 상품을 발송하세요.<br/>▶ 티켓상품 : 티켓번호가 발송되었습니다.</div><br/>";
		}elseif	($coupon > 0){
			$endMsg	.= "<div style=\"text-align:left;\">▶ 티켓상품 : 티켓번호가 발송되었습니다.</div><br/>";
		}else{
			$endMsg	.= "<div style=\"text-align:left;\">▶ 실물상품 : 출고처리하여 상품을 발송하세요.</div><br/>";
		}

		openDialogAlert($endMsg,500,280,'parent',"parent.location.reload();");
	}

	public function receipt_process(){
		$auth = $this->authmodel->manager_limit_act('sales_view');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}
		$order_seq	=  $_GET['order_seq'];
		$seq		=  $_GET['seq'];
		$result = firstmall_typereceipt($order_seq, $seq);
		if($result){
			$return["result"] = true;
		}else{
			$return["result"] = false;
		}
		echo json_encode($return);
		exit;
	}

	// 에누리
	public function enuri(){

		$order_seq	= $_GET['seq'];
		$enuri		= (int) $_POST['enuri'];
		$orders		= $this->ordermodel->get_order($order_seq);

		# 간편결제 API 주문건 주문 무효 불가 처리
		$npay_use		= npay_useck();			//Npay v2.1 사용여부
		$talkbuy_use	= talkbuy_useck();		//카카오페이 구매사용여부
		if(($npay_use && $orders['npay_order_id']) || ($talkbuy_use && $orders['talkbuy_order_id'])) {
			$marketname = order_market_name($orders);
			openDialogAlert($marketname." 주문 건은 에누리 변경을 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		if( !in_array($orders['step'],$this->ordermodel->able_step_action['enuri']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 에누리 변경을 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		if( !$ordres['payment'] != 'bank' ){
			openDialogAlert("무통장 주문만 에누리를 적용할 수 있습니다.",400,140,'parent',"");
			exit;
		}

		if( $enuri > $orders['settleprice']+$orders['enuri']){
			openDialogAlert("에누리금액은 결제금액을 초과할 수 없습니다.",400,140,'parent',"");
			exit;
		}

		if($enuri != $orders['enuri']){
			$this->ordermodel->set_enuri($order_seq,$enuri, $orders['enuri']);

			// 세금계산서를 신청한 경우 증빙금액 업데이트
			if ($orders['typereceipt'] == 1) {
				$this->ordermodel->update_tax_sales($order_seq);
			}else if($orders['typereceipt'] == 2){
				$this->ordermodel->update_cashreceipt_sales($order_seq);
			}

			// 마일리지/에누리/예치금 사용 상품옵션,추가옵션 별로 나누기
			$this->ordermodel->update_unit_emoney_cash_enuri($order_seq);

			$log_str = "에누리가 변경 되었습니다.";
			$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],'에누리 변경 ('.get_currency_price($orders['enuri'],3).' ->'.get_currency_price($enuri,3).' )',$log_str);

			/**
			* 1-2 에누리 사용 상품옵션,추가옵션, 배송비 별로 나누기 : 시작
			* 정산개선 - 임시매출테이블 업데이트 @
			**/

			$this->load->helper('accountall');
			if(!$this->accountallmodel)$this->load->model('accountallmodel');
			//step1 주문금액별 정의/비율/단가계산 후 정렬
			$set_order_price_ratio = $this->accountallmodel->set_order_price_ratio($order_seq);
			//step2 적립금/이머니 update
			$this->accountallmodel->update_ratio_emoney_cash_enuri_npoint($order_seq, $set_order_price_ratio,'enuri');
			//step3 임시매출데이타 정산업데이트
			$this->accountallmodel->enuri_update_calculate_sales_order_tmp($order_seq);

			//debug_var($this->db->queries);
			//debug_var($this->db->query_times);
			/**
			* 1-2에누리 사용 상품옵션,추가옵션, 배송비 별로 나누기 : 끝
			* 정산개선 - 임시매출테이블 업데이트 @
			**/


			openDialogAlert("에누리가 변경 되었습니다.",400,140,'parent',"parent.location.reload();");
		}

	}

	// 일괄 결제확인
	public function batch_deposit(){
		$this->coupon_reciver_sms	= array();
		$this->coupon_order_sms		= array();
		$this->send_for_provider	= array();
		
		$referer = $this->input->server('HTTP_REFERER');
		
		if (!preg_match('/admin\/order\//', $referer)) {
			pageBack("관리자 권한이 없습니다.");
		}

		# npay 주문건 주문 무효 불가 처리
		$npay_use	= npay_useck();	//Npay v2.1 사용여부
		$talkbuy_use	= talkbuy_useck();	//카카오페이 구매 사용여부

		$auth		= $this->authmodel->manager_limit_act('order_deposit');
		if(!$auth){
			if($_GET['ajaxcall']){
				echo "auth";
			}else{
				pageBack("관리자 권한이 없습니다.");
			}
			exit;
		}
		$order_count				= 0;

		// 출고량 업데이트를 위한 변수선언
		$r_reservation_goods_seq		= array();
		$npay_order = $talkbuy_order	= array();
		$providerList					= array();

		foreach($_POST['seq'] as $order_seq){

			$orders	= $this->ordermodel->get_order($order_seq);

			# Npay 주문건은 결제확인 처리 불가.
			if($npay_use && $orders['npay_order_id']){
				$npay_order[] = $orders['npay_order_id'];
				continue;
			}

			# 카카오페이구매 주문건은 결제확인 처리 불가.
			if($talkbuy_use && $orders['talkbuy_order_id']){
				$talkbuy_order[] = $orders['talkbuy_order_id'];
				continue;
			}
			
			if( !in_array($orders['step'],$this->ordermodel->able_step_action['order_deposit']) ){
				$result['error'][] = $order_seq;
				echo json_encode( $result );
				exit;
			}

			$this->ordermodel->set_step($order_seq,25);
			$log_str = "관리자가 결제확인을 하였습니다.";
			$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],'결제확인',$log_str);
			$result['ok'][] = $order_seq;

			/* 출고예약량 업데이트 */
			$result_option = $this->ordermodel->get_item_option($order_seq);
		   	$result_suboption = $this->ordermodel->get_item_suboption($order_seq);
			$providerArr = array();
			if($result_option){
				foreach($result_option as $data_option){
					if	($data_option['goods_kind'] == 'coupon')	$coupon_cnt++;
					else											$goods_cnt++;

					if($data_option['provider_seq']) $providerList[$data_option['provider_seq']]	= 1;

					// 출고량 업데이트를 위한 변수정의
					if(!in_array($data_option['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $data_option['goods_seq'];
					}
					$providerArr[] = $data_option['provider_seq'];
				}
			}
			if($result_suboption){
				foreach($result_suboption as $data_suboption){
					if	($data_suboption['goods_kind'] == 'coupon')	$coupon_cnt++;
					else											$goods_cnt++;

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

			typereceipt_setting($order_seq);

			// 결제 확인 메일링
			send_mail_step25($order_seq);

			// 샵링커 주문은 email/SMS 미발송
			if(!$orders['linkage_id'] && $orders['pg'] != 'pg'){
				if( $orders['order_cellphone'] ){
					$params['shopName']		= $this->config_basic['shopName'];
					$params['ordno']		= $order_seq;
					$params['user_name']	= $orders['order_user_name'];
					$params['member_seq']	= $orders['member_seq'];

					/**
					- 수동일괄처리시 관리자/입점사 1통 : {설정된 컨텐츠} 외 00건으로 개선
					- @2017-08-17
					**/
					sendSMS_for_provider('settle', $providerList, $params);
					if($this->send_for_provider['order_cellphone']) {
						$params['provider_mobile']	=  $this->send_for_provider['order_cellphone'];
					}
					$arr_params[$order_count]		= $params;
					$order_no[$order_count]			= $order_seq;
					$order_cellphones[$order_count] = $orders['order_cellphone'];
					$order_count					=$order_count+1;

					if( $orders['sms_25_YN'] != 'Y' ) {
						$this->db->where('order_seq', $orders['order_seq']);
						$this->db->update('fm_order', array('sms_25_YN'=>'Y'));
					}
				}
			}
			unset($providerList);

			//티켓상품 자동 출고처리구문 순차진행을 위해 분리함 @2017-08-16
			ticket_payexport_ck($orders['order_seq']);

			// 관리자 푸시알림 발송 2018-01-02 jhr
			push_for_admin(array(
				'kind'			=> 'order_deposit',
				'unique'		=> $orders['order_seq'],
				'ordno'			=> $orders['order_seq'],
				'member_seq'	=> $orders['member_seq'],
				'user_name'		=> $orders['order_user_name'],
				'provider_list'	=> $providerArr
			));

			// [판매지수 EP] 쿠키로 ep 등록 처리된 주문건인지 확인 후 EP 수집 :: 2018-09-18 pjw
			if(!$this->statsmodel) $this->load->model('statsmodel');
			$this->statsmodel->set_order_sale_ep($orders['order_seq']);
		}

		//결제 확인 SMS 데이터 생성
		if(count($order_cellphones) > 0){
			$commonSmsData['settle']['phone'] = $order_cellphones;
			$commonSmsData['settle']['params'] = $arr_params;
			$commonSmsData['settle']['order_no'] = $order_no;
		}

		//입점관리자 SMS 데이터
		if(count($this->send_for_provider['order_cellphone']) > 0){
			$provider_count = 0;
			foreach($this->send_for_provider['order_cellphone'] as $key=>$value){
				$provider_msg[$provider_count]			= $this->send_for_provider['msg'][$key];
				$provider_order_cellphones[$provider_count] = $this->send_for_provider['order_cellphone'][$key];
				$provider_count					=$provider_count+1;
			}
			$commonSmsData['provider']['phone'] = $provider_order_cellphones;
			$commonSmsData['provider']['msg'] = $provider_msg;
		}

		//받는 사람 티켓상품 SMS 데이터
		if(count($this->coupon_reciver_sms['order_cellphone']) > 0){
			$order_count = 0;
			foreach($this->coupon_reciver_sms['order_cellphone'] as $key=>$value){
				$coupon_arr_params[$order_count]		= $this->coupon_reciver_sms['params'][$key];
				$coupon_order_no[$order_count]			= $this->coupon_reciver_sms['order_no'][$key];
				$coupon_order_cellphones[$order_count] = $this->coupon_reciver_sms['order_cellphone'][$key];
				$order_count					=$order_count+1;
			}
			$commonSmsData['coupon_released']['phone'] = $coupon_order_cellphones;
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
			$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;
			$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
			$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;
		}

		if(count($commonSmsData) > 0){
			commonSendSMS($commonSmsData);
		}

		// 실물 + 쿠폰
		if		($coupon_cnt > 0 && $goods_cnt > 0)	$result	= 'all';
		elseif	($coupon_cnt > 0)					$result	= 'coupon';	// 쿠폰
		else										$result	= 'goods';	// 실물

		# npay 주문 결제확인 처리불가 안내
		if($npay_use && count($npay_order) > 0 ){
			echo "npay";
			exit;
		}

		# 카카오톡구매 주문 결제확인 처리불가 안내
		if($talkbuy_use && count($talkbuy_order) > 0 ){
			echo "talkbuy";
			exit;
		}

		echo $result;
	}

	// 일괄 주문 무효
	public function batch_cancel_order(){

		//2015-05-19 jhr 주문 무효는 따로 권한이 없기 때문에 주문 보기의 권한에 따른다 (팀장님 결정)
		$auth = $this->authmodel->manager_limit_act('order_view');
		if(!$auth){
			if($_GET['ajaxcall']){
				echo "auth";
			}else{
				pageBack("관리자 권한이 없습니다.");
			}
			exit;
		}

		$npay_use = npay_useck();	//Npay v2.1 사용여부
		$talkbuy_use = talkbuy_useck();	//카카오톡페이 구매사용여부

		// 출고량 업데이트를 위한 변수선언
		$r_reservation_goods_seq = array();

		$partner_order				= array();

		foreach($_POST['seq'] as $order_seq){

			$options	= $this->ordermodel->get_item_option($order_seq);
			$suboptions	= $this->ordermodel->get_item_suboption($order_seq);
			$orders		= $this->ordermodel->get_order($order_seq);

			if(($npay_use && $orders['pg'] == "npay") || ($talkbuy_use && $orders['pg'] == "talkbuy")){
				$partner_order[] = $order_seq;
				continue;
			}

			$this->ordermodel->set_step($order_seq,95);

			if($options) foreach($options as $k => $option){
				$tot_ea		+= $option['ea'];
			}
			if($suboptions) foreach($suboptions as $k => $option){
				$tot_ea		+= $option['ea'];
			}
			if($orders['member_seq']){
				$this->load->model('membermodel');

				/* 마일리지 환원 */
				if($orders['emoney_use']=='use' && $orders['emoney']>0 )
				{
					$params = array(
						'gb'		=> 'plus',
						'type'		=> 'cancel',
						'emoney'	=> $orders['emoney'],
						'ordno'		=> $order_seq,
						'memo'		=> "[복원]".$this->arr_step[95]."(".$order_seq.")에 의한 마일리지 환원",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp252",$order_seq),  // $this->arr_step[95] 는 "주문무효"이며 변동되지 않음, [복원]주문무효(%s)에 의한 마일리지 환원
					);
					$this->membermodel->emoney_insert($params, $orders['member_seq']);
					$this->ordermodel->set_emoney_use($order_seq,'return');
				}

				/* 예치금 환원 */
				if($orders['cash_use']=='use' && $orders['cash']>0)
				{
					$this->load->model('membermodel');
					$params = array(
						'gb'		=> 'plus',
						'type'		=> 'cancel',
						'cash'		=> $orders['cash'],
						'ordno'		=> $order_seq,
						'memo'		=> "[복원]".$this->arr_step[95]."(".$order_seq.")에 의한 예치금 환원",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp253",$order_seq),  // $this->arr_step[95] 는 "주문무효"이며 변동되지 않음, [복원]주문무효(%s)에 의한 예치금 환원
					);
					$this->membermodel->cash_insert($params, $orders['member_seq']);
					$this->ordermodel->set_cash_use($order_seq,'return');
				}
			}

			/* 쿠폰 환원 */
			$this->load->model('couponmodel');
			$this->load->model('promotionmodel');

			/* 배송비할인쿠폰 복원*/
			$shipping_coupon	= $this->couponmodel->get_shipping_coupon($orders['order_seq']);
			if($shipping_coupon){
				foreach($shipping_coupon as $row) {
					$shippingcoupon = $this->couponmodel->restore_used_coupon($row['shipping_coupon_down_seq']);
				}
			}

			// 주문서쿠폰 복원
			if($orders['ordersheet_seq']){
				$ordersheetcoupon = $this->couponmodel->restore_used_coupon($orders['ordersheet_seq']);
			}

			/* 배송비프로모션코드 복원 개별코드만 */
			if($orders['shipping_promotion_code_seq']){
				$shippingpromotioncode = $this->promotionmodel->restore_used_promotion($orders['shipping_promotion_code_seq']);
			}

			//상품별 쿠폰/프로모션코드 복원
			foreach($options as $data_option){
				if($data_option['download_seq']) $this->couponmodel->restore_used_coupon($data_option['download_seq']);
				if($data_option['promotion_code_seq']) $this->promotionmodel->restore_used_promotion($data_option['promotion_code_seq']);
			}


			$log = "-";
			$caccel_arr = array(
				'ea'	=> $tot_ea,
				'price'	=> $orders['settleprice']
			);

			$this->ordermodel->set_log($order_seq,'cancel',$this->managerInfo['mname'],'주문무효',$log,$caccel_arr);

			/* 출고예약량 업데이트 */
			if($options){
				foreach($options as $data_option){
					// 출고량 업데이트를 위한 변수정의
					if(!in_array($data_option['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $data_option['goods_seq'];
					}
				}
			}
			if($suboptions){
				foreach($suboptions as $data_suboption){
					// 출고량 업데이트를 위한 변수정의
					if(!in_array($data_suboption['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
					}
				}
			}

		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		if($partner_order){
			$result = "partner";
		}

		echo $result;
	}

	public function download_write(){
		## VALID
		$this->validation->set_rules('name', '이름', 'trim|required|xss_clean');		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		if(count($_POST['downloads_item_use'])<1){
			$callback = "parent.document.getElementsByName('name')[0].focus();";
			openDialogAlert("다운로드 항목을 1개 이상 설정해 주세요.",400,140,'parent',$callback);
			exit;
		}

		//print_r($_POST['downloads_item_use']);

		$item = implode("|",$_POST['downloads_item_use']);
		$params['name']			= $_POST['name'];
		$params['criteria']		= $_POST['criteria'];
		$params['item']			= $item;
		if ($_POST['only_real'] == 'on') {
			$params['item'] .= '||REAL';
		}
		$params['update_date'] = date("Y-m-d H:i:s");
		if($_POST['seq']){
			$this->db->where(array("seq"=>$_POST['seq'],"provider_seq"=>'1'));
			$result = $this->db->update('fm_exceldownload', $params);
			$msg	= "수정 되었습니다.";
			$func	= "parent.location.reload();";
		}else{
			$params['provider_seq']	= 1;
			$params['regdate'] = date("Y-m-d H:i:s");
			$this->db->insert('fm_exceldownload', $params);
			$msg = "등록 되었습니다.";
			$func	= "parent.location.replace('/admin/order/download_list');";
		}
		openDialogAlert($msg,400,140,'parent',$func);

	}


	public function download_delete(){
		$seq = $_POST['seq'];
		$result = $this->db->delete('fm_exceldownload', array("seq"=>$seq,"provider_seq"=>'1'));
		openDialogAlert("삭제되었습니다.",400,140,'parent',"parent.location.reload();");
	}

	//spout download kmj
	public function excel_down()
	{
		$aPostParams = $this->input->post();

		// validation
		if ($aPostParams) {
			$this->validation->set_data($aPostParams);
			$this->validation->set_rules('header_search_keyword', '검색어', 'trim|string|xss_clean');
			$this->validation->set_rules('header_search_type', '검색선택', 'trim|string|xss_clean');
			$this->validation->set_rules('pagemode', '모드', 'trim|string|xss_clean');
			$this->validation->set_rules('page', '페이지', 'trim|numeric|xss_clean');
			$this->validation->set_rules('searchTime', '검색일시', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		if($aPostParams['order_seq']){
			$form_seq				= $aPostParams['seq'];
			$str_order_seq			= $aPostParams['order_seq'];
			$excel_step				= $aPostParams['excel_step'];
			$chk_step				= $aPostParams['chk_step'];
			$excel_provider_seq_selector = $aPostParams['excel_provider_seq_selector'];
			$excel_provider_seq		= $aPostParams['excel_provider_seq'];
			$excel_provider_name	= $aPostParams['excel_provider_name'];
			$excel_ship_set_code	= $aPostParams['excel_ship_set_code'];
			$excel_type				= $aPostParams['excel_type'];
			parse_str($aPostParams['params'], $search_params);
			$search_params['pagemode'] = $aPostParams['pagemode'];
		}else{
			$form_seq				= $_GET['seq'];
			$str_order_seq			= $_GET['order_seq'];
			$excel_step				= $_GET['excel_step'];
			$chk_step				= $_GET['chk_step'];
			$excel_provider_seq_selector = $_GET['excel_provider_seq_selector'];
			$excel_provider_seq		= $_GET['excel_provider_seq'];
			$excel_provider_name	= $_GET['excel_provider_name'];
			$excel_ship_set_code	= $_GET['excel_ship_set_code'];
			$excel_type				= $_GET['excel_type'];
			parse_str($_GET['params'], $search_params);
			$search_params['pagemode'] = $_GET['pagemode'];
		}

		//입점사 체크
		if($search_params['pagemode'] == 'company_catalog' && $excel_provider_seq <= 1){
			echo "입점사 코드가 누락 되었습니다. 관리자에게 문의 하세요.";
			exit;
		}

		//엑셀 다운로드 요청
		if($search_params['pagemode'] != 'company_catalog'){
			$excel_request_seq = 1;
		} else {
			$excel_request_seq = $excel_provider_seq ;
		}

		if($excel_step > 0){
			unset($search_params['chk_step']);
			$search_params['chk_step'][$excel_step] = 1;
		} else {
			$search_params['chk_step'] = $chk_step;
		}

		$searchCount			= 0;
		$excel_provider_seq		= (int) $excel_provider_seq;
		$excel_ship_set_code	= $excel_ship_set_code;

		/*
		if( empty($search_params['regist_date'][0])
			|| empty($search_params['regist_date'][1]) ){ //3월 이내 데이터만 검색 가능 kmj

			$search_params['regist_date'][0] = date("Y-m-d",strtotime("-3 Months"));
			$search_params['regist_date'][1] = date("Y-m-d");
		}
		*/

		if($str_order_seq == 'search'){
			$arr_order_seq						= 'search';
			$search_params['query_type']		= 'total_record';
			$search_params['shipping_method']	= $excel_ship_set_code;
			$search_params['excel_provider_seq']= $excel_provider_seq;
			$res = $this->ordermodel->get_order_catalog_query_spout($search_params);
			$searchCount = $res[0]['cnt'];
		} else {
			$arr_order_seq	= explode('|',$str_order_seq); // 주문번호 추출
			$arr_order_seq	= array_filter($arr_order_seq);
			$searchCount	= count($arr_order_seq);
		}

		if($searchCount <= 0){
			echo "조건에 맞는 데이터가 없습니다";
			exit;
		}

		$this->load->model('providershipping');
		$this->load->model('order2exportmodel');
		$this->load->model('excelmodel');

		$this->excelmodel->get_exceldownload($form_seq);
		$excel_type .= "_".$this->excelmodel->data_exceldownload['criteria'];
		$excel_type = strtolower($excel_type);

		$this->order2exportmodel->courier_for_provider[1] = $this->providershipping->get_provider_courier(1);

		$limitCount = 2000;
		if( $searchCount > $limitCount ){ //엑셀 spout 비동기 처리 시작
			$params					= array();
			$params['list']			= $arr_order_seq;
			$params['searchcount']	= $searchCount;
			$params['form_seq']		= $form_seq;
			$params['provider_seq']	= $excel_provider_seq;
			$params['ship_set_code']= $excel_ship_set_code;
			$params['excel_step']	= $excel_step;
			$params					= array_merge($params, $search_params);

			$regDate = date('Y-m-d H:i:s');
			$setData = array(
				'id'			=> '',
				'provider_seq'	=> $excel_request_seq,
				'manager_id'	=> $this->managerInfo['manager_id'],
				'category'		=> '2', //type >> 1:goods, 2:order, 3:member
				'excel_type'	=> $excel_type,
				'context'		=> serialize($params),
				'count'			=> $searchCount,
				'state'			=> 0,
				'limit_count'	=> $limitCount,
				'reg_date'		=> $regDate
			);
			$this->db->insert('fm_queue', $setData);
			$queueID = $this->db->insert_id();
			if( $queueID > 0 ){
				/*
				$shipdomainArr = parse_url(base_url(uri_string()));
				$shopdomain	= $shipdomainArr['host'];

				$postParams = array(
					'queueID'		=> $queueID,
					'md5'			=> "excel_order_".$regDate,
					'limitCount'	=> $limitCount,
					'manager_id'	=> $this->managerInfo['manager_id']
				);
				$post_string = http_build_query($postParams);

				if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'){
					$shopport = 'https://';
				} else {
					$shopport = 'http://';
				}

				$parts = parse_url($shopport.$shopdomain.'/cli/excel_down/create_order');
				if ($parts['scheme'] == 'http'){
					$fp = fsockopen($parts['host'], isset($parts['port'])?$parts['port']:80, $errno, $errstr, 30);
				}else if ($parts['scheme'] == 'https'){
					$fp = fsockopen("ssl://" . $parts['host'], isset($parts['port'])?$parts['port']:443, $errno, $errstr, 30);
				}

				if (!$fp) {
					echo "$errstr ($errno), open sock erro.<br/>\n";
					exit;
				}

				fwrite($fp, "POST ".$parts['path']." HTTP/1.1\r\n");
				fwrite($fp, "Host: ".$parts['host']."\r\n");
				fwrite($fp, "Content-Type: application/x-www-form-urlencoded\r\n");
				fwrite($fp, "Content-Length: ".strlen($post_string)."\r\n");
				fwrite($fp, "Connection: close\r\n");
				fwrite($fp, "\r\n");

				fwrite($fp, $post_string);
				while (!feof($fp)) { //response 확인 할때 만
					echo fgets($fp, 128);
				}

				fclose($fp);
				*/

				$expectTime = ((ceil($params['searchcount']/$limitCount)) * 20) + 1200;
				echo '엑셀 파일 생성 중 (예상 소요시간 : '.gmdate("H시 i분 s초", $expectTime).')';
			} else {
				echo "Job Insert Errors";
			}

		} else {
			if($arr_order_seq == 'search'){
				unset($arr_order_seq);
				unset($search_params['query_type']);
				$res = $this->ordermodel->get_order_catalog_query_spout($search_params);

				foreach($res as $v){
					$arr_order_seq[] = $v['order_seq'];
				}
				unset($res);
			}
			$provider_data	= $this->order2exportmodel->provider_data;

			$params['provider_seq']		= $excel_provider_seq;
			$params['ship_set_code']	= $excel_ship_set_code;
			$params['warehouse']		= $excel_warehouse;
			$params['form_seq']			= $form_seq;
			$params['excel_type']		= $excel_type;
			$params['limit_count']		= $limitCount;
			$params['excel_request_seq']= $excel_request_seq;
			$params['excel_spout']		= true;

			//$data[$order_seq] = $this->order2exportmodel->get_excel($params);
			$this->excelmodel->exceldownload_spout($arr_order_seq, $provider_data, $params);
			//if( $data ) $result[$order_seq] = $data;
		}
	}

	/*
	public function excel_down()
	{
		if($_POST['order_seq']){
			$form_seq				= $_POST['seq'];
			$str_order_seq			= $_POST['order_seq'];
			$excel_warehouse	= $_POST['excel_warehouse'];
		}else{
			$form_seq				= $_GET['seq'];
			$str_order_seq			= $_GET['order_seq'];
			$excel_warehouse	= $_GET['excel_warehouse'];
		}
		$excel_provider_seq	= (int) $_POST['excel_provider_seq'];
		$excel_ship_set_code	= $_POST['excel_ship_set_code'];
		$arr_order_seq			= explode('|',$str_order_seq); // 주문번호 추출

		$this->load->model('providershipping');
		$this->load->model('order2exportmodel');
		$this->load->model('excelmodel');

		$this->order2exportmodel->courier_for_provider[1] = $this->providershipping->get_provider_courier(1);
		foreach($arr_order_seq as $order_seq){
			if( $order_seq ) {
				$params['order_seq']			= $order_seq;
				$params['provider_seq']		= $excel_provider_seq;
				$params['ship_set_code']	= $excel_ship_set_code;
				$params['warehouse']		= $excel_warehouse;

				$data = $this->order2exportmodel->get_excel($params);
				if( $data ) $result[$order_seq] = $data;
			}
		}
		$provider_data	= $this->order2exportmodel->provider_data;

		if	($result == '') {
			openDialogAlert("조건에 맞는 데이터가 없습니다",400,140,'parent',"");
		}else{
			$this->excelmodel->get_exceldownload($form_seq);
			$this->excelmodel->exceldownload($result, $provider_data);
		}
	}
	*/

	//excel file down
	public function file_down(){
		$this->load->helper('download');
		if(is_file($_GET['realfiledir'])){
			$data = @file_get_contents($_GET['realfiledir']);
			force_download($_GET['filenames'], $data);
			exit;
		}
	}

	//결제취소 -> 환불
	public function order_refund(){

		$order_seq		= $_POST['order_seq'];
		$data_order		= $this->ordermodel->get_order($order_seq);
		$minfo			= $this->session->userdata('manager');
		$manager_seq	= $minfo['manager_seq'];
		$data_order_items 	= $this->ordermodel->get_item($order_seq);

		// 네이버페이 주문 취소 @2016-01-26 pjm
		$npay_use = npay_useck();	//Npay v2.1 사용여부

		if( !in_array($data_order['step'],array('25','35','40','45','50','60','70')) ){
			openDialogAlert($this->arr_step[$data_order['step']]."에서는 환불신청을 하실 수 없습니다.",400,140,'parent');
			exit;
		}

		if($data_order['orign_order_seq']){
			openDialogAlert("교환 주문 건은 결제 취소를 할 수 없습니다.<br />교환 주문 건 취소는 관리자가 교환 주문 건에 대해 출고 및 반품 처리 후 환불 처리 가능합니다.",400,170,'parent');
			exit;
		}

		if(!$_POST['chk_seq']){
			openDialogAlert("결제취소/환불 신청할 상품을 선택해주세요.",400,140,'parent');
			exit;
		}

		$order_total_ea = $this->ordermodel->get_order_total_ea($order_seq);

		$cancel_total_ea = 0;
		foreach($_POST['chk_ea'] as $k=>$v){
			/* 네이버페이 주문취소시 수량 전체 취소(부분취소불가)*/
			if($npay_use && $data_order['pg'] == "npay"){
				if($_POST['input_chk_ea'][$k] > $v) $_POST['chk_ea'][$k] = $v = $_POST['input_chk_ea'][$k];
			}else{
				if(!$v){
					openDialogAlert("결제취소/환불 신청할 수량을 선택해주세요.",400,140,'parent');
					exit;
				}
			}
			$cancel_total_ea += $v;
		}

		$result_option		= $this->ordermodel->get_item_option($order_seq);
		$result_suboption	= $this->ordermodel->get_item_suboption($order_seq);

		//취소가능 수량(주문접수, 결제완료, 상품준비) @2015-06-05 pjm
		$able_return_item = 0;
		foreach($result_option as $data){
			$able_return_ea = (int) $data['ea'] - (int) $data['step85'] - (int) $data['step55']
												- (int) $data['step65'] - (int) $data['step75'];
			$able_return_total += $able_return_ea;
		}
		foreach($result_suboption as $data){
			$able_return_ea = (int) $data['ea'] - (int) $data['step85'] - (int) $data['step55']
												- (int) $data['step65'] - (int) $data['step75'];
			$able_return_total += $able_return_ea;
		}

		// 네이버페이 주문 취소 @2016-01-26 pjm
		$npay_use = npay_useck();	//Npay v2.1 사용여부
		if($npay_use && $data_order['pg'] == "npay"){
			$this->load->model('naverpaymodel');
			$this->load->library('naverpaylib');
			$npay_reason_arr		= $this->naverpaylib->get_npay_code("claim_cancel");
			$_POST['refund_reason']	= $npay_reason_arr[$_POST['npay_reason_code']];
			if(!$_POST['refund_reason']){
				openDialogAlert("결제취소 사유를 선택해 주세요.",400,140,'parent');
				exit;
			}
		}else $npay_use = false;

		/*사은품 주문 취소 start by hyem 2018-11-28*/
		$gift_order = false;
		foreach($data_order_items as $item){
			if($item['goods_type'] == 'gift') {
				list($gift) = $this->ordermodel->get_option_for_item(array('item_seq'=>$item['item_seq']));
				$order_gift_ea += $gift['ea'];
				$gift_item[] = $gift;
				$gift_item_seq[] = $gift['item_seq'];
				$gift_order = true;
			}
		}

		if($gift_order === true) {
			$this->load->model('giftmodel');
			// 취소 가능 수량 : $able_return_total
			// 취소 요청 수량 : $cancel_total_ea
			// 사은품 수량 : $order_gift_ea
			if( $able_return_total == $cancel_total_ea + $order_gift_ea ) {
				// 전체 취소 시 - 사은품도 함께 취소 요청
				$cancel_total_ea += $order_gift_ea;
				foreach($gift_item as $v => $gift) {
					$_POST['chk_seq'][]				= '1';
					$_POST['chk_item_seq'][]		= $gift['item_seq'];
					$_POST['chk_option_seq'][]		= $gift['item_option_seq'];
					$_POST['chk_suboption_seq'][]	= '';
					$_POST['chk_ea'][]				= $gift['ea'];
				}
			} else {
				$gift_cancel = $this->ordermodel->order_gift_partial_cancel($order_seq, $gift_item_seq, $data_order_items );

				// _POST 변수 담아서 실제 사은품 취소 처리
				if(count($gift_cancel) > 0) {
					foreach($gift_cancel as $key => $gift) {
						$_POST['chk_seq'][]				= '1';
						$_POST['chk_item_seq'][]		= $gift['item_seq'];
						$_POST['chk_option_seq'][]		= $gift['item_option_seq'];
						$_POST['chk_suboption_seq'][]	= '';
						$_POST['chk_ea'][]				= $gift['ea'];
					}
				}
			}
		}
		/*사은품 주문 취소 end by hyem 2018-11-28*/

		/* 신용카드 자동취소 */
		if (
			!$npay_use && $_POST['manual_refund_yn']=='y'
			&& (in_array($data_order['payment'],array('card','kakaomoney')) || is_order_simple_payment($data_order))
			&& $order_total_ea==$cancel_total_ea
		) {
			$creditcard_cancel = true;
		} else {
			$creditcard_cancel = false;
		}

		if($creditcard_cancel)
		{
			$pgCompany = $this->config_system['pgCompany'];

			// 카카오 페이의 PG사를 추출하기 위한 데이터 :: 2015-02-25 lwh
			switch($data_order['pg']){
				case 'kakaopay':
				case 'payco':
				case 'naverpayment':
					$pglog_tmp				= $this->ordermodel->get_pg_log($order_seq);
					$pg_log_data			= $pglog_tmp[0];
					$data_order['pg_log']	= $pg_log_data;
					$pgCompany				= $data_order['pg'];
					break;
				case 'paypal':
					$pgCompany				= $data_order['pg'];
					break;
				case 'eximbay':
					$pgCompany				= $data_order['pg'];
					break;
			}

			if	(!$pgCompany){
				openDialogAlert("결제 취소 실패<br /><font color=red>설정된 전자결제(PG)사 정보가 없습니다.</font>",400,160,'parent','');
				exit;
			}

			$cancelFunction = "{$pgCompany}_cancel";
			$cancelResult = $this->refundmodel->$cancelFunction($data_order,array('refund_reason'=>$_POST['refund_reason'],'cancel_type'=>'full'));

			if(!$cancelResult['success']){
				openDialogAlert("{$pgCompany} 결제 취소 실패<br /><font color=red>{$cancelResult['result_code']} : {$cancelResult['result_msg']}</font>",400,160,'parent','');
				exit;
			}
			$_POST['cancel_type']		= 'full';
			$cancel_pg_card				= true;
		}else if($order_total_ea==$cancel_total_ea){
			$_POST['cancel_type'] = 'full';
		}else{
			$_POST['cancel_type'] = 'partial';
		}

		if(!$_POST['bank_name'])		$bank_name		= ""; else $bank_name		= $_POST['bank_name'];
		if(!$_POST['bank_depositor'])	$bank_depositor = ""; else $bank_depositor	= $_POST['bank_depositor'];
		if(!$_POST['bank_account'])		$bank_account	= ""; else $bank_account	= $_POST['bank_account'];

		$data = array(
			'order_seq'			=> $order_seq,
			'bank_name'			=> $bank_name,
			'bank_depositor'	=> $bank_depositor,
			'bank_account'		=> $bank_account,
			'refund_reason'		=> $_POST['refund_reason'],
			'refund_type'		=> 'cancel_payment',
			'cancel_type'		=> $_POST['cancel_type'],
			'regist_date'		=> date('Y-m-d H:i:s'),
			'manager_seq'		=> $manager_seq,
		);
		if($status) $data['status'] = $status;
		if($npay_use && $data_order['npay_order_id']){
			$data['npay_order_id']	= $data_order['npay_order_id'];
			$data['npay_flag']		= 'CancelSale';
		}

		$items						= array();

		// 출고량 업데이트를 위한 변수선언
		$r_reservation_goods_seq = array();

		# npay 주문 관련
		$tot_refund_price			= 0;
		$tot_refund_delivery		= 0;
		$partner_return				= array();

		$this->db->trans_begin();
		$rollback = false;

		$this->load->model("couponmodel");
		$this->load->model("promotionmodel");
		// 원주문의 배송정보
		$data_shipping		= $this->ordermodel->get_order_shipping($_POST['order_seq']);
		if	($data_shipping)foreach($data_shipping as $k => $ship){

			//복원된 배송비쿠폰 여부 shipping_coupon_sale
			if($ship['shipping_coupon_down_seq']){
				$ship['restore_used_coupon_refund'] = $this->couponmodel->restore_used_coupon_refund($ship['shipping_coupon_down_seq']);
			}
			//복원된 배송비프로모션코드 여부
			if($ship['shipping_promotion_code_seq']){
				//발급받은 프로모션 타입(일반, 개별) - 일반(공용) 코드는 복원 불가(계속 사용가능)
				if($ship['shipping_promotion_code_sale'] > 0){
					$shipping_promotion = $this->promotionmodel->get_download_promotion($ship['shipping_promotion_code_seq']);
					$ship['shipping_promotion_type'] = $shipping_promotion['type'];
				}
				$ship['restore_used_promotioncode_refund'] = $this->promotionmodel->restore_used_promotioncode_refund($ship['shipping_promotion_code_seq']);
			}

			$ship['international']			= $data_order['international'];
			if($ship['shipping_summary']){
				$ship['default_type']	= $ship['shipping_summary']['default_type'];
				$ship['first_cost']		= $ship['shipping_summary']['first_cost'];
				$ship['max_cost']		= $ship['shipping_summary']['max_cost'];
				$ship['min_cost']		= $ship['shipping_summary']['min_cost'];
			}
			$ships[$ship['shipping_seq']]	= $ship;
		}

		foreach($_POST['chk_seq'] as $k=>$v){

			$items[$k]['item_seq']		= $_POST['chk_item_seq'][$k];
			$items[$k]['option_seq']	= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
			$items[$k]['suboption_seq']	= $_POST['chk_suboption_seq'][$k];
			$items[$k]['ea']			= $_POST['chk_ea'][$k];
			$items[$k]['npay_product_order_id']	= $_POST['chk_npay_product_order_id'][$k];
			$items[$k]['partner_return']= true;
			$partner_return['items'][$k]= true;

			// npay 주문 취소 송신 @2016-01-26 pjm
			if($npay_use){
				# 추가옵션이 모두 반품된 후 필수옵션반품 가능.
				$kk = count($_POST['chk_npay_product_order_id']) - ($k + 1);
				$npay_product_order_id = $_POST['chk_npay_product_order_id'][$kk];
				$npay_data = array("npay_product_order_id"	=> $npay_product_order_id,
									"order_seq"				=> $order_seq,
									"cancel_reason"			=> $_POST['npay_reason_code']
								);
				$npay_res = $this->naverpaymodel->order_cancel($npay_data);
				if($npay_res['result'] != "SUCCESS"){
					$items[$k]['partner_return']	= false;
					$partner_return['items'][$k]	= false;
					$partner_return['partner_name']	= "네이버페이";
					$partner_return['msg'][]		= $npay_product_order_id." : ".$npay_res['message'];
					$partner_return['fail_cnt']++;
					$message						= "실패";
				}else{
					$message						= "성공";
					$partner_return['success_cnt']++;
				}
				if($npay_res['result'] != "SUCCESS") continue;

			}

			if($partner_return['items'][$k]){
				if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){
					$mode = 'option';

					//취소환불가능 갯수 검증 start @2016-12-27
					$query = "select o.*, i.goods_seq, i.shipping_seq from fm_order_item_option o, fm_order_item i  where o.item_seq=i.item_seq and o.item_option_seq=?";
					$query = $this->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();

					if($shipping_seq != $optionData['shipping_seq']){
						$query = "select * from fm_order_shipping where shipping_seq=?";
						$query = $this->db->query($query,array($optionData['shipping_seq']));
						$shippingData = $query->row_array();
						$shipping_seq = $shippingData['shipping_seq'];
					}

					$rf_ea = $this->refundmodel->get_refund_option_ea($items[$k]['item_seq'],$items[$k]['option_seq'],'cancel_payment');
					$step_complete = $this->ordermodel->get_option_export_complete($order_seq,$items[$k]['shipping_seq'],$items[$k]['item_seq'],$items[$k]['option_seq']);
					$able_refund_ea = $optionData['ea'] - $rf_ea - $step_complete;
					if($able_refund_ea < $items[$k]['ea']){
						$rollback = true;
						break;
					}
					//취소환불가능 갯수 검증 end @2016-12-27

					$this->ordermodel->set_step_ea(85,$items[$k]['ea'],$items[$k]['option_seq'],$mode);

					# 동일 배송그룹의 최초 배송비(기본배송비 or 개별배송비 가져오기 + 추가배송비)
					// 동일 배송그룹의 최초 배송비를 이미 조회 했다면 동일 배송그룹의 배송비는 입력할 필요 없음.
					if($arr_refund_delivery[$optionData['shipping_seq']]){
						$refund_delivery = 0;
					}else{
						$arr_refund_delivery[$optionData['shipping_seq']] = $this->ordermodel->get_delivery_existing_price($order_seq,$optionData['shipping_seq']);
						$refund_delivery = $arr_refund_delivery[$optionData['shipping_seq']];
					}

					//pg 카드결제취소시(전체) 상품최종환불액 자동계산 @2016-07-21 ysm
					//PG 전체 취소 시 주문시 사용한 이머니, 예치금 item별로 환불금 계산 추가.
					if( $cancel_pg_card ) {

						$refund_emoney_unit = $optionData['emoney_sale_unit']*$optionData['ea']+$optionData['emoney_sale_rest'];
						$refund_cash_unit	= $optionData['cash_sale_unit']*$optionData['ea']+$optionData['cash_sale_rest'];
						$refund_enuri_unit	= $optionData['enuri_sale_unit']*$optionData['ea']+$optionData['enuri_sale_rest'];

						// sale_price가 없을경우 그냥 price로 넣음 (환불 금액 계산을 위해서 추가) :: refund.php view() 와 동일하게 refund_goods_price 계산하도록 추가 2020-03-25
						$optionData['sale_price']	= $optionData['sale_price'] > 0 ? $optionData['sale_price'] : $optionData['price'];
						$sale_price_tmp				= $optionData['sale_price'] * $optionData['ea'];

						$refund_goods_price	= $sale_price_tmp
														-$refund_emoney_unit
														-$refund_cash_unit
														-$refund_enuri_unit;

						if($refund_delivery > 0){
							$refund_delivery = $refund_delivery
												- $shippingData['emoney_sale_unit'] - $shippingData['emoney_sale_rest']
												- $shippingData['cash_sale_unit'] - $shippingData['cash_sale_rest'];
						}
						$items[$k]['refund_goods_price']		= $refund_goods_price;
						$items[$k]['emoney_sale_unit']			= $optionData['emoney_sale_unit'];
						$items[$k]['cash_sale_unit']			= $optionData['cash_sale_unit'];
						$items[$k]['emoney_sale_rest']			= $optionData['emoney_sale_rest'];
						$items[$k]['cash_sale_rest']			= $optionData['cash_sale_rest'];
						$items[$k]['refund_delivery_price']		= $refund_delivery;
						$items[$k]['refund_delivery_cash']		= $shippingData['cash_sale_unit'] + $shippingData['cash_sale_rest'];
						$items[$k]['refund_delivery_emoney']	= $shippingData['emoney_sale_unit'] + $shippingData['emoney_sale_rest'];

						if($refund_delivery > 0){
							$refund_delivery_pg_price = $refund_delivery;
							//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
							if($data_order['pg_currency']  && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
								$refund_delivery_pg_price = get_currency_exchange($refund_delivery_pg_price,$data_order['pg_currency'],'','front');
							}
						}else{
							$refund_delivery_pg_price = 0;
						}

						if($refund_goods_price > 0){
							$refund_goods_pg_price = $refund_goods_price;
							//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
							if($data_order['pg_currency']  && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
								$refund_goods_pg_price = get_currency_exchange($refund_goods_pg_price,$data_order['pg_currency'],'','front');
							}
						}

						$items[$k]['refund_delivery_pg_price']	= $refund_delivery_pg_price;
						$items[$k]['refund_goods_pg_price']		= $refund_goods_pg_price;
					}

					if($optionData['ea']==$optionData['step85']){
						$this->db->set('step','85');
					}

					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_option_seq',$items[$k]['option_seq']);
					$this->db->update('fm_order_item_option');

					// 주문 option 상태 변경
					$this->ordermodel->set_option_step($items[$k]['option_seq'],'option');

					// 출고량 업데이트를 위한 변수정의
					if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $optionData['goods_seq'];
					}

					// 반품으로 인한 원주문 추출 및 교체 :: 2015-08-13 pjm
					$query = $this->db->get_where('fm_order_item_option',
						array(
						'item_option_seq'=>$items[$k]['option_seq'],
						'item_seq'=>$items[$k]['item_seq'])
					);
					$result = $query->row_array();

					if($result['top_item_option_seq']) $items[$k]['option_seq'] = $result['top_item_option_seq'];
					if($result['top_item_seq']) $items[$k]['item_seq'] = $result['top_item_seq'];

				}else if($items[$k]['suboption_seq']){
					$mode = 'suboption';

					//취소환불가능 갯수 검증 start @2016-12-27
					$query = "select o.*, i.goods_seq from fm_order_item_suboption o, fm_order_item i  where o.item_seq=i.item_seq and o.item_suboption_seq=?";
					$query = $this->db->query($query,array($items[$k]['suboption_seq']));
					$optionData = $query->row_array();
					$rf_ea = $this->refundmodel->get_refund_suboption_ea($items[$k]['item_seq'],$items[$k]['suboption_seq']);
					$step_complete = $this->ordermodel->get_suboption_export_complete($order_seq,$items[$k]['shipping_seq'],$items[$k]['item_seq'],$items[$k]['suboption_seq']);
					$able_refund_ea = $optionData['ea'] - $rf_ea - $step_complete;
					if($able_refund_ea < $items[$k]['ea']){
						$rollback = true;
						break;
					}
					//취소환불가능 갯수 검증 end @2016-12-27

					$this->ordermodel->set_step_ea(85,$items[$k]['ea'],$items[$k]['suboption_seq'],$mode);

					$query = "select o.*, i.goods_seq from fm_order_item_suboption o, fm_order_item i  where o.item_seq=i.item_seq and o.item_suboption_seq=?";
					$query = $this->db->query($query,array($items[$k]['suboption_seq']));
					$optionData = $query->row_array();

					//pg 카드결제취소시 상품최종환불액 자동계산 @2016-07-21 ysm
					if( $cancel_pg_card ) {
						$refund_emoney_unit = $optionData['emoney_sale_unit']*$optionData['ea']+$optionData['emoney_sale_rest'];
						$refund_cash_unit	= $optionData['cash_sale_unit']*$optionData['ea']+$optionData['cash_sale_rest'];
						$refund_goods_price	= (($optionData['price']-$optionData['member_sale'])*$optionData['ea'])
														-$optionData['fblike_sale']
														-$optionData['mobile_sale']
														-$optionData['referer_sale']
														-$refund_emoney_unit
														-$refund_cash_unit;
						$items[$k]['refund_goods_price'] = $refund_goods_price;

						if($refund_delivery > 0){
							$refund_delivery_pg_price = $refund_delivery;
							//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
							if($data_order['pg_currency']  && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
								$refund_delivery_pg_price = get_currency_exchange($refund_delivery_pg_price,$data_order['pg_currency'],'','front');
							}
						}else{
							$refund_delivery_pg_price = 0;
						}

						if($refund_goods_price > 0){
							$refund_goods_pg_price = $refund_goods_price;
							//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
							if($data_order['pg_currency']  && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
								$refund_goods_pg_price = get_currency_exchange($refund_goods_pg_price,$data_order['pg_currency'],'','front');
							}
						}

						$items[$k]['refund_delivery_pg_price']	= $refund_delivery_pg_price;
						$items[$k]['refund_goods_pg_price']		= $refund_goods_pg_price;
					}

					if($optionData['ea']==$optionData['step85']){
						$this->db->set('step','85');
					}

					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
					$this->db->update('fm_order_item_suboption');

					// 주문 option 상태 변경
					$this->ordermodel->set_option_step($items[$k]['suboption_seq'],'suboption');

					// 출고량 업데이트를 위한 변수정의
					if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $optionData['goods_seq'];
					}

					// 반품으로 인한 원주문 추출 및 교체 :: 2015-08-13 pjm
					$query = $this->db->get_where('fm_order_item_suboption',
						array(
						'item_suboption_seq'=>$items[$k]['suboption_seq'])
					);
					$result = $query->row_array();
					if($result['top_item_suboption_seq']) $items[$k]['suboption_seq'] = $result['top_item_suboption_seq'];

				}

				/*if($npay_use){
					$tot_refund_price		+= $refund_price;
					$tot_refund_delivery	+= $refund_delivery;
				}*/
			}

		}

		if ($this->db->trans_status() === FALSE || $rollback == true)
		{
			$this->db->trans_rollback();
			openDialogAlert('처리 중 오류가 발생했습니다.',400,140,'parent','');
			exit;
		}
		else
		{
			$this->db->trans_commit();
		}

		//외부몰(npay) 취소처리 실패건수가 있을때
		if($npay_use && $partner_return['fail_cnt']> 0){

			//결제취소 전체 실패시 오류메세지 띄움
			if((count($items) - $partner_return['fail_cnt']) <= 0){
				if(count($partner_return['msg']) < 1) $h = 140; else $h = 150 + (count($partner_return['msg'])*18);
				openDialogAlert("<span class=\'fx12\'>".$partner_return['partner_name']." 결제취소 실패!<br /><span class=\'red\'>".implode("<br />",$partner_return['msg'])."</span></span>",460,$h,'parent');
				exit;
			}
		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}


		#npay 주문 취소일시 취소금액 저장.
		if($npay_use){//담당자에게 확인후 사용하지 않는 소스주석 @2016-07-21 ysm
			//$data['refund_price']		= $tot_refund_price;
			//$data['refund_delivery']	= $tot_refund_delivery;
		}
		$this->ordermodel->set_order_step($order_seq);

		/* 신용카드 자동취소 */
		if (
			!$npay_use && $_POST['manual_refund_yn']=='y'
			&& ( in_array($data_order['payment'], ['card', 'kakaomoney']) || is_order_simple_payment($data_order) ) 
			&& $order_total_ea==$cancel_total_ea
		) {
			// 전체 취소로 인해 카드환불이 자동으로 이루어질 때 추가된 배송비와 pg_price를 업데이트 해준다
			$tmp_refund_delivery			= 0;
			$tmp_refund_pg_price_sum		= 0;
			$tmp_refund_pg_delivery_sum		= 0;
			foreach($items as $tmp_item){
				$tmp_refund_delivery			+= $tmp_item['refund_delivery_price'];
				$tmp_refund_pg_price_sum		+= $tmp_item['refund_goods_pg_price'];
				$tmp_refund_pg_delivery_sum		+= $tmp_item['refund_delivery_pg_price'];
			}
			$data['refund_delivery']		= $tmp_refund_delivery;
			$data['refund_pg_price']		= $tmp_refund_pg_price_sum;
			$data['refund_pg_delivery']		= $tmp_refund_pg_delivery_sum;
		}

		$refund_code = $this->refundmodel->insert_refund($data,$items);
		if(!$refund_code || trim($refund_code) == ''){
			openDialogAlert(getAlert('mb178'),400,140,'parent','');
			exit;
		}

		/* 신용카드 자동취소 */
		if($creditcard_cancel)
		{
			$this->load->model('emoneymodel');
			$this->load->model('membermodel');
			$this->load->model('couponmodel');
			$this->load->model('promotionmodel');
			$this->load->helper('text');

			$refund_emoney	= 0;
			$refund_cash	= 0;

			$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
			$data_member		= $this->membermodel->get_member_data($data_order['member_seq']);

			//상품별 할인쿠폰/프로모션코드 복원
			foreach($_POST['chk_seq'] as $k=>$v){
				$items[$k]['item_seq']		= $_POST['chk_item_seq'][$k];
				$items[$k]['option_seq']	= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
				$items[$k]['suboption_seq']	= $_POST['chk_suboption_seq'][$k];
				$items[$k]['ea']			= $_POST['chk_ea'][$k];

				if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){
					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();

					/* 할인쿠폰 복원*/
					if($optionData['download_seq']){
						$optcoupon = $this->couponmodel->restore_used_coupon($optionData['download_seq']);
						if($optcoupon){
							$data_order['coupon_sale'] += $optionData['coupon_sale'];
						}
					}

					/* 프로모션코드 복원 개별코드만 */
					if($optionData['promotion_code_seq']){
						$optpromotioncode = $this->promotionmodel->restore_used_promotion($optionData['promotion_code_seq']);
						if($optpromotioncode){
							$data_order['shipping_promotion_code_sale'] += $optionData['promotion_code_sale'];
						}
					}

				}
			}

			/* 배송비할인쿠폰 복원*/
			$shipping_coupon	= $this->couponmodel->get_shipping_coupon($data_order['order_seq']);
			if($shipping_coupon){
				foreach($shipping_coupon as $row) {
					$shippingcoupon = $this->couponmodel->restore_used_coupon($row['shipping_coupon_down_seq']);
				}
			}

			// 주문서쿠폰 복원
			if($data_order['ordersheet_seq']){
				$ordersheetcoupon = $this->couponmodel->restore_used_coupon($data_order['ordersheet_seq']);
			}

			/* 배송비프로모션코드 복원 개별코드만 */
			if($data_order['shipping_promotion_code_seq']){
				$shippingpromotioncode = $this->promotionmodel->restore_used_promotion($data_order['shipping_promotion_code_seq']);
			}

			if($data_order['member_seq']){
				/* 마일리지 지급 */
				if($data_order['emoney_use']=='use' && $data_order['emoney'] > 0 )
				{
					$params = array(
						'gb'		=> 'plus',
						'type'		=> 'cancel',
						'emoney'	=> $data_order['emoney'],
						'ordno'		=> $data_order['order_seq'],
						'memo'		=> "[복원]결제취소({$refund_code})에 의한 마일리지 환원",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp248",$refund_code),  // [복원]결제취소(%s)에 의한 마일리지 환원
					);

					// 기본 적립금 유효기간 계산
					$reserve_str_ts = '';
					$reserve_limit_date = '';
					$cfg_reserves = config_load('reserve');
					if( $cfg_reserves['reserve_select'] == 'direct' ){
						$reserve_str_ts = "+".$cfg_reserves['reserve_direct']." month";
						$reserve_limit_date = date('Y-m-d',strtotime($reserve_str_ts));
					}
					if( $cfg_reserves['reserve_select'] == 'year' ){
						$reserve_str_ts = "+".$cfg_reserves['reserve_year']." year";
						$reserve_limit_date = date('Y-12-31',strtotime($reserve_str_ts));
					}
					if( $reserve_limit_date ){
						$params['limit_date'] = $reserve_limit_date;
					}

					$this->membermodel->emoney_insert($params, $data_order['member_seq']);
					$this->ordermodel->set_emoney_use($data_order['order_seq'],'return');

					$refund_emoney = $data_order['emoney'];
				}

				/* 예치금 지급 */
				if($data_order['cash_use']=='use' && $data_order['cash'] > 0 )
				{
					$params = array(
						'gb'		=> 'plus',
						'type'		=> 'cancel',
						'cash'		=> $data_order['cash'],
						'ordno'		=> $data_order['order_seq'],
						'memo'		=> "[복원]결제취소({$refund_code})에 의한 예치금 환원",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp249",$refund_code),  // [복원]결제취소(%s)에 의한 예치금 환원
					);
					$this->membermodel->cash_insert($params, $data_order['member_seq']);
					$this->ordermodel->set_cash_use($data_order['order_seq'],'return');

					$refund_cash = $data_order['cash'];
				}
			}

			$tot_refund_price = $data_order['settleprice'] + $refund_emoney + $refund_cash;	//총 환불액(PG결제액 + 마일리지할인액 + 예치금사용액)

			$saveData = array(
				'adjust_use_coupon'		=> $data_order['coupon_sale'],
				'adjust_use_promotion'		=> $data_order['shipping_promotion_code_sale'],
				'adjust_use_emoney'		=> $data_order['emoney'],
				'adjust_use_cash'		=> $data_order['cash'],
				'adjust_use_enuri'		=> $data_order['enuri'],
				'refund_method'			=> 'card',
				'refund_price'			=> $tot_refund_price,
				'refund_emoney'			=> $refund_emoney,
				'refund_cash'			=> $refund_cash,
				'status'				=> 'complete',
				'refund_emoney_limit_date' => $reserve_limit_date,
				'refund_delivery'		=> $data_order['shipping_cost'],
				'refund_date'			=> date('Y-m-d H:i:s')
			);
			$this->db->where('refund_code', $refund_code);
			$this->db->update("fm_order_refund",$saveData);

			// 추가옵션 관련 아이템 재배열
			$items_array	= array();
			if($data_refund_item)foreach($data_refund_item as $item){
				if($item['title1'])		$item['options_str']  = $item['title1'] .":".$item['option1'];
				if($item['title2'])		$item['options_str'] .= " / ".$item['title2'] .":".$item['option2'];
				if($item['title3'])		$item['options_str'] .= " / ".$item['title3'] .":".$item['option3'];
				if($item['title4'])		$item['options_str'] .= " / ".$item['title4'] .":".$item['option4'];

				if	($item['opt_type'] == 'sub'){
					$item['price']								= $item['price'] * $item['ea'];
					$item['sub_options']							= $item['options_str'];
					if	($first_option_seq)
						$items_array[$first_option_seq]['sub'][]		= $item;
					else
						$items_array[$item['option_seq']]['sub'][]		= $item;
				}else{
					$items_array[$item['option_seq']]['price']		+= $item['price'] * $row['ea'];
					$items_array[$item['option_seq']]['ea']			+= $item['ea'];
					$items_array[$item['option_seq']]['option_ea']	+= $item['option_ea'];
					$items_array[$item['option_seq']]['goods_name']	= $item['goods_name'];
					$items_array[$item['option_seq']]['options']	= $item['options_str'];
					$items_array[$item['option_seq']]['inputs']		= $this->ordermodel->get_input_for_option($item['item_seq'], $item['option_seq']);
					$items_array[$item['option_seq']]['image']		= $item['image'];
				}
				if	(!$first_option_seq)	$first_option_seq	= $item['option_seq'];

				/* 입점사별 환불 정보 pjm */
				$provider_seq			= $item['provider_seq'];
				$refund_delivery_price	= 0;
				$refund_goods_price		= ($item['price']*$item['ea'])-$item['coupon_sale']-($item['member_sale']*$item['ea'])-$item['fblike_sale']-$item['mobile_sale']-$item['promotion_code_sale']-$item['referer_sale'];
				if($item['opt_type'] == "opt"){
					if($item['shipping_policy'] == "shop"){
						$refund_delivery_price = $item['basic_shipping_cost'];
					}elseif($item['shipping_policy'] == "goods"){
						$refund_delivery_price = $item['goods_shipping_cost'];
					}
				}
				if($provider_seq){
					$refund_provider[$provider_seq]['provider_seq']			= $provider_seq;
					$refund_provider[$provider_seq]['refund_expect_price']	= 0;
					$refund_provider[$provider_seq]['adjust_refund_price']	+= $refund_goods_price+$refund_delivery_price;
					$refund_provider[$provider_seq]['refund_price']			+= $refund_goods_price+$refund_delivery_price;
				}
			}

			/* 입점사별 환불 정보 pjm */
			foreach($refund_provider as $provider_data){
				$this->refundmodel->set_provider_refund($refund_code, $provider_data);
			}

			$order_itemArr = array();
			$order_itemArr = array_merge($order_itemArr,$data_order);
			$order_itemArr['order_seq'] = $data_order['order_seq'];
			$order_itemArr['mpayment'] = $data_order['mpayment'];
			$order_itemArr['deposit_date'] = $data_order['deposit_date'];
			$order_itemArr['bank_account'] = $data_order['bank_account'];
			$order_itemArr['pg_transaction_number'] = $data_order['pg_transaction_number'];

			/* 결제취소완료 안내메일 발송 */
			$params = array_merge($saveData,$_POST);
			$params	= array_merge($params,$data_member);
			$params['refund_reason']	= htmlspecialchars($_POST['refund_reason']);
			$params['refund_date']		= $saveData['refund_date'];
			$params['mstatus'] 			= $this->refundmodel->arr_refund_status['complete'];
			$params['refund_price']		= number_format($saveData['refund_price']);
			$params['mrefund_method']	= $this->arr_payment['card'].' '.$this->arr_step[85];
			$params['items'] 			= $items_array;
			$params['order']			= $order_itemArr;
			if( $data_order['order_email'] )
				sendMail($data_order['order_email'], 'cancel', $data_member['userid'], $params);

			/* 결제취소완료 SMS 발송 */
			$params					= array();
			$params['shopName']		= $this->config_basic['shopName'];
			$params['ordno']		= $data_order['order_seq'];
			$params['member_seq']	= $data_order['member_seq'];
			$params['user_name']	= $data_order['order_user_name'];


			//SMS 데이터 생성
			$commonSmsData['cancel']['phone'][] = $data_order['order_cellphone'];
			$commonSmsData['cancel']['params'][] = $params;
			$commonSmsData['cancel']['order_no'][] = $data_order['order_seq'];
			commonSendSMS($commonSmsData);

			$logTitle	= $this->arr_step[85]."(".$refund_code.")";
			$logDetail	= "신용카드 전체취소처리하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams);

			/**
			* 4-2 환불관련 정산개선 시작
			* @
			**/
			$this->load->helper('accountall');
			if(!$this->accountallmodel)$this->load->model('accountallmodel');
			if(!$this->providermodel)$this->load->model('providermodel');
			if(!$this->refundmodel)$this->load->model('refundmodel');
			if(!$this->returnmodel)$this->load->model('returnmodel');
			//정산대상 수량업데이트
			$this->accountallmodel->update_calculate_sales_ac_ea($data_order['order_seq'],$refund_code, 'refund');
			//정산확정 처리
			$this->accountallmodel->insert_calculate_sales_order_refund($data_order['order_seq'], $refund_code, $_POST['cancel_type'], $data_order);//월별매출
			/**
			* 4-2 환불관련 정산개선 시작
			* @
			**/

			$callback = "
			parent.closeDialog('order_refund_layer');
			parent.document.location.reload();
			";

			$arr_field = search_arr_field();
			$payment = $arr_field['arr_order_payment'][$data_order['payment']];
			openDialogAlert($payment." ".$this->arr_step[85]."가 완료되었습니다.",400,140,'parent',$callback);
		}else{

			$logTitle	= "환불신청(".$refund_code.")";
			$logDetail	= $this->arr_step[85]."/환불신청하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams);

			$callback = "
			parent.closeDialog('order_refund_layer');
			parent.document.location.reload();
			";
			openDialogAlert($this->arr_step[85]."/환불 신청이 완료되었습니다.",400,140,'parent',$callback);
		}
	}

	//결제 취소처리
	public function order_refund_etc()
	{
		$data_order = $this->ordermodel->get_order($_POST['order_seq']);
		$minfo = $this->session->userdata('manager');
		$manager_seq = $minfo['manager_seq'];

		if( !in_array($data_order['step'],array('25','35','40','45','50','60','70')) ){
			openDialogAlert($this->arr_step[$data_order['step']]."에서는 환불신청을 하실 수 없습니다.",400,140,'parent');
			exit;
		}

		$data = array(
				'order_seq' => $_POST['order_seq'],
				'bank_name' => $_POST['bank_name'],
				'bank_depositor' => $_POST['bank_depositor'],
				'bank_account' => $_POST['bank_account'],
				'refund_reason' => $_POST['refund_reason'],
				'refund_type' => 'cancel_payment',
				'cancel_type' => 'partial',
				'regist_date' => date('Y-m-d H:i:s'),
				'refund_price' => 0,
				'manager_seq' => $manager_seq
		);

		$refund_code = $this->refundmodel->insert_refund($data);

		$logTitle	= "환불신청(".$refund_code.")";
		$logDetail	= "결제취소/환불(기타) 신청하였습니다.";
		$this->ordermodel->set_log($_POST['order_seq'],'process',$this->managerInfo['mname'],$logTitle,$logDetail);

		$callback = "
		parent.closeDialog('order_refund_layer');
		parent.document.location.reload();
		";
		openDialogAlert("결제취소/환불(기타) 신청이 완료되었습니다.",400,140,'parent',$callback);

	}



	//실물상품 반품 or 맞교환 -> 환불
	public function order_return(){

		$this->load->model('returnmodel');
		$this->load->model('exportmodel');

		$cfg_order			= config_load('order');
		$data_order			= $this->ordermodel->get_order($_POST['order_seq']);
		$data_order_items	= $this->ordermodel->get_item($_POST['order_seq']);

		$minfo			= $this->session->userdata('manager');
		$manager_seq	= $minfo['manager_seq'];

		// npay 주문건 확인
		$npay_use = npay_useck();	//Npay v2.1 사용여부
		if($npay_use && $data_order['npay_order_id']){
			$this->load->model("naverpaymodel");
			$arr_consumer_imputation = array("INTENT_CHANGED","COLOR_AND_SIZE","WRONG_ORDER");
		}else{
			$npay_use = false;
		}

		if($_POST['mode']=='exchange'){
			$mode_title		= "맞교환";
			$logTitle		= "맞교환신청";
		}else{
			$mode_title		= "반품";
			$logTitle		= "반품신청";
			$_POST['mode']	= "return";
		}

		$chk_seq	= $_POST['chk_seq'];
		$chk_ea		= $_POST['chk_ea'];

		if(!$chk_seq){
			openDialogAlert($logTitle."할 상품을 선택해주세요.",400,140,'parent');
			exit;
		}

		// 반품 배송비 무결성 체크 :: 2018-05-21 lwh
		if ($_POST['reason'] == '120'){
			$this->validation->set_rules('refund_ship_type', getAlert('mo153'),'trim|required|xss_clean');
			$_POST['refund_ship_duty'] = 'buyer';
		}else{
			$_POST['refund_ship_duty'] = 'seller';
		}

		if ($_POST['refund_ship_type'] == 'A'){
			$this->validation->set_rules('shipping_price_bank_account', getAlert('os064'),'trim|required|xss_clean');
			$this->validation->set_rules('shipping_price_depositor', getAlert('os064'),'trim|required|xss_clean');
		}

		// 반품완료 시 구매자부담에 실결제가격이 반품배송비보다 적은경우 처리안되게함 :: 2018-07-16 pjw
		// 총 반송배송비
		$total_pay_shipping = 0;
		foreach($_POST['pay_shiping_cost'] as $pay_shipping){
			$total_pay_shipping += $pay_shipping;
		}

		if(!$npay_use){
			$this->validation->set_rules('cellphone[]', '휴대폰','trim|required|numeric|max_length[4]|xss_clean');
		}

		if($_POST['return_method'] == 'shop'){
			$this->validation->set_rules('return_recipient_zipcode[]', '우편번호','trim|required|numeric|max_length[7]|xss_clean');
			$this->validation->set_rules('return_recipient_address', '주소','trim|required|xss_clean');
			$this->validation->set_rules('return_recipient_address_detail', '상세주소','trim|required|xss_clean');
		}

		// 네이버페이 주문건은 validation 검증 안함 :: 2019-03-04 rsh
		if($this->validation->exec()===false && $npay_use === false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		## 반품가능 수량 admin @2015-06-05 pjm
		## 출고수량(출고완료 + 배송중 + 배송완료) - 반품수량
		$partner_return				= array();		//외부연동몰(npay) 반품접수 결과

		// 환불금액 차감 검증 :: 2018-07-03 lwh
		// 위치 이동 시킴 :: 2018-07-19 pjw
		// 수식 변경 :: 2018-08-23 pjw
		// 실 결제금액, 반품배송비 크기 비교
		// 2018-10-15 pjm 반품하는 상품 전체의 결제금액으로 비교.
		$total_payment_amount	= 0;
		foreach($chk_ea as $k => $return_apply_ea){

			$option_seq				= $_POST['chk_option_seq'][$k];
			$suboption_seq			= $_POST['chk_suboption_seq'][$k];

			$option_data			= $this->ordermodel->get_order_item_option($option_seq);
			$suboption_data			= $this->ordermodel->get_order_item_suboption($suboption_seq);

			$total_payment_amount	+= $option_data['sale_price'] * $return_apply_ea;
			$total_payment_amount	+= $suboption_data['sale_price'] * $return_apply_ea;

		}

		if($total_payment_amount < $total_pay_shipping && $_POST['refund_ship_duty'] == 'buyer' && $_POST['refund_ship_type'] == 'M'){
			openDialogAlert(getAlert('mo154'),400,140,'parent',$callback);
			exit;
		}

		foreach($chk_ea as $k => $return_apply_ea){

			if($return_apply_ea == 0){
				openDialogAlert($mode_title." 수량을 0건으로 입력한 경우에는 신청되지 않습니다.",400,140,'parent');
				exit;
			}

			$export_code			= $_POST['chk_export_code'][$k];
			$item_seq				= $_POST['chk_item_seq'][$k];
			$option_seq				= $_POST['chk_option_seq'][$k];
			$suboption_seq			= $_POST['chk_suboption_seq'][$k];
			$able_return_ea			= 0;
			$cancel_type			= false;	//청약철회상품체크

			$orditemData			= $this->ordermodel->get_item_one($item_seq);

			//청약철회상품체크(반품불가)
			/*
			$goodscanceltype = $this->goodsmodel->get_goods($orditemData['goods_seq']);
			if( $goodscanceltype['cancel_type']) $cancel_type = true;

			if($cancel_type){
				openDialogAlert("청약철회 상품은 ".$mode_title."이 [불가능]합니다.",400,140,'parent');
				exit;
			}
			*/

			## 출고수량
			$exp_data			= $this->exportmodel->get_export_item_ea($export_code,$option_seq,$suboption_seq);

			if(!$suboption_seq) $return_item = $this->returnmodel->get_return_item_ea($item_seq,$option_seq,$export_code);
				else $return_item = $this->returnmodel->get_return_subitem_ea($item_seq,$suboption_seq,$export_code);

			$able_return_ea	= $exp_data['ea'] - $return_item['ea'];

			if($able_return_ea <= 0){
				openDialogAlert($mode_title." 가능한 수량이 없습니다.",400,140,'parent');
				exit;
			}

			if($able_return_ea < $return_apply_ea){
				openDialogAlert($mode_title." 수량이 ".$mode_title."가능수량보다 많습니다.",400,140,'parent');
				exit;
			}


			$_POST['scm_supply_price'][$k]	= $exp_data['scm_supply_price'];

			$partner_return['items'][$k]	= true;

			$_POST['npay_order_id']			= '';
			$_POST['npay_flag']				= '';

			## npay 사용시 api 반품 접수(상품주문번호,반품사유코드,수거배송방법코드)
			if($_POST['mode']=='return'){

				# 추가옵션이 모두 반품된 후 필수옵션반품 가능.
				$kk = count($_POST['chk_npay_product_order_id']) - ($k + 1);
				$npay_product_order_id	= $_POST['chk_npay_product_order_id'][$kk];	//npay 상품주문번호
				if($npay_product_order_id && $npay_use){
					$_POST['refund_ship_duty'] = $this->returnmodel->get_npay_ship_duty($this->input->post('reason'));
					$npay_params = array("npay_product_order_id"=>$npay_product_order_id,
										"order_seq"			=>$data_order['order_seq'],
										"actor"				=>$this->managerInfo['mname'],
										"reason"			=>$_POST['reason'],
										"return_method"		=>$_POST['return_method']);
					$npay_res = $this->naverpaymodel->order_return($npay_params);
					if($npay_res['result'] != "SUCCESS"){
						$items[$k]['partner_return']	= false;
						$partner_return['items'][$k]	= false;
						$partner_return['msg'][]		= $npay_product_order_id." : ".$npay_res['message'];
						$partner_return['fail_cnt']++;
					}else{
						$npay_result_msg				= '';
					}
					$_POST['npay_order_id'] = $data_order['npay_order_id'];
					# 구매자 귀책사유시 보류 처리
					if(in_Array($_POST['reason'],$arr_consumer_imputation)){
						$_POST['npay_flag']		= 'return_deliveryfee';
					}else{
						$_POST['npay_flag']		= 'return_request';
					}
				}
			}
			$_POST['partner_return'][$k]	= $partner_return['items'][$k];

		}

		// 사은품 있는 경우 확인 필요
		$gift_order = false;
		foreach($data_order_items as $item){
			if($item['goods_type'] == 'gift') {
				// option_seq 찾기
				list($gift) = $this->ordermodel->get_option_for_item($item['item_seq']);
				$chk = array();
				$chk['item_seq']		= $item['item_seq'];
				$chk['option_seq']		= $gift['item_option_seq'];

				// export_data 찾기
				$gexport = $this->exportmodel->get_export_item_by_item_seq('',$chk);
				$order_gift_ea += $gexport['ea'];
				$gift_item[] = $gexport;
				$gift_item_seq[] = $gexport['item_seq'];
				$gift_order = true;
			}
		}

		if($gift_order === true) {

			// 반품요청하는 출고건에 총 반품가능수량 구하기
			$export_code_fld	= 'export_code';
			if(preg_match('/^B/', $export_code))	$export_code_fld	= 'bundle_export_code';

			$where[] = $export_code;

			$query = "select * from fm_goods_export_item where " . $export_code_fld . "=? ";
			$query = $this->db->query($query,$where);
			$able_return_total	= 0;
			foreach($query->result_array() as $exp_item){
				## 구매확정 사용시 : 지급예정수량(출고완료+배송중+배송완료)
				## 구매확정 미사용시 : 출고수량(출고완료 + 배송중 + 배송완료) - 반품수량
				if($cfg_order['buy_confirm_use']){
					$able_return_total	+= $exp_item['reserve_ea'];
				}else{

					## 반품수량
					if(!$suboption_seq) $return_item = $this->returnmodel->get_return_item_ea($exp_item['item_seq'],$exp_item['option_seq']);
						else $return_item = $this->returnmodel->get_return_subitem_ea($exp_item['item_seq'],$exp_item['suboption_seq']);
					$able_return_total	+= $exp_item['ea'] - $return_item['ea'];
				}
			}

			$this->load->model('giftmodel');
			// 취소 가능 수량 : $able_return_total
			// 취소 요청 수량 : $cancel_total_ea
			// 사은품 수량 : $order_gift_ea

			if( $able_return_total == $cancel_total_ea + $order_gift_ea ) {
				// 전체 취소 시 - 사은품도 함께 취소 요청
				$cancel_total_ea += $order_gift_ea;
				foreach($gift_item as $v => $gift) {
					$chk_seq[]						= '1';
					$_POST['chk_seq'][]				= '1';
					$_POST['chk_item_seq'][]		= $gift['item_seq'];
					$_POST['chk_option_seq'][]		= $gift['option_seq'];
					$_POST['chk_suboption_seq'][]	= '';
					$_POST['chk_ea'][]				= $gift['ea'];
					$_POST['chk_export_code'][]		= $gift['export_code'];
				}
			} else {
				$gift_cancel = $this->ordermodel->order_gift_partial_cancel($_POST['order_seq'], $gift_item_seq, $data_order_items,'return');

				// _POST 변수 담아서 실제 사은품 취소 처리
				if(count($gift_cancel) > 0) {
					foreach($gift_cancel as $key => $gift) {
						$chk_seq[]						= '1';
						$_POST['chk_seq'][]				= '1';
						$_POST['chk_item_seq'][]		= $gift['item_seq'];
						$_POST['chk_option_seq'][]		= $gift['item_option_seq'];
						$_POST['chk_suboption_seq'][]	= '';
						$_POST['chk_ea'][]				= $gift['ea'];
						$_POST['chk_export_code'][]		= $gift['export_code'];
					}
				}
			}
		}

		if($_POST['bank'])			$bank		= $_POST['bank'];		else $bank		= "";
		if($_POST['account'])		$account	= $_POST['account'];	else $account	= "";
		if(!$_POST['depositor'])	$depositor	= "";					else $depositor = $_POST['depositor'];
		$_POST['refund_method'] = ($_POST['refund_method'])?$_POST['refund_method']:(($data_order['payment'])?$data_order['payment']:'bank');

		//출고건 배송완료 처리, 마일리지 지급 관련 정리
		$give_reserve_ea = $this->returnmodel->order_return_delivery_confirm($cfg_order,$_POST);

		// 환불 등록
		if(!$npay_use && $bank){
			$tmp		= code_load('bankCode',$bank);
			$bank		= $tmp[0]['value'];
			if($account) $account	= implode('-',$account);
		}

		$_POST['refund_method'] = ($_POST['refund_method'])?$_POST['refund_method']:(($data_order['payment'])?$data_order['payment']:'bank');

		$items					= array();
		foreach($chk_seq as $k=>$v){

			$items[$k]['item_seq']					= $_POST['chk_item_seq'][$k];
			$items[$k]['option_seq']				= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
			$items[$k]['suboption_seq']				= $_POST['chk_suboption_seq'][$k];
			$items[$k]['ea']						= $_POST['chk_ea'][$k];
			$items[$k]['npay_product_order_id']		= $_POST['chk_npay_product_order_id'][$k];
			$items[$k]['partner_return']			= $_POST['partner_return'][$k];

			if($items[$k]['partner_return']){

				$export_code = $_POST['chk_export_code'][$k];

				## 지급한 마일리지&포인트 뽑아오기. 2015-03-31 pjm
				if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){
					$option_seq = $items[$k]['option_seq'];
					$option_type = "OPT";
				}else{
					$option_seq = $items[$k]['suboption_seq'];
					$option_type = "SUB";
				}

				$_POST['give_reserve_ea'][$k] = $give_reserve_ea[$export_code][$option_type][$option_seq];
				if($_POST['give_reserve_ea'][$k] > 0){
					$reserve			= $this->ordermodel->get_option_reserve($option_seq,'reserve',$option_type);
					$point				= $this->ordermodel->get_option_reserve($option_seq,'point',$option_type);
					$give_reserve		= $reserve * $_POST['give_reserve_ea'][$k];
					$give_point			= $point * $_POST['give_reserve_ea'][$k];
					$tot_give_reserve	+= $give_reserve;
					$tot_give_point		+= $give_point;
				}else{
					$give_reserve		= 0;
					$give_point			= 0;
					$give_reserve_ea	= 0;
				}

				$items[$k]['give_reserve']		= $_POST['give_reserve'][$k]		= $give_reserve;
				$items[$k]['give_point']		= $_POST['give_point'][$k]			= $give_point;
				$items[$k]['give_reserve_ea']	= $_POST['give_reserve_ea'][$k];

				if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){

					$mode = 'option';

					// 반품으로 인한 원주문 추출 및 교체 :: 2014-11-27 lwh
					// @pjm 설명 덧붙임 : 교환 재주문건 반품 시 최상위 원주문의 환불로 생성됨.
					$query = $this->db->get_where('fm_order_item_option',
						array(
						'item_option_seq'=>$items[$k]['option_seq'],
						'item_seq'=>$items[$k]['item_seq'])
					);
					$result = $query -> result_array();

					if($result[0]['top_item_option_seq'])
						$items[$k]['option_seq'] = $result[0]['top_item_option_seq'];

					if($result[0]['top_item_seq'])
						$items[$k]['item_seq'] = $result[0]['top_item_seq'];

					/* 사용처 확인 안됨
					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();
					*/

					if($_POST['mode']!='exchange'){
						$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
						$this->db->where('item_option_seq',$items[$k]['option_seq']);
						$this->db->update('fm_order_item_option');
					}
				}else if($items[$k]['suboption_seq']){

					$mode = 'suboption';

					// 반품으로 인한 원주문 추출 및 교체 :: 2014-11-27 lwh
					// @pjm 설명 덧붙임 : 교환 재주문건 반품 시 최상위 원주문의 환불로 생성됨.
					$query = $this->db->get_where('fm_order_item_suboption',
						array(
						'item_suboption_seq'=>$items[$k]['suboption_seq'])
					);
					$result = $query -> result_array();

					if($result[0]['top_item_suboption_seq'])
						$items[$k]['suboption_seq'] = $result[0]['top_item_suboption_seq'];

					/*
					$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
					$query = $this->db->query($query,array($items[$k]['suboption_seq']));
					$optionData = $query->row_array();
					*/

					if($_POST['mode']!='exchange'){
						$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
						$this->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
						$this->db->update('fm_order_item_suboption');
					}
				}
			}

		}

		// 환불배송비 계산 :: 2018-05-21 lwh
		$_POST['return_shipping_price']		= ($_POST['refund_ship_type']) ? $total_pay_shipping : 0;		// post.pay_shiping_cost sum > total_pay_shipping

		//외부몰(npay) 반품접수 실패건수가 있을때
		if($npay_use && $_POST['mode']=='return' && $partner_return['fail_cnt']> 0){
			//반품접수 전체 실패시 오류메세지 띄움
			if((count($items) - $partner_return['fail_cnt']) <= 0){
				if(count($partner_return['msg']) < 1) $h = 140; else $h = 150 + (count($partner_return['msg'])*18);
				openDialogAlert("<span class=\'fx12\'>Npay 반품접수 실패!<br /><span class=\'red\'>".implode("<br />",$partner_return['msg'])."</span></span>",460,$h,'parent');
				exit;
			}
		}

		if($_POST['mode']=='exchange'){
			$refund_code = '0';
			$return_type = 'exchange';
		}else{
			// 맞교환으로 인한 재주문을 반품신청시 최상위 주문번호 저장
			if($data_order['top_orign_order_seq'])
				$orgin_order_seq = $data_order['top_orign_order_seq'];
			else
				$orgin_order_seq = $_POST['order_seq'];

			// 반품시 최상위 주문번호 저장 :: 2014-11-27 lwh
			// @pjm 설명 덧 붙임 : 교환으로 인한 재주문건은 주문금액 없음. 환불은 최상위 원주문에만 생성함.
			if($data_order['top_orign_order_seq'])
				$orgin_order_seq = $data_order['top_orign_order_seq'];
			else
				$orgin_order_seq = $_POST['order_seq'];

			// 구매확정 후 환불 여부 by hed #32095
			// 반품신청과 구매확정은 출고단위로 이루어지므로
			// 출고가 구매확정되었다면 동일출고건 내의 모든 반품신청은 구매확정 후 반품으로 처리된다.
			$chk_after_refund = $this->input->post('chk_after_refund');
			foreach($chk_after_refund as $v){
				if($v){
					$after_refund = '1';
				}
			}

			## 환불신청
			$data = array(
				'order_seq'			=> $orgin_order_seq,
				'bank_name'			=> ($bank)?$bank:'',
				'bank_depositor'	=> ($depositor)?$depositor:'',
				'bank_account'		=> ($account)?$account:'',
				'refund_reason'		=> '반품환불',
				'refund_type'		=> 'return',
				'regist_date'		=> date('Y-m-d H:i:s'),
				'manager_seq'		=> $manager_seq,
				'after_refund'		=> $after_refund,	// 구매확정 후 환불 여부 by hed #32095
			);

			$refund_code	= $this->refundmodel->insert_refund($data,$items);
			$return_type	= 'return';

			$logTitle		= "환불신청(".$refund_code.")";
			$logDetail		= "관리자 반품신청에 의한 환불신청이 접수되었습니다.";
			$logParams		= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($orgin_order_seq,'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams,'');
		}

		// 환불, 반품(&교환) DB Insert
		$return_code = $this->returnmodel->order_return_insert($_POST,$refund_code,$return_type,$partner_return);

		if(!$return_code){
			$res_msg = " 실패";
			$items[$k]['return_badea']		= '0';					// 불량재고로 반품 ( scm )
			$items[$k]['scm_supply_price']	= $scm_supply_price[$k];// 출고당시 출고창고 평균매입가 ( scm )
		}

		if($_POST['mode']=='exchange'){
			if($res_msg){
				$title		= "맞교환 신청이 실패되었습니다.";
			}else{
				$title		= "맞교환 신청이 완료되었습니다.";
			}
			$logTitle	= "맞교환신청".$res_msg."(".$return_code.")";
			$logDetail	= "관리자가 맞교환신청을".$res_msg." 하였습니다.";
		}else{
			if($res_msg){
				$title		= "반품 신청이 실패되었습니다.";
			}else{
				$title		= "반품 신청이 완료되었습니다.";
			}
			$logTitle	= "반품신청".$res_msg."(".$return_code.")";
			$logDetail	= "관리자가 반품신청을".$res_msg." 하였습니다.";
		}

		if($partner_return['fail_cnt'] > 0){
			$partner_error_msg = $partner_return['fail_cnt']."건 실패<br />".implode("<br />",$partner_return['msg']);
			$title		.= "Naverpay 반품접수 ".$partner_error_msg;
			$logDetail	.= "<br />Naverpay 반품접수 ".$partner_error_msg;
		}

		$logParams	= array('return_code' => $return_code);
		$this->ordermodel->set_log($_POST['order_seq'],'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams,'');

		//npay 주문건 아니거나 npay 주문, 반품일때만 (교환은 shop에서 접수 불가)
		if(!$npay_use || ($npay_use && $_POST['mode']=='return')){

			$callback = "
			parent.closeDialog('order_return_layer');
			parent.document.location.reload();";
			openDialogAlert($title,400,140,'parent',$callback);

		}else{

			// npay 주문건 교환 접수 일때
			return "ok";
		}
	}


	//티켓상품 반품 or 맞교환 -> 환불
	public function order_return_coupon(){
		$this->load->model('returnmodel');
		$this->load->model('exportmodel');

		$cfg_order = config_load('order');

		$minfo = $this->session->userdata('manager');
		$manager_seq = $minfo['manager_seq'];

		if(!$_POST['chk_seq']){
			if($_POST['mode']=='exchange'){
				openDialogAlert("맞교환할 상품을 선택해주세요.",400,140,'parent');
			}else{
				openDialogAlert("반품 신청할 상품을 선택해주세요.",400,140,'parent');
			}
			exit;
		}

		$this->validation->set_rules('cellphone[]', '휴대폰','trim|required|numeric|max_length[4]|xss_clean');
		//$this->validation->set_rules('phone[]', '연락처','trim|required|numeric|max_length[4]|xss_clean');
		if($_POST['return_method'] == 'shop'){
			$this->validation->set_rules('return_recipient_zipcode[]', '우편번호','trim|required|numeric|max_length[7]|xss_clean');
			$this->validation->set_rules('return_recipient_address', '주소','trim|required|xss_clean');
			$this->validation->set_rules('return_recipient_address_detail', '상세주소','trim|required|xss_clean');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$data_order = $this->ordermodel->get_order($_POST['order_seq']);
		//if( !in_array($data_order['step'],array(55,60,65,70,75)) ){
		if( !in_array($data_order['step'],array('40','45','50','55','60','65','70','75')) ){
			openDialogAlert("[티켓상품] ".$this->arr_step[$data_order['step']]."에서는 반품신청을 하실 수 없습니다.",400,140,'parent');
			exit;
		}
		$order_total_ea = $this->ordermodel->get_order_total_ea($_POST['order_seq']);

		$cancel_total_ea = 0;
		foreach ($_POST['chk_ea'] as $k => $chk_ea ){
			if($chk_ea == 0 && $data_order['settleprice']> 0 ){
				openDialogAlert("환불금액이 ".get_currency_price(0,3)."인 경우에는 신청되지 않습니다.",400,140,'parent');
				exit;
			}

			//환불금액 0인데 결제금액 0이면 출고수량만큼 처리 @2017-07-21
			if($chk_ea == 0 && $data_order['settleprice'] < 1 ) {
				$_POST['chk_ea'][$k] = $_POST['chk_ea_all'][$k];
				$cancel_total_ea += $_POST['chk_ea_all'][$k];
			}else{
				$cancel_total_ea += $chk_ea;
			}
		}

		$export_codes = array();
		foreach($_POST['chk_export_code'] as $k => $chk_export_code){
			if(!in_array($chk_export_code,$export_codes)) $export_codes[] = $chk_export_code;
		}
		if($_POST['manual_refund_yn']=='y' && ($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $data_order['settleprice']==$_POST['cancel_total_price'] && $order_total_ea==$cancel_total_ea)
		{
			$pgCompany = $this->config_system['pgCompany'];

			// 카카오 페이의 PG사를 추출하기 위한 데이터 :: 2015-02-25 lwh
			if($data_order['pg']=='kakaopay'){
				$pglog_tmp				= $this->ordermodel->get_pg_log($_POST['order_seq']);
				$pg_log_data			= $pglog_tmp[0];
				$data_order['pg_log']	= $pg_log_data;
				$pgCompany				= $data_order['pg'];
			}

			/* PG 전체취소 start */
			$cancelFunction = "{$pgCompany}_cancel";
			$cancelResult = $this->refundmodel->$cancelFunction($data_order,$data_refund);

			if(!$cancelResult['success']){
				openDialogAlert("{$pgCompany} 결제 취소 실패<br /><font color=red>{$cancelResult['result_code']} : {$cancelResult['result_msg']}</font>",400,160,'parent','');
				exit;
			}
			/* PG 전체취소 end */
		}

		// 환불 등록
		if($_POST['bank']){
			$tmp = code_load('bankCode',$_POST['bank']);
			$bank = $tmp[0]['value'];
		}

		$account = "";
		if($_POST['account'][0]){
			$account = implode('-',$_POST['account']);
		}

		$_POST['refund_method'] = ($_POST['refund_method'])?$_POST['refund_method']:(($data_order['payment'])?$data_order['payment']:'bank');

		$realitems 		= $this->ordermodel->get_item($_POST['order_seq']);
		//주문상품의 실제 1건당 금액계산 @2014-11-27
		foreach($realitems as $key=>$item){
			if ( $item['goods_kind'] != 'coupon' ) continue;
			$reOption	= array();
			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
			$rowspan	= 0;
			if($options) foreach($options as $k => $data){
				// 매입
				$data['out_supply_price'] = $data['supply_price']*$data['ea'];
				// 정산
				$data['out_commission_price'] = $data['commission_price']*$data['ea'];

				// 상품금액
				$data['out_price'] = $data['price']*$data['ea'];

				// 할인
				$data['out_event_sale'] = $data['event_sale'];
				$data['out_multi_sale'] = $data['multi_sale'];
				$data['out_member_sale'] = $data['member_sale']*$data['ea'];
				$data['out_coupon_sale'] = ($data['download_seq'])?$data['coupon_sale']:0;
				$data['out_fblike_sale'] = $data['fblike_sale'];
				$data['out_mobile_sale'] = $data['mobile_sale'];
				$data['out_promotion_code_sale'] = $data['promotion_code_sale'];
				$data['out_referer_sale'] = $data['referer_sale'];

				// 할인 합계
				$data['out_tot_sale'] = $data['out_event_sale'];
				$data['out_tot_sale'] += $data['out_multi_sale'];
				$data['out_tot_sale'] += $data['out_member_sale'];
				$data['out_tot_sale'] += $data['out_coupon_sale'];
				$data['out_tot_sale'] += $data['out_fblike_sale'];
				$data['out_tot_sale'] += $data['out_mobile_sale'];
				$data['out_tot_sale'] += $data['out_promotion_code_sale'];
				$data['out_tot_sale'] += $data['out_referer_sale'];

				// 할인가격
				$data['out_sale_price'] = $data['out_price'] - $data['out_tot_sale'];
				$data['sale_price'] = $data['out_sale_price'] / $data['ea'];
				$order_one_option_sale_price[$data['item_option_seq']] = $data['sale_price'];

				// 예상적립
				$data['out_reserve'] = $data['reserve']*$data['ea'];
				$data['out_point'] = $data['point']*$data['ea'];

				###
				unset($data['inputs']);
				$data['inputs'] = $this->ordermodel->get_input_for_option($data['item_seq'],$data['item_option_seq']);

				$options[$k] = $data;

				$tot['ea']					+= $data['ea'];
				$tot['ready_ea']			+= $data['ready_ea'];
				$tot['step_complete']		+= $data['step_complete'];
				$tot['step25']				+= $data['step25'];
				$tot['step85']				+= $data['step85'];
				$tot['step45']				+= $data['step45'];
				$tot['step55']				+= $data['step55'];
				$tot['step65']				+= $data['step65'];
				$tot['step75']				+= $data['step75'];
				$tot['supply_price']		+= $data['out_supply_price'];
				$tot['commission_price']	+= $data['out_commission_price'];
				$tot['consumer_price']		+= $data['out_consumer_price'];
				$tot['price']				+= $data['out_price'];

				$tot['member_sale']			+= $data['out_member_sale'];
				$tot['coupon_sale']			+= $data['out_coupon_sale'];
				$tot['fblike_sale']			+= $data['out_fblike_sale'];
				$tot['mobile_sale']			+= $data['out_mobile_sale'];
				$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];
				$tot['referer_sale']		+= $data['out_referer_sale'];

				$tot['coupon_provider']		+= $data['coupon_provider'];
				$tot['promotion_provider']	+= $data['promotion_provider'];
				$tot['referer_provider']	+= $data['referer_provider'];

				$tot['reserve']				+= $data['out_reserve'];
				$tot['point']				+= $data['out_point'];
				$tot['real_stock']			+= $real_stock;
				$tot['stock']				+= $stock;

				$return_item = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq']);
				$able_return_ea += (int) $data['step75'] - (int) $return_item['ea'];

				$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);
				if($suboptions) foreach($suboptions as $k => $subdata){
					###
					$subdata['out_supply_price']		= $subdata['supply_price']*$subdata['ea'];
					$subdata['out_commission_price']	= $subdata['commission_price']*$subdata['ea'];
					$subdata['out_consumer_price']		= $subdata['consumer_price']*$subdata['ea'];
					$subdata['out_price']				= $subdata['price']*$subdata['ea'];

					// 할인
					$subdata['out_event_sale'] = $subdata['event_sale'];
					$subdata['out_multi_sale'] = $subdata['multi_sale'];
					$subdata['out_member_sale'] = $subdata['member_sale']*$data['ea'];
					$subdata['out_coupon_sale'] = ($subdata['download_seq'])?$subdata['coupon_sale']:0;
					$subdata['out_fblike_sale'] = $subdata['fblike_sale'];
					$subdata['out_mobile_sale'] = $subdata['mobile_sale'];
					$subdata['out_promotion_code_sale'] = $subdata['promotion_code_sale'];
					$subdata['out_referer_sale'] = $subdata['referer_sale'];

					// 할인 합계
					$subdata['out_tot_sale'] = $subdata['out_event_sale'];
					$subdata['out_tot_sale'] += $subdata['out_multi_sale'];
					$subdata['out_tot_sale'] += $subdata['out_member_sale'];
					$subdata['out_tot_sale'] += $subdata['out_coupon_sale'];
					$subdata['out_tot_sale'] += $subdata['out_fblike_sale'];
					$subdata['out_tot_sale'] += $subdata['out_mobile_sale'];
					$subdata['out_tot_sale'] += $subdata['out_promotion_code_sale'];
					$subdata['out_tot_sale'] += $subdata['out_referer_sale'];

					// 할인가격
					$subdata['out_sale_price'] = $subdata['out_price'] - $subdata['out_tot_sale'];
					$subdata['sale_price'] = $subdata['out_sale_price'] / $subdata['ea'];
					$order_one_option_sale_price[$data['item_option_seq']] += $subdata['sale_price'];

					$subdata['out_reserve']				= $subdata['reserve']*$subdata['ea'];
					$subdata['out_point']				= $subdata['point']*$subdata['ea'];
				}
			}
		}

		$items = array();
		$r_reservation_goods_seq = array();
		foreach($_POST['chk_seq'] as $k=>$v){
			$cancelquery = "select * from fm_order_item where item_seq=?";
			$cancelquery = $this->db->query($cancelquery,array($_POST['chk_item_seq'][$k]));
			$orditemData = $cancelquery->row_array();

			$items[$k]['item_seq']			= $_POST['chk_item_seq'][$k];
			$items[$k]['option_seq']			= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
			$items[$k]['suboption_seq']	= $_POST['chk_suboption_seq'][$k];
			$items[$k]['ea']						= $_POST['chk_ea'][$k];

			//티켓상품의 1개의 실제 결제금액 @2014-11-27
			$coupon_real_total_price = $order_one_option_sale_price[$items[$k]['option_seq']];

			if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){
				$mode = 'option';

				//티켓상품의 취소(환불) 가능여부::반품
				if ( $orditemData['goods_kind'] == 'coupon') {

					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();

					$export_itemquery = "select * from fm_goods_export_item where export_code=? limit 1";
					$export_itemquery = $this->db->query($export_itemquery,array($_POST['chk_export_code'][$k]));
					$export_item_Data = $export_itemquery->row_array();
					$export_item_Data['couponinfo'] = get_goods_coupon_view($_POST['chk_export_code'][$k]);

					$coupon_value					= 0;
					$socialcp_return_notuse		= 0;
					$coupon_refund_emoney = $coupon_remain_price = $coupon_deduction_price = 0;
					$coupon_remain_real_percent = $coupon_remain_real_price = 0;
					$coupon_remain_price = $coupon_deduction_price = 0;
					$socialcoupon++;

					if( date("Ymd")>substr(str_replace("-","",$optionData['social_end_date']),0,8)) {//유효기간 종료 후 잔여값어치합계

						if( $export_item_Data['coupon_value'] == $export_item_Data['coupon_remain_value'] ) {//값어치 전체미사용
							$socialcp_status = '8';
						}else{//값어치 일부사용
							$socialcp_status = '9';
						}
						/**
						//관리자 : 미사용티켓상품 환불대상 불가 허용
						if( $orditemData['socialcp_use_return'] == 1 ) {//미사용티켓상품 환불대상
						}else{//불가
						}
						**/
						if( order_socialcp_cancel_return($orditemData['socialcp_use_return'], $export_item_Data['coupon_value'], $export_item_Data['coupon_remain_value'], $optionData['social_start_date'], $optionData['social_end_date'] , $orditemData['socialcp_use_emoney_day'] ) === true ) {//미사용티켓상품 잔여값어치합계
							if ( $orditemData['socialcp_input_type'] == 'price' ) {//금액
								$coupon_remain_price_tmp			= (int) $export_item_Data['coupon_remain_value'];
								$coupon_deduction_price_tmp	= (int) $export_item_Data['coupon_value'];
							}else{//횟수
								$coupon_remain_price_tmp			= (int) (100 * ($optionData['coupon_input_one'] * $export_item_Data['coupon_remain_value']) / 100);
								$coupon_deduction_price_tmp	= (int) ($optionData['coupon_input_one'] * $export_item_Data['coupon_value']);
							}
							$coupon_remain_real_percent = 100 * ($coupon_remain_price_tmp / $coupon_deduction_price_tmp);//잔여값어치율

							//실제결제금액
							$coupon_remain_real_price			= (int) ($coupon_remain_real_percent * ($coupon_real_total_price) / 100);
							$coupon_remain_price			= (int) ($orditemData['socialcp_use_emoney_percent'] * ($coupon_remain_real_price) / 100);
							$coupon_deduction_price	= (int) ($coupon_real_total_price) - $coupon_remain_price;
							//$cancel_total_price  += $coupon_remain_price;//취소총금액

							$items[$k]['coupon_refund_type']		= 'price';
							$coupon_valid_over++;//유효기가긴지난경우
						}
					}else{//유효기간 이전

						if( $export_item_Data['coupon_value'] == $export_item_Data['coupon_remain_value'] ) {//값어치 전체미사용
							$socialcp_status = '6';
						}else{//값어치 일부사용
							$socialcp_status = '7';
						}

						$items[$k]['coupon_refund_type']		= 'price';

						if( $export_item_Data['coupon_remain_value'] >0 ) {//잔여값어치가 남아있을때에만
							/**
							//관리자 : 부분 사용한 티켓상품은 취소(환불) 허용
							if(  $export_item_Data['coupon_value'] != $export_item_Data['coupon_remain_value']  && $orditemData['socialcp_cancel_use_refund'] == '1' ) {
								//부분 사용한 티켓상품은 취소(환불) 불가 @2014-10-07
							}else{
							}
							**/
							list($export_item_Data['socialcp_refund_use'], $export_item_Data['socialcp_refund_cancel_percent']) = order_socialcp_cancel_refund(
								$_POST['order_seq'],
								$_POST['chk_item_seq'][$k],
								$data_order['deposit_date'],
								$optionData['social_start_date'],
								$optionData['social_end_date'],
								$orditemData['socialcp_cancel_payoption'],
								$orditemData['socialcp_cancel_payoption_percent']
							);

							if( $export_item_Data['coupon_value'] == $export_item_Data['coupon_remain_value'] ){//미사용
								//실제결제금액
								$coupon_remain_price			= (int) ($export_item_Data['socialcp_refund_cancel_percent'] * $coupon_real_total_price / 100);
								$coupon_deduction_price	= (int) $coupon_real_total_price - $coupon_remain_price;
								$coupon_remain_real_percent = "100";
								$coupon_remain_real_price = $coupon_real_total_price;
								$cancel_total_price  += $coupon_remain_price;//취소총금액
							}else{//사용
								if ( $orditemData['socialcp_input_type'] == 'price' ) {//금액
									$coupon_remain_price_tmp			= (int) $export_item_Data['coupon_remain_value'];
									$coupon_deduction_price_tmp	= (int) $export_item_Data['coupon_value'];
								}else{//횟수
									$coupon_remain_price_tmp			= (int) (100 * ($optionData['coupon_input_one'] * $export_item_Data['coupon_remain_value']) / 100);
									$coupon_deduction_price_tmp	= (int) ($optionData['coupon_input_one'] * $export_item_Data['coupon_value']);
								}
								$coupon_remain_real_percent = 100 * ($coupon_remain_price_tmp / $coupon_deduction_price_tmp);//잔여값어치율

								//실제결제금액
								$coupon_remain_real_price			= (int) ($coupon_remain_real_percent * ($coupon_real_total_price) / 100);

								$coupon_remain_price			= (int) ($export_item_Data['socialcp_refund_cancel_percent'] * ($coupon_remain_real_price) / 100);
								$coupon_deduction_price	= (int) ($coupon_remain_real_price) - $coupon_remain_price;
								//$cancel_total_price  += $coupon_remain_price;//취소총금액
							}
						}
					}

					//취소(환불) 로그쌓기
					$cancel_memo = socialcp_cancel_memo($export_item_Data, $coupon_remain_real_percent, $coupon_real_total_price, $coupon_remain_real_price, $coupon_remain_price, $coupon_deduction_price);

					$items[$k]['coupon_remain_price']			= $coupon_remain_price;//티켓상품 결제금액의 실제금액
					$items[$k]['coupon_deduction_price']		= $coupon_deduction_price;//티켓상품 결제금액의 조정금액
					$items[$k]['refund_goods_price']			= $coupon_remain_real_price;//티켓상품 환불금액
					$items[$k]['coupon_remain_real_percent']	= $coupon_remain_real_percent;//티켓상품 사용비율
					$items[$k]['coupon_real_value']				= $export_item_Data['coupon_value'];//티켓상품 기준금액OR횟수
					$items[$k]['coupon_remain_real_value']		= $export_item_Data['coupon_remain_value'];//티켓상품 환불금액
					$items[$k]['cancel_memo']					= $cancel_memo;//티켓상품 취소(환불) 상세내역

				}

				if($_POST['mode']!='exchange'){
					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_option_seq',$items[$k]['option_seq']);
					$this->db->update('fm_order_item_option');
				}

				/* 신용카드 자동취소 > 재고차감 start */
				if( ($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $data_order['settleprice']==$_POST['cancel_total_price'] && $order_total_ea==$cancel_total_ea)
				{
					//상품체크>청약철회/
					unset($cancel_goods);
					$cancel_goods = $this->goodsmodel->get_goods($orditemData['goods_seq']);
					//$orditemData['cancel_type']				= $cancel_goods['cancel_type'];
					//$orditemData['coupon_serial_type']		= $cancel_goods['coupon_serial_type'];
					if( $cancel_goods['coupon_serial_type'] == 'a' ) {
						// 출고량 업데이트를 위한 변수정의
						if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
							$r_reservation_goods_seq[] = $optionData['goods_seq'];
						}
					}
				}
			}else if($items[$k]['suboption_seq']){
				$mode = 'suboption';

				$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
				$query = $this->db->query($query,array($items[$k]['suboption_seq']));
				$optionData = $query->row_array();

				if($_POST['mode']!='exchange'){
					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
					$this->db->update('fm_order_item_suboption');
				}

				/* 신용카드 자동취소 > 재고차감 start */
				if( ($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $data_order['settleprice']==$_POST['cancel_total_price'] && $order_total_ea==$cancel_total_ea)
				{

					//상품체크
					unset($cancel_goods);
					$cancel_goods = $this->goodsmodel->get_goods($optionData['goods_seq']);
					//$optionData['cancel_type']				= $cancel_goods['cancel_type'];
					//$optionData['coupon_serial_type']		= $cancel_goods['coupon_serial_type'];
					if( $cancel_goods['coupon_serial_type'] == 'a' ) {
						// 출고량 업데이트를 위한 변수정의
						if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
							$r_reservation_goods_seq[] = $optionData['goods_seq'];
						}
					}
				}
			}
		}

		//$_POST['refund_method'] = ($coupon_valid_over)?'emoney':$_POST['refund_method'];//2014-10-13 사용안함

		if($_POST['mode']=='exchange'){
			$refund_code = '0';
			$return_type = 'exchange';
		}else{
			// 맞교환으로 인한 재주문을 반품신청시 최상위 주문번호 저장
			if($data_order['top_orign_order_seq'])
				$orgin_order_seq = $data_order['top_orign_order_seq'];
			else
				$orgin_order_seq = $_POST['order_seq'];

			// 구매확정 후 환불 여부 by hed #32095
			// 반품신청과 구매확정은 출고단위로 이루어지므로
			// 출고가 구매확정되었다면 동일출고건 내의 모든 반품신청은 구매확정 후 반품으로 처리된다.
			$chk_after_refund = $this->input->post('chk_after_refund');
			foreach($chk_after_refund as $v){
				if($v){
					$after_refund = '1';
				}
			}

			$data = array(
				'order_seq' => $orgin_order_seq,
				'bank_name' => ($bank)?$bank:'',
				'bank_depositor' => ($_POST['depositor'])?$_POST['depositor']:'',
				'coupon_refund_emoney' => $coupon_refund_emoney,
				'coupon_refund_price' => $coupon_remain_price,
				'bank_account' => ($account)?$account:'',
				'refund_reason' => '반품환불',
				'refund_type' => 'return',
				'regist_date' => date('Y-m-d H:i:s'),
				'manager_seq' => $manager_seq,
				'refund_method' => $_POST['refund_method']
			);
			$refund_code = $this->refundmodel->insert_refund($data,$items);

			/* 신용카드 자동취소 > 재고차감 start */
			if( ($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $data_order['settleprice']==$_POST['cancel_total_price'] && $order_total_ea==$cancel_total_ea)
			{
				// 출고예약량 업데이트
				foreach($r_reservation_goods_seq as $goods_seq){
					$this->goodsmodel->modify_reservation_real($goods_seq);
				}
			}

			$return_type = 'return';

			$logTitle	= "환불신청(".$refund_code.")";
			$logDetail	= "관리자 반품신청에 의한 환불신청이 접수되었습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($orgin_order_seq,'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams);
		}

		/**
		* 티켓상품 반품처리 start
		**/
			if($_POST['phone'][1] && $_POST['phone'][2]) $phone = implode('-',$_POST['phone']);
			if($_POST['cellphone'][1] && $_POST['cellphone'][2]) $cellphone = implode('-',$_POST['cellphone']);
			$zipcode = "";
			if($_POST['recipient_zipcode'][1]) $zipcode = implode('-',$_POST['recipient_zipcode']);
			else $zipcode = $_POST['recipient_zipcode'][0];

			//티켓상품 반품등록
			$insert_data['status'] 				= 'complete';//티켓상품 반품완료
			$insert_data['order_seq'] 		= $_POST['order_seq'];
			$insert_data['refund_code'] 	= $refund_code;
			$insert_data['return_type'] 	= $return_type;
			$insert_data['return_reason'] 	= $_POST['reason_detail'];
			$insert_data['cellphone'] 		= $cellphone;
			$insert_data['phone'] 			= (!empty($phone)) ? $phone : '';
			$insert_data['return_method'] 		= $_POST['return_method'];
			$insert_data['sender_zipcode'] 		= $zipcode;
			$insert_data['sender_address_type'] = $_POST['recipient_address_type'];
			$insert_data['sender_address'] 		= $_POST['recipient_address']?$_POST['recipient_address']:'';
			$insert_data['sender_address_street'] 	= $_POST['recipient_address_street'];
			$insert_data['sender_address_detail'] = $_POST['recipient_address_detail']?$_POST['recipient_address_detail']:'';
			$insert_data['regist_date'] 	= date('Y-m-d H:i:s');
			$insert_data['return_date'] = date('Y-m-d H:i:s');//티켓상품 반품완료
			$insert_data['important'] 		= 0;
			$insert_data['manager_seq'] 	= $manager_seq;
			$insert_data['shipping_price_depositor'] 	= $_POST['shipping_price_depositor'];
			$insert_data['shipping_price_bank_account'] = $_POST['shipping_price_bank_account'];

			$items = array();
			foreach($_POST['chk_seq'] as $k=>$v){
				$items[$k]['item_seq']		= $_POST['chk_item_seq'][$k];
				$items[$k]['option_seq']	= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
				$items[$k]['suboption_seq']	= $_POST['chk_suboption_seq'][$k];
				$items[$k]['ea']			= $_POST['chk_ea'][$k];
				$items[$k]['reason_code']	= $_POST['reason'][$k];
				$items[$k]['reason_desc']	= $_POST['reason_desc'][$k];
				$items[$k]['export_code']	= $_POST['chk_export_code'][$k];
				$items[$k]['partner_return']= true;
			}

			$return_code = $this->returnmodel->insert_return($insert_data,$items);
		/**
		* 티켓상품 반품처리 end
		**/

		/**
		* 티켓상품 배송완료 start
		**/
			$this->load->model('socialcpconfirmmodel');
			foreach($export_codes as $export_code){
				$data_export = $this->exportmodel->get_export($export_code);
				if(in_array($data_export['status'],array('40','45','50','55','60','65','70','75'))){
					unset($data_socialcp_confirm);
					$data_socialcp_confirm['order_seq']		= $data_export['order_seq'];
					$data_socialcp_confirm['export_seq']		= $data_export['export_seq'];
					$data_socialcp_confirm['manager_seq']	= $this->managerInfo['manager_seq'];
					$data_socialcp_confirm['doer']				=  $this->managerInfo['mname'];
					$this->socialcpconfirmmodel -> socialcp_confirm('admin',$socialcp_status,$export_code);//socialcp_status = 환불시 상태 6,7,8,9
					$this->socialcpconfirmmodel -> log_socialcp_confirm($data_socialcp_confirm);

					//티켓상품의 배송완료처리
					$this->exportmodel->socialcp_exec_complete_delivery($export_code, true, $coupon_remain_real_percent, $socialcp_confirm, "cancel");
				}
			}
		/**
		* 티켓상품 배송완료 end
		**/
		if($_POST['manual_refund_yn']=='y' && ($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $data_order['settleprice']==$cancel_total_price)
		{
			/* 신용카드 자동취소 @2014-10-13 */
			//debug_var("data_order['payment']:".$data_order['payment']."=data_order['settleprice']:".$data_order['settleprice']."=cancel_total_price:".$cancel_total_price);
			/**
			* 티켓상품 신용카드 자동취소 start
			**/
			$this->load->model('emoneymodel');
			$this->load->model('membermodel');
			$this->load->model('couponmodel');
			$this->load->model('promotionmodel');
			$this->load->helper('text');

			if($data_order['member_seq']){
				/* 마일리지 지급 */
				if($data_order['emoney_use']=='use' && $data_order['emoney'] > 0 )
				{
					$params = array(
						'gb'		=> 'plus',
						'type'		=> 'cancel',
						'emoney'	=> $data_order['emoney'],
						'ordno'		=> $data_order['order_seq'],
						'memo'		=> "[복원]주문환불({$refund_code})에 의한 마일리지 환원",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp246",$refund_code), // [복원] 주문환불(%s)에 의한 마일리지 환원
					);
					$this->membermodel->emoney_insert($params, $data_order['member_seq']);
					$this->ordermodel->set_emoney_use($data_order['order_seq'],'return');
				}

				/* 예치금 지급 */
				if($data_order['cash_use']=='use' && $data_order['cash'] > 0 )
				{
					$params = array(
						'gb'		=> 'plus',
						'type'		=> 'cancel',
						'cash'		=> $data_order['cash'],
						'ordno'		=> $data_order['order_seq'],
						'memo'		=> "[복원]주문환불({$refund_code})에 의한 예치금 환원",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp247",$refund_code), // [복원] 주문환불(%s)에 의한 예치금 환원
					);
					$this->membermodel->cash_insert($params, $data_order['member_seq']);
					$this->ordermodel->set_cash_use($data_order['order_seq'],'return');
				}

				/* 마일리지 회수 */
				if($_POST['return_reserve'] && $data_refund['refund_type']=='return'){
					$params = array(
						'gb'		=> 'minus',
						'type'		=> 'refund',
						'emoney'	=> $_POST['return_reserve'],
						'ordno'		=> $data_order['order_seq'],
						'memo'		=> "[차감] 주문환불({$data_order['order_seq']})에 의하여 배송완료시 지급된 마일리지 차감",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp258",$data_order['order_seq']), // [차감] 주문환불(%s)에 의하여 배송완료시 지급된 마일리지 차감
					);
					$this->membermodel->emoney_insert($params, $data_order['member_seq']);
				}

				/* 포인트 회수 */
				if($_POST['return_point'] && $data_refund['refund_type']=='return'){
					$params = array(
						'gb'		=> 'minus',
						'type'		=> 'refund',
						'point'		=> $_POST['return_point'],
						'ordno'		=> $data_order['order_seq'],
						'memo'		=> "[차감] 주문환불({$data_order['order_seq']})에 의하여 배송완료시 지급된 포인트 차감",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp259",$data_order['order_seq']), // [차감] 주문환불(%s)에 의하여 배송완료시 지급된 포인트 차감
					);
					$this->membermodel->point_insert($params, $data_order['member_seq']);
				}
			}

			$saveData = array(
				'adjust_use_coupon'		=> $data_order['coupon_sale'],
				'adjust_use_promotion'	=> $data_order['shipping_promotion_code_sale'],
				'adjust_use_emoney'		=> $data_order['emoney'],
				'adjust_use_cash'			=> $data_order['cash'],
				'adjust_use_enuri'			=> $data_order['enuri'],
				'refund_method'				=> 'card',
				'refund_price'					=> $data_order['settleprice'],
				'status'							=> 'complete',
				'cancel_type'					=> 'full',
				'refund_date'			=> date('Y-m-d H:i:s')
			);//status 환불완료처리
			$this->db->where('refund_code', $refund_code);
			$this->db->update("fm_order_refund",$saveData);

			/* 저장된 정보 로드 */
			$data_refund		= $this->refundmodel->get_refund($refund_code);
			$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
			$data_member		= $this->membermodel->get_member_data($data_order['member_seq']);

			// 추가옵션 관련 아이템 재배열
			$items_array	= array();
			if($data_refund_item)foreach($data_refund_item as $item){
				if( $item['goods_kind'] == 'coupon' ) {
					$refund_goods_coupon_ea++;
				}
				if($item['title1'])		$item['options_str']  = $item['title1'] .":".$item['option1'];
				if($item['title2'])		$item['options_str'] .= " / ".$item['title2'] .":".$item['option2'];
				if($item['title3'])		$item['options_str'] .= " / ".$item['title3'] .":".$item['option3'];
				if($item['title4'])		$item['options_str'] .= " / ".$item['title4'] .":".$item['option4'];

				if	($item['opt_type'] == 'sub'){
					$item['price']								= $item['price'] * $item['ea'];
					$item['sub_options']							= $item['options_str'];
					if	($first_option_seq)
						$items_array[$first_option_seq]['sub'][]		= $item;
					else
						$items_array[$item['option_seq']]['sub'][]		= $item;
				}else{
					$items_array[$item['option_seq']]['price']		+= $item['price'] * $row['ea'];
					$items_array[$item['option_seq']]['ea']			+= $item['ea'];
					$items_array[$item['option_seq']]['goods_name']	= $item['goods_name'];
					$items_array[$item['option_seq']]['options']	= $item['options_str'];
					$items_array[$item['option_seq']]['inputs']		= $this->ordermodel->get_input_for_option($item['item_seq'], $item['option_seq']);
					$items_array[$item['option_seq']]['image']		= $item['image'];
				}
				if	(!$first_option_seq)	$first_option_seq	= $item['option_seq'];
			}

			$order_itemArr = array();
			$order_itemArr = array_merge($order_itemArr,$data_order);
			$order_itemArr['order_seq'] = $data_order['order_seq'];
			$order_itemArr['mpayment'] = $data_order['mpayment'];
			$order_itemArr['deposit_date'] = $data_order['deposit_date'];
			$order_itemArr['pg_transaction_number'] = $data_order['pg_transaction_number'];

			/* 환불처리완료 안내메일 발송 */
			$params = array_merge($saveData,$data_refund);
			$params['refund_reason']		= htmlspecialchars($data_refund['refund_reason']);
			$params['refund_date']			= $saveData['refund_date'];
			$params['mstatus'] 				= $this->refundmodel->arr_refund_status[$_POST['status']];
			$params['refund_price']			= number_format($data_refund['refund_price']);
			$params['refund_emoney']		= number_format($data_refund['refund_emoney']);
			$params['mrefund_method']		= $this->arr_payment[$data_refund['refund_method']];
			$params['order']				= $order_itemArr;
			if($data_refund['refund_method']=='bank'){
				$params['mrefund_method']		.= " 환불";
			}elseif($data_refund['cancel_type']=='full'){
				$params['mrefund_method'] 		.= " 결제취소";
			}elseif($data_refund['cancel_type']=='partial'){
				$params['mrefund_method'] 		.= " 부분취소";
			}
			$params['items'] 			= $items_array;
			if( $data_order['order_email'] ) {
				$couponsms		 = ( $refund_goods_coupon_ea ) ? "coupon_":"";
				$smsemailtype = ($data_refund['refund_type']=='return') ? 'refund' : 'cancel';
				sendMail($data_order['order_email'], $couponsms.$smsemailtype, $data_member['userid'], $params);
			}

			// 주문이 환불완료 일경우 주문한 회원의 구매횟수 및 구매금액 업데이트
			if($data_order['member_seq']){
				$refund_price = $data_refund['refund_price'] + $data_refund['refund_emoney'];
				$this->membermodel->member_order($data_order['member_seq']);
				//주문건/주문금액 필드추가 및 실시간업데이트 @2013-06-19
				$this->membermodel->member_order_batch($data_order['member_seq']);
			}

			//이벤트 판매건/주문건/주문금액 @2013-11-15
			if($data_refund['refund_type'] == 'return' && $data_refund_item){
				foreach($data_refund_item as $item) {
					if( $item['event_seq'] ) {
						$this->eventmodel->event_order($item['event_seq']);
						$this->eventmodel->event_order_batch($item['event_seq']);
					}
				}
			}

			$this->db->where('refund_code', $refund_code);
			$this->db->update("fm_order_refund",$saveData);
			$this->load->model('accountmodel');
			$this->accountmodel->set_refund($refund_code,$saveData['refund_date']);

			/* 로그저장 */
			$logTitle = "환불완료(".$refund_code.")";
			$logDetail = "관리자가 환불완료처리를 하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($data_order['order_seq'],'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams);
			$data_return = $this->returnmodel->get_return_refund_code($refund_code);
			$data_return_item 	= $this->returnmodel->get_return_item($data_return['return_code']);
			if($data_refund['refund_type']=='return') {
				coupon_send_sms_refund($data_return_item[0]['export_code'],$data_order);
			}else{
				coupon_send_sms_cancel($data_return_item[0]['export_code'],$data_order);
			}
			/**
			 * 4-2 환불데이타를 이용한 통합정산테이블 생성 시작
			 * @
			 **/
			 $this->load->helper('accountall');
			 if(!$this->accountallmodel) $this->load->model('accountallmodel');
			 if(!$this->providermodel) $this->load->model('providermodel');
			 if(!$this->refundmodel)  $this->load->model('refundmodel');
			 if(!$this->returnmodel)  $this->load->model('returnmodel');

			//정산대상 수량업데이트
			 $this->accountallmodel->update_calculate_sales_ac_ea($data_order['order_seq'],$refund_code, 'refund', $data_refund_item);
			//정산확정 처리
			 $this->accountallmodel->insert_calculate_sales_order_refund($data_order['order_seq'], $refund_code, $data_refund['cancel_type'], $data_order, $data_refund, $data_refund_item);
			 //debug_var($this->db->queries);
			 //debug_var($this->db->query_times);
			 /**
			* 4-2 환불관련 정산개선 끝
			* step1->step2 순차로 진행되어야 합니다.
			* @
			 **/

			$callback = "
			parent.closeDialog('order_refund_layer');
			parent.document.location.reload();
			";

			$title="신용카드 결제취소가 완료되었습니다.";
			openDialogAlert($title,400,140,'parent',$callback);

			/**
			* 티켓상품 신용카드 자동취소 end
			**/
		}else{
			if($_POST['mode']=='exchange'){
				$title="맞교환 신청이 완료되었습니다.";
				$logTitle = "맞교환신청(".$return_code.")";
				$logDetail = "관리자가 맞교환신청을 하였습니다.";
			}else{
				$title="반품이 완료되었습니다.";
				$logTitle = "반품완료(".$return_code.")";
				$logDetail = "관리자가 반품완료을 하였습니다.";
			}

			$logParams	= array('return_code' => $return_code);
			$this->ordermodel->set_log($_POST['order_seq'],'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams);
		}

		$callback = "
		parent.closeDialog('order_return_layer');
		parent.document.location.reload();";
		openDialogAlert($title,400,140,'parent',$callback);

	}

	###
	public function batch_temps_order(){
		$now = date("Y-m-d H:i:s");
		foreach($_POST['seq'] as $order_seq){
			$this->db->where('order_seq', $order_seq);
			$this->db->update('fm_order', array('hidden'=>'Y','hidden_date'=>$now));
		}
		echo json_encode($result);
	}
	public function batch_temps_orders(){
		$this->load->model('personalinfomodel');

		$now = date("Y-m-d H:i:s");
		foreach($_POST['seq'] as $order_seq){
			$this->db->where('order_seq', $order_seq);
			$this->db->update('fm_order', array('hidden'=>'T','hidden_date'=>$now));

			// 휴면/탈퇴 회원 주문 조회에서 해당 주문번호 건 삭제
			$this->personalinfomodel->delete(['order_seq'=>$order_seq]);
		}
		echo json_encode($result);
	}


	public function bank_search_set(){
		config_save("bank_set" ,array('sprice'=>$_POST['sprice']));
		config_save("bank_set" ,array('eprice'=>$_POST['eprice']));
		$callback = "parent.document.location.reload();";
		openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
	}


	public function auto_deposit_update(){
		###
		$this->load->model('usedmodel');
		$result = $this->usedmodel->auto_desposit_check();
		echo json_encode($result);
	}

	public function auto_deposit_update_plus(){
		###
		$setType		= $_GET['setType'];
		$this->load->model('usedmodel');
		$result['cnt']	= $this->usedmodel->auto_desposit_check_plus($setType);
		return "[".json_encode($result)."]";
	}

	public function auto_deposit_update_term(){
		###
		$this->load->model('usedmodel');
		$this->usedmodel->auto_desposit_check_term();
		return "[".json_encode($result)."]";
	}

	public function _exec_reverse($order_seq){

		$npay_use		= npay_useck();
		$talkbuy_use	= talkbuy_useck();
		$data_order		= $this->ordermodel->get_order($order_seq);
		$source_step	= (string) $data_order['step'];

		# npay 주문건 주문되돌리기 불가
		if($npay_use && $data_order['pg'] == "npay"){
			return 'npay';
		}

		// 카카오톡구매 주문건 주문되돌리기 불가
		if ($talkbuy_use && $data_order['pg'] == 'talkbuy') {
			return 'talkbuy';
		}

		# 오픈마켓 연동 주문건 주문되돌리기 불가
		if($data_order['linkage_id'] == "connector"){
			return 'openmarket';
		}

		# 오프라인 연동 주문건 주문되돌리기 불가
		if($data_order['linkage_id'] == "pos"){
			return 'pos';
		}

		// 주문접수 상태일 경우
		if( $data_order['step'] <= 15 ){
			return false;
		}

		// 주문 무효가 아니고 결제 확인 이상인 경우
		if( $data_order['step'] != '95' && $data_order['step'] > 15 ){
			//부분 출고 준비는 상품 준비로 변경
			if($data_order['step'] == '40'){
				$target_step = $data_order['step'] - 5;
			}else{
				$target_step = $data_order['step'] - 10;
			}
			$mode = "normal";
		}else{
			$mode = "cancel";
			$target_step = 15;
		}

		$target_step = (string) $target_step;
		if( $data_order['step'] == 25 && $data_order['payment'] != 'bank' ){
			return false;
		}else{
			$return = $this->ordermodel->set_reverse_step($order_seq,$target_step,$arr,$mode);

			if($return){
				// 주문접수 되돌릴 경우 롤백데이터 생성 :: 2015-09-07 lwh
				if($data_order['step'] == 25 && $target_step == 15 && $data_order['accumul_mark'] == 'Y'){
					$this->load->model('statsmodel');
					$this->statsmodel->rollback_stat_refund($order_seq);
				}
				// 로그
				$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],'되돌리기 ('.$this->arr_step[$data_order['step']].' => '.$this->arr_step[$target_step].')','-');

				/**
				* 2-2 주문접수 되돌릴 경우 시작
				* 정산개선 - 통합정산데이타 생성
				* @
				**/
				if($data_order['step'] == 25 && $target_step == 15) {
					$this->load->helper('accountall');
					if(!$this->accountallmodel)	$this->load->model('accountallmodel');
					if(!$this->providermodel)	$this->load->model('providermodel');
					$this->accountallmodel->insert_calculate_sales_order_rollback($order_seq, $data_order);
					//debug_var($this->db->queries);
					//debug_var($this->db->query_times);
				}
				/**
				* 2-2 주문접수 되돌릴 경우 끝
				* 정산개선 - 통합정산데이타 생성
				* @
				**/

			}

			return $return;
		}
	}

	public function _order_reverse($order_seq){
		$orders		= $this->ordermodel->get_order($order_seq);

		if($orders['orign_order_seq']){
			$msg = "교환 주문 건은 되돌리기 할 수 없습니다.";
			openDialogAlert("교환 주문 건은 되돌리기 할 수 없습니다.",350,140,'parent','');
			exit;
		}else{

			// npay 주문건 확인
			$result		= $this->_exec_reverse($order_seq);
			if($result === "npay"){
				openDialogAlert("Npay 주문건은 되돌리기 할 수 없습니다.",400,140,'parent','');
				exit;
			} elseif ($result === "talkbuy") {
				openDialogAlert("카카오페이 구매 주문건은 되돌리기 할 수 없습니다.",400,140,'parent','');
				exit;
			}elseif($result === "openmarket"){
				openDialogAlert("오픈마켓 주문건은 주문상태 되돌리기가 불가합니다.",400,140,'parent','');
				exit;
			}elseif(!$result){
				openDialogAlert("잔여 마일리지가 없습니다.주문접수로 되돌릴 수 없습니다.",400,140,'parent','');
				exit;
			}
		}

		return 'OK';
	}

	public function order_reverse(){

		$order_seq	= $_GET['seq'];
		$result		= $this->_order_reverse($order_seq);

		$callback = "parent.document.location.reload();";
		openDialogAlert("주문상태가 변경되었습니다.",400,140,'parent',$callback);
	}

	// 주문상태 되돌리기 :: 0000-00-00 // 최종수정 2015-09-07 lwh
	public function batch_reverse()
	{
		$npay_order		= false;
		$talkbuy_order		= false;
		$openmarket_order		= false;
		$pos_order		= false;
		$auth = $this->authmodel->manager_limit_act('order_deposit');
		if(!$auth){
			$msg = '해당 기능 권한이 없습니다.';
			$res['false'] = false;
		}else{
			$res		= array();
			$msg		= '주문상태가 변경되었습니다.';
			$true_cnt	= 0;
			foreach($_POST['seq'] as $order_seq){

				$orders = $this->ordermodel->get_order($order_seq);

				if($orders['orign_order_seq']){
					$res['false'][]	= $order_seq;
					$msg = "교환 주문 건은 되돌리기 할 수 없습니다.";
				}else{
					$result = $this->_exec_reverse($order_seq);

					// 카드 또는 다른 이상으로 false가 떨어진 경우 :: 2015-09-07 lwh
					if($result === "npay"){
						$res['false'][]	= $order_seq;
						$npay_order		= true;
					}elseif($result === "talkbuy"){
						$res['false'][]	= $order_seq;
						$talkbuy_order = true;
					}elseif($result === "openmarket"){
						$res['false'][]	= $order_seq;
						$openmarket_order		= true;
					}elseif($result === "pos"){
						$res['false'][]	= $order_seq;
						$pos_order		= true;
					}else{
						if(!$result){
							$res['false'][]	= $order_seq;
						}else{
							$res['true'][]	= $order_seq;
						}
					}
				}
				$true_cnt++;

			}
		}
		if($res['false']){
			if($npay_order || $talkbuy_order){
				$msg = "간편결제 주문건 주문건은 되돌리기 할 수 없습니다.<br />".implode("<br />",$res['false']);
			}elseif($openmarket_order){
				$msg = "오픈마켓 주문건은 주문상태 되돌리기가 불가합니다.<br />".implode("<br />",$res['false']);
			}elseif($pos_order){
				$msg = "오프라인 주문건은 주문상태 되돌리기가 불가합니다.<br />".implode("<br />",$res['false']);
			}else{
				if($true_cnt == count($res['false'])){
					$msg = '주문상태가 변경될수 없는 주문입니다.';
				}else{
					$msg = '주문상태가 변경될수 없는 주문이 포함되어 있습니다.<br />'.implode("<br />",$res['false']);
				}
			}
		}

		if($_POST['mode'] == 'json'){
			$json['result']	= $res;
			$json['msg']	= $msg;

			echo json_encode($json);
			exit;
		}else{
			echo $msg;
			exit;
		}
	}

	// 상품준비
	public function _exec_goods_ready($order_seq)
	{
		$this->ordermodel->set_step35_ea($order_seq);
		$log_str = "관리자가 상품준비를 하였습니다.";
		$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],'상품준비',$log_str);
	}

	// 상품준비
	public function goods_ready(){
		$callback = "parent.location.replace(parent.location.href);";

		$order_seq			= trim($this->input->post('order_seq'));
		$options			= $this->input->post('optionSeq');
		$suboptions			= $this->input->post('suboptionSeq');

		$data_order = $this->ordermodel->get_order($order_seq);
		if(!isset($data_order['order_seq'])) return;

		$this->db->trans_begin();
		$rollback = false;
		if($options) {
			$addWhere	= array("step = '25'", "item_option_seq in (".implode(",",$options).")");
			$options	= $this->ordermodel->get_option_for_order($order_seq, $addWhere);
			if	($options)foreach($options as $o => $option){
				$this->ordermodel->set_step35_ea($order_seq, $option['item_option_seq'], 'option');
				$this->ordermodel->set_option_step($option['item_option_seq'], 'option');
			}
		}
		if($suboptions) {
			$addWhere	= array("step = '25'", "item_suboption_seq in (".implode(",",$suboptions).")");
			$suboptions	= $this->ordermodel->get_suboption_for_order($order_seq, $addWhere);
			if	($suboptions)foreach($suboptions as $o => $suboption){
				$this->ordermodel->set_step35_ea($order_seq, $suboption['item_suboption_seq'], 'suboption');
				$this->ordermodel->set_option_step($suboption['item_suboption_seq'], 'suboption');
			}
		}

		if( count($options) + count($suboptions) == 0 ) {
			$rollback = true;
		}

		$this->ordermodel->set_order_step($order_seq);
		$log_str = "관리자가 상품준비를 하였습니다.";
		$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],'상품준비',$log_str);

		if ($this->db->trans_status() === FALSE || $rollback == true)
		{
			$this->db->trans_rollback();
			openDialogAlert("해당 주문 상태를 다시 한 번 확인해주세요.",400,140,'parent',$callback);
		}
		else
		{
			$this->db->trans_commit();
			openDialogAlert("해당 상품의 상태가 상품준비로 변경 되었습니다.",400,140,'parent',$callback);
		}
	}

	// 일괄 상품 준비
	public function batch_goods_ready(){

		$msg = '';
		// O2O 주문 상품 준비 불가 처리
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_order_process_batch_goods_ready($_POST['seq'], $msg);

		$nomatch_order_seq = $npay_order = array();
		$addWhere	= array("step = '25'");
		foreach($_POST['seq'] as $order_seq){
			$this->db->trans_begin();
			$rollback = false;

			$options	= $this->ordermodel->get_option_for_order($order_seq, $addWhere);
			if	($options)foreach($options as $o => $opt){
				$this->ordermodel->set_step35_ea($order_seq, $opt['item_option_seq'], 'option');
				$this->ordermodel->set_option_step($opt['item_option_seq'], 'option');
			}
			$suboptions	= $this->ordermodel->get_suboption_for_order($order_seq, $addWhere);
			if	($suboptions)foreach($suboptions as $s => $sub){
				$this->ordermodel->set_step35_ea($order_seq, $sub['item_suboption_seq'], 'suboption');
				$this->ordermodel->set_option_step($sub['item_suboption_seq'], 'suboption');
			}

			// 변경한 옵션(추가옵션) 하나도 없었다면 rollback 처리함
			if( count($options) + count($suboptions) == 0 ) {
				$rollback = true;
			}

			$this->ordermodel->set_order_step($order_seq);
			$log_str = "관리자가 상품준비를 하였습니다.";
			$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],'상품준비',$log_str);

			if ($this->db->trans_status() === FALSE || $rollback == true)
			{
				$not_goods_ready[] = $order_seq;
				$this->db->trans_rollback();
			}
			else
			{
				$this->db->trans_commit();
			}
		}

		if(count($not_goods_ready) > 0 ){
			$msg = "<br/>아래 주문 건은 다시 한 번 주문 상태를 확인해주세요.";
			foreach($not_goods_ready as $order_seq) {
				$msg .= "<br/>".$order_seq;
			}
		}

		echo "선택된 주문건의 결제확인 주문수량이 → 상품준비로 변경되었습니다.".$msg;
	}

	/*
	상품 입력옵션 첨부파일 다운로드
	*/
	public function filedown(){

		$file 		= $this->input->get('file');
		if(!$file){
			openDialogAlert("다운로드 받을 파일이 없습니다.",400,140,'parent');
			exit;
		}
		$path 		= ROOTPATH."data/order/".$file;
		get_file_down($path, $file);
	}


	//회원검색 전체인경우
	public function download_member_search_all()
	{

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('member_view');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->load->model('membermodel');
		### SEARCH
		$sc = $_POST;
		$sc['search_text']		= ($sc['search_text'] == '이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소') ? '':$sc['search_text'];
		$sc['orderby']		= 'A.member_seq';
		### MEMBER
		$i=0;
		$data = $this->membermodel->popup_member_list($sc);
		foreach($data['result'] as $datarow){
			//$download_coupons = $this->couponmodel->get_admin_download($datarow['member_seq'], $_POST['no']);
			if(!$download_coupons) {
				$searchallmember[$i]['user_name'] = $datarow['user_name'];
				$searchallmember[$i]['userid']			 = $datarow['userid'];
				$searchallmember[$i]['member_seq']			 = $datarow['member_seq'];
				$i++;
			}
		}

		$result = array('searchallmember'=>$searchallmember,'totalcnt'=>$i);
		echo json_encode($result);
		exit;
	}

	public function print_setting(){
		config_save("order" ,array('orderPrintOrderBarcode'=>$_POST['orderPrintOrderBarcode']));
		config_save("order" ,array('orderPrintGoodsCode'=>$_POST['orderPrintGoodsCode']));
		config_save("order" ,array('orderPrintGoodsBarcode'=>$_POST['orderPrintGoodsBarcode']));
		$callback = "parent.closeDialog('print_setting_dialog')";
		openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
	}

	// 상품 매칭 처리
	public function order_goods_matching(){
		$order_item_seq = $this->input->post('order_item_seq');
		$goods_seq = $this->input->post('goods_seq');

		if($this->db->query("update fm_order_item set goods_seq=? where item_seq=?",array($goods_seq,$order_item_seq))){
			$result = array('success'=>'1');
			echo json_encode($result);
			exit;
		}
	}

	// 상품 옵션매칭 처리
	public function modify_order_item_option(){

		$item_option_seq = $_POST['item_option_seq'];
		$goods_option_seq = $_POST['goods_option_seq'];

		if(!$item_option_seq || !$goods_option_seq) exit;

		$query = $this->db->query("select * from fm_goods_option where option_seq=?",$goods_option_seq);
		$data = $query->row_array();

		if($data){

			$option_title = explode(",",$data['option_title']);

			$setData = array();
			foreach($option_title as $k=>$title){
				$setData[] = "title".($k+1)."='{$title}'";
				$setData[] = "option".($k+1)."='".$data['option'.($k+1)]."'";
			}

			if($setData){
				$this->db->query("update fm_order_item_option set
				".implode(",",$setData)."
				where item_option_seq=?
				",$item_option_seq);
			}

			$result = array('success'=>'1');
			echo json_encode($result);
			exit;
		}
	}

	// 오픈마켓 주문수집
	public function openmarket_order_receive(){

		$this->load->model('openmarketmodel');
		$arr_order_seq = $this->openmarketmodel->exec_order_receive($_GET['mall_code']);

		openDialogAlert(number_format(count($arr_order_seq))."건의 주문건이 수집되었습니다.",400,140,'parent',"parent.location.reload();");
	}

	/*
	public function goods_matching()
	{
		$goods_seq = $_POST['matchingGoods'][0];
		$item_seq =  $_POST['item_seq'];

		if( $item_seq && $goods_seq ){
			$this->ordermodel->update_item($goods_seq,$item_seq);
		}

		$callback = "parent.closeDialog('goods_matching_dialog');parent.location.reload();";

		openDialogAlert( "주문 상품을 매칭하였습니다.",400,140,'parent',$callback);
	}

	public function goods_option_matching()
	{
		$item_option_seq = $_POST['item_option_seq'];
		$export_option = $_POST['export_option'];
		$item_suboption_seq = $_POST['item_suboption_seq'];
		$title_suboption = $_POST['title_suboption'];
		$export_suboption = $_POST['export_suboption'];

		if( $item_option_seq ){
			$this->ordermodel->update_option($item_option_seq,$export_option);
		}

		if( $item_suboption_seq ){
			foreach( $export_suboption as $key_suboption => $suboption ){
				if( $suboption ){
					$title = $title_suboption[$key_suboption];
					$this->ordermodel->update_suboption($item_suboption_seq,$title,$suboption);
				}
			}
		}

		$callback = "parent.closeDialog('goods_matching_dialog');parent.location.reload();";

		openDialogAlert( "주문 옵션을 매칭하였습니다.",400,140,'parent',$callback);
	}
	*/

	public function excel_upload_check()
	{
		if( !$_FILES['excel_file']['tmp_name'] ){
			echo("<script>parent.loadingStop();</script>");
			openDialogAlert( "파일을 업로드 하세요.",400,140,'parent','');
			exit;
		}

		if( !preg_match('/csv/',$_FILES['excel_file']['name']) ){
			echo("<script>parent.loadingStop();</script>");
			openDialogAlert(".csv 파일을 업로드해 주세요",400,140,'parent','');
			exit;
		}

		$temp_file = "data/tmp/excel_export_tmp_".time().rand(0,9).".csv";
		$fc = mb_convert_encoding(file_get_contents($_FILES['excel_file']['tmp_name']),'UTF-8','EUC-KR');
		file_put_contents($temp_file,$fc);
		$row_num = 0;
		setlocale(LC_CTYPE, 'ko_KR.utf8');
		if (($handle = fopen($temp_file, "r")) !== FALSE) {
			while (($data = fgetcsv($handle)) !== FALSE) {
				$num = count($data);
				for ($c=0; $c < $num; $c++) {
					$excel_data[$row_num][] = trim($data[$c]);
				}
				$row_num++;
			}
			fclose($handle);
		}
		unlink($temp_file);

		$excel_key = 0;
		foreach($excel_data as $data){
			if( $excel_key == 0 ){
				$excel_data_filter[] = $data;
				$form_cnt = count($data);
			}else{
				if( $form_cnt ==  count($data)){
					$excel_data_filter[] = $data;
				}
			}
			$excel_key++;
		}

		$setting_type = "ORDER";
		foreach($excel_data_filter[0] as $data) 	if($data == '출고상품번호') $setting_type = "ITEM";

		$this->load->model('excelmodel');
		$this->excelmodel->setting_type = $setting_type;
		$this->excelmodel->set_cell();

		$excel_tmp = $this->excelmodel->excel_upload($excel_data_filter,'check');
		$excel = $excel_tmp[0];

		$excel['check_mode'] = 'check';
		$excel['input_mode']  = 'excel';

		// 물류관리 창고재고 추출
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	($this->scm_cfg['use'] == 'Y' && $_POST['export_warehouse'] > 0){
			$this->load->model('scmmodel');
			if( $excel['optioninfo'] ) foreach($excel['optioninfo'] as $arr1){
				if($arr1['option']) foreach($arr1['option'] as $optioninfo){
					$arr_optioninfo[] = $optioninfo;
				}
			}
			if( $arr_optioninfo ){
				$scmData	= $this->scmmodel->get_warehouse_stock($_POST['export_warehouse'], 'optioninfo','',$arr_optioninfo);
			}
			if	($excel['optioninfo']) foreach($excel['optioninfo'] as $shipping_seq => $arr1){
				if	($arr1['option']) foreach($arr1['option'] as $item_option_seq => $optioninfo){
					$scminfo	= $scmData[$optioninfo];
					$excel['whSupplyPrice'][$shipping_seq]['option'][$item_option_seq]	= $scminfo['supply_price'];
					$excel['stock'][$shipping_seq]['option'][$item_option_seq]	= $scminfo['ea'];
				}
				if	($arr1['suboption']) foreach($arr1['suboption'] as $item_suboption_seq => $optioninfo){
					$scminfo	= $scmData[$optioninfo];
					$excel['whSupplyPrice'][$shipping_seq]['suboption'][$item_suboption_seq]	= $scminfo['supply_price'];
					$excel['stock'][$shipping_seq]['suboption'][$item_suboption_seq]			= $scminfo['ea'];
				}
			}
			$excel['scm_wh']	= $_POST['export_warehouse'];
		}

		$this->order_export_exec($excel,$excel_data,$excel_tmp[1]);

	}

	public function excel_upload(){

		if( !$_FILES['excel_file']['tmp_name'] ){
			echo("<script>parent.loadingStop();</script>");
			openDialogAlert( "파일을 업로드 하세요.",400,140,'parent','');
			exit;
		}

		if( !preg_match('/csv/',$_FILES['excel_file']['name']) ){
			echo("<script>parent.loadingStop();</script>");
			openDialogAlert(".csv 파일을 업로드해 주세요",400,140,'parent','');
			exit;
		}

		$temp_file = "data/tmp/excel_export_tmp_".time().rand(0,9).".csv";
		$fc = mb_convert_encoding(file_get_contents($_FILES['excel_file']['tmp_name']),'UTF-8','EUC-KR');
		file_put_contents($temp_file,$fc);
		$row_num = 0;
		setlocale(LC_CTYPE, 'ko_KR.utf8');
		if (($handle = fopen($temp_file, "r")) !== FALSE) {
			while (($data = fgetcsv($handle)) !== FALSE) {
				$num = count($data);
				for ($c=0; $c < $num; $c++) {
					$excel_data[$row_num][] = $data[$c];
				}
				$row_num++;
			}
			fclose($handle);
		}
		unlink($temp_file);
		$this->load->model('excelmodel');
		$excel_tmp = $this->excelmodel->excel_upload($excel_data,'check');
		$excel = $excel_tmp[0];
		$excel['stockable'] 			= $_POST['stockable'];
		$excel['export_step'] 		= $_POST['export_step'];
		$excel['ticket_stockable'] 	= $_POST['ticket_stockable'];
		$excel['ticket_step'] 		= $_POST['ticket_step'];
		$excel['export_date'] 		= $_POST['export_date'];
		$excel['input_mode']  = 'excel';
		// 물류관리 창고재고 추출
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	($this->scm_cfg['use'] == 'Y' && $_POST['export_warehouse'] > 0){
			$this->load->model('scmmodel');
			if( $excel['optioninfo'] ) foreach($excel['optioninfo'] as $arr1){
				if($arr1['option']) foreach($arr1['option'] as $optioninfo){
					$arr_optioninfo[] = $optioninfo;
				}
			}
			if( $arr_optioninfo ){
				$scmData	= $this->scmmodel->get_warehouse_stock($_POST['export_warehouse'], 'optioninfo','',$arr_optioninfo);
			}
			if	($excel['optioninfo']) foreach($excel['optioninfo'] as $shipping_seq => $arr1){
				if	($arr1['option']) foreach($arr1['option'] as $item_option_seq => $optioninfo){
					$scminfo	= $scmData[$optioninfo];
					$excel['whSupplyPrice'][$shipping_seq]['option'][$item_option_seq]	= $scminfo['supply_price'];
					$excel['stock'][$shipping_seq]['option'][$item_option_seq]	= $scminfo['ea'];
				}
				if	($arr1['suboption']) foreach($arr1['suboption'] as $item_suboption_seq => $optioninfo){
					$scminfo	= $scmData[$optioninfo];
					$excel['whSupplyPrice'][$shipping_seq]['suboption'][$item_suboption_seq]	= $scminfo['supply_price'];
					$excel['stock'][$shipping_seq]['suboption'][$item_suboption_seq]			= $scminfo['ea'];
				}
			}
			$excel['scm_wh']	= $_POST['export_warehouse'];
		}
		$this->order_export_exec($excel,$excel_data,$excel_tmp[1]);
	}

	//  묶음배송 출고
	public function bundle_order_export_popup(){

		//POST 치환
		$_POST['check_mode']		= $_POST['bundle_check_mode'];
		$_POST['bundle_mode']		= 'bundle';
		$_POST['mode']				= 'goods';
		$_POST['export_date']		= $_POST['bundle_export_date'];
		$_POST['stockable']			= $_POST['bundle_stockable'];
		$_POST['export_step']		= $_POST['bundle_export_step'];

		foreach((array)$_POST['check_shipping_seq'] as $row){
			$_POST['export_shipping_method'][$row]	= $_POST['bundle_export_shipping_method'];
			$_POST['delivery_company'][$row]		= $_POST['bundle_delivery_company'];
			$_POST['delivery_number'][$row]			= $_POST['bundle_delivery_number'];
		}

		$this->order_export_popup();
	}

	// 출고
	public function order_export_popup()
	{
		if (serviceLimit('H_SC')) {
			$scm_cfg = config_load('scm');
			if (count($scm_cfg['use_warehouse']) == 0) {
				openDialogAlert('쇼핑몰 창고정보가 없습니다.<br/>재고기초 > <span class="highlight-link">쇼핑몰창고</span> 에서 먼저 사용창고를 지정해주세요.', 500, 170, 'parent', 'parent.opener.location.reload();parent.window.close();');
				exit();
			}
		}

		$aPost = $result_param = $this->input->post();

		# 택배가 아닐경우 택배사 코드/송장번호 초기화 :: 2016-10-06 lwh
		foreach ($result_param['export_shipping_method'] as $shipping_seq => $shipping_method) {
			if (! in_array($shipping_method, array(
				"delivery"
			))) {
				$result_param['delivery_company'][$shipping_seq] = "";
				$result_param['delivery_number'][$shipping_seq] = "";
			}
		}

		if ($result_param['each_shipping_seq']) {
			$request_ea = $result_param['request_ea'][$result_param['each_shipping_seq']];

			// 패키지상품추가
			foreach ($result_param['package_request_ea'][$result_param['each_shipping_seq']]['option'] as $item_option_seq => $data_package) {
				$unit_request_ea = 0;
				foreach ($data_package as $package_option_seq => $package_ea) {
					$request_ea['option'][$item_option_seq] = $package_ea;
				}
			}
			foreach ($result_param['package_request_ea'][$result_param['each_shipping_seq']]['suboption'] as $item_suboption_seq => $data_package) {
				$unit_request_ea = 0;
				foreach ($data_package as $package_suboption_seq => $package_ea) {
					$request_ea['suboption'][$item_suboption_seq] = $package_ea;
				}
			}

			if ($result_param['each_shipping_method'] == 'coupon') {
				foreach (array_keys($request_ea['option']) as $option_key) {
					if ($option_key != $result_param['each_item_option_seq']) {
						unset($request_ea['option'][$option_key], $result_param['shipping_goods_kind'][$result_param['each_shipping_seq']]['option'][$option_key]);
					}
				}
			}
			unset($result_param['request_ea']);
			$result_param['request_ea'][$result_param['each_shipping_seq']] = $request_ea;
		} else {
			unset($result_param['request_ea']);
			foreach ($result_param['check_shipping_seq'] as $check_shipping_group_seq) {
				$request_ea = $aPost['request_ea'][$check_shipping_group_seq];

				// 패키지상품추가
				foreach ($result_param['package_request_ea'][$check_shipping_group_seq]['option'] as $item_option_seq => $data_package) {
					$unit_request_ea = 0;
					foreach ($data_package as $package_option_seq => $package_ea) {
						$unit_ea = $result_param['unit_ea']['option'][$item_option_seq][$package_option_seq];
						if (! $unit_request_ea) {
							$unit_request_ea = $package_ea / $unit_ea;
						}
						$request_ea['option'][$item_option_seq] = $unit_request_ea;
					}
				}
				foreach ($result_param['package_request_ea'][$check_shipping_group_seq]['suboption'] as $item_suboption_seq => $data_package) {
					$unit_request_ea = 0;
					foreach ($data_package as $package_suboption_seq => $package_ea) {
						$unit_ea = $result_param['unit_ea']['suboption'][$item_suboption_seq][$package_suboption_seq];
						if (! $unit_request_ea) {
							$unit_request_ea = $package_ea / $unit_ea;
						}
						$request_ea['suboption'][$item_suboption_seq] = $unit_request_ea;
					}
				}

				$result_param['request_ea'][$check_shipping_group_seq] = $request_ea;
			}
		}

		$this->order_export_exec($result_param);
	}

	// 출고 처리
	public function order_export_exec($export_param,$excel_data='',$param_shipping='')
	{
		/**
		* 대량출고처리를 위해 초기화 하며 검수시 주석처리하여 로그를 확인해 주세요.
		* $this->db->queries $this->db->query_times 초기화
		* @2016-12-06
		**/
		$dbqueriesunset = true;
		$auth = $this->authmodel->manager_limit_act('order_goods_export');
		if(!$auth){
			openDialogAlert( '관리자 권한이 없습니다.' ,300,150,'parent','parent.opener.location.reload();parent.window.close();');
			exit;
		}

		$this->load->model('order2exportmodel');
		$this->load->model('giftmodel');
		$this->load->library('exportlibrary');

		$npay_use = npay_useck();	//Npay v2.1 사용여부
		$talkbuy_use = talkbuy_useck();	//카카오톡구매 사용여부

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	($this->scm_cfg['use'] == 'Y'){
			$cfg['scm_use']			= 'Y';
			$cfg['scm_wh']			= $export_param['scm_wh'];

			if	($export_param['optioninfo']) foreach($export_param['optioninfo'] as $k => $optioninfo){
				if	($optioninfo['option']) foreach($optioninfo['option'] as $option_seq => $optstr){
					$arr_scmoptioninfo[$k]['option'][$option_seq]['info']	= $optstr;
					$arr_scmoptioninfo[$k]['option'][$option_seq]['stock']	= $export_param['stock'][$k]['option'][$option_seq];
					$arr_scmoptioninfo[$k]['option'][$option_seq]['autowh']	= $export_param['autoWh'][$k]['option'][$option_seq];
					$arr_scmoptioninfo[$k]['option'][$option_seq]['code']	= $export_param['goodscode'][$k]['option'][$option_seq];
					$arr_scmoptioninfo[$k]['option'][$option_seq]['price']	= $export_param['whSupplyPrice'][$k]['option'][$option_seq];
				}
				if	($optioninfo['suboption']) foreach($optioninfo['suboption'] as $suboption_seq => $substr){
					$arr_scmoptioninfo[$k]['suboption'][$suboption_seq]['info']		= $substr;
					$arr_scmoptioninfo[$k]['suboption'][$suboption_seq]['stock']	= $export_param['stock'][$k]['suboption'][$suboption_seq];
					$arr_scmoptioninfo[$k]['suboption'][$suboption_seq]['autowh']	= $export_param['autoWh'][$k]['suboption'][$suboption_seq];
					$arr_scmoptioninfo[$k]['suboption'][$suboption_seq]['code']		= $export_param['goodscode'][$k]['suboption'][$suboption_seq];
					$arr_scmoptioninfo[$k]['suboption'][$suboption_seq]['price']	= $export_param['whSupplyPrice'][$k]['suboption'][$suboption_seq];
				}
			}
		}

		$cfg['wh_seq'] 				= $export_param['scm_wh'];
		$cfg['stockable'] 			= $export_param['stockable'];
		$cfg['step'] 				= $export_param['export_step'];
		$cfg['ticket_stockable'] 	= $export_param['ticket_stockable'];
		$cfg['ticket_step'] 		= $export_param['ticket_step'];
		$cfg['export_date'] 		= $export_param['export_date'];
		$cfg['bundle_mode'] 		= ($export_param['bundle_mode'] == 'bundle') ? 'bundle' : '';

		$arr_order_seq 				= $export_param['order_seq'];
		$arr_request_ea  			= $export_param['request_ea'];
		$arr_shipping_goods_kind	= $export_param['shipping_goods_kind'];
		$arr_delivery_company		= $export_param['delivery_company'];
		$arr_delivery_number		= $export_param['delivery_number'];
		$arr_npay_flag_release		= $export_param['npay_flag_release'];		//npay 보류 사유
		$arr_talkbuy_flag_release	= $export_param['talkbuy_flag_release'];		//카카오페이 구매 보류 사유
		$arr_direct_export_complete = $export_param['direct_export_complete'];		// 자동 출고완료 여부, 1:자동출고완료 or false

		// 배송 출고 데이터 추가 작업 :: 2016-10-06 lwh
		$arr_export_data['group']		= $export_param['export_shipping_group'];
		$arr_export_data['method']		= $export_param['export_shipping_method'];
		$arr_export_data['set_name']	= $export_param['export_shipping_set_name'];
		$arr_export_data['scm_type']	= $export_param['export_store_scm_type'];
		$arr_export_data['address_seq'] = $export_param['export_address_seq'];

		$tmp_export_error		= array();		//출고에러
		$tmp_export_error_msg	= array();		//출고에러메세지
		$tmp_export_request		= array();		//출고요청
		$tmp_export_success		= array();		//출고성공

		$params_order_export = array(
			'cfg'	=> $cfg,
			'arr_order_seq'	=> $arr_order_seq,
			'arr_request_ea'	=> $arr_request_ea,
			'arr_shipping_goods_kind'	=> $arr_shipping_goods_kind,
			'arr_delivery_company'	=> $arr_delivery_company,
			'arr_delivery_number'	=> $arr_delivery_number,
			'arr_export_data'	=> $arr_export_data,
			'param_shipping'	=> $param_shipping,
			'arr_scmoptioninfo'	=> $arr_scmoptioninfo,
			'arr_npay_flag_release'	=> $arr_npay_flag_release,
			'arr_talkbuy_flag_release'	=> $arr_talkbuy_flag_release,
			'arr_direct_export_complete' => $arr_direct_export_complete
		);
		$result_check = $this->order2exportmodel->order_export($params_order_export);


		# 실패
		if($result_check[1]){
			foreach($result_check[1] as $data){
				$tmp_export_error[$data['step']][$data['export_item_seq']]	= $data['order_seq']." : ".$data['msg'];
				$tmp_export_error_msg[$data['step']][]						= $data['msg'];
				$tmp_export_request[$data['step']][$data['shipping_seq']]	= true;
			}
			//출고 실패사유 노출
			if($tmp_export_error_msg[45]){
				$err_msg_45 = $tmp_export_error_msg[45][0];
				if(count($tmp_export_error_msg[45])>1){
					$err_msg_45 .= " 외 ".(count($tmp_export_error_msg[45])-1)."건";
				}
			}
			if($tmp_export_error_msg[55]){
				$err_msg_55 = $tmp_export_error_msg[55][0];
				if(count($tmp_export_error_msg[55])>1){
					$err_msg_55 .= " 외 ".(count($tmp_export_error_msg[55])-1)."건";
				}
			}
		}

		# 성공
		foreach($result_check[2] as $data){
			if( array_sum($data['items']['ea'] ) >0 ){
				$tmp_export_request[$data['status']][$data['shipping_seq']] = true;
				$tmp_export_success[$data['status']][$data['shipping_seq']] = true;
			}
		}

		if($export_param['check_mode'] == 'check'){
			//번들 배송인 경우 1건으로 처리
			$bundle_mode		= '';
			if($_POST['bundle_mode'] == 'bundle'){
				$bundle_mode	= 'bundle';

				$arrayKeys		= array_keys($tmp_export_success['55']);
				if($arrayKeys > 1){
					$tmp_array	= $tmp_export_success['55'][$arrayKeys[0]];
					unset($tmp_export_success['55']);
					$tmp_export_success['55'][$arrayKeys[0]]	= $tmp_array;
				}

				$arrayKeys		= array_keys($tmp_export_error['55']);
				if($arrayKeys > 1){
					$tmp_array	= $tmp_export_error['55'][$arrayKeys[0]];
					unset($tmp_export_error['55']);
					$tmp_export_error['55'][$arrayKeys[0]]	= $tmp_array;
				}


				$arrayKeys		= array_keys($tmp_export_success['45']);
				if($arrayKeys > 1){
					$tmp_array	= $tmp_export_success['45'][$arrayKeys[0]];
					unset($tmp_export_success['45']);
					$tmp_export_success['45'][$arrayKeys[0]]	= $tmp_array;
				}

				$arrayKeys		= array_keys($tmp_export_error['45']);
				if($arrayKeys > 1){
					$tmp_array	= $tmp_export_error['45'][$arrayKeys[0]];
					unset($tmp_export_error['45']);
					$tmp_export_error['45'][$arrayKeys[0]]	= $tmp_array;
				}
			}

			$msg_height = 300;

			$msg = "<span class=\'fx12 left \'><div class=\'ml25\'><strong>예상 처리 결과는 아래와 같습니다.</strong></div>";
			$msg .= "<div class=\'left mt10 ml25\'>▶ 출고준비 ".number_format(count($tmp_export_success['45']) + count($tmp_export_error['45']))."건 요청 → 성공 ".number_format(count($tmp_export_success['45']))."건";
			$msg .= " , 실패".number_format(count($tmp_export_error['45']))."건 예상</div>";

			//출고 실패사유 노출
			if($tmp_export_error_msg[45]){
				$msg .= "<div class=\'left ml30\'><span class=\'red\'>┖ 실패사유 : ".$err_msg_45."</span></div>";
				$msg_height += 30;
			}

			$msg .= "<div class=\'left ml25 mt5\'>▶ 출고완료  ".number_format(count($tmp_export_success['55'])+count($tmp_export_error['55']))."건 요청 → 성공 ".number_format(count($tmp_export_success['55']))."건";
			$msg .= " , 실패".number_format(count($tmp_export_error['55']))."건 예상</div>";

			if	($this->scm_cfg['use'] == 'Y'){
				$msg .= "※출고완료 시 {$this->scm_cfg['use_warehouse'][$export_param['scm_wh']]}의 재고가 차감됩니다.";
			}
			$msg .= "<br/>";

			//출고 실패사유 노출
			if($tmp_export_error_msg[55]){
				$msg .= "<div class=\'left ml30\'><span class=\'red\'>┖ 실패사유 : ".$err_msg_55."</span></div>";
				$msg_height += 30;
			}
			$msg .= "</span><br/>";

			if($export_param['input_mode'] == 'excel'){
				echo("
				<script>
					parent.loadingStop();
					var params = {'yesMsg':'[예] 출고처리 실행','noMsg':'[아니오] 출고처리 취소'}
					parent.openDialogConfirm('".nl2br($msg)."',500,300,function(){
						parent.upload_excel();
					},function(){},params);
				</script>
				");
			}else{
				echo("
				<script>
					parent.loadingStop();
					var params = {'yesMsg':'[예] 출고처리 실행','noMsg':'[아니오] 출고처리 취소'}
					parent.openDialogConfirm('".nl2br($msg)."',500,".$msg_height.",function(){
						parent.batch_export('{$bundle_mode}', true);
					},function(){},params);
				</script>
				");
			}
			// 예상 결과보기 종료시에도 출고 처리 완료로 바꿈 by hed
			$this->exportlibrary->update_order_receive('end');
			exit;
		}

		// 출고 중복실행방지 2019-01-15 s
		$order_receive_status = $this->exportlibrary->select_order_receive();
		if( $order_receive_status == 'ing' ){
			echo('
				<script>
					parent.loadingStop();
					alert("현재 다른 관리자가 출고 진행중입니다.\n잠시 후 다시 출고진행해주세요.");
				</script>
				');
			exit;
		}
		$this->exportlibrary->update_order_receive('ing');
		// 출고 중복실행방지 2019-01-15 e

		if($dbqueriesunset) {// 대량출고처리를 위해 디버그용쿼리 초기화 @2016-12-08
			$this->db->queries = array();
			$this->db->query_times = array();
		}

		// 에러로그저장
		$this->load->model('exportlogmodel');

		$export_type = 'goods';
		if($export_param['export_mode'] == 'order') $export_type = 'order';

		if($export_param['input_mode'] == 'excel'){
			$export_type = "excel_" . $export_type;
		}else{
			$export_type = "web_" . $export_type;
		}

		if($result_check[1]){
			foreach($result_check[1] as $data_error){
				$goods_kind = 'goods';
				if( preg_match('/COU/',$data_error['export_item_seq']) ) $goods_kind = 'coupon';

				if( $goods_kind == 'goods' ){
					$stockable	= $export_param['stockable'];
					$step		= $export_param['export_step'];
				}else{
					$stockable	= $export_param['ticket_stockable'];
					$step		= $export_param['ticket_step'];
				}
				$this->exportlogmodel->export_log($stockable,$step,$export_type,$goods_kind,$data_error);
			}

			//출고 실패사유 노출(npay) @2016-01-27 pjm
			//if($err_msg_45) $export_error_msg = "출고준비 ".$err_msg_45;
			//if($err_msg_55) $export_error_msg .= "출고완료 ".$err_msg_55;

		}
		// 엑셀 출고 처리 결과 조합
		if( $excel_data ){
			$i = 0;
			$last_field_num = count($excel_data[0]);
			foreach($excel_data[0] as $title){
				if($title == '*출고상품번호'){
					$export_item_seq_title_num = $i;
				}
				if( $title == '*출고그룹' ){
					$shipping_seq_title_num = $i;
				}
				$i++;
			}
			foreach($excel_data as $excel_row_key => $excel_row){
				$excel_data[$excel_row_key][$last_field_num] = "성공";
				if( !$excel_row[$export_item_seq_title_num] && $excel_row[$shipping_seq_title_num] == $error['shipping_seq'] ){
					unset($excel_data[$excel_row_key]);
					continue;
				}
				foreach($result_check[1] as $error){
					if( $excel_row[$export_item_seq_title_num] ){
						list($opttype,$shipping_seq,$opt_seq) = $this->excelmodel->get_info_by_export_item_seq( $excel_row[$export_item_seq_title_num]);
						if( $shipping_seq == $error['shipping_seq']){
							$excel_data[$excel_row_key][$last_field_num] = $error['msg'];
						}
					}else if($excel_row[$shipping_seq_title_num] == $error['shipping_seq']){
						$excel_data[$excel_row_key][$last_field_num] = $error['msg'];
					}
				}
			}
			$excel_data[0][$last_field_num] = "결과";

			// 처리결과 임시테이블에 저장
			$this->load->model('exceltempmodel');
			$export_temp_seq = $this->exceltempmodel->excel_temp_insert($excel_data);
		}
		# ----------------------------------------------------------------------------------------
		# 출고처리
		$export_params = $result_check[2];
		$tmp_export_error_msg = array();
		if( $export_params ){
			$result_export= $this->order2exportmodel->goods_export($export_params,$cfg);

			/**
			 * 티켓상품 sms 전송 되도록 fm_batch 테이블에 등록합니다.
			 *  - 실시간 sms 전송처리를 하지 않기 위해서 입니다.
			 * 
			 * "[수동]출고완료" 와 "[자동]출고완료" 는 같은 비즈니스 로직을 사용하고 있습니다.
			 * sms 전송처리 로직이 side effect 가 크고 비즈니스 로직 끝부분에 깊숙히 존재하고 있어서, 분기처리 하기에 부담 됩니다.
			 * 
			 * 수동/자동 출고 로직을 구분이 시작되는 order2exportmodel->goods_export() 종료 시점에 sms 발송 처리를 추가 했습니다.
			 */
			if (count($this->coupon_order_sms['order_cellphone']) > 0) {
				$this->load->model('batchmodel');
				foreach ($this->coupon_order_sms['order_cellphone'] as $key => $value) {
					$smsSendParams = [
						'result_export_code' => [$this->coupon_order_sms['params'][$key]['export_code']],
						'sendType' => 'sms',
						'sms' => $this->coupon_order_sms['order_cellphone'][$key]
					];
					$this->batchmodel->insert('complete_ticket', serialize($smsSendParams), 'none');
				}
			}

			foreach($result_export as $goods_kind=>$result_export1){
				foreach($result_export1 as $export_status => $result_export2){
					foreach($result_export2 as $export_item_seq => $result_export3){
						$result_export4 = explode('<br/>',$result_export3['export_code']);
						foreach( $result_export4 as $tmp_explode_code ){
							if($tmp_explode_code == "ERROR"){
								$tmp_export_error[$export_status][$export_item_seq] = $result_export3['message'];
							}else{
								$arr_explode_code[$goods_kind][$export_status][ $tmp_explode_code ] = $tmp_explode_code;
								$arr_explode_code_all[ $tmp_explode_code ]							= $tmp_explode_code;
							}
						}
					}
				}
			}
		}

		$cnt_export_result_goods_45		= (int) count($arr_explode_code['goods']['45']);	 // 실물 출고준비 갯수
		$cnt_export_result_goods_55		= (int) count($arr_explode_code['goods']['55']);	 // 실물 출고완료 갯수
		$cnt_export_result_coupon_55	= (int) count($arr_explode_code['coupon']['55']);	 // 쿠폰 출고완료 갯수

		$cnt_export_result_goods		= $cnt_export_result_goods_45 + $cnt_export_result_goods_55;
		$cnt_export_result_coupon		= $cnt_export_result_coupon_45 + $cnt_export_result_coupon_55;

		$cnt_export_result_coupon_55	= $cnt_export_result_coupon_55; // 쿠폰 출고완료 갯수
		$cnt_export_request_45			= (int) count($tmp_export_error['45'])
											+ $cnt_export_result_goods_45;
		$cnt_export_request_55			= (int) count($tmp_export_error['55'])
											+ $cnt_export_result_coupon_55
											+ $cnt_export_result_goods_55
											+ (int) $result_export_error_cnt;
		$cnt_export_error_45			= (int) count($tmp_export_error['45']);
		$cnt_export_error_55			= (int) count($tmp_export_error['55']) + (int) $result_export_error_cnt;

		if(count($tmp_export_error['45']) > 0){
			$export_error_msg = implode("<br />",$tmp_export_error['45']);
		}
		if(count($tmp_export_error['55']) > 0){
			$export_error_msg = implode("<br />",$tmp_export_error['55']);
		}

		$msg = "처리 결과는 아래와 같습니다.";
		$msg .= "<br/>출고준비 ".number_format($cnt_export_request_45)."건 요청 → 성공 ".number_format($cnt_export_result_goods_45)."건";
		$msg .= " ,실패".number_format($cnt_export_error_45)."건";
		$msg .= "<br/>출고완료 ".number_format($cnt_export_request_55)."건 요청 → 성공 ".number_format($cnt_export_result_coupon_55+$cnt_export_result_goods_55)."건";
		$msg .= " ,실패".number_format($cnt_export_error_55)."건";

		$result_obj = "{";
		$result_obj .= "'cnt_export_request_45':".$cnt_export_request_45;
		$result_obj .= ",'cnt_export_result_goods_45':".$cnt_export_result_goods_45;
		$result_obj .= ",'cnt_export_error_45':".$cnt_export_error_45;
		$result_obj .= ",'cnt_export_request_55':".$cnt_export_request_55;
		$result_obj .= ",'cnt_export_result_coupon_55':".$cnt_export_result_coupon_55;
		$result_obj .= ",'cnt_export_result_goods_55':".$cnt_export_result_goods_55;
		$result_obj .= ",'cnt_export_error_55':".$cnt_export_error_55;
		$result_obj .= ",'exist_invoice':".$this->order2exportmodel->exist_invoice;
		$result_obj .= ",'export_result_error_msg':'".urlencode($export_error_msg)."'";
		$result_obj .= "}";

		if($arr_explode_code_all){
			$str_goods_export_code = implode('|',$arr_explode_code_all); // 실물출고코드합치기
		}

		if($cnt_export_result_goods_45 >0){
			// 출고준비->출고완료 출고 상태 변경 창로드
			$callback = "parent.batch_status_popup(45,'".$str_goods_export_code."',".$cnt_export_result_coupon_55.",".$result_obj.",'".$cfg['bundle_mode']."');";
		}else{
			// 인쇄용창 로드
			$callback = "parent.batch_status_popup(55,'".$str_goods_export_code."',".$cnt_export_result_coupon_55.",".$result_obj.",'".$cfg['bundle_mode']."');";
		}
		if($export_param['input_mode'] != 'excel'){
			$callback = "parent.close_export_popup();".$callback;
		}

		if($dbqueriesunset) {// 대량출고처리를 위해 디버그용쿼리 초기화 @2016-12-08
			$this->db->queries = array();
			$this->db->query_times = array();
		}

		// 물류관리 매장 재고 전송
		if	($this->scm_cfg['use'] == 'Y'){
			$this->load->model('scmmodel');
			if	($this->scmmodel->tmp_scm['wh_seq'] > 0){
				$sendResult		= $this->scmmodel->change_store_stock($this->scmmodel->tmp_scm['goods'], array($this->scmmodel->tmp_scm['wh_seq']), '');
			}
		}

		// O2O 주문 자동 배송완료 처리
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_order_process_order_export_exec($export_param['order_seq'], $cfg['wh_seq']);

		// 출고 중복실행방지 2019-01-15 s
		$this->exportlibrary->update_order_receive('end');
		// 출고 중복실행방지 2019-01-15 e

		if	(!$sendResult['status']){
			if($export_param['bundle_mode'] == 'bundle'){
				echo "<script>".$callback."</script>";
			}else{
				echo "<script>".$callback."parent.window.opener.location.reload();</script>";
			}
		}
	}

	public function excel_export_result()
	{
		$export_temp_seq = $_GET['no'];
		$this->load->model('excelmodel');
		$this->excelmodel->create_excel_temp($export_temp_seq);
	}

	## 상품 재매칭, 재주문(맞교환) @2015-07-30 pjm
	public function order_goods_change(){
		$this->load->model("ordermodel");
		$this->load->model("cartmodel");
		$this->load->model('goodsmodel');
		$this->load->model('membermodel');

		$order_seq			= $this->input->post('order_seq');
		$old_item_seq		= $this->input->post('old_item_seq');
		$old_option_seq		= $this->input->post('old_option_seq');
		$member_seq			= $this->input->post('member_seq');
		$cart_table			= $this->input->post('cart_table');
		$displayId			= $this->input->post('displayId');
		$arrImageExtensions	= array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tif', 'pic');

		# 주문서 변경
		## 재주문, 재매칭 => 주문서 변경
		## 개인결제, 관리자주문 => 장바구니 등록

		if(!$_POST['goods']){
			openDialogAlert( "적용할 상품을 선택해 주세요.",400,140,'parent');
			exit;
		}

		// 상품 추가입력사항 파일 업로드 시 저장 폴더 생성
		$path		= ROOTPATH."data/order/";
		if	(!is_dir($path)){
			@mkdir($path);
			@chmod($path, 0777);
		}

		if(count($_POST['goods']) > 1){
			openDialogAlert( "적용할 상품을 1개만 선택해 주세요.",400,140,'parent');
			exit;
		}

		$orders = $this->ordermodel->get_order($order_seq);

		$cfg['order']	= config_load('order');

		$old_option_data = $this->ordermodel->get_order_item_option($_POST['old_option_seq']);

		foreach($_POST['goods'] as $goods_seq=>$goodsData){

			if(!$goodsData['option']){
				openDialogAlert( "적용할 상품을 선택해 주세요.",400,140,'parent');
				exit;
			}

			if(count($goodsData['option']) > 1){
				openDialogAlert( "적용할 상품을 1개만 선택해 주세요.",400,140,'parent');
				exit;
			}

			$goods			= $this->goodsmodel->get_goods($goods_seq);
			$inputs			= $this->goodsmodel->get_goods_input($goods_seq);
			$options		= $this->goodsmodel->get_goods_default_option($goods_seq);
			$suboptions		= $this->goodsmodel->get_goods_suboption_required($goods_seq);
			$member_data	= $this->membermodel->get_member_data($member_seq);

			$goods_code		= $goods['goods_code'];

			$tmp_num = '';
			foreach($goodsData['option'] as $k=>$opt){
				if(!$tmp_num) $tmp_num = $k; else continue;
			}

			$new_option			= array();
			$new_optionTitle	= array();
			$new_optionEa		= array();
			$new_suboption		= array();
			$new_suboptionTitle	= array();
			$new_suboptionEa	= array();
			$new_inputValue		= array();
			$new_inputTitle		= array();
			$new_inputType		= array();

			## 변경 옵션 정보
			$new_option			= $goodsData['option'][$tmp_num];
			$new_optionTitle	= $goodsData['optionTitle'][$tmp_num];
			$new_optionEa		= $goodsData['optionEa'][$tmp_num];
			$new_suboption		= $goodsData['suboption'][$tmp_num];
			if($new_suboption){
				$new_suboptionTitle	= $goodsData['suboptionTitle'][$tmp_num];
				$new_suboptionEa	= $goodsData['suboptionEa'][$tmp_num];
			}
			$new_inputValue		= $goodsData['inputsValue'][$tmp_num];
			if($new_inputValue){
				$new_inputTitle		= $goodsData['inputsTitle'][$tmp_num];
				$new_inputType		= $goodsData['inputsType'][$tmp_num];
			}

			// 상품상태 체크
			if($goods['goods_status'] != 'normal'){
				$err_msg  = '';
				if($goods['goods_name']){
					$err_msg .= $goods['goods_name'] ."은(는) ";
				}
				if		($goods['goods_status'] == 'unsold')	$err_msg	.= '판매중지';
				else											$err_msg	.= '품절된';
				$err_msg .= " 상품입니다.";
				openDialogAlert( $err_msg, 400,140,'parent');
				exit;
			}
			// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
			if( $goods['event']['event_goodsStatus'] === true ){
				openDialogAlert( "단독이벤트 기간에만 구매가 가능한 상품입니다.", 400,140,'parent');
				exit;
			}

			// 최소구매 수량에 따른 구매 수량 변경
			if($goods['min_purchase_ea'] > $new_optionEa ){
				openDialogAlert("최소 구매수량은 ".number_format($goods['min_purchase_ea'])."개 입니다.",400,140,'parent',"");
				exit;
			}
			if($new_optionEa > $goods['max_purchase_ea'] && $goods['max_purchase_ea']){
				openDialogAlert("최대 구매수량은 ".number_format($goods['max_purchase_ea'])."개 입니다.",400,140,'parent',"");
				exit;
			}

			if($old_option_seq){

				## 기존상품 주문 수량
				$oldEa			= array();
				$old_option		= array();
				$old_suboption	= array();
				$old_totalEa	= 0;		//기존옵션 총 주문수량
				$query	= "select opt_type,ea from (
								select 'option' as opt_type,ea from fm_order_item_option where order_seq=? and item_option_seq=?
								union all
								select 'sub' as opt_type,ea from fm_order_item_suboption where order_seq=? and item_option_seq=?
							) k";
				$query		= $this->db->query($query,array($order_seq,$old_option_seq,$order_seq,$old_option_seq));
				foreach($query->result_array() as $k=>$opt){
					if($opt['opt_type'] == "option"){
						$oldEa['opt'][0] = $opt['ea'];
					}else{
						$oldEa['sub'][0][]	= $opt['ea'];
					}

					$old_totalEa += $opt['ea'];
				}

				# 재매칭일 경우 기존 주문수량으로 재고체크.
				if($cart_table == "rematch"){
					$stock_opt_ea		= $oldEa['opt'][0];
					$stock_sub_ea		= $oldEa['sub'][0];
				}else{
					$stock_opt_ea		= $new_optionEa ;
					$stock_sub_ea		= $new_suboptionEa ;
				}
			}

			// 필수 옵션 재고 체크
			$chk	= check_stock_option($goods_seq, $new_option[0], $new_option[1],
											$new_option[2], $new_option[3],$new_option[4],
											$stock_opt_ea, $cfg['order'], 'view_stock');
			if	(!$chk || $chk['stock'] < 0 ){
				openDialogAlert( "구매 가능한 필수옵션(재고부족)이 없습니다.", 400,140,'parent');
				exit;
			}

			// 필수 추가구성옵션 재고 체크
			if	($new_suboption){
				foreach($new_suboption as $k=>$suboption){

					$chk	= false;
					$chk	= check_stock_suboption($goods_seq, $new_suboptionTitle[$k],
													$suboption, $stock_sub_ea[$k],
													$cfg['order'], 'view_stock');
					if	(!$chk || $chk['stock'] < 0 ){
						openDialogAlert( "필수 추가구성옵션 " . $new_suboptionTitle[$k] . "을(를) 구매할(재고부족) 수 없습니다.", 400,140,'parent');
						exit;
					}
				}
			}

			## 기존 상품과 변경상품의 주문 수량 체크
			## 재매칭 => 필수옵션, 추가옵션 Row수 1:1 매칭, 변경된 수량은 무시. 원주문의 수량으로 저장. 원주문의 수량으로 재고체크
			if($old_option_seq){

				# 변경할 주문 상품의 필수/추가옵션 row수 체크
				$new_opt_row = count($goodsData['option']);
				foreach($goodsData['option'] as $k=>$opt){
					$new_sub_row = count($goodsData['suboption'][$k]);
				}

				# 기존 주문 상품의 필수/추가옵션 row수 체크
				$old_opt_row = count($oldEa['opt']);
				foreach($oldEa['opt'] as $k1=>$ea1){
					$old_sub_row = count($oldEa['sub'][$k1]);
					/*
						## 필수 옵션 주문 수량 체크
					if((int)$ea1 != (int)$new_optionEa){
						openDialogAlert( "필수옵션의 기존 주문 수량(".$ea1."개)과 같아야 합니다.",400,140,'parent');
						exit;
					}
						## 추가 옵션 주문 수량 체크
					foreach($oldEa['sub'][$tmp_num] as $k2=>$ea2){
						if((int)$ea2 != (int)$goodsData['suboptionEa'][$tmp_num][$k2]){
							openDialogAlert( "추가옵션의 기존 주문 수량(".$ea2."개)과 같아야 합니다.",400,140,'parent');
							exit;
						}
					}
				}
					*/
				}

				if($old_opt_row != $new_opt_row){
					openDialogAlert( "기존 주문의 필수옵션 Row수(".$old_opt_row."개)와 같아야 합니다.",400,140,'parent');
					exit;
				}
				if($old_sub_row != $new_sub_row){
					openDialogAlert( "기존 주문의 추가옵션 Row수(".$old_sub_row."개)와 같아야 합니다.",400,140,'parent');
					exit;
				}

				/*
				## 변경상품 총 주문 수량
				$new_totalEa = array_sum($goodsData['optionEa']);
				foreach($goodsData['suboptionEa'] as $subopt) $new_totalEa += array_sum($subopt);

				## 총 주문 수량 체크
				if($old_totalEa != $new_totalEa){
					openDialogAlert( "옵션의 기존 총 주문 수량(".$old_totalEa."개)과 같아야 합니다.",400,140,'parent');
					exit;
				}
				*/

				## 상품 정보만 교체
				$this->ordermodel->update_item($goods_seq,$goods['provider_seq'],$old_item_seq);

				$option_package_yn		= ($goods['package_yn'] == "y")? "y" : "n";
				$suboption_package_yn	= ($goods['package_yn_suboption'] == "y")? "y" : "n";

				$where_option = array();
				$where_option['goods_seq'] = $goods_seq;
				for($i=0; $i<5; $i++){
					if(empty($new_option[$i])) unset($new_optionTitle[$i]);

					$n = $i+1;
					if( $new_option[$i] ){
						$where_option['option'.$n] = $new_option[$i];
					}else{
						$where_option['option'.$n] = '';
					}
				}

				# 필수옵션 코드
				$query_option	= $this->goodsmodel->get_option($where_option);
				$data_option	= $query_option->row_array();

				## 가격변동 없이 옵션 정보만 교체
				$export_option		= array();
				for($i=0; $i<5; $i++){
					$j = $i + 1;
					$data = array();
					$data['title']		= ($new_optionTitle[$i])? $new_optionTitle[$i] : "";
					$data['value']		= ($new_option[$i])? $new_option[$i] : "";
					$data['code']		= ($data_option['optioncode'.$j])? $data_option['optioncode'.$j] : "";
					$data['goods_code']	= $goods_code;
					$data['package_yn']	= $option_package_yn;
					$export_option[] = $data;
				}
				$this->ordermodel->update_option($old_option_seq,$goods['provider_seq'],$export_option);
				foreach($export_option as $idx => $option) {
					$accountall['title'.($idx+1)] = $option['title'];
					$accountall['option'.($idx+1)] = $option['option'];
					$accountall['optioncode'.($idx+1)] = $option['code'];
				}

				$option_seq_list = array();
				$option_seq_list['opt'] = $old_option_seq;

				## 상품 추가 옵션 Change Start
				$export_suboption	= array();
				$new_suboptionCode	= array();
				if($new_suboption){

					# 추가옵션 코드
					foreach($new_suboption as $suboption){

						$where_suboption				= array();
						$where_suboption['goods_seq']	= $goods_seq;
						$where_suboption['suboption']	= ($suboption)? $suboption : "";
						$query_suboption	= $this->goodsmodel->get_suboption($where_suboption);
						$data_suboption		= $query_suboption->row_array();
						$new_suboptionCode[] = $data_suboption['suboption_code'];

					}

					# 원주문의 상품 추가옵션 가져오기
					$query			= "select item_suboption_seq from fm_order_item_suboption where item_option_seq=?";
					$query			= $this->db->query($query,array($old_option_seq));
					$old_suboption	= $query->result_array();

					# 추가옵션 정보 Update
					foreach($old_suboption as $k=>$old_data){

						$option_seq_list['sub'][] = $old_data['item_suboption_seq'];

						$data = array();
						$data['item_suboption_seq']	= $old_data['item_suboption_seq'];
						$data['title']				= ($new_suboptionTitle[$k])? $new_suboptionTitle[$k] : "";
						$data['value']				= ($new_suboption[$k])? $new_suboption[$k] : "";
						$data['code']				= ($new_suboptionCode[$k])? $new_suboptionCode[$k] : "";
						$data['goods_code']			= $goods_code.$new_suboptionCode[$k];
						$data['package_yn']			= $suboption_package_yn;

						$export_suboption[]			= $data;

					}
				}
				if($export_suboption) $this->ordermodel->update_suboption($export_suboption);

				# 기존 입력 옵션 정보 삭제
				$query = "delete from fm_order_item_input where item_option_seq=?";
				$this->db->query($query,array($old_option_seq));

				## 상품 입력 옵션 Change Start
				if($new_inputValue){
					# 입력옵션 정보 Insert
					foreach($new_inputValue as $k=>$inputVal){

						// 파일업로드 입력옵션일 경우
						if	($new_inputType[$k] == 'file' && realpath($inputVal)){
							$inputType	= 'file';
							$file_path	= str_replace(realpath(ROOTPATH), '', realpath($inputVal));
							$fname		= $file_path;

							// 파일 업로드 여부체크
							if	(preg_match("/\/tmp\//i", $file_path) && file_exists(realpath(ROOTPATH) . $file_path)){
								$file_ext	= end(explode('.', $file_path));
								$file_path	= realpath(ROOTPATH) . $file_path;
								$fname		= '';
								if	( in_array(strtolower($file_ext), $arrImageExtensions) ){
									$fname	= $old_item_seq . '_' . $old_option_seq . '_'
											. $k . "." . $file_ext;
									copy($file_path, $path.$fname);
									@unlink($inputVal);
								}
							}else{
								$tmp_img = explode("/",$inputVal);
								if(count($tmp_img) > 0){
									$fname = $tmp_img[count($tmp_img)-1];
								}
							}

							$inputVal	= $fname;
						}

						$insert_inp_option['title']				= $new_inputTitle[$k];
						$insert_inp_option['value']				= $inputVal;
						$insert_inp_option['type']				= $new_inputType[$k];
						$insert_inp_option['order_seq']			= $order_seq;
						$insert_inp_option['item_seq']			= $old_item_seq;
						$insert_inp_option['item_option_seq']	= $old_option_seq;

						$this->ordermodel->insert_inputoption($insert_inp_option);

					}
				}

				## 정산데이터 변경
				/**
				 * fm_order_item 업데이트하는것과 동일하게 update (item_seq 기준)
				 */
				$this->load->helper('accountall');
				if(!$this->accountallmodel)$this->load->model('accountallmodel');
				$update = [
					'order_goods_seq' => $goods_seq,
					'order_goods_name' => $goods['goods_name'],
					'title1' => $accountall['title1'],
					'title2' => $accountall['title2'],
					'title3' => $accountall['title3'],
					'title4' => $accountall['title4'],
					'title5' => $accountall['title5'],
					'option1' => $accountall['option1'],
					'option2' => $accountall['option2'],
					'option3' => $accountall['option3'],
					'option4' => $accountall['option4'],
					'option5' => $accountall['option5'],
					'optioncode1' => $accountall['optioncode1'],
					'optioncode2' => $accountall['optioncode2'],
					'optioncode3' => $accountall['optioncode3'],
					'optioncode4' => $accountall['optioncode4'],
					'optioncode5' => $accountall['optioncode5'],
				];
				$where = [
					'order_seq' => $order_seq,
					'item_seq' => $old_item_seq,
				];
				$tb_act_ym = date('Ym', strtotime($orders['deposit_date']));
				$this->accountallmodel->update_account_calculate($tb_act_ym, $update, $where);
			}

			$this->goodsmodel->modify_reservation_real($goods_seq);
			// 기존 상품은 출고안하기 때문에 기존 상품도 가용재고 재계산	2019-05-28 hyem
			if($old_option_data['goods_seq'] > 0) {
				$this->goodsmodel->modify_reservation_real($old_option_data['goods_seq']);
			}

			//재매칭 로그
			$logtitle = "상품재매칭".$logstr2;
			$logstr = "상품(옵션)이 재매칭 되었습니다.";
			if($old_option_data['goods_seq'] != $goods_seq){
				$logtitle .= "(상품변경:".$old_option_data['goods_seq']."->".$goods_seq.")";
			}
			$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],$logtitle,$logstr);

		}

		$this->load->model('orderpackagemodel');
		$this->orderpackagemodel->package_order($order_seq);

		# 연결된 패키지 상품의 재고 차감.
		if($option_seq_list){
			$goods_seq_list = array();
			foreach($option_seq_list as $opttype => $opt_seq){

				if($opttype == "opt"){
					$option_package = $this->orderpackagemodel->get_option($opt_seq);
					foreach($option_package as $package){
						$goods_seq_list[] = $package['goods_seq'];
					}
				}elseif($opttype == "sub"){
					foreach($opt_seq as $sub_seq){
						$option_package = $this->orderpackagemodel->get_suboption($sub_seq);
						foreach($option_package as $package){
							$goods_seq_list[] = $package['goods_seq'];
						}
					}
				}

			}

			$goods_seq_list = array_unique($goods_seq_list);
			foreach($goods_seq_list as $goods_seq){
				$this->goodsmodel->modify_reservation_real($goods_seq);
			}
		}

		$callback = "parent.closeDialog('".$displayId."');parent.location.reload();";
		openDialogAlert("주문 옵션을 매칭하였습니다.",400,140,'parent',$callback);

	}

	public function unique_personal_code()
	{
		$order_seq  					= $_GET['seq'];
		$data_order = $this->ordermodel->get_order($order_seq);
		if($data_order['clearance_unique_personal_code'] != $_POST['clearance_unique_personal_code']){
			$clearance_unique_personal_code	= $_POST['clearance_unique_personal_code'];
			$this->ordermodel->clearance_unique_personal_code($clearance_unique_personal_code,$order_seq);
			$data['clearance_unique_personal_code'] = $clearance_unique_personal_code;
			$log = "개인통관 고유부호 변경 ".base64_encode($data_order['clearance_unique_personal_code'])."→".base64_encode($clearance_unique_personal_code);
			$this->ordermodel->set_log($order_seq,'process',$this->managerInfo['mname'],$log,serialize($data));
			$callback = '';
			openDialogAlert("개인통관고유부호를 변경하였습니다.",400,140,'parent',$callback);
		}
	}

	public function excel_form_write()
	{
		error_reporting(E_ERROR|E_PARSE);
		$this->load->model('orderexcel');

		## 2차 배열 validation을 위해 변수 정의
		$_POST['sort_cell1']	= $_POST['sort_cell']['1'];
		$_POST['sort_cell2']	= $_POST['sort_cell']['2'];

		## 기본 validation
		$this->validation->set_rules('name', '이름','trim|required|max_length[255]|xss_clean');
		$this->validation->set_rules('form_type', '엑셀 종류','trim|required|max_length[255]|xss_clean');
		$this->validation->set_rules('chk_cell[]', '항목 설정','trim|required|max_length[40]|xss_clean');
		$this->validation->set_rules('sort_cell1[]', '항목 순서 설정','trim|required|max_length[40]|xss_clean');
		$this->validation->set_rules('sort_cell2[]', '항목 순서 설정','trim|required|max_length[40]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		if( !in_array($_POST['form_type'],$this->orderexcel->m_form_type) ){
			openDialogAlert('엑셀 종류가 올바르지 않습니다.',400,140,'parent',$callback);
			exit;
		}
		$all_cells	= $this->orderexcel->setting_filter($_POST['form_type']);
		foreach($all_cells as $code=>$data){
			$all_cell_codes[] = $code;
		}
		foreach($_POST['chk_cell'] as $data_chk_cell){
			if( !in_array($data_chk_cell,$all_cell_codes) ){
				openDialogAlert('항목 설정이 올바르지 않습니다.',400,140,'parent',$callback);
				exit;
			}
		}
		foreach($_POST['sort_cell1'] as $data_sort_cell){
			if( !in_array($data_sort_cell,$all_cell_codes) ){
				openDialogAlert('항목 순서 설정가 올바르지 않습니다.',400,140,'parent',$callback);
				exit;
			}
		}
		foreach($_POST['sort_cell2'] as $data_sort_cell){
			if( !in_array($data_sort_cell,$all_cell_codes) ){
				openDialogAlert('항목 순서 설정가 올바르지 않습니다.',400,140,'parent',$callback);
				exit;
			}
		}

		## 저장 변수 정의
		$today			= date('Y-m-d H:i:s');
		$form_id		=	'admin_order_'.time().rand(100,999);
		$form_name	= $_POST['name'];
		$form_type	= $_POST['form_type'];
		$form_item	= json_encode($_POST['chk_cell']);
		$sort_item		= json_encode(array($_POST['sort_cell1'],$_POST['sort_cell2']));
		$form_name	= $_POST['name'];
		$form_seq	=	(int) $_POST['no'];

		$params['provider_seq']	= 1; // 본사관리자에서 저장시 1로 고정
		$params['form_name']	= $form_name;
		$params['form_type']		= $form_type;
		$params['form_item']		= $form_item;
		$params['sort_item']		= $sort_item;
		$params['update_date']	= $today;
		if(!$form_seq){
			$params['form_id']			= $form_id;
			$params['regist_date']	= $today;
			$form_seq = $this->orderexcel->insert($params,true);
		}else{
			$where_params['form_seq']	= $form_seq;
			$this->orderexcel->update($params, $where_params);
		}
		$callback	= "parent.location.href='../order/excel_form_write?no=".$form_seq."';";
		openDialogAlert('저장이 완료 되었습니다.',400,140,'parent',$callback);

	}
	public function excel_form_delete()
	{
		error_reporting(E_ERROR|E_PARSE);
		$this->load->model('orderexcel');
		$form_seq	=	(int) $_POST['no'];

		if($form_seq){
			$where_params['form_seq']	= $form_seq;
			$this->orderexcel->del( $where_params);
		}
		$callback	= "parent.location.href='../order/excel_form_list';";
		openDialogAlert('삭제 되었습니다.', 400, 140, 'parent', $callback);
	}

	// 자동입금확인 수동매칭
	public function autodeposit_manual_match(){
		$this->load->model('usedmodel');
		$this->load->model('ordermodel');
		$this->load->library('orderlibrary');

		$bkcode				= $this->input->post('bkcode');
		$order_seq			= $this->input->post('order_seq');

		$callback	= "parent.location.reload();";
		// 자동입금확인 중복실행방지 확인
		$autodeporitStatus = $this->orderlibrary->select_autodeposit_receive();

		if ($autodeporitStatus === 'ing') {
			openDialogAlert('이미 자동입금확인 중입니다.', 400, 170, 'parent', $callback);
			exit;
		}

		// 자동입금 확인중으로 상태 업데이트 
		$this->orderlibrary->update_autodeposit_receive('ing');

		// 해당 주문 매칭된 입금내역 확인
		$chk				= $this->usedmodel->chk_bank_match(array($order_seq));
		if	($chk){
			openDialogAlert('이미 입금내역과 매칭된 주문입니다.', 400, 170, 'parent', $callback);
			exit;
		}

		// 입금내역 정보 추출
		unset($sc);
		$sc['bkcode']		= $bkcode;
		$bank				= $this->usedmodel->get_bank_data($sc);
		if	($bank['bktag2'] == '1' && $bank['bkmemo4']){
			openDialogAlert('이미 매칭된 입금내역입니다.', 400, 170, function(){});
			exit;
		}
		$orders				= $this->ordermodel->get_order($order_seq);
		$msg				= '주문 내역과 입금내역이 매칭되었습니다.';

		// 주문접수 상태로 변경
		if		($orders['step'] == 95){
			$msg			= '주문이 입금확인되었습니다. 주문 내역과 입금내역이 매칭되었습니다.';
			$result			= $this->_order_reverse($order_seq);
			$orders['step']	= 15;
		}

		// 결제확인 처리
		if		($orders['step'] == 15){
			$msg			= '주문이 입금확인되었습니다. 주문 내역과 입금내역이 매칭되었습니다.';
			$result			= $this->_deposit_exec($order_seq);
			$coupon			= $result['coupon'];
			$goods			= $result['goods'];
			$orders['step']	= 25;
		}

		// 주문서에 매칭값 추가
		$this->ordermodel->set_marking_autodeposit($bkcode, $order_seq, 'M');

		// 입금내역에 매칭값 추가
		$this->usedmodel->set_marking_autodeposit($bkcode, $order_seq);

		// 자동입금 확인 완료로 상태 업데이트 
		$this->orderlibrary->update_autodeposit_receive('end');

		// 매칭처리 로그
		$log_str = "관리자가 입금내역과 매칭처리하였습니다.";
		$this->ordermodel->set_log($order_seq, 'process', $this->managerInfo['manager_id'], '수동입금매칭', $log_str);

		openDialogAlert($msg, 450, 170, 'parent', $callback);
	}

	/* #16651 2018-07-10 ycg 관리자 메모 기능 개선 */
	//관리자 메모 선택 삭제
	function admin_memo_del(){
		$iMemo_idx	= $this->input->post('memo_idx');
		$bResult	= $this->ordermodel->del_order_memo($iMemo_idx);

		if($bResult!=true){
			$msg		= "메모 삭제에 실패하였습니다.다시 시도해주세요.";
			$callback	= "parent.location.reload();";
			openDialogAlert($msg, 450, 170, 'parent', $callback);
			exit;
		}
	}
	//관리자 메모 선택 수정
	function admin_memo_modify(){
		$iMemo_idx	= $this->input->get('memo_idx');
		$aResult	= $this->ordermodel->sel_order_memo($iMemo_idx);

		foreach($aResult as $key => $value){
			$aResult = $value;
		}

		print_r($aResult);
	}
	/* #16651 2018-07-10 ycg 관리자 메모 기능 개선 */
}

/* End of file order_process.php */
/* Location: ./app/controllers/admin/order_process.php */

