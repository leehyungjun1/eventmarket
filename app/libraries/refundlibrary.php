<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 환불과 관련된 소스들이 컨트롤러와 모델에 산재되어 있어 향후 병합을 위한 라이브러리 구조 
 * 2018-08-06
 * by hed 
 */
class RefundLibrary
{
	public $allow_exit = true;
	
	function __construct() {
		$this->CI =& get_instance();
		$this->CI->load->model('ordermodel');
		$this->CI->arr_step = config_load('step');
		$this->CI->load->helper('order');
		if(!$this->CI->refundmodel) $this->CI->load->model('refundmodel');
	}
	/**
	 * 주문 취소 조회 by hed
	 * @param type $order_seq
	 * @return type
	 */
	function get_order_for_cancel($order_seq){
		$this->CI->load->model('returnmodel');
		$able_steps	= $this->CI->ordermodel->able_step_action['cancel_payment'];
		
		$orders		= $this->CI->ordermodel->get_order($order_seq);
		$items 		= $this->CI->ordermodel->get_item($order_seq);
		$tot		= array();
		$order_total_ea = $this->CI->ordermodel->get_order_total_ea($order_seq);
		
		foreach($items as $key=>$item){
			$options 	= $this->CI->ordermodel->get_option_for_item($item['item_seq']);
			
			if($options) foreach($options as $k=>$option){
				//$this->CI->db->select("sum(ea) as ea");
				$options[$k]['mstep']	= $this->CI->arr_step[$options[$k]['step']];
				# (맞교환)재주문 일때
				if($option['top_item_option_seq']) $item_option_seq = $option['top_item_option_seq'];
				else $item_option_seq = $option['item_option_seq'];
				if($option['top_item_seq']) $item_seq = $option['top_item_seq'];
				else $item_seq = $option['item_seq'];
				
				$rf_ea = $this->CI->refundmodel->get_refund_option_ea($item_seq,$item_option_seq);
				//반품갯수는 환불갯수에서 차감(출고된 건수만큼 반품)@2017-01-23
				$return_item = $this->CI->returnmodel->get_return_item_ea($item_seq,$item_option_seq);
				$rt_ea = $return_item['ea'];
				if($rt_ea>0) $rf_ea = $rf_ea- $rt_ea;
				
				$step_complete = $this->CI->ordermodel->get_option_export_complete($order_seq,$option['shipping_provider_seq'],$item['item_seq'],$option['item_option_seq']);
				$options[$k]['able_refund_ea'] = $option['ea'] - $rf_ea - $step_complete;
				
				$tot['ea'] += $option['ea'];
				$suboptions = $this->CI->ordermodel->get_suboption_for_option($item['item_seq'], $option['item_option_seq'], array("step in ('".implode("','",$able_steps)."')","refund_ea<ea"));
				
				if($suboptions) foreach($suboptions as $k_sub=>$suboption){
					//$this->CI->db->select("sum(ea) as ea");
					$suboptions[$k_sub]['mstep']	= $this->CI->arr_step[$suboptions[$k_sub]['step']];
					
					$rf_ea = $this->CI->refundmodel->get_refund_suboption_ea($item['item_seq'],$suboption['item_suboption_seq']);
					//반품갯수는 환불갯수에서 차감(출고된 건수만큼 반품)@2017-01-23
					$return_item = $this->CI->returnmodel->get_return_subitem_ea($item['item_seq'],$suboption['item_suboption_seq']);
					$rt_ea = $return_item['ea'];
					if($rt_ea>0) $rf_ea = $rf_ea- $rt_ea;
					
					$step_complete = $this->CI->ordermodel->get_suboption_export_complete($order_seq,$option['shipping_provider_seq'],$item['item_seq'],$suboption['item_suboption_seq']);
					$suboptions[$k_sub]['able_refund_ea'] = $suboption['ea'] - $rf_ea - $step_complete;
					
					$tot['ea'] += $suboption['ea'];
				}
				if($suboptions) $options[$k]['suboptions'] = $suboptions;
				
				$options[$k]['inputs']	= $this->CI->ordermodel->get_input_for_option($options[$k]['item_seq'], $options[$k]['item_option_seq']);
			}
			
			$items[$key]['options'] = $options;
		}
		return $items;
	}
	
	// 주문 취소/환불 요청
	public function proc_order_refund($post_params, $send_for_user = true){
		$this->CI->load->model('goodsmodel');
				
		$data_order			= $this->CI->ordermodel->get_order($post_params['order_seq']);
		$minfo				= $this->CI->session->userdata('manager');
		$manager_seq		= $minfo['manager_seq'];
		$data_order_items 	= $this->CI->ordermodel->get_item($post_params['order_seq']);

		// 네이버페이 주문 취소 @2016-01-26 pjm
		$npay_use = npay_useck();	//Npay v2.1 사용여부

		if( !in_array($data_order['step'],array('25','35','40','45','50','60','70')) ){
			openDialogAlert($this->CI->arr_step[$data_order['step']]."에서는 환불신청을 하실 수 없습니다.",400,140,'parent');
			$this->call_exit();
		}

		if($data_order['orign_order_seq']){
			openDialogAlert("교환 주문 건은 결제 취소를 할 수 없습니다.<br />교환 주문 건 취소는 관리자가 교환 주문 건에 대해 출고 및 반품 처리 후 환불 처리 가능합니다.",400,170,'parent');
			$this->call_exit();
		}

		if(!$post_params['chk_seq']){
			openDialogAlert("결제취소/환불 신청할 상품을 선택해주세요.",400,140,'parent');
			$this->call_exit();
		}

		$order_total_ea = $this->CI->ordermodel->get_order_total_ea($post_params['order_seq']);

		$cancel_total_ea = 0;
		foreach($post_params['chk_ea'] as $k=>$v){
			/* 네이버페이 주문취소시 수량 전체 취소(부분취소불가)*/
			if($npay_use && $data_order['pg'] == "npay"){
				if($post_params['input_chk_ea'][$k] > $v) $post_params['chk_ea'][$k] = $v = $post_params['input_chk_ea'][$k];
			}else{
				if(!$v){
					openDialogAlert("결제취소/환불 신청할 수량을 선택해주세요.",400,140,'parent');
					$this->call_exit();
				}
			}
			$cancel_total_ea += $v;
		}

		$result_option		= $this->CI->ordermodel->get_item_option($post_params['order_seq']);
		$result_suboption	= $this->CI->ordermodel->get_item_suboption($post_params['order_seq']);

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
			$this->CI->load->model('naverpaymodel');
			$this->CI->load->library('naverpaylib');
			$npay_reason_arr		= $this->CI->naverpaylib->get_npay_code("claim_cancel");
			$post_params['refund_reason']	= $npay_reason_arr[$post_params['npay_reason_code']];
			if(!$post_params['refund_reason']){
				openDialogAlert("결제취소 사유를 선택해 주세요.",400,140,'parent');
				$this->call_exit();
			}
		}else $npay_use = false;

		/*사은품 주문 취소 start by hyem 2018-11-28*/
		$gift_order = false;
		foreach($data_order_items as $item){
			if($item['goods_type'] == 'gift') {
				list($gift) = $this->CI->ordermodel->get_option_for_item(array('item_seq'=>$item['item_seq']));
				$order_gift_ea += $gift['ea'];
				$gift_item[] = $gift;
				$gift_item_seq[] = $gift['item_seq'];
				$gift_order = true;
			}
		}

		if($gift_order === true) {
			$this->CI->load->model('giftmodel');
			// 취소 가능 수량 : $able_return_total
			// 취소 요청 수량 : $cancel_total_ea
			// 사은품 수량 : $order_gift_ea
			if( $able_return_total == $cancel_total_ea + $order_gift_ea ) {
				// 전체 취소 시 - 사은품도 함께 취소 요청
				$cancel_total_ea += $order_gift_ea;
				foreach($gift_item as $v => $gift) {
					$post_params['chk_seq'][]				= '1';
					$post_params['chk_item_seq'][]		= $gift['item_seq'];
					$post_params['chk_option_seq'][]		= $gift['item_option_seq'];
					$post_params['chk_suboption_seq'][]	= '';
					$post_params['chk_ea'][]				= $gift['ea'];
				}
			} else {
				$gift_cancel = $this->CI->ordermodel->order_gift_partial_cancel($order_seq, $gift_item_seq, $data_order_items );

				// _POST 변수 담아서 실제 사은품 취소 처리
				if(count($gift_cancel) > 0) {
					foreach($gift_cancel as $key => $gift) {
						$post_params['chk_seq'][]				= '1';
						$post_params['chk_item_seq'][]		= $gift['item_seq'];
						$post_params['chk_option_seq'][]		= $gift['item_option_seq'];
						$post_params['chk_suboption_seq'][]	= '';
						$post_params['chk_ea'][]				= $gift['ea'];
					}
				}
			}
		}
		/*사은품 주문 취소 end by hyem 2018-11-28*/

