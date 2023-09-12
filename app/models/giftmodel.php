<?php
class giftmodel extends CI_Model {
	public function get_gift($today,$goods_price,$gift_goods,$gift_categorys,$provider_seq='',$shipping_group_seq=''){
		$where = '';
		$bind = array();

		### GIFT
		$sql = "SELECT * FROM fm_gift WHERE gift_gb = 'order' AND display = 'y'";

		if($today){
			$where[] = "start_date <= ?";
			$where[] = "end_date >= ?";
			$bind[] = $today;
			$bind[] = $today;
		}

		if($provider_seq){
			$where[] = "provider_seq = ?";
			$bind[] = $provider_seq;
		}

		if($shipping_group_seq) {
			$where[] = "shipping_group_seq = ?";
			$bind[] = $shipping_group_seq;
		}

		if($where){
			$sql .= " AND ".implode(" AND ",$where);
		}

		$sql .= " order by end_date asc, gift_seq asc";
		$query = $this->db->query($sql,$bind);

		$gift_cnt = 0;
		foreach ($query->result_array() as $v){
			unset($g_result);
			if($v['goods_rule']=='all'){
				$g_result = $this->get_gift_benefit($v['gift_seq'], $v['gift_rule']);

			}else if($v['goods_rule']=='category'){
				$category_check = false;
				foreach($gift_categorys as $data){
					$category_codes = $this->categorymodel->split_category($data);

					foreach($category_codes as $category_code){

						$sql = "SELECT count(*) as cnt FROM fm_gift_choice WHERE category_code = '{$category_code}' and gift_seq = '".$v['gift_seq']."'";

						$query = $this->db->query($sql);
						$boolen = $query->result_array();

						if($boolen[0]["cnt"] > 0){
							$category_check = true;
						}
					}

				}
				if($category_check){
					$g_result = $this->get_gift_benefit($v['gift_seq'], $v['gift_rule']);
				}

			}else if($v['goods_rule']=='goods'){
				$goods_check = false;
				foreach($gift_goods as $data){
					$sql = "SELECT count(*) as cnt FROM fm_gift_choice WHERE goods_seq = '{$data}' and gift_seq = '{$v['gift_seq']}'";
					$query = $this->db->query($sql);

					$boolen = $query->result_array();
					if($boolen[0]["cnt"] > 0){
						$goods_check = true;
					}
				}
				if($goods_check){
					$g_result = $this->get_gift_benefit($v['gift_seq'], $v['gift_rule']);
				}
			}

			$v['benifits'] = $g_result;

			// NEW 스킨 종료일 안내 추가 :: 2016-11-11 lwh
			$evtEndObj	= new DateTime(date('Y-m-d',strtotime($v['end_date'])));
			$todayObj	= new DateTime(date('Y-m-d',strtotime($today)));
			$gap		= date_diff($todayObj,$evtEndObj);
			unset($alertTxt);
			if($gap->days <= 5){
				if($gap->days > 0){
					$alertTxt = '종료 '.$gap->days.'일전';
				}else{
					$alertTxt = '마지막날';
				}
			}
			$v['alertEnd'] = ($gap->days <= 5) ? $alertTxt : false;

			$gift_list = $v;

			if($gift_list['benifits']){
				return $gift_list;
			}
		}

		return $gift_list;
	}

	public function get_gift_goods($goods_seq)
	{
		$sql	= "SELECT a.goods_view, a.goods_status, sum(b.stock) stock,a.goods_name FROM fm_goods a, fm_goods_supply b WHERE a.goods_seq = b.goods_seq and a.goods_seq = ? group by a.goods_seq";
		$query	= $this->db->query($sql,$goods_seq);
		list($info)	= $query->result_array();
		if($info['stock'] < 1 || $info['goods_view'] != "look" || $info['goods_status'] != "normal"){
			$info['stock'] = 0;
		}

		return $info;

	}

	public function get_gift_supply_price($goods_seq)
	{
		$sql	= "SELECT supply_price FROM fm_goods_supply  WHERE goods_seq = ? limit 1";
		$query	= $this->db->query($sql,$goods_seq);
		$info	= $query->row_array();
		return get_cutting_price($info['supply_price']);
	}

