<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class returns_process extends admin_base {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('returnmodel');
		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
		$this->load->model('orderpackagemodel');
		
		$auth = $this->authmodel->manager_limit_act('refund_act');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}
	}

	# npay 반품요청승인, 교환수거완료 처리
	public function npay_approve_return($return_type,$itemdata,$data_return,$npay_return_released)
	{
		$npay_product_order_id	= $itemdata['npay_product_order_id'];
		$opt_type				= $itemdata['opt_type'];

		$npay_hold_reason	= $this->naverpaylib->get_npay_code("return_hold");	//npay 반품 보류 코드
		$message			= "실패";

		if($return_type == "return"){
			$title			= "반품";
			$npay_flag_new	= "ApproveReturnApplication";
		}else{
			$title			= "교환";
			$npay_flag_new	= "ApproveCollectedExchange";
		}

		$npay_data = array("npay_product_order_id"=>$npay_product_order_id,
							"order_seq"=>$data_return['order_seq'],
							"return_code"=>$data_return['return_code'],
							"return_type"=>$return_type,
						);
		//반품보류 해제\
		$npay_flag = strtoupper($data_return['npay_flag']);

		if($npay_return_released == 'y' && array_key_exists($npay_flag,$npay_hold_reason)){

			$npay_res = $this->naverpaymodel->approve_return_hold($npay_data);
			if($npay_res['result'] != "SUCCESS"){
				openDialogAlert("네이버페이 ".$title."보류 해제 실패<br /><font color=red>".$npay_res['message']."</font>",500,160,'parent','');
				exit;
			}else{
				if($data_return['npay_claim_deliveryfee_ids']){
					$npay_claim_deliveryfee_ids = explode(",",$data_return['npay_claim_deliveryfee_ids']);
					foreach($npay_claim_deliveryfee_ids as $ids){
						//보류해제에 대한 flag update 
						$ids = trim($ids);
						if($ids == $npay_product_order_id){
							$upt_npay_flag = "";
						}else{
							$upt_npay_flag = $return_type."_request";
						}
						$query = $this->db->query("select return_code from fm_order_return_item where npay_product_order_id=?",$ids);
						$return_tmp = $query->row_array();
						$this->db->query("update fm_order_return set npay_flag=? where order_seq=? and return_code=?",array($upt_npay_flag,$data_return['order_seq'],$return_tmp['return_code']));
					}
				}
			}
		}

		if($return_type == "return"){
			//반품 승인
			$npay_res = $this->naverpaymodel->approve_return($npay_data);
			//$return_title	= "반품요청승인";
			$return_msg		= "네이버페이 반품 요청 승인";

		}elseif($return_type == "exchange"){
			
			$npay_res		= $this->naverpaymodel->approve_exchange($npay_data);
			//$return_title	= "교환수거완료";
			$return_msg		= "네이버페이 교환 수거 완료";
		}
		if($npay_res['result'] == "SUCCESS"){
			$message = "성공";
			//반품요청승인에 대한 flag update
			$this->db->query("update fm_order_return set npay_flag=? where return_code=?",array($npay_flag_new,$data_return['return_code']));
		}

		$return_msg		.= " ".$message;

		if($npay_res['result'] != "SUCCESS"){
			openDialogAlert($return_msg."<br /><font color=red>".$npay_res['message']."</font>",500,160,'parent','');
			exit;
		}

		return true;
	}

	public function modify()
	{
		$this->load->helper('order');

		$aParams			= $this->input->post();
		$return_code		= $aParams['return_code'];
		$order_seq			= $aParams['order_seq'];
		$data_return		= $this->returnmodel->get_return($return_code);
		$data_return_item	= $this->returnmodel->get_return_item($return_code);
		$data_origin_order	= $this->ordermodel->get_order($order_seq);
		$private_masking    = $this->authmodel->manager_limit_act('private_masking');

		// 톡체크아웃 반품건은 관리자에서 처리할 수 없음
		$kakao_pay_chk = talkbuy_useck();

		if ($kakao_pay_chk && $data_origin_order["pg"] === "talkbuy" && $data_origin_order["talkbuy_order_id"]) {
			$callback = "parent.document.location.reload();";
			openDialogAlert("톡체크아웃의 대한 반품처리는 불가능합니다.",400,140,'parent',$callback);
			exit;
		}

		$this->load->library('returnlibrary');
		if($this->returnlibrary->check_return_shipping_price($aParams,$data_return_item) === false){
			$callback = "parent.document.location.reload();";
			openDialogAlert(getAlert('mo154'),400,140,'parent',$callback);
			exit;
		}

		//반품배송비 입점사가 받았을 경우. 입력한 금액 초기화(정산반영) @2015-06-23 pjm
		// 판매자 부담 시 반품 배송비는 무조건 0 원 처리 추가 :: 2018-05-24 lwh
		if(($aParams['return_shipping_gubun'] == "provider" && $aParams['refund_ship_type'] != 'M') || $aParams['refund_ship_duty'] == 'seller')
			$aParams['return_shipping_price'] = 0; 

		$npay_use		= npay_useck();
		$update_param	= array();
		$return_update	= true;		//반품 상태,재고 업데이트 여부(npay 때문에 생성)

		if($npay_use && $data_return['npay_order_id']){
			$npay_order = true;
		}else{
			$npay_order = false;
		}

		/* 완료상태일때는 메모만 수정*/
		if($data_return['status']=='complete'){
			$this->db->where('return_code',$aParams['return_code']);
			$update_param = array('admin_memo'=>$aParams['admin_memo']);
			$this->db->update('fm_order_return',$update_param);
			$callback = "parent.document.location.reload();";
			openDialogAlert("반품 관리 메모가 수정 되었습니다.",400,140,'parent',$callback);
			exit;
		}

		if(!$npay_order){
			if(!$private_masking) {
				//$this->validation->set_rules('phone[]', '연락처','trim|required|numeric|max_length[4]|xss_clean');
				$this->validation->set_rules('cellphone[]', '휴대폰','trim|required|numeric|max_length[4]|xss_clean');
				if($aParams['return_method'] == 'shop'){
					$this->validation->set_rules('senderZipcode[]', '우편번호','trim|required|numeric|max_length[7]|xss_clean');
					$this->validation->set_rules('senderAddress', '주소','trim|required|xss_clean');
					$this->validation->set_rules('senderAddressDetail', '상세주소','trim|required|xss_clean');
				}

				if($this->validation->exec()===false){
					$err = $this->validation->error_array;
					$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
					openDialogAlert($err['value'],400,140,'parent',$callback);
					exit;
				}
			}
			
			if($aParams['status'] == 'complete' && $aParams['return_type'] == 'return'){

				$this->load->library('Connector');
				$claimService		= $this->connector::getInstance('claim');
				$checkMarketClaim	= $claimService->marketClaimConfirm($aParams['return_code'], 'RTN');

				if ($checkMarketClaim['success'] != 'Y') {
					if (isset($checkMarketClaim['message']))
						openDialogAlert("[반품실패] {$checkMarketClaim['message']}",400,140,'parent',$callback);
					else
						openDialogAlert("[반품실패] 마켓 반품 상태를 확인해 주세요",400,140,'parent',$callback);
					exit;
				}
			}

			// 물류관리 사용 시 불량재고 입력값 체크
			if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
			if	($this->scm_cfg['use'] == 'Y'){
				if	($aParams['scm_wh'] > 0){
					// 물류관리 사용 시 창고번호 저장
					$update_param['wh_seq']		= $aParams['scm_wh'];
					if	($aParams['stock_return_ea']) foreach($aParams['stock_return_ea'] as $idx => $ea){
						if	($aParams['optioninfo'][$idx]){
							if	($aParams['return_badea'][$idx] > $ea){
								$callback	= 'if(parent.document.getElementsByName(\'return_badea[' . $idx . ']\')[0]) parent.document.getElementsByName(\'return_badea[' . $idx . ']\')[0].focus();';
								openDialogAlert('현재 입력하신 불량수량은 반품수량 보다 많습니다.',400,140,'parent',$callback);
								exit;
							}
							if	(!$aParams['location_position'][$idx]){
								openDialogAlert('로케이션을 선택해 주세요.', 400, 140, 'parent', $callback);
								exit;
							}
						}
					}
				}else if(array_key_exists('scm_wh', $aParams)){
					$callback	= 'if(parent.document.getElementsByName(\'scm_wh\')[0]) parent.document.getElementsByName(\'scm_wh\')[0].focus();';
					openDialogAlert('반품창고를 선택해 주세요.',400,140,'parent',$callback);
					exit;
				}
			}

			if(!$private_masking) {
				$zipcode = "";
				if($aParams['phone'][1] && $aParams['phone'][2]) $phone = implode('-',$aParams['phone']);
				if($aParams['cellphone'][1] && $aParams['cellphone'][2]) $cellphone = implode('-',$aParams['cellphone']);
				if($aParams['senderZipcode']) $zipcode = implode('-',$aParams['senderZipcode']);
		
				$update_param['cellphone'] 				= $cellphone;
				$update_param['phone'] 					= $phone;
				$update_param['sender_zipcode']			= $zipcode;
				// $update_param['sender_post_number']		= $aParams['senderPost_number'];
				$update_param['sender_address_type']	= ($aParams['senderAddress_type'])?$aParams['senderAddress_type']:"zibun";
				$update_param['sender_address']			= $aParams['senderAddress'];
				$update_param['sender_address_street']	= $aParams['senderAddress_street'];
				$update_param['sender_address_detail']	= $aParams['senderAddressDetail'];
			}
			$update_param['return_reason'] 			= $aParams['return_reason'];
			$update_param['admin_memo'] 			= $aParams['admin_memo'];
			$update_param['return_method']			= $aParams['return_method'];
			$update_param['manager_seq']			= $this->managerInfo['manager_seq'];
			$update_param['return_shipping_price']	= $aParams['return_shipping_price'];
			$update_param['return_shipping_gubun']	= $aParams['return_shipping_gubun'];

			// 반품 관련 수정처리 추가 :: 2018-05-24 lwh
			$update_param['refund_ship_duty']		= $aParams['refund_ship_duty']; // 반품 배송비 책임
			$update_param['refund_ship_type']		= $aParams['refund_ship_type']; // 반품 배송비 지불 타입
		}

		$update_param['return_type']	= $data_return['return_type'];
		if($aParams['admin_memo']) $update_param['admin_memo']		= $aParams['admin_memo'];

		if($data_return['status'] != "complete"){
			$update_param['status'] 		= $aParams['status'];
		}

		##--------------------------------------------------------------------------------------------------
		# npay 반품요청 승인 처리 > 처리가능작업 : 
		#	- 반품신청 -> 반품완료(O)
		#	- 반품신청 -> 반품처리중(X)
		#	- 반품처리중 -> 반품완료(X) 
		#	- 반품처리중 -> 반품신청(X) 
		if($npay_order){
			$this->load->model("naverpaymodel");
			$this->load->library('naverpaylib');

			if($aParams['status'] == "request"){
				openDialogAlert("이 반품건은 네이버페이 반품건으로 반품신청으로 되돌리기 불가합니다.",500,160,'parent','');
				exit;
			} 
			if($aParams['status'] == "ing"){
				openDialogAlert("이 반품건은 네이버페이 반품건으로 반품처리중 처리가 불가합니다.",500,160,'parent','');
				exit;
			}
			if($data_return['status'] == "ing" && $aParams['status'] == "complete"){
				openDialogAlert("이 반품건은 네이버페이 반품건으로 반품완료 처리가 불가합니다.",500,160,'parent','');
				exit;
			}
		}
		##--------------------------------------------------------------------------------------------------
		if($aParams['status'] == 'complete'){
			if($data_return['status']!="complete"){
				
				$update_param['return_date'] = date('Y-m-d H:i:s');
				// 재고 더하기
				foreach($data_return_item as $item){

					$return_item_seq = $item['return_item_seq'];

					if( $item['goods_kind'] == 'coupon' ) {//티켓상품 마일리지/포인트, 재고, 할인쿠폰 반환없음
						$retuns_goods_coupon_ea++;
						continue;
					}
					##--------------------------------------------------------------------------------------------------
					## npay 반품요청승인, 교환수거완료 API
					if($npay_order){
						# npay 주문 반품완료 처리 안함.(반품요청승인, 교환요청승인 처리까지만)
						# npay 변경된 주문 수집시 반품/교환완료 처리
						$itemdata = array("npay_product_order_id"=>$item['npay_product_order_id']
										,"opt_type"=>$item["opt_type"]);
						$npay_res = $this->npay_approve_return($update_param['return_type'],$itemdata,$data_return,$aParams['npay_return_released']);
						if($npay_res) $exchange_reorder = true; else $exchange_reorder = false;
					}else{
						$exchange_reorder = true;
					}
					##--------------------------------------------------------------------------------------------------
					//선택한 재고증가 수량만큼 증감 2015-03-31 pjm
					if(!$npay_order){

						$stock_return_ea	= $aParams['stock_return_ea'][$return_item_seq];

						// 반품으로 인한 재고증가
						$goodsData = $this->returnmodel->return_stock_ea($stock_return_ea,$return_item_seq,$item,$goodsData);
					
					}

				}
			}

			// 재주문 넣기(맞교환),( npay주문 교환수거완료일때 )
			if($update_param['return_type'] == 'exchange' && $exchange_reorder){
				$this->ordermodel->reorder($data_return['order_seq'],$return_code);
			}
		}

		# 반품정보 업데이트
		if(!$npay_order){
			$this->db->where('return_code',$return_code);
			$this->db->update('fm_order_return',$update_param);
		}

		# 재고차감할 반품수량
		$return_ea_arr = $aParams['stock_return_ea'];
		foreach($aParams['reason'] as $return_item_seq=>$reason_code)
		{
			unset($update_param);
			if(!$npay_order){
					$update_param['reason_code'] = $reason_code;
					if (!empty($aParams['reason_desc'][$return_item_seq])) {
						$update_param['reason_desc']	= $aParams['reason_desc'][$return_item_seq];
					}
			}
			$stock_return_ea = $aParams['stock_return_ea'][$return_item_seq];
			$return_badea = $aParams['return_badea'][$return_item_seq];
			
			if( !is_array($stock_return_ea) ){
				$update_param['stock_return_ea']	= $stock_return_ea;
				$update_param['return_badea']		= $return_badea;
			}else{
				$update_param['package_stock_return_ea']= serialize($stock_return_ea);
				$update_param['package_return_badea']	= serialize($return_badea);
			}
			if(is_array($aParams['location_position'][$return_item_seq])){
				$location_position	= serialize($aParams['location_position'][$return_item_seq]);
				$location_code		= serialize($aParams['location_code'][$return_item_seq]);
			}else{
				$location_position	= $aParams['location_position'][$return_item_seq];
				$location_code		= $aParams['location_code'][$return_item_seq];
			}

			$update_param['location_position']	= $location_position;
			$update_param['location_code']		= $location_code;

			$this->db->where('return_item_seq',$return_item_seq);
			$this->db->update('fm_order_return_item',$update_param);
		}
		
		// 품절체크를 위한 변수선언
		$r_runout_goods_seq = array();

		/* 재고조정 히스토리 저장 */
		if(!$npay_order && $aParams['status'] == 'complete'){
			if($data_return['status']!="complete"){
				
				$this->returnmodel->return_stock_history($return_code,$retuns_goods_coupon_ea,$data_return_item,$return_ea_arr,$update_param['return_date']);

				/* 로그저장 */
				$logTitle	= "반품완료(".$return_code.")";
				$logDetail = "관리자가 반품완료처리를 하였습니다.";
				$logParams	= array('return_code' => $return_code);
				$this->load->model('ordermodel');
				$this->ordermodel->set_log($data_return['order_seq'],'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams);

				// 물류관리 재고 적용 및 매장 재고 전송
				if	($this->scm_cfg['use'] == 'Y'){
					$this->load->model('scmmodel');
					$this->scmmodel->apply_return_wh($aParams['scm_wh'], $return_code, $goodsData);
					if	($this->scmmodel->tmp_scm['wh_seq'] > 0){
						$sendResult		= $this->scmmodel->change_store_stock($this->scmmodel->tmp_scm['goods'], array($this->scmmodel->tmp_scm['wh_seq']), '', '반품처리가 완료 되었습니다.', 'reload');
					}
				}

				/**
				* 2-2 반품배송비 관련 통합정산테이블 생성 시작
				* @
				**/
				if($aParams['return_shipping_gubun'] == 'company' && $aParams['return_shipping_price']) {
					$this->load->helper('accountall');
					if(!$this->accountallmodel)	$this->load->model('accountallmodel');
					if(!$this->providermodel)	$this->load->model('providermodel');
					if(!$this->refundmodel)		$this->load->model('refundmodel');
					if(!$this->returnmodel)		$this->load->model('returnmodel');

					//step2 통합정산 생성(미정산매출 환불건수 업데이트)
					$this->accountallmodel->insert_calculate_sales_order_returnshipping($data_return['order_seq'],$return_code);
					//debug_var($this->db->queries);
					//debug_var($this->db->query_times);
				}
				/**
				* 2-2 반품배송비 관련 통합정산테이블 생성 끝
				* @
				**/

				if	(!$sendResult['status']){
					$callback = "parent.document.location.reload();";
					openDialogAlert("반품처리가 완료 되었습니다.",400,140,'parent',$callback);
					exit;
				}
			}
		}
			
		$callback = "parent.document.location.reload();";
		if($npay_order){
			if($data_return['return_type'] == "return"){
				$title = "반품승인신청";
			}else{
				$title = "교환수거";
			}
			openDialogAlert($title." 완료 되었습니다.",400,140,'parent',$callback);
		}else{
			openDialogAlert("반품정보가 수정 되었습니다.",400,140,'parent',$callback);
		}
	}

	public function batch_reverse_return(){
		$result = array();
		foreach($_POST['code'] as $return_code){
			$result[]= $this->exec_reverse_return($return_code);
		}
		echo implode("<br />",$result);
	}

	public function exec_reverse_return($return_code,$mode='',$npay_data=array()){

		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('exportmodel');
		$this->load->helper('order');

		$data_return 		= $this->returnmodel->get_return($return_code);
		$data_return_item 	= $this->returnmodel->get_return_item($return_code);
		$data_order			= $this->ordermodel->get_order($data_return['order_seq']);

		if($data_return['return_type']=='return'){
			$title = "반품";
		}else{
			$title = "교환";
		}

		# npay 반품건 삭제 불가
		$npay_use = npay_useck();
		if($npay_use && $mode != 'npay' && $data_order['pg'] == "npay"){
			return $return_code." - Npay ".$title." 건은 삭제하실 수 없습니다.";
			exit;
		}

		# kakaoPay 구매 반품건 삭제 불가
		$kakao_pay_chk = talkbuy_useck();
		if ($kakao_pay_chk && $data_order["pg"] === "talkbuy" && $data_order["talkbuy_order_id"]) {
			return $return_code." - KakaoPay 구매 ".$title." 건은 삭제하실 수 없습니다.";
			exit;
		}

		if($data_return['status'] == 'complete'){
			return "{$return_code} - 반품 완료된 건은 삭제하실 수 없습니다.";
		}

		if($data_return['return_type']=='return' && $data_return['refund_code']){
			$data_refund 		= $this->refundmodel->get_refund($data_return['refund_code']);
			$data_refund_item 	= $this->refundmodel->get_refund_item($data_return['refund_code']);

			if($data_refund['status'] == 'complete'){
				return "{$return_code} - 환불 완료된 건은 삭제하실 수 없습니다.";
			}

			if($npay_use && $data_order['pg'] == "npay"){
				$refund_items = array();
				foreach($data_refund_item as $refund){
					$refund_items[$refund['npay_product_order_id']] = $refund['refund_item_seq'];
				}
			}
		}


		$cfg_order = config_load('order');

		$this->db->trans_begin();
		$rollback = false;

		//구매확정기간 종료 후 반품삭제시 : 구매확정 가능 기간 pjm
		$save_edate				= date('Y-m-d',strtotime("-".$cfg_order['save_term']." day"));
		$export_items_reserve	= array();
		$exports				= array();
		$export_items			= array();
		$reject_item			= array();

		if($cfg_order['save_type'] == 'exist') $reserve_mode = "destroy";
		else $reserve_mode = "buyconfirm";

		foreach($data_return_item as $return_item){

			$reject_use = true;
			if($npay_use && $data_order['pg'] == "npay"){
				if(in_array($return_item['npay_product_order_id'],$npay_data)){
					$reject_item['return'][] = $return_item['return_item_seq'];
					foreach($refund_items as $npay_product_order_id=>$refund_item_seq){
						if($npay_product_order_id == $return_item['npay_product_order_id']){
							$reject_item['refund'][] = $refund_item_seq;
						}
					}
				}else{
					$reject_use = false;
				}
			}

			if($reject_use){

				$reject_product[] = $return_item['return_item_seq'];

				if($return_item['opt_type']=='opt'){
					$option_seq = $return_item['option_seq'];
	
					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->db->query($query,array($option_seq));
					$optionData = $query->row_array();
	
					if($data_return['return_type']=='return' && $optionData['refund_ea']>=$return_item['ea']){
						$this->db->set('refund_ea','refund_ea-'.$return_item['ea'],false);
						$this->db->where('item_option_seq',$option_seq);
						$this->db->update('fm_order_item_option');
					}
				}else if($return_item['opt_type']=='sub'){
					$option_seq = $return_item['option_seq'];
	
					$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
					$query = $this->db->query($query,array($option_seq));
					$optionData = $query->row_array();
	
					if($data_return['return_type']=='return' && $optionData['refund_ea']>=$return_item['ea']){
						$this->db->set('refund_ea','refund_ea-'.$return_item['ea'],false);
						$this->db->where('item_suboption_seq',$option_seq);
						$this->db->update('fm_order_item_suboption');
					}
				}

				##----------------------------------------------------------------------------------
				// 반품철회에 대해 작업이 완료된 후 모든 출고내역에 대해 구매확정처리 by hed
				// 신정산개선에서는 1개의 출고에 대해 1개의 구매확정만 가능하며 반품/환불이 진행중인 경우 구매확정할 수 없음.
				if($cfg_order['buy_confirm_use']){
	
					$export_code		= $return_item['export_code'];
	
					if(!in_array($export_code,$export_code_loop)){
						$exports[$export_code]	= $this->exportmodel->get_export($export_code);
						$export_code_loop[]		= $export_code;
					}
	
	
					$chk					= array();
					$chk['export_code']		= $export_code;
					$chk['item_seq']		= $return_item['item_seq'];
					if($return_item['opt_type']=='opt'){
						$chk['option_seq'] 		= $option_seq;
					}else if($return_item['opt_type']=='sub'){
						$chk['suboption_seq']	= $option_seq;
					}
					$items_tmp				= $this->exportmodel->get_export_item_by_item_seq('',$chk);		//1건만
	
					if($exports[$export_code]['complete_date'] < $save_edate){
						$auto_buyconfirms					= true;
					}else{
						$auto_buyconfirms					= false;
					}
					$items_tmp['opt_type']			= $return_item['opt_type'];
					$items_tmp['return_item_ea']	= $return_item['ea'];				//반품취소수량
					$items_tmp['give_reserve_ea']	= $return_item['give_reserve_ea'];	//회수마일리지(지급된)수량
					$items_tmp['auto_buyconfirms']	= $auto_buyconfirms;				//자동구매확정여부
	
					$exports[$export_code]['items'][]	= $items_tmp;
				}

				$reject_cnt++;

				if($npay_use && $data_order['pg'] == "npay"){
					$logTitle	= "반품철회({$return_code})";
					$logDetail	= $return_item['npay_product_order_id']."(npay상품주문번호)를 반품철회 했습니다.";
					$this->ordermodel->set_log($data_order['order_seq'],'process',"Npay",$logTitle,$logDetail,'','','npay');
				}
			}
			##----------------------------------------------------------------------------------
		}

		if($reject_cnt == count($data_return_item)){

			if($mode == "npay"){
				$actor = "Npay";
			}else{
				$actor = $this->managerInfo['mname'];
			}

			if($data_return['return_type'] == "return"){
				$logTitle	= "반품삭제({$return_code})";
			}else{
				$logTitle	= "반품(맞교환)삭제({$return_code})";
			}
			$logDetail	= "{$return_code} 반품건을 삭제처리했습니다.";
			$this->ordermodel->set_log($data_order['order_seq'],'process',$actor,$logTitle,$logDetail,'','','',$mode);
		}
		
		if($npay_use && $data_order['pg'] == "npay"){

			if($reject_item['return']){
				$sql = "delete from fm_order_return_item where return_item_seq in(".implode(",",$reject_item['return']).")";
				$this->db->query($sql);

				if(count($reject_item['return']) == count($data_return_item)){
					$sql = "delete from fm_order_return where return_code=?";
					$this->db->query($sql, $return_code);
				}

			}

			if($data_return['return_type']=='return' && $data_return['refund_code']){

				$sql = "delete from fm_order_refund_item where refund_item_seq in(".implode(",",$reject_item['refund']).")";
				$this->db->query($sql);

				if(count($reject_item['refund']) == count($data_refund_item)){
					$sql = "delete from fm_order_refund where refund_code=?";
					$this->db->query($sql, $data_return['refund_code']);
				}
			}elseif($data_return['return_type']=='exchange'){

				# Npay 교환 삭제일 경우 생성된 재주문건 삭제
				$sql = "select order_seq from fm_order where orign_order_seq=?";
				$query = $this->db->query($sql,$data_order['order_seq']);
				$orign_order = $query->row_array();
				if($orign_order['order_seq']){
					$sql = "delete from fm_order where order_seq = ?";
					$this->db->query($sql,$orign_order['order_seq']);
					$sql = "delete from fm_order_item where order_seq = ?";
					$this->db->query($sql,$orign_order['order_seq']);
					$sql = "delete from fm_order_item_option where order_seq = ?";
					$this->db->query($sql,$orign_order['order_seq']);
					$sql = "delete from fm_order_item_suboption where order_seq = ?";
					$this->db->query($sql,$orign_order['order_seq']);
					$sql = "delete from fm_order_shipping where order_seq = ?";
					$this->db->query($sql,$orign_order['order_seq']);
				}
			}

		}else{
			$sql = "delete from fm_order_return where return_code=?";
			$this->db->query($sql, $return_code);
	
			$sql = "delete from fm_order_return_item where return_code=?";
			$this->db->query($sql, $return_code);
	
			if($data_return['return_type']=='return' && $data_return['refund_code']){
				$sql = "delete from fm_order_refund where refund_code=?";
				$this->db->query($sql, $data_return['refund_code']);
	
				$sql = "delete from fm_order_refund_item where refund_code=?";
				$this->db->query($sql, $data_return['refund_code']);
			}
		}
		
		// 실제 삭제 처리가 끝난 후에 구매확정 및정산 데이터 조정 by hed
		// 신정산개선에서는 1개의 출고에 대해 1개의 구매확정만 가능하며 반품/환불이 진행중인 경우 구매확정할 수 없음.
		// 따라서 철회건에 따른 일부 지급이 아닌 일반반품철회로 돌린 후 해당 전체 출고에 대해서 구매확정되도록 변경
		$msg = '';
		$this->load->library('buyconfirmlib');
		foreach($exports as $export_code=>$data_export){
			$auto_buyconfirms = false;

			// 반품철회에 따른 마일리지 지급 수량 및 반품수량 조절
			foreach($data_export['items'] as $export_items){
				$reserve_update				= false;
				## 마일리지 지급관련 반품취소 수량 ★
				$return_ea					= $export_items['return_item_ea']-$export_items['give_reserve_ea'];

				$tmp						= array();
				$tmp['export_item_seq']		= $export_items['export_item_seq'];
				$tmp['export_code']			= $export_items['export_code'];

				## 일반 반품취소
				if($export_items['reserve_return_ea'] > 0){
					$reserve_update				= true;
					//지급예정수량 = 지급예정수량 + 반품수량(회수마일리지(지급된)수량 제외)
					$tmp['reserve_ea']			= $export_items['reserve_ea'] + $return_ea;
					//지급예정반품수량 = 지급예정반품수량 - 반품수량(회수마일리지(지급된)수량 제외)
					$tmp['reserve_return_ea']	= $export_items['reserve_return_ea'] - $return_ea;
				}
				if($reserve_update) $export_items_reserve[]	= $tmp;
				
				$auto_buyconfirms = $export_items['auto_buyconfirms'];
			}

			## 구매확정사용시 : 마일리지지급예정수량,반품수량 조절 2015-03-26 pjm
			if($export_items_reserve) $this->exportmodel->exec_export_reserve_ea($export_items_reserve,'return_cancel');

			// 해당 출고의 구매확정 처리
			if($auto_buyconfirms){
				$dataExportArr		= array();
				if(preg_match('/^B/', $export_code)){
					$data_export_tmp	= $this->exportmodel->get_export_bundle($export_code);
					foreach($data_export_tmp['bundle_order_info'] as $bundle_key => $bundle_val){
						$dataExportArr[] = $bundle_key;
					}
				}else{
					$dataExportArr[]	= $export_code;
				}

				// 구매확정 관련 프로세스 통합 by hed
				foreach($dataExportArr as $export_code){
					$this->buyconfirmlib->exec_buyconfirm($export_code, $msg);
				}
			}
		}

		if ($this->db->trans_status() === FALSE || $rollback == true)
		{
		    $this->db->trans_rollback();
		    echo "반품삭제 처리중 오류가 발생했습니다.".$msg;
			exit;
		}
		else
		{
		    $this->db->trans_commit();
		}

		return "{$return_code} 반품 철회 완료";

	}
}

/* End of file returns_process.php */
/* Location: ./app/controllers/admin/returns_process.php */
