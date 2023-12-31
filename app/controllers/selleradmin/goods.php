<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class goods extends selleradmin_base {

	public function __construct() {
		parent::__construct();
		$auth = $this->authmodel->manager_limit_act('goods_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}
		$this->load->helper('goods');
		$this->load->helper('order');
		$this->load->model('errorpackage');
		$this->load->model('goodsmodel');
		$this->load->model('shippingmodel');
		$this->load->library('snssocial');
		$this->load->library('goodslibrary');
		$this->load->library('searchsetting');
		$this->load->library('goodslibrary');

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');

	}

	public function index()
	{
		redirect("/selleradmin/goods/catalog");
	}

	public function catalog()
	{
		$this->admin_menu();
		$this->tempate_modules();

		if($this->config_system['service']['code']=='P_ADVL' && uri_string()!='selleradmin/goods/social_catalog'){
			redirect('selleradmin/goods/social_catalog'.($_SERVER['QUERY_STRING']?'?'.$_SERVER['QUERY_STRING']:''));
		}

		### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');

		// 상품검색폼
		$this->template->define(array('goods_search_form' => $this->skin.'/goods/goods_search_form.html'));

		// 기본검색설정폼 분리 2015-05-04
		$this->template->define(array('set_search_default' => $this->skin.'/goods/_set_search_default_goods.html'));
		$this->template->assign(array('search_page'=>uri_string()));
		$file_path	= $this->template_path();

		// 기본값 설정 정보
		if	(!$this->cfg_goods_default)
			$this->cfg_goods_default	= $this->goodsmodel->get_goods_default_config('goods');

		if	(!$this->cfg_order)
			$this->cfg_order			= config_load('order');

		list($loop,$sc,$sort) =  $this->_goods_list();

		//정렬
		$sc['orderby'] = $sort;

		$arr_common						= array();
		$arr_common['colorPickList']	= getSearchColorList($sc['color_pick']);

		$shippingGroupList	= $this->shippingmodel->get_shipping_group_list($this->providerInfo['provider_seq']);
		$ship_set_code		= $this->shippingmodel->ship_set_code; // 배송설정코드

		foreach((array)$shippingGroupList as $key =>$row) {

			// 국내배송 방법
			$methodText		= '배송방법 없음';
			foreach ($row['shipping_method_list']['korea'] as $mkey => $method) {

				$nowMethodText	= strip_tags($method['shipping_set_name']);

				if ($mkey == 0)
					$methodText	= $nowMethodText;
				else
					$methodText	.= ", {$nowMethodText}";

				if ($method['delivery_std_type'] == 'Y')
					$methodText	.= "<span class='blue'>[기본]</span>";

				if (trim($method['delivery_std_input']) != '') {
					$nowStdInput	= strip_tags($method['delivery_std_input']);
					$methodText		.= "<span class='red'>[{$nowStdInput}]</span>";
				}

			}

			$row['method_korea_text']	= $methodText;

			// 해외배송 방법
			$row['method_global_text']	= '배송방법 없음';

			$methodText		= '배송방법 없음';
			foreach ($row['shipping_method_list']['global'] as $mkey => $method) {
				$nowMethodText	= strip_tags($method['shipping_set_name']);
				if ($mkey == 0)
					$methodText	= $nowMethodText;
				else
					$methodText	.= ", {$nowMethodText}";

				if ($method['delivery_std_type'] == 'Y')
					$methodText	.= "<span class='blue'>[기본]</span>";

				if (trim($method['delivery_std_input']) != '') {
					$nowStdInput	= strip_tags($method['delivery_std_input']);
					$methodText		.= "<span class='red'>[{$nowStdInput}]</span>";
				}

			}

			$row['method_global_text']	= $methodText;


			$shippingGroupList[$key]	= $row;
		}

		$arr_gl_gooda_config 	= $this->goodslibrary->get_goods_config($auth,false,true,$sc['goodsKind']);
		// 엑셀다운로드 레이어
		$this->template->define(array('excel_download_form' => $this->skin.'/excel/_gl_excel_download.html'));

		$this->template->assign('arr_common',$arr_common);
		$this->template->assign('shippingGroupList', $shippingGroupList);
		$this->template->assign(array('ship_set_code' => $ship_set_code));
		$this->template->assign('colorPickList', $colorPickList);
		$this->template->assign(array('cfg_order' => $this->cfg_order));
		$this->template->assign(array('cfg_goods_default' => $this->cfg_goods_default));
		$this->template->assign('loop',$loop['record']);
		$this->template->assign('page',$loop['page']);
		$this->template->assign('provider_seq',$this->providerInfo['provider_seq']);
		$this->template->assign(array('search_yn'=>$loop['search_yn'], 'catalog_page_gubun'=>$catalog_page_gubun));
		$this->template->assign(array('perpage'=>$_GET['perpage'],'orderby'=>$_GET['orderby'],'sort'=>$sort,'sorderby'=>$sorderby));
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc),'arr_gl_gooda_config'=>json_encode($arr_gl_gooda_config)));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	//티켓상품목록
	public function social_catalog()
	{
		define('SOCIALCPUSE',true);
		$this->template->assign('socialcpuse',1);

		$this->cfg_goods_default	= $this->goodsmodel->get_goods_default_config('coupon');
		$this->catalog();
	}

	public function _goods_list($optionType = 'default')
	{
		### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');
		if(isset($auth)) $this->template->assign('auth',$auth);

		$search_page 	= uri_string();
		$batch_update 	= false;			//일괄 업데이트 페이지 접속 여부
		if($search_page == "selleradmin/goods/batch_modify") {
			$search_page = "selleradmin/goods/catalog";
			$batch_update = true;
		}
		$_default 						= array('sort'=>'desc_goods_seq','page'=>1,'perpage'=>10);
		$scRes 							= $this->searchsetting->pagesearchforminfo($search_page,$_default);
		$sc_datePreset					= $scRes['date_preset'];
		$this->template->assign('sc_form',$scRes['form']);
		unset($scRes['form']);
		$sc 							= $scRes;

		if(!$this->input->get("mode")){
			$sc['mode'] = "ifgoods";
		}else{
			$sc['mode'] = $this->input->get("mode");
		}

		$set_default = false;
		if (preg_match('/goods\/batch_modify/',$_SERVER['REQUEST_URI']) && count($sc) == 2) $set_default = true;

		### 특정 검색에 대한 초기화
		// 선택되지 않은 이벤트 seq는 초기화
		switch($sc['event_type']){
			case "event":
				$sc['gift_seq'] 		= "";
				$sc['referersale_seq'] 	= "";
			break;
			case "gift":
				$sc['event_seq'] 		= "";
				$sc['referersale_seq'] 	= "";
			break;
			case "referer":
				$sc['event_seq'] 		= "";
				$sc['gift_seq'] 		= "";
			break;
		}
		if($sc['shipping_group_seq']){
			$sc['shipping_set_code']['domestic'] 		= '';
			$sc['shipping_set_code']['international'] 	= '';
		}

		if($sc['header_search_keyword']) {
			$sc['keyword']		= $sc['header_search_keyword'];
			$sc['search_type']	= 'all';
		}

		### SEARCH
		//$sc['sort']	 		= ($sc['sort']) ? $sc['sort']:'desc_goods_seq';
		$sc['page']	 		= ($sc['page']) ? intval($sc['page']):'1';
		$sc['perpage'] 		= ($sc['perpage']) ? intval($sc['perpage']):'10';
		$sc['provider_seq'] = $this->providerInfo['provider_seq'];
		$sc['goods_type']	= ($sc['goods_type'])? $sc['goods_type']:'goods';	// goods/gift

		// goods(일반)/package(패키지)/coupon(티켓)
		if($batch_update == false){
			$sc['goodsKind'] = "goods";
			if(defined('SOCIALCPUSE') == true){
				$sc['goodsKind'] = "coupon";
			}elseif(defined('PACKAGEUSE') == true){
				$sc['goodsKind'] = "package";
			}
		}else{
			$sc['goodsKind'] = 'goods';	// 일괄업데이트일떄 일반 상품만 업데이트가 기본 값.
			// 4.배송비, 14 입점 마케팅 전달 데이터 일때에는 일반+패키지 상품 모두 업데이트 가능.
			if(in_array($sc['mode'],array('shipping','ifep_shipping','ep_shipping')) ){
				$sc['goodsKind'] = '';
			}
		}

		if($sc["category1"] != "") $sc["category"] = $sc["category1"];
		if($sc["category2"] != "") $sc["category"] = $sc["category2"];
		if($sc["category3"] != "") $sc["category"] = $sc["category3"];
		if($sc["category4"] != "") $sc["category"] = $sc["category4"];

		if	(is_array($sc['provider_status_reason_type']) && count($sc['provider_status_reason_type']) > 0){
			$sc['provider_status_reason_type'] = $sc['provider_status_reason_type'];
		}

		// 기본설정에 날짜있으면 검색조건에 반영됨
		if($sc_datePreset[$sc['select_date_regist']] && empty($sc['sdate']) && empty($sc['edate'])) {
			$sc['sdate'] = $sc_datePreset[$sc['select_date_regist']][0];
			$sc['edate'] = $sc_datePreset[$sc['select_date_regist']][1];
		}

		### GOODS
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('videofiles');
		$cfg_order = config_load('order');
		$this->load->model('ordermodel');
		$this->load->model('providermodel');
		$this->load->model('locationmodel');
		$this->load->model('membermodel');
		$this->load->model("eventmodel");
		$this->load->model("giftmodel");

		//속도 개선을 위해 새 프로세스 적용 18.11.20 kmj
		$loop = $this->goodsmodel->admin_goods_list_new($sc);
		$this->template->assign(array('param_count'=>urlencode(base64_encode(end($this->db->queries)))));

		### ADDITION
		/*$goods_addition = $this->goodsmodel->goods_addition_list_all();
		$model				= $goods_addition['model'];
		$brand				= $goods_addition['brand'];
		$manufacture		= $goods_addition['manufacture'];
		$orign				= $goods_addition['orgin'];
		$orgin				= $goods_addition['orgin'];*/

		$provider			= $this->providermodel->provider_goods_list();
		//$brand_title	= $this->brandmodel->get_brand_title();

		$sql 	= "select distinct A.title, A.category_code from fm_provider_charge A where A.provider_seq = '".$this->providerInfo['provider_seq']."' and A.title <> ''";
		$query 	= $this->db->query($sql);
		$sallerbrand = $query->result_array();
		$this->template->assign(array('brand'=>$brand,'model'=>$model,'manufacture'=>$manufacture,'orign'=>$orign,'provider'=>$provider,'sallerbrand'=>$sallerbrand));

		### 상품 검색 개선 추가 항목 start / leewh 2015-04-27

		// 등급혜택
		$sale_list = $this->membermodel->get_member_sale();
		$this->template->assign(array('sale_list'=>$sale_list));

		//추가정보의 모델명추출
		$defaultadditionsar = array("모델명","브랜드","제조사","원산지");//model, brand, manufacture, orgin
		$code_arr 			= $this->goodsmodel->get_goodsaddinfo('goodsaddinfo');		// 상품추가정보
		$goodscode			= array();
		foreach ($code_arr as $code_datarow){
			$code_datarow['label_write'] = get_labelitem_type($code_datarow,$goods,'');
			if( in_array(trim($code_datarow['label_title']), $defaultadditionsar,true) ){
				$code_datarow['label_title'] = $code_datarow['label_title'].' [코드]';
			}
			$goodscode[] = $code_datarow;
		}
		$this->template->assign('goodsaddinfoloop', $goodscode);

		// 할인이벤트 진행중 리스트
		$event_list = $this->eventmodel->get_event_ing_list();
		$this->template->assign('event_list', $event_list);

		//사은품이벤트 진행중 리스트
		$gift_list = $this->giftmodel->get_gift_ing_list();
		$this->template->assign('gift_list', $gift_list);

		// 아이콘 검색
		$tmp_goods_icon = code_load('goodsIcon');
		$r_goods_icon = array();
		foreach($tmp_goods_icon as $k=>$icon_data){
			$path = ROOTPATH."data/icon/goods/".$icon_data['codecd'].".gif";
			if(file_exists($path)) {
				$r_goods_icon[] = $icon_data;
			}
		}
		sort($r_goods_icon);
		$this->template->assign('r_goods_icon',$r_goods_icon);

		### 상품 검색 개선 추가 항목 end

		### PAGE & DATA
		/*
		$query = "select count(*) cnt from fm_goods A LEFT JOIN fm_goods_option B ON A.goods_seq = B.goods_seq LEFT JOIN fm_goods_supply C ON A.goods_seq = C.goods_seq AND B.option_seq = C.option_seq where B.default_option = 'y'";
		$query = $this->db->query($query);
		$data = $query->row_array();
		$loop['page']['all_count'] = $data['cnt'];
		*/

		$idx = 0;
		foreach($loop['record'] as $k => $datarow){
			$idx++;

			$datarow['goods_name'] = strip_tags($datarow['goods_name']);
			$datarow['goods_name_linkage'] = strip_tags($datarow['goods_name_linkage']);
			$datarow['summary'] = strip_tags($datarow['summary']);

			$datarow['goods_view_text']	= $datarow['goods_view']=='look' ? '노출' : '미노출';
			if	($datarow['provider_status']=='1'){
				$datarow['provider_status_text']	= "승인";
				if ($datarow['provider_status_reason']) unset($datarow['provider_status_reason']);
			}else{
				$datarow['provider_status_text']	= '미승인';

				if		($datarow['provider_status_reason_type'] == '1'){
					$datarow['provider_status_reason']	= '<span title="[자동] 최초 상품등록 후 아직 승인되지 않은 미승인 상품" class="help">최초등록</span>';
				}elseif	($datarow['provider_status_reason_type'] == '2'){
					$datarow['provider_status_reason']	= '<span title="[수동] 승인된 상품을 관리자(통신판매중계자)가 미승인한 상품" class="help">관리자</span>';
				}elseif	($datarow['provider_status_reason_type'] == '3'){
					$datarow['provider_status_reason']	= '<span title="[자동] 승인된 상품의 정보가 수정되어 미승인된 상품" class="help">정보수정</span>';
				}else{
					$datarow['provider_status_reason']	= '<span title="'.$datarow['provider_status_reason'].'" class="help">기타</span>';
				}
			}
			$datarow['number']		= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;

			$optstock = $this->goodsmodel->get_default_option($datarow['goods_seq']);

			$provider = $this->providermodel->get_provider_one($datarow['provider_seq']);
			$datarow['provider_name']	= $provider['provider_name'];
			$datarow['provider_id']		= $provider['provider_id'];

			$datarow['commission_rate']	= $optstock['commission_rate'];
			$datarow['option_seq']		= $optstock['option_seq'];
			$datarow['reserve_rate']	= $optstock['reserve_rate'];
			$datarow['reserve_unit']	= $optstock['reserve_unit'];
			$datarow['reserve']			= $optstock['reserve'];

			$datarow['consumer_price']	= $optstock['consumer_price'];
			$datarow['price']			= $optstock['price'];
			$datarow['supply_price']	= $optstock['supply_price'];
			$datarow['default_stock']	= $optstock['stock'];
			$datarow['default_badstock']= $optstock['badstock'];
			$datarow['default_safe_stock']		= $optstock['safe_stock'];
			$datarow['default_weight']			= $optstock['weight'];

			$optstocktot				= $this->goodsmodel->get_tot_option($datarow['goods_seq']);
			$datarow['stock']			= $optstocktot['stock'];
			$datarow['badstock']		= $optstocktot['badstock'];
			$datarow['safe_stock']			= $optstocktot['safe_stock'];
			$datarow['rstock']				= $optstocktot['rstock'];
			$datarow['a_stock']		= isset($optstocktot['a_stock']) ? $optstocktot['a_stock'] : ""; //가용재고 > 0 옵션 재고 합계
			$datarow['a_rstock']	= isset($optstocktot['a_rstock']) ? $optstocktot['a_rstock'] : ""; //가용재고 > 0 가용재고 합계
			$datarow['a_stock_cnt']	= isset($optstocktot['a_stock_cnt']) ? $optstocktot['a_stock_cnt'] : ""; //가용재고 > 0 해당옵션 갯수
			$datarow['b_stock']		= isset($optstocktot['b_stock']) ? $optstocktot['b_stock'] : ""; //가용재고 <= 0 옵션 재고 합계
			$datarow['b_rstock']	= isset($optstocktot['b_rstock']) ? $optstocktot['b_rstock'] : ""; //가용재고 <= 0 가용재고 합계
			$datarow['b_stock_cnt']	= isset($optstocktot['b_stock_cnt']) ? $optstocktot['b_stock_cnt'] : ""; //가용재고 <= 0 해당옵션 갯수
			$datarow['stocknothing']	= $optstocktot['stocknothing'];			//재고 0이하인 옵션갯수
			$datarow['rstocknothing']	= $optstocktot['rstocknothing'];		//가용재고 0이하인 옵션갯수

			$datarow['rtotal_supply_price']	= $optstocktot['rtotal_supply_price'];
			$datarow['rtotal_stock']			= $optstocktot['rtotal_stock'];
			$datarow['rtotal_badstock']		= $optstocktot['rtotal_badstock'];

			//$datarow['catename']	= $this->categorymodel->get_category_name($datarow['category_code']);
			//가용재고 삭제
			//$reservation = //$this->ordermodel->get_reservation_for_goods($cfg_order['ableStockStep'],$datarow['goods_seq']);
			//$datarow['rstock'] = $datarow['stock'] - $reservation;

			unset($videosc);
			$videosc['tmpcode']= $datarow['videotmpcode'];
			$videosc['upkind']= 'goods';
			$videosc['type']= 'contents';
			$videocontentfirst = $this->videofiles->get_data($videosc);
			if($videocontentfirst) {
				$datarow['video_content_file_key_w']= $videocontentfirst['file_key_w'];
				$datarow['video_content_viewer_use']= $videocontentfirst['viewer_use'];
			}

			$datarow['goods_status_stock_text']	= '<b>정상</b>';
			if($datarow['goods_status']=="runout"){
				$datarow['goods_status_stock_text']	= "품절";
			}
			$datarow['goods_status_text'] 		= $this->goodslibrary->get_goods_status($datarow['goods_status']);

			// 옵션
			if ($optionType == 'all')
				$datarow['options']		= $this->goodsmodel->get_goods_option($datarow['goods_seq']);
			else
				$datarow['options'][0]	= $optstock;

			// 최근 매입처
			if($datarow['provider_seq']=='1'){
				$datarow['lastest_supplier_name'] = $this->goodsmodel->get_supplier_name($datarow['goods_seq']);
			}

			if ($datarow['update_date']=="0000-00-00 00:00:00") {
				$datarow['update_date'] = "&nbsp;";
			}

			// 대표카테고리
			$category_default		= '';
				$cate				= $this->goodsmodel->get_goods_category_default($datarow['goods_seq']);
				$category_default	= $this->categorymodel->get_category_name($cate['category_code']);
			$datarow['category_default']	= $category_default;

			// 대표브랜드
			$brand_default			= '';
				$brand				= $this->goodsmodel->get_goods_brand_default($datarow['goods_seq']);
				$brand_default		= $brand['title'];
			$datarow['brand_default']	= $brand_default;


			$loop['record'][$k] = $datarow;
		}

		//$gd_search_arr = explode('&',$_COOKIE['goods_list_search']);
		$gd_search_arr = explode('&',$result['search_info']);
		foreach($gd_search_arr as $gd_search_data){
			$gd_search_arr2 = explode("=",$gd_search_data);

			if( strstr($gd_search_arr2[0],"goodsStatus") ){
				$gdsearchdefault['goodsStatus'][] = $gd_search_arr2[1];
			}elseif( strstr($gd_search_arr2[0],"goodsView") ){
				$gdsearchdefault['goodsView'][] = $gd_search_arr2[1];
			}elseif( strstr($gd_search_arr2[0],"taxView") ){
				$gdsearchdefault['taxView'][] = $gd_search_arr2[1];
			}elseif( strstr($gd_search_arr2[0],"provider_status_reason_type") ){
				$gdsearchdefault['provider_status_reason_type'][] = $gd_search_arr2[1];
			}elseif( strstr($gd_search_arr2[0],"openmarket") ){
				$gdsearchdefault['openmarket'][] = $gd_search_arr2[1];
			}else{
				if( preg_match('/\[/',$gd_search_arr2[0]) ){
					$key = explode('[',$gd_search_arr2[0]);
					$gdsearchdefault[$key[0]][ str_replace(']','',$key[1]) ] = $gd_search_arr2[1];
				}else{
					$gdsearchdefault[$gd_search_arr2[0]] = $gd_search_arr2[1];
				}
			}
		}
		$this->template->assign('gdsearchdefault',$gdsearchdefault);

		return array($loop,$sc,$sc['sort']);
	}

	public function get_goods_option(){
		$options	= $this->goodsmodel->get_goods_option($_POST['goods_seq']);
		$this->template->assign(array('options'=>$options));
		$file_path	= $this->template_path();
		$file_path = str_replace("get_goods_option.html","_get_goods_option.html",$file_path);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 필수옵션 가용재고 계산 20210121
	// 상품상세 > 판매상태 > '정상' 항목 클릭 시 호출되어 실제 판매가능한 가용재고 계산함.
	public function check_option_stock(){

		list($goods_type, $totablestock) = $this->goodslibrary->check_option_stock($this->input->get());

		// 판매가능 재고(가용재고) 수량 리턴
		echo json_encode(array('goodsType'=>$goods_type,'totablestock'=>$totablestock));
	}


	public function batch_option_view()
	{
		$no = $_GET['no'];
		$cfg_order = config_load('order');

		$file_path	= $this->template_path();

		$data_goods = $this->goodsmodel->get_goods($no);
		$data_option = $this->goodsmodel->get_goods_option($no);
		foreach($data_option as $k=>$data){
			$data['shipping_policy'] = $data_goods['shipping_policy'];
			$data['unlimit_shipping_price'] = $data_goods['unlimit_shipping_price'];
			$data['reserve_policy'] = $data_goods['reserve_policy'];
			$field = 'reservation'.$cfg_order['ableStockStep'];
			$data['able_stock'] = $data['stock'] - $data[$field];
			$data_option[$k]=$data;
		}
		$loop = $data_option;
		if($_GET['mode'] != 'view') {
			if ($_GET['mode']=='stock') {
				//재고/재고연동/상태/노출/승인 업데이트
				$file_path = str_replace('batch_option_view','batch_option_stock',$file_path);
			} else {
				$file_path = str_replace('batch_option_view','batch_option',$file_path);
			}
		}

		$this->template->assign('loop',$loop);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function batch_modify()
	{
		$cfg_order = config_load('order');
		$this->load->model('brandmodel');
		$this->load->model('locationmodel');
		if(!$cfg_order['ableStockStep']) $cfg_order['ableStockStep'] = 25;
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');

		if	(defined('__SELLERADMIN__') === true){
			$this->template->assign(array('selleradmin'=>true));
		}

		$mode = $this->input->get('mode');
		if(!$this->input->get('mode')) $mode = "ifgoods"; // 기본값 설정

		// 상품검색폼
		$this->template->define(array('goods_search_form' => $this->skin.'/goods/goods_search_form.html'));

		// 기본검색설정폼 분리 2015-05-04
		$this->template->define(array('set_search_default' => $this->skin.'/goods/_set_search_default_goods.html'));
		$this->template->assign(array('search_page'=>uri_string()));

		$this->template->assign(array('batchModify'=>true));
		$this->template->define(array('batch_modify_selector' => $this->skin.'/goods/_batch_modify_selector.html'));

		$_regist_popup_guide				= $this->skin."/goods/_regist_popup_guide.html";
		$this->template->define(array('_regist_popup_guide'=>$_regist_popup_guide));

		// 모드에 따른 필요값 정의 :: START 2016-11-18 lwh
		$optionMode	= 'default';
		switch($mode) {
			case	'goodsetc'		:
			case	'ifgoodsetc'	:
				$optionMode	= 'all';
				break;
			case	'price'			:
				$optionMode	= 'all';
				break;
			case	'ifprice'		:
				$optionMode	= 'all';
				break;
			case	'hscode'		:
				$check_function = "chk_hscode";
				break;
			case	'commoninfo'	:
				$check_function = "chk_commoninfo";
				break;
			case	'watermark'		:
				$config_watermark = config_load('watermark');
				if($config_watermark['watermark_position']!=''){
					$config_watermark['watermark_position'] = explode('|',$config_watermark['watermark_position']);
				}
				$file_path	= $this->template_path();
				$this->template->assign(array('config_watermark'=>$config_watermark));
				break;
			case	'shipping'		: // 배송그룹 업데이트
				$this->load->model("shippingmodel");
				$ship_list = $this->shippingmodel->get_shipping_group_simple($this->providerInfo['provider_seq']);
				$this->template->assign(array('provider_seq'=>$this->providerInfo['provider_seq']));
				$this->template->assign(array('provider_name'=>$this->providerInfo['provider_name']));
				$this->template->assign(array('ship_list'=>$ship_list));
				break;
			case 'multidiscount':
				$check_function = "chk_multidiscount";
				break;
			case 'status':
				$check_function = "chk_status";
				$this->template->assign(array('config_runout'=>$cfg_order['runout'],'config_ableStockLimit'=>$cfg_order['ableStockLimit']));
				break;
			case 'ifstatus':
				$check_function = "chk_ifstatus";
				$this->template->assign(array('config_runout'=>$cfg_order['runout'],'config_ableStockLimit'=>$cfg_order['ableStockLimit']));
				break;
			break;
			case 'goods'			:
			case 'ifgoods'			:
				$check_function = "chk_goods";
			break;
		}

		// 모드에 따른 필요값 정의 :: END
		$provider = $this->providermodel->get_provider($this->providerInfo['provider_seq']);
		$this->providerInfo['commission_type'] = $provider['commission_type'];
		$this->template->define(array('list_contents' => $this->skin.'/goods/_batch_modify_'.$mode.'.html'));

		### COMMON INFO
		$query2 = $this->db->query("select * from fm_goods_info where info_name != '' and info_provider_seq = '" . $this->providerInfo['provider_seq'] . "' order by info_seq desc");
		foreach($query2->result_array() as $v){
			$info_loop[] = $v;
		}
		$this->template->assign('info_loop',$info_loop);

		### 이미지 사이즈
		//http://redmine.firstmall.kr/issues/1091 에 의한 list 외 이미지도 설정가능토록 수정 2015-06-09 jhr
		$tmp = config_load('goodsImageSize');
		@asort($tmp);
		foreach($tmp as $k=>$v){
			$v['key'] = $k;
			$r_img_size[] = $v;
		}
		$this->template->assign('r_img_size',$r_img_size);

		### 상품아이콘
		$tmp_goods_icon = code_load('goodsIcon');
		foreach($tmp_goods_icon as $k=>$icon_data){
			$path = ROOTPATH."data/icon/goods/".$icon_data['codecd'].".gif";
			if(file_exists($path)) $r_goods_icon[] = $icon_data;
		}
		$this->template->assign('r_goods_icon',$r_goods_icon);

		## HSCODE
		if($_GET['mode'] == "hscode"){
			$this->load->model("multisupportmodel");
			$r_hscode = $this->multisupportmodel->get_hscode_list();
			$this->template->assign('r_hscode',$r_hscode);
		}

		$_GET['goods_kind'] = array('goods','coupon');

		$this->load->model('membermodel');
		$sale_list = $this->membermodel->get_member_sale();
		$this->template->assign(array('sale_list'=>$sale_list));

		// 회원등급세트
		$sale_data = $this->membermodel->get_member_sale_array();
		$sale_temp = array();
		foreach($sale_data['sale_list'] as $sale_list)
			$sale_temp[$sale_list['sale_seq']] = $sale_list['sale_title'];

		list($loop,$sc) =  $this->_goods_list($optionMode);

		## 상품 리스트 가공
		foreach($loop['record'] as $key=>$data){
			if( $_GET['mode'] == "goods" ||  $_GET['mode'] == "ifgoods" ) {
				$data['goods_name']	= htmlspecialchars($data['goods_name']);
				$data['summary']	= htmlspecialchars($data['summary']);

				// 추가검색어 추출 :: 2018-01-25 lkh
				$data['keyword']		= htmlspecialchars($data['keyword']);
				$keyword = $this->goodsmodel->set_search_keyword($data['goods_seq'],$data['goods_code'],$data['goods_name'],$data['summary'],$data['keyword']);
				$data['keyword'] = $keyword['keyword'];
			}

			if($_GET['mode'] == 'watermark'){
				$data['images'] = $this->goodsmodel->get_goods_image($data['goods_seq']);
				$data['cut_count'] = count($data['images']);

			}

			$data['color_pick_list']= ($data['color_pick'])? explode(",",$data['color_pick']): "" ;
			$data['stock'] 			= $data['default_stock'];
			$field 					= 'default_'.'reservation'.$cfg_order['ableStockStep'];
			$data['able_stock'] 	= $data['stock'] - $data[$field];
			$data['event'] 			= get_event_price($data['price'], $data['goods_seq'], $data['category_code'], $data['consumer_price'], $data);
			$data['event_seq'] 		= $data['event']['event_seq'];
			$data['icons'] 			= $this->goodsmodel->get_goods_icon($data['goods_seq'],1);
			$data['r_img_size'] 	= $r_img_size;
			//$data['info_loop']	= $info_loop;
			$data['goods_icon'] 	= $r_goods_icon;

			foreach($info_loop as $info_data){
				if($info_data['info_seq'] == $data['info_seq']){
					$data['info_name'] = $info_data['info_name'];
				}
			}

			if($_GET['mode'] == "category") {
				$r_category = $this->goodsmodel->get_goods_category($data['goods_seq']);
				foreach( $r_category as $k_category => $data_category){
					$r_category_code = $this->categorymodel->split_category($data_category['category_code']);
					$r_category_name = array();
					foreach( $r_category_code as $k_code => $code){
						$r_category_name[] = $this->categorymodel->one_category_name($code);
					}
					$data_category['category_name'] = $r_category_name;
					$r_category[$k_category] = $data_category;
				}
				$data['category'] = $r_category;

				$r_brand = $this->goodsmodel->get_goods_brand($data['goods_seq']);
				foreach( $r_brand as $k_brand => $data_brand){
					$r_brand_code = $this->brandmodel->split_brand($data_brand['category_code']);
					$r_brand_name = array();
					foreach( $r_brand_code as $k_code => $code){
						$r_brand_name[] = $this->brandmodel->one_brand_name($code);
					}
					$data_brand['brand_name'] = $r_brand_name;
					$r_brand[$k_brand] = $data_brand;
				}
				$data['brand'] = $r_brand;

				$r_location = $this->goodsmodel->get_goods_location($data['goods_seq']);
				foreach( $r_location as $k_location => $data_location){
					$r_location_code = $this->locationmodel->split_location($data_location['location_code']);
					$r_location_name = array();
					foreach( $r_location_code as $k_code => $code){
						$r_location_name[] = $this->locationmodel->one_location_name($code);
					}
					$data_location['location_name'] = $r_location_name;
					$r_location[$k_location] = $data_location;
				}
				$data['location'] = $r_location;
			}

			// 배송그룹 업데이트 - 배송그룹 정보 추출 :: 2016-11-21 lwh
			if	($_GET['mode'] == 'shipping' && $data['shipping_group_seq']){
				$ship_info = $this->shippingmodel->get_shipping_group($data['shipping_group_seq']);
				$data['shipping_group_name'] = $ship_info['shipping_group_name'];
			}

			$data['sale_title'] = $sale_temp[$data['sale_seq']];

			if (trim($data['multi_discount_policy']))
				$data['multi_discount_policy']	= json_decode($data['multi_discount_policy'], 1);
			else
				$data['multi_discount_policy']	= '';

			// hscode 품명 추가
			$data['hscode_name']	= '';
			if	($mode == 'hscode' && $data['hscode']){
				if	($r_hscode) foreach($r_hscode as $k => $hsData){
					if	($data['hscode'] == $hsData['hscode_common']){
						$data['hscode_name']	= $hsData['hscode_name'];
					}
				}
			}

			if	($mode == 'goodsetc'){
				$goods_code = goodscodemulti($data['goods_seq']);
				$data['tmp_goods_code'] = $goods_code[$data['goods_seq']];
			}
			$loop['record'][$key] = $data;
		}

		$shippingGroupList	= $this->shippingmodel->get_shipping_group_list();
		$ship_set_code		= $this->shippingmodel->ship_set_code; // 배송설정코드

		foreach((array)$shippingGroupList as $key =>$row) {

			// 국내배송 방법
			$methodText		= '배송방법 없음';
			foreach ($row['shipping_method_list']['korea'] as $mkey => $method) {

				$nowMethodText	= strip_tags($method['shipping_set_name']);

				if ($mkey == 0)
					$methodText	= $nowMethodText;
				else
					$methodText	.= ", {$nowMethodText}";

				if ($method['delivery_std_type'] == 'Y')
					$methodText	.= "<span class='blue'>[기본]</span>";

				if (trim($method['delivery_std_input']) != '') {
					$nowStdInput	= strip_tags($method['delivery_std_input']);
					$methodText		.= "<span class='red'>[{$nowStdInput}]</span>";
				}

			}

			$row['method_korea_text']	= $methodText;

			// 해외배송 방법
			$row['method_global_text']	= '배송방법 없음';

			$methodText		= '배송방법 없음';
			foreach ($row['shipping_method_list']['global'] as $mkey => $method) {
				$nowMethodText	= strip_tags($method['shipping_set_name']);
				if ($mkey == 0)
					$methodText	= $nowMethodText;
				else
					$methodText	.= ", {$nowMethodText}";

				if ($method['delivery_std_type'] == 'Y')
					$methodText	.= "<span class='blue'>[기본]</span>";

				if (trim($method['delivery_std_input']) != '') {
					$nowStdInput	= strip_tags($method['delivery_std_input']);
					$methodText		.= "<span class='red'>[{$nowStdInput}]</span>";
				}

			}

			// 판매자명 추출
			if(serviceLimit('H_AD')){
				$this->load->model('providermodel');
				$provider = $this->providermodel->get_provider_one($row['shipping_provider_seq']);
				$row['provider_name'] = '['.$provider['provider_name'].'] ';
			}

			$row['method_global_text']	= $methodText;


			$shippingGroupList[$key]	= $row;
		}

		$arr_common						= array();
		$arr_common['colorPickList']	= getSearchColorList();
		$arr_common['sale_list']		= $sale_data['sale_list'];
		$this->template->assign('arr_common',$arr_common);

		$batch = $this->goodslibrary->get_batchmodify('selleradmin');

		$ifdirect['hide'] = array('if'=>'hide','direct'=>'hide');
		foreach($batch as $if_direct => $selector) {
			foreach($selector as $key => $data) {
				$selected="";
				if( $key == $mode) {
					$ifdirect['selected'][$if_direct] = $selected = "selected";
					$ifdirect['hide'][$if_direct] = "";
				}
				$batchmodify_selector[$if_direct][$key] = array('text'=>$data,'selected'=>$selected);
			}
		}

		if(in_array($mode,array('shipping','icon','category','commoninfo'))) {
			$this->template->assign('diff_layout', true);
		}
		
		$this->template->assign(array('batchmodify_selector'=> $batchmodify_selector, 'jsbatchmodify'=>json_encode($batchmodify_selector)));
		$this->template->assign('ifdirect', $ifdirect);

		// 반응형 운영방식 추가 :: 2019-01-07 pjw
		$this->template->assign(array('operation_type' => $this->operation_type));

		$arr_gl_gooda_config 	= $this->goodslibrary->get_goods_config($auth,false,true);

		$this->template->assign('cfg_order',$cfg_order);
		$this->template->assign(array('mode' => $mode)); 
		$this->template->assign(array('providerInfo' => $this->providerInfo)); 
		$this->template->assign(array('check_function' => $check_function));
		$this->template->assign('shippingGroupList', $shippingGroupList);
		$this->template->assign(array('ship_set_code' => $ship_set_code));
		$this->template->assign('loop',$loop['record']);
		$this->template->assign('page',$loop['page']);
		$this->template->assign('search_yn',$loop['search_yn']);
		$this->template->assign(array('perpage'=>$_GET['perpage'],'orderby'=>$_GET['orderby']));
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc),'arr_gl_gooda_config'=>json_encode($arr_gl_gooda_config)));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	public function set_search_default(){
		$this->load->model('searchdefaultconfigmodel');
		$param = $_POST;
		$this->searchdefaultconfigmodel->set_search_default($param);
		$search_page = $_POST['search_page'];

		$callback = "parent.closeDialog('search_detail_dialog');parent.location.replace('/{$search_page}');";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function get_search_default(){
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

	public function set_favorite(){
		$this->db->where('goods_seq', $_GET['goods_seq']);
		$result = $this->db->update('fm_goods', array("favorite_chk"=>$_GET['status']));
		echo $result;
	}

	public function regist()
	{
		/*
		header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
		header('Pragma: no-cache'); // HTTP 1.0.
		header('Expires: 0'); // Proxies.
		*/

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('goods_act');
		
		if (!$auth) {
			pageBack($this->auth_msg);
			exit;
		}
		
		$this->admin_menu();
		$this->tempate_modules();

		$aGetParams = $this->input->get();
		$goods_seq 	= $aGetParams['no'];
		$no 		= $aGetParams['no'];
		$old 		= $aGetParams['old'];

		if(isset($goods_seq) ) {
			$goods = $this->goodsmodel->get_goods($goods_seq);
			if (!isset($goods['goods_seq'])) pageBack('존재하지 않는 상품입니다.');

			if($goods['provider_seq'] != $this->providerInfo['provider_seq']){
				pageBack("권한이 없습니다.");
				exit;
			}
		}

		/*
		열기/닫기 설정 및 상단 tab메뉴
		*/
		$goodsType = "goods";
		if(defined('SOCIALCPUSE') === true) $goodsType = "social";
		if($goods['package_yn'] == 'y' || $aGetParams['package_yn'] == 'y') $goodsType = 'package';
		if( defined('__SELLERADMIN__') === true )	$sellermode			= true;

		$tabmenu 	= $this->goodsmodel->admin_goods_regist_tab_list($goodsType);
		$bxOpenSet 	= $this->searchdefaultconfigmodel->get_search_config($goodsType);
		$this->template->assign('tabmenu',$tabmenu);
		$this->template->assign('bxOpenSetObj',$bxOpenSet);

		## 성인쇼핑몰 여부 체크 :: 2015-03-17 lwh
		$arrBasic = ($this->config_basic)?$this->config_basic:config_load('basic');
		$this->template->assign('operation',$arrBasic['operating']);

		## 성인 상품 설정 체크 :: 2015-03-17 lwh
		$realname 				= config_load('realname');
		$realname['adult_chk'] 	= "N";
		if( $realname['useRealname'] == 'Y' && $realname['realnameId'] && $realname['realnamePwd']) $realname['adult_chk'] = "Y";
		if( $realname['useRealnamephone_adult'] == 'Y' && $realname['realnamephoneSikey'] && $realname['realnamePhoneSipwd']) $realname['adult_chk'] = "Y";
		if( $realname['useIpin_adult'] == 'Y' && $realname['ipinSikey'] && $realname['ipinKeyString']) $realname['adult_chk'] = "Y";
		$this->template->assign('realname', $realname);

		## 상품 등록 관련 서브 
		$_regist_popup_guide				= $this->skin."/goods/_regist_popup_guide.html";
		$_regist_category_brands_location	= $this->skin."/goods/_regist_category_brands_location.html";
		$this->template->define(array(
									'_regist_popup_guide'=>$_regist_popup_guide,
									'_regist_category_brands_location'=>$_regist_category_brands_location
								));

		## 바코드 안내
		$this->template->define(array('barcodeInfo'=>$this->skin."/barcode/_barcode_info_popup.html"));

		$cfg_order = config_load('order');
		$this->load->model('membermodel');

		// 워터마크 설정
		$config_watermark = config_load('watermark');
		if($config_watermark['watermark_position']!=''){
			$config_watermark['watermark_position'] = explode('|',$config_watermark['watermark_position']);
		}
		$config_watermark['use_watermark'] = false;
		if($config_watermark['watermark_type'] == 'cross') $config_watermark['use_watermark'] = true;
		if($config_watermark['watermark_type'] == 'position' && count($config_watermark['watermark_position'])>0){
			$config_watermark['use_watermark'] = true;
		}
		if(!$config_watermark['watermark_image']) $config_watermark['use_watermark'] = false;
		$this->template->assign(array('config_watermark'=>$config_watermark));

		// 에디터 세팅
		$config_setting_editor = config_load('goods_contents_editor');
		$this->template->assign(array('config_setting_editor'=>$config_setting_editor));

		$provider_seq 		= $this->providerInfo['provider_seq'];
		$limit_stock 		= '';
		$totstock 			= 0;
		$reservation15 		= 0;
		$reservation25 		= 0;

		$this->template->define(array('tpl'=>str_replace("social_","",$this->template_path())));
		$this->template->assign('goodsImageSize',config_load('goodsImageSize'));
		$cfg_goods = config_load('goods');
		$cfg_goods['videototalcut']  	= 5;//5건까지만등록가능
		if( $cfg_goods['ucc_domain'] && $cfg_goods['ucc_key'] ){
			$cfg_goods['video_use'] 	=  'Y';
		}
		$this->template->assign('cfg_goods',$cfg_goods);

		$this->load->model('providermodel');
		$this->load->model('brandmodel');
		$this->load->model('categorymodel');
		$this->load->model('locationmodel');
		$this->load->model('membermodel');

		$tmp 						= config_load('reserve');
		$default_reserve_percent 	= $tmp['default_reserve_percent'];

		// 패키지 여부
		$package_yn	= 'n';
		if( $aGetParams['package_yn'] ) $package_yn	= $aGetParams['package_yn'];
		if( $goods['package_yn'] ) $package_yn	= $goods['package_yn'];

		$displayTermsEnd	= strtotime($goods['display_terms_end']);
		$displayTermsBegin	= strtotime($goods['display_terms_begin']);
		$todayStamp			= strtotime(date('Y-m-d'));
		//$todayStamp			= strtotime('2016-09-16');

		$goods['display_terms_close']			= ($displayTermsEnd < $todayStamp && $goods['display_terms'] == 'AUTO') ? 'Y' : 'N';
		$goods['display_terms_status']			= 'ING';

		if ($todayStamp < $displayTermsBegin)
			$goods['display_terms_status']		= 'BEFORE';
		else if ($todayStamp > $displayTermsEnd)
			$goods['display_terms_status']		= 'AFTER';

		$goods['display_terms_begin_before']	= date('Y-m-d', strtotime('-1 Day', $displayTermsBegin));
		$goods['display_terms_end_after']		= date('Y-m-d', strtotime('+1 Day', $displayTermsEnd));

		$goods['feed_ship_type_txt'] = $this->shippingmodel->ep_ship_type[$goods['feed_ship_type']];

		### COMMON INFO
		/*$query2 = $this->db->query("select * from fm_goods_info where info_name != '' and info_provider_seq = '" . $this->providerInfo['provider_seq'] . "' order by info_seq desc");
		foreach($query2->result_array() as $v){
			$info_loop[] = $v;
		}*/

		// 티켓일 경우 기본 티켓 배송그룹 추출
		if(defined('SOCIALCPUSE') === true){
			$shipping_tmp = $this->shippingmodel->get_shipping_group_simple($this->providerInfo['provider_seq'],'Y');
			$shipping_info = $shipping_tmp[0];
			$this->template->assign('shipping_info',$shipping_info);
		}else{
			// 상품일 경우 기본 배송그룹 추출
			if(!$goods_seq){
				$default_ship = $this->shippingmodel->get_shipping_base($this->providerInfo['provider_seq']);
				$goods['shipping_group_seq'] = $default_ship['shipping_group_seq'];
				$this->template->assign(array('goods'=>$goods));
			}
		}

		if( isset($goods_seq) ){

			$categories = $this->goodsmodel->get_goods_category($goods_seq);
			if($categories){
				foreach($categories as $key => $data) $categories[$key]['title'] = $this->categorymodel->get_category_name($data['category_code']);
			}
			
			$brands = $this->goodsmodel->get_goods_brand($goods_seq);
			if($brands){
				foreach($brands as $key => $data) $brands[$key]['title'] = $this->brandmodel->get_brand_name($data['category_code']);
			}
			$locations = $this->goodsmodel->get_goods_location($goods_seq);
			if($locations){
				foreach($locations as $key => $data) $locations[$key]['title'] = $this->locationmodel->get_location_name($data['location_code']);
			}

			if( $goods['goods_kind'] == 'coupon' && defined('SOCIALCPUSE') != true ){
				redirect("/selleradmin/goods/social_regist?no=".$goods_seq);
				exit;
			}
			$goods['title']			= strip_tags($goods['goods_name']);
			$goods['goods_name']	= htmlspecialchars($goods['goods_name']);
			$goods['goods_name_linkage']	= htmlspecialchars($goods['goods_name_linkage']);
			$goods['auto_summary']		= strip_tags($goods['summary']);
			$goods['summary']		= htmlspecialchars($goods['summary']);
			$goods['purchase_goods_name']	= htmlspecialchars($goods['purchase_goods_name']);
			// #19650 auto_keyword 제거
			$goods['keyword']		= htmlspecialchars($goods['keyword']);
			$goods['string_price']	= htmlspecialchars($goods['string_price']);
			$goods['sub_info_desc']	= json_decode($goods['sub_info_desc']);


			$video_size = explode("X" , $goods['video_size']);
			$goods['video_size0'] = $video_size[0];
			$goods['video_size1'] = $video_size[1];
			$video_size_mobile = explode("X" , $goods['video_size_mobile']);
			$goods['video_size_mobile0'] = $video_size_mobile[0];
			$goods['video_size_mobile1'] = $video_size_mobile[1];


			$i=0;
			foreach($goods['sub_info_desc'] as $key => $value){
				if($key != "_empty_" && $key != ""){
					$goods_sub['subInfo'][$i]["title"] = $key;
					$goods_sub['subInfo'][$i]["desc"] = $value;
					$i++;
				}
			}

			$goods['sub_info_desc'] = $goods_sub;

			if($goods['goods_status']=='normal'){
				$goods['goods_status_text'] = "정상";
			}else if($goods['goods_status']=='runout'){
				$goods['goods_status_text'] = "품절";
			}else if($goods['goods_status']=='purchasing'){
				$goods['goods_status_text'] = "재고확보중";
			}else{
				$goods['goods_status_text'] = "판매중지";
			}
			$goods['goods_view_text'] = $goods['goods_view']=='look' ? "노출" : "미노출";

			// 구매대상제한
			if($goods['string_price_use'] == 1 || 
					$goods['member_string_price_use'] == 1 || 
					$goods['allmember_string_price_use'] == 1 || 
					$goods['string_button_use'] == 1 || 
					$goods['member_string_button_use'] == 1 || 
					$goods['allmember_string_button_use'] == 1){
				$goods['stringPriceUse'] = 'y';
			}else{
				$goods['stringPriceUse'] = 'n';
			}

			// #19650 제거

			$goods['provider_status_text'] = $goods['provider_status']=='1' ? "<span class='bold blue'>승인</span>" : "<span class='bold red'>미승인</span>";
			$images		= $this->goodsmodel->get_goods_image($goods_seq);
			$additions	= $this->goodsmodel->get_goods_addition($goods_seq);
			$options	= $this->goodsmodel->get_goods_option($goods_seq, $limit);
			$suboptions = $this->goodsmodel->get_goods_suboption($goods_seq, $sublimit);
			$inputs		= $this->goodsmodel->get_goods_input($goods_seq);
			$icons		= $this->goodsmodel->get_goods_icon($goods_seq,'1');

			//티켓상품그룹
			if($goods['social_goods_group']){
				$this->load->model('socialgoodsgroupmodel');
				$goods['social_goods_group_data'] = $this->socialgoodsgroupmodel->get_data(array('select'=>' * ','group_seq'=>$goods['social_goods_group']));
			}

			//상품추가양식 정보
			unset($goodscode);
			$code_arr 	= $this->goodsmodel->get_goodsaddinfo();
			foreach ($code_arr as $code_datarow){
				$goodscode[] = $code_datarow;
			}

			//추가정보의 모델명추출
			$defaultadditionsar = array("모델명","브랜드","제조사","원산지");//model, brand, manufacture, orgin
			if($additions){
				foreach($additions as $data_additions){
					foreach ($goodscode as $key=>$gdcode_datarow){
						$goodscode[$key]['label_write'] = get_labelitem_type($gdcode_datarow,$data_additions,'');
						if( in_array($gdcode_datarow['label_title'], $defaultadditionsar,true) ){
							$goodscode[$key]['label_title'] = $gdcode_datarow['label_title'].' [코드]';
						}
					}
					$data_additions['goodsaddinfo'] = $goodscode;
					$newadditions[] = $data_additions;
				}
			}
			$additions = $newadditions;//다시정의함
			foreach($additions as $data_additions){
				if($data_additions['type'] == 'model' ){
					$goods['model_text'] =  $data_additions['contents'];
					break;
				}
			}

			// 지역정보 체크값 기본 N :: 2014-04-02 lwh
			$isAddr = 'N';

			// 총재고, 출고예약량
			foreach($options as $key_option =>  $data_option){
				$totstock += $data_option['stock'];
				$reservation15 += $data_option['reservation15'];
				$reservation25 += $data_option['reservation25'];
				if	($cfg_order['ableStockStep'] == 15){
					$totunUsableStock	+= $data_option['badstock'] + $data_option['reservation15'];
				}
				if	($cfg_order['ableStockStep'] == 25){
					$totunUsableStock	+= $data_option['badstock'] + $data_option['reservation25'];
				}

				// 기본정책일 경우 마일리지 표기
				if($goods['reserve_policy'] == 'shop'){
					$data_option['reserve_rate'] = $default_reserve_percent;
					$data_option['reserve_unit'] = 'percent';
					$data_option['reserve'] = floor($data_option['price'] * ($default_reserve_percent * 0.01));
					$options[$key_option] = $data_option;
				}else{
					if	($data_option['reserve_unit'] == 'percent')
						$data_option['reserve'] = floor($data_option['price'] * ($data_option['reserve_rate'] * 0.01));
					else
						$data_option['reserve']	= $data_option['reserve_rate'];
					$options[$key_option] = $data_option;
				}

				// 지역정보 체크 :: 2014-03-31 lwh
				if(in_array('address',$data_option['divide_newtype'])){
					if($data_option['address'])
						$isAddr = 'Y';
				}

				// 패키지
				$data_min = array();
				if($goods['package_yn'] == 'y'){
					// 패키지 에러 가져오기
					$params = array('type'=>'option','parent_seq'=>$data_option['option_seq']);
					foreach($this->errorpackage->get_error($params)->result_array() as $data_error){
						if($data_error['error_code']){
							$idx = substr($data_error['error_code'],1,1);
							$error_code = substr($data_error['error_code'],2,2);
							$data_option['package_error_code'.$idx] = $error_code;
						}
					}
					for($package_num = 1;$package_num < 6;$package_num++){
						$option_field = 'package_option'.$package_num;
						$option_seq_field = 'package_option_seq'.$package_num;
						$option_unit_field = 'package_unit_ea'.$package_num;

						if($data_option[$option_seq_field]){
							$data_package = $this->goodsmodel->get_package_by_option_seq($data_option[$option_seq_field]);
							$data_option['package_goods_seq'.$package_num] 		= $data_package['package_goods_seq'];
							$data_option['package_stock'.$package_num] 			= $data_package['package_stock'];
							$data_option['package_badstock'.$package_num] 		= $data_package['package_badstock'];
							$data_option['package_ablestock'.$package_num] 		= $data_package['package_ablestock'];
							$data_option['package_safe_stock'.$package_num] 	= $data_package['package_safe_stock'];
							$data_option['package_option_code'.$package_num] 	= $data_package['package_option_code'];
							$data_option['weight'.$package_num] 				= $data_package['weight'];

							//패키시 상품 정보 배열화 2020.08
							$data_option['package_error_code'][$package_num] 	= $data_option['package_error_code'.$package_num];
							$data_option['package_goods_seq'][$package_num] 	= $data_option['package_goods_seq'.$package_num];
							$data_option['package_stock'][$package_num] 		= $data_option['package_stock'.$package_num];
							$data_option['package_badstock'][$package_num] 		= $data_option['package_badstock'.$package_num];
							$data_option['package_ablestock'][$package_num] 	= $data_option['package_ablestock'.$package_num];
							$data_option['package_safe_stock'][$package_num] 	= $data_option['package_safe_stock'.$package_num];
							$data_option['package_option_code'][$package_num] 	= $data_option['package_option_code'.$package_num];
							$data_option['package_weight'][$package_num] 		= $data_option['weight'.$package_num];
							$data_option['package_goods_code'][$package_num] 	= $data_option['package_goods_code'.$package_num];
							$data_option['package_goods_name'][$package_num] 	= $data_option['package_goods_name'.$package_num];
							$data_option['package_option_seq'][$package_num] 	= $data_option['package_option_seq'.$package_num];
							$data_option['package_option'][$package_num] 		= $data_option['package_option'.$package_num];
							$data_option['package_unit_ea'][$package_num] 		= $data_option['package_unit_ea'.$package_num];

							$data_packages = $this->goodsmodel->get_package_stock($data_option[$option_seq_field],$data_option[$option_unit_field]);
							if($data_packages['option_seq'] && (empty($data_min) || $data_min['unit_stock'] > $data_packages['unit_stock'])) {
								$data_min = $data_packages;
								$data_min['unit_ea'] = $data_option[$option_unit_field];
							}
						}
					}
					if(!empty($data_min)) {
						$data_option['stock'] = $data_min['unit_stock'];
						$data_option['badstock'] = $data_min['unit_badstock'];
						$data_option['reservation15'] = $data_min['unit_reservation15'];
						$data_option['reservation25'] = $data_min['unit_reservation25'];
					}
				}

				$data_option['optioncode1']		= trim($data_option['optioncode1']);
				$data_option['optioncode2']		= trim($data_option['optioncode2']);
				$data_option['optioncode3']		= trim($data_option['optioncode3']);
				$data_option['optioncode4']		= trim($data_option['optioncode4']);
				$data_option['optioncode5']		= trim($data_option['optioncode5']);

				$data_option['optioncode']		= $data_option['optioncode1'].$data_option['optioncode2'].$data_option['optioncode3'].$data_option['optioncode4'].$data_option['optioncode5'];

				$options[$key_option] = $data_option;
			}

			// 총재고, 출고예약량
			if	($suboptions)foreach($suboptions as $key_suboption => $data_suboption){
				if	($data_suboption)foreach($data_suboption as $key_sub => $data_sub){

					$totsuboptionrowcnt++;
					$totstock += $data_sub['stock'];
					$reservation15 += $data_sub['reservation15'];
					$reservation25 += $data_sub['reservation25'];
					if	($cfg_order['ableStockStep'] == 15){
						$totunUsableStock	+= $data_sub['badstock'] + $data_sub['reservation15'];
					}
					if	($cfg_order['ableStockStep'] == 25){
						$totunUsableStock	+= $data_sub['badstock'] + $data_sub['reservation25'];
					}

					// 기본정책일 경우 마일리지 표기
					if($goods['sub_reserve_policy'] == 'shop'){
						$data_sub['reserve_rate']	= $default_reserve_percent;
						$data_sub['reserve_unit']	= 'percent';
						$data_sub['reserve']		= get_cutting_price($data_sub['price'] * ($default_reserve_percent * 0.01));
						$data_suboption[$key_sub]	= $data_sub;
					}else{
						if	($data_sub['reserve_unit'] == 'percent')
							$data_sub['reserve']	= get_cutting_price($data_sub['price'] * ($data_sub['reserve_rate'] * 0.01));
						else
							$data_sub['reserve']	= $data_sub['reserve_rate'];
						$data_suboption[$key_sub] 	= $data_sub;
					}

					// 패키지
					if( $data_sub['package_option_seq1'] ){
						// 패키지 에러 가져오기
						$params = array('type'=>'suboption','parent_seq'=>$data_sub['suboption_seq']);
						foreach($this->errorpackage->get_error($params)->result_array() as $data_error){
							if($data_error['error_code']){
								$idx = substr($data_error['error_code'],1,1);
								$error_code = substr($data_error['error_code'],2,2);
								$data_sub['package_error_code'.$idx] = $error_code;
							}
						}
						$data_package = $this->goodsmodel->get_package_by_option_seq($data_sub['package_option_seq1']);
						$data_sub['package_goods_seq1'] = $data_package['package_goods_seq'];
						$data_sub['package_stock1'] = $data_package['package_stock'];
						$data_sub['package_badstock1'] = $data_package['package_badstock'];
						$data_sub['package_ablestock1'] = $data_package['package_ablestock'];
						$data_sub['package_safe_stock1'] = $data_package['package_safe_stock'];
						$data_suboption[$key_sub] = $data_sub;
					}
				}
				$suboptions[$key_suboption]	= $data_suboption;
			}

			$this->template->assign(array('totstock'=>$totstock));
			$this->template->assign(array('totunUsableStock'=>$totunUsableStock));

			### 공용정보
			if	($goods['info_seq'] > 0 && !trim($goods['common_contents'])){
				$info = get_data("fm_goods_info",array("info_seq"=>$goods['info_seq']));
				$goods['common_contents'] = $info ? $info[0]['info_value'] : '';
			}

			// 배송정보 가져오기
			$delivery = $this->goodsmodel->get_goods_delivery($goods);
			if( $goods['goods_kind'] == 'coupon' ) {//티켓상품@2013-11-13
				$socialcpcancelar = $this->goodsmodel->get_goods_socialcpcancel($goods_seq);
				if( $goods['socialcp_cancel_type'] !='payoption' ) {
					$goods['socialcp_cancel_day0'] = $socialcpcancelar[0]['socialcp_cancel_day'];
				}else{
					foreach($socialcpcancelar as $socialcpcancel) {
							$socialcpcancels[] = $socialcpcancel;
					}
					$this->template->assign(array('socialcpcancels'=>$socialcpcancels));
				}

			}

			//
			if	($goods['coupon_serial_type'] == 'n'){
				$coupon_serial_data	= $this->goodsmodel->get_outcoupon_list($goods['goods_seq']);
				if	($coupon_serial_data)foreach($coupon_serial_data as $k => $coupon_data){
					$coupon_serial_tcnt++;
					if	($coupon_serial_tcnt > 1) $coupon_serial_str	.= ',';
					$coupon_serial_str	.= $coupon_data['coupon_serial'].'|a|'. $coupon_data['export_code'];

					if	($coupon_data['export_code'])	$coupon_serial_ecnt++;
				}

				$goods['coupon_serial_tcnt']	= $coupon_serial_tcnt;
				$goods['coupon_serial_ecnt']	= $coupon_serial_ecnt;
				$goods['coupon_serial_str']		= $coupon_serial_str;
			}

			// 배송그룹 정보 추출 :: 2016-07-01 lwh
			if($goods['shipping_group_seq']){
				$shipping_info = $this->shippingmodel->get_shipping_group($goods['shipping_group_seq']);

				$shipping_info['calcul_type_txt'] = $this->shippingmodel->calcul_type_txt[$shipping_info['shipping_calcul_type']];

				$this->template->assign('shipping_info',$shipping_info);
			}

			$this->template->assign(array('default_reserve_percent'=>$default_reserve_percent));
			$this->template->assign(array('categories'=>$categories));
			$this->template->assign(array('brands'=>$brands));
			$this->template->assign(array('locations'=>$locations));
			$this->template->assign(array('goods'=>$goods));
			$this->template->assign(array('options'=>$options));
			$this->template->assign(array('isAddr'=>$isAddr));
			$this->template->assign('opts_loop',$options);
			$this->template->assign(array('icons'=>$icons));
			$this->template->assign(array('suboptions'=>$suboptions));
			$this->template->assign(array('totsuboptionrowcnt'=>$totsuboptionrowcnt));
			$this->template->assign('sopts_loop',$suboptions);
			$this->template->assign(array('inputs'=>$inputs));
			$this->template->assign(array('images'=>$images));
			$this->template->assign(array('delivery'=>$delivery));
			$this->template->assign(array('service_code'=>$this->config_system['service']['code']));

			### 관련상품 리스트
			$relation = $this->goodsmodel->get_goods_relation($goods_seq,'relation');
			$relation_seller = $this->goodsmodel->get_goods_relation($goods_seq,'relation_seller');

			if($relation) $this->template->assign('relation',$relation);
			if($relation_seller) $this->template->assign('relation_seller',$relation_seller);

		}
		
		$frequentlyinplist = $this->goodsmodel->frequentlygoods('inp',$goods_seq,defined('SOCIALCPUSE'));
		$this->template->assign(array('frequentlyinplist'=>$frequentlyinplist));

		// 구매대상제한
		$string_price_arr = array('string_price','member_string_price','allmember_string_price','string_button','member_string_button','allmember_string_button');
		$goodsStringPrice = array();
		foreach($string_price_arr as $_string){
			$goodsStringPrice[$_string] 				= $goods[$_string];
			$goodsStringPrice[$_string.'_use'] 			= $goods[$_string.'_use'];
			$goodsStringPrice[$_string.'_color'] 		= $goods[$_string.'_color'];
			$goodsStringPrice[$_string.'_link'] 		= $goods[$_string.'_link'];
			$goodsStringPrice[$_string.'_link_url'] 	= $goods[$_string.'_link_url'];
			$goodsStringPrice[$_string.'_link_target'] 	= $goods[$_string.'_link_target'];
		}
		$this->template->assign('goodsStringPrice',$goodsStringPrice);
		
		//상품코드양식 정보
		$this->load->helper("goods");
		$gdtypearray = array("goodsaddinfo","goodsoption","goodssuboption");
		foreach($gdtypearray as $gdtype){
			unset($goodscode);
			//추가정보의 모델명추출
			$defaultadditionsar = array("모델명","브랜드","제조사","원산지");//model, brand, manufacture, orgin
			$code_arr 			= $this->goodsmodel->get_goodsaddinfo($gdtype);
			foreach ($code_arr as $code_datarow){
				$code_datarow['label_write'] = get_labelitem_type($code_datarow,$goods,'');
				$i= 0;
				if($gdtype != 'goodsaddinfo' ){
					$label_value_ar = explode("|", $code_datarow['label_value']);
					$label_code_ar = explode("|", $code_datarow['label_code']);
					$label_default_ar = explode("|", $code_datarow['label_default']);
					foreach($label_code_ar as $code) {if(empty($code))continue;

						$codear['code'] = $code;
						$codear['value'] = $label_value_ar[$i];
						$codear['default'] = $label_default_ar[$i];
						$code_datarow['label_code_ar'][] = $codear;
						$i++;
					}
				}
				if( in_array(trim($code_datarow['label_title']), $defaultadditionsar,true) ){
					$code_datarow['label_title'] = $code_datarow['label_title'].' [코드]';
				}
				$goodscode[] = $code_datarow;
			}
			$this->template->assign($gdtype.'loop', $goodscode);
		}

		if($goods['videotmpcode']){
			$this->session->set_userdata('videotmpcode',$goods['videotmpcode']);
		}else{
			$videotmpcode = substr(microtime(), 2, 8);
			$this->session->set_userdata('videotmpcode',$videotmpcode);
		}
		$this->template->assign('videotmpcode',$this->session->userdata('videotmpcode'));

		/* 등록시 */
		if( !isset($goods_seq) ){
			$provider_seq = $this->providerInfo['provider_seq'];
		}
		/* 수정시 */
		if( isset($goods_seq) ){
			if($this->providerInfo['provider_seq'] != $provider_seq){
				pageBack("타 입점사의 상품입니다.");
				exit;
			}
		}

		//동영상관리
		$this->load->model('videofiles');
		unset($videosc);
		$videosc['tmpcode']		= $this->session->userdata('videotmpcode');
		$videosc['upkind']		= 'goods';
		$videosc['type']		= 'image';
		$videoimage = $this->videofiles->get_data($videosc);
		if($videoimage) $this->template->assign('videoimage',$videoimage);

		unset($videosc);
		$videosc['tmpcode']		= $this->session->userdata('videotmpcode');
		$videosc['upkind']		= 'goods';
		$videosc['type']		= 'contents';
		$videosc['orderby']		= 'sort ';
		$videosc['sort']		= 'asc, seq desc ';
		$goodsvideofiles 		= $this->videofiles->videofiles_list_all($videosc);
		if($goodsvideofiles['result']) $this->template->assign('goodsvideofiles',$goodsvideofiles['result']);

		###
		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		$point_text = "";
		if($reserves['point_use']=='Y'){
			switch($reserves['default_point_type']){
				case "per":
					$point_text = "※ 지급 포인트(P) ".$reserves['default_point_percent']."%";
					break;
				case "app":
					$point_text = "※ 지급 포인트(P) ".get_currency_price($reserves['default_point_app'],'basic',1)."당 ".$reserves['default_point']."포인트";
					break;
				default :
					$point_text = "";
					break;
			}
		}else{
			$point_text = "※ 지급 포인트(P) 없음";
		}
		$this->template->assign(array('point_text'=>$point_text));

		//상품코드 설정여부
		$gdtypearray 			= array("goodsaddinfo","goodsoption","goodssuboption");
		$goodscodesettingview	= '';
		$wheres 				= array("codesetting=1");
		foreach($gdtypearray as $gdtype){
			unset($goodscode);
			$user_arr 			= $this->goodsmodel->get_goodsaddinfo($gdtype,$wheres);
			foreach ($user_arr as $datarow){
				$goodscodesettingview .= $datarow['label_title'].' + ';
			}
		}
		$this->template->assign('goodscodesettingview',$goodscodesettingview);

		// 상품개별 재고 설정로드
		if($goods['runout_policy']){
			$cfg_runout['runout'] = $goods['runout_policy'];
			$cfg_runout['ableStockLimit'] = $goods['able_stock_limit'];
		}else{
			$cfg_runout['runout'] = $cfg_order['runout'];
			$cfg_runout['ableStockLimit'] = $cfg_order['ableStockLimit'];
		}
		$this->template->assign($cfg_runout);


		# 입점사 정산 방식 및 정산 수수료
		$query 		= $this->providermodel->get_provider_charge($provider_seq, '','','c.*');
		$chargedata = $query->row_array();
		list($chargedata['charge_text'],$chargedata['commission_text']) = $this->providermodel->get_commission_type($chargedata['charge'],$chargedata['commission_type']);
		$this->template->assign("default_charge",$chargedata);

		$provider = $this->providermodel->get_provider($provider_seq);
		$provider_charge = $this->providermodel->provider_charge_list($provider_seq);
		$provider_list		= $this->providermodel->provider_goods_list();
		$this->template->assign(array('provider_seq'=>$provider_seq));
		$this->template->assign(array('provider'=>$provider));
		$this->template->assign(array('provider_charge'=>$provider_charge));
		$this->template->assign(array('provider_list'=>$provider_list));

		### PROVIDER SHIPPING
		$shipping_provider_seq = $provider['deli_group']=='company' ? 1 : $provider_seq;
		$this->template->assign(array('shipping_provider_seq'=>$shipping_provider_seq));
		if($shipping_provider_seq>1){
			$sql = "select * from fm_provider_shipping where provider_seq = '{$shipping_provider_seq}'";
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

			$this->template->assign('shipping',$shipping[0]);
		}

		### 품절 기준 수량
		$cfg_order = config_load('order');
		$ableStockLimit = $cfg_order['ableStockLimit'];
		if($cfg_order['runout']=='ableStock'){
			if($cfg_order['ableStockStep'] == 15){
				$limit_stock = $totstock - $reservation15 - $ableStockLimit;
			}else{
				$limit_stock = $totstock - $reservation25 - $ableStockLimit;
			}
		}else if($cfg_order['runout']=='stock'){
			$limit_stock = 0;
		}else{
			$limit_stock = 'unlimited';
		}

		### 상품 사진 사이즈 조건
		list($goodsImageSizeArr,$r_img_size) = $this->goodsmodel->get_goodsImageSize();

		$icon_loop = code_load('goodsIcon');
		$this->template->assign('icon_default',$icon_loop[0]);

		### PROVIDER SHIPPING
		$shipping_provider_seq = $provider_seq;
		$this->template->assign(array('shipping_provider_seq'=>$shipping_provider_seq));

		$this->load->model('providershipping');
		$data_providershipping = $this->providershipping->get_provider_shipping($shipping_provider_seq);
		$this->template->assign("data_providershipping",$data_providershipping);

		// 옵션 기본 노출 수량 적용
		$config_goods	= config_load('goods');

		// 옵션 기본 노출 수량 적용 개선 leewh 2015-04-24
		$cfg_goods_default = array();

		$gkind = 'goods';
		if (defined('SOCIALCPUSE') === true) {
			$gkind = 'coupon';
		}

		if ($package_yn === 'y') {
			$gkind='package_'.$gkind;
		}

		$result = $this->goodsmodel->get_goods_default_config($gkind);
		if ($result) {
			$cfg_goods_default = $result;
		} else {
			if (isset($config_goods['option_view_count'])) {
				$cfg_goods_default['option_view_count'] = $config_goods['option_view_count'];
			}
			if (isset($config_goods['suboption_view_count'])) {
				$cfg_goods_default['suboption_view_count'] = $config_goods['suboption_view_count'];
			}
		}

		// 공용정보 기본설정값이 있을 경우 공용정보 표시
		if (!isset($goods_seq) && $cfg_goods_default['common_info_seq'] > 0) {
			$info = get_data("fm_goods_info",array("info_seq"=>$cfg_goods_default['common_info_seq']));
			$cfg_goods_default['common_contents'] = $info ? $info[0]['info_value'] : '';
		}

		if(!$cfg_goods_default){
			$cfg_goods_default = array('goods_kind' => $gkind);
		}

		// 패키지
		$package_count = 2;
		if($options[0]['package_count']){
			$package_count = $options[0]['package_count'];
		}
		$package_yn_suboption	= 'n';
		if( $goods['package_yn_suboption'] ) $package_yn_suboption	= $goods['package_yn_suboption'];
		else $package_yn_suboption = $package_yn;

		$this->template->assign(array('package_yn'=>$package_yn));
		$this->template->assign(array('package_yn_suboption'=>$package_yn_suboption));
		$this->template->assign(array('package_count'=>$package_count));

		$this->load->helper('admin');
		$cfg_system		= ($this->config_system) ? $this->config_system : config_load('system');
		$setting_date	= date('Ymd', strtotime($cfg_system['service']['setting_date']));

		$option_select_layout_notice	= '';
		if	($setting_date < '20150713'){
			$option_select_layout_notice	= getGabiaPannel('option_select_layout_notice_provider');
		}

		if	($this->scm_cfg['use'] == 'Y' && $_GET['no'] > 0){
			$this->load->model('scmmodel');
			$scmTotalStock			= $this->scmmodel->get_goods_total_stock($_GET['no']);
			$scmDefaultSupplyInfo	= $this->scmmodel->get_default_supply_goods_info($_GET['no']);
		}

		//상품정보고시 품목별 그룹 @2017-02-20
		$goods_subinfo_group = $this->goodsmodel->get_goods_sub_info_group();
		$this->template->assign(array('goods_subinfo_group'=>$goods_subinfo_group));

		$templatePath	= str_replace('social_', '', $this->template_path());
		$this->template->define(array('OPTION_HTML' => str_replace('regist.html', '_option_for_regist.html', $templatePath)));
		$this->template->define(array('SUBOPTION_HTML' => str_replace('regist.html', '_suboption_for_regist.html', $templatePath)));
		$this->template->define(array('SOCIAL_HTML' => str_replace('regist.html', '_social_for_regist.html', $templatePath)));

		// 포인트 & 적립금 제한일자 가져오기
		$reserves = config_load('reserve');

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


		// "회원 등급" 혜택 정보 가져오기
		$promotion_grade_categorys = array();

		if(is_array($categories)) {
			foreach($categories as $v) {
				$promotion_grade_categorys[] = $v['category_code'];
			}
		}

		if(!$goods['sale_seq']) $goods['sale_seq'] = 1;
		$promotion_grade = $this->get_promotion_grade($goods['sale_seq'], $goods['goods_seq'], $promotion_grade_categorys);
		$this->template->assign(array('promotion_grade' => $promotion_grade));

		// "모바일" 혜택 정보 가져오기
		$this->load->model('configsalemodel');
		$systemmobiles = $this->configsalemodel->lists(array('type'=>'mobile'));

		if(is_array($systemmobiles['result'])) {
			foreach($systemmobiles['result'] as $k=>$v) {
				$systemmobiles['result'][$k]['price1'] = get_currency_price($v['price1'], 3);
				$systemmobiles['result'][$k]['price2'] = get_currency_price($v['price2'], 3);
			}
		}

		$this->template->assign("systemmobiles", $systemmobiles['result']);

		// "좋아요" 정보 가져오기
		$this->load->model('configsalemodel');
		$systemfblike = $this->configsalemodel->lists(array('type'=>'fblike'));

		if(is_array($systemfblike['result'])) {
			foreach($systemfblike['result'] as $k=>$v) {
				$systemfblike['result'][$k]['price1'] = get_currency_price($v['price1'], 3);
				$systemfblike['result'][$k]['price2'] = get_currency_price($v['price2'], 3);
			}
		}

		$this->template->assign('systemfblike',$systemfblike['result']);

		// "할인혜택" 정보 가져오기
		$category_codes = array();

		if(is_array($categories)) {
			foreach($categories as $cRow) {
				$category_codes[] = $cRow['category_code'];
			}
		}

		$this->load->model('eventmodel');
		$eventRows = $this->eventmodel->get_discount_event_list($goods_seq, $category_codes);
		if(is_array($eventRows)) {
			foreach($eventRows as $k=>$v) {
				if(is_array($v)) {
					foreach($v as $ek=>$ev) {
						if ($ek != 'common') {
							// 이벤트 대상 입점사 리스트 체크
							$in_provider_list = array_values(array_filter(explode('|', $ev['provider_list'])));

							$provider_match = false;
							if ($in_provider_list) {
								foreach($in_provider_list as $ps) {
									if($ps == $provider_seq) {
										$provider_match = true;
										continue;
									}
								}
							} else {
								if ($provider_seq == 1){
									$provider_match = true;
								}
							}

							if (!$provider_match) {
								unset($eventRows[$k]);
								continue;
							}
						}

						# reset
						$weekday = array();

						# "요일" 치환
						strstr($ev['weekday'], '1') && $weekday[] = '월';
						strstr($ev['weekday'], '2') && $weekday[] = '화';
						strstr($ev['weekday'], '3') && $weekday[] = '수';
						strstr($ev['weekday'], '4') && $weekday[] = '목';
						strstr($ev['weekday'], '5') && $weekday[] = '금';
						strstr($ev['weekday'], '6') && $weekday[] = '토';
						strstr($ev['weekday'], '7') && $weekday[] = '일';

						# custom
						if($ek == 'common') {
							$eventRows[$k]['common']['start_date']			= substr($ev['start_date'], 0, 10);
							$eventRows[$k]['common']['end_date']			= substr($ev['end_date'], 0, 10);
							$eventRows[$k]['common']['weekday']			= implode(', ', $weekday);
							$eventRows[$k]['common']['app_start_time']	= substr($ev['app_start_time'], 0, 2);
							$eventRows[$k]['common']['app_end_time']	= substr($ev['app_end_time'], 0, 2);
							$eventRows[$k]['common']['currency']			= $this->config_system['basic_currency'];
						}
					}
				}
			}
		}

		$this->template->assign("discount_event_list",$eventRows);


		## HSCODE
		$this->load->model("multisupportmodel");
		$r_hscode = $this->multisupportmodel->get_hscode_list();
		$this->template->assign('r_hscode',$r_hscode);
		# 상품의 HSCODE정보 @2016-10-27
		if($goods['hscode']){
			$hscode_info	= $this->multisupportmodel->get_hscode_info($goods['hscode']);
			$this->template->assign('hscode',$hscode_info);
		}

		//검색용 색상리스트(생상코드)
		$goodsColorPick	= getSearchColorList($goods['color_pick']);

		//JS분리용
		$jsObjectVal['sellerMode']					= 'SELLER';
		$jsObjectVal['goods_seq']					= $goods['goods_seq'];
		$jsObjectVal['tax']							= $goods['tax'];
		$jsObjectVal['reserve_policy']				= $goods['reserve_policy'];
		$jsObjectVal['runout_policy']				= $goods['runout_policy'];
		$jsObjectVal['able_stock_limit']			= $goods['able_stock_limit'];
		$jsObjectVal['suboption_layout_group']		= $goods['suboption_layout_group'];
		$jsObjectVal['suboption_layout_position']	= $goods['suboption_layout_position'];
		$jsObjectVal['inputoption_layout_group']	= $goods['inputoption_layout_group'];
		$jsObjectVal['inputoption_layout_position']	= $goods['inputoption_layout_position'];
		$jsObjectVal['file_key_w']					= $goods['file_key_w'];
		$jsObjectVal['videototal']					= $goods['videototal'];
		$jsObjectVal['view_layout']					= $goods['view_layout'];
		$jsObjectVal['goods_status']				= $goods['goods_status'];
		$jsObjectVal['goods_view']					= $goods['goods_view'];
		$jsObjectVal['provider_status']				= $goods['provider_status'];
		$jsObjectVal['string_price_use']			= $goods['string_price_use'];
		$jsObjectVal['multi_discount_use']			= $goods['multi_discount_use'];
		$jsObjectVal['multi_discount_unit']			= $goods['multi_discount_unit'];
		$jsObjectVal['min_purchase_limit']			= $goods['min_purchase_limit'];
		$jsObjectVal['max_purchase_limit']			= $goods['max_purchase_limit'];
		$jsObjectVal['max_purchase_order_limit']	= $goods['max_purchase_order_limit'];
		$jsObjectVal['member_input_use']			= $goods['member_input_use'];
		$jsObjectVal['shipping_policy']				= $goods['shipping_policy'];
		$jsObjectVal['goods_shipping_policy']		= $goods['goods_shipping_policy'];
		$jsObjectVal['shipping_weight_policy']		= $goods['shipping_weight_policy'];
		$jsObjectVal['info_seq']					= $goods['info_seq'];
		$jsObjectVal['common_info_seq']				= $goods['common_info_seq'];
		$jsObjectVal['editor_view']					= $goods['editor_view'];
		$jsObjectVal['relation_type']				= $goods['relation_type'];
		$jsObjectVal['relation_seller_type']		= $goods['relation_seller_type'];
		$jsObjectVal['editor_view']					= $goods['editor_view'];
		$jsObjectVal['relation_type']				= $goods['relation_type'];
		$jsObjectVal['individual_refund']			= $goods['individual_refund'];
		$jsObjectVal['individual_refund_inherit']	= $goods['individual_refund_inherit'];
		$jsObjectVal['individual_export']			= $goods['individual_export'];
		$jsObjectVal['individual_return']			= $goods['individual_return'];
		$jsObjectVal['goods_name_linkage']			= $goods['goods_name_linkage'];
		$jsObjectVal['display_terms']				= ($goods['display_terms'] == 'AUTO') ? 'AUTO' : 'MENUAL';
		$jsObjectVal['display_terms_close']			= $goods['display_terms_close'];

		$jsObjectVal['goods_image_size']			= $goodsImageSizeArr;
		$jsObjectVal['imageLabel']					= $images[1]['large']['label'];
		$jsObjectVal['inputs']						= $inputs;
		$jsObjectVal['goods_uccdomain']				= uccdomain('fileswf',$goods['file_key_w']);
		$jsObjectVal['cfg_goods']					= $cfg_goods_default;
		$jsObjectVal['isAddr']						= $isAddr;
		$jsObjectVal['linkage_service']				= $LINKAGE_SERVICE;
		$jsObjectVal['adult_chk']					= $realname['adult_chk'];
		$jsObjectVal['iconCount']					= (is_array($icons) === true) ? count($icons) : 0;
		$jsObjectVal['additionCount']				= (is_array($additions) === true) ? count($additions) : 0;
		$jsObjectVal['imagesCount']					= (is_array($images) === true) ? count($images) : 0;
		$jsObjectVal['shipping_group_seq']			= $goods['shipping_group_seq'];
		$jsObjectVal['possible_pay_type']			= $goods['possible_pay_type'];
		$jsObjectVal['display_terms_type']			= $goods['display_terms_type'];
		$jsObjectVal['option_use']					= $goods['option_use'];
		$jsObjectVal['optsCnt']						= count($options);
		$jsObjectVal['option_view_type']			= $goods['option_view_type'];
		$jsObjectVal['reserve_policy']				= $goods['reserve_policy'];
		$jsObjectVal['reserve_policy']				= $goods['reserve_policy'];
		$jsObjectVal['mobile_contents_copy']		= $goods['mobile_contents_copy'];
		$jsObjectVal['goods_sub_info']				= $goods['goods_sub_info'];
		$jsObjectVal['feed_evt_sdate']				= $goods['feed_evt_sdate'];
		$jsObjectVal['feed_evt_edate']				= $goods['feed_evt_edate'];
		$jsObjectVal['locationUse']					= (count($locations) > 0)? 'y':'n';

		$jsObjectJson								= json_encode($jsObjectVal);
		//$hscodeTypeJson								= json_encode($hscodeTypeList);

		$traffic	= ($this->config_system['service']['traffic'] == '무제한') ? 'unlimit' : 'limit';

		# 기본통화기준의 KRW(원화) 환율(admin-goodsRegist.js 에서 정산금액 계산시 사용) @2016-11-04
		$currency_info	= get_currency_info("KRW");
		$this->template->assign('krw_exchange_rate', $currency_info['currency_exchange']);

		# 네이버
		$navercheckout = config_load('navercheckout');
		$this->template->assign(array("navercheckout"=>$navercheckout));

		# 입점마케팅 정보 추출
		$feed_info			= config_load('marketing_feed'); // 통합설정 호출
		$feed_image			= config_load('marketing_image'); // 전달 이미지 설정 호출
		$feed_sale			= config_load('marketing_sale'); // 입점마케팅 상품 추가할인
		$feed_sale_txt = '할인가';
		if($feed_sale['member'] == 'Y')		$feed_sale_txt .= ' - 회원할인';
		if($feed_sale['referer'] == 'Y')	$feed_sale_txt .= ' - 유입경로할인';
		if($feed_sale['coupon'] == 'Y')		$feed_sale_txt .= ' - 쿠폰할인';
		if($feed_sale['mobile'] == 'Y')		$feed_sale_txt .= ' - 모바일할인';
		$feed_info['image'] = $feed_image;
		$feed_info['sale']	= $feed_sale_txt;
		$this->template->assign(array("feed_info"=>$feed_info));

		# 오픈마켓
		$this->load->model('connectormodel');
		$MarketConnectorClause	= config_load('MarketConnectorClause');
		$MarketLinkage	= config_load('MarketLinkage');

		if($MarketLinkage['shopCode'] == "firstmall"){
			$useMarketList	= $this->connectormodel->getUseMarketList();
		}else{
			$useMarketList	= $this->connectormodel->getUseShoplinkerMarketList();
		}
		//useMarketList empty 로 TRUE, FALSE 체크
		$useMarket = !empty($useMarketList) ? true : false;

		//$this->template->assign('hscodeTypeList', $hscodeTypeJson);
		//$this->template->assign('goodsHscodeInfo', $goodsHscodeInfo);
		$this->template->assign('nationsInfo', $nationsInfo);
		$this->template->assign('goodsObj', $jsObjectJson);
		$this->template->assign('traffic', $traffic);
		$this->template->assign(array(
									'goodsColorPick'=>$goodsColorPick,
									'sellermode'		=> $sellermode)
								);
		$this->template->assign(array('scm_cfg'=>$this->scm_cfg));
		$this->template->assign(array('scmTotalStock'=>$scmTotalStock));
		$this->template->assign(array('scmDefaultSupplyInfo'=>$scmDefaultSupplyInfo));
		$this->template->assign(array('option_select_layout_notice'=>$option_select_layout_notice));
		$this->template->assign(array('config_goods' => $cfg_goods_default));
		$this->template->assign(array('additions'=>$additions));
		$this->template->assign('query_string',$_GET['query_string']);
		$this->template->assign(array('r_img_size'=>$r_img_size));
		//$this->template->assign('info_loop',$info_loop);
		$this->template->assign(array('default_reserve_percent'=>$default_reserve_percent));
		$this->template->assign(array('last_categories'=>$last_categories));
		$this->template->assign(array('last_brands'=>$last_brands));
		$this->template->assign(array('last_locations'=>$last_locations));
		$this->template->assign(array('limit_stock'=>$limit_stock));
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign(array('MarketConnectorClause'=>$MarketConnectorClause));
		$this->template->assign(array('useMarket'=>$useMarket));
		$this->template->print_("tpl");

		## 연결오류 알림 메시지
		if( isset($goods_seq) ){
			if($goods['package_yn'] == 'y'){
				$query_err = $this->errorpackage->get_last_error(array('goods_seq'=>$goods['goods_seq'],'type'=>'option'));
				$data_err_opt = $query_err->row_array();
			}
			if($goods['package_yn_suboption'] == 'y' && $goods['option_suboption_use'] == '1' && $suboptions) {
				$query_err = $this->errorpackage->get_last_error(array('goods_seq'=>$goods['goods_seq'],'type'=>'suboption'));
				$data_err_sub = $query_err->row_array();
			}
			$height = 50;
			if($data_err_opt['error_seq']||$data_err_sub['error_seq']){
				echo("<script>$('#packageErrorDialog').html('');\n");
				if($data_err_opt['error_seq']){
					echo("$('#packageErrorDialog').append('<div>".$data_err_opt['regist_date']."기준</div>');\n");
					echo("$('#packageErrorDialog').append('<div>현상 : 필수옵션에 ∞ 연결된 실제 상품 : 연결이 올바르지 않은 필수옵션이 존재합니다. <span class=\"red\">해당 필수옵션은 판매가 제한됩니다.</span></div>');\n");
					echo("$('#packageErrorDialog').append('<div>원인 : 연결된 실제 상품을 삭제하거나 옵션을 새롭게 변경하셨기 때문입니다.</div>');\n");
					echo("$('#packageErrorDialog').append('<div>해결 : 필수옵션에 실제 상품을 다시 연결하세요</div>');\n");
					$height+=100;
				}
				if( $data_err_sub['error_seq'] ){
					if($data_err_opt['error_seq']){
						echo("$('#packageErrorDialog').append('<div style=\"height:20px;\"></div>');\n");
						$height+=20;
					}
					echo("$('#packageErrorDialog').append('<div>".$data_err_sub['regist_date']."기준</div>');\n");
					echo("$('#packageErrorDialog').append('<div>현상 : 추가구성상품에 ∞ 연결된 실제 상품 : 연결이 올바르지 않은 추가구성상품이 존재합니다. <span class=\"red\">해당 추가구성상품은 판매가 제한됩니다.</span></div>');\n");
					echo("$('#packageErrorDialog').append('<div>원인 : 연결된 실제 상품을 삭제하거나 옵션을 새롭게 변경하셨기 때문입니다.</div>');\n");
					echo("$('#packageErrorDialog').append('<div>해결 : 추가구성상품에 실제 상품을 다시 연결하세요.</div>');\n");
					$height+=100;
				}
				$height +=60;
				echo("$('#packageErrorDialog').append('<div style=\"height:20px;\"></div>');\n");
				echo("$('#packageErrorDialog').append('<div class=\"center\"><span class=\"btn large cyanblue\"><button type=\"button\" onclick=\"closeDialog(\'packageErrorDialog\')\">확인</button></span></div>');\n");
				echo('openDialog("알려 드립니다.", "packageErrorDialog", {"width":"870","height":"'.$height.'","show" : "fade"});</script>');
			}
		}

	}


	// 설정-회원-등급별 구매혜택
	protected function get_promotion_grade($sale_seq='', $goods_seq=0, $category_code='') {
		# reset
		$buff = array();

		if(!$sale_seq) {
			$sale_seq 		= $this->input->post('sale_seq');
			$goods_seq 		= $this->input->post('goods_seq');
			$category_code 	= $this->input->post('category_code');
		}

		$this->load->model('membergroupmodel');
		$group_sale_lists = $this->membergroupmodel->get_group_sale($sale_seq, $goods_seq, $category_code);

		if(is_array($group_sale_lists)) {
			foreach($group_sale_lists as $sk=>$sv) {
				$buff[$sk]['sale_seq'] 		= $sale_seq;
				$buff[$sk]['sale_title'] 	= $sv['sale_title'];
				foreach($sv['loop'] as $lk=>$lv) {
					foreach($sv['records'] as $rk=>$rv) {
						if($lv['group_seq'] == $rk) {
							# 할인
							$buff[$sk]['discount'][$lk]['sale_use'] = $rv['sale_use'];
							$buff[$sk]['discount'][$lk]['sale_price'] = $rv['sale_price'];
							$buff[$sk]['discount'][$lk]['sale_price_type'] = $rv['sale_price_type'];
							$buff[$sk]['discount'][$lk]['sale_option_price'] = $rv['sale_option_price'];
							$buff[$sk]['discount'][$lk]['sale_option_price_type'] = $rv['sale_option_price_type'];
							$buff[$sk]['discount'][$lk]['group_name'] = $lv['group_name'];

							# 적립
							$buff[$sk]['save'][$lk]['point_use'] = $rv['point_use'];
							$buff[$sk]['save'][$lk]['point_price'] = $rv['point_price'];
							$buff[$sk]['save'][$lk]['point_price_type'] = $rv['point_price_type'];
							$buff[$sk]['save'][$lk]['reserve_price'] = $rv['reserve_price'];
							$buff[$sk]['save'][$lk]['reserve_price_type'] = $rv['reserve_price_type'];
							$buff[$sk]['save'][$lk]['group_name'] = $lv['group_name'];
						}
					}
				}
			}
		}

		sizeof($buff) == 1 && $buff = array_shift($buff);
		return $buff;
	}

	// 프로모션-유입경로 정보 가져오기
	public function get_promotion_referer() {
		# reset
		$referersaleRows = $result = array();

		# 입점사 코드 전달되지 않을 경우 반환값 없음.
		!$_POST['provider_seq'] && die('[]');

		# set-models
		$this->load->model('referermodel');
		$this->load->model('categorymodel');

		$referersaleRows = $this->referermodel->get_goods_referersale($_POST['goods_seq'], $_POST['category_code_arr'], $_POST['provider_seq']);

		if(sizeof($referersaleRows)) {
			foreach($referersaleRows as $k=>$v) {
				# reset
				$issuecategory = $issuegoods = $arrGoodsSeq = array();

				$referersale_seq = $v['referersale_seq'];

				# 특정 카테고리
				$issuecategory = $this->referermodel->get_referersale_issuecategory($referersale_seq);

				# 특정 상품
				$issuegoods = $this->referermodel->get_referersale_issuegoods($referersale_seq);

				if($issuegoods){
					foreach($issuegoods as $key => $tmp) $arrGoodsSeq[] =  $tmp['goods_seq'];
					$goods = $this->goodsmodel->get_goods_list($arrGoodsSeq,'thumbView');
					foreach($issuegoods as $key => $data) {
						$issuegoods[$key] = @array_merge($issuegoods[$key], array(
							'image'	=> $goods[$data['goods_seq']]['image'],
							'goods_name'	=> $goods[$data['goods_seq']]['goods_name'],
							'price'	=> get_currency_price($goods[$data['goods_seq']]['price'], 3)
						));
					}
				}

				if($issuecategory){
					foreach($issuecategory as $key =>$data) $issuecategory[$key]['category'] = $this->categorymodel->get_category_name($data['category_code']);
				}

				# 유입경로 조합
				$result[] = array(
					'referersale_seq'					=> $v['referersale_seq'],
					'referersale_name'				=> $v['referersale_name'],
					'referersale_url'					=> $v['referersale_url'],
					'url_type'							=> $v['url_type'],
					'issue_startdate'					=> $v['issue_startdate'],
					'issue_enddate'					=> $v['issue_enddate'],
					'limit_goods_price'				=> get_currency_price($v['limit_goods_price'], 3),
					'issue_type'						=> $v['issue_type'],
					'issue_category'					=> $issuecategory,
					'issue_goods'					=> $issuegoods,
					'currency'							=> $this->config_system['basic_currency'],
					'sale_type'							=> $v['sale_type'],
					'percent_goods_sale'			=> $v['percent_goods_sale'],
					'max_percent_goods_sale'	=> get_currency_price($v['max_percent_goods_sale'], 3),
					'won_goods_sale'				=> get_currency_price($v['won_goods_sale'], 3)
				);
			}
		}

		die(json_encode($result));
	}

	// 프로모션-사은품 정보 가져오기
	public function get_promotion_gift() {
		# 입점사 코드 전달되지 않을 경우 반환값 없음.
		!$_POST['provider_seq'] && die('[]');

		$this->load->model('giftmodel');
		$eventRows = $this->giftmodel->get_give_gift_list($_POST['goods_seq'], $_POST['category_codes'], $_POST['provider_seq']);

		die(json_encode($eventRows));
	}

	public function social_regist()
	{
		define('SOCIALCPUSE',true);
		$this->template->assign('socialcpuse',1);
		$this->regist();
	}

	public function icon(){
		//echo json_encode( code_load('goodsIcon') );
		$icon = code_load('goodsIcon');
		foreach($icon as $k){
			$path = ROOTPATH."/data/icon/goods/".$k['codecd'].".gif";
			if(file_exists($path)) $loop[] = $k;
		}
		echo json_encode( $loop );
	}


	public function select(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$containerHeight = !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 600;
		$this->template->assign(array('containerHeight'=>$containerHeight));

		$query = $this->db->query("select *, if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status from fm_event where end_date >= CURRENT_TIMESTAMP() order by event_seq desc");
		$eventData = $query->result_array();
		$this->template->assign(array('eventData'=>$eventData));

		$query = $this->db->query("SELECT *,
		if(current_date() between start_date and end_date,'진행 중',if(end_date < current_date(),'종료','시작 전')) as status
		FROM fm_gift
		WHERE gift_gb = 'order' AND end_date >= current_date()
		ORDER BY gift_seq desc");

		$giftData = $query->result_array();
		$this->template->assign(array('giftData'=>$giftData));

		$this->load->model('goodsdisplay');
		$this->template->assign(array('auto_orders'	=> $this->goodsdisplay->auto_orders));

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function select_list(){
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$aGetParams	= $this->input->get();
		if(!isset($aGetParams['selectGoodsStatus']))$aGetParams['selectGoodsStatus'] = "";
		if(!isset($aGetParams['goodsView']))$aGetParams['goodsView'] = "";
		if(!isset($aGetParams['sort']))$aGetParams['sort'] = 0;
		if(!isset($aGetParams['page']))$aGetParams['page'] = 1;
		$page = $aGetParams['page'];
		$adminOrder = $aGetParams['adminOrder'];
		$adminshipping = $aGetParams['adminshipping'];
		
		$where = $subWhere = $whereStr = "";
		$bind = array();
		
		$arg_list = func_get_args();
		
		$subWhere = null;
		if( isset($aGetParams['selectCategory4']) &&  $aGetParams['selectCategory4'] ){
			$subWhere = "category_code = ?";
			$bind[] = $aGetParams['selectCategory4'];
		}else if( isset($aGetParams['selectCategory3']) && $aGetParams['selectCategory3'] ){
			$subWhere = "category_code = ?";
			$bind[] = $aGetParams['selectCategory3'];
		}else if( isset($aGetParams['selectCategory2']) && $aGetParams['selectCategory2'] ){
			$subWhere = "category_code = ?";
			$bind[] = $aGetParams['selectCategory2'];
		}else if( isset($aGetParams['selectCategory1']) && $aGetParams['selectCategory1'] ){
			$subWhere = "category_code = ?";
			$bind[] = $aGetParams['selectCategory1'];
		}
		if($subWhere) $where[] = "g.goods_seq in (select goods_seq from fm_category_link where ".$subWhere.")";
		
		$subWhere = null;
		if( isset($aGetParams['selectBrand4']) &&  $aGetParams['selectBrand4'] ){
			$subWhere = "category_code = ?";
			$bind[] = $aGetParams['selectBrand4'];
		}else if( isset($aGetParams['selectBrand3']) && $aGetParams['selectBrand3'] ){
			$subWhere = "category_code = ?";
			$bind[] = $aGetParams['selectBrand3'];
		}else if( isset($aGetParams['selectBrand2']) && $aGetParams['selectBrand2'] ){
			$subWhere = "category_code = ?";
			$bind[] = $aGetParams['selectBrand2'];
		}else if( isset($aGetParams['selectBrand1']) && $aGetParams['selectBrand1'] ){
			$subWhere = "category_code = ?";
			$bind[] = $aGetParams['selectBrand1'];
		}
		if($subWhere) $where[] = "g.goods_seq in (select goods_seq from fm_brand_link where ".$subWhere.")";
		
		$subWhere = null;
		if( isset($aGetParams['selectLocation4']) &&  $aGetParams['selectLocation4'] ){
			$subWhere = "location_code = ?";
			$bind[] = $aGetParams['selectLocation4'];
		}else if( isset($aGetParams['selectLocation3']) && $aGetParams['selectLocation3'] ){
			$subWhere = "location_code = ?";
			$bind[] = $aGetParams['selectLocation3'];
		}else if( isset($aGetParams['selectLocation2']) && $aGetParams['selectLocation2'] ){
			$subWhere = "location_code = ?";
			$bind[] = $aGetParams['selectLocation2'];
		}else if( isset($aGetParams['selectLocation1']) && $aGetParams['selectLocation1'] ){
			$subWhere = "location_code = ?";
			$bind[] = $aGetParams['selectLocation1'];
		}
		if($subWhere) $where[] = "g.goods_seq in (select goods_seq from fm_location_link where ".$subWhere.")";
		
		if($adminOrder == 'Y' || $adminshipping == 'Y' ){//관리자주문넣기시 티켓상품제외
			$where[] = "g.goods_kind != 'coupon' ";
		}
		
		if( isset($aGetParams['selectGoodsName']) && $aGetParams['selectGoodsName'] ){
			$search_text = trim(str_replace(" ","",addslashes($aGetParams['selectGoodsName'])));
			$where[] = "
			(
				REPLACE(g.goods_name,' ','') like '%{$search_text}%'
				or g.goods_seq = '{$search_text}'
				or g.goods_code like '%{$search_text}%'
				or REPLACE(g.summary,' ','') like '{$search_text}'
				or REPLACE(g.keyword,' ','') like '{$search_text}'
				or (
					 select group_concat(sc_b.title,sc_b.title_eng) from fm_brand sc_b
					 inner join fm_brand_link sc_b2
					 on sc_b.category_code=sc_b2.category_code
					 where sc_b2.goods_seq=g.goods_seq
				) like '{$search_text}'
			)
			";
		}
		if( isset($aGetParams['selectStartPrice']) && $aGetParams['selectStartPrice'] ){
			$where[] = "o.price >= ?";
			$bind[] = $aGetParams['selectStartPrice'];
		}
		if( isset($aGetParams['selectEndPrice']) && $aGetParams['selectEndPrice'] ){
			$where[] = "o.price <= ?";
			$bind[] = $aGetParams['selectEndPrice'];
		}
		
		if( $aGetParams['selectGoodsStatus'] ){
			foreach($aGetParams['selectGoodsStatus'] as $selectGoodsStatus_tmp){
				$selGoodsStatus[] = trim(str_replace(" ","",addslashes($selectGoodsStatus_tmp)));
			}
			$where[] = "g.goods_status in ('".implode("','",$selGoodsStatus)."')";
		}
		
		if( $aGetParams['selectGoodsView'] ){
			$where[] = "g.goods_view = ?";
			$bind[] = $aGetParams['selectGoodsView'];
		}
		
		//동영상
		if( $aGetParams['file_key_w'] ){
			$where[] = " ( file_key_w != '') ";// or file_key_w is not null
		}
		if( !empty($aGetParams['video_use']) && $aGetParams['video_use'] !="전체" ){
			$where[] = "video_use = '{$aGetParams['video_use']}' ";
		}
		if( $aGetParams['videototal'] ){
			$where[] = "videototal > 0 ";
		}
		
		if( $aGetParams['selectdateGb'] && $aGetParams['selectSdate'] && $aGetParams['selectEdate'] ){
			$where[] = "g.".$aGetParams['selectdateGb']." >= '".$aGetParams['selectSdate']." 00:00:00'";
			$where[] = "g.".$aGetParams['selectdateGb']." <= '".$aGetParams['selectEdate']." 23:59:59'";
		}
		
		// 관련상품
		if($aGetParams['relation_goods_seq']){
			if($aGetParams['selectGoodsRelationCategory']){
				$sql = "select category_code from fm_category_link where goods_seq = ? and link = 1";
				$query = $this->db->query($sql,$aGetParams['relation_goods_seq']);
				$tmp = $query->row_array();
				
				if($tmp) $where[] = "g.goods_seq in (select goods_seq from fm_category_link where category_code=?)";
				$bind[] = $tmp['category_code'];
			}
			
			if($aGetParams['selectGoodsRelationBrand']){
				$sql = "select category_code from fm_brand_link where goods_seq = ? and link = 1";
				$query = $this->db->query($sql,$aGetParams['relation_goods_seq']);
				$tmp = $query->row_array();
				
				if($tmp) $where[] = "g.goods_seq in (select goods_seq from fm_brand_link where category_code=?)";
				$bind[] = $tmp['category_code'];
			}
			
			if($aGetParams['selectGoodsRelationLocation']){
				$sql = "select location_code from fm_location_link where goods_seq = ? and link = 1";
				$query = $this->db->query($sql,$aGetParams['relation_goods_seq']);
				$tmp = $query->row_array();
				
				if($tmp) $where[] = "g.goods_seq in (select goods_seq from fm_location_link where location_code=?)";
				$bind[] = $tmp['location_code'];
			}
		}
		
		$sqlFromClause = "";
		$sqlWhereClause = "";
		$sqlOrderbyClause = "";
		$sqlGroupbyClause = "";
		
		if($aGetParams['auto_term'] || ($aGetParams['auto_start_date'] && $aGetParams['auto_end_date'])){
			if($aGetParams['auto_term_type']=='relative') {
				$auto_start_date = date('Y-m-d',strtotime("-{$aGetParams['auto_term']} day"));
				$auto_end_date = date('Y-m-d');
			}else{
				$auto_start_date = $aGetParams['auto_start_date'];
				$auto_end_date = $aGetParams['auto_end_date'];
			}
			
			$gstSubSql	= "select goods_seq, sum(cnt) cnt from fm_stats_goods where type='[:STAT_TYPE:]' and stats_date between '{$auto_start_date}' and '{$auto_end_date}' group by goods_seq";
			switch($aGetParams['auto_order'])
			{
				case "deposit":
				case "best":
					/*
					 $gstSubSql			= str_replace('[:STAT_TYPE:]', 'deposit', $gstSubSql);
					 $sqlFromClause		.= " left join (".$gstSubSql.") gst on g.goods_seq=gst.goods_seq ";
					 $sqlOrderbyClause	= " order by gst.cnt desc, g.purchase_ea desc";
					 */
					$sqlOrderbyClause = " order by g.purchase_ea desc, g.goods_seq desc";
					break;
				case "deposit_price":
					$gstSubSql			= str_replace('[:STAT_TYPE:]', 'deposit_price', $gstSubSql);
					$sqlFromClause		.= " left join (".$gstSubSql.") gst on g.goods_seq=gst.goods_seq ";
					$sqlOrderbyClause	= " order by gst.cnt desc, g.purchase_ea desc";
					break;
				case "popular":
				case "view":
					$gstSubSql			= str_replace('[:STAT_TYPE:]', 'view', $gstSubSql);
					$sqlFromClause		.= " left join (".$gstSubSql.") gst on g.goods_seq=gst.goods_seq ";
					$sqlOrderbyClause	= " order by gst.cnt desc, g.page_view desc";
					break;
				case "review":
					$gstSubSql			= str_replace('[:STAT_TYPE:]', 'review', $gstSubSql);
					$sqlFromClause		.= " left join (".$gstSubSql.") gst on g.goods_seq=gst.goods_seq ";
					$sqlOrderbyClause	= " order by gst.cnt desc, g.review_count desc";
					break;
				case "cart":
					$gstSubSql			= str_replace('[:STAT_TYPE:]', 'cart', $gstSubSql);
					$sqlFromClause		.= " left join (".$gstSubSql.") gst on g.goods_seq=gst.goods_seq ";
					$sqlOrderbyClause	= " order by gst.cnt desc";
					break;
				case "wish":
					$gstSubSql			= str_replace('[:STAT_TYPE:]', 'wish', $gstSubSql);
					$sqlFromClause		.= " left join (".$gstSubSql.") gst on g.goods_seq=gst.goods_seq ";
					$sqlOrderbyClause	= " order by gst.cnt desc";
					break;
				case "newly":
				default:
					$sqlWhereClause		.= " and g.regist_date between '{$auto_start_date} 00:00:00' and '{$auto_end_date} 23:59:59'";
					$sqlOrderbyClause	= " order by g.regist_date desc, g.goods_seq desc";
					break;
				case "discount":
					$sqlWhereClause		.= " and o.consumer_price>0 ";
					$sqlOrderbyClause	= " order by o.price/o.consumer_price asc, o.price desc";
					break;
			}
		}else{
			$sqlOrderbyClause =" order by g.regist_date desc, g.goods_seq desc";
		}
		
		$query = "
		from fm_goods g
		inner join fm_goods_option o on o.goods_seq=g.goods_seq
		{$sqlFromClause}
		";
		
		if(!empty($aGetParams['selectEvent']) || !empty($aGetParams['selectEventBenefits'])){
			$query .= "
				left join fm_event_choice e on g.goods_seq = e.goods_seq
			";
			
			$where[] = "e.event_seq = ?";
			$bind[] = $aGetParams['selectEvent'];
			
			if(!empty($aGetParams['selectEventBenefits'])){
				$where[] = "e.event_benefits_seq = ?";
				$bind[] = $aGetParams['selectEventBenefits'];
			}
		}
		
		if(!empty($aGetParams['selectGift'])){
			$query .= "
				left join fm_gift_choice e on g.goods_seq = e.goods_seq
			";
			
			$where[] = "e.gift_seq = ?";
			$bind[] = $aGetParams['selectGift'];
		}
		
		$where[] = "g.provider_seq = ?";
		$bind[] = $this->providerInfo['provider_seq'];
		
		if($where){
			$whereStr = ' and '.implode(' and ',$where);
		}
		
		$query .= " where g.goods_type='goods' and o.default_option ='y' ".$whereStr.$sqlWhereClause;
		$query .= $sqlGroupbyClause.$sqlOrderbyClause;
		
		if($aGetParams['return_goods_seq']){
			$limit	= (int)($aGetParams['count_w']*$aGetParams['count_h']);
			$selectStr = "select g.goods_seq,g.goods_name,g.goods_type,o.price , g.event_st_num
			,(select image from fm_goods_image where goods_seq=g.goods_seq and cut_number=1 and image_type ='thumbCart' limit 1) as image
			,(select image from fm_goods_image where goods_seq=g.goods_seq and cut_number=2 and image_type ='thumbCart' limit 1) as image2
			";
			$query = $selectStr.$query. " limit {$limit}";
			$result = $this->db->query($query,$bind);
			$result = $result->result_array();
			echo json_encode($result);
			exit;
		}else{
			$selectStr = "select g.goods_seq,g.goods_name,g.goods_type,o.price, g.event_st_num
			,(select image from fm_goods_image where goods_seq=g.goods_seq and cut_number=1 and image_type ='thumbCart' limit 1) as image
			,(select image from fm_goods_image where goods_seq=g.goods_seq and cut_number=2 and image_type ='thumbCart' limit 1) as image2
			";
			$query = $selectStr.$query;
			
			$result = select_page(10,$page,10,$query,$bind);
			$result['page']['querystring'] = get_args_list();
			
			$this->template->assign('adminOrder',$adminOrder);
			$this->template->assign($result);
			$this->template->define(array('tpl'=>$file_path));
			$this->template->print_("tpl");
		}
	}


	# 상품선택 New @2015-07-30 pjm
	public function select_new(){

		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model("providermodel");
		$file_path	= $this->template_path();

		$containerHeight = !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 240;
		$this->template->assign(array('containerHeight'=>$containerHeight));

		$cart_table = $_GET['cart_table'];

		# 재주문 상품변경, 미매칭 상품매칭 @2015-08-27 pjm
		if($cart_table && $_GET['option_seq']){

			//item and option
			$query = "select
						i.item_seq,i.goods_seq,i.goods_name ,i.provider_seq,
						(select provider_name from fm_provider where provider_seq=i.provider_seq) provider_name,
						o.title1,o.title2,o.title3,o.title4,o.title5,
						o.option1,o.option2,o.option3,o.option4,o.option5,o.ea
					from
						fm_order_item_option as o
						left join fm_order_item as i on o.order_seq=i.order_seq and o.item_seq=i.item_seq
					where
						o.order_seq=?
						and o.item_option_seq=?
				";
			$query = $this->db->query($query,array($_GET['order_seq'],$_GET['option_seq']));
			$goodsData = $query->row_array();

			//검색 default
			$_GET['item_seq']		= $goodsData['item_seq'];
			$_GET['goods_seq']		= $goodsData['goods_seq'];
			$_GET['keyword']		= $goodsData['goods_name'];
			$_GET['selectKeyword']	= $goodsData['goods_name'];
			$_GET['provider_seq']	= $goodsData['provider_seq'];
			$_GET['provider_name']	= $goodsData['provider_name'];

			## 주문된 상품 정보 불러와서 script 로 뿌려주기
			$option		= $optionTitle		= $optionEa = array();
			$suboption	= $suboptionTitle	= $suboptionEa = array();
			$inputValue = $inputTitle		= array();
			$option[0][]					= $goodsData['option1'];
			$optionTitle[0][]				= $goodsData['title1'];
			if($goodsData['option2']){ $option[0][] = $goodsData['option2']; $optionTitle[0][] = $goodsData['title2']; }
			if($goodsData['option3']){ $option[0][] = $goodsData['option3']; $optionTitle[0][] = $goodsData['title3']; }
			if($goodsData['option4']){ $option[0][] = $goodsData['option4']; $optionTitle[0][] = $goodsData['title4']; }
			if($goodsData['option5']){ $option[0][] = $goodsData['option5']; $optionTitle[0][] = $goodsData['title5']; }

			$optionEa[0] = $goodsData['ea'];

			// suboption
			$query = "select title,suboption,ea from fm_order_item_suboption where order_seq=? and item_option_seq=?";
			$query = $this->db->query($query,array($_GET['order_seq'],$_GET['option_seq']));
			foreach($query->result_array() as $subData){
				$suboption[0][]		= $subData['suboption'];
				$suboptionTitle[0][]	= $subData['title'];
				$suboptionEa[0][]		= $subData['ea'];
			}

			// inputoption
			$query = "select title,value,type from fm_order_item_input where order_seq=? and item_option_seq=?";
			$query = $this->db->query($query,array($_GET['order_seq'],$_GET['option_seq']));
			foreach($query->result_array() as $subData){
				$inputsValue[0][]		= $subData['value'];
				$inputsTitle[0][]		= $subData['title'];
				$inputsType[0][]		= $subData['type'];
			}

			$tmp_cart					= array();
			$tmp_cart['option']			= $option;
			$tmp_cart['optionTitle']	= $optionTitle;
			$tmp_cart['optionEa']		= $optionEa;

			if($suboption)		$tmp_cart['suboption']		= $suboption;
			if($suboptionTitle) $tmp_cart['suboptionTitle']	= $suboptionTitle;
			if($suboptionEa)	$tmp_cart['suboptionEa']	= $suboptionEa;
			if($inputsValue)	$tmp_cart['inputsValue']	= $inputsValue;
			if($inputsTitle)	$tmp_cart['inputsTitle']	= $inputsTitle;
			if($inputsType)		$tmp_cart['inputsType']		= $inputsType;

			$tmp_str = array();
			$tmp_str[] = "mode=tmp";
			$tmp_str[] = "cart_table=".$cart_table;
			$tmp_str[] = "member_seq=".$_GET['member_seq'];
			$tmp_str[] = "option_select_goods_seq=".$goodsData['goods_seq'];
			$tmp_str[] = "gl_option_select_ver=0.1";
			foreach($tmp_cart as $key=>$val){
				foreach($val as $key2=>$val2){
					if(!is_array($val2)){
						$tmp_str[] = $key."[".$key2."]=".urlencode($val2);
					}else{
						foreach($val2 as $key3=>$val3) $tmp_str[] = $key."[".$key2."][".$key3."]=".urlencode($val3);
					}
				}
			}

			$str = implode("&",$tmp_str);

		}

		$query = $this->db->query("select *, if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status from fm_event where end_date >= CURRENT_TIMESTAMP() order by event_seq desc");
		$eventData = $query->result_array();
		$this->template->assign(array('eventData'=>$eventData));


		$query = $this->db->query("SELECT *,
		if(current_date() between start_date and end_date,'진행 중',if(end_date < current_date(),'종료','시작 전')) as status
		FROM fm_gift
		WHERE gift_gb = 'order' AND end_date >= current_date()
		ORDER BY gift_seq desc");

		$giftData = $query->result_array();
		$this->template->assign(array('giftData'=>$giftData));
		$this->load->model('goodsdisplay');
		$this->template->define(array('goods_search_form' => $this->skin.'/goods/goods_search_form_select.html'));
		$this->template->assign(array('auto_orders'	=> $this->goodsdisplay->auto_orders));

		// 할인이벤트 진행중 리스트
		$ingeventsql = 'SELECT event_seq, title, start_date, end_date FROM fm_event WHERE CURRENT_TIMESTAMP() BETWEEN start_date AND end_date';
		$ingeventquery = $this->db->query($ingeventsql);
		$event_list = array();
		foreach($ingeventquery->result_array() as $event_row){
			$event_row['event_title'] = sprintf("%s (%s ~ %s)", $event_row['title'], substr($event_row['start_date'],0,10), substr($event_row['end_date'],0,10));
			$event_list[] = $event_row;
		}
		$this->template->assign('event_list', $event_list);

		//사은품이벤트 진행중 리스트
		$ing_gift_sql = 'SELECT gift_seq, title, start_date, end_date FROM fm_gift WHERE CURRENT_TIMESTAMP() BETWEEN start_date AND end_date';
		$ing_gift_query = $this->db->query($ing_gift_sql);
		$gift_list = array();
		foreach($ing_gift_query->result_array() as $gift_row){
			$gift_row['gift_title'] = sprintf("%s (%s ~ %s)", $gift_row['title'], substr($gift_row['start_date'],0,10), substr($gift_row['end_date'],0,10));
			$gift_list[] = $gift_row;
		}

		$this->template->assign('provider_fix', 1);
		$this->template->assign('gift_list', $gift_list);
		$this->template->assign('tmp_str', $str);

		$provider	= $this->providermodel->provider_goods_list_sort();
		$this->template->assign('provider', $provider);


		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	# 상품선택 New > 검색된 상품 리스트 @2015-07-30 pjm
	public function select_list_new(){


		$this->tempate_modules();
		$file_path	= $this->template_path();

		define('PACKAGEUSE', 'all');

		if(!isset($_GET['sort']))$_GET['sort']			= "desc";
		if(!isset($_GET['page']))$_GET['page']			= 1;
		if(!isset($_GET['perpage']))$_GET['perpage']	= 5;
		if(!isset($_GET['orderby'])) $_GET['orderby']	= "goods_seq";

		$page			= $_GET['page'];
		$adminOrder		= $_GET['adminOrder'];
		$adminshipping	= $_GET['adminshipping'];

		### SEARCH
		# 승인/미승인
		if($_GET['search_provider_status']){
			$sc['search_provider_status']	= $_GET['search_provider_status'];
		}

		# 정렬관련 추가 (정가, 할인가, 재고 오름/내림 차순 정렬)
		$orderbyTmp = explode("_",$_GET['orderby']);
		if(in_array($orderbyTmp[0],array("asc","desc"))){
			foreach($orderbyTmp as $orderK=>$orderV) if($orderK > 0) $orderbyTmp2[] = $orderV;
			$_GET['orderby']	= implode("_",$orderbyTmp2);
			$_GET['sort']		= $orderbyTmp[0];
		}else{
			$_GET['orderby'];
		}

		$sc['page']		= $_GET['page'];
		$sc['sort']		= $_GET['sort'];
		$sc['perpage']	= $_GET['perpage'];
		$sc['orderby']	= $_GET['orderby'];

		$_GET['orderby'] = ($_GET['orderby']) ? $_GET['orderby']:'goods_seq';
		$_GET['sort']	 = ($_GET['sort']) ? $_GET['sort']:'desc';

		if($_GET['goods_seq']) $sc['abs_goods_seq'] = array($_GET['goods_seq']);

		if($_GET['selectCategory1']) $sc['category1'] = $_GET['selectCategory1'];
		if($_GET['selectCategory2']) $sc['category2'] = $_GET['selectCategory2'];
		if($_GET['selectCategory3']) $sc['category3'] = $_GET['selectCategory3'];
		if($_GET['selectCategory4']) $sc['category4'] = $_GET['selectCategory4'];

		if($_GET['selectBrand1']) $sc['brands4'] = $_GET['selectBrand1'];
		if($_GET['selectBrand2']) $sc['brands4'] = $_GET['selectBrand2'];
		if($_GET['selectBrand3']) $sc['brands4'] = $_GET['selectBrand3'];
		if($_GET['selectBrand4']) $sc['brands4'] = $_GET['selectBrand4'];

		if($_GET['selectLocation1']) $sc['location4'] = $_GET['selectLocation1'];
		if($_GET['selectLocation2']) $sc['location4'] = $_GET['selectLocation2'];
		if($_GET['selectLocation3']) $sc['location4'] = $_GET['selectLocation3'];
		if($_GET['selectLocation4']) $sc['location4'] = $_GET['selectLocation4'];

		if($_GET['selectGoodsStatus'])	$sc['goodsStatus'] = $_GET['selectGoodsStatus'];
		if($_GET['selectGoodsView'])	$sc['goodsView'] = $_GET['selectGoodsView'];
		if($_GET['selectGoodskind'])	$sc['goodsKind'] = $_GET['selectGoodskind'];
		if($_GET['selectEvent'])		$sc['event_seq'] = $_GET['selectEvent'];
		if($_GET['selectGift'])			$sc['gift_seq'] = $_GET['selectGift'];

		if($adminOrder == 'Y' || $adminshipping == 'Y' ){//관리자주문넣기시 쿠폰상품제외
			//$where[] = "g.goods_kind != 'coupon' ";
		}
		$sc['adminOrder'] = "Y";


		$sc['provider_seq']	= $_GET['provider_seq'];
		$sc['keyword']		= $_GET['selectKeyword'];

		if($_GET['selectKeyword_sType']){
			switch($_GET['selectKeyword_sType']){
				case '상품명':
					$sc['search_type'] = "goods_name";
				break;
				case '상품고유값':
					$sc['search_type'] = "goods_seq";
				break;
				case '상품코드':
					$sc['search_type'] = "goods_code";
				break;
				case '태그':
					$sc['search_type'] = "keyword";
				break;
				case '간략설명':
					$sc['search_type'] = "summary";
				break;
				case '전체검색':
					$sc['search_type'] = "all";
				break;
			}
		}

		$result = $this->goodsmodel->admin_goods_list($sc);

		foreach($result['record'] as $k=>$datarow){
			$datarow['goods_view_text']	= $datarow['goods_view']=='look' ? "<span style='color:blue'>노출</span>" : "<span style='color:red'>미노출</span>";
			$result['record'][$k] = $datarow;
		}

		//정렬
		$sorderby = $_GET['orderby'];
		$_GET['orderby'] = $_GET['sort']."_".$_GET['orderby'];


		$this->template->assign('adminOrder',$adminOrder);
		$this->template->assign($result);
		$this->template->assign(array('perpage'=>$_GET['perpage'],'orderby'=>$_GET['orderby'],'sort'=>$sort,'sorderby'=>$sorderby));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	public function gift(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$containerHeight = !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 600;
		$this->template->assign(array('containerHeight'=>$containerHeight));

		$query = $this->db->query("select *, if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status from fm_event where end_date >= CURRENT_TIMESTAMP() order by event_seq desc");
		$eventData = $query->result_array();
		$this->template->assign(array('eventData'=>$eventData));

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function gift_list(){
		$this->tempate_modules();
		$file_path	= $this->template_path();

		if(!isset($_GET['goodsStatus']))$_GET['goodsStatus'] = "";
		if(!isset($_GET['goodsView']))$_GET['goodsView'] = "";
		if(!isset($_GET['sort']))$_GET['sort'] = 0;
		if(!isset($_GET['page']))$_GET['page'] = 1;
		$page = $_GET['page'];
		$adminOrder = $_GET['adminOrder'];

		$where = $subWhere = $whereStr = "";
		$bind = array();

		$arg_list = func_get_args();

		if( isset($_GET['selectCategory4']) &&  $_GET['selectCategory4'] ){
			$subWhere = "category_code = ?";
			$bind[] = $_GET['selectCategory4'];
		}else if( isset($_GET['selectCategory3']) && $_GET['selectCategory3'] ){
			$subWhere = "category_code = ?";
			$bind[] = $_GET['selectCategory3'];
		}else if( isset($_GET['selectCategory2']) && $_GET['selectCategory2'] ){
			$subWhere = "category_code = ?";
			$bind[] = $_GET['selectCategory2'];
		}else if( isset($_GET['selectCategory1']) && $_GET['selectCategory1'] ){
			$subWhere = "category_code = ?";
			$bind[] = $_GET['selectCategory1'];
		}

		if($subWhere){
			$where[] = "g.goods_seq in (select goods_seq from fm_category_link where ".$subWhere.")";
		}
		if( isset($_GET['selectGoodsName']) && $_GET['selectGoodsName'] ){
			//$where[] = "g.goods_name like ?";
			//$bind[] = '%'.$_GET['selectGoodsName'].'%';
			$where[] = " (g.goods_name like '%".$_GET['selectGoodsName']."%' or g.goods_code like '%".$_GET['selectGoodsName']."%' ) ";
		}
		if( isset($_GET['selectStartPrice']) && $_GET['selectStartPrice'] ){
			$where[] = "o.price >= ?";
			$bind[] = $_GET['selectStartPrice'];
		}
		if( isset($_GET['selectEndPrice']) && $_GET['selectEndPrice'] ){
			$where[] = "o.price <= ?";
			$bind[] = $_GET['selectEndPrice'];
		}

		if( $_GET['goodsStatus'] ){
			$where[] = "g.goods_status = ?";
			$bind[] = $_GET['goodsStatus'];
		}

		if( $_GET['goodsView'] ){
			$where[] = "g.goods_view = ?";
			$bind[] = $_GET['goodsView'];
		}

		if( $_GET['provider_seq'] ){
			$where[] = "g.provider_seq = ?";
			$bind[] = $_GET['provider_seq'];
		}

		if( $_GET['ship_grp_seq'] ){
			$where[] = "g.shipping_group_seq = ?";
			$bind[] = $_GET['ship_grp_seq'];
		}

		$arrSort = array('g.goods_seq desc','g.goods_seq asc','g.purchase_ea desc','g.purchase_ea asc','g.page_view desc','g.page_view asc','g.review_count desc','g.review_count asc');
		$sortStr = " order by " .$arrSort[$_GET['sort']];

		$query = "select g.goods_seq,g.goods_name,g.goods_type,o.price
		from fm_goods g
		inner join fm_goods_option o on o.goods_seq=g.goods_seq";

		if(!empty($_GET['selectEvent']) || !empty($_GET['selectEventBenefits'])){
			$query .= "
				left join fm_event_choice e on g.goods_seq = e.goods_seq
			";

			$where[] = "e.event_seq = ?";
			$bind[] = $_GET['selectEventBenefits'];

			if(!empty($_GET['selectEventBenefits'])){
				$where[] = "e.event_benefits_seq = ?";
				$bind[] = $_GET['selectEventBenefits'];
			}
		}

		if($where){
			$whereStr = ' and '.implode(' and ',$where);
		}

		$query .= "
		where
			g.goods_type='gift' and o.default_option ='y'".$whereStr.$sortStr;
		$result = select_page(10,$page,10,$query,$bind);
		$result['page']['querystring'] = get_args_list();

		$this->template->assign('adminOrder',$adminOrder);
		$this->template->assign($result);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 상품 이미지 등록 :: 2016-05-03 lwh
	public function popup_image()
	{
		$file_path	= $this->template_path();

		//이미지설정폼
		$this->template->define(array('goods_resize_form' => $this->skin.'/goods/_goods_resize_setting.html'));

		// 옵션정보 가져오기
		$aGetParams = $this->input->get();	

		if($aGetParams['no']){
			$data_goods 	= $this->goodsmodel->get_goods($aGetParams['no']);
			$this->template->assign('data_goods',$data_goods);
			$options 		= $this->goodsmodel->get_goods_option($aGetParams['no']);
			$this->template->assign('options',$options);
		}

		// 정렬 필수 (부모창에 던질때 주의) :: 2016-05-12 lwh
		$goodsimg = array('large','view','list1','list2','thumbView','thumbCart','thumbScroll');
		$goodscof = config_load('goodsImageSize');
		foreach($goodsimg as $k => $imgtype){
			$goodsimgsize[$imgtype] = $goodscof[$imgtype];
		}

		$this->template->assign('sc',$aGetParams);
		$this->template->assign('browser_info',getBrowser());
		$this->template->assign('goodsImageSize',$goodsimgsize);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	//상품이미지 등록시 일괄등록 :: 2016-05-03 lwh
	public function popup_image_multi()
	{
		$file_path	= $this->template_path();

		//이미지설정폼
		$this->template->assign('multi',true);
		$this->template->define(array('goods_resize_form' => $this->skin.'/goods/_goods_resize_setting.html'));

		// 옵션정보 가져오기
		$no = $_GET['no'];
		if($_GET['no']){
			$data_goods = $this->goodsmodel->get_goods($no);
			$this->template->assign('data_goods',$data_goods);
		}

		$this->template->assign('goodsImageSize',config_load('goodsImageSize'));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 상품 이미지 정렬 및 삭제 :: 2016-05-03 lwh
	public function popup_image_sort()
	{
		$file_path	= $this->template_path();
		$aGetParams = $this->input->get();

		$this->template->assign('sc',$aGetParams);
		$this->template->assign("goodsSeq", $this->input->get("no"));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/**
	 * 동영상 등록
	 * #1 : 동영상을 입력받아 smartucc에 업로드한다.
	 */
	public function video_upload()
	{
	    $this->load->library('videouploadlibrary');
	    $this->videouploadlibrary->upload();
	}
	
	/**
	 * 동영상 등록
	 * #2 : smartucc에 등록된 동영상을 동영상을 DB에 저장한다.
	 */
	public function video_update()
	{
	    $this->load->library('videouploadlibrary');
	    $this->videouploadlibrary->update();
	}

	/* 동영상 URL 화면 */
	public function video_url(){
		$this->template->assign("realvideourl",$_GET['realvideourl']);
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}


	public function download_write(){
		$this->admin_menu();
		$this->tempate_modules();

		$this->load->model('excelgoodsmodel');

		$itemList 	= $this->excelgoodsmodel->itemList;
		$requireds 	= $this->excelgoodsmodel->requireds;

		$provider_seq 	= $this->providerInfo['provider_seq'];
		$data 			= get_data("fm_exceldownload",array("gb"=>"GOODS","provider_seq"=>$provider_seq));
		$item 			= $data ? explode("|",$data[0]['item']) : null;

		// 관리자UI개선 폼에서 호출 시
		if($this->input->get("mode") == "newform"){

			// 전체 칼럼
			$columnAll = $itemList;
			$sortKey = 0;
			foreach($columnAll as $key => $val){
				$sortKey++;
				$sortColumn[$key] = $sortKey;
			}
			$columnSelect 	= array();
			
			// 선택(저장)한 칼럼
			if	(is_array($item) && count($item) > 0){
				foreach($item as $_column){
					$columnSelect[$_column] = $columnAll[$_column];
				}
			}
			foreach($columnSelect as $key=>$val) unset($columnAll[$key]);

			$return = array('chkScm'=> false,
							'seq'			=> $data['seq'],
							'provider_seq'	=> $provider_seq,
							'formData'		=> array('form_id'=>'admin_goods_'.$provider_seq,'form_name'=>'상품','form_type'=>'GOODS'),
							'columnAll'		=> $columnAll,
							'sortColumn'	=> $sortColumn,
							'columnSelect'	=> $columnSelect,
							'mode'			=> $this->input->get("mode"),
			);
			echo json_encode($return);
		}else{

			$this->template->assign('itemList',$itemList);
			$this->template->assign('requireds',$requireds);

			if( isset($item) && count($item)>1 ){
				$this->template->assign('items',$item);
			}else{
				$this->template->assign('items',$requireds);
			}

			$file_path	= $this->template_path();
			$this->template->define(array('tpl'=>$file_path));
			$this->template->print_("tpl");
		}
	}

	public function _benefits($goods_seq)
	{
		$this->load->model('membermodel');
		$this->load->model('configsalemodel');
		$this->load->model('couponmodel');

		if($goods_seq){

			$cfg_reserve = ($this->reserves)?$this->reserves:config_load('reserve');

			$goods = $this->goodsmodel->get_goods($goods_seq);
			$options = $this->goodsmodel->get_goods_option($goods_seq);
			foreach($options as $key => $option)
			{
				if($option['default_option'] == 'y'){
					if( $goods['reserve_policy'] == 'shop' ){
						$result['reserve_rate'] = $cfg_reserve['default_reserve_percent'];
						$result['reserve_unit'] = 'percent';
					}
					$result['reserve'] = $this->goodsmodel->get_reserve_with_policy($goods['reserve_policy'],$option['price'],$cfg_reserve['default_reserve_percent'],$option['reserve']);
					$result = $option;
				}
			}
			$result['price_rate'] = ceil(($result['consumer_price']-$result['price']) / $result['consumer_price'] * 100);

			$categorys = $this->goodsmodel->get_goods_category($goods_seq);
			foreach($categorys as $data_category){
				$r_category_code[] = $data_category['category_code'];
			}
			// 이벤트
			$result['event'] = get_event_price($result['price'], $goods_seq, $r_category_code, $result['consumer_price'], $goods);
			// 회원등급
			$result['member_group'] = $this->membermodel->get_group_for_goods($result['price'],$goods_seq,$r_category_code);
			// 모바일
			$result['systemmobiles'] = $this->configsalemodel->get_mobile_sale_for_goods($result['price']);
			// 할인쿠폰
			$r_coupon = $this->couponmodel->get_able_download_list(date('Y-m-d'),'',$goods_seq,$r_category_code,$result['price']);
			foreach($r_coupon as $data_coupon){
				if($max < $data_coupon['goods_sale']) {
					$max = $data_coupon['goods_sale'];
					$result['max_coupon'] = $data_coupon;
				}
			}
			// 배송정보 가져오기
			$result['delivery'] = $this->goodsmodel->get_goods_delivery($goods);
			// 좋아요 할인
			$result['systemfblikes'] = $this->configsalemodel->get_fblike_sale_for_goods($result['price']);
			// 무이자 할인
			$pg = config_load($this->config_system['pgCompany']);
			if($pg['nonInterestTerms'] == 'manual'){
				$tmp = code_load($this->config_system['pgCompany'].'CardCompanyCode');
				foreach($tmp as $company_code){
					$r_card_company[$company_code['codecd']] = $company_code['value'];
				}
				if($pg['pcCardCompanyCode']) foreach($pg['pcCardCompanyCode'] as $key => $code){
					$result['nointerest'][] = $r_card_company[$code] . " " . $pg['pcCardCompanyTerms'][$key];
				}
			}

			/* 인기지수 : 장바구니 */
			$query = $this->db->query("select count(*) as cnt from fm_cart where goods_seq='{$goods_seq}' and member_seq>0 group by member_seq");
			$cntrow = $query->result_array();
			$result['popularity'][] = array(
				'desc'		=> '<b>장바구니</b>에 담고 있는 회원(현재기준)',
				'value'		=> number_format($cntrow[0]['cnt']) ,
				'postfix'	=> '명',
				'link'		=> '../member/catalog?goods_seq_cond=cart&goods_seq='.$goods_seq,
			);

			/* 인기지수 : 위시리스트 */
			$query = $this->db->query("select count(*) as cnt from fm_goods_wish where goods_seq='{$goods_seq}' and member_seq>0 group by member_seq");
			$cntrow = $query->result_array();
			$result['popularity'][] = array(
				'desc'		=> '<b>위시리스트</b>에 담고 있는 회원(현재기준)',
				'value'		=> number_format($cntrow[0]['cnt']) ,
				'postfix'	=> '명',
				'link'		=> '../member/catalog?goods_seq_cond=wish&goods_seq='.$goods_seq,
			);

			/* 인기지수 : 좋아요 */
			$query = $this->db->query("select count(*) as cnt from fm_goods_fblike where goods_seq='{$goods_seq}' and member_seq>0 group by member_seq");
			$cntrow = $query->result_array();
			$result['popularity'][] = array(
				'desc'		=> '<b>상품을 좋아요</b> 한 회원(누적)',
				'value'		=> number_format($cntrow[0]['cnt']) ,
				'postfix'	=> '명',
				'link'		=> '../member/catalog?goods_seq_cond=fblike&goods_seq='.$goods_seq,
			);

			/* 인기지수 : 재입고알림 */
			$query = $this->db->query("select count(*) as cnt from fm_goods_restock_notify  where goods_seq='{$goods_seq}' and member_seq>0 and notify_status='none' group by member_seq");
			$cntrow = $query->result_array();
			$result['popularity'][] = array(
				'desc'		=> '<b>재입고알림</b>을 요청한 회원(미통보기준)',
				'value'		=> number_format($cntrow[0]['cnt']) ,
				'postfix'	=> '명',
				'link'		=> "../goods/restock_notify_catalog?keyword={$goods_seq}&notifyStatus[]=none",
			);

			/* 인기지수 : 상품리뷰 */
			$query = $this->db->query("select count(*) as cnt from fm_goods_review where goods_seq='{$goods_seq}'");
			$cntrow = $query->result_array();
			$result['popularity'][] = array(
				'desc'		=> '<b>상품 리뷰</b>(누적)',
				'value'		=> number_format($cntrow[0]['cnt']),
				'postfix'	=> '회',
				'link'		=> "../board/board?id=goods_review&goods_seq={$goods_seq}",
			);

			$result['goodsbenefits'] = $goods;
			$result['goods_seq'] = $goods_seq;

			return $result;
		}
	}

	public function benefits()
	{
		$goods_seq = (int) $_GET['goods_seq'];
		$result = $this -> _benefits($goods_seq);
		if($_GET['socialcpuse']){
			$shippingdelivery = config_load("shippingdelivery");
			$this->template->assign('deliveryCostPolicy',$shippingdelivery['deliveryCostPolicy']);

			define('SOCIALCPUSE',true);
			$this->template->assign('socialcpuse',1);
		}

		$this->load->model('eventmodel');
		//단독이벤트추출
		//사은품이벤트추출
		$giftloop = $this->eventmodel->get_gift_event_all($goods_seq);
		$this->template->assign('gifloop',$giftloop);


		// 좋아요 버튼
		$this->load->library('snssocial');
		$this->template->assign('APP_USE',			$this->__APP_USE__);
		$this->template->assign('APP_ID',				$this->__APP_ID__);
		$this->template->assign('APP_SECRET',		$this->__APP_SECRET__);
		$this->template->assign('APP_PAGE',			$this->__APP_PAGE__);
		$this->template->assign('APP_NAMES',		$this->__APP_NAMES__);
		$this->template->assign('likeurl',				$this->likeurl);

		$file_path	= $this->template_path();
		$this->template->assign($result);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function benefits_info()
	{
		$goods_seq = (int) $_GET['goods_seq'];
		$result = $this -> _benefits($goods_seq);
		if($_GET['socialcpuse']){
			define('SOCIALCPUSE',true);
			$this->template->assign('socialcpuse',1);
		}

		$file_path	= $this->template_path();
		$this->template->assign($result);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function goods_status_images_setting(){
		$iconDirectoryPath = "/data/icon/goods_status/";
		$data = code_load('goodsStatusImage');
		$goodsStatusImage = array();
		foreach($data as $row){
			$goodsStatusImage[$row['codecd']] = $row['value'];
		}

		$this->template->assign(array(
			'goodsStatusImage' => $goodsStatusImage,
			'iconDirectoryPath' => $iconDirectoryPath,
		));

		if($_GET['type']) $this->template->assign('type', $_GET['type']);

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 재고 조정 */
	public function stock_modify(){
		$file_path	= $this->template_path();

		$stock_code = "ST".date('YmdHis');

		if($_GET['optionTitle']){

			if($_GET['mode']=='optionStockEdit') {
				$options = $this->goodsmodel->get_goods_option($_GET['goods_seq']);
				$optionData = array();
				foreach($options as $optionRow){
					$opt_names = array();
					if($optionRow['option1']) $opt_names[] = $optionRow['option1'];
					if($optionRow['option2']) $opt_names[] = $optionRow['option2'];
					if($optionRow['option3']) $opt_names[] = $optionRow['option3'];
					if($optionRow['option4']) $opt_names[] = $optionRow['option4'];
					if($optionRow['option5']) $opt_names[] = $optionRow['option5'];
					$optionData[] = array(
						'opt_names' => $opt_names,
						'supply_price' => $optionRow['supply_price']
					);
				}
				$_GET['optionData'] = $optionData;

			}
		}

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 매입처 검색 팝업 */
	public function supplier_search(){
		$file_path	= $this->template_path();

		$this->db->like("supplier_name",$_GET['keyword']);
		$query = $this->db->get("fm_supplier");
		$result = $query->result_array();
		$this->template->assign('result',$result);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 매입처 등록 팝업  */
	public function supplier_regist(){
		$file_path	= $this->template_path();

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 입점사 상품 브랜드 목록 ajax */
	public function provider_brand_list(){
		$this->load->model('providermodel');
		$result = $this->providermodel->provider_charge_list($_GET['provider_seq']);

		$brand_list = array();
		foreach($result as $row){
			if($row['category_code']) $brand_list[] = $row;
		}
		echo json_encode($brand_list);
	}

	/* 입점사 수수료 목록 ajax */
	public function provider_charge_list(){
		$this->load->model('providermodel');
		$result = $this->providermodel->provider_charge_list($_GET['provider_seq']);
		echo json_encode($result);
	}


	public function goods_sub_info(){
		$category = $_GET['category'];
		$result = $this->goodsmodel->get_goods_sub_info($category);

		echo json_encode($result);
	}

	public function gift_catalog()
	{
		$this->admin_menu();
		$this->tempate_modules();

		// 상품검색폼
		$this->template->define(array('goods_search_form' => $this->skin.'/goods/goods_search_form2.html'));
		$file_path	= $this->template_path();

		list($loop,$sc,$sort) =  $this->_goods_list_gift();

		### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');

		$currency_config 		= code_load('currency', $this->config_system['basic_currency']);
		$arr_gl_gooda_config 	= $this->goodslibrary->get_goods_config($auth,false,true,'gift');

		//정렬
		$sorderby = $_GET['orderby'];
		$_GET['orderby'] = $sort."_".$_GET['orderby'];

		$cfg_order = config_load('order');
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign('loop',$loop['record']);
		$this->template->assign('page',$loop['page']);
		$this->template->assign('search_yn',$loop['search_yn']);
		$this->template->assign(array('perpage'=>$_GET['perpage'],'orderby'=>$_GET['orderby'],'sort'=>$sort,'sorderby'=>$sorderby));
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc),'arr_gl_gooda_config'=>json_encode($arr_gl_gooda_config)));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function _goods_list_gift()
	{

		### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');
		if(isset($auth)) $this->template->assign('auth',$auth);

		###
		if($_GET['header_search_keyword']) $_GET['keyword'] = $_GET['header_search_keyword'];

		# default-setting
		$search_page 	= 'gift_catalog';
		$_default 		= array('orderby'=>'desc_goods_seq','page'=>1,'perpage'=>10);
		$scRes 			= $this->searchsetting->pagesearchforminfo($search_page,$_default);

		$this->template->assign('sc_form',$scRes['form']);
		unset($scRes['form']);
		$sc 				= $scRes;
		$sc['goods_type']	= 'gift';
		$sc['provider_seq'] = $this->providerInfo['provider_seq'];

		$set_default = false;
		if (preg_match('/goods\/batch_modify/',$_SERVER['REQUEST_URI']) && count($_GET) == 2) $set_default = true;

		### GOODS
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$cfg_order = config_load('order');
		$this->load->model('ordermodel');
		$this->load->model('providermodel');
		$this->load->model('giftmodel');

		//새 프로세스 적용 18.11.20 kmj
		$loop = $this->goodsmodel->admin_goods_list_new($sc); 
		$this->template->assign(array('param_count'=>urlencode(base64_encode(end($this->db->queries)))));

		$goods_addition = $this->goodsmodel->goods_addition_list_all();
		$model				= $goods_addition['model'];
		$brand				= $goods_addition['brand'];
		$manufacture		= $goods_addition['manufacture'];
		$orign				= $goods_addition['orgin'];

		//사은품이벤트 진행중 리스트
		$gift_list = $this->giftmodel->get_gift_ing_list();
		$this->template->assign('gift_list', $gift_list);

		$this->template->assign(array('provider_name'=>$this->providerInfo['provider_name'], 'model'=>$model,'manufacture'=>$manufacture,'orign'=>$orign));

		### PAGE & DATA
		/*
		$query = "select count(*) cnt from fm_goods A LEFT JOIN fm_goods_option B ON A.goods_seq = B.goods_seq LEFT JOIN fm_goods_supply C ON A.goods_seq = C.goods_seq AND B.option_seq = C.option_seq where B.default_option = 'y' AND A.goods_type = 'gift'";
		$query = $this->db->query($query);
		$data = $query->row_array();
		*/
		$loop['page']['all_count'] = $loop['page']['totalcount'];

		foreach($loop['record'] as $k => $datarow){

			$optstock 							= $this->goodsmodel->get_default_option($datarow['goods_seq']);
			$datarow['option_seq']				= $optstock['option_seq'];
			$datarow['reserve_rate']			= $optstock['reserve_rate'];
			$datarow['reserve_unit']			= $optstock['reserve_unit'];
			$datarow['reserve']					= $optstock['reserve'];

			$datarow['consumer_price']			= $optstock['consumer_price'];
			$datarow['price']					= $optstock['price'];
			$datarow['supply_price']			= $optstock['supply_price'];
			$datarow['default_stock']			= $optstock['stock'];
			$datarow['default_badstock']		= $optstock['badstock'];
			$datarow['default_reservation15']	= $optstock['reservation15'];
			$datarow['default_reservation25']	= $optstock['reservation25'];

			$optstocktot = $this->goodsmodel->get_tot_option($datarow['goods_seq']);
			$datarow['stock']				= $optstocktot['stock'];
			$datarow['badstock']			= $optstocktot['badstock'];
			$datarow['rstock']				= $optstocktot['rstock'];
			$datarow['a_stock']				= isset($optstocktot['a_stock']) ? $optstocktot['a_stock'] : ""; //가용재고 > 0 옵션 재고 합계
			$datarow['a_rstock']			= isset($optstocktot['a_rstock']) ? $optstocktot['a_rstock'] : ""; //가용재고 > 0 가용재고 합계
			$datarow['a_stock_cnt']			= isset($optstocktot['a_stock_cnt']) ? $optstocktot['a_stock_cnt'] : ""; //가용재고 > 0 해당옵션 갯수
			$datarow['b_stock']				= isset($optstocktot['b_stock']) ? $optstocktot['b_stock'] : ""; //가용재고 <= 0 옵션 재고 합계
			$datarow['b_rstock']			= isset($optstocktot['b_rstock']) ? $optstocktot['b_rstock'] : ""; //가용재고 <= 0 가용재고 합계
			$datarow['b_stock_cnt']			= isset($optstocktot['b_stock_cnt']) ? $optstocktot['b_stock_cnt'] : ""; //가용재고 <= 0 해당옵션 갯수
			$datarow['reservation15']		= $optstocktot['reservation15'];
			$datarow['reservation25']		= $optstocktot['reservation25'];
			$datarow['rtotal_supply_price']	= $optstocktot['rtotal_supply_price'];
			$datarow['rtotal_stock']		= $optstocktot['rtotal_stock'];
			$datarow['rtotal_badstock']		= $optstocktot['rtotal_badstock'];

			$datarow['goods_view_text']			= $datarow['goods_view']=='look' ? "노출" : "미노출";
			$datarow['provider_status_text'] 	= $datarow['provider_status']=='1' ?"<b>승인</b>" : "<b>미승인</b>";
			$datarow['number']					= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			$reservation 						= $this->ordermodel->get_reservation_for_goods($cfg_order['ableStockStep'],$datarow['goods_seq']);
			$datarow['rstock'] 					= $datarow['stock'] - $reservation;
			$datarow['goods_status_text'] 		= $this->goodslibrary->get_goods_status($datarow['goods_status']);

			// 옵션
			$datarow['options']		= $this->goodsmodel->get_goods_option($datarow['goods_seq']);

			if ($datarow['update_date']=="0000-00-00 00:00:00") {
				$datarow['update_date'] = "&nbsp;";
			}

			$loop['record'][$k] = $datarow;
		}

		return array($loop,$sc,$this->input->get('sort'));
	}


	public function gift_regist(){
		$this->admin_menu();
		$this->tempate_modules();

		if( !isset($_GET['no']) ){
			$provider_seq = $this->providerInfo['provider_seq'];
		}

		$goodsType = "gift";

		
		$limit_stock 		= '';
		$totstock 			= 0;
		$reservation15 		= 0;
		$reservation25 		= 0;

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->assign('goodsImageSize',config_load('goodsImageSize'));
		$this->template->assign('cfg_goods',config_load('goods'));
		

		## 상품 등록 관련 서브 
		$this->template->assign('goods_type',$goodsType);
		$_regist_popup_guide				= $this->skin."/goods/_regist_popup_guide.html";
		$this->template->define(array('_regist_popup_guide'=>$_regist_popup_guide));

		$tabmenu 		= $this->goodsmodel->admin_goods_regist_tab_list($goodsType);
		$bxOpenSet 		= $this->searchdefaultconfigmodel->get_search_config($goodsType);
		$this->template->assign('bxOpenSetObj',$bxOpenSet);
		$this->template->assign('tabmenu',$tabmenu);

		$tmp = config_load('reserve','default_reserve_percent');
		$default_reserve_percent = $tmp['default_reserve_percent'];

		### COMMON INFO
		$query2 = $this->db->query("select * from fm_goods_info where info_name != '' and info_provider_seq = '" . $this->providerInfo['provider_seq'] . "' order by info_seq desc");
		foreach($query2->result_array() as $v){
			$info_loop[] = $v;
		}

		if( isset($_GET['no']) ){
			$no = (int) $_GET['no'];

			$categories = $this->goodsmodel->get_goods_category($no);
			if($categories){
				foreach($categories as $key => $data) $categories[$key]['title'] = $this->categorymodel->get_category_name($data['category_code']);
			}
			$brands = $this->goodsmodel->get_goods_brand($no);


			if($brands){
				foreach($brands as $key => $data) $brands[$key]['title'] = $this->brandmodel->get_brand_name($data['category_code']);
			}

			$goods = $this->goodsmodel->get_goods($no);
			$goods['title']			= strip_tags($goods['goods_name']);
			$goods['goods_name']	= htmlspecialchars($goods['goods_name']);
			$goods['summary']		= htmlspecialchars($goods['summary']);
			$goods['purchase_goods_name']	= htmlspecialchars($goods['purchase_goods_name']);
			$goods['keyword']		= htmlspecialchars($goods['keyword']);
			$goods['string_price']	= htmlspecialchars($goods['string_price']);

			### 모바일 상품설명
			if($goods['mobile_contents']=="<P>&nbsp;</P>") $goods['mobile_contents'] = "";
			if(!$goods['mobile_contents'] && $goods['contents']){
				$goods['mobile_contents'] = $this->goodsmodel->set_mobile_contents($goods['contents'],$goods['goods_seq']);
			}

			# 수정폼 인자 고정
			$goods['provider_status'] = '1';

			# 상태값
			if($goods['goods_status']=='normal'){
				$goods['goods_status_text'] = "정상";
			}else if($goods['goods_status']=='runout'){
				$goods['goods_status_text'] = "품절";
			}else if($goods['goods_status']=='purchasing'){
				$goods['goods_status_text'] = "재고확보중";
			}else{
				$goods['goods_status_text'] = "판매중지";
			}
			$goods['goods_view_text'] = $goods['goods_view']=='look' ? "노출" : "미노출";
			$goods['provider_status_text'] = $goods['provider_status']=='1' ? "<b>승인</b>" : "<b>미승인</b>";

			// 상품 검색어 자동 제거 2018-08-07 #19650
			/*
			$result_keyword = $this->goodsmodel->set_search_keyword($goods['goods_seq'],$goods['goods_code'],$goods['goods_name'],$goods['summary'],$goods['keyword']);
			$this->template->assign(array('auto_keyword'=>$result_keyword['auto_keyword']));
			*/
			$goods['keyword'] = $goods['keyword'];

			$images = $this->goodsmodel->get_goods_image($no);
			$additions = $this->goodsmodel->get_goods_addition($no);
			$options = $this->goodsmodel->get_goods_option($no);
			$suboptions = $this->goodsmodel->get_goods_suboption($no);
			$inputs = $this->goodsmodel->get_goods_input($no);
			$icons = $this->goodsmodel->get_goods_icon($no);

			// 총재고, 출고예약량
			foreach($options as $data_option){
				$totstock += $data_option['stock'];
				$reservation15 += $data_option[$reservation15];
				$reservation25 += $data_option[$reservation25];
			}

			### 공용정보
			$info = get_data("fm_goods_info",array("info_seq"=>$goods['info_seq']));
			$goods['common_contents'] = $info ? $info[0]['info_value'] : '';

			// 배송정보 가져오기
			$delivery = $this->goodsmodel->get_goods_delivery($goods);

			$this->template->assign(array('categories'=>$categories));
			$this->template->assign(array('brands'=>$brands));
			$this->template->assign(array('goods'=>$goods));
			$this->template->assign(array('options'=>$options));
			$this->template->assign(array('additions'=>$additions));
			$this->template->assign(array('icons'=>$icons));
			$this->template->assign(array('suboptions'=>$suboptions));
			$this->template->assign(array('inputs'=>$inputs));
			$this->template->assign(array('images'=>$images));
			$this->template->assign(array('delivery'=>$delivery));

			### 옵션
			if($options){
				$cnt = 0;
				foreach($options as $k){
					$option_cnt = count($k['option_divide_title']);
					for($i=0;$i<$option_cnt;$i++){
						$opt_title[$i] = $k['option_divide_title'][$i];
						if($cnt>0){
							if(!in_array($k['opts'][$i],$opts[$i])){
								$opts[$i][] = $k['opts'][$i];
								$opt_price[$i][] = $k['price'];
							}
						}else{
							$opts[$i][] = $k['opts'][$i];
							$opt_price[$i][] = $k['price'];
						}
					}
					$cnt++;
				}
				for($i=0;$i<count($opt_title);$i++){
					$tmps['title']	= $opt_title[$i];
					$tmps['opt']	= implode(",",$opts[$i]);
					$tmps['price']	= implode(",",$opt_price[$i]);
					$opts_loop[] = $tmps;
				}
				$this->template->assign('opts_loop',$opts_loop);
			}

			### 추가옵션
			if($suboptions){
				$cnt = 0;
				$tmp_cnt = 0;
				unset($tmps);
				foreach($suboptions as $k){
					for($i=0;$i<count($k);$i++){
						if($cnt==0){
							$sopt_title[] = $k[$i]['suboption_title'];
						}else{
							if(!in_array($k[$i]['suboption_title'],$sopt_title)){
								$sopt_title[] = $k[$i]['suboption_title'];
								$tmp_cnt++;
							}
						}
						$sopt_opts[$tmp_cnt][] = $k[$i]['suboption'];
						$sopt_price[$tmp_cnt][] = $k[$i]['price'];
						$cnt++;
					}
				}
				for($i=0;$i<count($sopt_title);$i++){
					$tmps['title']	= $sopt_title[$i];
					$tmps['opt']	= implode(",",$sopt_opts[$i]);
					$tmps['price']	= implode(",",$sopt_price[$i]);
					$sopts_loop[] = $tmps;
				}

				$this->template->assign('sopts_loop',$sopts_loop);
			}

			//print_r($inputs);

			###
			$sql = "SELECT
						distinct A.*, B.*
					FROM
						fm_goods_relation A
						LEFT JOIN
						(SELECT
							g.goods_seq, g.goods_name, o.price
						FROM
							fm_goods g LEFT JOIN fm_goods_option o ON g.goods_seq = o.goods_seq AND o.default_option = 'y') B ON A.relation_goods_seq = B.goods_seq
					WHERE
						A.goods_seq = '{$no}'";
			$query = $this->db->query($sql);
			foreach ($query->result_array() as $row){
				$relation[] = $row;
			}
			if($relation) $this->template->assign('relation',$relation);

			$provider_seq = $goods['provider_seq'];
		} else {
			# 추가폼 인자고정
			$this->template->assign(array('goods'=>array('provider_status' => '1', 'goods_status' => 'normal')));
		}

		/* 입점사 세션일때 */
		if($this->adminSessionType=='provider'){
			/* 등록시 */
			if( !isset($_GET['no']) ){
				$provider_seq = $this->providerInfo['provider_seq'];
			}
			/* 수정시 */
			if( isset($_GET['no']) ){
				if($this->providerInfo['provider_seq'] != $provider_seq){
					pageBack("타 입점사의 상품입니다.");
					exit;
				}
			}

		}


		//상품코드 설정여부
		$gdtypearray 			= array("goodsaddinfo","goodsoption","goodssuboption");
		$goodscodesettingview	='';
		$wheres 				= array("codesetting=1");
		foreach($gdtypearray as $gdtype){
			unset($goodscode);
			$user_arr 			= $this->goodsmodel->get_goodsaddinfo($gdtype,$wheres);
			foreach ($user_arr as $datarow){
				$goodscodesettingview .= $datarow['label_title'].' + ';
			}
		}
		$this->template->assign('goodscodesettingview',$goodscodesettingview);


		$provider = $this->providermodel->get_provider($provider_seq);
		$provider_charge = $this->providermodel->provider_charge_list($provider_seq);
		$provider_list		= $this->providermodel->provider_goods_list();
		$this->template->assign(array('provider_seq'=>$provider_seq));
		$this->template->assign(array('provider'=>$provider));
		$this->template->assign(array('provider_charge'=>$provider_charge));
		$this->template->assign(array('provider_list'=>$provider_list));

		### PROVIDER SHIPPING
		if($provider_seq>1){
			$sql = "select * from fm_provider_shipping where provider_seq = '{$provider_seq}'";
			$query = $this->db->query($sql);
			$shipping = $query->result_array();

			$deli_text = "";
			if($shipping[0]['delivery_type']=='free'){
				$deli_text = "비용 : 무료";
			}else if($shipping[0]['delivery_type']=='pay'){
				$deli_text = "비용 : (선불) 유료 ".number_format($shipping[0]['delivery_price'])."원";
			}else if($shipping[0]['delivery_type']=='ifpay'){
				$deli_text = "비용 : ".number_format($shipping[0]['if_free_price'])."원 이상 구매 시 무료, (선불) 유료 ".number_format($shipping[0]['delivery_price'])."원";
			}
			$shipping[0]['deli_text'] = $deli_text;
			$this->template->assign('shipping',$shipping[0]);
		}

		### 품절 기준 수량
		$cfg_order = config_load('order');
		$ableStockLimit = $cfg_order['ableStockLimit'];
		if($cfg_order['runout']=='ableStock'){
			if($cfg_order['ableStockStep'] == 15){
				$limit_stock = $totstock - $reservation15 - $ableStockLimit;
			}else{
				$limit_stock = $totstock - $reservation25 - $ableStockLimit;
			}
		}else if($cfg_order['runout']=='stock'){
			$limit_stock = 0;
		}else{
			$limit_stock = 'unlimited';
		}

		### 상품 사진 사이즈 조건
		list($goodsImageSizeArr,$r_img_size) = $this->goodsmodel->get_goodsImageSize();

		# 필수옵션 경로 지정
		$templatePath	= str_replace('social_', '', $this->template_path());
		$this->template->define(array('OPTION_HTML' => str_replace('gift_regist.html', '_option_for_gift_regist.html', $templatePath)));

		//JS분리용
		$jsObjectVal['goods_seq']								= $goods['goods_seq'];
//		$jsObjectVal['tax']											= $goods['tax'];
//		$jsObjectVal['reserve_policy']							= $goods['reserve_policy'];
		$jsObjectVal['runout_policy']							= $goods['runout_policy'];
		$jsObjectVal['able_stock_limit']						= $goods['able_stock_limit'];
//		$jsObjectVal['suboption_layout_group']			= $goods['suboption_layout_group'];
//		$jsObjectVal['suboption_layout_position']		= $goods['suboption_layout_position'];
//		$jsObjectVal['inputoption_layout_group']		= $goods['inputoption_layout_group'];
//		$jsObjectVal['inputoption_layout_position']	= $goods['inputoption_layout_position'];
//		$jsObjectVal['file_key_w']								= $goods['file_key_w'];
//		$jsObjectVal['videototal']									= $goods['videototal'];
		$jsObjectVal['view_layout']								= $goods['view_layout'];
		$jsObjectVal['goods_status']							= $goods['goods_status'];
		$jsObjectVal['goods_view']								= $goods['goods_view'];
		$jsObjectVal['provider_status']						=	$goods['provider_status'];
		$jsObjectVal['string_price_use']						= $goods['string_price_use'];
//		$jsObjectVal['multi_discount_use']					= $goods['multi_discount_use'];
//		$jsObjectVal['multi_discount_unit']					= $goods['multi_discount_unit'];
//		$jsObjectVal['min_purchase_limit']					= $goods['min_purchase_limit'];
//		$jsObjectVal['max_purchase_limit']					= $goods['max_purchase_limit'];
//		$jsObjectVal['max_purchase_order_limit']		= $goods['max_purchase_order_limit'];
//		$jsObjectVal['member_input_use']					= $goods['member_input_use'];
//		$jsObjectVal['shipping_policy']							= $goods['shipping_policy'];
//		$jsObjectVal['goods_shipping_policy']				= $goods['goods_shipping_policy'];
//		$jsObjectVal['shipping_weight_policy']			= $goods['shipping_weight_policy'];
//		$jsObjectVal['info_seq']									= $goods['info_seq'];
//		$jsObjectVal['common_info_seq']					= $goods['common_info_seq'];
//		$jsObjectVal['editor_view']								= $goods['editor_view'];
//		$jsObjectVal['relation_type']							= $goods['relation_type'];
//		$jsObjectVal['relation_seller_type']					= $goods['relation_seller_type'];
//		$jsObjectVal['editor_view']								= $goods['editor_view'];
//		$jsObjectVal['relation_type']							= $goods['relation_type'];
//		$jsObjectVal['individual_refund']						= $goods['individual_refund'];
//		$jsObjectVal['individual_refund_inherit']			= $goods['individual_refund_inherit'];
//		$jsObjectVal['individual_export']						= $goods['individual_export'];
//		$jsObjectVal['individual_return']						= $goods['individual_return'];
//		$jsObjectVal['goods_name_linkage']				= $goods['goods_name_linkage'];
//		$jsObjectVal['display_terms']							= ($goods['display_terms'] == 'AUTO') ? 'AUTO' : 'MENUAL';
//
//
		$jsObjectVal['goods_image_size']					= $goodsImageSizeArr;
//		$jsObjectVal['imageLabel']								= $images[1]['large']['label'];
//		$jsObjectVal['inputs']										= $inputs;
//		$jsObjectVal['goods_uccdomain']					= uccdomain('fileswf',$goods['file_key_w']);
//		$jsObjectVal['cfg_goods']								= $cfg_goods_default;
//		$jsObjectVal['isAddr']										= $isAddr;
//		$jsObjectVal['linkage_service']						= $LINKAGE_SERVICE;
//		$jsObjectVal['adult_chk']									= $realname['adult_chk'];
//		$jsObjectVal['iconCount']									= (is_array($icons) === true) ? count($icons) : 0;
//		$jsObjectVal['additionCount']							= (is_array($additions) === true) ? count($additions) : 0;
//		$jsObjectVal['imagesCount']							= (is_array($images) === true) ? count($images) : 0;

		$jsObjectJson													= json_encode($jsObjectVal);

		## HSCODE
		$this->load->model("multisupportmodel");
		$r_hscode = $this->multisupportmodel->get_hscode_list();
		$this->template->assign('r_hscode',$r_hscode);
		# 상품의 HSCODE정보 @2016-10-27
		if($goods['hscode']){
			$hscode_info	= $this->multisupportmodel->get_hscode_info($goods['hscode']);
			$this->template->assign('hscode',$hscode_info);
		}

		$this->template->assign(array('scm_cfg'=>$this->scm_cfg));
		$this->template->assign('query_string',$_GET['query_string']);
		$this->template->assign(array('r_img_size'=>$r_img_size));
		$this->template->assign('info_loop',$info_loop);
		$this->template->assign(array('default_reserve_percent'=>$default_reserve_percent));
		$this->template->assign(array('last_categories'=>$last_categories));
		$this->template->assign(array('last_brands'=>$last_brands));
		$this->template->assign(array('limit_stock'=>$limit_stock));
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign('goodsObj', $jsObjectJson);
		$this->template->print_("tpl");
	}

	public function popup_string_price()
	{
		$no = $this->input->get('no');
		$data_goods = $this->goodsmodel->get_goods($no);

		$arr_string_price = array(
				'string_price_use',
				'string_price',
				'string_price_color',
				'string_price_link',
				'string_price_link_url',
				'string_price_link_target',
				'member_string_price_use',
				'member_string_price',
				'member_string_price_color',
				'member_string_price_link',
				'member_string_price_link_url',
				'member_string_price_link_target',
				'allmember_string_price_use',
				'allmember_string_price',
				'allmember_string_price_color',
				'allmember_string_price_link',
				'allmember_string_price_link_url',
				'allmember_string_price_link_target',
				'string_button_use',
				'string_button',
				'string_button_color',
				'string_button_link',
				'string_button_link_url',
				'string_button_link_target',
				'member_string_button_use',
				'member_string_button',
				'member_string_button_color',
				'member_string_button_link',
				'member_string_button_link_url',
				'member_string_button_link_target',
				'allmember_string_button_use',
				'allmember_string_button',
				'allmember_string_button_color',
				'allmember_string_button_link',
				'allmember_string_button_link_url',
				'allmember_string_button_link_target',
		);

		$jsonValue = array();
		foreach($arr_string_price as $_field){
			$jsonValue[$_field] = $data_goods[$_field];
		}
		$this->template->assign($jsonValue);
		$this->template->assign('jsonStringData',json_encode($jsonValue));
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function set_goods_options(){
		
		$this->load->model('providermodel');

		$aGetParams = $this->input->get();

		$addgoods		= trim($aGetParams['add_goods_seq']);//자주사용옵션의 상품
		$goods_seq		= trim($aGetParams['goods_seq']);
		$tmp_seq		= trim($aGetParams['tmp_seq']);
		$tmp_policy		= trim($aGetParams['tmp_policy']);
		$islimit		= trim($aGetParams['islimit']);
		$goodsTax		= trim($aGetParams['goodsTax']);
		$mode			= trim($aGetParams['mode']);
		$provider_seq	= trim($this->providerInfo['provider_seq']);

		$provider_info	= $this->providermodel->provider_charge_list($provider_seq);
		$default_charge = $provider_info[0];
		
		if($aGetParams['socialcp_input_type']){
			define('SOCIALCPUSE',true);
			$this->template->assign('socialcpuse',1);
		}

		$this->tempate_modules();

		// 상품 수정일 경우 기존 옵션 정보를 임시로 가져온다.
		if	($goods_seq && !$tmp_seq){
			if($addgoods)	$tmp_seq	= $this->goodsmodel->add_option_tmp_to_option_org($addgoods, 'import', $provider_info[0]);
			else			$tmp_seq	= $this->goodsmodel->add_option_tmp_to_option_org($goods_seq, '', $provider_info[0]);

			$this->template->assign(array('goods_seq'=>$goods_seq));
			$this->template->assign(array('tmp_policy'=>$tmp_policy));
			$this->template->assign(array('reload'=>'y'));
			$this->template->assign(array('islimit'=>$islimit));
		// 상품 신규 등록에서 옵션 초기 등록 시
		}elseif(!$goods_seq && !$tmp_seq){
			if($addgoods)	$tmp_seq	= $this->goodsmodel->add_option_tmp_to_option_org($addgoods, 'import', $provider_info[0]);
			else			$tmp_seq	= $this->goodsmodel->add_option_tmp_to_option_org($goods_seq, '', $provider_info[0]);

			$goods_info['reserve_policy']	= $tmp_policy;
			$this->template->assign(array('tmp_policy'=>$tmp_policy));
			$this->template->assign(array('goods'=>$goods_info));
			$this->template->assign(array('reload'=>'y'));
			$this->template->assign(array('islimit'=>$islimit));
		}else{
			if( !isset($aGetParams['optionViewTypeTmp']) && isset($_COOKIE['optionViewType_'.$aGetParams['tmp_seq']])) {
				$aGetParams['optionViewTypeTmp'] = $_COOKIE['optionViewType_'.$aGetParams['tmp_seq']];
			}
			// 기본 정책 정보
			$reserves		= ($this->reserves)?$this->reserves:config_load('reserve');
			$point_text		= "";
			if($reserves['point_use']=='Y'){
				switch($reserves['default_point_type']){
					case "per":
						$point_text = "※ 지급 포인트(P) ".$reserves['default_point_percent']."%";
						break;
					case "app":
						$point_text = "※ 지급 포인트(P) ".number_format($reserves['default_point_app'])."원당 ".$reserves['default_point']."포인트";
						break;
					default :
						$point_text = "";
						break;
				}
			}else{
				$point_text = "※ 지급 포인트(P) 없음";
			}

			### 품절 기준 수량
			$cfg_order = config_load('order');
			$ableStockLimit = $cfg_order['ableStockLimit'];
			if($cfg_order['runout']=='ableStock'){
				if($cfg_order['ableStockStep'] == 15){
					$limit_stock = $totstock - $reservation15 - $ableStockLimit;
				}else{
					$limit_stock = $totstock - $reservation25 - $ableStockLimit;
				}
			}else if($cfg_order['runout']=='stock'){
				$limit_stock = 0;
			}else{
				$limit_stock = 'unlimited';
			}

			// 임시 옵션 정보
			$tmp_option_list	= array();
			if	($tmp_seq)	$tmp_option_list	= $this->goodsmodel->get_option_tmp_list($tmp_seq);

			// 상품 정보 추출
			if	(!$goods_seq)
				$goods_seq			= $tmp_option_list[0]['goods_seq'];
			if	($goods_seq)
				$goods_info			= $this->goodsmodel->get_goods($goods_seq);
			if	($goods_info['goods_seq'])	$reserve_policy	= $goods_info['reserve_policy'];

			// 총재고, 출고예약량
			foreach($tmp_option_list as $key_option => $data_option){
				// 패키지 에러 가져오기
				$params = array('type'=>'option','parent_seq'=>$data_option['org_option_seq']);
				$this->errorpackage->get_error($params);
				foreach($this->errorpackage->get_error($params)->result_array() as $data_error){
					if($data_error['error_code']){
						$idx = substr($data_error['error_code'],1,1);
						$error_code = substr($data_error['error_code'],2,2);
						$data_option['package_error_code'.$idx] = $error_code;
					}
				}

				$totstock += $data_option['stock'];
				$reservation15 += $data_option['reservation15'];
				$reservation25 += $data_option['reservation25'];
				if	($cfg_order['ableStockStep'] == 15){
					$totunUsableStock	+= $data_option['badstock'] + $data_option['reservation15'];
				}
				if	($cfg_order['ableStockStep'] == 25){
					$totunUsableStock	+= $data_option['badstock'] + $data_option['reservation25'];
				}

				//정산금액 계산식 수정 leewh 2014-12-24
				//$data_option['commission_price']	= $data_option['price'] * ($data_option['commission_rate'] / 100);
				//$data_option['commission_price']	= $data_option['price'] - floor($data_option['price'] * $data_option['commission_rate'] / 100);

				# 정산기준금액
				switch($data_option['commission_type']){
					case	'SUCO' :
						$commission_price_krw	= $data_option['consumer_price'];
						break;
					case	'SUPR' :
						$commission_price_krw	= $data_option['commission_rate'];
						break;
					default	:
						$commission_price_krw	= $data_option['price'];
				}

				# 정산기준금액(원화로 변환)
				if($this->config_system['basic_currency'] != "KRW"){
					$commission_price_krw = get_currency_exchange($commission_price_krw,'KRW',$this->config_system['basic_currency'],'backoffice');
				}

				# 정산금액계산
				switch($data_option['commission_type']){
					case	'SUCO' :
						$data_option['commission_price']	= round($commission_price_krw / 100 * $data_option['commission_rate']);
						break;
					case	'SUPR' :
						$data_option['commission_price']	= round($commission_price_krw);
						break;

					default	:
						$fee_price							= round($commission_price_krw / 100 * $data_option['commission_rate']);
						$data_option['commission_price']	= $commission_price_krw - $fee_price;
				}

				$data_option['commission_price']	= get_cutting_price($data_option['commission_price'],'KRW','backoffice');

				// 기본정책일 경우 마일리지
				$data_option['shop_reserve']		= floor($data_option['price'] * ($reserves['default_reserve_percent'] * 0.01));

				// 임시 저장된 마일리지 정책 로드
				if	($data_option['tmp_policy'])
					$goods_info['reserve_policy']	= $data_option['tmp_policy'];
				if	(!$goods_info['reserve_policy'])
					$goods_info['reserve_policy']	= 'shop';

				// 기본정책일 경우 마일리지 표기
				if	($goods_info['reserve_policy'] == 'shop'){
					$data_option['reserve_rate']	= $reserves['default_reserve_percent'];
					$data_option['reserve_unit']	= 'percent';
					$data_option['reserve']			= get_cutting_price($data_option['price'] * ($reserves['default_reserve_percent'] * 0.01));

				// 개별정책일 경우 마일리지 계산
				}else{
					if	($data_option['reserve_unit'] == 'percent')
						$data_option['reserve']			= get_cutting_price($data_option['price'] * ($data_option['reserve_rate'] * 0.01));
					else
						$data_option['reserve']			= $data_option['reserve_rate'];
				}

				$data_min = array();
				if($goods_info['package_yn'] == 'y'){
					// 패키지 에러 가져오기
					$params = array('type'=>'option','parent_seq'=>$data_option['option_seq']);
					foreach($this->errorpackage->get_error($params)->result_array() as $data_error){
						if($data_error['error_code']){
							$idx = substr($data_error['error_code'],1,1);
							$error_code = substr($data_error['error_code'],2,2);
							$data_option['package_error_code'.$idx] = $error_code;
						}
					}
					for($package_num = 1;$package_num < 6;$package_num++){
						$option_field = 'package_option'.$package_num;
						$option_seq_field = 'package_option_seq'.$package_num;
						$option_unit_field = 'package_unit_ea'.$package_num;

						if($data_option[$option_seq_field]){
							$data_package = $this->goodsmodel->get_package_by_option_seq($data_option[$option_seq_field]);
							$data_option['package_goods_seq'.$package_num] = $data_package['package_goods_seq'];
							$data_option['package_stock'.$package_num] = $data_package['package_stock'];
							$data_option['package_badstock'.$package_num] = $data_package['package_badstock'];
							$data_option['package_ablestock'.$package_num] = $data_package['package_ablestock'];
							$data_option['package_safe_stock'.$package_num] = $data_package['package_safe_stock'];
							$data_option['package_option_code'.$package_num] = $data_package['package_option_code'];
							$data_option['weight'.$package_num] = $data_package['weight'];

							$data_packages = $this->goodsmodel->get_package_stock($data_option[$option_seq_field],$data_option[$option_unit_field]);
							if($data_packages['option_seq'] && (empty($data_min) || $data_min['unit_stock'] > $data_packages['unit_stock'])) {
								$data_min = $data_packages;
								$data_min['unit_ea'] = $data_option[$option_unit_field];
							}
						}
					}
					if(!empty($data_min)) {
						$data_option['stock'] = $data_min['unit_stock'];
						$data_option['badstock'] = $data_min['unit_badstock'];
						$data_option['reservation15'] = $data_min['unit_reservation15'];
						$data_option['reservation25'] = $data_min['unit_reservation25'];
					}
				}

				$data_option['optioncode1']		= trim($data_option['optioncode1']);
				$data_option['optioncode2']		= trim($data_option['optioncode2']);
				$data_option['optioncode3']		= trim($data_option['optioncode3']);
				$data_option['optioncode4']		= trim($data_option['optioncode4']);
				$data_option['optioncode5']		= trim($data_option['optioncode5']);

				$data_option['optioncode']		= $data_option['optioncode1'].$data_option['optioncode2'].$data_option['optioncode3'].$data_option['optioncode4'].$data_option['optioncode5'];

				// 패키지 상품 데이터 배열화
				if($data_option['package_count'] > 0){
					for($package_i=1; $package_i<6; $package_i++){
						$data_option['package_goods_seq'][$package_i] 	= $data_option['package_goods_seq'.$package_i];
						$data_option['package_stock'][$package_i] 		= $data_option['package_stock'.$package_i];
						$data_option['package_badstock'][$package_i] 	= $data_option['package_badstock'.$package_i];
						$data_option['package_ablestock'][$package_i] 	= $data_option['package_ablestock'.$package_i];
						$data_option['package_goods_code'][$package_i] 	= $data_option['package_goods_code'.$package_i];
						$data_option['package_option_code'][$package_i] = $data_option['package_option_code'.$package_i];
						$data_option['package_weight'][$package_i] 		= $data_option['weight'.$package_i];
						$data_option['package_goods_name'][$package_i] 	= $data_option['package_goods_name'.$package_i];
						$data_option['package_option_seq'][$package_i] 	= $data_option['package_option_seq'.$package_i];
						$data_option['package_option'][$package_i] 		= $data_option['package_option'.$package_i];
						$data_option['package_unit_ea'][$package_i] 	= $data_option['package_unit_ea'.$package_i];
					}
				}

				$tmp_option_list[$key_option]	= $data_option;
			}

			//상품추가양식 정보
			if	($mode != 'view')
				$goodsoptionloop	= $this->goodsmodel->get_add_option_code();

			for($i=0;$i<count($tmp_option_list[0]['option_divide_title']);$i++){
				$tmps['title']					= $tmp_option_list[0]['option_divide_title'][$i];
				$tmps['type']					= $tmp_option_list[0]['option_divide_type'][$i];
				$tmps['code_seq']				= $tmp_option_list[$i]['option_divide_codeseq'][$i];
				$tmps['opt']					= implode(",",$tmp_option_list[0]['optionArr'][$i]);
				$tmps['optcodes']				= implode(",",$tmp_option_list[0]['codeArr'][$i]);
				$tmps['price']					= implode(",",$tmp_option_list[0]['priceArr'][$i]);
				if( $tmp_option_list[0]['divide_newtype'][$i] == 'color' ) {
					$tmps['colors']				= implode(",",$tmp_option_list[0]['colorArr'][0]);
				}elseif( $tmp_option_list[0]['divide_newtype'][$i] == 'address' ) {
					$isAddr						= "Y";
					$tmps['zipcodes']			= implode(",",$tmp_option_list[0]['zipcodeArr'][0]);
					$tmps['addresss']			= implode(",",$tmp_option_list[0]['addressArr'][0]);
					$tmps['addressdetails']		= implode(",",$tmp_option_list[0]['addressdetailArr'][0]);
					$tmps['biztels']			= implode(",",$tmp_option_list[0]['biztelArr'][0]);
					$tmps['address_commissions']= implode(",",$tmp_option_list[0]['address_commissionArr'][0]);
				}elseif( $tmp_option_list[0]['divide_newtype'][$i] == 'date' ) {
					$tmps['codedates']			= implode(",",$tmp_option_list[0]['codedateArr'][0]);
				}

				$tmps['sdayinput']			= $tmp_option_list[0]['sdayinput'];
				$tmps['fdayinput']			= $tmp_option_list[0]['fdayinput'];
				$tmps['dayauto_type']		= $tmp_option_list[0]['dayauto_type'];

				$tmps['dayauto_type_title'] = $this->goodsmodel->dayautotype[$tmp_option_list[0]['dayauto_type']];
				$tmps['sdayauto']			= $tmp_option_list[0]['sdayauto'];
				$tmps['fdayauto']			= $tmp_option_list[0]['fdayauto'];
				$tmps['dayauto_day']		= $tmp_option_list[0]['dayauto_day'];
				$tmps['dayauto_day_title'] 	= $this->goodsmodel->dayautoday[$tmp_option_list[0]['dayauto_day']];

				if( $tmp_option_list[0]['divide_newtype'][$i] == 'dayauto' ) {
					$dayautodate					= goods_dayauto_setting_day( date("Y-m-d") ,
															$tmps['sdayauto'], $tmps['fdayauto'],
															$tmps['dayauto_type'], $tmps['dayauto_day']);
					$tmps['social_start_date_end']	= $dayautodate['social_start_date'] . "~"
													. $dayautodate['social_end_date'];
				}


				$tmps['newtype']				= $tmp_option_list[0]['divide_newtype'];

				$tmps['goodsoptionloop']	= $goodsoptionloop;
				$opts_loop[]				= $tmps;
				unset($tmps);
			}

			//자주쓰는 필수옵션
			if( $tmp_option_list[0]['package_count'] ) $package_yn = 'y';
			if( isset($aGetParams['package_yn']) && $package_yn == '' ) $package_yn = $aGetParams['package_yn'];
			$freqloop	= $this->goodsmodel->frequentlygoods('opt',$goods_seq,defined('SOCIALCPUSE'),$package_yn);
			if($freqloop) {
				$$freqloophtml = '';
				foreach( $freqloop as $freqkey => $freqdata ){
					$$freqloophtml .= "<option value='".$freqdata['goods_name']."^^".$freqdata['goods_seq']."' >".$freqdata['goods_name']."</option>";
				}
			}

			// 옵션 기본 노출 수량 적용
			$config_goods	= config_load('goods');

			// 옵션 기본 노출 수량 적용 개선 leewh 2015-04-24
			$cfg_goods_default = array();

			$gkind = 'goods';
			if (defined('SOCIALCPUSE') === true) {
				$gkind = 'coupon';
			}
			if ($tmp_option_list[0]['package_count']) {
				$gkind='package_'.$gkind;
			}

			$result = $this->goodsmodel->get_goods_default_config($gkind);
			if ($result) {
				$cfg_goods_default = $result;
			} else {
				if (isset($config_goods['option_view_count'])) {
					$cfg_goods_default['option_view_count'] = $config_goods['option_view_count'];
				}
				if (isset($config_goods['suboption_view_count'])) {
					$cfg_goods_default['suboption_view_count'] = $config_goods['suboption_view_count'];
				}
			}

			if	(!$goods_info['provider_seq'] && $aGetParams['provider_seq'])
				$goods_info['provider_seq']	= $aGetParams['provider_seq'];

			$goods_info['goods_code'] = $aGetParams['goodsCode'];

			$this->template->assign(array(
				'islimit'			=> $islimit,
				'config_goods'		=> $cfg_goods_default,
				'reserves'			=> $reserves,
				'point_text'		=> $point_text,
				'limit_stock'		=> $limit_stock,
				'cfg_order'			=> $cfg_order,
				'isAddr'			=> $isAddr,
				'frequentlyopt'		=> $$freqloophtml,
				'tmp_policy'		=> $tmp_policy,
				'totstock'			=> $totstock,
				'totunUsableStock'	=> $totunUsableStock,
				'goods'				=> $goods_info,
				'goods_seq'			=> $goods_seq,
				'goodsoptionloop'	=> $goodsoptionloop,
				'options'			=> $tmp_option_list,
				'opts_loop'			=> $opts_loop,
				'provider_seq'		=> $provider_seq,
				'mode'				=> $aGetParams['mode'],
				'package_yn'		=> $aGetParams['package_yn'],
				'goodsTax'			=> $goodsTax,
				'default_charge'	=> $default_charge,
			));
		}

		// 올인원만 무조건 y / 일반은 모두 검색이 가능해야함
		// package_yn 는 view 에 영향을 끼쳐 자주쓰는 추가구성옵션 리스트 용도로 freq_package_yn 추가함
		if	($this->scm_cfg['use'] == 'Y'){
			$freq_package_yn = 'y';
		}
		$freq_package_yn = $package_yn;
		//필수옵션 관리
		$frequentlyoptlistAll 	= $this->goodsmodel->frequentlygoodsPaging('opt',$goods_seq,defined('SOCIALCPUSE'),$freq_package_yn,'all');
		$this->template->assign(array('frequentlyoptlistAll'=>$frequentlyoptlistAll['result']));
		$frequentlyoptlist 		= $this->goodsmodel->frequentlygoodsPaging('opt',$goods_seq,defined('SOCIALCPUSE'),$freq_package_yn, 1, 10);
		$this->template->assign(array('frequentlyoptlist'=>$frequentlyoptlist['result']));
		$frequentlyoptpaginlay 	= pagingtagjs(1, 10, $frequentlyoptlist['total'], 'frequentlypaging([:PAGE:], \'opt\', \''.$freq_package_yn.'\', \'optionSettingPopup\')');
		$this->template->assign('frequentlyoptpaginlay', $frequentlyoptpaginlay);
		
		$cfg_goods = config_load('goods');
		$cfg_goods['videototalcut']  = 5;//5건까지만등록가능
		if( $cfg_goods['ucc_domain'] && $cfg_goods['ucc_key'] ){
			$cfg_goods['video_use']=  'Y';
		}

		if	($this->scm_cfg['use'] == 'Y'){
			$this->load->model('scmmodel');
		}

		$this->template->assign('scm_cfg',$this->scm_cfg);
		$this->template->assign('scmTotalStock',$scmTotalStock);
		$this->template->assign('cfg_goods',$cfg_goods);
		$this->template->assign(array('tmp_seq'=>$tmp_seq));
		$this->template->assign('freqList', $freqloop);

		$filePath	= $this->template_path();

		// 패키지
		if($aGetParams['package_yn']  == 'y'){
			$package_count = 2;
			if($tmp_option_list[0]['package_count']){
				$package_count = $tmp_option_list[0]['package_count'];
			}
			$this->template->assign(array('package_count'=> $package_count));
		}

		//상품코드 설정여부
		$gdtypearray 			= array("goodsaddinfo","goodsoption","goodssuboption");
		$goodscodesettingview	='';
		$wheres 				= array("codesetting=1");
		foreach($gdtypearray as $gdtype){
			unset($goodscode);
			$user_arr 			= $this->goodsmodel->get_goodsaddinfo($gdtype,$wheres);
			foreach ($user_arr as $datarow){
				$goodscodesettingview .= $datarow['label_title'].' + ';
			}
		}
		$this->template->assign('goodscodesettingview',$goodscodesettingview);
		$this->template->assign('sc', $aGetParams);

		$aGetParams['options_cnt'] 				= count($tmp_option_list[0]['option_divide_title']);
		$aGetParams['package_count'] 			= $package_count;
		$aGetParams['scm_use']					= $this->scm_cfg['use'];
		$aGetParams['tmp_seq']					= $tmp_seq;
		$aGetParams['mode']						= ($mode)? $mode:'';
		$this->template->assign(array('scObj'=>json_encode($aGetParams)));

		$defines = array(
			'tpl'		=> $filePath,
			'ONLY_VIEW'		=> str_replace('set_goods_options', 'view_goods_options', $filePath),
			'EDIT_VIEW'		=> str_replace('set_goods_options', 'edit_goods_options', $filePath),
			'CREATE_OPTION'	=> str_replace('set_goods_options', 'create_goods_options', $filePath),
		);

		$this->template->define($defines);
		$this->template->print_("tpl");
	}

	public function set_goods_suboptions(){
		$cfg_order = config_load('order');
		$aGetParams	= $this->input->get();

		if	($this->scm_cfg['use'] == 'Y'){
			$this->load->model('scmmodel');
		}

		// 기본 정책 정보
		$reserves		= ($this->reserves) ? $this->reserves:config_load('reserve');

		$addgoods		= trim($aGetParams['add_goods_seq']);//자주사용옵션의 상품
		$goods_seq		= trim($aGetParams['goods_seq']);
		$tmp_seq		= trim($aGetParams['tmp_seq']);
		$sub_tmp_policy	= trim($aGetParams['sub_tmp_policy']);
		$islimit		= trim($aGetParams['islimit']);
		$goodsTax		= trim($aGetParams['goodsTax']);
		$this->template->assign(array('goodsTax'=>$goodsTax));
		$mode			= trim($aGetParams['mode']);
		$this->template->assign(array('mode'=>$mode));

		$provider_seq	= $this->providerInfo['provider_seq'];
		$this->template->assign(array('provider_seq'=>$provider_seq));

		$this->load->model("providermodel");
		$provider_info	= $this->providermodel->provider_charge_list($aGetParams['provider_seq']);
		$this->template->assign(array('provider_info'=>$provider_info[0]));

		if($aGetParams['socialcp_input_type']){
			define('SOCIALCPUSE',true);
			$this->template->assign('socialcpuse',1);
		}

		$this->tempate_modules();

		// 상품 수정일 경우 기존 옵션 정보를 임시로 가져온다.
		if	($goods_seq && !$tmp_seq){
			if	($sub_tmp_policy == 'shop')	$addMod	= $mode;
			if($addgoods) {//자주사용옵션 상품정보
				$tmp_seq		= $this->goodsmodel->add_suboption_tmp_to_suboption_org($addgoods, $addMod, 'import');
			}else{
				$tmp_seq		= $this->goodsmodel->add_suboption_tmp_to_suboption_org($goods_seq, $addMod);
			}
			$this->template->assign(array('sub_tmp_policy'=>$sub_tmp_policy));
			$this->template->assign(array('reload'=>'y'));
			$this->template->assign(array('islimit'=>$islimit));
		}elseif(!$goods_seq && !$tmp_seq){
			if	($sub_tmp_policy == 'shop')	$addMod	= $mode;
			if($addgoods) {//자주사용옵션 상품정보
				$tmp_seq		= $this->goodsmodel->add_suboption_tmp_to_suboption_org($addgoods, $addMod, 'import');
			}else{
				$tmp_seq		= $this->goodsmodel->add_suboption_tmp_to_suboption_org($goods_seq, $addMod);
			}
			$goods_info['sub_reserve_policy']	= $sub_tmp_policy;
			$this->template->assign(array('goods'=>$goods_info));
			$this->template->assign(array('reload'=>'y'));
			$this->template->assign(array('islimit'=>$islimit));
		}else{
			// 임시 옵션 정보
			$tmp_option_list	= array();
			if	($sub_tmp_policy == 'shop')	$addMod	= $mode;
			if	($tmp_seq)
				$tmp_option_list	= $this->goodsmodel->get_suboption_tmp_list($tmp_seq, $addMod);

			//상품추가양식 정보
			$goodssuboptionloop	= $this->goodsmodel->get_add_suboption_code();

			if	(!$goods_seq)
				$goods_seq	= $tmp_option_list[0][0]['goods_seq'];

			if	($goods_seq)
				$goods_info	= $this->goodsmodel->get_goods($goods_seq);


			// 총재고, 출고예약량
			if	($tmp_option_list)foreach($tmp_option_list as $key_suboption => $data_suboption){
				if	($data_suboption)foreach($data_suboption as $key_sub => $data_sub){
					// 패키지 에러 가져오기
					$data_sub['package_error_code1'] = '';
					$data_sub['package_error_code2'] = '';
					$data_sub['package_error_code3'] = '';
					$data_sub['package_error_code4'] = '';
					$data_sub['package_error_code5'] = '';
					$params = array('type'=>'suboption','parent_seq'=>$data_sub['org_suboption_seq']);
					foreach($this->errorpackage->get_error($params)->result_array() as $data_error){
						if($data_error['error_code']){
							$idx = substr($data_error['error_code'],1,1);
							$error_code = substr($data_error['error_code'],2,2);
							$data_sub['package_error_code'.$idx] = $error_code;
						}
					}
					//$data_suboption[$key_sub] = $data_sub['package_error_code1'];
					if	($key_sub == 0){
						$tmps['title']				= $tmp_option_list[$key_suboption][0]['suboption_title'];
						$tmps['type']				= $tmp_option_list[$key_suboption][0]['suboption_type'];

						$tmps['code_seq']			= $tmp_option_list[$key_suboption][0]['code_seq'];
						$tmps['opt']				= implode(",",$tmp_option_list[$key_suboption][0]['optArr']);
						$tmps['optcodes']			= implode(",",$tmp_option_list[$key_suboption][0]['codeArr']);

						$tmps['newtype']			= $tmp_option_list[$key_suboption][0]['newtype'];

						$tmps['colors']				= implode(",",$tmp_option_list[$key_suboption][0]['colorArr']);
						$tmps['zipcodes']			= implode(",",$tmp_option_list[$key_suboption][0]['zipcodeArr']);
						$tmps['addresss']			= implode(",",$tmp_option_list[$key_suboption][0]['addressArr']);
						$tmps['addressdetails']		= implode(",",$tmp_option_list[$key_suboption][0]['addressdetailArr']);
						$tmps['biztels']			= implode(",",$tmp_option_list[$key_suboption][0]['biztelArr']);
						$tmps['codedates']			= implode(",",$tmp_option_list[$key_suboption][0]['codedateArr']);

						$tmps['sdayinput']			= $tmp_option_list[$key_suboption][0]['sdayinput'];
						$tmps['fdayinput']			= $tmp_option_list[$key_suboption][0]['fdayinput'];
						$tmps['dayauto_type']		= $tmp_option_list[$key_suboption][0]['dayauto_type'];
						$tmps['dayauto_type_title'] = $this->goodsmodel->dayautotype[$tmp_option_list[$key_suboption][0]['dayauto_type']];
						$tmps['sdayauto']			= $tmp_option_list[$key_suboption][0]['sdayauto'];
						$tmps['fdayauto']			= $tmp_option_list[$key_suboption][0]['fdayauto'];
						$tmps['dayauto_day']		= $tmp_option_list[$key_suboption][0]['dayauto_day'];
						$tmps['dayauto_day_title'] = $this->goodsmodel->dayautoday[$tmp_option_list[$key_suboption][0]['dayauto_day']];

						if( $tmps['newtype'] == 'dayauto' ) {
							$dayautodate = goods_dayauto_setting_day( date("Y-m-d") , $tmps['sdayauto'], $tmps['fdayauto'], $tmps['dayauto_type'], $tmps['dayauto_day'] );
							$tmps['social_start_date_end']=$dayautodate['social_start_date']."~".$dayautodate['social_end_date'];
						}

						$tmps['price']				= implode(",",$tmp_option_list[$key_suboption][0]['priceArr']);
						$tmps['goodssuboptionloop']	= $goodssuboptionloop;
						$sopts_loop[] = $tmps;
					}

					$totstock += $data_sub['stock'];
					$reservation15 += $data_sub['reservation15'];
					$reservation25 += $data_sub['reservation25'];
					if	($cfg_order['ableStockStep'] == 15){
						$totunUsableStock	+= $data_sub['badstock'] + $data_sub['reservation15'];
					}
					if	($cfg_order['ableStockStep'] == 25){
						$totunUsableStock	+= $data_sub['badstock'] + $data_sub['reservation25'];
					}

					// 기본정책일 경우 마일리지 표기
					if($sub_tmp_policy == 'shop'){
						$data_sub['reserve_rate']	= $reserves['default_reserve_percent'];
						$data_sub['reserve_unit']	= 'percent';
						$data_sub['reserve']		= floor($data_sub['price'] * ($reserves['default_reserve_percent'] * 0.01));
						$data_suboption[$key_sub]	= $data_sub;
					}else{
						$data_suboption[$key_sub]	= $data_sub;
					}
				}
				$tmp_option_list[$key_suboption]	= $data_suboption;
			}

			if ($sub_tmp_policy)
				$sub_goods_info['sub_reserve_policy']	= $sub_tmp_policy;

			$suboption_package_count = $tmp_option_list[0][0]['package_count'];
			if( $suboption_package_count > 0 ){
				$package_yn = 'y';
			}else{
				$package_yn = 'n';
			}

			$aGetParams['package_yn_suboption'] = $package_yn;

			
			//자주쓰는 추가구성옵션
			$freqloop = $this->goodsmodel->frequentlygoods('sub',$goods_seq,defined('SOCIALCPUSE'),$package_yn);
			if($freqloop) {
				$freqloophtml = '';
				foreach( $freqloop as $freqkey => $freqdata ){
					$freqloophtml .= "<option value='".$freqdata['goods_name']."^^".$freqdata['goods_seq']."' >".$freqdata['goods_name']."</option>";
				}
			}

			// 옵션 기본 노출 수량 적용
			$cfg_goods_default = array();

			$gkind = 'goods';
			if (defined('SOCIALCPUSE') === true) {
				$gkind = 'coupon';
			}
			if ($tmp_option_list[0][0]['package_count']) {
				$gkind='package_'.$gkind;
			}

			$result = $this->goodsmodel->get_goods_default_config($gkind);
			if ($result) {
				$cfg_goods_default = $result;
			} else {
				if (isset($config_goods['option_view_count'])) {
					$cfg_goods_default['option_view_count'] = $config_goods['option_view_count'];
				}
				if (isset($config_goods['suboption_view_count'])) {
					$cfg_goods_default['suboption_view_count'] = $config_goods['suboption_view_count'];
				}
			}

			if(!$suboption_package_count) $aGetParams['package_yn'] ='n';
			else $aGetParams['package_yn'] ='y';

			$this->template->assign(array('config_goods'=>$cfg_goods_default));
			$this->template->assign(array('suboption_package_count'=>$suboption_package_count));
			$this->template->assign(array('frequentlyopt'=>$freqloophtml));
			$this->template->assign(array('islimit'=>$islimit));
			$this->template->assign(array('totstock'=>$totstock));
			$this->template->assign(array('totunUsableStock'=>$totunUsableStock));
			$this->template->assign(array('goods'=>$goods_info));
			$this->template->assign(array('goods_seq'=>$goods_seq));
			$this->template->assign(array('goodssuboptionloop'=>$goodssuboptionloop));
			$this->template->assign(array('reserve_policy'=>$reserve_policy));
			$this->template->assign(array('suboptions'=>$tmp_option_list));
			$this->template->assign(array('sopts_loop'=>$sopts_loop));
			$this->template->assign(array('reserves'=>$reserves));
		}


		$query 		= $this->providermodel->get_provider_charge($provider_seq, '', '','*');
		$chargedata = $query->result_array();
		$this->template->assign("default_charge",$chargedata[0]);

		$cfg_goods = config_load('goods');
		$cfg_goods['videototalcut']  = 5;//5건까지만등록가능
		if( $cfg_goods['ucc_domain'] && $cfg_goods['ucc_key'] ){
			$cfg_goods['video_use']=  'Y';
		}

		$aGetParams['options_cnt'] 				= count($tmp_option_list);
		$aGetParams['suboption_package_count'] 	= $suboption_package_count;
		$aGetParams['scm_use']					= $scm_cfg["use"];
		$aGetParams['sub_tmp_policy']			= $sub_tmp_policy;
		$aGetParams['tmp_seq']					= $tmp_seq;

		$this->template->assign(array('scObj'=>json_encode($aGetParams)));

		//추가구성옵션 관리
		if($aGetParams['mode'] != 'view'){
			$frequentlysublistAll 	= $this->goodsmodel->frequentlygoodsPaging('sub',$goods_seq,defined('SOCIALCPUSE'),$package_yn,'all');
			$frequentlysublist 		= $this->goodsmodel->frequentlygoodsPaging('sub',$goods_seq,defined('SOCIALCPUSE'),$package_yn, 1, 10);
			$frequentlysubpaginlay 	= pagingtagjs(1, 10, $frequentlysublist['total'], 'frequentlypaging([:PAGE:], \'sub\', \''.$package_yn.'\', \'suboptionSettingPopup\')');
		}

		$this->template->assign(array('frequentlysublist'=>$frequentlysublist['result']));
		$this->template->assign(array('frequentlysublistAll'=>$frequentlysublistAll['result']));
		$this->template->assign('frequentlysubpaginlay', $frequentlysubpaginlay);

		$config_goods		= config_load('goods');
		$filePath			= $this->template_path();
		$subopt_view_path	= str_replace('set_goods_suboptions','_set_suboptions_view',$filePath);
		$subopt_modi_path	= str_replace('set_goods_suboptions','_set_suboptions_modify',$filePath);

		$this->template->assign('sc', $aGetParams);
		$this->template->assign(array('sub_tmp_policy'=>$sub_tmp_policy));
		$this->template->assign('scm_cfg',$this->scm_cfg);
		$this->template->assign('scmTotalStock',$scmTotalStock);
		$this->template->assign('cfg_goods',$cfg_goods);
		$this->template->assign(array('tmp_seq'=>$tmp_seq));
		$this->template->define(array('tpl'=>$filePath));
		$this->template->define(array('view'=>$subopt_view_path));
		$this->template->define(array('modify'=>$subopt_modi_path));
		$this->template->print_("tpl");
	}

	//상품등록/수정시 입력옵션 가져오기
	public function set_goods_inputoptions() {
		$no		= trim($_GET['goods_seq']);
		$this->tempate_modules();

		$inputs = $this->goodsmodel->get_goods_input($no);
		$this->template->assign(array('inputs'=>$inputs));
		$this->template->define('*', $this->template_path());
		$html = $this->template->fetch('*');
		$return = array('html'=>$html);
		echo json_encode($return);
	}


	public function select_for_provider(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->model('providermodel');
		$salescost		= $_GET['salescost'];
		$provider_list	= $_GET['provider_list'];
		$ship_grp_seq	= $_GET['ship_grp_seq'];
		$provider_seq	= $_GET['provider_seq'];
		if($ship_grp_seq){ // 배송그룹으로 조회하는 경우 추가 :: 2016-11-08 lwh
			$provider_seq = str_replace("|","",$provider_list);
			$this->template->assign(array('ship_grp_seq'=>$ship_grp_seq));
		}
		$provider	= $this->providermodel->provider_goods_list();
		$this->template->assign(array('salescost'		=> $salescost		));
		$this->template->assign(array('provider_list'	=> $provider_list	));
		$this->template->assign(array('provider'		=> $provider		));
		$this->template->assign(array('provider_seq'	=> $provider_seq	));

		$containerHeight = !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 600;
		$this->template->assign(array('containerHeight'=>$containerHeight));

		$query = $this->db->query("select *, if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status from fm_event where end_date >= CURRENT_TIMESTAMP() order by event_seq desc");
		$eventData = $query->result_array();
		$this->template->assign(array('eventData'=>$eventData));

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function select_list_for_provider(){
		$this->tempate_modules();
		$file_path	= $this->template_path();

		if(!isset($_GET['provider_seq']))$_GET['provider_seq'] = "";
		if(!isset($_GET['goodsStatus']))$_GET['goodsStatus'] = "";
		if(!isset($_GET['goodsView']))$_GET['goodsView'] = "";
		if(!isset($_GET['sort']))$_GET['sort'] = 0;
		if(!isset($_GET['page']))$_GET['page'] = 1;
		$page = $_GET['page'];
		$adminOrder = $_GET['adminOrder'];

		$where = $subWhere = $whereStr = "";
		$bind = array();

		$arg_list = func_get_args();

		$where[] = "g.goods_kind='goods'";

		if( isset($_GET['selectCategory4']) &&  $_GET['selectCategory4'] ){
			$subWhere = "category_code = ?";
			$bind[] = $_GET['selectCategory4'];
		}else if( isset($_GET['selectCategory3']) && $_GET['selectCategory3'] ){
			$subWhere = "category_code = ?";
			$bind[] = $_GET['selectCategory3'];
		}else if( isset($_GET['selectCategory2']) && $_GET['selectCategory2'] ){
			$subWhere = "category_code = ?";
			$bind[] = $_GET['selectCategory2'];
		}else if( isset($_GET['selectCategory1']) && $_GET['selectCategory1'] ){
			$subWhere = "category_code = ?";
			$bind[] = $_GET['selectCategory1'];
		}

		if($subWhere){
			$where[] = "g.goods_seq in (select goods_seq from fm_category_link where ".$subWhere.")";
		}
		if( isset($_GET['selectGoodsName']) && $_GET['selectGoodsName'] ){
			//$where[] = "g.goods_name like ?";
			//$bind[] = '%'.$_GET['selectGoodsName'].'%';
			$where[] = " (g.goods_name like '%".$_GET['selectGoodsName']."%' or g.goods_code like '%".$_GET['selectGoodsName']."%'  or g.summary like '%".$_GET['selectGoodsName']."%' or g.keyword like '%".$_GET['selectGoodsName']."%') ";
		}
		if( isset($_GET['selectStartPrice']) && $_GET['selectStartPrice'] ){
			$where[] = "o.price >= ?";
			$bind[] = $_GET['selectStartPrice'];
		}
		if( isset($_GET['selectEndPrice']) && $_GET['selectEndPrice'] ){
			$where[] = "o.price <= ?";
			$bind[] = $_GET['selectEndPrice'];
		}

		if( $_GET['goodsStatus'] ){
			$where[] = "g.goods_status = ?";
			$bind[] = $_GET['goodsStatus'];
		}

		if( $_GET['goodsView'] ){
			$where[] = "g.goods_view = ?";
			$bind[] = $_GET['goodsView'];
		}

		if( $_GET['provider_seq'] ){
			$where[] = "g.provider_seq = ?";
			$bind[] = $_GET['provider_seq'];
		}

		//동영상
		if( $_GET['file_key_w'] ){
			$where[] = " ( file_key_w != '') ";// or file_key_w is not null
		}
		if( !empty($_GET['video_use']) && $_GET['video_use'] !="전체" ){
			$where[] = "video_use = '{$_GET['video_use']}' ";
		}
		if( $_GET['videototal'] ){
			$where[] = "videototal > 0 ";
		}


		$arrSort = array('g.goods_seq desc','g.goods_seq asc','g.purchase_ea desc','g.purchase_ea asc','g.page_view desc','g.page_view asc','g.review_count desc','g.review_count asc');
		$sortStr = " order by " .$arrSort[$_GET['sort']];

		$query = "select g.goods_seq,g.goods_name,g.goods_type,o.price, o.consumer_price, g.provider_seq
		from fm_goods g
		inner join fm_goods_option o on o.goods_seq=g.goods_seq";

		if(!empty($_GET['selectEvent']) || !empty($_GET['selectEventBenefits'])){
			$query .= "
				left join fm_event_choice e on g.goods_seq = e.goods_seq
			";

			$where[] = "e.event_seq = ?";
			$bind[] = $_GET['selectEventBenefits'];

			if(!empty($_GET['selectEventBenefits'])){
				$where[] = "e.event_benefits_seq = ?";
				$bind[] = $_GET['selectEventBenefits'];
			}
		}

		if($where){
			$whereStr = ' and '.implode(' and ',$where);
		}

		$query .= "
		where
			g.goods_type='goods' and o.default_option ='y'".$whereStr.$sortStr;
		$result = select_page(10,$page,10,$query,$bind);

		$result['page']['querystring'] = get_args_list();

		$this->template->assign('adminOrder',$adminOrder);
		$this->template->assign($result);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	//티켓상품그룹
	public function social_goods_group()
	{
		$this->load->model('providermodel');
		$aGetParams = $this->input->get();
		if($aGetParams['provider_seq']>1){
			$provider = $this->providermodel->get_provider_one($aGetParams['provider_seq']);
			$aGetParams['provider_name'] = $provider['provider_name'];
		}else{
			$aGetParams['provider_name'] = '본사';
		}
		$this->template->assign("sc",$aGetParams);
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	//티켓상품리스트폼
	public function social_goods_group_html()
	{
		$this->load->model('socialgoodsgroupmodel');
		$this->load->model('providermodel');
		$result = $this->socialgoodsgroupmodel->social_goods_group_html();
		echo json_encode($result);
		exit;
	}

	// 옵션보기 설정 :: 2015-04-13 lwh
	public function option_default_setting(){

		// 옵션 기본 노출 수량 적용 : 기존에 저장된 값이 있을 경우
		$config_goods		= config_load('goods');
		$cfg_goods_default 	= array();

		$gkind 				= $this->input->get('goods_kind');
		$skind 				= $this->input->get('sub_kind');
		$result 			= $this->goodsmodel->get_goods_default_config($gkind);

		if ($result) {
			$cfg_goods_default = $result;
		} else {
			if($skind == "options"){
				if (isset($config_goods['option_view_count'])) {
					$cfg_goods_default['option_view_count'] = $config_goods['option_view_count'];
				}
				if (isset($config_goods['suboption_view_count'])) {
					$cfg_goods_default['suboption_view_count'] = $config_goods['suboption_view_count'];
				}
			}
		}

		if($skind == 'commonContents'){
			### COMMON INFO	
			$info_loop = array();
			$query2 = $this->db->query("select * from fm_goods_info where info_name != '' and info_provider_seq = '".$this->providerInfo['provider_seq']."' order by info_seq desc");
			foreach($query2->result_array() as $v){
				$info_loop[] = $v;
			}
			if ($info_loop) $cfg_goods_default['common_info_loop'] = $info_loop;
		}
		
		if($skind == "options"){
			$this->template->assign('config_goods',$result);
		}else{
			$this->template->assign('config_goods',$cfg_goods_default);
		}
		$this->template->assign('goods_kind',$gkind);
		$this->template->assign('sub_kind',$skind);
		
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 엑셀 다운로드 양식 ( 티켓상품 )
	public function social_excel_form(){
		$this->_excel_form('COUPON');
	}

	// 엑셀 다운로드 양식 ( 실물 )
	public function excel_form(){
		$this->_excel_form('GOODS');
	}

	// 엑셀 다운로드 양식
	public function _excel_form($kind){

		$this->load->model('goodsexcel');

		$params['process']		= 'DOWNLOAD';
		$params['goods_kind']	= $kind;
		$params['provider_seq']	= $this->providerInfo['provider_seq'];
		$params['provider_id']	= $this->providerInfo['provider_id'];
		$this->goodsexcel->set_init($params);

		$mode 	= $this->input->get("mode");
		$seq 	= $this->input->get("seq");

		$column				= $this->goodsexcel->get_cell_list_for_form(array(),$mode);
		$sc['seq']			= $seq;
		$sc['gb']			= $kind;
		$saved				= $this->goodsexcel->get_excel_form_data($sc);
		//상품 엑셀은 한개만 등록 및 검색이 가능함
		$saved				= $saved['0'];
		if($saved['item_arr']){
			$saved['item_arr']	= $this->goodsexcel->get_cell_list_for_form(array_flip($saved['item_arr']),$mode);
		}
		$saved['form_type']	= $kind;
		$codeLen			= $this->goodsexcel->get_code_length();
		$sortKey			= 0;

		if($mode == 'newform'){

			$columnAll = array();
			foreach($column as $i => $_column){
				foreach($_column['list'] as $key =>$list){
					$title = explode("<br/>",$list['title']);
					$columnAll[$list['code']] = $title[0];
					
					$sortKey++;
					$sortColumn[$list['code']] = $sortKey;
				}
			}
			$columnSelect 	= array();
			
			if	(is_array($saved['item_arr']) && count($saved['item_arr']) > 0){
				foreach($saved['item_arr'] as $_column){
					foreach($_column['list'] as $key=>$list){
						$title = explode("<br/>",$list['title']);
						$columnSelect[$list['code']] = $title[0];
					}
				}
			}

			$columnAll = array_diff($columnAll, $columnSelect);

			$return = array('chkScm'=> $this->scmmodel->chkScmConfig(true),
							'seq'			=> $saved['form_seq'],
							'provider_seq'	=> $saved['provider_seq'],
							'formData'		=> array('form_id'=>$saved['form_id'],'form_name'=>$saved['form_name'],'form_type'=>$saved['form_type']),
							'columnAll'		=> $columnAll,
							'sortColumn'		=> $sortColumn,
							'columnSelect'	=> $columnSelect,
			);
			echo json_encode($return);

		}else{

			// 체크 및 재배열
			if	(is_array($saved['item_arr']) && count($saved['item_arr']) > 0){
				if	(is_array($column)) foreach($column as $a => $list){
					if	(is_array($list['list'])) foreach($list['list'] as $b => $info){

						// 체크 상태 표시
						if	(in_array(substr($info['code'], 0, $codeLen), $saved['item_arr']))	$info['chk']	= 'y';
						$info['idx']	= $b;

						$rKey			= array_search($info['code'], $saved['sort_arr'][$a]);
						if	($rKey === 0 || $rKey > 0){
							$sorted_column[$a]['list'][$rKey]	= $info;
						}else{
							$remain_column[$a][]				= $info;
						}
					}
					ksort($sorted_column[$a]['list']);
				}
				// 남은 column을 밑에 붙여준다.
				if	($remain_column) foreach($remain_column as $a => $arr){
					if	($arr) foreach($arr as $b => $info){
						$sorted_column[$a]['list'][]	= $info;
					}
				}
			}else{
				$sorted_column	= $column;
			}

			$this->template->assign(array(
				'chkScm'		=> $this->scmmodel->chkScmConfig(true),
				'seq'			=> $saved['form_seq'],
				'provider_seq'	=> $saved['provider_seq'],
				'formData'		=> $saved,
				'column'		=> $column,
				'sorted_column'	=> $sorted_column,
			));
			$this->admin_menu();
			$this->tempate_modules();
			$file_path	= $this->template_path();
			$this->template->define(array('tpl'=>$file_path));
			$this->template->print_("tpl");
		}
	}

	// 엑셀 업로드 ( 티켓상품 )
	public function social_excel_upload(){
		$this->_excel_upload('COUPON');
	}

	// 엑셀 업로드 ( 실물 )
	public function excel_upload(){
		$this->_excel_upload('GOODS');
	}

	// 엑셀 업로드 페이지
	public function _excel_upload($kind){

		$this->load->model('goodsexcel');

		$params['process']		= 'UPDATE';
		$params['goods_kind']	= $kind;
		$params['provider_seq']	= $this->providerInfo['provider_seq'];
		$params['provider_id']	= $this->providerInfo['provider_id'];
		$this->goodsexcel->set_init($params);

		// 업로드 로그 추출 ( 최근 5건 )
		$sc['elimit']	= 10;
		$logs			= $this->goodsexcel->get_excel_upload_log($sc);

		// 필수, 필수옵션, 추가옵션, 입력옵션 cell 배열 생성
		$cellList	= $this->goodsexcel->get_cell_list();
		if	($cellList) foreach($cellList as $code => $title){
			if	(substr($code, 2, 1) == 'R')
				$requires[$code]	= $title;
			if	($this->goodsexcel->get_except_cell_list($code) == 'option')
				$options[$code]		= $title;
			if	($this->goodsexcel->get_except_cell_list($code) == 'suboption')
				$suboptions[$code]		= $title;
			if	($this->goodsexcel->get_except_cell_list($code) == 'input')
				$inputs[$code]		= $title;
		}

		$this->template->assign(array(
			'kind'			=> $kind,
			'logs'			=> $logs,
			'requires'		=> $requires,
			'options'		=> $options,
			'suboptions'	=> $suboptions,
			'inputs'		=> $inputs,
		));

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function package_catalog()
	{
		define('PACKAGEUSE',true);
		$this->template->assign('packageuse',1);

		$this->cfg_goods_default	= $this->goodsmodel->get_goods_default_config('package_goods');

		$this->catalog();
	}

	public function package_social_catalog()
	{
		if(in_array($this->config_system['service']['code'], array('P_FREE', 'P_PREM')) && uri_string()!='admin/goods/catalog'){
			redirect('admin/goods/catalog'.($_SERVER['QUERY_STRING']?'?'.$_SERVER['QUERY_STRING']:''));
		}
		define('SOCIALCPUSE',true);
		$this->template->assign('socialcpuse',1);

		define('PACKAGEUSE',true);
		$this->template->assign('packageuse',1);

		$this->cfg_goods_default	= $this->goodsmodel->get_goods_default_config('package_coupon');
		$this->catalog();
	}

	public function select_goods_options()
	{
		$this->load->model("eventmodel");
		$this->load->model("giftmodel");
		$this->load->model("providermodel");

		$aGetParams = $this->input->get();

		$_default 	= array('orderby'=>'goods_seq','sort'=>'desc','page'=>1,'perpage'=>5);
		$scRes 		= $this->searchsetting->pagesearchforminfo("admin/goods/catalog",$_default);
		$this->template->assign('sc_form',$scRes['form']);
		unset($scRes['form']);
        // 패키지 상품 연결 일 경우 일반 상품만 연결 가능.
        if($aGetParams['package']){
            $aGetParams['selectGoodsKind'] = "goods";
		}
		$provider	= $this->providermodel->provider_goods_list_sort(true);
		if($aGetParams['provider_seq']){
			foreach($provider as $data_provider){
				if($aGetParams['provider_seq'] == $data_provider['provider_seq']) {
					$aGetParams['provider_name'] = $data_provider['provider_name'];
					$aGetParams['provider_id'] = $data_provider['provider_id'];
				}
			}
		}

		if( $aGetParams['tmp_seq'] )	$tmp_seq 	= $aGetParams['tmp_seq'];
		if( $aGetParams['goods_seq'] ) 	$goods_seq 	= $aGetParams['goods_seq'];
		if( $aGetParams['opt_type'] ) 	$opt_type 	= $aGetParams['opt_type'];


		// 임시 옵션 정보
		$tmp_option_list	= array();
		if($tmp_policy == 'shop')	$addMod	= $mode;

		if( $opt_type == 'opt' ){
			$tmp_option_list[0]	= $this->goodsmodel->get_option_tmp_list($tmp_seq);
		}else{
			$tmp_option_list	= $this->goodsmodel->get_suboption_tmp_list($tmp_seq, $addMod);
		}
		$data = $tmp_option_list[0][0];

		// 초기값 세팅
		if( !$data ){
			if( !$data['package_count'] ) 	$data['package_count'] 	= $aGetParams['package_count'];
			if( !$data['title1'] ) 			$data['title1'] 		= '옵션1';
			if( !$data['option1'] ) 		$data['option1'] 		= '기본';
			if( !$data['tmp_no'] ) 			$data['tmp_no'] 		= $tmp_seq;
			if( !$data['option_count'] ) 	$data['option_count'] 	= 1;
			$tmp_option_list[0][0] = $data;
		}else{

			$tmp = explode(',',$data['option_title']);
			foreach($tmp as $key => $data_title){
				$data['title'.($key+1)] = $data_title;
			}
			$data['option_count'] = count($tmp);
		}

		// 추가옵션 옵션
		foreach($tmp_option_list as $k_result =>  $result_option){
			foreach($result_option as $k_data => $data_option){
				if( $data_option['suboption_title'] ) $tmp_option_list[$k_result][$k_data]['title1'] =  $data_option['suboption_title'];
				if( $data_option['suboption'] ) $tmp_option_list[$k_result][$k_data]['option1'] =  $data_option['suboption'];
				for($tn=1;$tn<6;$tn++){
					$tmp_option_list[$k_result][$k_data]['title'.$tn] = $data['title'.$tn];
				}
			}
		}
		if(!$data['package_count']) $data['package_count'] = 2;
		$title_loop = $title_new_loop = array();
		if( $data['package_count'] ){
			for($i=1;$i<=$data['package_count'];$i++){
				$title_loop[$i] = true;
				$title_new_loop[]['num'] = $i;
			}
		}

		for($i=0;$i<$data['option_count'];$i++){
			$option_title_loop[] = true;
		}

		$cell_cnt = $data['option_count'] + $data['package_count'];

		if($opt_type == 'opt') $loop = $tmp_option_list[0];
		else $loop = $tmp_option_list;

		$this->tempate_modules();
		$file_path	= $this->template_path();

		// 할인이벤트 진행중 리스트
		$event_list = $this->eventmodel->get_event_ing_list();
		$this->template->assign('event_list', $event_list);
		// 사은품이벤트 진행중 리스트
		$gift_list = $this->giftmodel->get_gift_ing_list();
		$this->template->assign('gift_list', $gift_list);

		if( $opt_type == 'opt' ){
			$mode	= 'option';
			$msg	= "연결이 올바르지 않은 필수옵션이 존재합니다.\\n페이지를 새로고침 합니다.";
		}else{
			$mode	= 'suboption';
			$msg	= "연결이 올바르지 않은 추가구성상품이 존재합니다.\\n페이지를 새로고침 합니다.";
		}
		if($data['goods_seq']) $goods_seq = $data['goods_seq'];
		if($aGetParams['goods_seq']) $goods_seq = $aGetParams['goods_seq'];
		if($goods_seq){
			$result_error = array();
			$error_option_seq = array();
			$query_error = $this->errorpackage->get_error(array('goods_seq'=>$goods_seq,'type'=>$mode));
			foreach($query_error->result_array() as $data_error){
				$error_option_seq[] = $data_error['parent_seq'];
				$result_error[$data_error['parent_seq']][] = $data_error;
			}

			$result_link_check = $this->goodsmodel->package_check($goods_seq,$mode);
			foreach($result_link_check as $data_check){
				if(!in_array($data_check['option_seq'],$error_option_seq)){
					alert($msg);
					echo("<script>
					if($(\"input[name='goodsName']\").length > 0){
						location.reload();
					}else{
						self.close();
						opener.location.reload();
					}
					</script>");
					exit;
				}
			}
		}
		$arr_msg['10'] = "연결할 실제상품을 찾을 수 없음 <span class=\"tooltip_btn\" onClick=\"showTooltip(this, '../tooltip/goods', '#regist_package_goods_error', 'sizeS')\"></span>";
		$arr_msg['20'] = "연결되었던 옵션명을 찾을 수 없음 <span class=\"tooltip_btn\" onClick=\"showTooltip(this, '../tooltip/goods', '#regist_package_options_error', 'sizeS')\"></span>";

		if( $opt_type == 'opt' ){

			// 패키지 상품 정보 배열화
			$_new_array_field 	= array(
									"package_goods_seq","package_goods_name",
									"package_option_seq","package_option",
									"package_unit_ea","title",
									"package_stock","package_ablestock","package_badstock","package_safe_stock",
								);
			$package_loop = array();
			foreach($loop as $k=>$data_loop){
				if($data_loop['org_option_seq']){ // 필수옵션 사용
					$error_msg = array();
					foreach($result_error[$data_loop['org_option_seq']] as $data_err){
						$code = $data_err['error_code'];
						if($code){
							$num 		= substr($code,1,1);
							$error_code = substr($code,2,2);
							$package_loop[$k][$num]['error_msg'] = $arr_msg[$error_code];
						}
					}
					//$loop[$k]['error_msg'] = $error_msg;
				}

				// 패키지 상품 정보 배열화
				foreach($_new_array_field as $_new_key){
					for($i=1; $i<6; $i++){
						if($title_loop[$i]){
							$package_loop[$k][$i][$_new_key] = $data_loop[$_new_key.$i];
						}
					}
				}

				//$loop[$k]['package_loop'] = $package_loop;

			}

		}else{
			foreach($loop as $k=>$tmp){
				foreach($tmp as $tk=>$data_loop){
					if($data_loop['org_suboption_seq']){ // 필수옵션 사용
						foreach($result_error[$data_loop['org_suboption_seq']] as $data_err){
							$code = $data_err['error_code'];
							if($code){
								$num 		= substr($code,1,1);
								$error_code = substr($code,2,2);
								$data_loop['error_msg'.$num] = $arr_msg[$error_code];
							}
							$loop[$k][$tk] = $data_loop;
						}
					}
				}
			}
		}
		
		$assigns = array(
			"loop" 					=> $loop,
			"title_loop" 			=> $title_loop,
			"package_loop" 			=> $package_loop,
			"option_title_loop" 	=> $option_title_loop,
			"cell_cnt"				=> $cell_cnt,
			"provider"				=> $provider,
			"package"				=> $aGetParams['package'],
			"provider_fix" 			=> true,
			"sc"					=> $aGetParams,
			"scObj"					=> json_encode($aGetParams),
		);
		
		//$this->template->define(array('searchForm' => $this->skin.'/goods/_gl_select_goods_search_form.html'));
		$defines = array(
			'select_option_package'		=> $this->skin.'/goods/_select_option_package.html',
			'select_suboption_package'	=> $this->skin.'/goods/_select_suboption_package.html',
			'goods_search_form' 		=> $this->skin.'/goods/_gl_select_goods_search_form.html',
			'tpl'						=> $file_path
		);

		$this->template->assign($assigns);
		$this->template->define($defines);
		$this->template->print_("tpl");
	}

	public function select_goods_options_list()
	{

		$this->tempate_modules();
		$file_path	= $this->template_path();

		$aGetParams = $this->input->get();
		$page			= $aGetParams['page'];
		$adminOrder		= $aGetParams['adminOrder'];
		$adminshipping	= $aGetParams['adminshipping'];

		$this->load->library('searchsetting');
		### SEARCH
		$_default 						= array('sort'=>'desc_goods_seq','page'=>0,'perpage'=>5);
		$scRes 							= $this->searchsetting->pagesearchforminfo("admin/goods/catalog",$_default);
		unset($scRes['form']);
		$scRes['adminOrder'] 		= "Y";
		$scRes['selectGoodsKind'] 	= 'goods';
		$scRes['searchType'] 		= 'packageGoods';
		$scRes['provider_seq'] 		= $this->providerInfo['provider_seq'];
		$sc 						= $this->goodslibrary->_select_goods_data_sc($scRes);
		$result = $this->goodsmodel->admin_goods_list_new($sc); 

		foreach($result['record'] as $k=>$datarow){
			$datarow['goods_view_text']	= $datarow['goods_view']=='look' ? "<span style='color:blue'>노출</span>" : "<span style='color:red'>미노출</span>";
			$result['record'][$k] = $datarow;
		}

		//정렬
		$sorderby = $aGetParams['orderby'];
		$aGetParams['orderby'] = $aGetParams['sort']."_".$aGetParams['orderby'];

		$assigns = array(
			'perpage'		=> $aGetParams['perpage'],
			'orderby'		=> $aGetParams['orderby'],
			'sort'			=> $sort,
			'sorderby'		=> $sorderby,
			'adminOrder'	=> $adminOrder
		);

		$this->template->assign($assigns);
		$this->template->assign($result);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	public function save_tmp_option_package(){
		$this->goodsmodel->save_tmp_option_package($_POST);
	}

	public function select_goods_options_view()
	{
		$goods_seq 		= $this->input->get('goods_seq');
		$data_goods	= $this->goodsmodel->get_goods($goods_seq);
		$data_options	= $this->goodsmodel->get_goods_option($goods_seq);
		foreach($data_options as $key_option => $data_option){
			$data_option['goods_name'] 		= $data_goods['goods_name'];
			$data_option['combine_option']	=  ($data_option['option1']) ? $data_option['option1'] : "기본";
			$data_option['combine_option'] .= ($data_option['option2']) ? " / " . $data_option['option2'] : "";
			$data_option['combine_option'] .= ($data_option['option3']) ? " / " . $data_option['option3'] : "";
			$data_option['combine_option'] .= ($data_option['option4']) ? " / " . $data_option['option4'] : "";
			$data_option['combine_option'] .= ($data_option['option5']) ? " / " . $data_option['option5'] : "";
			$data_option['option_codes']	= $data_goods['goods_code'] . $data_option['optioncode1'] . $data_option['optioncode2'] . $data_option['optioncode3'] . $data_option['optioncode4'] . $data_option['optioncode5'];

			$data_option['safe_stock']	= (int) $data_option['safe_stock'];
			$data_option['rstock']		= (int) $data_option['rstock'];
			$data_option['badstock']	= (int) $data_option['badstock'];
			$data_option['stock']		= (int) $data_option['stock'];
			$data_options[$key_option]		= $data_option;
		}

		$this->template->assign(array('record'=>$data_options));
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function select_auto_condition_title($displayKind='bigdata', $kind=''){
		if($this->input->get('displayKind')) $displayKind = $this->input->get('displayKind');
		$bigdata_title_arr 	= $this->goodslibrary->get_bigdata_title($displayKind);

		if($this->input->get('mode') == "json"){
			echo json_encode($bigdata_title_arr);
		}else{
			if($kind) return $bigdata_title_arr[$kind];
			else return $bigdata_title_arr;
		}
	}

	public function select_auto(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$aGetParams = $this->input->get();
		if	($aGetParams['design_bigdata']) $aGetParams['displayKind'] = 'bigdata';

		$bigdata_title_arr = $this->select_auto_condition_title($aGetParams['displayKind']);
		$this->template->assign('bigdata_title_arr',$bigdata_title_arr);
		$this->template->assign($aGetParams);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function select_auto_condition(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$aGetParams = $this->input->get();

		// 반응형일때 상품 번호가 있는경우 해당 상품의 추천상품 조건 가져옴 :: 2019-03-22 pjw
		if($this->config_system['operation_type'] == 'light' && !empty($aGetParams['goods_seq'])){
			if($aGetParams['mode'] == "default"){
			}else{
				
				$this->load->model('goodsmodel');
				$goods_info		=	$this->goodsmodel->get_goods($aGetParams['goods_seq']);
				$criteria_type	=	$aGetParams['displayKind'];
				$criteria_text	=	$goods_info[$criteria_type.'_criteria_light'];
			}
			
			// 추천상품 조건이 있는경우 파싱하여 get변수에 넣으면 스크립트에서 처리함
			if(!empty($criteria_text)){
				$conditions			= array();
				$con_origin			= explode('∀', $criteria_text);
				$aGetParams['condition']	= $con_origin[1];
			}
		}

		$bigdata_title_arr 	= $this->select_auto_condition_title($aGetParams['displayKind']);
		$this->template->assign('bigdata_title_arr',$bigdata_title_arr);
		$this->template->assign($aGetParams);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// option, suboption 승인내역 view :: 2016-03-24 lwh
	public function goods_batch_permit(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$chk_goods_seq = $_POST['goods_seq'];

		parse_str($_POST['data']);
		// 예외처리
		if($batchmodify_selector == 'ifprice'){
			if($_POST['modify_list'] == 'all'){
				$_GET = $_POST;
				$sc = $_GET;
				$this->goodsmodel->batch_mode = 1;
				$query = $this->goodsmodel->admin_goods_list($sc);

				$query = $this->db->query($query);
				foreach($query->result_array() as $data){
					$chk_goods_seq[] = $data['goods_seq'];
				}
			}

			// 전체 체크된 업데이트 대상 상품 추려내기
			foreach($chk_goods_seq as $goods_seq){
				// 상품정보 추출
				$goods_info = $this->goodsmodel->get_goods($goods_seq);
				// 옵션정보 추출
				$tmp_option_list	= $this->goodsmodel->get_goods_option($goods_seq);

				$optArr[$goods_seq]['goods_name'] = $goods_info['goods_name'];
				foreach($tmp_option_list as $optinfo){
					$optseq = $optinfo['option_seq'];

					//## 정산타입
					$optArr[$goods_seq]['commission_type'][$optseq] = $optinfo['commission_type'];

					//## 수수료율
					$optArr[$goods_seq]['commission_rate'][$optseq] = $optinfo['commission_rate'];

					// 정가 원본 저장
					$ori_consumer_price = $optinfo['consumer_price'];
					// 정가 조정
					if($_POST['consumer_price_yn']){
						// 퍼센트 및 금액 결정
						if($_POST['batch_consumer_price_unit'] == 'percent')
							$modi_consumer_price = ($ori_consumer_price * $_POST['batch_consumer_price'] / 100);
						else
							$modi_consumer_price = $_POST['batch_consumer_price'];

						// 증감 결정
						if($_POST['batch_consumer_price_updown']=='up')
							$ori_consumer_price = $ori_consumer_price + $modi_consumer_price;
						else
							$ori_consumer_price = $ori_consumer_price - $modi_consumer_price;
					}
					//## 정가 계산결과 저장
					$optArr[$goods_seq]['consumer_price'][$optseq] = $ori_consumer_price;

					// 판매가 원본 저장
					$ori_price = $optinfo['price'];
					// 판매가 조정
					if($_POST['batch_price_yn']){
						// 퍼센트 및 금액 결정
						if($_POST['batch_price_unit'] == 'percent')
							$modi_price = ($ori_price * $_POST['batch_price'] / 100);
						else
							$modi_price = $_POST['batch_price'];

						// 증감 결정
						if($_POST['batch_price_updown']=='up')
							$ori_price = $ori_price + $modi_price;
						else
							$ori_price = $ori_price - $modi_price;
					}
					//## 판매가 계산결과 저장
					$optArr[$goods_seq]['price'][$optseq] = $ori_price;

					// 매입가 원본 저장
					$ori_supply_price = $optinfo['supply_price'];
					// 매입가 조정
					if($_POST['batch_supply_price_yn']){
						// 퍼센트 및 금액 결정
						if($_POST['batch_supply_price_unit'] == 'percent')
							$modi_supply_price = ($ori_supply_price * $_POST['batch_supply_price'] / 100);
						else
							$modi_supply_price = $_POST['batch_supply_price'];

						// 증감 결정
						if($_POST['batch_supply_price_updown']=='up')
							$ori_supply_price = $ori_supply_price + $modi_supply_price;
						else
							$ori_supply_price = $ori_supply_price - $modi_supply_price;
					}
					//## 매입가 계산결과 저장
					$optArr[$goods_seq]['supply_price'][$optseq] = $ori_supply_price;


					// 역마진 여부 판단
					if($goods_info['provider_seq'] > 1){ // 입점사 상품
						// 정산가 계산
						switch($optArr[$goods_seq]['commission_type'][$optseq]){
							case	'SUCO' :
								$optArr[$goods_seq]['commission_price'][$optseq] = $optArr[$goods_seq]['consumer_price'][$optseq] / 100 * $optArr[$goods_seq]['commission_rate'][$optseq];
								break;

							case	'SUPR' :
								$optArr[$goods_seq]['commission_price'][$optseq]	= $optArr[$goods_seq]['commission_rate'][$optseq];
								break;

							default	:
								$optArr[$goods_seq]['commission_price'][$optseq]	= $optArr[$goods_seq]['price'][$optseq] / 100 *(100 - $optArr[$goods_seq]['commission_rate'][$optseq]);
						}

						// 정산금액 소수점 버림 처리 - 이정록 - 2016-07-13
						$optArr[$goods_seq]['commission_price'][$optseq] = floor($optArr[$goods_seq]['commission_price'][$optseq]);

						if($optArr[$goods_seq]['commission_price'][$optseq] > $optArr[$goods_seq]['price'][$optseq]){
							$optArr[$goods_seq]['chk_digit'][$optseq] = 'Y';
							$optArr[$goods_seq]['chk_goods_digit'] = 'Y';
							$digit_goods[$goods_seq] = $optArr[$goods_seq]['goods_name'];
						}
					}else{ // 본사 상품
						$optArr[$goods_seq]['sales_rate'][$optseq] = $optArr[$goods_seq]['price'][$optseq] - $optArr[$goods_seq]['supply_price'][$optseq];

						if($optArr[$goods_seq]['sales_rate'][$optseq] < 0){
							$optArr[$goods_seq]['chk_digit'][$optseq] = 'Y';
							$optArr[$goods_seq]['chk_goods_digit'] = 'Y';
							$digit_sale[$goods_seq] = $optArr[$goods_seq]['goods_name'];
						}
					}
				}//옵션 foreach end
			}//상품 foreach end
		}else if($batchmodify_selector == 'price'){
			// 전체 체크된 업데이트 대상 상품 추려내기
			foreach($chk_goods_seq as $goods_seq){
				foreach($_POST['option_seq'] as $optseq => $seq){
					if($goods_seq == $seq){ // 체크된 데이터 만..

						// 상품정보 추출
						$goods_info = $this->goodsmodel->get_goods($seq);
						$optArr[$seq]['goods_name'] = $goods_info['goods_name'];

						// 상품 seq 밑에 옵션종속
						$optArr[$goods_seq]['opt'][] = $optseq;

						// 정산타입
						$optArr[$seq]['commission_type'][$optseq] = ($_POST['commission_type'][$optseq]) ? $_POST['commission_type'][$optseq] : $_POST['detail_commission_type'][$optseq];

						// 수수료율
						$optArr[$seq]['commission_rate'][$optseq] = ($_POST['commission_rate'][$optseq]) ? $_POST['commission_rate'][$optseq] : $_POST['detail_commission_rate'][$optseq];

						// 정가
						$optArr[$seq]['consumer_price'][$optseq] = ($_POST['consumer_price'][$optseq]) ? $_POST['consumer_price'][$optseq] : $_POST['detail_consumer_price'][$optseq];

						// 판매가
						$optArr[$seq]['price'][$optseq] = ($_POST['price'][$optseq]) ? $_POST['price'][$optseq] : $_POST['detail_price'][$optseq];

						// 매입가
						$optArr[$seq]['supply_price'][$optseq] = ($_POST['supply_price'][$optseq]) ? $_POST['supply_price'][$optseq] : $_POST['detail_supply_price'][$optseq];

						// 역마진 여부 판단
						if($goods_info['provider_seq'] > 1){ // 입점사 상품
							// 정산가 계산
							switch($optArr[$seq]['commission_type'][$optseq]){
								case	'SUCO' :
									$optArr[$seq]['commission_price'][$optseq] = $optArr[$seq]['consumer_price'][$optseq] / 100 * $optArr[$seq]['commission_rate'][$optseq];
									break;

								case	'SUPR' :
									$optArr[$seq]['commission_price'][$optseq]	= $optArr[$seq]['commission_rate'][$optseq];
									break;

								default	:
									$optArr[$seq]['commission_price'][$optseq]	= $optArr[$seq]['price'][$optseq] / 100 *(100 - $optArr[$seq]['commission_rate'][$optseq]);
							}

							// 정산금액 소수점 버림 처리 - 이정록 - 2016-07-13
							$optArr[$seq]['commission_price'][$optseq] = floor($optArr[$seq]['commission_price'][$optseq]);

							if($optArr[$seq]['commission_price'][$optseq] > $optArr[$seq]['price'][$optseq]){
								$optArr[$seq]['chk_digit'][$optseq] = 'Y';
								$optArr[$seq]['chk_goods_digit'] = 'Y';
								$digit_goods[$seq] = $optArr[$seq]['goods_name'];
							}
						}else{ // 본사 상품
							$optArr[$seq]['sales_rate'][$optseq] = $optArr[$seq]['price'][$optseq] - $optArr[$seq]['supply_price'][$optseq];

							if($optArr[$seq]['sales_rate'][$optseq] < 0){
								$optArr[$seq]['chk_digit'][$optseq] = 'Y';
								$optArr[$seq]['chk_goods_digit'] = 'Y';
								$digit_sale[$seq] = $optArr[$seq]['goods_name'];
							}
						}
					}// 선택데이터 if end
				}//옵션 foreach end
			}//상품 foreach end
		}else if($batchmodify_selector == 'shipping'){
			if	($_POST['sel_shipping_group_seq'] == 'trust_ship'){
				$warning	= '<span class="red">대상 상품을 본사 위탁배송으로 변경합니다.</span>';
			}
		}

		$this->template->assign(array('warning'=>$warning)); // 경고메세지
		$this->template->assign(array('digit_sale'=>$digit_sale)); // 매입
		$this->template->assign(array('digit_goods'=>$digit_goods)); // 입점
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	## 패키지 연결상태 갱신
	public function package_check(){
		$goods_seq	= $_GET['goods_seq'];
		$mode		= $_GET['mode'];
		$result = $this->goodsmodel->package_check($goods_seq,$mode);
		echo json_encode($result);
	}

	//## 배송그룹 선택 :: 2016-06-30 lwh
	public function shipping_select_popup(){
		$this->admin_menu();
		$this->tempate_modules();

		// 본사의 배송그룹 별도 추출
		if($_GET['provider_seq'] > 1){
			$admin_list = $this->shippingmodel->get_shipping_group_simple('1');
		}
		$list = $this->shippingmodel->get_shipping_group_simple($_GET['provider_seq']);

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		if( defined('__SELLERADMIN__') === true ){
			$this->template->assign('admin_login','N');
		}else{
			$this->template->assign('admin_login','Y');
		}
		$this->template->assign('provider_seq',$_GET['provider_seq']);
		$this->template->assign('shipping_group_seq',$_GET['shipping_group_seq']);
		$this->template->assign('list',$list);
		$this->template->assign('admin_list',$admin_list);
		$this->template->print_("tpl");
	}
	
	//## 선택한 배송그룹명
	public function get_shipping_group_info(){
		$this->admin_menu();
		$ship_info 	= $this->shippingmodel->get_shipping_group($this->input->get('shipping_group_seq'));
		echo json_encode($ship_info);
	}

	//## 배송그룹 정보 추출 :: 2016-10-21 lwh
	public function shipping_group_view(){
		$this->admin_menu();
		$this->tempate_modules();
		$filePath = $this->template_path();

		$grp_sc = array(
			'search_type'=>'grp.shipping_group_seq',
			'keyword'=>$_GET['shipping_group_seq']
		);
		$ship_info = $this->shippingmodel->shipping_group_list($grp_sc);
		$shipping_info = $ship_info['record'];

		$this->template->assign('provider_seq',$_GET['provider_seq']);
		$this->template->assign('shipping_info',$shipping_info);
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	//## 입점사 티켓 배송그룹 추출
	public function get_coupon_shippinggrp(){
		if($this->input->get('provider_seq')){
			$shipping_tmp 	= $this->shippingmodel->get_shipping_group_simple($this->input->get('provider_seq'),'Y');
			$shipping_group = $shipping_tmp[0];
			echo $shipping_group['shipping_group_seq'];
		}
	}

	//## 입점사 상품 기본 배송그룹 추출
	public function get_base_shippinggrp(){
		if($this->input->get('provider_seq')){
			$shipping_tmp 	= $this->shippingmodel->get_shipping_group_simple($this->input->get('provider_seq'));
			$shipping_group = $shipping_tmp[0];
			echo $shipping_group['shipping_group_seq'];
		}
	}

	# HS CODE 상세 정보 불러오기
	public function hscode_setting_regist(){

		$this->admin_menu();
		$this->tempate_modules();

		$this->load->model("multisupportmodel");

		$hscode_nation	= array('USA','CHN','JPN','TWN','VNM','IDN','PHL','CHL');
		$nation_info	= $this->multisupportmodel->getNationList();
		$nation_list	= array();

		$nation_list['loop'][] = array("nationKey"=>"KOR","nationCode"=>"KOREA","nationName"=>"대한민국");
		foreach($nation_info as $nation_code=>$nation){
			if(in_array($nation['nationKey'],$hscode_nation) && $nation_code){
				$nation_list['loop'][] = $nation;
			}
		}
		foreach($nation_info as $nation_code=>$nation){
			if(!in_array($nation['nationKey'],$hscode_nation) && $nation_code){
				$nation_list['loop'][] = $nation;
			}
		}

		if($_GET['hscode_common']){
			$hscode_info	= $this->multisupportmodel->get_hscode_info($_GET['hscode_common']);
		}

		if($_GET['mode'] == "json"){

			# 상품상세 : 수출입코드 설정 시 사용
			echo json_encode($hscode_info);

		}else{

			# HSCODE 등록/수정 시 사용
			$this->template->assign($hscode_info);
			$this->template->assign(array(
							'arr_search_keyword'	=> $arr_search_keyword,
							'nation_list'			=> $nation_list
							));

			$filePath	= $this->template_path();
			$this->template->define(array('tpl'=>$filePath));
			$this->template->print_("tpl");

		}

	}

	# HS CODE 설정
	public function hscode_setting(){

		$this->admin_menu();
		$this->tempate_modules();

		### AUTH
		$auth = $this->authmodel->manager_limit_act('goods_act');
		if(isset($auth)) $this->template->assign('auth',$auth);

		$sc = $_GET;

		$this->load->model("multisupportmodel");

		$arr_search_keyword = array(
								"hscode_name"		=> "품명",
								"hscode_common"		=> "HS분류",
								);

		$hscode_list = $this->multisupportmodel->get_hscode_list($sc);

		$this->template->assign(array(
						'arr_search_keyword'	=> $arr_search_keyword,
						"loop"					=> $hscode_list,
						"sc"					=> $sc,
						));

		$filePath	= $this->template_path();
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");

	}

	// 상품 일괄등록 ( 빠른 상품등록 )
	public function batch_regist(){
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('scmmodel');
		$this->load->model('goodsmodel');
		$this->load->model('providermodel');
		$this->load->model('shippingmodel');

		if	(!$this->cfg_order)	$this->cfg_order	= config_load('order');
		if	( defined('__SELLERADMIN__') === true )	$sellermode			= true;

		$loadStatus		= 'SUCCESS';
		$tmpData		= $this->goodsmodel->get_tmp_goods_data($_GET['tmpno']);
		if	($tmpData){
			$provider_list	= $this->providermodel->provider_goods_list_sort();
			$shippingData	= $this->shippingmodel->get_shipping_group($tmpData['shipping_group_seq']);
			$provider_name	= '본사';
			if	($provider_list) foreach($provider_list as $p => $pData){
				if	($tmpData['provider_seq'] == $pData['provider_seq']){
					$current_provider	= $pData;
				}
			}

			// 물류관리일 경우 창고 및 로케이션 정보 추출
			if	($this->scmmodel->chkScmConfig(true)){
				if	(!$this->scm_cfg){
					if	($this->scmmodel->scm_cfg)	$this->scm_cfg		= $this->scmmodel->scm_cfg;
					else							$this->scm_cfg		= config_load('scm');
				}
				$warehouse				= $this->scmmodel->get_warehouse(array('orderby' => 'wh_name asc'));
				$wh_seq					= $warehouse[0]['wh_seq'];
				$whData['warehouse']	= $warehouse;
				if	($wh_seq > 0){
					$whData['location']	= $this->scmmodel->get_location(array('wh_seq' => $wh_seq));
				}

				$scmCateData	= $this->scmmodel->get_list_category($tmpData['scm_category']);
			}
		}else{
			$loadStatus		= 'FAIL';
		}
		$filePath		= $this->template_path();
		$this->template->assign(array(
			'sellermode'		=> $sellermode,
			'loadStatus'		=> $loadStatus,
			'cfg_order'			=> $this->cfg_order,
			'provider_list'		=> $provider_list,
			'current_provider'	=> $current_provider,
			'tmpData'			=> $tmpData,
			'shippingData'		=> $shippingData,
			'whData'			=> $whData,
			'scm_cfg'			=> $this->scm_cfg,
			'scmCategory'		=> $scmCateData['category'],
			'categoryinfo'		=> $scmCateData['categoryinfo'],
		));
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	// 배송그룹 추출
	public function get_probider_shipping_data(){
		$this->load->model('shippingmodel');
		$provider_seq		= trim($_POST['provider_seq']);
		if	($provider_seq > 0){
			$shipping			= $this->shippingmodel->get_shipping_group_simple($provider_seq);

			$result['status']				= true;
			$result['data']					= $shipping;
			$result['calcul_type_name']		= $this->shippingmodel->calcul_type_txt;
		}else{
			$result['status']	= false;
			$result['msg']		= '입점사를 선택해 주세요.';
		}

		echo json_encode($result);
	}

	// 옵션 생성 팝업 생성
	public function create_option_popup(){
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('goodsmodel');

		$goods_seq			= trim($_POST['goods_seq']);
		$submitFunc			= trim($_POST['submitFunc']);
		$tmp_seq			= trim($_POST['tmp_seq']);
		$popup_id			= trim($_POST['popup_id']);
		$filePath			= $this->template_path();
		$goodsoptionloop	= $this->goodsmodel->get_add_option_code();
		$this->template->assign(array(
			'submitFunc'		=> $submitFunc,
			'goods_seq'			=> $goods_seq,
			'tmp_seq'			=> $tmp_seq,
			'popup_id'			=> $popup_id,
			'goodsoptionloop'	=> $goodsoptionloop,
		));
		$this->template->define(array('tpl'=>$filePath));
		$this->template->print_("tpl");
	}

	// ajax로 EP데이터 추출 :: 2017-02-22 lwh
	public function get_shipping_grp_ajax(){
		$group_seq		= $_GET['shipping_group_seq'];
		$feed_type		= ($_GET['feed_type']) ? $_GET['feed_type'] : 'G';
		$return_type	= ($_GET['return_type']) ? $_GET['return_type'] : 'json';

		// 배송그룹 설정 가져오기
		if			($feed_type == 'G'){
			if($group_seq){
				$result = $this->shippingmodel->get_shipping_ep_data($group_seq);
				if($result['std'] > 0){
					$result['std'] = get_currency_price($result['std'],2);
				}
			}
		}else if	($feed_type == 'S'){ // 통합설정 가져오기
			$result = $this->shippingmodel->get_shop_ep_data();
			if($result['std'] > 0){
				$result['std'] = get_currency_price($result['std'],2);
			}
		}

		if($return_type == 'json'){
			echo json_encode($result);
		}else{
			return $result;
		}
	}

	// 입점사별 공용정보 추출
	public function get_goods_common_info(){

		$goods_kind					= $this->input->get('goods_kind');
		$provider_seq				= $this->input->get('provider_seq');

		unset($sc);
		$sc['provider_seq']			= $provider_seq;
		$commoninfo					= $this->goodsmodel->get_goods_common_info($sc);
		if	($commoninfo){
			$defaultconfig		= $this->goodsmodel->get_goods_default_config($goods_kind, $provider_seq);
			if	($defaultconfig['common_info_seq'] > 0){
				foreach($commoninfo as $k => $data){
					if	($data['info_seq'] == $defaultconfig['common_info_seq']){
						$data['default_selected']	= 'Y';
					}
					$commoninfo[$k]		= $data;
				}
			}

			echo json_encode($commoninfo);
		}
	}

	// 오픈마켓 검색어 가져오기 2018-07-24 #19650
	public function openmarket_keyword() {
		$keyword = $this->input->post('keyword');
		$result = $this->goodsmodel->get_openmarket_keyword($keyword);
		echo json_encode($result);
	}

	public function excel_download(){
		redirect('/selleradmin/excel_spout/excel_download?category=1&searchflag=1');
	}
	
	// 상품 공통 정보 20개 제한 체크
	public function get_good_common_info_check()
	{
		$check = false;
		$provider_seq = $this->input->get('provider_seq');
		$maxCountCommonInfo = $this->goodsmodel->get_max_count_common_info($provider_seq);
		if ($maxCountCommonInfo >= 20) {
			$check = true;
		}

		echo json_encode($check);
	}
}

/* End of file goods.php */
/* Location: ./app/controllers/selleradmin/goods.php */