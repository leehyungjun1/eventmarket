<?php

/* 상품디스플레이 출력*/
function metaHeaderWrite($add_meta_info,$og=false){

	$CI			=& get_instance();
	$CI->load->helper('basic');
	$seo_info	= ($CI->seo) ? $CI->seo : config_load('seo');
	
	$now_page		= $_SERVER['REDIRECT_QUERY_STRING'];
	
	if(isset($seo_info) === false){
		$CI->template->assign('old_meta','Y');
		/** 스킨 업데이트 없이 호환성 확보를 위해 이곳에 추가하였음 */
		return '<script nonce="'.script_nonce().'">'.front_config_js().'</script>';
	}

	if($now_page == '/page/index'){
		//예외페이지 처리
		if(preg_match('/etc%2Fevent/',$_SERVER['QUERY_STRING']))		$now_page	= 'event';
		else if(preg_match('/etc%2Fgift/',$_SERVER['QUERY_STRING']))	$now_page	= 'event';
	}

	$add_meta_info['shop_name']	= $CI->config_basic['shopName'];
	$replace['shop_name']		= '{쇼핑몰명}';

	switch($now_page){
		case	'/goods/view' :
			$now_seo_page				= 'goods_view';
			$replace['goods_name']		= '{상품명}';
			$replace['summary']			= '{간략설명}';
			$replace['brand_title']		= '{브랜드명}';
			$replace['category']		= '{카테고리명}';
			$replace['keyword']			= '{검색어}';
			break;
		
		case	'/goods/catalog' :
			$now_seo_page				= 'category';
			$replace['category']		= '{카테고리명}';
			break;
		
		case	'/goods/brand' :
			$now_seo_page				= 'brand';
			$replace['brand_title']		= '{브랜드명}';
			$replace['brand_eng_title']	= '{브랜드영문명}';
			$replace['brand_country']	= '{브랜드국가}';
			break;
		
		case	'/goods/location' :
			$now_seo_page				= 'location';
			$replace['location']		= '{지역}';
			break;
		
		// 할인 이벤트
		case	'/promotion/event_view' :
		// 사은품 이벤트
		case	'/promotion/gift_view' :
		// 출석체크 이벤트
		case	'/joincheck/joincheck_view' :
		case	'event' :
			$now_seo_page				= 'event';
			$replace['event_title']		= '{이벤트}';
			break;

		case	'/board' :
		case	'/board/' :
		case	'/board/view' :
			$now_seo_page				= 'board';
			$replace['board_neme']		= '{게시판}';
			$replace['subject']			= '{게시글제목}';
			break;

		case	'/broadcast/player' :
		case	'/broadcast/vod' :
			$now_seo_page				= 'broadcast';
			$replace['goods_name']		= '{상품명}';
			$replace['title']			= '{방송제목}';
			$replace['summary']			= '{방송설명}';
			$replace['start_date']		= '{방송일}';
			$replace['provider_name']	= '{방송입점사}';
		break;

		default :
			$now_seo_page				= 'others';
			break;
	}

	// 전용 스킨 할인, 사은품 이벤트 페이지 접속 시 타이틀 명 설정
	if (preg_match('/link\/etc\/event|gift/', $now_page)) {
		$now_seo_page = 'event';
		$replace['event_title'] = '{이벤트}';
	}
	
	//기본정보
	$base_meta			= $seo_info[$now_seo_page];
	$robot_allow_mode	= ($seo_info[$now_seo_page.'_allow'] == 'disallow') ? 'noindex,nofollow' : 'index,follow';

	//코드 치환
	foreach((array)$base_meta as $meta => $val){
		foreach($replace as $key => $code){
			$val	= str_replace($code, $add_meta_info[$key], $val);
		}

		$CI->meta_data[$meta]	= htmlspecialchars(strip_tags($val));
	}
	
	//상품 상세 페이지 image alt attr
	if($now_seo_page == 'goods_view'){
		$image_alt	= $seo_info['image_alt'];
		foreach($replace as $key => $code){
			$image_alt	= str_replace($code, $add_meta_info[$key], $image_alt);
		}

		$image_alt			= htmlspecialchars(strip_tags($image_alt));

		$CI->template->assign('goods_view_image_alt', $image_alt);
	}

	$CI->template->assign('old_meta','N');

	$new_meta_arr[]	= '<meta name="Robots" content="'.$robot_allow_mode.'" />';
	$new_meta_arr[]	= '<meta name="title" content="'.$CI->meta_data['title'].'" />';
	$new_meta_arr[]	= '<meta name="author" content="'.$CI->meta_data['author'].'" />';
	$new_meta_arr[]	= '<meta name="description" content="'.$CI->meta_data['description'].'" />';
	$new_meta_arr[]	= '<meta name="keywords" content="'.$CI->meta_data['keywords'].'" />';
	
	if($CI->config_basic['operating'] == 'adult' && $CI->config_basic['domain']){
		$new_meta_arr[]	= '<META http-equiv="PICS-label" content=\'(PICS-1.1 "http://service.kocsc.or.kr/rating.html" l gen true for "'.$CI->config_basic['domain'].'" r (y 1))\'>';   
	}
	
	if($CI->config_basic['operating'] != 'adult'
		&& $CI->config_basic['domain'] 
		&& ($now_page == '/member/adult_auth' || $now_page == '/goods/view' || $now_page == '/intro/adult_only')){
		$new_meta_arr[]	= '<META http-equiv="PICS-label" content=\'(PICS-1.1 "http://service.kocsc.or.kr/rating.html" l gen false for "'.$CI->config_basic['domain'].'" r (y 1))\'>';
	}
	if($og===true) {
		$new_meta_arr[]	= '<meta property="og:url" content="'.$CI->domainurl.$_SERVER['REQUEST_URI'].'" />';
		$new_meta_arr[]	= '<meta property="og:type" content="website" />';
		$new_meta_arr[]	= '<meta property="og:title" content="'.$CI->meta_data['title'].'" />';
		$new_meta_arr[]	= '<meta property="og:description" content="'.$CI->meta_data['description'].'" />';
	}
	
	$new_meta		= implode("\n", $new_meta_arr);
	$CI->template->assign('new_meta', $new_meta);
	$CI->template->assign('shopTitle', $CI->meta_data['title']);

	/** 스킨 업데이트 없이 호환성 확보를 위해 이곳에 추가하였음 */
	return '<script nonce="'.script_nonce().'">'.front_config_js().'</script>';
}
