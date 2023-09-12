<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

class shipping
{
	var $ci						= '';
	var $shipping_type_arr		= array();
	var $shipping_method_list	= array();
	var $receipt_address_type	= '';
	var $receipt_address		= '';
	var $nation					= 'KOREA';
	var $g_type					= 'korea';
	var $_call_mode				= '';

	public function __construct(){
		$CI		=& get_instance();
		$this->ci	= $CI;

		$this->set_init();
	}

	// 기본 값 설정
	public function set_init(){
		$this->ci->load->model('shippingmodel');
		$this->shipping_type_arr		= array('std', 'add', 'hop');
		$this->shipping_method_list		= $this->ci->shippingmodel->ship_set_code;
	}

	// 배송지 주소 설정
	public function set_ini($params=''){
		if	($params['street_address']){
			$this->receipt_address_type	= 'street';
			$this->receipt_address		= $params['street_address'];
			$this->receipt_address_zibun= $params['zibun_address'];
		}else{
			$this->receipt_address_type	= 'zibun';
			$this->receipt_address		= $params['zibun_address'];
			$this->receipt_address_zibun= $params['zibun_address'];
		}
		if	($params['nation']){
			if	($params['nation'] == 'KOREA'){
				$this->nation			= $params['nation'];
				$kr_nation				= '대한민국';
			}else{
				preg_match("/\([^가-힣]*\)/",$params['nation'],$matches);
				$kr_nation				= $params['nation'];
				$this->nation			= trim(preg_replace("/[\(,\)]/","",$matches[0]));
			}
			$this->nation_key	= $params['nation_key'];
		}
		if	($this->nation != 'KOREA')	$this->g_type	= 'global';

		$ini_info['g_type']					= $this->g_type;
		$ini_info['nation']					= $this->nation;
		$ini_info['kr_nation']				= ($this->nation == 'KOREA') ? '대한민국' : $kr_nation;
		$ini_info['nation_key']				= ($this->nation == 'KOREA') ? 'KOR' : $this->nation_key;
		$ini_info['receipt_address_type']	= $this->receipt_address_type;
		$ini_info['receipt_address']		= $this->receipt_address;

		return $ini_info;
	}

