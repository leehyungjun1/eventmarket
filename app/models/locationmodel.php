<?php

class locationmodel extends CI_Model {

	/* 브랜ㅡ 목록 반환  */
	public function get_all($arrWhere=array()) {
		$sql = "SELECT *, location_code AS category_code  FROM `fm_location`";

		if($arrWhere){
			$sql .= " where " . implode(" and ",$arrWhere);
		}

		$sql .=" ORDER BY `position` ASC";

		$query = $this->db->query($sql);
		foreach ($query->result_array() as $row){
			$returnArr[] = $row;
		}
		//$query->free_result();
		return $returnArr;
	}

	// 지역 해당 depth 목록 반환 :: 2018-11-06 lwh
	public function get_location_depth_list($depth=1, $target_code=null,$limit=null){
		$isWhere		= array();
		$params[]		= $depth + 1;
		if($target_code){
			$isWhere[]	= "location_code LIKE ?";
			$params[]	= $target_code.'%';
		}
		$query = "SELECT * FROM fm_location WHERE level=? ";
		if(count($isWhere)>0) $query .= " AND " . implode(" AND ",$isWhere);
		$query			.=  " ORDER BY `position` ASC, `left` ASC ";
		if($limit) $query	.=  " LIMIT ".$limit;
		$query			= $this->db->query($query, $params);
		foreach ($query->result_array() as $row){
			$location_list[$row['location_code']] = $row;
		}

		return $location_list;
	}

	/* 지역 목록 Depth 구분하여 반환 (프론트 출력용도) */
	public function get_location_view($location_codes=array(),$maxDepth=4,$division='',$parent=null){

		/* 지역 목록  */
		$params = array("level >= 2");
		if($division=='catalog' || $division=='searchForm'){
			$params[] = "hide_in_navigation != '1'";
		}elseif($division=='gnb'){
			$params[] = "hide_in_gnb != '1'";
		}elseif($division=='admin'){
			$params[] = "type = 'folder'";
		}else{
			$params[] = "hide != '1'";
		}
		if($location_codes) $params[] = "location_code in ('".implode("','",$location_codes)."')";
		if($parent)				$params[] = "location_code like '".$parent."%'";
		if($maxDepth<4) $params[] = "level <= '".($maxDepth+1)."'";
		$location_list = $this->get_all($params);

		/* 텍스트,이미지 효과 */
		if(is_array($location_list)) $location_list = $this->design_set($location_list,$division);

		/* Depth별로 나눔 */
		$location = array();
		$location = divisionLocationDepths($location_list,$location);

		return $location;
	}

	public function get_list($code,$arrWhere=array()) {
		if( $code ){
			$level = strlen($code) / 4 + 2;
			$where[] = "`location_code` like ?";
			$whereVal[] = $code."%";
			$where[] = "`level` = ?";
			$whereVal[] = $level;
		}else{
			$where[] = "`level` = ?";
			$whereVal[] = 2;
		}

		$whereStr = implode(' and ',$where);

		if($arrWhere){
			$whereStr .= " and " . implode(" and ",$arrWhere);
		}

		$query = "select * from `fm_location` where $whereStr order by `position` asc, `left` asc";
		$query = $this->db->query($query,$whereVal);
		$result = array();
		foreach ($query->result_array() as $row){
			$result[] = $row;
		}
		return $result;
	}

	public function get_admin_list($code,$arrWhere=array()) {
		if( $code ){
			$level = strlen($code) / 4 + 2;
			$where[] = "`location_code` like ?";
			$whereVal[] = $code."%";
			$where[] = "`level` = ?";
			$whereVal[] = $level;
		}else{
			$where[] = "`level` = ?";
			$whereVal[] = 2;
		}

		$whereStr = implode(' and ',$where);

		if($arrWhere){
			$whereStr .= " and " . implode(" and ",$arrWhere);
		}

		$query = "select `id`,`title`,`location_code`,`location_code` AS category_code from `fm_location` where $whereStr order by `position` asc, `left` asc";
		$query = $this->db->query($query,$whereVal);
		$result = array();
		foreach ($query->result_array() as $row){
			$result[] = $row;
		}
		return $result;
	}

	public function get_location_data($code=''){

		if($code){
			$this->db->where('location_code',$code);
		}else{
			$this->db->where('level','2');
			$this->db->order_by('position asc');
			$this->db->limit(1);
		}

		$query = $this->db->get('fm_location');
		$locationData = $query->row_array();

		// 지역 명칭 이스케이프 추가 :: 2019-04-09 pjw
		$categoryData['title'] = addslashes($categoryData['title']);

		if($locationData['list_info_settings']){
			$locationData['list_info_settings'] = str_replace("\"{","{",$locationData['list_info_settings']);
			$locationData['list_info_settings'] = str_replace("}\"","}",$locationData['list_info_settings']);
		}
		if($locationData['m_list_info_settings']){
			$locationData['m_list_info_settings'] = str_replace("\"{","{",$locationData['m_list_info_settings']);
			$locationData['m_list_info_settings'] = str_replace("}\"","}",$locationData['m_list_info_settings']);
		}

		return $locationData;
	}

