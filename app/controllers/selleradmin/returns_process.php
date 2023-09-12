<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class returns_process extends selleradmin_base {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('returnmodel');
		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
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
				if($data_return['npay_return_deliveryfee_ids']){
					$npay_return_deliveryfee_ids = explode(",",$data_return['npay_return_deliveryfee_ids']);
					foreach($npay_return_deliveryfee_ids as $ids){
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
		$data_return		= $this->returnmodel->get_return($return_code);
		$data_return_item	= $this->returnmodel->get_return_item($return_code);
		$private_masking    = $this->authmodel->manager_limit_act('private_masking');

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
			
				$zipcode = "";
				if($aParams['phone'][1] && $aParams['phone'][2]) $phone = implode('-',$aParams['phone']);
				if($aParams['cellphone'][1] && $aParams['cellphone'][2]) $cellphone = implode('-',$aParams['cellphone']);
				if($aParams['senderZipcode']) $zipcode = implode('-',$aParams['senderZipcode']);

				$update_param['cellphone'] 				= $cellphone;
				$update_param['phone'] 					= $phone;
				$update_param['sender_zipcode']			= $zipcode;
				$update_param['sender_address_type']	= ($aParams['senderAddress_type'])?$aParams['senderAddress_type']:"zibun";
				$update_param['sender_address']			= $aParams['senderAddress'];
				$update_param['sender_address_street']	= $aParams['senderAddress_street'];
				$update_param['sender_address_detail']	= $aParams['senderAddressDetail'];
			}
			$update_param['return_reason'] 			= $aParams['return_reason'];
			$update_param['return_method']			= $aParams['return_method'];
			$update_param['manager_seq']			= $this->managerInfo['manager_seq'];
			$update_param['return_type']			= $aParams['return_type'];
			$update_param['return_shipping_price']	= $aParams['return_shipping_price'];
			$update_param['return_shipping_gubun']	= $aParams['return_shipping_gubun'];			

			// 반품 관련 수정처리 추가 :: 2018-05-24 lwh
			$update_param['refund_ship_duty']		= $aParams['refund_ship_duty']; // 반품 배송비 책임
			$update_param['refund_ship_type']		= $aParams['refund_ship_type']; // 반품 배송비 지불 타입
		}

		if($data_return['status'] != "complete"){
			$update_param['status'] 		= $aParams['status'];
		}

		$update_param['return_type']	= $data_return['return_type'];
		if($aParams['admin_memo']) $update_param['admin_memo']		= $aParams['admin_memo'];

		##--------------------------------------------------------------------------------------------------
		# npay 반품요청 승인 처리 > 처리가능작업 :
		#	- 반품신청 -> 반품완료(O)
		#	- 반품신청 -> 반품처리중(X)
		#	- 반품처리중 -> 반품완료(X)
		#	- 반품처리중 -> 반품신청(X)
		if($npay_order){
			$this->load->model("naverpaymodel");
			$this->load->library('naverpaylib');

			if($data_return['return_type'] == "return"){
				$msg = "이 반품건은 네이버페이 반품보류건으로 보류해제 후 반품신청승인 가능합니다.";
			}else{
				$msg = "이 반품건은 네이버페이 교환보류건으로 보류해제 후 교환 수거 완료 처리 가능합니다.";
			}
			if($aParams['npay_return_hold'] == 'y' && $aParams['npay_return_released'] != 'y'){
				openDialogAlert($msg,550,160,'parent','');
				exit;
			}
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
						# npay 주문 반품완료 처리 안함.(반품요청승인 처리까지만)
						# npay 변경된 주문 수집시 자동 반품완료 처리됨.
						$itemdata = array("npay_product_order_id"=>$item['npay_product_order_id']
										,"opt_type"=>$item["opt_type"]);
						$npay_res = $this->npay_approve_return($data_return['return_type'],$itemdata,$data_return,$aParams['npay_return_released']);
						if($npay_res) $exchange_reorder = true; else $exchange_reorder = false;
					}else{
						$exchange_reorder = true;
					}
					##--------------------------------------------------------------------------------------------------
					//선택한 재고증가 수량만큼 증감 2015-03-31 pjm
					if(!$npay_order){
						$stock_return_ea = $aParams['stock_return_ea'][$return_item_seq];

						// 반품으로 인한 재고증가
						$goodsData = $this->returnmodel->return_stock_ea($stock_return_ea,$return_item_seq,$item,$goodsData);
					}
				}
			}

			// 재주문 넣기(맞교환)
			if($update_param['return_type'] == 'exchange' && $exchange_reorder){
				$this->ordermodel->reorder($data_return['order_seq'],$return_code);
			}
		}


		# 반품정보 업데이트
		if(!$npay_order){
			$this->db->where('return_code',$aParams['return_code']);
			$this->db->update('fm_order_return',$update_param);
		}

		# 재고차감할 반품수량
		$return_ea_arr = $aParams['stock_return_ea'];
		foreach($aParams['stock_return_ea'] as $return_item_seq=>$stock_return_ea)
		{
			unset($update_param);
			if(!$npay_order){
				$update_param['reason_code'] = $aParams['reason'][$return_item_seq];
				if (!empty($aParams['reason_desc'][$return_item_seq])) {
					$update_param['reason_desc'] = $aParams['reason_desc'][$return_item_seq];
				}
			}

			$stock_return_ea	= $aParams['stock_return_ea'][$return_item_seq];
			$return_badea		= $aParams['return_badea'][$return_item_seq];

			if( !is_array($stock_return_ea) ){
				$update_param['stock_return_ea']	= $stock_return_ea;
				$update_param['return_badea']		= $return_badea;
			}else{
				$update_param['package_stock_return_ea']= serialize($stock_return_ea);
				$update_param['package_return_badea']	= serialize($return_badea);
			}
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
				$logDetail	= "관리자가 반품완료처리를 하였습니다.";
				$logParams	= array('return_code' => $aParams['return_code']);
				$this->load->model('ordermodel');
				$this->ordermodel->set_log($data_return['order_seq'],'process',$this->providerInfo['provider_name'],$logTitle,$logDetail,$logParams);


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

				$callback = "parent.document.location.reload();";
				openDialogAlert("반품처리가 완료 되었습니다.",400,140,'parent',$callback);
				exit;
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
}

/* End of file returns_process.php */
/* Location: ./app/controllers/selleradmin/returns_process.php */