		/* 신용카드 자동취소 */
		if(!$npay_use && $post_params['manual_refund_yn']=='y' && ($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $order_total_ea==$cancel_total_ea)
		{
			$pgCompany = $this->CI->config_system['pgCompany'];

			// 카카오 페이의 PG사를 추출하기 위한 데이터 :: 2015-02-25 lwh
			switch($data_order['pg']){
				case 'kakaopay':
				case 'payco':
					$pglog_tmp				= $this->CI->ordermodel->get_pg_log($data_order['order_seq']);
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
				$this->call_exit();
			}

			$cancelFunction = "{$pgCompany}_cancel";
			$cancelResult = $this->CI->refundmodel->$cancelFunction($data_order,array('refund_reason'=>$post_params['refund_reason'],'cancel_type'=>'full'));

			if(!$cancelResult['success']){
				openDialogAlert("{$pgCompany} 결제 취소 실패<br /><font color=red>{$cancelResult['result_code']} : {$cancelResult['result_msg']}</font>",400,160,'parent','');
				$this->call_exit();
			}
			$post_params['cancel_type']		= 'full';
			$cancel_pg_card				= true;
		}else if($order_total_ea==$cancel_total_ea){
			$post_params['cancel_type'] = 'full';
		}else{
			$post_params['cancel_type'] = 'partial';
		}

		if(!$post_params['bank_name'])		$bank_name		= ""; else $bank_name		= $post_params['bank_name'];
		if(!$post_params['bank_depositor'])	$bank_depositor = ""; else $bank_depositor	= $post_params['bank_depositor'];
		if(!$post_params['bank_account'])		$bank_account	= ""; else $bank_account	= $post_params['bank_account'];

		$data = array(
			'order_seq'			=> $post_params['order_seq'],
			'bank_name'			=> $bank_name,
			'bank_depositor'	=> $bank_depositor,
			'bank_account'		=> $bank_account,
			'refund_reason'		=> $post_params['refund_reason'],
			'refund_type'		=> 'cancel_payment',
			'cancel_type'		=> $post_params['cancel_type'],
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

		$this->CI->db->trans_begin();
		$rollback = false;

		foreach($post_params['chk_seq'] as $k=>$v){

			$items[$k]['item_seq']		= $post_params['chk_item_seq'][$k];
			$items[$k]['option_seq']	= $post_params['chk_suboption_seq'][$k] ? '' : $post_params['chk_option_seq'][$k];
			$items[$k]['suboption_seq']	= $post_params['chk_suboption_seq'][$k];
			$items[$k]['ea']			= $post_params['chk_ea'][$k];
			$items[$k]['npay_product_order_id']	= $post_params['chk_npay_product_order_id'][$k];
			$items[$k]['partner_return']= true;
			$partner_return['items'][$k]= true;

			// npay 주문 취소 송신 @2016-01-26 pjm
			if($npay_use){
				# 추가옵션이 모두 반품된 후 필수옵션반품 가능.
				$kk = count($post_params['chk_npay_product_order_id']) - ($k + 1);
				$npay_product_order_id = $post_params['chk_npay_product_order_id'][$kk];
				$npay_data = array("npay_product_order_id"	=> $npay_product_order_id,
									"order_seq"				=> $post_params['order_seq'],
									"cancel_reason"			=> $post_params['npay_reason_code']
								);
				$npay_res = $this->CI->naverpaymodel->order_cancel($npay_data);
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
					$query = $this->CI->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();

					$rf_ea = $this->CI->refundmodel->get_refund_option_ea($items[$k]['item_seq'],$items[$k]['option_seq']);
					$step_complete = $this->CI->ordermodel->get_option_export_complete($post_params['order_seq'],$items[$k]['shipping_seq'],$items[$k]['item_seq'],$items[$k]['option_seq']);
					$able_refund_ea = $optionData['ea'] - $rf_ea - $step_complete;
					if($able_refund_ea < $items[$k]['ea']){
						$rollback = true;
						break;
					}
					//취소환불가능 갯수 검증 end @2016-12-27

					$this->CI->ordermodel->set_step_ea(85,$items[$k]['ea'],$items[$k]['option_seq'],$mode);

					$query = "select o.*, i.goods_seq, i.shipping_seq from fm_order_item_option o, fm_order_item i  where o.item_seq=i.item_seq and o.item_option_seq=?";
					$query = $this->CI->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();

					//pg 카드결제취소시 상품최종환불액 자동계산 @2016-07-21 ysm
					if( $cancel_pg_card ) {
						$refund_goods_price	= (($optionData['price']-$optionData['member_sale'])*$optionData['ea'])
														-$optionData['fblike_sale']
														-$optionData['mobile_sale']
														-$optionData['referer_sale'];

						# 동일 배송그룹의 최초 배송비(기본배송비 or 개별배송비 가져오기 + 추가배송비)
						// 동일 배송그룹의 최초 배송비를 이미 조회 했다면 동일 배송그룹의 배송비는 입력할 필요 없음.
						if($arr_refund_delivery[$optionData['shipping_seq']]){
							$refund_delivery = 0;
						}else{
							$arr_refund_delivery[$optionData['shipping_seq']] = $this->CI->ordermodel->get_delivery_existing_price($post_params['order_seq'],$optionData['shipping_seq']);
							$refund_delivery = $arr_refund_delivery[$optionData['shipping_seq']];
						}
						
						$items[$k]['refund_goods_price']		= $refund_goods_price;
						$items[$k]['refund_delivery_price']		= $refund_delivery;
					}

					if($optionData['ea']==$optionData['step85']){
						$this->CI->db->set('step','85');
					}

					$this->CI->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->CI->db->where('item_option_seq',$items[$k]['option_seq']);
					$this->CI->db->update('fm_order_item_option');

					// 주문 option 상태 변경
					$this->CI->ordermodel->set_option_step($items[$k]['option_seq'],'option');

					// 출고량 업데이트를 위한 변수정의
					if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $optionData['goods_seq'];
					}

					// 반품으로 인한 원주문 추출 및 교체 :: 2015-08-13 pjm
					$query = $this->CI->db->get_where('fm_order_item_option',
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
					$query = $this->CI->db->query($query,array($items[$k]['suboption_seq']));
					$optionData = $query->row_array();
					$rf_ea = $this->CI->refundmodel->get_refund_suboption_ea($items[$k]['item_seq'],$items[$k]['suboption_seq']);
					$step_complete = $this->CI->ordermodel->get_suboption_export_complete($post_params['order_seq'],$items[$k]['shipping_seq'],$items[$k]['item_seq'],$items[$k]['suboption_seq']);
					$able_refund_ea = $optionData['ea'] - $rf_ea - $step_complete;
					if($able_refund_ea < $items[$k]['ea']){
						$rollback = true;
						break;
					}
					//취소환불가능 갯수 검증 end @2016-12-27

					$this->CI->ordermodel->set_step_ea(85,$items[$k]['ea'],$items[$k]['suboption_seq'],$mode);

					$query = "select o.*, i.goods_seq from fm_order_item_suboption o, fm_order_item i  where o.item_seq=i.item_seq and o.item_suboption_seq=?";
					$query = $this->CI->db->query($query,array($items[$k]['suboption_seq']));
					$optionData = $query->row_array();

					//pg 카드결제취소시 상품최종환불액 자동계산 @2016-07-21 ysm
					if( $cancel_pg_card ) {
						$refund_goods_price		= (($optionData['price']-$optionData['member_sale'])*$optionData['ea']);
						$items[$k]['refund_goods_price'] = $refund_goods_price;
					}

					if($optionData['ea']==$optionData['step85']){
						$this->CI->db->set('step','85');
					}

					$this->CI->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->CI->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
					$this->CI->db->update('fm_order_item_suboption');

					// 주문 option 상태 변경
					$this->CI->ordermodel->set_option_step($items[$k]['suboption_seq'],'suboption');

					// 출고량 업데이트를 위한 변수정의
					if(!in_array($optionData['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $optionData['goods_seq'];
					}

					// 반품으로 인한 원주문 추출 및 교체 :: 2015-08-13 pjm
					$query = $this->CI->db->get_where('fm_order_item_suboption',
						array(
						'item_suboption_seq'=>$items[$k]['suboption_seq'])
					);
					$result = $query->row_array();
					if($result['top_item_suboption_seq']) $items[$k]['suboption_seq'] = $result['top_item_suboption_seq'];

				}

				if($npay_use){
					$tot_refund_price		+= $refund_price;
					$tot_refund_delivery	+= $refund_delivery;
				}
			}

		}

		if ($this->CI->db->trans_status() === FALSE || $rollback == true)
		{
		    $this->CI->db->trans_rollback();
		    openDialogAlert('처리 중 오류가 발생했습니다.',400,140,'parent','');
			$this->call_exit();
		}
		else
		{
		    $this->CI->db->trans_commit();
		}

		//외부몰(npay) 취소처리 실패건수가 있을때
		if($npay_use && $partner_return['fail_cnt']> 0){

			//결제취소 전체 실패시 오류메세지 띄움
			if((count($items) - $partner_return['fail_cnt']) <= 0){
				if(count($partner_return['msg']) < 1) $h = 140; else $h = 150 + (count($partner_return['msg'])*18);
				openDialogAlert("<span class=\'fx12\'>".$partner_return['partner_name']." 결제취소 실패!<br /><span class=\'red\'>".implode("<br />",$partner_return['msg'])."</span></span>",460,$h,'parent');
				$this->call_exit();
			}
		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->CI->goodsmodel->modify_reservation_real($goods_seq);
		}


		#npay 주문 취소일시 취소금액 저장.
		if($npay_use){//담당자에게 확인후 사용하지 않는 소스주석 @2016-07-21 ysm
			//$data['refund_price']		= $tot_refund_price;
			//$data['refund_delivery']	= $tot_refund_delivery;
		}
		$this->CI->ordermodel->set_order_step($post_params['order_seq']);
		$refund_code = $this->CI->refundmodel->insert_refund($data,$items);
		if(!$refund_code || trim($refund_code) == ''){
			openDialogAlert(getAlert('mb178'),400,140,'parent','');
			$this->call_exit();
		}

		/* 신용카드 자동취소 */
		if(!$npay_use && $post_params['manual_refund_yn']=='y' && ($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $order_total_ea==$cancel_total_ea)
		{
			$this->CI->load->model('emoneymodel');
			$this->CI->load->model('membermodel');
			$this->CI->load->model('couponmodel');
			$this->CI->load->model('promotionmodel');
			$this->CI->load->helper('text');

			$refund_emoney	= 0;
			$refund_cash	= 0;

			$data_refund_item 	= $this->CI->refundmodel->get_refund_item($refund_code);
			$data_member		= $this->CI->membermodel->get_member_data($data_order['member_seq']);

			//상품별 할인쿠폰/프로모션코드 복원
			foreach($post_params['chk_seq'] as $k=>$v){
				$items[$k]['item_seq']		= $post_params['chk_item_seq'][$k];
				$items[$k]['option_seq']	= $post_params['chk_suboption_seq'][$k] ? '' : $post_params['chk_option_seq'][$k];
				$items[$k]['suboption_seq']	= $post_params['chk_suboption_seq'][$k];
				$items[$k]['ea']			= $post_params['chk_ea'][$k];

				if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){
					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->CI->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();

					/* 할인쿠폰 복원*/
					if($optionData['download_seq']){
						$optcoupon = $this->CI->couponmodel->restore_used_coupon($optionData['download_seq']);
						if($optcoupon){
							$data_order['coupon_sale'] += $optionData['coupon_sale'];
						}
					}

					/* 프로모션코드 복원 개별코드만 */
					if($optionData['promotion_code_seq']){
						$optpromotioncode = $this->CI->promotionmodel->restore_used_promotion($optionData['promotion_code_seq']);
						if($optpromotioncode){
							$data_order['shipping_promotion_code_sale'] += $optionData['promotion_code_sale'];
						}
					}

				}
			}

			/* 배송비할인쿠폰 복원*/
			$shipping_coupon	= $this->CI->couponmodel->get_shipping_coupon($data_order['order_seq']);
			if($shipping_coupon){
				foreach($shipping_coupon as $row) {
					$shippingcoupon = $this->CI->couponmodel->restore_used_coupon($row['shipping_coupon_down_seq']);
				}
			}
			
			// 주문서쿠폰 복원
			if($data_order['ordersheet_seq']){
				$ordersheetcoupon = $this->CI->couponmodel->restore_used_coupon($data_order['ordersheet_seq']);
			}

			/* 배송비프로모션코드 복원 개별코드만 */
			if($data_order['shipping_promotion_code_seq']){
				$shippingpromotioncode = $this->CI->promotionmodel->restore_used_promotion($data_order['shipping_promotion_code_seq']);
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
						'memo_lang'	=> $this->CI->membermodel->make_json_for_getAlert("mp248",$refund_code),  // [복원]결제취소(%s)에 의한 마일리지 환원
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

					$this->CI->membermodel->emoney_insert($params, $data_order['member_seq']);
					$this->CI->ordermodel->set_emoney_use($data_order['order_seq'],'return');

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
						'memo_lang'	=> $this->CI->membermodel->make_json_for_getAlert("mp249",$refund_code),  // [복원]결제취소(%s)에 의한 예치금 환원
					);
					$this->CI->membermodel->cash_insert($params, $data_order['member_seq']);
					$this->CI->ordermodel->set_cash_use($data_order['order_seq'],'return');

					$refund_cash = $data_order['cash'];
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
				'refund_emoney'			=> $refund_emoney,
				'refund_cash'			=> $refund_cash,
				'status'				=> 'complete',
				'refund_emoney_limit_date' => $reserve_limit_date,
				'refund_date'			=> date('Y-m-d H:i:s')
			);
			$this->CI->db->where('refund_code', $refund_code);
			$this->CI->db->update("fm_order_refund",$saveData);

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
					$items_array[$item['option_seq']]['inputs']		= $this->CI->ordermodel->get_input_for_option($item['item_seq'], $item['option_seq']);
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
				$this->CI->refundmodel->set_provider_refund($refund_code, $provider_data);
			}
			
			if($send_for_user){
				$order_itemArr = array();
				$order_itemArr = array_merge($order_itemArr,$data_order);
				$order_itemArr['order_seq'] = $data_order['order_seq'];
				$order_itemArr['mpayment'] = $data_order['mpayment'];
				$order_itemArr['deposit_date'] = $data_order['deposit_date'];
				$order_itemArr['bank_account'] = $data_order['bank_account'];
				$order_itemArr['pg_transaction_number'] = $data_order['pg_transaction_number'];

				/* 결제취소완료 안내메일 발송 */
				$params = array_merge($saveData,$post_params);
				$params	= array_merge($params,$data_member);
				$params['refund_reason']	= htmlspecialchars($post_params['refund_reason']);
				$params['refund_date']		= $saveData['refund_date'];
				$params['mstatus'] 			= $this->CI->refundmodel->arr_refund_status['complete'];
				$params['refund_price']		= number_format($saveData['refund_price']);
				$params['mrefund_method']	= $this->CI->arr_payment['card'].' '.$this->CI->arr_step[85];
				$params['items'] 			= $items_array;
				$params['order']			= $order_itemArr;
				if( $data_order['order_email'] )
					sendMail($data_order['order_email'], 'cancel', $data_member['userid'], $params);

				/* 결제취소완료 SMS 발송 */
				$params					= array();
				$params['shopName']		= $this->CI->config_basic['shopName'];
				$params['ordno']		= $data_order['order_seq'];
				$params['member_seq']	= $data_order['member_seq'];
				$params['user_name']	= $data_order['order_user_name'];


				//SMS 데이터 생성
				$commonSmsData['cancel']['phone'][] = $data_order['order_cellphone'];
				$commonSmsData['cancel']['params'][] = $params;
				$commonSmsData['cancel']['order_no'][] = $data_order['order_seq'];
				commonSendSMS($commonSmsData);
			}
			

			$logTitle	= $this->CI->arr_step[85]."(".$refund_code.")";
			$logDetail	= "신용카드 전체취소처리하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->CI->ordermodel->set_log($post_params['order_seq'],'process',$this->CI->managerInfo['mname'],$logTitle,$logDetail,$logParams);

			/**
			* 4-2 환불관련 정산개선 시작
			* @
			**/
			$this->CI->load->helper('accountall');
			if(!$this->CI->accountallmodel)$this->CI->load->model('accountallmodel');
			if(!$this->CI->providermodel)$this->CI->load->model('providermodel');
			if(!$this->CI->returnmodel)$this->CI->load->model('returnmodel');
			//정산대상 수량업데이트
			$this->CI->accountallmodel->update_calculate_sales_ac_ea($data_order['order_seq'],$refund_code, 'refund');
			//정산확정 처리
			$this->CI->accountallmodel->insert_calculate_sales_order_refund($data_order['order_seq'], $refund_code, $post_params['cancel_type'], $data_order);//월별매출
			/**
			* 4-2 환불관련 정산개선 시작
			* @
			**/

			$callback = "
			parent.closeDialog('order_refund_layer');
			parent.document.location.reload();
			";
			openDialogAlert("신용카드 ".$this->CI->arr_step[85]."가 완료되었습니다.",400,140,'parent',$callback);
		}else{

			$logTitle	= "환불신청(".$refund_code.")";
			$logDetail	= $this->CI->arr_step[85]."/환불신청하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->CI->ordermodel->set_log($post_params['order_seq'],'process',$this->CI->managerInfo['mname'],$logTitle,$logDetail,$logParams);

			$callback = "
			parent.closeDialog('order_refund_layer');
			parent.document.location.reload();
			";
			openDialogAlert($this->CI->arr_step[85]."/환불 신청이 완료되었습니다.",400,140,'parent',$callback);
		}
		
		return $refund_code;
	}
	
	
	// 주문 취소/환불 요청
	public function proc_refund_save($post_params, $send_for_user = true){
		$this->CI->load->model('ordermodel');
		$this->CI->load->model('returnmodel');
		$this->CI->load->model('emoneymodel');
		$this->CI->load->model('membermodel');
		$this->CI->load->model('eventmodel');
		$this->CI->load->model('connectormodel');
		$this->CI->load->model('salesmodel');
		$this->CI->load->helper('text');
		$this->CI->load->helper('order');

		$cfg_order		= config_load('order');
		$cfg_reserve	= config_load('reserve');		//마일리지/예치금 환경 로드
		$npay_use		= npay_useck();					//npay 사용여부

		// 신청상태 신청상태 유지 제거 - 관리자 수정 상태 파악의도 :: 2019-02-11 lwh
		if($post_params['status'] == 'request'){
			openDialogAlert("환불 처리 상태를 `환불 처리 중` 또는 `환불 완료`로 변경해주세요. ",450,150,'parent');
			$this->call_exit();
		}

		/* 마일리지/예치금 환경 로드 */
		$cfg_reserve	= config_load('reserve');

		/* 예치금 미사용 시 */
		if($cfg_reserve['cash_use']=='N' && ($post_params['refund_cash'] > 0 || $post_params['refund_method'] == "cash")){
			openDialogAlert("예치금 환불이 불가능 합니다.<br />설정=>마일리지/포인트/예치금 설정을 확인해 주세요.",400,140,'parent');
			$this->call_exit();
		}

		//comma 제거 pjm
		foreach($post_params as $k=>$v){
			if(is_array($v)) foreach($v as $kk=>$vv) $post_params[$k][$kk] = str_replace(",","",$vv);
			else $post_params[$k] = str_replace(",","",$v);
		}

		$order_seq = $post_params['order_seq'];

		## (맞교환)재주문 환불
		## 1) 원주문의 총 주문 금액을 가져온다.
		## 2) 원주문과 맞교환 재주문건의 기 환불액을 가져온다.
		$all_order_seq = array($order_seq);

		if($post_params['top_orign_order_seq']){
			$top_orign_order_seq = $all_order_seq[] = $post_params['top_orign_order_seq'];
		}else{
			$top_orign_order_seq = $order_seq;
		}

		## 하위 주문번호 조회(원주문의 맞교환(재주문)건)
		$query = "select order_seq from fm_order where top_orign_order_seq='".$order_seq."'";
		$query = $this->CI->db->query($query);
		foreach($query->result_array() as $sub_order) $all_order_seq[] = $sub_order['order_seq'];

		$all_order_seq = array_unique($all_order_seq);

		$refund_code		= $post_params['refund_code'];

		// 총 결제금액(현금성+마일리지+예치금)
		$data_order			= $this->CI->ordermodel->get_order($top_orign_order_seq);
		$data_refund		= $this->CI->refundmodel->get_refund($refund_code);
		$pay_price			= $data_order['settleprice'] + $data_order['emoney'] + $data_order['cash'];
		$shipping_price		= $data_order['shipping_cost'];
		$data_return		= $this->CI->returnmodel->get_return_refund_code($refund_code);
		// 반품이 완료되지 않은 경우 환불 불가
		if	($data_refund['refund_type'] == 'return' && $post_params['status'] == 'complete' && $data_return['status'] != 'complete'){
			openDialogAlert('반품이 완료되지 않았습니다.<br/>반품을 먼저 완료해 주시기 바랍니다.', 500, 170, 'parent', '');
			$this->call_exit();
		}

		# npay 반품환불/주문취소 환불 승인
		# 처리가능작업 :
		#	- 환불신청 -> 환불완료(O)
		#	- 환불신청 -> 환불처리중(X)
		#	- 환불처리중 -> 환불완료(X)
		#	- 환불처리중 -> 환불신청(X)

		if($npay_use && $data_order['pg'] == "npay"){

			if($post_params['status'] == "request"){
				openDialogAlert("이 환불건은 네이버페이 환불건으로 환불신청으로 되돌리기 불가합니다.",500,160,'parent','');
				$this->call_exit();
			}
			if($post_params['status'] == "ing"){
				openDialogAlert("이 환불건은 네이버페이 환불건으로 환불처리중 처리가 불가합니다.",500,160,'parent','');
				$this->call_exit();
			}
			if($data_refund['status'] == "ing" && $post_params['status'] == "complete"){
				openDialogAlert("이 환불건은 네이버페이 환불건으로 환불완료 처리가 불가합니다.",500,160,'parent','');
				$this->call_exit();
			}
			if($post_params['status']=='complete'){

				if($post_params['refund_type'] == "return"){
					openDialogAlert("이 환불건은 네이버페이 반품건이므로 직접 처리 불가합니다.",500,160,'parent','');
					$this->call_exit();

				}else{

					//npay 취소요청 승인 API
					$this->CI->load->model("naverpaymodel");
					foreach($post_params['refund_npay_product_order_id'] as $npay_product_order_id){
						$npay_data = array("npay_product_order_id"	=> $npay_product_order_id,
											"order_seq"				=> $post_params['order_seq'],
											'refund_code'			=> $refund_code
											);
						$npay_res = $this->CI->naverpaymodel->approve_cancel($npay_data);
					}
					if($npay_res['result'] != "SUCCESS"){
						openDialogAlert("네이버페이 결제 취소요청 승인 실패<br /><font color=red>".$npay_res['message']."</font>",500,160,'parent','');
						$this->call_exit();
					}else{
						$callback = "parent.document.location.reload();";
						openDialogAlert("Npay 결제 취소요청 승인 완료하였습니다.",500,160,'parent',$callback);
						$this->call_exit();
					}

					$post_params['status'] = "request";
				}
				$this->call_exit();

			}
			$refund_price		= '0';
			$refund_delivery	= '0';

		}else{
			$npay_use = false;

			$_refund_goods_price		= array_sum($post_params['refund_goods_price']);
			$_refund_cash_tmp			= array_sum($post_params['refund_cash_tmp']);
			$_refund_emoney_tmp			= array_sum($post_params['refund_emoney_tmp']);
			$_refund_delivery_price_tmp = array_sum($post_params['refund_delivery_price_tmp']);
			$_refund_delivery_cash_tmp	= array_sum($post_params['refund_delivery_cash_tmp']);
			$_refund_delivery_emoney_tmp= array_sum($post_params['refund_delivery_emoney_tmp']);
			$refund_shipping_price		= $post_params['refund_shipping_price'];	// 반품배송비
			$cash_refund_shipping_price = $refund_shipping_price;			// 환급해줄 이머니 계산
			// 3차 환불 개선으로 변수 추가 :: 2018-11- lkh
			$refund_deductible_price	= $post_params['refund_deductible_price'] ? $post_params['refund_deductible_price'] : 0;
			$refund_delivery_deductible_price	= $post_params['refund_delivery_deductible_price'] ? $post_params['refund_delivery_deductible_price'] : 0;
			$refund_penalty_deductible_price	= $post_params['refund_penalty_deductible_price'] ? $post_params['refund_penalty_deductible_price'] : 0;
			$refund_all_deductible_price = $refund_deductible_price + $refund_delivery_deductible_price + $refund_penalty_deductible_price;

			// 최종환불액(상품+배송)
			// 예치금 환불을 위해 예치금도 추가 :: 2018-08-09 pjw
			$refund_price		= $_refund_goods_price + $_refund_cash_tmp + $_refund_emoney_tmp + $_refund_delivery_price_tmp;
			$refund_pg_price	= $_refund_goods_price;
			//환불배송비 합(관리자입력)
			$refund_delivery	= $_refund_delivery_price_tmp + $refund_delivery_cash_tmp + $_refund_delivery_emoney_tmp;
			$refund_pg_delivery = $_refund_delivery_price_tmp;

			// 3차 환불 개선으로 변수 추가 :: 2018-11- lkh
			$refund_price		-= $refund_all_deductible_price;//환불총액 - 공제금액 전체(상품+배송비+환불위약금)
			$refund_price		-= $refund_shipping_price;		//환불총액 - 반품배송비

			# 최종환불액(결제통화기준)
			if($refund_pg_price > 0){
				// 3차 환불 개선으로 변수 추가 :: 2018-11- lkh
				$refund_pg_price_sum = $refund_pg_price + $refund_pg_delivery - $refund_all_deductible_price - $refund_shipping_price;

				//실 환불금액이 0보다 작은 경우에는 이머니 환불금에서 반품배송비 차감
				//아닌 경우에는 이미 실 결제금액에서 반품배송비 차감하여 예치금으로 환급할 배송비가 없음
				if($refund_pg_price_sum < 0) $cash_refund_shipping_price = abs($refund_pg_price_sum);
				else $cash_refund_shipping_price = 0;

				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency'] && $this->CI->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_pg_price_sum = get_currency_exchange($refund_pg_price,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_pg_price_sum = 0;
			}
			
			$data_refund['refund_pg_price'] = $refund_pg_price_sum;

			# 환불배송비 합(결제통화기준)
			if($refund_pg_delivery > 0){
				$refund_pg_delivery_sum = $refund_pg_delivery;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency'] && $this->CI->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_pg_delivery_sum = get_currency_exchange($refund_pg_delivery,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_pg_delivery_sum = 0;
			}

			//동일주문의 기 환불금액 현금성
			$data_refund_item			= $this->CI->refundmodel->get_refund_item($refund_code,$all_order_seq);
			$refund_complete			= 0;
			$refund_complete_delivery	= 0;
			$refund_coupon_deduction_price	= 0;
			$all_refund_option_arr		= array();
			foreach($data_refund_item as $k => $data){
				
				$refund_option_info			= $data_refund['order_seq']."".$data['opt_type']."".$data['option_seq'];
				if(!in_array($refund_option_info,$all_refund_option_arr)){
					$refund_comp = $this->CI->refundmodel->get_refund_complete_price($all_order_seq,$data['option_seq'],$data['opt_type']);
					$refund_complete_price += $refund_comp['refund_goods_price'];
					$refund_complete_delivery += $refund_comp['refund_delivery_price'];
					$refund_coupon_deduction_price += $refund_comp['coupon_deduction_price'];
					$all_refund_option_arr[]	= $refund_option_info;
				}
			}

			//동일주문의 기 환불금액 마일리지, 예치금
			$refund_comp = $this->CI->refundmodel->get_refund_complete_emoney($all_order_seq);
			$refund_complete_price	+= $refund_comp['complete_emoney'];
			$refund_complete_price	+= $refund_comp['complete_cash'];

			
			// 결제 취소 건을 뺀 나머지 반품건 수
			$unrefund_ea = $rest_unrefund_ea['total_unrefund_ea'] - $rest_ea_data['cancel_ea'];

			# 배송비 환불이 불가할 때(반품귀책사유가 고객에게 있거나 출고건이 남아 있을 떄 등)
			# 총 환불 가능 금액에세 주문시 최초 배송비를 제외한다.
			$delivery_beginning_price = 0;
			if(!isset($post_params['refund_delivery_price_tmp'])){
				foreach($post_params['refund_item_for_ship'] as $shipping_seq => $item_seq){
					# 동일 배송그룹의 최초 배송비(기본배송비 or 개별배송비 가져오기 + 추가배송비)
					$delivery_beginning_price	+= $this->CI->ordermodel->get_delivery_existing_price($top_orign_order_seq,$shipping_seq);
				}
			}

			// 환불액이 환불가능금액 보다 클경우 경고창.
			// 기 환불 배송비 추가 :: 2018-08-09 pjw		
			// 환불가능금액
			$refund_remain		= $pay_price - $refund_complete_price - $refund_complete_delivery - $delivery_beginning_price - $refund_coupon_deduction_price;

			if($refund_remain < $refund_price){
				openDialogAlert("환불이 불가 합니다.<br />환불금액(".get_currency_price($refund_price,3).")은 환불가능 금액(".get_currency_price($refund_remain,3).")을 초과하는 금액입니다.",400,190,'parent');
				$this->call_exit();
			}

			//마일리지 환불 유효기간 체크
			if($data_order['member_seq']){
				if($post_params['refund_emoney_limit_type'] == "n"){
					$post_params['refund_emoney_limit_date'] = "";
				}else{
					if($post_params['refund_emoney_limit_date'] < date("Y-m-d",mktime())){
						openDialogAlert("마일리지 환불 유효기간은 오늘 이후(".date("Y-m-d",mktime()).")로 설정하셔야 합니다.",500,140,'parent');
						$this->call_exit();
					}
				}
			}

			//배송비 환불의 경우 예외처리
			$base_refund_info	= $this->CI->refundmodel->get_refund($post_params['refund_code']);
			if($base_refund_info['refund_type'] == 'shipping_price'){

				# 동일 배송그룹의 최초 배송비(기본배송비 or 개별배송비 가져오기 + 추가배송비)
				$delivery_existing_price	= $this->CI->ordermodel->get_delivery_existing_price($post_params['order_seq'],$data_refund_item[0]['shipping_seq']);
	
				foreach((array)$post_params['refund_delivery_price_tmp'] as $item_seq => $item_val){
					$post_params['refund_item_seq'][$item_seq]	= $item_seq;
					$now_refund_delivery					= $refund_complete_delivery + $item_val;
				}

				if($now_refund_delivery > $delivery_existing_price){
					openDialogAlert('배송비환불이 불가 합니다.<br />환불금액('.get_currency_price($now_refund_delivery,3).')은 환불가능 금액('.get_currency_price($delivery_existing_price,3).')을 초과하는 금액입니다.',400,190,'parent');
					$this->call_exit();
				}
			}
		}

		// 배송그룹별 환불금액 저장 :: 2018-06-08 lwh
		foreach($post_params['refund_item_for_ship'] as $ship_seq => $item_seq){
			$refund_delivery_price[$item_seq]		= $post_params['refund_delivery_price_tmp'][$ship_seq];
			$refund_delivery_emoney[$item_seq]		= $post_params['refund_delivery_emoney_tmp'][$ship_seq];
			$refund_delivery_cash[$item_seq]		= $post_params['refund_delivery_cash_tmp'][$ship_seq];
		}

		$post_params['adjust_use_coupon']		= 0;
		$post_params['adjust_use_promotion']	= 0;
		$post_params['adjust_use_emoney']		= 0;
		$post_params['adjust_use_cash']		= 0;
		$post_params['adjust_use_enuri']		= 0;
		$post_params['adjust_refund_price']	= 0;

		/* 환불 정보 저장 */
		$saveData = array(
			'adjust_use_coupon'			=> get_cutting_price($post_params['adjust_use_coupon']),
			'adjust_use_promotion'		=> get_cutting_price($post_params['adjust_use_promotion']),
			'adjust_use_emoney'			=> get_cutting_price($post_params['adjust_use_emoney']),
			'adjust_use_cash'			=> get_cutting_price($post_params['adjust_use_cash']),
			'adjust_use_enuri'			=> get_cutting_price($post_params['adjust_use_enuri']),
			'adjust_refund_price'		=> get_cutting_price($post_params['adjust_refund_price']),
			'refund_method'				=> $post_params['refund_method'],
			'refund_price'				=> get_cutting_price($refund_price),
			'refund_emoney'				=> get_cutting_price($post_params['refund_emoney']),
			'refund_emoney_limit_date'	=> $post_params['refund_emoney_limit_date'],
			'refund_cash'				=> get_cutting_price($post_params['refund_cash']),
			'refund_delivery'			=> get_cutting_price($refund_delivery),
			'refund_pg_price'			=> $refund_pg_price_sum,
			'refund_pg_delivery'		=> $refund_pg_delivery_sum,
			'refund_ordersheet'			=> $post_params['refund_ordersheet'],
			'refund_deductible_price'	=> $refund_deductible_price,
			'refund_delivery_deductible_price'	=> $refund_delivery_deductible_price,
			'refund_penalty_deductible_price'	=> $refund_penalty_deductible_price,
		);

		$this->CI->db->where('refund_code', $refund_code);
		$this->CI->db->update("fm_order_refund",$saveData);

		$data_refund['refund_price']		= $refund_price;
		$data_refund['refund_emoney']		= $post_params['refund_emoney'];
		$data_refund['refund_cash']			= $post_params['refund_cash'];
		$data_refund['refund_ordersheet']	= $post_params['refund_ordersheet'];

		// 3차 환불 개선으로 변수 추가 :: 2018-11- lkh
		$data_refund['refund_deductible_price']				= $refund_deductible_price;
		$data_refund['refund_delivery_deductible_price']	= $refund_delivery_deductible_price;
		$data_refund['refund_penalty_deductible_price']		= $refund_penalty_deductible_price;

		$refund_provider = array();
		foreach($post_params['refund_item_seq'] as $refund_item_seq){

			$refund_goods_price			= str_replace(",","",$post_params['refund_goods_price'][$refund_item_seq]);
			$refund_goods_promotion		= str_replace(",","",$post_params['refund_goods_promotion'][$refund_item_seq]);
			$refund_goods_coupon		= str_replace(",","",$post_params['refund_goods_coupon'][$refund_item_seq]);
			$refund_delivery_coupon		= str_replace(",","",$post_params['refund_delivery_coupon'][$refund_item_seq]);
			$refund_delivery_promotion	= str_replace(",","",$post_params['refund_delivery_promotion'][$refund_item_seq]);

			// 배송관련 추가 필드 저장 :: 2018-06-08 lwh
			$refund_delivery_price		= str_replace(",","",$post_params['refund_delivery_price_tmp'][$refund_item_seq]);
			$refund_delivery_cash		= str_replace(",","",$post_params['refund_delivery_cash_tmp'][$refund_item_seq]);
			$refund_delivery_emoney		= str_replace(",","",$post_params['refund_delivery_emoney_tmp'][$refund_item_seq]);

			// 상품개당 적립금 및 예치금 계산 적용 :: 2018-06-07 lwh
			// 정산의 계산 방식과 동일하게 수정 by hed 2019-06-18 
			$refund_emoney				= str_replace(",","",$post_params['refund_emoney_tmp'][$refund_item_seq]);
			$refund_cash				= str_replace(",","",$post_params['refund_cash_tmp'][$refund_item_seq]);
			$refund_ea					= $post_params['refund_ea'][$refund_item_seq];
			
			// 정산 계산방식과 동일하게 수정하기 위해 임의로 나누기 비율을 설정.
			// 금액 비율로 나눠지는 것이 아닌 개당으로 나눠져야 함.
			if(!$this->CI->accountallmodel)	$this->CI->load->model('accountallmodel');
			$set_ratio_array = array(
				'0' => array(
					'sale_ratio_unit' => 1/$refund_ea * 100
					, 'ea' => $refund_ea
				)
			);
			$set_ratio_array_emoney = $this->CI->accountallmodel->calculate_promotion_unit($refund_emoney, '0', $set_ratio_array, 'emoney');
			$emoney_sale_unit			= $set_ratio_array_emoney[0]['emoney_sale_unit'];
			$emoney_sale_rest			= $set_ratio_array_emoney[0]['emoney_sale_rest'];
			
			$set_ratio_array_cash = $this->CI->accountallmodel->calculate_promotion_unit($refund_cash, '0', $set_ratio_array, 'cash');
			$refund_cash_unit			= $set_ratio_array_cash[0]['cash_sale_unit'];
			$refund_cash_rest			= $set_ratio_array_cash[0]['cash_sale_rest'];

			# 상품 환불금액(결제통화기준)
			if($refund_goods_price > 0){
				$refund_goods_pg_price = $refund_goods_price;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency']  && $this->CI->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_goods_pg_price = get_currency_exchange($refund_goods_price,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_goods_pg_price = 0;
			}
			# 배송비 환불금액(결제통화기준)
			if($refund_delivery_price > 0){
				$refund_delivery_pg_price = $refund_delivery_price;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency']  && $this->CI->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_delivery_pg_price = get_currency_exchange($refund_delivery_price,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_delivery_pg_price = 0;
			}

			$saveData = array(
				'refund_goods_price'		=> get_cutting_price($refund_goods_price),
				'refund_goods_pg_price'		=> $refund_goods_pg_price,
				'refund_goods_coupon'		=> get_cutting_price($refund_goods_coupon),
				'refund_goods_promotion'	=> get_cutting_price($refund_goods_promotion),
				'refund_delivery_price'		=> get_cutting_price($refund_delivery_price),
				'refund_delivery_pg_price'	=> $refund_delivery_pg_price,
				'refund_delivery_cash'		=> $refund_delivery_cash,
				'refund_delivery_emoney'	=> $refund_delivery_emoney,
				'refund_delivery_coupon'	=> get_cutting_price($refund_delivery_coupon),
				'refund_delivery_promotion'	=> get_cutting_price($refund_delivery_promotion),
				'emoney_sale_unit'			=> get_cutting_price($emoney_sale_unit),
				'emoney_sale_rest'			=> get_cutting_price($emoney_sale_rest),
				'cash_sale_unit'			=> get_cutting_price($refund_cash_unit),
				'cash_sale_rest'			=> get_cutting_price($refund_cash_rest),
			);

			$this->CI->db->where('refund_item_seq', $refund_item_seq);
			$this->CI->db->update("fm_order_refund_item",$saveData);

			/* 입점사별 환불 정보 pjm */
			$provider_seq = $post_params['refund_provider_seq'][$refund_item_seq];
			if($provider_seq){
				$refund_provider[$provider_seq]['provider_seq']			= $provider_seq;
				$refund_provider[$provider_seq]['refund_expect_price']	= 0;
				$refund_provider[$provider_seq]['adjust_refund_price']	+= $refund_goods_price+$refund_delivery_price;
				$refund_provider[$provider_seq]['refund_price']			+= $refund_goods_price+$refund_delivery_price;
			}
		}

		/* 입점사별 환불 정보 pjm */
		foreach($refund_provider as $provider_data){
			$this->CI->refundmodel->set_provider_refund($refund_code, $provider_data);
		}


		/* 저장된 정보 로드 */
		$data_refund_item 	= $this->CI->refundmodel->get_refund_item($refund_code);
		$data_order_item	= $this->CI->ordermodel->get_item($data_refund['order_seq']);
		$data_member		= $this->CI->membermodel->get_member_data($data_order['member_seq']);

		$order_total_ea = $this->CI->ordermodel->get_order_total_ea($data_refund['order_seq']);
		if($data_refund_item) foreach($data_refund_item as $item) 	$refund_ea += $item['ea'];

		if(!$npay_use){
			//this->refundmodel->get_refund_item :: 반품건 외 전체 주문아이템 불러옴.
			//복합과세 결제여부 : 전체주문아이템중 비과세 상품 찾기 @2015-06-02 pjm
			$tmp_tax	= array();
			$free_tax	= "n";
			if($data_order_item){
				foreach($data_order_item as $item){
					$tmp_tax[]		= $item['tax'];
					if($item['tax'] == "exempt") $free_tax = "y";
				}
			}

			//kcp 전체 비과세일때 복합과세로 전송되도록 수정
			if( !in_array("tax",$tmp_tax) && $free_tax == "n" ) $free_tax = "y";

			$data_refund['free_tax'] = $free_tax;

			//환북액 과세/비과세 금액 나누기  @2015-06-02 pjm
			$data_refund['tax_price']	= "0";
			$data_refund['free_price']	= "0";
			if($data_refund_item){
				foreach($data_refund_item as $item){
					$refund_seq		= $item['refund_item_seq'];
					$refund_deliv	+= $post_params['refund_delivery_price'][$refund_seq];
					//과세
					if($item['tax'] == "tax"){
						$data_refund['tax_price'] += $post_params['refund_goods_price'][$refund_seq];
					}elseif($item['tax'] == "exempt"){
						$data_refund['free_price'] += $post_params['refund_goods_price'][$refund_seq];
					}
				}
			}

			//과세상품이 한건이라도 있으면 배송비는 과세, 전체 비과세 주문일때만 배송비 비과세.  @2015-06-02 pjm
			if(in_array("tax",$tmp_tax)){
				$data_refund['tax_price'] += $refund_deliv;
			}else{
				$data_refund['free_price'] += $refund_deliv;
			}

			if(!$this->CI->arr_payment)	$this->CI->arr_payment = config_load('payment');
		}
		// 과세 대상 환불 금액을 기준으로 과세금액와 부과세를 계산
		$data_refund['comm_tax_mny']	= 0;		// 과세금액
		$data_refund['comm_vat_mny']	= 0;		// 부과세
		if($data_refund['tax_price']){
			$order_cfg = ($this->CI->cfg_order) ? $this->CI->cfg_order : config_load('order');
			$vat = $order_cfg['vat'] ? $order_cfg['vat'] : 10;
			$sum_price		= $data_refund['tax_price'];
			$tax_price		= round($sum_price / (1 + ($vat / 100)));
			if($sum_price>$tax_price){
				$data_refund['comm_tax_mny']	= $tax_price;
				$data_refund['comm_vat_mny']	= $sum_price - $tax_price;
			}
		}
		// 올앳 & 카카오페이 의 경우 과세,부과세 금액을 주문 내역을 기준으로 계산
		$pgCompany = $this->CI->config_system['pgCompany'];
		if($pgCompany=="allat" || $data_order['pg']=="kakaopay" || $data_order['pg']=="kicc"){
			// 전체 과세금액 추출
			$refund_type = "complete";
			$order_seq = $data_refund['order_seq'];
			# 주문 데이터를 토대로 과세상품액, 비과세액, 과세 배송비금액 구해오기
			$all_order_list		= $this->CI->ordermodel->get_order($order_seq);
			$tax_invoice_type	= ($all_order_list['typereceipt'] == 1) ? true : false;		//세금 계산서 신청여부
			// 환불가능 과세금액 계산
			$order_tax_prices	= $this->CI->ordermodel->get_order_prices_for_tax($order_seq,$all_order_list,$tax_invoice_type,$refund_type);

			$data_tax = $this->CI->salesmodel->tax_calulate(
											$order_tax_prices["tax"],
											$order_tax_prices["exempt"],
											$order_tax_prices["shipping_cost"],
											$order_tax_prices["sale"],
											$order_tax_prices["tax_sale"],'SETTLE');

			$supply			= get_cutting_price($data_tax['supply']);
			$surtax			= get_cutting_price($data_tax['surtax']);
			$taxprice		= get_cutting_price($data_tax['supply']) + get_cutting_price($data_tax['surtax']);
			
			// 남은 환불가능 과세금액과 환불예정 과세금액이 동일할 경우 
			// 전체 과세금액으로부터 과세,부과세를 역산한다.
			if($data_refund['tax_price']==$taxprice && $taxprice > 0){
				// 전체 공급가액 계산
				$tot_tax_prices	= $this->CI->ordermodel->get_order_prices_for_tax($order_seq,$all_order_list,$tax_invoice_type,"all_order");
				$tot_data_tax = $this->CI->salesmodel->tax_calulate(
												$tot_tax_prices["tax"],
												$tot_tax_prices["exempt"],
												$tot_tax_prices["shipping_cost"],
												$tot_tax_prices["sale"],
												$tot_tax_prices["tax_sale"],'SETTLE');
				$tot_supply		= get_cutting_price($tot_data_tax['supply']);
				$tot_surtax		= get_cutting_price($tot_data_tax['surtax']);

				// 기존환불 과세금액 계산
				$re_tax_refund_data_list = $this->CI->refundmodel->get_refund_for_order($data_refund['order_seq']);
				$re_tax_sum_tax_price = 0;
				$re_tax_sum_comm_tax_mny = 0;
				$re_tax_sum_comm_vat_mny = 0;
				$re_tax_sum_free_price = 0;
				foreach($re_tax_refund_data_list as $re_tax_refund_data){
					if($re_tax_refund_data['status']=='complete'){
						$re_tax_sum_tax_price += $re_tax_refund_data['tax_price'];
						$re_tax_sum_comm_tax_mny += $re_tax_refund_data['comm_tax_mny'];
						$re_tax_sum_comm_vat_mny += $re_tax_refund_data['comm_vat_mny'];
						$re_tax_sum_free_price += $re_tax_refund_data['freeprice'];
					}
				}
				// 검산 : 기 환불금액
				if($re_tax_sum_tax_price+$re_tax_sum_free_price != ($post_params['complete_price'])){
					openDialogAlert('기환불금액 오류<br/> 기환불금액('.get_currency_price($post_params['complete_price'],3).')이 과세금액('.get_currency_price($re_tax_sum_tax_price,3).')와 면세금액('.get_currency_price($re_tax_sum_free_price,3).')의 합과 다릅니다.',400,190,'parent');
					$this->call_exit();
				}
				// 검산 : 기환불 과세금액
				if($re_tax_sum_tax_price != ($re_tax_sum_comm_tax_mny+$re_tax_sum_comm_vat_mny)){
					openDialogAlert('기환불금액 오류<br/> 과세금액('.get_currency_price($re_tax_sum_tax_price,3).')이 공급가액('.get_currency_price($re_tax_sum_comm_tax_mny,3).')와 부가세('.get_currency_price($re_tax_sum_comm_vat_mny,3).')의 합과 다릅니다.',400,190,'parent');
					$this->call_exit();
				}
				
				$re_tax_comm_tax_mny = $tot_supply - $re_tax_sum_comm_tax_mny;
				$re_tax_comm_vat_mny = $taxprice - $re_tax_comm_tax_mny;
				
				// 검산 : 환불요청 금액 //  $post_params['refund_price'] 기존 데이터에는 마일리지가 포함되어 있으므로 순수 환불 금액으로 재계산 by hed
				$real_refund_price = array_sum($post_params['refund_goods_price']);
				if($real_refund_price != ($re_tax_comm_tax_mny+$re_tax_comm_vat_mny+$data_refund['free_price'])){
					openDialogAlert('환불요청 금액 오류<br/> 환불요청 금액('.get_currency_price($real_refund_price,3).')이 공급가액('.get_currency_price($re_tax_comm_tax_mny,3).')와 부가세('.get_currency_price($re_tax_comm_vat_mny,3).')와 비과세('.get_currency_price($data_refund['free_price'],3).')의 합과 다릅니다.',400,190,'parent');
					$this->call_exit();
				}
				
				$data_refund['comm_tax_mny'] = $re_tax_comm_tax_mny;
				$data_refund['comm_vat_mny'] = $re_tax_comm_vat_mny;
			}
		}
		
		// 환불 과세 정보 저장
		$taxSaveData = array(
			'tax_price'		=> get_cutting_price($data_refund['tax_price']),
			'comm_tax_mny'		=> get_cutting_price($data_refund['comm_tax_mny']),
			'comm_vat_mny'		=> get_cutting_price($data_refund['comm_vat_mny']),
			'freeprice'	=> get_cutting_price($data_refund['free_price']),
		);

		$this->CI->db->where('refund_code', $refund_code);
		$this->CI->db->update("fm_order_refund",$taxSaveData);	

		// allat 의 경우 인코딩 전에 데이터를 처리해야하므로 과세 비과세 부과세 추출
		if($post_params['get_allat_multi_amt']){
			echo json_encode($data_refund);
			$this->call_exit();
		}
		
		$saveData = array();
		$saveData['status'] = $post_params['status'];

		
		/* 환불신청 또는 환불처리중에서 환불완료로 변경될때 */
		if(!$npay_use && $data_refund['status']!='complete' && $post_params['status']=='complete')
		{
			$this->CI->load->model('couponmodel');
			$this->CI->load->model('promotionmodel');

			$saveData['refund_date'] = date('Y-m-d H:i:s');
			$saveData['manager_seq'] = $this->CI->managerInfo['manager_seq'];

			/* 무통장 환불 처리 */
			if($post_params['refund_method']=='bank' || $post_params['refund_method']=='manual' || $post_params['refund_method']=='cash' || $post_params['refund_method']=='emoney')
			{
				// 별다른 처리 없음
			}
			/* PG 결제취소 처리 */
			// 환불 금액이 0원 이상인 경우에만 PG 환불 진행
			else if ($refund_pg_price_sum > 0)
			{
				if(!$data_order['payment_price'] && $data_order['pg_currency'] == $this->CI->config_system['basic_currency']){
					$data_order['payment_price'] = $data_order['settleprice'];
				}

				if($data_order['payment_price'] < $refund_pg_price_sum){
					openDialogAlert("환불금액이 실결제금액보다 클 수 없습니다.",400,140,'parent');
					$this->call_exit();
				}

				$pgCompany = $this->CI->config_system['pgCompany'];

				// 카카오 페이의 PG사를 추출하기 위한 데이터 :: 2015-02-25 lwh
				switch($data_order['pg']){
					case 'kakaopay':
					case 'payco':
						$pglog_tmp				= $this->CI->ordermodel->get_pg_log($data_order['order_seq']);
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

				$pgCancelType = $data_refund['cancel_type'];

				/* 카드일땐 금액에 따라 전체취소할지 부분취소할지 결정함 */
				if($data_order['settleprice']== $refund_pg_price_sum && $order_total_ea==$refund_ea){
					// 전체금액일땐 전체취소
					$data_refund['cancel_type'] = 'full';
				}else{
					// 부분금액일땐 부분취소
					$data_refund['cancel_type'] = 'partial';
				}
				/* PG 부분취소 */
				if($data_refund['cancel_type']=='partial')
				{
					$cancelMessage = "부분매입취소 실패";
				}
				/* PG 전체취소 */
				else
				{
					if($data_order['settleprice'] != $refund_pg_price_sum ){
						openDialogAlert("PG 전체취소시에는 결제금액과 환불금액이 동일해야합니다.",400,140,'parent');
						$this->call_exit();
					}
					$cancelMessage = "결제 취소 실패";
				}

				$cancelFunction = "{$pgCompany}_cancel";
				$cancelResult	= $this->CI->refundmodel->$cancelFunction($data_order,$data_refund);
				
				if(!$cancelResult['success']){
					openDialogAlert("{$pgCompany} ".$cancelMessage."<br /><font color=red>{$cancelResult['result_code']} : {$cancelResult['result_msg']}</font>",400,160,'parent','');
					$this->call_exit();
				}
			}

			$tot_reserve	= 0;
			$tot_point		= 0;
			// 추가옵션 관련 아이템 재배열
			$items_array	= array();
			if($data_refund_item)foreach($data_refund_item as $item) {

				if( $item['goods_kind'] == 'coupon' ) {//티켓상품 마일리지/포인트, 재고, 할인쿠폰 반환없음
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
					$items_array[$item['option_seq']]['price']			+= $item['price'] * $item['ea'];
					$items_array[$item['option_seq']]['ea']			+= $item['ea'];
					$items_array[$item['option_seq']]['option_ea']	+= $item['option_ea'];
					$items_array[$item['option_seq']]['goods_name']	= $item['goods_name'];
					$items_array[$item['option_seq']]['options']		= $item['options_str'];
					$items_array[$item['option_seq']]['inputs']		= $this->CI->ordermodel->get_input_for_option($item['item_seq'], $item['option_seq']);
					$items_array[$item['option_seq']]['image']		= $item['image'];
				}
				if	(!$first_option_seq)	$first_option_seq	= $item['option_seq'];

				# 지급했던 마일리지, 포인트 금액 가져오기 201-04-06 pjm
				$tot_reserve	+= $item['give_reserve'];
				$tot_point		+= $item['give_point'];

				/* 상품 할인쿠폰 복원 */
					if($item['refund_goods_coupon'] && $post_params['refund_goods_coupon'][$item['refund_item_seq']] ){
					$refund_goods_cp = $this->CI->couponmodel->restore_used_coupon($item['refund_goods_coupon']);
				}
				/* 상품 배송비할인쿠폰 복원 */
					if($item['refund_delivery_coupon'] && $post_params['refund_delivery_coupon'][$item['refund_item_seq']] ){
					$refund_deliv_cp = $this->CI->couponmodel->restore_used_coupon($item['refund_delivery_coupon']);
				}
				/* 상품 상품 프로모션 복원 */
					if($item['refund_goods_promotion'] && $post_params['refund_goods_promotion'][$item['refund_item_seq']] ){
					$refund_goods_pro = $this->CI->promotionmodel->restore_used_promotion($item['refund_goods_promotion']);
				}
				/* 상품 배송비 프로모션 복원 */
					if($item['refund_delivery_promotion'] && $post_params['refund_delivery_promotion'][$item['refund_item_seq']] ){
					$refund_deliv_pro = $this->CI->promotionmodel->restore_used_promotion($item['refund_delivery_promotion']);
				}
			}

			/* 마일리지 지급 */
			if($data_refund['refund_emoney'])
			{
				$params = array(
					'gb'			=> 'plus',
					'type'			=> 'refund',
					'limit_date'	=> $post_params['refund_emoney_limit_date'],
					'emoney'		=> get_cutting_price($data_refund['refund_emoney']),
					'ordno'			=> $data_order['order_seq'],
					'memo'			=> "[환불] 주문환불({$data_refund['refund_code']})에 의한 마일리지으로 환불",
					'memo_lang'		=> $this->CI->membermodel->make_json_for_getAlert("mp264",$data_refund['refund_code']), // [환불] 주문환불(%s)에 의한 마일리지으로 환불
				);
				$this->CI->membermodel->emoney_insert($params, $data_order['member_seq']);
			}

			/* 예치금 지급 */
			if($data_refund['refund_cash'] > 0 || $post_params['refund_method'] == "cash")
			{
				if($post_params['refund_method'] == "cash"){
					$data_refund['refund_cash'] += array_sum($post_params['refund_goods_price']) +$refund_delivery;
				}
				// 반품배송비는 실 환불금액에서 차감 후 남은 금액은 예치금에서 차감
				$data_refund['refund_cash'] -= $cash_refund_shipping_price;

				$params = array(
					'gb'		=> 'plus',
					'type'		=> 'refund',
					'cash'		=> get_cutting_price($data_refund['refund_cash']),
					'ordno'		=> $data_order['order_seq'],
					'memo'		=> "[환불] 주문환불({$data_refund['refund_code']})에 의한 예치금로 환불",
					'memo_lang'	=> $this->CI->membermodel->make_json_for_getAlert("mp265",$data_refund['refund_code']), // [환불] 주문환불(%s)에 의한 예치금로 환불
				);
				$this->CI->membermodel->cash_insert($params, $data_order['member_seq']);
			}

			## 티켓상품 아닐 경우에만
			if( !$refund_goods_coupon_ea ) {
				// 회수할 마일리지, 포인트가 있을때
				{
					/* 마일리지 회수 */
					if($tot_reserve && $data_refund['refund_type']=='return'){
						$params = array(
							'gb'		=> 'minus',
							'type'		=> 'refund',
							'emoney'	=> get_cutting_price($tot_reserve),
							'ordno'		=> $data_order['order_seq'],
							'memo'		=> "[차감] 주문환불({$data_order['order_seq']})에 의하여 배송완료시 지급된 마일리지 차감",
							'memo_lang'	=> $this->CI->membermodel->make_json_for_getAlert("mp258",$data_order['order_seq']),  // [차감] 주문환불(%s)에 의하여 배송완료시 지급된 마일리지 차감
						);
						$this->CI->membermodel->emoney_insert($params, $data_order['member_seq']);
					}

					/* 포인트 회수 */
					if($tot_point && $data_refund['refund_type']=='return'){
						$params = array(
							'gb'		=> 'minus',
							'type'		=> 'refund',
							'point'		=> get_cutting_price($tot_point),
							'ordno'		=> $data_order['order_seq'],
							'memo'		=> "[차감] 주문환불({$data_order['order_seq']})에 의하여 배송완료시 지급된 포인트 차감",
							'memo_lang'	=> $this->CI->membermodel->make_json_for_getAlert("mp259",$data_order['order_seq']),  // [차감] 주문환불(%s)에 의하여 배송완료시 지급된 포인트 차감
						);
						$this->CI->membermodel->point_insert($params, $data_order['member_seq']);
					}
				}
			}

			/* 주문서 쿠폰 복원 */
			if($post_params['refund_ordersheet']){
				$refund_ordersheet_cp = $this->CI->couponmodel->restore_used_coupon($post_params['refund_ordersheet']);
			}

			$order_itemArr = array();
			$order_itemArr = array_merge($order_itemArr,$data_order);
			$order_itemArr['order_seq']				= $data_order['order_seq'];
			$order_itemArr['mpayment']				= $data_order['mpayment'];
			$order_itemArr['deposit_date']			= $data_order['deposit_date'];
			$order_itemArr['pg_transaction_number'] = $data_order['pg_transaction_number'];

			if($send_for_user){
				/* 환불처리완료 안내메일 발송 */
				$params = array_merge($saveData,$data_refund);
				$params['refund_reason']		= htmlspecialchars($data_refund['refund_reason']);
				$params['refund_date']			= $saveData['refund_date'];
				$params['mstatus'] 				= $this->CI->refundmodel->arr_refund_status[$post_params['status']];
				$params['refund_price']			= get_currency_price($data_refund['refund_price']);
				$params['refund_emoney']		= get_currency_price($data_refund['refund_emoney']);
				$params['mrefund_method']		= $this->CI->arr_payment[$data_refund['refund_method']];
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

					// 오픈마켓 주문 메일/문자 발송 금지
					$isMarketOrder		= $this->CI->connectormodel->checkIsMarketOrder($data_order['order_seq']);

					if ($isMarketOrder == false) {
						$couponsms		= ( $refund_goods_coupon_ea ) ? "coupon_":"";
						$smsemailtype	= ($data_refund['refund_type']=='return') ? 'refund' : 'cancel';
						sendMail($data_order['order_email'], $couponsms.$smsemailtype, $data_member['userid'], $params);
					}
				}
			}

			// 주문이 환불완료 일경우 주문한 회원의 구매횟수 및 구매금액 업데이트
			if($data_order['member_seq']){
				$refund_price = $data_refund['refund_price'] + $data_refund['refund_emoney'];
				$this->CI->membermodel->member_order($data_order['member_seq']);
				//주문건/주문금액 필드추가 및 실시간업데이트 @2013-06-19
				$this->CI->membermodel->member_order_batch($data_order['member_seq']);
			}

		}

		$this->CI->db->where('refund_code', $post_params['refund_code']);
		$this->CI->db->update("fm_order_refund",$saveData);
		/* 환불신청 또는 환불처리중에서 환불완료로 변경될때 */
		if($data_refund['status']!='complete' && $post_params['status']=='complete')
		{
			$this->CI->load->model('accountmodel');
			$this->CI->accountmodel->set_refund($refund_code,$saveData['refund_date']);

			// 세금계산서 목록 추출
			$sc['whereis']	= ' and typereceipt = 1 and tstep = 1 and order_seq="'.$data_refund['order_seq'].'" ';
			$sc['select']	= ' * ';
			$taxitems 		= $this->CI->salesmodel->get_data($sc);
			if	($taxitems['seq']){ // 세금계산서 금액 재 업데이트
				$remain = $refund_remain - $refund_price;
				if($remain <= 0){ // 전체 환불시 취소로 상태 업데이트
					$params = array('tstep'=>'3', 'price'=>'0', 'supply'=>'0', 'surtax'=>'0');
					$params['seq'] = $taxitems['seq'];
					$this->CI->salesmodel->sales_modify($params);
				}else{
					$this->CI->ordermodel->update_tax_sales($data_refund['order_seq']);
				}
			}

			/* 로그저장 */
			$logTitle = "환불완료(".$refund_code.")";
			$logDetail = "관리자가 환불완료처리를 하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->CI->ordermodel->set_log($data_order['order_seq'],'process',$this->CI->managerInfo['mname'],$logTitle,$logDetail,$logParams);

			//회원일경우 id 불러오기
			if(trim($data_order['member_seq'])){
				$userid		= $this->CI->membermodel->get_member_userid(trim($data_order['member_seq']));
			}
			
			if($send_for_user){
				$params = array();
				$params['shopName']		= $this->CI->config_basic['shopName'];
				$params['ordno']		= $data_order['order_seq'];
				$params['user_name']	= $data_order['order_user_name'];
				$params['member_seq']	= $data_order['member_seq'];
				if( $data_order['order_cellphone'] ) {
					// 오픈마켓 주문 메일/문자 발송 금지
					$isMarketOrder		= $this->CI->connectormodel->checkIsMarketOrder($data_order['order_seq']);

					if ($isMarketOrder == false) {
					if($refund_goods_coupon_ea){
						$this->CI->load->model('returnmodel');
						$data_return = $this->CI->returnmodel->get_return_refund_code($refund_code);
						$data_return_item 	= $this->CI->returnmodel->get_return_item($data_return['return_code']);
						if($data_refund['refund_type']=='return') {
							coupon_send_sms_refund($data_return_item[0]['export_code'],$data_order);
						}else{
							coupon_send_sms_cancel($data_return_item[0]['export_code'],$data_order);
						}
					}else{
						$smsemailtype = ($data_refund['refund_type']=='return') ? 'refund' : 'cancel';
						//SMS 데이터 생성
						$commonSmsData[$smsemailtype]['phone'][] = $data_order['order_cellphone'];
						$commonSmsData[$smsemailtype]['params'][] = $params;
						$commonSmsData[$smsemailtype]['order_no'][] = $data_order['order_seq'];
						if(count($commonSmsData) > 0){
							commonSendSMS($commonSmsData);
						}
						//sendSMS($data_order['order_cellphone'], $smsemailtype, '', $params);
					}
					}
				}
			}

			//이벤트 판매건/주문건/주문금액 @2013-11-15
			if($data_refund_item){
				foreach($data_refund_item as $item) {
					if( $item['event_seq'] ) {
						$this->CI->eventmodel->event_order($item['event_seq']);
						$this->CI->eventmodel->event_order_batch($item['event_seq']);
					}
				}
			}

			/**
			* 4-2 환불관련 정산개선 시작
			* step1->step2 순차로 진행되어야 합니다.
			* @
			**/
			$this->CI->load->helper('accountall');
			if(!$this->CI->accountallmodel)	$this->CI->load->model('accountallmodel');
			if(!$this->CI->providermodel)	$this->CI->load->model('providermodel');
			if(!$this->CI->returnmodel)		$this->CI->load->model('returnmodel');

			//step1 주문금액별 정의/비율/단가계산 후 정렬 => step2 적립금/이머니 update
			/* 저장된 정보 로드 $data_order, $data_refund, $data_refund_item */
			/*
			if( $data_refund['refund_emoney'] || $data_refund['refund_cash'] ) {
			  	$this->CI->accountallmodel->update_ratio_emoney_cash_refund($data_order['order_seq'], $refund_code, $data_order, $data_refund, $data_refund_item);
			  }
			*/
			//step2 통합정산 생성(미정산매출 환불건수 업데이트)

			// 3차 환불 개선으로 티켓상품 처리 추가 :: 2018-11- lkh
			if($refund_goods_coupon_ea){
				$this->CI->accountallmodel->update_calculate_sales_coupon_remain($data_order['order_seq']);
				$this->CI->accountallmodel->update_calculate_sales_coupon_ac_ea($data_order['order_seq'],$data_return['return_code'], 'return', $data_return_item, $data_order, $data_return);
			}else{
				//정산대상 수량업데이트
				$this->CI->accountallmodel->update_calculate_sales_ac_ea($data_order['order_seq'],$refund_code, 'refund', $data_refund_item, $data_order, $data_return);
			}
			//정산확정 처리 insert_calculate_sales_order_refund에서 선처리하도록 수정했으므로 해당 프로세스 제거
			// $this->CI->accountallmodel->update_calculate_refund_sales_buyconfirm($data_order['order_seq'], $refund_code, $data_order);
			/* 저장된 정보 로드 $data_order, $data_refund, $data_refund_item */
			$this->CI->accountallmodel->insert_calculate_sales_order_refund($data_order['order_seq'],$refund_code, $data_refund['cancel_type'], $data_order, $data_refund, $data_refund_item);
			/* 저장된 정보 로드 $data_order, $data_refund, $data_refund_item */
			// 3차 환불 개선으로 함수 처리 추가 :: 2018-11- lkh
			$this->CI->accountallmodel->insert_calculate_sales_order_deductible($data_order['order_seq'],$refund_code, $data_refund['cancel_type'], $data_order, $data_refund, $data_refund_item);
			//debug($this->CI->db->queries);
			//debug_var($this->CI->db->query_times);
			if($data_return && $data_return['refund_ship_duty'] == "buyer" && in_array($data_return['refund_ship_type'],array("M","A","D")) && $data_return['return_shipping_gubun'] == 'company' && $data_return['return_shipping_price']) {
				//step2 통합정산 생성(미정산매출 환불건수 업데이트)
				$this->CI->accountallmodel->update_calculate_sales_order_returnshipping($data_return['order_seq'],$data_return['return_code'],$saveData['refund_date']);
				//debug_var($this->CI->db->queries);
				//debug_var($this->CI->db->query_times);
			}
			/**
			* 4-2 환불관련 정산개선 끝
			* step1->step2 순차로 진행되어야 합니다.
			* @
			**/
			
			// [판매지수 EP] 주문완료 후 통계테이블에 ep 정보 저장 :: 2018-09-14 pjw
			if(!$this->CI->statsmodel) $this->CI->load->model('statsmodel');
			$this->CI->statsmodel->set_refund_sale_ep($post_params['refund_code']);

			$callback = "parent.document.location.reload();";
			openDialogAlert("환불처리가 완료되었습니다.",400,140,'parent',$callback);
		}else{
			$callback = "parent.document.location.reload();";
			openDialogAlert("환불정보가 저장되었습니다.",400,140,'parent',$callback);
		}
	}
	/**
	 * 환불 신청 정보 조회 by hed
	 * @param type $refund_code
	 * @return type
	 */
	function get_refund($refund_code){
		$this->CI->load->helper('text');
		$this->CI->load->model('ordermodel');
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('membermodel');
		$this->CI->load->model('managermodel');
		$this->CI->load->model('couponmodel');
		$this->CI->load->model('promotionmodel');
		$this->CI->load->model('giftmodel');


		// 정산 반영 데이터 확인 :: 2018-05-25 lwh
		$data_refund 		= $this->CI->refundmodel->get_refund($refund_code);
		
		//환불코드로 등록된 데이터가 없을 경우 이전페이지로 이동 pjw
		if( is_null($data_refund) ) {
			pageBack("존재하지 않는 데이터 입니다.", 'self', $this->allow_exit);
			$this->call_exit();
		}
		
		$data_order		= $this->CI->ordermodel->get_order($data_refund['order_seq']);


		$cfg_order = config_load('order');

		$reserves = ($this->CI->reserves)?$this->CI->reserves:config_load('reserve');
		if(!$reserves['cash_use']) $reserves['cash_use'] = "N";
		$this->CI->template->assign($reserves);

		if(!$this->CI->arr_payment)	$this->CI->arr_payment = config_load('payment');

		//배송비 환불의 경우 처리
		$shipping_price_return	= false;
		if($data_refund['refund_type'] == 'shipping_price'){
			$shipping_price_return	= true;
			$refund_shipping_info	= $this->CI->refundmodel->get_provider_refund($refund_code);

			$refund_shipping_list	= array();
			foreach((array)$refund_shipping_info as $row){
				$refund_shipping_list[$row['refund_provider_seq']]	= $row;
				if($row['refund_provider_seq'] > 1 || $data_refund['refund_provider_seq'] == ''){
					$data_refund['refund_provider_seq']		= $row['refund_provider_seq'];
					$data_refund['refund_provider_name']	= $row['provider_name'];
				}
			}
		}

		$data_order_item	= $this->CI->ordermodel->get_item($data_refund['order_seq']);
		$order_seq			= $data_refund['order_seq'];

		$npay_use = npay_useck();
		//npay 사용여부 확인, 취소사유 코드 불러오기
		if($npay_use && $data_order['pg'] == "npay"){

			$this->CI->load->library('naverpaylib');
			$npay_return_hold	= $this->CI->naverpaylib->get_npay_code("cancel_hold");
			if($npay_return_hold[strtoupper($data_refund['npay_flag'])]){
				$data_refund['npay_flag_msg'] = $npay_return_hold[strtoupper($data_refund['npay_flag'])];
			}else{
				$data_refund['npay_flag_msg'] = '';
			}

		}

		## 하위 주문번호 조회(원주문의 맞교환(재주문)건)
		$all_order_seq = array($data_order['order_seq']);

		if($data_order['top_orign_order_seq']){

			$query = "select order_seq from fm_order where top_orign_order_seq='".$data_order['top_orign_order_seq']."'";
			$query = $this->CI->db->query($query);
			foreach($query->result_array() as $sub_order) $all_order_seq[] = $sub_order['order_seq'];

			$query						= "select * from fm_order where order_seq=?";
			$query						= $this->CI->db->query($query,$data_order['top_orign_order_seq']);
			$ori_order					= $query->row_array();
			$orign_order_seq			= $all_order_seq[] = $data_order['top_orign_order_seq'];
			$data_order['settleprice']	= $ori_order['settleprice'];
			$data_order['cash']			= $ori_order['cash'];
			$data_order['emoney']		= $ori_order['emoney'];
			$data_order['enuri']		= $ori_order['enuri'];
			$data_order['shipping_cost']= $ori_order['shipping_cost'];
			$new_order_seq				= $data_order['order_seq'];
		}else{
			$orign_order_seq			= $data_order['order_seq'];
			$new_order_seq				= '';
		}
		$all_order_seq = array_unique($all_order_seq);

		$data_refund_item	= $this->CI->refundmodel->get_refund_item($refund_code,$orign_order_seq,$new_order_seq);
		$process_log 		= $this->CI->ordermodel->get_log($data_refund['order_seq'],'process',array('refund_code'=>$refund_code));
		$data_member		= $this->CI->membermodel->get_member_data($data_refund['member_seq']);

		# 처리자 @2015/07/30 pjm
		$manager			= $this->CI->managermodel->get_manager($data_refund['manager_seq']);
		$data_refund['manager_name'] = $manager['mname'];

		// 원주문의 배송정보
		$data_shipping		= $this->CI->ordermodel->get_order_shipping($orign_order_seq);
		if	($data_shipping)foreach($data_shipping as $k => $ship){

			//복원된 배송비쿠폰 여부 shipping_coupon_sale
			if($ship['shipping_coupon_down_seq']){
				$ship['restore_used_coupon_refund'] = $this->CI->couponmodel->restore_used_coupon_refund($ship['shipping_coupon_down_seq']);
			}
			//복원된 배송비프로모션코드 여부
			if($ship['shipping_promotion_code_seq']){
				//발급받은 프로모션 타입(일반, 개별) - 일반(공용) 코드는 복원 불가(계속 사용가능)
				if($ship['shipping_promotion_code_sale'] > 0){
					$shipping_promotion = $this->CI->promotionmodel->get_download_promotion($ship['shipping_promotion_code_seq']);
					$ship['shipping_promotion_type'] = $shipping_promotion['type'];
				}
				$ship['restore_used_promotioncode_refund'] = $this->CI->promotionmodel->restore_used_promotioncode_refund($ship['shipping_promotion_code_seq']);
			}

			$ship['international']			= $data_order['international'];
			$ships[$ship['shipping_seq']]	= $ship;
		}

		// 개인정보 조회 로그
		//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderexcel', 'exportexcel'
		$this->CI->load->model('logPersonalInformation');
		$this->CI->logPersonalInformation->insert('refund',$this->CI->managerInfo['manager_seq'],$data_refund['refund_seq']);


		/* 반품에 의한 환불일경우 주문시 지급 마일리지합계 표시 */
		if($data_refund['refund_type']=='return'/* && !$cfg_order['buy_confirm_use']*/)
		{
			$optquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_option where order_seq=?";
			$optquery = $this->CI->db->query($optquery,$data_refund['order_seq']);
			$optres = $optquery->row_array();

			$suboptquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_suboption where order_seq=?";
			$suboptquery = $this->CI->db->query($suboptquery,$data_refund['order_seq']);
			$suboptres = $suboptquery->row_array();

			$tot['reserve_sum'] = $optres['reserve_sum']+$suboptres['reserve_sum'];
			$tot['point_sum'] = $optres['point_sum']+$suboptres['point_sum'];
		}

		// 반품정보 추출 :: 2018-05-28 lwh
		$query	= "SELECT * FROM fm_order_return WHERE refund_code=?";
		$query	= $this->CI->db->query($query,$data_refund['refund_code']);
		$res	= $query->row_array();
		$data_refund['returns_status'] = $this->CI->returnmodel->arr_return_status[$res['status']];
		
		// 반품 배송비 추가 정보 추출 :: 2018-05-28 lwh
		$data_refund['refund_ship_duty']		= $res['refund_ship_duty'];
		$data_refund['refund_ship_type']		= $res['refund_ship_type'];
		$data_refund['return_shipping_price']	= $res['return_shipping_price'];
		
		if(!$data_refund['refund_ship_duty']){
			$data_refund['refund_ship_duty'] = "seller";
		}

		$data_refund['mstatus']			= $this->CI->refundmodel->arr_refund_status[$data_refund['status']];
		$data_refund['mrefund_type']	= $this->CI->refundmodel->arr_refund_type[$data_refund['refund_type']];
		$data_refund['mcancel_type']	= $this->CI->refundmodel->arr_cancel_type[$data_refund['cancel_type']];
		$data_order['mpayment']			= $this->CI->arr_payment[$data_order['payment']];

		// 기본 마일리지 유효기간 계산
		if(!$data_refund['refund_emoney_limit_date']){
			$reserve_str_ts			= '';
			$reserve_limit_date		= '';
			$cfg_reserves			= config_load('reserve');
			if( $cfg_reserves['reserve_select'] == 'direct' ){
				$reserve_str_ts = "+".$cfg_reserves['reserve_direct']." month";
				$reserve_limit_date = date('Y-m-d',strtotime($reserve_str_ts));
			}
			if( $cfg_reserves['reserve_select'] == 'year' ){
				$reserve_str_ts = "+".$cfg_reserves['reserve_year']." year";
				$reserve_limit_date = date('Y-12-31',strtotime($reserve_str_ts));
			}
			$data_refund['refund_emoney_limit_date'] = $reserve_limit_date;
		}

		if($data_order['international']=='international'){
			$data_order['real_shipping_cost'] = $data_order['international_cost'];
		}else{
			$data_order['real_shipping_cost'] = $data_order['shipping_cost'];
		}

		$goods_exist				= 0;
		$order_goods_cnt			= ($data_refund['refund_ship_type'] == 'M' && $data_refund['refund_ship_duty'] == 'buyer')	? 1 : 0;
		$refund_items				= array();
		$shipping_group_array		= array();
		$refund_ship				= array();
		$return_formula_tmp			= array();	//회수해야할 마일리지/포인트 계산식
		
		//복원된 주문서쿠폰 여부
		if($data_order['ordersheet_seq']){
			$data_order['use_ordersheetcoupon'] = $this->CI->couponmodel->get_download_coupon($data_order['ordersheet_seq']);
			$data_order['restore_used_ordersheetcoupon_refund'] = $this->CI->couponmodel->restore_used_coupon_refund($data_order['ordersheet_seq']);
		}
		
		foreach($data_refund_item as $k => $data){
			// 환불 수량 없을때는 스킵 :: 2018-05-30 lwh
			// 위 작업으로인해 배송비 환불이 작동하지 않아 조건 추가 :: 2018-07-23 pjw
			if($data['ea'] == 0 && $data_refund['refund_type'] != 'shipping_price')	continue;			

			$tot['order_ea'] += $data['option_ea'];		//주문수량
			$tot['ea']		 += $data['ea'];			//환불수량

			## 환불처리 테이블 rowspan 구하기 @2015-07-24 pjm
			if(in_array($data['item_seq'],$arr_rows['item_seq']) && in_array($data['option_seq'],$arr_rows['option_seq'])){
				$refund_rows[$data['option_seq']]++;
				$data['first_rows'] = false;
			}else{
				$refund_rows[$data['option_seq']] = 1;
				$data['first_rows'] = true;
			}
			$arr_rows['item_seq'][] = $data['item_seq'];
			$arr_rows['option_seq'][] = $data['option_seq'];

			## 맞교환 주문건 일때
			if($data['top_item_option_seq']){
				if($data['opt_type'] == "opt"){
					$query = "select * from fm_order_item_option where item_option_seq=?";
				}else{
					$query = "select * from fm_order_item_suboption where item_option_seq=?";
				}
				$query = $this->CI->db->query($query,$data['top_item_option_seq']);
				$ori_option = $query->row_array();

				$orign_item_seq			= $ori_option['item_seq'];
				$data['price']			= $ori_option['price'];
				$data['consumer_price'] = $ori_option['supply_price'];
				$data['supply_price']	= $ori_option['consumer_price'];
				$data['supply_price']	= $ori_option['consumer_price'];

			}else{
				$orign_item_seq		= $data['item_seq'];
			}
			
			//티켓상품
			if ( $data['goods_kind'] == 'coupon' ) {//

				$data_return		= $this->CI->returnmodel->get_return_refund_code($refund_code);
				$data_return_item 	= $this->CI->returnmodel->get_return_item($data_return['return_code']);
				$data_refund_item[$k]['couponinfo'] = get_goods_coupon_view($data_return_item[0]['export_code']);
				$data_refund_item[$k]['coupon_use_return'] = $data_refund_item[$k]['couponinfo']['coupon_use_return'];

				if ( $data['coupon_refund_type'] == 'emoney' ) {//유효기간지나면
					$tot['coupon_valid_over']++;
					$tot['price'] += $data['coupon_refund_emoney'];//마일리지으로 추가
					$data_order['emoney'] += $data['coupon_refund_emoney'];//마일리지으로 추가
				}else{
					$tot['price'] += $data['coupon_remain_price'];
				}

				//총 할인액(주문기준)				
				// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
				// 에누리 추가 :: 2018-07-31 pjw
				$data['total_sale'] = $data['event_sale'] + $data['multi_sale'] + ($data['member_sale']*$data['option_ea'])+$data['coupon_sale']
										+$data['fblike_sale']+$data['mobile_sale']+$data['referer_sale']
										+$data['promotion_code_sale'] + $data_order['enuri'] + $data['unit_ordersheet'];

				if ( !in_array($data['item_seq'],$itemCoupontot) ) {
					$itemCoupontot[] = $data['item_seq'];

					//promotion sale
					// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
					// 에누리 추가 :: 2018-07-31 pjw
					$tot['event_sale']			+= $data['event_sale'];
					$tot['multi_sale']			+= $data['multi_sale'];
					$tot['member_sale']			+= $data['member_sale']*$data['ea'];
					$tot['coupon_sale']			+= $data['coupon_sale'];
					$tot['coupon_sale']			+= $data['unit_ordersheet'];
					$tot['fblike_sale']			+= $data['fblike_sale'];
					$tot['mobile_sale']			+= $data['mobile_sale'];
					$tot['referer_sale']		+= $data['referer_sale'];
					$tot['promotion_code_sale'] += $data['promotion_code_sale'];
					$tot['enuri_sale']			+= $data_order['enuri'];

					if($data_refund['refund_type']=='return'/* && !$cfg_order['buy_confirm_use']*/){
						$tot['return_reserve'] += $data['reserve']*$data['ea'];
						$tot['return_point'] += $data['point']*$data['ea'];
					}
				}

				//티켓상품 기존환불금액을 환불계산식의 결제금액 - 최종환불금액 계산식
				$tot['refund_complete_price']		+= $data['coupon_deduction_price'];
				$tot['refund_complete_total']		+= $data['coupon_deduction_price'];
				
				// 티켓상품은 배송비 행 노출하지 않도록 처리 :: 2018-07-13 lkh
				$data_refund['refund_ship_duty'] = "";

			}else{

				$tot['price']		+= $data['price']*$data['ea'];

				//총 할인액(주문기준)
				// 에누리 추가 :: 2018-07-31 pjw
				$data['total_sale'] = $data['event_sale'] + $data['multi_sale'] + ($data['member_sale']*$data['option_ea'])+$data['coupon_sale']
										+$data['fblike_sale']+$data['mobile_sale']+$data['referer_sale']
										+$data['promotion_code_sale'] + $data_order['enuri'] + $data['unit_ordersheet'];
				// promotion sale
				// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
				// 에누리 추가 :: 2018-07-31 pjw
				$tot['event_sale']			+= $data['event_sale'];
				$tot['multi_sale']			+= $data['multi_sale'];
				$tot['member_sale']			+= $data['member_sale']*$data['ea'];
				$tot['coupon_sale']			+= $data['coupon_sale'];
				$tot['coupon_sale']			+= $data['unit_ordersheet'];
				$tot['fblike_sale']			+= $data['fblike_sale'];
				$tot['mobile_sale']			+= $data['mobile_sale'];
				$tot['referer_sale']		+= $data['referer_sale'];
				$tot['promotion_code_sale']	+= $data['promotion_code_sale'];
				$tot['enuri_sale']			+= $data_order['enuri'];

				//동일주문의 기 환불금액 pjm
				//환불완료금액에 마일리지, 예치금 추가 :: 2018-07-27 pjw
				$refund_complete = $this->CI->refundmodel->get_refund_complete_price($all_order_seq,$data['option_seq'],$data['opt_type'],$data_refund['refund_code']);			
				
				// 현재 데이터에서 마일리지와 예치금을 더해서 실제 환불데이터랑 맞지않음 환불된 데이터로 수정 :: 2018-07-31 pjw
				$data['refund_complete_ea']			=	$refund_complete['complete_ea'];
				$data['refund_complete_price']		=	$refund_complete['complete_price'];
				$data['refund_complete_delivery']	=	$refund_complete['complete_delivery'];

				// 상품별 환불금액 계산 2018-08-31
				$data['refund_price']			= $refund_complete['refund_goods_price']+$refund_complete['refund_emoney']+$refund_complete['refund_cash'] ;

				$tot['refund_complete_price']		+=	$refund_complete['complete_price'];
				$tot['refund_complete_total']		+=	$data['refund_price'];

				// 환불 된 기 배송비 금액 표기 :: 2018-05-29 lwh
				$refund_delivery_price_sum = $data['refund_complete_delivery'];
			}

			//차감할 마일리지, 포인트 pjm
			if($data_refund['refund_type']=='return' && $data['ea'] > 0 /* && !$cfg_order['buy_confirm_use']*/){
				$tot['return_reserve']	+= $data['give_reserve'];
				$tot['return_point']	+= $data['give_point'];
			}

			if( $data['refund_item_seq'] ) {
				$refund_ship[$data['shipping_seq']] = $data['refund_item_seq'];
			}

			if($data['ea'] > 0){

				//복원된 할인쿠폰 여부
				if($data['download_seq']){
					$data['use_coupon'] = $this->CI->couponmodel->get_download_coupon($data['download_seq']);
					$data['restore_used_coupon_refund'] = $this->CI->couponmodel->restore_used_coupon_refund($data['download_seq']);
				}

				//복원된 프로모션코드 여부
				if($data['promotion_code_seq']){
					$data['use_promotion'] = $this->CI->promotionmodel->get_download_promotion($data['promotion_code_seq']);
					$data['restore_used_promotioncode_refund'] = $this->CI->promotionmodel->restore_used_promotioncode_refund($data['promotion_code_seq']);
				}

				//청약철회상품체크
				unset($ctgoods);
				$ctgoods = $this->CI->goodsmodel->get_goods($data['goods_seq']);
				$data['cancel_type'] = $ctgoods['cancel_type'];
				$data_refund_item[$k]['cancel_type'] = $ctgoods['cancel_type'];

				if( $data['opt_type']  == 'opt' && !$data['new_option_seq'] ) {
					$data['inputs'] = $this->CI->ordermodel->get_input_for_option($data['item_seq'], $data['option_seq']);
				}
				
				// sale_price가 없을경우 그냥 price로 넣음 (환불 금액 계산을 위해서 추가) :: 2018-07-19 pjw
				$data['sale_price'] = $data['sale_price'] > 0 ? $data['sale_price'] : 0;

				$refund_items[$data['item_seq']]['items'][]					= $data;
				$refund_items[$data['item_seq']]['refund_ea']				+= $data['ea'];
				$refund_items[$data['item_seq']]['shipping_policy']			= $data['shipping_policy'];
				$refund_items[$data['item_seq']]['goods_shipping_policy']	= $data['shipping_unit']?'limited':'unlimited';
				$refund_items[$data['item_seq']]['unlimit_shipping_price']	= $data['goods_shipping_cost'];	//개별배송비
				$refund_items[$data['item_seq']]['limit_shipping_price']	= $data['basic_shipping_cost'];	//기본배송비
				$refund_items[$data['item_seq']]['limit_shipping_ea']		= $data['shipping_unit'];		//배송수량
				$refund_items[$data['item_seq']]['limit_shipping_subprice'] = $data['add_shipping_cost'];	//추가배송비

				$goods[$data['goods_seq']]++;

				if($data['goods_type'] == "goods") $goods_exist++;

				$data_refund_item[$k]['inputs']	= $data['inputs'];

				## 환불신청 갯수 pjm 2015-03-12
				$total_refund_ea += $data['refund_ea'];

				## 회수해야할 마일리지 및 포인트 계산식 노출 @2015-09-17 pjm
				$return_formula_tmp['reserve'][]	= "".$data['reserve']."*".$data['ea']."";
				$return_formula_tmp['point'][]		= "".$data['point']."*".$data['ea']."";

				$refund_total_rows++;
				$return_shipping = 1;

			}else{
				$return_shipping = 0;
				if($shipping_price_return === true && isset($refund_shipping_list[$data['provider_seq']]) === true){
					$return_shipping	= 1;

					if( $data['refund_item_seq'] ) {
						$refund_ship[$data['shipping_seq']] = $data['refund_item_seq'];
					}
				}
			}

			// 실 환불금액 저장된 내역 없을때 기본값 지정 :: 2018-05-30 lwh
			if ((int)$data['refund_goods_price'] == 0 ){
				$sale_price_tmp = ($data['sale_price'] * $data['ea']);
				$data['refund_goods_price'] = $sale_price_tmp - (($data['cash_sale_unit'] * $data['ea']) + $data['cash_sale_rest']) - (($data['emoney_sale_unit'] * $data['ea']) + $data['emoney_sale_rest']) - $data_order['enuri'];
			}

			// 배송비 환불금액 저장된 내역 없을때 기본값 지정 :: 2018-05-30 lwh 
			// 에누리가 빠져있어 추가 처리 :: 2018-07-09 lkh
			// 마일리지, 에누리 둘다 빠져있어 추가 :: 2018-08-01 pjw
			// 배송비 할인 (코드, 쿠폰) 추가 :: 2018-08-24 pjw
			$return_shipping_cost = 0;
			if ((int)$data['refund_delivery_price'] == 0 ){
				$return_shipping_cost = $ships[$data['shipping_seq']]['shipping_cost'] - (($ships[$data['shipping_seq']]['cash_sale_unit'])+($ships[$data['shipping_seq']]['cash_sale_rest'])) - 
				(($ships[$data['shipping_seq']]['emoney_sale_unit'])+($ships[$data['shipping_seq']]['emoney_sale_rest'])) - 
				(($ships[$data['shipping_seq']]['enuri_sale_unit'])+($ships[$data['shipping_seq']]['enuri_sale_rest'])) - 
				$ships[$data['shipping_seq']]['shipping_promotion_code_sale'] - $ships[$data['shipping_seq']]['shipping_coupon_sale'];			
			}else{
				$return_shipping_cost = $data['refund_delivery_price'];
			}
			
			// 배송비 환불 추가 :: 2018-08-06 pjw
			$return_shipping_cost = $return_shipping_cost - get_currency_price($ships[$data['shipping_seq']]['shipping_coupon_sale'],1) - get_currency_price($ships[$data['shipping_seq']]['shipping_promotion_code_sale'],1);
			$return_shipping_cost = $return_shipping_cost > 0 ? $return_shipping_cost : 0;

			// 배송비 마일리지 예치금 표기 :: 2018-06-08 lwh
			$ships[$data['shipping_seq']]['refund_delivery_cash']	= $data['refund_delivery_cash'];
			$ships[$data['shipping_seq']]['refund_delivery_emoney'] = $data['refund_delivery_emoney'];		

			// 반품가능 수량으로 배송비 노출 여부 판단 (반품처리가 안되거나 구매확정이 되지않은경우 판매자 부담이여도 미노출) :: 2018-07-19 pjw
			$deliv_refurn			= 'N';	//배송비 환불여부

			// 배송그룹내 마지막 환불 코드
			$refund_maxcode = $this->CI->refundmodel->shipping_refund_maxcode_duty($data_refund['order_seq']);

			// 배송그룹별 주문수량, 취소수량, 배송수량
			$rest_ea_data = $this->CI->refundmodel->shipping_refund_ea($data['shipping_seq']);

			// 주문번호에 대한 남은 반품개수
			$rest_unrefund_ea = $this->CI->refundmodel->shipping_unrefund_order($data['shipping_seq']);
			
			// 결제 취소 건을 뺀 나머지 반품건 수
			$unrefund_ea = $rest_unrefund_ea['total_unrefund_ea'] - $rest_ea_data['cancel_ea'];

			# 최초 배송비 환불
			#	배송그룹내 출고전(배송안함) 수량이 없고 남은 반품수량이 없고 and 
			#	(
			#		현재 환불코드가 마지막 환불코드 이거나 or
			#		반품귀책사유가 판매자에게 있거나 or
			#		반품이 아닌 주문취소로 인한 환불 일때
			#	)
			if($rest_ea_data != null){
				if($unrefund_ea == 0 && ($data_refund['refund_ship_duty'] == "seller" || ($refund_maxcode == $data_refund['refund_code'] || $data_refund['refund_type'] == 'cancel_payment' ))){
					$deliv_refurn = 'Y';
				}
			}

			// 배송비 환불일 경우 무조건 노출 :: 2018-07-23 pjw
			if($data_refund['refund_type'] == 'shipping_price'){
				$deliv_refurn = 'Y';
			}

			// 배송비 및 반품 배송비 표기 :: 2018-05-29 lwh
			if($refund_shipping_items[$data['shipping_seq']]['shipping_cnt'])		$plus_cnt = 1;
			else if($data_refund['refund_ship_duty'] == 'seller' && $deliv_refurn == 'Y')	$plus_cnt = 2;
			else if($data_refund['refund_ship_duty'] == 'buyer')					$plus_cnt = 1;
			else																	$plus_cnt = 1;

			$order_goods_cnt	= $order_goods_cnt + $plus_cnt;
			$cash_price_total	+= ($data['cash_sale_unit'] * $data['option_ea']) + $data['cash_sale_rest'];
			
			// 총 상품 환불가격
			$data_refund['refund_price_sum']	+=  $data['refund_goods_price'] + $data['refund_delivery_price'];
			
			$refund_shipping_items[$data['shipping_seq']]['items'][]							= $data;
			$refund_shipping_items[$data['shipping_seq']]['shipping']							= $ships[$data['shipping_seq']];
			$refund_shipping_items[$data['shipping_seq']]['return_shipping_cnt']				+= $return_shipping;
			$refund_shipping_items[$data['shipping_seq']]['return_shipping_cost']				=  $return_shipping_cost;			
			$refund_shipping_items[$data['shipping_seq']]['refund_delivery_price_sum']			=  $total_ship_cost;
			$refund_shipping_items[$data['shipping_seq']]['refund_delivery_price_sum_except']	=  $except_ship_cost;
			$refund_shipping_items[$data['shipping_seq']]['return_shipping']					=  $refund_ship;
			$refund_shipping_items[$data['shipping_seq']]['refund_flag']						=  $deliv_refurn;
			$refund_shipping_items[$data['shipping_seq']]['shipping_cnt']++;
		}

		## 회수해야할 마일리지 및 포인트 계산식 노출 @2015-09-17 pjm
		if($return_formula_tmp){
			foreach($return_formula_tmp as $gubun=>$formula){
				foreach($formula as $k=>$data) {
					if($k > 0) $return_formula[$gubun] .= "+";
					$return_formula[$gubun] .= "(".$data.")";
				}
			}
			foreach($return_formula as $gubun=>$formula) $return_formula[$gubun] .= ' = ';
		}

		//동일주문의 기 환불금액(마일리지, 예치금) pjm
		$refund_complete = $this->CI->refundmodel->get_refund_complete_emoney($all_order_seq);
		$tot['refund_complete_emoney']	= $refund_complete['complete_emoney'];
		$tot['refund_complete_cash']	= $refund_complete['complete_cash'];
		// 위에서 더하므로 주석처리 :: 2018-08-29
		//$tot['refund_complete_total']	+=$tot['refund_complete_emoney'];
		//$tot['refund_complete_total']	+=$tot['refund_complete_cash'];

		$tot['goods_cnt']	= array_sum($goods);
		$tmp_shipping_seq	= array_keys($refund_shipping_items);

		// 입점사별 배송비,배송정책
		$provider_order_shipping = $this->CI->ordermodel->get_order_shipping($data_order['order_seq']);
		foreach($provider_order_shipping as $data_order_shipping){
			if(in_array($data_order_shipping['shipping_seq'] ,$tmp_shipping_seq) ){
				$tot['refund_shipping_cost'] += $this->CI->refundmodel->get_refund_shipping_cost(
					$data_order,
					$data_order_item,
					$data_refund,
					$data_refund_item,
					$data_order_shipping
				);

				$tot['shipping_coupon_sale'] += $data_order_shipping['shipping_coupon_sale'];
				$tot['shipping_promotion_code_sale'] += $data_order_shipping['shipping_promotion_code_sale'];
			}
		}

		$pg = config_load($this->CI->config_system['pgCompany']);
		$this->CI->template->assign(array('pg'	=> $pg));

		$data_order['kspay_authty']	= '1010';	// KSPAY - 신용카드
		if		($data_order['payment'] == 'account')
			$data_order['kspay_authty']	= '2010';	// KSPAY - 계좌이체
		elseif	($data_order['payment'] == 'cellphone')
			$data_order['kspay_authty']	= 'M110';	// KSPAY - 휴대폰

		$gift_order = 'y';
		if($goods_exist) $gift_order = 'n';

		//회수(차감) 가능한 마일리지 및 포인트 pjm
		$tot['return_reserve_use']	= false;
		$tot['return_point_use']	= false;
		if($tot['return_reserve']==0 || ($tot['return_reserve'] && $data_member['emoney'] > $tot['return_reserve'])) $tot['return_reserve_use'] = true;
		if($tot['return_point']==0 || ($tot['return_point'] && $data_member['point'] > $tot['return_point'])) $tot['return_point_use'] = true;

		if($data_refund['refund_method']){
			$refund_method = $data_refund['refund_method'];
		}else{
			$refund_method = $data_order['payment'];
		}

		switch($refund_method){
			case "card":			$refund_method_name = "신용카드"; break;
			case "account":			$refund_method_name = "계좌이체"; break;
			case "escrow_account":	$refund_method_name = "계좌이체"; break;
			case "virtual":			$refund_method_name = "가상계좌"; break;
			case "escrow_virtual":	$refund_method_name = "가상계좌"; break;
			case "cellphone":		$refund_method_name = "휴대폰"; break;
			case "bank":			$refund_method_name = "무통장"; break;
			default :				$refund_method_name = "무통장"; break;
		}
		if($data_order['pg'] == "npay"){
			switch($refund_method){
				case "card":			$refund_method_name = "신용카드"; break;
				case "account":			$refund_method_name = "계좌이체"; break;
				case "escrow_account":	$refund_method_name = "계좌이체"; break;
				case "virtual":			$refund_method_name = "가상계좌"; break;
				case "escrow_virtual":	$refund_method_name = "가상계좌"; break;
				case "cellphone":		$refund_method_name = "휴대폰"; break;
				case "bank":			$refund_method_name = "무통장"; break;
				case "point":			$refund_method_name = "Npay포인트"; break;
				default :				$refund_method_name = "무통장"; break;
			}
		}

		# 환불방법에 따라 총 환불액 뿌려주기
		if($refund_method == "cash"){
			$data_refund['refund_cash_sum']		= $data_refund['refund_cash'];
			$data_refund['refund_price_sum']	= 0;
		}else{
			$data_refund['refund_cash_sum']		= $data_refund['refund_cash'];
		}
		$data_refund['refund_total_price']		= $data_refund['refund_price_sum'] + $data_refund['refund_cash'] + $data_refund['refund_emoney'];
		if($npay_use){
			$data_refund['refund_total_price'] -= (int)$data_refund['npay_claim_price'];
		}

		// 반품 환불 배송비 :: 2018-06-01 lwh		
		if($data_refund['refund_ship_duty'] == 'buyer' &&  $data_refund['refund_ship_type'] == 'M'){
			$data_refund['refund_total_price'] -= (int)$data_refund['return_shipping_price'];
		}else{
			$data_refund['return_shipping_price'] = 0;
		}

		# 기본통화 정보
		$basic_amount	= get_exchange_rate($this->CI->config_system['basic_currency']);
		$currency_info	= array();
		$currency_info['basic_currency']	= $this->CI->config_system['basic_currency'];
		$currency_info['basic_amount']		= $basic_amount;
		
		return array(
				'refund_shipping_items'	=>$refund_shipping_items,
				'refund_total_rows'		=>$refund_total_rows,
				'process_log'			=>$process_log,
				'refund_method'			=>$refund_method,
				'refund_method_name'	=>$refund_method_name,
				'data_refund'			=>$data_refund,
				'data_refund_item'		=>$data_refund_item,
				'refund_items'			=>$refund_items,
				'tot'					=>$tot,
				'gift_order'			=>$gift_order,
				'data_order'			=>$data_order,
				'members'				=>$data_member,
				'order_goods_cnt'		=>$order_goods_cnt,
				'npay_use'				=>$npay_use,
				'basic_currency_info'	=>$currency_info,
				'refund_rows'			=>$refund_rows,
				'return_formula'		=>$return_formula,
				'cash_price_total'		=>$cash_price_total
			);
	}
	public function call_exit(){
		if($this->allow_exit){
			exit;
		}
	}

	
	public function get_refund_payment($data_order, $data_refund){

		$_arr_payment = config_load('payment');

		$return = array();
		if($data_order['settleprice'] == 0){
			/*
			전액할인 
			1. 예치금 100% or 예치금 + 마일리지 100%
			2. 마일리지 100%
			3. 쿠폰 or 이벤트 100% 할인
			*/
			if($data_order['cash_use'] == 'use'){
				$return[] = 'onlycash';						// 1. 예치금 100% :: 2019120215231017530
			}else{
				if($data_order['emoney_use'] == 'use'){
					$return[] = 'onlyemoney';				// 2. 마일리지 100% :: 2019120216453817531	
				}else{
					$return[] = 'bank';
					$return[] = 'cash';						// 3. 쿠폰 or 이벤트 100% 할인 :: 2019120216511117532
				}
			}
		}else{

			switch($data_order['payment']){
				case 'card':
				case 'paypal':
				case 'kakaomoney':
					$return[] = 'card_auto';
					$return[] = 'card_manual';
					if(!in_array($data_order['pg'], ["payco", "naverpayment"])){
						$return[] = 'bank';						//취소불가사유시
					}
				break;
				case 'cellphone':								//비현금성 : 휴대폰결제
					$return[] = 'cellphone_auto';
					$return[] = 'cellphone_manual';
					$return[] = 'bank';
				break;
				case 'account':									//현금성 : 실시간계좌이체, 예치금 환불가능
				case 'escrow_account':
					//KICC는 에스크로 부분취소 지원안하므로 무통장환불 활성화
					if($data_order['pg'] == 'kicc' && $data_refund['cancel_type'] != "full" && $data_order['payment'] == "escrow_account"){
					}else{
						$return[] = 'account_auto';
					}
					$return[] = 'account_manual';

					if ($data_order['pg'] !== 'naverpayment') {
						$return[] = 'cash';
					}
				break;
				case 'virtual':									//현금성 : 가상계좌, 페이코 무통장 입금, 예치금 환불가능
				case 'escrow_virtual':
				case 'bank':									//현금성 : 무통장입금, 예치금(payment가 bank로 잡힘)
					if($data_order['pg'] === 'payco'){
						$return[] = 'virtual_auto';		// 페이코 [자동]가상계좌 노출
						$return[] = 'virtual_manual';	// 페이코 [수동]가상계좌 노출
					}else{
						$return[] = 'bank';
						$return[] = 'cash';
					}
				break;
				case 'point':
					if($data_order['pg'] === 'naverpayment'){
						$return[] = 'point_auto';
						$return[] = 'point_manual';
					}
				break;
				default:
					$return[] = $data_order['payment'];
				break;
			}
			
			// @todo 톡구매클레임 연동개발 되면 삭제
			if ($data_order['pg'] === 'talkbuy') {
				$return = [];
				$return[] = 'talkbuy_manual';
			}

			// 예치금 사용 주문 시 예치금 환불 가능
			if($data_order['cash_use'] == 'use'){
				//$return[] = 'cash';
			}
		}

		//$return = array_unique($return);

		/*

		// 'card','bank','account','cellphone','virtual','escrow_virtual','escrow_account','point','paypal','kakaomoney','payco_coupon','pos_pay'
		$refund_payment = array();
			<!--{ ? in_array(data_order.payment,array('card','kakaomoney')) || data_order.pg == 'payco' }-->
				<option value="{data_order.payment}" {?data_refund.refund_method==data_order.payment}selected{/}>[자동] 신용카드 취소</option>
			<!--{ : !in_array(data_order.payment,array('bank','virtual','escrow_virtual','card','partial')) 
					|| (
						data_order.pg == 'kicc'
						&& (
							!(in_array(data_order.payment,array('bank', 'virtual', 'escrow_account', 'escrow_virtual')))
							|| (data_refund.cancel_type == 'full' && in_array(data_order.payment,array('escrow_account')))
						)
					)
			}-->
				<!-- // kicc일때 무통장입금을 신청하는 경우도 제외 -->
				<option value="{data_order.payment}" {?data_refund.refund_method==data_order.payment}selected{/}>{data_order.mpayment} {data_refund.mcancel_type}</option>
			<!--{ / }-->
			<!--{ ? !in_array(data_order.payment,array('bank','virtual','escrow_virtual','account','escrow_account','kakaomoney')) 
					|| data_order.pg == 'payco'
					|| (
						data_order.pg == 'kicc'
						&& (data_refund.cancel_type == 'full' && in_array(data_order.payment,array('escrow_virtual')))
					)
			}-->
				<option value="manual" {?data_refund.refund_method=='manual'}selected{/}>[수동] 신용카드 취소 (PG 관리자에서 직접 취소)</option>
			<!--{ / }-->
			<!--{ ? in_array(data_order.payment,array('account','escrow_account')) }-->
				<option value="manual" {?data_refund.refund_method=='manual'}selected{/}>[수동] 계좌이체 취소 (PG 관리자에서 직접 취소)</option>
			<!--{ / }-->
			<!--{ ? (
					!in_array(data_order.payment,array('account','escrow_account')) && data_order.pg != 'payco' && cash_price_total <= total_price 
					)
					|| (data_order.pg == 'kicc' && data_refund.cancel_type != 'full' &&  in_array(data_order.payment,array('escrow_account', 'escrow_virtual')))
			}-->
				<!-- // kicc의 경우 에크스로 부분취소를 지원하지 않으므로 무통장 환불 활성화 -->
				<option value="bank" {?data_refund.refund_method=='bank'}selected{/}>무통장 환불</option>
			<!--{ / }-->
			<!--{? (data_refund.userid && cash_use != 'N' && (cash_price_total > 0 && data_order.payment!='cellphone' && data_order.payment!='card' || in_array(data_order.payment,array('bank','account','escrow_account','virtual','escrow_virtual')))) && data_order.pg != 'payco' }-->
				<option value="cash" {?data_refund.refund_method=='cash'}selected{/}>예치금(캐쉬) 환불</option>
			<!--{ / }-->

			case "card":			$refund_method_name = "신용카드"; break;
			case "account":			$refund_method_name = "계좌이체"; break;
			case "escrow_account":	$refund_method_name = "계좌이체"; break;
			case "virtual":			$refund_method_name = "가상계좌"; break;
			case "escrow_virtual":	$refund_method_name = "가상계좌"; break;
			case "cellphone":		$refund_method_name = "휴대폰"; break;
			case "bank":			$refund_method_name = "무통장"; break;
			default :				$refund_method_name = "무통장"; break;
		*/

		/*
		환불수단이 '예치금(cash)'인 경우 최대 결제 전체 금액까지 예치금으로 환불 가능.
		단순 예치금 중복 결제인 경우 사용한 예치금 만큼만 예치금으로 환불 가능.
		*/

		$escrow = (strstr($data_order['payment'],"escrow_"))? " 에스크로 " : "";
		//$payment = $_arr_payment[$data_order['pg']];

		$refund_payment_name = array();
		$refund_payment_name["cash"]				= array("paycode" => "cash"					,"name" => "예치금(캐쉬) 환불");
		$refund_payment_name["onlycash"]			= array("paycode" => "cash"					,"name" => "예치금(캐쉬) 환불");
		$refund_payment_name["bank"]				= array("paycode" => 'bank'					,"name" => "무통장 환불");
		$refund_payment_name["card_auto"]			= array("paycode" => $data_order['payment']	,"name" => "[자동] 신용카드 취소");
		$refund_payment_name["card_manual"]			= array("paycode" => "manual"				,"name" => "[수동] 신용카드 취소 (PG 관리자에서 직접 취소)");
		$refund_payment_name["cellphone_auto"]		= array("paycode" => $data_order['payment']	,"name" => "[자동] 핸드폰 취소");
		$refund_payment_name["cellphone_manual"]	= array("paycode" => "manual"				,"name" => "[수동] 핸드폰 취소 (PG 관리자에서 직접 취소)");
		$refund_payment_name["account_auto"]		= array("paycode" => $data_order['payment']	,"name" => "[자동] ".$escrow."계좌이체 취소");
		$refund_payment_name["account_manual"]		= array("paycode" => "manual"				,"name" => "[수동] ".$escrow."계좌이체 취소 (PG 관리자에서 직접 취소)");
		$refund_payment_name["virtual_auto"]		= array("paycode" => $data_order['payment']	,"name" => "[자동] ".$escrow."가상계좌 취소");
		$refund_payment_name["virtual_manual"]		= array("paycode" => "manual"				,"name" => "[수동] ".$escrow."가상계좌 취소 (PG 관리자에서 직접 취소)");
		$refund_payment_name["point_auto"]			= array("paycode" => $data_order['payment']	,"name" => "[자동] 포인트 취소");
		$refund_payment_name["point_manual"]		= array("paycode" => "manual"				,"name" => "[수동] 포인트 취소 (PG 관리자에서 직접 취소)");
		$refund_payment_name["onlyemoney"]			= array("paycode" => "onlyemoney"			,"name" => "마일리지환불");
		$refund_payment_name["kakaomoney"]			= array("paycode" => $data_order['payment']	,"name" => "카카오머니 취소");
		$refund_payment_name["payco_coupon"]		= array("paycode" => $data_order['payment']	,"name" => "페이코 쿠폰결제 취소");
		$refund_payment_name["pos_pay"]				= array("paycode" => $data_order['payment']	,"name" => "Pos 결제 취소");
		$refund_payment_name["talkbuy_manual"]		= array("paycode" => "manual"				,"name" => "[수동] 톡체크아웃 취소 (톡체크아웃 관리자센터에서 직접 취소)");

		$refund_payment = array();
		foreach($return as $val){
			$refund_payment[$val] = $refund_payment_name[$val];
		}

		return $refund_payment;
	}

