<?php
/* 상품디스플레이 출력*/
function showDesignDisplay($display_seq, $perpage=null, $kind=null, $iscach=null, $display_ajax_call=null, $hash_paging=null){
    $CI =& get_instance();

	// light 버전 추가
	$operation_type = $CI->config_system['operation_type'];

	if($operation_type == 'light'){
		_lightShowDesignDisplay($display_seq, $perpage, $kind, $iscach, $display_ajax_call, $hash_paging);
	}else{
		_heavyShowDesignDisplay($display_seq, $perpage, $kind, $iscach, $display_ajax_call, $hash_paging);
	}

	return;
}

function _lightShowDesignDisplay($display_seq, $perpage=null, $kind=null, $iscach=null, $display_ajax_call=null, $hash_paging=null){
	########### 기본 모듈 로드 ###########
	$CI =& get_instance();
	$CI->designDisplayTabAjaxIdx	= empty($CI->designDisplayTabAjaxIdx) ? 0 : $CI->designDisplayTabAjaxIdx;

	// 관리자, 회원 로그인시 캐시 예외 적용
	$cache_flag = true;
	if ($CI->userInfo['member_seq'] || $CI->managerInfo) {
		$cache_flag = false;
	}

	//
	$cache_item_id = sprintf('design_goods_%s_tabs_%s_page_%s', $display_seq, $CI->designDisplayTabAjaxIdx, $CI->input->get_post('page'));
	$html = cache_load($cache_item_id);
	if ($html === false || ! $cache_flag) {

		$CI->load->model('categorymodel');
		$CI->load->model('goodsdisplay');
		$CI->load->model('goodsmodel');
		$CI->load->model('boardformmodel');

		########### 기본 변수 정의 ###########
		$remain							= '';
		$tab_count						= '';
		$tabList						= array();
		$displayAllGoodsList			= array();
		$aGetParams						= $CI->input->get();
		$aPostParams					= $CI->input->post();
		$sc_top							= $aGetParams['sc_top'];								//페이징이 있는 경우 해당 위치로 스크롤바를 옮겨준다
		$ajax_call						= ($display_ajax_call) ? true : false;					//상품디스플레이 ajax 호출시 item 만 가져 오도록
		$display_key					= $CI->goodsdisplay->make_display_key();				// 디스플레이 임시 코드명
		$display_data					= $CI->goodsdisplay->get_display($display_seq,true);	// 디스플레이 설정 데이터
		$cfg_system						= ($CI->config_system) ? $CI->config_system : config_load('system');
		$template_path					= $CI->__tmp_template_path ? $CI->__tmp_template_path : $CI->template_path;


		// 유저정보가 세션에 없고, 인자값으로 넘어온 경우 세션에 세팅처리
		if	($aGetParams['userInfo'] && !$CI->userInfo) $CI->userInfo = unserialize(base64_decode($aGetParams['userInfo']), ['allowed_classes' => false]);

		########### 페이징 변수 설정 ###########
		if($perpage){

			$perpage		= $aGetParams['perpage'] ? $aGetParams['perpage'] : $perpage;
			$perpage		= $perpage ? $perpage : 10;
			$perpage_min	= $display_data['count_r'];

		}else{
			// 쿼리에 넣을 limit 설정
			$limit = $display_data['count_r'];
			$limit = $limit ? $limit : 4;

			if($aPostParams['page']){
				$start = ($aPostParams['page']-1) * $limit;
				$limit = " {$start}, {$limit}";
			}

		}

		########### 디스플레이 설정 시작 ###########
		if($display_data){


			########### 탭 정보 읽기 ###########
			if($CI->designDisplayTabAjaxIdx){
				// ajax 호출인 경우 해당 탭의 정보만 가져옴
				$display_tabs = $CI->goodsdisplay->get_display_tab($display_seq, $CI->designDisplayTabAjaxIdx);
				$display_tabs = array($CI->designDisplayTabAjaxIdx=>$display_tabs[0]);
			}else{
				// ajax 이 아닌경우 첫번째 탭의 정보를 가져옴
				$CI->designDisplayTabAjaxIdx = 0;
				$display_tabs = $CI->goodsdisplay->get_display_tab($display_seq);
				$tabs_row = $display_tabs;
			}
			// 탭 개수 설정
			$tab_count = count($tabs_row);



			########### 캐시 설정 된 디스플레이 일 경우 ###########
			if($iscach){
				$display_tab = $display_tabs[$CI->designDisplayTabAjaxIdx];
				$aParams = array(
					'iTabIndex'     => $CI->designDisplayTabAjaxIdx,
					'display'       => $display_data,
					'display_tab'   => $display_tab,
					'perpage'       => $perpage,
					'aGetParams'    => $aGetParams,
					'kind'          => $kind,
					'tab_index'     => $CI->designDisplayTabAjaxIdx,
					'iscach'        => $iscach,
					'limit'         => $limit,
					'tabList'       => $tabList,
					'display_seq'   => $display_seq
				);

				$result = _designDisplayTab($aParams);
				if( !$result ) return false;
			}

			########### 디스플레이 탭 정보 가져옴 ###########
			foreach($display_tabs as $tab_index => $display_tab){
				$iTabIndex  = ($CI->designDisplayTabAjaxIdx) ? $CI->designDisplayTabAjaxIdx : $tab_index;
				if( $tab_index == 0 || $CI->designDisplayTabAjaxIdx ){
					$aParams = array(
						'iTabIndex'     => $iTabIndex,
						'display'       => $display_data,
						'display_tab'   => $display_tab,
						'perpage'       => $perpage,
						'aGetParams'    => $aGetParams,
						'kind'          => $kind,
						'tab_index'     => $tab_index,
						'iscach'        => $iscach,
						'limit'         => $limit,
						'tabList'       => $tabList,
						'display_seq'   => $display_seq
					);
					list($sort, $sc, $tabList, $list) = _designDisplayTab($aParams);
				}else{
					$tabList[$iTabIndex]    = $display_tab;
				}
			}

			############ 상품 평가정보 ############
			// 제일 첫 행의 제일 첫번째 키를 기준으로 가져온다.
			$query = $CI->boardformmodel->get_first_goods_review();
			$board_adddata		= $query->row_array();
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


			########### 디스플레이 탭 정보 가져옴 ###########
			$is_text = $tabList[$CI->designDisplayTabAjaxIdx]['contents_type'] == 'text' ? true : false;
			// 탭이 2개이상일때에는 노출되어야함 2018-04-23
			if($CI->designDisplayTabAjaxIdx && !$is_text && empty($tabList[$CI->designDisplayTabAjaxIdx]['record']) && !$CI->managerInfo && $tab_count == 1){
				return;
			}elseif( $is_text && empty($tabList[$CI->designDisplayTabAjaxIdx]['record']) && !$CI->managerInfo && $tab_count == 1 ){
				return;
			}

			// ajax로 호출 했을때 데이터가 없을 경우
			if($ajax_call && count($list['record']) < 1 && !$is_text){
				$html = '표시할 상품이 없습니다.';
			}

			// 페이징 설정
			if($perpage){
				$tmpGET             = $aGetParams;
				if(!$kind)	$kind	= ($CI->mobileMode || $CI->storemobileMode) ? 'style2' : 'default'; //기본값

				unset($tmpGET['page'], $tmpGET['sort'], $tmpGET['sc_top']);
				$sortUrlQuerystring = getLinkFilter('', array_keys($tmpGET));
				$sc['list_style']	= $aGetParams['display_style'] ? $aGetParams['display_style'] : $display['style'];
				$CI->template->assign(array(
					'categoryData'			=> $categoryData,
					'sortUrlQuerystring'	=> $sortUrlQuerystring,
					'sort'					=> $sort,
					'orders'				=> $CI->goodsdisplay->orders,
					'sc'					=> $sc,
					'perpage_min' 			=> $perpage_min,
					'list_style'			=> $sc['list_style'],
				));

				$CI->template->assign($list);
				$CI->template->assign('sc_top',         $sc_top);
				$CI->template->assign('paging_style',   $kind);
				$CI->template->define('paging',         $CI->skin."/_modules/display/display_paging.html");
			}else{
				$sc['list_style']   = $display['style'];
				$CI->template->assign('perpage', null);
			}

			// 페이스북 좋아요 포함시
			if(FACEBOOK_TAG_PRINTED!='YES' && strstr($display['info_settings'],"fblike") && ( !$CI->__APP_LIKE_TYPE__ || $CI->__APP_LIKE_TYPE__ == 'API') ) {
				echo $CI->is_file_facebook_tag;
			}

			// 상품별 데이터 가공
			$tabRecords = array();
			$tabRecords		= $CI->goodsmodel->get_goodslist_display_light($list['record'], $display_data);

			//페이징 상품 디스플레이에서 사용
			$displayClass = 'designDisplay';
			if($kind){
				$displayClass .= ' display_'.$kind;
			}

			$goodsImageSize = config_load('goodsImageSize');
			$goodsImageSize = $goodsImageSize[$display_data['image_size']];

			// 데이터가 없을 경우 관리자 세션이 있을 경우에만 탭의 영역을 보여준다 2016-02-22 jhr
			if( $CI->managerInfo || $tab_count > 1 || $tabList[0]['record'] || $tabList[0]['tab_contents'] || ($tabList && $CI->designDisplayTabAjaxIdx) ){
				$CI->template->assign($display_data);
				$CI->template->assign('displayClass', $displayClass);
				$CI->template->assign('displayElement', 'display');
				$CI->template->assign('display_key',$display_key);
				$CI->template->assign('displayTabsList',$tabList);
				$CI->template->assign('goodsList',$tabRecords);
				$CI->template->assign('goodsImageSize',$goodsImageSize);
				$CI->template->assign('template_path',$template_path);
				$CI->template->assign('display_seq',$display_seq);
				$CI->template->assign('perpage',$perpage);
				$CI->template->assign('page',$list['page']);
				$CI->template->assign('displayStyle',$display_data['style']);
				$CI->template->assign('ajax_call',$ajax_call);
				$CI->template->assign('skin',$CI->skin);

				$CI->template->define('paging',		$CI->skin."/_modules/display/display_paging.html");
				$CI->template->define('goods_list', "../design/{$display_data['goods_decoration_favorite_key']}.html");
				$CI->template->define('tpl',		$CI->skin."/_modules/display/goods_display_{$display_data['style']}.html");

				// $CI->template->print_("tpl", '', true);
				$html = $CI->template->fetch("tpl", '', true);
			}
		}

		if ($cache_flag) {
			cache_save($cache_item_id, $html);
		}
	}

	echo $html;
}