	// 전체 배송 옵션 정보 추출
	public function get_shipping_options(&$goods, &$free_shipping_exists, &$free_shipping_provider,$mode=''){
		// 배송 그룹별 정보 추출
		if	(is_array($goods) && count($goods) > 0){
			foreach ( $goods as $k => $data ){
				$cart_flag = false; // 카트정보 변경값지정
				unset($shipping);
				$shipping['group_seq']	= $data['shipping_group_seq'];
				$shipping['set_seq']	= $data['shipping_set_seq'];
				$shipping['set_code']	= $data['shipping_set_code'];

				// 해당 상품의 배송그룹정보가 변경 될 경우 예외처리.
				// O2O 의 경우 배송 정보가 강제로 할당되어 있으므로 갱신 X
				if	($data['shipping_group_seq'] != $data['goods_shipping_group_seq'] && $mode != "o2o"){
					$data['shipping_group_seq']		= $data['goods_shipping_group_seq'];
					$shipping['group_seq']			= $data['goods_shipping_group_seq'];
					$shipping['set_seq']			= 'default';
					$shipping['set_code']			= '';

					// 카트정보 변경 상태값 기억.
					if	($data['cart_seq'])	$cart_chg_flag = true;
				}

				// 예약상품 배송일 지정
				unset($reserve_date);
				if($data['display_terms'] == 'AUTO' && $data['display_terms_begin'] <= date('Y-m-d') && $data['display_terms_end'] >= date('Y-m-d') && $data['display_terms_type'] == 'LAYAWAY' && $data['possible_shipping_date']){
					$reserve_date	= $data['possible_shipping_date'];
					$reserve_txt	= $data['possible_shipping_text'];
				}

				unset($cfg);
				if	(!$shipping_group[implode('_', $shipping)]){
					unset($addBind);
					unset($store_info);

					// 옵션정보가 없는경우 OPT 정보를 추출하지 않음. 예) 매장수령
					if($shipping['set_code'] == 'direct_store'){
						$addBind[]	= $shipping['set_seq'];
						$sql		= "
							SELECT	ship_set.*,
									ship_grp.shipping_provider_seq AS ship_provider_seq,
									ship_grp.shipping_calcul_type,
									ship_grp.shipping_calcul_free_yn,
									ship_grp.shipping_std_free_yn,
									ship_grp.shipping_add_free_yn,
									ship_grp.shipping_hop_free_yn,
									ship_grp.refund_address_seq
							FROM	fm_shipping_set AS ship_set,
									fm_shipping_grouping AS ship_grp
							WHERE	ship_set.shipping_group_seq = ship_grp.shipping_group_seq
									AND ship_set.shipping_set_seq = ?
						";

						$query		= $this->ci->db->query($sql, $addBind);
						$result		= $query->row_array();

						$tmp_r['shipping_group_seq']	= $result['shipping_group_seq'];
						$tmp_r['shipping_set_seq']		= $result['shipping_set_seq'];
						$tmp_r['shipping_set_code']		= $result['shipping_set_code'];

						$tmp_r['shipping_set_name']		= $result['shipping_set_name'];
						$tmp_r['shipping_provider_seq']	= $result['ship_provider_seq'];
						$tmp_r['delivery_limit']		= $result['delivery_limit'];
						$tmp_r['default_yn']			= $result['default_yn'];
						$tmp_r['shipping_calcul_type']	= $result['shipping_calcul_type'];
						$tmp_r['shipping_calcul_free_yn']= $result['shipping_calcul_free_yn'];
						$tmp_r['shipping_std_free_yn']	= $result['shipping_std_free_yn'];
						$tmp_r['shipping_add_free_yn']	= $result['shipping_add_free_yn'];
						$tmp_r['shipping_hop_free_yn']	= $result['shipping_hop_free_yn'];
						$tmp_r['delivery_nation']		= $result['delivery_nation'];
						$tmp_r['shipping_prepay_info']	= $result['prepay_info'];

						// 반품/교환 배송비 추가 :: 2018-05-15 lwh
						$ship_refund['refund_shiping_cost']	= $result['refund_shiping_cost'];
						$ship_refund['swap_shiping_cost']	= $result['swap_shiping_cost'];
						$ship_refund['shiping_free_yn']		= $result['shiping_free_yn'];

						$cfg['baserule']				= $tmp_r;

						// 매장정보 추출
						$store_info	= $this->get_shipping_store($result['shipping_group_seq'], $data['shipping_store_seq']);

						// 매장정보가 사용가능한 상태인지 재 확인----------------------WONY
						// #####

					}else{ // 매장수령 외

						$sql		= "
							SELECT 
								ship_opt.*, 
								ship_set.delivery_nation, 
								ship_set.hopeday_required, 
								ship_set.refund_shiping_cost, 
								ship_set.swap_shiping_cost, 
								ship_set.shiping_free_yn, 
								ship_set.prepay_info as shipping_prepay_info, 
								ship_cost.shipping_cost, 
								ship_cost.shipping_cost_seq 
							FROM
								fm_shipping_option AS ship_opt
								left join fm_shipping_cost AS ship_cost on ship_opt.shipping_opt_seq=ship_cost.shipping_opt_seq ,
								fm_shipping_set AS ship_set
							WHERE
								ship_opt.shipping_set_seq = ship_set.shipping_set_seq AND
								ship_opt.shipping_group_seq = ? AND
								ship_cost.shipping_group_seq_tmp  = ?
						";
						$addBind[]	= $shipping['group_seq'];
						$addBind[]	= $shipping['group_seq'];
						if	($shipping['set_seq'] == 'default'){
							$sql		.= "AND ship_opt.default_yn = ? ";
							$addBind[]	= 'y';
						}else{
							$sql		.= "AND ship_opt.shipping_set_seq = ? ";
							$addBind[]	= $shipping['set_seq'];
							$sql		.= "AND ship_opt.shipping_set_code = ? ";
							$addBind[]	= $shipping['set_code'];
						}
						$query		= $this->ci->db->query($sql, $addBind);
						$result		= $query->result_array();

						// 장바구니의 저장된 정책과 현재 정책이 불일치할 시 기본 정책 추출
						if	(!$result){
							unset($addBind);
							$sql		= "
								SELECT
									ship_opt.*, 
									ship_set.delivery_nation, 
									ship_set.refund_shiping_cost, 
									ship_set.swap_shiping_cost, 
									ship_set.shiping_free_yn, 
									ship_cost.shipping_cost,
									ship_cost.shipping_cost_seq
								FROM
									fm_shipping_option AS ship_opt
									left join fm_shipping_cost AS ship_cost on ship_opt.shipping_opt_seq=ship_cost.shipping_opt_seq ,
									fm_shipping_set AS ship_set
								WHERE
									ship_opt.shipping_set_seq = ship_set.shipping_set_seq AND
									ship_opt.shipping_group_seq = ? AND
									ship_opt.default_yn = ?
							";
							$addBind[]	= $shipping['group_seq'];
							$addBind[]	= 'y';
							$query		= $this->ci->db->query($sql, $addBind);
							$result		= $query->result_array();
						}

						// 최종 결정된 배송그룹, 배송방법, 코드 재정의
						$shipping['group_seq']	= $result[0]['shipping_group_seq'];
						$shipping['set_seq']	= $result[0]['shipping_set_seq'];
						$shipping['set_code']	= $result[0]['shipping_set_code'];

						// 반품/교환 배송비 추가 :: 2018-05-15 lwh
						$ship_refund['refund_shiping_cost']	= $result[0]['refund_shiping_cost'];
						$ship_refund['swap_shiping_cost']	= $result[0]['swap_shiping_cost'];
						$ship_refund['shiping_free_yn']		= $result[0]['shiping_free_yn'];

						// 카트정보 변경하여 계산시 해당정보로 계산 :: 2017-06-22 lwh
						if($cart_chg_flag){
							$cart_sql = "
								UPDATE fm_cart
								SET
									shipping_group_seq = '".$shipping['group_seq']."',
									shipping_set_seq = '".$shipping['set_seq']."',
									shipping_set_code = '".$shipping['set_code']."'
								WHERE cart_seq = '".$data['cart_seq']."'";
							$this->ci->db->query($cart_sql);
						}

						// 배송 종류별 묶음 처리
						if	($result) foreach($result as $j => $opt){
							// 반품/교환 배송비 각 배송종류별 필요X :: 2018-05-15 lwh
							unset($opt['refund_shiping_cost'], $opt['swap_shiping_cost'], $opt['shiping_free_yn']);

							// 지정 배송방법명이 아닌 경우 기본 배송방법명으로 대체
							if	(!$opt['shipping_set_name']){
								$opt['shipping_set_name']	= $this->shipping_method_list[$opt['shipping_set_code']];
							}
							$cfg[$opt['shipping_set_type']][]		= $opt;
						}
						$cfg['baserule']							= $cfg['std'][0];
					} // end if
					$shipping_group[implode('_', $shipping)]	= $cfg;
				}else{
					$cfg										= $shipping_group[implode('_', $shipping)];
				}
				
				$data['shipping_set_seq']	= $cfg['baserule']['shipping_set_seq'];
				$data['shipping_set_code']	= $cfg['baserule']['shipping_set_code'];
				if($cfg['baserule']['shipping_prepay_info'] == 'postpaid'){ //착불 배송비
					$data['shipping_prepay_info'] = 'postpaid';
				}
				
				// 배송그룹 키
				$shipping_key	= $cfg['baserule']['shipping_group_seq'] . '_' . $cfg['baserule']['shipping_set_seq'] . '_' . $cfg['baserule']['shipping_set_code'];
				if	($cfg['baserule']['shipping_calcul_type'] == 'each')
					$shipping_key	.= '_' . $data['cart_option_seq'];

				// 희망배송일 필수 여부 검증
				if	($data['shipping_hop_date'] == '0000-00-00')
					unset($data['shipping_hop_date']);
				if	($result[0]['hopeday_required'] == 'Y' && !$data['shipping_hop_date']){
					$this->ci->load->model('shippingmodel');
					$sql		= "SELECT * FROM fm_shipping_set WHERE shipping_set_seq = '" . $result[0]['shipping_set_seq'] . "' ";
					$query		= $this->ci->db->query($sql);
					$hop_res	= $query->result_array();
					$hop_date	= $this->ci->shippingmodel->get_hop_date($hop_res[0]);
					$data['shipping_hop_date'] = $hop_date;
				}

				// 희망배송일 검증
				if	($data['shipping_hop_date'] && $data['cart_seq']){
					$chkdate		= true;
					$set_seq		= $data['shipping_set_seq'];
					$now_date		= date('Ymd');
					$chk_hop_date	= date('Ymd',strtotime($data['shipping_hop_date']));
					if	($now_date <= $chk_hop_date){
						// 가능하면 설정이 바뀌었나 재 체크
						$this->ci->load->model('shippingmodel');
						$chkdate = $this->ci->shippingmodel->chk_hop_date($set_seq,$chk_hop_date);
					}else{ // 지난 날짜이면 패스
						$chkdate = false;
					}

					// 불가능 날짜이면 cart에서 지움
					if(!$chkdate){
						$cart_sql = "UPDATE fm_cart SET shipping_hop_date = '' WHERE cart_seq = '".$data['cart_seq']."'";
						$this->ci->db->query($cart_sql);
						$data['shipping_hop_date'] = '';
					}
				}

				// 배송그룹별 합계값
				if	($cfg['baserule']['shipping_calcul_type'] == 'free'){
					$free_shipping_exists	= true;
					$free_shipping_provider	= $data['shipping']['provider_seq'];	//배송책임 판매자 번호
				}

				// 화면에 보여질 row 계산
				$return[$shipping_key]['row_cnt']++;
				if	($data['cart_suboptions']){
					$return[$shipping_key]['row_cnt'] += count($data['cart_suboptions']);
				}

				// 추가옵션 무게 및 금액 산정
				$sub_sale_price = 0;
				$sub_weight		= 0;
				$sub_ea			= 0;
				foreach($data['cart_suboptions'] as $subkey => $subval){
					$sub_ea			+= $subval['ea'];
					$sub_sale_price += $subval['sale_price'] * $subval['ea'];
					$sub_weight		+= $subval['weight'] * $subval['ea'];
				}


				$sale_price = ($mode == "adminCart")?$data['sales']['sale_price']:$data['sale_price'];
				$sale_ea = ($mode == "adminCart")?$data['sales']['ea']:$data['ea'];

				$return[$shipping_key]['shipping_ea']		+= $data['ea'] + $sub_ea;
				$return[$shipping_key]['total_ea']			+= $data['ea'];
				$return[$shipping_key]['total_cnt']			= $return[$shipping_key]['total_ea'];
				$return[$shipping_key]['total_amount']		+= $sale_price * $sale_ea + $sub_sale_price;
				$return[$shipping_key]['total_weight']		+= $data['goods_weight'] * $data['ea'] + $sub_weight;
				$return[$shipping_key]['shipping_hop_date']	= (!$data['shipping_hop_date'] || $data['shipping_hop_date'] == '0000-00-00') ? '' : $data['shipping_hop_date'];
				$return[$shipping_key]['shipping_prepay_info']	= ($data['shipping_prepay_info']) ? $data['shipping_prepay_info'] : 'delivery';
				$return[$shipping_key]['shipping_store_seq']	= ($data['shipping_store_seq']) ? $data['shipping_store_seq'] : '';

				// 반품/교환 배송비 추가 :: 2018-05-15 lwh
				if (!$return[$shipping_key]['refund_shiping_cost']){
					$return[$shipping_key]['refund_shiping_cost']	= ($ship_refund['refund_shiping_cost']) ? $ship_refund['refund_shiping_cost'] : 0;
					$return[$shipping_key]['swap_shiping_cost']		= ($ship_refund['swap_shiping_cost'])	? $ship_refund['swap_shiping_cost'] : 0;
					$return[$shipping_key]['shiping_free_yn']		= ($ship_refund['shiping_free_yn'])		? $ship_refund['shiping_free_yn'] : 0;
				}

				// 예약 배송일이 있으면 예약배송일 정보 매칭 :: 2016-11-14 lwh 변경
				if($reserve_date){
					$reserve_arr[$shipping_key][]		= $reserve_date;
					$reserve_txt_arr[$shipping_key][]	= $reserve_txt;
				}

				// 매장정보가 있으면 매장정보를 매칭
				if($store_info)		$return[$shipping_key]['store_info'] = $store_info;

				// 희망배송일 및 선착불 정보
				$shipping['shipping_hop_date']		= $data['shipping_hop_date'];
				$shipping['shipping_prepay_info']	= $data['shipping_prepay_info'];

				// 배송여부 판별
				if($cfg['baserule']['delivery_nation'] == $this->g_type){
					$return[$shipping_key]['ship_possible']	= 'Y'; // cost에서 체크
				}else{ // 배송불가
					$return[$shipping_key]['ship_possible']	= 'N'; // 배송불가
				}

				// 배송그룹 체크
				if(!$shipping['group_seq']){
					$return[$shipping_key]['ship_possible'] = 'E'; // 배송그룹 없음
				}

				$return[$shipping_key]['goods'][]		= $data;
				$return[$shipping_key]['cfg']			= $cfg;

				$goods[$k]		= $data;
			}

			// 예약배송일 확정 :: 2017-01-03 lwh
			foreach($reserve_arr as $shipping_key => $sdata){
				foreach($sdata as $k => $reserve_sdate){
					if(strtotime($return[$shipping_key]['reserve_sdate']) < strtotime($reserve_sdate)){
						$return[$shipping_key]['reserve_sdate']	=  $reserve_sdate;
						$return[$shipping_key]['reserve_txt']	=  $reserve_txt_arr[$shipping_key][$k];
						unset($return[$shipping_key]['shipping_hop_date']);
					}
				}
			}
		}
		return $return;
	}

