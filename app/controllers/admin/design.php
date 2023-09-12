<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class design extends admin_base {

	var $realSkin;
	var $workingSkin;

	var $realMobileSkin;
	var $workingMobileSkin;

	var $realFammerceSkin;
	var $workingFammerceSkin;

	var $designWorkingSkin;
	var $folders;

	public function __construct() {
		parent::__construct();
		$this->load->helper('design');
		$this->load->model('layout');
		$this->load->helper('text');
		$this->load->model('designmodel');
		$this->load->library('validation');

		$this->template->assign(array('realSkin'=>$this->realSkin));
		$this->template->assign(array('workingSkin'=>$this->workingSkin));

		$this->template->assign(array('realMobileSkin'=>$this->realMobileSkin));
		$this->template->assign(array('workingMobileSkin'=>$this->workingMobileSkin));

		$this->template->assign(array('realFammerceSkin'=>$this->realFammerceSkin));
		$this->template->assign(array('workingFammerceSkin'=>$this->workingFammerceSkin));

		$this->template->assign(array('designWorkingSkin'=>$this->designWorkingSkin));

		$this->template->assign(array('mobileMode'=>$this->mobileMode));
		$this->template->assign(array('arrSns'=>$this->arrSns));

		// 스킨의 영역별 폴더 구분
		$this->folders = $this->layout->get_folders_in_skin();

		// 웹FTP 템플릿 define
		$this->template->define(array('webftp'=>$this->skin.'/webftp/_webftp.html'));
		$this->template->define(array('mini_webftp'=>$this->skin.'/webftp/_mini_webftp.html'));

		// 페이지목록에서 숨길 파일 리스트
		$this->hidden_page_list = array(
			'goods/autocomplete.html',
			'goods/brand_list.html',
			'goods/contents.html',
			'goods/coupon_location_ajax.html',
			'goods/personal.html',
			'goods/qna_view_layer.html',
			'goods/restock_notify_apply.html',
			'goods/review_view_layer.html',
			'goods/user_select.html',
			'goods/user_select_list.html',
			'goods/view_location.html',
			'goods/view_review.html',
			'goods/zoom.html',
			'member/popup_change_pass.html',
			'member/recommend.html',
			'member/register_form.html',
			'member/register_sns_form.html',
			'mypage/buy_confirm.html',
			'mypage/buy_gift.html',
			'mypage/catalog_top.html',
			'mypage/coupon_use.html',
			'mypage/coupon_view.html',
			'mypage/export_list.html',
			'mypage/export_list_coupon.html',
			'mypage/export_list_goods.html',
			'mypage/export_view.html',
			'mypage/gift_use_log.html',
			'mypage/individual.html',
			'mypage/my_coupon_detail.html',
			'mypage/my_coupon_use.html',
			'mypage/myfbrecommend.html',
			'mypage/mygdqna_view_ajax.html',
			'mypage/mygdqna_view_layer.html',
			'mypage/mygdreview_view_ajax.html',
			'mypage/mygdreview_view_layer.html',
			'mypage/myqna_view_ajax.html',
			'mypage/order_exchange.html',
			'mypage/order_refund.html',
			'mypage/order_return.html',
			'mypage/order_return_coupon.html',
			'mypage/order_view_summary.html',
			'mypage/taxwrite.html',
			'order/consumer_cart.html',
			'order/consumer_settle.html',
			'order/goods_select.html',
			'order/individual_settle.html',
			'order/optional.html',
			'order/order_admin_option.html',
			'popup/issue_list.html',
			'popup/minishop_reg.html',
			// 미노출 추가 :: 2018-10-17 pjw
			'etc/flashview.html',
			'goods/category_list.html',
			'goods/goods_display_all.html',
			'goods/hop_calendar_pop.html',
			'goods/navi.html',
			'goods/search_list.html',
			'goods/shipping_detail_info.html',
			'goods/store_map_info.html',
			'goods/view_contents.html',
			'goods/view_snipet.html',
			'intro/adult_only.html',
			'layout_footer/intro_footer.html',
			'layout_header/intro_header.html',
			'layout_header/mypage.html',
			'main/guide.html',
			'member/auth_chk.html',
			'member/register_gate.html',
			'mypage/myreserve_catalog.html',
			'mypage/myreserve_view.html',
			'mypage/myreserve_write.html',
			'mypage/myreview_catalog.html',
			'mypage/myreview_view.html',
			'mypage/myreview_write.html',
			'mypage/tax_receipt_view.html',
			'order/pop_delivery_address.html',
			'popup/addressbook.html',
			'popup/addressbook_write.html',
			'promotion/goods_code_list.html',
			'promotion/event_list.html',
			'service/cancellation.html',
			'service/policy.html',
		);

		// 메뉴 순서 (새파일 추가나 순서 변경 시 이 부분만 수정하면 됨) :: 2018-10-17 pjw
		$this->menuOrderList = array(
			"main"				=>	array("main/index.html" => "메인화면"),
			"layouy_all"		=> array(),
			"layout_header"		=>	array("layout_header/standard.html" => "상단 기본형"),
			"layout_MainTopBar"	=>	array("layout_MainTopBar/standard.html" => "메인 기본형"),
			"layout_TopBar"		=>	array("layout_TopBar/standard.html" => "상단 메뉴바 A형","layout_TopBar/standard2.html" => "상단 메뉴바 B형"),
			"layout_scroll"		=>	array("layout_scroll/left.html" => "좌측 스크롤","layout_scroll/left2.html" => "좌측 사이드 메뉴","layout_scroll/right.html" => "우측 스크롤","layout_scroll/right2.html" => "우측 사이드 메뉴"),
			"layout_side"		=>	array("layout_side/standard.html" => "측면 기본형","layout_side/mypage.html" => "측면 마이페이지형","layout_side/cs.html" => "측면 고객센터형"),
			"layout_footer"		=>	array("layout_footer/standard.html" => "하단 기본형"),
			"member"			=>	array("member/login.html" => "로그인","member/adult_auth.html" => "성인인증", "member/join_gate.html"=>"가입방법 선택", "member/agreement.html" => "이용약관 동의","member/register.html" => "회원정보 입력","member/register_ok.html" => "회원가입 완료","member/find.html" => "아이디/비밀번호 찾기","member/dormancy_auth.html" => "휴면해제"),
			"goods"				=>	array("goods/catalog.html" => "카테고리 상품","goods/brand.html" => "브랜드 상품","goods/brand_main.html" => "브랜드 메인","goods/location.html" => "지역 상품","goods/search.html" => "상품 검색","goods/view.html" => "상품 상세","goods/best.html" => "베스트 상품","goods/new_arrivals.html" => "신상품","goods/recently.html" => "최근 본 상품"),
			"bigdata"			=>	array("bigdata/catalog.html" => "빅데이터 상품추천"),
			"mshop"				=>	array("mshop/index.html" => "판매자 미니샵"),
			"order"				=>	array("order/cart.html" => "장바구니","order/settle.html" => "주문/결제","order/complete.html" => "결제완료"),
			"popup"				=>	array("popup/zipcode.html" => "주소찾기"),
			"mypage"			=>	array("mypage/index.html" => "마이페이지 메인", "mypage/mypage_lnb.html" => "마이페이지 LNB" ,"mypage/order_catalog.html" => "주문/배송","mypage/order_view.html" => "주문/배송 상세","mypage/wish.html" => "위시리스트","mypage/taxinvoice.html" => "세금계산서","mypage/personal.html" => "개인결제","mypage/delivery_address.html" => "배송지 주소록","mypage/return_catalog.html" => "반품/교환","mypage/return_view.html" => "반품/교환 상세","mypage/refund_catalog.html" => "취소/환불","mypage/refund_view.html" => "취소/환불 상세","mypage/my_minishop.html" => "나의 단골 미니샵","mypage/emoney.html" => "마일리지","mypage/coupon.html" => "쿠폰","mypage/offlinecoupon.html" => "쿠폰 등록","mypage/cash.html" => "예치금","mypage/point.html" => "포인트","mypage/point_exchange.html" => "포인트 마일리지 교환","mypage/promotion.html" => "포인트 할인코드 교환", "mypage/emoney_exchange.html" => "마일리지 사은품 교환", "mypage/myqna_catalog.html" => "1:1문의","mypage/myqna_write.html" => "1:1문의 쓰기","mypage/myqna_view.html" => "1:1문의 보기","mypage/mygdqna_catalog.html" => "나의 상품문의","mypage/mygdqna_write.html" => "나의 상품문의 쓰기","mypage/mygdqna_view.html" => "나의 상품문의 보기","mypage/mygdreview_catalog.html" => "나의 상품후기","mypage/mygdreview_write.html" => "나의 상품후기 쓰기","mypage/mygdreview_view.html" => "나의 상품후기 보기","mypage/myinfo.html" => "회원정보 수정","mypage/withdrawal.html" => "회원 탈퇴"),
			"service"			=>	array("service/cs.html" => "고객센터 메인","service/company.html" => "회사소개","service/agreement.html" => "이용약관","service/privacy.html" => "개인정보처리방침", "service/guide.html" => "이용안내","service/partnership.html" => "제휴안내"),
			"promotion"			=>	array("promotion/event.html" => "이벤트 메인","promotion/event_view.html" => "기획전/할인이벤트", "promotion/gift_view.html" => "사은품 이벤트", "promotion/coupon_anniversary.html" => "기념일 쿠폰","promotion/coupon_birthday.html" => "생일자 쿠폰","promotion/coupon_member.html" => "신규가입 쿠폰","promotion/coupon_membergroup.html" => "회원등급 조정 쿠폰","promotion/coupon_memberlogin.html" => "컴백회원 쿠폰","promotion/coupon_membermonths.html" => "이달의 등급 쿠폰","promotion/coupon_order.html" => "첫 구매 쿠폰","promotion/coupon_shipping.html" => "배송비 쿠폰"),
			"joincheck"			=>	array("joincheck/comment_basic.html" => "댓글형출석체크 Basic형","joincheck/comment_simple.html" => "댓글형출석체크 Simple형","joincheck/stamp_basic.html" => "출석체크 Basic형","joincheck/stamp_simple.html" => "출석체크 Simple형"),
			"intro"				=>	array("intro/construction.html" => "공사중","intro/denined_ip.html" => "아이피차단","intro/intro_main.html" => "인트로","intro/member_only.html" => "회원전용"),
			"errdoc"			=>	array("errdoc/404.html" => "404 에러페이지"),
			"etc"				=>	array()
		);

		// 반응형일 때 숨김처리 파일 추가 :: 2019-04-10 pjw
		if($this->config_system['operation_type'] == 'light'){

			// 메뉴숨김
			unset($this->menuOrderList['layout_MainTopBar']);
			unset($this->folders['layout_MainTopBar']);

			// 숨김파일
			$this->hidden_page_list[] = 'layout_MainTopBar/standard.html';
			$this->hidden_page_list[] = 'goods/qna_catalog.html';
			$this->hidden_page_list[] = 'goods/qna_view.html';
			$this->hidden_page_list[] = 'goods/qna_write.html';
			$this->hidden_page_list[] = 'goods/review_catalog.html';
			$this->hidden_page_list[] = 'goods/review_view.html';
			$this->hidden_page_list[] = 'goods/review_write.html';
		}

		// 아이디자인 판넬 메뉴별 분기파일 정의 :: 2019-04-09 pjw
		/*
			folder_list : 폴더명이 일치하는 지 검사
			file_list	: 폴더 + 파일명이 일치하는 지 검사
			preg_list	: 폴더 + 파일명을 정규식으로 검사
			url			: 해당 타입에 공통으로 쓸 url
		*/
		$this->menuActionList = array(

			'layout'	=> array(
				'folder_list'	=> array('layout_MainTopBar', 'layout_header','layout_footer','layout_side','layout_scroll'),
			),
			'noview'	=> array(
				'folder_list'	=> array('joincheck'),
				'file_list'		=> array('mypage/mypage_lnb.html', 'goods/qna_catalog.html', 'goods/qna_write.html', 'goods/qna_view.html', 'goods/review_catalog.html', 'goods/review_write.html', 'goods/review_view.html'),
			),
			'noview_sub'	=> array(
				'preg_list'		=> array("/view\.html$/"),
			),
			'nologin'	=> array(
				'preg_list'		=> array("/^mypage\//"),
			),
			'settle'	=> array(
				'file_list'		=> array('order/settle.html'),
			),
			'goods_view'	=> array(
				'file_list'		=> array('goods/view.html'),
			),
			'_blank'	=> array(
				'folder_list'	=> array('layout_TopBar','layout_MainTopBar'),
				'url'			=> 'http://manual.firstmall.kr/manual/view?category=00110018',
			),
		);

		$sConProtocol = get_connet_protocol();
		$this->frontUrls['pc'] = $sConProtocol . $this->pcDomain . "/?setDesignMode=on&setMode=pc";
		$this->frontUrls['mobile'] = $sConProtocol . $this->mobileDomain . "/?setDesignMode=on&setMode=mobile";
		$this->frontUrls['fammerce'] = $sConProtocol . $this->pcDomain . "/?setDesignMode=on&setMode=fammerce";
	}

	// 아이디자인 판녈 메뉴 타입 검사 :: 2019-04-10 pjw
	/*
		패널의 메뉴별로 기능이 다르고 조건문이 복잡하여
		"폴더명, 폴더+파일명, 정규식" 부분만 따로 변수처리하여 검사함
		게시판의 경우 간결하여 전역변수에 포함하지 않음
	*/
	public function check_menu_type($chktype = 'normal', $child, $file){

		// 해당 타입 데이터
		$action		= $this->menuActionList[$chktype];
		$tpl_path	= $child . '/' . $file;

		// 폴더 검사
		if(!empty($action['folder_list']) && in_array($child, $action['folder_list'])){
			return true;
		}

		// 파일경로 검사
		if(!empty($action['file_list']) && in_array($tpl_path, $action['file_list'])){
			return true;
		}

		// 정규식 검사
		if(!empty($action['preg_list'])){

			// 정규식 배열만큼 정규식 판별
			foreach($action['preg_list'] as $preg_exp){
				if( preg_match($preg_exp, $tpl_path) ){
					return true;
				}
			}
		}

		return false;
	}

	public function index()
	{
		redirect("/admin/design/skin");
	}

	/* 스킨설정 */
	public function skin(){
		$this->admin_menu();
		$this->tempate_modules();

		$cfg_system	= ($this->config_system) ? $this->config_system : config_load('system');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('skin');

		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		// [반응형스킨] 스킨타입 추가 {responsive,fixed} :: 2018-10-31 pjw
		$skin_type = !empty($cfg_system['skin_type']) ? $cfg_system['skin_type'] : 'fixed';

		/* 모바일스킨 프리픽스 */
		// [반응형스킨] 스킨타입에 따라 프리픽스 변경 :: 2018-10-31 pjw
		if($skin_type == 'fixed' || $skin_type == 'responsive2') $skinPrefix = !empty($_GET['prefix']) ? $_GET['prefix'] : '';
		else													 $skinPrefix = 'responsive';

		$this->template->assign(array('skinType' => $skin_type, 'skinPrefix'=>$skinPrefix));

		/* 스킨박스 사이즈 assign */
		$this->template->assign(array(
			"skin_apply_box_width"	=>	206,
			"skin_apply_box_height"	=>	260,
		));

		/* 실적용스킨,작업용스킨 configuration assign */
		if($skinPrefix=='mobile'){
			$this->template->assign(array('realSkinConfiguration'=>skin_configuration($this->realMobileSkin)));
			$this->template->assign(array('workingSkinConfiguration'=>skin_configuration($this->workingMobileSkin)));
		}elseif($skinPrefix=='fammerce'){
			$this->template->assign(array('realSkinConfiguration'=>skin_configuration($this->realFammerceSkin)));
			$this->template->assign(array('workingSkinConfiguration'=>skin_configuration($this->workingFammerceSkin)));
		}else{
			$this->template->assign(array('realSkinConfiguration'=>skin_configuration($this->realSkin)));
			$this->template->assign(array('workingSkinConfiguration'=>skin_configuration($this->workingSkin)));
		}

		$this->template->assign(array('cfg_system'	=> $cfg_system));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 스킨추가 */
	public function skin_add(){
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->helper('readurl');

		$cfg_system	= ($this->config_system) ? $this->config_system : config_load('system');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('skin');

		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		/* 실적용스킨,작업용스킨 configuration assign */
		if($skinPrefix=='mobile'){
			$this->template->assign(array('realSkinConfiguration'=>skin_configuration($this->realMobileSkin)));
			$this->template->assign(array('workingSkinConfiguration'=>skin_configuration($this->workingMobileSkin)));
		}elseif($skinPrefix=='fammerce'){
			$this->template->assign(array('realSkinConfiguration'=>skin_configuration($this->realFammerceSkin)));
			$this->template->assign(array('workingSkinConfiguration'=>skin_configuration($this->workingFammerceSkin)));
		}else{
			$this->template->assign(array('realSkinConfiguration'=>skin_configuration($this->realSkin)));
			$this->template->assign(array('workingSkinConfiguration'=>skin_configuration($this->workingSkin)));
		}

		$search_url = get_connet_protocol()."design.firstmall.kr/design/skin_add";

		// 반응형 스킨 탭 추가 파라미터 :: 2019-02-19 pjw
		$_SERVER['QUERY_STRING'] = "resp=1&".$_SERVER['QUERY_STRING'];

		if($_SERVER['QUERY_STRING'])	$search_url .= "?".$_SERVER['QUERY_STRING'];
		if( serviceLimit('H_FR')  ){
			if($_SERVER['QUERY_STRING']) $search_url .= "&service=FREE";
			else  $search_url .= "?service=FREE";
		}
		$search_html = readurl(urldecode($search_url));
		$search_html = replace_connect_protocol($search_html);

		$this->template->assign(
			array(
				'cfg_system'	=> $cfg_system,
				'search_html'	=> $search_html,
			)
		);
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 디자인환경 안내 */
	public function main(){
		$sSetMode = $this->input->get('setMode');

		if ( ! $sSetMode) {
			$sSetMode = 'pc';
		}

		if ($sSetMode == 'fammerce') {
			serviceLimit('H_FR', 'process');
		}

		$this->admin_menu();
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if (!$auth) {
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->template->assign('frontUrl', urlencode($this->frontUrls[$sSetMode]));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 디자인모드 세팅 후 프론트로 이동 */
	public function front_action()
	{
		$frontUrl = $this->input->get('frontUrl');
		if (in_array($frontUrl, array($this->frontUrls['pc'], $this->frontUrls['mobile'], $this->frontUrls['fammerce']))) {
			pageRedirect($frontUrl);
		} else {
			pageBack('경로가 올바르지 않습니다.');
		}
	}

	/* 디자인관리 패널 HTML 출력 */
	public function get_panel_html()
	{
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth) return;



		$template_path = $_GET['template_path'];
		// 작업스킨 경로
		$working_skin_path = ROOTPATH."data/skin/".$this->designWorkingSkin;

		$tpls = array();
		foreach($this->folders as $key=>$value){
			$titleimgdir = './admin/skin/'.$this->config_system['adminSkin'].'/images/design/icon_'.$key.'.gif';
			$tpls[$key] = array('name'=>$value, 'titleimg'=>$titleimgdir, 'childs'=>array($key));
		}

		if($template_path=='member/agreement.html'){
			$location = parse_url($_GET['location']);
			if(!preg_match("/join_type=/",$location['query'])){
				$template_path='member/join_gate.html';
			}
		}

		if($this->config_system['operation_type'] == 'light' && $template_path=='promotion/event.html'){
			$template_path='promotion/event_list.html';
		}

		$layout_config		= layout_config_load($this->designWorkingSkin);
		$skin_configuration = skin_configuration($this->designWorkingSkin);

		// 회원 로그인 데이터
		$user_data = $this->session->userdata('user');

		// 스킨파일목록
		foreach($tpls as $i=>$directory){
			$tpls[$i]['files'] = array();
			foreach($tpls[$i]['childs'] as $child){
				$map = directory_map($working_skin_path."/".$child,true,false);
				sort($map);

				foreach((array)$map as $file){

					$tpl_path	= $child."/".$file;
					$file_path	= $working_skin_path."/".$tpl_path;

					// html파일이 아닌 경우 처리안함
					if(!preg_match("/\.html$/",$file)) continue;

					// 실제 파일이 있는지 여부 검사
					if(is_file($file_path)) {

						// 언더바로 시작하는 파일 미표시
						if(preg_match("/^_/",basename($file_path)))		continue;

						// 미표시파일 목록 처리
						if(in_array($tpl_path,$this->hidden_page_list)) continue;

						# 메뉴별 기능 설정
						$child_data		= $layout_config[$child."/".$file];
						$url			= "#";
						$file_type		= 'normal';
						$file_type_msg	= '';

						$tpl_desc = !empty($child_data['tpl_desc']) ? $child_data['tpl_desc'] : $child."/".$file;
						$tpl_page = !empty($child_data['tpl_page']) ? true : false;

						// 각 타입별 분기 처리
						## 레이아웃 파일
						if( $this->check_menu_type('layout', $child, $file) ){

							$file_type		= 'layout';
							$file_type_msg	= '별도의 미리보기 화면이 필요 없는 특별한 레이아웃 영역입니다<br />상단/하단/측면/스크롤의 영역은 해당 영역이 보이는 페이지에서 바로 EYE-DESIGN하세요.';

						}
						## 상품상세 파일
						elseif( $this->check_menu_type('goods_view', $child, $file) ) {

							$this->load->model('goodsmodel');
							$query		= $this->db->query("select goods_seq from fm_goods order by goods_seq desc limit 1");
							$goods_seq	= $query->row_array();
							$goods_seq	= $goods_seq['goods_seq'];

							if(!empty($goods_seq)){
								$url			= "../goods/view?no={$goods_seq}&designMode=1";
							}else{
								$file_type		= 'goods_view';
								$file_type_msg	= '상품상세페이지를 정확하게 디자인하기 위해서 최소 1개의 상품을 등록해 주세요.<br />이제, 상품등록하고 상품상세페이지를 보면서 바로 EYE-DESIGN 하세요!';
							}

						}
						## 로그인이 필요한 파일
						elseif( $this->check_menu_type('nologin', $child, $file) && empty($user_data) ){

							$file_type		= 'nologin';
							$file_type_msg	= '로그인 후 이용하실 수 있습니다.';

						}
						## 주문정보 파일
						elseif( $this->check_menu_type('settle', $child, $file) ){

							$file_type		= 'settle';
							$file_type_msg	= '주문하기페이지를 정확하게 디자인하기 위해서는 테스트로 주문을 하면서 디자인 하는 것이 가장 좋습니다.<br />왜냐하면, 주문할 때는 회원,비회원,쿠폰,마일리지,배송비 등 매우 복잡한 경우를 모두 분석해서 주문이 이뤄지기 때문입니다.<br />이제, 주문페이지도 주문을 하면서 바로 EYE-DESIGN 하세요!';

						}
						## 미리보기 없는 파일
						elseif( $this->check_menu_type('noview', $child, $file) ){

							$url			= empty($this->menuActionList['noview']['url_list'][$tpl_path]) ? "#" : $this->menuActionList['noview']['url_list'][$tpl_path];
							$file_type		= 'noview';
							$file_type_msg	= '화면을 미리 확인할 수 없는 페이지입니다.';

						}
						## 특정 뷰 미리보기 없는 파일 & tpl 페이지여부
						elseif( $this->check_menu_type('noview_sub', $child, $file) && !$tpl_page ){

							$url			= empty($this->menuActionList['noview']['url_list'][$tpl_path]) ? "#" : $this->menuActionList['noview']['url_list'][$tpl_path];
							$file_type		= 'noview';
							$file_type_msg	= '화면을 미리 확인할 수 없는 페이지입니다.';

						}
						## 새 창 띄우는 파일
						elseif( $this->check_menu_type('_blank', $child, $file) ){

							$url			= "http://manual.firstmall.kr/manual/view?category=00110018";
							$file_type		= '_blank';

						}
						## 나머지 조건 (공통 관리 힘든 부분은 여기서 처리)
						else {

							if($this->config_system['operation_type'] == 'light' && $template_path=='promotion/event.html'){
								$template_path='promotion/event_list.html';
							}

							// 일반 버튼일 경우 해당하는 경로에 맞는 주소로 이동
							$url = $tpl_page ? $this->layout->get_tpl_page_url($tpl_path) : "/".substr($tpl_path,0,strpos($tpl_path,'.'))."?designMode=1";

							// 파일별로 url 달라지는 경우 분기처리
							if(preg_match("/tab_[0-9]*.html/",$file))		$url = $url = "../topbar/?no={$file}&designMode=1";
							elseif($tpl_path == 'member/join_gate.html')	$url = "/member/agreement";
							elseif($tpl_path == 'member/agreement.html')	$url = "/member/agreement?join_type=member";
						}

						$tpl_data = array(
							'path'		=>	$tpl_path,
							'desc'		=>	$tpl_desc,
							'url'		=>	$url,
							'file_type'	=>	$file_type,
							'file_type_msg' => $file_type_msg,
						);

						$tpls[$i]['files'][$tpl_path] = $tpl_data;

					}
				}
			}
		}

		// 메뉴 순서대로 다시 넣음 :: 2018-10-19 pjw
		$newtpls = array();
		foreach($this->menuOrderList as $menu=>$submenu){
			if(empty($tpls[$menu])){
				continue;
			}

			$newtpls[$menu]				= $tpls[$menu];
			$newtpls[$menu]['files']	= array();
			foreach($submenu as $sub => $subtitle){
				$tmp_sub			= $tpls[$menu]['files'][$sub];
				$tmp_sub['desc']	= !empty($subtitle) ? $subtitle : $tpls[$menu]['files'][$sub]['desc'];

				$newtpls[$menu]['files'][] = $tmp_sub;
				unset($tpls[$menu]['files'][$sub]);
			}
		}

		// DB에 추가된 예외 항목들 추가 :: 2018-10-19 pjw
		foreach($tpls as $menu=>$submenu){
			if(empty($newtpls[$menu])){
				$newtpls[$menu] = $submenu;
				$newtpls[$menu]['files'] = array();
			}
			foreach($submenu['files'] as $sub){
				$newtpls[$menu]['files'][] = $sub;
			}
			if($this->config_system['operation_type'] == 'light'){
				$template_replace = array('promotion/event.html'=>'promotion/event_list.html');

				foreach($newtpls[$menu]['files'] as $k => $v){
					$newtpls[$menu]['files'][$k]['path'] = array_key_exists($v['path'], $template_replace)==TRUE?$template_replace[$v['path']]:$v['path'];
				}
			}
		}

		$tpls = $newtpls;

		// 게시판 목록
		$this->db->select('seq,id,name,skin');
		$query = $this->db->get('fm_boardmanager');
		$boards = $query->result_array();
		foreach($boards as $i=>$row){
			// 예외처리 추가 :: 2018-10-19 pjw
			// 1:1문의, 상점후기 숨김처리 :: 2019-04-10 pjw
			$exp_id_list = array('gs_seller_notice', 'gs_seller_qna', 'naverpay_qna', 'mbqna', 'store_review');
			if(in_array($row['id'], $exp_id_list)){
				unset($boards[$i]);
				continue;
			}

			$child			= "board/".$row['id']."/".$row['skin'];
			$board_skin_dir = $working_skin_path."/".$child;
			$map			= directory_map($board_skin_dir,true,false);

			$boards[$i]['files'] = array();
			foreach((array)$map as $file){

				// html이 아닌경우 처리안함
				if(!preg_match("/\.html$/",$file)) continue;

				// view 파일 숨김처리
				if(preg_match("/commentview\.html$/",$file)) continue;

				// 공지사항만 쓰기페이지 숨김처리
				if($file == 'write.html' && $row['id'] == 'notice') continue;

				// 메뉴 기본값 설정
				$tpl_desc = '';
				$file_type = 'normal';
				$file_type_msg = '';

				if($file == 'write.html'){
					$url			= "/board/write?id=".$row['id'];
					$tpl_desc		= '글쓰기페이지';

				} elseif($file=='index.html'){
					$url			= "/board/?id=".$row['id'];
					$tpl_desc		= '리스트페이지';

				} elseif($file=='view.html'){
					$url			= "#";
					$file_type		= 'noview';
					$file_type_msg	= '화면을 미리 확인할 수 없는 페이지입니다.';
					$tpl_desc		= '상세페이지';

				} else {
					$url = "/board/?id=".$row['id'];
				}

				$tpl_path = $child."/".$file;
				$file_path = $working_skin_path."/".$tpl_path;

				if(is_file($file_path)) {

					// 페이지명이 없는경우 실제 파일명을 넣음
					if($tpl_desc == ''){
						$tpl_desc = $file;
					}

					$tpl_desc = !empty($layout_config[$child."/".$file]['tpl_desc']) ? $layout_config[$child."/".$file]['tpl_desc'] : $tpl_desc;

					$is_layout = false;

					$boards[$i]['files'][] =  array(
						'path'		=>	$tpl_path,
						'desc'		=>	$tpl_desc,
						'url'		=>	$url,
						'file_type' => $file_type,
						'file_type_msg' => $file_type_msg,
					);
				}
			}
		}


		$this->template->assign(array(
			"folders"=>$tpls,
			"boards"=>$boards,
			"skin_configuration"=>$skin_configuration,
		));
		/*아이디자인에서 유저로그인시 로그인 로그아웃 css id관련 추가*/
		$design_userlogin = ($this->session->userdata['user']) ? "Y" : "N";
		$this->template->assign('design_userlogin',$design_userlogin);
		/*end*/

		$this->template->assign('template_path',$template_path);
		$this->template->assign('css_path','css/user.css');
		$file_path	= $this->skin.'/design/_panel.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	/* 전체 페이지 보기 */
	public function all_pages()
	{
		/* tpl_path assign */
		$tpl_path = $_GET['tpl_path']?$_GET['tpl_path']:null;
		$this->template->assign(array('skin'=>$this->designWorkingSkin,'tpl_path'=>$tpl_path));

		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$working_skin_path = ROOTPATH."data/skin/".$this->designWorkingSkin;

		$layout_config = layout_config_load($this->designWorkingSkin);

		$tpls = array();
		foreach($this->folders as $key=>$value){
			$tpls[$key] = array('name'=>$value, 'childs'=>array($key) , 'icon'=>$key);
		}

		// 회원 로그인 데이터
		$user_data = $this->session->userdata('user');

		foreach($tpls as $i=>$directory){
			$tpls[$i]['files'] = array();
			foreach($tpls[$i]['childs'] as $child){
				$map = directory_map($working_skin_path."/".$child,true,false);
				sort($map);

				foreach((array)$map as $file){

					$tpl_path	= $child."/".$file;
					$file_path	= $working_skin_path."/".$tpl_path;

					if(is_file($file_path)) {

						// 언더바로 시작하는 파일 미표시
						if(preg_match("/^_/",basename($file_path)))		continue;

						// 미표시파일 목록 처리
						if(in_array($tpl_path,$this->hidden_page_list)) continue;

						# 메뉴별 기능 설정
						$child_data		= $layout_config[$child."/".$file];
						$url			= "#";
						$file_type		= 'normal';
						$file_type_msg	= '';

						$tpl_desc = !empty($child_data['tpl_desc']) ? $child_data['tpl_desc'] : $child."/".$file;
						$tpl_page = !empty($child_data['tpl_page']) ? true : false;


						// 각 타입별 분기 처리
						## 레이아웃 파일
						if( $this->check_menu_type('layout', $child, $file) ){

							$file_type		= 'layout';
							$file_type_msg	= '별도의 미리보기 화면이 필요 없는 특별한 레이아웃 영역입니다<br />상단/하단/측면/스크롤의 영역은 해당 영역이 보이는 페이지에서 바로 EYE-DESIGN하세요.';

						}
						## 상품상세 파일
						elseif( $this->check_menu_type('goods_view', $child, $file) ) {

							$this->load->model('goodsmodel');
							$query		= $this->db->query("select goods_seq from fm_goods order by goods_seq desc limit 1");
							$goods_seq	= $query->row_array();
							$goods_seq	= $goods_seq['goods_seq'];

							if(!empty($goods_seq)){
								$url			= "../goods/view?no={$goods_seq}&designMode=1";
							}else{
								$file_type		= 'goods_view';
								$file_type_msg	= '상품상세페이지를 정확하게 디자인하기 위해서 최소 1개의 상품을 등록해 주세요.<br />이제, 상품등록하고 상품상세페이지를 보면서 바로 EYE-DESIGN 하세요!';
							}

						}
						## 로그인이 필요한 파일
						elseif( $this->check_menu_type('nologin', $child, $file) && empty($user_data) ){

							$file_type		= 'nologin';
							$file_type_msg	= '로그인 후 이용하실 수 있습니다.';

						}
						## 주문정보 파일
						elseif( $this->check_menu_type('settle', $child, $file) ){

							$file_type		= 'settle';
							$file_type_msg	= '주문하기페이지를 정확하게 디자인하기 위해서는 테스트로 주문을 하면서 디자인 하는 것이 가장 좋습니다.<br />왜냐하면, 주문할 때는 회원,비회원,쿠폰,마일리지,배송비 등 매우 복잡한 경우를 모두 분석해서 주문이 이뤄지기 때문입니다.<br />이제, 주문페이지도 주문을 하면서 바로 EYE-DESIGN 하세요!';

						}
						## 미리보기 없는 파일
						elseif( $this->check_menu_type('noview', $child, $file) ){

							$url			= empty($this->menuActionList['noview']['url_list'][$tpl_path]) ? "#" : $this->menuActionList['noview']['url_list'][$tpl_path];
							$file_type		= 'noview';
							$file_type_msg	= '화면을 미리 확인할 수 없는 페이지입니다.';

						}
						## 특정 뷰 미리보기 없는 파일 & tpl 페이지여부
						elseif( $this->check_menu_type('noview_sub', $child, $file) && !$tpl_page ){

							$url			= empty($this->menuActionList['noview']['url_list'][$tpl_path]) ? "#" : $this->menuActionList['noview']['url_list'][$tpl_path];
							$file_type		= 'noview';
							$file_type_msg	= '화면을 미리 확인할 수 없는 페이지입니다.';

						}
						## 새 창 띄우는 파일
						elseif( $this->check_menu_type('_blank', $child, $file) ){

							$url			= "http://manual.firstmall.kr/manual/view?category=00110018";
							$file_type		= '_blank';

						}
						## 나머지 조건 (공통 관리 힘든 부분은 여기서 처리)
						else {

							// 일반 버튼일 경우 해당하는 경로에 맞는 주소로 이동
							$url = $tpl_page ? $this->layout->get_tpl_page_url($tpl_path) : "/".substr($tpl_path,0,strpos($tpl_path,'.'))."?designMode=1";

							// 파일별로 url 달라지는 경우 분기처리
							if(preg_match("/tab_[0-9]*.html/",$file))		$url = $url = "../topbar/?no={$file}&designMode=1";
							elseif($tpl_path == 'member/join_gate.html')	$url = "/member/agreement";
							elseif($tpl_path == 'member/agreement.html')	$url = "/member/agreement?join_type=member";
						}

						$tpls[$i]['files'][$tpl_path] = array(
							'path'		=>	$tpl_path,
							'desc'		=>	$tpl_desc,
							'url'		=>	$url,
							'tpl_page'	=>	$tpl_page,
							'file_type' => $file_type,
							'file_type_msg' => $file_type_msg,
						);
					}
				}
			}
		}

		// 메뉴 순서대로 다시 넣음 :: 2018-10-19 pjw
		$newtpls = array();
		foreach($this->menuOrderList as $menu=>$submenu){
			if(empty($tpls[$menu])){
				continue;
			}

			$newtpls[$menu]				= $tpls[$menu];
			$newtpls[$menu]['files']	= array();
			foreach($submenu as $sub => $subtitle){
				$tmp_sub			= $tpls[$menu]['files'][$sub];
				$tmp_sub['desc']	= !empty($subtitle) ? $subtitle : $tpls[$menu]['files'][$sub]['desc'];

				$newtpls[$menu]['files'][] = $tmp_sub;
				unset($tpls[$menu]['files'][$sub]);
			}
		}

		// DB에 추가된 예외 항목들 추가 :: 2018-10-19 pjw
		foreach($tpls as $menu=>$submenu){
			if(empty($newtpls[$menu])){
				$newtpls[$menu] = $submenu;
				$newtpls[$menu]['files'] = array();
			}
			foreach($submenu['files'] as $sub){
				$newtpls[$menu]['files'][] = $sub;
			}
		}

		$tpls = $newtpls;

		// 게시판 목록
		$this->db->select('seq,id,name,skin');
		$query = $this->db->get('fm_boardmanager');
		$boards = $query->result_array();
		foreach($boards as $i=>$row){
			// 예외처리 추가 :: 2018-10-19 pjw
			$exp_id_list = array('gs_seller_notice', 'gs_seller_qna', 'naverpay_qna', 'mbqna', 'store_review');
			if(in_array($row['id'], $exp_id_list)){
				unset($boards[$i]);
				continue;
			}

			$child = "board/".$row['id']."/".$row['skin'];
			$board_skin_dir = $working_skin_path."/".$child;
			$map = directory_map($board_skin_dir,true,false);

			$boards[$i]['files'] = array();
			foreach((array)$map as $file){

				if(!preg_match("/\.html$/",$file)) continue;
				if(preg_match("/commentview\.html$/",$file)) continue;
				if($file=='view.html' && $row['id']=='faq') continue;

				$tpl_desc		= '';
				$file_type		= 'normal';
				$file_type_msg	= '';

				if($file=='write.html'){
					$url			= "/board/write?id=".$row['id'];
					$tpl_desc		= '글쓰기페이지';

				} elseif($file=='index.html'){
					$url			= "/board/?id=".$row['id'];
					$tpl_desc		= '리스트페이지';

				} elseif($file=='view.html'){
					$url			= "#";
					$file_type		= 'noview';
					$file_type_msg	= '화면을 미리 확인할 수 없는 페이지입니다.';
					$tpl_desc		= '상세페이지';

				} else {
					$url = "/board/?id=".$row['id'];
				}

				$tpl_path = $child."/".$file;
				$file_path = $working_skin_path."/".$tpl_path;

				if(is_file($file_path)) {

					// 페이지명이 없는경우 실제 파일명을 넣음
					if($tpl_desc == ''){
						$tpl_desc = $file;
					}

					$tpl_desc = !empty($layout_config[$child."/".$file]['tpl_desc']) ? $layout_config[$child."/".$file]['tpl_desc'] : $tpl_desc;

					$is_layout = false;

					$boards[$i]['files'][] =  array(
						'path'		=>	$tpl_path,
						'desc'		=>	$tpl_desc,
						'url'		=>	$url,
						'file_type' => $file_type,
						'file_type_msg' => $file_type_msg,
					);
				}
			}

		}

		$this->template->assign(array(
			"folders_count"=>count($tpls),
			"folders"=>$tpls,
			"boards"=>$boards
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 레이아웃 설정 화면 */
	public function layout()
	{
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$mode = isset($_GET['mode']) ? $_GET['mode'] : null;

		switch($mode){
			case "create" :
				$title = "새 페이지 만들기";
				$this->template->assign(array('title'=>$title,'mode'=>$mode));

				/* tpl_path assign */
				$tpl_path = 'basic';
				$this->template->assign(array(
					'designWorkingSkin'=>$this->designWorkingSkin,
					'tpl_path'=>$tpl_path,
					'folders'=>$this->folders
				));

			break;
			case "edit" :
				$title = "레이아웃/폰트/배경색 설정";
				$this->template->assign(array('title'=>$title,'mode'=>$mode));

				/* tpl_path assign */
				$tpl_path = $_GET['tpl_path']?$_GET['tpl_path']:null;
				$this->template->assign(array('designWorkingSkin'=>$this->designWorkingSkin,'tpl_path'=>$tpl_path));

			break;
			default:
				exit;
			break;
		}

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_autoload($this->designWorkingSkin,$tpl_path);

		/* 레이아웃 영역별 설정 assign */
		$layout_header_config	= layout_config_folder_load($this->designWorkingSkin,'layout_header');
		$layout_TopBar_config	= layout_config_folder_load($this->designWorkingSkin,'layout_TopBar');
		$layout_footer_config	= layout_config_folder_load($this->designWorkingSkin,'layout_footer');
		$layout_side_config		= layout_config_folder_load($this->designWorkingSkin,'layout_side');
		$layout_scroll_config	= layout_config_folder_load($this->designWorkingSkin,'layout_scroll');

		$this->template->assign(array(
			"skin"					=> $this->designWorkingSkin,
			"layout_header_config"	=>	$layout_header_config,
			"layout_TopBar_config"	=>	$layout_TopBar_config,
			"layout_footer_config"	=>	$layout_footer_config,
			"layout_side_config"	=>	$layout_side_config,
			"layout_scroll_config"	=>	$layout_scroll_config,
		));

		/* 측면영역 강제 숨김처리 */
		/*
		if($this->layout->is_fullsize_absolutly($tpl_path)){
			$this->template->assign(array('is_fullsize_absolutly'=>true));
		}
		*/

		// db값이 없을경우 상수값으로 넣음 :: 2018-11-02 pjw
		// 상수값이 노출되지만 db에는 없는상태 하지만 레이아웃 설정에서 저장을 하면 상수값이 기본으로 업데이트 되는 구조
		$tmp_layout = $layout_config[$tpl_path];
		$tmp_path   = $tmp_layout['tpl_path'];
		$tmp_folder = explode('/', $tmp_path);
		$tmp_folder = $tmp_folder[0];
		if(empty($tmp_layout['tpl_desc'])) $layout_config[$tpl_path]['tpl_desc'] = $this->menuOrderList[$tmp_folder][$tmp_path];

		$this->template->assign($layout_config[$tpl_path]);

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 스킨 설정 화면 > 디자인작업용스킨 목록 부분 ajax */
	public function get_skin_list_html()
	{
		$this->tempate_modules();

		$cfg_system			= ($this->config_system) ? $this->config_system : config_load('system');
		$skinType			= !empty($cfg_system['skin_type'])	? $cfg_system['skin_type'] : 'fixed';
		$defaultSkinPrefix	= $skinType == 'responsive'			? 'responsive' : 'pc';
		$skinPrefix			= !empty($_GET['skinPrefix'])		? $_GET['skinPrefix'] : $defaultSkinPrefix;
		$tmpSkinPrefix		= $skinType == 'fixed'				? $skinPrefix : 'responsive';

		$this->template->assign(array('skinType'=>$skinType, 'skinPrefix'=>$skinPrefix));

		/* 보유한 스킨 목록 가져오기 */
		$my_skin_list = $this->designmodel->get_skin_list($tmpSkinPrefix);

		$this->template->assign(array('my_skin_list'=>$my_skin_list));

		/* 스킨 현재상태 아이콘 */
		$my_skin_list_icon = array();
		foreach($my_skin_list as $k=>$v){
			$my_skin_list_icon[$k] = array();
			if		($skinPrefix=='mobile'){
				if($this->realMobileSkin == $v['skin']) $my_skin_list_icon[$k][] = "실제적용";
				if($this->workingMobileSkin == $v['skin']) $my_skin_list_icon[$k][] = "디자인작업용";
			}elseif	($skinPrefix=='fammerce'){
				if($this->realFammerceSkin == $v['skin']) $my_skin_list_icon[$k][] = "실제적용";
				if($this->workingFammerceSkin == $v['skin']) $my_skin_list_icon[$k][] = "디자인작업용";
			}else	{
				if($this->realSkin == $v['skin']) $my_skin_list_icon[$k][] = "실제적용";
				if($this->workingSkin == $v['skin']) $my_skin_list_icon[$k][] = "디자인작업용";
			}
		}
		$this->template->assign(array('my_skin_list_icon'=>$my_skin_list_icon));
		$this->template->assign(array('skinPrefix'=>$skinPrefix));

		/* 초기 선택 스킨 */
		if		($skinPrefix=='mobile'){
			if(!$_GET['checkedSkin']) $_GET['checkedSkin'] = $this->realMobileSkin;
		}elseif	($skinPrefix=='fammerce'){
			if(!$_GET['checkedSkin']) $_GET['checkedSkin'] = $this->realFammerceSkin;
		}else	{
			if(!$_GET['checkedSkin']) $_GET['checkedSkin'] = $this->realSkin;
		}

		$file_path	= $this->skin.'/design/_skinlist.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	/* 소스 편집화면 */
	public function sourceeditor(){
		$this->tempate_modules();
		$this->load->helper('file');
		$this->load->helper('directory');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		/* tpl_path assign */
		$skin = $this->designWorkingSkin;
		$skinPath = ROOTPATH."data/skin/";
		$tpl_path = $_GET['tpl_path']?$_GET['tpl_path']:null;
		$tpl_realpath = $skinPath.$skin."/".$tpl_path;
		$tpl_fileName = basename($tpl_realpath);
		$tpl_source = read_file($tpl_realpath);
		$searchKeyword = isset($_GET['searchKeyword']) ? $_GET['searchKeyword'] : '';

		/* CSS폴더 파일목록 */
		if(preg_match("/^css\/(.*).css$/",$tpl_path)){
			$css_files = array();
			$css_map = (array)directory_map(dirname($tpl_realpath),true);
			rsort($css_map);
			foreach($css_map as $k=>$v){
				if(preg_match("/(.*).css$/",$v)){
					$desc = "사용자 정의 CSS";
					if($v=='common.css') $desc = "스킨 공통 CSS";
					if($v=='buttons.css') $desc = "버튼 스타일 CSS";
					if($v=='board.css') $desc = "일반 게시판 CSS";
					if($v=='mypage_board.css') $desc = "마이페이지 게시판 CSS";
					if($v=='goods_board.css') $desc = "상품후기,상품문의 게시판 CSS";

					$css_files[] = array(
						'desc'		=> $desc,
						'filename'	=> $v,
						'path'		=> 'css/'.$v,
						'current'	=> $tpl_fileName==$v ? 1 : 0
					);
				}
			}
			$this->template->assign(array('css_files'=>$css_files));
		}

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$tpl_path);
		$this->template->assign(array('layout_config'=>$layout_config[$tpl_path]));

		/* 백업파일 */
		$backup_files = array();
		$skinBackupPath = "data/skin_backup"."/".$skin."/".$tpl_path.date('.YmdHis');
		$skinBackupFileName = basename($skinBackupPath);
		$skinBackupDir = dirname($skinBackupPath);
		$tpl_fileNameForReg = str_replace('.','\.',$tpl_fileName);
		$map = (array)directory_map(ROOTPATH.$skinBackupDir,true);
		rsort($map);
		foreach($map as $k=>$v){
			if(is_file(ROOTPATH.$skinBackupDir.'/'.$v) && preg_match("/{$tpl_fileNameForReg}\.[0-9]{14}/",$v)){
				$backup_files[] = array(
					'path' => $skinBackupDir.'/'.$v,
					'time' => filemtime(ROOTPATH.$skinBackupDir.'/'.$v)
				);

			}
		}

		if(preg_match("/\.css$/",$tpl_fileName)){
			$code_mode = "css";
		}else{
			$code_mode = "htmlmixed";
		}

		if(preg_match("/^board\/([^\/]*)/",$tpl_path,$matches)){
			$source_url = "/board/?id=".$matches[1];
		}else{
			$source_url = $layout_config[$tpl_path]['tpl_page'] ? $this->layout->get_tpl_page_url($tpl_path) : "/".substr($tpl_path,0,strpos($tpl_path,'.'));
		}

		$this->template->assign(array(
			'skin'			=> $skin,
			'tpl_path'		=> $tpl_path,
			'tpl_source'	=> $tpl_source,
			'backup_files'	=> $backup_files,
			'searchKeyword'	=> $searchKeyword,
			'code_mode'		=> $code_mode,
			'source_url'	=> $source_url
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 원본소스 보기 */
	public function source_view_popup(){
		$aGetParams = $this->input->get();
		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('mode', '동작', 'trim|string|xss_clean');
			$this->validation->set_rules('tpl_path', '경로', 'trim|string|xss_clean');
			$this->validation->set_rules('skin', '스킨', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$this->tempate_modules();
		$this->load->helper('file');
		$this->load->helper('readurl');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		/* tpl_path assign */
		$mode = $_GET['mode'];
		$tpl_path = $_GET['tpl_path']?$_GET['tpl_path']:null;
		$skin = !empty($_GET['skin']) ? $_GET['skin'] : $this->designWorkingSkin;
		$skinPath = ROOTPATH."data/skin/";

		switch($mode){
			case "original":
				/* 중계서버에서 원본스킨 소스 가져오기 */
				$skin_configuration = skin_configuration($skin);
				$originalSkin = $skin_configuration['originalSkin'];

				if	(in_array($originalSkin, array('default', 'mobile_ver3_default')))
					$originalSkin = $originalSkin.'_gl';

				$param = array(
					'cmd'			=>	'skinFileSource',
					'skin'			=>	$originalSkin,
					'tpl_path'		=>	$tpl_path,
					'service_code'	=> SERVICE_CODE,
					'hosting_code'	=> $this->config_system['service']['hosting_code'],
					'subDomain'		=> $this->config_system['subDomain'],
					'domain'		=> $this->config_system['domain'],
					'hostDomain'	=> $_SERVER['HTTP_HOST'],
					'shopSno'		=> $this->config_system['shopSno'],
					'multi'			=> 1
				);
				$url = get_connet_protocol()."interface.firstmall.kr/firstmall_plus/request.php";
				$tpl_source = readurl($url,$param);

				$this->template->assign(array('skin' => $skin));
			break;
			case "backup":
				$filePath = ROOTPATH.$tpl_path;
				$tpl_source = read_file($filePath);
			break;
			case "category":
				$tpl_source = readurl(get_connet_protocol().$_SERVER['HTTP_HOST'].'/common/category_navigation_html?tpl_path='.urlencode($tpl_path));
			break;
			case "brand":
				$tpl_source = readurl(get_connet_protocol().$_SERVER['HTTP_HOST'].'/common/brand_navigation_html?tpl_path='.urlencode($tpl_path));
			break;
			case "location":
				$tpl_source = readurl(get_connet_protocol().$_SERVER['HTTP_HOST'].'/common/location_navigation_html?tpl_path='.urlencode($tpl_path));
			break;
		}

		if(preg_match("/\.css$/",$tpl_path)){
			$code_mode = "css";
		}else{
			$code_mode = "htmlmixed";
		}

		$this->template->assign(array(
			'tpl_path'		=> $tpl_path,
			'tpl_source'	=> $tpl_source,
			'code_mode'		=> $code_mode
		));

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$tpl_path);
		$this->template->assign(array('layout_config'=>$layout_config[$tpl_path]));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 원본소스 보기 */
	public function file_view_popup(){
		$this->tempate_modules();
		$this->load->helper('file');
		$this->load->helper('readurl');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		/* tpl_path assign */
		$mode = $_GET['mode'];
		$tpl_path = $_GET['tpl_path']?$_GET['tpl_path']:null;

		$filePath = ROOTPATH.$tpl_path;
		$tpl_source = read_file($filePath);

		if(preg_match("/\.css$/",$tpl_path)){
			$code_mode = "css";
		}else{
			$code_mode = "htmlmixed";
		}

		$this->template->assign(array(
			'tpl_path'		=> $tpl_path,
			'tpl_source'	=> $tpl_source,
			'code_mode'		=> $code_mode
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 이미지 변경 화면 */
	public function image_edit(){
		$this->tempate_modules();
		$this->load->model('layout');

		$link = isset($_GET['link']) ? urldecode($_GET['link']) : null;
		$target = isset($_GET['target']) ? $_GET['target'] : null;
		$elementType = isset($_GET['elementType']) ? $_GET['elementType'] : null;

		$designTplPath = base64_decode($_GET['designTplPath']);
		$designImgSrc = base64_decode($_GET['designImgSrc']);
		$designImgSrcOri = base64_decode($_GET['designImgSrcOri']);
		$designImageLabel = $_GET['designImageLabel'];
		$designImgPath = preg_replace("/^\//","",$designImgSrc);

		if(preg_match("/\{(.*)\}/",$designImgSrc) && $_GET['viewSrc']){
			//openDialogAlert("치환코드로 출력되는 이미지는 변경할 수 없습니다.",400,140,'parent',"parent.DM_window_close();");
			//exit;
			$designImgSrc = $_GET['viewSrc'];
			$designImgPath = preg_replace("/^\//","",$_GET['viewSrc']);
			$isReplacedCode = true;
		}

		$tmp = explode('/',$designTplPath);
		array_shift($tmp);
		$tplPath = implode('/',$tmp);


		/* 이미지가로세로 크기 */
		@list($designImgWidth, $designImgHeight) = @getimagesize($designImgPath);

		/* 이미지 용량 */
		$designImgSize = @filesize($designImgPath);

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$tplPath);
		$this->template->assign(array('layout_config'=>$layout_config[$tplPath]));

		$this->template->assign(array(
			'link'				=> $link,
			'target'			=> $target,
			'elementType'		=> $elementType,
			'designTplPath'		=> $designTplPath,
			'designImgSrc'		=> $designImgSrc,
			'designImgSrcOri'	=> $designImgSrcOri,
			'designImageLabel'	=> $designImageLabel,
			'designImgPath'		=> $designImgPath,
			'designImgScale'	=> "{$designImgWidth} x {$designImgHeight}",
			'designImgSize'		=> $designImgSize,
			'tplPath'			=> $tplPath,
			'frequentUrls' => $this->layout->get_frequent_url(),
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 이미지 넣기 화면 */
	public function image_insert(){
		$this->tempate_modules();
		$this->load->model('layout');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$tplPath = $_GET['designTplPath'];
		$folder = dirname($tplPath);

		switch($folder){
			case "layout_header":
				$up_image_name = "img_layout_header_up";
				$down_image_name = "img_layout_header_down";
			break;
			case "layout_footer":
				$up_image_name = "img_layout_footer_up";
				$down_image_name = "img_layout_footer_down";
			break;
			case "layout_side":
				$up_image_name = "img_layout_side_up";
				$down_image_name = "img_layout_side_down";
			break;
			case "layout_scroll":
				if($tplPath=='layout_scroll/left.html'){
					$up_image_name = "img_layout_scroll_l_up";
					$down_image_name = "img_layout_scroll_l_down";
				}else{
					$up_image_name = "img_layout_scroll_up";
					$down_image_name = "img_layout_scroll_down";
				}
			break;
			default:
				$up_image_name = "img_layout_up";
				$down_image_name = "img_layout_down";
			break;
		}
		$this->template->assign(array('up_image_name'=>$up_image_name,'down_image_name'=>$down_image_name));

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$tplPath);
		$this->template->assign(array('layout_config'=>$layout_config[$tplPath]));

		$this->template->assign(array(
			'tplPath' => $tplPath,
			'frequentUrls' => $this->layout->get_frequent_url()
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 플래시 넣기 화면 */
	public function flash_insert(){
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$template_path = $_GET['template_path'];

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array('template_path' => $template_path));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 플래시 생성 화면 */
	public function flash_create(){
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$template_path = $_GET['template_path'];

		$return_url = get_connet_protocol().$_SERVER['HTTP_HOST']."/admin/design/flash_insert?template_path=".urlencode($template_path);

		$this->template->assign(array('template_path' => $template_path,'return_url' => $return_url));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 플래시 수정 화면 */
	public function flash_edit(){

		$this->load->library('SofeeXmlParser');

		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$flash_seq = $_GET['flash_seq'];
		$template_path = $_GET['template_path'];

		$query = $this->db->query("select a.*,b.url xmlurl from fm_design_flash a,fm_design_flash_file b where a.flash_seq=? and a.flash_seq=b.flash_seq and b.type='xml' and url like '%data.xml' limit 1",$flash_seq);
		$flash_data = $query->row_array();

		if(!file_exists(ROOTPATH.$flash_data['xmlurl'])){
			echo "XML 파일이 존재하지 않습니다.";
			exit;
		}

		$xmlParser = new SofeeXmlParser();
		$xml_url = get_connet_protocol().$_SERVER['HTTP_HOST'].$flash_data['xmlurl'];

		$xmlParser->parseFile($xml_url);
		$tree = $xmlParser->getTree();

		if($tree['data']['option'])
		{
			$options = $tree['data']['option'];
		} else {
			$options = $tree['data'];
			$this->template->assign(array('productExpendViewer'=>true));
		}

		if($tree['data']['item']){
			$items[0] = $tree['data']['item'];
			if(is_numeric(key($tree['data']['item'])) ){
				$items = $tree['data']['item'];
			}
		} else {
			$items[0] = $tree['data'];
			if(is_numeric(key($tree['data'])) ){
				$items = $tree['data'];
			}
		}

		$flashmagicxmldir = "/data/flash/xml/";

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array(
			'data'=>$flash_data,
			'template_path' => $template_path,
			'flash_seq'=>$flash_seq,
			'options'=>$options,
			'items'=>$items,
			'first_item'=>$items[0]
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 플래시 목록부분 ajax */
	public function get_flash_list_html(){
		$this->tempate_modules();

		/* 플래시 목록 가져오기 */
		$sql = 'select SQL_CALC_FOUND_ROWS *,(select url from fm_design_flash_file where flash_seq=a.flash_seq and type="img" and (url like "%flash_%" or url like "%thumb%"  ) limit 1) url from fm_design_flash a order by flash_seq desc';
		$query = $this->db->query($sql);
		$flash_list = $query->result_array();

		$this->template->assign(array('flash_list'=>$flash_list));

		$file_path	= $this->skin.'/design/flash_list_inc.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	/* 동영상 넣기 화면 */
	public function video_insert(){
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */


		$cfg_goods = config_load("goods");
		if( $cfg_goods['ucc_domain'] && $cfg_goods['ucc_key'] ){
			$cfg_goods['video_use']=  'Y';
		}
		$this->template->assign('cfg_goods',$cfg_goods);

		$this->template->assign(array('setMode' => $this->session->userdata('setMode')));
		$template_path = $_GET['template_path'];

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array('template_path' => $template_path));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}


	/* 동영상 넣기 >> 수정 화면 */
	public function video_edit(){
		$this->load->model('videofiles');
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */
		$videoSeq = ($_POST['videoSeq'])?$_POST['videoSeq']:$_GET['videoSeq'];
		$sc['seq'] = $videoSeq;
		$videodata = $this->videofiles->get_data($sc);
		$this->template->assign($videodata);


		$this->template->assign(array('setMode' => $this->session->userdata('setMode')));
		$template_path = $_GET['template_path'];

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array('template_path' => $template_path));
		$this->template->assign(array('realwidth' => $_GET['realwidth']));
		$this->template->assign(array('realheight' => $_GET['realheight']));
		$this->template->assign(array('setMode' => $this->session->userdata('setMode')));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 동영상 생성 화면 */
	public function video_create(){
		$this->load->model('videofiles');
		$this->load->helper('readurl');

		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */
		$this->template->assign(array('setMode' => $this->session->userdata('setMode')));
		if($_POST['file_key_W']) {
			$file_key_w = $_POST['file_key_W'];//웹 인코딩 코드
		}
		if($_POST['file_key_I']) {
			$file_key_i = $_POST['file_key_I'];//스마트폰 인코딩 코드
		}

		/* 파라미터 검증*/
		if($file_key_w || $file_key_i) {
			$videofiles['upkind']					= 'design';
			$videofiles['mbseq']					= $this->managerInfo['manager_seq'];//
			$videofiles['r_date']					= date("Y-m-d H:i:s");
			$videofiles['file_key']				= $_POST['file_key'];//
			$videofiles['file_key_w']			= $file_key_w;//웹 인코딩 코드
			$videofiles['file_key_i']				= $file_key_i;//웹 인코딩 코드

			$videoinforesult = readurl(uccdomain('fileinfo',$file_key_w));
			if($videoinforesult){
				$videoinfoarr = xml2array($videoinforesult);
				$videofiles['playtime']		 = ($videoinfoarr['class']['playtime'])?$videoinfoarr['class']['playtime']:'';
				$playtime = $videofiles['playtime'];
			}
			$videofiles['memo']				= $_POST['memo'];//
			$videofiles['encoding_speed']	= ($_POST['encoding_speed'])?$_POST['encoding_speed']:400;
			$videofiles['encoding_screen'] = (is_array($_POST['encoding_screen'])) ? @implode("X",($_POST['encoding_screen'])):'400X300';

			$videoseq = $this->videofiles->videofiles_write($videofiles);
		}

		if( $_POST['file_key_W'] || $_POST['file_key_I'] ) {
			if($playtime){
				$this->template->assign("playtime",$playtime.'초');
			}
			$this->template->assign("r_date",date("Y-m-d H:i:s"));
			$this->template->assign("thumbnailsrc",uccdomain('thumbnail',$file_key_w));
			$this->template->assign("videoseq",$videoseq);
			if( $this->_is_mobile_agent ){
				$this->template->assign("ismobileagent",true);
				if($file_key_i){
					$uccdomainembedsrc = uccdomain('fileurl',$file_key_i);
				}else{
					$uccdomainembedsrc = uccdomain('fileurl',$file_key_w);
				}
			}else{
				if($file_key_i){
					$uccdomainembedsrc = uccdomain('fileswf',$file_key_i);
				}else{
					$uccdomainembedsrc = uccdomain('fileswf',$file_key_w);
				}
			}

			$this->template->assign("uccdomainembedsrc",$uccdomainembedsrc);
			$this->template->assign("file_key_w",$file_key_w);
			$this->template->assign("file_key_i",$file_key_i);
			$this->template->assign("encoding_screen",$_POST['encoding_screen']);
			$this->template->assign("encoding_speed",$_POST['encoding_speed']);


			$this->template->assign("videook",true);
		}else{
			$this->template->assign("videook",false);
		}
		//동영상연결(기본 파일찾기)
		$this->template->assign("uccdomain",uccdomain());
		if( $_POST['error']) {
			$this->template->assign("videoerror",true);
			$this->template->assign("error",$_POST['error']);
		}else{
			$this->template->assign("videoerror",false);
		}

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 동영상 URL 화면 */
	public function video_url(){
		$this->template->assign("realvideourl",$_GET['realvideourl']);
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 동영상 목록부분 ajax */
	public function get_video_list_html(){
		$this->tempate_modules();

		/* 동영상 목록 가져오기 */
		$this->load->model('videofiles');
		$this->load->helper('readurl');


		/**
		 * list setting
		**/
		$videosc['orderby']	= (!empty($_GET['orderby'])) ?	$_GET['orderby']:' seq desc, sort asc ';
		$videosc['sort']		= (!empty($_GET['sort'])) ?			$_GET['sort']:'';
		$videosc['page']		= (!empty($_GET['page'])) ?		intval($_GET['page']):0;
		$videosc['perpage']	= (!empty($_GET['perpage'])) ?	intval($_GET['perpage']):20;

		$videosc['upkind']	= 'design';
		$video_list = $this->videofiles->videofiles_list($videosc);//debug_var($video_list);

		$videosc['searchcount']	= $video_list['count'];
		$videosc['total_page']		= ceil($videosc['searchcount']	 / $videosc['perpage']);
		$videosc['totalcount']		= $this->videofiles->get_item_total_count($videosc);

		if($video_list['result']) $this->template->assign('video_list',$video_list['result']);

		$returnurl = "./video_insert?template_path=".$_GET['template_path'];
		$paginlay =  pagingtag($videosc['searchcount']	,$videosc['perpage'],$returnurl, getLinkFilter('',array_keys($videosc)),'page','" ' );

		if($videosc['searchcount'] > 0) {
			$paginlay = (!empty($paginlay)) ? $paginlay:'<p><a class="on red">1</a><p>';
		}
		$this->template->assign('videopagin',$paginlay);


		$file_path	= $this->skin.'/design/video_list_inc.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 팝업 생성,수정 화면 */
	public function popup_edit(){
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */


		$popup_seq		= $_GET['popup_seq'];
		$template_path	= $_GET['template_path'];
		$banner_type	= ($_GET['banner_type'] == 'band') ? 'band' : 'layer';

		/* 팝업 목록 가져오기 */
		if($popup_seq){
			$query		= $this->db->query("select * from fm_design_popup where popup_seq = ?",$popup_seq);
			$popup_data = $query->row_array();
		}else{
			$popup_data['style'] = $banner_type;
		}

		if(!$popup_data['bar_background_color']){
			$popup_data['bar_background_color'] = '#eeeeee';
		}

		if(!is_object(json_decode($popup_data['bar_msg_today_decoration']))){
			$popup_data['bar_msg_today_decoration'] = json_encode(array('color'=>'#000000'));
		}

		if(!is_object(json_decode($popup_data['bar_msg_close_decoration']))){
			$popup_data['bar_msg_close_decoration'] = json_encode(array('color'=>'#000000'));
		}

		if($popup_data['contents']===null){
			$popup_data['width'] = 380;
			$popup_data['height'] = 350;
			$popup_data['contents'] = file_get_contents(ROOTPATH.'admin/skin/'.$this->skin.'/design/_popup_default_source.html');
		}

		// 팝업 노출 조건 추가 2015-10-01 jhr
		if($popup_data['popup_condition']){
			$popup_data['popup_condition'] = unserialize($popup_data['popup_condition']);
		}else{//최초 등록시
			$popup_condition = array();
			$popup_condition['view'] = 'all';
			$popup_condition['brand'] = 'all';
			$popup_condition['category'] = 'all';
			$popup_condition['location'] = 'all';
			$popup_data['popup_condition'] = $popup_condition;
		}

		# 팝업 본문 스타일 선택값 없을 시 ie9 error @2015-12-16 pjm
		if(!$popup_data['contents_type']) $popup_data['contents_type'] = "image";

		// 팝업 스타일
		$popup_styles = $this->designmodel->get_popup_styles();
		$this->template->assign(array('popup_styles'=>$popup_styles));

		// 배너 스타일
		$banner_styles = $this->designmodel->get_banner_styles();

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array('data'=>$popup_data, 'template_path' => $template_path, 'popup_seq'=>$popup_seq,'banner_styles'=>$banner_styles));
		$file_path	= $this->template_path();
		$file_path	= ($this->config_system['operation_type']=='light') ? str_replace('popup_edit.html', 'popup_edit_light.html', $file_path) : $file_path;
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 팝업 띄우기 화면 */
	public function popup_insert(){
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$template_path = $_GET['template_path'];

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array('template_path' => $template_path));
		$file_path	= $this->template_path();
		$file_path	= ($this->config_system['operation_type']=='light') ? str_replace('popup_insert.html', 'popup_insert_light.html', $file_path) : $file_path;
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 팝업 목록부분 ajax */
	public function get_popup_list_html(){
		$this->tempate_modules();

		// operation_type 추가 :: 2019-12-03 pjw
		$operation_type = $this->config_system['operation_type'] == 'light' ? 'light' : 'heavy';

		// 팝업 스타일
		$popup_styles = $this->designmodel->get_popup_styles();
		$this->template->assign(array('popup_styles'=>$popup_styles));

		/* 팝업 목록 가져오기 */
		$query = $this->db->query("select * from fm_design_popup where operation_type = '".$operation_type."' order by popup_seq desc");
		$popup_list = $query->result_array();

		$now_time = time();
		foreach($popup_list as $k=>$v){
			switch($v['status']){
				case 'show':
					$popup_list[$k]['status_msg'] = "진행";
				break;
				case 'period':
					if($now_time < strtotime($v['period_s'])){
						$popup_list[$k]['status_msg'] = "대기";
					}elseif($now_time < strtotime($v['period_e'])){
						$popup_list[$k]['status_msg'] = "진행";
					}elseif($now_time >= strtotime($v['period_e'])){
						$popup_list[$k]['status_msg'] = "종료";
					}
				break;
				case 'stop':
					$popup_list[$k]['status_msg'] = "중지";
				break;
			}
		}

		$this->template->assign(array('popup_list'=>$popup_list));



		$this->template->assign(array('template_path' => $template_path));
		$file_path	= ($this->config_system['operation_type']=='light') ? $this->skin.'/design/popup_list_light.html' : $this->skin.'/design/popup_list_inc.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 팝업 목록부분 ajax light 형 :: 2018-12-18 lwh
	public function get_popup_list_light_html(){
		$this->tempate_modules();

		// 팝업 스타일
		$popup_styles = $this->designmodel->get_popup_styles();
		$this->template->assign(array('popup_styles'=>$popup_styles));

		/* 팝업 목록 가져오기 */
		$operation_type = ($this->config_system['operation_type']) ? $this->config_system['operation_type'] : 'heavy';
		$query = $this->db->query("select * from fm_design_popup where operation_type = '" . $operation_type . "' order by popup_seq desc");
		$popup_list = $query->result_array();

		$now_time = time();
		foreach($popup_list as $k=>$v){
			switch($v['status']){
				case 'show':
					$popup_list[$k]['status_msg'] = '<font color=red>(노출)</font>';
				break;
				case 'period':
					if($now_time < strtotime($v['period_s'])){
						$popup_list[$k]['status_msg'] = '<br/><font color=gray>(' . date('Y-m-d', strtotime($v['period_s'])) . '~' . date('Y-m-d', strtotime($v['period_e'])) . ')</font>';
					}elseif($now_time < strtotime($v['period_e'])){
						$popup_list[$k]['status_msg'] = '<br/><font color=red>(노출 : ' . date('Y-m-d', strtotime($v['period_s'])) . '~' . date('Y-m-d', strtotime($v['period_e'])) . ')</font>';
					}elseif($now_time >= strtotime($v['period_e'])){
						$popup_list[$k]['status_msg'] = ' - ';
					}
				break;
				case 'stop':
					$popup_list[$k]['status_msg'] = " - ";
				break;
			}
		}

		$this->template->assign(array('popup_list'=>$popup_list));



		$this->template->assign(array('template_path' => $template_path));
		$file_path	= ($this->config_system['operation_type']=='light') ? $this->skin.'/design/popup_list_light.html' : $this->skin.'/design/popup_list_inc.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 상품디스플레이 생성,수정 화면 */
	public function display_edit(){
		$this->tempate_modules();
		$this->load->model('goodsmodel');
		$this->load->model('goodsdisplay');
		$this->load->model('eventmodel');
		$this->load->helper('text');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		// 다수의 설정 저장 시 사용 :: 2018-11-15 lwh
		$target_codes			= $_GET['target_codes'];
		if($target_codes[0])	$_GET['category_code']	= $target_codes[0]; // 대표로 불러올 데이터 추출용

		$display_seq			= $_GET['display_seq'];
		$kind					= $_GET['kind'];
		$sub_kind				= $_GET['sub_kind'];
		$displaykind			= $_GET['displaykind'];
		$direct					= $_GET['direct'];
		$perpage				= $_GET['perpage'];
		$popup					= $_GET['popup'];
		$category_code			= $_GET['category_code'];
		$display_data			= array();
		$m_display_data			= array();
		$mobile_display_flag	= false;	//모바일페이지 사용여부
		$display_tab_flag		= false;	//탭 추가 사용여부
		$display_condition_flag	= false;	//상품 조건지정 사용여부
		$display_select_flag	= false;	//상품노출, 인기순, 상품페이징 사용 여부
		$category_flag			= false;	//카테고리,브랜드,지역 상품영역
		$recommend_flag			= false;	//카테고리,브랜드,지역 추천상품영역
		$relation_flag			= false;	//추천상품1,추천상품2,판매자추천상품
		$batch_flag				= false;	//한꺼번에 꾸미기
		$mobile_skin_chk		= 'n';		//pc디스플레이를 모바일스킨에 넣었을때 경고창
		$orders					= $this->goodsdisplay->orders;	//기본정렬값
		$template_path			= $_GET['template_path'];
		$image_decorations		= array();
		$cfg_system				= ($this->config_system) ? $this->config_system : config_load('system');
		$sampleGoodsInfo		= array(	// 샘플 상품 정보
			'goods_seq' => '',
			'goods_name' => '샘플 상품',
			'sale_per' => 0,
			'price' => '19800',
			'consumer_price' => '24800',
			'image_cnt' => 2,
			'image2' => '/admin/skin/default/images/design/img_effect_sample2.gif',
		);
		$mobile_styles			= $this->goodsdisplay->get_mobilestyles();	//모바일용 스타일
		$mobilestyles_list		= $this->goodsdisplay->mobilestyles_list;	//카테고리용 모바일 스타일리스트
		$currency_symbol_list	= get_currency_symbol_list();				//노출심벌 통화단위
		$cfg_goods				= config_load("goods");
		$goodsImageSize			= config_load('goodsImageSize');
		@asort($goodsImageSize);

		if( $cfg_goods['ucc_domain'] && $cfg_goods['ucc_key'] ) $cfg_goods['video_use']=  'Y';

		if	(!$kind)
			$kind					= 'design';

		if	(in_array($kind,array('category','brand','location')))
			$category_flag			= true;

		if	(in_array($kind,array('category_recommend','brand_recommend','location_recommend')))
			$recommend_flag			= true;

		if	(in_array($kind,array('relation','relation_seller','bigdata')))
			$relation_flag			= true;

		if	($category_flag || $recommend_flag || $relation_flag)
			$mobile_display_flag	= true;

		if	($sub_kind == 'batch')
			$batch_flag				= true;

		// light 체크 추가 :: 2018-11-30 pjw
		$is_light = $this->config_system['operation_type']=='light' ? true : false;

		if	((in_array($kind, array('category', 'brand', 'location')) && $sub_kind == 'batch') || !$batch_flag) {
			switch($kind){
				case 'category':
					if	($category_code) {
						$this->load->model('categorymodel');
						$category_data	= $this->categorymodel->get_category_data($category_code);
						$display_data	= $this->change_display_params($category_data, 'pc');
						$m_display_data = $this->change_display_params($category_data, 'mobile');
					}
					$display_select_flag = true;
					break;
				case 'brand':
					if	($category_code) {
						$this->load->model('brandmodel');
						$brand_data		= $this->brandmodel->get_brand_data($category_code);
						$display_data	= $this->change_display_params($brand_data, 'pc');
						$m_display_data = $this->change_display_params($brand_data, 'mobile');
					}
					$display_select_flag = true;
					break;
				case 'location':
					if	($category_code) {
						$this->load->model('locationmodel');
						$location_data	= $this->locationmodel->get_location_data($category_code);
						$display_data	= $this->change_display_params($location_data, 'pc');
						$m_display_data = $this->change_display_params($location_data, 'mobile');
					}
					$display_select_flag = true;
					break;
				case 'category_recommend':
					$this->load->model('categorymodel');
					$display_seq_arr = $this->categorymodel->get_category_recommend_display_seq($category_code);

					// light 인 경우 light 디스플레이 고유번호 가져옴 :: 2018-11-30 pjw
					$display_seq = $is_light ? $display_seq_arr['recommend_display_light_seq'] : $display_seq_arr['recommend_display_seq'];
					$m_display_seq = $display_seq_arr['m_recommend_display_seq'];
					break;
				case 'brand_recommend':
					$this->load->model('brandmodel');
					$display_seq_arr = $this->brandmodel->get_brand_recommend_display_seq($category_code);

					// light 인 경우 light 디스플레이 고유번호 가져옴 :: 2018-11-30 pjw
					$display_seq = $is_light ? $display_seq_arr['recommend_display_light_seq'] : $display_seq_arr['recommend_display_seq'];
					$m_display_seq = $display_seq_arr['m_recommend_display_seq'];
					break;
				case 'location_recommend':
					$this->load->model('locationmodel');
					$display_seq_arr = $this->locationmodel->get_location_recommend_display_seq($category_code);

					// light 인 경우 light 디스플레이 고유번호 가져옴 :: 2018-11-30 pjw
					$display_seq = $is_light ? $display_seq_arr['recommend_display_light_seq'] : $display_seq_arr['recommend_display_seq'];
					$m_display_seq = $display_seq_arr['m_recommend_display_seq'];
					break;
				case 'relation':

					// light 인 경우 light 디스플레이 고유번호 가져옴 :: 2018-11-30 pjw
					$display_seq_arr	= $this->goodsmodel->get_goods_relation_display_seq();
					$display_seq		= $is_light ? $display_seq_arr['r_display_seq'] : $display_seq_arr['display_seq'];
					$m_display_seq		= $display_seq_arr['m_display_seq'];
					break;
				case 'relation_seller':

					// light 인 경우 light 디스플레이 고유번호 가져옴 :: 2018-11-30 pjw
					$display_seq_arr	= $this->goodsmodel->get_goods_relation_seller_display_seq();
					$display_seq		= $is_light ? $display_seq_arr['r_display_seq'] : $display_seq_arr['display_seq'];
					$m_display_seq		= $display_seq_arr['m_display_seq'];
					break;
				case 'bigdata':

					// light 인 경우 light 디스플레이 고유번호 가져옴 :: 2018-11-30 pjw
					$display_seq_arr	= $this->goodsmodel->get_goods_bigdata_display_seq();
					$display_seq		= $is_light ? $display_seq_arr['r_display_seq'] : $display_seq_arr['display_seq'];
					$m_display_seq		= $display_seq_arr['m_display_seq'];
					break;
				case 'search':			//검색 디스플레이 노출 설정 2018-02-14
					$display_seq_arr	 = $this->goodsmodel->get_goods_display_insert($_GET['platform'],'','search');
					$display_seq		 = $display_seq_arr['display_seq'];
					$display_select_flag = true;
					break;
			}
		}

		if	(!$category_flag && !$batch_flag) {
			/* 상품디스플레이 정보 가져오기 */
			$display_data = $this->goodsdisplay->get_display($display_seq,true);
			$display_tabs = $this->goodsdisplay->get_display_tab($display_seq);

			//관련상품 상품상세에서 수정시 kind 가 없기 때문에 DB에서 가져온 데이터로 판단한다
			if($display_data['kind'] == 'relation' || $display_data['kind'] == 'relation_mobile'){
				$display_seq_arr	= $this->goodsmodel->get_goods_relation_display_seq();
				$display_seq		= $is_light ? $display_seq_arr['r_display_seq'] : $display_seq_arr['display_seq'];
				$m_display_seq		= $display_seq_arr['m_display_seq'];

				if($display_data['kind'] == 'relation_mobile'){
					$display_data		= $this->goodsdisplay->get_display($display_seq,true);
					$display_tabs		= $this->goodsdisplay->get_display_tab($display_seq);
				}

				$mobile_display_flag	= true;
			}

			//빅데이터는 모바일일 경우엔 PC 버전 디스플레이 설정도 갖고온다
			if($display_data['kind'] == 'bigdata' || $display_data['kind'] == 'bigdata_mobile'){
				$this->load->model('bigdatamodel');
				if($display_data['kind'] == 'bigdata_mobile'){
					$display_seq = $this->bigdatamodel->get_kind_display_seq();
					$display_data = $this->goodsdisplay->get_display($display_seq,true);
					$display_tabs = $this->goodsdisplay->get_display_tab($display_seq);
				}
				$this->load->model('bigdatamodel');
				$m_display_seq = $this->bigdatamodel->get_kind_display_seq('bigdata_mobile');
				$mobile_display_flag = true;
			}

			//판매자 추천상품은 모바일일 경우엔 PC 버전 디스플레이 설정도 갖고온다
			if($display_data['kind'] == 'relation_seller' || $display_data['kind'] == 'relation_seller_mobile'){
				$display_seq_arr	= $this->goodsmodel->get_goods_relation_seller_display_seq();
				$display_seq		= $is_light ? $display_seq_arr['r_display_seq'] : $display_seq_arr['display_seq'];
				$m_display_seq		= $display_seq_arr['m_display_seq'];

				if($display_data['kind'] == 'relation_seller_mobile'){
					$display_data	= $this->goodsdisplay->get_display($display_seq,true);
					$display_tabs	= $this->goodsdisplay->get_display_tab($display_seq);
				}

				$mobile_display_flag = true;
			}

			//검색 디스플레이 노출 설정 2018-02-14
			if($display_data['kind'] == 'search') {
				$cfg_search = config_load("search");
				if(!$cfg_search[$display_data['platform'].'_list_goods_status']) $cfg_search[$display_data['platform'].'_list_goods_status'] = 'normal';
				$display_data['goods_status'] = explode("|",$cfg_search[$display_data['platform'].'_list_goods_status']);
				$display_data['default_sort'] = $cfg_search[$display_data['platform'].'_list_default_sort'];
			}

			if($mobile_display_flag){
				$m_display_data = $this->goodsdisplay->get_display($m_display_seq,true);
				$m_display_tabs = $this->goodsdisplay->get_display_tab($m_display_seq);
			}
		}

		$platform = $display_data['platform'] ? $display_data['platform'] : $_GET['platform'];
		$platform = $platform ? $platform : 'pc';
		if($this->config_system['operation_type']=='light')	$platform = 'responsive';
		$this->template->assign(array('platform'=>$platform));

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		/* 이벤트페이지에 상품디스플레이 넣을경우 체크 */
		if($this->eventmodel->is_event_template_file($template_path))
			$this->template->assign(array('eventpage'=>1));

		/* 구매조건 페이지에 상품디스플레이 넣을경우 체크 */
		if($this->eventmodel->is_gift_template_file($template_path))
			$this->template->assign(array('giftpage'=>1));

		/* 디스플레이 상품 목록 */
		if($display_seq){
			foreach($display_tabs as $k=>$v){
				$display_tabs[$k]['items'] = $this->goodsdisplay->get_display_item($display_seq,$k);
			}

			if($m_display_seq && $mobile_display_flag){
				foreach($m_display_tabs as $k=>$v){
					$m_display_tabs[$k]['items'] = $this->goodsdisplay->get_display_item($m_display_seq,$k);
				}
			}
		}

		/* 이미지 꾸미기 값 파싱 */
		if	($display_data['image_decorations'])
			$image_decorations = $this->goodsdisplay->decode_image_decorations($display_data['image_decorations']);
		if	($m_display_data['image_decorations'])
			$m_image_decorations = $this->goodsdisplay->decode_image_decorations($m_display_data['image_decorations']);

		if	( $displaykind == 'designvideo' || $display_data['kind'] == 'designvideo' ) {
			$styles = $this->goodsdisplay->get_videostyles();
		}else{
			$styles = $this->goodsdisplay->get_styles();
			$custom_style = array();

			if	($relation_flag) {
				unset($styles['lattice_b']);
				unset($styles['list']);
				unset($styles['rolling_v']);
			}

			if	($recommend_flag)
				unset($styles['rolling_v']);

			if	($category_flag) {
				unset($styles['rolling_h']);
				unset($styles['rolling_v']);
			}

			foreach($styles as $key => $val){
				if	($val['custom'] == 1) {
					$custom_style[] = $val;
					unset($styles[$key]);
				}else{
					$styles[$key]['image'] = $goodsImageSize;
					if	($key == 'lattice_a')
						$styles[$key]['image_selected'] = $display_data['image_size'];
					else
						$styles[$key]['image_selected'] = $display_data['image_size_'.$key];

					if	($key == 'lattice_a') {
						$styles[$key]['image_opt'] = $display_data['img_opt_'.$key];
						$styles[$key]['image_padding'] = $display_data['img_padding_'.$key];
					}
				}
			}

			//카테고리쪽에선 사용하지 않는다
			if	(!$category_flag) {
				$style_cnt = count($styles);
				$style_add = array();
				$style_row = '';
				$cnt = ceil(count($custom_style)/$style_cnt);
				if	($cnt > 0) {
					for($i=0; $i<$cnt; $i++){
						$style_row = 'arr'.$i;
						for	($x=0; $x<$style_cnt; $x++) {
							$r_idx = $x+($i*$style_cnt);
							$style_add[$style_row][$r_idx] = $custom_style[$r_idx] ? $custom_style[$r_idx] : '';
						}
					}
				}
				$this->template->assign('custom_style',$style_add);
			}
		}

		// light 일 경우만 반응형 스타일 노출 :: 2019-02-28 pjw
		if($this->config_system['operation_type'] != 'light'){
			unset($styles['responsible']);
			unset($styles['sizeswipe']);
		}


		//꾸미기 즐겨찾기 값들
		$image_favorite		= $this->designmodel->get_favorite_decorations('image_decoration', $platform);
		$m_image_favorite	= $this->designmodel->get_favorite_decorations('image_decoration', 'mobile');

		$goods_favorite		= $this->designmodel->get_favorite_decorations('goods_decoration', $platform);
		$m_goods_favorite	= $this->designmodel->get_favorite_decorations('goods_decoration', 'mobile');

		if	($display_data['info_settings']) {
			$display_data['info_settings'] = str_replace("\\\"","\"",$display_data['info_settings']);
			$display_data['info_settings'] = str_replace("\"{","{",$display_data['info_settings']);
			$display_data['info_settings'] = str_replace("}\"","}",$display_data['info_settings']);
		}

		if	($goods_favorite) {
			foreach($goods_favorite as $key => $val){
				$val['decoration'] = str_replace("\\\"","\"",$val['decoration']);
				$val['decoration'] = str_replace("\"{","{",$val['decoration']);
				$val['decoration'] = str_replace("}\"","}",$val['decoration']);
				$goods_favorite[$key] = $val;
			}
		}

		if	($m_goods_favorite) {
			foreach($m_goods_favorite as $key => $val){
				$val['decoration'] = str_replace("\\\"","\"",$val['decoration']);
				$val['decoration'] = str_replace("\"{","{",$val['decoration']);
				$val['decoration'] = str_replace("}\"","}",$val['decoration']);
				$m_goods_favorite[$key] = $val;
			}
		}

		if	($this->mobileMode && $platform == 'pc' && ($kind == 'design' || $display_data['kind'] == 'design'))
			$mobile_skin_chk				= 'y';

		if	(($kind == 'design' || $recommend_flag) && $display_data['kind'] != 'search' && !$relation_flag && !$category_flag)
			$display_tab_flag				= true;

		if	(($kind == 'design' || $recommend_flag || $display_data['kind'] == 'design') && !$category_flag)
			$display_condition_flag			= true;


		if	($this->config_system['operation_type'] == 'light'){
			$display_edit_style					= '/design/_display_edit_style_light.html';
		}else{
			if	($platform=='mobile' && !$relation_flag)
				$display_edit_style				= '/design/_display_edit_style_m.html';
			else
				$display_edit_style				= '/design/_display_edit_style.html';
		}

		if	($this->config_system['operation_type'] == 'light'){

			// 상품정보 스타일 가져오기 호출 :: 2019-05-09 pjw
		    $display_data['goods_decoration_favorite_key'] = $this->designmodel->get_goods_info_style('display', $display_data['goods_decoration_favorite_key']);

			// light 형태 사용여부 재지정 :: 2018-11-26 lwh
			$mobile_display_flag				= false;

			// condition 노출 타입 지정
			$condition_type = array();
			if	($displaykind == 'bigdata' || $display_data['kind'] == 'bigdata'){
				$condition_type['auto_sub'] = true;
			}else{
				$condition_type = array('auto' => true,	'select' => true, 'text' => true);
				if($displaykind == 'relation' || $displaykind == 'relation_seller' || $display_data['kind'] == 'relation' || $display_data['kind'] == 'relation_seller'){
					$condition_type['text'] = false;
				}
			}

			$display_edit_decoration			= '/design/_display_edit_decoration_light.html';
			$display_edit_goods_info			= '/design/_display_edit_goods_info_light.html';
			$display_edit_condition				= '/design/_display_edit_condition_light.html';
			$display_edit_tab					= '/design/_display_edit_tab_light.html';
			$display_footer_popup				= '/design/_display_edit_popup_light.html';
		}else{
			if	($displaykind == 'designvideo' || $display_data['kind']=='designvideo') {
				$display_edit_decoration		= '/design/_display_edit_movie.html';
			}else{
				if	($platform=='mobile')
					$display_edit_decoration	= '/design/_display_edit_decoration_m.html';
				else
					$display_edit_decoration	= '/design/_display_edit_decoration.html';
			}
			$display_edit_goods_info			= '/design/_display_edit_goods_info.html';
			$display_edit_condition				= '/design/_display_edit_condition.html';
			$display_edit_tab					= '/design/_display_edit_tab.html';
			$display_footer_popup				= '/design/_display_edit_popup.html';
		}

		$this->template->define(array(
			'display_edit_style'		=> $this->skin.$display_edit_style,						//스타일
			'display_edit_decoration'	=> $this->skin.$display_edit_decoration,				//꾸미기
			'display_edit_goods_info'	=> $this->skin.$display_edit_goods_info,				//상품정보
			'display_edit_condition'	=> $this->skin.$display_edit_condition,					//상품 조건지정
			'display_edit_tab'			=> $this->skin.$display_edit_tab,						//탭 추가
			'display_footer_popup'		=> $this->skin.$display_footer_popup,					//하단 팝업
			'display_edit_mobile'		=> $this->skin.'/design/_display_edit_mobile.html',		//모바일 전용페이지
			'display_edit_select'		=> $this->skin.'/design/_display_edit_select.html',		//상품노출,인기순정렬
			'display_edit_select_m'		=> $this->skin.'/design/_display_edit_select_m.html',	//상품노출,인기순정렬
			'tpl'						=> $this->template_path()
		));

		$this->template->assign(array(
			'condition_type'			=> $condition_type,
			'target_codes'				=> $target_codes,
			'template_path'				=> $template_path,
			'data'						=> $display_data,
			'm_data'					=> $m_display_data,
			'imageIcons'				=> $this->goodsdisplay->get_image_icons(),
			'goodsImageSizes'			=> $goodsImageSize,
			'displaykind'				=> $displaykind,
			'kind'						=> $kind,
			'sub_kind'					=> $sub_kind,
			'direct'					=> $direct,
			'perpage'					=> $perpage,
			'popup'						=> $popup,
			'orders'					=> $orders,
			'cfg_goods'					=> $cfg_goods,
			'display_seq'				=> $display_seq,
			'display_tabs'				=> $display_tabs,
			'image_decorations'			=> $image_decorations,
			'm_display_seq'				=> $m_display_seq,
			'm_display_tabs'			=> $m_display_tabs,
			'm_image_decorations'		=> $m_image_decorations,
			'sampleGoodsInfo'			=> $sampleGoodsInfo,
			'skinVersion'				=> $this->workingMobileSkinVersion,
			'mobile_skin_chk'			=> $mobile_skin_chk,
			'auto_select_upgrade'		=> $cfg_system['auto_select_upgrade'],
			'image_favorite'			=> $image_favorite,
			'm_image_favorite'			=> $m_image_favorite,
			'goods_favorite'			=> $goods_favorite,
			'm_goods_favorite'			=> $m_goods_favorite,
			'styles'					=> $styles,
			'mobile_styles'				=> $mobile_styles,
			'currency_symbol_list'		=> $currency_symbol_list,
			'category_code'				=> $category_code,
			'mobilestyles_list'			=> $mobilestyles_list,
			'mobile_display_flag'		=> $mobile_display_flag,
			'display_tab_flag'			=> $display_tab_flag,
			'display_condition_flag'	=> $display_condition_flag,
			'display_select_flag'		=> $display_select_flag,
			'category_flag'				=> $category_flag,
			'recommend_flag'			=> $recommend_flag,
			'relation_flag'				=> $relation_flag,
			'developIpCheck'			=> developIpCheck()
		));

		$this->template->print_("tpl");
	}

	public function display_image_icon(){
		$this->load->model('goodsdisplay');
		$icon = $this->goodsdisplay->get_image_icons();
		echo json_encode( $icon );
	}

	public function display_image_icon_background(){
		$this->load->model('goodsdisplay');
		$icon = $this->goodsdisplay->get_image_icons('background');
		echo json_encode( $icon );
	}

	public function display_image_send(){
		$this->load->model('goodsdisplay');
		$icon = $this->goodsdisplay->get_image_icons('send');
		echo json_encode( $icon );
	}

	public function display_image_zzim(){
		$this->load->model('goodsdisplay');
		$icon = $this->goodsdisplay->get_image_icons('zzim');
		echo json_encode( $icon );
	}

	public function display_image_zzim_on(){
		$this->load->model('goodsdisplay');
		$icon = $this->goodsdisplay->get_image_icons('zzim_on');
		echo json_encode( $icon );
	}

	public function display_image_slide(){
		$this->load->model('goodsdisplay');
		$icon = $this->goodsdisplay->get_image_icons('slide');
		echo json_encode( $icon );
	}

	/* 라이브방송 띄우기 화면 */
	public function broadcast_insert(){
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->load->model('broadcastdisplay');

		$platform ="responsive";
		if($this->config_system['operation_type'] !='light') {
			if($this->mobileMode) {
				$platform = 'mobile';
			} else {
				$platform = 'pc';
			}
		}

		/* 기본 라이브 방송 디스플레이 생성은 DB 패치로 제공 */

		$template_path = $_GET['template_path'];

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));
		$this->template->assign(array('skinVersion' => $this->workingMobileSkinVersion));
		$this->template->assign(array('template_path' => $template_path));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 라이브방송 목록부분 Light ajax */
	public function get_broadcast_list(){
		$this->tempate_modules();
		$this->load->model('broadcastdisplay');

		$platform ="responsive";
		if($this->config_system['operation_type'] !='light') {
			if($this->mobileMode) {
				$platform = 'mobile';
			} else {
				$platform = 'pc';
			}
		}

		/* 상품디스플레이 목록 가져오기 */
		$param['platform'] = $platform;
		$result = $this->broadcastdisplay->getDisplay($param);

		$totalcount = $this->broadcastdisplay->getDisplayCount();

		$perpage	= 10;
		$page		= $this->input->get('page') ? $this->input->get('page') : 1;
		$totalpage	= round($totalcount / $perpage);
		// 페이징 추가 :: 2015-07-31 lwh
		if($totalpage > 0){
			$paginlay = pagingtagjs($page, $perpage, $totalpage, 'load_broadcast_list([:PAGE:])');
		}
		$this->template->assign('pagin',$paginlay);
		$this->template->assign(array('broadcast_list'=>$result));

		$styles = $this->broadcastdisplay->get_styles($platform);
		$this->template->assign(array('styles'=>$styles));

		$file_path	= $this->skin.'/design/broadcast_list.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function broadcast_edit() {
		$this->tempate_modules();
		$this->load->helper('broadcast');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->load->model('broadcastdisplay');

		$platform ="responsive";
		if($this->config_system['operation_type'] !='light') {
			if($this->mobileMode) {
				$platform = 'mobile';
			} else {
				$platform = 'pc';
			}
		}
		if	($platform == 'pc'){
			$broadcast_edit_style				= '/design/_broadcast_edit_style.html';
		}else{
			$broadcast_edit_style				= '/design/_broadcast_edit_style_m.html';
		}
		$styles = $this->broadcastdisplay->get_styles($platform);

		if($this->input->get('display_seq')) {
			$data = $this->broadcastdisplay->getDisplay(array('display_seq'=>$this->input->get('display_seq')));
			$data = $data['0'];

			if($data['sort'] == 'direct') {
				$this->load->model('broadcastmodel');
				$this->load->helper('broadcast');
				// item 의 broadcast_seq 가져오고
				$bc_items = $this->broadcastdisplay->getBroadcastItem($data['display_seq']);
				$bs_seqs = array();
				foreach($bc_items as $item) {
					$bs_seqs[] = $item['bs_seq'];
				}
				if(count($bs_seqs) > 0) {
					$data['sch'] = $this->broadcastmodel->getSch(array('bs_seq' => $bs_seqs));
					broadcastlist($data['sch']);
				}
				// 그 후 방송 상세정보 가져오기
			}
		}
		$template_path = $this->input->get('template_path');

		$this->template->define(array(
			'broadcast_edit_style'		=> $this->skin.$broadcast_edit_style,					//스타일
			'tpl'						=> $this->template_path()
		));

		$this->template->assign(array(
			'styles'					=> $styles,
			'platform'					=> $platform,
			'data'						=> $data,
			'dataObj'					=> json_encode($data),
			'template_path'				=> $template_path,
		));

		$this->template->print_("tpl");
	}

	/* 인스타그램 피드 넣기 화면 */
	public function instagramFeed_insert()
	{
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if (!$auth) {
			$callback = 'history.go(-1);';
			$this->template->assign(['auth_msg' => $this->auth_msg, 'callback' => $callback]);
			$this->template->define(['denined' => $this->skin . '/common/denined.html']);
			$this->template->print_('denined');
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$template_path = $this->input->get('template_path');
		$folder = dirname($template_path);

		switch ($folder) {
			case 'layout_header':
				$up_image_name = 'img_layout_header_up';
				$down_image_name = 'img_layout_header_down';

				break;
			case 'layout_footer':
				$up_image_name = 'img_layout_footer_up';
				$down_image_name = 'img_layout_footer_down';

				break;
			case 'layout_side':
				$up_image_name = 'img_layout_side_up';
				$down_image_name = 'img_layout_side_down';

				break;
			case 'layout_scroll':
				if ($template_path == 'layout_scroll/left.html') {
					$up_image_name = 'img_layout_scroll_l_up';
					$down_image_name = 'img_layout_scroll_l_down';
				} else {
					$up_image_name = 'img_layout_scroll_up';
					$down_image_name = 'img_layout_scroll_down';
				}

				break;
			default:
				$up_image_name = 'img_layout_up';
				$down_image_name = 'img_layout_down';

				break;
		}
		$this->template->assign(['up_image_name' => $up_image_name, 'down_image_name' => $down_image_name]);

		// 인스타그램 연동 설정 정보
		$this->load->library('instagramlibrary');
		$instagram = $this->instagramlibrary->getConfig();
		$this->template->assign('instagram', $instagram);

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin, $template_path);
		$this->template->assign(['layout_config' => $layout_config[$template_path]]);
		$this->template->assign(['template_path' => $template_path]);

		$file_path = $this->template_path();
		$this->template->define(['tpl' => $file_path]);
		$this->template->print_('tpl');
	}

	/* 상품디스플레이 띄우기 화면 */
	public function display_insert(){
		$this->tempate_modules();
		$this->load->model('goodsdisplay');

		if($this->config_system['operation_type']=='light')	$_GET['platform'] = 'responsive';
		if(!$_GET['platform'])								$_GET['platform'] = 'pc';

		/* 기본 상품 디스플레이 생성 */
		for($i=1;$i<=10;$i++){
			$res = $this->goodsdisplay->get_display($i);
			if(!$res){
				$data = array(
					'display_seq' => $i,
					'admin_comment' => '기본 상품 디스플레이 '.$i,
					'regdate' => date('Y-m-d H:i:s')
				);
				$query = $this->db->insert_string('fm_design_display', $data);
				$this->db->query($query);
			}
		}

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$template_path = $_GET['template_path'];

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));
		$this->template->assign(array('skinVersion' => $this->workingMobileSkinVersion));
		$this->template->assign(array('template_path' => $template_path));

		// 스킨 운영설정에 따른 분기 :: 2018-11-22 lwh
		$file_path	= $this->template_path();
		$file_path	= ($this->config_system['operation_type']=='light') ? str_replace('display_insert.html', 'display_insert_light.html', $file_path) : $file_path;
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 상품디스플레이 목록부분 Heavy ajax */
	public function get_display_list_html(){
		$this->tempate_modules();
		$this->load->model('goodsdisplay');
		/* 상품디스플레이 목록 가져오기 */
		if( $_GET['displaykind'] == 'designvideo'){
			$kindsql = 'designvideo';
			$platformsql = 'pc';
		}else{
			$kindsql = 'design';
			$platformsql = $_GET['platform'] ? $_GET['platform'] : 'pc';
		}

		// 페이징 추가 :: 2015-07-31 lwh
		$perpage	= 10;
		$page		= $_GET['page'] ? $_GET['page'] : 1;
		$limit		= "limit " . $page . " , ".$perpage;

		// 전체 갯수
		$cnt_sql	= "select count(*) as cnt from fm_design_display where kind='{$kindsql}' and platform='{$platformsql}'";
		$cnt_query	= $this->db->query($cnt_sql);
		$cnt_res	= $cnt_query->row_array();
		$total_cnt	= $cnt_res['cnt'];

		$sql	= "
			select *,
				(select count(*) from fm_design_display_tab_item as b where a.display_seq = b.display_seq) as goodsCnt
			from fm_design_display as a
			where kind='{$kindsql}' and platform='{$platformsql}'
			order by display_seq desc
		";
		$result			= select_page($perpage,$page,10,$sql,array());
		// 페이징 추가 :: 2015-07-31 lwh
		if($result['page']['totalpage'] > 0){
			$paginlay = pagingtagjs($page, $result['page']['page'], $result['page']['totalpage'], 'load_display_list([:PAGE:])');
		}
		$this->template->assign('pagin',$paginlay);
		$this->template->assign(array('display_list'=>$result));

		$styles = $this->goodsdisplay->get_styles($platformsql);
		$this->template->assign(array('styles'=>$styles));

		$file_path	= $this->skin.'/design/display_list_inc.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 상품디스플레이 목록부분 Light ajax */
	public function get_display_list_light(){
		$this->tempate_modules();
		$this->load->model('goodsdisplay');

		// light 설정 정의 - 고정형 :: 2018-11-30 lwh
		$kindsql		= 'design';
		$platformsql	= 'responsive';

		/* 상품디스플레이 목록 가져오기 */
		$sql = "
			select *,
				(select count(*) from fm_design_display_tab_item as b where a.display_seq = b.display_seq) as goodsCnt
			from fm_design_display as a
			where kind='{$kindsql}' and platform='{$platformsql}'
			order by display_seq desc
		";

		$perpage	= 10;
		$page		= $_GET['page'] ? $_GET['page'] : 1;
		$result		= select_page($perpage,$page,10,$sql,array());
		// 페이징 추가 :: 2015-07-31 lwh
		if($result['page']['totalpage'] > 0){
			$paginlay = pagingtagjs($page, $result['page']['page'], $result['page']['totalpage'], 'load_display_list([:PAGE:])');
		}
		$this->template->assign('pagin',$paginlay);
		$this->template->assign(array('display_list'=>$result));

		$styles = $this->goodsdisplay->get_styles($platformsql);
		$this->template->assign(array('styles'=>$styles));

		$file_path	= $this->skin.'/design/display_list_light.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 게시판 넣기 화면 */
	public function lastest_insert(){
		$this->tempate_modules();
		$this->load->model('layout');
		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$template_path = $_GET['template_path'];

		$query = $this->db->query("select * from fm_boardmanager where id not in ('gs_seller_qna','gs_seller_notice','bulkorder') ");
		$boardList = $query->result_array();
		$this->template->assign(array('boardList'=>$boardList));

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array(
			'styles'			=> $this->Boardmanager->styles
		));

		$this->template->assign(array('template_path' => $template_path));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 게시판 넣기 변경 화면 */
	public function lastest_edit(){
		$aGetParams = $this->input->get();
		$this->tempate_modules();
		$this->load->model('layout');

		$link = isset($aGetParams['link']) ? $aGetParams['link'] : null;
		$target = isset($aGetParams['target']) ? $aGetParams['target'] : null;
		$elementType = isset($aGetParams['elementType']) ? $aGetParams['elementType'] : null;

		$designTplPath = $aGetParams['designTplPath'];
		if (preg_match('%^[a-zA-Z0-9/+]*={0,2}$%', $aGetParams['designTplPath'])) {
			$designTplPath = base64_decode($aGetParams['designTplPath']);
		}
		$designLastestId = $aGetParams['designLastestId'];

		$tmp = explode('/',$designTplPath);
		array_shift($tmp);
		$tplPath = implode('/',$tmp);

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$tplPath);
		$this->template->assign(array('layout_config'=>$layout_config[$tplPath]));

		$this->template->assign(array(
			'tplPath'		=> $designTplPath,
			'designLastestId'	=> $designLastestId
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 가비아 웹FTP */
	public function gabia_webftp(){
		header("location:http://firstmall.kr/popup/webftp/index_firstmall.php?s={$_SERVER['HTTP_HOST']}");
	}

	/* PC 상단바 디자인 설정 JHR */
	public function topBar_design(){


		$this->tempate_modules();

		$template_path = isset($_GET['template_path']) ? $_GET['template_path'] : '';
		$location = dirname($template_path);

		$category_config = skin_configuration($this->designWorkingSkin);
		if(!isset($category_config['topbar'])) $category_config['topbar'] = '';
		$direction = substr($category_config['topbar'],0,1);
		$topbar = explode("|",$category_config['topbar']);
		$this->template->assign(array(
			'allcategory'		=>$topbar[1],
			'category'			=>$topbar[2],
			'brand'				=>$topbar[3],
			'location'			=>$topbar[4],
			'template_path'		=>'_modules/category/category_topBar.html'
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");

	}

	/* 모바일 상단바 디자인 설정 JHR */
	public function mainTopBar_design(){
		$this->tempate_modules();
		$query = $this->db->query("select * from fm_topbar_style  left join fm_topbar_file  on  tab_index = style_index where skin = ? order by tab_seq",$this->designWorkingSkin);
		$data = $query->result_array();
		if(sizeOf($data) == 0){
			$query = $this->db->query("select * from fm_topbar_style  left join fm_topbar_file  on  tab_index = style_index where skin is null or skin = '' order by tab_seq");
			$data = $query->result_array();
		}

		$list["tab_index"] = $data[0]["tab_index"];
		$list["tab_type"] = $data[0]["tab_type"];
		$list["skin"] = $data[0]["skin"];
		$list["tab_styleName"] = $data[0]["tab_style"] != "" ? substr($data[0]["tab_style"],0,strlen($data[0]["tab_style"])-1) : "tabGrey";
		$list["tab_style"] = $data[0]["tab_style"];
		$list["tab_cursor"] = $data[0]["tab_cursor"];
		$list["tab_img_prev"] = $data[0]["tab_img_prev"];
		$list["tab_img_next"] = $data[0]["tab_img_next"];

		$working_skin_path = ROOTPATH."data/skin/".$this->designWorkingSkin."/main";
		$map = directory_map($working_skin_path,true,false);

		foreach	($map as $k => $file_name) if	($file_name == 'guide.html') unset($map[$k]);
		foreach ($data as $row) $tabs[] = $row;

		$tabsArr = array(
			'folders' => $map,
			'tabs' => $tabs
		);

		$template_path = "_modules/common/topbar.html";

		$this->template->assign(array(
			'data'=>$list,
			'tabsData'=>$tabsArr,
			'working_skin'=>$this->designWorkingSkin,
			'template_path'=>$template_path
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 카테고리 네비게이션 디자인 설정 */
	public function category_navigation_design(){


		$this->tempate_modules();

		$template_path = isset($_GET['template_path']) ? $_GET['template_path'] : '';
		$location = dirname($template_path);

		$category_config = skin_configuration($this->designWorkingSkin);
		if(!isset($category_config['category_type'])) $category_config['category_type'] = '';
		$direction = substr($category_config['category_type'],0,1);

		$this->template->assign(array(
			'template_path'		=> $template_path,
			'location'			=> $location,
			'direction'			=>$direction,
			'category_type'		=>$category_config['category_type']
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");

	}

	/* 브랜드 네비게이션 디자인 설정 */
	public function brand_navigation_design(){

		$this->tempate_modules();

		$template_path = isset($_GET['template_path']) ? $_GET['template_path'] : '';
		$location = dirname($template_path);

		$category_config = skin_configuration($this->workingSkin);
		if(!isset($category_config['brand_type'])) $category_config['brand_type'] = '';
		$direction = substr($category_config['brand_type'],0,1);

		$this->template->assign(array(
			'template_path'		=> $template_path,
			'location'			=> $location,
			'direction'			=>$direction,
			'brand_type'		=>$category_config['brand_type']
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");

	}

	/* 지역 네비게이션 디자인 설정 */
	public function location_navigation_design(){

		$this->tempate_modules();

		$template_path = isset($_GET['template_path']) ? $_GET['template_path'] : '';
		$location = dirname($template_path);

		$category_config = skin_configuration($this->workingSkin);
		if(!isset($category_config['location_type'])) $category_config['location_type'] = '';
		$direction = substr($category_config['location_type'],0,1);

		$this->template->assign(array(
			'template_path'		=> $template_path,
			'location'			=> $location,
			'direction'			=>$direction,
			'location_type'		=>$category_config['location_type']
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");

	}

	/* 아이에디터 */
	public function eye_editor(){

		$this->tempate_modules();

		$this->load->helper('file');
		$this->load->helper('directory');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		### SERVICE CHECK
		/*
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('eyeeditor');

		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}
		*/


		$searchKeyword	= isset($_GET['searchKeyword']) ? $_GET['searchKeyword'] : '';
		$useEncode		= isset($_GET['useEncode']) && $_GET['useEncode'] == 'true' ? 'Y' : 'N';

		// 인코딩이 안되어 있는경우 encode 후 탭컨텐츠 로딩
		if($useEncode == 'N'){
			$searchKeyword	= base64_encode($searchKeyword);
		}

		$this->template->define(array('eyeeditor_webftp'=>$this->skin.'/webftp/_eyeeditor_webftp.html'));
		$this->template->assign(array('EYE_EDITOR'=>true));
		$this->template->assign(array('searchKeyword'=>$searchKeyword));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 아이에디터 탭 컨텐츠 반환 */
	public function eye_editor_tabcontents(){
		$aGetParames = $this->input->get();
		$_GET = $aGetParames;
		$sUploadDir = ROOTPATH . 'data/';

		$this->tempate_modules();

		$this->load->helper('file');
		$this->load->helper('directory');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$tabIdx = $_GET['tabIdx'];
		$tplPath = $_GET['tplPath']?$_GET['tplPath']:null;

		/* tpl_path assign */
		$tpl_realpath = ROOTPATH.$tplPath;
		$tpl_fileName = basename($tpl_realpath);
		$tpl_source = read_file($tpl_realpath);
		$searchKeyword = isset($_GET['searchKeyword']) ? addslashes($_GET['searchKeyword']) : '';

		// 파일 타입 검증
		$sExtension	= get_mime_by_extension($tpl_fileName);
		if (in_array($sExtension, array('text/html','text/css','application/x-javascript')) === false) {
			pageBack(getAlert('et001')); //올바른 파일이 아닙니다.
		}

		// 경로 검증
		if (strpos(realpath($tpl_realpath), $sUploadDir) === false) {
			pageBack(getAlert('et001')); //올바른 파일이 아닙니다.
		}

		/* 백업파일 */
		$backup_files = array();
		$backupPath = "data/file_backup/".$tplPath.date('.YmdHis');
		$backupDir = dirname($backupPath);
		$tpl_fileNameForReg = str_replace('.','\.',$tpl_fileName);
		$map = (array)directory_map(ROOTPATH.$backupDir,true);
		rsort($map);
		foreach($map as $k=>$v){
			if(is_file(ROOTPATH.$backupDir.'/'.$v) && preg_match("/{$tpl_fileNameForReg}\.[0-9]{14}/",$v)){
				$backup_files[] = array(
					'path' => $backupDir.'/'.$v,
					'time' => filemtime(ROOTPATH.$backupDir.'/'.$v)
				);

			}
		}

		if(preg_match("/\.css$/",$tplPath)){
			$code_mode = "css";
		}else{
			$code_mode = "htmlmixed";
		}

		$filemtime = filemtime($tpl_realpath);
		/* 레이아웃 설정 assign */
		if(preg_match("/^data\/skin\/([^\/]+)\/(.*)/",$tplPath,$matches)){
			$skin = $matches[1];
			$skinTplPath = $matches[2];

			$layout_config = layout_config_load($skin,$skinTplPath);

			$tpl_name = $layout_config[$skinTplPath]['tpl_desc'];

			if(preg_match("/\.html$/",$tplPath)){

				$tpl_url = $layout_config[$skinTplPath]['tpl_page'] ? $this->layout->get_tpl_page_url($skinTplPath) : "/".substr($skinTplPath,0,strpos($skinTplPath,'.'));

				if($skin!=$this->designWorkingSkin){
					$tpl_url .= "&previewSkin=" . $skin;
				}
			}else{
				$tpl_url = "/".$tplPath;
			}


		}else{
			$skin = null;
			$skinTplPath = null;
			$tpl_name = null;
			$tpl_url = "/".$tplPath;
		}

		$this->template->assign(array(
			'tpl_source'	=> $tpl_source,
			'backup_files'	=> $backup_files,
			'code_mode'		=> $code_mode,
			'filemtime'		=> $filemtime,
			'tpl_name'		=> $tpl_name,
			'tpl_url'		=> $tpl_url,
			'skin'			=> $skin,
			'skinTplPath'	=> $skinTplPath,
		));

		// 태그 내 속성까지 검색 시 " 처리가 안되어있어 이스케이프 처리 추가 :: 2019-02-22 pjw
		$_GET['searchKeyword'] = base64_decode($_GET['searchKeyword']);
		$_GET['searchKeyword'] = preg_replace("/\"/", "\\\"", $_GET['searchKeyword']);
		$_GET['searchKeyword'] = preg_replace("/\n/", "<br>", $_GET['searchKeyword']);
		$_GET['searchKeyword'] = preg_replace("/\{/", "\\{", $_GET['searchKeyword']);
		$_GET['searchKeyword'] = preg_replace("/\}/", "\\}", $_GET['searchKeyword']);
		$_GET['searchKeyword'] = explode('<br>', $_GET['searchKeyword']);
		$_GET['searchKeyword'] = $_GET['searchKeyword'][0];

		$this->template->assign($_GET);
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 플래시매직 결제창 */
	public function flash_payment(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}
		$param = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=MAGIC_FLASH";
		$param = makeEncriptParam($param);

		$this->template->assign('param',$param);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	function editorinsertmenu(){
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$cfg_goods = config_load("goods");
		if( $cfg_goods['ucc_domain'] && $cfg_goods['ucc_key'] ){
			$cfg_goods['video_use']=  'Y';
		}
		$this->template->assign('cfg_goods',$cfg_goods);


		$this->template->define(array('editor_menu'=>$this->skin."/design/_editor_menu.html"));
		$editor_menu = $this->uri->rsegments[count($this->uri->rsegments)];
		$this->template->assign(array('selected_editor_menu'=>$editor_menu));
	}

	/* 에딧터 > 플래시 넣기 화면 */
	public function flash_editor_insert(){
		$this->tempate_modules();
		$this->editorinsertmenu();

		$template_path = $_GET['template_path'];

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array('template_path' => $template_path));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}


	/* 에딧터 > 플래시 목록부분 ajax */
	public function get_flash_editor_list_html(){
		$this->tempate_modules();

		/**
		 * list setting
		**/
		$_GET['page']	 = ($_GET['page']) ? intval($_GET['page']):1;
		$_GET['perpage'] = ($_GET['perpage']) ? intval($_GET['perpage']):15;

		/* 플래시 목록 가져오기 */
		$sql = 'select SQL_CALC_FOUND_ROWS *,(select url from fm_design_flash_file where flash_seq=a.flash_seq and type="img" and (url like "%flash_%" or url like "%thumb%"  ) limit 1) url from fm_design_flash a order by flash_seq desc';

		$result = select_page($_GET['perpage'],$_GET['page'],10,$sql,'');
		$result['page']['querystring'] = get_args_list();
		$this->template->assign(array('flash_list'=>$result['record']));
		$this->template->assign('page',$result['page']);

		$file_path	= $this->skin.'/design/flash_editor_list_inc.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}




	/* 에딧터 > 동영상 삽입 화면 */
	public function video_editor_insert(){

		$this->tempate_modules();
		$this->editorinsertmenu();

		$cfg_goods = config_load("goods");
		if( $cfg_goods['ucc_domain'] && $cfg_goods['ucc_key'] ){
			$cfg_goods['video_use']=  'Y';
		}
		$this->template->assign('cfg_goods',$cfg_goods);

		$this->template->assign(array('setMode' => $this->session->userdata('setMode')));
		$template_path = $_GET['template_path'];

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array('template_path' => $template_path));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 에딧터 > 동영상 목록부분 ajax */
	public function get_video_editor_list_html(){
		$this->tempate_modules();

		/* 동영상 목록 가져오기 */
		$this->load->model('videofiles');

		/**
		 * list setting
		**/
		$videosc['orderby']	= (!empty($_GET['orderby'])) ?	$_GET['orderby']:' seq desc, sort asc ';
		$videosc['sort']		= (!empty($_GET['sort'])) ?			$_GET['sort']:'';
		$videosc['page']		= (!empty($_GET['page'])) ?		intval($_GET['page']):0;
		$videosc['perpage']	= (!empty($_GET['perpage'])) ?	intval($_GET['perpage']):5;

		if( !empty($_GET['upkind']) ) $videosc['upkind']	= $_GET['upkind'];
		$video_list = $this->videofiles->videofiles_list($videosc);

		$videosc['searchcount']	= $video_list['count'];
		$videosc['total_page']		= ceil($videosc['searchcount']	 / $videosc['perpage']);
		$videosc['totalcount']		= $this->videofiles->get_item_total_count($videosc);

		if($video_list['result']) $this->template->assign('video_list',$video_list['result']);

		$returnurl = "./video_editor_insert?";
		$paginlay =  pagingtag($videosc['searchcount']	,$videosc['perpage'],$returnurl, getLinkFilter('',array_keys($videosc)),'page','" ' );

		if($videosc['searchcount'] > 0) {
			$paginlay = (!empty($paginlay)) ? $paginlay:'<p><a class="on red">1</a><p>';
		}
		$this->template->assign('videopagin',$paginlay);

		$file_path	= $this->skin.'/design/video_editor_list_inc.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function display_desc_layer(){
		$this->admin_menu();
		$this->tempate_modules();
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 스킨설정 */
	public function font(){
		#### AUTH
		$this->admin_menu();
		$this->tempate_modules();

		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function myfont(){
		$this->admin_menu();
		$this->tempate_modules();
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function mobile_quick_design(){
		$this->tempate_modules();

		$themes = $this->designmodel->get_mobile_themes();
		$this->template->assign('themes',$themes);

		$cssPath = $this->designmodel->get_mobile_buttons_css_path();
		$this->template->assign('cssPath',$cssPath);

		if( serviceLimit('H_ST')) {
			$skin = $this->workingStoremobileSkin;
		}else{
			$skin = $this->workingMobileSkin;
		}

		$skin_configuration = skin_configuration($skin);
		$this->template->assign($skin_configuration);

		if($skin_configuration['mobile_version']=='2'){
			$this->template->define(array('tpl'=>'default/design/mobile_quick_design.html'));
		}
		if($skin_configuration['mobile_version']=='3'){
			$this->template->define(array('tpl'=>'default/design/mobile_quick_design_ver3.html'));
		}
		$this->template->print_("tpl");
	}

	public function pc_quick_design(){
		if(preg_match("/^mobile_/i",$this->designWorkingSkin) || preg_match("/^storemobile_/i",$this->designWorkingSkin)){
			echo "<span class='red'>Mobile 디자인환경에서는 PC 버튼 설정이 불가능합니다.<br />디자인환경을 변경해주세요.</span>";
			exit;
		}

		$this->tempate_modules();

		$buttonDirectoryPath = "/data/skin/".$this->designWorkingSkin."/images/buttons/";
		$iconDirectoryPath = "/data/icon/goods_status/";
		$buttonImages = array();

		$buttonImages['goods_view']['title'] = '상품상세 페이지용 버튼';
		$buttonImages['goods_view']['buttons'][] = array('code'=>'btn_buy','name'=>'바로구매','filename'=>'btn_buy.gif');
		$buttonImages['goods_view']['buttons'][] = array('code'=>'btn_cart','name'=>'장바구니','filename'=>'btn_cart.gif');
		$buttonImages['goods_view']['buttons'][] = array('code'=>'btn_wish','name'=>'위시리스트','filename'=>'btn_wish.gif');
		$buttonImages['goods_view']['buttons'][] = array('code'=>'btn_runout','name'=>'품절','filename'=>'btn_runout.gif');
		$buttonImages['goods_view']['buttons'][] = array('code'=>'btn_purchasing','name'=>'재고 확보 중','filename'=>'btn_purchasing.gif');
		$buttonImages['goods_view']['buttons'][] = array('code'=>'btn_restock_notify','name'=>'재입고 알림','filename'=>'btn_restock_notify.gif');
		$buttonImages['goods_view']['buttons'][] = array('code'=>'btn_unsold','name'=>'판매중지','filename'=>'btn_unsold.gif');

		$buttonImages['cart']['title'] = '장바구니용 버튼';
		$buttonImages['cart']['buttons'][] = array('code'=>'btn_order_all','name'=>'전체상품 주문하기','filename'=>'btn_order_all.gif');
		$buttonImages['cart']['buttons'][] = array('code'=>'btn_order_selected','name'=>'선택상품 주문하기','filename'=>'btn_order_selected.gif');
		$buttonImages['cart']['buttons'][] = array('code'=>'btn_shopping_continue','name'=>'계속 쇼핑하기','filename'=>'btn_shopping_continue.gif');

		$buttonImages['settle']['title'] = '주문하기 페이지용 버튼';
		$buttonImages['settle']['buttons'][] = array('code'=>'btn_order','name'=>' 주문하기','filename'=>'btn_order.gif');
		$buttonImages['settle']['buttons'][] = array('code'=>'btn_pay','name'=>'결제하기','filename'=>'btn_pay.gif');
		$buttonImages['settle']['buttons'][] = array('code'=>'btn_order_cart','name'=>'장바구니로','filename'=>'btn_order_cart.gif');
		$buttonImages['settle']['buttons'][] = array('code'=>'btn_shopping_continue_s','name'=>'쇼핑 계속하기','filename'=>'btn_shopping_continue_s.gif');

		$buttonImages['login']['title'] = '로그인 페이지용 버튼';
		$buttonImages['login']['buttons'][] = array('code'=>'btn_login','name'=>'로그인','filename'=>'btn_login.gif');
		$buttonImages['login']['buttons'][] = array('code'=>'btn_login_join','name'=>'회원가입','filename'=>'btn_login_join.gif');
		$buttonImages['login']['buttons'][] = array('code'=>'btn_login_idpw','name'=>'아이디/비밀번호 찾기','filename'=>'btn_login_idpw.gif');
		$buttonImages['login']['buttons'][] = array('code'=>'btn_order_nonmem','name'=>'비회원으로 구매하기','filename'=>'btn_order_nonmem.gif');

		$buttonImages['join']['title'] = '회원가입 페이지용 버튼';
		$buttonImages['join']['buttons'][] = array('code'=>'btn_join','name'=>'회원가입','filename'=>'btn_join.gif');
		$buttonImages['join']['buttons'][] = array('code'=>'btn_myinfo','name'=>'회원정보수정','filename'=>'btn_myinfo.gif');
		$buttonImages['join']['buttons'][] = array('code'=>'btn_go_login','name'=>'로그인','filename'=>'btn_go_login.gif');
		$buttonImages['join']['buttons'][] = array('code'=>'btn_shopping','name'=>'쇼핑하러가기','filename'=>'btn_shopping.gif');

		$buttonImages['etc']['title'] = '회원가입 페이지용 버튼';
		$buttonImages['etc']['buttons'][] = array('code'=>'btn_list','name'=>'주문목록 돌아가기','filename'=>'btn_list.gif');
		$buttonImages['etc']['buttons'][] = array('code'=>'btn_list_return','name'=>'반품목록 돌아가기','filename'=>'btn_list_return.gif');
		$buttonImages['etc']['buttons'][] = array('code'=>'btn_list_refund','name'=>'환불목록 돌아가기','filename'=>'btn_list_refund.gif');
		$buttonImages['etc']['buttons'][] = array('code'=>'btn_ok','name'=>'확인','filename'=>'btn_ok.gif');
		$buttonImages['etc']['buttons'][] = array('code'=>'btn_cancel','name'=>'취소','filename'=>'btn_cancel.gif');

		$buttonImages['icon']['title'] = '리스트 페이지용 아이콘 (아래의 아이콘은 모든 스킨에 공통 사용됩니다)';
		$buttonImages['icon']['buttons'][] = array('code'=>'icon_list_soldout','name'=>'품절','filename'=>'icon_list_soldout.gif');
		$buttonImages['icon']['buttons'][] = array('code'=>'icon_list_warehousing','name'=>'재고 확보 중','filename'=>'icon_list_warehousing.gif');
		$buttonImages['icon']['buttons'][] = array('code'=>'icon_list_stop','name'=>'판매 중지','filename'=>'icon_list_stop.gif');
		$buttonImages['icon']['buttons'][] = array('code'=>'icon_list_cpn','name'=>'쿠폰','filename'=>'icon_list_cpn.gif');
		$buttonImages['icon']['buttons'][] = array('code'=>'icon_list_freedlv','name'=>'무료배송','filename'=>'icon_list_freedlv.gif');
		$buttonImages['icon']['buttons'][] = array('code'=>'icon_list_video','name'=>'동영상','filename'=>'icon_list_video.gif');

		foreach($buttonImages as $k=>$v){
			if($k=='login') $buttonImages[$k]['cols'] = 4;
			else $buttonImages[$k]['cols'] = 7;
		}

		$this->template->assign(array(
			'buttonImages' => $buttonImages,
			'buttonDirectoryPath' => $buttonDirectoryPath,
			'iconDirectoryPath' => $iconDirectoryPath,
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	// 반응형 퀵디자인 신규 추가 :: 2019-05-28 pjw
	public function responsive_quick_design(){
		$this->tempate_modules();

		// 현재 사용 중인 테마 가져옴 (설정정보 없을 시 기본으로 가져옴)
		$theme		= $this->designmodel->get_responsive_theme();

		// 기본 테마 가져오기
//		$theme = array(
//			'theme'		=> 'basic',
//			'colors'	=> $this->designmodel->get_responsive_default_theme('basic'),
//		);

		// 테마 샘플 페이지 출력
		$this->template->assign($theme);
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	// 반응형 퀵디자인 샘플용 css :: 2019-05-30 pjw
	public function quick_design_css(){

		// 퀵디자인 설정에서 받아온 값 정의
		$theme_key		= $this->input->get('theme');
		$custom_color	= $this->input->get('color');
		$select_colors	= $this->input->post();
		$theme_data		= array(
			'custom_color'	=> $custom_color,
			'select_colors'	=> $select_colors,
		);

		// 해당 값으로 css에 assign할 색상배열 가져옴
		$colors			= $this->designmodel->get_responsive_select_theme($theme_key, $theme_data);
		$css_contents	= file_get_contents(ROOTPATH.'admin/skin/'.$this->template_path());

		// color 배열 키에 맞는 값을 치환
		foreach($colors as $key => $color){
			$css_contents = preg_replace('/\{colors\.'.$key.'\}/', $color, $css_contents);
		}

		echo $css_contents;
	}


	public function codes(){
		$this->admin_menu();
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('skin');

		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		$query = $this->db->query("select code_page from fm_design_codes group by code_page order by code_page");
		$arr_code_page = $query->result_array();
		$this->template->assign(array('arr_code_page'=>$arr_code_page));

		/**
		 * list setting
		**/
		$_GET['page']	 = ($_GET['page']) ? intval($_GET['page']):1;
		$_GET['perpage'] = ($_GET['perpage']) ? intval($_GET['perpage']):15;

		$sql = "select * from fm_design_codes order by code_seq desc";

		$result = select_page($_GET['perpage'],$_GET['page'],10,$sql,'');
		$result['page']['querystring'] = get_args_list();
		$this->template->assign($result);

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 우측 추천상품 생성,수정 화면 */
	public function recomm_goods_edit(){
		$this->tempate_modules();
		$this->load->model('goodsmodel');
		$this->load->model('goodsdisplay');
		$this->load->model('eventmodel');
		$this->load->helper('text');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$tmp_data = layout_config_autoload($this->designWorkingSkin,$this->skin);
		$use_layout_right = $tmp_data[$this->skin]['layoutScrollRight'];

		/* 현재 사용중인 우측스크롤 페이지 지정*/
		//$template_path = $_GET['template_path'];
		$template_path = $use_layout_right;
		$image_decorations = array();

		/* 우측 추천상품 정보 가져오기 */
		$this->load->model('goodsmodel');
		$arr_data_seq = $this->goodsmodel->get_recommend_goods_list(1,5,'admin');

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		/* 디스플레이 상품 목록 */
		$display_item = $this->goodsmodel->get_recommend_item($arr_data_seq);
		$this->template->assign(array(
			'template_path'		=> $template_path,
			'display_item'		=> $display_item
		));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 배너 넣기 화면 */
	public function banner_insert(){
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$template_path = $_GET['template_path'];

		/* 레이아웃 설정 assign */
		$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
		$this->template->assign(array('layout_config'=>$layout_config[$template_path]));

		$this->template->assign(array('template_path' => $template_path));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 배너 생성 화면 */
	public function banner_create(){
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$template_path = $_GET['template_path'];

		$styles = $this->designmodel->get_banner_styles();

		$return_url = get_connet_protocol().$_SERVER['HTTP_HOST']."/admin/design/banner_insert?template_path=".urlencode($template_path);

		$this->template->assign(array('template_path' => $template_path,'return_url' => $return_url,'styles'=>$styles));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 배너 수정 화면 */
	public function banner_edit(){

		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */
		// [반응형스킨] platform 인자값 추가 (기존 프로세스 영향없음) :: 2018-11-08 pjw
				// 배너 스타일

		$requestGet = $this->input->get();
		if($this->config_system['operation_type']=='light') $requestGet['platform'] = 'responsive';
		$banner_styles = $this->designmodel->get_banner_styles($requestGet['platform']);

		$banner_seq = $requestGet['banner_seq'];
		$template_path = $requestGet['template_path'];

		// [반응형스킨] 페이지타입 추가 :: 2018-11-08 pjw
		$page_type = $requestGet['page_type'];
		// 페이지 타입 없는 경우 DB 페이지 타입 체크 2020-03-30
		if (isset($requestGet['page_type']) === false) {
			$query = $this->db->query("select * from fm_design_banner where skin=? and banner_seq=?",array($this->designWorkingSkin,$requestGet['banner_seq']));
			$result = $query->row_array();
			$page_type = $result['page_type'];
		}
		$tab = (isset($requestGet['tab'])) ? $requestGet['tab'] : '';

		$this->template->assign(array(
			'template_path'=>$template_path,
			'banner_styles'=>$banner_styles,
			'banner_seq'=>$banner_seq,
			'page_type' => $page_type,
			'tab' => $tab,
		));

		$file_path	= $this->template_path();
		if($this->config_system['operation_type']=='light'){
			$file_path	= str_replace('banner_edit.html', 'banner_edit_light.html', $file_path);
		}
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 배너 목록부분 ajax */
	public function get_banner_list_html(){
		$this->tempate_modules();

		$styles = $this->designmodel->get_banner_styles();

		/* 플래시 목록 가져오기 */
		$sql = 'select SQL_CALC_FOUND_ROWS * from fm_design_banner where skin=? order by banner_seq desc';
		$query = $this->db->query($sql,$this->designWorkingSkin);
		$banner_list = $query->result_array();

		$this->template->assign(array('banner_list'=>$banner_list,'styles'=>$styles));

		$file_path	= $this->skin.'/design/banner_list_inc.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 배너 설정 로딩 ajax */
	public function banner_setting_load(){
		if($_GET['banner_seq']){

			// 불러오기
			$query = $this->db->query("select * from fm_design_banner where skin=? and banner_seq=?",array($this->designWorkingSkin,$_GET['banner_seq']));
			$result = $query->row_array();

			// 스타일 설정 불러와서 병합
			$styles = $this->designmodel->get_banner_styles();
			$result = array_merge($styles[$result['style']],$result);

			$query = $this->db->query("select * from fm_design_banner_item where skin=? and banner_seq=?",array($this->designWorkingSkin,$_GET['banner_seq']));
			$result_item = $query->result_array();

			foreach($result_item as $k=>$item){
				if($item['image']){
					list($item['image_width'], $item['image_height']) = @getimagesize(ROOTPATH."data/skin/".$this->designWorkingSkin."/".$item['image']);
				}
				$result_item[$k] = $item;
			}

			$result['images'] = $result_item;

			echo json_encode($result);

		}else if($_GET['style']){

			// 스타일 설정, 샘플 병합
			$styles = $this->designmodel->get_banner_styles();
			$sample = $this->designmodel->get_banner_sample($_GET['style']);

			$result = array_merge($styles[$_GET['style']],$sample);
			$result['style'] = $_GET['style'];
			$result['skin'] = $this->designWorkingSkin;

			foreach($result['images'] as $k=>$item){
				if($item['image']){
					list($item['image_width'], $item['image_height']) = @getimagesize(ROOTPATH.$item['image']);
				}
				$result['images'][$k] = $item;
			}

			echo json_encode($result);
		}
	}

	/* 배너 스크립트 반환 */
	public function banner_html_ajax(){
		$this->template->include_('showDesignBanner');
		echo showDesignBanner($_GET['banner_seq'],true);
	}

	// 팝업 노출 조건 추가 2015-10-01 jhr
	public function popup_condition(){
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('design_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$popup_seq = $_GET['popup_seq'];
		if($popup_seq){
			$query = $this->db->query("select `popup_condition` from fm_design_popup where popup_seq = ?",$popup_seq);
			$popup_data = $query->row_array();
		}else{
			$popup_data = array();
		}

		$popup_condition = array();

		if($popup_data['popup_condition']){
			$popup_condition = unserialize($popup_data['popup_condition']);
			$this->load->model('goodsmodel');
			$this->load->model('categorymodel');
			$this->load->model('brandmodel');
			$this->load->model('locationmodel');

			switch($popup_condition['view']){
				case "category":
					$issuecategorysView = array();
					if($popup_condition['issueCategoryViewCode']){
						foreach($popup_condition['issueCategoryViewCode'] as $key =>$data){
							$issuecategorysView[$key]['code'] = $data;
							$issuecategorysView[$key]['category'] = $this->categorymodel->get_category_name($data);
						}
					}
					$this->template->assign(array('issuecategorysView'=>$issuecategorysView));
					break;
				case "brand":
					$issueBrandView = array();
					if($popup_condition['issueBrandViewCode']){
						foreach($popup_condition['issueBrandViewCode'] as $key =>$data){
							$issueBrandView[$key]['code'] = $data;
							$issueBrandView[$key]['brand'] = $this->brandmodel->get_brand_name($data);
						}
					}
					$this->template->assign(array('issueBrandView'=>$issueBrandView));
					break;
				case "goods":
					$issueGoods = array();
						if($popup_condition['issueGoods']){
						foreach($popup_condition['issueGoods'] as $key => $tmp) $arrGoodsSeq[] =  $tmp;
						$goods = $this->goodsmodel->get_goods_list($arrGoodsSeq,'thumbView');
						foreach($popup_condition['issueGoods'] as $key => $data) $issueGoods[$key] = $goods[$data];
					}
					$this->template->assign(array('issueGoods'=>$issueGoods));
					break;
			}

			if($popup_condition['category'] != 'all'){
				$issuecategorys = array();
				if($popup_condition['issueCategoryCode']){
					foreach($popup_condition['issueCategoryCode'] as $key =>$data){
						$issuecategorys[$key]['code'] = $data;
						$issuecategorys[$key]['category'] = $this->categorymodel->get_category_name($data);
					}
				}
				$this->template->assign(array('issuecategorys'=>$issuecategorys));
			}

			if($popup_condition['brand'] != 'all'){
				$issueBrand = array();
				if($popup_condition['issueBrandCode']){
					foreach($popup_condition['issueBrandCode'] as $key =>$data){
						$issueBrand[$key]['code'] = $data;
						$issueBrand[$key]['brand'] = $this->brandmodel->get_brand_name($data);
					}
				}
				$this->template->assign(array('issueBrand'=>$issueBrand));
			}

			if($popup_condition['location'] != 'all'){
				$issueLocation = array();
				if($popup_condition['issueLocationCode']){
					foreach($popup_condition['issueLocationCode'] as $key =>$data){
						$issueLocation[$key]['code'] = $data;
						$issueLocation[$key]['location'] = $this->locationmodel->get_location_name($data);
					}
				}
				$this->template->assign(array('issueLocation'=>$issueLocation));
			}
		}else{//최초 등록시
			$popup_condition['view'] = 'all';
			$popup_condition['brand'] = 'all';
			$popup_condition['category'] = 'all';
			$popup_condition['location'] = 'all';
		}

		$this->template->assign(array('popup_condition' => $popup_condition));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 팝업 배너 설정 로딩 ajax */
	public function popup_banner_setting_load(){

		if($_GET['banner_seq']){

			// 불러오기
			$query = $this->db->query("select * from fm_design_popup_banner where banner_seq=?",array($_GET['banner_seq']));
			$result = $query->row_array();

			// 스타일 설정 불러와서 병합
			$styles = $this->designmodel->get_banner_styles();
			if($styles[$result['style']])		$result = array_merge($styles[$result['style']],$result);

			$query = $this->db->query("select * from fm_design_popup_banner_item where  banner_seq=?",array($_GET['banner_seq']));
			$result_item = $query->result_array();

			foreach($result_item as $k=>$item){
				if($item['image']){
					$img_path = preg_replace("/^\//","",$item['image']);
					list($item['image_width'], $item['image_height']) = @getimagesize(ROOTPATH.$item['image']);
				}
				$result_item[$k] = $item;
			}

			$result['images'] = $result_item;

			echo json_encode($result);

		}else if($_GET['style']){

			// 스타일 설정, 샘플 병합
			$styles = $this->designmodel->get_popup_banner_styles();
			$sample = $this->designmodel->get_popup_banner_sample($_GET['style']);

			$result = array_merge($styles[$_GET['style']],$sample);
			$result['style'] = $_GET['style'];
			$result['skin'] = $this->designWorkingSkin;

			foreach($result['images'] as $k=>$item){
				if($item['image']){
					list($item['image_width'], $item['image_height']) = @getimagesize(ROOTPATH.$item['image']);
				}
				$result['images'][$k] = $item;
			}

			echo json_encode($result);
		}
	}

	public function image_upload(){
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 이미지 업로드 */
	public function upload_file(){
		$error		= array('status' => 0,'msg' => '업로드 실패하였습니다.','desc' => '업로드 실패');
		$folder		= "data/tmp/";
		$imgtype	= $_POST['imgtype'];
		$idx		= $_POST['idx'];
		$division	= $_POST['division'];
		$selector	= $_POST['selector'];

		$filename	= 'temp_'.time();//새로운이름으로
		$tmp						= getimagesize($_FILES['Filedata']['tmp_name']);
		$_FILES['Filedata']['type']	= $tmp['mime'];
		$config['upload_path']		= $folder;
		$config['allowed_types']	= 'jpeg|jpg|png|gif';
		$config['file_name']		= $filename;
		if($imgtype == 'file')		$config['allowed_types'] = '*';
		$this->load->library('upload', $config);
		if ( ! $this->upload->do_upload('Filedata')){
			$result		= array('status' => '0','error' => $this->upload->display_errors());
		}else{
			$fileinfo	= $this->upload->data();
			$result		= array('status'	=> '1',
								'division'	=> $division,
								'imgtype'	=> $imgtype,
								'selector'	=> $selector,
								'tmpFile'	=> $folder.$filename.$fileinfo['file_ext'],
								'idx'		=> $idx,
								'width'		=> $tmp[0],
								'height'	=> $tmp[1]);
		}

		echo "[".json_encode($result)."]";
	}

	### 디스플레이 정보 추출 Ajax :: 2015-12-21 lwh
	public function display_info(){
		$display_seq = $_GET['display_seq'];
		if($display_seq){
			$this->load->model('goodsdisplay');
			$res = $this->goodsdisplay->get_display_tab($display_seq);
			$limit_func = check_display_version($res[0]['platform'],array($res[0]['style']=>''));
			$limit_func = json_decode(base64_decode($limit_func[$res[0]['style']]['limit_func']));
			$result = array('cnt'=>count($res),'limit_func'=>$limit_func);
		}else{
			$result = array('cnt'=>0);
		}

		echo json_encode($result);
	}

	/*아이디자인 test로그인 시 레이어창*/
	public function t_id_list(){

		$file_path	= $this->template_path();

		$this->load->model('designmodel');
		$mall_t_list = $this->designmodel->mall_t_id_list();

		if($mall_t_list != '') {
			$p_test = array();
			foreach($mall_t_list as $row) {
				$row['status'] = ($row['status'] == 'hold') ? '미승인' : '승인';
				$row['type'] = ($row['business_seq']) ? '기업' : '개인';
				$p_test[] = $row;
			}
			$this->template->assign(array('mall_i_test' => $p_test));
		} else {
			$not_t_list = "설정된 테스트 계정이 없습니다.";
			$this->template->assign('not_t_list', $not_t_list);
		}

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}
	/*아이디자인 test 레이어창 end*/

	// 카테고리의 디스플레이  상품 디스플레이 데이터로 전환
	public function change_display_params($params, $platform){
		$ret = '';
		if	($params) {
			if	($platform == 'pc') {
				$ret['style']							= $params['list_style'];
				$ret['count_w']							= $params['list_count_w'];
				$ret['count_h']							= $params['list_count_h'];
				$ret['paging_use']						= $params['list_paging_use'];
				$ret['image_size']						= $params['list_image_size'];
				$ret['text_align']						= $params['list_text_align'];
				$ret['image_decorations']				= $params['list_image_decorations'];
				$ret['info_settings']					= $params['list_info_settings'];
				$ret['goods_status']					= explode('|',$params['list_goods_status']);
				$ret['default_sort']					= $params['list_default_sort'];
				$ret['count_w_lattice_b']				= $params['list_count_w_lattice_b'];
				$ret['count_h_lattice_b']				= $params['list_count_h_lattice_b'];
				$ret['count_h_list']					= $params['list_count_h_list'];
				$ret['image_size_lattice_b']			= $params['list_image_size_lattice_b'];
				$ret['image_size_list']					= $params['list_image_size_list'];
				$ret['img_opt_lattice_a']				= $params['img_opt_lattice_a'];
				$ret['img_padding_lattice_a']			= $params['img_padding_lattice_a'];
				$ret['image_decoration_type']			= $params['image_decoration_type'];
				$ret['image_decoration_favorite_key']	= $params['image_decoration_favorite_key'];
				$ret['image_decoration_favorite']		= $params['image_decoration_favorite'];
				$ret['goods_decoration_type']			= $params['goods_decoration_type'];
				$ret['goods_decoration_favorite_key']	= $params['goods_decoration_favorite_key'];
				$ret['goods_decoration_favorite']		= $params['goods_decoration_favorite'];
			}else{
				$ret['m_style']							= $params['m_list_style'];
				$ret['m_count_w']						= $params['m_list_count_w'];
				$ret['m_count_h']						= $params['m_list_count_h'];
				$ret['m_count_r']						= $params['m_list_count_r'];
				$ret['m_paging_use']					= $params['m_list_paging_use'];
				$ret['m_image_size']					= $params['m_list_image_size'];
				$ret['text_align']						= $params['m_list_text_align'];
				$ret['image_decorations']				= $params['m_list_image_decorations'];
				$ret['info_settings']					= $params['m_list_info_settings'];
				$ret['m_goods_status']					= explode('|',$params['m_list_goods_status']);
				$ret['m_default_sort']					= $params['m_list_default_sort'];
				$ret['image_decoration_type']			= $params['m_image_decoration_type'];
				$ret['image_decoration_favorite_key']	= $params['m_image_decoration_favorite_key'];
				$ret['image_decoration_favorite']		= $params['m_image_decoration_favorite'];
				$ret['goods_decoration_type']			= $params['m_goods_decoration_type'];
				$ret['goods_decoration_favorite_key']	= $params['m_goods_decoration_favorite_key'];
				$ret['goods_decoration_favorite']		= $params['m_goods_decoration_favorite'];
				$ret['m_list_use']						= $params['m_list_use'];
				$ret['mobile_h']						= $params['m_list_mobile_h'];
			}
		}
		return $ret;
	}

	//상품디스플레이 자주쓰는 꾸미기 정보
	function favorite_decorations_info(){
		$this->load->model('designmodel');
		$key			= $_POST['key'];
		$type			= $_POST['type'];
		$platform		= $_POST['platform'];
		$ret = $this->designmodel->get_favorite_decorations($type, $platform, $key);

		echo json_encode($ret);
	}

	// [반응형스킨] 스킨타입별 목록 및 갯수 정보 :: 2018-10-31 pjw
	function get_skintype_info(){
		$mode		= !empty($_GET['mode']) ? $_GET['mode'] : 'list';
		$skin_type  = $_GET['skin_type'];

		$result = array();
		$result = $this->designmodel->get_skin_list_type($skin_type, 'cnt');

		echo json_encode($result);
	}

	// 텍스트 수정기능 신규 추가 :: 2019-01-25 pjw
	function text_edit(){
		// html dom 파서
		$this->load->helper('html_dom_helper');
		$this->load->helper('file');
		$this->tempate_modules();

		// 기본 파라미터 가져온
		$template_path	= $this->input->get('template_path');
		$txt_index		= $this->input->get('txt_index');
		$tag_name		= $this->input->get('tag_name');
		$link			= $this->input->get('link');
		$target			= $this->input->get('target');
		$target			= !empty($target) ? $target : '_self';
		$is_anchor		= !empty($link) ? true : false;
		$link			= $is_anchor ? base64_decode($link) : '';

		// 파일경로 세팅
		$template_path = base64_decode($template_path);
		$template_path = str_replace($this->designWorkingSkin.'/', '', $template_path);

		// 해당 템플릿 파일 로드
		$file_path = ROOTPATH."data/skin/".$this->designWorkingSkin.'/'.$template_path;
		if(file_exists($file_path)){

			$origin_text	= ''; // 원본 소스

			// 템플릿 소스 읽음
			$fp = fopen($file_path, "r");

			// 파일이 정상적으로 오픈 되었을 때 실행
			if($fp){
				while(!feof($fp)) {
			        $origin_text .= fgets($fp,2048);
			    }
			}
			fclose($fp);

			// 파일 내용을 html 파싱
			$html = str_get_html($origin_text,false,false,"utf-8", false);

			// 수정할 텍스트객체 index로 검색 후 치환
			$txt		= $html->find('[designElement="text"]', $txt_index)->innertext;
			$txt_search = base64_encode($txt);

			// 데이터 바인딩
			$this->template->assign(array(
				'template_path'		=> $template_path,
				'txt'				=> $txt,
				'txt_index'			=> $txt_index,
				'txt_search'		=> $txt_search,
				'tag_name'			=> $tag_name,
				'link'				=> $link,
				'target'			=> $target,
				'is_anchor'			=> $is_anchor,
			));

			// 레이아웃 설정 assign
			$this->load->model('layout');
			$layout_config = layout_config_load($this->designWorkingSkin,$template_path);
			$this->template->assign(array('layout_config' => $layout_config[$template_path]));

			// 템플릿 출력
			$this->template->define(array('tpl'=>$this->template_path()));
			$this->template->print_("tpl");

		}else{
			openDialogAlert("파일경로를 찾을 수 없습니다.",400,140,'parent',"parent.parent.document.location.reload();");
		}


	}
}

/* End of file design.php */
/* Location: ./app/controllers/admin/design.php */