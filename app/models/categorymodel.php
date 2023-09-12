<?php
class categorymodel extends CI_Model {

	/* 카테고리 목록 반환  */
	public function get_all($arrWhere=array()) {
		if($this->userInfo['member_seq']){
			$sql = "SELECT *,
				if(b.member_category_seq is not null,if(b.ctype='category',1,0),0) as favorite
				from `fm_category` a
				left join fm_member_category b on a.category_code=b.ccode and b.member_seq='".$this->userInfo['member_seq']."'
			";
		}else{
			$sql = "SELECT * FROM `fm_category` ";
		}

		if($arrWhere){
			$sql .= " where " . implode(" and ",$arrWhere);
		}

		$sql .=" ORDER BY `level` ASC, `position` ASC";

		$query = $this->db->query($sql);
		foreach ($query->result_array() as $row){
			$returnArr[] = $row;
		}
		//$query->free_result();
		return $returnArr;
	}

	// 카테고리 해당 depth 목록 반환 :: 2018-11-06 lwh
	public function get_category_depth_list($depth=1, $target_code=null,$limit=null){
		$isWhere		= array();
		$params[]		= $depth + 1;
		if($target_code){
			$isWhere[]	= "category_code LIKE ?";
			$params[]	= $target_code.'%';
		}
		$isWhere[]	= "(hide != '1' OR hide IS NULL)";
		$query = "SELECT * FROM fm_category WHERE level=? ";
		if(count($isWhere)>0) $query .= " AND " . implode(" AND ",$isWhere);
		$query			.=  " ORDER BY `position` ASC, `left` ASC ";
		if($limit) $query	.=  " LIMIT ".$limit;
		$query			= $this->db->query($query, $params);
		foreach ($query->result_array() as $row){
			$category_list[$row['category_code']] = $row;
		}

		return $category_list;
	}

	/* 카테고리 목록 Depth 구분하여 반환 (프론트 출력용도) */
	public function get_category_view($category_codes=array(),$maxDepth=4,$division='',$parent=null){

		/* 카테고리 목록  */
		$params = array("`level` >= 2");
		if($division=='catalog' || $division=='searchForm'){
			$params[] = "(hide_in_navigation = '0' or hide_in_navigation is null)";
		}elseif($division=='gnb'){
			$params[] = "hide_in_gnb != '1'";
		}elseif($division=='admin'){
			$params[] = "type = 'folder'";
		}else{
			$params[] = "(hide != '1' OR hide IS NULL)";
		}
		if($category_codes)		$params[] = "category_code in ('".implode("','",$category_codes)."')";
		if($parent)				$params[] = "category_code like '".$parent."%'";
		if($maxDepth<4)			$params[] = "`level` <= '".($maxDepth+1)."'";
		$category_list = $this->get_all($params);

		/* 텍스트,이미지 효과 */
		if(is_array($category_list)) $category_list = $this->design_set($category_list,$division);

		/* Depth별로 나눔 */
		$category = array();
		$category = divisionCategoryDepths($category_list,$category);

		return $category;
	}

	public function get_list($code,$arrWhere=array(),$division=null) {
		if( $code ){
			$level = strlen($code) / 4 + 2;
			$where[] = "a.`category_code` like ?";
			$whereVal[] = $code."%";
			$where[] = "a.`level` = ?";
			$whereVal[] = $level;
		}else{
			$where[] = "a.`level` = ?";
			$whereVal[] = 2;
		}

		if ($division == null) {
			$where[] = "`hide` != '1'";
		}

		$whereStr = implode(' and ',$where);
		$groupbyStr = '';

		if($arrWhere){
			$whereStr .= " and " . implode(" and ",$arrWhere);
		}

		if($this->userInfo['member_seq']){
			$query = "
				select a.* from `fm_category` a
				left join fm_member_category b on (a.category_code=b.ccode and b.member_seq='".$this->userInfo['member_seq']."')
			";
		}else{
			$query = "select a.* from `fm_category` a ";
		}

		if($division=='searchForm' && in_array(uri_string(),array('mshop','mshop/index'))){
			$provider_seq = preg_replace("/[^0-9]/","",$_GET['m']);
			if($provider_seq){
				$query .= "
					inner join fm_category_link c on a.category_code=c.category_code
					inner join fm_goods d on (c.goods_seq=d.goods_seq and d.provider_seq=?)
				";
				$groupbyStr = " group by a.category_code ";
				$whereVal = array_merge(array($provider_seq),$whereVal);
			}
		}

		$query .= "where {$whereStr} {$groupbyStr} order by a.`position` asc, a.`left` asc";
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
			$where[] = "`category_code` like ?";
			$whereVal[] = $code."%";

			// 2차 하위 카테고리 조회 오류로 수정 leewh 2015-06-05
			//$where[] = "`level` = ?";
			//$whereVal[] = $level;
			$categoryData = $this->get_category_data($code);
			$where[] = "`parent_id` = ?";
			$whereVal[] = $categoryData["id"];
		}else{
			$where[] = "`level` = ?";
			$whereVal[] = 2;
		}

