<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);
class setting_process extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('admin/setting');
		$this->load->model('managermodel');
	}

	/* 판매환경 설정 */
	public function config()
	{

		$callback = "parent.document.location.reload();";

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_basic_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		$mobilesize = sizeof($_POST['mobile_price1']);
		if($mobilesize) {
			for($i=0;$i<$mobilesize;$i++) {
				$price1					= get_cutting_price($_POST['mobile_price1'][$i]);
				$price2					= get_cutting_price($_POST['mobile_price2'][$i]);
				$sale_price				= (int) $_POST['mobile_sale_price'][$i];
				$sale_emoney			= (int) $_POST['mobile_sale_emoney'][$i];
				$sale_point				= (int) $_POST['mobile_sale_point'][$i];

				if($price1 == 0 && $price2 == 0  ){
					openDialogAlert("모바일/테블릿  추가할인시 상품의 구매금액이 모두 \"0\"일 수는 없습니다.",500,140,'parent',$callback);
					exit;
				}

				if($sale_price == 0 && $sale_emoney == 0 && $sale_point == 0 ){
					openDialogAlert("모바일/테블릿 추가할인 혜택과 추가적립 혜택이 모두 \"0\"일 수는 없습니다.",500,140,'parent',$callback);
					exit;
				}
			}
		}

		$this->load->model('configsalemodel');
		$this->db->delete('fm_config_sale', array('type' => 'mobile'));

		$mobilesize = sizeof($_POST['mobile_price1']);
		if($mobilesize) {
			for($i=0;$i<$mobilesize;$i++) {
				$price1					= get_cutting_price($_POST['mobile_price1'][$i]);
				$price2					= get_cutting_price($_POST['mobile_price2'][$i]);
				$sale_price				= (int) $_POST['mobile_sale_price'][$i];
				$sale_emoney			= (int) $_POST['mobile_sale_emoney'][$i];
				$sale_point				= (int) $_POST['mobile_sale_point'][$i];
				$params['type']			= 'mobile';
				$params['price1']		= $price1;
				$params['price2']		= $price2;
				$params['sale_price']	= $sale_price;
				$params['sale_emoney']	= $sale_emoney;

				###
				if($_POST['mobile_reserve_select'][$i]=='year'){
					$reserve_limit = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$_POST['mobile_reserve_year'][$i]));//$_POST['mobile_reserve_year'][$i]."-12-31";
				}else if($_POST['mobile_reserve_select'][$i]=='direct'){
					$reserve_limit = date("Y-m-d", mktime(0,0,0,date("m")+$_POST['mobile_reserve_direct'][$i], date("d"), date("Y")));
				}else{
					$reserve_limit = "";
				}
				if($_POST['mobile_point_select'][$i]=='year'){
					$point_limit = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$_POST['mobile_point_year'][$i]));//$_POST['mobile_point_year'][$i]."-12-31";
				}else if($_POST['mobile_point_select'][$i]=='direct'){
					$point_limit = date("Y-m-d", mktime(0,0,0,date("m")+$_POST['mobile_point_direct'][$i], date("d"), date("Y")));
				}else{
					$point_limit = "";
				}
				$params['reserve_limit']		= $reserve_limit;
				$params['reserve_select']		= $_POST['mobile_reserve_select'][$i];
				$params['reserve_year']			= $_POST['mobile_reserve_year'][$i];
				$params['reserve_direct']		= $_POST['mobile_reserve_direct'][$i];


				if( $this->isplusfreenot) {//무료몰이아닌경우에만 적용 @2013-01-14

					if($_POST['mobile_point_select'][$i]=='year'){
						$point_limit = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$_POST['mobile_point_year'][$i]));//$_POST['mobile_point_year'][$i]."-12-31";
					}else if($_POST['mobile_point_select'][$i]=='direct'){
						$point_limit = date("Y-m-d", mktime(0,0,0,date("m")+$_POST['mobile_point_direct'][$i], date("d"), date("Y")));
					}else{
						$point_limit = "";
					}
					$params['sale_point']			= $sale_point;
					$params['point_limit']			= $point_limit;
					$params['point_select']		= ($_POST['mobile_point_select'][$i])?$_POST['mobile_point_select'][$i]:'';
					$params['point_year']			= ($_POST['mobile_point_year'][$i])?$_POST['mobile_point_year'][$i]:'';
					$params['point_direct']		= ($_POST['mobile_point_direct'][$i])?$_POST['mobile_point_direct'][$i]:'';
				}else{
					$params['sale_point']			= 0;
					$params['point_limit']			= '';
					$params['point_select']		= '';
					$params['point_year']			= '';
					$params['point_direct']		= '';
				}

				$params['regist_date']	= date("Y-m-d H:i:s");
				$params['add']	= "";
				$this->configsalemodel->confsale_write($params);//
			}
		}

		if($_POST['page_id_f']) config_save('snssocial',array('page_id_f'=>$_POST['page_id_f']));

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 기본설정 */
	public function basic()
	{
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_basic_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		/* 파비콘 파일 저장 */
		$favicon = $this->setting->upload_favicon();

		/* 모바일 바탕화면 바로가기 저장 :: 2016-01-05 lwh */
		if($_FILES['iphoneicon']['tmp_name'])
			$iphoneicon = $this->setting->upload_book_icon('iphoneicon');

		if($_FILES['androidicon']['tmp_name'])
			$androidicon = $this->setting->upload_book_icon('androidicon');

		if($_FILES['signatureicon']['tmp_name'])
			$signatureicon = $this->setting->upload_book_icon('signatureicon');

		$icon['favicon'] = $favicon;
		$icon['iphoneicon'] = $iphoneicon;
		$icon['androidicon'] = $androidicon;
		$icon['signatureicon'] = $signatureicon;

		/* 인증 */
		$_POST['companyEmail'] = $_POST['companyEmail'][0]."@". $_POST['companyEmail'][1];
		$_POST['partnershipEmail'] = $_POST['partnershipEmail'][0]."@". $_POST['partnershipEmail'][1];
		$_POST['member_info_email'] = $_POST['member_info_email'][0]."@". $_POST['member_info_email'][1];
		$this->validation->set_rules('domain', '쇼핑몰 도메인','trim|prep_urlmax_length[50]|xss_clean');
		$this->validation->set_rules('shopName', '쇼핑몰 이름','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('shopBranch[]', '쇼핑몰 분류','trim|numeric|max_length[50]|xss_clean');
		$this->validation->set_rules('shopTitleTag', '쇼핑몰 타이틀','trim|max_length[100]|xss_clean');
		$this->validation->set_rules('shopGoodsTitleTag', '쇼핑몰 상품상세페이지 타이틀','trim|max_length[100]|xss_clean');
		$this->validation->set_rules('shopCategoryTitleTag', '쇼핑몰 카테고리페이지 타이틀','trim|max_length[100]|xss_clean');
		$this->validation->set_rules('companyName', '상호','trim|max_length[50]|xss_clean');
		$this->validation->set_rules('businessConditions', '업태','trim|max_length[20]|xss_clean');
		$this->validation->set_rules('businessLine', '종목','trim|max_length[20]|xss_clean');
		$this->validation->set_rules('businessLicense[]', '사업자 번호','trim|numeric|max_length[6]|xss_clean');
		$this->validation->set_rules('mailsellingLicense', '통신판매업 신고번호','trim|xss_clean');
		$this->validation->set_rules('ceo', '대표자','trim|max_length[20]|xss_clean');
		$this->validation->set_rules('companyPhone[]', '연락처','trim|numeric|max_length[4]|xss_clean');
		$this->validation->set_rules('companyFax[]', '팩스번호','trim|numeric|max_length[4]|xss_clean');
		$this->validation->set_rules('companyEmail', '이메일','trim|max_length[50]|valid_email|xss_clean');
		$this->validation->set_rules('companyZipcode[]', '우편번호','trim|max_length[7]|numeric|xss_clean');
		$this->validation->set_rules('companyAddress', '주소','trim|max_length[100]|xss_clean');
		$this->validation->set_rules('companyAddressDetail', '상세 주소','trim|max_length[100]|xss_clean');

		if	(serviceLimit('H_AD')){ // 입점몰일 경우
			$this->validation->set_rules('partnershipEmail', '입점문의 수신 이메일','trim|max_length[50]|valid_email|xss_clean');
		}

		//개인정보 관련 문구개선 @2016-09-06 ysm
		$this->validation->set_rules('member_info_manager', '개인정보 보호책임자 성명','trim|max_length[50]|xss_clean');
		$this->validation->set_rules('member_info_tel', '개인정보 보호책임자 연락처','trim|xss_clean');
		$this->validation->set_rules('member_info_email', '개인정보 보호책임자 이메일','trim|max_length[50]|valid_email|xss_clean');

		### 마일리지 관련 설정 저장
		config_save("reserve",array('default_reserve_bookmark'=>$_POST['default_reserve_bookmark']));
		config_save("reserve",array('book_reserve_select'=>$_POST['book_reserve_select']));
		config_save("reserve",array('book_reserve_year'=>$_POST['book_reserve_year']));
		config_save("reserve",array('book_reserve_direct'=>$_POST['book_reserve_direct']));

		if( $this->isplusfreenot ) {//무료몰이아닌경우에만 적용 @2013-01-14
			config_save("reserve",array('default_point_bookmark'=>$_POST['default_point_bookmark']));
			config_save("reserve",array('book_point_select'=>$_POST['book_point_select']));
			config_save("reserve",array('book_point_year'=>$_POST['book_point_year']));
			config_save("reserve",array('book_point_direct'=>$_POST['book_point_direct']));
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if	(serviceLimit('H_AD')){ // 입점몰일 경우
			//본사 미니샵이미지적용
			$this->load->model('providermodel');
			if	($_POST['main_visual']){
				$_POST['main_visual']	= $this->providermodel->upload_minishop_image('base', $_POST['main_visual'], $_POST['org_main_visual']);
			}else{
				if	($_POST['del_main_visual'] == 'y'){
					$this->providermodel->delete_minishop_image($_POST['org_main_visual']);
					$_POST['org_main_visual']	= '';
				}
				$_POST['main_visual']	= $_POST['org_main_visual'];
			}
			$providerinfodata['main_visual']		= $_POST['main_visual'];
			$this->db->update('fm_provider', $providerinfodata, array('provider_seq'=>1));
		}

		/* 설정저장 */
		$this->setting->basic($icon);

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 마케팅설정 */
	public function admin_marketing_conf()
	{
		if(!$_POST['marketdaum']) $_POST['marketdaum'] = 'n';
		if(!$_POST['marketabout']) $_POST['marketabout'] = 'n';
		if(!$_POST['marketnaver']) $_POST['marketnaver'] = 'n';
		config_save("marketing",array('marketdaum'=>$_POST['marketdaum']));
		config_save("marketing",array('marketabout'=>$_POST['marketabout']));
		config_save("marketing",array('marketnaver'=>$_POST['marketnaver']));

		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* fammerce plus 쇼핑몰 로고 */
	public function snsconf_snslogo(){

		$snslogo = $this->setting->upload_snslogo();
		$this->setting->snsconf($snslogo);
		$callback = "parent.snslogoDisplay('{$snslogo}?".time()."');";
		openDialogAlert("등록하였습니다.",400,140,'parent',$callback);
	}

	## 메타태그저장(sns 키워드,소개 포함)
	public function snsconf_snsmetatag(){

		config_save('basic',array('metaTagDescription'=>$_POST['metaTagDescription']));
		config_save('basic',array('metaTagKeyword'=>$_POST['metaTagKeyword']));

		echo  json_encode(array("result"=>true));
		exit;

	}

	/* sns마케팅 */
	public function snsconf()
	{
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_snsconf_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		// 트위터 기본앱 사용 불가 처리 #19795 2018-06-27 hed
		if($_POST["app_gubun_t"]=="basic" && $_POST['use_t']=="1"){
			openDialogAlert("기본 앱 서비스가 종료되었습니다.<br/>전용 앱으로 설정 후 체크하여 주세요.",400,160,'parent',$callback);
			exit;
		}

		$this->load->model('configsalemodel');

		## 좋아요 : 혜택 체크
		$fblikesize = sizeof($_POST['fblike_price1']);
		if($fblikesize) {
			for($i=0;$i<$fblikesize;$i++) {
				$price1					= get_cutting_price($_POST['fblike_price1'][$i]);
				$price2					= get_cutting_price($_POST['fblike_price2'][$i]);
				$sale_price				= (int) $_POST['fblike_sale_price'][$i];
				$sale_emoney			= (int) $_POST['fblike_sale_emoney'][$i];
				$sale_point				= (int) $_POST['fblike_sale_point'][$i];

				if($price1 == 0 && $price2 == 0  ){
					openDialogAlert("좋아요  추가할인시 상품의 구매금액이 모두 \"0\"일 수는 없습니다.",500,140,'parent',$callback);
					exit;
				}

				if($sale_price == 0 && $sale_emoney == 0 && $sale_point  == 0 ){
					openDialogAlert("좋아요 추가할인 혜택과 추가적립 혜택이 모두 \"0\"일 수는 없습니다.",450,140,'parent',$callback);
					exit;
				}
			}
		}

		## 좋아요 : 좋아요 사용일때 기존 데이터 삭제 후 재 등록
		if($_POST['fb_like_box_type']) $fb_like_box_type = $_POST['fb_like_box_type'];
		if($_POST['new_fb_like_box_type']) $fb_like_box_type = $_POST['new_fb_like_box_type'];
		if($fb_like_box_type != "NO"){
			$this->db->delete('fm_config_sale', array('type' => 'fblike'));
		}


		$snscaption			= ($_POST['snscaption'])?$_POST['snscaption']:'';
		config_save('snssocial',array('snscaption'=> $snscaption));

		//짧은 주소 설정
		$shorturl_use		= ($_POST['shorturl_use'])?$_POST['shorturl_use']:'N';
		config_save('snssocial',array('shorturl_use'=> $shorturl_use));

		$snssocialold = ($this->arrSns)?$this->arrSns:config_load('snssocial');

		if( $snssocialold['key_f'] != '455616624457601' ) {//전용앱 정식도매인 > 임시도메인 순으로 저장
			$likeurl			= ($this->config_system['domain'])?$this->config_system['domain']:$this->config_system['subDomain'];
		}else{//기본앱
			$likeurl			= ($_POST['likeurl'])?$_POST['likeurl']:$this->config_system['subDomain'];
		}
		config_save('snssocial',array('likeurl'=> $likeurl));

		if( $snssocialold['facebook_app'] == 'new' ){
			$fb_like_box_type = ($_POST['new_fb_like_box_type'])?$_POST['new_fb_like_box_type']:'API';
		}else{
			$fb_like_box_type = ($_POST['fb_like_box_type'])?$_POST['fb_like_box_type']:'API';
		}
		config_save('snssocial',array('fb_like_box_type'=> $fb_like_box_type));

		## 좋아요 혜택 설정
		$fblikesize = sizeof($_POST['fblike_price1']);
		if($fblikesize) {
			for($i=0;$i<$fblikesize;$i++) {
				$price1					= get_cutting_price($_POST['fblike_price1'][$i]);
				$price2					= get_cutting_price($_POST['fblike_price2'][$i]);
				$sale_price				= (int) $_POST['fblike_sale_price'][$i];
				$sale_emoney			= (int) $_POST['fblike_sale_emoney'][$i];
				$sale_point				= (int) $_POST['fblike_sale_point'][$i];

				$params['type']			= 'fblike';
				$params['price1']		= $price1;
				$params['price2']		= $price2;
				$params['sale_price']	= $sale_price;
				$params['sale_emoney']	= $sale_emoney;

				###

				if($_POST['fblike_reserve_select'][$i]=='year'){
					$reserve_limit = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$_POST['fblike_reserve_year'][$i]));//$_POST['fblike_reserve_year'][$i]."-12-31";
				}else if($_POST['fblike_reserve_select'][$i]=='direct'){
					$reserve_limit = date("Y-m-d", mktime(0,0,0,date("m")+$_POST['fblike_reserve_direct'][$i], date("d"), date("Y")));
				}else{
					$reserve_limit = "";
				}
				if($_POST['fblike_point_select'][$i]=='year'){
					$point_limit = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$_POST['fblike_point_year'][$i]));//$_POST['fblike_point_year'][$i]."-12-31";
				}else if($_POST['fblike_point_select'][$i]=='direct'){
					$point_limit = date("Y-m-d", mktime(0,0,0,date("m")+$_POST['fblike_point_direct'][$i], date("d"), date("Y")));
				}else{
					$point_limit = "";
				}
				$params['reserve_limit']		= $reserve_limit;
				$params['reserve_select']		= $_POST['fblike_reserve_select'][$i];
				$params['reserve_year']			= $_POST['fblike_reserve_year'][$i];
				$params['reserve_direct']		= $_POST['fblike_reserve_direct'][$i];


				$params['sale_point']			= ($sale_point)?$sale_point:0;
				$params['point_limit']			= $point_limit;
				$params['point_select']		= ($_POST['fblike_point_select'][$i])?$_POST['fblike_point_select'][$i]:'';
				$params['point_year']			= ($_POST['fblike_point_year'][$i])?$_POST['fblike_point_year'][$i]:'';
				$params['point_direct']		= ($_POST['fblike_point_direct'][$i])?$_POST['fblike_point_direct'][$i]:'';


				$params['regist_date']	= date("Y-m-d H:i:s");
				$params['add']	= "";
				$this->configsalemodel->confsale_write($params);
			}
		}

		/* 채널톡 연동 2020.05.11 */
		$channeltalk = array();
		$channeltalk['channeltalk_use'] = $_POST['channeltalk_use'];

		$channeltalk['plugin_key'] = ($_POST['plugin_key']) ? $_POST['plugin_key']: '';
		$channeltalk['access_secret'] = ($_POST['access_secret']) ? $_POST['access_secret'] : '';

		if($_POST['channeltalk_use'] == 'Y' || $_POST['channeltalk_use'] == 'T'){
			$this->validation->set_rules('plugin_key', 'plugin key','trim|required|xss_clean');
			$this->validation->set_rules('access_secret', 'Acess Secret','trim|required|xss_clean');
			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(top.window.parent.document.getElementsByName('{$err['key']}')[0]) top.window.parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert('설정값을 입력해 주세요.',400,140,'parent',$callback);
				exit;
			}

		}

		$this->load->library('channeltalklibrary');
		$this->channeltalklibrary->set_channeltalk($channeltalk);

		// 채널톡 파트너사를 통한 고객사 등록 확인
		if ($_POST['channeltalk_use'] != 'N' && $_POST['channeltalk_use'] != ''){
			$this->load->helper('readurl');
			$oriUrl = 'https://api.channel.io';
			$path = '/partner/plugins/'.$channeltalk['plugin_key'].'/'.$channeltalk['access_secret'] .'/acquire';
			$headers = array(
				"x-access-key" => "6058408e3d483ef6a186",
				"x-access-secret" => "b396b81ad1d235e8feb3570a6438d4bc"
			);
			$result = readurl($oriUrl.$path,'',false, 7, $headers, true, false, 'post');
			if ($result == false) {
				$callback = "if(top.window.parent.document.getElementsByName('plugin_key')[0]) top.window.parent.document.getElementsByName('plugin_key')[0].focus();";
				openDialogAlert('plugin key 나 Acess Secret을 확인해주세요!',400,140,'parent',$callback);
				exit;
			}
		}


		// 네이버 톡톡 설정 저장
		// 2016.08.19 pjw
		// 2016.09.08 pjw 수정 : 고객센터 추가
		config_save('snssocial', array(
			'ntalk_use_web_product'		=> $_POST['ntalk_use_web_product'],
			'ntalk_use_web_quick'		=> $_POST['ntalk_use_web_quick'],
			'ntalk_use_web_customer'	=> $_POST['ntalk_use_web_customer'],
			'ntalk_use_mobile_product'	=> $_POST['ntalk_use_mobile_product'],
			'ntalk_use_mobile_main'		=> $_POST['ntalk_use_mobile_main'],
			'ntalk_use_mobile_customer'	=> $_POST['ntalk_use_mobile_customer'],
			'ntalk_use_sniffet'			=> $_POST['ntalk_use_sniffet']
		));

		/* 좋아요 설정저장 */
		config_save('order',array('fblike_ordertype'=>$_POST['fblike_ordertype']));

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);

	}

	## 짧은 URL 설정.
	public function snsconf_shorturl(){
		$this->arrSns	= ($this->arrSns)?$this->arrSns:config_load('snssocial');
		//짧은 주소 설정값 유효성 검증
		$this->validation->set_rules('shorturl_use2', '','trim|required|max_length[2]|xss_clean');
		$this->validation->set_rules('shorturl_keyType', '','trim|required|max_length[5]|xss_clean');
		$this->validation->set_rules('shorturl_app_id', '','trim');
		$this->validation->set_rules('shorturl_app_key', '','trim');
		$this->validation->set_rules('shorturl_app_token', '','trim');
		//짧은 주소 설정값 저장
		$shorturl_use		= $this->input->post('shorturl_use2');
		$shorturl_app_id	= $this->input->post('shorturl_app_id');
		$shorturl_app_key	= $this->input->post('shorturl_app_key');
		$shorturl_app_token	= $this->input->post('shorturl_app_token');
		$shorturl_keyType	= $this->input->post('shorturl_keyType');
		$shorturl_config = array('shorturl_use'=> $shorturl_use, 'shorturl_app_id'=> $shorturl_app_id,'shorturl_app_key'=> $shorturl_app_key,'shorturl_app_token'=> $shorturl_app_token,'shorturl_keyType'=> $shorturl_keyType);
		$this->arrSns = $shorturl_config + $this->arrSns;
		//짧은 주소 테스트 통신
		$shorturl_test	= get_connet_protocol().$this->config_system['domain'].'/personal_referer/access?inflow=shorturl&mid=1';
		list($shorturl, $shorturl_result) = get_shortURL($shorturl_test);

		if($shorturl_keyType == 'token'){
			if(parse_url($shorturl, PHP_URL_SCHEME)!='https'){
				$result = false;
			}else{
				$result = true;
			}
		}else{
			$result = $shorturl_result;
		}
		if($result){
			config_save('snssocial', $shorturl_config);
		}

		echo json_encode(array("result"=>$result,'shorturl'=>$shorturl));
	}

	/* 운영정책 설정 */
	public function operating(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_operating_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		config_save('basic',array('operating'=>$_POST['operating']));
		if(isset($_POST['general_use'])) config_save('basic',array('general_use'=>$_POST['general_use']));
		if(isset($_POST['intro_use'])) config_save('basic',array('intro_use'=>$_POST['intro_use']));
		if(isset($_POST['member_use'])) config_save('basic',array('member_use'=>$_POST['member_use']));
		if(isset($_POST['adult_use'])) config_save('basic',array('adult_use'=>$_POST['adult_use']));

		// 운영방식 모바일(태블릿) 추가 2014-05-20 leewh
		if(isset($_POST['intro_m_use'])) config_save('basic',array('intro_m_use'=>$_POST['intro_m_use']));
		if(isset($_POST['general_m_use'])) config_save('basic',array('general_m_use'=>$_POST['general_m_use']));
		if(isset($_POST['member_m_use'])) config_save('basic',array('member_m_use'=>$_POST['member_m_use']));
		if(isset($_POST['adult_m_use'])) config_save('basic',array('adult_m_use'=>$_POST['adult_m_use']));

		if(isset($_POST['adult_use']) && $_POST['adult_use']){
			$realname = config_load('realname');
			if( $realname['realnamephoneSikey'] && $realname['realnamePhoneSipwd'] ){
				config_save('realname',array('useRealnamephone'=>'Y'));
				config_save('realname',array('useRealnamephone_adult'=>'Y'));
			}
			if( $realname['ipinSikey'] && $realname['ipinKeyString'] ){
				config_save('realname',array('useIpin'=>'Y'));
				config_save('realname',array('useIpin_adult'=>'Y'));
			}
		}

		$callback = "parent.document.location.reload();";
		if(isset($_POST['adult_use']) && $_POST['adult_use']){
			openDialogAlert("접속 및 회원가입시에 성인인증을 사용하도록 자동 설정되었습니다.<br>설정>회원>본인확인에서 확인하실 수 있습니다.",550,170,'parent',$callback);
		}else{
			openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
		}
	}

	/* 아이콘 삭제 */
	public function icon_delete(){
		$iconType = $_GET['icontype'];
		if( $iconType && $iconType!='favicon' ){
			$icon = config_load('system', $iconType);
			@unlink('./'.$icon[$iconType]);
			config_save('system',array($iconType=>''));
			echo json_encode(array('result'=>'ok'));
		}else if( $iconType=='favicon' ){
			$this->load->model('adminenvmodel');
			$where_params['shopSno']	= $this->config_system['shopSno'];
			$query	= $this->adminenvmodel->get($where_params, 0, 1);
			$row	= $query->row_array();
			if($row['favicon']){
				@unlink('./'.$row['favicon']);
			}
			$update_params['favicon']	= '';
			$where_params['shopSno']	= $this->config_system['shopSno'];
			$this->adminenvmodel->update($update_params,$where_params);
			echo json_encode(array('result'=>'ok'));
		}else{
			echo json_encode(array('result'=>'fail'));
		}
	}

	/* snslogo 삭제 */
	public function snslogo_delete(){
		$snslogo = config_load('system', 'snslogo');
		@unlink('./'.$snslogo['snslogo']);
		config_save('system',array('snslogo'=>''));
		echo json_encode(array('result'=>'ok'));
	}

	//fblike icon save
	public function fblikeiconUpload(){
		$this->load->library('Upload');
		if (is_uploaded_file($_FILES['fblikeboxpciconFile']['tmp_name'])) {

			$dir = ROOTPATH.'/data/icon/facebook_like';
			if(!is_dir($dir) ) {
				@mkdir($dir);
				@chmod($dir,0707);
			}

			$config['upload_path'] = './data/icon/facebook_like';
			$config['max_size']	= $this->config_system['uploadLimit'];
			$tmp = getimagesize($_FILES['fblikeboxpciconFile']['tmp_name']);
			$_FILES['fblikeboxpciconFile']['type'] = $tmp['mime'];

			$config['upload_path']		= $path = ROOTPATH."/data/icon/facebook_like/";
			$file_ext = end(explode('.', $_FILES['fblikeboxpciconFile']['name']));//확장자추출
			$config['allowed_types']	= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;
			$file_name	= 'fblikebox.'.$file_ext;
			$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
			$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
			$config['file_name'] = $file_name;
			$this->upload->initialize($config);

			if ($this->upload->do_upload('fblikeboxpciconFile')) {
				@chmod($config['upload_path'].$config['file_name'], 0777);

				$uploadData = $this->upload->data();
				$fb_likebox_icon = str_replace($_SERVER['DOCUMENT_ROOT'],'',$uploadData['file_path']).$uploadData['raw_name'].$uploadData['file_ext'];
				config_save('snssocial',array('fb_likebox_icon'=> $fb_likebox_icon));
				$callback = "parent.fblikeiconDisplay('{$fb_likebox_icon}?".time()."');";
				openDialogAlert("등록하였습니다.",400,140,'parent',$callback);
			}else{
				$callback = "";
				openDialogAlert("gif, jpg,jpeg, png 만 가능합니다.",400,140,'parent',$callback);
			}
		}else{
			$callback = "";
			openDialogAlert("등록 파일이 없습니다.",400,140,'parent',$callback);
		}
		exit;
	}

	//fbunlike icon save
	public function fbunlikeiconUpload(){
		$this->load->library('Upload');
		if (is_uploaded_file($_FILES['fbunlikeboxpciconFile']['tmp_name'])) {

			$dir = ROOTPATH.'/data/icon/facebook_like';
			if(!is_dir($dir) ) {
				@mkdir($dir);
				@chmod($dir,0707);
			}

			$config['upload_path'] = './data/icon/facebook_like';
			$config['max_size']	= $this->config_system['uploadLimit'];
			$tmp = getimagesize($_FILES['fbunlikeboxpciconFile']['tmp_name']);
			$_FILES['fbunlikeboxpciconFile']['type'] = $tmp['mime'];

			$config['upload_path']		= $path = ROOTPATH."/data/icon/facebook_like/";
			$file_ext = end(explode('.', $_FILES['fbunlikeboxpciconFile']['name']));//확장자추출
			$config['allowed_types']	= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;
			$file_name	= 'fbunlikebox.'.$file_ext;
			$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
			$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
			$config['file_name'] = $file_name;
			$this->upload->initialize($config);

			if ($this->upload->do_upload('fbunlikeboxpciconFile')) {
				@chmod($config['upload_path'].$config['file_name'], 0777);

				$uploadData = $this->upload->data();
				$fb_likebox_icon = str_replace($_SERVER['DOCUMENT_ROOT'],'',$uploadData['file_path']).$uploadData['raw_name'].$uploadData['file_ext'];
				config_save('snssocial',array('fb_unlikebox_icon'=> $fb_likebox_icon));
				$callback = "parent.fbunlikeiconDisplay('{$fb_likebox_icon}?".time()."');";
				openDialogAlert("등록하였습니다.",400,140,'parent',$callback);
			}else{
				$callback = "";
				openDialogAlert("gif, jpg,jpeg, png 만 가능합니다.",400,140,'parent',$callback);
			}
		}else{
			$callback = "";
			openDialogAlert("등록 파일이 없습니다.",400,140,'parent',$callback);
		}
		exit;
	}


	/* fblike 삭제 */
	public function fblike_delete(){
		if( $_GET['fblikemode'] == 'unlike' ){
			$snsinfo = config_load('snssocial', 'fb_unlikebox_icon');
			@unlink('./'.$snsinfo['fb_unlikebox_icon']);
			config_save('snssocial',array('fb_unlikebox_icon'=>''));
		}else{
			$snsinfo = config_load('snssocial', 'fb_likebox_icon');
			@unlink('./'.$snsinfo['fb_likebox_icon']);
			config_save('snssocial',array('fb_likebox_icon'=>''));
		}
		echo json_encode(array('result'=>'ok'));
	}


	/* 은행설정 */
	public function bank(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_bank_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		/* 인증 */
		$this->validation->set_rules('bank[]', '은행','trim|required|max_length[10]|xss_clean');
		$this->validation->set_rules('bankUser[]', '예금주','trim|xss_clean');
		$this->validation->set_rules('account[]', '계좌번호','trim|xss_clean');
		$this->validation->set_rules('accountUse[]', '사용여부','trim|required|max_length[10]|xss_clean');
		$this->validation->set_rules('bankReturn[]', '반품배송비 입금계좌 은행','trim|max_length[10]|xss_clean');
		$this->validation->set_rules('bankUserReturn[]', '반품배송비 입금계좌 예금주','trim|xss_clean');
		$this->validation->set_rules('accountReturn[]', '반품배송비 입금계좌 계좌번호','trim|xss_clean');
		$this->validation->set_rules('accountUseReturn[]', '반품배송비 입금계좌 사용여부','trim|max_length[10]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		/* 설정 초기화 */
		$this->setting->bank();

		/* 설정 초기화 */
		$this->setting->bank2();

		//관리자 로그 남기기
		$this->load->library('managerlog');
		$this->managerlog->insertData();

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,150,'parent',$callback);
	}

	/* 주문설정 */
	public function order(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_order_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		$aPostParams = $this->input->post();

		/* 인증 */
		$aPostParams['ableStockLimit'] = (int) $aPostParams['ableStockLimit'];
		$aPostParams['cartDuration'] = (int) $aPostParams['cartDuration'];
		$aPostParams['cancelDuration'] = (int) $aPostParams['cancelDuration'];
		$aPostParams['ableStockStep'] = (int) $aPostParams['ableStockStep'];
		$aPostParams['refundDuration'] = (int) $aPostParams['refundDuration'];

		$aPostParams['cashreceiptuse']		=  if_empty($aPostParams, 'cashreceiptuse', '0');
		$aPostParams['taxuse']		=  if_empty($aPostParams, 'taxuse', '0');

		$this->validation->set_rules('runout', '재고에 따른 상품판매 여부','trim|required|max_length[10]|xss_clean');
		$this->validation->set_rules('ableStockLimit', '가용재고 품절표기 갯수','trim|numeric|xss_clean');
		$this->validation->set_rules('cartDuration', '장바구니 상품 보존기간','trim|required|numeric|xss_clean');
		$this->validation->set_rules('cancelDuration', '자동 주문 취소일','trim|required|numeric|xss_clean');
		$this->validation->set_rules('ableStockStep', '출고예약량','trim|required|numeric|xss_clean');
		//$this->validation->set_rules('refundDuration', '반품,환불,맞교환 가능일','trim|required|numeric|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,100,'parent',$callback);
			exit;
		}

		/* 구매확정 사용 -> 미사용 설정 방지*/
		$aBuyConfirmUse	= config_load('order', 'buy_confirm_use');
		$sCfgBuyConfirmUse	= $aBuyConfirmUse['buy_confirm_use'];
		$sInBuyConfirmUse	= $this->input->post('buy_confirm_use');
		if($sCfgBuyConfirmUse == '1' && $sCfgBuyConfirmUse != $sInBuyConfirmUse){
			$callback = '';
			openDialogAlert("구매확정을 미사용으로 변경하실 수 없습니다.", 400, 150, 'parent', $callback);
			exit;
		}


		/* 설정저장 */
		config_save('order',array('runout'=>$aPostParams['runout']));
		config_save('order',array('ableStockLimit'=>$aPostParams['ableStockLimit']));
		config_save('order',array('cartDuration'=>$aPostParams['cartDuration']));
		config_save('order',array('cancelDuration'=>$aPostParams['cancelDuration']));
		config_save('order',array('ableStockStep'=>$aPostParams['ableStockStep']));
		config_save('order',array('refundDuration'=>$aPostParams['refundDuration']));
		config_save('order',array('autocancel'=>$aPostParams['autocancel']));
		config_save('order',array('export_err_handling'=>$aPostParams['export_err_handling']));

		config_save('order' ,array('buy_confirm_use'=>$aPostParams['buy_confirm_use']));
		config_save('order' ,array('save_term'=>$aPostParams['save_term']));
		config_save('order' ,array('save_type'=>$aPostParams['save_type']));

		//config_save('order' ,array('cancellation'=>$_POST['cancellation']));//청약철회추가 , 위치변경
		config_save('order' ,array('cancelDisabledStep35'=>$aPostParams['cancelDisabledStep35']));
		config_save('order' ,array('provider_do_order_done'=>$aPostParams['provider_do_order_done']));

		// 오프라인 주문 미매칭 주문 수집 처리
		config_save('order',array('not_match_goods_order'=>$aPostParams['not_match_goods_order']));

		// 선물하기
		config_save('order',array('present_use'=>$aPostParams['present_use']));
		config_save('order',array('present_seller_use'=>$aPostParams['present_seller_use']));

		// 개인정보보호 대상 주문 설정
		config_save('order',array('separating_personal_info'=>$aPostParams['separating_personal_info']));

		/* 절사 */
		config_save('system',array('cutting_sale_use'=>$aPostParams['cutting_sale_use']));
		config_save('system',array('cutting_sale_price'=>$aPostParams['cutting_sale_price']));
		config_save('system',array('cutting_sale_action'=>$aPostParams['cutting_sale_action']));
		config_save('system',array('cutting_settle_use'=>$aPostParams['cutting_settle_use']));
		config_save('system',array('cutting_settle_price'=>$aPostParams['cutting_settle_price']));
		config_save('system',array('cutting_settle_action'=>$aPostParams['cutting_settle_action']));

		// 기본배송국가
		config_save('system',array('cfg_default_nation'=>$aPostParams['cfg_default_nation']));

		$codecd = $aPostParams['codecd'];
		$reason = $aPostParams['reason'];
		$idx = 1;
		$this->db->query('delete from fm_return_reason where return_type = "goods" ');
		for($i=0; $i<count($codecd); $i++){
			unset($params);
			$params["return_type"] = 'goods';
			$params["idx"] = $idx;
			$params["codecd"] = $codecd[$i];
			$params["reason"] = $reason[$i];
			if($params["reason"] != ""){
				$this->db->insert('fm_return_reason', $params);
				$idx++;
			}
		}

		//티켓상품 환불사유
		$codecdcoupon = $aPostParams['codecdcoupon'];
		$reasoncoupon = $aPostParams['reasoncoupon'];
		$idx = 1;
		$this->db->query('delete from fm_return_reason where return_type = "coupon" ');
		for($i=0; $i<count($codecdcoupon); $i++){
			unset($params);
			$params["return_type"] = 'coupon';
			$params["idx"] = $idx;
			$params["codecd"] = $codecdcoupon[$i];
			$params["reason"] = $reasoncoupon[$i];
			if($params["reason"] != ""){
				$this->db->insert('fm_return_reason', $params);
				$idx++;
			}
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 주문설정 */
	public function sale(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_order_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		//매출증빙설정
		config_save('order',array('vat'=>$_POST['vat']));
		config_save('order',array('cashreceiptuse'=>$_POST['cashreceiptuse']));
		config_save('order',array('biztype'=>$_POST['biztype']));
		config_save('order',array('cashreceiptpg'=>$_POST['cashreceiptpg']));
		config_save('order',array('cashreceiptid'=>$_POST['cashreceiptid']));
		config_save('order',array('cashreceiptkey'=>$_POST['cashreceiptkey']));
		config_save('order',array('taxuse'=>$_POST['taxuse']));
		config_save('order',array('sale_reserve_yn'=>$_POST['sale_reserve_yn']));
		config_save('order',array('sale_emoney_yn'=>$_POST['sale_emoney_yn']));

		## 현금영수증 의무 발행
		config_save('order',array('cashreceiptauto'=>$_POST['cashreceiptauto']));
		config_save('order',array('cashreceiptautoprice'=>$_POST['cashreceiptautoprice']));

		###
		config_save('order',array('hiworks_use'=>$_POST['hiworks_use']));
		config_save('order',array('cashreceipt_auto'=>$_POST['cashreceipt_auto']));

		config_save('system',array('webmail_admin_id'=>$_POST['webmail_admin_id']));
		config_save('system',array('webmail_domain'=>$_POST['webmail_domain']));
		config_save('system',array('webmail_key'=>$_POST['webmail_key']));


		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	// 구버전 배송비 등록 - DEL 예정
	public function shipping(){

		// 초기값 등록
		if($_POST['useYn']!='y') $_POST['useYn'] = 'n';

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_shipping_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		$result = false;
		$this->validation->set_rules('shipping', '배송방법','trim|required|xss_clean');
		$this->validation->set_rules('useYn', '사용설정','trim|required|max_length[1]|xss_clean');
		$this->validation->set_rules('summary', '설명','trim|max_length[100]|xss_clean');

		/* 택배일 경우 */
		if($_POST['shipping']=='delivery'){
			$this->validation->set_rules('deliveryCompanyCode[]', '택배사','trim|required|xss_clean');
			$this->validation->set_rules('deliveryCostPolicy', '기본 배송비 선택','trim|max_length[5]|required|xss_clean');
			if($_POST['deliveryCostPolicy']=='pay'){
				$this->validation->set_rules('payDeliveryCost', '배송비','trim|required|xss_clean|numeric');
			}
			if($_POST['deliveryCostPolicy']=='ifpay'){
				$this->validation->set_rules('ifpayFreePrice', '상품판매가격의 합','trim|required|xss_clean|numeric');
				$this->validation->set_rules('ifpayDeliveryCost', '배송비','trim|required|xss_clean|numeric');
			}
			if($_POST['deliveryCostPolicy']=='pay'){
				$this->validation->set_rules('payDeliveryCost', '배송비','trim|required|xss_clean|numeric');
			}

			if($_POST['orderDeliveryFree']=='free'){
				if( !$_POST['issueCategoryCode'] && !$_POST['issueBrandCode'] && !$_POST['issueGoods']){
					openDialogAlert("배송비 무료화 정책 상품이나 브랜드,카테고리를 <br/>설정해주세요.",400,150,'parent',$callback);
					exit;
				}
			}

			foreach($_POST['sigungu'] as $k => $sigungu){
				if(!$sigungu){
					unset($_POST['sigungu'][$k]);
					unset($_POST['addDeliveryCost'][$k]);
				}else{
					$result = true;
				}
			}
			if($result){
				$this->validation->set_rules('addDeliveryCost[]', '추가 배송비','trim|xss_clean|numeric');
			}
		}
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,150,'parent',$callback);
			exit;
		}

		/* 본사 설정 > 택배/배송비 필수 여부처리 */
		if($_POST['shipping']!='add_delivery' && $_POST['provider_seq'] == 1 ) {
			$shipping = 'shipping'.$_POST['shipping'];
			config_save($shipping ,array('useYn'=>$_POST['useYn']));
			config_save($shipping ,array('summary'=>$_POST['summary']));
		}


		$this->load->model('providershipping');

		$where_params['provider_seq']					=  $_POST['provider_seq']; //  본사 입점사코드 1

		/* 택배일 경우 */
		if($_POST['shipping']=='delivery'){
			// 지정상품 무료
			$update_params = array();
			$update_params['summary'] =  $_POST['summary'];
			$update_params['use_yn']						= $_POST['useYn']; //  본사 입점사코드 1
			$update_params['company_code']					= implode("|",(array)$_POST['deliveryCompanyCode']);
			$update_params['delivery_cost_policy'] 			= $_POST['deliveryCostPolicy'];
			$update_params['delivery_type']					= $_POST['deliveryCostPolicy'];
			$update_params['pay_delivery_cost']				= (int) $_POST['payDeliveryCost'];
			$update_params['ifpay_free_price']				= (int) $_POST['ifpayFreePrice'];
			$update_params['ifpay_delivery_cost']			= (int) $_POST['ifpayDeliveryCost'];

			$order_delivery_free = '';
			$issueCategoryCode = '';
			$issuebrandCode = '';
			$issueGoods  = '';
			$exceptIssueGoods  = '';
			if($_POST['orderDeliveryFree']) $order_delivery_free = $_POST['orderDeliveryFree'];
			if($_POST['issueCategoryCode']) $issueCategoryCode = implode("|",$_POST['issueCategoryCode']);
			if($_POST['issueBrandCode']) $issueBrandCode = implode("|",$_POST['issueBrandCode']);
			if($_POST['issueGoods']) $issueGoods = implode("|",$_POST['issueGoods']);
			if($_POST['exceptIssueGoods']) $exceptIssueGoods = implode("|",$_POST['exceptIssueGoods']);
			$update_params['order_delivery_free']	= $order_delivery_free;
			$update_params['issue_category_code']	= $issueCategoryCode;
			$update_params['issue_brand_code']		= $issueBrandCode;
			$update_params['issue_goods']			= $issueGoods;
			$update_params['except_issue_goods']	= $exceptIssueGoods;
		}else if($_POST['shipping']=='add_delivery'){
			$addDeliveryCosts = array();
			foreach($_POST['sigungu'] as $k=>$v){
				if ($v != "") $addDeliveryCosts[] = $_POST['sigungu'][$k].':'.$_POST['sigungu_street'][$k].':'.$_POST['addDeliveryCost'][$k];
			}
			$update_params['add_delivery_cost'] = implode("|",$addDeliveryCosts);

		}else if($_POST['shipping']=='address'){
			$update_params = array();

			$update_params['sendding_zipcode'] =  implode('-',$_POST['senderZipcode']);

			$update_params['sendding_address_type'] =  $_POST['senderAddress_type'];
			$update_params['sendding_address'] =  $_POST['senderAddress'];
			$update_params['sendding_address_street'] =  $_POST['senderAddress_street'];
			$update_params['sendding_address_detail'] =  $_POST['senderAddressDetail'];

			$update_params['return_zipcode'] =  implode('-',$_POST['returnZipcode']);

			$update_params['return_address_type'] =  $_POST['returnAddress_type'];
			$update_params['return_address'] =  $_POST['returnAddress'];
			$update_params['return_address_street'] =  $_POST['returnAddress_street'];
			$update_params['return_address_detail'] =  $_POST['returnAddressDetail'];
		}else if($_POST['shipping']=='postpaid'){
			// ALTER TABLE `fm_provider_shipping` ADD `postpaid_delivery_summary` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL AFTER `postpaid_delivery_cost` ;
			$update_params = array();
			$update_params['postpaid_delivery_summary']		=  $_POST['postpaid_delivery_summary'];
			$update_params['postpaid_delivery_cost_yn']		= $_POST['postpaidDeliveryCostYn']=='y'?'y':'n';
			$update_params['postpaid_delivery_cost']		= (int) $_POST['postpaidDeliveryCost'];
		}else{
			// 지정상품 무료
			$update_params = array();
			if($_POST['shipping'] == 'direct'){
				$update_params['direct_use_yn'] = $_POST['useYn']; //  본사 입점사코드 1
				$update_params['direct_summary'] =  $_POST['summary'];
			}

			if($_POST['shipping'] == 'quick'){
				$update_params['quick_use_yn'] = $_POST['useYn']; //  본사 입점사코드 1
				$update_params['quick_summary'] =  $_POST['summary'];
			}

		}

		$this->providershipping->set_provider_shipping($update_params,$where_params);

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}



	public function international_shipping(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_shipping_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		$this->validation->set_rules('useYn','사용설정','trim|required|max_length[1]|xss_clean');
		$this->validation->set_rules('company','방법','trim|required|max_length[5]|xss_clean');
		$this->validation->set_rules('summary','설명','trim|max_length[70]|xss_clean');
		$this->validation->set_rules('defaultGoodsWeight', '설명','trim|required|max_length[10]|xss_clean');
		$this->validation->set_rules('goodsWeight[]','무게','trim|required|max_length[10]|xss_clean');
		$this->validation->set_rules('region[]','해외 지역','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('regionSummary[]','해외 지역 설명','trim|max_length[30]|xss_clean');
		$this->validation->set_rules('deliveryCost[]','배송비','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('exceptCategory[]','카테고리','trim|max_length[16]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,100,'parent',$callback);
			exit;
		}
		$groupcd = "internationalShipping".$_POST['company'];
		config_save($groupcd ,array('useYn'=>$_POST['useYn']));
		config_save($groupcd ,array('company'=>$_POST['company']));
		config_save($groupcd ,array('summary'=>$_POST['summary']));
		config_save($groupcd ,array('defaultGoodsWeight'=>$_POST['defaultGoodsWeight']));
		config_save($groupcd ,array('region'=>$_POST['region']));
		config_save($groupcd ,array('regionSummary'=>$_POST['regionSummary']));
		config_save($groupcd ,array('goodsWeight'=>$_POST['goodsWeight']));
		config_save($groupcd ,array('deliveryCost'=>$_POST['deliveryCost']));
		config_save($groupcd ,array('exceptCategory'=>$_POST['exceptCategory']));

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	//
	public function reserve(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_reserve_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}
		### Validation
		$this->validation->set_rules('default_reserve_percent','기본 마일리지 적용 상품','trim|numeric|max_length[5]|xss_clean');
		$this->validation->set_rules('emoney_use_limit','보유 마일리지 기준','trim|numeric|xss_clean');
		$this->validation->set_rules('emoney_price_limit','상품 판매가격 기준','trim|numeric|xss_clean');
		$this->validation->set_rules('min_emoney','최소 마일리지 사용금액','trim|numeric|xss_clean');
		$this->validation->set_rules('max_emoney_percent','최대 마일리지 사용금액','trim|numeric|max_length[5]|xss_clean');
		$this->validation->set_rules('max_emoney','최대 마일리지 사용금액','trim|numeric|xss_clean');
		$this->validation->set_rules('max_emoney_policy','최대 마일리지 사용정책','trim|required|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,130,'parent',$callback);
			exit;
		}
		$groupcd = "reserve";
		config_save($groupcd ,array('default_reserve_percent'=>$_POST['default_reserve_percent']));
		config_save($groupcd ,array('emoney_use_limit'=>get_cutting_price($_POST['emoney_use_limit'])));
		config_save($groupcd ,array('emoney_price_limit'=>get_cutting_price($_POST['emoney_price_limit'])));
		config_save($groupcd ,array('min_emoney'=>get_cutting_price($_POST['min_emoney'])));
		config_save($groupcd ,array('max_emoney_percent'=>$_POST['max_emoney_percent']));
		config_save($groupcd ,array('max_emoney'=>get_cutting_price($_POST['max_emoney'])));
		config_save($groupcd ,array('max_emoney_policy'=>$_POST['max_emoney_policy']));


		config_save($groupcd ,array('emoney_exchange_use'=>$_POST['emoney_exchange_use']));

		if( $_POST['emoney_exchange_use'] == 'y'){
			if(!$_POST['minum_point']){
				openDialogAlert("최소 교환 가능 포인트를 정확히 입력해 주세요.",500,140,'parent',$callback);
				exit;
			}
			if(!$_POST['point_rate']){
				openDialogAlert("교환비율을 정확히 입력해 주세요.",500,140,'parent',$callback);
				exit;
			}
		}
		config_save($groupcd ,array('emoney_minum_point'=>get_cutting_price($_POST['minum_point'])));
		config_save($groupcd ,array('emoney_point_rate'=>get_cutting_price($_POST['point_rate'])));



		### ADD 201211
		config_save($groupcd ,array('cash_use'=>$_POST['cash_use']));

		config_save($groupcd ,array('reserve_select'=>$_POST['reserve_select']));
		config_save($groupcd ,array('reserve_year'=>$_POST['reserve_year']));
		config_save($groupcd ,array('reserve_direct'=>$_POST['reserve_direct']));


		config_save($groupcd ,array('exchange_emoney_select'=>$_POST['exchange_emoney_select']));
		config_save($groupcd ,array('exchange_emoney_year'=>$_POST['exchange_emoney_year']));
		config_save($groupcd ,array('exchange_emoney_direct'=>$_POST['exchange_emoney_direct']));

		$_POST['refundDuration'] = (int) $_POST['refundDuration'];
		config_save('order',array('refundDuration'=>$_POST['refundDuration']));
		if( $this->isplusfreenot) {//무료몰이아닌경우에만 적용@2013-01-14
			config_save($groupcd ,array('point_use'=>$_POST['point_use']));
			config_save($groupcd ,array('default_point_app'=>get_cutting_price($_POST['default_point_app'])));
			config_save($groupcd ,array('default_point_type'=>$_POST['default_point_type']));
			config_save($groupcd ,array('default_point_percent'=>$_POST['default_point_percent']));
			config_save($groupcd ,array('default_point'=>$_POST['default_point']));
			config_save($groupcd ,array('point_select'=>$_POST['point_select']));
			config_save($groupcd ,array('point_year'=>$_POST['point_year']));
			config_save($groupcd ,array('point_direct'=>$_POST['point_direct']));
		}

		/* 마일리지 설정 관련 기본값 추가 leewh 2014-06-24 */
		config_save($groupcd ,array('emoney_using_unit'=>$_POST['emoney_using_unit']));
		config_save($groupcd ,array('default_reserve_limit'=>$_POST['default_reserve_limit']));

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	/* 보안설정 저장 */
	public function protect(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_protect_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_protect_act');
		if(!$auth){
			$callback = "parent.history.go(-1);";
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		if(!isset($_POST['ssl'])) $_POST['ssl'] = "";
		if(!isset($_POST['protectMouseRight'])) $_POST['protectMouseRight'] = "";
		if(!isset($_POST['protectMouseDragcopy'])) $_POST['protectMouseDragcopy'] = "";
		if(!isset($_POST['protectIp'])) $_POST['protectIp'] = array();

		/* 설정저장 */
		$this->setting->protect();

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}


	/* 관리자 등록 */
	public function manager_reg(){

		if( $this->isdemo['isdemo'] ){
			$callback = "parent.document.location.reload();";
			openDialogAlert($this->isdemo['msg'],500,140,'parent',$callback);
			exit;
		}

		$this->load->model('multishopmodel');
		$result = $this->multishopmodel->getAdminEnv('');
		foreach($result as $data_multi){
			$this->multishopmodel->multiShopDBConnection($data_multi['shopSno']);
		}


		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_manager_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		// 관리자 아이디/비밀번호 xss_clean : input->post() 는 이미 변환이 되어 original POST GET 이용해야함
		$this->load->helper('Security');
		$this->load->helper('xssfilter');
		xss_clean_basic($_POST['manager_id']);
		xss_clean_basic($_POST['mpasswd']);

		// 비밀번호 유효성 체크
		$pre_enc_password = '';
		$enc_password = '';

		$check_password = $this->input->post('mpasswd');
		$password_params = array(
			'birthday'				=> '',
			'phone'					=> '',
			'cellphone'				=> '',
			'pre_enc_password'		=> $pre_enc_password,
			'enc_password'			=> $enc_password,
		);
		$this->load->library('memberlibrary');
		$result = $this->memberlibrary->check_password_validation($check_password, $password_params);
		if($result['code'] != '00' && $result['alert_code']){
			openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback);
			exit;
		}

		### required
		$this->validation->set_rules('manager_id', '아이디','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('mpasswd', '비밀번호','trim|required|min_length[8]|max_length[20]|xss_clean');
		$this->validation->set_rules('mpasswd_re', '비밀번호확인','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('mname', '이름','trim|required|max_length[32]|xss_clean');
		###
		$this->validation->set_rules('memail', '이메일','trim|max_length[64]|valid_email|xss_clean');
		$this->validation->set_rules('mcellphone', '휴대폰번호','trim|numeric|max_length[20]|xss_clean');
		$this->validation->set_rules('mphone', '연락처','trim|numeric|max_length[20]|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,150,'parent',$callback);
			exit;
		}

		$this->load->library('managerlibrary');
		$this->managerlibrary->exec_valid_limit_ip($_POST);
		$this->managerlibrary->exec_valid_limit_ip($_POST,'admin_limit_ip');

		if($_POST['mpasswd'] != $_POST['mpasswd_re']){
			$callback = "parent.document.getElementsByName('mpasswd_re')[0].focus();";
			openDialogAlert("비밀번호 확인이 다릅니다.",400,140,'parent',$callback);
			exit;
		}

		### 회원정보 다운로드 비밀번호 체크 leewh 2014-07-10
		if ($_POST['member_download']=='Y') {
			$this->multishopmodel->start_auto_sync('member_download');
			/* 주요행위 기록 */
			$this->managermodel->insert_action_history($this->managerInfo['manager_seq'],'member_excel_download_auth',$_POST['mname'].'('.$_POST['manager_id'].')');

			$this->multishopmodel->run_auto_sync('member_download');
		}

		###
		$return_result = $this->id_chk('re_chk');
		if(!$return_result['return']){
			$callback = "parent.document.getElementsByName('manager_id')[0].focus();";
			openDialogAlert($return_result['return_result'],400,140,'parent',$callback);
			exit;
		}

		// 확인코드 유효성 및 중복확인
		if	(count($_POST['certify_code']) > 0){
			$this->load->model('providermodel');
			foreach($_POST['certify_code'] as $k => $certify_code){
				if(!$_POST['manager_name'][$k] || !$certify_code) continue;

				$certify_code	= trim($certify_code);
				if	(!$this->providermodel->check_certify_code($certify_code)){
					$callback = "";
					openDialogAlert("잘못된 확인코드가 있습니다.",400,140,'parent',$callback);
					exit;
				}

				//입력된 확인코드 체크
				if ( $certify_code_arry && in_array(trim($certify_code) ,$certify_code_arry) ) {
					$callback = "";
					openDialogAlert("중복된 확인코드가 있습니다.",400,140,'parent',$callback);
					exit;
				}
				$certify_code_arry[] = $certify_code;


				// 중복확인
				$param['certify_code']	= $certify_code;
				$certify				= $this->providermodel->get_certify_manager($param);
				if	($certify ) {
					$callback = "";
					openDialogAlert("중복된 확인코드가 있습니다.",400,140,'parent',$callback);
					exit;
				}
				unset($param);
			}//endforeach
		}//endif

		### AUTH
		$auth = $this->managermodel->manager_auth_list();

		###
		$params = $_POST;
		$params['mregdate']				= date('Y-m-d H:i:s');
		$params['mpasswd']				= hash('sha256',md5($_POST['mpasswd']));
		$params['passwordUpdateTime']	= date('Y-m-d H:i:s');

		### 회원정보 다운로드 미체크시 다운로드 비밀번호 삭제
		if ($_POST['member_download']=='Y') {
			$params['member_download_passwd'] = hash('sha256',md5($_POST['member_download_passwd']));
		} else {
			$params['member_download_passwd'] = "";
		}

		if($_POST['hp_chk']!='Y'){
			$_POST['auth_hp'] = "";
		}else{
			if(trim($_POST['auth_hp']) == ''){
				openDialogAlert("인증 휴대폰 번호를 입력하여 주세요.",400,140,'parent','');
				exit;
			}

			$hp_value_chk_cnt = 0;
			if(preg_match("/[a-z]/",$_POST["auth_hp"])) $hp_value_chk_cnt += 1;
			if(preg_match("/[A-Z]/",$_POST["auth_hp"])) $hp_value_chk_cnt += 1;
			if(preg_match("/[!#$%^&*()?+=\/]/",$_POST["auth_hp"])) $hp_value_chk_cnt += 1;

			if($hp_value_chk_cnt > 0){
				openDialogAlert("휴대폰 번호는 숫자로만 입력해주세요.",400,140,'parent','');
				exit;
			}

		}

		$this->multishopmodel->start_auto_sync('manager_auth');

		$data = filter_keys($params, $this->db->list_fields('fm_manager'));
		if(preg_match("/^\/?data\/tmp/i", $_POST["mphoto"])) $data['mphoto'] = $this->iconUpload();

		$result = $this->db->insert('fm_manager', $data);
		$manager_seq = $this->db->insert_id();

		// 확인코드 저장
		if	(count($_POST['certify_code']) > 0){
			foreach($_POST['certify_code'] as $k => $certify_code){
				if(!$_POST['manager_name'][$k] || !$certify_code) continue;

				$cparams['provider_seq']	= 1;
				$cparams['manager_id']		= trim($_POST['manager_id']);
				$cparams['manager_name']	= $_POST['manager_name'][$k];
				$cparams['certify_code']	= trim($certify_code);
				$this->providermodel->insert_certify($cparams);
				unset($cparams);
			}
		}

		$this->multishopmodel->run_auto_sync('manager_auth',array('fm_config'));

		## 관리자 권한 설정
		$noti_codes = array('noti_count_priod_order','noti_count_priod_board','noti_count_priod_account','noti_count_priod_warehousing');
		$data_auth	= array();
		$this->load->model('authmodel');
		$wheres['shopSno']			= $this->config_system['shopSno'];
		$wheres['manager_seq']	= $manager_seq;
		$wheres['codecd not like'] = '%_priod_%';
		$orderbys['idx'] 					= 'asc';
		$query_auth	= $this->authmodel->select('*',$wheres,$orderbys);
		foreach($query_auth->result_array() as $data){
			$data_auth[$data['codecd']]	= $data['value'];
		}
		$tmp_auth	= $this->authmodel->make_auth_list();
		if( $data_auth && ($data_auth['manager_yn'] == 'Y') ){
			$tmp_auth = $data_auth;
		}
		$authdel = true;
		if($data_auth!=$tmp_auth){
			$authdel = false;
			$where_not_ins['codecd'] = $noti_codes;
			$this->authmodel->del($wheres,'',$where_not_ins);
			foreach($tmp_auth as $key=>$value){
				$idx++;
				$insert_params['idx']			= $idx;
				$insert_params['shopSno']		= $this->config_system['shopSno'];
				$insert_params['manager_seq']	= $manager_seq;
				$insert_params['codecd']		= $key;
				$insert_params['value']			= $value;
				$this->authmodel->insert($insert_params);
			}
		}

		//게시판접근권한
		$this->load->model('boardadmin');
		foreach($_POST['boardid'] as $k => $boardauth) {
			$this->boardadmin->boardadmin_delete_all($manager_seq,$k);
			$board_act = ($_POST['board_act'][$k]>0)?$_POST['board_act'][$k]:'0';
			$board_view = ($_POST['board_view'][$k]>0)?$_POST['board_view'][$k]:'0';
			$board_view_pw = ($board_view && $_POST['board_view_pw'][$k]>0)?$_POST['board_view_pw'][$k]:'0';
			$badparams['boardid']				= $k;
			$badparams['manager_seq']		= $manager_seq;
			$badparams['board_act']			= $board_act;
			$badparams['board_view']			= ($board_view_pw==2)?$board_view_pw:$board_view;
			$badparams['r_manager_seq']	= $this->managerInfo['manager_seq'];
			$badparams['r_date']					= date('Y-m-d H:i:s');
			$this->boardadmin->boardadmin_write($badparams);
			unset($badparams);
		}

		// 관리자 접속제한 IP 설정
		if($this->managerInfo['manager_yn'] == 'Y' && $_POST['admin_limit_ip']){
			config_save('system',array('admin_limit_ip'=>$_POST['admin_limit_ip']));
		}

		//관리자 로그 남기기
		$this->load->library('managerlog');
		$this->managerlog->insertData();

		$callback = "parent.document.location.href='/admin/setting/manager';";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function manager_modify(){
		if( $this->isdemo['isdemo'] ){
			$callback = "parent.document.location.reload();";
			openDialogAlert($this->isdemo['msg'],500,140,'parent',$callback);
			exit;
		}

		$this->load->model('multishopmodel');
		$this->multishopmodel->multiShopDBConnection();

		### required
		if(isset($_POST['passwd_chg']) && $_POST['passwd_chg']=='Y'){
			$this->load->helper('Security');
			$this->load->helper('xssfilter');
			xss_clean_basic($_POST['mpasswd']);

			// 비밀번호 유효성 체크
			$query = "select * from fm_manager where manager_seq=?";
			$query = $this->db->query($query,array($_POST['manager_seq']));
			$data = $query->row_array();
			$pre_enc_password = $data['mpasswd'];
			$enc_password = hash('sha256',md5($this->input->post('mpasswd')));

			$check_password = $this->input->post('mpasswd');
			$password_params = array(
				'birthday'				=> '',
				'phone'					=> '',
				'cellphone'				=> '',
				'pre_enc_password'		=> $pre_enc_password,
				'enc_password'			=> $enc_password,
			);
			$this->load->library('memberlibrary');
			$result = $this->memberlibrary->check_password_validation($check_password, $password_params);
			if($result['code'] != '00' && $result['alert_code']){
				openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback);
				exit;
			}


			$this->validation->set_rules('mpasswd', '비밀번호','trim|required|min_length[8]|max_length[20]|xss_clean');
			$this->validation->set_rules('mpasswd_re', '비밀번호확인','trim|required|min_length[8]|max_length[20]|xss_clean');
			$this->validation->set_rules('manager_password', '현재 관리자 비밀번호','trim|required|max_length[32]|xss_clean');

			if($_POST['mpasswd'] != $_POST['mpasswd_re']){
				$callback = "parent.document.getElementsByName('mpasswd_re')[0].focus();";
				openDialogAlert("비밀번호 확인이 다릅니다.",400,140,'parent',$callback);
				exit;
			}

			$str_md5 = md5($_POST['mpasswd']);
			$str_sha256_md5 = hash('sha256',$str_md5);
			$sql = "select count(*) as cnt from (select * from fm_manager_pwd_history where manager_seq=? order by regist_date desc limit 2) a where a.pwd=? or a.pwd=?;";
			$query = $this->db->query($sql,array($_POST['manager_seq'],$str_md5,$str_sha256_md5));
			$res = $query->row_array();
			if($res['cnt']){
				openDialogAlert("사용할 수 없는 비밀번호입니다.",400,140,'parent',"");
				exit;
			}
		}

		$this->validation->set_rules('mname', '이름','trim|required|max_length[32]|xss_clean');
		###
		$this->validation->set_rules('memail', '이메일','trim|max_length[64]|valid_email|xss_clean');
		$this->validation->set_rules('mcellphone', '휴대폰번호','trim|numeric|max_length[20]|xss_clean');
		$this->validation->set_rules('mphone', '연락처','trim|numeric|max_length[20]|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$this->load->library('managerlibrary');
		$this->managerlibrary->exec_valid_limit_ip($_POST);
		$this->managerlibrary->exec_valid_limit_ip($_POST,'admin_limit_ip');

		## 관리자 비밀번호 검증
		if(isset($_POST['passwd_chg']) && $_POST['passwd_chg']=='Y'){
			$str_md5 = md5($_POST['manager_password']);
			$str_sha256_md5 = hash('sha256',$str_md5);
			$query = "select * from fm_manager where manager_id=? and (mpasswd=? OR mpasswd=?)";
			$query = $this->db->query($query,array($this->managerInfo['manager_id'],$str_md5,$str_sha256_md5));
			$data = $query->row_array();
			if(!$data){
				$callback = "";
				openDialogAlert("현재 로그인된 관리자 비밀번호가 일치하지 않습니다.",400,140,'parent',$callback);
				exit;
			}
		}

		if($_POST['hp_chk']!='Y'){
			$_POST['auth_hp'] = "";
		}else{
			if(trim($_POST['auth_hp']) == ''){
				openDialogAlert("인증 휴대폰 번호를 입력하여 주세요.",400,140,'parent','');
				exit;
			}

			$hp_value_chk_cnt = 0;
			if(preg_match("/[a-z]/",$_POST["auth_hp"])) $hp_value_chk_cnt += 1;
			if(preg_match("/[A-Z]/",$_POST["auth_hp"])) $hp_value_chk_cnt += 1;
			if(preg_match("/[!#$%^&*()?+=\/]/",$_POST["auth_hp"])) $hp_value_chk_cnt += 1;

			if($hp_value_chk_cnt > 0){
				openDialogAlert("휴대폰 번호는 숫자로만 입력해주세요.",400,140,'parent','');
				exit;
			}

		}

		list($managerData) = get_data("fm_manager",array("manager_seq"=>$_POST['manager_seq']));

		// 확인코드 유효성 및 중복확인
		if	(count($_POST['certify_code']) > 0){
			$this->load->model('providermodel');
			foreach($_POST['certify_code'] as $k => $certify_code){
				if(!$_POST['manager_name'][$k] || !$certify_code) continue;

				$certify_code	= trim($certify_code);
				if	(!$this->providermodel->check_certify_code($certify_code)){
					$callback = "";
					openDialogAlert("잘못된 확인코드가 있습니다.",400,140,'parent',$callback);
					exit;
				}

				//입력된 확인코드 체크
				if ( $certify_code_arry && in_array(trim($certify_code) ,$certify_code_arry) ) {
					$callback = "";
					openDialogAlert("중복된 확인코드가 있습니다.",400,140,'parent',$callback);
					exit;
				}
				$certify_code_arry[] = $certify_code;

				// 중복확인
				if	($_POST['certify_seq'][$k])	$param['out_seq']	= $_POST['certify_seq'][$k];
				$param['certify_code']	= $certify_code;
				$param['not_manager_id']	= $managerData['manager_id'];//본인꺼는 제외
				$certify				= $this->providermodel->get_certify_manager($param);
				if	($certify[0]['seq']){
					$callback = "";
					openDialogAlert("중복된 확인코드가 있습니다.",400,140,'parent',$callback);
					exit;
				}
				unset($param);
			}
		}

		$this->multishopmodel->start_auto_sync('manager_auth');

		/* 주요행위 기록 */
		if($_POST['member_download']=='Y' && !preg_match("/member_download=Y/i",$managerData['manager_auth'])){
			$this->managermodel->insert_action_history($this->managerInfo['manager_seq'],'member_excel_download_auth',$_POST['mname'].'('.$managerData['manager_id'].')');
		}

		## 관리자 권한 설정
		$noti_codes = array('noti_count_priod_order','noti_count_priod_board','noti_count_priod_account','noti_count_priod_warehousing');
		$data_auth	= array();
		$this->load->model('authmodel');
		$wheres['shopSno']			= $this->config_system['shopSno'];
		$wheres['manager_seq']	= $_POST['manager_seq'];
		$wheres['codecd not like'] = '%_priod_%';
		$orderbys['idx'] 					= 'asc';
		$query_auth	= $this->authmodel->select('*',$wheres,$orderbys);
		foreach($query_auth->result_array() as $data){
			$data_auth[$data['codecd']]	= $data['value'];
		}

		//관리자 로그 남기기
		$params_before			= $data_auth;

		### board
		$this->load->helper(array('board'));
		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		boardalllist();//게시판전체리스트

		$params_before['setting_board'] = $this->boardmanagerlist;

		$tmp_auth	= $this->authmodel->make_auth_list();

		if( $data_auth && ($data_auth['manager_yn'] == 'Y') ){
			$tmp_auth = $data_auth;
		}

		/**
		 * 20210525(kjw)
		 * 부관리자 자신 스스로 비밀번호 변경등을 할 경우 기존 관리자 권한이 전부 default N으로 돌아가는 문제 수정
		 */
		if ($data_auth && ($this->managerInfo['manager_yn'] != 'Y')) {
			$tmp_auth = $data_auth;
		}

		$authdel = true;
		if($data_auth!=$tmp_auth){
			$authdel = false;
			$where_not_ins['codecd'] = $noti_codes;
			$this->authmodel->del($wheres,'',$where_not_ins);
			foreach($tmp_auth as $key=>$value){
				$idx++;
				$insert_params['idx']			= $idx;
				$insert_params['shopSno']		= $this->config_system['shopSno'];
				$insert_params['manager_seq']	= $_POST['manager_seq'];
				$insert_params['codecd']		= $key;
				$insert_params['value']			= $value;
				$this->authmodel->insert($insert_params);
			}
		}

		## 관리자 메뉴 상단 처리 건수 표기
		if($_POST['noti_count_priod_order']){
			unset($wheres['codecd not like']);
			$where_ins['codecd'] = $noti_codes;
			$this->authmodel->del($wheres,$where_ins);
			echo end($this->db->queries);
			$data_auth	= $this->authmodel->select('max(idx) as midx',$wheres,$orderbys)->row_array();
			$idx = $data_auth['midx'];
			foreach( $noti_codes as $value){
				$idx++;
				$insert_params['idx']					= $idx;
				$insert_params['shopSno']		= $this->config_system['shopSno'];
				$insert_params['manager_seq']	= $_POST['manager_seq'];
				$insert_params['codecd']			= $value;
				$insert_params['value']				= $_POST[$value];
				$this->authmodel->insert($insert_params);
			}
		}

		$this->multishopmodel->run_auto_sync('manager_auth',array('fm_config'));

		$changes = array();
		$params = $_POST;
		if(isset($_POST['passwd_chg']) && $_POST['passwd_chg']=='Y'){
			$params['mpasswd']				= hash('sha256',md5($_POST['mpasswd']));
			$params['passwordUpdateTime']	= date('Y-m-d H:i:s');
			$changes[] = "비밀번호";
		}else{
			unset($params['mpasswd']);
		}

		if($data_auth!=$tmp_auth) $changes[] = "권한";
		if($managerData['mphone']!=$params['mphone']) $changes[] = "전화번호";
		if($managerData['mname']!=$params['mname']) $changes[] = "관리자명";
		if($managerData['mcellphone']!=$params['mcellphone']) $changes[] = "핸드폰";
		if($managerData['memail']!=$params['memail']) $changes[] = "이메일";
		if($managerData['limit_ip']!=$params['limit_ip']) $changes[] = "접속허용IP";

		$changesStr = $changes ? "(".implode(",",$changes).")" : '';

		$data = filter_keys($params, $this->db->list_fields('fm_manager'));

		if(preg_match("/^\/?data\/tmp/i", $_POST["mphoto"]))
		{
			$data['mphoto'] = $this->iconUpload($_POST['mphoto']);
		}else{
			$this->iconUpload($managerData['mphoto']);
			$data['mphoto'] = $_POST['mphoto'];
		}

		unset($data['manager_id']);
		unset($data['manager_seq']);
		$data['manager_log'] = "<div>".date("Y-m-d H:i:s")." 관리자(".$this->managerInfo['manager_id'].")가 정보{$changesStr}를 수정하였습니다. (".$_SERVER['REMOTE_ADDR'].")</div>".$_POST['manager_log'];
		//$data['manager_auth'] = $auth;

		$this->multishopmodel->start_auto_sync('multi_manager');
		$this->db->where('manager_seq', $params['manager_seq']);
		$result = $this->db->update('fm_manager', $data);

		// 비밀번호가 실제로 변경된 후에 이력을 생성
		if(isset($params['passwd_chg']) && $params['passwd_chg']=='Y'){
			$sql = "insert into fm_manager_pwd_history set manager_seq=?, pwd=?, regist_date=now()";
			$query = $this->db->query($sql,array($params['manager_seq'],$params['mpasswd']));
		}

		// 확인코드 저장
		if	(count($_POST['certify_code']) > 0){
			//$this->load->model('providermodel');//위에서 정의
			$this->providermodel->delete_certify(array('provider_seq' => 1,'manager_id' =>$managerData['manager_id']));
			foreach($_POST['certify_code'] as $k => $certify_code){
				if(!$_POST['manager_name'][$k] || !$certify_code) continue;

				$cparams['provider_seq']	= 1;
				$cparams['manager_id']		= trim($managerData['manager_id']);
				$cparams['manager_name']	= $_POST['manager_name'][$k];
				$cparams['certify_code']	= trim($certify_code);
				$this->providermodel->insert_certify($cparams);
				unset($cparams);
			}
		}
		$this->multishopmodel->run_auto_sync('multi_manager',array('fm_config'));

		if($this->managerInfo['manager_yn'] == 'Y' && $params['manager_yn'] != 'Y'){
			//게시판접근권한
			$this->load->model('boardadmin');
			foreach($_POST['boardid'] as $k => $boardauth) {
				$this->boardadmin->boardadmin_delete_all($params['manager_seq'],$k);
				$board_act = ($_POST['board_act'][$k]>0)?$_POST['board_act'][$k]:'0';
				$board_view = ($_POST['board_view'][$k]>0)?$_POST['board_view'][$k]:'0';
				$board_view_pw = ($board_view && $_POST['board_view_pw'][$k]>0)?$_POST['board_view_pw'][$k]:'0';
				$badparams['boardid']				= $k;
				$badparams['manager_seq']		= $params['manager_seq'];
				$badparams['board_act']			= $board_act;
				$badparams['board_view']			= ($board_view_pw==2)?$board_view_pw:$board_view;
				$badparams['r_manager_seq']	= $this->managerInfo['manager_seq'];
				$badparams['up_date']					= date('Y-m-d H:i:s');
				$this->boardadmin->boardadmin_write($badparams);
				unset($badparams);
			}
		}

		// 슈퍼관리자의 경우
		if( $this->managerInfo['manager_yn'] == 'Y' ){
			config_save('noti_count',
				array(
					'order'=>$_POST['noti_count_priod_order'],
					'board'=>$_POST['noti_count_priod_board'],
					'account'=>$_POST['noti_count_priod_account'],
					'warehousing'=>$_POST['noti_count_priod_warehousing']
				)
			);
		}

		// 관리자 접속제한 IP 설정
		if($this->managerInfo['manager_yn'] == 'Y' && isset($_POST['admin_limit_ip'])){
			config_save('system',array('admin_limit_ip'=>$_POST['admin_limit_ip']));
		}

		if($params['manager_yn'] != 'Y'){ //본사의 대표운영자는 저장하지 않음
			//관리자 로그 남기기
			$this->load->library('managerlog');
			$logInfo = array(
				'params_before'	=> $params_before,
			);
			$this->managerlog->insertData($logInfo);
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}



	public function id_chk($chk_key = null){
		$manager_id = $_REQUEST['manager_id'];
		if(!$manager_id) die();
		//$manager_id = strtolower($manager_id);

		###
		$count = get_rows('fm_manager',array('manager_id'=>$manager_id));

		$text = "사용할 수 있는 아이디 입니다.";
		$return = true;
		if($manager_id=='gabia'){
			$text = "사용할 수 없는 아이디 입니다.";
			$return = false;
		}else if(strlen($manager_id)<4 || strlen($manager_id)>16){
			$text = "글자 제한 수를 맞춰주세요.";
			$return = false;
		}else if(preg_match("/[^a-z0-9\-_]/i", $manager_id)) {
			$text = "사용할 수 없는 아이디 입니다.";
			$return = false;
		}else if($count > 0){
			$text = "이미 사용중인 아이디 입니다.";
			$return = false;
		}
		$result = array("return_result" => $text, "manager_id" => $manager_id, "return" => $return);

		if($chk_key){
			return $result;
		}else{
			echo json_encode($result);
		}
	}

	public function manager_delete(){
		$this->load->model('providermodel');
		$this->load->model('boardadmin');


		$this->load->model('multishopmodel');
		$result = $this->multishopmodel->getAdminEnv('');
		foreach($result as $data_multi){
			$this->multishopmodel->multiShopDBConnection($data_multi['shopSno']);
		}

		$this->multishopmodel->start_auto_sync('manager_auth');

		$manager_arr = $_GET['manager_seq'];
		foreach($manager_arr as $k){
			$this->db->where('manager_seq', $k);
			$query = $this->db->get('fm_manager');
			$data = $query->row_array();

			$param['provider_seq']	= 1;
			$param['manager_id']	= $data['manager_id'];

			//관리자 로그 남기기
			$this->load->library('managerlog');
			$this->managerlog->insertData(array('params' => array('mname' => $data['mname'])));

			$this->providermodel->delete_certify($param);
			unset($data);unset($param);

			$result = $this->db->delete('fm_manager', array('manager_seq' => $k));

			//접근권한제거
			$this->boardadmin->boardadmin_delete_manager($k);
		}

		$this->multishopmodel->run_auto_sync('manager_auth',array('fm_config'));


		echo $result;
	}


	public function iconUpload($del_file=null){

		//등급 아이콘
		if(preg_match("/^\/?data\/tmp/i", $_POST["mphoto"])){

			// 폴더가 없을 수도 있어 생성처리
			if(!is_dir(ROOTPATH.'data/icon/manager')){
				@mkdir(ROOTPATH.'data/icon/manager');
				@chmod(ROOTPATH.'data/icon/manager',0777);
			}

			// 파일 이름 재정의
			$ext			= explode(".",$_POST["mphoto"]);
			$ext			= $ext[count($ext)-1];
			$file_name		= "mphoto".".{$ext}";
			$new_path		= "/data/icon/manager/{$file_name}";

			// 파일 이동 처리
			copy(ROOTPATH.$_POST["mphoto"], ROOTPATH.$new_path);
			chmod(ROOTPATH.$new_path,0777);

			// 파일 이동 후 db 값에 갱신 하기 위해 재정의 한 파일명 넣음
			$config['file_name'] = $file_name;
		}

		return $config['file_name'];
	}

	public function goods(){

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_goodscd_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		config_save('goods',array('stock_history_use'=>$_POST['stock_history_use']));

		//상품코드추가작업
		$this->db->empty_table('fm_goods_code_form');
		foreach($_POST['labelItem'] as $typearr){
			$sort_user=0;
			foreach($typearr as  $codearr){
				$sort_user++;

				$codearr['codesetting'] = ($codearr['codesetting'] == 1)?1:0;
				$codearr['base_type'] = ($codearr['base_type'] == '1')?'1':'0';

				$codearr['newtypeuse'] = ($codearr['newtypeuse'] == 1 )?1:0;
				$codearr['newtype'] = ($codearr['newtype'])?$codearr['newtype']:'none';

				$data = array(
					'codeform_seq'=> $codearr['codeform_seq'],
					'base_type' => $codearr['base_type'],
					'codesetting' => $codearr['codesetting'],
					'label_type' => $codearr['type'],
					'label_title' => $codearr['name'],
					'label_value' => $codearr['value'],
					'label_default' => $codearr['default'],
					'label_code' => $codearr['code'],
					'label_color' => $codearr['color'],
					'label_zipcode' => $codearr['zipcode'],
					'label_address_type' => $codearr['address_type'],
					'label_address' => $codearr['address'],
					'label_address_street' => $codearr['address_street'],
					'label_addressdetail' => $codearr['addressdetail'],
					'label_biztel' => $codearr['biztel'],
					'label_address_commission' => $codearr['address_commission'],
					'label_newtypeuse' => $codearr['newtypeuse'],
					'label_newtype' => $codearr['newtype'],
					'label_date' => $codearr['date'],
					'label_sdayinput' => $codearr['sdayinput'],
					'label_fdayinput' => $codearr['fdayinput'],
					'label_dayauto_type' => $codearr['dayauto_type'],
					'label_sdayauto' => $codearr['sdayauto'],
					'label_fdayauto' => $codearr['fdayauto'],
					'label_dayauto_day' => $codearr['dayauto_day'],
					'sort_seq' => $sort_user,
					'regist_date' => date('Y-m-d H:i:s'),
				);
				$this->db->insert('fm_goods_code_form', $data);
			}
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	//동영상설정
	public function video() {
		if($_POST['video_use'] == 'Y') {//동영상사용시 ucc 설정값 필수입니다.
			$this->validation->set_rules('ucc_id','UCC 아이디','trim|required');
			$this->validation->set_rules('ucc_key','UCC 인증키','trim|required');
			$this->validation->set_rules('ucc_domain','UCC 도메인','trim|required');

			if($this->validation->exec()===false){
			   $err = $this->validation->error_array;
			   $callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			   openDialogAlert($err['value'],400,140,'parent',$callback);
			   exit;
			}
		}

		config_save('goods',array(
			'video_use'=>$_POST['video_use'],
			'ucc_id'=>$_POST['ucc_id'],
			'ucc_key'=>$_POST['ucc_key'],
			'ucc_domain'=>'web.mvod.'.str_replace("web.mvod.","",$_POST['ucc_domain'])
			));

		// 2016.09.06 리얼패킹 서비스 사용여부 저장 추가 pjw
		$use_service = $_POST['use_service'];
		config_save('realpacking', array('use_service'=>$use_service));

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	//상품코드 조합설정
	public function goodssetting(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_goodscd_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		//상품코드추가작업
		$this->db->empty_table('fm_goods_code_form');
		foreach($_POST['SettingItem'] as $typearr){
			$sort_user=0;
			foreach($typearr as  $codearr){
				$sort_user++;
				$codearr['codesetting'] = ($codearr['codesetting'] == 1)?1:0;
				$codearr['newtypeuse'] = ($codearr['newtypeuse'] == 1 )?1:0;
				$codearr['base_type'] = ($codearr['base_type'] == '1' )?'1':'0';
				$codearr['newtype'] = ($codearr['newtype'])?$codearr['newtype']:'none';
				$data = array(
					'codeform_seq'=> $codearr['codeform_seq'],
					'codesetting' => $codearr['codesetting'],
					'base_type' => $codearr['base_type'],
					'label_type' => $codearr['type'],
					'label_title' => $codearr['name'],
					'label_value' => $codearr['value'],
					'label_default' => $codearr['default'],
					'label_code' => $codearr['code'],
					'label_color' => $codearr['color'],
					'label_zipcode' => $codearr['zipcode'],
					'label_address_type' => $codearr['address_type'],
					'label_address' => $codearr['address'],
					'label_address_street' => $codearr['address_street'],
					'label_addressdetail' => $codearr['addressdetail'],
					'label_address_commission' => $codearr['address_commission'],
					'label_biztel' => $codearr['biztel'],
					'label_newtypeuse' => $codearr['newtypeuse'],
					'label_newtype' => $codearr['newtype'],
					'label_date' => $codearr['date'],
					'label_sdayinput' => $codearr['sdayinput'],
					'label_fdayinput' => $codearr['fdayinput'],
					'label_dayauto_type' => $codearr['dayauto_type'],
					'label_sdayauto' => $codearr['sdayauto'],
					'label_fdayauto' => $codearr['fdayauto'],
					'label_dayauto_day' => $codearr['dayauto_day'],
					'sort_seq' => $sort_user,
					'regist_date' => date('Y-m-d H:i:s'),
				);
				$this->db->insert('fm_goods_code_form', $data);
			}
		}
		$callback = "parent.document.location.reload();";
		openDialogAlert("상품코드 자동생성 규칙 세팅이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function banner_upload_file(){
		$error = array('status' => 0,'msg' => '업로드 실패하였습니다.','desc' => '업로드 실패');
		$folder = "data/tmp/";
		$arrDiv = config_load('goodsImageSize');
		$newFile = date('dHis');
		$idx = $_POST['idx'];
		$filename = $newFile.$div;
		$this->load->model('goodsmodel');
		$result = $this->goodsmodel->goods_temp_image_upload($filename,$folder);
		if(!$result['status']){
			echo "[".json_encode($error)."]";
			exit;
		}

		$source = $result['fileInfo']['full_path'];
		$target = $result['fileInfo']['full_path'];

		$result = array('status' => 1,'newFile' => "/".$folder.$newFile,'idx' => $idx,'ext' => $result['fileInfo']['file_ext']);
		echo "[".json_encode($result)."]";
	}


	function member_sale_write(){

		$service_code = $this->config_system['service']['code'];
		if(serviceLimit('H_EXAD')){
			$default_member_sale_cnt = 5;
		}else if(serviceLimit('H_PRST')){
			$default_member_sale_cnt = 3;
		}else{
			$default_member_sale_cnt = 1;
		}

		$this->config_system['service']['max_member_sale_cnt'] += $default_member_sale_cnt;

		$sale_seq = $_POST["sale_seq"];

		if($sale_seq == ""){
			$qry = "select count(*) as cnt from fm_member_group_sale";
			$query = $this->db->query($qry);
			$saleData = $query -> row_array();

			if($saleData['cnt'] >= $this->config_system['service']['max_member_sale_cnt']){
				$callback = "parent.member_sale_payment();";
				openDialogAlert("등급별 구매혜택 세트 설정 가능 개수를 초과하였습니다.",400,140,'parent',$callback);
				exit;
			}
		}

		$this->load->model('membermodel');
		$list = $this->membermodel->member_sale_group_list();


		$this->validation->set_rules('sale_title', '할인율 이름','trim|required|max_length[100]|xss_clean');

		foreach($list as $validationdata){
			if($_POST["sale_use"][$validationdata["group_seq"]] == "Y"){
				if($_POST["sale_limit_price"][$validationdata["group_seq"]] == '0') $_POST["sale_limit_price"][$validationdata["group_seq"]] = '';
				$this->validation->set_rules('sale_limit_price['.$validationdata["group_seq"].']', '구매금액 조건','trim|required|max_length[100]|xss_clean');
			}
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		foreach($list as $validationdata){
			$this->validation->set_rules('sale_use['.$validationdata["group_seq"].']', '추가할인 조건','trim|required|max_length[100]|xss_clean');

			//할인 미입력시 0으로 셋팅
			if($_POST["sale_use"][$validationdata["group_seq"]] == "") $_POST["sale_use"][$validationdata["group_seq"]] = "0";
			$this->validation->set_rules('sale_price['.$validationdata["group_seq"].']', '추가할인 할인','trim|required|max_length[100]|xss_clean');

			//추가옵션 미입력시 0으로 셋팅
			if($_POST["sale_option_price"][$validationdata["group_seq"]] == "") $_POST["sale_option_price"][$validationdata["group_seq"]] = "0";
			$this->validation->set_rules('sale_option_price['.$validationdata["group_seq"].']', '추가할인 추가옵션','trim|required|max_length[100]|xss_clean');

			$this->validation->set_rules('point_use['.$validationdata["group_seq"].']', '추가적립 조건','trim|required|max_length[100]|xss_clean');

			//마일리지 미입력시 0으로 셋팅
			if($_POST["point_price"][$validationdata["group_seq"]] == "") $_POST["point_price"][$validationdata["group_seq"]] = "0";
			$this->validation->set_rules('point_price['.$validationdata["group_seq"].']', '추가적립 마일리지','trim|required|max_length[100]|xss_clean');

			//포인트 미입력시 0으로 셋팅
			if($_POST["reserve_price"][$validationdata["group_seq"]] == "") $_POST["reserve_price"][$validationdata["group_seq"]] = "0";
			$this->validation->set_rules('reserve_price['.$validationdata["group_seq"].']', '추가적립 포인트','trim|required|max_length[100]|xss_clean');

		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if($sale_seq == ""){
			$insert_params['sale_title'] = $_POST["sale_title"];
			$insert_params['regist_date'] = date("Y-m-d H:i:s");
			$insert_params['update_date'] = date("Y-m-d H:i:s");
			$insert_params['defualt_yn'] = $_POST['defualt_yn'] ? 'y' : 'n';

			$result = $this->db->insert('fm_member_group_sale', $insert_params);
			$sale_seq = $this->db->insert_id();

			$mode = "insert";
		}else{

			$insert_params['sale_title'] = $_POST["sale_title"];
			$insert_params['update_date'] = date("Y-m-d H:i:s");
			$insert_params['defualt_yn'] = $_POST['defualt_yn'] ? 'y' : 'n';

			$this->db->where('sale_seq', $sale_seq);
			$result = $this->db->update('fm_member_group_sale', $insert_params);
			$result = $this->db->delete('fm_member_group_sale_detail', array('sale_seq' => $sale_seq));
			$mode = "modify";

		}

		if($_POST['defualt_yn'] == "y"){
			$sql = "update fm_member_group_sale set defualt_yn = 'n' where sale_seq <> '".$sale_seq."'";
			$result = $this->db->query($sql);
		}


		foreach($list as $group){

			$group_seq						= $group["group_seq"];

			$data["sale_seq"]				= $sale_seq;
			$data["group_seq"]				= $group_seq;
			$data["sale_use"]				= $_POST["sale_use"][$group_seq];
			$data["sale_limit_price"]		= get_cutting_price($_POST["sale_limit_price"][$group_seq]);
			$data["sale_price"]				= get_cutting_price($_POST["sale_price"][$group_seq]);
			$data["sale_price_type"]		= $_POST["sale_price_type"][$group_seq];
			$data["sale_option_price"] 		= get_cutting_price($_POST["sale_option_price"][$group_seq]);
			$data["sale_option_price_type"]	= $_POST["sale_option_price_type"][$group_seq];
			$data["point_use"]				= $_POST["point_use"][$group_seq];
			$data["point_limit_price"]		= get_cutting_price($_POST["point_limit_price"][$group_seq]);
			$data["point_price"]			= get_cutting_price($_POST["point_price"][$group_seq]);
			$data["point_price_type"]		= $_POST["point_price_type"][$group_seq];
			$data["reserve_price"]			= get_cutting_price($_POST["reserve_price"][$group_seq]);
			$data["reserve_price_type"]		= $_POST["reserve_price_type"][$group_seq];
			$data["reserve_select"]			= $_POST["reserve_select"][$group_seq];
			$data["reserve_year"]			= $_POST["reserve_year"][$group_seq];
			$data["reserve_direct"]			= $_POST["reserve_direct"][$group_seq];
			$data["point_select"]			= $_POST["point_select"][$group_seq];
			$data["point_year"]				= $_POST["point_year"][$group_seq];
			$data["point_direct"]			= $_POST["point_direct"][$group_seq];

			$result = $this->db->insert('fm_member_group_sale_detail', $data);


		}

		$result = $this->db->delete('fm_member_group_issuegoods', array('sale_seq' => $sale_seq));
		$result = $this->db->delete('fm_member_group_issuecategory', array('sale_seq' => $sale_seq));

		### SALE
		$group_seq = (int) $group_seq;
		for($i=0;$i<count($_POST['issueGoods']);$i++){
			if($_POST['issueGoods'][$i])
			$result = $this->db->insert('fm_member_group_issuegoods', array('group_seq'=>$group_seq, 'sale_seq'=>$sale_seq,'goods_seq'=>$_POST['issueGoods'][$i],'type'=>'sale'));
		}
		for($i=0;$i<count($_POST['issueCategoryCode']);$i++){
			$result = $this->db->insert('fm_member_group_issuecategory', array('group_seq'=>$group_seq,'sale_seq'=>$sale_seq,'category_code'=>$_POST['issueCategoryCode'][$i],'type'=>'sale'));
		}

		### EMONEY
		for($i=0;$i<count($_POST['exceptIssueGoods']);$i++){
			if($_POST['exceptIssueGoods'][$i])
			$result = $this->db->insert('fm_member_group_issuegoods', array('group_seq'=>$group_seq,'sale_seq'=>$sale_seq,'goods_seq'=>$_POST['exceptIssueGoods'][$i],'type'=>'emoney'));
		}
		for($i=0;$i<count($_POST['exceptIssueCategoryCode']);$i++){
			$result = $this->db->insert('fm_member_group_issuecategory', array('group_seq'=>$group_seq,'sale_seq'=>$sale_seq,'category_code'=>$_POST['exceptIssueCategoryCode'][$i],'type'=>'emoney'));
		}

		$callback = "parent.location.replace('/admin/setting/member?gb=member_sale');";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	function member_sale_delete(){

		$sale_seq = $_GET['sale_seq'];
		$change_seq = $_GET['change_seq'];

		$result = $this->db->delete('fm_member_group_sale_detail', array('sale_seq' => $sale_seq));
		$result = $this->db->delete('fm_member_group_sale', array('sale_seq' => $sale_seq));

		if($result){
			$sql = "update fm_goods set sale_seq = '".$change_seq."' where sale_seq = '".$sale_seq."'";
			$result = $this->db->query($sql);
		}

		if($result){
			$callback = "parent.location.replace('/admin/setting/member?gb=member_sale');";
			openDialogAlert("삭제되었습니다.",400,140,'parent',$callback);
		}else{
			$callback = "parent.location.replace('/admin/setting/member?gb=member_sale');";
			openDialogAlert("삭제 처리중 에러가 발생하였습니다.",400,140,'parent',$callback);
		}
	}

	function search(){

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_address_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		$this->load->model('searchwordmodel');

		$this->searchwordmodel->truncate_word();

		foreach($_POST['page'] as $page_key => $page ){
			foreach($_POST['keyword'][$page_key] as $keyword_key => $keyword){
				if( $keyword ){
					$this->searchwordmodel->insert_word($_POST['page_yn'][$page_key],
						$page_key,
						$keyword,
						$_POST['search_result'][$page_key][$keyword_key],
						$_POST['search_result_link'][$page_key][$keyword_key],
						$_POST['search_result_target'][$page_key][$keyword_key]
					);
				}
			}
		}

		if($this->config_system['operation_type'] != 'light'){
			config_save('search',array(
				'auto_search'=>$_POST['auto_search'],
				'auto_search_limit_day'=>$_POST['auto_search_limit_day'],
				'auto_search_recomm_limit_day'=>$_POST['auto_search_recomm_limit_day'],
				'popular_search'=>$_POST['popular_search'],
				'popular_search_limit_day'=>$_POST['popular_search_limit_day'],
				'popular_search_recomm_limit_day'=>$_POST['popular_search_recomm_limit_day']
			));
			// 도로명 주소 설정 저장
			$street_ = false;
			$arr_param_street = array('street_zipcode_5','street_zipcode_6','old_zipcode_lot_number');
			foreach( $arr_param_street as $param_street) if( $_POST[$param_street] ) $street_ = true;
			if( $street_ ){
				foreach( $arr_param_street as $param_street) $arr_cfg_zipcode[$param_street] = $_POST[$param_street];
				config_save('zipcode',$arr_cfg_zipcode);
			}else{
				openDialogAlert("주소 검색창 설정 중 하나는 반드시 사용해야합니다.",400,140,'parent','');
				exit;
			}
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);

	}

	## 우체국택배 중복값 체크
	function epost_duple_chk(){
		$chk_type	= $_POST['chk_type'];
		$chk_val	= $_POST['chk_val'];

		if($chk_type && $chk_val){
			$this->load->model('epostmodel');
			$duple_res = $this->epostmodel->get_epost_duple($chk_type,$chk_val);

			if		($duple_res == 'ok')	$result['result'] = true;
			else if	($duple_res == 'err'){	$result['result'] = false;
				$result['msg'] = '일시적인 오류입니다.<br/>새로고침 후 시도해 주세요.';
			}else{							$result['result'] = false;
				$result['msg'] = '현재 입력하신 값은 중복된 값입니다.<br/>다시 확인해주세요.';
			}
		}else{
			$result['result']	= false;
			$result['msg']		= '일시적인 오류입니다.<br/>새로고침 후 시도해 주세요.';
		}

		echo json_encode($result);
	}

	## 우체국택배 업무자동화서비스 세팅 :: 2016-03-30 lwh
	function epost_setting(){
		$this->load->model('epostmodel');

		$param = $_POST;

		if($_POST['personinfo'] != 'Y'){
			openDialogAlert('개인정보 수집이용에 동의해주세요.',400,155,'parent',$callback);
			exit;
		}

		if($_POST['duple_chk1'] != 'Y' || $_POST['duple_chk2'] != 'Y'){
			openDialogAlert('중복체크는 필수 입니다.',400,155,'parent',$callback);
			exit;
		}

		$this->validation->set_rules('epost_id', '우체국아이디','required|trim|xss_clean|min_length[5]');
		$this->validation->set_rules('epost_pw', '우체국비밀번호','required|trim|xss_clean|min_length[9]');
		$this->validation->set_rules('epost_num', '우체국고객번호','required|trim|numeric|xss_clean|min_length[10]');
		$this->validation->set_rules('epost_auth_code[]', '우체국승인번호','required|trim|numeric|xss_clean');
		$this->validation->set_rules('label_printer', '라벨프린터','required|trim|xss_clean');
		$this->validation->set_rules('epost_central_name', '집하국명','required|trim|xss_clean');
		$this->validation->set_rules('epost_manager_name', '담당자','required|trim|xss_clean');
		$this->validation->set_rules('epost_manager_cellphone', '담당자 휴대폰','required|trim|xss_clean');

		$this->validation->set_rules('biz_name', '상호명','required|trim|xss_clean');
		$this->validation->set_rules('biz_ceo', '대표자명','required|trim|xss_clean');
		$this->validation->set_rules('biz_zipcode', '사업장우편번호','required|trim|xss_clean');
		$this->validation->set_rules('biz_address', '사업장주소','required|trim|xss_clean');
		$this->validation->set_rules('biz_phone', '전화번호','required|trim|xss_clean');
		$this->validation->set_rules('biz_email', '이메일','required|trim|xss_clean');


		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= $_POST['provider_seq'];
			if(!$provider_seq)
				$provider_seq = 1;
		}
		$param['provider_seq'] = $provider_seq; // 입점사 번호는 필수이다.
		$result = $this->epostmodel->set_epost_setting($param);

		if($result){	// 성공적으로 저장
			$callback = "parent.document.location.reload();";
			openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
			exit;
		}else{
			$callback = "parent.document.location.reload();";
			openDialogAlert("연동에 실패하였습니다.\\n계속 같은 문제가 발생하면 고객센터로 연락주세요.",400,140,'parent',$callback);
			exit;
		}
	}

	## 우체국택배 업무자동화서비스 사용여부 세팅 :: 2016-03-30 lwh
	function epost_use_save(){
		$this->load->model('epostmodel');
		$res = $this->epostmodel->set_epost_use($_POST['requestkey'],$_POST['epost_notuse']);
		$return['res'] = 'success';
		$return['msg'] = '저장되었습니다.';

		echo json_encode($return);
	}

	## 우체국택배 서비스 취소
	function epost_cancel(){
		$this->load->model('epostmodel');

		$requestkey	= $_POST['requestkey'];
		$status		= $_POST['status'];

		if($_POST['requestkey']){
			if($this->epostmodel->set_epost_cancel($requestkey,$status)){
				$result['result']	= true;
				$result['msg']		= "취소되었습니다.";
			}else{
				$result['result']	= false;
				$result['msg']		= "연동에 실패했습니다. 새로고침 후 시도하여주세요.<br/>해당문제가 계속 발생하면 고객센터로 연락주세요.";
			}
		}else{
			$result['result']	= false;
			$result['msg']		= "잘못된 경로입니다 (고유키 오류)";
		}

		echo json_encode($result);
	}

	## 굿스플로 업무자동화 이용유무 설정 :: 2015-07-17 lwh
	function goodsflow_use_save(){
		$goodsflow_use = $this->input->post('goodsflow_use');
		$provider_json = $this->input->post('provider_json');
		if(!empty($provider_json)) {
			$provider_array = json_decode($provider_json, true);
			if(count($provider_array)>0) {
				$this->db->trans_start();
				$this->load->model('goodsflowmodel');

				// 수정할 전체 입점사 / 업데이트할 입점사 / 신규 등록할 입점사
				$provider_data = array('total'=>array(), 'update'=>array(), 'insert'=>array());

				// 전체 입점사 데이터
				foreach($provider_array as $useType => $providers) {
					if(count($providers) > 0) {
						foreach($providers as $provider_row) {
							$provider_data['total'][$provider_row['seq']] = array(
							  'provider_seq' => $provider_row['seq'],
							  'gf_use' => ($useType === 'use' ? 'Y' : 'N'),
							);
						}
					}
				}

				// 업데이트할 입점사 번호를 가져온다,
				$provider_seqs = array_keys($provider_data['total']);
				$registeredSeqs = array();
				if(count($provider_seqs)>0) {
					$registeredSeqsTmp =  $this->goodsflowmodel->get_goodsflow_setting_list($provider_seqs, 'provider_seq');
					if(count($registeredSeqsTmp)>0) {
						foreach($registeredSeqsTmp as $tmpRow) {
							$registeredSeqs[] = $tmpRow['provider_seq'];
						}
					}
				}

				// 업데이트/신규 등록 분리
				if(count($provider_data['total'])>0) {
					foreach($provider_data['total'] as $provider_seq => $provider_row) {
						// 업데이트할 입점사
						if(in_array($provider_seq, $registeredSeqs)) {
							$provider_data['update'][] = $provider_row;
						} else { // 신규 등록할 입점사
							$provider_data['insert'][] = $provider_row;
						}
					}
					// 업데이트 쿼리 실행
					if(count($provider_data['update']) > 0) {
						$this->db->update_batch('fm_goodsflow', $provider_data['update'], 'provider_seq', true);
					}
					// 등록 쿼리 실행
					if(count($provider_data['insert'])>0) {
						$this->db->insert_batch('fm_goodsflow', $provider_data['insert']);
					}
				}
				$this->db->trans_complete();
			}
		}

		config_save('system',array('goodsflow_use'=>$goodsflow_use));

		$return['res'] = 'success';
		$return['msg'] = '설정이 저장되었습니다.';

		echo json_encode($return);
	}

	## 굿스플로 업무자동화 입점사 설정 :: 2015-07-17 lwh
	function goodsflow_provider_set(){
		$return['res'] = 'N';
		if($_POST['gf_use'] && $_POST['provider_seq']){
			$data = $_POST;
			$this->load->model('goodsflowmodel');
			$this->goodsflowmodel->set_goodsflow_setting($data);
			$return['res'] = 'Y';
		}

		echo json_encode($return);
	}

	/**
	 * 입점사 굿스플로 연동에서 사용하는 입점사 정보를 반환한다.
	 *
	 * @param string $provider_name : 입점사명
	 */
	public function goodsflow_provider($provider_name = null)
	{
		if($this->config_system['goodsflow_use'] != '1') {
			$this->output->set_content_type('application/json')->set_output(json_encode(array('success'=>false, 'code'=>-1, 'message'=>'굿스플로 미사용중입니다.')));
		}

		$data = array(
			'use' => array(),
			'notUse' => array(),
		);

		// 입점사 목록을 가져온다.
		$this->load->model('providermodel');
		$search_cond = array();
		if(!empty($provider_name)) {
			$search_cond['search_text'] = urldecode($provider_name);
			$encoding = mb_detect_encoding($search_cond['search_text'], array("EUC-KR", "UTF-8"));
			if($encoding !== 'UTF-8') {
				$search_cond['search_text'] = iconv($encoding, 'UTF-8', $search_cond['search_text']);
			}
		}
		$search_cond['orderby'] = 'A.provider_seq';
		$search_cond['page'] = 0;
		$search_cond['perpage'] = $this->db->count_all('fm_provider');
		$provider_list_tmp = $this->providermodel->provider_list($search_cond);

		if(count($provider_list_tmp['result'])>0) {
			// 입점사 중 굿스플로 사용 중인 입점사를 뽑는다.
			foreach($provider_list_tmp['result'] as $provider_row) {
				if($provider_row['provider_seq'] == '1') {
					continue;
				}
				$provider_list[$provider_row['provider_seq']] = array(
					'seq' => $provider_row['provider_seq'],
					'name' => $provider_row['provider_name'],
					'id' => $provider_row['provider_id'],
				);
			}
			$provider_seqs = array_keys($provider_list);

			$this->load->model('goodsflowmodel');
			$goodsflow_settings = $this->goodsflowmodel->get_goodsflow_setting_list($provider_seqs);
			$goodsflow_provider_seqs = array();
			if(count($goodsflow_settings) >0) {
				foreach($goodsflow_settings as $goodsflow_row) {
					if($goodsflow_row['gf_use'] == 'Y') {
						$goodsflow_provider_seqs[] = $goodsflow_row['provider_seq'];
					}
				}
			}

			foreach($provider_list as $provider_seq => $provider_row) {
				if(in_array($provider_seq, $goodsflow_provider_seqs) === true) {
					$data['use'][] =  $provider_list[$provider_seq];
				} else {
					$data['notUse'][] =  $provider_list[$provider_seq];
				}
			}
		}

		$this->output->set_content_type('application/json')->set_output(json_encode(array('success'=>true, 'data'=>$data)));
	}

	## 굿스플로 업무자동화서비스 세팅 :: 2015-06-23 lwh
	function goodsflow_setting(){
		$this->load->model('goodsflowmodel');
		// XML예약 특수 문자 치환 @2016-12-29
		$_POST['mallName']	= str_replace('&','&amp;',$_POST['mallName']);
		$_POST['mallName']	= str_replace("‘",'&apos;',$_POST['mallName']);
		$_POST['mallName']	= str_replace('<','&lt;',$_POST['mallName']);
		$_POST['mallName']	= str_replace('>','&gt;',$_POST['mallName']);

		$_POST['centerName']	= str_replace('&','&amp;',$_POST['centerName']);
		$_POST['centerName']	= str_replace("‘",'&apos;',$_POST['centerName']);
		$_POST['centerName']	= str_replace('<','&lt;',$_POST['centerName']);
		$_POST['centerName']	= str_replace('>','&gt;',$_POST['centerName']);
		$param = $_POST;
		unset($param['gf_mode']);
		$param['provider_seq'] = 1;

		foreach($_POST['boxSize'] as $k => $val){
			if($box_arr[$val]){
				openDialogAlert("박스타입은 중복될수 없습니다.",400,140,'parent','');
				exit;
			}
			$box_arr[$val] = $val;
		}

		if(!$_POST['mallId']){
			openDialogAlert("자동설정되는 아이디가 지정되어지지 않았습니다.\n새로고침 후 계속 같은 문제가 발생하면 고객센터로 연락주세요.",400,140,'parent');
			exit;
		}
		$this->validation->set_rules('mallName', '쇼핑몰명','required|trim|xss_clean');
		$this->validation->set_rules('centerName', '발송지명','required|trim|xss_clean');
		$this->validation->set_rules('goodsflowZipcode[]', '발송지주소','required|trim|xss_clean');
		$this->validation->set_rules('centerTel1', '발송지전화1','required|trim|numeric|xss_clean');
		$this->validation->set_rules('centerTel2', '발송지전화2','trim|numeric|xss_clean');
		if($_POST['gf_mode'] != 'modify'){
			$this->validation->set_rules('bizNo', '사업자 번호','required|trim|numeric|xss_clean');
			$this->validation->set_rules('deliveryCode', '택배사','required|trim|xss_clean');
			$this->validation->set_rules('contractNo', '택배사 계약코드','required|trim|xss_clean');
			if($_POST['deliveryCode']=='EPOST'){
				$this->validation->set_rules('contractCustNo', '택배사가 우체국인경우 `택배사 업체코드`','required|trim|xss_clean');
			}
		}

		$this->validation->set_rules('boxSize[]', '박스타입','required|trim|xss_clean');
		$this->validation->set_rules('shFare[0]', '선불배송','required|numeric|trim|xss_clean');
		$this->validation->set_rules('scFare[0]', '신용배송','required|numeric|trim|xss_clean');
		$this->validation->set_rules('bhFare[0]', '착불배송','required|numeric|trim|xss_clean');
		$this->validation->set_rules('rtFare[0]', '반품배송','required|numeric|trim|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		// 최종 신청 전 설정값 재 검사
		$config_goodsflow = $this->goodsflowmodel->get_goodsflow_setting($param['provider_seq']);
		$goodsflow_step		= $config_goodsflow['goodsflow_step'];

		// 정보 수정시
		if($_POST['gf_mode'] == 'modify'){
			$apiParam['requestKey'] = $config_goodsflow['requestKey'];
			$result	= $this->goodsflowmodel->apiSender('updateService',$apiParam);
			if($result['verified']){
				$this->goodsflowmodel->set_goodsflow_setting($param);
				openDialogAlert('수정되었습니다.',400,140,'parent','top.location.reload();');
				exit;
			}else{
				$step_param['goodsflow_msg'] = '이용중';
				$step_param['goodsflow_err'] = $result['msg'];
				$this->goodsflowmodel->set_goodsflow_step($param['provider_seq'],$step_param);

				$result['msg'] = str_replace("'","\'",$result['msg']);
				$retuen_msg	= '아래 사유로 인해 수정이 거절되었습니다.<br/>' . $result['msg'];
				openDialogAlert($retuen_msg,400,140,'parent',$callback);
				exit;
			}
		}else{
		// 신규 신청시
			if($goodsflow_step == '1' || $goodsflow_step == '2'){
				$callback = "parent.document.location.reload();";
				openDialogAlert('서비스 신청이 불가능한 상태입니다.<br/>새로고침 후 다시 시도해주세요.',400,140,'parent',$callback);
				exit;
			}

			$result = $this->goodsflowmodel->apiSender('requestService',$param);
		}

		/* ##### goodsflow_step 에 따른 상태
			1 -> 이용중 - 정상이용
			2 -> 이용중지 - 연동신청중 (신청이력이 있는경우 자동)
			3 -> 이용중지 - 연동불가 (연동이 되지 않았을경우-굿스플로사유)
			4 -> 이용중지 - 소진(충전필요)
		##### */

		config_save("system",array('goodsflow_use'=>1));

		if($result['verified']){	// 성공적으로 저장
			if($result['verified'] == 'Y'){
				// 충전 건수 확인 충전 X 면 4로 셋팅
				$param['goodsflow_step']	= '1';
				$param['goodsflow_msg']		= '이용중';
			}else{
				$param['goodsflow_step']	= '2';
				$param['goodsflow_msg']		= '이용 중지- 연동 신청중';
			}

			$param['requestKey']		= $result['requestKey'];
			$this->goodsflowmodel->set_goodsflow_setting($param);
		}else{
			$param['goodsflow_step']	= '3';
			$param['goodsflow_msg']		= '이용 중지- 연동 불가 ( 사유 : '.$result['msg'].') ';

			// 굿스플로 API 이상으로 인한 임시처리 - 추후 삭제
			if($result['msg'] == '계약확인 중인 서비스 신청 정보가 있습니다.'){
				$param['goodsflow_step']= '2';
				$param['goodsflow_msg']	= '이용 중지- 연동 신청중';
				//$param['requestKey']			= $result['requestKey'];
			}

			$this->goodsflowmodel->set_goodsflow_setting($param);
			$callback = "parent.document.location.reload();";

			$result['msg'] = str_replace("'","\'",$result['msg']);
			openDialogAlert($result['msg'],400,200,'parent','');
			exit;
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
		exit;
	}

	## 굿스플로 서비스 취소
	function goodsflow_cancel(){
		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= $_POST['provider_seq'];
		}
		$this->load->model('goodsflowmodel');
		if($provider_seq){
			$apiParam['requestKey'] = $_POST['requestKey'];
			$result = $this->goodsflowmodel->apiSender('cancelService',$apiParam);
			if($result['result']){
				$this->goodsflowmodel->del_goodsflow_setting($provider_seq);
			}
		}else{
			$result['result']	= false;
			$result['msg']		= "잘못된 경로입니다 (로그인정보 오류)";
		}

		echo json_encode($result);
	}

	## 택배업무자동화서비스 세팅
	function invoice_setting(){

		$this->load->model('invoiceapimodel');
		$this->load->model('shippingmodel');

		// 본사 배송정보 가져오기
		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= $_POST['provider_seq'];
			if(!$provider_seq)
				$provider_seq = 1;
		}

		if($_POST['invoice_notuse']){
			// 롯데택배 설정 삭제
			$this->invoiceapimodel->del_invoice_setting($provider_seq,'hlc');
		}else{
			$data_providershipping = $this->shippingmodel->get_shipping_base($provider_seq);

			if(!$data_providershipping['refund_address_seq']){
				openDialogAlert("기본배송그룹의 반송지 주소를 먼저 세팅해주세요.",400,140,'parent');
				exit;
			}

			$this->validation->set_rules('branch_name', '계약 대리점명','required|trim|max_length[20]|xss_clean');
			$this->validation->set_rules('auth_code[]', '신용코드','required|trim|max_length[6]|numeric|xss_clean');

			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,140,'parent',$callback);
				exit;
			}

			$arr_invoice_vendor = array();
			foreach($_POST['auth_code'] as $invoice_vendor=>$auto_code){
				if($auto_code){
					$arr_invoice_vendor[] = $invoice_vendor;
				}
			}

			if(!$arr_invoice_vendor){
				openDialogAlert("신용코드 인증이 필요합니다.",400,140,'parent');
				exit;
			}

			foreach($_POST['auth_code'] as $invoice_vendor=>$auto_code){

				$params = array();
				$params['invoice_use']	= $auto_code ? 1:0;
				$params['auth_code']	= $auto_code;

				if($invoice_vendor=='hlc'){
					$params['branch_name']	= $_POST['branch_name'];
					$params['print_type']	= $_POST['print_type'];
				}
				$params['invoice_use_date']	=  date('Y-m-d H:i:s');

				$this->invoiceapimodel->set_invoice_setting($provider_seq,$invoice_vendor,$params);
			}
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	## 롯데택배 신용코드 인증
	function hlc_auth(){
		$this->load->model('invoiceapimodel');
		$result = $this->invoiceapimodel->hlc_auth($_POST['auth_code']);
		echo json_encode($result);
	}

	function modify_shipping_address(){
		$this->validation->set_rules('senderEmail', '이메일','trim|max_length[50]|valid_email|xss_clean');
		if($_POST['senderZipcode'][0] || $_POST['senderZipcode'][1]){
		$this->validation->set_rules('senderZipcode[]', '우편번호','trim|max_length[7]|numeric|xss_clean');
		}
		$this->validation->set_rules('senderAddress', '주소','trim|max_length[100]|xss_clean');
		$this->validation->set_rules('senderAddressDetail', '상세 주소','trim|max_length[100]|xss_clean');
		$this->validation->set_rules('returnEmail', '이메일','trim|max_length[50]|valid_email|xss_clean');
		if($_POST['returnZipcode'][0] || $_POST['returnZipcode'][1]){
		$this->validation->set_rules('returnZipcode[]', '우편번호','trim|max_length[7]|numeric|xss_clean');
		}
		$this->validation->set_rules('returnAddress', '주소','trim|max_length[100]|xss_clean');
		$this->validation->set_rules('returnAddressDetail', '상세 주소','trim|max_length[100]|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		config_save('shipping',array('senderZipcode'=>$_POST['senderZipcode']));
		config_save('shipping',array('senderAddress'=>$_POST['senderAddress']));
		config_save('shipping',array('senderAddressDetail'=>$_POST['senderAddressDetail']));

		config_save('shipping',array('returnZipcode'=>$_POST['returnZipcode']));
		config_save('shipping',array('returnAddress'=>$_POST['returnAddress']));
		config_save('shipping',array('returnAddressDetail'=>$_POST['returnAddressDetail']));

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}


	//상품코드 > 특수옵션의 자동기간 오늘일자로 미리보기
	public function goods_dayauto_setting() {
		$this->load->helper('goods');
		$deposit_date		= ($_GET['deposit_date'])?$_GET['deposit_date']:date("Y-m-d");
		$sdayauto			= ($_GET['sdayauto'])?$_GET['sdayauto']:0;
		$fdayauto			= ($_GET['fdayauto'])?$_GET['fdayauto']:0;
		$dayauto_type	= ($_GET['dayauto_type'])?$_GET['dayauto_type']:0;
		$dayauto_day		= ($_GET['dayauto_day'])?$_GET['dayauto_day']:0;
		$resulthtml = goods_dayauto_setting_day( $deposit_date, $sdayauto, $fdayauto, $dayauto_type, $dayauto_day );
		echo json_encode($resulthtml);
	}

	// 상품등록 에디터 세팅
	function setting_editor(){
		config_save('goods_contents_editor',array('type'=>$_POST['editor_type']));
		$callback = "parent.closeDialog('setting_editor_popup');";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	// 워터마크세팅
	public function watermark_setting()
	{
		$this->load->model('watermarkmodel');
		$this->watermarkmodel->watermark_setting();
	}

	public function auto_logout()
	{
		config_save('autoLogout',array('auto_logout'=>$_POST['auto_logout']));
		config_save('autoLogout',array('until_time'=>$_POST['until_time']));

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function chatbot_setting()
	{
		config_save('chatbot', array('chatbot_use' => $_POST['chatbot_use']));

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.", 400, 140, 'parent', $callback);
	}

	public function print_setting(){

		if	(!$_POST['orderPrintAddInfo']){
			$_POST['orderPrintPackage']			= '';
			$_POST['orderPrintSubRelation']		= '';
			$_POST['orderPrintWarehouse']		= '';
			$_POST['orderPrintGoodsCode']		= '';
		}
		if	(!$_POST['exportPrintAddInfo']){
			$_POST['exportPrintPackage']		= '';
			$_POST['exportPrintSubRelation']	= '';
			$_POST['exportPrintWarehouse']		= '';
			$_POST['exportPrintGoodsCode']		= '';
		}

		$provider_seq	= 1;
		$query			= $this->db->query("select * from fm_setting_print where provider_seq=?",$provider_seq);
		$result			= $query->row_array();

		if($_POST['shopLogoType'] == "img" && $_FILES['shopLogoImg']['tmp_name']){
			$file_ext					= end(explode('.', $_FILES['shopLogoImg']['name']));
			$file_name					= 'shoplogo'.time().rand(10,99).'.'.$file_ext;
			$file_name					= str_replace("\'", "", $file_name); 	// ' " 제거
			$file_name					= str_replace("\"", "", $file_name); 	// ' " 제거
			$tmp						= getimagesize($_FILES['shopLogoImg']['tmp_name']);
			$_FILES['Filedata']['type'] = $tmp['mime'];
			$config['upload_path']		= './data/icon/favicon';
			$config['allowed_types']	= 'jpeg|jpg|png|gif';
			$config['max_size']			= $this->config_system['uploadLimit'];
			$config['file_name']		= $file_name;
			$this->load->library('Upload', $config);
			if (  $this->upload->do_upload('shopLogoImg'))
			{
				if( $result['shop_logo_img']) unlink($result['shop_logo_img']);
				$shopLogoImg = substr($config['upload_path']."/".$file_name,1);
			}
		}

		$data_params = array(
							"provider_seq"				=> $provider_seq,
							"order_barcode"				=> $_POST['orderPrintOrderBarcode'],
							"order_addinfo"				=> $_POST['orderPrintAddInfo'],
							"order_package"				=> $_POST['orderPrintPackage'],
							"order_sub_relation"		=> $_POST['orderPrintSubRelation'],
							"order_warehouse"			=> $_POST['orderPrintWarehouse'],
							"order_goods_code"			=> $_POST['orderPrintGoodsCode'],
							"order_goods_barcode"		=> $_POST['orderPrintGoodsBarcode'],
							"order_goods_image"			=> $_POST['orderPrintGoodsImage'],
							"order_centerinfo"			=> $_POST['orderPrintCenterInfo'],
							"order_centerinfo_message"	=> $_POST['orderPrintCenterInfoInput'],
							"export_code_barcode"		=> $_POST['exportPrintExportcodeBarcode'],
							"export_addinfo"			=> $_POST['exportPrintAddInfo'],
							"export_package"			=> $_POST['exportPrintPackage'],
							"export_sub_relation"		=> $_POST['exportPrintSubRelation'],
							"export_warehouse"			=> $_POST['exportPrintWarehouse'],
							"export_goods_code"			=> $_POST['exportPrintGoodsCode'],
							"export_goods_barcode"		=> $_POST['exportPrintGoodsBarcode'],
							"export_goods_image"		=> $_POST['exportPrintGoodsImage'],
							"export_centerinfo"			=> $_POST['exportPrintCenterInfo'],
							"export_centerinfo_message"	=> $_POST['exportPrintCenterInfoInput'],
							"shop_logo_type"			=> $_POST['shopLogoType'],
							"shop_logo_text"			=> $_POST['shopLogoText'],
							"shop_logo_img"				=> $shopLogoImg,
							"update_date"				=> date("Y-m-d H:i:s")
						);


		if($result){
			unset($data_params['provider_seq']);
			$this->db->where("provider_seq",$provider_seq);
			$this->db->update("fm_setting_print",$data_params);
		}else{
			$this->db->insert("fm_setting_print",$data_params);
		}

		$callback = "parent.location.reload();";
		openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
	}

	public function export_default_search()
	{
		$this->load->model('searchdefaultconfigmodel');

		$param_order = array(
				'search_page' => 'admin/order/order_export_popup',
				'order_default_date_field' => $_POST['order_default_date_field'],
				'order_default_period'		=> $_POST['order_default_period'],
				'order_default_step'		=> $_POST['order_default_step'],
				'order_detail_view'			=> $_POST['order_detail_view']
		);
		$this->searchdefaultconfigmodel->set_search_default($param_order);

		$param_export = array(
				'search_page' => 'admin/export/batch_status',
				'export_default_date_field' => $_POST['export_default_date_field'],
				'export_default_period'		=> $_POST['export_default_period'],
				'export_default_status'		=> $_POST['export_default_status'],
				'export_detail_view'			=> $_POST['export_detail_view']
		);
		$this->searchdefaultconfigmodel->set_search_default($param_export);

		$callback = "parent.closeDialog('search_detail_dialog');";
		openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
	}

	function change_adddelivery_type(){

		#### AUTH
		if($this->managerInfo['manager_yn']!='Y'){
			echo 'fail';
			die();
		}

		$params['addDeliveryType'] = "street";
		config_save('adddelivery',$params);

		$sql = "select manager_log from fm_manager where manager_seq = '".$this->managerInfo['manager_seq']."'";
		$query = $this->db->query($sql);
		$manager = $query->row_array();

		$data['manager_log'] = "<div>".date("Y-m-d H:i:s")." 관리자(".$this->managerInfo['manager_id'].")가 지번 주소에서 도로명 주소로 추가 배송비 기준을 변경하였습니다. (".$_SERVER['REMOTE_ADDR'].")</div>".$manager['manager_log'];

		$this->db->where('manager_seq', $this->managerInfo['manager_seq']);
		$result = $this->db->update('fm_manager', $data);
		echo 'ok';
	}


	public function seo(){
		$this->load->library('upload');

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_seo_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent', $callback);
			exit;
		}

		$robots_upload	= 'pass';
		$sitemap_upload	= 'pass';

		//파일 타입 검사
		if($_FILES['rebots_file']['size'] > 0){
			$robots_upload	= 'upload';
			$mime_type		= shell_exec('file -bi '. $_FILES['rebots_file']['tmp_name']);
			if(!preg_match('/^text/', $mime_type)){
				openDialogAlert('사용할 수 없는 "검색엔진 정보수집 제어" 파일입니다."',400,150,'parent');
				exit;
			}
		}

		if($_FILES['sitemap_file']['size'] > 0){
			$sitemap_upload	= 'upload';
			$mime_type		= shell_exec('file -bi '. $_FILES['sitemap_file']['tmp_name']);
			if(!preg_match('/^text|xml/', $mime_type)){
				openDialogAlert('사용할 수 없는 "사이트맵" 파일입니다."',400,150,'parent');
				exit;
			}
		}

		//파일업로드
		if($robots_upload == 'upload'){
			$config['upload_path'] = $_SERVER['DOCUMENT_ROOT'] . '/';
			$config['allowed_types'] = implode('|', array('txt'));
			$config['max_size']	= $this->config_system['uploadLimit'];
			$config['file_name'] = 'robots';
			$config['check_upload_path'] = FALSE;
			$config['overwrite'] = TRUE;
			$this->upload->initialize($config, TRUE);
			if ( ! $this->upload->do_upload('rebots_file')) {
				openDialogAlert('"검색엔진 정보수집 제어" 파일 업로드 실패.', 400, 150, 'parent');
				exit;
			}
		}

		if($sitemap_upload == 'upload'){
			$config['upload_path'] = $_SERVER['DOCUMENT_ROOT'] . '/';
			$config['allowed_types'] = implode('|', array('xml'));
			$config['max_size']	= $this->config_system['uploadLimit'];
			$config['file_name'] = 'sitemap';
			$config['check_upload_path'] = FALSE;
			$config['overwrite'] = TRUE;
			$this->upload->initialize($config, TRUE);
			if ( ! $this->upload->do_upload('sitemap_file')) {
				openDialogAlert('"사이트맵" 파일 업로드 실패.', 400, 150, 'parent');
				exit;
			}
		}

		$_POST['goods_view_allow']	= ($_POST['goods_view_allow'] == 'allow')	? 'allow' : 'disallow';
		$_POST['category_allow']	= ($_POST['category_allow'] == 'allow')		? 'allow' : 'disallow';
		$_POST['brand_allow']		= ($_POST['brand_allow'] == 'allow')		? 'allow' : 'disallow';
		$_POST['location_allow']	= ($_POST['location_allow'] == 'allow')		? 'allow' : 'disallow';
		$_POST['board_allow']		= ($_POST['board_allow'] == 'allow')		? 'allow' : 'disallow';
		$_POST['event_allow']		= ($_POST['event_allow'] == 'allow')		? 'allow' : 'disallow';
		$_POST['broadcast_allow']		= ($_POST['broadcast_allow'] == 'allow')		? 'allow' : 'disallow';

		config_save("seo" ,array('others'=>$_POST['others']));
		config_save("seo" ,array('goods_view'=>$_POST['goods_view']));
		config_save("seo" ,array('category'=>$_POST['category']));
		config_save("seo" ,array('brand'=>$_POST['brand']));
		config_save("seo" ,array('location'=>$_POST['location']));
		config_save("seo" ,array('board'=>$_POST['board']));
		config_save("seo" ,array('event'=>$_POST['event']));
		config_save("seo" ,array('broadcast'=>$_POST['broadcast']));
		config_save("seo" ,array('image_alt'=>$_POST['image_alt']));

		config_save("seo" ,array('goods_view_allow'=>$_POST['goods_view_allow']));
		config_save("seo" ,array('category_allow'=>$_POST['category_allow']));
		config_save("seo" ,array('brand_allow'=>$_POST['brand_allow']));
		config_save("seo" ,array('location_allow'=>$_POST['location_allow']));
		config_save("seo" ,array('board_allow'=>$_POST['board_allow']));
		config_save("seo" ,array('event_allow'=>$_POST['event_allow']));
		config_save("seo" ,array('broadcast_allow'=>$_POST['broadcast_allow']));

		//짧은 주소 설정
		$shorturl_use		= ($_POST['shorturl_use'])?$_POST['shorturl_use']:'N';
		config_save('snssocial',array('shorturl_use'=> $shorturl_use));

		$snslogo = $this->setting->upload_snslogo();
		$this->setting->snsconf($snslogo);

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,150,'parent',$callback);

	}

	public function get_search_engine_content(){
		$mode	= $_GET['mode'];

		switch($mode){
			case	'robots' :
				$load_file_name		= "{$_SERVER['DOCUMENT_ROOT']}/robots.txt";
				$file_name			= "robots.txt";
				break;

			case	'sitemap' :
				$load_file_name		= "{$_SERVER['DOCUMENT_ROOT']}/sitemap.xml";
				$file_name			= "sitemap.xml";
				break;
		}

		if(is_file($load_file_name)){
			$arrSEO['robots'];
			$fp			= fopen($load_file_name, "r");
			$content	= fread($fp, filesize($load_file_name));
			$return['content']	= $content;
		}else{
			$return['content']	= "설정된 파일 {$file_name}이 없습니다.";
		}


		echo json_encode($return);
	}

	public function multi_basic()
	{
		$this->load->model('multishopmodel');
		$result = $this->multishopmodel->getAdminEnv('');
		foreach($result as $data_multi){
			$this->multishopmodel->multiShopDBConnection($data_multi['shopSno']);
		}

		$aPostParams = $this->input->post();

		## 권한체크
		$auth	= $this->authmodel->manager_limit_act('setting_basic_detail');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		## 파비콘 파일 저장
		$favicon	= $this->setting->upload_favicon();
		$iphoneicon = $this->setting->upload_book_icon('iphoneicon');
		$androidicon = $this->setting->upload_book_icon('androidicon');
		$signatureicon = $this->setting->upload_book_icon('signatureicon');

		$icon['favicon'] = $favicon;
		$icon['iphoneicon'] = $iphoneicon;
		$icon['androidicon'] = $androidicon;
		$icon['signatureicon'] = $signatureicon;

		$where_params = array();
		$where_params['admin_env_seq'] = $aPostParams['admin_env_seq'];
		$data_ae = $this->adminenvmodel->get($where_params,0,1)->row_array();
		if($aPostParams['basic_currency'] && $data_ae['currency']!=$aPostParams['basic_currency'] && $data_ae['first_goods_date'] ){
			$callback = "";
			openDialogAlert("<span class=\"red\">최초 상품 등록 이후부터는 기준 통화를 변경할 수 없습니다.</span><br/>정상적으로 저장되었습니다.",450,180,'parent',$callback);
			exit;
		}

		## 기본정보
		$this->validation->set_rules('domain', '쇼핑몰 도메인','trim|prep_url|max_length[50]|xss_clean');
		$this->validation->set_rules('admin_env_name', '관리명','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('language', '안내 언어','trim|required|max_length[45]|xss_clean');
		if($aPostParams['compare_currency']){
			foreach($aPostParams['compare_currency'] as $row_compare_currency){
				if($row_compare_currency){
					$tmp_compare_currency[] = $row_compare_currency;
				}
				$compare_currency = implode(',',$tmp_compare_currency);
			}
			$aPostParams['compare_currency'] = $compare_currency;
		}
		if(count($aPostParams['currency_symbol_position'])!=5){
			$callback = "";
			openDialogAlert("통화표기/절사가 없습니다.",400,150,'parent',$callback);
			exit;
		}

		$tmp_code = array();
		foreach(code_load('currency_symbol_position') as $data_code){
			$tmp_code[] = $data_code['codecd'];
		}
		$this->validation->set_rules('currency_symbol_position[]', '통화표기/절사(위치)','trim|required|regex_match[/'.implode('|',$tmp_code).'/]');
		$tmp_code = array();
		foreach(code_load('cutting_price') as $data_code){
			$tmp_code[] = $data_code['codecd'];
		}
		$this->validation->set_rules('cutting_price[]', '통화표기/절사(절사)', 'required|regex_match[/'.implode('|',$tmp_code).'/]');
		$tmp_code = array();
		foreach(code_load('cutting_action') as $data_code){
			$tmp_code[] = $data_code['codecd'];
		}
		$this->validation->set_rules('cutting_action[]', '통화표기/절사(올림)', 'required|regex_match[/'.implode('|',$tmp_code).'/]');
		$this->validation->set_rules('currency_exchange[]', '기본통화환율', 'required|numeric');

		## 추가 정보 체크
		$this->validation->set_rules('shopBranch[]', '쇼핑몰 분류','trim|numeric|max_length[50]|xss_clean');
		$this->validation->set_rules('metaTagDescription', '메타태그 설명','trim|max_length[255]|xss_clean');

		## 모바일 혜택 체크
		$mobilesize = count($aPostParams['mobile_price1']);
		if($mobilesize) {
			for($i=0;$i<$mobilesize;$i++) {
				$price1			= $aPostParams['mobile_price1'][$i];
				$price2			= $aPostParams['mobile_price2'][$i];
				$sale_price		= (int) $aPostParams['mobile_sale_price'][$i];
				$sale_emoney	= (int) $aPostParams['mobile_sale_emoney'][$i];
				$sale_point		= (int) $aPostParams['mobile_sale_point'][$i];

				if($price1 == 0 && $price2 == 0  ){
					openDialogAlert("모바일/테블릿  추가할인시 상품의 구매금액이 모두 \"0\"일 수는 없습니다.",500,140,'parent',$callback);
					exit;
				}

				if($sale_price == 0 && $sale_emoney == 0 && $sale_point == 0 ){
					openDialogAlert("모바일/테블릿 추가할인 혜택과 추가적립 혜택이 모두 \"0\"일 수는 없습니다.",500,140,'parent',$callback);
					exit;
				}
			}
		}

		## 사업자정보 체크
		if($aPostParams['companyEmail'][0] || $aPostParams['companyEmail'][1]){
			$aPostParams['companyEmail']	= $aPostParams['companyEmail'][0]."@". $aPostParams['companyEmail'][1];
			$_POST['companyEmail'] 			= $aPostParams['companyEmail'];
		}
		if($aPostParams['partnershipEmail'][0] || $aPostParams['partnershipEmail'][1]){
			$aPostParams['partnershipEmail']	= $aPostParams['partnershipEmail'][0]."@".$aPostParams['partnershipEmail'][1];
			$_POST['partnershipEmail'] 			= $aPostParams['partnershipEmail'];
		}
		$this->validation->set_rules('companyName', '상호','trim|required|max_length[50]|xss_clean');
		$this->validation->set_rules('businessConditions', '업태','trim|max_length[20]|xss_clean');
		$this->validation->set_rules('businessLine', '종목','trim|max_length[20]|xss_clean');
		$this->validation->set_rules('businessLicense[]', '사업자 번호','trim|numeric|max_length[6]|xss_clean');
		$this->validation->set_rules('mailsellingLicense', '통신판매업 신고번호','trim|xss_clean');
		$this->validation->set_rules('ceo', '대표자','trim|max_length[20]|xss_clean');
		$this->validation->set_rules('companyPhone[]', '연락처','trim|numeric|max_length[4]|xss_clean');
		$this->validation->set_rules('companyFax[]', '팩스번호','trim|numeric|max_length[4]|xss_clean');
		$this->validation->set_rules('companyEmail', '이메일','trim|max_length[50]|valid_email|xss_clean');
		$this->validation->set_rules('companyZipcode[]', '우편번호','trim|max_length[7]|numeric|xss_clean');
		$this->validation->set_rules('companyAddress', '주소','trim|max_length[100]|xss_clean');
		$this->validation->set_rules('companyAddressDetail', '상세 주소','trim|max_length[100]|xss_clean');

		if	(serviceLimit('H_AD')){ // 입점몰일 경우
			$this->validation->set_rules('partnershipEmail', '입점문의 수신 이메일','trim|max_length[50]|valid_email|xss_clean');
		}

		if(!preg_match('/^[0-9]{3}\-[0-9]{2}\-[0-9]{5}$/', implode('-',$aPostParams['businessLicense']))){
			$callback = "if(parent.document.getElementsByName('businessLicense')[0]) parent.document.getElementsByName('businessLicense')[0].focus();";
			openDialogAlert('유효하지 않은 사업자번호입니다.',400,140,'parent',$callback);
			exit;
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$this->multishopmodel->start_auto_sync('multi_setting');

		## 기본정보 저장
		$this->setting->multi_basic_info($icon,$this->isplusfreenot,$aPostParams);

		## 추가 정보 저장
		$this->setting->multi_add_info($icon,$this->isplusfreenot,$aPostParams);

		## 사업자정보 저장
		$this->setting->multi_bussiness_info($icon,$this->isplusfreenot,$aPostParams);

		$this->multishopmodel->run_auto_sync('multi_setting',array('fm_config'));

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function set_alert(){
		$params		= $_POST;

		foreach($params['KR'] as $k => $v){
			$up_params = array(
						'KR' => $params['KR'][$k],
						'US' => $params['US'][$k],
						'CN' => $params['CN'][$k],
						'JP' => $params['JP'][$k]
			);
			$this->db->where(array('seq'=>$k));
			$this->db->update('fm_alert',$up_params);
		}

		$this->create_alert_file();

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,170,'parent',$callback);
	}

	public function create_alert_file(){
		$lang_arr	= array('KR','US','CN','JP');

		$sql		= "select * from fm_alert";
		$result		= $this->db->query($sql);
		$result		= $result->result_array();

		foreach($result as $k => $v){
			foreach($lang_arr as $lang){
				$lang_temp = str_replace("'","\'",$v[$lang]);
				$total_lang[$lang][] = "'".$v['code']."' : '".$lang_temp."'";
			}
		}

		foreach($lang_arr as $lang){
			$jsfilename = $_SERVER['DOCUMENT_ROOT']."/data/js/language/L10n_".$lang.".js";
			if( is_file($jsfilename) ){
				@unlink($jsfilename);
			}
			$fp = fopen($jsfilename, "w") or die("Can't open file ");
			@chmod($jsfilename,0777);
			fwrite($fp, "L10n = {");
			fwrite($fp, implode(',',$total_lang[$lang]));
			fwrite($fp, "};");
			fclose($fp);
		}
	}

	public function insert_alert(){
		// 파라미터 설정
		$params = $this->input->post();

		// 유효성 설정
		$this->validation->set_rules('location', '위치','trim|required|xss_clean|regex_match[/^[a-z]{2}[0-9]{0,3}$/]');
		$this->validation->set_rules('comment', '상세설명','trim|required|xss_clean');
		$this->validation->set_rules('KR', '한국어','trim|required|xss_clean');

		// 유효성 검증
		if ($this->validation->exec()===false) {
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		// 나라별 경고창 문구 코드 조회
		$this->db->select_max("code");
		$this->db->like('code', $params['location'], "after");
		$query = $this->db->get('fm_alert');
		$result	= $query->row_array();

		$sub_int = substr($result['code'],2,5);
		$sub_int = $sub_int+1;
		$code = substr("00".$sub_int, -3);

		if	($code > 999){
			openDialogAlert("1000 자리까진 넣을수 없습니다",400,170,'parent','');
			die();
		}

		$code = $params['location'].$code;

		switch($params['location']){
			case "gv": $location = '상품상세'; break;
			case "mp": $location = '마이페이지'; break;
			case "oc": $location = '장바구니'; break;
			case "os": $location = '주문/결제'; break;
			case "mo": $location = '주문내역'; break;
			case "mb": $location = '회원'; break;
			case "et": $location = '기타'; break;
		}

		$insert_param = array(
						'location'	=> $location,
						'comment'	=> $params['comment'],
						'code'		=> $code,
						'alert_type'=> $params['alert_type'],
						'KR_ORI'	=> $params['KR'],
						'US_ORI'	=> $params['US'],
						'CN_ORI'	=> $params['CN'],
						'JP_ORI'	=> $params['JP'],
						'KR'		=> $params['KR'],
						'US'		=> $params['US'],
						'CN'		=> $params['CN'],
						'JP'		=> $params['JP']
		);

		$this->db->insert('fm_alert',$insert_param);

		$this->create_alert_file();

		$callback = "";
		openDialogAlert("설정이 저장 되었습니다.",400,170,'parent',$callback);
	}

	// 입력된 장소 정보 추출 :: 2016-06-08 lwh
	public function get_shipping_address_ajax(){
		$this->load->model('shippingmodel');
		$result = $this->shippingmodel->get_shipping_address($_POST['seq']);

		echo json_encode($result);
	}

	// 장소 리스트 등록 및 수정 :: 2016-06-07 lwh
	public function set_shipping_address(){
		$this->load->model('shippingmodel');
		$data = $_POST;

		if($data['address_category'] == 'direct_input'){
			$this->validation->set_rules('address_category_direct', '분류','required|trim|xss_clean');
		}else{
			$this->validation->set_rules('address_category', '분류','required|trim|xss_clean');
		}

		$this->validation->set_rules('zoneZipcode[]', '우편번호','required|trim|xss_clean');
		if($data['address_nation'] == 'global'){
			$this->validation->set_rules('international_country', '국가','required|trim|xss_clean');
			$this->validation->set_rules('international_town_city', '도시','required|trim|xss_clean');
			$this->validation->set_rules('international_county', '주/도','required|trim|xss_clean');
			$this->validation->set_rules('international_address', '주소','required|trim|xss_clean');
		}else{
			$this->validation->set_rules('zoneAddressDetail', '주소','required|trim|xss_clean');
		}

		$this->validation->set_rules('address_name', '명칭','required|trim|xss_clean');
		$this->validation->set_rules('shipping_phone', '연락처','required|trim|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= $_POST['provider_seq'];
			if(!$provider_seq)	$provider_seq = 1;
		}

		$data['address_provider_seq'] = $provider_seq;

		// 등록 / 수정 model 에서 처리
		$this->shippingmodel->set_shipping_address($data);

		// file cache 삭제
		cache_clean('shipping_refund_address');

		$msg = '등록되었습니다.';
		if($data['shipping_address_seq'])	$msg = '수정되었습니다.';

		echo "<script>alert('".$msg."');parent.closeDialog('shipping_address_insert');parent.document.insFrm.reset();parent.international_chg();parent.category_chg();parent.applyAddress();</script>";
		exit;
	}

	// ###:1 배송설정 item 추가 :: 2016-06-09 lwh
	public function add_shipping_item(){
		$this->load->model('shippingmodel');

		// --- 수정 시 params 제작 :: START
		if($_GET['mode'] == 'modify'){
			$grp_seq = $_GET['grp_seq'];
			if(!$grp_seq){
				$callback = "parent.document.location.href='./shipping_group'";
				openDialogAlert('해당그룹 정보가 누락되었습니다<br/>다시 새로고침 후 시도하여 주세요.',400,160,'parent',$callback);
				exit;
			}

			// 수정용 POST 값 제작
			$limit = ($_GET['num']) ? $_GET['num'] : 0; // 1개씩 가져오기
			$params = $this->shippingmodel->ship_set_modify_params($grp_seq,$limit);

			if($params)		$next_num = $limit + 1;
			else			exit;

			// 필수값 체크를 위한 데이터
			$_POST = $params;
		}else{
			$params = $_POST;
		}

		if($params['shipping_set_code'] == 'direct_store' && $params['shipping_address_seq'] == null){
			openDialogAlert('수령매장 설정 항목은 필수입니다.',400,160,'parent',$callback);
			exit;
		}

		// --- 수정 시 params 제작 :: END
		$ship_set_code		= $this->shippingmodel->ship_set_code;
		$shipping_type_arr	= $this->shippingmodel->shipping_type_arr;
		$shipping_otp_type	= $this->shippingmodel->shipping_otp_type;
		$weekday			= $this->shippingmodel->weekday;

		// ### 배송저장에 변수정의 :: START ### //
		$shipping_group_seq = $params['shipping_group_seq']; // 최상위 그룹번호
		$ship_set	= array(); // 배송 설정
		$ship_opt	= array(); // 배송 방법
		$ship_cost	= array(); // 배송 금액
		$ship_zone	= array(); // 배송 지역
		$ship_store = array(); // 수령매장
		// ### 배송저장에 변수정의 :: END ### //

		$this->validation->set_rules('shipping_set_code', '배송설정','required|trim|xss_clean');

		$ship_set['shipping_set_code']	= $params['shipping_set_code']; // 배송설정 코드
		if($params['custom_set_use']=='Y'){ // 배송설정 명 정의
			$this->validation->set_rules('shipping_set_name', '배송설정 명','required|trim|xss_clean');
			$ship_set['shipping_set_name']	= $params['shipping_set_name'];
			$ship_set['custom_set_use']		= $params['custom_set_use'];
		}else{
			$ship_set['shipping_set_name']	= $ship_set_code[$params['shipping_set_code']];
		}

		$ship_set['shipping_set_seq']		= $params['shipping_set_seq'];	// 수정시 Seq 정보 추출
		$ship_set['prepay_info']			= $params['prepay_info'];		// 배송비 결제정보
		$ship_set['delivery_nation']		= $params['delivery_nation'];	// 배송가능국가
		$ship_set['delivery_type']			= $params['delivery_type'];		// 구매방식 - basic (1회구매)
		$ship_set['delivery_limit']			= $params['delivery_limit'];	// 배송지역 제한 여부

		// 기본값 설정
		if($ship_set['delivery_limit'] == 'unlimit'){
			if($params['delivery_nation'] == 'korea'){
				$params['shipping_area_name']['std'][0] = '대한민국';
			}else{
				$params['shipping_area_name']['std'][0] = '전세계';
			}
		}

		if($params['add_use'] == 'Y'){
			if($params['delivery_nation'] == 'global'){
				if(!$params['shipping_area_name']['add'][0]){
					$params['shipping_area_name']['add'][0] = '국가1';
				}
			}else{
				if(!$params['shipping_area_name']['add'][0]){
					$params['shipping_area_name']['add'][0] = '지역1';
				}
			}
		}

		foreach($shipping_type_arr as $type => $tit){
			// 사용여부 체크
			$ship_set[$type]['use_yn'] = $params[$type.'_use'];
			// 사용 시
			if($ship_set[$type]['use_yn'] == 'Y'){
				// 배송방법 타입
				$ship_opt[$type]['shipping_opt_type'] = $params['shipping_opt_type'][$type];

				// 무료인 및 고정인 경우 Fix 값 - 그외 단위 지정
				$ship_opt[$type]['shipping_opt_unit'] = $this->config_system['basic_currency'];
				if($ship_opt[$type]['shipping_opt_type'] == 'free' || $ship_opt[$type]['shipping_opt_type'] == 'fixed'){
					unset($params['section_st'][$type]);
					unset($params['section_ed'][$type]);
					$params['section_st'][$type][0]		= 0;
					$params['section_ed'][$type][0]		= 0;
				}else if($ship_opt[$type]['shipping_opt_type'] == 'cnt' || $ship_opt[$type]['shipping_opt_type'] == 'cnt_rep'){ // 수량
					$ship_opt[$type]['shipping_opt_unit'] = '개';
				}else if($ship_opt[$type]['shipping_opt_type'] == 'weight' || $ship_opt[$type]['shipping_opt_type'] == 'weight_rep'){ // 무게
					$ship_opt[$type]['shipping_opt_unit'] = 'Kg';
				}

				// 지역별 설정 정보 무결성 검사
				foreach($params['issue'][$type] as $k => $val){
					if($val >= '1'){
						$_POST['zone_chk'][$type][$k] = '1';
					}else{
						unset($_POST['zone_chk'][$type][$k]);
						openDialogAlert($tit . ' 배송비의 지역정보 항목은 필수입니다.',400,150,'parent',$callback);
						exit;
					}
					$this->validation->set_rules('zone_chk['.$type.']['.$k.']', '지역정보','required|trim|xss_clean');
				}
				// 각 배송비 구간 결과 추출
				$ins = 0;
				$idx = 0;
				$ship_cost_sum = 0;

				foreach($params['section_st'][$type] as $s => $section){
					$ship_opt[$type]['section_st'][$s] = $section;							// 시작구간
					$ship_opt[$type]['section_ed'][$s] = $params['section_ed'][$type][$s];	// 끝구간

					// 지역
					$idx2 = 0;
					foreach($params['shipping_area_name'][$type] as $z => $zone){
						$ship_cost[$type][$s]['shipping_area_name'][$z] = $zone; // 지역명
						if($type == 'hop'){
							// 당일배송 검증 - 당일배송이 가능하면 어느지역이든 당일배송이 존재하여야함.
							if($params['hopeday_limit_set'] == 'time' && array_search('Y',$params['today_yn']) === FALSE){
								openDialogAlert('희망배송일의 당일배송비가 1개이상 설정되어야 합니다.',400,150,'parent',$callback);
								exit;
							}
							$ship_cost[$type][$s]['shipping_today_yn'][$z] = $params['today_yn'][$idx2]; // 당일여부
							if($ship_cost[$type][$s]['shipping_today_yn'][$z] == 'Y'){
								$ship_cost[$type][$s]['shipping_cost_today'][$z] = $params['shipping_cost_today'][$type][$ins];
								$ins++;
							}
						}

						// 배송비 매칭
						$ship_cost[$type][$s]['shipping_cost'][$z] = ($params['shipping_cost'][$type][$idx]) ? $params['shipping_cost'][$type][$idx] : 0;
						$ship_cost[$type][$s]['shipping_cost_seq'][$z] = ($params['shipping_cost_seq'][$type][$idx]) ? $params['shipping_cost_seq'][$type][$idx] : 0;


						$idx ++;

						// 무료가 아닌경우 배송비 합계 계산
						if($ship_opt[$type]['shipping_opt_type'] != 'free'){
							$ship_cost_sum += $ship_cost[$type][$s]['shipping_cost'][$z];
						}

						// 지역 매칭
						if($params['delivery_nation'] != 'korea'){
							foreach($params['sel_address_street'][$type][$z] as $k => $address){
								$ship_zone[$type]['seq'] = $params['shipping_opt_seq_list'][$type][0];
								$ship_zone[$type]['sel_address_street'][$z][$k]	= $address;
								$ship_zone[$type]['sel_address_zibun'][$z][$k]	= $params['sel_address_zibun'][$type][$z][$k];
								$ship_zone[$type]['sel_address_join'][$z][$k]	= $params['sel_address_join'][$type][$z][$k];
								$ship_zone[$type]['sel_address_txt'][$z][$k]	= $params['sel_address_txt'][$type][$z][$k];
							}
						}

						$idx2++;
					}
				}

				// 배송비가 지정되는경우 예외처리:: 2017-01-06 lwh // $tit - 타입명
				if($type != 'store' && $ship_opt[$type]['shipping_opt_type'] != 'free'){
					// 배송비 검증 추가
					if($ship_cost_sum < 1){
						openDialogAlert("입력된 " . $tit . " 배송비가 모두 \'0\'입니다.<br/>" . $tit . " 배송비 유형을 \'무료\'로 선택하세요.",440,160,'parent',$callback);
						exit;
					}

					// 구간 무결성 검사
					$section_ed_cnt = count($params['section_ed'][$type]) - 1;
					if($params['section_ed'][$type][$section_ed_cnt] <= 0 && preg_match('/_rep/', $ship_opt[$type]['shipping_opt_type'])){
						openDialogAlert($tit . " 배송비 구간의 마지막은 \'0\'이 될수 없습니다.",400,150,'parent',$callback);
						exit;
					}
				}

				// 배송안내 설정
				$ship_set[$type]['delivery_info_type'] = $params['delivery_'.$type.'_type'];
				if($ship_set[$type]['delivery_info_type'] == 'N'){ // 직접입력 시 메세지
					$this->validation->set_rules('delivery_'.$type.'_input', '직접입력시 배송안내','required|trim|xss_clean');
					$ship_set[$type]['delivery_info_input'] = $params['delivery_'.$type.'_input'];
				}

				// 각종 예외처리 시작
				if	($type == 'hop')		{ // 희망배송일 예외처리
					$ship_set['npay_order_use']		= false;					//네이버페이 주문불가-희망배송일 사용
					// 희망배송일 선택설정
					$ship_set['hopeday_required']	= $params['hopeday_required'];
					// 희망배송일 선택 시작일 타입 - default 값 지정
					$ship_set['hopeday_limit_set']	= ($params['hopeday_limit_set']) ? $params['hopeday_limit_set'] : 'time';
					// 희망배송일 선택 시작일 설정 - default 값 지정
					$ship_set['hopeday_limit_val'] = ($params['hopeday_limit_val_'.$ship_set['hopeday_limit_set']]) ? $params['hopeday_limit_val_'.$ship_set['hopeday_limit_set']] : '1330';
					$this->validation->set_rules('hopeday_limit_val_'.$ship_set['hopeday_limit_set'], '주문당일 설정','required|trim|xss_clean');
					// 희망배송일 요일불가 설정
					$hopeday_limit_week = array(0,0,0,0,0,0,0);
					if($params['hopeday_limit_week']){
						$hopeday_week_arr = $params['hopeday_limit_week'];
						foreach($hopeday_week_arr as $k => $week){
							$hopeday_limit_week[$week] = 1;
						}
					}
					$ship_set['hopeday_limit_week']			= $hopeday_limit_week;
					$ship_set['hopeday_limit_week_real']	= implode('',$hopeday_limit_week);

					// 희망배송일 반복선택불가일
					$limit_repeat_day_arr = explode(',',preg_replace('/[^0-9\-\,]/','',$params['hopeday_limit_repeat_day'])); // 지정 문자 외 삭제 후 검사
					sort($limit_repeat_day_arr);
					foreach($limit_repeat_day_arr as $k => $day){
						if(date('Y-m-d',strtotime(date('y').'-'.$day)) != '1970-01-01'){
							$tmp_repeat_day[] = $day;
						}
					}
					$limit_repeat_day = implode(',',$tmp_repeat_day);
					$ship_set['hopeday_limit_repeat_day'] = $limit_repeat_day;

					//희망배송일 선택불가일
					$hope_year = $params['hope_year'];
					sort($hope_year);
					foreach($hope_year as $k => $year){
						$year = preg_replace('/[^0-9]/','',$year);
						unset($limit_day_arr);
						$limit_day_arr = explode(',',preg_replace('/[^0-9\-\,]/','',$params['hopeday_limit_day'][$k])); // 지정 문자 외 삭제 후 검사
						sort($limit_day_arr);
						foreach($limit_day_arr as $i => $day){
							if(date('Y-m-d',strtotime($year.'-'.$day)) != '1970-01-01'){
								$tmp_day[] = $year.'-'.$day;
								$tmp_serialize[$year][] = $day;
							}
						}
					}
					sort($tmp_day);
					$limit_day = implode(',',$tmp_day);
					$limit_day_serialize = serialize($tmp_serialize);
					$ship_set['hopeday_limit_day'] = $limit_day;
					$ship_set['limit_day_serialize'] = $limit_day_serialize;
					$ship_set['limit_day_tmp'] = $tmp_serialize;
				} else if	($type == 'store')		{ // 수령매장 예외처리
					$this->validation->set_rules('shipping_address_seq[]', '수령매장 설정','required|trim|xss_clean');
					// 연결된 수령매장
					if($params['shipping_address_seq']){
						// 창고 사용여부 검색
						if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
						$use_wh_seqs = array_keys($this->scm_cfg['use_warehouse']);

						$shipping_address_seq = $params['shipping_address_seq'];
						foreach($shipping_address_seq as $k => $address_seq){
							// 배송지 고유번호
							$ship_store['rel'][$k]['shipping_address_seq'] = $address_seq;
							// 수령매장명
							$ship_store['rel'][$k]['shipping_store_name'] = $params['shipping_store_name'][$k];
							// 매장 전화번호
							$ship_store['rel'][$k]['store_phone'] = $params['store_phone'][$k];
							// 수령매장 창고연결여부
							$ship_store['rel'][$k]['store_scm_type'] = $params['store_scm_type'][$k];
							// 수령매장 재고설정
							$ship_store['rel'][$k]['store_supply_set'] = $params['store_supply_set'][$k];
							// 수령매장 재고보기설정
							$ship_store['rel'][$k]['store_supply_set_view'] = $params['store_supply_set_view'][$k];
							// 수령매장 재고수량설정
							$ship_store['rel'][$k]['store_supply_set_order'] = $params['store_supply_set_order'][$k];
							// 사용창고여부 결정
							if	($ship_store['rel'][$k]['store_scm_type'] == 'Y'){
								$wh_use = 'Y';
								if	(array_search($params['store_scm_seq'][$k],$use_wh_seqs) === false)
									$wh_use = 'N';
							}
							$ship_store['rel'][$k]['store_scm_use'] = $wh_use;

							// 분류
							$ship_store['tmp'][$k]['shipping_address_category'] = $params['shipping_address_category'][$k];
							// 해외여부
							$ship_store['tmp'][$k]['shipping_address_nation'] = $params['shipping_address_nation'][$k];
							// 주소
							$ship_store['tmp'][$k]['shipping_address_full'] = trim($params['shipping_address_full'][$k]);
							// 매장 타입
							$ship_store['rel'][$k]['store_type'] =  $params['store_type'][$k];
							// 창고 고유키
							$ship_store['rel'][$k]['store_scm_seq'] =  $params['store_scm_seq'][$k];
						}
					}
				}
			}else{ // 사용안할 시
				continue;
			}

			$getDatas = array(
				'shipping_set_seq'		=> $params['shipping_set_seq'],
				'shipping_group_seq'	=> $params['shipping_group_seq'],
				'shipping_set_type'		=> $type
			);
			$optSeqs = $this->shippingmodel->get_option_seqs($getDatas);
			$shipping_opt_seq[$type]	= $optSeqs;

			if(!$params['zone_count']){
				if(count($optSeqs) > 0){
					$getDatas = array(
						'shipping_set_seq'		=> $params['shipping_set_seq'],
						'shipping_group_seq'	=> $params['shipping_group_seq'],
						'shipping_set_type'		=> $type,
						'delivery_limit'		=> 'limit'
					);
					$costDatas = $this->shippingmodel->get_cost_seqs($optSeqs, $getDatas);

					$costs = end($costDatas);
					foreach($costs as $seq){
						$zone_count = $this->shippingmodel->get_shipping_zone_count($seq,'shipping_cost_seq');
						$params['zone_count'][$type][$k] = $zone_count[0]['shipping_zone_count'];
						$params['zone_cost_seq'][$type][$k] = $seq;
						$k++;
					}
				}
			}
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$this->tempate_modules();
		if($_GET['mode'] == 'modify'){
			$this->template->assign("mode",'modify');
			$this->template->assign("grp_seq",$grp_seq);
			$this->template->assign("num",$next_num);
			$this->template->assign("default_yn", $params['default_yn']);
		}

		// 기존 등록내용 수정시
		if($params['idx']){
			$this->template->assign("idx",$params['idx']);
		}

		# 해당 배송정책이 네이버페이 주문시 사용가능한지 체크 @2016-10-17
		list($npay_possible,$npay_impossible_message) = $this->shippingmodel->add_shipping_partner_possible_check($params);
		$ship_set['npay_order_possible']		= ($npay_possible)? "Y":"N";
		$ship_set['npay_order_impossible_msg']	= ($npay_impossible_message)? implode(", ",$npay_impossible_message):"";

		# 해당 배송정책이  주문시 사용가능한지 체크
		list($talkbuy_possible,$talkbuy_impossible_message) = $this->shippingmodel->add_shipping_partner_possible_check($params,"talkbuy");
		$ship_set['talkbuy_order_possible']		= ($talkbuy_possible)? "Y":"N";
		$ship_set['talkbuy_order_impossible_msg']	= ($talkbuy_impossible_message)? implode(", ",$talkbuy_impossible_message):"";

		// 반품 배송비 관련 추가 :: 2018-05-10 lwh
		$ship_set['refund_shiping_cost']		= ($params['refund_shiping_cost'])	? $params['refund_shiping_cost'] : 0;
		$ship_set['swap_shiping_cost']			= ($params['swap_shiping_cost'])	? $params['swap_shiping_cost'] : 0;
		$ship_set['shiping_free_yn']			= ($params['shiping_free_yn'])		? $params['shiping_free_yn'] : 'N';

		$this->template->assign("punit",'KRW'); // config 에서 불러옴
		$this->template->assign(array(
			'shipping_group_seq'=> $shipping_group_seq,
			'shipping_group_real_seq'=> $params['shipping_group_real_seq'],
			'ship_set_code'		=> $ship_set_code,
			'shipping_type'		=> $shipping_type_arr,
			'shipping_otp_type' => $shipping_otp_type,
			'weekday'			=> $weekday,
			'ship_set'			=> $ship_set,
			'ship_opt'			=> $ship_opt,
			'ship_cost'			=> $ship_cost,
			'ship_store'		=> $ship_store,
			'zone_count'		=> $params['zone_count'],
			'zone_cost_seq'		=> $params['zone_cost_seq'],
			'shipping_opt_seq'	=> $shipping_opt_seq
		));

		if($params['delivery_nation'] != 'korea'){
			$this->template->assign('ship_zone', $ship_zone);
		}

		$file_path	= str_replace('setting_process/add_shipping_item.html', 'setting/add_national_view.html', $this->template_path());
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// ###:2 배송그룹 최종 저장 :: 2016-06-16 lwh
	public function save_shipping_group(){

		// 문구 변경
		if($this->input->post('provider_seq') != 1){
			echo "<script>alert('입점사 배송그룹정보는 수정할수 없습니다');history.go(-1);</script>";
			exit;
		}

		if($_POST['shipping_group_real_seq'] > 0){
			$_POST['shipping_group_seq'] = $_POST['shipping_group_real_seq'];
		}

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_shipping_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,160,'parent',$callback);
			exit;
		}

		// 임시 저장 제한 POST 값의 제한으로 인한 제한 :: 2017-01-02 lwh
		$cnt_kr = count($_POST['shipping_set_code']['korea']);
		$cnt_gl = count($_POST['shipping_set_code']['global']);
		if( ($cnt_kr + $cnt_gl) > 6 ){
			openDialogAlert('한 배송그룹 내에 배송방법은 6개를 넘을 수 없습니다.',400,140,'parent','');
			exit;
		}

		$this->validation->set_rules('shipping_group_name', '배송그룹명','required|trim|xss_clean');
		$this->validation->set_rules('refund_address_seq', '반송지','required|trim|xss_clean');
		$this->validation->set_rules('default_yn', '관리=>기본','required|trim|xss_clean');

		$callback = "parent.document.location.href='./shipping_group'";
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			openDialogAlert($err['value'],400,160,'parent','');
			exit;
		}

		// ### 배송저장에 변수정의 :: START ### //
		$grp_sum	= array(); // 배송 그룹 요약
		$ship_grp	= array(); // 배송 그룹
		$ship_set	= array(); // 배송 설정
		$ship_opt	= array(); // 배송 방법
		$ship_cost	= array(); // 배송 금액
		$ship_zone	= array(); // 배송 지역
		$ship_store = array(); // 수령매장
		// ### 배송저장에 변수정의 :: END ### //

		//더미 데이터 삭제용
		$set_seqs = array();
		$cost_seqs = array();

		$this->load->model('shippingmodel');
		$this->db->trans_begin();

		// 더미 데이터 유효성 검사
		$shipping_group_dummy = $this->shippingmodel->get_shipping_group($this->input->post('shipping_group_dummy_seq'));
		if(!$shipping_group_dummy) {
			$callback = "parent.document.location.href='./shipping_group'";
			openDialogAlert("다른 관리자가 수정중 입니다.<br/>다시 시도해주세요.", 400, 180, 'parent', $callback);
			exit;
		}

		if($_POST['shipping_group_real_seq'] > 0){
			$this->shippingmodel->reset_shipping($_POST['shipping_group_real_seq'], $shipping_group_dummy['shipping_group_seq'], $_POST['shipping_calcul_type']);
		}
		$ship_nation		= array('korea', 'global');
		$ship_set_code		= $this->shippingmodel->ship_set_code;
		$shipping_type_arr	= $this->shippingmodel->shipping_type_arr;
		$shipping_otp_type	= $this->shippingmodel->shipping_otp_type;
		$weekday			= $this->shippingmodel->weekday;

		$nowDate = date('Y-m-d H:i:s');

		// 업데이트 모드
		if($_POST['shipping_group_seq']){
			$reg_mode = 'modify';
		}else{
			$reg_mode = 'save';
		}

		// ### 배송그룹 저장 :: START
		$ship_grp['shipping_group_name']		= $_POST['shipping_group_name'];
		$ship_grp['shipping_group_type']		= $_POST['shipping_group_type'];
		$ship_grp['shipping_calcul_type']		= $_POST['shipping_calcul_type'];
		$ship_grp['shipping_calcul_free_yn']	= ($_POST[$ship_grp['shipping_calcul_type'].'_calcul_free_yn']) ? 'Y' : 'N';
		$ship_grp['shipping_std_free_yn']		= ($_POST[$ship_grp['shipping_calcul_type'].'_std_free_yn']) ? 'Y' : 'N';
		$ship_grp['shipping_add_free_yn']		= ($_POST[$ship_grp['shipping_calcul_type'].'_add_free_yn']) ? 'Y' : 'N';
		$ship_grp['shipping_hop_free_yn']		= ($_POST[$ship_grp['shipping_calcul_type'].'_hop_free_yn']) ? 'Y' : 'N';
		$ship_grp['sendding_scm_type']			= $_POST['refund_scm_type'];
		$ship_grp['sendding_address_seq']		= $_POST['refund_address_seq'];
		$ship_grp['refund_scm_type']			= $_POST['refund_scm_type'];
		$ship_grp['refund_address_seq']			= $_POST['refund_address_seq'];
		$ship_grp['shipping_provider_seq']		= $this->input->post('provider_seq');
		$ship_grp['total_rel_cnt']				= $_POST['total_rel_cnt'];
		$ship_grp['admin_memo']					= addslashes($_POST['admin_memo']);
		$ship_grp['default_yn']					= ($_POST['base_grp']=='Y') ? 'Y':'N';
		$ship_grp['provider_shipping_use']		= ($_POST['base_grp']=='Y') ? 'Y':'N';
		$ship_grp['update_date']				= $nowDate;
		if($reg_mode == 'save'){
			$ship_grp['regist_date']			= $nowDate;
			$mode_msg = '등록';
		}else{
			$mode_msg = '수정';
		}

		// 시스템 메모 저장
		$ship_grp['system_memo'] = $nowDate . ' ' . $this->managerInfo['mname'] . '(' . $this->managerInfo['manager_id'] . ') ' . $mode_msg . ' [' . $_SERVER['REMOTE_ADDR'] . ']';

		// ## fm_shipping_grouping Save
		if($reg_mode == 'save'){
			$shipping_group_seq = $this->shippingmodel->set_shipping_group($ship_grp);
			if(!$shipping_group_seq){
				openDialogAlert($fail_msg.'<br/>ErrCode: 01',400,140,'parent',$callback);
				exit;
			}
		}else{
			$shipping_group_seq = $this->shippingmodel->set_shipping_group($ship_grp);

			// 하위 데이터 지우기
			$this->shippingmodel->reset_shipping_group($shipping_group_seq);

			// 삭제된 set 설정 지우기
			foreach($_POST['delete_set_seq'] as $k => $del_set_seq){
				$this->shippingmodel->del_shipping_set($del_set_seq);
			}
		}

		// ### 배송그룹 저장 :: END

		// ### 배송그룹연결상품 조정
		$this->shippingmodel->group_cnt_adjust();

		// failed_msg
		$fail_msg = $mode_msg . '에 실패하였습니다.<br/>새로고침 후 다시 시도해주세요.';

		// -요약- 배송그룹번호
		$grp_sum['shipping_group_seq']			= $shipping_group_seq;
		// 기본배송방법 추출
		$tmpDefault = explode('_',$_POST['default_yn']);
		$default_yn[$tmpDefault[0]][$tmpDefault[1]] = 'Y';

		// 국가별 LOOP START
		foreach($ship_nation as $nation_key => $nation){
			if($nation != 'korea'){
				$this->shippingmodel->del_shipping_addr_global($shipping_group_seq);
			}

			// 배송설정 LOOP START
			foreach($_POST['shipping_set_code'][$nation] as $setKey => $set_code){

				// ### 배송설정 저장 :: START
				unset($ship_set);
				$ship_set['shipping_group_seq']			= $shipping_group_seq; // group_seq FK
				$ship_set['shipping_set_seq']			= $_POST['shipping_set_seq'][$nation][$setKey];
				$ship_set['default_yn']					= ($default_yn[$nation][$setKey]=='Y') ? 'Y' : 'N';
				$ship_set['shipping_set_code']			= $set_code; // 설정 코드명
				$ship_set['shipping_set_name']			= $_POST['shipping_set_name'][$nation][$setKey]; // 배송설정 명
				$ship_set['prepay_info']				= $_POST['prepay_info'][$nation][$setKey]; // 선불/착불정보
				$ship_set['delivery_nation']			= $_POST['delivery_nation'][$nation][$setKey]; // 배송가능국가
				$ship_set['delivery_type']				= $_POST['delivery_type'][$nation][$setKey]; // 구매방식
				// 네이버페이 주문시 해당 배송정책 사용 가능여부
				$ship_set['npay_order_possible']		= $_POST['npay_order_possible'][$nation][$setKey];
				$ship_set['npay_order_impossible_msg']	= $_POST['npay_order_impossible_msg'][$nation][$setKey];

				// 카카오톡구매 주문시 해당 배송정책 사용 가능여부
				$ship_set['talkbuy_order_possible']		 = $_POST['talkbuy_order_possible'][$nation][$setKey];
				$ship_set['talkbuy_order_impossible_msg']= $_POST['talkbuy_order_impossible_msg'][$nation][$setKey];

				$ship_set['delivery_limit'] = ($_POST['delivery_limit'][$nation][$setKey]) ? $_POST['delivery_limit'][$nation][$setKey] : 'unlimit'; // 배송지역 제한
				// 추가배송비 사용여부
				$ship_set['add_use'] = ($_POST['add_use'][$nation][$setKey]=='Y') ? 'Y' : 'N';
				// 희망배송일 사용여부
				$ship_set['hop_use'] = ($_POST['hop_use'][$nation][$setKey]=='Y') ? 'Y' : 'N';
				// 수령매장 사용여부
				$ship_set['store_use'] = ($_POST['store_use'][$nation][$setKey]=='Y') ? 'Y' : 'N';
				// 희망배송일 선택설정
				$ship_set['hopeday_required'] = ($_POST['hopeday_required'][$nation][$setKey]=='Y') ? 'Y' : 'N';
				// 희망배송일 선택 시작일 타입
				$ship_set['hopeday_limit_set'] = $_POST['hopeday_limit_set'][$nation][$setKey];
				// 희망배송일 선택 시작일 설정
				$ship_set['hopeday_limit_val'] = $_POST['hopeday_limit_val'][$nation][$setKey];
				// 희망배송일 요일불가 설정
				$ship_set['hopeday_limit_week'] = $_POST['hopeday_limit_week'][$nation][$setKey];
				// 희망배송일 반복선택불가일
				$ship_set['hopeday_limit_repeat_day'] = $_POST['hopeday_limit_repeat_day'][$nation][$setKey];
				// 희망배송일 선택불가일
				$ship_set['hopeday_limit_day'] = $_POST['hopeday_limit_day'][$nation][$setKey];
				// 희망배송일 선택불가일 serialize
				$ship_set['limit_day_serialize'] = $_POST['limit_day_serialize'][$nation][$setKey];

				// 반품 배송비 관련 추가 :: 2018-05-14 lwh
				$ship_set['refund_shiping_cost']	= $_POST['refund_shiping_cost'][$nation][$setKey];
				$ship_set['swap_shiping_cost']		= $_POST['swap_shiping_cost'][$nation][$setKey];
				$ship_set['shiping_free_yn']		= $_POST['shiping_free_yn'][$nation][$setKey];

				// 배송비 안내타입 저장
				foreach($shipping_type_arr as $set_type => $tit){
					// 배송비 안내 타입
					$ship_set['delivery_'.$set_type.'_type'] = $_POST['delivery_'.$set_type.'_type'][$nation][$setKey];
					// 배송비 직접입력
					$ship_set['delivery_'.$set_type.'_input'] = $_POST['delivery_'.$set_type.'_input'][$nation][$setKey];
				}
				// ## fm_shipping_set Save
				if($reg_mode == 'save'){
					$shipping_set_seq = $this->shippingmodel->set_shipping_set($ship_set);
				}else{
					if($_POST['shipping_set_seq'][$nation][$setKey]){
						$shipping_set_seq = $_POST['shipping_set_seq'][$nation][$setKey];
						if(!$shipping_set_seq){
							openDialogAlert($fail_msg.'<br/>ErrCode: 02',400,140,'parent',$callback);
							exit;
						}
						$ship_set['shipping_set_seq'] = $shipping_set_seq;
						$this->shippingmodel->set_shipping_set($ship_set);
					}else{
						$shipping_set_seq = $this->shippingmodel->set_shipping_set($ship_set);
					}
				}

				if(!$shipping_set_seq){
					// 배송그룹 롤백
					//$this->shippingmodel->del_shipping_group($shipping_group_seq);
					openDialogAlert($fail_msg.'<br/>ErrCode: 02',400,140,'parent',$callback);
					exit;
				} else {
					$set_seqs[] = $shipping_set_seq;
				}
				// ### 배송설정 저장 :: END
				// 배송설정 타입 Loop START
				unset($infostr);
				unset($set_sum);
				foreach($shipping_type_arr as $set_type => $tit){

				// 사용 시 에만 저장 - 예외처리
				if	($ship_set[$set_type.'_use'] == 'N' || $set_type == 'store' || $set_type == 'reserve')	continue;

				// 최대값/최소값 초기화
				if($set_type == 'std' || $set_type == 'add'){
					${$set_type.'_max_cost'} = 0;
					${$set_type.'_min_cost'} = 9999999;
				}

				// 구간 LOOP START
				$section_st_arr = $_POST['section_st'][$nation][$setKey][$set_type];
				$section_ed_arr = $_POST['section_ed'][$nation][$setKey][$set_type];

				foreach($section_st_arr as $otpKey => $section_st){
					// ### 배송방법 저장 :: START
					$ship_opt['shipping_opt_seq'] = $_POST['shipping_opt_seq'][$nation][$setKey][$set_type][$otpKey];
					$ship_opt['shipping_group_seq'] = $shipping_group_seq; // group_seq FK
					$ship_opt['shipping_set_seq'] = $shipping_set_seq; // set_seq FK
					$ship_opt['shipping_set_code'] = $set_code; // set_code FK

					// 배송 입점사 번호 - 반정규화
					$ship_opt['shipping_provider_seq'] = $ship_grp['shipping_provider_seq'];
					// 배송설정명 - 반정규화
					$ship_opt['shipping_set_name'] = $ship_set['shipping_set_name'];

					// 배송설정 타입
					$ship_opt['shipping_set_type'] = $set_type;
					// 배송방법 타입
					$ship_opt['shipping_opt_type'] = $_POST['shipping_opt_type'][$nation][$setKey][$set_type];

					// 배송지역 제한 - 반정규화
					$ship_opt['delivery_limit'] = ($set_type=='std') ? $ship_set['delivery_limit'] : 'limit';
					// 배송비 기준 여부 - 반정규화
					$ship_opt['default_yn'] = $ship_set['default_yn'];

					// 시작구간
					$ship_opt['section_st'] = $section_st_arr[$otpKey];
					// 끝구간
					$ship_opt['section_ed'] = $section_ed_arr[$otpKey];

					// 배송비계산기준 - 반정규화
					$ship_opt['shipping_calcul_type'] = $ship_grp['shipping_calcul_type'];
					// 계산 무료화 여부 - 반정규화
					$ship_opt['shipping_calcul_free_yn'] = $ship_grp['shipping_calcul_free_yn'];
					// 기본배송비 무료화 - 반정규화
					$ship_opt['shipping_std_free_yn'] = $ship_grp['shipping_std_free_yn'];
					// 추가배송비 무료화 - 반정규화
					$ship_opt['shipping_add_free_yn'] = $ship_grp['shipping_add_free_yn'];
					// 희망배송일 무료화 - 반정규화
					$ship_opt['shipping_hop_free_yn'] = $ship_grp['shipping_hop_free_yn'];

					// 무료계산시 기본 배송비는 무조건 무료 -> 검증추가
					$shipping_opt_seq = false;
					if($ship_grp['shipping_calcul_type'] == 'free' && $set_type == 'std' && $ship_opt['shipping_opt_type'] != 'free'){
						$fail_msg = '배송비 계산기준이 `무료`일때 기본 배송비는 `무료`가 아닐수 없습니다.';
					} else {
						// ## fm_shipping_option Save
						$shipping_opt_seq = $this->shippingmodel->set_shipping_opt($ship_opt);
					}

					if(!$shipping_opt_seq){
						// 배송그룹 롤백
						$this->shippingmodel->del_shipping_group($shipping_group_seq);
						// 배송설정 롤백
						$this->shippingmodel->del_shipping_set($shipping_set_seq);
						openDialogAlert($fail_msg.'<br/>ErrCode: 04',400,140,'parent',$callback);
						exit;
					}
					// ### 배송방법 저장 :: END

					// -요약- 무료배송여부
					if($set_type == 'std' && $ship_set['default_yn'] == 'Y'){
						// 기본 배송방법
						$grp_sum['free_set_code']	= $set_code;
						$grp_sum['first_cost']		= $_POST['shipping_cost'][$nation][$setKey][$set_type][0][0];

						// 기본 배송방법 배송비 결제타입
						$grp_sum['prepay_info']		= $ship_set['prepay_info'];

						// 기본 배송체크
						$grp_sum['free_shipping_use']	= 'N';
						$grp_sum['fixed_cost']			= Null;
						if($ship_opt['shipping_opt_type'] == 'free'){
							$grp_sum['default_type']		= 'free';
							$grp_sum['free_shipping_use']	= 'Y';
						}else{
							if($ship_opt['shipping_opt_type'] == 'fixed' && $ship_set['delivery_limit'] == 'unlimit'){
								$grp_sum['default_type']	= 'fixed';
								$grp_sum['fixed_cost']		= $_POST['shipping_cost'][$nation][$setKey][$set_type][0][0];
							}
						}
					}
					// -요약- 기본배송비 배송타입 저장 추가
					if($ship_set['default_yn'] == 'Y' && ($set_type == 'std' || $set_type == 'add')){
						$grp_sum[$set_type.'_opt_type']	= $ship_opt['shipping_opt_type'];
					}

					// 배송금액 LOOP START
					$shipping_area_name_arr = $_POST['shipping_area_name'][$nation][$setKey][$set_type];
					foreach($shipping_area_name_arr as $costKey => $area_name){
						// -배송안내- 배송비
						$set_sum[$set_type]['cost'][] = $_POST['shipping_cost'][$nation][$setKey][$set_type][$otpKey][$costKey];
						// -배송안내- 당일배송비
						$set_sum[$set_type]['today_cost'][] = $_POST['shipping_cost_today'][$nation][$setKey][$set_type][$otpKey][$costKey];
						$set_sum[$set_type]['shipping_today_yn'][] = $_POST['shipping_today_yn'][$nation][$setKey][$set_type][$costKey];

						// ### 배송금액 저장 :: START
						$ship_cost['shipping_cost_seq'] = $_POST['shipping_cost_seq'][$nation][$setKey][$set_type][$otpKey][$costKey];
						// 배송방법 번호
						$ship_cost['shipping_opt_seq']			= $shipping_opt_seq;
						// 배송그룹 번호 삭제용
						$ship_cost['shipping_group_seq_tmp']	= $shipping_group_seq;

						// 지역명
						$ship_cost['shipping_area_name'] = $area_name;
						// 금액
						$ship_cost['shipping_cost']	= $_POST['shipping_cost'][$nation][$setKey][$set_type][$otpKey][$costKey];

						// 당일여부
						$ship_cost['shipping_today_yn']	= $_POST['shipping_today_yn'][$nation][$setKey][$set_type][$costKey];
						// 당일금액
						$ship_cost['shipping_cost_today']	= $_POST['shipping_cost_today'][$nation][$setKey][$set_type][$otpKey][$costKey];

						// 최대값/최소값 정의
						if(($set_type == 'std' || $set_type == 'add') && $ship_set['default_yn'] == 'Y'){
							// 네이버페이 수량(구간반복) 첫번째 배송설정은 무시되도록 개선 2018-06-07
							if ( !($grp_sum[$set_type.'_opt_type'] == 'cnt_rep' && $ship_opt['section_ed'] == 1  && $ship_cost['shipping_cost'] == 0 )) {
								$nowCost = ($ship_cost['shipping_cost'] > $ship_cost['shipping_cost_today']) ? $ship_cost['shipping_cost'] : $ship_cost['shipping_cost_today'];

								// 최대값 정의
								if(${$set_type.'_max_cost'} < $nowCost)
									${$set_type.'_max_cost'} = $nowCost;
								// 최소값 정의
								if(${$set_type.'_min_cost'} > $nowCost)
									${$set_type.'_min_cost'} = $nowCost;
							}
						}

						// ## fm_shipping_option Save
						$shipping_cost_seq = $this->shippingmodel->set_shipping_cost($ship_cost);
						if(!$shipping_cost_seq){
							// 배송그룹 롤백
							$this->shippingmodel->del_shipping_group($shipping_group_seq);
							// 배송설정 롤백
							$this->shippingmodel->del_shipping_set($shipping_set_seq);
							// 배송금액 롤백
							$this->shippingmodel->del_shipping_opt($shipping_opt_seq);
							openDialogAlert($fail_msg.'<br/>ErrCode: 05',400,140,'parent',$callback);
							exit;
						} else {
							$cost_seqs[] = $shipping_cost_seq;
						}

						// 배송지 지역제한이 없을 경우
						// 이미 등록되어 있는 배송지 지역 정보를 삭제한다.
						if($ship_opt['delivery_limit'] == 'unlimit' && $shipping_cost_seq){
							$del_area_detail_params = array();
							$del_area_detail_params['shipping_cost_seq'] =  $shipping_cost_seq;
							$del_area_detail_params['shipping_group_seq_tmp'] = $ship_opt['shipping_group_seq'];
							$this->shippingmodel->del_shipping_area_detail($del_area_detail_params);
						}

						if($nation != 'korea'){
							// 배송지역 상세 LOOP START
							$detail_adress_arr = $_POST['sel_address_join'][$nation][$setKey][$set_type][$costKey];
							foreach($detail_adress_arr as $zoneKey => $address){
								// ### 배송지역 상세 저장 :: START

								// 배송금액 번호
								$ship_zone['shipping_cost_seq'] = $shipping_cost_seq;
								// 배송그룹 번호 삭제용
								$ship_zone['shipping_group_seq_tmp'] = $shipping_group_seq;
								// 배송지역 국가타입
								$ship_zone['area_nation_type'] = $nation;
								// 배송지역 상세 주소 join
								$ship_zone['area_detail_address_join'] = $address;
								// 배송지역 상세 지번주소
								$ship_zone['area_detail_address_zibun'] = $_POST['sel_address_zibun'][$nation][$setKey][$set_type][$costKey][$zoneKey];
								// 배송지역 상세 도로명주소
								$ship_zone['area_detail_address_street'] = $_POST['sel_address_street'][$nation][$setKey][$set_type][$costKey][$zoneKey];
								// 배송지역 상세 주소 Full text
								$ship_zone['area_detail_address_txt'] = $_POST['sel_address_txt'][$nation][$setKey][$set_type][$costKey][$zoneKey];

								// ## fm_shipping_area_detail Save
								$this->shippingmodel->set_shipping_zone($ship_zone);

								if(!$shipping_opt_seq){
									// 배송그룹 롤백
									$this->shippingmodel->del_shipping_group($shipping_group_seq);
									// 배송설정 롤백
									$this->shippingmodel->del_shipping_set($shipping_set_seq);
									// 배송방법 롤백
									$this->shippingmodel->del_shipping_opt($shipping_opt_seq);
									// 배송금액 롤백
									$this->shippingmodel->del_shipping_cost($shipping_cost_seq);
									openDialogAlert($fail_msg.'<br/>ErrCode: 06',400,140,'parent',$callback);
									exit;
								}
								// ### 배송지역 상세 저장 :: END
							} // END 배송지역 상세 LOOP
						}
						// ### 배송금액 저장 :: END
					} // END 배송금액 LOOP
				} // END 구간설정 LOOP

				// -요약- 배송여부
				if($nation == 'korea')	$grp_sum['kr_'.$set_code.'_yn'] = 'Y';
				else					$grp_sum['gl_'.$set_code.'_yn'] = 'Y';

				// -요약- 기본 배송타입 정의 :: 2017-02-15 lwh
				if($ship_set['default_yn'] == 'Y'){
					if($set_type == 'std' && !$grp_sum['default_type']){
						$grp_sum['max_cost']		= ${$set_type.'_max_cost'};
						$grp_sum['min_cost']		= ${$set_type.'_min_cost'};

						if(${$set_type.'_min_cost'} > 0)
											$grp_sum['default_type']	= 'ifpay';
						else				$grp_sum['default_type']	= 'iffree';
					}else if($set_type == 'add'){
						$grp_sum['add_max_cost']	= ${$set_type.'_max_cost'};
						$grp_sum['add_min_cost']	= ${$set_type.'_min_cost'};
					}

					$grp_sum['default_nation']		= ($nation=='korea') ? 'kr' : 'gl';
					$grp_sum['default_set_code']	= $set_code;
				}

				// -배송안내- 자동안내 내용 요약
				if($ship_set['delivery_'.$set_type.'_type'] == 'Y' && is_numeric($set_sum[$set_type]['cost'][0]) && $set_sum[$set_type]['cost'][0] >= 0){
					unset($strParams);
					$strParams['nation']	= $nation;
					$strParams['kind']		= $set_type;
					$strParams['type']		= $ship_opt['shipping_opt_type'];
					$strParams['st']		= $section_st_arr;
					$strParams['ed']		= $section_ed_arr;
					$strParams['cost']		= $set_sum[$set_type]['cost'];
					$strParams['area']		= $_POST['shipping_area_name'][$nation][$setKey][$set_type];
					$strParams['tcost']		= $set_sum[$set_type]['today_cost'];
					$strParams['tcost_yn']	= $set_sum[$set_type]['shipping_today_yn'];
					$strParams['limitd']	= $_POST['delivery_limit'][$nation][$setKey];
					$strParams['limit']		= $_POST['hopeday_limit_set'][$nation][$setKey][$set_type];
					$strParams['times']		= $_POST['hopeday_limit_val'][$nation][$setKey][$set_type];
					$strParams['reserve']	= $_POST['reserve_sdate'][$nation][$setKey][$set_type];

					$auto_info_str	= $this->shippingmodel->shipping_info_str($strParams);
					$infostr['delivery_' . $set_type . '_input']	= $auto_info_str['kr'];
					if($set_type != 'hop'){
					$infostr['delivery_' . $set_type . '_input_us']	= $auto_info_str['us'];
					$infostr['delivery_' . $set_type . '_input_cn']	= $auto_info_str['cn'];
					$infostr['delivery_' . $set_type . '_input_jp']	= $auto_info_str['jp'];
					}
				}

				// 배송자동안내 UPDATE
				if($infostr){
					$infostr['shipping_set_seq']	= $shipping_set_seq;
					$this->shippingmodel->set_shipping_set($infostr);
				}
			} // END 배송설정 타입 LOOP

				// ### 매장수령 저장 :: START
			if($ship_set['store_use'] == 'Y'){
				$store_arr = $_POST['store_address_seq'][$nation][$setKey];
				foreach( $store_arr as $storeKey => $store_seq){
					// 배송설정 번호
					$ship_store['shipping_set_seq'] = $shipping_set_seq;
					// 배송지 고유번호
					$ship_store['shipping_address_seq'] = $store_seq;
					// 배송그룹 번호 삭제용
					$ship_store['shipping_group_seq_tmp'] = $shipping_group_seq;
					// 수령매장명
					$ship_store['shipping_store_name'] = $_POST['shipping_store_name'][$nation][$setKey][$storeKey];
					// 매장 전화번호
					$ship_store['store_phone'] = $_POST['store_phone'][$nation][$setKey][$storeKey];
					// 매장안내
					$ship_store['store_information'] = $_POST['store_information'][$nation][$setKey][$storeKey];
					// 창고연결여부
					$ship_store['store_scm_type'] = $_POST['store_scm_type'][$nation][$setKey][$storeKey];
					// 수령매장 재고설정
					$ship_store['store_supply_set'] = $_POST['store_supply_set'][$nation][$setKey][$storeKey];
					// 수령매장 재고보기설정
					$ship_store['store_supply_set_view'] = $_POST['store_supply_set_view'][$nation][$setKey][$storeKey];
					// 수령매장 재고수량설정
					$ship_store['store_supply_set_order'] = $_POST['store_supply_set_order'][$nation][$setKey][$storeKey];

					// 수령매장 타입
					$ship_store['store_type'] = $_POST['store_type'][$nation][$setKey][$storeKey];
					// 수령매장 창고 고유키
					$ship_store['store_scm_seq'] = $_POST['store_scm_seq'][$nation][$setKey][$storeKey];

					// ## fm_shipping_option Save
					$shipping_store_seq = $this->shippingmodel->set_shipping_store($ship_store);

					if(!$shipping_store_seq){
						// 배송그룹 롤백
						$this->shippingmodel->del_shipping_group($shipping_group_seq);
						// 배송설정 롤백
						$this->shippingmodel->del_shipping_set($shipping_set_seq);
						openDialogAlert($fail_msg.'<br/>ErrCode: 03',400,140,'parent',$callback);
						exit;
					}
				}

				// -요약- 매장수령여부
				if($nation == 'korea')	$grp_sum['kr_direct_store_yn'] = 'Y';
				else					$grp_sum['gl_direct_store_yn'] = 'Y';

				// -요약- 매장수령 무료배송여부
				if($ship_set['default_yn'] == 'Y'){
					$grp_sum['free_shipping_use']	= 'Y';
					$grp_sum['free_set_code']		= 'direct_store';
					$grp_sum['default_type']		= 'free';
					$grp_sum['min_cost']			= '0';
					$grp_sum['max_cost']			= '0';
				}
			}
			// ### 매장수령 저장 :: END

			// -요약- 배송여부
			if($nation == 'korea')	$grp_sum['kr_shipping_yn'] = 'Y';
			else					$grp_sum['gl_shipping_yn'] = 'Y';
			} // END 배송설정 LOOP
		} // END 국가별 LOOP

		// ## fm_shipping_group_summary Save
		$this->shippingmodel->set_shipping_group_summary($grp_sum);
		$this->shippingmodel->del_shipping_dummy($shipping_group_seq, $set_seqs, $cost_seqs);

		// file cache 삭제
		cache_clean('shipping_refund_address');

		// dummy 데이터 최종 점검
		$inspect_data = [
			'shipping_group_seq' => $shipping_group_seq,
		];
		$this->shippingmodel->inspect_shipping_data($inspect_data);

		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			openDialogAlert('배송그룹 생성중 오류가 발생했습니다.',400,140,'parent',$callback);
			exit;
		} else {
			//debug_var($this->db);exit;
			// 저장완료
			$this->db->trans_commit();

			if($reg_mode == 'save') {
				$suc_callback	= 'parent.location.href="./shipping_group_regist?shipping_group_seq='.$shipping_group_seq.'"';
			} else {
				$suc_callback	= 'parent.document.location.reload();';
			}

			// 기본배송그룹 저장 시 가비아ads(google/putShippingSetup) 전송
			if ($this->input->post('base_grp') == 'Y') {
				echo js('parent.put_ads_shipping();');
			}
			openDialogAlert($mode_msg.'이 완료되었습니다.',400,140,'parent',$suc_callback);
		}
	}

	// 배송그룹 선택삭제 :: 2016-06-16 lwh
	public function rm_shipping_group(){
		$this->load->model('shippingmodel');

		$grp_seq = $_POST['grp_seq'];
		foreach($grp_seq as $k => $seq){
			$res = $this->shippingmodel->del_shipping_group($seq);
			if(!$res){
				$return['res'] = false;
				$return['msg'] = '배송그룹삭제에 실패했습니다.<br/>새로고침 후 다시 시도하여주세요.';
				echo json_encode($return);
				exit;
			}
		}

		// file cache 삭제
		cache_clean('shipping_refund_address');

		$return['res'] = true;
		$return['msg'] = '배송그룹이 삭제되었습니다.';
		echo json_encode($return);
		exit;
	}

	// 택배사 설정 저장 :: 2016-08-17 lwh
	public function save_delivery_company(){

		if	(count($_POST['selected_delivery_company']) > 0){
			$deliveryCompanyCode	= serialize($_POST['selected_delivery_company']);
			if	($_POST['provider_seq'] > 1){
				config_save('providerDeliveryCompanyCode', array($_POST['provider_seq'] => $deliveryCompanyCode));
			}else{
				config_save('shippingdelivery', array('deliveryCompanyCode' => $deliveryCompanyCode));
			}
			$callback	= 'parent.location.reload();';
			openDialogAlert("저장되었습니다.", 400, 150, 'parent', $callback);
			exit;
		}else{
			$callback	= 'parent.location.reload();';
			openDialogAlert("선택된 택배사가 없습니다.", 400, 150, 'parent', $callback);
			exit;
		}
	}

	// 배송안내 문구 설정
	public function msg_modify(){
		if($_POST['language_set']){
			$language = $_POST['language_set'];
			foreach($_POST['msgarr'] as $k => $msg){
				$code = 'dv' . str_pad($k, 3, '0', STR_PAD_LEFT);
				$this->db->where('code', $code);
				$this->db->update('fm_alert', array($language=>$msg));
			}
			$callback = "parent.closemsg();";
			openDialogAlert("문구가 설정되었습니다.", 400, 150, 'parent', $callback);
		}else{
			openDialogAlert("설정에 실패했습니다.", 400, 150, 'parent', $callback);
		}
	}

	// 배송그룹 복사
	public function copyShippingGroup(){
		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_shipping_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		$this->load->model('shippingmodel');
		$oldGroupSeq = $this->input->post('group_seq');
		if(!$oldGroupSeq){
			$return['result'] = false;
			$return['msg'] = "배송그룹이 선택되지 않았습니다.";
			echo json_encode($return);
			exit;
		}

		$this->db->trans_begin();

		$fail_msg = '배송그룹 복사에 실패하였습니다.<br/>새로고침 후 다시 시도해주세요.';

		// 배송그룹 가져오기
		$ship_grp = array();
		$ship_grp = $this->shippingmodel->get_shipping_group($oldGroupSeq);
		// 배송그룹 고유번호 초기화
		unset($ship_grp['shipping_group_seq']);
		// 시스템 메모 초기화
		$mode_msg = $oldGroupSeq . "번 배송그룹 복사";
		$ship_grp['system_memo'] = $nowDate . ' ' . $this->managerInfo['mname'] . '(' . $this->managerInfo['manager_id'] . ') ' . $mode_msg . ' [' . $_SERVER['REMOTE_ADDR'] . ']';
		// 날짜 초기화
		$ship_grp['regist_date'] = $ship_grp['update_date'] = date('Y-m-d H:i:s');
		// 상품갯수 초기화
		$ship_grp['target_package_cnt']	= 0;
		$ship_grp['target_goods_cnt']	= 0;
		$ship_grp['trust_goods_cnt']	= 0;
		// 기본배송지 초기화
		$ship_grp['default_yn'] = 'N';

		// 배송그룹 저장
		$shipping_group_seq = $this->shippingmodel->set_shipping_group($ship_grp);
		if(!$shipping_group_seq){
			$return['result'] = false;
			$return['msg'] = $fail_msg.'<br/>copyErrCode: 01';
			echo json_encode($return);
			exit;
		}

		// 배송설정 가져오기
		$ship_set_arr = array();
		$ship_set_arr = $this->shippingmodel->get_shipping_set($oldGroupSeq);
		foreach($ship_set_arr as $ship_set){
			$oldShipSetSeq = $ship_set['shipping_set_seq'];
			unset($ship_set['shipping_set_seq']);
			$ship_set['shipping_group_seq'] = $shipping_group_seq;
			// 배송설정 저장
			$shipping_set_seq = $this->shippingmodel->set_shipping_set($ship_set);
			if(!$shipping_set_seq){
				$return['result'] = false;
				$return['msg'] = $fail_msg.'<br/>copyErrCode: 02';
				echo json_encode($return);
				exit;
			}

			// 배송방법 가져오기
			$ship_opt_arr = array();
			$ship_opt_arr = $this->shippingmodel->get_shipping_opt($oldShipSetSeq,"shipping_set_seq");
			foreach($ship_opt_arr as $ship_opt){
				$oldShipOptSeq = $ship_opt['shipping_opt_seq'];
				unset($ship_opt['shipping_opt_seq']);
				$ship_opt['shipping_group_seq'] = $shipping_group_seq;
				$ship_opt['shipping_set_seq'] = $shipping_set_seq;
				// 배송방법 저장
				$shipping_opt_seq = $this->shippingmodel->set_shipping_opt($ship_opt);
				if(!$shipping_opt_seq){
					$return['result'] = false;
					$return['msg'] = $fail_msg.'<br/>copyErrCode: 04';
					echo json_encode($return);
					exit;
				}

				// 배송금액 가져오기
				$ship_cost_arr = array();
				$ship_cost_arr = $this->shippingmodel->get_shipping_cost($oldShipOptSeq,"shipping_opt_seq");
				foreach($ship_cost_arr as $ship_cost){
					$oldShipCosSeq = $ship_cost['shipping_cost_seq'];
					unset($ship_cost['shipping_cost_seq']);
					$ship_cost['shipping_opt_seq'] = $shipping_opt_seq;
					$ship_cost['shipping_group_seq_tmp'] = $shipping_group_seq;
					// ## 배송금액 저장
					$shipping_cost_seq = $this->shippingmodel->set_shipping_cost($ship_cost);
					if(!$shipping_cost_seq){
						$return['result'] = false;
						$return['msg'] = $fail_msg.'<br/>copyErrCode: 05';
						echo json_encode($return);
						exit;
					}

					// 배송지역 가져오기
					$ship_zone_arr = array();
					$ship_zone_arr = $this->shippingmodel->get_shipping_zone($oldShipCosSeq,"shipping_cost_seq");
					foreach($ship_zone_arr as $ship_zone){
						$oldShipZonSeq = $ship_zone['area_detail_seq'];
						unset($ship_zone['area_detail_seq']);
						$ship_zone['shipping_cost_seq'] = $shipping_cost_seq;
						$ship_zone['shipping_group_seq_tmp'] = $shipping_group_seq;
						// 배송지역 저장
						$shipping_zone_seq = $this->shippingmodel->set_shipping_zone($ship_zone);
						if(!$shipping_zone_seq){
							$return['result'] = false;
							$return['msg'] = $fail_msg.'<br/>copyErrCode: 06';
							echo json_encode($return);
							exit;
						}
					}
				}
			}

			// 수령매장 가져오기
			$ship_store_arr = array();
			$ship_store_arr = $this->shippingmodel->get_shipping_store($oldShipSetSeq,'shipping_set_seq');
			foreach($ship_store_arr as $ship_store){
				$oldShipStoSeq = $ship_store['shipping_store_seq '];
				unset($ship_store['shipping_store_seq']);
				$ship_store['shipping_set_seq'] = $shipping_set_seq;
				$ship_store['shipping_group_seq_tmp'] = $shipping_group_seq;
				// 수령매장 저장
				$shipping_store_seq = $this->shippingmodel->set_shipping_store($ship_store);
				if(!$shipping_store_seq){
					$return['result'] = false;
					$return['msg'] = $fail_msg.'<br/>copyErrCode: 03';
					echo json_encode($return);
					exit;
				}
			}
		}

		// 배송요약 가져오기
		$grp_sum = "";
		$grp_sum = $this->shippingmodel->get_shipping_group_summary($oldGroupSeq);
		unset($grp_sum['shipping_summary_seq']);
		unset($grp_sum['default_type_txt']);
		$grp_sum['shipping_group_seq'] = $shipping_group_seq;
		// 배송요약 저장
		$group_summary_seq = $this->shippingmodel->set_shipping_group_summary($grp_sum);
		if(!$group_summary_seq){
			$return['result'] = false;
			$return['msg'] = $fail_msg.'<br/>copyErrCode: 07';
			echo json_encode($return);
			exit;
		}
		if($this->db->trans_status() === FALSE){
			$this->db->trans_rollback();
			$return['res'] = false;
			$return['msg'] = '배송그룹 복사중 오류가 발생했습니다.';
			echo json_encode($return);
			exit;
		}else{
			// file cache 삭제
			cache_clean('shipping_refund_address');

			// 저장완료
			$this->db->trans_commit();
			$return['res'] = true;
			$return['msg'] = '등록되었습니다.';
			echo json_encode($return);
			exit;
		}
	}

	public function instagramConfThum(){
		$aSaveParams['thumSize']	= $this->input->post('thumSize');
		$aSaveParams['thumNumber']	= $this->input->post('thumNumber');
		$aSaveParams['thumCell']	= $this->input->post('thumCell');
		$aSaveParams['thumRow']		= $this->input->post('thumRow');
		$aSaveParams['thumPdl']		= $this->input->post('thumPdl');
		$aSaveParams['thumPdt']		= $this->input->post('thumPdt');
		if($aSaveParams['thumNumber'] > 30)	$aSaveParams['thumNumber']	= 30;

		config_save('instargramThumb', $aSaveParams);
		echo js("parent.closeDialog('instagramConfThum_popup');");
		openDialogAlert("설정이 저장 되었습니다.", 400, 150, 'parent', '');
	}

	public function manualViewClose(){
		$ManualView = $this->input->post("manual_check");
		if($ManualView == "Y"){
			config_save('basic',array('manual_view'=>$ManualView));
		}else{
			echo json_encode('err');
			exit;
		}
		echo json_encode('ok');
	}

	// 카카오싱크 연동 요청
	public function kakaosyncConnect()
	{
		$this->load->library('AdditionService/kakaosync/Client');
		//사업자정보 유효성 검사
		$result = $this->client->validBusinessData();

		if($result['success'] != true) {
			echo json_encode($result);
			exit;
		}

		echo json_encode($this->client->kakaosyncJoin());
	}

	// 카카오싱크 연동 키 불러오기
	public function kakaosyncConf()
	{
		$snssocial = config_load('snssocial');
		echo json_encode(['key_k' => $snssocial['key_k'], 'rest_key_k' =>  $snssocial['rest_key_k']]);
	}

	// 카카오싱크 연동해제 요청
	public function kakaosyncDisconnect()
	{
		$this->load->library('AdditionService/kakaosync/Client');
		$result = $this->client->kakaosyncTerminate();

		echo json_encode($result);
	}

	// 인스타그램 연동 요청
	public function instagram()
	{
		$this->load->library('AdditionService/instagram/Client');
		echo json_encode($this->client->instagramJoin());
	}

	// 인스타그램 계정 연동
	public function instagramConnect()
	{
		$aPostParams = $this->input->post();
		$aPostParams['expires'] = $aPostParams['expires'] + time();
		$aPostParams['use'] = 'Y';

		$this->load->library('instagramlibrary');

		// 인스타그램 연동 설정 저장
		$this->instagramlibrary->setConfig($aPostParams);

		// 인스타그램 피드 저장
		$this->instagramlibrary->createFeed();
	}

	// 인스타그램 연동 해제
	public function instagramDisconnect()
	{
		$aPostParams = $this->input->post();

		$this->load->library('instagramlibrary');
		$instagram = $this->instagramlibrary->getConfig();

		$username = $aPostParams['username'] ?: $instagram['username'];

		$this->load->model('instagramfeedmodel');

		// 인스타그램 연동 설정 삭제
		config_delete('instagram');

		// 인스타그램 피드 삭제
		$this->instagramfeedmodel->deleteFeed($username);
	}

	// 인스타그램 피드 노출 설정 저장
	public function instagramFeedConfSave()
	{
		$aPostParams = $this->input->post();

		// DB 디자인 스킨 값 없는 경우 값 설정
		$this->config_system['operation_type'] = $this->config_system['operation_type'] ?? "heavy";

		if ($this->config_system['operation_type'] != $aPostParams['operation_type']) {
			$callback = 'parent.document.location.reload();';
			$msg = '스킨설정이 변경되었습니다. 새로고침 후 다시 시도해주세요';
			openDialogAlert($msg, 400, 160, 'parent', $callback);
			exit;
		}

		if ($this->config_system['operation_type'] === 'light') {
			$this->validation->set_rules('feed_cell_resp', '가로 개수', 'required|trim|is_natural|xss_clean');
			$this->validation->set_rules('feed_count_resp', '노출 총 개수', 'required|trim|is_natural|xss_clean');
		} else {
			$this->validation->set_rules('feed_cell', '가로 개수', 'required|trim|is_natural|xss_clean');
			$this->validation->set_rules('feed_row', '세로 개수', 'required|trim|is_natural|xss_clean');
			$this->validation->set_rules('feed_pdl', '썸네일 간격', 'required|trim|is_natural|xss_clean');
			$this->validation->set_rules('feed_pdt', '썸네일 행 간격', 'required|trim|is_natural|xss_clean');
		}

		if ($this->validation->exec() === false) {
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'], 400, 160, 'parent', $callback);
			exit;
		}

		if ($this->config_system['operation_type'] === 'light') {
			$param['feed_cell_resp'] = $aPostParams['feed_cell_resp'];
			$param['feed_count_resp'] = $aPostParams['feed_count_resp'];

			if ($param['feed_cell_resp'] > 30) {
				$msg = '가로 개수를 30개 이하로 설정해주세요.';
				$callback = "parent.document.getElementsByName('feed_cell_resp')[0].focus();";
				openDialogAlert($msg, 400, 160, 'parent', $callback);
				exit;
			}

			if ($param['feed_cell_resp'] > $param['feed_count_resp']) {
				$msg = '가로 개수가 노출 총 개수보다 적게 설정해주세요.';
				$callback = "parent.document.getElementsByName('feed_cell_resp')[0].focus();";
				openDialogAlert($msg, 400, 160, 'parent', $callback);
				exit;
			}

			if ($param['feed_count_resp'] > 30) {
				$msg = '노출 총 개수를 30개 이하로 설정해주세요.';
				$callback = "parent.document.getElementsByName('feed_count_resp')[0].focus();";
				openDialogAlert($msg, 400, 160, 'parent', $callback);
				exit;
			}
		} else {
			$param['feed_cell'] = $aPostParams['feed_cell'];
			$param['feed_row'] = $aPostParams['feed_row'];
			$param['feed_pdl'] = $aPostParams['feed_pdl'];
			$param['feed_pdt'] = $aPostParams['feed_pdt'];

			if ($param['feed_cell'] * $param['feed_row'] > 30) {
				$msg = '노출 총 개수를 30개 이하로 설정해주세요.';
				$callback = "parent.document.getElementsByName('feed_cell')[0].focus();";
				openDialogAlert($msg, 400, 160, 'parent', $callback);
				exit;
			}
		}

		config_save('instagramFeed', $param);

		$callback = 'parent.document.location.reload();';
		openDialogAlert('설정이 저장 되었습니다.', 400, 140, 'parent', $callback);
	}

	public function file_cache()
	{
		$file_cache = $this->input->post('file_cache');

		if ($file_cache == 'N') {
			$this->cache->clean();
		}

		config_save('system', array('file_cache' => $file_cache));
		openDialogAlert("설정이 저장 되었습니다.", 400, 150, 'parent', '');
	}

	public function file_cache_delete()
	{
		$this->cache->clean();
		openDialogAlert("CACHE 삭제되었습니다.", 400, 150, 'parent', '');
	}
}

/* End of file setting_process.php */
/* Location: ./app/controllers/admin/setting_process.php */