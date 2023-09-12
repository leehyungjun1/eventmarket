<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class marketing_process extends admin_base {

	public function __construct()
	{
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('admin/setting');
	}

	### 가입
	public function marketplace()
	{
		$aPostParams	= $this->input->post();

		/* 관리자 권한 체크 : 시작 */
		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('marketplace_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		if( in_array($aPostParams['navercheckout_use'],array('test','y')) && $aPostParams['naver_wcs_use']!='y' )
		{
		    $callback = "window.parent.$(\"input[name='naver_wcs_use'][value='y']\").focus();";
			openDialogAlert("네이버 공통 인증을 사용으로 설정해주세요.",400,140,'parent',$callback);
			exit;
		}

		### 네이버 공통인증 설정 저장
		config_save('basic',array('naver_wcs_use'=>$aPostParams['naver_wcs_use']));

		$config_param['accountId'] = trim($aPostParams['naver_wcs_accountid']);
		$config_param['checkoutWhitelist'] = array();
		foreach($aPostParams['checkoutWhitelist'] as $v){
			if(trim($v)){
				$config_param['checkoutWhitelist'][] = $v;
			}
		}
		config_save('naver_wcs',$config_param);

		### 네이버 체크아웃 설정 저장
		$config_param = array();
		$config_param['version'] 	= trim($aPostParams['navercheckout_ver']);
		$config_param['use'] 		= trim($aPostParams['navercheckout_use']);
		$config_param['shop_id'] 	= trim($aPostParams['navercheckout_shop_id']);
		$config_param['certi_key'] 	= trim($aPostParams['navercheckout_certi_key']);
		$config_param['button_key'] = trim($aPostParams['navercheckout_button_key']);
		$config_param['culture'] = trim($aPostParams['navercheckout_culture']);
		$config_param['except_category_code'] = array();
		foreach($aPostParams['issueCategoryCode'] as $value){
			$config_param['except_category_code'][] = array('category_code'=>$value);
		}
		$config_param['except_goods'] = array();
		foreach($aPostParams['except_goods'] as $value){
			$config_param['except_goods'][] = array('goods_seq'=>$value);
		}
		$config_param['culture_goods'] = array();
		if($config_param['culture']=='choice') {
			foreach($aPostParams['culture_goods'] as $value){
				$config_param['culture_goods'][] = array('goods_seq'=>$value);
			}
		}
		if( $config_param['use'] == 'y' || $config_param['use'] == 'test'){
			if( !$config_param['shop_id'] ){
				openDialogAlert("상점 ID 필수 입니다.",400,140,'parent',$callback);
				exit;
			}
			if( !$config_param['certi_key'] ){
				openDialogAlert("상점 인증키 필수 입니다.",400,140,'parent',$callback);
				exit;
			}
			if( !$config_param['button_key'] ){
				openDialogAlert("버튼키는 필수 입니다.",400,140,'parent',$callback);
				exit;
			}
		}

		# Npay 2.1 사용 설정(상품연동 2.1 / 주문연동 5.0)
		if($aPostParams['navercheckout_ver'] == "2.1"){

			# Npay 현재 사용 버전
			$old_navercheckout = config_load('navercheckout');
			if(!$old_navercheckout['version'] && in_array($old_navercheckout['use'],array("y","test"))){
				$old_navercheckout_version = "1.0";
			}else{
				$old_navercheckout_version = $old_navercheckout['version'];
			}

			if(in_array($aPostParams['navercheckout_use'],array("y","test"))){

				# Npay에서 사용될 함수 체크
				$func_chk = function_exists('hash_hmac') ;
				if(!$func_chk){
					return array("result"=>"ERROR","message"=>"<span style=\'color:red;\'>Npay 사용이 불가한 환경입니다.<br />퍼스트몰에 문의해 주세요.</span>");
					exit;
				}

				# 중계서버에 가맹점 세팅 정보 확인
				$npay_chk = $this->npay_use_chk('npay_shop_upgrade',$old_navercheckout_version);

				if($npay_chk['result'] == "ERROR"){
					openDialogAlert($npay_chk['message'],350,160,'parent');
					exit;
				}
				if(!$old_navercheckout['npay_btn_pc_goods']) $config_param['npay_btn_pc_goods']		= 'A-1-2-236×88';
				if(!$old_navercheckout['npay_btn_mobile_goods']) $config_param['npay_btn_mobile_goods']	= 'MA-1-2-290×85';

			}

			# Npay 2.1 사용여부 중계서버에 업데이트
			if($old_navercheckout_version == "2.1" && $aPostParams['navercheckout_use'] != $old_navercheckout['use']){
				$old_navercheckout['new_useyn'] = $aPostParams['navercheckout_use'];
				$npay_chk = $this->npay_use_chk('npay_status_update',$old_navercheckout_version);
				# firstmall 관리 서버로 전송
				$this->naverpay_firstmall_apply("useyn",$old_navercheckout);
			}

		}

		config_save('navercheckout',$config_param);

		/**
		 * 카카오페이 설정 저장하기 시작
		 */
		$this->load->library("talkbuylibrary");
		$this->talkbuylibrary->save_talkbuy_config($aPostParams);
		/**
		 * 카카오페이 설정 저장하기 종료
		 */

		# Npay 2.1 사용 설정 시 네이버페이 전용 문의게시판 생성
		if($aPostParams['navercheckout_ver'] == "2.1" && $aPostParams['navercheckout_use'] == "y"){
			$this->load->model("Boardmanager");
			$params 	= array('board_id'=>'naverpay_qna','board_name'=>'네이버페이문의');
			$qna_res	= $this->Boardmanager->set_partner_board_create($params);
		}

		# npay 필드 네이버마일리지 설정에서 제외
		$config_param['use']								= "";
		$config_param['version']							= "";
		$config_param['npay_btn_pc_goods']		= "";
		$config_param['npay_btn_mobile_goods']	= "";

		$config_param['naver_mileage_yn']		= $aPostParams['naver_mileage_yn'];
		$config_param['naver_mileage_api_id'] = $aPostParams['naver_mileage_api_id'];
		$config_param['naver_mileage_secret']	= $aPostParams['naver_mileage_secret'];
		if( $aPostParams['naver_mileage_test']) $config_param['naver_mileage_test']	= $aPostParams['naver_mileage_test'];

		if( $config_param['naver_mileage_yn'] == 'y' ){
			if( !$aPostParams['naver_mileage_api_id'] || !$aPostParams['naver_mileage_secret'] ){
				openDialogAlert("외부인증아이디와 인증키는 필수 입니다.",400,140,'parent',$callback);
				exit;
			}
		}

		### 설정저장
		config_save('naver_mileage',$config_param);

		### 마케팅 네이버 및 다음 파일 생성 사용 여부 저장 :: 2015-12-03 lwh
		$aPostParams['naver_use'] && config_save('basic',array('naver_use'=>$aPostParams['naver_use']));
		$aPostParams['naver_third_use'] && config_save('basic',array('naver_third_use'=>$aPostParams['naver_third_use']));
		config_save('basic',array('daum_use'=>$aPostParams['daum_use']));

		### 미사용시 기존 파일삭제 :: 2015-12-09 lwh
		if($aPostParams['naver_use']=='N'){ # EP 2.0
			$naver_file_path	= ROOTPATH."/ep/naver_all.txt";
			if(is_file($naver_file_path)==true)	unlink($naver_file_path);
			config_save('partner',array('naver_file_time'=>null, 'naver_file_size'=>null));
			config_save('partner',array('naver_update'=>null));
		}
		if($aPostParams['naver_third_use']=='N'){ # EP 3.0
			$naver_file_path	= ROOTPATH."/ep/naver_third_all.tsv";
			if(is_file($naver_file_path)==true)	unlink($naver_file_path);
			config_save('partner',array('naver_third_file_time'=>null, 'naver_third_file_size'=>null));
			config_save('partner',array('naver_third_update'=>null));
		}
		if($aPostParams['daum_use']=='N'){
			$daum_file_path	= ROOTPATH."/ep/daum_all.txt";
			if(is_file($daum_file_path)==true)	unlink($daum_file_path);
			config_save('partner',array('daum_file_time'=>null, 'daum_file_size'=>null));
			config_save('partner',array('daum_update'=>null));

			$review_file_path	= ROOTPATH."/ep/review_all.txt";
			if(is_file($review_file_path)==true)unlink($review_file_path);
			config_save('partner',array('daum_review_file_time'=>null, 'daum_review_file_size'=>null));
			config_save('partner',array('daum_review_update'=>null));
		}

		### 마케팅 페이스북 픽셀 ID 및 사용 여부 저장
		$facebook_param['facebook_pixel'] = $aPostParams['facebook_pixel_id'];
		$facebook_param['facebook_pixel_use'] = $aPostParams['facebook_pixel_use'];
		config_save('system',$facebook_param);

		$google_param['google_feed_use'] = $aPostParams['google_feed_use'];
		config_save('system',$google_param);

		### google site verification token
		// config_save('partner',array('google_verification_token'=>$aPostParams['google_verification_token']));

		###
		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	### 공통설정 :: 상품 DB URL 통합 설정 분리
	public function marketplace_dburl()
	{
		$aPostParams	= $this->input->post();

		/* 관리자 권한 체크 : 시작 */
		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('marketplace_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		### 입점마케팅 전달 데이터 통합 설정 - 입점마케팅 상품명,카드무이자할부 leewh 2015-01-29
		### 배송데이터 추가 :: 2017-02-22 lwh
		$config_feed = array();
		$config_feed['goods_name'] = ($aPostParams['feed_goods_name'])? $aPostParams['feed_goods_name'] : '';
		$config_feed['brand_kind']		= ($aPostParams['feed_brand_kind'])? $aPostParams['feed_brand_kind'] : 'brand';
		$config_feed['cfg_card_free']	= ($aPostParams['cfg_card_free'])? $aPostParams['cfg_card_free'] : '';
		$config_feed['feed_pay_type']	= ($aPostParams['feed_pay_type'])	? $aPostParams['feed_pay_type']	: '';

		### 전달이미지 설정 lwh 2014-02-28
		$config_image['daumImage']	= $aPostParams['daumImage'];
		$config_image['naverImage']	= $aPostParams['naverImage'];
		config_save('marketing_image',$config_image);

		//페이스북 피드 관련 착불 배송비 데이터를 받기 위해 아래 설정 추가
		//DB 테이블 추가를 피하기 위해 고정 배송비 필드에 착불 배송비 값을 입력 함
		//데이터 구분은 feed_pay_type를 이용하기 바람 19.05.30 kmj
		if ($config_feed['feed_pay_type'] == "fixed") {
			$config_feed['feed_std_fixed'] 		= ($aPostParams['feed_std_fixed'])? $aPostParams['feed_std_fixed'] : '';
			$config_feed['feed_std_postpay'] 	= 0;
		} else if ($config_feed['feed_pay_type'] == "postpay") {
			$config_feed['feed_std_postpay'] 	= ($aPostParams['feed_std_postpay'])? $aPostParams['feed_std_postpay'] : '';
			$config_feed['feed_std_fixed'] 		= 0;
		} else {
			$config_feed['feed_std_postpay'] 	= 0;
			$config_feed['feed_std_fixed'] 		= 0;
		}
		$config_feed['feed_add_txt'] = ($aPostParams['feed_add_txt']) ? $aPostParams['feed_add_txt'] : '';
		config_save('marketing_feed',$config_feed);

		### 공통설정 :: 입점마케팅 상품 추가할인
		$config_sale = array();
		$config_sale['member'] = ($aPostParams['marketing_sale_member']=="Y") ? "Y" : "N";
		$config_sale['referer'] = ($aPostParams['marketing_sale_referer']=="Y") ? "Y" : "N";
		$config_sale['coupon'] = ($aPostParams['marketing_sale_coupon']=="Y") ? "Y" : "N";
		$config_sale['mobile'] = ($aPostParams['marketing_sale_mobile']=="Y") ? "Y" : "N";
		$config_sale['member_sale_type'] = ($aPostParams['member_sale_type']=="1") ? "1" : "0";
		config_save('marketing_sale',$config_sale);

		### google site verification token
		// config_save('partner',array('google_verification_token'=>$aPostParams['google_verification_token']));

		###
		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function login()
	{

		$this->load->model('marketingAdminModel');

		$vcode  	= $this->input->post('vcode');
		$id  		= $this->input->post('id');
		$pw  		= $this->input->post('pw');

		$out 		= $this->marketingAdminModel->get_id_pw();
		$md5_str 	= md5($id.$pw);

		if($out != $md5_str){
			openDialogAlert("로그인 실패",400,140,'parent');
			exit;
		}

		$this->marketingAdminModel->set_session_login($vcode);

		if($vcode == 'navercheckout'){
			pageRedirect("/main/index", "", "parent");
		}else{
			pageRedirect("/admin/marketing/marketplace_url", "", "parent");
		}
	}


	### 다음쇼핑하우 입점신청
	public function marketplace_daumshopping_process(){

		$this->load->helper('readurl');

		// 로고 업로드
		list($daumshopping_logo1,$daumshopping_logo2) = $this->setting->upload_daumshopping_logo();
		config_save('system',array('daumshopping_logo1'=>$daumshopping_logo1));
		config_save('system',array('daumshopping_logo2'=>$daumshopping_logo2));

		$params = $this->input->post();
		$params['regip']		= $_SERVER['REMOTE_ADDR'];
		$params['url']			= get_connet_protocol().$this->config_system['domain'];
		$params['shopSno']		= $this->config_system['shopSno'];
		$params['logoimg1']		= get_connet_protocol().$this->config_system['domain'].$daumshopping_logo1;
		$params['logoimg2']		= get_connet_protocol().$this->config_system['domain'].$daumshopping_logo2;

		unset($_FILES);		// _FILES 목록도 넘겨서 unset 2018-05-31
		$result = readurl(get_connet_protocol()."interface.firstmall.kr/firstmall_plus/request.php?cmd=daumshopping_apply_process",$params);
		$result = unserialize($result);

		if($result['code']=='succ'){
			$callback = "parent.document.location.replace('/admin/marketing/marketplace_url')";
			openDialogAlert($result['msg'],400,140,'parent',$callback);
			exit;
		}else{
			if($result['msg']){
				openDialogAlert($result['msg'],400,140,'parent',$result['callbackScript']);
				exit;
			}else{
				openDialogAlert("알수없는 통신 장애입니다.<br />가비아 퍼스트몰 고객센터로 문의해주세요",400,150,'parent',$result['callbackScript']);
				exit;
			}
		}

	}

	## 네이버페이 버튼 노출 설정 저장 @2016-01-29
	public function npay_btn_style(){

		$style_code 	= $this->input->post('style_code');
		$mode 			= $this->input->post('mode');

		if($style_code){
			### 네이버 공통인증 설정 저장
			$code		= explode("-",$style_code);
			$style_text = $code[0]."-".$code[1] . " 타입";
			$size		= explode("×",$code[3]);

			/**
			 * 네이버 페이 버튼 통합 (상품상세, 장바구니)
			 * 기존에는 1,2 구분되었지만 신규 저장코드는 모두 0으로 저장한다.
			 * $code[2] = 0 저장한다.
			 */
			$npay_btn["npay_btn_".$mode] = $style_code;

			config_save('navercheckout',$npay_btn);

			$callback = 'parent.lay_npay_close("'.$mode.'","'.$style_text.'","'.$size[1].'");';
			openDialogAlert("저장 되었습니다.",400,160,'parent',$callback);
			exit;

		}else{
			openDialogAlert("타입을 선택해주세요.",400,160,'parent');
			exit;

		}

	}

	# 중계서버에 설정된 가맹점 정보 체크 및 사용여부 업데이트
	public function npay_use_chk($mode,$old_navercheckout_version){

		$targetUrl	= "https://npayapi.firstmall.kr/npay/npay_status_check.php";

		$naverpay_mall_id 		= $this->input->post('naverpay_mall_id');
		$navercheckout_shop_id 	= $this->input->post('navercheckout_shop_id');
		$navercheckout_use 		= $this->input->post('navercheckout_use');

		$mallid					= ($naverpay_mall_id)? $naverpay_mall_id : $navercheckout_shop_id;

		$shopName 				= $this->config_system['admin_env_name'];
		if(!$shopName) $shopName = $this->config_basic['shopName'];

		$params		= array("mode"		=> $mode,
						"shopSno"		=> $this->config_system['shopSno'],
						"companyname"	=> $shopName,
						"mallid"		=> $mallid,
						"npay_status"	=> $navercheckout_use);

		if($mode == "npay_shop_upgrade"){

			# 현재 사용중인 버전이 1.0 이면 업그레이드 신청
			if($old_navercheckout_version == "1.0"){
				$gubun		= "upgrade";
				$status		= "apply";
			# 현재 사용중인 버전이 없다면. 신규 등록
			}elseif(!trim($old_navercheckout_version)){
				$gubun		= "new";
				$status		= "complete";
			}

			$params_sub		= array("mode"		=> $mode,
								"userid"		=> $this->config_system['service']['cid'],
								"domain"		=> $this->config_system['domain'],
								"subDomain"		=> $this->config_system['subDomain'],
								"companyname"	=> $shopName,
								"applyid"		=> $this->managerInfo['manager_id'],
								"applyname"		=> $this->managerInfo['mname'],
								"gubun"			=> $gubun,
								"status"		=> $status,
								"server_ip"		=> $_SERVER['SERVER_ADDR']
							);
			$params = array_merge($params,$params_sub);
		}

		$ci = curl_init();
		curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ci, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
		curl_setopt($ci, CURLOPT_URL, $targetUrl);
		curl_setopt($ci, CURLOPT_POST, TRUE);
		curl_setopt($ci, CURLOPT_TIMEOUT, 10);
		curl_setopt($ci, CURLOPT_POSTFIELDS, $params);

		// 주문 등록 후 결과값 확인
		$response = curl_exec($ci);

		if($response == false){
			$err = 'Curl error '. curl_error($ci);
			return $err;
			exit;
		}
		curl_close($ci);

		if($response == "E0001"){

			$result		= "ERROR";
			$message	= "Npay 사용 가능한 상태가 아닙니다.<br />퍼스트몰에 문의해 주세요.";

		}elseif($response == "E0002"){

			$result		= "ERROR";
			$message	= "동일한 가맹점 ID가 이미 존재 합니다.<br />퍼스트몰에 문의해 주세요.";

		}else{

			if($gubun == "new"){
				# firstmall 관리 서버로 전송
				$params['status']			= "9";
				$params['naverpay_mall_id'] = $params['mallid'];
				$this->naverpay_firstmall_apply('new',$params);
			}

			$result		= "SUCCESS";
			$message	= "";
		}

		return array("result"=>$result,"message"=>$message);

	}

	# 네이버페이 2.0 업그레이드 신청
	public function naverpay_upgrade(){

		$naverpay_mall_id 		= $this->input->post('naverpay_mall_id');
		$naverpay_email 		= $this->input->post('naverpay_email');
		$naverpay_user_phone 	= $this->input->post('naverpay_user_phone');

		# Npay에서 사용될 함수 체크
		$func_chk = function_exists('hash_hmac') ;
		if(!$func_chk){
			openDialogAlert("<span style=\'color:red;\'>Npay 사용이 불가한 환경입니다.<br />퍼스트몰에 문의 주세요.</span>",350,160,'parent');
			exit;
		}

		if(!trim($naverpay_mall_id)){
			openDialogAlert("페이가맹점ID를 입력해 주세요.",350,150,'parent');
			exit;
		}
		if(!trim($naverpay_email[0]) | !trim($naverpay_email[1])){
			openDialogAlert("이메일주소를 입력해 주세요.",350,150,'parent');
			exit;
		}
		if(!trim($naverpay_user_phone)){
			openDialogAlert("휴대폰번호를 입력해 주세요.",350,150,'parent');
			exit;
		}

		$navercheckout_tmp = $navercheckout = array();

		$shopName = $this->config_system['admin_env_name'];
		if(!$shopName) $shopName = $this->config_basic['shopName'];

		$navercheckout['naverpay_mall_id']		= $naverpay_mall_id;
		$navercheckout['naverpay_user_email']	= implode("@",$naverpay_email);
		$navercheckout['naverpay_user_phone']	= $naverpay_user_phone;

		$navercheckout_tmp						= $navercheckout;
		config_save('navercheckout_tmp',$navercheckout_tmp);	//위치변경하지말것

		config_save('navercheckout',array("use"=>"test"));	//업그레이드 신청시 현재 사용여부는 "테스트"로

		$navercheckout['userid']				= $this->config_system['service']['cid'];
		$navercheckout['shopSno']				= $this->config_system['shopSno'];
		$navercheckout['domain']				= $this->config_system['domain'];
		$navercheckout['subDomain']				= $this->config_system['subDomain'];
		$navercheckout['gubun']					= "upgrade";
		$navercheckout['companyname']			= $shopName;
		$navercheckout['applyid']				= $this->managerInfo['manager_id'];
		$navercheckout['applyname']				= $this->managerInfo['manager_id'];
		$navercheckout['npay_status']			= "n";
		$navercheckout['returnUrl']				= $_SERVER['HTTP_HOST'];
		$navercheckout['https']					= $_SERVER['HTTPS'];

		# firstmall 관리 서버로 전송
		$this->naverpay_firstmall_apply("upgrade",$navercheckout);

		# 중계서버로 전송
		$this->npay_use_chk("npay_shop_upgrade","1.0");

	}

	# firstmall 관리 서버로 전송
	public function naverpay_firstmall_apply($mode,$navercheckout){

		$target_url = "https://firstmall.kr/naverAPI/npay_apply.php";

		if($mode == "new"){
			echo "<iframe name='actionframe2' ></iframe>";
			echo "<form name='upgrade' method='post' target='actionframe2' action='".$target_url."'>";
		}else{
			echo "<form name='upgrade' method='post' action='".$target_url."'>";
		}
		echo "<input type='hidden' name='mode' value='".$mode."'>";
		echo "<input type='hidden' name='https' value='".(+(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'))."'>";

		foreach($navercheckout as $key=>$val){
			echo "<input type='hidden' name='".$key."' value='".$val."'>";
		}
		echo "</form>";
		echo "<script type='text/javascript'>";
		echo " upgrade.submit(); ";
		echo "</script>";
	}

	# 네이버페이 2.0 업그레이드 신청 결과(firstmall에서 신청처리 후 호출됨)
	public function naverpay_upgrade_result(){

		$callback = '';
		$res_code = $this->input->get('res_code');

		# 임시저장한 네이버페이 업그레이드 신청 정보 불러오기
		$navercheckout_tmp = config_load('navercheckout_tmp');
		foreach($navercheckout_tmp as $key=>$val){
			$navercheckout[$key]		= $val;
			$navercheckout_tmp[$key]	= "";
		}
		if($res_code){

			if($res_code == "E0000"){

				# 네이버페이 업그레이드 신청 정보 저장
				$navercheckout['version']				= '2.1';
				$navercheckout['use']					= 'test';
				$navercheckout['npay_btn_pc_goods']		= 'A-1-2-236×88';
				$navercheckout['npay_btn_mobile_goods']	= 'MA-1-2-290×85';
				config_save('navercheckout',$navercheckout);

				$msg		= "네이버페이 업그레이드 신청 되었습니다.";
				$callback	= 'parent.location.reload()';

			}elseif($res_code == "E0001"){

				$navercheckout['version']				= '2.1';
				$navercheckout['use']					= 'test';
				$navercheckout['npay_btn_pc_goods']		= 'A-1-2-236×88';
				$navercheckout['npay_btn_mobile_goods']	= 'MA-1-2-290×85';
				# 네이버페이 업그레이드 신청 정보 저장
				config_save('navercheckout',$navercheckout);

				$msg		= "이미 네이버페이 업그레이드 신청되었습니다.";
				$callback	= 'parent.location.reload()';

			}elseif($res_code == "E0002"){

				$msg		= "네이버페이 업그레이드 신청서 저장 오류";
			}

		}else{

			$msg = "일시적 장애입니다. 신청버튼을 다시 눌러주세요";

		}

		# 임시저장한 네이버페이 업그레이드 신청 정보 삭제
		config_save('navercheckout_tmp',$navercheckout_tmp);

		openDialogAlert($msg,400,150,'parent',$callback);
	}
	public function cancel_facebook()
	{
		config_save('system',array('facebook_pixel_use'=>'N'));
		$callback	= 'parent.location.reload()';
		$msg		= "전송 종료";
		openDialogAlert($msg, 400, 150, 'parent', $callback);
	}
	public function cancel_google()
	{
		// config_save('partner',array('google_verification_token'=>''));
		$callback	= 'parent.location.reload()';
		$msg		= "전송 종료";
		openDialogAlert($msg, 400, 150, 'parent', $callback);
	}

	## 톡체크아웃 버튼 스타일 저장
	public function talkbuy_btn_style(){

		$style_code 	= $this->input->post('style_code');
		$mode 			= $this->input->post('mode');

		if($style_code){
			### 톡체크아웃 버튼 설정 저장
			$code		= explode("-",$style_code);
		
			$style_text = '';
			if($mode == "mobile_goods") {
				$style_text = "M";
			}
			$style_text .= $code[3]."-".($code[1]+1) . " 타입";			
			$size		= explode("x",$code[0]);
			$talkbuy_btn["talkbuy_btn_".$mode]	= $style_code;
			
			

			config_save('talkbuy',$talkbuy_btn);

			$callback = 'parent.lay_talkbuy_close("'.$mode.'","'.$style_text.'","'.$size[1].'");';
			openDialogAlert("저장 되었습니다.",400,150,'parent',$callback);
			exit;

		}else{

			openDialogAlert("선택된 버튼 스타일이 없습니다.",150,130,'parent');
			exit;

		}

	}	
}