	// 배송 그룹별 묶음 및 배송비 계산. ( goods = row type array )
	public function get_shipping_groupping($goods,$mode=''){

		$this->ci->load->model('providermodel');
		// 상품에 배송 정책 설정값 추가 및 그룹별 총합 계산
		$free_shipping_exists	= false;
		$shipping				= $this->get_shipping_options($goods, $free_shipping_exists,$free_shipping_provider,$mode);

		// 배송그룹별 배송비 계산
		if	($shipping){
			$total_shipping_price = 0;
			foreach ( $shipping as $shipping_group_code => $data){
				$grp_shipping_price	= 0;
				if	($this->shipping_type_arr) foreach ($this->shipping_type_arr as $k => $shipping_type){

					// 계산 불필요 항목 Pass
					if(($shipping_type == 'hop' && !$data['shipping_hop_date']) || $data['ship_possible'] == 'N' || $data['cfg']['baserule']['shipping_set_code'] == 'direct_store'){
						continue;
					}

					$cfg	= $data['cfg'][$shipping_type];
					if	($cfg){

						# 무료계산-묶음 배송 그룹과 함께 주문되는 배송그룹 체크(단, 무료계산-묶음 배송의 배송책임판매자와 동일해야함)
						if( $free_shipping_exists && ($free_shipping_provider == $cfg[0]['shipping_provider_seq']) && $cfg[0]['shipping_calcul_free_yn'] == 'Y' && $cfg[0]['shipping_' . $shipping_type . '_free_yn'] == 'Y'){
							$bundle_free = true;
						}else{
							$bundle_free = false;
						}

						# 무료계산-묶음 배송 >> 표기만 하고 금액은 하단에서 무료처리
						# or 무료계산-묶음 배송 그룹과 함께 주문되는 배송그룹 상품(배송책임 판매자가 동일)
						# or 배송방법이 무료인 배송정책은 기본배송비가 무료
						if			($bundle_free || ($shipping_type == "std" && $cfg[0]['shipping_opt_type'] == 'free')){
							$data['shipping_' . $shipping_type . '_group_free']	= 'Y';
						}else if	($shipping_type == 'std' && $cfg[0]['shipping_calcul_type'] == 'free'){
							$data['shipping_' . $shipping_type . '_group_free']	= 'Y';
						}

						// 배송비 구간정보 추출
						$data['shipping_' . $shipping_type . '_cfg'] = $this->get_shipping_option($data, $cfg);
					}

					if	($data['shipping_' . $shipping_type . '_cfg']){
						$resCost = $this->get_shipping_cost($data['shipping_' . $shipping_type . '_cfg']);

						// 배송 불가 판별 -- N : 기본배송불가 / H : 희망배송불가
						if	($resCost['possible_yn'] == 'N'){
							if	($shipping_type == 'std')		$data['ship_possible']	= 'N';
							else if ($shipping_type == 'hop')	$data['ship_possible']	= 'H';
						}

						// 무료화 가능건 배송비 무료처리 / 기본 배송비만 무료처리함 / 무료처리하도록 수정 :: 2017-04-28 lwh :: 2017-10-18 lkh
						if($data['shipping_' . $shipping_type . '_group_free'] == 'Y'){
							$data['shipping_' . $shipping_type . '_cost']	= 0;
						}else{
							$data['shipping_' . $shipping_type . '_cost']	= $resCost['shipping_cost'];
						}

						if	(!($data['shipping_' . $shipping_type . '_cost'] > 0))
							$data['shipping_' . $shipping_type . '_cost']	= 0;
						$grp_shipping_price	+= $data['shipping_' . $shipping_type . '_cost'];

					}else{
						$data['shipping_' . $shipping_type . '_cost']	= 0;
					}
					// 타입별 배송 총합 금액 추출
					$shipping_price[$data['shipping_prepay_info']][$shipping_type] += $data['shipping_' . $shipping_type . '_cost'];
				}

				// 입점사 배송그룹일 경우 입점사명,배송비 수수료, 반품배송비 수수료 추출
				$shipper_name	= getAlert("sy009"); // '본사';
				if	($data['cfg']['baserule']['shipping_provider_seq'] > 1){
					$provider	= $this->ci->providermodel->get_provider_one($data['cfg']['baserule']['shipping_provider_seq']);
					$shipper_name			= $provider['provider_name'];
					$shipping_charge		= $provider['shipping_charge'];
					$return_shipping_charge	= $provider['return_shipping_charge'];
				}

				$shipping[$shipping_group_code]							= $data;
				$shipping[$shipping_group_code]['grp_shipping_price']	= $grp_shipping_price;
				if(serviceLimit('H_AD')){
					$shipping[$shipping_group_code]['shipper_name']				= $shipper_name;
					$shipping[$shipping_group_code]['shipping_charge']			= $shipping_charge;
					$shipping[$shipping_group_code]['return_shipping_charge']	= $return_shipping_charge;
				}
			}

			// 전체 배송금액 추출
			$shipping['total_shipping_price'] = array_sum($shipping_price['delivery']);

			$shipping['shipping_cost_detail'] = $shipping_price;
		}

		return $shipping;
	}

	// 어느 구간에 해당하는지 추출해 냄.
	public function get_shipping_option($data, $shipping){

		// 추가배송 : (국내)주소가 없는 경우 Pass
		// 희망배송 : 희망일이 없는 경우 Pass
		if	(($shipping[0]['shipping_set_type'] == 'add' && !$this->receipt_address && $shipping[0]['delivery_nation'] == 'korea') ||
			($shipping[0]['shipping_set_type'] == 'hop' && (!$data['shipping_hop_date'] || $data['shipping_hop_date'] == '0000-00-00'))){
			return null;
		}

		// 고정형 계산
		if(in_array($shipping[0]['shipping_opt_type'], array('fixed','free'))){
			$shipping_option			= $shipping[0];
		// 구간 반복형 계산
		}else if	(preg_match('/\_rep$/', $shipping[0]['shipping_opt_type'])){
			$opt_type		= preg_replace('/\_rep$/', '', $shipping[0]['shipping_opt_type']);
			$total			= $data['total_'.$opt_type];

			unset($real_shipping);
			foreach($shipping as $k => $data){
				if(!in_array($data['shipping_opt_seq'], $tmp_opt)){
					if($k > 0){
						$real_shipping[]	= array_merge($data,array("repeat"=>true,"total_".$opt_type=>$total));
					}else{
						$real_shipping[]	= array_merge($data,array("repeat"=>false,"total_".$opt_type=>$total));
					}
					$tmp_opt[] = $data['shipping_opt_seq'];
				}
			}
			$shipping = $real_shipping;

			if	($total < $shipping[0]['section_ed']){
				$shipping_option		= $shipping[0];
			}else{
				$shipping_option		= $shipping;
			}
		// 구간 입력형 계산
		}else{
			foreach($shipping as $k => $cfg){
				$total	= $data['total_'.$cfg['shipping_opt_type']];
				if	($cfg['section_st'] <= $total && !$cfg['section_ed']){
					$shipping_option		= $cfg;
				}elseif	($cfg['section_st'] <= $total && $total < $cfg['section_ed']){
					$shipping_option		= $cfg;
				}
			}
		}

		if	($shipping[0]['shipping_set_type'] == 'hop'){
			$nowTime = strtotime(date('Y-m-d'));
			$selTime = strtotime($data['shipping_hop_date']);
			// 당일배송 여부 확인
			if	($nowTime == $selTime)		$today_hop_yn = 'Y';
			else							$today_hop_yn = 'N';
			$shipping_option['today_hop_yn'] = $today_hop_yn;
		}

		return $shipping_option;
	}

