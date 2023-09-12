<?php
class cartmodel extends CI_Model {

	var $tb_cart			= "fm_cart";
	var $tb_cart_option		= "fm_cart_option";
	var $tb_cart_suboption	= "fm_cart_suboption";
	var $tb_cart_input		= "fm_cart_input";

	public function change_cart($prefix='')
	{
		$this->tb_cart				= "fm_".$prefix."_cart";
		$this->tb_cart_option		= "fm_".$prefix."_cart_option";
		$this->tb_cart_suboption	= "fm_".$prefix."_cart_suboption";
		$this->tb_cart_input		= "fm_".$prefix."_cart_input";
	}

	public function get_cart($cart_seq) {
		$query = "
			SELECT *
			FROM fm_cart
			WHERE cart_seq=?";
		$query = $this->db->query($query,array($cart_seq));
		list($returnArr) = $query->result_array();
		$query->free_result();
		return $returnArr;
	}

	public function get_cart_list($mode='') {
		$session_id = session_id();
		if($this->userInfo['member_seq']){
			$where_query[] = "member_seq = ?";
			$where_arr[] = $this->userInfo['member_seq'];
		}else{
			$where_query[] = "session_id = ?";
			$where_arr[] = $session_id;
		}

		if($mode!=''){
			$where_query[] = "distribution = ?";
			$where_arr[] = $mode;
		}

		$query = "SELECT * FROM fm_cart WHERE ".implode(' AND ',$where_query) ." order by cart_seq desc";
		$query = $this->db->query($query,$where_arr);
		foreach($query->result_array() as $row) $returnArr[] = $row;
		$query->free_result();
		return $returnArr;
	}

	public function get_cart_count() {
		$session_id = session_id();
		if($this->userInfo['member_seq']){
			$where_query[] = "cart.member_seq = ?";
			$where_arr[] = $this->userInfo['member_seq'];
		}else{
			$where_query[] = "cart.session_id = ?";
			$where_arr[] = $session_id;
		}

		//장바구니에 담긴 상품수가 $this->catalog() 에서 구하는 상품수와 안맞아
		//fm_goods_option 조건 추가해줌 leewh 2014-11-19 */
		$now_date = date('Y-m-d');
		$query = "SELECT count(*) as cnt FROM ".$this->tb_cart_option." cart_opt
		left join ".$this->tb_cart." cart on cart.cart_seq = cart_opt.cart_seq
		,fm_goods_option goods_opt
		,fm_goods goods
		WHERE cart.distribution = 'cart'
		AND cart.goods_seq = goods_opt.goods_seq
		AND cart.goods_seq = goods.goods_seq
		AND (goods.goods_view = 'look' or ( goods.display_terms = 'AUTO' and goods.display_terms_begin <= '".$now_date."' and goods.display_terms_end >= '".$now_date."'))
		AND goods.goods_status = 'normal'
		AND ifnull(cart_opt.option1,'') = ifnull(goods_opt.option1,'')
		AND ifnull(cart_opt.option2,'') = ifnull(goods_opt.option2,'')
		AND ifnull(cart_opt.option3,'') = ifnull(goods_opt.option3,'')
		AND ifnull(cart_opt.option4,'') = ifnull(goods_opt.option4,'')
		AND ifnull(cart_opt.option5,'') = ifnull(goods_opt.option5,'')
		AND ".implode(' AND ',$where_query) ." order by cart.cart_seq desc";
		$query = $this->db->query($query,$where_arr);
		$row = $query->result_array();

		return $row[0]['cnt'];

	}

	public function get_cart_option($cart_seq) {
		$this->load->model('goodsmodel');
		$returnArr = "";
		$query = "
			SELECT cart.*,goods.price,goods.consumer_price,goods.goods_seq,
			goods.optioncode1,goods.optioncode2,goods.optioncode3,goods.optioncode4,goods.optioncode5,
			goods.reserve_rate,goods.reserve_unit,goods.reserve,goods.commission_rate,goods.commission_type,
			(select supply_price from fm_goods_supply where option_seq=goods.option_seq and goods_seq=goods.goods_seq) supply_price
			FROM fm_cart_option cart,fm_goods_option goods
			WHERE cart.option1=goods.option1
				AND cart.option2=goods.option2
				AND cart.option3=goods.option3
				AND cart.option4=goods.option4
				AND cart.option5=goods.option5
				AND goods.goods_seq =
				(
					select goods_seq from fm_cart where cart_seq=?
				)
				AND cart.cart_seq=?
			ORDER BY cart.cart_option_seq DESC";
		$query = $this->db->query($query,array($cart_seq,$cart_seq));
		foreach ($query->result_array() as $row){
			$returnArr[] = $row;
		}
		$query->free_result();
		return $returnArr;
	}

	public function get_cart_suboption($cart_seq) {
		$returnArr = "";
		$query = "
			SELECT cart.*,goods.price,goods.consumer_price,goods.goods_seq,goods.suboption_code,
			goods.reserve_rate,goods.reserve_unit,goods.reserve,goods.commission_rate,goods.commission_type,
			(select supply_price from fm_goods_supply where suboption_seq=goods.suboption_seq and goods_seq=goods.goods_seq) supply_price
			FROM fm_cart_suboption cart,fm_goods_suboption goods
			WHERE cart.suboption=goods.suboption AND cart.suboption_title=goods.suboption_title
				AND goods.goods_seq =
				(
					select goods_seq from fm_cart where cart_seq=?
				)
				AND cart.cart_seq=?
			ORDER BY cart.cart_suboption_seq ASC";
		$query = $this->db->query($query,array($cart_seq,$cart_seq));
		foreach ($query->result_array() as $row){
			$returnArr[] = $row;
		}
		$query->free_result();
		return $returnArr;
	}

	public function get_cart_input($cart_seq, $cart_option_seq = '') {
		$bind[]	= $cart_seq;
		if	($cart_option_seq > 0){
			$addWhere	= " and cart_option_seq = ? ";
			$bind[]		= $cart_option_seq;
		}


		$returnArr = "";
		$query = "
			SELECT *
			FROM fm_cart_input
			WHERE cart_seq=?
			".$addWhere."
			ORDER BY cart_input_seq DESC";
		$query = $this->db->query($query, $bind);
		foreach ($query->result_array() as $row){
			$returnArr[] = $row;
		}
		$query->free_result();
		return $returnArr;
	}

	public function delete($cart_seqs){

		$this->db->select('cart_seq');
		if($this->userInfo['member_seq']) $this->db->where('member_seq', $this->userInfo['member_seq']);
		else $this->db->where('session_id', session_id());
		$this->db->where_in('cart_seq',$cart_seqs);
		$query = $this->db->get('fm_cart');

		foreach ($query->result_array() as $row)
		{
			$tables = array('fm_cart_option', 'fm_cart_input', 'fm_cart_suboption', 'fm_cart');
			$this->db->delete($tables,array('cart_seq' => $row['cart_seq']));
		}
	}

	public function delete_option($cart_option_seq=null,$cart_suboption_seq=null){
		$cart_seq = null;

		if($cart_option_seq){
			$query = $this->db->query("select cart_seq from fm_cart_option where cart_option_seq=?",$cart_option_seq);
		} else if($cart_suboption_seq) {
			$query = $this->db->query("select cart_seq from fm_cart_suboption where cart_suboption_seq=?",$cart_suboption_seq);
		}

		$result = $query->row_array();
		$cart_seq = $result['cart_seq'];

		if(!$cart_seq) return;

		$tables = array('fm_cart_option', 'fm_cart_input', 'fm_cart_suboption', 'fm_cart');
		$this->db->delete($tables, array('cart_seq' => $cart_seq));
	}

	public function delete_mode($mode){
		$this->db->select('cart_seq');
		if($this->userInfo['member_seq'] && $mode != 'admin') $this->db->where('member_seq', $this->userInfo['member_seq']);
		else $this->db->where('session_id', session_id());
		$this->db->where('distribution',$mode);
		$query = $this->db->get('fm_cart');
		foreach ($query->result_array() as $row)
		{
			$tables = array('fm_cart_option', 'fm_cart_input', 'fm_cart_suboption', 'fm_cart');
			$this->db->delete($tables,array('cart_seq' => $row['cart_seq']));
		}
	}

	/**
	 이전에 담긴 바로 구매
	 */
	public function delete_for_settle(){

		$where_option_str = '';

		$where[] = "distribution = ?";
		$where_val[] = 'direct';

		if($this->userInfo['member_seq']){
			$where[] = "member_seq=?";
			$where_val[] = $this->userInfo['member_seq'];
		}else{
			$where[] = "session_id=?";
			$where_val[] = session_id();
		}

		$query = "select max(cart_seq) cart_seq from fm_cart where ".implode(' and ',$where);
		$query = $this->db->query($query,$where_val);
		$max_row = $query->row_array();

		$query = "select cart_seq from fm_cart where ".implode(' and ',$where)." and cart_seq!='".$max_row['cart_seq']."'";
		$query = $this->db->query($query,$where_val);
		foreach ($query->result_array() as $row){
				$tables = array($this->tb_cart_option, $this->tb_cart_input, $this->tb_cart_suboption, $this->tb_cart);
				$this->db->delete($tables,array('cart_seq' => $row['cart_seq']));
		}
	}

	public function merge_for_member($member_seq){

		$session_id = session_id();

		$this->db->where('session_id',$session_id);
		$this->db->update('fm_cart', array('member_seq' => $member_seq, 'session_id'=>''));

		$carts = $this->get_cart_list('cart');
		$arr_done = array();
		foreach($carts as $cart){
			if(!in_array($cart['goods_seq'],$arr_done)){
				$this->merge_for_goods($cart['goods_seq'],$cart['cart_seq'],$member_seq);
			}
			$arr_done[] = $cart['goods_seq'];
		}

	}

	// 선택 상품을 장바구니에 합쳐줌 :: 수정 2017-01-17 lwh
	public function merge_for_choice(){
		$this->load->model('shippingmodel');

		if($this->userInfo['member_seq']){
			$this->db->where('member_seq',$this->userInfo['member_seq']);
			$this->db->where('distribution','choice');
		}else{
			$session_id = session_id();
			$this->db->where('session_id',$session_id);
			$this->db->where('distribution','choice');
		}
		$this->db->update('fm_cart', array('distribution' => 'cart'));

		$carts = $this->get_cart_list('cart');
		$arr_done = array();
		foreach($carts as $cart){

			// 배송그룹 정보 변경시 예외처리 :: START 2016-10-26 lwh
			$ship_seq_sql = "
				SELECT *
				FROM fm_shipping_set
				WHERE
					shipping_group_seq = '" . $cart['shipping_group_seq'] . "' AND
					shipping_set_seq = '" . $cart['shipping_set_seq'] . "' AND
					shipping_set_code = '" . $cart['shipping_set_code'] . "'
			";
			$query	= $this->db->query($ship_seq_sql);
			$set_res= $query->row_array();
			if($set_res){
				// 선착불 정보 조건 불일치시..
				if($cart['shipping_prepay_info'] != $set_res['prepay_info'] && $set_res['prepay_info'] != 'all'){
					$cart['shipping_prepay_info'] = $set_res['prepay_info'];
					$this->db->where('cart_seq',$cart['cart_seq']);
					$this->db->update('fm_cart', array('shipping_prepay_info'=>$set_res['prepay_info']));
				}
			}else{ // 기존 장바구니에서 담긴 SET 정보가 없을경우  기본배송방법을 지정 -> UPDATE
				$set_info = $this->shippingmodel->get_shipping_set($cart['shipping_group_seq'], 'shipping_group_seq', array('default_yn'=>'Y'));
				if($set_info){
					$update_arr = array();
					$update_arr['shipping_set_seq'] = $set_info[0]['shipping_set_seq'];
					$update_arr['shipping_set_code'] = $set_info[0]['shipping_set_code'];
					if($set_info[0]['prepay_info'] == 'all')
							$update_arr['shipping_prepay_info'] = 'delivery';
					else	$update_arr['shipping_prepay_info'] = $set_info[0]['prepay_info'];

					$this->db->where('cart_seq',$cart['cart_seq']);
					$this->db->update('fm_cart', $update_arr);
				}
			}
			// 배송그룹 정보 변경시 예외처리 :: END 2016-10-26 lwh

			// 배송그룹 정보 추출 및 개별 배송그룹 판단 :: 2017-01-17
			$grp_info = $this->shippingmodel->get_shipping_group($cart['shipping_group_seq']);
			if($grp_info['shipping_calcul_type'] == 'each'){
				$each_sql	= "select * from fm_cart_option where cart_seq = '" . $cart['cart_seq'] . "'";
				$query		= $this->db->query($each_sql);
				$each_res	= $query->result_array();
				foreach($each_res as $k => $opt_val){
					if($k < 1) continue;
					$copy_cart = $cart;
					unset($copy_cart['cart_seq']);
					$this->db->insert('fm_cart', $copy_cart);
					$new_cart_seq = $this->db->insert_id();

					$this->db->where('cart_option_seq',$opt_val['cart_option_seq']);
					$this->db->update('fm_cart_option', array('cart_seq'=>$new_cart_seq));
				}
			}else{
				// 묶음 계산 상품 장바구니 merge
				if(!in_array($cart['goods_seq'],$arr_done)){
					$this->merge_for_goods($cart['goods_seq'],$cart['cart_seq'],$member_seq);
				}
			}
			$arr_done[] = $cart['goods_seq'];
		}
	}

