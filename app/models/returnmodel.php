<?php
class returnmodel extends CI_Model {
    
    // 네이버페이 구매자/판매자 부담별 반품 사유코드
    const NPAY_SHIP_DUTY_CODE = array(
        'buyer'         =>      array(
            'INTENT_CHANGED',
            'COLOR_AND_SIZE',
            'WRONG_ORDER',
            'PRODUCT_UNSATISFIED',
        ),
        'seller'        =>      array(
            'DELAYED_DELIVERY',
            'SOLD_OUT',
            'DROPPED_DELIVERY',
            'BROKEN',
            'INCORRECT_INFO',
            'WRONG_DELIVERY',
            'WRONG_OPTION',
        ),
    );
    
	public function __construct()
	{
//		$this->arr_return_status = array(
//			'request'	=> '반품 신청',
//			'ing'		=> '반품 처리중',
//			'complete'	=> '반품 완료'
//		);
//
//		$this->arr_return_type = array(
//			'exchange'	=> '맞교환',
//			'return'	=> '반품'
//		);
//
//		$this->arr_return_method = array(
//			'user'	=> '자가반품',
//			'shop'	=> '택배회수'
//		);

		$this->arr_return_status = array(
			'request'	=> getAlert('mp195'),
			'ing'		=> getAlert('mp196'),
			'complete'	=> getAlert('mp197')
		);

		$this->arr_return_type = array(
			'exchange'	=> getAlert('mp198'),
			'return'	=> getAlert('mp199')
		);

		$this->arr_return_method = array(
			'user'	=> getAlert('mp200'),
			'shop'	=> getAlert('mp201')
		);
	}

	//반품 전 출고건 배송완료 처리 및 마일리지 지급 처리
	public function order_return_delivery_confirm($cfg_order,$data,$system=''){

		$this->load->model("exportmodel");

		$export_codes			= array();
		$export_items			= array();
		$export_items_reserve	= array();
		$give_reserve_ea		= array();

		$succ_deliv_cnt = 0;
		foreach($data['chk_export_code'] as $k => $chk_export_code){

			$chk_item_seq		= $data['chk_item_seq'][$k];
			$chk_option_seq		= $data['chk_option_seq'][$k];
			$chk_suboption_seq	= $data['chk_suboption_seq'][$k];
			$partner_return		= $data['partner_return'][$k];

			if($partner_return) {

				$succ_deliv_cnt++;		//npay

				if(!$chk_suboption_seq){
					$option_type		= "OPT";
					$option_seq			= $chk_option_seq;
				}else{
					$option_type		= "SUB";
					$option_seq			= $chk_suboption_seq;
				}

				$cancelquery = "select * from fm_order_item where item_seq=?";
				$cancelquery = $this->db->query($cancelquery,array($data['chk_item_seq'][$k]));
				$orditemData = $cancelquery->row_array();

				if($option_type == "OPT"){
					//티켓상품의 취소(환불) 가능여부::반품
					if ( $orditemData['goods_kind'] == 'coupon') continue;
				}
				if(!in_array($chk_export_code,$export_codes)) $export_codes[] = $chk_export_code;

				//지급수량에서 차감된 반품수량
				$give_reserve_ea[$chk_export_code][$option_type][$option_seq] = 0;

				##----------------------------------------------------------------------------------
				## 구매확정 사용시 @2015-03-27 pjm
				## user 반품신청 시 마일리지 지급예정 수량만큼만 반품 신청 가능.
				if($cfg_order['buy_confirm_use']){

					# 출고정보 불러오기
					$chk						= array();
					$chk['export_code']			= $chk_export_code;
					$chk['item_seq']			= $chk_item_seq;
					if($option_type == "OPT") $chk['option_seq'] = $chk_option_seq;
					else $chk['suboption_seq'] = $chk_suboption_seq;
					$exp_items = $this->exportmodel->get_export_item_by_item_seq('',$chk);

					# 지급예정수량+지급수량+소멸수량이 있을때
					# 차감순서 지급예정수량>지급수량>소멸수량
					$tmp = array();
					if(($exp_items['reserve_ea']+$exp_items['reserve_buyconfirm_ea']+$exp_items['reserve_destroy_ea']) > 0){
						$tmp = array();
						$tmp['export_code']			= $chk_export_code;
						$tmp['item_seq']			= $exp_items['item_seq'];
						if($exp_items['option_seq']){
							$tmp['option_seq']		= $exp_items['option_seq'];
						}
						if($exp_items['suboption_seq']){
							$tmp['suboption_seq']	= $exp_items['suboption_seq'];
						}

						## 지급예정반품수량으로 이동할 반품수량
						$reserve_return_ea	= $data['chk_ea'][$k];

						# 잔여반품처리수량(지급예정수량 - 반품수량)
						$reserve_ea_remain	= $exp_items['reserve_ea'] - $data['chk_ea'][$k];

						# 잔여반품처리수량이 0보다 작으면(지급예정수량 < 반품수량)
						#   => 지급수량, 소멸수량 순으로 추가 차감.
						if($reserve_ea_remain < 0){
							$reserve_ea_remain	= abs($reserve_ea_remain);
							$reserve_ea_remain2	= $exp_items['reserve_buyconfirm_ea']-$reserve_ea_remain;
							# 잔여반품처리수량이 지급수량
							if($reserve_ea_remain2 >= 0){
								$reserve_ea_remain2 = $reserve_ea_remain;
							}else{
								$reserve_ea_remain2 = $exp_items['reserve_buyconfirm_ea'];
							}
							# 지급예정수량이 반품수량보다 작으면 지급예정수량만큼만 이동
							$reserve_return_ea				= $exp_items['reserve_ea'];
							# 마일리지가 지급수량된 반품수량
							$give_reserve_ea[$chk_export_code][$option_type][$option_seq] = abs($reserve_ea_remain2);
							$reserve_ea_remain				= 0;
						}
						$tmp['reserve_ea']			= $reserve_ea_remain;
						$tmp['reserve_return_ea']	= $exp_items['reserve_return_ea'] + $reserve_return_ea;
						$export_items_reserve[]		= $tmp;
					}
				##----------------------------------------------------------------------------------
				## 구매확정 미사용시
				}else{
					//지급수량에서 차감된 반품수량
					//구매확정 미사용일때 반품신청시 자동배송완료처리 되며, 이때 마일리지도 같이 지급된다.
					$give_reserve_ea[$chk_export_code][$option_type][$option_seq] = $data['chk_ea'][$k];
				}
				##----------------------------------------------------------------------------------
			}else{
				$succ_deliv_cnt++;
			}
		}

		## 구매확정 사용시에만 지급예정수량 조절 2015-03-26 pjm
		if($cfg_order['buy_confirm_use'] && $export_items_reserve){
			$this->exportmodel->exec_export_reserve_ea($export_items_reserve,'return');
		}

		## 배송완료처리 (구매확정 미사용시 배송완료 처리 후 지급예정수량 조절)
		if(count($data['chk_item_seq']) ==  $succ_deliv_cnt){
			foreach($export_codes as $export_code){

				$exports		= $this->exportmodel->get_export($export_code);
				$reserve_save	= $exports['reserve_save'];		//마일리지 지급여부

				if(in_array($exports['status'],array(55,60,65,70))){
					//반품 또는 맞교환 환불신청시 메일/문자 미처리 @2016-12-09
					$this->_batch_buy_return = ($data['mode'])?$data['mode']:'return';
					$reserve_save = $this->exportmodel->exec_complete_delivery($export_code,$system);// 배송완료(수령확인)처리(후 적립금지급여부)
				}
			}
		}
		return $give_reserve_ea;
	}

