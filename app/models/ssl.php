<?php
class Ssl extends CI_Model {
	var $ssl_use;			// 사용여부	(1/0)
	var $ssl_pay;			// 유료여부	(1/0)

	var $ssl_kind;			// 종류		(text)
	var $ssl_status;		// 상태		(1/0)
	var $ssl_period_start;	// 시작일
	var $ssl_period_expire;	// 만기일
	var $ssl_port='80';		// 포트
	var $ssl_domain;		// 도메인

	function __construct() {
		parent::__construct();

		$this->ssl_use 				= $this->config_system['ssl_use'];
		$this->ssl_pay 				= $this->config_system['ssl_pay'];
		$this->ssl_kind 			= $this->config_system['ssl_kind'];
		$this->ssl_status 			= $this->config_system['ssl_status'];
		$this->ssl_period_start 	= $this->config_system['ssl_period_start'];
		$this->ssl_period_expire 	= $this->config_system['ssl_period_expire'];
		$this->ssl_port 			= $this->config_system['ssl_port'];
		$this->ssl_domain 			= $this->config_system['ssl_domain'];
		$this->ssl_external 		= $this->config_system['ssl_external'];
		$this->ssl_ex_domain 		= $this->config_system['ssl_ex_domain'];
		$this->ssl_ex_port 			= $this->config_system['ssl_ex_port'];
		$this->ssl_page 			= $this->config_system['ssl_page'];

	}

	function ssl_domain_check(){
		if($this->managerInfo && strstr($_SERVER['REQUEST_URI'],'common/editor_image')) return true;//
		if($this->managerInfo && strstr($_SERVER['REQUEST_URI'],'order/optional_changes')) return true;//
		if($this->providerInfo && strstr($_SERVER['REQUEST_URI'],'common/editor_image')) return true;//
		if($this->providerInfo && strstr($_SERVER['REQUEST_URI'],'order/optional_changes')) return true;//

		/*
		if(!$this->designMode && !$this->mobileMode && !$this->storemobileMode && !$this->fammerceMode && !$this->storefammerceMode && !preg_match("/^m\./i",$_SERVER['HTTP_HOST'])){
			if($this->ssl_external_setting() && $_SERVER['REQUEST_METHOD']=='GET' && preg_replace("/:[0-9]{1,5}/","",$_SERVER['HTTP_HOST'])!=$this->ssl_ex_domain){
				if( !strstr($_SERVER['REQUEST_URI'],'member/register_sns_form') ) {//facebook 임시도메인용
					redirect("http://".$this->ssl_ex_domain.$_SERVER['REQUEST_URI']);
					exit;
				}
			}elseif($this->ssl_pay_is_alive() && $_SERVER['REQUEST_METHOD']=='GET' && preg_replace("/:[0-9]{1,5}/","",$_SERVER['HTTP_HOST'])!=$this->ssl_domain){
				if( !strstr($_SERVER['REQUEST_URI'],'member/register_sns_form') ) {//facebook 임시도메인용
					redirect("http://".$this->ssl_domain.$_SERVER['REQUEST_URI']);
					exit;
				}
			}
		}
		*/
	}

	/* SSL세팅값 직접입력 체크 */
	function ssl_external_setting(){
		$currentDate = date('Y-m-d');
		if( $this->designMode ) return false;
		if($this->ssl_use && $this->ssl_pay && $this->ssl_external && $this->ssl_ex_domain && $this->ssl_ex_port){
			return true;
		}
		return false;
	}

	/* 유료사용일경우 상태와 날짜 체크 */
	function ssl_pay_is_alive(){
		$currentDate = date('Y-m-d');
		if( $this->designMode ) return false;
		if($this->ssl_use && $this->ssl_pay && $this->ssl_status && $this->ssl_domain && $this->ssl_port){
			if($this->ssl_period_start <= $currentDate && $currentDate < $this->ssl_period_expire) return true;
			return false;
		}
		return false;
	}

