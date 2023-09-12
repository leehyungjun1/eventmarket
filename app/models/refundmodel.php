<?php
class Refundmodel extends CI_Model {
	public function __construct()
	{
//		$this->arr_refund_status = array(
//			'request'	=> '환불 신청',
//			'ing'		=> '환불 처리중',
//			'complete'	=> '환불 완료'
//		);
//
//		$this->arr_refund_type = array(
//			'cancel_payment'	=> '결제취소',
//			'return'			=> '반품',
//			'shipping_price'	=> '배송비',
//		);
//
//		$this->arr_cancel_type = array(
//			'full'		=> '전체취소',
//			'partial'	=> '부분취소',
//		);

		$this->arr_refund_status = array(
			'request'	=> getAlert('mp202'),
			'ing'		=> getAlert('mp203'),
			'complete'	=> getAlert('mp204')
		);

		$this->arr_refund_type = array(
			'cancel_payment'	=> getAlert('mp205'),
			'return'			=> getAlert('mp206'),
			'shipping_price'	=> getAlert('mp207')
		);

		$this->arr_cancel_type = array(
			'full'		=> getAlert('mp208'),
			'partial'	=> getAlert('mp209')
		);
	}

	public function insert_refund($data,$items='')
	{
		$data = array_diff_key($data,array("refund_code"=>""));
		$this->db->trans_begin();
		$this->db->insert('fm_order_refund', $data);

		$refund_seq = $this->db->insert_id();
		if(!$refund_seq){
			$this->db->trans_rollback();
			return false;
		}
		$update_data['refund_code'] = 'C'.date('ymdH').$refund_seq;

		$this->db->where('refund_seq',$refund_seq);
		$this->db->update('fm_order_refund',$update_data);

		if($items){
			foreach($items as $item_data){
				if($item_data['npay_product_order_id']){
					$partner_return = $item_data['partner_return'];
				}else{
					$partner_return = true;
				}
				//파트너사 API 전송 결과 true 일때만 저장. API사용안할시 무조건 true
				if($partner_return){
					$item_data['refund_code']		= $update_data['refund_code'];
					$item_data['give_reserve']		= $item_data['give_reserve'];			//지급한 마일리지
					$item_data['give_point']		= $item_data['give_point'];				//지급한 포인트
					$item_data['give_reserve_ea']	= $item_data['give_reserve_ea'];		//지급한 마일리지/포인트수량

					$item_data = array_diff_key($item_data,array("partner_return"=>""));
					$this->db->insert('fm_order_refund_item',$item_data);
				}
			}
		}

		if ($this->db->trans_status() === FALSE || $rollback == true)
		{
			$this->db->trans_rollback();
			return false;
		}else{
			$this->db->trans_commit();
		}
		return $update_data['refund_code'];
	}

	public function get_refund_able_ea($order_seq){

		$ea = 0;
		$query = "select
						sum(IFNULL(ea, 0)-(IFNULL(step45, 0)+IFNULL(step55, 0)+IFNULL(step65, 0)+IFNULL(step75, 0)+IFNULL(step85, 0))) as ea
				from
					fm_order_item_option
				where
					order_seq=? ";
		$query = $this->db->query($query, $order_seq);
		$option_cancel_able_ea = $query->row_array();
		if($option_cancel_able_ea) $ea += $option_cancel_able_ea['ea'];

		$query = "select
					sum(IFNULL(ea, 0)-(IFNULL(step45, 0)+IFNULL(step55, 0)+IFNULL(step65, 0)+IFNULL(step75, 0)+IFNULL(step85, 0))) as ea
				from
					fm_order_item_suboption
				where order_seq=?";
		$query = $this->db->query($query, $order_seq);
		$suboption_cancel_able_ea = $query->row_array();
		if($suboption_cancel_able_ea) $ea += $suboption_cancel_able_ea['ea'];

		return $ea;
	}

	public function get_refund_list($sc){

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
			o.download_seq,
			(
				SELECT goods_name FROM fm_order_item WHERE item_seq=ri.item_seq ORDER BY item_seq LIMIT 1
			) goods_name,
			(
				SELECT image FROM fm_order_item WHERE item_seq=ri.item_seq ORDER BY item_seq LIMIT 1
			) image,
			(
				SELECT count(item_seq) FROM fm_order_refund_item WHERE refund_code=r.refund_code
			) item_cnt,
			m.userid,
			m.user_name,
			(
				SELECT sum(ea) FROM fm_order_item_option WHERE order_seq=o.order_seq
			) option_ea,
			(
				SELECT sum(ea) FROM fm_order_item_suboption WHERE order_seq=o.order_seq
			) suboption_ea,
			sum(ri.ea) as refund_ea_sum
			from
				fm_order_refund as r
				inner join fm_order as o on r.order_seq = o.order_seq
				inner join fm_member as m on o.member_seq = m.member_seq
				inner join fm_order_refund_item as ri on r.refund_code=ri.refund_code
			{$sqlWhereClause}
			group by r.refund_code
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
			$result['record'][$k]['mstatus'] = $this->arr_refund_status[$result['record'][$k]['status']];
			//환불
			$result['record'][$k]['mtype'] = $this->arr_refund_type[$result['record'][$k]['refund_type']] . " ".getAlert('mp210');
			$result['record'][$k]['mrefund_date'] = $result['record'][$k]['refund_date']=='0000-00-00' ? '' : $result['record'][$k]['refund_date'];

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

	public function get_refund($refund_code)
	{
		$query = "select
					ref.*,ord.member_seq,mem.userid,mem.user_name,mem.emoney,mgp.group_name
				from
					fm_order_refund as ref
					left join fm_order as ord on ref.order_seq = ord.order_seq
					left join fm_member as mem on ord.member_seq = mem.member_seq
					left join fm_member_group mgp on mem.group_seq = mgp.group_seq
				where refund_code=? limit 1";
		$query = $this->db->query($query,array($refund_code));
		list($result) = $query -> result_array();
		return $result;
	}

	public function get_refund_price_for_order($order_seq,$type,$status)
	{
		$query = "select sum(refund_price + refund_emoney) refund_price from fm_order_refund where order_seq=? and refund_type=? and status=?";
		$query = $this->db->query($query,array($order_seq,$type,$status));
		list($result) = $query -> result_array();
		return $result['refund_price'];
	}

	public function get_refund_ea_for_order($order_seq,$type,$status)
	{
		$query = "select sum(ea) as refund_ea from fm_order_refund_item where refund_code in (select refund_code from fm_order_refund where order_seq=? and refund_type=? and status=?)";
		$query = $this->db->query($query,array($order_seq,$type,$status));
		list($result) = $query -> result_array();
		return $result['refund_ea'];
	}

	// 20210611(kjw) : item_seq 에 의한 환불 ea 개수를 알기위한 쿼리
	public function get_refund_ea_by_item($item_seq, $group_id = null) {
		$where = "item_seq = ?";
		$value = [$item_seq];
		if ($group_id !== null) {
			$where .= " AND talkbuy_shipping_fee_group_id = ?";
			array_push($value, $group_id);
		}
		$query = "select sum(ea) as refund_ea from fm_order_refund_item where ".$where."";
		$query = $this->db->query($query, $value);
		list($result) = $query->result_array();
		return $result["refund_ea"];
	}

	// 20210706(kjw) : 공제금액(배송비) 처리를 위한 완료된 환불건 조회
	public function get_refund_complete_by_order($order_seq) {
		$where = "order_seq = ? AND status = 'complete' ";
		$value = [$order_seq];
		$query = "select * from fm_order_refund where ".$where."";
		$query = $this->db->query($query, $value);
		$result = $query->result_array();
		return $result;
	}

	public function get_refund_option_ea(/*$shipping_provider_seq,*/$item_seq,$item_option_seq,$refund_type='all'){

		if($refund_type == "all"){
			$wheres = " ";
			$values = array($item_seq,$item_option_seq);
		}else{
			$wheres = " and ref.refund_type=?";
			$values = array($item_seq,$item_option_seq,$refund_type);
		}

		$query	= "select
					sum(ref_item.ea) as 'ea'
				from
					fm_order_refund_item as ref_item
					left join fm_order_refund as ref on ref_item.refund_code=ref.refund_code
				where
					ref_item.item_seq=? and ref_item.option_seq=?  ".$wheres." and (suboption_seq is null or suboption_seq='')";
		$query	= $this->db->query($query,$values);
		$result = $query->row_array();

		return $result['ea'];
	}

	public function get_refund_suboption_ea(/*$shipping_provider_seq,*/$item_seq,$item_suboption_seq,$refund_type='all'){

		if($refund_type == "all"){
			$wheres = " ";
			$values = array($item_seq,$item_suboption_seq);
		}else{
			$wheres = " and ref.refund_type=?";
			$values = array($item_seq,$item_suboption_seq,$refund_type);
		}

		$query	= "select
					sum(ref_item.ea) as 'ea'
				from
					fm_order_refund_item as ref_item
					left join fm_order_refund as ref on ref_item.refund_code=ref.refund_code
				where
					ref_item.item_seq=? and ref_item.suboption_seq=? ".$wheres;
		$query	= $this->db->query($query,$values);
		$result = $query->row_array();
		return $result['ea'];
	}

	public function get_refund_item_data($refund_code,$item_seq,$item_option_seq){
		$query = "select * from fm_order_refund_item where refund_code=? and item_seq=? and option_seq=?";
		$values = array($refund_code,$item_seq,$item_option_seq);
		$query = $this->db->query($query,$values);
		$result = $query->row_array();

		return $result;
	}

	/* 동일 주문의 환불완료 된 배송비 (배송그룹별로 나옴) */
	public function get_refund_complete_shipping_price($order_seq,$refund_code='',$shipping_seq){
		$bind	= array();
		
		$where	= " and ship.shipping_seq = ? ";
		$bind[] = $shipping_seq;

		if(is_array($order_seq)){
			$where .= " and ship.order_seq in(".implode(",",$order_seq).")";
		}else{
			$where .= " and ship.order_seq=?";
			$bind[] = $order_seq;
		}

		$query = "
			SELECT 
				ship.shipping_seq, ifnull(sum(refi.refund_delivery_price),0) as refund_shipping_cost
			FROM 
				fm_order AS o
				LEFT JOIN fm_order_item AS oi 
					ON o.order_seq = oi.order_seq
				LEFT JOIN fm_order_shipping as ship 
					ON o.order_seq = ship.order_seq
						AND oi.shipping_seq = ship.shipping_seq
				LEFT JOIN fm_order_refund as ref
					ON o.order_seq = ref.order_seq
						AND ref.status = 'complete'
				LEFT JOIN fm_order_refund_item as refi
					ON ref.refund_code = refi.refund_code
						AND oi.item_seq = refi.item_seq
			where 1=1
		".$where;

		$query					= $this->db->query($query,$bind);
		$total_shipping_data	= $query->row_array();
		
		if($refund_code){
			$where .= "and ref.refund_code!=?";
			$bind[] = $refund_code;
		}
		$query = "
			SELECT 
				ship.shipping_seq, ifnull(sum(refi.refund_delivery_price),0) as refund_shipping_cost
			FROM 
				fm_order AS o
				LEFT JOIN fm_order_item AS oi 
					ON o.order_seq = oi.order_seq
				LEFT JOIN fm_order_shipping as ship 
					ON o.order_seq = ship.order_seq
						AND oi.shipping_seq = ship.shipping_seq
				LEFT JOIN fm_order_refund as ref
					ON o.order_seq = ref.order_seq
						AND ref.status = 'complete'
				LEFT JOIN fm_order_refund_item as refi
					ON ref.refund_code = refi.refund_code
						AND oi.item_seq = refi.item_seq
			where 1=1
		".$where;
		
		$query					= $this->db->query($query,$bind);
		$except_shipping_data	= $query->row_array();

		$result = array(
			'total_shipping_data'	=> $total_shipping_data,
			'except_shipping_data'	=> $except_shipping_data
		);

		return $result;
		
	}

	/*
		동일 주문 배송그룹 별 기 환불완료 금액
		npay 환불금액 검수용
		@20200408 pjm
	*/
	public function get_refund_group_price($order_seq,$shipping_seq,$refund_status='complete'){

		$where = " ref.status='{$refund_status}'";

		if(is_array($order_seq)){
			$where .= " and ref.order_seq in(".implode(",",$order_seq).")";
		}else{
			$where .= " and ref.order_seq={$order_seq}";
		}

		if($shipping_seq){
			$where .= " and opt.shipping_seq={$shipping_seq}";
		}

		// refund_price가 배송비 포함으로 저장되어 배송비를 뺀 합계를 조회하도록 수정 :: 2018-07-31 pjw
		// 마일리지, 예치금은 완료된 환불건에서 가져와야하므로 추가 :: 2018-07-31 pjw
		$query = "select * from  (	
					select
						opt.shipping_seq,
						ifnull(sum(ref_item.refund_goods_price),0) as refund_goods_price,
						sum(ref_item.emoney_sale_unit*ref_item.ea+ref_item.emoney_sale_rest) as refund_goods_emoney,
						sum(ref_item.cash_sale_unit*ref_item.ea+ref_item.cash_sale_rest) as refund_goods_cash,
						ifnull(sum(ref_item.refund_delivery_emoney),0) as refund_shipping_emoney,
						ifnull(sum(ref_item.refund_delivery_cash),0) as refund_shipping_cash,
						ifnull(sum(ref_item.refund_delivery_price),0) as refund_shipping_cost
					from 
						fm_order_refund_item as ref_item 
						left join fm_order_refund as ref on ref_item.refund_code=ref.refund_code
						left join fm_order_item_option as opt on opt.order_seq=ref.order_seq and opt.item_option_seq=ref_item.option_seq and opt.item_seq=ref_item.item_seq
					where 
						".$where."
					group by opt.shipping_seq
					union all
					select
						opt.shipping_seq,
						ifnull(sum(ref_item.refund_goods_price),0) as refund_goods_price,
						sum(ref_item.emoney_sale_unit*ref_item.ea+ref_item.emoney_sale_rest) as refund_goods_emoney,
						sum(ref_item.cash_sale_unit*ref_item.ea+ref_item.cash_sale_rest) as refund_goods_cash,
						ifnull(sum(ref_item.refund_delivery_emoney),0) as refund_shipping_emoney,
						ifnull(sum(ref_item.refund_delivery_cash),0) as refund_shipping_cash,
						ifnull(sum(ref_item.refund_delivery_price),0) as refund_shipping_cost
					from 
						fm_order_refund_item as ref_item 
						left join fm_order_refund as ref on ref_item.refund_code=ref.refund_code and ref_item.suboption_seq > 0
						left join fm_order_item_option as opt on opt.order_seq=ref.order_seq and opt.item_option_seq=ref_item.option_seq and opt.item_seq=ref_item.item_seq
						left join fm_order_item_suboption as sub on sub.order_seq=ref.order_seq and sub.item_option_seq=opt.item_option_seq and sub.item_suboption_seq=ref_item.suboption_seq and sub.item_seq=ref_item.item_seq
					where 
						".$where."
					group by opt.shipping_seq
					
				) as k";

		$query	= $this->db->query($query);

		$result = array();
		foreach($query->result_array() as $data){
			if($shipping_seq){
				$result = $data;
			}else{
				$result[$data['shipping_seq']] = $data;
			}
		}

		return $result;
	}

	/* 동일 주문의 기 환불완료 금액 */
	// refund_price가 배송비 포함한 가격을 저장해서 쿼리에서는 배송비를 제외한 금액으로 조회 :: 2018-07-31 pjw
	public function get_refund_complete_price($order_seq,$option_seq,$opt_type,$refund_code=''){

		$bind = array();

		if(is_array($order_seq)){
			$where = "ref.order_seq in(".implode(",",$order_seq).")";
		}else{
			$where = "ref.order_seq=?";
			$bind[] = $order_seq;
		}

		if($opt_type == "opt"){
			$where .= " and ref_item.option_seq=? and ref_item.suboption_seq=0";
			$bind[] = $option_seq;
		}else if($opt_type == "sub"){
			$where .= " and ref_item.suboption_seq=?";
			$bind[] = $option_seq;
		}
		
		// refund_price가 배송비 포함으로 저장되어 배송비를 뺀 합계를 조회하도록 수정 :: 2018-07-31 pjw
		// 마일리지, 예치금은 완료된 환불건에서 가져와야하므로 추가 :: 2018-07-31 pjw
		$query = "select
						sum(ref_item.ea) as complete_ea
						,sum(ref.refund_price) as complete_price
						,sum(ref.refund_delivery) as complete_delivery
						,sum(ref_item.emoney_sale_unit) as refund_emoney_sale_unit
						,sum(ref_item.cash_sale_unit) as refund_cash_sale_unit
						,sum(ref_item.emoney_sale_unit*ref_item.ea) + sum(ref_item.emoney_sale_rest) as refund_emoney
						,sum(ref_item.cash_sale_unit*ref_item.ea) + sum(ref_item.cash_sale_rest) as refund_cash
						,sum(ref_item.refund_delivery_emoney) as refund_delivery_emoney
						,sum(ref_item.refund_delivery_cash) as refund_delivery_cash
						,sum(ref_item.coupon_deduction_price) as coupon_deduction_price
						,sum(ref_item.refund_goods_price) as refund_goods_price
						,sum(ref_item.refund_delivery_price) as refund_delivery_price
					from
						fm_order_refund as ref
						left join fm_order_refund_item as ref_item on ref.refund_code=ref_item.refund_code
					where
						ref.status='complete'
						" . $refund_where . " 
						and ".$where;

		$query	= $this->db->query($query,$bind);
		return $query->row_array();
	}

