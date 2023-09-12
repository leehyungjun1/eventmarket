<?php
if (! defined('BASEPATH'))
	exit('No direct script access allowed');

class managerLog
{

	private static $CI;
	private $logPath;
    public static $action_type;
    public static $action_type_scm;
    public static $action_menu;
    public static $action_menu_scm;
	public static $fm_code;
	public static $fm_code_menu;
	public static $searchList;
	public static $list_sc_kind;
	public static $sitetype;
	public static $snsrute;
	public static $boardlist;

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->logPath = ROOTPATH."/data/manager_log/";
		$this->action_type = array(
			'login'		=> '로그인',
			'setting'	=> '설정',
			'provider'	=> '입점사',
			'order'		=> '주문',
			'member'	=> '회원',
			'market_connector'=> '오픈마켓',
			'goods'		=> '판매상품',
			'board'		=> '게시판',
			'promotion'	=> '프로모션/쿠폰',
		);

		$this->action_menu['login'] = array(
			'admin_login'		=> 'Admin 로그인',
			'selleradmin_login'	=> 'Selleradmin 로그인',
			'admincrm_login'	=> 'Admincrm 로그인'
		);

		$this->action_menu['setting'] = array(
			'manager_reg'	=> '관리자 등록',
			'manager_modify'=> '관리자 수정',
			'manager_delete'=> '관리자 삭제',
			'pg'			=> '전자결제',
			'bank'			=> '무통장입금계좌',
		);
		
		$this->action_menu['provider'] = array(
			'provider_reg'		=> '입점사 등록',
			'provider_modify'	=> '입점사 수정',
			'provider_delete'	=> '입점사 삭제',
		);
		
		$this->action_detail['setting'] = array(
			'manager_reg'		=> '{manager_target} 등록',
			'manager_modify'	=> '{manager_target} 권한 변경',
			'manager_delete'	=> '{manager_target} 삭제',
		);
		
		$this->action_detail['provider'] = array(
			'provider_reg'		=> '{provider} ({provider_id}) 등록',
			'provider_modify'	=> '{provider} 수정',
			'provider_delete'	=> '{provider} 삭제',
		);
		
		$this->action_menu['member'] = array(
			'catalog'				=> '회원리스트',
			'excel_download'		=> '회원리스트',
			'dormancy_catalog'		=> '휴면처리리스트',
			'withdrawal'			=> '탈퇴회원리스트',
			'kakaotalk_log'			=> '카카오 알림톡 > 발송내역',
			'sms_history'			=> 'SMS 발송관리 > 발송내역 ',
			'email_history'			=> '이메일 발송관리 > 발송내역',
			'curation_history_sms'	=> 'SMS/EMAIL 고객리마인드 > 리마인드 SMS 발송내역',
			'curation_history_email'=> 'SMS/EMAIL 고객리마인드 > 리마인드 EMAIL 발송내역',
			'email_member_catalog'	=> '이메일 수동 발송 > 회원 검색',
			'sms_member_catalog'	=> 'SMS 수동 발송 > 회원 검색',
			'emoney_member_catalog'	=> '마일리지 지급 및 차감 > 회원 검색',
			'point_member_catalog'	=> '포인트 지급 및 차감 > 회원 검색',
			'status_member_catalog'	=> '승인 일괄변경 > 회원 검색',
			'grade_member_catalog'	=> '등급 일괄변경 > 회원 검색',
			'amail_send'			=> '이메일 대량 발송 > 회원 검색',
			'batch_sms_member_catalog'=> 'SMS 대량발송 > 회원 검색',
			'order_catalog_ajax_ajax'=> '고객 CRM > 주문',
			'member_catalog'		=> '고객 CRM > 회원',
			'member_catalog_ajax'	=> '고객 CRM > 회원',
			'order_catalog'			=> '고객 CRM > 회원 주문',
			'main_user_detail'		=> '고객 CRM > 회원 정보',
			'member_activity'		=> '고객 CRM > 활동 정보',
			'order_return_catalog'	=> '고객 CRM > 반품/교환내역',
			'order_refund_catalog'	=> '고객 CRM > 환불 내역',
			'board_review_catalog'	=> '고객 CRM > 상품 후기',
			'board_qna_catalog'		=> '고객 CRM > 상품 문의',
			'board_mbqna_catalog'	=> '고객 CRM > 1:1 문의',
			'board_counsel_catalog'	=> '고객 CRM > 상담',
			'member_modify'			=> '고객 CRM > 회원 정보 수정',
			'member_detail'			=> '고객 CRM > 회원 정보 상세',
			'excel_spout'			=> '엑셀다운로드리스트',
			'sms_member_download_email'	=> '이메일 수동 발송 > 회원 검색',
			'sms_member_download_emoney'=> '마일리지 지급 및 차감 > 회원 검색',
			'sms_member_download_point' => '포인트 지급 및 차감 > 회원 검색',
			'sms_member_download_batch_sms'=> 'SMS 수동 발송 > 회원 검색',
			'grade_member_download' => '회원 승인/등급 > 회원 검색',
			'file_download'			=> '엑셀다운로드 리스트',
		);
		