	public function get_location_name($code) {
		$result = false;
		for($i=4;$i<=strlen($code);$i+=4){
			$codecd = substr($code,0,$i);
			$sql = "SELECT `title`,'' as `location_goods_code`  FROM `fm_location` where `location_code`='$codecd'";
			$query = $this->db->query($sql);
			foreach ($query->result_array() as $row){
				$arr[] = ($row['location_goods_code'])?$row['title'].' ('.$row['location_goods_code'].')':$row['title'];
			}
		}
		if($arr) $result = implode(" > ",$arr);
		//$query->free_result();
		return $result;
	}

	//지역코드
	public function get_location_goods_code($code,$type='view') {
		$cache_item_id = sprintf('location_goods_code_%s_%s', $code, $type);
		$result = cache_load($cache_item_id);
		if ($result === false) {
			for($i=4;$i<=strlen($code);$i+=4){
				$codecd = substr($code,0,$i);
				if($type == 'modify' && strlen($code) == $i ) break;
				$sql = "SELECT '' as `location_goods_code` FROM `fm_location` where `location_code`='$codecd'";
				$query = $this->db->query($sql);
				foreach ($query->result_array() as $row){
					$arr[] = $row['location_goods_code'];
				}
			}
			if($arr) $result = implode(" > ",$arr);

			//
			if (! is_cli()) {
				cache_save($cache_item_id, $result);
			}
		}
		return $result;
	}


	public function get_location_name_href($code) {
		$cache_item_id = sprintf('location_name_href_%s', $code);
		$result = cache_load($cache_item_id);
		if ($result === false) {
			for($i=4;$i<=strlen($code);$i+=4){
				$codecd = substr($code,0,$i);
				$sql = "SELECT `title` FROM `fm_location` where `location_code`='$codecd'";
				$query = $this->db->query($sql);
				foreach ($query->result_array() as $row){
					$arr[] = '<a href="/goods/catalog?code='.$codecd.'" target="_blank">'.$row['title'].'</a>';
				}
			}
			if($arr) $result = implode(" > ",$arr);
			//$query->free_result();

			//
			if (! is_cli()) {
				cache_save($cache_item_id, $result);
			}
		}
		return $result;
	}

	public function one_location_name($code) {
		$sql = "SELECT `title` FROM `fm_location` where `location_code`='$code'";
		$query = $this->db->query($sql);
		list($row) = $query->result_array();
		return $row['title'];
	}

	public function get_location_code($id) {
		$sql = "SELECT `location_code` FROM `fm_location` where `id`='$id'";
		$query = $this->db->query($sql);
		list($row) = $query->result_array();
		return $row['location_code'];
	}

	// 카테고리 코드를 차수 별로 나눈다
	public function split_location($code){
		for($i=4;$i<=strlen($code);$i+=4){
			$location[] = substr($code,0,$i);
		}
		return $location;
	}

	public function get_next_positon($parent_id){
		$query = "".
			"select max(`position`) max from `fm_location` ".
			"where `parent_id` = ?";

		$query = $this->db->query($query,array($parent_id));
		list($tmp) = $query->result_array();

		if($tmp['max']){
			$tmp['max'] += 1;
		}
		return $tmp['max'];
	}


	public function get_next_location(){
		$qry = "select max(substring(location_code,1,4))+1 as location_code from fm_location";
		$query = $this->db->query($qry);
		list($cate) = $query->result_array();
		if(strlen($cate['location_code'])<4){
			for($i=0;$i<(4-strlen($cate['location_code']));$i++){
				$location .= "0";
			}
			$location .= $cate['location_code'];
		}
		return $location;
	}


	public function get_next_left(){
		$qry = "select max(`right`)+1 as max_left from fm_location;";
		$query = $this->db->query($qry);
		list($cate) = $query->result_array();
		return $cate['max_left'];
	}

	public function get_location_groups($location){
		$qry = "select * from fm_location_group where location_code=?;";
		$query = $this->db->query($qry,$location);
		return $query->result_array();
	}

	// 지역 그룹별 지역 목록 반환 :: 2018-11-08 lwh
	public function get_location_group_for_member($location=null){
		$this->db->select('lg.*, mg.group_name');
		$this->db->from('fm_location_group  AS lg');
		$this->db->join('fm_member_group AS mg', 'lg.group_seq = mg.group_seq', 'left');
		if (is_array($location) === true && count($location) > 0) {
			$this->db->where_in('lg.location_code', $location);
		} elseif ($location) {
			$this->db->where('lg.location_code', $location);
		}
		$query = $this->db->get();
		return $query->result_array();
	}

	public function get_location_group_for_goods($goods_seq)
	{
		$query = "select group_seq from fm_location_group where
			location_code = (select location_code from fm_location_link where goods_seq=? and link=1 order by length(location_code) desc limit 1)";
		$query = $this->db->query($query,$goods_seq);
		foreach($query->result_array() as $data){
			$result[] = $data['group_seq'];
		}
		return $result;
	}

	/* 지역 목록 반환  */
	public function get_location_title() {
		$sql = "SELECT * FROM `fm_location` where id='2' and  parent_id='1'";

		$query = $this->db->query($sql);
		$data = $query->row_array();

		$return = $data['title'];
		//$query->free_result();
		return $return;
	}