	// 배송금액 추출
	public function get_shipping_cost($optCfg){
		// 네이버페이 수량(구간반복) 개선으로 첫번째 배송그룹은 계산시 제외 2018-05-23
		if($optCfg[0] && isset($optCfg[0]['shipping_cost_seq']) && $optCfg[0]['shipping_opt_type']=='cnt_rep' && $optCfg[0]['section_ed']==1){
			array_shift($optCfg);
		}
		if($optCfg[0] && isset($optCfg[0]['shipping_cost_seq'])){
			foreach($optCfg as $optCfgData){
				$result[] = $this->get_shsipping_cost_data($optCfgData);
			}
		}else{
			$result = $this->get_shsipping_cost_data($optCfg);
		}

		$return = array('possible_yn'=>'N','shipping_cost'=>0);
		if(is_array($result[0])){
			if($result[0]['possible_yn'] == "Y") $return['possible_yn'] = $result[0]['possible_yn'];
			foreach($result as $data){
				if($data['possible_yn'] == "Y") $return['shipping_cost'] += $data['shipping_cost'];
			}
		}else $return = $result;

		return $return;
	}

	/* 
	네이버페이용 추가배송비 검색 조건 
		예시) 인천광역시 중구 공항대로1번길 11(운서동, 공항터미널)
		$_searchStr_tmp 
			[0] 인천광역시 중구 공항대로1번길 11
			[1] 운서동, 공항터미널)
		$_searchStr1
			[0] 인천광역시
			[1] 중구
			[2] 공항대로1번길
			[3] 11
		$_searchStr2
			[0] 운서동
			[1] 공항터미널)
	*/
	public function get_add_shipping_search_wheres($searchStr,$gubun='street'){
		$_searchStr_tmp	= array();
		$_searchArr		= array();
		$_searchStr_tmp = explode("(",$searchStr);
		$_searchStr1	= explode(" ",$_searchStr_tmp[0]);
		$_searchStr2	= explode(",",$_searchStr_tmp[1]);
		foreach($_searchStr1 as $_txt){
			$_txt = trim($_txt);
			if($gubun == "street"){
				if(preg_match("/[\s0-9가-힣]+(번길|길|가)/",$_txt)
						|| preg_match("/[가-힣]+로/",$_txt)
						|| preg_match("/[가-힣]+구/",$_txt)
						|| preg_match("/[가-힣]+군/",$_txt)
						|| preg_match("/[가-힣]+읍/",$_txt)
						|| preg_match("/[가-힣]+면/",$_txt)
						|| preg_match("/[가-힣]+시/",$_txt)
						|| preg_match("/[가-힣]+도/",$_txt)
				){
					$_searchTxt[] = $_txt;
				}
			}elseif($gubun == "zibun"){
				if((preg_match("/[\s0-9가-힣]+(동|길)/",$_txt) && !preg_match("/[\s0-9가-힣]+동로/",$_txt))
						|| preg_match("/[가-힣]+구/",$_txt)
						|| preg_match("/[가-힣]+군/",$_txt)
						|| preg_match("/[가-힣]+읍/",$_txt)
						|| preg_match("/[가-힣]+면/",$_txt)
						|| preg_match("/[가-힣]+시/",$_txt)
						|| preg_match("/[가-힣]+도/",$_txt)
				){
					$_searchTxt[] = $_txt;
				}
			}
		}

		// ex) '인천광역시 중구 무의동' 도서산간비 적용
		// 인천광역시 중구 인중로 - zibun 변환 시 %인천광역시% and %중구% 로 검색되 도서산간비 부과됨
		// 인천광역시% and %중구 로 검색되도록 개선			2019-04-29 by hyem
		$cnt = count($_searchTxt);
		foreach($_searchTxt as $key => $_txt) {
			if($key == 0) $_txt .= '%';						// 시작은 뒤에만 %
			elseif( ($key+1) == $cnt ) $_txt = '%'.$_txt;	// 끝은 앞에만 %
			else $_txt = '%'.$_txt.'%';						// 중간은 앞뒤다 %

			if($gubun == "street")
				$_searchArr[] = "area.area_detail_address_street like '".$_txt."'";
			else
				$_searchArr[] = "area.area_detail_address_zibun like '".$_txt."'";
		}

		foreach($_searchStr2 as $_txt){
			$_txt = str_replace(")","",$_txt);
			$_txt = trim($_txt);
			if($gubun == "street"){
				if(preg_match("/[가-힣]+동/",$_txt)){
					$_searchArr[] = "area.area_detail_address_street like '%".$_txt."%'";
				}
			}elseif($gubun == "zibun"){
				if(preg_match("/[\s0-9가-힣]+동/",$_txt)){
					$_searchArr[] = "area.area_detail_address_zibun like '%".$_txt."%'";
				}
			}
		}

		$search_whreres = "(".implode(" AND ",$_searchArr).")";
		if($gubun == "street"){
			$search_whreres .= " OR INSTR('" . $searchStr . "', area.area_detail_address_street)";
		}else{
			$search_whreres .= " OR INSTR('" . $searchStr . "', area.area_detail_address_zibun)";
		}

		return $search_whreres;
	}

	public function get_shsipping_cost_data($optCfg){

		if	($optCfg['shipping_opt_seq'] > 0){

			$selectSql	= "SELECT * ";
			$fromSql	= "FROM  fm_shipping_cost AS cost ";
			$whereSql	= "WHERE cost.shipping_opt_seq = '" . $optCfg['shipping_opt_seq'] . "' ";
			if($optCfg['shipping_set_type'] == 'hop' && $optCfg['today_hop_yn']){
				if($optCfg['today_hop_yn']=='Y')
					$whereSql .= "AND shipping_today_yn = 'Y'";
			}
			$orderbySql	= "ORDER  BY cost.shipping_cost_seq ASC  ";
			$groupbySql	= "";
			$limitSql	= "LIMIT  1";

			/**
			 * 2021.04.29 : kjw
			 * receipt_address_zibun 값이 존재 하지 않을 경우가 생길 수 있어, else if 로 street 한 값 추가로 받을 수 있도록 함
			 */
			if	($optCfg['delivery_limit'] == 'limit'){
				if	($optCfg['delivery_nation'] == 'korea' && $this->receipt_address_zibun){
					$addSearch	= 'address';
					$searchStr	= $this->receipt_address_zibun;
				} else if ($optCfg['delivery_nation'] == 'korea' && $this->receipt_address) {
					$addSearch	= 'address';
					$searchStr	= $this->receipt_address;
				} else if	($optCfg['delivery_nation'] == 'global' && $this->nation){
					$addSearch	= 'nation';
					$searchStr	= '('.$this->nation.')';
				}

				// $this->receipt_address_type 도로명 주소 별 결정
				if			($addSearch == 'nation'){
					$fromSql	.= "INNER JOIN fm_shipping_area_detail AS area "
								. "	ON cost.shipping_cost_seq = area.shipping_cost_seq ";
					$whereSql	.= "AND ( area.area_detail_address_street LIKE '%" . $searchStr . "%' OR area.area_detail_address_zibun LIKE '%" . $searchStr . "%') ";
				}else if	($addSearch == 'address'){

					$fromSql	.= "INNER JOIN fm_shipping_area_detail AS area "
								. "	ON cost.shipping_cost_seq = area.shipping_cost_seq ";

					if($this->_call_mode == "npay"){
						$npay_add_shipping_search_street = $this->get_add_shipping_search_wheres($searchStr,'street');
						$npay_add_shipping_search_zibun = $this->get_add_shipping_search_wheres($searchStr,'zibun');

						$whereSql	.= "AND ( 
									  (".$npay_add_shipping_search_street.")
										OR 
									  (".$npay_add_shipping_search_zibun.")
									) ";

					}else{
						/**
						* 주소에 single quote가 포함되어있는 주소(제2부두로 17) 관련 오류로 인하여
						* 쿼리 인자에 escape 처리함.
						* 2019-06-25
						* @author Sunha Ryu
						*/
						$escapedValue = $this->ci->db->escape($searchStr);
						$whereSql	.= "AND ( 
											(INSTR({$escapedValue}, area.area_detail_address_street) > 0)
											OR 
											INSTR({$escapedValue}, area.area_detail_address_zibun) > 0
										) ";
					}

				}
			}
			