	public function cart_list($admin=""){
		$total = 0;
		$total_point =0;
		$result = "";
		$member_seq = "";
		$where_query = "";
		$shop_total_price = 0;
		$shop_total_price_exempt = 0;
		$exempt_chk	= 0;
		$shop_shipping_policy = "";
		$session_id = session_id();

		$cfg_reserve = config_load('reserve');

		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->model('promotionmodel');


		if($admin != ""){
			$where_arr[] = "admin";
			$where_query[] = "cart.session_id = ?";
			$where_arr[] = $session_id;
		}else{

			if(!isset($_GET['mode'])) $mode = 'cart';
			else $mode = $_GET['mode'];
			$where_arr[] = $mode;

			if($this->userInfo['member_seq']){

				$this->load->model('membermodel');

				$member_seq = $this->userInfo['member_seq'];
				$where_query[] = "cart.member_seq = ?";
				$where_arr[] = $member_seq;
			}else{
				$where_query[] = "cart.session_id = ?";
				$where_arr[] = $session_id;
			}
		}

		$query = "
		SELECT
			cart.cart_seq,cart.fblike,
			goods.goods_seq,goods.goods_name,goods.goods_code,goods.cancel_type,goods.goods_kind,
			goods.shipping_weight_policy,goods.goods_weight,
			goods.shipping_policy,goods.goods_shipping_policy,
			goods.unlimit_shipping_price,goods.limit_shipping_price,
			goods.limit_shipping_ea,goods.limit_shipping_subprice,
			goods_img.image,
			(
				SELECT sum(ea)
				FROM fm_cart_suboption
				WHERE cart_seq=cart.cart_seq
			) sub_ea,
			sum(cart_opt.ea) ea,
			(
				SELECT COUNT(cart_suboption_seq)
				FROM fm_cart_suboption
				WHERE cart_seq=cart.cart_seq
			) sub_cnt,
			(
				SELECT SUM(g.price*s.ea)
				FROM fm_goods_suboption g,fm_cart_suboption s
				WHERE g.goods_seq=cart.goods_seq
				AND g.suboption=s.suboption
				AND g.suboption_title=s.suboption_title
				AND s.cart_seq=cart.cart_seq
			) sub_price,
			(
				SELECT SUM(g.reserve*s.ea)
				FROM fm_goods_suboption g,fm_cart_suboption s
				WHERE g.goods_seq=cart.goods_seq
				AND g.suboption=s.suboption
				AND g.suboption_title=s.suboption_title
				AND s.cart_seq=cart.cart_seq
			) sub_reserve,
			goods_opt.price,
			goods_opt.consumer_price,
			goods_opt.reserve_unit as reserve_unit,
			SUM(IF(cart_opt.option1!='',1,0)) opt_cnt,
			SUM(goods_opt.reserve*cart_opt.ea) reserve,
			goods.reserve_policy,
			goods.multi_discount_use,
			goods.multi_discount_ea,
			goods.multi_discount,
			goods.multi_discount_unit,
			goods.tax,
			goods.provider_seq,
			goods.social_goods_group,
			goods.socialcp_input_type,goods.socialcp_cancel_type,
			goods.socialcp_cancel_use_refund,goods.socialcp_cancel_payoption,goods.socialcp_cancel_payoption_percent,
			goods.socialcp_use_return,goods.socialcp_use_emoney_day,goods.socialcp_use_emoney_percent,
			goods.individual_refund,
			goods.individual_refund_inherit,
			goods.individual_export,
			goods.individual_return,
			goods.trust_shipping,
			goods.display_terms,
			goods.display_terms_text,
			goods.display_terms_color,
			goods.display_terms_begin,
			goods.display_terms_end
		FROM
			fm_cart cart
			left join fm_goods_image goods_img on goods_img.cut_number = 1 AND goods_img.image_type = 'thumbCart' AND cart.goods_seq = goods_img.goods_seq
			,fm_goods goods
			,fm_cart_option cart_opt
			,fm_goods_option goods_opt
		WHERE
			cart.distribution=?
			AND cart.goods_seq = goods.goods_seq
			AND cart.cart_seq = cart_opt.cart_seq
			AND cart.goods_seq = goods_opt.goods_seq
			AND ifnull(cart_opt.option1,'') = ifnull(goods_opt.option1,'')
			AND ifnull(cart_opt.option2,'') = ifnull(goods_opt.option2,'')
			AND ifnull(cart_opt.option3,'') = ifnull(goods_opt.option3,'')
			AND ifnull(cart_opt.option4,'') = ifnull(goods_opt.option4,'')
			AND ifnull(cart_opt.option5,'') = ifnull(goods_opt.option5,'')";
		if($where_query){
			$query .= ' AND '.implode(' AND ', $where_query);
		}
		$query .= " GROUP BY cart.cart_seq ORDER BY cart.cart_seq DESC";
		$query = $this->db->query($query,$where_arr);

		$shipping_price['goods']	= 0;
		$shipping_exempt			= 0;
		$promocodeSale				= 0;
		$cart_items					= $query->result_array();

		$provider_shipping_policy	= array();
		$provider_sum_goods_price	= array();
		$provider_shipping_price	= array();
		$provider_box_ea			= array();

		foreach ($cart_items as $row){
			$categorys = $this->goodsmodel->get_goods_category($row['goods_seq']);
			foreach($categorys as $key => $data) $row['r_category'] = $this->categorymodel->split_category($data['category_code']);

			$cart_options		= $this->cartmodel->get_cart_option($row['cart_seq']);
			$cart_suboptions	= $this->cartmodel->get_cart_suboption($row['cart_seq']);
			$cart_inputs		= $this->cartmodel->get_cart_input($row['cart_seq']);
			$shipping			= $this->goodsmodel->get_goods_delivery($row,$row['ea']);

			if(!in_array($shipping['provider_seq'],array_keys($provider_shipping_policy))){
				$provider_shipping_policy[$shipping['provider_seq']] = $shipping;
			}

			# 실제배송업체 Seq(위탁배송여부에따라)
			$row['shipping_provider_seq'] = $shipping['provider_seq'];
			$row['shipping']			  = $shipping;

			//예약 상품의 경우 문구를 넣어준다 2016-11-07
			$row['goods_name'] = get_goods_pre_name($row,true,true);
			$row['goods_name'] = strip_tags(str_replace(array("\"","'"),'',$row['goods_name']));

			$arr_multi = array(
				'multi_discount_use' => $row['multi_discount_use'],
				'multi_discount_ea' => $row['multi_discount_ea'],
				'multi_discount' => $row['multi_discount'],
				'multi_discount_unit' => $row['multi_discount_unit']
			);



			if($row['reserve_policy'] == 'shop') $row['reserve'] = 0;

			$row['point'] = 0;
			foreach($cart_options as $key_option => $data_option){
				$data_option['ori_price'] = $data_option['price'];
				$categorys = $this->goodsmodel->get_goods_category($data_option['goods_seq']);
				foreach($categorys as $key => $data) $arr_category = $this->categorymodel->split_category($data['category_code']);

				// event sale
				$data_option['event'] = get_event_price($data_option['ori_price'], $row['goods_seq'], $arr_category, $data_option['consumer_price'],$data_option);
				if($data_option['event']['event_seq']) {
					if($data_option['event']['target_sale'] == 1 && $data_option['consumer_price'] > 0 ){//정가기준 할인시
						$data_option['price'] = ($data_option['consumer_price'] > $data_option['event']['event_sale_unit'])?$data_option['consumer_price'] - (int) $data_option['event']['event_sale_unit']:0;
					}else{
						$data_option['price'] = ($data_option['price'] > $data_option['event']['event_sale_unit'])?$data_option['price'] - (int) $data_option['event']['event_sale_unit']:0;
					}
				}

				// multi sale
				$data_option['price'] = (int) $this->goodsmodel->get_multi_sale_price($row['ea'],$data_option['price'],$arr_multi);
				$row['tot_price'] += $data_option['price'] * $data_option['ea'];

					// reserve
				if($row['reserve_policy'] == 'shop') {
					$data_option['reserve'] = $this->goodsmodel->get_reserve_with_policy($row['reserve_policy'],$data_option['price'],$cfg_reserve['default_reserve_percent'],$data_option['reserve_rate'],$data_option['reserve_unit'],$data_option['reserve']);
					$data_option['reserve'] += (int) $data_option['event']['event_reserve_unit'];
					$row['reserve'] += $data_option['reserve'] * $data_option['ea'];
				}

				###optoin point
				$data_option['point'] = (int) $this->goodsmodel->get_point_with_policy($data_option['price']);
				$row['point'] += ($data_option['point'] * $data_option['ea']);
				$row['point'] += (int) $data_option['event']['event_point_unit'];

				$cart_options[$key_option] = $data_option;
			}

			//suboption point
			foreach($cart_suboptions as $key_suboption => $data_suboption){
				###
				$data_suboption['point'] = (int) $this->goodsmodel->get_point_with_policy($data_suboption['price']);
				$row['point'] += ($data_suboption['point'] * $data_suboption['ea']);
			}

			$row['reserve']+=$row['sub_reserve'];
			$row['cart_options'] = $cart_options ? $cart_options : array();
			$row['cart_suboptions'] = $cart_suboptions ? $cart_suboptions : array();
			$row['cart_inputs'] = $cart_inputs;

			$row['tot_price'] += $row['sub_price'];
			$row['ea'] += $row['sub_ea'];

			$row['goods_shipping'] = 0;

			if($row['shipping_policy'] == 'shop'){
				$shop_total_price += $row['tot_price'];
				if($row['tax']!="tax"){
					$shop_total_price_exempt += $row['tot_price'];
				}
				//$shop_shipping_policy = $shipping;
			}else{
				$row['goods_shipping'] = $shipping['price'];
				$shipping_price['goods'] += $row['goods_shipping'];
				$provider_shipping_price[$row['shipping_provider_seq']]['goods'] += $row['goods_shipping'];
				$provider_box_ea[$row['provider_seq']] += $shipping['box_ea'];
				$box_ea += $shipping['box_ea'];

				if($row['tax']!="tax"){
					$shop_total_price_exempt += $row['tot_price'];
					$shipping_exempt += $shipping['price'];
				}
			}

			$shop_total_price += $row['tot_price'];
			$provider_sum_goods_price[$shipping['provider_seq']] += $row['tot_price'];

			###
			if($row['tax']!="tax"){
				$exempt_chk++;
			}

			$total_point += $row['point'];
			$total_reserve += $row['reserve'];
			$total += $row['tot_price'];
			$result[] = $row;
		}

		###
		if($query->num_rows()==$exempt_chk){
			$tax_type = "exempt";
		}else if($exempt_chk == 0){
			$tax_type = "tax";
		}else{
			$tax_type = "mix";
		}

		if($member_seq && $result) foreach($result as $k => $row){
			$categorys = $this->goodsmodel->get_goods_category($row['goods_seq']);
			foreach($categorys as $key => $data) $arr_category = $this->categorymodel->split_category($data['category_code']);
			foreach($row['cart_options'] as $key_option => $data_option){
				$add_reserve = (int) $this->membermodel->get_group_addreseve($member_seq,$data_option['price'],$total,$row['goods_seq'],$arr_category,'','','reserve');
				$data_option['reserve'] += $add_reserve;

				$row['reserve'] += $add_reserve*$data_option['ea'];
				$total_reserve+= $add_reserve*$data_option['ea'];

				$add_point = (int) $this->membermodel->get_group_addreseve($member_seq,$data_option['price'],$total,$row['goods_seq'],$arr_category,'','','point');
				$data_option['point'] += $add_point;
				$row['point'] += $add_point*$data_option['ea'];
				$total_point+= $add_point*$data_option['ea'];

				$row['cart_options'][$key_option] = $data_option;
			}
			$result[$k] = $row;
		}


		foreach($result as $k => $row){$result[$k]['promocodeSale']=0;
			foreach($row['cart_options'] as $key_option => $data_option){
				if($data_option['promotion_code_seq'] && $data_option['promotion_code_serialnumber']) {
					//promotion code sale
					$promotions = $this->promotionmodel->get_able_download_saleprice($data_option['promotion_code_seq'],$data_option['promotion_code_serialnumber'], $total, $data_option['price'],$data_option['ea']);
					$result[$k]['cart_options'][$key_option]['promotioncode_sale'] = (int) $promotions['promotioncode_sale'];
					$result[$k]['promocodeSale'] += (int) $promotions['promotioncode_sale'];
					$promocodeSale += (int) $promotions['promotioncode_sale'];
				}
			}
		}

		foreach($provider_shipping_policy as $provider_seq=>$row){
			if($provider_sum_goods_price[$provider_seq] && $row['free'] && $row['free'] <= $provider_sum_goods_price[$provider_seq]){
				$shipping_price['shop'] += 0;
				$provider_shipping_price[$row['provider_seq']]['shop'] += 0;
			}else{
				$shipping_price['shop'] += $row['policy']=='shop' ? $row['price'] : 0;
				$provider_shipping_price[$row['provider_seq']]['shop'] += $row['policy']=='shop' ? $row['price'] : 0;
			}
		}

		if( $shop_total_price )$box_ea += 1;
		foreach($provider_sum_goods_price as $provider_seq => $value){
			if($value) $provider_box_ea[$provider_seq] += 1;
		}

		$total_price = $total + array_sum($shipping_price);
		$exempt_price = $shop_total_price_exempt;

		function cmp($a, $b)
		{
		    if ($a['shipping_provider_seq'] == $b['shipping_provider_seq']) return 0;
		    return ($a['shipping_provider_seq'] < $b['shipping_provider_seq']) ? -1 : 1;
		}
		usort ($result, "cmp");
		foreach($result as $k=>$row){
			if ( $this->mobileMode ) {//모바일스킨형태
					$result[$k]['shipping_provider_division'] = true;
			}else{
				if(!$k || $prev_shipping_provider_seq!=$row['shipping_provider_seq']){
					$result[$k]['shipping_provider_division'] = true;
				}
			}
			$prev_shipping_provider_seq = $row['shipping_provider_seq'];

			$shipping_company_cnt[$row['shipping_provider_seq']]++;
		}

		$arr = array(
			'total_reserve'=>$total_reserve,
			'total_point'=>$total_point,
			'taxtype'=>$tax_type,
			'exempt_shipping'=>$shipping_exempt,
			'exempt_price'=>$exempt_price,
			'list'=>$result,
			'total'=>$total,
			'shipping_price'=>$shipping_price,
			'shipping_company_cnt'=>$shipping_company_cnt,
			'provider_shipping_price'=>$provider_shipping_price,
			'provider_shipping_policy'=>$provider_shipping_policy,
			'provider_box_ea'=>$provider_box_ea,
			//'shop_shipping_policy'=>$shop_shipping_policy,
			'total_price'=>$total_price,
			'promocodeSale'=>$promocodeSale,
			'box_ea'=>$box_ea
		);

		return $arr;
	}

	// 장바구니 정보 같은 상품군 묶기 :: 수정 2016-07-26 lwh
	function merge_for_goods($goods_seq,$cart_seq,$member_seq=''){

		$session_id = session_id();
		if(!$member_seq ) $member_seq = $this->userInfo['member_seq'];

		$cart		= $this->get_cart($cart_seq);
		$options	= $this->get_cart_option($cart_seq);
		$suboptions = $this->get_cart_suboption($cart_seq);

		if($member_seq) $this->db->where('member_seq',$member_seq);
		else $this->db->where('session_id',$session_id);

		$this->db->where('goods_seq',$goods_seq);
		$this->db->where('cart_seq !=',$cart_seq);
		$this->db->where('distribution','cart');
		$this->db->select('cart_seq, shipping_group_seq, shipping_set_seq, shipping_set_code');
		$query = $this->db->get('fm_cart');

		foreach($query->result_array() as $row){
			$pre_cart_seq		= $row['cart_seq'];
			$shipping_group_seq	= $cart['shipping_group_seq'];
			$shipping_set_seq	= $cart['shipping_set_seq'];

			$chg_cart_shipping = array(
				'cart_seq'=>$cart_seq,
				'shipping_method'=>$shipping_set_seq
			);

			$this->db->where('cart_seq', $pre_cart_seq);
			$this->db->update($this->tb_cart_option, $chg_cart_shipping);

			$this->db->where('cart_seq', $cart_seq);
			$this->db->update($this->tb_cart_option, $chg_cart_shipping);

			unset($chg_cart_shipping['shipping_method']);

			$this->db->where('cart_seq', $pre_cart_seq);
			$this->db->update($this->tb_cart_suboption, $chg_cart_shipping);

			$this->db->where('cart_seq', $pre_cart_seq);
			$this->db->update($this->tb_cart_input, $chg_cart_shipping);

			$this->db->delete($this->tb_cart,array('cart_seq' => $pre_cart_seq));
		}
	}


	public function get_cart_option_by_cart_option($cart_option_seq)
	{
		$bind[0] = $cart_option_seq;
		$query = "
			SELECT cart.*,goods.price,goods.consumer_price,goods.goods_seq,
			goods.reserve_rate,goods.reserve_unit,goods.reserve,goods.commission_rate,goods.commission_type,
			(select supply_price from fm_goods_supply where option_seq=goods.option_seq and goods_seq=goods.goods_seq) supply_price
			FROM ".$this->tb_cart_option." cart,fm_goods_option goods
			WHERE cart.cart_option_seq=?
			AND goods.goods_seq =
			(
				select goods_seq from ".$this->tb_cart." where cart_seq=cart.cart_seq
			)
			AND cart.option1=goods.option1
			AND cart.option2=goods.option2
			AND cart.option3=goods.option3
			AND cart.option4=goods.option4
			AND cart.option5=goods.option5";
		$query = $this->db->query($query,$bind);
		$data = $query->row_array();
		return $data;
	}

	public function get_cart_by_cart_option($cart_option_seq)
	{
		// reset
		$bind = array();

		if(is_array($cart_option_seq)) {
			$bind = $cart_option_seq;
		} else {
			$bind = array(array($cart_option_seq));
		}
		$query = "select * from ".$this->tb_cart." where cart_seq in (select cart_seq from ".$this->tb_cart_option." where cart_option_seq in ?)";
		$query = $this->db->query($query,$bind);
		$data = $query->row_array();
		return $data;
	}

	public function get_cart_suboption_by_cart_option($cart_option_seq)
	{
		$returnArr = "";
		$query = "
			SELECT cart.*,goods.suboption_seq, goods.price,goods.consumer_price,goods.goods_seq,goods.sub_sale,
			goods.reserve_rate,goods.reserve_unit,goods.reserve,goods.commission_rate,goods.commission_type,
			(select supply_price from fm_goods_supply where suboption_seq=goods.suboption_seq and goods_seq=goods.goods_seq) supply_price,
			goods.package_count as package_count_sub,
			goods.package_option_seq1,
			goods.package_option1,
			goods.suboption_seq,
			goods.weight,
			opt.weight as sub_weight
			FROM
				".$this->tb_cart_suboption." cart,fm_goods_suboption goods
				LEFT JOIN fm_goods_option as opt ON opt.option_seq = goods.package_option_seq1
			WHERE cart.suboption=goods.suboption AND cart.suboption_title=goods.suboption_title
				AND goods.goods_seq =
				(
					select goods_seq from ".$this->tb_cart." where cart_seq=cart.cart_seq
				)
				AND cart.cart_option_seq=?
			ORDER BY cart.cart_suboption_seq ASC";
		$query = $this->db->query($query,array($cart_option_seq));
		foreach ($query->result_array() as $row){
			if($row['package_count_sub'] > 0){
				$row['weight'] = $row['sub_weight'];
			}
			$returnArr[] = $row;
		}
		$query->free_result();
		return $returnArr;
	}

