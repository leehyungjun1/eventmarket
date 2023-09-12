<?php
class exportmodel extends CI_Model {
	public function __construct()
	{
		$this->load->helper('shipping');
		// 주문상태별 처리 가능한 액션 정의
		$action['complete_export'] 	= array('45'); // 출고완료
		$action['going_delivery'] = array('55'); // 배송중
		$action['complete_delivery'] = array('65','55'); // 배송완료
		$this->able_status_action = $action;

		/** 출고완료/배송완료 티켓상품 확정 : 1/2 크론추가
	   * 1 : 값어치 미사용
	   * 2 : 값어치 일부 사용
	   * 3 : 값어치 모두 사용
	   * 4 : 전체 값어치 모두 있고 환불가능기간 만료
	   * 5 : 잔여 값어치 남아 있고 환불가능기간 만료
	   * 6 : 시작 전 전체 값어치 모두 있고 환불
	   * 7 : 시작 전 잔여 값어치 남아 있고 환불
	   * 8 : 종료 후 전체 값어치 모두 있고 환불
	   * 9 : 종료 후 잔여 값어치 남아 있고 환불
	  **/

	  $this->socialcp_status = array('1'=>array('사용대기','값어치 미사용','①'),'2'=>array('부분사용','값어치 일부 사용','②'),
		  '3'=>array('전체사용[종료]','값어치 모두 사용','③'),'4'=>array('전체낙장[종료]','값어치 미사용+환불기간만료','④'),
		  '5'=>array('부분낙장[종료]','값어치 일부 사용+환불기간만료','⑤'),
		  '6'=>array('환불[종료]','값어치 미사용+유효기간 전 환불','⑥'),'7'=>array('환불[종료]','값어치 일부 사용+유효기간 전 환불','⑦'),
		  '8'=>array('환불[종료]','값어치 미사용+유효기간 후 환불','⑧'),'9'=>array('환불[종료]','값어치 일부 사용+유효기간 후 환불','⑨'));

		$this->arr_status = config_load('export_status');
		$this->arr_coupon_status = config_load('coupon_export_status');
	}

	public function insert_export($data, $bundle_export_code = '')
	{
		$this->db->insert('fm_goods_export', $data);
		$export_seq = $this->db->insert_id();

		$export_time_code					= date('ymdH').$export_seq;
		$update_data['export_code']			= 'D'.$export_time_code;
		$update_data['bundle_export_code']	= ($bundle_export_code == 'bundle') ? 'B'.$export_time_code : $bundle_export_code;

		$this->db->where('export_seq',$export_seq);
		$this->db->update('fm_goods_export',$update_data);
		return $update_data;
	}

	public function insert_export_item($data_item,$export_code,$bundle_export_code)
	{
		$insert_param['item_seq'] 			= $data_item['item_seq'];
		$insert_param['export_code'] 		= $export_code;
		$insert_param['bundle_export_code'] = ($bundle_export_code)?$bundle_export_code:'';
		if( $data_item['suboption_seq'] ) $insert_param['suboption_seq'] = $data_item['suboption_seq'];
		else $insert_param['option_seq'] = $data_item['option_seq'];
		$insert_param['ea']				= $data_item['ea'];

		## 마일리지&포인트 지급예정수량 2015-03-26 pjm
		$insert_param['reserve_ea']			= $data_item['reserve_ea'];
		// 물류관리 출고 당시 출고창고 평균매입가
		$insert_param['scm_supply_price']	= $data_item['scm_supply_price'];
		## Npay API전송결과, 상품 주문번호 추가 2016-02-17 pjm
		if($data_item['npay_product_order_id']) {
			$insert_param['npay_status']			= $data_item['npay_status'];
			$insert_param['npay_product_order_id']	= $data_item['npay_product_order_id'];
		}
		## 카카오페이  API전송결과, 상품 주문번호 추가 2021-04-29
		if($data_item['talkbuy_product_order_id']) {
			$insert_param['talkbuy_status']				= $data_item['talkbuy_status'];
			$insert_param['talkbuy_product_order_id']	= $data_item['talkbuy_product_order_id'];
		}		

		$this->db->insert('fm_goods_export_item', $insert_param);
		$export_item_seq = $this->db->insert_id();

		return $export_item_seq;
	}