	# 반품으로 인한 재고복구
	public function return_stock_ea($stock_return_ea,$return_item_seq,$item,$goodsData){

		// 패키지 상품 재고증가 처리
		if(is_array( $stock_return_ea ) ){

			foreach($stock_return_ea as $option_code => $return_ea){

				//연결상품 정보 가져오기
				if(preg_match('/suboption/',$option_code)){ // 추가옵션
					$package_suboption_seq = str_replace('suboption','',$option_code);
					list($data_package) = $this->orderpackagemodel->get_suboption($package_suboption_seq,true);
				}else if(preg_match('/option/',$option_code)) { // 필수옵션
					$package_option_seq = str_replace('option','',$option_code);
					list($data_package) = $this->orderpackagemodel->get_option($package_option_seq,true);
				}

				$param_item						= array();
				$param_item['opt_type']			= 'opt';
				$param_item['provider_seq']		= $item['provider_seq'];
				$param_item['option1']			= $data_package['option1'];
				$param_item['option2']			= $data_package['option2'];
				$param_item['option3']			= $data_package['option3'];
				$param_item['option4']			= $data_package['option4'];
				$param_item['option5']			= $data_package['option5'];
				$param_item['goods_seq']		= $data_package['goods_seq'];
				$param_item['goods_code']		= $data_package['goods_code'];
				$param_item['scm_supply_price'] = $data_package['supply_price'];
				$param_item['return_item_seq']	= $return_item_seq;

				$param_badea					= $_POST['return_badea'][$return_item_seq][$option_code];
				$param_location_code			= $_POST['location_code'][$return_item_seq][$option_code];
				$param_location_position		= $_POST['location_position'][$return_item_seq][$option_code];

				$goodsData[] = $this->set_stock($return_code,$return_ea,$param_badea,$param_location_code,$param_location_position,$param_item);
			}

		// 일반 상품 재고증가 처리
		}else{

			$param_badea				= $_POST['return_badea'][$return_item_seq];
			$param_location_code		= $_POST['location_code'][$return_item_seq];
			$param_location_position	= $_POST['location_position'][$return_item_seq];

			$goodsData[] = $this->set_stock($return_code,$stock_return_ea,$param_badea,$param_location_code,$param_location_position,$item);
		}

		return $goodsData;

	}

	public function set_stock($return_code,$stock_return_ea,$badea,$location_code,$location_position,$item){

        // 물류관리 관련 설정정보 추출
        if    (!$this->scm_cfg)        $this->scm_cfg            = config_load('scm');
        
		if($item['opt_type'] == 'opt'){
			if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == '1'){
				unset($sc);
				if	($item['option1'])	$sc['option1']	= $item['option1'];
				if	($item['option2'])	$sc['option2']	= $item['option2'];
				if	($item['option3'])	$sc['option3']	= $item['option3'];
				if	($item['option4'])	$sc['option4']	= $item['option4'];
				if	($item['option5'])	$sc['option5']	= $item['option5'];
				$optinfo		= $this->goodsmodel->get_goods_option($item['goods_seq'], $sc);
				if	($optinfo[0]['option_seq'] > 0){
					unset($tmp);
					$tmp['return_code']			= $return_code;
					$tmp['goods_code']			= $item['goods_code'];
					$tmp['goods_name']			= $item['goods_name'];
					$tmp['option_name']			= implode('', $sc);
					$tmp['optioninfo']			= $item['goods_seq'] . 'option' . $optinfo[0]['option_seq'];
					$tmp['ea']					= $stock_return_ea;
					$tmp['bad_ea']				= $badea;
					$tmp['supply_price']		= $item['scm_supply_price'];
					$tmp['location_position']	= $location_position;
					$tmp['location_code']		= $location_code;
					$goodsData					= $tmp;
				}
			}

			$this->goodsmodel->stock_option(
				'+',
				$stock_return_ea,
				$item['goods_seq'],
				$item['option1'],
				$item['option2'],
				$item['option3'],
				$item['option4'],
				$item['option5']
			);
		}else{
			if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == '1'){
				unset($sc);
				if	($item['title1'])	$sc['suboption_title']	= $item['title1'];
				if	($item['option1'])	$sc['suboption']		= $item['option1'];
				$optinfo		= $this->goodsmodel->get_goods_option($item['goods_seq'], $sc);
				if	($optinfo[0]['suboption_seq'] > 0){
					unset($tmp);
					$tmp['return_code']		= $return_code;
					$tmp['goods_code']		= $item['goods_code'];
					$tmp['goods_name']		= $item['goods_name'];
					$tmp['option_name']		= $item['option1'];
					$tmp['optioninfo']		= $item['goods_seq'] . 'suboption' . $optinfo[0]['suboption_seq'];
					$tmp['ea']				= $stock_return_ea;
					$tmp['bad_ea']			= $badea;
					$tmp['supply_price']	= $item['scm_supply_price'];
					$tmp['location_position']	= $location_position;
					$tmp['location_code']		= $location_code;
					$goodsData				= $tmp;
				}
			}

