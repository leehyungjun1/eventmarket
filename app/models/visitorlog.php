<?php
class visitorlog extends CI_Model {

	var $current_date;
	var $current_year;
	var $current_month;
	var $current_day;
	var $current_hour;
	var $current_ip;
	var $referer_flag;
	var $platform;

	var $arr_referer_sitecd = array(
		'basket.co.kr'	=> '바스켓',
		'yozm.daum.net'	=> 'yozm',
		'shopping.daum.net'	=> 'daum_shopping',
		'daum.net'		=> 'daum',
		'c.cyworld.com'	=> 'clog',
		'naver.com'		=> 'naver',
		'facebook.com'	=> 'facebook',
		'twitter.com'	=> 'twitter',
		'me2day.net'	=> 'me2day',
		'google.com'	=> 'google',
		'google.co.kr'	=> 'google',
		'nate.com'		=> 'nate',
	);

	var $arr_referer_sitecd_name = array(
		'about'			=> '어바웃',
		'yozm'			=> '요즘',
		'daum'			=> '다음',
		'daum_shopping' => '다음쇼핑하우',
		'clog'			=> 'C로그',
		'naver'			=> '네이버',
		'naver_shopping' => '네이버지식쇼핑',
		'facebook'		=> '페이스북',
		'twitter'		=> '트위터',
		'me2day'		=> '미투데이',
		'google'		=> '구글',
		'nate'			=> '네이트',
		'nate_keyword'	=> '네이트키워드',
		'naver_keyword'	=> '네이버키워드',
		'daum_keyword'	=> '다음키워드',
		'google_keyword'=> '구글키워드',
		'etc'			=> '기타사이트',
		'direct'		=> '직접유입',
	);

	function __construct() {
		parent::__construct();

		$this->load->helper('cookie');
		$this->load->library('elasticsearch');

		$this->current_ip = $_SERVER['REMOTE_ADDR'];
		$this->current_date = date('Y-m-d');
		$this->current_hour = date('H');

		list(
			$this->current_year,
			$this->current_month,
			$this->current_day
		) = explode('-',$this->current_date);

		$this->platform	= 'P';
		if( $this->fammerceMode ) {
			$this->platform	= 'F';
		}else if( $this->_is_mobile_agent || $this->mobileMode || $this->storemobileMode ) {
			$this->platform	= 'M';
		}
	}

	function get_arr_referer_sitecd_name(){
		$data = $this->arr_referer_sitecd_name;

		/* 추가광고매체 타이틀 */
		$query = $this->db->query("select * from fm_inflow");
		$result = $query->result_array();
		foreach($result as $row){
			$data[$row['inflow_code']] = $row['title'];
		}

		return $data;
	}

	function execute(){
		/* 통계수집 제외 아이피 체크 */
		if($this->is_exclude_ip()) return;

		/* robots 체크 */
		if($this->is_robots_agent()) return;
		if($this->is_robots_page()) return;

		$isFirst = false;

		/* 리퍼러 저장 */
		$this->set_referer();

		/* 페이지뷰 증가 */
		if($this->db->es_use === true){
			$this->add_pv_count_es();
			if($this->first_connection_es()){
				$this->add_visit_count_es();

				$isFirst = true;
			}
		} else {
			$this->add_pv_count();
			if($this->first_connection()){
				$this->add_visit_count();
				$this->add_platform();

				$isFirst = true;
			}
		}

		if($isFirst === true){
			$this->set_visitor_cookie();
		}
	}

	/* BOT 접속 페이지들 체크 */
	public function is_robots_page(){
		$robotsPages	= array('payment', 'partner','_batch');
		if($robotsPages && preg_match("/^\/(".implode("|",$robotsPages).")/i",$_SERVER['REQUEST_URI'])) return true;

		return false;
	}