	public function get_gift_benefit($gift_seq,$type){

		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' order by eprice asc";
		$query	= $this->db->query($sql);
		$i = 0;
		foreach( $query->result_array() as $info ){

			$garr	= explode("|",$info['gift_goods_seq']);
			if($type=='default'){
				$info['ea']	= 1;
			}else if($type=='price'){
				$garr	= explode("|",$info['gift_goods_seq']);
				$info['ea']		= 1;
			}

			// 사은품 재고 체크
			$goods = array();
			if(count($garr)>0){
				foreach($garr as $gift_goods_seq){
					$goods_data = $this->get_gift_goods($gift_goods_seq);
					if($goods_data['stock'] > 0){
						$goods[] = $goods_data;
					}
				}
			}

			if(count($goods)>0){
				$info['goods'] = $goods;
				$result[] = $info;
			}
		}

		if($result) return $result;
		else return false;

	}

	public function get_gift_benefit_info($benefit_seq){

		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_benefit_seq = '{$benefit_seq}'";
		$query	= $this->db->query($sql);
		$data	= $query->result_array();

		return $data[0];

	}
	public function get_gift_choice($gift_seq){

		$sql	= "SELECT * FROM fm_gift_choice WHERE gift_seq = '{$gift_seq}'";
		$query	= $this->db->query($sql);

		foreach($query->result_array() as $data){

			if($data['choice_type'] == 'goods') $choice['goods'][] = $data['goods_seq'];
			if($data['choice_type'] == 'category') $choice['category'][] = $data['category_code'];

		}

		return $choice;

	}

	## 지급한 사은품 title  2015-05-14 pjm
	public function get_gift_title($order_seq='',$item_seq=''){

		$giftlog = array();
		$sql	= "select gift_seq,gift_title from fm_log_order_gift where order_seq='".$order_seq."' and item_seq='".$item_seq."'";
		$query	= $this->db->query($sql);
		$data	= $query->result_array();
		$tmp = array();
		$tmp['gift_seq']	= $data['0']['gift_seq'];
		$tmp['gift_title']	= $data['0']['gift_title'];
		return $tmp;
	}

	## 사은품 지급 로그 보기 2015-05-14 pjm
	public function get_gift_order_log($order_seq,$item_seq){

		$this->load->model("categorymodel");

		$giftlog = array();
		if(is_array($item_seq)){
			$where = " and item_seq in(".implode(",",$item_seq).")";
		}else{
			$where = " and item_seq='".$item_seq."'";
		}
		$sql	= "select * from fm_log_order_gift where order_seq='".$order_seq."' ".$where;
		$query	= $this->db->query($sql);
		$result	= $query->result_array() ;

		foreach($result as $data){

			$tmp = $data;

			switch($tmp['goods_rule']){
				case "all":			$tmp['goods_rule_text'] = "전체"; break;
				case "category":	$tmp['goods_rule_text'] = "특정 카테고리"; break;
				case "goods":		$tmp['goods_rule_text'] = "특정 상품"; break;
				case "reserve":		$tmp['goods_rule_text'] = "마일리지로 교환"; break;
				case "point":		$tmp['goods_rule_text'] = "포인트로 교환"; break;
			}

			$tmp['gift_rule_text2']	= "[".$tmp['goods_rule_text'] ."] 대상";

			if($tmp['goods_rule'] == "category"){
				$cate_list			= array();
				$target_category	= unserialize($tmp['target_category']);
				foreach($target_category as $cate_tmp){
					$cate			= $this->categorymodel->get_category_data($cate_tmp);
					$cate_list[]	= $cate['title'];
				}
				if($cate_list) $tmp['gift_rule_text2'] = "[".implode(",",$cate_list)."] 대상 ";
			}
			$tmp['target_category_title'] = $cate_list;

			$tmp['gift_rule_text']	= "얼마 이상 구매하면 ";
			switch($tmp['gift_rule']){
				case "price":
					$tmp['gift_rule_text'] .= "→ 더 좋은 사은품 선택 가능<br /><span class='fx11'>(".number_format($tmp['benefit_sprice']) ."원 ~ ".number_format($tmp['benefit_eprice'])."원 1개 증정)</span>";
					$tmp['gift_rule_text2'] .= number_format($tmp['benefit_sprice'])."원 ~ ".number_format($tmp['benefit_eprice'])."원 이상 구매 시 사은품 1개 증정";
				break;
				case "quantity":
					$tmp['gift_rule_text'] .= "→ 더 많은 사은품 선택 가능<br /><span class='fx11'>(".number_format($tmp['benefit_sprice']) ."원 ~ ".number_format($tmp['benefit_eprice'])."원 ".number_format($tmp['benefit_ea'])."개 증정)</span>";
					$tmp['gift_rule_text2'] .= "".number_format($tmp['benefit_sprice']) ."원 ~ ".number_format($tmp['benefit_eprice'])."원 이상 구매 시 사은품 ".number_format($tmp['benefit_ea'])."개 증정";
				break;
				case "lot":
					$tmp['gift_rule_text'] .= "→ 추첨(또는 선정 기준에 따라)를 통하여 사은품 증정<br /><span class='fx11'>(".number_format($tmp['benefit_sprice'])."원 이상)</span>";
					$tmp['gift_rule_text2'] .= "".number_format($tmp['benefit_sprice'])."원 이상 구매 시 추첨(또는 선정 기준에 따라)를 통하여 사은품 증정";
				break;
				default :
					$tmp['gift_rule_text'] .= "→ 사은품 선택 가능<br /><span class='fx11'>(".number_format($tmp['benefit_sprice'])."원)</span>";
					$tmp['gift_rule_text2'] .= "".number_format($tmp['benefit_sprice'])."원 이상 구매 시 사은품 1개 증정";
				break;
			}

			// 사은품 보내는 판매자가 비었을 경우 추가
			if (empty($tmp['provider_name']) && serviceLimit('H_AD')) {
				$result = $this->getGiftProvider($tmp['gift_seq']);
				$tmp['provider_name'] = $result['provider_name'];
				$this->updateGiftLogProviderName($tmp);
			}

			$giftlog[] = $tmp;
		}
		return $giftlog;
	}