	public function get_cart_input_by_cart_option($cart_option_seq)
	{
		$returnArr = "";
		$query = "
			SELECT *
			FROM ".$this->tb_cart_input."
			WHERE cart_option_seq=?
			ORDER BY cart_input_seq ASC";
		$query = $this->db->query($query,array($cart_option_seq));
		foreach ($query->result_array() as $row){

			if($row['type'] == "file" && $row['input_value']){
				$row['input_img_path']	= "data/order/";

				$tmp = explode("/",$row['input_value']);
				if($tmp > 1){
					$row['input_value'] = $tmp[count($tmp)-1];
				}
			}
			$returnArr[] = $row;
		}

		$query->free_result();
		return $returnArr;
	}

	public function delete_cart_option($cart_option_seq,$mode='del')
	{
		$data = $this->get_cart_option_by_cart_option($cart_option_seq);
		$cart_seq = $data['cart_seq'];

		$bind[0]=$cart_option_seq;
		$query="delete from ".$this->tb_cart_option." where cart_option_seq=?";
		$this->db->query($query,$bind);
		$query="delete from ".$this->tb_cart_suboption." where cart_option_seq=?";
		$this->db->query($query,$bind);
		$query="delete from ".$this->tb_cart_input." where cart_option_seq=?";
		$this->db->query($query,$bind);

		if($mode == 'del'){
			$query = "select count(*) cnt from ".$this->tb_cart_option." where cart_seq=?";
			$query = $this->db->query($query,array($cart_seq));
			$data = $query->row_array();
			$cnt = $data['cnt'];
			if($cnt==0){
				$query="delete from ".$this->tb_cart." where cart_seq=?";
				$this->db->query($query,array($cart_seq));
			}
		}
	}