	/* robots 체크 */
	public function is_robots_agent(){
		/* v20130408 */
		$robotsAgents = array('yeti','naverbot','googlebot','msnbot','slurp','yandexbot','gigabot','teoma','twiceler','scrubby','robozilla','nutch','baiduspider','webauto','mmcrawler','yahoo-blog','psbot','cowbot','daumos','daumoa','mj12bot','bingbot','blexbot','ahrefsbot','megaindex','dotbot','ru_bot','adsbot-naver','duckduckgo-favicons-bot','siteexplorer','yandeximages');
		if($robotsAgents && preg_match("/(".implode("|",$robotsAgents).")/i",$_SERVER['HTTP_USER_AGENT'])) return true;
		return false;
	}

	/* 통계수집 제외 아이피 체크 */
	public function is_exclude_ip(){
		$statisticExcludeIps = explode("\n",$this->config_system['statisticExcludeIp']);
		foreach($statisticExcludeIps as $statisticExcludeIp){
			if($statisticExcludeIp && preg_match("/^".$statisticExcludeIp."/",$this->current_ip)){
				return true;
			}
		}
		return false;
	}

	/* 페이지뷰 증가 */
	function add_pv_count(){

		$hourField = "h".$this->current_hour;

		$query = $this->db->query("select * from fm_stats_visitor_count where count_type='pv' and stats_date=?",$this->current_date);
		$result = $query->row_array();

		if($result['stats_date']){
			$countSum = 0;
			for($i=0;$i<24;$i++) $countSum += $result['h'.sprintf("%02d",$i)];
			$countSum += 1;

			$this->db->query("update fm_stats_visitor_count set `{$hourField}`=ifnull(`{$hourField}`,0)+1, count_sum=? where stats_date=? and count_type=?",array($countSum,$this->current_date,'pv'));
		}else{
			$data = array(
				'stats_date'	=> $this->current_date,
				'stats_year'	=> $this->current_year,
				'stats_month'	=> $this->current_month,
				'stats_day'		=> $this->current_day,
				'count_type'	=> 'pv',
				'count_sum'		=> 1,
				$hourField		=> 1
			);
			$this->db->insert('fm_stats_visitor_count', $data);
		}

	}

	function add_pv_count_es(){	
		$referer = $this->set_referer();

		/*session->cookie 변경*/
		setcookie('shopReferer', $referer, 0, '/');

		// 유입도메인
		$refererDomain = $this->get_referer_domain($referer);
		$_COOKIE['refererDomain'] = $refererDomain;
		setcookie('refererDomain', $refererDomain, 0, '/');

		//유입매체
		$referer_sitecd = $this->get_referer_sitecd($referer);
		if (!$_COOKIE['marketplace'] && $referer_sitecd) {
			setcookie('marketplace', $referer_sitecd, 0, '/');
		}

		$tmp	= parse_url($referer);
		$domain	= $tmp['host'];
		$domain	= preg_replace('/^(www\.|m\.)/', '', $domain);

		$cid = $this->elasticsearch->index_check('stats_visitor');
		if($cid !== false){
			$params = [
				'index' => $cid.'_stats_visitor',
				'type'	=> 'count',
				'body'	=> [
					'cid'			=> $cid,
					'count_type'	=> 'pv',
					'ip'			=> $this->current_ip,
					'user_agent'	=> $_SERVER['HTTP_USER_AGENT'],
					'referer_domain'=> $domain, 
					'referer'		=> $referer,
					'referer_sitecd'=> $referer_sitecd,
					'referer_pre'	=> $_SERVER['HTTP_REFERER'],
					'platform'		=> $this->platform,	
					'request_uri'	=> $_SERVER['REQUEST_URI'],
					'post_date'		=> date('Y-m-d').'T'.date('H:i:s')
				]
			];

			$this->elasticsearch->esClientM->index($params);
		}
	}
	
	/* 오늘 첫접속 여부 체크 */
	function first_connection(){
		$visitorInfo = unserialize(get_cookie('visitorInfo'), ['allowed_classes' => false]);

		// 쿠키가 있고, 오늘날짜와 같으면 첫접속아님
		if(!empty($visitorInfo) && $visitorInfo['date']==date('Y-m-d')) return false;

		// IP테이블에 데이터가 있으면 첫접속 아님
		if(get_data("fm_stats_visitor_ip",array("ip_address"=>$this->current_ip,"stats_date"=>$this->current_date))) return false;

		// 첫접속
		return true;
	}