	public function set_refund_method_name($refund_method,$pg=''){

		switch($refund_method){
			case "card":			$refund_method_name = "신용카드"; break;
			case "account":			$refund_method_name = "계좌이체"; break;
			case "escrow_account":	$refund_method_name = "계좌이체"; break;
			case "virtual":			$refund_method_name = "가상계좌"; break;
			case "escrow_virtual":	$refund_method_name = "가상계좌"; break;
			case "cellphone":		$refund_method_name = "휴대폰"; break;
			case "kakaomoney":		$refund_method_name = "카카오머니"; break;
			case "payco_coupon":	$refund_method_name = "페이코-쿠폰결제"; break;
			case "bank":			$refund_method_name = "무통장"; break;
			case "pos_pay":			$refund_method_name = "POS결제"; break;
			case "point":			$refund_method_name = "포인트"; break;
			default :				$refund_method_name = "무통장"; break;
		}
		if($pg == "npay"){
			switch($refund_method){
				case "card":			$refund_method_name = "신용카드"; break;
				case "account":			$refund_method_name = "계좌이체"; break;
				case "escrow_account":	$refund_method_name = "계좌이체"; break;
				case "virtual":			$refund_method_name = "가상계좌"; break;
				case "escrow_virtual":	$refund_method_name = "가상계좌"; break;
				case "cellphone":		$refund_method_name = "휴대폰"; break;
				case "bank":			$refund_method_name = "무통장"; break;
				case "point":			$refund_method_name = "Npay포인트"; break;
				default :				$refund_method_name = "무통장"; break;
			}
		}

		return $refund_method_name;
	}

