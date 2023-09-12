<?php
class searchdefaultconfigmodel extends CI_Model {

	// 관리자 UI개선 후 검색 설정 가져오기
	public function get_search_config($goodsType,$mode=''){

		$search_page 	= 'bxOpenFixing'.UCFIRST($goodsType);
		$bxOpenFixing 	= $this->get_search_default_config($search_page,$mode);
		$search_info 	= (array) json_decode($bxOpenFixing['search_info']);
		if(isset($search_info['goods'])){
			$fixingGoods = json_decode($search_info['goods']);
		}else{
			$fixingGoods = "null";
		}
		$bxOpenDefault 	= $this->goodsmodel->goods_regist_bxopen_default($goodsType,$bxOpenFixing);
		$bxOpenSet['default']		= $bxOpenDefault;
		$bxOpenSet['fixing'] 		= $fixingGoods;
		$bxOpenSet['bxOpenFixing'] 	= $bxOpenFixing;

		return json_encode($bxOpenSet);
	}
	
	// 관리자 리스트 페이지별 기본검색설정 조회
	public function get_search_default_config($search_page=NULL,$mode=''){

		if($search_page == NULL) return false;

		$provider_seq = 1;
		if (defined('__SELLERADMIN__') === true) {
			$provider_seq = $this->providerInfo['provider_seq'];
			$this->db->where("manager_seq",'0');
			$this->db->where("provider_seq",$provider_seq);
		}else{
			$manager_seq = (int)$this->managerInfo['manager_seq'];
			$this->db->where("manager_seq",$manager_seq);
			$this->db->where("provider_seq",'1');
		}

		$this->db->where("search_page",$search_page);
		$query 	= $this->db->get("fm_search_default_config");
		//debug($this->db->last_query());
		$result = $query->row_array();

		$now = date("Y-m-d H:i:s");

		// 초기값이 없을 경우
		if( !$result['seq'] ){

			//쿠키로 저장된 값이 있으면 마이그레이션
			if($mode != "chk" && $_COOKIE['order_list_search']){
				$search_info = $this->get_search_default_cookie($search_page);
			}

			switch($search_page){
				case "admin/export/batch_status" :
					$search_info = "export_default_date_field=regist_date&export_default_period=-1 mon&export_default_status[0]=55&export_detail_view=close";
					break;
				case "admin/order/order_export_popup" :
					$search_info = "order_default_date_field=deposit_date&order_default_period=-1 mon&order_default_step[0]=25&order_default_step[1]=35&order_detail_view=open";
					break;
			}
			if( $search_info ){
				if (defined('__SELLERADMIN__') === true) {
					$result['provider_seq']	= $provider_seq;
				}else{
					$result['manager_seq']	= $manager_seq;
				}
				$result['search_info']	= $search_info;
				$result['search_page']	= $search_page;
				$result['regist_date']	= $now;
				$result['update_date']	= $now;
			}
		}

		return $result;
	}

	// 관리자UI개선 후 검색 조건 저장
	public function set_search_default_new($params){

		$search_page		= $params['search_page'];
		$search_form_editor = $params['search_form_editor'];
		unset($params['search_page']);
		unset($params['search_form_editor']);
		if (defined('__SELLERADMIN__') === true) {
			$provider_seq 	= $this->providerInfo['provider_seq'];
			$manager_seq	= '0';
		}else{
			$manager_seq 	= (int)$this->managerInfo['manager_seq'];
			$provider_seq	= '1';
		}

		$data = array(
			'manager_seq' => $manager_seq,
			'provider_seq' => $provider_seq,
			'search_info' => json_encode($params),
			'field_default' => json_encode($search_form_editor),
			'search_page' => $search_page
		);
		$json_data = json_encode($params);

		$result = $this->get_search_default_config($search_page,"chk");
		if ($result['seq']) {
			$data['update_date'] = date('Y-m-d H:i:s');
			$this->db->where(array('manager_seq'=>$manager_seq,'provider_seq'=>$provider_seq,'search_page'=>$search_page));
			$this->db->update('fm_search_default_config',$data);
		} else {
			$data['regist_date'] = date('Y-m-d H:i:s');
			$this->db->insert('fm_search_default_config',$data);
		}

		return array('result'=>'success');
	}

	public function set_search_default($param){

		$search_page = $param['search_page'];
		unset($param['search_page']);

		foreach($param as $key => $data){
			if(!is_array($data) && $data){
				if(substr($key,0,2)=='s_') $key = str_replace("s_","",$key);
				$cookie_arr[] = $key."=".$data;
				unset($param[$key]);
			}
		}

		$cookie_arr[] = http_build_query(array_filter($param));
		
		$cookie_str = implode('&',$cookie_arr);

		if (defined('__SELLERADMIN__') === true) {
			$provider_seq 	= $this->providerInfo['provider_seq'];
			$manager_seq	= '0';
		}else{
			$provider_seq 	= '1';
			$manager_seq 	= (int)$this->managerInfo['manager_seq'];
		}

		$data = array(
			'manager_seq' => $manager_seq,
			'provider_seq' => $provider_seq,
			'search_info' => $cookie_str,
			'search_page' => $search_page
		);

		$result = $this->get_search_default_config($search_page,"chk");
		if ($result['seq']) {
			$data['update_date'] = date('Y-m-d H:i:s');
			$this->db->where(array('manager_seq'=>$manager_seq,'provider_seq'=>$provider_seq,'search_page'=>$search_page));
			$this->db->update('fm_search_default_config',$data);
		} else {
			$data['regist_date'] = date('Y-m-d H:i:s');
			$this->db->insert('fm_search_default_config',$data);
		}
	}