	function first_connection_es(){
		$visitorInfo = unserialize(get_cookie('visitorInfo'), ['allowed_classes' => false]);

		//쿠키가 있고, 오늘날짜와 같으면 첫접속아님
		if(!empty($visitorInfo) && $visitorInfo['date']==date('Y-m-d')){
			return false;
		}

		$cid = $this->elasticsearch->index_check('stats_visitor');
		if($cid !== false){
			$params = [
				'index' => $cid.'_stats_visitor',
				'type'	=> 'count',
				'body'	=> [
					'query' => [
						'bool' => [
							'must' => [
								'match' => [
									'ip' => $this->current_ip
								],
							],
							'filter' => [
								'range' => [
									'post_date' => [
											'gte' => date('Y-m-d').'T00:00:00'	
									]	
								]
							]
						]
					]
				]
			];

			$response = $this->elasticsearch->esClientR->search($params);
			if($response['hits']['total'] > 0){
				return false;
			}
		}

		// 첫접속
		return true;
	}

	/* 첫접속시 접속자정보 쿠키 생성 */
	function set_visitor_cookie(){
		$visitorInfo = array(
			'date'=>date('Y-m-d'),
			'referer'=>$_SERVER['HTTP_REFERER']
		);
		set_cookie('visitorInfo',serialize($visitorInfo),86400);
		
		// IP테이블에 데이터 저장
		$data = array(
			'stats_date'	=> $this->current_date,
			'ip_address'	=> $this->current_ip,
			'user_agent'	=> $_SERVER['HTTP_USER_AGENT'],
			'referer'		=> $_SERVER['HTTP_REFERER'],
		);
		$this->db->insert('fm_stats_visitor_ip', $data);

	}

	/* 첫접속시 방문자수 증가 */
	function add_visit_count(){
		$hourField = "h".$this->current_hour;

		$query = $this->db->query("select * from fm_stats_visitor_count where count_type='visit' and stats_date=?",$this->current_date);
		$result = $query->row_array();

		if($result['stats_date']){
			$countSum = 0;
			for($i=0;$i<24;$i++) $countSum += $result['h'.sprintf("%02d",$i)];
			$countSum += 1;

			$this->db->query("update fm_stats_visitor_count set `{$hourField}`=ifnull(`{$hourField}`,0)+1, count_sum=? where stats_date=? and count_type=?",array($countSum,$this->current_date,'visit'));
		}else{

			$data = array(
				'stats_date'	=> $this->current_date,
				'stats_year'	=> $this->current_year,
				'stats_month'	=> $this->current_month,
				'stats_day'		=> $this->current_day,
				'count_type'	=> 'visit',
				'count_sum'		=> 1,
				$hourField		=> 1
			);
			$this->db->insert('fm_stats_visitor_count', $data);
		}
	}
	
	/* 첫접속시 방문자수 증가 */
	function add_visit_count_es(){
		$referer		= $this->set_referer();

		// 유입도메인
		$refererDomain	= $this->get_referer_domain($referer);

		//유입매체
		$referer_sitecd	= $this->get_referer_sitecd($referer);

		$tmp	= parse_url($referer);
		$domain	= $tmp['host'];
		$domain	= preg_replace('/^(www\.|m\.)/', '', $domain);

		$cid = $this->elasticsearch->index_check('stats_visitor');
		if($cid !== false){
			$params = [
				'index' => $cid.'_stats_visitor',
				'type'	=> 'count',
				'body'	=> [
					'cid'			=> $cid,
					'count_type'	=> 'visit',
					'ip'			=> $this->current_ip,
					'user_agent'	=> $_SERVER['HTTP_USER_AGENT'],
					'platform'		=> $this->platform,	
					'referer_domain'=> $domain, 
					'referer'		=> $referer,
					'referer_sitecd'=> $referer_sitecd,
					'referer_pre'	=> $_SERVER['HTTP_REFERER'],
					'request_uri'	=> $_SERVER['REQUEST_URI'],
					'post_date'		=> date('Y-m-d').'T'.sprintf('%02d', (date('H') - 9)).date(':i:s')
				]
			];

			$this->elasticsearch->esClientM->index($params);
		}
	}