		$this->action_menu['order'] = array(
			'catalog'							=> '통합주문리스트',
			'excel_download_catalog'			=> '통합주문리스트',
			'catalog_order_prints'				=> '통합주문리스트 > 주문내역서',
			'view_order_prints'					=> '통합주문리스트 > 상세정보 > 주문내역서',
			'company_catalog'					=> '본사배송 주문상품',
			'company_catalog_order_prints'		=> '본사배송 주문상품 > 주문내역서',
			'excel_download_company_catalog'	=> '본사배송 주문상품',
			'view'								=> '주문리스트 > 상세정보',
			'personal'							=> '개인결제리스트',
			'autodeposit'						=> '자동입금확인',
			'personal_info'						=> '휴면/탈퇴 회원 주문 조회',
			'export_catalog'					=> '출고리스트',
			'export_view'						=> '출고리스트 > 상세정보',
			'export_excel_download'				=> '출고리스트',
			'catalog_export_prints'				=> '출고리스트 > 출고내역서',
			'export_view_order_prints'			=> '출고리스트 > 상세정보 > 주문내역서',
			'view_export_prints'				=> '출고리스트 > 상세정보 > 출고내역서',
			'order_export_popup'				=> '출고처리리스트',
			'order_export_popup_order_prints'	=> '출고처리리스트 > 주문내역서',
			'order_export_popup_export_prints'	=> '출고처리리스트 > 출고내역서',
			'batch_status_order_prints'			=> '출고상태변경리스트 > 주문내역서',
			'batch_status_export_prints'		=> '출고상태변경리스트 > 출고내역서',
			'export_batch_status'				=> '출고상태변경리스트',
			'returns_catalog'					=> '반품리스트',
			'returns_view'						=> '반품리스트 > 상세정보',
			'refund_catalog'					=> '환불리스트',
			'refund_view'						=> '환불리스트 > 상세정보',
			'sales'								=> '매출증빙리스트',
			'order_tax_info'					=> '매출증빙리스트 > 신청정보 (세금)',
			'order_cash_info'					=> '매출증빙리스트 > 신청정보 (현금)',
			'temporary'							=> '삭제리스트',
			'temporary_view'					=> '삭제리스트 > 상세정보',
			'excel_spout'						=> '엑셀다운로드리스트',
			'file_download'						=> '엑셀다운로드 리스트',
			'excel_download_order_seller'		=> '입점사배송 주문상품',
			'selleradmin_catalog_order_prints'	=> '입점사배송 주문상품 > 주문내역서',
			'selleradmin_catalog'				=> '입점사배송 주문상품',
			'selleradmin_company_catalog'		=> '위탁배송 주문상품',
			'export_excel_download_export_seller'=> '출고리스트',
			'selleradmin_order_prints'			=> '출고처리리스트 > 주문내역서',
			'selleradmin_export_prints'			=> '출고리스트 > 출고내역서',
			'selleradmin_export_batch_prints'	=> '출고상태변경리스트 > 출고내역서',
			'file_download_seller'				=> '엑셀다운로드 리스트',
		);
		
		$this->action_menu['market_connector'] = array(
			'market_order_list'		=> '주문수집/등록',
			'market_cancel_list'	=> '취소관리',
			'market_return_list'	=> '반품관리',
			'market_exchange_list'	=> '교환관리',
		);

		$this->action_menu['goods'] = array(
			'restock_notify_catalog' => '재입고 알림',
		);

		$this->action_menu['board'] = array(
			'board'			=> '게시판',
			'view'			=> '게시글',
		);

		$this->action_menu['promotion'] = array(
			'coupon_downloadlist' => '할인쿠폰 > 사용 내역 ',
			'promotion_downloadlist' => '할인코드 > 사용 내역',
			'joincheck_memberlist' => '출석체크 > 현황',
		);
		
		$this->fm_code_menu = array(
			'auth_scmstore'		=> '재고기초',
			'auth_scmgoods'		=> '재고관리',
			'auth_scmautoorder'	=> '발주/입고',
			'auth_order'		=> '주문',
			'auth_openmarket'	=> '오픈마켓',
			'auth_goods'		=> '상품',
			'auth_member'		=> '회원',
			'auth_promotion'	=> '프로모션/쿠폰',
			'auth_marketing'	=> '마케팅',
			'auth_statistics'	=> '통계',
			'auth_o2o'			=> '오프라인 매장',
			'auth_provider'		=> '입점사',
			'auth_account'		=> '정산',
			'auth_design'		=> '디자인',
			'auth_setting'		=> '설정',
			'auth_board'		=> '게시판',
			'auth_board_view'	=> '게시판 ',
			'auth_board_view_pw'=> '게시판 ',
			'auth_board_act'	=> '게시판 ',
			'auth_mobileapp'	=> '모바일앱',
			'auth_liveapp'		=> '라이브 쇼핑',
			'auth_private'		=> '개인정보 보호',
        );