	public function catalog($admin="",$tmp_arr=null,$applypage='saleprice', $cart_option_seqs = array()){


		// 참조 load
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('promotionmodel');
		$this->load->model('shippingmodel');
		$this->load->model('membermodel');
		$this->load->library('sale');
		$this->load->helper('shipping_helper');

		// 기본값 세팅
		$total						= 0;
		$total_point				= 0;
		$result						= '';
		$member_seq					= '';
		$where_query				= '';
		$shop_total_price			= 0;
		$shop_total_price_exempt	= 0;
		$exempt_chk					= 0;
		$shop_shipping_policy		= '';
		$default_box_ea				= false;
		//$applypage					= 'saleprice';	//네이버페이2.1 연동. 회원 할인 적용 하기 위해 인자값으로 받아옴
		$arr_shipping_method		= get_shipping_method('all');
		if($tmp_arr['session_id']){
			$session_id				= $tmp_arr['session_id'];
		}else{
			$session_id				= session_id();
		}
		$cfg_reserve				= config_load('reserve');
		$shipping_price['goods']	= 0;
		$shipping_exempt			= 0;
		$promocodeSale				= 0;
		$provider_shipping_policy	= array();
		$provider_sum_goods_price	= array();
		$provider_shipping_price	= array();
		$provider_box_ea			= array();

		//--> sale library 적용
		$param['cal_type']				= 'list';
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<-- sale library 적용

		$now_date					= date('Y-m-d');
		//debug($session_id);
		if(in_array($tmp_arr['cart_table'],array("admin","person","reorder","rematch")) && $tmp_arr['mode'] == "tmp"){

			$goods_seq = $tmp_arr['option_select_goods_seq'];

			foreach($tmp_arr['inputsValue'] as $k2 => $v2){
				foreach($v2 as $k3=>$v3){
					 $tmp_arr['inputsValue'][$k2][$k3] = str_replace("\"", "&quot;", $tmp_arr['inputsValue'][$k2][$k3]);
				}
			}

			foreach($tmp_arr['option'] as $k=>$option){

				$option_data = $this->goodsmodel->get_cart_tmp_option($tmp_arr,$goods_seq,$k);

				if($option_data) $cart_list[] = $option_data;

			}
			$num_rows	= count($cart_list);

		}else{

			if(is_array($admin)){
				$param = $admin;
				unset($admin);
				$admin		= '';
				$admin		= $param['member_seq'];
				$cart_sdate = $param['cart_sdate'];
				$cart_edate = $param['cart_edate'];
				$cart_today	= array("today"=>$param['today'],"todayw"=>$param['todayw']);;
			}

			$goodsStatusSql		= "AND goods.goods_status = 'normal'";
			if($admin == "admin"){
				$where_arr[]		= "admin";
				$goodsStatusSql		= "";
				$where_query[]		= "cart.session_id = ?";
				$where_arr[]		= $session_id;
			}else if($admin != "admin" && $admin != ""){
				$where_query[]	= "cart.member_seq = ?";
				//2014-07-25추가. 특정회원 조회인데 장바구니 구분값이 빠져서 오류발생
				if($tmp_arr['distribution']){
					$where_arr[]	= $tmp_arr['distribution'];
				}else{
					$where_arr[]	= "cart";
				}
				$where_arr[]	= $admin;
				# 개인맞춤형알림에서 사용 2014-07-25
				if($cart_sdate && $cart_edate){
					$where_query[] = "cart.regist_date between ? and ? ";
					$where_arr[] = $cart_sdate;
					$where_arr[] = $cart_edate;
				}
			}else{
				if($tmp_arr['session_id']){
					$where_arr[]		= $tmp_arr["distribution"];
					$goodsStatusSql		= "";
					$where_query[]		= "cart.session_id = ?";
					$where_arr[]		= $session_id;
				}else{

					$mode			= $this->input->get_post('mode') ? $this->input->get_post('mode') : 'cart';
					$where_arr[]	= $mode;

					if($mode == 'choice'){
						$where_query[] = "cart_opt.choice = ?";
						$where_arr[] = 'y';
					}

					if($this->userInfo['member_seq']){
						$this->load->model('membermodel');
						$member_seq = $this->userInfo['member_seq'];
						$where_query[] = "cart.member_seq = ?";
						$where_arr[] = $member_seq;
					}else{
						$where_query[] = "member_seq=0 and cart.session_id = ?";
						$where_arr[] = $session_id;
					}
				}
			}
			if(count($cart_option_seqs)>0) {
			    $where_query[] = "cart_opt.cart_option_seq IN (" . implode($cart_option_seqs, ",") . ")";
			}
			$query = "
				SELECT
					cart.fblike,
					goods.goods_seq,goods.goods_name,goods.goods_code,goods.goods_type,goods.cancel_type,
					goods.goods_kind,goods.socialcp_event,
					goods.package_yn,goods.package_yn_suboption,goods.shipping_weight_policy,goods_opt.weight as goods_weight,
					goods.shipping_policy,goods.goods_shipping_policy,
					goods.unlimit_shipping_price,goods.limit_shipping_price,
					goods.limit_shipping_ea,goods.limit_shipping_subprice,
					goods.sale_seq,
					goods.min_purchase_ea,
					goods.max_purchase_ea,
					goods_opt.option_seq,
					goods_opt.price,
					goods_opt.commission_rate,
					goods_opt.commission_type,
					goods_opt.reserve_rate,
					goods_opt.reserve_unit as reserve_unit,
					goods_opt.reserve reserve,
					goods_opt.consumer_price,
					goods_opt.package_option_seq1,
					goods_opt.package_option_seq2,
					goods_opt.package_option_seq3,
					goods_opt.package_option_seq4,
					goods_opt.package_option_seq5,
					goods_opt.package_option1,
					goods_opt.package_option2,
					goods_opt.package_option3,
					goods_opt.package_option4,
					goods_opt.package_option5,
					goods_opt.full_barcode,
					(select supply_price from fm_goods_supply where goods_seq=goods_opt.goods_seq and option_seq=goods_opt.option_seq) supply_price,
					goods.reserve_policy,
					goods.sub_reserve_policy,
					goods.multi_discount_use,
					goods.multi_discount_ea,
					goods.multi_discount,
					goods.multi_discount_unit,
					goods.tax,
					goods.provider_seq,
					(select provider_name from fm_provider where provider_seq=goods.provider_seq) as provider_name,
					goods.social_goods_group,
					goods.socialcp_input_type,goods.socialcp_cancel_type,
					goods.socialcp_cancel_use_refund,goods.socialcp_cancel_payoption,goods.socialcp_cancel_payoption_percent,
					goods.socialcp_use_return,goods.socialcp_use_emoney_day,goods.socialcp_use_emoney_percent,
					goods.individual_refund,
					goods.individual_refund_inherit,
					goods.individual_export,
					goods.individual_return,
					goods.possible_pay,
					goods.possible_mobile_pay,
					goods.adult_goods,
					goods.hscode,
					goods.purchase_goods_name,
					goods.option_international_shipping_status,
					goods.shipping_group_seq as goods_shipping_group_seq,
					goods.trust_shipping,
					cart.shipping_group_seq,
					cart.shipping_set_seq,
					cart.shipping_set_code,
					cart.shipping_hop_date,
					cart.shipping_store_seq,
					cart.shipping_prepay_info,
					cart_opt.*,
					(select shipping_calcul_type from fm_shipping_grouping where shipping_group_seq = goods.shipping_group_seq) as shipping_calcul_type,
					goods.display_terms,
					goods.display_terms_text,
					goods.display_terms_color,
					goods.display_terms_begin,
					goods.display_terms_end,
					goods.display_terms_type,
					goods.possible_shipping_date,
					goods.possible_shipping_text,
					goods.multi_discount_policy
				FROM
					".$this->tb_cart_option." cart_opt
					left join ".$this->tb_cart." cart on cart.cart_seq = cart_opt.cart_seq
					,fm_goods goods
					,fm_goods_option goods_opt
					,fm_provider pv
				WHERE
						cart.distribution=?
					AND cart.goods_seq = goods.goods_seq
					AND goods.provider_seq = pv.provider_seq
					".$goodsStatusSql."
					AND (goods.goods_view = 'look'
					OR ( goods.display_terms = 'AUTO' and goods.display_terms_begin <= '".$now_date."' and goods.display_terms_end >= '".$now_date."'))
					AND cart.goods_seq = goods_opt.goods_seq
					AND ifnull(cart_opt.option1,'') = ifnull(goods_opt.option1,'')
					AND ifnull(cart_opt.option2,'') = ifnull(goods_opt.option2,'')
					AND ifnull(cart_opt.option3,'') = ifnull(goods_opt.option3,'')
					AND ifnull(cart_opt.option4,'') = ifnull(goods_opt.option4,'')
					AND ifnull(cart_opt.option5,'') = ifnull(goods_opt.option5,'')";
			if($where_query){
				$query .= ' AND '.implode(' AND ', $where_query);
			}
			# 배송방법 정렬순서(택배>퀵>직접수령) field(cart_opt.shipping_method,'postpaid','direct','quick') @2015-09-21 pjm
			//$query		.= " ORDER BY pv.deli_group,cart_opt.shipping_method, goods.provider_seq, cart.goods_seq,cart_opt.cart_option_seq ASC";
			$query		.= " ORDER BY pv.deli_group,field(cart_opt.shipping_method,'delivery','each_delivery','postpaid','direct','quick'), goods.provider_seq,goods.shipping_policy, cart.goods_seq,cart_opt.cart_option_seq ASC";

			$query		= $this->db->query($query,$where_arr);
			//debug($this->db->last_query());
			$cart_list	= $query->result_array();
			$num_rows	= $query->num_rows();
		}

		foreach ($cart_list as $k => $row){
			// 배송적책이 변경된 경우 강제 변환
			if		($row['shipping_method'] == 'delivery' && $row['shipping_policy'] == 'goods')
				$row['shipping_method']	= 'each_delivery';
			elseif	($row['shipping_method'] == 'each_delivery' && $row['shipping_policy'] == 'shop')
				$row['shipping_method']	= 'delivery';

			$goods_ea[$row['goods_seq']]	+= $row['ea'];
			$r_cart_option[]				= $row;
			$cart_list[$k]					= $row;
		}

		/*
		상품 이미지
		fm_goods_image에 thumbCart가 중복될 경우 장바구니 row가 증가되어 아래와 같이 상품 이미지는 따로 가져오기.
		*/
		$_goods_images = array();
		foreach($goods_ea as $goods_seq=>$ea){
			$_images = $this->goodsmodel->get_goods_image($goods_seq,array('cut_number'=>1,'image_type'=>'thumbCart'));
			$_goods_images[$goods_seq] = $_images['1']['thumbCart']['image'];
		}

		foreach ($cart_list as $row){

			$opt_no = $row['opt_no'];

			// 특수문자 처리
			//예약 상품의 경우 문구를 넣어준다 2016-11-07
			$row['goods_name']	= get_goods_pre_name($row,false,true);
			$row['goods_name']	= strip_tags(str_replace(array("\"","'"),'',$row['goods_name']));
			$row['image']		= $_goods_images[$row['goods_seq']];

			if (trim($row['multi_discount_policy'])){
				$row['multi_discount_policy']	= json_decode($row['multi_discount_policy'], 1);
			}else{
				$row['multi_discount_policy']	= '';
			}

			// 카테고리정보
			$tmparr1	= array();
			$tmparr2	= array();
			if($r_goods[$row['goods_seq']]['r_category']){
				$row['r_category']	= $r_goods[$row['goods_seq']]['r_category'];
			}else{
				$categorys			= $this->goodsmodel->get_goods_category($row['goods_seq']);
				foreach($categorys as $key => $data){
					$tmparr			= $this->categorymodel->split_category($data['category_code']);

					//대표카테고리 추출 @nsg 2016-02-15
					if($data['link']){
						foreach($tmparr as $p_cate)	$tmparr1[]	= $p_cate;
						$row['p_category']						= $tmparr1;
					}

					foreach($tmparr as $cate)	$tmparr2[]	= $cate;
				}
				if($tmparr2){
					$tmparr2									= array_values(array_unique($tmparr2));
					$row['r_category']							= $tmparr2;
					$r_goods[$row['goods_seq']]['r_category']	= $tmparr2;
				}
			}

			// 브랜드정보
			$tmparr2	= array();
			if	($r_goods[$row['goods_seq']]['r_brand']){
				$row['r_brand']			= $r_goods[$row['goods_seq']]['r_brand'];
			}else{
				$brands					= $this->goodsmodel->get_goods_brand($row['goods_seq']);
				foreach($brands as $key => $data){
					$tmparr				= $this->brandmodel->split_brand($data['category_code']);
					foreach($tmparr as $cate)	$tmparr2[]	= $cate;
				}
				if	($tmparr2){
					$tmparr2									= array_values(array_unique($tmparr2));
					$row['r_brand']								= $tmparr2;
					$r_goods[$row['goods_seq']]['r_brand']		= $tmparr2;
				}
			}

			// 옵션 별 최대 구매,최소 구매수량
			$option_name												= $row['option1'] . ' ^^ '
																		. $row['option2'] . ' ^^ '
																		. $row['option3'] . ' ^^ '
																		. $row['option4'] . ' ^^ '
																		. $row['option5'];
			$option_title												= $row['title1'] . ' ^^ '
																		. $row['title2'] . ' ^^ '
																		. $row['title3'] . ' ^^ '
																		. $row['title4'] . ' ^^ '
																		. $row['title5'];
			$r_goods[$row['goods_seq']]['ea_for_option'][$option_name]	+= $row['ea'];
			$r_goods[$row['goods_seq']]['option_title']					= $option_title;
			$r_goods[$row['goods_seq']]['min_purchase_ea']				= $row['min_purchase_ea'];
			$r_goods[$row['goods_seq']]['max_purchase_ea']				= $row['max_purchase_ea'];

			// 해외배송상품
			$r_goods[$row['goods_seq']]['option_international_shipping_status']	= $row['option_international_shipping_status'];

			if(in_array($tmp_arr['cart_table'],array("admin","person","reorder","rematch")) && $tmp_arr['mode'] == "tmp"){
				// 추가옵션
				$etc_ptions			= $this->goodsmodel->get_cart_tmp_etc_option($opt_no,$tmp_arr);
				$cart_suboptions	= $etc_ptions['suboption'];
				$cart_inputs		= $etc_ptions['inputoption'];
			}else{
				// 추가옵션
				$cart_suboptions		= $this->cartmodel->get_cart_suboption_by_cart_option($row['cart_option_seq']);

				// 추가입력사항
				$cart_inputs			= $this->cartmodel->get_cart_input_by_cart_option($row['cart_option_seq']);
			}

			// 연결 상품 검증
			$row['package_error']	= false;
			if($row['package_yn'] == 'y'){

				for($cpi=1;$cpi<=5;$cpi++){
					if( $row['package_option_seq'.$cpi] ){
						$params_check = array(
							'mode'					=> 'option',
							'goods_seq'				=> $row['goods_seq'],
							'option_seq'			=> $row['option_seq'],
							'package_option_seq'	=> $row['package_option_seq'.$cpi],
							'package_option'		=> $row['package_option'.$cpi],
							'no'					=> $cpi
						);
						if( !check_package_option($params_check) ){
							$row['package_error_type']	=  'option';
							$row['package_error']	=  true;
						}
					}
				}
			}
			if($row['package_yn_suboption'] == 'y'){
				foreach($cart_suboptions as $key_suboption => $data_suboption)
				{
					if($data_suboption['package_option_seq1']){
						$params_check = array(
							'mode'					=> 'suboption',
							'goods_seq'				=> $row['goods_seq'],
							'option_seq'			=> $data_suboption['suboption_seq'],
							'package_option_seq'	=> $data_suboption['package_option_seq1'],
							'package_option'		=> $data_suboption['package_option1'],
							'no'					=> 1
						);
						if( !check_package_option($params_check) ){
							$data_suboption['package_error_type']	=  'suboption';
							$data_suboption['package_error']	=  true;
							$row['package_error_type']	=  'suboption';
							$row['package_error']	=  true;
						}
					}
					$cart_suboptions[$key_suboption] = $data_suboption;
				}
			}

			$row['org_price']		= $row['price'];
			$row['tot_ori_price']	+= $row['price'] * $row['ea'];

			//----> sale library 적용
			unset($param,$row['reserve'],$row['point']);
			$param['consumer_price']		= $row['consumer_price'];
			$param['price']					= $row['price'];
			$param['ea']					= $row['ea'];
			$param['goods_ea']				= $goods_ea[$row['goods_seq']];
			$param['category_code']			= $row['r_category'];
			$param['goods_seq']				= $row['goods_seq'];
			$param['goods']					= $row;
			$this->sale->set_init($param);
			$sales	= $this->sale->calculate_sale_price($applypage);

			$row['basic_sale']				= $sales['one_sale_list']['basic'];
			$row['event_sale_target']		= ($sales['target_list']['event'] == 1) ? 'consumer_price' : 'price';
			$row['event_sale']				= $sales['one_sale_list']['event'];
			$row['multi_sale']				= $sales['one_sale_list']['multi'];
			$row['price']					= $sales['one_result_price'];
			$row['event']					= $this->sale->cfgs['event'];
			$row['eventEnd']				= $sales['eventEnd'];
			$this->sale->reset_init();
			unset($sales);
			//<---- sale library 적용 ( 마일리지과 포인트는 실결제금액 기준이므로 이후 할인 적용 후 구함 )

			$row['ori_price']	= $row['price'];
			$row['tot_price']	+= $row['price'] * $row['ea'];

			// 로우 길이
			$r_goods[$row['goods_seq']]['cnt']++;
			if($r_goods[$row['goods_seq']]['cnt'] == 1){
				$row['first'] = 1;
			}else{
				$row['first'] = 0;
			}

			//suboption point
			$sub_total_sale_price	= 0;
			foreach($cart_suboptions as $key_suboption => $data_suboption)
			{
				if($this->userInfo['member_seq']){
					// 포인트
					$data_suboption['point'] = (int) $this->goodsmodel->get_point_with_policy($data_suboption['price']);
					$data_suboption['point'] = $data_suboption['point'] * $data_suboption['ea'];
					// 마일리지
					$data_suboption['reserve'] = $this->goodsmodel->get_reserve_with_policy($row['sub_reserve_policy'],$data_suboption['price'],$cfg_reserve['default_reserve_percent'],$data_suboption['reserve_rate'],$data_suboption['reserve_unit'],$data_suboption['reserve']);
					$data_suboption['reserve'] = $data_suboption['reserve'] * $data_suboption['ea'];
				}else{
					$data_suboption['reserve'] = 0;
					$data_suboption['point'] = 0;
				}
				$cart_suboptions[$key_suboption] = $data_suboption;

				// 상품별
				$r_goods[$row['goods_seq']]['reserve'] += (int) $data_suboption['reserve'];
				$r_goods[$row['goods_seq']]['point'] += (int) $data_suboption['point'];
				$r_goods[$row['goods_seq']]['ea'] += (int) $data_suboption['ea'];
				$r_goods[$row['goods_seq']]['price'] += $data_suboption['price'] * $data_suboption['ea'];
				$row['all_ea'] += (int) $data_suboption['ea'];

				// 추가 마일리지
				$row['suboption_point'] += $data_suboption['point'];
				$row['suboption_reserve'] += $data_suboption['reserve'];

				$row['tot_ori_price'] += $data_suboption['price'] * $data_suboption['ea'];
				$row['tot_price'] += $data_suboption['price'] * $data_suboption['ea'];

				// 옵션별 구매수량
				$option_name = $data_suboption['suboption_title'].' ^^ '.$data_suboption['suboption'];
				$r_goods[$row['goods_seq']]['ea_for_suboption'][$option_name] += $data_suboption['ea'];
				$r_goods[$row['goods_seq']]['suboption_title'] = $data_suboption['suboption_title'].':'.$data_suboption['suboption'];

				// 장바구니 카운트
				$r_goods[$row['goods_seq']]['cnt']++;
				$sub_total_sale_price	+= $data_suboption['price'] * $data_suboption['ea'];
			}

			$row['cart_suboptions'] = $cart_suboptions;
			$row['cart_inputs'] = $cart_inputs;

			$shipping = $this->goodsmodel->get_goods_delivery($row,$row['ea']);
			# 실제배송업체
			$row['shipping_provider_seq'] = $shipping['provider_seq'];
			$row['shipping']			  = $shipping;
			$row['goods_name'] = strip_tags(str_replace(array("\"","'"),'',$row['goods_name']));
			$row['goods_shipping'] = 0;
			if($row['shipping_policy'] == 'goods'){
				$row['goods_shipping'] = $shipping['price'];
			}

			// 상품별 주문배송방법 선택 - 배송그룹 정의(입점사별 기본,개별정책)
			if($row['shipping_policy'] == 'goods'){
				$row['shipping_group'] = $row['shipping_method'].$row['goods_seq'];
			}else{
				$row['shipping_group'] = $row['shipping_method'].$row['shipping_provider_seq'];
			}

			// 새로운 shipping_grp 지정 :: 2016-08-08 lwh
			$each = '';
			if($row['shipping_calcul_type'] == 'each')	$each = '_'.$row['cart_option_seq'];
			$row['shipping_group'] = $row['goods_shipping_group_seq'].'_'.$row['shipping_set_seq'].'_'.$row['shipping_set_code'].$each;

			// 새로운 shipping_set_name 을 지정 :: 2016-10-26 lwh
			$row['shipping_method_name'] = $this->shippingmodel->ship_set_code[$row['shipping_set_code']];

			$shop_total_price += $row['tot_price'];
			if($row['tax']!="tax"){
				$shop_total_price_exempt += $row['tot_price'];
			}

			$provider_sum_goods_price[$shipping['provider_seq']] += $row['tot_price'];
			$shipping_group_sum_goods_price[$row['shipping_group']] += $row['tot_price'];

			// 상품별 저장
			$r_goods[$row['goods_seq']]['goods_name']				= $row['goods_name'];
			$r_goods[$row['goods_seq']]['provider_seq']				= $row['provider_seq'];
			$r_goods[$row['goods_seq']]['shipping_group']			= $row['shipping_group'];
			$r_goods[$row['goods_seq']]['price']					+= $row['price'] * $row['ea'];
			$r_goods[$row['goods_seq']]['ea']						+= $row['ea'];
			$r_goods[$row['goods_seq']]['option_ea']				+= $row['ea'];
			$r_goods[$row['goods_seq']]['shipping_weight_policy']	= $row['shipping_weight_policy'];
			$r_goods[$row['goods_seq']]['goods_weight']				= $row['goods_weight'];
			$r_goods[$row['goods_seq']]['shipping_policy']			= $row['shipping_policy'];
			$r_goods[$row['goods_seq']]['goods_shipping_policy']	= $row['goods_shipping_policy'];
			$r_goods[$row['goods_seq']]['unlimit_shipping_price']	= $row['unlimit_shipping_price'];
			$r_goods[$row['goods_seq']]['limit_shipping_price']		= $row['limit_shipping_price'];
			$r_goods[$row['goods_seq']]['limit_shipping_ea']		= $row['limit_shipping_ea'];
			$r_goods[$row['goods_seq']]['limit_shipping_subprice']	= $row['limit_shipping_subprice'];
			$r_goods[$row['goods_seq']]['shipping_weight_policy']	= $row['shipping_weight_policy'];
			$r_goods[$row['goods_seq']]['reserve']					+= (int) $row['reserve'];
			$r_goods[$row['goods_seq']]['point']					+= (int) $row['point'];
			$r_goods[$row['goods_seq']]['tax']						= $row['tax'];
			$r_goods[$row['goods_seq']]['shipping_provider_seq']	= $shipping['provider_seq'];
			$r_goods[$row['goods_seq']]['event']					= $row['event'];
			$r_goods[$row['goods_seq']]['shipping_group_seq']		= $row['shipping_group_seq'];
			$r_goods[$row['goods_seq']]['shipping_set_seq']			= $row['shipping_set_seq'];
			$r_goods[$row['goods_seq']]['shipping_set_code']		= $row['shipping_set_code'];
			$r_goods[$row['goods_seq']]['shipping_hop_date']		= $row['shipping_hop_date'];
			$r_goods[$row['goods_seq']]['shipping_prepay_info']		= $row['shipping_prepay_info'];

			// 배송 그룹별 저장
			$r_shipping_group[$row['shipping_group']]['goods_seq'][]				= $row['goods_seq'];
			$r_shipping_group[$row['shipping_group']]['category_code'] = $row['r_category'];
			$r_shipping_group[$row['shipping_group']]['brand_code'] = $row['r_brand'];
			$r_shipping_group[$row['shipping_group']]['provider_seq'] = $row['provider_seq'];
			$r_shipping_group[$row['shipping_group']]['shipping_policy'] = $row['shipping_policy'];
			$r_shipping_group[$row['shipping_group']]['goods_shipping_policy'] = $row['goods_shipping_policy'];
			$r_shipping_group[$row['shipping_group']]['unlimit_shipping_price'] = $row['unlimit_shipping_price'];
			$r_shipping_group[$row['shipping_group']]['limit_shipping_price'] = $row['limit_shipping_price'];
			$r_shipping_group[$row['shipping_group']]['limit_shipping_ea'] = $row['limit_shipping_ea'];
			$r_shipping_group[$row['shipping_group']]['limit_shipping_subprice'] = $row['limit_shipping_subprice'];
			$r_shipping_group[$row['shipping_group']]['shipping_weight_policy'] = $row['shipping_weight_policy'];
			$r_shipping_group[$row['shipping_group']]['price'] += $row['tot_price'];
			$r_shipping_group[$row['shipping_group']]['unit_price'] += $row['price'];
			$r_shipping_group[$row['shipping_group']]['ea'] += $row['ea'];
			$r_shipping_group[$row['shipping_group']]['tax'] = $row['tax'];
			$r_shipping_group[$row['shipping_group']]['shipping_provider_seq'] = $shipping['provider_seq'];
			$r_shipping_group[$row['shipping_group']]['shipping_method'] = $row['shipping_method'];

			//티켓상품저장@2013-10-22
			$r_goods[$row['goods_seq']]['socialcp_input_type'] = $row['socialcp_input_type'];
			$r_goods[$row['goods_seq']]['socialcp_cancel_type'] = $row['socialcp_cancel_type'];
			$r_goods[$row['goods_seq']]['socialcp_use_return'] = $row['socialcp_use_return'];
			$r_goods[$row['goods_seq']]['socialcp_use_emoney_day'] = $row['socialcp_use_emoney_day'];
			$r_goods[$row['goods_seq']]['socialcp_use_emoney_percent'] = $row['socialcp_use_emoney_percent'];
			$r_goods[$row['goods_seq']]['social_goods_group'] = $row['social_goods_group'];//티켓상품그룹

			$r_goods[$row['goods_seq']]['socialcp_cancel_use_refund'] = $row['socialcp_cancel_use_refund'];
			$r_goods[$row['goods_seq']]['socialcp_cancel_payoption'] = $row['socialcp_cancel_payoption'];
			$r_goods[$row['goods_seq']]['socialcp_cancel_payoption_percent'] = $row['socialcp_cancel_payoption_percent'];

			// 과세 비과세
			if($row['tax']=="exempt"){
				$exempt_chk++;
			}

			// 총상품금액
			$total				+= $row['price'];
			// 총 상품금액
			$total_sale_price	+= $row['price'] * $row['ea'] + $sub_total_sale_price;

			// 총수량
			$total_ea	+= $row['ea'];

			$result[] = $row;
		}

		### 과세 비과세
		if($num_rows==$exempt_chk){
			$tax_type = "exempt";
		}else if($exempt_chk == 0){
			$tax_type = "tax";
		}else{
			$tax_type = "mix";
		}

		// 배송비계산
		$row = array();
		foreach($r_shipping_group as $shipping_group => $row){
			$goods_seq		= (is_array($row['goods_seq']))?$row['goods_seq'][0]:$row['goods_seq'];
			$param = $row;
			$param['price'] = $row['unit_price'];

			$shipping = $this->goodsmodel->get_goods_delivery($param,$row['ea'],$shipping_group);
			$provider_shipping_policy[$shipping['provider_seq']]						= $shipping;
			$probider_shipping_policy2[$shipping['provider_seq']][$shipping['policy']]	= $shipping;

			// 상품별 주문배송방법 선택 배송정책
			$shipping_group_policy[$shipping_group] = $shipping;

			$row['goods_shipping'] = 0;
			if($row['shipping_policy'] == 'shop' && preg_match('/delivery/',$shipping_group) ){
				$shop_shipping_policy = $shipping;
				$default_box_ea = true;
			}else if($row['shipping_policy'] == 'goods' ) {

				$r_goods[$goods_seq]['goods_shipping'] = $shipping['price'];
				$box_ea += $shipping['box_ea'];
				$provider_box_ea[$row['provider_seq']] += $shipping['box_ea'];
				$shipping_price['goods'] += $shipping['price'];
				$provider_shipping_price[$row['shipping_provider_seq']]['goods'] += $shipping['price'];

				// 상품별 주문배송방법 선택 개별 배송비
				$shipping_group_price[$shipping_group]['goods'] += $shipping['price'];
			}

			if($row['tax']=="exempt"){
				$shipping_exempt += $shipping['price'];
			}
		}

		if($shop_total_price && $shop_shipping_policy['free']){
			if($shop_shipping_policy['free'] <= $shop_total_price){
				$shipping_price['shop'] = 0;
			}
		}

		foreach($provider_shipping_policy as $provider_seq=>$row){
			if($provider_sum_goods_price[$provider_seq] && $row['free'] && $row['free'] <= $provider_sum_goods_price[$provider_seq]){
				$provider_shipping_price[$row['provider_seq']]['shop'] += 0;
			}else{
				if	($probider_shipping_policy2[$provider_seq]['shop']['price'] > 0)
					$provider_shipping_price[$row['provider_seq']]['shop'] += $probider_shipping_policy2[$provider_seq]['shop']['price'];
			}
		}


		// 상품별 주문배송방법 선택
		foreach($shipping_group_policy as $shipping_group=>$row){

			if(preg_match('/delivery/',$shipping_group)){

				if( $row['policy'] == 'shop' ){
					if($shipping_group_sum_goods_price[$shipping_group] && $row['free'] && $row['free'] <= $shipping_group_sum_goods_price[$shipping_group]){
						$shipping_price['shop'] += 0;
						$shipping_groupg_price[$shipping_group]['shop'] += 0;
					}else{
						$shipping_price['shop'] += $row['policy']=='shop' ? $row['price'] : 0;
						$shipping_group_price[$shipping_group]['shop'] += $row['policy']=='shop' ? $row['price'] : 0;
					}
				}
			}else{
				if( $row['policy'] == 'shop' ){
					$shipping_group_price[$shipping_group]['postpaid'] = $row['postpaid'];
					$shipping_group_price[$shipping_group]['summary'] = $row['summary'];
					//$shipping_price['shop'] += $row['postpaid'];
				}
			}
		}

		$shipping_provider_division_list = array();

		$shipping_provider_division_list = array();

		foreach($result as $k=>$row){

			if(!$k || $prev_shipping_provider_seq!=$row['shipping_provider_seq'] || $prev_shipping_group!=$row['shipping_group'] ){
				if(!in_array($row['shipping_group'], $shipping_provider_division_list)){
					$result[$k]['shipping_provider_division'] = true;
				}
			}
			$prev_shipping_provider_seq = $row['shipping_provider_seq'];
			$prev_shipping_group		= $row['shipping_group'];

			if(!in_array($row['shipping_group'], $shipping_provider_division_list)){
				array_push($shipping_provider_division_list, $row['shipping_group']);
			}

			$shipping_company_cnt[$row['shipping_provider_seq']]++;
			$shipping_company_cnt[$row['shipping_group']]++;
			if(is_array($row['cart_suboptions']) && count($row['cart_suboptions']) > 0){
				$shipping_company_cnt[$row['shipping_provider_seq']] += count($row['cart_suboptions']);
				$shipping_company_cnt[$row['shipping_group']] += count($row['cart_suboptions']);
			}

		}


		if( $shop_total_price && $default_box_ea)$box_ea += 1;
		$total_price = $shop_total_price + array_sum($shipping_price);
		//$total_price = $total + array_sum($shipping_price);

		$exempt_price = $shop_total_price_exempt;
		$arr = array(
			'data_goods'=>$r_goods,
			'total_reserve'=>$total_reserve,
			'total_point'=>$total_point,
			'taxtype'=>$tax_type,
			'exempt_shipping'=>$shipping_exempt,
			'exempt_price'=>$exempt_price,
			'list'=>$result,
			'total'=>$total,
			'total_sale_price'=>$total_sale_price,
			'total_ea'=>$total_ea,
			'shipping_price'=>$shipping_price,
			'shipping_company_cnt'=>$shipping_company_cnt,
			'provider_shipping_price'=>$provider_shipping_price,
			'provider_shipping_policy'=>$provider_shipping_policy,
			'provider_box_ea'=>$provider_box_ea,
			'shop_shipping_policy'=>$shop_shipping_policy,
			'total_price'=>$total_price,
			'promocodeSale'=>$promocodeSale,
			'box_ea'=>$box_ea
		);

		// 상품별 주문배송방법 선택
		$arr['shipping_group_price'] = $shipping_group_price;
		$arr['shipping_group_policy'] = $shipping_group_policy;

		return $arr;
	}

