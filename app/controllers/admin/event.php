<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class event extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
	}

	public function index()
	{
		redirect("/admin/promotion/catalog");
	}

	public function catalog()
	{
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('event_seq', '일련번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('perpage', '페이지갯수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('keyword', '검색어', 'trim|string|xss_clean');
			$this->validation->set_rules('date', '날짜기준', 'trim|string|xss_clean');
			$this->validation->set_rules('sdate', '시작일', 'trim|string|xss_clean');
			$this->validation->set_rules('edate', '종료일', 'trim|string|xss_clean');
			$this->validation->set_rules('event_status[]', '상태', 'trim|string|xss_clean');
			$this->validation->set_rules('sc_event_type', '종류', 'trim|string|xss_clean');
			$this->validation->set_rules('sc_start_st', '시작차수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('sc_end_st', '종료차수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('sc_goods_info', '상품', 'trim|string|xss_clean');
			$this->validation->set_rules('use_coupon', '쿠폰', 'trim|string|xss_clean');
			$this->validation->set_rules('use_coupon_shipping', '배송비쿠폰', 'trim|string|xss_clean');
			$this->validation->set_rules('use_coupon_ordersheet', '주문서쿠폰', 'trim|string|xss_clean');
			$this->validation->set_rules('use_code', '할인코드', 'trim|string|xss_clean');
			$this->validation->set_rules('use_code_shipping', '배송비할인코드', 'trim|string|xss_clean');
			$this->validation->set_rules('display[]', '이벤트 노출', 'trim|string|xss_clean');
			$this->validation->set_rules('event_view[]', '전체 페이지 노출', 'trim|string|xss_clean');
			$this->validation->set_rules('sort', '정렬', 'trim|regex_match[/^(evt.event_seq|evt.regist_date|evt.title|evt.pageview|evt.pageview|)\s(asc|desc)/i]|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('eventmodel');

		$event		= $this->eventmodel->get_event_list($aGetParams);
		$result		= $event['result'];
		$count		= $event['count'];
		$sc			= $event['sc'];

		/* 상품명 getstrcut 자르기 오류로 title 태그 내용 삭제 처리 leewh 2014-12-09 */
		foreach ($result['record'] as $key => $row) {
			if (!empty($row['goods_name'])) {
				$result['record'][$key]['goods_name'] = trim(preg_replace("/<title[^>]*>(.*?)<\/title>/is", "", $row['goods_name']));
			}

			$event_sale		= ($row['target_sale'] == 2 )? get_currency_price($row['event_sale'],2,'basic'). ' 할인' : get_currency_price($row['event_sale'],1).'% 할인';
			$target_sale	= ($row['target_sale'] == 1)? '정가':'판매가';

			//혜택
			if( $result['record'][$key]['event_type'] == "multi" ){			//다중혜택
				$benefitData = $this->eventmodel->get_event_benefit($row['event_seq']);
				if(count($benefitData) > 1){
					$salepricetitle = ($row['goods_rule'] == '')?'상품별 혜택':'카테고리별 혜택';
				}else{
					$salepricetitle = $target_sale.' '.$event_sale;
				}
			}else{
				$salepricetitle = $target_sale.' '.$event_sale;
			}
			$result['record'][$key]['salepricetitle'] = $salepricetitle;

		}

		if(!$sc['date'])				$sc['date']				= "all";
		if(!$sc['sc_goods_type'])		$sc['sc_goods_type']	= "all";
		if(!$sc['event_status'])		$sc['event_status']		= "all";
		if(!$sc['sc_event_type'])		$sc['sc_event_type']	= "all";
		if(!$sc['use_type'])			$sc['use_type']			= "all";
		if(!$sc['display'])				$sc['display']			= "all";
		if(!$sc['event_view'])			$sc['event_view']		= "all";
		$sc['selectbox']['date'][$sc['date']]						= "selected";
		$sc['selectbox']['sc_goods_type'][$sc['sc_goods_type']]		= "selected";
		$sc['checkbox']['event_status'][$sc['event_status']]		= "checked";
		$sc['checkbox']['sc_event_type'][$sc['sc_event_type']]		= "checked";
		$sc['checkbox']['use_type'][$sc['use_type']]				= "checked";
		$sc['checkbox']['display'][$sc['display']]					= "checked";
		$sc['checkbox']['event_view'][$sc['event_view']]			= "checked";

		$this->template->assign(array('list'=>$result['record']));
		$this->template->assign($result);
		$this->template->assign(array(
			'count'=>$count,
			'sc'=>$sc
		));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function event_view(){
		$this->admin_menu();
		$this->tempate_modules();
		$assign_list			= config_load('event');
		$assign_list['mode']	= ($_GET['mode'] == 'gift_event') ? 'gift_event' : 'sale_event' ;

		$assign_list['display']['end_icon']		= ($assign_list['display']['end_icon']) ? $assign_list['display']['end_icon'] : '/data/icon/event/event_icon02.png';
		$assign_list['display']['close_icon']	= ($assign_list['display']['close_icon']) ? $assign_list['display']['close_icon'] : '/data/icon/event/event_icon01.png';
		$assign_list['display']['m_end_icon']	= ($assign_list['display']['m_end_icon']) ? $assign_list['display']['m_end_icon'] : '/data/icon/event/m_event_icon02.png';
		$assign_list['display']['m_close_icon']	= ($assign_list['display']['m_close_icon']) ? $assign_list['display']['m_close_icon'] : '/data/icon/event/m_event_icon01.png';

		$this->template->assign($assign_list);
		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function regist(){

		$this->load->model('categorymodel');
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('goodsdisplay');
		$this->load->model('goodsmodel');
		$this->load->model('eventmodel');
		$this->load->model('providermodel');
		$this->load->model('pagemanagermodel');
		$this->load->helper('accountall');

		$event_seq 					= $_GET['event_seq'];
		$data 						= $this->eventmodel->get_event($event_seq);
		$start_date					= ($data['start_date'] && $data['start_date'] != '0000-00-00 00:00:00')? strtotime($data['start_date']):'';
		$end_date					= ($data['end_date'] && $data['end_date'] != '0000-00-00 00:00:00')? strtotime($data['end_date']):'';
		$data['start_date']			= ($start_date)? date("Y-m-d",$start_date):'';
		$data['start_time']			= ($start_date)? date("H",$start_date):'';
		$data['end_date']			= ($end_date)? date("Y-m-d",$end_date):'';
		$data['end_time']			= ($end_date)? date("H",$end_date):'';
		$data['end_datetime']		= ($end_date)? date("Y-m-d H:59",$end_date):'';
		$data['app_start_hour']		= ($data['app_start_time'])? substr($data['app_start_time'], 0, 2):'';
		$data['app_start_minute']	= ($data['app_start_time'])? substr($data['app_start_time'], 2, 2):'';
		$data['app_end_hour']		= ($data['app_end_time'])? substr($data['app_end_time'], 0, 2):'';
		$data['app_end_minute']		= ($data['app_end_time'])? substr($data['app_end_time'], 2, 2):'';
		$data['tpl_path']			= str_replace('etc/','',$data['tpl_path']);

		// 검색필터만 값이 없을 경우 기본값을 노출해야 하기때문에 pagemanagermodel 에서 통합 관리함
		$aPageManager			= $this->pagemanagermodel->get_page_config('sales_event');

		$filter_col_tmp 			= $aPageManager['filter_col'];
		$aPageManager['filter_col'] 	= '';
		if($filter_col_tmp){
			$i = 0;
			foreach($filter_col_tmp as $k => $filter_col){
				foreach($filter_col['item'] as $k2 => $v2){
					$aPageManager['filter_col'][$i][$k2] = $v2;
				}
				$i++;
			}
		}

		if( !$data['search_filter'] ){
			$sDefaultSearchFilter	= implode(',', $this->pagemanagermodel->default_filters['sales_event']);
			$data['search_filter'] = $sDefaultSearchFilter;
		}
		if( !$data['search_orderby'] ){
			$data['search_orderby'] = 'rank';
		}

		$_data_benefit = $this->eventmodel->get_event_benefit($event_seq,'event_benefits_seq ASC');
		foreach($_data_benefit as $row){
			$_data_choice_goods = $this->eventmodel->get_event_choice_goods($row['event_benefits_seq']);
			foreach($_data_choice_goods as $row2){
				if($row2['category_code']){
					$row2['category_name'] = $this->categorymodel->get_category_name($row2['category_code']);
				}
				$row[$row2['choice_type']][] = $row2;
			}

			//이벤트 할인율은 소숫점 입력 불가.
			if($row['target_sale'] != 2) $row['event_sale'] = (int)$row['event_sale'];
			if($row['event_reserve'] != '') $row['event_reserve'] = (int)$row['event_reserve'];
			if($row['event_point'] != '') $row['event_point'] = (int)$row['event_point'];

			// 리뉴얼된 정산 판매 수수료 전체 적용 :: 2016-11-09 lwh
			if(!$row['rate_type_saco'] && !$row['rate_type_suco'] && !$row['rate_type_supr'] && $row['saller_rate_type']){
				if		($row['saller_rate_type'] == '1'){
					$row['rate_type_saco']	= 'ignore';
					$row['rate_type_suco']	= 'ignore';
					$row['rate_type_supr']	= 'equal';
					$row['saco_value']		= $row['saller_rate'];
					$row['suco_value']		= $row['saller_rate'];
				}else if($row['saller_rate_type'] == '2'){
					$saller_rate = $row['saller_rate'];
					if ($row['saller_rate'] < 0){
						$saller_rate = str_replace("-","",$saller_rate);
													$rate_type = 'minus';
					}else							$rate_type = 'plus';
					$row['rate_type_saco']	= $rate_type;
					$row['rate_type_suco']	= $rate_type;
					$row['rate_type_supr']	= 'equal';
					$row['saco_value']		= $saller_rate;
					$row['suco_value']		= $saller_rate;
				}
			}
			$data['rate_type_saco']		= $row['rate_type_saco'];
			$data['rate_type_suco']		= $row['rate_type_suco'];
			$data['rate_type_supr']		= $row['rate_type_supr'];
			$data['saco_value']			= $row['saco_value'];
			$data['suco_value']			= $row['suco_value'];
			$data['supr_value']			= $row['supr_value'];

			// 이벤트 할인 분담금 추가 :: 2018-05-03 lwh
			$data['salescost_admin']	= $row['salescost_admin'];
			$data['salescost_provider']	= $row['salescost_provider'];

			if($row['provider_list']){
				$data['provider_name_list']	= $this->providermodel->get_provider_select_list($row['provider_list']);
			}

			// 혜택부담대상
			if(count($data['provider_name_list']) > 0 ){
				$data['sales_tag'] = 'provider';
			}else{
				$data['sales_tag'] = 'admin';
			}
			/* 이벤트 상품의 입점사 수수료 조정 추가 leewh 2014-06-13 */
			if (isset($row['saller_rate_type'])) {
				if ($row['saller_rate_type']==2) {
					if ($row['saller_rate'] < 0) {
						$row['saller_rate'] = str_replace("-","",$row['saller_rate']);
						$data['saller_rate_num'] = "1";
					}
				}

				$data['saller_rate_type']	= $row['saller_rate_type'];
				$data['saller_rate']	= $row['saller_rate'];
			}

			$data['data_choice'][] = $row;
		}

		/* 샘플 상품 정보 */
		$sampleGoodsInfo = array(
			'goods_name' => '샘플 상품',
			'price' => '19800',
			'consumer_price' => '24800',
		);

		$styles = array();
		foreach($this->goodsdisplay->styles as $k=>$v)
		{
			if(in_array($k,array('lattice_a','lattice_b','list'))){
				$styles[$k]=$v;
			}
		}

		/* 리스트 이미지 꾸미기 값 파싱 */
		$list_image_decorations = $this->goodsdisplay->decode_image_decorations($data['list_image_decorations']);

		if	($event_seq){
			$stats	= $this->eventmodel->get_event_order_result($event_seq);
		}

		$this->template->assign(array(
			'stats'							=> $stats,
			'styles'						=> $styles,
			'orders'						=> $this->goodsdisplay->orders,
			'goodsImageSizes'				=> config_load('goodsImageSize'),
			'list_image_decorations'		=> $list_image_decorations,
			'sampleGoodsInfo'				=> $sampleGoodsInfo
		));

		$this->template->assign("event",$data);
		$this->template->assign(array('snsevent' => 'event'));

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
		$this->template->assign('query_string',$_GET['query_string']); //########### 16.10.27 : 이전 검색 리스트로 돌아가도록 처리

		### [반응형스킨] 운영방식 추가 :: 2018-11-01 pjw
		$operation_type = !empty($this->config_system['operation_type']) ? $this->config_system['operation_type'] : 'heavy';
		$this->template->assign('operation_type', $operation_type);

		// 반응형 상품정보 선택기능 추가 :: 2019-05-17 pjw
		$this->load->model('designmodel');
		$goods_info_style = $this->designmodel->get_goods_info_style('search_list', $data['goods_info_style']);

		$this->template->assign('goods_info_style', $goods_info_style);
		$this->template->define('goods_info_style', $this->skin.'/page_manager/_goods_info_style.html');

		// 이벤트 페이지 레이아웃 rowspan 계산 추가 :: 2018-11-01 pjw
		$rowspan = 3;
		if($data['event_seq'] > 0)		$rowspan++;
		if($operation_type == 'light')	$rowspan = $rowspan + 6;
		$this->template->assign('rowspan', $rowspan);

		//할인이벤트 할인금액 입점사 부담율 설정 제한. @2019-07-25 pjm
		//(구)정산 -> (신)정산 마이그레이션을 했다면 마이그레이션을 진행한 익월 부터 사용 가능
		$accountAllMiDate = getAccountSetting();
		if($accountAllMiDate['migrationCheckDate'] == "0000-00-00") $accountAllMiDate['migrationCheckDate'] = "";
		if(!$accountAllMiDate['migrationCheckDate'] || $accountAllMiDate['migrationCheckDate'] <= date("Y-m")){
			$provider_sale_use = true;
		}else{
			$provider_sale_use = false;
			$provider_sale_msg = "할인이벤트 입점판매자 할인 분담율 설정은 '(신)정산' 마이그레이션을 진행한 익월(".$accountAllMiDate['migrationCheckDate'].")부터 사용가능합니다.";
		}

		$file_path	= $this->template_path();
		$this->template->assign(array('provider_sale_use'=>$provider_sale_use,'provider_sale_msg'=>$provider_sale_msg));
		$this->template->assign('aPageManager', $aPageManager);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}



	public function gift_catalog()
	{
		serviceLimit('H_FR','process');

		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('eventmodel');
		$this->load->model("providermodel");
		$this->load->model('shippingmodel');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('gift_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$sc				= $_GET;
		$sc['sort']		= $_GET['sort'] ? $_GET['sort'] : 'evt.gift_seq desc';
		$sc['page']		= (isset($_GET['page']) && $_GET['page'] > 1) ? intval($_GET['page']):'0';
		$sc['keyword']	= $_GET['keyword'];
		$sc['perpage']	= $_GET['perpage'] ? $_GET['perpage'] : 10;

		$result		= $this->eventmodel->get_gift_event_list($sc);
		foreach($result['record'] as $k=>$v){

			// 배송그룹 미지정인 경우 (교환조건) / 데이터 오류인경우 -> 자동 본사 연결
			if	( !$v['shipping_group_seq'] ){
				if(!$v['provider_seq']) $v['provider_seq'] = '1';
				$ship_info = $this->shippingmodel->get_shipping_base($v['provider_seq']);
				$v['shipping_group_seq']	= $ship_info['shipping_group_seq'];
				$v['shipping_group_name']	= $ship_info['shipping_group_name'];

				$setParam['provider_seq']			= $v['provider_seq'];
				$setParam['shipping_group_seq']		= $v['shipping_group_seq'];

				$this->db->where("gift_seq",$v['gift_seq']);
				$this->db->update("fm_gift",$setParam);
			}

			// 입점사 명 추출
			if	( !$v['provider_name'] ){
				if	($v['provider_seq'] == 1)	$v['provider_name']	= '본사';
				else{
					$provider = $this->providermodel->get_provider($v['provider_seq']);
					$v['provider_name']		= $provider['provider_name'];
				}
			}

			// 배송 그룹명 추출
			if	( !$v['shipping_group_name'] ){
				$ship_info = $this->shippingmodel->get_shipping_group($v['shipping_group_seq']);
				$v['shipping_group_name']	= $ship_info['shipping_group_name'];
			}

			$result['record'][$k] = $v;
		}

		if(!$sc['event_status'])		$sc['event_status'] = "all";
		if(!$sc['gift_gb'])				$sc['gift_gb'] = "all";
		if(!$sc['display'])				$sc['display'] = "all";
		if(!$sc['event_view'])			$sc['event_view'] = "all";
		$sc['checkbox']['event_status'][$sc['event_status']]	= "checked";
		$sc['checkbox']['gift_gb'][$sc['gift_gb']]				= "checked";
		$sc['checkbox']['display'][$sc['display']]				= "checked";
		$sc['checkbox']['event_view'][$sc['event_view']]		= "checked";

		$this->template->assign($result);
		$this->template->assign(array('count'=>$count,'sc'=>$sc));

		$provider			= $this->providermodel->provider_goods_list();
		$this->template->assign('provider',$provider);

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function gift_regist(){

		$this->load->model('categorymodel');
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('goodsdisplay');
		$this->load->model('goodsmodel');
		$this->load->model('providermodel');
		$this->load->model('pagemanagermodel');
		$this->load->model('eventmodel');

		if( defined('__SELLERADMIN__') === true ){
			$provider_info['provider_seq']	= $this->providerInfo['provider_seq'];
			$provider_info['provider_name']	= $this->providerInfo['provider_name'];
		}else{
			$provider = $this->providermodel->provider_goods_list_sort();
			if(!$provider || serviceLimit('H_NAD')){
				$provider_info['provider_seq']	= 1;
				$provider_info['provider_name']	= '본사';
			}else{
				$provider_lists = array("1"=>array("provider_seq"=>1,"provider_name"=>"본사"));
				foreach($provider as $_provider){
					$provider_lists[$_provider['provider_seq']] = $_provider;
				}
			}
		}

		$event_seq = $_GET['event_seq'];

		if($event_seq){
			$data 				= $this->eventmodel->get_gift($event_seq);
			if($data['gift_gb'] == 'buy' && !$data['goods_rule']) $data['goods_rule'] = 'reserve';
			$data['tpl_path']	= str_replace('etc/','',$data['tpl_path']);
		}

		// [반응형스킨] 검색필터 추가 :: 2018-11-01 pjw
		// 검색필터만 값이 없을 경우 기본값을 노출해야 하기때문에 pagemanagermodel 에서 통합 관리함
		$aPageManager			= $this->pagemanagermodel->get_page_config('gift_event');

		$filter_col_tmp 			= $aPageManager['filter_col'];
		$aPageManager['filter_col'] 	= '';
		if($filter_col_tmp){
			$i = 0;
			foreach($filter_col_tmp as $k => $filter_col){
				foreach($filter_col['item'] as $k2 => $v2){
					$aPageManager['filter_col'][$i][$k2] = $v2;
				}
				$i++;
			}
		}

		if( !$data['search_filter'] ){
			$sDefaultSearchFilter	= implode(',', $this->pagemanagermodel->default_filters['gift_event']);
			$data['search_filter'] = $sDefaultSearchFilter;
		}
		if( !$data['search_orderby'] ){
			$data['search_orderby'] = 'rank';
		}

		$gift_gb = $_GET['gb'];
		if($data['gift_gb']){
			$gift_gb = $data['gift_gb'];
		}
		$this->template->assign("gift_gb", $gift_gb);

		if($data['goods_rule']=='goods'){
			$_rows = $this->eventmodel->get_gift_choice($data['gift_seq']);
			foreach ($_rows as $row){
				if(serviceLimit('H_AD')){
					$row['provider_name'] = $provider_lists[$row['provider_seq']]['provider_name'];
				}
				$limit_goods[] = $row;
			}

			if($limit_goods) $this->template->assign('issuegoods',$limit_goods);

		}else if($data['goods_rule']=='category'){
			###
			$this->load->model('categorymodel');
			$this->db->where('gift_seq', $data['gift_seq']);
			$query = $this->db->get('fm_gift_choice');
			foreach ($query->result_array() as $row){
				$row['title'] =  $this->categorymodel->get_category_name($row['category_code']);
				$limit_cate[] = $row;
			}
			if($limit_cate) $this->template->assign('issuecategorys',$limit_cate);
		}

		### 사은품 혜택
		$_benefit_rows = $this->eventmodel->get_gift_benefit($data['gift_seq']);
		switch($data['gift_rule']){
			case 'default':
				$temp = $_benefit_rows;
				$this->template->assign('default',$temp[0]);
				$search = str_replace("|", ",", $temp[0]['gift_goods_seq']);
				if($search == "") $search = "''";
				$lists = $this->eventmodel->get_gift_goods($search);
				if($lists) $this->template->assign('defaultGifts',$lists);
			break;

			case 'price':
				$cnt = 1;
				foreach ($_benefit_rows as $v){
					$search = str_replace("|", ",", $v['gift_goods_seq']);
					if($search == "") $search = "''";
					$lists = $this->eventmodel->get_gift_goods($search);
					$v['gifts']		= $lists;
					$v['num']		= $cnt;
					$priceLoop[]	= $v;
					$cnt++;
				}
				$this->template->assign('priceLoop',$priceLoop);
			break;

			case 'quantity':
				foreach ($_benefit_rows as $v){
					$gift_goods_seq = $v['gift_goods_seq'];
					$qtyLoop[] = $v;
				}
				$this->template->assign('qtyLoop',$qtyLoop);
				$search = str_replace("|", ",", $gift_goods_seq);
				if($search == "") $search = "''";
				$lists = $this->eventmodel->get_gift_goods($search);
				if($lists) $this->template->assign('qtyGifts',$lists);
			break;
			case 'lot':
				foreach ($_benefit_rows as $v){
					$gift_goods_seq = $v['gift_goods_seq'];
					$lotLoop[] = $v;
				}
				$this->template->assign('lotLoop',$lotLoop);

				$search = str_replace("|", ",", $gift_goods_seq);
				if($search == "") $search = "''";
				$lists = $this->eventmodel->get_gift_goods($search);
				if($lists) $this->template->assign('lotGifts',$lists);
			break;
		}


		/* 샘플 상품 정보 */
		$sampleGoodsInfo = array(
			'goods_name' => '샘플 상품',
			'price' => '19800',
			'consumer_price' => '24800',
		);

		$styles = array();
		foreach($this->goodsdisplay->styles as $k=>$v)
		{
			if(in_array($k,array('lattice_a','lattice_b','list'))){
				$styles[$k]=$v;
			}
		}

		/* 리스트 이미지 꾸미기 값 파싱 */
		$list_image_decorations = $this->goodsdisplay->decode_image_decorations($data['list_image_decorations']);

		// 수정인 경우 배송그룹 조회 :: 2016-11-08 lwh
		if($data['shipping_group_seq']){
			$this->load->model('shippingmodel');
			$ship_grp_list = $this->shippingmodel->get_shipping_group_simple($data['provider_seq']);
			$this->template->assign("ship_grp_list",$ship_grp_list);
		}else if($provider_info['provider_seq']){
			$this->load->model('shippingmodel');
			$ship_grp_list = $this->shippingmodel->get_shipping_group_simple($provider_info['provider_seq']);
			$this->template->assign("ship_grp_list",$ship_grp_list);
		}

		$this->template->assign(array(
			'styles'						=> $styles,
			'orders'						=> $this->goodsdisplay->orders,
			'goodsImageSizes'				=> config_load('goodsImageSize'),
			'list_image_decorations'		=> $list_image_decorations,
			'sampleGoodsInfo'				=> $sampleGoodsInfo,
			'mode'							=> $_GET['mode'],
		));
		if($provider_info){
			$this->template->assign("provider_info",$provider_info);
		}else{
			$this->template->assign("provider",$provider);
		}

		$this->template->assign("event",$data);
		$this->template->assign(array('snsevent' => 'event'));

		### [반응형스킨] 운영방식 추가 :: 2018-11-01 pjw
		$operation_type = !empty($this->config_system['operation_type']) ? $this->config_system['operation_type'] : 'heavy';
		$this->template->assign('operation_type', $operation_type);

		// 상품정보 선택기능 추가 :: 2019-05-17 pjw
		$this->load->model('designmodel');
		$goods_info_style = $this->designmodel->get_goods_info_style('search_list', $data['goods_info_style']);

		$this->template->assign('goods_info_style', $goods_info_style);
		$this->template->define('goods_info_style', $this->skin.'/page_manager/_goods_info_style.html');


		// 이벤트 페이지 레이아웃 rowspan 계산 추가 :: 2018-11-01 pjw
		$rowspan = 3;
		if($data['event_seq'] > 0)		$rowspan++;
		if($operation_type == 'light')	$rowspan = $rowspan + 6;
		$this->template->assign('rowspan', $rowspan);

		$file_path	= $this->template_path();
		$this->template->assign("aPageManager", $aPageManager);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function test(){
		$this->load->model('goodssummarymodel');
		$this->goodssummarymodel->set_event_price();
	}

	## 사은품 지급 상세 로그  2015-05-14 pjm
	public function gift_use_log(){

		$this->load->model('giftmodel');

		$giftlog = $this->giftmodel->get_gift_order_log($_POST['order_seq'],$_POST['item_seq']);

		$this->template->assign(array('giftlog'	=> $giftlog[0]));

		$file_path = dirname($this->template_path()).'/../event/gift_use_log.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	// 배송그룹 검색하기 :: 2016-11-08 lwh
	public function ship_grp_ajax(){
		if($_GET['provider_seq']){
			$this->load->model('shippingmodel');
			$grp_list = $this->shippingmodel->get_shipping_group_simple($_GET['provider_seq']);
			$res_arr = array();
			foreach($grp_list as $key => $val){
				$res['shipping_group_seq']	= $val['shipping_group_seq'];
				$res['shipping_group_name']	= $val['shipping_group_name'];

				$res_arr[$key] = $res;
			}

			echo json_encode($res_arr);
		}else{
			return false;
		}
	}
}

/* End of file event.php */
/* Location: ./app/controllers/admin/event.php */