	// 추천상품 디스플레이 seq 가져오기 (seq 가 없는경우 새로생성 후 리턴)
	// 미리 생성할 경우 페이지관리에서 존재하는것으로 인식하므로 불필요할땐 조회용으로만 사용하게 수정 :: 2019-01-25 pjw
	public function get_location_recommend_display_seq($locationCode, $make = true){
		$query = $this->db->query("select * from fm_location where level>0 and location_code = ?",$locationCode);
		$locationData = $query->row_array();

		$query = $this->db->query("select * from fm_design_display where display_seq = ?",$locationData['recommend_display_seq']);
		$displayData = $query->row_array();

		if($locationData['recommend_display_seq'] && $displayData){
			$query = "update fm_design_display set kind='location' where display_seq=?";
			$this->db->query($query,array($locationData['recommend_display_seq']));

			$query = $this->db->query("select * from fm_design_display where display_seq = ?",$locationData['m_recommend_display_seq']);
			$m_displayData = $query->row_array();

			if($locationData['m_recommend_display_seq'] && $m_displayData){
				$query = "update fm_design_display set kind='location_mobile' where display_seq=?";
				$this->db->query($query,array($locationData['m_recommend_display_seq']));
				$m_recommend_display_seq = $locationData['m_recommend_display_seq'];
			}else{
				$data = array(
					'kind' => 'location_mobile',
					'count_h' => '1',
					'platform' => 'mobile',
					'regdate' => date('Y-m-d H:i:s'),
				);
				$this->db->insert('fm_design_display', $data);
				$m_recommend_display_seq = $this->db->insert_id();

				$data = array(
					'display_seq' => $m_recommend_display_seq,
					'display_tab_index' => '0',
				);
				$this->db->insert('fm_design_display_tab', $data);

				$query = "update fm_location set m_recommend_display_seq=? where location_code=?";
				$this->db->query($query,array($m_recommend_display_seq,$locationCode));
			}

			// [반응형스킨] responsive 반응형 추가 :: 2018-11-30 pjw
			$query = $this->db->query("select * from fm_design_display where display_seq = ?",$locationData['recommend_display_light_seq']);
			$r_displayData = $query->row_array();
			if($locationData['recommend_display_light_seq'] && $r_displayData ){
				$query = "update fm_design_display set kind='location' where display_seq=?";
				$this->db->query($query,array($locationData['recommend_display_light_seq']));
				$recommend_display_light_seq = $locationData['recommend_display_light_seq'];
			}else{
				$data = array(
					'kind' => 'location',
					'platform' => 'responsive',
					'regdate' => date('Y-m-d H:i:s'),
				);
				$this->db->insert('fm_design_display', $data);
				$recommend_display_light_seq = $this->db->insert_id();

				$data = array(
					'display_seq' => $recommend_display_light_seq,
					'display_tab_index' => '0',
				);
				$this->db->insert('fm_design_display_tab', $data);

				$query = "update fm_location set recommend_display_light_seq=? where location_code=?";
				$this->db->query($query,array($recommend_display_light_seq,$locationCode));
			}

			return array('recommend_display_seq'=>$locationData['recommend_display_seq'],'m_recommend_display_seq'=>$m_recommend_display_seq, 'recommend_display_light_seq'=>$recommend_display_light_seq);

		}else if($make){// 데이터가 없을 경우 기존엔 insert 하였지만 필요에 따라 넘길수도 있음
			###### PC ######
			$data = array(
				'kind'		=> 'location',
				'count_h'	=> '1',
				'platform'	=> 'pc',
				'regdate'	=> date('Y-m-d H:i:s'),
			);
			$this->db->insert('fm_design_display', $data);
			$recommend_display_seq = $this->db->insert_id();

			$data = array(
				'display_seq' => $recommend_display_seq,
				'display_tab_index' => '0',
			);
			$this->db->insert('fm_design_display_tab', $data);
			###### PC ######

			###### MOBILE ######
			$data = array(
				'kind'		=> 'location_mobile',
				'count_h'	=> '1',
				'platform'	=> 'mobile',
				'regdate'	=> date('Y-m-d H:i:s'),
			);

			$this->db->insert('fm_design_display', $data);
			$m_recommend_display_seq = $this->db->insert_id();

			$data = array(
				'display_seq' => $m_recommend_display_seq,
				'display_tab_index' => '0',
			);
			$this->db->insert('fm_design_display_tab', $data);
			###### MOBILE ######

			// [반응형스킨] responsive 반응형 추가 :: 2018-11-30 pjw
			###### RESPONSIVE ######
			$data = array(
				'kind'		=> 'location',
				'count_h'	=> '1',
				'platform'	=> 'responsive',
				'regdate'	=> date('Y-m-d H:i:s'),
			);
			$this->db->insert('fm_design_display', $data);
			$recommend_display_light_seq = $this->db->insert_id();

			$data = array(
				'display_seq' => $recommend_display_light_seq,
				'display_tab_index' => '0',
			);
			$this->db->insert('fm_design_display_tab', $data);
			###### RESPONSIVE ######

			$query = "update fm_location set recommend_display_seq=?,m_recommend_display_seq=?,recommend_display_light_seq=? where location_code=?";
			$this->db->query($query,array($recommend_display_seq,$m_recommend_display_seq,$recommend_display_light_seq,$locationCode));

			return array('recommend_display_seq'=>$recommend_display_seq,'m_recommend_display_seq'=>$m_recommend_display_seq, 'recommend_display_light_seq'=>$recommend_display_light_seq);
		}else{
			return null;
		}
	}