	/* 동일 주문의 기 환불금액(마일리지, 예치금) */
	public function get_refund_complete_emoney($order_seq){

		$bind = array();
		if(is_array($order_seq)){
			$where = " and ref.order_seq in (".implode(",",$order_seq).")";
		}else{
			$where = " and ref.order_seq=? ";
			$bind[] = $order_seq;
		}
		$query = "select
						sum(ref.refund_emoney) as complete_emoney
						,sum(ref.refund_cash) as complete_cash
					from
						fm_order_refund as ref
					where
						ref.status='complete'".$where;
		$query = $this->db->query($query,$bind);
		$result = $query->result_array();

		if(!$result[0]['complete_emoney']) $result[0]['complete_emoney'] = 0;
		if(!$result[0]['complete_cash']) $result[0]['complete_cash'] = 0;

		return $result[0];
	}

	public function get_refund_item($refund_code,$order_seq='',$new_order_seq='')
	{
		if( defined('__SELLERADMIN__') === true ){

			//opt
			// event_sale, multi_sale 추가 :: 2018-07-16 pjw
			// [판매지수 EP] referer_domain 추가 :: 2018-09-18 pjw
			$field1 = "
					'opt' opt_type,
					opt.item_option_seq option_seq,
					opt.step,
					opt.supply_price,
					opt.consumer_price,
					opt.price as price,
					opt.basic_sale,
					opt.goods_price as goods_price,
					opt.original_price,
					opt.sale_price,
					opt.mobile_sale_unit,
					opt.fblike_sale_unit,
					opt.coupon_sale_unit,
					opt.code_sale_unit,
					opt.referer_sale_unit,
					opt.emoney_sale_unit,
					opt.cash_sale_unit,
					opt.enuri_sale_unit,
					opt.emoney_sale_rest,
					opt.cash_sale_rest,
					opt.enuri_sale_rest,
					opt.download_seq,
					opt.coupon_sale,
					opt.unit_ordersheet,
					opt.member_sale as member_sale,
					opt.member_sale as member_sale_unit,
					opt.member_sale_rest,
					opt.fblike_sale,
					opt.mobile_sale,
					opt.promotion_code_seq,
					opt.promotion_code_sale,
					opt.referer_sale,
					opt.reserve as reserve,
					opt.point as point,
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
					opt.ea option_ea,
					opt.refund_ea,
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
					opt.top_item_option_seq,
					item.goods_seq,
					item.goods_kind,
					item.socialcp_input_type,
					item.socialcp_use_return,
					item.socialcp_use_emoney_day,
					item.socialcp_use_emoney_percent,
					item.item_seq,
					item.shipping_seq,
					item.goods_shipping_cost,
					item.shipping_policy,
					item.shipping_unit,
					item.basic_shipping_cost,
					item.add_shipping_cost,
					item.goods_name,
					item.image,
					item.event_seq,
					item.goods_type,
					item.tax,
					item.adult_goods,
					item.option_international_shipping_status,
					ref.refund_item_seq,
					ref.coupon_refund_type,
					ref.coupon_refund_emoney,
					ref.coupon_remain_price,
					ref.coupon_deduction_price,
					ref.emoney_sale_unit as refund_emoney_sale_unit,
					ref.emoney_sale_rest as refund_emoney_sale_rest,
					ref.cash_sale_unit as refund_cash_sale_unit,
					ref.cash_sale_rest as refund_cash_sale_rest,
					ref.cancel_memo,
					ref.coupon_remain_real_percent,
					ref.coupon_real_value,
					ref.coupon_remain_real_value,
					IFNULL(ref.ea,0) ea,
					opt.npay_product_order_id,
					ref.give_reserve,
					ref.give_point,
					IFNULL(opt.unit_emoney,0) unit_emoney,
					item.provider_seq,
					IFNULL(ref.give_reserve,0) as give_reserve,
					IFNULL(ref.give_point,0) as give_point,
					IFNULL(ref.refund_goods_price,0) refund_goods_price,
					IFNULL(ref.refund_goods_coupon,0) refund_goods_coupon,
					IFNULL(ref.refund_goods_promotion,0) refund_goods_promotion,
					IFNULL(ref.refund_delivery_price,0) refund_delivery_price,
					IFNULL(ref.refund_delivery_cash,0) refund_delivery_cash,
					IFNULL(ref.refund_delivery_emoney,0) refund_delivery_emoney,
					IFNULL(ref.refund_delivery_coupon,0) refund_delivery_coupon,
					IFNULL(ref.refund_delivery_promotion,0) refund_delivery_promotion,
					(select cancel_type from fm_goods where goods_seq=item.goods_seq) as cancel_type,
					opt.event_sale,
					opt.multi_sale,
					item.referer_domain
				";

			if($order_seq){
			}else{
				$table_db1	= "fm_order_refund_item ref,fm_order_item_option opt,fm_order_item item";
				$where1		= "
						ref.option_seq is not null
						AND ref.option_seq = opt.item_option_seq and ref.suboption_seq = ''
						AND opt.item_seq = item.item_seq
						AND item.provider_seq = {$this->providerInfo['provider_seq']}
						AND ref.refund_code = ?
						";
			}
			$query1 = "select ".$field1." from ".$table_db1." where ".$where1;


			//sub
			// event_sale, multi_sale 추가 :: 2018-07-16 pjw
			$field2 = "
					'sub' opt_type,
					sub.item_suboption_seq option_seq,
					sub.step,
					sub.supply_price,
					sub.consumer_price,
					sub.price as price,
					0 as basic_sale,
					0 as goods_price,
					sub.original_price,
					0 as sale_price,
					0 as mobile_sale_unit,
					0 as fblike_sale_unit,
					0 as coupon_sale_unit,
					0 as code_sale_unit,
					0 as referer_sale_unit,
					sub.emoney_sale_unit,
					sub.cash_sale_unit,
					sub.enuri_sale_unit,
					sub.emoney_sale_rest,
					sub.cash_sale_rest,
					sub.enuri_sale_rest,
					'' as download_seq,
					0 as coupon_sale,
					0 unit_ordersheet,
					sub.member_sale as member_sale,
					sub.member_sale as member_sale_unit,
					sub.member_sale_rest,
					0 as fblike_sale,
					0 as mobile_sale,
					'' as promotion_code_seq,
					0 as promotion_code_sale,
					0 as referer_sale,
					sub.reserve as reserve,
					sub.point as point,
					sub.title as title1,
					'' as title2,
					'' as title3,
					'' as title4,
					'' as title5,
					sub.suboption option1,
					'' as option2,
					'' as option3,
					'' as option4,
					'' as option5,
					sub.goods_code,
					sub.ea option_ea,
					sub.refund_ea,
					sub.newtype,
					sub.color,
					sub.zipcode,
					sub.address,
					sub.addressdetail,
					sub.biztel,
					'' as address_commission,
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
					sub.top_item_suboption_seq as top_item_option_seq,
					item.goods_seq,
					item.goods_kind,
					item.socialcp_input_type,
					item.socialcp_use_return,
					item.socialcp_use_emoney_day,
					item.socialcp_use_emoney_percent,
					item.item_seq,
					item.shipping_seq,
					item.goods_shipping_cost,
					item.shipping_policy,
					item.shipping_unit,
					item.basic_shipping_cost,
					item.add_shipping_cost,
					item.goods_name,
					item.image,
					item.event_seq,
					item.goods_type,
					item.tax,
					item.adult_goods,
					item.option_international_shipping_status,
					ref.refund_item_seq,
					ref.coupon_refund_type,
					ref.coupon_refund_emoney,
					ref.coupon_remain_price,
					ref.coupon_deduction_price,
					ref.emoney_sale_unit as refund_emoney_sale_unit,
					ref.emoney_sale_rest as refund_emoney_sale_rest,
					ref.cash_sale_unit as refund_cash_sale_unit,
					ref.cash_sale_rest as refund_cash_sale_rest,
					ref.cancel_memo,
					ref.coupon_remain_real_percent,
					ref.coupon_real_value,
					ref.coupon_remain_real_value,
					IFNULL(ref.ea,0) ea,
					sub.npay_product_order_id,
					ref.give_reserve,
					ref.give_point,
					IFNULL(sub.unit_emoney,0) unit_emoney,
					item.provider_seq,
					IFNULL(ref.give_reserve,0) as give_reserve,
					IFNULL(ref.give_point,0) as give_point,
					IFNULL(ref.refund_goods_price,0) refund_goods_price,
					IFNULL(ref.refund_goods_coupon,0) refund_goods_coupon,
					IFNULL(ref.refund_goods_promotion,0) refund_goods_promotion,
					IFNULL(ref.refund_delivery_price,0) refund_delivery_price,
					IFNULL(ref.refund_delivery_cash,0) refund_delivery_cash,
					IFNULL(ref.refund_delivery_emoney,0) refund_delivery_emoney,
					IFNULL(ref.refund_delivery_coupon,0) refund_delivery_coupon,
					IFNULL(ref.refund_delivery_promotion,0) refund_delivery_promotion,
					(select cancel_type from fm_goods where goods_seq=item.goods_seq) as cancel_type,
					sub.event_sale,
					sub.multi_sale,
					item.referer_domain
				";

			if($order_seq){
				$table_db2	= "fm_order_refund_item ref,fm_order_item_suboption sub,fm_order_item item";
				$where2 = "
						ref.suboption_seq is not null
						AND ref.suboption_seq = sub.item_suboption_seq
						AND sub.item_seq = item.item_seq
						AND item.provider_seq = {$this->providerInfo['provider_seq']}
						AND ref.refund_code = ?
					";
			}
			$query2 = "select ".$field2." from ".$table_db2." where ".$where2;

		}else{
			//opt
			$field1 = "
				'opt' opt_type,
				opt.item_option_seq option_seq,
				opt.step,
				opt.supply_price,
				opt.consumer_price,
				opt.price as price,
				opt.basic_sale,
				opt.goods_price as goods_price,
				opt.original_price,
				opt.sale_price,
				opt.mobile_sale_unit,
				opt.fblike_sale_unit,
				opt.coupon_sale_unit,
				opt.code_sale_unit,
				opt.referer_sale_unit,
				opt.emoney_sale_unit,
				opt.cash_sale_unit,
				opt.enuri_sale_unit,
				opt.emoney_sale_rest,
				opt.cash_sale_rest,
				opt.enuri_sale_rest,
				opt.download_seq,
				opt.coupon_sale,
				opt.unit_ordersheet,
				opt.member_sale as member_sale,
				opt.member_sale as member_sale_unit,
				opt.member_sale_rest,
				opt.fblike_sale,
				opt.mobile_sale,
				opt.promotion_code_seq,
				opt.promotion_code_sale,
				opt.referer_sale,
				opt.reserve as reserve,
				opt.point as point,
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
				opt.ea option_ea,
				opt.refund_ea,
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
				opt.top_item_option_seq,
				item.goods_seq,
				item.goods_kind,
				item.socialcp_input_type,
				item.socialcp_use_return,
				item.socialcp_use_emoney_day,
				item.socialcp_use_emoney_percent,
				item.item_seq,
				item.shipping_seq,
				item.goods_shipping_cost,
				item.shipping_policy,
				item.shipping_unit,
				item.basic_shipping_cost,
				item.add_shipping_cost,
				item.goods_name,
				item.image,
				item.event_seq,
				item.goods_type,
				item.tax,
				item.adult_goods,
				item.option_international_shipping_status,
				ref.refund_item_seq,
				ref.coupon_refund_type,
				ref.coupon_refund_emoney,
				ref.coupon_remain_price,
				ref.coupon_deduction_price,
				ref.emoney_sale_unit as refund_emoney_sale_unit,
				ref.emoney_sale_rest as refund_emoney_sale_rest,
				ref.cash_sale_unit as refund_cash_sale_unit,
				ref.cash_sale_rest as refund_cash_sale_rest,
				ref.cancel_memo,
				ref.coupon_remain_real_percent,
				ref.coupon_real_value,
				ref.coupon_remain_real_value,
				IFNULL(ref.ea,0) ea,
				opt.npay_product_order_id,
				ref.give_reserve,
				ref.give_point,
				IFNULL(opt.unit_emoney,0) unit_emoney,
				item.provider_seq,
				IFNULL(ref.give_reserve,0) as give_reserve,
				IFNULL(ref.give_point,0) as give_point,
				IFNULL(ref.refund_goods_price,0) refund_goods_price,
				IFNULL(ref.refund_goods_coupon,0) refund_goods_coupon,
				IFNULL(ref.refund_goods_promotion,0) refund_goods_promotion,
				IFNULL(ref.refund_delivery_price,0) refund_delivery_price,
				IFNULL(ref.refund_delivery_cash,0) refund_delivery_cash,
				IFNULL(ref.refund_delivery_emoney,0) refund_delivery_emoney,
				IFNULL(ref.refund_delivery_coupon,0) refund_delivery_coupon,
				IFNULL(ref.refund_delivery_promotion,0) refund_delivery_promotion,
				(select cancel_type from fm_goods where goods_seq=item.goods_seq) as cancel_type,
				opt.event_sale,
				opt.multi_sale,
				item.referer_domain
				";

			if($order_seq){
				$table_db1 = "	fm_order_item_option opt
							left join fm_order_item item on opt.item_seq = item.item_seq
							left join fm_order_refund_item ref on ref.option_seq = opt.item_option_seq and ref.suboption_seq = '' and ref.refund_code=?
						";
				if(is_array($order_seq)){
					$where1		= "opt.order_seq in (".implode(",",$order_seq).")";
				}else{
					$where1		= "opt.order_seq = ?";
				}
			}else{
				$table_db1	= "fm_order_refund_item ref,fm_order_item_option opt,fm_order_item item";
				$where1		= "
						ref.option_seq is not null
						AND ref.option_seq = opt.item_option_seq and ref.suboption_seq = ''
						AND opt.item_seq = item.item_seq
						AND ref.refund_code = ?
						";
			}
			$query1 = "select ".$field1." from ".$table_db1." where ".$where1;

			//sub
			$field2 = "
				'sub' opt_type,
				sub.item_suboption_seq option_seq,
				sub.step,
				sub.supply_price,
				sub.consumer_price,
				sub.price as price,
				0 as basic_sale,
				0 as goods_price,
				sub.original_price,
				0 as sale_price,
				0 as mobile_sale_unit,
				0 as fblike_sale_unit,
				0 as coupon_sale_unit,
				0 as code_sale_unit,
				0 as referer_sale_unit,
				sub.emoney_sale_unit,
				sub.cash_sale_unit,
				sub.enuri_sale_unit,
				sub.emoney_sale_rest,
				sub.cash_sale_rest,
				sub.enuri_sale_rest,
				'' as download_seq,
				0 as coupon_sale,
				0 unit_ordersheet,
				sub.member_sale as member_sale,
				sub.member_sale as member_sale_unit,
				sub.member_sale_rest,
				0 as fblike_sale,
				0 as mobile_sale,
				'' as promotion_code_seq,
				0 as promotion_code_sale,
				0 as referer_sale,
				sub.reserve as reserve,
				sub.point as point,
				sub.title title1,
				'' as title2,
				'' as title3,
				'' as title4,
				'' as title5,
				sub.suboption option1,
				'' as option2,
				'' as option3,
				'' as option4,
				'' as option5,
				sub.goods_code,
				sub.ea option_ea,
				sub.refund_ea,
				sub.newtype,
				sub.color,
				sub.zipcode,
				sub.address,
				sub.addressdetail,
				sub.biztel,
				'' as address_commission,
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
				sub.top_item_suboption_seq as top_item_option_seq,
				item.goods_seq,
				item.goods_kind,
				item.socialcp_input_type,
				item.socialcp_use_return,
				item.socialcp_use_emoney_day,
				item.socialcp_use_emoney_percent,
				item.item_seq,
				item.shipping_seq,
				item.goods_shipping_cost,
				item.shipping_policy,
				item.shipping_unit,
				item.basic_shipping_cost,
				item.add_shipping_cost,
				item.goods_name,
				item.image,
				item.event_seq,
				item.goods_type,
				item.tax,
				item.adult_goods,
				item.option_international_shipping_status,
				ref.refund_item_seq,
				ref.coupon_refund_type,
				ref.coupon_refund_emoney,
				ref.coupon_remain_price,
				ref.coupon_deduction_price,
				ref.emoney_sale_unit as refund_emoney_sale_unit,
				ref.emoney_sale_rest as refund_emoney_sale_rest,
				ref.cash_sale_unit as refund_cash_sale_unit,
				ref.cash_sale_rest as refund_cash_sale_rest,
				ref.cancel_memo,
				ref.coupon_remain_real_percent,
				ref.coupon_real_value,
				ref.coupon_remain_real_value,
				IFNULL(ref.ea,0) ea,
				sub.npay_product_order_id,
				ref.give_reserve,
				ref.give_point,
				IFNULL(sub.unit_emoney,0) unit_emoney,
				item.provider_seq,
				IFNULL(ref.give_reserve,0) as give_reserve,
				IFNULL(ref.give_point,0) as give_point,
				IFNULL(ref.refund_goods_price,0) refund_goods_price,
				IFNULL(ref.refund_goods_coupon,0) refund_goods_coupon,
				IFNULL(ref.refund_goods_promotion,0) refund_goods_promotion,
				IFNULL(ref.refund_delivery_price,0) refund_delivery_price,
				IFNULL(ref.refund_delivery_cash,0) refund_delivery_cash,
				IFNULL(ref.refund_delivery_emoney,0) refund_delivery_emoney,
				IFNULL(ref.refund_delivery_coupon,0) refund_delivery_coupon,
				IFNULL(ref.refund_delivery_promotion,0) refund_delivery_promotion,
				(select cancel_type from fm_goods where goods_seq=item.goods_seq) as cancel_type,
				sub.event_sale,
				sub.multi_sale,
				item.referer_domain
				";

			if($order_seq){
				$table_db2 = "	fm_order_item_suboption sub
							left join fm_order_item item on sub.item_seq = item.item_seq
							left join fm_order_refund_item ref on ref.suboption_seq = sub.item_suboption_seq and ref.refund_code=?
						";
				if(is_array($order_seq)){
					$where2		= "sub.order_seq in (".implode(",",$order_seq).")";
				}else{
					$where2		= "sub.order_seq = ?";
				}
			}else{
				$table_db2	= "fm_order_refund_item ref,fm_order_item_suboption sub,fm_order_item item";
				$where2		= "
						ref.suboption_seq is not null
						AND ref.suboption_seq = sub.item_suboption_seq
						AND sub.item_seq = item.item_seq
						AND ref.refund_code = ?
						";
			}
			$query2 = "select ".$field2." from ".$table_db2." where ".$where2;
		}

		$query = "(".$query1.") union all (".$query2.") order by shipping_seq,goods_type desc, opt_type asc,item_seq asc";
		if($order_seq && !is_array($order_seq)){
			$query = $this->db->query($query,array($refund_code,$order_seq,$refund_code,$order_seq));
		}else{
			$query = $this->db->query($query,array($refund_code,$refund_code));
		}
		if($query) foreach($query->result_array() as $data){

			$data['new_option_seq']	= $data['item_option_seq'];

			## 맞교환일때(맞교환된 새 주문)
			if($new_order_seq){

				# 맞교환한 상품 정보 보여주기. 단, 금액은 원주문 그대로
				if($data['opt_type'] == "opt"){
					$query2 = "select
									item_seq,item_option_seq,ea,title1,title2,title3,title3,title5,option1,option2,option3,option4,option5
							from fm_order_item_option where order_seq=? and top_item_option_seq=?";
				}else{
					$query2 = "select
									item_seq,'' as item_option_seq,ea,title as title1, suboption as option1
								from fm_order_item_suboption where order_seq=? and top_item_suboption_seq=?";
				}
				$query2 = $this->db->query($query2,array($new_order_seq,$data['option_seq']));
				$exchange_option = $query2->row_array();

				$data['new_option_seq']	= $exchange_option['item_option_seq'];
				$data['option_ea']	= $exchange_option['ea'];
				$data['title1']		= $exchange_option['title1'];
				$data['title2']		= $exchange_option['title2'];
				$data['title3']		= $exchange_option['title3'];
				$data['title4']		= $exchange_option['title4'];
				$data['title5']		= $exchange_option['title5'];
				$data['option1']	= $exchange_option['option1'];
				$data['option2']	= $exchange_option['option2'];
				$data['option3']	= $exchange_option['option3'];
				$data['option4']	= $exchange_option['option4'];
				$data['option5']	= $exchange_option['option5'];

				if($data['new_option_seq']){
					$query2 = "select * from fm_order_item_input where order_seq=? and item_seq=? and item_option_seq=?";
					$query2 = $this->db->query($query2,array($new_order_seq,$exchange_option['item_seq'],$data['new_option_seq']));
					$data['inputs'] = $query2->result_array();
				}

				$query2 = "select
								image,goods_name,goods_code,goods_seq
							from fm_order_item where order_seq=? and item_seq=?";
				$query2 = $this->db->query($query2,array($new_order_seq,$exchange_option['item_seq']));
				$exchange_item = $query2->row_array();

				$data['image']		= $exchange_item['image'];
				$data['goods_name']	= $exchange_item['goods_name'];
				$data['goods_code']	= $exchange_item['goods_code'];
				$data['goods_seq']	= $exchange_item['goods_seq'];
			}


			//주문상품의 이미지가 없는경우 실제상품의 이미지를 가져옴
			if( !(is_file($data['image'])) ) {
				$data['image'] = viewImg($data['goods_seq'],'thumbCart');
			}
			$result[] = $data;
		}

		return $result;
	}

