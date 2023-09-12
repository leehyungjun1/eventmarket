<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);
require_once(APPPATH.'/libraries/Spout/Autoloader/autoload.php'); //excel library

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Writer\Style\Color;
use Box\Spout\Writer\Style\Border;
use Box\Spout\Writer\Style\BorderBuilder;

class setting extends selleradmin_base {

	public function __construct() {
		parent::__construct();

		$this->load->library('validation');
		$this->template->assign('APP_USE',   $this->__APP_USE__);
		$this->template->assign('APP_ID',    $this->__APP_ID__);
		$this->template->assign('APP_SECRET',  $this->__APP_SECRET__);
		$this->template->assign('APP_PAGE',   $this->__APP_PAGE__);

		$this->template->define(array('require_info'=>$this->skin."/setting/_require_info.html"));
		$this->template->define(array('setting_menu'=>$this->_setting_menu_template_path()));

		$setting_menu = $this->uri->rsegments[count($this->uri->rsegments)];
		if($setting_menu=='manager_reg') $setting_menu = 'manager';
		if($setting_menu=='shipping_group_regist') $setting_menu = 'shipping_group';

		$this->template->assign(array('selected_setting_menu'=>$setting_menu));

	}

	public function index()
	{
		redirect('admin/setting/config');
	}

	protected function _setting_menu_template_path(){
		return $this->skin."/setting/_setting_menu.html";
	}

	/* 판매환경 설정 */
	public function config()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();//

		$this->load->model('configsalemodel');

		$sc['type'] = 'mobile';
		$systemmobiles = $this->configsalemodel->lists($sc);
		$this->template->assign('systemmobiles',$systemmobiles['result']);

		$sc['type'] = 'fblike';
		$systemfblike = $this->configsalemodel->lists($sc);
		$this->template->assign('systemfblike',$systemfblike['result']);

		$goodssql = "select goods_seq,goods_name  from fm_goods order by goods_seq desc limit 0,1";
		$goodsquery = $this->db->query($goodssql);
		$goodsdata = $goodsquery->row_array();
		$this->template->assign('goods_seq',$goodsdata['goods_seq']);
		$this->template->assign('goods_name',$goodsdata['goods_name']);

		$arrSystem	= ($this->config_system)?$this->config_system:config_load('system');
		$page_id_f_ar				= explode(",",$this->arrSns['page_id_f']);
		$page_name_ar			= explode(",",$this->arrSns['page_name_f']);
		$page_url_ar				= explode(",",$this->arrSns['page_url_f']);
		$page_app_link_f_ar	= explode(",",$this->arrSns['page_app_link_f']);
		foreach($page_id_f_ar as $pagen=>$v) {
			if(intval(str_replace("[","",str_replace("]","",$page_id_f_ar[$pagen])))){
				$pageloop['page_id_f']			= str_replace("[","",str_replace("]","",$page_id_f_ar[$pagen]));
				$pageloop['page_name_f']		= str_replace("[","",str_replace("]","",$page_name_ar[$pagen]));
				$pageloop['page_url_f']			= str_replace("[","",str_replace("]","",$page_url_ar[$pagen]));
				$pageloop['page_app_link_f'] = str_replace("[","",str_replace("]","",$page_app_link_f_ar[$pagen]));
				$this->arrSns['pageloop'][] = $pageloop;
			}
		}
		//pagelist session 삭제
		$this->session->unset_userdata('access_token');
		$this->session->unset_userdata('fbuser');

		$orders = config_load('order');
		$this->template->assign('fblike_ordertype',$orders['fblike_ordertype']);