	function insert_cart_alloption($cart_seq,$inputs,$shipping_method='')
	{
		$this->load->helper('copy');
		$this->load->library('upload');

		$catetmp = $this->goodsmodel->get_goods_category($_POST['goodsSeq']);
		unset($category);
		foreach($catetmp as $caterow) {
			if( strlen($caterow['category_code']) > 4) {
				if(strlen($caterow['category_code']) == 16) {
					$category[] = substr($caterow['category_code'], 0, 16);
					$category[] = substr($caterow['category_code'], 0, 12);
					$category[] = substr($caterow['category_code'], 0, 8);
					$category[] = substr($caterow['category_code'], 0, 4);
				}elseif(strlen($caterow['category_code']) == 12) {
					$category[] = substr($caterow['category_code'], 0, 12);
					$category[] = substr($caterow['category_code'], 0, 8);
					$category[] = substr($caterow['category_code'], 0, 4);
				}elseif(strlen($caterow['category_code']) == 8) {
					$category[] = substr($caterow['category_code'], 0, 8);
					$category[] = substr($caterow['category_code'], 0, 4);
				}else{
					$category[] = substr($caterow['category_code'], 0, 4);
				}
			}else{
				$category[] = $caterow['category_code'];
			}
		}

		$brands = $this->goodsmodel->get_goods_brand($_POST['goodsSeq']);
		unset($brand_code);
		if($brands) foreach($brands as $bkey => $branddata){
			if( $branddata['link'] == 1 ){
				$brand_codear= $this->brandmodel->split_brand($branddata['category_code']);
				$brand_code[] = $brand_codear[0];
			}
		}

		$cart = $this->cartmodel->catalog($this->is_adminOrder);
		unset($insert_data,$max);
		foreach($_POST['optionEa'] as $k1 => $ea){
			unset($insert_data,$max);
			for($i=0;$i<5;$i++){
				if( !isset($_POST['option'][$i][$k1]) || !$_POST['option'][$i][$k1] ) $_POST['option'][$i][$k1] = "";
				if( !isset($_POST['optionTitle'][$i][$k1]) || !$_POST['optionTitle'][$i][$k1] ) $_POST['optionTitle'][$i][$k1] = null;
			}
			$insert_data['option1']		= $_POST['option'][0][$k1];
			$insert_data['title1']		= $_POST['optionTitle'][0][$k1];
			$insert_data['option2']		= $_POST['option'][1][$k1];
			$insert_data['title2']		= $_POST['optionTitle'][1][$k1];
			$insert_data['option3']		= $_POST['option'][2][$k1];
			$insert_data['title3']		= $_POST['optionTitle'][2][$k1];
			$insert_data['option4']		= $_POST['option'][3][$k1];
			$insert_data['title4']		= $_POST['optionTitle'][3][$k1];
			$insert_data['option5']		= $_POST['option'][4][$k1];
			$insert_data['title5']		= $_POST['optionTitle'][4][$k1];
			$insert_data['ea'] 			= $ea;
			$insert_data['cart_seq']	= $cart_seq;

			// 배송방법 저장
			if(!$shipping_method){
				$insert_data['shipping_method'] = $_POST['shipping_method'];
			}else{
				$insert_data['shipping_method'] = $shipping_method;
			}

			if(!$insert_data['shipping_method']) $insert_data['shipping_method'] = "delivery";

			/**
			**/
			if( $this->session->userdata('cart_promotioncode_'.session_id()) ){

				$sc['whereis'] = " and promotion_input_serialnumber ='".$this->session->userdata('cart_promotioncode_'.session_id())."'";
				$promotioncode = $this->promotionmodel->get_data($sc);
				$promotioncode = $promotioncode[0];

				list($price,$reserve) = $this->goodsmodel->get_goods_option_price(
					$_POST['goodsSeq'],
					$insert_data['option1'],
					$insert_data['option2'],
					$insert_data['option3'],
					$insert_data['option4'],
					$insert_data['option5']
				);

				if( strstr($promotioncode['type'],'promotion') ){//일반코드
					$promotions = $this->promotionmodel->get_able_promotion_list($_POST['goodsSeq'], $category, $brand_code, $cart['total'], $this->session->userdata('cart_promotioncode_'.session_id()), $price, $ea );
				}else{//개별코드
					$promotions = $this->promotionmodel->get_able_download_list($_POST['goodsSeq'], $category, $brand_code, $cart['total'], $this->session->userdata('cart_promotioncode_'.session_id()), $price, $ea );
				}

				if( $promotions) {
					if($promotions['duplication_use'] == 1) {//중복할인은 무조건추가
							$insert_data['promotion_code_seq']	= $this->session->userdata('cart_promotioncodeseq_'.session_id());
							$insert_data['promotion_code_serialnumber']	= $this->session->userdata('cart_promotioncode_'.session_id());
					}else{//중복할인이 아닌경우 상품 할인가(판매가)가 최대값인 상품으로 처리함
						foreach($cart['list'] as $cartkey => $cartdata){

							if($_POST['goodsSeq'] == $cartdata['goods_seq']) {

								if( ($max[$_POST['goods_seq']] && $max[$_POST['goods_seq']] < $price) || !$max[$_POST['goods_seq']]){
									$insert_data['promotion_code_seq']	= $this->session->userdata('cart_promotioncodeseq_'.session_id());
									$insert_data['promotion_code_serialnumber']	= $this->session->userdata('cart_promotioncode_'.session_id());

									$max[$_POST['goods_seq']] = $price;
									$upsql = "update fm_cart_option set promotion_code_seq=null,promotion_code_serialnumber=null where cart_seq = {$cartdata['cart_seq']} ";
									$this->db->query($upsql);
								}
							}
						}

						if($cart['list'] && !$max[$_POST['goods_seq']]){//최초상품인경우

							$insert_data['promotion_code_seq']	= $this->session->userdata('cart_promotioncodeseq_'.session_id());
							$insert_data['promotion_code_serialnumber']	= $this->session->userdata('cart_promotioncode_'.session_id());
						}
					}
				}
			}

			$this->db->insert($this->tb_cart_option, $insert_data);

			// 첫번째상품 일련번호구하기
			$cart_option_seq = $this->db->insert_id();
			if($k1 == 0) $first_cart_option_seq = $this->db->insert_id();

			// 장바구니 추가입력항목
			if( isset($_POST['inputsValue']) && is_array($_POST['inputsValue'][0])){
				// 2014-12-18 옵션 개편 후 (ocw)
				unset($insert_data);
				$path = "./data/order/";
				if( isset($_POST['inputsValue']) ){
					foreach($inputs as $key_input => $data_input){
						if( $data_input ){
							if($data_input['input_form']=='file'){

								$file_path = $_POST['inputsValue'][$key_input][$k1];

								/* 이미지파일 확장자 */
								$this->arrImageExtensions = array('jpg','jpeg','png','gif','bmp','tif','pic');
								$this->arrImageExtensions = array_merge($this->arrImageExtensions,array_map('strtoupper',$this->arrImageExtensions));

								if (preg_match("/^\/data\/tmp\//i", str_replace(realpath(ROOTPATH), "", realpath($file_path))) && file_exists($file_path)) {
									$file_ext = end(explode('.', $file_path));
									if( in_array($file_ext,$this->arrImageExtensions) ){
										// 파일명 변경안되게 수정 2015-04-22 leewh
										//$fname = $cart_seq.'_'.$cart_option_seq.'_'.$k.".".$file_ext;
										$fname = str_replace("data/tmp/","",$file_path);
										$file = $path.$fname;
										if (file_exists($file)) {
											$ori_fname = basename($fname, ".".$file_ext);
											$fname = $ori_fname.'_'.$cart_seq.'_'.$cart_option_seq.".".$file_ext;
										}

										$copyResult = copyFile($file_path, $path.$fname);
										if ($copyResult['result']) {
											$insert_data['type'] = 'file';
											$insert_data['input_title'] = $data_input['input_name'];
											$insert_data['input_value'] = $fname;
											$insert_data['cart_seq'] = $cart_seq;
											$insert_data['cart_option_seq']	= $cart_option_seq;
											$this->db->insert($this->tb_cart_input, $insert_data);
										}
									}
								}else/* if ($file_path)*/{
									$insert_data['type'] = 'file';
									$insert_data['input_title'] = $_POST['inputsTitle'][$key_input][$k1];
									$insert_data['input_value'] = $file_path ? $file_path : '';
									$insert_data['cart_seq'] = $cart_seq;
									$insert_data['cart_option_seq']	= $cart_option_seq;
									$this->db->insert($this->tb_cart_input, $insert_data);
								}
							}else{
								$insert_data['type'] = 'text';
								$insert_data['input_title'] = $_POST['inputsTitle'][$key_input][$k1];
								$insert_data['input_value'] = $_POST['inputsValue'][$key_input][$k1];
								$insert_data['cart_seq'] = $cart_seq;
								$insert_data['cart_option_seq']	= $cart_option_seq;
								$this->db->insert($this->tb_cart_input, $insert_data);
							}
						}
					}
				}
			}

			//장바구니 추가입력옵션
			if( isset($_POST['suboption']) && isset($_POST['suboption_addposition']) ){
				foreach($_POST['suboption_addposition'] as $k2 => $addposition){
					if	($k1 == $addposition){
						unset($insert_data);
						$insert_data['ea']				= $_POST['suboptionEa'][$k2];
						$insert_data['suboption_title']	= $_POST['suboptionTitle'][$k2];
						$insert_data['suboption']		= $_POST['suboption'][$k2];
						$insert_data['cart_seq'] 		= $cart_seq;
						$insert_data['cart_option_seq']	= $cart_option_seq;
						$this->db->insert($this->tb_cart_suboption, $insert_data);
					}
				}
			}
		}

		// 장바구니 추가입력항목
		if( isset($_POST['inputsValue']) && !is_array($_POST['inputsValue'][0])){
			// 2014-12-18 옵션 개편 전 (ocw)
			unset($insert_data);
			$path = "./data/order/";
			$config['upload_path'] = $path;
			$config['allowed_types'] = implode('|', array('jpg','jpeg','png','gif','bmp','tif','pic'));
			$config['max_size']	= $this->config_system['uploadLimit'];
			if( isset($_POST['inputsValue']) ){
				$i = 0;
				$k =0;
				foreach($inputs as $key_input => $data_input){
					if( $data_input ){
						if($data_input['input_form']=='file'){
							if($_FILES['inputsValue']['tmp_name']){
								if($_FILES['inputsValue']['tmp_name'][$k]){
									$config['file_name'] = $cart_seq . '_' . $first_cart_option_seq . '_' . $k;
									$this->upload->initialize($config, true);
									if ($this->upload->do_upload('inputsValue', $k)) {
										$insert_data['type'] = 'file';
										$insert_data['input_title'] = $data_input['input_name'];
										$insert_data['input_value'] = $this->upload->file_name;
										$insert_data['cart_seq'] = $cart_seq;
										$insert_data['cart_option_seq']	= $first_cart_option_seq;
										$this->db->insert($this->tb_cart_input, $insert_data);
									}
								}
								$k++;
							}else if ($_POST['inputsValue'][$i]){
								$insert_data['type'] = 'file';
								$insert_data['input_title'] = $data_input['input_name'];
								$insert_data['input_value'] = $_POST['inputsValue'][$i];
								$insert_data['cart_seq'] = $cart_seq;
								$insert_data['cart_option_seq']	= $first_cart_option_seq;
								$this->db->insert($this->tb_cart_input, $insert_data);
								$i++;
							}
						}else{
							$insert_data['type'] = 'text';
							$insert_data['input_title'] = $data_input['input_name'];
							$insert_data['input_value'] = $_POST['inputsValue'][$i];
							$insert_data['cart_seq'] = $cart_seq;
							$insert_data['cart_option_seq']	= $first_cart_option_seq;
							$this->db->insert($this->tb_cart_input, $insert_data);
							$i++;
						}
					}
				}
			}
		}

		//장바구니 추가입력옵션
		unset($insert_data);
		if( isset($_POST['suboption']) && !isset($_POST['suboption_addposition']) ){
			foreach($_POST['suboption'] as $k1 => $suboption){
				$insert_data['ea']				= $_POST['suboptionEa'][$k1];
				$insert_data['suboption_title']	= $_POST['suboptionTitle'][$k1];
				$insert_data['suboption']		= $suboption;
				$insert_data['cart_seq'] 		= $cart_seq;
				$insert_data['cart_option_seq']	= $first_cart_option_seq;
				$this->db->insert($this->tb_cart_suboption, $insert_data);
			}
		}
	}

	public function check_shipping_method($list)
	{
		$reload_cart = false;
		$arr_check_delivery = array();
		foreach($list as $key => $data)
		{
			$arr_check_delivery[$data['provider_seq']][$data['shipping_method']][] = $data['cart_option_seq'];
		}

		foreach($arr_check_delivery as $provider_seq => $data_check_delivery){
			if( $data_check_delivery['delivery'] && $data_check_delivery['postpaid']){
				$reload_cart = true;
			}
		}

		return array($reload_cart,$arr_check_delivery);
	}


	public function update_shipping_method_for_delivery($arr_check_delivery)
	{

		foreach($arr_check_delivery as $provider_seq => $data_check_delivery){
			if( $data_check_delivery['delivery'] && $data_check_delivery['postpaid']){
				foreach($data_check_delivery['postpaid'] as $cart_option_seq){
					$query = "update fm_cart_option set shipping_method='delivery' where cart_option_seq=?";
					$this->db->query($query,array($cart_option_seq));
				}
			}
		}
		return $re_load_cart;
	}