	public function get_refund_shipping_cost($data_order,$data_order_item,$data_refund,$data_refund_item,$data_order_shipping){

		$isFullCancel = $this->isFullCancel($data_refund['cancel_type'],$data_order['order_seq']) ? true : false; // 부분취소를 여러번해서 결국 전체취소가 될 경우도 "FullCancel에 해당"

		/* 해당 입점사의 아이템만 남기고 unset */
		$provider_item_seqs = array();
		foreach($data_order_item as $k=>$row){
			if($data_order_shipping['provider_seq']==$row['provider_seq']){
				$provider_item_seqs[] = $row['item_seq'];
			}else{
				unset($data_order_item[$k]);
			}
		}

		/* 해당 입점사의 아이템만 남기고 unset */
		foreach($data_refund_item as $k=>$row){
			if(!in_array($row['item_seq'],$provider_item_seqs)) unset($data_refund_item[$k]);
		}

		/* 갯수 체크 : 입점사별 full인지 partial인지 체크 */
		$total_ea = 0;		//총 기본 배송상품개수(기취소된개수 제외)
		$cancel_ea = 0;		//취소할 기본배송상품개수

		foreach($data_order_item as $k=>$item)
		{
			if($item['shipping_policy']=='shop')
			{
				$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
				$suboptions = $this->ordermodel->get_suboption_for_item($item['item_seq']);

				if($options) foreach($options as $option){
					$total_ea += ($option['ea']-$option['refund_ea']);
				}

				if($suboptions) foreach($suboptions as $suboption){
					$total_ea += ($suboption['ea']-$suboption['refund_ea']);
				}
			}
		}

		foreach($data_refund_item as $k=>$data)
		{
			if($data['shipping_policy']=='shop')
			{
				$cancel_ea	+= $data['ea'];
			}
		}

		$cancel_type = $total_ea == $cancel_ea ? 'full' : 'partial';

		/* 실배송비 */
		if($data_order['international']=='international'){
			$data_order['real_shipping_cost'] = $data_order_shipping['international_cost'];
		}else{
			$data_order['real_shipping_cost'] = $data_order_shipping['shipping_cost'];
		}

		/* 유료배송정책 */
		if($data_order_shipping['delivery_if']==0 && $data_order_shipping['delivery_cost']>0)
		{
			/* 결제취소에 의한 환불일 경우 */
			if($data_refund['refund_type']=='cancel_payment')
			{

				/*
				 전체취소이거나,
				 부분취소시 더이상 기본배송상품이 없게 될 경우
				*/
				if($isFullCancel || $this->willBeAllGoodsShipping($data_order,$data_order_item,$data_refund,$data_refund_item,$data_order_shipping))
				{
					return $data_order['real_shipping_cost'];
				}
				else
				{
					return 0;
				}
			}
			/* 반품에 의한 환불일 경우 */
			else
			{
				return 0;
			}
		}
		/* 조건부무료배송정책 */
		else if($data_order_shipping['delivery_if']>0)
		{
			/* 결제취소에 의한 환불일 경우 */
			if($data_refund['refund_type']=='cancel_payment')
			{
				/* 부분취소이며, 취소시 무료배송조건이 안맞게 될 경우  */
				if(!$isFullCancel && $this->willBeConditionsInvalid($data_order,$data_order_item,$data_refund,$data_refund_item,$data_order_shipping))
				{
					return -$data_order_shipping['delivery_cost'];
				}
				elseif($data_order_shipping['delivery_cost']>0 && $isFullCancel || $this->willBeAllGoodsShipping($data_order,$data_order_item,$data_refund,$data_refund_item,$data_order_shipping))
				{
					return $data_order['real_shipping_cost'];
				}
				else
				{
					return 0;
				}
			}else{
				return 0;
			}
		}
		/* 무료배송정책 */
		else
		{
			return 0;
		}
	}

	// 전체취소 여부 반환
	public function isFullCancel($cancel_type,$order_seq){
		if($cancel_type=='full' || $this->get_refund_able_ea($order_seq)==0) return true;
		else return false;
	}

	/* 취소후 더이상 기본배송상품이 없게 될지 여부 체크 */
	public function willBeAllGoodsShipping($data_order,$data_order_item,$data_refund,$data_refund_item,$data_order_shipping){
		$total_ea = 0;		//총 기본 배송상품개수(기취소된개수 제외)
		$cancel_ea = 0;		//취소후 총 기본배송상품 취소수
		$cancel_ea_before = 0;		//기취소된 기본배송상품개수

		foreach($data_order_item as $k=>$item)
		{
			if($item['shipping_policy']=='shop')
			{
				$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
				$suboptions = $this->ordermodel->get_suboption_for_item($item['item_seq']);

				if($options) foreach($options as $option){
					$total_ea += $option['ea'];
				}

				if($suboptions) foreach($suboptions as $suboption){
					$total_ea += $suboption['ea'];
				}
			}
		}

		foreach($data_refund_item as $k=>$data)
		{
			if($data['shipping_policy']=='shop')
			{
				$cancel_ea_before	+= $data['refund_ea']-$data['ea'];
				$cancel_ea			+= $data['refund_ea'];
			}
		}

		$remain_ea_before = $total_ea-$cancel_ea_before; // 취소처리 전 기본배송상품 수
		$remain_ea_after = $total_ea-$cancel_ea; // 취소처리 후 총 기본배송상품 수

		return $remain_ea_before && $remain_ea_after==0 ? true : false;
	}