	public function save_log($gift_seq,$giftdata,$order_seq,$item_seq=''){
		$giftbenefit = $this->get_gift_benefit_info($giftdata['benefit_seq']);
		$giftchoice  = $this->get_gift_choice($gift_seq);

		$gifts_log_param = array();
		$gifts_log_param['order_seq']		= $order_seq;
		$gifts_log_param['item_seq']		= $item_seq;
		$gifts_log_param['gift_seq']		= $gift_seq;
		$gifts_log_param['gift_title']		= $giftdata['title'];
		$gifts_log_param['gift_sdate']		= $giftdata['start_date'];
		$gifts_log_param['gift_edate']		= $giftdata['end_date'];
		$gifts_log_param['goods_rule']		= $giftdata['goods_rule'];
		$gifts_log_param['target_category'] = ($giftdata['real_target_category'])? serialize($giftdata['real_target_category']) : '';
		$gifts_log_param['target_goods']	= ($giftdata['real_target_goods'][$giftdata['ship_grp_seq']])? serialize($giftdata['real_target_goods'][$giftdata['ship_grp_seq']]) : '';
		$gifts_log_param['gift_rule']		= $giftdata['gift_rule'];
		$gifts_log_param['benefit_sprice']	= $giftbenefit['sprice'];
		$gifts_log_param['benefit_eprice']	= $giftbenefit['eprice'];
		$gifts_log_param['benefit_ea']		= $giftbenefit['ea'];
		$gifts_log_param['order_category']	= ($giftchoice['category'])? serialize($giftchoice['category']) : '';
		$gifts_log_param['order_goods']		= ($giftchoice['goods'])? serialize($giftchoice['goods']) : '';
		$gifts_log_param['regist_date']		= date("Y-m-d H:i:s",mktime());
		$gifts_log_param['provider_seq']	= $giftdata['provider_seq']; 
		$gifts_log_param['provider_name']	= $giftdata['provider_name']; 

		$this->db->insert('fm_log_order_gift', $gifts_log_param);
	}

	# 진행중인 사은품 이벤트
	public function get_gift_ing_list(){
		$ing_gift_sql = 'SELECT gift_seq, title, start_date, end_date FROM fm_gift WHERE CURRENT_TIMESTAMP() BETWEEN start_date AND end_date';
		$ing_gift_query = $this->db->query($ing_gift_sql);
		$gift_list = array();
		foreach($ing_gift_query->result_array() as $gift_row){
			$gift_row['gift_title'] = sprintf("%s (%s ~ %s)", $gift_row['title'], substr($gift_row['start_date'],0,10), substr($gift_row['end_date'],0,10));
			$gift_list[] = $gift_row;
		}
		return $gift_list;
	}

