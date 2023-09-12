<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class statistic_goods extends selleradmin_base {

	public function __construct() {
		parent::__construct();

		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('statsmodel');
		$this->load->library('validation');
		$this->load->model('usedmodel');

		### SERVICE CHECK
		$result = $this->usedmodel->used_service_check('statistic_goods_detail');
		if(!$result['type']){
			$this->template->assign('statistic_goods_detail_limit','Y');
		}

		$this->seriesColors = array("#445ebc", "#d33c34","#4bb2c5", "#c5b47f", "#EAA228", "#579575", "#839557", "#958c12",
        "#953579", "#4b5de4", "#d8b83f", "#ff5800", "#0085cc", "#c3b8f3", "#EA28A2", "#8566cc");
		$this->template->assign(array('seriesColors'=>$this->seriesColors));

		/* 쇼핑몰분석통계 메뉴 */
		$goods_menu = $this->uri->rsegments[count($this->uri->rsegments)];
		$goods_menu = str_replace(array("_monthly","_daily"),"",$goods_menu);
		$this->template->assign(array('selected_goods_menu'=>$goods_menu));
		$this->template->assign(array('service_code' => $this->config_system['service']['code']));
	}

	public function index()
	{
		redirect("/selleradmin/statistic_goods/goods_cart");
	}

	public function goods_cart()
	{
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('orderby', '정렬', 'trim|string|xss_clean');
			$this->validation->set_rules('keyword', '검색어', 'trim|string|xss_clean');
			$this->validation->set_rules('sdate', '시작일', 'trim|string|xss_clean');
			$this->validation->set_rules('edate', '종료일', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		serviceLimit('H_FR','process');

		$cfg_order = config_load('order');
		$statlist	= array();

		$getParams = $this->input->get();
		/*
		if ($_SERVER["QUERY_STRING"] == null) {
			$getParams['sdate'] = date('Y-m-01');
			$getParams['edate'] = date('Y-m-d');
		}*/

		$params['sdate'] = $getParams['sdate'];
		$params['edate'] = $getParams['edate'];
		$params['provider_seq']	= $this->session->userdata['provider']['provider_seq'];
		$params['keyword'] = trim($getParams['keyword']);
		$params['category1'] = trim($getParams['category1']);
		$params['category2'] = trim($getParams['category2']);
		$params['category3'] = trim($getParams['category3']);
		$params['category4'] = trim($getParams['category4']);
		$params['brands1'] = trim($getParams['brands1']);
		$params['brands2'] = trim($getParams['brands2']);
		$params['brands3'] = trim($getParams['brands3']);
		$params['brands4'] = trim($getParams['brands4']);
		$params['order_by'] = trim($getParams['order_by']);

		$statQuery = $this->statsmodel->get_goods_cart_stats($params);
		if	($statQuery){
			foreach($statQuery->result_array() as $k => $data){
				$data['stock'] = 0;
				$data['badstock'] = 0;
				$data['reservation15'] = 0;
				$data['reservation25'] = 0;

				unset($optParams);
				$optParams['goods_seq']	= $data['goods_seq'];
				$optParams['sdate']	= $getParams['sdate'];
				$optParams['edate']	= $getParams['edate'];
				$optQuery = $this->statsmodel->get_option_cart_stats($optParams);
				foreach($optQuery->result_array() as $o => $optData){
					$data['tstock'] += $optData['stock'];
					$data['tbadstock'] += $optData['badstock'];
					$data['treservation15']	+= $optData['reservation15'];
					$data['treservation25']	+= $optData['reservation25'];

					$data['options'][] = $optData;
				}
				$statlist[] = $data;
			}
		}

		$this->load->model('providermodel');
		$provider = $this->providermodel->provider_goods_list();

		$this->template->assign(array('sc'=>$_GET));
		$this->template->assign(array('provider'=>$provider));
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign(array('statlist'=>$statlist));

		//검색
		$sc = $this->input->get();		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));	

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function goods_wish(){

		$cfg_order = config_load('order');
		$statlist	= array();

		$getParams = $this->input->get();
		/*
		if ($_SERVER["QUERY_STRING"] == null) {
			$getParams['sdate'] = date('Y-m-01');
			$getParams['edate'] = date('Y-m-d');
		}*/
		$params['sdate'] = ($getParams['sdate']);
		$params['edate'] = ($getParams['edate']);
		$params['provider_seq']	= $this->session->userdata['provider']['provider_seq'];
		$params['keyword'] = trim($getParams['keyword']);
		$params['category1'] = trim($getParams['category1']);
		$params['category2'] = trim($getParams['category2']);
		$params['category3'] = trim($getParams['category3']);
		$params['category4'] = trim($getParams['category4']);
		$params['brands1'] = trim($getParams['brands1']);
		$params['brands2'] = trim($getParams['brands2']);
		$params['brands3'] = trim($getParams['brands3']);
		$params['brands4'] = trim($getParams['brands4']);
		$params['order_by'] = trim($getParams['order_by']);

		$statSql = $this->statsmodel->get_goods_wish_stats($params);
		$statlist = $statSql->result_array();

		$this->template->assign(array('sc'=>$getParams));
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign(array('statlist'=>$statlist));

		//검색
		$sc = $this->input->get();		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));	

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function goods_review(){

		$cfg_order = config_load('order');
		$statlist	= array();

		$getParams = $this->input->get();
		/*
		if ($_SERVER["QUERY_STRING"] == null) {
			$getParams['sdate'] = date('Y-m-01');
			$getParams['edate'] = date('Y-m-d');
		}*/
		$params['sdate'] = ($getParams['sdate']);
		$params['edate'] = ($getParams['edate']);
		$params['provider_seq']	= $this->session->userdata['provider']['provider_seq'];
		$params['keyword'] = trim($getParams['keyword']);
		$params['category1'] = trim($getParams['category1']);
		$params['category2'] = trim($getParams['category2']);
		$params['category3'] = trim($getParams['category3']);
		$params['category4'] = trim($getParams['category4']);
		$params['brands1'] = trim($getParams['brands1']);
		$params['brands2'] = trim($getParams['brands2']);
		$params['brands3'] = trim($getParams['brands3']);
		$params['brands4'] = trim($getParams['brands4']);
		$params['order_by'] = trim($getParams['order_by']);

		$statSql = $this->statsmodel->get_goods_review_stats($params);
		$statlist = $statSql->result_array();

		$this->template->assign(array('sc'=>$getParams));
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign(array('statlist'=>$statlist));

		//검색
		$sc = $this->input->get();		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));	

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function goods_restock(){

		$cfg_order = config_load('order');
		$statlist	= array();

		$getParams = $this->input->get();
		/*
		if ($_SERVER["QUERY_STRING"] == null) {
			$getParams['sdate'] = date('Y-m-01');
			$getParams['edate'] = date('Y-m-d');
		}*/
		$params['sdate'] = ($getParams['sdate']);
		$params['edate'] = ($getParams['edate']);
		$params['provider_seq']	= $this->session->userdata['provider']['provider_seq'];
		$params['keyword'] = trim($getParams['keyword']);
		$params['category1'] = trim($getParams['category1']);
		$params['category2'] = trim($getParams['category2']);
		$params['category3'] = trim($getParams['category3']);
		$params['category4'] = trim($getParams['category4']);
		$params['brands1'] = trim($getParams['brands1']);
		$params['brands2'] = trim($getParams['brands2']);
		$params['brands3'] = trim($getParams['brands3']);
		$params['brands4'] = trim($getParams['brands4']);
		$params['order_by'] = trim($getParams['order_by']);

		$statSql	= $this->statsmodel->get_goods_restock_stats($params);
		$statlist	= $statSql->result_array();

		$this->template->assign(array('sc'=>$getParams));
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign(array('statlist'=>$statlist));

		//검색
		$sc = $this->input->get();		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));	

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 매출통계 :: 2014-12-30 lwh */
	public function goods_stat()
	{
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('sdate', '시작일', 'trim|string|xss_clean');
			$this->validation->set_rules('edate', '종료일', 'trim|string|xss_clean');
			$this->validation->set_rules('sitetype[]', '사이트 구분', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$this->load->model('providermodel');
		$provider			= $this->providermodel->provider_goods_list();

		// 통계 개선 패치 체크 :: 2021-03-17 sms
		$this->load->helper('accountall');
		$accountAllMiDate	= getAccountSetting();
		$accountAllStatsV2	= $accountAllMiDate['accountall_stats_v2'];
		$this->template->assign(array('statsPatch'	=> $accountAllStatsV2));

		$param['sdate']		= ($aGetParams['sdate']) ? $aGetParams['sdate'] : date('Y-m-d',strtotime("-7 day"));
		$param['edate']		= ($aGetParams['edate']) ? $aGetParams['edate'] : date("Y-m-d");
		$param['sitetype']	= ($aGetParams['sitetype']) ? $aGetParams['sitetype'] : array('P','M','F');
		$param['provider_seq']	= $this->session->userdata['provider']['provider_seq'];

		if($accountAllStatsV2){
			$aGetParam['year']	= (trim($aGetParams['year']))	? trim($aGetParams['year'])	: date('Y');
			$aGetParam['month']	= (trim($aGetParams['s_month']))	? trim($aGetParams['s_month'])	: date('m');
			$aGetParam['month'] = sprintf("%02d",$aGetParam['month']);
			$param['sdate']		= $aGetParam['year'].$aGetParam['month'];
		}

		// 입점사 상품별 리스트 데이터 추출
		$tmpList	= $this->statsmodel->seller_goods_stat($param);
		foreach($tmpList as $data){
			$data['sell_price']	= $data['price_sum'] - $data['sale_sum'];
			$statList[] = $data;

			// 총합 계산
			$totalSell['ea']		+= $data['ea_sum'];
			$totalSell['price']		+= $data['price_sum'];
			$totalSell['sale']		+= $data['sale_sum'];
			$totalSell['sell']		+= $data['sell_price'];

			//상품별 배송그룹 배송비계산 @2016-08-30 ysm
			$param['goods_seq']		= $data['goods_seq'];
			$goods_shipping_code	= $this->statsmodel->seller_goods_shipping_code($param);
			$totalSell['shipping']	+= (int)($goods_shipping_code['shipping_sum']);//$data['shipping_sum'];
		}

		$toRefund	= $this->statsmodel->refund_stat($param);
		$totalSell['refund_price']			= $toRefund['refund'][0]['refund_price'];
		$totalSell['return_shipping_price']	= $toRefund['return'][0]['return_shipping_price'];
		$totalSell['total_price']			= $totalSell['sell'] + $totalSell['shipping'] - $totalSell['refund_price'];

		$this->template->assign(array(
			'provider'		=> $provider,
			'param'			=> $param,
			'totalSell'		=> $totalSell,
			'statList'		=> $statList
		));

		//판매환경
		$this->sitetypeloop = sitetype($aGetParams['sitetype'], 'image', 'array');
		if(!count($_GET)) $_GET['sitetype'] = array_keys($this->sitetypeloop);

		$this->template->assign(array(
			'service_code' => $this->config_system['service']['code'],
			'sitetype' => $_GET['sitetype'],
			'sitetypeloop' => $this->sitetypeloop
		));

		//검색
		$sc = $this->input->get();
		if(!$sc['s_month']) $sc['s_month']		= date("m");		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));	

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function goods_sales(){

		# npay 2.1 사용시
		$npayconfig = config_load('navercheckout');
		if($npayconfig['use'] == 'y' && $npayconfig['version'] == '2.1'){
			$this->arr_payment = array_merge($this->arr_payment,config_load('npay_payment'));
		}

		if	(!$_GET['date_type'])	$_GET['date_type']	= 'month';
		$file_path	= $this->template_path();
		switch($_GET['date_type']){
			case 'month':
			default:
				$this->sales_monthly();
				$file_path	= str_replace('goods_sales.html', 'sales_monthly.html', $file_path);
			break;
			case 'daily':
				$this->sales_daily();
				$file_path	= str_replace('goods_sales.html', 'sales_daily.html', $file_path);
			break;
			case 'hour':
				$this->sales_hour();
				$file_path	= str_replace('goods_sales.html', 'sales_hour.html', $file_path);
			break;
		}

		//판매환경
		$this->sitetypeloop = sitetype($_GET['sitetype'], 'image', 'array');
		if(!count($_GET['sitetype'])) $_GET['sitetype'] = array_keys($this->sitetypeloop);

		$this->template->assign(array(
			'service_code' => $this->config_system['service']['code'],
			'sitetype'=>$_GET['sitetype'],
			'sitetypeloop'=>$this->sitetypeloop
		));


		//검색
		$sc = $this->input->get();		
		if(!$sc['s_year']) $sc['s_year']		= date("Y");
		if(!$sc['s_month']) $sc['s_month']		= date("m");
		if(!$sc['s_day']) $sc['s_day']			= date("d");
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 월별  매출 통계 */
	public function sales_monthly()
	{
		ini_set("memory_limit" , -1);

		$this->load->helper("order");
		$this->load->helper('accountall');
		$this->load->model('accountallmodel');
		$npay_use = npay_useck();

		// 신규 정산을 이용할 경우 마이그레이션 다음 달부터 적용되도록 처리 :: 2017-06-14 lkh
		$accountAllMiDate = config_load("accountall_setting","accountall_migration_date");
		$accountAllMigrationDate = $accountAllMiDate['accountall_migration_date'];
		$migrationYear = substr($accountAllMigrationDate,0,4);
		$migrationMonth = (substr($accountAllMigrationDate,5,2)+1);
		$checkDate = date("Y-m");
		$migrationCheckDate = $migrationYear."-".sprintf("%02d",$migrationMonth);

		$params['year']		= (trim($_GET['year'])) ? trim($_GET['year'])	: date('Y');

		$params['sitetype']	= ($_GET['sitetype'])	? $_GET['sitetype']		: array();
		$params['provider_seq']	= $this->providerInfo['provider_seq'];

		// 강제수집
		$params['tryScrap']		= (trim($_GET['tryScrap'])) ? trim($_GET['tryScrap'])	: null;

		list($year, $month) = $this->accountallmodel->get_time_range();
		$this->template->assign(array('year'=>$year,'month'=>$month));

		// 통계 데이터 수집 요청 변수
		$this->accountallmodel->jsonRequestScrapSalesMonthly = array();
		$params['save_provider_seq']	= $params['provider_seq'];

		$statsData			= array();
		$params['q_type']	= 'order';
		$result	= $this->accountallmodel->get_sales_sales_monthly_stats($params);
		foreach($result as $row){
			$statsData[$row['stats_month']-1]	= is_array($statsData[$row['stats_month']-1]) ? array_merge($statsData[$row['stats_month']-1],$row) : $row;
		}

		$params['q_type']	= 'refund';
		$result	= $this->accountallmodel->get_sales_sales_monthly_stats($params);
		foreach($result as $row){
			$statsData[$row['stats_month']-1]	= is_array($statsData[$row['stats_month']-1]) ? array_merge($statsData[$row['stats_month']-1],$row) : $row;
		}

		// 통계 데이터 수집 요청 변수 바인드
		$this->template->assign(array(
			'jsonRequestScrapSalesMonthly'	=> str_replace("\"", "\\\"", json_encode($this->accountallmodel->jsonRequestScrapSalesMonthly))
		));

		/* 매출액, 매입금액평균, 순이익 계산 */
		foreach($statsData as $i => $row){
			// 주문금액
			$statsData[$i]['month_order_price'] = $row['month_settleprice_sum'];

			// 주문건수
			$statsData[$i]['month_count_sum'] = $row['month_count_sum'];

			// 에누리 가격 가감
			//$statsData[$i]['m_settleprice_sum'] -= $statsData[$i]['m_enuri_sum'];
			//$statsData[$i]['p_settleprice_sum'] -= $statsData[$i]['p_enuri_sum'];

			// 본사 결제금액 계산 :: 2015-10-06 lwh
			$statsData[$i]['month_m_order_price'] = $statsData[$i]['m_settleprice_sum'] + $statsData[$i]['m_shipping_cost_sum'] + $statsData[$i]['m_goods_shipping_cost_sum'];
			// 입점사 결제금액 계산 :: 2015-10-06 lwh
			$statsData[$i]['month_p_order_price'] = $statsData[$i]['p_settleprice_sum'] + $statsData[$i]['p_shipping_cost_sum'] + $statsData[$i]['p_goods_shipping_cost_sum'];

			//-----------환불데이터 (본사/입점사 나눠있지 않은경우) ---//
			if( ($statsData[$i]['month_m_refund_price_sum'] +
			$statsData[$i]['month_p_refund_price_sum']) == 0 && $statsData[$i]['month_refund_price_sum'] > 0 ){
				$statsData[$i]['month_m_refund_price_sum'] = $statsData[$i]['month_refund_price_sum'];

				$statsData[$i]['month_m_refund_price_total_sum'] += $statsData[$i]['month_m_refund_price_sum'];
			}

			// 환불 마이너스 처리
			$statsData[$i]['month_m_refund_price_total_sum'] = $statsData[$i]['month_m_refund_price_total_sum'] * -1;
			$statsData[$i]['month_p_refund_price_total_sum'] = $statsData[$i]['month_p_refund_price_total_sum'] * -1;
			$statsData[$i]['month_refund_price_total_sum'] = $statsData[$i]['month_refund_price_total_sum'] * -1;

			// 취소/반품 마이너스 처리
			$statsData[$i]['month_m_refund_price_sum'] = $statsData[$i]['month_m_refund_price_sum'] * -1;
			$statsData[$i]['month_p_refund_price_sum'] = $statsData[$i]['month_p_refund_price_sum'] * -1;
			$statsData[$i]['month_refund_price_sum'] = $statsData[$i]['month_refund_price_sum'] * -1;

			// 되돌리기 마이너스 처리
			$statsData[$i]['month_m_rollback_price_sum'] = $statsData[$i]['month_m_rollback_price_sum'] * -1;
			$statsData[$i]['month_p_rollback_price_sum'] = $statsData[$i]['month_p_rollback_price_sum'] * -1;
			$statsData[$i]['month_rollback_price_sum'] = $statsData[$i]['month_rollback_price_sum'] * -1;

			// 매입가 마이너스 처리
			$statsData[$i]['month_supply_price_sum']		= $statsData[$i]['month_supply_price_sum'] * -1;
			$statsData[$i]['month_commission_price_sum']	= $statsData[$i]['month_commission_price_sum'] * -1;

			// 매출액
			$statsData[$i]['month_m_sales_price'] = $statsData[$i]['month_m_order_price'] + $statsData[$i]['month_m_refund_price_total_sum'];
			$statsData[$i]['month_p_sales_price'] = $statsData[$i]['month_p_order_price'] + $statsData[$i]['month_p_refund_price_total_sum'];
			$statsData[$i]['month_sales_price'] = $statsData[$i]['month_order_price'] + $statsData[$i]['month_refund_price_total_sum'];

			//-----------원가계산------------//
			// 매입/정산 합계
			$statsData[$i]['month_supply_price'] = $statsData[$i]['month_supply_price_sum'] + $statsData[$i]['month_commission_price_sum'];

			// 취소/반품 합계
			$statsData[$i]['month_refund_supply'] = $statsData[$i]['month_refund_supply_price_sum'] + $statsData[$i]['month_refund_commission_price_sum'];
			// 되돌리기 합계
			$statsData[$i]['month_rollback_supply'] = $statsData[$i]['refund_rollback_supply_price_sum'] + $statsData[$i]['refund_rollback_commission_price_sum'];
			// 본사합계
			$statsData[$i]['month_supply_total'] = $statsData[$i]['month_supply_price_sum'] + $statsData[$i]['month_refund_supply_price_sum'] + $statsData[$i]['refund_rollback_supply_price_sum'];
			// 입점사합계
			$statsData[$i]['month_commission_total'] = $statsData[$i]['month_commission_price_sum'] + $statsData[$i]['month_refund_commission_price_sum'] + $statsData[$i]['refund_rollback_commission_price_sum'];

			// 원가 전체합계
			$statsData[$i]['month_supply_commission_sum'] = $statsData[$i]['month_supply_total'] + $statsData[$i]['month_commission_total'];


			//-----------매출이익 계산------------//
			// 본사 매출이익
			$statsData[$i]['month_m_sales_benefit'] = $statsData[$i]['month_m_sales_price'] + $statsData[$i]['month_supply_total'];
			// 입점사 매출이익
			$statsData[$i]['month_p_sales_benefit'] = $statsData[$i]['month_p_sales_price'] + $statsData[$i]['month_commission_total'];
			// 전체 매출이익
			$statsData[$i]['month_sales_benefit'] = $statsData[$i]['month_sales_price'] + $statsData[$i]['month_supply_commission_sum'];

			$statsData[$i]['month_m_sales_benefit_percent'] = 0;
			$statsData[$i]['month_p_sales_benefit_percent'] = 0;
			$statsData[$i]['month_sales_benefit_percent'] = 0;
			// 본사 매출이익 %
			if($statsData[$i]['month_m_sales_benefit'] > 0 && $statsData[$i]['month_m_sales_price'] > 0){
				$statsData[$i]['month_m_sales_benefit_percent'] = round(($statsData[$i]['month_m_sales_benefit'] / $statsData[$i]['month_m_sales_price']) * 100,2);
			}
			// 입점사 매출이익 %
			if($statsData[$i]['month_p_sales_benefit'] > 0 && $statsData[$i]['month_p_sales_price'] > 0){
				$statsData[$i]['month_p_sales_benefit_percent'] = round(($statsData[$i]['month_p_sales_benefit'] / $statsData[$i]['month_p_sales_price']) * 100,2);
			}
			// 전체 매출이익 %
			if($statsData[$i]['month_sales_benefit'] > 0 && $statsData[$i]['month_sales_price'] > 0){
				$statsData[$i]['month_sales_benefit_percent'] = round(($statsData[$i]['month_sales_benefit'] / $statsData[$i]['month_sales_price']) * 100,2);
			}

			// 할인합계
			$statsData[$i]['discount_price_sum'] = $row['month_enuri_sum']+$row['month_emoney_use_sum']+$row['month_coupon_sale_sum']+$row['month_promotion_code_sale_sum']+$row['month_fblike_sale_sum']+$row['month_mobile_sale_sum']+$row['month_member_sale_sum']+$row['month_referer_sale_sum']+$row['month_event_sale_sum']+$row['month_multi_sale_sum']+$row['month_api_pg_sale_sum'];
			// 환불,롤백할인합계
			$statsData[$i]['refund_discount_price_sum'] = $row['month_refund_enuri_sum']+$row['month_refund_emoney_use_sum']+$row['month_refund_coupon_sale_sum']+$row['month_refund_promotion_code_sale_sum']+$row['month_refund_fblike_sale_sum']+$row['month_refund_mobile_sale_sum']+$row['month_refund_member_sale_sum']+$row['month_refund_referer_sale_sum']+$row['month_refund_event_sale_sum']+$row['month_refund_multi_sale_sum']+$row['month_refund_api_pg_sale_sum'];
			// 할인합계 - 환불,롤백할인합계
			$statsData[$i]['discount_price'] = $statsData[$i]['discount_price_sum']-$statsData[$i]['refund_discount_price_sum'];
		}

		/* 데이터 가공 */
		$maxValue = 0;
		$maxMonth = 12;

		$dataForChart = array();

		for($i=0;$i<$maxMonth;$i++){
			//$dataForChart['매출액'][$i] = ($i+1).'월';
			$dataForChart['매출액'][$i] = $statsData[$i]['month_sales_price']?$statsData[$i]['month_sales_price']:0;
		}

		for($i=0;$i<$maxMonth;$i++){
			//$dataForChart['순이익'][$i] = ($i+1).'월';
			$dataForChart['매출이익'][$i] = $statsData[$i]['month_sales_benefit']?$statsData[$i]['month_sales_benefit']:0;
		}

		foreach($dataForChart as $k=>$v){
			foreach($dataForChart[$k] as $row){
				$maxValue = $maxValue < $row ? $row : $maxValue;
			}
		}

		$this->template->assign(array(
			'dataForChart'	=> $dataForChart,
			'maxValue'		=> $maxValue
		));

		/* 월별 데이터 테이블 */
		$dataForTable = array();
		$dataForTableSum = array();
		for($i=0;$i<$maxMonth;$i++){
			$dataForTable[$i] = $statsData[$i];
		}
		foreach($dataForTable as $stats_month=>$row){
			foreach($row as $k=>$v){
				$dataForTableSum[$k] += $v;
			}
		}

		// 매출이익 퍼센트 재계산 :: 2015-09-12 lwh
		if(!$dataForTableSum['month_m_sales_benefit'] || !$dataForTableSum['month_m_sales_price']){
			$dataForTableSum['month_m_sales_benefit_percent'] = "0";
		}else{
			$dataForTableSum['month_m_sales_benefit_percent'] = round(($dataForTableSum['month_m_sales_benefit'] / $dataForTableSum['month_m_sales_price'])*100,2);
		}
		if(!$dataForTableSum['month_p_sales_benefit'] || !$dataForTableSum['month_m_sales_price']){
			$dataForTableSum['month_p_sales_benefit_percent'] = "0";
		}else{
			$dataForTableSum['month_p_sales_benefit_percent'] = round(($dataForTableSum['month_p_sales_benefit'] / $dataForTableSum['month_p_sales_price'])*100,2);
		}
		if(!$dataForTableSum['month_m_sales_benefit'] || !$dataForTableSum['month_m_sales_price']){
			$dataForTableSum['month_sales_benefit_percent'] = "0";
		}else{
			$dataForTableSum['month_sales_benefit_percent'] = round(($dataForTableSum['month_sales_benefit'] / $dataForTableSum['month_sales_price'])*100,2);
		}

		$this->template->assign(array(
			'npay_use'			=> $npay_use,
			'dataForTable'		=> $dataForTable,
			'dataForTableSum'	=> $dataForTableSum
		));	

	}

	/* 일별 매출 통계 */
	public function sales_daily()
	{
		$this->load->model('accountallmodel');
		// 신규 정산을 이용할 경우 마이그레이션 다음 달부터 적용되도록 처리 :: 2017-06-14 lkh
		$accountAllMiDate = config_load("accountall_setting","accountall_migration_date");
		$accountAllMigrationDate = $accountAllMiDate['accountall_migration_date'];
		$migrationYear = substr($accountAllMigrationDate,0,4);
		$migrationMonth = (substr($accountAllMigrationDate,5,2)+1);
		$checkDate = date("Y-m");
		$migrationCheckDate = $migrationYear."-".sprintf("%02d",$migrationMonth);

		$_GET['year']		= (trim($_GET['year']))		? trim($_GET['year'])	: date('Y');
		$_GET['month']		= (trim($_GET['month']))	? trim($_GET['month'])	: date('m');
		$params['year']		= $_GET['year'];
		$params['month']	= $_GET['month'];
		$params['sitetype']	= ($_GET['sitetype'])		? $_GET['sitetype']		: array();
		$params['provider_seq']	= $this->providerInfo['provider_seq'];
		$statsData			= array();

		list($year, $month) = $this->accountallmodel->get_time_range();
		$this->template->assign(array('year'=>$year,'month'=>$month));

		$params['q_type']	= 'order';
		$query	= $this->accountallmodel->get_sales_sales_daily_stats($params);
		if($query){
			foreach($query->result_array() as $row)
				$statsData[$row['stats_day']-1] = is_array($statsData[$row['stats_day']-1]) ? array_merge($statsData[$row['stats_day']-1],$row) : $row;
		}

		$params['q_type']	= 'refund';
		$query	= $this->accountallmodel->get_sales_sales_daily_stats($params);
		if($query){
			foreach($query->result_array() as $row)
				$statsData[$row['stats_day']-1] = is_array($statsData[$row['stats_day']-1]) ? array_merge($statsData[$row['stats_day']-1],$row) : $row;
		}

		/* 매출액, 매입금액평균, 순이익 계산 */
		foreach($statsData as $i => $row){

			// 매출
			$statsData[$i]['order_price'] = $row['day_settleprice_sum']+$row['day_cash_use_sum'];
			// 할인합계
			$statsData[$i]['discount_price_sum'] = $row['day_enuri_sum']+$row['day_emoney_use_sum']+$row['day_coupon_sale_sum']+$row['day_fblike_sale_sum']+$row['day_mobile_sale_sum']+$row['day_promotion_code_sale_sum']+$row['day_member_sale_sum']+$row['day_referer_sale_sum']+$row['day_event_sale_sum']+$row['day_multi_sale_sum']+$row['day_api_pg_sale_sum'];
			// 환불,롤백할인합계
			$statsData[$i]['refund_discount_price_sum'] = $row['day_refund_enuri_sum']+$row['day_refund_emoney_use_sum']+$row['day_refund_coupon_sale_sum']+$row['day_refund_fblike_sale_sum']+$row['day_refund_mobile_sale_sum']+$row['day_refund_promotion_code_sale_sum']+$row['day_refund_member_sale_sum']+$row['day_refund_referer_sale_sum']+$row['day_refund_event_sale_sum']+$row['day_refund_multi_sale_sum']+$row['day_refund_api_pg_sale_sum'];
			// 할인합계 - 환불,롤백할인합계
			$statsData[$i]['discount_price'] = $statsData[$i]['discount_price_sum']-$statsData[$i]['refund_discount_price_sum'];
			// 매출액
			$statsData[$i]['sales_price'] = $row['day_settleprice_sum']+$row['day_cash_use_sum']-$row['day_refund_price_sum'];
			// 매입원가
			$statsData[$i]['day_supply_price']	= $statsData[$i]['day_supply_price_sum']-$row['day_refund_supply_price_sum'];
			// 순이익
			$statsData[$i]['interests'] = $statsData[$i]['sales_price']-$statsData[$i]['day_supply_price'];

			// 매출이익
			$statsData[$i]['day_sales_benefit'] = $statsData[$i]['order_price']-$statsData[$i]['day_refund_price_sum_total'];

			// 매출이익 %
			$statsData[$i]['day_sales_benefit_percent'] = ($statsData[$i]['day_sales_benefit']>0) ? round(($statsData[$i]['day_sales_benefit'] / $statsData[$i]['order_price']) * 100,2) : 0;
		}

		/* 데이터 가공 */
		$maxValue = 0;
		$maxDay = date('t',strtotime($_GET['year'].'-'.$_GET['month'].'-01'));

		$dataForChart = array();

		for($i=0;$i<$maxDay;$i++){
			//$dataForChart['매출액'][$i] = ($i+1).'월';
			$dataForChart['결제금액'][$i] = $statsData[$i]['order_price']?$statsData[$i]['order_price']:0;
		}

		for($i=0;$i<$maxDay;$i++){
			//$dataForChart['순이익'][$i] = ($i+1).'월';
			$dataForChart['매출액'][$i] = $statsData[$i]['day_sales_benefit']?$statsData[$i]['day_sales_benefit']:0;
		}

		foreach($dataForChart as $k=>$v){
			foreach($dataForChart[$k] as $row){
				$maxValue = $maxValue < $row ? $row : $maxValue;
			}
		}

		$this->template->assign(array(
			'dataForChart'	=> $dataForChart,
			'maxValue'		=> $maxValue,
			'maxDay'		=> $maxDay
		));

		/* 일별 데이터 테이블 */
		$dataForTable = array();
		$dataForTableSum = array();
		for($i=0;$i<$maxDay;$i++){
			$dataForTable[$i] = $statsData[$i];
			foreach($dataForTable[$i] as $k=>$v){
				$dataForTableSum[$k] += $v;
			}
		}

		// 매출이익 퍼센트 재계산 :: 2015-09-12 lwh
		$dataForTableSum['day_sales_benefit_percent'] = ($dataForTableSum['day_sales_benefit']>0) ? round(($dataForTableSum['day_sales_benefit'] / $dataForTableSum['order_price'])*100,2) : 0;

		$this->template->assign(array(
			'dataForTable'	=> $dataForTable,
			'dataForTableSum' => $dataForTableSum,
		));


		/* 일별 데이터 달력용 */
		$c_start_idx = date('w',strtotime("{$params['year']}-{$params['month']}-01"));
		$c_end_idx = date('t',strtotime("{$params['year']}-{$params['month']}-01"));
		$c_row = ceil(($c_start_idx+$c_end_idx)/7);

		$this->template->assign(array(
			'c_start_idx'	=> $c_start_idx,
			'c_end_idx'		=> $c_end_idx,
			'c_row'			=> $c_row,
		));
		$this->template->define(array('sales_daily_calendar'=>$this->skin."/statistic_goods/_sales_daily_calendar.html"));
	}

	/* 시간대별  매출 통계 */
	public function sales_hour(){
		// 정산db 호출
		$this->load->model('accountallmodel');

		$params['year']		= (trim($_GET['year']))		? trim($_GET['year'])	: date('Y');
		$params['month']	= (trim($_GET['month']))	? trim($_GET['month'])	: date('m');
		$params['sitetype']	= ($_GET['sitetype'])		? $_GET['sitetype']		: array();
		$params['provider_seq']	= $this->providerInfo['provider_seq'];
		$statsData			= array();

		list($year, $month) = $this->accountallmodel->get_time_range();
		$this->template->assign(array('year'=>$year,'month'=>$month));

		$query	= $this->accountallmodel->get_goods_sales_hour_stats($params);
		foreach($query->result_array() as $row)
			$statsData[$row['stats_hour']] = $row;

		/* 데이터 가공 */
		$maxDay = date('t',strtotime($_GET['year'].'-'.$_GET['month'].'-01'));
		$count_total_sum = 0;

		$dataForChart = array();
		for($i=0;$i<24;$i++){
			$count_total_sum += $statsData[$i]['month_count_sum'];
			$dataForChart['건수'][$i][0] = $i;
			$dataForChart['건수'][$i][1] = $statsData[$i]['month_count_sum']?$statsData[$i]['month_count_sum']:0;
			$dataForChart['금액'][$i][0] = $i;
			$dataForChart['금액'][$i][1] = $statsData[$i]['month_settleprice_sum']?$statsData[$i]['month_settleprice_sum']:0;
		}

		$maxValue['건수'] = 0;
		$maxValue['금액'] = 0;
		foreach($dataForChart as $k=>$v){
			foreach($dataForChart[$k] as $row){
				$maxValue[$k] = $maxValue[$k] < $row[1] ? $row[1] : $maxValue[$k];
			}
		}

		$this->template->assign(array(
			'dataForChart'	=> $dataForChart,
			'maxValue'		=> $maxValue,
			'maxDay'		=> $maxDay
		));

		/* 테이블 */
		$dataForTable = array();
		for($i=0;$i<24;$i++){
			$dataForTable[$i] = $statsData[$i];
			$dataForTable[$i]['month_count_percent'] = $dataForTable[$i]['month_count_sum']?round($dataForTable[$i]['month_count_sum']/$count_total_sum*100):0;
		}

		$this->template->assign(array(
			'dataForTable'	=> $dataForTable,
			'arr_weekday'	=> $this->arr_weekday
		));
	}

	/*
	 * 월별 통계 정보 수집
	 */
	function ajax_scrap_sales_monthly(){
		// $this->db->trans_start(true);
		// 수집을 위한 변수
		$aParams = array();
		$aParams					= $this->input->get();
		$aParams['provider_seq']	= $this->providerInfo['provider_seq'];

		// 통계 수집
		$this->load->model('accountallmodel');
		$result = $this->accountallmodel->create_scrap_sales_monthly($aParams);

		$aResult = array(
			'result'		=> $result
		);
		echo json_encode($aResult);		

	}
}

/* End of file statistic_promotion.php */
/* Location: ./app/controllers/admin/statistic_promotion.php */