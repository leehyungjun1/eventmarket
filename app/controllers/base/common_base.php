<?php if ( ! defined("BASEPATH")) exit("No direct script access allowed");
/**
 * @version 1.0.0
 * @license copyright by GABIA
 * @property CI_DB_active_record $db
 * @property CI_DB_forge $dbforge
 * @property CI_Benchmark $benchmark
 * @property CI_Calendar $calendar
 * @property CI_Cart $cart
 * @property CI_Config $config
 * @property CI_Controller $controller
 * @property CI_Email $email
 * @property CI_Encrypt $encrypt
 * @property CI_Exceptions $exceptions
 * @property CI_Form_validation $form_validation
 * @property CI_Ftp $ftp
 * @property CI_Hooks $hooks
 * @property CI_Image_lib $image_lib
 * @property CI_Input $input
 * @property CI_Language $language
 * @property CI_Loader $load
 * @property CI_Log $log
 * @property CI_Model $model
 * @property CI_Output $output
 * @property CI_Pagination $pagination
 * @property CI_Parser $parser
 * @property CI_Profiler $profiler
 * @property CI_Router $router
 * @property CI_Session $session
 * @property CI_Sha1 $sha1
 * @property CI_Table $table
 * @property CI_Trackback $trackback
 * @property CI_Typography $typography
 * @property CI_Unit_test $unit_test
 * @property CI_Upload $upload
 * @property CI_URI $uri
 * @property CI_User_agent $user_agent
 * @property CI_Validation $validation
 * @property CI_Xmlrpc $xmlrpc
 * @property CI_Xmlrpcs $xmlrpcs
 * @property CI_Zip $zip
 * @property template $template
 * @property Javascript $javascript
 */

class common_base_original extends CI_Controller {

	var $config_system			= array();
	var $config_admin_env		= array();
	var $config_currency		= array();
	var $functionLimitUri		= array();
	var $gl_skin_configuration	= array();

