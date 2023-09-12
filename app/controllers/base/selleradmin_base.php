<?php if ( ! defined("BASEPATH")) exit("No direct script access allowed");
require_once(APPPATH ."controllers/base/common_base".EXT);

class selleradmin_base_original extends common_base {
	var $AdminMenu			= array();
	var $skin;
	var $managerInfo;
	var $auth_msg			= "권한이 없습니다.";

	public function __construct() {
		parent::__construct();
		$this->load->model('providermodel');
		checkEnvironmentValidation();

		/* 만기도래 체크(로그인화면 제외) */
		$file_path = $this->config_system['sadminSkin']."/common/blank.html";
		$this->template->define(array('warningScript'=>$file_path));

		if(!preg_match("/^selleradmin\/login(^_)*/",uri_string()) && !preg_match("/^selleradmin\/main_index/",uri_string()) && uri_string()!='selleradmin'){
			warningExpireDate();
		}

		### 데모세션처리
		$this->demo = false;
		$this->set_demo();

		define('__SELLERADMIN__',true);//입점사페이지 if( defined('__SELLERADMIN__') === true ) {

		/* 현재 언어 저장 */
		$this->template->assign(array('language'=>$this->config->item('language')));

		$auto_logout = config_load('autoLogout');
		$this->template->assign(array('autoLogout'=>$auto_logout));

		$this->skin = 'default';//$this->config_system['sadminSkin'];

		/* PC용 스킨 */
		$this->realSkin = isset($this->config_system['skin']) ? $this->config_system['skin'] : null;
		$this->workingSkin = isset($this->config_system['workingSkin']) ? $this->config_system['workingSkin'] : null;

		/* 모바일용 스킨 */
		$this->realMobileSkin = isset($this->config_system['mobileSkin']) ? $this->config_system['mobileSkin'] : null;
		$this->workingMobileSkin = isset($this->config_system['workingMobileSkin']) ? $this->config_system['workingMobileSkin'] : null;

		/* 페이머스용 스킨 */
		$this->realFammerceSkin = isset($this->config_system['fammerceSkin']) ? $this->config_system['fammerceSkin'] : null;
		$this->workingFammerceSkin = isset($this->config_system['workingFammerceSkin']) ? $this->config_system['workingFammerceSkin'] : null;

		/* 아이디자인에서 처리할 스킨 */
		if		($this->mobileMode)		$this->designWorkingSkin = $this->workingMobileSkin;
		elseif	($this->fammerceMode)	$this->designWorkingSkin = $this->workingFammerceSkin;
		else 							$this->designWorkingSkin = $this->workingSkin;

		### MANAGER SESSION
		$this->managerInfo = $this->session->userdata('manager');
		$this->template->assign(array('managerInfo' => $this->managerInfo));

		### PROVIDER SESSION
		$this->providerInfo = $this->session->userdata('provider');
		$this->template->assign(array('providerInfo' => $this->providerInfo));

		### 입점사 접속IP 체크
		$this->load->model('protectip');
		$this->protectip->protect_ip_provider($this->providerInfo['provider_seq']);

		### ADMIN SESSION TYPE
		$this->adminSessionType = 'provider';
		$this->template->assign(array('adminSessionType' => $this->adminSessionType));

		if (! isset($this->providerInfo['provider_seq']) ) {
			if( !strpos($this->template_path(),'login') &&  !strpos($this->template_path(),'facebook') ){
				redirect("/selleradmin/login/index");
				exit;
			}
		}

		/* 사용 도메인 정의 */
		$host = $_SERVER['HTTP_HOST'];
		$host = preg_replace('/^m\./','', $host);
		$this->pcDomain = $host;
		$this->template->assign('pcDomain',$this->pcDomain);
		if($this->config_system['operation_type'] == 'light')	$this->mobileDomain = $host;
		else													$this->mobileDomain = "m.".preg_replace("/^www\./","",$host);
		$this->template->assign('mobileDomain',$this->mobileDomain);

		/* 페이스북 연결 여부 */
		$page_id_f_ar				= (isset($this->arrSns['page_id_f']))?explode(",",$this->arrSns['page_id_f']):'';
		$page_name_ar			= (isset($this->arrSns['page_name_f']))?explode(",",$this->arrSns['page_name_f']):'';
		$page_url_ar				= (isset($this->arrSns['page_url_f']))?explode(",",$this->arrSns['page_url_f']):'';
		$page_app_link_f_ar	= (isset($this->arrSns['page_app_link_f']))?explode(",",$this->arrSns['page_app_link_f']):'';
		if($page_id_f_ar){
			foreach($page_id_f_ar as $pagen=>$v) {
				if($page_id_f_ar[$pagen] && $page_app_link_f_ar[$pagen]) {
					$this->template->assign('facebookConnected',1);
					$this->template->assign('facebookapp_url',str_replace("]","",str_replace("[","",$page_app_link_f_ar[$pagen])));
					break;
				}
			}
		}
		$this->load->model('authmodel');
		$cfg_goods = config_load("goods");
		if( $cfg_goods['ucc_id'] && $cfg_goods['ucc_domain'] && $cfg_goods['ucc_key'] ) {
			$this->template->assign('video_use',1);
		}

		//멀티 버젼
		$env_all			= false;
		$env_except_list	= array('selleradmin/setting/goods','selleradmin/setting/multi');
		$this_admin_env		= array();
		$env_list			= array();

		$uri_str = uri_string();
		foreach($env_except_list as $except){
			if	(strpos($uri_str,$except) !== false)
				$env_all	= true;
		}

		if	(!$env_all){
			$env_query								= $this->db->query("select shopSno,admin_env_name,currency,temp_domain,domain,language from fm_admin_env");
			$env_data								= $env_query->result_array();
			$lang_arr = array(
				'KR' => '한국어','JP' => '일본어','CN' => '중국어','US' => '영어',
			);
			foreach($env_data as $v){
				if	(!$v['domain']) $v['domain']	= $v['temp_domain'];
				if	($v['shopSno'] == $this->config_system['shopSno']){
					$this_admin_env['env_name']		= $v['admin_env_name'];
					$this_admin_env['currency']		= $v['currency'];
					$this_admin_env['language']		= $v['language'];
					$this_admin_env['lang']			= $lang_arr[$v['language']];
					$v['this_admin']				= 'y';
				}
				$v['lang']						= $lang_arr[$v['language']];
				$env_list[]							= $v;
			}
		}

		$this->template->assign(array('env_list'=>$env_list));
		$this->template->assign(array('this_admin_env'=>$this_admin_env));
		$this->template->assign(array('env_all'=>$env_all));
		
		$number = array();
		
		$arrBasic = ($this->config_basic)?$this->config_basic:config_load('basic');
		$this->template->assign(array('number'=>$arrBasic));

		// 슈퍼관리자 예외처리 :: 2017-04-06 lwh
		$manager_info = $this->session->userdata('manager');
		if($manager_info['manager_yn'] != 'Y'){
			/* 비밀번호 체크 */
			$is_change_pass_required = false;
			$is_change_pass = false;

			// 입점사 부관리자 접근 시 실제 접근한 관리자 seq로 체크
			if($this->providerInfo['sub_provider_seq'] && $this->providerInfo['manager_yn'] != 'Y'){
				$chk_provider_seq = $this->providerInfo['sub_provider_seq'];
			}else{
				$chk_provider_seq = $this->providerInfo['provider_seq'];
			}

			$change_pass_day = $this->providermodel->chk_change_pass_day($chk_provider_seq);
			if($change_pass_day>180){
				$is_change_pass_required = true;
			}elseif($change_pass_day>90){
				$is_change_pass = true;
			}

			$this->template->assign('is_change_pass_required',$is_change_pass_required);
			$this->template->assign('is_change_pass',$is_change_pass);
		}

		/*데모 사이트 체크*/

		//입점몰 체크 보안키 로그인 체크
		$adCheck = serviceLimit('H_AD');
		
		if($this->demo){
			$this->demoFunctionChk = true;
			if($adCheck){
				$this->demoChk = true;
			}
		}

		$this->template->assign('demoChk',$this->demoChk);
		$this->template->assign('functionLimit',$this->demoFunctionChk);
		if($this->demoFunctionChk) $this->demoFunctionCheck(uri_string());
		/*데모 사이트 체크*/

		// LNB 메뉴 노출 여부
		$this->load->library('bookmarklibrary');
		$lnb_close = $this->bookmarklibrary->getLnbClose();
		$this->template->assign('lnb_close_yn', $lnb_close ? 'y' : 'n');
		$this->template->assign('lnb_close_seq', $lnb_close['seq']);
	}

