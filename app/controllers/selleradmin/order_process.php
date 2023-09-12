<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class order_process extends selleradmin_base {

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

		$orders		= $this->ordermodel->get_order($order_seq);

		# 간편결제 API 주문건 배송정보 변경 불가 처리
		$npay_use		= npay_useck();			//Npay v2.1 사용여부
		$talkbuy_use	= talkbuy_useck();		//톡체크아웃사용여부
		if(($npay_use && $orders['npay_order_id']) || ($talkbuy_use && $orders['talkbuy_order_id'])) {
			$marketname = order_market_name($orders);
			openDialogAlert("<span class=\'fx12\'>".$marketname." 주문건은 직접 배송지 변경이 불가합니다.<br />".$marketname." 어드민에서 처리할 수 있습니다.</span>",400,180,'parent',"");
			exit;
		}

		if( !in_array($orders['step'],$this->ordermodel->able_step_action['shipping_region']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 배송정보 변경을 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}
		
		//개인정보 마스킹 표시 권한 체크
		$private_masking = $this->authmodel->manager_limit_act('private_masking');
		if( !$private_masking ) {
			$this->validation->set_rules('recipient_user_name','받는이','trim|required|xss_clean');
			// $this->validation->set_rules('recipient_phone[]','전화','trim|numeric|required|xss_clean');
			$this->validation->set_rules('recipient_cellphone[]','휴대폰','trim|numeric|required|xss_clean');
	
			if($international == 'domestic'){
				$this->validation->set_rules('recipient_zipcode[]','우편번호','trim|required|xss_clean');
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
				$aPostParams['recipient_zipcode'] = implode('-',$aPostParams['recipient_zipcode']);
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
				foreach($_POST as $k => $row) if($orders[$k]!=$data) $change = 1;
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
            $this->ordermodel->set_log($order_seq,'process',$this->providerInfo['provider_log_name'],$log,serialize($data));
            
            //관리자 로그 남기기
			$is_log = false;
			if($oldData['recipient_phone'] == ''){
				$oldData['recipient_phone'] = '--';
			}
			
			if($oldData['recipient_cellphone'] == ''){
				$oldData['recipient_cellphone'] = '--';
			}
			
			$logData = array();
			$logData['params'] = array('order_seq' => $order_seq);
			foreach($oldData as $k => $v){
				if($v != $data[$k]){
					$logData['params']['target'] .= $k."|";
					$logData['params']['before'] .= $v."|";
					$logData['params']['after'] .= $data[$k]."|";
				}
			}
			
			if($logData['params']['before']){
				$this->load->library('managerlog');
				$this->managerlog->insertData($logData);
			}
			
			openDialogAlert("배송지 정보가 변경 되었습니다.",400,140,'parent','');
		}
	}

	// 배송정보 변경
	public function bank()
	{
		$aGetParams		= $this->input->get();
		$aPostParams 	= $this->input->post();
		$order_seq 		= $aGetParams['seq'];
		$orders			= $this->ordermodel->get_order($order_seq);

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
			$this->ordermodel->set_log($order_seq,'process',$this->providerInfo['provider_log_name'],$log,serialize($data));
			openDialogAlert("입금계좌가 변경 되었습니다.",400,140,'parent','');
		}
	}

	// 관리자 메모 기능 추가
	// 기존 동일 메소드 명으로는 매출증빙 메모 등록이나, 입점사의 경우 해당 기능을 제공하지 않음
	// 때문에 admin 과 동일한 메소드 명으로 용도 변경
	public function admin_memo()
	{
		$this->validation->set_rules('admin_memo','관리자메모','trim|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if (!$this->providerInfo) {
		    openDialogAlert("입점사 정보를 찾을 수 없습니다.",400,140,'parent','');
		    exit;
		}

		$data['memo_idx']         = $this->input->post('memo_idx');
		$data['order_seq']        = $this->input->post('seq');
		$data['provider_seq']     = $this->providerInfo['provider_seq'];
		$data['regist_date']      = date("Y-m-d H:i:s");
		$data['mname']            = $this->input->post('mname');
		$data['manager_id']       = $this->providerInfo['provider_id'];
		$data['admin_memo']       = $this->input->post('admin_memo');
		$data['ip']               = $_SERVER['REMOTE_ADDR'];

		if ($data['memo_idx'] > 0) { //수정
		    $this->db->where('memo_idx', $data['memo_idx']);
		    $this->db->where('order_seq', $data['order_seq']);
		    $this->db->update('fm_order_memo',$data);
		} else { //입력
		    unset($data['memo_idx']);
		    $this->db->insert('fm_order_memo', $data);
		}

		return true;
	}

	function admin_memo_delete(){
	    $memo_idx  = $this->input->post('memo_idx');
	    $order_seq = $this->input->post('order_seq');

	    if ($memo_idx <= 0 || $order_seq <= 0) {
	        openDialogAlert("메모 고유값 누락", 400, 140, 'parent', '');
	        exit;
	    }

	    $order_memo = $this->ordermodel->get_order_memo($order_seq, $memo_idx);

	    if($order_memo[0]){
	        if($this->providerInfo['provider_seq'] != $order_memo[0]['provider_seq']){
	            openDialogAlert("삭제 권한이 없습니다.",400,140,'parent','');
	            exit;
	        }
	    } else {
	        openDialogAlert("데이터를 찾을 수 없습니다.", 400, 140, 'parent', '');
	        exit;
	    }

        $res = $this->ordermodel->del_order_memo($memo_idx);
        if ($res != true) {
            openDialogAlert("메모 삭제에 실패하였습니다. 다시 시도해주세요.", 450, 170, 'parent', '');
            exit;
        }

	    return true;
	}

	// 주문 무효
	public function cancel_order(){
		$order_seq = $_GET['seq'];

		$orders		= $this->ordermodel->get_order($order_seq);
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
					'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp252",$order_seq), // $this->arr_step[95] 는 "주문무효"이며 변동되지 않음, [복원]주문무효(%s)에 의한 마일리지 환원
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
					'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp253",$order_seq), // $this->arr_step[95] 는 "주문무효"이며 변동되지 않음, [복원]주문무효(%s)에 의한 예치금 환원
				);
				$this->membermodel->cash_insert($params, $orders['member_seq']);
				$this->ordermodel->set_cash_use($order_seq,'return');
			}
		}

		/* 프로모션환원 */
		$this->load->model('couponmodel');
		$this->load->model('promotionmodel');

		$r_reservation_goods_seq = array();
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

		/* 배송비쿠폰 복원*/
		if($orders['download_seq']){
			$shippingcoupon = $this->couponmodel->restore_used_coupon($orders['download_seq']);
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

		$this->ordermodel->set_log($order_seq,'cancel',$this->providerInfo['provider_log_name'],'주문무효',$log,$caccel_arr);
		openDialogAlert("주문무효가 완료되었습니다.",400,140,'parent',"parent.location.reload();");
	}

	public function receipt_process(){
		$order_seq	=  $_GET['order_seq'];
		$seq		=  $_GET['seq'];
		$result = firstmall_typereceipt($order_seq, $seq);
		echo json_encode($result);
		exit;
	}

	// 에누리
	public function enuri(){
		$order_seq = $_GET['seq'];
		$enuri = (int) $_POST['enuri'];
		$orders	= $this->ordermodel->get_order($order_seq);
		if( !in_array($orders['step'],$this->ordermodel->able_step_action['enuri']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 에누리 변경을 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		if( !$ordres['payment'] != 'bank' ){
			openDialogAlert("무통장 주문만 에누리를 적용할 수 있습니다.",400,140,'parent',"");
			exit;
		}

		if( $enuri > $orders['settleprice']){
			openDialogAlert("에누리금액은 결제금액을 초과할 수 없습니다.",400,140,'parent',"");
			exit;
		}

		if($enuri != $orders['enuri']){
			$this->ordermodel->set_enuri($order_seq,$enuri);
			$log_str = "에누리가 변경 되었습니다.";
        	$this->ordermodel->set_log($order_seq,'process',$this->providerInfo['provider_log_name'],'에누리 변경',$log_str);
			openDialogAlert("에누리가 변경 되었습니다.",400,140,'parent',"parent.location.reload();");
		}

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

		$item = implode("|",$_POST['downloads_item_use']);
		$params['name']			= $_POST['name'];
		$params['criteria']		= $_POST['criteria'];
		$params['item']			= $item;
		$params['update_date'] = date("Y-m-d H:i:s");
		if($_POST['seq']){
			$this->db->where(array("seq"=>$_POST['seq'],"provider_seq"=>$this->providerInfo['provider_seq']));
			$result = $this->db->update('fm_exceldownload', $params);
			$msg	= "수정 되었습니다.";
			$func	= "parent.location.reload();";
		}else{
			$params['provider_seq']	= $this->providerInfo['provider_seq'];
			$params['regdate'] = date("Y-m-d H:i:s");
			$this->db->insert('fm_exceldownload', $params);
			$msg = "등록 되었습니다.";
			$func	= "parent.location.replace('../order/download_list');";
		}
		openDialogAlert($msg,400,140,'parent',$func);

	}


	public function download_delete(){
		$seq = $_POST['seq'];
		$result = $this->db->delete('fm_exceldownload', array("seq"=>$seq,"provider_seq"=>$this->providerInfo['provider_seq']));
		openDialogAlert("삭제되었습니다.",400,140,'parent',"parent.location.reload();");
	}

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

		$this->excelmodel->get_exceldownload($form_seq);
		$this->excelmodel->exceldownload($result, $provider_data);
	}

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

		$data_order = $this->ordermodel->get_order($_POST['order_seq']);
		$minfo = $this->session->userdata('manager');
		$manager_seq = $minfo['manager_seq'];

		if( !in_array($data_order['step'],array('25','35','40','45','50','60','70')) ){
			openDialogAlert($this->arr_step[$data_order['step']]."에서는 환불신청을 하실 수 없습니다.",400,140,'parent');
			exit;
		}

		if(!$_POST['chk_seq']){
			openDialogAlert("결제취소/환불 신청할 상품을 선택해주세요.",400,140,'parent');
			exit;
		}

		$order_total_ea = $this->ordermodel->get_order_total_ea($_POST['order_seq']);

		$cancel_total_ea = 0;
		foreach($_POST['chk_ea'] as $k=>$v){
			if(!$v){
				openDialogAlert("결제취소/환불 신청할 수량을 선택해주세요.",400,140,'parent');
				exit;
			}
			$cancel_total_ea += $v;
		}

		/* 신용카드 자동취소 */
		if($_POST['manual_refund_yn']=='y' && $data_order['payment']=='card' && $order_total_ea==$cancel_total_ea)
		{
			$pgCompany = $this->config_system['pgCompany'];

			$cancelFunction = "{$pgCompany}_cancel";
			$cancelResult = $this->refundmodel->$cancelFunction($data_order,array('refund_reason'=>$_POST['refund_reason'],'cancel_type'=>'full'));

			if(!$cancelResult['success']){
				openDialogAlert("{$pgCompany} 결제 취소 실패<br /><font color=red>{$cancelResult['result_code']} : {$cancelResult['result_msg']}</font>",400,160,'parent','');
				exit;
			}
			$_POST['cancel_type'] = 'full';
		}else if($order_total_ea==$cancel_total_ea){
			$_POST['cancel_type'] = 'full';
		}else{
			$_POST['cancel_type'] = 'partial';
		}

		$data = array(
			'order_seq' => $_POST['order_seq'],
			'bank_name' => $_POST['bank_name'],
			'bank_depositor' => $_POST['bank_depositor'],
			'bank_account' => $_POST['bank_account'],
			'refund_reason' => $_POST['refund_reason'],
			'refund_type' => 'cancel_payment',
			'cancel_type' => $_POST['cancel_type'],
			'regist_date' => date('Y-m-d H:i:s'),
			'manager_seq' => $manager_seq,
		);

		$items = array();

		// 출고량 업데이트를 위한 변수선언
		$r_reservation_goods_seq = array();

		foreach($_POST['chk_seq'] as $k=>$v){
			$items[$k]['item_seq']		= $_POST['chk_item_seq'][$k];
			$items[$k]['option_seq']	= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
			$items[$k]['suboption_seq']	= $_POST['chk_suboption_seq'][$k];
			$items[$k]['ea']			= $_POST['chk_ea'][$k];

			if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){
				$mode = 'option';
				$this->ordermodel->set_step_ea(85,$items[$k]['ea'],$items[$k]['option_seq'],$mode);

				$query = "select o.*, i.goods_seq from fm_order_item_option o, fm_order_item i  where o.item_seq=i.item_seq and o.item_option_seq=?";
				$query = $this->db->query($query,array($items[$k]['option_seq']));
				$optionData = $query->row_array();

				if($optionData['ea']==$optionData['step85']){
					$this->db->set('step','85');
				}

				$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
				$this->db->where('item_option_seq',$items[$k]['option_seq']);
				$this->db->update('fm_order_item_option');

				// 출고량 업데이트를 위한 변수정의
				if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
					$r_reservation_goods_seq[] = $optionData['goods_seq'];
				}

			}else if($items[$k]['suboption_seq']){
				$mode = 'suboption';
				$this->ordermodel->set_step_ea(85,$items[$k]['ea'],$items[$k]['suboption_seq'],$mode);

				$query = "select o.*, i.goods_seq from fm_order_item_suboption o, fm_order_item i  where o.item_seq=i.item_seq and o.item_suboption_seq=?";
				$query = $this->db->query($query,array($items[$k]['suboption_seq']));
				$optionData = $query->row_array();

				if($optionData['ea']==$optionData['step85']){
					$this->db->set('step','85');
				}

				$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
				$this->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
				$this->db->update('fm_order_item_suboption');


				// 출고량 업데이트를 위한 변수정의
				if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
					$r_reservation_goods_seq[] = $optionData['goods_seq'];
				}
			}

		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		$this->ordermodel->set_order_step($_POST['order_seq']);
		$refund_code = $this->refundmodel->insert_refund($data,$items);

		/* 신용카드 자동취소 */
		if($_POST['manual_refund_yn']=='y' && $data_order['payment']=='card' && $order_total_ea==$cancel_total_ea)
		{
			$this->load->model('emoneymodel');
			$this->load->model('membermodel');
			$this->load->model('couponmodel');
			$this->load->model('promotionmodel');
			$this->load->helper('text');

			$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
			$data_member		= $this->membermodel->get_member_data($data_order['member_seq']);

			//상품별 쿠폰/프로모션코드 복원
			foreach($_POST['chk_seq'] as $k=>$v){
				$items[$k]['item_seq']		= $_POST['chk_item_seq'][$k];
				$items[$k]['option_seq']	= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
				$items[$k]['suboption_seq']	= $_POST['chk_suboption_seq'][$k];
				$items[$k]['ea']			= $_POST['chk_ea'][$k];

				if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){
					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();

					/* 쿠폰 복원*/
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

			/* 배송비쿠폰 복원*/
			if($data_order['download_seq']){
				$shippingcoupon = $this->couponmodel->restore_used_coupon($data_order['download_seq']);
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
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp248",$refund_code), // [복원]결제취소(%s)에 의한 마일리지 환원
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
						'memo'		=> "[복원]결제취소({$refund_code})에 의한 예치금 환원",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp249",$refund_code), // [복원]결제취소(%s)에 의한 예치금 환원
					);
					$this->membermodel->cash_insert($params, $data_order['member_seq']);
					$this->ordermodel->set_cash_use($data_order['order_seq'],'return');
				}
			}

			$saveData = array(
				'adjust_use_coupon'		=> $data_order['coupon_sale'],
				'adjust_use_promotion'		=> $data_order['shipping_promotion_code_sale'],
				'adjust_use_emoney'		=> $data_order['emoney'],
				'adjust_use_cash'		=> $data_order['cash'],
				'adjust_use_enuri'		=> $data_order['enuri'],
				'refund_method'			=> 'card',
				'refund_price'			=> $data_order['settleprice'],
				'status'				=> 'complete',
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
			$params = array();
			$params['shopName'] = $this->config_basic['shopName'];
			$params['ordno']	= $data_order['order_seq'];
			$params['user_name'] = $data_order['order_user_name'];
			sendSMS($data_order['order_cellphone'], 'cancel', '', $params);

			$logTitle	= "결제취소";
			$logDetail	= "신용카드 전체취소처리하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($_POST['order_seq'],'process',$this->providerInfo['provider_log_name'],$logTitle,$logDetail,$logParams);
			//$this->ordermodel->set_log($_POST['order_seq'],'process',$this->providerInfo['provider_log_name'],$logTitle,$logDetail);

			$callback = "
			parent.closeDialog('order_refund_layer');
			parent.document.location.reload();
			";
			openDialogAlert("신용카드 결제취소가 완료되었습니다.",400,140,'parent',$callback);
		}else{

			$logTitle	= "환불신청";
			$logDetail	= "결제취소/환불신청하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($_POST['order_seq'],'process',$this->providerInfo['provider_log_name'],$logTitle,$logDetail,$logParams);
			//$this->ordermodel->set_log($_POST['order_seq'],'process',$this->providerInfo['provider_log_name'],$logTitle,$logDetail);

			$callback = "
			parent.closeDialog('order_refund_layer');
			parent.document.location.reload();
			";
			openDialogAlert("결제취소/환불 신청이 완료되었습니다.",400,140,'parent',$callback);
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

		$logTitle	= "환불신청";
		$logDetail	= "결제취소/환불(기타) 신청하였습니다.";
		$this->ordermodel->set_log($_POST['order_seq'],'process',$this->providerInfo['provider_log_name'],$logTitle,$logDetail);

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
		$this->load->model("naverpaymodel");

		$cfg_order			= config_load('order');
		$minfo				= $this->session->userdata('provider');
		$manager_seq		= $minfo['provider_seq'];
		$data_order			= $this->ordermodel->get_order($_POST['order_seq']);
		$data_order_items	= $this->ordermodel->get_item($_POST['order_seq']);

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

		if(!$npay_use){
			$this->validation->set_rules('cellphone[]', '휴대폰','trim|required|numeric|max_length[4]|xss_clean');
		}

		if($_POST['return_method'] == 'shop'){
			$this->validation->set_rules('return_recipient_zipcode[]', '우편번호','trim|required|numeric|max_length[4]|xss_clean');
			$this->validation->set_rules('return_recipient_address', '주소','trim|required|xss_clean');
			$this->validation->set_rules('return_recipient_address_detail', '상세주소','trim|required|xss_clean');
		}

		$err = $this->validation->error_array;
		if($this->validation->exec()===false && $err){
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		/*
		if( !in_array($data_order['step'],array(55,60,65,70,75)) ){
			openDialogAlert($this->arr_step[$data_order['step']]."에서는 반품신청을 하실 수 없습니다.",400,140,'parent');
			exit;
		}
		*/

		// 반품완료 시 구매자부담에 실결제가격이 반품배송비보다 적은경우 처리안되게함 :: 2018-07-16 pjw
		// 총 반송배송비
		$total_pay_shipping = 0;
		foreach($_POST['pay_shiping_cost'] as $pay_shipping){
			$total_pay_shipping += $pay_shipping;
		}

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

		if($total_payment_amount < $total_pay_shipping && $_POST['refund_ship_duty'] == 'buyer'
			&& $_POST['refund_ship_type'] == 'M'){
			openDialogAlert(getAlert('mo154'),400,140,'parent');
			exit;
		}

		$export_codes = array();
		foreach($_POST['chk_export_code'] as $k => $chk_export_code){
			$cancelquery = "select * from fm_order_item where item_seq=?";
			$cancelquery = $this->db->query($cancelquery,array($_POST['chk_item_seq'][$k]));
			$orditemData = $cancelquery->row_array();

			if($_POST['option_seq'][$k] && !$_POST['suboption_seq'][$k]){
				//티켓상품의 취소(환불) 가능여부::반품
				if ( $orditemData['goods_kind'] == 'coupon'){
					continue;
				}
			}
			if(!in_array($chk_export_code,$export_codes)) $export_codes[] = $chk_export_code;
		}

		## 반품가능 수량 admin @2015-06-05 pjm
		## 출고수량(출고완료 + 배송중 + 배송완료) - 반품수량
		$partner_return				= array();		//외부연동몰(npay) 반품접수 결과
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


			## 출고수량
			$exp_data			= $this->exportmodel->get_export_item_ea($export_code,$option_seq,$suboption_seq);

			if(!$suboption_seq) $return_item = $this->returnmodel->get_return_item_ea($item_seq,$option_seq,$export_code);
				else $return_item = $this->returnmodel->get_return_subitem_ea($item_seq,$suboption_seq,$export_code);

			$able_return_ea	= $exp_data['ea'] - $return_item['ea'];
			$able_return_total += $able_return_ea;

			if($able_return_ea == 0){
				openDialogAlert($mode_title." 가능한 수량이 없습니다.",400,140,'parent');
				exit;
			}

			if($able_return_ea < $return_apply_ea){
				openDialogAlert($mode_title." 수량이 ".$mode_title."가능수량보다 많습니다.",400,140,'parent');
				exit;
			}

			$partner_return['items'][$k]	= true;

			$_POST['npay_order_id']			= '';
			$_POST['npay_flag']				= '';

			## npay 사용시 api 반품 접수(상품주문번호,반품사유코드,수거배송방법코드)
			if($_POST['mode']=='return'){

				# 추가옵션이 모두 반품된 후 필수옵션반품 가능.
				$kk = count($_POST['chk_npay_product_order_id']) - ($k + 1);
				$npay_product_order_id	= $_POST['chk_npay_product_order_id'][$kk];	//npay 상품주문번호
				
				$actor = "";
				if($this->managerInfo['mname']) {
					$actor = $this->managerInfo['mname'];
				} else {
					$actor = $this->providerInfo['provider_name'];
				}

				if($npay_product_order_id && $npay_use){
					$_POST['refund_ship_duty'] = $this->returnmodel->get_npay_ship_duty($this->input->post('reason'));
					$npay_params = array("npay_product_order_id"=>$npay_product_order_id,
										"order_seq"			=>$data_order['order_seq'],
										"actor"				=>$actor,
										"reason"			=>$_POST['reason'],
										"return_method"		=>$_POST['return_method']);
					$npay_res = $this->naverpaymodel->order_return($npay_params);
					if($npay_res['result'] != "SUCCESS"){
						$items[$k]['partner_return']	= false;
						$partner_return['items'][$k]	= false;
						$partner_return['partner_name']	= "네이버페이";
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

		$items					= array();
		$pay_shiping_cost		= array();
		foreach($chk_seq as $k=>$v){

			$items[$k]['item_seq']				= $_POST['chk_item_seq'][$k];
			$items[$k]['option_seq']			= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
			$items[$k]['suboption_seq']			= $_POST['chk_suboption_seq'][$k];
			$items[$k]['ea']					= $_POST['chk_ea'][$k];
			$items[$k]['npay_product_order_id']	= $_POST['chk_npay_product_order_id'][$k];
			$items[$k]['partner_return']		= true;

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
				$pay_shiping_cost[$export_code] = (float)$_POST['pay_shiping_cost'][$k];

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

					/*
					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();
					*/

					if($_POST['mode']=='return'){
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

					if($result[0]['top_item_seq'])
						$items[$k]['item_seq'] = $result[0]['top_item_seq'];

					/*
					$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
					$query = $this->db->query($query,array($items[$k]['suboption_seq']));
					$optionData = $query->row_array();
					*/

					if($_POST['mode']=='return'){
						$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
						$this->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
						$this->db->update('fm_order_item_suboption');
					}
				}
			}
		}

		// 환불배송비 계산 :: 2018-05-21 lwh
		$_POST['return_shipping_price']		= ($_POST['refund_ship_type']) ? array_sum($pay_shiping_cost) : 0;

		//외부몰(npay) 반품접수 실패건수가 있을때
		if($npay_use && $_POST['mode']=='return' && $partner_return['fail_cnt']> 0){
			//반품접수 전체 실패시 오류메세지 띄움
			if((count($items) - $partner_return['fail_cnt']) <= 0){
				if(count($partner_return['msg']) < 1) $h = 140; else $h = 150 + (count($partner_return['msg'])*18);
				openDialogAlert("<span class=\'fx12\'>".$partner_return['partner_name']." 반품접수 실패!<br /><span class=\'red\'>".implode("<br />",$partner_return['msg'])."</span></span>",460,$h,'parent');
				exit;
			}
		}

		if($_POST['mode']=='exchange'){
			$refund_code = '0';
			$return_type = 'exchange';
		}else{

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

			$data = array(
				'order_seq'			=> $orgin_order_seq,
				'bank_name'			=> $bank,
				'bank_depositor'	=> $depositor,
				'bank_account'		=> $account,
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
			$this->ordermodel->set_log($orgin_order_seq,'process',$this->providerInfo['provider_log_name'],$logTitle,$logDetail,$logParams);
		}


		// 환불, 반품(&교환) DB Insert
		$return_code = $this->returnmodel->order_return_insert($_POST,$refund_code,$return_type,$partner_return);

		if(!$return_code){
			$res_msg = " 실패";
		}
		/*
		if($_POST['phone'][1] && $_POST['phone'][2]) $phone = implode('-',$_POST['phone']);
		if($_POST['cellphone'][1] && $_POST['cellphone'][2]) $cellphone = implode('-',$_POST['cellphone']);
		$zipcode = "";
		if($_POST['return_recipient_zipcode'][1]) $zipcode = implode('-',$_POST['return_recipient_zipcode']);

		// 반품 등록
		$insert_data['status'] 			= 'request';
		$insert_data['order_seq'] 		= $_POST['order_seq'];
		$insert_data['refund_code'] 	= $refund_code;
		$insert_data['return_type'] 	= $return_type;
		$insert_data['return_reason'] 	= $_POST['reason_detail'];
		$insert_data['cellphone'] 		= $cellphone;
		$insert_data['phone'] 			= $phone;
		$insert_data['return_method'] 	= $_POST['return_method'];
		$insert_data['sender_zipcode'] 	= $zipcode;
		$insert_data['sender_address_type'] 	= $_POST['return_recipient_address_type'];
		$insert_data['sender_address'] 	= $_POST['return_recipient_address']?$_POST['return_recipient_address']:'';
		$insert_data['sender_address_street'] 	= $_POST['return_recipient_address_street'];
		$insert_data['sender_address_detail'] = $_POST['return_recipient_address_detail']?$_POST['return_recipient_address_detail']:'';
		$insert_data['regist_date'] 	= date('Y-m-d H:i:s');
		$insert_data['important'] 		= 0;
		$insert_data['manager_seq'] 	= 1;
		$insert_data['shipping_price_depositor'] 	= $_POST['shipping_price_depositor'];
		$insert_data['shipping_price_bank_account'] = $_POST['shipping_price_bank_account'];

		$items = array();
		foreach($_POST['chk_seq'] as $k=>$v){
			$items[$k]['item_seq']		= $_POST['chk_item_seq'][$k];
			$items[$k]['option_seq']			= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
			$items[$k]['suboption_seq']	= $_POST['chk_suboption_seq'][$k];
			$items[$k]['ea']			= $_POST['chk_ea'][$k];
			$items[$k]['reason_code']	= $_POST['reason'][$k];
			$items[$k]['reason_desc']		= $_POST['reason_desc'][$k];
			$items[$k]['export_code']	= $_POST['chk_export_code'][$k];
		}

		$return_code = $this->returnmodel->insert_return($insert_data,$items);
		*/

		if($_POST['mode']=='exchange'){
			if($res_msg){
				$title		= "맞교환 신청이 실패되었습니다.";
			}else{
				$title		= "맞교환 신청이 완료되었습니다.";
			}
			$logTitle	= "맞교환신청".$res_msg."(".$return_code.")";
			$logDetail	= $this->providerInfo['provider_log_name']."가 맞교환신청을".$res_msg." 하였습니다.";
		}else{
			if($res_msg){
				$title		= "반품 신청이 실패되었습니다.";
			}else{
				$title		= "반품 신청이 완료되었습니다.";
			}
			$logTitle	= "반품신청".$res_msg."(".$return_code.")";
			$logDetail	= $this->providerInfo['provider_log_name']."가 반품신청을".$res_msg." 하였습니다.";
		}

		if($partner_return['fail_cnt'] > 0){
			$partner_error_msg = $partner_return['fail_cnt']."건 실패<br />".implode("<br />",$partner_return['msg']);
			$title		.= "Naverpay 반품접수 ".$partner_error_msg;
			$logDetail	.= "<br />Naverpay 반품접수 ".$partner_error_msg;
		}

		$logParams	= array('return_code' => $return_code);
		$this->ordermodel->set_log($_POST['order_seq'],'process',$this->providerInfo['provider_log_name'],$logTitle,$logDetail,$logParams,'');

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
		$this->load->model('refundmodel');

		$cfg_order = config_load('order');

		$minfo = $this->session->userdata('provider');
		$manager_seq = $minfo['provider_seq'];

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
			$this->validation->set_rules('return_recipient_zipcode[]', '우편번호','trim|required|numeric|max_length[4]|xss_clean');
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

		foreach ($_POST['chk_ea'] as $k => $chk_ea ){
			if($chk_ea == 0 && $data_order['settleprice']> 0 ){
			openDialogAlert("환불금액이 0원인경우에는 신청되지 않습니다.",400,140,'parent');
			exit;
			}
		}


		$export_codes = array();
		foreach($_POST['chk_export_code'] as $k => $chk_export_code){
			if(!in_array($chk_export_code,$export_codes)) $export_codes[] = $chk_export_code;
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

		$items = array();
		foreach($_POST['chk_seq'] as $k=>$v){
			$cancelquery = "select * from fm_order_item where item_seq=?";
			$cancelquery = $this->db->query($cancelquery,array($_POST['chk_item_seq'][$k]));
			$orditemData = $cancelquery->row_array();

			$items[$k]['item_seq']		= $_POST['chk_item_seq'][$k];
			$items[$k]['option_seq']	= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
			$items[$k]['suboption_seq']	= $_POST['chk_suboption_seq'][$k];
			$items[$k]['ea']			= $_POST['chk_ea'][$k];

			if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){
				$mode = 'option';

				//티켓상품의 취소(환불) 가능여부::반품 ==> 입점사 제외

				if($_POST['mode']=='return'){
					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_option_seq',$items[$k]['option_seq']);
					$this->db->update('fm_order_item_option');
				}
			}else if($items[$k]['suboption_seq']){
				$mode = 'suboption';

				$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
				$query = $this->db->query($query,array($items[$k]['suboption_seq']));
				$optionData = $query->row_array();

				if($_POST['mode']=='return'){
					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
					$this->db->update('fm_order_item_suboption');
				}
			}
		}

		//$_POST['refund_method'] = ($coupon_valid_over)?'emoney':$_POST['refund_method'];//2014-10-13 사용안함

		if($_POST['mode']=='exchange'){
			$refund_code = '0';
			$return_type = 'exchange';
		}else{

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
				'order_seq' => $_POST['order_seq'],
				'bank_name' => $bank,
				'bank_depositor' => $_POST['depositor'],
				'coupon_refund_emoney' => $coupon_refund_emoney,
				'coupon_refund_price' => $coupon_remain_price,
				'bank_account' => $account,
				'refund_reason' => '반품환불',
				'refund_type' => 'return',
				'regist_date' => date('Y-m-d H:i:s'),
				'manager_seq' => $manager_seq,
				'refund_method' => $_POST['refund_method']
			);
			$refund_code = $this->refundmodel->insert_refund($data,$items);
			$return_type = 'return';

			$logTitle	= "환불신청";
			$logDetail	= $this->providerInfo['provider_log_name']." 반품신청에 의한 환불신청이 접수되었습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($_POST['order_seq'],'process',$this->providerInfo['provider_log_name'],$logTitle,$logDetail,$logParams);
		}

		/**
		* 티켓상품 반품처리 start
		**/
		if($_POST['phone'][1] && $_POST['phone'][2]) $phone = implode('-',$_POST['phone']);
		if($_POST['cellphone'][1] && $_POST['cellphone'][2]) $cellphone = implode('-',$_POST['cellphone']);
		$zipcode = "";
		if($_POST['recipient_zipcode'][1]) $zipcode = implode('-',$_POST['recipient_zipcode']);

		//티켓상품 반품등록
			$insert_data['status'] 				= 'complete';//티켓상품 반품완료
		$insert_data['order_seq'] 					= $_POST['order_seq'];
		$insert_data['refund_code'] 				= $refund_code;
		$insert_data['return_type'] 					= $return_type;
		$insert_data['return_reason'] 				= $_POST['reason_detail'];
		$insert_data['cellphone'] 					= $cellphone;
		$insert_data['phone'] 							= $phone;
		$insert_data['return_method'] 			= $_POST['return_method'];
		$insert_data['sender_zipcode'] 			= $zipcode;
		$insert_data['sender_address_type'] 	= $_POST['recipient_address_type'];
		$insert_data['sender_address'] 				= $_POST['recipient_address']?$_POST['recipient_address']:'';
		$insert_data['sender_address_street'] 	= $_POST['recipient_address_street'];
		$insert_data['sender_address_detail'] = $_POST['recipient_address_detail']?$_POST['recipient_address_detail']:'';
		$insert_data['regist_date'] 	= date('Y-m-d H:i:s');
		$insert_data['return_date'] = date('Y-m-d H:i:s');//티켓상품 반품완료
		$insert_data['important'] 		= 0;
		$insert_data['manager_seq'] 	= 1;
		$insert_data['shipping_price_depositor'] 	= $_POST['shipping_price_depositor'];
		$insert_data['shipping_price_bank_account'] = $_POST['shipping_price_bank_account'];

		$items = array();
		foreach($_POST['chk_seq'] as $k=>$v){
			$items[$k]['item_seq']				= $_POST['chk_item_seq'][$k];
			$items[$k]['option_seq']			= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
			$items[$k]['suboption_seq']			= $_POST['chk_suboption_seq'][$k];
			$items[$k]['ea']					= $_POST['chk_ea'][$k];
			$items[$k]['reason_code']			= $_POST['reason'][$k];
			$items[$k]['reason_desc']			= $_POST['reason_desc'][$k];
			$items[$k]['export_code']			= $_POST['chk_export_code'][$k];
			$items[$k]['partner_return']		= true;
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
				$data_socialcp_confirm['order_seq'] = $data_export['order_seq'];
				$data_socialcp_confirm['export_seq'] = $data_export['export_seq'];
				if($this->managerInfo) {
					$data_socialcp_confirm['manager_seq']	= $this->managerInfo['manager_seq'];
					$data_socialcp_confirm['doer']				= $this->managerInfo['mname'];
				}else{
					$data_socialcp_confirm['manager_seq']	= $this->providerInfo['provider_seq'];
					$data_socialcp_confirm['doer']				= "입점사:".$this->providerInfo['provider_log_name'];
				}
				$this->socialcpconfirmmodel -> socialcp_confirm('admin',$socialcp_status,$export_code);//socialcp_status = 환불시 상태 6,7,8,9
				$this->socialcpconfirmmodel -> log_socialcp_confirm($data_socialcp_confirm);

				//티켓상품의 배송완료처리
				$this->exportmodel->socialcp_exec_complete_delivery($export_code, true, $coupon_remain_real_percent, $socialcp_confirm, "cancel");
			}
		}
		/**
		* 티켓상품 배송완료 end
		**/


		if($_POST['mode']=='exchange'){
			$title="맞교환 신청이 완료되었습니다.";
			$logTitle = "맞교환신청";
			$logDetail = $this->providerInfo['provider_log_name']."가 맞교환신청을 하였습니다.";
		}else{
			$title="반품이 완료되었습니다.";
			$logTitle = "반품완료";
			$logDetail = "관리자가 반품완료을 하였습니다.";
		}

		$logParams	= array('return_code' => $return_code);
		$this->ordermodel->set_log($_POST['order_seq'],'process',$this->providerInfo['provider_log_name'],$logTitle,$logDetail,$logParams);

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

	public function _exec_reverse($order_seq){

		$npay_use		= npay_useck();
		$data_order = $this->ordermodel->get_order($order_seq);
		$source_step = (string) $data_order['step'];

		# npay 주문건 주문되돌리기 불가
		if($npay_use && $data_order['pg'] == "npay"){
			return 'npay';
		}

		# 오픈마켓 연동 주문건 주문되돌리기 불가
		if($data_order['linkage_id'] == "connector"){
			return 'openmarket';
		}

		// 주문접수 상태일 경우
		if( $data_order['step'] <= 15 ){
			return false;
		}

		// 주문 무효가 아닌 경우
		if( $data_order['step'] != '95'){
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
		# npay 주문건은 주문접수로 되돌리기 안됨.
		$npayconfig	= config_load('navercheckout');
		if(in_array($npayconfig['use'],array('y','test')) && $npayconfig['version'] == '2.1'){
			$npay_use = true;
		}else $npay_use = false;

		$target_step = (string) $target_step;
		if( $data_order['step'] == 25 && ($data_order['payment'] != 'bank' || ($npay_use && $data_order['pg'] == 'npay')) ){
			return false;
		}else{
			$return = $this->ordermodel->set_reverse_step($order_seq,$target_step,$arr,$mode);

			if($return){
				// 로그
				$this->ordermodel->set_log($order_seq,'process',$this->providerInfo['provider_log_name'],'되돌리기 ('.$this->arr_step[$data_order['step']].' => '.$this->arr_step[$target_step].')','-');
			}

			return $return;
		}
	}

	public function order_reverse(){
		$order_seq = $_GET['seq'];
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
			}elseif($result === "openmarket"){
				openDialogAlert("오픈마켓 주문건은 주문상태 되돌리기가 불가합니다.",400,140,'parent','');
				exit;
			}elseif(!$result){
				openDialogAlert("잔여 마일리지가 없습니다.주문접수로 되돌릴 수 없습니다.",400,140,'parent','');
				exit;
			}
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("주문상태가 변경되었습니다.",400,140,'parent',$callback);

	}

	public function batch_reverse()
	{
		$npay_order		= false;
		$openmarket_order		= false;
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
					}elseif($result === "openmarket"){
						$res['false'][]	= $order_seq;
						$openmarket_order		= true;
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
			if($npay_order){
				$msg = "Npay 주문건은 되돌리기 할 수 없습니다.<br />".implode("<br />",$res['false']);
			}elseif($openmarket_order){
				$msg = "오픈마켓 주문건은 주문상태 되돌리기가 불가합니다.<br />".implode("<br />",$res['false']);
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
	    $this->ordermodel->set_log($order_seq,'process',$this->providerInfo['provider_log_name'],'상품준비',$log_str);
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
		$this->ordermodel->set_log($order_seq,'process',$this->providerInfo['provider_log_name'],'상품준비',$log_str);

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

		$totalcnt = $returnstepcnt = 0;
		foreach($_POST['seq'] as $order_seq){
			$totalcnt++;
			$this->db->trans_begin();
			$rollback = false;

			$params = array(
				"item.order_seq" 	=> $order_seq,
				"item.provider_seq" => $this->providerInfo['provider_seq'],
				"opt.step" 			=> '25',
			);
			$options	= $this->ordermodel->get_item_join_option($params,"option");
			if	($options)foreach($options as $o => $opt){
				$this->ordermodel->set_step35_ea($order_seq, $opt['item_option_seq'], 'option');
				$this->ordermodel->set_option_step($opt['item_option_seq'], 'option');
			}
			$suboptions	= $this->ordermodel->get_item_join_option($params,"suboption");
			if	($suboptions)foreach($suboptions as $s => $sub){
				$this->ordermodel->set_step35_ea($order_seq, $sub['item_suboption_seq'], 'suboption');
				$this->ordermodel->set_option_step($sub['item_suboption_seq'], 'suboption');
			}
			// 변경한 옵션(추가옵션) 하나도 없었다면 rollback 처리함
			if( count($options) + count($suboptions) == 0 ) {
				$rollback = true;
			} else {
				$this->ordermodel->set_order_step($order_seq);
				$log_str = "관리자가 상품준비를 하였습니다.";
				$this->ordermodel->set_log($order_seq,'process',$this->providerInfo['provider_name'],'상품준비',$log_str);
			}

			if ($this->db->trans_status() === FALSE || $rollback == true)
			{
				$this->db->trans_rollback();
			}
			else
			{
				$returnstepcnt++;
				$this->db->trans_commit();
			}
		}

		$msg = "선택된 주문건의 결제확인 주문수량이 → 상품준비로 <br />";
		$msg .= "총 ".number_format($totalcnt)."건 요청 → 성공 ".number_format($returnstepcnt)."건";
		$msg .= " , 실패".number_format($totalcnt-$returnstepcnt)."건 ";
		$msg .= "변경되었습니다.";
		echo $msg;
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
		$auth = $this->authmodel->manager_limit_act('coupon_act');
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
		$excel['stockable'] 			= $_POST['stockable'];
		$excel['export_step'] 		= $_POST['export_step'];
		$excel['ticket_stockable'] 	= $_POST['ticket_stockable'];
		$excel['ticket_step'] 		= $_POST['ticket_step'];
		$excel['export_date'] 		= $_POST['export_date'];
		$excel['input_mode']  = 'excel';
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
					if ($option_key != $_POST['each_item_option_seq']) {
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
		$auth = $this->authmodel->manager_limit_act('order_goods_export');
		if(!$auth){
			openDialogAlert( '관리자 권한이 없습니다.' ,300,150,'parent','parent.opener.location.reload();parent.window.close();');
			exit;
		}

		$this->load->model('order2exportmodel');

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
		$arr_npay_flag_release		= $export_param['npay_flag_release'];	//npay 보류 사유

		// 배송 출고 데이터 추가 작업 :: 2016-10-06 lwh
		$arr_export_data['group']		= $export_param['export_shipping_group'];
		$arr_export_data['method']		= $export_param['export_shipping_method'];
		$arr_export_data['set_name']	= $export_param['export_shipping_set_name'];
		$arr_export_data['scm_type']	= $export_param['export_store_scm_type'];
		$arr_export_data['address_seq'] = $export_param['export_address_seq'];
		$provider_seq                   = $this->providerInfo['provider_seq'];

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
			'provider_seq'	=>	$provider_seq
		);
		$result_check = $this->order2exportmodel->order_export($params_order_export);

		if($result_check[1]){
			foreach($result_check[1] as $data){
				$tmp_export_error[$data['step']][$data['shipping_seq']] = true;
				$tmp_export_error_msg[$data['step']][]						= $data['msg'];
				$tmp_export_request[$data['step']][$data['shipping_seq']] = true;
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

		foreach($result_check[2] as $data){
			if( array_sum($data['items']['ea'] ) > 0 ){
				$tmp_export_request[$data['status']][$data['shipping_seq']] = true;
				$tmp_export_success[$data['status']][$data['shipping_seq']] = true;
			}
		}

		if($export_param['check_mode'] == 'check'){

			$bundle_mode	= ($_POST['bundle_mode'] == 'bundle') ? 'bundle' : '';

			$msg_height = 220;

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
					parent.openDialogConfirm('".nl2br($msg)."',500,320,function(){
						parent.upload_excel();
					},function(){},params);
				</script>
				");
			}else{
				echo("
				<script>
					parent.loadingStop();
					var params = {'yesMsg':'[예] 출고처리 실행','noMsg':'[아니오] 출고처리 취소'}
					parent.openDialogConfirm('".nl2br($msg)."',500,320,function(){
						parent.batch_export('{$bundle_mode}');						
					},function(){},params);
				</script>
				");
			}
			exit;
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
			if($err_msg_45) $export_error_msg = "출고준비 ".$err_msg_45;
			if($err_msg_55) $export_error_msg .= "출고완료 ".$err_msg_55;
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

		// 출고처리
		$export_params = $result_check[2];

		if( $export_params ){
			$result_export = $this->order2exportmodel->goods_export($export_params,$cfg);

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
								$tmp_export_error[$export_status][$export_item_seq] = "ERROR";
							}else{
								$arr_explode_code[$goods_kind][$export_status][ $tmp_explode_code ] = $tmp_explode_code;
								$arr_explode_code_all[ $tmp_explode_code ]							= $tmp_explode_code;
							}
						}
						// 오류메세지는 1개만 보여줌 @2016-03-11 pjm
						if($result_export3['message'] && !$export_error_msg) $export_error_msg .= $result_export3['message'];
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

		$msg = "처리 결과는 아래와 같습니다.";
		$msg .= "<br/>출고준비 ".number_format($cnt_export_request_45)."건 요청 → 성공 ".number_format($cnt_export_result_goods_45)."건";
		$msg .= " ,실패".number_format($cnt_export_error_45)."건";
		$msg .= "<br/>출고완료 ".number_format($cnt_export_request_55)."건 요청 → 성공 ".number_format($cnt_export_result_coupon_55+$cnt_export_result_goods_55)."건";
		$msg .= " ,실패".number_format($cnt_export_error_55)."건";

		//출고 실패사유 노출(npay) @2016-01-27 pjm
		if($tmp_export_error_msg[55]) $msg .= $export_error_msg;

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

		if($export_param['bundle_mode'] == 'bundle'){
			echo "<script>".$callback."</script>";
		}else{
			echo "<script>".$callback."parent.window.opener.location.reload();</script>";
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

		$order_seq			= $_POST['order_seq'];
		$old_item_seq		= $_POST['old_item_seq'];
		$old_option_seq		= $_POST['old_option_seq'];
		$member_seq			= $_POST['member_seq'];
		$cart_table			= $_POST['cart_table'];
		$displayId			= $_POST['displayId'];
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
					$data['goods_code']	= $goods_code.$data_option['optioncode'.$j];
					$data['package_yn']	= $option_package_yn;
					$export_option[] = $data;
				}
				$this->ordermodel->update_option($old_option_seq,$goods['provider_seq'],$export_option);

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
			}

			$this->goodsmodel->modify_reservation_real($goods_seq);

			//재매칭 로그
			$logtitle = "상품재매칭".$logstr2;
			$logstr = "상품(옵션)이 재매칭 되었습니다.";
			if($old_option_data['goods_seq'] != $goods_seq){
				$logtitle .= "(상품변경:".$old_option_data['goods_seq']."->".$goods_seq.")";
			}

			$actor = "";
			if($this->managerInfo['mname']) {
				$actor = $this->managerInfo['mname'];
			} else {
				$actor = $this->providerInfo['provider_name'];
			}

			$this->ordermodel->set_log($order_seq,'process',$actor,$logtitle,$logstr);

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
			
			$actor = "";
			if($this->managerInfo['mname']) {
				$actor = $this->managerInfo['mname'];
			} else {
				$actor = $this->providerInfo['provider_name'];
			}
			$this->ordermodel->set_log($order_seq,'process',$actor,$log,serialize($data));
			$callback = '';
			openDialogAlert("개인통관고유부호를 변경하였습니다.",400,140,'parent',$callback);
		}
	}


}

/* End of file order_process.php */
/* Location: ./app/controllers/selleradmin/order_process.php */