function _heavyShowDesignDisplay($display_seq, $perpage=null, $kind=null, $iscach=null, $display_ajax_call=null, $hash_paging=null){
	$CI =& get_instance();
    $CI->load->helper('basic');
    $CI->load->helper('javascript');
    $CI->load->model('goodsdisplay');
    $CI->load->model('goodsmodel');
    $remain         = '';
    $tab_count      = '';
    $tabList        = array();
    $displayAllGoodsList   = array();
    $aGetParams     = $CI->input->get();
    $aPostParams    = $CI->input->post();
    $sc_top         = $aGetParams['sc_top']; //페이징이 있는 경우 해당 위치로 스크롤바를 옮겨준다
    if	($aGetParams['userInfo'] && !$CI->userInfo) $CI->userInfo = unserialize(base64_decode($aGetParams['userInfo']), ['allowed_classes' => false]);
    $cfg_system     = ($CI->config_system) ? $CI->config_system : config_load('system');
    $template_path  = $CI->__tmp_template_path ? $CI->__tmp_template_path : $CI->template_path;
    $ajax_call      = ($display_ajax_call) ? true : false; //상품디스플레이 ajax 호출시 item 만 가져 오도록
    $display_key    = $CI->goodsdisplay->make_display_key(); // 디스플레이 임시 코드명
    $display        = $CI->goodsdisplay->get_display($display_seq); // 디스플레이 설정 데이터

    if($CI->realMobileSkinVersion < 3 && $display['platform']=='mobile' && $display['style']=='newmatrix'){ // 모바일전용 격자형 ver2 이하
        $perpage = $display['count_w'] * $display['count_h'];
        $aGetParams['perpage'] = $perpage;
    }
    if($perpage){
        $perpage = $aGetParams['perpage'] ? $aGetParams['perpage'] : $perpage;
        $perpage = $perpage ? $perpage : 10;
        $perpage_min = $display['count_w']*$display['count_h'];
        if($perpage != $display['count_w']*$display['count_h']){
            $display['count_h'] = ceil($perpage/$display['count_w']);
        }
        if($aGetParams['category_code']){ // 카테고리 정보
            $CI->load->model('categorymodel');
            $CI->categoryData = $categoryData = $CI->categorymodel->get_category_data($code);
        }
    }else{
        $limit = $display['style']=='responsible' ? $display['count_r'] : ($display['count_w'] * $display['count_h']);
        $limit = $limit ? $limit : 4;
        if($aPostParams['page']){
            $start = ($aPostParams['page']-1)*$limit;
            $limit = " {$start}, {$limit}";
        }
    }

    if($CI->realMobileSkinVersion >  2 && $display['platform']=='mobile' && ($display['style']=='newswipe' || $display['style']=='sizeswipe')){ // 모바일전용 ver3 이상
        $display['count_w'] = $display['count_w_swipe'];
        $display['count_h'] = $display['count_h_swipe'];
        $limit              = $display['count_max_swipe'];
        $limit              = $limit ? $limit : $display['count_w']*$display['count_h'];
        if($display['style']=='sizeswipe') $limit = 20; //슬라이드(크기고정) 최대 상품 수량 20개로 픽스
        $perpage            = null;
    }

    if($CI->realMobileSkinVersion < 3 && $display['platform']=='mobile' && $display['style']=='newswipe'){ // 모바일전용 스와이프형 일때 ver2 이하
        $display['count_w'] = $display['count_w_swipe'];
        $display['count_h'] = $display['count_h_swipe'];
        $limit = $display['count_max_swipe'];
        $limit = $limit ? $limit : $display['count_w']*$display['count_h'];
    }

    if($display){
        if($CI->designDisplayTabAjaxIdx){
            $display_tabs = $CI->goodsdisplay->get_display_tab($display_seq,$CI->designDisplayTabAjaxIdx);
            $display_tabs = array($CI->designDisplayTabAjaxIdx=>$display_tabs[0]);
        }else{
			$CI->designDisplayTabAjaxIdx = 0;
            $display_tabs = $CI->goodsdisplay->get_display_tab($display_seq);
			$tabs_row = $display_tabs;
        }
        $tab_count = count($tabs_row);
        foreach($display_tabs as $tab_index => $display_tab){
            if($display['tab_design_type']=='displayTabTypeImage' && $display_tab['tab_title_img']){
                $display_tabs[$tab_index]['tab_title'] = "<span class='displayTabItemImage pointer'><img src='/data/icon/goodsdisplay/tabs/{$display_tab['tab_title_img']}' class='displayTabItemImageOff' title='{$display_tab['tab_title']}' /><img src='/data/icon/goodsdisplay/tabs/{$display_tab['tab_title_img_on']}' class='displayTabItemImageOn hide' /></span>";
            }
        }

        /**
         * list setting
         **/
        if($iscach){
            $display_tab = $display_tabs[$CI->designDisplayTabAjaxIdx];
            $aParams = array(
                'iTabIndex'     => $CI->designDisplayTabAjaxIdx,
                'display'       => $display,
                'display_tab'   => $display_tab,
                'perpage'       => $perpage,
                'aGetParams'    => $aGetParams,
                'kind'          => $kind,
                'tab_index'     => $CI->designDisplayTabAjaxIdx,
                'iscach'        => $iscach,
                'limit'         => $limit,
                'tabList'       => $tabList,
                'display_seq'   => $display_seq
            );
			$result = _designDisplayTab($aParams);
            if( ! $result ) return false;
        }
        foreach($display_tabs as $tab_index => $display_tab){
            $iTabIndex  = ($CI->designDisplayTabAjaxIdx) ? $CI->designDisplayTabAjaxIdx : $tab_index;
            if( $tab_index == 0 || $CI->designDisplayTabAjaxIdx ){
                $aParams = array(
                    'iTabIndex'     => $iTabIndex,
                    'display'       => $display,
                    'display_tab'   => $display_tab,
                    'perpage'       => $perpage,
                    'aGetParams'    => $aGetParams,
                    'kind'          => $kind,
                    'tab_index'     => $tab_index,
                    'iscach'        => $iscach,
                    'limit'         => $limit,
                    'tabList'       => $tabList,
                    'display_seq'   => $display_seq
                );
                list($sort, $sc, $tabList, $list) = _designDisplayTab($aParams);
            }else{
                $tabList[$iTabIndex]    = $display_tab;
            }
        }
        if($CI->designDisplayTabAjaxIdx && $tabList[$CI->designDisplayTabAjaxIdx]['contents_type'] != 'text'
            && empty($tabList[$CI->designDisplayTabAjaxIdx]['record']) && !$CI->managerInfo && $tab_count == 1){ // 탭이 2개이상일때에는 노출되어야함 2018-04-23
                return;
        }elseif( $tabList[$CI->designDisplayTabAjaxIdx]['contents_type'] != 'text' && empty($tabList[$CI->designDisplayTabAjaxIdx]['record']) && !$CI->managerInfo && $tab_count == 1 ){
            return;
        }
        if($ajax_call && count($list['record']) < 1){ //ajax로 호출 했을때 데이터가 없을 경우
            echo '<script>$(".displayTabContentsContainer").eq("'.$CI->designDisplayTabAjaxIdx.'").html("표시할 상품이 없습니다.");</script>';
        }
        if( $display['kind']== 'designvideo') {
            $CI->load->model('videofiles');
            if($list['record']) {
                foreach($list['record'] as $k => $data) {
                    if( $display['goods_video_type']== 'contents' ){
                        unset($videosc);
                        $videosc['tmpcode'] = $data['videotmpcode'];
                        $videosc['upkind']  = 'goods';
                        $videosc['type']    = 'contents';
                        $videoimage         = $CI->videofiles->get_data($videosc);//debug_var($videoimage);
                        if($videoimage) {
                            $list['record'][$k]['file_key_w'] = $videoimage['file_key_w'];
                            $list['record'][$k]['file_key_i'] = $videoimage['file_key_i'];
                        }else{
                            $list['record'][$k]['file_key_w'] = '';
                            $list['record'][$k]['file_key_i'] = '';
                        }
                        $file_key_i         = $list['record'][$k]['file_key_i'];
                        $file_key_w         = $list['record'][$k]['file_key_w'];
                    }else{
                        $videosc['tmpcode'] = $data['videotmpcode'];
                        $videosc['upkind']  = 'goods';
                        $videosc['type']    = 'image';
                        $videoimage         = $CI->videofiles->get_data($videosc);
                        $file_key_i         = $data['file_key_i'];
                        $file_key_w         = $data['file_key_w'];
                    }
                    if( $CI->session->userdata('setMode')=='mobile' && $file_key_i ){ //모바일이면서 file_key_i 값이 있는 경우
                        $list['record'][$k]['uccdomain_thumbnail']	= uccdomain('thumbnail',    $file_key_i);
                        $list['record'][$k]['uccdomain_fileswf']	= uccdomain('fileswf',      $file_key_i);
                        $list['record'][$k]['uccdomain_fileurl']	= uccdomain('fileurl',      $file_key_i);
                        $list['record'][$k]['videosize_w']          = $videoimage['mobile_width'];
                        $list['record'][$k]['videosize_h']          = $videoimage['mobile_height'];
                    }elseif( uccdomain('thumbnail',$file_key_w) && $file_key_w ) {
                        $list['record'][$k]['uccdomain_thumbnail']  = uccdomain('thumbnail',    $file_key_w);
                        $list['record'][$k]['uccdomain_fileswf']	= uccdomain('fileswf',      $file_key_w);
                        $list['record'][$k]['uccdomain_fileurl']	= uccdomain('fileurl',      $file_key_w);
                        $list['record'][$k]['videosize_w']          = $videoimage['pc_width'];
                        $list['record'][$k]['videosize_h']          = $videoimage['pc_height'];
                    }
                }
            }
            $tabList[0]['record'] = $list['record'];
        }
        if($perpage){
            $tmpGET             = $aGetParams;
            if(!$kind){
                $kind = ($CI->mobileMode || $CI->storemobileMode) ? 'style2' : 'default'; //기본값
            }
            unset($tmpGET['page'], $tmpGET['sort'], $tmpGET['sc_top']);
            $sortUrlQuerystring = getLinkFilter('', array_keys($tmpGET));
            $sc['list_style']	= $aGetParams['display_style'] ? $aGetParams['display_style'] : $display['style'];
            $CI->template->assign(array(
                'categoryData'			=> $categoryData,
                'sortUrlQuerystring'	=> $sortUrlQuerystring,
                'sort'					=> $sort,
                'orders'				=> $CI->goodsdisplay->orders,
                'sc'					=> $sc,
                'perpage_min' 			=> $perpage_min,
                'list_style'			=> $sc['list_style'],
            ));
            if($display['style']=='rolling_h'){
                $display['style'] = "lattice_a";
                if($display['count_w']<=2) $display['count_w'] = 4;
            }

            $CI->template->assign($list);
            $CI->template->assign('sc_top',         $sc_top);
            $CI->template->assign('paging_style',   $kind);
            $CI->template->define('paging',         $CI->skin."/_modules/display/display_paging.html");
        }else{
            $sc['list_style']   = $display['style'];
            $CI->template->assign('perpage', null);
        }
        if($display['style']=='rolling_h' && $display['h_rolling_type'] != 'moveSlides'){
            $remain_cnt = $display['count_w']-(count($list['record'])%$display['count_w']);
            if($remain_cnt <= 0 || $remain_cnt >= $display['count_w']){
                $remain_cnt = 0;
            }
            for($r_i=0;$r_i<$remain_cnt;$r_i++){
                $remain     .= '<div class="slide">&nbsp;</div>';
            }
        }
        $img_optimize	=  $display['img_opt_'.$display['style']];
        $img_padding	=  $display['img_padding_'.$display['style']];
        $CI->goodsdisplay->set('remain',            $remain);
        $CI->goodsdisplay->set('h_rolling_type',    $display['h_rolling_type']);
        $CI->goodsdisplay->set('v_rolling_type',    $display['v_rolling_type']);
        $CI->goodsdisplay->set('img_opt_lattice_a', $display['img_opt_lattice_a']);
        foreach($tabList as $row){
            $displayAllGoodsList = array_merge($displayAllGoodsList,(array)$row['record']);
        }
        if(FACEBOOK_TAG_PRINTED!='YES' && strstr($display['info_settings'],"fblike") && ( !$CI->__APP_LIKE_TYPE__ || $CI->__APP_LIKE_TYPE__ == 'API') ) {//라이크포함시
            echo $CI->is_file_facebook_tag;
        }
        if($display['platform']=='mobile' && $sc['list_style']=='newswipe'){
            echo "<script type=\"text/javascript\" src=\"/app/javascript/plugin/custom-mobile-pagination.js\"></script>";
        }
        if($kind){
            $kind_class = 'display_'.$kind; //페이징 상품 디스플레이에서 사용
        }
        if(!$CI->designDisplayTabAjaxIdx && !$iscach){
            echo "<div id='".$display_key."' class='designDisplay ".$kind_class."' designElement='display' templatePath='".$template_path."' displaySeq='".$display_seq."' page='".$aGetParams['page']."' perpage='".$perpage."' count_w='".$display['count_w']."' displayStyle='".$sc['list_style']."'>";
        }
        if(
            $CI->managerInfo || $tab_count > 1 || $tabList[0]['record'] || $tabList[0]['tab_contents'] ||
            ($tabList && $CI->designDisplayTabAjaxIdx)
        ){ // 데이터가 없을 경우 관리자 세션이 있을 경우에만 탭의 영역을 보여준다 2016-02-22 jhr
			$count_h    = ($perpage) ? ceil($perpage/$display['count_w']) : $display['count_h'];
			$CI->goodsdisplay->set('APP_USE',                   $CI->__APP_USE__);
			$CI->goodsdisplay->set('perpage',                   $perpage);
			$CI->goodsdisplay->set('count_h',                   $count_h);
			$CI->goodsdisplay->set('display_key',               $display_key);
			$CI->goodsdisplay->set('displayGoodsList',          $displayAllGoodsList);
			$CI->goodsdisplay->set('displayTabsList',           $tabList);
			$CI->goodsdisplay->set('img_optimize',              $img_optimize);
			$CI->goodsdisplay->set('img_padding',               $img_padding);
			$CI->goodsdisplay->set('paging_style',              $kind);
			$CI->goodsdisplay->set('ajax_call',                 $ajax_call);
			$CI->goodsdisplay->set('hash_paging',               $hash_paging);
			$CI->goodsdisplay->set('style',                     $sc['list_style']);
			$CI->goodsdisplay->set('displayTitle',              $list['display_title']);
			$CI->goodsdisplay->set('title',                     $display['title']);
			$CI->goodsdisplay->set('platform',                  $display['platform']);
			$CI->goodsdisplay->set('count_w',                   $display['count_w']);
			$CI->goodsdisplay->set('count_w_lattice_b',         $display['count_w_lattice_b']);
			$CI->goodsdisplay->set('count_h_lattice_b',         $display['count_h_lattice_b']);
			$CI->goodsdisplay->set('count_h_list',              $display['count_h_list']);
			$CI->goodsdisplay->set('count_w_rolling_v',         $display['count_w_rolling_v']);
			$CI->goodsdisplay->set('kind',                      $display['kind']);
			$CI->goodsdisplay->set('navigation_paging_style',   $display['navigation_paging_style']);
			$CI->goodsdisplay->set('goods_video_type',          $display['goods_video_type']);
			$CI->goodsdisplay->set('videosize_w',               $display['videosize_w']);
			$CI->goodsdisplay->set('videosize_h',               $display['videosize_h']);
			$CI->goodsdisplay->set('image_decorations',         $display['image_decorations']);
			$CI->goodsdisplay->set('image_size',                $display['image_size']);
			$CI->goodsdisplay->set('text_align',                $display['text_align']);
			$CI->goodsdisplay->set('info_settings',             $display['info_settings']);
			$CI->goodsdisplay->set('tab_design_type',           $display['tab_design_type']);
			$CI->goodsdisplay->set('mobile_h',                  $display['mobile_h']);
			$CI->goodsdisplay->set('m_list_use',                $display['m_list_use']);
			$CI->goodsdisplay->print_();
			if(!$CI->designDisplayTabAjaxIdx && !$iscach){
				echo "</div>";
			}

			// GA4 연동
			if	($CI->ga4_auth_commerce && (!$CI->uri->uri_string || $CI->uri->uri_string == "page/index") && $list['record'] && !$return  && !$iscach) {
				$CI->load->model('eventmodel');
				$CI->load->library('ga4library');
				$tpl = $aGetParams['tpl'];
				$event_info = [];

				if	($tpl)	{
					if	($CI->eventmodel->is_gift_template_file($tpl))  {
						$event_type = 'gift';
						$query = $CI->db->query("select *, if(current_date() between start_date and end_date,'진행 중',if(end_date < current_date(),'종료','시작 전')) as status from fm_".$event_type." where tpl_path=?",array($tpl));
					}	else if($CI->eventmodel->is_event_template_file($tpl)) {
						$event_type = 'event';
						$query = $CI->db->query("select *, if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status from fm_".$event_type." where tpl_path=?",array($tpl));
					}

					if ($event_type && $query) {
						$data = $query->row_array();
						$event_info = [
							$event_type.'_seq' => $data[$event_type.'_seq'],
										'title' => $data['title'],
									'tpl_path' => $data['tpl_path']
						];
						$CI->ga4library->view_promotion('직접이벤트',$event_info,$displayAllGoodsList,$event_type);
					}
				}


			}
        }
    }
}

