<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class accountall extends selleradmin_base {

	public function __construct() {
		ini_set('memory_limit','-1');
		parent::__construct();
		$this->arr_step = config_load('step');
		$this->arr_payment = config_load('payment');
		$this->load->model('accountmodel');
		$this->load->model('providermodel');

		$this->load->helper('accountall');
		$this->load->model('accountallmodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('openmarketmodel');
		$this->load->model('ordermodel');
		$this->load->model('exportmodel');
		$this->load->model('membermodel');
		$this->load->helper('order');
		$this->cfg_order = config_load('order');
		$this->account_table_finish = config_load('account_table_finish','',true);//정산확정버튼추가 @2018-03-15  [201708_date]

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('account_view');

		if (!$auth) {
			pageBack($this->auth_msg);
			exit;
		}
		
	}

	public function index()
	{
		redirect("/selleradmin/accountall/accountallviewerall".($_SERVER['QUERY_STRING']?"?".$_SERVER['QUERY_STRING']:''));
	}

	public function accountAllExcelHeader(){
	
		ini_set("memory_limit",-1);
		//xls출력용 추가
		header( "Content-type: application/vnd.ms-excel;charset=UTF-8");
		header( "Expires: 0" );
		header( "Cache-Control: must-revalidate, post-check=0,pre-check=0" );
		header( "Pragma: public" );
		$filename = (in_array('all', $_GET['order_referer']))? 'all':implode($_GET['order_referer'],'_');
		$filename .= "_".$_GET['s_year'].$_GET['s_month'];
		header( "Content-Disposition: attachment; filename=".$filename."_account_list.xls" );
	}

	//정산리스트
	public function accountallviewerall() {

		if( $_GET['accountall_excel'] ) {
			if( !$_GET['testex'] ) {
				$this->accountAllExcelHeader();
			}
		}else{
			$this->admin_menu();
			$this->tempate_modules();
		}
		// 입점사고유번호 처리 :: 2018-05-02 lkh
		$_GET['provider_seq']	= $this->providerInfo['provider_seq'];
		$order_referer			= ($_GET['order_referer'])?trim($_GET['order_referer']):'all';
		$_GET['date_field']		= ($_GET['date_field'])?trim($_GET['date_field']):'deposit_date';
		$order_referer_pg		= account_order_referer_title($order_referer);
		$search_seq				= trim($_GET['search_seq']);

		//입점사 정산주기
		$nowPeriodArr	= $this->accountallmodel->get_account_provider_period('pre',$_GET['provider_seq']);
		if($nowPeriodArr['accountall_period_count']){
			$providernew['calcu_count'] = $nowPeriodArr['accountall_period_count'];
		}else{
			$providernew['calcu_count'] = "0";
		}
		$this->template->assign('provider', $providernew);

		if(!$_GET['s_year']) $_GET['s_year']		= date("Y");
		if(!$_GET['s_month']) $_GET['s_month']		= date("m");

		//검색기간 년/월 selectbox 추출
		list($year,$month) = $this->accountallmodel->get_search_year_month();

		$this->template->assign(array('year'=>$year,'month'=>$month));
		$_GET['acc_table'] = trim($_GET['s_year'].$_GET['s_month']);

		$_PARAM						= $_GET;

		/*
		* 신규 정산을 이용할 경우 마이그레이션 다음 달부터 적용되도록 처리 :: 2017-06-14 lkh
		* 마이그레이션 날짜 가져오기 시작
		*/
		$checkDate = $_GET['s_year']."-".$_GET['s_month'];
		$_accountSettings			= getAccountSetting();
		$accountallPatchV1			= $_accountSettings['accountall_patch_v1'];		// account patch v1 : 신정산 수정 v1 패치일자 20190430 개선 pjm
		$accountAllViewV1			= $_accountSettings['accountall_view_v1'];		// account view v1 : 신정사 수정 v1 view 기준일자 20190430 개선 pjm
		$migrationYear				= $_accountSettings['migrationYear'];
		$migrationMonth				= $_accountSettings['migrationMonth'];
		$migrationCheckDate			= $_accountSettings['migrationCheckDate'];
		$accountAllMigrationDate	= $_accountSettings['migration_date'];
		/*
		* 마이그레이션 날짜 가져오기 끝
		*/

		/*
		* 정산주기 설정 시작
		*/
		$accountAllDate					= getAccountAllDate($_PARAM);
		/*
		* 정산주기 설정 끝
		*/
		$carry_page = ($this->input->get('carry_page'))?$this->input->get('carry_page'):'1';
		$current_page = ($this->input->get('current_page'))?$this->input->get('current_page'):'1';
		$carryover_out_num = ($this->input->get('carry_last_num'))?$this->input->get('carry_last_num'):'0';
		$out_num = ($this->input->get('current_last_num'))?$this->input->get('current_last_num'):'0';
		
		$this->template->assign("carry_page", $carry_page);
		$this->template->assign("current_page", $current_page);

		// 전체 조회	: 엑셀과 합계를 구하기 위해서는 전체를 조회해야함
		$_PARAM['total_view']		= '1';
		if($this->input->get('pagemode') && $this->input->get('targetmode')){
			$_PARAM['perpage'] = ($this->input->get('perpage'))?$this->input->get('perpage'):$this->accountallmodel->perpage; // 한 페이지당 노출 수
			$_PARAM['total_view'] = '0';
			$_PARAM['carry_page'] = $carry_page;
			$_PARAM['current_page'] = $current_page;
		}

		$sc = $this->input->get();
		if(!isset($sc['order_referer'])){
			$sc['order_referer'][] = 'all';
		}
		$this->template->assign(array('sc'=>$sc,'scObj'=>json_encode($sc)));

		/*
		* 정산 실행 내역 시작
		*/
		$accountallConfirmSetting = array();
		$accountConfirm = $this->accountallmodel->get_account_confirm(trim($_GET['s_year']."-".$_GET['s_month']));

		//정산금액 절삭기준 통일로 view버전에 따라 보여주기(이미 정산완료된 금액은 변경되면 안됨)
		$listview = "list";

		//정산 마감전
		if(!$accountConfirm){
			$settingArr						= array("year"=>$_GET['s_year'], "month"=>$_GET['s_month']);
			$accountallConfirmSetting		= $this->accountallmodel->get_account_setting("month",$settingArr);
			$yearmonth						= $settingArr['year']."-".$settingArr['month'];
			$accountallConfirmSetting['confirm_date'] = date("Y-m", strtotime($yearmonth."+1 month"))."-".sprintf("%02d",$accountallConfirmSetting['accountall_confirm']);
			$accountallConfirmSetting['confirm_name'] = getConfirmDay($accountallConfirmSetting['accountall_confirm']);
			if($accountallPatchV1 && $accountAllViewV1){
				if(date("Ymd",$accountallPatchV1) <= date("Ymd") && date("Y-m",$accountAllViewV1) <= $checkDate){
					$listview = "list2";
				}
			}

		//정산 마감후
		}else{
			$accountConfirm['confirm_name'] = getConfirmDay($accountConfirm['confirm_day']);
			if($accountallPatchV1 && $accountAllViewV1){
				if(date("Ymd",$accountallPatchV1) <= date("Ymd") && date("Y-m",$accountAllViewV1) <= $checkDate && date("Y-m-d",$accountAllViewV1) < substr($accountConfirm['confirm_end_date'],0,10)){
					$listview = "list2";
				}
			}
		}

		$loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
		if( ($accountAllMigrationDate == "0000-00-00" || ( $checkDate >= $migrationCheckDate && $_PARAM['s_year'] >= $migrationYear) ) && !$_GET['old'] ){
			$query_result = $this->accountallmodel->get_account_all_catalog_query($_PARAM);
		}
		
		$mode_accountalllist = 'sum';
		if(($this->input->get('pagemode') && $this->input->get('targetmode'))){
			$mode_accountalllist = 'all';
		}elseif($_PARAM['accountall_excel']){
			$mode_accountalllist = 'excel';
		}

		$carryovertot['carryoverloopcnt'] = 0;
		$tot['loopcnt'] = 0;
		$caller = 'seller';
		if($_PARAM['accountall_excel']){
			draw_excel_accountall($caller, 'destory', 'carryoverloop');
			draw_excel_accountall($caller, 'destory', 'loop');
		}

		$accountAllCount = array();
		$accountAllCount['account1'][0] = 0;
		$accountAllCount['account2'][0] = 0;
		$accountAllCount['account2'][1] = 0;
		$accountAllCount['account4'][0] = 0;
		$accountAllCount['account4'][1] = 0;
		$accountAllCount['account4'][2] = 0;
		$accountAllCount['account4'][3] = 0;

		$accountAllCount['account1_sum_commission_price'][0]	= 0;
		$accountAllCount['account1_sum_feeprice'][0]			= 0;
		$accountAllCount['account2_sum_commission_price'][0]	= 0;
		$accountAllCount['account2_sum_feeprice'][0]			= 0;
		$accountAllCount['account2_sum_commission_price'][1]	= 0;
		$accountAllCount['account2_sum_feeprice'][1]			= 0;
		$accountAllCount['account4_sum_commission_price'][0]	= 0;
		$accountAllCount['account4_sum_feeprice'][0]			= 0;
		$accountAllCount['account4_sum_commission_price'][1]	= 0;
		$accountAllCount['account4_sum_feeprice'][1]			= 0;
		$accountAllCount['account4_sum_commission_price'][2]	= 0;
		$accountAllCount['account4_sum_feeprice'][2]			= 0;
		$accountAllCount['account4_sum_commission_price'][3]	= 0;
		$accountAllCount['account4_sum_feeprice'][3]			= 0;

		$extend = array(
			'caller' => $caller,
			'get_order_referer' => $_PARAM['order_referer'],
			'mode_accountalllist' => $mode_accountalllist,
			'get_provider_seq' => $_PARAM['provider_seq'],
			'accountAllCount' => $accountAllCount,
			'accountAllDate' => $accountAllDate,
		);
		
		foreach($query_result as $query_list){
			while ($acinsdata = mysqli_fetch_array($query_list)){
				$acinsdata_order_seq_ar[$acinsdata['ac_type']][$acinsdata['status']][] = $acinsdata['order_seq'];
				switch($acinsdata['status']){//전월/당월/차월 구분
					case "carryover"://통합정산데이타의 전월
					case "not-carryover":
						$carryover_out_num++;
						$acinsdata['out_num'] = $carryover_out_num;
					break;
					case "overdraw"://정산데이타의 차월
						if($acinsdata['ac_type'] == 'cal') {
							$overdraw_out_num++;
							$acinsdata['out_num']=$overdraw_out_num;
						}else{
							$out_num++;
							$acinsdata['out_num']=$out_num;
						}
					break;
					default://매출+정산 당월
						$out_num++;
						$acinsdata['out_num']=$out_num;
					break;
				}
				accountalllist($listview, $acinsdata, $loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot, $extend);
				if( $_PARAM['account_hidden_sales'] ) unset($overdrawloop,$overdrawtot);
				if( $_PARAM['account_hidden_cal'] ) unset($carryoverloop,$carryovertot);
			}
		}

		if($_PARAM['provider_seq'])
			$accountAllCount = $extend['accountAllCount'];

		if($tot){
			$tot['out_ac_fee_rate'] = 0;
			$tot['out_ac_profit_rate'] = 0;
			// 수수료율 계산
			$tot_out_sales_unit_feeprice_tmp	= $tot['out_sales_unit_feeprice'] - $tot['refund_sales_unit_feeprice'];
			$tot_out_total_ac_price_tmp			= $tot['out_total_ac_price'] - $tot['refund_out_total_ac_price'];
			if($tot_out_sales_unit_feeprice_tmp && $tot_out_total_ac_price_tmp){
				$tot['out_ac_fee_rate']				= round((($tot_out_sales_unit_feeprice_tmp/$tot_out_total_ac_price_tmp)*100),2);				//수수료율
			}
			// 이익율 계산
			$tot_out_ac_profit_price_tmp		= $tot['out_ac_profit_price'] - $tot['refund_out_ac_profit_price'];
			$tot_out_pg_default_price_tmp		= $tot['out_pg_default_price'] - $tot['refund_out_pg_default_price'];
			if($tot_out_ac_profit_price_tmp && $tot_out_pg_default_price_tmp){
				$tot['out_ac_profit_rate']			= round(($tot_out_ac_profit_price_tmp/$tot_out_pg_default_price_tmp*100),1);	//이익율
				$tot['out_ac_profit_rate']			= sprintf("%.1f",$tot['out_ac_profit_rate']);
			}
		}

		if($carryovertot){
			$carryovertot['out_ac_fee_rate'] = 0;
			$carryovertot['out_ac_profit_rate'] = 0;
			// 수수료율 계산
			$carryovertot_out_sales_unit_feeprice_tmp	= $carryovertot['out_sales_unit_feeprice'] - $carryovertot['refund_sales_unit_feeprice'];
			$carryovertot_out_total_ac_price_tmp		= $carryovertot['out_total_ac_price'] - $carryovertot['refund_out_total_ac_price'];
			if($carryovertot_out_sales_unit_feeprice_tmp && $carryovertot_out_total_ac_price_tmp){
				$carryovertot['out_ac_fee_rate']			= round((($carryovertot_out_sales_unit_feeprice_tmp/$carryovertot_out_total_ac_price_tmp)*100),2);				//수수료율
			}
			// 이익율 계산
			$carryovertot_out_ac_profit_price_tmp		= $carryovertot['out_ac_profit_price'] - $carryovertot['refund_out_ac_profit_price'];
			$carryovertot_out_pg_default_price_tmp		= $carryovertot['out_pg_default_price'] - $carryovertot['refund_out_pg_default_price'];
			if($carryovertot_out_ac_profit_price_tmp && $carryovertot_out_pg_default_price_tmp){
				$carryovertot['out_ac_profit_rate']			= round(($carryovertot_out_ac_profit_price_tmp/$carryovertot_out_pg_default_price_tmp*100),1);	//이익율
				$carryovertot['out_ac_profit_rate']			= sprintf("%.1f",$carryovertot['out_ac_profit_rate']);
			}
		}

		if($overdrawtot){
			$overdrawtot['out_ac_fee_rate'] = 0;
			$overdrawtot['out_ac_profit_rate'] = 0;
			// 수수료율 계산
			$overdrawtot_out_sales_unit_feeprice_tmp	= $overdrawtot['out_sales_unit_feeprice'] - $overdrawtot['refund_sales_unit_feeprice'];
			$overdrawtot_out_total_ac_price_tmp		= $overdrawtot['out_total_ac_price'] - $overdrawtot['refund_out_total_ac_price'];
			if($overdrawtot_out_sales_unit_feeprice_tmp && $overdrawtot_out_total_ac_price_tmp){
				$overdrawtot['out_ac_fee_rate']				= round((($overdrawtot_out_sales_unit_feeprice_tmp/$overdrawtot_out_total_ac_price_tmp)*100),2);				//수수료율
			}
			// 이익율 계산
			$overdrawtot_out_ac_profit_price_tmp		= $overdrawtot['out_ac_profit_price'] - $overdrawtot['refund_out_ac_profit_price'];
			$overdrawtot_out_pg_default_price_tmp		= $overdrawtot['out_pg_default_price'] - $overdrawtot['refund_out_pg_default_price'];
			if($overdrawtot_out_ac_profit_price_tmp && $overdrawtot_out_pg_default_price_tmp){
				$overdrawtot['out_ac_profit_rate']			= round(($overdrawtot_out_ac_profit_price_tmp/$overdrawtot_out_pg_default_price_tmp*100),1);	//이익율
				$overdrawtot['out_ac_profit_rate']			= sprintf("%.1f",$overdrawtot['out_ac_profit_rate']);
			}
		}

		//당월 매출/정산소계 영역
		$alltot = array();
		//통합매출 합계
		$alltot['sales_out_ea']				= ($tot['out_ea']+$overdrawtot['out_ea'])
													- ($tot['refund_out_ea']+$overdrawtot['refund_out_ea']);

		$alltot['sales_out_price']				= ($tot['out_price']+$overdrawtot['out_price'])
													- ($tot['refund_out_price']+$overdrawtot['refund_out_price']);
		$alltot['sales_out_salescost_admin']	= ($tot['out_salescost_admin']+$overdrawtot['out_salescost_admin'])-
														($tot['refund_salescost_admin']+$overdrawtot['refund_salescost_admin']);
		$alltot['sales_out_salescost_provider']	= ($tot['out_salescost_provider']+$overdrawtot['out_salescost_provider'])-
														($tot['refund_salescost_provider']+$overdrawtot['refund_salescost_provider']);
		$alltot['sales_out_salescost_total']	=  ($tot['salescost_total']+$overdrawtot['salescost_total'])	-
													($tot['refund_salescost_total']+$overdrawtot['refund_salescost_total']);
		$alltot['sales_out_pg_sale_price']		= ($tot['pg_sale_price']+$overdrawtot['pg_sale_price'])
													- ($tot['refund_pg_sale_price']+$overdrawtot['refund_pg_sale_price']);
		$alltot['sales_out_cash_use']			= ($tot['out_cash_use']+$overdrawtot['out_cash_use'])
													- ($tot['refund_out_cash_use']+$overdrawtot['refund_out_cash_use']);
		$alltot['sales_out_pg_support_price']	= ($tot['pg_support_price']+$overdrawtot['pg_support_price'])
													- ($tot['refund_pg_support_price']+$overdrawtot['refund_pg_support_price']);
		$alltot['sales_out_sale_price']			= ($tot['out_sale_price']+$overdrawtot['out_sale_price'])
													- ($tot['refund_out_sale_price']+$overdrawtot['refund_out_sale_price']);

		//통합정산 합계
		$alltot['ac_out_exp_ea']				= ($tot['out_exp_ea']+$carryovertot['out_exp_ea'])
													- ($tot['refund_out_exp_ea']+$carryovertot['refund_out_exp_ea']);
		$alltot['ac_out_total_ac_price']		= ($tot['out_total_ac_price']+$carryovertot['out_total_ac_price'])
													- ($tot['refund_out_total_ac_price']+$carryovertot['refund_out_total_ac_price']);
		$alltot['ac_out_pg_default_price']		= ($tot['out_pg_default_price']+$carryovertot['out_pg_default_price'])
													- ($tot['refund_out_pg_default_price']+$carryovertot['refund_out_pg_default_price']);
		$alltot['ac_out_salescost_total']		= ($tot['out_ac_salescost_total']+$carryovertot['out_ac_salescost_total'])
													- ($tot['refund_out_ac_salescost_total']+$carryovertot['refund_out_ac_salescost_total']);
		$alltot['ac_out_ac_cash_use']			= ($tot['out_ac_cash_use']+$carryovertot['out_ac_cash_use'])
													- ($tot['refund_out_ac_cash_use']+$carryovertot['refund_out_ac_cash_use']);
		$alltot['ac_out_ac_pg_price']				= ($tot['out_ac_pg_price']+$carryovertot['out_ac_pg_price'])
													- ($tot['refund_out_ac_pg_price']+$carryovertot['refund_out_ac_pg_price']);
		$alltot['ac_out_pg_add_price']			= ($tot['out_pg_add_price']+$carryovertot['out_pg_add_price'])
													- ($tot['refund_out_pg_add_price']+$carryovertot['refund_out_pg_add_price']);
		$alltot['ac_out_sales_unit_feeprice']	= ($tot['out_sales_unit_feeprice']+$carryovertot['out_sales_unit_feeprice'])
													- ($tot['refund_sales_unit_feeprice']+$carryovertot['refund_sales_unit_feeprice']);
		$alltot['ac_out_commission_price']		= ($tot['out_commission_price']+$carryovertot['out_commission_price'])
													- ($tot['refund_out_commission_price']+$carryovertot['refund_out_commission_price']);
		$alltot['ac_out_salescost_admin']		= ($tot['out_ac_salescost_admin']+$carryovertot['out_ac_salescost_admin'])
													- ($tot['refund_ac_salescost_admin']+$carryovertot['refund_ac_salescost_admin']);
		$alltot['ac_out_salescost_provider']		= ($tot['out_ac_salescost_provider']+$carryovertot['out_ac_salescost_provider'])
													- ($tot['refund_ac_salescost_provider']+$carryovertot['refund_ac_salescost_provider']);
		// 공급금액
		$alltot['ac_out_consumer_real_price']		= ($tot['out_ac_consumer_real_price']+$carryovertot['out_ac_consumer_real_price'])
													- ($tot['refund_out_ac_consumer_real_price']+$carryovertot['refund_out_ac_consumer_real_price']);
		// 이익
		$alltot['ac_out_profit_price']			= ($tot['out_ac_profit_price']+$carryovertot['out_ac_profit_price'])
													- ($tot['refund_out_ac_profit_price']+$carryovertot['refund_out_ac_profit_price']);
		// 수수료율 계산
		if($alltot['ac_out_sales_unit_feeprice'] && $alltot['ac_out_total_ac_price'])
			$alltot['ac_out_fee_rate']				= round($alltot['ac_out_sales_unit_feeprice']/$alltot['ac_out_total_ac_price']*100,1);				//수수료율
		// 이익율 계산
		if($alltot['ac_out_profit_price'] && $alltot['ac_out_pg_default_price'])
		$alltot['ac_out_profit_rate']			= round(($alltot['ac_out_profit_price']/$alltot['ac_out_pg_default_price']*100),1);	//이익율

		$loopcnt = count($loop);
		$carryoverloopcnt = count($carryoverloop);
		if($mode_accountalllist == 'sum'){
			$loopcnt = $tot['loopcnt'];
			$carryoverloopcnt = $carryovertot['carryoverloopcnt'];
		}

		/****/
		$this->template->assign(array(
			'alltot'			=> $alltot,					//당월 매출/정산 전체소계
			'tot'				=> $tot,					//당월소계
			'loop'				=> $loop,					//당월리스트
			'loopcnt'			=> $loopcnt,			//당월건수
			'carryovertot'		=> $carryovertot,			//전월소계
			'carryoverloop'		=> $carryoverloop,			//전월리스트
			'carryoverloopcnt'	=> $carryoverloopcnt,	//전월건수
			'overdrawtot'		=> $overdrawtot,			//차월소계
			'overdrawloop'		=> $overdrawloop,			//차월리스트
			'overdrawloopcnt'	=> count($overdrawloop),	//차월건수
			'accountAllCount'	=> $accountAllCount,
			'accountConfirm'	=> $accountConfirm,			//정산실행마감일
			'accountallConfirmSetting'	=> $accountallConfirmSetting, //정산마감예정일
			'provider_viewer'		=> $this->providerInfo,
			
			'perpage'			=> $_PARAM['perpage'], // 페이지당 노출 수
			'carryover_out_num'	=> $carryover_out_num, //이월 순번
			'current_out_num'	=> $out_num, //당월 순번
		));

		//$overdrawloopview = ( $_GET['acc_table'] < date('Ym') )?true:false;//차월영역은 현재달이 아닐때만 노출되도록
		$overdrawloopview = false;//차월영역은 노출되지 않도록

		$tableheight = ( $loopcnt || $carryoverloopcnt || count($overdrawloop) )?'350':'300';
		$tableheight_carry = ( $carryoverloopcnt )?'250':'80';
		$tableheight_current = ( $loopcnt )?'250':'90';

		//타이틀 기본/매출/정산 숨김
		$totalcolspan = 28;
		if($_GET['account_hidden_name'])	$totalcolspan -= 3;
		if($_GET['account_hidden_sales'])	$totalcolspan -= 8;
		if($_GET['account_hidden_cal'])		$totalcolspan -= 9;
		$this->template->assign(array('account_fee_ar_pg'=>$this->accountallmodel->account_fee_ar['pg'],'account_fee_ar_goods'=>$this->accountallmodel->account_fee_ar['goods']));
		$file_path	= $this->template_path();
		$file_pat_change = ( $_GET['accountall_excel'] )?"accountall_excel.html":"accountallviewer.html";
		
		// 페이지 모드 
		if($this->input->get('pagemode') && $this->input->get('targetmode')){
			$page_file_path = 'accountallviewer_'.$this->input->get('pagemode').'_'.$this->input->get('targetmode').'_ajax.html';
			$file_pat_change = str_replace("accountallviewer.html",$page_file_path,$file_pat_change);
		}
		
		$file_path = str_replace("accountallviewerall.html",$file_pat_change,$file_path);
		$this->template->assign(array('cfg_order'	=> $this->cfg_order,'account_table_finish'	=> $this->accountallmodel->account_table_finish[$_GET['acc_table']],'account_table_finish_date'	=> $this->accountallmodel->account_table_finish[$_GET['acc_table'].'_date'],'config_system'	=> $this->config_system,'order_referer_pg'	=> $order_referer_pg,'tableheight'	=> $tableheight,'tableheight_carry'	=> $tableheight_carry,'tableheight_current'	=> $tableheight_current,"overdrawloopview"=>$overdrawloopview,'totalcolspan'	=> $totalcolspan));
		$this->template->define(array('tpl'	=>$file_path));
		
		if($_GET['accountall_excel']){
			draw_excel_accountall($caller, 'create', 'header');
			draw_excel_accountall($caller, 'create', 'footer');

			draw_excel_accountall($caller, 'destory', 'carryovertot');
			draw_excel_accountall($caller, 'destory', 'tot');

			draw_excel_accountall($caller, 'create', 'carryovertot', array('carryovertot'=>$carryovertot), 0, $loopcnt);
			draw_excel_accountall($caller, 'create', 'tot', array('tot'=>$tot,'alltot'=>$alltot), 0, $carryoverloopcnt);

			draw_excel_accountall($caller, 'read', 'header');
			draw_excel_accountall($caller, 'read', 'carryoverloop');
			draw_excel_accountall($caller, 'read', 'carryovertot');
			draw_excel_accountall($caller, 'read', 'loop');
			draw_excel_accountall($caller, 'read', 'tot');
			draw_excel_accountall($caller, 'read', 'footer');
		}else{
			$this->template->print_("tpl");
		}
	}

	/**
	* 정산리스트 - 업체별 월별정산통계화면
	* @
	**/
	public function accountgroup(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		
		$provider			= $this->providermodel->provider_goods_list_sort();
		$this->template->assign('provider',$provider);

		if(!$_GET['s_year']) $_GET['s_year']		= date("Y");
		if(!$_GET['s_month']) $_GET['s_month']		= date("m");

		//검색기간 년/월 selectbox 추출
		list($year,$month) = $this->accountallmodel->get_search_year_month();

		$this->template->assign(array('year'=>$year,'month'=>$month));
		$_GET['acc_table'] = trim($_GET['s_year'].$_GET['s_month']);

		$_PARAM			= $_GET;
		$_PARAM['confirm_date']			= $confirm_date;

		$loop	= $this->accountallmodel->get_seller_stats_query($_PARAM);//debug_var($this->db->last_query());
		$tot = array();
		$count = 0;
		foreach($loop as $key=>$statsdata) {
			if( $_GET['acc_table'] >= date('Ym') ) {//당일이면 통합정산 당월데이타 기준으로 추출
				if($this->accountallmodel->account_fee_ar['pg']){
					//debug_var($statsdata);
					$loop[$key]['sum_price']			= ($statsdata['sum_price'] + $statsdata['sum_npay_point'] + $statsdata['naverstore_sum_price'] + $statsdata['refund_sum_all_npay_point'] );
					$loop[$key]['sum_price']			= ($loop[$key]['sum_price'] - $statsdata['refund_sum_price'] - $statsdata['sum_all_npay_point'] - $statsdata['refund_naverstore_sum_price']);
					$loop[$key]['sum_commission_price']	= ($statsdata['sum_commission_price'] + $statsdata['sum_sales_admin_total'] - $statsdata['refund_sum_commission_price'] - $statsdata['refund_sum_sales_admin_total'] );					
					$loop[$key]['sum_feeprice']			= ($statsdata['sum_feeprice']			- $statsdata['refund_sum_feeprice']);
					$loop[$key]['sum_sales_price']		= ($statsdata['sum_sales_price']		- $statsdata['refund_sum_sales_price']);
				}
			}
			//$tot['sum_ea']				+= $statsdata['sum_ea'];
			$tot['sum_price']				+= $loop[$key]['sum_price'];
			$tot['sum_commission_price']	+= $loop[$key]['sum_commission_price'];
			$tot['sum_feeprice']			+= $loop[$key]['sum_feeprice'];
		}
		$this->template->assign(array('loop'=> $loop,'loopcnt'=> count($loop),'tot'		=> $tot));
		$this->template->define(array('tpl'	=>$file_path));
		$this->template->print_("tpl");
	}

	//정산데이타 재정의리스트
	public function accountalllist_old($pagetype='list',$acinsdata, &$loop, &$tot, &$carryoverloop, &$carryovertot, &$overdrawloop, &$overdrawtot) {
		/**
		* 입점사정보 한번만 가져오기
		**/
		if($acinsdata['provider_seq']) {
			$data_provider = $this->providermodel->get_provider_one($acinsdata['provider_seq']);
			$acinsdata['provider_id']		= $data_provider['provider_id'];
			$acinsdata['out_provider_name'] = $data_provider['provider_name'];
			if(!$data_provider['calcu_count']) $data_provider['calcu_count'] = 1;
			$acinsdata['calcu_count'] 		= $data_provider['calcu_count'];
		}

		$acinsdata['out_deposit_date']			= substr($acinsdata['deposit_date'],2,8);
		if($acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback" || $acinsdata['account_type'] == "after_refund" ) {
			$acinsdata['out_confirm_date']		= substr($acinsdata['regist_date'],2,8);
			$acinsdata['account_refund_rollback'] = true;
		}else{
			if( substr(str_replace("-","",$acinsdata['confirm_date']),0,8) != '00000000' ) $acinsdata['out_confirm_date']		= substr($acinsdata['confirm_date'],2,8);
		}
		// 정산 대상여부 확인 :: 2018-05-28 lkh
		if(
			empty($acinsdata['out_ac_acc_status'])	// 정산대상여부를 선언하지 않았고 아래 조건 중 최소 하나를 만족해야함
			&& (
				($acinsdata['provider_seq'] == '1')	// 본사상품이거나
				|| (								// 반품이나 되돌리기 주문이거나
					$acinsdata['account_type'] == 'refund' || $acinsdata['account_type'] == 'rollback'
				) 
				|| (								// 아직 정산완료(ac_ea==0)이 완료됬으나 정산수량(exp_ea)이 없는 경우
					$acinsdata['account_type'] == 'order' && $acinsdata['exp_ea'] == 0 && $acinsdata['ac_ea'] == 0
				) 
				|| (								// 맞교환에 의한 주문일 경우
					$acinsdata['account_type'] == 'exchange'
				) 
			)
		){
			$acinsdata['out_ac_acc_status']			= "no_acc";	//정산대상여부
		}
		// 정산수량이 0개가 될 수 있으므로 강제 변환 로직 제거 by hed
		// // 전체환불시 계산이 꼬여서 계산되도록 기본 주문 갯수와 동일하게 처리 :: 2018-05-28 lkh
		// if($acinsdata['exp_ea'] < 1){
		// 	$acinsdata['exp_ea'] = $acinsdata['ea'];
		// }
		$acinsdata['out_order_goods_name']		= htmlspecialchars($acinsdata['order_goods_name']);
		// 반품배송비 수량 - 노출되도록 수정 :: 2018-07-09 lkh
		// if(  $acinsdata['account_type']!="return" && $acinsdata['order_type']=="shipping" ) {
		if( $acinsdata['order_type']=="shipping" ) {
			$acinsdata['out_ea']					= ' - ';
			$acinsdata['out_exp_ea']				= ' - ';
		}else{
			$acinsdata['out_ea']					= ($acinsdata['ea']);
			$acinsdata['out_exp_ea']				= ($acinsdata['exp_ea']);
		}

		if($this->accountallmodel->order_referer_om_ar[$acinsdata['order_referer']] || $acinsdata['order_referer']=='npay' ) {
			$acinsdata['out_pg_ordernum']			= ($acinsdata['linkage_mall_order_id'])?$acinsdata['linkage_mall_order_id']:$acinsdata['linkage_order_id'];
		}else{
			$acinsdata['out_pg_ordernum']			= $acinsdata['pg_ordernum'];
		}
		$acinsdata['out_order_referer_viewer']	= account_order_referer_title($acinsdata['order_referer'], $acinsdata);
		$acinsdata['out_payment']				= acc_payment($acinsdata['payment']);
		if($acinsdata['payment'] == "bank"){
			$bankAccountTmp = $this->ordermodel->get_order_bank_account($acinsdata['order_seq']);
			$bankAccount = explode(' ', $bankAccountTmp['bank_account']);
			$acinsdata['out_payment'] = $bankAccount[0];
		}

		//Npay 상품 할인액(Npay부담) # npay 쿠폰할인(네이버페이 부담=상품별 할인액-판매자 부담 할인액)
		$acinsdata['out_npay_sale_npay']	= ($acinsdata['npay_sale_npay']);
		//Npay 상품 할인액(판매자부담) : # npay 할인(배송비 할인 + 상품별 할인 - 네이버페이 부담 상품할인액)
		$acinsdata['out_npay_sale_seller']	= ($acinsdata['npay_sale_seller']);
		/* 제휴사 할인 계산 :: 2018-06-01 lkh
		 * 네이스마트스토어 {결제금액 = 주문금액 + 배송비 - 판매자할인 - (제휴사할인-판매자할인) }
		 * 11번가 {결제금액 = 주문금액 + 배송비 - 판매자할인 - 제휴사할인}
		 * 쿠팡 {결제금액 = 주문금액 + 배송비 - 제휴사할인}
		 */
		if($acinsdata['order_referer']=='storefarm'){
			$acinsdata['api_pg_sale_price'] = (($acinsdata['api_pg_sale_price'] - $acinsdata['api_pg_support_price']) + $acinsdata['api_pg_support_price']);
		}elseif($acinsdata['order_referer']=='open11st'){
			$acinsdata['api_pg_sale_price'] = ($acinsdata['api_pg_sale_price'] + $acinsdata['api_pg_support_price']);
		}elseif($acinsdata['order_referer']=='coupang'){
			$acinsdata['api_pg_sale_price'] = ($acinsdata['api_pg_sale_price'] + $acinsdata['api_pg_support_price']);
		}else{
			$acinsdata['api_pg_sale_price'] = 0;
		}
		$acinsdata['api_pg_support_price'] = 0;

		//결제수수료전용으로 npay 포인트 결제금액 체크
		if($this->accountallmodel->account_fee_ar['pg']){
			if( ($acinsdata['order_referer']=='npay' && $acinsdata['payment']=='point') ) {//||  ($acinsdata['order_referer']=='naverstorefarm' && $acinsdata['status'] != 'overdraw')
				$acinsdata['out_supply_price']			= ($acinsdata['supply_price']);		//매입가
				$acinsdata['out_consumer_price']		= ($acinsdata['consumer_price']);		//실제정가
				$acinsdata['out_original_price']		= ($acinsdata['original_price']);		//할인전정가*수량
				$acinsdata['out_price']					= ($acinsdata['price']);				//할인가(기본+이벤트 차감)*수량

				$acinsdata['out_org_price']				= ($acinsdata['org_price']);			//할인가(기본 차감)*수량
				$acinsdata['out_sales_basic']			= ($acinsdata['sales_basic']);			//기본할인*수량
				$acinsdata['out_sales_price']			= ($acinsdata['sales_price']);							//할인가(8가지할인항목 차감)*수량
			}else{
				//판매수량
				$acinsdata['out_supply_price']			= ($acinsdata['supply_price']*$acinsdata['ea']);		//매입가
				$acinsdata['out_consumer_price']		= ($acinsdata['consumer_price']*$acinsdata['ea']);		//실제정가
				$acinsdata['out_original_price']		= ($acinsdata['original_price']*$acinsdata['ea']);		//할인전정가*수량
				$acinsdata['out_price']					= ($acinsdata['price']*$acinsdata['ea']);				//할인가(기본+이벤트 차감)*수량
				$acinsdata['out_org_price']				= ($acinsdata['org_price']*$acinsdata['ea']);			//할인가(기본 차감)*수량
				$acinsdata['out_sales_basic']			= ($acinsdata['sales_basic']*$acinsdata['ea']);			//기본할인*수량
				$acinsdata['out_sales_price']			= ($acinsdata['sales_price']);							//할인가(8가지할인항목 차감)*수량

				//정산수량
				$acinsdata['out_ac_supply_price']		= ($acinsdata['supply_price']*$acinsdata['exp_ea']);		//매입가
				$acinsdata['out_ac_consumer_price']		= ($acinsdata['consumer_price']*$acinsdata['exp_ea']);		//실제정가
				$acinsdata['out_ac_original_price']		= ($acinsdata['original_price']*$acinsdata['exp_ea']);		//할인전정가*수량
				$acinsdata['out_ac_price']				= ($acinsdata['price']*$acinsdata['exp_ea']);				//할인가(기본+이벤트 차감)*수량
				$acinsdata['out_ac_org_price']			= ($acinsdata['org_price']*$acinsdata['exp_ea']);			//할인가(기본 차감)*수량
				$acinsdata['out_ac_sales_basic']		= ($acinsdata['sales_basic']*$acinsdata['exp_ea']);			//기본할인*수량
			}
		}else{
			//판매수량별
			$acinsdata['out_supply_price']			= ($acinsdata['supply_price']*$acinsdata['ea']);		//매입가
			$acinsdata['out_consumer_price']		= ($acinsdata['consumer_price']*$acinsdata['ea']);		//실제정가
			$acinsdata['out_original_price']		= ($acinsdata['original_price']*$acinsdata['ea']);		//할인전정가*수량
			$acinsdata['out_price']					= ($acinsdata['price']*$acinsdata['ea']);				//할인가(기본+이벤트 차감)*수량
			$acinsdata['out_org_price']				= ($acinsdata['org_price']*$acinsdata['ea']);			//할인가(기본 차감)*수량
			$acinsdata['out_sales_basic']			= ($acinsdata['sales_basic']*$acinsdata['ea']);			//기본할인*수량
			$acinsdata['out_sales_price']			= ($acinsdata['sales_price']);							//할인가(8가지할인항목 차감)*수량

			//정산수량별
			$acinsdata['out_ac_supply_price']		= ($acinsdata['supply_price']*$acinsdata['exp_ea']);		//매입가
			$acinsdata['out_ac_consumer_price']		= ($acinsdata['consumer_price']*$acinsdata['exp_ea']);		//실제정가
			$acinsdata['out_ac_original_price']		= ($acinsdata['original_price']*$acinsdata['exp_ea']);		//할인전정가*수량
			$acinsdata['out_ac_price']				= ($acinsdata['price']*$acinsdata['exp_ea']);				//할인가(기본+이벤트 차감)*수량
			$acinsdata['out_ac_org_price']			= ($acinsdata['org_price']*$acinsdata['exp_ea']);			//할인가(기본 차감)*수량
			$acinsdata['out_ac_sales_basic']		= ($acinsdata['sales_basic']*$acinsdata['exp_ea']);			//기본할인*수량
		}

		/**
		할인항목별 처리
		**/
		acc_promotion_sales_viewr('multi',	$acinsdata);
		acc_promotion_sales_viewr('event',	$acinsdata);
		acc_promotion_sales_viewr('member',	$acinsdata);
		acc_promotion_sales_viewr('coupon',	$acinsdata);
		acc_promotion_sales_viewr('fblike',	$acinsdata);
		acc_promotion_sales_viewr('mobile',	$acinsdata);
		acc_promotion_sales_viewr('code',	$acinsdata);
		acc_promotion_sales_viewr('referer',$acinsdata);

		acc_promotion_sales_viewr('emoney',	$acinsdata);
		acc_promotion_sales_viewr('cash',	$acinsdata);
		acc_promotion_sales_viewr('enuri',	$acinsdata);
		acc_promotion_sales_viewr('npay_point',	$acinsdata);

		/**
		** 할인부담금 시작
		**/

		//판매금액의 본사/정산대상금액의 본사 추출
		acc_promotion_sales_total($acinsdata);

		//쿠폰-할인(본사) out_ac_salescost_admin
		$acinsdata['out_salescost_admin']		= ($acinsdata['salescost_admin_promotion'] + $acinsdata['salescost_admin_sales'] - $acinsdata['out_cash_use']);
		$acinsdata['out_salescost_provider']	= ($acinsdata['salescost_provider_promotion'] + $acinsdata['salescost_provider_sales']);
		$acinsdata['out_salescost_total']		= ($acinsdata['out_salescost_admin'] + $acinsdata['out_salescost_provider']);
		$acinsdata['out_ac_salescost_admin']	= ($acinsdata['ac_salescost_admin_promotion'] + $acinsdata['ac_salescost_admin_sales'] - $acinsdata['out_ac_cash_use']); //정산대상금액(A)>본사
		$acinsdata['out_ac_salescost_provider']	= ($acinsdata['ac_salescost_provider_promotion'] + $acinsdata['ac_salescost_provider_sales']);//정산대상금액(A)>입점사
		$acinsdata['out_ac_salescost_total']	= ($acinsdata['out_ac_salescost_admin'] + $acinsdata['out_ac_salescost_provider']); //정산대상금액(A)>할인전체

		if($this->accountallmodel->account_fee_ar['pg']){//결제수수료전용으로 엑셀업로드 정산금액으로 적용
			//쿠폰(마일리지)할인>제휴사 -> Npay Point /11번가 제휴사할인
			if( $acinsdata['status'] == 'overdraw' ) {//차월일때에는 결제금액 그대로
				$acinsdata['out_pg_sale_price'] = $acinsdata['pg_sale_price'] = $acinsdata['api_pg_sale_price'];
				 if( $acinsdata['order_referer']=='open11st' ){
					$acinsdata['out_price']	-= $acinsdata['out_pg_sale_price'];//결제금액 11번가 차감
				 }
			}else{
				 if( $acinsdata['order_referer']=='npg' ){
					 $npay_point_sale		= ($acinsdata['out_npay_point_use'] + $acinsdata['api_pg_sale_price']);
					 $ac_npay_point_sale	= ($acinsdata['out_ac_npay_point_use'] + $acinsdata['api_pg_sale_price']);
					$acinsdata['out_price']	-= $npay_point_sale;//결제금액 네이버포인트 차감
					//debug_var($acinsdata['out_npay_point_use']."/".$acinsdata['out_price']);
				 }elseif( $acinsdata['order_referer']=='open11st' ){
					$acinsdata['out_price']	-= $acinsdata['out_pg_sale_price'];//결제금액 11번가 차감
				 }
			}
			if( $acinsdata['order_referer'] == 'npg' || $acinsdata['order_referer'] == 'pg' ) {
				$acinsdata['real_sale_price']	= ($acinsdata['out_price'])-($acinsdata['salescost_total']+$npay_point_sale);//실결제금액1
				$acinsdata['out_sale_price']	= ($acinsdata['real_sale_price'])+($acinsdata['out_cash_use']);//결제금액(A)
				$acinsdata['out_sale_price']	-= ($npay_point_sale);//결제금액 네이버포인트 차감
				//debug_var($acinsdata['out_sale_price']."/".$npay_point_sale);
			}else{
				$acinsdata['out_sale_price']	= ($acinsdata['out_price'])-($acinsdata['salescost_admin']);//결제금액
				$acinsdata['out_ac_sale_price']	= ($acinsdata['out_ac_price'])-($acinsdata['ac_salescost_admin']);//결제금액 ac_salescost_total
				//debug_var($acinsdata['out_sale_price']."==>(".$acinsdata['out_price'].")-(".$acinsdata['salescost_total'].")");
				//debug_var($acinsdata['out_ac_sale_price']."==>(".$acinsdata['out_ac_price'].")-(".$acinsdata['ac_salescost_admin'].")");
			}

			//엑셀정산확정시 노출되도록 개선 중요 별5개~~(퍼스트몰에서는 제외)
			if( $acinsdata['status'] != 'overdraw' && ( !($acinsdata['order_referer'] == 'shop' && $acinsdata['payment'] == 'bank') )  ) {

				if( $acinsdata['order_referer'] = 'npg' &&  $acinsdata['account_type'] == "refund" ) {//npg 환불시 이미 차감된 금액으로
					$acinsdata['out_sale_price']		= $acinsdata['api_pg_price'] - ($acinsdata['salescost_total']);
				}else{
					$acinsdata['out_sale_price']		= $acinsdata['api_pg_price'] - ($npay_point_sale+$acinsdata['salescost_total']);
				}
				//debug_var($acinsdata['out_sale_price'].'= '.$acinsdata['api_pg_price'].' - ('.$npay_point_sale.'+'.$acinsdata['salescost_total'].')');
				$acinsdata['commission_price']		= $acinsdata['api_pg_commission_price'];
				$acinsdata['commission_price_rest']	= $acinsdata['api_pg_commission_price_rest'];
				$acinsdata['sales_unit_feeprice']	= $acinsdata['api_pg_sales_unit_feeprice'];
				$acinsdata['sales_feeprice_rest']	= $acinsdata['api_pg_sales_feeprice_rest'];
				$acinsdata['sales_unit_minfee']		= 0;
			}

		}else{
			// 제휴사 할인 계산 + out_pg_sale_price :: 2018-06-01 lkh
			$acinsdata['out_pg_sale_price']		= $acinsdata['pg_sale_price'] = $acinsdata['api_pg_sale_price'];
			$acinsdata['out_price'] 			= ($acinsdata['out_price']);

			$acinsdata['out_sales_price_total']	= ($acinsdata['out_price']) + $acinsdata['out_salescost_total'];//총 판매금액(8가지할인항목 총합)  + (
			// 제휴사 할인 계산 + out_pg_sale_price :: 2018-06-01 lkh
			$acinsdata['out_sale_price']		= ($acinsdata['out_price'] - $acinsdata['out_pg_sale_price'])-($acinsdata['out_salescost_total'])-$acinsdata['out_cash_use'];//결제금액
			$acinsdata['out_ac_sale_price']		= ($acinsdata['out_ac_price'] - $acinsdata['out_pg_sale_price'])-($acinsdata['out_ac_salescost_total'])-$acinsdata['out_ac_cash_use'];//결제금액
			if($acinsdata['out_ac_sale_price'] < 0){
				$acinsdata['out_ac_sale_price'] = 0;
			}
		}
		/**
		** 할인부담금 끝
		**/

		$acinsdata['account_type_view'] 	= $this->accountallmodel->account_type_ar[$acinsdata['account_type']];
		$acinsdata['out_step'] 				= ($acinsdata['account_type']=="order" && $acinsdata['status']=="overdraw")?$this->arr_step[$acinsdata['step']]:$acinsdata['account_type_view'];
		//debug_var($acinsdata['out_step']."/step:".$acinsdata['step']."/account_type_view:".$acinsdata['account_type_view']."/account_type:".$acinsdata['account_type']);

		$acinsdata['out_order_type'] 		= ($acinsdata['account_type']=="return" && $acinsdata['order_type']=="shipping")?$this->accountallmodel->order_type_ar['returnshipping']:$this->accountallmodel->order_type_ar[$acinsdata['order_type']];

		if( $acinsdata['order_goods_kind'] == 'shipping' && $acinsdata['shipping_provider_seq'] == 1) {//위탁배송이면 정산 0원처리
			$acinsdata['out_ac_profit_price']		= $acinsdata['out_ac_sale_price']+$acinsdata['out_ac_cash_use'];		//이익금
			$acinsdata['out_ac_profit_rate']		= sprintf("%.1f",100);		//이익율
			$acinsdata['out_total_ac_price']		= 0;		//정산대상금액(A)>합계
			$acinsdata['out_pg_default_price']		= 0;		//정산대상금액(A)>PG(전쳬)
			$acinsdata['out_ac_salescost_admin']	= 0;		//정산대상금액(A)>본사
			$acinsdata['out_ac_salescost_provider']	= 0;		//정산대상금액(A)>입점사
			$acinsdata['out_ac_salescost_total']	= 0;		//정산대상금액(A)>할인전체
			$acinsdata['out_ac_cash_use']			= 0;		//정산대상금액(A)>이머니
			$acinsdata['out_ac_pg_price']			= 0;		//정산대상금액(A)>제휴사 -> Npay Point
			$acinsdata['out_pg_add_price']			= 0;		//정산대상금액(A)>추가할인
			$acinsdata['out_ac_consumer_real_price']= 0;		//공급금액
			$acinsdata['out_fee_rate']				= 0;		//수수료율
			$acinsdata['out_sales_unit_feeprice']	= 0;		//정산>수수료(B)
			$acinsdata['out_commission_price']		= 0;		//정산> 정산금액(A-B)
			$acinsdata['out_pg_support_price']		= 0;		//판매자 추가할인
			$acinsdata['out_ac_acc_status']			= "no_acc";	//정산대상여부
		}else{
			if($this->accountallmodel->account_fee_ar['pg'] && !($acinsdata['order_referer'] == 'shop' && $acinsdata['payment'] == 'bank') ){
				if( $acinsdata['order_referer'] == 'npg' || $acinsdata['order_referer'] == 'pg' ) {
					$acinsdata['out_pg_default_price']	= ($acinsdata['out_sale_price']);		//결제금액(A)->정산대상금액  -$npay_point_sale

					$acinsdata['out_total_ac_price']	= ($acinsdata['out_pg_default_price']+$acinsdata['out_salescost_total']);//정산대상금액(A)>합계+$npay_point_sale
				}else{
					$acinsdata['out_total_ac_price']	= (($acinsdata['out_sale_price'])+$npay_point_sale+$acinsdata['ac_salescost_admin']+($acinsdata['out_cash_use']));//정산대상금액(A)>합계
					$acinsdata['out_pg_default_price']	= ($acinsdata['out_sale_price']);		//정산대상금액(A)>PG(전쳬)
				}
			}else{
				// 합계금액 잘못나와 수정 :: 2018-05-21 lkh
				if($acinsdata['commission_type'] == "SUCO"){
					$acinsdata['out_total_ac_price']	= ($acinsdata['out_ac_sale_price']+$acinsdata['out_ac_cash_use']);//정산대상금액(A)>합계
					$acinsdata['out_ac_salescost_admin'] = 0;
				}elseif($acinsdata['commission_type'] == "SUPR"){
					$acinsdata['out_total_ac_price']	= ($acinsdata['out_ac_sale_price']+$acinsdata['out_ac_cash_use']);//정산대상금액(A)>합계
					$acinsdata['out_ac_salescost_admin'] = 0;
				}else{
					$acinsdata['out_total_ac_price']	= (($acinsdata['out_ac_sale_price'])+$acinsdata['out_ac_salescost_admin']+$acinsdata['out_ac_cash_use']);//정산대상금액(A)>합계
				}
				$acinsdata['out_pg_default_price']	= ($acinsdata['out_ac_sale_price'])+$acinsdata['out_ac_cash_use'];		//정산대상금액(A)>PG(전쳬)
			}

			//정산대상금액 - 수수료액
			if($pagetype == "list2"){
				//$acinsdata['out_sales_unit_feeprice']	= $acinsdata['total_feeprice'];
				$acinsdata['out_sales_unit_feeprice']	= (round($acinsdata['sales_unit_feeprice']*$acinsdata['exp_ea'])+$acinsdata['sales_unit_minfee']+($acinsdata['sales_feeprice_rest']));
			}else{
				$acinsdata['out_sales_unit_feeprice']	= (($acinsdata['sales_unit_feeprice']*$acinsdata['exp_ea'])+$acinsdata['sales_unit_minfee']+($acinsdata['sales_feeprice_rest']));
			}

			//20190429 pjm
			//정산대상금액 - 수수료액
			if($pagetype == "list2"){
				//$acinsdata['out_commission_price']	= $acinsdata['total_commission_price'];
				$acinsdata['out_commission_price']	= $acinsdata['out_total_ac_price'] - $acinsdata['out_sales_unit_feeprice'];
			}else{
			//단품 정산금액*ea+짜투리-수수료짜투리
			$acinsdata['out_commission_price']	= $acinsdata['commission_price']*$acinsdata['exp_ea']+($acinsdata['commission_price_rest']);
			}

			// 공급금액 계산
			$acinsdata['out_ac_fee_rate'] = 0;
			if($acinsdata['commission_type'] == "SUCO"){
				$acinsdata['out_ac_consumer_real_price']	= ((int)$acinsdata['consumer_price']*$acinsdata['commission_rate']/100)*$acinsdata['exp_ea'];	//공급가액
				if($acinsdata['out_ac_consumer_real_price'] && $acinsdata['out_sales_unit_feeprice']){
					if($acinsdata['out_total_ac_price'] > 0 && $acinsdata['out_sales_unit_feeprice'] > 0)
						$acinsdata['out_ac_fee_rate']				= round((($acinsdata['commission_rate'])),2);	//수수료율
				}
				//debug_var($acinsdata['out_fee_rate'].'= (('.$acinsdata['out_sales_unit_feeprice'].')/'.$acinsdata['out_total_ac_provider_price'].'*100)');
			}elseif($acinsdata['commission_type'] == "SUPR"){
				$acinsdata['out_ac_consumer_real_price']	= $acinsdata['commission_price']*$acinsdata['exp_ea'];												//공급가액
				if($acinsdata['out_ac_consumer_real_price'] && $acinsdata['out_sales_unit_feeprice']){
					if($acinsdata['out_total_ac_price'] > 0 && $acinsdata['out_sales_unit_feeprice'] > 0)
						$acinsdata['out_ac_fee_rate']				= 0;	//수수료율
				}
			}else{
				$acinsdata['out_ac_consumer_real_price']	= 0;								//공급가액
				$acinsdata['out_ac_fee_rate']				= $acinsdata['commission_rate'];	//수수료율 round($acinsdata['out_fee_rate'],2)
			}
			if( ($acinsdata['account_type'] != 'refund' && $acinsdata['account_type'] != 'rollback') && ($acinsdata['status'] == "complete" && $acinsdata['exp_ea'] > 0) || ($acinsdata['status'] == "carryover" && $acinsdata['exp_ea'] > 0) || ($acinsdata['order_goods_kind'] != 'shipping' && $acinsdata['provider_seq'] == 1) || ($acinsdata['order_goods_kind'] == 'shipping' && $acinsdata['shipping_provider_seq'] == 1) ){
				// 3차 환불 개선으로 티켓상품 금액 처리 추가 :: 2018-11- lkh
				$outPgDefaultPrice = $acinsdata['out_pg_default_price'];
				if($acinsdata['order_goods_kind'] == "coupon"){
					$couponStatusArr = explode("|",$acinsdata['socialcp_status']);
					$couponValArr = explode("|",$acinsdata['coupon_value']);
					$couponRemainValArr = explode("|",$acinsdata['coupon_remain_value']);
					$couponVal = 0;
					$couponUseVal = 0;
					foreach($couponStatusArr as $cpKey => $cpVal){
						$cpValSt = substr($cpVal,0,1);
						$cpStHalfChk = array(7,9);
						if(in_array($cpValSt,$cpStHalfChk)){
							$couponVal += $couponValArr[$cpKey];
							$couponUseVal += ($couponValArr[$cpKey] -$couponRemainValArr[$cpKey]);
						}else{
							$couponVal += $couponValArr[$cpKey];
							$couponUseVal += $couponValArr[$cpKey];
						}
					}
					if($couponVal != $couponUseVal){
						$couponUsePercent = 100 * ($couponUseVal / $couponVal);//사용값 비율
						$acinsdata['out_total_ac_price']		= acc_coupon_remain_sales_unit($acinsdata['out_total_ac_price'],$couponUsePercent);			//정산대상금액(A)>합계
						$acinsdata['out_pg_default_price']		= acc_coupon_remain_sales_unit($acinsdata['out_pg_default_price'],$couponUsePercent);		//정산대상금액(A)>PG(전쳬)
						$acinsdata['out_ac_salescost_admin']	= acc_coupon_remain_sales_unit($acinsdata['out_ac_salescost_admin'],$couponUsePercent);		//정산대상금액(A)>본사
						$acinsdata['out_ac_salescost_provider']	= acc_coupon_remain_sales_unit($acinsdata['out_ac_salescost_provider'],$couponUsePercent);	//정산대상금액(A)>입점사
						$acinsdata['out_ac_salescost_total']	= acc_coupon_remain_sales_unit($acinsdata['out_ac_salescost_total'],$couponUsePercent);		//정산대상금액(A)>할인전체
						$acinsdata['out_ac_cash_use']			= acc_coupon_remain_sales_unit($acinsdata['out_ac_cash_use'],$couponUsePercent);			//정산대상금액(A)>이머니
						$acinsdata['out_ac_pg_price']			= acc_coupon_remain_sales_unit($acinsdata['out_ac_pg_price'],$couponUsePercent);			//정산대상금액(A)>제휴사 -> Npay Point
						$acinsdata['out_pg_add_price']			= acc_coupon_remain_sales_unit($acinsdata['out_pg_add_price'],$couponUsePercent);			//정산대상금액(A)>추가할인
						$acinsdata['out_ac_consumer_real_price']= acc_coupon_remain_sales_unit($acinsdata['out_ac_consumer_real_price'],$couponUsePercent);	//공급금액
						$acinsdata['out_sales_unit_feeprice']	= acc_coupon_remain_sales_unit($acinsdata['out_sales_unit_feeprice'],$couponUsePercent);	//정산>수수료(B)
						$acinsdata['out_commission_price']		= acc_coupon_remain_sales_unit($acinsdata['out_commission_price'],$couponUsePercent);		//정산> 정산금액(A-B)
						$acinsdata['out_pg_support_price']		= acc_coupon_remain_sales_unit($acinsdata['out_pg_support_price'],$couponUsePercent);		//판매자 추가할인
					}
				}
				
				// 이익 계산
				$acinsdata['out_ac_profit_price']	= $outPgDefaultPrice-$acinsdata['out_commission_price'];	//이익
				$acinsdata['out_ac_profit_rate'] 	= 0;
				if($acinsdata['out_ac_profit_price'] && $outPgDefaultPrice){
					$acinsdata['out_ac_profit_rate']	= round(($acinsdata['out_ac_profit_price']/$outPgDefaultPrice*100),1);	//이익율
					$acinsdata['out_ac_profit_rate']	= sprintf("%.1f",$acinsdata['out_ac_profit_rate']);
				}
			}

			// 결제 금액이 0원일때 결제금액, 수수료율 0 처리 :: 2018-07-11 lkh
			// 정산대상관련금액 초기화? : 본사할인금액 0원 && 결제금액 0원 && 예치금사용액 0원
			if( ($acinsdata['out_ac_pg_price'] <= 0 && $acinsdata['out_ac_salescost_admin'] <= 0 && $acinsdata['out_ac_sale_price'] <= 0 && $acinsdata['out_cash_use'] <= 0) || ($acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback") ){

				$acinsdata['out_total_ac_price']		= 0;		//정산대상금액(A)>합계
				$acinsdata['out_pg_default_price']		= 0;		//정산대상금액(A)>PG(전쳬)
				$acinsdata['out_ac_salescost_admin']	= 0;		//정산대상금액(A)>본사
				$acinsdata['out_ac_salescost_provider']	= 0;		//정산대상금액(A)>입점사
				$acinsdata['out_ac_salescost_total']	= 0;		//정산대상금액(A)>할인전체
				$acinsdata['out_ac_cash_use']			= 0;		//정산대상금액(A)>이머니
				$acinsdata['out_ac_pg_price']			= 0;		//정산대상금액(A)>제휴사 -> Npay Point
				$acinsdata['out_pg_add_price']			= 0;		//정산대상금액(A)>추가할인
				$acinsdata['out_ac_consumer_real_price']= 0;		//공급금액
				//$acinsdata['out_sales_unit_feeprice']	= 0;		//정산>수수료(B)
				//$acinsdata['out_commission_price']		= 0;		//정산> 정산금액(A-B)
				$acinsdata['out_pg_support_price']		= 0;		//판매자 추가할인
				//$acinsdata['out_ac_fee_rate'] 			= 0;
			}
			//debug_var($acinsdata['out_ac_profit_rate'].'= (('.$acinsdata['out_ac_profit_price'].')/'.$acinsdata['out_pg_default_price'].'*100)');
		}
		
        $acinsdata['minus_sale'] = '0';    // 매출 마이너스 표기 0:양수, 1:음수
		switch($acinsdata['status']){//전월/당월/차월 구분
			case "carryover"://통합정산데이타의 전월
			case "not-carryover":

				$carryovertot['total_num']++;
				$carryovertot['out_title']				= $acinsdata['out_title']	= "이월";
				$carryovertot['out_num']				= '합계';
				$carryovertot['out_total_title']		= 'start';

				//소계영역
				if( $acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback" ) {
					$acinsdata['minus_sale'] = '1';    // 매출 마이너스 표기 0:양수, 1:음수
					$carryovertot['refund_out_consumer_price']		+= $acinsdata['out_consumer_price'];
					$carryovertot['refund_out_sales_price']			+= $acinsdata['out_sales_price'];
					$carryovertot['refund_salescost_total']			+= $acinsdata['salescost_total'];
					$carryovertot['refund_pg_sale_price']			+= $acinsdata['pg_sale_price'];
					$carryovertot['refund_pg_support_price']		+= $acinsdata['pg_support_price'];

					//통합매출 소계
					$carryovertot['refund_out_ea']					+= $acinsdata['out_ea'];
					$carryovertot['refund_out_price']				+= $acinsdata['out_price'];
					$carryovertot['refund_out_salescost_total']		+= $acinsdata['out_salescost_total'];
					$carryovertot['refund_out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
					$carryovertot['refund_out_cash_use']			+= $acinsdata['out_cash_use'];
					$carryovertot['refund_out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
					$carryovertot['refund_out_sale_price']			+= $acinsdata['out_sale_price'];
					$carryovertot['refund_salescost_admin']			+= $acinsdata['out_salescost_admin'];
					$carryovertot['refund_salescost_provider']		+= $acinsdata['out_salescost_provider'];

					//통합정산 소계
					$carryovertot['refund_out_exp_ea']				+= $acinsdata['out_exp_ea'];
					$carryovertot['refund_out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
					$carryovertot['refund_out_pg_default_price']	+= $acinsdata['out_pg_default_price'];
					$carryovertot['refund_out_ac_salescost_total']	+= $acinsdata['out_ac_salescost_total'];
					$carryovertot['refund_out_ac_cash_use']			+= $acinsdata['out_ac_cash_use'];
					$carryovertot['refund_out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
					$carryovertot['refund_out_pg_add_price']		+= $acinsdata['out_pg_add_price'];
					$carryovertot['refund_sales_unit_feeprice']		+= $acinsdata['out_sales_unit_feeprice'];
					$carryovertot['refund_out_commission_price']	+= $acinsdata['out_commission_price'];

					$carryovertot['refund_ac_salescost_admin']		+= $acinsdata['out_ac_salescost_admin'];
					$carryovertot['refund_ac_salescost_provider']	+= $acinsdata['out_ac_salescost_provider'];

					$carryovertot['refund_out_ac_consumer_real_price']	+= $acinsdata['out_ac_consumer_real_price'];
					$carryovertot['refund_out_ac_profit_price']			+= $acinsdata['out_ac_profit_price'];

				}else{
					$carryovertot['out_consumer_price']		+= $acinsdata['out_consumer_price'];
					$carryovertot['out_sales_price']		+= $acinsdata['out_sales_price'];
					$carryovertot['salescost_total']		+= $acinsdata['salescost_total'];
					$carryovertot['pg_sale_price']			+= $acinsdata['pg_sale_price'];
					$carryovertot['pg_support_price']		+= $acinsdata['pg_support_price'];

					//통합매출 소계
					$carryovertot['out_ea']					+= $acinsdata['out_ea'];
					$carryovertot['out_price']				+= $acinsdata['out_price'];
					$carryovertot['out_salescost_total']	+= $acinsdata['out_salescost_total'];
					$carryovertot['out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
					$carryovertot['out_cash_use']			+= $acinsdata['out_cash_use'];
					$carryovertot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
					$carryovertot['out_sale_price']			+= $acinsdata['out_sale_price'];
					$carryovertot['out_salescost_admin']		+= $acinsdata['out_salescost_admin'];
					$carryovertot['out_salescost_provider']		+= $acinsdata['out_salescost_provider'];

					if($acinsdata['ac_type'] == "cal_sales" && !$acinsdata['out_confirm_date']){
						$acinsdata['out_total_ac_price']		= 0;
						$acinsdata['out_pg_default_price']		= 0;
						$acinsdata['out_ac_salescost_total']	= 0;
						$acinsdata['out_ac_cash_use']			= 0;
						$acinsdata['out_ac_pg_price']			= 0;
						$acinsdata['out_pg_add_price']			= 0;
						$acinsdata['out_sales_unit_feeprice']	= 0;
						$acinsdata['out_commission_price']		= 0;
						$acinsdata['out_exp_ea']				= 0;
						$acinsdata['out_ac_salescost_admin']	= 0;
						$acinsdata['out_ac_salescost_provider']	= 0;
						$acinsdata['out_ac_consumer_real_price']= 0;
						$acinsdata['out_ac_fee_rate']			= 0;
						$acinsdata['out_ac_profit_price']		= 0;
						$acinsdata['out_ac_profit_rate']		= 0;
						//$acinsdata['out_ac_profit_price']		= 0;
						//$acinsdata['out_ac_profit_rate']		= 0;
						if(!$acinsdata['out_ac_acc_status']) $acinsdata['out_ac_acc_status'] = "ing_acc";	//정산대상여부
					}

					//통합정산 소계
					$carryovertot['out_exp_ea']					+= $acinsdata['out_exp_ea'];
					$carryovertot['out_total_ac_price']			+= $acinsdata['out_total_ac_price'];
					$carryovertot['out_pg_default_price']		+= $acinsdata['out_pg_default_price'];
					$carryovertot['out_ac_salescost_total']		+= $acinsdata['out_ac_salescost_total'];
					$carryovertot['out_ac_cash_use']			+= $acinsdata['out_ac_cash_use'];
					$carryovertot['out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
					$carryovertot['out_pg_add_price']			+= $acinsdata['out_pg_add_price'];
					$carryovertot['out_sales_unit_feeprice']	+= $acinsdata['out_sales_unit_feeprice'];
					$carryovertot['out_commission_price']		+= $acinsdata['out_commission_price'];
					$carryovertot['out_ac_salescost_admin']		+= $acinsdata['out_ac_salescost_admin'];
					$carryovertot['out_ac_salescost_provider']	+= $acinsdata['out_ac_salescost_provider'];
					$carryovertot['out_ac_consumer_real_price']	+= $acinsdata['out_ac_consumer_real_price'];
					$carryovertot['out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

				}
				$carryoverloop[] = $acinsdata;
			break;
			case "overdraw"://차월영역
				/**
				* 현재기준 이달이면 통합매출데이타의 당월영역이며 전달이면 차월영역 노출
				* @2018-02-06
				**/
				/*if($_GET['acc_table'] >= date('Ym')) {*/
					$tot['total_num']++;
					$tot['out_title']			= $acinsdata['out_title']	= "당월";
					$tot['out_num']				= '합계';
					$tot['out_total_title']		= 'start';
					//소계영역
					if( $acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback" ) {
						$acinsdata['minus_sale'] = '1';    // 매출 마이너스 표기 0:양수, 1:음수
						$tot['refund_out_consumer_price']		+= $acinsdata['out_consumer_price'];
						$tot['refund_out_sales_price']			+= $acinsdata['out_sales_price'];
						$tot['refund_salescost_total']			+= $acinsdata['salescost_total'];
						$tot['refund_pg_sale_price']			+= $acinsdata['pg_sale_price'];
						$tot['refund_pg_support_price']			+= $acinsdata['pg_support_price'];
						$tot['refund_out_pg_add_price']			+= $acinsdata['out_pg_add_price'];
						if($acinsdata['ac_type'] == "sales") {
							//통합정산 소계
							$acinsdata['out_total_ac_price']		= 0;
							$acinsdata['out_pg_default_price']		= 0;
							$acinsdata['out_ac_salescost_total']	= 0;
							$acinsdata['out_ac_cash_use']			= 0;
							$acinsdata['out_ac_pg_price']			= 0;
							$acinsdata['out_pg_add_price']			= 0;
							$acinsdata['out_sales_unit_feeprice']	= 0;
							$acinsdata['out_commission_price']		= 0;
							$acinsdata['out_exp_ea']				= 0;
							$acinsdata['out_ac_salescost_admin']	= 0;
							$acinsdata['out_ac_salescost_provider']	= 0;
							$acinsdata['out_ac_consumer_real_price']= 0;
							$acinsdata['out_ac_fee_rate']			= 0;
							//$acinsdata['out_ac_profit_price']		= 0;
							//$acinsdata['out_ac_profit_rate']		= 0;
							if(!$acinsdata['out_ac_acc_status']) $acinsdata['out_ac_acc_status'] = "ing_acc";	//정산대상여부

							$tot['out_ac_salescost_admin']		+= 0;
							$tot['out_ac_salescost_provider']	+= 0;

							$tot['refund_out_ea']					+= $acinsdata['out_ea'];
							$tot['refund_out_exp_ea']				+= $acinsdata['out_exp_ea'];
							$tot['refund_out_total_ac_price']		+= 0;//$acinsdata['out_total_ac_price'];
							$tot['refund_out_pg_default_price']		+= 0;//$acinsdata['out_pg_default_price'];
							$tot['refund_out_ac_salescost_total']	+= 0;//$acinsdata['out_ac_salescost_total'];
							$tot['refund_out_ac_cash_use']			+= 0;//$acinsdata['out_cash_use'];
							$tot['refund_out_ac_pg_price']			+= 0;//$acinsdata['out_ac_pg_price'];
							$tot['refund_out_pg_add_price']			+= 0;//$acinsdata['out_pg_add_price'];
							$tot['refund_sales_unit_feeprice']		+= 0;// $acinsdata['out_sales_unit_feeprice'];
							$tot['refund_out_commission_price']		+= 0;//$acinsdata['out_commission_price'];
							$tot['refund_out_pg_support_price']		+= 0;//$acinsdata['out_api_pg_support_price'];

							$tot['refund_out_price']				+= $acinsdata['out_price'];
							$tot['refund_out_salescost_total']		+= $acinsdata['out_salescost_total'];
							$tot['refund_out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
							$tot['refund_out_cash_use']				+= $acinsdata['out_cash_use'];
							$tot['refund_out_pg_support_price']		+= $acinsdata['out_pg_support_price'];
							$tot['refund_out_sale_price']			+= $acinsdata['out_sale_price'];
							$tot['refund_salescost_admin']			+= $acinsdata['out_salescost_admin'];
							$tot['refund_salescost_provider']		+= $acinsdata['out_salescost_provider'];
							$tot['refund_out_ac_consumer_real_price']+= $acinsdata['out_ac_consumer_real_price'];
							$tot['refund_out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

						}else{//통합정산 소계
							$tot['refund_out_ea']					+= $acinsdata['out_ea'];
							$tot['refund_out_exp_ea']				+= $acinsdata['out_exp_ea'];
							$tot['refund_out_price']				+= $acinsdata['out_price'];
							$tot['refund_out_salescost_total']		+= $acinsdata['out_salescost_total'];
							$tot['refund_out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
							$tot['refund_out_cash_use']				+= $acinsdata['out_cash_use'];
							$tot['refund_out_pg_support_price']		+= $acinsdata['out_pg_support_price'];
							$tot['refund_out_sale_price']			+= $acinsdata['out_sale_price'];
							$tot['refund_out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
							$tot['refund_out_pg_default_price']		+= $acinsdata['out_pg_default_price'];
							$tot['refund_sales_unit_feeprice']		+= $acinsdata['out_sales_unit_feeprice'];
							$tot['refund_out_commission_price']		+= $acinsdata['out_commission_price'];
							$tot['refund_salescost_admin']			+= $acinsdata['out_salescost_admin'];
							$tot['refund_salescost_provider']		+= $acinsdata['out_salescost_provider'];
							$tot['refund_out_ac_salescost_total']	+= $acinsdata['out_salescost_total'];
							$tot['refund_out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
							$tot['refund_out_ac_cash_use']			+= $acinsdata['out_cash_use'];
							$tot['refund_out_ac_consumer_real_price']+= $acinsdata['out_ac_consumer_real_price'];
							$tot['refund_out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

						}
					}else{
						$tot['out_consumer_price']		+= $acinsdata['out_consumer_price'];
						$tot['out_sales_price']			+= $acinsdata['out_sales_price'];
						$tot['salescost_total']			+= $acinsdata['salescost_total'];
						$tot['pg_sale_price']			+= $acinsdata['pg_sale_price'];
						$tot['pg_support_price']		+= $acinsdata['pg_support_price'];

						//통합매출 소계

						if($acinsdata['ac_type'] == "sales") {
							//통합정산 소계
							$acinsdata['out_exp_ea']				= 0;
							$acinsdata['out_total_ac_price']		= 0;
							$acinsdata['out_pg_default_price']		= 0;
							$acinsdata['out_ac_salescost_total']	= 0;
							$acinsdata['out_ac_cash_use']			= 0;
							$acinsdata['out_ac_pg_price']			= 0;
							$acinsdata['out_pg_add_price']			= 0;
							$acinsdata['out_sales_unit_feeprice']	= 0;
							$acinsdata['out_commission_price']		= 0;//out_ac_salescost_total
							$acinsdata['out_pg_support_price']		= 0;//out_api_pg_support_price
							$acinsdata['out_ac_consumer_real_price']= 0;
							$acinsdata['out_ac_fee_rate']			= 0;
							//$acinsdata['out_ac_profit_price']		= 0;
							//$acinsdata['out_ac_profit_rate']		= 0;
							if(!$acinsdata['out_ac_acc_status']) $acinsdata['out_ac_acc_status'] = "ing_acc";	//정산대상여부

							$tot['out_total_ac_price']		+= 0;//$acinsdata['out_total_ac_price'];
							$tot['out_pg_default_price']	+= 0;//$acinsdata['out_pg_default_price'];
							$tot['out_ac_salescost_total']	+= 0;//$acinsdata['out_ac_salescost_total'];
							$tot['out_ac_cash_use']			+= 0;//$acinsdata['out_ac_cash_use'];out_salescost_total
							$tot['out_ac_pg_price']			+= 0;//$acinsdata['out_ac_pg_price'];
							$tot['out_pg_add_price']		+= 0;//$acinsdata['out_pg_add_price'];
							$tot['out_sales_unit_feeprice']	+= 0;//$acinsdata['out_sales_unit_feeprice'];
							$tot['out_commission_price']	+= 0;//$acinsdata['out_commission_price'];out_salescost_total
							$tot['out_pg_support_price']	+= 0;//$acinsdata['out_pg_support_price'];
							$tot['out_ac_consumer_real_price']+= 0;
							//$tot['out_ac_profit_price']		+= 0;

							$tot['out_ea']					+= $acinsdata['out_ea'];
							$tot['out_exp_ea']				+= $acinsdata['out_exp_ea'];
							$tot['out_price']				+= $acinsdata['out_price'];
							$tot['out_salescost_total']		+= $acinsdata['out_salescost_total'];
							$tot['out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
							$tot['out_cash_use']			+= $acinsdata['out_cash_use'];
							$tot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
							$tot['out_sale_price']			+= $acinsdata['out_sale_price'];

							$tot['out_salescost_admin']		+= $acinsdata['out_salescost_admin'];
							$tot['out_salescost_provider']	+= $acinsdata['out_salescost_provider'];
							$tot['out_ac_consumer_real_price']+= $acinsdata['out_ac_consumer_real_price'];
							$tot['out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

							$acinsdata['out_ac_salescost_admin']	= 0;
							$acinsdata['out_ac_salescost_provider']	= 0;

							$tot['out_ac_salescost_admin']		+= 0;
							$tot['out_ac_salescost_provider']	+= 0;

						}else{//통합정산 소계if($acinsdata['ac_type'] == "cal"){

							$tot['out_ea']					+= $acinsdata['out_ea'];
							$tot['out_exp_ea']				+= $acinsdata['out_exp_ea'];
							$tot['out_price']				+= $acinsdata['out_price'];
							$tot['out_salescost_total']		+= $acinsdata['out_salescost_total'];
							$tot['out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
							$tot['out_cash_use']			+= $acinsdata['out_cash_use'];
							$tot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
							$tot['out_sale_price']			+= $acinsdata['out_sale_price'];
							$tot['out_salescost_admin']		+= $acinsdata['out_salescost_admin'];
							$tot['out_salescost_provider']	+= $acinsdata['out_salescost_provider'];

							$tot['out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
							$tot['out_pg_default_price']	+= $acinsdata['out_pg_default_price'];
							$tot['out_ac_salescost_total']	+= $acinsdata['out_ac_salescost_total'];
							$tot['out_ac_cash_use']			+= $acinsdata['out_ac_cash_use'];
							$tot['out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
							$tot['out_pg_add_price']		+= $acinsdata['out_pg_add_price'];
							$tot['out_sales_unit_feeprice']	+= $acinsdata['out_sales_unit_feeprice'];
							$tot['out_commission_price']	+= $acinsdata['out_commission_price'];

							$tot['out_ac_salescost_admin']		+= $acinsdata['out_ac_salescost_admin'];
							$tot['out_ac_salescost_provider']	+= $acinsdata['out_ac_salescost_provider'];

							$tot['out_ac_consumer_real_price']	+= $acinsdata['out_ac_consumer_real_price'];
							$tot['out_ac_profit_price']			+= $acinsdata['out_ac_profit_price'];

						}
					}
					$loop[] = $acinsdata;
			break;
			case "complete"://매출+정산 당월
				$tot['total_num']++;
				$tot['out_title']			= $acinsdata['out_title']	= "당월";
				$tot['out_num']				= '합계';
				$tot['out_total_title']		= 'start';
				//소계영역
				if( $acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback" || $acinsdata['account_type'] == "after_refund" ) {
					$acinsdata['minus_sale'] = '1';    // 매출 마이너스 표기 0:양수, 1:음수
					$tot['refund_out_consumer_price']		+= $acinsdata['out_consumer_price'];
					$tot['refund_out_sales_price']			+= $acinsdata['out_sales_price'];
					$tot['refund_salescost_total']			+= $acinsdata['salescost_total'];
					$tot['refund_pg_sale_price']			+= $acinsdata['pg_sale_price'];
					$tot['refund_pg_support_price']			+= $acinsdata['pg_support_price'];
					$tot['refund_out_pg_add_price']			+= $acinsdata['out_pg_add_price'];

					//통합매출 소계
					//if($acinsdata['ac_type'] == "sales"){ -> rollback 차감시 필요 @2017-12-05
						$tot['refund_out_ea']					+= $acinsdata['out_ea'];
						$tot['refund_out_price']				+= $acinsdata['out_price'];
						$tot['refund_out_salescost_total']		+= $acinsdata['out_salescost_total'];
						$tot['refund_out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
						$tot['refund_out_cash_use']				+= $acinsdata['out_cash_use'];
						$tot['refund_out_pg_support_price']		+= $acinsdata['out_pg_support_price'];
						$tot['refund_out_sale_price']			+= $acinsdata['out_sale_price'];
					//}
						$tot['refund_out_exp_ea']				+= $acinsdata['out_exp_ea'];

					//통합정산 소계 refund_sales_unit_feeprice
					if($acinsdata['ac_type'] == "cal"){
						$tot['refund_out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
						$tot['refund_out_pg_default_price']		+= $acinsdata['out_pg_default_price'];
						$tot['refund_sales_unit_feeprice']		+= $acinsdata['out_sales_unit_feeprice'];
						$tot['refund_out_commission_price']		+= $acinsdata['out_commission_price'];
						$tot['refund_out_pg_support_price']		+= $acinsdata['out_pg_support_price'];
						$tot['refund_salescost_admin']			+= $acinsdata['out_salescost_admin'];
						$tot['refund_salescost_provider']		+= $acinsdata['out_salescost_provider'];
						$tot['refund_out_ac_salescost_total']	+= $acinsdata['out_salescost_total'];
						$tot['refund_out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
						$tot['refund_out_ac_cash_use']			+= $acinsdata['out_cash_use'];
						$tot['refund_out_ac_consumer_real_price']+= $acinsdata['out_ac_consumer_real_price'];
						$tot['refund_out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

					}
				}else{
					$tot['out_consumer_price']		+= $acinsdata['out_consumer_price'];
					$tot['out_sales_price']			+= $acinsdata['out_sales_price'];
					$tot['salescost_total']			+= $acinsdata['salescost_total'];
					$tot['pg_sale_price']			+= $acinsdata['pg_sale_price'];
					$tot['pg_support_price']		+= $acinsdata['pg_support_price'];

					//통합매출 소계
					//if($acinsdata['ac_type'] == "sales"){
						$tot['out_ea']					+= $acinsdata['out_ea'];
						$tot['out_price']				+= $acinsdata['out_price'];
						$tot['out_salescost_total']		+= $acinsdata['out_salescost_total'];
						$tot['out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
						$tot['out_cash_use']			+= $acinsdata['out_cash_use'];
						$tot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
						$tot['out_sale_price']			+= $acinsdata['out_sale_price'];
					//}

					//통합정산 소계
						$tot['out_exp_ea']				+= $acinsdata['out_exp_ea'];
					if($acinsdata['ac_type'] == "cal"){
						$tot['out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
						$tot['out_pg_default_price']	+= $acinsdata['out_pg_default_price'];
						$tot['out_ac_salescost_total']	+= $acinsdata['out_ac_salescost_total'];
						$tot['out_ac_cash_use']			+= $acinsdata['out_ac_cash_use'];
						$tot['out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
						$tot['out_pg_add_price']		+= $acinsdata['out_pg_add_price'];
						$tot['out_sales_unit_feeprice']	+= $acinsdata['out_sales_unit_feeprice'];
						$tot['out_commission_price']	+= $acinsdata['out_commission_price'];
						$tot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
						$tot['out_salescost_admin']		+= $acinsdata['out_salescost_admin'];
						$tot['out_salescost_provider']	+= $acinsdata['out_salescost_provider'];
						$tot['out_ac_salescost_admin']		+= $acinsdata['out_ac_salescost_admin'];
						$tot['out_ac_salescost_provider']	+= $acinsdata['out_ac_salescost_provider'];
						$tot['out_ac_consumer_real_price']	+= $acinsdata['out_ac_consumer_real_price'];
						$tot['out_ac_profit_price']			+= $acinsdata['out_ac_profit_price'];

					}

				}
				$loop[] = $acinsdata;
			break;
		}

		$acinsdata = array();
		$this->db->queries = array();
		$this->db->query_times = array();
	}

	/**
	* 정산마감일 설정
	*
	**/
	public function accountall_setting() {
		$this->admin_menu();
		$this->tempate_modules();
		$accountallNextConfirm	= $this->accountallmodel->get_account_setting('last');
		$accountallNowConfirm	= $this->accountallmodel->get_account_setting('pre');
		$sellerAccCycle			= $this->accountallmodel->get_provider_calcu_cnt('pre');

		/*
		* 마이그레이션 날짜 가져오기 시작
		*/
		$_accountSettings			= getAccountSetting('accountall_migration_date');
		$migrationCheckDate			= $_accountSettings['migrationCheckDate'];
		/*
		* 마이그레이션 날짜 가져오기 끝
		*/

		// 신규 정산을 이용할 경우 마이그레이션 다음 달부터 적용되도록 처리 :: 2018-08-23 lkh
		$migration_patch = false;
		if($migrationCheckDate > date("Y-m")){
			$migration_patch = true;
		}

		// 월 계산
		$accountallNowConfirm['settingmonth'] = $accountallNowConfirm['month'] = date("m");
		$accountallNowConfirm['nextmonth'] = date("m",strtotime(date("Y-m").'+1 month'));
		if(substr($accountallNextConfirm['regist_date'],0,7) > substr($accountallNowConfirm['regist_date'],0,7)){
			$accountallNextConfirm['month'] = date("m",strtotime(date("Y-m").'+1 month'));
		}else{
			$accountallNowConfirm['month'] = "매";
			$accountallNextConfirm = array();
		}

		$this->template->assign(array(
							'acc_next_confirm'=>$accountallNextConfirm,
							'acc_now_confirm'=>$accountallNowConfirm,
							'seller_acc_cycle'=>$sellerAccCycle,
							'migration_patch'=>$migration_patch
							));
		$this->template->define(array('tpl' => $this->template_path()));
		$this->template->print_("tpl");
	}

	/**
	* 정산마감일 변경 이력
	*
	**/
	public function accountall_setting_catalog() {
		$this->admin_menu();
		$this->tempate_modules();

		if(!$this->managermodel) $this->load->model("managermodel");
		$managerList = $this->managermodel->getAccountSettingManagerList();

		$sc = $_GET;
		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'seq';
		$sc['sort']				= (isset($_GET['sort'])) ?		$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):0;
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):5;

		$data = $this->accountallmodel->get_account_setting_list($sc);

		### PAGE & DATA
		$sc['totalcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['totalcount']	 / $sc['perpage']);

		$dataLoop = array();
		foreach($data['result'] as $dataRow){
			$idx++;
			$dataRow['number']					= $sc['totalcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			if($dataRow['manager_seq'] > 0){
				$dataRow['manager_name']		= $managerList[$dataRow['manager_seq']]['mname'];
			}else{
				$dataRow['manager_name']		= "시스템";
			}
			if($dataRow['regist_date'] && $dataRow['regist_date'] != "0000-00-00 00:00:00"){
				$rowM = substr($dataRow['regist_date'],0,7);
				$rowDate = date("Y-m", strtotime($rowM."+1 month"));
				$rowDateArr = explode("-",$rowDate);
				$dataRow['accountall_cofirm_text']	= $rowDateArr[0]."년 ".$rowDateArr[1]."월 매출";
			}elseif($dataRow['manager_seq'] == "0" && $dataRow['regist_date'] == "0000-00-00 00:00:00"){
				$dataRow['accountall_cofirm_text']	= "매월 매출";
			}
			$dataLoop[] = $dataRow;
		}

		$paginlay = pagingtag($sc['totalcount'],$sc['perpage'],'javascript:settingPaging(\'',getLinkFilter('',array_keys(array())).'\');' );

		$paginlay = str_replace("&amp;", "&", $paginlay);

		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';

		$this->template->assign('pagin',$paginlay);
		$this->template->assign('sc',$sc);
		$this->template->assign(array('loop'=>$dataLoop));
		$this->template->define(array('tpl' => $this->template_path()));
		$this->template->print_("tpl");
	}

	/**
	* 결제수수료 - 검수용 페이지
	* 차후 제거
	**/
	public function account_order_ea() {

		if( $_GET['accountall_excel'] ) {
			ini_set("memory_limit",-1);
			//xls출력용 추가
			header( "Content-type: application/vnd.ms-excel;charset=UTF-8");
			header( "Expires: 0" );
			header( "Cache-Control: must-revalidate, post-check=0,pre-check=0" );
			header( "Pragma: public" );
			$filename = ($_GET['order_referer'])?$_GET['order_referer']:'all';
			$filename .= "_".$_GET['s_year'].$_GET['s_month'];
			header( "Content-Disposition: attachment; filename=".$filename."_account_list_test.xls" ); 
		}else{
			$this->admin_menu();
			$this->tempate_modules();
		}
		if(!$_GET['account_excel']) $_GET['account_excel'] = 'account_deposit_excel';
		$file_path	= $this->template_path();
 
		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_GET['regist_date']) == 0 ){
			$_GET['regist_date'][0] = date("Y-m-01");//'2017-08-01';
			$_GET['regist_date'][1] = date("Y-m-d");//'2017-08-31';
		}
		if(!$_GET['chk_step']) {
			if($_GET['account_excel']) {
			}else{
				if( $_GET['order_seq'] ) {
					$chk_stepar	= array('25','35','40','45','50','55','60','65','70','75','85');
				}else{
					$chk_stepar	= array('75');
				}
				$chk_stepar	= array('15','25','35','40','45','50','55','60','65','70','75');//,'85'
				foreach($chk_stepar as $k)$_GET['chk_step'][$k] = $k;
			}
		}
		$order_referer = ($_GET['order_referer'])?trim($_GET['order_referer']):'';
		$_GET['date_field'] = ($_GET['date_field'])?trim($_GET['date_field']):'deposit_date';
		if($order_referer){
			$paymentar = account_order_referer_payment($order_referer);
			foreach($paymentar as $k) $_GET['payment'][$k] = $k;
			$order_referer_pg = account_order_referer_title($order_referer);
		}

		$order_seq = trim($_GET['order_seq']);
		if($_GET['ac_order_seq']) $order_seq = trim($_GET['ac_order_seq']);
		$_PARAM			= $_GET;

		$_PARAM['nperpage']		= '100000';
		$_PARAM['nolimit']		= 'y';
		$query	= $this->accountallmodel->get_order_catalog_query($_PARAM);//debug_var($this->db->last_query());
		if($query) foreach($query->result_array() as $k => $acinsdata){ 
			if( $_GET['account_excel'] == 'account_deposit_excel' || $_GET['account_excel'] == 'buy_confirm' ){
				$account_deposit_excel['confirm_date_as']	= $acinsdata['confirm_date_as'];
				$account_deposit_excel['deposit_date_as']	= $acinsdata['deposit_date_as'];
				$account_deposit_excel['order_seq']			= $acinsdata['order_seq'];
				$account_deposit_excel['settleprice']		= $acinsdata['settleprice'];
				$loop[] = $account_deposit_excel;
			}else{
				//if($acinsdata['step'] == '15' || $acinsdata['step'] == '95' || $acinsdata['step'] == '99') continue;
			
				//적립금/캐쉬/에누리 사용시 금액별 자동처리
				if( $acinsdata['emoney']>0 || $acinsdata['cash']>0 || $acinsdata['enuri']>0 || $acinsdata['npay_point']>0) {
					//step1 주문금액별 정의/비율/단가계산 후 정렬
					$set_order_price_ratio = $this->accountallmodel->set_order_price_ratio($acinsdata['order_seq']);//debug_var($set_order_price_ratio);
					//step2 적립금/이머니 update
					$this->accountallmodel->update_ratio_emoney_cash_enuri_npoint($acinsdata['order_seq'], $set_order_price_ratio,'all',$acinsdata);
				}

				//주문 공통정보 정의 시작
				$acinsdata['order_regist_date']	= substr($acinsdata['regist_date'],0,10);//수집일(주문일)
				$acinsdata['shipping_date']		= substr($acinsdata['shipping_date'],0,10);//배송완료일
				$acinsdata['complete_date']		= substr($acinsdata['complete_date'],0,10);//출고완료일자
				$acinsdata['confirm_date']		= substr($acinsdata['confirm_date'],0,10);//출고완료일자
				

				$acinsdata['order_member_seq']	= $acinsdata['member_seq'];
				$acinsdata['order_user_name']	= $acinsdata['order_user_name'];
				$acinsdata['order_step']		= $acinsdata['step'];
				$acinsdata['payment']			= $acinsdata['payment'];
				$acinsdata['pgcompany']			= $this->config_system['pgCompany'];
				$acinsdata['pg']				= (!$acinsdata['pg'] && $acinsdata['payment']== 'bank')?'bank':$acinsdata['pg'];
				if($acinsdata['npay_order_id']) {
					unset($acinsdata['order_referer_npay']);
					if($acinsdata['payment_type']) {
						if(preg_match('/네이버결제/',$acinsdata['payment_type'])){
							$acinsdata['order_referer_npay']	= 'npay';
						}else{
							$acinsdata['order_referer_npay']	= 'npg';
						}
					}else{
						$npay_log 		= $this->ordermodel->get_log($acinsdata['order_seq'],'pay',array("add_info"=>"npay"));
						$npay_log_tmp = array();
						foreach($npay_log as $log){
							if(!preg_match('/간편결제/',$log['title']) && $log['title']){
								$acinsdata['order_referer_npay'] = "npg";
								break;
							}
						}
						if(!$acinsdata['order_referer_npay']) $acinsdata['order_referer_npay'] = 'npay';
					}
				}

				$acinsdata['order_referer']			= account_order_referer($acinsdata['pg'], $acinsdata);
				$acinsdata['order_referer_viewer']	= account_order_referer_title($acinsdata['pg'], $acinsdata);

				$acinsdata['pg_ordernum']			= $acinsdata['pg_transaction_number'];//PG거래번호
				$acinsdata['pg_ordernum_approval']	= $acinsdata['pg_approval_number'];//PG승인번호

				$acinsdata['mpayment']			= $this->arr_payment[$acinsdata['payment']];

				//결제수단별 수수료율
				unset($charge);
				$charge	= $this->accountallmodel->get_fee_info($acinsdata['order_referer'], $acinsdata);
				$paycharge = $charge['data'];
				if( $acinsdata['npay_point']>0) {
					$npay_point_charge	= $this->accountallmodel->get_fee_info('npay', array("payment"=>"point"));
					$this->npay_point_paycharge = $npay_point_charge['data'];
				}

				$acinsdata['mstatus'] 			= $this->arr_step[$acinsdata['step']];
				/**
				$acinsdata['out_ac_pg_price']			= $acinsdata['pg_price'];			//PG정산_결제금액
				$acinsdata['out_pg_commission']		= $acinsdata['pg_commission'];		//PG정산_결제수수료
				$acinsdata['out_pg_default_price']	= $acinsdata['pg_default_price'];	//PG정산_주결제수단금액
				$acinsdata['out_pg_default_comm']	= $acinsdata['pg_default_comm'];	//PG정산_주결제수단수수료
				$acinsdata['out_pg_support_price']	= $acinsdata['pg_support_price'];	//PG정산_보조결제수단금액
				$acinsdata['out_pg_support_comm']	= $acinsdata['pg_support_comm'];	//PG정산_보조결제수단수수료
				$acinsdata['out_pg_connect_comm']	= $acinsdata['pg_connect_comm'];	//PG정산_매출연동수수료
				$acinsdata['out_pg_account']		= $acinsdata['pg_account'];			//PG정산_정산예정금액
				$acinsdata['out_pg_sale_price']		= $acinsdata['pg_sale_price'];		//할인 본사/제휴사
				**/

				//임시정산 공통정보 정의 완료

				/**
				* 상품 필수/추가 옵션정보 시작
				**/
				$items 				= $this->ordermodel->get_item($acinsdata['order_seq']);
				foreach($items as $key=>$item){
					/**
					* 입점사정보 한번만 가져오기
					**/
					if(!$data_provider[$item['provider_seq']]) 
						$data_provider[$item['provider_seq']] = $this->providermodel->get_provider($item['provider_seq']);

					$acinsdata['shipping_seq']		= $item['shipping_seq'];
					$acinsdata['provider_seq']		= $item['provider_seq'];
					$acinsdata['goods_code']		= $item['goods_code'];
					$acinsdata['item_seq']			= $item['item_seq'];
					$acinsdata['order_goods_seq']	= $item['goods_seq'];
					$acinsdata['order_goods_name']	= $item['goods_name'];
					$acinsdata['order_goods_kind']	= $item['goods_kind'];

					if($data_provider[$item['provider_seq']]) {
						$acinsdata['provider_id']	 = $data_provider[$item['provider_seq']]['provider_id'];
						$acinsdata['provider_name'] = $data_provider[$item['provider_seq']]['provider_name'];
					}
					
					//필수옵션
					$data_options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
					//추가옵션
					$data_suboptions = $this->ordermodel->get_suboption_for_item($item['item_seq']);
					//배송비
					$data_shipping	= $this->ordermodel->get_order_shipping($item['order_seq'],$item['provider_seq'],$item['shipping_seq']);

					//환불정보
					$data_refund = $this->excelaccountmodel->gmarket_get_refund_for_order_item($item['order_seq'],$item['item_seq']);
					if($data_refund){
						if($data_refund[0]['status'] == 'complete' && $data_refund[0]['refund_date']) 
							$acinsdata['refund_date']			= $data_refund[0]['refund_date'];
					}
					//반품배송비 정보(결제수수료 반품기능 없음)
					$data_return = $this->excelaccountmodel->gmarket_get_return_for_order_item($item['order_seq'],$item['item_seq'],"return");

					//출고정보가져오기
					if(!($acinsdata['step'] == '15' || $acinsdata['step'] == '95' || $acinsdata['step'] == '99' || $acinsdata['step'] == '85')) {
						$items_export = $this->accountallmodel->get_items_export_complete_code_group($acinsdata['order_seq'],$item['item_seq']);
						if($items_export) {
							if(count($items_export)>1) {//부분출고시 출고건수만큼 처리필요(결제수수료에서만 예외처리)
								//debug_var($acinsdata['order_seq']." count(items_export) = ".count($items_export));
							}else{
								$acinsdata['export_code']			= $items_export[0]['export_code'];
								$acinsdata['shipping_date']			= $items_export[0]['shipping_date'];
								$acinsdata['complete_date']			= $items_export[0]['complete_date'];
								$acinsdata['shipping_provider_seq']	= $items_export[0]['shipping_provider_seq'];//배송그룹사 번호
								$acinsdata['confirm_date']			= $items_export[0]['confirm_date'];//배송완료=>구매확정 동일처리(기획)
							}
						}
					}

					/**
					* 필수옵션정보
					**/
					if($data_options) foreach($data_options as $optk => $optdata){
						$optdata = account_ins_option_ck($acinsdata,$optdata,$paycharge);

						/**
						정산계산식 수동처리
						
						$optdata['sales_price']	= ($optdata['price']*$optdata['ea']);		//총 결제금액
						$optdata['sell_price']	= ($optdata['org_price']*$optdata['ea']);	//총 판매금액
						
						$acc_unit_mincharge = 0;
						if($paycharge['min_fee'] > 0){
							$tmp_acc_unit_mincharge = $paycharge['min_fee'] * $optdata['sale_ratio'];
							$acc_unit_mincharge		= acc_string_floor($tmp_acc_unit_mincharge / $optdata['ea'],$acinsdata['pg']);
						}
						$acc_unit_payprice_tmp		= $optdata['sales_price'] - $optdata['salescost_admin'];
						$acc_unit_payprice			= $acc_unit_payprice_tmp / $optdata['ea']; //■ B 실 결제액 (개당)
						$sales_unit_feeprice		= acc_string_floor($acc_unit_payprice * $paycharge['commission_rate'] / 100,$acinsdata['pg']); //■ C 수수료
						$acc_charge_str = "";
						if( $paycharge['commission_rate'] > 0)					$acc_charge_str	.= $paycharge['commission_rate']."%";
						if( $acc_charge_str !='' && $paycharge['min_fee'] > 0)	$acc_charge_str	.= " + ";
						if( $paycharge['min_fee'] > 0)							$acc_charge_str	.= $paycharge['min_fee']."원 (개당 {$acc_unit_mincharge}원)";

						$tot_commission_unit				= $sales_unit_feeprice + $acc_unit_mincharge + $optdata['salescost_provider'];
						$commission_price_unit				= $acc_unit_payprice - $tot_commission_unit;	// 임시정산 - 수수료합계
						$acc_payprice						= $commission_price_unit * $optdata['ea'];		//■ E 수량적용된 정산금액
						$comm_all[]							= $acc_payprice; 								//짜투리 알아내기 위해서 
						$optdata['commission_price']		= $acc_payprice;								//수량적용된 정산금액
						$optdata['commission_price_unit']	= $acc_payprice / $optdata['ea'];				//단품 정산금액
						//$optdata['commission_price_rest']	= $acc_payprice_rest;							//단품 정산금액-짜투리
						$optdata['sales_unit_feeprice']		= $sales_unit_feeprice;							//정산 수수료금액-단가
						$optdata['sales_unit_minfee']		= $acc_unit_mincharge;							//정산 추가수수료 단가(+)
						$optdata['sales_unit_payprice']		= $acc_unit_payprice;							//실 결제액 (개당)
						
						$optdata['commission_text']			= $acc_charge_str." => ".$acc_unit_payprice.
																"-".$sales_unit_feeprice.
																"-".$acc_unit_mincharge.
																"-".$optdata['salescost_provider'].
																"=".$commission_price_unit;			//정산계산식 상세설명**/
						/**
						정산계산식 수동처리
						**/
						
						$optdata['out_supply_price']	= $optdata['supply_price']*$optdata['ea'];		//매입가
						$optdata['out_consumer_price']	= $optdata['consumer_price']*$optdata['ea'];	//정가
						$optdata['out_price']			= $optdata['price']*$optdata['ea'];				//판매가
						
						//Npay 상품 할인액(Npay부담) # npay 쿠폰할인(네이버페이 부담=상품별 할인액-판매자 부담 할인액)
						$optdata['out_npay_sale_npay']		= $optdata['npay_sale_npay'];
						//Npay 상품 할인액(판매자부담) : # npay 할인(배송비 할인 + 상품별 할인 - 네이버페이 부담 상품할인액)
						$optdata['out_npay_sale_seller']	= $optdata['npay_sale_seller'];
						$optdata['out_price']				= $acc_unit_payprice*$optdata['ea'];
						$optdata['out_pg_sale_price']		= $optdata['out_npay_point_use'];		//매출 > 쿠폰(마일리지) 할인 > 제휴사 -> Npay Point
						$optdata['out_pg_support_price']	= $optdata['pg_support_price'];			//매출 > 판매자 추가할인

						$optdata['out_tot_sale']			= ($optdata['out_salescost_admin'] + $optdata['out_npay_point_use'] + $optdata['out_cash_use']+ $optdata['out_pg_support_price']);//쿠폰본사+제휴사+이머니+판매추가할인
						$optdata['out_sale_price']			= $optdata['sales_price'] - $optdata['out_tot_sale'];//결제금액
						$optdata['out_sales_price']			= $optdata['sales_price'];
						$optdata['out_pg_default_price']	= $optdata['out_sale_price'];			//정산대상금액(A)>PG 결제금액
						$optdata['out_pg_admin_price']		= $optdata['salescost_admin'];		//정산대상금액(A)>본사
						$optdata['out_ac_pg_price']			= $optdata['out_npay_point_use'];		//정산대상금액(A)>제휴사 -> Npay Point
						$optdata['out_pg_add_price']		= $optdata['pg_add_price'];				//정산대상금액(A)>추가할인	
						$optdata['out_total_ac_price']		= $optdata['out_pg_default_price']
																+ $optdata['salescost_provider']
																+ $optdata['out_pg_admin_price'];	//정산대상금액-합계
						$optdata['out_ac_cash_use']			= $optdata['out_cash_use'];				//정산대상금액(A)>이머니 캐쉬
					 
						$tmp = $this->returnmodel->get_return_item_ea($optdata['item_seq'],$optdata['item_option_seq'],null,'return');
						if($tmp) $optdata['return_list_ea'] = $tmp['ea'];
						$tmp = $this->refundmodel->get_refund_option_ea($optdata['item_seq'],$optdata['item_option_seq'],"return");
						if($tmp) $optdata['refund_list_ea'] = $tmp;
						$tmp = $this->returnmodel->get_return_item_ea($optdata['item_seq'],$optdata['item_option_seq'],null,'exchange');
						if($tmp) $optdata['exchange_list_ea'] = $tmp['ea'];

						$ac_succes					= false;
						$optdata['ac_all_ea'] = ($optdata['step25']
													+ $optdata['step35']
													+ $optdata['step45']
													+ $optdata['step55']
													+ $optdata['step65']
													+ $optdata['step75']
													+ $optdata['step85']);
						$optdata['ac_ceancel_ea'] = ($optdata['refund_list_ea']
														 + $optdata['return_list_ea']
														 + $optdata['exchange_list_ea']);
						$optdata['ac_succes_ea'] = ($optdata['ac_all_ea'] - $optdata['ac_ceancel_ea']);
						if( ($optdata['ea'] == $optdata['ac_all_ea'] && $optdata['ac_ceancel_ea'] < 1 ) 
							|| ($optdata['ea'] == $optdata['ac_all_ea'] && $optdata['ea'] == $optdata['ac_ceancel_ea']) ){
							$ac_succes					= true;
						}
						$optdata['ac_succes_viewer']		= ($ac_succes)?"대상":"미대상";

						//소계영역
						$tot['out_price']				+= $optdata['out_price'];
						$tot['out_sales_price']			+= $optdata['out_sales_price'];
						$tot['out_salescost_admin']		+= $optdata['out_salescost_admin'];
						$tot['pg_sale_price']			+= $optdata['pg_sale_price'];
						$tot['out_cash_use']			+= $optdata['out_cash_use'];
						$tot['pg_support_price']		+= $optdata['pg_support_price'];
						$tot['out_sale_price']			+= $optdata['out_sale_price'];

						$tot['out_pg_sale_price']		+= $optdata['out_pg_sale_price'];
						$tot['out_pg_support_price']	+= $optdata['out_pg_support_price'];
						$tot['out_pg_default_price']	+= $optdata['out_pg_default_price'];
						$tot['out_pg_admin_price']		+= $optdata['out_pg_admin_price'];
						$tot['out_ac_pg_price']			+= $optdata['out_ac_pg_price'];
						$tot['out_ac_cash_use']			+= $optdata['out_ac_cash_use'];
						$tot['out_pg_add_price']		+= $optdata['out_pg_add_price'];

						$tot['out_total_ac_price']		+= $optdata['out_total_ac_price'];
						$tot['sales_unit_feeprice']		+= $optdata['sales_unit_feeprice']*$optdata['ac_all_ea'];
						$tot['commission_price']		+= $optdata['commission_price'];

						$optdata = array_merge($acinsdata,$optdata);
						$loop[] = $optdata;
					}//endforeach

					/**
					* 추가옵션정보
					**/
					if($data_suboptions) foreach($data_suboptions as $suboptk => $subdata){
						$subdata = account_ins_suboption_ck($acinsdata,$subdata, $paycharge);
					
						/**
						정산계산식 수동처리
						**/
						$subdata['sales_price']	= ($subdata['price']*$subdata['ea']);			//총 결제금액
						$subdata['sell_price']	= ($subdata['org_price']*$subdata['ea']);		//총 판매금액
						/**
						$acc_unit_mincharge = 0;
						if($paycharge['min_fee'] > 0){
							$tmp_acc_unit_mincharge = $paycharge['min_fee'] * $subdata['sale_ratio'];
							$acc_unit_mincharge = acc_string_floor($tmp_acc_unit_mincharge / $subdata['ea'],$acinsdata['pg']);
						}
						$acc_unit_payprice_tmp			= $subdata['sales_price'] - $subdata['salescost_admin'];
						$acc_unit_payprice				= $acc_unit_payprice_tmp / $subdata['ea']; //■ B 실 결제액 (개당)
						$sales_unit_feeprice			= acc_string_floor($acc_unit_payprice * $paycharge['commission_rate'] / 100,$acinsdata['pg']); //■ C 수수료
						$acc_charge_str = "";
						if( $paycharge['commission_rate'] > 0)								$acc_charge_str	.= $paycharge['commission_rate']."%";
						if( $acc_charge_str !='' && $paycharge['min_fee'] > 0)	$acc_charge_str	.= " + ";
						if( $paycharge['min_fee'] > 0)							$acc_charge_str	.= $paycharge['min_fee']."원 (개당 {$acc_unit_mincharge}원)";

						$tot_commission_unit			= $sales_unit_feeprice + $acc_unit_mincharge + $subdata['salescost_provider'];
						$commission_price_unit			= $acc_unit_payprice - $tot_commission_unit;	// 임시정산 - 수수료합계
						$acc_payprice					= $commission_price_unit * $subdata['ea'];		//■ E 수량적용된 정산금액
						$comm_all[]						= $acc_payprice;								//짜투리 알아내기 위해서 	
						$subdata['commission_price']		= $acc_payprice;							//수량적용된 정산금액
						$subdata['commission_price_unit']	= $acc_payprice / $subdata['ea'];			//단품 정산금액
						$subdata['commission_price_rest']	= $acc_payprice_rest;						//단품 정산금액-짜투리
						$subdata['sales_unit_feeprice']		= $sales_unit_feeprice;						//정산 수수료금액-단가
						$subdata['sales_unit_minfee']		= $acc_unit_mincharge;						//정산 추가수수료 단가(+)
						$subdata['sales_unit_payprice']		= $acc_unit_payprice;						//실 결제액 (개당)

						$subdata['commission_text']			= $acc_charge_str." => ".$acc_unit_payprice.
																"-".$sales_unit_feeprice.
																"-".$acc_unit_mincharge.
																"-".$subdata['salescost_provider'].
																"=".$commission_price_unit;				//정산계산식 상세설명
						**/
						/**
						정산계산식 수동처리
						**/
						
						$subdata['out_supply_price']	= $subdata['supply_price']*$subdata['ea'];		//매입가
						$subdata['out_consumer_price']	= $subdata['consumer_price']*$subdata['ea'];	//정가
						$subdata['out_price']			= $subdata['price']*$subdata['ea'];				//판매가

						//Npay 상품 할인액(Npay부담) # npay 쿠폰할인(네이버페이 부담=상품별 할인액-판매자 부담 할인액)
						$subdata['out_npay_sale_npay']		= $subdata['npay_sale_npay'];
						//Npay 상품 할인액(판매자부담) : # npay 할인(배송비 할인 + 상품별 할인 - 네이버페이 부담 상품할인액)
						$subdata['out_npay_sale_seller']	= $subdata['npay_sale_seller'];
						$subdata['out_price']				= $acc_unit_payprice*$subdata['ea'];
						$subdata['out_pg_sale_price']		= $subdata['out_npay_point_use'];			//매출 > 쿠폰(마일리지) 할인 > 제휴사 -> Npay Point
						$subdata['out_pg_support_price']	= $subdata['pg_support_price'];				//매출 > 판매자 추가할인
					
						$subdata['out_tot_sale']			= ($subdata['out_salescost_admin'] + $subdata['out_npay_point_use'] + $subdata['out_cash_use']+ $subdata['out_pg_support_price']);//쿠폰본사+제휴사+이머니+판매추가할인
						$subdata['out_sale_price']		= $subdata['sales_price'] - $subdata['out_tot_sale'];//결제금액
						$subdata['out_sales_price']			= $subdata['sales_price'];	
						$subdata['out_pg_default_price']	= $subdata['out_sale_price'];			//정산대상금액(A)>PG 결제금액
						$subdata['out_pg_admin_price']		= $subdata['salescost_admin'];			//정산대상금액(A)>본사
						$subdata['out_ac_pg_price']			= $subdata['out_npay_point_use'];		//정산대상금액(A)>제휴사 -> Npay Point
						$subdata['out_pg_add_price']		= $subdata['pg_add_price'];				//정산대상금액(A)>추가할인	
						$subdata['out_total_ac_price']		= $subdata['out_pg_default_price']
																+ $subdata['salescost_provider']
																+ $subdata['out_pg_admin_price'];	//정산대상금액-합계
						$subdata['out_ac_cash_use']			= $subdata['out_cash_use'];				//정산대상금액(A)>이머니 캐쉬
					
						$tmp = $this->returnmodel->get_return_subitem_ea($subdata['item_seq'],$subdata['item_suboption_seq'],null,'return');
						$subdata['return_list_ea'] = $tmp['ea'];
						$tmp = $this->refundmodel->get_refund_suboption_ea('',$subdata['item_seq'],$subdata['item_suboption_seq'],"return");
						$subdata['refund_list_ea'] = $tmp;
						$tmp = $this->returnmodel->get_return_subitem_ea($subdata['item_seq'],$subdata['item_suboption_seq'],null,'exchange');
						$subdata['exchange_list_ea'] = $tmp['ea'];
						
						$ac_succes					= false;
						$subdata['ac_all_ea'] = ($subdata['step25']
												+ $subdata['step35']
												+ $subdata['step45']
												+ $subdata['step55']
												+ $subdata['step65']
												+ $subdata['step75']
												+ $subdata['step85']);
						$subdata['ac_ceancel_ea'] = ($subdata['refund_list_ea']
														 + $subdata['return_list_ea']
														 + $subdata['exchange_list_ea']);
						$subdata['ac_succes_ea'] = ($subdata['ac_all_ea'] - $subdata['ac_ceancel_ea']);
						if( ($subdata['ea'] == $subdata['ac_all_ea'] && $subdata['ac_ceancel_ea'] < 1 ) 
							|| ($subdata['ea'] == $subdata['ac_all_ea'] && $subdata['ea'] == $subdata['ac_ceancel_ea']) ){
							$ac_succes					= true;
						}
						$subdata['ac_succes_viewer']		= ($ac_succes)?"대상":"미대상";

						//소계영역
						$tot['out_price']				+= $subdata['out_price'];
						$tot['out_sales_price']			+= $subdata['out_sales_price'];
						$tot['out_salescost_admin']		+= $subdata['out_salescost_admin'];
						$tot['pg_sale_price']			+= $subdata['pg_sale_price'];
						$tot['out_cash_use']			+= $subdata['out_cash_use'];
						$tot['pg_support_price']		+= $subdata['pg_support_price'];
						$tot['out_sale_price']			+= $subdata['out_sale_price'];

						$tot['out_pg_sale_price']		+= $subdata['out_pg_sale_price'];
						$tot['out_pg_support_price']	+= $subdata['out_pg_support_price'];
						$tot['out_pg_default_price']	+= $subdata['out_pg_default_price'];
						$tot['out_pg_admin_price']		+= $subdata['out_pg_admin_price'];
						$tot['out_ac_pg_price']			+= $subdata['out_ac_pg_price'];
						$tot['out_ac_cash_use']			+= $subdata['out_ac_cash_use'];
						$tot['out_pg_add_price']		+= $subdata['out_pg_add_price'];

						$tot['out_total_ac_price']		+= $subdata['out_total_ac_price'];
						$tot['sales_unit_feeprice']		+= $subdata['sales_unit_feeprice']*$subdata['ac_all_ea'];
						$tot['commission_price']		+= $subdata['commission_price'];

						$subdata = array_merge($acinsdata,$subdata);
						$loop[] = $subdata;
					}//endforeach
					
					/**
					* 배송정보 시작
					**/
					if($data_shipping) foreach($data_shipping as $key=>$shipping){
						if($data_provider[$item['shipping_seq']][$key]) continue;
						$shipping = account_ins_shipping_ck($acinsdata,$shipping,$paycharge);

						/**
						정산계산식 수동처리
						**/
						// 배송비합 : 일반 + 개별
						unset($shipping_tot);
						if($shipping['shipping_method']=='delivery'){
							$shipping_tot['basic_cost']				= $shipping['delivery_cost'];
							$shipping_tot['add_shipping_cost']		= $shipping['add_delivery_cost'];
							//$shipping_tot['shipping_cost']			= $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
						}

						if($shipping['shipping_method']=='each_delivery'){
							$shipping_tot['goods_cost']				= $shipping['delivery_cost'];
							$shipping_tot['add_shipping_cost']		= $shipping['add_delivery_cost'];
							//$shipping_tot['goods_shipping_cost']	= $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
						}
						$shipping['sales_price'] = $shipping_tot['basic_cost'] + $shipping_tot['goods_cost'] + $shipping_tot['add_shipping_cost'];
						$shipping['sell_price']	= $shipping['sales_price'];

						$acc_unit_mincharge = 0;
						if($paycharge['min_fee'] > 0){
							$tmp_acc_unit_mincharge	= $paycharge['min_fee'] * $shipping['sale_ratio'];//$shipping['sales_price'] / $account_tot;
							$acc_unit_mincharge		= acc_string_floor($tmp_acc_unit_mincharge / $shipping['ea'],$acinsdata['pg']);
						}
						$acc_emoney_cash_enuri_tmp	= ($shipping['out_emoney_use']) + ($shipping['out_cash_use']) + ($shipping['out_enuri_use']);
						$acc_unit_payprice_tmp		= $shipping['sales_price'] - $acc_emoney_cash_enuri_tmp;
						$acc_unit_payprice			= $acc_unit_payprice_tmp / $shipping['ea']; //■ B 실 결제액 (개당)
						$sales_unit_feeprice		= acc_string_floor($acc_unit_payprice * $paycharge['commission_rate'] / 100,$acinsdata['pg']); //■ C 수수료
						$acc_charge_str = "";
						if( $paycharge['commission_rate'] > 0)					$acc_charge_str	.= $paycharge['commission_rate']."%";
						if( $acc_charge_str !='' && $paycharge['min_fee'] > 0)	$acc_charge_str	.= " + ";
						if( $paycharge['min_fee'] > 0)							$acc_charge_str	.= $paycharge['min_fee']."원 (개당 {$acc_unit_mincharge}원)";

						$sales_provider					= $shipping['shipping_coupon_sale'] 
														  + $shipping['shipping_promotion_code_sale'];
						$tot_commission_unit			= $sales_unit_feeprice + $acc_unit_mincharge + $sales_provider;

						$commission_price_unit			= $acc_unit_payprice - $tot_commission_unit; // 임시정산 - 수수료합계
						$acc_payprice					= $commission_price_unit * $shipping['ea']; //■ E 수량적용된 정산금액
						$comm_all[]						= $acc_payprice; //짜투리 알아내기 위해서
						
						$shipping['price']				= $shipping['sales_price'];//상품단가
						$shipping['org_price']			= $shipping['sales_price'];//상품의 판매가
						$shipping['consumer_price']		= $shippiacc_payprice_tmp;//단품 정가
						//$shipping['supply_price']		= $shipping['sales_price'];//단품 매입가

						$shipping['commission_price']		= $acc_payprice;//수량적용된 정산금액
						$shipping['commission_price_rest']	= $acc_payprice_rest;//수량적용된 정산금액-짜투리
						//PG수수료율(6,3) 또는 수수료액 문구 
						$shipping['settle_charge_str']		= ($acinsdata['pg_payment_fee_txt'])?$acinsdata['pg_payment_fee_txt']:$acc_charge_str;
						//PG수수료율(6,3) 또는 수수료액 
						$shipping['settle_charge']			= ($acinsdata['pg_payment_fee'])?$acinsdata['pg_payment_fee']:$paycharge['commission_rate'];
						$shipping['sales_unit_feeprice']	= $sales_unit_feeprice;//PG수수료금액-단가
						$shipping['sales_unit_minfee']		= $acc_unit_mincharge;//PG수단별-추가수수료 단가(+)
						$shipping['sales_unit_payprice']	= $acc_unit_payprice;//실 결제액 (개당)
						$shipping['settle_commission_text'] = $acc_unit_payprice.
																"-".$sales_unit_feeprice.
																"-".$acc_unit_mincharge.
																"-".$sales_provider.
																"=".$commission_price_unit;//정산계산식 상세설명
						/**
						정산계산식 수동처리
						**/

						$data_provider[$shipping['shipping_seq']][$key] = $shipping;//배송그룹은 한번만 호출

						
						$shipping['out_supply_price']	= $shipping['sales_price'];		//매입가
						$shipping['out_consumer_price']	= $shipping['sales_price'];		//정가
						$shipping['out_price']			= $shipping['sales_price'];		//판매가

						$shipping['out_price']				= $acc_unit_payprice*$shipping['ea'];
						$shipping['out_pg_sale_price']		= $shipping['out_npay_point_use'];		//매출 > 쿠폰(마일리지) 할인 > 제휴사 -> Npay Point
						$shipping['out_pg_support_price']	= $shipping['pg_support_price'];		//매출 > 판매자 추가할인

						$shipping['out_tot_sale']			= ($shipping['out_salescost_admin'] + $shipping['out_npay_point_use'] + $shipping['out_cash_use']+ $shipping['out_pg_support_price']);//쿠폰본사+제휴사+이머니+판매추가할인
						$shipping['out_sale_price']			= $shipping['sales_price'] - $shipping['out_tot_sale'];
						if($this->uri->rsegments[2]=='account_order_ea'){//정산 검수페이지
							$shipping['out_sales_price']			= $shipping['sales_price'];
						}else{
							$shipping['out_sales_price']			= $shipping['sales_price']+$shipping['salescost_admin'];
						}

						$shipping['sale_price']				= $shipping['out_sale_price'] / $shipping['ea'];

						$shipping['out_pg_default_price']	= $shipping['out_sale_price'];				//정산대상금액(A)>PG 결제금액
						$shipping['out_pg_admin_price']		= $shipping['out_salescost_admin'];			//정산대상금액(A)>본사
						$shipping['out_ac_pg_price']			= $shipping['out_npay_point_use'];			//정산대상금액(A)>제휴사 -> Npay Point
						$shipping['out_pg_add_price']		= $shipping['pg_add_price'];				//정산대상금액(A)>추가할인	
						$shipping['out_total_ac_price']		= $shipping['out_pg_default_price']+$shipping['salescost_provider']+$shipping['out_pg_admin_price'];
						$shipping['out_ac_cash_use']		= $shipping['out_cash_use'];				//정산대상금액(A)>이머니 캐쉬


						$data_provider[$shipping['shipping_seq']][$key] = $shipping;//배송그룹은 한번만 호출

						/**
						정산계산식 수동처리
						**/
						if($shipping['out_price']) {
							//소계영역
							$tot['out_price']				+= $shipping['out_price'];
							$tot['out_sales_price']			+= $shipping['out_sales_price'];
							$tot['out_salescost_admin']		+= $shipping['out_salescost_admin'];
							$tot['pg_sale_price']			+= $shipping['pg_sale_price'];
							$tot['out_cash_use']			+= $shipping['out_cash_use'];
							$tot['pg_support_price']		+= $shipping['pg_support_price'];
							$tot['out_sale_price']			+= $shipping['out_sale_price'];

							$tot['out_pg_sale_price']		+= $shipping['out_pg_sale_price'];
							$tot['out_pg_support_price']	+= $shipping['out_pg_support_price'];
							$tot['out_pg_default_price']	+= $shipping['out_pg_default_price'];
							$tot['out_pg_admin_price']		+= $shipping['out_pg_admin_price'];
							$tot['out_ac_pg_price']			+= $shipping['out_ac_pg_price'];
							$tot['out_ac_cash_use']			+= $shipping['out_ac_cash_use'];
							$tot['out_pg_add_price']		+= $shipping['out_pg_add_price'];

							$tot['out_total_ac_price']		+= $shipping['out_total_ac_price'];
							$tot['sales_unit_feeprice']		+= $shipping['sales_unit_feeprice']*$shipping['ac_all_ea'];
							$tot['commission_price']		+= $shipping['commission_price'];
							//$tot['commission_price_rest']	+= $shipping['commission_price_rest'];
							$shipping = array_merge($acinsdata,$shipping);
							$loop[] = $shipping;
						}
					}//endforeach
					/**
					* 배송정보 끝
					**/

					/**
					* 환불정보 시작
					-> 옵션/추가옵션/배송비/추가배송비 환불
					**/
					if($data_refund){
						unset($refund_item);
						foreach($data_refund as $refunddata){
							if( $refunddata['refund_type'] != 'shipping_price' ) {
								$refunddata = account_ins_refund_ck($acinsdata,$refunddata,$paycharge);
								if($refunddata) {	
									$refunddata['out_sale_price']		= $refunddata['out_price'] - $refunddata['out_tot_sale'];
									$refunddata['out_sales_price']		= $sales_price+$refunddata['salescost_admin'];
									$refunddata['sale_price']			= ($refunddata['out_sale_price'] / $refunddata['ea']);
									
									$refunddata['out_pg_sale_price']	= 0;//매출 > 쿠폰(마일리지) 할인 > 제휴사
									$refunddata['out_pg_support_price']	= 0;//매출 > 판매자 추가할인
									$refunddata['out_pg_default_price']	= $refunddata['out_sale_price'];//정산대상금액(A)>PG 결제금액
									$refunddata['out_pg_admin_price']	= $refunddata['salescost_admin'];//정산대상금액(A)>본사
									$refunddata['out_ac_pg_price']			= 0;//정산대상금액(A)>제휴사
									$refunddata['out_pg_add_price']		= 0;//정산대상금액(A)>추가할인	
									$refunddata['out_total_ac_price']	= $refunddata['out_pg_default_price']+$refunddata['salescost_provider']+$refunddata['out_pg_admin_price'];
									//소계영역
									$tot['refund_out_price']				+= $refunddata['out_price'];
									$tot['refund_out_sales_price']			+= $refunddata['out_sales_price'];
									$tot['refund_out_salescost_admin']		+= $refunddata['out_salescost_admin'];
									$tot['refund_pg_sale_price']			+= $refunddata['pg_sale_price'];
									$tot['refund_out_cash_use']				+= $refunddata['out_cash_use'];
									$tot['refund_pg_support_price']			+= $refunddata['pg_support_price'];
									$tot['refund_out_sale_price']			+= $refunddata['out_sale_price'];

									$tot['refund_out_pg_sale_price']		+= $refunddata['out_pg_sale_price'];
									$tot['refund_out_pg_support_price']		+= $refunddata['out_pg_support_price'];
									$tot['refund_out_pg_default_price']		+= $refunddata['out_pg_default_price'];
									$tot['refund_out_pg_admin_price']		+= $refunddata['out_pg_admin_price'];
									$tot['refund_out_ac_pg_price']				+= $refunddata['out_ac_pg_price'];
									$tot['refund_out_ac_cash_use']			+= $refunddata['out_ac_cash_use'];
									$tot['refund_out_pg_add_price']			+= $refunddata['out_pg_add_price'];

									$tot['refund_out_total_ac_price']		+= $refunddata['out_total_ac_price'];
									$tot['refund_sales_unit_feeprice']		+= $refunddata['sales_unit_feeprice']*$refunddata['ac_all_ea'];
									$tot['refund_commission_price']			+= $refunddata['commission_price'];

									$refund_item = array_merge($acinsdata,$refunddata);
									$loop[] = $refund_item;
								}
							}

							if( $refunddata['refund_delivery_price']){//배송비환불영역 추가
								$refundshipping = account_ins_refundshipping_ck($acinsdata,$refunddata,$paycharge);
		
								$refundshipping['out_pg_sale_price']	= 0;//매출 > 쿠폰(마일리지) 할인 > 제휴사
								$refundshipping['out_pg_support_price']	= 0;//매출 > 판매자 추가할인
								$refundshipping['out_pg_default_price']	= $refundshipping['out_sale_price'];//정산대상금액(A)>PG 결제금액
								$refundshipping['out_pg_admin_price']	= $refundshipping['salescost_admin'];//정산대상금액(A)>본사
								$refundshipping['out_ac_pg_price']			= 0;//정산대상금액(A)>제휴사
								$refundshipping['out_pg_add_price']		= 0;//정산대상금액(A)>추가할인	
								$refundshipping['out_total_ac_price']	= $refundshipping['out_pg_default_price']+$refundshipping['salescost_provider']+$refundshipping['out_pg_admin_price'];

								//소계영역
								$tot['refund_out_price']			+= $refundshipping['out_price'];
								$tot['refund_out_sales_price']		+= $refundshipping['out_sales_price'];
								$tot['refund_out_salescost_admin']	+= $refundshipping['out_salescost_admin'];
								$tot['refund_pg_sale_price']		+= $refundshipping['pg_sale_price'];
								$tot['refund_out_cash_use']			+= $refundshipping['out_cash_use'];
								$tot['refund_pg_support_price']		+= $refundshipping['pg_support_price'];
								$tot['refund_out_sale_price']		+= $refundshipping['out_sale_price'];

								$tot['refund_out_pg_sale_price']	+= $refundshipping['out_pg_sale_price'];
								$tot['refund_out_pg_support_price']	+= $refundshipping['out_pg_support_price'];
								$tot['refund_out_pg_default_price']	+= $refundshipping['out_pg_default_price'];
								$tot['refund_out_pg_admin_price']	+= $refundshipping['out_pg_admin_price'];
								$tot['refund_out_ac_pg_price']			+= $refundshipping['out_ac_pg_price'];
								$tot['refund_out_ac_cash_use']		+= $refundshipping['out_ac_cash_use'];
								$tot['refund_out_pg_add_price']		+= $refundshipping['out_pg_add_price'];

								$tot['refund_out_total_ac_price']	+= $refundshipping['out_total_ac_price'];
								$tot['refund_sales_unit_feeprice']	+= $refundshipping['sales_unit_feeprice']*$refundshipping['ac_all_ea'];
								$tot['refund_commission_price']		+= $refundshipping['commission_price'];

								$refund_item = array_merge($acinsdata,$refundshipping);
								$loop[] = $refund_item;
							}
						}//endforeach
					}
					/**
					* 환불정보 끝
					**/

					/**
					* 반품배송비 시작 
					* 예)2017070515002717549
					**/ 
					if($data_return){unset($return_item);
						foreach($data_return as $returndata){
							$returndata = account_ins_returnshipping_ck($acinsdata,$returndata,$paycharge);
							if($returndata){ 

								$returndata['out_sale_price']			= $returndata['out_price'] - $returndata['out_tot_sale'];
								$returndata['out_sales_price']			= $returndata['sales_price']+$returndata['salescost_admin'];
								$returndata['sale_price']				= ($returndata['out_sale_price'] / $returndata['ea']);
								
								$returndata['out_pg_sale_price']	= 0;//매출 > 쿠폰(마일리지) 할인 > 제휴사
								$returndata['out_pg_support_price']	= 0;//매출 > 판매자 추가할인
								$returndata['out_pg_default_price']	= $returndata['gb '];//정산대상금액(A)>PG 결제금액
								$returndata['out_pg_admin_price']	= $returndata['salescost_admin'];//정산대상금액(A)>본사
								$returndata['out_ac_pg_price']			= 0;//정산대상금액(A)>제휴사
								$returndata['out_pg_add_price']		= 0;//정산대상금액(A)>추가할인	
								$returndata['out_total_ac_price']	= $returndata['out_pg_default_price']+$returndata['salescost_provider']+$returndata['out_pg_admin_price'];

								//소계영역
								$tot['return_out_price']			+= $returndata['out_price'];
								$tot['return_out_sales_price']		+= $returndata['out_sales_price'];
								$tot['return_out_salescost_admin']	+= $returndata['out_salescost_admin'];
								$tot['return_pg_sale_price']		+= $returndata['pg_sale_price'];
								$tot['return_out_cash_use']			+= $returndata['out_cash_use'];
								$tot['return_pg_support_price']		+= $returndata['pg_support_price'];
								$tot['return_out_sale_price']		+= $returndata['out_sale_price'];

								$tot['return_out_pg_sale_price']	+= $returndata['out_pg_sale_price'];
								$tot['return_out_pg_support_price']	+= $returndata['out_pg_support_price'];
								$tot['return_out_pg_default_price']	+= $returndata['out_pg_default_price'];
								$tot['return_out_pg_admin_price']	+= $returndata['out_pg_admin_price'];
								$tot['return_out_ac_pg_price']			+= $returndata['out_ac_pg_price'];
								$tot['return_out_ac_cash_use']		+= $returndata['out_ac_cash_use'];
								$tot['return_out_pg_add_price']		+= $returndata['out_pg_add_price'];

								$tot['return_out_total_ac_price']	+= $returndata['out_total_ac_price'];
								$tot['return_sales_unit_feeprice']	+= $returndata['sales_unit_feeprice']*$acinsdata['ac_all_ea'];
								$tot['return_commission_price']		+= $returndata['commission_price'];

								$return_item = array_merge($acinsdata,$returndata);
								$loop[] = $return_item;
							}

							/**
							정산계산식 수동처리
							**/
						}//endforeach
					}//endif
					/**
					* 반품배송비 끝
					**/

				}//당월 데이타 
				unset($acinsdata,$data_options,$data_suboptions,$data_shipping,$refund_item,$return_item,$comm_all,$refund_comm_all,$return_comm_all,$comm_all_sum,$refund_comm_all_sum, $return_comm_all_sum,$gap, $refund_gap, $return_gap);//초기화}
			}
		}

		//소계 정산 영역 sales_out_price sales_out_sale_price
		$tot['sales_out_consumer_price']			+= $tot['out_consumer_price']-$tot['refund_out_consumer_price'];//-$tot['return_out_price']
		$tot['sales_out_price']			+= $tot['out_price']-$tot['refund_out_price'];//-$tot['return_out_price']
		$tot['sales_out_price_total']	+= $tot['out_sales_price']-$tot['refund_out_sales_price']-$tot['return_out_sales_price'];
		$tot['sales_out_salescost_admin']	+= $tot['out_salescost_admin']-$tot['refund_out_salescost_admin']-$tot['return_out_salescost_admin'];
		$tot['sales_out_pg_sale_price']		+= $tot['pg_sale_price']-$tot['refund_pg_sale_price']-$tot['return_pg_sale_price'];
		$tot['sales_out_cash_use']		+= $tot['out_cash_use']-$tot['refund_out_cash_use']-$tot['return_out_cash_use'];
		$tot['sales_out_pg_support_price']	+= $tot['pg_support_price']-$tot['refund_pg_support_price']-$tot['return_pg_support_price'];
		$tot['sales_out_sale_price']	+= $tot['out_sale_price']-$tot['refund_out_sale_price']-$tot['return_out_sale_price'];
		
		$tot['ac_out_pg_sale_price']	+= $tot['out_pg_sale_price']-$tot['refund_out_pg_sale_price']-$tot['return_out_pg_sale_price'];
		$tot['ac_out_pg_support_price']	+= $tot['out_pg_support_price']-$tot['refund_out_pg_support_price']-$tot['return_out_pg_support_price'];
		$tot['ac_out_pg_default_price']	+= $tot['out_pg_default_price']-$tot['refund_out_pg_default_price']-$tot['return_out_pg_default_price'];
		$tot['ac_out_pg_admin_price']	+= $tot['out_pg_admin_price']-$tot['refund_out_pg_admin_price']-$tot['return_out_pg_admin_price'];
		$tot['ac_out_ac_pg_price']			+= $tot['out_ac_pg_price']-$tot['refund_out_ac_pg_price']-$tot['return_out_ac_pg_price'];
		$tot['ac_out_ac_cash_use']		+= $tot['out_ac_cash_use']-$tot['refund_out_ac_cash_use']-$tot['return_out_ac_cash_use'];
		$tot['ac_out_pg_add_price']		+= $tot['out_pg_add_price']-$tot['refund_out_pg_add_price']-$tot['return_out_pg_add_price'];

		$tot['ac_out_total_ac_price']	+= $tot['out_total_ac_price']-$tot['refund_out_total_ac_price']-$tot['return_out_total_ac_price'];
		$tot['ac_out_sales_unit_feeprice']	+= $tot['sales_unit_feeprice']-$tot['refund_sales_unit_feeprice']-$tot['return_sales_unit_feeprice'];
		$tot['ac_commission_price']		+= $tot['commission_price']-$tot['refund_commission_price']-$tot['return_commission_price'];
		//debug_var($tot['commission_price']." - ".$tot['refund_commission_price']);

		$this->template->assign(array(
			'carryovertot'		=> $carryovertot,
			'carryoverloop'		=> $carryoverloop,
			'tot'		=> $tot,
			'loop'		=> $loop,
			'loopcnt'		=> count($loop),
			'carryoverloopcnt'		=> count($carryoverloop),
		));

		$file_path	= $this->template_path();
		if( $_GET['accountall_excel'] ) {
			$file_path = str_replace("account_order_ea.html","accountall_excel.html",$file_path);
		}
		$this->template->assign(array('cfg_order'	=> $this->cfg_order,'config_system'	=> $this->config_system,'order_referer_pg'	=> $order_referer_pg));
		$this->template->define(array('tpl'	=>$file_path));
		$this->template->print_("tpl");
	}

	public function get_provider_for_period()
	{
		$period = $_GET['period'];

		$result = $this->providermodel->provider_list_for_account_period_sort($period);
		echo("<option value='all'>전체</option>");
		foreach($result as $data){
			if($data['provider_name']) echo("<option value='".$data['provider_seq']."'>".$data['provider_name']."</option>");
		}
	}
}

/* End of file accountall.php */
/* Location: ./app/controllers/admin/accountall.php */

?>
