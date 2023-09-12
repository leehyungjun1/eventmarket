<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);
require_once(APPPATH.'/libraries/Spout/Autoloader/autoload.php'); //excel library

use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Writer\WriterFactory;
use Box\Spout\Common\Type;
use Box\Spout\Writer\Style\StyleBuilder;
use Box\Spout\Writer\Style\Color;
use Box\Spout\Writer\Style\Border;
use Box\Spout\Writer\Style\BorderBuilder;

class setting extends admin_base {

	public function __construct() {
		parent::__construct();

		$this->load->model('authmodel');
		$this->load->library('validation');

		$this->template->assign('APP_USE',	$this->__APP_USE__);
		$this->template->assign('APP_ID',	$this->__APP_ID__);
		$this->template->assign('APP_SECRET',	$this->__APP_SECRET__);
		$this->template->assign('APP_PAGE',	$this->__APP_PAGE__);

		$this->template->define(array('require_info'=>$this->skin."/setting/_require_info.html"));
		$this->template->define(array('setting_menu'=>$this->_setting_menu_template_path()));

		$setting_menu = $this->uri->rsegments[count($this->uri->rsegments)];
		if($setting_menu=='manager_reg') $setting_menu = 'manager';
		if($setting_menu=='provider_reg') $setting_menu = 'provider';
		if($setting_menu=='multi_basic') $setting_menu = 'multi';
		if($setting_menu=='shipping_group_regist') $setting_menu = 'shipping_group';

		$this->template->assign(array('selected_setting_menu'=>$setting_menu));

	}

	public function index()
	{
		redirect('admin/setting/multi');
	}

	protected function _setting_menu_template_path(){
		return $this->skin."/setting/_setting_menu.html";
	}

	/* 입점마케팅 전체 행 갯수 */
	function file_rows(){
		$this->load->model('goodsmodel');
		$markets	= array('all','summary');
		foreach($markets AS $val){
			$last_update_date = '';
			if($mode == 'summary'){
				$tmp = config_load('partner','naver_update');
				if($tmp['naver_update']) $last_update_date = $tmp['naver_update'];
			}
			$query		= $this->goodsmodel->get_goods_all_partner_count($last_update_date,'view',true);
			$result		= mysqli_query($this->db->conn_id,$query);
			$data		= mysqli_fetch_array($result);
			$rows[$val]	= $data['cnt'];
		}


		return $rows;
	}

	/* 판매환경 설정 */
	public function config()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		## sns 로그인&가입 관련
		$this->joinform_sns();

		$this->load->model('configsalemodel');

		$sc['type'] = 'mobile';
		$systemmobiles = $this->configsalemodel->lists($sc);
		$this->template->assign('systemmobiles',$systemmobiles['result']);

		$goodssql = "select goods_seq,goods_name  from fm_goods order by goods_seq desc limit 0,1";
		$goodsquery = $this->db->query($goodssql);
		$goodsdata = $goodsquery->row_array();
		$this->template->assign('goods_seq',$goodsdata['goods_seq']);
		$this->template->assign('goods_name',$goodsdata['goods_name']);

		$arrSystem			= ($this->config_system)?$this->config_system:config_load('system');
		$page_id_f_ar		= explode(",",$this->arrSns['page_id_f']);
		$page_name_ar		= explode(",",$this->arrSns['page_name_f']);
		$page_url_ar		= explode(",",$this->arrSns['page_url_f']);
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
		$unsetuserdata = array('access_token' => '', 'fbuser' => '');
		 $this->session->unset_userdata($unsetuserdata);


		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		switch($reserves['reserve_select']){
			case "year":
				$reserves['reservetitle'] = date("Y년 m월 d일", mktime(0,0,0,12, 31, date("Y")+$reserves['reserve_year']));
				break;
			case "direct":
				$reserves['reservetitle'] = $reserves['reserve_direct'].'개월';
				break;
			default:
				$reserves['reservetitle'] = '제한하지 않음';
				break;
		}

		switch($reserves['point_select']){
			case "year":
				$reserves['pointtitle'] = date("Y년 m월 d일", mktime(0,0,0,12, 31, date("Y")+$reserves['point_year']));
				break;
			case "direct":
				$reserves['pointtitle'] = $reserves['point_direct'].'개월';
				break;
			default:
				$reserves['pointtitle'] = '제한하지 않음';
				break;
		}
		$this->template->assign($reserves);

		$this->template->assign('redirect_uri_new', 'http://'.$_SERVER['HTTP_HOST'].'/admin/sns/config_facebook');