	/* 중계처리 페이지 URL 변환  */
	function get_ssl_action($action){
		$base64url_encode_action = str_replace(array('+', '/'), array('-', '_'), base64_encode($action));

		if($this->ssl_external_setting()){
			$url = "https://{$this->ssl_ex_domain}:{$this->ssl_ex_port}/ssl/relayRsa?action=".$base64url_encode_action;
		}else if($this->ssl_pay_is_alive()){
			$url = "https://{$this->ssl_domain}:{$this->ssl_port}/ssl/relayRsa?action=".$base64url_encode_action;
		}else{
			// $url = "http://hed.firstmall.kr:80/ssl/relayRsa?action=".$base64url_encode_action;	// 유료 SSL 테스트
			// $url = "http://ssltest.firstmall.kr/ssl/setRSAReturnPost/".$base64url_encode_action;	// 무료 SSL 서버 테스트
			$url = "https://ssl.gabiafreemall.com/RSA/ssl/setRSAReturnPost/".$base64url_encode_action;		// 무료 SSL 서버
		}
		return $url;
	}

	/* 
	 * SSL 도메인 반환 SSL 
	 * 미사용 중일 경우 임시 도메인 반환
	 * $onlySSL 변수가 true 일때 SSL 미사용일 경우 false 반환
	 */
	function get_ssl_domain($onlySSL = false){
		$url = false;
		if($this->ssl_external_setting()){
			$url = "https://{$this->ssl_ex_domain}:{$this->ssl_ex_port}";
		}else if($this->ssl_pay_is_alive()){
			$url = "https://{$this->ssl_domain}:{$this->ssl_port}";
		}else{
			if(!$onlySSL){
				$url = "http://".$this->config_system['subDomain'];
			}
		}
		return $url;
	}

	/* SSL 중계처리 페이지로 넘어갔다가 되돌아온 데이터 디코드 */
	function decode(){
		
		$aPostParams	= $this->input->post();
		// 퍼스트몰 SSL
		if( $aPostParams['jCryption'] && $aPostParams['encryptionKey'] ){
			$this->load->model('jcryptionmodel');
			$aParams	= $this->jcryptionmodel->decrypt_key($aPostParams['encryptionKey'], $aPostParams['jCryption']);
			$_POST		= $aParams;
		}else{
			if($this->ssl_use && !empty($_POST['sslEncodedString'])){
				$this->load->helper('cookiesecure');
				$decoded = unserialize(cookieDecode(base64_decode($_POST['sslEncodedString']),50), ['allowed_classes' => false]);
	
				if(is_array($decoded)){
					foreach($decoded as $k=>$v){
						$_POST[$k] = $v;
					}
				}
			}
		}

		/**
		// 보안체크 추가
		/* login, member, mypage, board, sales
		/* @2017-04-18
		**/
		if(strstr(uri_string(), "_process")){
			$this->load->helper('xssfilter');
			xss_clean_filter();
		}
		sql_injection_check();
	}