	public function  __construct() {
		// error_reporting(0);//0 E_ALL E_ERROR|E_PARSE

		// 레퍼러에 싱글쿼테이션(')이 있을 경우 레퍼러 조작으로 판단하여 레퍼러 제거. by hed #24462
		if(preg_match('/\'/', $_SERVER['HTTP_REFERER'])){
			$_SERVER['HTTP_REFERER'] = '';
		}

		parent::__construct();
		$this->chk_refesh_load();

		DBConnect();

		//$this->output->enable_profiler(TRUE);
		//페이지 각종 정보 노출

		// query builder alias 사용을 위해 prefix 제한
		$this->db->set_dbprefix('');

		$this->set_header();

		// 캐시 드라이버를 로드하고, 사용하는 드라이버로 APC를 지정하고, APC를 사용할 수 없는 경우 파일 기반 캐싱으로 대체
		$this->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));

		$this->load->helper("directory");
		$this->load->helper('basic');
		$this->load->helper('debug');
		$this->load->helper('cookie');
		$this->load->helper('javascript');
		$this->load->helper('sqlinjection');
		$this->load->helper('xssfilter');
		$this->load->model('adminenvmodel');
		$this->load->model('currencymodel');
		$this->load->library('blockpage');

		$this->get_config_system();
		$this->get_config_basic();
		$this->redirect_domain();
		$this->chk_mobile_env();
		$this->getPolicyPrivacy();
		sql_injection_check();

		// O2O 환경 체크 false : 일반 접속, true : 오프라인 요청
		// 해당 변수를 o2o 서비스가 시작되면 갱신함
		$this->o2o_pos_env = false;

		// 스킨 compile 시 필요 정보
		$this->template->phpSkin = $this->config_system['phpSkin'];

		$this->managerInfo = $this->session->userdata('manager');
		$this->providerInfo = $this->session->userdata('provider');

		if( (isset($_GET['facebook']) && $_GET['facebook']=='Y') ||  (isset($_GET['signed_request']) && $_GET['signed_request']) || ($_COOKIE['fammercemode'] && $_COOKIE['fammercemode'] !='deleted')){
			$this->load->library('snssocial');
			//$this->snssocial->facebooklogin();
			$this->fbuser = $this->snssocial->facebookuserid();
			if ( !$this->fbuser ) {
				$this->facebook = new Facebook(array(
				  'appId'  => $this->__APP_ID__,
				  'secret' => $this->__APP_SECRET__,
				  "cookie" => true
				));
				// Get User ID
				$this->fbuser = $this->facebook->getUser();
				if($this->fbuser){
					if( !$this->session->userdata('fbuser') ) {
						$this->session->set_userdata('fbuser', $this->fbuser);
					}
				}else{
					$this->snssocial->facebooklogin();
				}
			}else{
				if( !$this->session->userdata('fbuser') ) {
					$this->session->set_userdata('fbuser', $this->fbuser);
				}
			}
			if($_GET['signed_request']){
				setcookie('fammercemode', $_GET['signed_request'], 0, '/');
			}elseif($_GET['facebook-page']){
				setcookie('fammercemode', $_GET['facebook-page'], 0, '/');
			}else{
				setcookie('fammercemode', $_COOKIE['fammercemode'], 0, '/');
			}
			$_GET['setMode']='fammerce';
		}

		$setMode = !empty($_GET['setMode']) ? $_GET['setMode'] : '';
		$setMode = $setMode ? $setMode : $this->session->userdata('setMode');

		/* PC/모바일/페이스북 모드로 보기 */
		if($setMode){
			$this->session->set_userdata('setMode',	$setMode);

			setcookie('fammercemode', '', 0, '/');

			$this->mobileMode = false;
			$this->fammerceMode = false;

			if($setMode=='mobile')		$this->mobileMode = true;
			if($setMode=='fammerce')	$this->fammerceMode = true;

		}else{
			$setMode = $this->session->userdata('setMode');
		}

		/* setMode가 페이머스이거나 페이스북 캔버스 내에서 호출할때 */
		if($setMode=='fammerce' || ($_COOKIE['fammercemode'] && $_COOKIE['fammercemode'] !='deleted')){
			$this->fammerceMode = true;
			$this->mobileMode = false;
		}else{
			$this->fammerceMode = false;
		}

		/* 페이머스플러스면 무조건 페이머스모드 */
		if($this->config_system['service']['code']=='P_FAMM'){
			//$this->fammerceMode = true;
			//$this->mobileMode = false;
		}

		$this->checkfreecheck();
		$this->get_config_skin();


		//GA통계 사용여부
		$this->get_config_GA();

		//기능제한 URI
		$this->functionLimitUri = array(
			"admin/marketing_process/marketplace", //마케팅 > 입점마케팅설정 > 저장하기
			"admin/sales_process/tax_check", //매출증빙
			"admin/order_process/receipt_process", //매출증빙
			"admin/member_process/amail_send_set", //이메일 대량 발송
			"admin/statistic_process/ga_setting", //구글 애널리스틱 저장
			"admin/setting_process/seo", //설정 > SEO > 저장하기
			"admin/pg_process/lg", //설정 > 전자결제
			"admin/pg_process/kcp", //설정 > 전자결제
			"admin/pg_process/inicis", //설정 > 전자결제
			"admin/pg_process/allat", //설정 > 전자결제
			"admin/pg_process/kspay", //설정 > 전자결제
			"admin/pg_process/kakaopay", //설정 > 전자결제
			"admin/pg_process/payco", //설정 > 전자결제
			"admin/pg_process/paypal", //설정 > 전자결제
			"admin/pg_process/eximbay", //설정 > 전자결제
			"admin/setting_process/bank", //설정 > 무통장
			"admin/member_process/realname", //설정 > 회원 > 본인확인
			"admin/setting_process/video", //설정 > 동영상
			"admin/setting_process/sale", //설정 > 매출증빙
			"admin/setting_process/protect", //설정 > 보안/속도
			"admin/member_process/sms_auth", //회원 > SMS 보안키 등록
			"admin/design_process/layout", //아이 디자인
			"admin/design_process/apply_skin", //아이 디자인
			"admin/design_process/backup_skin", //아이 디자인
			"admin/design_process/copy_skin", //아이 디자인
			"admin/design_process/rename_skin", //아이 디자인
			"admin/design_process/delete_skin", //아이 디자인
			"admin/design_process/download_skin", //아이 디자인
			"admin/design_process/upload_skin", //아이 디자인
			"admin/design_process/sourceeditor_save", //아이 디자인
			"admin/design_process/eye_editor_save", //아이 디자인
			"admin/design_process/eye_editor_newpage", //아이 디자인
			"admin/design_process/eye_editor_filemtime", //아이 디자인
			"admin/design_process/tpl_file_name_chk", //아이 디자인
			"admin/design_process/tpl_file_delete", //아이 디자인
			"admin/design_process/file_name_chk", //아이 디자인
			"admin/design_process/image_edit", //아이 디자인
			"admin/design_process/image_insert", //아이 디자인
			"admin/design_process/popup_insert", //아이 디자인
			"admin/design_process/popup_edit", //아이 디자인
			"admin/design_process/copy_popup", //아이 디자인
			"admin/design_process/delete_popup", //아이 디자인
			"admin/design_process/flash_insert", //아이 디자인
			"admin/design_process/flash_edit", //아이 디자인
			"admin/design_process/delete_flash", //아이 디자인
			"admin/design_process/flash_add", //아이 디자인
			"admin/design_process/video_edit", //아이 디자인
			"admin/design_process/video_insert", //아이 디자인
			"admin/design_process/delete_video", //아이 디자인
			"admin/design_process/display_insert", //아이 디자인
			"admin/design_process/display_edit", //아이 디자인
			"admin/design_process/display_icon_upload", //아이 디자인
			"admin/design_process/display_quick_icon_upload", //아이 디자인
			"admin/design_process/delete_display", //아이 디자인
			"admin/design_process/copy_display", //아이 디자인
			"admin/design_process/topBar_design", //아이 디자인
			"admin/design_process/mainTopBar_edit", //아이 디자인
			"admin/design_process/deleteFiles", //아이 디자인
			"admin/design_process/createTabFile", //아이 디자인
			"admin/design_process/category_navigation_design", //아이 디자인
			"admin/design_process/brand_navigation_design", //아이 디자인
			"admin/design_process/location_navigation_design", //아이 디자인
			"admin/design_process/lastest_insert", //아이 디자인
			"admin/design_process/mobile_quick_design", //아이 디자인
			"admin/design_process/pc_quick_design_image_upload", //아이 디자인
			"admin/design_process/recomm_goods_edit", //아이 디자인
			"admin/design_process/banner_insert", //아이 디자인
			"admin/design_process/banner_directory_check", //아이 디자인
			"admin/design_process/banner_edit", //아이 디자인
			"admin/design_process/delete_banner", //아이 디자인
			"admin/design_process/popup_banner_directory_check", //아이 디자인
			"admin/design_process/popup_banner_edit", //아이 디자인
			"admin/design_process/set_upgrade_select", //아이 디자인
			"admin/batch_process/send_email", //이메일 발송
			"admin/batch_process/send_sms", //SMS 발송
			"admin/batch_process/sms_member_download" //SMS 발송
		);

		// config 커스텀 추가 영역
		$this->loadAdditionalConfig();

		/* SSL 적용 페이지 체크 */
		$this->load->model('ssl');
		$this->ssl->ssl_page_check();

	}

	protected function chk_mobile_env(){

		// 모바일 기기 접속여부
		$this->_is_mobile_agent = isMobilecheck($_SERVER['HTTP_USER_AGENT']);
		define("__ISMOBILE_AGENT__",$this->_is_mobile_agent);
		$this->template->assign("__ISMOBILE_AGENT__",$this->_is_mobile_agent);
		$this->template->assign("ISMOBILE_AGENT",$this->_is_mobile_agent);

		// 모바일 도메인 접속여부
		$this->_is_mobile_domain = preg_match("/^m\.|^www\.m\./",$_SERVER['HTTP_HOST']);
		$this->template->assign("__ISMOBILE_DOMAIN__",$this->_is_mobile_domain);

		if(!$this->_is_mobile_domain && !$this->_is_mobile_agent){
			$this->session->unset_userdata('setMode');
		}

		## 모바일 스킨 기준점 변경 :: 2018-12-27 lwh
		if($this->operation_type == 'light')	$_is_mobile_operation = $this->_is_mobile_agent;
		else									$_is_mobile_operation = $this->_is_mobile_domain;

		if($_is_mobile_operation)
			if(!$_GET['setMode']) $_GET['setMode'] = 'mobile';

		$this->mobileMode = $_is_mobile_operation ? true : false;

		// 쇼핑몰앱 접속여부
		$this->_is_mobile_app_agent = checkUserApp($_SERVER['HTTP_USER_AGENT']);
		$this->_is_mobile_app_agent_android = checkUserAppAndroid($_SERVER['HTTP_USER_AGENT']);
		$this->_is_mobile_app_agent_ios = checkUserAppIos($_SERVER['HTTP_USER_AGENT']);
		define("__ISMOBILE_APP_AGENT__",$this->_is_mobile_app_agent);
		$this->template->assign("__ISMOBILE_APP_AGENT__",$this->_is_mobile_app_agent);
		$this->template->assign("ISMOBILE_APP_AGENT",$this->_is_mobile_app_agent);
	}

	protected function set_header(){
		ini_set("default_charset", 'utf-8');
		ini_set('zlib.output_compression_level', 3 );

		$phpver		= phpversion();
		$useragent	= (isset($_SERVER["HTTP_USER_AGENT"])) ? $_SERVER["HTTP_USER_AGENT"] : $HTTP_USER_AGENT;
		if ($phpver >= '4.0.4pl1' && (strstr($useragent,'compatible') || strstr($useragent,'Gecko')))
		{
			//if (extension_loaded('zlib')) { ob_start('ob_gzhandler'); }
		}
		else if ( $phpver > '4.0' )
		{
			if (strstr($HTTP_SERVER_VARS['HTTP_ACCEPT_ENCODING'], 'gzip'))
			{
				if (extension_loaded('zlib'))
				{
					ob_start();
					ob_implicit_flush(0);
					header('Content-Encoding: gzip');
				}
			}
		}
		else
		{
			header('Content-Length: ' . ob_get_length());
		}

		// #28841 xss 공격 차단 19.02.13 kmj
		header("X-XSS-Protection: 1; mode=block");
	    header("Content-Type: text/html; charset=UTF-8");
	    header('P3P: CP="ALL CURa ADMa DEVa TAIa OUR BUS IND PHY ONL UNI PUR FIN COM NAV INT DEM CNT STA POL HEA PRE LOC OTC"');
	    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
	    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	    header("Cache-Control: no-store, no-cache, must-revalidate");
	    header("Cache-Control: post-check=0, pre-check=0", false);
	    header("Pragma: no-cache");

		/* 서버 환경체크 */
		$required_extensions = array('iconv','json','gd','curl','mbstring','mcrypt','soap'/*,'zlib'*/);
		$loaded_extensions = array_map('strtolower',get_loaded_extensions());
		$error = array();
		if(phpversion()<'5.2'){
			$error[] = ('Firstmall needs PHP version 5.2.');
		}
		foreach($required_extensions as $val){
			if(!in_array(strtolower($val),$loaded_extensions)){
				$error[] = ('Firstmall needs the ['.$val.'] PHP extension.');
			}
		}
		if($error) {
			foreach($error as $row){
				echo $row.'<br />';
			}
			exit;
		}
	}

	function get_config_system(){
		get_base_config_system();

		$this->template->assign("basic_currency", $this->config_system['basic_currency']);
		$this->template->assign("basic_currency_info", $this->config_currency[$this->config_system['basic_currency']]);
		$this->template->assign("config_currency", $this->config_currency);
		if(in_array($this->config_currency[$this->config_system['basic_currency']],array("USD","CNY","EUR"))) {
			$only_numberic_type = "onlyfloat";
		}else{
			$only_numberic_type = "onlynumber";
		}
		$this->template->assign("only_numberic_type", $only_numberic_type);
		$this->template->assign(array("service_code"=>SERVICE_CODE,"service_name"=>SERVICE_NAME));

		/* facebook 체크 */
		isfacebook();
		$this->template->assign("config_system",$this->config_system);

	}

	/* 기본 설정 로드 */
	public function get_config_basic(){
		$this->config_basic = config_load('basic');
		$this->config_basic['shopName']	= $this->config_system['admin_env_name'];
		$this->config_basic['domain']	= $this->config_system['domain'];

		//TITLE 없으면 SEO 개선된 기능으로 대체 @2016-05-03
		if( !(trim($this->config_basic['shopTitleTag'])) ) {
			if(!$this->meta_data){
				$this->template->include_('metaHeaderWrite');
				if( _IS_SHELL_MODE_ != 'Y' ) metaHeaderWrite();
			}
			if($this->meta_data['title']) $this->config_basic['shopTitleTag'] = $this->meta_data['title'];
		}

		//사업자 이메일 배열로 넘어오는 경우 빈값으로 출력함
		if( empty($this->config_basic['companyEmail'][0]) || empty($this->config_basic['companyEmail'][1])) {
			$this->config_basic['companyEmail'] = '';
		}

		$this->template->assign("config_basic",$this->config_basic);
	}

	function get_config_goodsImageSize(){
		$this->config_goodsImageSize = config_load('goodsImageSize');
		$this->template->assign("config_goodsImageSize",$this->config_goodsImageSize);
	}

	//GA통계 사용여부
	function get_config_GA(){
        // GA4 연동 추가
        $this->ga4_auth_commerce = false;
        $this->ga4_auth = config_load('GA4');

        if(serviceLimit('H_NFR')){
            // GA4 설정
            if ($this->ga4_auth['ga4_id'] && $this->ga4_auth['ga4_visit'] == 'Y' && $this->ga4_auth['ga4_commerce'] == "Y" ) {
                $this->ga4_auth_commerce = true;
            }
		}
        $this->ga4_auth['ga4_auth_commerce'] = $this->ga4_auth_commerce;
		$this->template->assign(array('ga4_auth' =>$this->ga4_auth));
	}

	function redirect_domain(){
		/*
		if($_SERVER['HTTP_HOST'] == $this->config_system['domain']) return true;
		$url = prep_url($this->config_system['domain'])."/".uri_string();
		header("Location: ".$url);
		*/
	}


	function volume_check($ajax=null){
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_limit_check();
		if(!$result['type']){
			if(!$ajax){
				$callback = "";
				openDialogAlert($result['msg'],700,340,'parent',$callback,array('hideButton'=>true));
				exit;
			}else{
				echo $result['msg'];
			}
		}
	}



	/**
	* 무료몰 제한체크
	* 마일리지유효기간
	* 포인트(유효기간포함)
	* 예치금(유효기간포함)
	* 프로모션코드
	* 관리자 수기주문
	* 사은품
	* 개인결제
	* 구매확정
	* 상품후기 마일리지
	* 대량구매
	**/
	public function checkfreecheck(){
		if( serviceLimit('H_NFR') ){//무료몰인경우 제한
			$this->isplusfreenot['code'] = SERVICE_CODE;
			$this->reserves = config_load('reserve');
			//마일리지유효기간
			//관리자 수기주문
			//사은품
			//개인결제
			//구매확정
			//상품후기 마일리지, //대량구매

			//포인트(유효기간포함) : 사용여부에 따라
			$this->isplusfreenot['ispoint']		= ($this->reserves['point_use'] == 'Y')?true:false;
			//포인트교환
			$this->isplusfreenot['isemoney_exchange']		= ($this->reserves['emoney_exchange_use'] == 'y')?true:false;

			//예치금(유효기간포함) : 사용여부에 따라
			$this->isplusfreenot['iscash']		= ($this->reserves['cash_use'] == 'Y')?true:false;
			//프로모션코드 : 사용여부에 따라
			$this->isplusfreenot['ispromotioncode']		= ($this->reserves['promotioncode_use'] == 'Y')?true:false;

			$this->template->assign('isplusfreenot',$this->isplusfreenot);
		}
		//오픈마켓 사용시 이미지 호스팅연결
		$openmarketuse = true;
		$this->template->assign('openmarketuse',$openmarketuse);
	}

	// 새로고침 부하 체크
	public function chk_refesh_load(){

		$chk_sec = 2; // n초
		$chk_cnt = 10; // n회
		$block_sec = 10; // 차단될경우 n초동안 접속불가

		$sess_name = 'fm_load_check';
		$sess_block_name = 'fm_load_block';
		$now = time();

		// 부하 예외 체크 path
		$except_path = [
			'/common/autocomplete',
			'/broadcast/touch_likes',
			'/font/json_font',
			'/goods/option_stock',
			'/coupon/goods_coupon_max',
			'/favicon.ico',
			'/common/ajax_category_favorite',
			'/common/goods_image_temporary_fileupload',
			'/common/image_temporary_fileupload',
			'/common/board_temporary_fileupload',
		];

		if(in_array($this->input->server('REDIRECT_QUERY_STRING'),$except_path)) return;
		if(preg_match("/^\/admin\/|^\/cosmos\/|^\/errdoc\/|^\/scm\//i",$_SERVER['REQUEST_URI'])) return;
		if(get_class($this)=='errdoc') return;

		$sess = (array)$_SESSION[$sess_name];

		$exists = false;
		$alert = false;

		foreach($sess as $i=>$row){
			if($row['uri']==$_SERVER['REQUEST_URI']){
				$exists = true;

				if(!is_array($row['times'])) $row['times'] = array();
				$row['times'][] = $now;

				$cnt = 0;
				foreach($row['times'] as $j=>$time){
					if($time >= $now-$chk_sec){
						$cnt++;
					}else{
						unset($row['times'][$j]);
					}
				}
				$sess[$i]['times']=array_values($row['times']);
				if($cnt>=$chk_cnt){
					$alert = true;
					break;
				}

			}else{
				$cnt = 0;
				foreach($row['times'] as $j=>$time){
					if($time < $now-$chk_sec){
						unset($row['times'][$j]);
					}
				}
				$sess[$i]['times']=array_values($row['times']);
			}

			if(!$row['times'] || !count($row['times'])) {unset($sess[$i]);}
		}

		if(!$exists){
			$sess[] = array('uri'=>$_SERVER['REQUEST_URI'],'times'=>array($now));
		}


		if($alert){
			$_SESSION[$sess_block_name] = $now;
			$remain_sec = $block_sec - ($now-$_SESSION[$sess_block_name]);
			$this->set_header();
			echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
			$msg = "과도한 접속으로 인한 차단 - {$chk_sec}초 동안 {$cnt}회 이상 접속 시도";
			echo "<script>alert('{$msg}');setTimeout(function(){document.location.reload();},'".($remain_sec*1000)."');</script>";
			echo "{$remain_sec}초동안 접속차단";
			exit;
		}

		if(!empty($_SESSION[$sess_block_name]) && $_SESSION[$sess_block_name]>$now-$block_sec){
			$remain_sec = $block_sec - ($now-$_SESSION[$sess_block_name]);
			$this->set_header();
			echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
			echo "<script>setTimeout(function(){document.location.reload();},'".($remain_sec*1000)."');</script>";
			echo "{$remain_sec}초동안 접속차단";
			exit;
		}

		$_SESSION[$sess_name] = array_values($sess);

	}

	public function get_config_skin(){
		/* 일반스킨 */
		$this->realSkin = $this->config_system['skin'];
		$this->workingSkin = $this->config_system['workingSkin'];

		/* 모바일 쇼핑몰 스킨*/
		$this->realMobileSkin = $this->config_system['mobileSkin'];
		$this->workingMobileSkin = $this->config_system['workingMobileSkin'];

		/* 페이머스용 스킨 */
		$this->realFammerceSkin = $this->config_system['fammerceSkin'];
		$this->workingFammerceSkin = $this->config_system['workingFammerceSkin'];

		/* 아이디자인에서 처리할 스킨 */
		if		($this->mobileMode)			$this->designWorkingSkin = $this->workingMobileSkin;
		elseif	($this->fammerceMode)		$this->designWorkingSkin = $this->workingFammerceSkin;
		else 								$this->designWorkingSkin = $this->workingSkin;

		$this->realMobileSkinVersion = $this->config_system['mobileSkinVersion'];
		$this->workingMobileSkinVersion = $this->config_system['workingMobileSkinVersion'];

		if	(!$this->realMobileOriginalSkin)
			$this->realMobileSkinVersion = get_skin_version($this->realMobileSkin,'mobileSkinVersion');
		if	(!$this->workingMobileSkinVersion)
			$this->workingMobileSkinVersion = get_skin_version($this->workingMobileSkin,'workingMobileSkinVersion');
	}

	public function getPolicyPrivacy(){
		$this->member = config_load('member');
		$this->joinform = config_load('joinform');
		$this->board = config_load('board');
		$this->scm = config_load('scm');
		$this->order = config_load('order');
	}

	// 데모사이트 기능 제한 함수
	public function demoFunctionCheck($procUrl){

		if(in_array($procUrl,$this->functionLimitUri)){
			$actionJs = js('parent.servicedemoalert("use_f");');
			echo $actionJs;
			exit;
		}
	}

	// 기본 config에서 추가로 로드 되어야할 config 파일을 로드하는 함수
	protected function loadAdditionalConfig() {
		// config 파일명을 아래 배열에 추가한다
		$configFiles = ['broadcast'];

		foreach($configFiles as $fileName) {
			$this->load->config($fileName);
		}
	}

}

// 커스텀 파일이 있는 경우 커스텀파일에서 현파일을 로딩하여 상속 받아 사용한다.
if(!customBaseCall(__FILE__)) { class common_base extends common_base_original {} }

// END
/* End of file common_base.php */
/* Location: ./app/base/common_base.php */