	# 상품정보-사은품 이벤트
	public function get_give_gift_list($goods_seq='', $category_codes=array(), $provider_seq='', $shipping_group_seq='') {
		# reset
		$gift_seqs = '';
		$buff = array();

		# gift_seq 추출
		$sql	= ""
		."SELECT gift_seq "
		."FROM fm_gift_choice "
		."WHERE 1 ";

		if($goods_seq) {
			$sql	.= "AND (choice_type='goods' AND goods_seq='".$goods_seq."') ";
		}

		if(sizeof($category_codes)) {
			$sql	.= ""
			.($goods_seq && sizeof($category_codes) ? " OR ":" AND ")
			."(choice_type='category' AND category_code IN('".implode("','",$category_codes)."'))";
		}

		$query	= $this->db->query($sql);
		$rows	= $query->result_array();

		if(sizeof($rows)) {
			foreach($rows as $v) {
				$gift_seqs .= ($gift_seqs ? "', '" : '') . $v['gift_seq'];
			}
		}

		#
		$sql	= ""
		."SELECT "
		."B.gift_seq,"
		."B.title,"
		."B.start_date,"
		."B.end_date,"
		."B.goods_rule, "
		."B.gift_rule, "
		."A.gift_benefit_seq, "
		."A.benefit_rule, "
		."A.sprice, "
		."A.eprice "
		."FROM "
		."fm_gift_benefit A "
		."LEFT JOIN fm_gift B ON A.gift_seq=B.gift_seq "
		."WHERE 1 "
		."AND CURRENT_TIMESTAMP() BETWEEN B.start_date AND B.end_date ";

		# 입점사 코드 존재시
		$provider_seq && $sql .= "AND B.provider_seq='".$provider_seq."' ";

		$sql .= ""
		."AND ( "
		."	B.goods_rule IN('goods','category') AND A.gift_seq IN('".$gift_seqs."') "
		."	OR B.goods_rule='all' "
		.")";
		if	($shipping_group_seq > 0){
			$sql .= " AND B.shipping_group_seq = '" . $shipping_group_seq . "' ";
		}

		$query	= $this->db->query($sql);
		$rows	= $query->result_array();

		if(is_array($rows)) {
			foreach($rows as $row) {
				$gift_seq = $row['gift_seq'];
				$gift_benefit_seq = $row['gift_benefit_seq'];

				if(!array_key_exists($gift_benefit_seq, $buff)) {
					$buff[$gift_seq]['common'] = array(
						'gift_seq' => $gift_seq,
						'title' => $row['title'],
						'start_date' => $row['start_date'],
						'end_date' => $row['end_date']
					);
				}

				if(!array_key_exists($gift_benefit_seq, $buff)) {
					$buff[$gift_seq][$gift_benefit_seq] = array(
						'gift_benefit_seq' => $gift_benefit_seq,
						'benefit_rule' => $row['benefit_rule'],
						'sprice' => $row['sprice'],
						'eprice' => $row['eprice'],
						'goods_rule' => $row['goods_rule'],
						'gift_rule' => $row['gift_rule']
					);
				}
			}
		}

		return array_values($buff);
	}

	public function get($params, $field_str='', $orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		if( $field_str ) $this->db->select($field_str);
		return $this->db->get('fm_gift');
	}

	// 사은품 제공 정보 가져오기
	public function getGiftProvider($giftSeq) {
		$query = $this->db->select('*')
							->from('fm_provider AS provider')
							->join('fm_gift AS gift', 'gift.provider_seq = provider.provider_seq', 'INNER')
							->where('gift.gift_seq', $giftSeq)
							->get();
		$result = $query->row_array();
		return $result;
	}

	// 사은품 제공자 추가
	public function updateGiftLogProviderName($giftInfo) {
		$this->db->where('gift_seq',$giftInfo['gift_seq']);
		$this->db->where('order_seq',$giftInfo['order_seq']);
		$this->db->update('fm_log_order_gift', ['provider_name'=> $giftInfo['provider_name']]);
	}
}