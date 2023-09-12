<?php
class Couponmodel extends CI_Model {
	function __construct() {

		parent::__construct();
		$this->table_coupon						= 'fm_coupon';								//쿠폰
		$this->coupon_group						= 'fm_coupon_group';						//회원
		$this->coupon_issuecategory				= 'fm_coupon_issuecategory';		//카테고리
		$this->coupon_issuegoods				= 'fm_coupon_issuegoods';			//상품
		$this->coupon_download					= 'fm_download';							//발급
		$this->coupon_download_issuecategory	= 'fm_download_issuecategory';	//발급카테고리
		$this->coupon_download_issuegoods		= 'fm_download_issuegoods';		//발급상품
		$this->members							= 'fm_member';
		$this->offlinecoupon					= 'fm_offline_coupon';							//오프라인 자동
		$this->offlinecoupon_input				= 'fm_offline_coupon_input';					//오프라인 수동

		$this->couponTypeTitle 		= array("download"=>"상품","mobile"=>"모바일","birthday"=>"생일자","anniversary"=>"기념일","shipping"=>"배송비","memberGroup"=>"회원등급조정", "member"=>"신규가입", "admin"=>"직접 발급", "offline_coupon"=>"상품(인증번호)", "offline_emoney"=>"인쇄용 (마일리지)", "point"=>"포인트 전환",'memberGroup_shipping'=>"회원등급조정(배송비)",'member_shipping'=>"신규가입(배송비)", 'admin_shipping'=>"직접 발급(배송비)",'memberlogin'=>"이달의 컴백회원",'memberlogin_shipping'=>"이달의 컴백회원(배송비)",'membermonths'=>"이달의 등급",'membermonths_shipping'=>"이달의 등급(배송비)",'order'=>"첫 구매",'app_install'=>"앱 설치 상품 쿠폰",'ordersheet'=>"주문서");

		$this->couponTypeShortTitle = array("download"=>"상품","mobile"=>"모바일","birthday"=>"생일자","anniversary"=>"기념일","shipping"=>"배송비","memberGroup"=>"등급조정", "member"=>"신규가입", "admin"=>"직접 발급", "offline_coupon"=>"상품", "offline_emoney"=>"인쇄용 (마일리지)", "point"=>"포인트 전환",'memberGroup_shipping'=>"등급조정(배송비)",'member_shipping'=>"신규가입(배송비)", 'admin_shipping'=>"직접 발급(배송비)",'memberlogin'=>"컴백회원",'memberlogin_shipping'=>"컴백회원(배송비)",'membermonths'=>"이달의 등급",'membermonths_shipping'=>"이달의 등급(배송비)",'order'=>"첫 구매",'app_install'=>"앱 설치 쿠폰",'ordersheet'=>"주문서");

		$this->couponIssueType 		= array("all"=>"전상품","issue"=>"특정상품적용","except"=>"특정상품제외");

		//이벤트페이지의 쿠폰 구분
		$this->couponpagetype 		= array(
										"mypage"=>array("birthday","anniversary","memberGroup"=>"membergroup","memberGroup_shipping"=>"membergroup_shipping"),
										"promotionpage"=>array("shipping","member","member_shipping","memberlogin","memberlogin_shipping","membermonths","membermonths_shipping","order")
									);

		// 입점몰 전용
		$this->except_providerchk_coupon	= array('admin', 'admin_shipping', 'point', 'offline_emoney',
											'birthday', 'anniversary', 'memberGroup', 'member',
											'memberlogin', 'membermonths', 'order');

		//배송비관련 쿠폰모음
		$this->coupontotaltype 		= array("birthday","anniversary","memberGroup","shipping","member","memberlogin","membermonths","order");
		$this->couponshipping 		= array("memberGroup","member","memberlogin","membermonths");


		$this->copuonupload_dir 	= ROOTPATH.'data/coupon/';//첨부파일폴더
		$this->copuonupload_src 	= '/data/coupon/';
		$this->today 				= date("Y-m-d",time());

		$this->config->load("couponSet");
		$this->coupon_category		= $this->config->item("coupon_category");
		$this->coupon_category_sub	= $this->config->item("coupon_category_sub");
		$this->coupon_all_list		= $this->config->item("coupon_all_list");
		$this->coupon_popup			= $this->config->item("coupon_popup");
		$this->set_coupon_form		= $this->config->item("set_coupon_form");
		$this->coupon_service_limit	= $this->config->item("coupon_service");
		$this->coupon_validation	= $this->config->item("validation");
		$this->fieldValiSet			= $this->config->item("fieldValiSet");

		$coupon_category_sub 		= array();
		foreach($this->coupon_category_sub as $cate1=>$_sub){
			foreach($_sub as $type=>$_data){
				if(count($_data['list']) > 1){
					foreach($_data['list'] as $type2 => $_name){
						//무료몰일 때 :: 쿠폰 사용제한
						if(!serviceLimit('H_NFR') && in_array($type2,$this->coupon_service_limit['H_NFR'])) continue;
						$coupon_category_sub[$cate1][$type2] = $_data['name']."(".$_name.")";
					}
				}else{
					//무료몰일 때 :: 쿠폰 사용제한
					if(!serviceLimit('H_NFR') && in_array($type,$this->coupon_service_limit['H_NFR'])) continue;
					if($cate1 == 'order'){
						$coupon_category_sub[$cate1][$type] 		= $_data['name']."(온라인)";
						$coupon_category_sub[$cate1][$type.'_off'] 	= $_data['name']."(오프라인)";
					}else{
						$coupon_category_sub[$cate1][$type] 		= $_data['name'];
					}
				}
			}
		}
		$this->coupon_category_all = $coupon_category_sub;

	}


	/*
	 * 관리자>쿠폰목록
	 * @param
	*/
	public function coupon_list($sc)
	{

		if($sc['sc_coupon_category'] == "all")	$sc['sc_coupon_category']	= "";
		if($sc['issue_stop'] == "all")			$sc['issue_stop']			= "";
		if($sc['use_type'] == "all")			$sc['use_type']				= "";
		if($sc['sale_agent'] == "all")			$sc['sale_agent']			= "";
		if($sc['sale_payment'] == "all")		$sc['sale_payment']			= "";
		if($sc['sale_payment'] == "all")		$sc['sale_payment']			= "";

		$where = array();

		if(!empty($sc['search_text'])) $where[] = "coupon_name like \"%".$sc['search_text']."%\" ";

		if(!empty($sc['couponType']))
		{
			$couponTypein = implode("','",$sc['couponType']);
			$where[] = "type in ('".$couponTypein."') ";
		}

		// 쿠폰 혜택구분
		if(!empty($sc['sc_coupon_category']))
		{
			$where[] = "coupon_category='".$sc['sc_coupon_category']."'";
			if(in_array($sc['sc_coupon_category'],array("goods","shipping")) && !empty($sc['sc_coupon_category_sub'])){
				if($sc['sc_coupon_category'] == "goods"){
					$sc_coupon_category = $sc['sc_coupon_category_sub'][0];
				}elseif($sc['sc_coupon_category'] == "shipping"){
					$sc_coupon_category = $sc['sc_coupon_category_sub'][1];
				}
				if($sc_coupon_category){
					$where[] = "type='".$sc_coupon_category."'";
				}
			}
		}

		// 온/오프라인 검색
		if(!empty($sc['use_type']))
		{
			if($sc['use_type'] == 'offline'){
				$where[] = "coupon_category='order' and sale_store='off'";
			}else{
				$where[] = "(coupon_category !='order' || (coupon_category='order' and sale_store!='off'))";
			}
		}
		// 오프라인전용 매장 검색
		if(!empty($sc['sale_store_item'])){
			$where[] = " sale_store_item like '%\"".$sc['sale_store_item']."\"%'";		//	["1","2"]
		}

		if($sc['sdate']) $start_date = $sc['sdate'].' 00:00:00';
		if($sc['edate']) $end_date	= $sc['edate'].' 23:59:59';

		// 등록일 검색(시작)
		if($sc['sdate'] AND !$sc['edate']) {
			$where[] = "regist_date >= '{$start_date}' ";
		}

		// 등록일 검색(끝)
		if($sc['edate'] AND !$sc['sdate']) {
			$where[] = "regist_date <= '{$start_date}' ";
		}

		// 등록일 검색
		if($sc['sdate'] AND $sc['edate']) {
			$where[] = "regist_date BETWEEN '{$start_date}' AND '{$end_date}' ";
		}

		if(!$sc['search_cost_start']) $sc['search_cost_start'] = '0';
		if(!$sc['search_cost_end']) $sc['search_cost_end'] = '0';

		## 통신판매중계자 부담율 검색
		$sales_cost_fld	= 'salescost_admin';
		if	($sc['cost_type'] == 'provider')	$sales_cost_fld	= 'salescost_provider';
		if		(!empty($sc['search_cost_start']) && !empty($sc['search_cost_end'])){
			$where[] = $sales_cost_fld." between '".$sc['search_cost_start']."' and  '".$sc['search_cost_end']."' ";
		}elseif	(!empty($sc['search_cost_start']) && empty($sc['search_cost_end'])){
			$where[] = $sales_cost_fld." >= '".$sc['search_cost_start']."' ";
		}elseif	(empty($sc['search_cost_start']) && !empty($sc['search_cost_end'])){
			$where[] = $sales_cost_fld." <= '".$sc['search_cost_end']."' ";
		}

		## 입점사 검색, 입점사 관리자에서 검색 시
		if(defined('__SELLERADMIN__')){
			$where[] = "(discount_seller_type in('all','seller') and provider_list like '%|".$this->providerInfo['provider_seq']."|%')";
			$countWheres[] = "(discount_seller_type in('all','seller') and provider_list like '%|".$this->providerInfo['provider_seq']."|%')";
		}else{
			if(!empty($sc['provider_seq'])){
				if($sc['provider_seq'] == 1){
					$where[] = "discount_seller_type in('all','admin')";
				}else{
					$where[] = "(discount_seller_type in('all','seller') and provider_list like '%|".$sc['provider_seq']."|%')";
				}
			}
		}

		## 입점사 부담율 검색
		if	(!empty($sc['salescost_provider'])){
			$where[] = "salescost_provider > '".$sc['salescost_provider']."' ";
		}

		// o2o 쿠폰 매장 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_coupon_catalog($sql, $sc);

		//발급여부
		if(!empty($sc['issue_stop'])){
			if($sc['issue_stop'] == 1){
				$where[] = "issue_stop = '0' ";
			}elseif($sc['issue_stop'] == 2){
				$where[] = "issue_stop = '1' ";
			}
		}else{
		if(!empty($sc['issue_stop0']) && !empty($sc['issue_stop1']) ) {
			//$sql.= " and issue_stop = '0' ";
		}elseif(!empty($sc['issue_stop0']) && empty($sc['issue_stop1']) ) {
				$where[] = "issue_stop != '1' ";
		}elseif(empty($sc['issue_stop0']) && !empty($sc['issue_stop1']) ) {
				$where[] = "issue_stop = '1' ";
		}
		}

		//단독
		if(!empty($sc['coupon_same_time'])) {
			$where[] = "coupon_same_time = '{$sc[coupon_same_time]}' ";
		}
		//제한금액
		if(!empty($sc['limit_goods_price'])) {
			$where[] = "limit_goods_price >= '{$sc[limit_goods_price]}' ";
		}
	   //모바일여부
		if(!empty($sc['sale_agent'])) {
			$where[] = "sale_agent = '{$sc[sale_agent]}' ";
		}
	   //무통장여부
		if(!empty($sc['sale_payment'])) {
			$where[] = "sale_payment = '{$sc[sale_payment]}' ";
		}
		//유입경로여부
		if(!empty($sc['sale_referer'])) {
			$where[] = "sale_referer = '{$sc[sale_referer]}' ";
		}
		// 정렬
		if($sc['orderby'] ) {
			$orderby ="ORDER BY {$sc['orderby']} {$sc['sort']} ";
		} else {
			$orderby ="ORDER BY coupon_seq desc ";
		}

		$sqlWhereClause = $where ? implode(' AND ',$where) : "";

		$limitStr =" LIMIT {$sc['page']}, {$sc['perpage']} ";

		$sql				= array();
		$sql['field']		= "*";
		$sql['table']		= $this->table_coupon;
		$sql['wheres']		= $sqlWhereClause;
		$sql['countWheres']	= $countWheres;
		$sql['orderby']		= $orderby;
		$sql['limit']		= $limitStr;

		$result				= pagingNumbering($sql,$sc);

		return $result;
	}

	// 총건수
	public function get_item_total_count()
	{
		## 입점사 관리자에서 검색 시
		if( defined('__SELLERADMIN__') === true ){
			$where	= " where (discount_seller_type in('all','seller') and provider_list like '%|".$this->providerInfo['provider_seq']."|%')";
		}

		$sql = 'select coupon_seq from '.$this->table_coupon. " ".$where;
		$query = $this->db->query($sql);

		return $query->num_rows();
	}


	//생일자/신규회원가입 쿠폰은 다중지급. //@2014-06-30다중지급가능
	public function get_coupon_multi_list($sc)
	{
		$sc['whereis'] = ($sc['whereis'])?' where 1 '. $sc['whereis']:'';
		$sql = 'select coupon_seq from '.$this->table_coupon.$sc['whereis'];
		$query = $this->db->query($sql);
		return $query->result_array();
	}

	//발급받은 쿠폰정보가져오기
	public function get_download_coupon($download_seq)
	{
		$this->db->where('download_seq', $download_seq);
		$query = $this->db->get($this->coupon_download);
		$result = $query->row_array();
		return $result;
	}


	// 발급총건수 와 사용건수
	public function get_download_total_count($sc)
	{
		$sc['whereis'] = ($sc['whereis'])?' where 1 '. $sc['whereis']:'';
		$sql = 'select count(*) cnt from '.$this->coupon_download.$sc['whereis'];
		$query = $this->db->query($sql);
		$result = $query->row_array();
		return $result['cnt'];
	}

	/*
	 * 관리자 > 발급내역관리
	 * @param
	*/
	public function download_list($sc)
	{
		$sql = "select SQL_CALC_FOUND_ROWS *, m.userid, m.user_name,
					(select bname from fm_member_business where member_seq = d.member_seq) as bname,
					d.*
					from ".$this->coupon_download." d
					left join ".$this->members." m on m.member_seq = d.member_seq where 1 ";

		if( !empty($sc['no']) )
		{
			$sql .= ' and d.coupon_seq = '.$sc['no'].' ';
		}

		if(isset($sc['member_seq'])) $sql.= ' and m.member_seq ='.$sc['member_seq'];//회원

		if( !empty($sc['search_text']) )
		{
			if(!empty($sc['search_field'])){
				$sql .= ' and '.$sc['search_field'].' like "%'.$sc['search_text'].'%"';//
			}else{
			$sql .= ' and ( m.user_name like "%'.$sc['search_text'].'%" or m.userid  like "%'.$sc['search_text'].'%") ';//
		}
		}

		if(!empty($sc['use_status'])){
			switch($sc['use_status']){
				case "used":	// 사용 : 쿠폰사용완료
			$sql.= " and d.use_status='".$sc['use_status']."'";
				break;
				case "unused":	//미사용 : 쿠폰사용전 & 기간만료전
					$sql.= " and d.use_status='".$sc['use_status']."' and d.issue_enddate >= '".date("Y-m-d",mktime())."' ";
				break;
				case "expire":	// 유효기간만료 : 미사용 & 기간만료
					$sql.= " and  d.use_status='unused' and d.issue_enddate < '".date("Y-m-d",mktime())."'";
				break;
			}
		}

		if(!empty($sc['keyword']))$sql.= " and d.coupon_name like \"%".$sc['keyword']."%\" ";

		// 등록일 검색(시작)
		if($sc['sdate'] AND !$sc['edate']) {
			$start_date = $sc['sdate'].' 00:00:00';
			$sql.=" and d.regist_date >= '{$start_date}' ";
		}

		// 등록일 검색(끝)
		if($sc['edate'] AND !$sc['sdate']) {
			$start_date = $sc['edate'].' 23:59:59';
			$sql.=" and d.regist_date <= '{$start_date}' ";
		}

		// 등록일 검색
		if($sc['sdate'] AND $sc['edate']) {
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$sql.=" and d.regist_date BETWEEN '{$start_date}' and '{$end_date}' ";
		}


		$sql.=" order by d.download_seq desc ";
		$sql .=" limit {$sc['page']}, {$sc['perpage']} ";
		$query = $this->db->query($sql);
		$data['result'] = $query->result_array();

		//총건수
		$sql = "SELECT FOUND_ROWS() as COUNT";
		$query_count = $this->db->query($sql);
		$res_count= $query_count->result_array();
		$data['count'] = $res_count[0]['COUNT'];
		return $data;
	}

	// 다운받은 총건수
	public function get_download_item_total_count($no)
	{
		$sql = 'select download_seq from '.$this->coupon_download.' where 1';
		if( !empty($no) )
		{
			$sql .= ' and coupon_seq = '.$no.' ';
		}

		$query = $this->db->query($sql);
		return $query->num_rows();
	}

	public function get_coupon($no)
	{
		//$this->db->limit(1,0);
		$this->db->where('coupon_seq', $no);
		$query = $this->db->get('fm_coupon');
		$result = $query->row_array();
		return $result;
	}

	public function get_coupon_group($no)
	{
		$result = false;
		$bind = array($no);
		$query = $this->db->query("select cg.*,g.group_name from fm_coupon_group as cg left join fm_member_group as g on cg.group_seq=g.group_seq where cg.coupon_seq=?",$bind);
		foreach( $query->result_array() as $row ){
			$result[] = $row;
		}
		return $result;
	}

	public function get_coupon_issuecategory($no,$issue_type='')
	{
		$result = false;
		$this->db->where('coupon_seq', $no);
		if($issue_type){
			$this->db->where('type', $issue_type);
		}
		$query = $this->db->get('fm_coupon_issuecategory');
		foreach( $query->result_array() as $row ){
			$result[] = $row;
		}
		return $result;
	}

	public function get_coupon_issuegoods($no,$issue_type='')
	{
		$result = false;

		$this->db->select('c.*');
		$this->db->from('fm_coupon_issuegoods AS c');
		if( defined('__SELLERADMIN__') === true ){
			$this->db->join('fm_goods AS g', 'g.goods_seq=c.goods_seq','left');
			$this->db->where('g.provider_seq', $this->providerInfo['provider_seq']);
		}

		$this->db->where('c.coupon_seq', $no);
		if($issue_type){
			$this->db->where('c.type', $issue_type);
		}
		$query = $this->db->get();
		foreach( $query->result_array() as $row ){
			$result[] = $row;
		}
		return $result;
	}


	/* 발급여부체크 */
	public function get_admin_download($memberSeq,$couponSeq)
	{
		$sql = "SELECT * FROM fm_download where coupon_seq='".$couponSeq."' and member_seq='".$memberSeq."' order by download_seq desc ";//최근다운받은쿠폰기준
		$query = $this->db->query($sql);
		list($result) = $query->result_array();
		return $result;
	}


	/* 오프라인쿠폰 >  발급갯수 */
	public function get_offlinecoupon_download_cnt($memberSeq,$couponSeq)
	{
		$sql = "SELECT coupon_seq FROM fm_download where coupon_seq='".$couponSeq."' and member_seq='".$memberSeq."'  ";
		$sql .= " and refund_download_seq is null ";
		$query = $this->db->query($sql);
		return $query->num_rows();
	}

	/* 오프라인쿠폰 >  인증번호 중복체크 (자동생성의 랜덤 또는 수동생성의 수동등록) */
	public function get_offlinecoupon_serialnumber_download_cnt( $couponSeq, $offline_serialnumber)
	{
		$sql = "SELECT coupon_seq, member_seq FROM fm_download where coupon_seq='".$couponSeq."' and offline_input_serialnumber='".$offline_serialnumber."' ";
		$query = $this->db->query($sql);
		return $query->row_array();
	}

