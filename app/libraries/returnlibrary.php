<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 반품과 관련된 소스들이 컨트롤러와 모델에 산재되어 있어 향후 병합을 위한 라이브러리 구조 
 * 2018-08-06
 * by hed 
 */
class ReturnLibrary
{
	public $allow_exit = true;
	
	function __construct() {
		$this->CI =& get_instance();
		$this->CI->load->model('ordermodel');
		$this->CI->arr_step = config_load('step');
		$this->CI->load->helper('order');
	}
	
	/**
	 * 주문 환불 조회 by hed
	 * @param int $order_seq
	 * @param string $get_mode			| return_coupon : 티겟상품 환불
	 * @param string $type | return : 반품, exchange : 맞교환
	 * @return array
	 */
	public function get_order_for_return($order_seq, $get_mode = null, $type = 'return'){
		$this->CI->load->model('ordermodel');
		$this->CI->load->model('returnmodel');
		$this->CI->load->model('refundmodel');
		$this->CI->load->model('providermodel');
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('exportmodel');
		$this->CI->load->model('shippingmodel');
		
		if(!$this->CI->arr_step)	$this->CI->arr_step = config_load('step');
		$able_steps	= $this->CI->ordermodel->able_step_action['return_list'];
		
		$orders		= $this->CI->ordermodel->get_order($order_seq);
		$items 		= $this->CI->ordermodel->get_item($order_seq);
		
		if( strstr($orders['recipient_zipcode'],'-') ) {
			$orders['recipient_new_zipcode'] 	= str_replace("-","",$orders['recipient_zipcode']);
		}else{
			$orders['recipient_new_zipcode'] 	= $orders['recipient_zipcode'];
		}
		if($orders['order_phone']) $orders['order_phone'] = explode('-',$orders['order_phone']);
		if($orders['order_cellphone']) $orders['order_cellphone'] = explode('-',$orders['order_cellphone']);
		
		$reasonLoop = array();
		$npay_use	= npay_useck();
		//npay 사용여부 확인, 반품사유 코드 불러오기
		if($npay_use && $orders['npay_order_id']){
			$this->CI->load->library('naverpaylib');
			$reasonLoop = $this->CI->naverpaylib->get_npay_return_reason();
		}else{
			// 사유코드
			$reasons = code_load('return_reason');
			
			if( $get_mode == 'return_coupon' ) {
				$qry = "select * from fm_return_reason where return_type='coupon' order by idx asc";
				$query = $this->CI->db->query($qry);
				$reasonLoop = $query -> result_array();
			}else{
				$qry = "select * from fm_return_reason where return_type!='coupon' order by idx asc";
				$query = $this->CI->db->query($qry);
				$reasonLoop = $query -> result_array();
			}
			$npay_use = false;
		}
		$out['reasonLoop'] = $reasonLoop;
		$out['reasons'] = $reasons;
		
		// 	계좌설정 정보
		$bank = $payment = $escrow = "";
		$arr = config_load('bank');
		if($arr) foreach(config_load('bank') as $k => $v){
			list($tmp) = code_load('bankCode',$v['bank']);
			$v['bank'] = $tmp['value'];
			$bank[] = $v;
			if( $v['accountUse'] == 'y' ) $payment['bank'] = true;
		}
		$out['bank'] = $bank;
		
		// 반품배송비 입금 계좌설정 정보
		$aReturnBanks	= array();
		$aCfgBanks		= config_load('bank_return');
		if( $aCfgBanks ) foreach($aCfgBanks	as $sKeyCfgBank	=> $sValCfgBank){
			if($sValCfgBank['accountUseReturn'] == 'y'){
				list($sValCfgBank['bank'])	= code_load('bankCode', $sValCfgBank['bankReturn']);
				$aReturnBanks[]				= $sValCfgBank;
			}
		}
		$out['bankReturn'] = $aReturnBanks;
		
		// 출력데이터
		$loop = array();
		
		$cfg_order = config_load('order');
		
		// 출고정보
		$exports = $this->CI->exportmodel->get_export_for_order($order_seq);
		
		//주문상품의 실제 1건당 금액계산 @2014-11-27
		foreach($items as $key=>$item){
			if ( $item['goods_kind'] != 'coupon' ) continue;
			$reOption	= array();
			$options 	= $this->CI->ordermodel->get_option_for_item($item['item_seq']);
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
				$data['inputs'] = $this->CI->ordermodel->get_input_for_option($data['item_seq'],$data['item_option_seq']);
				
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
				$tot['out_sale_price']		+= $data['out_sale_price'];
				
				
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
				
				$return_item = $this->CI->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq']);
				$able_return_ea += (int) $data['step75'] + (int) $data['step55'] + (int) $data['step65']  - (int) $return_item['ea'];
				
				$suboptions = $this->CI->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);
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
					
					$tot['out_sale_price']		+= $subdata['out_sale_price'];
				}
			}
		}
		
		$gift_cnt = 0;
		foreach( $exports as $k => $data_export ){
			
			$data_export['item'] =  $this->CI->exportmodel->get_export_item($data_export['export_code']);
			
			foreach($data_export['item'] as $i=>$data){
				if ( ($data['goods_kind'] != 'coupon' && $get_mode == 'return_coupon') || ($data['goods_kind'] == 'coupon' && $get_mode != 'return_coupon')  ) continue;//티켓상품 반품/맞교환 제외@2013-11-12
				
				$data['export_code']		= $data_export['export_code'];
				if	($data['is_bundle_export'] == 'Y') {
					$data['export_code'] = $data_export['bundle_export_code'];
				}
				$data['reasons'] = $reasons;
				$data['reasonLoop'] = $reasonLoop;
				$data['mstep'] = $this->CI->arr_step[$data['step']];
				
				//티켓상품의 1개의 실제 결제금액 @2014-11-27
				$coupon_real_total_price = $order_one_option_sale_price[$data['option_seq']];
				
				$it_s = $data['item_seq'];
				$it_ops = $data['option_seq'];
				
				if($data['opt_type']=='opt'){
					$return_item = $this->CI->returnmodel->get_return_item_ea($it_s,$it_ops,$data_export['export_code']);
				}
				if($data['opt_type']=='sub'){
					$return_item = $this->CI->returnmodel->get_return_subitem_ea($it_s,$it_ops,$data_export['export_code']);
				}
				
				## 주문의 전체 출고수량이 아닌 해당 출고수량에 대해 체크하도록 수정 by hed
				$it_subops = "";
				if( $data['opt_type'] == 'sub') $it_subops = $data['option_seq'];
				$exp_data			= $this->CI->exportmodel->get_export_item_ea($data_export['export_code'],$data['item_option_seq'],$it_subops);
				$data['rt_ea'] = $exp_data['ea'];
				
				if($data['goods_type'] == 'gift' && $data['rt_ea'] > 0) $gift_cnt++;	//사은품 반품가능 수량 @2015-09-15 pjm
				
				//티켓상품의 취소(환불) 가능여부
				if ( $data['goods_kind'] == 'coupon' ) {
					$coupontotal++;//티켓상품@2013-11-06
					$data['rt_ea'] = 1;//출고당 기본 1개 @2016-07-20 ysm
					
					$data['couponinfo'] = get_goods_coupon_view($data_export['export_code']);
					$orders['coupon_use_return'] = $data['couponinfo']['coupon_use_return'];
					$orders['order_socialcp_cancel_return_title'] = $data['couponinfo']['order_socialcp_cancel_return_title'];
					$data['socialcp_return_disabled'] = false;
					$coupon_refund_emoney = $coupon_remain_price = $coupon_deduction_price = 0;
					$coupon_remain_real_percent = $coupon_remain_real_price = $coupon_remain_price = $coupon_deduction_price = 0;
					
					if( $return_item['ea'] ) {//환불접수된 경우
						$data['rt_ea'] = 0;
						$data['coupon_refund_type']		= 'price';
						$data['socialcp_return_disabled'] = true;
					}else{
						if( date("Ymd")>substr(str_replace("-","",$data['social_end_date']),0,8) ) {//유효기간 종료 후 마일리지환불 신청가능여부
							//$orders['socialcp_valid_coupons'] = true;
							/**
							 //관리자 : 미사용티켓상품 환불대상 불가 허용
							 if( $data['socialcp_use_return'] == 1) {//미사용티켓상품 환불대상
							 }else{//불가
							 }
							 **/
							if( order_socialcp_cancel_return($data['socialcp_use_return'], $data['coupon_value'], $data['coupon_remain_value'], $data['social_start_date'], $data['social_end_date'] , $data['socialcp_use_emoney_day'] ) === true ) {//미사용티켓상품여부 잔여값어치합계
								if ( $data['socialcp_input_type'] == 'price' ) {//금액
									$coupon_remain_price_tmp			= (int) $data['coupon_remain_value'];
									$coupon_deduction_price_tmp	= (int) $data['coupon_value'];
								}else{//횟수
									$coupon_remain_price_tmp			= (int) (100 * ($data['coupon_input_one'] * $data['coupon_remain_value']) / 100);
									$coupon_deduction_price_tmp	= (int) ($data['coupon_input_one'] * $data['coupon_value']);
								}
								$coupon_remain_real_percent = 100 * ($coupon_remain_price_tmp / $coupon_deduction_price_tmp);//잔여값어치율
								
								//실제결제금액
								$coupon_remain_real_price			= (int) ($coupon_remain_real_percent * ($coupon_real_total_price) / 100);
								
								$coupon_remain_price			= (int) ($data['socialcp_use_emoney_percent'] * ($coupon_remain_real_price) / 100);
								$coupon_deduction_price	= (int) ($coupon_real_total_price) - $coupon_remain_price;
								//$cancel_total_price  += $coupon_remain_price;//취소총금액
							}else{
								$data['socialcp_return_disabled'] = true;
							}
						}else{//유효기간 이전
							if( $data['coupon_remain_value'] >0) {//잔여값어치가 남아있으면
								/**
								 if( $data['coupon_value'] != $data['coupon_remain_value'] && $data['socialcp_cancel_use_refund'] == '1' ) {
								 //부분 사용한 티켓상품은 취소(환불) 불가 @2014-10-07
								 $data['rt_ea'] = 0;
								 $data['coupon_refund_type']		= 'price';
								 $data['socialcp_return_disabled'] = true;
								 }else{
								 }
								 ***/
								list($data['socialcp_refund_use'], $data['socialcp_refund_cancel_percent']) = order_socialcp_cancel_refund(
										$order_seq,
										$data['item_seq'],
										$orders['deposit_date'],
										$data['social_start_date'],
										$data['social_end_date'],
										$data['socialcp_cancel_payoption'],
										$data['socialcp_cancel_payoption_percent']
										);//취소(환불) 가능여부
										
										if( $data['coupon_value'] == $data['coupon_remain_value'] ) {//전체체크 미사용
											//실제결제금액
											$coupon_remain_price			= (int) ($data['socialcp_refund_cancel_percent'] * $coupon_real_total_price / 100);
											$coupon_deduction_price	= (int) ($coupon_real_total_price) - $coupon_remain_price;
											$coupon_remain_real_percent = "100";
											$coupon_remain_real_price = $coupon_real_total_price;
											$data['coupon_refund_type']	= 'price';
											$cancel_total_price  += $coupon_remain_price;//취소총금액
										}else{//사용
											$data['coupon_refund_type']		= 'price';
											$data['socialcp_return_disabled'] = true;
											
											if ( $data['socialcp_input_type'] == 'price' ) {//금액
												$coupon_remain_price_tmp			= (int) $data['coupon_remain_value'];
												$coupon_deduction_price_tmp	= (int) $data['coupon_value'];
											}else{//횟수
												$coupon_remain_price_tmp			= (int) (100 * ($data['coupon_input_one'] * $data['coupon_remain_value']) / 100);
												$coupon_deduction_price_tmp	= (int) ($data['coupon_input_one'] * $data['coupon_value']);
											}
											$coupon_remain_real_percent = 100 * ($coupon_remain_price_tmp / $coupon_deduction_price_tmp);//잔여값어치율
											
											//실제결제금액
											$coupon_remain_price			= (int) ($data['socialcp_refund_cancel_percent'] * ($coupon_remain_price_tmp) / 100);
											$coupon_deduction_price	= (int) ($coupon_real_total_price) - $coupon_remain_price;
											//$cancel_total_price  += $coupon_remain_price;//취소총금액
										}
							}else{
								$data['rt_ea'] = 0;
								$data['coupon_refund_type']		= 'price';
								$data['socialcp_return_disabled'] = true;
							}
							
						}
						
						$cancel_memo = socialcp_cancel_memo($data, $coupon_remain_real_percent, $coupon_real_total_price, $coupon_remain_real_price, $coupon_remain_price, $coupon_deduction_price);
						//echo "유효기간전";
						//debug_var($data['socialcp_return_disabled']);
						//debug_var($data['socialcp_refund_use']);
						//debug_var($data['socialcp_refund_cancel_percent']);
						//debug_var($coupon_refund_emoney);
						//debug_var("coupon_remain_price_tmp=>".$coupon_remain_price_tmp);
						//debug_var("coupon_deduction_price_tmp=>".$coupon_deduction_price_tmp);
						//debug_var("coupon_remain_real_percent=>".$coupon_remain_real_percent);
						//debug_var("coupon_remain_real_price=>".$coupon_remain_real_price);
						//debug_var("coupon_remain_price=>".$coupon_remain_price);
						//debug_var("coupon_deduction_price=>".$coupon_deduction_price);
						//debug_var("cancel_memo=>".$cancel_memo);//$this->call_exit();
					}
					
					//$data['coupon_refund_emoney']		= $coupon_refund_emoney;//티켓상품 잔여 값어치의 실제금액
					$data['coupon_remain_price']			= $coupon_remain_price;//티켓상품 결제금액의 실제금액
					$data['coupon_deduction_price']		= $coupon_deduction_price;//티켓상품 결제금액의 공제금액
					$data['cancel_memo']		= $cancel_memo;//티켓상품 결제금액의 공제금액
				}else{
					$goodstotal++;
				}
				//if($cfg_order['buy_confirm_use'] && $data_export['buy_confirm']!='none') $data['rt_ea'] = 0;
				
				//청약철회상품체크
				unset($goods);
				$goods = $this->CI->goodsmodel->get_goods($data['goods_seq']);
				$data['cancel_type'] = $goods['cancel_type'];
				
				unset($data['inputs']);
				$data['inputs']	= $this->CI->ordermodel->get_input_for_option($data['item_seq'], $data['option_seq']);

				// 무료배송여부가 잘못 노출되어 배송비가 0 이상인 경우 N으로 처리 :: 2018-07-25 pjw
				$data['shiping_free_yn'] = $data['shipping_cost'] == 0 ? 'Y' : 'N';

				// 구매확정후 반품을 위해 변수 저장 by hed #32095
				$data['keep_rt_ea'] = $data['rt_ea'];
				
				# 구매확정 사용시 : 지급예정수량(출고수량-지급예정반품수량-지급수량-소멸수량)
				if($cfg_order['buy_confirm_use'] && $data['reserve_ea']==0) $data['rt_ea'] = 0;

				// 구매확정후 반품가능 처리 by hed #32095
				if($cfg_order['buy_confirm_use'] && $type == 'return' && $data['rt_ea'] == 0){
					$data['rt_ea'] = $data['keep_rt_ea'];
					// 구매확정 후 환불 여부 by hed #32095
					$after_refund = ($data['rt_ea'])?'1':'';
					$this->CI->template->assign(array('after_refund'		=> $after_refund));
				}

				$ex_code_shipping_provider_seq = $data_export['item'][0]['shipping_provider_seq']."_".$data_export['item'][0]['export_code'];
				$loop[$ex_code_shipping_provider_seq]['export_item'][] = $data;
				$loop[$ex_code_shipping_provider_seq]['tot_rt_ea'] += $data['rt_ea'];
					
			}
		}
		
		if ( $get_mode == 'return_coupon' ) {
			if (!$coupontotal || empty($coupontotal) ){
				echo null;
				//$this->CI->template->assign('backalert',true);
				//$msg = "환불신청 티켓상품이 없습니다.!";
				//echo js('alert("'.$msg.'");setTimeout(function(){closeDialog("order_refund_layer");},300);');//exit;
			}
		}elseif( !$goodstotal || empty($goodstotal) ) {
			echo null;
			/*
			 $this->CI->template->assign('backalert',true);
			 if($get_mode == 'exchange') {
			 $msg = "맞교환신청 상품이 없습니다!";
			 }else{
			 $msg = "반품신청 상품이 없습니다!";
			 }
			 echo js('alert("'.$msg.'");setTimeout(function(){closeDialog("order_refund_layer");},300);');//exit;
			 */
		}
		
		if(($coupontotal > 0 && !empty($coupontotal)) || ($goodstotal > 0 && !empty($goodstotal))){
			foreach($loop as $ex_code_shipping_provider_seq=>$v){
				list($shipping_provider_seq, $export_code) = explode("_",$ex_code_shipping_provider_seq);
				$grp_sql = "SELECT refund_address_seq,refund_scm_type FROM fm_shipping_grouping WHERE shipping_provider_seq = {$shipping_provider_seq} AND default_yn = 'Y' LIMIT 1";
				$grpping = $this->CI->db->query($grp_sql);
				$grpping = $grpping->row_array();
				$grp_seq = $grpping['refund_address_seq'];
				$grp_scm_type = $grpping['refund_scm_type'];
				$address = $this->CI->shippingmodel->get_shipping_address($grp_seq, $grp_scm_type);
				
				$return_address = '';
				
				if($address['address_street']){
					$return_address = $address['address_street'];
					$deli_address1	= $address['address_street'];
				}else{
					$return_address = $address['address'];
					$deli_address1	= $address['address'];
				}
				$return_address .= " ".$address['address_detail'];
				$deli_address2	= $address['address_detail'];
				
				
				$loop[$ex_code_shipping_provider_seq]['shipping_provider'] = $this->CI->providermodel->get_provider($shipping_provider_seq);
				
				$loop[$ex_code_shipping_provider_seq]['shipping_provider']['deli_zipcode'] = $address['address_zipcode'];
				$loop[$ex_code_shipping_provider_seq]['shipping_provider']['deli_address1'] = $deli_address1;
				$loop[$ex_code_shipping_provider_seq]['shipping_provider']['deli_address2'] = $deli_address2;
				
				$loop[$ex_code_shipping_provider_seq]['return_address'] = $return_address;
			}

			// 비현금성 주문일 경우 환불방법 미노출 처리 (신용카드, 휴대폰결제) :: 2018-07-20
			$show_refund_method = 'Y';
			if($orders['payment'] == 'card' || $orders['payment'] == 'cellphone'){
				$show_refund_method = 'N';
			}
			$orders['show_refund_method'] = $show_refund_method;
			
			if($get_mode == 'return_coupon') {//티켓상품 환불
				$file_path = str_replace('order_return','order_return_coupon',$file_path);
			}
		}

		
		$out['orders']				= $orders;
		$out['loop']				= $loop;
		$out['items']				= $items;
		$out['cancel_total_price']	= $cancel_total_price;
		$out['gift_cnt']			= $gift_cnt;
		$out['npay_use']			= $npay_use;
		$out['npay_reasons']		= $npay_reasons;
		return $out;
	}


	
	// 주문 환불 요청
	public function proc_order_return($post_params, $send_for_user = true){
		$this->CI->load->model('returnmodel');
		$this->CI->load->model('exportmodel');

		$cfg_order			= config_load('order');
		$data_order			= $this->CI->ordermodel->get_order($post_params['order_seq']);
		$data_order_items	= $this->CI->ordermodel->get_item($post_params['order_seq']);

		$minfo			= $this->CI->session->userdata('manager');
		$manager_seq	= $minfo['manager_seq'];

		// npay 주문건 확인
		$npay_use = npay_useck();	//Npay v2.1 사용여부
		if($npay_use && $data_order['npay_order_id']){
			$this->CI->load->model("naverpaymodel");
			$arr_consumer_imputation = array("INTENT_CHANGED","COLOR_AND_SIZE","WRONG_ORDER");
		}else{
			$npay_use = false;
		}

		if($post_params['mode']=='exchange'){
			$mode_title		= "맞교환";
			$logTitle		= "맞교환신청";
		}else{
			$mode_title		= "반품";
			$logTitle		= "반품신청";
			$post_params['mode']	= "return";
		}

		$chk_seq	= $post_params['chk_seq'];
		$chk_ea		= $post_params['chk_ea'];

		if(!$chk_seq){
			openDialogAlert($logTitle."할 상품을 선택해주세요.",400,140,'parent');
			$this->call_exit();
		}

		// 반품 배송비 무결성 체크 :: 2018-05-21 lwh
		if ($post_params['reason'] == '120'){
			$post_params['refund_ship_duty'] = 'buyer';
		}else{
			$post_params['refund_ship_duty'] = 'seller';
		}

		// 반품완료 시 구매자부담에 실결제가격이 반품배송비보다 적은경우 처리안되게함 :: 2018-07-16 pjw
		// 총 반송배송비
		$total_pay_shipping = 0;
		foreach($post_params['pay_shiping_cost'] as $pay_shipping){
			$total_pay_shipping += $pay_shipping;
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
			
			$option_seq				= $post_params['chk_option_seq'][$k];
			$suboption_seq			= $post_params['chk_suboption_seq'][$k];

			$option_data			= $this->CI->ordermodel->get_order_item_option($option_seq);
			$suboption_data			= $this->CI->ordermodel->get_order_item_suboption($suboption_seq);
			
			$total_payment_amount	+= $option_data['sale_price'] * $return_apply_ea;
			$total_payment_amount	+= $suboption_data['sale_price'] * $return_apply_ea;

		}

		if($total_payment_amount < $total_pay_shipping && $post_params['refund_ship_duty'] == 'buyer' && $post_params['refund_ship_type'] == 'M'){
			openDialogAlert(getAlert('mo154'),400,140,'parent',$callback);
			$this->call_exit();
		}

		foreach($chk_ea as $k => $return_apply_ea){

			if($return_apply_ea == 0){
				openDialogAlert($mode_title." 수량을 0건으로 입력한 경우에는 신청되지 않습니다.",400,140,'parent');
				$this->call_exit();
			}

			$export_code			= $post_params['chk_export_code'][$k];
			$item_seq				= $post_params['chk_item_seq'][$k];
			$option_seq				= $post_params['chk_option_seq'][$k];
			$suboption_seq			= $post_params['chk_suboption_seq'][$k];
			$able_return_ea			= 0;
			$cancel_type			= false;	//청약철회상품체크

			$orditemData			= $this->CI->ordermodel->get_item_one($item_seq);

			//청약철회상품체크(반품불가)
			/*
			$goodscanceltype = $this->CI->goodsmodel->get_goods($orditemData['goods_seq']);
			if( $goodscanceltype['cancel_type']) $cancel_type = true;

			if($cancel_type){
				openDialogAlert("청약철회 상품은 ".$mode_title."이 [불가능]합니다.",400,140,'parent');
				$this->call_exit();
			}
			*/

			## 출고수량
			$exp_data			= $this->CI->exportmodel->get_export_item_ea($export_code,$option_seq,$suboption_seq);

			if(!$suboption_seq) $return_item = $this->CI->returnmodel->get_return_item_ea($item_seq,$item_option_seq);
				else $return_item = $this->CI->returnmodel->get_return_subitem_ea($item_seq,$suboption_seq);

			$able_return_ea	= $exp_data['ea'] - $return_item['ea'];

			if($able_return_ea == 0){
				openDialogAlert($mode_title." 가능한 수량이 없습니다.",400,140,'parent');
				$this->call_exit();
			}

			if($able_return_ea < $return_apply_ea){
				openDialogAlert($mode_title." 수량이 ".$mode_title."가능수량보다 많습니다.",400,140,'parent');
				$this->call_exit();
			}


			$post_params['scm_supply_price'][$k]	= $exp_data['scm_supply_price'];

			$partner_return['items'][$k]	= true;

			$post_params['npay_order_id']			= '';
			$post_params['npay_flag']				= '';

			## npay 사용시 api 반품 접수(상품주문번호,반품사유코드,수거배송방법코드)
			if($post_params['mode']=='return'){

				# 추가옵션이 모두 반품된 후 필수옵션반품 가능.
				$kk = count($post_params['chk_npay_product_order_id']) - ($k + 1);
				$npay_product_order_id	= $post_params['chk_npay_product_order_id'][$kk];	//npay 상품주문번호
				if($npay_product_order_id && $npay_use){
					$npay_params = array("npay_product_order_id"=>$npay_product_order_id,
										"order_seq"			=>$data_order['order_seq'],
										"actor"				=>$this->CI->managerInfo['mname'],
										"reason"			=>$post_params['reason'],
										"return_method"		=>$post_params['return_method']);
					$npay_res = $this->CI->naverpaymodel->order_return($npay_params);
					if($npay_res['result'] != "SUCCESS"){
						$items[$k]['partner_return']	= false;
						$partner_return['items'][$k]	= false;
						$partner_return['msg'][]		= $npay_product_order_id." : ".$npay_res['message'];
						$partner_return['fail_cnt']++;
					}else{
						$npay_result_msg				= '';
					}
					$post_params['npay_order_id'] = $data_order['npay_order_id'];
					# 구매자 귀책사유시 보류 처리
					if(in_Array($post_params['reason'],$arr_consumer_imputation)){
						$post_params['npay_flag']		= 'return_deliveryfee';
					}else{
						$post_params['npay_flag']		= 'return_request';
					}
				}
			}
			$post_params['partner_return'][$k]	= $partner_return['items'][$k];

		}

		// 사은품 있는 경우 확인 필요
		$gift_order = false;
		foreach($data_order_items as $item){
			if($item['goods_type'] == 'gift') {
				// option_seq 찾기
				list($gift) = $this->CI->ordermodel->get_option_for_item($item['item_seq']);
				$chk = array();
				$chk['item_seq']		= $item['item_seq'];
				$chk['option_seq']		= $gift['item_option_seq'];
				
				// export_data 찾기
				$gexport = $this->CI->exportmodel->get_export_item_by_item_seq('',$chk);
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
			$query = $this->CI->db->query($query,$where);
			$able_return_total	= 0;
			foreach($query->result_array() as $exp_item){
				## 구매확정 사용시 : 지급예정수량(출고완료+배송중+배송완료)
				## 구매확정 미사용시 : 출고수량(출고완료 + 배송중 + 배송완료) - 반품수량
				if($cfg_order['buy_confirm_use']){
					$able_return_total	+= $exp_item['reserve_ea'];
				}else{
					
					## 반품수량
					if(!$suboption_seq) $return_item = $this->CI->returnmodel->get_return_item_ea($exp_item['item_seq'],$exp_item['option_seq']);
						else $return_item = $this->CI->returnmodel->get_return_subitem_ea($exp_item['item_seq'],$exp_item['suboption_seq']);
					$able_return_total	+= $exp_item['ea'] - $return_item['ea'];
				}
			}

			$this->CI->load->model('giftmodel');
			// 취소 가능 수량 : $able_return_total
			// 취소 요청 수량 : $cancel_total_ea
			// 사은품 수량 : $order_gift_ea

			if( $able_return_total == $cancel_total_ea + $order_gift_ea ) {
				// 전체 취소 시 - 사은품도 함께 취소 요청
				$cancel_total_ea += $order_gift_ea;
				foreach($gift_item as $v => $gift) {
					$chk_seq[]						= '1';
					$post_params['chk_seq'][]				= '1';
					$post_params['chk_item_seq'][]		= $gift['item_seq'];
					$post_params['chk_option_seq'][]		= $gift['option_seq'];
					$post_params['chk_suboption_seq'][]	= '';
					$post_params['chk_ea'][]				= $gift['ea'];
					$post_params['chk_export_code'][]		= $gift['export_code'];
				}
			} else {
				$gift_cancel = $this->CI->ordermodel->order_gift_partial_cancel($post_params['order_seq'], $gift_item_seq, $data_order_items,'return');

				// _POST 변수 담아서 실제 사은품 취소 처리
				if(count($gift_cancel) > 0) {
					foreach($gift_cancel as $key => $gift) {
						$chk_seq[]						= '1';
						$post_params['chk_seq'][]				= '1';
						$post_params['chk_item_seq'][]		= $gift['item_seq'];
						$post_params['chk_option_seq'][]		= $gift['item_option_seq'];
						$post_params['chk_suboption_seq'][]	= '';
						$post_params['chk_ea'][]				= $gift['ea'];
						$post_params['chk_export_code'][]		= $gift['export_code'];
					}
				}
			}
		}

		if($post_params['bank'])			$bank		= $post_params['bank'];		else $bank		= "";
		if($post_params['account'])		$account	= $post_params['account'];	else $account	= "";
		if(!$post_params['depositor'])	$depositor	= "";					else $depositor = $post_params['depositor'];
		$post_params['refund_method'] = ($post_params['refund_method'])?$post_params['refund_method']:'bank';

		//출고건 배송완료 처리, 마일리지 지급 관련 정리
		$give_reserve_ea = $this->CI->returnmodel->order_return_delivery_confirm($cfg_order,$post_params);

		// 환불 등록
		if(!$npay_use && $bank){
			$tmp		= code_load('bankCode',$bank);
			$bank		= $tmp[0]['value'];
			if($account) $account	= implode('-',$account);
		}

		$post_params['refund_method'] = ($post_params['refund_method'])?$post_params['refund_method']:'bank';

		$items					= array();
		$pay_shiping_cost		= array();
		foreach($chk_seq as $k=>$v){

			$items[$k]['item_seq']					= $post_params['chk_item_seq'][$k];
			$items[$k]['option_seq']				= $post_params['chk_suboption_seq'][$k] ? '' : $post_params['chk_option_seq'][$k];
			$items[$k]['suboption_seq']				= $post_params['chk_suboption_seq'][$k];
			$items[$k]['ea']						= $post_params['chk_ea'][$k];
			$items[$k]['npay_product_order_id']		= $post_params['chk_npay_product_order_id'][$k];
			$items[$k]['partner_return']			= $post_params['partner_return'][$k];

			if($items[$k]['partner_return']){

				$export_code = $post_params['chk_export_code'][$k];

				## 지급한 마일리지&포인트 뽑아오기. 2015-03-31 pjm
				if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){
					$option_seq = $items[$k]['option_seq'];
					$option_type = "OPT";
				}else{
					$option_seq = $items[$k]['suboption_seq'];
					$option_type = "SUB";
				}

				$post_params['give_reserve_ea'][$k] = $give_reserve_ea[$export_code][$option_type][$option_seq];
				if($post_params['give_reserve_ea'][$k] > 0){
					$reserve			= $this->CI->ordermodel->get_option_reserve($option_seq,'reserve',$option_type);
					$point				= $this->CI->ordermodel->get_option_reserve($option_seq,'point',$option_type);
					$give_reserve		= $reserve * $post_params['give_reserve_ea'][$k];
					$give_point			= $point * $post_params['give_reserve_ea'][$k];
					$tot_give_reserve	+= $give_reserve;
					$tot_give_point		+= $give_point;
				}else{
					$give_reserve		= 0;
					$give_point			= 0;
					$give_reserve_ea	= 0;
				}

				$items[$k]['give_reserve']		= $post_params['give_reserve'][$k]		= $give_reserve;
				$items[$k]['give_point']		= $post_params['give_point'][$k]			= $give_point;
				$items[$k]['give_reserve_ea']	= $post_params['give_reserve_ea'][$k];
				$pay_shiping_cost[$export_code] = (float)$post_params['pay_shiping_cost'][$k];

				if($items[$k]['option_seq'] && !$items[$k]['suboption_seq']){

					$mode = 'option';

					// 반품으로 인한 원주문 추출 및 교체 :: 2014-11-27 lwh
					// @pjm 설명 덧붙임 : 교환 재주문건 반품 시 최상위 원주문의 환불로 생성됨.
					$query = $this->CI->db->get_where('fm_order_item_option',
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
					$query = $this->CI->db->query($query,array($items[$k]['option_seq']));
					$optionData = $query->row_array();
					*/

					if($post_params['mode']!='exchange'){
						$this->CI->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
						$this->CI->db->where('item_option_seq',$items[$k]['option_seq']);
						$this->CI->db->update('fm_order_item_option');
					}
				}else if($items[$k]['suboption_seq']){

					$mode = 'suboption';

					// 반품으로 인한 원주문 추출 및 교체 :: 2014-11-27 lwh
					// @pjm 설명 덧붙임 : 교환 재주문건 반품 시 최상위 원주문의 환불로 생성됨.
					$query = $this->CI->db->get_where('fm_order_item_suboption',
						array(
						'item_suboption_seq'=>$items[$k]['suboption_seq'])
					);
					$result = $query -> result_array();

					if($result[0]['top_item_suboption_seq'])
						$items[$k]['suboption_seq'] = $result[0]['top_item_suboption_seq'];

					/*
					$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
					$query = $this->CI->db->query($query,array($items[$k]['suboption_seq']));
					$optionData = $query->row_array();
					*/

					if($post_params['mode']!='exchange'){
						$this->CI->db->set('refund_ea','refund_ea+'.$items[$k]['ea'],false);
						$this->CI->db->where('item_suboption_seq',$items[$k]['suboption_seq']);
						$this->CI->db->update('fm_order_item_suboption');
					}
				}
			}

		}

		// 환불배송비 계산 :: 2018-05-21 lwh
		$post_params['return_shipping_price']		= ($post_params['refund_ship_type']) ? array_sum($pay_shiping_cost) : 0;

		//외부몰(npay) 반품접수 실패건수가 있을때
		if($npay_use && $post_params['mode']=='return' && $partner_return['fail_cnt']> 0){
			//반품접수 전체 실패시 오류메세지 띄움
			if((count($items) - $partner_return['fail_cnt']) <= 0){
				if(count($partner_return['msg']) < 1) $h = 140; else $h = 150 + (count($partner_return['msg'])*18);
				openDialogAlert("<span class=\'fx12\'>Npay 반품접수 실패!<br /><span class=\'red\'>".implode("<br />",$partner_return['msg'])."</span></span>",460,$h,'parent');
				$this->call_exit();
			}
		}

		if($post_params['mode']=='exchange'){
			$refund_code = '0';
			$return_type = 'exchange';
		}else{
			// 맞교환으로 인한 재주문을 반품신청시 최상위 주문번호 저장
			if($data_order['top_orign_order_seq'])
				$orgin_order_seq = $data_order['top_orign_order_seq'];
			else
				$orgin_order_seq = $post_params['order_seq'];

			// 반품시 최상위 주문번호 저장 :: 2014-11-27 lwh
			// @pjm 설명 덧 붙임 : 교환으로 인한 재주문건은 주문금액 없음. 환불은 최상위 원주문에만 생성함.
			if($data_order['top_orign_order_seq'])
				$orgin_order_seq = $data_order['top_orign_order_seq'];
			else
				$orgin_order_seq = $post_params['order_seq'];
			
			// 구매확정 후 환불 여부 by hed #32095
			$after_refund = $post_params['after_refund'];
			if($after_refund!='1'){
				unset($after_refund);
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
			
			$refund_code	= $this->CI->refundmodel->insert_refund($data,$items);
			$return_type	= 'return';

			$logTitle		= "환불신청(".$refund_code.")";
			$logDetail		= "관리자 반품신청에 의한 환불신청이 접수되었습니다.";
			$logParams		= array('refund_code' => $refund_code);
			$this->CI->ordermodel->set_log($orgin_order_seq,'process',$this->CI->managerInfo['mname'],$logTitle,$logDetail,$logParams,'');
		}

		// 환불, 반품(&교환) DB Insert
		$return_code = $this->CI->returnmodel->order_return_insert($post_params,$refund_code,$return_type,$partner_return);

		if(!$return_code){
			$res_msg = " 실패";
			$items[$k]['return_badea']		= '0';					// 불량재고로 반품 ( scm )
			$items[$k]['scm_supply_price']	= $scm_supply_price[$k];// 출고당시 출고창고 평균매입가 ( scm )
		}

		if($post_params['mode']=='exchange'){
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
		$this->CI->ordermodel->set_log($post_params['order_seq'],'process',$this->CI->managerInfo['mname'],$logTitle,$logDetail,$logParams,'');

		$result['return_code'] = $return_code;
		$result['refund_code'] = $refund_code;
		
		//npay 주문건 아니거나 npay 주문, 반품일때만 (교환은 shop에서 접수 불가)
		if(!$npay_use || ($npay_use && $post_params['mode']=='return')){

			$callback = "
			parent.closeDialog('order_return_layer');
			parent.document.location.reload();";
			openDialogAlert($title,400,140,'parent',$callback);
			
			return $result;
		}else{

			// npay 주문건 교환 접수 일때
			return $result;
		}
	}
	/**
	 * 반품 신청 정보 조회 by hed
	 * @param type $return_code
	 * @return type
	 */
	function get_return($return_code){
		$this->CI->load->model('returnmodel');
		$this->CI->load->model('ordermodel');
		$this->CI->load->model('accountallmodel');

		$data_return 		= $this->CI->returnmodel->get_return($return_code);

		//반품코드로 등록된 데이터가 없을 경우 이전페이지로 이동 pjw
		if( is_null($data_return) ) {
			pageBack("존재하지 않는 데이터 입니다.", 'self', $this->allow_exit);
			$this->call_exit();
		}

		$data_order			= $this->CI->ordermodel->get_order($data_return['order_seq']);

		// 사유코드
		$reasons			= code_load('return_reason');
		$qry				= "select * from fm_return_reason where return_type='coupon' order by idx asc";
		$query				= $this->CI->db->query($qry);
		$reasoncouponLoop	= $query -> result_array();
		$qry				= "select * from fm_return_reason where return_type!='coupon' order by idx asc";
		$query				= $this->CI->db->query($qry);
		$reasonLoop			= $query -> result_array();

		$this->CI->load->helper('order');
		$this->CI->load->model('refundmodel');
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('giftmodel');
		$this->CI->load->model('providermodel');
		$this->CI->load->model('orderpackagemodel');

		// 물류관리 관련 설정정보 추출
		if	(!$this->CI->scm_cfg)		$this->CI->scm_cfg			= config_load('scm');

		$data_return_item 	= $this->CI->returnmodel->get_return_item($return_code);
		$process_log 		= $this->CI->ordermodel->get_log($data_return['order_seq'],'process',array('return_code'=>$return_code));

		//npay 사용여부 확인, 취소사유 코드 불러오기
		$npay_use = npay_useck();
		if($npay_use && $data_return['npay_order_id']){
			$this->CI->load->library('naverpaylib');
			$reasonLoop = $this->CI->naverpaylib->get_npay_return_reason();
			$npay_return_hold	= $this->CI->naverpaylib->get_npay_code("return_hold");
			//debug($npay_return_hold);

			if($npay_return_hold[strtoupper($data_return['npay_flag'])]){
				$data_return['npay_flag_msg'] = $npay_return_hold[strtoupper($data_return['npay_flag'])];
			}else{
				$data_return['npay_flag_msg'] = '';
			}
		}

		// 개인정보 조회 로그
		//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderexcel', 'exportexcel'
		$this->CI->load->model('logPersonalInformation');
		$this->CI->logPersonalInformation->insert('return',$this->CI->managerInfo['manager_seq'],$data_return['return_seq']);

		$tmp = $this->CI->refundmodel->get_refund($data_return['refund_code']);
		$data_return['mrefund_status']	= $this->CI->refundmodel->arr_refund_status[$tmp['status']];
		$data_return['mstatus'] 		= $this->CI->returnmodel->arr_return_status[$tmp['status']];

		if(!$npay_use || !$data_return['npay_order_id']){
			if( $data_return['phone'] )  $data_return['phone'] = explode('-',$data_return['phone']);
			if( $data_return['cellphone'] )$data_return['cellphone'] = explode('-',$data_return['cellphone']);
		}

		if( $data_return['sender_zipcode'] )$data_return['sender_zipcode'] = explode('-',$data_return['sender_zipcode']);

		$return_provider = 1;
		foreach($data_return_item as $key => $item){
			// 물류관리 창고 정보 추출
			if	($this->CI->scm_cfg['use'] == 'Y' && $item['provider_seq'] == '1'){
				if($item['opt_type'] == 'sub'){
					unset($sc);
					if	($item['title'])		$sc['suboption_title']	= $item['title1'];
					if	($item['option1'])		$sc['suboption']		= $item['option1'];
					$optionData			= $this->CI->goodsmodel->get_goods_suboption($item['goods_seq'], $sc);
					if	($optionData[0][0]['suboption_seq'] > 0){
						$optionStr		= $item['goods_seq'] . 'suboption' . $optionData[0][0]['suboption_seq'];
					}
				}else{
					unset($sc);
					if	($item['option1'])	$sc['option1']		= $item['option1'];
					if	($item['option2'])	$sc['option2']		= $item['option2'];
					if	($item['option3'])	$sc['option3']		= $item['option3'];
					if	($item['option4'])	$sc['option4']		= $item['option4'];
					if	($item['option5'])	$sc['option5']		= $item['option5'];
					$optionData			= $this->CI->goodsmodel->get_goods_option($item['goods_seq'], $sc);
					if	($optionData[0]['option_seq'] > 0){
						$optionStr		= $item['goods_seq'] . 'option' . $optionData[0]['option_seq'];
					}
				}
				$data_return_item[$key]['optioninfo']	= $optionStr;
			}

			$goods_cnt[$item['goods_seq']]++;
			$tot['ea']  		+= $item['ea'];
			$tot['return_ea']	+= $item['return_ea'];

			//반품완료 처리시 재고 증가여부 선택용 2015-03-30 pjm
			for($k=0; $k <= $item['ea']; $k++){
				$item['eaLoop'][] = $k;
			}
			$data_return_item[$key]['eaLoop'] = $item['eaLoop'];

			if(!$item['stock_return_ea']){
				$data_return_item[$key]['stock_return_ea'] = $item['ea'];
			}
			if($item['package_stock_return_ea']){
				$data_return_item[$key]['package_stock_return_ea'] = unserialize($item['package_stock_return_ea']);
			}

			if( $item['reason_code'] > 100 && $item['reason_code'] < 200 ) $tot['user_reason_cnt'] += $item['ea'];
			if( $item['reason_code'] > 200 && $item['reason_code'] < 300 ) $tot['goods_reason_cnt'] += $item['ea'];
			if( $item['reason_code'] > 300 ) $tot['shop_reason_cnt'] += $item['ea'];

			if	($item['goods_kind'] == 'coupon'){
				$data_return_item[$key]['couponinfo'] = get_goods_coupon_view($item['export_code']);
			}

			## 사은품
			$item['gift_title'] = "";
			if($item['goods_type'] == "gift"){
				$giftlog = $this->CI->giftmodel->get_gift_title($data_return['order_seq'],$item['item_seq']);
				$data_return_item[$key]['gift_title'] = $giftlog['gift_title'];
			}

			//청약철회상품체크
			$ctgoods = $this->CI->goodsmodel->get_goods($item['goods_seq']);
			$data_return_item[$key]['cancel_type'] = $ctgoods['cancel_type'];

			$data_return_item[$key]['reasons']		= $reasons;
			$data_return_item[$key]['reasonLoop']	= ($item['goods_kind'] == 'coupon' )?$reasoncouponLoop:$reasonLoop;

			$data_return_item[$key]['refunditem']	= $this->CI->refundmodel->get_refund_item_data($data_return['refund_code'],$item['item_seq'], $item['option_seq']);
			$data_return_item[$key]['inputs'] = $this->CI->ordermodel->get_input_for_option($item['item_seq'], $item['option_seq']);

			// Npay 반품건일시 반품사유 코드
			if($npay_use && $item['npay_product_order_id']){
				$reason_desc = explode("@",$data_return_item[$key]['reason_desc']);
				if(count($reason_desc)>1){
					$data_return_item[$key]['reason_code'] = $reason_desc[0];
					$data_return_item[$key]['reason_desc'] = $reason_desc[1];
				}else{
					$data_return_item[$key]['reason_code'] = $item['reason_desc'];
				}
				
				// Npay 반품건일시 반품 부담
				if(empty($data_return['refund_ship_duty'])) {
				    $npay_ship_duty = $this->CI->returnmodel->get_npay_ship_duty($data_return_item[$key]['reason_code']);
				    if(!empty($npay_ship_duty)) {
				        $data_return['refund_ship_duty'] = $npay_ship_duty;
				    }
				}
			}

			## 반품item 입점사 정보 - 2015-06-15 pjm 수정
			if($item['provider_seq'] > 1){
				$return_provider = $item['provider_seq'];
			}

			if($item['package_yn'] == 'y' && $item['opt_type'] == 'opt'){
				$item['packages'] = $this->CI->orderpackagemodel->get_option($item['option_seq']);
				foreach($item['packages'] as $key_package=>$data_package){
					$stock = (int) $data_package['stock'];
					$badstock = (int) $data_package['badstock'];
					$reservation = (int) $data_package['reservation'.$this->CI->cfg_order['ableStockStep']];
					$ablestock = $stock - $badstock - $reservation;
					$item['packages'][$key_package]['ablestock'] = $ablestock;
					$optionStr = $data_package['goods_seq']."option".$data_package['option_seq'];
					$item['packages'][$key_package]['optioninfo'] = $optionStr;
					$package_option_code = 'option'.$data_package['package_option_seq'];
					$item['packages'][$key_package]['package_option_code'] = $package_option_code;
					$arr_package_return_badea = unserialize($item['package_return_badea']);
					$item['packages'][$key_package]['return_badea'] = $arr_package_return_badea[$package_option_code];

					if($data_return['status'] != "complete" && !$item['package_stock_return_ea']){
						$data_return_item[$key]['package_stock_return_ea'][$package_option_code] = $item['ea'];
					}

				}
				$data_return_item[$key]['packages'] = $item['packages'];
			}

			if($item['package_yn'] == 'y' && $item['opt_type'] == 'sub'){
				$item['packages'] = $this->CI->orderpackagemodel->get_suboption($item['option_seq']);
				foreach($item['packages'] as $key_package=>$data_package){
					$stock = (int) $data_package['stock'];
					$badstock = (int) $data_package['badstock'];
					$reservation = (int) $data_package['reservation'.$this->CI->cfg_order['ableStockStep']];
					$ablestock = $stock - $badstock - $reservation;
					$item['packages'][$key_package]['ablestock'] = $ablestock;
					$optionStr = $data_package['goods_seq']."option".$data_package['option_seq'];
					$item['packages'][$key_package]['optioninfo'] = $optionStr;
					$package_option_code = 'suboption'.$data_package['package_suboption_seq'];
					$item['packages'][$key_package]['package_option_code'] = $package_option_code;
					$arr_package_return_badea = unserialize($item['package_return_badea']);
					$item['packages'][$key_package]['return_badea'] = $arr_package_return_badea[$package_option_code];

					if($data_return['status'] != "complete" && !$item['package_stock_return_ea']){
						$data_return_item[$key]['package_stock_return_ea'][$package_option_code] = $item['ea'];
					}

				}
				$data_return_item[$key]['packages'] = $item['packages'];
			}

		}

		if($goods_ea) $tot['goods_cnt'] = array_sum($goods_cnt);
		$data_return['mstatus'] = $this->CI->returnmodel->arr_return_status[$data_return['status']];
		$data_return['mreturn_type'] = $this->CI->returnmodel->arr_return_type[$data_return['return_type']];

		// 반품배송비 책임 예외처리 :: 2018-05-23 lwh
		// 오픈마켓 이슈로 reason_code 가 없으면 빈값으로 들어가게 처리 :: 2018-07-19 pjw
		if (!$data_return['refund_ship_duty']){
			if ($data_return['reason_code'] == '120'){
				$data_return['refund_ship_duty'] = 'buyer';
			}else if($data_return['reason_code'] == '210' || $data_return['reason_code'] == '310'){
				$data_return['refund_ship_duty'] = 'seller';
			}
		}
		// 반품 배송비 주체 결정 :: 2018-05-23 lwh
		if ($data_return['status'] == 'request'){
			if			($return_provider == '1' || $data_return['refund_ship_type'] == 'A'){
				$data_return['return_shipping_gubun'] = 'company';
			}else if	($data_return['refund_ship_type'] == 'D'){
				$data_return['return_shipping_gubun'] = 'provider';
			}
		}

		$out['process_log']			= $process_log;
		$out['data_return']			= $data_return;
		$out['data_return_item']	= $data_return_item;
		$out['tot']					= $tot;
		$out['data_order']			= $data_order;
		$out['npay_use']			= $npay_use;

		// 반품완료건일경우 정산전이면 반품배송비 수정가능하도록 처리
		if($data_return['status']=='complete'){
			// 반품상품들의 출고코드
			$arr_export_code = array();
			foreach($data_return_item as $item) if($item['export_code']) $arr_export_code[] = $item['export_code'];
			if($arr_export_code){
				// 출고코드중 정산된건이 하나라도 있는지 여부 체크
				$query = $this->CI->db->query("select count(*) as cnt from fm_goods_export where export_code in ('".implode("','",$arr_export_code)."') and account_gb='complete'");
				$query = $query->row_array();
				$cnt = $query['cnt'];

				// 반품완료이면서 정산전일때
				if($cnt==0){
					$able_return_shipping_price = true;	// 반품배송비 수정가능
					$out['able_return_shipping_price']			= $able_return_shipping_price;
				}
			}
		}

		// 물류관리 창고 선택 plugin관련 option정보
		if	(!$this->CI->config_system)	$this->CI->config_system	= config_load('system');
		if	($data_return['wh_seq'] > 0)	$return_wh_seq	= $data_return['wh_seq'];
		else								$return_wh_seq	= $this->CI->scm_cfg['return_wh'];
		$scmOption	= array(
			'boxName'							=> 'scm_wh',
			'goodsinfoSelector'					=> '.optioninfo',
			'locCodeSelector'					=> '.location-code-title',
			'locCodeInputer'					=> '.location_code_val',
			'locPositionInputer'				=> '.location_position_val',
			'showhideSelectorReverseLocation'	=> '.btn-select-warehouse',
			'defaultValue'						=> $return_wh_seq
		);
		
		$out['scm_cfg']			= $this->CI->scm_cfg;
		$out['scmOption']			= $scmOption;

		## 반품item 입점사 정보 - 2015-06-15 pjm 수정
		$provider = $this->CI->providermodel->get_provider_one($return_provider);
		$provider['provider_seq'] = $return_provider;
		$out['provider']			= $provider;
		
		return $out;
	}
	
	public function proc_return_save($post_params){
		
		$this->CI->load->helper('order');

		$return_code		= $post_params['return_code'];
		$order_seq			= $post_params['order_seq'];
		$not_connect_scm	= $post_params['not_connect_scm']; // o2o 창고의 미연결 상태 Y:미연결
		$data_return		= $this->CI->returnmodel->get_return($return_code);
		$data_return_item	= $this->CI->returnmodel->get_return_item($return_code);
		$data_origin_order	= $this->CI->ordermodel->get_order($order_seq);

		// 임시로 부담 선택 안할 시 튕기게 처리 (오픈마켓 이슈) 추후 수정 필요 :: 2018-07-19 pjw


		// 반품완료 시 구매자부담에 실결제가격이 반품배송비보다 적은경우 처리안되게함 :: 2018-07-16 pjw
		// 실 결제금액 + 이머니 - 배송비 - 반품배송비
		$total_payment_amount = $data_origin_order['settleprice'] + $data_origin_order['cash'] - $data_origin_order['shipping_cost'] - $post_params['return_shipping_price'];	
		if($post_params['status'] == 'complete' && $post_params['refund_ship_type'] == 'M' && $post_params['refund_ship_duty'] == 'buyer' && $total_payment_amount < 0){
			$callback = "parent.document.location.reload();";
			openDialogAlert(getAlert('mo154'),400,140,'parent',$callback);
			$this->call_exit();
		}

		//반품배송비 입점사가 받았을 경우. 입력한 금액 초기화(정산반영) @2015-06-23 pjm
		// 판매자 부담 시 반품 배송비는 무조건 0 원 처리 추가 :: 2018-05-24 lwh
		if(($post_params['return_shipping_gubun'] == "provider" && $post_params['refund_ship_type'] != 'M') || $post_params['refund_ship_duty'] == 'seller')
			$post_params['return_shipping_price'] = 0; 

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
			$this->CI->db->where('return_code',$post_params['return_code']);
			$update_param = array('admin_memo'=>$post_params['admin_memo']);
			if(isset($post_params['return_shipping_price'])){
				$update_param['return_shipping_gubun'] = $post_params['return_shipping_gubun'];
				$update_param['return_shipping_price'] = $post_params['return_shipping_price'];
			}
			$this->CI->db->update('fm_order_return',$update_param);
			$callback = "parent.document.location.reload();";
			openDialogAlert("반품 관리 메모가 수정 되었습니다.",400,140,'parent',$callback);
			$this->call_exit();
		}

		if(!$npay_order){
			
			if($post_params['status'] == 'complete' && $post_params['return_type'] == 'return'){

				$this->CI->load->library('Connector');
				$claimService		= $this->CI->connector::getInstance('claim');
				$checkMarketClaim	= $claimService->marketClaimConfirm($post_params['return_code'], 'RTN');

				if ($checkMarketClaim['success'] != 'Y') {
					if (isset($checkMarketClaim['message']))
						openDialogAlert("[반품실패] {$checkMarketClaim['message']}",400,140,'parent',$callback);
					else
						openDialogAlert("[반품실패] 마켓 반품 상태를 확인해 주세요",400,140,'parent',$callback);
					$this->call_exit();
				}
			}

			// 물류관리 사용 시 불량재고 입력값 체크
			if	(!$this->CI->scm_cfg)	$this->CI->scm_cfg	= config_load('scm');
			if	($this->CI->scm_cfg['use'] == 'Y'){
				if	($post_params['scm_wh'] > 0){
					// 물류관리 사용 시 창고번호 저장
					$update_param['wh_seq']		= $post_params['scm_wh'];
					if	($post_params['stock_return_ea']) foreach($post_params['stock_return_ea'] as $idx => $ea){
						if	($post_params['optioninfo'][$idx]){
							if	($post_params['return_badea'][$idx] > $ea){
								$callback	= 'if(parent.document.getElementsByName(\'return_badea[' . $idx . ']\')[0]) parent.document.getElementsByName(\'return_badea[' . $idx . ']\')[0].focus();';
								openDialogAlert('현재 입력하신 불량수량은 반품수량 보다 많습니다.',400,140,'parent',$callback);
								$this->call_exit();
							}
							if	(!$post_params['location_position'][$idx]){
								openDialogAlert('로케이션을 선택해 주세요.', 400, 140, 'parent', $callback);
								$this->call_exit();
							}
						}
					}
				}else if(array_key_exists('scm_wh', $post_params)){
					$callback	= 'if(parent.document.getElementsByName(\'scm_wh\')[0]) parent.document.getElementsByName(\'scm_wh\')[0].focus();';
					openDialogAlert('반품창고를 선택해 주세요.',400,140,'parent',$callback);
					$this->call_exit();
				}
			}
	
			$zipcode = "";
			if($post_params['phone'][1] && $post_params['phone'][2]) $phone = implode('-',$post_params['phone']);
			if($post_params['cellphone'][1] && $post_params['cellphone'][2]) $cellphone = implode('-',$post_params['cellphone']);
			if($post_params['senderZipcode']) $zipcode = implode('-',$post_params['senderZipcode']);
	
			$update_param['cellphone'] 				= $cellphone;
			$update_param['phone'] 					= $phone;
			$update_param['sender_zipcode']			= $zipcode;
			// $update_param['sender_post_number']		= $post_params['senderPost_number'];
			$update_param['sender_address_type']	= ($post_params['senderAddress_type'])?$post_params['senderAddress_type']:"zibun";
			$update_param['sender_address']			= $post_params['senderAddress'];
			$update_param['sender_address_street']	= $post_params['senderAddress_street'];
			$update_param['sender_address_detail']	= $post_params['senderAddressDetail'];
			$update_param['return_reason'] 			= $post_params['return_reason'];
			$update_param['admin_memo'] 			= $post_params['admin_memo'];
			$update_param['return_method']			= $post_params['return_method'];
			$update_param['manager_seq']			= $this->CI->managerInfo['manager_seq'];
			$update_param['return_shipping_price']	= $post_params['return_shipping_price'];
			$update_param['return_shipping_gubun']	= $post_params['return_shipping_gubun'];

			// 반품 관련 수정처리 추가 :: 2018-05-24 lwh
			$update_param['refund_ship_duty']		= $post_params['refund_ship_duty']; // 반품 배송비 책임
			$update_param['refund_ship_type']		= $post_params['refund_ship_type']; // 반품 배송비 지불 타입
		}

		$update_param['return_type']	= $data_return['return_type'];
		if($post_params['admin_memo']) $update_param['admin_memo']		= $post_params['admin_memo'];

		if($data_return['status'] != "complete"){
			$update_param['status'] 		= $post_params['status'];
		}

		##--------------------------------------------------------------------------------------------------
		# npay 반품요청 승인 처리 > 처리가능작업 : 
		#	- 반품신청 -> 반품완료(O)
		#	- 반품신청 -> 반품처리중(X)
		#	- 반품처리중 -> 반품완료(X) 
		#	- 반품처리중 -> 반품신청(X) 
		if($npay_order){
			$this->CI->load->model("naverpaymodel");
			$this->CI->load->library('naverpaylib');

			if($post_params['status'] == "request"){
				openDialogAlert("이 반품건은 네이버페이 반품건으로 반품신청으로 되돌리기 불가합니다.",500,160,'parent','');
				$this->call_exit();
			} 
			if($post_params['status'] == "ing"){
				openDialogAlert("이 반품건은 네이버페이 반품건으로 반품처리중 처리가 불가합니다.",500,160,'parent','');
				$this->call_exit();
			}
			if($data_return['status'] == "ing" && $post_params['status'] == "complete"){
				openDialogAlert("이 반품건은 네이버페이 반품건으로 반품완료 처리가 불가합니다.",500,160,'parent','');
				$this->call_exit();
			}
		}
		##--------------------------------------------------------------------------------------------------
		if($post_params['status'] == 'complete'){
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
						$npay_res = $this->CI->npay_approve_return($update_param['return_type'],$itemdata,$data_return,$post_params['npay_return_released']);
						if($npay_res) $exchange_reorder = true; else $exchange_reorder = false;
					}else{
						$exchange_reorder = true;
					}
					##--------------------------------------------------------------------------------------------------
					//선택한 재고증가 수량만큼 증감 2015-03-31 pjm
					if(!$npay_order){

						$stock_return_ea	= $post_params['stock_return_ea'][$return_item_seq];
						
						// 올인원 사용상태고 o2o미연결창고가 아닐때만 재고를 변동함
						if(!($this->CI->scm_cfg['use'] == 'Y' && $not_connect_scm == 'Y')){
							// 반품으로 인한 재고증가
							$goodsData = $this->CI->returnmodel->return_stock_ea($stock_return_ea,$return_item_seq,$item,$goodsData);
						}
					
					}

				}
			}

			// 재주문 넣기(맞교환),( npay주문 교환수거완료일때 )
			if($update_param['return_type'] == 'exchange' && $exchange_reorder){
				$this->CI->ordermodel->reorder($data_return['order_seq'],$return_code);
			}
		}

		# 반품정보 업데이트
		if(!$npay_order){
			$this->CI->db->where('return_code',$return_code);
			$this->CI->db->update('fm_order_return',$update_param);
		}

		# 재고차감할 반품수량
		$return_ea_arr = $post_params['stock_return_ea'];
		foreach($post_params['reason'] as $return_item_seq=>$reason_code)
		{
			unset($update_param);
			if(!$npay_order){
					$update_param['reason_code'] = $reason_code;
					if (!empty($post_params['reason_desc'][$return_item_seq])) {
						$update_param['reason_desc']	= $post_params['reason_desc'][$return_item_seq];
					}
			}
			$stock_return_ea = $post_params['stock_return_ea'][$return_item_seq];
			$return_badea = $post_params['return_badea'][$return_item_seq];
			
			if( !is_array($stock_return_ea) ){
				$update_param['stock_return_ea']	= $stock_return_ea;
				$update_param['return_badea']		= $return_badea;
			}else{
				$update_param['package_stock_return_ea']= serialize($stock_return_ea);
				$update_param['package_return_badea']	= serialize($return_badea);
			}
			if(is_array($post_params['location_position'][$return_item_seq])){
				$location_position	= serialize($post_params['location_position'][$return_item_seq]);
				$location_code		= serialize($post_params['location_code'][$return_item_seq]);
			}else{
				$location_position	= $post_params['location_position'][$return_item_seq];
				$location_code		= $post_params['location_code'][$return_item_seq];
			}

			$update_param['location_position']	= $location_position;
			$update_param['location_code']		= $location_code;

			$this->CI->db->where('return_item_seq',$return_item_seq);
			$this->CI->db->update('fm_order_return_item',$update_param);
		}
		
		// 품절체크를 위한 변수선언
		$r_runout_goods_seq = array();

		/* 재고조정 히스토리 저장 */
		if(!$npay_order && $post_params['status'] == 'complete'){
			if($data_return['status']!="complete"){
				
				$this->CI->returnmodel->return_stock_history($return_code,$retuns_goods_coupon_ea,$data_return_item,$return_ea_arr,$update_param['return_date']);

				/* 로그저장 */
				$logTitle	= "반품완료(".$return_code.")";
				$logDetail = "관리자가 반품완료처리를 하였습니다.";
				$logParams	= array('return_code' => $return_code);
				$this->CI->load->model('ordermodel');
				$this->CI->ordermodel->set_log($data_return['order_seq'],'process',$this->CI->managerInfo['mname'],$logTitle,$logDetail,$logParams);

				// 물류관리 재고 적용 및 매장 재고 전송
				if	($this->CI->scm_cfg['use'] == 'Y'){
					$this->CI->load->model('scmmodel');
					$this->CI->scmmodel->apply_return_wh($post_params['scm_wh'], $return_code, $goodsData);
					if	($this->CI->scmmodel->tmp_scm['wh_seq'] > 0){
						$sendResult		= $this->CI->scmmodel->change_store_stock($this->CI->scmmodel->tmp_scm['goods'], array($this->CI->scmmodel->tmp_scm['wh_seq']), '', '반품처리가 완료 되었습니다.', 'reload');
					}
				}

				/**
				* 2-2 반품배송비 관련 통합정산테이블 생성 시작
				* @
				**/
				if($post_params['return_shipping_gubun'] == 'company' && $post_params['return_shipping_price']) {
					$this->CI->load->helper('accountall');
					if(!$this->CI->accountallmodel)	$this->CI->load->model('accountallmodel');
					if(!$this->CI->providermodel)	$this->CI->load->model('providermodel');
					if(!$this->CI->refundmodel)		$this->CI->load->model('refundmodel');
					if(!$this->CI->returnmodel)		$this->CI->load->model('returnmodel');

					//step2 통합정산 생성(미정산매출 환불건수 업데이트)
					$this->CI->accountallmodel->insert_calculate_sales_order_returnshipping($data_return['order_seq'],$return_code);
					//debug_var($this->CI->db->queries);
					//debug_var($this->CI->db->query_times);
				}
				/**
				* 2-2 반품배송비 관련 통합정산테이블 생성 끝
				* @
				**/

				if	(!$sendResult['status']){
					$callback = "parent.document.location.reload();";
					openDialogAlert("반품처리가 완료 되었습니다.",400,140,'parent',$callback);
					$this->call_exit();
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
	public function call_exit(){
		if($this->allow_exit){
			exit;
		}
	}

	public function check_return_shipping_price($return, $data_return_item) {
		// 반품완료 시 구매자부담에 실결제가격이 반품배송비보다 적은경우 처리안되게함 :: 2018-07-16 pjw		
		if(!($return['status'] == 'complete' && $return['refund_ship_type'] == 'M' && $return['refund_ship_duty'] == 'buyer')) {
			return true;
		}
		// 2018-10-15 pjm 반품하는 상품 전체의 결제금액으로 비교.
		$total_payment_amount	= 0;
		$total_pay_shipping		= $return['return_shipping_price'];
		foreach($data_return_item as $k => $item){
			$option_seq				= $item['option_seq'];
			$suboption_seq			= $item['suboption_seq'];

			$option_data			= $this->CI->ordermodel->get_order_item_option($option_seq);
			$suboption_data			= $this->CI->ordermodel->get_order_item_suboption($suboption_seq);
			
			$total_payment_amount	+= $option_data['sale_price'] * $item['ea'];
			$total_payment_amount	+= $suboption_data['sale_price'] * $item['ea'];
		}
		if($total_payment_amount < $total_pay_shipping){
			return false;
		}
		return true;
	}
}
?>