	public function get_search_format_date($default_period){

		switch ( $default_period)  {
			case "today" :
				$start_date = date('Y-m-d');
				$end_date	= date('Y-m-d');
				break;
			case "yesterday" :
				$start_date	= date('Y-m-d',strtotime('-1 day'));
				$end_date	= date('Y-m-d',strtotime('-1 day'));
				break;
			case "3day" :
				$start_date = date('Y-m-d',strtotime('-3 day'));
				$end_date	= date('Y-m-d');
				break;
			case "1week" :
			case "work_thisweek" :
				$start_date = date('Y-m-d',strtotime('-1 week'));
				$end_date	= date('Y-m-d');
				break;
			case "work_lastweek" :
				$start_date	= date('Y-m-d',strtotime('-2 week'));
				$end_date	= date('Y-m-d',strtotime('-1 week'));
				break;
			case "1month" :
			case "thismonth" :
				$start_date = date('Y-m-d',strtotime('-1 month'));
				$end_date	= date('Y-m-d');
				break;
			case "lastmonth" :
				$start_date	= date('Y-m-d',strtotime('-2 month'));
				$end_date	= date('Y-m-d',strtotime('-1 month'));
				break;
			case "3month" :
				$start_date = date('Y-m-d',strtotime('-3 month'));
				$end_date	= date('Y-m-d');
				break;
			case "6month" :
				$start_date = date('Y-m-d',strtotime('-6 month'));
				$end_date	= date('Y-m-d');
				break;
			case "1year" :
				$start_date = date('Y-m-d',strtotime('-1 year'));
				$end_date	= date('Y-m-d');
				break;
			case "all" :
				$start_date = "";
				$end_date	= "";
				break;
			default :
				$start_date = date('Y-m-d',strtotime('-1 week'));
				$end_date	= date('Y-m-d');
				break;
		}

		return array('start_date'=>$start_date,'end_date'=>$end_date);
	}

	# cookie 값 마이그레이션
	public function get_search_default_cookie($search_page){

			$arr = explode('&',$_COOKIE['order_list_search']);
			$tmp = array();
			if($arr){
				foreach($arr as $data){
					$arr2 = explode("=",$data);
					if(in_array($arr2[0],array("regist_date","deposit_date"))){
						$tmp['default_date_field']	= $arr2[0];

						if($arr2[1] == "7day"){
							$arr2[1] = "1week";
						}elseif($arr2[1] == "1mon"){
							$arr2[1] = "1month";
						}elseif($arr2[1] == "3mon"){
							$arr2[1] = "3month";
						}
						$tmp['default_period']		= $arr2[1];
					}else{
					$key = explode('[',$arr2[0]);
					$key2 = "default_".$key[0];
					$tmp[$key2][ str_replace(']','',$key[1]) ] = $arr2[1];
					}
				}
				$tmp['search_page'] = $search_page;
				$this->set_search_default($tmp);

			return $_COOKIE['order_list_search'];
		}

	}


	// 관리자Ui-상품 개선전 기본검색설정을 사용중인 경우 검색 노출 항목 마이그레이션
	public function set_default_field_migration($search_info){
		$default_field 		= array();
		$tmp = json_decode($search_info);
		if($tmp->provider_seq || $tmp->commission_type_sel || $tmp->s_commission_rate || $tmp->e_commission_rate) $default_field[] = 'sc_provider_seq';
		if($tmp->category1 || $tmp->category2 || $tmp->category3 || $tmp->category4) $default_field[] = 'sc_category';
		if($tmp->brand1 || $tmp->brand2 || $tmp->brand3 || $tmp->brand4) $default_field[] = 'sc_brand';
		if($tmp->location1 || $tmp->location2 || $tmp->location3 || $tmp->location4) $default_field[] = 'sc_location';
		if($tmp->goodsView) $default_field[] = 'sc_view';
		if($tmp->taxView) $default_field[] = 'sc_tax';
		if($tmp->cancel_type) $default_field[] = 'sc_canceltype';
		if($tmp->adult_goods) $default_field[] = 'sc_adult';
		if($tmp->sprice >= 0 || $tmp->eprice >= 0 ) $default_field[] = 'sc_price';
		if($tmp->sstock >= 0 || $tmp->estock >= 0) $default_field[] = 'sc_stock';
		if($tmp->goods_runout) $default_field[] = 'sc_sale_for_stock';
		if($tmp->shipping_group_seq) $default_field[] = 'sc_shipping';
		if($tmp->event_seq || $tmp->gift_seq || $tmp->referersale_seq) $default_field[] = 'sc_event';
		if($tmp->multi_discount || $tmp->sale_seq) $default_field[] = 'sc_multi_discount';
		if($tmp->market || $tmp->sellerId) $default_field[] = 'sc_openmarket';
		if($tmp->search_feed_status) $default_field[] = 'sc_feed_status';
		if($tmp->search_string_price) $default_field[] = 'sc_string_price';
		if($tmp->favorite_chk) $default_field[] = 'sc_favorite';
		if($tmp->select_search_icon) $default_field[] = 'sc_icon';
		if($tmp->layaway_product) $default_field[] = 'sc_layaway';

		$default_field[] = 'sc_status';
		$tmp->goodsStatus = array('all','normal','runout','purchasing','unsold');

		return array($default_field,json_encode($tmp));
	}
}
?>