	public function set_location_recommend($locationCode,$params){
		$this->load->model('goodsdisplay');
		if(!isset($params['m_list_use'])) $params['m_list_use'] = 'n';
		$m_params = $params;

		$recommend_display_seq_arr = $this->get_location_recommend_display_seq($locationCode);
		$recommend_display_seq = $recommend_display_seq_arr['recommend_display_seq'];
		$m_recommend_display_seq = $recommend_display_seq_arr['m_recommend_display_seq'];

		$recommend_image_decoration = isset($params['recommend_image_decoration']) ? $params['recommend_image_decoration'] : '';
		$recommend_info_setting = isset($params['recommend_info_setting']) ? $params['recommend_info_setting'] : array();

		$recommendData = $this->goodsdisplay->get_display($recommend_display_seq);

		$params['style'] = $params['recommend_style'];
		$params['count_w'] = $params['recommend_count_w'];
		$params['count_h'] = $params['recommend_count_h'];
		$params['image_size'] = $params['recommend_image_size'];
		$params['text_align'] = $params['recommend_text_align'];
		$params['image_decorations'] = $recommend_image_decoration;
		$params['info_settings'] = "[".implode(",",$recommend_info_setting)."]";

		$data = filter_keys($params, $this->db->list_fields('fm_design_display'));
		unset($data['auto_use']);
		$this->db->update('fm_design_display', $data, "display_seq = {$recommend_display_seq}");

		$this->db->query("delete from fm_design_display_tab where display_seq=?",$recommend_display_seq);
		if(!$params['tab_title']) $params['tab_title'] = array('');
		if(count($params['tab_title'])>1){
			foreach($params['tab_title'] as $tab_index => $tab_title){
				$tab_data = array();
				$tab_data['display_seq'] = $recommend_display_seq;
				$tab_data['display_tab_index'] = $tab_index;

				$tab_data['tab_title'] = count($params['tab_title']) > 1 ? $tab_title : '';

				// 이미지업로드
				if($params['popup_tab_design_kind']=='image'){
					if($_FILES['new_tab_title_img']['tmp_name'][$tab_index]){
						$config['file_name'] = "tab_{$recommend_display_seq}_{$tab_index}";
						$this->upload->initialize($config);
						$this->upload->do_upload('new_tab_title_img',$tab_index);
						$res = $this->upload->data();
						$tab_data['tab_title_img'] = $res['file_name']?$res['file_name']:$params['tab_title_img'][$tab_index];
					}else{
						$tab_data['tab_title_img'] = $params['tab_title_img'][$tab_index];
					}

					if($_FILES['new_tab_title_img_on']['tmp_name'][$tab_index]){
						$config['file_name'] = "tab_{$recommend_display_seq}_{$tab_index}_on";
						$this->upload->initialize($config);
						$this->upload->do_upload('new_tab_title_img_on',$tab_index);
						$res = $this->upload->data();
						$tab_data['tab_title_img_on'] = $res['file_name']?$res['file_name']:$params['tab_title_img_on'][$tab_index];
					}else{
						$tab_data['tab_title_img_on'] = $params['tab_title_img_on'][$tab_index];
					}
				}

				$tab_data['contents_type'] = $params['contents_type'][$tab_index];
				$tab_data['auto_use'] = $params['contents_type'][$tab_index] == 'auto' || $params['contents_type'][$tab_index] == 'auto_sub' ? 'y' : 'n';
				$tab_data['auto_criteria'] = $params['auto_criteria'][$tab_index];
				$tab_data['tab_contents'] = $params['tab_contents'][$tab_index];
				$tab_data['tab_contents_mobile'] = $params['tab_contents_mobile'][$tab_index];

				if( $tab_data['auto_criteria']!='' && preg_match('/∀/',$tab_data['auto_criteria'])){
					$tab_data['auto_condition_use'] = 1;
				}
				$this->db->insert('fm_design_display_tab', $tab_data);
			}
		}elseif(isset($params['contents_type'])){
			$tab_data = array();
			$tab_data['display_seq'] = $recommend_display_seq;
			$tab_data['display_tab_index'] = 0;
			$tab_data['tab_title'] = '';
			$tab_data['tab_title_img'] = '';
			$tab_data['tab_title_img_on'] = '';
			$tab_data['contents_type'] = $params['contents_type'][0];
			$tab_data['auto_use'] = $params['contents_type'][0] == 'auto' || $params['contents_type'][$tab_index] == 'auto_sub' ? 'y' : 'n';
			$tab_data['auto_criteria'] = $tab_data['auto_use'] == 'y' ? $params['auto_criteria'][0] : '';
			$tab_data['tab_contents'] = $params['tab_contents'][0];
			$tab_data['tab_contents_mobile'] = $params['tab_contents_mobile'][0];

			if( $tab_data['auto_criteria']!='' && preg_match('/∀/',$tab_data['auto_criteria'])){
				$tab_data['auto_condition_use'] = 1;
			}
			$this->db->insert('fm_design_display_tab', $tab_data);
		}

		$this->db->query("delete from fm_design_display_tab_item where display_seq=?",$recommend_display_seq);
		if(isset($params['auto_goods_seqs'])){
			foreach($params['auto_goods_seqs'] as $tab_index=>$auto_goods_seqs){
				$arr_goods_seqs = explode(",",$auto_goods_seqs);
				foreach($arr_goods_seqs as $goods_seq){
					if($goods_seq){
						$data = array(
							"display_seq" => $recommend_display_seq,
							"display_tab_index" => $tab_index,
							"goods_seq" => $goods_seq
						);
						$this->db->insert('fm_design_display_tab_item', $data);
					}
				}
			}
		}

		//2015-12-29 모바일

		$recommend_image_decoration = isset($m_params['m_recommend_image_decoration']) ? $m_params['m_recommend_image_decoration'] : '';
		$recommend_info_setting = isset($m_params['m_recommend_info_setting']) ? $m_params['m_recommend_info_setting'] : array();

		$recommendData = $this->goodsdisplay->get_display($m_recommend_display_seq);

		$m_params['style'] = $m_params['m_style'];
		$m_params['count_w'] = $m_params['count_w_swipe'];
		$m_params['count_h'] = $m_params['count_h_swipe'];
		$m_params['image_size'] = $m_params['m_recommend_image_size'];
		$m_params['text_align'] = $m_params['m_recommend_text_align'];
		$m_params['tab_design_type'] = $m_params['m_tab_design_type'];
		$m_params['navigation_paging_style'] = $m_params['navigation_paging_style'];
		$m_params['image_decorations'] = $recommend_image_decoration;
		$m_params['info_settings'] = "[".implode(",",$recommend_info_setting)."]";

		$data = filter_keys($m_params, $this->db->list_fields('fm_design_display'));
		unset($data['auto_use']);
		$this->db->update('fm_design_display', $data, "display_seq = {$m_recommend_display_seq}");

		$this->db->query("delete from fm_design_display_tab where display_seq=?",$m_recommend_display_seq);
		if(!$m_params['m_tab_title']) $m_params['m_tab_title'] = array('');
		if(count($m_params['m_tab_title'])>1){
			foreach($m_params['m_tab_title'] as $tab_index => $tab_title){
				$tab_data = array();
				$tab_data['display_seq'] = $m_recommend_display_seq;
				$tab_data['display_tab_index'] = $tab_index;

				$tab_data['tab_title'] = count($m_params['m_tab_title']) > 1 ? $tab_title : '';

				// 이미지업로드
				if($m_params['m_popup_tab_design_kind']=='image'){
					if($_FILES['m_new_tab_title_img']['tmp_name'][$tab_index]){
						$config['file_name'] = "tab_{$m_recommend_display_seq}_{$tab_index}";
						$this->upload->initialize($config);
						$this->upload->do_upload('m_new_tab_title_img',$tab_index);
						$res = $this->upload->data();
						$tab_data['tab_title_img'] = $res['file_name']?$res['file_name']:$m_params['m_tab_title_img'][$tab_index];
					}else{
						$tab_data['tab_title_img'] = $m_params['m_tab_title_img'][$tab_index];
					}

					if($_FILES['m_new_tab_title_img_on']['tmp_name'][$tab_index]){
						$config['file_name'] = "tab_{$m_recommend_display_seq}_{$tab_index}_on";
						$this->upload->initialize($config);
						$this->upload->do_upload('m_new_tab_title_img_on',$tab_index);
						$res = $this->upload->data();
						$tab_data['tab_title_img_on'] = $res['file_name']?$res['file_name']:$m_params['m_tab_title_img_on'][$tab_index];
					}else{
						$tab_data['tab_title_img_on'] = $m_params['m_tab_title_img_on'][$tab_index];
					}
				}

				$tab_data['contents_type'] = $m_params['m_contents_type'][$tab_index];
				$tab_data['auto_use'] = $m_params['m_contents_type'][$tab_index] == 'auto' || $m_params['m_contents_type'][$tab_index] == 'auto_sub' ? 'y' : 'n';
				$tab_data['auto_criteria'] = $m_params['m_auto_criteria'][$tab_index];
				$tab_data['tab_contents_mobile'] = $m_params['m_tab_contents_mobile'][$tab_index];

				if( $tab_data['auto_criteria']!='' && preg_match('/∀/',$tab_data['auto_criteria'])){
					$tab_data['auto_condition_use'] = 1;
				}
				$this->db->insert('fm_design_display_tab', $tab_data);
			}
		}elseif(isset($m_params['m_contents_type'])){
			$tab_data = array();
			$tab_data['display_seq'] = $m_recommend_display_seq;
			$tab_data['display_tab_index'] = 0;
			$tab_data['tab_title'] = '';
			$tab_data['tab_title_img'] = '';
			$tab_data['tab_title_img_on'] = '';
			$tab_data['contents_type'] = $m_params['m_contents_type'][0];
			$tab_data['auto_use'] = $m_params['m_contents_type'][0] == 'auto' || $m_params['m_contents_type'][0] == 'auto_sub' ? 'y' : 'n';
			$tab_data['auto_criteria'] = $tab_data['auto_use'] == 'y' ? $m_params['m_auto_criteria'][0] : '';
			$tab_data['tab_contents_mobile'] = $m_params['m_tab_contents_mobile'][0];

			if( $tab_data['auto_criteria']!='' && preg_match('/∀/',$tab_data['auto_criteria'])){
				$tab_data['auto_condition_use'] = 1;
			}
			$this->db->insert('fm_design_display_tab', $tab_data);
		}

		$this->db->query("delete from fm_design_display_tab_item where display_seq=?",$m_recommend_display_seq);
		if(isset($m_params['m_auto_goods_seqs'])){
			foreach($m_params['m_auto_goods_seqs'] as $tab_index=>$auto_goods_seqs){
				$arr_goods_seqs = explode(",",$auto_goods_seqs);
				foreach($arr_goods_seqs as $goods_seq){
					if($goods_seq){
						$data = array(
							"display_seq" => $m_recommend_display_seq,
							"display_tab_index" => $tab_index,
							"goods_seq" => $goods_seq
						);
						$this->db->insert('fm_design_display_tab_item', $data);
					}
				}
			}
		}

		return $recommend_display_seq_arr;
	}

