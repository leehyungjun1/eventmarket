<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class export extends selleradmin_base {
	public function __construct(){
		parent::__construct();
		$this->arr_status = config_load('export_status');
		$this->arr_step = config_load('step');
		$this->arr_payment = config_load('payment');
		$this->cfg_order = config_load('order');
		$this->load->library('validation');

		$auth = $this->authmodel->manager_limit_act('order_goods_export');
		if(!$auth){
			openDialogAlert( '관리자 권한이 없습니다.' ,300,150,'parent','parent.location.reload();');
			exit;
		}
	}
	public function index()
	{
		redirect("/selleradmin/order/catalog");
	}

	public function important()
	{
		$val = $_GET['val'];
		$no = str_replace('important_','',$_GET['no']);
		$query = "update fm_goods_export set important=? where export_seq=?";
		$this->db->query($query,array($val,$no));
	}

	public function set_search_default(){
		foreach($_POST as $key => $data){
			if( is_array($data) ){
				foreach($data as $key2 => $data2){
					if($data2) $cookie_arr[] = $key."[".$key2."]"."=".$data2;
				}
			}else if($data){
				$cookie_arr[] = $key."=".$data;
			}
		}
		if($cookie_arr){
			$cookie_str = implode('&',$cookie_arr);
			$_COOKIE['export_list_search'] = $cookie_str;
			setcookie('export_list_search',$cookie_str,time()+86400*30);
		}
		$callback = "parent.location.reload();parent.closeDialog('search_detail_dialog');";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function get_search_default(){
		$arr = explode('&',$_COOKIE['export_list_search']);
		foreach($arr as $data){
			$arr2 = explode("=",$data);
			$result[] = $arr2;
		}
		echo json_encode($result);
	}

	// 출고리스트 :: 2016-10-11 lwh
	public function catalog()
	{
		$this->admin_menu();
		$this->tempate_modules();

		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('providermodel');
		$this->load->model('shippingmodel');
		$this->load->model('ordershippingmodel');
		$this->load->helper('shipping');
		$this->load->helper('order');

		$requestGet = $this->input->get();

		# npay 2.1 사용여부
		$npay_use			= npay_useck();

		// 검색 타입 지정 :: 2016-10-13 lwh
		$search_arr_field = array(
			"ord.order_seq"				=> "주문번호",
			"exp.export_code"			=> "출고번호",
			"ord.order_user_name"		=> "주문자명",
			"ord.depositor"				=> "입금자명",
			"mem.userid"				=> "아이디",
			"ord.order_cellphone"		=> "휴대전화",
			"ord.order_email"			=> "이메일",

			"ord.recipient_user_name"	=> "수령자명",
			"ord.recipient_cellphone"	=> "휴대전화",
			"ord.recipient_phone"		=> "일반전화",

			"oitem.goods_name"			=> "상품명",
			"oitem.goods_seq"			=> "상품번호"
		);
		if($npay_use)	$search_arr_field['exp.npay_order_id'] = "네이버페이";

		//오픈마켓연동정보
		$this->load->model('openmarketmodel');
		$linkage = $this->openmarketmodel->get_linkage_config();
		if($linkage){
			// 설정된 판매마켓 정보
			$linkage_mallnames = array();
			$linkage_malldata		= $this->openmarketmodel->get_linkage_mall();
			foreach($linkage_malldata as $k => $data){
				if	($data['default_yn'] == 'Y'){
					$linkage_mallnames[$data['mall_code']]	= $data['mall_name'];
				}
			}
			$this->template->assign('linkage_mallnames',$linkage_mallnames);
		}

		// 택배사 추출 :: 2016-10-14 lwh
		$arr_delivery = config_load('delivery_url');
		foreach(get_invoice_company($this->providerInfo['provider_seq']) as $k=>$data){
			$arr_delivery[$k] = $data;
		}

		/* 입점사명 정렬 추가(가나다abc) leewh 2014-11-10 */
		$provider		= $this->providermodel->provider_goods_list_sort();

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if (count($requestGet) === 0 || $_PARAM['noquery']){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('selleradmin/export/catalog');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$requestGet['regist_date'][0]	= $search_date['start_date'];
				$requestGet['regist_date'][1]	= $search_date['end_date'];
				foreach($data_search_default as $key => $val){
					$key = str_replace("default_","",$key);
					$requestGet[$key]		= $val;
				}
			}else{
				// 기본세팅이 없는경우 오늘의 입금확인 주문접수 주문을 검색조건으로 합니다.
				$requestGet['date'] = 'export';
				$requestGet['regist_date'][0] = date('Y-m-d');
				$requestGet['regist_date'][1] = date('Y-m-d');
				$requestGet['export_status'][45] = 1;
				$requestGet['export_status'][55] = 1;
				$requestGet['export_status'][65] = 1;
			}
		}

		$isRequireSearchValue = true;
		if (
			strlen($requestGet['header_search_keyword']) === 0
			&& strlen($requestGet['keyword']) === 0
			&& (strlen($requestGet['regist_date'][0]) === 0 || strlen($requestGet['regist_date'][1]) === 0)
		) {
			$isRequireSearchValue = false;
		}

		// 필수 검색 조건이 없을 경우
		if ($isRequireSearchValue === false) {
			$requestGet['regist_date'][0] = date('Y-m-d', strtotime('-365 days'));
			$requestGet['regist_date'][1] = date('Y-m-d');
		}

		$this->load->model('invoiceapimodel');
		$invoice_vendor = $this->invoiceapimodel->get_usable_invoice_vendor();
		$this->template->assign(array('invoice_vendor'	=> $invoice_vendor));

		$this->template->assign(array(
			'sc'							=> $requestGet,
			'arr_search_keyword'			=> $search_arr_field,
			'ship_set_code'					=> $this->shippingmodel->ship_set_code,
			'delivery_company_array'		=> $delivery_company_array,
			'international_company_array'	=> $international_company_array,
			'provider'						=> $provider,
			'npay_use'						=> $npay_use,
		));

		$this->template->define(array('tpl' => $this->template_path()));
		$this->template->print_("tpl");
	}


	public function catalog_ajax(){

		$aPostParams = $this->input->post();

		// validation
		if ($aPostParams) {
			$this->validation->set_data($aPostParams);
			$this->validation->set_rules('header_search_keyword', '검색어', 'trim|string|xss_clean');
			$this->validation->set_rules('header_search_type', '검색선택', 'trim|string|xss_clean');
			$this->validation->set_rules('page', '페이지', 'trim|numeric|xss_clean');
			$this->validation->set_rules('shipping_provider_seq', '배송주체', 'trim|string|xss_clean');
			$this->validation->set_rules('bfStep', '상태', 'trim|string|xss_clean');
			$this->validation->set_rules('nnum', '번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('searchTime', '검색일시', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value'],422);
			}
		}

		$this->admin_menu();
		$this->tempate_modules();

		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('providermodel');
		$this->load->model('shippingmodel');
		$this->load->model('ordershippingmodel');
		$this->load->helper('shipping');
		$this->load->helper('order');
		$this->load->library('privatemasking');

		$_PARAM			= $_POST;//$_GET//$_PARAM

		# npay 2.1 사용여부
		$npay_use			= npay_useck();

		// 검색 타입 지정 :: 2016-10-13 lwh
		$search_arr_field = array(
			"ord.order_seq"				=> "주문번호",
			"exp.export_code"			=> "출고번호",
			"ord.order_user_name"		=> "주문자명",
			"ord.depositor"				=> "입금자명",
			"mem.userid"				=> "아이디",
			"ord.order_cellphone"		=> "휴대전화",
			"ord.order_email"			=> "이메일",

			"ord.recipient_user_name"	=> "수령자명",
			"ord.recipient_cellphone"	=> "휴대전화",
			"ord.recipient_phone"		=> "일반전화",

			"oitem.goods_name"			=> "상품명",
			"oitem.goods_seq"			=> "상품번호"
		);
		if($npay_use)	$search_arr_field['exp.npay_order_id'] = "네이버페이";

		//오픈마켓연동정보
		$this->load->model('openmarketmodel');
		$linkage = $this->openmarketmodel->get_linkage_config();
		if($linkage){
			// 설정된 판매마켓 정보
			$linkage_mallnames = array();
			$linkage_malldata		= $this->openmarketmodel->get_linkage_mall();
			foreach($linkage_malldata as $k => $data){
				if	($data['default_yn'] == 'Y'){
					$linkage_mallnames[$data['mall_code']]	= $data['mall_name'];
				}
			}
			$this->template->assign('linkage_mallnames',$linkage_mallnames);
		}

		// 택배사 추출 :: 2016-10-14 lwh
		$arr_delivery = config_load('delivery_url');
		foreach(get_invoice_company($this->providerInfo['provider_seq']) as $k=>$data){
			$arr_delivery[$k] = $data;
		}

		/* 입점사명 정렬 추가(가나다abc) leewh 2014-11-10 */
		$provider		= $this->providermodel->provider_goods_list_sort();

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_PARAM) == 0 ){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('selleradmin/export/catalog');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$_PARAM['regist_date'][0]	= $search_date['start_date'];
				$_PARAM['regist_date'][1]	= $search_date['end_date'];
				foreach($data_search_default as $key => $val){
					$key = str_replace("default_","",$key);
					$_PARAM[$key]		= $val;
				}
			}else{
				// 기본세팅이 없는경우 오늘의 입금확인 주문접수 주문을 검색조건으로 합니다.
				$_PARAM['date'] = 'export';
				$_PARAM['regist_date'][0] = date('Y-m-d');
				$_PARAM['regist_date'][1] = date('Y-m-d');
				$_PARAM['export_status'][45] = 1;
				$_PARAM['export_status'][55] = 1;
				$_PARAM['export_status'][65] = 1;
			}
		}

		/**
		 * 검색날짜 조건이 없으면 1년치 데이터를 조회한다.
		 *
		 * 전체 검색되면 슬로우 쿼리 발생 으로 사이트가 뻗는다
		 */
		if (!$_PARAM['header_search_keyword'] && !$_PARAM['keyword'] && (!$_PARAM['regist_date'][0] || !$_PARAM['regist_date'][1])) {
			$_PARAM['regist_date'][0] = date('Y-m-d', strtotime('-365 days'));
			$_PARAM['regist_date'][1] = date('Y-m-d');
		}

		//페이징 정보
		$page			= (trim($_PARAM['page'])) ? trim($_PARAM['page']) : 1;
		$bfStep			= trim($_PARAM['bfStep']);
		$no				= trim($_PARAM['nnum']);

		$international_company_array = get_international_company();

		$_PARAM['query_type'] = "selleradmin_catalog";
		$_PARAM['search_arr_field'] = $search_arr_field;
		$query = $this->exportmodel->get_export_catalog_query($_PARAM);
		if	($query){
			if	($page == 1){
				$_PARAM['query_type']	= 'selleradmin_total_record';
				$totalQuery				= $this->exportmodel->get_export_catalog_query($_PARAM);
				$totalData				= $totalQuery->result_array();
				$no						= $totalData[0]['cnt'];
			}

			$able_export_page = true;
			foreach($query->result_array() as $k => $data)
			{
				$data['price'] = (int) $data['opt_price'] + (int) $data['sub_price'];
				$data['mstatus'] = $this->arr_status[$data['status']];
				$status_cnt[$data['status']]++;
				$tot_price[$data['status']] += $data['price'];
				$data['tot_price'] = $tot_price;
				$tot[$data['status']][$data['important']] += $data['price'];
				$data['status_cnt'] = $status_cnt;
				$data['tot'][$data['important']] = $tot[$data['status']][$data['important']];
				$data['mpayment'] = $this->arr_payment[$data['payment']];
				$data['mstep'] = $this->arr_step[$data['step']];
				$data['shipping_method'] = ($data['shipping_method']) ? $data['shipping_method'] : $data['domestic_shipping_method'];

				// 본사 배송 상품일경우 출고 기능 제한: 출고 변경 가능 여부
				$data['able_export'] = true;
				$shipping_provider_seq = $this->ordershippingmodel->get_shipping_provider_seq_for_order($data['order_seq'],$this->providerInfo['provider_seq']);
				if( $shipping_provider_seq != $this->providerInfo['provider_seq']){
					$data['able_export'] = false;
					$able_export_page = false;
				}

				$data['delivery_company_array'] = get_shipping_company_provider($data['shipping_provider_seq']);

				if($data['delivery_company_code']) {
					$data['delivery_company_array'][$data['delivery_company_code']] = $arr_delivery[$data['delivery_company_code']];
				}

				$data['international_company_array'] = $international_company_array;
				if($data['international'] == 'domestic'){
					if($data['domestic_shipping_method'] == 'delivery'||$data['domestic_shipping_method'] == 'postpaid'){
						//$tmp = config_load('delivery_url',$data['delivery_company_code']);
						$data['mdelivery'] = $arr_delivery[$data['delivery_company_code']]['company'];
						$data['mdelivery_number'] = $data['delivery_number'];
						if($data['delivery_company_code']) $data['tracking_url'] = $arr_delivery[$data['delivery_company_code']]['url'].$data['delivery_number'];
					}else{
						$data['mdelivery'] = get_domestic_method($data['domestic_shipping_method']);
					}
				}else{
					$data['mdelivery'] = get_international_method($data['international_shipping_method']);
					$data['mdelivery_number'] = $data['international_delivery_no'];
				}

				if($data['invoice_send_yn']=='y'){
					$status_invoice_cnt[$data['status']]++;
				}

				if($data['member_seq']){
					$data['member_type']	= $data['mbinfo_business_seq'] ? '기업' : '개인';
				}

				$data_export_items = $this->exportmodel->get_export_item($data['export_code']);

				if( $data_export_items[0]['opt_type'] == 'sub'){
					if($data_export_items[0]['option1']){
						$data['item_title'] .= $data_export_items[0]['title1'].':'.$data_export_items[0]['option1'];
					}
					if($data_export_items[0]['option2']){
						$data['item_title'] .= ' '. $data_export_items[0]['title2'].':'.$data_export_items[0]['option2'];
					}
					if($data_export_items[0]['option3']){
						$data['item_title'] .= ' '.$data_export_items[0]['title3'].':'.$data_export_items[0]['option3'];
					}
					if($data_export_items[0]['option4']){
						$data['item_title'] .= ' '.$data_export_items[0]['title4'].':'.$data_export_items[0]['option4'];
					}
					if($data_export_items[0]['option5']){
						$data['item_title'] .= ' '.$data_export_items[0]['title5'].':'.$data_export_items[0]['option5'];
					}

				}

				$data['goods_kind'] = $data_export_items[0]['goods_kind'];
				$data['opt_type'] = $data_export_items[0]['opt_type'];
				$data['goods_name'] = $data_export_items[0]['goods_name'];
				$data['goods_type'] = $data_export_items[0]['goods_type'];
				$data['item_count'] .= count($data_export_items);

				// 배송그룹 정보 추출 :: 2016-10-11 lwh
				$shipping_group_arr	= explode('_', $data['shipping_group']);
				$data['shipping_grp_seq']	= $shipping_group_arr[0];
				$data['shipping_set_seq']	= $shipping_group_arr[1];
				$data['shipping_set_code'] = ($shipping_group_arr[3]) ? $shipping_group_arr[2].'_'.$shipping_group_arr[3] : $shipping_group_arr[2];
				// 배송출고지 추출 :: 2016-10-10 lwh
				$sql = "SELECT * FROM fm_shipping_grouping WHERE shipping_group_seq = ?";
				$grp_query	= $this->db->query($sql,$data['shipping_grp_seq']);
				$grp_res	= $grp_query->row_array();
				$send_add = $this->shippingmodel->get_shipping_address($grp_res['sendding_address_seq'], $grp_res['sendding_scm_type']);
				if($send_add['address_nation'] == 'korea'){
					$send_add['view_address'] = ($send_add['address_type'] == 'street') ? $send_add['address_street'] : $send_add['address'];
					$send_add['view_address'] = '(' . $send_add['address_zipcode'] . ') ' . $send_add['view_address'] . ' ' . $send_add['address_detail'];
				}else{
					$send_add['view_address'] = '(' . $send_add['international_postcode'] . ') ' . $send_add['international_country'] . ' ' . $send_add['international_town_city'] . ' ' . $send_add['international_county'] . ' ' . $send_add['international_address'];
				}
				$data['sending_address'] = $send_add;
				// $data['refund_address']		= $this->shippingmodel->get_shipping_address($grp_res['refund_address_seq'], $grp_res['refund_scm_type']); // 일단 필요없음

				// 매장수령 정보 추출 :: 2016-10-11 lwh
				if($data['shipping_set_code'] == 'direct_store'){
					$ship_store_arr = $this->shippingmodel->get_shipping_store($data['shipping_set_seq'],'shipping_set_seq');
					$data['shipping_store_info'] = $ship_store_arr;
				}

				// 티켓상품일때 티켓 정보 추가
				if ($data['goods_kind'] == 'coupon'){
					$data['coupon_serial']	= $data_export_items[0]['coupon_serial'];
					$data['email']			= $data_export_items[0]['recipient_email'];
					$data['cellphone']		= $data_export_items[0]['recipient_cellphone'];
				}

				//합포장의 경우 합포장 번호로 노출
				if($data['is_bundle_export'] == 'Y'){
					$data['export_code']		= $data['bundle_export_code'];
					$data['bundle_order_list']	= array_values(array_unique(explode(',',$data['has_order_list'])));
				}

				## 시작점과 종료점
				$data['no']		= $no;
				if	($bfStep != $data['status']){
					$data['start_step']	= $data['status'];
					if	($bfStep){
						$record[$k]['end_step']			= $bfStep;
						$_PARAM['query_type']			= 'selleradmin_summary';
						$_PARAM['end_step']				= $bfStep;
						$summary_query					= $this->exportmodel->get_export_catalog_query($_PARAM);
						$endData						= $summary_query->result_array();
						$data['end_mstep']				= $this->arr_step[$bfStep];
						$data['end_step']				= $bfStep;
						$data['end_step_cnt']			= $endData[0]['cnt'];
						$data['end_step_settleprice']	= $endData[0]['total_settleprice'];
					}
					$bfStep	= $data['status'];
				}

				if	($no == 1){
					$_PARAM['query_type']			= 'selleradmin_summary';
					$_PARAM['end_step']				= $data['status'];
					$summary_query					= $this->exportmodel->get_export_catalog_query($_PARAM);
					$endData						= $summary_query->result_array();
					$data['last_step']				= $data['status'];
					$data['last_step_cnt']			= $endData[0]['cnt'];
					$data['last_step_settleprice']	= $endData[0]['total_settleprice'];
				}

				//개인정보 마스킹 표시
				$data = $this->privatemasking->masking($data, 'order');

				$record[$k] = $data;
				$final_step	= $data['status'];

				$no--;
			}
		}

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign(array('page' => $page));
		$this->template->assign(array('final_no' => $no));
		$this->template->assign(array('final_step' => $final_step));
		$this->template->assign(array('record' => $record));
		$this->template->assign(array('npay_use' => $npay_use));
		$this->template->assign(array('status_invoice_cnt' => $status_invoice_cnt));
		$this->template->print_("tpl");
	}

	public function goods_export()
	{
		$this->load->model('exportmodel');
		$cfg_order = config_load('order');
		$export_code = $_GET['no'];
		$this->load->helper('shipping');
		$arr_delivery = get_delivery_url();
		$data_export 			= $this->exportmodel->get_export($export_code);
		if($data_export['international'] == 'domestic'){
			if($data_export['domestic_shipping_method'] == 'delivery'||$data_export['domestic_shipping_method'] == 'postpaid'){
				$data_export['mdelivery'] = $arr_delivery[$data_export['delivery_company_code']]['company'];
				$data_export['mdelivery_number'] = $data_export['delivery_number'];
				if($data_export['delivery_number']) $data_export['tracking_url'] = $arr_delivery[$data_export['delivery_company_code']]['url'].$data_export['delivery_number'];
			}
		}else{
			$data_export['mdelivery'] = $data_export['international_shipping_method'];
			$data_export['mdelivery_number'] = $data_export['international_delivery_no'];
			if($data_export['international_delivery_no']) $data_export['tracking_url'] = $arr_delivery[$data_export['international_shipping_method']]['url'].$data_export['international_delivery_no'];
		}
		$data_export['mstatus'] = $this->exportmodel->arr_step[$data_export['status']];
		$data_export_item 		= $this->exportmodel->get_export_item($export_code);

		$this->template->assign(array('cfg_order'	=> $cfg_order));
		$this->template->assign('data_export',$data_export);
		$this->template->assign('data_export_item',$data_export_item);
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function view()
	{
		$this->admin_menu();
		$this->tempate_modules();

		$this->load->helper('shipping');
		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('buyconfirmmodel');
		$this->load->model('goodsmodel');
		$this->load->model('eventmodel');
		$this->load->model('giftmodel');
		$this->load->helper('order');
		$this->load->model('orderpackagemodel');
		$this->load->library('privatemasking');

		$cfg_order			= config_load('order');
		$cfg_reserve		= ($this->reserves)?$this->reserves:config_load('reserve');
		$export_code		= $_GET['no'];
		$npay_use			= npay_useck();

		if(preg_match('/^B/', $export_code)){
			$bundle_export 		= $this->exportmodel->get_export_bundle($export_code);
			$data_export		= $bundle_export['bundle_export_info'];
			$export_order		= $bundle_export['bundle_order_info'];
		}else{
			$data_export 		= $this->exportmodel->get_export($export_code);
			$export_order[$data_export['export_code']]		= $data_export['order_seq'];
		}

		$arr_delivery			= get_delivery_url($data_export['shipping_provider_seq']);
		$data_buy_confirm	= $this->buyconfirmmodel->get_log_buy_confirm($data_export['export_seq']);
		//$export_shipping	= $this->ordermodel->get_shipping($data_export['order_seq'],$data_export['shipping_provider_seq']);
		//$export_shipping	= $export_shipping[0];
		$orders				= $this->ordermodel->get_order($data_export['order_seq']);

		if( $orders['recipient_phone'] == '--' ) $orders['recipient_phone'] = '';
		if( $orders['recipient_cellphone'] == '--' ) $orders['recipient_cellphone'] = '';

		//개인정보 마스킹 표시
		$orders = $this->privatemasking->masking($orders, 'order');

		$this->template->assign(array('orders'	=> $orders));

		// 본사배송 출고 변경 제한
		$data_export['able_export'] = true;
		if($data_export['shipping_provider_seq'] != $this->providerInfo['provider_seq']){
			$data_export['able_export'] = false;
		}

		// 개인정보 조회 로그
		//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderprint' 'orderexcel', 'exportexcel'
		$this->load->model('logPersonalInformation');
		$this->logPersonalInformation->insert('export',$this->managerInfo['manager_seq'],$data_export['export_seq']);

		if($data_export['international'] == 'domestic'){
			if($data_export['domestic_shipping_method'] == 'delivery'||$data_export['domestic_shipping_method'] == 'postpaid'){
				//$tmp = config_load('delivery_url',$data['delivery_company_code']);
				$data_export['mdelivery'] = ($data_export['mdelivery']) ? $data_export['mdelivery'] : $arr_delivery[$data_export['delivery_company_code']]['company'];
				$data_export['mdelivery_number'] = $data_export['delivery_number'];
				if($data_export['delivery_number']) {
					$data_export['tracking_url'] = ($data_export['tracking_url']) ? $data_export['tracking_url'] : $arr_delivery[$data_export['delivery_company_code']]['url'].$data_export['delivery_number'];
				}
			}
		}else{
			$data_export['mdelivery'] = $data_export['international_shipping_method'];
			$data_export['mdelivery_number'] = $data_export['international_delivery_no'];
			if($data_export['international_delivery_no']) $data_export['tracking_url'] = $arr_delivery[$data_export['international_shipping_method']]['url'].$data_export['international_delivery_no'];
		}

		$data_export['mstatus'] = $this->exportmodel->arr_step[$data_export['status']];
		$data_export_item 		= $this->exportmodel->get_export_item($export_code);

		foreach($data_export_item as $k => $data){
			$data['order_seq']	= $export_order[$data['export_code']];

			if	($data['goods_kind'] == 'coupon')	$coupon_cnt++;

			if($data['event_seq']) {
				$events = $this->eventmodel->get_event($data['event_seq']);
				if($events['title']) $data['event_title'] = $events['title'];
				if($events['event_type']) $data['event_type'] = $events['event_type'];
			}

			$data['gift_title'] = "";
			if($data['goods_type'] == "gift"){
				$giftlog = $this->giftmodel->get_gift_title($data_export['order_seq'],$data['item_seq']);
				$data['gift_title'] = $giftlog['gift_title'];
			}

			$goods[$data['goods_seq']]++;
			unset($data['inputs']);
			if( $data['opt_type'] == 'opt' ){
				$data['inputs'] = $this->ordermodel->get_input_for_option($data['item_seq'],$data['option_seq']);

				$real_stock = (int) $this->goodsmodel -> get_goods_option_stock(
					$data['goods_seq'],
					$data['option1'],
					$data['option2'],
					$data['option3'],
					$data['option4'],
					$data['option5']
				);

				$rstock = $this->ordermodel -> get_option_reservation(
					$this->cfg_order['ableStockStep'],
					$data['goods_seq'],
					$data['option1'],
					$data['option2'],
					$data['option3'],
					$data['option4'],
					$data['option5']
				);

				$badstock = $this->goodsmodel -> get_goods_option_badstock(
					$data['goods_seq'],
					$data['option1'],
					$data['option2'],
					$data['option3'],
					$data['option4'],
					$data['option5']
				);
			}else{
				$real_stock = (int) $this->goodsmodel -> get_goods_suboption_stock(
					$data['goods_seq'],
					$data['title1'],
					$data['option1']
				);
				$rstock = $this->ordermodel -> get_suboption_reservation(
					$this->cfg_order['ableStockStep'],
					$data['goods_seq'],
					$data['title1'],
					$data['option1']
				);
				$badstock = $this->goodsmodel -> get_goods_suboption_badstock(
					$data['goods_seq'],
					$data['title1'],
					$data['option1']
				);
			}

			//청약철회상품체크
			$ctgoods = $this->goodsmodel->get_goods($data['goods_seq']);
			$data['cancel_type'] = $ctgoods['cancel_type'];

			if($data['opt_type']=='opt'){
				$data['step_complete'] = $this->ordermodel -> get_option_export_complete($data_export['order_seq'],$data_export['shipping_provider_seq'],$data['item_seq'],$data['option_seq'],$export_code);
				//$data['step85'] = $this->refundmodel->get_refund_option_ea($data_export['shipping_provider_seq'],$data['item_seq'],$data['option_seq']);
			}
			if($data['opt_type']=='sub'){
				$data['step_complete'] = $this->ordermodel -> get_suboption_export_complete($data_export['order_seq'],$data_export['shipping_provider_seq'],$data['item_seq'],$data['option_seq']);
				//$data['step85'] = $this->refundmodel->get_refund_suboption_ea($data_export['shipping_provider_seq'],$data['item_seq'],$data['option_seq']);
			}

			$stock = (int) $real_stock - (int) $badstock - (int) $rstock;
			$data['real_stock'] = $real_stock;
			$data['stock'] = $stock;

			// 준비수량
			if($data['step'] >= 25 && $data['step'] < 75){
				$data['ready_ea'] = $data['opt_ea'] - $data['step_complete'] - $data['step85'];
			}

			$data['exp_step45'] = $data['step45'];
			$data['exp_step55'] = $data['step55'];
			$data['exp_step65'] = $data['step65'];
			$data['exp_step75'] = $data['step75'];
			$data['exp_step'.$data_export['status']] -= $data['ea'];

			$tot['ready_ea'] += $data['ready_ea'];
			$tot['exp_step45'] += $data['exp_step45'];
			$tot['exp_step55'] += $data['exp_step55'];
			$tot['exp_step65'] += $data['exp_step65'];
			$tot['exp_step75'] += $data['exp_step75'];
			$tot['step85'] += $data['step85'];


			$data['out_supply_price'] = $data['supply_price']*$data['ea'];
			$data['out_consumer_price'] = $data['consumer_price']*$data['ea'];
			$data['out_price'] = $data['price']*$data['ea'];

			$data['out_member_sale']					= $data['member_sale']*$data['ea'];
			$data['out_coupon_sale']					= ($data['download_seq'])?$data['coupon_sale']:0;
			$data['out_fblike_sale']						= $data['fblike_sale'];//*$data['ea']
			$data['out_mobile_sale']						= $data['mobile_sale'];//*$data['ea']
			$data['out_promotion_code_sale']			= $data['promotion_code_sale'];

			## 지급예정 마일리지/포인트, 지급 마일리지/포인트 pjm 2015-04-01
			//$data['out_reserve'] 	= $data['reserve']*$data['ea'];
			//$data['out_point'] 	= $data['point']*$data['ea'];
			$data['out_reserve']	= $data['reserve']*$data['reserve_ea'];
			$data['out_point']		= $data['point']*$data['reserve_ea'];
			$data['in_reserve']		= $data['reserve']*$data['reserve_buyconfirm_ea'];
			$data['in_point']		= $data['point']*$data['reserve_buyconfirm_ea'];


			if($data['provider_seq']){
				$query = $this->db->query("select provider_name from fm_provider where provider_seq=?",$data['provider_seq']);
				$tmp = $query->row_array();
				$data['provider_name'] = $tmp['provider_name'];
			}

			if($data['opt_type'] == 'opt' && $data['package_yn'] == 'y' ){
				$data['packages'] = $this->orderpackagemodel->get_option($data['option_seq']);
				$package_rowspan += count($data['packages']);
				foreach($data['packages'] as $key=>$data_package){
					$stock = (int) $data_package['stock'];
					$badstock = (int) $data_package['badstock'];
					$reservation = (int) $data_package['reservation'.$this->cfg_order['ableStockStep']];
					$ablestock = $stock - $badstock - $reservation;
					$data['packages'][$key]['ablestock'] = $ablestock;

					$data['packages'][$key]['stock'] = $stock;
					$data['packages'][$key]['real_stock'] = $ablestock;

					// 물류관리 창고 정보 추출
					if	($this->scm_cfg['use'] == 'Y' && $data['provider_seq'] == 1 ){
						if	($data_package['option_seq'] > 0){
							$optionStr		= $data_package['goods_seq'] . 'option' . $data_package['option_seq'];
							$whinfo			= $this->scmmodel->get_warehouse_stock($data_export['wh_seq'], 'optioninfo', '', array($optionStr));
							$data['packages'][$key]['whinfo']	= $whinfo[$optionStr];
						}
						$data['packages'][$key]['whinfo']['wh_seq']	= $data_export['wh_seq'];
					}
				}

			}else if($data['package_yn'] == 'y'){
				$data['packages'] = $this->orderpackagemodel->get_suboption($data['option_seq']);
				$package_sub_rowspan += count($data['packages']);

				foreach($data['packages'] as $key => $data_package){
					$stock = (int) $data['stock'];
					$badstock = (int) $data['badstock'];
					$reservation = (int) $data['reservation'.$this->cfg_order['ableStockStep']];
					$ablestock = $stock - $badstock - $reservation;

					$data['packages'][$key]['ablestock'] = $ablestock;

					$data['packages'][$key]['stock'] = $stock;
					$data['packages'][$key]['real_stock'] = $ablestock;

					// 물류관리 창고 정보 추출
					if	($this->scm_cfg['use'] == 'Y' && $data['provider_seq'] == 1 ){
						if	($data_package['option_seq'] > 0){
							$optionStr		= $data_package['goods_seq'] . 'option' . $data_package['option_seq'];
							$whinfo			= $this->scmmodel->get_warehouse_stock($data_export['wh_seq'], 'optioninfo', '', array($optionStr));
							$data['packages'][$key]['whinfo']	= $whinfo[$optionStr];
						}
						$data['packages'][$key]['whinfo']['wh_seq']	= $data_export['wh_seq'];
					}
				}
			}

			$data_export_item[$k] = $data;

			$tot['ea'] += $data['ea'];
			$tot['opt_ea'] += $data['opt_ea'];
			$tot['supply_price'] += $data['out_supply_price'];
			$tot['consumer_price'] += $data['out_consumer_price'];
			$tot['price'] += $data['out_price'];

			$tot['member_sale'] += $data['out_member_sale'];
			$tot['coupon_sale'] += $data['out_coupon_sale'];
			$tot['fblike_sale'] += $data['out_fblike_sale'];
			$tot['mobile_sale'] += $data['out_mobile_sale'];
			$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];

			$tot['goods_shipping_cost'] += $data['goods_shipping_cost'];

			$tot['reserve'] += $data['out_reserve'];
			$tot['point'] += $data['out_point'];
			$tot['in_reserve']			+= $data['in_reserve'];
			$tot['in_point']			+= $data['in_point'];

			$tot['real_stock'] += $real_stock;
			$tot['stock'] += $stock;

			// 출고준비상태일때는 구매확정불가
			if($data_export['status'] == '45'){
				$data['reserve_ea'] = 0;
			}
			
			//구매확정 수량(지급+소멸 수량), 잔여구매확정 수량(구매확정 대기) 2015-07-15 pjm
			$tot['buyconfirm_ea']		+= $data['reserve_buyconfirm_ea']+$data['reserve_destroy_ea'];
			$tot['buyconfirm_remain']		+= $data['reserve_ea'];

		}
		
		// 구매 확정 버튼 활성화 여부 체크
		$this->load->library('buyconfirmlib');
		$buyconfirmInfo = $this->buyconfirmlib->check_buyconfirm($data_export, $data_export_item);
		$data_export['buyconfirmInfo'] = $buyconfirmInfo;
		
		$tot['goods_cnt'] = array_sum($goods);

		$order_list		= json_encode(array_values(array_unique($export_order)));

		$this->template->assign(array('coupon_cnt'	=> $coupon_cnt));
		$this->template->assign(array('order_list'	=> $order_list));
		$this->template->assign(array('cfg_order'	=> $cfg_order));
		$this->template->assign(array('cfg_reserve'	=> $cfg_reserve));
		$this->template->assign(array('data_buy_confirm' => $data_buy_confirm));
		$this->template->assign(array('export_shipping'	=> $export_shipping));
		$this->template->assign(array('npay_use'	=> $npay_use));
		$this->template->assign(
			array('data_export'=>$data_export,'data_export_item'=>$data_export_item,'tot'=>$tot)
		);
		$this->template->assign('query_string',$_GET['query_string']);//######################## 16.12.15 gcs yjy : 검색조건 유지되도록

		if($_GET['mode'] == 'export_list'){
			$file_path = str_replace('view.html','view_list.html',$this->template_path());
			$this->template->define(array('tpl' => $file_path));
		}else{
			$this->template->define(array('tpl' => $this->template_path()));
		}

		$this->template->print_("tpl");
	}

	public function batch_status(){

		$this->load->helper('order');
		$this->load->model('exportmodel');
		$this->load->helper('shipping');
		$this->load->model('ordermodel');
		$this->load->model('providermodel');
		$this->load->model('providershipping');
		$this->load->model('goodsmodel');
		$this->load->model('shippingmodel');
		$this->load->library('privatemasking');

		$cfg_order = config_load('order');

		//npay 사용확인
		$npay_use = npay_useck();

		$this->load->library('Connector');
		$connector	= $this->connector::getInstance();

		//마켓연동 연동 서비스 사용유무
		if ($connector->isConnectorUse()) {
			$connectorUse	= true;
			$this->load->model('connectormodel');
			$marketList		= $this->connectormodel->getUseAllMarkets();
		}  else {
			$connectorUse	= false;
			$marketList		= array();
		}
		
		$this->template->assign(array('connectorUse' => $connectorUse, 'marketList' => $marketList));
		
		// 기본 검색 설정
		$this->load->model('searchdefaultconfigmodel');
		$data_search_default_order = $this->searchdefaultconfigmodel->get_search_default_config('admin/order/order_export_popup');
		$data_search_default_export = $this->searchdefaultconfigmodel->get_search_default_config('admin/export/batch_status');
		$data_search_default_str = $data_search_default_order['search_info'] ."&". $data_search_default_export['search_info'];
		parse_str($data_search_default_str, $data_search_default);
		$this->template->assign(array('data_search_default'=>$data_search_default));
		$export_default_search_path = dirname($this->template_path()).'/../setting/_export_default_search.html';
		$this->template->define(array('export_default_search_path'=>$export_default_search_path));
		switch ( $data_search_default['export_default_period'] )  {
			case "-1 day" :
				$start_date = date('Y-m-d');
				$end_date = date('Y-m-d');
				break;
			case "-1 mon" :
				$start_date = date('Y-m-d',strtotime('-1 month'));
				$end_date = date('Y-m-d');
				break;
			case "-3 mon" :
				$start_date = date('Y-m-d',strtotime('-3 month'));
				$end_date = date('Y-m-d');
				break;
			case "all" :
				$start_date = "";
				$end_date = "";
				break;
			default :
				$start_date = date('Y-m-d',strtotime('-1 week'));
				$end_date = date('Y-m-d');
				break;
		}

		if( count($_GET) == 0  ){
			$_GET['data_field'] = $data_search_default['export_default_date_field'];
			$_GET['start_search_date'] = $start_date;
			$_GET['end_search_date'] = $end_date;
			$_GET['status'] = $data_search_default['export_default_status'][0];
		}

		if(! $_GET['status']) $_GET['status'] = $data_search_default['export_default_status'][0];


		// 검색어 설명 문구
		if(!$_GET['search_type']){
			$_GET['search_type'] = 'export_code';
		}

		if(!$_GET['page']) $_GET['page'] = 1;

		$arr_search_type = array('export_code'=>'출고번호','order_seq'=>'주문번호','userid'=>'아이디','order_user_name'=>'주문자','recipient_user_name'=>'수령자','depositor'=>'입금자','order_email'=>'이메일','order_phone'=>'연락처','order_cellphone'=>'휴대폰','goods_name'=>'상품명','goods_seq'=>'상품번호','goods_code'=>'상품코드','npay_order_id'=>'N페이주문번호','npay_product_order_id'=>'N페이상품주문번호');
		if($_GET['keyword']){
			$_GET['search_type_text'] = sprintf("%s : %s", $arr_search_type[$_GET['search_type']], $_GET['keyword']);
		}

		if ($_GET['status'] == '45' || $_GET['status'] == '55')
			$_GET['isExportedList']	= true;
		else
			$_GET['isExportedList']	= false;


		if ($_GET['isExportedList'] == true && is_array($_GET['selectMarkets']) === true) {
			$_GET['hasMarketOrders']	= true;
		} else {
			$_GET['hasMarketOrders']	= false;
		}

		$this->template->assign(array('connectorUse' => $connectorUse, 'isExportedList' => $_GET['isExportedList'], 'hasMarketOrders' => $_GET['hasMarketOrders'], 'marketList' => $marketList));

		$params = $_GET;
		$exist_goods = false;

		$params['base_inclusion'][] = 2;
		$params['provider_seq'] = $this->providerInfo['provider_seq'];

		if($params){
			list($query,$bind) = $this->exportmodel->get_change_status_list($params);
			$result_page = select_page(100,$_GET['page'],10,$query,$bind);
			$goodsflow_flag = false; // 굿스플로 일괄 처리 버튼 제어변수 :: 2016-10-10 lwh
			foreach($result_page['record'] as $data_export_code){

				$mode		= '';
				if($data_export_code['is_bundle_export'] == 'Y'){
					$mode	= 'bundle';
					$data_export_code['export_code']	= $data_export_code['bundle_export_code'];
				}

				$query = $this->exportmodel->get_change_status_detail($data_export_code['export_code']);
				foreach($query->result_array() as $data){

										// 주문서 결제정보
					$orders	= $this->ordermodel->get_order($data['order_seq']);
					$data['pg'] = $orders['pg'];

					// 배송정보 기본 추출 :: 2016-10-10 lwh
					$shipping_group_arr	= explode('_', $data['shipping_group']);
					$data['shipping_grp_seq']	= $shipping_group_arr[0];
					$data['shipping_set_seq']	= $shipping_group_arr[1];
					$data['shipping_set_code'] = ($shipping_group_arr[3]) ? $shipping_group_arr[2].'_'.$shipping_group_arr[3] : $shipping_group_arr[2];

					// 배송출고지 추출 :: 2016-10-10 lwh
					$sql = "SELECT * FROM fm_shipping_grouping WHERE shipping_group_seq = ?";
					$grp_query	= $this->db->query($sql,$data['shipping_grp_seq']);
					$grp_res	= $grp_query->row_array();
					$send_add = $this->shippingmodel->get_shipping_address($grp_res['sendding_address_seq'], $grp_res['sendding_scm_type']);
					if($send_add['address_nation'] == 'korea'){
						$send_add['view_address'] = ($send_add['address_type'] == 'street') ? $send_add['address_street'] : $send_add['address'];
						$send_add['view_address'] = '(' . $send_add['address_zipcode'] . ') ' . $send_add['view_address'] . ' ' . $send_add['address_detail'];
					}else{
						$send_add['view_address'] = '(' . $send_add['international_postcode'] . ') ' . $send_add['international_country'] . ' ' . $send_add['international_town_city'] . ' ' . $send_add['international_county'] . ' ' . $send_add['international_address'];
					}
					$data['sending_address'] = $send_add;
					// $data['refund_address']		= $this->shippingmodel->get_shipping_address($grp_res['refund_address_seq'], $grp_res['refund_scm_type']); // 일단 필요없음

					// 배송방법 예외처리 추가 :: 2016-10-10 lwh
					if(!$data['shipping_method']) $data['shipping_method'] = $data['domestic_shipping_method'];
					if(!$data['shipping_set_name']){
						$data['shipping_set_name'] = $this->shippingmodel->shipping_method_arr[$data['shipping_method']];
					}

					// 굿스플로 일괄 처리 버튼 제어 :: 2016-10-10 lwh
					if($data['shipping_method'] == 'delivery' || $data['shipping_method'] == 'postpaid')	$goodsflow_flag = true;

					if($data_export_code['is_bundle_export'] == 'Y'){
						$data['export_code']		= $data['bundle_export_code'];
						$data['is_bundle_export']	= 'Y';
						$data['bundle_order_list']	= array_values(array_unique(explode(',',$data_export_code['has_order_list'])));
					}

					// 매장수령 정보 추출 :: 2016-10-10 lwh
					if($data['shipping_method'] == 'direct_store'){
						$ship_store_arr = $this->shippingmodel->get_shipping_store($data['shipping_set_seq'],'shipping_set_seq');
						$data['shipping_store_info'] = $ship_store_arr;
					}

					if($data['option_seq']){
						$data['stock'] = $this->goodsmodel->get_goods_option_stock($data['goods_seq'],$data['option1'],$data['option2'],$data['option3'],$data['option4'],$data['option5']);
						$goods_code = $data['opt_goods_code'];
					}

					if($data['suboption_seq']){
						$data['stock'] = $this->goodsmodel->get_goods_suboption_stock($data['goods_seq'],$data['subtitle'],$data['suboption']);
						$goods_code = $data['subopt_goods_code'];
					}

					// opt_goods_code subopt_goods_code
					$data['bar_goods_code'] = $goods_code;
					if ( ! preg_match("/^[a-z0-9:_\/-]+$/i", $goods_code))
					{
						$data['bar_goods_code'] = "";
					}
					$data['bar_goods_code'] = $data['bar_goods_code'];

					if($data['inputs']){
						$arr_inputs = explode(',',$data['inputs']);
						foreach($arr_inputs as $str_input){
							$row_input = explode(':',$str_input);
							$data['subinputs'][] = array('type'=>$row_input[0],'title'=>$row_input[1],'value'=>$row_input[2]);
						}
					}

					$shipping_provider_seq = $this->providerInfo['provider_seq'];
					if( $data['shipping_provider_seq'] ) $shipping_provider_seq = $data['shipping_provider_seq'];

					if(! $data_provider_shipping_method[$shipping_provider_seq] ){
						$data_provider_shipping_method[$shipping_provider_seq] = $this->providershipping->get_provider_shipping($shipping_provider_seq);
					}

					$data['shipping'] = $data_provider_shipping_method[$shipping_provider_seq];


					if(!$delivery_company_array[$data['shipping_provider_seq']]){
						$delivery_company_array[$shipping_provider_seq] = get_shipping_company_provider($shipping_provider_seq);
					}
					$data['delivery_company_array'] = $delivery_company_array[$shipping_provider_seq];

					if($data['goods_kind'] == 'coupon'){
						$data['couponinfo'] = get_goods_coupon_view($data['export_code']);
						$log_params['export_code']	= $data['export_code'];
						$log_params['send_kind']	= 'mail';
						$data['mail_send_log'] = $this->exportmodel->get_coupon_export_send_log($log_params, 2);
						$log_params['send_kind']	= 'sms';
						$data['sms_send_log'] = $this->exportmodel->get_coupon_export_send_log($log_params, 2);
						$data['confirm_date'] = $this->exportmodel->arr_status[$data['socialcp_confirm_date']];
						$data['coupon_use_value']	= $data['coupon_input'] - $data['coupon_remain_value'];
						$data['mstatus_arr'][0]		= $this->exportmodel->arr_status[$data['status']];
						$data['mstatus_arr'][1]		= $this->exportmodel->socialcp_status[$data['socialcp_status']][2] . $this->exportmodel->socialcp_status[$data['socialcp_status']][0];
					}else{
						$exist_goods = true;
					}
					$data['mstatus'] = $this->exportmodel->arr_status[$data['status']];
					$data['num'] = $data_export_code['_no'];

					//#23611 2019-02-07 ycg 주문 상태가 출고 상태에 포함된 경우 체크박스 선택되도록 수정
					$export_status = in_array($data['status'], array_keys($this->exportmodel->arr_status));
					$export_status!=false?$data['export_status']='y':$data['export_status']='n';

					if ($data['linkage_id'] == 'connector')
						$data['linkage_mallname_text']	= $marketList[$data['linkage_mall_code']]['name'];

					//개인정보 마스킹 표시
					$data = $this->privatemasking->masking($data, 'order');

					$result[$data['export_code']][] = $data;
				}
			}

			foreach($result as $export_code =>$data){
				foreach($data as $k=>$data_option){
					$data1[$export_code]++;
					if( !$result[$export_code][0]['tot_goods_name'] ){
						$result[$export_code][0]['tot_goods_name'] = $data_option['goods_name'];
						$result[$export_code][0]['tot_image'] = $data_option['image'];
					}
					$result[$export_code][0]['tot_stock'] += (int) $data_option['stock'];
					$result[$export_code][0]['tot_ea'] += (int) $data_option['opt_ea'] + (int) $data_option['subopt_ea'];
					$result[$export_code][0]['tot_step85'] += (int) $data_option['opt_step85'] + (int) $data_option['subopt_step85'];
					$result[$export_code][0]['tot_sended_ea'] += (int) $data_option['opt_step45'] + (int) $data_option['opt_step55']+ (int) $data_option['opt_step65']+ (int) $data_option['opt_step75'];
					$result[$export_code][0]['tot_sended_ea'] += (int) $data_option['subopt_step45'] + (int) $data_option['subopt_step55']+ (int) $data_option['subopt_step65']+ (int) $data_option['subopt_step75'];
					$result[$export_code][0]['tot_export_ea'] += (int) $data_option['ea'];
				}
				foreach($data as $k=>$data_option){
					$result[$export_code][$k]['rowspan'] = $data1[$export_code];
				}
				$result[$export_code][0]['tot_request_ea'] =  $result[$export_code]['tot_ea'] - $result[$export_code]['tot_sended_ea'] - $result[$export_code]['tot_step85'];
			}
		}

		// 전체 택배사 추출을 위한 데이터 :: 2015-07-08 lwh
		if($this->providerInfo['provider_seq']){
			$all_delivery_company = get_shipping_company_all('domestic','delivery',$this->providerInfo['provider_seq']);

			$this->template->assign(array('all_delivery'=> $all_delivery_company));
		}

		$this->load->helper('shipping');
		$shipping = use_shipping_method();
		if( $shipping ) foreach($shipping as $key => $data){
			if($data) $shipping_cnt[$key] = count($data);
		}

		$shipping_policy['policy'] 	= $shipping;
		foreach($shipping_policy['policy'][0] as $k => $method) $domestic_method[$method['code']] = $method['method'];

		$this->tempate_modules();
		$invoice_guide_path = dirname($this->template_path()).'/../order/_invoice_guide.html';
		$this->template->define(array('invoice_guide'=>$invoice_guide_path));

		// 입점사 정보 가져오기
		$present_provider_seq = 1;
		if($this->providerInfo['provider_seq']){
			$present_provider_seq = $this->providerInfo['provider_seq'];
		}
		$data_present_provider = $this->providermodel->get_provider($present_provider_seq);
		$this->template->assign(array('data_present_provider'=>$data_present_provider));

		$provider	= $this->providermodel->provider_goods_list_sort();
		$this->template->assign(array('provider'=>$provider));

		// 기본 출고 처리 설정
		$default_stock_check_path = dirname($this->template_path()).'/../order/_default_stock_check.html';
		$this->template->define(array('default_stock_check'=>$default_stock_check_path));

		// 굿스플로 설정 로드 :: 2015-06-30 lwh
		$this->load->model('goodsflowmodel');
		$config_goodsflow = $this->goodsflowmodel->get_goodsflow_setting($present_provider_seq);
		if($config_goodsflow['goodsflow_step']=='1'){
			//$config_goodsflow['goodsflow_step'] = '2';
			$service_cnt = $this->goodsflowmodel->get_service_info('view');
			$config_goodsflow['gf_deliveryCode'] = 'auto_'.$config_goodsflow['deliveryCode'];
			$this->template->assign('gf_config',$config_goodsflow);
			$this->template->assign('service_cnt',$service_cnt);
		}

		$this->template->assign(array('batch_goodsflow' => $goodsflow_flag));
		$this->template->assign(array('ship_set_code' => $this->shippingmodel->ship_set_code));
		$this->template->assign(array('cfg_order'	=> $cfg_order));
		$this->template->assign(array('shipping_policy'	=> $shipping_policy));
		$this->template->assign(array('domestic_method'	=> $domestic_method));
		$this->template->assign('data_export',$result);
		$this->template->assign('data_page',$result_page['page']);
		$this->template->assign('exist_goods',$exist_goods);
		$this->template->assign('npay_use',$npay_use);

		$this->template->define(array('tpl' => $this->template_path()));
		$this->template->print_("tpl");
	}

	public function batch_status_popup()
	{
		$this->load->model("exportmodel");

		$status_title['55'] = "출고완료";
		$status_title['65'] = "출고완료";
		$status_title['75'] = "출고완료";
		$status_title['45'] = "출고준비";

		$this->template->assign('status_title',$status_title);
		$this->template->assign('params',$this->input->post());
		$this->template->define(array('tpl' => $this->template_path()));
		$this->template->print_("tpl");
	}


	public function export_print(){
		redirect(uri_string()."s?pagemode={$_GET['pagemode']}&export={$_GET['export']}|&order={$_GET['ordno']}|");
	}


	public function export_prints(){

		$this->tempate_modules();
		$this->load->model('barcodemodel');
		$this->load->model('returnmodel');
		$this->load->model('membermodel');
		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('orderpackagemodel');
		$this->load->helper('order');
		$this->load->helper('shipping');
		$this->load->library('orderlibrary');
		$this->load->library('privatemasking');

		$arr_shipping_method	= get_shipping_method('all');

		$provider_seq	= $this->providerInfo['provider_seq'];
		$query			= $this->db->query("select * from fm_setting_print where provider_seq=? ",$provider_seq);
		$prints_data	= $query->row_array();
		$this->template->assign($prints_data);


		// 개인정보 조회 모델 로드
		$this->load->model('logPersonalInformation');

		$file_path	= $this->template_path();
		$ordarr 	= explode("|",$_GET['order']);
		$exparr 	= explode("|",$_GET['export']);
		unset($ordarr[count($ordarr)-1]);
		unset($exparr[count($exparr)-1]);

		if(!$exparr[0] && $ordarr){
			$query = $this->exportmodel->get_export_for_orders($ordarr);
			unset($ordarr);
			unset($exparr);
			foreach($query->result_array() as $data_export){
				$ordarr[] = $data_export['order_seq'];
				$exparr[] = $data_export['export_code'];
			}
		}

		if(!$ordarr[0] && $exparr){
			$query = $this->exportmodel->get_exports($exparr);
			unset($ordarr);
			unset($exparr);
			foreach( $query->result_array() as $data_export ){
				$ordarr[] = $data_export['order_seq'];
				$exparr[] = ($data_export['is_bundle_export'] == 'Y') ? $data_export['bundle_export_code'] : $data_export['export_code'];
			}
		}

		if(count( $exparr ) == 0){
			$msg = "인쇄할 출고가 없습니다.";
			pageClose($msg);
			exit;
		}

		for($i=0;$i<count($ordarr);$i++){
			$tot = array();
			$base_order_seq = $ordarr[$i];
			$export_code	= $exparr[$i];

			if(!$export_code) continue;
			unset($export_order);
			if(preg_match('/^B/', $export_code)){
				$bundle_export 		= $this->exportmodel->get_export_bundle($export_code);
				$data_export		= $bundle_export['bundle_export_info'];
				$export_order		= array_unique($bundle_export['bundle_order_info']);
			}else{
				$data_export 								= $this->exportmodel->get_export($export_code);
				$export_order[$data_export['export_code']]		= $data_export['order_seq'];
			}

			$data_export_item 					= $this->exportmodel->get_export_item($export_code);
			$package_rowspan = 0;
			$package_sub_rowspan = 0;
			foreach($data_export_item as $k => $data){
				if($data['opt_type'] == 'opt' && $data['package_yn'] == 'y' ){
					$data['packages'] = $this->orderpackagemodel->get_option($data['option_seq']);
					$package_rowspan += count($data['packages']);
				}else if($data['package_yn'] == 'y'){
					$data['packages'] = $this->orderpackagemodel->get_suboption($data['option_seq']);
					$package_sub_rowspan += count($data['packages']);
				}
				$data_export_item[$k] = $data;
			}
			$export_shipping_seq 			= $data_export_item[0]['shipping_seq'];
			$tmp_export_shipping			= $this->ordermodel->get_order_shipping($base_order_seq,null,$export_shipping_seq);
			list($data_export_shipping) 	= array_values($tmp_export_shipping);

			// 택배 선착불 정보 추가 :: 2018-01-02 lwh
			$data_export['out_shipping_method'] = $arr_shipping_method[$tmp_export_shipping[$data_export['shipping_group']]['shipping_type']];

			//2016.04.21 바코드 설정 추가 pjw
			foreach($data_export_item as $k => $data){

				if($data['goods_code']){
					$data['barcode_image'] = $this->barcodemodel->create_barcode_html('use_code', $data['goods_code']);
				}

				foreach($data['packages'] as $key=>$val){
					if($val['goods_code']){
						$val['barcode_image'] = $this->barcodemodel->create_barcode_html('use_code', $val['goods_code']);
						$data['packages'][$key] = $val;
					}
				}

				$data_export_item[$k] = $data;
			}

			// 개인정보 조회 로그
			//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderprint' 'orderexcel', 'exportexcel'
			$this->logPersonalInformation->insert('exportprint',$this->managerInfo['manager_seq'],$data_export['export_seq']);

			unset($order_list,$shipping_tot);
			foreach((array)$export_order as $order_seq){
				$tot		= array();
				$orders 	= $this->ordermodel->get_order($order_seq);
				$items 		= $this->ordermodel->get_item($order_seq);

				$orders['mpayment'] = $this->arr_payment[$orders['payment']];

				if ($orders['pg'] == 'naverpayment') {
					$orders['mpayment'] = $this->arr_payment[$orders['pg'].'_'.$orders['payment']];
				}

				$orders['mstep'] 	= $this->arr_step[$orders['step']];

				$total_sale = (int) $orders['enuri'];

				foreach($items as $key=>$item){
					$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
					$suboptions = $this->ordermodel->get_suboption_for_item($item['item_seq']);

					if($options) foreach($options as $k => $data){
						$real_stock = $this->goodsmodel -> get_goods_option_stock(
								$item['goods_seq'],
								$data['option1'],
								$data['option2'],
								$data['option3'],
								$data['option4'],
								$data['option5']
						);

						$rstock = $this->ordermodel -> get_option_reservation(
								$this->cfg_order['ableStockStep'],
								$item['goods_seq'],
								$data['option1'],
								$data['option2'],
								$data['option3'],
								$data['option4'],
								$data['option5']
						);

						$stock = (int) $real_stock - (int) $rstock;
						$data['mstep']		= $this->arr_step[$data['step']];
						$data['real_stock'] = $real_stock;
						$data['stock'] = $stock;

						$data['out_supply_price'] = $data['supply_price']*$data['ea'];
						$data['out_commission_price'] = $data['commission_price']*$data['ea'];
						$data['out_consumer_price'] = $data['consumer_price']*$data['ea'];
						$data['out_price'] = $data['price']*$data['ea'];
						$data['out_org_price'] = $data['org_price']*$data['ea'];
						$data['out_refund_price'] = $data['price']*$data['refund_ea'];

						//promotion sale
						$data['out_event_sale'] = $data['event_sale'];
						$data['out_multi_sale'] = $data['multi_sale'];
						$data['out_member_sale'] = $data['member_sale']*$data['ea'];
						$data['out_coupon_sale'] = ($data['download_seq'])?$data['coupon_sale']:0;
						$data['out_fblike_sale'] = $data['fblike_sale'];
						$data['out_mobile_sale'] = $data['mobile_sale'];
						$data['out_referer_sale'] = $data['referer_sale'];
						$data['out_promotion_code_sale'] = $data['promotion_code_sale'];

						// total sale
						$out_sale_price	= $data['out_event_sale'] + $data['out_multi_sale'] + $data['out_member_sale'] + $data['out_coupon_sale'] + $data['out_promotion_code_sale'] + $data['out_fblike_sale'] + $data['out_mobile_sale'] + $data['out_referer_sale'];
						$total_sale += $out_sale_price;

						// 할인가격
						$data['out_sale_price'] = $data['out_price'] - $out_sale_price;
						$data['sale_price'] = $data['out_sale_price'] / $data['ea'];

						//use
						$data['out_reserve']	= $data['reserve']*$data['reserve_ea'];			//예상마일리지
						$data['out_point'] 		= $data['point']*$data['reserve_ea'];				//예상마일리지
						$data['in_reserve']		= $data['reserve']*$data['reserve_buyconfirm_ea'];	//지급마일리지
						$data['in_point']		= $data['point']*$data['reserve_buyconfirm_ea'];	//지급포인트

						$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];

						###
						$input = array();
						$sql = "SELECT * FROM fm_order_item_input WHERE order_seq = '{$order_seq}' and item_seq = '{$data[item_seq]}' and item_option_seq='{$data[item_option_seq]}'";
						$query = $this->db->query($sql);
						foreach($query->result_array() as $rows){
							$input[] = $rows;
						}
						$data['inputs'] = $input;

						foreach($data_export_item as $k => $data_export){
							$data_export_item[$k]['inputs'] = $input;
						}

						if($suboptions) foreach($suboptions as $data_sub){
							if( $data_sub['item_option_seq'] == $data['item_option_seq']){
								$data['suboptions'][] = $data_sub;
							}
						}

						$options[$k] = $data;

						$tot['ea'] += $data['ea'];
						$tot['refund_ea'] += $data['refund_ea'];
						$tot['supply_price'] += $data['out_supply_price'];
						$tot['commission_price'] += $data['out_commission_price'];
						$tot['consumer_price'] += $data['out_consumer_price'];
						$tot['price'] += $data['out_price'];
						$tot['oprice'] += $data['price'];
						$tot['out_sale_price'] += $data['out_sale_price'];
						$tot['sale_price'] += $data['sale_price'];

						//promotion sale
						$tot['event_sale'] += $data['out_event_sale'];
						$tot['multi_sale'] += $data['out_multi_sale'];
						$tot['member_sale'] += $data['out_member_sale'];
						$tot['coupon_sale'] += $data['out_coupon_sale'];
						$tot['fblike_sale'] += $data['out_fblike_sale'];
						$tot['mobile_sale'] += $data['out_mobile_sale'];
						$tot['referer_sale'] += $data['out_referer_sale'];
						$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];

						//use sale
						$tot['reserve'] += $data['out_reserve'];
						$tot['point'] += $data['out_point'];
							$tot['in_reserve']	+= $data['in_out_reserve'];
							$tot['in_point']	+= $data['in_point'];

						$tot['real_stock'] += $real_stock;
						$tot['stock'] += $stock;

						$return_item = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq']);
						$able_return_ea += (int) $data['step75'] - (int) $return_item['ea'];

					}

					if($suboptions) foreach($suboptions as $k => $data){
						$real_stock = $this->goodsmodel -> get_goods_suboption_stock(
								$item['goods_seq'],
								$data['title'],
								$data['suboption']
						);
						$rstock = $this->ordermodel -> get_suboption_reservation(
								$this->cfg_order['ableStockStep'],
								$item['goods_seq'],
								$data['title'],
								$data['suboption']
						);

						$stock = (int) $real_stock - (int) $rstock;
						$data['real_stock'] = (int) $real_stock;
						$data['stock'] = (int) $stock;

						###
						$data['out_supply_price'] = $data['supply_price']*$data['ea'];
						$data['out_commission_price'] = $data['commission_price']*$data['ea'];
						$data['out_consumer_price'] = $data['consumer_price']*$data['ea'];
						$data['out_price'] = $data['price']*$data['ea'];
						$data['out_org_price'] = $data['org_price']*$data['ea'];
						$data['out_refund_price'] = $data['price']*$data['refund_ea'];

						// total sale
						$out_sale_price	= $data['out_event_sale'] + $data['out_multi_sale'] + $data['out_member_sale'] + $data['out_coupon_sale'] + $data['out_promotion_code_sale'] + $data['out_fblike_sale'] + $data['out_mobile_sale'] + $data['out_referer_sale'];
						$total_sale += $out_sale_price;

						// 할인가격
						$data['out_sale_price'] = $data['out_price'] - $out_sale_price;
						$data['sale_price'] = $data['out_sale_price'] / $data['ea'];

						//promotion sale
						$data['out_event_sale'] = $data['event_sale'];
						$data['out_multi_sale'] = $data['multi_sale'];
						$data['out_member_sale'] = $data['member_sale']*$data['ea'];
						$data['out_coupon_sale'] = ($data['download_seq'])?$data['coupon_sale']:0;
						$data['out_fblike_sale'] = $data['fblike_sale'];
						$data['out_mobile_sale'] = $data['mobile_sale'];
						$data['out_referer_sale'] = $data['referer_sale'];
						$data['out_promotion_code_sale'] = $data['promotion_code_sale'];


						//member use
						$data['out_reserve']	= $data['reserve']*$data['reserve_ea'];
						$data['out_point']		= $data['point']*$data['reserve_ea'];
						$data['in_reserve']		= $data['reserve']*$data['reserve_buyconfirm_ea'];
						$data['in_point']		= $data['point']*$data['reserve_buyconfirm_ea'];

						$data['mstep']	= $this->arr_step[$data['step']];
						$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];
						$suboptions[$k] = $data;



						$tot['ea'] += $data['ea'];
						$tot['refund_ea'] += $data['refund_ea'];
						$tot['supply_price'] 	+= $data['out_supply_price'];
						$tot['commission_price'] 	+= $data['out_commission_price'];
						$tot['consumer_price'] 	+= $data['out_consumer_price'];

						//promotion sale
						$tot['event_sale'] += $data['out_event_sale'];
						$tot['multi_sale'] += $data['out_multi_sale'];
						$tot['member_sale'] += $data['out_member_sale'];
						$tot['coupon_sale'] += $data['out_coupon_sale'];
						$tot['fblike_sale'] += $data['out_fblike_sale'];
						$tot['mobile_sale'] += $data['out_mobile_sale'];
						$tot['referer_sale'] += $data['out_referer_sale'];
						$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];

						$tot['out_sale_price'] += $data['out_sale_price'];
						$tot['sale_price'] += $data['sale_price'];

						//member use
						$tot['reserve'] += $data['out_reserve'];
						$tot['point'] += $data['out_point'];
							$tot['in_reserve']	+= $data['in_out_reserve'];
							$tot['in_point']	+= $data['in_point'];

						$tot['oprice'] 			+= $data['price'];
						$tot['price'] 			+= $data['out_price'];
						$tot['real_stock'] 		+= $real_stock;
						$tot['stock'] 			+= $stock;

						$return_item = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_suboption_seq']);
						$able_return_ea += (int) $data['step75'] - (int) $return_item['ea'];

					}

					$item['rowspan']			= count($options) + count($suboptions);
					$item['suboptions']			= $suboptions;
					$item['options']			= $options;
					$items[$key] 				= $item;

					$tot['goods_shipping_cost']	+= $item['goods_shipping_cost'];
				}

				/* 주문상품을 배송그룹별로 분할 */
				$shipping = $this->ordermodel->get_order_shipping($order_seq);
				$shipping_group_items=array();
				foreach($items as $item){
					if( $item['goods_kind'] == 'goods' ){
						$shipping_group_items[$item['shipping_seq']]['goods_items'][] = $item;
					}else{
						$shipping_group_items[$item['shipping_seq']]['couopn_items'][] = $item;
					}

					$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost_sum']+= $item['goods_shipping_cost'];
					$shipping_group_items[$item['shipping_seq']]['shipping'] = $result_shipping;
					$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost']+= $item['goods_shipping_cost'];
					$shipping_group_items[$item['shipping_seq']]['rowspan'] += $item['rowspan'];
					$shipping_group_items[$item['shipping_seq']]['items'][] = $item;
					$shipping_group_items[$item['shipping_seq']]['items'][0]['options'][0]['shipping_division']	= 1;
					$shipping_group_items[$item['shipping_seq']]['totalitems'] += count($item['options'])+count($item['suboptions']);
				}

				foreach($shipping_group_items as $shipping_seq=>$row){
					$query = $this->db->query("select a.*, b.provider_name
					from fm_order_shipping a
					inner join fm_provider b on a.provider_seq = b.provider_seq
					where a.shipping_seq=?",$shipping_seq);
					$shipping = $query->row_array();
					$shipping['shipping_method_name'] = $arr_shipping_method[$shipping['shipping_method']];
					$shipping_group_items[$shipping_seq]['shipping'] = $shipping;

					$shipping_tot[$order_seq]['shipping_promotion_code_sale']	+= $shipping['shipping_promotion_code_sale'];
					$shipping_tot[$order_seq]['shipping_coupon_sale']			+= $shipping['shipping_coupon_sale'];

					$total_sale += $shipping_tot[$order_seq]['shipping_promotion_code_sale'];
					$total_sale += $shipping_tot[$order_seq]['shipping_coupon_sale'];

					if($shipping['shipping_method']=='delivery'){
						$shipping_tot[$order_seq]['shipping_cost']				+= $shipping['shipping_cost'];
						$shipping_tot[$order_seq]['add_shipping_cost']			+= $shipping['add_delivery_cost'];
					}
					if($shipping['shipping_method']=='each_delivery'){
						$shipping_tot[$order_seq]['goods_shipping_cost']		+= $row['goods_shipping_cost'] + $shipping['add_delivery_cost'];
						$shipping_tot[$order_seq]['add_goods_shipping_cost']	+= $shipping['add_delivery_cost'];
					}

					$shipping_tot[$order_seq]['international_cost']				+= $shipping['international_cost'];
				}

				$orders['total_sale'] = $total_sale;

				// 회원 정보 가져오기
				if($orders['member_seq']){
					$members = $this->membermodel->get_member_data($orders['member_seq']);
					$this->template->assign(array('members'=>$members));
				}

				// 배송방법
				$orders['mshipping'] = $this->ordermodel->get_delivery_method($orders);

				// 외부주문 linkage_mallname_text 정의 2020-05-27 
				$this->orderlibrary->get_order_market_name($orders);

				//개인정보 마스킹 표시
				$orders = $this->privatemasking->masking($orders, 'order');

				$order_list[$order_seq]['order']		= $orders;
				$order_list[$order_seq]['items']		= $items;
				$order_list[$order_seq]['items_tot']	= $tot;
				$order_list[$order_seq]['shipping_tot']	= $shipping_tot[$order_seq];

			}

			// 2016.04.20 바코드 노출 기능 추가 pjw
			$expert_barcode = $this->barcodemodel->create_barcode_html('use_code_order', $data_export['export_code'], 30);
			$data_export['export_barcode'] = $expert_barcode;

			$data_arr['order_list']				= $order_list;
			$data_arr['order']					= $orders;
			$data_arr['data_export_item'] 		= $data_export_item;
			$data_arr['data_export'] 			= $data_export;
			$data_arr['data_export_shipping'] 	= $data_export_shipping;

			$data_arr['items']			= $items;
			$data_arr['items_tot']		= $tot;
			$data_arr['bank']			= $bank;
			$data_arr['pay_log']		= $pay_log;
			$data_arr['process_log']	= $process_log;
			$data_arr['cancel_log']		= $cancel_log;
			$data_arr['data_return']	= $data_return;
			$data_arr['data_exchange']	= $data_exchange;
			$data_arr['data_refund']	= $data_refund;
			$data_arr['shipping_policy']= $shipping_policy;
			$data_arr['goods_kind_arr']= $goods_kind_arr;
			$data_arr['shipping_group_items']= $shipping_group_items;
			$data_arr['shipping_tot']= $shipping_tot[$order_seq];
			$data_arr['able_step_action']= $this->ordermodel->able_step_action;
			$data_arr['rowspan'] = count($data_export_item);
			/*
			if( $export_cfg['exportPrintPackageGoodsName'] ){
				$data_arr['rowspan'] += $package_rowspan;
			}
			if( $export_cfg['exportPrintPackageGoodsNameSub'] ){
				$data_arr['rowspan'] += $package_sub_rowspan;
			}
			*/
			$loop[] = $data_arr;

		}

		$this->template->assign(array('loop' => $loop));
		$this->template->define(array('tpl'	=> $file_path));
		$this->template->print_("tpl");

	}

	/* 엑셀 다운로드 항목설정 */
	public function download_write(){

		$this->load->model('excelexportmodel');
		$itemList 	= $this->excelexportmodel->itemList;
		if( defined('__SELLERADMIN__') === true ) {//입점사 주문/출고 엑셀항목 : 결제금액,결제일,결제방법 제외
			unset($itemList['settleprice'],$itemList['deposit_date'],$itemList['payment']);
		}
		$this->template->assign('itemList',$itemList);
		$requireds 	= $this->excelexportmodel->requireds;
		$this->template->assign('requireds',$requireds);

		$data = get_data("fm_exceldownload",array("gb"=>'EXPORT',"provider_seq"=>$this->providerInfo['provider_seq']));
		if($data){
			$item = explode("|",$data[0]['item']);
			$this->template->assign('items',$item);
		}else{
			$data[0]['criteria'] = "EXPORT";
		}

		$this->template->assign($data[0]);

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 운송장 출력
	public function invoice_prints(){
		$this->tempate_modules();

		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->model('invoiceapimodel');

		$export 	= explode("|",$_GET['export']);
		$order 		= explode("|",$_GET['order']);

		$arr_export_code	= array();
		$arr_order_seq		= array();

		foreach($export as $v)	if($v) $arr_export_code[]	= $v;

		$loop = array();

		$invoice_vendor = null;

		// 개인정보 조회 로그 모델 로드
		$this->load->model('logPersonalInformation');

		foreach($arr_export_code as $export_code){
			$data_export 			= $this->exportmodel->get_export($export_code);

			// 개인정보 조회 로그
			//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderprint' 'orderexcel', 'exportexcel'
			$this->logPersonalInformation->insert('invoiceprint',$this->managerInfo['manager_seq'],$data_export['export_seq']);

			if($data_export['invoice_send_yn']!='y') continue;
			if(!preg_match("/^auto_/",$data_export['delivery_company_code'])) continue;

			if($invoice_vendor && $invoice_vendor!=$data_export['delivery_company_code']){
				$msg = "서로 다른 택배사의 운송장을 동시에 출력할 수 없습니다!";
				pageClose($msg);
				exit;
			}else{
				$invoice_vendor = $data_export['delivery_company_code'];
			}

			$data_export_item 		= $this->exportmodel->get_export_item($export_code);
			$orders 				= $this->ordermodel->get_order($data_export['order_seq']);
			$export_shipping		= $this->ordermodel->get_shipping($data_export['order_seq'],$data_export['shipping_provider_seq']);

			// 롯데택배 API 양식 맞춤 - 받는 분 정보 :: 2017-11-23 lwh
			$export_shipping[0]['recipient_user_name'] = $orders['recipient_user_name'];
			$export_shipping[0]['recipient_address'] = $orders['recipient_address'];
			$export_shipping[0]['recipient_address_detail'] = $orders['recipient_address_detail'];
			$export_shipping[0]['recipient_phone'] = $orders['recipient_phone'];
			$export_shipping[0]['recipient_cellphone'] = $orders['recipient_cellphone'];
			$export_shipping[0]['memo'] = $orders['memo'];

			$data_arr['data_export']		= $data_export;
			$data_arr['data_export_item']	= $data_export_item;
			$data_arr['export_shipping']	= $export_shipping[0];
			$data_arr['order']				= $orders;

			$loop[] = $data_arr;
		}

		if(!$loop){
			$msg = "출력할 운송장이 없습니다.";
			pageClose($msg);
			exit;
		}

		$invoice_vendor = preg_replace("/^auto_/","",$invoice_vendor);

		if(!$this->invoiceapimodel->config_invoice[$invoice_vendor]['use']){
			$company = $this->invoiceapimodel->invoice_vendor_cfg[$invoice_vendor]['company'];
			$msg = "설정 > 택배/배송비 > 택배업무자동화서비스({$company}) 세팅을 해주세요.";
			pageClose($msg);
			exit;
		}

		$method = "_invoice_prints_".$invoice_vendor;

		$this->$method($loop);
	}

	// 롯데택배 운송장 출력
	public function _invoice_prints_hlc($loop){
		$vendor = 'hlc';

		switch($this->invoiceapimodel->config_invoice['hlc']['print_type']){
			case "label_a":
				$invoiceWidth=339;
				$invoiceHeight=670;
			case "label_b":
				$invoiceWidth=339;
				$invoiceHeight=376;
			break;
			case "a4":
				$invoiceWidth=760;
				$invoiceHeight=339;
			break;
		}
/*
		$this->template->assign(array(
			'invoiceWidth' => $invoiceWidth,
			'invoiceHeight' => $invoiceHeight
		));
*/

		$result = $this->invoiceapimodel->hlc_invoice_print($loop);

		$file_path = str_replace("invoice_prints","invoice_prints_".$vendor,$this->template_path());
		$this->template->assign(array('loop'=>$loop));
		$this->template->assign($result);
		$this->template->assign(array('print_type'=>$this->invoiceapimodel->config_invoice[$vendor]['print_type']));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	// 티켓상품 사용 확인 팝업
	public function coupon_use(){

		if	($_POST['order_seq']){
			$this->load->model("ordermodel");
			$this->load->model("exportmodel");
			$this->load->model("returnmodel");
			$refund_able_ea=0;
			$result	= $this->exportmodel->get_coupon_export($_POST['order_seq'], $this->session->userdata['provider']['provider_seq']);
			if	($result){
				foreach($result as $key => $data){
					if	($data['suboption_seq']) {
						$data['suboption']	= $this->ordermodel->get_order_item_suboption($data['suboption_seq']);
					}
					else{
						$data['option'] = $this->ordermodel->get_order_item_option($data['option_seq']);

						$return_item = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['option_seq']);
						$refund_able_ea += (int) $data['ea'] - (int) $return_item['ea'];
					}
					$export[]	= $data;
				}
			}
			$this->template->assign(array('refund_able_ea'	=> $refund_able_ea));

			$this->template->assign(array('order_seq'=>$_POST['order_seq']));
			$this->template->assign(array('export'=>$export));

			$smsinfo	= get_sms_remind_count();
			$this->template->assign(array('smsinfo'	=> $smsinfo));

			$file_path = $this->template_path();
			$this->template->define(array('tpl'=>$file_path));
			$this->template->print_("tpl");
		}
	}

	// 티켓상품 티켓번호 인증
	public function get_coupon_info(){

		$this->load->model("exportmodel");
		$result	= $this->exportmodel->chk_coupon($_GET);

		echo json_encode($result);
	}

	// 티켓상품 사용내역
	public function coupon_use_list(){
		$this->load->model("exportmodel");
		$export		= $this->exportmodel->get_coupon_info($_GET);
		$history	= $this->exportmodel->get_coupon_use_history($export['coupon_serial']);
		$export['history']	= $history;
		$export['coupon_value_unit']	= '회';
		if	($export['coupon_value_type'] == 'price')
			$export['coupon_value_unit']	= $this->config_system['basic_currency'];

		$this->template->assign(array('export'=>$export));

		$file_path = $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 티켓상품 발송내역
	public function coupon_send_list(){
		$this->load->model("exportmodel");
		$param['send_kind']		= (trim($_GET['type']))			? trim($_GET['type'])			: '';
		$param['order_seq']		= (trim($_GET['order_seq']))	? trim($_GET['order_seq'])		: '';
		$param['export_code']	= (trim($_GET['export_code']))	? trim($_GET['export_code'])	: '';
		$param['provider_seq']	= $this->session->userdata['provider']['provider_seq'];
		$history	= $this->exportmodel->get_coupon_export_send_log($param);
		if	($history){
			$sms_key = $mail_key = 0;
			foreach($history as $k => $data){
				$tmp_param['export_code']	= $data['export_code'];
				$export						= $this->exportmodel->get_coupon_info($tmp_param);
				unset($tmp_param);
				if	($data['send_kind'] == 'sms'){
					$history_tmp[$sms_key]['regist_date']	= $data['regist_date'];
					$history_tmp[$sms_key]['sms']			= $data['send_val'];
					$history_tmp[$sms_key]['sms_status']	= $data['status'];
					$history_tmp[$sms_key]['export']		= $export;
					$sms_key++;
				}else{
					$history_tmp[$mail_key]['regist_date']	= $data['regist_date'];
					$history_tmp[$mail_key]['email']		= $data['send_val'];
					$history_tmp[$mail_key]['email_status']	= $data['status'];
					$history_tmp[$mail_key]['export']		= $export;
					$mail_key++;
				}
				unset($export);
			}
			unset($history);
			$history	= $history_tmp;
		}

		$export['send_type']	= $param['send_kind'];
		$export['history']		= $history;
		$this->template->assign(array('export'=>$export));
		$file_path = $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 굿스플로 운송장 받기 API 호출 :: 2015-07-02 lwh
	public function gf_export_call(){
		$this->load->model('goodsflowmodel');
		$this->load->model('exportmodel');

		$present_provider_seq = $this->providerInfo['provider_seq'];

		// 굿스플로 설정 로드 :: 2015-07-02 lwh
		$config_goodsflow = $this->goodsflowmodel->get_goodsflow_setting($present_provider_seq);

		// 굿스플로 설정 가능여부 판단
		if($this->config_system['goodsflow_use']=='1' && $config_goodsflow['goodsflow_step']=='1' && $config_goodsflow['gf_use']=='Y'){
			$service_cnt = $this->goodsflowmodel->get_service_info('view');
			$config_goodsflow['gf_deliveryCode'] = 'auto_'.$config_goodsflow['deliveryCode'];

			// 굿스플로 연동 시작
			if($_POST['gf_mode'] == 'each' && $_POST['gf_export_code']){
				// 잘못된 값이 넘어왔을경우 예외
				if($_POST['delivery_company'][$_POST['gf_export_code']] != $config_goodsflow['gf_deliveryCode']){
					openDialogAlert( '해당 출고건을 굿스플로 택배 서비스를 먼저 선택해주세요.' ,400,150,'parent','');
					exit;
				}
				if($_POST['shipping_provider_seq'][$_POST['gf_export_code']] != $config_goodsflow['provider_seq']){
					openDialogAlert( '다른 입점사의 굿스플로 서비스는 이용할 수 없습니다.' ,400,150,'parent','');
					exit;
				}
				// 보낼 수량 체크
				if($service_cnt > 0){
					// api 연동 시작
					$params['provider_seq'] = $present_provider_seq;
					$result = $this->goodsflowmodel->apiSender('sendOrderInformation',$params);

					if($result['result']){
						$export_log = $this->goodsflowmodel->get_goodsflow_log($result['exportlog_seq']);

						$this->template->assign(array('gf_mode'=>$_POST['gf_mode']));
						$this->template->assign(array('pop_url'=>$result['pop_url']));
						$this->template->assign(array('siteCode'=>$result['siteCode']));
						$this->template->assign(array('export_log'=>$export_log));
						$this->template->assign(array('partnerCode'=>$this->config_system['shopSno'].'_'.$present_provider_seq));
						$this->template->assign(array('export_code'=>$_POST['gf_export_code']));
					}else{
						openDialogAlert( $result['msg'] ,400,150,'parent','');
						exit;
					}
				}else{
					openDialogAlert( '굿스플로 서비스 충전건수가 없습니다.<br/>충전 후 이용해주세요.' ,400,150,'parent','');
					exit;
				}
			}else if($_POST['gf_mode'] == 'all'){
				if($_POST['status'] != '45'){
					//openDialogAlert( '출고 준비 상태에서만 가능합니다.' ,400,150,'parent','');
					//exit;
				}

				$result_arr = array();
				foreach($_POST['delivery_company'] as $export_code => $del_company){
					if($del_company == $config_goodsflow['gf_deliveryCode'] && $_POST['delivery_number'][$export_code] == '' && $_POST['shipping_provider_seq'][$export_code] == $config_goodsflow['provider_seq']){
						$_POST['gf_export_code'][] = $export_code;
					}
				}

				// 걸러낸 보낼 갯수 체크
				if(!$_POST['gf_export_code'][0]){
					openDialogAlert( '운송장 프린트 할 수량이 없습니다.<br/>실행조건 : 굿스플로 택배사 + 운송장번호가 없는 출고' ,400,150,'parent','');
					exit;
				}

				// 보낼 수량 체크
				if($service_cnt > count($_POST['gf_export_code'])){
					// api 연동 시작
					$params['provider_seq'] = $present_provider_seq;
					$result = $this->goodsflowmodel->apiSender('sendOrderInformation',$params);

					if($result['result']){
						$export_log = $this->goodsflowmodel->get_goodsflow_log($result['exportlog_seq']);

						$this->template->assign(array('gf_mode'=>$_POST['gf_mode']));
						$this->template->assign(array('pop_url'=>$result['pop_url']));
						$this->template->assign(array('siteCode'=>$result['siteCode']));
						$this->template->assign(array('export_log'=>$export_log));
						$this->template->assign(array('partnerCode'=>$this->config_system['shopSno'].'_'.$present_provider_seq));
						$this->template->assign(array('export_code'=>$_POST['gf_export_code']));
					}else{
						openDialogAlert($result['msg'] ,400,150,'parent','');
						exit;
					}
				}else{
					openDialogAlert( '굿스플로 서비스 충전건수가 없습니다.<br/>충전 후 이용해주세요.' ,400,150,'parent','');
					exit;
				}
			}else{
				openDialogAlert( '굿스플로 운송장연동에 오류가 발생하였습니다.<br/>퍼스트몰 고객센터로 연락주세요.<br/>Tel : 1544-3270' ,400,150,'parent','parent.location.reload();');
				exit;
			}
		}else{ // 굿스플로 설정 안되어있는경우 예외
			openDialogAlert( '굿스플로 설정이 되어있지 않습니다.<br/>먼저 굿스플로 설정을 변경해주세요.' ,400,150,'parent','');
			exit;
		}

		$this->template->assign(array('domain'=>$_SERVER['HTTP_HOST']));
		$file_path = $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 운송장 업데이트 JAVASCRIPT :: 2015-07-07 lwh
	public function gf_export_input(){
		$export_code = $_POST['export_code'];
		if(count($export_code) > 0){
			$this->load->model('exportmodel');
			echo "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
			$scripts[] = "<script type='text/javascript'>";
			$scripts[] = "$(function() {";

			foreach($export_code as $k => $code){
				$param[0]		= $code;
				$export_info	= $this->exportmodel->get_exports($param);
				if($export_info['delivery_number']){
					$scripts[] = '$("input[name=delivery_number['.$code.']",parent.document).val("'.$export_info['delivery_number'].'");';
				}
			}

			$scripts[] = "});";
			$scripts[] = "</script>";

			foreach($scripts as $script){
				echo $script."\n";
			}
		}
	}
}

/* End of file export.php */
/* Location: ./app/controllers/selleradmin/export.php */