	/* 접속시 리퍼러 저장 */
	function set_referer(){

		// 통계 제외 대상 체크
		if($this->is_exclude_ip())		return;
		if($this->is_robots_agent())	return;
		if($this->is_robots_page())		return;

		if	($this->referer_flag != 'y'){
			$this->referer_flag	= 'y';

			$referer		= !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
			$referer_sitecd	= '';
			$save_flag		= false;
			$old_referer	= $_COOKIE['shopReferer'];
			if	(!$old_referer && $_COOKIE['shopReferer']){
				$old_referer	= $_COOKIE['shopReferer'];
				/*session -> cookie 변경*/
				setcookie('shopReferer', $old_referer, 0, '/');
			}

			## naver 추적URL 통한 접속시 "네이버 검색 광고" 로 변환.
			if(preg_match('/^http[s]*\:\/\/(m\.)*[^\.]*\.naver/',$referer) && strlen($_GET['NVAR'])>0){
				$referer = str_replace("search","adcr",$referer);
			}

			// 1. referer가 있나?
			if	($referer){
				$refererParsed		= parse_url($referer);
				$refererParsed_han	= preg_replace('/(http|https)*\:\/\/([^\/]*)\/.*/', '$2', $referer);
				$chk_now_host		= preg_replace("/^(www|m)\./i","",$_SERVER['HTTP_HOST']);
				$chk_referer_host	= preg_replace("/^(www|m)\./i","",$refererParsed['host']);
				$chk_config_host	= preg_replace("/^(www|m)\./i","",$this->config_system['domain']);
				
				//한글 도메인일경우 Punocode 변환
				$this->load->library('punycode');
				if	(preg_match('/[가-힣]/', $chk_now_host))		
					$chk_now_host		= $this->punycode->encodeHostName($chk_now_host);
				if	(preg_match('/[가-힣]/', $refererParsed_han))
					$chk_referer_host	= $this->punycode->encodeHostName($refererParsed_han);

				if($chk_now_host == $chk_referer_host)
					$referer=''; // 쇼핑몰 내에서 이동시에는 referer 직접입력으로 처리
				if($chk_config_host && $chk_config_host==$chk_referer_host)
					$referer=''; // 쇼핑몰 내에서 이동시에는 referer 직접입력으로 처리

				// 예외 referer_url 체크
				if($referer) {
					$except_check = $this->referer_except_check($chk_referer_host);
					if($except_check == false) $referer='';
				}

				// 2. host와 같나?
				if	($referer){

					// 3. session이 있나?
					if		(!$old_referer)				$save_flag	= true;
					// 3-3. session의 referer와 현재 referer가 같나?
					elseif	($referer != $old_referer)	$save_flag	= true;

				// 2-2. session이 있나?
				}//elseif	(!$old_referer)					$save_flag	= true;

			// 1-1. session이 있나?
			}elseif	(!$old_referer && !isset($_COOKIE['shopReferer']))	$save_flag	= true;

			// 아이프레임 예외처리
			if($_GET['iframe'] || $_GET['firstmallcartid'])
				$save_flag = false;

			// 새로운 session 생성 및 유입경로 log 저장
			if	($save_flag){
				$this->save_referer_log($referer);
			}
		}
	}

	/*
	* 예외 referer 체크
	* .firstmall.kr , 실명인증, 결제모듈을 갖다오면 referer 가 바뀜
	* 추가적으로 발생하는 예외처리해야하는 referer 는 fm_referer_except 데이터 추가하면 됨
	* result (true : 예외처리하지않고 referer 저장 , false : 예외처리함)
	* 2020-03-20
	*/
	public function referer_except_check($referer_host) {
		$result = false;

		$sql = "SELECT * FROM fm_referer_except WHERE referer_except_url LIKE ?";
		$query = $this->db->query($sql, array('%'.$this->db->escape_like_str($referer_host).'%'));

		// referer_host 와 일치하지 않으면 referer 저장/업뎃
		if( $query->num_rows() == 0 ) $result = true;

		return $result;
	}