function _designDisplayTab($aParams)
{
    $CI = & get_instance();
    $CI->load->model('goodsdisplay');
    $CI->load->model('goodsmodel');
    foreach($aParams as $sKey => $sValue){
        ${$sKey} = $sValue;
    }

    if($display_tab['auto_use']=='y' && $display_tab['auto_condition_use'] != 1){ // 상품 자동노출 조건 파싱
        $sc     = $CI->goodsdisplay->search_condition($display_tab['auto_criteria'], $sc);
        $scSort = $sc['auto_order'];
    }else{
        $scSort = 'display';
    }
    $sc['sort']	= ($perpage && !empty($aGetParams['sort'])) ? $aGetParams['sort'] : $scSort;

    $sort                       = $sc['sort'];
    $sc['display_seq']          = $display_seq;
    $sc['admin_category']       = defined('__ISADMIN__') ? true : false;
    $sc['display_tab_index']    = $CI->designDisplayTabAjaxIdx ? $CI->designDisplayTabAjaxIdx : $tab_index;
    $sc['page']                 = (!empty($aGetParams['page'])) ? intval($aGetParams['page']) : '1';
    $sc['perpage']              = $perpage ? $perpage : 1000;
    $sc['image_size']           = $display['image_size'];
    $sc['limit']                = $limit;
    if($perpage){
        $sc['category_code']	= !empty($aGetParams['category_code'])      ? $aGetParams['category_code'] : '';
        $sc['brands']			= !empty($aGetParams['brands'])             ? $aGetParams['brands'] : array();
        $sc['brand_code']		= !empty($aGetParams['brand_code'])         ? $aGetParams['brand_code'] : '';
        $sc['search_text']		= !empty($aGetParams['search_text'])        ? $aGetParams['search_text'] : '';
        $sc['old_search_text']	= !empty($aGetParams['old_search_text'])    ? $aGetParams['old_search_text'] : '';
        $sc['start_price']		= !empty($aGetParams['start_price'])        ? $aGetParams['start_price'] : '';
        $sc['end_price']		= !empty($aGetParams['end_price'])          ? $aGetParams['end_price'] : '';
        $sc['color']			= !empty($aGetParams['color'])              ? $aGetParams['color'] : '';
    }
    if($CI->goodsdisplay->info_settings_have_eventprice($display['info_settings'])){
        $sc['join_event']   = true;
    }
    if($kind == 'style2' || $hash_paging > 0) { // main paging add
        $sc['m_code']       = 'all_item';
    }
    if($display_tab['auto_use']=='y' && $display_tab['auto_condition_use'] == 1){
        if($display_tab['contents_type'] == 'auto_sub'){
            $sc['bigdata']              = 1;
            $sc['goods_seq_exclude']    = $CI->bigdataGoodsSeq;
        }
        if($perpage && !$sort){
            $sort   = 'act';
        }
        $sc     = $CI->goodsdisplay->auto_select_condition($display_tab['auto_criteria'], $sc);
        $list   = $CI->goodsmodel->auto_condition_goods_list($sc);
        $tabList[$iTabIndex]            = $display_tab;
        $tabList[$iTabIndex]['record']  = $list['record'];
    }else if( $display_tab['cache_use'] != 'y' || !$sFileName || $iscach || $CI->userInfo['member_seq'] || $CI->designMode || $CI->managerInfo ) {
        $list   = $CI->goodsmodel->goods_list($sc);
        $tabList[$iTabIndex]            = $display_tab;
        $tabList[$iTabIndex]['record']  = $list['record'];
    }
    if( $iscach ){
        echo serialize($tabList[$tab_index]);
        return false;
    }
    return array($sort, $sc, $tabList, $list);
}
?>