	// @202003 통합 - 온라인/오프라인 쿠폰 등록/수정 필드 체크
	public function check_param_download($_params)
	{
		//쿠폰종류/발급방식에 따른 등록폼설정값
		$set_coupon_form			= $this->set_coupon_form;
		$set_validation				= $this->coupon_validation;
		$fieldValiSet				= $this->fieldValiSet;

		if($_params['couponSeq']){
			$save_mode = "modify";
		}

		$_params['couponType']		= $_POST['couponType'] = (!$_params['couponType'])? $_params['issued_method']:$_params['couponType'];
		$couponType					= $_params['couponType'];
		$_params['couponName']		= strip_tags($_params['couponName']);
		$_params['couponDesc']		= strip_tags($_params['couponDesc']);

		// 현재까지 확인된 바 솔루션의 모든 쿠폰 사용타입은 online 2020-05-20
		if(!$_params['coupon_usetype']) $_params['coupon_usetype'] = "online";
		if($_params['coupon_category'] != "mileage") unset($_params['period_limit']);

		if(!serviceLimit('H_AD')) {
			$_params['discount_seller_type'] = "admin";
		}

		/*
		제한없음 설정 등에 따른 초기화
		*/
		if(!strstr($_params['issued_method'],'offline')){
			if(!$_params['percentGoodsSale']) $_params['percentGoodsSale'] = $_params['goodsSalePrice'];
		}else{
			if(!$_params['wonGoodsSale']) $_params['wonGoodsSale'] = $_params['goodsSalePrice'];
		}

		$downloadPeriodSet		= $set_coupon_form[$couponType]['downloadPeriodSet'];			// 다운기간 사용여부
		$certificationPeriodSet = $set_coupon_form[$couponType]['certificationPeriodSet'];		// 인증기간 사용여부

		$downloadLimit			= $_params['downloadLimit'];
		$download_period_use	= $_params['download_period_use'];
		$period_limit			= $_params['period_limit'];

		// 본사부담 100%일 경우 POST 값 상관없이 초기화
		if($set_coupon_form[$couponType]['discount_seller_type'] == "A" || $_params['discount_seller_type'] == 'admin'){
			$_params['salescost_admin']				= 100;
			$_params['salescost_provider']			= 0;
			$_params['provider_seq_list']			= "";
			$_params['salescost_provider_list'] 	= '';
		}elseif($_params['couponType'] == 'shipping' && $_params['discount_seller_type'] == 'seller'){		//배송비 다운로드 쿠폰, 입점사 배송비일 경우
			$_params['salescost_admin']				= 0;
			$_params['salescost_provider']			= 100;
		}

		//모든 유입경로 할인 시 기 선택된 유입경로할인 이벤트 삭제.
		if($_params['sale_referer_type'] == "a"){
			unset($_params['referersale_seq']);
		}
		//쿠폰 발급기간 제한없음 시 제한 날짜 초기화
		if($download_period_use == "unlimit"){
			unset($_params['downloadDate_s'],$_params['downloadDate_e']);
		}
		//마일리지 유효기간 제한없음 시 제한 날짜 초기화
		if($period_limit == "unlimit"){
			unset($_params['offline_reserve_year'],$_params['offline_reserve_direct']);
		}
		//수량제한 없음 시 제한수량 초기화
		if($downloadLimit == "unlimit"){
			$_params['downloadLimitEa'] = 0;
		}

		$_params['percentGoodsSale']		= ($_params['percentGoodsSale']>0)? $_params['percentGoodsSale']:0;
		$_params['maxPercentGoodsSale']		= ($_params['maxPercentGoodsSale']>0)? $_params['maxPercentGoodsSale']:0;
		$_params['wonGoodsSale']			= ($_params['wonGoodsSale']>0)? $_params['wonGoodsSale']:0;
		if(($_params['couponType'] == "ordersheet" || $_params['couponType'] == "ordersheet_off") && $_params['sale_store'] == 'off'){
			$_params['sale_store'] = "off";
			$couponType .= "_off";
		}else{
			$_params['sale_store'] 		= "on";
			$_params['sale_store_item'] = "";
		}

		// ★ 본사, 입점사 모든 상품 대상일 때 provider_seq_list = null 이어야 함
		if(is_array($_params['salescost_provider_list'])) $_params['provider_seq_list'] = "|".implode("|",$_params['salescost_provider_list'])."|";

		// 요일제한 :: 미체크 시 요일선택해제
		if(!$_params['dayoftheweek_limit']) unset($_params['downloadWeek_']);

		if($certificationPeriodSet == "y"){
			$downloadDate_s			= array($_params['certificationDate_s']);
			$downloadDate_e			= array($_params['certificationDate_e']);
		}else{
			$downloadDate_s			= $_params['downloadDate_s'];
			$downloadDate_e			= $_params['downloadDate_e'];
			$downloadTime_s			= $_params['downloadTime_s'];
			$downloadTime_e			= $_params['downloadTime_e'];
		}

		// validation check - 공통
		foreach($set_validation['common'] as $field){
		//	$this->validation->set_rules($field, $fieldValiSet[$field]['label'],$fieldValiSet[$field]['rules']);
		}

		// 수정모드일 때 validation 체크 예외 필드 정의
		$except_modify = array();
		if($save_mode == "modify"){
			$except_modify = array("certificationNumberSet");
		}

		/*
		validation check - app/config/couponSet.php 에 체크필드 정의
		$couponValidation['offlineStore']['y'] = array('sale_store_item[]');
		$couponValidation['downloadPeriodSet']['neworder'] = array('order_terms');
		*/

		foreach($set_validation['common'] as $_field){ $set_coupon_form[$couponType][$_field] = 'y'; }

		foreach($set_validation['etc'] as $chk_key => $chk_value){

			if(in_array($chk_key,$except_modify)) continue;

			// 유효성 체크 항목 foreach
			foreach($chk_value as $chk_key2 => $chk_fields){

				$selectSetValue = $set_coupon_form[$couponType][$chk_key];

				// 혜택 유효기간(periodofuse_type)가 date|day 등 다중 설정일때 실제 선택한 issuePriodType 값으로 체크
				if($chk_key == "periodofuse_type"){
					$selectSetValue = $_params['issuePriodType'];
				}

				if($_params['period_limit'] == "unlimit" && $chk_key == "periodofuse_type"){
					$selectSetValue = "";
				}

				// 필수 체크 시작
				if($selectSetValue == $chk_key2){

					foreach($chk_fields as $_opt => $post_field){

						if(is_array($post_field)){
							$post_fields = array();
							/* 특정 필드에 대한 validation 추가 체크 시작 */
							if(is_array($post_field[$_params[$_opt]])){
								foreach($post_field[$_params[$_opt]] as $_opt2 => $_field2){
									if(is_array($_field2[$_params[$_opt2]])){
										foreach($_field2[$_params[$_opt2]] as $_opt3 => $_field3){
											if($_field3) $post_fields[] = $_field3;
										}
									}else{
										if($_field2) $post_fields[] = $_field2;
									}
								}
							}else{
								if($post_field[$_params[$_opt]]) $post_fields[] = $post_field[$_params[$_opt]];
							}

							foreach($post_fields as $_params_field){
								$rules		= $fieldValiSet[$_params_field]['rules'];
								$this->validation->set_rules($_params_field, $fieldValiSet[$_params_field]['label'],$rules);
							}

						}else{

							/* 특정 필드에 대한 validation 추가 체크 시작 */
							switch($chk_key){
							//상품/카테고리 제한 사용일 때
								case "goodsCategoryLimit":
									if($chk_key2 == "y"){
										if($_params['issue_type'] == 'issue' || $_params['issue_type'] == 'except') {
											if($_params['issue_type'] == 'issue' ){
												$msg = "사용할 상품 또는 카테고리를 선택해주세요.";
												$label = "적용";
											}elseif($_params['issue_type'] == 'except' ){
												if($_params['issueGoods']) $_params['exceptIssueGoods'] = $_params['issueGoods'];
												if($_params['issueCategoryCode']) $_params['exceptIssueCategoryCode'] = $_params['issueCategoryCode'];
												$msg = "사용을 제한할 상품 또는 카테고리를 선택해주세요.";
												$label = "적용예외";
											}

											if(count($_params['issueGoods']) == 0 && $_params['issueCategoryCode'][0] == ""){
												$callback = "parent.document.onlineRegist.downloadDate_s[0].focus();";
												openDialogAlert($msg,450,150,'parent',$callback);
												exit;
											}
											$this->validation->set_rules('issueGoods[]', $label.' 상품','trim|numeric|xss_clean');
											$this->validation->set_rules('issueCategoryCode[]', $label.' 카테고리','trim|xss_clean');
										}
									}
								break;
								default:
									// default
								break;
							}

							/* 특정 값에 대한 validation 추가 체크 종료 */
							$rules		= $fieldValiSet[$post_field]['rules'];
							$this->validation->set_rules($post_field, $fieldValiSet[$post_field]['label'],$rules);

						}

					}
				}else{
					//debug($set_coupon_form[$couponType]);
				}
			}
		}

		//exit;
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,160,'parent',$callback);
			exit;
		}

		$paramCoupon['excel_url_check'] = 'none';
		if( $_params['offline_type'] == 'file' && $_params['offline_file']) {//수동생성2 > 파일
			$realfilename	= ROOTPATH.'/data/tmp/'.$_params['offline_file'];
			if(!is_file($realfilename)){
				$paramCoupon['excel_url_check'] = 'fail';
				openDialogAlert("수동으로 발급할 엑셀의 경로가 올바르지 않습니다.",400,140,'parent');
				exit;
			}else{
				$paramCoupon['excel_url_check'] = 'ok';
			}
		}

		$_params['offline_reserve_select']		= (in_array($_params['issuePriodType'],array("year","direct")))?$_params['issuePriodType']:"";
		$_params['duplicationUse'] 				= if_empty($_params, 'duplicationUse', '0');

		// o2o 쿠폰 체크 초기화
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_check_param_online_download($paramCoupon, $_params);

		if($save_mode != "modify"){
			$paramCoupon['type'] 					= ($couponType == "ordersheet_off")? "ordersheet":$couponType;
			$paramCoupon['use_type']				= $_params['coupon_usetype'];
			$paramCoupon['coupon_category'] 		= $_params['coupon_category'];
			$paramCoupon['coupon_type'] 			= $_params['coupon_type'];
		}
		$paramCoupon['issue_type'] 				= if_empty($_params, 'issue_type', 'all');
		$paramCoupon['issue_stop'] 				= if_empty($_params, 'issue_stop', '0');
		$paramCoupon['coupon_name'] 			= $_params['couponName'];
		$paramCoupon['coupon_desc'] 			= $_params['couponDesc'];
		$paramCoupon['sale_type'] 				= $_params['saleType'];
		$paramCoupon['discount_seller_type'] 	= $_params['discount_seller_type'];
		$paramCoupon['coupon_same_time']		= if_empty($_params, 'couponsametime', 'Y');//동시사용여부: Y
		$paramCoupon['download_period_use'] 	= $download_period_use;

		$paramCoupon['sale_agent'] 				= if_empty($_params, 'sale_agent', 'a');
		$paramCoupon['sale_payment'] 			= if_empty($_params, 'sale_payment', 'a');
		$paramCoupon['sale_referer'] 			= if_empty($_params, 'sale_referer', 'a');
		$paramCoupon['sale_referer_type'] 		= if_empty($_params, 'sale_referer_type', 'a');
		$paramCoupon['sale_referer_item'] 		= (is_array($_params['referersale_seq']))? implode(",",$_params['referersale_seq']):'';


		// 쿠폰 발급 기한 설정 : 00동안 미구매 체크
		if($downloadPeriodSet == "notpurchased"){
			$paramCoupon['memberlogin_terms'] 	= if_empty($_params, 'memberlogin_terms', '1');
		}else{
			$paramCoupon['memberlogin_terms'] 	= 0;
		}

		$paramCoupon['order_terms'] 			= if_empty($_params, 'order_terms', '0');
		$paramCoupon['download_limit'] 			= ($_params['downloadLimit'])?$_params['downloadLimit']:'unlimit';
		$paramCoupon['download_limit_ea'] 		= ($_params['downloadLimitEa'])?$_params['downloadLimitEa']:0;

		// 온라인 쿠폰
		if(!strstr($_params['issued_method'],'offline')){

			$paramCoupon['coupon_point'] 			= if_empty($_params, 'coupon_point', '0');
			$paramCoupon['before_birthday']			= 0;
			$paramCoupon['after_birthday']			= 0;
			$paramCoupon['before_anniversary']		= 0;
			$paramCoupon['after_anniversary']		= 0;
			$paramCoupon['after_upgrade']			= 0;

			// 특정 쿠폰 종류에 따른 설정
			switch($couponType){
				case "birthday":		//생일쿠폰 발급기간 설정
					if(isset($_params['beforeDay'])) $paramCoupon['before_birthday']	= $_params['beforeDay'];
					if(isset($_params['afterDay'])) $paramCoupon['after_birthday']	= $_params['afterDay'];
				break;
				case "anniversary":		//기념일쿠폰 발급기간 설정
					if(isset($_params['beforeDay'])) $paramCoupon['before_anniversary']	= $_params['beforeDay'];
					if(isset($_params['afterDay'])) $paramCoupon['after_anniversary']		= $_params['afterDay'];
				break;
				case "memberGroup":
				case "memberGroup_shipping":
					if(isset($_params['afterUpgrade'])) $paramCoupon['after_upgrade']		= $_params['afterUpgrade'];
				break;
			}

		// 오프라인 쿠폰
		}else{
			$paramCoupon['offline_emoney']			= $_params['offline_emoney'];
			$paramCoupon['offline_reserve_select']	= ($_params['period_limit'] == "unlimit")? '' :$_params['offline_reserve_select'];		// 마일리지 유효기간
			$paramCoupon['offline_reserve_year']	= $_params['offline_reserve_year'];
			$paramCoupon['offline_reserve_direct']	= $_params['offline_reserve_direct'];
			$paramCoupon['download_limit_ea']		= $_params['downloadLimitEa_offline'];

			if($save_mode != "modify"){

				$paramCoupon['offline_type']			= $_params['offline_type'];

				switch($paramCoupon['offline_type']){
					case 'random':	//자동생성 > 인증번호 갯수
						$paramCoupon['offline_random_num'] 	= if_empty($_params, 'offline_random_num', '0');
					break;
					case 'one' :			//자동생성 > 동일번호
						$paramCoupon['offline_limit'] 					= $_params['offlineLimit_one'];
						if( $paramCoupon['offline_limit'] == 'limit') {//자동생성 > 동일번호 > 선착순
							$paramCoupon['offline_limit_ea'] 		= if_empty($_params, 'offlineLimitEa_one', '0');
						}
					break;
					case 'input':			//수동생성 > 동일번호
						$paramCoupon['offline_input_serialnumber'] 		= if_empty($_params, 'offline_input_num', '');
						$paramCoupon['offline_limit'] 					= $_params['offlineLimit_input'];
						if(!$_params['couponSeq']) {//등록시에만 적용
							// offline쿠폰 인증번호 체크
							$sc['offline_serialnumber'] = $paramCoupon['offline_input_serialnumber'];
							$offlienresult = $this->get_offlinecoupon_total_count($sc);
							if(!$offlienresult){
								$offlienresult = $this->get_offlinecoupon_input_total_count($sc);
							}
							if($offlienresult){
								$err = '이미 등록된 인증번호입니다.';
								$callback = "if(parent.document.getElementsByName('offline_input_num')[0]) parent.document.getElementsByName('offline_input_num')[0].focus();";
								openDialogAlert($err,400,140,'parent',$callback);
								exit;
							}
						}

						if( $paramCoupon['offline_limit'] == 'limit') {//자동생성 > 동일번호 > 선착순
							$paramCoupon['offline_limit_ea'] 		= if_empty($_params, 'offlineLimitEa_input', '0');
						}
						break;
					case 'file':			//수동생성 > 파일
						//'offline_file', '수동생성 > 엑셀파일' upload
					break;
				}
			}
		}

		// 다운로드 기간제한 : 추가 체크
		if(($download_period_use == "limit" && $downloadPeriodSet == "period") or ($certificationPeriodSet == "y")){
			// 기간을 기재 했을경우
			if($downloadDate_s[0] && $downloadDate_e[0]){

				if($certificationPeriodSet == "y"){
					$periodTitle = "인증 기간";
				}else{
					$periodTitle = "제한 기간";
				}

				// 제한기간 검사
				$pattan = "/^(19|20)\d{2}-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[0-1])$/";
				if(!preg_match($pattan,$downloadDate_s[0])){
					$callback = "parent.document.onlineRegist.downloadDate_s[0].focus();";
					openDialogAlert($periodTitle." 시작일이 올바르지 않습니다.<br/>날짜를 조정해 주세요.",450,170,'parent',$callback);
					exit;
				}
				if(!preg_match($pattan,$downloadDate_e[0])){
					$callback = "parent.document.onlineRegist.downloadDate_e[0].focus();";
					openDialogAlert($periodTitle." 종료일이 올바르지 않습니다.<br/>날짜를 조정해 주세요.",450,170,'parent',$callback);
					exit;
				}

				// 제한기간 체크
				if(!isset($downloadDate_s[1])) $downloadDate_s[1] = "00";
				if(!isset($downloadDate_s[2])) $downloadDate_s[2] = "00";
				if(!isset($downloadDate_e[1])) $downloadDate_e[1] = "23";
				if(!isset($downloadDate_e[2])) $downloadDate_e[2] = "59";

				$downloadDate_s[1] = str_pad($downloadDate_s[1], 2, "0", STR_PAD_LEFT);
				$downloadDate_s[2] = str_pad($downloadDate_s[2], 2, "0", STR_PAD_LEFT);
				$downloadDate_e[1] = str_pad($downloadDate_e[1], 2, "0", STR_PAD_LEFT);
				$downloadDate_e[2] = str_pad($downloadDate_e[2], 2, "0", STR_PAD_LEFT);

				$downloadDate_start	= $downloadDate_s[0] . " " . $downloadDate_s[1] . ":" . $downloadDate_s[2];
				$downloadDate_end	= $downloadDate_e[0] . " " . $downloadDate_e[1] . ":" . $downloadDate_e[2];

				if(strtotime($downloadDate_start) > strtotime($downloadDate_end)){
					$callback = "parent.document.couponRegist.downloadDate_s[0].focus();";
					openDialogAlert($periodTitle." 종료일이 시작일보다 이후에 있어야 합니다.<br/>날짜를 조정해 주세요.",450,150,'parent',$callback);
					exit;
				}

				$paramCoupon['download_startdate'] 	= $downloadDate_start;
				$paramCoupon['download_enddate'] 	= $downloadDate_end;

			}

			if(!strstr($_params['issued_method'],'offline')){
				// 다운로드 가능 시간 기본값 설정
				if(!isset($downloadTime_s[0])) $downloadTime_s[0] = "00";
				if(!isset($downloadTime_s[1])) $downloadTime_s[1] = "00";
				if(!isset($downloadTime_e[0])) $downloadTime_e[0] = "23";
				if(!isset($downloadTime_e[1])) $downloadTime_e[1] = "59";

				$downloadTime_s[0] = str_pad($downloadTime_s[0], 2, "0", STR_PAD_LEFT);
				$downloadTime_s[1] = str_pad($downloadTime_s[1], 2, "0", STR_PAD_LEFT);
				$downloadTime_e[0] = str_pad($downloadTime_e[0], 2, "0", STR_PAD_LEFT);
				$downloadTime_e[1] = str_pad($downloadTime_e[1], 2, "0", STR_PAD_LEFT);

				$paramCoupon['download_starttime']	= $downloadTime_s[0].":".$downloadTime_s[1];
				$paramCoupon['download_endtime']	= $downloadTime_e[0].":".$downloadTime_e[1];

				// 유효기간 체크
				if($_params['issuePriodType'] == 'date'&& $downloadDate_e[0]){

					if($_params['issueDate'][1] < $downloadDate_e[0]){
						$callback = "parent.document.couponRegist.downloadDate_s[0].focus();";
					openDialogAlert("유효기간 종료일이 다운로드 가능기간보다 이후에 있어야 합니다.<br/>날짜를 조정해 주세요.",450,150,'parent',$callback);
					exit;
					}
				}

				// 요일 체크
				if($_params['downloadWeek_']) {
					$paramCoupon['download_week'] = implode("", $_params['downloadWeek_']);
				}else{
					$paramCoupon['download_week'] = "1234567";
				}
			}
		}else{
			//발급기한제한없음 시 세팅된 기한설정값 초기화
			$paramCoupon['download_startdate'] 	= null;
			$paramCoupon['download_enddate'] 	= null;
			$paramCoupon['download_starttime']	= "00:00";
			$paramCoupon['download_endtime']	= "23:59";
			$paramCoupon['download_week'] = "1234567";
		}

		// 유효기간이 날짜 형태인 경우
		if($_params['issuePriodType'] == 'date'){
			if($_params['issueDate'][1] < $_params['issueDate'][0]){
			$callback = "parent.document.couponRegist.issueDate[0].focus();";
			openDialogAlert("유효기간 종료일이 시작일보다 이후에 있어야 합니다.<br/>날짜를 조정해 주세요.",450,140,'parent',$callback);
			exit;
			}
		}

		if($paramCoupon['sale_type']=='percent'){
			$paramCoupon['percent_goods_sale'] 		= $_params['goodsSalePrice'];
			$paramCoupon['max_percent_goods_sale'] 	= $_params['maxPercentGoodsSale'];
		}elseif($paramCoupon['sale_type']=='won'){
			$paramCoupon['won_goods_sale'] 			= $_params['goodsSalePrice'];
		}

		$paramCoupon['shipping_type'] 				= $_params['shippingType'];
		$paramCoupon['won_shipping_sale'] 			= ($_params['shippingType'] == 'free')? '':$_params['wonShippingSale'];
		$paramCoupon['max_percent_shipping_sale'] 	= ($_params['shippingType'] == 'free')? $_params['wonShippingSale']:'0' ;
		$paramCoupon['duplication_use'] 			= ($_params['duplicationUse'])?$_params['duplicationUse']:0;
		$paramCoupon['issue_priod_type'] 			= $_params['issuePriodType'];

		if($paramCoupon['issue_priod_type']=='date') {
			if(isset($_params['issueDate']) && $_params['issueDate'][0]){
				$paramCoupon['issue_startdate'] 	= $_params['issueDate'][0];
			}
			if(isset($_params['issueDate']) && $_params['issueDate'][1]){
				$paramCoupon['issue_enddate'] 	= $_params['issueDate'][1];
			}
		}elseif($paramCoupon['issue_priod_type']=='day') {
			if(isset($_params['afterIssueDay'])){
				$paramCoupon['after_issue_day'] = if_empty($_params, 'afterIssueDay', '0');
			}
		}elseif($paramCoupon['issue_priod_type']=='months') {
			$paramCoupon['after_issue_day']		= '31';//발급일로부터 말일 28~31사이
		}

		if(isset($_params['limitGoodsPrice'])){
			$paramCoupon['limit_goods_price']		= $_params['limitGoodsPrice'];
		}

		//$paramCoupon['coupon_popup_use']		= if_empty($_params, 'coupon_popup_use', 'N');

		$paramCoupon['coupon_img'] 				= ($_params['couponImg'])?$_params['couponImg']:1;
		if(!empty($_params['couponimage4']) && @is_file(ROOTPATH."data/tmp/".$_params['couponimage4'])) {//rename
			@rename(ROOTPATH."data/tmp/".$_params['couponimage4'], $this->copuonupload_dir.$_params['couponimage4']);
			@chmod($this->copuonupload_dir.$_params['couponimage4'],0707);
			$paramCoupon['coupon_image4'] 			= $_params['couponimage4'];
		}

		$paramCoupon['coupon_mobile_img'] 				= ($_params['couponmobileImg'])?$_params['couponmobileImg']:1;
		if(!empty($_params['couponmobileimage4']) && @is_file(ROOTPATH."data/tmp/".$_params['couponmobileimage4'])) {//rename
			@rename(ROOTPATH."data/tmp/".$_params['couponmobileimage4'], $this->copuonupload_dir.$_params['couponmobileimage4']);
			@chmod($this->copuonupload_dir.$_params['couponmobileimage4'],0707);
			$paramCoupon['coupon_mobile_image4'] 			= $_params['couponmobileimage4'];
		}

		if($save_mode == "modify" ) {
			$paramCoupon['update_date'] = date('Y-m-d H:i:s',time());
		}else{
			$paramCoupon['regist_date']	= $paramCoupon['update_date'] = date('Y-m-d H:i:s',time());
		}

		if	(!(strlen(str_replace('|', '', trim($_params['provider_seq_list']))) > 0))
			$_params['provider_seq_list']		= '';

		$paramCoupon['salescost_admin']			= $_params['salescost_admin'];
		$paramCoupon['salescost_provider']		= if_empty($_params, 'salescost_provider', '0');
		$paramCoupon['provider_list']			= $_params['provider_seq_list'];

		return $paramCoupon;
	}

	//online write/modify ------ 삭제대상 20200519
	public function check_param_online_download()
	{

		// Admin UX/UI 개편. @2020.02.10 pjm 정의 시작
		$this->config->load("couponSet");

		$set_coupon_form			= $this->config->item("set_coupon_form");			//쿠폰종류/발급방식에 따른 등록폼설정값

		$couponType		= $_POST['couponType'];

		$this->validation->set_rules('couponType', '쿠폰종류','trim|required|xss_clean');

		$downloadDate = $_POST['downloadDate_'.$_POST['couponType']];
		$downloadTime = $_POST['downloadTime_'.$_POST['couponType']];

		if( $couponType == 'shipping' ) {
			if	($_POST['provider_seq_list'] && $_POST['provider_seq_list'] != '|' ){
				$_POST['salescost_admin']		= 0;
				$_POST['salescost_provider']	= 100;
			}else{
				$_POST['salescost_admin']		= 100;
				$_POST['salescost_provider']	= 0;
				$_POST['provider_seq_list']		= '';
			}
		}

		// 등급제한 필수체크
		if($set_coupon_form[$couponType] == "gradelimit"){
			$this->validation->set_rules('memberGroups_'.$couponType.'[]', '등급 제한','trim|required|max_length[7]|xss_clean');
		}else{
			$this->validation->set_rules('memberGroups_'.$couponType.'[]', '등급 제한','trim|max_length[7]|xss_clean');
		}

		/*
		if( $_POST['couponType'] == 'memberGroup' ) {
			$this->validation->set_rules('memberGroups_memberGroup[]', '등급 제한','trim|required|max_length[7]|xss_clean');
		}elseif( $_POST['couponType'] == 'memberGroup_shipping' ) {
			$this->validation->set_rules('memberGroups_memberGroup_shipping[]', '등급 제한','trim|required|max_length[7]|xss_clean');
		}elseif( $_POST['couponType'] == 'membermonths' ) {
			$this->validation->set_rules('memberGroups_membermonths[]', '등급 제한','trim|required|max_length[7]|xss_clean');
		}elseif( $_POST['couponType'] == 'membermonths_shipping' ) {
			$this->validation->set_rules('memberGroups_membermonths_shipping[]', '등급 제한','trim|required|max_length[7]|xss_clean');
		}else{
			$this->validation->set_rules('memberGroups_'.$_POST['couponType'].'[]', '등급 제한','trim|max_length[7]|xss_clean');
		}
		*/

		$this->validation->set_rules('downloadDate_'.$couponType.'[]', '기간 제한','trim|max_length[10]|xss_clean');

		$_POST['couponName'] = strip_tags($_POST['couponName']);
		$_POST['couponDesc'] = strip_tags($_POST['couponDesc']);
		$this->validation->set_rules('couponName', '쿠폰명','trim|required|xss_clean');
		$this->validation->set_rules('couponDesc', '쿠폰 설명','trim|xss_clean');
		$this->validation->set_rules('saleType', '쿠폰 혜택 종류','trim|required|max_length[7]|xss_clean');

		if( $couponType == 'point' ) {
			$_POST['coupon_point']			= ($_POST['coupon_point']>0)?$_POST['coupon_point']:'';
			$this->validation->set_rules('coupon_point', '전환 포인트','trim|required|numeric|xss_clean');
		}

		if( $_POST['couponType'] == 'memberlogin' || $_POST['couponType'] == 'memberlogin_shipping' ) {
			//$_POST['memberlogin_terms']			= ($_POST['memberlogin_terms']>0)?$_POST['memberlogin_terms']:'';
			//$this->validation->set_rules('memberlogin_terms', '최근 미구매한 개월','trim|required|numeric|xss_clean');
		}


		$_POST['percentGoodsSale']	= ($_POST['percentGoodsSale']>0)?$_POST['percentGoodsSale']:'';
		$_POST['maxPercentGoodsSale']	= ($_POST['maxPercentGoodsSale']>0)?$_POST['maxPercentGoodsSale']:'';
		$_POST['wonGoodsSale']			= ($_POST['wonGoodsSale']>0)?$_POST['wonGoodsSale']:'';
		if( $couponType == 'shipping'  || strstr($couponType,'_shipping') ) {
		}else{
			if($_POST['coopon_usetype']=='online'){
				if($_POST['saleType']=='percent'){
					$this->validation->set_rules('percentGoodsSale', '할인율','trim|required|numeric|max_length[3]|xss_clean');
					$this->validation->set_rules('maxPercentGoodsSale', '최대 할인 금액','trim|required|numeric|xss_clean');
				}
				if($_POST['saleType']=='won'){
					$this->validation->set_rules('wonGoodsSale', '할인 금액','trim|required|numeric|xss_clean');
				}
			}
		}
		if(isset($_POST['duplicationUse'])){
			$this->validation->set_rules('duplicationUse', '다중 사용','trim|numeric|xss_clean');
		}

		//월1회다운가능쿠폰은 발급 당월 말일까지
		$couponmonthsar = array('memberlogin','memberlogin_shipping','membermonths','membermonths_shipping');
		if( in_array($couponType,$couponmonthsar) ) {
			$_POST['issuePriodType'] = 'months';
		}

		$this->validation->set_rules('issuePriodType', '유효 기간 종류','trim|required|max_length[6]|xss_clean');

		if($_POST['issuePriodType']=='date'){
			$this->validation->set_rules('issueDate[]', '유효 기간','trim|required|max_length[10]|xss_clean');
		}
		if($_POST['issuePriodType']=='day'){
			$this->validation->set_rules('afterIssueDay', '유효 기간','trim|required|max_length[10]|xss_clean');
		}

		$this->validation->set_rules('limitGoodsPrice', '사용제한 금액','trim|numeric|xss_clean');

		if($_POST['issue_type'] == 'issue' ){
			if(!$_POST['issueGoods']) $_POST['issueGoods'] = $_POST['select_goods'];
			if(count($_POST['issueGoods']) == 0 && $_POST['issueCategoryCode'][0] == ""){
				$callback = "parent.document.onlineRegist.downloadDate_download[0].focus();";
				openDialogAlert("사용할 상품 또는 카테고리를 선택해주세요.",450,150,'parent',$callback);
				exit;
			}
			$this->validation->set_rules('issueGoods[]', '적용 상품','trim|numeric|xss_clean');
			$this->validation->set_rules('issueCategoryCode[]', '적용 카테고리','trim|xss_clean');
		}elseif($_POST['issue_type'] == 'except' ){
			if(!$_POST['exceptIssueGoods']) $_POST['exceptIssueGoods'] = $_POST['select_goods'];
			if(count($_POST['exceptIssueGoods']) == 0 && $_POST['exceptIssueCategoryCode'][0] == ""){
				$callback = "parent.document.onlineRegist.downloadDate_download[0].focus();";
				openDialogAlert("사용을 제한할 상품 또는 카테고리를 선택해주세요.",450,150,'parent',$callback);
				exit;
			}
			$this->validation->set_rules('exceptIssueGoods[]', '적용예외상품','trim|numeric|xss_clean');
			$this->validation->set_rules('exceptIssueCategoryCode[]', '적용 예외 카테고리','trim|xss_clean');
		}

		$this->validation->set_rules('couponImg', '쿠폰 PC용 이미지','trim|numeric|max_length[3]|xss_clean');
		$this->validation->set_rules('couponmobileImg', '쿠폰 Mobile용 이미지','trim|numeric|max_length[3]|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		// 쿠폰혜택 체크
		if($_POST['coopon_usetype']=='offline'){
			if($_POST['benefit_txt']){
				$paramCoupon['benefit'] = $_POST['benefit_txt'];
			} else {
				$callback = "parent.document.onlineRegist.benefit_txt.focus();";
				openDialogAlert("쿠폰에 명시될 혜택은 필수 입니다.<br/>혜택을 기재해 주세요.",450,140,'parent',$callback);
				exit;
			}

			if($_POST['limit_txt'])	$paramCoupon['limit_txt'] = $_POST['limit_txt'];
		}


		// 기간제한 쿠폰인 경우
		if($set_coupon_form[$_POST['couponType']]['downloadPeriodSet'] == "period"){

			// 기간을 기재 했을경우
			if($downloadDate[0] && $downloadDate[3]){

				// 제한기간 검사
				$pattan = "/^(19|20)\d{2}-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[0-1])$/";
				if(!preg_match($pattan,$downloadDate[0])){
					$callback = "parent.document.onlineRegist.downloadDate_download[0].focus();";
					echo $downloadDate[0];
					openDialogAlert("제한기간 시작일이 올바르지 않습니다.<br/>날짜를 조정해 주세요.",450,150,'parent',$callback);
					exit;
				}
				if(!preg_match($pattan,$downloadDate[3])){
					$callback = "parent.document.onlineRegist.downloadDate_download[0].focus();";
					openDialogAlert("제한기간 종료일이 올바르지 않습니다.<br/>날짜를 조정해 주세요.",450,150,'parent',$callback);
					exit;
				}

				// 제한기간 체크
				if(!$downloadDate[1]) $downloadDate[1] = "00";
				if(!$downloadDate[2]) $downloadDate[2] = "00";
				if(!$downloadDate[4]) $downloadDate[4] = "23";
				if(!$downloadDate[5]) $downloadDate[5] = "59";

				$downloadDate_start	= $downloadDate[0] . " " . $downloadDate[1] . ":" . $downloadDate[2];
				$downloadDate_end	= $downloadDate[3] . " " . $downloadDate[4] . ":" . $downloadDate[5];

				if(strtotime($downloadDate_start) > strtotime($downloadDate_end)){
					$callback = "parent.document.onlineRegist.downloadDate_download[0].focus();";
					openDialogAlert("제한 기간 종료일이 시작일보다 이후에 있어야 합니다.<br/>날짜를 조정해 주세요.",450,150,'parent',$callback);
					exit;
				}

				// 요일 체크
				if($_POST['downloadWeek_'.$_POST['couponType']]) {
					$paramCoupon['download_week'] = implode("", $_POST['downloadWeek_'.$_POST['couponType']]);
				}else{
					$paramCoupon['download_week'] = "1234567";
				}

				// 다운로드 가능시간 체크
				if($downloadTime[0] && $downloadTime[1]
					&& $downloadTime[2] && $downloadTime[3])
				{
					$paramCoupon['download_starttime'] = $downloadTime[0].":".$downloadTime[1];
					$paramCoupon['download_endtime'] = $downloadTime[2].":".$downloadTime[3];
				}else{
					$paramCoupon['download_starttime'] = "00:00";
					$paramCoupon['download_endtime'] = "23:59";
				}
			}else{
				// 다운로드 가능 시작시간 기본값 설정
				if($downloadTime[0] && !$downloadTime[1]){
					$paramCoupon['download_starttime'] = $downloadTime[0].":00";
				}else if(!$downloadTime[0] && $downloadTime[1]){
					$paramCoupon['download_starttime'] = "00:".$downloadTime[1];
				}else if($downloadTime[0] && $downloadTime[1]){
					$paramCoupon['download_starttime'] = $downloadTime[0].":".$downloadTime[1];
				}else{
					$paramCoupon['download_starttime'] = "00:00";
				}

				// 다운로드 가능 끝시간 기본값 설정
				if($downloadTime[2] && !$downloadTime[3]){
					$paramCoupon['download_endtime'] = $downloadTime[2].":59";
				}else if(!$downloadTime[2] && $downloadTime[3]){
					$paramCoupon['download_endtime'] = "23:".$downloadTime[1];
				}else if($downloadTime[2] && $downloadTime[3]){
					$paramCoupon['download_endtime'] = $downloadTime[2].":".$downloadTime[3];
				}else{
					$paramCoupon['download_endtime'] = "23:59";
				}

				// 요일 체크
				if($_POST['downloadWeek_'.$_POST['couponType']]) {
					$paramCoupon['download_week'] = implode("", $_POST['downloadWeek_'.$_POST['couponType']]);
				}
			}

			// 유효기간 체크
			if($_POST['issuePriodType'] == 'date'&& $downloadDate[3]){

				if($_POST['issueDate'][1] < $downloadDate[3]){
				$callback = "parent.document.onlineRegist.downloadDate_download[0].focus();";
				openDialogAlert("유효기간 종료일이 다운로드 가능기간보다 이후에 있어야 합니다.<br/>날짜를 조정해 주세요.",450,150,'parent',$callback);
				exit;
				}
			}
		}

		if($_POST['issuePriodType'] == 'date'){
			if($_POST['issueDate'][1] < $_POST['issueDate'][0]){
			$callback = "parent.document.onlineRegist.issueDate[0].focus();";
			openDialogAlert("유효기간 종료일이 시작일보다 이후에 있어야 합니다.<br/>날짜를 조정해 주세요.",450,140,'parent',$callback);
			exit;
			}
		}


		$paramCoupon['type'] 						= $_POST['couponType'];
		$paramCoupon['use_type']					= $_POST['coopon_usetype'];
		$paramCoupon['issue_type'] 					= if_empty($_POST, 'issue_type', 'all');
		$paramCoupon['issue_stop'] 					= if_empty($_POST, $paramCoupon['type'].'_issue_stop', '0');
		$paramCoupon['coupon_point'] 				= if_empty($_POST, 'coupon_point', '0');
		$_POST['duplicationUse'] 					= if_empty($_POST, 'duplicationUse', '0');

		$paramCoupon['sale_agent'] 					= if_empty($_POST, 'sale_agent', 'a');
		$paramCoupon['sale_payment'] 				= if_empty($_POST, 'sale_payment', 'a');
		$paramCoupon['sale_referer'] 				= if_empty($_POST, 'sale_referer', 'a');
		$paramCoupon['sale_referer_type'] 		= if_empty($_POST, 'sale_referer_type', 'a');
		$paramCoupon['sale_referer_item'] 		= $_POST['sale_referer_item'];

		// o2o 쿠폰 체크 초기화
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_check_param_online_download($paramCoupon, $_POST);


		if( $paramCoupon['type'] == 'memberlogin' ) {
			$paramCoupon['memberlogin_terms'] 	= if_empty($_POST, 'memberlogin_terms', '1');
		}elseif ( $paramCoupon['type'] == 'memberlogin_shipping' ) {
			$paramCoupon['memberlogin_terms'] 	= if_empty($_POST, 'memberlogin_shipping_terms', '1');
		}else{
			$paramCoupon['memberlogin_terms'] 	= 0;
		}
		$paramCoupon['order_terms'] 				= if_empty($_POST, 'order_terms', '0');



		$paramCoupon['download_limit'] 			= ($_POST['downloadLimit_'.$paramCoupon['type']])?$_POST['downloadLimit_'.$paramCoupon['type']]:'unlimit';
		$paramCoupon['download_limit_ea'] 		= ($_POST['downloadLimitEa_'.$paramCoupon['type']])?$_POST['downloadLimitEa_'.$paramCoupon['type']]:0;

		if(isset($downloadDate) && $downloadDate[0]){
			// 기간제한 시간체크
			if(!$downloadDate[1])	$downloadDate[1] = '00';
			if(!$downloadDate[2])	$downloadDate[2] = '00';
			$paramCoupon['download_startdate'] 	= $downloadDate[0]." " .$downloadDate[1].":".$downloadDate[2];
		}

		if(isset($downloadDate) && $downloadDate[3]){
			// 기간체한 시간체크
			if(!$downloadDate[4])	$downloadDate[4] = '23';
			if(!$downloadDate[5])	$downloadDate[5] = '59';
			$paramCoupon['download_enddate'] 	= $downloadDate[3]." ".$downloadDate[4].":".$downloadDate[5];
		}


		//생일쿠폰
		if(isset($_POST['beforeBirthday'])) $paramCoupon['before_birthday'] = $_POST['beforeBirthday'];
		if(isset($_POST['afterBirthday'])) $paramCoupon['after_birthday'] = $_POST['afterBirthday'];

		//기념일쿠폰
		if(isset($_POST['beforeanniversary'])) $paramCoupon['before_anniversary'] = $_POST['beforeanniversary'];
		if(isset($_POST['afteranniversary'])) $paramCoupon['after_anniversary'] = $_POST['afteranniversary'];

		if( $paramCoupon['type'] == 'memberGroup' ) {
		if(isset($_POST['afterUpgrade'])) $paramCoupon['after_upgrade'] = $_POST['afterUpgrade'];
		}elseif ( $paramCoupon['type'] == 'memberGroup_shipping' ) {
			if(isset($_POST['shipping_afterUpgrade'])) $paramCoupon['after_upgrade'] = $_POST['shipping_afterUpgrade'];
		}


		$paramCoupon['coupon_name'] 			= $_POST['couponName'];
		$paramCoupon['coupon_desc'] 			= $_POST['couponDesc'];
		$paramCoupon['sale_type'] 				= $_POST['saleType'];
		$paramCoupon['discount_seller_type'] 	= $_POST['discount_seller_type'];

		$paramCoupon['coupon_same_time']		= if_empty($_POST, 'couponsametime', 'Y');//동시사용여부: Y

		if($paramCoupon['sale_type']=='percent'){
			$paramCoupon['percent_goods_sale'] 			= $_POST['percentGoodsSale'];
			$paramCoupon['max_percent_goods_sale'] 	= $_POST['maxPercentGoodsSale'];
		}elseif($paramCoupon['sale_type']=='won'){
			$paramCoupon['won_goods_sale'] 			= $_POST['wonGoodsSale'];
		}


		$paramCoupon['shipping_type'] 					= $_POST['shippingType'];
		$paramCoupon['won_shipping_sale'] 			= $_POST['wonShippingSale'];
		$paramCoupon['max_percent_shipping_sale'] 	= ($_POST['maxPercentShippingSale'])? $_POST['maxPercentShippingSale']:'0' ;

		$paramCoupon['duplication_use'] 	= ($_POST['duplicationUse'])?$_POST['duplicationUse']:0;

		$paramCoupon['issue_priod_type'] 		= $_POST['issuePriodType'];

		if($paramCoupon['issue_priod_type']=='date') {
			if(isset($_POST['issueDate']) && $_POST['issueDate'][0]){
				$paramCoupon['issue_startdate'] 	= $_POST['issueDate'][0];
			}
			if(isset($_POST['issueDate']) && $_POST['issueDate'][1]){
				$paramCoupon['issue_enddate'] 	= $_POST['issueDate'][1];
			}

		}elseif($paramCoupon['issue_priod_type']=='day') {

			if(isset($_POST['afterIssueDay']) && $_POST['afterIssueDay']){
				$paramCoupon['after_issue_day'] = if_empty($_POST, 'afterIssueDay', '0');
			}

		}elseif($paramCoupon['issue_priod_type']=='months') {
			$paramCoupon['after_issue_day']		= '31';//발급일로부터 말일 28~31사이
		}

		if(isset($_POST['limitGoodsPrice'])){
			$paramCoupon['limit_goods_price']		= $_POST['limitGoodsPrice'];
		}

		//$paramCoupon['coupon_popup_use']		= if_empty($_POST, 'coupon_popup_use', 'N');

		$paramCoupon['coupon_img'] 				= $_POST['couponImg'];
		if(!empty($_POST['couponimage4']) && @is_file(ROOTPATH."data/tmp/".$_POST['couponimage4'])) {//rename
			@rename(ROOTPATH."data/tmp/".$_POST['couponimage4'], $this->copuonupload_dir.$_POST['couponimage4']);
			@chmod($this->copuonupload_dir.$_POST['couponimage4'],0707);
			$paramCoupon['coupon_image4'] 			= $_POST['couponimage4'];
		}

		$paramCoupon['coupon_mobile_img'] 				= $_POST['couponmobileImg'];
		if(!empty($_POST['couponmobileimage4']) && @is_file(ROOTPATH."data/tmp/".$_POST['couponmobileimage4'])) {//rename
			@rename(ROOTPATH."data/tmp/".$_POST['couponmobileimage4'], $this->copuonupload_dir.$_POST['couponmobileimage4']);
			@chmod($this->copuonupload_dir.$_POST['couponmobileimage4'],0707);
			$paramCoupon['coupon_mobile_image4'] 			= $_POST['couponmobileimage4'];
		}

		if( $_POST['couponSeq'] ) {
			$paramCoupon['update_date'] = date('Y-m-d H:i:s',time());
		}else{
			$paramCoupon['regist_date']	= $paramCoupon['update_date'] = date('Y-m-d H:i:s',time());
		}

		if	(!(strlen(str_replace('|', '', trim($_POST['provider_seq_list']))) > 0))
			$_POST['provider_seq_list']		= '';

		$paramCoupon['salescost_admin']			= $_POST['salescost_admin'];
		$paramCoupon['salescost_provider']		= if_empty($_POST, 'salescost_provider', '0');
		$paramCoupon['provider_list']					= $_POST['provider_seq_list'];

		return $paramCoupon;
	}

	//offline coupon write/modfiy ------ 삭제대상 20200519
	public function check_param_offline_download()
	{
		$this->validation->set_rules('couponType', '쿠폰종류','trim|required|xss_clean');
		$this->validation->set_rules('offline_type', '인증번호발급방식','trim|required|max_length[11]|xss_clean');

		$this->validation->set_rules('downloadLimitEa_'.$_POST['couponType'], '인증번호 인증횟수 제한','trim|required|numeric|xss_clean');
		$this->validation->set_rules('downloadDate_'.$_POST['couponType'].'[]', '인증번호 인증기간 제한','trim|max_length[10]|xss_clean');

		$_POST['couponName'] = strip_tags($_POST['couponName']);
		$_POST['couponDesc'] = strip_tags($_POST['couponDesc']);
		$this->validation->set_rules('couponName', '쿠폰명','trim|required|xss_clean');
		$this->validation->set_rules('couponDesc', '쿠폰 설명','trim|xss_clean');
		if(!$_POST['couponSeq']) {//등록시에만 적용
			if( $_POST['offline_type'] == 'random') {//자동생성 > 인증번호 갯수
				$this->validation->set_rules('offline_random_num', '자동생성 시 갯수','trim|numeric|min_length[1]|max_length[5]|required|xss_clean');

				if($_POST['offline_random_num'] < 1 ){
					$callback = "if(parent.document.getElementsByName('offline_random_num')[0]) parent.document.getElementsByName('offline_random_num')[0].focus();";
					openDialogAlert('자동생성 시 갯수는 1이상부터 가능합니다..',400,140,'parent',$callback);
					exit;
				}
				if($_POST['offline_random_num'] > 10000 ){
					$callback = "if(parent.document.getElementsByName('offline_random_num')[0]) parent.document.getElementsByName('offline_random_num')[0].focus();";
					openDialogAlert('자동생성 시 갯수는 10000개 이하까지 가능합니다.',400,140,'parent',$callback);
					exit;
				}
			}elseif( $_POST['offline_type'] == 'one') {//자동생성 > 동일번호
				if( $_POST['offlineLimit_one'] == 'limit') {//자동생성 > 동일번호 > 선착순
					$this->validation->set_rules('offlineLimitEa_one', '동일 인증번호 선착순 갯수','trim|numeric|min_length[1]|required|xss_clean');

					if($_POST['offlineLimitEa_one'] < 1 ){
						$callback = "if(parent.document.getElementsByName('offlineLimitEa_one')[0]) parent.document.getElementsByName('offlineLimitEa_one')[0].focus();";
						openDialogAlert('동일 인증번호 선착순 갯수는 1번이상부터 가능합니다..',400,140,'parent',$callback);
						exit;
					}
				}
			}elseif( $_POST['offline_type'] == 'input') {//수동생성 > 동일번호
				$this->validation->set_rules('offline_input_num', '동일 인증번호','trim|required|xss_clean');
				if( $_POST['offlineLimit_input'] == 'limit') {//자동생성 > 동일번호 > 선착순
					$this->validation->set_rules('offlineLimitEa_input', '동일 인증번호 > 선착순 갯수','trim|numeric|required|xss_clean');
					if($_POST['offlineLimitEa_input'] < 1 ){
						$callback = "if(parent.document.getElementsByName('offlineLimitEa_input')[0]) parent.document.getElementsByName('offlineLimitEa_input')[0].focus();";
						openDialogAlert('동일 인증번호 선착순 갯수는 1번이상부터 가능합니다..',400,140,'parent',$callback);
						exit;
					}
				}
			}elseif( $_POST['offline_type'] == 'file') {//수동생성 > 파일
				$this->validation->set_rules('offline_file', '수동생성 > 엑셀파일','trim|required|xss_clean');
			}
		}

		if( $_POST['couponType'] == 'offline_emoney') {//마일리지 지급쿠폰

			$this->validation->set_rules('offline_emoney', '사용제한 금액','trim|numeric|xss_clean');

		}else{//offline_coupon

			$this->validation->set_rules('saleType', '쿠폰 혜택 종류','trim|required|max_length[7]|xss_clean');
			if($_POST['saleType']=='percent'){
				$this->validation->set_rules('percentGoodsSale', '할인율','trim|required|numeric|max_length[3]|xss_clean');
				$this->validation->set_rules('maxPercentGoodsSale', '최대 할인 금액','trim|required|numeric|xss_clean');
			}
			if($_POST['saleType']=='won'){
				$this->validation->set_rules('wonGoodsSale', '할인 금액','trim|required|numeric|xss_clean');
			}

			$this->validation->set_rules('issuePriodType', '유효 기간 종류','trim|required|max_length[6]|xss_clean');
			if($_POST['issuePriodType']=='date'){
				$this->validation->set_rules('issueDate[]', '유효 기간','trim|required|max_length[10]|xss_clean');
			}
			if($_POST['issuePriodType']=='day'){
				$this->validation->set_rules('afterIssueDay', '유효 기간','trim|required|max_length[10]|xss_clean');
			}

			$this->validation->set_rules('limitGoodsPrice', '사용제한 금액','trim|numeric|xss_clean');

			if($_POST['issue_type'] == 'issue' ){
				if(count($_POST['issueGoods']) == 0 && $_POST['issueCategoryCode'][0] == ""){
					$callback = "parent.document.onlineRegist.downloadDate_download[0].focus();";
					openDialogAlert("사용할 상품 또는 카테고리를 선택해주세요.",450,150,'parent',$callback);
					exit;
				}
				$this->validation->set_rules('issueGoods[]', '적용 상품','trim|numeric|xss_clean');
				$this->validation->set_rules('issueCategoryCode[]', '적용 카테고리','trim|xss_clean');
			}elseif($_POST['issue_type'] == 'except' ){
				if(count($_POST['exceptIssueGoods']) == 0 && $_POST['exceptIssueCategoryCode'][0] == ""){
					$callback = "parent.document.onlineRegist.downloadDate_download[0].focus();";
					openDialogAlert("사용을 제한할 상품 또는 카테고리를 선택해주세요.",450,150,'parent',$callback);
					exit;
				}
				$this->validation->set_rules('exceptIssueGoods[]', '적용예외상품','trim|numeric|xss_clean');
				$this->validation->set_rules('exceptIssueCategoryCode[]', '적용 예외 카테고리','trim|xss_clean');
			}

			$this->validation->set_rules('couponImg', '쿠폰 PC용  이미지','trim|numeric|max_length[3]|xss_clean');
			$this->validation->set_rules('couponmobileImg', '쿠폰 Mobile용  이미지','trim|numeric|max_length[3]|xss_clean');
		}


		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}


		$paramCoupon['type'] 							= $_POST['couponType'];
		$paramCoupon['offline_type'] 					= $_POST['offline_type'];

		if( $paramCoupon['offline_type'] == 'random') {//자동생성 > 인증번호 갯수
			$paramCoupon['offline_random_num'] 	= if_empty($_POST, 'offline_random_num', '0');
		}elseif( $paramCoupon['offline_type'] == 'one') {//자동생성 > 동일번호
			$paramCoupon['offline_limit'] 					= $_POST['offlineLimit_one'];
			if( $paramCoupon['offline_limit'] == 'limit') {//자동생성 > 동일번호 > 선착순
				$paramCoupon['offline_limit_ea'] 		= if_empty($_POST, 'offlineLimitEa_one', '0');
			}
		}elseif( $paramCoupon['offline_type'] == 'input') {//수동생성 > 동일번호
			$paramCoupon['offline_input_serialnumber'] 		= if_empty($_POST, 'offline_input_num', '');
			$paramCoupon['offline_limit'] 								= $_POST['offlineLimit_input'];
			if(!$_POST['couponSeq']) {//등록시에만 적용
				// offline쿠폰 인증번호 체크
				$sc['offline_serialnumber'] = $paramCoupon['offline_input_serialnumber'];
				$offlienresult = $this->get_offlinecoupon_total_count($sc);
				if(!$offlienresult){
					$offlienresult = $this->get_offlinecoupon_input_total_count($sc);
				}
				if($offlienresult){
					$err = '이미 등록된 인증번호입니다.';
					$callback = "if(parent.document.getElementsByName('offline_input_num')[0]) parent.document.getElementsByName('offline_input_num')[0].focus();";
					openDialogAlert($err,400,140,'parent',$callback);
					exit;
				}
			}

			if( $paramCoupon['offline_limit'] == 'limit') {//자동생성 > 동일번호 > 선착순
				$paramCoupon['offline_limit_ea'] 		= if_empty($_POST, 'offlineLimitEa_input', '0');
			}
		}elseif( $paramCoupon['offline_type'] == 'file') {//수동생성 > 파일
			//'offline_file', '수동생성 > 엑셀파일' upload
		}

		$paramCoupon['issue_type'] 					= if_empty($_POST, 'issue_type', 'all');
		$paramCoupon['issue_stop'] 					= if_empty($_POST, $paramCoupon['type'].'_issue_stop', '0');
		$paramCoupon['offline_emoney'] 			= if_empty($_POST, 'offline_emoney', '0');
		$_POST['duplicationUse']       = if_empty($_POST, 'duplicationUse', '0');

		$paramCoupon['sale_agent']					= if_empty($_POST, 'sale_agent', 'a');
		$paramCoupon['sale_payment']				= if_empty($_POST, 'sale_payment', 'a');
		$paramCoupon['sale_referer']					= if_empty($_POST, 'sale_referer', 'a');
		$paramCoupon['sale_referer_type']		= if_empty($_POST, 'sale_referer_type', 'a');
		$paramCoupon['sale_referer_item']		= $_POST['sale_referer_item'];

		if( $paramCoupon['type'] == 'memberlogin' ) {
			$paramCoupon['memberlogin_terms'] 	= if_empty($_POST, 'memberlogin_terms', '0');
		}elseif ( $paramCoupon['type'] == 'memberlogin_shipping' ) {
			$paramCoupon['memberlogin_terms'] 	= if_empty($_POST, 'memberlogin_shipping_terms', '0');
		}else{
			$paramCoupon['memberlogin_terms'] 	= 0;
		}
		$paramCoupon['order_terms'] 				= if_empty($_POST, 'order_terms', '0');

		$paramCoupon['offline_reserve_select'] = $_POST['offline_reserve_select'];
		$paramCoupon['offline_reserve_year'] 	= $_POST['offline_reserve_year'];
		$paramCoupon['offline_reserve_direct'] 	= $_POST['offline_reserve_direct'];

		$paramCoupon['download_limit'] 			= ($_POST['downloadLimit_'.$paramCoupon['type']])?$_POST['downloadLimit_'.$paramCoupon['type']]:'unlimit';
		$paramCoupon['download_limit_ea'] 		= ($_POST['downloadLimitEa_'.$paramCoupon['type']])?$_POST['downloadLimitEa_'.$paramCoupon['type']]:0;

		if(isset($_POST['downloadDate_'.$_POST['couponType']]) && $_POST['downloadDate_'.$_POST['couponType']][0]){
			$paramCoupon['download_startdate'] 	= $_POST['downloadDate_'.$_POST['couponType']][0];
		}

		if(isset($_POST['downloadDate_'.$_POST['couponType']]) && $_POST['downloadDate_'.$_POST['couponType']][1]){
			$paramCoupon['download_enddate'] 	= $_POST['downloadDate_'.$_POST['couponType']][1];
		}

		$paramCoupon['coupon_name'] 			= $_POST['couponName'];
		$paramCoupon['coupon_desc'] 			= $_POST['couponDesc'];
		$paramCoupon['sale_type'] 				= $_POST['saleType'];

		$paramCoupon['coupon_same_time']		= if_empty($_POST, 'couponsametime', 'Y');//동시사용여부: Y

		if($paramCoupon['sale_type']=='percent'){
			$paramCoupon['percent_goods_sale'] 			= $_POST['percentGoodsSale'];
			$paramCoupon['max_percent_goods_sale'] 	= $_POST['maxPercentGoodsSale'];
		}elseif($paramCoupon['sale_type']=='won'){
			$paramCoupon['won_goods_sale'] 			= $_POST['wonGoodsSale'];
		}

		$paramCoupon['shipping_type'] 					= $_POST['shippingType'];
		$paramCoupon['won_shipping_sale'] 			= $_POST['wonShippingSale'];
		$paramCoupon['max_percent_shipping_sale'] 	= $_POST['maxPercentShippingSale'];

		$paramCoupon['duplication_use']  = ($_POST['duplicationUse'])?$_POST['duplicationUse']:0;

		$paramCoupon['issue_priod_type'] 		= $_POST['issuePriodType'];

		if($paramCoupon['issue_priod_type']=='date') {
			if(isset($_POST['issueDate']) && $_POST['issueDate'][0]){
				$paramCoupon['issue_startdate'] 	= $_POST['issueDate'][0];
			}
			if(isset($_POST['issueDate']) && $_POST['issueDate'][1]){
				$paramCoupon['issue_enddate'] 	= $_POST['issueDate'][1];
			}

		}elseif($paramCoupon['issue_priod_type']=='day') {
			if(isset($_POST['afterIssueDay']) && $_POST['afterIssueDay']){
				$paramCoupon['after_issue_day']	= if_empty($_POST, 'afterIssueDay', '0');
			}
		}

		if(isset($_POST['limitGoodsPrice']) && $_POST['limitGoodsPrice']){
			$paramCoupon['limit_goods_price']		= $_POST['limitGoodsPrice'];
		}

		$paramCoupon['coupon_img'] 				= '';
		$paramCoupon['coupon_mobile_img'] 		= '';

		if( $_POST['couponSeq'] ) {
			$paramCoupon['update_date'] = date('Y-m-d H:i:s',time());
		}else{
			$paramCoupon['regist_date']	= $paramCoupon['update_date'] = date('Y-m-d H:i:s',time());
		}

		$paramCoupon['salescost_admin']			= $_POST['salescost_admin'];
		$paramCoupon['salescost_provider']		= if_empty($_POST, 'salescost_provider', '0');
		$paramCoupon['provider_list']			= $_POST['provider_seq_list'];
		return $paramCoupon;
	}

	/* 사용자의 상품쿠폰 >다운시 개별체크용  */
	public function get_able_download($today,$memberSeq,$goodsSeq,$category,$couponSeq)
	{
		if( defined('__ADMIN__') != true ) {
			if(!$memberSeq) return;
			$membersql = "AND d.member_seq='".$memberSeq."' ";
		}
		if( $memberSeq ) {
			$this->load->model('membermodel');
			$this->mdata = $this->membermodel->get_member_data($memberSeq);//회원정보
			$mbquery = ",
			(
				SELECT count(*)
				FROM fm_coupon_group
				WHERE coupon_seq=c.coupon_seq AND `group_seq`='".$this->mdata['group_seq']."'
			) as mbgroup_issue_cnt,
			(
				SELECT count(*)
				FROM fm_coupon_group
				WHERE coupon_seq=c.coupon_seq
			) as allgroup_issue_cnt";

			$mbwhere = " AND ((allgroup_issue_cnt =0 AND mbgroup_issue_cnt=0) OR (allgroup_issue_cnt>0 AND mbgroup_issue_cnt>0))";
		}
		$issue_subquery = $except_subquery = "";
		if(count($category)>0){
			$issue_subquery = "+(select count(*) FROM fm_coupon_issuecategory WHERE coupon_seq=c.coupon_seq AND `type`='issue' AND category_code IN ('".implode("','",$category)."'))";
			$except_subquery = "+(select count(*) FROM fm_coupon_issuecategory WHERE coupon_seq=c.coupon_seq AND `type`='except' AND category_code IN ('".implode("','",$category)."'))";
		}

		if($this->_is_mobile_app_agent) {
			$coupontypear = array('download','shipping','mobile');
			$mobilequery = " ";
		}elseif($this->_is_mobile_agent) {
			$coupontypear = array('download','shipping','mobile');
			$mobilequery = " AND c.sale_agent != 'app' ";
		}else{
			$coupontypear = array('download','shipping');
			$mobilequery = " AND c.sale_agent != 'app' AND c.sale_agent != 'm' ";//모바일
		}
		$coupontype = " AND c.type IN ('".implode("','",$coupontypear)."') ".$mobilequery;

		// o2o 사용 매장 조건 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_public_able_o2o_sale_store($coupontype, "c");

		/*쿠폰 다운로드 URL 로 다운 받을 경우 추가 2015-04-20 */
		$goodsSql = '';
		$exceptSql = '';
		if ($goodsSeq) {
			$goodsSql = "AND goods_seq='".$goodsSeq."' ";
			$exceptSql = "AND except_cnt=0 AND (all_issue_cnt=0 OR issue_cnt>0)";
		}

		$query = "SELECT coupon.*
					FROM (
						SELECT c.*,
							SUM(if(d.use_status='used',1,0)) used_cnt,
							SUM(if(d.use_status='unused',1,0)) unused_cnt,
							(select regist_date from fm_download where coupon_seq = c.coupon_seq AND member_seq='".$memberSeq."' order by regist_date desc limit 1 ) download_regist_date,
							SUM(if(d.use_status='unused' AND d.issue_enddate < '".$today."',1,0)) cancel_cnt,
							(
								SELECT count(*)
								FROM fm_coupon_issuegoods
								WHERE coupon_seq=c.coupon_seq AND `type`='issue'
							)+
							(
								SELECT count(*)
								FROM fm_coupon_issuecategory
								WHERE coupon_seq=c.coupon_seq AND `type`='issue'
							) as all_issue_cnt,
							(
								SELECT count(*)
								FROM fm_coupon_issuegoods
								WHERE coupon_seq=c.coupon_seq ".$goodsSql." AND `type`='issue'
							)".$issue_subquery." as issue_cnt,
							(
								select count(*)
								FROM fm_coupon_issuegoods
								WHERE coupon_seq=c.coupon_seq ".$goodsSql." AND `type`='except'
							)".$except_subquery." as except_cnt
							{$mbquery}
						FROM fm_coupon c
							LEFT JOIN fm_download d ON c.coupon_seq = d.coupon_seq ".$membersql."
						WHERE
							c.coupon_seq='".$couponSeq."'
							".$coupontype."
							AND
							(
								(
									(c.download_startdate is null  AND c.download_enddate is null )
									OR
									(c.download_startdate <='".date('Y-m-d H:i:s',time())."' AND c.download_enddate >='".date('Y-m-d H:i:s',time())."')
								)
								AND (
									(c.download_starttime is null  AND c.download_endtime is null )
									OR
									(c.download_starttime <='".date('H:i',time())."' AND c.download_endtime >= '".date('H:i',time())."')
								)
								AND INSTR(c.download_week, '".date('N')."') > 0
							)
						GROUP BY c.coupon_seq
					) coupon
				WHERE 1 ".$exceptSql.$mbwhere."
					AND
					(
						( used_cnt > 0 AND duplication_use = 1 )
						OR  used_cnt = 0
					)";
		$query = $this->db->query($query);
		list($result) = $query->result_array();
		return $result;
	}

	/* 상품상세의 다운로드 가능한 쿠폰목록  */
	public function get_able_download_list($today,$memberSeq,$goodsSeq,$category,$price,$use_type=null)
	{
		if( $memberSeq ) {
			$this->load->model('membermodel');
			$this->mdata = $this->membermodel->get_member_data($memberSeq);//회원정보
			$mbquery = ",
			(
				SELECT count(*)
				FROM fm_coupon_group
				WHERE coupon_seq=c.coupon_seq AND `group_seq`='".$this->mdata['group_seq']."'
			) as mbgroup_issue_cnt,
			(
				SELECT count(*)
				FROM fm_coupon_group
				WHERE coupon_seq=c.coupon_seq
			) as allgroup_issue_cnt";

			$mbwhere = " AND ((allgroup_issue_cnt =0 AND mbgroup_issue_cnt=0) OR (allgroup_issue_cnt>0 AND mbgroup_issue_cnt>0))";
		}

		$issue_subquery = $except_subquery = "";
		if(count($category)>0){
			$issue_subquery = "+(
												select count(*) FROM
												fm_coupon_issuecategory
												WHERE coupon_seq=c.coupon_seq AND `type`='issue' AND category_code IN ('".implode("','",$category)."')
											)";
			$except_subquery = "+(
													select count(*) FROM
													fm_coupon_issuecategory
													WHERE coupon_seq=c.coupon_seq AND `type`='except' AND category_code IN ('".implode("','",$category)."')
												)";
		}

		/**if($this->_is_mobile_agent) {
			$coupontype = "c.type IN ('download','mobile')";
		}else{
			$coupontype = "c.type IN ('download')";
		}
		**/

		$mobilequery = "";
		if($this->_is_mobile_app_agent) {
			$coupontypear = array('download','mobile');
			if ( serviceLimit('H_ST') ) {
				$mobilequery .= " AND c.use_type != 'offline' ";//모바일
			}
		}elseif($this->_is_mobile_agent) {
			$coupontypear = array('download','mobile');
			if ( serviceLimit('H_ST') ) {
				$mobilequery .= " AND c.sale_agent != 'app' AND c.use_type != 'offline' ";//모바일
			}else{
				$mobilequery .= " AND c.sale_agent != 'app' ";//모바일
			}
		}else{
			$coupontypear = array('download');
			if ( serviceLimit('H_ST') ) {
				$mobilequery .= " AND c.sale_agent != 'app' AND c.sale_agent != 'm' ";
			} else {
				$mobilequery .= " AND c.sale_agent != 'app' AND c.sale_agent != 'm' AND c.use_type != 'offline' ";
			}
		}
		$coupontype = " c.type IN ('".implode("','",$coupontypear)."') ".$mobilequery;

		if($use_type){
			$coupontype .= "AND c.use_type = '".$use_type."' ";
		}
		$coupontype .= "AND c.issue_stop = '0' ";

		// o2o 사용 매장 조건 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_public_able_o2o_sale_store($coupontype, "c");

		$query = "SELECT coupon.*
					FROM (
						SELECT c.*,
							SUM(if(d.use_status='used',1,0)) used_cnt,
							SUM(if(d.use_status='unused',1,0)) unused_cnt,
							(select regist_date from fm_download where coupon_seq = c.coupon_seq AND member_seq='".$memberSeq."' order by regist_date desc limit 1 ) download_regist_date,
							SUM(if(d.use_status='unused' AND d.issue_enddate < '".$today."',1,0)) cancel_cnt,
							(
								SELECT count(*)
								FROM fm_coupon_issuegoods
								WHERE coupon_seq=c.coupon_seq AND `type`='issue'
							)+
							(
								SELECT count(*)
								FROM fm_coupon_issuecategory
								WHERE coupon_seq=c.coupon_seq AND `type`='issue'
							) as all_issue_cnt,
							(
								SELECT count(*)
								FROM fm_coupon_issuegoods
								WHERE coupon_seq=c.coupon_seq AND goods_seq='".$goodsSeq."' AND `type`='issue'
							)".$issue_subquery." as issue_cnt,
							(
								select count(*)
								FROM fm_coupon_issuegoods
								WHERE coupon_seq=c.coupon_seq AND goods_seq='".$goodsSeq."' AND `type`='except'
							)".$except_subquery." as except_cnt
							{$mbquery}
						FROM fm_coupon c
							LEFT JOIN fm_download d ON c.coupon_seq = d.coupon_seq AND d.member_seq='".$memberSeq."'
						WHERE
							(
								".$coupontype."
								AND (
									(c.download_startdate is null  AND c.download_enddate is null )
									OR
									(c.download_startdate <='".date('Y-m-d H:i:s',time())."' AND c.download_enddate >='".date('Y-m-d H:i:s',time())."')
								)
								AND (
									(c.download_starttime is null  AND c.download_endtime is null )
									OR
									(c.download_starttime <='".date('H:i',time())."' AND c.download_endtime >= '".date('H:i',time())."')
								)
								AND INSTR(c.download_week, '".date('N')."') > 0
							)
						GROUP BY c.coupon_seq
					) coupon
				WHERE except_cnt=0
					AND (all_issue_cnt=0 OR issue_cnt>0)
					AND
					(
						( used_cnt > 0 AND duplication_use = 1 )
						OR  used_cnt = 0
					)
					{$mbwhere}
					AND (issue_type = 'all' OR (issue_type = 'issue' AND all_issue_cnt > 0) OR (issue_type = 'except' AND all_issue_cnt = 0) ) AND ( (issue_priod_type = 'day') OR (issue_priod_type = 'date' AND issue_enddate >='".$today."') )";
		$query .= ($use_type)?" ORDER BY use_type DESC":" ORDER BY coupon_seq ASC";
		$query = $this->db->query($query);
		foreach($query->result_array() as $data) {
			$data['goods_sale'] = 0;
			if( $data['type'] != 'shipping' ){
				if( $data['sale_type'] == 'percent' && $data['percent_goods_sale'] && $price ){
					$data['goods_sale'] = $data['percent_goods_sale'] * $price / 100;
				}else if( $data['sale_type'] == 'won' && $data['won_goods_sale'] && $price ){
					$data['goods_sale'] = $data['won_goods_sale'];
				}
			}

			if( $data['type'] == 'mobile' ) {//기존 모바일쿠폰제외
				$data['type']				= 'download';//상품쿠폰으로 대체
				$data['sale_agent']	= ($data['sale_agent']!= 'm')?'m':'';//사용환경 모바일로 대체
			}

			//사용제한 - 유입경로 체크
			/**if( couponordercheck(&$data, $goodsSeq, $price, 1) != true ) {
				continue;
			}**/

			$result[] = $data;
		}
		return $result;
	}

	/* 주문 시 다운로드 가능한 목록  */
	public function get_able_use_list($member_seq,$goods_seq,$category, $price, $goodprice,$ea=1)
	{
		if(!$member_seq) return;
		//$today = date("Y-m-d",time());
		if( ! $this->config_system['cutting_price'] ) $this->config_system['cutting_price'] = 10;
		$result = $issue_subquery = $except_subquery = "";

		/**if($this->_is_mobile_agent) {
			$coupontype = " and type NOT IN ('shipping')";
		}else{
			$coupontype = " and type NOT IN ('shipping','mobile')";
		}**/
		if($this->_is_mobile_app_agent) {
			$coupontypear = array('shipping','offline_emoney','ordersheet');
			$mobilequery = " ";
		}elseif($this->_is_mobile_agent) {
			$coupontypear = array('shipping','offline_emoney','ordersheet','app_install');
			$mobilequery = " AND sale_agent != 'app' ";
		}else{
			$coupontypear = array('shipping','mobile','offline_emoney','ordersheet','app_install');
			$mobilequery = " AND sale_agent != 'm' AND sale_agent != 'app' ";
		}
		$coupontype = " AND  (type NOT IN ('".implode("','",$coupontypear)."') and  right(type,9) != '_shipping' ) ".$mobilequery;

		// o2o 사용 매장 조건 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_public_able_o2o_sale_store($coupontype);

		if(count($category)>0){
			$issue_subquery = ", (
				SELECT count(*)
				FROM fm_download_issuecategory
				WHERE download_seq=d.download_seq AND `type`= d.issue_type AND category_code IN ('".implode("','",$category)."')) as cate_cnt";

			$query = "select * from
			(
				select d.*,
				(
					SELECT count(*)
					FROM fm_download_issuegoods
					WHERE download_seq=d.download_seq AND goods_seq='".$goods_seq."' AND `type`= d.issue_type
				) as goods_cnt ".$issue_subquery."
				from fm_download d
				where member_seq = ?
				and issue_startdate <= ?
				and issue_enddate >= ?
				and use_status = 'unused'
			) a where
				(
					(issue_type = 'all')
					OR
					(issue_type = 'issue' AND (goods_cnt > 0 OR cate_cnt > 0))
					OR
					(issue_type = 'except' AND (goods_cnt = 0 AND cate_cnt = 0))
				)
				{$coupontype}
			";
		}else{
			$issue_subquery = '';
			$query = "select * from
			(
				select d.*,
				(
					SELECT count(*)
					FROM fm_download_issuegoods
					WHERE download_seq=d.download_seq AND goods_seq='".$goods_seq."' AND `type`= d.issue_type
				) as goods_cnt ".$issue_subquery."
				from fm_download d
				where member_seq = ?
				and issue_startdate <= ?
				and issue_enddate >= ?
				and use_status = 'unused'
			) a where
				(
					(issue_type = 'all')
					OR
					(issue_type = 'issue' AND (goods_cnt > 0))
					OR
					(issue_type = 'except' AND (goods_cnt = 0))
				)
				{$coupontype}
			";
		}
		$query = $this->db->query($query,array($member_seq, $this->today, $this->today));
		$result = array();
		$goods_info = $this->goodsmodel -> get_goods($goods_seq);
		foreach($query->result_array() as $data){

			## 할인부담금 관련 부담자의 상품에만 적용.

			## 쿠폰 사용처가 본사상품일때(생일쿠폰 외 특정 쿠폰 제외)
			if(!in_array($data['type'], $this->except_providerchk_coupon)) {
				if	(empty($data['provider_list']) && $goods_info['provider_seq'] != 1)	continue;

				// 앱 부담금 설정 입점사목록에 본사번호가 들어갈 수도 있어 입점사목록에 본사번호만 있으면 본사기준으로 처리하게 수정 :: 2020-04-08 pjw
				if	(($data['provider_list'] && $data['provider_list'] != '1') && $goods_info['provider_seq'] == 1) continue;
				if	($data['provider_list'] && !strstr($data['provider_list'], '|'.$goods_info['provider_seq'].'|') && $data['provider_list'] != $goods_info['provider_seq'])	{
						continue;
				}
			}

			//사용제한 - 유입경로 체크
			if( couponordercheck($data, $goods_seq, $price, $ea) != true ) {
				continue;
			}
			$data['goods_sale'] = 0;

			if( $data['coupon_same_time'] == 'N' ) {//단독쿠폰이면
				$data['couponsametimetitle'] = '-'.getAlert('os253');
			}else{
				$data['couponsametimetitle'] = '';
			}

			if( $data['sale_payment'] == 'b' ) {//무통장만가능
				$data['couponsametimetitle'] .= ($data['couponsametimetitle'])?','.getAlert('os254'):getAlert('os254');
			}

			if( $data['limit_goods_price'] <= $price ) {//사용제한 원이상인경우만
				if( $data['sale_type'] == 'percent' && $data['percent_goods_sale'] && $goodprice ){

					if( $this->config_system['cutting_price'] != 'none' ){
						$data['goods_sale'] = $data['percent_goods_sale'] * $goodprice / ( $this->config_system['cutting_price'] * 100);
						$data['goods_sale'] = get_cutting_price($data['goods_sale'],'{=basic_currency}');
						$data['goods_sale'] = $data['goods_sale'] * $this->config_system['cutting_price'];
					}else{
						$data['goods_sale'] = $data['percent_goods_sale'] * $goodprice / 100;
						$data['goods_sale'] = get_cutting_price($data['goods_sale'],'{=basic_currency}');
					}

					if($data['max_percent_goods_sale'] < $data['goods_sale']){
						$data['goods_sale'] = $data['max_percent_goods_sale'];
					}
				}else if( $data['sale_type'] != 'percent' && $data['won_goods_sale'] && $goodprice ){
					$data['goods_sale'] = $data['won_goods_sale'];
				}

				$data['goods_sale'] = get_price_point($data['goods_sale']);

				// 쿠폰 할인 금액 체크
				$goods_sale = $data['goods_sale'];
				if($data['duplication_use'] == 1){
					$goods_sale			= $data['goods_sale'] * $ea;
					$goodsprice_total	= $goodprice*$ea;
				}else{
					$goodsprice_total	= $goodprice;
				}
				// debug_var($goodprice."/".$ea."/".$goods_sale);
				//if($goodprice*$ea >= $goods_sale && $goods_sale) $result[] = $data;

				//상품의 총할인금액보다 쿠폰할인금액이 큰경우 상품할인금액으로 대체
				if($goodsprice_total < $goods_sale && $goods_sale)
				{
					$data['goods_sale'] = $goodprice;
				}

				if( $data['type'] == 'mobile' ) {//기존 모바일쿠폰제외
					$data['type']				= 'download';//상품쿠폰으로 대체
					$data['sale_agent']	= ($data['sale_agent']!= 'm')?'m':'';//사용환경 모바일로 대체
				}

				$result[] = $data;
			}
		}
		@usort($result, 'goods_sale_desc');//할인금액내림차순
		return $result;
	}

	/* 배송비가 없는경우: 주문 시 배송비쿠폰 다운로드 가능한 목록  */
	public function get_shipping_use_list($member_seq, $price, $shippingprice,$sellcoupon=null, $shippingcouponprice=null,$provider_seq)
	{

		if($shippingprice == 0) return ;

		//$today = date("Y-m-d",time());
		$result = "";
		$query = "select * from
		(
			select d.*
			from fm_download d
			where member_seq = ?
			and issue_startdate <= ?
			and issue_enddate >= ?
			and use_status = 'unused'
		) a where ( type = 'shipping' or  right(type,9) = '_shipping')
		";

		if(!$this->_is_mobile_app_agent) {
			$query .= " and sale_agent != 'app' ";
		}
		if(!$this->_is_mobile_agent) {
			$query .= " and sale_agent != 'm' ";
		}

		// o2o 사용 매장 조건 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_public_able_o2o_sale_store($query);

		$query = $this->db->query($query,array($member_seq, $this->today, $this->today));
		foreach($query->result_array() as $data){

			## 배송비쿠폰>할인부담금 관련 부담자의 배송그룹에만 적용.
			if ( $data['type'] == 'shipping' ) {
				if	($provider_seq == 1 && $data['provider_list'])	{
					continue;
				}
				if	($data['provider_list'] && !strstr($data['provider_list'], '|'.$provider_seq.'|'))	{
					continue;
				}
			}

			//사용제한 - 유입경로 체크(배송비 쿠폰은 상품이 아닌 사용가능 입점사로 체크)
			if( couponordercheck($data, '', $price, 1, $provider_seq) != true ) {
				continue;
			}

			if($sellcoupon == $data['download_seq'] ){
				$data['shipping_sale'] = $shippingcouponprice;
				$result[] = $data;
			}else{
				$data['shipping_sale'] = 0;
				if( $data['limit_goods_price'] <= $price ) {//사용제한 원이상만
					if( $data['shipping_type'] == 'free' ){
						if($data['max_percent_shipping_sale'] > 0 && $data['max_percent_shipping_sale'] < $shippingprice ){
							$data['shipping_sale'] =  $data['max_percent_shipping_sale'];
						}else{
							$data['shipping_sale'] =  $shippingprice;
						}
					}else if( $data['shipping_type'] == 'won'){
						$data['shipping_sale'] = $data['won_shipping_sale'];
					}

					//할인금액이 판매금액보다 큰경우 구매금액
					if($shippingprice < $data['shipping_sale']){
						$data['shipping_sale'] = $shippingprice;
					}
					$data['shipping_sale'] = get_price_point($data['shipping_sale']);

					if( $data['coupon_same_time'] == 'N' ) {//단독쿠폰이면
						$data['couponsametimetitle'] = '-'.getAlert('os253');
					}else{
						$data['couponsametimetitle'] = '';
					}

					if( $data['sale_payment'] == 'b' ) {//무통장만가능
						$data['couponsametimetitle'] .= ($data['couponsametimetitle'])?','.getAlert('os254'):getAlert('os254');
					}


					if( $data['type'] == 'mobile' ) {//기존 모바일쿠폰제외
						$data['type']				= 'download';//상품쿠폰으로 대체
						$data['sale_agent']	= ($data['sale_agent']!= 'm')?'m':'';//사용환경 모바일로 대체
					}

					$result[] = $data;
				}
			}
		}

		@usort($result, 'shipping_sale_desc');//할인금액내림차순
		return $result;
	}

	/* 쿠폰을 사용한 주문의 쿠폰 발급 상태 변경 */
	function set_download_use_status($download_seq,$status,$manager_name='',$manager_code='')
	{
		$use_date = date('Y-m-d H:i:s',time());
		$this->db->where('download_seq', $download_seq);
		if($manager_name || $manager_code){
			$this->db->update('fm_download', array('use_status' => $status,'use_date' => $use_date,'confirm_user' => $manager_name,'confirm_user_serial' => $manager_code));
		}else{
			$this->db->update('fm_download', array('use_status' => $status,'use_date' => $use_date));
		}
	}

	//관리자 > 쿠폰 직접발급
	public function _admin_downlod($couponSeq, $memberSeq)
	{
		$now_timestamp = time();
		//$today = date('Y-m-d',$now_timestamp);
		$now = date('Y-m-d H:i:s',$now_timestamp);

		// 쿠폰 정보 확인
		$coupons = $this->get_admin_download($memberSeq, $couponSeq);
		if($coupons) return false;//이미 다운받은 쿠폰이 있습니다.

		$couponData = $this->get_coupon($couponSeq);
		if(!$couponData) return false;//쿠폰이 올바르지 않습니다.

		$paramInsert['member_seq']				= $memberSeq;
		$paramInsert['coupon_seq']				= $couponSeq;
		$paramInsert['type']							= $couponData['type'];
		$paramInsert['use_type']					= $couponData['use_type'];
		$paramInsert['coupon_name']				= $couponData['coupon_name'];
		$paramInsert['coupon_desc']				= $couponData['coupon_desc'];
		$paramInsert['coupon_same_time']	= $couponData['coupon_same_time'];
		$paramInsert['sale_type']					= $couponData['sale_type'];
		$paramInsert['issue_type']								= if_empty($couponData, 'issue_type', 'all');//$couponData['issue_type'];
		$paramInsert['shipping_type']				= $couponData['shipping_type'];
		$paramInsert['max_percent_shipping_sale']				= $couponData['max_percent_shipping_sale'];
		$paramInsert['won_shipping_sale']				= $couponData['won_shipping_sale'];
		$paramInsert['percent_goods_sale']			= $couponData['percent_goods_sale'];
		$paramInsert['max_percent_goods_sale']	= $couponData['max_percent_goods_sale'];
		$paramInsert['won_goods_sale']				= $couponData['won_goods_sale'];
		$paramInsert['duplication_use']					= $couponData['duplication_use'];
		$paramInsert['limit_goods_price']				= $couponData['limit_goods_price'];

		$paramInsert['sale_agent']								= $couponData['sale_agent'];
		$paramInsert['sale_payment']							= $couponData['sale_payment'];
		$paramInsert['sale_referer']							= $couponData['sale_referer'];
		$paramInsert['sale_referer_type']					= $couponData['sale_referer_type'];
		$paramInsert['sale_referer_item']					= $couponData['sale_referer_item'];
		//$paramInsert['memberlogin_terms']				= $couponData['memberlogin_terms'];
		//$paramInsert['order_terms']							= $couponData['order_terms'];

		$paramInsert['salescost_admin']						= if_empty($couponData, 'salescost_admin', '100');//$couponData['salescost_admin'];
		$paramInsert['salescost_provider']					= if_empty($couponData, 'salescost_provider', '0');
		$paramInsert['provider_list']							= $couponData['provider_list'];

		$paramInsert['use_status']							= 'unused';
		$paramInsert['regist_date']							= $now;
		if($couponData['issue_priod_type'] == 'day'){
			$paramInsert['issue_startdate']	= date("Y-m-d",$now_timestamp);
			$to_timestamp = $now_timestamp + ( $couponData['after_issue_day'] * 86400 );
			$paramInsert['issue_enddate']	= date("Y-m-d",$to_timestamp);
		}else{
			$paramInsert['issue_startdate']	= $couponData['issue_startdate'];
			$paramInsert['issue_enddate']	= $couponData['issue_enddate'];
		}
		$this->db->insert('fm_download', $paramInsert);
		$downloadSeq = $this->db->insert_id();
		unset($paramInsert);

		$couponGoods 	= $this->get_coupon_issuegoods($couponSeq);
		if($couponGoods) foreach($couponGoods as $paramInsert){
			unset($paramInsert['issuegoods_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuegoods', $paramInsert);
			unset($paramInsert);
		}

		$couponCategory = $this->get_coupon_issuecategory($couponSeq);
		if($couponCategory) foreach($couponCategory as $paramInsert){
			unset($paramInsert['issuecategory_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuecategory', $paramInsert);
			unset($paramInsert);
		}
		return true;
	}

	//사용자 > 상품쿠폰 다운시 사용안하고--> _members_downlod 공통이용
	public function _goods_downlod_( $couponSeq, $memberSeq)
	{
		if(empty($couponSeq))return false;
		if(empty($memberSeq))return false;

		$now_timestamp = time();
		//$today = date('Y-m-d',$now_timestamp);
		$now = date('Y-m-d H:i:s',$now_timestamp);

		$couponData 	= $this->get_coupon($couponSeq);
		if(!$couponData) return false;//쿠폰이 올바르지 않습니다.

		$paramInsert['member_seq']							= $memberSeq;
		$paramInsert['coupon_seq']							= $couponSeq;
		$paramInsert['type']										= $couponData['type'];
		$paramInsert['use_type']								= $couponData['use_type'];
		$paramInsert['offline_type']							= $couponData['offline_type'];
		$paramInsert['offline_emoney']						= $couponData['offline_emoney'];
		$paramInsert['coupon_point']							= $couponData['coupon_point'];

		$paramInsert['coupon_name']							= $couponData['coupon_name'];
		$paramInsert['coupon_desc']							= $couponData['coupon_desc'];
		$paramInsert['coupon_same_time']				= $couponData['coupon_same_time'];
		$paramInsert['sale_type']								= $couponData['sale_type'];
		$paramInsert['issue_type']								= if_empty($couponData, 'issue_type', 'all');//$couponData['issue_type'];
		$paramInsert['shipping_type']							= $couponData['shipping_type'];
		$paramInsert['max_percent_shipping_sale']	= $couponData['max_percent_shipping_sale'];
		$paramInsert['won_shipping_sale']				= $couponData['won_shipping_sale'];
		$paramInsert['percent_goods_sale']				= $couponData['percent_goods_sale'];
		$paramInsert['max_percent_goods_sale']		= $couponData['max_percent_goods_sale'];
		$paramInsert['won_goods_sale']					= $couponData['won_goods_sale'];
		$paramInsert['duplication_use']						= $couponData['duplication_use'];
		$paramInsert['limit_goods_price']					= $couponData['limit_goods_price'];

		$paramInsert['sale_agent']								= $couponData['sale_agent'];
		$paramInsert['sale_payment']							= $couponData['sale_payment'];
		$paramInsert['sale_referer']							= $couponData['sale_referer'];
		$paramInsert['sale_referer_type']					= $couponData['sale_referer_type'];
		$paramInsert['sale_referer_item']					= $couponData['sale_referer_item'];
		//$paramInsert['memberlogin_terms']				= $couponData['memberlogin_terms'];
		//$paramInsert['order_terms']							= $couponData['order_terms'];

		$paramInsert['salescost_admin']						= $couponData['salescost_admin'];
		$paramInsert['salescost_provider']					= if_empty($couponData, 'salescost_provider', '0');
		$paramInsert['provider_list']							= $couponData['provider_list'];

		$paramInsert['use_status']								= 'unused';
		$paramInsert['regist_date']								= $now;

		if($couponData['issue_priod_type'] == 'day'){
			$paramInsert['issue_startdate']	= date("Y-m-d",$now_timestamp);
			$to_timestamp = $now_timestamp + ( $couponData['after_issue_day'] * 86400 );
			$paramInsert['issue_enddate']	= date("Y-m-d",$to_timestamp);
		}else{
			$paramInsert['issue_startdate']			= $couponData['issue_startdate'];
			$paramInsert['issue_enddate']			= $couponData['issue_enddate'];
		}

		$this->db->insert('fm_download', $paramInsert);
		$downloadSeq = $this->db->insert_id();
		unset($paramInsert);

		$couponGoods 	= $this->couponmodel->get_coupon_issuegoods($couponSeq);
		if($couponGoods) foreach($couponGoods as $paramInsert){
			unset($paramInsert['issuegoods_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuegoods', $paramInsert);
			unset($paramInsert);
		}

		$couponCategory = $this->couponmodel->get_coupon_issuecategory($couponSeq);
		if($couponCategory) foreach($couponCategory as $paramInsert){
			unset($paramInsert['issuecategory_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuecategory', $paramInsert);
			unset($paramInsert);
		}
		return true;
	}

	//사용자 > 쿠폰다운시 자동쿠폰/상품쿠폰/직접발급쿠폰 제외
	public function _members_downlod( $couponSeq, $memberSeq)
	{
		if(empty($memberSeq))return false;
		if(empty($couponSeq))return false;

		$now_timestamp = time();
		//$today = date('Y-m-d',$now_timestamp);
		$now = date('Y-m-d H:i:s',$now_timestamp);
		$couponData 	= $this->get_coupon($couponSeq);
		if(!$couponData) return false;//쿠폰이 올바르지 않습니다.


		//배송비만 중복다운체크
		//$downcoupons = $this->get_admin_download($memberSeq, $couponSeq);
		if( $couponData['type'] == 'shipping' && $couponData['duplication_use'] != 1 && $downcoupons ) {
			return false;//이미 다운받은 쿠폰이 있습니다.
		}

		//앱 설치 쿠폰 중복 다운 체크
		$app_install_downcoupons = $this->get_admin_download($memberSeq, $couponSeq);
		if( $couponData['type'] == 'app_install' && $app_install_downcoupons ) {
			return false;//이미 다운받은 쿠폰이 있습니다.
		}

		$paramInsert['member_seq']					= $memberSeq;
		$paramInsert['coupon_seq']					= $couponSeq;
		$paramInsert['type']						= $couponData['type'];
		$paramInsert['use_type']					= $couponData['use_type'];
		$paramInsert['offline_type']				= $couponData['offline_type'];
		$paramInsert['offline_emoney']				= $couponData['offline_emoney'];
		$paramInsert['coupon_point']				= $couponData['coupon_point'];

		$paramInsert['coupon_name']					= $couponData['coupon_name'];
		$paramInsert['coupon_desc']					= $couponData['coupon_desc'];
		$paramInsert['coupon_same_time']			= $couponData['coupon_same_time'];
		$paramInsert['sale_type']					= $couponData['sale_type'];
		$paramInsert['issue_type']								= if_empty($couponData, 'issue_type', 'all');//$couponData['issue_type'];
		$paramInsert['shipping_type']				= $couponData['shipping_type'];
		$paramInsert['max_percent_shipping_sale']	= $couponData['max_percent_shipping_sale'];
		$paramInsert['won_shipping_sale']			= $couponData['won_shipping_sale'];
		$paramInsert['percent_goods_sale']			= $couponData['percent_goods_sale'];
		$paramInsert['max_percent_goods_sale']		= $couponData['max_percent_goods_sale'];
		$paramInsert['won_goods_sale']				= $couponData['won_goods_sale'];
		$paramInsert['duplication_use']				= $couponData['duplication_use'];
		$paramInsert['limit_goods_price']			= $couponData['limit_goods_price'];

		$paramInsert['sale_agent']								= $couponData['sale_agent'];
		$paramInsert['sale_payment']							= $couponData['sale_payment'];
		$paramInsert['sale_referer']							= $couponData['sale_referer'];
		$paramInsert['sale_referer_type']					= $couponData['sale_referer_type'];
		$paramInsert['sale_referer_item']					= $couponData['sale_referer_item'];
		//$paramInsert['memberlogin_terms']				= $couponData['memberlogin_terms'];
		//$paramInsert['order_terms']							= $couponData['order_terms'];

		$paramInsert['sale_store']						= $couponData['sale_store'];
		$paramInsert['sale_store_item']					= $couponData['sale_store_item'];

		$paramInsert['use_status']					= 'unused';
		$paramInsert['regist_date']					= $now;

		//생일년도/기념일년 체크
		if( $couponData['type'] == 'birthday' || $couponData['type'] == 'anniversary') {
				$down_year = date('Y');
			$paramInsert['down_year']							= $down_year;
		}elseif( $couponData['type'] == 'memberGroup' || $couponData['type'] == 'memberGroup_shipping') {
			$paramInsert['down_year']							= $this->userInfo['group_seq'];//등급쿠폰이면 해당등급
		}

		if($couponData['issue_priod_type'] == 'months'){
			$paramInsert['issue_startdate']	= date("Y-m-d",$now_timestamp);
			$paramInsert['issue_enddate']	= date("Y-m-t");//당월의 말일
		}elseif($couponData['issue_priod_type'] == 'day'){
			$paramInsert['issue_startdate']	= date("Y-m-d",$now_timestamp);
			$to_timestamp = $now_timestamp + ( $couponData['after_issue_day'] * 86400 );
			$paramInsert['issue_enddate']	= date("Y-m-d",$to_timestamp);
		}else{
			$paramInsert['issue_startdate']	= $couponData['issue_startdate'];
			$paramInsert['issue_enddate']	= $couponData['issue_enddate'];
		}

		$paramInsert['salescost_admin']						= $couponData['salescost_admin'];
		$paramInsert['salescost_provider']	= if_empty($couponData, 'salescost_provider', '0');
		$paramInsert['provider_list']		= $couponData['provider_list'];

		if($couponData['type'] == 'point'){//point 전환쿠폰
			$this->load->model('membermodel');
			$this->mdata = $this->membermodel->get_member_data($memberSeq);//회원정보
			if( $this->mdata['point']<1 || $this->mdata['point'] < $couponData['coupon_point'] ) {//포인트가 작거나 없는 경우
				if( $this->mdata['point']<1 ) {//포인트가 작거나 없는 경우
					return false;// 보유포인트가 없습니다
				}else{
					return false;//전환포인트 금액이 보유포인트보다 작습니다
				}
			}
		}
		$this->db->insert('fm_download', $paramInsert);
		$downloadSeq = $this->db->insert_id();
		unset($paramInsert);

		if($couponData['type'] == 'point'){//point 전환쿠폰
			$this->load->model('membermodel');
			$params = array(
				'gb'		=> 'minus',
				'type'		=> 'coupon',
				'point'         => $couponData['coupon_point'],
				'memo'		=> "[차감]포인트전환 쿠폰 [".$couponData['coupon_name']."] 다운에 의한 포인트 차감",
				'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp262",$couponData['coupon_name']),   // [차감]포인트전환 쿠폰 [%s] 다운에 의한 포인트 차감
			);//:".$downloadSeq."
			$this->membermodel->point_insert($params, $memberSeq);
		}

		$couponGoods 	= $this->get_coupon_issuegoods($couponSeq);
		if($couponGoods) foreach($couponGoods as $paramInsert){
			unset($paramInsert['issuegoods_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuegoods', $paramInsert);
			unset($paramInsert);
		}

		$couponCategory = $this->get_coupon_issuecategory($couponSeq);
		if($couponCategory) foreach($couponCategory as $paramInsert){
			unset($paramInsert['issuecategory_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuecategory', $paramInsert);
			unset($paramInsert);
		}

		return true;
	}

	//사용자 > 오프라인쿠폰 인증시
	public function _offlinecoupon_members_downlod( $couponSeq, $memberSeq, $offline_serialnumber)
	{
		if(empty($memberSeq))return false;
		if(empty($couponSeq))return false;

		$now_timestamp = time();
		//$today = date('Y-m-d',$now_timestamp);
		$now = date('Y-m-d H:i:s',$now_timestamp);

		//중복등록가능
		$couponData 	= $this->get_coupon($couponSeq);
		if(!$couponData) return false;//쿠폰이 올바르지 않습니다.

		$paramInsert['member_seq']				= $memberSeq;
		$paramInsert['coupon_seq']				= $couponSeq;
		$paramInsert['type']							= $couponData['type'];
		$paramInsert['use_type']					= $couponData['use_type'];
		$paramInsert['offline_type']				= $couponData['offline_type'];
		$paramInsert['offline_emoney']			= $couponData['offline_emoney'];
		$paramInsert['coupon_point']				= $couponData['coupon_point'];
		$paramInsert['offline_input_serialnumber'] = $offline_serialnumber;
		$paramInsert['coupon_name']				= $couponData['coupon_name'];
		$paramInsert['coupon_desc']				= $couponData['coupon_desc'];
		$paramInsert['coupon_same_time']	= $couponData['coupon_same_time'];
		$paramInsert['sale_type']					= $couponData['sale_type'];
		$paramInsert['issue_type']								= if_empty($couponData, 'issue_type', 'all');//$couponData['issue_type'];
		$paramInsert['shipping_type']				= $couponData['shipping_type'];
		$paramInsert['max_percent_shipping_sale']				= $couponData['max_percent_shipping_sale'];
		$paramInsert['won_shipping_sale']				= $couponData['won_shipping_sale'];
		$paramInsert['percent_goods_sale']			= $couponData['percent_goods_sale'];
		$paramInsert['max_percent_goods_sale']	= $couponData['max_percent_goods_sale'];
		$paramInsert['won_goods_sale']				= $couponData['won_goods_sale'];
		$paramInsert['duplication_use']					= $couponData['duplication_use'];
		$paramInsert['limit_goods_price']				= $couponData['limit_goods_price'];

		$paramInsert['sale_agent']								= $couponData['sale_agent'];
		$paramInsert['sale_payment']							= $couponData['sale_payment'];
		$paramInsert['sale_referer']							= $couponData['sale_referer'];
		$paramInsert['sale_referer_type']					= $couponData['sale_referer_type'];
		$paramInsert['sale_referer_item']					= $couponData['sale_referer_item'];
		//$paramInsert['memberlogin_terms']				= $couponData['memberlogin_terms'];
		//$paramInsert['order_terms']							= $couponData['order_terms'];

		$paramInsert['use_status']							= 'unused';
		$paramInsert['regist_date']							= $now;

		if($couponData['issue_priod_type'] == 'day'){
			$paramInsert['issue_startdate']	= date("Y-m-d",$now_timestamp);
			$to_timestamp = $now_timestamp + ( $couponData['after_issue_day'] * 86400 );
			$paramInsert['issue_enddate']	= date("Y-m-d",$to_timestamp);
		}else{
			$paramInsert['issue_startdate']	= $couponData['issue_startdate'];
			$paramInsert['issue_enddate']	= $couponData['issue_enddate'];
		}
		$paramInsert['salescost_admin']						= $couponData['salescost_admin'];
		$paramInsert['salescost_provider']	= if_empty($couponData, 'salescost_provider', '0');
		$paramInsert['provider_list']		= $couponData['provider_list'];

		$this->db->insert('fm_download', $paramInsert);
		$downloadSeq = $this->db->insert_id();
		unset($paramInsert);

		$couponGoods 	= $this->get_coupon_issuegoods($couponSeq);
		if($couponGoods) foreach($couponGoods as $paramInsert){
			unset($paramInsert['issuegoods_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuegoods', $paramInsert);
			unset($paramInsert);
		}

		$couponCategory = $this->get_coupon_issuecategory($couponSeq);
		if($couponCategory) foreach($couponCategory as $paramInsert){
			unset($paramInsert['issuecategory_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuecategory', $paramInsert);
			unset($paramInsert);
		}

		return true;
	}

	//사용자 > 오프라인 마일리지 쿠폰 발급완료처리
	public function _offlinecoupon_members_emoney_downlod( $couponSeq, $memberSeq, $offline_serialnumber)
	{
		if(empty($memberSeq))return false;
		if(empty($couponSeq))return false;

		$now_timestamp = time();
		//$today = date('Y-m-d',$now_timestamp);
		$now = date('Y-m-d H:i:s',$now_timestamp);

		$couponData 	= $this->get_coupon($couponSeq);
		if(!$couponData) return false;//쿠폰이 올바르지 않습니다.

		$paramInsert['member_seq']				= $memberSeq;
		$paramInsert['coupon_seq']				= $couponSeq;
		$paramInsert['type']							= $couponData['type'];
		$paramInsert['use_type']					= $couponData['use_type'];
		$paramInsert['offline_type']				= $couponData['offline_type'];
		$paramInsert['offline_emoney']			= $couponData['offline_emoney'];
		$paramInsert['coupon_point']				= $couponData['coupon_point'];
		$paramInsert['offline_input_serialnumber'] = $offline_serialnumber;
		$paramInsert['coupon_name']				= $couponData['coupon_name'];
		$paramInsert['coupon_desc']				= $couponData['coupon_desc'];
		$paramInsert['coupon_same_time']	= $couponData['coupon_same_time'];
		$paramInsert['sale_type']					= $couponData['sale_type'];
		$paramInsert['issue_type']								= if_empty($couponData, 'issue_type', 'all');//$couponData['issue_type'];
		$paramInsert['shipping_type']				= $couponData['shipping_type'];
		$paramInsert['max_percent_shipping_sale']	= $couponData['max_percent_shipping_sale'];
		$paramInsert['won_shipping_sale']				= $couponData['won_shipping_sale'];
		$paramInsert['percent_goods_sale']			= $couponData['percent_goods_sale'];
		$paramInsert['max_percent_goods_sale']	= $couponData['max_percent_goods_sale'];
		$paramInsert['won_goods_sale']				= $couponData['won_goods_sale'];
		$paramInsert['duplication_use']					= $couponData['duplication_use'];
		$paramInsert['limit_goods_price']				= $couponData['limit_goods_price'];

		$paramInsert['sale_agent']								= $couponData['sale_agent'];
		$paramInsert['sale_payment']							= $couponData['sale_payment'];
		$paramInsert['sale_referer']							= $couponData['sale_referer'];
		$paramInsert['sale_referer_type']					= $couponData['sale_referer_type'];
		$paramInsert['sale_referer_item']					= $couponData['sale_referer_item'];
		//$paramInsert['memberlogin_terms']				= $couponData['memberlogin_terms'];
		//$paramInsert['order_terms']							= $couponData['order_terms'];

		$paramInsert['use_status']								= 'used';//사용함처리
		$paramInsert['use_date']							= $now;
		$paramInsert['regist_date']							= $now;

		if($couponData['issue_priod_type'] == 'day'){
			$paramInsert['issue_startdate']	= date("Y-m-d",$now_timestamp);
			$to_timestamp = $now_timestamp + ( $couponData['after_issue_day'] * 86400 );
			$paramInsert['issue_enddate']	= date("Y-m-d",$to_timestamp);
		}else{
			$paramInsert['issue_startdate']	= $couponData['issue_startdate'];
			$paramInsert['issue_enddate']	= $couponData['issue_enddate'];
		}

		$paramInsert['salescost_admin']						= $couponData['salescost_admin'];
		$paramInsert['salescost_provider']	= if_empty($couponData, 'salescost_provider', '0');
		$paramInsert['provider_list']		= $couponData['provider_list'];

		$this->db->insert('fm_download', $paramInsert);

		$downloadSeq = $this->db->insert_id();
		unset($paramInsert);

		$couponGoods 	= $this->get_coupon_issuegoods($couponSeq);
		if($couponGoods) foreach($couponGoods as $paramInsert){
			unset($paramInsert['issuegoods_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuegoods', $paramInsert);
			unset($paramInsert);
		}

		$couponCategory = $this->get_coupon_issuecategory($couponSeq);
		if($couponCategory) foreach($couponCategory as $paramInsert){
			unset($paramInsert['issuecategory_seq'],$paramInsert['coupon_seq']);
			$paramInsert['download_seq'] = $downloadSeq;
			$this->db->insert('fm_download_issuecategory', $paramInsert);
			unset($paramInsert);
		}

		return true;
	}

	/* 환불에 의한 쿠폰 복원 */
	public function restore_used_coupon($tmp_download_seq){
		// download_seq 변수로 배열이 넘어오는 경우가 있어 수정.
		$tmp_array = array();
		if(!is_array($tmp_download_seq)){
			$tmp_array[] = $tmp_download_seq;
		}else{
			foreach($tmp_download_seq as $download_seq){
				if(!is_array($download_seq)){
					$tmp_array[] = $download_seq;
				}else{
					foreach($download_seq as $s_download_seq){
						$tmp_array[] = $s_download_seq;
					}
				}
			}
		}
		foreach($tmp_array as $download_seq){
			$sql = "select * from fm_download where download_seq=?";
			$query = $this->db->query($sql,array($download_seq));
			list($download) = $query->result_array($query);

			$sqlck = "select * from fm_download where refund_download_seq=?";
			$queryck = $this->db->query($sqlck,array($download_seq));
			list($downloadck) = $queryck->result_array($queryck);

			if($download && !$downloadck ) {

				$remain_issue_day = (strtotime($download['issue_enddate'])-strtotime(substr($download['use_date'],0,10))) / 86400;
				$remain_issue_day = is_null($remain_issue_day) ? 1 : (int)$remain_issue_day;

				$download['regist_date']		= date('Y-m-d H:i:s');
				$download['issue_startdate']	= date('Y-m-d');
				$download['issue_enddate']				= date('Y-m-d',strtotime("+".$remain_issue_day." day"));
				$download['coupon_name']		= "[복원]".$download['coupon_name'];
				$download['refund_download_seq']= $download_seq;

				unset($download['download_seq']);
				unset($download['use_status']);
				unset($download['use_date']);
				//unset($download['order_seq']);

				$this->db->insert('fm_download', $download);
				$item_seq = $this->db->insert_id();

				$success = $item_seq;

				$couponGoods 	= $this->get_coupon_download_issuegoods($download_seq);
				if($couponGoods) foreach($couponGoods as $paramInsert){
					unset($paramInsert['issuegoods_seq'],$paramInsert['coupon_seq']);
					$paramInsert['download_seq'] = $success;
					$this->db->insert('fm_download_issuegoods', $paramInsert);
					unset($paramInsert);
				}

				$couponCategory = $this->get_coupon_download_issuecategory($download_seq);
				if($couponCategory) foreach($couponCategory as $paramInsert){
					unset($paramInsert['issuecategory_seq'],$paramInsert['coupon_seq']);
					$paramInsert['download_seq'] = $success;
					$this->db->insert('fm_download_issuecategory', $paramInsert);
					unset($paramInsert);
				}

			}else{
				$success = false;
			}
		}

		return $success;
	}

	public function get_coupon_download_issuecategory($download_seq,$issue_type='')
	{
		$result = false;
		$this->db->where('download_seq', $download_seq);
		if($issue_type){
			$this->db->where('type', $issue_type);
		}
		$query = $this->db->get($this->coupon_download_issuecategory);
		foreach( $query->result_array() as $row ){
			$result[] = $row;
		}
		return $result;
	}

	public function get_coupon_download_issuegoods($download_seq,$issue_type='')
	{
		$result = false;
		$this->db->where('download_seq', $download_seq);
		if($issue_type){
			$this->db->where('type', $issue_type);
		}
		$query = $this->db->get($this->coupon_download_issuegoods);
		foreach( $query->result_array() as $row ){
			$result[] = $row;
		}
		return $result;
	}

	/* 환불에 의한 복원쿠폰가져오기 */
	public function restore_used_coupon_refund($refund_download_seq){
		$sql = "select * from fm_download where refund_download_seq=?";
		$query = $this->db->query($sql,array($refund_download_seq));
		list($download) = $query->result_array($query);
		if($download){
			$success = $download['download_seq'];
		}else{
			$success = false;
		}
		return $success;
	}

	/* 오프라인쿠폰 */
	public function get_offlinecoupon_total_count($sc)
	{
		$query = $this->db->from($this->offlinecoupon)
		->where($sc);
		$query = $query->get();

		return $query->num_rows();
	}

	/*
	 * 오프라인 인증번호 보기
	* @param
	*/
	public function offlinecoupon_list($sc, $all=false)
	{
		$this->db->select('SQL_CALC_FOUND_ROWS *, c.*, off.*', FALSE);
		$this->db->from($this->offlinecoupon." off");
		$this->db->where('1', 1);
		$this->db->join($this->table_coupon.' c', 'off.coupon_seq=c.coupon_seq','left');

		if(!empty($sc['no'])){
			$this->db->where('off.coupon_seq', $sc['no']);
		}

		if(!empty($sc['search_text'])) {
			// both -> %?%
			// after -> ?%
			// before -> %?
			$this->db->like('off.offline_serialnumber', $sc['search_text'], 'both'); 
		}

		// 등록일 검색(시작)
		if($sc['sdate'] AND !$sc['edate']) {
			$start_date = $sc['sdate'].' 00:00:00';
			$this->db->where('off.regist_date >=', $start_date);
		}

		// 등록일 검색(끝)
		if($sc['edate'] AND !$sc['sdate']) {
			$start_date = $sc['edate'].' 23:59:59';
			$this->db->where('off.regist_date <=', $start_date);
		}
		
		// 등록일 검색
		if($sc['sdate'] AND $sc['edate']) {
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$this->db->where('off.regist_date >=', $start_date);
			$this->db->where('off.regist_date <=', $end_date);
		}

		if(!$all){
			$this->db->limit($sc['perpage'], $sc['page']);
		}				

		$this->db->order_by('off.offline_seq', 'desc');
		$query = $this->db->get();
		$data['result'] = $query->result_array();
		
		if(!$all){
			$sql = "SELECT FOUND_ROWS() as COUNT";
			$query_count = $this->db->query($sql);
			$res_count= $query_count->result_array();
			$data['count'] = $res_count[0]['COUNT'];
		}

		return $data;
	}

	// 총건수
	public function get_offlinecoupon_item_total_count($no)
	{
		$sql = 'select offline_seq from '.$this->offlinecoupon.' where 1';
		if( !empty($no) )
		{
			$sql .= ' and coupon_seq = '.$no.' ';
		}

		$query = $this->db->query($sql);
		return $query->num_rows();
	}

	//개별오프라인쿠폰 정보가가져오기
	public function get_offlinecoupon($no)
	{
		$this->db->limit(1,0);
		$this->db->where('offline_seq', $no);
		$query = $this->db->get($this->offlinecoupon);
		$result = $query->result_array();
		return $result[0];
	}

	//개별오프라인 가져오기
	public function get_offlinecoupon_serialnumber($offline_serialnumber)
	{
		$this->db->limit(1,0);
		$this->db->where('offline_serialnumber', $offline_serialnumber);
		$query = $this->db->get($this->offlinecoupon);
		$result = $query->result_array();
		return $result[0];
	}

	/* 오프라인쿠폰 > 자동등록 : 사용건수 - 변경 */
	function set_offlinecoupon_use_count($offline_serialnumber)
	{
		$upsql = "update ".$this->offlinecoupon." set use_count = use_count-1 where offline_serialnumber = '{$offline_serialnumber}'";
		$this->db->query($upsql);
	}

	// 오프라인쿠폰 > 수동등록
	public function get_offlinecoupon_input_total_count($sc)
	{
		$query = $this->db->from($this->offlinecoupon_input)
		->where($sc);
		$query = $query->get();

		return $query->num_rows();
	}

	/*
	 * 오프라인 인증번호 보기  > 수동등록
	* @param
	*/
	public function offlinecoupon_input_list($sc, $all=false)
	{
		$sql = "select SQL_CALC_FOUND_ROWS *, off.*
					from ".$this->offlinecoupon_input." off
					left join ".$this->table_coupon." c on c.coupon_seq = off.coupon_seq where 1 ";

		if( !empty($sc['no']) )
		{
			$sql .= ' and off.coupon_seq = '.$sc['no'].' ';
		}
		if(!empty($sc['search_text']))$sql.= " and off.offline_serialnumber like '%".$sc['search_text']."%' ";

		// 등록일 검색(시작)
		if($sc['sdate'] AND !$sc['edate']) {
			$start_date = $sc['sdate'].' 00:00:00';
			$sql.=" and off.regist_date >= '{$start_date}' ";
		}

		// 등록일 검색(끝)
		if($sc['edate'] AND !$sc['sdate']) {
			$start_date = $sc['edate'].' 23:59:59';
			$sql.=" and off.regist_date <= '{$start_date}' ";
		}

		// 등록일 검색
		if($sc['sdate'] AND $sc['edate']) {
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$sql.=" and d.regist_date BETWEEN '{$start_date}' and '{$end_date}' ";
		}

		$sql.=" order by off.offline_seq desc ";
		if(!$all)$sql .=" limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($sql);
		$data['result'] = $query->result_array();

		if(!$all){
			//총건수
			$sql = "SELECT FOUND_ROWS() as COUNT";
			$query_count = $this->db->query($sql);
			$res_count= $query_count->result_array();
			$data['count'] = $res_count[0]['COUNT'];
		}

		return $data;
	}

	// 오프라인쿠폰 > 수동등록 총건수
	public function get_offlinecoupon_input_item_total_count($no)
	{
		$sql = 'select offline_seq from '.$this->offlinecoupon_input.' where 1';
		if( !empty($no) )
		{
			$sql .= ' and coupon_seq = '.$no.' ';
		}
		$query = $this->db->query($sql);
		return $query->num_rows();
	}

	//개별오프라인 수동쿠폰 가져오기
	public function get_offlinecoupon_input($no)
	{
		$this->db->limit(1,0);
		$this->db->where('offline_seq', $no);
		$query = $this->db->get($this->offlinecoupon_input);
		$result = $query->result_array();
		return $result[0];
	}


	//개별오프라인 수동쿠폰 가져오기
	public function get_offlinecoupon_input_serialnumber($offline_serialnumber)
	{
		$this->db->limit(1,0);
		$this->db->where('offline_serialnumber', $offline_serialnumber);
		$query = $this->db->get($this->offlinecoupon_input);
		$result = $query->result_array();
		return $result[0];
	}

	/* 오프라인쿠폰 > 수동쿠폰 : 사용건수 - 변경 */
	function set_offlinecoupon_input_use_count($offline_serialnumber)
	{
		$upsql = "update ".$this->offlinecoupon_input." set use_count = use_count-1 where offline_serialnumber = '{$offline_serialnumber}'";
		$this->db->query($upsql);
	}

	//보유한 쿠폰 리스트
	public function my_download_list($sc, $all=false)
	{
		$sql = "select
					SQL_CALC_FOUND_ROWS *,
					m.userid,
					m.user_name,
					d.*,
					c.benefit
				from ".$this->coupon_download." d
					left join ".$this->members." m on m.member_seq = d.member_seq
					inner join fm_coupon c on c.coupon_seq = d.coupon_seq
				where 1 ";


		// 장바구니 상품용 쿠폰만 추출
		if	($sc['only_cart_goods'] == 'y'){
			if($this->_is_mobile_app_agent) {
				$coupontypear = array('shipping','offline_emoney');
				$mobilequery = " ";
			}elseif($this->_is_mobile_agent) {
				$coupontypear	= array('shipping','offline_emoney','app_install');
				$mobilequery	= " AND d.sale_agent != 'app' ";
			}else{
				$coupontypear	= array('shipping','mobile','offline_emoney','app_install');
				$mobilequery	= " AND d.sale_agent != 'm' AND d.sale_agent != 'app' ";
			}
			$sql		.= " AND  (d.type NOT IN ('".implode("','",$coupontypear)."') and  right(d.type,9) != '_shipping' ) ".$mobilequery;
		}

		// o2o 사용 매장 조건 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_public_able_o2o_sale_store($sql, "d");

		if( !empty($sc['no']) )
		{
			$sql .= ' and d.coupon_seq = '.$sc['no'].' ';
		}

		if(isset($sc['member_seq'])) $sql.= ' and m.member_seq ='.$sc['member_seq'];//회원

		if( !empty($sc['search_text']) )
		{
			$sql .= ' and ( m.user_name like "%'.$sc['search_text'].'%" or m.userid  like "%'.$sc['search_text'].'%") ';//
		}

		if(!empty($sc['use_status']))
		{
			$sql.= " and d.use_status='".$sc['use_status']."'";
		}

		if(!empty($sc['couponUsed']))
		{
			$couponTypein = implode("','",$sc['couponUsed']);
			$sql.= " and use_status in ('".$couponTypein."') ";
		}

		if(!empty($sc['couponDate'])){
			$arr = array();
			foreach($sc['couponDate'] as $key => $cdata){
				switch($cdata){
					case "available":
						$today = date('Y-m-d');
						$arr[] =" d.issue_enddate >= '{$today}' ";
					break;
					case "extinc":
						$today = date('Y-m-d');
						$arr[] =" d.issue_enddate < '{$today}' ";
					break;
				}
			}
			if($arr) $sql.= " and (".implode(' OR ',$arr).")";
		}

		if ($sc['issue_date']){
		// 유효기간 검색(시작) :: 개인 맞춤형 안내용
			// 유효기간 검색
			if($sc['issue_date']['sdate'] AND $sc['issue_date']['edate']) {
				$start_date = $sc['issue_date']['sdate'];
				$end_date	= $sc['issue_date']['edate'];
				$sql.=" and d.issue_enddate BETWEEN '{$start_date}' and '{$end_date}' ";
			}
		}

		if(!empty($sc['keyword']))$sql.= " and d.coupon_name like \"%".$sc['keyword']."%\" ";

		if($sc['check_date']=='regist_date'){
			// 발급일 검색(시작)
			if($sc['sdate'] AND !$sc['edate']) {
				$start_date = $sc['sdate'].' 00:00:00';
				$sql.=" and d.regist_date >= '{$start_date}' ";
			}

			// 발급일 검색(끝)
			if($sc['edate'] AND !$sc['sdate']) {
				$start_date = $sc['edate'].' 23:59:59';
				$sql.=" and d.regist_date <= '{$start_date}' ";
			}

			// 발급일 검색
			if($sc['sdate'] AND $sc['edate']) {
				$start_date = $sc['sdate'].' 00:00:00';
				$end_date = $sc['edate'].' 23:59:59';
				$sql.=" and d.regist_date BETWEEN '{$start_date}' and '{$end_date}' ";
			}
		}elseif ($sc['check_date']=='use_date'){
		// 발급일 검색(시작)
			if($sc['sdate'] AND !$sc['edate']) {
				$start_date = $sc['sdate'].' 00:00:00';
				$sql.=" and d.use_date >= '{$start_date}' ";
			}

			// 발급일 검색(끝)
			if($sc['edate'] AND !$sc['sdate']) {
				$start_date = $sc['edate'].' 23:59:59';
				$sql.=" and d.use_date <= '{$start_date}' ";
			}

			// 발급일 검색
			if($sc['sdate'] AND $sc['edate']) {
				$start_date = $sc['sdate'].' 00:00:00';
				$end_date = $sc['edate'].' 23:59:59';
				$sql.=" and d.use_date BETWEEN '{$start_date}' and '{$end_date}' ";
			}
		}

		// 다운로드 쿠폰 고유키 확인
		if(!empty($sc['download_seq']))
		{
			$sql.= " and d.download_seq='".$sc['download_seq']."'";
		}

		$sql.=" order by d.download_seq desc ";
		if(!$all)$sql .=" limit {$sc['page']}, {$sc['perpage']} ";
		$query = $this->db->query($sql);
		$data['result'] = $query->result_array();

		if(!$all){
			//총건수
			$sql = "SELECT FOUND_ROWS() as COUNT";
			$query_count = $this->db->query($sql);
			$res_count= $query_count->result_array();
			$data['count'] = $res_count[0]['COUNT'];
		}
		return $data;
	}

	//	사용가능한 총건수
	public function get_download_have_total_count($sc, $members)
	{

		//총 보유한 쿠폰 수
		$this->db->select("COUNT(*) AS cnt");
		if(isset($sc['member_seq'])) $this->db->where("member_seq", $sc['member_seq']);
		$res_count 				= $this->db->get($this->coupon_download)->row_array();
		$result['totalcount'] 	= $res_count['cnt'];

		// 보유한쿠폰 중 사용가능한 미사용 쿠폰
		$this->db->select("COUNT(*) AS cnt");
		$this->db->where("use_status='unused' and issue_enddate >= date(now()) and issue_startdate <= date(now())");
		if(isset($sc['member_seq'])) $this->db->where("member_seq", $sc['member_seq']);
		$res_count 				= $this->db->get($this->coupon_download)->row_array();
		$result['unusedcount'] 	= $res_count['cnt'];

		//다운로드 가능 쿠폰
		$data = $this->get_my_download($sc,$members,'totalcnt');
		$result['svcount'] = $data['count'];

		return $result;
	}


	/**
	*@ 마이페이지의 > 다운로드 가능 쿠폰 목록
	* 오프라인쿠폰 @2016-11-04
	* 생일자 : 생일전 ~ 생일후 기간
	* 기념일 : 기념일전 ~ 기념일후 기간
	* 배송비 : 발급전 ~ 발급후 기간
	* 등록 : 등급조정 이후기간
	==> AND 등급제한 체크
	==> AND 전체수량제한
	**/
	public function get_my_download($sc, $members,$totalcnt=null)
	{
		if( !$members['point']) $members['point'] = 0;//null

		if( $totalcnt == 'totalcnt' ) {
			$sql = "SELECT count(coupon.coupon_seq) as cnt ";
		}else{
			$sql = "SELECT SQL_CALC_FOUND_ROWS coupon.*";
		}
		if($sc['coupon_type'] ) {
			$coupon_typein = @implode("','",$sc['coupon_type']);
			$sqltype = " c.type IN ('{$coupon_typein}') ";
		}else{
			$sqltype = " (
							(
								c.type='shipping'
								AND (
									(c.download_startdate is null  AND c.download_enddate is null )
									OR
									(c.download_startdate <='".date('Y-m-d H:i:s',time())."' AND c.download_enddate >='".date('Y-m-d H:i:s',time())."')
								)
								AND (
									(c.download_starttime is null  AND c.download_endtime is null )
									OR
									(c.download_starttime <='".date('H:i',time())."' AND c.download_endtime >= '".date('H:i',time())."')
								)
							)
							OR (
								c.type='download' AND c.use_type = 'offline'
							)
							OR
							( c.type in ('memberGroup','point','birthday','anniversary','memberGroup_shipping','memberlogin','memberlogin_shipping','membermonths','membermonths_shipping','order') )
							OR (
								c.type in ('ordersheet')
							)
						) ";
		}

		if( !empty($sc['coupon_seq']) )
		{
			$sqltype .= ' and c.coupon_seq = '.$sc['coupon_seq'].' ';
		}

		$sql .= " FROM (
						 SELECT c.*,
					if( (c.type in ('shipping', 'point', 'ordersheet') OR (c.type='download' AND c.use_type = 'offline')),SUM(if(d.use_status='used',1,0)),0) as used_cnt,
					if( (c.type in ('shipping', 'point', 'ordersheet') OR (c.type='download' AND c.use_type = 'offline')),SUM(if(d.use_status='unused',1,0)),0) as unused_cnt,
					SUM(if( (c.type ='order') AND substring(d.regist_date ,1,7) = '".$sc['month']."' ,1,0)) as membermonthsuse_order,
					SUM(if( (c.type = 'memberlogin'  OR c.type = 'memberlogin_shipping' ) AND substring(d.regist_date ,1,7) = '".$sc['month']."' ,1,0)) as membermonthsuse_login,
					SUM(if( (c.type ='membermonths' OR c.type ='membermonths_shipping') AND substring(d.regist_date ,1,7) = '".$sc['month']."' ,1,0)) as membermonthsuse_months,
					(case
					  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY), INTERVAL 1 YEAR))
					  then DATE_SUB(DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY), INTERVAL 1 YEAR)
					  else DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY)
					end) as birthday_beforeday,
					(case
					  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY), INTERVAL 1 YEAR))
					  then DATE_SUB(DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY), INTERVAL 1 YEAR)
					  else DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY)
					end) as birthday_afterday,
					(case
					  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY), INTERVAL 1 YEAR))
					  then DATE_SUB(DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY), INTERVAL 1 YEAR)
					  else DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY)
					end) as anniversary_beforeday,

					(case
					  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY), INTERVAL 1 YEAR))
					  then DATE_SUB(DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY), INTERVAL 1 YEAR)
					  else DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY)
					end) as anniversary_afterday,

					 if( (c.type = 'memberGroup' OR c.type = 'memberGroup_shipping') AND (d.regist_date BETWEEN \"".$members['grade_update_date']."\" AND DATE_ADD(\"".$members['grade_update_date']."\", INTERVAL c.after_upgrade DAY)) AND (d.down_year != '".$members['group_seq']."'), 1, 0) as upgrade_groupday_dk,
					if( (c.type = 'memberGroup' OR c.type = 'memberGroup_shipping'), DATE_ADD(\"".$members['grade_update_date']."\", INTERVAL c.after_upgrade DAY), 0) as upgrade_groupday,
					if(  (c.type = 'memberlogin' ) OR ( c.type = 'memberlogin_shipping' ),
					(
						SELECT count(*)
						 FROM fm_member_order
						 WHERE `member_seq`='".$members['member_seq']."' AND month >= replace(substring(DATE_SUB(\"".$sc['today']."\", INTERVAL c.memberlogin_terms MONTH),1,7),'-','')
					),0) as member_order_cnt,
					if(  (c.type = 'memberlogin' ) OR ( c.type = 'memberlogin_shipping' ),
					(
						replace(substring(DATE_SUB(\"".$sc['today']."\", INTERVAL c.memberlogin_terms MONTH),1,7),'-','')
					),0) as member_order_terms,
					if( (c.type = 'order'),
					(
						SELECT count(*)
						 FROM fm_member_order
						 WHERE `member_seq`='".$members['member_seq']."'
					),0) as member_order_total,
					if(  (c.type = 'memberlogin' ) OR (c.type = 'memberlogin_shipping'),
					(
						SELECT count(*)
						 FROM fm_member_order
						 WHERE `member_seq`='".$members['member_seq']."'
					),0) as login_member_order_total,
					if( (c.type ='order'), DATE_ADD(\"".$members['regist_date']."\", INTERVAL c.order_terms DAY),0) as order_afterday,
							(
								SELECT count(*)
								 FROM fm_coupon_group
								 WHERE coupon_seq=c.coupon_seq AND `group_seq`='".$members['group_seq']."'
							) as mbgroup_issue_cnt,
							(
								SELECT count(*)
								 FROM fm_coupon_group
								 WHERE coupon_seq=c.coupon_seq
							) as allgroup_issue_cnt,
					d.download_seq as down_download_seq,
					d.regist_date as down_regist_date
						 FROM fm_coupon c
				 LEFT JOIN fm_download d
					ON (c.coupon_seq = d.coupon_seq AND d.download_seq = (SELECT download_seq FROM fm_download d1 WHERE d1.coupon_seq = c.coupon_seq AND d1.member_seq='".$members['member_seq']."' order by d1.regist_date desc limit 1 ))
						 WHERE
						".$sqltype."
						 AND c.issue_stop = 0
						GROUP BY c.coupon_seq
					) coupon
				 WHERE
					(
			(allgroup_issue_cnt =0 AND mbgroup_issue_cnt=0) OR (allgroup_issue_cnt>0 AND mbgroup_issue_cnt>0)
			)
			AND
			(
				(
				  type = 'birthday'
				  AND (down_regist_date is null OR (down_regist_date and !(down_regist_date BETWEEN birthday_beforeday AND birthday_afterday)) )
				  AND ( CURDATE() BETWEEN birthday_beforeday AND birthday_afterday )
				) OR (
				  type = 'anniversary'
				  AND (down_regist_date is null OR (down_regist_date and !(down_regist_date BETWEEN anniversary_beforeday AND anniversary_afterday)) )
				  AND ( CURDATE() BETWEEN anniversary_beforeday AND anniversary_afterday )
				) OR (
				  (
				    (type = 'shipping' OR (type='download' AND use_type = 'offline')) AND
				    (( used_cnt > 0 AND duplication_use = 1  AND unused_cnt=0) OR  (used_cnt = 0 AND unused_cnt=0))
				  )
				) OR (
				  ( type = 'point' ) AND ( ".$members['point'].">=coupon_point )
				) OR (
				   (type = 'memberGroup' OR type = 'memberGroup_shipping')
				   AND (down_regist_date is null OR (down_regist_date and !(down_regist_date BETWEEN '".$members['grade_update_date']."' AND upgrade_groupday)))
				   AND ( NOW() BETWEEN '".$members['grade_update_date']."' AND upgrade_groupday )
				   AND upgrade_groupday_dk = 0
				) OR (
					( type = 'memberlogin' OR type = 'memberlogin_shipping' ) AND ( membermonthsuse_login = 0 ) AND (login_member_order_total > 0) AND (member_order_cnt <= 0)
				) OR (
					( type = 'membermonths' OR type = 'membermonths_shipping' ) AND  membermonthsuse_months = 0
				) OR (
					( type = 'order' AND  membermonthsuse_order = 0 ) AND ( member_order_total = 0 ) AND ( now() > order_afterday )
				) OR (
					(
						type in ('ordersheet')
						AND (( used_cnt > 0 AND duplication_use = 1  AND unused_cnt=0) OR  (used_cnt = 0 AND unused_cnt=0))
					)
				)
			)
					";
		$sql.=" order by coupon.coupon_seq desc ";
		if( $totalcnt != 'totalcnt' && ($sc['perpage']) ) {
			$sql .=" limit {$sc['page']}, {$sc['perpage']} ";
		}

		$query = $this->db->query($sql);

		if( $totalcnt == 'totalcnt' ) {//다운가능 총갯수 추출에만
			$datatotalcnt = ($query)?$query->row_array():'';
			$data['count'] = $datatotalcnt['cnt'];
		}else{
			$data['result'] = ($query)?$query->result_array():'';

			if( $totalcnt != 'couponall' ) {//다운가능 총갯수 추출에만
				//총건수
				$query = "SELECT FOUND_ROWS() as COUNT";
				$query_count = $this->db->query($query);
				$res_count= ($query)?$query_count->result_array():'';
				$data['count'] = $res_count[0]['COUNT'];
			}
		}
		//debug_var($data);//

		return $data;
	}
	//쿠폰 새창/이벤트페이지 전체 노출
	public function get_promotion_coupon_download($sc, $totalcnt=null)
	{
		$sqltype = "";

		if( !empty($sc['coupon_seq']) )
		{
			$sqltype .= ' and coupon_seq = '.$sc['coupon_seq'].' ';
		}

		if($sc['coupon_type'] ) {
			$coupon_typein = @implode("','",$sc['coupon_type']);
			$sqltype .= " AND type IN ('{$coupon_typein}') ";
		}

		if( $sc['coupon_popup_use'] ) {//팝업제공여부
			//$sqltype .= " AND coupon_popup_use = '".$sc['coupon_popup_use']."' ";
		}

		if( $coupon_typein == 'shipping' ) {
			$coupon_shipping_query = " OR ( type='shipping'
									AND (
										(download_startdate is null  AND download_enddate is null )
										OR
										(download_startdate <='".date('Y-m-d H:i:s',time())."' AND download_enddate >='".date('Y-m-d H:i:s',time())."')
									)
									AND (
										(download_starttime is null  AND download_endtime is null )
										OR
										(download_starttime <='".date('H:i',time())."' AND download_endtime >= '".date('H:i',time())."')
									)
									) ";
		}
		$sql = "SELECT * FROM fm_coupon
				 WHERE
					(
						( type not in ('admin','admin_shipping','point','offline_coupon','offline_emon') )
						".$sqltype."
						".$coupon_shipping_query."
					)
					AND issue_stop = 0
		";
		$query = $this->db->query($sql);
		//debug_var($sql);
		$data['result'] = ($query)?$query->result_array():'';
		return $data;
	}

	//쿠폰 새창/이벤트페이지 전체 노출
	public function get_promotion_coupon_my_download($sc, $totalcnt=null)
	{
		$sqltype = "";
		$members = $this->mdata;
		if( !empty($sc['coupon_seq']) )
		{
			$sqltype .= ' and c.coupon_seq = '.$sc['coupon_seq'].' ';
		}

		if($sc['coupon_type'] ) {
			$coupon_typein = @implode("','",$sc['coupon_type']);
			$sqltype .= " AND c.type IN ('{$coupon_typein}') ";
		}

		if( $coupon_typein == 'shipping' ) {
			$coupon_shipping_query = " (OR ( type='shipping'
									AND (
										(download_startdate is null  AND download_enddate is null )
										OR
										(download_startdate <='".date('Y-m-d H:i:s',time())."' AND download_enddate >='".date('Y-m-d H:i:s',time())."')
									)
									AND (
										(download_starttime is null  AND download_endtime is null )
										OR
										(download_starttime <='".date('H:i',time())."' AND download_endtime >= '".date('H:i',time())."')
									)
									)) ";
		}
		$sql = "SELECT *
				FROM (
					SELECT c.*,
						(case
						  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY), INTERVAL 1 YEAR))
						  then DATE_SUB(DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY), INTERVAL 1 YEAR)
						  else DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY)
						end) as birthday_beforeday,
						(case
						  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY), INTERVAL 1 YEAR))
						  then DATE_SUB(DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY), INTERVAL 1 YEAR)
						  else DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY)
						end) as birthday_afterday,

						(case
						  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY), INTERVAL 1 YEAR))
						  then DATE_SUB(DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY), INTERVAL 1 YEAR)
						  else DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY)
						end) as anniversary_beforeday,
						(case
						  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY), INTERVAL 1 YEAR))
						  then DATE_SUB(DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY), INTERVAL 1 YEAR)
						  else DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY)
						end) as anniversary_afterday,

						if( (c.type ='birthday') AND (d.regist_date BETWEEN DATE_SUB(\"".$sc['year']."-01-01\", INTERVAL c.before_birthday DAY) AND DATE_ADD(\"".$sc['year']."-12-31\", INTERVAL c.after_birthday DAY)), 1, 0) as birthday_year,
						if( (c.type ='anniversary') AND (d.regist_date BETWEEN DATE_SUB(\"".$sc['year']."-01-01\", INTERVAL c.before_anniversary DAY) AND DATE_ADD(\"".$sc['year']."-12-31\", INTERVAL c.after_anniversary DAY)), 1, 0) as anniversary_year,
						 if( (c.type = 'memberGroup' OR c.type = 'memberGroup_shipping') AND (d.regist_date BETWEEN \"".$members['grade_update_date']."\" AND DATE_ADD(\"".$members['grade_update_date']."\", INTERVAL c.after_upgrade DAY)) AND (d.down_year != '".$members['group_seq']."'), 1, 0) as upgrade_groupday_dk,
						if( (c.type = 'memberGroup' OR c.type = 'memberGroup_shipping'), DATE_ADD(\"".$members['grade_update_date']."\", INTERVAL c.after_upgrade DAY), 0) as upgrade_groupday,
						(
							SELECT count(*)
							 FROM fm_coupon_group
							 WHERE coupon_seq=c.coupon_seq AND `group_seq`='".$members['group_seq']."'
						) as mbgroup_issue_cnt,
						(
							SELECT count(*)
							FROM fm_coupon_group
							WHERE coupon_seq=c.coupon_seq
						) as allgroup_issue_cnt,
						d.download_seq as down_download_seq,
						d.regist_date as down_regist_date
						 FROM fm_coupon c
						 LEFT JOIN fm_download d
							ON (c.coupon_seq = d.coupon_seq AND d.download_seq = (SELECT download_seq FROM fm_download d1 WHERE d1.coupon_seq = c.coupon_seq AND d1.member_seq='".$members['member_seq']."' order by d1.regist_date desc limit 1 ))
						 WHERE
						 c.issue_stop = 0
						".$sqltype."
						GROUP BY c.coupon_seq
					) coupon
				 WHERE
					issue_stop = 0
						".$coupon_shipping_query."
					AND (
					(allgroup_issue_cnt =0 AND mbgroup_issue_cnt=0) OR (allgroup_issue_cnt>0 AND mbgroup_issue_cnt>0)
					)
					AND
					(
						(
						  type = 'birthday'
						  AND (down_regist_date is null OR (down_regist_date and !(down_regist_date BETWEEN birthday_beforeday AND birthday_afterday)) )
						  AND ( CURDATE() BETWEEN birthday_beforeday AND birthday_afterday )
						) OR (
						  type = 'anniversary'
						  AND (down_regist_date is null OR (down_regist_date and !(down_regist_date BETWEEN anniversary_beforeday AND anniversary_afterday)) )
						  AND ( CURDATE() BETWEEN anniversary_beforeday AND anniversary_afterday )
						) OR (
						  (type = 'memberGroup' OR type = 'memberGroup_shipping')
						  AND (down_regist_date is null OR (down_regist_date and !(down_regist_date BETWEEN '".$members['grade_update_date']."' AND upgrade_groupday)))
						  AND ( NOW() BETWEEN '".$members['grade_update_date']."' AND upgrade_groupday )
						  AND upgrade_groupday_dk = 0
						)
					)
		";
		$query = $this->db->query($sql);
		$data['result'] = ($query)?$query->result_array():'';
		return $data;
	}

	/* 관리자의 발급내역 > 총 할인금액추출*/
	public function get_coupontotal($sc, $coupons)
	{

		if(isset($sc['member_seq'])) $addsql.= ' and m.member_seq ='.$sc['member_seq'];//회원

		if( !empty($sc['search_text']) )
		{
			$addsql .= ' and ( m.user_name like "%'.$sc['search_text'].'%" or m.userid  like "%'.$sc['search_text'].'%") ';//
		}

		if(!empty($sc['use_status'] && $sc['use_status'] != 'all'))
		{
			$addsql.= " and down.use_status='".$sc['use_status']."'";
		}

		// 등록일 검색(시작)
		if($sc['sdate'] AND !$sc['edate']) {
			$start_date = $sc['sdate'].' 00:00:00';
			$addsql.=" and down.regist_date >= '{$start_date}' ";
		}

		// 등록일 검색(끝)
		if($sc['edate'] AND !$sc['sdate']) {
			$start_date = $sc['edate'].' 23:59:59';
			$addsql.=" and down.regist_date <= '{$start_date}' ";
		}

		// 등록일 검색
		if($sc['sdate'] AND $sc['edate']) {
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$addsql.=" and down.regist_date BETWEEN '{$start_date}' and '{$end_date}' ";
		}

		if ( ( $coupons['type'] == 'shipping' || strstr($coupons['type'],'_shipping') ) ) {//배송비할인

			$totalsalequery = "SELECT
						SUM(ords.shipping_coupon_sale) AS coupon_sale
					FROM
						fm_download down
					LEFT JOIN fm_order_shipping ords ON ords.shipping_coupon_down_seq = down.download_seq
					LEFT JOIN fm_order ord ON ords.order_seq = ord.order_seq
					LEFT JOIN fm_member m ON m.member_seq = ord.member_seq
					WHERE
						ord.step NOT IN ('0','85','95','99') AND  down.coupon_seq='".$sc[no]."'".$addsql;

		}elseif ( $coupons['type'] == 'ordersheet'){		// 주문서쿠폰

			$totalsalequery = "SELECT
					SUM(ord.ordersheet_sale) AS coupon_sale
				FROM
					fm_download down
					LEFT JOIN fm_order ord ON ord.ordersheet_seq = down.download_seq
					LEFT JOIN fm_member m ON m.member_seq = ord.member_seq
				WHERE
					ord.step NOT IN ('0','85','95','99') AND  down.coupon_seq = '".$sc[no]."'".$addsql;

		}else{

			$totalsalequery = "SELECT SUM(o.coupon_sale) AS coupon_sale
				FROM
					fm_download down
					LEFT JOIN fm_order_item_option o ON o.download_seq = down.download_seq
					LEFT JOIN fm_order ord ON ord.order_seq = o.order_seq
					LEFT JOIN fm_member m ON m.member_seq = ord.member_seq
				WHERE
					ord.step NOT IN ('0','85','95','99') AND  down.coupon_seq = '".$sc[no]."'".$addsql;

		}
		$coupon_sale_query 			= $this->db->query($totalsalequery);
		$coupon_sale 				= $coupon_sale_query->row_array();
		$coupon_sale['coupon_sale'] = ($coupon_sale['coupon_sale'])?$coupon_sale['coupon_sale']:0;

		return $coupon_sale;
	}

	/**
	*@ 마이페이지의 > 개별다운시 체크용
	* 생일자 : 생일전 ~ 생일후 기간
	* 기념일 : 기념일전 ~ 기념일후 기간
	* 등록 : 등급조정 이후기간
	**/
	public function get_my_download_member($couponSeq,$members)
	{
		$sql = "SELECT c.*,
		(case
		  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY), INTERVAL 1 YEAR))
		  then DATE_SUB(DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY), INTERVAL 1 YEAR)
		  else DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY)
		end) as birthday_beforeday,
		(case
		  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_birthday']."\", INTERVAL c.before_birthday DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY), INTERVAL 1 YEAR))
		  then DATE_SUB(DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY), INTERVAL 1 YEAR)
		  else DATE_ADD(\"".$members['thisyear_birthday']."\", INTERVAL c.after_birthday DAY)
		end) as birthday_afterday,

		(case
		  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY), INTERVAL 1 YEAR))
		  then DATE_SUB(DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY), INTERVAL 1 YEAR)
		  else DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY)
		end) as anniversary_beforeday,
		(case
		  when ( CURDATE() BETWEEN DATE_SUB(DATE_SUB(\"".$members['thisyear_anniversary']."\", INTERVAL c.before_anniversary DAY), INTERVAL 1 YEAR) AND DATE_SUB(DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY), INTERVAL 1 YEAR))
		  then DATE_SUB(DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY), INTERVAL 1 YEAR)
		  else DATE_ADD(\"".$members['thisyear_anniversary']."\", INTERVAL c.after_anniversary DAY)
		end) as anniversary_afterday,

		if( (c.type = 'memberGroup' OR c.type = 'memberGroup_shipping'), DATE_ADD(\"".$members['grade_update_date']."\", INTERVAL c.after_upgrade DAY), 0) as upgrade_groupday,
		if(  (c.type = 'memberlogin' ) OR ( c.type = 'memberlogin_shipping' ),
		(
			SELECT count(*)
			FROM fm_member_order
			WHERE `member_seq`='".$members['member_seq']."' AND month >= substring(DATE_SUB(\"".$sc['today']."\", INTERVAL c.memberlogin_terms MONTH),1,7)
		),0) as member_order_cnt,
		if(  (c.type = 'memberlogin' ) OR (c.type = 'memberlogin_shipping') OR (c.type = 'order'),
		(
			SELECT count(*)
			FROM fm_member_order
			WHERE `member_seq`='".$members['member_seq']."'
		),0) as member_order_total,
		if( (c.type ='birthday'), DATE_ADD(\"".$members['regist_date']."\", INTERVAL c.order_terms DAY),0) as order_afterday,
		(
			SELECT count(*)
			FROM fm_coupon_group
			WHERE coupon_seq=c.coupon_seq AND `group_seq`='".$members['group_seq']."'
		) as mbgroup_issue_cnt,
		(
			SELECT count(*)
			FROM fm_coupon_group
			WHERE coupon_seq=c.coupon_seq
		) as allgroup_issue_cnt
		FROM fm_coupon c
		WHERE coupon_seq = ".$couponSeq." ";
		$query = $this->db->query($sql);
		$result = $query->row_array();
		return $result;
	}

	## 입점사별 배송쿠폰 추출
	public function get_shippingcoupon_provider($provider_seq, $member_seq, $price, $shippingprice, $sellcoupon=null, $shippingcouponprice=null){
		/**
		if	($provider_seq == 1){
			$addProviderWhere	= " ( type = 'shipping' and salescost_admin = '100' and (provider_list is null or provider_list = '') ) ";
		}else{
			$addProviderWhere	= " ( type = 'shipping' and salescost_provider = '100' and provider_list like '%|".$provider_seq."|%' ) ";
		}
			".$addProviderWhere."
		**/

		$today = date("Y-m-d",time());
		$result = "";
		$query = "select * from
		(
			select d.*
			from fm_download d
			where member_seq = ?
			and issue_startdate <= ? and issue_enddate >= ?
			and use_status = 'unused'
		) a where (type = 'shipping'  or  right(type,9) = '_shipping')
		";

		if(!$this->_is_mobile_app_agent) {
			$query .= " and sale_agent != 'app' ";
		}
		if(!$this->_is_mobile_agent) {
			$query .= " and sale_agent != 'm' ";
		}
		// o2o 사용 매장 조건 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_public_able_o2o_sale_store($query);

		$query = $this->db->query($query,array($member_seq, $today, $today));
		foreach($query->result_array() as $data){

			//배송비쿠폰> 할인부담금 관련 부담자의 상품에만 적용.
			if ( $data['type'] == 'shipping' ) {
				if	($provider_seq == 1 && $data['provider_list'])	continue;
				if	($provider_seq != 1 && !$data['provider_list'])	continue;
				if	($data['provider_list'] && !strstr($data['provider_list'], '|'.$provider_seq.'|'))	continue;
			}

			//사용제한 - 유입경로 체크(배송비 쿠폰은 상품이 아닌 사용가능 입점사로 체크)
			if( couponordercheck($data, '', $price, 1, $provider_seq) != true ) {
				continue;
			}

			if($sellcoupon == $data['download_seq'] ){
				$data['shipping_sale'] = $shippingcouponprice;
				$result[] = $data;
			}else{
				$data['shipping_sale'] = 0;
				if( $data['limit_goods_price'] <= $price ) {//사용제한 원이상인경우만
					if( $data['shipping_type'] == 'free' ){
						$data['shipping_sale'] =  $shippingprice;
						if($data['max_percent_shipping_sale'] > 0 && $data['max_percent_shipping_sale'] < $shippingprice ){
							$data['shipping_sale'] =  $data['max_percent_shipping_sale'];
						}
					}else if( $data['shipping_type'] == 'won'){
						$data['shipping_sale'] = $data['won_shipping_sale'];
					}

					//할인금액이 판매금액보다 큰경우 구매금액
					if($shippingprice < $data['shipping_sale']){
						$data['shipping_sale'] = $shippingprice;
					}
					$data['shipping_sale'] = get_price_point($data['shipping_sale']);

					$result[] = $data;
				}
			}
		}
		@usort($result, 'shipping_sale_desc');//할인금액내림차순
		return $result;
	}


	## 입점사 할인부담금 계산
	public function get_salecost_provider($params){
		$ea						= $params['ea'];
		$coupon_sale			= $params['coupon_sale'];
		$download_seq			= $params['download_seq'];
		$coupons				= $params['coupons'];
		$provider_seq			= $params['provider_seq'];//상품의입점사
		$salescost_provider		= 0;
		$shipping_provider_seq = $this->shipping_group_policy[$params['shipping_group']]['provider_seq'];//배송그룹의 입점사

		if	($coupon_sale > 0 && $download_seq > 0 && is_array($coupons) && count($coupons) > 0){
			## 해당 쿠폰 정보 추출
			foreach($coupons as $k => $coupon_data){
				if	($download_seq == $coupon_data['download_seq'] && ( ($coupon_data['type'] == 'birthday' || $coupon_data['type'] == 'anniversary' || $coupon_data['type'] == 'memberGroup' || $coupon_data['type'] == 'member' || $coupon_data['type'] == 'memberlogin' || $coupon_data['type'] == 'membermonths'  || $coupon_data['type'] == 'order'  && $provider_seq != 1 )	||	( ($coupon_data['type'] == 'download' || $coupon_data['type'] == 'offline_coupon' ) && ( ($coupon_data['provider_list'] && strstr($coupon_data['provider_list'], '|'.$provider_seq.'|')) || (!$coupon_data['provider_list'] && $provider_seq == 1)) )	) ) {
					//배송그룹이 본사가 아니거나
					$provider_per	= $coupon_data['salescost_provider'];
					break;
				}
			}

			## 입점사의 부담율이 0보다 클 시 계산 ( 개당 부담금 )
			if	($provider_per > 0){
				//debug_var($coupon_sale." * (".$provider_per."/100)/".$ea);
				$salescost_provider		= floor(($coupon_sale * ($provider_per / 100)) / $ea);
			}
		}

		return $salescost_provider;
	}

	// 주문정보로 배송비 할인쿠폰 고유번호 가져오기 :: 2015-04-01 lwh
	public function get_shipping_coupon($order_seq){
		$sql = " select shipping_coupon_down_seq from fm_order_shipping where order_seq = '" . $order_seq . "' and shipping_coupon_down_seq!=''";
		$query = $this->db->query($sql);

		foreach($query->result_array() as $row){
			$returnArr[] = $row;
		}
		return $returnArr;
	}

	/**
	 * 주문서 작성 시 사용 가능한 주문서쿠폰 목록
	 * 주문서 쿠폰은 본사 100% 부담
	 */
	public function get_able_use_ordersheet_coupon_list($member_seq, $price=0, $sellcoupon=null, $ordersheetcouponprice=null){

		if(!$member_seq) return;
		if( ! $this->config_system['cutting_price'] ) $this->config_system['cutting_price'] = 10;
		$result = $except_subquery = "";

		if($this->_is_mobile_app_agent) {
			$coupontypear = array('ordersheet');
			$mobilequery = " ";
		}elseif($this->_is_mobile_agent) {
			$coupontypear = array('ordersheet');
			$mobilequery = " AND sale_agent != 'app' ";
		}else{
			$coupontypear = array('ordersheet');
			$mobilequery = " AND sale_agent != 'm' AND sale_agent != 'app' ";
		}
		$coupontype = " AND type IN ('".implode("','",$coupontypear)."') ".$mobilequery;

		// o2o 사용 매장 조건 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_public_able_o2o_sale_store($coupontype);

		$query = "select * from
		(
			select d.*
			from fm_download d
			where member_seq = ?
			and issue_startdate <= ?
			and issue_enddate >= ?
			and use_status = 'unused'
		) a where 1=1
			{$coupontype}
		";

		$query = $this->db->query($query,array($member_seq, $this->today, $this->today));
		$result = array();
		foreach($query->result_array() as $data){
			// 주문서 쿠폰은 본사 100% 부담

			//사용제한 - 유입경로 체크
			if( couponordercheck($data, '', $price, 1) != true ) {
				continue;
			}
			$data['ordersheet_sale'] = 0;

			if( $data['coupon_same_time'] == 'N' ) {//단독쿠폰이면
				$data['couponsametimetitle'] = '-단독';
			}else{
				$data['couponsametimetitle'] = '';
			}

			if( $data['sale_payment'] == 'b' ) {//무통장만가능
				$data['couponsametimetitle'] .= ($data['couponsametimetitle'])?',무통장':'무통장';
			}

			if( $data['limit_goods_price'] <= $price ) {//사용제한 원이상인경우만
				if( $data['sale_type'] == 'percent' && $data['percent_goods_sale'] && $price ){

					if( $this->config_system['cutting_price'] != 'none' ){
						$data['ordersheet_sale'] = $data['percent_goods_sale'] * $price / ( $this->config_system['cutting_price'] * 100);
						$data['ordersheet_sale'] = get_cutting_price($data['ordersheet_sale'],'{=basic_currency}');
						$data['ordersheet_sale'] = $data['ordersheet_sale'] * $this->config_system['cutting_price'];
					}else{
						$data['ordersheet_sale'] = $data['percent_goods_sale'] * $price / 100;
						$data['ordersheet_sale'] = get_cutting_price($data['ordersheet_sale'],'{=basic_currency}');
					}

					if($data['max_percent_goods_sale'] < $data['ordersheet_sale']){
						$data['ordersheet_sale'] = $data['max_percent_goods_sale'];
					}
				}else if( $data['sale_type'] != 'percent' && $data['won_goods_sale'] && $price ){
					$data['ordersheet_sale'] = $data['won_goods_sale'];
				}

				$data['ordersheet_sale'] = get_price_point($data['ordersheet_sale']);

				// 주문서 쿠폰은 중복할인 개념 없음쿠폰 할인 금액 체크
				$ordersheet_sale = $data['ordersheet_sale'];
				$ordershhetprice_total	= $price;

				//상품의 총할인금액보다 쿠폰할인금액이 큰경우 상품할인금액으로 대체
				if($ordershhetprice_total < $ordersheet_sale && $ordersheet_sale)
				{
					$data['ordersheet_sale'] = $price;
				}

				$result[] = $data;
			}
		}
		@usort($result, 'ordersheet_sale_desc');//할인금액내림차순
		return $result;
	}

	// 미사용 쿠폰 전체삭제 :: 2019-09-02 pjw
	public function delete_unused_download($couponSeq){

		// 리턴 할 결과값
		$result				= array();

		// 쿠폰 정보 조회
		$couponData			= $this->get_coupon($couponSeq);
		if(!$couponData) $result = array('status'=>false, 'msg'=>'쿠폰이 올바르지 않습니다.'); //쿠폰이 올바르지 않습니다.

		// 삭제 가능한 발급 쿠폰 조회
		$sql				= "SELECT * FROM ".$this->coupon_download." WHERE use_status = 'unused' and coupon_seq = ".$couponSeq;
		$query				= $this->db->query($sql);
		$download_list		= $query->result_array();
		$download_seq_list	= array();

		// 발급 쿠폰 고유번호 리스트 생성
		if(count($download_list) > 0){
			foreach($download_list as $download){
				$download_seq_list[] = $download['download_seq'];
			}

			// 리스트에 해당되는 데이터만 삭제처리
			$sql = "DELETE FROM ".$this->coupon_download." WHERE download_seq in (". implode(',', $download_seq_list) .")";
			$this->db->query($sql);
			$sql = "DELETE FROM ".$this->coupon_download_issuecategory." WHERE download_seq in (". implode(',', $download_seq_list) .")";
			$this->db->query($sql);
			$sql = "DELETE FROM ".$this->coupon_download_issuegoods." WHERE download_seq in (". implode(',', $download_seq_list) .")";
			$this->db->query($sql);

			$result = array('status'=>true, 'msg'=>'총 '.count($download_list).' 건의 쿠폰을 정상적으로 삭제하였습니다.');
		}else{
			$result = array('status'=>false, 'msg'=>'미사용 된 발급 쿠폰이 없습니다.');
		}

		return $result;
	}

	// 쿠폰 타입을 이용해서 쿠폰 유형 가져오기
	public function get_coupon_category($ctype = '',$opt=array()){

		$ccategory = array();
		foreach($this->coupon_category_sub as $category => $cdata){
			foreach($cdata as $_ctype => $_clist){
				if($_ctype == $ctype){
					$ccategory['coupon_category']	= $category;
					$ccategory['coupon_type']		= $_ctype;
					$ccategory['coupon_type_name']	= $_clist['name'];
				}elseif(array_key_exists($ctype,$_clist['list'])){
					$ccategory['coupon_category']	= $category;
					$ccategory['coupon_type']		= $_ctype;
					$ccategory['coupon_type_name']	 = $_clist['name'];
				}
			}
		}

		$ccategory['coupon_category_name'] = $this->coupon_category[$ccategory['coupon_category']];

		$return  = array();
		if(is_array($opt) && count($opt) > 0){
			foreach($opt as $_opt){
				$return[$_opt] = $ccategory[$_opt];
			}
		}else{
			$return = $ccategory;
		}

		return $return;

	}

	// 신규가입 쿠폰 발급
	public function downloadMemberJoinCoupons($memberSeq) {
		// 발급 된 쿠폰 수
		$couponCount = 0;

		// 신규가입 쿠폰 목록 조회
		$coupons = $this->db->select('coupon_seq')
							->from($this->table_coupon)
							->where_in('type', ['member', 'member_shipping'])
							->where('issue_stop', '!= 1')
							->get()
							->result_array();

		// 각 쿠폰 마다 발급처리
		foreach ($coupons as $coupon) {
			$this->_members_downlod($coupon['coupon_seq'], $memberSeq);
			$couponCount++;
		}

		return $couponCount;
	}

	// 앱 설치 쿠폰 발급
	public function downloadAppInstallCoupons($memberSeq) {
		// 발급 된 쿠폰 수
		$couponCount = 0;

		// 앱 설치 쿠폰 목록 조회
		$coupons = $this->db->select('coupon_seq')
							->from($this->table_coupon)
							->where('type', 'app_install')
							->where('issue_stop', '!= 1')
							->get()
							->result_array();

		// 각 쿠폰 마다 발급처리
		foreach ($coupons as $coupon) {
			$this->_members_downlod($coupon['coupon_seq'], $memberSeq);
			$couponCount++;
		}

		return $couponCount;
	}
}


/* End of file couponmodel.php */
/* Location: ./app/models/couponmodel.php */
