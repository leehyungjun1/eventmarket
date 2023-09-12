<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class popup extends front_base
{
	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
	}

	public function main_index()
	{
		redirect("/popup/index");
	}

	public function index()
	{
		redirect("/popup/catalog");
	}

	public function _zipcode_oldzibun()
	{
		// 우편번호 설정
		$cfg_zipcode		= config_load('zipcode');

		//사용가능한 우편번호 검색
		$use_zipcode_set	= array();
		if($cfg_zipcode['street_zipcode_5'])		$use_zipcode_set[]	= 'street';
		if($cfg_zipcode['street_zipcode_6'])		$use_zipcode_set[]	= 'zibun';
		if($cfg_zipcode['old_zipcode_lot_number'])	$use_zipcode_set[]	= 'oldzibun';

		if(!$_GET['zipcode_type'])									$_GET['zipcode_type']	= $use_zipcode_set[0];
		else if(!in_array($_GET['zipcode_type'],$use_zipcode_set))	$_GET['zipcode_type']	= $use_zipcode_set[0];

		$select_zipcode_type = $_GET['zipcode_type'];


		//구스킨용 설정
		$cfg_zipcode['zipcode_street']			= $cfg_zipcode['street_zipcode_5'];
		$cfg_zipcode['new_zipcode_lot_number']	= $cfg_zipcode['street_zipcode_6'];
		$cfg_zipcode['old_zipcode_lot_number']	= $cfg_zipcode['old_zipcode_lot_number'];

		if($this->mobileMode == "mobile")
			$perpage = "5";
		else
			$perpage = "10";

	    $this->load->model('zipcodemodel');
		$data = $this->zipcodemodel->zipcode_oldzibun($perpage);

		$zipcode_type = $_GET['zipcode_type'] ? $_GET['zipcode_type'] : "street";

		foreach($_GET as $key => $value){
			if(in_array($key,array('zipcode_type','old_zipcode','keyword'))) continue;
			if($query_string) $query_string .= "&";
			$query_string .= $key."=".$value;
		}

		$this->template->assign("cfg_zipcode",$cfg_zipcode);
		if($this->mobileMode == "mobile"){
			$this->template->assign("zipcodeFlag",$_GET['zipcodeFlag']);
		}
		$this->template->assign("query_string",$query_string);
		$this->template->assign("zipcode_type",$data['zipcode_type']);
		$this->template->assign("keyword",$data['keyword'] );
		$this->template->assign("loop", $data['loop']);
		$this->template->assign("page",$data['page']);
		$this->print_layout($this->template_path());
	}

	public function zipcode()
	{
		$aGetParams = $this->input->get();
		$aGetParams['popup'] = true;

		if($aGetParams['idx'] === 'undefined') $aGetParams['idx'] = '';
		if($aGetParams['page'] === 'undefined') $aGetParams['page'] = 1; 
		if($aGetParams['ziptype'] === 'undefined') $aGetParams['ziptype'] = '';
	
		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('page', '페이지', 'trim|numeric|xss_clean');
			$this->validation->set_rules('zipcode_type', '우편번호 종류', 'trim|string|xss_clean');
			$this->validation->set_rules('old_zipcode', '구우편번호', 'trim|string|xss_clean');
			$this->validation->set_rules('mtype', '모드', 'trim|string|xss_clean');
			$this->validation->set_rules('popup', '팝업', 'trim|numeric|xss_clean');
			$this->validation->set_rules('iframe', '아이프레임', 'trim|numeric|xss_clean');
			$this->validation->set_rules('keyword', '검색어', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value'],422);
			}
		}

		/**
		 * "반응형 스킨"과 "전용 스킨"의 넘어오는 파라미터 명이 다르기 때문에 통합 했습니다.
		 * $this->zipcodemodel->zipcode() 에서 글로벌 변수 $_GET 사용되어서 $_GET['keyword'] 통합 했습니다.
		 */
		$_GET['keyword'] = (isset($aGetParams['zipcode_keyword'])) ? $aGetParams['zipcode_keyword'] : $aGetParams['keyword'];

		/**
		 * 2글자 이하면 경고창 출력 시킨다
		 * 1글자면 검색시 slow query 발생
		 */
		$wordLength = mb_strlen(trim($_GET['keyword']));
		if ($wordLength === 1) {
			// iframe 호출 되이서 javascript callback 동작되지 않기 때문에 키워드 초기화 시킴
			$_GET['keyword'] = '';

			/**
			 * 팝업창 에서 처리
			 *
			 * 다국어 스킨 이면 주소찾기 스킨도 변경됩니다
			 * - 다국어스킨 + 팝업창 = 에러 (opener, parent 사용못함)
			 * - 위치 : 관리자 > 주문 > 전체 주문 조회 > 관리자가 주문넣기 > 주소검색 팝업창 open
			 *
			 * 경고창 출력 후 exit 하면 안됩니다.
			 * - view 출력이 되지 않습니다
			 */

			// adminzipcode 관리자에서 호출한 페이지인지 확인하는 값
			if (isset($aGetParams['adminzipcode']) && $aGetParams['adminzipcode'] === 'y') {
				alert('검색어는 2자이상 입력하여 주세요');

			// 검색어는 2자이상 입력하여 주세요
			} else {
				openDialogAlert(getAlert('et428'), 400, 160, 'parent');
			}
		}

		// 우편번호 설정
		$cfg_zipcode		= config_load('zipcode');

		//사용가능한 우편번호 검색
		$use_zipcode_set	= array();
		if($cfg_zipcode['street_zipcode_5'])		$use_zipcode_set[]	= 'street';
		if($cfg_zipcode['street_zipcode_6'])		$use_zipcode_set[]	= 'zibun';
		if($cfg_zipcode['old_zipcode_lot_number'])	$use_zipcode_set[]	= 'oldzibun';

		if (!$aGetParams['zipcode_type']) {
			$_GET['zipcode_type'] = $use_zipcode_set[0];
		} else if(!in_array($aGetParams['zipcode_type'],$use_zipcode_set)) {
			$_GET['zipcode_type'] = $use_zipcode_set[0];
		}

		$select_zipcode_type = $_GET['zipcode_type'];

		$cfg_zipcode['zipcode_street']			= $cfg_zipcode['street_zipcode_5'];
		$cfg_zipcode['new_zipcode_lot_number']	= $cfg_zipcode['street_zipcode_6'];
		$cfg_zipcode['old_zipcode_lot_number']	= $cfg_zipcode['old_zipcode_lot_number'];

		// 구지번 검색
		if($select_zipcode_type == 'oldzibun'){
			$this->_zipcode_oldzibun();
			exit;
		}

		if($this->mobileMode)
			$perpage = "5";
		else
			$perpage = "10";

	    $this->load->model('zipcodemodel');
		$data = $this->zipcodemodel->zipcode($perpage);

		// zipcode 공통 자바스크립트 호출
		requirejs([
			["/app/javascript/js/skin-zipcode.js",20],
		]);

		$this->template->assign(['sc' => $aGetParams]);
		$this->template->assign("cfg_zipcode",$cfg_zipcode);
		$this->template->assign("arrSigungu",$data['arrSigungu']);
		$this->template->assign("zipcodeFlag",$aGetParams['zipcodeFlag']);
		$this->template->assign("zipcode_type",$select_zipcode_type);
		$this->template->assign("query_string",$data['query_string'] );
		$this->template->assign("arrSido",$data['arrSido'] );
		$this->template->assign("keyword",$data['keyword'] );
		$this->template->assign("loop", $data['loop']);
		$this->template->assign("page",$data['page']);
		$this->print_layout($this->template_path());

	}

	public function sido()
	{
		//미사용 함수로 소스 제거 @2017-01-17
	}

	public function zipcode_street_sigungu()
	{
		$arrSigungu = array();
	    $this->load->helper('zipcode');
	    $ZIP_DB = get_zipcode_db();

		 $this->load->model('zipcodemodel');

		$zipcode_type = $_GET['zipcode_type'] ? $_GET['zipcode_type'] : "street";

		if($zipcode_type == "street"){
			$this->zipcodeTable = "zipcode_street_new";
		}else{
			$this->zipcodeTable = "zipcode_street";
		}

		if(isset($_GET['zipcode_keyword'])){
			$keyword = $_GET['zipcode_keyword'];
		}else{
			$keyword = isset($_GET['keyword']) ? $_GET['keyword'] : false;
		}

		list($wheres,$sidoWheres) = $this->zipcodemodel->get_where_query($keyword,$zipcode_type);

		$ZIP_DB->select('SIGUNGU');
		$ZIP_DB->from($this->zipcodeTable);
		$ZIP_DB->where('SIDO', $_GET['SIDO']);
		if($sidoWheres){
			$ZIP_DB->where(implode(" AND ", $sidoWheres));
		}
		$ZIP_DB->group_by('SIGUNGU');
		$arrSigungu = $ZIP_DB->get();

		echo json_encode($arrSigungu->result());
	}


	public function addressbook()
	{
		$this->print_layout($this->template_path());
	}

	public function addressbook_write()
	{
		$this->print_layout($this->template_path());
	}

	public function designpopup(){
		$popup_seq = $_GET['seq'];
		$popup_key = $_GET['popup_key'];
		$template_path = $this->template_path();

		$query  = $this->db->query("select * from fm_design_popup where popup_seq = ?",$popup_seq);
		$data = $query->row_array();

		$popupHtml = "";
		$popupHtml .= "<div class='designPopup' popupStyle='window' designElement='popup' template_path='{$template_path}' popupSeq='{$popup_seq}' style='left:0px;top:0px;'>";
		$popupHtml .= "<div class='designPopupBody'>";

		if($data['contents_type']=='image'){
			if($data['link'])  $popupHtml .= "<a href='{$data['link']}' target='_opener' onclick='self.close()'>";
			$popupHtml .= "<img src='/data/popup/{$data['image']}' />";
			if($data['link'])  $popupHtml .= "</a>";
		}else if($data['contents_type']=='text'){
			$popupHtml .= $data['contents'];
		}else{
			$banner_seq = $data['popup_banner_seq'];

			$query  = $this->db->query("select * from fm_design_popup_banner where banner_seq = ?",array($banner_seq));

			$banner = $query->row_array();

			$query  = $this->db->query("select * from fm_design_popup_banner_item where banner_seq = ?",array($banner_seq));
			$banner_item = $query->result_array();

			if(!$banner) return;

			$html = "";

			if(BANNER_SCRIPT_LOADED!==true){
				// 한페이지에 여러 배너 노출할 때 스크립트는 1회만 로드
				define("BANNER_SCRIPT_LOADED",true);
				$html .= "<script type='text/javascript' src='/app/javascript/jquery/jquery.ui.touch-punch.min.js'></script>";
				$html .= "<script type='text/javascript' src='/app/javascript/plugin/anibanner/jquery.anibanner.js?v=20140808'></script>";
				$html .= "<link rel='stylesheet' type='text/css' href='/app/javascript/plugin/anibanner/anibanner.css' />";
			}

			if($banner['navigation_paging_style']=='custom'){
				/* 이미지가로세로 크기 */
				@list($customImageWidth, $customImageHeight) = @getimagesize(ROOTPATH."data/popup/{$banner_item[0]['tab_image_inactive']}");
				$banner['navigation_paging_height'] = $customImageHeight;
			}

			$html .= "<div class='designBanner' templatePath='{$template_path}' bannerSeq='{$banner_seq}' style='height:{$banner['height']}px;'></div>";

			$html .= "<script>";
			$html .= "$(function(){";
			$html .= "var settings = {";
			$html .= "'platform' : '{$banner['platform']}',";
			$html .= "'modtime' : '{$banner['modtime']}',";
			$html .= "'style' : '{$banner['style']}',";
			$html .= "'height' : '{$banner['height']}',";
			$html .= "'background_color' : '{$banner['background_color']}',";
			$html .= "'background_image' : '/data/popup/{$banner['background_image']}',";
			$html .= "'background_repeat' : '{$banner['background_repeat']}',";
			$html .= "'background_position' : '{$banner['background_position']}',";
			$html .= "'image_border_use' : '{$banner['image_border_use']}',";
			$html .= "'image_border_width' : '{$banner['image_border_width']}',";
			$html .= "'image_border_color' : '{$banner['image_border_color']}',";
			$html .= "'image_opacity_use' : '{$banner['image_opacity_use']}',";
			$html .= "'image_opacity_percent' : '{$banner['image_opacity_percent']}',";
			$html .= "'image_top_margin' : '{$banner['image_top_margin']}',";
			$html .= "'image_side_margin' : '{$banner['image_side_margin']}',";
			$html .= "'image_width' : '{$banner['image_width']}',";
			$html .= "'image_height' : '{$banner['image_height']}',";
			$html .= "'navigation_btn_style' : '{$banner['navigation_btn_style']}',";
			$html .= "'navigation_btn_visible' : '{$banner['navigation_btn_visible']}',";
			$html .= "'navigation_paging_style' : '{$banner['navigation_paging_style']}',";
			$html .= "'navigation_paging_height' : '{$banner['navigation_paging_height']}',";
			$html .= "'navigation_paging_align' : '{$banner['navigation_paging_align']}',";
			$html .= "'navigation_paging_position' : '{$banner['navigation_paging_position']}',";
			$html .= "'navigation_paging_margin' : '{$banner['navigation_paging_margin']}',";
			$html .= "'navigation_paging_spacing' : '{$banner['navigation_paging_spacing']}',";
			$html .= "'slide_event' : '{$banner['slide_event']}',";
			$html .= "'images' : [";
			foreach($banner_item as $k=>$item){
				if($k) $html .= ",";
				$html .= "{'link':'{$item['link']}','target':'{$item['target']}','image':'/data/popup/{$item['image']}'}";
			}
			$html .= "],";
			$html .= "'navigation_paging_custom_images' : [";
			foreach($banner_item as $k=>$item){
				if($k) $html .= ",";
				$html .= "{'active':'/data/popup/popup_banner/{$banner_seq}/{$item['tab_image_active']}','inactive':'/data/popup/popup_banner/{$banner_seq}/{$item['tab_image_inactive']}'}";
			}
			$html .= "]";
			$html .= "};";
			$html .= "$('.designBanner[bannerSeq=\"{$banner_seq}\"]').anibanner(settings);";
			$html .= "});";
			$html .= "</script>";
			$popupHtml .= $html;
		}

		$designPopupTodaymsgCss = font_decoration_attr($data['bar_msg_today_decoration'],'css','style');
		$designPopupCloseCss = font_decoration_attr($data['bar_msg_close_decoration'],'css','style');

		$popupHtml .= "</div>";
		$popupHtml .= "<div class='designPopupBar' style='background-color:{$data['bar_background_color']}'>";
		$popupHtml .= "<div class='designPopupTodaymsg' {$designPopupTodaymsgCss}><label><input type='checkbox' /> {$data['bar_msg_today_text']}</label></div>";
		$popupHtml .= "<div class='designPopupClose' {$designPopupCloseCss}>{$data['bar_msg_close_text']}</div>";
		$popupHtml .= "</div>";
		$popupHtml .= "</div>";

		$this->template->assign(array('popupHtml'=>$popupHtml));
		$tpl = $this->template_path();
		$tpl = str_replace("designpopup","_designpopup",$tpl);

		$this->template->assign(array('shopTitle'=>$data['title']));

		$this->tempate_modules();
		$this->template->define(array('tpl'=>$tpl));
		$this->template->print_('tpl');
	}

	public function joincheck(){
		$_GET['popup'] = true;
		$joincheck_seq = $_GET['seq'];

		$query = $this->db->query("select * from fm_joincheck where joincheck_seq=?",$joincheck_seq);
		$data = $query->row_array();

		$tpl = 'popup/_'.$data['skin'].'.html';

		$this->template_path = $tpl;
		$this->template->assign(array("template_path"=>$this->template_path));

		$this->print_layout($this->skin.'/'.$tpl);

	}

	public function issue_list(){
		$coupon_seq	= $_POST['coupon_seq'];
		$type		= $_POST['type'];

		if(!$coupon_seq){
			$coupon_seq	= $_GET['coupon_seq'];
			$type		= $_GET['type'];
		}

		if(isset($coupon_seq)) {

			$no = $coupon_seq;
			$this->load->model('couponmodel');
			$this->load->model('goodsmodel');
			$this->load->model('categorymodel');

			$coupons 			= $this->couponmodel->get_coupon($no);
			$couponGroups 	= $this->couponmodel->get_coupon_group($no);
			$issuegoods 		= $this->couponmodel->get_coupon_issuegoods($no);
			$issuecategorys	= $this->couponmodel->get_coupon_issuecategory($no);

			if($couponGroups){
				foreach($couponGroups as $key => $group){
					foreach($this->groups as $tmp){
						if($tmp['group_seq'] == $group['group_seq']){
							$couponGroups[$key]['group_name'] = $tmp['group_name'];
						}
					}
				}
				$this->template->assign(array('couponGroups'=>$couponGroups));
			}

			if(($issuegoods)){
				foreach($issuegoods as $key => $tmp) $arrGoodsSeq[] =  $tmp['goods_seq'];
				$goods = $this->goodsmodel->get_goods_list($arrGoodsSeq,'thumbView');
				foreach($issuegoods as $key => $data) $issuegoods[$key] = @array_merge($issuegoods[$key],$goods[$data['goods_seq']]);
				$this->template->assign(array('issuegoods'=>$issuegoods));
			}

			if($issuecategorys){
				foreach($issuecategorys as $key =>$data){
					if($issuecategorys[$key]['type']=='issue'){
						$issuecategorys[$key]['category'] = $this->categorymodel -> get_category_name_href($data['category_code']);
					}elseif($issuecategorys[$key]['type']=='except'){
						$issuecategorys[$key]['category'] = $this->categorymodel -> get_category_name($data['category_code']);
					}
				}

				$this->template->assign(array('issuecategorys'=>$issuecategorys));
			}

			$dsc['whereis'] = ' and coupon_seq='.$coupons['coupon_seq'];
			$downloadtotal = $this->couponmodel->get_download_total_count($dsc);
			$coupons['downloadtotal']	= 0;//발급수-> 수정가능하도록 수정@2012-06-08
			$coupons['downloadtotalbtn']	= number_format($downloadtotal);

			$usc['whereis'] = ' and coupon_seq='.$coupons['coupon_seq'].' and use_status = \'used\' ';
			$usetotal = $this->couponmodel->get_download_total_count($usc);
			$coupons['usetotalbtn']	= number_format($usetotal);//사용건수

			$this->template->assign(array('coupons'=>$coupons));
		}
		$this->print_layout($this->template_path());

	}

	public function minishop_reg(){

		$this->load->model('providermodel');
		$m				= $_GET['m'];
		$provider		= $this->providermodel->get_provider($m);
		$this->template->assign(array('pv'=>$provider));
		$this->template->assign(array('seq'=>$this->userInfo['member_seq']));

		$this->print_layout($this->template_path());
	}

}

