<?php

/* 상품디스플레이 출력*/
function showLocationRecommendDisplay($location_code)
{
	$CI =& get_instance();

	// light 버전 추가
	$operation_type = $CI->config_system['operation_type'];

	if($operation_type == 'light'){
		_lightShowLocationRecommendDisplay($location_code);
	}else{
		_heavyShowLocationRecommendDisplay($location_code);
	}

	return;
}

function _lightShowLocationRecommendDisplay($location_code){
	$CI =& get_instance();

	$CI->designDisplayTabAjaxIdx = empty($CI->designDisplayTabAjaxIdx) ? 0 : $CI->designDisplayTabAjaxIdx;

	// 관리자, 회원 로그인시 캐시 예외 적용
	$cache_flag = true;
	if ($CI->userInfo['member_seq'] || $CI->managerInfo) {
		$cache_flag = false;
	}

	//
	$cache_item_id = sprintf('location_recommend_html_%s_%s', $location_code, $CI->designDisplayTabAjaxIdx);
	$html = cache_load($cache_item_id);
	if ($html === false || ! $cache_flag) {

		$CI->load->model('locationmodel');
		$CI->load->model('goodsdisplay');
		$CI->load->model('goodsmodel');
		$CI->load->model('boardformmodel');


		$template_path		= $CI->__tmp_template_path ? $CI->__tmp_template_path : $CI->template_path;
		$display_key		= $CI->goodsdisplay->make_display_key();
		$display_seq_arr	= $CI->locationmodel->get_location_recommend_display_seq($location_code);
		$display_seq		= $display_seq_arr['recommend_display_light_seq'];
		$display_data		= $CI->goodsdisplay->get_display($display_seq,true);
		$display_tabs		= $CI->goodsdisplay->get_display_tab($display_seq);
		$ajax_call			= $CI->designDisplayTabAjaxIdx > 0 ? true : false;
		$limit				= $display_data['count_r'] ? $display_data['count_r'] : 4;

		if($display_seq){

			// 반응형일 경우 탭은 1개일 수 밖에 없으므로 나머지 데이터는 없앰
			if($display_data['style'] == 'sizeswipe') $display_tabs = array($display_tabs[0]);

			############ 상품 평가정보 ############
			// 제일 첫 행의 제일 첫번째 키를 기준으로 가져온다.

			$query = $CI->boardformmodel->get_first_goods_review();
			$board_adddata = $query->row_array();
			$review_toprate_key = explode('|', $board_adddata['label_value']);
			$review_toprate_key = $review_toprate_key[0];


			############ 이미지 꾸미기 데이터 ############
			// 꾸미기 데이터 디코딩
			$display_data['decorations'] = json_decode(base64_decode($display_data['image_decorations']) , true);

			// 모바일인 경우 미리보기와 세컨드 이미지 설정은 사용하지않게끔 처리
			if($CI->mobileMode && $CI->_is_mobile_agent){
				unset($display_data['decorations']['use_seconde_image']);
				unset($display_data['decorations']['use_review_option_like']);
			}

			// 아이콘 커스텀 꾸미기 디코딩
			$tmp_icon_condition		= json_decode(base64_decode($display_data['decorations']['image_icon_condition']), true);
			$image_icon_condition	= array();
			foreach($tmp_icon_condition as $k=>$condition){
				// 사용중인 조건만 가져오기
				if($condition['use']){
					if(!empty($condition['background'])){
						$condition['background'] = json_decode(base64_decode($condition['background']));
					}
					$image_icon_condition[$condition['key']] = $condition;
				}
			}
			$display_data['decorations']['image_icon_condition'] = $image_icon_condition;


			// 상품 정보 리스트 데이터 가공
			$tabList			= array();
			$tabRecords			= array();
			foreach($display_tabs as $tab_index => $display_tab){

				if($tab_index == $CI->designDisplayTabAjaxIdx){
					$sc						= array();

					// 상품 자동노출 조건 파싱
					if($display_tab['auto_use']=='y' && $display_tab['auto_condition_use'] != 1){
						$sc				= $CI->goodsdisplay->search_condition($display_tab['auto_criteria'], $sc, 'recommend');
						$sc['sort']		= $perpage && !empty($_GET['sort']) ? $_GET['sort'] : $sc['auto_order'];
					}else{
						$sc['sort']		= $perpage && !empty($_GET['sort']) ? $_GET['sort'] : 'display';
					}

					$sc['display_seq']		= $display_seq;
					$sc['display_tab_index']= $tab_index;
					$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):'1';
					$sc['perpage']			= $perpage ? $perpage : 1000;
					$sc['image_size']		= $display_data['image_size'];
					$sc['limit']			= $limit;

					if( $display_tab['auto_use']=='y' && $display_tab['auto_condition_use'] == 1 ){
						$sc['standard']			= 'category';
						$sc['category_code']	= $location_code;

						if	($display_tab['contents_type'] == 'auto_sub'){
							$sc['bigdata']				= 1;
							$sc['goods_seq_exclude']	= $CI->bigdataGoodsSeq;
						}

						$sc['sort'] = '';
						$sc			= $CI->goodsdisplay->auto_select_condition($display_tab['auto_criteria'], $sc, 'recommend');
						$list		= $CI->goodsmodel->auto_condition_goods_list($sc);
					}else{
						$list		= $CI->goodsmodel->goods_list($sc);
					}

					// 리스트 데이터가 없는 경우 바로 리턴
					if(empty($list['record']) && $display_tab['contents_type'] != 'text'){
						return false;
					}

					// 상품별 데이터 가공
					$tabRecords			 = $CI->goodsmodel->get_goodslist_display_light($list['record'], $display_data);
					$tabList[$tab_index] = $display_tab;

				}else if($CI->designDisplayTabAjaxIdx == 0){
					$tabList[$tab_index] = $display_tab;
				}
			}

			$CI->template->assign($display_data);
			$CI->template->assign('displayClass','designLocationRecommendDisplay');
			$CI->template->assign('displayElement', 'locationRecommendDisplay');
			$CI->template->assign('display_key',$display_key);
			$CI->template->assign('displayTabsList',$tabList);
			$CI->template->assign('goodsList',$tabRecords);
			$CI->template->assign('template_path',$template_path);
			$CI->template->assign('display_seq',$display_seq);
			$CI->template->assign('perpage',$perpage);
			$CI->template->assign('location_code',$location_code);
			$CI->template->assign('displayStyle',$display['style']);
			$CI->template->assign('ajax_call',$ajax_call);
			$CI->template->assign('isRecommend',true);
			$CI->template->assign('skin',$CI->skin);

			$CI->template->define('paging',		$CI->skin."/_modules/display/display_paging.html");
			$CI->template->define('goods_list', "../design/{$display_data['goods_decoration_favorite_key']}.html");
			$CI->template->define('tpl',		$CI->skin."/_modules/display/goods_display_{$display_data['style']}.html");

			$html = $CI->template->fetch("tpl");
		}else{
			$html = '상품 디스플레이 정보가 없습니다.';
		}

		//
		if ($cache_flag) {
			cache_save($cache_item_id, $html);
		}
	}
}