	public function childset_location($div=null,$location_code=''){

		$query = $this->db->query("select * from fm_location where level>0 and ifnull(location_code,'')=?",$location_code);
		$locationData = $query->row_array();

		switch($div){
			case "top_html":
				$this->db->query("update fm_location set top_html = ?, update_date=now() where location_code like '{$location_code}%' and location_code!='{$location_code}'",$locationData['top_html']);
			break;
			case "recommend":
				$this->recommend_childset($location_code,$locationData['recommend_display_seq'],'pc');
				$this->recommend_childset($location_code,$locationData['m_recommend_display_seq'],'mobile');
			break;
			case "location":
				$data = array(
					'list_default_sort'			=> $locationData['list_default_sort'],
					'list_style'				=> $locationData['list_style'],
					'list_count_w'				=> $locationData['list_count_w'],
					'list_count_w_lattice_b'	=> $locationData['list_count_w_lattice_b'],
					'list_count_h'				=> $locationData['list_count_h'],
					'list_paging_use'			=> $locationData['list_paging_use'],
					'list_image_size'			=> $locationData['list_image_size'],
					'list_text_align'			=> $locationData['list_text_align'],
					'list_image_decorations'	=> $locationData['list_image_decorations'],
					'list_info_settings'		=> $locationData['list_info_settings'],
					'list_goods_status'			=> $locationData['list_goods_status'],
					'm_list_use'				=> $locationData['m_list_use'],
					'm_list_default_sort'		=> $locationData['m_list_default_sort'],
					'm_list_style'				=> $locationData['m_list_style'],
					'm_list_count_w'			=> $locationData['m_list_count_w'],
					'm_list_count_h'			=> $locationData['m_list_count_h'],
					'm_list_count_r'			=> $locationData['m_list_count_r'],
					'm_list_paging_use'			=> $locationData['m_list_paging_use'],
					'm_list_mobile_h'			=> $locationData['m_list_mobile_h'],
					'm_list_image_size'			=> $locationData['m_list_image_size'],
					'm_list_text_align'			=> $locationData['m_list_text_align'],
					'm_list_image_decorations'	=> $locationData['m_list_image_decorations'],
					'm_list_info_settings'		=> $locationData['m_list_info_settings'],
					'm_list_goods_status'		=> $locationData['m_list_goods_status'],
					'list_count_h_lattice_b'	=> $locationData['list_count_h_lattice_b'],
					'list_count_h_list'			=> $locationData['list_count_h_list'],
					'list_image_size_lattice_b'	=> $locationData['list_image_size_lattice_b'],
					'list_image_size_list'		=> $locationData['list_image_size_list'],
					'img_opt_lattice_a'			=> $locationData['img_opt_lattice_a'],
					'img_padding_lattice_a'		=> $locationData['img_padding_lattice_a'],
					'image_decoration_type'		=> $locationData['image_decoration_type'],
					'image_decoration_favorite_key'		=> $locationData['image_decoration_favorite_key'],
					'image_decoration_favorite'			=> $locationData['image_decoration_favorite'],
					'goods_decoration_type'				=> $locationData['goods_decoration_type'],
					'goods_decoration_favorite_key'		=> $locationData['goods_decoration_favorite_key'],
					'goods_decoration_favorite'			=> $locationData['goods_decoration_favorite'],
					'm_image_decoration_type'			=> $locationData['m_image_decoration_type'],
					'm_image_decoration_favorite_key'	=> $locationData['m_image_decoration_favorite_key'],
					'm_image_decoration_favorite'		=> $locationData['m_image_decoration_favorite'],
					'm_goods_decoration_type'			=> $locationData['m_goods_decoration_type'],
					'm_goods_decoration_favorite_key'	=> $locationData['m_goods_decoration_favorite_key'],
					'm_goods_decoration_favorite'		=> $locationData['m_goods_decoration_favorite'],
					'update_date'				=> date('Y-m-d H:i:s'),
				);
				$this->db->update('fm_location', $data, "location_code like '{$location_code}%' and length(location_code)>".strlen($location_code));
			break;
		}

	}

