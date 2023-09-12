<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class marketing extends admin_base {

	public function __construct() {
		parent::__construct();
	}

	public function index()
	{
		redirect("/admin/marketing/marketplace_url");
	}

	public function login(){
		$this->load->model('marketingAdminModel');

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->assign(array('vendor'=>$vendor));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function logout(){
		$this->load->model('marketingAdminModel');

		$vcode = $this->session->userdata('marketing');
		$this->marketingAdminModel->set_session_logout();

		pageRedirect("/admin/marketing/login?v=".$vcode, "", "top");
	}

	public function marketplace()
	{
	    /* 관리자 권한 체크 : 시작 */
	    $this->load->model('authmodel');
	    $auth = $this->authmodel->manager_limit_act('marketplace_view');
	    if(!$auth){
	        pageBack($this->auth_msg);
	        exit;
	    }
	    
		$params = array();
		$this->load->helper('readurl');
		$gabiaPageUrl = get_connet_protocol()."interface.firstmall.kr/firstmall_plus/marketing.html";
		$html = readurl($gabiaPageUrl);
		$html = replace_connect_protocol($html);
		$this->template->assign(array('gabiaPage'=>$html));
		$this->admin_menu();
		$this->tempate_modules();
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 입점마케팅 전체 행 갯수 */
	function file_rows(){
		$this->load->model('goodsmodel');
		$markets	= array('all','summary');
		foreach($markets AS $val){
			$last_update_date = '';
			if($mode == 'summary'){
				$tmp = config_load('partner','naver_update');
				if($tmp['naver_update']) $last_update_date = $tmp['naver_update'];
			}
			$query		= $this->goodsmodel->get_goods_all_partner_count($last_update_date,'view',true);
			$result		= mysqli_query($this->db->conn_id,$query);
			$data		= mysqli_fetch_array($result);
			$rows[$val]	= $data['cnt'];
		}


		return $rows;
	}

	public function marketplace_url()
	{
		/* 관리자 권한 체크 : 시작 */
		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('marketplace_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('categorymodel');
		$this->load->model('goodsmodel');

		if( !$this->managerInfo['manager_id']){
			$vendor = $this->session->userdata('marketing');
			if($vendor) $visible[$vendor] = true;
		}else{
			$visible['daum'] = true;
			$visible['ebay'] = true;
			$visible['nbp'] = true;
		}

		$navercheckout = config_load('navercheckout');

		if(!$navercheckout['use']){
			$navercheckout['use'] = 'n';
		}

		if(!$navercheckout['naverpay_mall_id']){
			$navercheckout['naverpay_mall_id'] = $navercheckout['shop_id'];
		}
		if(!$navercheckout['naverpay_user_phone']){
			$navercheckout['naverpay_user_phone'] = $this->config_basic['companyPhone'];
		}
		if(!$navercheckout['naverpay_email']){
			$navercheckout['naverpay_email'] = $this->config_basic['companyEmail'];
		}
		if($navercheckout['naverpay_email']){
			$npay_email							= explode("@",$navercheckout['naverpay_email']);
			$navercheckout['naverpay_email_id']		= $npay_email[0];
			$navercheckout['naverpay_email_host']	= $npay_email[1];
		}

		foreach((array)$navercheckout['except_category_code'] as $k=>$row){
			$navercheckout['except_category_code'][$k]['category_name']  = $this->categorymodel->get_category_name($row['category_code']);
		}
		
		$goods_list = array('except_goods','culture_goods');
		foreach($goods_list as $key) {
			$navercheckout[$key] = $this->goodsmodel->get_select_goods_list($navercheckout[$key]);
		}

		$navercheckout['culture_count'] = count($navercheckout['culture_goods']);

		$naver_wcs		= config_load('naver_wcs');
		$naver_mileage	= config_load('naver_mileage');
		$arrmarket		= config_load('marketing');

		// 마케팅 네이버 및 다음 파일 생성 사용 여부 호출 :: 2015-12-03 lwh
		$daum_use			= $this->config_basic['daum_use'];
		$naver_use			= $this->config_basic['naver_use']; # EP 2.0
		$naver_third_use	= $this->config_basic['naver_third_use']; # EP 3.0
		$facebook_pixel_use	= $this->config_system['facebook_pixel_use'];
		$facebook_pixel		= $this->config_system['facebook_pixel'];
		$google_feed_use	= $this->config_system['google_feed_use'];
		$partner_info		= config_load('partner');

		// 전달 이미지 설정 호출 lwh 2014-02-28
		$marketing_image = config_load('marketing_image');

		// 입점마케팅 전달 데이터 통합 설정 - 입점마케팅 상품명,카드무이자할부 leewh 2015-01-29
		$marketing_feed = config_load('marketing_feed');

		// 입점마케팅 상품 추가할인
		$marketing_sale = config_load('marketing_sale');

		# ----------------------------------------------------------------------------------------------------------
		# 간편결제(네이버페이/톡체크아웃) 배송가능 그룹 조회
		$this->load->library("partnerlib");
		$partner_shipping_group = $this->partnerlib->possible_partner_shipping_group();
		$this->template->assign($partner_shipping_group);
		# ----------------------------------------------------------------------------------------------------------

		if($arrmarket['marketdaum'] == 'y') {
			$target_count	= $this->file_rows();
		}

		//npay 2.1 버튼설정
		$sel_npay_arr = array("pc_goods","mobile_goods");
		foreach($sel_npay_arr as $npay_style){
			if($navercheckout['npay_btn_'.$npay_style]){
				$code		= explode("-",$navercheckout['npay_btn_'.$npay_style]);
				$style_text = $code[0]."-".$code[1] . " 타입";
				$size		= explode("×",$code[3]);
				$h			= $size[1];
			}else{
				$style_text = "";
				$h			= "88";
			}
			$sel_npay_btn_text[$npay_style."_h"]	= $h;
			$sel_npay_btn_text[$npay_style]  = $style_text;
		}
		
		$shop_hash = hash("sha256", $this->config_system['subDomain'].$this->config_system['shopSno']);

		/**
		 * 톡체크아웃 설정 불러오기
		 */
		$this->load->library("talkbuylibrary");
		$talkbuy_config = $this->talkbuylibrary->load_talkbuy_config();
		$this->template->assign($talkbuy_config);
		$this->template->define(array('talkbuy_config'=>$this->skin.'/setting/_talkbuy_config.html'));
		
		$this->template->assign(array(
			"visible"				=>$visible,
			"navercheckout"			=>$navercheckout,
			"sel_npay_btn_text"		=>$sel_npay_btn_text,
			"naver_wcs"				=>$naver_wcs,
			"naver_mileage"			=>$naver_mileage,
			"arrmarket"				=>$arrmarket,
			"marketing_image"		=>$marketing_image,
			"marketing_feed"		=>$marketing_feed,
			"marketing_sale"		=>$marketing_sale,
			"target_count"			=>$target_count,
			"daum_use"				=>$daum_use,
			"naver_use"				=>$naver_use, # EP 2.0
			"naver_third_use"		=>$naver_third_use, # EP 3.0
			"partner_info"			=>$partner_info,
			"pop_contants"			=>$contants,
		    "facebook_pixel_use"	=>$facebook_pixel_use,
			"google_feed_use"		=>$google_feed_use,
		    "facebook_pixel"		=>$facebook_pixel,
		    "shop_hash"		        =>$shop_hash
		));

		$this->template->define(array('naverpay_desc'=>$this->skin."/marketing/naverpay_desc.html"));
		$this->template->define(array('npay_style_inc'=>$this->skin."/marketing/npay_btn_style.html"));

		if($this->session->userdata('marketing')){
			$this->load->model('marketingAdminModel');
			$vendor = $this->marketingAdminModel->vendors[$this->session->userdata('marketing')];
			$this->template->assign(array('vendor'=>$vendor));
		}
		
		//상단 배너 노출
		$this->load->helper('admin');
		$marketing_naverpay_banner = getGabiaPannel('marketing_naverpay_banner');
		$marketing_navershopping_banner = getGabiaPannel('marketing_navershopping_banner');
		$this->template->assign(array(
			'marketing_naverpay_banner' => $marketing_naverpay_banner,	
			'marketing_navershopping_banner' => $marketing_navershopping_banner		
		));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function keyword()
	{
		$params = array();

		$gabiaPageUrl = get_connet_protocol()."firstmall.kr/ec_hosting/marketing/keyword.php";

		$params['firstmall'] = urlencode(iconv('utf-8','euc-kr','yes'));
		$params['shopSno'] = urlencode(iconv('utf-8','euc-kr',$this->config_system['shopSno']));
		$params['domain'] = urlencode(iconv('utf-8','euc-kr',$_SERVER['HTTP_HOST']));
		$params['type'] = urlencode(iconv('utf-8','euc-kr',$this->config_system['service']['code']));

		/* 마케팅서비스 신청을 위한 파라미터 */
		$params['shopDomain'] = urlencode(iconv('utf-8','euc-kr',$this->config_system['domain']));
		$params['shopName'] = urlencode(iconv('utf-8','euc-kr',str_replace(' ','',$this->config_basic['shopName'])));
		$params['tel'] = urlencode(iconv('utf-8','euc-kr',$this->config_basic['companyPhone']));
		$params['email'] = urlencode(iconv('utf-8','euc-kr',$this->config_basic['companyEmail']));
		$params['name'] = urlencode(iconv('utf-8','euc-kr',$this->config_basic['ceo']));

		$paramsStrings = array();
		foreach($params as $k=>$v) $paramsStrings[] = $k."=".$v;

		$gabiaPageUrl .= "?" . implode('&',$paramsStrings);

		$this->template->assign(array('gabiaPageUrl'=>$gabiaPageUrl));

		$this->admin_menu();
		$this->tempate_modules();
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	### 다음쇼핑하우 입점신청
	public function marketplace_daumshopping_apply(){
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->helper('readurl');

		$url = get_connet_protocol()."interface.firstmall.kr/firstmall_plus/request.php?cmd=daumshopping_apply&shopSno={$this->config_system['shopSno']}";
		$formHtml = readurl($url);
		$this->template->assign(array('formHtml'=>$formHtml));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function npay_btn_style_iframe(){

		$this->admin_menu();
		$this->tempate_modules();

		$navercheckout = config_load('navercheckout');


		$mode = $_GET['mode'];
		$type = $_GET['type'];

		$code = explode("-",$navercheckout['npay_btn_'.$mode]);
		$sel_npay_btn['type']	= $code[0];
		$sel_npay_btn['color']	= $code[1];

		// 장바구니는 찜버튼을 숨긴다.
		if ( $type === 'cart') {
			$code[2] = '1';
		}
		$sel_npay_btn['count']	= $code[2];

		$this->template->assign(array('mode'=>$mode,'sel_npay_btn'=>$sel_npay_btn,'navercheckout'=>$navercheckout));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");

	}

	# 네이버페이 버튼 스타일 선택 레이어 @2016-01-19 pjm
	public function npay_btn_style(){

		$this->admin_menu();
		$this->tempate_modules();
		$this->template->define(array("HTML_HEADER"=>$this->skin."/_modules/common/html_header.html"));

		$npay_btn = array();
		if($_GET['mode'] == "pc_goods"){
			$npay_btn[] = array('style'=>'A','color'=>1,'count'=>2,'size'=>'285×88');
			$npay_btn[] = array('style'=>'A','color'=>1,'count'=>1,'size'=>'236×88');
			$npay_btn[] = array('style'=>'B','color'=>1,'count'=>2,'size'=>'261×83');
			$npay_btn[] = array('style'=>'B','color'=>1,'count'=>1,'size'=>'214×83');
			$npay_btn[] = array('style'=>'C','color'=>1,'count'=>2,'size'=>'272×88');
			$npay_btn[] = array('style'=>'C','color'=>1,'count'=>1,'size'=>'225×88');
			$npay_btn[] = array('style'=>'C','color'=>2,'count'=>2,'size'=>'227×88');
			$npay_btn[] = array('style'=>'C','color'=>2,'count'=>1,'size'=>'225×88');
			$npay_btn[] = array('style'=>'C','color'=>3,'count'=>2,'size'=>'272×92');
			$npay_btn[] = array('style'=>'C','color'=>3,'count'=>1,'size'=>'225×92');
			$npay_btn[] = array('style'=>'D','color'=>1,'count'=>2,'size'=>'251×83');
			$npay_btn[] = array('style'=>'D','color'=>1,'count'=>1,'size'=>'210×83');
			$npay_btn[] = array('style'=>'D','color'=>2,'count'=>2,'size'=>'251×83');
			$npay_btn[] = array('style'=>'D','color'=>2,'count'=>1,'size'=>'210×83');
			$npay_btn[] = array('style'=>'D','color'=>3,'count'=>2,'size'=>'251×87');
			$npay_btn[] = array('style'=>'D','color'=>3,'count'=>1,'size'=>'210×87');
			$npay_btn[] = array('style'=>'E','color'=>1,'count'=>2,'size'=>'124×135');
			$npay_btn[] = array('style'=>'E','color'=>1,'count'=>1,'size'=>'124×135');
			$npay_btn[] = array('style'=>'E','color'=>2,'count'=>2,'size'=>'124×135');
			$npay_btn[] = array('style'=>'E','color'=>2,'count'=>1,'size'=>'124×135');
			$npay_btn[] = array('style'=>'E','color'=>3,'count'=>2,'size'=>'124×139');
			$npay_btn[] = array('style'=>'E','color'=>3,'count'=>1,'size'=>'124×139');
		}elseif($_GET['mode'] == "mobile_goods"){
			$npay_btn[] = array('style'=>'MA','color'=>1,'count'=>2,'size'=>'290×85');
			$npay_btn[] = array('style'=>'MA','color'=>1,'count'=>1,'size'=>'290×85');
			$npay_btn[] = array('style'=>'MB','color'=>1,'count'=>2,'size'=>'320×100');
			$npay_btn[] = array('style'=>'MB','color'=>1,'count'=>1,'size'=>'320×100');
		}

		$navercheckout = config_load('navercheckout');

		if($navercheckout['npay_btn_'.$_GET['mode']]){
			$npay_style = explode("-",$navercheckout['npay_btn_'.$_GET['mode']]);
		}
		
		// 장바구니에서는 찜버튼 노출하지 않는다.
		$npay_style[2] = 2;

		// "찜버튼"과 "찜 없는버튼" 같이 표기 하기위해서 재정렬
		$btnList = [];
		foreach($npay_btn as $btn) {
			$key = $btn['style'] . $btn['color'];
			$btnList[$key][] = $btn;
		}
		$npay_btn = array_values($btnList);

		$this->template->assign(array('mode'=>$_GET['mode']));
		$this->template->assign(array('sel_npay_style'=>$npay_style));
		$this->template->assign(array('npay_btn'=>$npay_btn));
		$this->template->assign(array('navercheckout'=>$navercheckout));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");


	}

	/**
	 * 톡체크아웃 버튼 스타일 정의
	 */
	public function talkbuy_btn_style() {
		$this->admin_menu();
		$this->tempate_modules();
		$this->template->define(array("HTML_HEADER"=>$this->skin."/_modules/common/html_header.html"));

		$mode = $this->input->get("mode") ? $this->input->get("mode") : "pc_goods";

		$talkbuy_btn = array();
		/**
		 * darkMode - 0: 밝음 ,1 : 어두움
		 * wishButton - 0 : 찜 없음, 1: 찜 있음
		 */
		if($mode == "pc_goods"){
			$talkbuy_btn[] = array("type"=>"A","size"=>"210x83","darkMode"=>0,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"A","size"=>"210x83","darkMode"=>0,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"A","size"=>"210x83","darkMode"=>1,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"A","size"=>"210x83","darkMode"=>1,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"B","size"=>"236x88","darkMode"=>0,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"B","size"=>"236x88","darkMode"=>0,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"B","size"=>"236x88","darkMode"=>1,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"B","size"=>"236x88","darkMode"=>1,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"C","size"=>"285x88","darkMode"=>0,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"C","size"=>"285x88","darkMode"=>0,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"C","size"=>"285x88","darkMode"=>1,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"C","size"=>"285x88","darkMode"=>1,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"D","size"=>"124x115","darkMode"=>0,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"D","size"=>"124x115","darkMode"=>0,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"D","size"=>"124x115","darkMode"=>1,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"D","size"=>"124x115","darkMode"=>1,"wishButton"=>0);
		}elseif($mode == "mobile_goods"){
			$talkbuy_btn[] = array("type"=>"A","size"=>"290x95","darkMode"=>0,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"A","size"=>"290x95","darkMode"=>0,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"A","size"=>"290x95","darkMode"=>1,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"A","size"=>"290x95","darkMode"=>1,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"B","size"=>"310x100","darkMode"=>0,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"B","size"=>"310x100","darkMode"=>0,"wishButton"=>0);
			$talkbuy_btn[] = array("type"=>"B","size"=>"310x100","darkMode"=>1,"wishButton"=>1);
			$talkbuy_btn[] = array("type"=>"B","size"=>"310x100","darkMode"=>1,"wishButton"=>0);
		}

		$talkbuy = config_load('talkbuy');

		if($talkbuy['talkbuy_btn_'.$mode]){
			$talkbuy_style = explode("-",$talkbuy['talkbuy_btn_'.$mode]);
		}
		foreach($talkbuy_btn as &$btn) {
			if($mode == "mobile_goods") {
				$btn["btn_style_info"] = "M";
			}
			$btn["btn_style_info"] .= $btn["type"]."-".($btn["darkMode"]+1);
		}
		unset($btn);

		// "찜버튼"과 "찜 없는버튼" 같이 표기 하기위해서 재정렬
		$btnList = [];
		foreach($talkbuy_btn as $btn) {
			$key = $btn['type'] . $btn['darkMode'] . $btn['size'];
			$btnList[$key][] = $btn;
		}
		$talkbuy_btn = array_values($btnList);
		$this->template->assign(array('mode'=>$mode));
		$this->template->assign(array('sel_talkbuy_style'=>$talkbuy_style));
		$this->template->assign(array('talkbuy_btn'=>$talkbuy_btn));
		$this->template->assign(array('talkbuy'=>$talkbuy));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/**
	 * 카카오톡 페이 구매 버튼 iframe 으로 노출용
	 */
	public function talkbuy_btn_style_iframe(){

		$this->admin_menu();
		$this->tempate_modules();

		$talkbuy = config_load('talkbuy');


		$mode = $this->input->get("mode") ? $this->input->get("mode") : "pc_goods";
		$type = $this->input->get("type");

		$code = explode("-",$talkbuy['talkbuy_btn_'.$mode]);
		// 장바구니는 찜버튼을 숨긴다.
		if ($type === 'cart') {
			$code[2] = '0';
		}
		$sel_talkbuy_btn['size']	= $code[0];
		$sel_talkbuy_btn['darkMode']	= $code[1];
		$sel_talkbuy_btn['wishButton']	= $code[2];

		$this->template->assign(array('mode'=>$mode,'sel_talkbuy_btn'=>$sel_talkbuy_btn,'talkbuy'=>$talkbuy));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");

	}

	public function banner()
	{
		$params = array();

		$gabiaPageUrl = get_connet_protocol()."firstmall.kr/ec_hosting/marketing/banner.php";

		$params['firstmall'] = urlencode(iconv('utf-8','euc-kr','yes'));
		$params['shopSno'] = urlencode(iconv('utf-8','euc-kr',$this->config_system['shopSno']));
		$params['domain'] = urlencode(iconv('utf-8','euc-kr',$_SERVER['HTTP_HOST']));
		$params['type'] = urlencode(iconv('utf-8','euc-kr',$this->config_system['service']['code']));

		/* 마케팅서비스 신청을 위한 파라미터 */
		$params['shopDomain'] = urlencode(iconv('utf-8','euc-kr',$this->config_system['domain']));
		$params['shopName'] = urlencode(iconv('utf-8','euc-kr',str_replace(' ','',$this->config_basic['shopName'])));
		$params['tel'] = urlencode(iconv('utf-8','euc-kr',$this->config_basic['companyPhone']));
		$params['email'] = urlencode(iconv('utf-8','euc-kr',$this->config_basic['companyEmail']));
		$params['name'] = urlencode(iconv('utf-8','euc-kr',$this->config_basic['ceo']));

		if($_GET["setpage"] != ""){
			$params['p'] = urlencode(iconv('utf-8','euc-kr',$_GET["setpage"]));
		}

		$paramsStrings = array();
		foreach($params as $k=>$v) $paramsStrings[] = $k."=".$v;

		$gabiaPageUrl .= "?" . implode('&',$paramsStrings);

		$this->template->assign(array('gabiaPageUrl'=>$gabiaPageUrl));

		$this->admin_menu();
		$this->tempate_modules();
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}
}

/* End of file marketing.php */
/* Location: ./app/controllers/admin/marketing.php */