	// 새로운 session 생성 및 유입경로 log 저장
	public function save_referer_log($referer){
		/*session->cookie 변경*/
	    /**
	     * value가 없으면 cookie 가 저장되지 않아
	     * null pointer value로 저장하도록 수정
	     * 2019-08-23
	     * @author Sunha Ryu
	     */
	    if(empty($referer)) {
	        setrawcookie('shopReferer', "\x00", 0, '/');
	    } else {
	        setcookie('shopReferer', $referer, 0, '/');
	    }

		// 유입도메인
		$refererDomain	= $this->get_referer_domain($referer);
		$_COOKIE['refererDomain']	= $refererDomain;
		setcookie('refererDomain', $refererDomain, 0, '/');

		//유입매체 :: 기존 유입매체와 현재 유입매체가 다르면 쿠키를 새로 굽는다
		$referer_sitecd		= $this->get_referer_sitecd($referer);
		if	($_COOKIE['marketplace'] != $referer_sitecd)
			setcookie('marketplace', $referer_sitecd, 0, '/');

		$query = $this->db->query("select * from fm_stats_visitor_referer where stats_date=? and referer=? and referer_sitecd=?",array($this->current_date,$referer,$referer_sitecd));
		$result = $query->row_array();

		$tmp	= parse_url($referer);
		$domain	= $tmp['host'];
		$domain	= preg_replace('/^(www\.|m\.)/', '', $domain);

		if($result['stats_date']){
			$this->db->query("update fm_stats_visitor_referer set count=count+1 where stats_date=? and referer=? and referer_sitecd=?",array($this->current_date,$referer,$referer_sitecd));
		}else{
			$data = array(
				'stats_date'		=> $this->current_date,
				'stats_year'		=> $this->current_year,
				'stats_month'		=> $this->current_month,
				'stats_day'			=> $this->current_day,
				'referer_domain'	=> $domain, 
				'referer'			=> $referer,
				'referer_sitecd'	=> $referer_sitecd,
				'count'				=> 1
			);
			$this->db->insert('fm_stats_visitor_referer', $data);
		}
	}

	/* 리퍼러 사이트코드 반환 */
	function get_referer_sitecd($referer){

		/* 추가광고매체를 통한 접속일 경우 */
		if($this->uri->segment(1)=='ad' && !empty($_GET['code'])){
			return $_GET['code'];
		}

		if( $_GET['market'] ){
			$market = $_GET['market'];
			switch($market){
				case "naver" : $market = "naver_shopping"; return $market; break;
				case "about" : $market = "about"; return $market; break;
				case "daum" : $market = "daum_shopping"; return $market; break;
			}
		}

		$bits = parse_url($referer);
		$bits['host'] = preg_replace("/^www\./","",$bits['host']);
		$bits['host'] = preg_replace("/^m\./","",$bits['host']);

		if(empty($bits['host'])) $sitecd = "direct";
		else{
			$sitecd = "etc";
			foreach($this->arr_referer_sitecd as $domain=>$cd){

				$domain = preg_replace("/^www\./","",$domain);
				$domain = preg_replace("/^m\./","",$domain);

				$regexp = addslashes($domain);
				$regexp = str_replace("/","\\/",$regexp);
				$regexp = str_replace(".","\\.",$regexp);

				if($bits['host']==$domain || preg_match("/^([a-z0-9-_]+\.){0,1}".$regexp."$/",$bits['host']))	{
					$sitecd = $cd;
					break;
				}
			}
		}

		if($sitecd=='naver'		&& !empty($_GET['NVADKWD']))	$sitecd = "naver_keyword";
		if($sitecd=='nate'		&& !empty($_GET['DMSKW']))		$sitecd = "nate_keyword";
		if($sitecd=='google'	&& $bits['path']=='/aclk')		$sitecd = "google_keyword";
		if($sitecd=='daum'		&& !empty($_GET['OVRAW']))		$sitecd = "daum_keyword";

		return $sitecd;

	}