	public function recommend_childset($location_code,$recommend_display_seq,$type){
		$this->load->model('goodsdisplay');

		$recommend_display_seq_var = 'recommend_display_seq';
		if($type == 'mobile') $recommend_display_seq_var = 'm_recommend_display_seq';

		$query = $this->db->query("select * from fm_design_display where display_seq = ?",$recommend_display_seq);
		$recommend_display_data = $query->row_array();

		$query = $this->db->query("select * from fm_location where location_code like '{$location_code}%' and length(location_code)>".strlen($location_code));
		foreach($query->result_array() as $childLocationData){

			if($childLocationData[$recommend_display_seq_var]==$recommend_display_seq) continue;

			/* 하위카테고리에 설정된 상품디스플레이 제거*/
			$this->db->query("delete from fm_design_display where display_seq=?",$childLocationData[$recommend_display_seq_var]);
			$this->db->query("delete from fm_design_display_tab where display_seq=?",$childLocationData[$recommend_display_seq_var]);
			$this->db->query("delete from fm_design_display_tab_item where display_seq=?",$childLocationData[$recommend_display_seq_var]);

			/* 사용시 하위복사 */
			if($recommend_display_seq){
				$child_recommend_display_seq = $this->goodsdisplay->copy_display($recommend_display_seq);
				if($child_recommend_display_seq){
					$this->db->query("update fm_location set {$recommend_display_seq_var}='{$child_recommend_display_seq}', update_date=now() where location_code = '{$childLocationData['location_code']}'");
				}

			/* 미사용시 하위제거 */
			}else{
				if($childLocationData[$recommend_display_seq_var]){
					$this->db->query("update fm_location set {$recommend_display_seq_var}='', update_date=now() where location_code = '{$childLocationData['location_code']}'");
				}
			}

		}
	}

