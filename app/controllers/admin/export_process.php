<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class export_process extends admin_base {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->helper('order');
		$this->load->helper('shipping');

		$this->arr_step 	= config_load('step');

		$auth = $this->authmodel->manager_limit_act('order_goods_export');
		if(!$auth){
			openDialogAlert( '관리자 권한이 없습니다.' ,300,150,'parent','parent.location.reload();');
			exit;
		}

	}

	public function exec_complete_export($export_code,$cfg_order=''){
		return $this->exportmodel->exec_complete_export($export_code,$cfg_order);
	}

	// 배송중 처리
	public function exec_going_delivery($export_code,$mode=''){
		return $this->exportmodel->exec_going_delivery($export_code,$mode);
	}

	public function exec_complete_delivery($export_code){
		$this->exportmodel->exec_complete_delivery($export_code);
	}

	public function going_delivery(){
		$export_code = $_POST['export_code'];
		$this->exec_going_delivery($export_code);
		openDialogAlert("배송중처리가 완료되었습니다.",400,140,'parent',"parent.location.reload();");
	}

	public function complete_delivery(){
		$this->exportSmsData = array();
		$export_code = $_POST['export_code'];
		$this->exec_complete_delivery($export_code);

		if(count($this->exportSmsData) > 0){
			commonSendSMS($this->exportSmsData);
		}

		openDialogAlert("배송완료가 완료되었습니다.",400,140,'parent',"parent.location.reload();");
	}

	public function complete_export(){

		$this->exportSmsData = array();

		$export_code	= $_POST['export_code'];
		$result			= $this->exec_complete_export($export_code);

		if(count($this->exportSmsData) > 0){
			commonSendSMS($this->exportSmsData);
		}

		if(!$result){
			openDialogAlert("출고완료가 완료되었습니다.",400,140,'parent',"parent.location.reload();");
		}else{
			openDialogAlert("‘출고수량’ 보다 ‘재고수량’이 부족합니다.<br/>출고가 가능한 수량으로 조정해 주세요!",400,150,'parent',"parent.location.reload();");
		}
	}

	// 일괄처리
	public function batch_status(){

		$cfg_order = config_load('order');
		$cfg_order['runout'] = 'unlimited';

		# npay 주문건 출고완료 변경 불가 처리
		$npay_use = npay_useck();

		if($_POST['stockable']=='limit'){
			$cfg_order['runout'] = 'stock';
		}

		if($_POST['status'] == '45'){
			$mode			= "complete_export";
			$mode_from		= "출고준비";
			$mode_to		= "출고완료";
			$mode_to_status = 55;
		}else if($_POST['status'] == '55'){
			$mode			= "going_delivery";
			$mode_from		= "출고완료";
			$mode_to		= "배송중";
			$mode_to_status = 65;
		}else if($_POST['status'] == '65'){
			$mode			= "complete_delivery";
			$mode_from		= "배송중";
			$mode_to		= "배송완료";
			$mode_to_status = 75;
		}else{
			$mode			= "save";
		}

		if( $_POST['codes'] ){
			$_POST['export_code'] = explode('|',$_POST['codes']);
		}

		// 개별출고처리
		if( $_POST['each_export_code'] )
		{
			$_POST['export_code'] = array($_POST['each_export_code']);
		}

		if( $_POST['market_mode'] == 'y' ) {
			$_POST['export_code'] = explode('|',$_POST['err_codes']);
		}

		if($_POST['export_code']){
			$_POST['export_code'] = array_unique($_POST['export_code']);
		}

		$npay_export = array();
		foreach($_POST['export_code'] as $export_code)
		{

			$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';

			$field = array();
			$bind = array();

			//출구완료시에만 출고일 업데이트합니다. @2016-07-29 ysm
			if ( $mode == "complete_export" ) {
				$field[] = "export_date = ?";
				$bind[] = ($_POST['export_date'])?$_POST['export_date']:date("Y-m-d");
			}

			if( $_POST['export_shipping_method'][$export_code] ){
				$field[] = "delivery_number = ?";
				$field[] = "delivery_company_code = ?";
				$field[] = "international = 'domestic'";
				$field[] = "domestic_shipping_method = ?";
				// 배송정보 추가분 :: 2016-10-10 lwh
				$field[] = "shipping_group = ?";
				$field[] = "shipping_method = ?";
				$field[] = "shipping_set_name = ?";
				$field[] = "store_scm_type = ?";
				$field[] = "shipping_address_seq = ?";

				$bind[] = $_POST['delivery_number'][$export_code];
				$bind[] = $_POST['delivery_company'][$export_code];
				$bind[] = $_POST['export_shipping_method'][$export_code];
				// 배송정보 추가분 :: 2016-10-10 lwh
				$bind[] = $_POST['export_shipping_group'][$export_code];
				$bind[] = $_POST['export_shipping_method'][$export_code];
				$bind[] = $_POST['export_shipping_set_name'][$export_code];
				$bind[] = $_POST['export_store_scm_type'][$export_code];
				$bind[] = $_POST['export_address_seq'][$export_code];
			}
			
			// 상태변경일시 업데이트 추가 @2017-02-09 nsg
			$field[] = "status_date = ?";
			$bind[] = date('Y-m-d H:i:s');

			$bind[] = $export_code;
			$query = "
			update fm_goods_export set ".implode(",",$field)." where {$export_field} = ?";
			$this->db->query($query,$bind);

		}

		$result_msg			= '';
		$r_err_export_code	= array();
		foreach($_POST['export_code'] as $code){
			if( $mode != 'save' ){

				if( $mode == 'complete_export' ){
					$cfg_order['scm_wh']        = $_POST['scm_wh'];
					$err_export = $this->exec_complete_export($code,$cfg_order);
					if(!$err_export['result'])
					{
						$r_err_export_code[] = $code;
						if(!$result_msg) $result_msg = $err_export['msg'];
					}
				}else if( $mode == 'going_delivery' ){
					$market_mode = isset($_POST['selectMarkets']) ? 'y' : ''; 
					$export_mode = ($_POST['market_mode'] == 'y') ? 'market' : ''; 
					$err_export = $this->{'exec_'.$mode}($code, $export_mode);
					if(!$err_export['result'])
					{
						$r_err_export_code[] = $code;
						if(!$result_msg) $result_msg = $err_export['msg'];
					}
				}else{
					$this->{'exec_'.$mode}($code);
				}
			}
		}

		if($mode == "complete_export") {

			if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
			// 물류관리 매장 재고 전송
			if	($this->scm_cfg['use'] == 'Y'){
				$this->load->model('scmmodel');
				if	($this->scmmodel->tmp_scm['wh_seq'] > 0){
					$sendResult		= $this->scmmodel->change_store_stock($this->scmmodel->tmp_scm['goods'], array($this->scmmodel->tmp_scm['wh_seq']), '');
				}
			}

			/* 출고자동화 전송 (우체국택배 자동화 전송 포함시킴 : 2016-04-05 lwh)*/
			$this->load->model('invoiceapimodel');
			$invoiceExportResult = $this->invoiceapimodel->export($this->exportCompleteCode);
		}

		if(count($this->exportSmsData) > 0){
			commonSendSMS($this->exportSmsData);
		}

		$err_cnt = count($r_err_export_code);
		$req_cnt = count($_POST['export_code']);

		if($result_msg) $result_msg = urlencode($result_msg);

		$msg = "처리 결과는 아래와 같습니다.";
		$msg .= "<br/>".$mode_to." ".number_format($req_cnt)."건 요청 → 성공 ".number_format($req_cnt - $err_cnt)."건";
		$msg .= " ,실패 ".number_format($err_cnt)."건";

		$result_obj = "{";
		$result_obj .= "'err_cnt':".$err_cnt;
		$result_obj .= ",'req_cnt':".$req_cnt;
		$result_obj .= ",'export_result_msg':'".$result_msg."'";
		$result_obj .= ",'market_mode':'".$market_mode."'";
		$result_obj .= ",'err_export_code':'".implode('|',$r_err_export_code)."'";
		$result_obj .= "}";

		if($_POST['export_code']){
			$str_goods_export_code = implode('|',$_POST['export_code']); // 실물출고코드합치기
		}

		$callback = "parent.close_export_popup();parent.batch_status_popup(".$mode_to_status.",'".$str_goods_export_code."',0,".$result_obj.");";

		if( $mode == 'save'){
			openDialogAlert("변경 정보가 저장 되었습니다.",400,140,'parent',"parent.location.reload();");
		}else{
			echo "<script>".$callback."parent.window.opener.location.reload();</script>";
		}
	}

	public function export_modify(){

		foreach($_POST['export_shipping_group'] as $export_code => $shipping_group){

			$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';

			$update_param = array();
			$update_param['shipping_group']			= $shipping_group;
			$update_param['shipping_method']		= $_POST['export_shipping_method'][$export_code];
			$update_param['shipping_set_name']		= $_POST['export_shipping_set_name'][$export_code];
			$update_param['store_scm_type']			= $_POST['export_store_scm_type'][$export_code];
			$update_param['shipping_address_seq']	= $_POST['export_address_seq'][$export_code];

			if($_POST['delivery_company_code'][$export_code]){
				if( $_POST['export_date'][$export_code] ) $update_param['export_date'] = $_POST['export_date'][$export_code];
				$update_param['delivery_company_code'] = $_POST['delivery_company_code'][$export_code];
				$update_param['delivery_number'] = $_POST['delivery_number'][$export_code];

				if($_POST['delivery_company_code'][$export_code]){
					# 오픈마켓 송장등록 #
					$this->load->model('openmarketmodel');
					$this->openmarketmodel->request_send_export($export_code);
				}
			}

			$this->db->update('fm_goods_export', $update_param, array($export_field=>$export_code));
		}

		// 해외 배송 관련 -> 추후 수정되어야 함
		if($_POST['international_shipping_method']){
			foreach($_POST['international_shipping_method'] as $export_code => $international_company_code)
			{
				foreach($_POST['international_shipping_method'][$export_code] as $k => $international_company_code)
				{
					$update_param = array();
					if( $_POST['export_date'][$export_code][$k] ) $update_param[] = $_POST['export_date'][$export_code][$k];
					$update_param[] = $_POST['international_shipping_method'][$export_code][$k];
					$update_param[] = $_POST['international_delivery_no'][$export_code][$k];
					$this->db->update('fm_goods_export', $update_param,array('export_code'=>$export_code));
				}
			}
		}
		openDialogAlert("출고정보가 변경되었습니다.",400,140,'parent',"top.location.reload();");
	}

	public function ea_modify(){
		if($_POST['ea']){
			foreach($_POST['ea'] as $export_item_seq => $export_ea){
				$export_item = $this->exportmodel->get_export_item_by_item_seq($export_item_seq);
				if($export_item['option_seq']){
					$item = $this->ordermodel->get_order_item_option($export_item['option_seq']);
					$option_mode='option';
					$option_seq = $export_item['option_seq'];

				}else{
					$item = $this->ordermodel->get_order_item_suboption($export_item['suboption_seq']);
					$option_mode='suboption';
					$option_seq = $export_item['suboption_seq'];
				}
				$order_seq = $item['order_seq'];

				// 수량 변경 가능 수량
				$limit_ea = $item['step35']+ $export_item['ea'];
				if($limit_ea < $export_ea){
					openDialogAlert("출고가능 최대 수량은 ".$limit_ea."개 입니다.",400,140,'parent',"parent.location.reload();");
					exit;
				}

				if($export_ea == 0){
					$export_items = $this->exportmodel->get_export_item($export_item['export_code']);
					if(count($export_items) == 1){
						openDialogAlert("마지막 출고 상품은 수량을 0으로 변경하실 수 없습니다.",400,140,'parent',"parent.location.reload();");
						exit;
					}
					$this->exportmodel->delete_export_item_by_item_seq($export_item_seq);
				}else{
					$this->exportmodel->update_ea_export_item($export_item_seq,$export_ea);
				}

				$this->ordermodel->set_step_ea(45,$export_ea-$export_item['ea'],$option_seq,$option_mode);
				$this->ordermodel->set_option_step($option_seq,$option_mode);
				$this->ordermodel->set_order_step($order_seq);
			}
		}
		openDialogAlert("수량이 변경되었습니다.",400,140,'parent',"parent.location.reload();");
	}

	public function reverse_export()
	{
		# npay 사용확인
		$npay_use		= npay_useck();
		$talkbuy_use 	= talkbuy_useck();	//톡체크아웃 사용여부
		$export_code	= $_POST['export_code'];
		$data_export	= $this->exportmodel->get_export($export_code);

		if($npay_use && $data_export['npay_order_id']){
			echo "Npay 주문건은 되돌리기 할 수 없습니다.<br />".$data_export['npay_order_id'];
			exit;
		}
	
		if ($talkbuy_use && $data_export['talkbuy_order_id']) {
			echo '톡체크아웃 주문건은 되돌리기 할 수 없습니다.<br />' . $data_export['talkbuy_order_id'];
			exit;
		}

		$except = array();
		$openmarket_order = array();
		if(preg_match('/^B/', $export_code)){
			$export_list	= $this->exportmodel->get_export_bundle($export_code);
			foreach((array)$export_list['bundle_order_info'] as $now_export_code => $order_seq){
				$reverse = $this->exec_reverse_export($now_export_code);
				if	($reverse == 'coupon')	$except[]	= $now_export_code;
				elseif	($reverse == 'openmarket')	$openmarket_order[]	= $now_export_code;
				else						$result		= $reverse;
			}
		}else{
			$reverse = $this->exec_reverse_export($export_code);
			if	($reverse == 'coupon')	$except[]	= $export_code;
			elseif	($reverse == 'openmarket')	$openmarket_order[]	= $export_code;
			else						$result		= $reverse;
		}
		
		if($openmarket_order){
			openDialogAlert("오픈마켓 주문건은 주문상태 되돌리기가 불가합니다.",400,140,'parent',"parent.location.href='../export/catalog';");
			exit;
		}
		
		if	($except && count($except) > 0 ){
			openDialogAlert("<br/>티켓상품의 출고(".implode(', ', $except).")는 되돌릴 수 없습니다.",400,140,'parent',"parent.location.href='../export/catalog';");
			exit;
		}

		if($result == 35){
			openDialogAlert("출고한 상품의 주문상태가 상품준비중으로 변경되었습니다.",400,140,'parent',"parent.location.href='../export/catalog';");
		}else{
			openDialogAlert("출고상태가 변경되었습니다.",400,140,'parent',"parent.location.reload();");
		}
	}

	public function batch_reverse_export()
	{
		# npay 사용확인
		$npay_use		= npay_useck();
		$talkbuy_use	= talkbuy_useck();
		$npay_order = array();
		$talkbuy_order = array();
		$openmarket_order = array();

		foreach($_POST['code'] as $export_code){

			if(preg_match('/^B/', $export_code)){
				$export_list	= $this->exportmodel->get_export_bundle($export_code);
				foreach((array)$export_list['bundle_order_info'] as $now_export_code => $data_export){
					if($npay_use && !empty($data_export['npay_order_id'])){
						$npay_order[] = $now_export_code;
					} elseif ($talkbuy_use && !empty($data_export['talkbuy_order_id'])) {
						$talkbuy_order[] = $now_export_code;
					}else{
						$reverse	= $this->exec_reverse_export($now_export_code);
						if	($reverse == 'coupon')	$except[]	= $now_export_code;
						elseif	($reverse == 'openmarket')	$openmarket_order[]	= $now_export_code;
						else						$result		= $reverse;
					}
				}

			}else{

				$data_export = $this->exportmodel->get_export($export_code);
				if($npay_use && $data_export['npay_order_id']){
					$npay_order[] = $export_code;
				} elseif ($talkbuy_use && $data_export['talkbuy_order_id']) {
					$talkbuy_order[] = $now_export_code;
				}else{
					$reverse	= $this->exec_reverse_export($export_code);
					if	($reverse == 'coupon')	$except[]	= $export_code;
					elseif	($reverse == 'openmarket')	$openmarket_order[]	= $export_code;
					elseif	($reverse == 'pos')	$pos_order[]	= $export_code;
					else						$result		= $reverse;
				}
			}
		}

		
		if($npay_order){
			echo "Npay 주문건은 되돌리기 할 수 없습니다.<br />".implode("<br />",$npay_order);
			exit;
		}
		
		if ($talkbuy_order) {
			echo '톡체크아웃 주문건은 되돌리기 할 수 없습니다.<br />' . implode('<br />', $talkbuy_order);
			exit;
		}

		if($openmarket_order){
			echo "오픈마켓 주문건은 주문상태 되돌리기가 불가합니다.<br />".implode("<br />",$openmarket_order);
			exit;
		}

		if($pos_order){
			echo "오프라인 주문건은 주문상태 되돌리기가 불가합니다.<br />".implode("<br />",$pos_order);
			exit;
		}
		
		if($result == 35){
			echo("출고한 상품의 주문상태가 상품준비중으로 변경되었습니다.");
		}else{
			echo("출고상태가 변경되었습니다.");
		}

		if	($except && count($except) > 0 )
			echo "<br/>티켓상품의 출고(".implode(', ', $except).")는 되돌릴 수 없습니다.";
	}

	public function exec_reverse_export($export_code)
	{
		$this->load->model('goodsmodel');
		$this->load->model('orderpackagemodel');

		$data_export = $this->exportmodel->get_export($export_code);
		$data_export_item = $this->exportmodel->get_export_item($export_code);
		
		# 오픈마켓 연동 주문건 주문되돌리기 불가
		$data_order = $this->ordermodel->get_order($data_export['order_seq']);
		if($data_order['linkage_id'] == "connector"){
			return 'openmarket';
		}
		
		# 오프라인 연동 주문건 주문되돌리기 불가
		if($data_order['linkage_id'] == "pos"){
			return 'pos';
		}

		if	($data_export['status'] == '55' && $this->scm_cfg['use'] == 'Y'){
			openDialogAlert("출고완료를 출고준비로 되돌릴 수 없습니다.",400,140,'parent',"parent.location.reload();");
			exit;
		}

		// 티켓상품 예외처리
		if	($data_export_item[0]['goods_kind'] == 'coupon')	return 'coupon';

		$source_step = (string) $data_export['status'];
		$target_step = $data_export['status']-10;
		$target_step = (string) $target_step;

		// 상품수량 환원
		foreach($data_export_item as $item){
			$option_mode = "option";
			if($item['opt_type'] == 'sub') $option_mode = "suboption";

			// 상품 재고 환원
			if($target_step == '45'){

				// 출고량 업데이트를 위한 변수정의
				if(!in_array($item['goods_seq'],$r_reservation_goods_seq)){
					$r_reservation_goods_seq[] = $item['goods_seq'];
				}

				if($item['opt_type'] == 'opt'){
					$this->goodsmodel->stock_option('+',$item['ea'],$item['goods_seq'],$item['option1'],$item['option2'],$item['option3'],$item['option4'],$item['option5']);
				}else{
					$this->goodsmodel->stock_suboption('+',$item['ea'],$item['goods_seq'],$item['title1'],$item['option1']);
				}

				// 패키지 상품 재고 변경
				if($item['package_yn'] == 'y'){
					$export_target = "option";
					if($item['opt_type'] == 'sub') $export_target = "suboption";
					$result_option_package = $this->orderpackagemodel->{'get_'.$export_target}($item['option_seq']);
					foreach($result_option_package as $data_option_package){
						// 품절체크를 위한 변수정의
						if(!in_array($data_option_package['goods_seq'],$r_package_goods_seq)){
							$r_package_goods_seq[] = $data_option_package['goods_seq'];
						}
					}
				}
			}

			// 옵션 상태 수량 변경
			$plus = $item['ea'];
			$minus = -1*$item['ea'];
			$this->ordermodel->set_step_ea($source_step,$minus,$item['option_seq'],$option_mode);
			$this->ordermodel->set_step_ea($target_step,$plus,$item['option_seq'],$option_mode);
			$this->ordermodel->set_option_step($item['option_seq'],$option_mode);

			// 환원될 마일리지 합산
			if($target_step == '65' && $data_export['reserve_save'] == 'save'){
				$reserve = 0;
				if($item['opt_type'] == 'opt') $reserve = $this->ordermodel->get_option_reserve($item['option_seq']);
				else $reserve = $this->ordermodel->get_suboption_reserve($item['option_seq']);
				$tot_reserve += $reserve * $item['ea'];

				$point = 0;
				if($item['opt_type'] == 'opt') $point = $this->ordermodel->get_option_reserve($item['option_seq'],'point');
				else $point = $this->ordermodel->get_suboption_reserve($item['option_seq'],'point');
				$tot_point += $point * $item['ea'];

			}
		}

		// 회원 마일리지 환원
		if($target_step == '65' && $data_export['reserve_save'] == 'save'){
			if($data_order['member_seq']){
				$this->load->model('membermodel');
				if($tot_reserve){
					$params_reserve['gb']			= "minus";
					$params_reserve['emoney']		= $tot_reserve;
					$params_reserve['memo']			= "[".$export_code."] 배송완료 취소";
					$params_reserve['memo_lang'] 	= $this->membermodel->make_json_for_getAlert("mp241",$export_code);   // [%s] 배송완료 취소
					$params_reserve['ordno']		= $data_order['order_seq'];
					$params_reserve['type']			= "order";
					$this->membermodel -> emoney_insert($params_reserve, $data_order['member_seq']);
				}

				if($tot_point){
					$params_point['gb']             = "minus";
					$params_point['point']          = $tot_point;
					$params_point['memo']           = "[".$export_code."] 배송완료 취소";
					$params_point['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp241",$export_code);   // [%s] 배송완료 취소
					$params_point['ordno']          = $data_order['order_seq'];
					$params_point['type']           = "order";
					$this->membermodel -> point_insert($params_point, $data_order['member_seq']);
				}


				$query = "update fm_goods_export set reserve_save = 'none' where export_code = ?";
				$this->db->query($query,array($data_export['export_code']));
			}
		}

		// 주문상태 업데이트
		$this->ordermodel->set_order_step($data_export['order_seq']);

		// 출고상태 업데이트
		$this->exportmodel->set_status($data_export['export_code'],$target_step);

		// 상품준비로 돌아갈경우 출고 목록 삭제
		if($target_step == 35){
			$this->exportmodel->delete_export($data_export['export_code']);
			//묶음배송일경우 처리
			if(preg_match('/^B/', $data_export['bundle_export_code'])){
				$this->ordermodel->set_bundle_order('to_35', $data_export['order_seq'], $data_export['bundle_export_code']);
			}
		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		// 로그
		$this->ordermodel->set_log($data_export['order_seq'],'process',$this->managerInfo['mname'],'되돌리기 ('.$this->arr_step[$data_export['status']].' => '.$this->arr_step[$target_step].')','-');

		return $target_step;
	}

	public function batch_waybill_number()
	{
		if($_POST['export_date']) foreach($_POST['export_date'] as $export_code => $export_date){

			# 오픈마켓 송장등록여부체크 #
			/*
			$query = $this->db->query("select ord.linkage_order_id,exp.delivery_number from fm_goods_export exp inner join fm_order ord on exp.order_seq=ord.order_seq where exp.export_code=?",$export_code);
			$order_chk = $query->row_array();
			if($order_chk['linkage_order_id'] && $order_chk['delivery_number']) {
				continue;
			}
			*/

			$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';

			$delivery_company_code = $_POST['delivery_company_code'][$export_code];
			$delivery_number = $_POST['delivery_number'][$export_code];
			$international_shipping_method = $_POST['international_shipping_method'][$export_code];
			$international_delivery_no = $_POST['international_delivery_no'][$export_code];

			$update_array['export_date'] = $export_date;
			$update_array['delivery_company_code'] = $delivery_company_code;
			$update_array['delivery_number'] = $delivery_number;
			$update_array['international_shipping_method'] = $international_shipping_method;
			$update_array['international_delivery_no'] = $international_delivery_no;
			$query = $this->db->update_string('fm_goods_export', $update_array, $export_field."='".$export_code."'");
			$this->db->query($query);

			# 오픈마켓 송장등록 #
			/*
			if(!($order_chk['linkage_order_id'] && $order_chk['delivery_number'])) {
				$this->load->model('openmarketmodel');
				$this->openmarketmodel->request_send_export($export_code);
			}
			*/

		}

		openDialogAlert("출고정보가 변경되었습니다.",400,140,'parent',"parent.location.reload();");
	}

	public function download_write(){

		if(count($_POST['downloads_item_use'])<1){
			$callback = "";
			openDialogAlert("다운로드 항목을 1개 이상 설정해 주세요.",400,140,'parent',$callback);
			exit;
		}

		$item = implode("|",$_POST['downloads_item_use']);
		$params['item']			= $item;

		$datas = get_data("fm_exceldownload",array("gb"=>'EXPORT',"provider_seq"=>1));
		if(!$datas){
			$params['provider_seq']	= 1;
			$params['gb']	= 'EXPORT';
			$result = $this->db->insert('fm_exceldownload', $params);
			$msg	= "등록 되었습니다.";
		}else{
			$this->db->where(array('gb'=> 'EXPORT',"provider_seq"=>1));
			$result = $this->db->update('fm_exceldownload', $params);
			$msg	= "수정 되었습니다.";
		}
		$func	= "parent.closeDialog('download_list_setting');";

		openDialogAlert($msg,400,140,'parent',$func);

	}

	public function excel_down(){
		if($_POST['export_code']){
			$criteria	= $_POST['criteria'];
			$export_code	= $_POST['export_code'];
		}else{
			$criteria	= $_GET['criteria'];
			$export_code	= $_GET['export_code'];
		}
		$this->load->model('excelexportmodel');
		$this->excelexportmodel->create_excel_list($criteria, $export_code);
		exit;
	}

	//티켓상품 > 사용내역 엑셀추출
	public function coupon_use_excel(){
		$this->load->model('excelexportmodel');
		$chk_masking = $this->authmodel->manager_limit_act('private_masking');
		if($chk_masking) {
			$msg = "마스킹(*) 처리된 개인정보 항목이 포함되어 있어 엑셀 다운로드를 할 수 없습니다.";
			$msg .= "<br/ >대표운영자에게 관리자 권한 수정을 요청해주시기 바랍니다.";
			openDialogAlert($msg, 600, 180, 'parent', '');
		} else {
			$this->excelexportmodel->create_excel_coupon_use();
		}
		exit;
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


	public function excel_upload(){
		###
		$config['upload_path']		= $path = ROOTPATH."/data/tmp/";
		$config['overwrite']			= TRUE;
		$this->load->library('Upload');
		if (is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
			$file_ext = end(explode('.', $_FILES['excel_file']['name']));//확장자추출
			$config['allowed_types']	= 'xls';
			$config['file_name']			= 'order_upload.'.$file_ext;
			$this->upload->initialize($config);
			if ($this->upload->do_upload('excel_file')) {
				$file_nm = $config['upload_path'].$config['file_name'];
				@chmod("{$file_nm}", 0777);
			}else{
				$callback = "";
				openDialogAlert("xls 파일만 가능합니다.",400,140,'parent',$callback);
				exit;
			}
		}else{
			$callback = "";
			openDialogAlert("파일을 등록해 주세요.",400,140,'parent',$callback);
			exit;
		}

		$this->load->model('excelexportmodel');
		$result = $this->excelexportmodel->excel_upload($file_nm);

		if($result['result_excel_url']){
			$callback = "document.location.href='{$result['result_excel_url']}'; setTimeout('parent.location.reload()',1000)";
		}
		else{
			$callback = "parent.location.reload();";
		}
		openDialogAlert($result['msg'],600,140,'parent',$callback);
		exit;
	}

	# 구매확정처리
	public function buy_confirm()
	{
		$this->load->model('returnmodel');
		$this->load->model('goodsmodel');

		$export_code_tmp		= $_POST['export_code'];

		$dataExportArr		= array();
		if(preg_match('/^B/', $export_code_tmp)){
			$data_export_tmp	= $this->exportmodel->get_export_bundle($export_code_tmp);
			foreach($data_export_tmp['bundle_order_info'] as $bundle_key => $bundle_val){
				$dataExportArr[] = $bundle_key;
			}
		}else{
			$dataExportArr[]	= $export_code_tmp;
		}

		$msg = '';
		// 구매확정 관련 프로세스 통합 by hed
		$this->load->library('buyconfirmlib');
		foreach($dataExportArr as $export_code){
			$this->buyconfirmlib->exec_buyconfirm($export_code, $msg);
		}

		$callback = "parent.location.reload();";
		openDialogAlert($msg,400,140,'parent',$callback);
	}


	// 티켓상품 사용 처리
	public function usecoupon(){
		$this->exportSmsData = array();
		$this->load->model("exportmodel");
		$this->load->model("returnmodel");

		$export_code		= trim($_POST['export_code']);
		$coupon_serial		= trim($_POST['coupon_serial']);
		$use_coupon_value	= trim($_POST['use_coupon_value']);
		if	(!$export_code || !$coupon_serial || !$use_coupon_value || !is_numeric($use_coupon_value)){
			openDialogAlert('티켓사용 인증에 실패하였습니다.',400,140,'parent',$callback);
			exit;
		}

		// 티켓상품 인증 확인
		$chkcoupon	= $this->exportmodel->chk_coupon(array('export_code'=>$export_code));

		if	($chkcoupon['result'] != 'success'){
			if		($chkcoupon['result'] == 'refund')		$msg	= "환불된 티켓입니다.";
			elseif	($chkcoupon['result'] == 'noremain')	$msg	= "이미 모두 사용된 티켓입니다.";
			elseif	($chkcoupon['result'] == 'notyet')		$msg	= "사용 가능한 기간이 아닙니다.";
			elseif	($chkcoupon['result'] == 'expire')		$msg	= "만료된 티켓입니다.";
			else											$msg	= "티켓사용 인증에 실패하였습니다.";

			$callback = "";
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}

		// 티켓상품 사용 내역 저장 및 배송완료 처리
		$this->load->model('exportmodel');
		$this->exportmodel->coupon_use_save($_POST);

		if(count($this->exportSmsData) > 0){
			commonSendSMS($this->exportSmsData);
		}

		$callback = "parent.location.reload();";
		openDialogAlert('티켓 사용확인이 완료되었습니다.',400,140,'parent',$callback);
	}

	// 이메일, SMS 개별 재발송
	public function resend_coupon_info(){

		$this->load->model('exportmodel');

		$type			= trim($_GET['type']);
		$email			= trim($_GET['email']);
		$sms			= trim($_GET['sms']);
		$export_code	= trim($_GET['export_code']);

		if	($type == 'mail'){
			if		(!$email)
				$result	= array('code'=>'error_1', 'msg' => '이메일 주소를 입력해주세요.');
			elseif	(!preg_match('/^[0-9a-zA-Z\_\-]+@[0-9a-zA-Z\_\-]+\.[a-zA-Z\.]+$/', $email))
				$result	= array('code'=>'error_2', 'msg' => '올바르지 않은 이메일 주소입니다.');
			else{
				$result	= $this->exportmodel->coupon_export_send_for_option(array($export_code), 'mail', $email);
				if	($result['mail_status'] == 'y')
					$result	= array('result'=>'success');
				else
					$result	= array('result'=>'fail','code'=>'error_3', 'msg' => '발송에 실패하였습니다.');
			}
		}
		if	($type == 'sms'){
			if		(!$sms)
				$result	= array('code'=>'error_1', 'msg' => '휴대폰번호를 입력해주세요.');
			elseif	(!preg_match('/^01[0-9]\-{0,1}[0-9]{3,4}\-{0,1}[0-9]{4}$/', $sms))
				$result	= array('code'=>'error_2', 'msg' => '올바르지 않은 휴대폰번호입니다.');
			else{
				// 휴대폰 번호가 - 없이 오면 -을 추가해 준다. ( 자릿수 맞춤을 위해 )
				if	(preg_match('/^[0-9]*$/', $sms)){
					if	(strlen($sms) == 10)
						$sms	= substr($sms, 0, 3).'-'.substr($sms, 3, 3).'-'.substr($sms, 6);
					else
						$sms	= substr($sms, 0, 3).'-'.substr($sms, 3, 4).'-'.substr($sms, 7);
				}
				$result	= $this->exportmodel->coupon_export_send($export_code, 'sms', '', $sms);
				if	($result['sms_status'] == 'y'){
					$result	= array('result'=>'success');
					if(count($this->resend_sms_common_data) > 0){
						commonSendSMS($this->resend_sms_common_data);
					}
				}else{
					$result	= array('result'=>'fail','code'=>'error_3', 'msg' => '발송에 실패하였습니다.');
				}
			}
		}

		echo json_encode($result);
	}

	public function print_setting(){
		config_save("export" ,array('exportPrintExportcodeBarcode'=>$_POST['exportPrintExportcodeBarcode']));
		config_save("export" ,array('exportPrintGoodsCode'=>$_POST['exportPrintGoodsCode']));
		config_save("export" ,array('exportPrintGoodsBarcode'=>$_POST['exportPrintGoodsBarcode']));
		$callback = "parent.closeDialog('print_setting_dialog')";
		openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 출고자동화 송장번호발급실패시 재전송 */
	public function invoice_export_resend(){
		$export_code = $_GET['export_code'];

		$this->load->model('invoiceapimodel');
		if($export_code) {
			$result = $this->invoiceapimodel->export($export_code,true);
		}

		if($result['code']=='success'){
			echo json_encode($result);
		}else{
			echo json_encode(array(
				'code' => 'fail',
				'msg' => $result['msg']
			));
		}
	}

	/* 구매확정 로그 2015-04-07 pjm */
	public function buy_confirm_log(){

		$export_seq = $_GET['export_seq'];
		$query = "select
						*
				from
					fm_log_buy_confirm as a
				where export_seq=? order by regdate desc";
		$query = $this->db->query($query,array($export_seq));

		foreach($query->result_array() as $data){

			$result[] = $data;

		}

		$tpl = "default/export/buy_confirm_log.html";

		$this->template->assign('data_log',$result);
		$this->template->define(array('tpl' => $tpl));
		$this->template->print_("tpl");
	}

}

/* End of file export_process.php */
/* Location: ./app/controllers/admin/export_process.php */
