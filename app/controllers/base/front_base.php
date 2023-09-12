<?php if ( ! defined("BASEPATH")) exit("No direct script access allowed");
require_once(APPPATH ."controllers/base/common_base".EXT);

class front_base_original extends common_base {
	var $skin;
	var $realSkin;
	var $workingSkin;
	var $config_basic;
	var $userInfo;
	var $managerInfo;
	var $skin_config;
	var $skin_currency;

	public function __construct() {

		parent::__construct();
		xss_clean_filter();

		$this->load->model('layout');
		$this->load->helper('design');
		$this->load->model('visitorlog');
		$this->load->model('ssl');

		// 2016.08.19 pjw sns 정보 가져옴
		$snsinfo = config_load('snssocial');
		$this->template->assign(array('sns'=>$snsinfo));

		/* 현재 언어 저장 */
		switch($this->config_system['language']){
			case "KR":$config_language_ori = 'korean';break;
			case "US";$config_language_ori = 'english';break;
			case "CN";$config_language_ori = 'chinese';break;
			case "JP";$config_language_ori = 'japanese';
		}
		$this->config->set_item('language',$config_language_ori);


		/* 유입경로 저장 */
		if(!$this->managerInfo && !$this->providerInfo) {
			$this->visitorlog->set_referer();
		}

		/* ssl 도메인 체크 */
		$this->ssl->ssl_domain_check();

		/* 현재 보여줄 스킨 */
		$this->skin = $this->layout->get_view_skin();
		$this->template->assign(array('skin'=>$this->skin));

		/* 현재 보여줄 스킨의 (비교)통화설정 */
		/*
		$sql	= "select * from fm_skin where admin_env_seq='".$this->config_system['admin_env_seq']."' and  skin_real_name='".$this->layout->get_view_skin()."'";
		$query	= $this->db->query($sql);
		$row	= $query->row_array();
		$this->skin_config['skin_seq']						= $row['skin_seq'];
		$this->skin_config['skin_name']						= $row['skin_name'];
		$this->skin_config['language']						= $row['language'];
		$this->skin_config['skin_currency_simbol_postion']	= $row['currency_simbol_postion'];
		$this->skin_config['skin_currency_simbol']			= $row['currency_simbol'];
		$this->skin_config['skin_currency']					= $row['currency'];
		$this->template->assign($this->skin_config);
		if(!$this->skin_config['skin_seq']) debug("스킨정보 없음!!!");
		*/

		$setMode = $this->session->userdata('setMode');

		/* 디자인모드 여부 */
		$this->designMode			= $this->layout->is_design_mode();

		/* 일반스킨 */
		$this->realSkin				= $this->config_system['skin'];
		$this->workingSkin			= $this->config_system['workingSkin'];

		/* 모바일 쇼핑몰 스킨*/
		$this->realMobileSkin		= $this->config_system['mobileSkin'];
		$this->workingMobileSkin	= $this->config_system['workingMobileSkin'];

		/* 페이머스용 스킨 */
		$this->realFammerceSkin		= $this->config_system['fammerceSkin'];
		$this->workingFammerceSkin	= $this->config_system['workingFammerceSkin'];

		/* 아이디자인에서 처리할 스킨 */
		if		($setMode == 'mobile')		$this->designWorkingSkin = $this->workingMobileSkin;
		elseif	($this->fammerceMode)		$this->designWorkingSkin = $this->workingFammerceSkin;
		else 								$this->designWorkingSkin = $this->workingSkin;

		if($this->fammerceMode && !empty($this->realFammerceSkin)){
			$this->realSkin		= $this->realFammerceSkin;
			$this->workingSkin	= $this->workingFammerceSkin;
		}

		if($setMode == 'mobile' && !empty($this->realMobileSkin)){
			$this->realSkin		= $this->realMobileSkin;
			$this->workingSkin	= $this->workingMobileSkin;
		}

		/* 반응형 일때 mobileMode 를 강제로 True 지정 :: 2019-01-03 lwh */
		if($this->config_system['operation_type'] == 'light'){
			$this->mobileMode	= true;
		}

		/* 검색어 */
		$config_search = config_load("search");
		$this->load->model('searchwordmodel');
		$uri_str = uri_string();
		if(strpos($uri_str, "goods/search") !== false){
			if( $this->config_system['operation_type'] != 'light' ) $search_word_data = $this->searchwordmodel->get_word_by_page('goods_search');
		}else if(strpos($uri_str, "goods/view") !== false){
			$search_word_data = $this->searchwordmodel->get_word_by_page('good_view');
		}else if(strpos($uri_str, "goods/catalog") !== false){
			$search_word_data = $this->searchwordmodel->get_word_by_page('category');
		}else if(strpos($uri_str, "goods/brand") !== false){
			$search_word_data = $this->searchwordmodel->get_word_by_page('brand');
		}else if(strpos($uri_str, "goods/location") !== false ){
			$search_word_data = $this->searchwordmodel->get_word_by_page('location');
		}else if($this->uri->rsegments[1] == 'mypage'){
			$search_word_data = $this->searchwordmodel->get_word_by_page('mypage');
		}else if($this->uri->rsegments[1] == 'mshop'){
			$search_word_data = $this->searchwordmodel->get_word_by_page('mshop');
		}else if(strpos($uri_str, "board") !== false){
			$search_word_data = $this->searchwordmodel->get_word_by_page('board');
		}else if(preg_match('/event|gift/',$_SERVER['REQUEST_URI'])){
			$search_word_data = $this->searchwordmodel->get_word_by_page('event');
		}else{
			$search_word_data = $this->searchwordmodel->get_word_by_page('main');
		}

		### MEMBER SESSION
		$this->userInfo = $this->session->userdata('user');

		if($this->uri->rsegments[2]!='mobile_mode_off' && uri_string() != 'main/blank'){
			$this->operating_check();
		}

		//취약점 개선 start @2017-01-02
		$_GET['popup'] = (isset($_GET['popup']) && $_GET['popup'] == 1)?1:null;
		$_GET['iframe'] = (isset($_GET['iframe']) && $_GET['iframe'] == 1)?1:null;
		if(isset($_GET['display_style']) && $_GET['display_style'])
			$_GET['display_style'] = preg_replace("/[#\&\+\-%@=\/\\\:;,\.'\"\^`~\|\!\?\*$#<>()\[\]\{\}]/i", "", $_GET['display_style']);
		if(isset($_GET['categoryNavigationKey']))
			$_GET['categoryNavigationKey'] = preg_replace("/[#\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "", $_GET['categoryNavigationKey']);
		//취약점 개선 end @2017-01-02

		$auto_search_use = $search_word_data[0]['page_yn'];
		$auto_search_text = $search_word_data[0]['word'];
		$auto_search_type = $search_word_data[0]['search_result'];
		$auto_search_target = $search_word_data[0]['search_result_target'];
		$auto_search_link = $search_word_data[0]['search_result_link'];
		$auto_search_complete = $config_search['auto_search'];
		$popular_search_complete = $config_search['popular_search'];
		$this->template->assign(array('config_search'=>$config_search));

		$this->template->assign(array('auto_search_complete'=>$auto_search_complete));
		$this->template->assign(array('popular_search_complete'=>$popular_search_complete));

		$this->template->assign(array('auto_search_use'=>$auto_search_use));
		$this->template->assign(array('auto_search_text'=>$auto_search_text));
		$this->template->assign(array('auto_search_type'=>$auto_search_type));
		$this->template->assign(array('auto_search_link'=>$auto_search_link));
		$this->template->assign(array('auto_search_target'=>$auto_search_target));

		$this->template->assign(array('designMode'=>$this->designMode));
		$this->template->assign(array('mobileMode'=>$this->mobileMode));
		$this->template->assign(array('fammerceMode'=>$this->fammerceMode));

		# 기본통화설정 값 @2017-02-06
		$this->template->assign(array('basic_currency_info'		=> $this->config_currency[$this->config_system['basic_currency']]));

		// http_protocol
		$http_protocol = $_SERVER['HTTPS'] ? 'https' : 'http';
		$this->template->assign(array('http_protocol'=>$http_protocol));

		if ( isset($this->userInfo['member_seq']) ) {
			define('__ISUSER__',true);//회원로그인

			/* 유저정보 assign */
			$this->template->assign('userInfo',$this->userInfo);

			//비밀번호 변경 유도
			if($this->userInfo['password_update_date']){
				$member_config = config_load('member');
				if($member_config['modifyPW'] == "Y"){
					$password_update_date = str_replace("-", "", substr(date('Y-m-d H:i:s',time()-(int)$member_config['modifyPWMin']*24*3600), 0, 10));
					$member_password_date = str_replace("-", "", substr($this->userInfo['password_update_date'], 0, 10));
					if((int)$password_update_date >= (int)$member_password_date){
						$this->template->assign('passwordChange','Y');
						$rate_month = $member_config['modifyPWMin'] / 30;
						$this->template->assign('passwordRate',$rate_month);

						// 예외 url
						$arrExceptUrl = array('popup_change_pass', 'blank' ,'check_password_validation');
						if(($this->mobileMode || $this->storemobileMode) && !in_array($this->uri->rsegments[2], $arrExceptUrl)){
							pageRedirect("/member/popup_change_pass?popup=1");
						}
					}
				}
			}

		}

		// 비회원 로그인 세션이 있는경우 assign 처리 :: 2019-02-08 pjw
		$sess_order = $this->session->userdata('sess_order');
		if ( isset($sess_order) ) {
			$this->template->assign('sess_order', $sess_order);
		}

		/**************************************************************/

		/* 가비아 통신처리시에는 아래 소스 건너뜀 */
		if($this->uri->rsegments[1]=='_gabia') return;
		if($_SERVER['SHELL']) return;
		if(php_sapi_name() == 'cli' ) return;

		/**************************************************************/

        /*######################## 17.12.15 gcs yjy : 앱 처리 s */

		// 접속 디바이스 정보 세팅
		$deviceInfo = getDeviceEnvirnment();
		$this->mobileapp = $deviceInfo['mobileapp'];
		$this->m_device = $deviceInfo['device'];

		// 앱 접속 쿠키 설정
		setcookie('mobileapp', $this->mobileapp, time()+(86400*365),'.firstmall.kr');
		$this->session->set_userdata('mobileapp', $this->mobileapp);


		if($this->mobileapp=='Y'){
			$this->template->assign(array('mobileapp'=>'Y'));
		}

		if(!empty($_COOKIE['auto_login'])  ) {
			$this->session->set_userdata('auto_login',$_COOKIE['auto_login']);
			$this->template->assign(array('auto_login'=>$_COOKIE['auto_login']));
        }
        $this->template->assign("m_device",$this->m_device);


        //$this->m_device = "iphone";
        //$this->mobileapp = "Y";
        /*######################## 17.12.15 gcs yjy : 앱 처리 e */

		checkEnvironmentValidation();
		checkExpireDate();

		/* 모바일기기일때 */
		if($this->_is_mobile_agent && $this->operation_type == 'heavy'){
			/* 모바일도메인이 아닐때 모바일로 이동 */
			if($this->session->userdata('setMode')!='pc' && !$this->_is_mobile_domain){
				$mobile_domain = "m.".preg_replace("/^www\./","",$_SERVER['HTTP_HOST']).$_SERVER['REQUEST_URI'];
				// 요청 프로토콜을 유지한 체로 모바일 도메인으로 이동 by hed
				redirect($http_protocol."://".$mobile_domain);
			}
		}

		/* 스킨 존재여부 체크 */
		$this->skin_exists_check();

		/* 차단아이피 체크 */
		$this->load->model('protectip');
		$this->protectip->protect_ip_check();

		/* OPERATION SETTING */
		$this->managerInfo = $this->session->userdata('manager');
		if($this->managerInfo) {
			define('__ISADMIN__',true);//관리자인경우
			$this->template->assign(array('ISADMIN'=>__ISADMIN__));
		}
		/*
		else{
			if($this->uri->rsegments[2]!='mobile_mode_off'){
				$this->operating_check();
			}
		}
		*/

		/* 쇼핑몰 타이틀 */
		$title = $this->config_basic['shopTitleTag'];
		$this->template->assign(array('shopTitle'=>$title));

		/* 하단 무통장 정보 */
		$bank_temp = config_load('bank');
		if($bank_temp) foreach(config_load('bank') as $k => $v){
			if	($v['accountUse'] == 'y') {
				list($tmp) = code_load('bankCode',$v['bank']);
				$v['bank'] = $tmp['value'];
				$bank_arr[] = $v;
			}
			$this->template->assign(array('bank_loop'=>$bank_arr));
		}

		/* 즐겨찾기(북마크) */
		//홑따옴표,쌍따옴표 있을경우 즐겨찾기오류 발생으로 추가 leewh 2014-10-29
		$tmp_title = str_replace(array('&quot;', '&apos;'), array('"', "'"), $title);
		$title = str_replace("'", "\'", $tmp_title);
		$title = str_replace('"', "\'", $title);

		$bookmark = bookmarkckeck($_COOKIE['bookmark'], $title);
		$this->template->assign('bookmark',$bookmark);

		$this->template_path = implode('/',$this->uri->rsegments).".html";

		/* 상단 회원가입 포인트 */
		$emoneyapp = config_load('member');

		$this->template->assign(array(
				"designSetMode"=>$this->session->userdata('setMode'),
				"designMode"=>$this->designMode,
				"template_path"=>$this->template_path,
				"member_emoneyapp"=>$emoneyapp
		));

		/* 하단 반송지 노출(반응형) */
		$cache_item_id = 'shipping_refund_address';
		$refund_address = cache_load($cache_item_id);
		if ($refund_address === false) {
			$this->load->model('shippingmodel');
			$refund_address = $this->shippingmodel->get_default_address();

			//
			cache_save($cache_item_id, $refund_address);
		}
		$this->template->assign(array("refund_address"=>$refund_address));

		if(strstr($_SERVER['HTTP_REFERER'], "/goods") !== false){
			$_SESSION["refer_adress"] = $_SERVER['HTTP_REFERER'];
		}

		/* 공통으로 필요해서 밖으로 빼버림 2021.06.24 */
		$this->load->model('cartmodel');
		$push_count_cart = $this->cartmodel->get_cart_count();
		$push_price_cart = $this->cartmodel->get_cart_total_price();
		$this->session->set_userdata('cartCount',$push_count_cart); // 채널톡 연동으로 인해 장바구니갯수카운트 세션에 추가
		$this->session->set_userdata('cartPrice', $push_price_cart);

		if($this->config_system['operation_type'] == 'light' || $this->mobileMode || $this->storemobileMode){

			$pushes['push_count_cart'] = $push_count_cart;
			$pushes['push_price_cart'] = $push_price_cart; // 채널톡 추가개발  장바구니 총 금액 추가

			if($this->userInfo['member_seq']){
				$query = "select count(*) cnt from fm_order where member_seq=? and step > 0 and step < 75 and hidden='N'";
				$query = $this->db->query($query,array($this->userInfo['member_seq']));
				$data = $query->result_array();
				$pushes['push_count_order'] = $data[0]['cnt'];

				$this->load->model('wishmodel');
				$pushes['push_count_wish'] = $this->wishmodel->get_wish_count($this->userInfo['member_seq']);
			}

			$today_view = $_COOKIE['today_view'];
			if( $today_view ) {
				$today_view = unserialize($today_view, ['allowed_classes' => false]);
				krsort($today_view);
				$this->load->model('goodsmodel');
				$push_count_today = $this->goodsmodel->get_goods_list($today_view,'thumbScroll');
				$pushes['push_count_today'] = count($push_count_today);
				$this->template->assign(array('dataRightQuicklist'=>$push_count_today));
				$pushes['push_count_today_images'] = ($push_count_today[$today_view[($pushes['push_count_today']-1)]]['image']);//mobile 첫번째 이미지추출
			}

			$this->template->assign($pushes);
		}

		/*모바일 메인상단바를 위함*/
		if(($this->uri->segment(1) == "main") && (strpos($this->uri->segment(2),"tab_") !== FALSE)){
			$tabFile = strpos($this->uri->segment(2),".html") !== FALSE ? $this->uri->segment(2) :  $this->uri->segment(2).".html";
			redirect("topbar/index?no=".$tabFile);
			exit;
		}

		/* 영역 define */
		$defines = array();
		$defines['HTML_HEADER'] 		= $this->skin."/_modules/common/html_header.html";
		$defines['HTML_FOOTER'] 		= $this->skin."/_modules/common/html_footer.html";
		$defines['paging'] 				= $this->skin."/_modules/common/paging.html";
		$this->template->define($defines);


	}

	/* 스킨 존재여부 체크 */
	public function skin_exists_check(){
		$view_skin_path = APPPATH."../data/skin/".$this->skin;
		if(!is_dir($view_skin_path)){
			echo ("{$this->skin} 스킨 디렉토리를 찾을 수 없습니다.");
			exit;
		}
	}

	public function tempate_modules(){

		$filePath = APPPATH."../data/skin/".$this->skin."/_modules/";
		$map = directory_map($filePath);

		foreach($map as $dir => $dirRow) {
			if(is_array($dirRow)) {
				$dir = str_replace('/','',$dir);
				foreach($dirRow as $modulePath) {
					$modulesList[$dir."_".substr($modulePath,0,-5)] = $this->skin."/_modules/".$dir."/".$modulePath;
				}
			}
		}

		$this->template->define($modulesList);
	}

	public function template_path($addPath=null){
		return $this->skin."/".(($addPath)?$addPath."/":"").implode('/',$this->uri->rsegments).".html";
	}

	/* 레이아웃 출력 */
	public function print_layout($template_path)
	{
		$this->tempate_modules();

		/* 방문자 분석 기록 */
		//페이스북 허수 페이지뷰 로그 감지 kmj
		switch(1) {
			default:
				$this->visitorlog->execute();
				break;
			case preg_match('/\/errdoc\/404\.html$/', $template_path):
			case substr($_SERVER['HTTP_USER_AGENT'], 0, 8) === "facebook":
			case $this->managerInfo:
			case $this->providerInfo:
				break;
		}

		/* 게시판일경우 게시판스킨별로 레이아웃이 별도처리되므로 분기하여 처리함 */
		if($this->uri->segment(1)=='board'){
			$board_template_path = $this->skin.'/'.$this->template_path;
			$tpl_path = substr($board_template_path,strpos($board_template_path,'/')+1);
			$layout_config = layout_config_autoload($this->skin,$tpl_path);
		}else{
			$tpl_path = substr($template_path,strpos($template_path,'/')+1);
			$layout_config = layout_config_autoload($this->skin,$tpl_path);
		}

		/** 동일한 레이아웃 적용
		* - 본인인증/회원가입구분/약관동의 페이지는 약관동의페이지와 동일한 레이아웃적용
		* - 회원정보/비밀번호확인 -> 회원정보수정페이지와 동일한 레이아웃적용
		* @2017-03-03
		**/
		if( $this->uri->uri_string == 'member/agreement' || $this->uri->uri_string == 'mypage/myinfo'  ) {
			$tpl_path_agree = $this->uri->uri_string.'.html';
			$layout_config_agree = layout_config_autoload($this->skin,$tpl_path_agree);
			if($layout_config_agree){
				foreach($layout_config_agree[$tpl_path_agree] as $key => $val) {
					$layout_config[$tpl_path][$key] = $val;
				}
			}
		}

		$category_config = config_load('category');

		/* 측면영역 강제 숨김처리 */
		if($this->layout->is_fullsize_absolutly($tpl_path)){
			$tmp_layout_config = layout_config_load($this->skin,$tpl_path);
			if(!$tmp_layout_config[$tpl_path]['tpl_desc']){
				$layout_config[$tpl_path]['layoutSide'] = 'hidden';
			}
		}

		/* 페이머스 측면영역 강제 숨김처리
		if($this->fammerceMode){
			$layout_config[$tpl_path]['layoutSide'] = 'hidden';
		}
		*/

		/* 모바일 페이지 레이아웃 숨김 예외처리 :: 2015-03-16 lwh */
		if($this->mobileMode && $tpl_path == 'intro/member_only.html'){
			// 모바일 페이지는 레이아웃을 숨기지 않음. ::
		}else{
			/* 팝업페이지 상하양측 레이아웃 강제 숨김처리 */
		    if(preg_match("/^popup\//",$tpl_path) || preg_match("/^promotion\/goods_code_list/",$tpl_path) || preg_match("/^errdoc\//",$tpl_path) || preg_match("/^intro\//",$tpl_path) || preg_match("/^order\/pop_delivery_address/", $tpl_path) || preg_match("/^member\/adult_auth/", $tpl_path) || $_GET['quickview'] || $_GET['display_style']=='mobile_zoom'){
				$layout_config[$tpl_path]['layoutScrollLeft'] = 'hidden';
				$layout_config[$tpl_path]['layoutScrollRight'] = 'hidden';
				$layout_config[$tpl_path]['layoutHeader'] = 'hidden';
				$layout_config[$tpl_path]['layoutTopBar'] = 'hidden';
				$layout_config[$tpl_path]['layoutMainTopBar'] = 'hidden';
				$layout_config[$tpl_path]['layoutFooter'] = 'hidden';
				$layout_config[$tpl_path]['layoutSide'] = 'hidden';
			}elseif(preg_match("/^mshop\//",$tpl_path)){
				$layout_config[$tpl_path]['layoutScrollLeft'] = 'hidden';
				$layout_config[$tpl_path]['layoutScrollRight'] = 'hidden';
				$layout_config[$tpl_path]['layoutSide'] = 'hidden';
			} else if(preg_match("/^member\/(agreement|find)/i",uri_string())){
			    $layout_config[$tpl_path]['layoutScrollLeft'] = 'hidden';
			    $layout_config[$tpl_path]['layoutScrollRight'] = 'hidden';
			}
		}

		/*아이디자인 px / % 조정 20170614*/
		$layout_config[$tpl_path]['width_sign'] = (!$layout_config[$tpl_path]['width_sign']) ? 'px': $layout_config[$tpl_path]['width_sign'];
		$layout_config[$tpl_path]['body_width_sign'] = (!$layout_config[$tpl_path]['body_width_sign']) ? 'px': $layout_config[$tpl_path]['body_width_sign'];

		if($layout_config[$tpl_path]['body_width_sign'] == '%') {
			if($layout_config[$tpl_path]['width_sign'] == 'px') {
				$per_result_px = ($layout_config[$tpl_path]['width'] / 100) * $layout_config[$tpl_path]['body_width'];
				$layout_config[$tpl_path]['body_width'] = $per_result_px;
				$layout_config[$tpl_path]['body_width_sign'] = 'px';
			}
		}
		/*end*/

		/* 레이아웃설정 assign */
		$this->template->assign(array('layout_config'=>$layout_config[$tpl_path]));

		/* 영역 define */
		$defines = array();
		$defines['LAYOUT'] 				= $this->skin."/_modules/layout.html";
		$defines['LAYOUT_BODY'] 		= $template_path;
		$defines['LAYOUT_SCROLL_LEFT'] 	= $this->skin."/".$layout_config[$tpl_path]['layoutScrollLeft'];
		$defines['LAYOUT_SCROLL_RIGHT'] = $this->skin."/".$layout_config[$tpl_path]['layoutScrollRight'];
		$defines['LAYOUT_HEADER'] 		= $this->skin."/".$layout_config[$tpl_path]['layoutHeader'];
		$defines['LAYOUT_TOPBAR']		= $this->skin."/".$layout_config[$tpl_path]['layoutTopBar'];
		$defines['LAYOUT_MAIN_TOPBAR']	= $this->skin."/".$layout_config[$tpl_path]['layoutMainTopBar'];
		$defines['LAYOUT_FOOTER'] 		= $this->skin."/".$layout_config[$tpl_path]['layoutFooter'];
		$defines['LAYOUT_SIDE'] 		= $this->skin."/".$layout_config[$tpl_path]['layoutSide'];


		$this->template->define($defines);

		/* 디자인모드일때 이미지태그에 tpl속성 추가 */
		if($this->designMode) {
			$this->template->compile_dir	= BASEPATH."../_compile/design";
			$this->template->prefilter		= "addImageAttributesBefore | ".$this->template->prefilter." | addImageAttributes";

			/* 컴파일 디렉토리가 없으면 생성 */
			if(!is_dir($this->template->compile_dir)){
				@mkdir($this->template->compile_dir);
				@chmod($this->template->compile_dir,0777);
			}
		}

		if( $this->isdemo['isdemo'] && !($_GET['popup'] || $_GET['iframe'] || $_GET['mobileAjaxCall']) ) {
			if($this->mobileMode){
				echo '<div id="layout_demo" style="width:320px;background:url(\''.get_connet_protocol().$_SERVER['HTTP_HOST'].'/admin/skin/default/images/design/warning_bg_m.png\') repeat;vertical-align:middle;height:30px;" align="center"><div><img src="/admin/skin/default/images/design/warning_txt_m.png"  style="margin:5px;" width="290"  width="18"></div></div>';
			}else{
				echo '<div id="layout_demo" style="width:'.$layout_config[width].';background:url(\''.get_connet_protocol().$_SERVER['HTTP_HOST'].'/admin/skin/default/images/design/warning_bg.png\') repeat;vertical-align:middle;height:60px;" align="center"><div><img src="/admin/skin/default/images/design/warning_txt.png"  style="margin:10px;"></div></div>';
			}
		}

		if(($this->config_system['operation_type'] == 'light' || $this->mobileMode) && !empty($_GET['mobileAjaxCall'])){
			/* 모바일모드에서 AJAX호출할때 컨텐츠부분만 출력 */
			$this->template->assign(array('mobileAjaxCall'=>$_GET['mobileAjaxCall']));
			$this->template->print_('LAYOUT_BODY');
		}else{
			$this->template->print_('LAYOUT');
		}

		// 도메인 환경 체크@2015.10
		$this->load->library('dbenvironment');
		$chkDBEnv = $this->dbenvironment->checkDBEnvironment($this->config_system['shopSno']);
		if( !$chkDBEnv[0] ){
			echo "<script type='text/javascript'>$.get('../_firstmallplus/env_firstmallplus', function(data){});</script>";
		}

		/* 카카오톡 인앱 브라우저에서 자동로그인 기능 미사용 (필요 시 주석제거하여 사용 가능)
		$this->load->library('snssocial');
		$this->snssocial->kakaosyncAutoLogin();
		*/

		//회원전용 쿠폰팝업용 생일자/기념일/회원 등급 조정 쿠폰/회원 등급 조정 쿠폰 (배송비)
		if ( ( (isset($this->userInfo['coupon_birthday_count']) || isset($this->userInfo['coupon_anniversary_count']) || isset($this->userInfo['coupon_membergroup_count']) ) && !$_GET['popup'] && ( !in_array('promotion',$this->uri->rsegments)  &&  !in_array('coupon',$this->uri->rsegments) ) ) || ($_GET['previewlayer'] && $this->managerInfo) ) {
			/* 쿠폰팝업 */
			if($_GET['previewlayer'] && $this->managerInfo) {
				$couponpopup[] = $_GET['previewlayer'];
			}else{
				$couponpopup = array("birthday","anniversary","membergroup");
			}
			$num = $indexpoup =0;
			foreach($couponpopup as $coupontypenew) {
				 $popup_key = "designPopupcoupon_".$coupontypenew;
				if( ($this->userInfo['coupon_'.$coupontypenew.'_count']>0 && !( $this->input->cookie($popup_key)=='1' || (time()-$this->input->cookie($popup_key) < 86400))) || ($_GET['previewlayer'] && $this->managerInfo) ) {//오늘하루 그만보기 체크된경우
					if( $this->mobileMode && $indexpoup == 1 ) break;
					if (!$this->mobileMode && $_GET['iframe']==1
						|| in_array($this->uri->uri_string,array('member/adult_auth','intro/adult_only'))) {
						break;
					}
			echo '<script type="text/javascript">
					//레이어띄우기
					$.ajax({
						"url": "/promotion/coupon_'.$coupontypenew.'",
						"data" : {"popup":"1","layer":"1","leftnum":"'.$num.'","previewlayer":"'.$_GET['previewlayer'].'"},
						success: function(result){
							if(result) $("body").prepend(result);
						';
					if($this->managerInfo) {
							echo 'setDesignElementEvent("body","");';
					}
			echo	'
						}
					});
					</script>';
					$indexpoup++;
					$num+=100;
				}
			}//endforeach
		}

		// 앱 설치 권장 팝업 - 모바일 기기 접속시에만..
		$app_agent	= checkUserApp(getallheaders());
		if($app_agent){
			echo '
			<script type="text/javascript">
			$("#layout_footer").find(".fnb").find("li > a").each(function (){
				if($(this).attr("href") == "../common/mobile_mode_off"){
					$(this).closest("li").hide();
				}
			});
			</script>';
		}else{
			if ($this->mobileMode && $this->_is_mobile_agent) {			
				$this->app_install_popup(); // 모바일 웹 접속시 앱설치 권장 팝업 오픈
			}
		}
		if ($this->config_system['facebook_pixel_use'] == 'Y') {
		    //facebook pixel
		    $fbq = "";
		    $fbq .= "<script>";
		    $fbq .= "$('#topSearchForm').submit(function() {";
		    $fbq .= " var search_string = $('#searchVer2InputBox').val();";
		    $fbq .= " fbq('track', 'Search', {";
		    $fbq .= "     search_string: search_string";
		    $fbq .= " });";
		    $fbq .= "});";
		    $fbq .= "</script>";

		    echo $fbq;
		}
	}



	public function operating_check(){
		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		// 운영방식 모바일(태블릿) 추가 2014-05-22 leewh
		if (!isset($basic['intro_m_use']) || $this->config_system['operation_type'] == 'light') { // 일반
			$basic['intro_m_use'] = ($basic['intro_use']) ? $basic['intro_use'] : 'N';
		}
		if (!isset($basic['general_m_use']) || $this->config_system['operation_type'] == 'light') { // 일반 모바일 운영 여부
			$basic['general_m_use'] = ($basic['general_use']) ? $basic['general_use'] : 'N';
		}
		if (!isset($basic['member_m_use']) || $this->config_system['operation_type'] == 'light') { // 회원 전용
			$basic['member_m_use'] = ($basic['member_use']) ? $basic['member_use'] : 'Y';
		}
		if (!isset($basic['adult_m_use']) || $this->config_system['operation_type'] == 'light') { // 성인 전용
			$basic['adult_m_use'] = ($basic['adult_use']) ? $basic['adult_use'] : 'Y';
		}

		$use_yn = $basic['operating']."_use";
		$operating = $basic[$use_yn];

		//모바일/태블릿
		$is_mobile = false;
		if($this->mobileMode || $this->storemobileMode){
			$is_mobile = true;
		}

		$operating_mobile = $basic[$basic['operating']."_m_use"];

		switch($basic['operating']){
			case "general":
				if(!$basic['intro_use']) $basic['intro_use'] = "N";
				if(!$is_mobile && $basic['intro_use']=="N" && $operating=="N"
					|| $is_mobile && $basic['intro_m_use']=="N" && in_array($operating_mobile, array('N', 'P'))){		// 메인페이지 정상
					if($is_mobile && $operating_mobile=='P') {
						$this->operating_set_skin();
					}
					continue;
				}
				else if(!$is_mobile && $basic['intro_use']=="N" && $operating=="Y"
					|| $is_mobile && $basic['intro_m_use']=="N" && $operating_mobile=="Y"){	// U:공사중, A:메인
					if(!$this->managerInfo && !$this->providerInfo) $this->operating_general_process();
				}
				else if(!$is_mobile && $basic['intro_use']=="Y" && $operating=="N"
					|| $is_mobile && $basic['intro_m_use']=="Y" && in_array($operating_mobile, array('N', 'P'))){	// 인트로
					if(in_array('main',$this->uri->rsegments) && !$this->session->userdata('intro')){
						 $this->operating_intro_process();
					}
					if($is_mobile && $operating_mobile=='P') {
						if( $basic['general_use'] == 'Y' ) {//pc 공사중사용시
							$this->operating_general_process();
						}else{
							$this->operating_set_skin();
						}
					}
				}
				else if(!$is_mobile && $basic['intro_use']=="Y" && $operating=="Y"
					|| $is_mobile && $basic['intro_m_use']=="Y" && $operating_mobile=="Y"){	//
					if(!$this->managerInfo && !$this->providerInfo){
						$this->operating_general_process();
					}else{
						if(in_array('main',$this->uri->rsegments) && !$this->session->userdata('intro')){
							 $this->operating_intro_process();
						}
					}
				}
				break;
			case "member":
				if(!$this->managerInfo && !$this->providerInfo){
					if(!$is_mobile && $operating=='Y' || $is_mobile && in_array($operating_mobile, array('Y', 'P'))) {
						if($is_mobile && $operating_mobile=='P') {
							$this->operating_set_skin();
						}
						$this->operating_member_process();
					}else {
						$this->operating_general_process();
					}
				}
				break;
			case "adult":
				if(!$this->managerInfo && !$this->providerInfo){
					if(!$is_mobile && $operating=='Y' || $is_mobile && in_array($operating_mobile, array('Y', 'P'))) {
						if($is_mobile && $operating_mobile=='P') {
							$this->operating_set_skin();
						}
						$this->operating_adult_process();
					} else {
						$this->operating_general_process();
					}
				}
				break;
		}
	}

	/**
	 * 결제 실패로 처리되어 인트로 사용 중 일 때 예외처리 함수
	 * 공사중, 인트로, 회원전용, 성인전용 공통 check 함수
	*/
	protected function operating_check_process($type) 
	{
		$check = true;

		// 인트로를 제외하고자 하는 url
		$exclude_url = [
			'order',
			'payment',
			'lg_mobile',
			'inicis_mobile',
			'allat_mobile',
			'kcp_mobile',
			'naver_mileage',
			'naverpay',
			'sns',
			'kakaotalk',
			'link',
			'payco',
		];

		foreach ($exclude_url as $exclude_url_name) {
			if (in_array($exclude_url_name,$this->uri->rsegments)) {
				$check = false;
				return $check;
			}
		}

		if($type == "intro") {
			if(in_array('common',$this->uri->rsegments)) $check = false;
			if(in_array('member',$this->uri->rsegments)) $check = false;
			if(in_array('partner',$this->uri->rsegments)) $check = false;
		} else if($type == "general") {
			if(in_array('common',$this->uri->rsegments)) $check = false;
			if(in_array('register_sns_form',$this->uri->rsegments)) $check = false;
			if(in_array('partner',$this->uri->rsegments)) $check = false;
		} else if($type == "member") {
			if(in_array('register_sns_form',$this->uri->rsegments)) $check = false;
			if(in_array('partner',$this->uri->rsegments)) $check = false;
		} else if($type == "adult") {
			if(in_array('register_sns_form',$this->uri->rsegments)) $check = false;
		}

		return $check;
	}

	/* 공사중 */
	public function operating_general_process(){

		if(!$this->operating_check_process("general")) return;

		if(!in_array('intro',$this->uri->rsegments) && !in_array('sns_process',$this->uri->rsegments)){
			redirect("intro/construction");
			exit;
		}
	}

	/* 인트로 */
	public function operating_intro_process(){

		if(!$this->operating_check_process("intro")) return;

		if(!in_array('intro',$this->uri->rsegments) && !in_array('sns_process',$this->uri->rsegments)){
			redirect("intro/intro_main?".$_SERVER['QUERY_STRING']);
			exit;
		}
	}

	/* 회원전용 */
	public function operating_member_process(){

		if(isset($this->userInfo['member_seq'])) return;
		if(!$this->operating_check_process("member")) return;

		if(!in_array('common',$this->uri->rsegments) && !in_array('intro',$this->uri->rsegments) && !in_array('member',$this->uri->rsegments) && !in_array('member_process',$this->uri->rsegments) && !in_array('sns_process',$this->uri->rsegments) && !in_array('login',$this->uri->rsegments) && !in_array('login_process',$this->uri->rsegments) && !in_array('popup',$this->uri->rsegments)){
		    // og tag 노출을 위해 301 redirect로 개선  2019-06-25  Sunha Ryu
		    redirect("/intro/member_only?return_url=".urlencode($_SERVER["REQUEST_URI"]), 301);
			exit;
		}
	}

	/* 성인전용 */
	public function operating_adult_process(){

		if(!$this->operating_check_process("adult")) return;

		if($this->session->userdata('auth_intro')){
			$auth_intro = $this->session->userdata('auth_intro');
			if($auth_intro['auth_intro_yn']=='Y') return;
		}

		if(!in_array('intro',$this->uri->rsegments) && !in_array('member',$this->uri->rsegments) && !in_array('member_process',$this->uri->rsegments)  && !in_array('sns_process',$this->uri->rsegments)  && !in_array('login',$this->uri->rsegments) && !in_array('login_process',$this->uri->rsegments) && !in_array('popup',$this->uri->rsegments)){
			pageRedirect("/intro/adult_only?return_url=".$_SERVER["REQUEST_URI"]);
			exit;
		}
	}

	public function operating_set_skin(){

		//운영중(PC와 동일) 일때 통계체크용 추가@2017-07-12
		$this->mobileMode_pc = false;
		// PC 스킨으로 설정 여부
		if (!$this->designMode) {
			if ($this->mobileMode) {
				$this->mobileMode = false;
				$this->mobileMode_pc = true;
				$this->skin = $this->config_system['skin'];
				if($this->session->userdata('setMode')!='pc') $this->session->set_userdata('setMode','pc');
			} else if ($this->storemobileMode) {
				$this->storemobileMode = false;
				$this->skin = $this->config_system['storeSkin'];
			}

			$this->realSkin = $this->skin;
			$this->workingSkin = $this->skin;
			$this->template->assign(array('skin'=>$this->skin));
		}
	}

	// 모바일 기기 접속인 경우 앱 설치 권장 팝업 노출 여부 설정
	public function app_install_popup() {
		
		$popupuse = getMobilePopupUse(); // 앱 설치 권장 팝업 노출 여부 설정	
		
		if ($popupuse === false) { 
			
			$mobile_agent_info = getMobilePopupInfo(); // 앱 설치 권장 팝업 정보 가져오기

			if ($mobile_agent_info['app_popup_use'] == 'Y' && $mobile_agent_info['down_url']) {
				echo $this->print_popuplayer($mobile_agent_info);
			}
		}

	}

	// 앱권장 팝업 레이어 출력
	public function print_popuplayer($mobile_agent_info) {

		// 주소 치환
		$pop_html = str_replace("appClosepopup('set');","appClosepopup('".$mobile_agent_info['down_url']."');",$mobile_agent_info['pop_html']);

		$apppop =  '<link rel="stylesheet" type="text/css" href="/admin/skin/default/css/mobile_app.css" />';
		$apppop .= '<div class="appPopup_lay" style="margin-left:50%;">';
		$apppop .= '<div class="appPopupBody" style="width:640px;height:350px;position:fixed;z-index:99999;top:150px;margin-left:-150px;">';
		$apppop .=  $pop_html;
		$apppop .= '</div>';
		$apppop .= '</div>';
		$apppop .= '<div id="appPopupModalBack" style="background: rgb(0, 0, 0); position:fixed;left:0px;top:0px;width:100%;height:100%;opacity: 0.5;z-index:99998;"></div>';

		// 표현부
		return $apppop;
	}

}

// 커스텀 파일이 있는 경우 커스텀파일에서 현파일을 로딩하여 상속 받아 사용한다.
if(!customBaseCall(__FILE__)) { class front_base extends front_base_original {} }

// END
/* End of file front_base.php */
/* Location: ./app/base/front_base.php */