	/**
	 * 전액 환불 완료 처리
	 * @param $data[
		'tmp' => $tmp,
		'result_option' => $result_option,
		'result_suboption' => $result_suboption,
		'refund_data' => $refund_data,
	 * ];
	 *  가용재고 업데이트, 각종 쿠폰 및 코드 복원, 마일리지 및 이머니 복원, 환불데이터 완료, 정산반영
	 */
	public function process_refund_complete($data) {
		$order = $data['order'];
		$refund_data = $data['refund_data'];

		$order_seq = $order['order_seq'];

		$items 		= $this->CI->ordermodel->get_item($order_seq);
		foreach ($items as $key => $item) {
			// 출고량 업데이트를 위한 변수정의
			if(!in_array($item['goods_seq'],$r_reservation_goods_seq)){
				$r_reservation_goods_seq[] = $item['goods_seq'];
			}
		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->CI->goodsmodel->modify_reservation_real($goods_seq);
		}

		foreach($data['result_option'] as $option) {
			/* 할인쿠폰 복원*/
			if($option['download_seq']) {
				$optcoupon = $this->CI->couponmodel->restore_used_coupon($option['download_seq']);
				if($optcoupon){
					$order['coupon_sale'] += $option['coupon_sale'];
				}
			}

			/* 프로모션코드 복원 개별코드만 */
			if($option['promotion_code_seq']){
				$optpromotioncode = $this->CI->promotionmodel->restore_used_promotion($option['promotion_code_seq']);
				if($optpromotioncode){
					$order['shipping_promotion_code_sale'] += $option['promotion_code_sale'];
				}
			}
			
		}

		/* 배송비할인쿠폰 복원*/
		$shipping_coupon	= $this->CI->couponmodel->get_shipping_coupon($order['order_seq']);
		if($shipping_coupon){
			foreach($shipping_coupon as $row) {
				$shippingcoupon = $this->CI->couponmodel->restore_used_coupon($row['shipping_coupon_down_seq']);
			}
		}
	
		// 주문서쿠폰 복원
		if($order['ordersheet_seq']){
			$ordersheetcoupon = $this->CI->couponmodel->restore_used_coupon($order['ordersheet_seq']);
		}

		/* 배송비프로모션코드 복원 개별코드만 */
		if($order['shipping_promotion_code_seq']){
			$shippingpromotioncode = $this->CI->promotionmodel->restore_used_promotion($order['shipping_promotion_code_seq']);
		}

		if($order['member_seq']){
			/* 마일리지 지급 */
			if($order['emoney_use']=='use' && $order['emoney'] > 0 )
			{
				$params = array(
					'gb'		=> 'plus',
					'type'		=> 'cancel',
					'emoney'	=> $order['emoney'],
					'ordno'		=> $order['order_seq'],
					'memo'		=> "[복원]결제취소({$refund_data['refund_code']})에 의한 마일리지 환원",
					'memo_lang'	=> $this->CI->membermodel->make_json_for_getAlert("mp248",$refund_data['refund_code']),  // [복원]결제취소(%s)에 의한 마일리지 환원
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

				$this->CI->membermodel->emoney_insert($params, $order['member_seq']);
				$this->CI->ordermodel->set_emoney_use($order['order_seq'],'return');

				$refund_emoney = $order['emoney'];
			}

			/* 예치금 지급 */
			if($order['cash_use']=='use' && $order['cash'] > 0 )
			{
				$params = array(
					'gb'		=> 'plus',
					'type'		=> 'cancel',
					'cash'		=> $order['cash'],
					'ordno'		=> $order['order_seq'],
					'memo'		=> "[복원]결제취소({$refund_data['refund_code']})에 의한 예치금 환원",
					'memo_lang'	=> $this->CI->membermodel->make_json_for_getAlert("mp249",$refund_data['refund_code']),  // [복원]결제취소(%s)에 의한 예치금 환원
				);
				$this->CI->membermodel->cash_insert($params, $order['member_seq']);
				$this->CI->ordermodel->set_cash_use($order['order_seq'],'return');

				$refund_cash = $order['cash'];
			}
		}

		$tot_refund_price = $order['settleprice'] + $refund_emoney + $refund_cash;	//총 환불액(PG결제액 + 마일리지할인액 + 예치금사용액)

		$saveData = array(
			'adjust_use_coupon'		=> $order['coupon_sale'],
			'adjust_use_promotion'		=> $order['shipping_promotion_code_sale'],
			'adjust_use_emoney'		=> $order['emoney'],
			'adjust_use_cash'		=> $order['cash'],
			'adjust_use_enuri'		=> $order['enuri'],
			'refund_method'			=> 'card',
			'refund_price'			=> $tot_refund_price,
			'refund_emoney'			=> $refund_emoney,
			'refund_cash'			=> $refund_cash,
			'refund_delivery'			=> $tmp['refund_delivery'],
			'refund_pg_price'			=> $tmp['refund_pg_price_sum'],
			'refund_pg_delivery'			=> $tmp['refund_pg_delivery_sum'],
			'status'				=> 'complete',
			'refund_emoney_limit_date' => $reserve_limit_date,
			'refund_date'			=> date('Y-m-d H:i:s'),
		);
		$this->CI->db->where('refund_code', $refund_data['refund_code']);
		$this->CI->db->update("fm_order_refund",$saveData);


		$this->CI->arr_step 	= config_load('step');
		$logTitle	= $this->CI->arr_step[85]."(".$refund_data['refund_code'].")";
		$logDetail	= "전체취소 처리하였습니다.";
		$logParams	= array('refund_code' => $refund_data['refund_code']);
		$this->CI->ordermodel->set_log($order['order_seq'],'process','system',$logTitle,$logDetail,$logParams);

		/**
		* 4-2 환불관련 정산개선 시작
		* @
		**/
		$this->CI->load->helper('accountall');
		if(!$this->CI->accountallmodel)$this->CI->load->model('accountallmodel');
		if(!$this->CI->providermodel)$this->CI->load->model('providermodel');
		if(!$this->CI->refundmodel)$this->CI->load->model('refundmodel');
		if(!$this->CI->returnmodel)$this->CI->load->model('returnmodel');
		//정산대상 수량업데이트
		$this->CI->accountallmodel->update_calculate_sales_ac_ea($order['order_seq'],$refund_data['refund_code'], 'refund');
		//정산확정 처리
		$this->CI->accountallmodel->insert_calculate_sales_order_refund($order['order_seq'], $refund_data['refund_code'], 'full', $order);//월별매출
	}
	/**
	 * fm_order_item_(sub)option table update
	 */
	public function order_item_to_refund($option, $mode='option') {
		$table			= "fm_order_item_option";
		$field	= "item_option_seq";

		if($mode == 'suboption'){
			$table			= "fm_order_item_suboption";
			$field	= "item_suboption_seq";
		}

		$this->CI->ordermodel->set_step_ea(85,$option['ea'],$option[$field],$mode);

		$this->CI->db->set('step','85');
		$this->CI->db->set('refund_ea','refund_ea+'.$option['ea'],false);
		$this->CI->db->where($field,$option[$field]);
		$this->CI->db->update($table);

		$this->CI->ordermodel->set_option_step($option[$field],$mode);
	}	

	/**
	 * 전액 환불 시 fm_order_refund , fm_order_refund_item 데이터 insert 
	 * 및 
	 * order 데이터 step 변경
	 */
	public function insert_full_refund($order_seq) {
		// 환불 생성
		$item = [];
		$refund_data = [
			'order_seq'=>$order_seq,
			'refund_type'=>'cancel_payment',
			'cancel_type'=>'full',
			'regist_date' => date("Y-m-d H:i:s"),
		];
		$result_option = $this->CI->ordermodel->get_item_option($order_seq);
		$result_suboption = $this->CI->ordermodel->get_item_suboption($order_seq);

		// 필수옵션
		foreach($result_option as $option) {
			// item 생성
			list($item[], $arr) = $this->CI->refundlibrary->set_refund_item($option, $arr);
			// step 변경
			$this->CI->refundlibrary->order_item_to_refund($option);

			$tmp['refund_delivery']			+= $item['refund_delivery_price'];
			$tmp['refund_pg_price_sum']		+= $item['refund_goods_pg_price'];
			$tmp['refund_pg_delivery_sum']		+= $item['refund_delivery_pg_price'];
		}

		// 추가옵션
		foreach($result_suboption as $option) {
			// item 생성
			list($item[], $arr) = $this->CI->refundlibrary->set_refund_item($option, $arr);
			// step 변경
			$this->CI->refundlibrary->order_item_to_refund($option,"suboption");
		}

		// 주문 상태 변경
		$this->CI->ordermodel->set_order_step($order_seq);

		// refund 생성
		$refund_data['refund_code'] = $this->CI->refundmodel->insert_refund($refund_data,$item);

		$result = [
			'tmp' => $tmp,
			'result_option' => $result_option,
			'result_suboption' => $result_suboption,
			'refund_data' => $refund_data,
		];

		return $result;
	}
	/**
	 * 환불 시 item 생성
	 * @param $option : fm_order_item_option
	 * 		  $arr : 이전 item 에서 생성된 데이터
	 * @return $item : fm_order_refund_item
	 */
	public function set_refund_item($option, $arr) {
		$mode = isset($option['item_suboption_seq']) ? "suboption" : "option";
		
		$refund_emoney_unit = $option['emoney_sale_unit']*$option['ea']+$option['emoney_sale_rest'];
		$refund_cash_unit	= $option['cash_sale_unit']*$option['ea']+$option['cash_sale_rest'];
		$refund_enuri_unit	= $option['enuri_sale_unit']*$option['ea']+$option['enuri_sale_rest'];

		// sale_price가 없을경우 그냥 price로 넣음 (환불 금액 계산을 위해서 추가) :: refund.php view() 와 동일하게 refund_goods_price 계산하도록 추가
		$option['sale_price'] = $option['sale_price'] > 0 ? $option['sale_price'] : $option['price'];
		$sale_price_tmp = $option['sale_price'] * $option['ea'];
		$refund_goods_price = $sale_price_tmp - $refund_emoney_unit - $refund_cash_unit - $refund_enuri_unit;
		$refund_goods_pg_price = $refund_goods_price;

		// option / suboption 공통
		$item = [
			'item_seq' => $option['item_seq'],
			'ea' => $option['ea'],
			'refund_goods_price' => $refund_goods_price,
			'emoney_sale_unit' => $option['emoney_sale_unit'],
			'cash_sale_unit' => $option['cash_sale_unit'],
			'emoney_sale_rest' => $option['emoney_sale_rest'],
			'cash_sale_rest' => $option['cash_sale_rest'],
			'refund_goods_pg_price' => $refund_goods_pg_price
		];

		if($mode === "option") {
			// #### 배송비 계산 시작
			if($arr['refund_delivery'][$option['shipping_seq']]){
				$refund_delivery = 0;
			}else{
				$arr['refund_delivery'][$option['shipping_seq']] =  $this->CI->ordermodel->get_delivery_existing_price($option['order_seq'],$option['shipping_seq']);
				$refund_delivery = $arr['refund_delivery'][$option['shipping_seq']];
				$shippingData = $this->CI->ordermodel->get_seq_for_order_shipping($option['shipping_seq']);
			}

			// 배송비가 있는경우 할인금액을 제외한 배송비를 저장함
			if($refund_delivery > 0){
				$refund_delivery = $refund_delivery
									- $shippingData['emoney_sale_unit'] - $shippingData['emoney_sale_rest']
									- $shippingData['cash_sale_unit'] - $shippingData['cash_sale_rest'];
			}
			$refund_delivery_pg_price = $refund_delivery;
			// #### 배송비 계산 종료

			$item += [
				'option_seq' => $option['item_option_seq'],
				'suboption_seq' => '0',
				'refund_delivery_price' => $refund_delivery,
				'refund_delivery_cash' => $shippingData['cash_sale_unit'] + $shippingData['cash_sale_rest'],
				'refund_delivery_emoney' => $shippingData['emoney_sale_unit'] + $shippingData['emoney_sale_rest'],
				'refund_delivery_pg_price' => $refund_delivery_pg_price,
			];
		} else if($mode === "suboption") {
			$item += [
				'option_seq' => '0',
				'suboption_seq' => $option['item_suboption_seq'],
			];
		}

		return [$item, $arr];
	}
}
?>