	/* 우측 퀵메뉴 장바구니 목록 반환 */
	function get_right_cart_list($page,$limit){
		$display_item = array();

		$this->load->library('sale');
		$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');

		//----> sale library 적용
		$applypage						= 'lately_scroll';
		$param['cal_type']				= 'list';
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		$now_date						= date('Y-m-d');

		if (!$page) $page = 1;
		$start = ($page-1)*$limit;
		$limit = "LIMIT {$start} , {$limit}";

		$add_and = "";
		$session_id = session_id();
		if($this->userInfo['member_seq']){
			$add_and = "AND cart.member_seq = {$this->userInfo['member_seq']}";
		}else{
			$add_and = "AND cart.session_id = '{$session_id}'";
		}

		$query = $this->db->query("
		SELECT
		goods.goods_seq,goods.sale_seq,goods.goods_name,goods_img.image, goods_opt.price, goods_opt.consumer_price, cart_opt.cart_option_seq,goods.display_terms,goods.display_terms_text,goods.display_terms_color
		FROM
		fm_cart_option cart_opt
		left join fm_cart cart on cart.cart_seq = cart_opt.cart_seq
		left join fm_goods_image goods_img on goods_img.cut_number = 1 AND goods_img.image_type = 'thumbScroll' AND cart.goods_seq = goods_img.goods_seq
		,fm_goods goods
		,fm_goods_option goods_opt
		WHERE cart.distribution='cart'
		AND cart.goods_seq = goods.goods_seq
		AND goods.goods_status = 'normal'
		AND (goods.goods_view = 'look'
		OR ( goods.display_terms = 'AUTO' and goods.display_terms_begin <= '".$now_date."' and goods.display_terms_end >= '".$now_date."'))
		AND cart.goods_seq = goods_opt.goods_seq
		AND ifnull(cart_opt.option1,'') = ifnull(goods_opt.option1,'')
		AND ifnull(cart_opt.option2,'') = ifnull(goods_opt.option2,'')
		AND ifnull(cart_opt.option3,'') = ifnull(goods_opt.option3,'')
		AND ifnull(cart_opt.option4,'') = ifnull(goods_opt.option4,'')
		AND ifnull(cart_opt.option5,'') = ifnull(goods_opt.option5,'') ".$add_and." ORDER BY cart.goods_seq,cart_opt.cart_option_seq ASC {$limit}");

		foreach ($query->result_array() as $data) {

			// 해당 상품의 전체 카테고리
			$category	= array();
			$tmp		= $this->goodsmodel->get_goods_category($data['goods_seq']);
			foreach($tmp as $row)	$category[]		= $row['category_code'];

			//----> sale library 적용
			unset($param, $sales);
			$param['option_type']				= 'option';
			$param['consumer_price']			= $data['consumer_price'];
			$param['price']						= $data['price'];
			$param['total_price']				= $data['price'];
			$param['ea']						= 1;
			$param['goods_ea']					= 1;
			$param['category_code']				= $category;
			$param['goods_seq']					= $data['goods_seq'];
			$param['goods']						= $data;
			$this->sale->set_init($param);
			$sales								= $this->sale->calculate_sale_price($applypage);
			$data['sale_price']					= $sales['result_price'];
			$this->sale->reset_init();
			//<---- sale library 적용

			//예약 상품의 경우 문구를 넣어준다 2016-11-07
			$data['goods_name']					= get_goods_pre_name($data);

			$display_item[] = $data;
		}
		return $display_item;
	}

	// 옵션 선택 모듈 ver0.1용 체크 함수
	public function chk_cart_ver_0_1($add_data=''){

		$this->load->model('goodsmodel');

		if(!$add_data) $add_data= $_POST;

		$seloptprefix			= trim($add_data['select_option_prefix']);
		$seloptsuffix			= trim($add_data['select_option_suffix']);
		$use_add_action_button	= $add_data[$seloptprefix.'use_add_action_button'.$seloptsuffix];
		$exist_option_seq		= $add_data[$seloptprefix.'exist_option_seq'.$seloptsuffix];
		$goods_seq				= $add_data[$seloptprefix.'option_select_goods_seq'.$seloptsuffix];
		$option					= $add_data[$seloptprefix.'option'.$seloptsuffix];
		$optionTitle			= $add_data[$seloptprefix.'optionTitle'.$seloptsuffix];
		$inputsValue			= $add_data[$seloptprefix.'inputsValue'.$seloptsuffix];
		$inputsTitle			= $add_data[$seloptprefix.'inputsTitle'.$seloptsuffix];
		$viewInputs				= $add_data[$seloptprefix.'viewInputs'.$seloptsuffix];
		$viewInputsTitle		= $add_data[$seloptprefix.'viewInputsTitle'.$seloptsuffix];
		$optionEa				= $add_data[$seloptprefix.'optionEa'.$seloptsuffix];
		$suboption				= $add_data[$seloptprefix.'suboption'.$seloptsuffix];
		$suboptionTitle			= $add_data[$seloptprefix.'suboptionTitle'.$seloptsuffix];
		$suboptionEa			= $add_data[$seloptprefix.'suboptionEa'.$seloptsuffix];
		$cfg_order				= config_load('order');

		// 버튼식 추가가 아닌 경우 입력옵션 추가
		if	($use_add_action_button == 'n' && is_array($viewInputs) && count($viewInputs) > 0){
			$inputsValue[0]		= $viewInputs;
			$inputsTitle[0]		= $viewInputsTitle;
		}

		// 현재 장바구니 개수 체크
		$cart_able_count	= 100;
		$cart_now_count		= $this->get_cart_count();
		if( $cart_now_count >= $cart_able_count ) {
			$result['status']	= false;
			//장바구니에 담을 수 있는 상품은 최대 100개 입니다.
			$result['errorMsg']	= getAlert('oc044', $cart_able_count);
			return $result;
		}

		// 상품체크
		if	(!$goods_seq){
			$result['status']	= false;
			//상품이 없습니다.
			$result['errorMsg']	= getAlert('oc026');
			return $result;
		}

		// 필수옵션 체크
		if	(!(is_array($option) && count($option) > 0
			&& is_array($optionEa) && count($optionEa) > 0)){
			$result['status']	= false;
			//장바구니에 담을 상품이 없습니다.
			$result['errorMsg']	= getAlert('oc027');
			return $result;
		}

		// 상품 정보
		$goods				= $this->goodsmodel->get_goods($goods_seq);
		// 상품 필수옵션 정보
		$goods_options		= $this->goodsmodel->get_goods_option($goods_seq);
		// 상품 입력옵션 정보
		$goods_inputs		= $this->goodsmodel->get_goods_input($goods_seq);
		// 상품 추가옵션 정보
		$goods_suboptions	= $this->goodsmodel->get_goods_suboption($goods_seq);

		// 희망배송일 체크 :: 2016-07-25 lwh - 굳이 체크할 필요가 있나 싶음. 하여서 주석
		/*
		$this->load->model('shippingmodel');
		$grp_seq = $goods['shipping_group_seq'];
		$set_seq = $add_data['shipping_method'];
		if($grp_seq && $set_seq){
			$set_info = $this->shippingmodel->get_shipping_set($set_seq, 'shipping_set_seq');
			if($set_info['hopeday_required'] == 'Y' && !$add_data['hop_select_date']){
				$err_msg					= '희망배송일을 선택해주세요.';
				$result['status']			= false;
				$result['errorMsg']			= $err_msg;
				return $result;
			}
		}
		*/

		// 필수옵션 재고 체크
		$checked_options	= array();
		foreach($option as $grp_idx => $opt){
			$opttitle				= '';
			if( $opt[0] ) foreach($opt as $optKey => $optVal){
				if($optionTitle[$grp_idx][$optKey])
					$opttmp		= $optionTitle[$grp_idx][$optKey] . ':';
				$tmpopttitle[]	= $opttmp . $optVal;
			}
			if(count($tmpopttitle) > 0)
				$opttitle			= implode(', ', $tmpopttitle);
			$checked_key			= $grp_idx;
			$ea						= $optionEa[$grp_idx];

			// 같은 필수옵션을 여러개 선택 시 합친 재고로 체크
			if	(in_array($opttitle, $checked_options)){
				$checked_key						= array_search($opttitle, $checked_options);
				$ea									+= $checked_options_ea[$checked_key];
			}

			$chk	= check_stock_option(	$goods_seq, $opt[0], $opt[1], $opt[2],
											$opt[3], $opt[4], $ea, $cfg_order,
											'view_stock'	);
			if	( $chk['stock'] < 0 ){
				$goodsName	= addslashes($goods['goods_name']);
				if	($opttitle)	$goodsName	.= ' (' . addslashes($opttitle) . ')';
				//구매가능재고를 초과한 상품을 알려 드립니다.<br/> $goodsName | $ea개 → 구매가능재고는 $chk['sale_able_stock']개입니다.
				$err_msg					= getAlert('oc028', array($goodsName, $ea, $chk['sale_able_stock']));

				$result['status']			= false;
				$result['errorMsg']			= $err_msg;
				return $result;
			}

			$checked_options[$checked_key]		= $opttitle;
			$checked_options_ea[$checked_key]	= $ea;

			// 필수 입력옵션 체크
			if	($goods_inputs){
				foreach($goods_inputs as $k => $data){
					if	($data['input_require'] == 1){
						if	(!$inputsTitle[$grp_idx] || !$inputsValue[$grp_idx]){
							$result['status']			= false;
							$result['errorMsg']			= addslashes($data['input_name'])
														. ' '.getAlert('oc029'); //옵션은 필수입니다.
							return $result;
						}

						$idx	= array_search($data['input_name'], $inputsTitle[$grp_idx]);
						if	(!is_numeric($idx) || !$inputsValue[$grp_idx][$idx]){
							$result['status']			= false;
							$result['errorMsg']			= addslashes($data['input_name'])
														. ' '.getAlert('oc029'); //옵션은 필수입니다.
							return $result;
						}
					}
				}
			}

			// 필수 추가옵션 선택 및 재고 체크
			if	($goods_suboptions){
				// 필수 추가옵션 체크
				foreach($goods_suboptions as $k => $data){
					if	($data[0]['sub_required'] == 'y'){
						$sub_tit	= $data[0]['suboption_title'];
						if	(!$suboptionTitle[$grp_idx] || !$suboption[$grp_idx]){
							$result['status']			= false;
							$result['errorMsg']			= addslashes($sub_tit)
														. ' '.getAlert('oc029'); //옵션은 필수입니다.
							return $result;
						}

						$idx	= array_search($sub_tit, $suboptionTitle[$grp_idx]);
						if	(!is_numeric($idx) || !$suboption[$grp_idx][$idx]){
							$result['status']			= false;
							$result['errorMsg']			= addslashes($sub_tit)
														. ' '.getAlert('oc029'); //옵션은 필수입니다.
							return $result;
						}
					}
				}

				// 추가옵션 재고 체크
				if	($suboptionTitle[$grp_idx])foreach($suboptionTitle[$grp_idx] as $idx => $tit){
					$arr_opt					= array();
					$val							= $suboption[$grp_idx][$idx];
					if($tit) $arr_opt[]		= $tit;
					if($val) $arr_opt[]		= $val;
					$opt_str					= implode(':',$arr_opt);
					$ea							= $suboptionEa[$grp_idx][$idx];

					$checked_sub_key	= $goods['goods_seq'] . '_sub_' . $opt_str;
					if($checked_sub_ea[$checked_sub_key]){
						$ea	+= $checked_sub_ea[$checked_sub_key];
					}
					$chk	= check_stock_suboption($goods_seq, $tit, $val, $ea,
													$cfg_order, 'view_stock'); // 재고체크
					$checked_sub_ea[$checked_sub_key] = $ea;

					if	( $chk['stock'] < 0 ){
						$goodsName	= addslashes($goods['goods_name']);
						if	($opt_str)	$goodsName	.= ' (' . addslashes($opt_str) . ')';
						///구매가능재고를 초과한 상품을 알려 드립니다.<br/> $goodsName | $ea개 → 구매가능재고는 $chk['sale_able_stock']개입니다.
						$err_msg					= getAlert('oc028', array($goodsName, $ea, $chk['sale_able_stock']));

						$result['status']			= false;
						$result['errorMsg']			= $err_msg;
						return $result;
					}
				}
			}
		}

		$result['status']			= true;
		return $result;
	}

	// 옵션 선택 모듈 ver0.1용 추가 함수
	public function add_cart_ver_0_1($add_data=''){

		$this->load->model('statsmodel');
		$this->load->model('goodsmodel');
		$this->load->model('goodsfblike');
		$this->load->model('brandmodel');
		$this->load->model('promotionmodel');
		$this->load->model('shippingmodel');
		$this->load->helper('goods');

		if(!$add_data) $add_data= $_POST;

		$seloptprefix			= trim($add_data['select_option_prefix']);
		$seloptsuffix			= trim($add_data['select_option_suffix']);
		$cart_option_seq		= $add_data['cart_option_seq'];
		$use_add_action_button	= $add_data[$seloptprefix.'use_add_action_button'.$seloptsuffix];
		$exist_option_seq		= $add_data[$seloptprefix.'exist_option_seq'.$seloptsuffix];
		$goods_seq				= $add_data[$seloptprefix.'option_select_goods_seq'.$seloptsuffix];
		$provider_seq			= $add_data[$seloptprefix.'option_select_provider_seq'.$seloptsuffix];
		$option					= $add_data[$seloptprefix.'option'.$seloptsuffix];
		$optionTitle			= $add_data[$seloptprefix.'optionTitle'.$seloptsuffix];
		$inputsValue			= $add_data[$seloptprefix.'inputsValue'.$seloptsuffix];
		$inputsTitle			= $add_data[$seloptprefix.'inputsTitle'.$seloptsuffix];
		$viewInputs				= $add_data[$seloptprefix.'viewInputs'.$seloptsuffix];
		$viewInputsTitle		= $add_data[$seloptprefix.'viewInputsTitle'.$seloptsuffix];
		$optionEa				= $add_data[$seloptprefix.'optionEa'.$seloptsuffix];
		$suboption				= $add_data[$seloptprefix.'suboption'.$seloptsuffix];
		$suboptionTitle			= $add_data[$seloptprefix.'suboptionTitle'.$seloptsuffix];
		$suboptionEa			= $add_data[$seloptprefix.'suboptionEa'.$seloptsuffix];
		$mode					= (trim($_GET['mode']))	? 'direct'	: 'cart';
		// 모드 값 덮어쓰기
		$mode					= ($add_data[$seloptprefix.'cart_overwrite_distribution'.$seloptsuffix])?$add_data[$seloptprefix.'cart_overwrite_distribution'.$seloptsuffix]:$mode;
		$session_id				= session_id();
		$member_seq				= (int) $this->userInfo['member_seq'];
		$arrImageExtensions		= array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tif', 'pic');
		$sale_code_seq			= $this->session->userdata('cart_promotioncodeseq_' . $session_id);
		$sale_code				= $this->session->userdata('cart_promotioncode_' . $session_id);
		$shipping_method		= trim($add_data['shipping_method']);

		// 상품 추가입력사항 파일 업로드 시 저장 폴더 생성
		$path		= ROOTPATH."data/order/";
		if	(!is_dir($path)){
			@mkdir($path);
			@chmod($path, 0777);
		}

		// 버튼식 추가가 아닌 경우 입력옵션 추가
		if	($use_add_action_button == 'n' && is_array($viewInputs) && count($viewInputs) > 0){
			$inputsValue[0]		= $viewInputs;
			$inputsTitle[0]		= $viewInputsTitle;
		}

		// 상품 정보 추출
		$goodsinfo		= $this->goodsmodel->get_goods($goods_seq);
		$goods_status	= $goodsinfo['goods_status'];

		// 상품 입력옵션 정보 추출
		$inputs			= $this->goodsmodel->get_goods_input($goods_seq);

		// 장바구니 통계 데이터 추가
		if	($this->is_adminOrder != 'admin'){
			$stats_param['goods_seq']		= $goods_seq;
			$stats_param['provider_seq']	= $provider_seq;
			$stats_param['goods_name']		= $goodsinfo['goods_name'];
			foreach($optionEa as $k1 => $ea){
				for($i = 0; $i < 5; $i++){
					$opt_idx						= $i + 1;
					if	($option[$k1][$i]!=null)	$stats_param['option' . $opt_idx]	= $option[$k1][$i];
					else					$stats_param['option' . $opt_idx]	= '';
				}
				$stats_param['ea']	= $ea;

				$this->statsmodel->insert_cart_stats($stats_param);
			}
		}

		// 바로구매, 선택구매 장바구니 정리
		if	($mode != 'cart' && empty($add_data["keep_cart"])) $this->delete_mode($mode);

		// 좋아요 여부
		$ckfblike	= $this->goodsfblike->getgoodsfblike($goods_seq, '', $session_id);

		// 카테고리 추출
		$category	= array();
		$catetmp	= $this->goodsmodel->get_goods_category($goods_seq);
		if	($catetmp)foreach($catetmp as $caterow){
			if	( strlen($caterow['category_code']) > 4) {
				if	(strlen($caterow['category_code']) >= 16)
					$category[]	= substr($caterow['category_code'], 0, 16);
				if	(strlen($caterow['category_code']) >= 12)
					$category[]	= substr($caterow['category_code'], 0, 12);
				if	(strlen($caterow['category_code']) >= 8)
					$category[]	= substr($caterow['category_code'], 0, 8);
			}
			$category[]		= substr($caterow['category_code'], 0, 4);
		}

		// 대표 브랜드 추출
		$brands		= $this->goodsmodel->get_goods_brand($goods_seq);
		unset($brand_code);
		if	($brands)foreach($brands as $bkey => $branddata){
			if	($branddata['link'] == 1){
				$brand_codear	= $this->brandmodel->split_brand($branddata['category_code']);
				$brand_code[]	= $brand_codear[0];
			}
		}

		// 기존 장바구니 정보 추출
		$cart		= $this->catalog($this->is_adminOrder);
		$cart_goods	= $cart['data_goods'][$goods_seq];

		if($add_data['shipping_method'] == 'direct')
			$add_data['shipping_method'] = 'direct_store';
		if(!$goodsinfo['shipping_group_seq'])	$goodsinfo['shipping_group_seq']	= $cart_goods['shipping_group_seq'];
		if(!$add_data['shipping_method'])		$add_data['shipping_method']		= $cart_goods['shipping_set_seq'];

		// 스킨별, 서비스별  예외처리 :: START 2016-10-25 lwh
		if			( $goodsinfo['goods_kind'] == "coupon" ){
			# 티켓상품일때 배송그룹 자동 지정
			$default_shipping = $this->shippingmodel->get_shipping_set($goodsinfo['shipping_group_seq'], 'shipping_group_seq',array('shipping_set_code'=>$goodsinfo['goods_kind']));
			$set_info = $default_shipping[0];
			$set_info['exception_code']	= 'coupon';
		}else if($mode == "o2o" && $add_data['shipping_method']){
			// O2O 상품 배송그룹 조회
			$default_shipping = $this->shippingmodel->get_shipping_set($add_data['shipping_method']);
			if($default_shipping[0]){
				$set_info = $default_shipping[0];
			}
		}else if	( !$add_data['shipping_method'] ){
			# 상품을 장바구니에 담을때 배송설정이 불가한 경우 예외처리 -> 기본배송설정 추출
			$default_shipping = $this->shippingmodel->get_shipping_set($goodsinfo['shipping_group_seq'], 'shipping_group_seq',array('default_yn'=>'Y'));
			$set_info = $default_shipping[0];
			$set_info['exception_code']	= 'shipping_method';
		}else if	( preg_match('/[^0-9]/',$add_data['shipping_method']) ){
			# 구 스킨용 method 가 넘어왔을경우 처리 -> 같은 코드 추출
			$default_shipping = $this->shippingmodel->get_shipping_set($goodsinfo['shipping_group_seq'], 'shipping_group_seq',array('shipping_set_code'=>$add_data['shipping_method']));
			$set_info = $default_shipping[0];
			$set_info['exception_code']	= 'old_method';
		}else if	($goodsinfo['shipping_group_seq'] && $add_data['shipping_method']){
			# 정상적인 범위 내의 배송설정 추출 :: 2016-07-25 lwh
			$set_info = $this->shippingmodel->get_shipping_set($add_data['shipping_method'], 'shipping_set_seq');
		}

		if	(!$set_info){
			# 기타 정해지지 않은 배송 설정 기본값 정의 :: 2016-08-31 lwh
			$set_info = $this->shippingmodel->load_shipping_set($goodsinfo['shipping_group_seq']);
			$set_info['exception_code'] = 'etc';
		}

		if(!$add_data['shipping_prepay_info']){
			# 선착불 정보 기본값 추출
			if($set_info['prepay_info'] == 'all' || $set_info['prepay_info'] == 'delivery'){
				$add_data['shipping_prepay_info'] = 'delivery';
			}else{
				$add_data['shipping_prepay_info'] = 'postpaid';
			}
		}
		// 스킨별, 서비스별  예외처리 :: END

		// 기타 필수 값 예외처리 -> 위의 예외처리에서 걸린경우를 의미 :: START 2016-10-25 lwh
		if($set_info['exception_code']){
			$shipping_method		= $set_info['shipping_set_seq'];

			# 매장수령 기본값 추출
			if($set_info['store_use'] == 'Y' && !$add_data['shipping_store_seq']){
				$store_list = $this->shippingmodel->get_shipping_store($set_info['shipping_set_seq'], 'shipping_set_seq');
				$add_data['shipping_store_seq'] = $store_list[0]['shipping_store_seq'];
			}

			# 희망배송일 추출
			if($set_info['hopeday_required'] == 'Y' && !$add_data['hop_select_date']){
				$add_data['hop_select_date'] = $this->shippingmodel->get_hop_date($set_info);
			}
		}
		// 기타 필수 값 예외처리 :: END

		if	($this->is_adminOrder == 'admin'){
			//$data_cart	= $this->get_cart_by_cart_option($cart_option_seq);
			//$cart_seq	= $data_cart['cart_seq'];
			$member_seq = $add_data["member_seq"];

			if($cart_option_seq){

				$data_cart	= $this->get_cart_by_cart_option($cart_option_seq);
				$cart_seq	= $data_cart['cart_seq'];

			}else{

				$insert_data['goods_seq']			= $goods_seq;
				$insert_data['session_id'] 			= $session_id;
				$insert_data['member_seq'] 			= $member_seq;
				$insert_data['distribution']		= 'admin';
				$insert_data['regist_date ']		= date('Y-m-d H:i:s',time());
				$insert_data['update_date']			= date('Y-m-d H:i:s',time());
				$insert_data['fblike']				= 'N';
				if($ckfblike)	$insert_data['fblike']	= 'Y';
				$insert_data['agent']				= $_SERVER['HTTP_USER_AGENT'];
				$insert_data['ip']					= $_SERVER["REMOTE_ADDR"];
				$insert_data['shipping_group_seq']	= $goodsinfo['shipping_group_seq'];
				$insert_data['shipping_set_seq']	= $set_info['shipping_set_seq'];
				$insert_data['shipping_set_code']	= $set_info['shipping_set_code'];
				$insert_data['shipping_hop_date']	= $add_data['hop_select_date'];
				$insert_data['shipping_store_seq']	= $add_data['shipping_store_seq'];
				$insert_data['shipping_prepay_info']= $add_data['shipping_prepay_info'];
				$this->db->insert($this->tb_cart, $insert_data);
				$cart_seq							= $this->db->insert_id();
			}
		}else{
			// 장바구니 추가
			$insert_data['goods_seq']			= $goods_seq;
			$insert_data['session_id'] 			= $session_id;
			if($member_seq && $mode == "cart") $insert_data['session_id'] = '';
			$insert_data['member_seq'] 			= $member_seq;
			$insert_data['distribution']		= $mode;
			$insert_data['regist_date ']		= date('Y-m-d H:i:s',time());
			$insert_data['update_date']			= date('Y-m-d H:i:s',time());
			$insert_data['fblike']				= 'N';
			if($ckfblike)	$insert_data['fblike']	= 'Y';
			$insert_data['agent']				= $_SERVER['HTTP_USER_AGENT'];
			$insert_data['ip']					= $_SERVER["REMOTE_ADDR"];
			$insert_data['shipping_group_seq']	= ($mode=="o2o")?$set_info['shipping_group_seq']:$goodsinfo['shipping_group_seq'];
			$insert_data['shipping_set_seq']	= $set_info['shipping_set_seq'];
			$insert_data['shipping_set_code']	= $set_info['shipping_set_code'];
			$insert_data['shipping_hop_date']	= $add_data['hop_select_date'];
			$insert_data['shipping_store_seq']	= $add_data['shipping_store_seq'];
			$insert_data['shipping_prepay_info']= $add_data['shipping_prepay_info'];
			$this->db->insert($this->tb_cart, $insert_data);

			$cart_seq							= $this->db->insert_id();
		}


		if (!$cart_seq) {
			return false;
		}

		// 묶음배송일 경우 기존 같은 배송그룹 merge :: 2016-10-28 lwh
		$grp_info = $this->shippingmodel->get_shipping_group($insert_data['shipping_group_seq']);
		if($grp_info['shipping_calcul_type'] != 'each'){
			// 기존 카트 정보 추출
			if($member_seq) $this->db->where('member_seq', $member_seq);
			else			$this->db->where('session_id', $session_id);
			$this->db->where('shipping_group_seq', $insert_data['shipping_group_seq']);
			$this->db->where('cart_seq !=',$cart_seq);
			$this->db->select('cart_seq');
			$query		= $this->db->get($this->tb_cart);
			$cart_info	= $query->result_array();
			foreach($cart_info as $val)		$cart_arr[] = $val['cart_seq'];

			$set_params = array(
				'update_date'			=> date('Y-m-d H:i:s'),
				'shipping_set_seq'		=> $insert_data['shipping_set_seq'],
				'shipping_set_code'		=> $insert_data['shipping_set_code'],
				'shipping_hop_date'		=> $insert_data['hop_select_date'],
				'shipping_store_seq'	=> $insert_data['shipping_store_seq'],
				'shipping_prepay_info'	=> $insert_data['shipping_prepay_info']
			);

			if($member_seq) $this->db->where('member_seq', $member_seq);
			else			$this->db->where('session_id', $session_id);
			$this->db->where('cart_seq !=',$cart_seq);
			$this->db->where('shipping_group_seq', $insert_data['shipping_group_seq']);
			$this->db->update($this->tb_cart, $set_params);

			$opt_up_sql = "UPDATE " . $this->tb_cart_option . " SET shipping_method = '" . $insert_data['shipping_set_seq'] . "' WHERE cart_seq IN ('" . implode("', '",$cart_arr) . "')";
			$this->db->query($opt_up_sql);
		}

		// 기존에 선택된 옵션이 있는 경우 제거
		if	($exist_option_seq && is_array($exist_option_seq) && count($exist_option_seq) > 0){
			foreach($exist_option_seq as $k => $seq){
				$this->db->delete($this->tb_cart_option,	array('cart_option_seq' => $seq));
				$this->db->delete($this->tb_cart_input,		array('cart_option_seq' => $seq));
				$this->db->delete($this->tb_cart_suboption,	array('cart_option_seq' => $seq));
			}
		}

		// 필수옵션 기준으로 옵션 정보 insert
		$arr_cart_option_seq = array();	// 장바구니 옵션 고유키
		foreach($optionEa as $grp_idx => $ea){

			// 필수옵션 추가
			unset($insert_data,$max);
			for($i = 0; $i < 5; $i++){
				$opt_idx			= $i + 1;
				${'opt'.$opt_idx}	= $option[$grp_idx][$i];
				if	( !isset($option[$grp_idx][$i]) || $option[$grp_idx][$i]==null )
					$option[$grp_idx][$i]		= '';
				if	( !isset($optionTitle[$grp_idx][$i]) || !$optionTitle[$grp_idx][$i] )
					$optionTitle[$grp_idx][$i]	= null;

				$insert_data['option' . $opt_idx]	= $option[$grp_idx][$i];
				$insert_data['title' . $opt_idx]	= $optionTitle[$grp_idx][$i];
			}
			$insert_data['ea'] 				= $ea;
			$insert_data['cart_seq']		= $cart_seq;
			$insert_data['shipping_method']	= $shipping_method;

			// 코드할인 session 추가
			if	($sale_code){
				$sc['whereis']	= " and promotion_input_serialnumber ='" . $sale_code . "'";
				$promotioncode	= $this->promotionmodel->get_data($sc);
				$promotioncode	= $promotioncode[0];

				list($price, $reserve)	= $this->goodsmodel->get_goods_option_price(
					$goods_seq, $opt1, $opt2, $opt3, $opt4, $opt5
				);

				// 할인 코드 정보 추출
				$codeFuncName		= 'get_able_download_list';		// 개별코드
				if	( strstr($promotioncode['type'], 'promotion') )
					$codeFuncName	= 'get_able_promotion_list';	// 일반코드
				$promotions	= $this->promotionmodel->$codeFuncName($goods_seq, $category, $brand_code, $cart['total'], $sale_code, $price, $ea);

				if	($promotions){
					// 중복할인은 무조건추가
					if	($promotions['duplication_use'] == 1){
							$insert_data['promotion_code_seq']			= $sale_code_seq;
							$insert_data['promotion_code_serialnumber']	= $sale_code;
					// 중복할인이 아닌경우 상품 할인가(판매가)가 최대값인 상품으로 처리함
					}else{
						foreach($cart['list'] as $cartkey => $cartdata){
							if	($goods_seq == $cartdata['goods_seq']){
								if	( ($max[$goods_seq] && $max[$goods_seq] < $price) || !$max[$goods_seq]){
									$insert_data['promotion_code_seq']			= $sale_code_seq;
									$insert_data['promotion_code_serialnumber']	= $sale_code;
									$max[$goods_seq]							= $price;
									$upsql	= "update " . $this->tb_cart_option . "
												set promotion_code_seq = null,
												promotion_code_serialnumber = null
												where cart_seq = '" . $cartdata['cart_seq'] . "'";
									$this->db->query($upsql);
								}
							}
						}

						// 최초상품인경우
						if($cart['list'] && !$max[$goods_seq]){
							$insert_data['promotion_code_seq']			= $sale_code_seq;
							$insert_data['promotion_code_serialnumber']	= $sale_code;
						}
					}
				}
			}

			// 라이브 방송
			// 쿠키 체크
			// 현재 방송중 체크
			// db 필드 추가
			$bs_type = array('live','vod');
			foreach($bs_type as $val) {
				$cookie = "broadcast_".$goods_seq."_".$val;
				if($_COOKIE[$cookie]) {
					$insert_data['bs_seq'] = $_COOKIE[$cookie];
					$insert_data['bs_type'] = $val;
				}
			}



			$this->db->insert($this->tb_cart_option, $insert_data);
			$cart_option_seq	= $this->db->insert_id();
			$arr_cart_option_seq[] = $cart_option_seq;
			if	($grp_idx == 0)	$first_cart_option_seq	= $cart_option_seq;

			// 입력옵션 추가
			if( $inputsValue[$grp_idx] && is_array($inputsValue[$grp_idx])){

				foreach($inputs as $k => $data_input){
					unset($insert_data);
					$input_name	= $data_input['input_name'];
					$idx		= array_search($input_name, $inputsTitle[$grp_idx]);
					if	($idx || $input_name == $inputsTitle[$grp_idx][$idx]){
						$inputType								= $data_input['input_form'];
						$inputVal								= str_replace("data/tmp/","",$inputsValue[$grp_idx][$idx]);
						$inputsTitle[$grp_idx][$idx]	= '';

						// 파일업로드 입력옵션일 경우
						if	($data_input['input_form'] == 'file' && realpath("data/tmp/".$inputVal)){
							$inputType	= 'file';
							$file_path	= str_replace(realpath(ROOTPATH), '', realpath("data/tmp/".$inputVal));
							$fname		= $file_path;

							// 파일 업로드 여부체크
							if	(preg_match("/\/tmp\//i", $file_path) && file_exists(realpath(ROOTPATH) . $file_path)){
								$file_ext	= end(explode('.', $file_path));
								$file_path	= realpath(ROOTPATH) . $file_path;
								$fname		= '';
								if	( in_array(strtolower($file_ext), $arrImageExtensions) ){
									$fname	= $cart_seq . '_' . $cart_option_seq . '_'
											. $k . "." . $file_ext;
									copy($file_path, $path.$fname);
								}
								$k++;
							}else{ $fname = ""; }

							$inputVal	= $fname;
						}

						$insert_data['type']			= $inputType;
						$insert_data['input_title']		= $input_name;
						$insert_data['input_value']		= $inputVal;
						$insert_data['cart_seq']		= $cart_seq;
						$insert_data['cart_option_seq']	= $cart_option_seq;
						$this->db->insert($this->tb_cart_input, $insert_data);
					}
				}
			}

			// 추가옵션 추가
			if( $suboption[$grp_idx] && is_array($suboption[$grp_idx])){
				foreach($suboption[$grp_idx] as $idx => $sub){
					unset($insert_data);
					$insert_data['ea']				= $suboptionEa[$grp_idx][$idx];
					$insert_data['suboption_title']	= $suboptionTitle[$grp_idx][$idx];
					$insert_data['suboption']		= $sub;
					$insert_data['cart_seq'] 		= $cart_seq;
					$insert_data['cart_option_seq']	= $cart_option_seq;
					$this->db->insert($this->tb_cart_suboption, $insert_data);
				}
			}
		}
		$result['mode']			= $mode;
		$result['member_seq']	= $member_seq;
		$result['goods_seq']	= $goods_seq;
		$result['cart_seq']		= $cart_seq;
		$result['cart_option_seq']		= $arr_cart_option_seq;
		$result['goods_status']	= $goods_status;

		return $result;
	}

	public function add_cart(){

		$member_seq = "";
		$pre_cart_seqs = "";
		if(! isset($_GET['mode'])) $mode = "cart";
		else $mode = "direct";

		// 상품 추가입력사항 파일 업로드 시 저장 폴더 생성
		$path = ROOTPATH."data/order/";
		if(!is_dir($path)){
			@mkdir($path);
			@chmod($path,0777);
		}

		$this->load->model('goodsmodel');
		$this->load->model('goodsfblike');

		if( !isset($_POST['option']) && !isset($_POST['optionEa']) ){
			//"장바구니에 담을 상품이 없습니다."
			openDialogAlert(getAlert('gv012'),400,140,'parent',"");
			exit;
		}

		$goods_seq = (int) $_POST['goodsSeq'];

		$inputs = $this->goodsmodel->get_goods_input($goods_seq);

		if( isset($_POST['inputsValue']) && is_array($_POST['inputsValue'][0])){
			// 2014-12-18 옵션 개편 후 (ocw)
			foreach($inputs as $key_input => $data_input){
				foreach($_POST['inputsValue'][0] as $k=>$v){
					if($data_input['input_require'] == 1 && !$_POST['inputsValue'][$key_input][$k]){
						//옵션은 필수입니다.
						openDialogAlert(addslashes($_POST['inputsTitle'][$key_input][$k]) . " ".getAlert('gv013'),400,140,'parent',"");
						exit;
					}elseif($data_input['input_require'] == 1){
						$inputs_required = true;
					}
				}
			}
		}else{
			// 2014-12-18 옵션 개편 전 (ocw)
			$inputs_required = false;
			$file_num = 0;
			$input_num = 0;
			foreach($inputs as $key_input => $data_input){

				$_POST['inputsValue'][$input_num] = trim( $_POST['inputsValue'][$input_num] );
				if( $data_input['input_require'] == 1 && !$_POST['inputsValue'][$input_num] && $data_input['input_form'] != 'file' ){
					//옵션은 필수입니다.
					openDialogAlert(addslashes($data_input['input_name']) . " ".getAlert('gv013'),400,140,'parent',"");
					exit;
				}else if( $data_input['input_require'] == 1 && $data_input['input_form'] == 'file' && !$_FILES['inputsValue']['tmp_name'][$file_num] ){
					//옵션은 필수입니다.
					openDialogAlert(addslashes($data_input['input_name']) . " ".getAlert('gv013'),400,140,'parent',"");
					exit;
				}elseif($data_input['input_require'] == 1){
					$inputs_required = true;
				}

				if( $data_input['input_form'] == 'file' ){
					$file_num++;
				} else {
					$input_num++;
				}
			}
		}

		if(!$_POST['suboptionTitle']) $_POST['suboptionTitle'] = array();
		$suboption_required = false;

		foreach($_POST['suboption_title_required'] as $required_title){
			if( !in_array($required_title,$_POST['suboptionTitle']) ){
				//옵션은 필수입니다.
				openDialogAlert(addslashes($required_title) . " ".getAlert('gv013'),400,140,'parent',"");
				exit;
			}
			$suboption_required = true;
		}

		foreach($_POST['optionEa'] as $k1 => $ea){
			for($i=1;$i<=5;$i++){
				if(!isset($_POST['option'][$i][$k1])){
					$_POST['option'][$i][$k1] = "";
					$_POST['optionTitle'][$i][$k1] = "";
				}
			}
		}

		// 상품정보
		$goodsinfo = $this->goodsmodel->get_goods($goods_seq);

		// 상품의 배송방법
		$data_policy_shipping = $this->goodsmodel->get_shipping_policy($goodsinfo);
		if( !$_POST['shipping_method'] && $data_policy_shipping['shipping_method'] )
		{
			if( array_key_exists('coupon',$data_policy_shipping['shipping_method']) ){
				$_POST['shipping_method'] = 'coupon';
			}else if( array_key_exists('delivery',$data_policy_shipping['shipping_method']) ){
				$_POST['shipping_method'] = 'delivery';
			}else if( array_key_exists('each_delivery',$data_policy_shipping['shipping_method']) ){
				$_POST['shipping_method'] = 'each_delivery';
			}else if( array_key_exists('postpaid',$data_policy_shipping['shipping_method']) ){
				$_POST['shipping_method'] = 'postpaid';
			}else if( array_key_exists('each_postpaid',$data_policy_shipping['shipping_method']) ){
				$_POST['shipping_method'] = 'each_postpaid';
			}else if( array_key_exists('quick',$data_policy_shipping['shipping_method']) ){
				$_POST['shipping_method'] = 'quick';
			}else if( array_key_exists('direct',$data_policy_shipping['shipping_method']) ){
				$_POST['shipping_method'] = 'direct';
			}
		}

		// 실물상품 (배송이 필요한 상품) - 상품상세페이지, 장바구니, 주문하기페이지 배송방법 미선택 검증
		if( !$_POST['shipping_method'] ){
			//상품의 배송 방법이 없습니다. 쇼핑몰 고객센터로 문의해 주십시오.
			openDialogAlert(getAlert('gv014'),400,140,'parent',"");
			exit;
		}

		## 장바구니 통계 데이터 추가
		$this->load->model('statsmodel');
		$stats_param['provider_seq']	= $goodsinfo['provider_seq'];
		$stats_param['goods_seq']		= $goods_seq;
		$stats_param['goods_name']		= $goodsinfo['goods_name'];
		foreach($_POST['optionEa'] as $k1 => $ea){
			for($i=0;$i<5;$i++){
				$opt_idx	= $i + 1;
				$stats_param['option'.$opt_idx]	= '';
				if(!isset($_POST['option'][$i][$k1])){
					$_POST['option'][$i][$k1] = "";
					$_POST['optionTitle'][$i][$k1] = "";
				}else{
					if	($_POST['option'][$i][$k1])
						$stats_param['option'.$opt_idx]	= $_POST['option'][$i][$k1];
				}
			}
			$stats_param['ea']	= $ea;

			$this->statsmodel->insert_cart_stats($stats_param);
		}

		$session_id = session_id();
		if($this->userInfo['member_seq']) $member_seq = $this->userInfo['member_seq'];

		if($mode != "cart"){
			// 바로구매 시
			$this->delete_mode($mode);
		}

		$insert_data['goods_seq'] 	= $goods_seq;
		$insert_data['session_id'] 	= $session_id;
		if($this->userInfo['member_seq'] && $mode == "cart") $insert_data['session_id'] = '';
		$insert_data['member_seq'] 	= $member_seq;
		$insert_data['distribution'] = $mode;
		$insert_data['regist_date '] = $insert_data['update_date'] = date('Y-m-d H:i:s',time());

		$ckfblike = $this->goodsfblike->getgoodsfblike($goods_seq, '', $session_id);
		if($ckfblike){
			$insert_data['fblike'] = 'Y';
		}else{
			$insert_data['fblike'] = 'N';
		}

		$this->load->model('brandmodel');
		$category = array();
		$catetmp = $this->goodsmodel->get_goods_category($goods_seq);
		foreach($catetmp as $caterow) {
			if( strlen($caterow['category_code']) > 4) {
				if(strlen($caterow['category_code']) == 16) {
					$category[] = substr($caterow['category_code'], 0, 16);
					$category[] = substr($caterow['category_code'], 0, 12);
					$category[] = substr($caterow['category_code'], 0, 8);
					$category[] = substr($caterow['category_code'], 0, 4);
				}elseif(strlen($caterow['category_code']) == 12) {
					$category[] = substr($caterow['category_code'], 0, 12);
					$category[] = substr($caterow['category_code'], 0, 8);
					$category[] = substr($caterow['category_code'], 0, 4);
				}elseif(strlen($caterow['category_code']) == 8) {
					$category[] = substr($caterow['category_code'], 0, 8);
					$category[] = substr($caterow['category_code'], 0, 4);
				}else{
					$category[] = substr($caterow['category_code'], 0, 4);
				}
			}else{
				$category[] = $caterow['category_code'];
			}
		}

		$brands = $this->goodsmodel->get_goods_brand($goods_seq);
		unset($brand_code);
		if($brands) foreach($brands as $bkey => $branddata){
			if( $branddata['link'] == 1 ){
				$brand_codear= $this->brandmodel->split_brand($branddata['category_code']);
				$brand_code[] = $brand_codear[0];
			}
		}

		$insert_data['agent'] = $_SERVER['HTTP_USER_AGENT'];

		$this->db->insert('fm_cart', $insert_data);
		$cart_seq = $this->db->insert_id();

		if (!$cart_seq) {
			return false;
		}

		$this->insert_cart_alloption($cart_seq,$inputs);

		$result = array(
			'mode'=>$mode,
			'member_seq'=>$member_seq,
			'goods_seq'=>$goods_seq,
			'cart_seq'=>$cart_seq
		);
		return $result;
	}

	# 재매칭시 주문 상품 및 수량 체크
	public function rematch_ea_check($optionEa,$suboptionEa,$data){

		$rematch_ea = 0;
		if($optionEa) $rematch_ea += array_sum($optionEa);
		if($suboptionEa) $rematch_ea += array_sum($suboptionEa);

		if($data['old_option_seq']){

			if(count($optionEa) > 1){
				//상품매칭은 주문된 옵션 갯수와 동일하게 매칭하셔야 합니다.
				openDialogAlert(getAlert('oc030'), 480, 140, 'parent');
				exit;
			}
			$query	= "select order_seq,ea from fm_order_item_option where item_option_seq=?";
			$query	= $this->db->query($query,array($data['old_option_seq']));
			$option	= $query->row_array();
			if(array_sum($optionEa) != $option['ea']){
				//상품매칭은 주문된 옵션 수량(".$option['ea']."개) 만큼만 가능합니다.
				openDialogAlert(getAlert('oc031',$option['ea']), 400, 140, 'parent');
				exit;
			}

			$query	= "select ea from fm_order_item_suboption where order_seq=? and item_option_seq=?";
			$query	= $this->db->query($query,array($option['order_seq'],$data['old_option_seq']));
			if(count($suboptionEa) != count($query->result_array())){
				//상품매칭은 주문된 추가 옵션 갯수와 동일하게 매칭하셔야 합니다.
				openDialogAlert(getAlert('oc032'), 480, 140, 'parent');
				exit;
			}

			foreach($query->result_array() as $suboption) $old_suboptionEa += $suboption['ea'];

			if(array_sum($suboptionEa) != $old_suboptionEa){
				//상품매칭은 주문된 추가 옵션 수량 만큼만 가능합니다.
				openDialogAlert(getAlert('oc033'), 400, 140, 'parent');
				exit;
			}

			$query	= "select sum(ea) ea from (select sum(ea) ea from fm_order_item_option where item_option_seq=? and order_seq=? union all select sum(ea) ea from fm_order_item_suboption where item_option_seq=? and order_seq=?) k";
			$bind	= array($data['old_option_seq'],$option['order_seq'],$data['old_option_seq'],$option['order_seq']);
			$query	= $this->db->query($query,$bind);
			$option	= $query->row_array();
			if($option['ea'] != $rematch_ea){
				//상품매칭은 주문된 수량(".$option['ea']."개) 만큼만 가능합니다.
				openDialogAlert(getAlert('oc034',$option['ea']), 400, 140, 'parent');
				exit;
			}
		}
		$cart_option_seq = (int) $data['cart_option_seq'];
		$query = "select cart_seq from fm_cart_option where cart_option_seq='".$cart_option_seq."'";
		$query = $this->db->query($query);
		$cart  = $query->row_array();
		$cart_seq = $cart['cart_seq'];


		$query = "select sum(cnt) cnt from (
				select count(*) as cnt from fm_cart_option where cart_seq=?
				union
				select count(*) as cnt from fm_cart_suboption where cart_seq=?
				) k ";
		$query		= $this->db->query($query,array($cart_seq,$cart_seq));
		$cartres	= $query->row_array();
		if($cartres['cnt'] > 1){
			//이미 매칭된 상품이 있습니다.
			echo json_encode(array("res"=>false,"msg"=>getAlert('oc035')));
			exit;
		}

	}