	public function chkDupleSort($locationCode){
		$query			= $this->db->query("select count(location_link_seq) as cnt from fm_location_link where location_code = ? group by sort having cnt > 1 limit 1", $locationCode);
		$chkData		= $query->row_array();
		if	($chkData)	return true;
		else			return false;
	}

	public function reSortAll($locationCode){

		$query = $this->db->query("select l.* from fm_goods_option o, fm_goods g, fm_location_link l where g.goods_view='look' AND g.goods_type = 'goods' and o.goods_seq=g.goods_seq and l.goods_seq = g.goods_seq and o.default_option ='y' and l.location_code = ? group by g.goods_seq order by l.sort asc, g.regist_date desc", $locationCode);
		$sort	= 0;
		foreach($query->result_array() as $linkData){
			if	($linkData['location_link_seq']){
				$this->db->query("update fm_location_link set sort = ? where location_link_seq = ? ", array($sort, $linkData['location_link_seq']));
				$sort++;
			}
		}

		config_save('mig_sort_location',array($locationCode=>'Y'));
	}

	public function getSortValue($locationCode, $type){
		switch($type){
			case 'min':
				$query		= "SELECT MIN(sort) sortVal FROM fm_location_link WHERE location_code = ? ";
			break;
			case 'max':
				$query		= "SELECT MAX(sort) sortVal FROM fm_location_link WHERE location_code = ? ";
			break;
			case 'cnt':
				$query		= "SELECT COUNT(location_link_seq) sortVal FROM fm_location_link WHERE location_code = ? GROUP BY g.goods_seq";
			break;
			case 'mobile_min':
				$query		= "SELECT MIN(mobile_sort) sortVal FROM fm_location_link WHERE location_code = ? ";
			break;
			case 'mobile_max':
				$query		= "SELECT MAX(mobile_sort) sortVal FROM fm_location_link WHERE location_code = ? ";
			break;
		}

		$query		= $this->db->query($query, $categoryCode);
		$sortData	= $query->row_array();

		return $sortData['sortVal'];
	}

	public function rangeUpdateSort($locationCode, $sSort, $eSort, $addSort){
		if	(!is_null($sSort))
			$addWhere	.= " and sort > ".$sSort." ";
		if	(!is_null($eSort))
			$addWhere	.= " and sort < ".$eSort." ";
		$this->db->query("update fm_location_link set sort=sort".$addSort." where location_code = ? ".$addWhere, $locationCode);
	}

	public function chgLocationSort($location_link_seq, $sort){
		if	($location_link_seq){
			$this->db->query("update fm_location_link set sort=? where location_link_seq = ? ", array($sort, $location_link_seq));
		}
	}

	public function design_set($childLocationData,$division='catalog'){
		// 운영방식 추가 :: 2018-11-29 pjw
		$operation_type = $this->config_system['operation_type'];
		if($division=='searchForm') $division = 'catalog';

		$division = $division ? '_'.$division.'_' : '_';

		foreach($childLocationData as $k=>$row){
			$childLocationData[$k]['title'] = htmlspecialchars($childLocationData[$k]['title']);
			$childLocationData[$k]['ori_title'] = $childLocationData[$k]['title'];

			// 운영방식이 light 형이 아닌 경우에만 기존 스타일 적용 :: 2018-11-29 pjw
			if($operation_type != 'light'){
				if($childLocationData[$k]['node'.$division.'type']=='text'){
					if($childLocationData[$k]['node'.$division.'text_normal']){
						$attrStyle = font_decoration_attr($childLocationData[$k]['node'.$division.'text_normal'],'css','style');
						$attrOnmouseover = font_decoration_attr($childLocationData[$k]['node'.$division.'text_over'],'script','onmouseover');
						$attrOnmouseout = font_decoration_attr($childLocationData[$k]['node'.$division.'text_normal'],'script','onmouseout');

						$childLocationData[$k]['title'] = "<span {$attrStyle} {$attrOnmouseover} {$attrOnmouseout}>{$childLocationData[$k]['title']}</span>";
					}
				}

				if($childLocationData[$k]['node'.$division.'type']=='image'){
					$attrSrc ='';
					$attrOnmouseover ='';
					$attrOnmouseout ='';
					if($childLocationData[$k]['node'.$division.'image_normal']){
						$attrSrc = 'src="'.$childLocationData[$k]['node'.$division.'image_normal'].'"';
					}
					if($childLocationData[$k]['node'.$division.'image_over']){
						$attrOnmouseover = 'onmouseover="this.src=\''.$childLocationData[$k]['node'.$division.'image_over'].'\'"';
						$attrOnmouseout = 'onmouseout="this.src=\''.$childLocationData[$k]['node'.$division.'image_normal'].'\'"';
					}

					$childLocationData[$k]['title'] = "<img {$attrSrc} {$attrOnmouseover} {$attrOnmouseout} />";
				}
			}

			$childLocationData[$k]['name'] = $childLocationData[$k]['title'];
		}
		return $childLocationData;
	}

