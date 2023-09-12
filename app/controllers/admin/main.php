<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class main extends admin_base {

	public function __construct() {
		parent::__construct();

		$this->cach_file_path	= $_SERVER['DOCUMENT_ROOT'] . '/data/cach/';
		$this->cach_file_url		= '../../data/cach/';
		$this->cach_file_name	= 'admin_main_index.html';

		// 운영자별 페이지 생성
		$this->cach_stat_file	= 'admin_main_stats_'.$this->managerInfo['manager_id'].'.html';
	}

	public function main_index()
	{
		redirect("/admin/main/index");
	}

	// 메인화면
	public function index()
	{
		$this->load->model('usedmodel');
		$this->template->assign("config_basic",$this->config_basic);

		# 네이버페이 1.0 사용자들에게만 공지팝업 띄우기 옵션 추가 @2016-08-01 pjm
		$cfg_naverpay = config_load('navercheckout');
		if(!trim($cfg_naverpay['version']) && in_array($cfg_naverpay['use'],array('y','test'))) $cfg_naverpay['version'] = "1.0";

		/* MY 서비스 바 */
		$params['cfg_naverpay'] = $cfg_naverpay;
		$this->get_main_myservice_bar($params);

		/* 진행중인 이벤트 */
		$params = array();
		$this->load->model('eventmodel');
		$field_str						= "count(*) cnt";
		$params['start_date<=']	= date('Y-m-d H:i:s');
		$params['end_date>=']		= date('Y-m-d H:i:s');
		$data								= $this->eventmodel->get($params, $field_str)->row_array();
		$eventCount					= $data['cnt'];

		/* 진행중인 사은품 이벤트 */
		$params = array();
		$this->load->model('giftmodel');
		$field_str						= "count(*) cnt";
		$params['start_date<=']	= date('Y-m-d H:i:s');
		$params['end_date>=']		= date('Y-m-d H:i:s');
		$data								= $this->giftmodel->get($params, $field_str)->row_array();
		$giftCount						= $data['cnt'];

		/* 매출증빙 */
		$params = array();
		$this->load->model('salesmodel');
		$params['tstep'][]				= '1';
		$params['action_mode']	= 'count';
		$data					= $this->salesmodel->sales_list($params)->row_array();
		$saleCount			= $data['cnt'];

		/* 서비스기간 남은일수 */
		$expireDayTime		= strtotime($this->config_system['service']['expire_date']);
		$todayTime				= strtotime(date('Y-m-d'));
		$expireDay				= date("Y년 m월 d일", $expireDayTime);
		$remainExpireDay	= round( ($expireDayTime-$todayTime)/(3600*24) );

		/* 최대 용량 */
		$maxDiskSpace		= $this->usedmodel->get_disk_space_format($this->config_system['service']['disk_space']);
		$maxDiskSpace		= str_replace('MB','',$maxDiskSpace);
		if($maxDiskSpace > 1000){
			$maxDiskSpace		= round($maxDiskSpace/1000*100)/100;
			$maxDiskSpace		.= 'GB';
		}else{
			$maxDiskSpace		.= 'MB';
		}

		/* 사용 용량 */
		$usedDiskSpace = $this->usedmodel->get_disk_space_format($this->config_system['usedDiskSpace']);
		$result = $this->usedmodel->used_limit_check();

		/* 디스크 사용율 */
		$usedSpacePercent = $this->usedmodel->get_used_space_percent();

		/* 트래픽제한 */
		$trafficLimit = $this->config_system['service']['traffic'];

		/* 호스팅 구분 (HOSTING / SERVERHOSTING / CLOUD / SH_SASR ) */
		$hosting_service 	= $this->config_system['service']['hosting_service'];
		$hosting_code 		= $this->config_system['service']['hosting_code'];

		/* 통계 */
		$caching_time	= $this->chk_stats_caching();

		/* 출고예약량 */
		$cfg_reservation = config_load('reservation');
		$this->template->assign(array(
			'expireDay'					=> $expireDay,
			'remainExpireDay'		=> $remainExpireDay,
			'maxDiskSpace'			=> $maxDiskSpace,
			'maxDiskSpaceGiga'		=> $maxDiskSpaceGiga,
			'usedDiskSpace'			=> $usedDiskSpace,
			'usedSpacePercent'		=> $usedSpacePercent,
			'trafficLimit'			=> $trafficLimit,
			'hosting_service'		=> $hosting_service,
			'hosting_code'			=> $hosting_code,
			'cfg_reservation'		=> $cfg_reservation,
			'eventCount'			=> $eventCount,
			'giftCount'				=> $giftCount,
			'saleCount'				=> $saleCount,
			'main'					=> true
		));

		$this->admin_menu();
		$this->tempate_modules();

		// 트위터 기본앱 관련 공지 #19795 2018-06-27 hed
		// 페이스북 공지를 그대로 활용
		if($this->arrSns['key_t'] == "ifHWJYpPA2ZGYDrdc5wQ" && $this->arrSns['use_t'] == "1" && date('Ymd') <= '20180713'){
			$facebook_notice['content']	= readurl(get_connet_protocol()."interface.firstmall.kr/firstmall_plus/request.php?cmd=getGabiaPannel&code=twitter_notice");
			$facebook_notice['title']	= '트위터 기본앱 서비스 중단 안내';
			$facebook_notice['width']	= 800;
			$facebook_notice['height']	= 800;
		} else {
			$facebook_notice = 0;
		}

		$this->template->assign('facebook_notice', $facebook_notice);
		$this->template->assign('npayver',"npay".$cfg_naverpay['version']);
		$this->template->assign('last_reload',$caching_time);
		$this->template->assign(array('cfg_reservation',$cfg_reservation));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	// 2013-10-25 lwh 트래픽 데이터 호출
	public function get_traffic_data($domain){
		if	($this->config_system['service']['hosting_code'] == 'F_SH_X'){
			$decode_arr['u']['limits']	= '0KB';
			$decode_arr['u']['usages']	= '0KB';
			$decode_arr['u']['state']	= '0';
		}else{
			if( !serviceLimit('H_EXAD') ) {//무료/임대
				$decode_arr['u'][]	= 'FR';
			}else{
				$this->load->helper('readurl');
				$requestUrl = "http://traffic.firstmall.kr/traffic.php";
				$json_traffic = readurl($requestUrl,array('domain' => $domain));
				$decode_arr = json_decode($json_traffic,true);
			}
		}

		return $decode_arr;
	}
	// 2013-10-25 lwh 트래픽 재 데이터 호출
	public function re_traffic_data(){
		if	($this->config_system['service']['hosting_service'] == 'CLOUD'){
			$decode_arr['u'][]	= 'CLOUD';
		}elseif	($this->config_system['service']['hosting_service'] != 'CLOUD' && $this->config_system['service']['hosting_code'] == 'F_SH_X'){
			/*
			$decode_arr['u']['limits']	= '0KB';
			$decode_arr['u']['usages']	= '0KB';
			$decode_arr['u']['state']	= '0';
			*/
			$decode_arr['u'][]	= 'OUTSIDE';
		}else{
			if( !serviceLimit('H_EXAD') || $this->config_system['service']['hosting_service'] != 'HOSTING' || preg_match("/^F_SH_/", $this->config_system['service']['hosting_code']) ) {//무료/임대
				$decode_arr['u'][]	= 'FR';
			}else{
				$this->load->helper('readurl');
				$requestUrl = "http://traffic.firstmall.kr/traffic.php";
				$json_traffic = readurl($requestUrl,array('domain' => $this->input->get('domain')));
				$decode_arr = json_decode($json_traffic,true);
			}
		}

		echo implode($decode_arr['u'],"|");
	}

	public function get_main_myservice_bar($params)
	{
		//############ 서비스 설정 ##########//
		/*
			* servicetxt
			0 = 신청이미지 <img src='../skin/default/images/main/btn_s_app.gif' />
			1 = 사용중
			2 = 사용(무료)
			3 = 사용(유료)
			4 = 발행안함
			5 = CSS 사용중
			6 = 미사용
			7 = 설정

			그외 = servicetxt 그대로 표현
		*/

		$servicecnt = 0;

		/* 카카오 알림톡 */
		$this->load->model('kakaotalkmodel');
		$config_kakaotalk = $this->kakaotalkmodel->get_service();
		$serviceuse = "<span class='servicetxt_kko'></span>";
		$servicecnt++;
		$serviceHtml[$servicecnt]['name'] = "알림톡";
		$serviceHtml[$servicecnt]['link'] = "../member/kakaotalk_charge";
		$serviceHtml[$servicecnt]['servicetxt'] = $serviceuse;

		/* 문자 서비스 */
		$serviceuse = "<span class='servicetxt_sms'></span>";
		$servicecnt++;
		$serviceHtml[$servicecnt]['name'] = "SMS";
		$serviceHtml[$servicecnt]['link'] = "../member/sms_charge";
		$serviceHtml[$servicecnt]['servicetxt'] = $serviceuse;

		/* 이프두 사용여부 */
		$this->load->library('ifdolibrary');
		$ifdo_used = $this->ifdolibrary->check_used();
		if($ifdo_used){
			$serviceuse			= '사용';
		}else{
			$serviceuse			= '미사용';
		}
		$servicecnt++;
		$serviceHtml[$servicecnt]['name'] = "이프두";
		$serviceHtml[$servicecnt]['link'] = '../ifdo_marketing/config';
		$serviceHtml[$servicecnt]['servicetxt'] = $serviceuse;

		$this->template->assign('addService', $addService);
		$this->template->assign('serviceHtml', $serviceHtml);
		
	}

	/* 공지사항 영역 Define */
	public function json_main_news_area(){
		$this->load->helper('text');
		$this->load->library('SofeeXmlParser');
		$channel	= $_POST['channel'];
		$today	= date("Y-m-d",time());
		switch($channel){
			case "notice" :
				$rss_url	= get_connet_protocol()."firstmall.kr/ec_hosting/rss/_notice/rss.php?channel=notice&solution=firstmall_plus&limit=6";
				break;
			case "upgrade" :
				$rss_url	= get_connet_protocol()."firstmall.kr/ec_hosting/rss/_notice/rss.php?channel=upgrade&solution=firstmall_plus&shopSno={$this->config_system['shopSno']}&service_type=".SERVICE_CODE."_GL&limit=6";
				break;
			case "upgrade_news" :
				$rss_url	= get_connet_protocol()."firstmall.kr/ec_hosting/rss/_notice/rss.php?channel=upgrade_news&solution=firstmall_plus&limit=6";
				break;
			case "education" :
				$rss_url	= get_connet_protocol()."firstmall.kr/ec_hosting/rss/_notice/rss.php?channel=education&solution=firstmall_plus&shopSno={$this->config_system['shopSno']}&service_type=".SERVICE_CODE."&limit=6";
				break;
		}
		$xmlParser = new SofeeXmlParser();
		$xmlParser->parseFile($rss_url);
		$tree = $xmlParser->getTree();
		$mainNewsNoticeList = $tree['rss']['channel']['item'];
		foreach($mainNewsNoticeList as $k => $data)
		{
			$data['pubDateStatus']			= 'ing';
			$data['pubDateStatusMsg']	= '접수';
			if( $today >= $data['pubDate']['value'] )
			{
				$data['pubDateStatus']			= 'end';
				$data['pubDateStatusMsg']	= '마감';
			}
			$data['pubDate']['value']   = date('m.d',strtotime($data['pubDate']['value']));
			$data['title']['value']		= strip_tags($data['title']['value']);
			if(!isset($data['link'])) $data['link']['value'] = '';
			$mainNewsNoticeList[$k]	= $data;

		}
		echo json_encode($mainNewsNoticeList);
	}

	/* 관리자메모 영역 Define */
	public function _define_admin_memo_area(){
		$this->template->define(array('admin_memo_area'=>$this->skin."/main/_admin_memo_area.html"));
	}

	// 카카오 알림톡 건수 조회
	public function get_kt_info(){
		$this->load->model('kakaotalkmodel');
		$config_kakaotalk = $this->kakaotalkmodel->get_service();
		$data['getType']	= 'C';
		$data['year']		= date('Y');
		$kakaotalk_info		= $this->kakaotalkmodel->get_charge_log($data);

		echo json_encode($kakaotalk_info);
	}

	public function get_sms_info(){
		/* SMS 건수 */
		include_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/sms.class.php";
		$auth = config_load('master');
		$sms_id = $this->config_system['service']['sms_id'];
		$sms_api_key = $auth['sms_auth'];

		//$sms_send	= new SMS_SEND();
		$gabiaSmsApi = new gabiaSmsApi($sms_id,$sms_api_key);

		$params	= "sms_id=" . $sms_id . '&sms_pw=' . md5($sms_id);
		$params = makeEncriptParam($params);
		$limit	= $gabiaSmsApi->getSmsCount();
		$sms_chk = $sms_id;

		$int_sms = (int) $limit;

		if($int_sms<50){
			$popicon = "charge";
		}

		$return = array();
		if ($popicon=="charge") {
			$return['html'] = sprintf("<div class='myservice_area'><div class='myservice_%s'><img src='/admin/skin/default/images/main/icon_%s.gif' /></div>",$popicon,$popicon);
		} else {
			$return['html'] = "<div class='myservice_area'></div>";
		}

		$return['txt_cnt'] = $int_sms;

		echo json_encode($return);
	}


	/* 게시판마일리지지급 Define */
	public function _define_board_emoney_form(){
		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		$this->template->assign('reserve_goods_review',$reserves['reserve_goods_review']);
		$this->template->define(array('emoneyform'=>$this->skin.'/board/_emoney.html'));
	}

	public function login(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 메인 페이지 통계 캐쉬 생성 시간 체크 */
	public function chk_stats_caching()
	{
		$cache_file_path	= $this->cach_file_path . $this->cach_stat_file;
		//if (!file_exists($cache_file_path) || strtotime('-4 hour') > filemtime($cache_file_path))
		$this->main_stats_caching();

		return filemtime($cache_file_path);
	}

	/* 메인 페이지 통계 캐쉬 처리 */
	public function main_stats_caching()
	{
		ob_start();
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('advanced_statistic');
		if(!$result['type']){
			$this->advanced_statistic_limit	= 'y';
		}
		$this->load->model('statsmodel');
		$result	= $this->statsmodel->get_main_statistic_json();
		echo json_encode($result);

		$cach_stats	= ob_get_contents();
		ob_end_clean();

		$cache_file_path	= $this->cach_file_path . $this->cach_stat_file;

		$file_obj	= fopen($cache_file_path, 'w+');
		if	(!$file_obj){
			$dir_name	= dirname($cache_file_path);
			if( !is_dir($dir_name) )	@mkdir($dir_name);
			@chmod($dir_name,0777);
			$file_obj	= fopen($cache_file_path, 'w+');
		}

		fwrite($file_obj, $cach_stats);
		fclose($file_obj);
	}

	/* 메인 페이지 통계 캐쉬 제거 */
	public function main_stats_cach_delete()
	{
		// 운영자별 페이지 생성 체크
		$cache_file_path	= $this->cach_file_path . $this->cach_stat_file;
		if	(file_exists($cache_file_path)){
			@unlink($cache_file_path);
		}
		$cache_file_path	= ROOTPATH . 'data/cach/action_alert.html';
		if	(file_exists($cache_file_path)){
			@unlink($cache_file_path);
		}
		echo json_encode(array('result'=>'OK'));
	}

	public function json_main_stats()
	{
		$filePath	= $this->cach_file_path . $this->cach_stat_file;
		if(file_exists($filePath)) {
			$handle			= fopen($filePath, "r");
			$fileContents	= fread($handle, filesize($filePath));
			fclose($handle);

			if($_GET['mode'] == 'debug'){
				debug(json_decode($fileContents));
				exit;
			}

			if(!empty($fileContents)) {
				echo $fileContents;
			}
		}
	}

	public function popup_change_pass()
	{
		$this->template->define(array('tpl'=>$this->skin."/main/popup_change_pass.html"));
		$this->template->print_("tpl");
	}

	// 부가서비스 이슈 알림
	public function get_notify_info(){

		$this->load->model('usedmodel');

		$return = array();

		/* 페이스북 좋아요 연결방식 변경권유 */
		$snssocial = ($this->arrSns)?$this->arrSns:config_load('snssocial');
		if( $snssocial['fb_like_box_type']=='OP' && ( !($snssocial['key_f'] != '455616624457601' && $snssocial['facebook_publish_actions']) || $snssocial['key_f'] == '455616624457601' ) ) {//전용앱중에서 오픈그라피제공앱은 제외@2015-07-14
			$return['fb_like_box_type'] = $snssocial['fb_like_box_type'];
		}

		$cfg_addservie_notify = config_load('addservie_notify');

		/* SMS 건수 */
		$sms = commonCountSMS();
		if($cfg_addservie_notify['sms_primary_complete']!='Y' && 31 <= $sms && $sms <= 50){
			$return['remain_sms'] = (int)preg_replace("/[^0-9]/",'',$sms);
			config_save('addservie_notify',array('sms_primary_complete'=>'Y'));
		}
		if($cfg_addservie_notify['sms_finally_complete']!='Y' && 1 <= $sms && $sms <= 30){
			$return['remain_sms'] = (int)preg_replace("/[^0-9]/",'',$sms);
			config_save('addservie_notify',array('sms_finally_complete'=>'Y'));
		}

		/* SMS 발신 번호 등록*/
		if($sms){
			$send_sms_phone = getSmsSendInfo();
			if(!$send_sms_phone){
				$return['send_sms'] = true;
			}
		}

		/* 자동입금확인 */
		$edate = $this->config_system['autodeposit_count'];
		$remain = round((strtotime($edate)-time()) / (3600*24));
		if($cfg_addservie_notify['autodeposit_primary_complete']!='Y' && 11 <= $remain && $remain <= 20){
			$return['remain_autodeposit'] = $this->config_system['autodeposit_edate'];
			config_save('addservie_notify',array('autodeposit_primary_complete'=>'Y'));
		}
		if($cfg_addservie_notify['autodeposit_finally_complete']!='Y' && 1 <= $remain && $remain <= 10){
			$return['remain_autodeposit'] = $this->config_system['autodeposit_edate'];
			config_save('addservie_notify',array('autodeposit_finally_complete'=>'Y'));
		}

		/* 굿스플로 */
		$goodsflow = $this->usedmodel->used_get_service_info('view');
		if($cfg_addservie_notify['goodsflow_primary_complete']!='Y' && 31 <= $goodsflow && $goodsflow <= 50){
			$return['remain_goodsflow'] = (int)preg_replace("/[^0-9]/",'',$goodsflow);
			config_save('addservie_notify',array('goodsflow_primary_complete'=>'Y'));
		}
		if($cfg_addservie_notify['goodsflow_finally_complete']!='Y' && 1 <= $goodsflow && $goodsflow <= 30){
			$return['remain_goodsflow'] = (int)preg_replace("/[^0-9]/",'',$goodsflow);
			config_save('addservie_notify',array('goodsflow_finally_complete'=>'Y'));
		}

		if(!preg_match("/^F_SH_/",$this->config_system['service']['hosting_code']) && $this->config_system['service']['hosting_service'] != "CLOUD"){

			/*lek 오늘하루그만보기 추가*/
			$cookie = get_cookie('isChk');
			$disk_percent	= $this->usedmodel->get_used_space_percent();
			if($disk_percent >= 90 && empty($cookie))	$return['space_percent']	= $disk_percent;
		}

		/* 우체국택배 */
		if($cfg_addservie_notify['epost_message_complete']=='N'){
			config_save('addservie_notify',array('epost_message_complete'=>''));
			$return['epost_complete'] = 'Y';
		}
		
		// 인증후에서 수신 인증서가 없을 경우, 향후 수동 설치된 인증서 여부를 파악하기 위해서는 기존 ssl정보를 추적해야함 by hed
		$this->load->library('ssllib');
		$sslEnv = $this->ssllib->getSslEnvironment();
		if(empty($sslEnv['data'])){
			$return['ssl_notify'] = 'Y';
		}

		if($return){
			$this->template->assign("return",$return);
			$this->template->define(array('tpl'=>$this->skin."/main/_main_notify_popup.html"));
			$return['html'] = $this->template->fetch("tpl");
		}else{
			$return['html'] = '';
		}

		echo json_encode($return);
	}

	//데모 기능제한 팝업
	public function main_demo()
	{
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	//무료몰 기능제한 팝업
	public function main_free()
	{
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}
}

/* End of file main.php */
/* Location: ./app/controllers/admin/main.php */