		$this->template->assign($this->arrSns);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($arrSystem);
		$this->template->print_("tpl");
	}

	/* 일반 설정 */
	public function basic()
	{

		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();
		$arrBasic = config_load('basic');

		if(isset($arrBasic['businessLicense']))$arrBasic['businessLicense'] = explode('-',$arrBasic['businessLicense']);
		if(isset($arrBasic['companyPhone']))$arrBasic['companyPhone'] = explode('-',$arrBasic['companyPhone']);
		if(isset($arrBasic['companyFax']))$arrBasic['companyFax'] = explode('-',$arrBasic['companyFax']);
		if(isset($arrBasic['companyZipcode']))$arrBasic['companyZipcode'] = explode('-',$arrBasic['companyZipcode']);
		if(isset($arrBasic['companyEmail']))$arrBasic['companyEmail'] = explode('@',$arrBasic['companyEmail']);
		if(isset($arrBasic['shopBranch'])){
			if(is_array($arrBasic['shopBranch']))foreach($arrBasic['shopBranch'] as $codecd2){
				$codecd1 = substr($codecd2,0,3);
				list($groupcd1) = code_load('shopBranch',$codecd1);
				list($groupcd2) = code_load('shopBranch'.$codecd1,$codecd2);
				$ret[] = array(
					'groupcd1'=>$groupcd1['value'],
					'groupcd2'=>$groupcd2['value'],
					'codecd'=>$codecd2
				);
			}
			$arrBasic['shopBranch'] = $ret;
		}

		$reserve = config_load('reserve');
		$this->template->assign('reserve',$reserve);

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($arrBasic);
		$this->template->print_("tpl");
	}

	/* SNS마케팅 설정 */
	public function snsconf()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$this->template->assign('snsgoods', 'goods');
		$this->template->assign('snsevent', 'event');
		$this->template->assign('snsboard', 'board');

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign(array('sns'=>$this->arrSns));
		$this->template->assign($arrBasic);
		$this->template->print_("tpl");
	}

	/* 운영 설정 */
	public function operating(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('operating');
		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		$realname = config_load('realname');
		$realname['adult_chk'] = "N";
		if( ($realname['realnameId'] && $realname['realnamePwd']) || ($realname['ipinSikey'] && $realname['ipinKeyString']) ){
			$realname['adult_chk'] = "Y";
		}
		$this->template->assign('realname',$realname);

		$arrBasic = config_load('basic');
		$this->template->assign($arrBasic);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}
	/* PG 설정 */
	public function pg(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}
	/* KCP 설정 */
	public function kcp(){
		$filePath	= $this->template_path();
		$tmp = config_load('kcp');
		$tmp['arrKcpCardCompany'] = code_load('kcpCardCompanyCode');
		foreach($tmp['arrKcpCardCompany'] as $k=>$v){
			$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
		}
		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}
	/* LG유플러스 설정 */
	public function lg(){
		$filePath	= $this->template_path();
		$tmp = config_load('lg');
		$tmp['arrLgCardCompany'] = code_load('lgCardCompanyCode');
		foreach($tmp['arrLgCardCompany'] as $k=>$v){
			$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
		}
		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}
	/* 이니시스 설정 */
	public function inicis(){
		$filePath	= $this->template_path();
		$tmp = config_load('inicis');
		$tmp['arrInicisCardCompany'] = code_load('inicisCardCompanyCode');

		$key_dir = './pg/inicis/key/'.$tmp['mallCode'];
		$arr = array(
			'keypass'=>'keypass.enc',
			'mcert'=>'mcert.pem',
			'mpriv'=>'mpriv.pem'
		);
		foreach($arr as $keyword => $keyfile){
			if(!file_exists($key_dir.'/'.$keyfile)){
				unset($arr[$keyword]);
			}
		}
		$this->template->assign($arr);

		$key_dir = './pg/inicis/key/'.$tmp['escrowMallCode'];
		$arr = array(
			'escrowKeypass'=>'keypass.enc',
			'escrowMcert'=>'mcert.pem',
			'escrowMpriv'=>'mpriv.pem'
		);
		foreach($arr as $keyword => $keyfile){
			if(!file_exists($key_dir.'/'.$keyfile)){
				unset($arr[$keyword]);
			}
		}
		$this->template->assign($arr);


		foreach($tmp['arrInicisCardCompany'] as $k=>$v){
			$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
		}

		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}

	/* 이니시스 에스크로 인증마크 안내 셈플
	public function inics_escrow_info(){
		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}
	 */

	/* 올엣페이 설정 */
	public function allat(){
		$filePath	= $this->template_path();
		$tmp = config_load('allat');
		/*
		$tmp['arrAllatCardCompany'] = code_load('allatCardCompanyCode');
		foreach($tmp['arrAllatCardCompany'] as $k=>$v){
			$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
		}
		*/

		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}
	/* 무통장설정 */
	public function bank(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		###
		$this->load->model('usedmodel');
		$banks = $this->usedmodel->autodeposit_check();
		$this->template->assign(array('bankChk'=>$banks['chk'],'bankCount'=>$banks['count']));

		/* 계좌설정 정보 */
		$loop = config_load('bank');
		if(!$loop)$loop[0]['account'] = '';

		###
		$cid = $this->usedmodel->getEncodeBankda();
		$this->template->assign(array('cid' => $cid));

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign('loop',$loop);
		$this->template->print_("tpl");
	}

	public function bank_payment(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}
		$sms_call = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=BANK&req_url=/myhg/mylist/spec/firstmall/bank/index.php";
		$sms_call = makeEncriptParam($sms_call);

		$this->template->assign('param',$sms_call);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function bank_history(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}
		$sms_call = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=BANK&req_url=/myhg/mylist/spec/firstmall/bank/index.php";
		$sms_call = makeEncriptParam($sms_call);

		$this->template->assign('param',$sms_call);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 회원설정 */
	public function member(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		if(isset($_GET['grade']) && $_GET['grade']=='modify'){
			$this->template->assign('grade',$_GET['grade']);
			$this->template->assign('seq',$_GET['seq']);
		}

		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}
	### 회원설정 - 실명확인
	public function realname(){
		$filePath	= $this->template_path();
		$realname = config_load('realname');

		$status = $realname['useRealname']=='N' ? "실명확인 미사용" : "실명확인 사용";
		$status .= $realname['useIpin']=='N' ? ", 아이핀 미사용" : ", 아이핀 사용";

		$arrBasic = config_load('basic');
		$this->template->assign('operating',$arrBasic['operating']);

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign('status',$status);
		if($realname) $this->template->assign($realname);
		$this->template->print_("tpl");
	}
	### 회원설정 - 이용약관
	public function agreement(){
		$filePath	= $this->template_path();
		$member = config_load('member');

		$this->template->define(array('tpl'=>$filePath));
		if($member) $this->template->assign($member);
		$this->template->print_("tpl");
	}
	### 회원설정 - 개인정보처리
	public function privacy(){
		$filePath	= $this->template_path();
		$member = config_load('member');

		$url = get_connet_protocol().$_SERVER['HTTP_HOST'];

		$this->template->assign(array('member_url'=>$url."/mypage/myinfo",'privacy_url'=>$url."/service/"));
		$this->template->define(array('tpl'=>$filePath));
		if($member) $this->template->assign($member);
		$this->template->print_("tpl");
	}
	### 회원설정 - 가입
	public function joinform(){
		$filePath	= $this->template_path();

		$this->typeNames = array(
		'text'		=> '텍스트박스',
		'select'   	=> '셀렉트박스',
		'radio'		=> '여러개 중 택1',
		'checkbox'	=> '체크박스',
		'textarea'	=> '에디트박스'
		);

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('member');
		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		$surveyFilePath = dirname($filePath)."/_survey.htm";
		$this->template->define(array('surveyForm'=>$surveyFilePath));

		$tmp = config_load('joinform');

		//추가 조건 있는지 확인
		$qry = "select count(*) as cnt, max(joinform_seq) as maxid from fm_joinform";
		$query = $this->db->query($qry);
		$sub_row = $query -> row_array();
		$this->template->assign('sub_cnt',$sub_row);

		//일반가입 정보
		$qry = "select * from fm_joinform where join_type = 'user' order by sort_seq";
		$query = $this->db->query($qry);
		$user_arr = $query -> result_array();
		foreach ($user_arr as $datarow){
			$datarow['label_ctype'] = $this->typeNames[$datarow['label_type']];
			$user_sub[] = $datarow;
		}
		$this->template->assign('user_sub', $user_sub);

		//사업자가입 정보
		$qry = "select * from fm_joinform where join_type = 'order' order by sort_seq";
		$query = $this->db->query($qry);
		$order_arr = $query -> result_array();
		foreach ($order_arr as $datarow){
			$datarow['label_ctype'] = $this->typeNames[$datarow['label_type']];
			$order_sub[] = $datarow;
		}
		$this->template->assign('order_sub',$order_sub);

		$this->load->model('snsmember');
		$fquery = $this->db->query("select count(A.member_seq) as total from fm_membersns A LEFT JOIN fm_member B ON A.member_seq = B.member_seq WHERE B.rute = 'facebook' and B.status = 'done' ");
		$snsftotal = $fquery->row_array();

		$tquery = $this->db->query("select count(A.member_seq) as total from fm_membersns A LEFT JOIN fm_member B ON A.member_seq = B.member_seq WHERE B.rute = 'twitter' and B.status = 'done' ");
		$snsttotal = $tquery->row_array();

		$mquery = $this->db->query("select count(A.member_seq) as total from fm_membersns A LEFT JOIN fm_member B ON A.member_seq = B.member_seq WHERE B.rute = 'me2day'  and B.status = 'done' ");
		$snsmtotal = $mquery->row_array();
		$cquery = $this->db->query("select count(A.member_seq) as total from fm_membersns A LEFT JOIN fm_member B ON A.member_seq = B.member_seq WHERE B.rute = 'cyworld'  and B.status = 'done' ");
		$snsctotal = $cquery->row_array();

		$yquery = $this->db->query("select count(A.member_seq) as total from fm_membersns A LEFT JOIN fm_member B ON A.member_seq = B.member_seq WHERE B.rute = 'yozm'  and B.status = 'done' ");
		$snsytotal = $yquery->row_array();

		$this->arrSns['total_f']  = ($snsftotal['total']);
		$this->arrSns['total_t']  = ($snsttotal['total']);
		$this->arrSns['total_c']  = ($snsctotal['total']);
		$this->arrSns['total_m']  = ($snsmtotal['total']);
		$this->arrSns['total_y']  = ($snsytotal['total']);
		$this->template->assign(array('sns'=>$this->arrSns));
		if( (str_replace("-","",$this->config_system['service']['setting_date']) < '20121009') ){
			$this->template->assign('service_setting_date_ck', true);
		}
		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}
	### 회원설정 - 승인/혜택
	public function approval(){
		$filePath	= $this->template_path();
		$tmp = config_load('member');

		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}
	### 회원설정 - 등급
	public function grade(){
		$filePath	= $this->template_path();

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('grade');
		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		###
		$this->load->model('membermodel');
		$list = $this->membermodel->find_group_cnt_list();
		$totalcount	 = get_rows('fm_member',array('status !='=>'withdrawal'));

		###
		$grade_clone = config_load('grade_clone');
		$grade_clone['chg_text'] = "";
		$grade_clone['chk_text'] = "";
		$grade_clone['keep_text'] = "";
		$month = $grade_clone['start_month']=='13' ? '1' : $grade_clone['start_month'];
		$for_type = 12/$grade_clone['chg_term'];
		for($i=0;$i<$for_type;$i++){
			if($i!=0){
				$month = $this->calcu_month('calcu', $month, $grade_clone['chg_term']);
			}
			###
			$grade_clone['chg_text'] .= $month."월 ".$grade_clone['chg_day']."일<br>";

			###
			$chk_month = $this->calcu_month('chk', $month, $grade_clone['chk_term'], 1);
			$chk_month2 = $this->calcu_month('chk', ($month+$grade_clone['chk_term']), $grade_clone['chk_term']);
			$grade_clone['chk_text'] .= $chk_month."월 01일 ~ ".$chk_month2."월 31일<br>";

			###
			$keep_month = $this->calcu_month('add', $month, $grade_clone['keep_term'], 1);
			$keep_day	= $grade_clone['chg_day']==1 ? '31' : '14';
			$grade_clone['keep_text'] .= $month."월 ".$grade_clone['chg_day']."일 ~ ".$keep_month."월 ".$keep_day."일<br>";
		}

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign('clone',$grade_clone);
		$this->template->assign('tot',$totalcount);
		if($list) $this->template->assign(array('loop'=>$list,'gcount'=>count($list)));
		$this->template->print_("tpl");
	}
	public function calcu_month($case, $month, $alpha, $prv=0){
		switch($case){
			case "add":
				$month = $month + $alpha;
				$month = $month - 1;
				if($month>12) $month = $month - 12;
				break;
			case "chk":
				$month = $month - $alpha - 1;
				$month = $month + $prv;
				if($month<1) $month = 12 + ($month);
				break;
			case "calcu":
				$month = $month + $alpha;
				if($month>12) $month = $month - 12;
				break;
		}
		return $month;
	}

	public function grade_write(){
		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}
	public function grade_modify(){
		$filePath	= $this->template_path();

		### SERVICE CHECK
		if(!$_GET['group_seq']){
			$this->load->model('usedmodel');
			$result = $this->usedmodel->used_service_check('grade');
			if(!$result['type']){
				###
				$this->load->model('membermodel');
				$list = $this->membermodel->find_group_list();
				if(count($list)>3){
					$callback = "parent.formMove('grade',4);";
					openDialogAlert("더 이상 생성하실 수 없습니다.",400,140,'parent',$callback);
					exit;
				}
			}
		}

		$icons = find_icons();
		//print_r($icons);

		###
		$this->db->where('group_seq', $_GET['group_seq']);
		$query = $this->db->get('fm_member_group');
		foreach ($query->result_array() as $row){
			if(preg_match('/a:/',$row['order_sum_use'])) $row['order_sum_arr'] = unserialize($row['order_sum_use']);
			$returnArr[] = $row;
		}

		###
		$sql = "SELECT
						distinct A.*, B.*
					FROM
						fm_member_group_issuegoods A
						LEFT JOIN
						(SELECT
							g.goods_seq, g.goods_name, o.price
						FROM
							fm_goods g LEFT JOIN fm_goods_option o ON g.goods_seq = o.goods_seq AND o.default_option = 'y') B ON A.goods_seq = B.goods_seq
					WHERE
						A.group_seq = '{$returnArr[0]['group_seq']}'";
		$query = $this->db->query($sql);
		foreach ($query->result_array() as $row){
			$limit_goods[] = $row;
		}
		if($limit_goods) $this->template->assign('issuegoods',$limit_goods);

		###
		$this->load->model('categorymodel');
		$this->db->where('group_seq', $returnArr[0]['group_seq']);
		$query = $this->db->get('fm_member_group_issuecategory');
		foreach ($query->result_array() as $row){
			$row['title'] =  $this->categorymodel->get_category_name($row['category_code']);
			$limit_cate[] = $row;
		}
		if($limit_cate) $this->template->assign('issuecategorys',$limit_cate);
		//print_r($limit_cate);

		$this->template->define(array('tpl'=>$filePath));
		if($icons) $this->template->assign('icons',$icons);
		if($returnArr) $this->template->assign('data',$returnArr[0]);
		$this->template->print_("tpl");
	}
	### 회원설정 - 로그아웃/탈퇴/재가입
	public function withdraw(){
		$filePath	= $this->template_path();
		$tmp = config_load('member');

		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}

	/* 주문설정 */
	public function order(){
		$this->admin_menu();
		$this->tempate_modules();
		$orders = config_load('order');

		###
		$domain = str_replace("www.","",$_SERVER["SERVER_NAME"]);
		$pattern = array(".com",".net",".org",".biz",".info",".name",".kr",".co.kr",".or.kr",".pe.kr",".asia",".me",".cc",".cn",".tv",".in",".tw",".mobi");
		preg_match('/[a-z\d\-]+('.implode("|",$pattern).')/i',$domain, $match);
		$resDomain = $match[0];
		$this->template->assign('domain',$resDomain);

		if($this->config_system['hiworks_request']=="Y"){
			if(isset($this->config_system['webmail_admin_id']) && isset($this->config_system['webmail_domain'])){
				$this->template->assign('webmail_admin_id', $this->config_system['webmail_admin_id']);
				$this->template->assign('webmail_domain', $this->config_system['webmail_domain']);
			}else{
				$this->load->helper("environment");
				callSetEnvironment(false);
			}
		}
		$this->template->assign('hiworks_request', $this->config_system['hiworks_request']);
		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($orders);
		$this->template->print_("tpl");
	}

	/* 주문설정 */
	public function sale(){
		$this->admin_menu();
		$this->tempate_modules();
		$orders = config_load('order');

		###
		$domain = str_replace("www.","",$_SERVER["SERVER_NAME"]);
		$pattern = array(".com",".net",".org",".biz",".info",".name",".kr",".co.kr",".or.kr",".pe.kr",".asia",".me",".cc",".cn",".tv",".in",".tw",".mobi");
		preg_match('/[a-z\d\-]+('.implode("|",$pattern).')/i',$domain, $match);
		$resDomain = $match[0];
		$this->template->assign('domain',$resDomain);

		if($this->config_system['hiworks_request']=="Y"){
			if(isset($this->config_system['webmail_admin_id']) && isset($this->config_system['webmail_domain'])){
				$this->template->assign('webmail_admin_id', $this->config_system['webmail_admin_id']);
				$this->template->assign('webmail_domain', $this->config_system['webmail_domain']);
				$this->template->assign('webmail_key', $this->config_system['webmail_key']);
			}else{
				$this->load->helper("environment");
				callSetEnvironment(false);
			}
		}
		$this->template->assign('hiworks_request', $this->config_system['hiworks_request']);
		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($orders);
		$this->template->print_("tpl");
	}


	/* 주문설정 */
	public function reserve(){
		$this->admin_menu();
		$this->tempate_modules();
		$reserves = config_load('reserve');
		$filePath	= $this->template_path();

		if(!$reserves['point_use']) $reserves['point_use'] = "N";
		if(!$reserves['cash_use']) $reserves['cash_use'] = "N";
		if(!$reserves['default_point_type']) $reserves['default_point_type'] = "per";
		//if(!$reserves['save_step']) $reserves['save_step'] = "75";
		if(!$reserves['reserve_year']) $reserves['reserve_year'] = date("Y");
		if(!$reserves['point_year']) $reserves['point_year'] = date("Y");
		if(!$reserves['reserve_direct']) $reserves['reserve_direct'] = "24";
		if(!$reserves['point_direct']) $reserves['point_direct'] = "24";

		$orders = config_load('order');

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($reserves);
		$this->template->assign($orders);
		$this->template->print_("tpl");
	}


	/* 배송설정 */
	public function shipping(){

		$provider_seq = isset($_GET['provider_seq'])?$_GET['provider_seq']:1;

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('shipping');
		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		## 택배업무자동화서비스 세팅값 :: 2015-07-10
		$this->load->model('invoiceapimodel');
		$config_invoice = $this->invoiceapimodel->get_invoice_setting($provider_seq);
		$this->template->assign("config_invoice",$config_invoice);

		## 우체국택배업무자동화서비스 세팅값 :: 2016-03-29 lwh
		$this->load->model('epostmodel');
		$config_epost = $this->epostmodel->get_epost_setting($provider_seq);

		if(!$config_epost){ // 정보가 없으면 기본적으로 사업자 정보를 넣어줌.
			if($provider_seq == 1){
				if($this->config_basic['companyAddress_type'] == 'street'){
					$address = $this->config_basic['companyAddress_street'] . ' ' . $this->config_basic['companyAddressDetail'];
				}else{
					$address = $this->config_basic['companyAddress'] . ' ' . $this->config_basic['companyAddressDetail'];
				}
				$config_epost['biz_name']		= $this->config_basic['companyName'];
				$config_epost['biz_ceo']		= $this->config_basic['ceo'];
				$config_epost['biz_no']			= $this->config_basic['businessLicense'];
				$config_epost['biz_zipcode']	= $this->config_basic['companyZipcode'];
				$config_epost['biz_address']	= $address;
				$config_epost['biz_phone']		= $this->config_basic['companyPhone'];
				$config_epost['biz_email']		= $this->config_basic['companyEmail'];
			}else{
				$this->load->model('providermodel');
				$provider_info = $this->providermodel->get_provider($provider_seq);

				if($provider_info['info_address1_type'] == 'street'){
					$address = $provider_info['info_address1_street'] . ' ' . $provider_info['info_address2'];
				}else{
					$address = $provider_info['info_address1'] . ' ' . $$provider_info['info_address2'];
				}
				$config_epost['biz_name']		= $provider_info['info_name'];
				$config_epost['biz_ceo']		= $provider_info['info_ceo'];
				$config_epost['biz_no']			= $provider_info['info_num'];
				$config_epost['biz_zipcode']	= $provider_info['info_zipcode'];
				$config_epost['biz_address']	= $address;
				$config_epost['biz_phone']		= $provider_info['info_phone'];
				$config_epost['biz_email']		= $provider_info['info_email'];
			}
		}
		$this->template->assign("config_epost",$config_epost);

		## 굿스플로 서비스 세팅값 및 결과체크 :: 2015-06-12 lwh
		$this->load->model('goodsflowmodel');
		$this->config_goodsflow = config_load('goodsflow');
		$this->config_goodsflow['setting'] = $this->goodsflowmodel->get_goodsflow_setting($provider_seq);
		$service_cnt	= $this->goodsflowmodel->get_service_info('view');
		// 연동 신청중일때 신청결과 재 확인
		if($this->config_goodsflow['setting']['goodsflow_step']=='2'){
			$apiParam['requestKey'] = $this->config_goodsflow['setting']['requestKey'];
			$apiRespon	= $this->goodsflowmodel->apiSender('getServiceResult',$apiParam);
			if($apiRespon['result']){ // 결과가 변동되어 재 호출
				$step_param['goodsflow_step'] = $apiRespon['goodsflow_step'];
				$step_param['goodsflow_msg'] = $apiRespon['goodsflow_msg'];
				$this->goodsflowmodel->set_goodsflow_step($provider_seq,$step_param);
				$this->config_goodsflow['setting'] = $this->goodsflowmodel->get_goodsflow_setting($provider_seq);
			}
		}else{
			$goodsflow_deli = $this->goodsflowmodel->delivery_set();
			config_save('goodsflow',array('terms'=>$goodsflow_deli));
			$this->config_goodsflow['terms'] = config_load('goodsflow','terms');
		}

		//5자리 우편번호 양식(6자리 우편번호도 "-"  없음)
		$this->config_goodsflow['setting']['goodsflowNewZipcode']	= implode('', (array)$this->config_goodsflow['setting']['goodsflowZipcode']);
		$this->template->assign("config_goodsflow",$this->config_goodsflow);
		$this->template->assign("service_cnt",$service_cnt);

		###
		$this->load->model('providershipping');
		if($provider_seq) $data_providershipping = $this->providershipping->get_provider_shipping($provider_seq);
		$this->template->assign("data_providershipping",$data_providershipping);

		$filePath	= $this->template_path();
		if( !isset($_GET['provider_seq']) ){
			$this->admin_menu();
			$this->tempate_modules();

		}else{
			$filePath = str_replace('setting/shipping.html','setting/provider_shipping.html',$filePath);
		}

		$addDeliveryType = config_load('adddelivery', 'addDeliveryType');
		$this->template->assign("addDeliveryType",$addDeliveryType['addDeliveryType']);

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign("provider_seq",$provider_seq);
		$this->template->assign("loop",$loop);
		// $this->template->assign("internationalShipping",$result);
		$this->template->print_("tpl");
	}
	/* 국내 배송 수정 */
	public function shipping_modify(){


		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('goodsmodel');
		$this->load->helper('shipping');

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));

		$this->load->model("providerModel");
		$this->load->model("providershipping");

		$addDeliveryType = config_load('adddelivery', 'addDeliveryType');
		$this->template->assign("addDeliveryType",$addDeliveryType['addDeliveryType']);

		$provider_seq = $_GET['provider_seq']?$_GET['provider_seq']:1;

		$data = $this->providershipping->get_provider_shipping($provider_seq);

		if( isset($_GET['code']) ){
			if($_GET['code']=='quick'){
				$data['use_yn'] = $data['quick_use_yn'];
				$data['summary'] = $data['quick_summary'];
			}

			if($_GET['code']=='direct'){
				$data['use_yn'] = $data['direct_use_yn'];
				$data['summary'] = $data['direct_summary'];
			}

		 	if( isset($data['deliveryCompany']) ){
				foreach( $data['deliveryCompany'] as $key => $deliveryCompany ){
					$data['deliveryCompany'][$key] = $deliveryCompany;
				}
			}
		}

		if(isset($data)){
			$this->template->assign($data);
		}
		$this->template->print_("tpl");
	}

	/* 해외 배송 추가/수정 */
	public function international_shipping(){

		$this->load->model('categorymodel');
		$code = $_GET['code'];
		$filePath	= $this->template_path();
		if($code != 'regist'){
			$data = config_load('internationalShipping'.$code);
			$rownum = count($data['region']);
			$rp = 0;
			foreach($data['deliveryCost'] as $k => $deliveryCost){
				$num = $k + 1;
				$data['arrDeliveryCost'][$rp][] = $deliveryCost;
				if($num%$rownum == 0) $rp += 1;
			}
			if($data['exceptCategory']){
				foreach($data['exceptCategory'] as $k => $exceptCategory){
					$data['exceptCategoryName'][] = $this->categorymodel->get_category_name($exceptCategory);
				}
			}
		}
		if(isset($data)){
			$this->template->assign($data);
		}

		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	/* 보안 설정 */
	public function protect(){
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('ssl');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_protect_view');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$protectIp = !empty($this->config_system['protectIp']) ? $this->config_system['protectIp'] : "";
		$protectIp = $protectIp ? explode("\n",$protectIp) : array();

		$this->template->assign(array(
			'protectIp'=>$protectIp,
			'ssl'=>$this->ssl
		));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}


	/* 관리자 */
	public function manager(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$auth = $this->authmodel->manager_limit_act('manager_act');

		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('manager');

		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		$this->load->model('membermodel');

		### SEARCH
		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'provider_seq';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):'10';

		###
		$data = $this->membermodel->seller_manager_list($sc);

		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_manager');
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['type']	= $datarow['business_seq'] ? '기업' : '개인';
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			$dataloop[] = $datarow;
		}
		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );

		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('group_arr',$group_arr);
		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);

		$auth = $this->authmodel->manager_limit_act('setting_manager_act');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		$this->template->assign('use_manager_cnt',$data['count']);

		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	public function manager_reg(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$this->load->model('providercode');

		$num_menu_count = 0;
		$rowspan_menu_count = 0;
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if( $this->scm_cfg['use'] == 'Y' ){ // 올인원일 경우
			$num_menu_count++;
		}
		if	(serviceLimit('H_AD')){ // 입점몰일 경우
			$num_menu_count++;
			$is_provider_solution = true;
		}
		if( $num_menu_count == 1 ){
			$colspan_menu_count = 3;
		}
		$this->template->assign('num_menu_count',$num_menu_count);
		$this->template->assign('colspan_menu_count',$colspan_menu_count);
		$this->template->assign('is_provider_solution',$is_provider_solution);

		$issueCount = array();
		if( $this->providerInfo['provider_seq'] ){
			$wheres['shopSno']			= $this->config_system['shopSno'];
			$wheres['provider_seq']	= $this->providerInfo['provider_seq'];
			$wheres['codecd like']		= '%_priod_%';;
			$orderbys['idx'] 					= 'asc';
			$query_auth	= $this->providercode->select('*',$wheres,$orderbys);
			foreach($query_auth->result_array() as $data){
				$codecd = str_replace('noti_count_priod_','',$data['codecd']);
				$noti_acount_priod[$codecd]	= $data['value'];
			}
		}
		if(!$noti_acount_priod['order']) $noti_acount_priod['order'] = "6개월";
		if(!$noti_acount_priod['board']) $noti_acount_priod['board'] = "6개월";
		if(!$noti_acount_priod['account']) $noti_acount_priod['account'] = "6개월";
		if(!$noti_acount_priod['warehousing']) $noti_acount_priod['warehousing'] = "6개월";
		$this->template->assign('noti_acount_priod',$noti_acount_priod);

		$auth = $this->authmodel->manager_limit_act('manager_act');

		if(!$auth && !$_GET['provider_seq']){
			pageBack("권한이 없습니다.");
			exit;
		}

		### 부관리자는 본인것만 수정 가능함
		if	($this->providerInfo['manager_yn'] != 'Y' && ($this->providerInfo['sub_provider_seq'] != $_GET['provider_seq'])){
			pageBack("잘못된 접근입니다.");
			exit;
		}

		if(isset($_GET['provider_seq'])){
			$this->db->where('provider_seq', $_GET['provider_seq']);
			$query = $this->db->get('fm_provider');
			$data = $query->result_array();

			$auth_arr = explode("||",$data[0]['manager_auth']);

			if ($data[0]['limit_ip']) {
				$limit_row = explode("|", $data[0]['limit_ip']);
				$count = count($limit_row)-1;
				for ($i=0; $i<$count; $i++) {
					$arr = explode(".", $limit_row[$i]);
					$limit_ip[] = $arr;
					if (count($arr) == 3) {
						$limit_ip_msg[] = $limit_row[$i].".1 ~ ".$limit_row[$i].".255";
					} else {
						$limit_ip_msg[] = $limit_row[$i];
					}
				}
				$data[0]['limit_ip'] = $limit_ip;
				$data[0]['limit_ip_msg'] = $limit_ip_msg;
			}

			foreach($auth_arr as $k){
				$tmp_arr = explode("=",$k);
				$auth_temp[$tmp_arr[0]] = $tmp_arr[1];
			}

			$this->template->assign('auth',$auth_temp);
			$this->template->assign($data[0]);
		}

		$auth_arr = config_load('master','sms_auth'); // 보안키
		$sms_api_key = $auth_arr['sms_auth'];
		$send_phone = getSmsSendInfo(); // 발신번호인증
		// 보안키 및 발신번호 미인증시 처리
		if($sms_api_key && $send_phone){
			$sms_st = 'Y';
		}else{
			if(!$send_phone)	$sms_st = '2';
			if(!$sms_api_key)	$sms_st = '1';
		}

		$this->template->assign('sms_st',$sms_st);

		###
		if	($this->providerInfo['manager_yn'] == 'Y'){
			$auth_limit = $this->authmodel->manager_limit_act('manager_act');
			$this->template->assign('auth_limit',$auth_limit);
		}

		$this->template->assign('ip',$_SERVER['REMOTE_ADDR']);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	/* 관리자 계정 추가 신청 */
	public function manager_payment(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}

		$req_type = 'MANAGER';
		$param = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=".$req_type."&req_url=/myshop";
		$param = makeEncriptParam($param);

		$this->template->assign('param',$param);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function hiworks_request(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}
		$sms_call = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=HIWORKS&req_url=/myhg/mylist/spec/firstmall/hiworks/index.php";
		$sms_call = makeEncriptParam($sms_call);

		$this->template->assign('param',$sms_call);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 상품 설정 */
	public function goods(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$cfg_goods = config_load("goods");

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('stock_history');
		if(!$result['type']){
			$this->template->assign('stock_history_limit','Y');

			if($cfg_goods['stock_history_use']){
				$cfg_goods['stock_history_use'] = 0;
				config_save('goods',array('stock_history_use'=>0));
			}
		}

		/*
		$qry = "select * from fm_brand_banner";
		$query = $this->db->query($qry);
		$row = $query->result_array();
		$this->template->assign('images',$row);
		*/


		$surveyFilePath = dirname($this->template_path())."/_survey.htm";
		$this->template->define(array('surveyForm'=>$surveyFilePath));

		//상품추가양식 정보
		$this->load->helper("goods");
		$gdtypearray = array("goodsaddinfo","goodsoption","goodssuboption");
		$goodscodesettingview='';
		foreach($gdtypearray as $gdtype){
			unset($goodscode);
			$qry = "select * from fm_goods_code_form  where label_type ='".$gdtype."'  order by sort_seq";
			$query = $this->db->query($qry);
			$user_arr = $query -> result_array();
			foreach ($user_arr as $datarow){
				$datarow['label_view'] = get_labelitem_type($datarow,'','setting');
				if($datarow['codesetting']==1){
					$goodscodesettingview .= $datarow['label_title'].' + ';
					$datarow['label_codesetting'] = ' checked ';
				}else{
					$datarow['label_codesetting'] = '';
				}
				$goodscode[] = $datarow;
			}
			$this->template->assign($gdtype.'loop', $goodscode);
		}
		$this->template->assign('goodscodesettingview',substr($goodscodesettingview,0,strlen($goodscodesettingview)-3));
		$qry = "select codeform_seq as maxseq from fm_goods_code_form order by codeform_seq desc limit 1";
		$query = $this->db->query($qry);
		$maxseq = $query -> result_array();
		$this->template->assign('maxseq',$maxseq[0]['maxseq']);

		### PAGE & DATA
		$gdquery = "select count(*) cnt from fm_goods where goods_type = 'goods' ";
		$gdquery = $this->db->query($gdquery);
		$gddata = $gdquery->row_array();
		$this->template->assign('totalcount',$gddata['cnt']);
		$this->template->assign('totalpage',@ceil($gddata['cnt']/500));



		$this->template->assign('cfg_goods',$cfg_goods);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	/* 동영상 설정 */
	public function video(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$cfg_goods = config_load("goods");
		$this->template->assign('cfg_goods',$cfg_goods);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	/* 입점사 */
	public function provider(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$this->load->model('membermodel');
		$this->load->model('accountallmodel');
		$this->load->model('providermodel');

		### SEARCH
		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'A.regdate';
		if($sc['orderby']=='A.regdate'){
			$sc['sort']= 'desc';
		}else{
			$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'asc';
		}
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):'10';

		###
		$data = $this->providermodel->provider_list($sc);
		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_provider');
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			$datarow['provider_status'] = $datarow['provider_status']=="Y" ? "<span style='color:blue;'>정상</sapn>" : "<span style='color:red;'>종료</span>";
			$datarow['provider_gb'] = $datarow['provider_gb']=="company" ? "입점(본사)" : "입점(업체)";
			$datarow['deli_group'] = $datarow['deli_group']=="company" ? "본사 배송" : "입점사 배송";
			$dataloop[] = $datarow;
		}
		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );

		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('group_arr',$group_arr);
		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);

		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	public function provider_reg(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();
		$this->load->model('providermodel');
		$this->load->model('accountallmodel');
		$this->load->model('providercode');
		
		// 21.05.10 lsh 로그인한 관리자가 대표관리자가 아니고 자신의관리자정보페이지가 아닐 경우
		if ($this->providerInfo['manager_yn'] != "Y") {
			$managerSeq = $this->input->get("no");
			if (!isset($managerSeq) || $this->providerInfo['sub_provider_seq'] != $managerSeq) {
				pageBack("권한이 없습니다."); 
				exit;
			}
		}

		$num_menu_count = 0;
		$rowspan_menu_count = 0;
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if( $this->scm_cfg['use'] == 'Y' ){ // 올인원일 경우
			$num_menu_count++;
		}
		if	(serviceLimit('H_AD')){ // 입점몰일 경우
			$num_menu_count++;
			$is_provider_solution = true;
		}
		if( $num_menu_count == 1 ){
			$colspan_menu_count = 3;
		}
		$this->template->assign('num_menu_count',$num_menu_count);
		$this->template->assign('colspan_menu_count',$colspan_menu_count);
		$this->template->assign('is_provider_solution',$is_provider_solution);

		$issueCount = array();
		if( $this->providerInfo['provider_seq'] ){
			$wheres['shopSno']			= $this->config_system['shopSno'];
			$wheres['provider_seq']	= $this->providerInfo['provider_seq'];
			$wheres['codecd like']		= '%_priod_%';;
			$orderbys['idx'] 					= 'asc';
			$query_auth	= $this->providercode->select('*',$wheres,$orderbys);
			foreach($query_auth->result_array() as $data){
				$codecd = str_replace('noti_count_priod_','',$data['codecd']);
				$noti_acount_priod[$codecd]	= $data['value'];
			}
		}
		if(!$noti_acount_priod['order']) $noti_acount_priod['order'] = "6개월";
		if(!$noti_acount_priod['board']) $noti_acount_priod['board'] = "6개월";
		if(!$noti_acount_priod['account']) $noti_acount_priod['account'] = "6개월";
		if(!$noti_acount_priod['warehousing']) $noti_acount_priod['warehousing'] = "6개월";

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('minishop');
		if(!$result['type']){
			$this->template->assign('minishop_service_limit','Y');
		}

		### BRAND
		$sql = "select * from fm_brand where length(category_code)=4 and position = 1 and parent_id = 2 order by `left` asc";
		$query = $this->db->query($sql);
		foreach ($query->result_array() as $row){
			$brand[] = $row;
		}
		$this->template->assign('brand',$brand);
		$this->template->assign('brand_cnt',count($brand));

		### [반응형스킨] 운영방식 추가 :: 2018-11-01 pjw
		$operation_type = !empty($this->config_system['operation_type']) ? $this->config_system['operation_type'] : 'heavy';
		$this->template->assign('operation_type', $operation_type);

		### MODIFY
		if(isset($_GET['no'])){
			$sql = "select
						A.* ,B.*
						,C.pgroup_name
						,C.pgroup_icon
					from
						fm_provider A
						left join fm_provider_charge B on A.provider_seq = B.provider_seq and B.link = 1
						left join fm_provider_group C on A.pgroup_seq=C.pgroup_seq
					where
						A.provider_seq = '{$_GET['no']}'";
			$query = $this->db->query($sql);
			$data = $query->result_array();

			// 입점사 권한 체크
			if( $this->providerInfo['provider_seq'] !=  $_GET['no']){
				pageBack('올바른 접속이 아닙니다.');
				exit;
			}

			$data[0]['deli_zipcode']		= $data[0]['deli_zipcode'];
			$data[0]['info_zipcode']		= $data[0]['info_zipcode'];

			$data[0]['main_visual_name']	= basename($data[0]['main_visual']);
			$data[0]['mshop_url']			= '/mshop/?m='.$data[0]['provider_seq'];
			$mshop						= $this->providermodel->get_minishop_count($data[0]['provider_seq']);
			$data[0]['mshop_cnt']			= $mshop['cnt'];
			//[반응형스킨] light용 미니샵 정보 추가 :: 2018-11-01 pjw
			$data[0]['minishop_search_filter']	= explode(',', $data[0]['minishop_search_filter']);
			$data[0]['minishop_orderby']		= $data[0]['minishop_orderby'];
			$data[0]['minishop_status']			= explode(',', $data[0]['minishop_status']);

			if($data[0]['limit_ip']){
				$limit_row = explode("|", $data[0]['limit_ip']);
				$count = count($limit_row)-1;
				for($i=0; $i<$count; $i++){
					$limit_ip[] = explode(".", $limit_row[$i]);
				}
				$data[0]['limit_ip'] = $limit_ip;
			}

			$this->template->assign($data[0]);

			### CHARGE
			$sql = "select * from fm_provider_charge where provider_seq = '{$_GET['no']}' and link =0";
			$query = $this->db->query($sql);
			foreach ($query->result_array() as $row){
				$charge[] = $row;
			}
			$this->template->assign('charge_loop',$charge);

			### SHIPPING
			$sql = "select * from fm_provider_shipping where provider_seq = '{$_GET['no']}'";
			$query = $this->db->query($sql);
			$shipping = $query->result_array();

			$deli_text = "";
			if($shipping[0]['delivery_type']=='free'){
				$deli_text = "비용 : 무료";
			}else if($shipping[0]['delivery_type']=='pay'){
				$deli_text = "비용 : (선불) 유료 ".number_format($shipping[0]['delivery_price'])."원";
				if($shipping[0]['post_yn']=='Y') $deli_text .= ", (후불) 유료 ".number_format($shipping[0]['post_price'])."원";
			}else if($shipping[0]['delivery_type']=='ifpay'){
				$deli_text = "비용 : ".number_format($shipping[0]['if_free_price'])."원 이상 구매 시 무료, (선불) 유료 ".number_format($shipping[0]['delivery_price'])."원";
				if($shipping[0]['post_yn']=='Y') $deli_text .= ", (후불) 유료 ".number_format($shipping[0]['post_price'])."원";
			}
			$shipping[0]['deli_text'] = $deli_text;

			###
			$international = unserialize($shipping[0]['international']);
			$shipping[0]['weight']	= $international['defaultGoodsWeight'];
			$this->template->assign('int',$international);

			//$temp_arr = explode("|",$shipping['company_code']);
			$this->template->assign('shipping',$shipping[0]);

			### PERSON
			$person = array('ds1', 'ds2', 'cs', 'calcu', 'md', 'wcalcu');
			foreach($person as $k){
				unset($temp);
				$query = $this->db->query("select * from fm_provider_person where provider_seq = '{$_GET['no']}' and gb = '{$k}'");
				$temp = $query->result_array();
				$this->template->assign($k, $temp[0]);
			}

			### 추천상품리스트
			// 추천상품 타입이 직접선정인 경우 상품데이터 가져옴
			if($data[0]['auto_criteria_type'] == 'MANUAL'){
				$sql	= "SELECT r.*, g.*, o.price, (select image from fm_goods_image where goods_seq=g.goods_seq and cut_number=1 and image_type ='thumbCart' limit 1) as image FROM
							fm_goods g
							INNER JOIN fm_goods_option o ON g.goods_seq = o.goods_seq AND o.default_option = 'y'
							INNER JOIN fm_provider_relation r ON r.relation_goods_seq = g.goods_seq AND r.provider_seq = '{$_GET['no']}'
							ORDER BY r.relation_seq asc";

				$query					= $this->db->query($sql);
				$relation_goods_list	= $query->result_array();
				$this->template->assign('items',$relation_goods_list);
			}

			$param['provider_seq']	= $_GET['no'];
			$certify				= $this->providermodel->get_certify_manager($param);
			$this->template->assign('certify',$certify);
		}

		// 정산마감일 가져오기 마지막 마감일가져오기 :: 2018-08-23 lkh
		$nextConfirmArr	= $this->accountallmodel->get_account_setting('last');
		$nowConfirmArr	= $this->accountallmodel->get_account_setting('pre');
		if($nextConfirmArr['accountall_confirm'] == "8"){
			$nextConfirm = "익월 : 7일";
		}elseif($nextConfirmArr['accountall_confirm'] == "11"){
			$nextConfirm = "익월 : 10일";
		}else{
			$nextConfirm = "익월 : 월말";
		}
		if($nowConfirmArr['accountall_confirm'] == "8"){
			$nowConfirm = "당월 : 7일";
		}elseif($nowConfirmArr['accountall_confirm'] == "11"){
			$nowConfirm = "당월 : 10일";
		}else{
			$nowConfirm = "당월 : 월말";
		}
		$this->template->assign('nextConfirm',$nextConfirmArr['accountall_confirm']);
		// 신규 정산을 이용할 경우 마이그레이션 다음 달부터 적용되도록 처리 :: 2017-06-14 lkh
		$accountAllMiDate = config_load("accountall_setting","accountall_migration_date");
		$accountAllMigrationDate = $accountAllMiDate['accountall_migration_date'];
		$migrationYear = substr($accountAllMigrationDate,0,4);
		$migrationMonth = (substr($accountAllMigrationDate,5,2)+1);
		$migrationCheckDate = $migrationYear."-".sprintf("%02d",$migrationMonth);
		if( isset($_GET['no']) ){
			$nextPeriodArr	= $this->accountallmodel->get_account_provider_period('last',$_GET['no']);
			$nowPeriodArr	= $this->accountallmodel->get_account_provider_period('pre',$_GET['no']);
			$nextPeriod = $nextPeriodArr['accountall_period_count'];
			if($migrationCheckDate > date("Y-m")){
				if($data['calcu_count']){
					$nowPeriod = $data['calcu_count'];
				}else{
					if($nowPeriodArr['accountall_period_count']){
						$nowPeriod = $nowPeriodArr['accountall_period_count'];
					}else{
						$nowPeriod = "0";
					}
				}
				$nowConfirm = "당월 : 구 정산화면에서 정산";
			}else{
				if($nowPeriodArr['accountall_period_count']){
					$nowPeriod = $nowPeriodArr['accountall_period_count'];
				}else{
					$nowPeriod = "0";
				}
			}
		}else{
			$nextPeriod = 1;
			$nowPeriod = 1;
		}
		$accountAllPeriodConfirm = array("nextPeriod"	=> $nextPeriod,
										"nowPeriod"		=> $nowPeriod,
										"nextConfirm"	=> $nextConfirm,
										"nowConfirm"	=> $nowConfirm
									);
		$this->template->assign('accountAllPeriodConfirm',$accountAllPeriodConfirm);

		// 상품정보 선택기능 추가 :: 2019-05-17 pjw
		$this->load->model('designmodel');
		$goods_info_style = $this->designmodel->get_goods_info_style('search_list', $data[0]['goods_info_style']);

		$this->template->assign('goods_info_style', $goods_info_style);
		$this->template->define('goods_info_style', $this->skin.'/page_manager/_goods_info_style.html');

		$this->template->assign('noti_acount_priod',$noti_acount_priod);
		$this->template->define('tpl', $filePath);
		$this->template->define('condition', $this->skin.'/setting/_recommend.html');

		$this->template->print_("tpl");
	}

	public function provider_shipping(){
		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));

		if( $_GET['reg']=='Y' ){
			$arr = explode("|",$_GET['company_code']);
			$cnt = 0;
			foreach($arr as $k){
				$tmp = config_load('delivery_url',$k);
				$data['deliveryCompany'][$k]		= $tmp[$k]['company'];
				$data['deliveryCompanyCode'][$cnt]	= $k;
				$cnt++;
			}
			if(!$_GET['company_code']) unset($data['deliveryCompanyCode']);
			###
			$data['summary']				= $_GET['summary'];
			$data['useYn']					= $_GET['use_yn'];
			$data['deliveryCostPolicy']		= $_GET['delivery_type'];
			if($_GET['delivery_type']=='pay'){
				$data['payDeliveryCost']		= $_GET['delivery_price'];
				$data['postpaidDeliveryCost']	= $_GET['post_price'];
				if($_GET['post_price']>0) $data['postpaidDeliveryCostYn'] = 'y';
			}else if($_GET['delivery_type']=='ifpay'){
				$data['ifpayFreePrice']			= $_GET['if_free_price'];
				$data['ifpayDeliveryCost']		= $_GET['delivery_price'];
				$data['ifpostpaidDeliveryCost']	= $_GET['post_price'];
				if($_GET['post_price']>0) $data['ifpostpaidDeliveryCostYn'] = 'y';
			}

			$arr2 = explode("|",$_GET['add_delivery_cost']);
			$cnt = 0;
			foreach($arr2 as $k){
				$tmps = explode(":", $k);
				$data['sigungu'][$cnt]			= $tmps[0];
				$data['addDeliveryCost'][$cnt]	= $tmps[1];
				$cnt++;
			}
			/*
			echo "<pre>";
			print_r($data);
			*/
			$this->template->assign($data);
		}

		if( isset($_GET['seq']) && $_GET['seq']!="" ){
			$sql = "select * from fm_provider_shipping where provider_seq = '{$_GET['seq']}'";
			$query = $this->db->query($sql);
			$temp = $query->result_array();
			$data = $temp[0];

			$arr = explode("|",$temp[0]['company_code']);
			$cnt = 0;
			foreach($arr as $k){
				$tmp = config_load('delivery_url',$k);
				$data['deliveryCompany'][$k]		= $tmp[$k]['company'];
				$data['deliveryCompanyCode'][$cnt]	= $k;
				$cnt++;
			}
			###
			$data['useYn']					= $data['use_yn'];
			$data['deliveryCostPolicy']		= $data['delivery_type'];
			if($data['delivery_type']=='pay'){
				$data['payDeliveryCost']		= $data['delivery_price'];
				$data['postpaidDeliveryCost']	= $data['post_price'];
				if($data['post_price']>0) $data['postpaidDeliveryCostYn'] = 'y';
			}else if($data['delivery_type']=='ifpay'){
				$data['ifpayFreePrice']			= $data['if_free_price'];
				$data['ifpayDeliveryCost']		= $data['delivery_price'];
				$data['ifpostpaidDeliveryCost']	= $data['post_price'];
				if($data['post_price']>0) $data['ifpostpaidDeliveryCostYn'] = 'y';
			}

			$arr2 = explode("|",$temp[0]['add_delivery_cost']);
			$cnt = 0;
			foreach($arr2 as $k){
				$tmps = explode(":", $k);
				$data['sigungu'][$cnt]			= $tmps[0];
				$data['addDeliveryCost'][$cnt]	= $tmps[1];
				$cnt++;
			}
			/*
			echo "<pre>";
			print_r($data);
			*/
			$this->template->assign($data);
		}

		$this->template->print_("tpl");
	}


	public function shipping_international(){

		if( isset($_GET['seq']) ){
			$sql = "select * from fm_provider_shipping where provider_seq = '{$_GET['seq']}'";
			$query = $this->db->query($sql);
			$temp = $query->result_array();
			$international = unserialize($temp[0]['international']);

			if($international['deliveryCost']) $international['deliveryCost'] = explode("|",$international['deliveryCost']);
			if($international['exceptCategory']) $international['exceptCategory'] = explode("|",$international['exceptCategory']);
			if($international['goodsWeight']) $international['goodsWeight'] = explode("|",$international['goodsWeight']);
			if($international['region']) $international['region'] = explode("|",$international['region']);
			if($international['regionSummary']) $international['regionSummary'] = explode("|",$international['regionSummary']);
			if($international['arrDeliveryCost']) $international['arrDeliveryCost'] = explode("|",$international['arrDeliveryCost']);
			$data = $international;
			if($data['exceptCategory']){
				$this->load->model('categorymodel');
				foreach($data['exceptCategory'] as $k => $exceptCategory){
					$data['exceptCategoryName'][] = $this->categorymodel->get_category_name($exceptCategory);
				}
			}
			$this->template->assign($data);
		}

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	public function popup_image(){
		$file_path	= $this->template_path();
		$this->template->assign('goodsImageSize',config_load('goodsImageSize'));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function mshop_popup_image(){
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 확인코드 중복체크 및 유효성 체크
	public function chk_certify_code($cerfify_code = ''){

		$return	= 'ok';

		if	($_GET['certify_code'])
			$certify_code	= trim($_GET['certify_code']);

		if	($_GET['certify_seq'])
			$param['out_seq']	= trim($_GET['certify_seq']);

		if		(!$certify_code)											$return	= 'error_1';
		elseif	(strlen($certify_code) < 6 || strlen($certify_code) > 16)	$return	= 'error_2';
		elseif	(preg_match('/[^0-9a-zA-Z]/', $certify_code))				$return	= 'error_3';

		$this->load->model('providermodel');
		$param['certify_code']	= $certify_code;
		$certify				= $this->providermodel->get_certify_manager($param);
		if	($certify){
			$return	= 'duple';
		}

		if	($_GET['certify_code'])	echo $return;
		else						return $return;
	}

	public function default_add_delivery(){

		$query = "select * from fm_default_addshipping";

		$query = $this->db->query($query);
		$result = $query->result_array();

		$this->template->assign('loop',$result);
		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	// 굿스플로 사용현황 :: 2015-06-29 lwh
	public function goodsflow_log(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->model('goodsflowmodel');
		$sc					= $this->input->get();

		if ($this->providerInfo['provider_seq'])
		{
			$sc['provider_seq'] = $this->providerInfo['provider_seq'];
		}
		$sc['select_date_regist']	= isset($sc['select_date_regist'])	? $sc['select_date_regist']	: 'today';
		$log_list			= $this->goodsflowmodel->goodsflow_log_list($sc);

		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));
		$this->template->assign('pagin',$log_list['paginlay']);
		$this->template->assign('log_list',$log_list['list']);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 배송그룹 리스트 :: 2016-05-20 lwh */
	public function shipping_group()
	{
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('no', '일련번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('keyword', '검색어', 'trim|string|xss_clean');
			$this->validation->set_rules('search_type', '검색 선택', 'trim|string|xss_clean');
			$this->validation->set_rules('show_search_form', '검색 열기', 'trim|string|xss_clean');
			$this->validation->set_rules('shipping_calcul_type[]', '배송비 계산', 'trim|string|xss_clean');
			$this->validation->set_rules('shipping_calcul_free_yn[]', '무료 배송', 'trim|string|xss_clean');
			$this->validation->set_rules('kr_method', '대한민국 배송 방법', 'trim|string|xss_clean');
			$this->validation->set_rules('kr_set_code[]', '대한민국 배송', 'trim|string|xss_clean');
			$this->validation->set_rules('gl_method', '해외 배송 방법', 'trim|string|xss_clean');
			$this->validation->set_rules('gl_set_code[]', '해외 배송', 'trim|string|xss_clean');
			$this->validation->set_rules('default_type[]', '기본 배송', 'trim|string|xss_clean');
			$this->validation->set_rules('add_opt_type[]', '추가 배송', 'trim|string|xss_clean');
			$this->validation->set_rules('shipping_etc_search[]', '연결 상품 없음', 'trim|string|xss_clean');
			$this->validation->set_rules('orderby', '정렬', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$this->admin_menu();
		$this->tempate_modules();
		$filePath = $this->template_path();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_shipping_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		# default-setting
		$search_page = uri_string();
		$this->load->model('goodsmodel');
		$result = $this->goodsmodel->get_search_default_config($search_page);
		if( count($_GET) == 0 ){
			if($result['search_info']){
				parse_str($result['search_info'], $arr);
				if(is_array($arr)) $_GET = $arr;
			}
		}

		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= $_POST['provider_seq'];
			if(!$provider_seq)	$provider_seq = 1;
		}

		$sc = $aGetParams;
		if(!$sc['page']) $sc['page'] = 1;
		$sc['provider_seq'] = $provider_seq;

		// 배송그룹리스트
		$this->load->model('shippingmodel');
		$grp_list = $this->shippingmodel->shipping_group_list($sc);

		// 기본 배송그룹 생성
		if(count($grp_list['record']) == 0 && !$_GET){
			$this->shippingmodel->set_base_shipping_group($provider_seq);
			$this->shippingmodel->set_base_shipping_group($provider_seq,'coupon');
			$this->shippingmodel->set_base_shipping_group($provider_seq,'o2o');		// O2O배송그룹 추가
			echo "<script>alert('기본그룹을 생성했습니다.\\n화면을 새로고침 합니다.');location.reload();</script>";
			exit;
		}

		// 상품검색폼
		$this->template->define(array('shipping_search_form' => $this->skin.'/setting/shipping_search_form.html'));

		// 기본검색설정폼 분리 2017-03-20
		$this->template->define(array('set_search_default' => $this->skin.'/setting/_set_search_default_shipping.html'));
		$this->template->assign(array('search_page'=>uri_string()));

		$this->template->assign("grp_list",$grp_list['record']);
		$this->template->assign("grp_pagin",$grp_list['page']);
		$this->template->assign("provider_seq",$provider_seq);
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	// 기본 검색 설정 저장 :: 2017-03-21 lwh
	public function set_search_default(){
		$this->load->model('searchdefaultconfigmodel');
		$param = $_POST;
		$this->searchdefaultconfigmodel->set_search_default($param);
		$search_page = $_POST['search_page'];

		$callback = "parent.closeDialog('search_detail_dialog');parent.location.replace('/{$search_page}');";
		openDialogAlert("설정이 저장 되었습니다.",400,150,'parent',$callback);
	}

	// 기본 검색 설정 호출 :: 2017-03-21 lwh
	public function get_search_default(){
		$this->load->model('goodsmodel');
		if (isset($_GET['search_page'])) {
			$res = $this->goodsmodel->get_search_default_config($_GET['search_page']);
		}

		$arr = $result = array();
		if ($res['search_info']) {
			parse_str($res['search_info'], $arr);

			if(is_array($arr)) {
				foreach($arr as $k=>$v) {
					$result[] = array($k, $v);
				}
			}
		}

		echo json_encode($result);
	}

	/* 배송그룹 등록/수정 :: 2016-05-20 lwh */
	public function shipping_group_regist(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath = $this->template_path();

		$this->load->model('shippingmodel');

		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= $_POST['provider_seq'];
			if(!$provider_seq)	$provider_seq = 1;
		}

		//임시 seq 부여
		$shipping_group_dummy		= $this->shippingmodel->set_shipping_dummy($_GET['shipping_group_seq']);
		$shipping_group_dummy_seq	= $shipping_group_dummy['shipping_group_dummy_seq'];
		$shipping_group_seq			= $shipping_group_dummy['shipping_group_dummy_seq'];
		$shipping_calcul_type		= $shipping_group_dummy['shipping_calcul_type'];

		// 수정시
		if($_GET['shipping_group_seq']){
			// 배송그룹 호출
			$grp_info = $this->shippingmodel->get_shipping_group($shipping_group_seq);
			$grp_info['shipping_calcul_type'] = $shipping_calcul_type;

			if($grp_info['shipping_provider_seq'] != $provider_seq){
				echo "<script>alert('권한 없음');history.go(-1);</script>";
				exit;
			}

			// 요약정보 추출
			$grp_summary = $this->shippingmodel->get_shipping_group_summary($grp_info['shipping_group_seq']);
			$this->template->assign("grp_summary",$grp_summary);

			$this->template->assign("reg_type",'modify');
		}else{ // 등록시
			// 기본배송그룹 여부 판단
			$base_grp = $this->shippingmodel->get_shipping_base($provider_seq);
			if(!$base_grp['shipping_group_seq']){
				$grp_info['default_yn'] = 'Y';
			}
		}

		// 반송지 추출
		$grp_info['refund_address'] = $this->shippingmodel->get_default_address($provider_seq);

		// 언어 설정 추출 :: 2017-02-16 lwh
		$code_language = code_load('language',$this->config_system['language']);
		$language = $code_language[0];

		// 배송그룹 원본 문구 추출 :: 2017-02-20 lwh
		$this->load->model('alertmodel');
		$params = array('code'=>'dv');
		$msg_list = $this->alertmodel->alert_list($params);
		foreach($msg_list as $k => $msg){
			$info_msg[$msg['code']]['code']		= $msg['code'];
			$info_msg[$msg['code']]['msg']		= $msg[$language['codecd'].'_ORI'];
			$info_msg[$msg['code']]['cus_msg']	= $msg[$language['codecd']];
		}

		// 자동안내설명 스킨
		$this->template->define(array('delivery_desc' => $this->skin.'/setting/add_national_delivery_desc.html'));

		//임시 데이터 메모의 관리자 이름 삭제
		$grp_info['admin_memo'] = explode("||", $grp_info['admin_memo']);
		$grp_info['admin_memo'] = $grp_info['admin_memo'][1];

		$this->template->assign("shipping_group_dummy_seq", $shipping_group_dummy_seq);
		$this->template->assign("info_msg",$info_msg);
		$this->template->assign("language",$language);
		$this->template->assign("ship_grp",$grp_info);
		$this->template->assign("provider_seq",$provider_seq);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	/* 배송그룹 가능 국가 추가 팝업 :: 2016-05-24 lwh */
	public function add_national_pop(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath = $this->template_path();

		$this->load->model('shippingmodel');
		$ship_set_code = $this->shippingmodel->ship_set_code; // 배송설정코드

		// 배송비계산기준 표시용
		if($_GET['calcul_type'] == 'bundle'){
			$calcul_type_tit = '묶음계산-묶음배송';
		}else if($_GET['calcul_type'] == 'each'){
			$calcul_type_tit = '개별계산-개별배송';
		}else if($_GET['calcul_type'] == 'free'){
			$calcul_type_tit = '무료계산-묶음배송';
		}



		// 수정시 data 변경
		if($_POST['mode'] == 'modify'){
			$post_data = $_POST;
			$key = $_POST['idx'] - 1;
			foreach($post_data as $col => $pdata){
				if(is_array($pdata[$_POST['nation']]) === true){
					$params[$col] = $pdata[$_POST['nation']][$key];
				}else{
					$params[$col] = $pdata;
				}
			}
			// 희망배송 week 배열 재가공
			if($params['hopeday_limit_week']){
				for($i=0;$i<strlen($params['hopeday_limit_week']);$i++){
					$params['hopeday_limit_week_arr'][$i] = substr($params['hopeday_limit_week'],$i,1);
				}
			}
			// 희망배송 선택불가일 재가공
			if($params['limit_day_serialize']){
				$tmp_arr = unserialize($params['limit_day_serialize']);
				foreach($tmp_arr as $year => $days){
					$params['hopeday_limit_day_arr'][$year] = implode(', ',$days);
				}
			}
			// 설정명 커스텀 확인
			if($params['shipping_set_name'] == $ship_set_code[$params['shipping_set_code']]){
				$params['custom_set_use'] = 'N';
			}else{
				$params['custom_set_use'] = 'Y';
			}
			// 창고 배열 재가공
			if($params['store_use'] == 'Y'){
				foreach($params['store_address_seq'] as $i => $seq){
					$params['store_list_arr'][$i]['shipping_address_seq'] = $params['store_address_seq'][$i];
					$params['store_list_arr'][$i]['shipping_address_category'] = $params['shipping_address_category'][$i];
					$params['store_list_arr'][$i]['shipping_address_nation'] = $params['shipping_address_nation'][$i];
					$params['store_list_arr'][$i]['shipping_store_name'] = $params['shipping_store_name'][$i];
					$params['store_list_arr'][$i]['shipping_address_full'] = $params['shipping_address_full'][$i];
					$params['store_list_arr'][$i]['store_phone'] = $params['store_phone'][$i];
					$params['store_list_arr'][$i]['store_supply_set'] = $params['store_supply_set'][$i];
					$params['store_list_arr'][$i]['store_supply_set_view'] = $params['store_supply_set_view'][$i];
					$params['store_list_arr'][$i]['store_supply_set_order'] = $params['store_supply_set_order'][$i];
					$params['store_list_arr'][$i]['store_scm_type'] = $params['store_scm_type'][$i];
				}
			}

			if	($this->shippingmodel->shipping_type_arr){
				foreach ($this->shippingmodel->shipping_type_arr as $opt_type => $opt_name){
					if	($params[$opt_type . '_use'] == 'Y'){
						${$opt_type . 'Data'}['opt_type']		= $opt_type;
						${$opt_type . 'Data'}['use']			= $params[$opt_type . '_use'];
						${$opt_type . 'Data'}['shipping_opt_seq']= $params['shipping_opt_seq'][$opt_type];
						${$opt_type . 'Data'}['area_name']		= $params['shipping_area_name'][$opt_type];
						${$opt_type . 'Data'}['section_st']		= $params['section_st'][$opt_type];
						${$opt_type . 'Data'}['section_ed']		= $params['section_ed'][$opt_type];
						${$opt_type . 'Data'}['shipping_cost']	= $params['shipping_cost'][$opt_type];
						${$opt_type . 'Data'}['today_yn']		= $params['shipping_today_yn'][$opt_type];
						${$opt_type . 'Data'}['today_cost']		= $params['shipping_cost_today'][$opt_type];
						if($params['nation'] != 'korea'){
							${$opt_type . 'Data'}['street']		= $params['sel_address_street'][$opt_type];
							${$opt_type . 'Data'}['zibun']		= $params['sel_address_zibun'][$opt_type];
							${$opt_type . 'Data'}['join']		= $params['sel_address_join'][$opt_type];
							${$opt_type . 'Data'}['txt']		= $params['sel_address_txt'][$opt_type];
						}
						${$opt_type . 'Data'}['zone_count']		= $params['zone_count'][$opt_type];
						${$opt_type . 'Data'}['zone_cost_seq']	= $params['zone_cost_seq'][$opt_type];

						$optTypeArr[$opt_type]					= ${$opt_type . 'Data'};
					}
				}
			}

			$shipping_group_seq = $params['shipping_group_dummy_seq'];
			$is_dummy = "Y";
		}else{ // 기본 처리
			$params['section_st']['std'][0] = 0;
			$params['section_st']['add'][0] = 0;
			$params['section_st']['hop'][0] = 0;
			$params['section_ed']['std'][0] = 0;
			$params['section_ed']['add'][0] = 0;
			$params['section_ed']['hop'][0] = 0;

			$shipping_group_seq = $_GET['shipping_group_dummy_seq'];
			$is_dummy = "N";
		}

		$params['shipping_group_seq'] = $shipping_group_seq;
		if(!$shipping_group_seq){
			openDialogAlert("배송 고유 번호를 찾을 수 없습니다. 재시도 해주세요.",400,150,'parent',$callback);
			exit;
		}

		$params['shipping_group_real_seq'] = $_POST['shipping_group_real_seq'] ;

		if($params['shipping_set_seq'] <= 0){
			$datas = array();
			$datas['shipping_group_seq']	= $shipping_group_seq;
			$datas['default_yn']			= 'N';
			$datas['shipping_set_code']		= 'delivery';
			$datas['shipping_set_name']		= '택배';
			$datas['prepay_info']			= 'delivery';
			$datas['delivery_nation']		= $_GET['nation'];
			$datas['delivery_type']			= 'basic';
			$datas['delivery_limit']		= 'unlimit';
			$datas['add_use']				= 'N';

			$this->db->insert("fm_shipping_set", $datas);
			$shipping_set_seq = $this->db->insert_id();
			unset($datas);

			$this->shippingmodel->get_seqs(array('shipping_group_seq' => $shipping_group_seq, 'shipping_set_seq' => $shipping_set_seq, 'p_type' => 'std', 'shipping_opt_type' => 'free', 'nation' => $_GET['nation'], 'shipping_opt_sec_cost' => array()));
			$params['shipping_set_seq'] = $shipping_set_seq;
		}

		$zoneInfo = $this->shippingmodel->get_cost_list($shipping_group_seq, $params['shipping_set_seq']);
		$params['shipping_cost_seq']	= $zoneInfo['shipping_cost_seq'];

		foreach($this->shippingmodel->shipping_type_arr as $k => $v){
			$optTypeArr[$k]['shipping_cost_seq'] = $zoneInfo['shipping_cost_seq'][$k];
		}

		if($zoneInfo['zone_cost_seq']){
			foreach($this->shippingmodel->shipping_type_arr as $k => $v){
				if($zoneInfo[$k.'_use'] == 'Y'){
					$params[$k.'_use'] = 'Y';
				} else {
					$params[$k.'_use'] = 'N';
				}
			}

			$params['shipping_opt_type']	= $zoneInfo['shipping_opt_type'];
			$params['shipping_opt_seq']		= $zoneInfo['shipping_opt_seq'];
			$params['shipping_area_name']	= $zoneInfo['shipping_area_name'];
			$params['zone_cost_seq']		= $zoneInfo['zone_cost_seq'];
			$params['zone_count']			= $zoneInfo['zone_count'];
			$params['section_st']			= $zoneInfo['section_st'];
			$params['section_ed']			= $zoneInfo['section_ed'];
			$params['shipping_cost']		= $zoneInfo['shipping_cost'];
			$params['delivery_limit']		= $zoneInfo['delivery_limit']['std'];
			$params['std_use']				= 'Y';

			foreach($this->shippingmodel->shipping_type_arr as $k => $v){
				$optTypeArr[$k]['area_name']		= $zoneInfo['area_name'][$k];
				$optTypeArr[$k]['section_st']		= $zoneInfo['section_st'][$k];
				$optTypeArr[$k]['section_ed']		= $zoneInfo['section_ed'][$k];
				$optTypeArr[$k]['zone_count']		= $zoneInfo['zone_count'][$k];
				$optTypeArr[$k]['zone_cost_seq']	= $zoneInfo['zone_cost_seq'][$k];
				$optTypeArr[$k]['shipping_cost']	= $zoneInfo['shipping_cost'][$k];
				$optTypeArr[$k]['shipping_opt_seq']	= $zoneInfo['shipping_opt_seq'][$k];
				$optTypeArr[$k]['opt_type']			= $k;
				if($params['nation'] != 'korea'){
					$optTypeArr[$k]['street']		= $zoneInfo['sel_address_street'][$k];
					$optTypeArr[$k]['zibun']		= $zoneInfo['sel_address_zibun'][$k];
					$optTypeArr[$k]['join']			= $zoneInfo['sel_address_join'][$k];
					$optTypeArr[$k]['txt']			= $zoneInfo['sel_address_txt'][$k];
				}

				$optTypeArr[$k]['use']				= $zoneInfo[$k.'_use'];
			}

			$optTypeArr['std']['use'] = 'Y';
		}

		// 자동안내설명 스킨
		$this->template->define(array('delivery_desc' => $this->skin.'/setting/add_national_delivery_desc.html'));

		$this->template->assign("optTypeArr",$optTypeArr);
		$this->template->assign("params",$params);
		$this->template->assign("punit",$this->config_system['basic_currency']);
		$this->template->assign("calcul_type_tit",$calcul_type_tit);
		$this->template->assign("ship_set_code",$ship_set_code);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	// 택배사 설정 :: 2016-08-17 lwh
	public function delivery_company(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();
		$this->load->model('providershipping');

		$provider_seq			= $this->providerInfo['provider_seq'];
		$delivery_url			= config_load('delivery_url');
		$deliveryCompanyCode	= config_load('providerDeliveryCompanyCode', $provider_seq);
		$deliveryCompanyCode	= $deliveryCompanyCode[$provider_seq];
		if	($delivery_url && $deliveryCompanyCode){
			foreach($delivery_url as $code => $data){
				if	(in_array($code, $deliveryCompanyCode)){
					$key = array_search($code, $deliveryCompanyCode);
					$tmpSel[$key][$code]	= $data;
				}else{
					$deliveryCompany[$code]	= $data;
				}
			}
			for($i=0; $i <= count($tmpSel); $i++){
				foreach($tmpSel[$i] as $key => $val)		$selectedCompany[$key] = $val;
			}
		}else{
			$deliveryCompany	= $delivery_url;
		}

		## 롯데택배업무자동화서비스 세팅값 :: 2015-07-10
		$this->load->model('invoiceapimodel');
		$config_invoice = $this->invoiceapimodel->get_invoice_setting($provider_seq);
		$this->template->assign("config_invoice",$config_invoice);

		## 우체국택배업무자동화서비스 세팅값 :: 2016-03-29 lwh
		$this->load->model('epostmodel');
		$config_epost = $this->epostmodel->get_epost_setting($provider_seq);
		if(!$config_epost['biz_name']){ // 정보가 없으면 기본적으로 사업자 정보를 넣어줌.
			if($provider_seq == 1){
				if($this->config_basic['companyAddress_type'] == 'street'){
					$address = $this->config_basic['companyAddress_street'] . ' ' . $this->config_basic['companyAddressDetail'];
				}else{
					$address = $this->config_basic['companyAddress'] . ' ' . $this->config_basic['companyAddressDetail'];
				}
				$config_epost['biz_name']		= $this->config_basic['companyName'];
				$config_epost['biz_ceo']		= $this->config_basic['ceo'];
				$config_epost['biz_no']			= $this->config_basic['businessLicense'];
				$config_epost['biz_zipcode']	= $this->config_basic['companyZipcode'];
				$config_epost['biz_address']	= $address;
				$config_epost['biz_phone']		= $this->config_basic['companyPhone'];
				$config_epost['biz_email']		= $this->config_basic['companyEmail'];
			}else{
				$this->load->model('providermodel');
				$provider_info = $this->providermodel->get_provider($provider_seq);

				if($provider_info['info_address1_type'] == 'street'){
					$address = $provider_info['info_address1_street'] . ' ' . $provider_info['info_address2'];
				}else{
					$address = $provider_info['info_address1'] . ' ' . $$provider_info['info_address2'];
				}
				$config_epost['biz_name']		= $provider_info['info_name'];
				$config_epost['biz_ceo']		= $provider_info['info_ceo'];
				$config_epost['biz_no']			= $provider_info['info_num'];
				$config_epost['biz_zipcode']	= $provider_info['info_zipcode'];
				$config_epost['biz_address']	= $address;
				$config_epost['biz_phone']		= $provider_info['info_phone'];
				$config_epost['biz_email']		= $provider_info['info_email'];
			}
		}
		$this->template->assign("config_epost",$config_epost);
		## 우체국택배업무자동화서비스 END :: 2016-03-29 lwh

		## 굿스플로 입점사 이용유무 없을경우 기본값 지정 :: 2015-07-17 lwh
		if($this->config_system['goodsflow_use']==''){
			config_save('system',array('goodsflow_use'=>'1'));
		}

		## 굿스플로 서비스 세팅값 및 결과체크 :: 2015-06-12 lwh
		$this->load->model('goodsflowmodel');
		$this->config_goodsflow = config_load('goodsflow');
		$this->config_goodsflow['setting'] = $this->goodsflowmodel->get_goodsflow_setting($provider_seq);
		$service_cnt	= $this->goodsflowmodel->get_service_info('view');
		// 연동 신청중일때 신청결과 재 확인
		if($this->config_goodsflow['setting']['goodsflow_step']=='2'){
			$apiParam['requestKey'] = $this->config_goodsflow['setting']['requestKey'];
			$apiRespon	= $this->goodsflowmodel->apiSender('getServiceResult',$apiParam);
			if($apiRespon['result']){ // 결과가 변동되어 재 호출
				$step_param['goodsflow_step']	= $apiRespon['goodsflow_step'];
				$step_param['goodsflow_msg']	= $apiRespon['goodsflow_msg'];
				$step_param['goodsflow_err']	= $apiRespon['goodsflow_err'];
				$this->goodsflowmodel->set_goodsflow_step($provider_seq,$step_param);
				$this->config_goodsflow['setting'] = $this->goodsflowmodel->get_goodsflow_setting($provider_seq);
			}
		}
		// 설정 필요 시 저장 후 재로드 :: 2017-11-29 lwh
		if(!$this->config_goodsflow['terms']['boxname'] || $this->config_goodsflow['setting']['goodsflow_step'] != 1){
			$goodsflow_deli = $this->goodsflowmodel->delivery_set();
			config_save('goodsflow',array('terms'=>$goodsflow_deli));
			$this->config_goodsflow = config_load('goodsflow');
			$this->config_goodsflow['setting'] = $this->goodsflowmodel->get_goodsflow_setting($provider_seq);
		}

		//5자리 우편번호 양식(6자리 우편번호도 "-"  없음)
		$this->config_goodsflow['setting']['goodsflowNewZipcode']	= implode('', (array)$this->config_goodsflow['setting']['goodsflowZipcode']);

		$this->template->assign("config_goodsflow",$this->config_goodsflow);
		$this->template->assign("service_cnt",(int)$service_cnt);


		$this->template->assign(array(
			'provider_seq'		=> $provider_seq,
			'deliveryCompany'	=> $deliveryCompany,
			'selectedCompany'	=> $selectedCompany,
		));
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function popup_print_setting(){

		$this->tempate_modules();

		$provider_seq	= $this->providerInfo['provider_seq'];
		$query			= $this->db->query("select * from fm_setting_print where provider_seq=?",$provider_seq);
		$data			= $query->row_array();

		$shopName		= $this->config_basic['shopName'];
		$domain			= $this->config_system['domain'];

		if(! $shopName ) $shopName = "○○○몰";
		if(! $domain ) $domain = "www.○○○○○.com";

		if( !$data['order_centerinfo_message'] ){
			$data['order_centerinfo_message'] = "<table width='100%' style='border-collapse:collapse;border-top:1px solid #aaa;border-right:1px solid #dadada;' cellpadding='0' cellspacing='0'>
			<tr>
				<td style='border-left:1px solid #dadada;border-bottom:1px solid #dadada;padding:5px;'>
					".$shopName." (".$domain.")
				</td>
				<td style='border-left:1px solid #dadada;border-bottom:1px solid #dadada;padding:5px;'>
					<ul>
					<li>고객만족센터 : 0000-0000 (운영시간 평일 10시~18시, 주말/공휴일 휴무)</li>
					<li>취소 : 해당 상품의 결제취소 수량입니다.</li>
					</ul>
				</td>
			</tr>
			</table>";
		}

		if( !$data['export_centerinfo_message'] ){
			$data['export_centerinfo_message'] = "<table width='100%' style='border-collapse:collapse;border-top:1px solid #aaa;border-right:1px solid #dadada;' cellpadding='0' cellspacing='0'>
			<tr>
				<td style='border-left:1px solid #dadada;border-bottom:1px solid #dadada;padding:5px;'>
					".$shopName." (".$domain.")
				</td>
				<td style='border-left:1px solid #dadada;border-bottom:1px solid #dadada;padding:5px;'>
					<ul>
					<li>고객만족센터 : 0000-0000 (운영시간 평일 10시~18시, 주말/공휴일 휴무)</li>
					<li>취소 : 해당 상품의 결제취소 수량입니다.</li>
					<li>발송 : 본 발송(출고)내역서로 배송해 드리는 해당 상품의 발송수량입니다.</li>
					</ul>
				</td>
			</tr>
			</table>";
		}



		//2016.04.20 바코드 설정 불러오기 추가 pjw
		$this->load->model('barcodemodel');
		$barcode_info		= $this->barcodemodel->get_barcode_info();
		$this->template->assign(array('use_code'			=>$barcode_info[$barcode_info['use_code']]));
		$this->template->assign(array('use_code_order'		=>$barcode_info[$barcode_info['use_code_order']]));

		$this->template->assign($data);
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}
	public function shipping_zone_download()
	{
		$shipping_cost_seq		= $_GET['shipping_cost_seq'];
		$shipping_group_name	= $_GET['shipping_group_name'];
		$zone_name				= $_GET['zone_name'];

		if($shipping_cost_seq <= 0){
			echo 'ERROR';
			exit;
		}

		$this->db->select('area_detail_address_txt');
		$this->db->where('shipping_cost_seq', $shipping_cost_seq);
		$res = $this->db->get('fm_shipping_area_detail');

		if($res->result()){
			$writer = WriterFactory::create(Type::XLSX); // for XLSX files
			$fileName = $shipping_group_name."_".$zone_name."_".date('YmdHis').".xlsx";
			$filePath = ROOTPATH . "data/tmp/".$fileName;

			$writer->openToFile($filePath);
			$writer->addRow(array('지역명'));

			foreach($res->result() as $v){
				$writer->addRow(array($v->area_detail_address_txt));
			}
			$writer->close();

			echo $filePath;
			exit;
		} else {
			echo '0';
			exit;
		}
	}

	public function shipping_zone_upload()
	{
		//특정한 사유로 동일한 파일명으로 3초이내 업로드시 제한 @2017-06-01
		$date			= date('Y-m-d H:i:s', strtotime('-3 second'));
		$secondckLog	= $this->db->query("seLECT * FROM fm_excel_upload_log WHERE upload_date > '".$date."' limit 1")->result_array();
		if ($secondckLog[0]) {
			foreach($secondckLog as $secondckLogquery => $sklog){
				$fileinfo = $_FILES['zone_excel_file'];//첨부파일과 로그파일명 비교
				if( $sklog['upload_filename'] == $fileinfo['name'] ) {
					echo "ERROR_ACCESS";
					exit;
				}
			}
		}

		$this->load->library('upload');
		$fileinfo = $_FILES['zone_excel_file'];
		if (is_uploaded_file($fileinfo['tmp_name'])) {
			$fileName				= "upload_zone_excel_" . date('YmdHis') . rand(0,9999);

			$cfg					= array();
			$cfg['allowed_types']	= 'xlsx';
			$cfg['file_name']		= $fileName;
			$cfg['upload_path']		= ROOTPATH . "data/tmp/";
			$cfg['overwrite']		= TRUE;

			$this->upload->initialize($cfg);
			if ($this->upload->do_upload('zone_excel_file')) {
				$filePath = $cfg['upload_path'] . $cfg['file_name'] . '.' . $cfg['allowed_types'];

				@chmod($filePath, 0777);
			}else{
				echo "ERROR_FILE_EXE";
				exit;
			}
		}else{
			echo "ERROR_FILE";
			exit;
		}

		ini_set("memory_limit",-1);
		set_time_limit(0);

		$this->load->helper('zipcode');
		$this->load->model("shippingmodel");
		$ZIP_DB = get_zipcode_db();
		$params = array();

		$shipping_cost_seq 	= $_POST['shipping_cost_seq'];
		$shipping_group_seq	= $_POST['shipping_group_seq'];
		$nation				= $_POST['nation'];

		if(!is_array($_POST['shipping_opt_sec_cost']) && $_POST['shipping_opt_sec_cost']){
			$_POST['shipping_opt_sec_cost'] = explode(',', $_POST['shipping_opt_sec_cost']);
		}

		if(!is_array($_POST['shipping_opt_sec_st']) && $_POST['shipping_opt_sec_st']){
			$_POST['shipping_opt_sec_st'] = explode(',', $_POST['shipping_opt_sec_st']);
		}

		if(!is_array($_POST['shipping_opt_sec_ed']) && $_POST['shipping_opt_sec_ed']){
			$_POST['shipping_opt_sec_ed'] = explode(',', $_POST['shipping_opt_sec_ed']);
		}

		$seqsDatas		= $this->set_cost_seqs($_POST);
		$optionsSeqs	= $seqsDatas['optionsSeqs'];
		$costSeqs		= $seqsDatas['costSeqs'];

		$reader = ReaderFactory::create(Type::XLSX);
		$reader->open($filePath);
		foreach ($reader->getSheetIterator() as $sheet) {
			foreach ($sheet->getRowIterator() as $num => $row) {
				if($num > 1 && array_filter($row)){ //서식 설정만으로도 셀 내용 인식하여 추가
					$sqlRI			= "";
					$groupBy		= " GROUP BY ZIPCODE";
					preg_match('/\(([^\)]*)\)/', $row[0], $match);

					if($match[1]){
						$sqlRI		= " AND RI = '".$match[1]."'";
						$row[0]		= preg_replace("/\([^)]+\)/","", $row[0]);
					} else {
						$groupBy	.= " , RI";
					}

					$addrs = explode(' ', $row[0]);
					$addrs = array_filter($addrs);
					$addrs = array_values($addrs);

					$addrsNew		= array();
					$addrsNew[0]	= $addrs[0];

					if($addrs[0] == '세종특별자치시'){
						if(strlen($addrs[1])){
							$addrsNew[1] = '';
							$addrsNew[2] = $addrs[1];

							if(strlen($addrs[2])){
								$addrsNew[3] = $addrs[2];
							}
						}
					} else {
						if(strlen($addrs[1])){
							$is_chk = iconv_substr($addrs[2], iconv_strlen($addrs[2], "utf-8")-1, 1, "utf-8");
							if($is_chk == '시' || $is_chk == '군' || $is_chk == '구'){
								$addrsNew[1] = $addrs[1].' '.$addrs[2];

								if(strlen($addrs[3])){
									$addrsNew[2] = $addrs[3];

									if(strlen($addrs[4])){
										$addrsNew[3] = $addrs[4];
									}
								}
							} else {
								$addrsNew[1] = $addrs[1];

								if(strlen($addrs[2])){
									$addrsNew[2] = $addrs[2];

									if(strlen($addrs[3])){
										$addrsNew[3] = $addrs[3];
									}
								}
							}
						}
					}


					$addrs = $addrsNew;
					unset($addrsNew);

					//debug_var($addrs);

					$sql = '';
					$is_street = 'N';
					if(count($addrs) == 4){
						$is_street = 'Y';
						$sql = "seLECT * fROM zipcode_street_new
								wHERE SIDO = ? AND SIGUNGU = ? AND DONG = ? AND STREET = ? ".$sqlRI.$groupBy.";";
					} else if(count($addrs) == 3){
						$sql = "seLECT * fROM zipcode_street_new wHERE SIDO = ? AND SIGUNGU = ? AND DONG = ? limit 1;";
					} else if(count($addrs) == 2){
						$sql = "seLECT * fROM zipcode_street_new wHERE SIDO = ? AND SIGUNGU = ? limit 1;";
					} else {
						$addrsInfo = array(
							'강원도',
							'경기도',
							'경상남도',
							'경상북도',
							'광주광역시',
							'대구광역시',
							'대전광역시',
							'부산광역시',
							'서울특별시',
							'세종특별자치시',
							'울산광역시',
							'인천광역시',
							'전라남도',
							'전라북도',
							'제주특별자치도',
							'충청남도',
							'충청북도',
						);

						if(array_search($addrs[0], $addrsInfo)){
							$result = $addrs;
						}
					}

					if(strlen($sql) > 0){
						$res	= $ZIP_DB->query($sql, $addrs);
						$result	= $res->result_array();
					}

					if ($result) {
						if($is_street == 'N'){
							$addrs = array_filter($addrs);

							$area_detail_address_join	= implode('||', $addrs);
							$area_detail_address_txt	= implode(' ', $addrs);
							$area_detail_address_zibun	= implode(' ', $addrs);
							$area_detail_address_street	= implode(' ', $addrs);
						} else {
							foreach($result as $v){
								if($addrs[0] == '세종특별자치시'){
									$area_detail_address_join	= $v['SIDO'].'||'.$v['DONG'].'||'.$v['STREET'];
									$area_detail_address_txt	= $v['SIDO'].' '.$v['DONG'].' '.$v['STREET'];
									$area_detail_address_zibun	= $v['SIDO'].' '.$v['DONG'].' '.$v['RI'];
									$area_detail_address_street	= $v['SIDO'].' '.$v['DONG'].' '.$v['STREET'];

								} else {
									$area_detail_address_join	= $v['SIDO'].'||'.$v['SIGUNGU'].'||'.$v['DONG'].'||'.$v['STREET'];
									$area_detail_address_txt	= $v['SIDO'].' '.$v['SIGUNGU'].' '.$v['DONG'].' '.$v['STREET'];
									$area_detail_address_zibun	= $v['SIDO'].' '.$v['SIGUNGU'].' '.$v['DONG'].' '.$v['RI'];
									$area_detail_address_street	= $v['SIDO'].' '.$v['SIGUNGU'].' '.$v['DONG'].' '.$v['STREET'];
								}

								if ($v['RI']) {
									$area_detail_address_join	.= '||'.$v['RI'];
									$area_detail_address_txt	.= ' ('.$v['RI'].')';
								}
							}
						}

						$this->db->select('area_detail_seq');
						$this->db->where('shipping_cost_seq', $shipping_cost_seq);
						$this->db->where('area_detail_address_txt', $area_detail_address_txt);
						$res = $this->db->get('fm_shipping_area_detail');

						if(!$res->result()){
							$params[] = array(
								'shipping_group_seq_tmp'		=> $shipping_group_seq,
								'area_nation_type'				=> $nation,
								'area_detail_address_join'		=> $area_detail_address_join,
								'area_detail_address_txt'		=> $area_detail_address_txt,
								'area_detail_address_zibun'		=> $area_detail_address_zibun,
								'area_detail_address_street'	=> $area_detail_address_street
							);
						}
					}

					//중복제거
					$params = array_map("unserialize", array_unique(array_map("serialize", $params)));

					if(count($params) > 1000){
						$reader->close();
						echo "ERROR_LIMIT";
						exit;
					}
				}
			}
		}

		$reader->close();

		$inDatas = array();
		foreach($costSeqs as $k => $seq){
			foreach($params as $j => $v){
				$v['shipping_cost_seq'] = $seq;
				$inDatas[] = $v;
			}
		}

		if(count($inDatas) > 0){
			$this->db->insert_batch('fm_shipping_area_detail', $inDatas);
			$total = $this->db->affected_rows();

			if($total <= 0){
				$num = 0;
			} else {
				$num = $total/count($costSeqs);
			}
			$shipping_cost_seq	= end($costSeqs);

			$this->db->select('area_detail_seq');
			$this->db->where('shipping_cost_seq', $shipping_cost_seq);
			$totalCount = $this->db->count_all_results("fm_shipping_area_detail");

			$return = array('num' => $num, 'total' => $totalCount, 'shipping_cost_seq' => $shipping_cost_seq, 'shipping_costs_seqs' => $costSeqs);
		}

		if(count($optionsSeqs) > 0){
			$this->db->where_in('shipping_opt_seq', $optionsSeqs);
			$this->db->update('fm_shipping_option', array('delivery_limit' => 'limit'));

			$this->db->where_in('shipping_cost_seq', $costSeqs);
			$this->db->update('fm_shipping_cost', array('shipping_area_name' => $_POST['zone_name']));
		}

		foreach($_POST['shipping_opt_sec_cost'] as $k => $cost){
			$this->db->where('shipping_cost_seq', $costSeqs[$k]);
			$this->db->update('fm_shipping_cost', array('shipping_cost' => $cost));
		}

		echo json_encode($return);
		exit;
	}

	public function shipping_zone_list()
	{
		$shipping_cost_seq = $_GET['shipping_cost_seq'];

		if ($shipping_cost_seq <= 0) {
			echo 'ERROR';
			exit;
		}

		if($_GET['offset'] <= 0){
			$offset = 20;
		} else {
			$offset = $_GET['offset'];
		}

		if($_GET['perpage'] <= 0){
			$perpage = 0;
		} else {
			$perpage = ($_GET['perpage'] - 1) * $offset;
		}

		$keyword = trim($_GET['keyword']);
		if(strlen($keyword) >= 3){
			$keyword = $keyword;
		} else {
			$keyword = '';
		}

		$this->load->model('shippingmodel');
		$res['list'] = $this->shippingmodel->get_shipping_zone_list($shipping_cost_seq, $perpage, $offset, $keyword);

		if($_GET['total'] <= 0){
			$total = $this->shippingmodel->get_shipping_zone_count($shipping_cost_seq, "shipping_cost_seq", $keyword);
			$total = $total[0]['shipping_zone_count'];
			$res['total'] = $total;
		} else {
			$total = $_GET['total'];
		}

		if($total > $offset){
			if($_GET['perpage'] <= 0){
				$curpage = 1;
			} else {
				$curpage = $_GET['perpage'];
			}

			if($_GET['type'] == 'shipping_zone_list'){
				$res['paging'] = pagingtagjs($curpage, $offset, $total, 'shipping_zone_list(\'\', \''.$shipping_cost_seq.'\', \''.$total.'\', [:PAGE:], \''.$keyword.'\')', 10);
			} else {
				$res['paging'] = pagingtagjs($curpage, $offset, $total, 'ship_zone_pop_ajax(this, \''.$shipping_cost_seq.'\', \''.$total.'\', [:PAGE:], \''.$offset.'\')', 10);
			}
		}

		echo json_encode($res);
		exit;
	}

	public function shipping_zone_delete()
	{
		$zone_seq = $_GET['zone_seq'];
		if (count($zone_seq) <= 0) {
			echo 'ERROR';
			exit;
		}

		$option_seqs = array_filter($_GET['option_seqs']);
		if (count($option_seqs) <= 0) {
			echo 'ERROR';
			exit;
		}

		$this->db->select('shipping_cost_seq');
		$this->db->where_in('shipping_opt_seq', $option_seqs);
		$optionDatas = $this->db->get('fm_shipping_cost');

		$costSeqs = array();
		foreach($optionDatas->result_array() as $v){
			$costSeqs[] = $v['shipping_cost_seq'];
		}

		$zoneNames = array();
		foreach($zone_seq as $seq){
			$this->db->select('area_detail_address_join');
			$this->db->where('	area_detail_seq', $seq);
			$zoneDatas = $this->db->get('fm_shipping_area_detail');
			$zoneDatas = $zoneDatas->result_array();
			$zoneNames[] = $zoneDatas[0]['area_detail_address_join'];
		}

		$this->db->where_in('area_detail_address_join', $zoneNames);
		$this->db->where_in('shipping_cost_seq', $costSeqs);
		$this->db->delete('fm_shipping_area_detail');

		echo count($zone_seq);
		exit;
	}

	public function set_cost_seqs($params)
	{
		$costSeqs	= array();
		$optSeqs = $this->shippingmodel->get_option_seqs($params);
		$costSeqs = $this->shippingmodel->get_cost_seqs($optSeqs, $params);

		if(count($costSeqs) <= 0){
			$costDatas = $this->shippingmodel->get_seqs($params);
			$optionsSeqs = array();

			foreach($costDatas as $opt => $cost){
				$optionsSeqs[] = $opt;
				foreach($cost as $k => $v){
					if($params['shipping_cost_seq'] <= 0){
						$costSeqs[] = $v;
					} else {
						if($k == $params['idx']){
							$costSeqs[] = $v;
						}
					}
				}
			}
		}

		$return					= array();
		$return['optionsSeqs']	= $optionsSeqs;
		$return['costSeqs']		= $costSeqs;

		return $return;
	}

	public function shipping_zone_insert()
	{
		$address			= $_GET['addrs'];
		$street				= $_GET['street'];
		$shipping_cost_seq 	= $_GET['shipping_cost_seq'];
		$shipping_group_seq	= $_GET['shipping_group_seq'];
		$nation				= $_GET['nation'];

		$this->load->model("shippingmodel");
		//$this->db->trans_begin();

		$seqsDatas	= $this->set_cost_seqs($_GET);
		$optionsSeqs= $seqsDatas['optionsSeqs'];
		$costSeqs	= $seqsDatas['costSeqs'];

		$shipping_cost_seq = end($costSeqs);
		$datas = array();

		if($nation == 'korea'){
			if (count($address) <= 0 || $shipping_group_seq <= 0 || !$nation || count($costSeqs) <= 0) {
				echo 'ERROR';
				exit;
			}

			if(count($street) > 0 && is_array($street)){
				foreach($street as $st){
					$datas['street'][] = implode(" ", $address)." ".$st;
				}
			} else {
				$datas['addr'] = implode(" ", $address);
			}

			if(count($datas['street']) > 0){
				$this->load->helper('zipcode');
				$ZIP_DB = get_zipcode_db();

				foreach($datas['street'] as $row){
					$sqlRI			= "";
					$groupBy		= " GROUP BY ZIPCODE";
					$match = explode("||", $row);

					if($match[1]){
						$sqlRI		= " AND RI = '".$match[1]."'";
						$row		= preg_replace("||", "", $match[0]);
					} else {
						$groupBy	.= " , RI";
					}

					$addrs = explode(' ', $row);
					$addrs = array_filter($addrs);

					$addrsNew		= array();
					$addrsNew[0]	= $addrs[0];

					$is_chk = iconv_substr($addrs[2], iconv_strlen($addrs[2], "utf-8")-1, 1, "utf-8");
					$is_sejong = false;
					if($is_chk == '시' || $is_chk == '군' || $is_chk == '구'){
						$addrsNew[1]	= $addrs[1].' '.$addrs[2];
						$addrsNew[2]	= $addrs[3];
						$addrsNew[3]	= $addrs[4];
					} else if($addrs[0] == '세종특별자치시'){
						$is_sejong = true;
						$addrsNew[1]	= '';
						$addrsNew[2]	= $addrs[1];
						$addrsNew[3]	= $addrs[2];
					} else {
						$addrsNew[1]	= $addrs[1];
						$addrsNew[2]	= $addrs[2];
						$addrsNew[3]	= $addrs[3];
					}
					$addrs = $addrsNew;
					unset($addrsNew);

					$sql	= "seLECT * fROM zipcode_street_new
							wHERE SIDO = ? AND SIGUNGU = ? AND DONG = ? AND STREET = ? ".$sqlRI.$groupBy.";";

					$res	= $ZIP_DB->query($sql, $addrs);
					$result	= $res->result_array();

					if ($result) {
						foreach($result as $v){
							if($is_sejong){
								$area_detail_address_join	= $v['SIDO'].'||'.$v['STREET'];
								$area_detail_address_txt	= $v['SIDO'].' '.$v['STREET'];
								$area_detail_address_zibun	= $v['SIDO'].' '.$v['DONG'];
								$area_detail_address_street	= $v['SIDO'].' '.$v['STREET'];
							} else {
								$area_detail_address_join	= $v['SIDO'].'||'.$v['SIGUNGU'].'||'.$v['DONG'].'||'.$v['STREET'];
								$area_detail_address_txt	= $v['SIDO'].' '.$v['SIGUNGU'].' '.$v['DONG'].' '.$v['STREET'];
								$area_detail_address_zibun	= $v['SIDO'].' '.$v['SIGUNGU'].' '.$v['DONG'].' '.$v['RI'];
								$area_detail_address_street	= $v['SIDO'].' '.$v['SIGUNGU'].' '.$v['DONG'].' '.$v['STREET'];
							}

							if ($v['RI']) {
								$area_detail_address_join	.= '||'.$v['RI'];
								$area_detail_address_txt	.= ' ('.$v['RI'].')';
							}

							$this->db->select('area_detail_seq');
							$this->db->where('shipping_cost_seq', $shipping_cost_seq);
							$this->db->where('area_detail_address_txt', $area_detail_address_txt);
							$res = $this->db->get('fm_shipping_area_detail');

							if(!$res->result()){
								$params[] = array(
									//'shipping_cost_seq'				=> $shipping_cost_seq,
									'shipping_group_seq_tmp'		=> $shipping_group_seq,
									'area_nation_type'				=> $nation,
									'area_detail_address_join'		=> $area_detail_address_join,
									'area_detail_address_txt'		=> $area_detail_address_txt,
									'area_detail_address_zibun'		=> $area_detail_address_zibun,
									'area_detail_address_street'	=> $area_detail_address_street
								);
							}
						}
					}
				}
			} else {
				$addr_join		= implode('||', $address);
				$addr_zibun		= implode(' ', $address);
				$addr_street	= implode(' ', $address);

				$this->db->select('area_detail_seq');
				$this->db->where('shipping_cost_seq', $shipping_cost_seq);
				$this->db->where('area_detail_address_txt', $datas['addr']);
				$res = $this->db->get('fm_shipping_area_detail');

				if(!$res->result()){
					$params[] = array(
						'shipping_group_seq_tmp'		=> $shipping_group_seq,
						'area_nation_type'				=> $nation,
						'area_detail_address_join'		=> $addr_join,
						'area_detail_address_zibun'		=> $addr_zibun,
						'area_detail_address_street'	=> $addr_street,
						'area_detail_address_txt'		=> $datas['addr']
					);
				}
			}

			//중복제거
			$params = array_map("unserialize", array_unique(array_map("serialize", $params)));

			$inDatas = array();
			foreach($costSeqs as $k => $seq){
				foreach($params as $j => $v){
					$v['shipping_cost_seq'] = $seq;
					$inDatas[] = $v;
				}
			}

			if(count($inDatas) > 0){
				$this->db->insert_batch('fm_shipping_area_detail', $inDatas);
				$total = $this->db->affected_rows();

				if($total <= 0){
					$num = 0;
				} else {
					$num = $total/count($costSeqs);
				}
				$shipping_cost_seq	= end($costSeqs);

				$this->db->select('area_detail_seq');
				$this->db->where('shipping_cost_seq', $shipping_cost_seq);
				$totalCount = $this->db->count_all_results("fm_shipping_area_detail");

				$return = array('num' => $num, 'total' => $totalCount, 'shipping_cost_seq' => $shipping_cost_seq, 'shipping_costs_seqs' => $costSeqs);

				/*
				 if ($this->db->trans_status() === FALSE) {
				 $this->db->trans_rollback();
				 } else {
				 debug_var($this->db);
				 exit;
				 //$this->db->trans_commit();
				 }
				 */
			}

		} else {
			if ($shipping_group_seq <= 0 || !$nation || count($costSeqs) <= 0) {
				echo 'ERROR';
				exit;
			}

			$return = array('shipping_costs_seqs' => $costSeqs);
		}

		if(count($optionsSeqs) > 0){
			$this->db->where_in('shipping_opt_seq', $optionsSeqs);
			$this->db->update('fm_shipping_option', array('delivery_limit' => 'limit'));

			$this->db->where_in('shipping_cost_seq', $costSeqs);
			$this->db->update('fm_shipping_cost', array('shipping_area_name' => $_GET['zone_name']));
		}

		foreach($_GET['shipping_opt_sec_cost'] as $k => $cost){
			$this->db->where('shipping_cost_seq', $costSeqs[$k]);
			$this->db->update('fm_shipping_cost', array('shipping_cost' => $cost));
		}

		// 이미 중복된 이력을 등록할 경우 등록된 이력이 없음
		if(empty($return)){
			$return = 'duplicate';
		}

		echo json_encode($return);
		exit;
	}

	public function shipping_otp_insert($params)
	{
		$optionSeqs = array();
		$this->load->model("shippingmodel");
		$optionSeqs = $this->shippingmodel->get_option_seqs($params);

		if(count($optionSeqs) <= 0 && $params['shipping_set_type'] == 'add'){ //add 새로 등록
			$this->db->update('fm_shipping_set', array('add_use' => 'Y'), array('shipping_group_seq' => $params['shipping_group_seq'], 'default_yn' => 'Y'));
		}

		$costSeqs = array();
		if($params['shipping_cost_seq'] <= 0){
			$datas = array();
			foreach($optionSeqs as $k => $opt){
				$datas[$k]['shipping_opt_seq']			= $opt;
				$datas[$k]['shipping_group_seq_tmp']	= $params['shipping_group_seq'];
				$datas[$k]['shipping_area_name']		= $params['zone_name'];

				$this->db->insert("fm_shipping_cost", $datas[$k]);
				$costSeqs[$k] = $this->db->insert_id();
			}
		} else {
			$costSeqs = array();
			$costSeqs = $this->shippingmodel->get_option_seqs($optionSeqs, $params);
		}

		if(count($costSeqs) > 0){
			return $costSeqs;
		} else {
			return false;
		}
	}

	public function shipping_otp_delete()
	{
		$optionSeqs = array();
		$this->load->model("shippingmodel");
		$optionSeqs = $this->shippingmodel->get_option_seqs($_GET);
		$provider_seq = $this->providerInfo['provider_seq'];

		if(count($optionSeqs) > 0){
			$costSeqs = array();
			$costSeqs = $this->shippingmodel->get_cost_seqs($optionSeqs, $_GET);

			$return = 0;

			if($_GET['delivery_limit'] == 'unlimit'){
				foreach($costSeqs as $k => $seq){
					$this->db->where_in('shipping_cost_seq', $seq);
					$this->db->delete('fm_shipping_area_detail');
					unset($seq[0]);

					if(count($seq) > 0){
						$this->db->where_in('shipping_cost_seq', $seq);
						$this->db->delete('fm_shipping_cost');
					}
				}

				$this->db->where_in('shipping_opt_seq', $optionSeqs);
				$this->db->update('fm_shipping_option', array('delivery_limit' => 'unlimit'));

				if($_GET['nation'] == 'korea'){
					$nation = '대한민국';
				} else {
					$nation = '전세계';
				}

				$this->db->where_in('shipping_opt_seq', $optionSeqs);
				$this->db->update('fm_shipping_cost', array('shipping_area_name' => $nation));
			} else if($_GET['delivery_limit'] == 'limit'){
				$costSeqs = end($costSeqs);
				echo $costSeqs[0];
			} else {
				$this->db->where_in('shipping_cost_seq', $costSeqs);
				$this->db->delete('fm_shipping_cost');

				$this->db->where_in('shipping_cost_seq', $costSeqs);
				$this->db->delete('fm_shipping_area_detail');
			}
		} else {
			if($_GET['delivery_limit'] == 'limit'){
				$datas = array();
				$datas['shipping_group_seq']	= $_GET['shipping_group_seq'];
				$datas['shipping_set_seq']		= $_GET['shipping_set_seq'];
				$datas['shipping_set_code']		= 'delivery';
				$datas['shipping_set_name']		= '택배';
				$datas['shipping_set_type']		= 'std';
				$datas['shipping_opt_type']		= 'free';
				$datas['shipping_provider_seq']	= $provider_seq;
				$datas['delivery_limit']		= 'limit';
				$datas['default_yn']			= 'Y';
				$datas['section_st']			= 0;
				$datas['section_ed']			= 0;

				$this->db->insert("fm_shipping_option", $datas);
				$optSeq = $this->db->insert_id();

				if($_GET['nation'] == 'global'){
					$areaName = '국가1';
				} else {
					$areaName = '지역1';
				}

				$datas = array();
				$datas['shipping_opt_seq']		= $optSeq;
				$datas['shipping_group_seq_tmp']= $_GET['shipping_group_seq'];
				$datas['shipping_area_name']	= $areaName;
				$datas['shipping_cost']			= 0;

				$this->db->insert("fm_shipping_cost", $datas);
				$costSeqs =  $this->db->insert_id();
				echo $costSeqs;
			}
		}

		exit;
	}

	public function set_section_addr()
	{
		$optionSeqs = array();
		$this->load->model("shippingmodel");
		$optionSeqs			= $this->shippingmodel->get_option_seqs($_GET);

		$optionSeqLast = end($optionSeqs);

		//1. 추가 행 입력
		$this->db->where('shipping_opt_seq', $optionSeqLast);
		$this->db->update('fm_shipping_option',
			array('section_st' => $_GET['section_st'][0], 'section_ed' => $_GET['section_ed'][0]));

		//2. 마지막 행 입력
		$this->db->select('*');
		$this->db->where('shipping_opt_seq', $optionSeqLast);
		$optionData = $this->db->get('fm_shipping_option');
		$optionData = $optionData->result_array();
		$optionData = $optionData[0];
		unset($optionData['shipping_opt_seq'], $optionData['section_st'], $optionData['section_ed']);

		$optionData['section_st'] = $_GET['section_st'][1];
		$optionData['section_ed'] = 0;

		$this->db->insert("fm_shipping_option", $optionData);
		$optionSeqNew = $this->db->insert_id();

		//3. cost 입력 및 업데이트
		$this->db->select('*');
		$this->db->where('shipping_opt_seq', $optionSeqLast);
		$costData = $this->db->get('fm_shipping_cost');
		$costData = $costData->result_array(); // insert 대기 데이터

		$insertDatas	= array();
		$costSeqs		= array();
		$costSeqsNew	= array();
		foreach($costData as $v){
			$costSeqs[] = $v['shipping_cost_seq'];
			unset($v['shipping_cost_seq']);

			$v['shipping_opt_seq'] = $optionSeqNew;

			$this->db->insert("fm_shipping_cost", $v);
			$costSeqsNew[] = $this->db->insert_id();
		}

		$this->db->where('shipping_opt_seq', $optionSeqLast);
		$this->db->update('fm_shipping_cost', array('shipping_cost' => 0));

		$returns = array();
		foreach($costSeqs as $k => $seq){
			$this->db->select('*');
			$this->db->where('shipping_cost_seq', $seq);
			$res = $this->db->get('fm_shipping_area_detail');
			$res = $res->result_array();

			$datas = array();
			foreach($res as $kk => $data){
				unset($data['area_detail_seq']);
				$datas[$kk]							= $data;
				$datas[$kk]['shipping_cost_seq']	= $costSeqsNew[$k];
				$returns[] = $costSeqsNew[$k];
			}

			if(count($datas) > 0){
				$this->db->insert_batch("fm_shipping_area_detail", $datas);
			}
		}

		$return = array();
		$_GET['idx'] = 'limit';
		$_GET['delivery_limit'] = 'limit';
		$return['options']	= $this->shippingmodel->get_option_seqs($_GET);
		$return['costs']	= $this->shippingmodel->get_cost_seqs($return['options'], $_GET);

		echo json_encode($return);
		exit;
	}

	public function shipping_sec_delete()
	{
		$optionSeqs = array();
		$this->load->model("shippingmodel");
		$optionSeqs			= $this->shippingmodel->get_option_seqs($_GET);
		$optionSeqDel		= $optionSeqs[$_GET['idx']];
		$_GET['is_delete']	= 'Y';
		unset($optionSeqs[$_GET['idx']]);

		$optionSeqs			= array_values($optionSeqs);

		//$this->db->trans_begin();

		if($optionSeqDel > 0){
			$costSeqs = array();
			$this->db->select('shipping_cost_seq');
			$this->db->where('shipping_opt_seq', $optionSeqDel);
			$costs = $this->db->get("fm_shipping_cost");
			foreach($costs->result_array() as $v){
				$costSeqs[] = $v['shipping_cost_seq'];
			}

			if(count($costSeqs) > 0){
				$this->db->where_in('shipping_cost_seq', $costSeqs);
				$this->db->delete('fm_shipping_cost');

				$this->db->where_in('shipping_cost_seq', $costSeqs);
				$this->db->delete('fm_shipping_area_detail');
			}
		}

		$upDatas = array();
		foreach($optionSeqs as $k => $seq){
			$this->db->where('shipping_opt_seq', $seq);
			$this->db->update('fm_shipping_option',
				array('section_st' => $_GET['section_st'][$k], 'section_ed' => $_GET['section_ed'][$k]));
		}

		$this->db->where('shipping_opt_seq', $optionSeqDel);
		$this->db->delete('fm_shipping_option');

		/*
		 if ($this->db->trans_status() === FALSE) {
		 $this->db->trans_rollback();
		 } else {
		 //debug_var($this->db);
		 //$this->db->trans_commit();
		 }
		 */

		$return = array();

		$this->db->select('shipping_cost_seq');
		$this->db->where_in('shipping_opt_seq', $optionSeqs);
		$this->db->order_by('shipping_opt_seq ASC', 'shipping_cost_seq ASC');
		$costs = $this->db->get("fm_shipping_cost");
		foreach($costs->result_array() as $v){
			$return[] = $v['shipping_cost_seq'];
		}

		echo json_encode($return);

		exit;
	}

	public function shipping_otp_modify()
	{
		//$this->db->trans_begin();

		if($_GET['shipping_opt_type'] == 'fixed' || $_GET['shipping_opt_type'] == 'free'){
			$_GET['shipping_opt_sec_cost'] = array_slice($_GET['shipping_opt_sec_cost'], 0, ($_GET['areaLength']*-1), true);
		}

		$costs = array();
		$areaLength = $_GET['areaLength'];
		foreach($_GET['shipping_opt_sec_cost'] as $k => $cost){
			$idx = ($k%$areaLength);
			$costs[$idx][] = $cost;
		}

		$_GET['idx'] = 'unlimit';
		if($_GET['shipping_opt_type'] == 'fixed' || $_GET['shipping_opt_type'] == 'free'){
			$sec_st = array(0);
			$sec_ed = array(0);

			if($_GET['shipping_opt_type'] == 'free'){
				$costs[0][0] = 0;
			}
		} else {
			$sec_st = $_GET['section_st'];
			$sec_ed = $_GET['section_ed'];
		}

		$optionSeqs = array();
		$this->load->model("shippingmodel");
		$optionSeqs	= $this->shippingmodel->get_option_seqs($_GET);

		$provider_seq = $this->providerInfo['provider_seq'];
		if(count($optionSeqs) <= 0){
			$data = array(
				'shipping_group_seq' 	=> $_GET['shipping_group_seq'],
				'shipping_set_seq'		=> $_GET['shipping_set_seq'],
				'shipping_set_code' 	=> 'delivery',
				'shipping_set_name' 	=> '택배',
				'shipping_set_type' 	=> $_GET['shipping_set_type'],
				'shipping_opt_type' 	=> $_GET['shipping_opt_type'],
				'shipping_provider_seq' => $provider_seq,
				'delivery_limit' 		=> 'unlimit',
				'default_yn' 			=> 'Y'
			);

			$this->db->insert("fm_shipping_option", $data);
			$optionSeqs[] = $this->db->insert_id();
		}

		$costSeqs	= $this->shippingmodel->get_cost_seqs($optionSeqs, $_GET);
		if(count($costSeqs) <= 0){
			if($_GET['delivery_nation'] == 'global'){
				$areaName = '국가1';
			} else {
				$areaName = '지역1';
			}

			$data = array(
				'shipping_group_seq_tmp'=> $_GET['shipping_group_seq'],
				'shipping_opt_seq'		=> $optionSeqs[0],
				'shipping_area_name' 	=> $areaName,
				'shipping_cost' 		=> 0
			);

			$this->db->insert("fm_shipping_cost", $data);
			$costSeqs[][] = $this->db->insert_id();
		}

		if($_GET['shipping_opt_type'] == 'fixed' || $_GET['shipping_opt_type'] == 'free'){
			$delOtps	= array_slice($optionSeqs, 0, -1);
			$leaveOtps	= array_slice($optionSeqs, -1);

			$delCosts	= array_slice($costSeqs, 0, -1);
			$leaveCosts	= array_slice($costSeqs, -1);
		} else {
			$delOtps	= array_slice($optionSeqs, 0, -2);
			$leaveOtps	= array_slice($optionSeqs, -2);

			$delCosts	= array_slice($costSeqs, 0, -2);
			$leaveCosts	= array_slice($costSeqs, -2);
		}

		if($_GET['shipping_opt_type'] != 'fixed' && $_GET['shipping_opt_type'] != 'free'){
			if(count($leaveOtps) < 2){
				$this->db->select('*');
				$this->db->where('shipping_opt_seq', $leaveOtps[0]);
				$otpDatas = $this->db->get("fm_shipping_option");
				$otpDatas = $otpDatas->result_array();
				$otpDatas = $otpDatas[0];

				unset($otpDatas['shipping_opt_seq']);
				$this->db->insert("fm_shipping_option", $otpDatas);
				$leaveOtps[] = $this->db->insert_id();
			}

			if(count($leaveCosts) < 2){
				foreach($leaveCosts[0] as $otp){
					$this->db->select('*');
					$this->db->where('shipping_cost_seq', $otp);
					$costDatas = $this->db->get("fm_shipping_cost");
					$costDatas = $costDatas->result_array();
					$costDatas = $costDatas[0];
					unset($costDatas['shipping_cost_seq']);

					$costDatas['shipping_opt_seq'] = end($leaveOtps);
					$this->db->insert("fm_shipping_cost", $costDatas);
					$leaveCosts[1][] = $this->db->insert_id();
				}
			}
		}

		foreach($leaveOtps as $k => $seq){
			$this->db->where('shipping_opt_seq', $seq);
			$this->db->update('fm_shipping_option', array('shipping_opt_type' => $_GET['shipping_opt_type'], 'section_st' => $sec_st[$k], 'section_ed' => $sec_ed[$k]));
		}

		$areaDatas = array();
		$areaCosts = array();
		foreach($leaveCosts as $k => $seqs){
			foreach($seqs as $j => $seq){
				$this->db->where('shipping_cost_seq', $seq);
				$this->db->update('fm_shipping_cost', array('shipping_cost' => $costs[$j][$k]));

				$this->db->select('shipping_group_seq_tmp, area_nation_type, area_detail_address_join, area_detail_address_txt, area_detail_address_zibun, area_detail_address_street');
				$this->db->where('shipping_cost_seq', $seq);
				$datas = $this->db->get("fm_shipping_area_detail");
				$datas = $datas->result_array();
				if($datas){
					$areaDatas[0] = $datas;
				} else {
					$areaCosts[] = $seq;
				}
			}
		}

		if(count($areaDatas[0]) > 0){
			$areainDatas = array();

			foreach($areaCosts as $k => $seqs){
				foreach($areaDatas[0] as $data){
					$data['shipping_cost_seq'] = $seqs;
					$areainDatas[] = $data;
				}
			}

			if(count($areaCosts) > 0){
				$this->db->insert_batch('fm_shipping_area_detail', $areainDatas);
			}
		}

		if(count($delOtps) > 0){
			$this->db->where_in('shipping_opt_seq', $delOtps);
			$this->db->delete('fm_shipping_option');
		}

		foreach($delCosts as $seqs){
			$this->db->where_in('shipping_cost_seq', $seqs);
			$this->db->delete('fm_shipping_cost');

			$this->db->where_in('shipping_cost_seq', $seqs);
			$this->db->delete('fm_shipping_area_detail');
		}

		if($_GET['shipping_set_type'] == 'add'){
			$this->db->where('shipping_set_seq', $_GET['shipping_set_seq']);
			$this->db->update('fm_shipping_set', array('add_use' => 'Y'));
		} else if($_GET['shipping_set_type'] == 'hop'){
			$this->db->where('shipping_set_seq', $_GET['shipping_set_seq']);
			$this->db->update('fm_shipping_set', array('hop_use' => 'Y'));
		}


		/*
		 if ($this->db->trans_status() === FALSE) {
		 $this->db->trans_rollback();
		 } else {
		 debug_var($this->db);
		 exit;
		 //$this->db->trans_commit();
		 }
		 */

		/*
		 if($_GET['shipping_opt_type'] == 'fixed'){
		 echo json_encode($leaveCosts[0][0]);
		 } else {
		 echo json_encode($areaCosts);
		 }
		 */

		$return = array();
		$return['options'] = $leaveOtps;
		$return['costs'] = $leaveCosts;

		echo json_encode($return);

		exit;
	}

	public function shipping_add_modify()
	{
		//$this->db->trans_begin();
		$useType		= $_GET['shipping_set_type']."_use";
		$useVal			= $_GET['useVal'];
		$_GET['idx']	= 'unlimit';
		$_GET['delivery_limit']	= 'unlimit';

		$this->db->where('shipping_set_seq', $_GET['shipping_set_seq']);
		$this->db->update('fm_shipping_set', array($useType => $useVal));

		if($useVal == 'N'){
			$this->load->model("shippingmodel");
			$optionSeqs	= $this->shippingmodel->get_option_seqs($_GET);

			if(count($optionSeqs) > 0){
				$costSeqs = $this->shippingmodel->get_cost_seqs($optionSeqs, $_GET);

				$this->db->where_in('shipping_opt_seq', $optionSeqs);
				$this->db->delete('fm_shipping_option');

				$this->db->where_in('shipping_opt_seq', $optionSeqs);
				$this->db->delete('fm_shipping_cost');
			}

			if(count($costSeqs) > 0){
				foreach($costSeqs as $opt => $seq){
					$this->db->where_in('shipping_cost_seq', $seq);
					$this->db->delete('fm_shipping_area_detail');
				}
			}
		}

		/*
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
		} else {
			debug_var($this->db);
			exit;
		}
		*/

		exit;
	}

	public function watermark_setting(){
		serviceLimit('H_FR','process');

		$config_watermark = config_load('watermark');

		if($config_watermark['watermark_position']!=''){
			$config_watermark['watermark_position'] = explode('|',$config_watermark['watermark_position']);
		}

		$file_path	= $this->template_path();
		$this->template->assign(array('config_watermark'=>$config_watermark));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign('sc',$this->input->get());
		$this->template->print_("tpl");
    }

    public function manager_log(){
		if($this->providerInfo['manager_yn'] != 'Y'){
			pageBack("권한이 없습니다.");
			exit;
		}

		if(!$this->providerInfo['provider_seq']){
			pageBack("접근 경로 에러.");
			exit;
		}

		$provider_seq = $this->providerInfo['provider_seq'];

		$this->load->model('authmodel');
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$this->load->library('managerlog');

		//메뉴 데이터
		//입점사 미노출 메뉴 정리
        $action_type = array();
        foreach($this->managerlog->action_type as $k => $v){
            if(in_array($k, $this->managerlog->action_type_scm) !== true){
                $action_type[$k] = $v;
            }
        }

		//입점사 미노출 메뉴 정리
        $action_menu = array();
        foreach($this->managerlog->action_menu as $k => $v){
            foreach($v as $kk => $vv){
                if(in_array($kk, $this->managerlog->action_menu_scm[$k]) !== true){
                    $action_menu[$k][$kk] = $vv;
                }
            }
        }
		unset($action_menu['member']['excel_spout'], $action_menu['order']['excel_spout']);

		$action_menu_new = array();
		foreach($action_menu as $k => $v){
			foreach($v as $kk => $vv){
				if(substr($kk, -7) == '_prints'){
					$vv .= " > 인쇄";
				} else if(strpos($kk, 'excel_download') !== false){
					$vv .= " > 엑셀 다운로드";
				}

				$action_menu_new[$k][$kk] = $vv;
			}
		}

		$this->template->assign('action_type', $action_type);
		$this->template->assign('action_menu_json', json_encode($action_menu_new));

        if($this->input->get('is_excel') == 'Y'){
			if($this->input->get('list_total') > 10000){
				pageBack("10000개 이상의 로그는 다운로드가 불가능 합니다.");
				exit;
			}
			parse_str($this->input->get('params'), $params);
			$params['is_excel'] = 'Y';
		} else {
			$params = $this->input->get();
		}

		//페이징
		if(empty($params['perpage'])){
			$perpage = 10;
		} else {
			$perpage = $params['perpage'];
		}
		$this->template->assign('perpage', $perpage);

		if(empty($params['page'])){
			$page = 0;
		} else {
			$page = $params['page'];
		}

        $where_and = array('provider_seq' => $provider_seq, 'super_manager_yn !=' => 'Y');

		$managers = $this->manager_list($provider_seq, 'Y');
		$this->template->assign('managers', $managers);

        if($params['regist_date'][0] && $params['regist_date'][1]){
			$where_and['regist_date >='] = $params['regist_date'][0]." 00:00:00";
			$where_and['regist_date <='] = $params['regist_date'][1]." 23:59:59";
		} else if($params['regist_date'][0] && !$params['regist_date'][1]){
			$where_and['regist_date >='] = $params['regist_date'][0]." 00:00:00";
		} else if(!$params['regist_date'][0] && $params['regist_date'][1]){
			$where_and['regist_date <='] = $params['regist_date'][1]." 23:59:59";
		} else {
			$where_and['regist_date >='] = date('Y-m-d')." 00:00:00";
			$where_and['regist_date <='] = date('Y-m-d')." 23:59:59";

			$this->template->assign('sdate', date('Y-m-d'));
			$this->template->assign('edate', date('Y-m-d'));
		}

		if($params['manager_seq'] > 0){
			$where_and['manager_seq'] = $params['manager_seq'];
        }

        if($params['action_type'] && $action_type[$params['action_type'] ]){
			$where_and['action_type'] = $params['action_type'];
			$this->template->assign('action_menu', $action_menu_new[$params['action_type']]);
        }

        if($params['action_type'] && $params['action_menu'] && $action_menu[$params['action_type']]){
			$where_and['action_menu'] = $params['action_menu'];
        }

		//엑셀 다운로드
		if ($params['is_excel'] == 'Y') {
            $query = $this->db
                ->select("*")
                ->from('fm_manager_log')
                ->where($where_and)
                //->limit($perpage, $page)
                ->get();

			$writer = WriterFactory::create(Type::XLSX); // for XLSX files
			$fileName = date('YmdHis')."_administrator_work_history.xlsx";
			$filePath = ROOTPATH . "excel_download/".$fileName;

			$columns = array('No.', '구분', '메뉴', '수행업무', '접속자', '접속일시', '접속 IP');

			$writer->openToFile($filePath);
			$writer->addRow($columns);

			$no = 1;
			foreach($query->result_array() as $k => $v){
				$datas = array();
				$datas['no']			= $no++;
				$datas['type']			= $action_type[$v['action_type']];
				$datas['menu']			= $action_menu[$v['action_type']][$v['action_menu']];
				$datas['desc']			= $v['action_desc'];
				$datas['manager_name']	= $v['manager_id']." (".$v['manager_name'].")";
				$datas['regist_date']	= $v['regist_date'];
				$datas['access_ip']		= $v['access_ip'];

				$writer->addRow($datas);
			}
			$writer->close();

			echo $fileName;
			exit;
		}

        //전체 갯수
		$list_total = $this->db
            ->select("*")
            ->from('fm_manager_log')
            ->where($where_and)
            ->get()
            ->num_rows();
        $this->template->assign('list_total', $list_total);

		//목록
		$query = $this->db
			->select("*")
			->from('fm_manager_log')
			->where($where_and)
            ->limit($perpage, $page)
            ->order_by('manager_log_seq', 'DESC')
			->get();

        //정보 매칭
        $no = $list_total - ( ($page/$perpage) * $perpage );
        foreach($query->result_array() as $k => $v){
            $list[$k]['no']				= $no;
            $list[$k]['type']			= $action_type[$v['action_type']];
            $list[$k]['menu']			= $action_menu[$v['action_type']][$v['action_menu']];
            $list[$k]['desc']			= $v['action_desc'];

			if( $v['action_menu'] == 'manager_modify' || ($v['action_type'] == 'member' && $v['action_desc'] == '검색')
				|| ($v['action_type'] == 'order' && $v['action_desc'] == '검색')
				|| ($v['action_type'] == 'goods' && $v['action_desc'] == '검색')
				|| ($v['action_type'] == 'board' && strpos($v['action_desc'], '검색') !== false) ){
				$list[$k]['detail_seq'] = $v['manager_log_seq'];
			}

            if($v['action_menu'] == 'manager_reg' || $v['action_menu'] == 'manager_modify'){
                $list[$k]['detail_seq'] = $v['manager_log_seq'];
            }

			 if($v['action_menu'] == 'manager_reg' || $v['action_menu'] == 'manager_modify' || $v['action_menu'] == 'manager_delete'){
				$list[$k]['desc']		= str_replace("입점사 ", "", $v['action_desc']);
            }

            if ($v['provider_seq'] != 1) {
                if($v['super_manager_yn'] == 'Y'){
                    $list[$k]['provider'] = '본사';
                } else {
                    $list[$k]['provider'] = $v['provider_name']." (".$v['provider_id'].")";
                }
            } else {
                $list[$k]['provider'] = '본사';
            }

            $list[$k]['manager_name']	= $v['manager_id']." (".$v['manager_name'].")";
            $list[$k]['regist_date']	= $v['regist_date'];
            $list[$k]['access_ip']		= $v['access_ip'];

            $no--;
        }

        $this->template->assign('loop', $list);
        $paginlay =  pagingtag( $list_total, $perpage, "/selleradmin/setting/manager_log?" );
        if( empty($paginlay) ){
            $paginlay = '<p><a class="on red">1</a><p>';
        }
        $this->template->assign('pagin',$paginlay);
        $this->template->define('manager_log_search', $this->skin.'/setting/manager_log_search.html');
        $this->template->define('manager_log_list', $this->skin.'/setting/manager_log_list.html');
        $this->template->define(array('tpl'=>$filePath));
        $this->template->print_("tpl");
	}

	public function manager_log_detail()
	{
		if($this->providerInfo['manager_yn'] != 'Y'){
			echo "ERROR_AUTH";
			exit;
		}

		$seq = $this->input->get('seq');
		if(!$seq || $seq <= 0){
			echo "ERROR_SEQ";
			exit;
		}

		$provider_seq = $this->providerInfo['provider_seq'];
		$this->load->library('managerlog');

		$list = $this->db
			->select("action_menu, action_type, action_target, action_status, action_before, action_desc, action_menu_url")
			->from('fm_manager_log')
			->where('provider_seq', $provider_seq)
			->where('manager_log_seq', $seq)
			->get()
			->row_array();

		$action_target = explode("|", substr($list['action_target'], 0, -1));
		$action_status = explode("|", substr($list['action_status'], 0, -1));
		$action_before = explode("|", substr($list['action_before'], 0, -1));

		$parts = parse_url($list['action_menu_url']);
		parse_str($parts['query'], $params);

		$data = array();
		foreach($action_target as $k => $v){
			if($list['action_type'] == 'order' && $list['action_desc'] == '검색'){
				$data['desc'] = $this->manager_log_detail_order($list['action_menu'], $params);
			} else if($list['action_type'] == 'goods' && $list['action_desc'] == '검색'){
				$data['desc'] = $this->manager_log_detail_goods($list['action_menu'], $params);
			} else if($list['action_type'] == 'board' && strpos($list['action_desc'], '검색') !== false){
				$data['desc'] = $this->manager_log_detail_board($list['action_menu'], $params);
			} else {
				$data['type'] = 'list';
				foreach($this->managerlog->fm_code as $menu => $code){
					if($code[$v]){
						$data['data'][$k]['menu']	= $this->managerlog->fm_code_menu[$menu];
						$data['data'][$k]['action'] = $this->managerlog->fm_code[$menu][$v];
						$data['data'][$k]['status'] = ($action_status[$k] == 'Y') ? '권한 있음' : '권한 없음';
						$data['data'][$k]['before'] = ($action_before[$k] == 'Y') ? '권한 있음' : '권한 없음';
					}
				}
			}
		}

		echo json_encode($data);
		exit;
	}

	public function manager_log_detail_board($menu, $params)
	{
		$desc = array();

		if(strlen($params['search_text']) > 0){
			$key_txt = $params['search_text'];
		} else {
			$key_txt = '-';
		}

		if(strlen($params['order_seq']) > 0){
			$order_txt = $params['order_seq'];
		} else {
			$order_txt = '-';
		}

		if($params['ordered_review'] == 'y'){
			$ordered_review = '구매 상품';
		} else if($params['ordered_review'] == 'n'){
			$ordered_review = '미구매 상품';
		} else {
			$ordered_review = '전체';
		}

		if($params['member_review'] == 'y'){
			$member_review = '회원';
		} else if($params['member_review'] == 'n'){
			$member_review = '비회원';
		} else {
			$member_review = '전체';
		}

		if($params['review_type'] == 'best'){
			$review_type = '베스트 후기';
		} else if($params['review_type'] == 'npay'){
			$review_type = '네이버페이 후기';
		} else {
			$review_type = '전체';
		}

		if($params['searchreply'] == 'y'){
			$status_type = '답변 대기';
		} else if($params['searchreply'] == 'n'){
			$status_type = '답변 완료';
		} else {
			$status_type = '전체';
		}

		if(strlen($params['category']) > 0){
			$cate_txt = $params['category'];
		} else {
			$cate_txt = '전체';
		}

		$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
		$desc .= "<tr><td>등록일</td><td>".$params['rdate_s']." ~ ".$params['rdate_f']."</td></tr>";
		if(array_key_exists('order_seq', $params)){
			$desc .= "<tr><td>주문번호</td><td>".$order_txt."</td></tr>";
			$desc .= "<tr><td>평점</td><td>".$params['score']."</td></tr>";
			$desc .= "<tr><td>구매 여부</td><td>".$ordered_review."</td></tr>";
			$desc .= "<tr><td>회원 여부</td><td>".$member_review."</td></tr>";
			$desc .= "<tr><td>기타 후기</td><td>".$review_type."</td></tr>";
		} else {
			if(array_key_exists('category', $params)){
				$desc .= "<tr><td>분류</td><td>".$cate_txt."</td></tr>";
			}

			if(array_key_exists('searchreply', $params)){
				$desc .= "<tr><td>답변상태</td><td>".$status_type."</td></tr>";
			}
		}

		return $desc;
	}

	public function manager_log_detail_goods($menu, $params)
	{
		$desc = array();

		if(strlen($params['keyword']) > 0){
			$key_txt = $params['keyword'];
		} else {
			$key_txt = '-';
		}

		if($params['notifyStatus'] == 'none'){
			$alert_txt = '미통보';
		} else if($params['notifyStatus'] == 'complete'){
			$alert_txt = '통보';
		} else {
			$alert_txt = '전체';
		}

		if($params['provider_seq_selector'] == 'all'){
			$provider = '전체';
		} else {
			$this->load->model('providermodel');
			$provider_info = $this->providermodel->get_provider($params['provider_seq']);
			$provider = $provider_info['provider_name'];
		}

		$this->load->model('categorymodel');
		$cate	= array();
		$cate[] = $this->categorymodel->get_category_name($params['category1']);
		$cate[] = $this->categorymodel->get_category_name($params['category2']);
		$cate[] = $this->categorymodel->get_category_name($params['category3']);
		$cate[] = $this->categorymodel->get_category_name($params['category4']);
		$cate = array_filter($cate);

		if( count($cate) <= 0 ){
			$cate_txt = '-';
		} else {
			$cate_txt = array_pop($cate);
		}

		$cate2 = array();
		if($params['search_link_category']){
			$cate2[] = '대표카테고리 기준';
		}
		if($params['not_regist_category']){
			$cate2[] = '카테고리 미등록';
		}
		if( count($cate2) > 0 ){
			$cate_txt2 = " (".implode('/', $cate2).")";
		} else {
			$cate_txt2 = "";
		}

		if($params['goodsStatus'] == 'normal') {
			$status_txt = "정상";
		} else if($params['goodsStatus'] == 'runout') {
			$status_txt = "품절";
		} else if($params['goodsStatus'] == 'purchasing') {
			$status_txt = "재고확보중";
		} else if($params['goodsStatus'] == 'unsold') {
			$status_txt = "판매중지";
		} else {
			$status_txt = "전체";
		}

		if($params['goodsView'] == 'look') {
			$view_txt = "노출";
		} else if($params['goodsView'] == 'notLook') {
			$view_txt = "미노출";
		} else {
			$view_txt = "전체";
		}

		if($params['goodsView'] == 'look') {
			$view_txt = "노출";
		} else if($params['goodsView'] == 'notLook') {
			$view_txt = "미노출";
		} else {
			$view_txt = "전체";
		}

		if($params['taxView'] == 'tax') {
			$tax_txt = "과세";
		} else if($params['taxView'] == 'exempt') {
			$tax_txt = "비과세";
		} else {
			$tax_txt = "전체";
		}

		if($params['price_gb'] == 'price') {
			$price_txt = "판매가";
		} else if($params['taxView'] == 'consumer_price') {
			$price_txt = "정가";
		}

		$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
		$desc .= "<tr><td>신청일</td><td>".$params['sdate']." ~ ".$params['edate']."</td></tr>";
		$desc .= "<tr><td>재입고 알림 통보</td><td>".$alert_txt."</td></tr>";
		$desc .= "<tr><td>입점사</td><td>".$provider."</td></tr>";
		$desc .= "<tr><td>카테고리</td><td>".$cate_txt.$cate_txt2."</td></tr>";
		$desc .= "<tr><td>판매 상태</td><td>".$status_txt."</td></tr>";
		$desc .= "<tr><td>노출 여부</td><td>".$view_txt."</td></tr>";
		$desc .= "<tr><td>과세</td><td>".$tax_txt."</td></tr>";
		$desc .= "<tr><td>".$price_txt."</td><td>".$params['sprice']." ~ ".$params['eprice']."</td></tr>";
		$desc .= "<tr><td>재고</td><td>".$params['sstock']." ~ ".$params['estock']."</td></tr>";

		return $desc;
	}

	public function manager_log_detail_order($menu, $params)
	{
		$desc = array();
		switch($menu){
			case "selleradmin_company_catalog":
				if(strlen($params['keyword']) > 0){
					$key_txt = $params['keyword'];
				} else {
					$key_txt = '-';
				}

				$status_config	= config_load('step');
				$status_txt = array();
				foreach($params['chk_step'] as $k => $v){
					$status_txt[$k] = $status_config[$k];
				}
				if(count($status_txt) <= 0){
					$status_txt[] = '-';
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>날짜</td><td>".$params['regist_date'][0]." ~ ".$params['regist_date'][1]."</td></tr>";
				$desc .= "<tr><td>상태</td><td>".implode(', ', $status_txt)."</td></tr>";
			break;

			case "returns_catalog":
			case "refund_catalog":
				if($menu == 'returns_catalog'){
					$status = $params['return_status'];
					$menu_txt = '반품';
				} else if($menu == 'refund_catalog'){
					$status = $params['refund_status'];
					$menu_txt = '환불';
				}

				if(strlen($params['keyword']) > 0){
					$key_txt = $params['keyword'];
				} else {
					$key_txt = '-';
				}

				if($params['date_field'] == 'ref.regist_date'){
					$date_txt = $menu_txt.'신청일';
				} else if($params['date_field'] == 'ref.refund_date' || $params['date_field'] == 'ref.return_date'){
					$date_txt = $menu_txt.'완료일';
				} else {
					$date_txt = $menu_txt.'신청일';
				}

				$status_txt = array();
				foreach($status as $k => $v){
					if($v == 'request'){
						$status_txt[$k] =  $menu_txt.'신청';
					} else if($v == 'ing'){
						$status_txt[$k] =  $menu_txt.'처리중';
					} else if($v == 'complete'){
						$status_txt[$k] =  $menu_txt.'완료';
					}
				}

				if($params['search_npay_order_return']){
					$npay = '조회';
				} else {
					$npay = '-';
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";

				if($menu == 'returns_catalog'){
					if(strlen($params['provider_name']) > 0){
						$provider = $params['provider_name'];
					} else {
						$provider = '전체';
					}
					$desc .= "<tr><td>배송책임</td><td>".$provider."</td></tr>";
				}

				$desc .= "<tr><td>".$date_txt."</td><td>".$params['sdate']." ~ ".$params['edate']."</td></tr>";
				$desc .= "<tr><td>상태</td><td>".implode(', ', $status_txt)."</td></tr>";
				$desc .= "<tr><td>Npay 요청건</td><td>".$npay."</td></tr>";

				if($menu == 'returns_catalog'){
					$this->load->model('connectormodel');
					$connector	= $this->connector::getInstance();
					$marketList	= $connector->getAllMarkets(true);
					$marketList['NOT']['name'] = '내 쇼핑몰';
					$market_txt = array();
					foreach($params['selectMarkets'] as $k => $v){
						$market_txt[] = $marketList[$v]['name'];
					}
					if(count($market_txt) <= 0){
						$market_txt[] = '-';
					}

					if($params['return_method'] == 'user'){
						$return_method_txt = '자가반품';
					} else if($params['return_method'] == 'shop'){
						$return_method_txt = '택배회수';
					} else {
						$return_method_txt = '-';
					}

					$desc .= "<tr><td>오픈마켓</td><td>".implode(', ', $market_txt)."</td></tr>";
					$desc .= "<tr><td>회수방법</td><td>".$return_method_txt."</td></tr>";
				}

			break;

			case "export_batch_status":
			case "order_export_popup":
				if(strlen($params['keyword']) > 0){
					$key_txt = $params['keyword'];
				} else {
					$key_txt = '-';
				}

				if($params['provider_seq'] > 0){
					$this->load->model('providermodel');
					$provider = $this->providermodel->provider_goods_list_sort();
					$providerInfo = array();
					foreach($provider as $k => $v){
						$providerInfo[$v['provider_seq']] = $v['provider_name'];
					}
					$provider = $providerInfo[$params['provider_seq']];
				} else {
					$provider = '전체';
				}

				if($params['date_field'] == 'order'){
					$date_txt = '주문일';
				} else if($params['date_field'] == 'export'){
					$date_txt = '출고일(입력)';
				} else if($params['date_field'] == 'regist_date'){
					$date_txt = '출고일';
				} else if($params['date_field'] == 'shipping'){
					$date_txt = '배송완료일';
				} else {
					$date_txt = '구매확정일';
				}

				$status_config	= config_load('export_status');

				$this->load->model('shippingmodel');
				$ship_set_code = $this->shippingmodel->ship_set_code;
				$ship_set_code['coupon'] = '문자/이메일 주문';
				$shipping_txt = array();
				foreach($params['shipping_method'] as $v){
					$shipping_txt[] = $ship_set_code[$v];
				}
				if(count($shipping_txt) <= 0){
					$shipping_txt[] = '-';
				}

				if($params['search_npay_order'] == 'y'){
					$npay_txt = 'O';
				} else {
					$npay_txt = '-';
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>배송책임</td><td>".$provider."</td></tr>";
				$desc .= "<tr><td>".$date_txt."</td><td>".$params['start_search_date']." ~ ".$params['end_search_date']."</td></tr>";

				if($menu == 'export_batch_status'){
					$desc .= "<tr><td>상태</td><td>".$status_config[$params['status']]."</td></tr>";
				} else {
					$status_config	= config_load('step');
					$status_txt = array();
					foreach($params['step'] as $k => $v){
						$status_txt[$k] = $status_config[$k];
					}
					if(count($status_txt) <= 0){
						$status_txt[] = '전체';
					}
					$desc .= "<tr><td>상태</td><td>".implode(', ', $status_txt)."</td></tr>";
				}

				$desc .= "<tr><td>배송방법</td><td>".implode(', ', $shipping_txt)."</td></tr>";
				$desc .= "<tr><td>네이버페이 주문</td><td>".$npay_txt."</td></tr>";

				if($menu == 'export_batch_status'){
					if($params['search_market_fail'] == 'y'){
						$market_fail = 'O';
					} else {
						$market_fail = '-';
					}

					if(strlen($params['src_shipping_delivery']) > 0){
						$delivery_company_array = config_load('delivery_url');
						$delivery_txt = $delivery_company_array[$params['src_shipping_delivery']]['company'];
					} else {
						$delivery_txt = '전체';
					}

					if($params['none_search_delivery_number']){
						$delivery_txt .= " (운송장번호 없음)";
					} else if(strlen($params['search_delivery_number']) > 0) {
						$delivery_txt .= " (".$params['search_delivery_number'].")";
					}

					$desc .= "<tr><td>송장전송 실패</td><td>".$market_fail."</td></tr>";
					$desc .= "<tr><td>택배정보</td><td>".$delivery_txt."</td></tr>";
				}

			break;

			case "export_catalog":
				if(strlen($params['keyword']) > 0){
					$key_txt = $params['keyword'];
				} else {
					$key_txt = '-';
				}

				if(strlen($params['provider_name']) > 0){
					$provider = $params['provider_name'];
				} else {
					$provider = '전체';
				}

				if($params['date'] == 'order'){
					$date_txt = '주문일';
				} else if($params['date'] == 'export'){
					$date_txt = '출고일(입력)';
				} else if($params['date'] == 'regist_date'){
					$date_txt = '출고일';
				} else if($params['date'] == 'shipping'){
					$date_txt = '배송완료일';
				} else {
					$date_txt = '구매확정일';
				}

				$status_config	= config_load('export_status');
				$status_txt = array();
				foreach($params['export_status'] as $k => $v){
					$status_txt[$k] =  $status_config[$k];
				}
				if(count($status_txt) <= 0){
					$status_txt[] = '전체';
				}

				$confirm_txt = array();
				foreach($params['buy_confirm'] as $k => $v){
					if($k == 'ok'){
						$confirm_txt[] = '구매확정 완료 (출고상태 : 배송완료)';
					}else if($k == 'standby'){
						$confirm_txt[] = '구매확정 대기 (출고상태 : 출고완료, 배송중, 배송완료)';
					}
				}
				if(count($confirm_txt) <= 0){
					$confirm_txt[] = '-';
				}

				$this->load->model('shippingmodel');
				$ship_set_code = $this->shippingmodel->ship_set_code;
				$shipping_txt = array();
				if( $params['search_shipping_nation']['kr'] ){
					$shipping_txt_domestic = array();
					foreach($params['search_shipping_method_kr'] as $v){
						$shipping_txt_domestic[] = $ship_set_code[$v];
					}
					if( count($shipping_txt_domestic) <= 0 ){
						$shipping_txt_domestic[] = '-';
					}
					$shipping_txt[] = "국내(".implode(', ', $shipping_txt_domestic).")";
				}

				if( $params['search_shipping_nation']['kr'] ){
					$shipping_txt_nation = array();
					foreach($params['search_shipping_method_gl'] as $v){
						$shipping_txt_nation[] = $ship_set_code[$v];
					}
					if( count($shipping_txt_nation) <= 0 ){
						$shipping_txt_nation[] = '-';
					}
					$shipping_txt[] = "해외(".implode(', ', $shipping_txt_nation).")";
				}

				if($params['search_shipping_method_coupon'] == 'coupon'){
					$shipping_txt[] = "문자/이메일 (티켓발송)";
				}

				if(strlen($params['search_delivery_company_code']) > 0){
					$delivery_company_array = config_load('delivery_url');
					$delivery_txt = $delivery_company_array[$params['search_delivery_company_code']]['company'];
				} else {
					$delivery_txt = '전체';
				}

				if($params['null_delivery_number']){
					$delivery_txt .= " (운송장번호 없음)";
				} else if(strlen($params['search_delivery_number']) > 0) {
					$delivery_txt .= " (".$params['search_delivery_number'].")";
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>배송책임</td><td>".$provider."</td></tr>";
				$desc .= "<tr><td>".$date_txt."</td><td>".$params['regist_date'][0]." ~ ".$params['regist_date'][1]."</td></tr>";
				$desc .= "<tr><td>상태</td><td>".implode(', ', $status_txt)."</td></tr>";
				if($params['chk_bundle_yn']){
					$desc .= "<tr><td>합포장</td><td>선택</td></tr>";
				} else {
					$desc .= "<tr><td>합포장</td><td>미선택</td></tr>";
				}
				$desc .= "<tr><td>구매확정</td><td>".implode(', ', $confirm_txt)."</td></tr>";
				$desc .= "<tr><td>출고방법</td><td>".implode('<br>', $shipping_txt)."</td></tr>";
				$desc .= "<tr><td>택배정보</td><td>".$delivery_txt."</td></tr>";
				//debug_var($params);
			break;

			default:
				if(strlen($params['keyword']) > 0){
					$key_txt = $params['keyword'];
				} else {
					$key_txt = '-';
				}

				if($menu == 'company_catalog'){
					$provider = '본사';
				} else {
					if($params['shipping_provider_seq'] > 0){
						$this->load->model('providermodel');
						$provider = $this->providermodel->provider_goods_list_sort();
						$providerInfo = array();
						foreach($provider as $k => $v){
							$providerInfo[$v['provider_seq']] = $v['provider_name'];
						}
						$provider = $providerInfo[$params['shipping_provider_seq']];
					} else {
						$provider = '전체';
					}
				}


				if($params['date_field'] == 'deposit_date'){
					$date_txt = '입금일';
				} else {
					$date_txt = '주문일';
				}

				$status_config	= config_load('step');
				$status_txt = array();
				$i = 0;
				foreach($params['chk_step'] as $k => $v){
					if($i%6 == 0 && $i > 0){
						$status_txt[$k] = "<br>".$status_config[$k];
					} else {
						$status_txt[$k] =  $status_config[$k];
					}
					$i++;
				}
				if(count($status_txt) <= 0){
					$status_txt[] = '전체';
				}

				$this->load->model('shippingmodel');
				$ship_set_code = $this->shippingmodel->ship_set_code;
				$shipping_txt = array();
				if( in_array('domestic', array_values($params['nation'])) !== false ){
					$shipping_txt_domestic = array();
					foreach($params['shipping_set_code']['domestic'] as $v){
						$shipping_txt_domestic[] = $ship_set_code[$v];
					}
					if( count($shipping_txt_domestic) <= 0 ){
						$shipping_txt_domestic[] = '-';
					}
					$shipping_txt[] = "국내(".implode(', ', $shipping_txt_domestic).")";
				}

				if( in_array('international', array_values($params['nation'])) !== false ){
					$shipping_txt_nation = array();
					foreach($params['shipping_set_code']['international'] as $v){
						$shipping_txt_nation[] = $ship_set_code[$v];
					}
					if( count($shipping_txt_nation) <= 0 ){
						$shipping_txt_nation[] = '-';
					}
					$shipping_txt[] = "해외(".implode(', ', $shipping_txt_nation).")";
				}

				if($params['shipping_set_code']['ticket'] == 'ticket'){
					$shipping_txt[] = "문자/이메일 (티켓발송)";
				}

				$this->load->helper('order');
				$search_arr_field = search_arr_field();
				$goodstype_txt = array();
				foreach($params['goodstype'] as $v){
					$goodstype_txt[] = $search_arr_field['arr_order_goods_type'][$v];
				}
				if(count($goodstype_txt) <= 0){
					$goodstype_txt[] = '-';
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>배송책임</td><td>".$provider."</td></tr>";
				$desc .= "<tr><td>".$date_txt."</td><td>".$params['regist_date'][0]." ~ ".$params['regist_date'][1]."</td></tr>";
				$desc .= "<tr><td>상태</td><td>".implode(', ', $status_txt)."</td></tr>";
				$desc .= "<tr><td>배송방법</td><td>".implode('<br>', $shipping_txt)."</td></tr>";
				if( $params['shipping_hop_use'] == 'y' ){
					$desc .= "<tr><td>배송예정</td><td>".$params['shipping_hope_sdate']." ~ ".$params['shipping_hope_edate']."</td></tr>";
				} else {
					$desc .= "<tr><td>배송예정</td><td>전체</td></tr>";
				}
				if( $params['shipping_reserve_use'] == 'y' ){
					$desc .= "<tr><td>예약상품발송일</td><td>".$params['shipping_reserve_sdate']." ~ ".$params['shipping_reserve_edate']."</td></tr>";
				} else {
					$desc .= "<tr><td>예약상품발송일</td><td>전체</td></tr>";
				}
				$desc .= "<tr><td>주문상품</td><td>".implode(', ', $goodstype_txt)."</td></tr>";

				if($menu == 'catalog'){
					if($params['chk_bundle_yn']){
						$desc .= "<tr><td>합포장</td><td>선택</td></tr>";
					} else {
						$desc .= "<tr><td>합포장</td><td>미선택</td></tr>";
					}

					$this->load->helper('common');
					$sitetypeloop = sitetype('', 'name', 'array');
					$sitetype_txt = array();
					foreach($params['sitetype'] as $v){
						$sitetype_txt[] = $sitetypeloop[$v]['name'];
					}
					if(count($sitetype_txt) <= 0){
						$sitetype_txt[] = '-';
					}

					$ordertype_txt = array();
					foreach($params['ordertype'] as $v){
						if($v == 'admin'){
							$ordertype_txt[] = '관리자주문';
						} else if($v == 'personal'){
							$ordertype_txt[] = '개인결제';
						} else if($v == 'change'){
							$ordertype_txt[] = '교환주문';
						}
					}
					if(count($ordertype_txt) <= 0){
						$ordertype_txt[] = '-';
					}

					$pay_config	= config_load('payment');
					$pay_info = array_unique(array_merge($pay_config, $search_arr_field['arr_order_payment']), SORT_REGULAR);
					$pay_info['pos_pay'] = '매장결제';
					$payment_txt = array();
					$i = 0;
					foreach($params['payment'] as $k => $v){
						if($i%6 == 0 && $i > 0){
							$payment_txt[$k] = "<br>".$pay_info[$v];;
						} else {
							$payment_txt[$k] =  $pay_info[$v];
						}
						$i++;
					}
					if(count($payment_txt) <= 0){
						$payment_txt[] = '-';
					}

					$pg_txt = array();
					$i = 0;
					foreach($params['pg'] as $k => $v){
						if($i%6 == 0 && $i > 0){
							$pg_txt[$k] = "<br>".$search_arr_field['arr_order_pg'][$v];;
						} else {
							$pg_txt[$k] =  $search_arr_field['arr_order_pg'][$v];
						}
						$i++;
					}
					if(count($pg_txt) <= 0){
						$pg_txt[] = '-';
					}

					$this->load->model('statsmodel');
					$referer_list = $this->statsmodel->get_referer_grouplist();
					$referer_info = array();
					foreach($referer_list as $v){
						$referer_info[$v['referer_group_cd']] = $v['referer_group_name'];
					}
					$referer_info['etc'] = '기타';
					$referer_txt = array();
					$i = 0;
					foreach($params['referer'] as $k => $v){
						if($i%4 == 0 && $i > 0){
							$referer_txt[$k] = "<br>".$referer_info[$v];
						} else {
							$referer_txt[$k] =  $referer_info[$v];
						}
						$i++;
					}
					if(count($referer_txt) <= 0){
						$referer_txt[] = '-';
					}

					$this->load->model('connectormodel');
					$connector	= $this->connector::getInstance();
					$marketList	= $connector->getAllMarkets(true);
					$marketList['NOT']['name'] = '내 쇼핑몰';
					$market_txt = array();
					foreach($params['selectMarkets'] as $k => $v){
						$market_txt[] = $marketList[$v]['name'];
					}
					if(count($market_txt) <= 0){
						$market_txt[] = '-';
					}

					$desc .= "<tr><td>주문환경</td><td>".implode(', ', $sitetype_txt)."</td></tr>";
					$desc .= "<tr><td>주문유형</td><td>".implode(', ', $ordertype_txt)."</td></tr>";
					$desc .= "<tr><td>결제수단</td><td>".implode(', ', $payment_txt)."</td></tr>";
					$desc .= "<tr><td>결제사</td><td>".implode(', ', $pg_txt)."</td></tr>";
					$desc .= "<tr><td>주문유입</td><td>".implode(', ', $referer_txt)."</td></tr>";
					$desc .= "<tr><td>오픈마켓</td><td>".implode(', ', $market_txt)."</td></tr>";
				}


			break;
		}

		return $desc;
	}

	public function manager_list($provider_seq_sel, $is_flag='N')
	{
		if($is_flag == 'Y'){
			$provider_seq = $provider_seq_sel;
		} else {
			$provider_seq = $this->input->get('provider_seq');
		}

		$where_and['provider_id !='] = 'base';
		$where_or = "provider_group = '".$provider_seq."' or provider_seq = '".$provider_seq."'";

		//목록
		$query = $this->db
			->select("provider_seq as manager_seq, provider_name as mname, provider_id as manager_id")
			->from('fm_provider')
			->where($where_and)
			->where($where_or)
			->order_by('manager_id', 'ASC')
			->get();

		//--> 키 인덱스로 재배열
		$providerData = array();
		foreach($query->result_array() as $k => $provider){
			$providerData[$k]['manager_seq']	= $provider['manager_seq'];
			$providerData[$k]['manager_id']	= $provider['manager_id'];
			$providerData[$k]['mname']		= $provider['mname'];
		}

		if($is_flag == 'Y'){
			return $providerData;
		} else {
			echo json_encode($providerData);
		}
		exit;
	}

}

/* End of file setting.php */
/* Location: ./app/controllers/selleradmin/setting.php */