		$whereStr = implode(' and ',$where);

		if($arrWhere){
			$whereStr .= " and " . implode(" and ",$arrWhere);
		}

		$query = "select `id`,`title`,`category_code` from `fm_category` where $whereStr order by `position` asc, `left` asc";
		$query = $this->db->query($query,$whereVal);
		$result = array();
		foreach ($query->result_array() as $row){
			$result[] = $row;
		}
		return $result;
	}

	public function get_category_data($code=''){
		if($code){
			$this->db->where('category_code',$code);
			$query = $this->db->get('fm_category');
			$categoryData = $query->row_array();
		}else{
			$this->db->where('level','2');
			$this->db->order_by('position asc');
			$this->db->limit(1);
			$query = $this->db->get('fm_category');
			$categoryData = $query->row_array();
			unset($categoryData['category_code']);
			unset($categoryData['title']);
		}

		// 카테고리 명칭 이스케이프 추가 :: 2019-04-09 pjw
		$categoryData['title'] = addslashes($categoryData['title']);

		if($categoryData['list_info_settings']){
			$categoryData['list_info_settings'] = str_replace("\"{","{",$categoryData['list_info_settings']);
			$categoryData['list_info_settings'] = str_replace("}\"","}",$categoryData['list_info_settings']);
		}
		if($categoryData['m_list_info_settings']){
			$categoryData['m_list_info_settings'] = str_replace("\"{","{",$categoryData['m_list_info_settings']);
			$categoryData['m_list_info_settings'] = str_replace("}\"","}",$categoryData['m_list_info_settings']);
		}

		return $categoryData;
	}

	public function get_category_name($code) {
		$result = false;
		for($i=4;$i<=strlen($code);$i+=4){
			$codecd = substr($code,0,$i);
			$sql = "SELECT `title`,`category_goods_code` FROM `fm_category` where `category_code`='$codecd'";
			$query = $this->db->query($sql);
			foreach ($query->result_array() as $row){
				$arr[] = ($row['category_goods_code'])?$row['title'].' ('.$row['category_goods_code'].')':$row['title'];
			}
		}
		if($arr) $result = implode(" > ",$arr);
		//$query->free_result();
		return $result;
	}

	public function get_category_goods_code($code,$type='view') {
		$result = false;
		for($i=4;$i<=strlen($code);$i+=4){
			$codecd = substr($code,0,$i);
			if($type == 'modify' && strlen($code) == $i ) break;
			$sql = "SELECT `category_goods_code` FROM `fm_category` where `category_code`='$codecd'";
			$query = $this->db->query($sql);
			foreach ($query->result_array() as $row){
				$arr[] = $row['category_goods_code'];
			}


		}
		if($arr) $result = implode(" > ",$arr);
		//$query->free_result();
		return $result;
	}

	public function get_category_name_href($code) {
		$cache_item_id = sprintf('category_name_href_%s', $code);
		$result = cache_load($cache_item_id);
		if ($result === false) {
			for($i=4;$i<=strlen($code);$i+=4){
				$codecd = substr($code,0,$i);
				$sql = "SELECT `title` FROM `fm_category` where `category_code`='$codecd'";
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

	public function one_category_name($code) {
		$sql = "SELECT `title` FROM `fm_category` where `category_code`='$code'";
		$query = $this->db->query($sql);
		list($row) = $query->result_array();
		return $row['title'];
	}

	public function get_category_code($id) {
		$sql = "SELECT `category_code` FROM `fm_category` where `id`='$id'";
		$query = $this->db->query($sql);
		list($row) = $query->result_array();
		return $row['category_code'];
	}

	// 카테고리 코드를 차수 별로 나눈다
	public function split_category($code){
		for($i=4;$i<=strlen($code);$i+=4){
			$category[] = substr($code,0,$i);
		}
		return $category;
	}

	public function get_next_positon($parent_id){
		$query = "".
			"select max(`position`) max from `fm_category` ".
			"where `parent_id` = ?";

		$query = $this->db->query($query,array($parent_id));
		list($tmp) = $query->result_array();

		if($tmp['max']){
			$tmp['max'] += 1;
		}
		return $tmp['max'];
	}


	public function get_next_category(){
		$qry = "select max(substring(category_code,1,4))+1 as category_code from fm_category";
		$query = $this->db->query($qry);
		list($cate) = $query->result_array();
		$category = sprintf("%04d",$cate['category_code']);
		return $category;
	}


	public function get_next_left(){
		$qry = "select max(`right`)+1 as max_left from fm_category;";
		$query = $this->db->query($qry);
		list($cate) = $query->result_array();
		return $cate['max_left'];
	}

	public function get_category_groups($category){
		$cache_item_id = sprintf('category_groups_%s', $category);
		$result = cache_load($cache_item_id);
		if ($result === false) {
			$qry = "select * from fm_category_group where category_code=?;";
			$query = $this->db->query($qry,$category);
			$result = $query->result_array();

			//
			if (! is_cli()) {
				cache_save($cache_item_id, $result);
			}
		}
		return $result;
	}

	// 카테고리 그룹별 카테고리 목록 반환
	public function get_category_group_for_member($category=null){
		$this->db->select('cg.*, mg.group_name');
		$this->db->from('fm_category_group  AS cg');
		$this->db->join('fm_member_group AS mg', 'cg.group_seq = mg.group_seq', 'left');
		if(is_array($category)===true && count($category)>0) {
			$this->db->where_in('cg.category_code', $category);
		}else if($category){
			$this->db->like('cg.category_code', $category, 'after');
		}
		$query = $this->db->get();
		return $query->result_array();
	}

	public function get_category_group_for_goods($goods_seq,$type='basic')
	{
		if($type == 'all'){
			$query = "select group_seq, user_type from fm_category_group where
				category_code = (select category_code from fm_category_link where goods_seq=? and link=1 order by length(category_code) desc limit 1  )";
			$query = $this->db->query($query,$goods_seq);

			foreach($query->result_array() as $data){
				if($data['group_seq'])	$result['user_group'][]		= $data['group_seq'];
				if($data['user_type'])	$result['user_type'][]		= $data['user_type'];
			}
		}else{
			$query = "select group_seq from fm_category_group where
				category_code = (select category_code from fm_category_link where goods_seq=? and link=1 order by length(category_code) desc limit 1  )";
			$query = $this->db->query($query,$goods_seq);
			foreach($query->result_array() as $data){
				$result[] = $data['group_seq'];
			}
		}
		return $result;
	}

	// 추천상품 디스플레이 seq 가져오기 (seq 가 없는경우 새로생성 후 리턴)
	// 미리 생성할 경우 페이지관리에서 존재하는것으로 인식하므로 불필요할땐 조회용으로만 사용하게 수정 :: 2019-01-25 pjw
	public function get_category_recommend_display_seq($categoryCode, $make = true){
		if(empty($categoryCode)) return null;
		$query			= $this->db->query("select * from fm_category where `level`>0 and ifnull(category_code,'')=?",$categoryCode);
		$categoryData	= $query->row_array();

		$query			= $this->db->query("select * from fm_design_display where display_seq = ?",$categoryData['recommend_display_seq']);
		$displayData	= $query->row_array();

		if($categoryData['recommend_display_seq'] && $displayData){
			$query = "update fm_design_display set kind='category' where display_seq=?";
			$this->db->query($query,array($categoryData['recommend_display_seq']));

			$query = $this->db->query("select * from fm_design_display where display_seq = ?",$categoryData['m_recommend_display_seq']);
			$m_displayData = $query->row_array();

			if($categoryData['m_recommend_display_seq'] && $m_displayData){
				$query = "update fm_design_display set kind='category_mobile' where display_seq=?";
				$this->db->query($query,array($categoryData['m_recommend_display_seq']));
				$m_recommend_display_seq = $categoryData['m_recommend_display_seq'];
			}else{
				$data = array(
					'kind' => 'category_mobile',
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

				$query = "update fm_category set m_recommend_display_seq=? where category_code=?";
				$this->db->query($query,array($m_recommend_display_seq,$categoryCode));
			}

			// [반응형스킨] responsive 반응형 추가 :: 2018-11-30 pjw
			$query = $this->db->query("select * from fm_design_display where display_seq = ?",$categoryData['recommend_display_light_seq']);
			$r_displayData = $query->row_array();
			if($categoryData['recommend_display_light_seq'] && $r_displayData ){
				$query = "update fm_design_display set kind='category' where display_seq=?";
				$this->db->query($query,array($categoryData['recommend_display_light_seq']));
				$recommend_display_light_seq = $categoryData['recommend_display_light_seq'];
			}else{
				$data = array(
					'kind' => 'category',
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

				$query = "update fm_category set recommend_display_light_seq=? where category_code=?";
				$this->db->query($query,array($recommend_display_light_seq,$categoryCode));
			}

			return array('recommend_display_seq'=>$categoryData['recommend_display_seq'],'m_recommend_display_seq'=>$m_recommend_display_seq, 'recommend_display_light_seq'=>$categoryData['recommend_display_light_seq']);

		}else if($make){// 데이터가 없을 경우 기존엔 insert 하였지만 필요에 따라 넘길수도 있음

			###### PC ######
			$data = array(
				'kind'		=> 'category',
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
				'kind'		=> 'category_mobile',
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
				'kind'		=> 'category',
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

			$query = "update fm_category set recommend_display_seq=?,m_recommend_display_seq=?,recommend_display_light_seq=? where category_code=?";
			$this->db->query($query,array($recommend_display_seq,$m_recommend_display_seq,$recommend_display_light_seq,$categoryCode));

			return array('recommend_display_seq'=>$recommend_display_seq,'m_recommend_display_seq'=>$m_recommend_display_seq, 'recommend_display_light_seq'=>$recommend_display_light_seq);
		}else{
			return null;
		}
	}

	public function set_category_recommend($categoryCode,$params){
		$this->load->model('goodsdisplay');
		if(!isset($params['m_list_use'])) $params['m_list_use'] = 'n';
		$m_params = $params;

		$recommend_display_seq_arr = $this->get_category_recommend_display_seq($categoryCode);
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
			$tab_data['auto_use'] = $params['contents_type'][0] == 'auto' || $params['contents_type'][0] == 'auto_sub' ? 'y' : 'n';
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

	public function childset_category($div=null,$category_code=''){

		$query = $this->db->query("select * from fm_category where `level`>0 and ifnull(category_code,'')=?",$category_code);
		$categoryData = $query->row_array();

		switch($div){
			case "top_html":
				$this->db->query("update fm_category set top_html = ?, update_date=now() where category_code like '{$category_code}%' and category_code!='{$category_code}'",$categoryData['top_html']);
			break;
			case "recommend":
				$this->recommend_childset($category_code,$categoryData['recommend_display_seq'],'pc');
				$this->recommend_childset($category_code,$categoryData['m_recommend_display_seq'],'mobile');
			break;
			case "category":
				$data = array(
					'list_default_sort'			=> $categoryData['list_default_sort'],
					'list_style'				=> $categoryData['list_style'],
					'list_count_w'				=> $categoryData['list_count_w'],
					'list_count_w_lattice_b'	=> $categoryData['list_count_w_lattice_b'],
					'list_count_h'				=> $categoryData['list_count_h'],
					'list_paging_use'			=> $categoryData['list_paging_use'],
					'list_image_size'			=> $categoryData['list_image_size'],
					'list_text_align'			=> $categoryData['list_text_align'],
					'list_image_decorations'	=> $categoryData['list_image_decorations'],
					'list_info_settings'		=> $categoryData['list_info_settings'],
					'list_goods_status'			=> $categoryData['list_goods_status'],
					'm_list_use'				=> $categoryData['m_list_use'],
					'm_list_default_sort'		=> $categoryData['m_list_default_sort'],
					'm_list_style'				=> $categoryData['m_list_style'],
					'm_list_count_w'			=> $categoryData['m_list_count_w'],
					'm_list_count_h'			=> $categoryData['m_list_count_h'],
					'm_list_count_r'			=> $categoryData['m_list_count_r'],
					'm_list_paging_use'			=> $categoryData['m_list_paging_use'],
					'm_list_mobile_h'			=> $categoryData['m_list_mobile_h'],
					'm_list_image_size'			=> $categoryData['m_list_image_size'],
					'm_list_text_align'			=> $categoryData['m_list_text_align'],
					'm_list_image_decorations'	=> $categoryData['m_list_image_decorations'],
					'm_list_info_settings'		=> $categoryData['m_list_info_settings'],
					'm_list_goods_status'		=> $categoryData['m_list_goods_status'],
					'list_count_h_lattice_b'	=> $categoryData['list_count_h_lattice_b'],
					'list_count_h_list'			=> $categoryData['list_count_h_list'],
					'list_image_size_lattice_b'	=> $categoryData['list_image_size_lattice_b'],
					'list_image_size_list'		=> $categoryData['list_image_size_list'],
					'img_opt_lattice_a'			=> $categoryData['img_opt_lattice_a'],
					'img_padding_lattice_a'		=> $categoryData['img_padding_lattice_a'],
					'image_decoration_type'		=> $categoryData['image_decoration_type'],
					'image_decoration_favorite_key'		=> $categoryData['image_decoration_favorite_key'],
					'image_decoration_favorite'			=> $categoryData['image_decoration_favorite'],
					'goods_decoration_type'				=> $categoryData['goods_decoration_type'],
					'goods_decoration_favorite_key'		=> $categoryData['goods_decoration_favorite_key'],
					'goods_decoration_favorite'			=> $categoryData['goods_decoration_favorite'],
					'm_image_decoration_type'			=> $categoryData['m_image_decoration_type'],
					'm_image_decoration_favorite_key'	=> $categoryData['m_image_decoration_favorite_key'],
					'm_image_decoration_favorite'		=> $categoryData['m_image_decoration_favorite'],
					'm_goods_decoration_type'			=> $categoryData['m_goods_decoration_type'],
					'm_goods_decoration_favorite_key'	=> $categoryData['m_goods_decoration_favorite_key'],
					'm_goods_decoration_favorite'		=> $categoryData['m_goods_decoration_favorite'],
					'update_date'				=> date('Y-m-d H:i:s'),
				);
				$this->db->update('fm_category', $data, "category_code like '{$category_code}%' and length(category_code)>".strlen($category_code));
			break;
		}

	}

	public function recommend_childset($category_code,$recommend_display_seq,$type){
		$this->load->model('goodsdisplay');

		$recommend_display_seq_var = 'recommend_display_seq';
		if($type == 'mobile') $recommend_display_seq_var = 'm_recommend_display_seq';

		$query = $this->db->query("select * from fm_design_display where display_seq = ?",$recommend_display_seq);
		$recommend_display_data = $query->row_array();

		$query = $this->db->query("select * from fm_category where category_code like '{$category_code}%' and length(category_code)>".strlen($category_code));
		foreach($query->result_array() as $childCategoryData){

			if($childCategoryData[$recommend_display_seq_var]==$recommend_display_seq) continue;

			/* 하위카테고리에 설정된 상품디스플레이 제거*/
			$this->db->query("delete from fm_design_display where display_seq=?",$childCategoryData[$recommend_display_seq_var]);
			$this->db->query("delete from fm_design_display_tab where display_seq=?",$childCategoryData[$recommend_display_seq_var]);
			$this->db->query("delete from fm_design_display_tab_item where display_seq=?",$childCategoryData[$recommend_display_seq_var]);

			/* 사용시 하위복사 */
			if($recommend_display_seq){
				$child_recommend_display_seq = $this->goodsdisplay->copy_display($recommend_display_seq);
				if($child_recommend_display_seq){
					$this->db->query("update fm_category set {$recommend_display_seq_var}='{$child_recommend_display_seq}', update_date=now() where category_code = '{$childCategoryData['category_code']}'");
				}

			/* 미사용시 하위제거 */
			}else{
				if($childCategoryData[$recommend_display_seq_var]){
					$this->db->query("update fm_category set {$recommend_display_seq_var}='', update_date=now() where category_code = '{$childCategoryData['category_code']}'");
				}
			}

		}
	}

	public function chkDupleSort($categoryCode){
		$query			= $this->db->query("select count(category_link_seq) as cnt from fm_category_link where category_code = ? group by sort having cnt > 1 limit 1", $categoryCode);
		$chkData		= $query->row_array();
		if	($chkData)	return true;
		else			return false;
	}

	public function reSortAll($categoryCode){

		$query = $this->db->query("select l.* from fm_goods_option o, fm_goods g, fm_category_link l where g.goods_view='look' AND g.goods_type = 'goods' and o.goods_seq=g.goods_seq and l.goods_seq = g.goods_seq and o.default_option ='y' and l.category_code = ? group by g.goods_seq order by l.sort asc, g.regist_date desc", $categoryCode);
		$sort	= 0;
		foreach($query->result_array() as $linkData){
			if	($linkData['category_link_seq']){
				$this->db->query("update fm_category_link set sort = ? where category_link_seq = ? ", array($sort, $linkData['category_link_seq']));
				$sort++;
			}
		}

		config_save('mig_sort_category',array($categoryCode=>'Y'));
	}

	/**
	* app/javascript/plugin/jstree/_tree/_inc/class.tree.php 에도 동일 코드 사용
	* 수정시 반드시 위 링크에서도 동일하게 수정할 것
	**/
	public function getSortValue($categoryCode, $type){
		switch($type){
			case 'min':
				$query 	= "SELECT MIN(sort) sortVal FROM `fm_category_link` WHERE category_code =  ?";
			break;
			case 'max':
				$query		= "SELECT MAX(sort) sortVal FROM `fm_category_link` WHERE category_code = ?";
			break;
			case 'mobile_min':
				$query		= "SELECT MIN(mobile_sort) sortVal FROM `fm_category_link` WHERE category_code = ?";
			break;
			case 'mobile_max':
				$query		= "SELECT MAX(mobile_sort) sortVal FROM `fm_category_link` WHERE category_code = ?";
			break;
		}

		$query		= $this->db->query($query, $categoryCode);
		$sortData	= $query->row_array();

		return $sortData['sortVal'];
	}

	public function rangeUpdateSort($categoryCode, $sSort, $eSort, $addSort){
		if	(!is_null($sSort))
			$addWhere	.= " and sort > ".$sSort." ";
		if	(!is_null($eSort))
			$addWhere	.= " and sort < ".$eSort." ";
		$this->db->query("update fm_category_link set sort=IF((IFNULL(sort,0)".$addSort.")<0,0,IFNULL(sort,0)".$addSort.") where category_code = ? ".$addWhere, $categoryCode);
	}

	public function chgCategorySort($category_link_seq, $sort){
		if	($category_link_seq){
			$this->db->query("update fm_category_link set sort=? where category_link_seq = ? ", array($sort, $category_link_seq));
		}
	}

	public function design_set($childCategoryData,$division='catalog'){
		// 운영방식 추가 :: 2018-11-29 pjw
		$operation_type = $this->config_system['operation_type'];
		if($division=='searchForm') $division = 'catalog';

		$division = $division ? '_'.$division.'_' : '_';

		foreach($childCategoryData as $k=>$row){
			$childCategoryData[$k]['title'] = htmlspecialchars($childCategoryData[$k]['title']);
			$childCategoryData[$k]['ori_title'] = $childCategoryData[$k]['title'];

			// 운영방식이 light 형이 아닌 경우에만 기존 스타일 적용 :: 2018-11-29 pjw
			if($operation_type != 'light'){
				if($childCategoryData[$k]['node'.$division.'type']=='text'){
					if($childCategoryData[$k]['node'.$division.'text_normal']){
						$attrStyle = font_decoration_attr($childCategoryData[$k]['node'.$division.'text_normal'],'css','style');
						$attrOnmouseover = font_decoration_attr($childCategoryData[$k]['node'.$division.'text_over'],'script','onmouseover');
						$attrOnmouseout = font_decoration_attr($childCategoryData[$k]['node'.$division.'text_normal'],'script','onmouseout');

						$childCategoryData[$k]['title'] = "<span {$attrStyle} {$attrOnmouseover} {$attrOnmouseout}>{$childCategoryData[$k]['title']}</span>";
					}
				}

				if($childCategoryData[$k]['node'.$division.'type']=='image'){
					$attrSrc ='';
					$attrOnmouseover ='';
					$attrOnmouseout ='';
					if($childCategoryData[$k]['node'.$division.'image_normal']){
						$attrSrc = 'src="'.$childCategoryData[$k]['node'.$division.'image_normal'].'"';
					}
					if($childCategoryData[$k]['node'.$division.'image_over']){
						$attrOnmouseover = 'onmouseover="this.src=\''.$childCategoryData[$k]['node'.$division.'image_over'].'\'"';
						$attrOnmouseout = 'onmouseout="this.src=\''.$childCategoryData[$k]['node'.$division.'image_normal'].'\'"';
					}

					$childCategoryData[$k]['title'] = "<img {$attrSrc} {$attrOnmouseover} {$attrOnmouseout} />";
				}
			}

			$childCategoryData[$k]['name'] = $childCategoryData[$k]['title'];

		}
		return $childCategoryData;
	}

	public function get_represent_category_for_goods($goods_seq){

		$query = "select c.* from fm_category as c, fm_category_link as cl where
					c.category_code = cl.category_code and cl.link = 1 and cl.goods_seq = ?
					limit 1 ";
		$query = $this->db->query($query,$goods_seq);
		$result	= $query->result_array();

		return $result[0];
	}

	public function getChildCategory($code,$exactly=false,$division='catalog'){
		$arrWhere = array("`level` >= 2");

		if($division=='catalog' || $division=='searchForm'){
			$arrWhere[] = "(hide_in_navigation = '0' or hide_in_navigation is null)";
		}elseif($division=='gnb'){
			$arrWhere[] = "hide_in_gnb != '1'";
		}else{
			$arrWhere[] = "hide != '1'";
		}

		switch(uri_string()){
			case "mshop":
			case "mshop/index":
				$arrWhere[] = "hide != '1'";
			break;
		}

		$childCategoryData = $this->get_list($code,$arrWhere,$division);

		if($division=='searchForm'){
			if(!$childCategoryData && !$exactly /* && strlen($code)>4*/){
				$childCategoryData = $this->get_list(substr($code,0,strlen($code)-4),$arrWhere,$division);
			}
		}else{
			if(!$childCategoryData && !$exactly && strlen($code)>4){
				$childCategoryData = $this->get_list(substr($code,0,strlen($code)-4),$arrWhere,$division);
			}
		}

		$childCategoryData = $this->design_set($childCategoryData,$division);

		return $childCategoryData;
	}

	public function getChildBrand($code,$division=null){
		if($code){
			$sql = "select br.* from fm_category_brand cabr inner join fm_brand br on cabr.brand_code=br.category_code where cabr.category_code=?";
			$query = $this->db->query($sql,$code);
			$goodsBrands = $query->result_array();
		}else{
			$this->load->model('brandmodel');
			$goodsBrands = $this->brandmodel->getChildBrand('',false,$division);
		}
		return $goodsBrands;
	}

	public function get_default_category()
	{
		return $this->db->query("select category_code from fm_category where level>1 order by category_code asc limit 1");
	}

	public function get_goods_categories($goods_seq){
		$this->db->select('category_code');
		$this->db->from('fm_category_link');
		if( is_array($goods_seq) ){
			$this->db->where_in('goods_seq', $goods_seq);
		}else{
			$this->db->where('goods_seq', $goods_seq);
		}
		return $this->db->get();
	}

	public function getCategoryDepthForNavi($depth=1, $target_code=null){
		$isWhere		= array();
		$params[]		= $depth + 1;
		if($target_code){
			$isWhere[]	= "category_code LIKE ?";
			$params[]	= $target_code.'%';
		}
		//#30963 2019-03-22 ycg 카테고리 미표시 설정 시 네비게이션에 안보이도록 처리
		$isWhere[]	= "hide != '1'";
		$query = "SELECT category_code, title FROM fm_category WHERE level=? ";
		if(count($isWhere)>0) $query .= " AND " . implode(" AND ",$isWhere);
		$query			.=  " ORDER BY `position` ASC, `left` ASC ";
		$query			= $this->db->query($query, $params);
		foreach ($query->result_array() as $row){
			$category_list[$row['category_code']] = $row;
		}

		return $category_list;
	}

	public function getCodeInfo($category_code, $fields) {
		$result = array();
		if(empty($category_code)) return;

		$fields = $fields == "" ? "*" : $fields;

		$this->db->select($fields);
		$this->db->where_in('category_code', $category_code);
		$query = $this->db->get('fm_category');

		foreach ($query->result_array() as $row){
			$result[] = $row;
		}

		return $result;
	}

	public function category_plan_main()
	{
		return $this->db->select('*')->from('fm_category')->where(array('plan'=>'y', 'level'=>'3'))->order_by('category_code', 'asc')->get();
	}

}

/* End of file category.php */
/* Location: ./app/models/category */