		$this->template->assign($this->arrSns);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($arrSystem);
		$this->template->print_("tpl");
	}

	/* 일반 설정 */
	public function basic()
	{
		redirect('admin/setting/multi');
	}


	/* SEO 설정 */
	public function seo(){

		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_seo_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		$cfg_goods = config_load("goods");
		$arrSeoInfo	= ($this->seo)?$this->reserves:config_load('seo');

		if(is_file("{$_SERVER['DOCUMENT_ROOT']}/robots.txt")){
			$arrSEO['robots'];
			$fp		= fopen("{$_SERVER['DOCUMENT_ROOT']}/robots.txt", "r");
			$fstat	= fstat($fp);
			fclose($fp);

			$arrSeoInfo['robots']['size']	= $fstat['size'];
			$arrSeoInfo['robots']['time']	= date('Y-m-d H:i:s',$fstat['ctime']);
		}else{
			$arrSeoInfo['robots']['size']	= 'none';
			$arrSeoInfo['robots']['time']	= 'none';
		}

		if(is_file("{$_SERVER['DOCUMENT_ROOT']}/sitemap.xml")){
			$arrSEO['robots'];
			$fp		= fopen("{$_SERVER['DOCUMENT_ROOT']}/sitemap.xml", "r");
			$fstat	= fstat($fp);
			fclose($fp);

			$arrSeoInfo['sitemap']['size']	= $fstat['size'];
			$arrSeoInfo['sitemap']['time']	= date('Y-m-d H:i:s',$fstat['ctime']);
		}else{
			$arrSeoInfo['sitemap']['size']	= 'none';
			$arrSeoInfo['sitemap']['time']	= 'none';
		}


		$arrBasic = ($this->config_basic)?$this->config_basic:config_load('basic');

		if(isset($arrBasic['businessLicense']))$arrBasic['businessLicense'] = explode('-',$arrBasic['businessLicense']);
		if(isset($arrBasic['providerNumber']))$arrBasic['providerNumber'] = explode('-',$arrBasic['providerNumber']);
		if(isset($arrBasic['companyPhone']))$arrBasic['companyPhone'] = explode('-',$arrBasic['companyPhone']);
		if(isset($arrBasic['companyFax']))$arrBasic['companyFax'] = explode('-',$arrBasic['companyFax']);
		if(isset($arrBasic['companyZipcode']))$arrBasic['companyZipcode'] = explode('-',$arrBasic['companyZipcode']);
		if(isset($arrBasic['companyEmail']))$arrBasic['companyEmail'] = explode('@',$arrBasic['companyEmail']);
		if(isset($arrBasic['partnershipEmail']))$arrBasic['partnershipEmail'] = explode('@',$arrBasic['partnershipEmail']);
		if(count($arrBasic['companyPhone']) < 3){
			$arrBasic['companyPhone'][2] = $arrBasic['companyPhone'][1];
			$arrBasic['companyPhone'][1] = $arrBasic['companyPhone'][0];
			$arrBasic['companyPhone'][0] = '';
		}
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


		$snslogo = $this->config_system['snslogo'];
		$snslogo_exists = 0;
		if($snslogo && file_exists('.'.$snslogo) )
			$snslogo_exists = 1;
		$this->template->assign('snslogo_exists',$snslogo_exists);

		$reserve = ($this->reserves)?$this->reserves:config_load('reserve');
		$this->template->assign('reserve',$reserve);

		## sns 로그인&가입 관련
		$this->joinform_sns();

		$this->template->define(array('shorturl_setting'=>$this->skin."/setting/snsconf_shorturl_setting.html"));
		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($arrBasic);
		$this->template->assign($arrSeoInfo);
		$this->template->print_("tpl");
	}

	/* 마케팅설정 */
	public function admin_marketing_conf()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();
		$marketing = ($this->marketing)?$this->marketing:config_load('marketing');

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign('marketing',$marketing);
		$this->template->print_("tpl");
	}

	/* SNS마케팅 설정 */
	public function snsconf()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_snsconf_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		// sns 설정을 위한 자바스크립트 호출
		requirejs([
			["/app/javascript/js/admin/snsconfSetting.js",20],
		]);

		$this->template->assign('snsgoods', 'goods');
		$this->template->assign('snsevent', 'event');
		$this->template->assign('snsboard', 'board');
		$arrBasic = ($this->config_basic)?$this->config_basic:config_load('basic');

		## 페이스북 좋아요 설정 2014.07.18
		$sc['type'] = 'fblike';
		$this->load->model('configsalemodel');
		$systemfblike = $this->configsalemodel->lists($sc);
		$this->template->assign('systemfblike',$systemfblike['result']);

		$this->template->define(array('tpl'=>$filePath,'sns_setting'=>$this->skin."/setting/joinform_sns.html"));

		## 좋아요 설정값
		$orders = config_load('order');
		$this->template->assign('fblike_ordertype',$orders['fblike_ordertype']);

		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		switch($reserves['reserve_select']){
			case "year":
				$reserves['reservetitle'] = date("Y년 m월 d일", mktime(0,0,0,12, 31, date("Y")+$reserves['reserve_year']));
				break;
			case "direct":
				$reserves['reservetitle'] = $reserves['reserve_direct'].'개월';
				break;
			default:
				$reserves['reservetitle'] = '제한하지 않음';
				break;
		}
		switch($reserves['point_select']){
			case "year":
				$reserves['pointtitle'] = date("Y년 m월 d일", mktime(0,0,0,12, 31, date("Y")+$reserves['point_year']));
				break;
			case "direct":
				$reserves['pointtitle'] = $reserves['point_direct'].'개월';
				break;
			default:
				$reserves['pointtitle'] = '제한하지 않음';
				break;
		}
		$this->template->assign($reserves);

		## sns 로그인&가입 관련
		$this->joinform_sns();

        ## 채널톡 설정 2021.05.11
        $this->load->library('channeltalklibrary');
        $channeltalk = $this->channeltalklibrary->get_channeltalk();


		/* 짧은 url 설정에 따른 안내 문구 추가 leewh 2014-12-04 */
		$set_url = true;
		$set_string = "";
		if (empty($this->arrSns['shorturl_app_id']) && empty($this->arrSns['shorturl_app_key']) && empty($this->arrSns['shorturl_app_token'])) {
			$set_url = false;
			$set_string = "설정이 필요";
		}

		$shorturl_test = 'http://'.$this->config_system['subDomain'].'/goods/view?no=1';
		list($shorturl, $shorturl_result) = get_shortURL($shorturl_test);

		if ($shorturl_result === false) {
			$shorturl = "http://bit.ly/xxxxxxxx";
			if ($set_url) {
				$set_string = "제대로 설정되지 않았습니다. ‘설정’ 을 확인해 주세요";
			}
		}

		$snslogo = $this->config_system['snslogo'];
		$snslogo_exists = 0;
		if($snslogo && file_exists('.'.$snslogo) )
			$snslogo_exists = 1;

		// 인스타그램 연동 설정 정보
		$this->load->library('instagramlibrary');
		$instagram = $this->instagramlibrary->getConfig();

		$this->template->assign('redirect_uri_new',get_connet_protocol().$_SERVER['HTTP_HOST'].'/admin/sns/config_facebook');
		$this->template->assign('shorturl_test',$shorturl_test);
		$this->template->assign('shorturl',$shorturl);
		$this->template->assign('set_url',$set_url);
		$this->template->assign('set_string',$set_string);
		$this->template->assign('snslogo_exists',$snslogo_exists);
		$this->template->assign('instagram', $instagram);
		$this->template->assign('aInstargramThumb', config_load('instargramThumb'));
		$this->template->assign('instagramFeed', config_load('instagramFeed'));
        $this->template->assign('channeltalk',$channeltalk);
		$this->template->assign($arrBasic);
		$this->template->print_("tpl");
	}

	## SNS 및 외부 연동 기능 안내 : 자세히 팝업
	public function snsconf_detail(){

		$this->load->helper('admin');
		if($_GET['mode']) $snsconf_detail		= getGabiaPannel('snsconf_'.$_GET['mode']);
		echo $snsconf_detail;
	}

	/* 운영 설정 */
	public function operating(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_operating_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('operating');
		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		$realname = config_load('realname');
		$realname['adult_chk'] = "N";
		if( $realname['useRealname'] == 'Y' && $realname['realnameId'] && $realname['realnamePwd']) $realname['adult_chk'] = "Y";
		if( $realname['useRealnamephone'] == 'Y' && $realname['realnamephoneSikey'] && $realname['realnamePhoneSipwd']) $realname['adult_chk'] = "Y";
		if( $realname['useIpin'] == 'Y' && $realname['ipinSikey'] && $realname['ipinKeyString']) $realname['adult_chk'] = "Y";

		$this->template->assign('realname',$realname);

		// [반응형스킨] 운영방식 타입 추가 :: 2018-10-31 pjw
		$operationType = !empty($this->config_system['operation_type']) ? $this->config_system['operation_type'] : 'heavy';
		$this->template->assign('operationType',$operationType);

		$arrBasic = ($this->config_basic)?$this->config_basic:config_load('basic');
		$this->template->assign($arrBasic);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}
	/* PG 설정 */
	public function pg(){

		$this->admin_menu();
		$this->tempate_modules();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_pg_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		$arrBanner[0] = "pg_banner_inicis.jpg";
		$arrBanner[1] = "pg_banner_kcp.jpg";
		$arrBanner[2] = "pg_banner_lg.jpg";

		$rand = rand(0, 2);

		$banner = $arrBanner[$rand];

		$navercheckout = config_load('navercheckout');

		$this->template->assign(array('banner'=>$banner));
		$this->template->assign(array('rand'=>$rand));
		$this->template->assign('navercheckout',$navercheckout);

		$naverpayment = config_load('naverpayment');
		$this->template->assign('naverpayment',$naverpayment);

		$talkbuy = config_load('talkbuy');
		$this->template->assign('talkbuy',$talkbuy);

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");


		$result = checkPgEnvironment($this->config_system['pgCompany']);
		if(!$result[0]){
			echo "<script type='text/javascript'>$.get('../../_firstmallplus/env_pg_interface', function(data){});</script>";
		}
	}

	/**
	 * 카카오페이 설정
	 */
	public function talkbuy() {
		// 카카오 설정 가져오기
		$this->load->library("talkbuylibrary");
		$talkbuy_config = $this->talkbuylibrary->load_talkbuy_config();
		$this->template->assign($talkbuy_config);

		// 사용가능한 배송그룹 가져오기
		$this->load->library("partnerlib");
		$partner_shipping_group = $this->partnerlib->possible_partner_shipping_group();
		$this->template->assign($partner_shipping_group);

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->define(array('talkbuy_config'=>$this->skin.'/setting/_talkbuy_config.html'));
		$this->template->print_("tpl");
	}

	/**
	 * PG사 정보 출력
	 */
	public function pgInfo(){
		$pgCompany = $_GET["pgCompany"];
		$filePath	= $this->template_path();
		$tmp = config_load($pgCompany);

		//추가 설정 부분
		switch($pgCompany){
			case "payco" : //페이코
				if(!$tmp['payco_currency']) $tmp['payco_currency'] = "KRW";

				$payment_opt_arr = array('31'=>'신용카드','35'=>'간편계좌','02'=>'무통장입금'); // ,'98'=>'페이코 포인트'
				$pg_method_code = explode('||', preg_replace('/^\|\|/','',$tmp['method_code']));
				foreach($pg_method_code as $k=>$v){
					if(!empty($payment_opt_arr[$v])){
						$tmp['payment_opt_str'][] = $payment_opt_arr[$v];
					}
				}
				$tmp['payment_opt_str'] = implode(' / ', $tmp['payment_opt_str']);
				break;
			case "kakaopay" : //카카오 페이
				$tmp['arrKakaoCardCompany'] = code_load('kakaoCardCompanyCode');

				// 카카오 페이 추가로 인한 임시 코드값 삽입 :: 추후 삭제 요망
				if(!$tmp['arrKakaoCardCompany']){
					$arrKakaoCode['01'] = '비씨';
					$arrKakaoCode['07'] = '현대';
					$arrKakaoCode['13'] = '수협';
					$arrKakaoCode['21'] = '광주';
					$arrKakaoCode['27'] = '해외다이너스';
					$arrKakaoCode['02'] = '국민';
					$arrKakaoCode['08'] = '롯데자사';
					$arrKakaoCode['15'] = '우리';
					$arrKakaoCode['22'] = '전북';
					$arrKakaoCode['28'] = '해외AMX';
					$arrKakaoCode['03'] = '외환';
					$arrKakaoCode['11'] = '씨티';
					$arrKakaoCode['16'] = '하나SK';
					$arrKakaoCode['23'] = '제주';
					$arrKakaoCode['29'] = '해외JCB';
					$arrKakaoCode['04'] = '삼성';
					$arrKakaoCode['11'] = '한미';
					$arrKakaoCode['18'] = '주택';
					$arrKakaoCode['25'] = '해외비자';
					$arrKakaoCode['30'] = '해외디스커버';
					$arrKakaoCode['06'] = '신한';
					$arrKakaoCode['12'] = 'NH채움';
					$arrKakaoCode['19'] = '조흥(강원';
					$arrKakaoCode['26'] = '해외마스터';
					$arrKakaoCode['34'] = '은련';
					$arrKakaoCode['06'] = '신한(구LG)';
					code_save('kakaoCardCompanyCode',$arrKakaoCode);

					$tmp['arrKakaoCardCompany'] = code_load('kakaoCardCompanyCode');
				}

				foreach($tmp['arrKakaoCardCompany'] as $k=>$v){
					$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
				}

				$tmp["interestTerms"] = (int)$tmp["interestTerms"];
				break;
			case "daumkakaopay" : //다음카카오 페이
				$payment_opt_arr = array('CARD'=>'카드','MONEY'=>'카카오머니');
				foreach($tmp['payment_opt'] as $k => $opt){
					$tmp['payment_opt_str'][] = $payment_opt_arr[$opt];
				}
				$tmp['payment_opt_str'] = implode(' / ', $tmp['payment_opt_str']);

				if($tmp["interestTerms"] == 'auto'){
					$tmp["interestTerms"] = '자동';
				}else if($tmp["interestTerms"] == '01'){
					$tmp["interestTerms"] = '일시불 고정';
				}else{
					$tmp["interestTerms"] = (int)$tmp["interestTerms"] . ' 개월 고정';
				}
				break;

			case "paypal" : //페이팔
				if(!$tmp['paypal_currency']) $tmp['paypal_currency'] = "USD";
				break;

			case "kcp" : //KCP
				$tmp['arrKcpCardCompany'] = code_load('kcpCardCompanyCode');
				foreach($tmp['arrKcpCardCompany'] as $k=>$v){
					$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
				}
				if	($tmp['kcp_logo_val_img'])
					$tmp['kcp_logo_val_img_url']	= get_connet_protocol().$_SERVER['HTTP_HOST'].str_replace(ROOTPATH, '/', $tmp['kcp_logo_val_img']);
					$tmp['shopName']	= $this->config_basic['shopName'];
					if($tmp) {
						if($this->isdemo['isdemo']){
							$tmp['mallCode'] = getstrcut($tmp['mallCode'],0,'*********');
							$tmp['merchantKey'] = getstrcut($tmp['merchantKey'],0,'******************');
						}
						$this->template->assign($tmp);
					}
				break;

			case "lg" : //lg유플러스
				$tmp['arrLgCardCompany'] = code_load('lgCardCompanyCode');
				foreach($tmp['arrLgCardCompany'] as $k=>$v){
					$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
				}
				break;

			case "inicis" : //이니시스
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
				break;


			case "kspay" : //KSNET
				$arrKspayCompany	= array(	'01'=>'비씨카드', '02'=>'국민카드', '03'=>'외환카드',
				'04'=>'삼성카드', '05'=>'신한카드', '08'=>'현대카드',
				'09'=>'롯데카드', '11'=>'한미은행', '12'=>'수협',
				'14'=>'우리은행', '15'=>'농협', '16'=>'제주은행',
				'17'=>'광주은행', '18'=>'전북은행', '19'=>'조흥은행',
				'23'=>'주택은행', '24'=>'하나은행', '26'=>'씨티은행',
				'25'=>'해외카드사', '99'=>'기타'	);
				$params						= config_load('kspay');
				$params['shopName']			= $this->config_basic['shopName'];
				$params['arrKspayCompany']	= $arrKspayCompany;

				$this->template->assign($params);

				break;
			case "eximbay" :
				$exCur = code_load('eximbay_cur');

				foreach($exCur as $data){
					if($data["codecd"] == $tmp["eximbay_cur"]){
						$eximBayCur = $data["codecd"];
					}
				}

				$this->template->assign('eximBayCur',$eximBayCur);
				break;
			case "naverpay" :
			case "talkbuy" :
				if($pgCompany == "naverpay") {
					$partnerName = "npay";
					$partnerConfigName = "navercheckout";
				} else if($pgCompany == "talkbuy") {
					$partnerName = "talkbuy";
					$partnerConfigName = "talkbuy";
				}

				$partnerConfig = config_load($partnerConfigName);

				# ----------------------------------------------------------------------------------------------------------
				# 배송가능 그룹 조회
				$this->load->model("shippingmodel");
				$this->load->model("providermodel");
				$shipping_group_list = $this->shippingmodel->get_shipping_group_list(null,array('order_by'=>'shipping_provider_seq'));
				$shipping_group = array();
				foreach($shipping_group_list as $group_data){

					$shipping_set_list	= $this->shippingmodel->load_shipping_set_list($group_data['shipping_group_seq']);

					$shipping_group_tmp = array();
					$set_data_tmp		= array();
					foreach($shipping_set_list as $shipping_set_seq => $set_data){
						// 사용가능한 배송정책인지 확인.
						if($set_data[$partnerName.'_order_possible'] == "Y") $set_data_tmp[]	=$set_data['shipping_set_name'];
					}

					if(count($set_data_tmp) > 0){

						$provider_data = $this->providermodel->get_provider_one($group_data['shipping_provider_seq']);

						if(serviceLimit('H_AD')){
							if($group_data['shipping_provider_seq'] > 1){
								$provider_info = $provider_data['provider_name'];
							}else{
								$provider_info = "[본사]";
							}
						}

						// 연결상품 통계 구하기 :: 2017-02-16 lwh
						$goods_cnt['goods']		= $group_data['target_goods_cnt'];
						$goods_cnt['package']	= $group_data['target_package_cnt'];
						$shipping_group_tmp['rel_goods_cnt']		= $goods_cnt;
						$shipping_group_tmp['shipping_group_seq']	= $group_data['shipping_group_seq'];
						$shipping_group_tmp['provider_shipping_use']= $group_data['provider_shipping_use'];
						$shipping_group_tmp['shipping_provider_seq']= $group_data['shipping_provider_seq'];
						$shipping_group_tmp['provider_info']		= $provider_info;
						$shipping_group_tmp['shipping_group_name']	= $group_data['shipping_group_name'];
						$shipping_group_tmp['shipping_set']			= $set_data_tmp;
					}

					if($shipping_group_tmp){
						$shipping_group[$group_data['shipping_provider_seq']][] = $shipping_group_tmp;
					}
				}
				# ----------------------------------------------------------------------------------------------------------
				foreach($shipping_group as $shipping_grp) {
					$shipping_grp_cnt += count($shipping_grp);
				}

				$this->template->assign($partnerName.'_shipping_group_cnt',$shipping_grp_cnt);
				$this->template->assign($partnerConfigName,$partnerConfig);
				break;

			case "kicc" : //KICC
				$tmp['arrKiccCardCompany'] = code_load('kiccCardCompanyCode');
				foreach($tmp['arrKcpCardCompany'] as $k=>$v){
					$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
				}
				break;
		}

		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->assign('pgCompany',$pgCompany);
		$this->template->print_("tpl");
	}

	/* 페이코 설정 :: 2018-08-22 lwh */
	public function payco(){
		$filePath	= $this->template_path();
		$tmp		= config_load('payco');

		if(!$tmp['payco_currency']) $tmp['payco_currency'] = "KRW";
		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}

	/* 카카오페이 설정 :: 2015-02-09 lwh */
	public function kakaopay(){
		$filePath	= $this->template_path();
		$tmp = config_load('kakaopay');
		$tmp['arrKakaoCardCompany'] = code_load('kakaoCardCompanyCode');

		// 카카오 페이 추가로 인한 임시 코드값 삽입 :: 추후 삭제 요망
		if(!$tmp['arrKakaoCardCompany']){
			$arrKakaoCode['01'] = '비씨';
			$arrKakaoCode['07'] = '현대';
			$arrKakaoCode['13'] = '수협';
			$arrKakaoCode['21'] = '광주';
			$arrKakaoCode['27'] = '해외다이너스';
			$arrKakaoCode['02'] = '국민';
			$arrKakaoCode['08'] = '롯데자사';
			$arrKakaoCode['15'] = '우리';
			$arrKakaoCode['22'] = '전북';
			$arrKakaoCode['28'] = '해외AMX';
			$arrKakaoCode['03'] = '외환';
			$arrKakaoCode['11'] = '씨티';
			$arrKakaoCode['16'] = '하나SK';
			$arrKakaoCode['23'] = '제주';
			$arrKakaoCode['29'] = '해외JCB';
			$arrKakaoCode['04'] = '삼성';
			$arrKakaoCode['11'] = '한미';
			$arrKakaoCode['18'] = '주택';
			$arrKakaoCode['25'] = '해외비자';
			$arrKakaoCode['30'] = '해외디스커버';
			$arrKakaoCode['06'] = '신한';
			$arrKakaoCode['12'] = 'NH채움';
			$arrKakaoCode['19'] = '조흥(강원';
			$arrKakaoCode['26'] = '해외마스터';
			$arrKakaoCode['34'] = '은련';
			$arrKakaoCode['06'] = '신한(구LG)';
			code_save('kakaoCardCompanyCode',$arrKakaoCode);

			$tmp['arrKakaoCardCompany'] = code_load('kakaoCardCompanyCode');
		}

		foreach($tmp['arrKakaoCardCompany'] as $k=>$v){
			$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
		}

		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}

	/* 다음카카오페이 설정 :: 2017-12-08 lwh */
	public function daumkakaopay(){
		$filePath		= $this->template_path();
		$kakaopay_conf	= config_load('daumkakaopay');

		// CID가 없을때 현재 상태 조회 기능 심사완료 이슈로 인해 제거 by hed 2018-05-02
		/*
		// CID가 없을 시 현재 상태 신청 상태 조회
		if(empty($kakaopay_conf['cid'])){
			$this->load->helper('readurl');

			$shopSno = $this->config_system['shopSno'];
			$service_code = $this->config_system['service']['code'];

			$firstmall_url = "http://firstmall.kr/ec_hosting/pg_apply/return/kakaopay.php";

			$params = array(
				'shopSno' => $shopSno,
				'service_code' => $service_code,
				'kakaopay_request_type' => "check",
			);

			$return = readurl($firstmall_url,$params);
			$return = json_decode($return);
			$return = get_object_vars($return);
			if(isset($return['cid'])){
				$kakaopay_conf['cid'] = $return['cid'];
			}
		}
		*/

		$this->template->define(array('tpl'=>$filePath));
		if($kakaopay_conf) $this->template->assign($kakaopay_conf);
		$this->template->print_("tpl");
	}

	/* 다음카카오페이 상태 조회 :: 2018-03-02 hed */
	public function daumkakaopayGetCorporation(){

		$this->load->helper('readurl');

		$shopSno = $this->config_system['shopSno'];
		$service_code = $this->config_system['service']['code'];

		$firstmall_url = get_connet_protocol()."firstmall.kr/ec_hosting/pg_apply/return/kakaopay.php";
		$params = array(
			'shopSno' => $shopSno,
			'service_code' => $service_code,
			'kakaopay_request_type' => "get",
		);

		$return = readurl($firstmall_url,$params);
		$json_return = json_decode($return);
		$json_return = get_object_vars($json_return);
		if(empty($return) || empty($json_return['kakaopay_status'])){
			$return = array(
				'kakaopay_status' => 'NOTYET'
			);
			$return = json_encode($return);
		}
		echo $return;
		exit;
	}
	// 페이코 상태 조회 :: 2018-10-10 lwh
	public function paycoGetCorporation(){

		$this->load->helper('readurl');

		$shopSno = $this->config_system['shopSno'];
		$service_code = $this->config_system['service']['code'];

		$firstmall_url = get_connet_protocol()."firstmall.kr/ec_hosting/pg_apply/return/payco.php";
		$params = array(
			'shopSno' => $shopSno,
			'service_code' => $service_code,
			'payco_request_type' => "get",
		);

		$return = readurl($firstmall_url,$params);
		$json_return = json_decode($return);
		$json_return = get_object_vars($json_return);
		if(empty($return) || empty($json_return['payco_status'])){
			$return = array(
				'payco_status' => 'NOTYET'
			);
			$return = json_encode($return);
		}
		echo $return;
		exit;
	}

	/* 페이팔(Paypal) 설정 :: 2016-07-26 pjm */
	public function paypal(){
		$filePath	= $this->template_path();
		$tmp		= config_load('paypal');
		if(!$tmp['paypal_currency']) $tmp['paypal_currency'] = "USD";
		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}

	/* 엑심베이(eximbay) 설정 :: 2016-07-26 pjm */
	public function eximbay(){

		$filePath	= $this->template_path();
		$tmp		= config_load('eximbay');
		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
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
		if	($tmp['kcp_logo_val_img'])
			$tmp['kcp_logo_val_img_url']	= get_connet_protocol().$_SERVER['HTTP_HOST'].str_replace(ROOTPATH, '/', $tmp['kcp_logo_val_img']);
		$tmp['shopName']	= $this->config_basic['shopName'];
		$this->template->define(array('tpl'=>$filePath));

		if($tmp) {
			if($this->isdemo['isdemo']){
				$tmp['mallCode'] = getstrcut($tmp['mallCode'],0,'*********');
				$tmp['merchantKey'] = getstrcut($tmp['merchantKey'],0,'******************');
			}
			$this->template->assign($tmp);
		}
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

	/* 올엣페이 설정 */
	public function allat(){
		$filePath	= $this->template_path();
		$tmp = config_load('allat');

		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}

	/* KSPAY 설정 */
	public function kspay(){
		$arrKspayCompany	= array(	'01'=>'비씨카드', '02'=>'국민카드', '03'=>'외환카드',
										'04'=>'삼성카드', '05'=>'신한카드', '08'=>'현대카드',
										'09'=>'롯데카드', '11'=>'한미은행', '12'=>'수협',
										'14'=>'우리은행', '15'=>'농협', '16'=>'제주은행',
										'17'=>'광주은행', '18'=>'전북은행', '19'=>'조흥은행',
										'23'=>'주택은행', '24'=>'하나은행', '26'=>'씨티은행',
										'25'=>'해외카드사', '99'=>'기타'	);
		$params						= config_load('kspay');
		$params['shopName']			= $this->config_basic['shopName'];
		$params['arrKspayCompany']	= $arrKspayCompany;

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($params);
		$this->template->print_("tpl");
	}

	//네이버 페이 설정 2017-06-09
	public function naverpay(){
		/* 관리자 권한 체크 : 시작 */
		$navercheckout = config_load('navercheckout');

		if(!$navercheckout['naverpay_mall_id']){
			$navercheckout['naverpay_mall_id'] = $navercheckout['shop_id'];
		}
		if(!$navercheckout['naverpay_user_phone']){
			$navercheckout['naverpay_user_phone'] = $this->config_basic['companyPhone'];
		}
		if(!$navercheckout['naverpay_email']){
			$navercheckout['naverpay_email'] = $this->config_basic['companyEmail'];
		}
		if($navercheckout['naverpay_email']){
			$npay_email							= explode("@",$navercheckout['naverpay_email']);
			$navercheckout['naverpay_email_id']		= $npay_email[0];
			$navercheckout['naverpay_email_host']	= $npay_email[1];
		}

		$this->load->model('categorymodel');
		foreach((array)$navercheckout['except_category_code'] as $k=>$row){
			$navercheckout['except_category_code'][$k]['category_name']  = $this->categorymodel->get_category_name($row['category_code']);
		}

		$goods_list = array('except_goods','culture_goods');
		foreach($goods_list as $key) {
			foreach((array)$navercheckout[$key] as $k=>$row){
				$sql = "select g.goods_name,
				(select image from fm_goods_image where goods_seq=g.goods_seq and image_type='thumbCart' order by cut_number limit 1) image,
				(select provider_name from fm_provider p where g.provider_seq = p.provider_seq) as provider_name,
				(select price from fm_goods_option where goods_seq=g.goods_seq and default_option='y') price from
				fm_goods g where goods_seq=?";
				$query = $this->db->query($sql,$row['goods_seq']);
				$goods = $query->row_array();

				$navercheckout[$key][$k]['goods_name'] = $goods['goods_name'];
				$navercheckout[$key][$k]['image'] = $goods['image'] ? $goods['image'] : '/admin/skin/default/images/common/noimage_list.gif';
				$navercheckout[$key][$k]['price'] = $goods['price'];
				$navercheckout[$key][$k]['provider_name'] = $goods['provider_name'];

			}
		}

		$naver_wcs		= config_load('naver_wcs');
		$naver_mileage	= config_load('naver_mileage');
		$arrmarket		= config_load('marketing');

		// 마케팅 네이버 및 다음 파일 생성 사용 여부 호출 :: 2015-12-03 lwh
		$daum_use			= $this->config_basic['daum_use'];
		$naver_use			= $this->config_basic['naver_use']; # EP 2.0
		$naver_third_use		= $this->config_basic['naver_third_use']; # EP 3.0
		$partner_info			= config_load('partner');

		// 전달 이미지 설정 호출 lwh 2014-02-28
		$marketing_image = config_load('marketing_image');

		// 입점마케팅 전달 데이터 통합 설정 - 입점마케팅 상품명,카드무이자할부 leewh 2015-01-29
		$marketing_feed = config_load('marketing_feed');

		// 입점마케팅 상품 추가할인
		$marketing_sale = config_load('marketing_sale');

		// 팝업 불러오기
		//		$this->load->helper('readurl');
		//		$url = 'http://firstmall.kr/ec_hosting/marketing/marketplace_url_pop.php';
		//		$contants = readurl($url);
		//		$contants = iconv('euc-kr','utf-8',$contants);

		# ----------------------------------------------------------------------------------------------------------
		# 네이버페이 배송가능 그룹 조회
		$this->load->model("shippingmodel");
		$this->load->model("providermodel");
		$shipping_group_list = $this->shippingmodel->get_shipping_group_list(null,array('order_by'=>'shipping_provider_seq'));
		$npay_shipping_group = array();
		foreach($shipping_group_list as $group_data){

			$shipping_set_list	= $this->shippingmodel->load_shipping_set_list($group_data['shipping_group_seq']);

			$shipping_group_tmp = array();
			$set_data_tmp		= array();
			$set_shipping_add	= "";
			foreach($shipping_set_list as $shipping_set_seq => $set_data){

				// 네이버페이에서 사용가능한 배송정책인지 확인.
				if($set_data['npay_order_possible'] == "Y") $set_data_tmp[]	=$set_data['shipping_set_name'];

				//지역별 추가 배송비 - 이게 왜 들어가는지 모르겠음. 주석처리 :: 2017-02-16 lwh
				/*
				 if($set_data['default_yn'] == "Y" && $set_data['add_use'] == "Y" && $set_data['shipping_opt_type']['add'] == "fixed"){
				 $set_shipping_add = "지역별 추가배송비 ".number_format($set_data['shipping_cost']['add'][0])."원";
				 }
				 */
			}

			if(count($set_data_tmp) > 0){

				$provider_data = $this->providermodel->get_provider_one($group_data['shipping_provider_seq']);

				if($set_shipping_add) $set_data_tmp[] = $set_shipping_add;

				if(serviceLimit('H_AD')){
					if($group_data['shipping_provider_seq'] > 1){
						$provider_info = $provider_data['provider_name'];
					}else{
						$provider_info = "[본사]";
					}
				}

				// 연결상품 통계 구하기 :: 2017-02-16 lwh
				$goods_cnt['goods']		= $group_data['target_goods_cnt'];
				$goods_cnt['package']	= $group_data['target_package_cnt'];
				$shipping_group_tmp['rel_goods_cnt']		= $goods_cnt;
				$shipping_group_tmp['shipping_group_seq']	= $group_data['shipping_group_seq'];
				$shipping_group_tmp['provider_shipping_use']= $group_data['provider_shipping_use'];
				$shipping_group_tmp['shipping_provider_seq']= $group_data['shipping_provider_seq'];
				$shipping_group_tmp['provider_info']		= $provider_info;
				$shipping_group_tmp['shipping_group_name']	= $group_data['shipping_group_name'];
				$shipping_group_tmp['shipping_set']			= $set_data_tmp;
			}

			if($shipping_group_tmp){
				$npay_shipping_group[$group_data['shipping_provider_seq']][] = $shipping_group_tmp;
			}
		}
		# ----------------------------------------------------------------------------------------------------------

		if($arrmarket['marketdaum'] == 'y') {
			$target_count	= $this->file_rows();
		}

		//npay 2.1 버튼설정
		$sel_npay_arr = array("pc_goods","mobile_goods");
		foreach($sel_npay_arr as $npay_style){
			if($navercheckout['npay_btn_'.$npay_style]){
				$code		= explode("-",$navercheckout['npay_btn_'.$npay_style]);
				$style_text = $code[0]."-".$code[1] . " 타입";
				$size		= explode("×",$code[3]);
				$h			= $size[1];
			}else{
				$style_text = "";
				$$h			= "88";
			}
			$sel_npay_btn_text[$npay_style."_h"]	= $h;
			$sel_npay_btn_text[$npay_style]  = $style_text;
		}

		$this->template->assign(array(
				"visible"				=>$visible,
				"navercheckout"			=>$navercheckout,
				"sel_npay_btn_text"		=>$sel_npay_btn_text,
				"naver_wcs"				=>$naver_wcs,
				"naver_mileage"			=>$naver_mileage,
				"arrmarket"				=>$arrmarket,
				"marketing_image"		=>$marketing_image,
				"marketing_feed"		=>$marketing_feed,
				"marketing_sale"		=>$marketing_sale,
				"target_count"			=>$target_count,
				"daum_use"				=>$daum_use,
				"naver_use"				=>$naver_use, # EP 2.0
				"naver_third_use"		=>$naver_third_use, # EP 3.0
				"partner_info"			=>$partner_info,
				"pop_contants"			=>$contants,
				"npay_shipping"			=>$npay_shipping_group
		));

		$this->template->define(array('naverpay_desc'=>$this->skin."/marketing/naverpay_desc.html"));

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	// 네이버페이 결제형 설정
	public function naverpayment(){
		$filePath = $this->template_path();
		$naverpayment_config = config_load('naverpayment');

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($naverpayment_config);
		$this->template->print_("tpl");
	}

	/* 무통장설정 */
	public function bank(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_bank_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		###
		$this->load->model('usedmodel');
		$banks = $this->usedmodel->autodeposit_check();
		$this->template->assign(array('bankChk'=>$banks['chk'],'bankCount'=>$banks['count']));

		/* 계좌설정 정보 */
		$loop = config_load('bank');
		if(!$loop)$loop[0]['account'] = '';

		/* 반품배송비 입금계좌설정 정보 */
		$loop2 = config_load('bank_return');
		if(!$loop2)$loop2[0]['account'] = '';

		/* 무통장 입금확인 서비스 신청정보 :: 2015-10-19 lwh */
		$this->usedmodel->autodeposit_use_chk(); //페이지 열때 체크 19.01.08 kmj
		$auto_status	= $this->config_system['autodeposit_status'];
		if($auto_status == '0' || $auto_status == '1')
				$autodeposit_status = 'S';
		else	$autodeposit_status = 'F';
		$this->template->assign(array('autodeposit_status' => $autodeposit_status, 'status_code' => $auto_status));

		$this->load->library('bankda');
		$cid = $this->bankda->get_auth();

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign(array('cid' => $cid));
		$this->template->assign('loop',$loop);
		$this->template->assign('loop2',$loop2);
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

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_member_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}
		// setting_member_act

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

		$status = $realname['useIpin'] == "Y" ? "아이핀 사용" : "아이핀 미사용";

		if($status) $status .= ",";
		$status .= $realname['useRealnamephone'] == "Y" ? "휴대폰본인인증 사용" : "휴대폰본인인증 미사용";

		$arrBasic = ($this->config_basic)?$this->config_basic:config_load('basic');

		switch ($arrBasic['operating']){
			case 'general'	:	$operating_str = '일반';		break;
			case 'member'	:	$operating_str = '회원전용';	break;
			case 'adult'	:	$operating_str = '성인전용';	 break;
		}

		$member_config = config_load('member');
		$this->template->assign('dormancy',$member_config['dormancy']);

		$this->template->assign('operating',$arrBasic['operating']);
		$this->template->assign('operating_str',$operating_str);

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign('status',$status);

		if($realname) {
			if($this->isdemo['isdemo']) {
				$realname['ipinSikey'] = getstrcut($realname['ipinSikey'],0,'*********');
				$realname['realnameId'] = getstrcut($realname['realnameId'],0,'*********');
				$realname['ipinKeyString'] = getstrcut($realname['ipinKeyString'],0,'*********');
				$realname['realnamePwd'] = getstrcut($realname['realnamePwd'],0,'*********');
			}
			$this->template->assign($realname);
		}
		$this->template->print_("tpl");
	}

	### 회원설정 - 이용약관
	public function agreement(){

		$filePath	= $this->template_path();

		$this->template->define(array('tpl'=>$filePath));

		/**
		agreement			: 기존 이용약관 필드
		policy_agreement	: 신규 이용약관 필드
		기존 데이터가 있으면 기존 데이터로 노출. -> 저장 시 기존/신규 필드 동일하게 업데이트 함
		**/
		$this->load->helper('member');
		$member = chkPolicyInfo('admin');
		if($member){
			$this->template->assign($member);
		}
		$this->template->print_("tpl");
	}

	### 회원설정 - 개인정보처리
	public function privacy(){

		$filePath	= $this->template_path();

		$url = get_connet_protocol().$_SERVER['HTTP_HOST'];

		// 통합약관 업데이트일 이전일때 기존 데이터 불러오기
		$this->load->helper('member');
		$member = chkPolicyInfo('admin');
		if($member){
			$this->template->assign($member);
		}

		$this->template->assign(array('member_url'=>$url."/mypage/myinfo",'privacy_url'=>$url."/service/"));
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}
	### 회원설정 - 개인정보처리 - 개인정보 3자제공동의
	public function policy_third_party(){
		$CI =& get_instance();
		$this->admin_menu();
		$this->tempate_modules();
		$filePath = $this->template_path();

		if($CI->member) {
			if(serviceLimit('H_AD')){
				$policy_third_part = $CI->member['policy_third_party'];
			}else{
				$policy_third_part = $CI->member['policy_third_party_normal'];
			}

			$this->template->assign('policy_third_party', $policy_third_part);
		}
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	### 회원설정 - 개인정보처리 - 개인정보 3자제공동의 ajax
	public function ajax_third_party(){
		### 설정저장
		if(serviceLimit('H_AD')){
			$member_config = config_load('member','policy_third_party');
			echo $member_config['policy_third_party'];
		}else{
			$member_config = config_load('member','policy_third_party_normal');
			echo $member_config['policy_third_party_normal'];
		}
		exit;
	}

	### 회원설정 - 청약철회 관련 방침
	public function cancellation(){

		$filePath	= $this->template_path();

		$this->template->define(array('tpl'=>$filePath));
		// 통합약관 업데이트일 이전일때 기존 데이터 불러오기
		$this->load->helper('member');
		$member = chkPolicyInfo('admin');
		if($member){
			$this->template->assign($member);
		}

		$this->template->print_("tpl");
	}

	### 회원설정 - 개인정보 제공 동의
	public function policy(){
		$CI =& get_instance();
		$filePath	= $this->template_path();

		$this->template->define(array('tpl'=>$filePath));
		if($CI->member) $this->template->assign($CI->member);
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

		$snsinfo = array();
		$snsinfo['페이스북']= array("email"=>2,"name"=>1,"sex"=>1,"birthday"=>1,"nickname"=>0);
		$snsinfo['트위터'] = array("email"=>0,"name"=>0,"sex"=>0,"birthday"=>0,"nickname"=>0);
		$snsinfo['네이버']	= array("email"=>2,"name"=>2,"sex"=>2,"birthday"=>2,"nickname"=>2);
		$snsinfo['카카오']	= array("email"=>2,"name"=>0,"sex"=>0,"birthday"=>0,"nickname"=>1);
		$snsinfo['인스타그램']	= array("email"=>0,"name"=>0,"sex"=>0,"birthday"=>0,"nickname"=>1);
		$snsinfo['애플']	= array("email"=>0,"name"=>0,"sex"=>0,"birthday"=>0,"nickname"=>0);

		## sns 로그인&가입 관련
		$this->joinform_sns();

		$this->template->assign(array('snsinfo'=>$snsinfo));

		if( (str_replace("-","",$this->config_system['service']['setting_date']) < '20121009') ){
			$this->template->assign('service_setting_date_ck', true);
		}

		$find_idpass = config_load('find_idpass');
		$this->template->assign($find_idpass);


		// o2o 설정 관리자 회원 추가 입력 값 추가
		$this->load->library('o2o/o2orequiredlibrary');
		$this->o2orequiredlibrary->required_admin_member_joinform();

		$this->template->define(array('tpl'=>$filePath,'sns_setting'=>$this->skin."/setting/joinform_sns.html"));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}


	public function joinform_sns(){

		// 각 sns 마다 쿼리 날리는 것이 비효율적으로 판단되어 한번에 가져오는 쿼리로 변경 :: 2020-02-25 pjw
		$sns_count_query	= $this->db->query("SELECT A.rute, COUNT(*) AS total FROM fm_membersns A LEFT JOIN fm_member B ON A.member_seq = B.member_seq WHERE B.status = 'done' GROUP BY A.rute ORDER BY NULL ");
		$sns_member_count	= $sns_count_query->result_array();

		// 아래 총 지원하는 sns 목록이며, 각 sns 별 앞글자만 따서 변수처리를 하므로 동적으로 구성해준다
		// e.g. $this->arrSns['total_f'] = facebook값
		// 'facebook', 'twitter', 'me2day', 'yozm', 'cyworld', 'google+', 'mypeople', 'naver', 'kakao', 'daum', 'instagram', 'apple'
		foreach($sns_member_count as $snstotal){
			$total_key					= 'total_'.substr($snstotal['rute'], 0, 1);
			$this->arrSns[$total_key]	= ($snstotal['total']);
		}

		if($this->isdemo['isdemo']) {
			$this->arrSns['key_m'] = getstrcut($this->arrSns['key_m'],0,'*********');
			$this->arrSns['key_c'] = getstrcut($this->arrSns['key_c'],0,'*********');
			$this->arrSns['secret_c'] = getstrcut($this->arrSns['secret_c'],0,'*********');
		}

		# 네아로 클라이언트ID 또는 Secret Key 값이 없을때 서비스 저장정보 초기화.
		if(!$this->arrSns['nid_client_id'] || !$this->arrSns['nid_client_secret']){
			$this->arrSns['nid_client_id']		= '';
			$this->arrSns['nid_client_secret']	= '';
			$this->arrSns['nid_client_url']		= '';
			$this->arrSns['nid_service_name']	= '';
			$this->arrSns['nid_client_name']	= '';
			$this->arrSns['nid_icon_url']		= '';
		}
		if($this->arrSns['nid_client_id'] || $this->arrSns['nid_client_secret']){
			$this->arrSns['key_n']		= '';
			$this->arrSns['secret_n']	= '';
		}else{
			$this->arrSns['nid_client_id']		= $this->arrSns['key_n'];
			$this->arrSns['nid_client_secret']	= $this->arrSns['secret_n'];
		}

		$this->arrSns['nid_icon_noimg'] = "/data/icon/common/nid_noimg.gif";

		if(strstr($this->arrSns['nid_icon_url'],"nid_noimg")){
				$this->arrSns['nid_icon_url'] = "";
		}

		if($this->arrSns['nid_client_url']){
			$nid_client_url = json_decode($this->arrSns['nid_client_url']);
			if(is_object($nid_client_url)){
				$nid_client_url = (array)$nid_client_url;
				if(is_array($nid_client_url['pcweb'])){
					$nid_client_url_pcweb = $nid_client_url['pcweb'][0];
				}else{
					$nid_client_url_pcweb = $nid_client_url['pcweb'];
				}
				if(is_array($nid_client_url['mobileweb'])){
					$nid_client_url_mobileweb = $nid_client_url['mobileweb'][0];
				}else{
					$nid_client_url_mobileweb = $nid_client_url['mobileweb'];
				}
				$this->arrSns['nid_client_url_web']		= str_replace("http://","",str_replace("https://","",$nid_client_url_pcweb));
				$this->arrSns['nid_client_url_mobile']	= str_replace("http://","",str_replace("https://","",$nid_client_url_mobileweb));
			}
		}

		# SNS별 가입및 로그인 선택 레이어창 타이틀 아이콘
		$this->arrSns['facebook_logo']	= "<img src='/admin/skin/default/images/sns/sns_f0.gif' alt='facebook' align='absmiddle' />";
		$this->arrSns['nid_logo']		= "<img src='/admin/skin/default/images/sns/sns_n0.gif' alt='naver' align='absmiddle' />";
		$this->arrSns['kakao_logo']		= "<img src='/admin/skin/default/images/sns/sns_k0.gif' alt='kakao' align='absmiddle' />";
		$this->arrSns['daum_logo']		= "<img src='/admin/skin/default/images/sns/sns_d0.gif' alt='daum' align='absmiddle' />";
		$this->arrSns['twitter_logo']	= "<img src='/admin/skin/default/images/sns/sns_t0.gif' alt='twitter' align='absmiddle'/>";
		$this->arrSns['instagram_logo']	= "<img src='/admin/skin/default/images/sns/sns_i0.gif' alt='instagram' align='absmiddle'/>";
		$this->arrSns['apple_logo']		= "<img src='/admin/skin/default/images/sns/sns_a0.gif' alt='apple' align='absmiddle'/>";

		$this->template->assign(array('sns'=>$this->arrSns));

	}


	### 회원설정 - 승인/혜택
	public function approval(){
		$filePath	= $this->template_path();
		$tmp = config_load('member');

		$tmp_joinform = config_load('joinform');
		if($tmp_joinform) $this->template->assign($tmp_joinform);

		$this->template->assign(array('sns'=>$this->arrSns));

		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}

	public function split_date($date)
	{
		return substr($date,0,4)."년 ".substr($date,5,2)."월 ".substr($date,8,2)."일";
	}

	public function calculate_date($start_month,$chg_day,$chg_term,$chk_term,$keep_term){

		$this->load->model('membermodel');
		$data = $this->membermodel->calculate_date($start_month,$chg_day,$chg_term,$chk_term,$keep_term,'setting');

		$tmp_date = array();
		foreach($data['chg_text'] as $date)
			$tmp_date[] = $this->split_date($date);
		$result['chg_text'] = implode("<br/>",$tmp_date);

		$tmp_date = array();
		foreach($data['chk_text_start'] as $k=> $date)
			$tmp_date[] = $this->split_date($date)." ~ ".$this->split_date($data['chk_text_end'][$k]);
		$result['chk_text'] = implode("<br/>",$tmp_date);

		$tmp_date = array();
		foreach($data['keep_text_start'] as $k=> $date)
			$tmp_date[] = $this->split_date($date)." ~ ".$this->split_date($data['keep_text_end'][$k]);
		$result['keep_text'] = implode("<br/>",$tmp_date);

		$result['next_grade_date'] = $this->split_date($data['next_grade_date']);

		return $result;
	}

	public function grade_ajax()
	{
		$start_month = $_GET['start_month'];
		$chg_day = $_GET['chg_day'];
		$chg_term = $_GET['chg_term'];
		$chk_term = $_GET['chk_term'];
		$keep_term = $_GET['keep_term'];

		$result = $this-> calculate_date($start_month,$chg_day,$chg_term,$chk_term,$keep_term);
		echo json_encode($result);
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

		$this->load->model('membermodel');
		$list = $this->membermodel->find_group_cnt_list();
		$totalcount = $this->membermodel->get_item_total_count();

		$grade_clone = config_load('grade_clone');

		$grade_clone['chg_text'] = "";
		$grade_clone['chk_text'] = "";
		$grade_clone['keep_text'] = "";
		$next_grade_date = "";
		$month = $grade_clone['start_month'] ? $grade_clone['start_month'] : '1';

		if($grade_clone['chg_day']){
			$result = $this->calculate_date($month,$grade_clone['chg_day'],$grade_clone['chg_term'],$grade_clone['chk_term'],$grade_clone['keep_term']);
		}

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign('result',$result);
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
				break;
			case "chk":
				$month = $month - $alpha - 1;
				$month = $month + $prv;
				if($month<1) $month = 36 + ($month);
				break;
			case "calcu":
				$month += $alpha;
				break;
		}
		$month = $month % 12;
		if($month == 0) $month  = 12;
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
				$this->load->model('membermodel');
				$list = $this->membermodel->find_group_list();
				if(count($list)>3){
					$callback = "parent.formMove('grade',4);";
					openDialogAlert("더 이상 생성하실 수 없습니다.",400,150,'parent',$callback);
					exit;
				}
			}
		}

		// mypage icon 추가 :: 2019-09-03 pjw
		$icons		= find_icons();
		$myicons	= find_icons('mypage');

		// 기본이미지 목록 추가 :: 2019-12-03 pjw
		$default_icons = array(
			'icon_grade01.gif',
			'icon_grade02.gif',
			'icon_grade03.gif',
			'icon_grade04.gif',
			'icon_grade05.gif',
			'icon_grade06.gif',
		);


		###
		$this->db->where('group_seq', $_GET['group_seq']);
		$query = $this->db->get('fm_member_group');
		foreach ($query->result_array() as $row){
			if(preg_match('/a:/',$row['order_sum_use'])) $row['order_sum_arr'] = unserialize($row['order_sum_use']);
			$returnArr[] = $row;
		}

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
		if($icons) $this->template->assign('default_icons',$default_icons);
		if($icons) $this->template->assign('icons',$icons);
		if($icons) $this->template->assign('myicons',$myicons);
		if($returnArr) $this->template->assign('data',$returnArr[0]);
		$this->template->print_("tpl");
	}
	### 회원설정 - 로그아웃/탈퇴/재가입
	public function withdraw(){
		$filePath	= $this->template_path();
		$tmp = config_load('member');
		$realname = config_load('realname');
		if(!$tmp['modifyPW']) $tmp['modifyPW']= "N";
		if(!$tmp['modifyPWMin']) $tmp['modifyPWMin']= "90";

		$auth = config_load('master','sms_auth'); // 보안키
		$sms_api_key = $auth['sms_auth'];
		$send_phone = getSmsSendInfo(); // 발신번호인증
		// 보안키 및 발신번호 미인증시 처리
		if($sms_api_key && $send_phone){
			$sms_st = 'Y';
		}else{
			if(!$send_phone)	$sms_st = '2';
			if(!$sms_api_key)	$sms_st = '1';
		}

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign('sms_st',$sms_st);
		if($tmp) $this->template->assign($tmp);
		if($realname) $this->template->assign($realname);
		$this->template->print_("tpl");
	}

	/* 주문설정 */
	public function order(){
		$this->admin_menu();
		$this->tempate_modules();
		$orders = config_load('order');

		// O2O 미매칭 관련 설정 추가
		$this->load->library('o2o/o2orequiredlibrary');
		$this->o2orequiredlibrary->required_admin_setting_order();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_order_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('buy_confirm');
		if(!$result['type']){
			$this->template->assign('buy_confirm_service_limit','Y');
			$orders['buy_confirm_use'] = 0;
		}
		$result = $this->usedmodel->used_service_check('multi_shipping');
		if(!$result['type']){
			$this->template->assign('multi_shipping_service_limit','Y');
		}

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

		$qry = "select * from fm_return_reason where return_type='coupon' order by idx asc";
		$query = $this->db->query($qry);
		$reasoncouponLoop = $query -> result_array();
		$this->template->assign('reasoncouponLoop',$reasoncouponLoop);

		$qry = "select * from fm_return_reason where return_type!='coupon' order by idx asc";
		$query = $this->db->query($qry);
		$reasonLoop = $query -> result_array();
		$this->template->assign('reasonLoop',$reasonLoop);

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		$this->template->assign(array('scm_cfg' => $this->scm_cfg));

		// 기본배송국가
		foreach(code_load('shipping_nation') as $data_code){
			$shipping_nation_codes[$data_code['codecd']] = $data_code['value'];
		}
		$this->template->assign('shipping_nation_codes',$shipping_nation_codes);
		$this->template->assign('cfg_default_nation',$this->config_system['cfg_default_nation']);

		$this->template->define(array('order_process'=>$this->skin."/setting/_order_process.html"));
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

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_sale_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		###
		$domain = str_replace("www.","",$_SERVER["SERVER_NAME"]);
		$pattern = array(".com",".net",".org",".biz",".info",".name",".kr",".co.kr",".or.kr",".pe.kr",".asia",".me",".cc",".cn",".tv",".in",".tw",".mobi");
		preg_match('/[a-z\d\-]+('.implode("|",$pattern).')/i',$domain, $match);
		$resDomain = $match[0];
		$this->template->assign('domain',$resDomain);

		if($this->isdemo['isdemo']){
			$this->config_system['webmail_domain'] = getstrcut($this->config_system['webmail_domain'],0,'*********');
			$this->config_system['webmail_admin_id'] = getstrcut($this->config_system['webmail_admin_id'],0,'*********');
			$this->config_system['webmail_key'] = getstrcut($this->config_system['webmail_key'],0,'*********');
		}

		$this->template->assign('webmail_admin_id', $this->config_system['webmail_admin_id']);
		$this->template->assign('webmail_domain', $this->config_system['webmail_domain']);
		$this->template->assign('webmail_key', $this->config_system['webmail_key']);

		$this->template->assign('hiworks_request', $this->config_system['hiworks_request']);

		## 현금영수증 의무 발행 START
		// 기본 10만원 이상시 의무발행
		$cashreceiptautoprice = $orders['cashreceiptautoprice'];
		if( $cashreceiptautoprice == "" ) $cashreceiptautoprice = 100000;

		$orders['cashreceiptautoprice'] = $cashreceiptautoprice;
		## 현금영수증 의무 발행 END

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));

		$this->template->assign($orders);
		$this->template->print_("tpl");
	}

	/* 주문설정 */
	public function reserve(){
		$this->admin_menu();
		$this->tempate_modules();
		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		$filePath	= $this->template_path();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_reserve_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		if(!$reserves['point_use']) $reserves['point_use'] = "N";
		if(!$reserves['cash_use']) $reserves['cash_use'] = "N";
		if(!$reserves['default_point_type']) $reserves['default_point_type'] = "per";
		if($reserves['reserve_year']=='') $reserves['reserve_year'] = 0;
		if($reserves['point_year']=='') $reserves['point_year'] = 0;
		if(!$reserves['reserve_direct']) $reserves['reserve_direct'] = "24";
		if(!$reserves['point_direct']) $reserves['point_direct'] = "24";

		$orders = config_load('order');

		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($reserves);
		$this->template->assign($orders);
		$this->template->print_("tpl");
	}

	/* 배송그룹 리스트 :: 2016-05-20 lwh */
	public function shipping_group(){
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
			$provider_seq	= $_GET['provider_seq'];
			if(!$provider_seq)
				$provider_seq = 1;
		}

		$sc = $this->input->get();
		$sc['provider_seq'] = $provider_seq;
		if(!$sc['page']) $sc['page'] = 1;

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

		if($provider_seq > 1){
			$viewParam['provider_seq']	= $_GET['provider_seq'];
			$viewParam['provider_name']	= $_GET['provider_name'];
			$this->template->assign('view',$viewParam);
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
		$aGetParams = $this->input->get();
		$admin_shipping_group = false; // 본사 배송그룹인지 확인하기 위한 flag값 추가
		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('shipping_group_seq', '일련번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('provider_seq', '입점사', 'trim|numeric|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$this->admin_menu();
		$this->tempate_modules();
		$filePath = $this->template_path();

		$this->load->model('shippingmodel');

		// 검색 params
		$sc = $_GET;

		if (defined('__ADMIN__') === true)	{
			$provider_seq = 1;
			$admin_shipping_group = true;
		}

		if($aGetParams['provider_seq'])			$provider_seq = $aGetParams['provider_seq'];

		if(!$provider_seq || ($provider_seq != 1 && !serviceLimit('H_AD'))){
			echo "<script type='text/javascript'>alert('잘못된 접근입니다.');history.back(-1);</script>";
			exit;
		}

		//임시 seq 부여
		$shipping_group_dummy		= $this->shippingmodel->set_shipping_dummy($aGetParams['shipping_group_seq']);
		$shipping_group_dummy_seq	= $shipping_group_dummy['shipping_group_dummy_seq'];
		$shipping_group_seq			= $shipping_group_dummy['shipping_group_dummy_seq'];
		$shipping_calcul_type		= $shipping_group_dummy['shipping_calcul_type'];

		// 수정시
		if($aGetParams['shipping_group_seq']){
			// 배송그룹 호출
			$grp_info = $this->shippingmodel->get_shipping_group($shipping_group_seq);
			$grp_info['shipping_calcul_type'] = $shipping_calcul_type;
			// 요약정보 추출
			$grp_summary = $this->shippingmodel->get_shipping_group_summary($aGetParams['shipping_group_seq']);
			$this->template->assign("grp_summary",$grp_summary);

			// 임점사명 추출
			if($grp_info['shipping_provider_seq']){
				$this->load->model('providermodel');
				$provider = $this->providermodel->get_provider_one($grp_info['shipping_provider_seq']);
				$grp_info['provider_name'] = $provider['provider_name'];
			}
			unset($sc['shipping_group_seq']);

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

		// 네이버페이 이용시 안내 추가 :: 2017-02-16 lwh
		$this->template->define(array('naverpay_desc'=>$this->skin."/marketing/naverpay_desc.html"));

		// 자동안내설명 스킨
		$this->template->define(array('delivery_desc' => $this->skin.'/setting/add_national_delivery_desc.html'));

		//임시 데이터 메모의 관리자 이름 삭제
		$grp_info['admin_memo'] = explode("||", $grp_info['admin_memo']);
		$grp_info['admin_memo'] = $grp_info['admin_memo'][1];

		$this->template->assign("shipping_group_dummy_seq", $shipping_group_dummy_seq);
		$this->template->assign("info_msg",$info_msg);
		$this->template->assign("language",$language);
		$this->template->assign("ship_grp",$grp_info);
		$this->template->assign("sc",$sc);
		$this->template->assign("admin_shipping_group",$admin_shipping_group);
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
		if($this->input->post('mode') == 'modify'){
			$post_data = $this->input->post();
			$key = $this->input->post('idx') - 1;
			foreach($post_data as $col => $pdata){
				if(is_array($pdata[$this->input->post('nation')]) === true){
					$params[$col] = $pdata[$this->input->post('nation')][$key];
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
				$tmp_arr = unserialize($params['limit_day_serialize'], ['allowed_classes' => false]);
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
					$params['store_list_arr'][$i]['store_scm_use'] = $params['store_scm_use'][$i];
					$params['store_list_arr'][$i]['store_type'] = $params['store_type'][$i];
					$params['store_list_arr'][$i]['store_scm_seq'] = $params['store_scm_seq'][$i];
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

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		$this->template->assign("scm_cfg",$this->scm_cfg);
		$this->template->assign("optTypeArr",$optTypeArr);
		$this->template->assign("params",$params);
		$this->template->assign("punit",$this->config_system['basic_currency']);
		$this->template->assign("calcul_type_tit",$calcul_type_tit);
		$this->template->assign("ship_set_code",$ship_set_code);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	/* 배송설정 :: 기존 구버전 배송설정 삭제예정 */
	public function shipping(){

		$provider_seq = isset($_GET['provider_seq'])?$_GET['provider_seq']:1;

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('shipping');
		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		## 현태택배업무자동화서비스 세팅값 :: 2015-07-10
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
					$address = $provider_info['info_address1'] . ' ' . $provider_info['info_address2'];
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
		}else{
			$goodsflow_deli = $this->goodsflowmodel->delivery_set();
			config_save('goodsflow',array('terms'=>$goodsflow_deli));
			$this->config_goodsflow['terms'] = config_load('goodsflow','terms');
		}

		//5자리 우편번호 양식(6자리 우편번호도 "-"  없음)
		$this->config_goodsflow['setting']['goodsflowNewZipcode']	= implode('', (array)$this->config_goodsflow['setting']['goodsflowZipcode']);

		$this->template->assign("config_goodsflow",$this->config_goodsflow);
		$this->template->assign("service_cnt",$service_cnt);

		$this->load->model('providershipping');
		if($provider_seq) $data_providershipping = $this->providershipping->get_provider_shipping($provider_seq);

		$this->template->assign("data_providershipping",$data_providershipping);

		$filePath	= $this->template_path();
		$setting_shipping_act = 0;
		if( !isset($_GET['provider_seq']) ){
			$this->admin_menu();
			$this->tempate_modules();

		}else{
			$filePath = str_replace('setting/shipping.html','provider/provider_shipping.html',$filePath);
			if(strpos($this->managerInfo['manager_auth'],'setting_shipping_act=N')) $setting_shipping_act = 1;
		}

		$addDeliveryType = config_load('adddelivery', 'addDeliveryType');
		$this->template->assign("addDeliveryType",$addDeliveryType['addDeliveryType']);

		$this->template->assign("setting_shipping_act",$setting_shipping_act);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign("provider_seq",$provider_seq);
		$this->template->assign("loop",$loop);
		$this->template->print_("tpl");
	}
	// 국내 배송 수정
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

		 	if( isset($data['deliveryCompanyCode']) ){
				foreach( $data['deliveryCompanyCode'] as $deliveryCompanyCode ){
					$tmp = config_load('delivery_url',$deliveryCompanyCode);
					$data['deliveryCompany'][$deliveryCompanyCode] = $tmp[$deliveryCompanyCode]['company'];
				}
			}
		}

		foreach(get_invoice_company() as $k=>$data_auto){
			$data['deliveryCompany'][$k] = $data_auto['company'];
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

		$cfg_system	= ($this->config_system) ? $this->config_system : config_load('system');

		// 인증서 관련 라이브러리 추가
		$this->load->library('ssllib');

		$this->template->assign(array(
			'cfg_system'	=> $cfg_system,
			'protectIp'		=> $protectIp,
			'ssllib'			=> $this->ssllib
		));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 이미지호스팅 계정 설정 */
	public function imagehosting(){
		$this->admin_menu();
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_imagehosting_view');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		$auth_act = $this->authmodel->manager_limit_act('setting_imagehosting_act');
		$this->template->assign(array('imagehosting_act' => $auth_act));
		/* 관리자 권한 체크 : 끝 */

		// 상단 이미지호스팅 홍보 배너: 중앙에서 배너 관리
		$marketing_imagehosting_banner = getGabiaPannel('marketing_imagehosting_banner');
		$this->template->assign(array(
			'marketing_imagehosting_banner' => $marketing_imagehosting_banner	
		));

		$this->load->model("imagehosting");
		$data = $this->imagehosting->get_imagehostingList();
		if(isset($data)){
			foreach($data as $idx => $row){
				$data[$idx]['_no'] = ($idx+1);
			}
			$this->template->assign(array('loop' => $data));
		}

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 이미지호스팅 계정 설정 */
	public function imagehosting_setting(){
		$this->tempate_modules();
		$aGetParams = $this->input->get();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_imagehosting_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,140,'parent','parent.location.reload();');
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		/* 수정: 이미지호스팅 계정 기존 정보 가져오기 */
		if($aGetParams['imagehosting_seq']!=''){
			$this->load->model("imagehosting");
			$data = $this->imagehosting->get_imagehostingEach($aGetParams['imagehosting_seq']);
			if(isset($data)){
				$get_protocol = explode("://", $data['store_url']);
				$data['store_url_protocol'] = ($get_protocol[0] == "https") ? 0: 1;
				$data['store_url'] = $get_protocol[1];
				
				//비밀번호 정보 초기화
				for($n=0; $n<10; $n++){
					$temporary_password .= random_int(1, 9);
				}
				$data['store_password'] = $temporary_password; // 자리수 동일한 임시비밀번호
				$this->template->assign($data);
			}
		}

		$this->template->define(array('tpl'=> $this->skin.'/setting/_imagehosting_setting.html'));
		$this->template->print_("tpl");
	}

	/* 기능별 저장소 설정 */
	public function imagestore(){
		$this->admin_menu();
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_imagehosting_view');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		$auth_act = $this->authmodel->manager_limit_act('setting_imagehosting_act');
		$this->template->assign(array('imagehosting_act' => $auth_act));
		/* 관리자 권한 체크 : 끝 */
		
		// 기능별 저장소 데이터 가져오기
		$this->load->model("imagehosting");
		$data = $this->imagehosting->get_imagestoreList();

		// 이미지 호스팅 계정 정보
		$imagehostingList = $this->imagehosting->get_imagehostingList();
		$this->template->assign(array('imagehostingList' => $imagehostingList));

		// 구분, 항목명 가져오기
		$this->config->load('imageStoreSet');
		$imagestore_division = $this->config->item('imagestore_division');
		$imagestore_division_rowspan = $this->config->item('imagestore_division_rowspan');
		$imagestore_item = $this->config->item('imagestore_item');
		$imagestore_item_default = $this->config->item('imagestore_item_default');
		$imagestore_directory = $this->config->item('imagestore_directory');

		if(isset($data)){
			foreach($data as $idx => $row){
				if(in_array($row['imagestore_item'], $imagestore_item_default)){ //대분류 기준 값에만 구분명, rowspan 값 설정
					$data[$idx]['imagestore_division_title'] = $imagestore_division[$row['imagestore_division']];
					$data[$idx]['imagestore_division_rowspan'] = $imagestore_division_rowspan[$row['imagestore_division']];
				}
				$data[$idx]['imagestore_item_title'] = $imagestore_item[$row['imagestore_item']];
				$data[$idx]['imagestore_directory'] = $imagestore_directory[$row['imagestore_item']];
				$data[$idx]['imagehostingList'] = $imagehostingList;

				// 반응형/전용 스킨에 따라 설명 가져오기
				if(in_array($row['imagestore_item'], array('all_navigation', 'slide_banner', 'event_banner'))){
					$data[$idx]['imagestore_directory'] = $imagestore_directory[$row['imagestore_item']."_".$this->config_system['operation_type']];
				}

				// 입점몰 여부에 따라 상품등록(입점사) 초기화
				if (!serviceLimit('H_AD') && $row['imagestore_division']== 'goods_provider') {
					unset($data[$idx]);
				}

				// 입점몰 여부에 따른 본사/입점사 구분 노출
				if (serviceLimit('H_AD') && $row['imagestore_division']== 'goods_headquaters') {
					$data[$idx]['imagestore_directory'] = str_replace("%입점사번호%", "1", $data[$idx]['imagestore_directory']);
				}
			}
			$this->template->assign(array('loop' => $data));
		}

		// 기능별 저장소 로그 가져오기
		$this->load->library('actionhistorylibrary');
		$param['category'] = ["image_store", "image_url"];
		$log_list = $this->actionhistorylibrary->getHistorys($param);
		$image_store_flag = $image_url_flag = false; // 첫번째 로그인지 체크
		foreach($log_list['historys'] as $idx => $log){

			// templete: %년%-%월%-%일% %시%:%분%:%초% %관리자명%(%관리자아이디%)가 설정을 변경하였습니다.(%아이피%) - %구분% > %항목%:%변경후설정%(%저장소명%)
			$imagestoreLog = $log['regist_date']." ".$log['mname']."(".$log['manager_id'].")가 ".$log['action']."(".$log['regist_ip'].") - ".$log['detail'];

			// 저장소 설정 로그
			if($log['category']=="image_store") {
				if($image_store_flag) $image_store_log .="\n";
				$image_store_log .= $imagestoreLog;
				$image_store_flag = true;
			}
			// 도메인 일괄 변경 로그
			if($log['category']=="image_url") {
				if($image_url_flag) $image_url_log .="\n";
				$image_url_log .= $imagestoreLog;
				$image_url_flag = true;
			}
			
		}
		$this->template->assign(array('image_store_log' => $image_store_log));
		$this->template->assign(array('image_url_log' => $image_url_log));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	/* 기능별 저장소 도메인 일괄 변경 */
	public function imagestore_setting(){
		$this->tempate_modules();
		$aGetParams = $this->input->get();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_imagehosting_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		// 이미지 호스팅 계정 정보
		$this->load->model("imagehosting");
		$imagehostingList = $this->imagehosting->get_imagehostingList();

		// 기능별 저장소 데이터 가져오기
		$this->load->model("imagehosting");
		$data = $this->imagehosting->get_imagestoreList();
		$target_imagestore = $data[$aGetParams['imagestore_seq']-1];

		// 구분, 항목명 가져오기
		$this->config->load('imageStoreSet');
		$imagestore_division_list = $this->config->item('imagestore_division');
		$imagestore_division = strip_tags($imagestore_division_list[$target_imagestore['imagestore_division']]);
		$imagestore_item_list = $this->config->item('imagestore_item');
		$imagestore_item = $imagestore_item_list[$target_imagestore['imagestore_item']];

		// 저장소 경로 가져오기
		$imagestore_directory_list = $this->config->item('imagestore_directory');
		$imagestore_directory = $imagestore_directory_list[$target_imagestore['imagestore_item']];
		// 반응형/전용 스킨에 따라 설명 가져오기
		if(in_array($target_imagestore['imagestore_item'], array('all_navigation', 'slide_banner', 'event_banner'))){
			$imagestore_directory = $imagestore_directory_list[$target_imagestore['imagestore_item']."_".$this->config_system['operation_type']];
		}
		// 입점몰 여부에 따른 본사/입점사 구분 노출
		if (serviceLimit('H_AD') && $target_imagestore['imagestore_division']== 'goods_headquaters') {
			$imagestore_directory = str_replace("%입점사번호%", "1", $imagestore_directory);
		}
		foreach(explode("/", $imagestore_directory) as $dKey => $dValue){
			if($dKey == 1)$imagehostingList[0]['store_url'] = "/". $dValue;
			if($dKey == 2)$imagehostingList[0]['store_dir'] = $dValue;
		}

		$this->template->assign(array('imagehostingList' => $imagehostingList));
		$this->template->assign(array('imagestore_division' => $imagestore_division));
		$this->template->assign(array('imagestore_item' => $imagestore_item));
		$this->template->assign(array('imagestore_seq' => $aGetParams['imagestore_seq']));
		$this->template->define(array('tpl'=> $this->skin.'/setting/_imagestore_setting.html'));
		$this->template->print_("tpl");
	}

	/* 캐시 설정 */
	public function cache()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$this->template->define(array('tpl' => $this->template_path()));
		$this->template->print_("tpl");
	}

	/* 캐시 설정 */
	public function cacheMainAjax(){
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_protect_view');
		if(!$auth){
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		if(!$this->config_system['skinCache'])		  $this->config_system['skinCache']		   = "n";
		if(!$this->config_system['mobileSkinCache'])	$this->config_system['mobileSkinCache']	 = "n";
		if(!$this->config_system['fammerceSkinCache'])  $this->config_system['fammerceSkinCache']   = "n";

		$this->load->library('cachemain');
		$sTemplateDir = str_replace(array('//','admin'), array('/','data'), $this->template->template_dir);

		if($this->config_system['operation_type'] == 'light') {
			$sSkinPath  = 'main/cache_main.html';
			$this->cachemain->cache_file_path = $sTemplateDir."/".$this->config_system['skin'].'/';
			$this->cachemain->set_cache_file($sSkinPath);
			$filetime = $this->cachemain->check_cache_filetime();
			$aMainDisplay[] = array(
				'platform'	  => 'responsive',
				'skin_name'	 => $this->config_system['skin'],
				'skin_cache'	=> $this->config_system['skinCache'],
				'file_time'	  => $filetime,
				'cache_able'	=> 1
			);
		}else{
			$sSkinPath  = 'main/cache_main.html';
			$this->cachemain->cache_file_path = $sTemplateDir."/".$this->config_system['skin'].'/';
			$this->cachemain->set_cache_file($sSkinPath);
			$filetime = $this->cachemain->check_cache_filetime();
			$aMainDisplay[] = array(
				'platform'	  => 'pc',
				'skin_name'	 => $this->config_system['skin'],
				'skin_cache'	=> $this->config_system['skinCache'],
				'file_time'	  => $filetime,
				'cache_able'	=> 1
			);

			$this->cachemain->cache_file_path = $sTemplateDir."/".$this->config_system['mobileSkin'].'/';
			$this->cachemain->set_cache_file($sSkinPath);
			$filetime   = $this->cachemain->check_cache_filetime();
			$aMainDisplay[] = array(
				'platform'   => 'mobile',
				'skin_name'  => $this->config_system['mobileSkin'],
				'skin_cache' => $this->config_system['mobileSkinCache'],
				'file_time'  => $filetime,
				'cache_able'  => 1
			);
			$this->cachemain->cache_file_path = $sTemplateDir."/".$this->config_system['fammerceSkin'].'/';
			$this->cachemain->set_cache_file($sSkinPath);
			$filetime   = $this->cachemain->check_cache_filetime();
			$aMainDisplay[] = array(
				'platform'   => 'fammerce',
				'skin_name'  => $this->config_system['fammerceSkin'],
				'skin_cache' => $this->config_system['fammerceSkinCache'],
				'file_time' => $filetime,
				'cache_able' => $this->arrSns['use_f']
			);
		}
		$this->template->assign(array('main_display_list'	=> $aMainDisplay));
		$this->template->define(array('tpl'			 => $this->template_path()));
		$this->template->print_("tpl");
	}

	/* 캐시 설정 */
	public function cacheDisplayAjax(){
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('ssl');
		$this->load->model('goodsdisplay');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_protect_view');
		if(!$auth){
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		## 상품 디스플레이 목록
		$perpage	= 100;
		$page			   = $this->input->get('page');
		$platform		   = $this->input->get('platform');
		$keyword			= $this->input->get('keyword');
		$auto_generation	= $this->input->get('auto_generation');
		$cache_use		  = $this->input->get('cache_use');
		$search_favorite	= $this->input->get('search_favorite');

		$aParams = $aSearchParams  = '';
		if( $platform )		 $aParams['dd.platform']		   = $platform;
		if( $auto_generation )  $aParams['ddt.auto_generation']   = $auto_generation;
		if( $cache_use )		$aParams['ddt.cache_use']		 = $cache_use;
		if( $search_favorite )  $aParams['ddt.favorite']		  = $search_favorite;
		if( $keyword ){
			$aSearchParams['dd.admin_comment']	  = $keyword;
			$aSearchParams['dd.display_seq']		= $keyword;
		}
		$sql	= $this->goodsdisplay->get_display_tab_list_sql($aParams, $aSearchParams);
		$display_list = select_script_page($perpage, $page, 10, $sql, array(), 'goCachePage');
		$this->userInfo['group_seq']	= 0;
		foreach($display_list['record'] as $sKey => $aData){
			// 모바일전용 ver3 이상
			if($this->realMobileSkinVersion >  2 && $aData['platform']=='mobile' && ($aData['style']=='newswipe' || $aData['style']=='sizeswipe')){
				$aData['count_w'] = $aData['count_w_swipe'];
				$aData['count_h'] = $aData['count_h_swipe'];
			}
			// 모바일전용 스와이프형 일때 ver2 이하
			if($this->realMobileSkinVersion < 3 && $aData['platform']=='mobile' && $aData['style']=='newswipe'){
				$aData['count_w'] = $aData['count_w_swipe'];
				$aData['count_h'] = $aData['count_h_swipe'];
			}
			$sPerPage   = $aData['count_w'] * $aData['count_h'];
			$sCacheFile = $this->goodsdisplay->checkDesignDisplayCach($aData['display_seq'], $aData['display_tab_index'], $sPerPage, $aData['kind'], 'date');
			$display_list['record'][$sKey]['perpage']	   = $sPerPage;
			$display_list['record'][$sKey]['cache_file']	= $sCacheFile;
		}
		$this->template->assign(array('display_list'	=> $display_list));
		$this->template->define(array('tpl'			 => $this->template_path()));
		$this->template->print_("tpl");
	}

	/* 관리자 */
	public function manager(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		# reset
		$manager_seqs = array();

		if($this->managerInfo['manager_yn'] != 'Y'){
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
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'manager_seq';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):'10';
		$data = $this->membermodel->admin_manager_list($sc);

		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_manager');
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$manager_seq = $datarow['manager_seq'];
			$datarow['type']	= $datarow['business_seq'] ? '기업' : '개인';
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			if ($datarow['lastlogin_date']=="0000-00-00 00:00:00") {
				$datarow['lastlogin_date'] = "";
			}
			$dataloop[$manager_seq] = $datarow;
		}

		# 권한추가
		$wheres['shopSno']		= $this->config_system['shopSno'];
		$wheres['manager_seq']	= array_keys($dataloop);
		$wheres['codecd not like'] = '%_priod_%';
		$orderbys['idx'] 		= 'asc';
		$query_auth	= $this->authmodel->select('*',$wheres,$orderbys);
		foreach($query_auth->result_array() as $data_auth){
			$manager_seq = $data_auth['manager_seq'];
			$auth_codecd = $data_auth['codecd'];
			$auth_value = $data_auth['value'];

			array_key_exists($manager_seq, $dataloop) && $dataloop[$manager_seq]['auth'][$auth_codecd] = $auth_value;
		}

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

		$auto_logout = config_load('autoLogout');
		$this->template->assign($auto_logout);

		$chatbot = config_load('chatbot');
		$this->template->assign($chatbot);

		$this->template->assign('use_manager_cnt',$data['count']);

		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	public function manager_reg(){
		$this->load->model('authmodel');
		$this->load->model('managermodel');
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();
		$iManagerSeq = $this->input->get('manager_seq');

		// 21.05.10 lsh 로그인한 관리자가 대표관리자가 아니고 자신의관리자정보페이지가 아닐 경우
		if ($this->managerInfo['manager_yn'] != "Y") {
			$managerSeq = $this->input->get("manager_seq");
			if (!isset($managerSeq) || $this->managerInfo['manager_seq'] != $managerSeq) {
				pageBack("권한이 없습니다.");
				exit;
			}
		}

		$num_menu_count = 0;
		$rowspan_menu_count = 0;
		if (!$this->scm_cfg) {
			$this->scm_cfg	= config_load('scm');
		}
		if ($this->scm_cfg['use'] == 'Y') { // 올인원일 경우
			$num_menu_count++;
		}
		if (serviceLimit('H_AD')) { // 입점몰일 경우
			$num_menu_count++;
			$is_provider_solution = true;
		}
		if ($num_menu_count == 1) {
			$colspan_menu_count = 3;
		}
		$this->template->assign('num_menu_count', $num_menu_count);
		$this->template->assign('colspan_menu_count', $colspan_menu_count);
		$this->template->assign('is_provider_solution', $is_provider_solution);


		if ($iManagerSeq != $this->managerInfo['manager_seq']) {
			if($this->managerInfo['manager_yn'] != 'Y'){
				pageBack("권한이 없습니다.");
				exit;
			}
		}

		if ($iManagerSeq) {
			$wheres['shopSno']		= $this->config_system['shopSno'];
			$wheres['manager_seq']	= $iManagerSeq;
			$orderbys['idx'] 		= 'asc';
			$wheres['codecd like'] = '%_priod_%';
			$query_auth	= $this->authmodel->select('*',$wheres,$orderbys);
			foreach ($query_auth->result_array() as $data) {
				$codecd = str_replace('noti_count_priod_','',$data['codecd']);
				$noti_acount_priod[$codecd]	= $data['value'];
			}
		}
		if ( ! $noti_acount_priod['order']) $noti_acount_priod['order'] = "6개월";
		if ( ! $noti_acount_priod['board']) $noti_acount_priod['board'] = "6개월";
		if ( ! $noti_acount_priod['account']) $noti_acount_priod['account'] = "6개월";
		if ( ! $noti_acount_priod['warehousing']) $noti_acount_priod['warehousing'] = "6개월";

		if ($this->config_system['admin_limit_ip']) {
			$limit_row = explode("|", $this->config_system['admin_limit_ip']);
			$count = count($limit_row)-1;
			for ($i=0; $i<$count; $i++) {
				$arr = explode(".", $limit_row[$i]);
				$admin_limit_ip[] = $arr;
				if (count($arr) == 3) {
					$admin_limit_ip_msg[] = $limit_row[$i].".1 ~ ".$limit_row[$i].".255";
				} else {
					$admin_limit_ip_msg[] = $limit_row[$i];
				}
			}
		}
		$this->template->assign('admin_limit_ip', $admin_limit_ip);
		$this->template->assign('admin_limit_ip_msg', $admin_limit_ip_msg);

		$icons = find_icons('manager');

		if ($iManagerSeq) {
			$this->db->where('manager_seq', $iManagerSeq);
			$query = $this->db->get('fm_manager');
			$data = $query->result_array();

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

			if ($data['0']['lastlogin_date'] == "0000-00-00 00:00:00") {
				$data['0']['lastlogin_date'] = "";
			}

			unset($wheres);
			$wheres['shopSno']		= $this->config_system['shopSno'];
			$wheres['manager_seq']	= $_GET['manager_seq'];
			$wheres['codecd not like'] = '%_priod_%';
			$orderbys['idx'] 		= 'asc';
			$query_auth	= $this->authmodel->select('*',$wheres,$orderbys);
			foreach ($query_auth->result_array() as $data_auth) {
				$auth[$data_auth['codecd']]	= $data_auth['value'];
			}

			if ($auth['manager_yn'] == "Y") {
				$this->managerInfo = $this->session->userdata('manager');
				$this->managerInfo["mphoto"] = $data[0]['mphoto'];
				$this->managerInfo["mname"] = $data[0]['mname'];
				$this->session->set_userdata("manager", $this->managerInfo);
				$this->template->assign(array('managerInfo' => $this->managerInfo));
			}

			$this->template->assign('auth', $auth);
			$this->template->assign($data[0]);

			###
			$wheres['shopSno'] = $this->config_system['shopSno'];
			$wheres['manager_seq'] = $this->managerInfo['manager_seq'];
			$orderbys['idx'] = 'asc';
			$query_auth	= $this->authmodel->select('*',$wheres,$orderbys);
			foreach ($query_auth->result_array() as $data_auth) {
				$loop[$data_auth['codecd']]	= $data_auth['value'];
			}
			$this->template->assign('auth_loop',$loop);

			// 확인코드 추출
			$this->load->model('providermodel');
			$param['provider_seq']	= '1';
			$param['manager_id']	= $data[0]['manager_id'];
			$certify	= $this->providermodel->get_certify_manager($param);
			$this->template->assign('certify',$certify);

			$action_history_data = $this->managermodel->get_history($data[0]['manager_seq'])->result_array();
			$this->template->assign('action_history_data',$action_history_data);
		}

		$auth = config_load('master', 'sms_auth'); // 보안키
		$sms_api_key = $auth['sms_auth'];
		$send_phone = getSmsSendInfo(); // 발신번호인증
		// 보안키 및 발신번호 미인증시 처리
		if ($sms_api_key && $send_phone) {
			$sms_st = 'Y';
		} else {
			if ( ! $send_phone) $sms_st = '2';
			if ( ! $sms_api_key) $sms_st = '1';
		}

		$this->template->assign('sms_st',$sms_st);

		###
		$auth_limit = $this->authmodel->manager_limit_act('setting_manager_act');
		$this->template->assign('auth_limit',$auth_limit);

		### board
		$this->load->helper(array('board'));
		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		boardalllist($bsc);//게시판전체리스트

		// O2O 메뉴 권한 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_manager_auth();


		if ($icons) $this->template->assign('icons', $icons);
		$this->template->assign('scm_cfg', $this->scm_cfg);
		$this->template->assign('ip', $_SERVER['REMOTE_ADDR']);
		$this->template->assign('noti_acount_priod', $noti_acount_priod);
		$this->template->define(array('tpl' => $filePath));
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
		serviceLimit('H_FR','process');

		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$auth = $this->authmodel->manager_limit_act('setting_goodscd_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		$setting_goodscd_act_auth = $this->authmodel->manager_limit_act('setting_goodscd_act');
		$this->template->assign("setting_goodscd_act_auth",$setting_goodscd_act_auth);

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

		$this->template->assign('optcoloraddruse','1');//색상/주소 사용여부

		$surveyFilePath = dirname($this->template_path())."/_survey.htm";
		$this->template->define(array('surveyForm'=>$surveyFilePath));

		//상품추가양식 정보
		$this->load->helper("goods");
		$gdtypearray = array("goodsaddinfo","goodsoption","goodssuboption","goodscolor");
		$goodscodesettingview='';
		foreach($gdtypearray as $gdtype){
			unset($goodscode);
			$qry = "select * from fm_goods_code_form  where label_type ='".$gdtype."'  order by sort_seq";
			$query = $this->db->query($qry);
			$user_arr = $query -> result_array();

			foreach ($user_arr as $datarow){
				$datarow['label_title'] = str_replace('고유값','번호',$datarow['label_title']);
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

		if($this->isdemo['isdemo']) {
			$cfg_goods['ucc_id'] = getstrcut($cfg_goods['ucc_id'],0,'*********');
			$cfg_goods['ucc_domain'] = getstrcut($cfg_goods['ucc_domain'],0,'*********');
			$cfg_goods['ucc_key'] = getstrcut($cfg_goods['ucc_key'],0,'*********');
		}

		$real_config = config_load('realpacking');
		$real_config['service_info'] = get_object_vars(json_decode($real_config['service_info']));

		$this->template->assign("real_config",$real_config);
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

		### BRAND
		$sql = "select * from fm_brand where length(category_code)=4 and parent_id = 2 order by `left` asc";
		$query = $this->db->query($sql);
		foreach ($query->result_array() as $row){
			$brand[] = $row;
		}
		$this->template->assign('brand',$brand);
		$this->template->assign('brand_cnt',count($brand));

		### MODIFY
		if(isset($_GET['no'])){
			$sql = "select * from fm_provider A left join fm_provider_charge B on A.provider_seq = B.provider_seq and B.link = 1 where A.provider_seq = '{$_GET['no']}'";
			$query = $this->db->query($sql);
			$data = $query->result_array();
			$data[0]['deli_zipcode'] = explode("-",$data[0]['deli_zipcode']);
			$data[0]['info_zipcode'] = explode("-",$data[0]['info_zipcode']);
			$data[0]['main_visual_name']	= basename($data[0]['main_visual']);
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
			$person = array('cs', 'calcu', 'md', 'wcalcu');
			foreach($person as $k){
				unset($temp);
				$query = $this->db->query("select * from fm_provider_person where provider_seq = '{$_GET['no']}' and gb = '{$k}'");
				$temp = $query->result_array();
				$this->template->assign($k, $temp[0]);
			}


		}

		$this->template->define(array('tpl'=>$filePath));
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
		$this->template->assign('browser_info',getBrowser());
		$this->template->print_("tpl");
	}


	/* 등급별 할인율 설정 */
	public function member_sale(){

		$this->load->model('membermodel');
		$list = $this->membermodel->member_sale_group_list();

		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'sale_seq';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'asc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'1';
		$sc['perpage']			= '10';


		//할인율 MASTER 정보
		$qry = "select * from fm_member_group_sale";
		$qry .=" order by {$sc['orderby']} {$sc['sort']}";
		$sale_list = select_page($sc['perpage'],$sc['page'],10,$qry,'');

		$this->template->assign('page',$sale_list['page']);

		foreach ($sale_list["record"] as $datarow){

			foreach($list as $group){

				$qry = "select * from fm_member_group_sale_detail where sale_seq = '".$datarow["sale_seq"]."' and group_seq = '".$group["group_seq"]."'";
				$query = $this->db->query($qry);
				$detail_list = $query -> result_array();

				foreach($detail_list as $subdatarow){
					if($subdatarow["sale_use"] == "Y"){
						$subdata[$group["group_seq"]]["sale_use"]				= get_currency_price($subdatarow["sale_limit_price"],2)." 이상 구매";
					}else{
						$subdata[$group["group_seq"]]["sale_use"]				= "조건없음";
					}

					$subdata[$group["group_seq"]]["sale_price"]				= get_currency_price($subdatarow["sale_price"]);

					if($subdatarow["sale_price_type"] == "PER"){
						$subdata[$group["group_seq"]]["sale_price_type"]	= "% 할인";
					}else{
						$subdata[$group["group_seq"]]["sale_price_type"]	= $this->config_system['basic_currency']." 할인";
					}

					$subdata[$group["group_seq"]]["sale_option_price"] 		= get_currency_price($subdatarow["sale_option_price"]);

					if($subdatarow["sale_option_price_type"] == "PER"){
						$subdata[$group["group_seq"]]["sale_option_price_type"]		= "% 할인";
					}else{
						$subdata[$group["group_seq"]]["sale_option_price_type"]	= $this->config_system['basic_currency']." 할인";
					}

					$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_use"];

					if($subdatarow["point_use"] == "Y"){
						$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_limit_price"]."원 이상 구매";
					}else{
						$subdata[$group["group_seq"]]["point_use"]				= "조건없음";
					}

					$subdata[$group["group_seq"]]["point_price"]			= $subdatarow["point_price"];

					if($subdatarow["point_price_type"] == "PER"){
						$subdata[$group["group_seq"]]["point_price_type"]		= "% 적립";
					}else{
						$subdata[$group["group_seq"]]["point_price_type"]		= $this->config_system['basic_currency']." 적립";
					}


					$subdata[$group["group_seq"]]["reserve_price"]			= $subdatarow["reserve_price"];

					if($subdatarow["reserve_price_type"] == "PER"){
						$subdata[$group["group_seq"]]["reserve_price_type"]		= "% 적립";
					}else{
						$subdata[$group["group_seq"]]["reserve_price_type"]		=  $this->config_system['basic_currency']." 적립";
					}

					$subdata[$group["group_seq"]]["reserve_select"]			= $subdatarow["reserve_select"];
					$subdata[$group["group_seq"]]["reserve_year"]			= $subdatarow["reserve_year"];
					$subdata[$group["group_seq"]]["reserve_direct"]			= $subdatarow["reserve_direct"];
					$subdata[$group["group_seq"]]["point_select"]			= $subdatarow["point_select"];
					$subdata[$group["group_seq"]]["point_year"]				= $subdatarow["point_year"];
					$subdata[$group["group_seq"]]["point_direct"]			= $subdatarow["point_direct"];
				}


			}

			$data[$datarow["sale_seq"]] = $subdata;
			$data[$datarow["sale_seq"]]["sale_seq"] = $datarow["sale_seq"];
			$data[$datarow["sale_seq"]]["sale_title"] = $datarow["sale_title"];
			$data[$datarow["sale_seq"]]["_no"] = $datarow["_no"];
			$data[$datarow["sale_seq"]]["totalcount"] = $sale_list['page']['totalcount'];
			$data[$datarow["sale_seq"]]["loop"] = $list;
			$data[$datarow["sale_seq"]]["gcount"] = count($list);
			unset($limit_goods);
			unset($limit_cate);
			###
			$sql = "SELECT
							distinct A.*, B.*
						FROM
							fm_member_group_issuegoods A
							LEFT JOIN
							(SELECT
								g.goods_seq, p.provider_name, g.goods_name, o.price
							FROM
								fm_goods g LEFT JOIN fm_goods_option o ON g.goods_seq = o.goods_seq AND o.default_option = 'y'
								LEFT JOIN fm_provider p ON g.provider_seq = p.provider_seq
								) B ON A.goods_seq = B.goods_seq
						WHERE
							A.sale_seq = '{$datarow["sale_seq"]}'";
			$query = $this->db->query($sql);
			foreach ($query->result_array() as $row){
				$limit_goods[] = $row;

				if($row["type"]=="sale")
				{
					$data[$datarow["sale_seq"]]["issuegoods_sale"] = $row;
				}else{
					$data[$datarow["sale_seq"]]["issuegoods_emoney"] = $row;
				}
			}

			$data[$datarow["sale_seq"]]["issuegoods"] = $limit_goods;


			###
			$this->load->model('categorymodel');
			$this->db->where('sale_seq', $datarow["sale_seq"]);
			$query = $this->db->get('fm_member_group_issuecategory');
			foreach ($query->result_array() as $row){
				$row['title'] =  $this->categorymodel->get_category_name($row['category_code']);
				$limit_cate[] = $row;

				if($row["type"]=="sale")
					$data[$datarow["sale_seq"]]["issuecategorys_sale"] = $row;
				else
					$data[$datarow["sale_seq"]]["issuecategorys_emoney"] = $row;
			}

			$data[$datarow["sale_seq"]]["issuecategorys"] = $limit_cate;


		}

		$service_code = $this->config_system['service']['code'];

		if		(serviceLimit('H_EXAD')){
			$default_member_sale_cnt = 5;
		}else if(serviceLimit('H_PRST')){
			$default_member_sale_cnt = 3;
		}else{
			$default_member_sale_cnt = 1;
		}

		$this->config_system['service']['max_member_sale_cnt'] += $default_member_sale_cnt;

		$this->template->assign(array('use_member_sale_cnt'=>$sale_list['page']['totalcount']));
		$this->template->assign(array('service_code'=>$service_code));
		$this->template->assign(array('config_system'=>$this->config_system));
		$this->template->assign(array('default_member_sale_cnt'=>$default_member_sale_cnt));
		$this->template->assign(array('data'=>$data));
		$this->template->assign(array('loop'=>$list,'gcount'=>count($list)));

	// 관리자 - 설정 - 회원 등급별 구매 혜택 안내
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_member_sale();

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}


	public function member_sale_write(){

		$this->load->model('membermodel');
		$list = $this->membermodel->member_sale_group_list();


		if($_GET["sale_seq"]){
			//일반가입 정보
			$qry = "select * from fm_member_group_sale where sale_seq = '".$_GET["sale_seq"]."'";
			$query = $this->db->query($qry);
			$sale_list = $query -> result_array();
			$this->template->assign(array('sale_title'=>$sale_list[0]["sale_title"]));
			$this->template->assign(array('defualt_yn'=>$sale_list[0]["defualt_yn"]));

			foreach ($sale_list as $datarow){

				foreach($list as $group){

					$qry = "select * from fm_member_group_sale_detail where sale_seq = '".$datarow["sale_seq"]."' and group_seq = '".$group["group_seq"]."'";
					$query = $this->db->query($qry);
					$detail_list = $query -> result_array();

					foreach($detail_list as $subdatarow){

						$subdata[$group["group_seq"]]["sale_use"]				= $subdatarow["sale_use"];
						$subdata[$group["group_seq"]]["sale_limit_price"]		= $subdatarow["sale_limit_price"];
						$subdata[$group["group_seq"]]["sale_price"]				= $subdatarow["sale_price"];

						$subdata[$group["group_seq"]]["sale_price_type"]		= $subdatarow["sale_price_type"];
						$subdata[$group["group_seq"]]["sale_option_price"] 		= $subdatarow["sale_option_price"];

						$subdata[$group["group_seq"]]["sale_option_price_type"]	= $subdatarow["sale_option_price_type"];
						$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_use"];

						$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_use"];
						$subdata[$group["group_seq"]]["point_limit_price"]		= $subdatarow["point_limit_price"];
						$subdata[$group["group_seq"]]["point_price"]			= $subdatarow["point_price"];

						$subdata[$group["group_seq"]]["point_price_type"]		= $subdatarow["point_price_type"];

						$subdata[$group["group_seq"]]["reserve_price"]			= $subdatarow["reserve_price"];

						$subdata[$group["group_seq"]]["reserve_price_type"]		= $subdatarow["reserve_price_type"];
						$subdata[$group["group_seq"]]["reserve_select"]			= $subdatarow["reserve_select"];
						$subdata[$group["group_seq"]]["reserve_year"]			= $subdatarow["reserve_year"];
						$subdata[$group["group_seq"]]["reserve_direct"]			= $subdatarow["reserve_direct"];
						$subdata[$group["group_seq"]]["point_select"]			= $subdatarow["point_select"];
						$subdata[$group["group_seq"]]["point_year"]				= $subdatarow["point_year"];
						$subdata[$group["group_seq"]]["point_direct"]			= $subdatarow["point_direct"];
					}


				}

				$data[$datarow["sale_seq"]] = $subdata;
				$data[$datarow["sale_seq"]]["sale_seq"] = $datarow["sale_seq"];
				$data[$datarow["sale_seq"]]["sale_title"] = $datarow["sale_title"];
				$data[$datarow["sale_seq"]]["loop"] = $list;
				$data[$datarow["sale_seq"]]["gcount"] = count($list);
				unset($limit_goods);
				unset($limit_cate);
				###
				$sql = "SELECT
								distinct A.*, B.*
							FROM
								fm_member_group_issuegoods A
								LEFT JOIN
								(SELECT
									g.goods_seq, p.provider_name, g.goods_name, o.price
								FROM
									fm_goods g LEFT JOIN fm_goods_option o ON g.goods_seq = o.goods_seq AND o.default_option = 'y'
									LEFT JOIN fm_provider p ON g.provider_seq = p.provider_seq
									) B ON A.goods_seq = B.goods_seq
							WHERE
								A.sale_seq = '{$datarow["sale_seq"]}'";
				$query = $this->db->query($sql);
				foreach ($query->result_array() as $row){
					$limit_goods[] = $row;

					if($row["type"]=="sale")
						$data[$datarow["sale_seq"]]["issuegoods_sale"] = $row;
					else
						$data[$datarow["sale_seq"]]["issuegoods_emoney"] = $row;

				}

				$data[$datarow["sale_seq"]]["issuegoods"] = $limit_goods;


				###
				$this->load->model('categorymodel');
				$this->db->where('sale_seq', $datarow["sale_seq"]);
				$query = $this->db->get('fm_member_group_issuecategory');
				foreach ($query->result_array() as $row){
					$row['title'] =  $this->categorymodel->get_category_name($row['category_code']);
					$limit_cate[] = $row;

					if($row["type"]=="sale")
						$data[$datarow["sale_seq"]]["issuecategorys_sale"] = $row;
					else
						$data[$datarow["sale_seq"]]["issuecategorys_emoney"] = $row;

				}

				$data[$datarow["sale_seq"]]["issuecategorys"] = $limit_cate;

			}

		}
		$this->template->assign(array('data'=>$data));
		$this->template->assign(array('loop'=>$list,'gcount'=>count($list)));

		$reserve = config_load('reserve');
		$this->template->assign(array('reserve'=>$reserve));




		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}


	function member_sale_delete(){

		$this->tempate_modules();
		$filePath	= $this->template_path();

		$this->load->model('membermodel');

		$where_str = "sale_seq <> '".$_GET["sale_seq"]."'";
		$sale_list = $this->membermodel->get_member_sale($where_str);

		$where_str = "sale_seq = '".$_GET["sale_seq"]."'";
		$sale_title = $this->membermodel->get_member_sale($where_str, "sale_title");

		$this->template->assign(array('list'=>$sale_list));
		$this->template->assign(array('sale_title'=>$sale_title[0]["sale_title"]));

		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");

	}

	function search(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_address_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		$cfg_tmp = config_load("search");

		$cfg_search['popular_search'] = $cfg_tmp['popular_search']?$cfg_tmp['popular_search']:'n';
		$cfg_search['popular_search_limit_day'] = $cfg_tmp['popular_search_limit_day']?$cfg_tmp['popular_search_limit_day']:30;
		$cfg_search['popular_search_recomm_limit_day'] = $cfg_tmp['popular_search_recomm_limit_day']?$cfg_tmp['popular_search_recomm_limit_day']:30;

		$cfg_search['auto_search'] = $cfg_tmp['auto_search']?$cfg_tmp['auto_search']:'n';
		$cfg_search['auto_search_limit_day'] = $cfg_tmp['auto_search_limit_day']?$cfg_tmp['auto_search_limit_day']:30;
		$cfg_search['auto_search_recomm_limit_day'] = $cfg_tmp['auto_search_recomm_limit_day']?$cfg_tmp['auto_search_recomm_limit_day']:30;

		$cfg_search_word['main']		= "메인 및 그 외";
		$cfg_search_word['good_view']	= "상품상세";
		$cfg_search_word['category']	= "카테고리";
		$cfg_search_word['location']	= "지역";
		$cfg_search_word['brand']		= "브랜드";
		if($this->config_system['operation_type'] == 'light'){ // heavy일 경우
			$cfg_search_word['brand_main']		= "브랜드 메인";
			$cfg_search_word['all_event']	= "이벤트 메인";
			$cfg_search_word['goods_new']	= "신상품";
			$cfg_search_word['goods_best']	= "베스트";
			$cfg_search_word['bigdata']	= "빅데이터 상품추천";
		}
		$cfg_search_word['event']	= "할인 이벤트, 사은품 이벤트";
		if(serviceLimit('H_AD')){ // 입점사버전 일 경우
			$cfg_search_word['mshop'] = "판매자 미니샵";
		}
		$cfg_search_word['board']	= "게시판";
		$cfg_search_word['mypage']	= "MY 페이지";





		$query = "select * from fm_search_word";
		$query = $this->db->query($query);
		foreach($query->result_array() as $data){
			$result[$data['page']][] = $data;
		}

		// 우편번호 설정
		$cfg_zipcode = config_load("zipcode");

		$this->template->assign('cfg_zipcode',$cfg_zipcode);

		$this->template->assign('cfg_search',$cfg_search);
		$this->template->assign('cfg_search_word',$cfg_search_word);
		$this->template->assign('result',$result);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}


	/* 등급별 구매혜택 세트 추가 신청 */
	public function member_payment(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}

		$req_type = 'MEMBER_SALE';
		$param = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=".$req_type."&req_url=/myshop&payment_type=".$_GET['type']."&totalCnt=".$_GET['totalCnt'];
		$param = makeEncriptParam($param);

		$this->template->assign('param',$param);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}



	/* 등급별 구매혜택 결제 로그 */
	public function member_account_log(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}

		$req_type = 'MEMBER_SALE_ACCOUNT';
		$param = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=".$req_type."&req_url=/myshop";
		$param = makeEncriptParam($param);

		$this->template->assign('param',$param);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
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

	public function pop_manager_member_log(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function manager_member_downloadlist() {
		### SEARCH
		$sc						= $_POST;
		$sc['search_text']		= ($sc['search_text'] == '관리자 아이디') ? '':$sc['search_text'];
		$sc['perpage']		= (!empty($_POST['perpage'])) ?	intval($_POST['perpage']):10;
		$sc['page']			= (!empty($_POST['page'])) ?		intval(($_POST['page'] - 1) * $sc['perpage']):0;

		$data = array();
		$dataloop = array();

		$sqlWhereClause = "";
		if (!empty($sc['sdate']) && !empty($sc['edate'])) {
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$sqlWhereClause.=" AND A.reg_date BETWEEN '{$start_date}' AND '{$end_date}' ";
		}

		if (!empty($sc['search_text'])) {
			$sqlWhereClause .= " AND A.manager_id LIKE '".$sc['search_text']."%'";
		}

		$sql = "SELECT
				A.*
			FROM
				fm_log_member_download A
			WHERE 1 ".$sqlWhereClause;

		$sql .=" ORDER BY A.seq desc";
		$limit =" limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();

		$query = $this->db->query($sql);
		$data['count'] = $query->num_rows();
		$data['html'] = "";

		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);

		if ($data['result']) {
			foreach($data['result'] as $datarow){
				$str_down_count = "";
				if ($datarow['down_count']) {
					$str_down_count = sprintf("(%s명)",number_format($datarow['down_count']));
				}
				$datarow['content'] = sprintf("회원정보%s를 다운로드 하였습니다. (%s) %s", $str_down_count, $datarow['ip'], $datarow['file_name']);
				$data['html'] .= sprintf("<tr><td class='its-td-align center'>%s(%s)</td><td class='its-td-align center'>%s</td><td class='its-td-align center'>%s</td></tr>", get_manager_name($datarow['manager_seq']), $datarow['manager_id'], $datarow['content'], $datarow['reg_date']);
			}
		} else {
			$data['html'] .= sprintf("<tr><td class='its-td-align center' colspan='3'>%s</td></tr>","로그 내역이 없습니다.");
		}

		// 페이징 처리 위한 변수 셋팅
		$page =  get_current_page($sc);
		$pagecount = get_page_count($sc, $data['count']);

		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']		= @ceil($sc['searchcount']/ $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_log_member_download');

		$result = array( 'content'=>$data['html'], 'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);

		echo json_encode($result);
		exit;
	}

	public function setting_editor_popup(){
		$config_setting_editor = config_load('goods_contents_editor');

		$file_path	= $this->template_path();
		$this->template->assign(array('config_setting_editor'=>$config_setting_editor));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
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

	// 굿스플로 결제 :: 2015-06-25 lwh
	public function goodsflow_payment(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$shopSno	= isset($shopSno) ? $shopSno : $this->config_system['shopSno'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}

		$gf_param = "req_domain=".$domain."&req_mallid=".$mall_id."&shop_sno=".$shopSno."&req_type=GOODSFLOW&req_url=/myshop";
		$gf_param = makeEncriptParam($gf_param);

		$this->template->assign('param',$gf_param);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 굿스플로 사용현황 :: 2015-06-29 lwh
	public function goodsflow_log(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$sc = $this->input->get();
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		$gf_config['terms']	= config_load('goodsflow');
		$this->load->model('goodsflowmodel');
		$this->load->model('providermodel');

		$sc	= $_GET;
		if ($_GET['no'])
		{
			$sc['provider_seq'] = $_GET['no'];
		}
		$sc['select_date_regist']	= isset($sc['select_date_regist'])	? $sc['select_date_regist']	: 'today';
		$log_list	= $this->goodsflowmodel->goodsflow_log_list($sc);
		$provider	= $this->providermodel->provider_goods_list_sort();

		$this->template->assign('provider',$provider);
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));
		$this->template->assign('gf_config',$gf_config['terms']);
		$this->template->assign('pagin',$log_list['paginlay']);
		$this->template->assign('log_list',$log_list['list']);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function popup_print_setting(){

		$this->tempate_modules();

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');

		$provider_seq	= 1;
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

		$this->template->assign(array('scm_cfg' => $this->scm_cfg));
		$this->template->assign($data);
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	## 네아로(네이버아이디로로그인) API 연동 창 불러오기. @2015-12-17 pjm
	function sns_nid_api(){

		$this->tempate_modules();

		## sns 로그인&가입 관련
		$this->joinform_sns();

		if($_SERVER['HTTPS']) $shop_protocol = 'https'; else $shop_protocol = 'http';

		$this->template->assign(array(
								'ceo'=>$this->config_basic['ceo']
								,'shopName'=>$this->config_basic['shopName']
								,'shopcode'=>$this->config_system['shopSno']
								,'apicallurl'=>"https://firstmall.kr/naverAPI/nid_apply.php"
								,'shop_callbackurl'=>$_SERVER['HTTP_HOST']
								,'shop_protocol'=>$shop_protocol
								));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	## SNS 설정방법 인터페이스에서 가져오기. @2016-05-09 jhr
	public function get_sns_guide_ajax(){
		$this->load->helper('admin');
		$mode = $_GET['mode'];
		if($mode) $snsconf_detail = getGabiaPannel('snsconf_'.$mode.'_guide');
		echo $snsconf_detail;
	}

	public function multi()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_basic_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		$this->admin_menu();
		$this->load->model('adminenvmodel');
		$this->load->model('currencymodel');
		$this->tempate_modules();
		$params = array('use_yn'=>'y');
		$query = $this->adminenvmodel->get($params);
		foreach($query->result_array() as $data){
			$data['currency_loop'] = array();
			$params_currency = array('admin_env_seq'=>$data['admin_env_seq']);
			$query_currency = $this->currencymodel->get($params_currency);
			foreach($query_currency->result_array() as $data_currency){
				$data['currency_loop'][] = $data_currency;
			}
			$loop[] = $data;
		}
		foreach(code_load('language') as $data_code){
			$language_codes[$data_code['codecd']] = $data_code['value'];
		}
		foreach(code_load('currency') as $data_code){
			$currency_codes[$data_code['codecd']] = $data_code['value'];
		}

		// 목록 페이지를 제거하기 위해 현재 샵번호와 동일한 SEQ를 이용하여 상세 페이지로 전달
		$adminEnvSeq = '';
		foreach($loop as $row){
			if($row['shopSno'] == $this->config_system['shopSno']){
				$adminEnvSeq = $row['admin_env_seq'];
			}
		}
		if($adminEnvSeq != ''){
			pageRedirect("/admin/setting/multi_basic?no=".$adminEnvSeq);
		}

		$this->template->assign(array(
			'loop'				=> $loop,
			'language_codes'	=> $language_codes,
			'currency_codes'	=> $currency_codes
		));
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 일반 설정 */
	public function multi_basic(){
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('setting_basic_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}

		$cfg_system		= config_load('system');
		$skinType 		= !empty($cfg_system['skin_type']) ? $cfg_system['skin_type'] : 'fixed';
		$this->template->assign('skinType',$skinType);

		$this->admin_menu();
		$admin_env_seq = (int) $_GET['no'];
		$this->load->model('adminenvmodel');
		$this->load->model('currencymodel');
		$params = array('use_yn'=>'y','admin_env_seq'=>$admin_env_seq);
		$query = $this->adminenvmodel->get($params);
		list($data) = $query->result_array();
		$data['compare_currencys'] = explode(',',$data['compare_currency']);
		$data['currency_loop'] = array();
		$params_currency = array('admin_env_seq'=>$data['admin_env_seq']);
		$query_currency = $this->currencymodel->get($params_currency);
		foreach($query_currency->result_array() as $data_currency){
			$data['currency_loop'][] = $data_currency;
		}
		$data_admin_env = $data;
		foreach(code_load('language') as $data_code){
			$language_codes[$data_code['codecd']] = $data_code['value'];
		}
		foreach(code_load('currency') as $data_code){
			$currency_codes[$data_code['codecd']] = $data_code['value'];
		}
		// 정렬 순서 임의로
		$this->template->assign(array(
			'data_admin_env'		=> $data_admin_env,
			'language_codes'		=> $language_codes,
			'currency_codes'		=> $currency_codes,
		));
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();
		$arrBasic = ($this->config_basic)?$this->config_basic:config_load('basic');

		$this->load->model('configsalemodel');
		$sc['type'] = 'mobile';
		$systemmobiles = $this->configsalemodel->lists($sc);
		$this->template->assign('systemmobiles',$systemmobiles['result']);

		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		switch($reserves['reserve_select']){
			case "year":
				$reserves['reservetitle'] = date("Y년 m월 d일", mktime(0,0,0,12, 31, date("Y")+$reserves['reserve_year']));
				break;
			case "direct":
				$reserves['reservetitle'] = $reserves['reserve_direct'].'개월';
				break;
			default:
				$reserves['reservetitle'] = '제한하지 않음';
				break;
		}
		switch($reserves['point_select']){
			case "year":
				$reserves['pointtitle'] = date("Y년 m월 d일", mktime(0,0,0,12, 31, date("Y")+$reserves['point_year']));
				break;
			case "direct":
				$reserves['pointtitle'] = $reserves['point_direct'].'개월';
				break;
			default:
				$reserves['pointtitle'] = '제한하지 않음';
				break;
		}
		$this->template->assign($reserves);

		## sns 로그인&가입 관련
		$this->joinform_sns();
		//debug($arrBasic);
		if(isset($arrBasic['businessLicense']))$arrBasic['businessLicense'] = explode('-',$arrBasic['businessLicense']);
		if(isset($arrBasic['providerNumber']))$arrBasic['providerNumber'] = explode('-',$arrBasic['providerNumber']);
		if(isset($arrBasic['companyPhone']))$arrBasic['companyPhone'] = explode('-',$arrBasic['companyPhone']);
		if(isset($arrBasic['companyFax']))$arrBasic['companyFax'] = explode('-',$arrBasic['companyFax']);
		if(isset($arrBasic['companyZipcode']))$arrBasic['companyZipcode'] = $arrBasic['companyZipcode'];
		if(isset($arrBasic['companyZipcode']))$arrBasic['companyZipcode'] = str_replace('-','',$arrBasic['companyZipcode']);
		if(isset($arrBasic['companyEmail']))$arrBasic['companyEmail'] = explode('@',$arrBasic['companyEmail']);
		if(isset($arrBasic['partnershipEmail']))$arrBasic['partnershipEmail'] = explode('@',$arrBasic['partnershipEmail']);
		if(count($arrBasic['companyPhone']) < 3){
			$arrBasic['companyPhone'][2] = $arrBasic['companyPhone'][1];
			$arrBasic['companyPhone'][1] = $arrBasic['companyPhone'][0];
			$arrBasic['companyPhone'][0] = '';
		}
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

		//본사 미니샵이미지
		$query = $this->db->query("select * from fm_provider A where A.provider_seq = '1'");
		$providerdata = $query->row_array();
		$providerdata['main_visual_name']	= basename($providerdata['main_visual']);
		$this->template->assign('providerdatainfo',$providerdata);

		$reserve = ($this->reserves)?$this->reserves:config_load('reserve');
		$this->template->assign('reserve',$reserve);


		/* 서비스기간 남은일수 */
		$expireDayTime		= strtotime($this->config_system['service']['expire_date']);
		$todayTime				= strtotime(date('Y-m-d'));
		$expireDay				= date("Y-m-d", $expireDayTime);
		$remainExpireDay	= round( ($expireDayTime-$todayTime)/(3600*24) );
		$this->template->assign(array(
			'expireDay'				=> $expireDay,
			'remainExpireDay'		=> $remainExpireDay,
		));


		$this->template->define(array('tpl'=>$filePath));
		$this->template->assign($arrBasic);
		$this->template->print_("tpl");
	}

	## 다국어 경고창 설정 페이지 @2016-06-13 jhr

	public function alert_setting(){
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('gb', '구분', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$this->load->model('alertmodel');

		$sc		= $this->input->get();
		$data	= $this->alertmodel->alert_list($sc);

		$group = 0;
		foreach($data as $k => $v){
			if	($k%100 == 0 && $k > 0)
				$group++;
			$loop[$group][$k] = $v;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->assign('loop',$loop);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 택배사 설정 :: 2016-08-17 lwh
	public function delivery_company(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_deliverycompany_view');
		if(!$auth){
			pageBack($this->auth_msg);
			exit;
		}
		$auth = $this->authmodel->manager_limit_act('setting_deliverycompany_act');
		if(!$auth){
			$this->template->assign('service_limit','Y');
		}

		$aParamsGet = $this->input->get();

		if	($aParamsGet['provider_seq'] > 1){
			$provider_seq			= $aParamsGet['provider_seq'];
			$deliveryCompanyCode	= config_load('providerDeliveryCompanyCode', $provider_seq);
			$deliveryCompanyCode	= $deliveryCompanyCode[$provider_seq];
			$this->template->assign("sc",$aParamsGet);

			$viewParam['provider_seq']	= $aParamsGet['provider_seq'];
			$viewParam['provider_name']	= urldecode($aParamsGet['provider_name']);
			$this->template->assign('view',$viewParam);
		}else{
			$provider_seq			= 1;
			$deliveryCompanyCode	= config_load('shippingdelivery', 'deliveryCompanyCode');
			$deliveryCompanyCode	= $deliveryCompanyCode['deliveryCompanyCode'];
		}

		$delivery_url			= config_load('delivery_url');
		// 택배 업무 자동화 자동등록 - 서버분리로 인해 임시 적용 :: 2017-01-19 lwh
		if(!$delivery_url['code97'] || !$delivery_url['code98'] || !$delivery_url['code99']){
			config_save('delivery_url',array('code97'=>array('company'=>'롯데택배 업무자동화','url'=>'')));
			config_save('delivery_url',array('code98'=>array('company'=>'굿스플로 업무자동화','url'=>'')));
			config_save('delivery_url',array('code99'=>array('company'=>'우체국택배 업무자동화','url'=>'')));
			$delivery_url			= config_load('delivery_url');
		}
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

		## 현태택배업무자동화서비스 세팅값 :: 2015-07-10
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
					$address = $provider_info['info_address1'] . ' ' . $provider_info['info_address2'];
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
		if(serviceLimit('H_AD')) {
		    // 굿스플로 입점사 선택 레이어
		    $this->template->define('goodsflow_provider_layer', $this->skin.'/setting/_goodsflow_provider_layer.html');
		}
		$this->template->print_("tpl");

	}

	public function instargram_ajax()
	{
		// sns 설정 출력
		echo json_encode($this->arrSns);
	}


	/* kicc 설정 */
	public function kicc(){
		$filePath	= $this->template_path();
		$tmp = config_load('kicc');
		$tmp['arrKiccCardCompany'] = code_load('kiccCardCompanyCode');

		$key_dir = './pg/kicc/key/'.$tmp['mallCode'];
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

		$key_dir = './pg/kicc/key/'.$tmp['escrowMallCode'];
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


		foreach($tmp['arrKiccCardCompany'] as $k=>$v){
			$tmp['arrCardCompany'][$v['codecd']]=$v['value'];
		}

		// 에스크로 마크 초기값 지정
		if(empty($tmp['escrowMark']) || empty($tmp['escrowMarkMobile'])){
			$tmp['escrowMark'] = 'kicc.gif';
			$tmp['escrowMarkMobile'] = 'kicc_mobile.gif';
			config_save('kicc',array('escrowMark'=>'kicc.gif'));
			config_save('kicc',array('escrowMarkMobile'=>'kicc_mobile.gif'));
		}

		$this->template->define(array('tpl'=>$filePath));
		if($tmp) $this->template->assign($tmp);
		$this->template->print_("tpl");
	}

	public function ssl_list_ajax(){
		$this->admin_menu();
		$this->tempate_modules();
		// 인증서 관련 라이브러리 추가
		$this->load->library('ssllib');
		$this->template->assign('ssllib', $this->ssllib);

		$page		= $this->input->get('page') ? $this->input->get('page') : 1;

		unset($params);
		$params[$this->ssllib->sslConfigColumn['certStatus']] = $this->ssllib->valueSslConfigCertStatus[$this->input->get('status')];
		$result = $this->ssllib->getSslEnvironment($params, $page);
		$this->template->assign('loop', $result['data']);


		$paginlay = pagingtagjs($page, $result['block_pages'], $result['total_page'], 'callSslList([:PAGE:])');
		$this->template->assign('pagin',$paginlay);

		$file_path	= $this->template_path();
		$file_path = str_replace('ssl_list_ajax.html','_ssl_list_ajax.html',$file_path);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function set_ssl_redirect(){
		$this->admin_menu();
		$this->tempate_modules();
		$ssl_seq		= $this->input->get("sslSeq");
		$redirect		= $this->input->get("redirect");
		if($redirect=='Y'){
			$redirect = 'N';
		}else{
			$redirect = 'Y';
		}

		// 인증서 관련 라이브러리 추가
		$this->load->library('ssllib');
		$this->ssllib->changeSslRedirect($ssl_seq, $redirect);

		echo "ok";
	}

	public function shipping_zone_download()
	{
		$params 				= $this->input->get();
		$shipping_cost_seq		= $params['shipping_cost_seq'];
		$shipping_group_name	= preg_replace("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", '_', $params['shipping_group_name']);
		$zone_name				= preg_replace("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", '_', $params['zone_name']);

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
		//app/javascript/js/admin-layout.js:602:
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
				$datas['shipping_provider_seq']	= 1;
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
		if(count($optionSeqs) <= 0){
			$data = array(
				'shipping_group_seq' 	=> $_GET['shipping_group_seq'],
				'shipping_set_seq'		=> $_GET['shipping_set_seq'],
				'shipping_set_code' 	=> 'delivery',
				'shipping_set_name' 	=> '택배',
				'shipping_set_type' 	=> $_GET['shipping_set_type'],
				'shipping_opt_type' 	=> $_GET['shipping_opt_type'],
				'shipping_provider_seq' => '1',
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

		exit;
    }

	public function manager_log(){
        if($this->managerInfo['manager_yn'] != 'Y'){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->load->model('authmodel');
		$this->admin_menu();
		$this->tempate_modules();
		$filePath	= $this->template_path();

		$this->load->library('managerlog');

		//메뉴 데이터
		$action_type 	= $this->managerlog->action_type;
		$action_menu = $this->managerlog->action_menu;
		unset($action_menu['member']['excel_spout'], $action_menu['order']['excel_spout'], $action_menu['member']['member_catalog_ajax']);

		$action_menu_new = array();
		foreach($action_menu as $k => $v){
			foreach($v as $kk => $vv){
				if(substr($kk, -7) == '_prints'){
					$vv .= " > 인쇄";
				} else if(strpos($kk, 'excel_download') !== false){
					$vv .= " > 엑셀 다운로드";
				}

				if(substr($kk, 0, 12) == 'selleradmin_' || substr($kk, -7) == '_seller'){
					$action_menu_new[$k][$kk] = $vv." (입점)";
				} else {
					$action_menu_new[$k][$kk] = $vv;
				}
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

		//1. 페이징
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

		$where_and = array();
		$where_or = array();

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

		if(serviceLimit('H_AD') && $params['provider_seq'] > 0){
			if($params['provider_seq'] == 1){
				$where_or = "(provider_seq = 1 OR super_manager_yn = 'Y')";

				$managers = $this->manager_list(1, 'Y');
			} else {
				$where_and['provider_seq'] = $params['provider_seq'];
				$where_and['super_manager_yn !='] = 'Y';

				$managers = $this->manager_list($params['provider_seq'], 'Y');
			}
		} else {
			//목록
			$query = $this->db
				->select("*")
				->from('fm_manager')
				->order_by('manager_id ASC')
				->get();

			$managers = array();
			foreach($query->result_array() as $k => $provider){
				$managers[$k]['manager_seq']	= $provider['manager_seq'];
				$managers[$k]['manager_id']	= $provider['manager_id'];
				$managers[$k]['mname']		= $provider['mname'];
			}
		}

		$this->template->assign('managers', $managers);

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
				->where($where_or)
				//->limit($perpage, $page)
				->get();

			$writer = WriterFactory::create(Type::XLSX); // for XLSX files
			$fileName = date('YmdHis')."_administrator_work_history.xlsx";
			$filePath = ROOTPATH . "excel_download/".$fileName;

			$columns = array('No.', '구분', '메뉴', '수행업무', '입점사', '접속자', '접속일시', '접속 IP');

			$writer->openToFile($filePath);
			$writer->addRow($columns);

			$no = 1;
			foreach($query->result_array() as $k => $v){
				$datas = array();
				$datas['no']	= $no++;
				$datas['type']	= $action_type[$v['action_type']];
				$datas['menu']	= $action_menu[$v['action_type']][$v['action_menu']];
				$datas['desc']	= $v['action_desc'];
				if ($v['provider_seq'] != 1 && $v['provider_seq'] != 0) {
					if($v['super_manager_yn'] == 'Y'){
						$datas['provider'] = '본사';
					} else {
						//$datas['provider'] = $v['provider_name']." (".$v['provider_id'].")";
						$list[$k]['provider'] = $v['provider_name'];
					}
				} else {
					$datas['provider']	= '본사';
				}

				$datas['manager_name']	= $v['manager_id']." (".$v['manager_name'].")";
				$datas['regist_date']	= $v['regist_date'];
				$datas['access_ip']		= $v['access_ip'];

				$writer->addRow($datas);
			}
			$writer->close();

			echo $fileName;
			exit;
		}

		//입점사 정보
        if(serviceLimit('H_AD')){
			//--> provider 정보 추출
			$provider_name	= trim($params['provider_name']);
			$provider = $this->db
				->select("*")
				->from('fm_provider')
				->where('manager_yn', 'y')
				->where('provider_seq !=', '1')
				->order_by('provider_seq', 'ASC')
				->get();

		    //--> 키 인덱스로 재배열
            $providerData = array();
            foreach($provider->result_array() as $provider){
                $providerData[$provider['provider_seq']]['provider_seq']	= $provider['provider_seq'];
                $providerData[$provider['provider_seq']]['provider_id']		= $provider['provider_id'];
                $providerData[$provider['provider_seq']]['provider_name']	= $provider['provider_name'];

                if( !empty($provider_name) && $provider['provider_name'] == $provider_name ){ //--> 입점사 검색일 경우의 입점사 정보 저장
                    $provider_seq_search = $provider['provider_seq'];
                }
            }
            $this->template->assign('provider', $providerData);
        }

		//전체 갯수
		$list_total = $this->db
			->select("*")
			->from('fm_manager_log')
			->where($where_and)
			->where($where_or)
			->get()
			->num_rows();
		$this->template->assign('list_total', $list_total);

		//목록
		$query = $this->db
			->select("*")
			->from('fm_manager_log')
			->where($where_and)
			->where($where_or)
			->limit($perpage, $page)
			->order_by('manager_log_seq', 'DESC')
			->get();

		//정보 매칭
		$no = $list_total - ( ($page/$perpage) * $perpage );
		foreach($query->result_array() as $k => $v){
            $list[$k]['no']		= $no;
			$list[$k]['type']	= $action_type[$v['action_type']];

			$list[$k]['menu']	= $action_menu[$v['action_type']][$v['action_menu']];
			$list[$k]['desc']	= $v['action_desc'];
			if($v['action_type'] == 'board'){
				$list[$k]['menu'] .= ' > '.$v['action_target'];
				if($v['action_menu'] == 'view'){
					$list[$k]['desc'] .= ' ('.$v['action_status'].')';
				}
			}

			if( $v['action_menu'] == 'manager_modify'
				|| ($v['action_type'] == 'member' && $v['action_desc'] == '검색')
				|| ($v['action_type'] == 'order' && $v['action_desc'] == '검색')
				|| ($v['action_type'] == 'market_connector' && $v['action_desc'] == '검색')
				|| ($v['action_type'] == 'goods' && $v['action_desc'] == '검색')
				|| ($v['action_type'] == 'board' && strpos($v['action_desc'], '검색') !== false)
				|| ($v['action_type'] == 'promotion' && strpos($v['action_desc'], '검색') !== false)
				|| ($v['action_type'] == 'login' && $v['provider_id'] == 'Firstmall') ){
				$list[$k]['detail_seq'] = $v['manager_log_seq'];
			}

			if ($v['provider_seq'] != 1 && $v['provider_seq'] != 0) {
				if($v['super_manager_yn'] == 'Y'){
					$list[$k]['provider'] = '본사';
				} else {
					//$list[$k]['provider'] = $v['provider_name']." (".$v['provider_id'].")";
					$list[$k]['provider'] = $v['provider_name'];
				}
			} else {
				if($v['action_menu'] == 'selleradmin_login'){
					$list[$k]['provider']	= '입점사';
				} else {
					$list[$k]['provider']	= '본사';
				}
			}

			$list[$k]['manager_name']	= $v['manager_id']." (".$v['manager_name'].")";
			$list[$k]['regist_date']	= $v['regist_date'];
			$list[$k]['access_ip']		= $v['access_ip'];

			$no--;
		}

		$this->template->assign('loop', $list);
		$paginlay = pagingtag( $list_total, $perpage, "/admin/setting/manager_log?" );
		if( empty($paginlay) ){
			$paginlay = '<p><a class="on red">1</a><p>';
		}
		$this->template->assign('pagin', $paginlay);
		$this->template->define('manager_log_search', $this->skin.'/setting/manager_log_search.html');
		$this->template->define('manager_log_list', $this->skin.'/setting/manager_log_list.html');
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	public function manager_log_detail()
	{
        if($this->managerInfo['manager_yn'] != 'Y'){
			echo "ERROR_AUTH";
			exit;
		}

		$seq = $this->input->get('seq');
		if(!$seq || $seq <= 0){
			echo "ERROR_SEQ";
			exit;
		}

		$this->load->library('managerlog');

		$list = $this->db
			->select("action_menu, action_type, action_target, action_status, action_before, action_desc, action_menu_url, manager_id")
			->from('fm_manager_log')
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
            if($list['action_type'] == 'member' && $list['action_desc'] == '검색'){
				$data['type'] = 'search';
				if($v == 'searchcount'){
					$desc = $this->manager_log_detail_member($list['action_menu'], $params);
					if(is_array($desc) === false){
						$data['desc'] = $desc;
					} else {
						$data['desc']	= '<tr><td style="width:40%">검색  결과</td><td>'.$action_status[$k]."</td></tr>";
						if(count($desc) > 0){
							foreach($desc as $k => $v){
								$data['desc'] .= "<tr><td>".$k."</td><td>".$v."</td></tr>";
							}
						}
					}
				} else {
					$data['desc'] = "<tr><td>검색어</td><td>".$action_status[$k]."</td></tr>";
                }
			} else if($list['action_type'] == 'order' && $list['action_desc'] == '검색'){
				$data['desc'] = $this->manager_log_detail_order($list['action_menu'], $params);
			} else if($list['action_type'] == 'market_connector' && $list['action_desc'] == '검색'){
				$data['desc'] = $this->manager_log_detail_market_connector($list['action_menu'], $params);
			} else if($list['action_type'] == 'goods' && $list['action_desc'] == '검색'){
				$data['desc'] = $this->manager_log_detail_goods($list['action_menu'], $params);
			} else if($list['action_type'] == 'board' && strpos($list['action_desc'], '검색') !== false){
				$data['desc'] = $this->manager_log_detail_board($list['action_menu'], $params);
			} else if($list['action_type'] == 'promotion' && strpos($list['action_desc'], '검색') !== false){
				$data['desc'] = $this->manager_log_detail_promotion($list['action_menu'], $params);
			} else if($list['action_type'] == 'login' && $list['manager_id'] == 'Firstmall'){
				$data['desc'] = $list['action_target'];
				$data['type'] = 'dialog';
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

			$data[$k]['status'] = ($action_status[$k] == 'Y') ? '권한 있음' : '권한 없음';
			$data[$k]['before'] = ($action_before[$k] == 'Y') ? '권한 있음' : '권한 없음';;
		}

        if($data['type'] == 'list' && !$data['data']){
			$data['data'] = array();
		}

		echo json_encode($data);
		exit;
	}

	public function manager_log_detail_promotion($menu, $params)
	{
		$desc = array();

		switch($menu){
			case "joincheck_memberlist": //휴면 처리 리스트
				if(strlen($params['keyword']) > 0){
					$key_txt = $params['keyword'];
				} else {
					$key_txt = '-';
				}

				if($params['clear_success'][0] == 'N'){
					$clear_success = '미달성';
				} else if($params['clear_success'][0] == 'Y'){
					$clear_success = '달성';
				} else {
					$clear_success = '전체';
				}

				if($params['emoney_pay'][0] == 'N'){
					$emoney_pay = '미지급';
				} else if($params['emoney_pay'][0] == 'Y'){
					$emoney_pay = '지급';
				} else {
					$emoney_pay = '전체';
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>상태</td><td>".$clear_success."</td></tr>";
				$desc .= "<tr><td>마일리지</td><td>".$emoney_pay."</td></tr>";
			break;

			default:
				if(strlen($params['search_text']) > 0){
					$key_txt = $params['search_text'];
				} else {
					$key_txt = '-';
				}

				if($params['use_status'] == 'used'){
					$use_status = '사용';
				} else if($params['use_status'] == 'unused'){
					$use_status = '미사용';
				} else if($params['use_status'] == 'expire'){
					$use_status = '유효기간 만료';
				} else {
					$use_status = '전체';
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>발급일</td><td>".$params['sdate']." ~ ".$params['edate']."</td></tr>";
				$desc .= "<tr><td>사용 여부</td><td>".$use_status."</td></tr>";
			break;
		}

		return $desc;
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

		if($params['taxView'] == 'tax') {
			$tax_txt = "과세";
		} else if($params['taxView'] == 'exempt') {
			$tax_txt = "비과세";
		} else {
			$tax_txt = "전체";
		}

		if($params['price_gb'] == 'price') {
			$price_txt = "판매가";
		} else if($params['price_gb'] == 'consumer_price') {
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

	public function manager_log_detail_market_connector($menu, $params)
	{
		$desc = array();

		if($params['dateType'] == 'registeredTime'){
			$date_txt = '수집일';
		} else if($params['dateType'] == 'fmOrderSaveTime'){
			$date_txt = '퍼스트몰 주문 등록일';
		} else if($params['dateType'] == 'settleTime'){
			$date_txt = '결제일';
		}

		if(strlen($params['sellerId']) > 0){
			$seller_id = $params['sellerId'];
		} else {
			$seller_id = '-';
		}

		if($params['searchType'] == 'fmOrderSeq'){
			$search_txt = '쇼핑몰 주문번호';
		} else if($params['searchType'] == 'marketOrderNo'){
			$search_txt = '마켓주문번호';
		} else if($params['searchType'] == 'fmClaimCode'){
			if($params['now_claim_type'] == 'RTN'){
				$search_txt = '쇼핑몰 반품 번호';
			} else if($params['now_claim_type'] == 'CAN'){
				$search_txt = '쇼핑몰 환불 번호';
			} else if($params['now_claim_type'] == 'EXC'){
				$search_txt = '쇼핑몰 교환 번호';
			}
		}

		if(strlen($params['keyword']) > 0){
			$key_txt = $params['keyword'];
		} else {
			$key_txt = '-';
		}

		$status_txt = array();
		if($menu == 'market_order_list'){
			if($params['status'] == 'ORD10'){
				$status_txt = '결제완료';
			} else if($params['status'] == 'ORD20'){
				$status_txt = '배송준비';
			} else if($params['status'] == 'ORD40'){
				$status_txt = '배송완료';
			} else if($params['status'] == 'CAN10'){
				$status_txt = '취소완료';
			} else {
				$status_txt = '전체';
			}
		} else {
			if($params['status'] == 'CAN00'){
				$status_txt = '취소 요청';
			} else if($params['status'] == 'CAN10'){
				$status_txt = '취소 완료';
			} else if($params['status'] == 'RTN00'){
				$status_txt = '반품 요청';
			} else if($params['status'] == 'RTN10'){
				$status_txt = '반품 완료';
			} else if($params['status'] == 'EXC00'){
				$status_txt = '교환 요청';
			} else if($params['status'] == 'EXC10'){
				$status_txt = '교환 완료';
			} else {
				$status_txt = '전체';
			}
		}

		$desc = "<tr><td>".$date_txt."</td><td>".$params['searchBeginDate']." ~ ".$params['searchEndDate']."</td></tr>";
		$desc .= "<tr><td>판매자 아이디</td><td>".$seller_id."</td></tr>";
		$desc .= "<tr><td>".$search_txt."</td><td>".$key_txt."</td></tr>";
		$desc .= "<tr><td>주문 상태</td><td>".$status_txt."</td></tr>";

		return $desc;
	}

	public function manager_log_detail_member($menu, $params)
	{
		$desc = array();
		switch($menu){
			case "dormancy_catalog": //휴면 처리 리스트
				if ( strlen(trim($params['keyword'])) > 0 ) {
					$desc['아이디'] = $params['keyword'];
				} else {
					$desc['아이디'] = "-";
				}

				if ($params['select_date_regist'] == 'all') {
					$desc['휴면일'] = "전체";
				} else {
					$desc['휴면일'] = $params['regist_sdate']." ~ ".$params['regist_edate'];
				}

				if($params['status'] == 'on') {
					$desc['구분'] = "휴면";
				} else if($params['status'] == 'off') {
					$desc['구분'] = "휴면 해제";
				} else {
					$desc['구분'] = "전체";
				}
			break;

			case "withdrawal": //탈퇴 리스트
				if ( strlen(trim($params['keyword'])) > 0 ) {
					$desc['아이디'] = $params['keyword'];
				} else {
					$desc['아이디'] = "-";
				}

				if ($params['select_date_regist'] == 'all') {
					$desc['탈퇴일'] = "전체";
				} else {
					$desc['탈퇴일'] = $params['sdate']." ~ ".$params['edate'];
				}
			break;

			case "kakaotalk_log": //알림톡 발송 내역
				if(!$params['s_date'] && !$params['e_date']){
					$desc['기간'] = "전체";
				} else {
					$desc['기간'] = $params['s_date']." ~ ".$params['e_date'];
				}

				if($params['status_yn'] == 'Y') {
					$desc['전송 결과'] = "성공";
				} else if($params['status_yn'] == 'N') {
					$desc['전송 결과'] = "실패";
				} else {
					$desc['전송 결과'] = "전체";
				}

				if ( strlen(trim($params['kkoBizCode'])) > 0 ) {
					$this->load->model('kakaotalkmodel');
					$code_list = $this->kakaotalkmodel->get_template(array('kkoBizCode' => $params['kkoBizCode']));
					$code_list = array_shift(array_shift($code_list));
					$desc['발송 상황'] = $code_list['msg_txt'];
				} else {
					$desc['발송 상황'] = "전체";
				}

				if ( strlen(trim($params['mobile'])) > 0 ) {
					$desc['수신 번호'] = $params['mobile'];
				} else {
					$desc['수신 번호'] = "-";
				}
			break;

			case "email_history": //이메일 발송
				if ( strlen(trim($params['sc_subject'])) > 0 ) {
					$desc['제목'] = $params['sc_subject'];
				} else {
					$desc['제목'] = "-";
				}

				if ($params['select_date_regist'] == 'all') {
					$desc['발송일'] = "전체";
				} else {
					$desc['발송일'] = $params['start_date']." ~ ".$params['end_date'];
				}
			break;

			case "email_history": //이메일 대량 발송
				if ( strlen(trim($params['keyword'])) > 0 ) {
					$desc['검색어'] = $params['keyword'];
				} else {
					$desc['검색어'] = "-";
				}

				if($params['sc_day_type'] == 'lastlogin'){
					$date_txt = "최종방문일";
					if ($params['lastlogin_search_type'] == 'in') {
						$date_txt2 = " 이내 방문";
					} else {
						$date_txt2 = " 이내 미방문";
					}
				} else {
					$date_txt = "가입일";
					$date_txt2 = "";
				}
				if($params['select_date_regist'] == 'all'){
					$desc[$date_txt] = "전체";
				} else {
					$desc[$date_txt] = $params['regist_sdate']." ~ ".$params['regist_edate'].$date_txt2;
				}

				if ( $params['grade'] > 0 && is_numeric($params['grade']) ) {
					$this->load->model('membermodel');
					$grade_list = $this->membermodel->get_member_group_info($params['grade']);
					$desc['등급'] = $grade_list['group_name'];
				} else {
					$desc['등급'] = "전체";
				}

				if($params['mailing'] == 'y') {
					$desc['이메일 수신'] = "동의";
				} else if($params['status'] == 'n') {
					$desc['이메일 수신'] = "거부";
				} else {
					$desc['이메일 수신'] = "전체";
				}
			break;

			case "curation_history_sms": //리마인드 sms
			case "curation_history_email": //리마인드 email
				if ( strlen(trim($params['sc_subject'])) > 0 ) {
					$desc['제목'] = $params['sc_subject'];
				} else {
					$desc['제목'] = "-";
				}

				if($params['select_date_regist'] == 'all'){
					$desc['발송일'] = "전체";
				} else {
					$desc['발송일'] = $params['start_date']." ~ ".$params['end_date'];
				}

				if ( $this->managerlog->list_sc_kind[$params['sc_kind']] ) {
					$desc['리마인드 종류'] = $this->managerlog->list_sc_kind[$params['sc_kind']];
				} else {
					$desc['리마인드 종류'] = "전체";
				}
			break;

			case "member_catalog": //고객 crm 회원 검색
				if ( strlen(trim($params['body_crm_search_keyword'])) > 0 ) {
					$desc['회원'] = $params['body_crm_search_keyword'];
				} else {
					$desc['회원'] = "-";
				}
			break;

			case "order_catalog":
			case "order_catalog_ajax_ajax": //고객 crm 주문 검색
				$status_config	= config_load('step');
				$pay_config		= config_load('payment');

				if($params['keyword']){
					$key_txt = $params['keyword'];
				} else {
					$key_txt = '-';
				}

				if($params['date_field'] == 'regist_date'){
					$date_txt = '주문일';
				} else {
					$date_txt = '입금일';
				}

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

				$payment_txt = array();
				$i = 0;
				foreach($params['payment'] as $k => $v){
					if($i%7 == 0 && $i > 0){
						$payment_txt[$k] = "<br>".$pay_config[$v];;
					} else {
						$payment_txt[$k] =  $pay_config[$v];
					}
					$i++;
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>".$date_txt."</td><td>".$params['regist_date'][0]." ~ ".$params['regist_date'][1]."</td></tr>";
				$desc .= "<tr><td>상태</td><td>".implode(', ', $status_txt)."</td></tr>";
				$desc .= "<tr><td>결제수단</td><td>".implode(', ', $payment_txt)."</td></tr>";

			break;

			case "order_return_catalog":
			case "order_refund_catalog":
				if($menu == 'order_return_catalog'){
					$status = $params['return_status'];
					$menu_txt = '반품';
				} else if($menu == 'order_refund_catalog'){
					$status = $params['refund_status'];
					$menu_txt = '환불';
				}

				if($params['date_field'] == 'ref.regist_date'){
					$date_txt = $menu_txt.'신청일';
				} else {
					$date_txt = $menu_txt.'완료일';
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

				$desc = "<tr><td>".$date_txt."</td><td>".$params['sdate']." ~ ".$params['edate']."</td></tr>";
				$desc .= "<tr><td>상태</td><td>".implode(', ', $status_txt)."</td></tr>";
			break;

			case "board_review_catalog":
			case "board_qna_catalog":
			case "board_mbqna_catalog":
				if($params['search_text']){
					$key_txt = $params['search_text'];
				} else {
					$key_txt = '-';
				}
				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>날짜</td><td>".$params['sdate']." ~ ".$params['edate']."</td></tr>";

				if($menu == 'board_review_catalog'){
					$status_txt = array();
					foreach($params['gb'] as $k => $v){
						if($v == 'buyed'){
							$status_txt[$k] =  '구매한 후기';
						} else if($v == 'unbuyed'){
							$status_txt[$k] =  '미구매 후기';
						} else if($v == 'best'){
							$status_txt[$k] =  '베스트 후기';
						}
					}
					$desc .= "<tr><td>상태</td><td>".implode(', ', $status_txt)."</td></tr>";
				}
			break;

			case "board_counsel_catalog":
				if($params['dateType'] == 'counsel_regdate'){
					$date_txt = '상담일';
				} else {
					$date_txt = '처리일';
				}
				$desc = "<tr><td>".$date_txt."</td><td>".$params['sdate']." ~ ".$params['edate']."</td></tr>";
				$desc .= "<tr><td>상담자</td><td>".$params['manager_name']."</td></tr>";

				$status_txt = array();
				foreach($params['counsel_status'] as $k => $v){
					if($v == 'request'){
						$status_txt[$k] =  '미처리';
					} else if($v == 'ing'){
						$status_txt[$k] =  '처리중';
					} else if($v == 'complete'){
						$status_txt[$k] =  '처리';
					}
				}
				$desc .= "<tr><td>상태</td><td>".implode(', ', $status_txt)."</td></tr>";

				if($params['relationType'] == 'order_seq'){
					$num_txt = '주문번호';
				} else if($params['relationType'] == 'export_code'){
					$num_txt = '출고번호';
				} else if($params['relationType'] == 'return_code'){
					$num_txt = '반품번호';
				} else if($params['relationType'] == 'refund_code'){
					$num_txt = '환불번호';
				} else if($params['relationType'] == 'goods_qna_seq'){
					$num_txt = '상품품의';
				} else if($params['relationType'] == 'goods_review_seq'){
					$num_txt = '상품후기';
				} else if($params['relationType'] == 'parent_counsel_seq'){
					$num_txt = '상담번호';
				}
				$desc .= "<tr><td>".$num_txt."</td><td>".$params['relationCode']."</td></tr>";

				if($params['search_text']){
					$key_txt = $params['search_text'];
				} else {
					$key_txt = '-';
				}
				$desc .= "<tr><td>상담내용</td><td>".$key_txt."</td></tr>";
			break;

			default:
				if ( strlen(trim($params['keyword'])) > 0 ) {
					$desc['검색어'] = $params['keyword'];
				} else {
					$desc['검색어'] = "-";
				}

				if($params['sc_day_type'] == 'lastlogin'){
					$date_txt = "최종방문일";
					if ($params['lastlogin_search_type'] == 'in') {
						$date_txt2 = " 이내 방문";
					} else {
						$date_txt2 = " 이내 미방문";
					}
				} else {
					$date_txt = "가입일";
					$date_txt2 = "";
				}
				if($params['select_date_regist'] == 'all'){
					$desc[$date_txt] = "전체";
				} else {
					$desc[$date_txt] = $params['regist_sdate']." ~ ".$params['regist_edate'].$date_txt2;
				}

				if($params['sc_money_type'] == 'point'){
					$emoney_txt = "포인트";
				} else if($params['sc_money_type'] == 'cash'){
					$emoney_txt = "예치금";
				} else {
					$emoney_txt = "마일리지";
				}
				if(!$params['eemoney']){
					$desc[$emoney_txt] = "전체";
				} else {
					$desc[$emoney_txt] = $params['semoney']." ~ ".$params['eemoney'];
				}

				if($params['sc_count_type'] == 'review_cnt'){
					$login_txt = "리뷰수";
				} else {
					$login_txt = "방문수";
				}
				if(!$params['elogin_cnt']){
					$desc[$login_txt] = "전체";
				} else {
					$desc[$login_txt] = $params['slogin_cnt']." ~ ".$params['elogin_cnt'];
				}

				if($params['sc_specialDay_type'] == 'anniversary'){ //기념일
					if($params['select_date_birthday'] == 'all'){
						$desc['기념일'] = "전체";
					} else {
						$desc['기념일'] = $params['anniversary_sdate'][0]."-".$params['anniversary_sdate'][1]." ~ ".$params['anniversary_edate'][0]."-".$params['anniversary_edate'][1];
					}
				} else { //생일
					if($params['birthday_year_except'] == 'Y'){
						$birth_txt2 = " (연도 제외)";
					} else {
						$birth_txt2 = " (연도 포함)";
					}

					if($params['select_date_birthday'] == 'all'){
						$desc['생일'] = "전체";
					} else {
						$desc['생일'] = $params['birthday_sdate']." ~ ".$params['birthday_edate'].$birth_txt2;
					}
				}

				if($params['provider_seq_selector'] == 'all'){
					$desc['단골 미니샵'] = "전체";
				} else {
					$this->load->model('providermodel');
					$provider_info = $this->providermodel->get_provider($params['provider_seq']);
					$desc['단골 미니샵'] = $provider_info['provider_name'];
				}

				$searchList = $this->managerlog->searchList;
				foreach($params as $k => $v){
					if ($searchList[$k]) {
						if (strlen(trim($v)) > 0 || is_numeric($v)) {
							if($k == 'sitetype'){
								$v = $this->managerlog->sitetype[$v];
							}

							if($k == 'snsrute'){
								$v = $this->managerlog->snsrute[$v];
							}

							if($k == 'sms' || $k == 'mailing'){
								if ($v == 'y') {
									$v = '동의';
								} else if ($v == 'n') {
									$v = '거부';
								} else {
									$v = '전체';
								}
							}

							if($k == 'business_seq'){
								if ($v == 'y') {
									$v = '사업자';
								} else if ($v == 'n') {
									$v = '개인';
								} else {
									$v = '전체';
								}
							}

							if($k == 'status'){
								if($v == 'done'){
									$v = '승인';
								} else if($v == 'hold'){
									$v = '미승인';
								} else if($v == 'dormancy'){
									$v = '휴면';
								}  else {
									$v = '전체';
								}
							}

							if($k == 'sex'){
								if($v == 'male'){
									$v = '남성';
								} else if($v == 'female'){
									$v = '여성';
								}  else {
									$v = '전체';
								}
							}

							if($k == 'mall_t_check'){
								if($v == 'Y'){
									$v = '테스트용 회원만 검색';
								}  else {
									$v = '테스트용 회원 포함 검색';
								}
							}

							if($k == 'grade'){
								$this->load->model('membermodel');
								$grade_list = $this->membermodel->get_member_group_info($v['group_seq']);
								$v = $grade_list['group_name'];
							}

							if($desc[$searchList[$k]] || is_numeric($desc[$searchList[$k]])){
								$desc[$searchList[$k]] .= ' ~ '.$v;
							} else {
								$desc[$searchList[$k]] = $v;
							}
						} else {
							$desc[$searchList[$k]] = '전체';
						}
					}
				}
			break;
		}

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

			case "personal":
				if(strlen($params['keyword']) > 0){
					$key_txt = $params['keyword'];
				} else {
					$key_txt = '-';
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>주문일</td><td>".$params['regist_date'][0]." ~ ".$params['regist_date'][1]."</td></tr>";
			break;

			case "temporary":
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

				$pay_config	= config_load('payment');
				$payment_txt = array();
				$i = 0;
				foreach($params['payment'] as $k => $v){
					if($i%7 == 0 && $i > 0){
						$payment_txt[$k] = "<br>".$pay_config[$k];;
					} else {
						$payment_txt[$k] =  $pay_config[$k];
					}
					$i++;
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>주문일</td><td>".$params['regist_date'][0]." ~ ".$params['regist_date'][1]."</td></tr>";
				$desc .= "<tr><td>출고 전</td><td>".implode(', ', $status_txt)."</td></tr>";
				$desc .= "<tr><td>결제수단</td><td>".implode(', ', $payment_txt)."</td></tr>";
			break;

			case "sales":
				if(strlen($params['keyword']) > 0){
					$key_txt = $params['keyword'];
				} else {
					$key_txt = '-';
				}

				if($params['date_gb'] == 'all'){
					$date_txt = '신청/주문일';
				} else if($params['date_gb'] == 'order_date'){
					$date_txt = '주문일';
				} else if($params['date_gb'] == 'regdate'){
					$date_txt = '신청일';
				} else if($params['date_gb'] == 'up_date'){
					$date_txt = '처리확정일';
				}

				if($params['date_gb'] == 'order_date'){
					$date_txt = '주문일';
				} else if($params['date_gb'] == 'regdate'){
					$date_txt = '신청일';
				} else if($params['date_gb'] == 'up_date'){
					$date_txt = '처리확정일';
				}

				$type_txt = array();
				if($params['typereceipt'][0]){
					$type_txt[] = '매출전표';
				}
				if($params['typereceipt'][1]){
					$type_txt[] = '세금계산서';
				}
				if($params['typereceipt'][1]){
					$type_txt[] = '현금영수증';
				}

				$admin_txt = array();
				if($params['admin_type'][0]){
					$admin_txt[] = '구매자';
				}
				if($params['admin_type'][1]){
					$admin_txt[] = '관리자';
				}
				if($params['admin_type'][1]){
					$admin_txt[] = '자진 발급';
				}

				$ostep_txt = array();
				if($params['ostep'][0]){
					$ostep_txt[] = '입금';
				}
				if($params['ostep'][1]){
					$ostep_txt[] = '미입금';
				}

				$orefund_txt = array();
				if($params['orefund'][0]){
					$orefund_txt[] = '있음';
				}
				if($params['orefund'][1]){
					$orefund_txt[] = '없음';
				}

				$status_txt = array();
				if($params['tstep'][0]){
					$status_txt[] = '대기';
				}
				if($params['tstep'][1]){
					$status_txt[] = '완료(연동)';
				}
				if($params['tstep'][2]){
					$status_txt[] = '완료(미연동)';
				}
				if($params['tstep'][3]){
					$status_txt[] = '취소';
				}
				if($params['tstep'][4]){
					$status_txt[] = '완료(연동실패)';
				}

				$desc = "<tr><td>검색어</td><td>".$key_txt."</td></tr>";
				$desc .= "<tr><td>".$date_txt."</td><td>".$params['sdate']." ~ ".$params['edate']."</td></tr>";
				$desc .= "<tr><td>증빙서류</td><td>".implode(', ', $type_txt)."</td></tr>";
				$desc .= "<tr><td>신청구분</td><td>".implode(', ', $admin_txt)."</td></tr>";
				$desc .= "<tr><td>결제여부</td><td>".implode(', ', $ostep_txt)."</td></tr>";
				$desc .= "<tr><td>환불유무</td><td>".implode(', ', $orefund_txt)."</td></tr>";
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
						$status_txt[] = '-';
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
					$status_txt[] = '-';
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
					$status_txt[] = '-';
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

					$sitetypeloop = $this->managerlog->sitetype;
					$sitetype_txt = array();
					foreach($params['sitetype'] as $v){
						$sitetype_txt[] = $sitetypeloop[$v];
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

		if($provider_seq == 1){
			//목록
			$query = $this->db
				->select("*")
				->from('fm_manager')
				->order_by('manager_id ASC')
				->get();
		} else {
			$where_and['provider_id !='] = 'base';
			$where_or = "provider_group = '".$provider_seq."' or provider_seq = '".$provider_seq."'";

			//목록
			$query = $this->db
				->select("provider_seq as manager_seq, provider_name as mname, provider_id as manager_id")
				->from('fm_provider')
				->where($where_and)
				->where($where_or)
				->order_by('manager_id ASC')
				->get();
		}

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

	public function get_instagram_use()
	{
		$this->load->library('instagramlibrary');
		$instagram = $this->instagramlibrary->getConfig();

		echo json_encode(['use' => $instagram['use']]);
	}
}
/* End of file setting.php */
/* Location: ./app/controllers/admin/setting.php */