	public function get_export_for_order($order_seq, $get_type = '')
	{
		// 상품 종류별로 출고 추출
		if( defined('__SELLERADMIN__') === true ){
			$tb_oi_name = 'orditem';
		}else{
			$tb_oi_name = 'oitem';
		}

		if		($get_type == 'goods'){
			$addWhere	= " and ".$tb_oi_name.".goods_kind = 'goods' ";
		}elseif	($get_type == 'coupon'){
			$addWhere	= " and ".$tb_oi_name.".goods_kind = 'coupon' ";
		}

		if(!isset($this->cfg_order)) {
		    $this->cfg_order = config_load('order');
		}

		if( defined('__SELLERADMIN__') === true ){
				$query = "
				select exp.*,
				sum(item.ea) as ea,
				IFNULL(sum(opt.ea),0)+IFNULL(sum(sub.ea),0) as order_ea,
				IFNULL(sum(opt.reserve*opt.ea),0) as reserve,
				IFNULL(sum(opt.point*opt.ea),0) as point,
				IFNULL(sum(sub.reserve*sub.ea),0) as sub_reserve,
				IFNULL(sum(sub.point*sub.ea),0) as sub_point,
				IFNULL(sum(opt.price*item.ea),0) as price,
				IFNULL(sum(sub.price*item.ea),0) as sub_price,
				orditem.goods_kind,
				opt.coupon_input as coupon_input,
				item.coupon_remain_value as coupon_remain_value,
				(select provider_name from fm_provider where provider_seq = orditem.provider_seq ) as provider_name,
				if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) as group_export_code,
				if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export,
				item.npay_product_order_id,
                sum(item.reserve_ea) as sum_reserve_ea,
                sum(item.reserve_buyconfirm_ea) as reserve_buyconfirm_ea
			from
			fm_goods_export exp
			left join fm_goods_export_item item on exp.export_code = item.export_code
			LEFT JOIN fm_order_item orditem ON orditem.item_seq = item.item_seq
			left join fm_order_item_option opt on opt.item_option_seq  = item.option_seq
			left join fm_order_item_suboption sub on sub.item_suboption_seq  = item.suboption_seq
			where
			orditem.provider_seq = {$this->providerInfo['provider_seq']}
			AND exp.order_seq = ?
			".$addWhere."
			group by group_export_code
			order by exp.`status`,exp.export_code
			";
		}else{
			$query = "
			select exp.*,
				sum(item.ea) as ea,
				IFNULL(sum(opt.ea),0)+IFNULL(sum(sub.ea),0) as order_ea,
				IFNULL(sum(opt.reserve*opt.ea),0) as reserve,
				IFNULL(sum(opt.point*opt.ea),0) as point,
				IFNULL(sum(sub.reserve*sub.ea),0) as sub_reserve,
				IFNULL(sum(sub.point*sub.ea),0) as sub_point,
				IFNULL(sum(opt.price*item.ea),0) as price,
				IFNULL(sum(sub.price*item.ea),0) as sub_price,
				oitem.goods_kind,
				opt.coupon_input as coupon_input,
				item.coupon_remain_value as coupon_remain_value,
				(select provider_name from fm_provider where provider_seq = oitem.provider_seq ) as provider_name,
				if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) as group_export_code,
				if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export,
				item.npay_product_order_id,
                sum(item.reserve_ea) as sum_reserve_ea,
                sum(item.reserve_buyconfirm_ea) as reserve_buyconfirm_ea
			from
			fm_goods_export exp
			left join fm_goods_export_item item on exp.export_code = item.export_code
			left join fm_order_item oitem ON oitem.item_seq = item.item_seq
			left join fm_order_item_option opt on opt.item_option_seq  = item.option_seq
			left join fm_order_item_suboption sub on sub.item_suboption_seq  = item.suboption_seq
			where
			exp.order_seq = ?
			".$addWhere."
			group by group_export_code
			order by exp.`status`,exp.export_code
			";
		}
		$query = $this->db->query($query,array($order_seq));

		$this->load->helper('shipping');
		$arr_delivery = config_load('delivery_url');

		foreach($query -> result_array() as $data){
		    $data['buy_confirm_use'] = false;

			// 택배사 조회 :: 2017-02-01 lwh
			$data['delivery_company_array'] = get_shipping_company_provider($data['shipping_provider_seq']);

			if($data['goods_kind']=='coupon'){
				//$data['mstatus'] = $this->arr_coupon_status[$data['status']];
				//$data['mstatus'] .= "<br/>".$this->socialcp_status[$data['socialcp_status']][1];
				if( defined('__ADMIN__') === true || defined('__SELLERADMIN__') === true) {
					$data['mstatus'] = "<span class='red underline' >".$this->socialcp_status[$data['socialcp_status']][2]." ".$this->socialcp_status[$data['socialcp_status']][0].'</span>';
					$data['mstatus'] .= "<br/>";
					$data['mstatus'] .= $this->arr_status[$data['status']];
				}else{
					$data['mstatus'] = $this->arr_status[$data['status']];
					$data['mstatus'] .= " (<span style='color:red' >".$this->socialcp_status[$data['socialcp_status']][2]." ".$this->socialcp_status[$data['socialcp_status']][0].'</span>)';
				}
				$data['confirm_date'] = $this->arr_status[$data['socialcp_confirm_date']];
				$data['coupon_use_value']	= $data['coupon_input'] - $data['coupon_remain_value'];
				$data['mstatus_arr'][0]		= $this->arr_status[$data['status']];
				$data['mstatus_arr'][1]		= $this->socialcp_status[$data['socialcp_status']][2] . $this->socialcp_status[$data['socialcp_status']][0];
				$data['reserve_buyconfirm_ea'] = 0;
			}else{
				if(!$this->cfg_order['buy_confirm_use']) { // 구매확정 버튼 및 구매확정 수량 제어
					$data['reserve_buyconfirm_ea'] = 0;
				} else if ($data['sum_reserve_ea'] > 0 && $data['status'] > '45'){ // 구매확정 버튼은 출고준비상태에서는 비활성

					// 구매확정 중 반품 신청이 있는지 확인
					$params_check_buyconfirm = array();
					$params_check_buyconfirm['order_seq']		= $order_seq;
					$params_check_buyconfirm['export_code']		= $data['export_code'];

					$this->load->model('buyconfirmmodel');
					$check_buyconfirm = $this->buyconfirmmodel->check_ing_return_for_buyconfirm($params_check_buyconfirm);

					$data['buy_confirm_use'] = $check_buyconfirm;
				}
				$data['mstatus'] = $this->arr_status[$data['status']];
			}

			$result[] = $data;
		}
		return $result;
	}

	public function get_export($export_code)
	{
		$query = "select * from fm_goods_export where export_code=? limit 1";
		$query = $this->db->query($query,array($export_code));
		list($result) = $query -> result_array();

		// 택배사 리스트 추출 :: 2016-04-27 lwh
		$arr_delivery = config_load('delivery_url');
		foreach(get_invoice_company($result['shipping_provider_seq']) as $k=>$data){
			$arr_delivery[$k] = $data;
		}

		$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';

		$query = "select *, if(bundle_export_code REGEXP '^B', 'Y', 'N') AS is_bundle_export from fm_goods_export where {$export_field}=? limit 1";
		$query = $this->db->query($query,array($export_code));
		list($result) = $query -> result_array();

		if($result['international'] == 'domestic'){
			if($result['domestic_shipping_method'] == 'delivery'){
				//$tmp = config_load('delivery_url',$result['delivery_company_code']);
				$result['mdelivery'] = $arr_delivery[$result['delivery_company_code']]['company'];
				$result['mdelivery_number'] = $result['delivery_number'];
				if($result['delivery_number']) $result['tracking_url'] = $arr_delivery[$result['delivery_company_code']]['url'].str_replace('-','',$result['delivery_number']);
			}else{
				//배송 방식이 택배(delivery)가 아닌경우 송장 및 추적 URL은 빈값 처리.
				$result['mdelivery'] = $result['shipping_set_name'];
				$result['mdelivery_number'] = '';
				$result['tracking_url'] = '';
			}
		}else{
			$result['mdelivery'] = $result['international_shipping_method'];
			$result['mdelivery_number'] = $result['international_delivery_no'];
			if($result['international_delivery_no']) $result['tracking_url'] = $arr_delivery[$result['international_shipping_method']]['url'].$result['international_delivery_no'];
		}

		return $result;
	}

	public function get_export_bundle($bundle_code)
	{
		$arr_delivery = config_load('delivery_url');
		foreach(get_invoice_company() as $k=>$data){
			$arr_delivery[$k] = $data;
		}

		$select_arr		= array();
		$select_arr[]	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';
		$select_arr[]	= $export_code;

		$bundle_query	= "select * from fm_goods_export where bundle_export_code=?";
		$query			= $this->db->query($bundle_query,array($bundle_code));

		$result			= array();

		foreach((array)$query->result_array() as $row){

			if(isset($result['bundle_export_info']) === false){
				$export_info				= $row;
				$export_info['export_code']	= $export_info['bundle_export_code'];

				if($export_info['international'] == 'domestic'){
					if($export_info['domestic_shipping_method'] == 'delivery'){
						$export_info['mdelivery']			= $arr_delivery[$export_info['delivery_company_code']]['company'];
						$export_info['mdelivery_number']	= $export_info['delivery_number'];

						if($export_info['delivery_number']) {
							$export_info['tracking_url'] = $arr_delivery[$export_info['delivery_company_code']]['url'].$export_info['delivery_number'];
						}
					}
				}else{
					$export_info['mdelivery']			= $export_info['international_shipping_method'];
					$export_info['mdelivery_number']	= $export_info['international_delivery_no'];
					if($export_info['international_delivery_no']){
						$export_info['tracking_url'] = $arr_delivery[$export_info['international_shipping_method']]['url'].$export_info['international_delivery_no'];
					}
				}

				$export_info['is_bundle_export']	= 'Y';
				$result['bundle_export_info']		=  $export_info;
			}

			$result['bundle_order_info'][$row['export_code']]	= $row['order_seq'];
			$row['export_code']	= $row['bundle_export_code'];
		}

		return $result;
	}

	public function get_export_item($export_code)
	{

		$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';

		if( defined('__SELLERADMIN__') === true ){
			$query1 = "
			SELECT
			item.order_seq,
			'opt' opt_type,
			opt.item_seq item_seq,
			opt.item_option_seq option_seq,
			opt.item_option_seq item_option_seq,
			opt.supply_price,
			opt.consumer_price,
			opt.price,
			opt.org_price,
			item.goods_shipping_cost,
			opt.download_seq,
			opt.coupon_sale,
			opt.member_sale,
			opt.fblike_sale,
			opt.mobile_sale,
			opt.promotion_code_sale,
			opt.referer_sale,
			opt.reserve,
			(opt.reserve*(exp.reserve_ea+exp.reserve_buyconfirm_ea)) as out_reserve,
			opt.point,
			(opt.point*(exp.reserve_ea+exp.reserve_buyconfirm_ea)) as out_point,
			opt.step,
			item.goods_name,
			item.goods_kind,
			item.goods_type,
			item.socialcp_input_type,
			item.socialcp_use_return,
			item.socialcp_use_emoney_day,
			item.socialcp_use_emoney_percent,
			item.image,
			item.shipping_seq,
			item.event_seq,
			item.option_international_shipping_status,
			item.adult_goods,
			item.tax,
			shi.provider_seq as shipping_provider_seq,
			shi.shipping_cost,
			shi.refund_shiping_cost,
			shi.swap_shiping_cost,
			shi.shiping_free_yn,
			shi.shipping_type,
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
			opt.goods_code,
			opt.step85,
			opt.step35,
			opt.step45,
			opt.step55,
			opt.step65,
			opt.step75,
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
			item.goods_seq,
			item.goods_shipping_cost,
			opt.ea opt_ea,
			opt.npay_order_id,
			opt.npay_product_order_id,
			opt.talkbuy_order_id,
			opt.talkbuy_product_order_id,
			exp.ea,
			exp.export_item_seq,
			exp.coupon_value,
			exp.coupon_value_type,
			exp.coupon_remain_value,
			item.provider_seq,
			item.individual_refund,
			item.individual_refund_inherit,
			item.individual_export,
			item.individual_return,
			item.socialcp_cancel_use_refund,
			item.socialcp_cancel_payoption,
			item.socialcp_cancel_payoption_percent,
			if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export,
			exp.export_code,
			exp.coupon_serial,
			exp.coupon_st,
			exp.recipient_email,
			exp.recipient_cellphone,
			exp.mail_status,
			exp.sms_status,
			exp.reserve_ea,
			exp.reserve_buyconfirm_ea,
			exp.reserve_return_ea,
			exp.reserve_destroy_ea,
			exp.npay_status,
			opt.package_yn,
			opt.event_sale,
			opt.multi_sale
			FROM
			fm_goods_export_item exp,fm_order_item_option opt,fm_order_item item, fm_order_shipping shi
			WHERE
			exp.option_seq is not null
			AND exp.option_seq = opt.item_option_seq
			AND opt.item_seq = item.item_seq
			AND item.provider_seq = {$this->providerInfo['provider_seq']}
			AND item.shipping_seq = shi.shipping_seq
			AND exp.{$export_field} = ?
			";
			$query2 = "
			SELECT
			item.order_seq,
			'sub' opt_type,
			sub.item_seq item_seq,
			sub.item_suboption_seq option_seq,
			sub.item_option_seq item_option_seq,
			sub.supply_price,
			sub.consumer_price,
			sub.price,
			sub.org_price,
			0 goods_shipping_cost,
			0 download_seq,
			0 coupon_sale,
			member_sale,
			0 fblike_sale,
			0 mobile_sale,
			0 promotion_code_sale,
			0 referer_sale,
			sub.reserve,
			(sub.reserve*(exp.reserve_ea+exp.reserve_buyconfirm_ea)) as out_reserve,
			sub.point,
			(sub.point*(exp.reserve_ea+exp.reserve_buyconfirm_ea)) as out_point,
			sub.step,
			item.goods_name,
			item.goods_kind,
			item.goods_type,
			item.socialcp_input_type,
			item.socialcp_use_return,
			item.socialcp_use_emoney_day,
			item.socialcp_use_emoney_percent,
			item.image,
			item.shipping_seq,
			item.event_seq,
			item.option_international_shipping_status,
			item.adult_goods,
			item.tax,
			shi.provider_seq as shipping_provider_seq,
			shi.shipping_cost,
			shi.refund_shiping_cost,
			shi.swap_shiping_cost,
			shi.shiping_free_yn,
			shi.shipping_type,
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
			sub.goods_code,
			sub.step85,
			sub.step35,
			sub.step45,
			sub.step55,
			sub.step65,
			sub.step75,
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
			item.goods_seq,
			item.goods_shipping_cost,
			sub.ea opt_ea,
			sub.npay_order_id,
			sub.npay_product_order_id,
			sub.talkbuy_order_id,
			sub.talkbuy_product_order_id,
			exp.ea,
			exp.export_item_seq,
			exp.coupon_value,
			exp.coupon_value_type,
			exp.coupon_remain_value,
			item.provider_seq,
			item.individual_refund,
			item.individual_refund_inherit,
			item.individual_export,
			item.individual_return,
			item.socialcp_cancel_use_refund,
			item.socialcp_cancel_payoption,
			item.socialcp_cancel_payoption_percent,
			if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export,
			exp.export_code,
			exp.coupon_serial,
			exp.coupon_st,
			exp.recipient_email,
			exp.recipient_cellphone,
			exp.mail_status,
			exp.sms_status,
			exp.reserve_ea,
			exp.reserve_buyconfirm_ea,
			exp.reserve_return_ea,
			exp.reserve_destroy_ea,
			exp.npay_status,
			sub.package_yn,
			0 as event_sale,
			0 as multi_sale
			FROM
			fm_goods_export_item exp,fm_order_item_suboption sub,fm_order_item item, fm_order_shipping shi
			WHERE
			exp.suboption_seq is not null
			AND exp.suboption_seq = sub.item_suboption_seq
			AND sub.item_seq = item.item_seq
			AND item.provider_seq = {$this->providerInfo['provider_seq']}
			AND item.shipping_seq = shi.shipping_seq
			AND exp.{$export_field} = ?
			";
		}else{

			$query1 = "
			SELECT
			item.order_seq,
			'opt' opt_type,
			opt.item_seq item_seq,
			opt.item_option_seq option_seq,
			opt.item_option_seq item_option_seq,
			opt.supply_price,
			opt.consumer_price,
			opt.price,
			opt.org_price,
			item.goods_shipping_cost,
			opt.download_seq,
			opt.coupon_sale,
			opt.member_sale,
			opt.fblike_sale,
			opt.mobile_sale,
			opt.promotion_code_sale,
			opt.referer_sale,
			opt.reserve,
			(opt.reserve*(exp.reserve_ea+exp.reserve_buyconfirm_ea)) as out_reserve,
			opt.point,
			(opt.point*(exp.reserve_ea+exp.reserve_buyconfirm_ea)) as out_point,
			opt.step,
			item.goods_name,
			item.goods_type,
			item.goods_kind,
			item.socialcp_input_type,
			item.socialcp_use_return,
			item.socialcp_use_emoney_day,
			item.socialcp_use_emoney_percent,
			item.image,
			item.shipping_seq,
			item.event_seq,
			item.option_international_shipping_status,
			item.adult_goods,
			item.tax,
			shi.provider_seq as shipping_provider_seq,
			shi.shipping_cost,
			shi.refund_shiping_cost,
			shi.swap_shiping_cost,
			shi.shiping_free_yn,
			shi.shipping_type,
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
			opt.goods_code,
			opt.step85,
			opt.step35,
			opt.step45,
			opt.step55,
			opt.step65,
			opt.step75,
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
			exp.coupon_remain_value,
			item.goods_seq,
			item.goods_shipping_cost,
			opt.ea opt_ea,
			opt.npay_order_id,
			opt.npay_product_order_id,
			opt.talkbuy_order_id,
			opt.talkbuy_product_order_id,
			exp.ea,
			exp.export_item_seq,
			exp.coupon_value,
			exp.coupon_value_type,
			exp.coupon_remain_value,
			item.provider_seq,
			item.individual_refund,
			item.individual_refund_inherit,
			item.individual_export,
			item.individual_return,
			item.socialcp_cancel_use_refund,
			item.socialcp_cancel_payoption,
			item.socialcp_cancel_payoption_percent,
			if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export,
			exp.export_code,
			exp.coupon_serial,
			exp.coupon_st,
			exp.recipient_email,
			exp.recipient_cellphone,
			exp.mail_status,
			exp.sms_status,
			exp.reserve_ea,
			exp.reserve_buyconfirm_ea,
			exp.reserve_return_ea,
			exp.reserve_destroy_ea,
			exp.npay_status,
			opt.package_yn,
			opt.event_sale,
			opt.multi_sale
			FROM
			fm_goods_export_item exp,fm_order_item_option opt,fm_order_item item, fm_order_shipping shi
			WHERE
			exp.option_seq is not null
			AND exp.option_seq = opt.item_option_seq
			AND opt.item_seq = item.item_seq
			AND item.shipping_seq = shi.shipping_seq
			AND exp.{$export_field} = ?
			";
			$query2 = "
			SELECT
			item.order_seq,
			'sub' opt_type,
			sub.item_seq item_seq,
			sub.item_suboption_seq option_seq,
			sub.item_option_seq item_option_seq,
			sub.supply_price,
			sub.consumer_price,
			sub.price,
			sub.org_price,
			0 goods_shipping_cost,
			0 download_seq,
			0 coupon_sale,
			member_sale,
			0 fblike_sale,
			0 mobile_sale,
			0 promotion_code_sale,
			0 referer_sale,
			sub.reserve,
			(sub.reserve*(exp.reserve_ea+exp.reserve_buyconfirm_ea)) as out_reserve,
			sub.point,
			(sub.point*(exp.reserve_ea+exp.reserve_buyconfirm_ea)) as out_point,
			sub.step,
			item.goods_name,
			item.goods_type,
			item.goods_kind,
			item.socialcp_input_type,
			item.socialcp_use_return,
			item.socialcp_use_emoney_day,
			item.socialcp_use_emoney_percent,
			item.image,
			item.shipping_seq,
			item.event_seq,
			item.option_international_shipping_status,
			item.adult_goods,
			item.tax,
			shi.provider_seq as shipping_provider_seq,
			shi.shipping_cost,
			shi.refund_shiping_cost,
			shi.swap_shiping_cost,
			shi.shiping_free_yn,
			shi.shipping_type,
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
			sub.goods_code,
			sub.step85,
			sub.step35,
			sub.step45,
			sub.step55,
			sub.step65,
			sub.step75,
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
			exp.coupon_remain_value,
			item.goods_seq,
			item.goods_shipping_cost,
			sub.ea opt_ea,
			sub.npay_order_id,
			sub.npay_product_order_id,
			sub.talkbuy_order_id,
			sub.talkbuy_product_order_id,
			exp.ea,
			exp.export_item_seq,
			exp.coupon_value,
			exp.coupon_value_type,
			exp.coupon_remain_value,
			item.provider_seq,
			item.individual_refund,
			item.individual_refund_inherit,
			item.individual_export,
			item.individual_return,
			item.socialcp_cancel_use_refund,
			item.socialcp_cancel_payoption,
			item.socialcp_cancel_payoption_percent,
			if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export,
			exp.export_code,
			exp.coupon_serial,
			exp.coupon_st,
			exp.recipient_email,
			exp.recipient_cellphone,
			exp.mail_status,
			exp.sms_status,
			exp.reserve_ea,
			exp.reserve_buyconfirm_ea,
			exp.reserve_return_ea,
			exp.reserve_destroy_ea,
			exp.npay_status,
			sub.package_yn,
			0 as event_sale,
			0 as multi_sale
			FROM
			fm_goods_export_item exp,fm_order_item_suboption sub,fm_order_item item,fm_order_shipping shi
			WHERE
			exp.suboption_seq is not null
			AND exp.suboption_seq = sub.item_suboption_seq
			AND sub.item_seq = item.item_seq
			AND item.shipping_seq = shi.shipping_seq
			AND exp.{$export_field} = ?
			";
		}
		$query = "(".$query1.") union (".$query2.") order by export_code, shipping_seq, item_seq asc,item_option_seq,opt_type='opt' desc";

		$query = $this->db->query($query,array($export_code,$export_code));
		foreach($query->result_array() as $data){
			//주문상품의 이미지가 없는경우 실제상품의 이미지를 가져옴
			if(!preg_match("/(http|https|ftp):\/\//i",trim($data['image'])) && !(is_file($data['image'])) ) {
				$data['image'] = viewImg($data['goods_seq'],'thumbCart');
			}

			###
			$data['out_supply_price'] = $data['supply_price']*$data['opt_ea'];
			$data['out_commission_price'] = $data['commission_price']*$data['opt_ea'];
			$data['out_consumer_price'] = $data['consumer_price']*$data['opt_ea'];
			$data['out_price'] = $data['price']*$data['opt_ea'];
			$data['out_org_price'] = $data['org_price']*$data['opt_ea'];
			$data['out_refund_price'] = $data['price']*$data['refund_ea'];

			//promotion sale
			$data['out_event_sale'] = $data['event_sale'];
			$data['out_multi_sale'] = $data['multi_sale'];
			$data['out_member_sale'] = $data['member_sale']*$data['opt_ea'];
			$data['out_coupon_sale'] = $data['coupon_sale'];
			$data['out_fblike_sale'] = $data['fblike_sale'];
			$data['out_mobile_sale'] = $data['mobile_sale'];
			$data['out_referer_sale'] = $data['referer_sale'];
			$data['out_promotion_code_sale'] = $data['promotion_code_sale'];

			// total sale
			$out_sale_price	= $data['out_event_sale'] + $data['out_multi_sale'] + $data['out_member_sale'] + $data['out_coupon_sale'] + $data['out_promotion_code_sale'] + $data['out_fblike_sale'] + $data['out_mobile_sale'] + $data['out_referer_sale'];

			// 할인가격
			$data['out_sale_price'] = $data['out_price'] - $out_sale_price;
			$data['sale_price'] = $data['out_sale_price'] / $data['opt_ea'];

			$result[] = $data;
		}
		return $result;
	}

	public function get_export_item_by_item_seq($export_item_seq='',$export_info_seq=array())
	{

		$query = "select * from fm_goods_export_item where ";
		if($export_item_seq){
			$query .= "export_item_seq=? ";
			$where = array($export_item_seq);
		}elseif(is_array($export_info_seq)){
			$where = $tmp = array();
			/*
			foreach($export_info_seq as $k=>$v){
				if($v){
					$tmp[]		= $k ."=?";
					$where[]	= $v;
				}
			}
			*/
			$tmp[] = "item_seq=?";
			$where[] = $export_info_seq['item_seq'];

			if($export_info_seq['export_code']){
				$tmp[] = "export_code=?";
				$where[] = $export_info_seq['export_code'];
			}

			if(!$export_info_seq['suboption_seq']){
				$tmp[] = "option_seq=?";
				$where[] = $export_info_seq['option_seq'];
			}else{
				$tmp[] = "suboption_seq=?";
				$where[] = $export_info_seq['suboption_seq'];
			}

			$query .= implode(" and ",$tmp);
		}

		$query = $this->db->query($query,$where);
		$result = $query->row_array();
		return $result;
	}

	// 옵션별 출고
	public function get_export_item_by_option_seq($option_seq, $order_seq='')
	{
		$query	= "
			select
				a.*,b.domestic_shipping_method
			from
				fm_goods_export_item a,fm_goods_export b
			where
				a.export_code=b.export_code and
				a.option_seq=?
		";
		$bind[]	= $option_seq;

		if($order_seq){
			$query .= "and b.order_seq = ?";
			$bind[]	= $order_seq;
		}
		$query = $this->db->query($query, $bind);
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	// 추가옵션별 출고
	public function get_export_item_by_suboption_seq($suboption_seq)
	{
		$query = "select a.*,b.domestic_shipping_method from fm_goods_export_item a,fm_goods_export b where a.export_code=b.export_code and a.suboption_seq=?";
		$query = $this->db->query($query,array($suboption_seq));
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	public function delete_export_item_by_item_seq($export_item_seq)
	{
		$query = "delete from fm_goods_export_item where export_item_seq=?";
		$this->db->query($query,array($export_item_seq));
	}

	public function update_ea_export_item($export_item_seq,$ea)
	{
		$query = "update fm_goods_export_item set ea=? where export_item_seq=?";
		$this->db->query($query,array($ea,$export_item_seq));
	}

	// 출고 상태 변경
	public function set_status($code,$status){

		$export_field	= (preg_match('/^B/', $code)) ? 'bundle_export_code' : 'export_code';

		if($status=='75'){
			$query = "update `fm_goods_export` set `status`=? , `shipping_date`=? where `{$export_field}`=?";
			$this->db->query($query,array($status, date("Y-m-d"), $code));
		}else if($status=='55'){
			$query = "update `fm_goods_export` set `status`=?, `complete_date`=? where `{$export_field}`=?";
			$this->db->query($query,array($status, date("Y-m-d"), $code));
		}else{
			$query = "update `fm_goods_export` set `status`=? where `{$export_field}`=?";
			$this->db->query($query,array($status,$code));
		}
	}

	public function delete_export($code){

		$export_field	= (preg_match('/^B/', $code)) ? 'bundle_export_code' : 'export_code';

		$query = "delete from fm_goods_export_item where {$export_field}=?";
		$this->db->query($query,array($code));
		$query = "delete from fm_goods_export where {$export_field}=?";
		$this->db->query($query,array($code));
	}

	public function exec_complete_export($export_code,$cfg_order,$mode='',$system=''){
		$CI =& get_instance();

		# npay 주문건 배송정보 변경 불가 처리
		$npay_use = npay_useck();
		if($npay_use){
			$this->load->model('naverpaymodel');
		}
		$talkbuy_use = talkbuy_useck();
		if($talkbuy_use) $this->load->library('talkbuylibrary');

		$this->load->model('goodsmodel');
		$this->load->model('orderpackagemodel');

		$data_export		= $this->get_export($export_code);
		$data_export_item	= $this->get_export_item($export_code);
		$orders				= $this->ordermodel->get_order($data_export['order_seq']);

		$data_export['orign_order_seq']		= $orders['orign_order_seq'];
		$data_export['top_orign_order_seq'] = $orders['top_orign_order_seq'];

		if(!$cfg_order)$cfg_order = config_load('order');

		if( !in_array($data_export['status'],$this->able_status_action['complete_export']) ){
			// openDialogAlert($this->arr_step[$data_export['status']]."에서는 출고완료를 하실 수 없습니다.",400,140,'parent',"");
			// exit;
			return false;
		}

		# Npay 주문일때 택배사 및 송장 정보 확인(실제 출고처리시 선택된 배송방법 : 택배일때만)
		if($mode != 'order_api' && $npay_use && $orders['pg'] == "npay"){
			if($data_export['domestic_shipping_method'] != "direct_delivery" && preg_match( '/delivery/',$data_export['domestic_shipping_method']) && (!$data_export['delivery_company_code'] || !$data_export['delivery_number'])){
				return array("result"=>false,"msg"=>"NaverPay주문건은 택배사 및  송장번호 입력 필수 입니다.");	// error
			}
		}

		$scm_wh		= $data_export['wh_seq'];
		$data_export_item = $this->get_export_item($export_code);
		if(!$cfg_order)$cfg_order = config_load('order');

		if (!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if ($this->scm_cfg['use'] == 'Y' && $scm_wh > 0) {
			$this->load->model('scmmodel');
			$query				= $this->get_change_status_detail($export_code);
			$whExportOptions	= array();

			foreach($query->result_array() as $exportItem){
				$isPackage			= false;

				// 본사 상품일 때만 재고정보 확인하도록 수정 2019-11-21 by hyem
				// 위탁배송상품일 때에는 scm_wh 에 값이 있을 수도 있기때문에 다시 한 번 체크함
				if($exportItem['provider_seq'] != 1 ) continue;

				if($exportItem['package_yn'] == 'y') {
					$isPackage		= true;
					$packageList	= $this->orderpackagemodel->get_option($exportItem['option_seq']);
				} else if($exportItem['subopt_package_yn'] == 'y'){
					$isPackage		= true;
					$packageList	= $this->orderpackagemodel->get_suboption($exportItem['suboption_seq']);
				}else{
					$goodsinfo		= $this->goodsmodel->get_goods_simple($exportItem['goods_seq']);
				}

				if ($isPackage !== true) {
					if ($exportItem['option_seq'] > 0){
						$data_option_stock	= $this->goodsmodel->get_goods_option_stock($exportItem['goods_seq'],$exportItem['option1'],$exportItem['option2'],$exportItem['option3'],$exportItem['option4'],$exportItem['option5'],2);
						$option_seq			= $data_option_stock[1][0]['option_seq'];
						$optionKey			= "{$exportItem['goods_seq']}option{$option_seq}";
						$optioninfo[]		= $optionKey;

						if( $whExportOptions[$optionKey] ) {
							$whExportOptions[$optionKey]['ea'] += $exportItem['ea'];
						}else{
							$nowOption						= array();
							$nowOption['export_code']		= $export_code;
							$nowOption['optionname']		= $exportItem['option1'].$exportItem['option2'].$exportItem['option3'].$exportItem['option4'].$exportItem['option5'];
							$nowOption['goodsname']			= $exportItem['goods_name'];
							$nowOption['optioninfo']		= $optionKey;
							$nowOption['goodscode']			= $exportItem['opt_goods_code'];
							$nowOption['ea']				= $exportItem['ea'];
							$nowOption['auto_wh']			= $goodsinfo['scm_auto_warehousing'];

							$whExportOptions[$optionKey]	= $nowOption;
						}
					}
				} else {
					foreach ($packageList as $packageRow) {
						$optionKey		= "{$packageRow['goods_seq']}option{$packageRow['option_seq']}";
						$optioninfo[]	= $optionKey;

						if( $whExportOptions[$optionKey] ) {
							$whExportOptions[$optionKey]['ea'] += ($exportItem['ea'] * $packageRow['unit_ea']);
						}else{
							$nowOption					= array();
							$nowOption['export_code']	= $export_code;
							$nowOption['optionname']	= $packageRow['option1'].$packageRow['option2'].$packageRow['option3'].$packageRow['option4'].$packageRow['option5'];
							$nowOption['goodsname']		= $packageRow['goods_name'];
							$nowOption['optioninfo']	= $optionKey;
							$nowOption['goodscode']		= $packageRow['goods_code'];
							$nowOption['ea']			= $exportItem['ea'] * $packageRow['unit_ea'];
							$nowOption['auto_wh']		= $packageRow['scm_auto_warehousing'];

							$whExportOptions[$optionKey]= $nowOption;
						}
					}

				}

			}


			$whStock		= $this->scmmodel->get_warehouse_stock($scm_wh, 'optioninfo', 'array', $optioninfo);

			$whExportList	= array();
			foreach($whExportOptions as $optionKey => $exportInfo) {
				if ($exportInfo['auto_wh']){
					$exportInfo['supplyprice']	= 'X';
				}else{
					//창고 재고수량 확인
					if ($exportInfo['ea'] > $whStock[$optionKey]['ea']){
						//return $export_code; 18-05-09 gcns jhs update
						$err_msg = "[".$export_code."]상품:".$exportInfo['goodsname']." [".$whStock[$optionKey]['wh_name']."]재고(".$whStock[$optionKey]['ea'].")가 주문수량(".$exportInfo['ea'].")보다 적습니다.";

						return array("result"=>false,"msg"=>$err_msg);
					}

					$exportInfo['supplyprice']	= $whStock[$optionKey]['supply_price'];
				}

				$whExportList[]				= $exportInfo;
			}
		}


		//묶음배송처리용
		$doExportList		= array();
		$doOrderList		= array();
		if(preg_match('/^B/', $export_code)){
			$export_list	= $this->get_export_bundle($export_code);

			foreach((array)$export_list['bundle_order_info'] as $now_export_code => $order_seq){
				$doExportList[]	= $now_export_code;
				$doOrderList[]	= $order_seq;
			}

		} else {
			$doExportList[]		= $export_code;
			$doOrderList[]		= $data_export['order_seq'];
		}

		$export_item_cnt			= 0;			# 출고처리할 아이템 갯수
		$r_reservation_goods_seq	= array();		# 출고량 업데이트를 위한 변수선언

		# 상품 재고 체크
		foreach($data_export_item as $item){

			$export_item_cnt++;
			$data_export['items']['ea'][]					 	= $item['ea'];	//출고수량
			$data_export['items']['npay_product_order_id'][] 	= $item['npay_product_order_id'];
			$data_export['items']['talkbuy_product_order_id'][] = $item['talkbuy_product_order_id'];

			if($cfg_order['export_err_handling'] == 'error'){

				if($item['opt_type'] == 'opt'){

					$goods_seq = $item['goods_seq'];
					$option1 = $item['option1'];
					$option2 = $item['option2'];
					$option3 = $item['option3'];
					$option4 = $item['option4'];
					$option5 = $item['option5'];

					$goods_option_data =  $this->goodsmodel->get_goods_option_stock($goods_seq,$option1,$option2,$option3,$option4,$option5,2);
					$goods_stock	= (int) $goods_option_data[0];
					$option_key		= $goods_seq . 'option' . $goods_option_data[1][0]['option_seq'];
					$stock_check	= true;
					if ($this->scm_cfg['use'] == 'Y' && $scm_wh > 0) {
						if ($whExportOptions[$option_key]['auto_wh']){
							$stock_check	= false;
						}
					}
					// 2016.07.19 재고 상관없이 출고 가능 설정 시 체크 건너뜀 추가 pjw
					if($cfg_order['runout'] != 'unlimited' && $stock_check){
						if($goods_stock < $item['ea']){
							return array("result"=>false,"msg"=>"필수옵션의 재고가 출고수량보다 적습니다.");
						}
					}
				}else{

					$goods_seq		= $item['goods_seq'];
					$title			= $item['title1'];
					$suboption		= $item['option1'];
					$goods_stock	= (int) $this->goodsmodel->get_goods_suboption_stock($goods_seq,$title,$suboption);
					// 2016.07.19 재고 상관없이 출고 가능 설정 시 체크 건너뜀 추가 pjw
					if($cfg_order['runout'] != 'unlimited'){
						if($goods_stock < $item['ea']){
							return array("result"=>false,"msg"=>"추가옵션의 재고가 출고수량보다 적습니다.");
						}
					}
				}
			}
		}

		$npay_export_msg = array();

		# 실시간 출고처리(npay_product_order_id / talkbuy_product_order_id 기준)
		if($mode != 'order_api'){

			if($npay_use && $orders['pg'] == "npay") {
				if($data_export['orign_order_seq']){ //교환 재배송
					$data_export['npay_flag_release'] = "redelivery";
				}
				$npay_res			= $this->naverpaymodel->order_export($data_export);
				$export_success_cnt	= (int)$npay_res['success_cnt'];	//성공 아이템갯수
				$export_fail_cnt	= 0;	//실패 아이템갯수
				$export_message		= '';	//실패 메세지
				foreach($npay_res['export_items'] as $npay_product_order_id => $data){
					if($data['result'] != "SUCCESS"){
						if(!$export_message) $export_message = "[".$npay_product_order_id."]".$data['message'];
						$export_fail_cnt++;
					}
				}
				if($export_fail_cnt > 1) $export_message .= " 외 ".($export_fail_cnt-1)."건";
	
				if($export_success_cnt < 1){
					return array("result"=>false,"msg"=>"Npay 출고처리 실패 - ".$export_message);	// error
				}
			} else if($talkbuy_use && $orders['pg'] == "talkbuy"){
				$talkbuy_res		= $this->talkbuylibrary->order_export($data_export);
				$export_success_cnt	= (int)$talkbuy_res['success_cnt'];	//성공 아이템갯수
				$export_fail_cnt	= 0;	//실패 아이템갯수
				$export_message		= '';	//실패 메세지
				foreach($talkbuy_res['export_items'] as $talkbuy_product_order_id => $data){
					if($data['result'] != "SUCCESS"){
						if(!$export_message) $export_message = "[".$talkbuy_product_order_id."]".$data['message'];
						$export_fail_cnt++;
					}
				}
				if($export_fail_cnt > 1) $export_message .= " 외 ".($export_fail_cnt-1)."건";
	
				if($export_success_cnt < 1){
					return array("result"=>false,"msg"=>"Kpay 출고처리 실패 - ".$export_message);	// error
				}
			}else{
				$export_success_cnt = $export_item_cnt;	//npay주문아니면 총 아이템갯수를 성공갯수로
			}
		}else{
			$export_success_cnt = $export_item_cnt;	// 파트너 주문 출고처리 (총 아이템갯수를 성공갯수로)
		}

		if($export_success_cnt > 0){

			// 상품 재고 차감
			foreach($data_export_item as $item){

				# npay 주문건일때 출고처리 실패시 재고차감 건너뜀.
				if($mode != 'order_api'){
					if($npay_use && $orders['pg'] == "npay") {
						if($npay_res['export_items'][$item['npay_product_order_id']]['result'] != "SUCCESS"){
							continue;
						}
					}
					if($talkbuy_use && $orders['pg'] == "talkbuy") {
						if($talkbuy_res['export_items'][$item['talkbuy_product_order_id']]['result'] != "SUCCESS"){
							continue;
						}
					}
				}

				if($item['opt_type'] == 'opt'){
					$this->goodsmodel->stock_option(
						'-',
						$item['ea'],
						$item['goods_seq'],
						$item['option1'],
						$item['option2'],
						$item['option3'],
						$item['option4'],
						$item['option5']
					);

				}else{
					$this->goodsmodel->stock_suboption(
						'-',
						$item['ea'],
						$item['goods_seq'],
						$item['title1'],
						$item['option1']
					);
				}

				if($item['shipping_provider_seq']) $providerList[$item['shipping_provider_seq']]	= 1;

				// 출고량 업데이트를 위한 변수정의
				if(!in_array($item['goods_seq'],$r_reservation_goods_seq)){
					$r_reservation_goods_seq[] = $item['goods_seq'];
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

				if($item['opt_type'] == 'opt') $opt_mode = 'option';
				else $opt_mode = 'suboption';

				$minus_ea = $item['ea'] * -1;
				$this->ordermodel->set_step_ea($data_export['status'],$minus_ea,$item['option_seq'],$opt_mode);
				$this->ordermodel->set_step_ea(55,$item['ea'],$item['option_seq'],$opt_mode);
				$this->ordermodel->set_option_step($item['option_seq'],$opt_mode);

			}


			// 물류관리 창고 재고 차감 ( 출고완료 시에만 )
			if ($this->scm_cfg['use'] == 'Y' && $scm_wh > 0 && is_array($whExportList) && count($whExportList) > 0)
				$this->scmmodel->apply_export_wh($scm_wh, $whExportList);



			// 출고예약량 업데이트
			foreach($r_reservation_goods_seq as $goods_seq){
				$this->goodsmodel->modify_reservation_real($goods_seq);
				$this->goodsmodel->runout_check($goods_seq);
			}

			// 패키지 품절 체크
			foreach($r_package_goods_seq as $goods_seq){
				$this->goodsmodel->runout_check($goods_seq);
			}

			foreach((array)$doOrderList as $nowOrderSeq)
				$this->ordermodel->set_order_step($nowOrderSeq);


			# 총 아이템 갯수 = 출고처리 성공 갯수 일때 출고상태 업데이트
			if($export_item_cnt == $export_success_cnt){
				$this->set_status($export_code,'55');
			}

			// 출고 로그
			if($mode == "order_api"){
				if($orders['pg'] == "talkbuy") {
					$log_mode	= $this->talkbuylibrary->baseLogParams["add_info"];
					$actor		= $this->talkbuylibrary->baseLogParams["actor"];
				} else {
					$log_mode	= "npay";
					$actor		= "Npay";
				}

			}else if($system){
				$actor = $system;
			} else {
				// 입점사일 경우
				if( defined('__SELLERADMIN__') === true ) {
					$actor = $this->providerInfo['provider_name'];
				}else{
					$actor = $this->managerInfo['mname'];
				}
			}
			if(!$actor) $actor = "관리자";

			$logtitle = "출고완료(".$export_code.")";

			//묶음배송 출고로그
			foreach((array)$doOrderList as $nowOrderSeq){
				$this->ordermodel->set_log($nowOrderSeq,'export',$actor,$logtitle,$actor.'가 출고완료를 하였습니다.','',$data_export['export_code'],strtolower($log_mode));
			}

			if($export_item_cnt == $export_success_cnt){
				$CI->exportCompleteCode[] = $data_export['export_code'];
			}
		}

		$this->load->model('batchmodel');
		//묶음배송처리
		foreach((array)$doOrderList as $key => $nowOrderSeq) {

			$orders	= $this->ordermodel->get_order($nowOrderSeq);

			// 연동 마켓 주문은 문자 보내지 않음.
			if ($orders['linkage_id'] == 'connector')
				continue;

			if(!$orders['linkage_id'] && $orders['pg'] != "npay"){
				$arr_params = serialize(array('order_seq'=>$data_export['order_seq'],'export_code'=>$data_export['export_code']));
				$this->batchmodel->insert('export_complete',$arr_params,'none');
			}
		}

		return array("result"=>true);
	}

	// 배송중 처리
	public function exec_going_delivery($export_code,$mode=''){

		$this->load->model('ordermodel');
		$this->load->model('connectormodel');
		$data_export = $this->get_export($export_code);
		if($mode != "Npay" && $mode != "talkbuy" && !in_array($data_export['status'],$this->able_status_action['going_delivery']) ){
			openDialogAlert($this->arr_step[$data_export['status']]."에서는 배송중처리를 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		// 마켓 주문건인지 확인.
		$marketOrder = $this->connectormodel->checkIsMarketOrder($data_export['order_seq']);

		// 상태별 수량 업데이트 및 주문 상태 변경
		$data_export_item = $this->get_export_item($export_code);

		// mode 가 market 인 경우에는 송장전송 안하고 배송중 강제처리
		if ($marketOrder !== false && $mode != 'market') {
			// 연동마켓 주문 정보 확인 및 송장전송
			$this->load->library('Connector');
			$orderService		= $this->connector::getInstance('order');
			$checkMarketOrder	= $orderService->marketOrderDelivery($data_export['order_seq'], $data_export, $data_export_item);

			if($checkMarketOrder['success'] != 'Y'){
				$error = array();
				$error['order_seq']			= $data_export['order_seq'];
				$error['shipping_seq']		= '';
				$error['export_item_seq']	= $export_item_seq;
				$error['export_code']		= $export_code;
				$error['msg']				= "[{$export_code}] : 송장전송 실패 - {$checkMarketOrder['message']}";
				// 에러로그저장
				$this->load->model('exportlogmodel');
				$this->exportlogmodel->export_log('',65,'web_order','goods',$error);
				// 출고건에 송장실패한거 체크
				$this->market_export_fail($export_code);
				return array("result"=>false,"msg"=>$message);
			}
		}
		////////////////////////

		//묶음배송처리용
		$doOrderList		= array();
		if(preg_match('/^B/', $export_code)){
			$export_list	= $this->get_export_bundle($export_code);

			foreach((array)$export_list['bundle_order_info'] as $now_export_code => $order_seq){
				$doOrderList[]	= $order_seq;
			}

		} else {
			$doOrderList[]		= $data_export['order_seq'];
		}

		foreach($data_export_item as $k => $item){
			if($item['opt_type'] == 'opt') $opt_mode = 'option';
			else $opt_mode = 'suboption';

			$minus_ea = $item['ea'] * -1;

			$this->ordermodel->set_step_ea(65,$item['ea'],$item['option_seq'],$opt_mode);
			$this->ordermodel->set_step_ea($data_export['status'],$minus_ea,$item['option_seq'],$opt_mode);

			$this->ordermodel->set_option_step($item['option_seq'],$opt_mode);
		}

		//묶음배송처리
		foreach((array)$doOrderList as $nowOrderSeq)
			$this->ordermodel->set_order_step($nowOrderSeq);

		$this->set_status($export_code,'65');

		$actor = '';
		$subActor = '';
		if($mode == "Npay"){
			$actor = $mode;
		}else if ( $mode == "talkbuy" ) {
			$actor = "Kakao Pay";
			$subActor = "Kakao";
		}else if ( $mode == "system" ) {
			$actor = "자동";
		}else{
			// 입점사일 경우
			if( defined('__SELLERADMIN__') === true ) {
				$actor = $this->providerInfo['provider_name'];
			}else{
				$actor = $this->managerInfo['mname'];
			}
		}
		$subActor = (strlen($subActor) > 0) ? $subActor : $actor;
		$logtitle = '배송중('.$export_code.")";

		//묶음배송처리
		foreach((array)$doOrderList as $nowOrderSeq)
			$this->ordermodel->set_log($nowOrderSeq,'export',$actor,'배송중',$subActor.'가 배송중 처리를 하였습니다.','',$data_export['export_code'],strtolower($mode));

		return array("result"=>true);
	}

	public function market_export_fail($export_code)
	{
		$query = "update fm_goods_export set market_fail='y' where export_code=?";
		$this->db->query($query,array($export_code));
	}

	## 지급예정, 지급된, 반품, 소멸된 마일리지 수량 저장
	public function exec_export_reserve_ea($export_items,$mode='reserve'){

		# reserve		: 적립예정
		# buyconfirm	: 적립완료
		# return		: 반품
		# distory		: 소멸
		foreach($export_items as $items){

			$query = $where = array();

			foreach($items as $field=>$value){
				if(in_array($field,array('reserve_ea','reserve_buyconfirm_ea','reserve_return_ea','reserve_destroy_ea'))){
					$query[] = $field."='".$value."'";
				}
			}

			if($items['export_item_seq']){
				$where[] = "export_item_seq='".$items['export_item_seq']."'";
			}else{
				$where[] = "export_code='".$items['export_code']."'";
				$where[] = "item_seq='".$items['item_seq']."'";
				if($items['option_seq'] && !$items['suboption_seq']) $where[] = "option_seq='".$items['option_seq']."'";
				if($items['suboption_seq']) $where[] = "suboption_seq='".$items['suboption_seq']."'";
			}

			$query_string = "update fm_goods_export_item set ".implode(",",$query) ." where ".implode(" and ",$where);
			$this->db->query($query_string);
		}

	}

	//실물상품의 배송완료처리
	public function exec_complete_delivery($export_code,$system=''){

		$CI =& get_instance();

		$this->load->helper('order');
		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
		$this->load->model('membermodel');
		/*
			반품을 위한 수령확인 시 $save값 false
			system = Npay : npay 주문수집으로 인한 배송완료처리
		*/
		$data_export	= $this->get_export($export_code);

		if(!$cfg_order) $cfg_order = config_load('order');
		$save = !$cfg_order['buy_confirm_use'] ? true : false;

		//npay 사용여부 확인, 취소사유 코드 불러오기
		$npay_use = npay_useck();
		//카카오톡 사용여부 확인
		$talkbuy_use = talkbuy_useck();

		//npay or goods cronjob 무조건 배송완료처리 @2016-07-25 ysm
		if($system != 'Npay' && $system != 'goodsflow_cronjob' ){
			if( !in_array($data_export['status'],$this->able_status_action['complete_delivery']) ){
				if($echo){
					openDialogAlert($this->arr_step[$data_export['status']]."에서는 배송완료를 하실 수 없습니다.",400,140,'parent',"");
					exit;
				}else{
					return $this->arr_step[$data_export['status']]."에서는 배송완료를 하실 수 없습니다.";
				}
			}
		}

		//goods cronjob 무조건 배송완료처리 @2016-07-25 ysm
		if( $system == 'goodsflow_cronjob' ) $system = 'system';

		//묶음배송처리용
		$doExportList		= array();
		$doOrderList		= array();
		if(preg_match('/^B/', $export_code)){
			$export_list	= $this->get_export_bundle($export_code);

			foreach((array)$export_list['bundle_order_info'] as $now_export_code => $order_seq){
				$doExportList[]	= $now_export_code;
				$doOrderList[]	= $order_seq;
			}

		} else {
			$doExportList[]		= $export_code;
			$doOrderList[]		= $data_export['order_seq'];
		}

		if($data_export['status'] < 75){

			$this->set_status($export_code,'75');

			// 배송완료로 변경될 때 출고완료일이 없는 경우 업데이트 해준다.
			// 출고완료일이 없을 경우 출고완료일을 기준으로 처리하는 구매확정 및 이후 정산 프로세스가 동작하지 않음 by hed
			if(empty($data_export['complete_date'])){
				$data['complete_date']	= date('Y-m-d H:i:s');		//출고완료일
				$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';
				$arr_where = array(
					$export_field => $export_code,
					'status >=' => '55',				// 출고완료 이후
					'buy_confirm >=' => 'none',			// 구매확정 전
				);
				$this->db->where($arr_where);
				$this->db->where('npay_order_id IS NULL', NULL, FALSE);		// 네이버페이건이 아님
				$this->db->where('talkbuy_order_id IS NULL', NULL, FALSE);		// 카카오페이 구매 아님
				$this->db->where('complete_date IS NULL', NULL, FALSE);		// 출고완료일이 없음
				$this->db->update('fm_goods_export',$data);
			}

			// 상태별 수량 업데이트 및 주문 상태 변경
			$tot_reserve		= array();
			$tot_point			= array();
			$tot_reserve_ea		= array();
			$chg_reserve	= array();		//마일리지지급수량 변경용
			$data_export_item = $this->get_export_item($export_code);
			if($data_export_item[0]['goods_kind'] == 'coupon') $this->coupon_goods = true;//티켓상품

			foreach($data_export_item as $k => $item){

				$providerList[$item['provider_seq']]	= 1;

				if($item['opt_type'] == 'opt'){
					$mode = 'option';
				}else{
					$mode = 'suboption';
				}

				$minus_ea = $item['ea'] * -1;
				$this->ordermodel->set_step_ea(75,$item['ea'],$item['option_seq'],$mode);
				$this->ordermodel->set_step_ea($data_export['status'],$minus_ea,$item['option_seq'],$mode);

				// 상품에 구매수량 업데이트
				$this->goodsmodel->get_purchase_ea($item['ea'],$item['goods_seq']);

				$this->ordermodel->set_option_step($item['option_seq'],$mode);

				## 구매확정 사용안할때, 지급할마일리지 계산 : 적립예정수량이 있을때 지급 2015-03-26 pjm
				if($save) {

					$tot_reserve_ea[$item['order_seq']] += $item['reserve_ea'];
						if($item['reserve_ea'] > 0){
							$reserve	= 0;
							$point		= 0;
							if($mode == 'option') $reserve = $this->ordermodel->get_option_reserve($item['option_seq']);
							if($mode == 'suboption') $reserve = $this->ordermodel->get_suboption_reserve($item['option_seq']);
							if($mode == 'option') $point = $this->ordermodel->get_option_reserve($item['option_seq'],'point');
							if($mode == 'suboption') $point = $this->ordermodel->get_suboption_reserve($item['option_seq'],'point');

							$tot_reserve[$item['order_seq']]	+= $reserve * $item['reserve_ea'];
							$tot_point[$item['order_seq']]		+= $point * $item['reserve_ea'];

							#지급예정수량 = 지급예정수량 - (반품수량 + 소멸수량)
							#지급완료수량 = 지급예정수량
							$tmp = array();
							$tmp['mode']					= $mode;
							$tmp['export_item_seq']			= $item['export_item_seq'];
							$tmp['reserve_ea']				= 0;
							$tmp['reserve_buyconfirm_ea']	= $item['reserve_ea'];
							$chg_reserve[$item['order_seq']][] = $tmp;

						}
					}
				}

			//묶음배송처리
			foreach((array)$doOrderList as $nowOrderSeq)
				$this->ordermodel->set_order_step($nowOrderSeq);

			$reserve_save = false;
			/**
			- 구매확정 상관없이 메일/문자 발송처리
			- 단, 반품/맞교환 환불신청시 메일/문자 미발송
			- 단, 네이버페이 처리시에는 메일/문자 미발송
			- @2016-12-09
			**/

			if ($system=='system') {
				$actor = $system;
			} elseif($system){
				$actor = $system;
			} else {
				// 입점사일 경우
				if( defined('__SELLERADMIN__') === true ) {
					$actor = $this->providerInfo['provider_name'];
				}else{
					$actor = $this->managerInfo['mname'];
				}
				if(!$actor) $actor = "주문자";
			}
			$logTitle = "배송완료(".$export_code.")";

			//묶음배송처리
			foreach((array)$doOrderList as $nowOrderSeq) {

				$data_order = $this->ordermodel->get_order($nowOrderSeq);

				$log_mode = "";
				if($npay_use && $data_order['pg'] == "npay"){
					$log_mode =  'npay';
				}else if($talkbuy_use && $data_order['pg'] == "talkbuy"){
					$log_mode =  'talkbuy';
					$subActor = "Kakao";
				}
				$subActor = (strlen($subActor) > 0) ? $subActor : $actor;
				$this->ordermodel->set_log($nowOrderSeq,'export',$actor,$logTitle,$subActor.'가 배송완료를 하였습니다.','',$data_export['export_code'],$log_mode);

				## 구매확정 사용안할때, 지급할마일리지 계산 : 적립예정수량이 있을때 지급 @2016-12-09
				if( $save ) {
					// 회원 마일리지 적립
					if($data_order['member_seq'] && $tot_reserve_ea[$nowOrderSeq] > 0){
						if($tot_reserve[$nowOrderSeq]){
							$params_reserve['gb']			= "plus";
							$params_reserve['emoney']		= $tot_reserve[$nowOrderSeq];
							$params_reserve['memo']			= "[".$export_code."] 배송완료";
							$params_reserve['memo_lang'] 	= $this->membermodel->make_json_for_getAlert("mp240",$export_code);    // [%s] 배송완료
							$params_reserve['ordno']		= $data_order['order_seq'];
							$params_reserve['type']			= "order";
							$params_reserve['limit_date'] 	= get_emoney_limitdate('order');
							$this->membermodel->emoney_insert($params_reserve, $data_order['member_seq']);
						}

						if($tot_point[$nowOrderSeq]){
							$params_point['gb']				= "plus";
							$params_point['point']          = $tot_point[$nowOrderSeq];
							$params_point['memo']           = "[".$export_code."] 배송완료";
							$params_point['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp240",$export_code);    // [%s] 배송완료
							$params_point['ordno']          = $data_order['order_seq'];
							$params_point['type']           = "order";
							$params_point['limit_date'] 	= get_point_limitdate('order');
							$this->membermodel->point_insert($params_point, $data_order['member_seq']);
						}

						## 출고아이템에 마일리지 지급예정수량, 지급완료 수량 업데이트 2015-03-31 pjm
						$this->exec_export_reserve_ea($chg_reserve[$nowOrderSeq],'buyconfirm');

						$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';

						if($tot_reserve[$nowOrderSeq] || $tot_point[$nowOrderSeq]){
							$query = "update fm_goods_export set reserve_save = 'save' where {$export_field} = ?";

							$this->db->query($query,array($export_code));
							$reserve_save = true;
						}
					}
				}

				$this->load->model('accountmodel');
				$this->accountmodel->set_account_round($data_export,$data_export_item);


				// 연동 마켓 주문은 문자 보내지 않음.
				if ($data_order['linkage_id'] == 'connector')
					continue;


				//판매마켓, log_mode(네이버페이/카카오페이), 반품/맞교환 문자/이메일 미발송
				if( !$data_order['linkage_id'] && $log_mode == "" && !$CI->_batch_buy_return ){
					// 배송완료시 email/sms
					if ( $this->coupon_goods ) {//티켓상품
						coupon_send_mail_step75($export_code);
						coupon_send_sms_step75($export_code);
					}else{
						send_mail_step75($export_code);

						// 배송완료시 sms
						if( $data_order['order_cellphone'] ){
							$params['delivery_company']	= $data_export['mdelivery'];
							$params['delivery_number']	= $data_export['mdelivery_number'];

							$params['goods_name']	= $data_export_item[0]['goods_name'];
							if	(count($data_export_item) > 1)
								$params['goods_name']	.= '외 '.(count($data_export_item) - 1).'건';

							if	($data_order['payment'] == 'bank'){
								$bank_arr				= explode(' ', $data_order['bank_account']);
								$params['settle_kind']	= $bank_arr[0] . ' 입금확인';
							}else{
								$params['settle_kind']	= $data_order['mpayment'] . ' 입금확인';
							}

							$params['shopName']		= $this->config_basic['shopName'];
							$params['ordno']		= $data_order['order_seq'];
							$params['user_name']	= $data_order['order_user_name'];
							$params['member_seq']	= $data_order['member_seq'];
							$CI->send_for_provider = array();//초기화
							sendSMS_for_provider('delivery', $providerList, $params);
							/**
							- 수동일괄처리시 관리자/입점사 1통 : {설정된 컨텐츠} 외 00건으로 개선
							- @2017-08-17
							**/
							if($CI->send_for_provider['order_cellphone']) {
								$params['provider_mobile']	=  $CI->send_for_provider['order_cellphone'];
							}
							$CI->exportSmsData['delivery']['phone'][]		= $data_order['order_cellphone'];
							$CI->exportSmsData['delivery']['params'][] 		= $params;
							$CI->exportSmsData['delivery']['order_no'][]	= $data_order['order_seq'];

							 # 주문자와 받는분이 다를때 받는분에게도 문자 전송
							 if($data_order['recipient_cellphone'] && (preg_replace("[^0-9]", "", $data_order['order_cellphone']) !=  preg_replace("[^0-9]", "", $data_order['recipient_cellphone']))){
								$CI->exportSmsData['delivery2']['phone'][]		= $data_order['recipient_cellphone'];
								$CI->exportSmsData['delivery2']['params'][]		= $params;
								$CI->exportSmsData['delivery2']['order_no'][]	= $data_order['order_seq'];
							 }
						}
					}
				}
			}//endforeach

			unset($providerList);
		}

		return $reserve_save;
	}

	/**
	** 티켓상품의 주문상태처리
	* statustype :  cancel(취소(환불)), expired_prev(환불가능기간전 완료), expired_next(환불가능기간후 완료)
	* export_code : 출고번호
	* pointsave : 포인트/마일리지 지급여부
	* cancelpercent : 취소(환불)시 환불금액 제외율
	* socialcp_confirm : system, mname, 주문자 상태변경 작업자
	**/
	public function socialcp_exec_complete_delivery($export_code,$pointsave=true,$cancelpercent,$socialcp_confirm,$statustype='cancel'){
		$this->load->helper('order');
		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
		$this->load->model('membermodel');
		$this->load->model('accountmodel');

		$data_export	= $this->get_export($export_code);
		$orders			= $this->ordermodel->get_order($data_export['order_seq']);
		if($data_export['status'] < 75)  $this->set_status($export_code,'75');

		if( $socialcp_confirm == "system" ) {
			$actor = "자동";
		}else{
			// 입점사일 경우
			if( defined('__SELLERADMIN__') === true ) {
				$actor = $this->providerInfo['provider_name'];
			}else{
				$actor = $this->managerInfo['mname'];
			}
			if(!$actor) $actor = "주문자";
		}

		// 출고로그
		$this->ordermodel->set_log($data_export['order_seq'],'export',$actor,'배송완료',$actor.'가 배송완료를 하였습니다.','',$data_export['export_code']);

		// 상태별 수량 업데이트 및 주문 상태 변경
		$tot_reserve	= 0;
		$tot_point		= 0;
		$tot_reserve_ea	= 0;
		$data_export_item = $this->get_export_item($export_code);
		if($data_export_item[0]['goods_kind'] == 'coupon') $this->coupon_goods = true;//티켓상품

		$chg_reserve = array();
		## 출고 및 주문 배송완료 처리
		foreach($data_export_item as $k => $item){
			$providerList[$item['provider_seq']]	= 1;

			if($item['opt_type'] == 'opt'){
				$mode = 'option';
			}else{
				$mode = 'suboption';
			}

			$minus_ea = $item['ea'] * -1;
			$this->ordermodel->set_step_ea(75,$item['ea'],$item['option_seq'],$mode);
			$this->ordermodel->set_step_ea($data_export['status'],$minus_ea,$item['option_seq'],$mode);

			// 상품에 구매수량 업데이트
			$this->goodsmodel->get_purchase_ea($item['ea'],$item['goods_seq']);

			$this->ordermodel->set_option_step($item['option_seq'],$mode);

			//마일리지 지급예정 수량이 있을때 2015-04-09 pjm
			//if($data_export['reserve_save'] == 'none'){
			if($item['reserve_ea'] > 0){

				$tot_reserve_ea += $item['reserve_ea'];
				$reserve			= 0;
				$point				= 0;
				$tot_reserve_tmp	= 0;
				$tot_point_tmp		= 0;

				if($mode == 'option') $reserve = $this->ordermodel->get_option_reserve($item['option_seq']);
				if($mode == 'suboption') $reserve = $this->ordermodel->get_suboption_reserve($item['option_seq']);

				if($mode == 'option') $point = $this->ordermodel->get_option_reserve($item['option_seq'],'point');
				if($mode == 'suboption') $point = $this->ordermodel->get_suboption_reserve($item['option_seq'],'point');

				if( $statustype == 'cancel' ) {//취소(환불)시 환불금액만큼 제외
					$coupon_remain_reserve	= (int) ($cancelpercent * ($reserve * $item['reserve_ea']) / 100);
					$tot_reserve_tmp		= (int) ($reserve * $item['reserve_ea']) - $coupon_remain_reserve;
					$coupon_remain_point	= (int) ($cancelpercent * ($point * $item['reserve_ea']) / 100);
					$tot_point_tmp			= (int) ($point * $item['reserve_ea']) - $coupon_remain_point;
				}else{
					$tot_reserve_tmp		= $reserve * $item['reserve_ea'];
					$tot_point_tmp			= $point * $item['reserve_ea'];
				}

				$tot_reserve	+= $tot_reserve_tmp;
				$tot_point		+= $tot_point_tmp;

				#지급예정수량 = 0
				#지급완료수량 = 지급예정수량
				$tmp = array();
				$tmp['mode']					= $mode;
				$tmp['export_item_seq']			= $item['export_item_seq'];
				$tmp['reserve_ea']				= 0;
				if($tot_reserve_tmp || $tot_point_tmp){
					$tmp['reserve_buyconfirm_ea']	= $item['reserve_ea'];
				}else{
					$tmp['reserve_return_ea']	= $item['reserve_ea'];
				}
				$chg_reserve[] = $tmp;
			}
		}
		$this->ordermodel->set_order_step($data_export['order_seq']);
		if($data_export['status'] < 75) $this->accountmodel->set_account_round($data_export,$data_export_item);//정산관련

		if( $pointsave) {
			//if($data_export['reserve_save'] == 'none'){
			// 회원 마일리지 적립, 마일리지 지급예정 수량이 남아 있을때
			if($orders['member_seq'] && $tot_reserve_ea > 0 ){
				if($tot_reserve) {
					$params_reserve['gb']			= "plus";
					$params_reserve['emoney']		= $tot_reserve;
					$params_reserve['memo']			= "[".$export_code."] 배송완료";
					$params_reserve['memo_lang'] 	= $this->membermodel->make_json_for_getAlert("mp240",$export_code);    // [%s] 배송완료
					$params_reserve['ordno']		= $orders['order_seq'];
					$params_reserve['type']			= "order";
					$params_reserve['limit_date'] 	= get_emoney_limitdate('order');
					$this->membermodel->emoney_insert($params_reserve, $orders['member_seq']);
				}

				if($tot_point){
					$params_point['gb']				= "plus";
					$params_point['point']          = $tot_point;
					$params_point['memo']           = "[".$export_code."] 배송완료";
					$params_point['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp240",$export_code);    // [%s] 배송완료
					$params_point['ordno']          = $orders['order_seq'];
					$params_point['type']           = "order";
					$params_point['limit_date'] 	= get_point_limitdate('order');
					$this->membermodel->point_insert($params_point, $orders['member_seq']);
				}

				## 출고아이템에 마일리지 지급예정수량, 지급완료 수량 업데이트 2015-04-09 pjm
				$this->exec_export_reserve_ea($chg_reserve,'buyconfirm');

				if($tot_reserve || $tot_point){
					$query = "update fm_goods_export set reserve_save = 'save' where export_code = ?";
					$this->db->query($query,array($export_code));
				}
			}
		}//endif;
	}

	//출고테이블 item 옵션별 가져오기
	public function get_export_for_order_item($order_seq, $item_seq, $item_option_seq)
	{
		$this->load->helper('shipping');
		$arr_delivery = get_delivery_url();
		if( defined('__SELLERADMIN__') === true ){
			$query = "
			select exp.*,item.*,
			sum(item.ea) as ea,
			IFNULL(sum(opt.ea),0)+IFNULL(sum(sub.ea),0) as order_ea,
			IFNULL(sum(opt.reserve*opt.ea),0) as reserve,
			IFNULL(sum(sub.reserve*sub.ea),0) as sub_reserve,
			IFNULL(sum(opt.price*opt.ea),0) as price,
			(
				SELECT if(b.provider_gb='provider',b.provider_seq,1) FROM fm_goods a
				left join fm_provider b on a.provider_seq=b.provider_seq
				WHERE a.goods_seq=orditem.goods_seq
			) shipping_provider_seq
			from
			fm_goods_export_item item
			left join fm_goods_export exp on exp.export_code = item.export_code
			LEFT JOIN fm_order_item orditem ON orditem.item_seq = item.item_seq
			left join fm_order_item_option opt on opt.item_option_seq  = item.option_seq
			left join fm_order_item_suboption sub on sub.item_suboption_seq  = item.suboption_seq
			where
			orditem.provider_seq = {$this->providerInfo['provider_seq']}
			and item.item_seq = ?
			and  item.option_seq = ?
			";//group by exp.export_code
		}else{
			$query = "
			select exp.*,item.*,
			sum(item.ea) as ea,
			IFNULL(sum(opt.ea),0)+IFNULL(sum(sub.ea),0) as order_ea,
			IFNULL(sum(opt.reserve*opt.ea),0) as reserve,
			IFNULL(sum(sub.reserve*sub.ea),0) as sub_reserve,
			IFNULL(sum(opt.price*opt.ea),0) as price,
			(
				SELECT if(b.provider_gb='provider',b.provider_seq,1) FROM fm_goods a
				left join fm_provider b on a.provider_seq=b.provider_seq
				WHERE a.goods_seq=oitem.goods_seq
			) shipping_provider_seq
			from
			fm_goods_export_item item
			left join fm_goods_export exp on exp.export_code = item.export_code
			left join fm_order_item oitem ON oitem.item_seq = item.item_seq
			left join fm_order_item_option opt on opt.item_option_seq  = item.option_seq
			left join fm_order_item_suboption sub on sub.item_suboption_seq  = item.suboption_seq
			where
			item.item_seq = ?
			and item.option_seq = ?
			";//group by exp.export_code
		}
		$query = $this->db->query($query,array($order_seq, $item_seq, $item_option_seq));
		foreach($query -> result_array() as $data){
			if($data['international'] == 'domestic'){
				if($data['delivery_number']){
					$tmp = config_load('delivery_url',$data['delivery_company_code']);
					$data['mdelivery'] = $arr_delivery[$data['delivery_company_code']]['company'];
					$data['mdelivery_number'] = $data['delivery_number'];
					if($data['delivery_number']) $data['tracking_url'] = $arr_delivery[$data['delivery_company_code']]['url'].$data['delivery_number'];
				}
				$data['delivery_company_array'] = get_shipping_company('domestic','delivery',$data['shipping_provider_seq']);
			}else{
				$data['mdelivery'] = $data['international_shipping_method'];
				$data['mdelivery_number'] = $data['international_delivery_no'];
				if($data['international_delivery_no']) $data['tracking_url'] = $arr_delivery[$data['international_shipping_method']]['url'].$data['international_delivery_no'];
			}
			$data['mstatus'] = $this->arr_status[$data['status']];
			$result[] = $data;
		}
		return $result;
	}

	public function get_export_item_for_order($item_seq, $optSeq, $optType = 'option'){
		$addWhere	= " and option_seq = '".$optSeq."' ";
		if	($optType == 'sub')
			$addWhere	= " and suboption_seq = '".$optSeq."' ";

		$sql	= "select * from fm_goods_export_item where item_seq = '".$item_seq."' ".$addWhere;
		$query	= $this->db->query($sql);

		return $query->result_array();
	}

	// 출고목록 중 티켓상품 상품만 추출
	public function get_coupon_export($order_seq, $provider_seq = ''){

		if	($provider_seq)
			$addWhere	= " and item.provider_seq = '".$provider_seq."' ";

		$sql	= "select *
					from
						fm_goods_export	exp,
						fm_goods_export_item exp_item,
						fm_order_item item
					where
						exp.order_seq = '".$order_seq."' and
						exp.export_code = exp_item.export_code and
						exp_item.item_seq = item.item_seq and
						item.goods_kind = 'coupon' ".$addWhere;
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		return $result;
	}

	// 결제확인될 때 티켓상품 상품 출고처리
	public function coupon_payexport($order_seq, $provider_seq = '', $setemail = array(), $setsms = array(), $export_date = '' ) {

		$this->load->model('order2exportmodel');
		$this->load->model('accountallmodel');
		$this->load->model('goodsmodel');

		$export_cnt = 0;

		$cfg['stockable'] 			= $_POST['stockable'];
		$cfg['export_step'] 		= '55';
		$cfg['step'] 				= '55';
		$cfg['ticket_stockable'] 	= $_POST['ticket_stockable'];
		$cfg['ticket_step'] 		= '55';
		$cfg['export_date'] 		= $export_date;
		if(!$cfg['export_date']) $cfg['export_date'] = date('Y-m-d');

		// 입점사 정보 가져오기
		if( !$cfg['ticket_stockable'] || !$cfg['stockable'] ){
			$this->load->model('providermodel');
			$present_provider_seq = 1;
			if($provider_seq){
				$present_provider_seq = $provider_seq;
			}
			$data_present_provider = $this->providermodel->get_provider($present_provider_seq);
			if(!$cfg['stockable']){
				$cfg['stockable'] = $data_present_provider['default_export_stock_check'];
			}
			if(!$cfg['ticket_stockable']){
				$cfg['ticket_stockable'] = $data_present_provider['default_export_ticket_stock_check'];
			}
		}

		$arr_order_seq 			= array($order_seq);
		$params_order_export = array(
			'cfg'	=> $cfg,
			'arr_order_seq'	=> $arr_order_seq
		);
		$result_check = $this->order2exportmodel->order_export($params_order_export);
		foreach($result_check[1] as $data){
			$tmp_export_error[$data['step']][$data['shipping_seq']] = true;
			$tmp_export_request[$data['step']][$data['shipping_seq']] = true;
		}

		foreach($result_check[2] as $data){
			$tmp_export_success[$data['status']][$data['shipping_seq']] = true;
			$tmp_export_request[$data['status']][$data['shipping_seq']] = true;
		}

		// 에러로그저장
		$this->load->model('exportlogmodel');
		$export_type = 'web_order';
		foreach($result_check[1] as $data_error){
			$goods_kind = 'goods';
			if( preg_match('/COU/',$data_error['export_item_seq']) ) $goods_kind = 'coupon';

			if( $goods_kind == 'goods' ){
				$stockable = $cfg['stockable'];
				$step = $cfg['step'];
			}else{
				$stockable = $cfg['ticket_stockable'];
				$step = $cfg['ticket_step'];
			}
			$this->exportlogmodel->export_log($stockable,$step,$export_type,$goods_kind,$data_error);
		}
		$export_params = $result_check[2];

		// 출고처리
		if( $export_params ){
			$result_export = $this->order2exportmodel->goods_export($export_params,$cfg);
			foreach($result_export as $goods_kind=>$result_export1){
				foreach($result_export1 as $export_item_seq => $result_export2){
					$result_export3 = explode('<br/>',$result_export2);
					$export_cnt += count($result_export3);
				}
			}
		}
		
		// 출고예약량 업데이트
		foreach($arr_order_seq as $order_seq){
			$sql	=	"select item.goods_seq, opt.ea, opt.option1,opt.option2,opt.option3,opt.option4,opt.option5
						from fm_order_item item, fm_order_item_option opt
						where item.item_seq = opt.item_seq and opt.order_seq = '".$order_seq."' and opt.step >= '15' and opt.step < '65' ";
			$query			= $this->db->query($sql);
			$result			= $query->row_array();

			$this->goodsmodel->modify_reservation_real($result['goods_seq']);
		}

		// 3차 환불 개선으로 함수 처리 추가 :: 2018-11- lkh
		$this->accountallmodel->update_calculate_sales_coupon_remain($order_seq);

		return $export_cnt;
	}

	// 티켓상품 출고 처리 ( option당 처리 )
	public function coupon_export($param, $export_ea = 'ALL'){

		$this->load->model('goodsmodel');

		// 주문 정보 추출 ( 현재 티켓상품은 suboption이 없으나 나중을 위해 넣어둠. )
		if	($param['suboption_seq'] > 0){
			$suboption_seq	= $param['suboption_seq'];
			$sql	= "select item.goods_seq, item.socialcp_input_type, item.socialcp_use_return, item.socialcp_use_emoney_day, sub.*,
						from fm_order_item item, fm_order_item_suboption sub
						where item.item_seq = sub.item_seq and sub.item_suboption_seq = '".$suboption_seq."' and sub.step >= 25 and sub.step < 55 ";
		}else{
			$option_seq		= $param['option_seq'];
			$sql	= "select item.goods_seq, item.socialcp_input_type, item.socialcp_use_return, item.socialcp_use_emoney_day, opt.*
						from fm_order_item item, fm_order_item_option opt
						where item.item_seq = opt.item_seq and opt.item_option_seq = '".$option_seq."' and opt.step >= '25' and opt.step < '55' ";
		}
		$query			= $this->db->query($sql);
		$result			= $query->row_array();

		if	($result['order_seq']){
			// ea당 출고 처리
			$goods_seq		= $result['goods_seq'];
			$export_date	= $param['export_date'];
			if	(!$export_date)			$export_date	= date('Y-m-d');
			if	($export_ea == 'ALL')	$export_ea		= $result['ea'];
			$coupon_st	= $result['step55'];
			for ($o = 1; $o <= $export_ea; $o++){

				if	($goods_seq)
					$coupon_serial_code	= $this->goodsmodel->get_out_coupon_serial_code($goods_seq);

				if	($coupon_serial_code){
					$result_export_ea++;

					$coupon_st++;
					$insertExport['status']					= '55';
					$insertExport['order_seq']				= $result['order_seq'];
					$insertExport['buy_confirm']			= 'none';
					$insertExport['reserve_save']			= 'none';		// 배송완료에서 마일리지 지급
					$insertExport['international']			= 'domestic';	// NOT NULL 이라서....
					$insertExport['export_date']			= $export_date;
					$insertExport['regist_date']				= date('Y-m-d H:i:s');
					$insertExport['complete_date']		= date('Y-m-d H:i:s');

					//미사용티켓환불기간
					if( $result['socialcp_use_return']  == 1 ) {//유예기간 설정시
						$insertExport['socialcp_refund_day'] = date("Ymd",strtotime('+'.$result['socialcp_use_emoney_day'].' day '.substr(str_replace("-","",$result['social_end_date']),0,8)));
					}else{//유예기간없으면 종료유효기간과 동일
						$insertExport['socialcp_refund_day'] = date("Ymd",strtotime($result['social_end_date']));
					}

					$export_code = $this->insert_export($insertExport);
					unset($insertExport);

					// 외부티켓상품일 경우 출고처리 자동일 경우 티켓번호 추출
					if	($coupon_serial_code == 'a')
						$coupon_serial_code	= get_coupon_serialnumber($export_code);
					else
						$this->goodsmodel->use_out_coupon_serial_code($coupon_serial_code, $result['goods_seq'], $export_code);

					$insertExportItem['export_code']			= $export_code;
					$insertExportItem['item_seq']				= $result['item_seq'];
					$insertExportItem['coupon_serial']			= $coupon_serial_code;
					$insertExportItem['coupon_st']				= $coupon_st;
					$insertExportItem['coupon_value_type']		= $result['socialcp_input_type'];
					$insertExportItem['coupon_value']			= $result['coupon_input'];
					$insertExportItem['coupon_remain_value']	= $result['coupon_input'];
					$insertExportItem['recipient_email']		= $param['coupon_mail'];
					$insertExportItem['recipient_cellphone']	= $param['coupon_sms'];
					$insertExportItem['ea']						= 1;
					$insertExportItem['reserve_ea']				= 1;	//마일리지 지급예정수량 2015-04-08 pjm
					$insertExportItem['mail_status']			= 'n';
					$insertExportItem['sms_status']				= 'n';
					if	($suboption_seq > 0)	$insertExportItem['suboption_seq']	= $suboption_seq;
					else						$insertExportItem['option_seq']		= $option_seq;
					$this->db->insert('fm_goods_export_item', $insertExportItem);

					// 티켓번호 저장
					$this->save_coupon_serial($insertExportItem['coupon_serial'], $export_code);
					unset($insertExportItem);

					$this->coupon_export_send_for_option(array($export_code), 'all', $param['coupon_mail'], $param['coupon_sms']);
				}
			}

			$export_ea	= $result_export_ea;

			// 재고 차감
			if	($suboption_seq > 0){
				$this->goodsmodel->stock_suboption(
					'-',
					$export_ea,
					$result['goods_seq'],
					$result['title1'],
					$result['option1']
				);
			}else{
				$this->goodsmodel->stock_option(
					'-',
					$export_ea,
					$result['goods_seq'],
					$result['option1'],
					$result['option2'],
					$result['option3'],
					$result['option4'],
					$result['option5']
				);
			}

			// 출고예약량 업데이트
			$this->goodsmodel->modify_reservation_real($result['goods_seq']);

			return $export_ea;
		}

		return false;
	}

	// 티켓상품 출고 메일, SMS발송 (@param 출고코드, 받는이 이메일, 받는이 핸드폰 번호 )
	public function coupon_export_send($export_code, $sendType = 'all', $email = '', $sms = ''){

		$export	= $this->get_export($export_code);
		$items	= $this->get_export_item($export_code);
		$item	= $items[0];
		if	(!$email)	$email	= $item['recipient_email'];
		if	(!$sms)		$sms	= $item['recipient_cellphone'];

		// 출고완료 메일 및 SMS 발송
		$mail_result = $sms_result = false;
		$param['export_code']	= $export_code;
		$param['order_seq']		= $export['order_seq'];
		$param['regist_date']	= date('Y-m-d H:i:s');
		if	($email && in_array($sendType, array('all', 'mail'))){
			$mail_result	= coupon_send_mail_step55($export_code, $email);

			// mail send log
			$param['order_seq']		= $export['order_seq'];
			$param['export_code']	= $export_code;
			$param['send_kind']		= 'mail';
			$param['status']		= ($mail_result === false) ? 'n' : 'y';
			$param['send_val']		= $email;
			$param['regist_date']	= date('Y-m-d H:i:s');
			$this->db->insert('fm_goods_export_send_log', $param);
		}
		if	($sms && in_array($sendType, array('all', 'sms'))){

			## 주문자와 받는분이 다를 때. 주문자 핸드폰 번호 불러오기
			$sql				= "select order_cellphone from fm_order where order_seq='".$export['order_seq']."'";
			$query				= $this->db->query($sql);
			$res				= $query->row_array();
			$order_cellphone	= $res['order_cellphone'];

			$sms_result		= coupon_send_sms_step55($export_code, $sms, $order_cellphone);

			// sms send log
			$param['order_seq']		= $export['order_seq'];
			$param['export_code']	= $export_code;
			$param['send_kind']		= 'sms';
			$param['status']		= ($sms_result === false) ? 'n' : 'y';
			$param['send_val']		= $sms;
			$param['regist_date']	= date('Y-m-d H:i:s');
			$this->db->insert('fm_goods_export_send_log', $param);
		}

		// 발송결과 저장
		if	($item['mail_status'] == 'y'){
			if	($mail_result){
				$addUpdate['recipient_email']		= $email;
				$addUpdate['mail_status']			= 'y';
			}
		}elseif	(in_array($sendType, array('all', 'mail'))){
			$addUpdate['recipient_email']		= $email;
			$addUpdate['mail_status']			= ($mail_result === false) ? 'n' : 'y';
		}

		if	($item['sms_status'] == 'y'){
			if	($sms_result){
				$addUpdate['recipient_cellphone']	= $sms;
				$addUpdate['sms_status']			= 'y';
			}
		}elseif	(in_array($sendType, array('all', 'sms'))){
			$addUpdate['recipient_cellphone']	= $sms;
			$addUpdate['sms_status']			= ($sms_result === false) ? 'n' : 'y';
		}

		if	($item['export_item_seq'] > 0 && count($addUpdate) > 0){
			$this->db->where(array("export_item_seq"=>$item['export_item_seq']));
			$result = $this->db->update('fm_goods_export_item', $addUpdate);
		}

		return $addUpdate;
	}

	// 티켓상품 출고 처리 ( option당 처리 )
	public function coupon_export_for_option($param, $export_ea = 'ALL'){

		$this->load->model('goodsmodel');

		// 주문 정보 추출 ( 현재 티켓상품은 suboption이 없으나 나중을 위해 넣어둠. )
		if	($param['suboption_seq'] > 0){
			$suboption_seq	= $param['suboption_seq'];
			$sql	= "select item.provider_seq,item.goods_seq, item.socialcp_input_type, item.socialcp_use_return, item.socialcp_use_emoney_day, sub.*,
						from fm_order_item item, fm_order_item_suboption sub
						where item.item_seq = sub.item_seq and sub.item_suboption_seq = '".$suboption_seq."' and sub.step >= 25 and sub.step < 55 ";
		}else{
			$option_seq		= $param['option_seq'];
			$sql	= "select item.provider_seq,item.goods_seq, item.socialcp_input_type, item.socialcp_use_return, item.socialcp_use_emoney_day, opt.*
						from fm_order_item item, fm_order_item_option opt
						where item.item_seq = opt.item_seq and opt.item_option_seq = '".$option_seq."' and opt.step >= '25' and opt.step < '55' ";
		}
		$query			= $this->db->query($sql);
		$result			= $query->row_array();

		if	($result['order_seq']){
			// ea당 출고 처리
			$goods_seq		= $result['goods_seq'];
			$export_date	= $param['export_date'];
			if	(!$export_date)			$export_date	= date('Y-m-d');
			if	($export_ea == 'ALL')	$export_ea		= $result['ea'];
			$coupon_st	= $result['step55'];
			for ($o = 1; $o <= $export_ea; $o++){

				if	($goods_seq)
					$coupon_serial_code	= $this->goodsmodel->get_out_coupon_serial_code($goods_seq);

				if	($coupon_serial_code){
					$result_export_ea++;

					$coupon_st++;
					$insertExport['status']					= '55';
					$insertExport['order_seq']				= $result['order_seq'];
					$insertExport['buy_confirm']			= 'none';
					$insertExport['reserve_save']			= 'none';		// 배송완료에서 마일리지 지급
					$insertExport['international']			= 'domestic';	// NOT NULL 이라서....
					$insertExport['domestic_shipping_method']	= 'coupon';
					$insertExport['export_date']			= $export_date;
					$insertExport['regist_date']				= date('Y-m-d H:i:s');
					$insertExport['complete_date']		= date('Y-m-d H:i:s');
					$insertExport['shipping_provider_seq']	= $result['provider_seq'];

					//미사용티켓환불기간
					if( $result['socialcp_use_return']  == 1 ) {//유예기간 설정시
						$insertExport['socialcp_refund_day'] = date("Ymd",strtotime('+'.$result['socialcp_use_emoney_day'].' day '.substr(str_replace("-","",$result['social_end_date']),0,8)));
					}else{//유예기간없으면 종료유효기간과 동일
						$insertExport['socialcp_refund_day'] = date("Ymd",strtotime($result['social_end_date']));
					}

					$export			= $this->insert_export($insertExport);
					$export_code	= $export['export_code'];
					unset($insertExport);

					// 외부티켓상품일 경우 출고처리 자동일 경우 티켓번호 추출
					if	($coupon_serial_code == 'a')
						$coupon_serial_code	= get_coupon_serialnumber($export_code);
					else
						$this->goodsmodel->use_out_coupon_serial_code($coupon_serial_code, $result['goods_seq'], $export_code);

					$insertExportItem['export_code']			= $export_code;
					$insertExportItem['item_seq']				= $result['item_seq'];
					$insertExportItem['coupon_serial']			= $coupon_serial_code;
					$insertExportItem['coupon_st']				= $coupon_st;
					$insertExportItem['coupon_value_type']		= $result['socialcp_input_type'];
					$insertExportItem['coupon_value']			= $result['coupon_input'];
					$insertExportItem['coupon_remain_value']	= $result['coupon_input'];
					$insertExportItem['recipient_email']		= $param['coupon_mail'];
					$insertExportItem['recipient_cellphone']	= $param['coupon_sms'];
					$insertExportItem['ea']						= 1;
					$insertExportItem['reserve_ea']				= 1;	//마일리지 지급예정수량 2015-04-08 pjm
					$insertExportItem['mail_status']			= 'n';
					$insertExportItem['sms_status']				= 'n';
					if	($suboption_seq > 0)	$insertExportItem['suboption_seq']	= $suboption_seq;
					else						$insertExportItem['option_seq']		= $option_seq;
					$this->db->insert('fm_goods_export_item', $insertExportItem);

					// 티켓번호 저장
					$this->save_coupon_serial($insertExportItem['coupon_serial'], $export_code);

					//$this->coupon_export_send_for_option(array($export_code), 'all', $param['coupon_mail'], $param['coupon_sms']);
					$couponresult	= $this->coupon_export_send($export_code, 'all', $param['coupon_mail'], $param['coupon_sms']);
					unset($insertExportItem);

					$result_export_code[] = $export_code;



				}
			}

			$export_ea	= $result_export_ea;

			// 재고 차감
			if	($suboption_seq > 0){
				$this->goodsmodel->stock_suboption('-',$export_ea,$result['goods_seq'],$result['title1'],$result['option1']);
			}else{
				$this->goodsmodel->stock_option('-',$export_ea,$result['goods_seq'],$result['option1'],$result['option2'],$result['option3'],$result['option4'],$result['option5']);
			}

			// 출고예약량 업데이트
			$this->goodsmodel->modify_reservation_real($result['goods_seq']);

			if( $result_export_code ) $result_coupon = implode('<br/>',$result_export_code);
			return array($export_ea,$result_export_code,$result_coupon);
		}

		return false;
	}

	// 티켓상품 출고 메일, SMS발송 (@param 출고코드, 받는이 이메일, 받는이 핸드폰 번호 )
	public function coupon_export_send_for_option($result_export_code, $sendType = 'all', $email = '', $sms = ''){

		if(!is_array($result_export_code)) $result_export_code = array($result_export_code);

		foreach($result_export_code as $export_code){
			$export	= $this->get_export($export_code);
			$items	= $this->get_export_item($export_code);
			$item	= $items[0];
			$arr_item[]	= $items[0];
			if	(!$email)	$email	= $item['recipient_email'];
			if	(!$sms)		$sms	= $item['recipient_cellphone'];
		}

		// 출고완료 메일 및 SMS 발송
		$mail_result = $sms_result = false;
		$param['export_code']	= $result_export_code;
		$param['order_seq']		= $export['order_seq'];
		$param['regist_date']	= date('Y-m-d H:i:s');
		if	($email && in_array($sendType, array('all', 'mail'))){
			$mail_result	= coupon_send_mail_step55_for_option($result_export_code, $email);

			foreach($result_export_code as $export_code){
				// mail send log
				$param['order_seq']		= $export['order_seq'];
				$param['export_code']	= $export_code;
				$param['send_kind']		= 'mail';
				$param['status']		= ($mail_result === false) ? 'n' : 'y';
				$param['send_val']		= $email;
				$param['regist_date']	= date('Y-m-d H:i:s');
				$this->db->insert('fm_goods_export_send_log', $param);
			}
		}
		if	($sms && in_array($sendType, array('all', 'sms')))
		{
			## 주문자와 받는분이 다를 때. 주문자 핸드폰 번호 불러오기
			$sql				= "select order_cellphone from fm_order where order_seq='".$export['order_seq']."'";
			$query				= $this->db->query($sql);
			$res				= $query->row_array();
			$order_cellphone	= $res['order_cellphone'];

			$sms_result		= coupon_send_sms_step55_for_option($result_export_code, $sms, $order_cellphone);

			// sms send log
			foreach($result_export_code as $export_code){
				$param['order_seq']		= $export['order_seq'];
				$param['export_code']	= $export_code;
				$param['send_kind']		= 'sms';
				$param['status']		= ($sms_result === false) ? 'n' : 'y';
				$param['send_val']		= $sms;
				$param['regist_date']	= date('Y-m-d H:i:s');
				$this->db->insert('fm_goods_export_send_log', $param);
			}
		}

		// 발송결과 저장
		foreach($arr_item as $item){
			if	($item['mail_status'] == 'y'){
				if	($mail_result){
					$addUpdate['recipient_email']		= $email;
					$addUpdate['mail_status']			= 'y';
				}
			}elseif	(in_array($sendType, array('all', 'mail'))){
				$addUpdate['recipient_email']		= $email;
				$addUpdate['mail_status']			= ($mail_result === false) ? 'n' : 'y';
			}

			if	($item['sms_status'] == 'y'){
				if	($sms_result){
					$addUpdate['recipient_cellphone']	= $sms;
					$addUpdate['sms_status']			= 'y';
				}
			}elseif	(in_array($sendType, array('all', 'sms'))){
				$addUpdate['recipient_cellphone']	= $sms;
				$addUpdate['sms_status']			= ($sms_result === false) ? 'n' : 'y';
			}

			if	($item['export_item_seq'] > 0 && count($addUpdate) > 0){
				$this->db->where(array("export_item_seq"=>$item['export_item_seq']));
				$result = $this->db->update('fm_goods_export_item', $addUpdate);
			}
		}

		return $addUpdate;
	}


	// 티켓상품 티켓인증코드 출고 정보 추출
	public function get_coupon_info($param)
	{
		$this->db->select('fm_goods_export.order_seq, fm_goods_export.export_code, fm_goods_export.status, fm_goods_export_item.export_item_seq,
			fm_goods_export_item.coupon_serial, fm_goods_export_item.coupon_value_type, fm_goods_export_item.coupon_value, fm_goods_export_item.coupon_remain_value, fm_goods_export_item.item_seq,
			fm_goods_export_item.option_seq, fm_goods_export_item.suboption_seq, fm_order_item.goods_seq, fm_order_item.provider_seq,
			IF(fm_goods_export_item.option_seq > 0,(select address_commission from fm_order_item_option where item_option_seq = fm_goods_export_item.option_seq limit 1), 0) as address_commission,
			IF(fm_goods_export_item.option_seq > 0,(select social_start_date from fm_order_item_option where item_option_seq = fm_goods_export_item.option_seq limit 1), (select social_start_date from fm_order_item_suboption where item_suboption_seq = fm_goods_export_item.suboption_seq limit 1)) as coupon_start_date,
			IF(fm_goods_export_item.option_seq > 0,(select social_end_date from fm_order_item_option where item_option_seq = fm_goods_export_item.option_seq limit 1), (select social_end_date from fm_order_item_suboption where item_suboption_seq = fm_goods_export_item.suboption_seq limit 1)) as coupon_end_date');
		$this->db->from('fm_goods_export');
		$this->db->join('fm_goods_export_item', 'fm_goods_export.export_code = fm_goods_export_item.export_code');
		$this->db->join('fm_order_item', 'fm_goods_export_item.item_seq = fm_order_item.item_seq');
		$this->db->join('fm_order', 'fm_goods_export.order_seq = fm_order.order_seq');
		if ($param['order_seq']) {
			$this->db->where('fm_goods_export.order_seq', $param['order_seq']);
		}
		if ($param['export_code']) {
			$this->db->where('fm_goods_export.export_code', $param['export_code']);
		}
		if ($param['coupon_serial']) {
			$this->db->where('fm_goods_export_item.coupon_serial', $param['coupon_serial']);
		}
		if ($param['member_seq']) {
			$this->db->where('fm_order.member_seq', $param['member_seq']);
		}
		$query = $this->db->get();
		$result = $query->row_array();

		return $result;
	}

	// 티켓상품 사용내역 저장 및 배송완료 처리
	public function coupon_use_save($param){

		$this->load->helper('order');
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->model('membermodel');
		$this->load->model('socialcpconfirmmodel');
		$this->load->model('accountmodel');
		$this->load->model('accountallmodel');
		$this->load->model('providermodel');

		$export	= $this->get_coupon_info($param);
		if	(!$export){
			openDialogAlert('티켓사용 인증에 실패하였습니다.',400,140,'parent',$callback);
			exit;
		}

		// 가용횟수(금액) 체크
		$chg_coupon_remain_value	= $export['coupon_remain_value'] - $param['use_coupon_value'];
		if	($chg_coupon_remain_value < 0){
			$msg	= '티켓 사용횟수를 초과하였습니다.';
			if	($export['coupon_value_type'] == 'price')
				$msg	= '티켓 사용금액을 초과하였습니다.';
			openDialogAlert($msg,400,140,'parent',$callback);
			exit;
		}

		// 사용처
		if	($param['use_coupon_area'] == 'direct')
			$param['use_coupon_area']	= addslashes($param['use_coupon_area_direct']);
		else
			$param['use_coupon_area']	= addslashes($param['use_coupon_area']);

		if	( defined('__SELLERADMIN__') === true && $this->providerInfo['provider_seq'] ) {	//입점사
			$param['provider_seq']	= $this->providerInfo['provider_seq'];//$export['provider_seq'];
			$param['certify_code']	= $_POST['manager_code'];
			$certify				= $this->providermodel->get_certify_manager($param);
		}elseif	( defined('__ADMIN__') === true && $this->managerInfo['manager_id']){//본사 > 관리자
			$param['provider_seq']	= 1;//본사
			$param['manager_id']	= $this->managerInfo['manager_id'];
			$param['certify_code']	= $_POST['manager_code'];
			$certify				= $this->providermodel->get_certify_manager($param);
		}elseif	($_POST['manager_code']){//확인코드
			$param['provider_seq']	= $export['provider_seq'];
			$param['certify_code']		= $_POST['manager_code'];
			$certify				= $this->providermodel->get_certify_manager($param);
		}

		$manager_id					= $certify[0]['manager_id'];
		$manager_name				= $certify[0]['manager_name'];
		$certify_code				= $certify[0]['certify_code'];

		if	(!$certify_code || !$manager_id){
			openDialogAlert("유효하지 않은 확인코드입니다.",400,140,'parent',$callback);
			exit;
		}

		// 값어치 종류가 없는 경우
		if	(!$export['coupon_value_type']){
			$export['coupon_value_type']	= 'pass';
			if	($param['use_coupon_value'] >= 100)	$export['coupon_value_type']	= 'price';
		}

		//쿠푼상품의 필수옵션에서 선택된매장의 수수료가져오기
		$address_commission = $this->goodsmodel->get_option_address_commission($export['goods_seq'],$param['use_coupon_area']);

		// 사용내역 로그 저장
		$insertLogParam['order_seq']						= $export['order_seq'];
		$insertLogParam['export_code']					= $export['export_code'];
		$insertLogParam['coupon_serial']					= $export['coupon_serial'];
		$insertLogParam['coupon_value_type']			= $export['coupon_value_type'];
		$insertLogParam['coupon_use_value']			= $param['use_coupon_value'];
		$insertLogParam['coupon_use_area']			= $param['use_coupon_area'];
		$insertLogParam['coupon_use_memo']			= addslashes($param['use_coupon_memo']);
		$insertLogParam['manager_id']					= $manager_id;
		$insertLogParam['confirm_user']					= $manager_name;
		$insertLogParam['confirm_user_serial']		= $certify_code;
		$insertLogParam['address_commission']		= $address_commission;//지역(주소) 수수료
		$insertLogParam['regist_date']					= date('Y-m-d H:i:s');
		$this->db->insert('fm_goods_coupon_use_log', $insertLogParam);

		// 잔여 값어치 차감
		$updateExportItem['coupon_remain_value']	= $chg_coupon_remain_value;
		$this->db->where('export_item_seq',$export['export_item_seq']);
		$this->db->update('fm_goods_export_item',$updateExportItem);

		$export_code		= $export['export_code'];

		## 출고 및 주문 배송완료 처리

		$data_export	= $this->get_export($export_code);
		if	($export['status'] < 75){
			// 출고 배송완료 처리
			$this->set_status($export_code,'75');

			// 상태별 수량 업데이트 및 주문 상태 변경
			$data_export_item	= $this->get_export_item($export_code);
			foreach($data_export_item as $k => $item){
				if($item['opt_type'] == 'opt')	$mode = 'option';
				else							$mode = 'suboption';

				$minus_ea	= $item['ea'] * -1;
				$this->ordermodel->set_step_ea(75, $item['ea'], $item['option_seq'], $mode);
				$this->ordermodel->set_step_ea($data_export['status'], $minus_ea, $item['option_seq'], $mode);

				// 상품에 구매수량 업데이트
				$this->goodsmodel->get_purchase_ea($item['ea'], $item['goods_seq']);
				$this->ordermodel->set_option_step($item['option_seq'], $mode);
			}

			$this->ordermodel->set_order_step($data_export['order_seq']);
			$this->accountmodel->set_account_round($data_export,$data_export_item);
		}


		$data_socialcp_confirm['order_seq'] = $data_export['order_seq'];
		$data_socialcp_confirm['export_seq'] = $data_export['export_seq'];
		$data_socialcp_confirm['doer'] = '자동';
		$socialcp_status = ($chg_coupon_remain_value == 0)?'3':'2';//모두사용 3, 일부사용 2
		$this->socialcpconfirmmodel -> socialcp_confirm('system',$socialcp_status,$export_code);
		$this->socialcpconfirmmodel -> log_socialcp_confirm($data_socialcp_confirm);

		// 3차 환불 개선으로 티켓상품 사용 후 남은금액 함수 처리 추가 :: 2018-11- lkh
		$this->accountallmodel->update_calculate_sales_coupon_remain($data_export['order_seq']);

		// 잔여 값어치 모두 소진 시 마일리지 및 포인트 지급
		if	($chg_coupon_remain_value == 0){
			$tot_reserve		= 0;
			$tot_point			= 0;
			$chg_reserve		= array();
			$tot_reserve_ea		= 0;
			$data_export_item	= $this->get_export_item($export_code);
			foreach($data_export_item as $k => $item){
				if($item['opt_type'] == 'opt')	$mode = 'option';
				else							$mode = 'suboption';

				//if($data_export['reserve_save'] == 'none'){
				//마일리지 지급예정수량이 남아 있을때 pjm 2015-04-08
				if($item['reserve_ea'] > 0){

					$reserve		= 0;
					$tot_reserve_ea += $item['reserve_ea'];

					if($mode == 'option') $reserve = $this->ordermodel->get_option_reserve($item['option_seq']);
					if($mode == 'suboption') $reserve = $this->ordermodel->get_suboption_reserve($item['option_seq']);
					$tot_reserve += $reserve * $item['reserve_ea'];

					$point = 0;
					if($mode == 'option') $point = $this->ordermodel->get_option_reserve($item['option_seq'],'point');
					if($mode == 'suboption') $point = $this->ordermodel->get_suboption_reserve($item['option_seq'],'point');
					$tot_point += $point * $item['reserve_ea'];

					#지급예정수량 = 0
					#지급완료수량 = 지급예정수량
					$tmp = array();
					$tmp['mode']					= $mode;
					$tmp['export_item_seq']			= $item['export_item_seq'];
					$tmp['reserve_ea']				= 0;
					$tmp['reserve_buyconfirm_ea']	= $item['reserve_ea'];
					$chg_reserve[] = $tmp;

				}
			}

			//회원 마일리지 적립, 마일리지 지급예정수량이 남아 있을때 pjm 2015-04-08
			$data_order = $this->ordermodel->get_order($data_export['order_seq']);
			if($tot_reserve_ea > 0 && $data_order['member_seq']){
				if($tot_reserve){
					$params_reserve['gb']			= "plus";
					$params_reserve['emoney']		= $tot_reserve;
					$params_reserve['memo']			= "[".$export_code."] 배송완료";
					$params_reserve['memo_lang'] 	= $this->membermodel->make_json_for_getAlert("mp240",$export_code);    // [%s] 배송완료
					$params_reserve['ordno']		= $data_order['order_seq'];
					$params_reserve['type']			= "order";
					$params_reserve['limit_date'] 	= get_emoney_limitdate('order');
					$this->membermodel->emoney_insert($params_reserve, $data_order['member_seq']);
				}

				if($tot_point){
					$params_point['gb']				= "plus";
					$params_point['point']          = $tot_point;
					$params_point['memo']           = "[".$export_code."] 배송완료";
					$params_point['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp240",$export_code);    // [%s] 배송완료
					$params_point['ordno']          = $data_order['order_seq'];
					$params_point['type']           = "order";
					$params_point['limit_date'] 	= get_point_limitdate('order');
					$this->membermodel->point_insert($params_point, $data_order['member_seq']);
				}

				## 출고아이템에 마일리지 지급예정수량, 지급완료 수량 업데이트 2015-03-31 pjm
				$this->exec_export_reserve_ea($chg_reserve,'buyconfirm');

				if($tot_reserve || $tot_point){
					$query = "update fm_goods_export set reserve_save = 'save' where export_code = ?";
					$this->db->query($query,array($export_code));
				}
			}
		}

		// 티켓상품 배송완료 메일 및 SMS 발송 ( = 사용내역 발송 )
		coupon_send_mail_step75($export_code);
		coupon_send_sms_step75($export_code);

		// 잔여 값어치 모두 소진 시 정산 처리
		if	($chg_coupon_remain_value == 0){

			/**
			* 2-1 임시매출데이타를 이용한 미정산데이타 또는 통합정산테이블 시작
			* 정산개선 - 통합정산데이타 생성
			* @
			**/
			if(!$this->accountall)			$this->load->helper('accountall');
			if(!$this->accountallmodel)		$this->load->model('accountallmodel');
			if(!$this->providermodel)		$this->load->model('providermodel');
			//정산대상 수량업데이트
			$this->accountallmodel->update_calculate_sales_ac_ea($data_export['order_seq'],$export_code);
			//정산확정 처리
			$this->accountallmodel->insert_calculate_sales_buyconfirm($data_export['order_seq'], $export_code, $tot_reserve_ea, true);
			//debug_var($this->db->queries);
			//debug_var($this->db->query_times);
			/**
			* 2-1 임시매출데이타를 이용한 미정산데이타 또는 통합정산테이블 끝
			* 정산개선 - 통합정산데이타 생성
			* @
			**/
		}
	}

	// 티켓상품 사용 내역
	public function get_coupon_use_history($coupon_serial){
		$sql	= "select * from fm_goods_coupon_use_log where coupon_serial = '".$coupon_serial."' order by regist_date desc";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		return $result;
	}

	// 티켓상품 발송내역
	public function get_coupon_export_send_log($params, $limit = ''){
		if	($params['order_seq'])
			$addWhere	.= " and log.order_seq = '".$params['order_seq']."' ";
		if	($params['export_code'])
			$addWhere	.= " and log.export_code = '".$params['export_code']."' ";
		if	($params['send_kind'])
			$addWhere	.= " and log.send_kind = '".$params['send_kind']."' ";
		if	($params['status'])
			$addWhere	.= " and log.status = '".$params['status']."' ";
		if	($params['email'])
			$addWhere	.= " and (log.send_kind = 'mail' and log.send_val like '%".$params['email']."%') ";
		if	($params['sms'])
			$addWhere	.= " and (log.send_kind = 'sms' and log.send_val like '%".$params['sms']."%') ";

		// 입점사 검색
		if	($params['provider_seq'])
			$addWhere	.= " and item.provider_seq = '".$params['provider_seq']."' ";

		if	($limit > 0)
			$addLimit	= " LIMIT ".$limit." ";

		$sql	= " select log.* from
						fm_goods_export_send_log log,
						fm_goods_export_item exp,
						fm_order_item item
					where
						log.export_code = exp.export_code and
						exp.item_seq = item.item_seq
					".$addWhere
				." order by log.regist_date desc ".$addLimit;
		$query	= $this->db->query($sql);

		return $query->result_array();
	}

	// 티켓상품 사용가능여부 체크
	public function chk_coupon($param){
		$this->load->model("goodsmodel");
		$this->load->model("returnmodel");
		$coupon		= $this->get_coupon_info($param);
		if	($coupon['coupon_start_date'])
			$start_time	= strtotime($coupon['coupon_start_date'].' 00:00:00');
		if	($coupon['coupon_end_date'])
			$end_time	= strtotime($coupon['coupon_end_date'].' 23:59:59');

		// 해당 티켓상품 구매한 유저인지 체크
		if (!$coupon['order_seq']) {
			return array('result' => 'notOrderUser');
		}

		// 해당 티켓상품 정보가 있는지 체크
		if	(!$coupon['export_code'])
			return array('result' => 'fail');

		// 값어치가 남아 있는지 확인
		if	(!$coupon['coupon_remain_value'])
			return array('result' => 'noremain');

		// 유효기간 체크

		if	(!($start_time <= time() && time() <= $end_time)){
			if	(time() > $end_time)	return array('result' => 'expire');
			else						return array('result' => 'notyet');
		}

		// 반품 정보 추출
		if	($coupon['suboption_seq'])
			$returns	= $this->returnmodel->get_return_subitem_ea($coupon['item_seq'], $coupon['suboption_seq'], $coupon['export_code']);
		else
			$returns	= $this->returnmodel->get_return_item_ea($coupon['item_seq'], $coupon['option_seq'], $coupon['export_code']);

		// 환불된 티켓상품인지 확인
		if	($returns['ea'])
			return array('result' => 'refund');

		$result				= $coupon;
		// 원 상품에서 장소 정보 추출
		$address	= $this->goodsmodel->get_option_address($coupon['goods_seq']);
		$addressnew	= array();
		foreach($address as $key => $data){
			$addressnew[] = $data;
		}
		$result['address']	= $addressnew;
		$result['result']	= 'success';

		return $result;
	}

	// 티켓상품 인증번호 저장
	public function save_coupon_serial($coupon_serial, $export_code){
		$this->load->model('goodsmodel');
		if	($this->goodsmodel->chkDuple_coupon_serial($coupon_serial)){
			$update['export_code']		= $export_code;
			$update['export_date']		= date('Y-m-d H:i:s');
			$this->db->where('coupon_serial',$coupon_serial);
			$this->db->update('fm_goods_coupon_serial',$update);
		}else{
			$insert['coupon_serial']	= $coupon_serial;
			$insert['export_code']		= $export_code;
			$insert['regist_date']		= date('Y-m-d H:i:s');
			$insert['export_date']		= date('Y-m-d H:i:s');
			$this->db->insert('fm_goods_coupon_serial', $insert);
		}
	}

	public function get_change_status_list($params)
	{
		$this->load->library('validation');
		if($params){
			foreach($params as $key => $data){
				if(!is_array($data)){
					$params[$key] = $this->validation->xss_clean($data);
				}else{
					foreach($data as $key2 => $data2){
						$params[$key][$key2] = $this->validation->xss_clean($data2);
					}
				}
			}
		}


		$bind = array();
		if( !empty($params['status']) ){
			$where = "AND exp.status = ?";
			$bind[] = $params['status'];
		}

		// 배송책임 검색 :: 2016-10-11 lwh
		if($params['provider_seq']){
			$where .= " AND exp.shipping_provider_seq = ? ";
			$bind[] = $params['provider_seq'];
		}

		if( !empty($params['date_field']) ){

			switch($params['date_field']){
				case "export" :  // date
					$date_field = "exp.export_date";
					$date_type = "date";
				break;
				case "regist_date" : // datetime
					$date_field = "exp.regist_date";
					$date_type = "datetime";
				break;
				case "order" :  // datetime
					$date_field = "ord.regist_date";
					$date_type = "datetime";
				break;
				case "shipping" : //date
					$date_field = "exp.shipping_date";
					$date_type = "date";
				break;
				case "confirm_date" : //date
					$date_field = "exp.confirm_date";
					$date_type = "date";
				break;
			}

			if( !empty($params['start_search_date']) ){
				$where .= " AND ".$date_field.">=?";
				if($date_type=='datetime'){
					$bind[] = $params['start_search_date']." 00:00:00";
				}else{
					$bind[] = $params['start_search_date'];
				}
			}
			if( !empty($params['end_search_date']) ){
				$where .= " AND ".$date_field."<=?";
				if($date_type=='datetime'){
					$bind[] = $params['end_search_date']." 23:59:59";
				}else{
					$bind[] = $params['end_search_date'];
				}
			}
		}

		// 배송방법 검색
		if(	!empty($params['shipping_method'])){
			$where .=  "AND exp.shipping_method IN ('".implode("','",$_GET['shipping_method'])."')";
		}

		// 배송 택배사 검색 :: 2015-07-02 lwh
		if( $params['src_shipping_delivery'] ){
			$where .= " AND exp.delivery_company_code = ?";
			$bind[] = $params['src_shipping_delivery'];
		}

		if( $params['search_delivery_number'] && !$params['none_search_delivery_number'] ){
			$where .= " AND exp.delivery_number = ?";
			$bind[] = $params['search_delivery_number'];
		}

		if( $params['search_delivery_company'] && !$params['none_search_delivery_number'] ){
			$where .= " AND exp.delivery_company_code = ?";
			$bind[] = $params['search_delivery_company'];
		}

		if( $params['none_search_delivery_number'] ){
			$where .= " AND (exp.delivery_number = '' OR exp.delivery_number is null)";
		}

		if( $params['search_npay_order'] == 'y'){
			$where .= " AND ord.pg='npay'";
		}

		if( $params['search_market_fail'] == 'y'){
			$where .= " AND exp.market_fail='y'";
		}

		if($_GET['seq']){

			$tmp_export_code	= explode('|', $_GET['seq']);
			$export_code_list	= array();
			$bundle_code_list	= array();
			foreach((array)$tmp_export_code as $export_code){
				if(preg_match('/^B/', $export_code)){
					//묶음배송
					$bundle_code_list[]		= $export_code;
				}else{
					//일반배송
					$export_code_list[]		= $export_code;
				}
			}
			if( count($bundle_code_list) > 0 && count($export_code_list) > 0 ) {
				$where .= " AND ( ";
				$where .= " exp.bundle_export_code in ('".implode("','",$bundle_code_list)."')";
				$where .= " OR ";
				$where .= " exp.export_code in ('".implode("','",$export_code_list)."')";
				$where .= " ) ";
			}else{
				if(count($bundle_code_list) > 0){
					$where .= " AND exp.bundle_export_code in ('".implode("','",$bundle_code_list)."')";
				}
				if(count($export_code_list) > 0){
					$where .= " AND exp.export_code in ('".implode("','",$export_code_list)."')";
				}
			}
		}

		if($params['keyword']){
			switch($params['search_type']){
				case 'export_code' :

					if(preg_match('/^B/', $params['keyword']))	$where .= " AND exp.bundle_export_code = ?";
					else										$where .= " AND exp.export_code = ?";

					$bind[] = $params['keyword'];
					break;
				case 'order_seq' :
					$where .= " AND ord.order_seq = ?";
					$bind[] = $params['keyword'];
					break;
				case 'userid' :
					$where .= " AND ord.member_seq in (select member_seq from fm_member where userid like ?)";
					$bind[] = '%'.$params['keyword'].'%';
					break;
				case 'order_user_name' :
					$where .= " AND ord.order_user_name like ?";
					$bind[] = '%'.$params['keyword'].'%';
					break;
				case 'depositor' :
					$where .= " AND ord.depositor like ?";
					$bind[] = '%'.$params['keyword'].'%';
					break;
				case 'recipient_user_name' :
					$where .= " AND ord.recipient_user_name like ?";
					$bind[] = '%'.$params['keyword'].'%';
					break;
				case 'order_email' :
					$where .= " AND ord.order_email like ?";
					$bind[] = '%'.$params['keyword'].'%';
					break;
				case 'order_phone' :
					$where .= " AND ord.order_phone like ?";
					$bind[] = '%'.$params['keyword'].'%';
					break;
				case 'order_cellphone' :
					$where .= " AND ord.order_cellphone like ?";
					$bind[] = '%'.$params['keyword'].'%';
					break;
				case 'goods_name' :
					$where .=  " AND ord.order_seq in (select oit.order_seq from fm_order_item oit where ord.order_seq=oit.order_seq and oit.goods_name like ?)";
					$bind[] = '%'.$params['keyword'].'%';
					break;
				case 'goods_seq' :
					$where .=  " AND ord.order_seq in (select oit.order_seq from fm_order_item oit where ord.order_seq=oit.order_seq and oit.goods_seq = ?)";
					$bind[] = $params['keyword'];
					break;
				case 'goods_code' :
					$where .=  " AND ord.order_seq in (select oit.order_seq from fm_order_item oit where ord.order_seq=oit.order_seq and oit.goods_code = ?)";
					$bind[] = $params['keyword'];
					break;
				case 'npay_order_id' :
					$where .=  " AND ord.npay_order_id = ?";
					$bind[] = $params['keyword'];
					break;
				case 'npay_product_order_id' :
					$where .=  " AND ord.order_seq in (select opt.order_seq from fm_order_item_option opt where ord.order_seq=opt.order_seq and opt.npay_product_order_id = ?)";
					$bind[] = $params['keyword'];
					break;
			}
		}

		if ($params['isExportedList'] == true) {

			if ($params['hasMarketOrders'] == true) {

				if ($params['selectAllMarkets'] == 'Y') {
					$where		.= " AND ord.linkage_id = ?";
					$bind[]		= 'connector';
				} else if (count($params['selectMarkets']) == 1) {
					$where		.= " AND ord.linkage_id = ? AND ord.linkage_mall_code = ?";
					$bind[]		= 'connector';
					$bind[]		= $params['selectMarkets'][0];
				} else {
					$where		.= " AND ord.linkage_id = ?";
					$bind[]		= 'connector';

					$marketList	= array_unique($params['selectMarkets']);
					$inString	= str_replace(' ', ',', trim(str_repeat("? ", count($marketList))));
					$where		.= " AND ord.linkage_mall_code IN ({$inString})";
					$bind		= array_merge($bind, $marketList);
				}

				$where			.= " AND ord.step = ?";
				// 오픈 마켓 주문도 출고 준비 상태로 변경 가능하도록 프로세스를 수정,
				// 이에 따라 출고 상태에 따라 조회하도록 수정
				$bind[]			= $params['status'];

			} else {
				//$where		.= " AND (ord.linkage_id is null OR ord.linkage_id = ?)";
				//$bind[]		= '';
			}

		}


		$where = substr($where,4);
		$query = "
		select
			exp.export_code,
			exp.bundle_export_code,
			if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) AS group_export_code,
			if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export,
			group_concat(exp.order_seq)							AS has_order_list
		from
			fm_goods_export exp
			left join fm_order as ord on ord.order_seq = exp.order_seq
		where
			{$where}
		group by group_export_code ORDER BY exp.status asc,exp.export_seq DESC";

		return array($query,$bind);
	}

	public function get_change_status_detail($export_code)
	{

		$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';
		$query = "
		select
		ord.order_seq,
		ord.member_seq,
		ord.order_user_name,
		ord.recipient_user_name,
		ord.recipient_phone,
		ord.recipient_cellphone,
		ord.recipient_zipcode,
		ord.recipient_address,
		ord.recipient_address_detail,
		ord.memo,
		ord.recipient_address_type,
		ord.recipient_address_street,
		ord.recipient_email,
		ord.linkage_id,
		ord.linkage_mall_order_id,
		ord.linkage_mall_code,
		ord.npay_order_id,
		(select mall_name from fm_linkage_mall  where mall_code=ord.linkage_mall_code limit 1) mall_name,
		exp.export_code,
		exp.bundle_export_code,
		exp.shipping_provider_seq,
		exp.status,
		exp.socialcp_status,
		exp.domestic_shipping_method,
		exp.delivery_company_code,
		exp.delivery_number,
		exp.shipping_group,
		exp.shipping_method,
		exp.shipping_set_name,
		exp.store_scm_type,
		exp.shipping_address_seq,
		item.export_item_seq,
		item.option_seq,
		item.suboption_seq,
		oitem.provider_seq,
		item.ea,
		(select provider_name from fm_provider where provider_seq=exp.shipping_provider_seq) provider_name,
		oitem.goods_name,
		oitem.image	,
		oitem.goods_kind,
		oitem.goods_type,
		oitem.goods_seq,
		item.scm_supply_price,
		goods.cancel_type,
		(case when opt.option1 != '' then opt.npay_product_order_id else subopt.npay_product_order_id end) as npay_product_order_id,
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
		opt.goods_code opt_goods_code,
		(opt.ea) as opt_ea,
		(opt.step35) as opt_step35,
		(opt.step45) as opt_step45,
		(opt.step55) as opt_step55,
		(opt.step65) as opt_step65,
		(opt.step75) as opt_step75,
		(opt.step85) as opt_step85,
		opt.package_yn,
		group_concat( concat(oii.type,':',oii.title,':',oii.value) ) as inputs,
		subopt.title subtitle,
		subopt.suboption,
		(subopt.ea) as subopt_ea,
		(subopt.step35) as subopt_step35,
		(subopt.step45) as subopt_step45,
		(subopt.step55) as subopt_step55,
		(subopt.step65) as subopt_step65,
		(subopt.step75) as subopt_step75,
		(subopt.step85) as subopt_step85,
		subopt.goods_code subopt_goods_code,
		subopt.package_yn subopt_package_yn
		from
		fm_goods_export_item item
		left join fm_order_item_option opt on opt.item_option_seq=item.option_seq
		left join fm_order_item_input oii on oii.item_option_seq=item.option_seq
		left join fm_order_item_suboption subopt on subopt.item_suboption_seq=item.suboption_seq,
		fm_goods_export exp,
		fm_order_item as oitem
		left join fm_goods goods on goods.goods_seq=oitem.goods_seq,
		fm_order as ord
		where
		exp.{$export_field}=item.{$export_field}
		and item.item_seq = oitem.item_seq
		and ord.order_seq=exp.order_seq
		and item.{$export_field}=?
		group by item.export_item_seq
		order by item.export_item_seq";

		$query = $this->db->query($query,array($export_code));
		return $query;
	}

	public function get_export_for_orders($arr_order_seq)
	{
		$query = "select * from fm_goods_export where order_seq in ('".implode("','",$arr_order_seq)."')";
		$query = $this->db->query($query);
		return $query;
	}
	public function get_exports($arr_export_code)
	{

		$bundle_code_list	= array();
		$export_code_list	= array();

		foreach((array)$arr_export_code as $export_code){
			if(preg_match('/^B/', $export_code)){
				//묶음배송
				$bundle_code_list[]		= $export_code;
			}else{
				//일반배송
				$export_code_list[]		= $export_code;
			}
		}


		$where_arr	= array();

		if (count($bundle_code_list) > 0) {
			$where_arr[] = "AND bundle_export_code in ('" . implode("','", $bundle_code_list) . "')";
		}
		if (count($export_code_list) > 0) {
			// bundle_export_code 조건이 있으면 "or" 변경한다.
			$condition = (count($where_arr) === 0) ?  'AND' : 'OR';
			$where_arr[] = $condition . " export_code in ('" . implode("','", $export_code_list) . "')";
		}

		if(count($bundle_code_list) > 0 || count($export_code_list) > 0){
			$query = "
				select *,
					if(bundle_export_code REGEXP '^B', bundle_export_code, export_code) AS group_export_code,
					if(bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export
				from fm_goods_export where 1=1 ".implode(" ", $where_arr)." GROUP BY group_export_code";
			$query = $this->db->query($query);
		}

		return $query;
	}

	# 출고아이템별 출고수량
	public function get_export_item_ea($export_code,$option_seq='',$suboption_seq=''){

		$export_code_fld	= 'export_code';
		if(preg_match('/^B/', $export_code))	$export_code_fld	= 'bundle_export_code';

		$where[] = $export_code;
		$query = "select * from fm_goods_export_item where " . $export_code_fld . "=? ";
		if($suboption_seq){
			$query .= " and suboption_seq=? ";
			$where[] = $suboption_seq;
		}else{
			$query .= " and option_seq=? and (suboption_seq='' or suboption_seq is null)";
			$where[] = $option_seq;
		}
		$query = $this->db->query($query,$where);
		if($option_seq){
			$loop = $query->row_array();
		}else{
			foreach($query->result_array() as $data){
				$loop[] = $data;
			}
		}

		return $loop;

	}
	
	# 네이버페이 주문 취소로 인한 출고준비건 삭제
	public function delete_export_ready_by_partner($order_seq, $product_ids, $partner = "talkbuy"){

		if(is_array($product_ids) === false){
			$product_ids = array($product_ids);
		}

		$where_in_field = null;
		$platform = null;
		
		if ($partner === "talkbuy") {
			$platform = "KakaoPay 구매";
			$where_in_field = "talkbuy_product_order_id";
		} else {
			$platform = "Npay";
			$where_in_field = "npay_product_order_id";
		}

		// 수량 변경 유무
		$isModified = false;

		$sql = $this->db->select("ei.export_code, e.status, ei.ea, i.goods_seq, e.delivery_company_code, e.delivery_number")
		->from("fm_goods_export_item ei")
		->join("fm_goods_export e", "ei.export_code=e.export_code", "left outer")
		->join("fm_order_item i", "i.item_seq=ei.item_seq", "left outer")
		->where("e.order_seq", $order_seq)
		->where_in("ei.".$where_in_field, $product_ids)
		->where("e.status", '45')
		->get();
		$export = $sql->row_array();

		// 삭제 하기 전에 상품 준비로 되돌릴 데이터를 체킹함.
		$updateItem = array();
		foreach(array('option', 'suboption') as $prefix) {
			$itemSql = $this->db->select("opt.step45, opt.item_{$prefix}_seq as option_seq, '{$prefix}' as option_mode", false)
			->from("fm_goods_export_item exp")
			->join("fm_goods_export e", "exp.export_code = e.export_code")
			->join("fm_order_item_{$prefix} opt", "exp.{$prefix}_seq = opt.item_{$prefix}_seq")
			->where("e.status", '45')
			->where("e.order_seq", $order_seq)
			->where("e.export_code", $export['export_code'])
			->where_not_in("exp.".$where_in_field, $product_ids)
			->get();
			$itemRes = $itemSql->result_array();

			if(count($itemRes)>0) {
				foreach($itemRes as $itemRow) {
					// 출고준비를 상품준비로 되돌리기함
					$ea = (int) $itemRow['step45'];
					if( $ea > 0 ) {
						$updateItem[] = $itemRow;
						$isModified = true;
					}
				}
			}
		}

		if( $export ) {
			// 출고삭제
			$this->delete_export($export['export_code']);

			if($export['delivery_company_code'] && $export['delivery_number']){
				$delivery_info = "(".$export['delivery_company_code'] .":".$export['delivery_number'].")";
			}else{
				$delivery_info = "";
			}
			// 로그
			$log_title		= "출고준비삭제(".$export['export_code'].")";
			$log_message	= "[".implode(',',$product_ids)."]".$platform." 주문취소로 인한 출고준비(".$export['export_code'].") 삭제 되었습니다.".$delivery_info;
			$this->ordermodel->set_log($order_seq,'process',$platform,$log_title,$log_message,'','',$platform);
		}

		// 출고데이터가 없으면 그냥 return
		if(!$export['export_code']) return;


		if($isModified === true) {
			// 실제 출고준비 수량만큼 상품준비로 되돌린다.
			foreach($updateItem as $itemRow) {
				$ea = (int) $itemRow['step45'];
				$plus = $ea;
				$minus = -1*$ea;
				$this->ordermodel->set_step_ea('45',$minus,$itemRow['option_seq'], $itemRow['option_mode']);
				$this->ordermodel->set_step_ea('35',$plus,$itemRow['option_seq'], $itemRow['option_mode']);
				$this->ordermodel->set_option_step($itemRow['option_seq'], $itemRow['option_mode']);
			}
			// 상태 변경
			$this->ordermodel->set_order_step($order_seq);
			$arr_step 	= config_load('step');
			// 로그
			$this->ordermodel->set_log($order_seq, 'process', $platform, '되돌리기 ('.$arr_step['45'].' => '.$arr_step['35'].')', '-', '', '', $platform);
		}
	}

	# 네이버페이 주문 취소로 인한 출고준비건 삭제
	public function delete_export_ready($order_seq,$npay_product_order_id){

	    if(is_array($npay_product_order_id) === false){
	        $npay_product_order_id = array($npay_product_order_id);
		}

		// 수량 변경 유무
		$isModified = false;
		
		$this->db->reset_query();
		
		$sql = $this->db->select("ei.export_code, e.status, ei.ea, i.goods_seq, e.delivery_company_code, e.delivery_number")
	    ->from("fm_goods_export_item ei")
	    ->join("fm_goods_export e", "ei.export_code=e.export_code", "left outer")
	    ->join("fm_order_item i", "i.item_seq=ei.item_seq", "left outer")
	    ->where("e.order_seq", $order_seq)
	    ->where_in("ei.npay_product_order_id", $npay_product_order_id)
	    ->where("e.status", '45')
	    ->get();
	    $export = $sql->row_array();

		// 삭제 하기 전에 상품 준비로 되돌릴 데이터를 체킹함.
		$updateItem = array();
		foreach(array('option', 'suboption') as $prefix) {
			$itemSql = $this->db->select("opt.step45, opt.item_{$prefix}_seq as option_seq, '{$prefix}' as option_mode", false)
			->from("fm_goods_export_item exp")
			->join("fm_goods_export e", "exp.export_code = e.export_code")
			->join("fm_order_item_{$prefix} opt", "exp.{$prefix}_seq = opt.item_{$prefix}_seq")
			->where("e.status", '45')
			->where("e.order_seq", $order_seq)
			->where("e.export_code", $export['export_code'])
			->where_not_in("exp.npay_product_order_id", $npay_product_order_id)
			->get();
			$itemRes = $itemSql->result_array();
			if(count($itemRes)>0) {
				foreach($itemRes as $itemRow) {
					// 출고준비를 상품준비로 되돌리기함
					$ea = (int) $itemRow['step45'];
					if( $ea > 0 ) {
						$updateItem[] = $itemRow;
						$isModified = true;
					}
				}
			}
		}

	    if( $export ) {
	        // 출고삭제
	        $this->delete_export($export['export_code']);

	        if($export['delivery_company_code'] && $export['delivery_number']){
	            $delivery_info = "(".$export['delivery_company_code'] .":".$export['delivery_number'].")";
	        }else{
	            $delivery_info = "";
	        }
	        // 로그
	        $log_title		= "출고준비삭제(".$export['export_code'].")";
	        $log_message	= "[".implode(',',$npay_product_order_id)."]NPay 주문취소로 인한 출고준비(".$export['export_code'].") 삭제 되었습니다.".$delivery_info;
	        $this->ordermodel->set_log($order_seq,'process','Npay',$log_title,$log_message,'','','npay');
		}

		// 출고데이터가 없으면 그냥 return
		if(!$export['export_code']) return;


		if($isModified === true) {
			// 실제 출고준비 수량만큼 상품준비로 되돌린다.
			foreach($updateItem as $itemRow) {
				$ea = (int) $itemRow['step45'];
				$plus = $ea;
				$minus = -1*$ea;
				$this->ordermodel->set_step_ea('45',$minus,$itemRow['option_seq'], $itemRow['option_mode']);
				$this->ordermodel->set_step_ea('35',$plus,$itemRow['option_seq'], $itemRow['option_mode']);
				$this->ordermodel->set_option_step($itemRow['option_seq'], $itemRow['option_mode']);
			}
			// 상태 변경
	        $this->ordermodel->set_order_step($order_seq);
	        $arr_step 	= config_load('step');
	        // 로그
	        $this->ordermodel->set_log($order_seq,'process','Npay','되돌리기 ('.$arr_step['45'].' => '.$arr_step['35'].')','-','','','npay');
		}
	}

	public function get_export_catalog_query( $_PARAM = array('list') ){

		$page				= (trim($_PARAM['page'])) ? trim($_PARAM['page']) : 1;
		$nperpage			= 20;
		$limit_s			= ($page - 1) * $nperpage;
		$limit_e			= $nperpage;
		$addLimit			= " LIMIT {$limit_s}, {$limit_e} ";

		$record				= "";

		// 검색시간 검색 후 들어온 데이터를 무시 :: 2015-08-05 lwh
		if( $_PARAM['searchTime'] ){
			$where[] = "ord.regist_date <= '" . $_PARAM['searchTime'] . "'";
		}

		if($_PARAM['header_search_keyword']) $_PARAM['keyword'] = $_PARAM['header_search_keyword'];

		// 검색어
		if( $_PARAM['keyword'] ){

			$keyword_type	= str_replace("'","\'",trim($_PARAM['search_type']));
			$keyword		= str_replace("'","\'",trim($_PARAM['keyword']));

			if($keyword_type && $_PARAM['search_type'] != 'all'){
				$where[] = "{$keyword_type} = '" . $keyword . "'";
			}else{
				$search_arr_field_new = $_PARAM['search_arr_field'];
				unset($search_arr_field_new['ord.order_cellphone'],$search_arr_field_new['ord.recipient_phone'],$search_arr_field_new['ord.recipient_cellphone']);
				$wherekeyord = " INSTR(replace( ord.order_cellphone,'-',''), '" . str_replace("-","",$keyword) . "') OR ";
				$wherekeyord .= " INSTR(replace( ord.recipient_phone,'-',''), '" . str_replace("-","",$keyword) . "') OR ";
				$wherekeyord .= " INSTR(replace( ord.recipient_cellphone,'-',''), '" . str_replace("-","",$keyword) . "') OR ";
				$field_list = array_keys($search_arr_field_new);
				$wherekeyord .= " cast( " . implode(" AS char ) like '%" . $keyword . "%' OR cast( ", $field_list) . " AS char ) like '%" . $keyword . "%'";
				$where[] = "(" . $wherekeyord . ")";
			}

		}
		// 주문일
		if( $_PARAM['date']=='export' ){
			$date_field = "exp.export_date";
		}else if( $_PARAM['date']=='complete' ){
			$date_field = "exp.complete_date";
		}else if( $_PARAM['date']=='shipping' ){
			$date_field = "exp.shipping_date";
		}else if( $_PARAM['date']=='regist_date' ){
			$date_field = "exp.regist_date";
		}else if( $_PARAM['date']=='confirm_date' ){
			$date_field = "exp.confirm_date";
		}else{
			$date_field = "ord.regist_date";
		}
		if($_PARAM['regist_date'][0] && $date_field == 'exp.shipping_date'){
			$where[] = $date_field." >= '".$_PARAM['regist_date'][0]."'";
		}
		if($_PARAM['regist_date'][1] && $date_field == 'exp.shipping_date'){
			$where[] = $date_field." <= '".$_PARAM['regist_date'][1]."'";
		}
		if($_PARAM['regist_date'][0] && $date_field != 'exp.shipping_date'){
			$where[] = $date_field." >= '".$_PARAM['regist_date'][0]." 00:00:00'";
		}
		if($_PARAM['regist_date'][1] && $date_field != 'exp.shipping_date'){
			$where[] = $date_field." <= '".$_PARAM['regist_date'][1]." 23:59:59'";
		}

		// 출고준비중에 결제취소된 건 제외
		$where[] = "not(exp.status='45' and ord.step='85')";

		// 주문상태
		if( $_PARAM['export_status'] ){
			unset($arr);
			foreach($_PARAM['export_status'] as $key => $data){
				$arr[] = "exp.status = '".$key."'";
			}
			$where[] = "(".implode(' OR ',$arr).")";
		}

		// 출고방법
		if($_PARAM['search_shipping_nation']){
			foreach($_PARAM['search_shipping_nation'] as $k => $v){
				if($v == 'kr')	$nation = 'domestic';
				else			$nation = 'international';
				if($_PARAM['search_shipping_method_'.$v]){
					$r_export_method[] = "exp.international = '" . $nation . "' AND exp.shipping_method in ('".implode("','",$_PARAM['search_shipping_method_'.$v])."')";
				}
			}
		}
		// 티켓출고 검색
		if($_PARAM['search_shipping_method_coupon']){
			$r_export_method[] = "exp.domestic_shipping_method = 'coupon' OR exp.shipping_method = 'coupon'";
		}

		if($r_export_method){
			$where[] = "(".implode(' OR ',$r_export_method).")";
		}

		// 출고 정보
		if($_PARAM['search_delivery_company_code'] && preg_match('/code|auto/',$_PARAM['search_delivery_company_code'])){ // 택배사
		    
			$edn_cond = "delivery_company_code = '".$_PARAM['search_delivery_company_code']."'";

			// 업무자동화 택배사일 경우 택배사 코드 치환
		    if(str_replace('code','',$_PARAM['search_delivery_company_code']) >= 90) {
		        $delivery_codes = get_delivery_codes($_PARAM['search_delivery_company_code']);
		        if(count($delivery_codes)>0) {
		            array_walk($delivery_codes, function(&$item) { $item = "'{$item}'"; });
		            $edn_cond = "delivery_company_code IN (" . implode($delivery_codes, ',') . ")";
		        }
		    }
		    $where_edn[] = $edn_cond;
			
			if($_PARAM['search_delivery_number']){
				$where_edn[] = "delivery_number like '".$_PARAM['search_delivery_number']."%'";
			}
		}else if($_PARAM['search_delivery_company_code']){ //해외택배사
			$where_edn[] = "international_shipping_method = '".$_PARAM['search_delivery_company_code']."'";
			if($_PARAM['search_delivery_number']){
				$where_edn[] = "international_delivery_no like '".$_PARAM['search_delivery_number']."%'";
			}
		}else if($_PARAM['search_delivery_number']){
			$where_edn[] = "(delivery_number like '".$_PARAM['search_delivery_number']."%' OR international_delivery_no like '".$_PARAM['search_delivery_number']."%')";
		}
		if($where_edn){
			$where_ei[] = "(".implode(' AND ',$where_edn).")";
		}
		if($_PARAM['null_delivery_number']){
		 	$where_ei[] = "((exp.international='domestic' and exp.shipping_method in ('delivery','postpaid') and (exp.delivery_number is null OR exp.delivery_number=''))
		 	OR (exp.international='international' and (exp.international_delivery_no is null OR exp.international_delivery_no='')))";
		}
		if($where_ei){
			$where[] = "(".implode(' OR ',$where_ei).")";
		}

		$international_company_array = get_international_company();

		// 셀러어드민 출고 목록 쿼리
		if	($_PARAM['query_type'] == 'selleradmin_catalog' ||
			$_PARAM['query_type'] == 'selleradmin_total_record' ||
			$_PARAM['query_type'] == 'selleradmin_summary'){

			// 구매 확정완료(적립계정수량=0), 구매확정대기(적립예정수량>0)
			if($_PARAM['buy_confirm']){
				foreach( $_PARAM['buy_confirm'] as $key => $val){
					$in_where_buy_confirm[] = "'".$key."'";
				}
				if($in_where_buy_confirm){
					$where[] = "exp.buy_confirm in (".implode(',',$in_where_buy_confirm).")";
				}
			}

			$delivery_company_array			= get_shipping_company_provider($this->providerInfo['provider_seq']);

			### 매입상품만
			if( !empty($_PARAM['provider_base']) ){
				$where[] = " '1' in (select provider_seq from fm_order_item where item.item_seq=item_seq)";
			}

			### 입점사
			$where[] = " '{$this->providerInfo['provider_seq']}' in (select provider_seq from fm_order_item where item.item_seq=item_seq ) ";

		}else{	// 어드민 출고 목록 쿼리

			// 구매 확정완료(적립계정수량=0), 구매확정대기(적립예정수량>0)
			if($_PARAM['buy_confirm']){
				if($_PARAM['buy_confirm']['ok']){
				    $in_where_buy_confirm[] = "item.reserve_ea=0 AND exp.buy_confirm <> 'none' ";
				}
				if($_PARAM['buy_confirm']['standby']){
					$in_where_buy_confirm[] = "item.reserve_ea>0";
				}

				if($in_where_buy_confirm){
					$where[] = "(".implode(' or ',$in_where_buy_confirm).")";
				}
			}

			$delivery_company_array			= config_load('delivery_url');

			### 매입상품만
			if( !empty($_PARAM['base_inclusion']) ){
				$where_provider[] = "exists(select item_seq from fm_order_item where item.item_seq=item_seq and provider_seq='1')";
			}

			### 입점사
			if( !empty($_PARAM['provider_seq']) ){
				if( $_PARAM['provider_seq'] == 999999999999 ){
					$where_provider[] = "exists(select item_seq from fm_order_item where item.item_seq=item_seq and provider_seq!='1') ";
				}else{
					$where_provider[] = "exists(select item_seq from fm_order_item where item.item_seq=item_seq and provider_seq='".$_PARAM['provider_seq']."') ";
				}
			}
			if($where_provider){
				$where[] = "(".implode(" OR ",$where_provider).")";
			}
			### 2014-05-29
			if($_PARAM['linkage_mall_code'] || $_PARAM['not_linkage_order'] || $_PARAM['etc_linkage_order']){
				$arr = array();

				if($_PARAM['not_linkage_order']){
					$arr[] = "(ord.linkage_mall_code is null or ord.linkage_mall_code = '')";
				}
				if($_PARAM['linkage_mall_code']){
					$arr[] = "ord.linkage_mall_code in ('".implode("','",$_PARAM['linkage_mall_code'])."')";
				}
				if($_PARAM['etc_linkage_order']){
					if(!$linkage_malldata){
						$this->load->model('openmarketmodel');
						$linkage_malldata		= $this->openmarketmodel->get_linkage_mall();
						$linkage_malldata		= $this->openmarketmodel->sort_linkage_mall($linkage_malldata);
					}
					$search_mall_code = array();
					foreach($linkage_malldata as $k => $data){
						if	($data['default_yn'] == 'Y'){
							$search_mall_code[] = $data['mall_code'];
						}
					}
					for($i=0;$i<count($search_mall_code);$i++){
						if($i<10) unset($search_mall_code[$i]);
					}
					$arr[] = "ord.linkage_mall_code in ('".implode("','",$search_mall_code)."')";
				}

				$where[] = "(".implode(' OR ',$arr).")";
			}

		}

		if($_PARAM['chk_bundle_yn']){
			$where[] = "exp.bundle_export_code LIKE 'B%'";
		}

		if	($_PARAM['query_type'] == 'summary' || $_PARAM['query_type'] == 'selleradmin_summary'){
			$where[] = " exp.status = '{$_PARAM['end_step']}'";
		}

		$shopkey = get_shop_key();

		$addFroms = "
			fm_goods_export exp
				LEFT JOIN fm_order ord ON ord.order_seq=exp.order_seq
				LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
				LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
			,fm_goods_export_item item
				LEFT JOIN fm_order_item oitem ON oitem.item_seq = item.item_seq
				LEFT JOIN fm_order_item_option opt ON opt.item_option_seq = item.option_seq and item.option_seq
				LEFT JOIN fm_order_item_suboption sub ON sub.item_suboption_seq = item.suboption_seq and item.suboption_seq
			WHERE exp.export_code=item.export_code and exp.status!='' " . ($where ? " AND " . implode(' AND ',$where) : '') . "
			";
		// 셀러어드민 출고 목록 쿼리
		if	($_PARAM['query_type'] == 'selleradmin_catalog' ||
			$_PARAM['query_type'] == 'selleradmin_total_record' ||
			$_PARAM['query_type'] == 'selleradmin_summary'){
			$addFroms .= " AND oitem.provider_seq = '{$this->providerInfo['provider_seq']}' ";
			// 본사 출고 주문 목록에 출력 여부 확인 필요
			$addFroms .= " AND exp.shipping_provider_seq = '{$this->providerInfo['provider_seq']}'  ";

		}

		// 셀러어드민 출고 요약
		if	($_PARAM['query_type'] == 'selleradmin_summary'){
			$query = "
				select
					count(*) as cnt,
					sum(ifnull(opt_price,0)+ifnull(sub_price,0)) as total_settleprice
				FROM
					(
						select
							sum(opt.price*item.ea) opt_price, sum(sub.price*item.ea) sub_price,
							if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) as group_export_code,
							if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export
						FROM
							".$addFroms."
						GROUP BY group_export_code
					) a
				";
		// 셀러어드민 출고 총갯수
		}elseif	($_PARAM['query_type'] == 'selleradmin_total_record'){
			$query = "
				select
					count(*) as cnt
				FROM
					(
						select
							if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) as group_export_code,
							if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export
						FROM
							".$addFroms."
						GROUP BY group_export_code
					) a
				";
		// 셀러어드민 출고 목록
		}elseif	($_PARAM['query_type'] == 'selleradmin_catalog'){
			$query = "
				SELECT
					ord.*, ord.regist_date as order_date,exp.*,sum(opt.price*item.ea) opt_price,sum(sub.price*item.ea) sub_price,sum(item.ea) ea,
					(select sum(ea) from fm_order_item_option where order_seq = exp.order_seq and step in ('40', '45', '50', '55', '70', '75')) opt_ea,
					(select sum(ea) from fm_order_item_suboption where order_seq = exp.order_seq) sub_ea,
					(
						SELECT userid FROM fm_member WHERE member_seq=ord.member_seq
					) userid,
					(
						SELECT AES_DECRYPT(UNHEX(email), '{$shopkey}') as email FROM fm_member WHERE member_seq=ord.member_seq
					) mbinfo_email,
					(
						SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=ord.member_seq
					) group_name,
					(
						SELECT provider_name FROM fm_provider b
						WHERE exp.shipping_provider_seq=b.provider_seq
					) provider_name,
					mem.rute as mbinfo_rute,
					mem.user_name as mbinfo_user_name,
					bus.business_seq as mbinfo_business_seq,
					bus.bname as mbinfo_bname,
					if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) as group_export_code,
					if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export,
					group_concat(exp.order_seq)							AS has_order_list
				FROM
					".$addFroms."
				GROUP BY group_export_code
				ORDER BY exp.status asc,exp.export_seq DESC
				".$addLimit."";
		// 어드민 출고 요약
		}elseif	($_PARAM['query_type'] == 'summary'){
			$query = "
				select
					count(*) as cnt,
					sum(ifnull(opt_price,0)+ifnull(sub_price,0)) as total_settleprice
				FROM
					(
						select
							sum(opt.price*item.ea) opt_price, sum(sub.price*item.ea) sub_price,
							if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) as group_export_code,
							if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export
						FROM
							".$addFroms."
						GROUP BY group_export_code
					) a
				";
		// 어드민 출고 총갯수
		}elseif	($_PARAM['query_type'] == 'total_record'){
			$query = "
				select
					count(*) as cnt
				FROM
					(
						select
							if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) as group_export_code,
							if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export
						FROM
							".$addFroms."
						GROUP BY group_export_code
					) a
				";
		// 어드민 출고 목록
		}else{
			$query = "
				SELECT
					ord.*, ord.regist_date as order_date,exp.*,sum(opt.price*item.ea) opt_price,sum(sub.price*item.ea) sub_price,sum(item.ea) ea,
					sum(opt.ea) opt_ea,
					sum(sub.ea) sub_ea,
					(
						SELECT userid FROM fm_member WHERE member_seq=ord.member_seq
					) userid,
					(
						SELECT AES_DECRYPT(UNHEX(email), '{$shopkey}') as email FROM fm_member WHERE member_seq=ord.member_seq
					) mbinfo_email,
					(
						SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=ord.member_seq
					) group_name,
					(
						SELECT provider_name FROM fm_provider b
						WHERE exp.shipping_provider_seq=b.provider_seq
					) provider_name,
					mem.rute as mbinfo_rute,
					mem.user_name as mbinfo_user_name,
					bus.business_seq as mbinfo_business_seq,
					bus.bname as mbinfo_bname,
					if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) as group_export_code,
					if(exp.bundle_export_code REGEXP '^B', 'Y', 'N')	AS is_bundle_export
				FROM
					".$addFroms."
				GROUP BY group_export_code
				ORDER BY exp.status asc,exp.export_seq DESC
				".$addLimit."";
		}
		return $this->db->query($query);
	}

	/**
	 * 출고 아이템 검색
	 * 출고 아이템은 기본으로 export join
	 * 옵션은 fm_order_item_option join 
	 * 추가옵션은 fm_order_item_suboption join
	 */
	function get_data_export_item($params,$option_type = null){

		$query = $this->db->select("*")->from("fm_goods_export_item item")->join("fm_goods_export exp", "export_code", "left");

		if($option_type == "opt") {
			$query->join("fm_order_item_option opt", "opt.item_option_seq = item.option_seq", "left");
		}else if($option_type == "sub") {
			$query->join("fm_order_item_suboption opt", "opt.item_suboption_seq = item.suboption_seq", "left");
		}
		if($params) {
			$query->where($params);
		}
		return $query->get();
	}

	// 주문번호로 출고번호 조회
	public function getExportCodeForOrderSeq($order_seq){
		return $this->db->select('export_code')
						->from('fm_goods_export')
						->where('order_seq', $order_seq)
						->get()
						->result_array();
	}
}
/* End of file exportmodel.php */
/* Location: ./app/models/exportmodel.php */
