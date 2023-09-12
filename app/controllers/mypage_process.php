<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

class mypage_process extends front_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('snssocial');
		$this->load->library('validation');
		$this->load->model('couponmodel');
		$this->load->model('ordermodel');
		$this->load->model('refundmodel');
		$this->load->model('goodsmodel');
		$this->arr_step = config_load('step');


		//스킨패치 하지 않은 사용자를 위해 우편번호 합치기
		if($_POST['return_recipient_zipcode'][1]){
			$_POST['return_recipient_zipcode'][0] = @implode('-',$_POST['return_recipient_zipcode']);
			unset($_POST['return_recipient_zipcode'][1]);
		}

		if($_POST['recipient_zipcode'][1]){
			$_POST['recipient_zipcode'][0] = @implode('-',$_POST['recipient_zipcode']);
			unset($_POST['recipient_zipcode'][1]);
		}

	}

	//결제취소 -> 환불
	public function order_refund(){
		$this->load->model('exportmodel');
		$this->load->helper('order');

		if(!$this->cfg_order)	$this->cfg_order = config_load('order');

		$aParams 			= $this->input->post();
		$order_seq			= $aParams['order_seq'];
		$data_order			= $this->ordermodel->get_order($order_seq);
		$data_order_items 	= $this->ordermodel->get_item($order_seq);

		//결제취소/주문무효/결제실패-> 결제취소 불가 @2016-07-12 ysm
		if( in_array($data_order['step'],array('85','95','99')) ){
			//에서는 환불신청을 하실 수 없습니다.
			openDialogAlert($this->arr_step[$data_order['step']].getAlert('mo143'),400,140,'parent');
			exit;
		}

		if($data_order['orign_order_seq']){
			//교환 주문 건은 결제 취소를 할 수 없습니다.<br />교환 주문 건 취소는 관리자가 교환 주문 건에 대해 출고 및 반품 처리 후 환불 처리 가능합니다.
			openDialogAlert(getAlert('mp185'),400,170,'parent');
			exit;
		}

		if(!$aParams['chk_seq']){
			//결제취소/환불 신청할 상품을 선택해주세요.
			openDialogAlert(getAlert('mo031'),400,140,'parent');
			exit;
		}

		$order_total_ea		= $this->ordermodel->get_order_total_ea($order_seq);
		$cancel_total_ea = 0;

		foreach($aParams['chk_seq'] as $k=>$v){
			if(!$aParams['chk_ea'][$k]){
				//결제취소/환불 신청할 수량을 선택해주세요.
				openDialogAlert(getAlert('mo032'),400,140,'parent');
				exit;
			}
			$cancel_total_ea += $aParams['chk_ea'][$k];
		}

		//청약철회상품체크
		$cancel_type = 0;
		foreach($data_order_items as $item){
			if(in_array($item['item_seq'],$aParams['chk_item_seq'])){
				$goodscanceltype = $this->goodsmodel->get_goods($item['goods_seq']);
				if( $goodscanceltype['cancel_type']) {//청약철회상품 반품불가
					$cancel_type++;
					continue;
				}
				if($goodscanceltype['goods_type']=='gift') {
					$gift_type++;
					continue;
				}
			}
		}
		if( $cancel_type > 0 ) {
			//청약철회 상품은 결제취소가 [불가능]합니다.
			openDialogAlert(getAlert('mo033'),400,140,'parent');
			exit;
		}

		if( $gift_type > 0 ) {
			//사은품은 결제취소가 불가능합니다. 상품 결제취소 시 자동으로 취소됩니다.
			openDialogAlert(getAlert('mo156'),400,140,'parent');
			exit;
		}

		//환불 방법 입력부분 체크
		if($aParams['chk_seq'] && $aParams['chk_ea'] && in_array($data_order['payment'], ['bank', 'virtual', 'escrow_virtual'])) {
			if(!$aParams['bank_name'] || !$aParams['bank_depositor'] || !$aParams['bank_account']) {

				if(!$aParams['bank_name']) $bank['refundBank'] = "환불 은행";
				if(!$aParams['bank_depositor']) $bank['depositor'] = "예금주";
				if(!$aParams['bank_account']) $bank['accountNum'] = "계좌번호";

				$refundMethod = implode(', ', $bank);

				if($data_order['payment']=='bank') $payMethod = "무통장";
				if($data_order['payment']=='virtual' || $data_order['payment'] == 'escrow_virtual') $payMethod = "가상계좌";

				openDialogAlert(getAlert('mo160',array($payMethod, $refundMethod)),400,160,'parent','');
				exit;
			}
		}

		$result_option		= $this->ordermodel->get_item_option($order_seq);
		$result_suboption	= $this->ordermodel->get_item_suboption($order_seq);
		/**
		** 주문상품 옵션별/추가옵션별 주문취소가능 조건 (주문접수, 결제완료, 상품준비)
		** 최소(환불) 설정 => 0:결제확인 25, 상품준비 35, 1:결제확인 25
		** @2017-02-28
		**/
		if($aParams['chk_item_seq']){
			foreach($result_option as $opts){
				if(in_array($opts['item_seq'],$aParams['chk_item_seq'])){
					if( ($this->cfg_order['cancelDisabledStep35'] != '1' && !($opts['step']==25 || $opts['step']==35)) ||
						($this->cfg_order['cancelDisabledStep35'] == '1' && $opts['step']!=25) )
					{
						openDialogAlert(getAlert('mo143',$this->arr_step[$opts['step']]),400,140,'parent');
						exit;
					}
				}
			}
			foreach($result_suboption as $opts){
				if(in_array($opts['item_seq'],$aParams['chk_item_seq'])){
					if( ($this->cfg_order['cancelDisabledStep35'] != '1' && !($opts['step']==25 || $opts['step']==35)) ||
						($this->cfg_order['cancelDisabledStep35'] == '1' && $opts['step']!=25) )
					{
						openDialogAlert(getAlert('mo143',$this->arr_step[$opts['step']]),400,140,'parent');
						exit;
					}
				}
			}
		}

		//취소가능 수량(주문접수, 결제완료, 상품준비) @2015-06-05 pjm
		$able_return_item = 0;
		foreach($result_option as $data){
			$able_return_ea = (int) $data['ea'] - (int) $data['step85'] - (int) $data['step55']
												- (int) $data['step65'] - (int) $data['step75'];
			$able_return_total += $able_return_ea;
			if($able_return_ea > 0 ) $able_return_item++;
		}
		foreach($result_suboption as $data){
			$able_return_ea = (int) $data['ea'] - (int) $data['step85'] - (int) $data['step55']
												- (int) $data['step65'] - (int) $data['step75'];
			$able_return_total += $able_return_ea;
			if($able_return_ea > 0 ) $able_return_item++;
		}
		if($able_return_item == 0){
			//환불 가능 수량이 없습니다.
			openDialogAlert(getAlert('mo034'),400,140,'parent');
			exit;
		}

		// 사은품 있는 경우 확인 필요
		$gift_order = false;
		foreach($data_order_items as $item){
			if($item['goods_type'] == 'gift') {
				list($gift) = $this->ordermodel->get_option_for_item($item['item_seq']);
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
					$aParams['chk_seq'][]				= '1';
					$aParams['chk_item_seq'][]		= $gift['item_seq'];
					$aParams['chk_option_seq'][]		= $gift['item_option_seq'];
					$aParams['chk_suboption_seq'][]	= '';
					$aParams['chk_ea'][]				= $gift['ea'];
				}
			} else {
				$gift_cancel = $this->ordermodel->order_gift_partial_cancel($order_seq, $gift_item_seq, $data_order_items );

				// aParams 변수 담아서 실제 사은품 취소 처리
				if(count($gift_cancel) > 0) {
					foreach($gift_cancel as $key => $gift) {
						$aParams['chk_seq'][]				= '1';
						$aParams['chk_item_seq'][]		= $gift['item_seq'];
						$aParams['chk_option_seq'][]		= $gift['item_option_seq'];
						$aParams['chk_suboption_seq'][]	= '';
						$aParams['chk_ea'][]				= $gift['ea'];
					}
				}
			}
		}

		$refund_status = 'request';

		/* 신용카드 자동취소 */
		if((in_array($data_order['payment'],array('card','kakaomoney')) || $data_order['pg'] == 'payco') && $order_total_ea==$cancel_total_ea)
		{
			$pgCompany = $this->config_system['pgCompany'];

			// 기타전자결제의 PG사를 추출하기 위한 데이터 :: 2015-02-25 lwh
			switch($data_order['pg']){
				case 'kakaopay':
				case 'payco':
					$pglog_tmp				= $this->ordermodel->get_pg_log($data_order['order_seq']);
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

			if(!($pgCompany=='allat' && $this->mobileMode)) // 모바일모드에서 올앳취소시에는 바로취소 안하도록 수정(activeX때문)
			{
				$cancelFunction = "{$pgCompany}_cancel";
				$cancelResult = $this->refundmodel->$cancelFunction($data_order,array('refund_reason'=>$aParams['refund_reason'],'cancel_type'=>'full'));
	
				if(!$cancelResult['success']){
					/*
					//{$pgCompany} 결제 취소 실패<br /><font color=red>{$cancelResult['result_code']} : {$cancelResult['result_msg']}</font>
					openDialogAlert(getAlert('mo035',array($pgCompany,$cancelResult['result_code'],$cancelResult['result_msg'])),400,160,'parent','');
					exit;
					*/
					// pg 결제 취소 실패 시 환불 접수 단계로만 수정
					$refund_status		= 'request';
					$cancel_pg_card		= false;
				} else {
					$refund_status		= 'complete';
					$cancel_pg_card		= true;					
				}
			}
			$aParams['cancel_type'] = 'full';
		}else if($order_total_ea==$cancel_total_ea){
			$aParams['cancel_type'] = 'full';
		}else{
			$aParams['cancel_type'] = 'partial';
		}

		if(!$aParams['bank_name'])		$bank_name		= ""; else $bank_name		= $aParams['bank_name'];
		if(!$aParams['bank_depositor'])	$bank_depositor = ""; else $bank_depositor	= $aParams['bank_depositor'];
		if(!$aParams['bank_account'])		$bank_account	= ""; else $bank_account	= $aParams['bank_account'];

		$data = array(
			'order_seq' 		=> $order_seq,
			'bank_name'			=> $bank_name,
			'bank_depositor'	=> $bank_depositor,
			'bank_account'		=> $bank_account,
			'refund_reason' => $aParams['refund_reason'],
			'refund_type' => 'cancel_payment',
			'cancel_type' => $aParams['cancel_type'],
			'regist_date' => date('Y-m-d H:i:s'),
		);

		$this->db->trans_begin();
		$rollback = false;

		$items = array();
		$r_reservation_goods_seq = array();
		$refund_delivery_shipping = array();

		// 원주문의 배송정보
		$data_shipping		= $this->ordermodel->get_order_shipping($order_seq);
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
		foreach($aParams['chk_seq'] as $k=>$v){

			$items[$k]['item_seq']			= $aParams['chk_item_seq'][$k];
			$items[$k]['option_seq']		= $aParams['chk_suboption_seq'][$k] ? '' : $aParams['chk_option_seq'][$k];
			$items[$k]['suboption_seq']		= $aParams['chk_suboption_seq'][$k];
			$items[$k]['ea']				= $aParams['chk_ea'][$k];
			$items[$k]['partner_return']	= true;
			$chk_shipping_seq				= $aParams['chk_shipping_seq'][$k];

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

				$rf_ea = $this->refundmodel->get_refund_option_ea($items[$k]['item_seq'],$items[$k]['option_seq']);
				$step_complete = $this->ordermodel->get_option_export_complete($order_seq,$chk_shipping_seq,$items[$k]['item_seq'],$items[$k]['option_seq']);
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
					$refund_goods_price	= (($optionData['price']-$optionData['member_sale'])*$optionData['ea'])
													-$optionData['fblike_sale']
													-$optionData['mobile_sale']
													-$optionData['referer_sale']
													-$refund_emoney_unit
													-$refund_cash_unit;

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
					$items[$k]['refund_delivery_emoney']	= ($shippingData['emoney_sale_unit'] + $shippingData['emoney_sale_rest']);
					$items[$k]['refund_delivery_cash']		= ($shippingData['cash_sale_unit'] + $shippingData['cash_sale_rest']);

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
				$step_complete = $this->ordermodel->get_suboption_export_complete($order_seq,$chk_shipping_seq,$items[$k]['item_seq'],$items[$k]['suboption_seq']);
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

				//pg 카드결제취소시 상품최종환불액 자동계산 @2016-07-21
				if( $cancel_pg_card ) {
					$refund_emoney_unit = $optionData['emoney_sale_unit']*$optionData['ea']+$optionData['emoney_sale_rest'];
					$refund_cash_unit	= $optionData['cash_sale_unit']*$optionData['ea']+$optionData['cash_sale_rest'];
					$refund_goods_price	= (($optionData['price']-$optionData['member_sale'])*$optionData['ea'])
													-$optionData['fblike_sale']
													-$optionData['mobile_sale']
													-$optionData['referer_sale']
													-$refund_emoney_unit
													-$refund_cash_unit;
					$shippingSeq = $optionData['shipping_seq'];

					if(!in_array($shippingSeq, $refund_delivery_shipping)) {
						$refund_delivery_shipping[] = $shippingSeq;
						
						$refund_delivery = $ships[$shippingSeq]['shipping_cost'] 
							- (($ships[$shippingSeq]['cash_sale_unit']) + ($ships[$shippingSeq]['cash_sale_rest'])) 
							- (($ships[$shippingSeq]['emoney_sale_unit']) + ($ships[$shippingSeq]['emoney_sale_rest'])) 
							- (($ships[$shippingSeq]['enuri_sale_unit']) + ($ships[$shippingSeq]['enuri_sale_rest'])) 
							- $ships[$shippingSeq]['shipping_promotion_code_sale'] - $ships[$shippingSeq]['shipping_coupon_sale'];
					} else {
						$refund_delivery = 0;
					}
					$items[$k]['cash_sale_unit']			= $optionData['cash_sale_unit'];
					$items[$k]['emoney_sale_unit']			= $optionData['emoney_sale_unit'];
					$items[$k]['cash_sale_rest']			= $optionData['cash_sale_rest'];
					$items[$k]['emoney_sale_rest']			= $optionData['emoney_sale_rest'];
					$items[$k]['refund_delivery_cash']		= $ships[$shippingSeq]['cash_sale_unit'] + $ships[$shippingSeq]['cash_sale_rest'];
					$items[$k]['refund_delivery_emoney']	= $ships[$shippingSeq]['emoney_sale_unit'] + $ships[$shippingSeq]['emoney_sale_rest'];
					$items[$k]['refund_goods_price']		= $refund_goods_price;
					$items[$k]['refund_delivery_price']		= $refund_delivery;

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
		}

		if ($this->db->trans_status() === FALSE || $rollback == true)
		{
			//"잠시 후 다시 시도하여주십시오.<br/>오류가 계속 될 경우 고객센터로 문의하세요."
		    $this->db->trans_rollback();
		    openDialogAlert(getAlert('mb178'),400,140,'parent','');
			exit;
		}
		else
		{
		    $this->db->trans_commit();
		}

		$this->ordermodel->set_order_step($order_seq);

		/* 신용카드 자동취소 */
		if(($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $order_total_ea==$cancel_total_ea)
		{
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
		if(!$refund_code){
			openDialogAlert(getAlert('mb178'),400,140,'parent','');
			exit;
		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		/* 신용카드 자동취소 */
		if( $cancel_pg_card === true && $refund_status == 'complete' )
		{
			$this->load->model('emoneymodel');
			$this->load->model('membermodel');
			$this->load->model('couponmodel');
			$this->load->model('promotionmodel');
			$this->load->helper('text');

			$refund_emoney = 0;
			$refund_cash = 0;

			$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
			$data_member		= $this->membermodel->get_member_data($data_order['member_seq']);

			//상품별 할인쿠폰/프로모션코드 복원
			foreach($aParams['chk_seq'] as $k=>$v){
				$items[$k]['item_seq']		= $aParams['chk_item_seq'][$k];
				$items[$k]['option_seq']	= $aParams['chk_suboption_seq'][$k] ? '' : $aParams['chk_option_seq'][$k];
				$items[$k]['suboption_seq']	= $aParams['chk_suboption_seq'][$k];
				$items[$k]['ea']			= $aParams['chk_ea'][$k];

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

			/* 배송비쿠폰 복원*/
			$shipping_coupon_seq	= $this->couponmodel->get_shipping_coupon($data_order['order_seq']);
			if($shipping_coupon_seq){
				$shippingcoupon = $this->couponmodel->restore_used_coupon($shipping_coupon_seq);
			}
			// 주문서쿠폰 복원
			if($data_order['ordersheet_seq']){
				$ordersheetcoupon = $this->couponmodel->restore_used_coupon($data_order['ordersheet_seq']);
			}

			/* 배송비프로모션코드 복원 개별코드만 */
			if($data_order['shipping_promotion_code_seq']){
				$shippingpromotioncode = $this->promotionmodel->restore_used_promotion($data_order['shipping_promotion_code_seq']);
			}

			if($data_order['member_seq'] ) {
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

					// 기본 마일리지 유효기간 계산
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
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp249",$refund_code), // [복원]결제취소(%s)에 의한 예치금 환원
					);
					$this->membermodel->cash_insert($params, $data_order['member_seq']);
					$this->ordermodel->set_cash_use($data_order['order_seq'],'return');

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
				'refund_price'			=> $data_order['settleprice'] + $data_order['emoney'] + $data_order['cash'],
				'refund_emoney'			=> $refund_emoney,
				'refund_cash'			=> $refund_cash,
				'status'				=> $refund_status,
				'refund_emoney_limit_date' => $reserve_limit_date,
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
			$params = array_merge($saveData,$aParams);
			$params	= array_merge($params,$data_member);
			$params['refund_reason']	= htmlspecialchars($aParams['refund_reason']);
			$params['refund_date']		= $saveData['refund_date'];
			$params['mstatus'] 			= $this->refundmodel->arr_refund_status['complete'];
			$params['refund_price']		= number_format($saveData['refund_price']);
			$params['mrefund_method']	= $this->arr_payment['card'].' '.$this->arr_step[85];
			$params['items'] 			= $items_array;
			$params['order'] 			= $order_itemArr;
			if( $data_order['order_email'] )
				sendMail($data_order['order_email'], 'cancel', $data_member['userid'], $params);

			/* 결제취소완료 SMS 발송 */
			$params = array();
			$params['shopName'] = $this->config_basic['shopName'];
			$params['ordno']	= $data_order['order_seq'];
			$params['member_seq']	= $data_order['member_seq'];
			$params['user_name'] = $data_order['order_user_name'];

			//SMS 데이터 생성
			$commonSmsData['cancel']['phone'][] = $data_order['order_cellphone'];
			$commonSmsData['cancel']['params'][] = $params;
			$commonSmsData['cancel']['order_no'][] = $data_order['order_seq'];

			if(count($commonSmsData) > 0){
				commonSendSMS($commonSmsData);
			}

			//GA통계
			if($this->ga_auth_commerce_plus){
				$ga_item = $this->refundmodel->get_refund_item($refund_code);
				$ga_params['item'] = $ga_item;
				$ga_params['order_seq'] = $data_order['order_seq'];
				$ga_params['action'] = "refund";
				echo google_analytics($ga_params,"refund");
			}

			$logTitle	= "결제취소";
			$logDetail	= "신용카드 전체취소처리하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($order_seq,'process','주문자',$logTitle,$logDetail,$logParams);
			
			// [판매지수 EP] 주문완료 후 통계테이블에 ep 정보 저장 :: 2020-04-09
			if(!$this->statsmodel) $this->load->model('statsmodel');
			$this->statsmodel->set_refund_sale_ep($refund_code);

			/**
			 * 4-2 환불데이타를 이용한 통합정산테이블 생성 시작
			 * @
			 **/
			 $this->load->helper('accountall');
			 if(!$this->accountallmodel) $this->load->model('accountallmodel');
			 if(!$this->providermodel) $this->load->model('providermodel');
			 if(!$this->refundmodel)  $this->load->model('refundmodel');
			 if(!$this->returnmodel)  $this->load->model('returnmodel');

			 //통합정산 생성(미정산매출 환불건수 업데이트)
			//정산대상 수량업데이트
			$this->accountallmodel->update_calculate_sales_ac_ea($data_order['order_seq'],$refund_code, 'refund', $data_refund_item, $data_order);
			//정산확정 처리
			 $this->accountallmodel->insert_calculate_sales_order_refund($data_order['order_seq'], $refund_code, $data_refund['cancel_type'], $data_order, $data_refund, $data_refund_item);
			 //debug_var($this->db->queries);
			 //debug_var($this->db->query_times);
			 /**
			 * 4-2 환불데이타를 이용한 통합정산테이블 생성 시작 끝
			 * @
			 **/

			if($aParams['use_layout']){
				$callback = "
				parent.document.location.replace('../mypage/order_view?no={$order_seq}');
				";
			}else{
				$callback = "
				parent.closeDialog('order_refund_layer');
				parent.document.location.reload();
				";
			}
			//신용카드 결제취소가 완료되었습니다.
			//openDialogAlert(getAlert('mo036'),400,140,'parent',$callback);

			//결제취소/환불 신청이 완료되었습니다.
			openDialogAlert(getAlert('mo037'),400,140,'parent',$callback);
		}else{
			$logTitle	= "결제취소 신청(".$refund_code.")";
			$logDetail	= "결제취소/환불신청하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($order_seq,'process','주문자',$logTitle,$logDetail,$logParams);

			if($aParams['use_layout']){
				$callback = "
				parent.document.location.replace('../mypage/order_view?no={$order_seq}');
				";
			}else{
				$callback = "
				parent.closeDialog('order_refund_layer');
				parent.document.location.reload();
				";
			}
			//결제취소/환불 신청이 완료되었습니다.
			openDialogAlert(getAlert('mo037'),400,140,'parent',$callback);
		}

	}

	//실물상품 반품 or 맞교환 -> 환불
	public function order_return(){

		$this->load->model('returnmodel');
		$this->load->model('exportmodel');
		$this->load->helper('order');

		$cfg_order = config_load('order');

		$aParams = $this->input->post();
		if(!$aParams['chk_seq']){
			//반품 신청할 상품을 선택해주세요.
			openDialogAlert(getAlert('mo081'),400,140,'parent');
			exit;
		}

		// 반품 배송비 무결성 체크 :: 2018-05-21 lwh
		if ($aParams['reason'] == '120'){
			$this->validation->set_rules('refund_ship_type', getAlert('mo153'),'trim|required|xss_clean');
			$refund_ship_duty = 'buyer';
		}else{
			$refund_ship_duty = 'seller';
		}
		if ($aParams['refund_ship_type'] == 'A'){
			$this->validation->set_rules('shipping_price_bank_account', getAlert('os064'),'trim|required|xss_clean');
			$this->validation->set_rules('shipping_price_depositor', getAlert('os063'),'trim|required|xss_clean');
		}

		//휴대폰
		if(is_array($aParams['cellphone'])) $this->validation->set_rules('cellphone[]', getAlert('mo082'),'trim|required|numeric|max_length[4]|xss_clean');
		//휴대폰
		else $this->validation->set_rules('cellphone', getAlert('mo082'),'trim|required|max_length[14]|xss_clean');

		if($aParams['return_method'] == 'shop'){
			if($aParams['return_recipient_new_zipcode']){
				//우편번호
				$this->validation->set_rules('return_recipient_new_zipcode', getAlert('mo083'),'trim|required|numeric|max_length[7]|xss_clean');
			}else{
				//우편번호
				$this->validation->set_rules('return_recipient_zipcode[]', getAlert('mo083'),'trim|required|numeric|max_length[7]|xss_clean');
			}
			//주소
			$this->validation->set_rules('return_recipient_address', getAlert('mo084'),'trim|required|xss_clean');
			//상세주소
			$this->validation->set_rules('return_recipient_address_detail', getAlert('mo085'),'trim|required|xss_clean');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$data_order = $this->ordermodel->get_order($aParams['order_seq']);
		$data_order_items 		= $this->ordermodel->get_item($aParams['order_seq']);
		if( !in_array($data_order['step'],array(55,60,65,70,75)) ){
			//에서는 반품신청을 하실 수 없습니다.
			openDialogAlert($this->arr_step[$data_order['step']].getAlert('mo086'),400,140,'parent');
			exit;
		}

		foreach ($aParams['chk_ea'] as $k => $chk_ea ){
			if($chk_ea == 0){
			//반품 수량을 0건으로 입력한 경우에는 신청되지 않습니다.
			openDialogAlert(getAlert('mo087'),400,140,'parent');
			exit;
			}
			$cancel_total_ea += $chk_ea;
		}

		if($aParams['mode']=='exchange'){
			$mode_title		= "맞교환";
			$logTitle		= "맞교환신청";
		}else{
			$mode_title		= "반품";
			$logTitle		= "반품신청";
		}

		$total_pay_shipping = 0;
		foreach($aParams['pay_shiping_cost'] as $pay_shipping){
			$total_pay_shipping += $pay_shipping;
		}

		// 환불금액 차감 검증 :: 2018-07-03 lwh
		// 위치 이동 시킴 :: 2018-07-19 pjw
		// 수식 변경 :: 2018-08-23 pjw
		// 실 결제금액, 반품배송비 크기 비교
		// 2018-10-15 pjm 반품하는 상품 전체의 결제금액으로 비교.
		$total_payment_amount	= 0;
		foreach($aParams['chk_ea'] as $k => $return_apply_ea){
			
			$option_seq				= $aParams['chk_option_seq'][$k];
			$suboption_seq			= $aParams['chk_suboption_seq'][$k];

			$option_data			= $this->ordermodel->get_order_item_option($option_seq);
			$suboption_data			= $this->ordermodel->get_order_item_suboption($suboption_seq);
			
			$total_payment_amount	+= $option_data['sale_price'] * $return_apply_ea;
			$total_payment_amount	+= $suboption_data['sale_price'] * $return_apply_ea;

		}

		if($total_payment_amount < $total_pay_shipping && $refund_ship_duty == 'buyer' && $aParams['refund_ship_type'] == 'M'){
			openDialogAlert(getAlert('mo154'),400,140,'parent');
			exit;
		}


		## 반품가능 수량 user @2015-06-05 pjm
		foreach($aParams['chk_ea'] as $k => $return_apply_ea){

			if($return_apply_ea == 0){
				//반품 수량을 0건으로 입력한 경우에는 신청되지 않습니다.
				openDialogAlert(getAlert('mo087'),400,140,'parent');
				exit;
			}

			$export_code		= $aParams['chk_export_code'][$k];
			$item_seq			= $aParams['chk_item_seq'][$k];
			$option_seq			= $aParams['chk_option_seq'][$k];
			$suboption_seq		= $aParams['chk_suboption_seq'][$k];
			$able_return_ea		= 0;
			$cancel_type		= false;	//청약철회상품체크

			$orditemData		= $this->ordermodel->get_item_one($item_seq);

			## 청약철회상품체크(반품불가)
			$goodscanceltype = $this->goodsmodel->get_goods($orditemData['goods_seq']);
			if( $goodscanceltype['cancel_type']) $cancel_type = true;

			if($cancel_type){
				//청약철회 상품은 ".$mode_title."이 [불가능]합니다.
				openDialogAlert(getAlert('mo088',$mode_title),400,140,'parent');
				exit;
			}

			if($goodscanceltype['goods_type']=='gift') {
				//사은품은 결제취소가 불가능합니다. 상품 결제취소 시 자동으로 취소됩니다.
				openDialogAlert(getAlert('mo156'),400,140,'parent');
				exit;
			}

			## 출고수량
			$exp_data			= $this->exportmodel->get_export_item_ea($export_code,$option_seq,$suboption_seq);

			## 구매확정 사용시 : 지급예정수량(출고완료+배송중+배송완료)
			## 구매확정 미사용시 : 출고수량(출고완료 + 배송중 + 배송완료) - 반품수량
			if($cfg_order['buy_confirm_use']){
				$able_return_ea	= $exp_data['reserve_ea'];
			}else{
				## 반품수량
				if(!$suboption_seq) $return_item = $this->returnmodel->get_return_item_ea($item_seq,$option_seq,$export_code);
					else $return_item = $this->returnmodel->get_return_subitem_ea($item_seq,$suboption_seq,$export_code);

				$able_return_ea	= $exp_data['ea'] - $return_item['ea'];
			}

			if($able_return_ea == 0){
				//반품 가능한 수량이 없습니다.
				openDialogAlert(getAlert('mo089'),400,140,'parent');
				exit;
			}

			if($able_return_ea < $return_apply_ea){
				//반품수량이 반품가능수량보다 많습니다.
				openDialogAlert(getAlert('mo090'),400,140,'parent');
				exit;
			}
		}

		//환불 방법 입력부분 체크
		if ($aParams['mode'] != 'exchange' && $aParams['chk_seq'] && $aParams['chk_ea'] && in_array($data_order['payment'], ['bank', 'virtual', 'escrow_virtual'])) {
			// 모바일 스킨은 string , 반응형/pc 는 array
			$account = is_array($aParams['account']) ? implode('', $aParams['account']) : $aParams['account'];
			if (!$aParams['depositor'] || !$account) {
				if (!$aParams['depositor']) {
					$bank['depositor'] = '예금주';
				}
				if (!$account) {
					$bank['accountNum'] = '계좌번호';
				}

				$refundMethod = implode(', ', $bank);

				if ($data_order['payment'] == 'bank') {
					$payMethod = '무통장';
				}
				if ($data_order['payment'] == 'virtual' || $data_order['payment'] == 'escrow_virtual') {
					$payMethod = '가상계좌';
				}

				openDialogAlert(getAlert('mo160', [$payMethod, $refundMethod]), 400, 160, 'parent', '');
				exit;
			}
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
					$aParams['chk_seq'][]				= '1';
					$aParams['chk_item_seq'][]		= $gift['item_seq'];
					$aParams['chk_option_seq'][]		= $gift['option_seq'];
					$aParams['chk_suboption_seq'][]	= '';
					$aParams['chk_ea'][]				= $gift['ea'];
					$aParams['chk_export_code'][]		= $gift['export_code'];
				}
			} else {
				$gift_cancel = $this->ordermodel->order_gift_partial_cancel($aParams['order_seq'], $gift_item_seq, $data_order_items,'return');

				// aParams 변수 담아서 실제 사은품 취소 처리
				if(count($gift_cancel) > 0) {
					foreach($gift_cancel as $key => $gift) {
						$aParams['chk_seq'][]				= '1';
						$aParams['chk_item_seq'][]		= $gift['item_seq'];
						$aParams['chk_option_seq'][]		= $gift['item_option_seq'];
						$aParams['chk_suboption_seq'][]	= '';
						$aParams['chk_ea'][]				= $gift['ea'];
						$aParams['chk_export_code'][]		= $gift['export_code'];
					}
				}
			}
		}

		$export_codes = array();
		foreach($aParams['chk_export_code'] as $k => $chk_export_code){

			$cancelquery = "select * from fm_order_item where item_seq=?";
			$cancelquery = $this->db->query($cancelquery,array($aParams['chk_item_seq'][$k]));
			$orditemData = $cancelquery->row_array();

			if($aParams['chk_option_seq'][$k] && !$aParams['chk_suboption_seq'][$k]){
				//티켓상품의 취소(환불) 가능여부::반품
				if ( $orditemData['goods_kind'] == 'coupon'){
					continue;
				}
			}
			if(!in_array($chk_export_code,$export_codes)) $export_codes[] = $chk_export_code;

			$aParams['give_reserve_ea'][$k] = 0;		//지급수량에서 차감된 반품수량

			##----------------------------------------------------------------------------------
			## 구매확정 사용시 @2015-03-27 pjm
			if($cfg_order['buy_confirm_use']){

				$chk = array();
				$chk['export_code']		= $chk_export_code;
				$chk['item_seq']		= $aParams['chk_item_seq'][$k];
				if($aParams['chk_option_seq'][$k] && !$aParams['chk_suboption_seq'][$k]){
					$chk['option_seq'] 		= $aParams['chk_option_seq'][$k];
				}else{
					$chk['suboption_seq']	= $aParams['chk_suboption_seq'][$k];
				}
				# 출고정보 불러오기
				$export_items = $this->exportmodel->get_export_item_by_item_seq('',$chk);

				# 지급예정수량이 있을때
				if($export_items['reserve_ea'] > 0){
					$tmp = array();
					$tmp['export_code']			= $chk_export_code;
					$tmp['item_seq']			= $export_items['item_seq'];
					if($export_items['option_seq']){
						$tmp['option_seq']		= $export_items['option_seq'];
					}
					if($export_items['suboption_seq']){
						$tmp['suboption_seq']	= $export_items['suboption_seq'];
					}
					$tmp['reserve_ea']			= $export_items['reserve_ea'] - $aParams['chk_ea'][$k];
					$tmp['reserve_return_ea']	= $export_items['reserve_return_ea'] + $aParams['chk_ea'][$k];
					$export_items_reserve[]	= $tmp;
				}
			##----------------------------------------------------------------------------------
			## 구매확정 미사용시
			}else{
				//지급수량에서 차감된 반품수량
				//구매확정 미사용일때 반품신청시 자동배송완료처리 되며, 이때 마일리지도 같이 지급된다.
				$aParams['give_reserve_ea'][$k] = $aParams['chk_ea'][$k];
			}
			##----------------------------------------------------------------------------------

		}

		$this->db->trans_begin();
		$rollback = false;

		## 구매확정 사용시에만 마일리지지급예정수량 조절 2015-03-26 pjm
		if($cfg_order['buy_confirm_use'] && $export_items_reserve){
			$this->exportmodel->exec_export_reserve_ea($export_items_reserve,'return');
		}

		foreach($export_codes as $export_code){
			$exports		= $this->exportmodel->get_export($export_code);
			$reserve_save	= $exports['reserve_save'];		//마일리지 지급여부

			if(in_array($exports['status'],array(55,60,65,70))){
				//반품 또는 맞교환 환불신청시 메일/문자 미처리 @2016-12-09
				$this->_batch_buy_return = ($aParams['mode'])?$aParams['mode']:'return';
				if( !empty($exports['bundle_export_code']) ){
					$export_code = $exports['bundle_export_code'];
				}
				$reserve_save = $this->exportmodel->exec_complete_delivery($export_code);// 배송완료(수령확인)처리
			}
		}

		// 환불 등록
		if($aParams['bank']){
			$tmp = code_load('bankCode',$aParams['bank']);
			$bank = $tmp[0]['value'];
		}

		$account = is_array($aParams['account']) ? implode('-',$aParams['account']) : $aParams['account'];

        $aParams['refund_method'] = ($aParams['refund_method'])?$aParams['refund_method']:(($data_order['payment'])?$data_order['payment']:'bank');

		$items = array();

		$cancel_type		= 0;	//청약철회상품체크
		$socialcp_return_use= 0;	//티켓상품
		$tot_give_reserve	= 0;
		$tot_give_point		= 0;
		foreach($aParams['chk_seq'] as $k=>$v) {
			$cancelquery = "select * from fm_order_item where item_seq=?";
			$cancelquery = $this->db->query($cancelquery,array($aParams['chk_item_seq'][$k]));
			$orditemData = $cancelquery->row_array();

			//청약철회상품체크
			$goodscanceltype = $this->goodsmodel->get_goods($orditemData['goods_seq']);
			if( $goodscanceltype['cancel_type']) {//청약철회상품 반품불가
				$cancel_type++;
				continue;
			}

			$items[$k]['item_seq']		= $aParams['chk_item_seq'][$k];
			$items[$k]['option_seq']	= $aParams['chk_suboption_seq'][$k] ? '' : $aParams['chk_option_seq'][$k];
			$items[$k]['suboption_seq']	= $aParams['chk_suboption_seq'][$k];
			$items[$k]['ea']			= $aParams['chk_ea'][$k];

			if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){

				## 지급한 마일리지&포인트 뽑아오기. 2015-07-23 pjm
				$give_reserve_ea	= $aParams['give_reserve_ea'][$k];
				if($give_reserve_ea > 0){
					$reserve	= $this->ordermodel->get_option_reserve($items[$k]['option_seq']);
					$point		= $this->ordermodel->get_option_reserve($items[$k]['option_seq'],'point');
					$give_reserve		= $reserve * $give_reserve_ea;
					$give_point			= $point * $give_reserve_ea;

					$tot_give_reserve	+= $give_reserve;
					$tot_give_point		+= $give_point;
				}else{
					$give_reserve		= 0;
					$give_point			= 0;
					$give_reserve_ea	= 0;
				}

				$items[$k]['give_reserve']		= $aParams['give_reserve'][$k]		= $give_reserve;
				$items[$k]['give_point']		= $aParams['give_point'][$k]			= $give_point;
				$items[$k]['give_reserve_ea']	= $give_reserve_ea;

				// 반품으로 인한 원주문 추출 및 교체 :: 2014-11-27 lwh
				$query = $this->db->get_where('fm_order_item_option',
					array(
					'item_option_seq'=>$items[$k]['option_seq'],
					'item_seq'=>$items[$k]['item_seq'])
				);
				$result = $query -> result_array();

				if($result[0]['top_item_option_seq'])
					$items[$k]['option_seq'] = $result[0]['top_item_option_seq'];

				if($result[0]['top_item_option_seq'])
					$items[$k]['item_seq'] = $result[0]['top_item_seq'];

				//티켓상품의 취소(환불) 가능여부::반품
				if ( $orditemData['goods_kind'] == 'coupon'){
					$socialcp_return_use++;
					unset($items[$k]);
					continue;
				}

				$query = "select * from fm_order_item_option where item_option_seq=?";
				$query = $this->db->query($query,array($items[$k]['option_seq']));
				$optionData = $query->row_array();

				if($aParams['mode']!='exchange'){
					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_option_seq',$items[$k]['option_seq']);
					$this->db->update('fm_order_item_option');
				}
			}else if($items[$k]['suboption_seq']){

				## 지급한 마일리지&포인트 뽑아오기. 2015-03-31 pjm
				$give_reserve_ea	= $aParams['give_reserve_ea'][$k];
				if($give_reserve_ea > 0){
					$reserve = $this->ordermodel->get_suboption_reserve($items[$k]['suboption_seq']);
					$point = $this->ordermodel->get_suboption_reserve($items[$k]['suboption_seq'],'point');
					$give_reserve		= $reserve * $give_reserve_ea;
					$give_point			= $point * $give_reserve_ea;

					$tot_give_reserve	+= $give_reserve;
					$tot_give_point		+= $give_point;
				}else{
					$give_reserve		= 0;
					$give_point			= 0;
					$give_reserve_ea	= 0;
				}

				$items[$k]['give_reserve']		= $aParams['give_reserve'][$k]		= $give_reserve;
				$items[$k]['give_point']		= $aParams['give_point'][$k]			= $give_point;
				$items[$k]['give_reserve_ea']	= $give_reserve_ea;

				// 반품으로 인한 원주문 추출 및 교체 :: 2014-11-27 lwh
				$query = $this->db->get_where('fm_order_item_suboption',
					array(
					'item_suboption_seq'=>$items[$k]['suboption_seq'])
				);
				$result = $query -> result_array();

				if($result[0]['top_item_suboption_seq'])
					$items[$k]['suboption_seq'] = $result[0]['top_item_suboption_seq'];

				$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
				$query = $this->db->query($query,array($items[$k]['suboption_seq']));
				$suboptionData = $query->row_array();

				if($aParams['mode']!='exchange'){
					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
					$this->db->update('fm_order_item_suboption');
				}
			}
		}

		if( $cancel_type == count($aParams['chk_seq']) ) {

			if($aParams['mode']=='exchange'){
				//청약철회 상품으로 맞교환이 [불가능]합니다.
				$title=getAlert('mo091');
			}else{
				//청약철회 상품으로 반품신청이 [불가능] 합니다.
				$title=getAlert('mo092');
			}

			openDialogAlert($title,400,140,'parent','');
			exit;
		} elseif( ($socialcp_return_use > 0 && $aParams['mode']=='exchange' ) || ($socialcp_return_use == count($aParams['chk_seq'])) ) {

			if($aParams['mode']=='exchange'){
				//티켓상품은 맞교환이 [불가능]합니다.
				$title=getAlert('mo093');
			}else{
				//티켓상품은 반품신청이 [불가능] 합니다.
				$title=getAlert('mo094');
			}

			openDialogAlert($title,400,140,'parent','');
			exit;
		}


		if($aParams['mode']=='exchange'){//맞교환
			$refund_code = '0';
			$return_type = 'exchange';
		}else{//반품
			if( !( ( $cancel_type == count($aParams['chk_seq']) ) || ( $socialcp_return_use == count($aParams['chk_seq']) ) ) ) {

				// 맞교환으로 인한 재주문을 반품신청시 최상위 주문번호 저장
				if($data_order['top_orign_order_seq'])
					$orgin_order_seq = $data_order['top_orign_order_seq'];
				else
					$orgin_order_seq = $aParams['order_seq'];

				$data = array(
					'order_seq' => $orgin_order_seq,
					'bank_name' => ($bank)?$bank:'',
					'bank_depositor' => ($aParams['depositor'])?$aParams['depositor']:'',
					'bank_account' => ($account)?$account:'',
					'refund_reason' => '반품환불',
					'refund_type' => 'return',
					'regist_date' => date('Y-m-d H:i:s'),
					'refund_method' => $aParams['refund_method']
				);
				$refund_code = $this->refundmodel->insert_refund($data,$items);
				if(!$refund_code){
					openDialogAlert(getAlert('mb178'),400,140,'parent','');
					exit;
				}
				$return_type = 'return';

				$logTitle	= "환불신청(".$refund_code.")";
				$logDetail	= "주문자 반품신청에 의한 환불신청이 접수되었습니다.";
				$logParams	= array('refund_code' => $refund_code);
				$this->ordermodel->set_log($orgin_order_seq,'process','주문자',$logTitle,$logDetail,$logParams);
			}
		}

		if(is_array($aParams['phone']))  $phone = implode('-',$aParams['phone']);
		else  $phone = $aParams['phone'];

		if(is_array($aParams['cellphone'])) $cellphone = implode('-',$aParams['cellphone']);
		else  $cellphone = $aParams['cellphone'];

		$zipcode = "";
		if($aParams['return_recipient_new_zipcode']){
			$zipcode = $aParams['return_recipient_new_zipcode'];
		}else{
			$zipcode = $aParams['return_recipient_zipcode'][0].$aParams['return_recipient_zipcode'][1];
		}

		// 반품 등록
		$insert_data['status'] 			= 'request';
		$insert_data['order_seq'] 		= $aParams['order_seq'];
		$insert_data['refund_code'] 	= $refund_code;
		$insert_data['return_type'] 	= $return_type;
		$insert_data['return_reason'] 	= $aParams['reason_detail'];
		$insert_data['cellphone'] 		= $cellphone;
		$insert_data['phone'] 			= $phone;
		$insert_data['return_method'] 	= $aParams['return_method'];
		$insert_data['sender_zipcode'] 	= $zipcode;
		$insert_data['sender_address_type']		= (($aParams['return_recipient_address_type']))?$aParams['return_recipient_address_type']:"zibun";
		$insert_data['sender_address'] 				= $aParams['return_recipient_address']?$aParams['return_recipient_address']:'';
		$insert_data['sender_address_street'] 	= $aParams['return_recipient_address_street'];
		$insert_data['sender_address_detail']	= $aParams['return_recipient_address_detail']?$aParams['return_recipient_address_detail']:'';
		$insert_data['regist_date'] 	= date('Y-m-d H:i:s');
		$insert_data['important'] 		= 0;
		$insert_data['shipping_price_depositor'] 	= $aParams['shipping_price_depositor'];
		$insert_data['shipping_price_bank_account'] = $aParams['shipping_price_bank_account'];

		$items = array();
		foreach($aParams['chk_seq'] as $k=>$v)
		{
			$query = "select * from fm_order_item where item_seq=?";
			$query = $this->db->query($query,array($aParams['chk_item_seq'][$k]));
			$orditemData = $query->row_array();

			//청약철회상품체크
			$goodscanceltype = $this->goodsmodel->get_goods($orditemData['goods_seq']);
			if( $goodscanceltype['cancel_type']) {//청약철회상품 반품불가
				continue;
			}

			if($aParams['chk_option_seq'][$k] && !$aParams['chk_suboption_seq'][$k]) {

				//티켓상품의 취소(환불) 가능여부
				if ( $orditemData['goods_kind'] == 'coupon' ) {
					continue;
				}

			}

			$items[$k]['item_seq']			= $aParams['chk_item_seq'][$k];
			$items[$k]['option_seq']		= $aParams['chk_suboption_seq'][$k] ? '' : $aParams['chk_option_seq'][$k];
			$items[$k]['suboption_seq']		= $aParams['chk_suboption_seq'][$k];
			$items[$k]['ea']				= $aParams['chk_ea'][$k];
			$items[$k]['reason_code']		= is_array($aParams['reason']) ? $aParams['reason'][$k] : $aParams['reason'];
			$items[$k]['reason_desc']		= is_array($aParams['reason_desc']) ? $aParams['reason_desc'][$k] : $aParams['reason_desc'];
			$items[$k]['export_code']		= $aParams['chk_export_code'][$k];
			$items[$k]['give_reserve_ea']	= $aParams['give_reserve_ea'][$k];	//회수마일리지수량
			$items[$k]['give_reserve']		= $aParams['give_reserve'][$k];		//회수마일리지액
			$items[$k]['give_point']		= $aParams['give_point'][$k];			//회수포인트액
			$items[$k]['partner_return']	= true;
		}

		// 배송비 정보 체크 :: 2018-05-28 lwh
		$data_shipping		= $this->ordermodel->get_order_shipping($aParams['order_seq']);
		if	($data_shipping)foreach($data_shipping as $k => $ship){
			$ship['swap_refund_shiping_cost'];
			$ship['refund_shiping_cost'];
		}

		// 환불배송비 자동계산 :: 2018-05-21 lwh
		$insert_data['reason_code']				= is_array($aParams['reason']) ? $aParams['reason'][0] : $aParams['reason'];
		$insert_data['reason_desc']				= is_array($aParams['reason_desc']) ? $aParams['reason_desc'][0] : $aParams['reason_desc'];
		$insert_data['refund_ship_duty']		= $refund_ship_duty;
		$insert_data['refund_ship_type']		= $aParams['refund_ship_type'];
		$insert_data['return_shipping_price']	= ($insert_data['refund_ship_type']) ? $total_pay_shipping : 0;			// post.pay_shiping_cost sum > total_pay_shipping

		$trans = true;		// 트랜잭션 처리
		$return_code = $this->returnmodel->insert_return($insert_data,$items,$trans);
		if( $return_code === FALSE) {
			$rollback = true;
		}

		if ($this->db->trans_status() === FALSE || $rollback == true)
		{
			//"잠시 후 다시 시도하여주십시오.<br/>오류가 계속 될 경우 고객센터로 문의하세요."
		    $this->db->trans_rollback();
		    openDialogAlert(getAlert('mb178'),400,140,'parent','');
			exit;
		}
		else
		{
		    $this->db->trans_commit();
		}

		if($aParams['mode']=='exchange'){
			//맞교환 신청이 완료되었습니다.
			$title=getAlert('mo095');
			$logTitle = "맞교환신청(".$return_code.")";
			$logDetail = "주문자가 맞교환신청을 하였습니다.";
		}else{
			//반품 신청이 완료되었습니다.
			$title=getAlert('mo096');
			$logTitle = "반품신청(".$return_code.")";
			$logDetail = "주문자가 반품신청을 하였습니다.";
		}

		if($cancel_type){//1개이상 청약철회상품
			$logDetail .= ", ".number_format($cancel_type)."건의 청약철회 상품은 제외되었습니다.";
//				$title .= "<br/> ".number_format($cancel_type)."건의 청약철회 상품은 제외되었습니다.";
			$title .= getAlert('mo097',number_format($cancel_type));
		}

		if($socialcp_return_use){//1개이상 티켓상품
			$logDetail .= ", ".number_format($socialcp_return_use)."건의 티켓상품은 제외되었습니다.";
//				$title .= "<br/> ".number_format($socialcp_return_use)."건의 티켓상품은 제외되었습니다.";
			$title .= getAlert('mo098',number_format($socialcp_return_use));
		}


		$logParams	= array('return_code' => $return_code);
		$this->ordermodel->set_log($aParams['order_seq'],'process','주문자',$logTitle,$logDetail,$logParams);

		$aParams['shipping_price_bank_account']	= str_replace("'",'',$aParams['shipping_price_bank_account']);
		$aParams['shipping_price_depositor']		= str_replace("'",'',$aParams['shipping_price_depositor']);

		if($aParams['use_layout']){
			$script = "
				<script>
					alert('{$title}');
					parent.document.location.replace('../mypage/order_view?no={$aParams['order_seq']}');
				</script>
			";
		}else if($this->mobileMode){
			$script = "
				<script>
				alert('{$title}');
				parent.document.location.reload();
				</script>
			";
		}else{
			$script = "
			<script>
			parent.$('#order_return_msg .shipping_price_bank_account').html('".$aParams['shipping_price_bank_account']."');
			parent.$('#order_return_msg .shipping_price_depositor').html('".$aParams['shipping_price_depositor']."');
			parent.closeDialog('order_refund_layer');
			parent.openDialog('{$title}', 'order_return_msg', {width:340});
			</script>
			";
		}
		echo $script;
	}

	//티켓상품 반품 or 맞교환 -> 환불
	public function order_return_coupon(){
		$this->load->model('returnmodel');
		$this->load->model('exportmodel');
		$this->load->helper('order');

		$cfg_order = config_load('order');

		if(!$_POST['chk_seq']){
			//환불 신청할 상품을 선택해주세요.
			openDialogAlert(getAlert('mo039'),400,140,'parent');
			exit;
		}

		//휴대폰
		if(is_array($_POST['cellphone'])) $this->validation->set_rules('cellphone[]', getAlert('mo040'),'trim|required|numeric|max_length[4]|xss_clean');
		//휴대폰
		else $this->validation->set_rules('cellphone', getAlert('mo040'),'trim|required|max_length[14]|xss_clean');

		if($_POST['return_method'] == 'shop'){
			if($_POST['return_recipient_new_zipcode']){
				//우편번호
				$this->validation->set_rules('return_recipient_new_zipcode', getAlert('mo041'),'trim|required|numeric|max_length[7]|xss_clean');
			}else{
				//우편번호
				$this->validation->set_rules('return_recipient_zipcode[]', getAlert('mo041'),'trim|required|numeric|max_length[7]|xss_clean');
			}
			//주소
			$this->validation->set_rules('return_recipient_address', getAlert('mo042'),'trim|required|xss_clean');
			//상세주소
			$this->validation->set_rules('return_recipient_address_detail', getAlert('mo043'),'trim|required|xss_clean');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$data_order = $this->ordermodel->get_order($_POST['order_seq']);
		$data_order_items 		= $this->ordermodel->get_item($_POST['order_seq']);
		if( !in_array($data_order['step'],array('40','45','50','55','60','65','70','75')) ){
			//"[티켓상품] ".$this->arr_step[$data_order['step']]."에서는 환불신청을 하실 수 없습니다."
			openDialogAlert(getAlert('mo044',$this->arr_step[$data_order['step']]),400,140,'parent');
			exit;
		}
		$order_total_ea = $this->ordermodel->get_order_total_ea($_POST['order_seq']);

		$cancel_total_ea = 0;
		foreach ($_POST['chk_ea'] as $k => $chk_ea ){

			if($chk_ea == 0){
				//[티켓상품] 환불금액이 0원일때에는 환불신청을 하실 수 없습니다.<br />고객센터에 문의해 주세요.
				openDialogAlert(getAlert('mo045',get_currency_price(0,2)),420,160,'parent');
				exit;
			}

			$export_code		= $_POST['chk_export_code'][$k];
			$item_seq			= $_POST['chk_item_seq'][$k];
			$option_seq			= $_POST['chk_option_seq'][$k];
			$suboption_seq		= $_POST['chk_suboption_seq'][$k];
			$able_return_ea		= 0;

			## 출고수량
			$exp_data			= $this->exportmodel->get_export_item_ea($export_code,$option_seq,$suboption_seq);

			## 반품수량
			if(!$suboption_seq) 
				$return_item = $this->returnmodel->get_return_item_ea($item_seq,$option_seq,$export_code);
			else 
				$return_item = $this->returnmodel->get_return_subitem_ea($item_seq,$suboption_seq,$export_code);

			$able_return_ea	= $exp_data['ea'] - $return_item['ea'];

			if($able_return_ea <= 0){
				//반품 가능한 상품이 없습니다.
				openDialogAlert(getAlert('mo046'),400,140,'parent');
				exit;
			}

			if($able_return_ea < $chk_ea){
				//반품이 가능한 상품이 없습니다.
				openDialogAlert(getAlert('mo047'),400,140,'parent');
				exit;
			}
			$cancel_total_ea += $chk_ea;//티켓상품은 출고갯수만큼
		}

		$export_codes = array();
		foreach($_POST['chk_export_code'] as $k => $chk_export_code) {

			$cancelquery = "select * from fm_order_item where item_seq=?";
			$cancelquery = $this->db->query($cancelquery,array($_POST['chk_item_seq'][$k]));
			$orditemData = $cancelquery->row_array();

			if($_POST['chk_option_seq'][$k] && !$_POST['chk_suboption_seq'][$k]) {

				//티켓상품의 취소(환불) 가능여부::환불
				if ( $orditemData['goods_kind'] == 'coupon') {
					$query = "select * from fm_order_item_option where item_option_seq=?";
					$query = $this->db->query($query,array($_POST['chk_option_seq'][$k]));
					$optionData = $query->row_array();

					$export_itemquery = "select * from fm_goods_export_item where export_code=? limit 1";
					$export_itemquery = $this->db->query($export_itemquery,array($_POST['chk_export_code'][$k]));
					$export_item_Data = $export_itemquery->row_array();

					if( date("Ymd")>substr(str_replace("-","",$optionData['social_end_date']),0,8) ) {//유효기간 종료 후 마일리지환불 신청가능여부

						if( $orditemData['socialcp_use_return'] == 1 ) {//미사용티켓상품 환불대상
							if( order_socialcp_cancel_return($orditemData['socialcp_use_return'], $export_item_Data['coupon_value'], $export_item_Data['coupon_remain_value'], $optionData['social_start_date'], $optionData['social_end_date'] , $orditemData['socialcp_use_emoney_day'] ) === true ) {//미사용티켓상품여부 잔여값어치합계
								if(!in_array($chk_export_code,$export_codes)) $export_codes[] = $chk_export_code;
							}
						}
					}else{//유효기간 이전
						if( $export_item_Data['coupon_remain_value'] >0 ) {//잔여값어치가 남아있을때에만..
							if( $export_item_Data['coupon_value'] != $export_item_Data['coupon_remain_value']  && $orditemData['socialcp_cancel_use_refund'] == '1' ) {
								//부분 사용한 티켓상품은 취소(환불) 불가 @2014-10-07
								continue;
							}else{
								list($data['socialcp_refund_use'], $data['socialcp_refund_cancel_percent']) = order_socialcp_cancel_refund(
									$_POST['order_seq'],
									$orditemData['item_seq'],
									$data_order['deposit_date'],
									$optionData['social_start_date'],
									$optionData['social_end_date'],
									$orditemData['socialcp_cancel_payoption'],
									$orditemData['socialcp_cancel_payoption_percent']
								);//취소(환불) 가능여부

								if( $data['socialcp_refund_use'] === true ) {//취소(환불) 100% 또는 XX% 공제
									if(!in_array($chk_export_code,$export_codes)) $export_codes[] = $chk_export_code;
								}
							}
						}
					}
				}
			}
		}

		// 환불 등록
		if($_POST['bank']){
			$tmp = code_load('bankCode',$_POST['bank']);
			$bank = $tmp[0]['value'];
		}

		$account = is_array($_POST['account']) ? implode('-',$_POST['account']) : $_POST['account'];

        $_POST['refund_method'] = ($_POST['refund_method'])?$_POST['refund_method']:(($data_order['payment'])?$data_order['payment']:'bank');

		$items = array();

		$cancel_type=0;//청약철회상품체크
		$socialcp_return_use=0;//티켓상품

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

		foreach($_POST['chk_seq'] as $k=>$v) {
			$cancelquery = "select * from fm_order_item where item_seq=?";
			$cancelquery = $this->db->query($cancelquery,array($_POST['chk_item_seq'][$k]));
			$orditemData = $cancelquery->row_array();

			//청약철회상품체크
			$goodscanceltype = $this->goodsmodel->get_goods($orditemData['goods_seq']);
			if( $goodscanceltype['cancel_type']) {//청약철회상품 반품불가
				$cancel_type++;
				continue;
			}

			$items[$k]['item_seq']			= $_POST['chk_item_seq'][$k];
			$items[$k]['option_seq']		= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
			$items[$k]['suboption_seq']		= $_POST['chk_suboption_seq'][$k];
			$items[$k]['ea']				= 1;//$_POST['chk_ea'][$k];
			$items[$k]['partner_return']	= true;

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
					$coupon_remain_real_percent = $coupon_remain_real_price = $coupon_remain_price = $coupon_deduction_price = 0;

					$socialcoupon++;

					if( date("Ymd")>substr(str_replace("-","",$optionData['social_end_date']),0,8)) {//유효기간 종료 후 구매금액 % 환불 @2014-10-07

						if( $export_item_Data['coupon_value'] == $export_item_Data['coupon_remain_value'] ) {//값어치 전체미사용
							$socialcp_status = '8';
						}else{//값어치 일부사용
							$socialcp_status = '9';
						}

						if( $orditemData['socialcp_use_return'] == 1 ) {//미사용티켓상품 환불대상

							if( order_socialcp_cancel_return($orditemData['socialcp_use_return'], $export_item_Data['coupon_value'], $export_item_Data['coupon_remain_value'], $optionData['social_start_date'], $optionData['social_end_date'] , $orditemData['socialcp_use_emoney_day'] ) === true ) {//미사용티켓상품여부 구매금액 % 환불 @2014-10-07
								$items[$k]['coupon_refund_type']		= 'price';
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
								$coupon_refund_emoney		= $coupon_remain_price;//이전스킨적용

								$coupon_valid_over++;//유효기가긴지난
							}else{//불가
								$socialcp_return_use++;
								unset($items[$k]);
								continue;
							}

						}else{//불가
							$socialcp_return_use++;
							unset($items[$k]);
							continue;
						}
					}else{//유효기간 이전

						if( $export_item_Data['coupon_value'] == $export_item_Data['coupon_remain_value'] ) {//값어치 전체미사용
							$socialcp_status = '6';
						}else{//값어치 일부사용
							$socialcp_status = '7';
						}

						if( $export_item_Data['coupon_remain_value'] >0 ) {//구매금액 % 환불 @2014-10-07
							if( $export_item_Data['coupon_value'] != $export_item_Data['coupon_remain_value']    && $orditemData['socialcp_cancel_use_refund'] == '1' ) {
								//부분 사용한 티켓상품은 취소(환불) 불가 @2014-10-07
								$socialcp_return_use++;
								unset($items[$k]);
								continue;
							}else{
								list($export_item_Data['socialcp_refund_use'], $export_item_Data['socialcp_refund_cancel_percent']) = order_socialcp_cancel_refund(
									$data_order['order_seq'],
									$_POST['chk_item_seq'][$k],
									$data_order['deposit_date'],
									$optionData['social_start_date'],
									$optionData['social_end_date'],
									$orditemData['socialcp_cancel_payoption'],
									$orditemData['socialcp_cancel_payoption_percent']
								);

								if( $export_item_Data['socialcp_refund_use'] === true ) {//취소(환불)
									if( $export_item_Data['coupon_value'] == $export_item_Data['coupon_remain_value'] ){//전체체크 미사용
										//실제결제금액
										$coupon_remain_price			= (int) ($export_item_Data['socialcp_refund_cancel_percent'] * $coupon_real_total_price / 100);
										$coupon_deduction_price	= (int) $coupon_real_total_price - $coupon_remain_price;
										$coupon_remain_real_percent = "100";
										$coupon_remain_real_price = $coupon_real_total_price;
										$cancel_total_price  += $coupon_remain_price;//취소총금액
									}else{
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

									$items[$k]['coupon_refund_type']		= 'price';
								}else{//불가
									$socialcp_return_use++;
									unset($items[$k]);
									continue;
								}
							}
						}else{
							$socialcp_return_use++;
							unset($items[$k]);
							continue;
						}
					}

					$cancel_memo = socialcp_cancel_memo($export_item_Data, $coupon_remain_real_percent, $coupon_real_total_price, $coupon_remain_real_price, $coupon_remain_price, $coupon_deduction_price);
					//debug_var($coupon_remain_price.' , '.$coupon_deduction_price.' , '.$coupon_refund_emoney);

					$items[$k]['coupon_refund_emoney']			= $coupon_refund_emoney;//티켓상품 잔여 값어치의 실제금액
					$items[$k]['coupon_remain_price']			= $coupon_remain_price;//티켓상품 결제금액의 실제금액
					$items[$k]['coupon_deduction_price']		= $coupon_deduction_price;//티켓상품 결제금액의 공제금액
					$items[$k]['coupon_deduction_price']		= $coupon_deduction_price;//티켓상품 결제금액의 조정금액
					$items[$k]['refund_goods_price']			= $coupon_remain_real_price;//티켓상품 환불금액
					$items[$k]['coupon_remain_real_percent']	= $coupon_remain_real_percent;//티켓상품 사용비율
					$items[$k]['coupon_real_value']				= $export_item_Data['coupon_value'];//티켓상품 기준금액OR횟수
					$items[$k]['coupon_remain_real_value']		= $export_item_Data['coupon_remain_value'];//티켓상품 환불금액
					$items[$k]['cancel_memo']					= $cancel_memo;//취소(환불) 상세내역
					//티켓상품 실제 환불금액
					if ( $coupon_real_total_price != $coupon_remain_price) {
						$items[$k]['refund_goods_price']			= $coupon_remain_price;
					} else {
						$items[$k]['refund_goods_price']			= $coupon_remain_real_price;
					}

				}
				//debug_var($items[$k]);exit;

				if($_POST['mode']!='exchange'){
					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_option_seq',$items[$k]['option_seq']);
					$this->db->update('fm_order_item_option');
				}
			}else if($items[$k]['suboption_seq']){
				$mode = 'suboption';

				$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
				$query = $this->db->query($query,array($items[$k]['suboption_seq']));
				$suboptionData = $query->row_array();

				if($_POST['mode']!='exchange'){
					$this->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
					$this->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
					$this->db->update('fm_order_item_suboption');
				}
			}
		}

		//$_POST['refund_method'] = ($coupon_valid_over)?'emoney':$_POST['refund_method'];

		if($_POST['mode']=='exchange'){//맞교환
			$refund_code = '0';
			$return_type = 'exchange';
		}else{//반품
			if( !( ( $cancel_type == count($_POST['chk_seq']) ) || ( $socialcp_return_use == count($_POST['chk_seq']) ) ) ) {

				// 맞교환으로 인한 재주문을 반품신청시 최상위 주문번호 저장
				if($data_order['top_orign_order_seq'])
					$orgin_order_seq = $data_order['top_orign_order_seq'];
				else
					$orgin_order_seq = $_POST['order_seq'];

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
					'refund_method' => $_POST['refund_method']
				);
				$refund_code = $this->refundmodel->insert_refund($data,$items);
				$refund_type = 'return';
				$return_type = 'return';

				$logTitle	= "환불신청(".$refund_code.")";
				$logDetail	= "주문자 반품신청에 의한 환불신청이 접수되었습니다.";
				$logParams	= array('refund_code' => $refund_code);
				$this->ordermodel->set_log($orgin_order_seq,'process','주문자',$logTitle,$logDetail,$logParams);
			}
		}//환불타입(cancel_payment:결제취소,return:반품환불)


		if( $cancel_type == count($_POST['chk_seq']) ) {

			if($_POST['mode']=='exchange'){
				//청약철회 상품으로 맞교환이 [불가능]합니다.
				$title=getAlert('mo049');
				$logTitle = "맞교환신청";
			}else{
				//청약철회 상품으로 반품신청이 [불가능] 합니다.
				$title=getAlert('mo050');
				$logTitle = "반품신청";
			}

			$script = "
				<script>
				alert('{$title}');
				</script>
			";
			echo $script;
		}elseif( ($socialcp_return_use > 0 && $_POST['mode']=='exchange' ) || ( $socialcp_return_use == count($_POST['chk_seq']) ) ) {

			if($_POST['mode']=='exchange'){
				//티켓상품은 맞교환이 [불가능]합니다.
				$title=getAlert('mo051');
				$logTitle = "맞교환신청";
			}else{
				//티켓상품은 반품신청이 [불가능] 합니다.
				$title=getAlert('mo052');
				$logTitle = "반품신청";
			}

			$script = "
				<script>
				alert('{$title}');
				</script>
			";
			echo $script;

		}else{
			if(is_array($_POST['phone']))  $phone = implode('-',$_POST['phone']);
			else  $phone = $_POST['phone'];

			if(is_array($_POST['cellphone'])) $cellphone = implode('-',$_POST['cellphone']);
			else  $cellphone = $_POST['cellphone'];

			$zipcode = "";
			if($_POST['return_recipient_new_zipcode']){
				$zipcode = $_POST['return_recipient_new_zipcode'];
			}else{
				$zipcode = $_POST['return_recipient_zipcode'][0].$_POST['return_recipient_zipcode'][1];
			}

			//티켓상품 반품등록
			$insert_data['status'] 				= 'complete';// 티켓상품은 반품완료처리'request';
			$insert_data['order_seq'] 		= $_POST['order_seq'];
			$insert_data['refund_code'] 	= $refund_code;
			$insert_data['return_type'] 	= $return_type;
			$insert_data['return_reason'] 	= $_POST['reason_detail'];
			$insert_data['cellphone'] 		= $cellphone;
			$insert_data['phone'] 			= (!empty($phone)) ? $phone : '';
			$insert_data['return_method'] 	= $_POST['return_method'];
			$insert_data['sender_zipcode'] 	= $zipcode;
			$insert_data['sender_address_type']		= (($_POST['return_recipient_address_type']))?$_POST['return_recipient_address_type']:"zibun";
			$insert_data['sender_address'] 	= $_POST['return_recipient_address']?$_POST['return_recipient_address']:'';
			$insert_data['sender_address_street']	= $_POST['return_recipient_address_street'];
			$insert_data['sender_address_detail'] = $_POST['return_recipient_address_detail']?$_POST['return_recipient_address_detail']:'';
			$insert_data['regist_date'] 	= date('Y-m-d H:i:s');
			$insert_data['return_date'] = date('Y-m-d H:i:s');
			$insert_data['important'] 		= 0;
			$insert_data['shipping_price_depositor'] 	= $_POST['shipping_price_depositor'];
			$insert_data['shipping_price_bank_account'] = $_POST['shipping_price_bank_account'];

			$items = array();
			foreach($_POST['chk_seq'] as $k=>$v){
				$query = "select * from fm_order_item where item_seq=?";
				$query = $this->db->query($query,array($_POST['chk_item_seq'][$k]));
				$orditemData = $query->row_array();

				//청약철회상품체크
				$goodscanceltype = $this->goodsmodel->get_goods($orditemData['goods_seq']);
				if( $goodscanceltype['cancel_type']) {//청약철회상품 반품불가
					continue;
				}

				if($_POST['chk_option_seq'][$k] && !$_POST['chk_suboption_seq'][$k]) {

					//티켓상품의 취소(환불) 가능여부
					if ( $orditemData['goods_kind'] == 'coupon') {
						$query = "select * from fm_order_item_option where item_option_seq=?";
						$query = $this->db->query($query,array($_POST['chk_option_seq'][$k]));
						$optionData = $query->row_array();

						$export_itemquery = "select * from fm_goods_export_item where export_code=? limit 1";
						$export_itemquery = $this->db->query($export_itemquery,array($_POST['chk_export_code'][$k]));
						$export_item_Data = $export_itemquery->row_array();

						if( date("Ymd")>substr(str_replace("-","",$optionData['social_end_date']),0,8)) {//유효기간 종료 후 잔여값어치합계 마일리지

							if( $orditemData['socialcp_use_return'] == 1 ) {//미사용티켓상품 환불대상
								if( order_socialcp_cancel_return($orditemData['socialcp_use_return'], $export_item_Data['coupon_value'], $export_item_Data['coupon_remain_value'], $optionData['social_start_date'], $optionData['social_end_date'] , $orditemData['socialcp_use_emoney_day'] ) === false ) {//미사용티켓상품여부 잔여값어치합계
									continue;
								}
							}else{//불가
								continue;
							}
						}else{//유효기간 이전
							if( $export_item_Data['coupon_remain_value'] >0 ) {//잔여값어치가 남아있을때에만..
								if( $export_item_Data['coupon_value'] != $export_item_Data['coupon_remain_value'] && $orditemData['socialcp_cancel_use_refund'] == '1' ) {
										//부분 사용한 티켓상품은 취소(환불) 불가 @2014-10-07
										continue;
								}else{
									list($optionData['socialcp_refund_use'], $optionData['socialcp_refund_cancel_percent']) = order_socialcp_cancel_refund(
										$orditemData['order_seq'],
										$_POST['chk_item_seq'][$k],
										$data_order['deposit_date'],
										$optionData['social_start_date'],
										$optionData['social_end_date'],
										$orditemData['socialcp_cancel_payoption'],
										$orditemData['socialcp_cancel_payoption_percent']
									);

									if( $optionData['socialcp_refund_use'] === false ) {//취소(환불)
										continue;
									}
								}
							}else{
								continue;
							}
						}
					}
				}

				$items[$k]['item_seq']			= $_POST['chk_item_seq'][$k];
				$items[$k]['option_seq']			= $_POST['chk_suboption_seq'][$k] ? '' : $_POST['chk_option_seq'][$k];
				$items[$k]['suboption_seq']	= $_POST['chk_suboption_seq'][$k];
				$items[$k]['ea']						= $_POST['chk_ea'][$k];
				$items[$k]['reason_code']		= $_POST['reason'][$k];
				$items[$k]['reason_desc']		= $_POST['reason_desc'][$k];
				$items[$k]['export_code']		= $_POST['chk_export_code'][$k];
				$items[$k]['partner_return']	= true;

			}

			$return_code = $this->returnmodel->insert_return($insert_data,$items);

			$this->load->model('socialcpconfirmmodel');
			foreach($export_codes as $export_code){
				$data_export = $this->exportmodel->get_export($export_code);
				if(in_array($data_export['status'],array('40','45','50','55','60','65','70','75'))){
					unset($data_socialcp_confirm);
					$data_socialcp_confirm['order_seq'] = $data_export['order_seq'];
					$data_socialcp_confirm['export_seq'] = $data_export['export_seq'];
					if($this->userInfo['member_seq']){
						$data_socialcp_confirm['member_seq'] = $this->userInfo['member_seq'];
					}else{
						$data_socialcp_confirm['doer'] = '구매자';
					}
					$this->socialcpconfirmmodel->socialcp_confirm('user',$socialcp_status,$export_code);//socialcp_status = 환불시 상태 6,7,8,9
					$this->socialcpconfirmmodel->log_socialcp_confirm($data_socialcp_confirm);

					// 배송완료(수령확인)처리
					$this->exportmodel->socialcp_exec_complete_delivery($export_code, true, $coupon_remain_real_percent, "", "cancel");
				}
			}
			
			/* 신용카드 자동취소 전체취소 start */
			if( ($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $data_order['settleprice']==$_POST['cancel_total_price'] && $order_total_ea == $cancel_total_ea)
			{
			    $pgCompany = $this->config_system['pgCompany'];
			    
			    // 카카오 페이의 PG사를 추출하기 위한 데이터 :: 2015-02-25 lwh
			    if($data_order['pg']=='kakaopay' || $data_order['pg']=='payco'){
			        $pglog_tmp				= $this->ordermodel->get_pg_log($_POST['order_seq']);
			        $pg_log_data			= $pglog_tmp[0];
			        $data_order['pg_log']	= $pg_log_data;
			        $pgCompany				= $data_order['pg'];
			    }
			    
			    /* PG */
			    $cancelFunction = "{$pgCompany}_cancel";
			    $cancelResult = $this->refundmodel->$cancelFunction($data_order,array('refund_reason'=>$_POST['reason_detail'],'cancel_type'=>'full'));
			    
			    if(!$cancelResult['success']){
			        //{$pgCompany} 결제 취소 실패<br /><font color=red>{$cancelResult['result_code']} : {$cancelResult['result_msg']}</font>
			        openDialogAlert(getAlert('mo048',array($pgCompany,$cancelResult['result_code'],$cancelResult['result_msg'])),400,160,'parent','');
			        exit;
			    }
			    $_POST['cancel_type'] = 'full';
			}
			/* 신용카드 자동취소 전체취소 end */

			/* 신용카드 자동취소 */
			if(($data_order['payment']=='card' || $data_order['payment']=='kakaomoney' || $data_order['pg']=='payco' ) && $data_order['settleprice']==$cancel_total_price) {
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
								'emoney'	=> get_cutting_price($data_order['emoney']),
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
								'cash'		=> get_cutting_price($data_order['cash']),
								'ordno'		=> $data_order['order_seq'],
								'memo'		=> "[복원]주문환불({$refund_code})에 의한 예치금 환원",
								'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp247",$refund_code), // [복원] 주문환불(%s)에 의한 예치금 환원
							);
							$this->membermodel->cash_insert($params, $data_order['member_seq']);
							$this->ordermodel->set_cash_use($data_order['order_seq'],'return');
						}

						/* 마일리지 회수 */
						if($_POST['return_reserve'] && $refund_type=='return'){
							$params = array(
								'gb'		=> 'minus',
								'type'		=> 'refund',
								'emoney'	=> get_cutting_price($_POST['return_reserve']),
								'ordno'		=> $data_order['order_seq'],
								'memo'		=> "[차감] 주문환불({$data_order['order_seq']})에 의하여 배송완료시 지급된 마일리지 차감",
								'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp258",$data_order['order_seq']), // [차감] 주문환불(%s)에 의하여 배송완료시 지급된 마일리지 차감
							);
							$this->membermodel->emoney_insert($params, $data_order['member_seq']);
						}

						/* 포인트 회수 */
						if($_POST['return_point'] && $refund_type=='return'){
							$params = array(
								'gb'		=> 'minus',
								'type'		=> 'refund',
								'point'		=> get_cutting_price($_POST['return_point']),
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
					$logDetail = "주문자가 환불완료처리를 하였습니다.";
					$logParams	= array('refund_code' => $refund_code);
					$this->ordermodel->set_log($data_order['order_seq'],'process',"주문자",$logTitle,$logDetail,$logParams);
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

					 //통합정산 생성(미정산매출 환불건수 업데이트)
					//정산대상 수량업데이트
					$this->accountallmodel->update_calculate_sales_ac_ea($data_order['order_seq'],$refund_code, 'refund', $data_refund_item);
					//정산확정 처리
					 $this->accountallmodel->insert_calculate_sales_order_refund($data_order['order_seq'], $refund_code, $data_refund['cancel_type'], $data_order, $data_refund, $data_refund_item);
					 //debug_var($this->db->queries);
					 //debug_var($this->db->query_times);
					 /**
					 * 4-2 환불데이타를 이용한 통합정산테이블 생성 시작 끝
					 * @
					 **/

					$callback = "
					parent.closeDialog('order_refund_layer');
					parent.document.location.reload();
					";

					//신용카드 결제취소가 완료되었습니다.
					$title=getAlert('mo053');

					/**
					* 티켓상품 신용카드 자동취소 end
					**/
			}else{
				if($_POST['mode']=='exchange'){
					//맞교환 신청이 완료되었습니다.
					$title=getAlert('mo054');
					$logTitle = "맞교환신청(".$return_code.")";
					$logDetail = "주문자가 맞교환신청을 하였습니다.";
				}else{
					//반품이 완료되었습니다.
					$title=getAlert('mo055');
					$logTitle = "반품완료(".$return_code.")";
					$logDetail = "주문자가 반품완료을 하였습니다.";
				}

				if($cancel_type){//1개이상 청약철회상품
					$logDetail .= ", ".number_format($cancel_type)."건의 청약철회 상품은 제외되었습니다.";
//					$title .= "<br/> ".number_format($cancel_type)."건의 청약철회 상품은 제외되었습니다.";
					$title .= getAlert('mo056',number_format($cancel_type));
				}

				if($socialcp_return_use){//1개이상 티켓상품
					$logDetail .= ", ".number_format($socialcp_return_use)."건의 티켓상품은 제외되었습니다.";
//					$title .= "<br/> ".number_format($socialcp_return_use)."건의 티켓상품은 제외되었습니다.";
					$title .= getAlert('mo057',number_format($socialcp_return_use));
				}


				$logParams	= array('return_code' => $return_code);
				$this->ordermodel->set_log($_POST['order_seq'],'process','주문자',$logTitle,$logDetail,$logParams);

				$_POST['shipping_price_bank_account']	= str_replace("'",'',$_POST['shipping_price_bank_account']);
				$_POST['shipping_price_depositor']		= str_replace("'",'',$_POST['shipping_price_depositor']);
			}

			if($_POST['use_layout']){
				$script = "
					<script>
						alert('{$title}');
						parent.document.location.replace('../mypage/order_view?no={$_POST['order_seq']}');
					</script>
				";
			}else if($this->mobileMode){
				$script = "
					<script>
					alert('{$title}');
					parent.document.location.reload();
					</script>
				";
			}else{
				$script = "
					<script>
					alert('{$title}');
					parent.document.location.reload();
					</script>
				";
			}
			echo $script;
		}


	}


	public function order_pg_info()
	{

		$pg = config_load($this->config_system['pgCompany']);
		$order 			= $this->ordermodel->get_order($_POST['order_seq']);
		if( $this->config_system['pgCompany'] == 'lg' ) {
			// 가상계좌도 별도로 현금영수증 신청하기 때문에 모두 cr 로 요청함 2018-06-14
			$tax_bank	= 'cr';

			$authdata	= md5($pg['mallCode'] . $order['pg_transaction_number'] . $pg['merchantKey']);
			$cst_platform	= 'service';
			$return = array('result'=>true,'tax_bank'=>$tax_bank,'authdata'=>$authdata,'cst_platform'=>$cst_platform);

			echo json_encode($return);
			exit;
		}else{
			$return = array('result'=>true);
			echo json_encode($return);
			exit;
		}
	}

	public function order_auth()
	{
		$this->load->model('ssl');
		$this->ssl->decode();

		### Validation
		//주문번호
		$this->validation->set_rules('order_seq', getAlert('mo058'),'trim|required|xss_clean');
		//주문 메일
		$this->validation->set_rules('order_email', getAlert('mo059'),'trim|required|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		### QUERY
		$where_arr = array('order_seq'=>$_POST['order_seq'], 'order_email'=>$_POST['order_email'], '(member_seq is null OR member_seq = \'\')'=> NULL);
		$data = get_data('fm_order', $where_arr);

		if( (strstr(urldecode($_POST['return_url']),"/board/write") || strstr(urldecode($_POST['return_url']),"goods/review_write") || strstr(urldecode($_POST['return_url']),"/mypage/mygdreview_write") ) && $data) {
			$goods_seq = @explode("goodsseq=",urldecode($_POST['return_url']));
			$itemwhere_arr = array('order_seq'=>$_POST['order_seq'], 'goods_seq'=>$goods_seq[1]);
			$itemdata = get_data('fm_order_item', $itemwhere_arr);
			if(!$itemdata) unset($data);
		}
		if(!$data){
			$callback = "if(parent.document.getElementsByName('order_seq')[0]) parent.document.getElementsByName('order_seq')[0].focus();";
			//일치하는 주문 정보가 없습니다.
			openDialogAlert(getAlert('mo060'),400,140,'parent',$callback);
			exit;
		}

		### SESSION
		$_SESSION['sess_order'] = $data[0]['order_seq'];
		$this->session->set_userdata(array('sess_order'=>$data[0]['order_seq']));
		if(strstr(urldecode($_POST['return_url']),"/board/write") || strstr(urldecode($_POST['return_url']),"goods/review_write") || strstr(urldecode($_POST['return_url']),"/mypage/mygdreview_write") ) {
			echo js("parent.opener.gdordersearch();parent.self.close();");//상품후기 >> 비회원 주문검색시 새창접근
		}else{
			### PAGE MOVE
			pageRedirect('/mypage/order_view','','parent');
		}
	}

	public function cancel(){
		$order_seq = $_GET['order_seq'];
		$this->load->model('ordermodel');
		$orders		= $this->ordermodel->get_order($order_seq);
		if( $orders['member_seq'] != $this->userInfo['member_seq']  ){
			//자신의 주문만 주문무효를 하실 수 있습니다.
			openDialogAlert(getAlert('mo026'),400,140,'parent',"");
			exit;
		}

		if( !in_array($orders['step'],$this->ordermodel->able_step_action['cancel_order']) ){
			//$this->arr_step[$orders['step']]."에서는 주문무효를 하실 수 없습니다."
			openDialogAlert(getAlert('mo027',$this->arr_step[$orders['step']]),400,140,'parent',"");
			exit;
		}

		/* 프로모션환원 */
		$this->load->model('couponmodel');
		$this->load->model('promotionmodel');

		$options	= $this->ordermodel->get_item_option($order_seq);
		$suboptions	= $this->ordermodel->get_item_suboption($order_seq);
		$r_reservation_goods_seq = array();

		if($options) foreach($options as $k => $option){

			//청약철회상품체크
			$goods = $this->goodsmodel->get_goods($option['goods_seq']);
			if($goods['cancel_type'] && !in_array($option['step'],$this->ordermodel->able_step_action['canceltype_cancel_order']) ) {
				$cancel_type_cnt++;
				continue;//청약철회상품 주문취소불가
			}

			$tot_ea		+= $option['ea'];

			// 출고량 업데이트를 위한 변수정의
			if(!in_array($option['goods_seq'],$r_reservation_goods_seq)){
				$r_reservation_goods_seq[] = $option['goods_seq'];
			}


			//상품별 할인쿠폰/프로모션코드 복원
			if($option['download_seq'] && $option['coupon_sale']) $goodscoupon = $this->couponmodel->restore_used_coupon($option['download_seq']);
			if($option['promotion_code_seq'] && $option['promotion_code_sale']) $goodspromotioncode = $this->promotionmodel->restore_used_promotion($option['promotion_code_seq']);
		}
		if($suboptions) foreach($suboptions as $k => $option){

			//청약철회상품체크
			$goods = $this->goodsmodel->get_goods($option['goods_seq']);
			if($goods['cancel_type']  && !in_array($option['step'],$this->ordermodel->able_step_action['canceltype_cancel_order']) ) {
				$cancel_type_cnt++;
				continue;//청약철회상품 주문취소불가
			}

			$tot_ea		+= $option['ea'];
		}

		if($cancel_type_cnt && $cancel_type_cnt == (count($options)+count($options))  ) {//청약철회상품있으면서 주문상품수와 동일한 경우
			//[청약철회불가]상품으로 주문무효가 불가능합니다.
			openDialogAlert(getAlert('mo028'),400,140,'parent',"parent.location.reload();");
		}else{
			///청약철회상품없는경우
			$this->ordermodel->set_step($order_seq,95);

			/* 배송비할인쿠폰 복원*/
			$shipping_coupon_seq	= $this->couponmodel->get_shipping_coupon($orders['order_seq']);
			if($shipping_coupon_seq){
				$shippingcoupon = $this->couponmodel->restore_used_coupon($shipping_coupon_seq);
			}
			// 주문서쿠폰 복원
			if($orders['ordersheet_seq']){
				$ordersheetcoupon = $this->couponmodel->restore_used_coupon($orders['ordersheet_seq']);
			}

			/* 배송비프로모션코드 복원 개별코드만 */
			if( $orders['shipping_promotion_code_seq'] ){
				$shippingpromotioncode = $this->promotionmodel->restore_used_promotion($orders['shipping_promotion_code_seq']);
			}

			$this->load->model('membermodel');
			/* 마일리지 환원 */
			if($orders['emoney_use']=='use' && $orders['emoney'])
			{
				$params = array(
					'gb'		=> 'plus',
					'type'		=> 'cancel',
					'emoney'	=> get_cutting_price($orders['emoney']),
					'ordno'		=> $order_seq,
					'memo'		=> "[복원]주문무효({$order_seq})에 의한 마일리지 환원",
					'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp252",$order_seq), // [복원]주문무효(%s)에 의한 마일리지 환원
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
					'cash'		=> get_cutting_price($orders['cash']),
					'ordno'		=> $order_seq,
					'memo'		=> "[복원]".$this->arr_step[95]."(".$order_seq.")에 의한 예치금 환원",
					'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp253",$order_seq), // [복원]주문무효(%s)에 의한 예치금 환원
				);
				$this->membermodel->cash_insert($params, $orders['member_seq']);
				$this->ordermodel->set_cash_use($order_seq,'return');
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
			$this->ordermodel->set_log($order_seq,'cancel','주문자','주문무효',$log,$caccel_arr);
			//[청약철회불가] 상품을 제외한 주문무효가 완료되었습니다.
			openDialogAlert(getAlert('mo029'),400,140,'parent',"parent.location.reload();");
		}
	}

	public function recipient(){
		$this->load->library('orderlibrary');
		$this->load->library('change_recipient_address');
		// reset
		$ship_possible = 'N';

		$this->load->library('shipping');
		$this->load->model('ordermodel');
		$order_seq = $this->input->post('order_seq');
		$mode			= $_POST['mode']; // json, null

		if(!$this->userInfo['member_seq']){//비회원주문확인 후 배송지 정보저장시@2013-12-06
			$order_seq = $this->session->userdata('sess_order');
			if(!$order_seq) {
				if($mode == 'json') {
					echo json_encode(array("state"=>"999", "location"=>"/member/login?order_auth=1"));
				} else {
					redirect("/member/login?order_auth=1");
				}
				exit;
			}
			$orders 			= $this->ordermodel->get_order($order_seq);
			$international = $orders['international'];
		}else{
			$orders		= $this->ordermodel->get_order($order_seq);
			$international = $orders['international'];

			if( $orders['member_seq'] != $this->userInfo['member_seq']  ){
				//자신의 주문만 정보를 변경 하실 수 없습니다.
				if($mode == 'json') {
					echo json_encode(array("state"=>"999", "error_message"=>getAlert('mo007')));
				} else {
					openDialogAlert(getAlert('mo007'),400,140,'parent',"");
				}
				exit;
			}
		}
		// 배송지 변경 시 유효성 검사
		$this->orderlibrary->validation_change_order_recipient($orders);

		$recipient_data = [
			'recipient_address' => $this->input->post('recipient_address'),
			'order_seq' => $order_seq,
		];
		
		$isChangeShippingCost = $this->change_recipient_address->isChangeShippingCost($recipient_data);

		// 변경된 배송지의 배송비 변경 여부 체크
		if ($isChangeShippingCost === true) {
			openDialogAlert(getAlert('mp302'), 400, 140, 'parent');
			exit;
		}

		// 선물하기 배송지는 수정 불가
		if(is_order_present($orders)) {
			openDialogAlert(getAlert('mp022'),400,140,'parent','parent.location.reload()');
			exit;
		}

		// 상품별 배송메세지 변경
		$each_memo = $_POST['each_memo'];

		if(is_array($each_memo)) {
			foreach($each_memo as $item_option_seq=>$ship_message) {
				$this->db->where('item_option_seq', $item_option_seq);
				$this->db->update('fm_order_item_option', array('ship_message'=>$ship_message));
			}
		}

		// 배송지 변경
		$result = $this->orderlibrary->change_order_recipient($orders,$this->input->post());
		//배송지 정보가 변경 되었습니다.
		if($mode == 'json') {
			echo json_encode(array("state"=>"0", "message" => getAlert('mo019')));
		} else {
			openDialogAlert(getAlert('mo019'),400,140,'parent','typeof parent.callback_recipient == "function" && parent.callback_recipient();');
		}
	}

	/**
	 * 선물하기 수신인 배송지 등록
	 */
	public function recipient_present() {
		$this->load->library('orderlibrary');
		$order_seq = $this->input->post('order_seq');
		$orders 			= $this->ordermodel->get_order($order_seq);

		//최초 등록 시에만 인증 여부 검사
		if(!has_recipient_zipcode($orders)) {
			$present_delivery = $this->session->userdata('present_delivery');
			if(!(isset($present_delivery[$order_seq]) && $present_delivery[$order_seq] == $orders['recipient_cellphone'])) {
				// 휴대폰 번호로 인증을 완료해주세요.
				openDialogAlert(getAlert('mo162'),400,140,'parent','');
				exit;
			}
		}

		// 배송지 변경 시 유효성 검사
		$this->orderlibrary->validation_change_order_recipient($orders);

		$data = $this->input->post();
		$data['actor'] = 'present';
		// 배송지 변경
		$result = $this->orderlibrary->change_order_recipient($orders,$data);
		if($result) {
			// 최초 배송지 등록 시 관리자 메일,sms,push 발송
			if(!has_recipient_zipcode($orders)) {
				$this->orderlibrary->first_regist_recipient_zipcode($orders);
			}
			//선물 받으실 배송지 주소가 등록되었습니다.
			openDialogAlert(getAlert('mo163'),400,140,'parent','parent.location.reload()');
			exit;
		} else {
			//잘못된 접근입니다.
			openDialogAlert(getAlert('mp022'),400,140,'parent','parent.location.reload()');
			exit;
		}
	}

	public function delivery_address(){

		//5자리 우편번호예외 처리
		if($_POST['check_new_zipcode'] == 'NEW'){
			$_POST['recipient_address_type']	= ($_POST['recipient_address_type'] == 'oldzibun') ? 'zibun' : 'street';
		}else{
			$_POST['recipient_address_type']	= ($_POST['recipient_address_type'] == 'oldzibun') ? 'zibun' : $_POST['recipient_address_type'];
		}

		$this->load->model('ordermodel');
		//배송지 설명
		$this->validation->set_rules('address_description', getAlert('mp002'),'trim|required|max_length[20]|xss_clean');
		$mode = $_POST['insert_mode'];
		$_POST['international'] = ($_POST['nation_select'] == 'KOREA') ? 0 : 1;
		$_POST['international_country'] = $_POST['nation_select'];
		$_POST['address_nation'] = $_POST['nation_select'];

		if( isset($_POST['international']) ){
			//받는이
			$this->validation->set_rules('recipient_user_name', getAlert('mp003'),'trim|required|max_length[20]|xss_clean');
			// 국내 배송일 경우
			if($_POST['international'] == 0){
				if($_POST['recipient_new_zipcode']){
					//우편번호
					$this->validation->set_rules('recipient_new_zipcode', getAlert('mp005'),'trim|required|max_length[7]|xss_clean');
				}else{
					$recipient_zipcode1			= $_POST['recipient_zipcode'][0];
					$recipient_zipcode2			= $_POST['recipient_zipcode'][1];
					//구스킨오류로 첫번째 5자리와 두번째값이 없으면 도로명주소로 간주하여 체크하지 않습니다. @2016-10-21
					if( $recipient_zipcode1 && !(strlen($recipient_zipcode1) == 5 || strlen($recipient_zipcode1) == 6) ) {
					//우편번호
					$this->validation->set_rules('recipient_zipcode[]', getAlert('mp005'),'trim|required|max_length[7]|xss_clean');
					}
				}
				//주소
				$this->validation->set_rules('recipient_address', getAlert('mp006'),'trim|max_length[255]|required|xss_clean');
				//나머지주소
				$this->validation->set_rules('recipient_address_detail', getAlert('mp007'),'trim|max_length[255]|required|xss_clean');
				//받는이 유선전화
				$this->validation->set_rules('recipient_phone[]', getAlert('mp008'),'trim|max_length[4]|xss_clean');
				//받는이 핸드폰
				$this->validation->set_rules('recipient_cellphone[]', getAlert('mp009'),'trim|numeric|max_length[4]|required|xss_clean');
			}else if($_POST['international'] == 1){
				// 연락처 정보 해외용 추출
				$_POST['international_recipient_phone']		= !$_POST['international_recipient_phone'] ? $_POST['recipient_phone'] : $_POST['international_recipient_phone'];
				$_POST['international_recipient_cellphone'] = !$_POST['international_recipient_cellphone'] ? $_POST['recipient_cellphone'] : $_POST['international_recipient_cellphone'];

				//주소
				$this->validation->set_rules('international_address', getAlert('mp006'),'trim|max_length[255]|required|xss_clean');
				//시
				$this->validation->set_rules('international_town_city', getAlert('mp011'),'trim|max_length[45]|required|xss_clean');
				//주
				$this->validation->set_rules('international_county', getAlert('mp012'),'trim|max_length[20]|required|xss_clean');
				//우편번호
				$this->validation->set_rules('international_postcode', getAlert('mp005'),'trim|max_length[20]|required|xss_clean');
				//국가
				$this->validation->set_rules('international_country', getAlert('mp013'),'trim|max_length[45]|required|xss_clean');
				//받는이 유선전화
				$this->validation->set_rules('international_recipient_phone[]', getAlert('mp008'),'trim|max_length[10]|xss_clean');
				//받는이 핸드폰
				$this->validation->set_rules('international_recipient_cellphone[]', getAlert('mp009'),'trim|numeric|max_length[10]|xss_clean');
			}
		}
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		// 주문페이지에서는 주소록을 스크립트로 재로드
		if($_POST['page_type'] == 'order'){
			$callback = "parent.closeDialog('inAddress');parent.popDeliveryaddress('reload');";
		}else{
			$callback = "parent.document.location.reload();";
		}

		if($mode == 'insert'){
			//배송지 저장 (로그인한 경우만)
			if($_POST['save_delivery_address']){
				$this->ordermodel->insert_delivery_address('insert');
			}else{
				$this->ordermodel->insert_delivery_address();
			}
			//자주쓰는 배송지가 등록 되었습니다.
			openDialogAlert(getAlert('mp014'),400,140,'parent',$callback);

		}elseif($mode == 'update'){
			$address_seq=$_POST['address_seq'];
			$this->ordermodel->update_delivery_address($address_seq);
			//자주쓰는 배송지가 수정 되었습니다.
			openDialogAlert(getAlert('mp015'),400,140,'parent',$callback);
		}

	}


	public function delete_address(){
		$addres_seq = $this->input->get('address_seq', true);
		$page_type = $this->input->get('page_type', true);
		$this->db->delete('fm_delivery_address', ['address_seq' => $addres_seq]);
		if ($page_type == 'mobile') { // 모바일일경우
			$callback = "parent.delivery_address_ajax('1');";
		} elseif ($page_type == 'order') {
			// 주문페이지에서는 주소록을 스크립트로 재로드
			$callback = "parent.popDeliveryaddress('reload');";
		} else {
			$callback = 'parent.document.location.reload();';
		}
		//배송지가 삭제 되었습니다.
		openDialogAlert(getAlert('mp020'),400,140,'parent',$callback);
	}

	public function change_address(){
		$requestParam = $this->input->get();
		$addres_seq = $requestParam['address_seq'];
		$popup_seq = $requestParam['popup'];

		$this->validation->set_data($requestParam);
		$this->validation->set_rules('address_seq', '배송지 고유 번호', 'trim|numeric|xss_clean');
		if ($this->validation->exec() === false) {
			show_error($this->validation->error_array['value'], 422);
		}

		$key = get_shop_key();
		$this->db->select("*,AES_DECRYPT(UNHEX(recipient_phone), '{$key}') as recipient_phone,
		AES_DECRYPT(UNHEX(recipient_cellphone), '{$key}') as recipient_cellphone");
		$this->db->from("fm_delivery_address");
		$this->db->where("address_seq",$addres_seq);
		$query	= $this->db->get();
		$params = $query->row_array();
		$params['address_seq']='';
		$params['address_description']='-';
		$params['often']='Y';
		$params['lately']='';
		$params['regist_date']				= date('Y-m-d H:i:s');
		$this->db->insert('fm_delivery_address', $params);

		### Private Encrypt
		$cellphone = get_encrypt_qry('recipient_cellphone');
		$phone = get_encrypt_qry('recipient_phone');
		$sql = "update fm_delivery_address set  {$cellphone}, {$phone}, update_date = now() where address_seq = {$this->db->insert_id()}";
		$this->db->query($sql);
		###

		if($requestParam['complete']){
			$callback="";
		}else{
			$callback = "parent.location.href = '/mypage/delivery_address?tab=1&popup=".$popup_seq."'";//$callback = "parent.delivery_address('1');";
		}
		//자주쓰는 배송지에 등록 되었습니다.
		openDialogAlert(getAlert('mp021'),400,140,'parent',$callback);
	}

	public function refund_modify(){
		$refund_code = $_POST['refund_code'];

		/* Check availability */
		$refund_info = $this->refundmodel->get_refund($refund_code);
		switch($refund_info['status']) {
			case 'ing':
				openDialogAlert(getAlert('mo158'),400,140,'parent',"parent.document.location.replace('/mypage/refund_view?refund_code={$refund_code}')");
				return;
			case 'complete':
				openDialogAlert(getAlert('mo159'),400,140,'parent',"parent.document.location.replace('/mypage/refund_view?refund_code={$refund_code}')");
				return;
			case 'request':
			default:
		}
		/* End of Check availability */

		$data = array();
		$data['bank_name'] = $_POST['bank_name'];
		$data['bank_depositor'] = $_POST['bank_depositor'];
		$data['bank_account'] = $_POST['bank_account'];
		$data['refund_reason'] = $_POST['refund_reason'];

		$this->db->where('refund_code', $refund_code);
		$this->db->update('fm_order_refund', $data);
		//환불정보가 변경 되었습니다.
		openDialogAlert(getAlert('mo061'),400,140,'parent',"parent.document.location.replace('/mypage/refund_view?refund_code={$refund_code}')");
	}

	public function return_modify(){
		$return_code = $_POST['return_code'];

		$data = array();
		$data['return_method']			= $_POST['return_method'];
		$data['cellphone']				= implode('-',$_POST['cellphone']);
		$data['phone']					= implode('-',$_POST['phone']);
		if($_POST['sender_new_Zipcode']){
			$data['sender_zipcode']			= $_POST['sender_new_Zipcode'];
		}else{
			$data['sender_zipcode']			= join("-", $_POST['senderZipcode']);
		}

		$data['sender_address_type']	= (($_POST['senderAddress_type']))?$_POST['senderAddress_type']:"zibun";
		$data['sender_address']			= $_POST['senderAddress'];
		$data['sender_address_street']	= $_POST['senderAddress_street'];
		$data['sender_address_detail']	= $_POST['senderAddressDetail'];
		$data['return_reason']			= $_POST['return_reason'];
		$data['shipping_price_depositor'] 	= $_POST['shipping_price_depositor'];
		$data['shipping_price_bank_account'] = $_POST['shipping_price_bank_account'];

		$this->db->where('return_code', $return_code);
		$this->db->update('fm_order_return', $data);
		//반품정보가 변경 되었습니다.
		openDialogAlert(getAlert('mo062'),400,140,'parent',"parent.document.location.replace('/mypage/return_view?return_code={$return_code}')");
	}

	# 구매확정처리
	public function buy_confirm()
	{
		$this->load->model('returnmodel');
		$this->load->model('exportmodel');

		$export_code		= str_replace('code_','',$_GET['export_code']);
		$data_export		= $this->exportmodel->get_export($export_code);
		$data_export_item	= $this->exportmodel->get_export_item($export_code);

		if ($data_export['status'] == 45) {
			//출고준비 상태에서는 구매확정을 하실 수 없습니다.
			$msg = getAlert('mo161');
			$result = array('result'=>false, 'msg'=>$msg);
			echo json_encode($result);
			exit;
		}
		
		if ($data_export_item[0]['goods_kind'] == 'coupon') {
			//티켓상품에서는 구매확정을 하실 수 없습니다.
			$msg = getAlert('mo101');
			$result = array('result'=>false, 'msg'=>$msg);
			echo json_encode($result);
			exit;
		}


		$cfg_order		= config_load('order');
		$cfg_reserve	= ($this->reserves)?$this->reserves:config_load('reserve');

		$mode		= "buyconfirm";
		if($cfg_order['buy_confirm_use'])
		{
			$tot_confirm_ea		= 0;
			foreach($data_export_item as $k => $item)
			{
				
				// 구매확정 중 반품 신청이 있는지 확인
				$params_check_buyconfirm = array();
				$params_check_buyconfirm['order_seq']		= $item['order_seq'];
				$params_check_buyconfirm['export_code']		= $item['export_code'];
				
				$this->load->model('buyconfirmmodel');
				$check_buyconfirm = $this->buyconfirmmodel->check_ing_return_for_buyconfirm($params_check_buyconfirm);
				
				if(!$check_buyconfirm){
					//D출고건은 반품신청 중이므로 구매확정할 수 없습니다.
					$msg = getAlert('mp301', $params_check_buyconfirm['export_code']);
					$result = array('result'=>false, 'msg'=>$msg);
					echo json_encode($result);
					exit;
				}


				//티켓상품의 취소(환불) 구매확정제외
				if ( $item['goods_kind'] == 'coupon') continue;

				$tmp = array();
				$tmp['opt_type']				= $item['opt_type'];
				$tmp['reserve_ea']				= $item['reserve_ea'];
				$tmp['reserve_buyconfirm_ea']	= $item['reserve_buyconfirm_ea'];
				$tmp['reserve_destroy_ea']		= $item['reserve_destroy_ea'];
				$tmp['option_seq']				= $item['option_seq'];
				$tmp['export_item_seq']			= $item['export_item_seq'];
				$tot_confirm_ea					+= $item['reserve_ea'];

				$export_items[] = $tmp;
			}

			## 마일리지 지급예정수량이 있을때
			if($tot_confirm_ea > 0){
			//if($data_export['reserve_save'] == 'none'){

				$edate		= date('Y-m-d',strtotime("-".$cfg_order['save_term']." day"));
				## 배송완료일 > 구매확정시 마일리지 지급 기간 or 기간 만료시 마일리지 무조건 지급
				if( $data_export['complete_date'] >= $edate || $cfg_order['save_type'] == 'give'){

					$data_order	= $this->ordermodel->get_order($data_export['order_seq']);
					foreach($export_items as $k => $item)
					{

						// 마일리지 지급예정수량 2015-03-31 pjm
						$confirm_ea		= $item['reserve_ea'];

						$reserve = 0;
						if($item['opt_type'] == 'opt') $reserve = $this->ordermodel->get_option_reserve($item['option_seq']);
						else $reserve = $this->ordermodel->get_suboption_reserve($item['option_seq']);
						$tot_reserve += $reserve * $confirm_ea;

						$point = 0;
						if($item['opt_type'] == 'opt') $point = $this->ordermodel->get_option_reserve($item['option_seq'],'point');
						else $point = $this->ordermodel->get_suboption_reserve($item['option_seq'],'point');
						$tot_point += $point * $confirm_ea;

						#지급예정수량 : 0, 지급완료수량 : 지급예정수량
						$tmp = array();
						$tmp['export_item_seq']			= $item['export_item_seq'];
						$tmp['reserve_ea']				= 0;
						$tmp['reserve_buyconfirm_ea']	= $item['reserve_ea']+$item['reserve_buyconfirm_ea'];
						$chg_reserve[]					= $tmp;

					}

					if( $data_order['member_seq'] ){
						$this->load->model('membermodel');

						if( $tot_reserve ){
							$params_reserve['gb']			= "plus";
							$params_reserve['emoney']		= $tot_reserve;
							$params_reserve['memo']			= "[".$export_code."] 구매확정";
							$params_reserve['memo_lang'] 	= $this->membermodel->make_json_for_getAlert("mp238",$export_code); // [%s] 구매확정
							$params_reserve['ordno']		= $data_order['order_seq'];
							$params_reserve['type']			= "order";
							$params_reserve['limit_date'] 	= get_emoney_limitdate('order');
							$this->membermodel -> emoney_insert($params_reserve, $data_order['member_seq']);
						}

						if( $tot_point ){
							$params_point['gb']				= "plus";
							$params_point['point']          = $tot_point;
							$params_point['memo']           = "[".$export_code."] 구매확정";
							$params_point['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp238",$export_code); // [%s] 구매확정
							$params_point['ordno']          = $data_order['order_seq'];
							$params_point['type']           = "order";
							$params_point['limit_date'] 	= get_point_limitdate('order');
							$this->membermodel->point_insert($params_point, $data_order['member_seq']);
						}

						$query = "update fm_goods_export set reserve_save = 'save' where export_code = ?";
						$this->db->query($query,array($export_code));
					}

				## 지급예정 수량 소멸 처리
				}elseif($data_export['complete_date'] < $edate && $cfg_order['save_type'] == 'exist'){

					$mode = "destroy";
					foreach($data_export_item as $k => $item)
					{
						#지급예정수량 : 0, 소멸수량 : 지급예정수량
						$tmp = array();
						$tmp['export_item_seq']			= $item['export_item_seq'];
						$tmp['reserve_ea']				= 0;
						$tmp['reserve_destroy_ea']		= $item['reserve_ea']+$item['reserve_destroy_ea'];
						$chg_reserve[]					= $tmp;
					}
				}
				## 출고아이템에 마일리지 지급예정수량, 지급완료 수량 업데이트 2015-03-31 pjm
				$this->exportmodel->exec_export_reserve_ea($chg_reserve,$mode);
			}
		}

		if($mode == "buyconfirm") $mode = "pay";

		$data_buy_confirm['order_seq']		= $data_export['order_seq'];
		$data_buy_confirm['export_seq']		= $data_export['export_seq'];
		if($this->userInfo['member_seq']){
			$data_buy_confirm['member_seq'] = $this->userInfo['member_seq'];
			$data_buy_confirm['doer']		= $this->userInfo['user_name'];
		}else{
			$data_buy_confirm['doer'] = '구매자';
		}
		$data_buy_confirm['ea']				= $tot_confirm_ea;
		$data_buy_confirm['emoney_status']	= $mode;
		$data_buy_confirm['actor_id']		= $this->userInfo['userid'];

		$this->load->model('buyconfirmmodel');
		$this->buyconfirmmodel -> buy_confirm('user',$export_code);
		$this->buyconfirmmodel -> log_buy_confirm($data_buy_confirm);

		// 배송완료 처리
		if( $data_export['status'] < 75 ){
			if( in_array($data_export['status'],$this->exportmodel->able_status_action['complete_delivery']) ){
				$this->exportmodel->exec_complete_delivery($export_code);
			}else{
				//$this->exportmodel->arr_step[$data_export['status']] 에서는 배송완료를 하실 수 없습니다.
				$msg = getAlert('mo102',$this->exportmodel->arr_step[$data_export['status']]);
				$result = array('result'=>false, 'msg'=>$msg);
				echo json_encode($result);
				exit;
			}
		}

		// 주문로그
		if(!$this->userInfo['user_name']) {
			$order_row = $this->ordermodel->get_order($data_export['order_seq']);
			$actor = $order_row['order_user_name'];
		} else {
			$actor = $this->userInfo['user_name'];
		}

		$order_log_title	= '구매확정('.$export_code.':'.$tot_confirm_ea.")";
		$this->ordermodel->set_log($data_export['order_seq'],'buyconfirm',$actor,$order_log_title);

		/**
		* 4-1 임시매출데이타를 이용한 통합정산테이블
		* 정산개선 - 통합정산데이타 생성
		* @ 
		**/
		if(!$this->accountall)			$this->load->helper('accountall');
		if(!$this->accountallmodel)		$this->load->model('accountallmodel');
		if(!$this->providermodel)		$this->load->model('providermodel');
		//정산대상 수량업데이트
		$this->accountallmodel->update_calculate_sales_ac_ea($data_export['order_seq'],$export_code);
		//정산확정 처리
		$this->accountallmodel->insert_calculate_sales_buyconfirm($data_export['order_seq'],$export_code, $tot_confirm_ea);
		//debug_var($this->db->queries);
		//debug_var($this->db->query_times);
		/**
		* 4-1 임시매출데이타를 이용한 통합정산테이블
		* 정산개선 - 통합정산데이타 생성
		* @
		**/

		//구매확정이 완료 되었습니다.
		$msg = getAlert('mo103');
		if( $tot_reserve > 0 && $tot_point > 0 ) {
			//구매확정 및
			$msg = getAlert('mo104');
			if($tot_reserve > 0){
				//마일리지 지급(".$tot_reserve."원)
				$msg .= getAlert('mo105',get_currency_price($tot_reserve,2));
			}
			if($tot_point > 0){
				//포인트 지급(".$tot_point."p)
				$msg .= ($tot_reserve > 0)?", ".getAlert('mo106',get_currency_price($tot_point,1)):getAlert('mo106',get_currency_price($tot_point,1));
			}
			//완료 되었습니다.
			$msg .= getAlert('mo107');
		}

		if($cfg_reserve[autoemoney] == 1 ) {//마일리지 자동지급사용함
			$this->load->model('Boardmanager');
			$sql['whereis']	= ' and id= "goods_review" ';
			$sql['select']		= ' * ';
			$manager = $this->Boardmanager->managerdataidck($sql);//게시판정보

			//상품평을 작성 하시면 아래와같이 추가로 지급됩니다.
			$msg .= "<span ><div style=\"margin-top:5px;margin-left:18px;\"><br/>".getAlert('mo108')."<br/> ";
			if($cfg_reserve[autoemoney_photo] > 0 ||  $cfg_reserve[autopoint_photo] > 0 ) {
				//<b>포토 ".$manager['name']."</b>는
				$msg .= getAlert('mo109',$manager['name']);
				if($cfg_reserve[autoemoneytype] != 1 && ( $cfg_reserve[autoemoneystrcut1]>0 || $cfg_reserve[autoemoneystrcut2]>0 )) {
				$msg .= "<span >(";
				if($cfg_reserve[autoemoneytype] == 2 && $cfg_reserve[autoemoneystrcut1]>0 ){
					$msg .= number_format($cfg_reserve[autoemoneystrcut1]);
				}elseif($cfg_reserve[autoemoneytype] == 3 && $cfg_reserve[autoemoneystrcut2]>0) {
					$msg .= number_format($cfg_reserve[autoemoneystrcut2]);
				}
					//자 이상
					$msg .= getAlert('mo110').")</span>";
				}
				if($cfg_reserve[autoemoney_photo] > 0 ){
//					$msg .= "마일리지 <span style=\"color:#c40000;\" >".number_format($cfg_reserve[autoemoney_photo])."</span>원, ";
					$msg .= getAlert('mo111',get_currency_price($cfg_reserve[autoemoney_photo],2));
				}
				if($cfg_reserve[autopoint_photo] > 0) {
//					$msg .= "포인트 <span style=\"color:#c40000;\" >".number_format($cfg_reserve[autopoint_photo])."</span>P";
					$msg .= getAlert('mo112',get_currency_price($cfg_reserve[autopoint_photo]));
				}
				//지급,
				$msg .= getAlert('mo113');
			}
			$msg .= "<br/>";
			if($cfg_reserve[autoemoney_video] > 0 ||  $cfg_reserve[autopoint_video] > 0 ) {
				//<b>동영상 ".$manager['name']."</b>는
				$msg .= getAlert('mo114',$manager['name']);
				if($cfg_reserve[autoemoneytype] != 1 && ( $cfg_reserve[autoemoneystrcut1]>0 || $cfg_reserve[autoemoneystrcut2]>0 )) {
				$msg .= "<span >(";
				if($cfg_reserve[autoemoneytype] == 2 && $cfg_reserve[autoemoneystrcut1]>0 ){
					$msg .= number_format($cfg_reserve[autoemoneystrcut1]);
				}elseif($cfg_reserve[autoemoneytype] == 3 && $cfg_reserve[autoemoneystrcut2]>0) {
					$msg .= number_format($cfg_reserve[autoemoneystrcut2]);
				}
					//자 이상
					$msg .= getAlert('mo110').")</span>";
				}
				if($cfg_reserve[autoemoney_video] > 0 ){
//					$msg .= "마일리지 <span style=\"color:#c40000;\" >".number_format($cfg_reserve[autoemoney_video])."</span>원, ";
					$msg .= getAlert('mo111',get_currency_price($cfg_reserve[autoemoney_video],2));
				}
				if($cfg_reserve[autopoint_video] > 0) {
//					$msg .= "포인트 <span style=\"color:#c40000;\" >".number_format($cfg_reserve[autopoint_video])."</span>P";
					$msg .= getAlert('mo112',get_currency_price($cfg_reserve[autopoint_video]));
				}
				//지급,
				$msg .= getAlert('mo113');
			}
			$msg .= "<br/>";
			if( $cfg_reserve[autoemoney_review] > 0 ||  $cfg_reserve[autopoint_review] > 0 ) {
			//<b>일반 ".$manager['name']."</b>는
			$msg .= getAlert('mo115',$manager['name']);
			if( $cfg_reserve[autoemoneytype] != 1 && ( $cfg_reserve[autoemoneystrcut1]>0 || $cfg_reserve[autoemoneystrcut2]>0 ) ){
				$msg .= "	<span >(";
				if( $cfg_reserve[autoemoneytype] == 2 && $cfg_reserve[autoemoneystrcut1]>0 ) {
					$msg .= number_format($cfg_reserve[autoemoneystrcut1]);
				}elseif( $cfg_reserve[autoemoneytype] == 3 && $cfg_reserve[autoemoneystrcut2]>0) {
					$msg .= number_format($cfg_reserve[autoemoneystrcut2]);
				}
				//자 이상
				$msg .= getAlert('mo110').")</span>";
			}
				if($cfg_reserve[autoemoney_review] > 0 ){
//				$msg .= "마일리지 <span style=\"color:#c40000;\">".number_format($cfg_reserve[autoemoney_review])."</span>원, ";
				$msg .= getAlert('mo111',get_currency_price($cfg_reserve[autoemoney_review],2));
				}
				if( $cfg_reserve[autopoint_review] > 0 ) {
//				$msg .= " 포인트 <span style=\"color:#c40000;\">".number_format($cfg_reserve[autopoint_review])."</span>P";
				$msg .= getAlert('mo112',get_currency_price($cfg_reserve[autopoint_review]));
				}
				$msg .= getAlert('mo113');
			}
			$msg .= "</div>";

			$msg .= "<div id='openDialogLayerBtns' align='center' style='padding-top:15px'><span class='btn medium'><input type='button' value='상품평' onclick=\"location.href='mypage/mygdreview_catalog';\" /></span></div>";
		}

		$result = array('result'=>true, 'msg'=>$msg);
		echo json_encode($result);
		exit;
		//$callback = "parent.location.reload();";
		//openDialogAlert('구매확정이 완료 되었습니다.',400,140,'parent',$callback);
	}


	public function buy_gift(){

		if(!$this->userInfo['member_seq']) {
			openDialogAlert(getAlert('mp022'),400,140,'parent',$callback);
			exit;
		}

		$this->load->model('membermodel');
		$aParams = $this->input->post();

		### Validation
		//기본주소
		$this->validation->set_rules('recipient_address', getAlert('mp086'),'trim|required|max_length[40]|xss_clean');
		//나머지주소
		$this->validation->set_rules('recipient_address_detail', getAlert('mp087'),'trim|required|max_length[40]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if(!isset($aParams['gift_seq'])) {
			$this->load->library('mypagelibrary');
			$gift_validation = $this->mypagelibrary->buy_gift_validation();
			if($gift_validation == false) {
				openDialogAlert(getAlert('mp022'),400,140,'parent',$callback);
				exit;
			}
		}else{
			// gift_seq 있을 때 처리
		}

		### 주문에 넣는 데이터는 POST 필요하므로 params 안바꿈
		$_POST['payment']		= 'bank';
		$_POST['emoney_use']	= 'use';
		$_POST['emoney']		= $_POST['point'];

		$this->load->model('ordermodel');
		$order_seq = $this->ordermodel->insert_order(0, 0, null, $this->freeprice);
		$this->ordermodel->insert_delivery_address();

		## 배송그룹 insert 2015-03-27 pjm
		$shipping_params = array();
		$shipping_params['order_seq']		= $order_seq;
		$shipping_params['provider_seq']	= 1;
		$shipping_params['shipping_cost']	= 0;
		$shipping_params['delivery_cost']	= 0;
		$shipping_params['shipping_group']	= 'delivery1';
		$shipping_params['shipping_method'] = 'delivery';
		$this->db->insert('fm_order_shipping', $shipping_params);
		$shipping_seq = $this->db->insert_id();

		unset($gift_params);
		$gift_params['order_seq'] 		= $order_seq;
		$gift_params['goods_seq']		= $aParams['goods_seq'];
		$gift_params['item_seq'] 		= $item_seq;
		$gift_params['shipping_seq'] 	= $shipping_seq;
		$gift_params['image']		= get_gift_image($aParams['goods_seq'],'thumbCart');
		$gift_params['goods_name']	= get_gift_name($aParams['goods_seq']);
		$gift_params['goods_type'] = 'gift';
		$this->db->insert('fm_order_item', $gift_params);
		$item_seq = $this->db->insert_id();

		unset($gift_params);
		$gift_params['order_seq'] 		= $order_seq;
		$gift_params['item_seq'] 		= $item_seq;
		$gift_params['shipping_seq'] 	= $shipping_seq;
		$gift_params['provider_seq'] 	= 1;
		$gift_params['step'] 			= "0";
		$gift_params['price'] 			= "0";
		$gift_params['ori_price'] 		= $aParams['point'];
		$gift_params['ea'] 				= "1";
		$this->db->insert('fm_order_item_option', $gift_params);

		$this->ordermodel->set_step($order_seq, '25');

		// 포인트 교환은 없는것같은데.... 혹시 몰라서 post 로 넘어올때만 처리함
		if($aParams['goods_rule'] == "point"){
			###
			$this->load->model('membermodel');
			$iparam['gb']			= "minus";
			$iparam['type']			= 'order_gift';
			$iparam['point']		= get_cutting_price($aParams['point']);
			$iparam['memo']			= '['.$aParams["goods_name"].'] 포인트 교환 사은품';
			$iparam['memo_lang']	= $this->membermodel->make_json_for_getAlert("mp245",$aParams["goods_name"]); // [%s] 포인트 교환 사은품
			$iparam['ordno']		= $order_seq;
			$this->membermodel->point_insert($iparam, $this->userInfo['member_seq']);
		}else{
			/* 마일리지 사용 */
			$this->load->model('membermodel');
			$params = array(
				'gb'		=> 'minus',
				'type'		=> 'order_gift',
				'emoney'	=> get_cutting_price($aParams['point']),
				'memo'		=> "[".$aParams["goods_name"]."] 마일리지 교환 사은품",
				'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp239",$aParams["goods_name"]), // [%s] 마일리지 교환 사은품
				'ordno'		=> $order_seq
			);

			$this->membermodel->emoney_insert($params, $this->userInfo['member_seq']);
			$this->ordermodel->set_emoney_use($order_seq,'return');
		}

		$callback = "parent.top.document.location.reload();";
		//사은품 신청이 정상적으로 완료 되었습니다.
		openDialogAlert(getAlert('mp088'),400,140,'parent',$callback);

	}

	public function add_delivery_address(){

		if(!$this->userInfo['member_seq']) {
			echo json_encode(array(
				'msg' => '로그인 후에 가능합니다.'
			));
			exit;
		}

		$this->load->model('ordermodel');

		$this->validation->set_rules('recipient_user_name', '받는이','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('recipient_zipcode[]', '우편번호','trim|required|max_length[7]|xss_clean');
		$this->validation->set_rules('recipient_address', '주소','trim|max_length[255]|required|xss_clean');
		$this->validation->set_rules('recipient_address_detail', '나머지주소','trim|max_length[255]|required|xss_clean');
		$this->validation->set_rules('recipient_phone[]', '받는이 유선전화','trim|max_length[4]|xss_clean');
		$this->validation->set_rules('recipient_cellphone[]', '받는이 핸드폰','trim|numeric|max_length[4]|required|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$_POST['address_description'] = $_POST['recipient_user_name'];
		$_POST['insert_mode'] = 'insert';
		$_POST['international'] = '0';

		$this->ordermodel->insert_delivery_address();

		echo json_encode(array(
			'msg' => '자주쓰는 배송지가 등록 되었습니다'
		));
	}

	public function cancel_tax(){

		$order_seq = $_POST["order_seq"];

		$sql = "UPDATE fm_sales SET tstep = 3, approach = 'unlink', up_date = '".date("Y-m-d H:i:s")."' WHERE order_seq = '{$order_seq}'";
		$result = $this->db->query($sql);
		$return = array('result'=>true, 'msg'=>"처리되었습니다.");
		echo json_encode($return);
		exit;

	}

	public function filedown(){

		$file 	= $this->input->get('file');
		if(!$file){
			//잘못된 접근입니다.
			openDialogAlert(getAlert('mp022'),400,140,'parent');
			exit;
		}
		$path	= ROOTPATH."data/order/".$file;
		$dir	= 'order';

		if(!file_exists(realpath($path))){
			$path = ROOTPATH."data/tmp/".$file;
			$dir	= 'tmp';
		}

		get_file_down($path, $file, $dir);
	}

	public function point_exchagne(){

		$configReserve = ($this->reserves)?$this->reserves:config_load('reserve');
		if($configReserve['point_use'] != 'Y') {
			//잘못된 접근입니다.
			openDialogAlert(getAlert('mp022'),400,140,'parent',$callback);
			exit;
		}

		$exchange_point = $_POST['exchange_point'];
		$exchange_emoney = $_POST['exchange_emoney'];

		$this->load->model('membermodel');
		$mdata = $this->membermodel->get_member_data($this->userInfo['member_seq']);//회원정보

		if($mdata['point'] < $exchange_point){
			//보유한 포인트보다 더 많이 입력 하셨습니다.
			openDialogAlert(getAlert('mp023'),400,140,'parent',$callback);
			exit;
		}

		if($configReserve['emoney_minum_point'] > $exchange_point){
			//최소 교환 포인트보다 더 작게 입력 하셨습니다.
			openDialogAlert(getAlert('mp024'),400,140,'parent',$callback);
			exit;
		}

		if($exchange_emoney < 1){
			//전환될 마일리지가 없습니다.
			openDialogAlert(getAlert('mp025'),400,140,'parent',$callback);
			exit;
		}

		$mod = get_currency_price($exchange_point,1) % get_currency_price($configReserve['emoney_point_rate'],1);

		if($mod > 0){
			$exchange_point = get_currency_price($exchange_point,1) - get_currency_price($mod,1);
		}

		$exchange_emoney = get_currency_price($exchange_point,1) / get_currency_price($configReserve['emoney_point_rate'],1);

		/* 마일리지 지급 */
		if($exchange_emoney > 0 ){
			$params = array(
				'gb'			=> 'plus',
				'type'			=> 'exchange',
				'emoney'		=> get_currency_price($exchange_emoney,1),
				'ordno'			=> '',
				'limit_date'	=> get_emoney_limitdate('exchange_emoney'),
				'memo'			=> "교환포인트",
				'memo_lang'		=> $this->membermodel->make_json_for_getAlert("mp268"), // 교환포인트
			);
			$this->membermodel->emoney_insert($params, $this->userInfo['member_seq']);

			$iparam['gb']			= "minus";
			$iparam['type']			= 'exchange';
			$iparam['point']		= get_currency_price($exchange_point,1);
			$iparam['memo']			= '마일리지('.get_currency_price($exchange_emoney).') 교환';
			$iparam['memo_lang']	= $this->membermodel->make_json_for_getAlert("mp271",get_currency_price($exchange_emoney));    // 마일리지(%s) 교환
			$this->membermodel->point_insert($iparam, $this->userInfo['member_seq']);
			$callback = "parent.top.document.location.reload();";

			//포인트 교환이 정상적으로 처리되었습니다. \n교환 포인트 : '.$exchange_point.'P => 지급 마일리지 : '.$exchange_emoney.'원
			openDialogAlert(getAlert('mp026',array(get_currency_price($exchange_point,1),get_currency_price($exchange_emoney,2))),400,150,'parent',$callback);
		}


		exit;
	}

	// 티켓상품 사용 처리
	public function usecoupon(){
		$this->exportSmsData = array();
		$this->load->model("exportmodel");
		$this->load->model("returnmodel");

		$export_code		= trim($_POST['export_code']);
		$coupon_serial		= trim($_POST['coupon_serial']);
		$use_coupon_value	= trim($_POST['use_coupon_value']);

		$usetype			= trim($_POST['usetype']);
		$next_coupon_use	= trim($_POST['next_coupon']);

		if	(!$export_code || !$coupon_serial || !$use_coupon_value || !is_numeric($use_coupon_value)){
			//티켓사용 인증에 실패하였습니다.
			openDialogAlert(getAlert('mo063'),400,140,'parent',$callback);
			exit;
		}

		// 티켓상품 인증 확인
		$chkcoupon	= $this->exportmodel->chk_coupon(array('export_code'=>$export_code));
		if	($chkcoupon['result'] != 'success'){
			if		($chkcoupon['result'] == 'refund')		$msg	= getAlert('mo064'); //환불된 티켓입니다.
			elseif	($chkcoupon['result'] == 'noremain')	$msg	= getAlert('mo065'); //이미 모두 사용된 티켓입니다.
			elseif	($chkcoupon['result'] == 'notyet')		$msg	= getAlert('mo066'); //사용 가능한 기간이 아닙니다.
			elseif	($chkcoupon['result'] == 'expire')		$msg	= getAlert('mo067'); //만료된 티켓입니다.
			else											$msg	= getAlert('mo063'); //티켓사용 인증에 실패하였습니다.

			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}

		// 티켓상품 사용 내역 저장 및 배송완료 처리
		if($usetype != 'multi'){
			$this->load->model('exportmodel');
			$this->exportmodel->coupon_use_save($_POST);
		}

		$callback = "parent.location.reload();";
		if	($this->mobileMode)
			$callback = "parent.window.close();";

		## 모바일 ver 3 및 새로운 티켓상품 사용
		// 티켓상품 사용에 따른 화면처리 :: 2015-05-12 lwh
		$coupon		= $this->exportmodel->get_export($export_code);
		if($usetype == 'one' && $next_coupon_use){
			$cp_list	= $this->exportmodel->get_export_for_order($coupon['order_seq'], 'coupon');
			foreach($cp_list as $coupon_info){
				if($coupon_info['export_code']==$export_code){
					continue;
				}else{
					$cp_param['export_code'] = $coupon_info['export_code'];
					$coupons = $this->exportmodel->chk_coupon($cp_param);
					if($coupons['coupon_remain_value'] > 0){
						$next_coupon = $coupon_info['export_code'];
						break;
					}
				}
			}
		}else if($usetype == 'multi'){
			// 여러장 사용시 사용가능 티켓상품 출고번호 추출
			$cp_list	= $this->exportmodel->get_export_for_order($coupon['order_seq'], 'coupon');

			foreach($cp_list as $coupon_info){
				if($coupon_info['socialcp_status']!='1') continue;

				$cp_param['export_code'] = $coupon_info['export_code'];
				// 사용가능 티켓상품 확인
				$coupons = $this->exportmodel->chk_coupon($cp_param);
				if($coupons['result'] == 'success'){
					$idx++;
					$multi_coupon[$idx]['export_code']	= $coupons['export_code'];
					$multi_coupon[$idx]['coupon_serial']= $coupons['coupon_serial'];
				}
			}

			$cnt = $_POST['multi_use_coupon'];
			for($i=1;$i<=$cnt;$i++){
				$_POST['export_code']	= $multi_coupon[$i]['export_code'];
				$_POST['coupon_serial'] = $multi_coupon[$i]['coupon_serial'];
				$this->exportmodel->coupon_use_save($_POST);
			}

			if($cnt < count($multi_coupon)){
				$next_coupon = $multi_coupon[$i]['export_code'];
			}
		}
		if(count($this->exportSmsData) > 0){
			commonSendSMS($this->exportSmsData);
		}

		// 다음티켓상품이 있을경우 콜백 처리
		if($next_coupon){
			if($_POST['version'] == '3.0'){
				$popup = "&popup=1";
			}

			$callback = "parent.location.replace('/mypage/coupon_use?code=".$next_coupon."&usetype=".$usetype."&use_coupon_value=".$use_coupon_value."&use_coupon_area=".$_POST['use_coupon_area']."&use_coupon_area_direct=".$_POST['use_coupon_area_direct']."&use_coupon_memo=".$_POST['use_coupon_memo']."&manager_code=".$_POST['manager_code'].$popup."');";
		}else{
			//$callback = "parent.location.href='/mypage/export_list?seq=".$coupon['order_seq']."&type=coupon';";

			if($_POST['usetype']=='one'){
				$callback = "parent.location.reload();";
			}else{
				$callback = "parent.location.reload();";
			}

			if($_POST['version'] == '3.0'){
				$callback = "parent.self.close();";
			}
		}
		//티켓 사용확인이 완료되었습니다.
		openDialogAlert(getAlert('mo068'),400,140,'parent',$callback);
	}

	// 나의 할인쿠폰 사용 처리
	public function usemycoupon(){

		$this->load->model('membermodel');
		$this->load->model('couponmodel');

		if($_POST['manager_code']){
				$param['provider_seq']	= 1; // 입점몰일 때만
				$param['certify_code']	= $_POST['manager_code'];
				$certify				= $this->membermodel->get_certify_manager($param);
				$manager_id				= $certify[0]['manager_id'];
				$manager_code			= $certify[0]['certify_code'];
				$manager_name			= $certify[0]['manager_name'];
				if	(!$manager_code){
					//유효하지 않은 직원코드입니다.
					openDialogAlert(getAlert('mo069'),400,140,'parent',$callback);
					exit;
				}

			// 할인쿠폰 사용가능 여부
			$download_seq	= trim($_POST['download_seq']);
			$coupon			= $this->couponmodel->get_download_coupon($download_seq);

			if($_POST['popup']){
				$callback = "parent.close();";
			}else{
				$callback = "parent.location.reload();";
			}

			if(!$coupon){
				//유효한 쿠폰이 아닙니다.
				openDialogAlert(getAlert('mo070'),400,140,'parent',$callback);
			}

			if($coupon['use_status'] == 'unused'){
				// 할인쿠폰 사용 저장처리
				$this->couponmodel->set_download_use_status($download_seq,'used',$manager_name,$manager_code);
				//쿠폰 사용확인이 완료되었습니다.
				openDialogAlert(getAlert('mo071'),400,140,'parent',$callback);
			}else{
				//이미 사용한 쿠폰입니다.
				openDialogAlert(getAlert('mo072'),400,140,'parent',$callback);
			}
		}else{
			//잘못된 접근입니다.
			openDialogAlert(getAlert('mo073'),400,140,'parent',$callback);
			exit;
		}
	}

	// 해당 출고의 아이템 목록 - 구매확정 표기시 사용
	public function ajax_export_item(){
		$goods_kind['goods'] = 0;
		$goods_kind['subopt'] = 0;
		$goods_kind['gift'] = 0;
		$return = array('result'=>false);
		$this->load->model('exportmodel');

		//$_POST['export_code'] = 'D15051316225';

		if($_POST['export_code']){
			$item = $this->exportmodel->get_export_item($_POST['export_code']);

			foreach($item as $data){
				if($data['goods_type'] == 'goods'){
					if($data['opt_type'] == 'opt')
						$goods_kind['goods'] += $data['ea'];
					if($data['opt_type'] == 'sub')
						$goods_kind['subopt'] += $data['ea'];
				}else{
					$goods_kind['gift'] += $data['ea'];
				}
			}

			if($goods_kind['goods'] > 0)
				$in_str[] = '상품 <span class="red">'.$goods_kind['goods'].'</span>개';
			if($goods_kind['subopt'] > 0)
				$in_str[] = '추가상품 <span class="red">'.$goods_kind['subopt'].'</span>개';
			if($goods_kind['gift'] > 0)
				$in_str[] = '사은품 <span class="red">'.$goods_kind['gift'].'</span>개';

			$in_str = implode(', ',$in_str);

			$return = array('result'=>true, 'in_str'=>$in_str);
		}
		echo json_encode($return);
		exit;
	}
}

/* End of file mypage_process.php */
/* Location: ./app/controllers/mypage_process.php */