function _heavyShowLocationRecommendDisplay($location_code){
	$CI =& get_instance();
	$CI->load->helper('javascript');
	$CI->load->model('goodsdisplay');
	$CI->load->model('goodsmodel');
	$CI->load->model('locationmodel');
	$template_path = $CI->__tmp_template_path ? $CI->__tmp_template_path : $CI->template_path;

	// 디스플레이 임시 코드명
	$display_key = $CI->goodsdisplay->make_display_key();

	// 디스플레이 설정 데이터
	$query  = $CI->db->query("select d.* from fm_location as c, fm_design_display as d where c.recommend_display_seq=d.display_seq and c.location_code = ?",$location_code);
	$display = $query->row_array();
	$oriDisplay = $display;

	if($display){

		$display = $CI->goodsdisplay->set_display_default($display);

		if($CI->realMobileSkinVersion >  2 && $CI->mobileMode && $display['m_list_use'] == 'y'){
			$query  = $CI->db->query("select d.* from fm_location as c, fm_design_display as d where c.m_recommend_display_seq=d.display_seq and c.location_code = ?",$location_code);
			$display = $query->row_array();
			if($display['style'] == 'newswipe'){
				$display['count_w'] = $display['count_w_swipe'];
				$display['count_h'] = $display['count_h_swipe'];
				$limit = $display['count_max_swipe'];
			}else if($display['style'] == 'sizeswipe'){
				$limit = 20;
			}
		}else{
			if($CI->realMobileSkinVersion >  2 && $CI->mobileMode && $display['style'] == 'rolling_h') {
				$display['style'] = 'lattice_a';
				if($display['count_w']<=2) $display['count_w'] = 4;
			}
			if( $display['style'] == 'rolling_h') $limit = $display['count_w_rolling_h']*$display['count_h_rolling_h'];
			else $limit = $display['count_w']*$display['count_h'];
		}

		$limit = $limit ? $limit : 4;

        $img_optimize	=  $display['img_opt_'.$display['style']];
        $img_padding	=  $display['img_padding_'.$display['style']];

		$display_seq = $display['display_seq'];

		if($CI->designDisplayTabAjaxIdx){
			$display_tabs = $CI->goodsdisplay->get_display_tab($display_seq,$CI->designDisplayTabAjaxIdx);
			$display_tabs = array($CI->designDisplayTabAjaxIdx=>$display_tabs[0]);
		}else{
			$display_tabs = $CI->goodsdisplay->get_display_tab($display_seq);
		}

		foreach($display_tabs as $tab_index => $display_tab){
			if($display['tab_design_type']=='displayTabTypeImage' && $display_tab['tab_title_img']){
				$display_tabs[$tab_index]['tab_title'] = "<span class='displayTabItemImage'><img src='/data/icon/goodsdisplay/tabs/{$display_tab['tab_title_img']}' class='displayTabItemImageOff' title='{$display_tab['tab_title']}' /><img src='/data/icon/goodsdisplay/tabs/{$display_tab['tab_title_img_on']}' class='displayTabItemImageOn hide' /></span>";
			}
		}

		/**
		 * list setting
		**/
		$tabList = array();
		foreach($display_tabs as $tab_index => $display_tab){

			if($tab_index==0 || $CI->designDisplayTabAjaxIdx){

				$sc=array();

				// 상품 자동노출 조건 파싱
				if($display_tab['auto_use']=='y' && $display_tab['auto_condition_use'] != 1){

					$sc = $CI->goodsdisplay->search_condition($display_tab['auto_criteria'], $sc, 'recommend');

					$sc['sort']		= $perpage && !empty($_GET['sort']) ? $_GET['sort'] : $sc['auto_order'];
					$sc['location'] = $location_code;
				}else{
					$sc['sort']		= $perpage && !empty($_GET['sort']) ? $_GET['sort'] : 'display';
				}

				$sc['display_seq']		= $display_seq;
				$sc['display_tab_index']= $tab_index;
				$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):'1';
				$sc['perpage']			= $perpage ? $perpage : 1000;
				$sc['image_size']		= $display['image_size'];
				$sc['limit']			= $limit;

				if($CI->goodsdisplay->info_settings_have_eventprice($display['info_settings'])){
					$sc['join_event']	= true;
				}

				if( $display_tab['auto_use']=='y' && $display_tab['auto_condition_use'] == 1 ){
					if	($display_tab['contents_type'] == 'auto_sub'){
						$sc['bigdata'] = 1;
						$sc['goods_seq_exclude'] = $CI->bigdataGoodsSeq;
					}

					$sc['sort'] = '';

					$sc = $CI->goodsdisplay->auto_select_condition($display_tab['auto_criteria'], $sc, 'recommend');
					$list = $CI->goodsmodel->auto_condition_goods_list($sc);
				}else{
					$list = $CI->goodsmodel->goods_list($sc);
				}

				$tabList[$tab_index] = $display_tab;
				$tabList[$tab_index]['record'] = $list['record'];
			}else{
				$tabList[$tab_index] = $display_tab;
			}
		}

		$displayAllGoodsList = array();
		foreach($tabList as $row) $displayAllGoodsList = array_merge($displayAllGoodsList,$row['record']);

		if(!$CI->designDisplayTabAjaxIdx) echo "<div id='{$display_key}' class='designLocationRecommendDisplay' designElement='locationRecommendDisplay' templatePath='{$template_path}' displaySeq='{$display_seq}' perpage='{$perpage}' location='{$location_code}' displayStyle='{$display['style']}'>";
		$CI->goodsdisplay->set('img_optimize',$img_optimize);
		$CI->goodsdisplay->set('img_padding',$img_padding);
		$CI->goodsdisplay->set('title',$display['title']);
		$CI->goodsdisplay->set('style',$display['style']);
		$CI->goodsdisplay->set('count_w',$display['count_w']);
		$CI->goodsdisplay->set('count_h',$display['count_h']);
		$CI->goodsdisplay->set('h_rolling_type',$display['h_rolling_type']);
		$CI->goodsdisplay->set('image_decorations',$display['image_decorations']);
		$CI->goodsdisplay->set('image_size',$display['image_size']);
		$CI->goodsdisplay->set('text_align',$display['text_align']);
		$CI->goodsdisplay->set('info_settings',$display['info_settings']);
		$CI->goodsdisplay->set('display_key',$display_key);
		$CI->goodsdisplay->set('displayGoodsList',$displayAllGoodsList);
		$CI->goodsdisplay->set('displayTabsList',$tabList);
		$CI->goodsdisplay->set('tab_design_type',$display['tab_design_type']);
		$CI->goodsdisplay->set('platform',$display['platform']);
		$CI->goodsdisplay->set('navigation_paging_style',$display['navigation_paging_style']);
		$CI->goodsdisplay->set('isRecommend',true);
		$CI->goodsdisplay->print_();
		if(!$CI->designDisplayTabAjaxIdx) echo "</div>";
	}

	return;
}
?>