	/* 접속 환경 로그 기록 */
	function add_platform(){

		$platform	= get_sitetype();

		$query	= $this->db->query("select * from fm_stats_visitor_platform where stats_date=? and platform=?",array($this->current_date,$platform));
		$result	= $query->row_array();

		if($result['stats_date']){
			$this->db->query("update fm_stats_visitor_platform set count=count+1 where stats_date=? and platform=? ",array($this->current_date,$platform));
		}else{
			$data = array(
				'stats_date'		=> $this->current_date,
				'stats_year'		=> $this->current_year,
				'stats_month'		=> $this->current_month,
				'stats_day'			=> $this->current_day,
				'platform'			=> $platform,
				'count'				=> 1
			);
			$this->db->insert('fm_stats_visitor_platform', $data);
		}
	}

	public function get_referer_domain($referer){

		$tmp	= parse_url($referer);
		//@2015-01-08 한글도메인일 때 처리
		$referer_domain	= (preg_match("/[\xA1-\xFE\xA1-\xFE]/",$tmp['host']))?$_SERVER['HTTP_HOST']:$tmp['host'];
		$referer_domain	= preg_replace('/^(www\.|m\.)/', '', $referer_domain);

		return $referer_domain;
	}
	
	public function ip_delete(){
		$query = "delete from fm_stats_visitor_ip where stats_date < ?";
		$this->db->query($query,date('Y-m-d',strtotime('-30 day')));
	}

	// [판매지수 EP] 쿠키값 초기화 함수 :: 2018-09-14 pjw
	public function init_sale_ep(){
		$sales_ep_info = unserialize(get_cookie('salesEpInfo'), ['allowed_classes' => false]);
		
		// 쿠키가 없거나, 있지만 오늘날짜가 아닌 경우 새로 빈 쿠키 생성
		if(empty($sales_ep_info) || (!empty($sales_ep_info) && $sales_ep_info['date'] != date('Y-m-d'))){
			$sales_ep_info = $this->make_sale_ep();
		}

		return $sales_ep_info;
	}
	
	// [판매지수 EP] EP 쿠키 생성 함수 :: 2018-09-14 pjw
	public function make_sale_ep($sales_ep_info=null){	
		
		if($sales_ep_info == null){
			$this->remove_sale_ep();
			$sales_ep_info = array(
				'ep_list'	=> array(),
				'date'		=> date('Y-m-d')
			);
		}
		set_cookie('salesEpInfo', serialize($sales_ep_info), 1440);

		return $sales_ep_info;
	}

	// [판매지수 EP] EP 쿠키 삭제 함수 :: 2018-09-14 pjw
	public function remove_sale_ep(){
		delete_cookie('salesEpInfo');
	}

	// [판매지수 EP] 네이버 쇼핑 유입 시 상품 SEQ 쿠키 저장 :: 2018-09-14 pjw
	public function set_sales_ep($goods_seq){

		// referer 체크해서 네이버 유입이 아닌경우 리턴
		$referer_domain = $this->get_referer_domain($_SERVER['HTTP_REFERER']);
		$rf_naver_arr	= $this->get_sales_ep_referer_domains();
		if( !in_array($referer_domain, $rf_naver_arr) ) return false;

		// 상품 SEQ를 추가한 뒤 쿠키에 다시 저장
		$sales_ep_info							= $this->init_sale_ep();
		$sales_ep_info['ep_list'][$goods_seq]	= array( 'goods_seq' => $goods_seq, 'referer_domain' => $referer_domain );
		$this->make_sale_ep($sales_ep_info);
		
		return $sales_ep_info;
	}
	
	// [판매지수 EP] 상품 SEQ에 연결되있는 referer 가져오는 함수 :: 2018-09-14 pjw
	public function get_sales_ep_referer($goods_seq){
		$sales_ep_info = $this->init_sale_ep();
		
		return $sales_ep_info['ep_list'][$goods_seq]['referer_domain'];
	}

	// [판매지수 EP] EP referer 도메인 배열 :: 2018-09-14 pjw
	public function get_sales_ep_referer_domains(){
		return array('cr2.shopping.naver.com', 'castbox.shopping.naver.com', 'msearch.shopping.naver.com');
	}
}
?>