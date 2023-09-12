<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}
require_once APPPATH . 'controllers/base/admin_base' . EXT;

class provider extends admin_base
{
	public function __construct()
	{
		parent::__construct();
		$this->load->library('validation');
	}

	public function index()
	{
		redirect('/admin/provider/catalog');
	}

	/* 입점사 */
	public function catalog()
	{
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('sort', '정렬', 'trim|string|xss_clean');
			$this->validation->set_rules('searchcount', '검색수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('perpage', '페이지갯수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('page', '페이지', 'trim|numeric|xss_clean');
			$this->validation->set_rules('provider_seq_selector', '입점사', 'trim|string|xss_clean');
			$this->validation->set_rules('provider_seq', '입점사', 'trim|numeric|xss_clean');
			$this->validation->set_rules('provider_name', '입점사명', 'trim|string|xss_clean');
			$this->validation->set_rules('provider_status[]', '입점사상태', 'trim|string|xss_clean');
			$this->validation->set_rules('deli_group[]', '배송', 'trim|string|xss_clean');
			$this->validation->set_rules('info_type[]', '사업자', 'trim|string|xss_clean');
			$this->validation->set_rules('calcu_count[]', '정산주기', 'trim|string|xss_clean');
			$this->validation->set_rules('pgroup_seq', '입점사그룹', 'trim|numeric|xss_clean');
			$this->validation->set_rules('mshop_cnt_s', '단골수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('mshop_cnt_e', '단골수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('commission_type', '정산기준', 'trim|string|xss_clean');
			$this->validation->set_rules('regdate[]', '임점일', 'trim|string|xss_clean');
			$this->validation->set_rules('orderby', '정렬', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('provider_view');
		if (!$auth) {
			$callback = 'history.go(-1);';
			$this->template->assign(['auth_msg' => $this->auth_msg, 'callback' => $callback]);
			$this->template->define(['denined' => $this->skin . '/common/denined.html']);
			$this->template->print_('denined');
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('membermodel');
		$this->load->model('providermodel');
		$this->load->model('accountallmodel');
		$this->load->model('goodsmodel');
		$this->load->library('searchsetting');

		// SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('minishop');
		if (!$result['type']) {
			$this->template->assign('minishop_service_limit', 'Y');
		}
		if (!$this->input->get('orderby') && $this->input->get('sort')) {
			$_GET['display_sort'] = $this->input->get('sort');
			$_tmp = explode(' ', $this->input->get('sort'));
			if (count($_tmp) == 2) {
				$_GET['orderby'] = $_tmp[0];
				$_GET['sort'] = $_tmp[1];
			}
		}
		$_default = ['orderby' => 'A.regdate', 'sort' => 'DESC', 'page' => 0, 'perpage' => 10];
		$scRes = $this->searchsetting->pagesearchforminfo('provider_catalog', $_default);
		$this->template->assign('sc_form', $scRes['form']);
		unset($scRes['form']);

		// SEARCH
		$sc = $scRes;
		$sc['get_mshop'] = 'y';
		$result = $this->providermodel->provider_list($sc);

		// 신규 정산을 이용할 경우 마이그레이션 다음 달부터 적용되도록 처리 :: 2017-06-14 lkh
		$this->load->helper('accountall');
		$_accountSettings = getAccountSetting();
		$migrationCheckDate = $_accountSettings['migrationCheckDate'];

		// PAGE & DATA
		$sc['searchcount'] = $result['page']['searchcount'];
		$sc['total_page'] = @ceil($sc['searchcount'] / $sc['perpage']);
		$sc['totalcount'] = $result['page']['totalcount'];

		foreach ($result['result'] as $datarow) {
			$datarow['mshop_url'] = '/mshop/?m=' . $datarow['provider_seq'];
			if ($datarow['provider_status'] == 'Y') {
				$datarow['provider_status'] = '정상';
			} else {
				$datarow['provider_status'] = '종료';
			}

			// 신규 정산을 이용할 경우 마이그레이션 다음 달부터 적용되도록 처리 :: 2017-06-14 lkh
			// 현 소스가 적용됐다면 이미 신정산 사용하는 경우이기 때문에 기존 마이그레이션일자로 분기처리한 부분은 삭제.
			if ($datarow['accountall_period_count']) {
				$nowPeriod = $datarow['accountall_period_count'];
			} else {
				$nowPeriod = $datarow['calcu_count'];
			}
			// 입점사가 등록한 상품 개수 제공 :: 2019-11-21 cws
			$datarow['goodsCount'] = $this->providermodel->get_provider_goods_cnt($datarow['provider_seq']);
			$datarow['totalGoodsCount'] = array_sum($datarow['goodsCount']);
			$datarow['calcu_count'] = $datarow['accountall_period_count'];
			$dataloop[] = $datarow;
		}

		//
		if (isset($dataloop)) {
			$this->template->assign('loop', $dataloop);
		}
		$this->template->assign('page', $result['page']);

		// 등급리스트
		$group_list = $this->providermodel->find_group_cnt_list();

		if (!isset($sc['calcu_count'])) {
			$sc['calcu_count'][] = 'all';
		}
		if (!isset($sc['info_type'])) {
			$sc['info_type'][] = 'all';
		}
		if (!isset($sc['provider_status'])) {
			$sc['provider_status'][] = 'all';
		}

		$this->template->assign('group_list', $group_list);
		$this->template->assign('group_arr', $group_arr);
		$this->template->assign(['sc' => $sc, 'scObj' => json_encode($sc)]);

		requirejs('/app/javascript/js/admin/gSearchForm.js', 50);		// search libraries

		$filePath = $this->template_path();
		$this->template->define(['tpl' => $filePath]);
		$this->template->print_('tpl');
	}

	public function provider_reg()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$filePath = $this->template_path();
		$this->load->model('providermodel');
		$this->load->model('providercode');
		$this->load->model('accountallmodel');
		$this->load->model('pagemanagermodel');
		$this->load->model('goodsmodel');

		$num_menu_count = 0;
		$rowspan_menu_count = 0;
		if (!$this->scm_cfg) {
			$this->scm_cfg = config_load('scm');
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

		$aGetParams = $this->input->get();
		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('no', '입점사 고유번호', 'trim|numeric|xss_clean');

			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		// 메뉴 상단 건수 표시
		$noti_acount_priod['order'] = $noti_acount_priod['board'] = $noti_acount_priod['account'] = $noti_acount_priod['warehousing'] = '6개월';
		
		if (isset($aGetParams['no'])) {
			$wheres['shopSno'] = $this->config_system['shopSno'];
			$wheres['provider_seq'] = $aGetParams['no'];
			$orderbys['idx'] = 'asc';
			$wheres['codecd like'] = '%_priod_%';
			$query_auth = $this->providercode->select('*', $wheres, $orderbys);
			foreach ($query_auth->result_array() as $data) {
				$codecd = str_replace('noti_count_priod_', '', $data['codecd']);
				$noti_acount_priod[$codecd] = $data['value'];
			}
		}

		// SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('minishop');
		if (!$result['type']) {
			$this->template->assign('minishop_service_limit', 'Y');
		}

		$calcu_count_limit = $this->usedmodel->get_provider_account_calcu_count();
		$this->template->assign('calcu_count_limit', $calcu_count_limit);

		$provider_grade_list = $this->providermodel->find_group_cnt_list();
		$this->template->assign('pgroup_list', $provider_grade_list);

		if (!isset($aGetParams['no']) && $this->config_system['service']['code'] == 'P_ADVL') {
			$limit = $this->usedmodel->get_provider_limit();
			$sql = "select count(*) as cnt from fm_provider where provider_id!='base' and provider_status='Y'";
			$query = $this->db->query($sql);
			$data = $query->row_array();
			if ($data['cnt'] >= $limit) {
				pageBack("입점사는 총 {$limit}개까지 등록하실 수 있습니다.");
				exit;
			}
		}

		// BRAND
		$sql = 'select * from fm_brand where length(category_code)=4 and parent_id = 2 order by `left` asc';
		$query = $this->db->query($sql);
		foreach ($query->result_array() as $row) {
			$brand[] = $row;
		}
		$this->template->assign('brand', $brand);
		$this->template->assign('brand_cnt', count($brand));

		// [반응형스킨] 운영방식 추가 :: 2018-11-01 pjw
		$operation_type = !empty($this->config_system['operation_type']) ? $this->config_system['operation_type'] : 'heavy';
		$this->template->assign('operation_type', $operation_type);

		// MODIFY
		if (isset($aGetParams['no'])) {
			$data = $this->providermodel->get_provider($aGetParams['no']);
			$data['deli_zipcode'] = $data['deli_zipcode'];
			$data['info_zipcode'] = $data['info_zipcode'];
			$data['main_visual_name'] = basename($data['main_visual']);
			$data['mshop_url'] = '/mshop/?m=' . $data['provider_seq'];
			$mshop = $this->providermodel->get_minishop_count($data['provider_seq']);
			$data['mshop_cnt'] = $mshop['cnt'];

			// [반응형스킨] light용 미니샵 정보 추가 :: 2018-11-01 pjw
			// 검색필터만 값이 없을 경우 기본값을 노출해야 하기때문에 pagemanagermodel 에서 통합 관리함
			$data['minishop_search_filter'] = $this->pagemanagermodel->get_search_filter('minishop', $data['minishop_search_filter']);
			$data['minishop_orderby'] = $data['minishop_orderby'];

			if ($data['limit_ip']) {
				$limit_row = explode('|', $data['limit_ip']);
				$count = count($limit_row) - 1;
				for ($i = 0; $i < $count; $i++) {
					$limit_ip[] = explode('.', $limit_row[$i]);
				}
				$data['limit_ip'] = $limit_ip;
			}
			$this->template->assign($data);

			// PERSON
			$person_date = $this->providermodel->get_person($aGetParams['no']);
			foreach ($person_date as $person) {
				$this->template->assign($person['gb'], $person);
			}

			// 추천상품리스트
			// 추천상품 타입이 직접선정인 경우 상품데이터 가져옴
			if ($data['auto_criteria_type'] == 'MANUAL') {
				$sql = "SELECT r.*, g.*, o.price, (select image from fm_goods_image where goods_seq=g.goods_seq and cut_number=1 and image_type ='thumbCart' limit 1) as image FROM
							fm_goods g
							INNER JOIN fm_goods_option o ON g.goods_seq = o.goods_seq AND o.default_option = ?
							INNER JOIN fm_provider_relation r ON r.relation_goods_seq = g.goods_seq AND r.provider_seq = ?
							ORDER BY r.relation_seq asc";

				$query = $this->db->query($sql, ['y', $aGetParams['no']]);
				$relation_goods_list = $query->result_array();
				$this->template->assign('items', $relation_goods_list);
			}

			$param['provider_seq'] = $aGetParams['no'];
			$certify = $this->providermodel->get_certify_manager($param);
			$this->template->assign('certify', $certify);
		} else {
			// 검색필터 기본값 추가
			$data['minishop_search_filter'] = $this->pagemanagermodel->get_search_filter('minishop', $data['minishop_search_filter']);
		}

		// 정산마감일 가져오기 마지막 마감일가져오기 :: 2018-08-23 lkh
		$nextConfirmArr = $this->accountallmodel->get_account_setting('last');
		$nowConfirmArr = $this->accountallmodel->get_account_setting('pre');
		if ($nextConfirmArr['accountall_confirm'] == '8') {
			$nextConfirm = '익월 : 7일';
		} elseif ($nextConfirmArr['accountall_confirm'] == '11') {
			$nextConfirm = '익월 : 10일';
		} else {
			$nextConfirm = '익월 : 월말';
		}
		if ($nowConfirmArr['accountall_confirm'] == '8') {
			$nowConfirm = '당월 : 7일';
		} elseif ($nowConfirmArr['accountall_confirm'] == '11') {
			$nowConfirm = '당월 : 10일';
		} else {
			$nowConfirm = '당월 : 월말';
		}
		$this->template->assign('nextConfirm', $nextConfirmArr['accountall_confirm']);
		// 신규 정산을 이용할 경우 마이그레이션 다음 달부터 적용되도록 처리 :: 2017-06-14 lkh
		$accountAllMiDate = config_load('accountall_setting', 'accountall_migration_date');
		$accountAllMigrationDate = $accountAllMiDate['accountall_migration_date'];
		$migrationYear = substr($accountAllMigrationDate, 0, 4);
		$migrationMonth = (substr($accountAllMigrationDate, 5, 2) + 1);
		$migrationCheckDate = $migrationYear . '-' . sprintf('%02d', $migrationMonth);
		if (isset($aGetParams['no'])) {
			$nextPeriodArr = $this->accountallmodel->get_account_provider_period('last', $aGetParams['no']);
			$nowPeriodArr = $this->accountallmodel->get_account_provider_period('pre', $aGetParams['no']);
			$nextPeriod = $nextPeriodArr['accountall_period_count'];
			if ($migrationCheckDate > date('Y-m')) {
				if ($data['calcu_count']) {
					$nowPeriod = $data['calcu_count'];
				} else {
					if ($nowPeriodArr['accountall_period_count']) {
						$nowPeriod = $nowPeriodArr['accountall_period_count'];
					} else {
						$nowPeriod = '0';
					}
				}
				$nowConfirm = '당월 : 구 정산화면에서 정산';
			} else {
				if ($nowPeriodArr['accountall_period_count']) {
					$nowPeriod = $nowPeriodArr['accountall_period_count'];
				} else {
					$nowPeriod = '0';
				}
			}
		} else {
			$nextPeriod = 1;
			$nowPeriod = 1;
		}
		$accountAllPeriodConfirm = ['nextPeriod' => $nextPeriod,
			'nowPeriod' => $nowPeriod,
			'nextConfirm' => $nextConfirm,
			'nowConfirm' => $nowConfirm
		];
		$this->template->assign('accountAllPeriodConfirm', $accountAllPeriodConfirm);
		$this->template->assign('noti_acount_priod', $noti_acount_priod);

		// 상품정보 선택기능 추가 :: 2019-05-17 pjw
		$this->load->model('designmodel');
		$goods_info_style = $this->designmodel->get_goods_info_style('search_list', $data['goods_info_style']);

		$this->template->assign('goods_info_style', $goods_info_style);
		$this->template->define('goods_info_style', $this->skin . '/page_manager/_goods_info_style.html');

		// 입점사가 등록한 상품 개수 제공 :: 2019-11-21 cws

		$goodsCount = $this->providermodel->get_provider_goods_cnt($aGetParams['no']);
		$totalGoodsCount = array_sum($goodsCount);

		$this->template->assign('goodsCount', $goodsCount);
		$this->template->assign('totalGoodsCount', $totalGoodsCount);

		// 추천상품 define 추가
		$this->template->define([
			'tpl' => $filePath,
			'condition' => $this->skin . '/provider/_recommend.html',
		]);
		$this->template->print_('tpl');
	}

	public function provider_shipping()
	{
		$filePath = $this->template_path();
		$this->template->define(['tpl' => $filePath]);

		if ($_GET['reg'] == 'Y') {
			$arr = explode('|', $_GET['company_code']);
			$cnt = 0;
			foreach ($arr as $k) {
				$tmp = config_load('delivery_url', $k);
				$data['deliveryCompany'][$k] = $tmp[$k]['company'];
				$data['deliveryCompanyCode'][$cnt] = $k;
				$cnt++;
			}

			if (!$_GET['company_code']) {
				unset($data['deliveryCompanyCode']);
			}
			//
			$data['summary'] = $_GET['summary'];
			$data['useYn'] = $_GET['use_yn'];
			$data['deliveryCostPolicy'] = $_GET['delivery_type'];
			if ($_GET['delivery_type'] == 'pay') {
				$data['payDeliveryCost'] = $_GET['delivery_price'];
				$data['postpaidDeliveryCost'] = $_GET['post_price'];
				if ($_GET['post_price'] > 0) {
					$data['postpaidDeliveryCostYn'] = 'y';
				}
			} elseif ($_GET['delivery_type'] == 'ifpay') {
				$data['ifpayFreePrice'] = $_GET['if_free_price'];
				$data['ifpayDeliveryCost'] = $_GET['delivery_price'];
				$data['ifpostpaidDeliveryCost'] = $_GET['post_price'];
				if ($_GET['post_price'] > 0) {
					$data['ifpostpaidDeliveryCostYn'] = 'y';
				}
			}

			$arr2 = explode('|', $_GET['add_delivery_cost']);
			$cnt = 0;
			foreach ($arr2 as $k) {
				$tmps = explode(':', $k);
				$tmpsCount = count($tmps);
				if ($tmpsCount == 3) {
					$data['sigungu'][$cnt] = $tmps[0];
					$data['sigungu_street'][$cnt] = $tmps[1];
					$data['addDeliveryCost'][$cnt] = $tmps[2];
				} else {
					$data['sigungu'][$cnt] = $tmps[0];
					$data['addDeliveryCost'][$cnt] = $tmps[1];
				}
				$cnt++;
			}
			/*
			echo "<pre>";
			print_r($data);
			*/

			$this->template->assign($data);
		}

		if (isset($_GET['seq']) && $_GET['seq'] != '') {
			$this->load->model('providershipping');
			$data = $this->providershipping->get_provider_shipping($_GET['seq']);
			$this->template->assign($data);
		}

		$this->template->print_('tpl');
	}

	public function salescost()
	{
		$this->load->model('providermodel');
		$sc['orderby'] = 'provider_name';
		$sc['sort'] = 'asc';
		$sc['page'] = 0;
		$sc['perpage'] = 9999;
		$provider = $this->providermodel->provider_list($sc);
		$provider_gb = [];
		$provider_seq = [];

		if ($provider) {
			foreach ($provider['result'] as $k => $data) {
				$provider_gb[$data['deli_group']][] = $data;
				$provider_seq[$data['provider_seq']] = $data;
			}
		}

		if ($_GET['provider_seq_list']) {
			$provider_list = substr(substr($_GET['provider_seq_list'], 1), 0, -1);
			$provider_arr = explode('|', $provider_list);
			if (count($provider_arr) > 0) {
				$provider_select_list = $this->providermodel->get_provider_range($provider_arr);
				if ($provider_select_list) {
					foreach ($provider_select_list as $k => $data) {
						if ($data['deli_group']) {
							$default_deli_group = $data['deli_group'];
						}

						$add_commission_text = ($provider_seq[$data['provider_seq']]['commission_type'] == 'SACO' || $provider_seq[$data['provider_seq']] == '') ? ' ("수수료" 정산)' : ' ("공급가" 정산)';
						$selectedProvider[$data['provider_seq']] = $data['provider_name'] . $add_commission_text;
					}
				}
			}

			$this->template->assign(['selectedProvider' => $selectedProvider]);
		}

		$this->template->assign(['default_deli_group' => $default_deli_group]);
		$this->template->assign(['shippingtype' => $_GET['shippingtype']]);
		$this->template->assign(['salescost_provider' => $_GET['salescost_provider']]);
		$this->template->assign(['provider_gb' => $provider_gb]);
		$this->template->assign(['provider' => $provider['result']]);
		$this->template->assign(['calltype' => $_GET['calltype']]);
		$file_path = $this->template_path();
		$this->template->define(['tpl' => $file_path]);
		$this->template->print_('tpl');
	}

	public function provider_statistic()
	{
		$this->load->model('providermodel');
		if (!$_GET['year']) {
			$_GET['year'] = date('Y');
		}

		$sc = $_GET;
		$provider_seq = $_GET['provider_seq'];
		$pageType = $_GET['pageType'];

		//# 실매출 ( 월별 통계 )
		switch ($pageType) {
			case 'order':
				$order = $this->providermodel->get_account_order($sc);
				if ($order) {
					foreach ($order as $k => $data) {
						$stats[$data['export']] = $data['opt_price'] + $data['sub_price'];
					}
				}

			break;
			case 'account':
				$order = $this->providermodel->get_account_order($sc);
				if ($order) {
					foreach ($order as $k => $data) {
						$sc['date'] = $sc['year'] . '-' . $data['export'];
						$shipping = $this->providermodel->get_account_shipping($sc);
						$refund = $this->providermodel->get_account_refund($sc);
						$return = $this->providermodel->get_account_return($sc);

						$price = $data['opt_price'] + $data['sub_price'];
						$account = $price + $shipping['shipping_cost'] + $data['goods_shipping_cost'];
						$account = $account - ($price - $data['commission_price']);
						$account = $account - $refund['refund_commission_price'];
						$account = $account + $return['return_shipping_price'];

						$stats[$data['export']] = $account;
					}
				}

			break;
			case 'charge':
				$order = $this->providermodel->get_account_order($sc);
				if ($order) {
					foreach ($order as $k => $data) {
						$price = $data['opt_price'] + $data['sub_price'];
						$charge = $price - $data['commission_price'];
						$stats[$data['export']] = $charge;
					}
				}

			break;
			case 'mshop':
				$mshop = $this->providermodel->get_account_mshop($sc);
				if ($mshop) {
					foreach ($mshop as $k => $data) {
						$stats[$data['date']] = $data['cnt'];
					}
				}

			break;
		}

		for ($m = 1; $m <= 12; $m++) {
			$month = str_pad($m, 2, '0', STR_PAD_LEFT);
			$value = ($stats[$month]) ? $stats[$month] : 0;
			$dataForChart[] = [$month . '월', $value];

			$maxValue = ($maxValue < $value) ? $value : $maxValue;
		}

		$this->template->assign(['maxValue' => $maxValue]);
		$this->template->assign(['dataForChart' => $dataForChart]);

		$file_path = $this->template_path();
		$this->template->define(['tpl' => $file_path]);
		$this->template->print_('tpl');
	}

	// 확인코드 중복체크 및 유효성 체크
	public function chk_certify_code($cerfify_code = '')
	{
		$return = 'ok';

		if ($_GET['certify_code']) {
			$certify_code = trim($_GET['certify_code']);
		}

		if ($_GET['certify_seq']) {
			$param['out_seq'] = trim($_GET['certify_seq']);
		}

		if (!$certify_code) {
			$return = 'error_1';
		} elseif (strlen($certify_code) < 6 || strlen($certify_code) > 16) {
			$return = 'error_2';
		} elseif (preg_match('/[^0-9a-zA-Z]/', $certify_code)) {
			$return = 'error_3';
		}

		$this->load->model('providermodel');
		$param['certify_code'] = $certify_code;
		$certify = $this->providermodel->get_certify_manager($param);
		if ($certify) {
			$return = 'duple';
		}

		if ($_GET['certify_code']) {
			echo $return;
		} else {
			return $return;
		}
	}

	/* 입점사 등급 */
	public function provider_group()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('provider_view');
		if (!$auth) {
			$callback = 'history.go(-1);';
			$this->template->assign(['auth_msg' => $this->auth_msg, 'callback' => $callback]);
			$this->template->define(['denined' => $this->skin . '/common/denined.html']);
			$this->template->print_('denined');
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('providermodel');

		// 등급리스트
		$list = $this->providermodel->find_group_cnt_list();
		$sql = 'select count(*) cnt from fm_provider where provider_seq > 1';
		$query = $this->db->query($sql);
		$result = $query->result_array();
		$totalcount = $result[0]['cnt'];

		// 자동 등급 조정 설정 불러오기
		$grade_clone = config_load('provider_grade_clone');

		$grade_clone['chg_text'] = '';
		$grade_clone['chk_text'] = '';
		$grade_clone['keep_text'] = '';
		$next_grade_date = '';
		$month = $grade_clone['start_month'] ? $grade_clone['start_month'] : '1';

		//# 자동갱신 일자/산출기간/유지기간 계산
		if ($grade_clone['chg_day']) {
			$auto_result = $this->providermodel->calculate_date($month, $grade_clone, 'setting');
		}

		// 자동 등급조정 기초값.
		$list_month = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12];
		$list_term = [1, 3, 6, 12, 18, 24, 36];
		$list_day = [1, 15];

		$this->template->define(['tpl' => $filePath]);
		$this->template->assign('list_month', $list_month);
		$this->template->assign('list_term', $list_term);
		$this->template->assign('list_day', $list_day);
		$this->template->assign('auto_result', $auto_result);
		$this->template->assign('clone', $grade_clone);
		$this->template->assign('tot', $totalcount);
		if ($list) {
			$this->template->assign(['loop' => $list, 'gcount' => count($list)]);
		}

		$filePath = $this->template_path();
		$this->template->define(['tpl' => $filePath]);
		$this->template->print_('tpl');
	}

	/* 입점사 등급 만들기 화면 */
	public function provider_group_reg()
	{
		$pgroup_seq = $_GET['pgroup_seq'];

		$this->admin_menu();
		$this->tempate_modules();

		// SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('grade');

		if (!$result['type']) {
			$this->template->assign('service_limit', 'Y');
		}

		if ($pgroup_seq) {
			//
			$this->load->model('providermodel');
			$data = $this->providermodel->get_pgroup_data($pgroup_seq);

			switch ($data['use_type']) {
				case 'auto1': $no = 1;

break;
				case 'auto2': $no = 2;

break;
				default: $no = 3;

break;
			}

			$data['order_sum_price' . $no] = $data['order_sum_price'];
			$data['order_sum_ea' . $no] = $data['order_sum_ea'];
			$data['order_sum_cnt' . $no] = $data['order_sum_cnt'];

			$data['order_sum_use'] = unserialize($data['order_sum_use']);

			foreach ($data['order_sum_use'] as $key => $val) {
				$selected['order_sum_' . $val . '_use'] = 'checked';
			}
		}

		$filePath = $this->template_path();
		$this->template->define(['tpl' => $filePath]);
		$this->template->assign('data', $data);
		$this->template->assign('selected', $selected);
		$this->template->print_('tpl');
	}

	/* 입점사 자동 등급조정 설정 미리보기 */
	public function grade_ajax()
	{
		$this->load->model('providermodel');
		$result = $this->providermodel->calculate_date($_GET['start_month'], $_GET, 'setting');

		$grade_dt = [];
		foreach ($result as $id => $val1) {
			$arr_txt = [];
			if (in_array($id, ['chg_text', 'chk_text', 'keep_text'])) {
				$arr_txt[] = '<ul>';

				foreach ($val1 as $k => $cont) {
					if ($k % 2 == 1) {
						$sty = " style='background-color:#FFF;'";
					} else {
						$sty = '';
					}
					$arr_txt[] = '<li' . $sty . '>' . $cont . '</li>';
				}

				$arr_txt[] = '</ul>';

				$grade_dt[$id] = implode('', $arr_txt);
			}
		}
		echo json_encode($grade_dt);
	}

	// [공용]openDialog 선택형 입점사 리스트
	// 2020.02.12 pjm
	public function gl_select_provider()
	{
		$this->load->model('providermodel');

		unset($sc);
		$sc['page'] = (isset($_GET['page']) && $_GET['page'] > 1) ? intval($_GET['page']) : '0';
		$sc['perpage'] = ($_GET['perpage']) ? intval($_GET['perpage']) : '10';
		$sc['sc_provider_name'] = trim($_GET['sc_provider_name']);
		$sc['pageblock'] = ($_GET['pageblock']) ? intval($_GET['pageblock']) : '10';
		$sc['orderby'] = ($_GET['orderby']) ? $_GET['orderby'] : 'provider_name';
		$sc['sort'] = ($_GET['sort']) ? $_GET['sort'] : 'asc';

		if ($_GET['select_lists']) {
			$select_lists = explode('|', $_GET['select_lists']);
			$sc['select_lists'] = $select_lists;
		}

		$provider = $this->providermodel->provider_list($sc);
		$provider_gb = [];

		if ($provider['result']) {
			foreach ($provider['result'] as $k => $data) {
				list($commission_charge, $commission_text) = $this->providermodel->get_commission_type($data['charge'], $data['commission_type']);
				$provider['result'][$k]['commission_charge'] = $commission_charge;
				$provider['result'][$k]['commission_text'] = $commission_text;
				$provider_gb[$data['deli_group']][] = $data;
			}
		}

		$shippingtype = $_GET['shippingtype'];
		$calltype = $_GET['calltype'];

		$this->template->assign($provider);
		$this->template->assign('sc', $sc);
		$this->template->assign(['shippingtype' => $shippingtype]);
		$this->template->assign(['provider_gb' => $provider_gb]);
		$this->template->assign(['calltype' => $calltype, 'sc' => $sc]);

		$file_path = str_replace('gl_select_provider.html', '_gl_select_provider.html', $this->template_path());
		$this->template->define(['tpl' => $file_path]);
		$this->template->print_('tpl');
	}

	/* 입점사 주문 미처리 현황 */
	public function remind_export()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('provider_view');
		if (!$auth) {
			$callback = 'history.go(-1);';
			$this->template->assign(['auth_msg' => $this->auth_msg, 'callback' => $callback]);
			$this->template->define(['denined' => $this->skin . '/common/denined.html']);
			$this->template->print_('denined');
			exit;
		}
		/* 관리자 권한 체크 : 끝 */
	
	// validation
		$aGetParams = $this->input->get();
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('sort', '정렬', 'trim|string|xss_clean');
			$this->validation->set_rules('searchcount', '검색수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('perpage', '페이지갯수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('page', '페이지', 'trim|numeric|xss_clean');
			$this->validation->set_rules('provider_seq_selector', '입점사', 'trim|string|xss_clean');
			$this->validation->set_rules('provider_seq', '입점사', 'trim|numeric|xss_clean');
			$this->validation->set_rules('found_info_mobile[]', '물류담당자 연락처', 'trim|string|xss_clean');
			$this->validation->set_rules('regdate[]', '날짜', 'trim|string|date|xss_clean');
			$this->validation->set_rules('orderby', '정렬', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$this->admin_menu();
		$this->tempate_modules();
		$this->load->model('providermodel');
		$this->load->model('membermodel');
		$this->load->library('providerlibrary');
		
		// 검색 기본값 세팅
		$sc = $this->providerlibrary->default_search_remind_export($aGetParams);
		$result = $this->providermodel->remind_export_list($sc);
		$searchCount = $this->providermodel->get_remind_export_count(); // 검색된 총 갯수 $result 바로 다음 실행해야 직전 쿼리 총 갯수를 가져옴

		// PAGE & DATA
		$filePath = $this->template_path();
		$page['searchcount'] = $searchCount;
		$page['total_page'] = @ceil($page['searchcount'] / $sc['perpage']);
		$page['html'] = pagingtag($page['searchcount'], $sc['perpage'], $this->membermodel->admin_member_url($filePath) . '?', getLinkFilter('', array_keys($sc)));		
		$no = $page['searchcount'] - ($sc['page'] / $sc['perpage'] * $sc['perpage']);

		// get list data
		
		foreach ($result as $row) {
			// 입점상태
			$row['provider_status'] = ($row['provider_status'] == 'Y') ? '정상' : '종료';

			/* 순번 계산 */
			$row['_no'] = $no;
			$no--;

			$dataloop[] = $row;
		}

		//# assign
		if (isset($dataloop)) {
			$this->template->assign('loop', $dataloop);
		}
		$this->template->assign('page', $page);
		$this->template->assign(['sc' => $sc, 'scObj' => json_encode($sc)]);
		requirejs('/app/javascript/js/admin/gSearchForm.js', 50);		// search libraries
		requirejs('/app/javascript/js/admin/provider.js', 50);
		$this->template->define(['tpl' => $filePath]);
		$this->template->print_('tpl');
	}
}