	// 관리자 메뉴 로딩
	public function admin_menu(){
		$this->load->model("admin_menu");

		$adminMenuCurrent = $this->uri->rsegments[1];
		$adminSubMenuCurrent = $this->uri->rsegments[2];	
		$adminURLCurrent = $adminMenuCurrent."/".$adminSubMenuCurrent;

		// 배송정책 관련 메뉴고정 :: 2017-09-18 lwh
		if	($adminMenuCurrent == 'setting' && ($this->uri->rsegments[2] == 'shipping_group' || $this->uri->rsegments[2] == 'shipping_group_regist' || $this->uri->rsegments[2] == 'delivery_company')) {
			$adminMenuCurrent = 'shipping';
		}

	

		if($adminMenuCurrent == "excel_spout") {
			if($_GET['category'] == 1){
				$adminMenuCurrent = 'goods';
			}else if($_GET['category'] == 2){
				$adminMenuCurrent = 'order';
			}else if($_GET['category'] == 3){
				$adminMenuCurrent = 'member';
			}
			$adminURLCurrent = $adminMenuCurrent."/".$adminSubMenuCurrent;
		}

		if($adminSubMenuCurrent=="regist" && $_GET['package_yn']){
			$adminURLCurrent = $adminMenuCurrent."/".$adminSubMenuCurrent.'?package_yn=y';			
		}	
		

		if($adminSubMenuCurrent =="multi_basic") $adminURLCurrent = $adminMenuCurrent."/".$adminSubMenuCurrent.'?no=1';	


		// 구정산 마이그레이션 안했으면 메뉴 제거
		$accountall_setting = config_load('accountall_setting');
		if (!$accountall_setting['old_accountall_display']) {
			$this->admin_menu->except_old_accountall_menu();
		}

		$admin_menu =  $this->admin_menu->arr_menu;
		foreach($admin_menu as $k => $v) {
			if($k == 'shipping'){
				$admin_menu[$k]['folders'][0] = 'shipping';
			}
		}		

		$this->template->assign(array(
			'adminMenu' => $admin_menu,
			'adminMenuLimit' => 5,
			'adminMenuCurrent' => $adminMenuCurrent,
			'adminSubMenuCurrent' => $adminSubMenuCurrent,
			'adminURLCurrent' => $adminURLCurrent
		));

		//$this->load->helper('cookie');
	}

	// 디자인 모듈 로딩
	public function tempate_modules(){

		$filePath = APPPATH."../selleradmin/skin/".$this->skin."/_modules/";
		$map = directory_map($filePath);

		foreach($map as $dir => $dirRow) {
			if(is_array($dirRow)) {
				$dir	= preg_replace('/\/$/', '', $dir);
				foreach($dirRow as $modulePath) {
					$modulesList[$dir."_".substr($modulePath,0,-5)] = $this->skin."/_modules/".$dir."/".$modulePath;
				}
			}
		}
		$this->template->define($modulesList);
	}

	public function template_path(){
		return $this->skin."/".implode('/',$this->uri->rsegments).".html";
	}

	### 데모세션처리
	public function set_demo(){
		$filename = APPPATH."helpers/demo_helper".EXT;
		if(file_exists($filename)){
			$this->load->helper('demo');
		}
	}

}

// 커스텀 파일이 있는 경우 커스텀파일에서 현파일을 로딩하여 상속 받아 사용한다.
if(!customBaseCall(__FILE__)) { class selleradmin_base extends selleradmin_base_original {} }

// END
/* End of file selleradmin_base.php */
/* Location: ./app/base/selleradmin_base.php */