	public function get_recent_cart($member_seq){
		$query = "
			SELECT goods_seq
			FROM {$this->tb_cart}
			WHERE member_seq=?
			ORDER BY cart_seq DESC
			";
		$query = $this->db->query($query,array($member_seq));
		$row = $query->result_array();

		return $row[0]['goods_seq'];
	}

	public function modify($mode, $set_params, $where_params)
	{
		if($mode == 'cart'){
			$table = $this->tb_cart;
		}else if($mode == 'option'){
			$table = $this->tb_cart_option;
		}else if($mode == 'suboption'){
			$table = $this->tb_cart_suboption;
		}
		$this->db->where($where_params);
		$this->db->update($table, $set_params);
	}

    // 채널톡 연동으로인해 장바구니 총 금액 추가 2021.06.21
    public function get_cart_total_price() {

        $session_id = session_id();
		if($this->userInfo['member_seq']){
			$where_query[] = "cart.member_seq = ?";
			$where_arr[] = $this->userInfo['member_seq'];
		}else{
			$where_query[] = "cart.session_id = ?";
			$where_arr[] = $session_id;
		}

		//장바구니에 담긴 상품수가 $this->catalog() 에서 구하는 상품수와 안맞아
		//fm_goods_option 조건 추가해줌 leewh 2014-11-19 */
		$now_date = date('Y-m-d');
		$query = "SELECT sum(cart_opt.ea * goods_opt.price) as total_price , sum(goods.default_price) as default_price ,sum(price) as price FROM ".$this->tb_cart_option." cart_opt
		left join ".$this->tb_cart." cart on cart.cart_seq = cart_opt.cart_seq
		,fm_goods_option goods_opt
		,fm_goods goods
		WHERE cart.distribution = 'cart'
		AND cart.goods_seq = goods_opt.goods_seq
		AND cart.goods_seq = goods.goods_seq
		AND (goods.goods_view = 'look' or ( goods.display_terms = 'AUTO' and goods.display_terms_begin <= '".$now_date."' and goods.display_terms_end >= '".$now_date."'))
		AND goods.goods_status = 'normal'
		AND ifnull(cart_opt.option1,'') = ifnull(goods_opt.option1,'')
		AND ifnull(cart_opt.option2,'') = ifnull(goods_opt.option2,'')
		AND ifnull(cart_opt.option3,'') = ifnull(goods_opt.option3,'')
		AND ifnull(cart_opt.option4,'') = ifnull(goods_opt.option4,'')
		AND ifnull(cart_opt.option5,'') = ifnull(goods_opt.option5,'')
		AND ".implode(' AND ',$where_query) ." order by cart.cart_seq desc";
		$query = $this->db->query($query,$where_arr);
		$row = $query->result_array();
		return $row[0]['total_price'];

	}
}

/* End of file category.php */
/* Location: ./app/models/category */