			$this->goodsmodel->stock_suboption(
				'+',
				$stock_return_ea,
				$item['goods_seq'],
				$item['title1'],
				$item['option1']
			);
		}

		return $goodsData;
	}

	# 재고조정 히스토리 저장
	public function return_stock_history($return_code,$retuns_goods_coupon_ea,$data_return_item,$return_ea_arr,$return_date){

		// 품절체크를 위한 변수선언
		$r_runout_goods_seq = array();

		if( !$retuns_goods_coupon_ea ) {//티켓상품 마일리지/포인트, 재고, 할인쿠폰 반환없음

			$this->load->model('stockmodel');

			$data = array();
			$data['reason']			= 'input';
			$data['supplier_seq']	= '';
			$data['reason_detail']	= '반품';
			$data['stock_date']		= date('Y-m-d H:i:s');
			$stock_code				= $this->stockmodel->insert_stock_history($data);

			foreach($data_return_item as $item){

				$return_item_seq = $item['return_item_seq'];
				//선택한 재고증가 수량만큼 저장 2015-03-31 pjm
					$stock_return_ea = $return_ea_arr[$return_item_seq];

				if(is_array($stock_return_ea)){

					foreach($stock_return_ea as $option_code => $return_ea){
						//연결상품 정보 가져오기
						if(preg_match('/suboption/',$option_code)){ // 추가옵션
							$package_suboption_seq	= str_replace('suboption','',$option_code);
							list($data_package)		= $this->orderpackagemodel->get_suboption($package_suboption_seq,true);
						}else if(preg_match('/option/',$option_code)) { // 필수옵션
							$package_option_seq		= str_replace('option','',$option_code);
							list($data_package)		= $this->orderpackagemodel->get_option($package_option_seq,true);
						}

						$data = array();
						$data['goods_name']			= $data_package['goods_name'];
						$data['option_type']		= 'option';
						$data['stock_code']			= $stock_code;
						$data['goods_seq']			= $data_package['goods_seq'];
						$data['prev_supply_price']	= $data_package['supply_price'];
						$data['supply_price']		= $data_package['supply_price'];
						$data['ea']					= $return_ea;

						for($i=1;$i<=5;$i++){
							if(!empty($data_package['title'.$i])){
								$data['title'.$i] = $data_package['title'.$i];
								$data['option'.$i] = $data_package['option'.$i];
							}
						}

						$this->stockmodel->insert_stock_history_item($data);

						// 출고예약량/품절체크 업데이트를 위한 변수정의
						if(!in_array($data_package['goods_seq'],$r_runout_goods_seq)){
							$r_runout_goods_seq[] = $data_package['goods_seq'];
						}

					}

				}else{

					$data = array();
					$data['goods_name']			= $item['goods_name'];
					$data['option_type']		= $item['opt_type'] == 'opt' ? 'option' : 'suboption';
					$data['stock_code']			= $stock_code;
					$data['goods_seq']			= $item['goods_seq'];
					$data['prev_supply_price']	= $item['supply_price'];
					$data['supply_price']		= $item['supply_price'];
					$data['ea']					= $stock_return_ea;

					for($i=1;$i<=5;$i++){
						if(!empty($item['title'.$i])){
							$data['title'.$i] = $item['title'.$i];
							$data['option'.$i] = $item['option'.$i];
						}
					}

					$this->stockmodel->insert_stock_history_item($data);

					// 품절체크를 업데이트를 위한 변수정의
					if(!in_array($item['goods_seq'],$r_runout_goods_seq)){
						$r_runout_goods_seq[] = $item['goods_seq'];
					}
				}
			}

			// 출고예약량/품절체크 업데이트
			foreach($r_runout_goods_seq as $goods_seq){
				$this->goodsmodel->modify_reservation_real($goods_seq);
			}
		}

		$this->load->model('accountmodel');
		$res = $this->accountmodel->set_return($return_code,$return_date);

	}

	//반품,교환 정보 insert
	public function order_return_insert($return_data,$refund_code='',$return_type){

		$minfo				= $this->session->userdata('manager');
		$manager_seq		= $minfo['manager_seq'];
		if( defined('__SELLERADMIN__') === true ){
			$minfo			= $this->session->userdata('provider');
			$manager_seq	= $minfo['provider_seq'];
		}

		$order_seq			= $return_data['order_seq'];
		$phone				= $return_data['phone'];
		$cellphone			= $return_data['cellphone'];
		$zipcode			= $return_data['return_recipient_zipcode'];

		if(is_array($phone)){
			if($phone[1] && $phone[2]) $phone = implode('-',$phone); else $phone = '';
		}
		if(is_array($cellphone)){
			if($cellphone[1] && $cellphone[2]) $cellphone = implode('-',$cellphone); else $cellphone = '';
		}
		if(is_array($zipcode)){
			if($zipcode[0]) $zipcode = implode('-',$zipcode); else $zipcode = '';
		}

		if(!$phone)		$phone		= "";
		if(!$cellphone)	$cellphone	= "";
		if(!$zipcode)	$zipcode	= "";

		if(!$return_data['reason_detail']) $reason_detail = ""; else $reason_detail = $return_data['reason_detail'];

		$return_method					= $return_data['return_method'];
		$return_recipient_address_type	= $return_data['return_recipient_address_type'];
		$return_recipient_address		= ($return_data['return_recipient_address'])? $return_data['return_recipient_address'] : "" ;
		$return_recipient_address_street= ($return_data['return_recipient_address_street'])? $return_data['return_recipient_address_street'] : "" ;
		$return_recipient_address_detail= ($return_data['return_recipient_address_detail'])? $return_data['return_recipient_address_detail'] : "" ;

		// 반품 등록
		$insert_data['status'] 							= 'request';
		$insert_data['order_seq'] 						= $order_seq;
		$insert_data['refund_code'] 					= $refund_code;
		$insert_data['return_type'] 					= $return_type;
		$insert_data['return_reason']					= $reason_detail;
		$insert_data['cellphone'] 						= $cellphone;
		$insert_data['phone'] 							= $phone;
		$insert_data['return_method'] 					= $return_method;
		$insert_data['sender_zipcode'] 					= $zipcode;
		$insert_data['sender_address_type']				= $return_recipient_address_type;
		$insert_data['sender_address'] 					= $return_recipient_address;
		$insert_data['sender_address_street']			= $return_recipient_address_street;
		$insert_data['sender_address_detail']			= $return_recipient_address_detail;
		$insert_data['regist_date'] 					= date('Y-m-d H:i:s');
		$insert_data['important'] 						= 0;
		$insert_data['manager_seq'] 					= $manager_seq;
		$insert_data['shipping_price_depositor'] 		= $return_data['shipping_price_depositor'];
		$insert_data['shipping_price_bank_account']		= $return_data['shipping_price_bank_account'];

		// 환불배송비 자동계산 :: 2018-05-21 lwh
		$insert_data['reason_code']				= $_POST['reason'];
		$insert_data['reason_desc']				= $_POST['reason_desc'];
		$insert_data['refund_ship_duty']		= $_POST['refund_ship_duty'];
		$insert_data['refund_ship_type']		= $_POST['refund_ship_type'];
		$insert_data['return_shipping_price']	= $_POST['return_shipping_price'];

		if($return_data['npay_order_id']){
			$insert_data['manager_seq'] 				= '0';
			$insert_data['admin_memo']					= $return_data['admin_memo'];
			$insert_data['npay_order_id']				= $return_data['npay_order_id'];
			$insert_data['npay_return_request_date']	= $return_data['npay_request_date'];
			$insert_data['npay_return_complete_date']	= $return_data['npay_complete_date'];
			$insert_data['npay_flag']					= strtolower($return_data['npay_flag']);
			$insert_data['return_shipping_gubun']		= $return_data['return_shipping_gubun'];
			$insert_data['npay_return_deliveryfee_ids']	= $return_data['npay_claim_deliveryfee_ids'];
			//$return_data['reason_desc']	= $return_data['reason'];
		}

		$items = array();

		foreach($return_data['chk_seq'] as $k=>$v){
			$items[$k]['item_seq']			= $return_data['chk_item_seq'][$k];
			$items[$k]['option_seq']		= $return_data['chk_suboption_seq'][$k] ? '' : $return_data['chk_option_seq'][$k];
			$items[$k]['suboption_seq']		= $return_data['chk_suboption_seq'][$k];
			$items[$k]['ea']				= $return_data['chk_ea'][$k];
			// isset으로 검사하여 데이터가 잘라서 들어감 array 인지 판단 후 값 세팅 :: 2018-07-18 pjw
			$items[$k]['reason_code']		= is_array($return_data['reason']) ? $return_data['reason'][$k] : $return_data['reason'];
			$items[$k]['reason_desc']		= is_array($return_data['reason_desc']) ? $return_data['reason_desc'][$k] : $return_data['reason_desc'];
			$items[$k]['export_code']		= $return_data['chk_export_code'][$k];
			$items[$k]['give_reserve_ea']	= $return_data['give_reserve_ea'][$k];	//회수마일리지수량
			$items[$k]['give_reserve']		= $return_data['give_reserve'][$k];		//회수마일리지액
			$items[$k]['give_point']		= $return_data['give_point'][$k];			//회수포인트액
			$items[$k]['return_badea']		= '0';					// 불량재고로 반품 ( scm )
			$items[$k]['scm_supply_price']	= $return_data['scm_supply_price'][$k];	// 출고당시 출고창고 평균매입가 ( scm )
			$items[$k]['partner_return']	= $return_data['partner_return'][$k];		//외부연동몰(npay)반품접수 결과
			if($return_data['chk_npay_product_order_id'][$k]){
				$items[$k]['npay_product_order_id'] = $return_data['chk_npay_product_order_id'][$k];
				$items[$k]['stock_return_ea']		= ($return_data['stock_return_ea'][$k])?$return_data['stock_return_ea'][$k] : $return_data['chk_ea'][$k];
			}
		}

		$return_code = $this->insert_return($insert_data,$items);

		return $return_code;

	}


	public function insert_return($data,$items,$trans=false)
	{
		// trans == true 인 경우 transaction 을 controller에서 처리하여 rollback 처리함
		// controller 에서 transaction 을 사용하는 경우 trans 값을 true 로 전달해야함 2020-01-07
		$result = $this->db->insert('fm_order_return', $data);
		if( $result === FALSE  && $trans == true) {
			return false;
		}
		$return_seq = $this->db->insert_id();
		$update_data['return_code'] = 'R'.date('ymdH').$return_seq;

		$this->db->where('return_seq',$return_seq);
		$this->db->update('fm_order_return',$update_data);

		foreach($items as $item_data){
			if($item_data['npay_product_order_id']){
				$partner_return = $item_data['partner_return'];
			}else{
				$partner_return = true;
			}
			if($partner_return){
				$item_data['return_code'] = $update_data['return_code'];
				$item_data = array_diff_key($item_data,array("partner_return"=>""));
				$this->db->insert('fm_order_return_item',$item_data);
			}
		}

		return $update_data['return_code'];
	}

	public function get_return_list($sc){

		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');

		$sc['page']		= !empty($sc['page'])		? intval($sc['page']):'1';
		$sc['perpage']	= !empty($sc['perpage'])	? intval($sc['perpage']):'10';

		$sqlWhereClause = "";
		$sqlLimitClause = "";

		if(!empty($sc['member_seq'])){
			$sqlWhereClause .= " and o.member_seq = '{$sc['member_seq']}'";
		}
		if(!empty($sc['order_seq'])){
			$sqlWhereClause .= " and o.order_seq = '{$sc['order_seq']}'";
		}

		if($sqlWhereClause) $sqlWhereClause = "where 1 " . $sqlWhereClause;

		$sql = "
		SELECT * FROM (
			select
			r.*,
			o.payment,
			(
				SELECT goods_name FROM fm_order_item WHERE item_seq=ri.item_seq ORDER BY item_seq LIMIT 1
			) goods_name,
			(
				SELECT image FROM fm_order_item WHERE item_seq=ri.item_seq ORDER BY item_seq LIMIT 1
			) image,
			(
				SELECT count(item_seq) FROM fm_order_return_item WHERE return_code=r.return_code
			) item_cnt,
			m.userid,
			m.user_name,
			(
				SELECT sum(ea) FROM fm_order_item_option WHERE order_seq=o.order_seq
			) option_ea,
			(
				SELECT sum(ea) FROM fm_order_item_suboption WHERE order_seq=o.order_seq
			) suboption_ea,
			sum(ri.ea) as return_ea_sum
			from
				fm_order_return as r
				inner join fm_order as o on r.order_seq = o.order_seq
				inner join fm_member as m on o.member_seq = m.member_seq
				inner join fm_order_return_item as ri on r.return_code=ri.return_code
			{$sqlWhereClause}
			group by r.return_code
		) t
		ORDER BY regist_date DESC
		";
			
		if($sc['mode'] == 'count'){
			$query	= str_replace('ORDER BY regist_date DESC', '', $sql);
			$query	= str_replace('SELECT * FROM (', 'SELECT count(*) cnt FROM (', $sql);
			$query	= $this->db->query($query);
			$data	= $query->row_array();
			return $data['cnt'];
		}

		$result = select_page($sc['perpage'],$sc['page'],10,$sql,array());
		$result['page']['querystring'] = get_args_list();

		foreach($result['record'] as $k => $data)
		{
			$no++;

			$result['record'][$k]['mpayment'] = $this->arr_payment[$result['record'][$k]['payment']];
			$result['record'][$k]['mstatus'] = $this->arr_return_status[$result['record'][$k]['status']];
			$result['record'][$k]['mtype'] = $this->arr_return_type[$result['record'][$k]['return_type']];
			$result['record'][$k]['mreturn_date'] = $result['record'][$k]['return_date']=='0000-00-00 00:00:00' ? '' : substr($result['record'][$k]['return_date'],0,10);

			//주문상품의 이미지가 없는경우 실제상품의 이미지를 가져옴
			if( !(is_file($data['image'])) ) {
				$result['record'][$k]['image'] = viewImg($data['goods_seq'],'thumbCart');
			}
		}

		if($result['record'])
		{
			$result['record'][$k]['end'] = true;
			foreach($result['record'] as $k => $data){
				$result['record'][$k]['no'] = $no;
				$no--;
			}
		}

		return $result;
	}

	public function get_return($return_code)
	{
		$query = "select * from fm_order_return where return_code=? limit 1";
		$query = $this->db->query($query,array($return_code));
		list($result) = $query -> result_array();
		return $result;
	}

	public function get_return_refund_code($refund_code)
	{
		$query = "select * from fm_order_return where refund_code=? limit 1";
		$query = $this->db->query($query,array($refund_code));
		list($result) = $query -> result_array();
		return $result;
	}

	public function get_return_for_order($order_seq,$type=null)
	{
		if( defined('__SELLERADMIN__') === true ){
			$bind	= array($order_seq, $this->providerInfo['provider_seq']);
		}else{
			$bind	= array($order_seq);
		}
		if($type){
			$addWhere .= " and return_type= ? ";
			$bind[]	= $type;
		}

		if( defined('__SELLERADMIN__') === true ){
			//
			$query = "
			select r.*,
			sum(i.ea) ea,
			sum(ifnull(sub.price,0)*i.ea) sub_price,
			sum(ifnull(opt.sale_price,0)*i.ea) opt_price,
			(
				SELECT goods_name FROM fm_order_item WHERE item_seq=i.item_seq ORDER BY item_seq LIMIT 1
			) goods_name,
			(select mname from fm_manager where manager_seq=r.manager_seq) admin
			from
			fm_order_return r,
			fm_order_return_item i
			left join fm_order_item_option opt on opt.item_option_seq=i.option_seq
			left join fm_order_item_suboption sub on sub.item_suboption_seq=i.suboption_seq
			LEFT JOIN fm_order_item orditem ON orditem.item_seq = i.item_seq
			where r.return_code=i.return_code and r.order_seq=? and orditem.provider_seq=? ".$addWhere." group by r.return_code";
			$query = $this->db->query($query,$bind);
		}else{
			$query = "
			select r.*,
			sum(i.ea) ea,
			sum(ifnull(sub.price,0)*i.ea) sub_price,
			sum(ifnull(opt.sale_price,0)*i.ea) opt_price,
			(
				SELECT goods_name FROM fm_order_item WHERE item_seq=i.item_seq ORDER BY item_seq LIMIT 1
			) goods_name,
			(select mname from fm_manager where manager_seq=r.manager_seq) admin
			from
			fm_order_return r,
			fm_order_return_item i
			left join fm_order_item_option opt on opt.item_option_seq=i.option_seq
			left join fm_order_item_suboption sub on sub.item_suboption_seq=i.suboption_seq
			where r.return_code=i.return_code and r.order_seq=? ".$addWhere." group by r.return_code";
			$query = $this->db->query($query,$bind);
		}
		foreach($query -> result_array() as $data) {
			$data['mtype'] = $this->arr_return_type[$data['return_type']];
			$result[] = $data;
		}
		return $result;
	}

	public function get_return_item($return_code)
	{
		if( defined('__SELLERADMIN__') === true ){
			// 3차 환불 개선으로 shipping_seq 추가 :: 2018-11- lkh
			$query1 = "
			SELECT
			'opt' opt_type,
			opt.item_option_seq option_seq,
			opt.supply_price,
			opt.consumer_price,
			opt.price,
			opt.goods_code,
			item.goods_shipping_cost,
			opt.download_seq,
			opt.coupon_sale,
			opt.member_sale,
			opt.fblike_sale,
			opt.mobile_sale,
			opt.promotion_code_sale,
			opt.referer_sale,
			opt.reserve,
			opt.point as point,
			item.goods_name,
			item.goods_type,
			item.image,
			item.adult_goods,
			item.option_international_shipping_status,
			item.tax,
			(select cancel_type from fm_goods where goods_seq = item.goods_seq) as cancel_type,
			opt.title1,
			opt.title2,
			opt.title3,
			opt.title4,
			opt.title5,
			opt.option1,
			opt.option2,
			opt.option3,
			opt.option4,
			opt.option5,
			opt.newtype,
			opt.color,
			opt.zipcode,
			opt.address,
			opt.addressdetail,
			opt.biztel,
			opt.address_commission,
			opt.codedate,
			opt.sdayinput,
			opt.fdayinput,
			opt.dayauto_type,
			opt.sdayauto,
			opt.fdayauto,
			opt.dayauto_day,
			opt.social_start_date,
			opt.social_end_date,
			opt.coupon_input,
			opt.coupon_input_one,
			opt.package_yn,
			item.goods_seq,
			item.goods_kind,
			item.socialcp_input_type,
			item.socialcp_use_return,
			item.socialcp_use_emoney_day,
			item.socialcp_use_emoney_percent,
			item.item_seq,
			item.shipping_seq,
			item.event_seq,
			item.goods_shipping_cost,
			item.provider_seq,
			ref.return_item_seq,
			ref.reason_code,
			ref.reason_desc,
			ref.return_ea,
			ref.ea,
			ref.return_badea,
			ref.export_code,
			ref.stock_return_ea,
			ref.package_stock_return_ea,
			ref.give_reserve_ea,
			ref.npay_product_order_id,
			ref.scm_supply_price,
			ref.return_badea,
			ref.package_return_badea,
			ref.location_position,
			ref.location_code
			FROM
			fm_order_return_item ref,fm_order_item_option opt,fm_order_item item
			WHERE
			ref.option_seq is not null
			AND ref.option_seq = opt.item_option_seq
			AND opt.item_seq = item.item_seq
			AND item.provider_seq = {$this->providerInfo['provider_seq']}
			AND ref.return_code = ?
			";
			$query2 = "
			SELECT
			'sub' opt_type,
			sub.item_suboption_seq option_seq,
			sub.supply_price,
			sub.consumer_price,
			sub.price,
			sub.goods_code,
			0 goods_shipping_cost,
			'' download_seq,
			0 coupon_sale,
			0 member_sale,
			0 fblike_sale,
			0 mobile_sale,
			0 promotion_code_sale,
			0 referer_sale,
			sub.reserve as reserve,
			sub.point as point,
			item.goods_name,
			item.goods_type,
			item.image,
			item.adult_goods,
			item.option_international_shipping_status,
			item.tax,
			(select cancel_type from fm_goods where goods_seq = item.goods_seq) as cancel_type,
			sub.title title1,
			'' title2,
			'' title3,
			'' title4,
			'' title5,
			sub.suboption option1,
			'' option2,
			'' option3,
			'' option4,
			'' option5,
			sub.newtype,
			sub.color,
			sub.zipcode,
			sub.address,
			sub.addressdetail,
			sub.biztel,
			'' address_commission,
			sub.codedate,
			sub.sdayinput,
			sub.fdayinput,
			sub.dayauto_type,
			sub.sdayauto,
			sub.fdayauto,
			sub.dayauto_day,
			sub.social_start_date,
			sub.social_end_date,
			sub.coupon_input,
			sub.coupon_input_one,
			sub.package_yn,
			item.goods_seq,
			item.goods_kind,
			item.socialcp_input_type,
			item.socialcp_use_return,
			item.socialcp_use_emoney_day,
			item.socialcp_use_emoney_percent,
			item.item_seq,
			item.event_seq,
			item.shipping_seq,
			item.goods_shipping_cost,
			item.provider_seq,
			ref.return_item_seq,
			ref.reason_code,
			ref.reason_desc,
			ref.return_ea,
			ref.ea,
			ref.return_badea,
			ref.export_code,
			ref.stock_return_ea,
			ref.package_stock_return_ea,
			ref.give_reserve_ea,
			ref.npay_product_order_id,
			ref.scm_supply_price,
			ref.return_badea,
			ref.package_return_badea,
			ref.location_position,
			ref.location_code
			FROM
			fm_order_return_item ref,fm_order_item_suboption sub,fm_order_item item
			WHERE
			ref.suboption_seq is not null
			AND ref.suboption_seq = sub.item_suboption_seq
			AND sub.item_seq = item.item_seq
			AND item.provider_seq = {$this->providerInfo['provider_seq']}
			AND ref.return_code = ?
			";
		}else{
			// 3차 환불 개선으로 shipping_seq 추가 :: 2018-11- lkh
			$query1 = "
			SELECT
			'opt' opt_type,
			opt.item_option_seq option_seq,
			opt.supply_price,
			opt.consumer_price,
			opt.price,
			opt.goods_code,
			item.goods_shipping_cost,
			opt.download_seq,
			opt.coupon_sale,
			opt.member_sale,
			opt.fblike_sale,
			opt.mobile_sale,
			opt.promotion_code_sale,
			opt.referer_sale,
			opt.reserve,
			opt.point as point,
			item.goods_name,
			item.goods_type,
			item.image,
			opt.title1,
			opt.title2,
			opt.title3,
			opt.title4,
			opt.title5,
			opt.option1,
			opt.option2,
			opt.option3,
			opt.option4,
			opt.option5,
			opt.newtype,
			opt.color,
			opt.zipcode,
			opt.address,
			opt.addressdetail,
			opt.biztel,
			opt.address_commission,
			opt.codedate,
			opt.sdayinput,
			opt.fdayinput,
			opt.dayauto_type,
			opt.sdayauto,
			opt.fdayauto,
			opt.dayauto_day,
			opt.social_start_date,
			opt.social_end_date,
			opt.coupon_input,
			opt.coupon_input_one,
			opt.package_yn,
			item.goods_seq,
			item.goods_kind,
			item.socialcp_input_type,
			item.socialcp_use_return,
			item.socialcp_use_emoney_day,
			item.socialcp_use_emoney_percent,
			item.item_seq,
			item.event_seq,
			item.shipping_seq,
			item.goods_shipping_cost,
			item.provider_seq,
			item.adult_goods,
			item.option_international_shipping_status,
			item.tax,
			(select cancel_type from fm_goods where goods_seq = item.goods_seq) as cancel_type,
			ref.return_item_seq,
			ref.reason_code,
			ref.reason_desc,
			ref.return_ea,
			ref.ea,
			ref.return_badea,
			ref.export_code,
			ref.stock_return_ea,
			ref.package_stock_return_ea,
			ref.give_reserve_ea,
			ref.npay_product_order_id,
			ref.scm_supply_price,
			ref.return_badea,
			ref.package_return_badea,
			ref.location_position,
			ref.location_code
			FROM
			fm_order_return_item ref,fm_order_item_option opt,fm_order_item item
			WHERE
			ref.option_seq is not null
			AND ref.option_seq = opt.item_option_seq
			AND opt.item_seq = item.item_seq
			AND ref.return_code = ?
			";
			$query2 = "
			SELECT
			'sub' opt_type,
			sub.item_suboption_seq option_seq,
			sub.supply_price,
			sub.consumer_price,
			sub.price,
			sub.goods_code,
			0 goods_shipping_cost,
			'' download_seq,
			0 coupon_sale,
			0 member_sale,
			0 fblike_sale,
			0 mobile_sale,
			0 promotion_code_sale,
			0 referer_sale,
			sub.reserve as reserve,
			sub.point as point,
			item.goods_name,
			item.goods_type,
			item.image,
			sub.title title1,
			'' title2,
			'' title3,
			'' title4,
			'' title5,
			sub.suboption option1,
			'' option2,
			'' option3,
			'' option4,
			'' option5,
			sub.newtype,
			sub.color,
			sub.zipcode,
			sub.address,
			sub.addressdetail,
			sub.biztel,
			'' address_commission,
			sub.codedate,
			sub.sdayinput,
			sub.fdayinput,
			sub.dayauto_type,
			sub.sdayauto,
			sub.fdayauto,
			sub.dayauto_day,
			sub.social_start_date,
			sub.social_end_date,
			sub.coupon_input,
			sub.coupon_input_one,
			sub.package_yn,
			item.goods_seq,
			item.goods_kind,
			item.socialcp_input_type,
			item.socialcp_use_return,
			item.socialcp_use_emoney_day,
			item.socialcp_use_emoney_percent,
			item.item_seq,
			item.event_seq,
			item.shipping_seq,
			item.goods_shipping_cost,
			item.provider_seq,
			item.adult_goods,
			item.option_international_shipping_status,
			item.tax,
			(select cancel_type from fm_goods where goods_seq = item.goods_seq) as cancel_type,
			ref.return_item_seq,
			ref.reason_code,
			ref.reason_desc,
			ref.return_ea,
			ref.ea,
			ref.return_badea,
			ref.export_code,
			ref.stock_return_ea,
			ref.package_stock_return_ea,
			ref.give_reserve_ea,
			ref.npay_product_order_id,
			ref.scm_supply_price,
			ref.return_badea,
			ref.package_return_badea,
			ref.location_position,
			ref.location_code
			FROM
			fm_order_return_item ref,fm_order_item_suboption sub,fm_order_item item
			WHERE
			ref.suboption_seq is not null
			AND ref.suboption_seq = sub.item_suboption_seq
			AND sub.item_seq = item.item_seq
			AND ref.return_code = ?
			";
		}

		$query = "(".$query1.") union (".$query2.") order by goods_type desc, opt_type asc,item_seq asc";
		$query = $this->db->query($query,array($return_code,$return_code));
		foreach($query->result_array() as $data){
			//주문상품의 이미지가 없는경우 실제상품의 이미지를 가져옴
			if( !(is_file($data['image'])) ) {
				$data['image'] = viewImg($data['goods_seq'],'thumbCart');
			}
			$result[] = $data;
		}
		return $result;
	}

	//반품 체크
	public function check_return($order_seq)
	{
		$query = "select * from fm_order_return where order_seq=? ";
		$query = $this->db->query($query,array($order_seq));
		list($result) = $query -> result_array();
		return $result;
	}

	//반품 상품 체크
	public function check_return_item($return_code)
	{
		$query = "select * from fm_order_return_item where return_code=? ";
		$query = $this->db->query($query,array($return_code));
		foreach($query->result_array() as $data) $result[] = $data;
		return $result;
	}

	//반품 상품 반품코드와 반품신청날짜 체크
	public function get_return_item_return_code($item_seq,$option_seq,$export_code=null)
	{
		$query = "select sum(a.ea) as 'ea', a.return_code from fm_order_return_item a
		where a.item_seq=? and a.option_seq=?";
		$values = array($item_seq,$option_seq);
		if($export_code){
			$query .= " and a.export_code=?";
			$values[] = $export_code;
		}
		$query = $this->db->query($query,$values);
		$result = $query->row_array();
		return $result;
	}

	//반품 상품 수량 체크
	public function get_return_item_ea($item_seq,$option_seq,$export_code=null,$return_type=null)
	{
		$query = "select sum(a.ea) as 'ea' from fm_order_return_item a
		left join fm_order_return b on a.return_code=b.return_code
		where a.item_seq=? and a.option_seq=?";
		$values = array($item_seq,$option_seq);
		if($export_code){
			$query .= " and a.export_code=?";
			$values[] = $export_code;
		}
		if($return_type){
			$query .= " and b.return_type=?";
			$values[] = $return_type;
		}
		$query = $this->db->query($query,$values);

		$result = $query->row_array();
		return $result;
	}

	//반품 서브상품 수량 체크
	public function get_return_subitem_ea($item_seq,$suboption_seq,$export_code=null,$return_type=null)
	{
		$query = "select sum(a.ea) as 'ea' from fm_order_return_item a
		left join fm_order_return b on a.return_code=b.return_code
		where a.item_seq=? and a.suboption_seq=?";
		$values = array($item_seq,$suboption_seq);
		if($export_code){
			$query .= " and a.export_code=?";
			$values[] = $export_code;
		}
		if($return_type){
			$query .= " and b.return_type=?";
			$values[] = $return_type;
		}
		$query = $this->db->query($query,$values);
		foreach($query->result_array() as $data) $result = $data;
		return $result;
	}

	public function get_return_reason($mode){
		if( $mode == 'return_coupon' ) {
			$qry = "select * from fm_return_reason where return_type='coupon' order by idx asc";
			$query = $this->db->query($qry);
			$reasonLoop = $query -> result_array();
		}else{
			$qry = "select * from fm_return_reason where return_type!='coupon' order by idx asc";
			$query = $this->db->query($qry);
			$reasonLoop = $query -> result_array();
		}
		return $reasonLoop;
	}
	
	/**
	 * 네이버페이 반품건의 반품배송비 부담을 반환한다.
	 * @param 네이버페이 반품 사유 코드 $reason_code
	 * @return 반품배송비 부담(buyer||seller)
	 * NPAY_SHIP_DUTY_CODE 대신 interface 에 있는 claim_return_duty 사용하도록 개선
	 */
	public function get_npay_ship_duty($reason_code)
	{
		$this->load->library('naverpaylib');
		$npay_reasons_duty	= $this->naverpaylib->get_npay_code("claim_return_duty");

		$duty = array_key_exists($reason_code, $npay_reasons_duty) ? $npay_reasons_duty[$reason_code] : false;

		return $duty;
	}
	

	// 202106(kjw) : 톡구매, 네이버페이 조회 메소드
	public function get_data_return($params) {
		$query = $this->db->select("*")->from("fm_order_return");
		if($params) {
			$query->where($params);
		}
		return $query->get();
	}

	// 202106(kjw) : 톡구매, 네이버페이 조회 메소드
	public function get_data_return_item($params) {
		$query = $this->db->select("*")->from("fm_order_return_item");
		if($params) {
			$query->where($params);
		}
		return $query->get();
	}

	// 주문번호로 반품번호 조회
	public function getReturnForOrderSeq($order_seq, $return_field='*'){
		$default_field = ['return_code'];
		if (is_array($return_field)) {
			$return_field = array_merge($default_field, $return_field);
		}

		return $this->db->select($return_field)
						->from('fm_order_return')
						->where('order_seq', $order_seq)
						->get()
						->result_array();
	}

	// 반품테이블 업데이트 batch
	function updateReturnData($updateParams, $where) {
		// where절 검증
		if (empty($where)) {
			return false;
		}
		foreach ($updateParams as $param) {
			if (empty($param[$where])) {
				return false;
			}
		}

		return $this->db->update_batch('fm_order_return', $updateParams, $where);
	}
}