	/* SSL이 적용될 페이지일 경우 보안 프로토콜로 리다이렉션 */
	function ssl_page_check(){
		// 모바일 도메인 여부
		$mobile_add_domain = '';
		// 요청 도메인을 유지, front_base에서 모바일 도메인으로 포워딩하므로 해당 기능을 통해 모바일 도메인으로 변경 by hed
		// $mobile_add_domain = (($this->mobileMode)?"m.":"");
		
		// 현재 주소
		$info_uri = parse_url($_SERVER['REQUEST_URI']);
		$now_url = $info_uri['path']; // without querystring
		$now_uri = $now_url.(($info_uri['query'])?"?".$info_uri['query']:"");	// with querystring

		// 프로토콜
		// $this->ssl_use, $this->ssl_pay 둘 다 1이어야 유료 서버 사용 중, 각각 1,0 이면 무료서버이므로 https 적용이 아님
		$http_protocol	= "http://";
		$ssl_protocol	= ($this->ssl_use && $this->ssl_pay)?"https://":$http_protocol;

		// 도메인
		$domain			= $_SERVER['HTTP_HOST'];
		if($this->_is_mobile_agent){
			/* 모바일도메인이 아닐때 모바일로 이동 */
			if($this->session->userdata('setMode')!='pc' && !$this->_is_mobile_domain){
				$domain = preg_replace("/^www\./","",$domain);
			}
		}
		$http_domain	= $mobile_add_domain.$domain;
		$http_domain	= str_replace("m.m.", "m.", $http_domain);
		$ssl_domain		= $http_domain;
		
		if($this->ssl_external_setting()){	// 별도 SSL 사용시
			$ssl_domain = $mobile_add_domain.$this->ssl_ex_domain.":".$this->ssl_ex_port;
		}else if($this->ssl_pay_is_alive()){	// 기본 SSL 사용시
			$ssl_domain = $mobile_add_domain.$this->ssl_domain.":".$this->ssl_port;
			$ssl_domain	= str_replace("m.m.", "m.", $ssl_domain);
		}
		
		// 무시 페이지 : 무시 될 경우 현재 페이지의 프로토콜을 그대로 이용
		$array_ignore_ssl_page = array(
			'/_firstmallplus/statistics',				// 통계
			'/favicon.ico',								// 파비콘
			'/common/get_right_display',				// 우측 판넬
			'/common/get_right_total',					// 우측 판넬
			'/popup/zipcode',							// 우편번호 찾기
			'/main/blank',								// iframe용 빈 페이지
			'/common/category_all_navigation',			// 카테고리 조회
			'/login_process/logout',					// 로그아웃
			'/order/fblike_opengraph',					// 페이스북 라이크 처리
			'/order/calculate',							// 금액 계산
			'/order/modify_shipping_changes',			// 배송방법 수정
			'/mypage_process/delivery_address',			// 배송주소록 수정
			'/payment/inicis_return',					// ActiveX 가상계좌 입금확인 리턴 //  고객이 관리자에 등록함
			'/payment/lg_return',						// ActiveX 가상계좌 입금확인 리턴 // 호출할 URL을 전송하므로 불필요
			'/lg/status',								// NON-ActiveX LG 유플러스에서 조회	//  호출할 URL을 전송하므로 불필요
			'/lg_mobile/cas_noteurl',					// 모바일 입금확인 //  호출할 URL을 전송하므로 불필요
			'/lg_mobile/returnurl',						// 모바일 결제성공 //  호출할 URL을 전송하므로 불필요
			'/lg_mobile/note_url',						// 모바일 ISP방식 //  호출할 URL을 전송하므로 불필요
			'/payment/allat_return',					// ActiveX 가상계좌 입금확인 리턴 // 고객이 관리자에 등록함
			'/allat/auth',								// NON-ActiveX 승인요청	// 호출할 URL을 전송하므로 불필요
			'/allat/status',							// NON-ActiveX 조회	// 고객이 관리자에 등록함
			'/payment/kcp_return',						// ActiveX 가상계좌 입금확인 리턴 // 고객이 관리자에 등록함
			'/kcp/status',								// NON-ActiveX 조회	// 고객이 관리자에 등록함
			'/payment/kicc_return',						// kicc 가상계좌 입금확인 리턴
			'/payment/kspay_return',					// 가상계좌 입금확인 리턴 // 고객이 관리자에 등록함
			'/board_process',							// 게시판 처리
			'/board_comment_process',					// 게시판 댓글 처리
			'/goods/user_select_list',					// 상품목록
			'/order/complete_replace',					// 결재 취소 및 실패시 iframe 부모창 제어를 위해
			'/partner/navercheckout_item',				// 네이버페이 상품정보 XML
		    '/partner/navercheckout_additionalFee',     // 네이버페이 도서산간비체크 XML
			'/partner/naver_third',						// 네이버쇼핑 EP 데이터(무조건 http 접근)
			'/partner/naver_sales_ep',					// 네이버쇼핑 EP 데이터(무조건 http 접근) - 판매지수
			'/cosmos/*',								// 외부에서 통신하는 컨트롤러, 리다이렉트를 하면 안됨(데이터 누락됨)
			'/_gabia/*',								// 크론
			'/_gabiaFront/*',							// 크론
			'/_batch/*',								// 크론
			'/.well-known/*',							// 무료인증서 프로그램이 인증서 생성, 연장할때 자동으로 생성했다가 지움
			'/payco/set_keys',							// 페이코 서비스 승인완료 처리
			'/naverpay/set_npay_inquiry',				// 네이버페이 문의 - 중계서버에서 호출 시 리다이렉트 예외 되도록 추가(2020-04-09)
			'/naverpay/set_npay_goodsreview',			// 네이버페이 후기 - 중계서버에서 호출 시 리다이렉트 예외 되도록 추가(2020-04-09)
			'/naverpay/set_npay_order',					// 네이버페이 주문 - 중계서버에서 호출 시 리다이렉트 예외 되도록 추가(2020-04-09)
			'/kakaotalk/get_sms_token',					// 카카오 알림톡 SMS 발송 token 생성
			'/kakaotalk/sms_send',						// 카카오 알림톡 SMS 발송 요청
		);

		// ssl 적용 페이지
		$array_ssl_page = array(
			// 관리자 로그인
				'/admin/login/index',					// 관리자 로그인 페이지
				'/admin/login_process/login',			// 관리자 로그인 처리
				'/selleradmin/login/index',				// 입점몰 관리자 로그인 페이지
				'/selleradmin/login_process/login',		// 입점몰 관리자 로그인 처리
			// 회원가입
				'/member/agreement',					// 회원가입 약관 페이지
				'/member/register',						// 회원가입 정보 입력
				'/member_process/register',				// 회원가입 체크
				'/member_process/register_ok',			// 회원가입 처리
				'/member_process/id_chk',				// 아이디 중복 체크
				'/member/recommend_confirm',			// 추천인ID
				'/member/register_sns_form',			// sns 회원가입
				'/sns_process/snsredirecturl',			// sns 처리
				'/sns_process/twitterjoin',				// 트위터 회원 가입 처리
				'/sns_process/naveruserck',				// 네이버 sns 체크
				'/sns_process/instagramuserck',			// 인스타그램 sns 체크
			// 로그인
				'/member/login',						// 로그인
				'/login_process/login',					// 로그인 처리
				'/sns_process/twitterlogin',			// 트위터 로그인 처리
			// 아이디/비밀번호 찾기
				'/member/find',							// 아이디/비밀번호 찾기
				'/login_process/findid',				// 아이디 찾기 처리
				'/admin/captcha/securimage_show',		// 보안문자
				'/login_process/findpwd',				// 비밀번호 찾기 처리
			// 주문서 작성페이지
				'/order/settle',						// 주문서 작성페이지
				'/order/pay',							// 주문서 처리
			// 무통장입금
				'/payment/bank',
			// 이니시스
				'/payment/inicis',					// ActiveX 결제성공
			// '/payment/inicis_return',			// ActiveX 가상계좌 입금확인 리턴 //  고객이 관리자에 등록함
				'/inicis/request',					// NON-ActiveX 결제 요청
				'/inicis/receive',					// NON-ActiveX 결제성공
				'/inicis/payClose',					// 결제창 클로징
				'/inicis/payPopup',					// 결제창
				'/inicis_mobile/inicis',			// 모바일 결제요청
				'/inicis_mobile/inicis_next',		// 모바일 결제성공
				'/inicis_mobile/inicis_rnoti',		// 모바일 입금통보
				'/inicis_mobile/popup_return',		// 모바일 리턴 페이지
			// LG U+
				'/payment/lg',						// ActiveX 결제성공
			// '/payment/lg_return',			// ActiveX 가상계좌 입금확인 리턴 // 호출할 URL을 전송하므로 불필요
				'/lg/auth',							// NON-ActiveX PAYKEY 인증
				'/lg/receive',						// NON-ActiveX 최종결제요청 페이지
				'/lg/request',						// NON-ActiveX 결제 요청
			// '/lg/status',					// NON-ActiveX LG 유플러스에서 조회	//  호출할 URL을 전송하므로 불필요
				'/lg_mobile/auth',					// 모바일 승인요청
			// '/lg_mobile/cas_noteurl',		// 모바일 입금확인 //  호출할 URL을 전송하므로 불필요
			// '/lg_mobile/returnurl',			// 모바일 결제성공 //  호출할 URL을 전송하므로 불필요
			// '/lg_mobile/note_url',			// 모바일 ISP방식 //  호출할 URL을 전송하므로 불필요
				'/lg_mobile/payres',				// 모바일 결제요청
			// 올앳
				'/payment/allat',					// ActiveX 결제성공
			// '/payment/allat_return',			// ActiveX 가상계좌 입금확인 리턴 // 고객이 관리자에 등록함
				'/allat/request',					// NON-ActiveX 결제요청
			// '/allat/auth',						// NON-ActiveX 승인요청	// 호출할 URL을 전송하므로 불필요
				'/allat/receive',					// NON-ActiveX 결제처리
			// '/allat/status',					// NON-ActiveX 조회	// 고객이 관리자에 등록함
				'/allat_mobile/allat',				// 모바일 승인요청
				'/allat_mobile/approval',			// 모바일 결제요청
				'/allat_mobile/cancel',			// 모바일 취소
				'/allat_mobile/receive',			// 모바일 처리
			// kcp
				'/payment/kcp',					// ActiveX 결제성공
			// '/payment/kcp_return',			// ActiveX 가상계좌 입금확인 리턴 // 고객이 관리자에 등록함
				'/kcp/request',					// NON-ActiveX 결제요청
				'/kcp/pg_open_script',						// NON-ActiveX 승인요청	// 호출할 URL을 전송하므로 불필요
				'/kcp/receive',					// NON-ActiveX 결제처리
			// '/kcp/status',					// NON-ActiveX 조회	// 고객이 관리자에 등록함
				'/kcp_mobile/auth',				// 모바일 승인요청
				'/kcp_mobile/approval',			// 모바일 결제요청
				'/kcp_mobile/pp_ax_hub',			// 모바일 처리
			// ksnet
				'/order/kspay_wh_rcv',					// 결제요청
				'/payment/kspay',						// 결제성공
			// '/payment/kspay_return',					// 가상계좌 입금확인 리턴 // 고객이 관리자에 등록함
				'/kspay_mobile/kspay_wh_result',			// 결제 회신
			// 카카오페이
				'/kakaopay/request',				// 결제 요청
				'/kakaopay/auth',					// 인증
				'/kakaopay/receive',				// 결제 승인
				'/kakaopay/cancel',					// 결제 취소
				'/kakaopay/payfail',				// 결제 실패
			// 페이팔
				'/order/paypal_order',				// 페이팔 주문
			// 엑심베이
				'/order/eximbay',					// 엑심베이 요청
				'/eximbay/request',					// 결제 요청
				'/eximbay/receive',					// 결제 승인
				'/eximbay/status',					// 결제 검증
			// 비원 주문 확인 인증
				'/mypage_process/order_auth',			// 비원 주문 확인 인증
			// 게시판 ( 비회원 작성권한설정이 가능한 게시판 모두, 추가생성되는 게시판 포함)
				'/board/write',							// 게시판 쓰기
				'/board/view',							// 게시판 보기 & 댓글
			// '/goods/qna_catalog',					// 상품문의 목록	// 불필요
				'/goods/qna_view',						// 상품문의 보기
				'/goods/qna_write',						// 상품문의 쓰기
			// '/goods/view',						// 상품상세 	// 불필요
			// 마이페이지 - 주소록
				'/mypage/delivery_address',				// 마이페이지 - 주소록
				'/mypage/delivery_address_ajax',		// 마이페이지 - 주소록 처리 & 수정
			// 마이페이지 - 회원정보수정
				'/mypage/myinfo',						// 마이페이지 - 회원정보수정
				'/member_process/myinfo_modify',		// 처리
			// 마이페이지 - 회원탈퇴
				'/mypage/withdrawal',					// 마이페이지 - 회원탈퇴
				'/member_process/withdrawal',			// 처리
			// 마이페이지 - 나의 1:1문의
				'/mypage/myqna_write',					// 마이페이지 - 나의 1:1문의
			// 마이페이지 - 나의 상품문의
				'/mypage/mygdqna_write',				// 마이페이지 - 나의 상품문의
			// 마이페이지 - 나의 상품후기
				'/mypage/mygdreview_write',				// 마이페이지 - 나의 상품후기
			// 마이페이지 - 개인결제
				'/mypage/personal',						// 마이페이지 - 개인결제
			// 재입고 알림 신청
				'/goods/restock_notify_apply',			// 재입고 알림 신청
			// 하단 에스크로 표기 이미지
			// 별도 구성
			// 거래명세서 & 견적서 인쇄								
				'/prints/form_print_trade',						// 거래명세서
				'/prints/form_print_estimate',					// 견적서 인쇄
		);
		
		$_subDomain = config_load("system","subDomain");

		$array_cron = array('_gabia', '_batch', '_market', '_gabiaFront', 'cli', 'batch/dailyEp');
		// 적용 페이지에 맞춰 평문과 암호문으로 강제 이동
		if( array_search($_SERVER['argv'][1], $array_cron) === false 
			 && _IS_SHELL_MODE_ != 'Y' ){ //cronjob 일땐 하단 프로세스 무시 19.01.07 kmj
			if(   ($this->ssl_use && $this->ssl_pay && $this->ssl_page) 
				&& !check_ajax_protocol() 
				&& !check_contain_url($now_url, $array_ignore_ssl_page)
				&& $domain != $_subDomain['subDomain']){

				if(!check_ssl_protocol()){
					redirect($ssl_protocol.$ssl_domain.$now_uri);
					exit;
				}
			}
		}
	}
}
/**
 * ======================================================================
 * created by hed 2019-03-19
 * app/models/ssl.php 에서 app/helpers/common_helper.php 의 의존성을 해제하기 위해 함수가 없을 경우 별도로 생성하여 처리
 * 이미 최신 패치까지 완료되었을 경우 불필요한 소스
 *  = 패치명 : 페이코 간편결제, KICC(이지페이), 반응형 쇼핑몰 기능 및 기타 오류 수정
 *  = 패치일자 : 03-07
 * ======================================================================
 * 의존성 해제 소스 시작
 * ======================================================================
**/ 
if (!function_exists('check_ajax_protocol')) {
	function check_ajax_protocol(){
		$ajax = false;
		if(!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
		&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
		  $ajax = true;
		}
		return $ajax;
	}
}
if (!function_exists('check_contain_url')) {
	function check_contain_url($now_url, $check_urls){
		$result	  = false;
		foreach($check_urls as $check){
			$tmp_urls = explode('/', $check);
			$regex = "/^";
			foreach($tmp_urls as $tmp_url){
				$cell = trim($tmp_url);
				if($cell == '*')		$regex .= "\/.*";
				else if($cell != '')	$regex .= "\/".$cell;
				else					continue;
			}
			$regex .= "$/";
			if(preg_match($regex, $now_url)){
				$result = true;
			}
		}
		return $result;
	}
}
if (!function_exists('check_ssl_protocol')) {
	function check_ssl_protocol(){
		$ssl = "";
		$ssl = ((!empty($_SERVER['HTTPS'])) && ($_SERVER['HTTPS']=='on')) ? true : false;
		return $ssl;
	}
}
/**
 * ======================================================================
 * 의존성 해제 소스 종료
 * ======================================================================
 */
?>