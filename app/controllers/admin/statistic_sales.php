<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class statistic_sales extends admin_base {

	public function __construct() {
		ini_set("memory_limit" , -1);
		parent::__construct();

		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('statsmodel');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('statistic_sales');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->seriesColors = array("#445ebc", "#d33c34","#4bb2c5", "#c5b47f", "#EAA228", "#579575", "#839557", "#958c12",
        "#953579", "#4b5de4", "#d8b83f", "#ff5800", "#0085cc", "#c3b8f3", "#EA28A2", "#8566cc");
		$this->template->assign(array('seriesColors'=>$this->seriesColors));

		// O2O 통계 메뉴 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_statistic_sales_menu();		
		
		/* 매출통계 메뉴 */
		$sales_menu = $this->uri->rsegments[count($this->uri->rsegments)];
		$this->template->assign(array('selected_sales_menu'=>$sales_menu));

		//판매환경
		$this->sitetypeloop = sitetype($_GET['sitetype'], 'image', 'array');
		if(!count($_GET)) $_GET['sitetype'] = array_keys($this->sitetypeloop);

		$this->template->assign(array(
			'service_code' => $this->config_system['service']['code'],
			'sitetype'=>$_GET['sitetype'],
			'sitetypeloop'=>$this->sitetypeloop
		));
	}

	public function index()
	{
		redirect("/admin/statistic_sales/sales_sales");
	}

	public function sales_sales(){

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
				$file_path	= str_replace('sales_sales.html', 'sales_monthly.html', $file_path);
			break;
			case 'daily':
				$this->sales_daily();
				$file_path	= str_replace('sales_sales.html', 'sales_daily.html', $file_path);
			break;
			case 'hour':
				$this->sales_hour();
				$file_path	= str_replace('sales_sales.html', 'sales_hour.html', $file_path);
			break;
		}

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sales_goods(){
		serviceLimit('H_FR','process');

		// 오늘일자 포함 시 오늘일자 데이터 갱신 :: 2014-08-20 lwh
		if($_GET['sdate'] <= date('Y-m-d') && $_GET['edate'] >= date('Y-m-d')){
			$renewal_res = $this->renewal_goods(date('Y-m-d'));
		}

		/* 상단 검색폼 */
		$this->template->define(array('goods_search'=>$this->skin."/statistic_sales/_sales_goods_search.html"));

		if	(!$_GET['sc_type'])	$_GET['sc_type']	= 'goods';
		$file_path		= $this->template_path();
		switch($_GET['sc_type']){
			case 'daily':
				$this->goods_daily();
				$file_path	= str_replace('sales_goods.html', 'goods_daily.html', $file_path);
			break;
			case 'goods':
			default:
				$this->goods_goods();
				$file_path	= str_replace('sales_goods.html', 'goods_goods.html', $file_path);
			break;
		}

		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));		

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 월별  매출 통계 */
	public function sales_monthly()
	{
		$this->load->helper("order");
		$this->load->helper('accountall');
		$this->load->model('accountallmodel');
		$npay_use = npay_useck();
		
		// 신규 정산을 이용할 경우 마이그레이션 다음 달부터 적용되도록 처리 :: 2017-06-14 lkh
		$accountAllMiDate			= getAccountSetting('accountall_migration_date');
		$checkDate = date("Y-m");
		$accountAllMigrationDate	= $accountAllMiDate['migration_date'];
		$migrationYear				= $accountAllMiDate['migrationYear'];
		$migrationMonth				= $accountAllMiDate['migrationMonth'];
		$migrationCheckDate			= $accountAllMiDate['migrationCheckDate'];
		
		list($year, $month) = $this->accountallmodel->get_time_range();
		$this->template->assign(array('year'=>$year,'month'=>$month));

		// 강제수집
		$params['tryScrap']		= (trim($_GET['tryScrap'])) ? trim($_GET['tryScrap'])	: null;
		
		$params['year']		= (trim($_GET['year'])) ? trim($_GET['year'])	: date('Y');

		$params['sitetype']	= ($_GET['sitetype'])	? $_GET['sitetype']		: array();
		$statsData			= array();
		// 오늘일자 포함 시 오늘일자 데이터 갱신 :: 2014-08-20 lwh
		if($params['year'] == date('Y') && $checkDate < $migrationCheckDate){
			$renewal_res = $this->renewal_sales(date('Y-m-d'));
		}

		// 매출 통계에서 본사매출 중 POS에서 발생한 내역은 제외한다.
		$this->load->library("o2o/o2oinitlibrary");
		$this->o2oinitlibrary->init_admin_statistic_sales_monthly_for_params($params);
		
		// 통계 데이터 수집 요청 변수
		$this->accountallmodel->jsonRequestScrapSalesMonthly = array();
		
		$params['q_type']	= 'order';
		$query	= $this->statsmodel->get_sales_sales_monthly_stats($params);
		
		foreach($query->result_array() as $row)
			$statsData[$row['stats_month']-1]	= is_array($statsData[$row['stats_month']-1]) ? array_merge($statsData[$row['stats_month']-1],$row) : $row;

		$params['q_type']	= 'refund';
		$query	= $this->statsmodel->get_sales_sales_monthly_stats($params);
		foreach($query->result_array() as $row)
			$statsData[$row['stats_month']-1]	= is_array($statsData[$row['stats_month']-1]) ? array_merge($statsData[$row['stats_month']-1],$row) : $row;

		$flag_migration = false;	// 정산 마이그레이션 적용 여부

		$commission_data = array();
		if( ($accountAllMigrationDate == "0000-00-00" || ( $checkDate >= $migrationCheckDate && $params['year'] >= $migrationYear) ) && !$_GET['old'] ){
			// 월간 통계-매출의 경우 마이그레이션 달에 따라 구정산 데이터를 포함해야하므로 마이그레이션 일자에 따라 구정산의 통계 이력 초기화
			foreach($statsData as $i => $row){
				$month = sprintf("%02d",$row['stats_month']);
				$checkDateYm  = trim($row['stats_year']."-".$month);
				if($checkDateYm >= $migrationCheckDate){
					unset($statsData[$i]);
				}
			}
			
			//정산금액 : 신정산의 이월/당월 정산금액
			$params['q_type']	= 'commission';
			$commission_result	= $this->accountallmodel->get_sales_sales_monthly_stats($params);
			foreach($commission_result as $_data){
				$ym = $_data['stats_year'].str_pad($_data['stats_month'],2,"0",STR_PAD_LEFT);
				$commission_data[$ym] = $_data;
			}
		
			//매출 : 신정산의 당월 매출 금액
			$params['q_type']	= 'order';
			$result	= $this->accountallmodel->get_sales_sales_monthly_stats($params);
			foreach($result as $row){
				if($migrationYear < $params['year'] || ($migrationYear == $params['year'] && $migrationMonth <= $row['stats_month'])){
					$statsData[$row['stats_month']-1]	= is_array($statsData[$row['stats_month']-1]) ? array_merge($statsData[$row['stats_month']-1],$row) : $row;
				}
			}

			//환불 : 신정산의 당월 환불 금액
			$params['q_type']	= 'refund';
			$result	= $this->accountallmodel->get_sales_sales_monthly_stats($params);
			foreach($result as $row){
				if($migrationYear < $params['year'] || ($migrationYear == $params['year'] && $migrationMonth <= $row['stats_month'])){
					$statsData[$row['stats_month']-1]	= is_array($statsData[$row['stats_month']-1]) ? array_merge($statsData[$row['stats_month']-1],$row) : $row;
				}
			}
			$flag_migration = true;	// 정산 마이그레이션 적용 여부
		}
		
		// O2O 매장 매출 정보 추가
		$this->load->library("o2o/o2oinitlibrary");
		$migration_info = array();
		$migration_info['flag_migration']	= $flag_migration;
		$migration_info['migrationYear']	= $migrationYear;
		$migration_info['migrationMonth']	= $migrationMonth;
		$this->o2oinitlibrary->init_admin_statistic_sales_monthly_for_stats($params, $migration_info, $statsData, $commission_data);
		
		// 통계 데이터 수집 요청 변수 바인드
		$this->template->assign(array(
			'jsonRequestScrapSalesMonthly'	=> str_replace("\"", "\\\"", json_encode($this->accountallmodel->jsonRequestScrapSalesMonthly))
		));
		
		
		/* 매출액, 매입금액평균, 순이익 계산 */
		foreach($statsData as $i => $row){


			$ym = $row['stats_year'].str_pad($row['stats_month'],2,"0",STR_PAD_LEFT);
			if($commission_data[$ym]['month_commission_price_sum'] > 0){
				$statsData[$i]['month_commission_price_sum']			= $commission_data[$ym]['month_commission_price_sum'];
				$statsData[$i]['month_refund_commission_price_sum']		= $commission_data[$ym]['month_refund_commission_price_sum'];
				$statsData[$i]['refund_rollback_commission_price_sum']	= $commission_data[$ym]['refund_rollback_commission_price_sum'];				
			}

			// 주문금액
			$statsData[$i]['month_order_price'] = $row['month_settleprice_sum'];

			// 주문건수
			$statsData[$i]['month_count_sum'] = $row['month_count_sum'];

			// 에누리 가격 가감
			//$statsData[$i]['m_settleprice_sum'] -= ($statsData[$i]['m_enuri_sum']+$statsData[$i]['m_emoney_sum']);
			//$statsData[$i]['p_settleprice_sum'] -= ($statsData[$i]['p_enuri_sum']+$statsData[$i]['p_emoney_sum']);

			// 본사 결제금액 계산 :: 2015-10-06 lwh
			$statsData[$i]['month_m_order_price'] = $statsData[$i]['m_settleprice_sum'] + $statsData[$i]['m_shipping_cost_sum'] + $statsData[$i]['m_goods_shipping_cost_sum'];
			// 입점사 결제금액 계산 :: 2015-10-06 lwh
			$statsData[$i]['month_p_order_price'] = $statsData[$i]['p_settleprice_sum'] + $statsData[$i]['p_shipping_cost_sum'] + $statsData[$i]['p_goods_shipping_cost_sum'];

			//-----------환불데이터 (본사/입점사 나눠있지 않은경우) ---//
			if( ($statsData[$i]['month_m_refund_price_sum'] +
			$statsData[$i]['month_p_refund_price_sum'] +
			$statsData[$i]['month_o_refund_price_sum']) == 0 && $statsData[$i]['month_refund_price_sum'] > 0 ){
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
			
			// O2O 매장 매출 계산 추가
			$this->load->library("o2o/o2oinitlibrary");
			$this->o2oinitlibrary->init_admin_statistic_sales_monthly_for_calculate($statsData[$i]);
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
		
		// O2O 매장 매출 계산 추가
		$this->load->library("o2o/o2oinitlibrary");
		$this->o2oinitlibrary->init_admin_statistic_sales_monthly_for_calculate2($dataForTableSum);

		$this->template->assign(array(
			'npay_use'			=> $npay_use,
			'dataForTable'		=> $dataForTable,
			'dataForTableSum'	=> $dataForTableSum
		));

		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");
		if(!$sc['day']) $sc['day']			= date("d");
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));		
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
		$params['month']	= ((int)$_GET['month']>9)? $_GET['month']:'0'.(int)$_GET['month'];
		$params['sitetype']	= ($_GET['sitetype'])		? $_GET['sitetype']		: array();
		$statsData			= array();
		
		list($year, $month) = $this->accountallmodel->get_time_range();
		$this->template->assign(array('year'=>$year,'month'=>$month));

		// 오늘일자 포함 시 오늘일자 데이터 갱신 :: 2014-08-20 lwh
		if($params['year'].$params['month'] == date('Ym') && $checkDate < $migrationCheckDate){
			$renewal_res = $this->renewal_sales(date('Y-m-d'));
		}

		$params['q_type']	= 'order';
		$query	= $this->statsmodel->get_sales_sales_daily_stats($params);
		foreach($query->result_array() as $row)
			$statsData[$row['stats_day']-1] = is_array($statsData[$row['stats_day']-1]) ? array_merge($statsData[$row['stats_day']-1],$row) : $row;

		$params['q_type']	= 'refund';
		$query	= $this->statsmodel->get_sales_sales_daily_stats($params);
		foreach($query->result_array() as $row)
			$statsData[$row['stats_day']-1] = is_array($statsData[$row['stats_day']-1]) ? array_merge($statsData[$row['stats_day']-1],$row) : $row;

		if( ($accountAllMigrationDate == "0000-00-00" || ( $checkDate >= $migrationCheckDate && $params['year'].$params['month'] >= $migrationYear.sprintf("%02d",$migrationMonth)) ) && !$_GET['old'] ){
			// 구정산의 통계 이력 초기화
			unset($statsData);
			$statsData = array();
			$statsData = $this->accountallmodel->get_stats_data_sales_daily($params, $statsData);
		}
		
		/* 매출액, 매입금액평균, 순이익 계산 */		
		$this->accountallmodel->make_view_sales_daily($statsData);

		/* 데이터 가공 */
		$maxValue = 0;
		$maxDay = date('t',strtotime($_GET['year'].'-'.$_GET['month'].'-01'));

		$dataForChart = array();

		for($i=0;$i<$maxDay;$i++){
			//$dataForChart['매출액'][$i] = ($i+1).'월';
			$dataForChart['매출'][$i] = $statsData[$i]['order_price']?$statsData[$i]['order_price']:0;
		}

		for($i=0;$i<$maxDay;$i++){
			//$dataForChart['순이익'][$i] = ($i+1).'월';
			$dataForChart['매출이익'][$i] = $statsData[$i]['day_sales_benefit']?$statsData[$i]['day_sales_benefit']:0;
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

		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		$this->template->define(array('sales_daily_calendar'=>$this->skin."/statistic_sales/_sales_daily_calendar.html"));
	}

	/* 시간대별  매출 통계 */
	public function sales_hour(){
		$this->load->model('accountallmodel');

		$params['year']		= (trim($_GET['year']))		? trim($_GET['year'])	: date('Y');
		$params['month']	= (trim($_GET['month']))	? trim($_GET['month'])	: date('m');
		$params['sitetype']	= ($_GET['sitetype'])		? $_GET['sitetype']		: array();
		$statsData			= array();

		list($year, $month) = $this->accountallmodel->get_time_range();
		$this->template->assign(array('year'=>$year,'month'=>$month));

		$query	= $this->statsmodel->get_sales_sales_hour_stats($params);
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
			'arr_weekday'	=> $this->arr_weekday,
		));

		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");
		if(!$sc['day']) $sc['day']			= date("d");
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));
	}

	/* 상품 일별 매출 통계 */
	public function goods_daily(){
		$_GET['year']			= (trim($_GET['year']))		? trim($_GET['year'])	: date('Y');
		$_GET['month']			= (trim($_GET['month']))	? str_pad(trim($_GET['month']), 2, '0', STR_PAD_LEFT)	: date('m');
		$params['sort']			= (trim($_GET['sort']))		? trim($_GET['sort'])	: "ord.deposit_date desc";
		$params['sitetype']		= ($_GET['sitetype'])		? $_GET['sitetype']		: array();
		$params['provider_seq']	= (int)$_GET['provider_seq'];
		$params['keyword']		= trim($_GET['keyword']);
		$dateYM					= $_GET['year'].'-'.$_GET['month'];

		$statsData				= array();
		$statsDataSum			= array();
		$search_mode			= 'order';
		if($_GET['keyword'] || $_GET['provider_seq'] > 0)	$search_mode = 'item';
		
		// 시작날짜와 끝 날짜의 달이 다른경우 끝 날짜에 맞춰서 정산테이블 가져옴
		$table_name = $this->statsmodel->get_stat_table($dateYM);
		// 테이블이 없는 경우 초기화면으로
		if($table_name == '') {
			pageRedirect('sales_goods', '데이터가 없습니다.');
			exit;
		}
		$params['table_name'] = $table_name;
		
		$params['q_type']	= 'list';
		$sumquery	= $this->statsmodel->get_sales_goods_daily_stats($params,'sum');
		list($statsDataSum) = $sumquery->result_array();
		
		$query	= $this->statsmodel->get_sales_goods_daily_stats($params);
		foreach($query->result_array() as $row) {
			$statsData[] = $row;
		}

		// 전체 갯수 :: 2014-08-20 lwh
		$cnt_query	= $this->statsmodel->get_sales_goods_daily_stats($params,'cnt');
		$cntData	= $cnt_query->result_array();
		$listCnt	= ($cntData[0]['cnt'])?$cntData[0]['cnt']:0;

		$params['q_type']	= 'order';
		$query	= $this->statsmodel->get_sales_goods_daily_stats($params);
		list($orderData) = $query->result_array();
		
		$params['q_type']	= 'refund';
		$query	= $this->statsmodel->get_sales_goods_daily_stats($params);
		list($refundData) = $query->result_array();

		//환불 금액에서 할인 된 내역을 빼줘야한다 :: 2018-08-08 pjw
		$refundData['refund_sale_price_sum'] = $refundData['event_sale'] + $refundData['multi_sale'] + $refundData['member_sale'] + $refundData['fblike_sale'] + $refundData['mobile_sale'] + $refundData['promotion_code_sale'] + $refundData['referer_sale'] + $refundData['coupon_sale']  + $refundData['refund_emoney_sum'] + $refundData['refund_enuri_sum'];
		
		//환불 합
		$refundData['refund_sum']		= $refundData['refund_price_sum'];
		
		//소계2
		$orderData['sub_price_sum1']	= $orderData['shipping_cost_sum'] + $orderData['return_shipping_cost_sum'] - $orderData['shipping_coupon_sale_sum'] - $orderData['shipping_promotion_code_sale_sum'];
		$orderData['sub_price_sum2']	= $refundData['refund_sum'] - $refundData['refund_sale_price_sum'] + $orderData['emoney_use_sum'] + $orderData['enuri_sum'];
		
		//매출합계
		//배송비합계가 크면 더함
		if( $orderData['sub_price_sum1'] > $orderData['sub_price_sum2'] )		
			$orderData['sub_price_sum']		= ($orderData['sub_price_sum1']-$orderData['sub_price_sum2']);
		else																	
			$orderData['sub_price_sum']		= ($orderData['sub_price_sum2']-$orderData['sub_price_sum1']);

		$orderData['sub_price_sum_txt']	= " - ".number_format($orderData['sub_price_sum']);
		$orderData['sales_sum']			= $statsDataSum['goods_price'] - $orderData['sub_price_sum'] - $statsDataSum['event_sale'] - $statsDataSum['multi_sale'] - $statsDataSum['coupon_sale'] - $statsDataSum['member_sale'] - $statsDataSum['fblike_sale'] - $statsDataSum['mobile_sale'] - $statsDataSum['promotion_code_sale'] - $statsDataSum['referer_sale'];

		$orderData['sales_sum_txt']	= number_format($statsDataSum['goods_price'])."(소계①)";
		$orderData['sales_sum_txt']	.= $orderData['sub_price_sum_txt']."(소계②)";
		$orderData['sales_sum_txt']	.= ($statsDataSum['event_sale'] > 0) ? ' - '.number_format($statsDataSum['event_sale']).'(이벤트)' : '';
		$orderData['sales_sum_txt']	.= ($statsDataSum['multi_sale'] > 0) ? ' - '.number_format($statsDataSum['multi_sale']).'(복수구매)' : '';
		$orderData['sales_sum_txt']	.= ($statsDataSum['coupon_sale'] > 0) ? ' - '.number_format($statsDataSum['coupon_sale']).'(쿠폰)' : '';
		$orderData['sales_sum_txt']	.= ($statsDataSum['member_sale'] > 0) ? ' - '.number_format($statsDataSum['member_sale']).'(등급)' : '';
		$orderData['sales_sum_txt']	.= ($statsDataSum['fblike_sale'] > 0) ? ' - '.number_format($statsDataSum['fblike_sale']).'(좋아요)' : '';
		$orderData['sales_sum_txt']	.= ($statsDataSum['mobile_sale'] > 0) ? ' - '.number_format($statsDataSum['mobile_sale']).'(모바일)' : '';
		$orderData['sales_sum_txt']	.= ($statsDataSum['promotion_code_sale'] > 0) ? ' - '.number_format($statsDataSum['promotion_code_sale']).'(코드)' : '';
		$orderData['sales_sum_txt']	.= ($statsDataSum['referer_sale'] > 0) ? ' - '.number_format($statsDataSum['referer_sale']).'(유입)' : '';

		$this->load->model('providermodel');
		$provider			= $this->providermodel->provider_goods_list();

		$this->template->assign(array(
			'provider'		=> $provider,
			'statsData'		=> $statsData,
			'statsDataSum'	=> $statsDataSum,
			'orderData'		=> $orderData,
			'refundData'	=> $refundData,
			'sc'			=> $_GET,
			'search_mode'	=> $search_mode,
			'listCnt'		=> $listCnt - 1
		));

		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");
		if(!$sc['day']) $sc['day']			= date("d");
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));
	}

	/* 상품 상품별 매출 통계 */
	public function goods_goods(){
		$this->load->model('goodsmodel');
		$cfg_order = config_load('order');
		$statlist	= array();

		if	(!trim($_GET['sdate']) && !trim($_GET['edate'])){
			$_GET['sdate']	= date('Y-m').'-01';
			$_GET['edate']	= date('Y-m-d');
			$_GET['select_date_regist'] = 'thatmonth';
		}

		$params['sdate']		= trim($_GET['sdate']);
		$params['edate']		= trim($_GET['edate']);
		$params['keyword']		= trim($_GET['keyword']);
		$params['provider_seq']	= trim($_GET['provider_seq']);
		$params['category1']	= trim($_GET['category1']);
		$params['category2']	= trim($_GET['category2']);
		$params['category3']	= trim($_GET['category3']);
		$params['category4']	= trim($_GET['category4']);
		$params['brands1']		= trim($_GET['brands1']);
		$params['brands2']		= trim($_GET['brands2']);
		$params['brands3']		= trim($_GET['brands3']);
		$params['brands4']		= trim($_GET['brands4']);
		$params['order_by']		= trim($_GET['order_by']);

		$statSql	= $this->statsmodel->get_sales_goods_stats($params);
		if	($statSql){
			foreach($statSql->result_array() as $k => $data){
				$lk = $k;
				if( $data['package_option_seq1'] && $data['package_option_seq1'] > 0 ){					
					$data_min_package['package_stock'] = null;
					for($package_num = 1;$package_num < 6;$package_num++){
						$option_field = 'package_option'.$package_num;
						$option_seq_field = 'package_option_seq'.$package_num;
						if( $data[$option_seq_field] > 0 && $data[$option_seq_field] ){
							$data_package = $this->goodsmodel->get_package_by_option_seq($data[$option_seq_field]);
							if( $data_min_package['package_stock'] == null || $data_min_package['package_stock'] > $data_package['package_stock'] ) {
								$data_min_package = $data_package;
							}
						}
					}
					$data['stock']			= $data_min_package['package_stock'];
					$data['badstock']		= $data_min_package['package_badstock'];
					$data['reservation15']	= $data_min_package['package_reservation15'];
					$data['reservation25']	= $data_min_package['package_reservation25'];
					$data['tot_stock']		= $data_min_package['package_stock'];
				} 

				$statlist[$k]							= $data;

				$lank++;
				$statlist[$k]['goods_first']		= 'y';
				$statlist[$k]['lank']				= $lank;
				$statlist[$lk]['tstock']			+= $data['stock'];
				$statlist[$lk]['tbadstock']			+= $data['badstock'];
				$statlist[$lk]['treservation15']	+= $data['reservation15'];
				$statlist[$lk]['treservation25']	+= $data['reservation25'];

				if($lank % 2 == 0)
					$statlist[$k]['line_col']		= 1;
			}
		}

		$this->load->model('providermodel');
		$provider			= $this->providermodel->provider_goods_list();

		$this->template->assign(array('sc'=>$_GET));
		$this->template->assign(array('provider'=>$provider));
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign(array('statlist'=>$statlist));
	}

	public function sales_etc(){
		serviceLimit('H_FR','process');

		$this->load->helper('zipcode');

		$_GET['year']		= (trim($_GET['year']))		? trim($_GET['year'])	: date('Y');
		$_GET['month']		= (trim($_GET['month']))	? trim($_GET['month'])	: date('m');
		$params['year']		= $_GET['year'];
		$params['month']	= $_GET['month'];
		$params['sitetype']	= ($_GET['sitetype'])		? $_GET['sitetype']		: array();
		$statsData			= array();
		$this->arr_age		= array('10대 이하','20대','30대','40대','50대','60대 이상');
		$this->arr_sex		= array('남','여');
	    $ZIP_DB				= get_zipcode_db();
		$this->arr_location = array();
		$query = $ZIP_DB->query("SELECT substring(SIDO,1,2) as SIDO FROM `zipcode` GROUP BY SIDO");
		foreach($query->result_array() as $row){
			$this->arr_location[] = $row['SIDO'];
		}

		$params['q_type']	= 'sexage';
		$query	= $this->statsmodel->get_sales_etc_stats($params);
		foreach($query->result_array() as $row) {
			$statsData[$row['buyer_sex']][$row['buyer_age']] = $row;
			${'statsData_'.$row['buyer_sex'].'_'.$row['buyer_age'].'month_settleprice_sum'}	+= $row['month_settleprice_sum'];
			${'statsData_'.$row['buyer_sex'].'_'.$row['buyer_age'].'month_count_sum'}			+= $row['month_count_sum'];
		}

		foreach($this->arr_sex as $sex){
			foreach($this->arr_age as $age){
				$statsData[$sex][$age]['month_settleprice_sum'] = ${'statsData_'.$sex.'_'.$age.'month_settleprice_sum'};
				$statsData[$sex][$age]['month_count_sum'] = ${'statsData_'.$sex.'_'.$age.'month_count_sum'};
			}
		}

		/* 데이터 가공 */
		$maxDay				= date('t',strtotime($_GET['year'].'-'.$_GET['month'].'-01'));
		$count_total_sum	= 0;

		$idx = 0;
		foreach($this->arr_sex as $sex){
			foreach($this->arr_age as $age){
				$count_total_sum	+= $statsData[$sex][$age]['month_count_sum'];
				$idx++;
			}
		}

		/* 일별 데이터 테이블 */
		$count_total_sum;
		$dataForTable1 = array();
		$dataForTableSum = array();
		foreach($this->arr_sex as $sex){
			foreach($this->arr_age as $age){
				$dataForTable1[$sex][$age] = $statsData[$sex][$age];
				$dataForTableSum[$age]['month_count_sum'] += $statsData[$sex][$age]['month_count_sum'];
				$dataForTableSum[$age]['month_settleprice_sum'] += $statsData[$sex][$age]['month_settleprice_sum'];
				$dataForTableSum[$age]['month_count_percent'] = $dataForTableSum[$age]['month_count_sum']?round($dataForTableSum[$age]['month_count_sum']/$count_total_sum*100):0;
			}
		}

		$dataForChart1	= array();
		$idx = 0;
		foreach($this->arr_age as $age){
			$dataForChart1['건수'][$idx][0] = $age;
			$dataForChart1['건수'][$idx][1] = $dataForTableSum[$age]['month_count_sum']?$dataForTableSum[$age]['month_count_sum']:0;
			$dataForChart1['금액'][$idx][0] = $age;
			$dataForChart1['금액'][$idx][1] = $dataForTableSum[$age]['month_settleprice_sum']?$dataForTableSum[$age]['month_settleprice_sum']:0;
			$idx++;
		}

		$params['q_type']	= 'location';
		$query	= $this->statsmodel->get_sales_etc_stats($params);
		foreach($query->result_array() as $row) $statsData[$row['location']] = $row;

		/* 데이터 가공 */
		$maxDay = date('t',strtotime($_GET['year'].'-'.$_GET['month'].'-01'));
		$count_total_sum = 0;

		$dataForChart2 = array();
		$idx = 0;
		foreach($this->arr_location as $v){
			$count_total_sum += $statsData[$v]['month_count_sum'];
			$dataForChart2['건수'][$idx][0] = $v;
			$dataForChart2['건수'][$idx][1] = $statsData[$v]['month_count_sum']?$statsData[$v]['month_count_sum']:0;
			$dataForChart2['금액'][$idx][0] = $v;
			$dataForChart2['금액'][$idx][1] = $statsData[$v]['month_settleprice_sum']?$statsData[$v]['month_settleprice_sum']:0;
			$idx++;
		}

		$maxValue['건수'] = 0;
		$maxValue['금액'] = 0;
		foreach($dataForChart2 as $k=>$v){
			foreach($dataForChart2[$k] as $row){
				$maxValue[$k] = $maxValue[$k] < $row[1] ? $row[1] : $maxValue[$k];
			}
		}

		/* 일별 데이터 테이블 */
		$dataForTable2 = array();
		foreach($this->arr_location as $v){
			$dataForTable2[$v] = $statsData[$v];
			$dataForTable2[$v]['month_count_percent'] = $dataForTable2[$v]['month_count_sum']?round($dataForTable2[$v]['month_count_sum']/$count_total_sum*100):0;
		}

		// 성별/연령
		$this->template->assign(array(
			'dataForChart1'		=> $dataForChart1,
			'maxDay1'			=> $maxDay,
			'dataForTable1'		=> $dataForTable1,
			'dataForTableSum'	=> $dataForTableSum,
			'arr_sex'			=> $this->arr_sex,
			'arr_age'			=> $this->arr_age,
		));


		$this->template->assign(array(
			'dataForChart2'	=> $dataForChart2,
			'maxDay'		=> $maxDay,
			'maxValue'		=> $maxValue,
			'dataForTable2'	=> $dataForTable2,
			'arr_location'	=> $this->arr_location,
		));

		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 결제수단별  통계 */
	public function sales_payment(){
		serviceLimit('H_FR','process');

		$this->arr_payment = config_load('payment');
		if(array_key_exists('eximbay',$this->arr_payment)){
			$this->arr_payment['eximbay_card'] = $this->arr_payment['eximbay'];
			unset($this->arr_payment['eximbay']);
		}
		// 카카오페이 정상적으로 노출안되어 추가 :: 2018-09-12 lkh
		unset($this->arr_payment['kakaopay']);
		$this->arr_payment['daumkakaopay'] = '(구) 카카오페이';
		$this->arr_payment = array_merge($this->arr_payment,array('kakaopay_card'=>'카카오페이'));

		# npay 2.1 사용시
		$this->load->helper("order");
		$npay_use = npay_useck();
		if($npay_use){
			$this->arr_payment = array_merge($this->arr_payment,config_load('npay_payment'));
			//npay_point 추가 18-04-19 gcns jhs
			$this->arr_payment = array_merge($this->arr_payment,array('npay_point'=>'네이버페이 주문형 (포인트)'));
		}
		// 카카오페이구매 사용시
		$talkbuy_use = talkbuy_useck();
		if($talkbuy_use){
			$this->arr_payment = array_merge($this->arr_payment,config_load('talkbuy_payment'));
		}

		/* 날짜 파라미터 */
		$_GET['year']		= (trim($_GET['year']))		? trim($_GET['year'])	: date('Y');
		$_GET['month']		= (trim($_GET['month']))	? trim($_GET['month'])	: date('m');
		$params['year']		= $_GET['year'];
		$params['month']	= $_GET['month'];
		$params['sitetype'] = !empty($_GET['sitetype']) ? $_GET['sitetype'] : array();
		$statsData			= array();

		$query	= $this->statsmodel->get_sales_payment_stats($params);
		foreach($query->result_array() as $row){
			// 카카오페이 정상적으로 노출안되어 수정 :: 2018-09-12 lkh
			// 페이코 노출 수정 20190621 hed
			if(in_array($row['pgs'],['payco','paypal'])){
				$statsData[$row['pgs']] = $row;
			}elseif($row['pgs']){
				$statsData[$row['pgs']."_".$row['payment']] = $row;
			}else{
				// payment 가 동일하면 금액과 건수 합산
				if($statsData[$row['payment']]) {
					$statsData[$row['payment']]['month_settleprice_sum']	+= $row['month_settleprice_sum'];
					$statsData[$row['payment']]['month_count_sum'] 			+= $row['month_count_sum'];
				} else {
					$statsData[$row['payment']] = $row;
				}
			}
		}

		/* 데이터 가공 */
		$maxDay = date('t',strtotime($_GET['year'].'-'.$_GET['month'].'-01'));
		$count_total_sum = 0;

		$dataForChart = array();
		$idx = 0;
		foreach($this->arr_payment as $k=>$v){
			$count_total_sum += $statsData[$k]['month_count_sum'];
			$dataForChart['건수'][$idx][0] = $v;
			$dataForChart['건수'][$idx][1] = $statsData[$k]['month_count_sum']?$statsData[$k]['month_count_sum']:0;
			$dataForChart['금액'][$idx][0] = $v;
			$dataForChart['금액'][$idx][1] = (int)$statsData[$k]['month_settleprice_sum']?(int)$statsData[$k]['month_settleprice_sum']:0;
			$idx++;
		}

		$this->template->assign(array(
			'dataForChart'	=> $dataForChart,
			'maxDay'		=> $maxDay
		));

		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		/* 일별 데이터 테이블 */
		$dataForTable = array();
		foreach($this->arr_payment as $k=>$v){
			$dataForTable[$k] = $statsData[$k];
			$dataForTable[$k]['month_count_percent'] = $dataForTable[$k]['month_count_sum']?round($dataForTable[$k]['month_count_sum']/$count_total_sum*100):0;

			$dataForTable[$k] = $statsData[$k];
			$dataForTable[$k]['month_count_percent'] = $dataForTable[$k]['month_count_sum']?round($dataForTable[$k]['month_count_sum']/$count_total_sum*100):0;
		}

		$this->template->assign(array(
			'dataForTable'	=> $dataForTable,
			'arr_payment'	=> $this->arr_payment,
		));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 판매환경별  매출 통계 */
	public function sales_platform(){
		serviceLimit('H_FR','process');

		/* 날짜 파라미터 */
		$_GET['year']		= (trim($_GET['year']))		? trim($_GET['year'])	: date('Y');
		$_GET['month']		= (trim($_GET['month']))	? trim($_GET['month'])	: date('m');
		if($_GET['month']=='all')	$_GET['month'] = '';
		$params['year']		= $_GET['year'];
		$params['month']	= $_GET['month'];
		$params['sitetype'] = !empty($_GET['sitetype']) ? $_GET['sitetype'] : array();
		$statsData			= array();

		$query	= $this->statsmodel->get_sales_platform_stats($params);
		foreach($query->result_array() as $row) {
			$statsData[$row['sitetype']] = $row;
			$statsData[$row['sitetype']]['sitetype_name'] = $this->sitetypeloop[$row['sitetype']]['name'];
		}

		/* 데이터 가공 */
		$maxDay = date('t',strtotime($_GET['year'].'-'.$_GET['month'].'-01'));
		$count_total_sum = 0;

		$idx = 0;
		foreach($this->sitetypeloop as $sitetype=>$v){
			$count_total_sum += $statsData[$sitetype]['count_sum'];
			$idx++;
		}

		/* 일별 데이터 테이블 */
		$dataForTable = array();
		foreach($this->sitetypeloop as $sitetype=>$v){
			$dataForTable[$sitetype] = $statsData[$sitetype];
			$dataForTable[$sitetype]['count_percent'] = $dataForTable[$sitetype]['count_sum']?round($dataForTable[$sitetype]['count_sum']/$count_total_sum*100):0;
		}

		$dataForChart = array();
		$idx=0;
		foreach($this->sitetypeloop as $sitetype=>$v){
			$dataForChart['금액'][$idx][0] = $this->sitetypeloop[$sitetype]['name'];
			$dataForChart['금액'][$idx][1] = $statsData[$sitetype]['settleprice_sum'] ? floor($statsData[$sitetype]['settleprice_sum']) : 0;
			$dataForChart['건수'][$idx][0] = $this->sitetypeloop[$sitetype]['name'];
			$dataForChart['건수'][$idx][1] = $statsData[$sitetype]['count_sum'] ? $statsData[$sitetype]['count_sum'] : 0;
			$idx++;
		}

		$this->template->assign(array(
			'dataForTable'	=> $dataForTable,
			'dataForChart' => $dataForChart,
		));

		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sales_referer(){
		serviceLimit('H_FR','process');

		$cfg_order		= config_load('order');

		$_GET['dateSel_type']	= !empty($_GET['dateSel_type'])	? $_GET['dateSel_type']	: 'month';
		$_GET['year']			= !empty($_GET['year'])			? $_GET['year']			: date('Y');
		$_GET['month']			= !empty($_GET['month'])		? $_GET['month']		: date('m');
		$params['year']			= $_GET['year'];
		$params['month']		= $_GET['month'];
		$params['dateSel_type']	= $_GET['dateSel_type'];
		$statlist				= array();

		$query		= $this->statsmodel->get_sales_referer_stats($params);
		$statlist	= $query->result_array();
		if	($statlist)	foreach($statlist as $key => $data){
			$stat[$data['referer_name']][$data['date']]	= $data;
		}

		$sitecdArr	= array_keys($stat);
		unset($statlist);
		if	($_GET['dateSel_type'] == 'daily'){
			$end_day	= date('t', strtotime($_GET['year'].'-'.$_GET['month'].'-01'));
			for	($d = 1; $d <= $end_day; $d++){
				$dk	= str_pad($d, 2, "0", STR_PAD_LEFT);
				foreach($sitecdArr as $k => $v){
					$cnt	= ($stat[$v][$dk]['cnt'])	? $stat[$v][$dk]['cnt']		: '0';
					$price	= ($stat[$v][$dk]['price'])	? floor($stat[$v][$dk]['price']/1000)	: '0';
					$statlist[$v]['list'][$d]['referer_name']	= $v;
					$statlist[$v]['list'][$d]['cnt']			= $cnt;
					$statlist[$v]['list'][$d]['price']			= $price;
					$statlist[$v]['total_cnt']					+= $cnt;
					$statlist[$v]['total_price']				+= $price;

					if	($maxCnt < $cnt)		$maxCnt			= $cnt;
					if	($maxPrice < $price)	$maxPrice		= $price;

					$dataForChart['cnt'][$v][]					= array($d.'일', $cnt);
					$dataForChart['price'][$v][]				= array($d.'일', $price);
				}

				$table_title[]	= $d.'일';
			}

		}else{
			for	($m = 1; $m <= 12; $m++){
				$mk	= str_pad($m, 2, "0", STR_PAD_LEFT);
				foreach($sitecdArr as $k => $v){
					$cnt	= ($stat[$v][$mk]['cnt'])	? $stat[$v][$mk]['cnt']		: '0';
					$price	= ($stat[$v][$mk]['price'])	? floor($stat[$v][$mk]['price']/1000)	: '0';
					$statlist[$v]['list'][$m]['referer_name']	= $v;
					$statlist[$v]['list'][$m]['cnt']			= $cnt;
					$statlist[$v]['list'][$m]['price']			= $price;
					$statlist[$v]['total_cnt']					+= $cnt;
					$statlist[$v]['total_price']				+= $price;

					if	($maxCnt < $cnt)		$maxCnt			= $cnt;
					if	($maxPrice < $price)	$maxPrice		= $price;

					$dataForChart['cnt'][$v][]					= array($m.'월', $cnt);
					$dataForChart['price'][$v][]				= array($m.'월', $price);
				}

				$table_title[]	= $m.'월';
			}
		}

		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		$this->template->define(array('referer_cnt_table'=>$this->skin."/statistic_sales/_referer_cnt_table.html"));
		$this->template->define(array('referer_price_table'=>$this->skin."/statistic_sales/_referer_price_table.html"));
		$this->template->assign(array('sc'=>$_GET));
		$this->template->assign(array('table_title'=>$table_title));
		$this->template->assign(array('marketplace'=>$marketplace));
		$this->template->assign(array('maxCnt'=>$maxCnt));
		$this->template->assign(array('maxPrice'=>$maxPrice));
		$this->template->assign(array('dataForChart'=>$dataForChart));
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign(array('marketplace'=>$marketplace));
		$this->template->assign(array('statlist'=>$statlist));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function sales_category(){
		serviceLimit('H_FR','process');

		$_GET['sc_type']		= !empty($_GET['sc_type'])		? $_GET['sc_type']		: 'category';
		$_GET['dateSel_type']	= !empty($_GET['dateSel_type'])	? $_GET['dateSel_type']	: 'month';
		$_GET['year']			= !empty($_GET['year'])			? $_GET['year']			: date('Y');
		$_GET['month']			= !empty($_GET['month'])		? $_GET['month']			: date('m');

		$params['year']			= $_GET['year'];
		$params['month']		= $_GET['month'];
		$params['sc_type']		= $_GET['sc_type'];
		$params['dateSel_type']	= $_GET['dateSel_type'];

		// 오늘일자 포함 시 오늘일자 데이터 갱신 :: 2014-08-20 lwh
		if($params['dateSel_type'] == 'daily'){
			if($params['year'].$params['month'] == date('Yn')){
				$renewal_res = $this->renewal_cate(date('Y-m-d'));
			}
		}else{ // month
			if($params['year'] == date('Y')){
				$renewal_res = $this->renewal_cate(date('Y-m-d'));
			}
		}

		$query		= $this->statsmodel->get_sales_category_stats($params);
		$statlist	= $query->result_array();
		if ($statlist) {
			foreach ($statlist as $key => $data) {
				$stat[$data['category_code']][$data['date']] = $data;
				$category[$data['category_code']] = $data['category_name'];
			}
		}

		$codeArr	= array_keys($stat);
		unset($statlist);
		if	($_GET['dateSel_type'] == 'daily'){
			$end_day	= date('t', strtotime($_GET['year'].'-'.$_GET['month'].'-01'));
			for	($d = 1; $d <= $end_day; $d++){
				$dk	= str_pad($d, 2, "0", STR_PAD_LEFT);
				foreach($codeArr as $k => $v){
					$cnt	= ($stat[$v][$dk]['cnt'])	? $stat[$v][$dk]['cnt']		: '0';
					$price	= ($stat[$v][$dk]['price'])	? floor($stat[$v][$dk]['price']/1000)	: '0';

					$statlist[$v]['list'][$d]['category_code'] = $v;
					$statlist[$v]['list'][$d]['cnt']			= $cnt;
					$statlist[$v]['total_cnt']					+= $cnt;
					if	($maxPrice < $price)	$maxPrice		= $price;
					$dataForChart[$v][]							= array($d.'일', $price);
				}

				$table_title[]	= $d.'일';
			}

		}else{
			for	($m = 1; $m <= 12; $m++){
				$mk	= str_pad($m, 2, "0", STR_PAD_LEFT);
				foreach($codeArr as $k => $v){
					$cnt	= ($stat[$v][$mk]['cnt'])	? $stat[$v][$mk]['cnt']		: '0';
					$price	= ($stat[$v][$mk]['price'])	? floor($stat[$v][$mk]['price']/1000)	: '0';

					$statlist[$v]['list'][$m]['category_code'] = $v;
					$statlist[$v]['list'][$m]['cnt']			= $cnt;
					$statlist[$v]['total_cnt']					+= $cnt;
					if	($maxPrice < $price)	$maxPrice		= $price;
					$dataForChart[$v][]							= array($m.'월', $price);
				}

				$table_title[]	= $m.'월';
			}
		}

		// category_code 이용하여 name 찾아주기
		foreach ($statlist as $code => $data) {
			$category_name = $category[$code];
			$statlist[$code]['category_name'] = $category_name;
		}
		
		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		$this->template->assign(array('sc'=>$_GET));
		$this->template->assign(array('table_title'=>$table_title));
		$this->template->assign(array('maxPrice'=>$maxPrice));
		$this->template->assign(array('dataForChart'=>$dataForChart));
		$this->template->assign(array('cfg_order'=>$cfg_order));
		$this->template->assign(array('statlist'=>$statlist));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	/* 구매통계-상품 상품별 데이터통계 옵션 호출 :: 2014-08-04 lwh */
	public function sales_option_ajax(){

		$params['sdate']		= trim($_POST['sdate']);
		$params['edate']		= trim($_POST['edate']);
		$params['goods_seq']	= trim($_POST['goods_seq']);
		$params['order_by']		= trim($_POST['order_by']);

		$opt_data	= $this->statsmodel->get_sales_option_stats($params);

		echo json_encode($opt_data);
	}

	/* 구매통계-상품 일별 데이터 통계 데이터 갱신하기 :: 2014-08-05 lwh */
	public function renewal_goods($seldate){
		$result['flag'] = false;

		// 데이터 설정
		$sdate = $seldate;
		$edate = $seldate;

		$daily_data	= $this->statsmodel->get_daily_sales_stats($sdate,$edate);

		// 넣을 데이터 삭제
		$this->statsmodel->delete_accumul_stats_sales($sdate,$edate);

		foreach($daily_data as $k => $sel_data){
			$result['flag'] = true;
			$this->statsmodel->set_accumul_stats_sales($sel_data);
		}

		return $result;
	}

	/* 구매통계-상품 일별 데이터 페이징 :: 2014-08-05 lwh */
	// 정산테이블 기준으로 변경 :: 2018-07-30 pjw
	public function goods_daily_pagin(){

		parse_str($_POST['queryString']);

		// queryString을 통해 조합된 문자열을 각 파라미터 변수에 재할당 by hed
		$_GET['year']			= $year;
		$_GET['month']			= $month;
		
		$_GET['year']			= (trim($_GET['year']))		? trim($_GET['year'])	: date('Y');
		$_GET['month']			= (trim($_GET['month']))	? str_pad(trim($_GET['month']), 2, '0', STR_PAD_LEFT)	: date('m');

		$dateYM					= $_GET['year'].'-'.$_GET['month'];
		$params['sort']			= ($sort)	? trim($sort) : "deposit_date desc";
		$params['sitetype']		= ($sitetype)	? $sitetype	: array();
		$params['keyword']		= trim($keyword);
		$statsData				= array();
		$statsDataSum			= array();
		
		// 시작날짜와 끝 날짜의 달이 다른경우 끝 날짜에 맞춰서 정산테이블 가져옴
		$table_name = $this->statsmodel->get_stat_table($dateYM);
		// 테이블이 없는 경우 초기화면으로
		if($table_name == '') {
			openDialogAlert('데이터가 없습니다.', 400, 150, 'parent');
			exit;
		}
		$params['table_name'] = $table_name;

		// 페이징
		$params['start_page']	= ($_POST['npage'] - 1) * $_POST['nnum'] + 1;
		$params['end_page']		= $_POST['nnum'];

		$listData	= $this->statsmodel->get_sales_goods_daily_pagin($params);

		$this->template->assign(array('st_index'=>$params['start_page']));
		$this->template->assign(array('list_loop'=>$listData));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 구매통계-매출 데이터 통계 데이터 갱신하기 :: 2014-08-08 lwh */
	public function renewal_sales($seldate){
		$result['flag1'] = false;
		$result['flag2'] = false;

		// 데이터 설정
		$sdate = $seldate;
		$edate = $seldate;
//$sdate = '2015-01-01';
//$edate = '2015-01-31';
		/* 매출 데이터 갱신 */
		$daily_data	= $this->statsmodel->get_sales_mdstats($sdate,$edate);

		// 넣을 데이터 삭제
		$this->statsmodel->delete_accumul_sales_mdstats($sdate,$edate);

		foreach($daily_data as $k => $sel_data){
			$result['flag1'] = true;
			$this->statsmodel->set_accumul_sales_mdstats($sel_data);
		}

		unset($daily_data);
		unset($sel_data);

		/* 환불 데이터 갱신 */
		$daily_data	= $this->statsmodel->get_sales_refund($sdate,$edate);

		// 넣을 데이터 삭제
		$this->statsmodel->delete_accumul_sales_refund($sdate,$edate);

		foreach($daily_data as $k => $sel_data){
			$result['flag2'] = true;
			$this->statsmodel->set_accumul_sales_refund($sel_data);
		}

		return $result;
	}

	/* 구매통계-카테고리/브랜드 통계 데이터 갱신하기 :: 2014-08-11 lwh */
	public function renewal_cate($seldate){
		$result['flag1'] = false;
		$result['flag2'] = false;

		// 데이터 설정
		$sdate = $seldate;
		$edate = $seldate;

		$daily_data_C	= $this->statsmodel->get_sales_category('C',$sdate,$edate);

		$daily_data_B	= $this->statsmodel->get_sales_category('B',$sdate,$edate);

		// 넣을 데이터 삭제
		$this->statsmodel->delete_accumul_sales_category($sdate,$edate);

		foreach($daily_data_C as $k => $sel_data){
			$sel_data['t_type'] = 'C';
			$result['flag1'] = true;
			$this->statsmodel->set_accumul_sales_category($sel_data);
		}

		foreach($daily_data_B as $k => $sel_data){
			$sel_data['t_type'] = 'B';
			$result['flag2'] = true;
			$this->statsmodel->set_accumul_sales_category($sel_data);
		}

		return $result;
	}

	/* 구매통계-입점사별 매출통계 :: 2014-12-24 lwh */
	public function sales_seller(){
		serviceLimit('H_NAD','process');
		$this->load->model('accountallmodel');

		$this->load->model('providermodel');
		$provider		= $this->providermodel->provider_goods_list();

		
		/**
		* 년/월 추출 시작
		**/
		if(!$_GET['year'] && !$_GET['s_month']){
			$this_year	= date("Y");
			$this_mon	= date("m");
			$_GET['year']			= $this_year;
			$_GET['month']		= $this_mon;
			//$_GET['account_hidden_name'] = 'hidden';
		}else{
			$this_year				= $_GET['year'];
			$this_mon				= $_GET['month'];
		}

		list($year, $month) = $this->accountallmodel->get_time_range();
		$this->template->assign(array('year'=>$year,'month'=>$month));
		
		$this_last_day			= date("t", strtotime($this_year.'-'.$this_mon.'-01'));
		$param['sdate'] = $this_year.'-'.$this_mon.'-'.'01';
		$param['edate'] = $this_year.'-'.$this_mon.'-'.$this_last_day;
		
		$param['sitetype']	= ($_GET['sitetype']) ? $_GET['sitetype'] : array('P','M','F');

		if($_GET['provider_seq']){
			// 입점사 상품별 리스트 데이터 추출
			$param['provider_seq']	= $_GET['provider_seq'];
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

			// 마이그레이션 날짜에맞춰서 통계가 노출되어야하기 때문에 추가 :: 2019-01-21 lkh
			$this->load->helper('accountall');
			$sdate				= $param['sdate'];
			if(!$tb_act_ym) $tb_act_ym	= str_replace("-","",substr($sdate,0,7));
			$accountAllMiDate			= getAccountSetting();
			$accountAllStatsV2			= $accountAllMiDate['accountall_stats_v2'];		// 통계 개선 2019-06-19 by hed

			// ==========================================================================
			// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 시작 by hed 2019-06-19 #34379
			// ==========================================================================
			if($accountAllStatsV2 && date("Ym",$accountAllStatsV2) <= $tb_act_ym){
				$params_stats_v2 = array();
				$params_stats_v2['sdate']				= $param['sdate'];
				$params_stats_v2['edate']				= $param['edate'];
				$params_stats_v2['sitetype']			= $param['sitetype'];
				$params_stats_v2['provider_seq']		= $param['provider_seq'];
				$params_stats_v2['goods_seq']			= $param['goods_seq'];

				$this->load->model('accountallmodel');
				$totalSell['shipping'] = $this->accountallmodel->get_goods_shipping_code_v2($params_stats_v2);
			}
			// ==========================================================================
			// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 종료 by hed 2019-06-19 #34379
			// ==========================================================================

			$toRefund	= $this->statsmodel->refund_stat($param);
			$totalSell['refund_price']			= $toRefund['refund'][0]['refund_price'];
			$totalSell['return_shipping_price']	= $toRefund['return'][0]['return_shipping_price'];
			$totalSell['total_price']			= $totalSell['sell'] + $totalSell['shipping'] - $totalSell['refund_price'];

		}else{
			// 입점사별 리스트 데이터 추출
			$tmpList	= $this->statsmodel->seller_sales_stat($param);

			foreach($tmpList as $data){
				$data['sell_price']	= $data['price_sum'] - $data['sale_sum'] - $data['refund_price'] + $data['shipping_sum'];
				$data['goods_ea'] = count($data['goods_ea']);
				$statList[] = $data;

				// 총합 계산
				$totalSell['price_sum']				+= $data['price_sum'];
				$totalSell['sale_sum']				+= $data['sale_sum'];
				$totalSell['shipping_sum']			+= $data['shipping_sum'];
				$totalSell['refund_price']			+= $data['refund_price'];
				$totalSell['sell_price']			+= $data['sell_price'];
				$totalSell['return_shipping_price']	+= $data['return_shipping_price'];
				$totalSell['total_ea']				+= $data['total_ea'];
				$totalSell['goods_ea']				+= $data['goods_ea'];
			}
		}

		$this->template->assign(array(
			'totalSell'		=> $totalSell,
			'provider'		=> $provider,
			'param'			=> $param,
			'statList'		=> $statList
		));

		//검색
		$sc = $this->input->get();	
		if(!$sc['year']) $sc['year']		= date("Y");
		if(!$sc['month']) $sc['month']		= date("m");
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));		

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/* 구매통계-매장별 매출통계 :: 2018-11-01 hed */
	public function sales_o2o(){
		
		/**
		* 년/월 추출 시작
		**/
		if(!$_GET['year'] && !$_GET['s_month']){
			$this_year	= date("Y");
			$this_mon	= date("m");
			$_GET['year']			= $this_year;
			$_GET['month']		= $this_mon;
			//$_GET['account_hidden_name'] = 'hidden';
		}else{
			$this_year				= $_GET['year'];
			$this_mon				= $_GET['month'];
		}

		$sql = "select regist_date from fm_order order by regist_date limit 1";//tb_act_tmp  fm_order
		$query = $this->db->query($sql);
		$order = $query->result_array();
		if($order[0]['regist_date']){
			$start = substr($order[0]['regist_date'],0,4);
		}else{
			$start = $this_year;
		}

		$cnt = $this_year - $start;
		if($cnt<1){
			$year[] = $start;
		}else{
			for($i=date("Y");$i>=$start;$i--){
				$year[] = $i;
			}
		}
		for($i=12;$i>0;$i--){
			$temp = strlen($i)>1 ? $i : "0".$i;
			$month[] = $temp;
		}
		$this->template->assign(array('year'=>$year,'month'=>$month));
		
		$this_last_day			= date("t", strtotime($this_year.'-'.$this_mon.'-01'));
		$params['sdate'] = $this_year.'-'.$this_mon.'-'.'01';
		$params['edate'] = $this_year.'-'.$this_mon.'-'.$this_last_day;
		$params['o2o_store_seq']	= $_GET['o2o_store_seq'];

		//검색
		$sc = $this->input->get();		
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_statistic_sales_page($params);
		
	}
	
	/*
	 * 월별 통계 정보 수집
	 */
	function ajax_scrap_sales_monthly(){
		// $this->db->trans_start(true);
		// 수집을 위한 변수
		$aParams = array();
		$aParams					= $this->input->get();
		
		// 통계 수집
		$this->load->model('accountallmodel');
		$result = $this->accountallmodel->create_scrap_sales_monthly($aParams);
		
		$aResult = array(
			'result'		=> $result
		);
		echo json_encode($aResult);
	}
}

/* End of file statistic_sales.php */
/* Location: ./app/controllers/admin/statistic_sales.php */