			$sql	= $selectSql . $fromSql . $whereSql . $orderbySql . $groupbySql . $limitSql;
			$query	= $this->ci->db->query($sql);
			$result	= $query->row_array();

			if($result){

				$return['possible_yn']			= 'Y';

				$shipping_cost	= 0;

				if($optCfg['shipping_set_type'] == 'hop' && $optCfg['today_hop_yn'] == 'Y'){
					$shipping_cost	= $result['shipping_cost_today'];
				}else{
					$shipping_cost	= $result['shipping_cost'];
				}

				// 반복구간일때 반복수량별 배송비 계산
				if($optCfg['repeat']){
					$full_type		= $optCfg['shipping_opt_type'];
					$opt_type		= preg_replace('/\_rep$/', '', $full_type);
					//$repeat_ea		= $optCfg['total_'.$opt_type] - ($optCfg['section_st']-1);
					//$shipping_cost	= $shipping_cost * ceil($repeat_ea / $optCfg['section_ed']);
					$repeat_ea	= ($optCfg['total_'.$opt_type] - $optCfg['section_st']);
					$shipping_cost= $shipping_cost * ceil(($repeat_ea+0.01) / $optCfg['section_ed'] );
				}
				$return['shipping_cost'] = $shipping_cost;

			}else{
				$return['possible_yn']		= 'N';
				$return['shipping_cost']	= '0';
			}
		}

		return $return;
	}
	
	public function get_addr_street_zibun($addrs)
	{
		$addrsNew		= array();
		
		if($addrs[0] == '세종특별자치시'){
			$addrsNew[0]	= $addrs[0];
			$addrsNew[1]	= $addrs[1];
		} else {
			$is_chk = iconv_substr($addrs[2], iconv_strlen($addrs[2], "utf-8")-1, 1, "utf-8");
			if($is_chk == '면' || $is_chk == '읍' || $is_chk == '리'){
				$addrsNew[0]	= $addrs[0].' '.$addrs[1].' '.$addrs[2];
				$addrsNew[1]	= $addrs[3];
			} else {
				$addrsNew[0]	= $addrs[0].' '.$addrs[1];
				$addrsNew[1]	= $addrs[2];
			}
		}
		
		return $addrsNew;
	}
	
	// 매장정보 추출 - 매장정보가 없을시 임시로 1번째를 가져옴
	public function get_shipping_store($grp_seq, $store_seq=''){
		$sql		= "SELECT * FROM fm_shipping_store "
					. "WHERE shipping_group_seq_tmp = ? ";
		$addBind[]	= $grp_seq;

		// 매장정보가 있으면 매장정보를 추출
		if($store_seq){
			$sql		.= "AND shipping_address_seq = ? ";
			$addBind[]	= $store_seq;
		}
		$sql		.= "ORDER BY shipping_store_seq ASC ";
		$sql		.= "LIMIT 1 ";

		$query		= $this->ci->db->query($sql, $addBind);
		$result		= $query->row_array();

		return $result;
	}

	// 구버전 용 cart list 변환
	public function get_old_shipping_groupping($goods, $shipping_group_list){
		unset($goods['shipping_price']);

		foreach($goods['list'] as $key => $cart){
			$ship_grp = $cart['shipping_group'];
			$baserule = $shipping_group_list[$ship_grp]['cfg']['baserule'];

			if($baserule['shipping_set_name'] && $baserule['shipping_set_name'] != $cart['shipping_method_name']){ // 배송 방법 명칭 변경
				$cart['shipping_method_name'] = $baserule['shipping_set_name'];
			}

			// 퀵배송에 대한 요금처리 예외처리
			if($cart['shipping_set_code'] == 'quick'){
				$cart['shipping_method_name'] .= '<div id="price_'.$ship_grp.'">' . get_currency_price($goods['shipping_group_price'][$ship_grp][$cart['shipping_policy']],2) . '</div>';
			}

			// 개별배송비 뽑아내기
			if($baserule['shipping_calcul_type'] == 'each'){
				$ship_each[$ship_grp]	= $shipping_group_list[$ship_grp]['grp_shipping_price'];
			}else{
				$ship_bundle[$ship_grp] = $shipping_group_list[$ship_grp]['grp_shipping_price'];
			}

			$old_shipping_list['list'][$key] = $cart;
		}

		$goods['shipping_price']['goods']	= array_sum($ship_each);
		$goods['shipping_price']['shop']	= array_sum($ship_bundle);

		// list 교체
		unset($goods['list']);
		$goods['list'] = $old_shipping_list['list'];

		// 배송비 매칭
		if(array_sum($goods['shipping_price']) != $shipping_group_list['total_shipping_price']){
			// 배송비 산정에 문제가 있는경우 신 배송비 금액을 그대로 인계..
			$goods['shipping_price'][0] = $shipping_group_list['total_shipping_price'];
		}

		return $goods;
	}

	// 배송 가능여부 체크
	public function check_shipping_possible($order_seq='', $international='international', $international_country='', $street_address='', $zibun_address='') {
		// reset
		$ship_possible = 'Y'; # E : 배송그룹 없음, N : 배송 불가, H : 희망배송 불가

		if(!$order_seq || !$international) {
			return false;
		}

		$this->ci->load->model('shippingmodel');

		$nation = preg_replace("/[가-힣 ]+\(([a-z]*)\)/i", '$1',$international_country ? $international_country : 'KOREA');
		$international_country = $this->ci->shippingmodel->get_gl_nation($nation);

		switch($international) {
			case 'domestic':
				$ini_info = $this->set_ini(array(
					'nation' => $nation,
					'street_address' => $street_address,
					'zibun_address' => $zibun_address
				));
				break;
			case 'international':
				$ini_info = $this->set_ini(array(
					'nation' => $nation
				));
				break;
		}

		$shipping_possible = $this->get_shipping_possible($order_seq);

		// 한번이라도 ship_possible이 Y가 아닌 값이 포함된 경우 배송불가!
		if(is_array($shipping_possible)) {
			foreach($shipping_possible as $shipping_group=>$shipping_data) {
				if($shipping_data['ship_possible'] != 'Y') {
					$ship_possible = $shipping_data['ship_possible'];
				}
			}
		} else {
			$ship_possible = 'N';
		}

		return $ship_possible;
	}

	// 주문 후 배송지 변경 가능여부 체크
	protected function get_shipping_possible($order_seq='') {

		// reset
		$buff = array();
		$free_shipping_exists	= false;

		$CI = $this->ci;
		$CI->load->model('ordermodel');
		$CI->load->model('shippingmodel');

		// 상품별로 loop
		$order_shipping_rows = $CI->ordermodel->get_order_shipping($order_seq);

		foreach($order_shipping_rows as $shipping_group_code=>$order_shipping_row) {
			// reset
			unset($shipping, $cfg, $addBind, $store_info);

			$shipping['group_seq']	= preg_replace('/^([0-9]+)_([0-9]+)_([a-z_]+)$/i', '$1', $shipping_group_code);
			$shipping['set_seq']		= preg_replace('/^([0-9]+)_([0-9]+)_([a-z_]+)$/i', '$2', $shipping_group_code);
			$shipping['set_code']		= preg_replace('/^([0-9]+)_([0-9]+)_([a-z_]+)$/i', '$3', $shipping_group_code);

			if($shipping['set_code'] == 'direct_store') { // 매장수령상품
				$addBind[]	= $shipping['set_seq'];
				$sql		= "
					SELECT	ship_set.*,
							ship_grp.shipping_provider_seq AS ship_provider_seq,
							ship_grp.shipping_calcul_type,
							ship_grp.shipping_calcul_free_yn,
							ship_grp.shipping_std_free_yn,
							ship_grp.shipping_add_free_yn,
							ship_grp.shipping_hop_free_yn,
							ship_grp.refund_address_seq
					FROM	fm_shipping_set AS ship_set,
							fm_shipping_grouping AS ship_grp
					WHERE	ship_set.shipping_group_seq = ship_grp.shipping_group_seq
							AND ship_set.shipping_set_seq = ?
				";

				$query		= $this->ci->db->query($sql, $addBind);
				$result		= $query->row_array();

				$tmp_r['shipping_group_seq']	= $result['shipping_group_seq'];
				$tmp_r['shipping_set_seq']		= $result['shipping_set_seq'];
				$tmp_r['shipping_set_code']		= $result['shipping_set_code'];

				$tmp_r['shipping_set_name']		= $result['shipping_set_name'];
				$tmp_r['shipping_provider_seq']	= $result['ship_provider_seq'];
				$tmp_r['delivery_limit']		= $result['delivery_limit'];
				$tmp_r['default_yn']			= $result['default_yn'];
				$tmp_r['shipping_calcul_type']	= $result['shipping_calcul_type'];
				$tmp_r['shipping_calcul_free_yn']= $result['shipping_calcul_free_yn'];
				$tmp_r['shipping_std_free_yn']	= $result['shipping_std_free_yn'];
				$tmp_r['shipping_add_free_yn']	= $result['shipping_add_free_yn'];
				$tmp_r['shipping_hop_free_yn']	= $result['shipping_hop_free_yn'];
				$tmp_r['delivery_nation']		= $result['delivery_nation'];

				$cfg['baserule'] = $tmp_r;

				// 매장정보 추출
				$store_info	= $this->get_shipping_store($result['shipping_group_seq']);
			} else { // 매장수령 외
				$sql		= "
					SELECT
						ship_opt.*,
						ship_set.delivery_nation,
						ship_set.hopeday_required,
						ship_cost.shipping_cost,
						ship_cost.shipping_cost_seq
					FROM
						fm_shipping_option AS ship_opt
						left join fm_shipping_cost AS ship_cost on ship_opt.shipping_opt_seq=ship_cost.shipping_opt_seq ,
						fm_shipping_set AS ship_set
					WHERE
						ship_opt.shipping_set_seq = ship_set.shipping_set_seq AND
						ship_opt.shipping_group_seq = ?
				";
				$addBind[]	= $shipping['group_seq'];
				if	($shipping['set_seq'] == 'default'){
					$sql .= "AND ship_opt.default_yn = ? ";
					$addBind[]	= 'y';
				}else{
					$sql .= "AND ship_opt.shipping_set_seq = ? ";
					$addBind[] = $shipping['set_seq'];
					$sql .= "AND ship_opt.shipping_set_code = ? ";
					$addBind[] = $shipping['set_code'];
				}
				$query = $this->ci->db->query($sql, $addBind);
				$result = $query->result_array();

				// 장바구니의 저장된 정책과 현재 정책이 불일치할 시 기본 정책 추출
				if(!$result){
					unset($addBind);
					$sql		= "
						SELECT
							ship_opt.*,
							ship_set.delivery_nation,
							ship_cost.shipping_cost,
							ship_cost.shipping_cost_seq
						FROM
							fm_shipping_option AS ship_opt
							left join fm_shipping_cost AS ship_cost on ship_opt.shipping_opt_seq=ship_cost.shipping_opt_seq ,
							fm_shipping_set AS ship_set
						WHERE
							ship_opt.shipping_set_seq = ship_set.shipping_set_seq AND
							ship_opt.shipping_group_seq = ? AND
							ship_opt.default_yn = ?
					";
					$addBind[] = $shipping['group_seq'];
					$addBind[] = 'y';
					$query        = $this->ci->db->query($sql, $addBind);
					$result        = $query->result_array();
				}

				// 최종 결정된 배송그룹, 배송방법, 코드 재정의
				$shipping['group_seq']	= $result[0]['shipping_group_seq'];
				$shipping['set_seq']	= $result[0]['shipping_set_seq'];
				$shipping['set_code']	= $result[0]['shipping_set_code'];

				// 배송 종류별 묶음 처리
				if	($result) foreach($result as $j => $opt){
					// 지정 배송방법명이 아닌 경우 기본 배송방법명으로 대체
					if	(!$opt['shipping_set_name']){
						$opt['shipping_set_name']	= $this->shipping_method_list[$opt['shipping_set_code']];
					}
					$cfg[$opt['shipping_set_type']][] = $opt;
				}
				$cfg['baserule'] = $cfg['std'][0];
			}
			$shipping_group[$shipping_group_code] = $cfg;

			$order_shipping_row['shipping_set_seq']   = $cfg['baserule']['shipping_set_seq'];
			$order_shipping_row['shipping_set_code'] = $cfg['baserule']['shipping_set_code'];

			// 배송그룹 키
			$shipping_key = $cfg['baserule']['shipping_group_seq'] . '_' . $cfg['baserule']['shipping_set_seq'] . '_' . $cfg['baserule']['shipping_set_code'];

			// 배송여부 판별
			if($cfg['baserule']['delivery_nation'] == $this->g_type){
				$buff[$shipping_key]['ship_possible']	= 'Y'; // cost에서 체크
			}else{ // 배송불가
				$buff[$shipping_key]['ship_possible']	= 'N'; // 배송불가
			}

			// 배송그룹 체크
			if(!$shipping['group_seq']){
				$buff[$shipping_key]['ship_possible'] = 'E'; // 배송그룹 없음
			}

			$buff[$shipping_key]['cfg']         = $cfg;
		}

		foreach($buff as $shipping_key=>$row) {
			// 배송그룹별
			if($this->shipping_type_arr) foreach ($this->shipping_type_arr as $k => $shipping_type){
				// 계산 불필요 항목 Pass
				if(($shipping_type == 'hop' && !$row['ship_possible']) || $row['ship_possible'] == 'N' || $row['cfg']['baserule']['shipping_set_code'] == 'direct_store'){
					continue;
				}

				if($cfg = $row['cfg'][$shipping_type]){

					# 무료계산-묶음 배송 그룹과 함께 주문되는 배송그룹 체크(단, 무료계산-묶음 배송의 배송책임판매자와 동일해야함)
					if( $free_shipping_exists && ($free_shipping_provider == $cfg[0]['shipping_provider_seq']) && $cfg[0]['shipping_calcul_free_yn'] == 'Y' && $cfg[0]['shipping_' . $shipping_type . '_free_yn'] == 'Y'){
						$bundle_free = true;
					}else{
						$bundle_free = false;
					}

					# 무료계산-묶음 배송 >> 표기만 하고 금액은 하단에서 무료처리
					# or 무료계산-묶음 배송 그룹과 함께 주문되는 배송그룹 상품(배송책임 판매자가 동일)
					# or 배송방법이 무료인 배송정책
					if	($cfg[0]['shipping_calcul_type'] == 'free' || $bundle_free || ($shipping_type == "std" && $cfg[0]['shipping_opt_type'] == 'free')){
						$row['shipping_' . $shipping_type . '_group_free']	= 'Y';
					}

					// 배송비 구간정보 추출
					$row['shipping_' . $shipping_type . '_cfg'] = $this->get_shipping_option($row, $cfg);
				}

				if($row['shipping_' . $shipping_type . '_cfg']){
					$resCost = $this->get_shipping_cost($row['shipping_' . $shipping_type . '_cfg']);

					// 배송 불가 판별 -- N : 기본배송불가 / H : 희망배송불가
					if	($resCost['possible_yn'] == 'N'){
						if	($shipping_type == 'std')		$row['ship_possible']	= 'N';
						else if ($shipping_type == 'hop')	$row['ship_possible']	= 'H';
					}
				}
			}

			$buff[$shipping_key] = $row;
		}

		return $buff;
	}

	// 상품 금액 기준 배송비 추출 :: 2017-12-29 lwh
	public function get_goods_for_shipprice($ship_grp_seq, $acc_stand, $opt_type){

		// 기본 배송방법 검색
		$set_sql	= "SELECT * FROM `fm_shipping_set` WHERE `default_yn` = 'Y' AND shipping_group_seq = '" . $ship_grp_seq . "'";
		$query		= $this->ci->db->query($set_sql);
		$result		= $query->row_array();

		// 배송 방법 추출
		$opt_sql	= "SELECT * FROM `fm_shipping_option` WHERE `shipping_set_seq` = '" . $result['shipping_set_seq'] . "' AND `shipping_set_type` = 'std' AND `section_st` <= '" . $acc_stand . "' AND `section_ed` > '" . $acc_stand . "'";
		if($opt_type){
			$opt_sql.= "AND `shipping_opt_type` = '" . $opt_type . "'";
		}
		$query		= $this->ci->db->query($opt_sql);
		$result		= $query->row_array();

		// 배송 금액 추출
		$cost_sql	= "SELECT * FROM `fm_shipping_cost` WHERE `shipping_opt_seq` = '" . $result['shipping_opt_seq'] . "' ORDER BY shipping_cost_seq ASC LIMIT 1";
		$query		= $this->ci->db->query($cost_sql);
		$result		= $query->row_array();

		return $result['shipping_cost'];
	}
	
	/**
	 * 주문 생성 용 배송 그룹 목록 구성 by hed
	 * OrderService 오픈 마켓에서 사용하는 소스가 기반이며 현재는 O2O 주문에 특화되어 있음, 
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $in				인풋 데이터
	 *		$orderSeq						주문번호
	 *		$orderSeqList					외부 주문번호 고유키
	 *		$shippingGroupSeqArr			배송 그룹 고유키
	 *		$marketProductArr				외부 판매 상품 고유키 목록
	 *		$shippingProviderArr			배송 입점사 그룹 고유키
	 *		$shippingParsms					배송 set 검색 조건
	 *		$shippingStoreScmTypeArr		매장 연결 여부
	 *		$shippingAddressSeqArr			수령매장 고유키
	 * 
	 * @param array &$out				리턴 데이터
	 *		$shippingInfoList				배송 정보 목록
	 *		$shippingGroupList				배송 그룹 목록
	 *		$productShippingCodeList		상품별 배송코드 목록
	 */
	function make_shipping_group_list($in, &$out){
		$this->ci->load->model('shippingmodel');
		
		// 인풋 데이터
		$orderSeq					= $in['orderSeq'];
		$orderSeqList				= $in['externalOrderSeqList'];
		$shippingGroupSeqArr		= $in['shippingGroupSeqArr'];
		// $shippingCostArr			= explode(',', $row['shipping_cost_list']);
		// $shippingTypeArr			= explode(',', $row['shipping_type_list']);
		// $extraShippingCostArr		= explode(',', $row['extra_shipping_cost_list']);
		$marketProductArr			= $in['marketProductArr'];
		$shippingProviderArr		= $in['shippingProviderArr'];
		$shippingParsms				= $in['shippingParsms'];
		
		$shippingStoreScmTypeArr	= $in['shippingStoreScmTypeArr'];
		$shippingAddressSeqArr		= $in['shippingAddressSeqArr'];
		
		// 리턴 데이터
		$shippingGroupList			= array();
		$productShippingCodeList	= array();
		
		for($si = 0, $scnt = count($shippingGroupSeqArr); $si < $scnt; $si++) {

			$shippingGroupSeq	= $shippingGroupSeqArr[$si];

			// 사용 배송그룹 저장
			if (isset($shippingInfoList[$shippingGroupSeq]) !== true) {

				$shippingSetList	= $this->ci->shippingmodel->load_shipping_set_list($shippingGroupSeq, $shippingParsms);

				if (is_array($shippingSetList) == true) {
					$shippingGroupInfo	= $this->ci->shippingmodel->get_shipping_group($shippingGroupSeq);
					$defaultSet			= $shippingSetList[key($shippingSetList)];
					$stdOptSeq			= $defaultSet['shipping_opt_seq_list']['std'][key($defaultSet['shipping_opt_seq_list']['std'])];
					$stdCostSeq			= $defaultSet['shipping_cost_seq_list'][$stdOptSeq][key($defaultSet['shipping_cost_seq_list'][$stdOptSeq])];

					// 추가배송비 코드가 없을경우 9 + 기본배송비코드
					if(is_array($defaultSet['shipping_opt_seq_list']['add']) == true) {
						$addOptSeq		= $defaultSet['shipping_opt_seq_list']['add'][key($defaultSet['shipping_opt_seq_list']['add'])];
						$addCostSeq		= $defaultSet['shipping_cost_seq_list'][$addOptSeq][key($defaultSet['shipping_cost_seq_list'][$addOptSeq])];
					} else {
						$addOptSeq		= 0;
						$addCostSeq		= 0;
					}


					$shippingInfo['shipping_group_seq']		= $shippingGroupSeq;
					$shippingInfo['shipping_calcul_type']	= $shippingGroupInfo['shipping_calcul_type'];
					$shippingInfo['shipping_calcul_free_yn']= $shippingGroupInfo['shipping_calcul_free_yn'];
					$shippingInfo['shipping_std_free_yn']	= $shippingGroupInfo['shipping_std_free_yn'];
					$shippingInfo['shipping_add_free_yn']	= $shippingGroupInfo['shipping_add_free_yn'];
					$shippingInfo['shipping_hop_free_yn']	= $shippingGroupInfo['shipping_hop_free_yn'];
					$shippingInfo['shipping_set_seq']		= $defaultSet['shipping_set_seq'];
					$shippingInfo['shipping_set_code']		= $defaultSet['shipping_set_code'];
					$shippingInfo['shipping_set_name']		= $defaultSet['shipping_set_name'];
					$shippingInfo['store_use']				= 'N';
					$shippingInfo['delivery_nation']		= 'korea';	// 배송국가
					$shippingInfo['delivery_limit']			= 'unlimit';
					$shippingInfo['std_opt_seq']			= $stdOptSeq;
					$shippingInfo['std_cost_seq']			= $stdCostSeq;
					$shippingInfo['add_opt_seq']			= $addOptSeq;
					$shippingInfo['add_cost_seq']			= $addCostSeq;
					$shippingInfo['shipping_opt_type']		= $defaultSet['shipping_opt_type'];
					$shippingInfo['std_shipping_cost']		= $defaultSet['shipping_cost']['std'][0];


					if (isset($defaultSet['shipping_cost']['add'][0]) == true)
						$shippingInfo['add_shipping_cost']	= $defaultSet['shipping_cost']['add'][0];
					else
						$shippingInfo['add_shipping_cost']	= 0;

					$shippingInfoList[$shippingGroupSeq]	= $shippingInfo;
				} else {
					continue;
				}


			}

			$nowShippingInfo		= $shippingInfoList[$shippingGroupSeq];
			$shippingGroupCode		= "{$nowShippingInfo['shipping_group_seq']}_{$nowShippingInfo['shipping_set_seq']}_{$nowShippingInfo['shipping_set_code']}";


			if ($nowShippingInfo['shipping_calcul_type'] == 'each')
				$shippingGroupCode	.= "_{$orderSeqList[$si]}";


			$nowShippingType	= 'free';

			if (isset($shippingGroupList[$shippingGroupCode]) !== true) {
				$nowGroupInfo['seq_list']				= array();
				$nowGroupInfo['shipping_group']			= $shippingGroupCode;
				$nowGroupInfo['shipping_group_seq']		= $nowShippingInfo['shipping_group_seq'];
				$nowGroupInfo['shipping_set_seq']		= $nowShippingInfo['shipping_set_seq'];
				$nowGroupInfo['shipping_method']		= $nowShippingInfo['shipping_set_code'];
				$nowGroupInfo['shipping_type']			= $nowShippingType;
				$nowGroupInfo['order_seq']				= $orderSeq;
				$nowGroupInfo['provider_seq']			= $shippingProviderArr[$si];
				$nowGroupInfo['shipping_set_name']		= $nowShippingInfo['shipping_set_name'];
				$nowGroupInfo['shipping_cost']			= 0;
				$nowGroupInfo['postpaid']				= 0;
				$nowGroupInfo['delivery_if']			= '';
				$nowGroupInfo['international_cost']		= 0;
				$nowGroupInfo['delivery_cost']			= 0;
				$nowGroupInfo['add_delivery_cost']		= 0;
				$nowGroupInfo['hop_delivery_cost']		= 0;
				$nowGroupInfo['shipping_cost_krw']		= 0;
				if(!empty($shippingStoreScmTypeArr[$si])) {
					$nowGroupInfo['store_scm_type']			= $shippingStoreScmTypeArr[$si];
				}
				if(!empty($shippingAddressSeqArr[$si])) {
					$nowGroupInfo['shipping_address_seq']	= $shippingAddressSeqArr[$si];
				}
				

				$shippingGroupList[$shippingGroupCode]	= $nowGroupInfo;
			}


			switch ($nowShippingType) {
				case	'postpaid' :
					$shippingGroupList[$shippingGroupCode]['postpaid']		+= $shippingCostArr[$si] + $extraShippingCostArr[$si];
					break;

				case	'prepay' :
					$shippingGroupList[$shippingGroupCode]['shipping_cost']	+= $shippingCostArr[$si] + $extraShippingCostArr[$si];
					break;
			}

			if (isset($productShippingCodeList[$marketProductArr[$si]]) !== true) {
				$productShippingCodeList[$marketProductArr[$si]]['basic_shipping_cost']	= $shippingCostArr[$si];
				$productShippingCodeList[$marketProductArr[$si]]['add_shipping_cost']	= $extraShippingCostArr[$si];
			} else {
				$productShippingCodeList[$marketProductArr[$si]]['basic_shipping_cost']	+= $shippingCostArr[$si];
				$productShippingCodeList[$marketProductArr[$si]]['add_shipping_cost']	+= $extraShippingCostArr[$si];
			}

			$shippingGroupList[$shippingGroupCode]['delivery_cost']			+= $shippingCostArr[$si];
			$shippingGroupList[$shippingGroupCode]['add_delivery_cost']		+= $extraShippingCostArr[$si];

			$shippingGroupList[$shippingGroupCode]['seq_list'][]			= $orderSeqList[$si];

		}
		
		
		// 결과 반환
		$out['shippingInfoList']			= $shippingInfoList;
		$out['shippingGroupList']			= $shippingGroupList;
		$out['productShippingCodeList']		= $productShippingCodeList;
	}
	
	/**
	 * 주문 생성 용 배송 그룹 저장 구성 by hed
	 * OrderService 오픈 마켓에서 사용하는 소스가 기반이며 현재는 O2O 주문에 특화되어 있음, 
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $in				인풋 데이터
	 *		$orderSeq						주문번호
	 *		$shippingInfoList				배송 정보 목록
	 *		$shippingGroupList				배송 그룹 목록
	 * 
	 * @param array &$out				리턴 데이터
	 *		$account_ins_shipping			정산용 배송 정보
	 *		$shippingSeqList				주문 배송 고유키 목록
	 */
	function save_shipping($in, &$out){
		$this->ci->load->model('shippingmodel');
		$this->ci->load->model('providermodel');
		
		// 인풋 데이터
		$orderSeq						= $in['orderSeq'];
		$shippingInfoList				= $in['shippingInfoList'];
		$shippingGroupList				= $in['shippingGroupList'];
		
		// 리턴 데이터
		// 배송비 저장
		$shippingSeqList	= array();
		foreach ((array)$shippingGroupList as $shippingInfo) {


			$shippingLogInfo									= array();
			$shippingLogInfo['baserule']						= $shippingInfoList[$shippingInfo['shipping_group_seq']];
			$shippingLogInfo['std'][0]['shipping_opt_seq']		= ($shippingLogInfo['baserule']['std_opt_seq']) ? $shippingLogInfo['baserule']['std_opt_seq'] : $shippingLogInfo['baserule']['shipping_opt_seq'];
			$shippingLogInfo['std'][0]['shipping_set_type']		= 'std';
			$shippingLogInfo['std'][0]['shipping_opt_type']		= $shippingLogInfo['baserule']['shipping_opt_type']['std'] ? $shippingLogInfo['baserule']['shipping_opt_type']['std'] : $shippingLogInfo['baserule']['shipping_opt_type'];
			$shippingLogInfo['std'][0]['delivery_limit']		= $shippingLogInfo['baserule']['delivery_limit'];
			$shippingLogInfo['std'][0]['section_st']			= 0;
			$shippingLogInfo['std'][0]['section_ed']			= 0;


			if ($shippingLogInfo['baserule']['add_opt_seq'] > 0) {
				$shippingLogInfo['add'][0]['shipping_opt_seq']	= $shippingLogInfo['baserule']['add_opt_seq'];
				$shippingLogInfo['add'][0]['shipping_set_type']	= 'add';
				$shippingLogInfo['add'][0]['shipping_opt_type']	= $shippingLogInfo['baserule']['shipping_opt_type']['add'];
				$shippingLogInfo['add'][0]['delivery_limit']	= $shippingLogInfo['baserule']['delivery_limit'];
				$shippingLogInfo['add'][0]['section_st']		= 0;
				$shippingLogInfo['add'][0]['section_ed']		= 0;
			}

			$shippingParams			= array();
			$shippingParams['cfg']	= $shippingLogInfo;
			$this->ci->shippingmodel->set_shipping_log($orderSeq, $shippingParams);

			if ($shippingInfo['shipping_cost'] > 0)
				$shippingInfo['shipping_cost_krw']				= get_currency_exchange($shippingInfo['shipping_cost'], "KRW", $this->ci->config_system['basic_currency']);
			else
				$shippingInfo['shipping_cost_krw']				= 0;

			$setShippingData		= filter_keys($shippingInfo, $this->ci->db->list_fields('fm_order_shipping'));
			$this->ci->db->insert('fm_order_shipping', $setShippingData);
			$shippingSeq			= $this->ci->db->insert_id();

			/**
			* 정산개선 - 배송처리 : 순서변경주의 시작
			* data : 주문정보
			* insert_params : 배송정보
			* @ accountallmodel
			**/
			$shipping_charge = "";
			$return_shipping_charge = "";
			if	($shippingInfo['provider_seq'] > 1){
				$provider	= $this->ci->providermodel->get_provider_one($shippingInfo['provider_seq']);
				$shipping_charge		= $provider['shipping_charge'];
				$return_shipping_charge	= $provider['return_shipping_charge'];
			}
			$shippingInfo['order_form_seq']			= $shippingSeq;
			$shippingInfo['shipping_seq']			= $shippingSeq;
			$shippingInfo['shipping_charge']		= $shipping_charge;
			$shippingInfo['return_shipping_charge']	= $return_shipping_charge;
			$shippingInfo['accountallmodeltest']	= "accountallmodeltest_ship";
			$account_ins_shipping[$shippingSeq] = $shippingInfo;
			/**
			* 정산개선 - 배송처리 : 순서변경주의 끝
			* data : 주문정보
			* insert_params : 배송정보
			* @
			**/

			foreach((array)$shippingInfo['seq_list'] as $tmeMarketSeq)
				$shippingSeqList[$tmeMarketSeq]		= $shippingSeq;

		}
		
		// 결과 반환
		$out['shippingSeqList']				= $shippingSeqList;
		$out['account_ins_shipping']		= $account_ins_shipping;
	}
	
	public function getOrderShippingInfo(string $orderSeq = '')
	{
		$this->ci->load->model('ordermodel');		
		$orderShippingRow = $this->ci->ordermodel->get_shipping($orderSeq);
		$shippingInfo = explode('_', $orderShippingRow[0]['shipping_group']);
		list($shippingGroupSeq, $shippingSetSeq, $shippingSetCode) = $shippingInfo;
		$shippingMethod = $orderShippingRow[0]['shipping_method'];
		// 매장수령 or 직접배송인 경우
		if ($shippingInfo[2] === 'direct') {
			$shippingSetCode = $shippingInfo[2].'_'.$shippingInfo[3];
		}
		return [$shippingGroupSeq, $shippingSetSeq, $shippingSetCode, $shippingMethod];
	}
	
}

// END shipping Class

/* End of file shipping.php */
/* Location: ./app/libraries/shipping.php */