	/* 취소후 무료배송조건이 안맞게 되는지 여부 체크 */
	public function willBeConditionsInvalid($data_order,$data_order_item,$data_refund,$data_refund_item,$data_order_shipping){
		$total_price = 0; // 총 상품가격
		$cancel_price = 0; // 취소후 총 취소상품가격
		$cancel_price_before = 0; // 기취소된 상품가격

		foreach($data_order_item as $k=>$item)
		{
			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
			$suboptions = $this->ordermodel->get_suboption_for_item($item['item_seq']);

			if($options) foreach($options as $option){
				$total_price += ($option['price']*$option['ea']);
			}

			if($suboptions) foreach($suboptions as $suboption){
				$total_price += ($suboption['price']*$suboption['ea']);
			}
		}

		foreach($data_refund_item as $k=>$data)
		{
			if($data['shipping_policy']=='shop')
			{
				$cancel_price_before	+= $data['price']*($data['refund_ea']-$data['ea']);
				$cancel_price			+= $data['price']*$data['refund_ea'];
			}
		}

		$result_price_before	= $total_price-$cancel_price_before; // 취소처리 전 기취소 조건금액
		$result_price_after		= $total_price-$cancel_price; // 취소처리 후 총 취소 조건금액

		if($data_order['real_shipping_cost']==0){
			if($result_price_after && $result_price_before >= $data_order_shipping['delivery_if'] && $result_price_after < $data_order_shipping['delivery_if']){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	public function get_refund_for_order($order_seq)
	{
		if( defined('__SELLERADMIN__') === true ){
			$query = "
			select r.*,
			sum(i.ea) ea,
			(
				SELECT goods_name FROM fm_order_item WHERE item_seq=i.item_seq ORDER BY item_seq LIMIT 1
			) goods_name,
			(select mname from fm_manager where manager_seq=r.manager_seq) admin
			from
			fm_order_refund r,
			fm_order_refund_item i
			left join fm_order_item_option opt on opt.item_option_seq=i.option_seq
			left join fm_order_item_suboption sub on sub.item_suboption_seq=i.suboption_seq
			LEFT JOIN fm_order_item orditem ON orditem.item_seq = i.item_seq
			where r.refund_code=i.refund_code and r.order_seq=? and orditem.provider_seq=? group by r.refund_code";
			$query = $this->db->query($query,array($order_seq, $this->providerInfo['provider_seq']));
		}else{
			$query = "
			select r.*,
			sum(i.ea) ea,
			(
				SELECT goods_name FROM fm_order_item WHERE item_seq=i.item_seq ORDER BY item_seq LIMIT 1
			) goods_name,
			(select mname from fm_manager where manager_seq=r.manager_seq) admin
			from
			fm_order_refund r,
			fm_order_refund_item i
			left join fm_order_item_option opt on opt.item_option_seq=i.option_seq
			left join fm_order_item_suboption sub on sub.item_suboption_seq=i.suboption_seq
			where r.refund_code=i.refund_code and r.order_seq=? group by r.refund_code";
			$query = $this->db->query($query,array($order_seq));
		}
		foreach($query -> result_array() as $data) $result[] = $data;
		return $result;
	}

	// 주문번호로 환불 정보 가져오기 (아이템별) :: 2017-08-25 lwh
	public function get_refund_item_for_order($order_seq)
	{
		$query = "
			SELECT 
				r.refund_code,
				oi.tax,
				r.refund_emoney, 
				r.refund_cash,
				r.status,
				SUM(i.refund_goods_price) AS refund_goods_price,
				SUM(i.refund_delivery_price) AS refund_delivery_price	
			FROM
				fm_order_refund AS r, 
				fm_order_refund_item AS i
				LEFT JOIN fm_order_item AS oi
					ON i.item_seq=oi.item_seq
			WHERE 
				r.refund_code=i.refund_code and 
				r.order_seq=?
			GROUP BY r.refund_code, oi.tax
		";
		$query = $this->db->query($query,array($order_seq));
		
		foreach($query -> result_array() as $data) $result[] = $data;
		return $result;
	}

	/* Paypal 결제 취소 */
	public function paypal_cancel($data_order,$data_refund){

		//error_reporting(E_ALL);
		require_once dirname(__FILE__)."/../../pg/paypal/API_Key.php";
		require_once dirname(__FILE__)."/../../pg/paypal/SendRequest.php";

		$nvpstr = array();
		$cancel_type = $data_refund['cancel_type']=='partial' ? 'Partial' : 'Full';
		$nvpstr[] = "TRANSACTIONID=".$data_order['pg_transaction_number'];
		$nvpstr[] = "REFUNDTYPE=".$cancel_type;

		//부분환불시 통화를 포함한 환불금액 지정
		$$memo		= '';
		if($cancel_type == 'Partial'){
			$nvpstr[] = "AMT=".$data_refund['refund_pg_price'];
			$nvpstr[] = "CURRENCYCODE=".$data_order['pg_currency'];	//통화
			if($memo) $nvpstr[] = "NOTE=".$memo;		// 부분환불 메모
		}

		$nvpstring	= $nvpHeader."&".implode("&",$nvpstr);

		# Express Checkout 거래 설정(판매자 사이트 리다이렉션 URL, 취소URL, 주문총액 등)
		$resArray				= hash_call("RefundTransaction",$nvpstring);

		/*
		Array 성공
		(
			[REFUNDTRANSACTIONID] => 4W602338PM5698910
			[FEEREFUNDAMT] => 0.06
			[GROSSREFUNDAMT] => 2.00
			[NETREFUNDAMT] => 1.94
			[CURRENCYCODE] => USD
			[TOTALREFUNDEDAMOUNT] => 2.00
			[TIMESTAMP] => 2016-08-18T07:01:54Z
			[CORRELATIONID] => f0764d45c87a8
			[ACK] => Success
			[VERSION] => 96.0
			[BUILD] => 24362847
			[REFUNDSTATUS] => Instant
			[PENDINGREASON] => None
		)
		Array  실패
		(
			[TIMESTAMP] => 2016-08-18T07:05:07Z
			[CORRELATIONID] => 536ad6c4a0d59
			[ACK] => Failure
			[VERSION] => 96.0
			[BUILD] => 24362847
			[L_ERRORCODE0] => 10009
			[L_SHORTMESSAGE0] => Transaction refused
			[L_LONGMESSAGE0] => This transaction has already been fully refunded
			[L_SEVERITYCODE0] => Error
			[REFUNDSTATUS] => None
			[PENDINGREASON] => None
		*/
		$ack		= strtoupper($resArray["ACK"]);

		//debug("GetExpressCheckoutDetails >>" );
		//$resArray	= hash_call("GetExpressCheckoutDetails",$nvpstr);

		if($ack != "SUCCESS"){
			$res_msg	= $resArray['L_LONGMESSAGE0'];
			$res_cd		= $resArray['L_ERRORCODE0'];
			$success	= false;
		}else{
			$res_cd		= $ack;
			$res_msg	= '';
			$success	= true;
		}

		return array(
			'success'=>$success,
			'result_code'=>$res_cd,
			'result_msg'=>$res_msg
		);

	}


	/* KCP 결제 취소 */
	public function kcp_cancel($data_order,$data_refund){
		$cancel_params = array();
		$cancel_params['req_tx']	= 'mod';
		$cancel_params['mod_type']	= $data_refund['cancel_type']=='partial' ? 'STPC' : 'STSC'; // PG 전체취소 : STSC, 신용카드 부분취소 : STPC
		$cancel_params['tno']		= $data_order['pg_transaction_number'];
		$cancel_params['mod_desc']	= $data_refund['cancel_type']=='partial' ? '부분결제취소' : '전체취소';

		// 에스크로 취소 시 타입 변경
		if($cancel_params['mod_type'] == 'STSC' && preg_match('/escrow/', $data_refund['refund_method']))
			$cancel_params['mod_type']	= 'STE2';	// 즉시취소 (배송전취소)

		if($data_refund['cancel_type']=='partial'){
			$cancel_params['mod_mny']	= get_currency_price($data_refund['refund_pg_price'],1);
			$cancel_params['rem_mny']	= $data_order['settleprice'];

			/* 기 부분매입취소된 금액 제외 */
			//동일한 결제수단으로 취소된건만 확인함 2018-03-27 jhs
			$query = "select sum(refund_pg_price) as sum_refund_price from fm_order_refund where `status`='complete' and refund_method=? and order_seq=?";
			$query = $this->db->query($query,array($data_refund['refund_method'],$data_order['order_seq']));
			$res = $query->row_array();
			if($res['sum_refund_price']){
				$cancel_params['rem_mny'] -= $res['sum_refund_price'];
			}
		}

		$pg = config_load($this->config_system['pgCompany']);

		/* bin 디렉토리 전까지의 경로를 입력,절대경로 입력 */
		$g_conf_home_dir  = dirname(__FILE__)."/../../pg/kcp/";
		/* 테스트  : testpaygw.kcp.co.kr
		 * 실결제  : paygw.kcp.co.kr */
		$g_conf_gw_url    = ($pg['mallCode']=='T0000' || $pg['mallCode']=='T0007') ? "testpaygw.kcp.co.kr" : "paygw.kcp.co.kr";
		/* 테스트  : https://pay.kcp.co.kr/plugin/payplus_test.js
		 * 실결제  : https://pay.kcp.co.kr/plugin/payplus.js */
		$g_conf_js_url	  = ($pg['mallCode']=='T0000' || $pg['mallCode']=='T0007') ? "https://pay.kcp.co.kr/plugin/payplus_test.js" : "https://pay.kcp.co.kr/plugin/payplus.js";
		/* 테스트 T0000 */
		$g_conf_site_cd   = $pg['mallCode'];
		/* 테스트 3grptw1.zW0GSo4PQdaGvsF__ */
		$g_conf_site_key  = $pg['merchantKey'];
		$g_conf_site_name = $this->config_basic['shopName'];
		$g_conf_log_level = "3";           // 변경불가
		$g_conf_gw_port   = "8090";        // 포트번호(변경불가)

		require_once dirname(__FILE__)."/../../pg/kcp/sample/pp_ax_hub_lib.php"; // library [수정불가]

		/* ============================================================================== */
		/* =   01. 취소 요청 정보 설정                                                  = */
		/* = -------------------------------------------------------------------------- = */
		$req_tx         = $cancel_params['req_tx'];					  // 요청 종류
		$cust_ip        = getenv( "REMOTE_ADDR"    ); // 요청 IP
		/* = -------------------------------------------------------------------------- = */
		$res_cd         = "";                         // 응답코드
		$res_msg        = "";                         // 응답메시지
		$res_en_msg     = "";                         // 응답 영문 메세지
		$tno            = $cancel_params['tno']; // KCP 거래 고유 번호
		/* = -------------------------------------------------------------------------- = */
		$mod_type       = $cancel_params['mod_type']; 						 // 변경TYPE VALUE 승인취소시 필요
		$mod_desc       = $cancel_params['mod_desc']; // 변경사유
		$mod_mny		= $cancel_params['mod_mny']; // 취소 요청 금액
		$rem_mny		= $cancel_params['rem_mny']; //취소 가능 잔액

		// 복합과세시 부가세 계산 @2015-06-02 pjm
		if($data_refund['free_tax'] == "y"){
			$supply = $data_refund['tax_price'];
			if($supply){
				$surtax = $supply - round($supply / 1.1);
				$supply = $supply - $surtax;
			}else{
				$supply = 0;
				$surtax = 0;
			}
			$mod_tax_mny	= $supply;							///과세금액
			$mod_vat_mny	= $surtax;							//부가세
			$mod_free_mny	= $data_refund['free_price'];		//비과세 금액
		}
		/* ============================================================================== */

		/* ============================================================================== */
		/* =   02. 인스턴스 생성 및 초기화                                              = */
		/* = -------------------------------------------------------------------------- = */
		/* =       결제에 필요한 인스턴스를 생성하고 초기화 합니다.                     = */
		/* = -------------------------------------------------------------------------- = */
		$c_PayPlus = new C_PP_CLI;

		$c_PayPlus->mf_clear();
		/* ------------------------------------------------------------------------------ */
		/* =   02. 인스턴스 생성 및 초기화 END											= */
		/* ============================================================================== */


		/* ============================================================================== */
		/* =   03. 처리 요청 정보 설정                                                  = */
		/* = -------------------------------------------------------------------------- = */

		/* = -------------------------------------------------------------------------- = */
		/* =   03-1. 승인 요청                                                          = */
		/* = -------------------------------------------------------------------------- = */
		if ( $req_tx == "mod" )
		{
			$tran_cd = "00200000";

			$c_PayPlus->mf_set_modx_data( "tno",      $tno      ); // KCP 원거래 거래번호
			$c_PayPlus->mf_set_modx_data( "mod_type", $mod_type ); // 원거래 변경 요청 종류
			$c_PayPlus->mf_set_modx_data( "mod_ip",   $cust_ip  ); // 변경 요청자 IP
			$c_PayPlus->mf_set_modx_data( "mod_desc", iconv("utf-8","euc-kr",$mod_desc) ); // 변경 사유

			if ( $mod_type == "STPC" ) // 부분취소의 경우
            {
                $c_PayPlus->mf_set_modx_data( "mod_mny", $mod_mny ); // 취소요청금액
                $c_PayPlus->mf_set_modx_data( "rem_mny", $rem_mny ); // 취소가능잔액

				//복합과세 @2015-06-02 pjm
				if($data_refund['free_tax'] == "y"){
					if(!$mod_tax_mny) $mod_tax_mny = "0";
					if(!$mod_vat_mny) $mod_vat_mny = "0";
					if(!$mod_free_mny) $mod_free_mny = "0";
					$c_PayPlus->mf_set_modx_data( "tax_flag",  "TG03");
					$c_PayPlus->mf_set_modx_data( "mod_tax_mny", $mod_tax_mny ); // 공급가 부분취소 요청금액
					$c_PayPlus->mf_set_modx_data( "mod_vat_mny", $mod_vat_mny ); // 부과세 부분취소 요청금액
					$c_PayPlus->mf_set_modx_data( "mod_free_mny", $mod_free_mny ); // 비과세 부분취소 요청금액
				}
            }
		}
		/* ------------------------------------------------------------------------------ */
		/* =   03.  처리 요청 정보 설정 END  											= */
		/* ============================================================================== */

		/* ============================================================================== */
		/* =   04. 실행                                                                 = */
		/* = -------------------------------------------------------------------------- = */
		if ( $tran_cd != "" )
		{
			$c_PayPlus->mf_do_tx( $trace_no, $g_conf_home_dir, $g_conf_site_cd, $g_conf_site_key, $tran_cd, "",
								  $g_conf_gw_url, $g_conf_gw_port, "payplus_cli_slib", $ordr_idxx,
								  $cust_ip, "3" , 0, 0, $g_conf_key_dir, $g_conf_log_dir); // 응답 전문 처리

			$success = $c_PayPlus->m_res_cd=='0000' ? true : false;
			$res_cd  = $c_PayPlus->m_res_cd;  // 결과 코드
			$res_msg = $c_PayPlus->m_res_msg; // 결과 메시지
			/* $res_en_msg = $c_PayPlus->mf_get_res_data( "res_en_msg" );  // 결과 영문 메세지 */
		}
		else
		{
			$success = false;
			$res_cd = $c_PayPlus->m_res_cd  = "9562";
			$res_msg = $c_PayPlus->m_res_msg = "연동 오류|Payplus Plugin이 설치되지 않았거나 tran_cd값이 설정되지 않았습니다.";
		}


		/* = -------------------------------------------------------------------------- = */
		/* =   04. 실행 END                                                             = */
		/* ============================================================================== */

		/* ============================================================================== */
	    /* =   05. 취소 결과 처리                                                       = */
	    /* = -------------------------------------------------------------------------- = */
	    if ( $req_tx == "mod" )
	    {
			if ( $res_cd == "0000" )
			{
				$tno = $c_PayPlus->mf_get_res_data( "tno" );  // KCP 거래 고유 번호

	    /* = -------------------------------------------------------------------------- = */
	    /* =   05-1. 부분취소 결과 처리                                                 = */
	    /* = -------------------------------------------------------------------------- = */
				if ( $mod_type == "STPC" ) // 부분취소의 경우
				{
					$amount  = $c_PayPlus->mf_get_res_data( "amount"       ); // 원 거래금액
					$mod_mny = $c_PayPlus->mf_get_res_data( "panc_mod_mny" ); // 취소요청된 금액
					$rem_mny = $c_PayPlus->mf_get_res_data( "panc_rem_mny" ); // 취소요청후 잔액
				}
			} // End of [res_cd = "0000"]

	    /* = -------------------------------------------------------------------------- = */
	    /* =   05-2. 취소 실패 결과 처리                                                = */
	    /* = -------------------------------------------------------------------------- = */
			else
			{
			}
		} // End of Process

		return array(
			'success'=>$success,
			'result_code'=>$res_cd,
			'result_msg'=>iconv('euc-kr','utf-8',$res_msg)
		);
	}

	/* LG 결제 취소 */
	public function lg_cancel($data_order,$data_refund){
		global $pg;// 전역변수 선언:XPayClinet 에서 접근 위함 @2017-07-13
		$cancel_params = array();
		$cancel_params['LGD_TXNAME']		= $data_refund['cancel_type']=='partial' ? 'PartialCancel' : 'Cancel'; // PG 전체취소 : STSC, 신용카드 부분취소 : RN07
		$cancel_params['LGD_TID']			= $data_order['pg_transaction_number'];
		$cancel_params['LGD_CANCELREASON']	= $data_refund['cancel_type']=='partial' ? '부분결제취소' : '전체취소';

		if($data_refund['cancel_type']=='partial'){
			$cancel_params['LGD_CANCELAMOUNT']	= get_currency_price($data_refund['refund_pg_price'],1);
			$cancel_params['LGD_REMAINAMOUNT']	= $data_order['settleprice']; // 취소전남은금액

			/* 기 부분매입취소된 금액 제외 */
			//동일한 결제수단으로 취소된건만 확인함 2018-03-27 jhs
			$query = "select sum(refund_pg_price) as sum_refund_price from fm_order_refund where `status`='complete' and refund_method=? and order_seq=?";
			$query = $this->db->query($query,array($data_refund['refund_method'],$data_order['order_seq']));
			$res = $query->row_array();
			if($res['sum_refund_price']){
				$cancel_params['LGD_REMAINAMOUNT'] -= $res['sum_refund_price'];
			}
		}

		$pg = config_load($this->config_system['pgCompany']);

		if( $pg['mallCode'] == 'gb_gabiatest01' )	$pg['mallCode'] = "gabiatest01";	//gabia test
		/*
	     * [결제취소 요청 페이지]
	     *
	     * LG유플러스으로 부터 내려받은 거래번호(LGD_TID)를 가지고 취소 요청을 합니다.(파라미터 전달시 POST를 사용하세요)
	     * (승인시 LG유플러스으로 부터 내려받은 PAYKEY와 혼동하지 마세요.)
	     */
	    $CST_PLATFORM               = 'service';       //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
	    $CST_MID                    = $pg['mallCode'];            //상점아이디(LG유플러스으로 부터 발급받으신 상점아이디를 입력하세요)
	                                                                         //테스트 아이디는 't'를 반드시 제외하고 입력하세요.
	    $LGD_MID                    = (("test" == $CST_PLATFORM)?"t":"").$CST_MID;  //상점아이디(자동생성)
	    $LGD_TID                	= $cancel_params["LGD_TID"];			 //LG유플러스으로 부터 내려받은 거래번호(LGD_TID)

	 	$configPath 				= dirname(__FILE__)."/../../pg/lgdacom/";	 //LG유플러스에서 제공한 환경파일("/conf/lgdacom.conf") 위치 지정.

	    require_once($configPath."XPayClient.php");

	    $xpay = new XPayClient($configPath, $CST_PLATFORM);
	    $xpay->Init_TX($LGD_MID);

	    $xpay->Set("LGD_TXNAME", $cancel_params['LGD_TXNAME']);
	    $xpay->Set("LGD_TID", $LGD_TID);

	    /* 부분취소 파라미터 */
	    if($data_refund['cancel_type']=='partial'){
			$xpay->Set("LGD_CANCELAMOUNT", $cancel_params['LGD_CANCELAMOUNT']);
			$xpay->Set("LGD_REMAINAMOUNT", $cancel_params['LGD_REMAINAMOUNT']);
			// LG 부분취소 과세/비과세 파라미터 활성화
			if(isset($data_refund['free_price']) && $data_refund['free_price']>0){	
				$xpay->Set("LGD_CANCELTAXFREEAMOUNT", $data_refund['free_price']);
			}
			$xpay->Set("LGD_CANCELREASON", iconv('utf-8','euc-kr',$cancel_params['LGD_CANCELREASON']));
			//$xpay->Set("LGD_RFACCOUNTNUM", $LGD_RFACCOUNTNUM);		//환불계좌 번호(가상계좌 환불인경우만 필수)
			//$xpay->Set("LGD_RFBANKCODE", $LGD_RFBANKCODE);			//환불계좌 은행코드(가상계좌 환불인경우만 필수)
			//$xpay->Set("LGD_RFCUSTOMERNAME", $LGD_RFCUSTOMERNAME);	//환불계좌 예금주(가상계좌 환불인경우만 필수)
			//$xpay->Set("LGD_RFPHONE", $LGD_RFPHONE);					//요청자 연락처(가상계좌 환불인경우만 필수)
		}

		if($xpay->TX())
		{
			$res_cd  = $xpay->Response_Code();  // 결과 코드
			$res_msg = $xpay->Response_Msg(); // 결과 메시지

			$success = $res_cd=='0000' ? true : false;
		}
		else
		{
			$success = false;
		}

		return array(
			'success'=>$success,
			'result_code'=>$res_cd,
			'result_msg'=>iconv('euc-kr','utf-8',$res_msg)
		);
	}

	//public function allat_

	/* Allat 결제 취소 */
	public function allat_cancel($data_order,$data_refund){

		if(!$_POST["allat_enc_data"]) {
			openDialogAlert('allat_enc_data 누락',400,140,'top','');
			exit;
		}

		$cancel_params = array();

		$pg = config_load($this->config_system['pgCompany']);

		if($pg['mallCode'] == "FM_allat_test01") $pg['mallCode'] = "allat_test01";

		// 올앳관련 함수 Include
		//----------------------
		include  dirname(__FILE__)."/../../pg/allat/allatutil.php";
		//Request Value Define
		//----------------------

		/********************* Service Code *********************/
		$at_cross_key 	= $pg['merchantKey'];    //설정필요 [사이트 참조 - http://www.allatpay.com/servlet/AllatBiz/support/sp_install_guide_scriptapi.jsp#shop]
		$at_shop_id   	= $pg['mallCode'];       //설정필요
		/*********************************************************/

		// 요청 데이터 설정
		//----------------------

		if($at_shop_id == "FM_allat_test01") $at_shop_id = "allat_test01";

		$cancel_params['allat_shop_id']		= $at_shop_id;
		$cancel_params['allat_enc_data']	= $_POST["allat_enc_data"];
		$cancel_params['allat_cross_key']	= $at_cross_key;

		$at_data_array = array();
		foreach($cancel_params as $k=>$v) $at_data_array[] = "{$k}={$v}";

		$at_data   = implode("&",$at_data_array);

		// 올앳 결제 서버와 통신 : ApprovalReq->통신함수, $at_txt->결과값
		//----------------------------------------------------------------
		$at_txt = CancelReq($at_data,"SSL");

		// 결제 결과 값 확인
		//------------------
		$REPLYCD   =getValue("reply_cd",$at_txt);
		$REPLYMSG  =getValue("reply_msg",$at_txt);

		// 결과 값이 '0000'이면 정상임. 단, allat_test_yn=Y 일경우 '0001'이 정상임.
		// 실제 취소   : allat_test_yn=N 일 경우 reply_cd=0000 이면 정상
		// 테스트 취소 : allat_test_yn=Y 일 경우 reply_cd=0001 이면 정상
		//----------------------------------------------------------------------------------------

		if( $pg['mallCode'] == 'FM_pgfreete2' ) $sucess_code = "0001";
		else $sucess_code = "0000";

		if( !strcmp($REPLYCD,$sucess_code) ){
			$success = true;
		}
		else
		{
			$success = false;
		}

		$res_cd  = $REPLYCD;  // 결과 코드
		$res_msg = $REPLYMSG; // 결과 메시지

		// 기취소건일 경우 취소 성공으로 처리
		if( $res_cd == '0505') {
			$success = true;
		}

		return array(
			'success'=>$success,
			'result_code'=>$res_cd,
			'result_msg'=>iconv('euc-kr','utf-8',$res_msg)
		);
	}

	/* INICIS 결제 취소 */
	public function inicis_cancel($data_order,$data_refund){

		$cancel_params = array();
		$cancel_params['type']		= $data_refund['cancel_type']=='partial' ? 'repay' : 'cancel'; // PG 전체취소 : STSC, 신용카드 부분취소 : RN07
		$cancel_params['tid']		= $data_order['pg_transaction_number'];
		$cancel_params['cancelmsg']	= $data_refund['cancel_type']=='partial' ? '부분결제취소' : '전체취소';

		if($data_refund['cancel_type']=='partial'){
			$cancel_params['price']	= get_currency_price($data_refund['refund_pg_price'],1);
			$cancel_params['confirm_price']	= $data_order['settleprice']-$data_refund['refund_pg_price'];

			/* 기 부분매입취소된 금액 제외 */
			//동일한 결제수단으로 취소된건만 확인함 2018-03-27 jhs
			$query = "select sum(refund_pg_price) as sum_refund_price from fm_order_refund where `status`='complete' and refund_method=? and order_seq=?";
			$query = $this->db->query($query,array($data_refund['refund_method'],$data_order['order_seq']));
			$res = $query->row_array();
			if($res['sum_refund_price']){
				$cancel_params['confirm_price'] -= $res['sum_refund_price'];
			}
		}

		$pg = config_load($this->config_system['pgCompany']);

		if(preg_match("/^escrow/",$data_order['payment'])){
			$mallCode = $pg['escrowMallCode'];
			$merchantKey = $pg['escrowMerchantKey'];
		}
		else{
			$mallCode = $pg['mallCode'];
			$merchantKey = $pg['merchantKey'];
		}

		/**************************
		 * 1. 라이브러리 인클루드 *
		 **************************/
		require_once(dirname(__FILE__)."/../../pg/inicis/libs/INILib.php");
		$HTTP_SESSION_VARS = $_SESSION;

		/***************************************
		 * 2. INIpay41 클래스의 인스턴스 생성 *
		 ***************************************/
		$inipay = new INIpay50;

		/***********************
		 * 3. 재승인 정보 설정 *
		 ***********************/
	  	$inipay->SetField("inipayhome", dirname(__FILE__)."/../../pg/inicis");  	// 이니페이 홈디렉터리(상점수정 필요)
	  	$inipay->SetField("type", $cancel_params['type']);                          // 고정 (절대 수정 불가)

		$inipay->SetField("subpgip","203.238.3.10");                    			// 고정
	  	$inipay->SetField("debug", "true");                             			// 로그모드("true"로 설정하면 상세로그가 생성됨.)
	  	$inipay->SetField("mid", $mallCode);                                 		// 상점아이디
	  	$inipay->SetField("admin", $merchantKey);    								// 키패스워드(상점아이디에 따라 변경)

	  	if($cancel_params['type']=='repay'){
		  	$inipay->SetField("pgid", "INIphpRPAY");                      	 	 	// 고정 (절대 수정 불가)
		  	$inipay->SetField("oldtid", $cancel_params['tid']);                     // 취소할 거래의 거래아이디
			$inipay->SetField("currency", 'WON');                      			 	// 화폐단위
			$inipay->SetField("price", $cancel_params['price']);                 	// 취소금액
			$inipay->SetField("confirm_price", $cancel_params['confirm_price']); 	// 승인요청금액
			$inipay->SetField("buyeremail",$data_order['order_email']);          	// 구매자 이메일 주소
			$inipay->SetField("tax",$data_refund['comm_vat_mny']);					// 부과세
			$inipay->SetField("taxfree",$data_refund['free_price']);				// 비과세
	  	}else{
	  		$inipay->SetField("tid", $cancel_params['tid']);                		// 취소할 거래의 거래아이디
			$inipay->SetField("cancelmsg", $cancel_params['cancelmsg']);    		// 취소사유
	  	}

		//$inipay->SetField("no_acct",$no_acct); //국민은행 부분취소 환불계좌번호
		//$inipay->SetField("nm_acct",$nm_acct); //국민은행 부분취소 환불계좌주명

		/******************
		 * 4. 재승인 요청 *
		 ******************/
		$inipay->startAction();


		/*********************************************************************
		 * 5. 재승인 결과                                                    *
		 *                                                                   *
		 * 신거래번호 : $inipay->getResult('TID')                            *
		 * 결과코드 : $inipay->getResult('ResultCode') ("00"이면 재승인 성공)*
		 * 결과내용 : $inipay->getResult('ResultMsg') (결과에 대한 설명)     *
		 * 원거래 번호 : $inipay->getResult('PRTC_TID')                      *
		 * 최종결제 금액 : $inipay->getResult('PRTC_Remains')                *
		 * 부분취소 금액 : $inipay->getResult('PRTC_Price')                  *
		 * 부분취소,재승인 구분값 : $inipay->getResult('PRTC_Type')          *
		 *                          ("0" : 재승인, "1" : 부분취소)           *
		 * 부분취소 요청횟수 : $inipay->getResult('PRTC_Cnt')                *
		 *********************************************************************/

		// 결제 결과 값 확인
		//------------------
		$REPLYCD   = $inipay->getResult('ResultCode');
		$REPLYMSG  = $inipay->getResult('ResultMsg');

		// 결과 값이 '0000'이면 정상임. 단, allat_test_yn=Y 일경우 '0001'이 정상임.
		// 실제 취소   : allat_test_yn=N 일 경우 reply_cd=0000 이면 정상
		// 테스트 취소 : allat_test_yn=Y 일 경우 reply_cd=0001 이면 정상
		//----------------------------------------------------------------------------------------

		if( !strcmp($REPLYCD,'00') ){
			$success = true;
		}
		else
		{
			$success = false;
		}

		$res_cd  = $REPLYCD;  // 결과 코드
		$res_msg = $REPLYMSG; // 결과 메시지

		if(iconv('euc-kr','utf-8',$res_msg)){
			$res_msg = iconv('euc-kr','utf-8',$res_msg);
		}

		return array(
			'success'=>$success,
			'result_code'=>$res_cd,
			'result_msg'	=> $res_msg
		);
	}

	public function kspay_cancel($data_order,$data_refund){

		require_once dirname(__FILE__)."/../../pg/kspay/KSPayEncApprovalCancel4.inc"; // library [수정불가]

		//ipgExec 실행파일 절대경로설정: 권한777, other에 실행권한이 필요
		//$EXEC_DIR = "/home/semuplus/public_html/pg/php_host/sample/kspay_client/ipgExec";

		//로그파일 디렉토리 절대경로설정: 권한777, other에 read,write권한이 필요
		//$LOG_DIR = "/web/okgiro/install/pg_apache/htdocs/test/log";

		$KSPAY_IPADDR	= "210.181.28.137";//운영:210.181.28.137, 테스트:210.181.28.116
		$KSPAY_PORT		= 21001;
		$cancel_type	= "0";
		$filler			= "";
		if($data_refund['cancel_type']=='partial'){
			$cancel_params['price']			= $data_refund['refund_pg_price'];
			$cancel_params['confirm_price']	= $data_order['settleprice'];

			/* 기 취소된 횟수 */
			$query		= "select count(*) as cnt from fm_order_refund where `status`='complete' and order_seq=?";
			$query		= $this->db->query($query,array($data_order['order_seq']));
			$res		= $query->row_array();
			$cancel_cnt	= $res['cnt']+1;

			$filler		= substr("00000000".$data_refund['refund_pg_price'],-9).substr("00".$cancel_cnt,-2);
			$cancel_type	= "3";
			$filler		.= str_pad("","32","0",STR_PAD_RIGHT);
			$filler .= "DF3=".$data_refund['comm_tax_mny'].":".$data_refund['comm_vat_mny'];
		}

		// Default-------------------------------------------------------
		$EncType		= "2";     // 0: 암화안함, 1:openssl, 2: seed
		$Version		= "0210";  // 전문버전
		$VersionType	= "00";    // 구분
		$Resend			= "0";     // 전송구분 : 0 : 처음,  2: 재전송

		$RequestDate	= strftime("%Y%m%d%H%M%S");
		$KeyInType		= "K";   // KeyInType 여부 : S : Swap, K: KeyInType
		$LineType		= "1";   // lineType 0 : offline, 1:internet, 2:Mobile
		$ApprovalCount	= "1";   // 복합승인갯수
		$GoodType		= "0";   // 제품구분 0 : 실물, 1 : 디지털
		$HeadFiller		= "";    // 예비
		//-------------------------------------------------------------------------------
		
		//2017-05-17 jhs PG사 정보 호출
		$pg = config_load($this->config_system['pgCompany']);
		$mallCode = $pg['mallId'];

		// Header (입력값 (*) 필수항목)--------------------------------------------------
		$StoreId		= $mallCode;     			// *상점아이디
		$OrderNumber	= ""; 						// *주문번호
		$UserName		= "";   					// *주문자명
		$IdNum			= "";       				// 주민번호 or 사업자번호
		$Email			= "";       				// *email
		$GoodName		= "";    					// *제품명
		$PhoneNo		= "";     					// *휴대폰번호
		// Header end -------------------------------------------------------------------
		
		//2017-05-17 jhs PG사 정보 매칭
		// Data Default(수정항목이 아님)-------------------------------------------------
		$ApprovalType	= "1010"; // 승인구분
		if(in_array($data_order['payment'], array("account", "virtual_account"))){		// 계좌이체 취소요청코드
			$ApprovalType = "2010";
		}
		$TransactionNo	= $data_order['pg_transaction_number'];   // 거래번호
		// Data Default end -------------------------------------------------------------


		// --------------------------------------------------------------------------------
		$ipg = new KSPayEncApprovalCancel($KSPAY_IPADDR, $KSPAY_PORT);

		$ipg->HeadMessage(
			$EncType       ,                  // 0: 암화안함, 1:openssl, 2: seed
			$Version       ,                  // 전문버전
			$VersionType   ,                  // 구분
			$Resend        ,                  // 전송구분 : 0 : 처음,  2: 재전송
			$RequestDate   ,                  // 재사용구분
			$StoreId       ,                  // 상점아이디
			$OrderNumber   ,                  // 주문번호
			$UserName      ,                  // 주문자명
			$IdNum         ,                  // 주민번호 or 사업자번호
			$Email         ,                  // email
			$GoodType      ,                  // 제품구분 0 : 실물, 1 : 디지털
			$GoodName      ,                  // 제품명
			$KeyInType     ,                  // KeyInType 여부 : S : Swap, K: KeyInType
			$LineType      ,                  // lineType 0 : offline, 1:internet, 2:Mobile
			$PhoneNo       ,                  // 휴대폰번호
			$ApprovalCount ,                  // 복합승인갯수
			$HeadFiller    );                 // 예비


		// ------------------------------------------------------------------------------
		if($cancel_type == '3'){ 
			$ipg->CancelDataMessage(
				$ApprovalType,      // ApprovalType,	: 승인구분
				$cancel_type,       // CancelType,	: 취소처리구분 1:거래번호, 2:주문번호, 3.부분취소
				$TransactionNo,     // TransactionNo,: 거래번호
				"",                 // TradeDate,	: 거래일자
				"",                 // OrderNumber,	: 주문번호
				$filler,
				"",
				""					);           // Filler)		: 기타
		} else {
			$ipg->CancelDataMessage(
				$ApprovalType,      // ApprovalType,	: 승인구분
				$cancel_type,       // CancelType,	: 취소처리구분 1:거래번호, 2:주문번호, 3.부분취소
				$TransactionNo,     // TransactionNo,: 거래번호
				"",                 // TradeDate,	: 거래일자
				"",                 // OrderNumber,	: 주문번호
				"",
				"",
				""					);           // Filler)		: 기타
		}

		$rStatus	= "X";
		$rMessage	= "C취소거절/잠시후재시도하세요";
		$success	= false;

		if ($ipg->SendEncSocket()){
		//if ($ipg->SendExecSocket($EXEC_DIR ,$LOG_DIR)){
			if (substr($ApprovalType,0,1) == "1" || substr($ApprovalType,0,1) == "I"){
				$rStatus	= $ipg->Status;//성공여부(O/X/S)
				$rMessage	= iconv('euc-kr','utf-8',$ipg->Message1) . "/"
							. iconv('euc-kr','utf-8',$ipg->Message2);
			}elseif (substr($ApprovalType,0,1) == "6"){
				$rStatus	= $ipg->VAStatus;//성공여부(O/X/S)
				$rMessage	= iconv('euc-kr','utf-8',$ipg->VAMessage1) . "/"
							. iconv('euc-kr','utf-8',$ipg->VAMessage2);
			}elseif (substr($ApprovalType,0,1) == "2"){
				$rStatus	= $ipg->ACStatus;//성공여부(O/X/S)
				$rMessage	= iconv('euc-kr','utf-8',$ipg->ACMessage1) . "/"
							. iconv('euc-kr','utf-8',$ipg->ACMessage2);
			}elseif (substr($ApprovalType,0,1) == "H"){
				$rStatus	= $ipg->HStatus;//성공여부(O/X/S)
				$rMessage	= iconv('euc-kr','utf-8',$ipg->HMessage1) . "/"
							. iconv('euc-kr','utf-8',$ipg->HMessage2);
			}elseif (substr($ApprovalType,0,1) == "M"){
				$rStatus	= $ipg->MStatus;//성공여부(O/X/S)
				$rMessage	= iconv('euc-kr','utf-8',$ipg->MRespMsg);
			}else{
				$rStatus = "X";
				$rMessage = "C취소거절/승인구분오류";
			}

			if($rStatus == "O"){
				$success = true;
			}
		}
		$resultArr	= array(
			'success'=>$success,
			'result_code'=>$rAuthNo,
			'result_msg'=>$rMessage
		);

		return array(
			'success'=>$success,
			'result_code'=>$rAuthNo,
			'result_msg'=>$rMessage
		);

	}

	public function eximbay_cancel($data_order,$data_refund){
		$this->load->helper('readurl');
		$this->load->model('paymentlog');
		$result['success'] = false;
		## 결제확인로그가져오기
		$arr_log	= $this->paymentlog->get_log($data_order['order_seq']);
		foreach($arr_log as $tmp_log){
			if($tmp_log['txntype']=='PAYMENT'&&$tmp_log['rescode']=='0000'){
				$data_log = $tmp_log;
			}
		}
		if(!$data_log){
			return $result;
		}
		if(!$data_refund['refund_price'] && $data_refund['cancel_type']=='full') $data_refund['refund_price'] = $data_order['payment_price'];
		$refundtype	='F';
		$refundamt = $data_refund['refund_pg_price'];
		if($refundamt < $data_order['settleprice']){
			$refundtype	='P';
		}
		if( in_array($data_log['mid'],array('1849705C64','3138433A69')) ){
			$reqURL = "https://secureapi.test.eximbay.com/Gateway/DirectProcessor.krp";//EXIMBAY TEST 서버 요청 URL
		}else{
			$reqURL = "https://secureapi.eximbay.com/Gateway/DirectProcessor.krp";
		}

		$balance	=  $data_log['amt'] - $refundamt;

		$params['txntype']		= 'REFUND';
		$params['charset']		= 'UTF-8';

		$params['ver']			= $data_log['ver'];
		$params['mid']			= $data_log['mid'];
		$params['ref']				= $data_log['ref'];
		$params['cur']			= $data_log['cur'];
		$params['amt']			= $data_log['amt'];
		$params['transid']		= $data_log['transid'];
		$params['lang']			= $data_log['lang'];
		$params['param1']		= $data_log['param1'];
		$params['param2']		= $data_log['param2'];
		$params['param3']		= $data_log['param3'];
		$params['fgkey']			= $data_log['fgkey'];
		$params['refundtype']	= $refundtype;
		$params['refundamt']	= $refundamt;
		$params['balance']	= $balance;
		$params['reason']		= 'cancel';
		$params['returnurl ']	= '';

		$tmp	= readurl($reqURL,$params);
		$arr	= explode('&',$tmp);
		foreach($arr as $data){
			$data = trim($data);
			if(preg_match('/rescode/',$data)){
				$result['result_code'] = str_replace('rescode=','',$data);
			}
			if(preg_match('/resmsg/',$data)){
				$result['result_msg'] = str_replace('resmsg=','',$data);
			}
		}
		if($result['result_code'] == '0000'){
			$result['success'] = true;
		}
		return $result;
	}

	/* 환불 입점사별 금액 셋팅 :: 2014-12-29 lwh */
	public function set_provider_refund($refund_code,$refundData){
		$query = "SELECT count(*) as cnt FROM fm_order_refund_provider WHERE refund_code = ? AND provider_seq = ?";
		$values	= array($refund_code,$refundData['provider_seq']);
		$query	= $this->db->query($query,$values);
		$result	= $query->row_array();

		if($result['cnt'] > 0){
			$this->db->where(array(
				'refund_code'=>$refund_code,
				'provider_seq'=>$refundData['provider_seq']
			));
			$this->db->update('fm_order_refund_provider', $refundData);
		}else{
			$refundData['refund_code']	= $refund_code;
			$this->db->insert('fm_order_refund_provider', $refundData);
		}
	}

	/* 환불 입점사별 금액 호출 :: 2014-12-29 lwh */
	public function get_provider_refund($refund_code){
		$query = "
			SELECT
				p.provider_name,
				rp.provider_seq as refund_provider_seq,
				rp.refund_expect_price as provider_refund_expect_price,
				rp.adjust_refund_price as adjust_provider_refund_price,
				rp.refund_price as provider_refund_price
			FROM
				fm_order_refund_provider	as rp,
				fm_provider					as p
			WHERE
				rp.provider_seq	= p.provider_seq AND
				rp.refund_code	= '" . $refund_code . "'
			";
		$query	= $this->db->query($query);
		if($query) $result	= $query->result_array();

		return $result;
	}


	/* 환불 입점사별 리스트 :: 2014-12-31 lwh */
	public function refund_provider_list($refund_code){

		/*
		## option
		$query	= "
			SELECT
				p.provider_seq, p.provider_name,s.shipping_seq,
				sum( IFNULL(io.price, 0) * IFNULL(ri.ea, 0) ) as price_sum,
				sum( IFNULL(io.coupon_sale, 0) + ( IFNULL(io.member_sale,0) * IFNULL(ri.ea, 0) ) + IFNULL(io.fblike_sale,0) + IFNULL(io.mobile_sale,0) + IFNULL(io.promotion_code_sale,0) + IFNULL(io.referer_sale,0) ) AS sale_sum,
				( IFNULL(s.shipping_cost, 0) + ifnull(i.goods_shipping_cost,0)) as shipping_cost_sum,
				sum( IFNULL(ri.ea, 0) ) as refund_ea,
				sum( IFNULL(io.unit_emoney,0) * IFNULL(ri.ea, 0) ) as emoney_sum
			FROM
				fm_order_refund				as r,
				fm_order_refund_item		as ri,
				fm_order_item_option		as io,
				fm_order_item				as i,
				fm_provider					as p,
				fm_order_shipping			as s
			WHERE
				r.refund_code		= ri.refund_code AND
				ri.option_seq		= io.item_option_seq AND
				ri.item_seq			= io.item_seq AND
				io.item_seq			= i.item_seq AND
				i.provider_seq		= p.provider_seq AND
				i.shipping_seq		= s.shipping_seq AND
				r.refund_code		= ?
			GROUP BY
				i.provider_seq
			";

		$values	= array($refund_code);
		$query	= $this->db->query($query,$values);
		if($query) $result	= $query->result_array();

		## suboption 2015-03-11 pjm 추가
		$sub_query	= "
			SELECT
				p.provider_seq, p.provider_name,s.shipping_seq,
				sum( IFNULL(io.price, 0) * IFNULL(ri.ea, 0) ) as price_sum,
				sum(  IFNULL(io.member_sale,0) * IFNULL(ri.ea, 0) ) AS sale_sum,
				( IFNULL(s.shipping_cost, 0) + ifnull(i.goods_shipping_cost,0)) as shipping_cost_sum,
				sum( IFNULL(ri.ea, 0) ) as refund_ea,
				sum( IFNULL(io.unit_emoney,0) * IFNULL(ri.ea, 0) ) as emoney_sum
			FROM
				fm_order_refund				as r,
				fm_order_refund_item		as ri,
				fm_order_item_suboption		as io,
				fm_order_item				as i,
				fm_provider					as p,
				fm_order_shipping			as s
			WHERE
				r.refund_code		= ri.refund_code AND
				ri.suboption_seq	= io.item_suboption_seq AND
				ri.item_seq			= io.item_seq AND
				io.item_seq			= i.item_seq AND
				i.provider_seq		= p.provider_seq AND
				i.shipping_seq		= s.shipping_seq AND
				r.refund_code		= ?
			GROUP BY
				i.provider_seq
			";
		$sub_query	= $this->db->query($sub_query,$values);
		if($sub_query) $sub_result	= $sub_query->result_array();
		if($result){
			## 필수옵션 취소시 서브옵션 합치기
			foreach($result as $v){
				foreach($sub_result as $v2){
					if($v['provider_seq'] == $v2['provider_seq']){
						$v['price_sum'] += $v2['price_sum'];
						$v['sale_sum']	+= $v2['sale_sum'];
						$v['refund_ea'] += $v2['refund_ea'];
						$v['emoney_sum'] += $v2['emoney_sum'];
					}
				}
				$loop[] = $v;
			}
		}else{
			## 추가옵션만 취소 했을때
			$loop = $sub_result;
		}
		*/

		## 입점사별 환불액 2015-03-19 pjm
		$query = "select
					provider_seq
					,provider_name
					,shipping_seq
					,sum(price_sum) as price_sum
					,sum(sale_sum) as sale_sum
					,(shipping_cost_sum) as shipping_cost_sum
					,sum(refund_ea) as refund_ea
					,sum(emoney_sum) as emoney_sum
				from (
				(
					select
						p.provider_seq, p.provider_name,s.shipping_seq,
						sum( IFNULL(io.price, 0) * IFNULL(ri.ea, 0) ) as price_sum,
						sum( IFNULL(io.unit_ordersheet, 0) + sum( IFNULL(io.coupon_sale, 0) + ( IFNULL(io.member_sale,0) * IFNULL(ri.ea, 0) ) + IFNULL(io.fblike_sale,0) + IFNULL(io.mobile_sale,0) + IFNULL(io.promotion_code_sale,0) + IFNULL(io.referer_sale,0) ) AS sale_sum,
						( IFNULL(s.shipping_cost, 0) + ifnull(i.goods_shipping_cost,0)) as shipping_cost_sum,
						sum( IFNULL(ri.ea, 0) ) as refund_ea,
						sum( IFNULL(io.unit_emoney,0) * IFNULL(ri.ea, 0) ) as emoney_sum
					from
						fm_order_refund_item as ri
						left join fm_order_refund as r on r.refund_code=ri.refund_code
						,fm_order_item_option as io
						left join fm_order_item	as i on 	io.item_seq = i.item_seq
						left join fm_provider as p on p.provider_seq=i.provider_seq
						left join fm_order_shipping as s on s.shipping_seq=i.shipping_seq
					where
						io.item_option_seq=ri.option_seq and io.item_seq=ri.item_seq
						and ri.refund_code=?
					GROUP BY
						i.provider_seq
				) union all (
					select
						p.provider_seq, p.provider_name,s.shipping_seq,
						sum( IFNULL(io.price, 0) * IFNULL(ri.ea, 0) ) as price_sum,
						sum(  IFNULL(io.member_sale,0) * IFNULL(ri.ea, 0) ) AS sale_sum,
						( IFNULL(s.shipping_cost, 0) + ifnull(i.goods_shipping_cost,0)) as shipping_cost_sum,
						sum( IFNULL(ri.ea, 0) ) as refund_ea,
						sum( IFNULL(io.unit_emoney,0) * IFNULL(ri.ea, 0) ) as emoney_sum
					from
						fm_order_refund_item as ri
						left join fm_order_refund as r on r.refund_code=ri.refund_code
						,fm_order_item_suboption as io
						left join fm_order_item	as i on 	io.item_seq = i.item_seq
						left join fm_provider as p on p.provider_seq=i.provider_seq
						left join fm_order_shipping as s on s.shipping_seq=i.shipping_seq
					where
						io.item_suboption_seq=ri.suboption_seq and io.item_seq=ri.item_seq
						and ri.refund_code=?
					GROUP BY
						i.provider_seq

				)
				) as k group by provider_seq
				";

		$values	= array($refund_code,$refund_code);
		$query	= $this->db->query($query,$values);
		if($query) $result	= $query->result_array();

		return $result;
	}

	/* 페이코 결제 취소 :: 2018-09-20 lwh */
	public function payco_cancel($data_order,$data_refund){
		$this->load->helper('readurl');

		// 취소 금액 계산
		$TotalAmt		= $data_order['settleprice'];
		$cancel_type	= $data_refund['cancel_type']=='partial' ? 'partial' : 'full';
		if($cancel_type == 'full'){
			$CancelAmt	= $data_order['settleprice'];
			$CancelCnt	= '';
		}else{
			$CancelAmt	= $data_refund['refund_pg_price'];

			/* 기 부분매입취소된 금액 제외 */
			$query = "select sum(refund_pg_price) as sum_refund_price, count(*) as cancle_cnt from fm_order_refund where `status`='complete' and order_seq=?";
			$query = $this->db->query($query,array($data_order['order_seq']));
			$res = $query->row_array();
			if($res['sum_refund_price']){
				$TotalAmt -= $res['sum_refund_price'];
			}
			// 취소금액의 최대값 설정
			if($CancelAmt > $TotalAmt && $res['cancle_cnt'] > 0)
				$CancelAmt = $TotalAmt;

			$CancelCnt	= (int)$res['cancle_cnt'] + 1;
		}

		// 가격 검증
		if	($data_order['pg_currency'] == 'KRW') $CancelAmt	= floor($CancelAmt);

		// 인증 관련 호출 전용 값 호출
		$pg_param = config_load('payco');

		// body data 추출
		$body_data['sellerKey']			= $pg_param['sellerKey'];			// 가맹점 코드
		$body_data['orderNo']			= $data_order['pg_log']['tno'];		// 결제 고유번호
		$body_data['orderCertifyKey']	= $data_order['pg_log']['app_no'];	// 결제승인 인증번호

		//과세/비과세 공급가액/부가세 계산
		$mod_tax_mny	= '0';	// 과세금액
		$mod_vat_mny	= '0';	// 부가세
		$mod_free_mny	= '0';	// 비과세

		// refund_process->save() 에서 미리 계산한 경우.
		if(isset($data_refund['comm_tax_mny']) && isset($data_refund['comm_vat_mny']) && isset($data_refund['free_price'])){
			$mod_tax_mny	= floor($data_refund['comm_tax_mny']);	// 과세금액
			$mod_vat_mny	= floor($data_refund['comm_vat_mny']);	// 부가세
			$mod_free_mny	= floor($data_refund['free_price']);	// 비과세
		}else{
			$order_cfg = ($this->cfg_order) ? $this->cfg_order : config_load('order');
			$vat = $order_cfg['vat'] ? $order_cfg['vat'] : 10;

			// 취소금액을 임의로 과세 금액으로 설정
			$tax_price			= ($data_refund['tax_price']) ? $data_refund['tax_price'] : $CancelAmt;

			// 비과세 계산
			if ($data_refund['free_price']){				// 비과세 금액
				$mod_free_mny = $data_refund['free_price'];
			}else if($data_order['freeprice']){
				$mod_free_mny	= (int)$data_order['freeprice'];
				$tax_price	= $tax_price - $mod_free_mny;
			}else{
				$mod_free_mny = 0;
			}

			// 취소금액 중 비과세 금액 제외 후 부과세 계산
			if($tax_price){
				$sum_price		= $tax_price;
				$supply		= get_cutting_price($sum_price / (1 + ($vat / 100)));
				$surtax = (int) ($sum_price - $supply);
			}else{
				$supply = 0;
				$surtax = 0;
			}
			
			$mod_tax_mny	= $tax_price;					// 과세금액
			$mod_vat_mny	= $surtax;						// 부과세
			$SupplyAmt		= $mod_tax_mny + $mod_free_mny;	// 공급가액
		}

		$body_data['cancelTotalAmt']			= $CancelAmt;		// 취소 금액
		$body_data['totalCancelVatAmt']			= $mod_vat_mny;		// 부과세 금액
		$body_data['totalCancelTaxfreeAmt']		= $mod_free_mny;	// 비과세 금액
		$body_data['totalCancelTaxableAmt']		= $CancelAmt - $mod_vat_mny - $mod_free_mny; // 과세 금액

		// 샵정보 추가
		$shop_info['shopSno']	= $this->config_system['shopSno'];
		$shop_info['domain']	= get_connet_protocol().$_SERVER['HTTP_HOST']; //가맹점 도메인 입력
		$body_param['s_info']	= $shop_info;

		// API 통신
		$body_param['api_type']	= 'cancel';
		$body_param['params']	= $body_data;
		$call_url	= 'https://payco.firstmall.kr/payco_hub.php';
		$json_data	= readurl($call_url,$body_param,false,$this->timeout);
		$read_data	= json_decode($json_data,true);

		$respons	= $read_data['result'];

		// 결과값 수신
		if($read_data['httpCode'] == '200' && $respons['result']['cancelTradeSeq']){
			$success	= true;
			$resultCode	= '200';
			$resultMsg	= '취소성공';
		}else{
			$success	= false;
			$resultCode	= $respons['code'];
			$resultMsg	= str_replace("'", "`",$respons['message']);
		}

		return array(
			'success'		=> $success,
			'result_code'	=> $resultCode,
			'result_msg'	=> $resultMsg
		);
	}

	/* 카카오페이 결제 취소 - 구버전과 분기처리 :: 2017-12-18 lwh */
	public function kakaopay_cancel($data_order,$data_refund){
		if($this->config_system['not_use_daumkakaopay'] == 'n' && $data_order['pg_log']['biller'] == 'kakao'){
			$return = $this->daum_kakaopay_cancel($data_order,$data_refund);
		}else{
			$return = $this->old_kakaopay_cancel($data_order,$data_refund);
		}

		return $return;
	}

	/* 다음카카오페이 결제 취소 :: 2017-12-18 lwh */
	public function daum_kakaopay_cancel($data_order,$data_refund){
		$this->load->helper('readurl');

		// 취소 금액 계산
		$TotalAmt		= $data_order['settleprice'];
		$cancel_type	= $data_refund['cancel_type']=='partial' ? 'partial' : 'full';
		if($cancel_type == 'full'){
			$CancelAmt	= $data_order['settleprice'];
			$CancelCnt	= '';
		}else{
			$CancelAmt	= $data_refund['refund_pg_price'];

			/* 기 부분매입취소된 금액 제외 */
			$query = "select sum(refund_pg_price) as sum_refund_price, count(*) as cancle_cnt from fm_order_refund where `status`='complete' and order_seq=?";
			$query = $this->db->query($query,array($data_order['order_seq']));
			$res = $query->row_array();
			if($res['sum_refund_price']){
				$TotalAmt -= $res['sum_refund_price'];
			}
			// 취소금액의 최대값 설정
			if($CancelAmt > $TotalAmt && $res['cancle_cnt'] > 0)
				$CancelAmt = $TotalAmt;

			$CancelCnt	= (int)$res['cancle_cnt'] + 1;
		}

		// 가격 검증
		if	($data_order['pg_currency'] == 'KRW') $CancelAmt	= floor($CancelAmt);

		// 인증 관련 호출 전용 값 호출
		$pg_param = config_load('daumkakaopay');

		// body data 추출
		$body_data['cid']			= $pg_param['cid'];				// 가맹점 코드
		$body_data['tid']			= $data_order['pg_log']['tno'];	// 결제 고유번호

		//과세/비과세 공급가액/부가세 계산
		$mod_tax_mny	= '0';	// 과세금액
		$mod_vat_mny	= '0';	// 부가세
		$mod_free_mny	= '0';	// 비과세

		// refund_process->save() 에서 미리 계산한 경우.
		if(isset($data_refund['comm_tax_mny']) && isset($data_refund['comm_vat_mny']) && isset($data_refund['free_price'])){
			$mod_tax_mny	= floor($data_refund['comm_tax_mny']);	// 과세금액
			$mod_vat_mny	= floor($data_refund['comm_vat_mny']);	// 부가세
			$mod_free_mny	= floor($data_refund['free_price']);	// 비과세
		}else{
			$order_cfg = ($this->cfg_order) ? $this->cfg_order : config_load('order');
			$vat = $order_cfg['vat'] ? $order_cfg['vat'] : 10;

			// 취소금액을 임의로 과세 금액으로 설정
			$tax_price			= ($data_refund['tax_price']) ? $data_refund['tax_price'] : $CancelAmt;

			// 비과세 계산
			if ($data_refund['free_price']){				// 비과세 금액
				$mod_free_mny = $data_refund['free_price'];
			}else if($data_order['freeprice']){
				$mod_free_mny	= (int)$data_order['freeprice'];
				$tax_price	= $tax_price - $mod_free_mny;
			}else{
				$mod_free_mny = 0;
			}

			// 취소금액 중 비과세 금액 제외 후 부과세 계산
			if($tax_price){
				
				$sum_price		= $tax_price;
				$supply			= round($sum_price / (1 + ($vat / 100)));		//부가세 계산은 무조건 round 처리(주문시도 동일)
				$surtax			= (int) ($sum_price - $supply);
			}else{
				$supply = 0;
				$surtax = 0;
			}
			$mod_vat_mny	= $surtax;						// 부과세

			// 다음 카카오에서는 미사용
			// $mod_tax_mny	= $tax_price;						// 과세금액
			// $SupplyAmt		= $mod_tax_mny + $mod_free_mny;	// 공급가액
		}

		$body_data['cancel_amount']				= $CancelAmt;		// 취소 금액
		if($mod_vat_mny > 0) $body_data['cancel_vat_amount']		= $mod_vat_mny;		// 부과세 금액
		$body_data['cancel_tax_free_amount']	= $mod_free_mny;	// 비과세 금액

		// 샵정보 추가
		$shop_info['shopSno']	= $this->config_system['shopSno'];
		$shop_info['domain']	= "http://".$_SERVER['HTTP_HOST']; //가맹점 도메인 입력
		$body_param['s_info']	= $shop_info;

		// API 통신
		$body_param['api_type']	= 'cancel';
		$body_param['params']	= $body_data;
		$call_url	= 'https://kakaopay.firstmall.kr/kakaopay_hub.php';
		$json_data	= readurl($call_url,$body_param,false,$this->timeout);
		$read_data	= json_decode($json_data,true);
		$respons	= $read_data['result'];

		// 결과값 수신
		if($read_data['httpCode'] == '200' && $respons['cid'] == $pg_param['cid'] && $respons['tid'] == $data_order['pg_log']['tno']){
			$success	= true;
			$resultCode	= '200';
			$resultMsg	= '취소성공';
		}else{
			$success	= false;
			$resultCode	= $respons['code'];
			$resultMsg	= str_replace("'", "`",$respons['msg']);
			if($respons['extras']){
				$resultMsg = $resultMsg . ' (' . str_replace("'", "`", $respons['extras']['method_result_message']) . ':' . $respons['extras']['method_result_code'] . ')';
			}
		}

		return array(
			'success'		=> $success,
			'result_code'	=> $resultCode,
			'result_msg'	=> $resultMsg
		);
	}

	/* 카카오페이 결제 취소 :: 2015-02-25 lwh */
	public function old_kakaopay_cancel($data_order,$data_refund){

		//## 1. 라이브러리 인클루드
		require_once("./pg/kakaopay/conf_inc.php");
		require_once("./pg/kakaopay/libs/lgcns_CNSpay.php");

		// 취소 금액 계산
		$TotalAmt		= $data_order['settleprice'];
		$cancel_type	= $data_refund['cancel_type']=='partial' ? 'partial' : 'full';
		if($cancel_type == 'full'){
			$CancelAmt	= $data_order['settleprice'];
			$CancelCnt	= '';
		}else{
			$CancelAmt	= $data_refund['refund_pg_price'];

			/* 기 부분매입취소된 금액 제외 */
			$query = "select sum(refund_pg_price) as sum_refund_price, count(*) as cancle_cnt from fm_order_refund where `status`='complete' and order_seq=?";
			$query = $this->db->query($query,array($data_order['order_seq']));
			$res = $query->row_array();
			if($res['sum_refund_price']){
				$TotalAmt -= $res['sum_refund_price'];
			}
			// 취소금액의 최대값 설정
			if($CancelAmt > $TotalAmt && $res['cancle_cnt'] > 0)
				$CancelAmt = $TotalAmt;

			$CancelCnt	= (int)$res['cancle_cnt'] + 1;
		}

		## 가격 검증
		if	($data_order['pg_currency'] == 'KRW') $CancelAmt	= floor($CancelAmt);

		// 인증 관련 TXN_ID 호출 전용 값 호출
		$pg_param = config_load('kakaopay');

		//## 2. 취소 요청 파라미터 구성
		$cancleParam['MID']					= $pg_param['mid'];
															// 가맹점 ID
		$cancleParam['TID']					= $data_order['pg_log']['tno'];
															// 카카오페이 거래번호
		$cancleParam['CancelAmt']			= $CancelAmt;	// 취소 금액

		//과세/비과세 공급가액/부가세 계산 @2017-01-23
		$mod_tax_mny		= "0";// 공급가
		$mod_free_mny	= "0";// 비과세
		$mod_vat_mny	= "0";// 부과세
		$supply = $data_refund['tax_price'];
		if($supply){
			$surtax = $supply - round($supply / 1.1);
			$supply = $supply - $surtax;
		}else{
			$supply = 0;
			$surtax = 0;
		}
		$mod_tax_mny		= $supply;							///과세금액
		$mod_free_mny	= $data_refund['free_price'];	//비과세 금액
		$SupplyAmt = $mod_tax_mny+$mod_free_mny;		// 공급가액

		$mod_vat_mny	= $surtax;							//부가세
		$cancleParam['SupplyAmt']			= $SupplyAmt;			// 공급가액
		$cancleParam['GoodsVat']			= $mod_vat_mny;	// 부가세

		$cancleParam['ServiceAmt']			= 0;			// 봉사료
		$cancleParam['CancelMsg']			= ($data_refund['refund_reason']) ? $data_refund['refund_reason'] : '사유없음';
															// 취소사유
		$cancleParam['PartialCancelCode']	=
			($data_refund['cancel_type']=='full') ? '0' : 1;
															// 취소단위 : 0-전체, 1-부분
		$cancleParam['CancelIP']			= $_SERVER['REMOTE_ADDR'];
															// 취소요청자IP
		$cancleParam['CancelNo']			= $CancelCnt;	// 취소번호
		$cancleParam['PayMethod']			= 'CARD';		// 결제타입

		//## 3. 데이터 요청
		$connector = new CnsPayWebConnector($LogDir);
		$connector->CnsActionUrl("https://".$CnsPayDealRequestUrl);
		$connector->CnsPayVersion($phpVersion);

		$connector->setRequestData($cancleParam);

		$connector->addRequestData("actionType", "CL0");
		$connector->addRequestData("CancelPwd", $pg_param['cancelPwd']);
		$connector->addRequestData("CancelIP", $_SERVER['REMOTE_ADDR']);

		//가맹점키 셋팅 (MID 별로 틀림)
		$connector->addRequestData("EncodeKey", $pg_param['merchantKey']);

		//## 4. CNSPAY Lite 서버 접속하여 처리
		$connector->requestAction();

		//## 5. 결과 처리
		$resultCode = $connector->getResultData("ResultCode");
		// 결과코드 (정상 :2001(취소성공), 2002(취소진행중), 그 외 에러)
		$resultMsg	= $connector->getResultData("ResultMsg");		// 결과메시지
		$cancelAmt	= $connector->getResultData("CancelAmt");		// 취소금액
		$cancelDate = $connector->getResultData("CancelDate");		// 취소일
		$cancelTime = $connector->getResultData("CancelTime");		// 취소시간
		$payMethod	= $connector->getResultData("PayMethod");		// 취소 결제수단
		$mid		= $connector->getResultData("MID");				// 가맹점 ID
		$tid		= $connector->getResultData("TID");				// TID
		$errorCD	= $connector->getResultData("ErrorCD");        	// 상세 에러코드
		$errorMsg	= $connector->getResultData("ErrorMsg");      	// 상세 에러메시지
		$authDate	= $cancelDate . $cancelTime;					// 취소거래시간
		$ccPartCl	= $connector->getResultData("CcPartCl");		// 부분취소 가능여부 (0:부분취소불가, 1:부분취소가능)
		$stateCD = $connector->getResultData("StateCD");			// 거래상태코드 (0: 승인, 1:전취소, 2:후취소)
		$authDate = $connector->makeDateString($authDate);
		$errorMsg = iconv("euc-kr", "utf-8", $errorMsg);
		$resultMsg = iconv("euc-kr", "utf-8", $resultMsg);

		if($resultCode == '2001'){
			$success	= true;
		}else{
			$success	= false;
		}

		return array(
			'success'		=> $success,
			'result_code'	=> $resultCode,
			'result_msg'	=> $resultMsg
		);
	}

	/* 배송그룹내 마지막 환불 코드 :: 2015-03-12 pjm */
	public function shipping_refund_maxcode($order_seq,$shipping_seq){
		$query = "
			select
				max(refund_code) as refund_code
			from (
				(select
					re.refund_code,opt.provider_seq,opt.shipping_seq,opt.ea
				from
					fm_order_refund_item as rei
					, fm_order_refund as re
					, fm_order_item_option as opt
				where
					rei.refund_code=re.refund_code
					and opt.item_option_seq=rei.option_seq
					and re.order_seq='".$order_seq."'
					and opt.shipping_seq='".$shipping_seq."'
				) union
				(select
					re.refund_code,opt.provider_seq,opt.shipping_seq,sub.ea
				from
					fm_order_refund_item as rei
					, fm_order_refund as re
					, fm_order_item_suboption as sub left join fm_order_item_option as opt on opt.item_option_seq=sub.item_option_seq and opt.item_seq=sub.item_seq
				where
					rei.refund_code=re.refund_code
					and sub.item_suboption_seq=rei.suboption_seq
					and re.order_seq='".$order_seq."'
					and opt.shipping_seq='".$shipping_seq."'
				)
			) as k
		";
		$query	= $this->db->query($query);
		if($query) $result	= $query->result_array();

		return $result[0]['refund_code'];
	}

	/* 동일 배송그룹 내 미반품정보 :: 2018-07-20 pjw */
	public function shipping_unrefund_order($shipping_seq){

		// 주문 내 총 상품 개수
		$query = "SELECT SUM( total_ea.ea ) AS sum_ea
				FROM (
					
					SELECT b.ea
					FROM fm_order_item as a
					LEFT JOIN fm_order_item_option as b
					ON a.item_seq = b.item_seq
					WHERE a.shipping_seq = '".$shipping_seq."'
					UNION ALL 
					SELECT b.ea
					FROM fm_order_item as a
					LEFT JOIN fm_order_item_suboption as b
					ON a.item_seq = b.item_seq
					WHERE a.shipping_seq = '".$shipping_seq."'
				) AS total_ea";

		$query	= $this->db->query($query);
		if($query) $result	= $query->result_array();
		$total_ea = $result[0]['sum_ea'];
		
		// 주문 내 반품개수
		$query = "SELECT SUM( reti.ea ) AS sum_ea
				FROM fm_order_item as ord
				LEFT JOIN fm_order_return_item AS reti 
				ON ord.item_seq = reti.item_seq
				WHERE ord.shipping_seq = '".$shipping_seq."'";

		$query	= $this->db->query($query);
		if($query) $result	= $query->result_array();
		$total_refund_ea = $result[0]['sum_ea'];

		
		// 주문 내 기환불개수
		$query = "SELECT SUM( reri.ea ) AS sum_ea
				FROM fm_order_item as ordi
					LEFT JOIN fm_order_refund AS rer 
						ON ordi.order_seq = rer.order_seq AND rer.status = 'complete'
					LEFT JOIN fm_order_refund_item AS reri 
						ON ordi.item_seq = reri.item_seq AND rer.refund_code = reri.refund_code
				WHERE ordi.shipping_seq = '".$shipping_seq."'";

		$query	= $this->db->query($query);
		if($query) $result	= $query->result_array();
		$total_refund_ea_complete = ($result[0]['sum_ea'])?$result[0]['sum_ea']:0;


		return array(
			'total_ea'			=> $total_ea,
			'total_refund_ea'	=> $total_refund_ea,
			'total_unrefund_ea' => $total_ea - $total_refund_ea,
			'total_refund_ea_complete' => $total_refund_ea_complete,
		);
	}

	/* 배송그룹내 마지막 환불 코드 (판매자책임인 경우만) :: 2018-07-20 pjw */
	public function shipping_refund_maxcode_duty($order_seq){
		$query = "
			SELECT MAX( rf.refund_code ) AS refund_code
			FROM fm_order_refund AS rf
			INNER JOIN fm_order_return AS rt ON rf.refund_code = rt.refund_code
			WHERE rf.order_seq =  ?
			AND rt.refund_ship_duty ='seller'
		";

		$query	= $this->db->query($query, $order_seq);
		$result	= $query->row_array();

		return $result['refund_code'];
	}

	/* 배송그룹별 환불수량 :: 2018-07-19 pjw */
	public function shipping_refund_ea($shipping_seq){
		$que = "
			select sum(ifnull(ea,0)) as ea,sum(ifnull(cancel_ea,0)) as cancel_ea ,sum(ifnull(deliv_ea,0)) as deliv_ea  from
				(
					(
					select
						opt.ea,
						opt.step85 as cancel_ea,
						(opt.step45+opt.step55+opt.step65+opt.step75) as deliv_ea
					from
						fm_order_item as item, fm_order_item_option as opt
					where
						item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
						and item.shipping_seq=?
					)
					union all
					(
					select
						opt.ea,
						opt.step85 as cancel_ea,
						(opt.step45+opt.step55+opt.step65+opt.step75) as deliv_ea
					from
						fm_order_item as item, fm_order_item_suboption as opt
					where
						item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
						and item.shipping_seq=?
					)
				) as k
		";
		$query	= $this->db->query($que, array($shipping_seq,$shipping_seq));
		if($query){
			$rest_ea_data	= $query->row_array();
			## 배송그룹내 남은 상품 수량 - 출고수량 - 취소수량
			$rest_ea_data['rest_ea'] = $rest_ea_data['ea'] - $rest_ea_data['deliv_ea'] - $rest_ea_data['cancel_ea'];
		}

		return $rest_ea_data;
	}

	/* 배송그룹별 주문수량, 취소수량, 배송수량  :: 2015-03-12 pjm */
	public function shipping_order_ea($shipping_seq){
		$que = "
			select sum(ifnull(ea,0)) as ea,sum(ifnull(cancel_ea,0)) as cancel_ea ,sum(ifnull(deliv_ea,0)) as deliv_ea  from
				(
					(
					select
						opt.ea,
						opt.step85 as cancel_ea,
						(opt.step45+opt.step55+opt.step65+opt.step75) as deliv_ea
					from
						fm_order_item as item, fm_order_item_option as opt
					where
						item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
						and item.shipping_seq=?
					)
					union all
					(
					select
						opt.ea,
						opt.step85 as cancel_ea,
						(opt.step45+opt.step55+opt.step65+opt.step75) as deliv_ea
					from
						fm_order_item as item, fm_order_item_suboption as opt
					where
						item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
						and item.shipping_seq=?
					)
				) as k
		";

		$query	= $this->db->query($que, array($shipping_seq,$shipping_seq));
		if($query){
			$rest_ea_data	= $query->row_array();
			## 배송그룹내 남은 상품 수량 - 출고수량 - 취소수량
			$rest_ea_data['rest_ea'] = $rest_ea_data['ea'] - $rest_ea_data['deliv_ea'] - $rest_ea_data['cancel_ea'];
		}

		return $rest_ea_data;
	}

	// 환불리스트 쿼리 분리 :: 2017-09-07 lwh
	public function get_refund_catalog_query($_PARAM){
		$page				= (trim($_PARAM['page'])) ? trim($_PARAM['page']) : 1;
		$nperpage			= 20;
		$limit_s			= ($page - 1) * $nperpage;
		$limit_e			= $nperpage;

		$record				= "";

		// 검색어
		if( $_PARAM['keyword'] ){

			$keyword_type = preg_replace("/[^a-z_.]/i","",trim($_PARAM['keyword_type']));
			$keyword = str_replace("'","\'",trim($_PARAM['keyword']));

			if($keyword_type){
				$where[] = "{$keyword_type} = '" . $keyword . "'";
			// 검색어가 주문번호 일 경우
			}else if( preg_match('/^([0-9]{19})$/',$keyword) ){
				$where[] = "ref.order_seq = '" . $keyword . "'";

			// 검색어가 출고번호 일 경우
			}else if(preg_match('/^([D0-9]{9,11})$/',$keyword)){
				$where[] = "ref.order_seq = (SELECT order_seq FROM fm_goods_export WHERE export_code = '" . $keyword . "')";
			// 검색어가 반품번호 일 경우
			}else if(preg_match('/^([R0-9]{9,11})$/',$keyword)){
				$where[] = "ref.return_code = '" . $keyword . "'";
			// 검색어가 환불번호 일 경우
			}else if(preg_match('/^([C0-9]{9,11})$/',$keyword)){
				$where[] = "ref.refund_code = '" . $keyword . "'";
			}else{

				$where[] = "
				(
					mem.user_name like '%" . $keyword . "%' OR
					bus.bname like '%" . $keyword . "%' OR
					ord.order_user_name  like '%" . $keyword . "%' OR
					ord.depositor like '%" . $keyword . "%' OR
					ord.order_email like '%" . $keyword . "%' OR
					ord.order_phone like '%" . $keyword . "%' OR
					ord.order_cellphone like '%" . $keyword . "%' OR
					mem.userid like '%" . $keyword . "%' OR
					ref.refund_code like '%" . $keyword . "%' OR
					EXISTS (
						SELECT shipping_seq FROM fm_order_shipping WHERE order_seq = ord.order_seq and (
							recipient_phone LIKE '%" . $keyword . "%' OR
							recipient_cellphone LIKE '%" . $keyword . "%' OR
							recipient_user_name LIKE '%" . $keyword . "%')
					) OR
					EXISTS (
						SELECT
							item_seq
						FROM fm_order_item WHERE order_seq = ord.order_seq and goods_name LIKE '%" . $keyword . "%'
					)
				)
				";
			}

		}

		// 주문일
		$date_field = $_PARAM['date_field'] ? $_PARAM['date_field'] : 'ref.regist_date';
		if($_PARAM['sdate']){
			$where[] = $date_field." >= '".$_PARAM['sdate']." 00:00:00'";
		}
		if($_PARAM['edate']){
			$where[] = $date_field." <= '".$_PARAM['edate']." 24:00:00'";
		}

		// 주문상태
		if( $_PARAM['query_type'] == 'summary' && $_PARAM['summary_type']){
			$where[] = "ref.status = '" . $_PARAM['summary_type'] . "'";
		}else if( $_PARAM['refund_status'] ){
			$arr = array();
			foreach($_PARAM['refund_status'] as $key => $data){
				$arr[] = "ref.status = '" . $data . "'";
			}
			$where[] = "(".implode(' OR ',$arr).")";
		}

		# npay 취소요청건 조회
		if($_PARAM['search_npay_order_cancel']){
			$where[] = "ref.npay_order_id != '' and ref.status = 'request'";
		}

		$sqlWhereClause = $where ? " WHERE ".implode(' AND ',$where) : "";

		// 입점사 조회
		if($_PARAM['provider_seq']){
			$providerJoin	= "
				LEFT JOIN fm_order_item orditem ON (orditem.order_seq = ord.order_seq and item.item_seq=orditem.item_seq)
			";
			if($sqlWhereClause)
				$sqlWhereClause	.= " AND orditem.provider_seq = '".$_PARAM['provider_seq']."'";
			else
				$sqlWhereClause	= " WHERE orditem.provider_seq = '".$_PARAM['provider_seq']."'";
		}

		$sqlForm	= "
			fm_order_refund as ref
			LEFT JOIN fm_order as ord on ref.order_seq = ord.order_seq
			LEFT JOIN fm_order_refund_item as item on ref.refund_code=item.refund_code
			{$providerJoin}
			LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
			LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
		";
		
		if			($_PARAM['query_type'] == 'total_record'){
			$query = "
			SELECT COUNT(tmp.refund_seq) AS cnt
			FROM
				(
				SELECT 
					ref.*
				FROM
					{$sqlForm}
				{$sqlWhereClause}
				GROUP BY ref.refund_code
				) AS tmp
			";
		}else if	($_PARAM['query_type'] == 'summary'){
			$query = "
			SELECT 
				ref.refund_seq, ref.refund_price, ref.status,
				sum(item.refund_delivery_price) as refund_delivery_price,
				sum(item.refund_delivery_cash) as refund_delivery_cash,
				sum(item.refund_delivery_emoney) as refund_delivery_emoney
			FROM
				{$sqlForm}
			{$sqlWhereClause}
			GROUP BY ref.refund_code
				
			";
		}else{
			$query = "
			SELECT 
				ord.*,ref.*,
				ord.payment,
				(
					SELECT userid FROM fm_member WHERE member_seq=ord.member_seq
				) userid,
				(
					SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=ord.member_seq
				) group_name,
				(
					SELECT sum(ea) FROM fm_order_item_option WHERE order_seq=ord.order_seq
				) option_ea,
				(
					SELECT sum(ea) FROM fm_order_item_suboption WHERE order_seq=ord.order_seq
				) suboption_ea,
				(SELECT status FROM fm_order_return WHERE refund_code=ref.refund_code) return_status,
				sum(item.ea) as refund_ea_sum,
				mem.rute as mbinfo_rute,
				mem.user_name as mbinfo_user_name,
				bus.business_seq as mbinfo_business_seq,
				bus.bname as mbinfo_bname,
				sum(item.refund_delivery_price) as refund_delivery_price,
				sum(item.refund_delivery_cash) as refund_delivery_cash,
				sum(item.refund_delivery_emoney) as refund_delivery_emoney
			FROM
				{$sqlForm}
			{$sqlWhereClause}
			GROUP BY ref.refund_code
			ORDER BY ref.status asc, ref.refund_seq DESC
			LIMIT {$limit_s}, {$limit_e}
			";
		}

		return $this->db->query($query);
	}

	/* KICC 결제 취소 */
	public function kicc_cancel($data_order,$data_refund){
		$this->load->library('kicclib');
		$pg = config_load($this->config_system['pgCompany']);
		
		// 거래구분 
		$mgr_txtype = $this->kicclib->arr_mgr_txtype[$data_order['payment']];
		
		// 에스크로 취소 시 변경세부구분
		$mgr_subtype = '';
		
		// 부분취소시
		if($data_refund['cancel_type']=='partial'){
			$mgr_amt = get_currency_price($data_refund['refund_pg_price'],1);
			
			// 에스크로 부분취소는 허가되지 않음.
			if(preg_match('/escrow/', $data_order['payment'])){
				$success = false;
				$res_cd = '9999';
				$res_msg = 'KICC 에스크로 부분취소는 허가되지 않습니다.';
				return array(
					'success'=>$success,
					'result_code'=>$res_cd,
					'result_msg'=>$res_msg,
				);
			}
		}else{
			// 거래구분 
			$mgr_txtype = '40';
			
			// 에스크로 취소일 경우엔 에스크로 구분 값 강제 지정
			if(preg_match('/escrow/', $data_order['payment'])){
				$mgr_txtype = '61';
				$mgr_subtype	= 'ES02';	// 즉시취소 (배송전취소)
				// 출고 완료 이후엔 배송중 취소로 동작
				if($data_order['step']>='55' && $data_order['step']<'85'){
					$mgr_subtype	= 'ES08';	// 배송중 취소요청
				}
			}
		}
		
		// 계좌 환불 처리 시 60 :환불 61: 에스크로 상태변경 62 : 가상계좌
		if($mgr_txtype=="60" || $mgr_txtype=="61" || $mgr_txtype=="62"){
			// 솔루션에서 계좌정보를 입력 받는 형식은 kicc에 환불에 연결 할 수 없기에 데이터를 입력하지 않음.
			$mgr_bank_cd = '';
			$mgr_account = '';
			$mgr_depositor = '';
		}
		
		//변경사유 
		$mgr_msg = $data_refund['cancel_type']=='partial' ? '부분결제취소' : '전체취소';		
		$mgr_msg = iconv('utf-8', 'euc-kr', $mgr_msg);
		
		$cancel_params = array();
		// 공통
		$cancel_params[$this->kicclib->params_prefix.'tr_cd'		] = '00201000';								//	요청구분	N	8	●	변경:00201000
		$cancel_params[$this->kicclib->params_prefix.'mall_id'		] = $pg['mallCode'];							//	가맹점 아이디
		$cancel_params['mgr_txtype'		] = $mgr_txtype;								//	거래구분	N	2	○	20:매입 31:부분매입취소(신용카드) 32:승인부분취소(신용카드) 33:부분취소(계좌이체) 40:즉시취소(승인/매입자동판단취소),  60:환불,  62:부분환불(가상계좌)
		$cancel_params['mgr_subtype'	] = $mgr_subtype;								//		변경세부구분									AN									4 									△									환불(60) 시 필수
		$cancel_params['org_cno'		] = $data_order['pg_transaction_number'];		//	원거래 고유번호									N									20 									○									PG 거래번호
		$cancel_params['mgr_msg'		] = $mgr_msg;									//	변경사유									AN									100									△									
		$cancel_params['req_ip'			] = getenv( "REMOTE_ADDR"    );					//	요청자 IP									ANS									20 									○									

		// 부분취소 / 전체환불 / 부분환불
		$cancel_params['mgr_amt'		] = $mgr_amt;									//	금액									N									14 									△									부분취소/부분환불/부분입금취소 요청 금액(해당 요청 시 필수)
		$cancel_params['mgr_bank_cd'	] = $mgr_bank_cd;								//		은행코드									N									3 									△									환불계좌 은행코드(환불 시 필수)
		$cancel_params['mgr_account'	] = $mgr_account;								//		계좌번호									N									14 									△									환불계좌번호(환불 시 필수)
		$cancel_params['mgr_depositor'	] = $mgr_depositor;								//			예금주명									ANS									50 									△									환불계좌 예금주 명(환불 시 필수)

		// 결제할시 복합결제였는지 체크하여 복합결제일 경우에만 과세 구분 플래그 적용
		$this->load->model('ordermodel');
		$taxData = $this->ordermodel->get_order_prices_for_tax($data_order['order_seq'], '', false, 'all_order');
		$taxData['exempt'] = (int) $taxData['exempt'];
		$taxData['tax'] = (int) $taxData['tax'];
		
		if($taxData['exempt'] > 0){
			$cancel_params['mgr_tax_flg'	] = 'TG01';					//		과세구분 플래그									AN									4									△									
			$cancel_params['mgr_tax_amt'	] = $data_refund['comm_tax_mny'];					//		과세부분취소 금액									N									14									△									
			$cancel_params['mgr_free_amt'	] = $data_refund['free_price'];					//		비과세부분취소 금액									N									14									△									
			$cancel_params['mgr_vat_amt'	] = $data_refund['comm_vat_mny'];				//		부가세 부분취소금액									n									14									△									
		}
		
		// KICC 변경요청 모듈 호출
		$kicc_result = $this->kicclib->callKiccModule($cancel_params);
		$success = $kicc_result['res_cd'] == '0000';
		$res_cd = $kicc_result['res_cd'];
		$res_msg = $kicc_result['res_msg'];

		return array(
			'success'=>$success,
			'result_code'=>$res_cd,
			'result_msg'=>iconv('euc-kr','utf-8',$res_msg)
		);
	}

	/**
	 * @access		public
	 * @author		ycg 2021-02-04
	 * @deprecated	환불 정산 데이터 생성 시 기환불 건 및 환불 상품 옵션 데이터 전달
	 * @param		string $refund_code		환불 코드
	 * @param		array $aAccountallOrder	정산 주문 데이터(주문 번호/상품 옵션 번호)
	 * @return 		boolean
	 */
	public function refund_data_for_accountall(string $refund_code = '', $aAccountallOrder = array()){

		// 조회된 데이터 여부 변수 값 초기화
		// 최초에는 검색 전이므로 기환불 건 없는 경우로 설정
		$bRefundData = false;

		$this->db->where(
			array(
				'fm_order_refund.refund_code !=' => $refund_code,
				'fm_order_refund.status' => 'complete',
				'fm_order_refund.order_seq'=> $aAccountallOrder['order_seq'],
				'fm_order_refund_item.item_seq' => $aAccountallOrder['item_seq']
			)
		);
		$this->db->join('fm_order_refund_item','fm_order_refund_item.refund_code = fm_order_refund.refund_code','left');
		$this->db->from('fm_order_refund');
		$this->db->select();
		$query = $this->db->get();

		if(empty($query->row_array()) != true){
			// 검색 조건에 맞는 값이 있는 경우 기환불 건 있는 경우로 설정
			$bRefundData = true;
		};
		
		return $bRefundData;
	}

	function get_data_refund($params){
		$query = $this->db->select("*")->from("fm_order_refund");
		if($params) {
			$query->where($params);
		}
		return $query->get();
	}

	function get_data_refund_item($params){
		$query = $this->db->select("*")->from("fm_order_refund_item");
		if($params) {
			$query->where($params);
		}
		return $query->get();
	}

	/* 네이버페이 결제 취소 */
	public function naverpayment_cancel($data_order,$data_refund){
		$this->load->library('naverpaymentlib');

		// 취소 금액 계산
		$TotalAmt = $data_order['settleprice'];
		$cancel_type = $data_refund['cancel_type']=='partial' ? 'partial' : 'full';
		if($cancel_type == 'full'){
			$CancelAmt	= $data_order['settleprice'];
		}else{
			$CancelAmt	= $data_refund['refund_pg_price'];

			/* 기 부분매입취소된 금액 제외 */
			$this->db->select('sum(refund_pg_price) as sum_refund_price, count(*) as cancle_cnt');
			$this->db->from('fm_order_refund');
			$this->db->where([
				'status' => 'complete',
				'order_seq' => $data_order['order_seq']
			]);
			$query = $this->db->get();

			$res = $query->row_array();
			if($res['sum_refund_price']){
				$TotalAmt -= $res['sum_refund_price'];
			}
			// 취소금액의 최대값 설정
			if($CancelAmt > $TotalAmt && $res['cancle_cnt'] > 0)
				$CancelAmt = $TotalAmt;
		}

		// 가격 검증
		if ($data_order['pg_currency'] == 'KRW') $CancelAmt = floor($CancelAmt);

		// 인증 관련 호출 전용 값 호출
		$pg_param = config_load('naverpayment');

		$tid = $data_order['pg_log']['tno'] ?: $data_order['pg_transaction_number'];

		// body data 추출
		$body_data['paymentId'] = $tid;	// 네이버페이 결제 번호
		$body_data['merchantPayKey'] = $data_order['order_seq']; // 가맹점의 결제 번호
		$body_data['cancelAmount'] = $CancelAmt; // 취소 요청 금액

		if ($data_order['cancelRequester'] == 1) {
			$cancelReason = getSubstring($data_refund['refund_reason'], '256') ?: '사용자취소';
			$cancelRequester = 1;
		} else {
			$cancelReason = getSubstring($data_refund['refund_reason'], '256') ?: '관리자취소';
			$cancelRequester = 2;
		}

		$body_data['cancelReason'] = $cancelReason; // 취소 사유 (최대 크기는 256바이트)
		$body_data['cancelRequester'] = $cancelRequester; // 취소요청자(1: 주문자, 2: 관리자)

		//과세/비과세 계산
		$mod_tax_mny	= '0';	// 과세금액
		$mod_free_mny	= '0';	// 비과세

		// refund_process->save() 에서 미리 계산한 경우.
		if( isset($data_refund['tax_price']) && isset($data_refund['free_price'])) {
			$mod_tax_mny = floor($data_refund['tax_price']);	// 과세 대상 금액
			$mod_free_mny = floor($data_refund['free_price']);	// 면세 대상 금액
		}else{
			$tax_price = 0;

			// 과세/비과세 계산
			if ($data_refund['tax_price']) {
				$tax_price = $data_refund['tax_price'];

				if($CancelAmt < $data_refund['tax_price']) {
					$tax_price = $CancelAmt;
				}

				$mod_free_mny = $CancelAmt - $tax_price;

			} elseif ($data_refund['free_price']) {
				$tax_price = $CancelAmt;
				$mod_free_mny = $data_refund['free_price'];

				if($CancelAmt < $data_refund['free_price']) {
					$mod_free_mny = $CancelAmt;
				}

				$tax_price = $tax_price - $mod_free_mny;
			} else if($data_order['freeprice']) {
				$tax_price = $CancelAmt;
				$mod_free_mny = $data_order['freeprice'];

				if($CancelAmt < $data_order['freeprice']) {
					$mod_free_mny = $CancelAmt;
				}

				$tax_price = $tax_price - $mod_free_mny;
			} else {
				$tax_price = $CancelAmt;
				$mod_free_mny = 0;
			}

			$mod_tax_mny = (int)$tax_price;
			$mod_free_mny = (int)$mod_free_mny;
		}

		// 취소금액과 과세/비과세 합이 다르면 재계산
		if ($CancelAmt < ($mod_tax_mny + $mod_free_mny)) {
			if ($CancelAmt <= $mod_tax_mny) {
				$mod_tax_mny = $CancelAmt;
				$mod_free_mny = 0;
			} elseif ($CancelAmt <= $mod_free_mny) {
				$mod_tax_mny = 0;
				$mod_free_mny = $CancelAmt;
			} else {
				$mod_free_mny = $CancelAmt - $mod_tax_mny;
			}
		}
		
		$body_data['taxScopeAmount'] = $mod_tax_mny; // 과세 대상 금액
		$body_data['taxExScopeAmount'] = $mod_free_mny; // 면세 대상 금액

		// API 통신
		$read_data = $this->naverpaymentlib->callApi('cancel', $body_data, 60);
		$response = $read_data['body'];

		// 결과값 수신
		if($read_data['code'] == 'Success' && $response['paymentId'] == $tid){
			$success	= true;
			$resultCode	= '200';
			$resultMsg	= '취소성공';
		}else{
			$success	= false;
			if ($read_data['error'] == true) {
				$resultCode	= $read_data['httpCode'];
				$resultMsg	= json_decode($read_data['result'])->message;
			} else {
				$resultCode	= $read_data['code'];
				$resultMsg	= str_replace("'", "`",$read_data['message']);
			}
		}

		// 기취소건이거나 취소 진행중일 경우 취소 성공으로 처리
		if(in_array($resultCode, ['AlreadyCanceled', 'CancelNotComplete'])) {
			$success = true;
		}

		return array(
			'success'		=> $success,
			'result_code'	=> $resultCode,
			'result_msg'	=> $resultMsg
		);
	}

	// 주문번호로 환불번호 조회
	public function getRefundCodeForOrderSeq($order_seq){
		return $this->db->select('refund_code')
						->from('fm_order_refund')
						->where('order_seq', $order_seq)
						->get()
						->result_array();
	}
}