	public function get_represent_location_for_goods($goods_seq){

		$query = "select b.* from fm_location as b, fm_location_link as bl where
					b.location_code = bl.location_code and bl.link = 1 and bl.goods_seq = ?
					limit 1 ";

		$query = $this->db->query($query,$goods_seq);
		$result	= $query->result_array();

		return $result[0];
	}

	public function getChildLocation($code,$exactly=false,$division='catalog'){
		$arrWhere = array("level >= 2");

		if($division=='catalog' || $division=='searchForm'){
			$arrWhere[] = "hide_in_navigation != '1'";
		}elseif($division=='gnb'){
			$arrWhere[] = "hide_in_gnb != '1'";
		}else{
			$arrWhere[] = "hide != '1'";
		}

		$childLocationData = $this->get_list($code,$arrWhere);

		if(!$childLocationData && !$exactly /* && strlen($code)>4*/){
			$childLocationData = $this->get_list(substr($code,0,strlen($code)-4),$arrWhere);
		}

		$childLocationData = $this->design_set($childLocationData,$division);

		return $childLocationData;
	}

	public function getChildCategory($code){
		if($code){
			$sqlInner = "
				select b.category_code from fm_location_link as a
					inner join fm_category_link as b on (a.location_code=? and a.goods_seq = b.goods_seq and b.link=1)
					inner join fm_goods as c on (b.goods_seq=c.goods_seq and c.goods_view='look')

			";
			if(!empty($this->categoryData['list_goods_status'])) $sqlInner .= " where c.goods_status in ('".str_replace('|',"','",$this->categoryData['list_goods_status'])."')";
			$sql = "
				select * from (
					{$sqlInner}
					group by b.category_code
				) a
				inner join fm_category as b on a.category_code = b.category_code
			";
			$query = $this->db->query($sql,$code);
			$goodsCategories = $query->result_array();
		}else{
			$this->load->model('categorymodel');
			$goodsCategories = $this->categorymodel->getChildCategory('');
		}

		$goodsCategories = $this->design_set($goodsCategories,'searchForm');

		return $goodsCategories;
	}

	public function getChildBrand($code){
		if($code){
			$sqlInner = "
				select b.category_code from fm_location_link as a
					inner join fm_brand_link as b on (a.location_code=? and a.goods_seq = b.goods_seq and b.link=1)
					inner join fm_goods as c on (b.goods_seq=c.goods_seq and c.goods_view='look')

			";
			if(!empty($this->categoryData['list_goods_status'])) $sqlInner .= " where c.goods_status in ('".str_replace('|',"','",$this->categoryData['list_goods_status'])."')";
			$sql = "
				select * from (
					{$sqlInner}
					group by b.category_code
				) a
				inner join fm_brand as b on a.category_code = b.category_code
			";
			$query = $this->db->query($sql,$code);
			$goodsBrands = $query->result_array();
		}else{
			$this->load->model('brandmodel');
			$goodsBrands = $this->brandmodel->getChildBrand('');
		}

		$goodsBrands = $this->design_set($goodsBrands,'searchForm');

		return $goodsBrands;
	}

	public function getDepthForNavi($depth=1, $target_code=null){
		$isWhere		= array();
		$params[]		= $depth + 1;
		if($target_code){
			$isWhere[]	= "location_code LIKE ?";
			$params[]	= $target_code.'%';
		}
		$query = "SELECT location_code, title FROM fm_location WHERE level=? ";
		if(count($isWhere)>0) $query .= " AND " . implode(" AND ",$isWhere);
		$query			.=  " ORDER BY `position` ASC, `left` ASC ";
		$query			= $this->db->query($query, $params);
		foreach ($query->result_array() as $row){
			$category_list[$row['location_code']] = $row;
		}

		return $category_list;
	}

	public function getTitle($sCategory){
		$sQuery = "SELECT title FROM fm_location WHERE location_code = ?";
		$rQuery = $this->db->query($sQuery, array($sCategory));
		foreach ($rQuery->result_array() as $row){
			return $row['title'];
		}
	}

	public function getCodeInfo($location_code, $fields) {
		$result = array();
		if(empty($location_code)) return;

		$fields = $fields == "" ? "*" : $fields;

		$this->db->select($fields);
		$this->db->where_in('location_code', $location_code);
		$query = $this->db->get('fm_location');

		foreach ($query->result_array() as $row){
			$result[] = $row;
		}

		return $result;
	}

	public function get_location_design_display($location_code)
	{
		$query = $this->db->select('fm_design_display.*')
		->from('fm_location')
		->join('fm_design_display','fm_location.m_recommend_display_seq=fm_design_display.display_seq')->
		where(array('fm_location.location_code' => $location_code))
		->get();
		return $query;
	}
}

/* End of file location.php */
/* Location: ./app/models/location */