		foreach(code_load('auth_scmstore') as $v){
			$this->fm_code['auth_scmstore'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_scmgoods') as $v){
			$this->fm_code['auth_scmgoods'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_scmautoorder') as $v){
			$this->fm_code['auth_scmautoorder'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_order') as $v){
			$this->fm_code['auth_order'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_openmarket') as $v){
			$this->fm_code['auth_openmarket'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_goods') as $v){
			$this->fm_code['auth_goods'][$v['codecd']] = wordwrap($v['value'], 60, "<br>");
		}

		foreach(code_load('auth_member') as $v){
			$this->fm_code['auth_member'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_promotion') as $v){
			$this->fm_code['auth_promotion'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_marketplace') as $v){
			$this->fm_code['auth_marketing'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_statistic') as $v){
			$this->fm_code['auth_statistics'][$v['codecd']] = $v['value'];
		}

		$this->fm_code['auth_o2o'] = array(
			'o2osetting_act'	=> '매장 리스트 설정',
		);

		foreach(code_load('auth_provider') as $v){
			$this->fm_code['auth_provider'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_account') as $v){
			$this->fm_code['auth_account'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_design') as $v){
			$this->fm_code['auth_design'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_setting') as $v){
			$this->fm_code['auth_setting'][$v['codecd']] = $v['value'];
		}

		### board
		$this->CI->load->helper(array('board'));
		$this->CI->load->model('Boardmanager');
		$this->CI->load->model('membermodel');
		$this->CI->load->model('boardadmin');
		boardalllist();//게시판전체리스트
		$this->boardlist = array();
		foreach($this->CI->boardmanagerlist as $v){
			$this->fm_code['auth_board_view'][$v['id'].'_view']			= $v['name'].' 보기'; 
			$this->fm_code['auth_board_view_pw'][$v['id'].'_view_pw']	= $v['name'].' 보기 (비밀글 포함)'; 
			$this->fm_code['auth_board_act'][$v['id'].'_act']			= $v['name'].' (게시물 등록/답변/삭제)'; 
			$this->boardlist[$v['id']] = $v['name'];
		}

		$this->fm_code['auth_board'] = array(
			'board_manger'	=> '게시판 관리 (생성,수정,삭제)',
			'counsel_view'	=> '고객상담 통합게시판 보기',
			'counsel_act'	=> '고객상담 통합게시판 관리',
		);

		foreach(code_load('auth_mobileapp') as $v){
			$this->fm_code['auth_mobileapp'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('auth_broadcast') as $v){
			$this->fm_code['auth_liveapp'][$v['codecd']] = $v['value'];
		}

		foreach(code_load('private_order') as $v){
			$this->fm_code['auth_private'][$v['codecd']] = $v['value'];
		}

		//입점사 노출 제외 메뉴
        $this->action_type_scm = array( 'provider','member','market_connector', 'promotion', 'goods' );
        $this->action_menu_scm = array(
            'login' => array('admin_login', 'admincrm_login'),
            'order' => array('catalog', 'excel_download_catalog', 'catalog_order_prints', 'view_order_prints', 'company_catalog', 'company_catalog_order_prints', 'excel_download_company_catalog', 'autodeposit', 'sales', 'order_tax_info', 'order_cash_info', 'temporary', 'excel_spout', 'file_download', 'catalog_export_prints', 'view_export_prints', 'export_excel_download', 'order_export_popup_order_prints', 'batch_status_order_prints', 'batch_status_export_prints', 'temporary_view'),
			'setting' => array('pg', 'bank'),
		);
		
		$this->searchList = array(
			'sms'					=> 'SMS 수신',
			'mailing'				=> '이메일 수신',
			'business_seq'			=> '가입유형',
			'status'				=> '가입승인(휴면)',
			'grade'					=> '등급',
			'sorder_cnt'			=> '주문횟수',
			'eorder_cnt'			=> '주문횟수',
			'sorder_sum'			=> '결제금액',
			'eorder_sum'			=> '결제금액',
			'referer'				=> '가입경로',
			'snsrute'				=> '회원가입 방법',
			'sitetype'				=> '회원가입 환경',
			'sex'					=> '성별',
			'sage'					=> '나이',
			'eage'					=> '나이',
			'mobile'				=> '수신번호',
			'sc_subject'			=> '이메일 제목',
			'sc_kind'				=> '발송구분',
			'start_date'			=> '발송일',
			'start_date'			=> '발송일',
			'end_date'				=> '발송일',
			'sdate'					=> '탈퇴일',
			'edate'					=> '탈퇴일',
			's_date'				=> '기간',
			'e_date'				=> '기간',
			'status_yn'				=> '전송결과',
			'mall_t_check'			=> '테스트용 회원'
		);
		
		//리마인드 종류
		$this->CI->load->helper('reservation_helper');
		$curations = curation_menu();
		foreach($curations as $v){
			$mname = str_replace('personal_', '', $v['name']);
			$this->list_sc_kind[$mname] = $v['title'];
		}

		$this->CI->load->helper('common');
		$sitetypeloop = sitetype('', 'name', 'array');
		foreach($sitetypeloop as $k => $v){
			$this->sitetype[$k] = $v['name'];
		}

		$ruteloop = memberrute('', 'name', 'array' , 'search');
		foreach($ruteloop as $k => $v){
			$this->snsrute[$k] = $v['name'];
		}
	}
	
	public function insertData($data)
	{
		$shop_no = $this->CI->config_system['shopSno'];
		
		if (!$shop_no) {
			echo 'NOT FOUND SHOP INFO';
			exit;
		}
		
		$provider		= $this->CI->uri->segment(1);
		$type			= str_replace("_process", "", $this->CI->uri->segment(2));
		$menu			= $this->CI->uri->segment(3);
		//$managerInfo	= $this->CI->managerInfo;
		//$url			= base_url(uri_string());
		$is_crm			= false;

		if($data['params']){
			$params		= $data['params'];
		} else {
			if($_POST){
				$params	= $_POST;
			} else if($_GET){
				$params	= $_GET;
			}
		}
		
		$params_before	= $data['params_before'];
		if($provider == 'admincrm'){
			if($menu == 'login'){
				$type = 'login';
			} else {
				$type		= 'member';
				$menu		= $this->CI->uri->segment(2).'_'.$menu;
				$is_crm		= true; //고객 crm 여부

				if($params['ajaxCall']){
					$menu .= '_ajax';
				}
			}
		}

		if($type == 'login'){
			if($provider == 'admin'){
				$menu = 'admin_login';
			} else if($provider == 'admincrm'){
				$menu = 'admincrm_login';
			} else {
				$menu = 'selleradmin_login';
			}
		}

		if( $type == 'excel_spout' && $menu == 'file_download' ){
			$type	= $params['type'];
			if($type == 'export'){
				$type = 'order';
			}

			if($provider == 'selleradmin'){
				$menu = 'file_download_seller';
			}
		}

		if($provider == 'cli'){
			$type	= $params['type'];
			$menu	= $params['menu'];

			if($params['callPage']){
				$menu = 'excel_download_'.$params['callPage'];
			}
		}
		
		if($provider == 'selleradmin' 
			&& (($type == 'order' && $menu == 'catalog') || ($type == 'order' && $menu == 'company_catalog'))){
				$menu = 'selleradmin_'.$menu;
		}
		
		if(strpos($menu, '_prints') !== false){
			if($provider == 'selleradmin' && $type == 'order' && $params['pagemode'] == 'catalog'){
				$menu = 'selleradmin_catalog_'.$menu;
			} else if($provider == 'selleradmin' && $type == 'export' && $params['pagemode'] == 'catalog'){
				$type = 'order';
				$menu = 'selleradmin_export_prints';
			} else if($provider == 'selleradmin' && $type == 'order' && $params['pagemode'] == 'order_export_popup'){
				$type = 'order';
				$menu = 'selleradmin_order_prints';
			} else if($provider == 'selleradmin' && $type == 'export' && $params['pagemode'] == 'batch_status'){
				$type = 'order';
				$menu = 'selleradmin_export_batch_prints';
			} else {
				$type = 'order';
				if($type == 'export' || $type== 'returns' || $type == 'refund'){
					$menu = $type.'_'.$params['pagemode'].'_'.$menu;
				} else {
					$menu = $params['pagemode'].'_'.$menu;
				}
			}
		} else {
			if( $type == 'export' || $type == 'returns' || $type == 'refund' ){
				$menu = $type."_".$menu;
				$type = 'order';
			}
		}
		
		if($type == 'batch'){
			$type = 'member';
			
			if($_GET['callPage']){
				$menu = $_GET['callPage']."_".$menu;
			}
		}

		if($type == 'board'){
			if(strpos($menu, 'write') !== false ){
				exit;
			}

			if($menu != 'board'){
				$menu = 'view';
			}
		}
		
		if($type == 'order' && $menu == 'shipping'){
			$menu = 'view';
		}

		if($menu == 'sms_member_download'){
			$menu = 'sms_member_download_'.$params['callPage'];
		}

		if($type == 'coupon' || $type == 'joincheck' || $type == 'promotion'){
			$menu = $type.'_'.$menu;
			$type = 'promotion';
		}

		if($type == 'pg'){
			$params = $menu;
			$type = 'setting';
			$menu = 'pg';
		}

		if($menu == 'member_catalog_ajax'){
			$menu = 'member_catalog';
		}

		if($menu == 'getMarketOrderList'){
			$menu = 'market_order_list';
		}

		if($menu == 'getMarketClaimList'){
			if($params['now_claim_type'] == 'RTN'){
				$menu = 'market_return_list';
			} else if($params['now_claim_type'] == 'CAN'){
				$menu = 'market_cancel_list';
			} else if($params['now_claim_type'] == 'EXC'){
				$menu = 'market_exchange_list';
			}
		}

		if($type == 'order' && $menu == 'view' && $params['pagemode'] == 'del_order_list'){
			$menu = 'temporary_view';
		}
				
		if($data['type']){
			$type	= key($data['type']);
			$menu	= $data['type'][$type];
		}

//debug_var($provider);
//debug_var($type);
//debug_var($menu);
//debug_var($params);
//debug_var($_POST);
//debug_var($_GET);

		// 데이터 가공 시작
		if (is_null($this->action_menu[$type][$menu]) === false) {
			$indata = array();
			
			$indata['shop_no']		= $shop_no;
			$indata['action_type']	= $type;
			$indata['action_menu']	= $menu;
			if ( ( isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1) ) 
				|| ( isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') ) {
			  $protocol = 'https://';
			} else {
			  $protocol = 'http://';
			}
			$indata['action_menu_url']= $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

			if($type == 'login'){
				if($params['failMsg']){
					//임시 데이터 설정
					$manager_seq	= '0';
					
					$manager_id		= $params['manager_id'];
					$manager_name	= '-';

					if ($provider != 'admin' && $provider != 'admincrm') { // 입점사의 경우
						$provider_id	= 'provider';
						$provider_name	= '입점사';
						$provider_seq	= '0';
					} else {
						$provider_id	= 'admin';
						$provider_name	= '본사';
						$provider_seq	= '1';
					}
					
					$manager_yn			= 'N';
					$super_manager_yn	= 'N';
				} else {
					if ($provider != 'admin' && $provider != 'admincrm') { // 입점사의 경우
						if(!$params){
							//echo 'NOT FOUND PROVIDER INFO';
							exit;
						} else {
							$provider_seq	= $params['provider_seq'];
							$provider_id	= $params['provider_id'];
							$provider_name	= $params['provider_name'];
						}
					} else {
						$provider_seq	= 1;
						$provider_id	= 'admin';
						$provider_name	= $this->CI->config_system['admin_env_name'];
					}
					
					if(!$params['manager_seq']){
						exit;
					}

					$manager_seq		= $params['manager_seq'];
					$manager_id			= $params['manager_id'];
					$manager_name		= $params['manager_name'];
					$manager_yn			= $params['manager_yn'];
					$super_manager_yn	= $params['super_manager_yn'];
				}
			} else {
				if( $provider == 'selleradmin' || (substr($menu, -7) == '_seller' || substr($menu, 0, 12) == 'selleradmin_') ){ //입점사의 경우
					$provider_seq	= $this->CI->providerInfo['provider_seq'];
					$provider_id	= $this->CI->providerInfo['provider_id'];
					$provider_info	= explode(":", $this->CI->providerInfo['provider_log_name']);
					$provider_name	= $provider_info[0];

					if($this->CI->managerInfo && $this->CI->managerInfo['manager_seq'] == 1){ //본사가 입점사 로그인 한 경우
						$manager_seq 	= $this->CI->managerInfo['manager_seq'];
						$manager_id		= $this->CI->managerInfo['manager_id'];
						$manager_name	= $this->CI->managerInfo['mname'];
						
						$manager_yn		= 'N';
						$super_manager_yn= 'Y';
					} else {
						$manager_seq 	= $this->CI->providerInfo['sub_provider_seq'];
						$manager_id		= $this->CI->providerInfo['provider_id'];
						$manager_name	= $this->CI->providerInfo['provider_name'];
						
						if($this->CI->providerInfo['manager_yn'] == 'Y'){
							$manager_yn			= 'Y';
						} else {
							$manager_yn			= 'N';
						}
						
						$super_manager_yn= 'N';
					}
				} else {
					$provider_seq	= 1;
					$provider_id	= 'admin';
					$provider_name	= $this->CI->config_system['admin_env_name'];
					
					$manager_seq 	= $this->CI->managerInfo['manager_seq'];
					$manager_id		= $this->CI->managerInfo['manager_id'];
					$manager_name	= $this->CI->managerInfo['mname'];
					
					if($this->CI->managerInfo['manager_yn'] == 'Y'){
						$manager_yn			= 'Y';
						$super_manager_yn	= 'Y';
					} else {
						$manager_yn			= 'N';
						$super_manager_yn	= 'N';
					}
				}
			}
			
			//$action_detail	= $this->action_detail[$type][$menu];
			$action_desc	= "";

			switch($type){
				case 'login':
					if($params['failMsg']){
						$action_desc	= '실패';
						$action_target	= $params['failMsg'];
					}else{
						$action_desc = '성공';
					}
				break;
					
				case 'setting':
					if($menu == 'pg'){
						$action_target = $params;
						$action_desc = '수정';
					} else {
						if($provider == 'admin'){
							if(strpos($menu, 'manager') !== false){
								$action_desc = str_replace('{manager_target}', "부운영자 (".$params['mname'].")", $this->action_detail[$type][$menu]);
							} else {
								$action_desc = '수정';
							}
						} else {
							$action_desc = str_replace('{manager_target}', "입점사 부운영자 ".$params['provider_id'].' ('.$params['provider_name'].')', $this->action_detail[$type][$menu]);
						}
					}
				break;
					
				case 'provider':
					$action_desc	= str_replace('{provider}', "입점사 ".$params['provider_name'], $this->action_detail[$type][$menu]);
					$action_desc	= str_replace('{provider_id}', $params['provider_id'], $action_desc);
				break;
				
				case 'member':
					if($is_crm){ //crm 회원 조회 시
						$sc = $this->CI->mdata;
						$action_target	= "member_seq|userid|";
						$action_status	= $params['member_seq']."|".$sc['userid']."|";
						$action_before	= "";
						if($menu == 'member_catalog' || $menu == 'order_catalog' || $menu == 'order_catalog_ajax_ajax' 
							|| $menu == 'order_return_catalog' || $menu == 'order_refund_catalog' || $menu == 'board_review_catalog' 
							|| $menu == 'board_qna_catalog' || $menu == 'board_mbqna_catalog' || $menu == 'board_counsel_catalog'){ 
							if($menu == 'order_catalog_ajax_ajax'){
								$indata['action_menu_url'] .= "?".http_build_query($_POST);
							}
							
							if( array_key_exists('date_field', $params) || array_key_exists('sdate', $params) || array_key_exists('edate', $params) || array_key_exists('body_crm_search_keyword', $params) ){
								if($params['body_crm_search_keyword']){ //회원검색
									$keyword = $params['body_crm_search_keyword'];
								} else { //주문검색
									$keyword = $params['keyword'];
								}

								$action_target	= "keyword|searchcount|";
								$action_status	= $keyword."|".$this->CI->searchcount."|";
								$action_before	= "";
								$action_desc = "검색";

								if( ($menu == 'order_catalog_ajax_ajax' && $params['searchType'] != 'search') 
									|| ($menu == 'member_catalog' && $params['searchType'] == 'init')){
									$action_desc = "조회";
								}
							} else {
								if( strlen($sc['userid']) > 0 ){
									$action_desc = $sc['userid']." 조회";
								} else {
									$action_desc = "조회";
								}
							}
						} else {
							$action_desc	= $sc['userid']." 조회";
						}
					} else {
						if(is_numeric($params['searchcount']) || $params['orderby']){ //리스트 검색 시
							$action_desc	= "검색";
							$action_before	= "";
							$action_target	= "searchcount|";
							
							$sc = array_shift($this->CI->template->var_);
							$sc = $sc['sc'];
							
							$action_status	= $sc['searchcount']."|";
						} else if($data['userinfo']) { //crm 회원 수정 시
							$action_target	= "member_seq|userid|";
							$action_status	= $data['userinfo']['member_seq']."|".$data['userinfo']['userid']."|";
							$action_before	= $data['userinfo']['member_seq']."|".$data['userinfo']['userid']."|";

							$user_name = $data['userinfo']['user_name'];
							$name_len = mb_strlen($data['userinfo']['user_name'], "UTF-8");
							$user_name_new = mb_substr($user_name, 0, 1, "UTF-8").str_repeat('*', $name_len - 2).mb_substr($user_name, $name_len - 1, 1, "UTF-8");
							
							$userinfo = $data['userinfo']['userid']."(".$user_name_new.")";
							
							$keyname = key($params);
							if($keyname != 'password'){
								$action_target .= $keyname."|";
								$action_status .= $params[$keyname]."|";
								$action_before .= $params_before[$keyname]."|";
							}
							
							switch(key($params)){
								case "user_name":
									$action_desc = $userinfo." 이름 수정";
									break;
									
								case "email":
									$action_desc	= $userinfo." 이메일 수정";
									break;
									
								case "phone":
									$action_desc	= $userinfo." 전화번호 수정";
									break;
									
								case "cellphone":
									$action_desc	= $userinfo." 핸드폰 번호 수정";
									break;
									
								case "address_street":
									$action_desc	= $userinfo." 주소 수정";
									break;
									
								case "address_detail":
									$action_desc	= $userinfo." 주소 (상세) 수정";
									break;
									
								case "password":
									$action_desc	= $userinfo." 비밀번호 수정";
									break;
							}
						} else if(is_numeric($params['excelcount'])){ //excel 다운로드 시
							if($params['reg_date']){
								$action_desc = $params['reg_date'].' 요청 엑셀 다운로드';
							} else {
								$action_desc = '엑셀 다운로드';
							}
							
							$action_before	= "";
							$action_target	= "searchcount|";
							$action_status	= $params['excelcount']."|";
						} else if($menu == 'excel_spout' || $menu == 'file_download') { //엑셀 다운로드
							$action_desc = $params['reg_date'].' 요청 엑셀 다운로드';
							
							$action_before	= "";
							$action_target	= "searchcount|";
							$action_status	= $params['excelcount']."|";
						} else {
							$action_desc	= "조회";
						}
					}
					
				break;
					
				case 'order':
					if($params['pagemode'] == "export_view" 
						|| $params['pagemode'] == "return_view"
						|| $params['pagemode'] == "refund_view"){ //중복 로그 방지
						exit;
					}
					$getUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
					parse_str($getUrl, $searchParams);

					if($params['no'] && (strpos($menu, "view") !== false || $menu == 'order_tax_info' || $menu == 'order_cash_info')){
						$action_desc	= $params['no']." 조회";
					} else if($menu == 'excel_spout' || strpos($menu, 'excel_download') !== false || strpos($menu, 'file_download') !== false) { //엑셀 다운로드
						if($params['url']) { //excle 다운로드 페이지에서 다운로드 시
							$urlArr = parse_url($params['url']);
							$urlArr = explode("/", $urlArr['path']);
							
							$urlProvider= $urlArr[1];
							$urlType	= $urlArr[2];
							$urlMenu	= $urlArr[3];
							
							if($urlProvider == 'selleradmin' && $urlType == 'order' && $urlMenu == 'catalog'){
								$urlMenu = 'selleradmin_catalog';
							}
							
							if($urlType == 'export' || $urlType == 'returns' || $urlType == 'refund'){
								$urlMenu = $urlType."_".$urlMenu;
								$urlType = 'order';
							}
							
							if($urlType == 'excel_spout'){
								$indata['action_type']	= $type;
							} else {
								$indata['action_type']	= $urlType;
							}
							
							$indata['action_menu']	= $urlMenu;
							$indata['action_menu_url'] = $params['url'];
							
							if($urlMenu != 'excel_download'){ //엑셀 다운로드 리스트에서 다운시 중복 입력 방지
								exit;
								//$action_desc	= "엑셀 다운로드";
							}
						} else {
							if($params['reg_date']){
								$action_desc = $params['reg_date'].' 요청 엑셀 다운로드';
							} else {
								$action_desc = '엑셀 다운로드';
							}
				
							$action_before	= "";
							$action_target	= "searchcount|";
							$action_status	= $params['excelcount']."|";
						}
					} else if($params['order_seq']) { //주문 상세에서 배송지 수정
						$action_desc	= $params['order_seq']." 배송지정보 수정";
						
						$action_target	= $params['target'];
						$action_before	= $params['before'];
						$action_status	= $params['after'];
					} else if(strpos($menu, '_prints') !== false){ //인쇄
						$action_desc	= "인쇄";
					} else if( strlen($searchParams['date']) > 0 || strlen($searchParams['date_field']) > 0 || strlen($searchParams['date_gb']) > 0 
						|| ( array_key_exists('keyword', $params) && ($menu == 'temporary' || $menu == 'personal') ) 
						|| ($provider == 'selleradmin' && $menu == 'refund_catalog') ){

						if( $provider == 'selleradmin' && ($menu == 'export_catalog' || $menu == 'refund_catalog') ){
							if(array_key_exists('keyword', $params)){
								$action_desc	= "검색";
							} else {
								$action_desc	= "조회";
							}
						} else {
							if(count($searchParams) > 0){
								$action_desc	= "검색";
							} else {
								$action_desc	= "조회";
							}
						}

						// 휴면/탈퇴 회원 주문 조회
						if($menu == 'personal_info') {
							$action_desc = $searchParams['keyword']." 조회";
						}
					} else {
						$action_desc	= "조회";
					}
				break;
				
				case 'board':
					if(strlen($params['id']) > 0){
						$action_target = $this->boardlist[$params['id']];
						$action_status = $params['seq'];
						if($menu == 'board'){
							if(strlen($params['select_date_regist']) > 0){
								$action_desc = "검색";
							} else {
								$action_desc = "조회";
							}
						} else {
							$action_desc = "조회";
						}
					}
				break;
				
				case 'promotion':
					if($_POST['pageType'] == 'search' || $params['searchflag']){
						$desc_txt = '검색';
						$indata['action_menu_url'] .= "?".http_build_query($_POST);
					} else {
						$desc_txt = '조회';
					}

					if($params['title']){
						$action_desc = $params['title']." ".$desc_txt;
					} else {
						$action_desc = $params['coupon_name']." ".$desc_txt;
					}

					if(is_numeric($params['searchcount'])){ 
						$action_before	= "";
						$action_target	= "searchcount|";
						$action_status	= $params['searchcount']."|";						
					}
				break;
				
				case 'market_connector':
					if( strlen($params['perpage']) <= 0 && strlen($params['dateType']) > 0 ){ //중복 로그 방지
						exit;
					}

					if( strlen($params['dateType']) > 0 ){
						$action_desc = "검색";
					} else {
						$action_desc = "조회";
					}
				break;

				case 'goods':
					if( strlen($params['search_field']) > 0 ){
						$action_desc = "검색";
					} else {
						$action_desc = "조회";
					}
				break;
			}
			
			$indata['action_desc']	= $action_desc;
			
			$indata['provider_seq']	= $provider_seq;
			$indata['provider_id']	= $provider_id;
			$indata['provider_name']= $provider_name;

			$indata['manager_seq']	= $manager_seq;
			$indata['manager_id']	= $manager_id;
			$indata['manager_name']	= $manager_name;
			$indata['manager_yn']	= $manager_yn;
			$indata['super_manager_yn']	= $super_manager_yn;
			
			$indata['access_ip']	= $_SERVER['REMOTE_ADDR'];
			$indata['regist_date']	= date('Y-m-d H:i:s');
			//$indata['expire_date']	= date('Y-m-d H:i:s', strtotime('+2 years -1 day'));
			$indata['expire_date']	= date('Y-m-d H:i:s', strtotime('+3 months'));
			
			//관리자 변경 사항에 대한 설정
			if($type == 'setting'){
				if($menu != 'pg'){
					$action_target	= '';
					$action_status	= '';
					$action_before	= '';
				}
				
				$fm_codes = array_reduce(array_values($this->fm_code), 'array_merge', array());
				switch($menu){
					case 'manager_modify': //관리자 수정의 경우
						foreach($fm_codes as $k => $v){
							if(array_key_exists($k, $params) && $params_before[$k] == 'N'){
								$action_target .= $k."|";
								$action_status .= "Y|";
								$action_before .= "N|";
							} else if(!array_key_exists($k, $params) && $params_before[$k] == 'Y'){
								$action_target .= $k."|";
								$action_status .= "N|";
								$action_before .= "Y|";
							}
						}
						
						$board_before = array();
						foreach($params_before['setting_board'] as $k => $v){
							if($v['board_view'] > 0){
								$board_before[$v['id']]['board_view']	= 'Y';
							} else {
								$board_before[$v['id']]['board_view']	= 'N';
							}
							
							if($v['board_view_pw'] > 0){
								$board_before[$v['id']]['board_view_pw']	= 'Y';
							} else {
								$board_before[$v['id']]['board_view_pw']	= 'N';
							}
							
							if($v['board_act'] > 0){
								$board_before[$v['id']]['board_act']	= 'Y';
							} else {
								$board_before[$v['id']]['board_act']	= 'N';
							}
						}
						
						foreach($board_before as $k => $v){
							if($params['board_view'][$k] && $v['board_view'] == 'N'){
								$action_target .= $k."_view|";
								$action_status .= "Y|";
								$action_before .= "N|";
							} else if(!$params['board_view'][$k] && $v['board_view'] == 'Y'){
								$action_target .= $k."_view|";
								$action_status .= "N|";
								$action_before .= "Y|";
							}
							
							if($params['board_view_pw'][$k] && $v['board_view_pw'] == 'N'){
								$action_target .= $k."_view_pw|";
								$action_status .= "Y|";
								$action_before .= "N|";
							} else if(!$params['board_view_pw'][$k] && $v['board_view_pw'] == 'Y'){
								$action_target .= $k."_view_pw|";
								$action_status .= "N|";
								$action_before .= "Y|";
							}
							
							
							if($params['board_act'][$k] && $v['board_act'] == 'N'){
								$action_target .= $k."_act|";
								$action_status .= "Y|";
								$action_before .= "N|";
							} else if(!$params['board_act'][$k] && $v['board_act'] == 'Y'){
								$action_target .= $k."_act|";
								$action_status .= "N|";
								$action_before .= "Y|";
							}
						}
						
					break;
						
					case 'manager_reg': //관리자 입력의 경우
						foreach($fm_codes as $k => $v){
							if(array_key_exists($k, $params)){
								$action_target .= $k."|";
								$action_status .= "Y|";
								$action_before .= "N|";
							}
						}
						
						foreach($params['board_view'] as $k => $v){
							$action_target .= $k."_view|";
							$action_status .= "Y|";
							$action_before .= "N|";
						}
						
						foreach($params['board_view_pw'] as $k => $v){
							$action_target .= $k."_view_pw|";
							$action_status .= "Y|";
							$action_before .= "N|";
						}
						
						foreach($params['board_act'] as $k => $v){
							$action_target .= $k."_act|";
							$action_status .= "Y|";
							$action_before .= "N|";
						}
					break;
				}
			}
			
			if(strlen($action_target) > 0){
				$indata['action_target']= $action_target;
				$indata['action_status']= $action_status;
				$indata['action_before']= $action_before;
			}

//debug_var($action_desc);
//debug_var($indata);
			
			if ($action_desc) {
				if($indata['action_menu'] == 'manager_reg'){
					$indata1 = $indata;
					unset($indata1['action_target'], $indata1['action_status'], $indata1['action_before']);
					$this->CI->db->insert('fm_manager_log', $indata1);

					if(strlen($action_target) > 0){
						$indata2 = $indata;
						$indata2['action_menu'] = 'manager_modify';
						$indata2['action_desc'] = str_replace('등록', '권한 변경', $indata2['action_desc']);
						$this->CI->db->insert('fm_manager_log', $indata2);
					}
				} else {
					$this->CI->db->insert('fm_manager_log', $indata);
				}
				
				//filebeat 수집을 위한 로그 쓰기
				if($this->CI->config_system['service']['hosting_code'] != 'F_SH_X'){ //외부 호스팅이 아닐 경우
					//$this->writeLog($indata);
				}
			}
		}
	}

	public function delete_manager_log()
	{
		$this->CI->db->where('expire_date <=', date('Y-m-d H:i:s'));
		$this->CI->db->delete('fm_manager_log');
		
		$getDate = date("Ymd", strtotime("-1days"));
		$files = array_filter(glob($this->logPath."*"), 'is_file');
		
		foreach($files as $file){
			$fileDate = end(explode("/", str_replace(".log", "", $file)));
			if($fileDate < $getDate){
				unlink($file);
			}
		}
	}

	public function writeLog($indata)
	{
		$list = array(
			'shop_no',
			'action_type',
			'action_menu',
			'action_menu_url',
			'provider_seq',
			'provider_id',
			'provider_name',
			'manager_seq',
			'manager_id',
			'manager_name',
			'access_ip',
			'regist_date',
			'expire_date',
			'action_desc',
		);

		if(!is_dir($this->logPath)){
			mkdir($this->logPath);
			@chmod($this->logPath, 0777);
		}
		
		$content = "";
		foreach($list as $v){
			$content .= $indata[$v]." ";
		}
		
		$fp = fopen($this->logPath.date('Ymd').".log", "a+");
		fwrite($fp, $content . "\r\n");
		fclose($fp);
	}
}
