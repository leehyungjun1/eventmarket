<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class mobile_app extends admin_base
{

	public function __construct()
	{
		parent::__construct();

		// 보안키 입력창
		$member_download_info = $this->skin.'/member/member_download_info.html';
		$this->template->define(array("member_download_info"=>$member_download_info));
	}

	public function state()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function info()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function info_form()
	{
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function push()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		### 공통
		$msg_group[1]['name']		= array('order', 'settle', 'notpaid', 'prod_ans', 'mb_ans', 'goods_review_ans');
		$msg_group[1]['title']		= array('주문접수 알림', '결제확인 알림', '미입금 알림', '상품문의 답변 알림', '1:1문의 답변 알림', '상품후기 댓글 등록 알림');//1:1문의 답변 시
		$msg_group[1]['push_chk']	= array('', '', '', '', '', '');//푸시설정 체크박스

		### 실물상품
		$msg_group[2]['name']		= array('released', 'released2', 'delivery', 'delivery2', 'cancel', 'refund');
		$msg_group[2]['title']		= array('출고완료 알림', '출고완료 받는분 알림', '배송완료 알림', '배송완료 받는분 알림', '취소신청 환불완료 알림', '반품신청 환불완료 알림');//1:1문의 답변 시
		$msg_group[2]['push_chk']	= array('', '', '', '', '', '');//푸시설정 체크박스

		### 티켓상품
		$msg_group[3]['name']		= array('coupon_released', 'coupon_released2', 'coupon_delivery', 'coupon_delivery2', 'coupon_cancel', 'coupon_refund');
		$msg_group[3]['title']		= array('티켓발송 주문자 알림', '티켓발송 받는분 알림', '티켓사용 주문자 알림', '티켓사용 받는분 알림', '취소신청 환불완료 알림', '반품신청 환불완료 알림');//1:1문의 답변 시
		$msg_group[3]['push_chk']	= array('', '', '', '', '', '');//푸시설정 체크박스

		$loop						= array();

		$msg_arr					= parse_ini_file(APPPATH."config/_default_push_msg.ini", true);

		foreach ($msg_group as $k => $data){

			$name_arr				= $data['name'];
			$title_arr				= $data['title'];
			$push_chk_arr			= $data['push_chk'];
			$cnt					= count($name_arr);

			for($i = 0; $i < $cnt; $i++){

				$name				= $name_arr[$i];

				###
				$v['name']			= $name;
				$v['title']			= $title_arr[$i];
				$v['push_msg']		= $msg_arr[$name];
				$v['push_chk']		= $push_chk_arr[$i];

				$loop[$k][]			= $v;
			}
		}

		## 치환코드 리스트
		$replace_item	= array();
		$replace_item[] = array("cd" => "shopName"			,"nm" => "쇼핑몰 이름(설정 &gt; 일반정보)");
		$replace_item[] = array("cd" => "shopDomain"		,"nm" => "쇼핑몰 도메인");
		$replace_item[] = array("cd" => "userid"			,"nm" => "회원아이디");
		$replace_item[] = array("cd" => "username"			,"nm" => "회원명(회원명 없을시 제외)");
		$replace_item[] = array("cd" => "password"			,"nm" => "회원비밀번호");
		$replace_item[] = array("cd" => "order_user"		,"nm" => "주문자명");
		$replace_item[] = array("cd" => "recipient_user"	,"nm" => "받는분");
		$replace_item[] = array("cd" => "ordno"				,"nm" => "주문번호");
		$replace_item[] = array("cd" => "orduserName"		,"nm" => "주문자명");
		$replace_item[] = array("cd" => "go_item"			,"nm" => "출고완료/배송완료 상품","etc"=>"여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.");
		$replace_item[] = array("cd" => "ord_item"			,"nm" => "주문상품","etc"=>"여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.");
		$replace_item[] = array("cd" => "bank_account"		,"nm" => "입금은행 계좌번호 예금주");
		$replace_item[] = array("cd" => "settleprice"		,"nm" => "입금(결제)금액");
		$replace_item[] = array("cd" => "settle_kind"		,"nm" => "결제수단 수단별확인메시지","etc"=>"<div style='color:#999999;'>
																신용카드 예시) 카드결제 완료<br>
																계좌이체 예시) 계좌이체 완료<br>
																가상계좌 예시) 가상계좌 완료<br>
																무통장 예시) OO은행 입금확인<br>
																핸드폰 예시) 핸드폰 결제완료
																</div>");
		$replace_item[] = array("cd" => "delivery_company"	,"nm" => "택배사명");
		$replace_item[] = array("cd" => "delivery_number"	,"nm" => "운송장번호");
		$replace_item[] = array("cd" => "coupon_serial"		,"nm" => "티켓인증코드");
		$replace_item[] = array("cd" => "couponNum"			,"nm" => "티켓발송회차");
		$replace_item[] = array("cd" => "coupon_value"		,"nm" => "티켓값어치");
		$replace_item[] = array("cd" => "options"			,"nm" => "필수옵션");
		$replace_item[] = array("cd" => "used_time"			,"nm" => "티켓사용일시");
		$replace_item[] = array("cd" => "coupon_used"		,"nm" => "티켓사용 값어치");
		$replace_item[] = array("cd" => "coupon_remain"		,"nm" => "티켓잔여 값어치");
		$replace_item[] = array("cd" => "used_location"		,"nm" => "티켓 사용처");
		$replace_item[] = array("cd" => "confirm_person"	,"nm" => "티켓사용 확인자");
		$replace_item[] = array("cd" => "goods_name"		,"nm" => "티켓상품","etc"=>"여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.");
		$replace_item[] = array("cd" => "repay_item"		,"nm" => "취소/반품->환불완료 상품","etc"=>"여러 개의 상품인 경우 외 몇 건으로 표시됨<br />상품명은 치환코드 길이 설정을 따릅니다.");
		$replace_item[] = array("cd" => "remainSms"				,"nm" => "잔여문자");
		$replace_item[] = array("cd" => "remainAutodeposit"		,"nm" => "자동입금만료일");
		$replace_item[] = array("cd" => "remainGoodsflow"		,"nm" => "잔여택배자동");
		$replace_item[] = array("cd" => "dormancy_du_date"		,"nm" => "휴면예정일");

		$replace_item[] = array("cd" => "거래처명"				,"nm" => "거래처명");
		$replace_item[] = array("cd" => "발주번호"				,"nm" => "발주번호");
		$replace_item[] = array("cd" => "발주일시"				,"nm" => "발주일시");
		$replace_item[] = array("cd" => "발주종수"				,"nm" => "발주종수");
		$replace_item[] = array("cd" => "발주수량"				,"nm" => "발주수량");
		$replace_item[] = array("cd" => "발주서상세URL"			,"nm" => "발주서상세URL");

		## 공통
		//주문접수 알림
		$use_replace_code['order']				= array("shopName","username","ord_item","ordno", "bank_account", "settleprice");
		//결제확인 알림
		$use_replace_code['settle']				= array("shopName","username","ord_item","ordno", "settleprice");
		//미입금 알림
		$use_replace_code['notpaid']			= array("shopName","username","ord_item","ordno", "bank_account", "settleprice");
		//상품문의 답변 알림
		$use_replace_code['prod_ans']			= array("shopName","username");
		//1:1문의 답변 알림
		$use_replace_code['mb_ans']				= array("shopName","username");
		//상품후기 댓글 등록 알림
		$use_replace_code['goods_review_ans']	= array("shopName","username");

		## 실물상품
		//출고완료 알림
		$use_replace_code['released']			= array("shopName", "ordno");
		//출고완료 받는분 알림
		$use_replace_code['released2']			= array("shopName", "order_user", "recipient_user");
		//배송완료 알림
		$use_replace_code['delivery']			= array("shopName", "ordno");
		//배송완료 받는분 알림
		$use_replace_code['delivery2']			= array("shopName", "ordno");
		//취소신청 환불완료 알림
		$use_replace_code['cancel']				= array("shopName");
		//반품신청 환불완료 알림
		$use_replace_code['refund']				= array("shopName");

		## 티켓상품
		//티켓발송 주문자 알림
		$use_replace_code['coupon_released']	= array("order_user", "recipient_user", "goods_name", "coupon_serial", "couponNum", "coupon_value", "options");
		//티켓발송 받는분 알림
		$use_replace_code['coupon_released2']	= array("shopName", "shopDomain", "order_user", "goods_name", "coupon_serial", "couponNum", "coupon_value", "options");
		//티켓사용 주문자 알림
		$use_replace_code['coupon_delivery']	= array("recipient_user", "goods_name", "coupon_serial", "couponNum", "coupon_value", "options", "used_time", "coupon_used", "coupon_remain", "used_location", "confirm_person");
		//티켓사용 받는분 알림
		$use_replace_code['coupon_delivery2']	= array("shopName", "shopDomain", "order_user", "goods_name", "coupon_serial", "couponNum", "coupon_value", "options", "used_time", "coupon_used", "coupon_remain", "used_location", "confirm_person");
		//취소신청 환불완료 알림
		$use_replace_code['coupon_cancel']		= array("order_user", "goods_name", "coupon_serial", "couponNum");
		//반품신청 환불완료 알림
		$use_replace_code['coupon_refund']		= array("order_user", "goods_name", "coupon_serial", "couponNum");

		$this->template->assign('loop',$loop);
		$this->template->assign('replace_item', $replace_item);
		$this->template->assign('use_replace_code', $use_replace_code);

		$this->template->assign('tab1','-on');
		$this->template->define('top_menu',$this->skin.'/mobile_app/top_menu.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function push_manual()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth_send	= $this->authmodel->manager_limit_act('mobileapp_push');
		if(!$auth_send){
			echo "<script>alert('권한이 없습니다.'); history.back();</script>";
			exit;
		}

		//$send_phone = getSmsSendInfo();
		//if(isset($send_phone)) $this->template->assign('send_phone',$send_phone);

		$table = !empty($_GET['table']) ? $_GET['table'] : 'fm_member';
		$this->template->assign('table',$table);

		// 회원정보다운로드 체크
		/*if ($this->managerInfo['manager_yn']=='Y') {
			$auth_member_down = true;
		} else {
			$auth_member_down	= $this->authmodel->manager_limit_act('member_download');
		}
		if(isset($auth_member_down)) $this->template->assign('auth_member_down',$auth_member_down);
		*/

        #############################################################################
	    ##
	    $push_list_url = "http://userapp.firstmall.kr/getmobileapprelease";

        //$params   		= array("shopSno" => "100918");//$this->config_system['shopSno']);
        $params   		= array("shopSno" => $this->config_system['shopSno']);

	    $ci = curl_init();
	    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE);
	    curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE);
	    curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
	    curl_setopt($ci, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	    curl_setopt($ci, CURLOPT_URL, $push_list_url);
	    curl_setopt($ci, CURLOPT_POST, TRUE);
	    curl_setopt($ci, CURLOPT_TIMEOUT, 10);
	    curl_setopt($ci, CURLOPT_POSTFIELDS, $params);

	    // 주문 등록 후 결과값 확인
        $response = curl_exec($ci);

        $response_array = json_decode($response, true);
        # app 신청갯수
        //$app_cnt = (int)$response_array["ANDROID"]["result"] + (int)$response_array["IOS"]["result"];
        $android = (int)$response_array["ANDROID"]["result"];
        $ios = (int)$response_array["IOS"]["result"];

        if( $android > 0 || $ios > 0 ) $app_cnt = 1;
	    if($response == false){
	        $err = 'Curl error '. curl_error($ci);
	        return $err;
	        exit;
	    }
        curl_close($ci);

		for ($h=1;$h<=24;$h++){	$h_arr[] = str_pad($h, 2, '0', STR_PAD_LEFT); }
		for ($m=0;$m<=59;$m++){	$m_arr[] = str_pad($m, 2, '0', STR_PAD_LEFT); }

        $this->template->assign('app_cnt',$app_cnt);
		$this->template->assign('h_arr',$h_arr);
		$this->template->assign('m_arr',$m_arr);

		$this->template->assign('tab2','-on');
		$this->template->define('top_menu',$this->skin.'/mobile_app/top_menu.html');
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function push_history()
	{
		$auth_send	= $this->authmodel->manager_limit_act('mobileapp_push');
		if(!$auth_send){
			echo "<script>alert('권한이 없습니다.'); history.back();</script>";
			exit;
		}

	    $this->load->model('membermodel');

	    $this->admin_menu();
	    $this->tempate_modules();
	    $file_path	= $this->template_path();

	    ### SEARCH
	    $sc = $this->input->get();
	    $sc['page']				= (isset($sc['page']))			?	intval($sc['page'])	: 0;
	    $sc['perpage']			= (isset($sc['perpage']))			?	intval($sc['perpage']): 10;
	    $sc['start_date']		= (isset($sc['start_date']))		?	$sc['start_date']		: "";
	    $sc['end_date']			= (isset($sc['end_date']))		?	$sc['end_date']		: "";
	    $sc['search_title']		= (isset($sc['search_title']))	?	$sc['search_title']	: "";
		$sc['send_type']		= (isset($sc['send_type']))		?	$sc['send_type']		: "";

	    #############################################################################
	    ##
	    $push_list_url = "http://userapp.firstmall.kr/push/list_push.php";

		// 발송 대기중인 목록 가져오기 위한 구분자 추가 :: 2019-01-04 pjw
	    $params   		= array("shopsno"		=> $this->config_system['shopSno'],
                    	        "page"			=> $sc['page'],
                    	        "perpage"		=> $sc['perpage'],
	                            "start_date"	=> $sc['start_date'],
	                            "end_date"		=> $sc['end_date'],
	                            "search_title"	=> $sc['search_title'],
								"send_type"		=> $sc['send_type'],
								'isadmin'		=> 'Y',
        );

		$ci = curl_init();
	    curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE);
	    curl_setopt($ci, CURLOPT_SSL_VERIFYHOST, FALSE);
	    curl_setopt($ci, CURLOPT_RETURNTRANSFER, TRUE);
	    curl_setopt($ci, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
	    curl_setopt($ci, CURLOPT_URL, $push_list_url);
	    curl_setopt($ci, CURLOPT_POST, TRUE);
	    curl_setopt($ci, CURLOPT_TIMEOUT, 10);
	    curl_setopt($ci, CURLOPT_POSTFIELDS, $params);

	    // 주문 등록 후 결과값 확인
	    $response = curl_exec($ci);

        $response_array = json_decode($response, true);

	    if($response == false){
	        $err = 'Curl error '. curl_error($ci);
	        return $err;
	        exit;
	    }
	    curl_close($ci);
	    ##
	    #############################################################################
	    ### PAGE & DATA
	    $sc['searchcount']	 = $response_array['cnt'];
	    $sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);

		foreach($response_array['data']['list'] as &$data) {
			$data['encode_title'] = urlencode($data['title']);
			$data['encode_body'] = urlencode(str_replace("<br/>","\n",$data['body']));
		}
	    $this->template->assign('pushlist', $response_array['data']['list']);
	    $this->template->assign('tab3','-on');

	    $paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
	    if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';

	    $this->template->assign('pagin',$paginlay);
	    $this->template->assign('perpage',$sc['perpage']);
	    $this->template->assign('sc',$sc);

	    $this->template->define('top_menu',$this->skin.'/mobile_app/top_menu.html');
	    $this->template->define(array('tpl'=>$file_path));
	    $this->template->print_("tpl");
	}


	// 앱 설정
	public function setting()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth_send	= $this->authmodel->manager_limit_act('mobileapp_setting');
		if(!$auth_send){
			echo "<script>alert('권한이 없습니다.'); history.back();</script>";
			exit;
		}

		$this->load->helper('readurl');

		// 모바일 앱 설정 정보 조회
		$mobileAppConfig = getMobileAppConfig();
		$mobileAppSetting = $mobileAppConfig['mobileAppSetting'];
		$environmentAppInfo = $mobileAppConfig['environmentAppInfo'];

		// 앱 상태
		$statusTxtArr = [
			'00' => '제작중',
			'10' => '제작중',
			'20' => '제작중',
			'30' => '제작중',
			'40' => '제작중',
			'80' => '사용중',
		];
		// 등록방식
		$accountTypeTxtArr = [
			'firstmall' => '퍼스트몰 계정',
			'custom' => '고객사 계정',
		];

		// android, iOS 둘중 하나라도 부가서비스 상태가 서비스 중이거나, 앱 상태가 제작완료 상태인 경우 사용중으로 판단
		$appUse = false;
		if (($environmentAppInfo['ANDROID']['status'] === '9' || $environmentAppInfo['ANDROID']['app_status'] === '80') ||
		$environmentAppInfo['IOS']['status'] === '9' || $environmentAppInfo['IOS']['app_status'] === '80') {
			$appUse = true;
		}

		// OS별 앱 서비스 정보 설정
		foreach ($environmentAppInfo as $osType => $osInfo) {
			$info['account_type_txt'] = $accountTypeTxtArr[$osInfo['app_account_type']];
			$info['period']['start'] = $osInfo['regist_date'];
			$info['period']['launched'] = $osInfo['start_date'];
			$info['period']['end'] = $osInfo['expire_date'];
			$info['status'] = is_numeric($osInfo['app_status']) ? $osInfo['app_status'] : 0;
			$info['status_txt'] = $statusTxtArr[$osInfo['app_status']];
			$info['app_store_url'] = $osInfo['app_store_url'];

			$serviceInfo[$osType] = $info;

			// DB 앱 스토어 주소 값이 없을 때 설정
			$popupUrlType = strtolower(substr($osType, 0, 3));
			if ($appUse && !empty($osInfo['app_store_url']) && empty($mobileAppSetting['popup_url_' . $popupUrlType])) {
				$mobileAppSetting['popup_url_' . $popupUrlType] = $info['store_url'];
				config_save('app_config', $mobileAppSetting);
			}
		}

		// view 출력 변수
		$mobileAppInfo = [
			'app_use' => $appUse,
			'app_config' => $mobileAppSetting,
			'ANDROID' => $serviceInfo['ANDROID'],
			'IOS' => $serviceInfo['IOS'],
		];

		$this->template->assign($mobileAppInfo);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 앱 권장 팝업 수정 HTML
	public function app_popup(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$style_txt = array('img_a'=>'이미지형 A', 'img_b'=>'이미지형 B', 'btn'=>'버튼형');
		if($_POST['popup_type'] != 'custom'){
			$base_app_config	= config_load('app_config');

			// 기본 default 값 설정
			if			($_POST['pop_html'] && $_POST['new_popup_type'] == $_POST['popup_type']){ // 임시 수정
				$app_popup_config	= $_POST;
			}else if	($base_app_config['popup_type'] != $_POST['popup_type']){
				switch($_POST['popup_type']){
					case 'img_a':
						$app_popup_config['pop_title']			= '이번달 한정 Event';
						$app_popup_config['pop_subtitle']		= "신규 APP 설치 고객\r\n10% 할인 쿠폰 증정!";
						$app_popup_config['pop_sale']			= '10';
						$app_popup_config['pop_sale_unit']		= 'per';
						$app_popup_config['pop_footer_txt']		= '오늘 이 창을 열지 않음';
						$app_popup_config['pop_footer_close']	= '닫기';
					break;
					case 'img_b':
						$app_popup_config['pop_title']			= 'APP 설치 혜택';
						$app_popup_config['pop_subtitle']		= "신규 APP 설치 시\r\n할인 쿠폰 제공!";
						$app_popup_config['pop_footer_close']	= '괜찮습니다. 모바일웹으로 볼게요.';
					break;
					case 'btn':
						$app_popup_config['pop_title']			= '쇼핑몰 앱 설치하기';
						$app_popup_config['pop_footer_close']	= '모바일웹으로 계속하기';
					break;
				}
			}else{ // 저장값
				$app_popup_config	= $base_app_config;
			}

			$app_popup_config['popup_type']		= $_POST['popup_type'];
			$app_popup_config['popup_type_txt']	= $style_txt[$_POST['popup_type']];
		}

		$this->template->assign($app_popup_config);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function statistic ()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		## 날짜 파라미터
		$_GET['year']	= !empty($_GET['year'])		? $_GET['year']		: date('Y');
		$_GET['month']	= !empty($_GET['month'])	? $_GET['month']	: date('m');
		$_GET['osType'] = !empty($_GET['osType'])	? $_GET['osType']	: "android";

		if($_GET['osType']=="android")
		{
			$this->template->assign('tab1','-on');
		}else{
			$this->template->assign('tab2','-on');
		}

		$start_time		= strtotime($_GET['year'].'-'.$_GET['month'].'-01');

		$end_day	= date('t', $start_time);

		for ($d = 1; $d <= $end_day; $d++)
		{
			$download					= 1;
			$cancel						= 2;
			$member						= 3;
			$push						= 4;

			$statlist[$d]['download']	= $download;
			$statlist[$d]['cancel']		= $cancel;
			$statlist[$d]['member']		= $member;
			$statlist[$d]['push']		= $push;

			$total['t_download']		+= $download;
			$total['t_cancel']			+= $cancel;
			$total['t_member']			+= $member;
			$total['t_push']			+= $push;
		}

		$this->template->assign(array('statlist' => $statlist, 'total' => $total));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}
}