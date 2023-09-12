<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class order extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('ordermodel');
		$this->load->model('shippingmodel');
		$this->load->helper('order');
		$this->arr_step		= config_load('step');
		$this->arr_payment	= config_load('payment');
		$this->cfg_order	= config_load('order');

		$auth = $this->authmodel->manager_limit_act('order_view');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}

	}

	public function index()
	{
		redirect("/admin/order/catalog");
	}

	# 기본 검색 설정 저장
	public function set_default_search_form(){

		# reset
		$callbackAppend = '';

		$this->load->model('searchdefaultconfigmodel');

		$pageid = $_POST['pageid'];
		if(in_array($pageid,array("order","returns","refund","export","member"))){
			$pageid = 'admin/'.$pageid.'/catalog';

			# [주문-통합주문리스트]인 경우만 추가
			# 2016-10-25 14:44  나중에 추가하기로 하여 주석처리함(채우형)
			#$_POST['pageid'] == 'order' && $callbackAppend = "parent.location.replace('/{$pageid}');";
		}elseif(in_array($pageid,array("order_company"))){
			$pageid = 'admin/order/company_catalog';
		}elseif(in_array($pageid,array("gift_catalog","restock_notify_catalog"))){
			$pageid = 'admin/goods/'.$pageid;
		}elseif(in_array($pageid,array("revision", "stockmove", "inven", "ledger", "scmgoods"))){
			$pageid = $pageid == 'scmgoods' ? 'goods' : $pageid;
			$pageid = 'admin/scm_manage/'.$pageid;
		}elseif(in_array($pageid,array("sorder", "warehousing", "carryingout", "autoorder", "traderaccount"))){
			$pageid = 'admin/scm_warehousing/'.$pageid;
		}elseif(in_array($pageid,array("dormancy_catalog","withdrawal"))){
			$pageid = 'admin/member/'.$pageid;
		}else{
			$pageid = 'admin/order/'.$pageid;
		}
		unset($_POST['pageid']);
		$param_order				= $_POST;
		$param_order['search_page'] = $pageid;

		$this->searchdefaultconfigmodel->set_search_default($param_order);

		$callback = "parent.closeDialog('search_detail_dialog');" . $callbackAppend;
		openDialogAlert("[".$this->managerInfo['mname']."] 관리자의 검색옵션이 저장되었습니다.<br/>단, 검색어와 입력날짜는 저장되지 않습니다.", 500,180,'parent',$callback);
	}

	# 기본 검색 설정 불러오기
	public function get_default_search_form(){

		$pageid = $_GET['pageid'];
		if(in_array($pageid,array("order","returns","refund","export","member"))){
			$pageid = 'admin/'.$pageid.'/catalog';
		}elseif(in_array($pageid,array("gift_catalog","restock_notify_catalog"))){
			$pageid = 'admin/goods/'.$pageid;
		}elseif(in_array($pageid,array("revision", "stockmove", "inven", "ledger", "scmgoods"))){
			$pageid = $pageid == 'scmgoods' ? 'goods' : $pageid;
			$pageid = 'admin/scm_manage/'.$pageid;
		}elseif(in_array($pageid,array("sorder", "warehousing", "carryingout", "autoorder", "traderaccount"))){
			$pageid = 'admin/scm_warehousing/'.$pageid;
		}elseif(in_array($pageid,array("dormancy_catalog","withdrawal"))){
			$pageid = 'admin/member/'.$pageid;
		}elseif(in_array($pageid,array("revision", "stockmove", "inven", "ledger", "scmgoods"))){
			$pageid = $pageid == 'scmgoods' ? 'goods' : $pageid;
			$pageid = 'admin/scm_manage/'.$pageid;
		}elseif(in_array($pageid,array("sorder", "warehousing", "carryingout", "autoorder", "traderaccount"))){
			$pageid = 'admin/scm_warehousing/'.$pageid;
		}else{
			$pageid = 'admin/order/'.$pageid;
		}
		$this->load->model('searchdefaultconfigmodel');
		$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config($pageid);
		parse_str($data_search_default_str['search_info'], $data_search_default);
		echo json_encode($data_search_default);
	}

	public function important()
	{
		$val = $_GET['val'];
		$no = str_replace('important_','',$_GET['no']);
		$query = "update fm_order set important=? where order_seq=?";
		$this->db->query($query,array($val,$no));
	}


	public function order_search_popup(){
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->model('providermodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('ordermodel');
		$this->load->library('privatemasking');

		/* 입점사명 정렬 추가(가나다abc) leewh 2014-11-10 */
		$provider				= $this->providermodel->provider_goods_list_sort();

		//유입매체
		$sitemarketplaceloop	= sitemarketplace($_GET['sitemarketplace'], 'image', 'array');

		/*
		 //오픈마켓연동정보
		 if	($this->openmarketmodel->chk_linkage_service()){
		 $linkage = $this->openmarketmodel->get_linkage_config();
		 if($linkage){
		 // 설정된 판매마켓 정보
		 $linkage_mallnames = array();
		 $linkage_mallnames_for_search = array();
		 $linkage_malldata		= $this->openmarketmodel->get_linkage_mall();
		 $linkage_malldata		= $this->openmarketmodel->sort_linkage_mall($linkage_malldata);

		 foreach($linkage_malldata as $k => $data){
		 if	($data['default_yn'] == 'Y'){
		 $linkage_mallnames[$data['mall_code']]	= preg_replace("/\(.*\)/","",$data['mall_name']);
		 $linkage_mallnames_for_search[]	= array(
		 'mall_code' => $data['mall_code'],
		 'mall_name' => preg_replace("/\(.*\)/","",$data['mall_name'])
		 );
		 }
		 }
		 $this->template->assign('linkage_mallnames_for_search',$linkage_mallnames_for_search);
		 $this->template->assign('linkage_mallnames',$linkage_mallnames);
		 }
		 }
		 */

		$record = "";

		//상품리스트에서 [조회] 클릭시 해당 상품 주문 리스트 검색용. @2015-09-21 pjm
		if($_GET['goods_seq']){
			$_GET['keyword'] = $_GET['goods_seq'];
		}

		if($_GET['header_search_keyword']) {
			$_GET['keyword']= $_GET['header_search_keyword'];//'order_seq';
		}

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_GET) == 0 ){
			if($_COOKIE['order_list_search']){
				$arr = explode('&',$_COOKIE['order_list_search']);
				if($arr) foreach($arr as $data){
					$arr2 = explode("=",$data);
					if($arr2[0]!='regist_date' ){
						$key = explode('[',$arr2[0]);
						$_GET[$key[0]][ str_replace(']','',$key[1]) ] = $arr2[1];
					}else{
						if($arr2[1] == 'today'){
							$_GET['regist_date'][0] = date('Y-m-d');
							$_GET['regist_date'][1] = date('Y-m-d');
						}else if($arr2[1] == '3day'){
							$_GET['regist_date'][0] = date('Y-m-d',strtotime("-3 day"));
							$_GET['regist_date'][1] = date('Y-m-d');
						}else if($arr2[1] == '7day'){
							$_GET['regist_date'][0] = date('Y-m-d',strtotime("-7 day"));
							$_GET['regist_date'][1] = date('Y-m-d');
						}else if($arr2[1] == '1mon'){
							$_GET['regist_date'][0] = date('Y-m-d',strtotime("-1 month"));
							$_GET['regist_date'][1] = date('Y-m-d');
						}else if($arr2[1] == '3mon'){
							$_GET['regist_date'][0] = date('Y-m-d',strtotime("-3 month"));
							$_GET['regist_date'][1] = date('Y-m-d');
						}else if($arr2[1] == 'all'){
							$_GET['regist_date'][0] = '';
							$_GET['regist_date'][1] = '';
						}
						$_GET['regist_date_type'] = $arr2[1];
					}
				}
			}else{
				// 기본세팅이 없는경우 오늘의 입금확인 주문접수 주문을 검색조건으로 합니다.
				$_GET['regist_date'][0] = date('Y-m-d');
				$_GET['regist_date'][1] = date('Y-m-d');
				$_GET['chk_step'][15] = 1;
				$_GET['chk_step'][25] = 1;
			}
		}

		// 주문 선택 팝업에서 기본 검색 설정
		if(count($_GET['chk_step']) == 0) {
			if($_GET['search_page'] == "refund_shipping") {
				//환불신청(배송비)는 결제확인 이후부터 검색
				$able_step = $this->ordermodel->able_step_action['card_slips'];
			} else {
				//매출증빙 신청은 주문접수 이후부터 검색
				$able_step = $this->ordermodel->able_step_action['cash_receipts'];
			}
			foreach($able_step as $k) {
				$_GET['chk_step'][$k] = 1;
			}
		}


		$_PARAM		= $_GET;//$_GET//$_POST
		unset($_PARAM['stepBox']);

		$page		= (trim($_PARAM['page'])) ? trim($_PARAM['page']) : 1;
		$bfStep		= trim($_PARAM['bfStep']);
		$no			= trim($_PARAM['nnum']);

		if($_PARAM['page'] > 2){
			$_PARAM['page']			= ($_PARAM['page'] / 20) + 1;
			$_PARAM['totla_cnt']	= $_GET['totla_cnt'];
			$no						= $_GET['totla_cnt'] - ($_PARAM['page']-1) * 20;

		}


		// 양식 데이터
		$this->db->order_by("seq","desc");
		$this->db->where(array('gb'=>'ORDER',"provider_seq"=>'1'));
		$query = $this->db->get("fm_exceldownload");
		foreach ($query->result_array() as $row){
			$row['count'] = count(explode("|",$row['item']));
			$loop[] = $row;
		}

		$query	= $this->ordermodel->get_order_catalog_query($_PARAM);

		if	($query){

		    //#23947 kmj
		    $this->load->library('Connector');
		    $MarketLinkage	= config_load('MarketLinkage');
		    $connector	= $this->connector::getInstance();

		    if ($connector->isConnectorUse()){
		        $marketList = $connector->getAllMarkets(true);

		        $this->load->model('connectormodel');
		        $shopLinkermarketList = array();
		        $shopLinkerUseMarketList = $this->connectormodel->getLinkageMarketGroup();
		        foreach($shopLinkerUseMarketList as $marketInfo){
		            $shopLinkermarketList[$marketInfo['marketCode']] = array('name'=>$marketInfo['marketName'],'productLink'=>'');
		        }

		        unset($marketList['shoplinker']);
		    }

			if	($page == 1){
				$_PARAM['query_type']	= 'total_record';
				$totalQuery				= $this->ordermodel->get_order_catalog_query($_PARAM);
				$totalData				= $totalQuery->result_array();
				$no						= $totalData[0]['cnt'];
				$_GET['totla_cnt']		= $no;
			}

			foreach($query->result_array() as $k => $data){
			    //#23947 kmj
			    //$data['linkage_mallname'] = str_replace("(주)","",$linkage_mallnames[$data['linkage_mall_code']]);
			    //if(!$data['linkage_mallname'] ) $data['linkage_mallname'] = "내사이트";

			    if ($data['linkage_id'] == 'connector') { //#23947 kmj
			        if(substr($data['linkage_mall_code'],0,3) == "API"){
			            $data['linkage_mallname_text']	= $shopLinkermarketList[$data['linkage_mall_code']]['name'];
			        }else{
			            $data['linkage_mallname_text']	= $marketList[$data['linkage_mall_code']]['name'];
			        }
			    }

				$data['referer_naver']		= sitemarketplaceNaver($data, 'naver');//유입매체:네이버 url 파라미터 제거

				$data['mstep'] = $this->arr_step[$data['step']];

				$data['sitetypetitle']		= sitetype($data['sitetype'], 'image', '');//판매환경
				$data['marketplacetitle']	= sitemarketplace($data['marketplace'], 'image', '');//유입매체

				$tmp = explode(' ',$data['bank_account']);
				$data['bankname'] = $tmp[0];

				###
				//$data['opt_cnt']	= $this->ordermodel->get_option_count('opt', $data['order_seq']);
				//$data['gift_cnt']	= $this->ordermodel->get_option_count('gift', $data['order_seq']);
				$data['gift_nm']	= $this->ordermodel->get_gift_name($data['order_seq']);
				$data['tot_ea']	= $data['opt_ea']+$data['sub_ea'];

				//반품정보 가져오기
				$data['return_list_ea'] = 0;
				$data_return = $this->returnmodel->get_return_for_order($data['order_seq'],"return");
				if($data_return) foreach($data_return as $row_return){
					$data['return_list_ea'] += $row_return['ea'];
				}

				//교환정보 가져오기
				$data['exchange_list_ea'] = 0;
				$data_exchange = $this->returnmodel->get_return_for_order($data['order_seq'],"exchange");
				if($data_exchange) foreach($data_exchange as $row_exchange){
					$data['exchange_list_ea'] += $row_exchange['ea'];
				}

				//환불정보 가져오기
				$data['refund_list_ea'] = 0;
				$data['cancel_list_ea'] = 0;
				$data_refund = $this->refundmodel->get_refund_for_order($data['order_seq']);
				if($data_refund) foreach($data_refund as $row_refund){
					if( $row_refund['refund_type'] == 'cancel_payment' ){
						$data['cancel_list_ea'] += $row_refund['ea'];
					}else{
						$data['refund_list_ea'] += $row_refund['ea'];
					}
				}

				$data['mpayment'] = $this->arr_payment[$data['payment']];
				$step_cnt[$data['step']]++;
				$tot_settleprice[$data['step']] += $data['settleprice'];
				$tot[$data['step']][$data['important']] += $data['settleprice'];

				$data['step_cnt'] = $step_cnt;
				$data['tot_settleprice'] = $tot_settleprice;
				$data['tot'][$data['important']] = $tot[$data['step']][$data['important']];

				if($data['member_seq']){
					$data['member_type']	= $data['mbinfo_business_seq'] ? '기업' : '개인';
				}

				$data['loop']	= $loop;
				$data['no']		= $no;
				if	($data['payment'] == 'bank' && $data['bank_account']){
					$bank_tmp			= explode(' ', $data['bank_account']);
					$bank_name			= str_replace('은행', '', $bank_tmp[0]);
					$data['bank_name']	= $bank_name;
				}

				if ($data['deposit_date']=="0000-00-00 00:00:00") $data['deposit_date'] = "";

				if	($_PARAM['stepBox'][$data['step']]){
					if		($_POST['stepBox'][$data['step']] == 'select'){
						$data['thischeck']	= true;
					}elseif	($_POST['stepBox'][$data['step']] == 'important'){
						if	($data['important'])
							$data['thischeck']	= true;
					}elseif	($_POST['stepBox'][$data['step']] == 'not-important'){
						if	(!$data['important'])
							$data['thischeck']	= true;
					}
				}

				## 시작점과 종료점
				if	($bfStep != $data['step']){
					$data['start_step']	= $data['step'];
					if	($bfStep){
						$record[$k]['end_step']			= $bfStep;
						$_PARAM['query_type']			= 'summary';
						$_PARAM['end_step']				= $bfStep;
						$summary_query					= $this->ordermodel->get_order_catalog_query($_PARAM);
						$endData						= $summary_query->result_array();
						$data['end_mstep']				= $this->arr_step[$bfStep];
						$data['end_step']				= $bfStep;
						$data['end_step_cnt']			= $endData[0]['cnt'];
						$data['end_step_settleprice']	= $endData[0]['total_settleprice'];
						$data['end_step_payprice']		= $endData[0]['total_payprice'];
					}
					$bfStep	= $data['step'];
				}

				if	($no == 1){
					$_PARAM['query_type']			= 'summary';
					$_PARAM['end_step']				= $data['step'];
					$summary_query					= $this->ordermodel->get_order_catalog_query($_PARAM);
					$endData						= $summary_query->result_array();
					$data['last_step']				= $data['step'];
					$data['last_step_cnt']			= $endData[0]['cnt'];
					$data['last_step_settleprice']	= $endData[0]['total_settleprice'];
					$data['last_step_payprice']		= $endData[0]['total_payprice'];
				}

				//개인정보 마스킹 표시
				$data = $this->privatemasking->masking($data, 'order');

				$record[$k] = $data;
				$final_step	= $data['step'];

				$no--;
			}
		}

		//유입매체
		$sitemarketplaceloop = sitemarketplace($_GET['sitemarketplace'], 'image', 'array');

		$paginlay =  pagingtag($_GET['totla_cnt'], 20,'./order_search_popup?', getLinkFilter('',array_keys($_GET)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);

		$this->template->assign('sitemarketplaceloop',$sitemarketplaceloop);
		$this->template->assign(array('stepBox' => $_PARAM['stepBox']));
		$this->template->assign(array('page' => $page));
		$this->template->assign(array('final_no' => $no));
		$this->template->assign(array('final_step' => $final_step));
		$this->template->assign(array('record' => $record));
		$this->template->assign(array('provider' => $provider));

		//매출증빙 check Y/N
		if($_PARAM['voucher_type'] && $_PARAM['voucher_type'] != '') {
			$this->template->assign('voucher_type', $_PARAM['voucher_type']);
			$this->template->assign('sale_reserve_yn',$this->cfg_order['sale_reserve_yn']);
			$this->template->assign('sale_emoney_yn',$this->cfg_order['sale_emoney_yn']);
		}

		$this->template->define(array('tpl'	=> $file_path));
		$this->template->print_("tpl");
	}

	public function get_order_info(){
		$order_seq			= (int)$_GET['order_seq'];
		$order_info			= $this->ordermodel->get_order($order_seq);
		$shipping			= $this->ordermodel->get_order_shipping($order_seq);

		// 전화번호 문자제거 :: 2017-08-28 lwh
		$order_info['order_phone_num']		= preg_replace("/[^0-9]/","",$order_info['order_phone']);
		$order_info['order_cellphone_num']	= preg_replace("/[^0-9]/","",$order_info['order_cellphone']);

		// 상품명 검색 :: 2017-08-25 lwh
		if($_GET['goods_name']==1){
			$order_item = $this->ordermodel->get_item($order_seq);
			$order_info['goods_name']	= $order_item[0]['goods_name'];
			$order_info['tax']			= $order_item[0]['tax'];
			if(count($order_item)>1){
				$order_info['goods_name'] .= '외 ' . (count($order_item) - 1) . '건';
				foreach($order_item as $key => $item){
					if($item['tax'] == 'tax'){
						$order_info['tax'] = 'tax';
					}
				}
			}
		}

		// 주문금액 검색 :: 2017-08-28 lwh
		if($_GET['order_price']==1){
			$this->load->model('salesmodel');
			$order		= $this->ordermodel->get_order($order_seq);
			$tax_price	= $this->ordermodel->get_order_prices_for_tax($order_seq,$order,true);
			$data_tax	= $this->salesmodel->tax_calulate($tax_price["tax"],$tax_price["exempt"],$tax_price["shipping_cost"],$tax_price["sale"],$tax_price["tax_sale"],'SETTLE');
			$return['tax_price']				= $data_tax;
			$return['tax_price']['total_price']	= array_sum($data_tax);
		}

		$shipping_provider	= array();
		foreach((array)$shipping as $key=>$val){
			if(!isset($shipping_provider[$val['provider_seq']]))	$shipping_provider[$val['provider_seq']]		= $val;
		}

		$return['show_refund_method'] = 'Y';

		// 간편결제 환불방법 미노출 처리
		if (is_order_simple_payment($order_info)) {
			$return['show_refund_method'] = 'N';
		}

		$return['order_info']			= $order_info;
		$return['shipping_provider']	= array_values($shipping_provider);

		echo json_encode($return);
	}

	/**
	 * 배송지 미등록 주문 조회
	 */
	public function no_receipt_address(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->model('refundmodel');
		$this->load->model('membermodel');
		$this->load->model('providermodel');
		$this->load->helper('shipping');

		$this->load->helper('xssfilter');
		xss_clean_filter();

		$record = "";

		//상품리스트에서 [조회] 클릭시 해당 상품 주문 리스트 검색용. @2015-09-21 pjm
		if($_GET['goods_seq']){
			$_GET['keyword'] = $_GET['goods_seq'];
		}

		if($_GET['header_search_keyword']) {
			$_GET['keyword']= $_GET['header_search_keyword'];//'order_seq';
		}

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_GET) == 0 ){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/order/no_receipt_address');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$_GET['regist_date'][0]	= $search_date['start_date'];
				$_GET['regist_date'][1]	= $search_date['end_date'];
				foreach($data_search_default as $key => $val){
					$key = str_replace("default_","",$key);
					$_GET[$key]		= $val;
				}
			}else{
				// 기본세팅이 없는경우 오늘의 입금확인 주문접수 주문을 검색조건으로 합니다.
				$_GET['regist_date'][0] = date('Y-m-d');
				$_GET['regist_date'][1] = date('Y-m-d');
			}
		}


		$sc = $_GET;
		$this->template->assign("sc", $sc);

		// 현재의 처리 프로세스
		$orders = config_load('order');
		$this->template->assign($orders);
		$this->template->assign('config_system',$this->config_system);

		# 검색필드 배열정의 @2016-09-28 pjm
		$search_arr_field = search_arr_field();
		$this->template->assign(array('arr_search_keyword'		=> $search_arr_field['arr_search_keyword']));
		$this->template->assign(array('arr_order_goods_type'	=> $search_arr_field['arr_order_goods_type']));
		$this->template->assign(array('arr_order_pg'			=> $search_arr_field['arr_order_pg']));
		$this->template->assign(array('arr_order_payment'		=> $search_arr_field['arr_order_payment']));

		$search_form_path = dirname($this->template_path()).'/_receipt_search_form.html';
		$this->template->define(array('search_form'=>$search_form_path));

		// 엑셀 검색 다운로드
		$excel_download_path = dirname($this->template_path()).'/_excel_download.html';
		$this->template->define(array('excel_dwonload'=>$excel_download_path));

		$this->template->assign(array('shopSno' => $this->config_system['shopSno']));
		$this->template->assign(array('pagemode' => "catalog"));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign(array('record' => $record));
		$this->template->print_("tpl");
	}

	public function catalog(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->model('returnmodel');
		$this->load->model('refundmodel');
		$this->load->model('membermodel');
		$this->load->model('providermodel');
		$this->load->helper('shipping');

		$this->load->helper('xssfilter');
		xss_clean_filter();

		$npay_use = npay_useck();
		$talkbuy_use = talkbuy_useck();

		/* 입점사명 정렬 추가(가나다abc) leewh 2014-11-10 */
		$provider			= $this->providermodel->provider_goods_list_sort();

		//유입매체
		$sitemarketplaceloop = sitemarketplace($_GET['sitemarketplace'], 'image', 'array');

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

		$record = "";

		//상품리스트에서 [조회] 클릭시 해당 상품 주문 리스트 검색용. @2015-09-21 pjm
		if($_GET['goods_seq']){
			$_GET['keyword'] = $_GET['goods_seq'];
		}

		if($_GET['header_search_keyword']) {
			$_GET['keyword']= $_GET['header_search_keyword'];//'order_seq';
		}

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.

		if( count($_GET) == 0 ){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/order/catalog');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$_GET['regist_date'][0]	= $search_date['start_date'];
				$_GET['regist_date'][1]	= $search_date['end_date'];
				foreach($data_search_default as $key => $val){
					$key = str_replace("default_","",$key);
					$_GET[$key]		= $val;
				}
			}else{
				// 기본세팅이 없는경우 오늘의 입금확인 주문접수 주문을 검색조건으로 합니다.
				$_GET['regist_date'][0] = date('Y-m-d');
				$_GET['regist_date'][1] = date('Y-m-d');
				$_GET['chk_step'][15] = 1;
				$_GET['chk_step'][25] = 1;
			}
		}

		// 필수 검색 조건이 없을 경우
		if( ! $_GET['header_search_keyword'] && ! $_GET['keyword'] && ( ! $_GET['regist_date'][0] || ! $_GET['regist_date'][1])){
			$_GET['regist_date'][0] = date('Y-m-d', strtotime('-365 days'));
			$_GET['regist_date'][1] = date('Y-m-d');
		}

		$sc = $_GET;
		$this->template->assign("sc", $sc);


		## 택배업무자동화서비스 세팅값 :: 2015-07-10
		$this->load->model('invoiceapimodel');
		$provider_seq	= 1;
		$config_invoice = $this->invoiceapimodel->get_invoice_setting($provider_seq);
		$this->template->assign("config_invoice",$config_invoice);

		## 굿스플로 서비스 체크 @nsg 2015-10-20
		$this->load->model('goodsflowmodel');
		$goodsflow = $this->goodsflowmodel->get_goodsflow_setting();
		$this->template->assign('goodsflow',$goodsflow);

		## 우체국자동화서비스 체크 :: 2016-04-12 lwh
		$this->load->model('epostmodel');
		$epost = $this->epostmodel->get_epost_requestkey();
		$this->template->assign('epost',$epost);

		# 택배업무 자동화 사용여부 @2016-09-28 pjm
		if($config_invoice['hlc']['use'] == 1
				|| ($goodsflow['gf_use']=='Y' && $goodsflow['goodsflow_step']=='1')
				|| ($epost['epost_use']=='Y' && $epost['status']=='9')
				){ $auto_parcel = 'y'; }else{ $auto_parcel = 'n'; }
				$this->template->assign('auto_parcel',$auto_parcel);

				# 무통장자동입금(뱅크다) 사용여부
				$this->load->model('usedmodel');
				$banks = $this->usedmodel->autodeposit_check();
				$this->template->assign(array('bankChk'=>$banks['chk'],'bankCount'=>$banks['count']));

				## 유입경로 그룹
				$this->load->model('statsmodel');
				$referer_list	= $this->statsmodel->get_referer_grouplist();
				$this->template->assign('referer_list',$referer_list);

				// 현재의 처리 프로세스
				$orders = config_load('order');
				$this->template->assign($orders);
				$export_cfg = config_load('export');
				$this->template->assign($export_cfg);
				$this->template->assign('config_system',$this->config_system);
				$this->template->define(array('order_process'=>$this->skin."/setting/_order_process.html"));

				//판매환경
				$sitetypeloop = sitetype($_GET['sitetype'], 'image', 'array');
				$this->template->assign('sitetypeloop',$sitetypeloop);
				$this->template->assign('order_list_search',$_COOKIE['order_list_search']);
				$this->template->assign('cfg_order',$this->cfg_order);

				$this->template->assign('sitemarketplaceloop',$sitemarketplaceloop);
				$this->template->assign('provider',$provider);
				$this->template->assign(array('able_step_action' => $this->ordermodel->able_step_action));

				// 엑셀 출고
				$excel_export_path = dirname($this->template_path()).'/_excel_export.html';
				$this->template->define(array('excel_export'=>$excel_export_path));

				$excel_export_path = dirname($this->template_path()).'/_excel_delivery_code.html';
				$this->template->define(array('excel_delivery_code'=>$excel_export_path));

				$invoice_guide_path = dirname($this->template_path()).'/_invoice_guide.html';
				$this->template->define(array('invoice_guide'=>$invoice_guide_path));

				$print_setting_path = dirname($this->template_path()).'/../setting/_print_setting.html';
				$this->template->define(array('print_setting'=>$print_setting_path));

				// 엑셀 검색 다운로드
				$excel_download_path = dirname($this->template_path()).'/_excel_download.html';
				$this->template->define(array('excel_dwonload'=>$excel_download_path));

				# 검색필드 배열정의 @2016-09-28 pjm
				$search_arr_field = search_arr_field();
				$this->template->assign(array('arr_search_keyword'		=> $search_arr_field['arr_search_keyword']));
				$this->template->assign(array('arr_order_goods_type'	=> $search_arr_field['arr_order_goods_type']));
				$this->template->assign(array('arr_order_pg'			=> $search_arr_field['arr_order_pg']));
				$this->template->assign(array('arr_order_payment'		=> $search_arr_field['arr_order_payment']));

				$search_form_path = dirname($this->template_path()).'/_search_form.html';
				$this->template->define(array('search_form'=>$search_form_path));

				# 배송방법
				$ship_set_code = $this->shippingmodel->ship_set_code; // 배송설정코드
				$this->template->assign(array('ship_set_code' => $ship_set_code));

				// 물류설정
				if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
				$scmOptions	= array('boxName' => 'export_warehouse');
				$this->template->assign(array('scmOptions' => $scmOptions));
				$this->template->assign(array('scm_cfg' => $this->scm_cfg));
				$this->template->assign(array('shopSno' => $this->config_system['shopSno']));
				$this->template->assign(array('pagemode' => "catalog"));
				$this->template->define(array('tpl'=>$file_path));
				$this->template->assign(array('record' => $record));
				$this->template->assign(array('npay_use' => $npay_use));
				$this->template->assign(array('talkbuy_use' => $talkbuy_use));
				$this->template->print_("tpl");

	}

	# 본사배송 주문상품 리스트 @2016-09-28 pjm
	public function company_catalog(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->model('returnmodel');
		$this->load->model('refundmodel');
		$this->load->model('membermodel');
		$this->load->model('providermodel');
		$this->load->helper('shipping');
		$this->load->model('openmarketmodel');

		$npay_use = npay_useck();

		/* 입점사명 정렬 추가(가나다abc) leewh 2014-11-10 */
		$provider			= $this->providermodel->provider_goods_list_sort();

		//유입매체
		$sitemarketplaceloop = sitemarketplace($_GET['sitemarketplace'], 'image', 'array');

		/*
		 //오픈마켓연동정보
		 if	($this->openmarketmodel->chk_linkage_service()){
		 $linkage = $this->openmarketmodel->get_linkage_config();
		 if($linkage){
		 // 설정된 판매마켓 정보
		 $linkage_mallnames = array();
		 $linkage_mallnames_for_search = array();
		 $linkage_malldata		= $this->openmarketmodel->get_linkage_mall();
		 $linkage_malldata		= $this->openmarketmodel->sort_linkage_mall($linkage_malldata);

		 foreach($linkage_malldata as $k => $data){
		 if	($data['default_yn'] == 'Y'){
		 $linkage_mallnames[$data['mall_code']]	= preg_replace("/\(.*\)/","",$data['mall_name']);
		 $linkage_mallnames_for_search[]	= array(
		 'mall_code' => $data['mall_code'],
		 'mall_name' => preg_replace("/\(.*\)/","",$data['mall_name'])
		 );
		 }
		 }
		 $this->template->assign('linkage_mallnames_for_search',$linkage_mallnames_for_search);
		 $this->template->assign('linkage_mallnames',$linkage_mallnames);
		 }
		 }
		 */

		$record = "";

		//상품리스트에서 [조회] 클릭시 해당 상품 주문 리스트 검색용. @2015-09-21 pjm
		if($_GET['goods_seq']){
			$_GET['keyword'] = $_GET['goods_seq'];
		}

		if($_GET['header_search_keyword']) {
			$_GET['keyword']= $_GET['header_search_keyword'];//'order_seq';
		}

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_GET) == 0 ){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/order/catalog');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$_GET['regist_date'][0]	= $search_date['start_date'];
				$_GET['regist_date'][1]	= $search_date['end_date'];
				foreach($data_search_default as $key => $val){
					$key = str_replace("default_","",$key);
					$_GET[$key]		= $val;
				}
			}else{
				// 기본세팅이 없는경우 오늘의 입금확인 주문접수 주문을 검색조건으로 합니다.
				$_GET['regist_date'][0] = date('Y-m-d');
				$_GET['regist_date'][1] = date('Y-m-d');
				$_GET['chk_step'][15] = 1;
				$_GET['chk_step'][25] = 1;
			}
		}
		$_GET['shipping_provider_seq'] = 1;

		// 필수 검색 조건이 없을 경우
		if( ! $_GET['header_search_keyword'] && ! $_GET['keyword'] && ( ! $_GET['regist_date'][0] || ! $_GET['regist_date'][1])){
			$_GET['regist_date'][0] = date('Y-m-d', strtotime('-365 days'));
			$_GET['regist_date'][1] = date('Y-m-d');
		}

		$sc = $_GET;
		$this->template->assign("sc",$sc);

		## 택배업무자동화서비스 세팅값 :: 2015-07-10
		$this->load->model('invoiceapimodel');
		$provider_seq = 1;
		$config_invoice = $this->invoiceapimodel->get_invoice_setting($provider_seq);
		//$this->template->assign("config_invoice",$config_invoice);

		## 굿스플로 서비스 체크 @nsg 2015-10-20
		$this->load->model('goodsflowmodel');
		$goodsflow = $this->goodsflowmodel->get_goodsflow_setting();
		//$this->template->assign('goodsflow',$goodsflow);

		## 우체국자동화서비스 체크 :: 2016-04-12 lwh
		$this->load->model('epostmodel');
		$epost = $this->epostmodel->get_epost_requestkey();
		//$this->template->assign('epost',$epost);

		# 택배업무 자동화 사용여부 @2016-09-28 pjm
		if($config_invoice['hlc']['use'] == 1
				|| ($goodsflow['gf_use']=='Y' && $goodsflow['goodsflow_step']=='1')
				|| ($epost['epost_use']=='Y' && $epost['status']=='9')
				){ $auto_parcel = 'y'; }else{ $auto_parcel = 'n'; }
				$this->template->assign('auto_parcel',$auto_parcel);

				# 무통장자동입금(뱅크다) 사용여부
				$this->load->model('usedmodel');
				$banks = $this->usedmodel->autodeposit_check();
				$this->template->assign(array('bankChk'=>$banks['chk'],'bankCount'=>$banks['count']));

				## 유입경로 그룹
				$this->load->model('statsmodel');
				$referer_list	= $this->statsmodel->get_referer_grouplist();
				$this->template->assign('referer_list',$referer_list);

				// 현재의 처리 프로세스
				$orders			= config_load('order');
				$export_cfg		= config_load('export');
				$this->template->assign($orders);
				$this->template->assign($export_cfg);
				$this->template->assign('config_system',$this->config_system);
				$this->template->define(array('order_process'=>$this->skin."/setting/_order_process.html"));

				//판매환경
				$sitetypeloop = sitetype($_GET['sitetype'], 'image', 'array');
				$this->template->assign('sitetypeloop',$sitetypeloop);
				$this->template->assign('order_list_search',$_COOKIE['order_list_search']);
				$this->template->assign('cfg_order',$this->cfg_order);

				$this->template->assign('sitemarketplaceloop',$sitemarketplaceloop);
				$this->template->assign('provider',$provider);
				$this->template->assign(array('able_step_action' => $this->ordermodel->able_step_action));

				// 엑셀 출고
				$excel_export_path = dirname($this->template_path()).'/_excel_export.html';
				$this->template->define(array('excel_export'=>$excel_export_path));

				$excel_export_path = dirname($this->template_path()).'/_excel_delivery_code.html';
				$this->template->define(array('excel_delivery_code'=>$excel_export_path));

				$invoice_guide_path = dirname($this->template_path()).'/_invoice_guide.html';
				$this->template->define(array('invoice_guide'=>$invoice_guide_path));

				$print_setting_path = dirname($this->template_path()).'/../setting/_print_setting.html';
				$this->template->define(array('print_setting'=>$print_setting_path));

				// 엑셀 검색 다운로드
				$excel_download_path = dirname($this->template_path()).'/_excel_download.html';
				$this->template->define(array('excel_dwonload'=>$excel_download_path));

				# 검색필드 배열정의 @2016-09-28 pjm
				$search_arr_field = search_arr_field();
				$this->template->assign(array('arr_search_keyword'		=> $search_arr_field['arr_search_keyword']));
				$this->template->assign(array('arr_order_goods_type'	=> $search_arr_field['arr_order_goods_type']));
				$this->template->assign(array('arr_order_pg'			=> $search_arr_field['arr_order_pg']));
				$this->template->assign(array('arr_order_payment'		=> $search_arr_field['arr_order_payment']));

				$search_form_path = dirname($this->template_path()).'/_search_form.html';
				$this->template->define(array('search_form'=>$search_form_path));

				# 배송방법
				$ship_set_code = $this->shippingmodel->ship_set_code; // 배송설정코드
				$this->template->assign(array('ship_set_code' => $ship_set_code));

				$file_path = str_replace('company_catalog.html','catalog.html',$file_path);

				// 물류설정
				if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
				$scmOptions	= array('boxName' => 'export_warehouse');
				$this->template->assign(array('pagemode' => "company_catalog"));
				$this->template->assign(array('scmOptions' => $scmOptions));
				$this->template->assign(array('scm_cfg' => $this->scm_cfg));
				$this->template->assign(array('shopSno' => $this->config_system['shopSno']));
				$this->template->define(array('tpl'=>$file_path));
				$this->template->assign(array('record' => $record));
				$this->template->assign(array('npay_use' => $npay_use));
				$this->template->print_("tpl");
	}

	public function view()
	{
		$this->load->model('membermodel');
		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('returnmodel');
		$this->load->model('refundmodel');
		$this->load->model('eventmodel');
		$this->load->model('providermodel');
		$this->load->model('exportlogmodel');
		$this->load->model('giftmodel');
		$this->load->model('scmmodel');
		$this->load->model('orderpackagemodel');
		$this->load->model('broadcastmodel');
		$this->load->library('orderlibrary');

		# 무통장자동입금(뱅크다) 사용여부
		$this->load->model('usedmodel');
		$banks = $this->usedmodel->autodeposit_check();
		$this->template->assign(array('bankChk'=>$banks['chk'],'bankCount'=>$banks['count']));

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');

		$aParamsGet = $this->input->get();
		
		if(!$aParamsGet['callPage']) {
			if(strstr($_SERVER['HTTP_REFERER'], '/company_catalog')) {
				$aParamsGet['callPage'] = 'company_catalog';
			}else{
				$aParamsGet['callPage'] = 'catalog';
			}
		}
		$order_seq 	= $aParamsGet['no'];
		$pagemode	= $aParamsGet['pagemode'];
		$callPage	= $aParamsGet['callPage'];
		$file_path	= $this->template_path();

		$socialcp_status_loop = get_socialcp_status($this->exportmodel->socialcp_status);
		$this->template->assign(array('socialcp_status_loop'	=> $socialcp_status_loop));
		$socialcp_status_path = dirname($this->template_path()).'/../order/_socialcp_status_guide.html';
		$this->template->define(array('socialcp_status_guide'=>$socialcp_status_path));
		$error_export_log = $this->exportlogmodel->get_log_for_order($order_seq);
		$this->template->assign(array('error_export_log'	=> $error_export_log));
		$process_log 	= $this->ordermodel->get_log($order_seq,'all');
		$cancel_log 	= $this->ordermodel->get_log($order_seq,'cancel');

		$orders 			= $this->ordermodel->get_order($order_seq);
		$items 				= $this->ordermodel->get_item($order_seq);
		$child_order_seq	= $this->ordermodel->get_child_order_seq($order_seq);

		$accountAllMiDate = config_load("accountall_setting","accountall_migration_date");
		$accountAllMigrationDate = $accountAllMiDate['accountall_migration_date'];

		// 주문서쿠폰 정보 추가
		$ordersheet_coupon_info		= $this->ordermodel->get_order_discount_info($orders['ordersheet_seq']);
		$orders['ordersheet_coupon_info']		= ($ordersheet_coupon_info[coupon_info] !== false) ? $ordersheet_coupon_info[coupon_info] : '';

		//npay 사용확인
		$npay_use = npay_useck();
		//카카오톡구매 사용확인
		$talkbuy_use = talkbuy_useck();
		if($npay_use || $talkbuy_use){
			$this->load->library('partnerlib');
			$partner_log 			= $this->ordermodel->get_log($order_seq,'all',array(),array("add_info"=>array("npay","talkbuy")));
			$partner_log_tmp = array();
			foreach($partner_log as &$log){
				if($log['detail']){
					$detail = explode("a:",$log['detail']);
					// 내용 비어있고 개행 문자열만 들어있는경우
					if (strlen($detail[0]) === 1 && ord($detail[0]) === 10) {
						$log['detail'] = '';
					} else {
						$log['detail'] = $detail[0];
					}
				}
			}
			$partner_log = $this->partnerlib->viewOrderLog($partner_log);
			$process_log = $this->partnerlib->viewOrderLog($process_log);
			$error_export_log = $this->partnerlib->viewOrderLog($error_export_log);
		}

		// 배송방법
		$orders['mshipping'] = $this->ordermodel->get_delivery_method($orders);

		$orders['mstep'] 	= $this->arr_step[$orders['step']];

		if($orders['recipient_phone']) $orders['recipient_phone'] 	= explode('-',$orders['recipient_phone']);
		if($orders['recipient_cellphone']) $orders['recipient_cellphone'] 	= explode('-',$orders['recipient_cellphone']);

		$arr = config_load('bank');
		if($arr) foreach(config_load('bank') as $k => $v){
			list($tmp) = code_load('bankCode',$v['bank']);
			$v['bank'] = $tmp['value'];
			$bank[] = $v;
		}

		// 되돌리기 버튼 노출
		$revert_btn = $this->orderlibrary->get_order_revert($orders);
		$this->template->assign('revert_btn', $revert_btn);

		$gifts_item				= array();
		$tot['goodstotal']		= $tot['coupontotal'] = 0;
		$reserve_ea				= 0;
		$is_option_international_shipping = false;

		foreach($items as $key=>$item){

			// 상품종수
			$item_cnt[$item['goods_seq']] = 1;

			// 입점사 갯수 카운터 :: 2015-10-08 lwh
			$provider_cnt[$item['provider_seq']] += 1;

			if ( $item['goods_kind'] == 'coupon' ) {
				$tot['coupontotal']++;//티켓상품@2013-11-06
			}else{
				$tot['goodstotal']++;
			}

			if ( $item['option_international_shipping_status'] == 'y' ) {
				$is_option_international_shipping = true;
			}

			$reOption	= array();
			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);

			if($item['event_seq']) {
				$events = $this->eventmodel->get_event($item['event_seq']);
				if($events['title']) $item['event_title'] = strip_tags($events['title']);
				if($events['event_type']) $item['event_type'] = $events['event_type'];

				// 이벤트 할인 부담금 추가로 인해 event_benefit 정보 가져옴 :: 2018-07-13 pjw
				$events_benefit = $this->eventmodel->get_event_benefit($item['event_seq']);
			}

			if($item['bs_seq']) {
				$broadcast = $this->broadcastmodel->getSchEach($item['bs_seq']);
				if($broadcast['title']) $item['bs_title'] = strip_tags($broadcast['title']);

			}

			## 사은품
			$item['gift_title'] = "";
			if($item['goods_type'] == "gift"){
				$gifts_item[] = $item['item_seq'];
				$giftlog = $this->giftmodel->get_gift_title($order_seq,$item['item_seq']);
				$item['gift_title'] = $giftlog['gift_title'];
			}

			$rowspan	= 0;
			if($options) foreach($options as $k => $data){

				//주문상품 앞에 아이콘표시(패/예/사/구/티/19/청/위)
				$data['order_goodstype'] = array();
				if($data['package_yn'] == "y"){ $data['order_goodstype']['package'] = "패키지/복합상품"; }
				if($item['goods_type'] == "gift"){ $data['order_goodstype']['gift'] = "사은품"; }
				if($item['goods_kind'] == "coupon"){ $data['order_goodstype']['ticket'] = "티켓"; }
				if($data['shipping_provider_seq'] == 1 && ($data['provider_seq'] != 1 && $item['goods_type'] != 'gift')){
					$data['order_goodstype']['consignment'] = "위탁배송상품";
				}
				$item['shipping_provider_seq'] = $data['shipping_provider_seq'];
				if($item['adult_goods'] == "Y"){ $data['order_goodstype']['adult'] = "성인상품"; }
				if($item['reservation_ship'] == "y"){ $data['order_goodstype']['reserve'] = "예약상품"; }
				if($item['option_international_shipping_status'] == "y"){ $data['order_goodstype']['international_shipping'] = "구매대행상품"; }
				if($item['cancel_type'] == '1'){ $data['order_goodstype']['withdraw'] = "청약철회불가상품"; }

				if	($data['step'] > $goods_kind_arr[$item['goods_kind']])
					$goods_kind_arr[$item['goods_kind']]	= $data['step'];

					if( $data['step'] < 35 ) $tot['goods_ready_cnt']++;//상품별 상품준비 버튼 노출여부

					// 마일리지 로그
					if	( $data['reserve_log'] ){
						$arr_reserve_log = explode(' / ',$data['reserve_log']);
						$data['reserve_log'] = $arr_reserve_log;
					}

					// 포인트 로그
					if	( $data['point_log'] ){
						$arr_point_log = explode(' / ',$data['point_log']);
						$data['point_log'] = $arr_point_log;
					}

					// 개별 배송메시지 별도 정의 :: 2016-09-02 lwh
					if($orders['each_msg_yn'] == 'Y'){
						$goods_info_name = $item['goods_name'] . ' ';
						/*if($data['title1'])	$goods_info_name .= $data['title1'] . ' : ' . $data['option1'];
						 if($data['title2'])	$goods_info_name .= $data['title2'] . ' : ' . $data['option2'];
						 if($data['title3'])	$goods_info_name .= $data['title3'] . ' : ' . $data['option3'];
						 if($data['title4'])	$goods_info_name .= $data['title4'] . ' : ' . $data['option4'];
						 if($data['title5'])	$goods_info_name .= $data['title5'] . ' : ' . $data['option5'];
						 */
						$ship_message['ship_message'][]	= $data['ship_message'];
						$ship_message['goods_info'][]	= $goods_info_name;
					}

					// 물류관리 창고 정보 추출
					if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == 1 && $data['package_yn'] != 'y' && $item['goods_kind'] != 'coupon' ){
						unset($sc);
						if	($data['option1'])	$sc['option1']		= $data['option1'];
						if	($data['option2'])	$sc['option2']		= $data['option2'];
						if	($data['option3'])	$sc['option3']		= $data['option3'];
						if	($data['option4'])	$sc['option4']		= $data['option4'];
						if	($data['option5'])	$sc['option5']		= $data['option5'];
						$optionData			= $this->goodsmodel->get_goods_option($item['goods_seq'], $sc);
						if	($optionData[0]['option_seq'] > 0){
							$optionStr		= $item['goods_seq'] . 'option' . $optionData[0]['option_seq'];
							$whinfo			= $this->scmmodel->get_warehouse_stock($this->scm_cfg['export_wh'], 'optioninfo', '', array($optionStr));
							$data['whinfo']	= $whinfo[$optionStr];
						}
						$data['whinfo']['wh_seq']	= $this->scm_cfg['export_wh'];
					}

					$data['goods_option_seq'] = $optionData[0]['option_seq'];

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

					$badstock = $this->goodsmodel -> get_goods_option_badstock(
							$item['goods_seq'],
							$data['option1'],
							$data['option2'],
							$data['option3'],
							$data['option4'],
							$data['option5']
							);

					# 재고연동 여부 확인. 재고가 null 이면 미매칭 상품 @2015-09-02 pjm
					if( $real_stock === "미매칭" ){
						$real_stock = "미매칭";
						$rstock		= "미매칭";
						$stock		= "미매칭";
						$data['nomatch']++;
					}else{
						$real_stock = (int) $real_stock;
						$rstock		= (int) $rstock;
						$stock		= $real_stock - $badstock - $rstock;
					}

					$data['mstep']			= $this->arr_step[$data['step']];
					$data['real_stock']		= $real_stock;
					$data['stock']			= $stock;
					$orders['real_stock']	= ($real_stock == '미매칭')?$real_stock:$orders['real_stock'];	// 39534 미매칭 상태 우선 처리 by hed
					$orders['stock']		= $stock;

					// #32781 미매칭상품과 재고에 대해 출력 변경 by hed
					$orders['miss_stock']	= ($real_stock <= 0 || !empty($orders['miss_stock']))?'Y':'';

					if($orders['international'] != 'international'){
						if($item['shipping_policy'] == 'shop'){
							$data['out_shipping_method'] = $orders['mshipping'];
						}else{
							$data['out_shipping_method'] = "개별배송";
						}
					}

					// 옵션 배송방법별 출고 수량
					$tmp_option_export_item = $this->exportmodel->get_export_item_by_option_seq($data['item_option_seq'], $data['order_seq']);
					foreach($tmp_option_export_item as $option_export_item){
						if($option_export_item['domestic_shipping_method']){
							$data['export_sum_ea'][$option_export_item['domestic_shipping_method']] += $option_export_item['ea'];
						}
					}

					$data['option_shipping'] = $this->ordermodel->get_shipping_for_option($data['item_option_seq']);

					// 매입
					$data['out_supply_price']		= $data['supply_price']*$data['ea'];

					// 정산
					$data['out_commission_price']		= $data['commission_price']*$data['ea'];
					// 정산
					$data['out_commission_price_krw']	= $data['commission_price_krw']*$data['ea'];

					// 상품금액
					$data['out_price'] = $data['price']*$data['ea'];

					$data['out_consumer_price']		= $data['consumer_price']*$data['ea'];

					// 할인
					// 이벤트, 복수구매 추가 2018-07-13 pjw
					// 마일리지 추가 2018-07-17 pjw
					// 에누리 추가 2018-07-31 pjw
					$data['out_event_sale']				= $data['event_sale'];
					$data['out_multi_sale']				= $data['multi_sale'];
					$data['out_member_sale']			= $data['member_sale']*$data['ea'];
					$data['out_coupon_sale']			= ($data['download_seq'])?$data['coupon_sale']:0;
					$data['out_coupon_sale']			+= $data['unit_ordersheet'];
					$data['out_fblike_sale']			= $data['fblike_sale'];
					$data['out_mobile_sale']			= $data['mobile_sale'];
					$data['out_promotion_code_sale']	= $data['promotion_code_sale'];
					$data['out_referer_sale']			= $data['referer_sale'];
					// $data['unit_emoney'], $data['unit_enuri'] 다른 금액은 수량을 더한 총 금액이므로 수량 금액으로 변경 by hed
					$data['out_emoney_sale']			= ($data['emoney_sale_unit']*$data['ea'])+$data['emoney_sale_rest'];
					$data['out_enuri_sale']				= ($data['enuri_sale_unit']*$data['ea'])+$data['enuri_sale_rest'];

					// 할인 합계
					// 이벤트, 복수구매 추가 2018-07-13 pjw
					// 에누리 추가 2018-07-31 pjw
					$data['out_tot_sale'] = $data['out_event_sale'];
					$data['out_tot_sale'] += $data['out_multi_sale'];
					$data['out_tot_sale'] += $data['out_member_sale'];
					$data['out_tot_sale'] += $data['out_coupon_sale'];
					$data['out_tot_sale'] += $data['out_fblike_sale'];
					$data['out_tot_sale'] += $data['out_mobile_sale'];
					$data['out_tot_sale'] += $data['out_promotion_code_sale'];
					$data['out_tot_sale'] += $data['out_referer_sale'];
					$data['out_tot_sale'] += $data['out_emoney_sale'];
					$data['out_tot_sale'] += $data['out_enuri_sale'];

					// 할인가격
					$data['out_sale_price'] = $data['out_price'] - $data['out_tot_sale'];
					// 마이그레이션 이전은 event_sale 이 중복으로 적용되어있어서 수정함 2020-03-11
					if($orders['regist_date'] < $accountAllMigrationDate) {
						$data['out_sale_price'] += $data['out_event_sale'];
					}
					$data['sale_price']		= $data['out_sale_price'] / $data['ea'];

					// 정산차감
					// 입점사 이벤트 부담금 추가 2018-07-13 pjw
					$data['event_provider']		= (($data['out_event_sale'] / 100) * $events_benefit[0]['salescost_provider']);
					$data['coupon_provider']	= $data['salescost_provider_coupon'] * $data['ea'];
					$data['promotion_provider'] = $data['salescost_provider_promotion'] * $data['ea'];
					$data['referer_provider']	= $data['salescost_provider_referer'] * $data['ea'];
					$data['multi_provider']		= ($data['provider_seq'] != 1)? $data['out_multi_sale']:0;
					$data['tot_sale_provider']	= $data['referer_provider'] + $data['promotion_provider'] + $data['coupon_provider'] + $data['event_provider'] + $data['multi_provider'];
					// 할인부담금
					if	($item['provider_seq'] > 1){
						$salescost[$item['provider_seq']]['name']					= $item['provider_name'];
						$salescost[$item['provider_seq']]['salescost']['coupon']	+= $data['coupon_provider'];
						$salescost[$item['provider_seq']]['original']['coupon']		+= $data['out_coupon_sale'];
						$salescost[$item['provider_seq']]['salescost']['promotion']	+= $data['promotion_provider'];
						$salescost[$item['provider_seq']]['original']['promotion']	+= $data['out_promotion_code_sale'];
						$salescost[$item['provider_seq']]['salescost']['referer']	+= $data['referer_provider'];
						$salescost[$item['provider_seq']]['original']['referer']	+= $data['out_referer_sale'];

						// 이벤트 부담금 추가 2018-07-13 pjw
						$salescost[$item['provider_seq']]['salescost']['event']		+= $data['event_provider'];
						$salescost[$item['provider_seq']]['original']['event']		+= $data['out_event_sale'];

						// 복수구매할인 부담금 추가(입점사 상품은 입점사 부담)
						$salescost[$item['provider_seq']]['salescost']['multi']		+= $data['multi_provider'];
						$salescost[$item['provider_seq']]['original']['multi']		+= $data['out_multi_sale'];

						// 총 할인 부담금
						$salescost[$item['provider_seq']]['salescost']['total']		+= $data['coupon_provider']
																					+  $data['promotion_provider']
																					+  $data['referer_provider']
																					+  $data['multi_provider']
																					+  $data['out_event_sale'];

					}

					// 예상적립
					$data['out_reserve']	= $data['reserve']*$data['ea'];
					$data['out_point']		= $data['point']*$data['ea'];

					$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];
					$data['step25'] = 0;
					if($data['step'] >= 25 && $data['step'] < 95){
						$data['step25'] = $data['ea'] - (  $data['step35'] + $data['step45']+$data['step55']+$data['step65']+$data['step75']+$data['step85'] );
					}

					if($data['step'] >= 25 && $data['step'] < 75){
						$data['ready_ea'] = $data['ea'] - $data['step_complete'] - $data['step85'];
					}

					###
					unset($data['inputs']);
					$data['inputs'] = $this->ordermodel->get_input_for_option($data['item_seq'],$data['item_option_seq']);

					$tmp = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq'],null,'return');
					$data['return_list_ea'] = $tmp['ea'];
					$tmp = $this->refundmodel->get_refund_option_ea($data['item_seq'],$data['item_option_seq'],"return");
					$data['refund_list_ea'] = $tmp;
					$tmp = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq'],null,'exchange');
					$data['exchange_list_ea'] = $tmp['ea'];


					// 2015-07-21  할인내역 상세항목 추가 - Added by jp
					$return		= $this->ordermodel->get_order_discount_info($data['download_seq'], $data['promotion_code_seq'], $data['referersale_seq']);
					$data['coupon_info']		= ($return[coupon_info] !== false) ? $return[coupon_info] : '';
					$data['promotion_info']		= ($return[promotion_info] !== false) ? $return[promotion_info] : '';
					$data['referersale_info']	= ($return[referersale_info] !== false) ? $return[referersale_info] : '';

					if($data['package_yn'] == 'y'){
						$data['packages'] = $this->orderpackagemodel->get_option($data['item_option_seq']);
						$rowspan += count($data['packages']);
						foreach($data['packages'] as $key_packages=>$data_package){
							$real_stock = (int) $data_package['stock'];
							$badstock = (int) $data_package['badstock'];
							$reservation = (int) $data_package['reservation'.$this->cfg_order['ableStockStep']];
							$stock = $real_stock - $badstock - $reservation;
							$data['packages'][$key_packages]['ablestock'] = $stock;

							$tot['stock'] += $stock;
							$tot['real_stock'] += $real_stock;

							if(!$data_package['option_seq']){
								$data['nomatch']++;
								$data['packages'][$key_packages]['stock'] = "미매칭";
							}

							// 물류관리 창고 정보 추출
							if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == 1){
								if	($data_package['option_seq'] > 0){
									$optionStr		= $data_package['goods_seq'] . 'option' . $data_package['option_seq'];
									$whinfo			= $this->scmmodel->get_warehouse_stock($this->scm_cfg['export_wh'], 'optioninfo', '', array($optionStr));

									$data['packages'][$key_packages]['whinfo']	= $whinfo[$optionStr];
								}
								$data['packages'][$key_packages]['whinfo']['wh_seq']	= $this->scm_cfg['export_wh'];
							}

						}
					}

					$options[$k] = $data;

					$tot['ea']					+= $data['ea'];
					$tot['ready_ea']			+= $data['ready_ea'];
					$tot['step_complete']		+= $data['step_complete'];
					$tot['step25']				+= $data['step25'];
					$tot['step35']				+= $data['step35'];
					$tot['step85']				+= $data['step85'];
					$tot['step45']				+= $data['step45'];
					$tot['step55']				+= $data['step55'];
					$tot['step65']				+= $data['step65'];
					$tot['step75']				+= $data['step75'];
					$tot['supply_price']		+= $data['out_supply_price'];
					$tot['commission_price']	+= $data['out_commission_price'];
					$tot['consumer_price']		+= $data['out_consumer_price'];
					$tot['price']				+= $data['out_price'];
					$tot['out_sale_price']				+= $data['out_sale_price'];

					// 이벤트, 복수구매 추가 2018-07-13 pjw
					// 마일리지 추가 2018-07-17 pjw
					// 에누리 추가 2018-07-31 pjw
					$tot['event_sale']			+= $data['out_event_sale'];
					$tot['multi_sale']			+= $data['out_multi_sale'];
					$tot['member_sale']			+= $data['out_member_sale'];
					$tot['coupon_sale']			+= $data['out_coupon_sale'];
					$tot['fblike_sale']			+= $data['out_fblike_sale'];
					$tot['mobile_sale']			+= $data['out_mobile_sale'];
					$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];
					$tot['referer_sale']		+= $data['out_referer_sale'];
					$tot['emoney_sale']			+= $data['out_emoney_sale'];
					$tot['enuri_sale']			+= $data['out_enuri_sale'];

					$tot['npay_sale_npay']		+= $data['npay_sale_npay'];
					$tot['npay_sale_seller']	+= $data['npay_sale_seller'];	//npay 할인(판매자부담)

					$tot['coupon_provider']		+= $data['coupon_provider'];
					$tot['promotion_provider']	+= $data['promotion_provider'];
					$tot['referer_provider']	+= $data['referer_provider'];
					$tot['event_provider']		+= $data['event_provider'];
					$tot['multi_provider']		+= $data['multi_provider'];

					$tot['reserve']				+= $data['out_reserve'];
					$tot['point']				+= $data['out_point'];
					if($real_stock != '미매칭' && $data['package_yn'] != 'y' ) $tot['real_stock']		+= $real_stock;
					if($stock != '미매칭' && $data['package_yn'] != 'y' ) $tot['stock']				+= $stock;

					# 실물상품의 반품/취소 가능 수량 구하기
					if($item['goods_kind'] != 'coupon'){
						// 반품가능수량
						$return_item = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq']);
						$able_return_ea_tmp = $able_after_return_ea_tmp = (int) $data['step75'] + (int) $data['step55'] + (int) $data['step65']  - (int) $return_item['ea'];

						# 구매확정 사용시 : 반품,환불 불가
						if($this->cfg_order['buy_confirm_use'] && $able_return_ea_tmp > 0){
							$que	= "select reserve_buyconfirm_ea from fm_goods_export_item where item_seq=? and option_seq=?";
							$query	= $this->db->query($que,array($data['item_seq'],$data['item_option_seq']));
							$exp_reserve = $query->row_array();
							$able_return_ea_tmp -= $exp_reserve['reserve_buyconfirm_ea'];
						}

						$able_return_ea += $able_return_ea_tmp;
						$able_after_return_ea += $able_after_return_ea_tmp;

						// 취소가능수량
						if($data['step'] != 95){
							$able_refund_ea += (int) $data['ea'] - (int) $data['step45'] - (int) $data['step55'] - (int) $data['step65'] - (int) $data['step75'] - (int) $data['step85'];
						}else{
							$able_refund_ea += 0;
						}

						# Npay 주문건. 구매확정시 반품 불가(=구매확정 가능 수량이 있을 경우에만 반품 가능)
						if($able_return_ea_tmp > 0 && $npay_use && $orders['pg'] == "npay"){
							$que	= "select reserve_ea from fm_goods_export_item where item_seq=? and option_seq=?";
							$query	= $this->db->query($que,array($data['item_seq'],$data['item_option_seq']));
							$exp_reserve = $query->row_array();
							$reserve_ea += $exp_reserve['reserve_ea'];
						}
					}

					$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);
					if($suboptions) foreach($suboptions as $k => $subdata){
						if	($subdata['step'] > $goods_kind_arr[$item['goods_kind']])
							$goods_kind_arr[$item['goods_kind']]	= $subdata['step'];

							if( $subdata['step'] < 35 ) $tot['goods_ready_cnt']++;//상품별 상품준비 버튼 노출여부

							// 마일리지 로그
							if	( $subdata['reserve_log'] ){
								$arr_reserve_log = explode(' / ',$subdata['reserve_log']);
								$subdata['reserve_log'] = $arr_reserve_log;
							}

							// 포인트 로그
							if	( $subdata['point_log'] ){
								$arr_point_log = explode(' / ',$subdata['point_log']);
								$subdata['point_log'] = $arr_point_log;
							}

							// 물류관리 창고 정보 추출
							if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == 1 && $subdata['package_yn'] != 'y' && $item['goods_kind'] != 'coupon' ){
								unset($sc);
								if	($subdata['title'])		$sc['suboption_title']	= $subdata['title'];
								if	($subdata['suboption'])	$sc['suboption']		= $subdata['suboption'];
								$optionData			= $this->goodsmodel->get_goods_suboption($item['goods_seq'], $sc);
								if	($optionData[0][0]['suboption_seq'] > 0){
									$optionStr		= $item['goods_seq'] . 'suboption' . $optionData[0][0]['suboption_seq'];
									$whinfo			= $this->scmmodel->get_warehouse_stock($this->scm_cfg['export_wh'], 'optioninfo', '', array($optionStr));
									$subdata['whinfo']	= $whinfo[$optionStr];
								}
								$subdata['whinfo']['wh_seq']	= $this->scm_cfg['export_wh'];
							}

							$real_stock		= $this->goodsmodel -> get_goods_suboption_stock(
									$item['goods_seq'],
									$subdata['title'],
									$subdata['suboption']
									);
							$rstock		= $this->ordermodel -> get_suboption_reservation(
									$this->cfg_order['ableStockStep'],
									$item['goods_seq'],
									$subdata['title'],
									$subdata['suboption']
									);

							# 재고연동 여부 확인. 재고가 null 이면 미매칭 상품 @2015-09-02 pjm
							if( $real_stock === '미매칭' ){
								$real_stock = "미매칭";
								$rstock = "미매칭";
								$stock	= "미매칭";
								$data['nomatch']++;
							}else{
								$real_stock = (int) $real_stock;
								$rstock		= (int) $rstock;
								## 옵션 정보가 변경/삭제되어 미매칭 된 경우. @2015-09-01 pjm
								$stock	= $real_stock - $rstock;
							}

							$subdata['real_stock']	= $real_stock;
							$subdata['stock']		= $stock;

							// #32781 미매칭상품과 재고에 대해 출력 변경 by hed
							$orders['miss_stock']	= ($real_stock <= 0 || !empty($orders['miss_stock']))?'Y':'';

							// 추가옵션 배송방법별 출고 수량
							$tmp_option_export_item = $this->exportmodel->get_export_item_by_suboption_seq($subdata['item_suboption_seq']);
							foreach($tmp_option_export_item as $option_export_item){
								if($option_export_item['domestic_shipping_method']){
									$subdata['export_sum_ea'][$option_export_item['domestic_shipping_method']] += $option_export_item['ea'];
								}
							}

							###
							$subdata['out_supply_price']		= $subdata['supply_price']*$subdata['ea'];
							$subdata['out_commission_price']	= $subdata['commission_price']*$subdata['ea'];
							$subdata['out_commission_price_krw']= $subdata['commission_price_krw']*$subdata['ea'];
							$subdata['out_consumer_price']		= $subdata['consumer_price']*$subdata['ea'];
							$subdata['out_price']				= $subdata['price']*$subdata['ea'];

							// 할인
							// 이벤트, 복수구매 추가 2018-07-13 pjw
							$subdata['out_event_sale']			= $subdata['event_sale'];
							$subdata['out_multi_sale']			= $subdata['multi_sale'];
							$subdata['out_member_sale']			= $subdata['member_sale']*$subdata['ea'];
							$subdata['out_coupon_sale']			= ($subdata['download_seq'])?$subdata['coupon_sale']:0;
							$subdata['out_fblike_sale']			= $subdata['fblike_sale'];
							$subdata['out_mobile_sale']			= $subdata['mobile_sale'];
							$subdata['out_promotion_code_sale'] = $subdata['promotion_code_sale'];
							$subdata['out_referer_sale']		= $subdata['referer_sale'];
							// $data['unit_emoney'], $data['unit_enuri'] 다른 금액은 수량을 더한 총 금액이므로 수량 금액으로 변경 by hed
							$subdata['out_emoney_sale']			= ($subdata['emoney_sale_unit']*$subdata['ea'])+$subdata['emoney_sale_rest'];
							$subdata['out_enuri_sale']				= ($subdata['enuri_sale_unit']*$subdata['ea'])+$subdata['enuri_sale_rest'];

							// 할인 합계
							// 이벤트, 복수구매 추가 2018-07-13 pjw
							// 에누리 추가 2018-07-31 pjw
							$subdata['out_tot_sale'] = $subdata['out_event_sale'];
							$subdata['out_tot_sale'] += $subdata['out_multi_sale'];
							$subdata['out_tot_sale'] += $subdata['out_member_sale'];
							$subdata['out_tot_sale'] += $subdata['out_coupon_sale'];
							$subdata['out_tot_sale'] += $subdata['out_fblike_sale'];
							$subdata['out_tot_sale'] += $subdata['out_mobile_sale'];
							$subdata['out_tot_sale'] += $subdata['out_promotion_code_sale'];
							$subdata['out_tot_sale'] += $subdata['out_referer_sale'];
							$subdata['out_tot_sale'] += $subdata['out_enuri_sale'];
							$subdata['out_tot_sale'] += $subdata['out_emoney_sale'];

							// 할인가격
							$subdata['out_sale_price'] = $subdata['out_price'] - $subdata['out_tot_sale'];
							// 마이그레이션 이전은 event_sale 이 중복으로 적용되어있어서 수정함 2020-03-11
							if($orders['regist_date'] < $accountAllMigrationDate) {
								$subdata['out_sale_price'] += $subdata['out_event_sale'];
							}
							$subdata['sale_price'] = $subdata['out_sale_price'] / $subdata['ea'];


							// 정산차감
							$subdata['coupon_provider']		= $subdata['salescost_provider_coupon'] * $subdata['ea'];
							$subdata['promotion_provider']	= $subdata['salescost_provider_promotion'] * $subdata['ea'];
							$subdata['referer_provider']	= $subdata['salescost_provider_referer'] * $subdata['ea'];
							$subdata['tot_sale_provider']	= $subdata['referer_provider'] + $subdata['promotion_provider'] + $subdata['coupon_provider'];
							// 입점사 복수 구매할인 by 2019-09-02 by hed
							$subdata['multi_provider']		= 0;

							if	($item['provider_seq'] > 1){
								$salescost[$item['provider_seq']]['name']						= $item['provider_name'];
								$salescost[$item['provider_seq']]['salescost']['total']			+= $subdata['coupon_provider'] + $subdata['promotion_provider'] + $subdata['referer_provider'];
								$salescost[$item['provider_seq']]['salescost']['coupon']		+= $subdata['coupon_provider'];
								$salescost[$item['provider_seq']]['original']['coupon']			+= $subdata['out_coupon_sale'];
								$salescost[$item['provider_seq']]['salescost']['promotion']		+= $subdata['promotion_provider'];
								$salescost[$item['provider_seq']]['original']['promotion']		+= $subdata['out_promotion_code_sale'];
								$salescost[$item['provider_seq']]['salescost']['referer']		+= $subdata['referer_provider'];
								$salescost[$item['provider_seq']]['original']['referer']		+= $subdata['out_referer_sale'];

								// 입점사 복수 구매할인 by 2019-09-02 by hed
								// 복수구매할인은 부담금을 본사와 나눠갖지 않으므로 총 할인 부담금에 가산하지 않음
								$subdata['multi_provider']		= $subdata['out_multi_sale'];
							}

							$subdata['out_reserve']				= $subdata['reserve']*$subdata['ea'];
							$subdata['out_point']				= $subdata['point']*$subdata['ea'];

							$subdata['mstep']					= $this->arr_step[$subdata['step']];
							$subdata['step_complete']			= $subdata['step45'] + $subdata['step55'] + $subdata['step65'] + $subdata['step75'];

							$subdata['step25'] = 0;
							if($subdata['step']>=25 && $subdata['step'] < 95){
								$subdata['step25'] = $subdata['ea'] - (  $subdata['step35'] + $subdata['step45']+$subdata['step55']+$subdata['step65']+$subdata['step75']+$subdata['step85'] );
							}

							if($subdata['step'] >= 25 && $subdata['step'] < 75){
								$subdata['ready_ea'] = $subdata['ea'] - $subdata['step_complete'] - $subdata['step85'];
							}

							$tmp = $this->returnmodel->get_return_subitem_ea($subdata['item_seq'],$subdata['item_suboption_seq'],null,'return');
							$subdata['return_list_ea'] = $tmp['ea'];
							$tmp = $this->refundmodel->get_refund_suboption_ea($subdata['item_seq'],$subdata['item_suboption_seq'],"return");
							$subdata['refund_list_ea'] = $tmp;
							$tmp = $this->returnmodel->get_return_subitem_ea($subdata['item_seq'],$subdata['item_suboption_seq'],null,'exchange');
							$subdata['exchange_list_ea'] = $tmp['ea'];

							if($subdata['package_yn'] == 'y'){
								$subdata['packages'] = $this->orderpackagemodel->get_suboption($subdata['item_suboption_seq']);
								$rowspan += count($subdata['packages']);
								foreach($subdata['packages'] as $key_packages => $data_package){
									$stock = (int) $data_package['stock'];
									$badstock = (int) $data_package['badstock'];
									$reservation = (int) $data_package['reservation'.$this->cfg_order['ableStockStep']];
									$ablestock = $stock - $badstock - $reservation;

									$subdata['packages'][$key_packages]['ablestock'] = $ablestock;

									if(!$data_package['option_seq']){
										$data['nomatch']++;
										$subdata['packages'][$key_packages]['stock'] = "미매칭";
									}
									$tot['stock'] += $stock;
									$tot['real_stock'] += $ablestock;

									// 물류관리 창고 정보 추출
									if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == 1){
										if	($data_package['option_seq'] > 0){
											$optionStr		= $data_package['goods_seq'] . 'option' . $data_package['option_seq'];

											$whinfo			= $this->scmmodel->get_warehouse_stock($this->scm_cfg['export_wh'], 'optioninfo', '', array($optionStr));

											$subdata['packages'][$key_packages]['whinfo']	= $whinfo[$optionStr];
										}
										$subdata['packages'][$key_packages]['whinfo']['wh_seq']	= $this->scm_cfg['export_wh'];
									}
								}
							}
							$suboptions[$k]						= $subdata;

							$tot['ea']					+= $subdata['ea'];
							$tot['ready_ea']			+= $subdata['ready_ea'];
							$tot['step25']				+= $subdata['step25'];
							$tot['step35']				+= $subdata['step35'];
							$tot['step85']				+= $subdata['step85'];
							$tot['step45']				+= $subdata['step45'];
							$tot['step55']				+= $subdata['step55'];
							$tot['step65']				+= $subdata['step65'];
							$tot['step75']				+= $subdata['step75'];

							$tot['step_complete']		+= $subdata['step_complete'];
							$tot['supply_price'] 		+= $subdata['out_supply_price'];
							$tot['commission_price']	+= $subdata['out_commission_price'];
							$tot['consumer_price'] 		+= $subdata['out_consumer_price'];

							// 이벤트, 복수구매 추가 2018-07-13 pjw
							// 에누리 추가 2018-07-31 pjw
							$tot['event_sale']			+= $subdata['out_event_sale'];
							$tot['multi_sale']			+= $subdata['out_multi_sale'];
							$tot['member_sale']			+= $subdata['out_member_sale'];
							$tot['coupon_sale']			+= $subdata['out_coupon_sale'];
							$tot['fblike_sale']			+= $subdata['out_fblike_sale'];
							$tot['mobile_sale']			+= $subdata['out_mobile_sale'];
							$tot['promotion_code_sale']	+= $subdata['out_promotion_code_sale'];
							$tot['referer_sale']		+= $subdata['out_referer_sale'];
							$tot['emoney_sale']			+= $subdata['out_emoney_sale'];
							$tot['enuri_sale']			+= $subdata['out_enuri_sale'];

							$tot['coupon_provider']		+= $subdata['coupon_provider'];
							$tot['promotion_provider']	+= $subdata['promotion_provider'];
							$tot['referer_provider']	+= $subdata['referer_provider'];

							$tot['price'] 				+= $subdata['out_price'];
							$tot['out_sale_price']	+= $subdata['out_sale_price'];

							$tot['reserve']				+= $subdata['out_reserve'];
							$tot['point']				+= $subdata['out_point'];

							if($real_stock != '미매칭' && $subdata['package_yn'] != 'y') $tot['real_stock']		+= $real_stock;
							if($stock != '미매칭' && $subdata['package_yn'] != 'y')	$tot['stock']			+= $stock;

							# 실물상품의 반품/취소 가능 수량 구하기
							if($item['goods_kind'] != 'coupon'){
								// 반품가능수량
								$return_item = $this->returnmodel->get_return_item_ea($subdata['item_seq'],$subdata['item_suboption_seq']);
								$able_return_ea_tmp = $able_after_return_ea_tmp = (int) $subdata['step75'] + (int) $subdata['step55'] + (int) $subdata['step65']  - (int) $return_item['ea'];

								# 구매확정 사용시 : 반품,환불 불가
								if($this->cfg_order['buy_confirm_use'] && $able_return_ea_tmp > 0){
									$que	= "select reserve_buyconfirm_ea from fm_goods_export_item where item_seq=? and suboption_seq=?";
									$query	= $this->db->query($que,array($subdata['item_seq'],$subdata['item_option_seq']));
									$exp_reserve = $query->row_array();
									$able_return_ea_tmp -= $exp_reserve['reserve_buyconfirm_ea'];
								}

								$able_return_ea += $able_return_ea_tmp;
								$able_after_return_ea += $able_after_return_ea_tmp;	// 구매확정후 반품가능수량

								// 취소가능수량
								if($subdata['step'] != 95){
									$able_refund_ea_sub = (int) $subdata['ea'] - (int) $subdata['step85'] - (int) $subdata['step45'] - (int) $subdata['step55'] - (int) $subdata['step65'] - (int) $subdata['step75'];
									$able_refund_ea += $able_refund_ea_sub;
								}else{
									$able_refund_ea += 0;
								}

								# Npay 주문건. 구매확정시 반품 불가(=구매확정 가능 수량이 있을 경우에만 반품 가능)
								if($able_return_ea_tmp > 0 && $npay_use && $orders['pg'] == "npay"){
									$que	= "select reserve_ea from fm_goods_export_item where item_seq=? and suboption_seq=?";
									$query	= $this->db->query($que,array($subdata['item_seq'],$subdata['item_suboption_seq']));
									$exp_reserve = $query->row_array();
									$reserve_ea += $exp_reserve['reserve_ea'];
								}
							}
					}

					$rowspan			+= count($suboptions);
					$data['suboptions']	= $suboptions;
					$reOption[]			= $data;
			}

			$rowspan			+= count($reOption);
			$item['options']	= $reOption;
			$item['rowspan']	= $rowspan;
			$items[$key] 		= $item;
			$tot['goods_shipping_cost']	+= $item['goods_shipping_cost'];

			if((!$item['goods_seq'] || $stock === '미매칭') && $orders['step'] < 40 && $item['goods_type'] != 'gift' ) $tot['nomatch_goods_cnt']++;
		}

		$shipping_provider_seq = ($pagemode == 'company_catalog' && $aParamsGet['shipping_provider_seq'])? $aParamsGet['shipping_provider_seq'] : null;

		/* 주문상품을 배송그룹별로 분할 */
		$shipping = $this->ordermodel->get_order_shipping($order_seq,$shipping_provider_seq);
		$shipping_group_items=array();
		foreach($items as $item){

			//배송책임 본사선택시(본사상품 및 본사위탁배송 상품만 노출)
			if($shipping_provider_seq == null || ($shipping_provider_seq != null && $item['shipping_provider_seq'] == $shipping_provider_seq)){
				$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost_sum']+= $item['goods_shipping_cost'];
				$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost']+= $item['goods_shipping_cost'];
				$shipping_group_items[$item['shipping_seq']]['rowspan'] += $item['rowspan'];
				$shipping_group_items[$item['shipping_seq']]['items'][] = $item;
				$shipping_group_items[$item['shipping_seq']]['items'][0]['options'][0]['shipping_division']	= 1;
				$shipping_group_items[$item['shipping_seq']]['totalitems'] += count($item['options'])+count($item['suboptions']);
			}
		}

		$this->load->helper('shipping');
		$arr_shipping_method = get_shipping_method('all','');
		foreach($shipping_group_items as $shipping_seq=>$row){
			$shipping_res	= $this->ordermodel->get_order_shipping($order_seq,$shipping_provider_seq,$shipping_seq,'limit');

			$tmp_key		= array_keys($shipping_res);
			$shipping		= $shipping_res[$tmp_key[0]];

			$shipping['shipping_method_name'] = $arr_shipping_method[$shipping['shipping_method']];

			$shipping_tot['shipping_promotion_code_sale']	+= $shipping['shipping_promotion_code_sale'];
			$shipping_tot['shipping_coupon_sale']			+= $shipping['shipping_coupon_sale'];

			// 배송비 종류별 계산 :: 2016-08-18 lwh
			if($shipping['shipping_type'] == 'prepay'){
				$shipping_tot['shipping_cost']			+= $shipping['shipping_cost'];//총배송비
				$shipping_tot['std_shipping_cost']		+= $shipping['delivery_cost'];//선불배송비
				$shipping_tot['add_shipping_cost']	+= $shipping['add_delivery_cost'];//추가배송비
				$shipping_tot['hop_shipping_cost']	+= $shipping['hop_delivery_cost'];//희망배송
				$shipping['shipping_pay_type']		= getAlert("sy002"); // "주문시 결제";

				// 착불배송비 계산 (@2016-09-28 pjm)
			}elseif($shipping['shipping_type'] == 'postpaid'){
				$shipping['postpaid']				= $shipping['delivery_cost'] + $shipping['add_delivery_cost'] + $shipping['hop_delivery_cost'];
				$shipping_tot['postpaid_cost']		+= $shipping['postpaid'];//총착불배송비
				$shipping_tot['std_postpaid_cost']		+= $shipping['delivery_cost'];//착불배송비
				$shipping_tot['add_postpaid_cost']	+= $shipping['add_delivery_cost'];//착불 추가배송비
				$shipping_tot['hop_postpaid_cost']	+= $shipping['hop_delivery_cost'];//착불 희망배송
				$shipping['shipping_pay_type']		= getAlert("sy003"); // "착불";
			}else{
				$shipping['shipping_pay_type'] = getAlert("sy010"); // "무료";
			}

			if(preg_match( '/each_delivery/',$shipping['shipping_method'])){
				$shipping_tot['goods_shipping_cost']		+= $row['goods_shipping_cost'];
				$shipping_tot['add_goods_shipping_cost']	+= $shipping['add_delivery_cost'];
			}

			$shipping_tot['international_cost']				+= $shipping['international_cost'];

			if	($shipping['provider_seq'] > 1){
				$salescost[$shipping['provider_seq']]['salescost']['shippingcost']	+= $shipping['salescost_provider_coupon'];
				$salescost[$shipping['provider_seq']]['salescost']['shippingcost']	+= $shipping['salescost_provider_promotion'];
				$salescost[$shipping['provider_seq']]['salescost']['shippingcoupon']	+= $shipping['salescost_provider_coupon'];
				$salescost[$shipping['provider_seq']]['salescost']['shippingpromotion']	+= $shipping['salescost_provider_promotion'];
				$salescost[$shipping['provider_seq']]['original']['shippingcoupon']	+= $shipping['shipping_coupon_sale'];
				$salescost[$shipping['provider_seq']]['original']['shippingpromotion']	+= $shipping['shipping_promotion_code_sale'];
			}

			$shipping_group_items[$shipping_seq]['shipping'] = $shipping;
		}

		$this->template->assign(array('goods_kind_arr'=> $goods_kind_arr));
		$this->template->assign(array('shipping_group_items'=> $shipping_group_items));

		$able_export_ea = $able_refund_ea;

		# npay 주문건일때,
		if($npay_use && $orders['pg'] == "npay"){
			#구매확정 가능 수량이 있어야 반품 가능(=구매확정 주문은 반품 불가)
			if($reserve_ea == 0 || $orders['orign_order_seq']) $able_return_ea = $able_after_return_ea = 0;
			# npay 주문건일때, 맞교환 재주문건은 결제취소 불가 @2016-02-17 pjm
			if($orders['orign_order_seq']) $able_refund_ea = $able_after_return_ea = 0;
		}

		// 출고,반품,취소 가능수량
		$orders['able_return_ea'] = $able_return_ea;
		$orders['able_after_return_ea'] = $able_after_return_ea;		// 구매확정 후 환불 가능수량
		$orders['able_refund_ea'] = $able_refund_ea;
		$orders['able_export_ea'] = $able_export_ea;

		$orders['is_option_international_shipping'] = $is_option_international_shipping;

		// 회원 정보 가져오기
		if($orders['member_seq']){
			$members = $this->membermodel->get_member_data($orders['member_seq']);
			$members['type'] = $members['business_seq'] ? '기업' : '개인';
			$this->template->assign(array('members'=>$members));
		}

		$this->load->helper('shipping');
		$shipping = use_shipping_method();
		if( $shipping ) foreach($shipping as $key => $data){
			if($data) $shipping_cnt[$key][] = count($data);
		}
		$shipping_policy['policy'] 	= $shipping;

		$data_export		= $this->exportmodel->get_export_for_order($order_seq);
		foreach($data_export as $k=>$row_export){

			//합포장인경우 합포장 합포장 코드로 치환
			if($row_export['is_bundle_export'] == 'Y'){
				$row_export['export_code']		= $row_export['bundle_export_code'];
			}

			// 배송그룹 정보 추출 :: 2016-10-18 lwh
			$row_export_group_arr	= explode('_', $row_export['shipping_group']);
			$row_export['shipping_grp_seq']	= $row_export_group_arr[0];
			$row_export['shipping_set_seq']	= $row_export_group_arr[1];
			$row_export['shipping_set_code'] = ($row_export_group_arr[3]) ? $row_export_group_arr[2].'_'.$row_export_group_arr[3] : $row_export_group_arr[2];

			// 배송출고지 추출 :: 2016-10-10 lwh
			$sql = "SELECT * FROM fm_shipping_grouping WHERE shipping_group_seq = ?";
			$grp_query	= $this->db->query($sql,$row_export['shipping_grp_seq']);
			$grp_res	= $grp_query->row_array();
			$send_add = $this->shippingmodel->get_shipping_address($grp_res['sendding_address_seq'], $grp_res['sendding_scm_type']);
			if($send_add['address_nation'] == 'korea'){
				$send_add['view_address'] = ($send_add['address_type'] == 'street') ? $send_add['address_street'] : $send_add['address'];
				$send_add['view_address'] = '(' . $send_add['address_zipcode'] . ') ' . $send_add['view_address'] . ' ' . $send_add['address_detail'];
			}else{
				$send_add['view_address'] = '(' . $send_add['international_postcode'] . ') ' . $send_add['international_country'] . ' ' . $send_add['international_town_city'] . ' ' . $send_add['international_county'] . ' ' . $send_add['international_address'];
			}
			$row_export['sending_address'] = $send_add;
			// $row_export['refund_address']		= $this->shippingmodel->get_shipping_address($grp_res['refund_address_seq'], $grp_res['refund_scm_type']); // 일단 필요없음

			// 배송방법 예외처리 추가 :: 2016-10-10 lwh
			if(!$row_export['shipping_method']) $row_export['shipping_method'] = $row_export['domestic_shipping_method'];
			if(!$row_export['shipping_set_name']){
				$row_export['shipping_set_name'] = $this->shippingmodel->shipping_method_arr[$row_export['shipping_method']];
			}

			// 매장수령 정보 추출 :: 2016-10-11 lwh
			if($row_export['shipping_set_code'] == 'direct_store'){
				$ship_store_arr = $this->shippingmodel->get_shipping_store($row_export['shipping_set_seq'],'shipping_set_seq');
				$row_export['shipping_store_info'] = $ship_store_arr;
			}

			$data_export_item 		= $this->exportmodel->get_export_item($row_export['export_code']);
			if	($data_export_item[0]['goods_kind'] == 'coupon'){
				$params['export_code']	= $row_export['export_code'];

				$row_export['couponinfo'] = get_goods_coupon_view($row_export['export_code']);

				$params['send_kind']	= 'mail';
				$mail_send_log			= $this->exportmodel->get_coupon_export_send_log($params, 2);
				$params['send_kind']	= 'sms';
				$sms_send_log			= $this->exportmodel->get_coupon_export_send_log($params, 2);
				$coupon_use_log			= $this->exportmodel->get_coupon_use_history($data_export_item[0]['coupon_serial']);
			}

			if($row_export['status'] == '45'){
				$orders['export_ready_ea'] += $row_export['ea'];
			}else{
				$orders['export_complete_ea'] += $row_export['ea'];
			}

			$row_export['price'] = $row_export['price'] + $row_export['sub_price'];
			$tot_export['price'] += $row_export['price'];
			$tot_export['reserve'] += $row_export['reserve'];
			$tot_export['point'] += $row_export['point'];

			$row_export['mail_send_log']		= $mail_send_log;
			$row_export['sms_send_log']			= $sms_send_log;
			$row_export['coupon_use_log']		= $coupon_use_log;
			$row_export['goods_kind']			= $data_export_item[0]['goods_kind'];
			$row_export['coupon_serial']		= $data_export_item[0]['coupon_serial'];
			$row_export['coupon_input']			= $data_export_item[0]['coupon_input'];
			$row_export['coupon_input_type']	= $data_export_item[0]['socialcp_input_type'];
			$row_export['coupon_remain_value']	= $data_export_item[0]['coupon_remain_value'];
			//$row_export['mstatus'] = $this->exportmodel->arr_step[$row_export['status']];

			$export[]	= $row_export;
		}

		//반품정보 가져오기
		$orders['return_list_ea'] = 0;
		$this->load->model('returnmodel');
		$data_return = $this->returnmodel->get_return_for_order($order_seq,"return");
		$r_refund_code = array();
		if( $data_return )foreach($data_return as $k=>$data){

			# npay 승인완료 확인
			if($npay_use && $orders['pg'] == "npay"){
				if($data['npay_flag'] == "ApproveReturnApplication") $data['npay_flag_msg'] = '(반품승인완료)';
			}else{
				$data['npay_flag_msg'] = '';
			}

			if($data['refund_code']){
				$r_refund_code[] = $data['refund_code'];
				$return_field = "return";

				$orders['return_list'][$data['refund_code']] = $data;

			}else{

				$return_field = "exchange";
			}

			$data['mstatus'] = $this->returnmodel->arr_return_status[$data['status']];
			//관리자가 처리했을경우 ID가져오기
			if($data['manager_seq']){
				$rtsql = "select * from fm_manager where manager_seq=?";
				$m_seq=$data['manager_seq'];
				$query = $this->db->query($rtsql,array($m_seq));
				$m_data = $query->row_array();
				$data['manager_id']=$m_data['manager_id'];
				$data['mname']=$m_data['mname'];
			}

			$data_return[$k] = $data;
			$orders['return_list_ea'] += $data['ea'];

			if($data['status']=='complete'){
				$orders[$return_field.'_complete_ea'] += $data['ea'];
			}else if($data['status']=='request'){
				$orders[$return_field.'_request_ea'] += $data['ea'];
			}
		}

		//교환정보 가져오기
		$orders['exchange_list_ea'] = 0;
		$this->load->model('returnmodel');
		$data_exchange = $this->returnmodel->get_return_for_order($order_seq,"exchange");
		if( $data_exchange )foreach($data_exchange as $k=>$data){
			$data['mstatus'] = $this->returnmodel->arr_return_status[$data['status']];
			//관리자가 처리했을경우 ID가져오기
			if($data['manager_seq']){
				$rtsql = "select * from fm_manager where manager_seq=?";
				$m_seq=$data['manager_seq'];
				$query = $this->db->query($rtsql,array($m_seq));
				$m_data = $query->row_array();
				$data['manager_id']=$m_data['manager_id'];
				$data['mname']=$m_data['mname'];
			}

			# npay 승인완료 확인
			if($npay_use && $orders['pg'] == "npay"){
				if($data['npay_flag'] == "ApproveCollectedExchange") $data['npay_flag_msg'] = '(교환수거완료)';
			}else{
				$data['npay_flag_msg'] = '';
			}

			$orders['exchange_list'][] = $data;
			$data_exchange[$k] = $data;
			$orders['exchange_list_ea'] += $data['ea'];
		}

		//환불정보 가져오기
		$orders['cancel_list_ea'] = 0;
		$orders['refund_list_ea'] = 0;
		$this->load->model('refundmodel');
		$data_refund = $this->refundmodel->get_refund_for_order($order_seq);
		if( $data_refund )foreach($data_refund as $k=>$data){

			$data['is_return'] = 0;
			$refund_field = 'refund';
			if( in_array($data['refund_code'],$r_refund_code) || $data['refund_type'] == "return"){
				$data['is_return'] = 1;
				$refund_field = 'return_refund';
			}

			$data['mstatus'] = $this->refundmodel->arr_refund_status[$data['status']];
			if($data['manager_seq']){
				$rtsql = "select * from fm_manager where manager_seq=?";
				//관리자가 처리했을경우 ID가져오기
				$m_seq=$data['manager_seq'];
				$query = $this->db->query($rtsql,array($m_seq));
				$m_data = $query->row_array();
				$data['manager_id']=$m_data['manager_id'];
				$data['mname']=$m_data['mname'];
			}

			if ($data['refund_date']=="0000-00-00") {
				$data['refund_date'] = "";
			}

			# npay 승인완료 확인
			if($npay_use && $orders['pg'] == "npay" && $data['refund_type'] == "cancel_payment"){
				if($data['npay_flag'] == "ApproveCancelApplication") $data['npay_flag_msg'] = '(취소요청승인완료)';
			}else{
				$data['npay_flag_msg'] = '';
			}
			if($npay_use && $orders['pg'] == "npay"){
				$data['refund_price'] -= (int)$data['npay_claim_price'];
			}

			$data_refund[$k] = $data;

			if( $data['refund_type'] == 'cancel_payment' ){
				$orders['cancel_list_ea'] += $data['ea'];
				$orders['cancel_list_count'] += 1;

				$orders['cancel_list'][] = $data;
			}else{
				$orders['refund_list_ea'] += $data['ea'];
				if($data['status']=='complete'){
					$orders[$refund_field.'_complete_ea'] += $data['ea'];
				}else if($data['status']=='request'){
					$orders[$refund_field.'_request_ea'] += $data['ea'];
				}

				$orders['return_list'][$data['refund_code']]['refund_status'] = $data['status'];
			}
		}

		//사은품 지급 사유 정보 가져오기 @2015-09-14 pjm
		array_unique($gifts_item);
		$gifts				= '';
		$gift_target_goods	= array();
		$gift_seq_tmp		= array();
		$gift_goods_seq		= array();
		if($gifts_item){
			$gifts = $this->giftmodel->get_gift_order_log($order_seq,$gifts_item);
			foreach($gifts as $gift_tmp){

				//교환 이벤트 제외. 사은품 지급 대상 상품이 있으면
				if( ( $gift_tmp['target_goods'] || $gift_tmp['goods_rule'] == 'all' )
						&& !in_array($gift_tmp['goods_rule'],array('reserve','point')) ){

							$gift_goods_tmp = $this->ordermodel->get_item_one($gift_tmp['item_seq']);
							$gift_goods[$gift_tmp['gift_seq']][] = $gift_goods_tmp['goods_name'];

							$target_goods = unserialize($gift_tmp['target_goods']);
							if( $target_goods ){
								$where_str = "and item.goods_seq in(".implode(",",$target_goods).")";
							}

							$query = "select
								item.goods_name
								,item.goods_seq
								,item.image
								,opt.option1
								,opt.option2
								,opt.option3
								,opt.option4
								,opt.option5
								,opt.title1
								,opt.title2
								,opt.title3
								,opt.title4
								,opt.title5
							from
								fm_order_item as item
								left join fm_order_item_option as opt on item.order_seq=opt.order_seq and item.item_seq=opt.item_seq
							where
								item.order_seq=?
								".$where_str;
							$query = $this->db->query($query,array($order_seq));
							$gift_tmp['row_cnt'] = count($query->result_array());
							foreach($query->result_array() as $goods_tmp){
								if($goods_tmp){
									if(in_array($gift_tmp['gift_seq'],$gift_seq_tmp) && in_array($goods_tmp['goods_seq'],$gift_goods_seq)){
									}else{
										$gift_target_goods[] = array_merge($gift_tmp,$goods_tmp);
									}
									$gift_goods_seq[] = $goods_tmp['goods_seq'];
								}
							}
							$gift_seq_tmp[] = $gift_tmp['gift_seq'];
				}
			}
			foreach($gift_target_goods as $k=>$gift_tmp){
				$gift_tmp['after_gift_seq'] = $after_gift_seq;
				$after_gift_seq				= $gift_tmp['gift_seq'];
				$gift_tmp['gift_goods']		= $gift_goods[$gift_tmp['gift_seq']];
				$gift_target_goods[$k]		= $gift_tmp;
			}

		}

		$this->load->model('salesmodel');
		//세금계산서 or 현금영수증
		$sc['whereis']	= ' and typereceipt = "'.$orders['typereceipt'].'" and order_seq="'.$order_seq.'" ';
		$sc['select']		= '  cash_no, tstep, seq  ';
		$sales 		= $this->salesmodel->get_data($sc);
		if( $sales ) {
			if($sales['tstep']=='1')
			{
				$cash_msg = "발급신청";
			}
			else if($sales['tstep']=='2')
			{
				$cash_msg = "발급완료";
			} else if($sales['tstep']=='3')
			{
				$cash_msg = "발급취소";
			} else if($sales['tstep']=='4')
			{
				$cash_msg = "발급실패";
			}

			if(!($orders['payment'] =='card' && $orders['payment'] =='cellphone') ) {
				if( $orders['typereceipt'] == 2 && $sales['tstep'] == "2") {
					$cash_receipts_no = ($sales['cash_no'])?$sales['cash_no']:$orders['cash_receipts_no'];
					if(!$cash_receipts_no) {
						$cash_msg = "발급실패";
					}
				}
			}
			$this->template->assign(array('sales_cash_msg'	=> $cash_msg));
		}
		$orders['sitetypetitle']		= sitetype($orders['sitetype'], 'image', '');//판매환경
		$orders['marketplacetitle']		= sitemarketplace($orders['marketplace'], 'image', '');//유입매체
		$orders['referer_naver']		= sitemarketplaceNaver($orders, 'naver');//유입매체:네이버 url 파라미터 제거

		$pg_log = $this->ordermodel->get_pg_log($order_seq);
		if( preg_match('/virtual/',$orders['payment']) && $pg_log[1]){		//가상계좌
			$orders['pg_log'][0] = $pg_log[1];
		}else{
			$orders['pg_log'][0] = $pg_log[0];
		}

		$config_order	= config_load('order');

		if	($orders['payment'] == 'bank' && $orders['bank_account']){
			$bank_tmp			= explode(' ', $orders['bank_account']);
			$bank_name			= str_replace('은행', '', $bank_tmp[0]);
			$orders['bank_name']	= $bank_name;
		}

		if ($orders['deposit_date']=="0000-00-00 00:00:00") $orders['deposit_date'] = "";

		// 수량 / 종 추가 leewh 2014-08-01
		if (!$orders['total_ea']) {
			$orders['total_ea'] = $tot['ea'];
		}

		if (!$orders['total_type']) {
			$orders['total_type'] = count($items);
		}

		// 매장 정보 추출
		$this->load->library("o2o/o2oservicelibrary");
		$o2oStoreList = $this->o2oservicelibrary->get_o2o_config(array(),999);

		// 상품종수
		$tot['cnt'] = count($item_cnt);

		// 입점사 카운터 :: 2015-10-08 lwh
		$tot['provider_cnt'] = count($provider_cnt);

		if ($orders['admin_memo']) $orders['admin_memo'] = str_replace("<br>",chr(10),$orders['admin_memo']);

		// 마일리지 할인으로 변경 :: 2018-06-04 lkh
		// 이벤트, 복수구매 추가 :: 2018-07-13 pjw
		$total_sale_price = $tot['event_sale']
							+$tot['multi_sale']+$tot['coupon_sale']
							+$tot['member_sale']+$tot['fblike_sale'] +$tot['mobile_sale']
							+$tot['promotion_code_sale']+$tot['referer_sale']
							+$orders['enuri']+$shipping_tot['shipping_coupon_sale']
							+$shipping_tot['shipping_promotion_code_sale']
							+$tot['npay_sale_seller'] + $orders['emoney'];

		// 제휴사 할인 초기화 :: 2018-06-04 lkh
		$orders['op_sale_price'] = 0;
		//마켓연동정보
		if ($orders['linkage_id'] == 'connector') {
			//퍼스트몰 마켓 연동
			$this->load->library('Connector');

			//샵링커 추가 2017-09-27 jhs
			$this->load->model('connectormodel');
			$connector	= $this->connector::getInstance();
			$marketList	= $connector->getAllMarkets(true);

			$shopLinkermarketList = array();
			$shopLinkerUseMarketList = $this->connectormodel->getLinkageMarketGroup();
			foreach($shopLinkerUseMarketList as $marketInfo){
				$shopLinkermarketList[$marketInfo['marketCode']] = array('name'=>$marketInfo['marketName'],'productLink'=>'');
			}

			unset($marketList['shoplinker']);

			if(substr($orders["linkage_mall_code"],0,3) == "API"){
				$orders['connector_market_name']		= $shopLinkermarketList[$orders['linkage_mall_code']]['name'];
				// 제휴사 할인 금액 :: 2018-06-04 lkh
				$orders['op_sale_price']				= $this->connectormodel->getOpenmarketDiscountAmount($orders['order_seq'],$orders['linkage_mall_order_id'],"shoplinker");
			}else{
				$orders['connector_market_name']		= $marketList[$orders['linkage_mall_code']]['name'];
				// 제휴사 할인 금액 :: 2018-06-04 lkh
				$orders['op_sale_price']				= $this->connectormodel->getOpenmarketDiscountAmount($orders['order_seq'],$orders['linkage_mall_order_id'],$orders['linkage_mall_code']);
			}
			$mallName[$orders['linkage_mall_code']]	= $orders['connector_market_name'];

			$linkage['linkage_name']				= '오픈마켓';

			$this->template->assign('linkage', $linkage);
			$this->template->assign('linkage_mallnames', $mallName);
		} elseif ($orders['linkage_id'] == 'pos' && $o2oStoreList) {
			foreach($o2oStoreList as $o2oStore){
				if($o2oStore['o2o_store_seq'] == $orders['linkage_mall_code']){
					$linkage['linkage_name']				= 'POS';
					$mallName[$orders['linkage_mall_code']]	= $o2oStore['pos_name'];
					$orders['connector_market_name'] = $o2oStore['pos_name'];
					$this->template->assign('linkage', $linkage);
					$this->template->assign('linkage_mallnames', $mallName);
				}
			}
		} else {
			$orders['linkage_mallname']				= "내사이트";
		}


		if(!$aParamsGet['pagemode']){
			$pagemode = "";
		}else{
			$pagemode = $aParamsGet['pagemode'];
		}

		// 개별 메세지 처리 :: 2016-09-02 lwh
		if($orders['each_msg_yn'] == "Y")	$orders['memo'] = $ship_message;

		// O2O 주문의 주문상태 변경 불가 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_order_view($orders);

		// 파트너 주문인지 확인함
		$partnerPg = [
			'npay',
			'talkbuy',
		];
		$is_partner_order = false;
		if (in_array($orders["pg"], $partnerPg) === true) {
			$is_partner_order = true;
		}

		// 결제확인이나 출고처리 불가 케이스
		if($orders['label'] === 'present' && $orders['recipient_zipcode'] == '') {
			$orders['present_no_receipt_address'] = true;
		}

		//개인정보 마스킹 표시
		$this->load->library('privatemasking');
		$orders 	 = $this->privatemasking->masking($orders, 'order');
		$pay_log 	 = $this->privatemasking->masking($pay_log, 'order_log');
		$process_log = $this->privatemasking->masking($process_log, 'order_log');
		$cancel_log  = $this->privatemasking->masking($cancel_log, 'order_log');

		$this->template->assign(array(
								'pagemode'			=> $pagemode,
								'callPage' 			=> $callPage
							));
		$this->template->assign(array('scm_cfg'				=> $this->scm_cfg));
		$this->template->assign(array('salescost'			=> $salescost));
		$this->template->assign(array('config_order'		=> $config_order));
		$this->template->assign(array('orders'				=> $orders));
		$this->template->assign(array('data_export'			=> $export));
		$this->template->assign(array('tot_export'			=> $tot_export));
		$this->template->assign(array('items'				=> $items));
		$this->template->assign(array('items_tot'			=> $tot));
		$this->template->assign(array('total_sale_price'	=> $total_sale_price));
		$this->template->assign(array('shipping_tot'		=> $shipping_tot));
		$this->template->assign(array('bank'				=> $bank));
		$this->template->assign(array('pay_log'				=> $pay_log));
		$this->template->assign(array('process_log'			=> $process_log));
		$this->template->assign(array('cancel_log'			=> $cancel_log));
		$this->template->assign(array('data_return'			=> $data_return));
		$this->template->assign(array('data_exchange'		=> $data_exchange));
		$this->template->assign(array('data_refund'			=> $data_refund));
		$this->template->assign(array('shipping_policy'		=> $shipping_policy));
		$this->template->assign(array('able_step_action'	=> $this->ordermodel->able_step_action));
		$this->template->assign(array('child_order_seq'		=> $child_order_seq));
		$this->template->assign(array('gift_target_goods'	=> $gift_target_goods));
		$this->template->assign(array('npay_use'			=> $npay_use));
		$this->template->assign(array('talkbuy_use'			=> $talkbuy_use));
		$this->template->assign(array('partner_log'			=> $partner_log));
		$this->template->assign(array('is_partner_order'	=> $is_partner_order));
		$this->template->assign('query_string',$aParamsGet['query_string']);

		//######################## 16.12.15 gcs yjy : 검색조건 유지되도록
		/* #16651 2018-07-10 ycg 관리자 메모 기능 개선 */
		$order_memo =  $this->ordermodel->get_order_memo($order_seq);
		$this->template->assign(array('order_memo'			=> $order_memo));
		/* #16651 2018-07-10 ycg 관리자 메모 기능 개선 */
		if( $pagemode == 'goods_ready' ){ // 출고상세에서 출력용
			$file_path = str_replace('view.html','goods_ready.html',$file_path);
		}else if( $pagemode ){ // 출고상세에서 출력용
			$file_path = str_replace('view.html','view_summary.html',$file_path);
		}else{

			$this->template->define('ORDER_INFO','default/order/view_summary.html');

			// 개인정보 조회 로그
			//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderexcel', 'exportexcel'
			$this->load->model('logPersonalInformation');
			$this->logPersonalInformation->insert('order',$this->managerInfo['manager_seq'],$order_seq);

			$this->admin_menu();
			$this->tempate_modules();
		}

		$this->template->define(array('tpl'	=> $file_path));
		$this->template->print_("tpl");
	}

	//매출증빙내역
	public function sales()
	{
		$auth = $this->authmodel->manager_limit_act('sales_view');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$this->file_path	= $this->template_path();
		$pg = config_load($this->config_system['pgCompany']);
		$pg['pgSet']	= 'no';
		if	( ($pg['mallCode'] && $pg['merchantKey']) || ($pg['mallId'] && $pg['mallPass'])
				|| ($this->config_system['pgCompany']=='kicc' && $pg['mallCode'])
			)
			$pg['pgSet']	= 'ok';
			$this->template->assign('pg',$pg);

			$this->load->model('ordermodel');
			$this->load->model('salesmodel');//세금계산서/현금영수증

			if($this->config_system['webmail_admin_id'] && $this->config_system['webmail_key']){
				$this->template->assign('webmail_admin_id', $this->config_system['webmail_admin_id']);
			}else{
				$this->template->assign('webmail_admin_id', '');
			}

			// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
			if( count($_GET) == 0 ){
				$this->load->model('searchdefaultconfigmodel');
				$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/order/sales');
				if($data_search_default_str['search_info']){
					parse_str($data_search_default_str['search_info'], $data_search_default);
					$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
					$_GET['sdate']			= $search_date['start_date'];
					$_GET['edate']			= $search_date['end_date'];
					foreach($data_search_default as $key => $val){
						$key = str_replace("default_","",$key);
						$_GET[$key]		= $val;
					}
				}
			}

			/**
			 * list setting
			 **/
			$aGetParams				= $this->input->get();
			$sc 					= $aGetParams;
			$sc['isAll']			= 'y';
			$sc['orderby']			= (!empty($aGetParams['orderby'])) ?	$aGetParams['orderby']:'seq desc';
			$sc['page']				= (!empty($aGetParams['page'])) ?		intval($aGetParams['page']):0;
			$sc['perpage']			= (!empty($aGetParams['perpage'])) ?	intval($aGetParams['perpage']):10;
			if(!empty($aGetParams['member_seq'])) $sc['member_seq'] = $aGetParams['member_seq'];
			if(!empty($aGetParams['keyword'])) $sc['keyword'] = $aGetParams['keyword'];
			$sc['date_gb']			= (!empty($aGetParams['date_gb'])) ?	$aGetParams['date_gb']:"all";

			if (!empty($sc['typereceipt'])){
				if(gettype($sc['typereceipt']) == 'string' ) $sc['typereceipt'] = unserialize(urldecode($sc['typereceipt']), ['allowed_classes' => false]);
				foreach ($sc['typereceipt'] as $v) {
					$checked['typereceipt'][$v] = "checked";
				}
			}

			if (!empty($sc['admin_type'])){
				if(gettype($sc['admin_type']) == 'string' ) $sc['admin_type'] = unserialize(urldecode($sc['admin_type']), ['allowed_classes' => false]);
				foreach ($sc['admin_type'] as $v) {
					$checked['admin_type'][$v] = "checked";
				}
			}

			if (!empty($sc['ostep'])){
				if(gettype($sc['ostep']) == 'string' ) $sc['ostep'] = unserialize(urldecode($sc['ostep']), ['allowed_classes' => false]);
				foreach ($sc['ostep'] as $v) {
					$checked['ostep'][$v] = "checked";
				}
			}

			if (!empty($sc['tstep'])){
				if(gettype($sc['tstep']) == 'string' ) $sc['tstep'] = unserialize(urldecode($sc['tstep']), ['allowed_classes' => false]);
				foreach ($sc['tstep'] as $v) {
					$checked['tstep'][$v] = "checked";
				}
			}

			if (!empty($sc['orefund'])){
				if(gettype($sc['orefund']) == 'string' ) $sc['orefund'] = unserialize(urldecode($sc['orefund']), ['allowed_classes' => false]);
				foreach ($sc['orefund'] as $v) {
					$checked['orefund'][$v] = "checked";
				}
			}


			$this->template->assign('checked',$checked);


			$data = $this->salesmodel->sales_list($sc);//게시글목록
			if(gettype($sc['type']) == 'array'){
				$_GET['type'] = urlencode(serialize($sc['type']));
			}
			if(gettype($sc['typereceipt']) == 'array'){
				$_GET['typereceipt'] = urlencode(serialize($sc['typereceipt']));
			}

			###
			$orders = config_load('order');
			$this->template->assign('orders',$orders);
			//print_r($orders);

			$sc['searchcount']	 = $data['count'];

			//개인정보 마스킹 표시
			$this->load->library('privatemasking');
			foreach($data['record'] as $k => $v){
				$data['record'][$k] = $this->privatemasking->masking($data['record'][$k], 'order');
			}
			$private_masking = $this->authmodel->manager_limit_act('private_masking');

			/**
			 * pagin setting
			 **/
			$paginlay =  pagingtag($sc['searchcount']	,$sc['perpage'],'./sales?', getLinkFilter('',array_keys($sc)) );
			if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
			$this->template->assign('pagin',$paginlay);
			$this->template->assign('sc',$sc);
			$this->template->assign('salesloop',$data['record']);
			$this->template->assign('private_masking',$private_masking);
			$this->template->assign('arr_row_number',$arr_row_number);
			$this->template->define(array('tpl'=>$this->template_path()));
			$this->template->print_("tpl");
	}


	public function sales_favorite(){
		$this->db->where('seq', $_GET['seq']);
		$result = $this->db->update('fm_sales', array("favorite_chk"=>$_GET['status']));
		echo $result;
	}


	public function order_pg_info()
	{
		$pg = config_load($this->config_system['pgCompany']);
		$order 			= $this->ordermodel->get_order($_POST['order_seq']);
		if( $this->config_system['pgCompany'] == 'lg' ) {
			// 가상계좌도 별도로 현금영수증 신청하기 때문에 모두 cr 로 요청함 2018-06-14
			$tax_bank	= 'cr';

			$authdata	= md5($pg['mallCode'] . $order['pg_transaction_number'] . $pg['merchantKey']);
			$cst_platform	= 'service';//'service';
			$return = array('result'=>true,'tax_bank'=>$tax_bank,'authdata'=>$authdata,'cst_platform'=>$cst_platform);

			echo json_encode($return);
			exit;
		}else{
			$return = array('result'=>true);
			echo json_encode($return);
			exit;
		}
	}

	public function order_tax_info()
	{
		$this->load->model('salesmodel');
		if	($_POST['seq'])
			$sc['whereis']	= ' and typereceipt = 1 and seq="'.$_POST['seq'].'" ';
			else
				$sc['whereis']	= ' and typereceipt = 1 and order_seq="'.$_POST['order_seq'].'" ';
				$sc['select']		= ' * ';
				$taxitems 		= $this->salesmodel->get_data($sc);

				if($taxitems){
					if($taxitems['tstep']=='1')
					{
						$cash_msg = "발급신청";
					}
					else if($taxitems['tstep']=='2')
					{
						$cash_msg = "발급완료";
					} else if($taxitems['tstep']=='3')
					{
						$cash_msg = "발급취소";
					} else if($taxitems['tstep']=='4')
					{
						$cash_msg = "발급실패";
					} else if($taxitems['tstep']=='5')
					{
						$cash_msg = "전송완료";
					}

					if($taxitems['vat_type'] == '1'){
						$vat_msg	= '과세 세금계산서';
					}else if($taxitems['vat_type'] == '2'){
						$vat_msg	= '비과세 세금계산서';
					}

					//개인정보 마스킹 표시
					$this->load->library('privatemasking');
					$taxitems = $this->privatemasking->masking($taxitems, 'order');

					$vat_type		= $taxitems['vat_type'];
					$cus_no			= $taxitems['cuse'];
					$order_seq		= $taxitems['order_seq'];
					$co_name		= html_entity_decode($taxitems['co_name']);
					$co_ceo			= $taxitems['co_ceo'];
					$co_status		= $taxitems['co_status'];
					$person			= $taxitems['person'];
					$email			= $taxitems['email'];
					$phone			= $taxitems['phone'];
					$co_type		= $taxitems['co_type'];
					$busi_no		= $taxitems['busi_no'];
					$address_type	= $taxitems['address_type'];
					$address		= $taxitems['address'];
					$address_street	= $taxitems['address_street'];
					$address_detail	= $taxitems['address_detail'];
					$price			= $taxitems['price'];
					$supply			= $taxitems['supply'];
					$surtax			= $taxitems['surtax'];
					$post_number	= $taxitems['post_number'];
					$zipcode		= $taxitems['zipcode'];
					$address		= $taxitems['address'];

					$return = array('result'=>true,'seq'=>$taxitems['seq'],'type'=>$taxitems['type'],'co_name'=>$co_name,'co_ceo'=>$co_ceo,'co_status'=>$co_status,'co_type'=>$co_type,'busi_no'=>$busi_no,'tax_tstep'=>$cash_msg,'tstep'=>$taxitems['tstep'],'cus_no'=>$cus_no,'order_seq'=>$order_seq,'vat_type'=>$vat_type,'address_type'=>$address_type,'address'=>$address,'address_street'=>$address_street,'address_detail'=>$address_detail,'zipcode'=>$zipcode,'person'=>$person,'email'=>$email,'phone'=>$phone,'vat_msg'=>$vat_msg,'price'=>$price,'supply'=>$supply,'surtax'=>$surtax,'view_price'=>get_currency_price($price),'view_supply'=>get_currency_price($supply),'view_surtax'=>get_currency_price($surtax),'post_number'=>$post_number);

				}else{
					$return = array('result'=>false);
                }

                //관리자 로그 남기기
				$this->load->library('managerlog');
				$this->managerlog->insertData(array('params' => array('no' => $taxitems['order_seq'])));

				echo json_encode($return);
				exit;
	}

	public function order_cash_info()
	{
		$this->load->model('salesmodel');
		$seq 	= $_POST['seq'];
		$sc['whereis']	= ' and seq="'.$seq.'" ';
		$sc['select']		= ' * ';
		$taxitems 		= $this->salesmodel->get_data($sc);

		if($taxitems){
			if($taxitems['tstep']=='1')
			{
				$cash_msg = "발급신청";
			}
			else if($taxitems['tstep']=='2')
			{
				$cash_msg = "발급완료";
			} else if($taxitems['tstep']=='3')
			{
				$cash_msg = "발급취소";
			} else if($taxitems['tstep']=='4')
			{
				$cash_msg = "발급실패";
			}

			if($taxitems['cuse']=='0'){
				$taxitems['cuse'] = "개인 소득공제용";
			}else{
				$taxitems['cuse'] = "사업자지출 증빙용";
			}

			if($taxitems['order_date'] == "1970-01-01 09:00:00"){
				$taxitems['order_date'] = $taxitems['regdate'];
			}

			$dates	= date('YmdHis', strtotime($taxitems['order_date']));

			// 세금계산서 데이터 누락 예외처리 pjw
			$taxitems['phone'] = ($taxitems['phone']) ? $taxitems['phone'] : $taxitems['creceipt_number'];

			//개인정보 마스킹 표시
			$this->load->library('privatemasking');
			$taxitems = $this->privatemasking->masking($taxitems, 'order');

			$return = array('result'=>true,'seq'=>$taxitems['seq'],'order_seq'=>$taxitems['order_seq'],'type'=>$taxitems['type'],'cuse'=>$taxitems['cuse'],'creceipt_number'=>$taxitems['creceipt_number'],'person'=>$taxitems['person'],'order_date'=>$taxitems['order_date'],'email'=>$taxitems['email'],'phone'=>$taxitems['phone'],'goodsname'=>$taxitems['goodsname'],'cash_msg'=>$cash_msg,'tstep'=>$taxitems['tstep'],'cus_no'=>$cus_no,'odates'=>$dates,'vat_type'=>$taxitems['vat_type'],'price'=>$taxitems['price'],'supply'=>$taxitems['supply'],'surtax'=>$taxitems['surtax'],'view_price'=>get_currency_price($taxitems['price']),'view_supply'=>get_currency_price($taxitems['supply']),'view_surtax'=>get_currency_price($taxitems['surtax']));
		}else{
			$return = array('result'=>false);
		}

		//관리자 로그 남기기
		$this->load->library('managerlog');
		$this->managerlog->insertData(array('params' => array('no' => $taxitems['order_seq'])));

		echo json_encode($return);
		exit;
	}

	public function order_print(){
		$pagemode		= ($_GET['pagemode']) ? $_GET['pagemode'] : '';
		redirect(uri_string()."s?ordarr={$_GET['ordno']}|&pagemode={$pagemode}");
	}

	public function order_prints(){
		$this->tempate_modules();

		$this->load->model('barcodemodel');
		$this->load->model('returnmodel');
		$this->load->model('membermodel');
		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('orderpackagemodel');
		$this->load->library('orderlibrary');

		$pagemode		= ($_GET['pagemode']) ? $_GET['pagemode'] : '';
		$provider_seq	= 1;
		$query			= $this->db->query("select * from fm_setting_print where provider_seq=? ",$provider_seq);
		$prints_data	= $query->row_array();
		$this->template->assign($prints_data);

		$accountAllMiDate = config_load("accountall_setting","accountall_migration_date");
		$accountAllMigrationDate = $accountAllMiDate['accountall_migration_date'];

		/*
		 $order_cfg = config_load('order');
		 $this->template->assign($order_cfg);
		 $export_cfg = config_load('export');
		 $this->template->assign($export_cfg);
		 $this->template->assign('config_system',$this->config_system);
		 */
		$this->template->assign('meta_title','&nbsp;');
		// 개인정보 조회 모델 로드
		$this->load->model('logPersonalInformation');
		$this->load->model('scmmodel');

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');

		$file_path	= $this->template_path();
		$ordarr 	= array_values(array_filter(array_unique(explode("|",$_GET['ordarr']))));

		for($i=0;$i<count($ordarr);$i++){
			$tot = array();
			$order_seq = $ordarr[$i];

			if(!$order_seq) continue;

			// 개인정보 조회 로그
			//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderprint' 'orderexcel', 'exportexcel'
			$this->logPersonalInformation->insert('orderprint',$this->managerInfo['manager_seq'],$order_seq);

			$pay_log 		= $this->ordermodel->get_log($order_seq,'pay');
			$process_log 	= $this->ordermodel->get_log($order_seq,'process');
			$cancel_log 	= $this->ordermodel->get_log($order_seq,'cancel');

			$orders 			= $this->ordermodel->get_order($order_seq);
			$items 				= $this->ordermodel->get_item($order_seq);

			// 네이버 페이 표기 추가 2016-09-07 jhr
			if	($orders['pg'] == 'npay') $orders['mpayment'] = 'naver pay('.$orders['mpayment'].')';

			if ($orders['pg'] == 'naverpayment') {
				$orders['mpayment'] = $this->arr_payment[$orders['pg'].'_'.$orders['payment']];
			}

			$orders['mstep'] 	= $this->arr_step[$orders['step']];

			$arr = config_load('bank');
			if($arr) foreach(config_load('bank') as $k => $v){
				list($tmp) = code_load('bankCode',$v['bank']);
				$v['bank'] = $tmp['value'];
				$bank[] = $v;
			}

			$total_sale = get_cutting_price($orders['enuri']);

			foreach($items as $key=>$item){
				$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
				$suboptions = $this->ordermodel->get_suboption_for_item($item['item_seq']);

				if($options) foreach($options as $k => $data){

					// 개별 배송메시지 추가  :: 2018-03-29 lkh
					if($orders['each_msg_yn'] == 'Y'){
						$goods_info_name = $item['goods_name'] . ' ';
						$ship_message['ship_message'][]	= $data['ship_message'];
						$ship_message['goods_info'][]	= $goods_info_name;
					}

					// 물류관리 창고 정보 추출
					if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == 1 && $data['package_yn']!='y' && $item['goods_kind'] != 'coupon' ){
						unset($sc);
						if	($data['option1'])	$sc['option1']		= $data['option1'];
						if	($data['option2'])	$sc['option2']		= $data['option2'];
						if	($data['option3'])	$sc['option3']		= $data['option3'];
						if	($data['option4'])	$sc['option4']		= $data['option4'];
						if	($data['option5'])	$sc['option5']		= $data['option5'];
						$optionData			= $this->goodsmodel->get_goods_option($item['goods_seq'], $sc);
						if	($optionData[0]['option_seq'] > 0){
							$optionStr		= $item['goods_seq'] . 'option' . $optionData[0]['option_seq'];
							$whinfo			= $this->scmmodel->get_warehouse_stock($this->scm_cfg['export_wh'], 'optioninfo', '', array($optionStr));
							$data['whinfo']	= $whinfo[$optionStr];
						}
						$data['whinfo']['wh_seq']	= $this->scm_cfg['export_wh'];
					}

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

					$stock				= (int) $real_stock - (int) $rstock;
					$data['mstep']		= $this->arr_step[$data['step']];
					$data['real_stock'] = $real_stock;
					$data['stock']		= $stock;

					$data['out_supply_price'] = $data['supply_price']*$data['ea'];
					$data['out_commission_price'] = $data['commission_price']*$data['ea'];
					$data['out_consumer_price'] = $data['consumer_price']*$data['ea'];
					$data['out_price'] = $data['price']*$data['ea'];
					$data['out_org_price'] = $data['org_price']*$data['ea'];
					$data['out_refund_price'] = $data['price']*$data['refund_ea'];

					//promotion sale
					// 이벤트, 복수구매 추가 2018-07-13 pjw
					$data['out_event_sale']   = $data['event_sale'];
					$data['out_multi_sale']   = $data['multi_sale'];
					$data['out_member_sale']  = $data['member_sale']*$data['ea'];
					$data['out_coupon_sale']  = ($data['download_seq'])?$data['coupon_sale']:0;
					$data['out_fblike_sale']  = $data['fblike_sale'];
					$data['out_mobile_sale']  = $data['mobile_sale'];
					$data['out_referer_sale'] = $data['referer_sale'];
					$data['out_promotion_code_sale'] = $data['promotion_code_sale'];

					// total sale
					// 이벤트, 복수구매 추가 2018-07-13 pjw
					$out_sale_price	= $data['out_event_sale'] + $data['out_multi_sale'] + $data['out_member_sale'] + $data['out_coupon_sale'] + $data['out_promotion_code_sale'] + $data['out_fblike_sale'] + $data['out_mobile_sale'] + $data['out_referer_sale'];
					$total_sale += $out_sale_price;

					// 할인가격
					$data['out_sale_price'] = $data['out_price'] - $out_sale_price;
					// 마이그레이션 이전은 event_sale 이 중복으로 적용되어있어서 수정함 2020-03-11
					if($orders['regist_date'] < $accountAllMigrationDate) {
						$data['out_sale_price'] += $data['out_event_sale'];
					}
					$data['sale_price'] = $data['out_sale_price'] / $data['ea'];

					//member use
					$data['out_reserve'] = $data['reserve']*$data['ea'];
					$data['out_point'] = $data['point']*$data['ea'];

					$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];

					if($data['package_yn'] == 'y'){
						$data['packages'] = $this->orderpackagemodel->get_option($data['item_option_seq']);
						$item['package_rowspan'] += count($data['packages']);
						foreach($data['packages'] as $key_packages=>$data_package){
							$stock				= (int) $data_package['stock'];
							$badstock			= (int) $data_package['badstock'];
							$reservation		= (int) $data_package['reservation'.$this->cfg_order['ableStockStep']];
							$ablestock			= $stock - $badstock - $reservation;
							$data['packages'][$key_packages]['ablestock'] = $ablestock;

							$tot['stock']		+= $stock;
							$tot['real_stock']	+= $ablestock;

							// 물류관리 창고 정보 추출
							if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == 1){
								if	($data_package['option_seq'] > 0){
									$optionStr		= $data_package['goods_seq'] . 'option' . $data_package['option_seq'];

									$whinfo			= $this->scmmodel->get_warehouse_stock($this->scm_cfg['export_wh'], 'optioninfo', '', array($optionStr));
									$data['packages'][$key_packages]['whinfo']	= $whinfo[$optionStr];
								}
								$data['packages'][$key_packages]['whinfo']['wh_seq']	= $this->scm_cfg['export_wh'];
							}
						}
					}

					###
					$input = array();
					$sql = "SELECT * FROM fm_order_item_input WHERE order_seq = '{$order_seq}' and item_seq = '{$data[item_seq]}' and item_option_seq='{$data[item_option_seq]}'";
					$query = $this->db->query($sql);
					foreach($query->result_array() as $rows){
						$input[] = $rows;
					}
					$data['inputs'] = $input;

					if($suboptions) foreach($suboptions as $data_sub){
						if( $data_sub['item_option_seq'] == $data['item_option_seq']){
							// 물류관리 창고 정보 추출
							if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == 1 && $data_sub['package_yn'] != 'y' && $item['goods_kind'] != 'coupon' ){
								unset($sc);
								if	($data_sub['title'])		$sc['suboption_title']	= $data_sub['title'];
								if	($data_sub['suboption'])	$sc['suboption']		= $data_sub['suboption'];
								$optionData			= $this->goodsmodel->get_goods_suboption($item['goods_seq'], $sc);
								if	($optionData[0][0]['suboption_seq'] > 0){
									$optionStr		= $item['goods_seq'] . 'suboption' . $optionData[0][0]['suboption_seq'];
									$whinfo			= $this->scmmodel->get_warehouse_stock($this->scm_cfg['export_wh'], 'optioninfo', '', array($optionStr));
									$data_sub['whinfo']	= $whinfo[$optionStr];
								}
								$data_sub['whinfo']['wh_seq']	= $this->scm_cfg['export_wh'];
							}

							###
							$data_sub['out_supply_price']		= $data_sub['supply_price']*$data_sub['ea'];
							$data_sub['out_commission_price']	= $data_sub['commission_price']*$data_sub['ea'];
							$data_sub['out_consumer_price']		= $data_sub['consumer_price']*$data_sub['ea'];
							$data_sub['out_price']				= $data_sub['price']*$data_sub['ea'];

							// 이벤트, 복수구매 추가 2018-07-13 pjw
							$data_sub['out_event_sale']				= $data_sub['event_sale'];
							$data_sub['out_multi_sale']				= $data_sub['multi_sale'];
							$data_sub['out_member_sale']			= $data_sub['member_sale']*$data_sub['ea'];
							$data_sub['out_fblike_sale']			= $data_sub['fblike_sale'];
							$data_sub['out_mobile_sale']			= $data_sub['mobile_sale'];
							$data_sub['out_promotion_code_sale']	= $data_sub['promotion_code_sale'];
							$data_sub['out_referer_sale']			= $data_sub['referer_sale'];
							$data_sub['out_coupon_sale'] 			= 0;
							if($data_sub['download_seq']){
								$data_sub['out_coupon_sale'] 		= $data_sub['download_seq'];
							}

							// 할인 합계
							// 이벤트, 복수구매 추가 2018-07-13 pjw
							$data_sub['out_tot_sale'] = $data_sub['out_event_sale'];
							$data_sub['out_tot_sale'] += $data_sub['out_multi_sale'];
							$data_sub['out_tot_sale'] += $data_sub['out_member_sale'];
							$data_sub['out_tot_sale'] += $data_sub['out_coupon_sale'];
							$data_sub['out_tot_sale'] += $data_sub['out_fblike_sale'];
							$data_sub['out_tot_sale'] += $data_sub['out_mobile_sale'];
							$data_sub['out_tot_sale'] += $data_sub['out_promotion_code_sale'];
							$data_sub['out_tot_sale'] += $data_sub['out_referer_sale'];

							// 할인가격
							$data_sub['out_sale_price'] = $data_sub['out_price'] - $data_sub['out_tot_sale'];
							$data_sub['sale_price'] 	= $data_sub['out_sale_price'] / $data_sub['ea'];

							//member use
							$data_sub['out_reserve'] = $data_sub['reserve']*$data_sub['ea'];
							$data_sub['out_point'] = $data_sub['point']*$data_sub['ea'];

							if($data_sub['package_yn'] == 'y'){
								$data_sub['packages'] = $this->orderpackagemodel->get_suboption($data_sub['item_suboption_seq']);
								$item['package_rowspan_sub'] += count($data_sub['packages']);
								foreach($data_sub['packages'] as $key2 => $data_package){
									$stock				= (int) $data_package['stock'];
									$badstock			= (int) $data_package['badstock'];
									$reservation		= (int) $data_package['reservation'.$this->cfg_order['ableStockStep']];
									$ablestock			= $stock - $badstock - $reservation;

									$data_sub['packages'][$key2]['ablestock'] = $ablestock;

									$tot['stock']		+= $stock;
									$tot['real_stock']	+= $ablestock;

									// 물류관리 창고 정보 추출
									if	($this->scm_cfg['use'] == 'Y' && $item['provider_seq'] == 1){
										if	($data_package['option_seq'] > 0){
											$optionStr		= $data_package['goods_seq'] . 'option' . $data_package['option_seq'];
											$whinfo			= $this->scmmodel->get_warehouse_stock($this->scm_cfg['export_wh'], 'optioninfo', '', array($optionStr));
											$data_sub['packages'][$key2]['whinfo']	= $whinfo[$optionStr];
										}
										$data_sub['packages'][$key2]['whinfo']['wh_seq']	= $this->scm_cfg['export_wh'];
									}
								}
							}

							$data_sub['mstep']   = $this->arr_step[$data_sub['step']];

							$data['suboptions'][] = $data_sub;
						}
					}

					$options[$k] = $data;

					$tot['ea'] += $data['ea'];
					$tot['refund_ea'] += $data['refund_ea'];
					$tot['supply_price'] 	+= $data['out_supply_price'];
					$tot['commission_price'] 	+= $data['out_commission_price'];
					$tot['consumer_price'] 	+= $data['out_consumer_price'];
					$tot['price'] += $data['out_price'];
					$tot['oprice'] += $data['price'];

					//promotion sale
					// 이벤트, 복수구매 추가 2018-07-13 pjw
					$tot['event_sale']  += $data['out_event_sale'];
					$tot['multi_sale']  += $data['out_multi_sale'];
					$tot['member_sale'] += $data['out_member_sale'];
					$tot['coupon_sale'] += $data['out_coupon_sale'];
					$tot['fblike_sale'] += $data['out_fblike_sale'];
					$tot['mobile_sale'] += $data['out_mobile_sale'];
					$tot['referer_sale'] += $data['out_referer_sale'];
					$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];

					//use sale
					$tot['reserve'] += $data['out_reserve'];
					$tot['point'] += $data['out_point'];

					if( $data['package_yn'] != 'y' ){
						$tot['real_stock'] 		+= $real_stock;
						$tot['stock'] 			+= $stock;
					}

					$return_item = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq']);
					$able_return_ea += (int) $data['step75'] + (int) $data['step55'] + (int) $data['step65']  - (int) $return_item['ea'];
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

					$stock								= (int) $real_stock - (int) $rstock;
					$data['real_stock']					= (int) $real_stock;
					$data['stock']						= (int) $stock;

					###
					$data['out_supply_price']			= $data['supply_price']*$data['ea'];
					$data['out_commission_price']		= $data['commission_price']*$data['ea'];
					$data['out_consumer_price']			= $data['consumer_price']*$data['ea'];
					$data['out_price']					= $data['price']*$data['ea'];
					$data['out_org_price']				= $data['org_price']*$data['ea'];
					$data['out_refund_price']			= $data['price']*$data['refund_ea'];

					//promotion sale
					// 이벤트, 복수구매 추가 2018-07-13 pjw
					$data['out_event_sale']				= $data['event_sale'];
					$data['out_multi_sale']				= $data['multi_sale'];
					$data['out_member_sale']			= $data['member_sale']*$data['ea'];
					$data['out_coupon_sale']			= ($data['download_seq'])?$data['coupon_sale']:0;
					$data['out_fblike_sale']			= $data['fblike_sale'];
					$data['out_mobile_sale']			= $data['mobile_sale'];
					$data['out_referer_sale']			= $data['referer_sale'];
					$data['out_promotion_code_sale']	= $data['promotion_code_sale'];

					// total sale
					// 이벤트, 복수구매 추가 2018-07-13 pjw
					$out_sale_price						= $data['out_event_sale'] + $data['out_multi_sale'] + $data['out_member_sale'] + $data['out_coupon_sale']
					+ $data['out_promotion_code_sale'] + $data['out_fblike_sale']
					+ $data['out_mobile_sale'] + $data['out_referer_sale'];
					$total_sale							+= $out_sale_price;

					// 할인가격
					$data['out_sale_price']				= $data['out_price'] - $out_sale_price;
					$data['sale_price']					= $data['out_sale_price'] / $data['ea'];

					//member use
					$data['out_reserve']				= $data['reserve']*$data['ea'];
					$data['out_point']					= $data['point']*$data['ea'];

					$data['mstep']						= $this->arr_step[$data['step']];
					$data['step_complete']				= $data['step45']+$data['step55']+$data['step65']+$data['step75'];
					$suboptions[$k]						= $data;

					$tot['ea']							+= $data['ea'];
					$tot['refund_ea']					+= $data['refund_ea'];
					$tot['supply_price']				+= $data['out_supply_price'];
					$tot['commission_price']			+= $data['out_commission_price'];
					$tot['consumer_price']				+= $data['out_consumer_price'];

					//promotion sale
					// 이벤트, 복수구매 추가 2018-07-13 pjw
					$tot['event_sale']					+= $data['out_event_sale'];
					$tot['multi_sale']					+= $data['out_multi_sale'];
					$tot['member_sale']					+= $data['out_member_sale'];
					$tot['coupon_sale']					+= $data['out_coupon_sale'];
					$tot['fblike_sale']					+= $data['out_fblike_sale'];
					$tot['mobile_sale']					+= $data['out_mobile_sale'];
					$tot['referer_sale']				+= $data['out_referer_sale'];
					$tot['promotion_code_sale']			+= $data['out_promotion_code_sale'];

					//member use
					$tot['reserve']						+= $data['out_reserve'];
					$tot['point']						+= $data['out_point'];

					$tot['oprice'] 						+= $data['price'];
					$tot['price'] 						+= $data['out_price'];

					$return_item		= $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_suboption_seq']);
					$able_return_ea		+= (int) $data['step75'] + (int) $data['step55'] + (int) $data['step65']
					- (int) $return_item['ea'];
				}

				$item['rowspan']			= count($options) + count($suboptions);
				if( $prints_data['order_package'] ){
					$item['rowspan']		+= $item['package_rowspan'];
				}
				if( $prints_data['order_sub_relation'] ){
					$item['rowspan']		+= $item['package_rowspan_sub'];
				}
				$item['suboptions']			= $suboptions;
				$item['options']			= $options;
				$items[$key] 				= $item;

				$tot['goods_shipping_cost']	+= $item['goods_shipping_cost'];
			}

			/* 주문상품을 배송그룹별로 분할 */
			$shipping = $this->ordermodel->get_order_shipping($order_seq);
			$shipping_group_items=array();
			foreach($items as $item){

				//2016.04.20 바코드 설정 pjw
				//##############################
				foreach($item['options'] as $key=>$val){

					foreach($val['packages'] as $key2=>$val2){
						$val2['barcode_image'] = $this->barcodemodel->create_barcode_html('use_code', $val2['goods_code']);
						$val['packages'][$key2] = $val2;
					}


					foreach($val['suboptions'] as $key2=>$val2){
						$val2['barcode_image']		= $this->barcodemodel->create_barcode_html('use_code', $val2['goods_code']);
						foreach($val2['packages'] as $key3=>$val3){
							$val3['barcode_image']		= $this->barcodemodel->create_barcode_html('use_code', $val3['goods_code']);
							$val2['packages'][$key3]	= $val3;
						}
						$val['suboptions'][$key2] = $val2;
					}

					$opt_code  = $val['goods_code'];
					//주문내역서에서는 옵션코드를 안붙이고 출력하기때문에 주석처리
					//$opt_code .= $val['optioncode1'];
					//$opt_code .= $val['optioncode2'];
					//$opt_code .= $val['optioncode3'];
					//$opt_code .= $val['optioncode4'];
					//$opt_code .= $val['optioncode5'];

					$opt_barcode = $this->barcodemodel->create_barcode_html('use_code', $opt_code);
					$val['barcode_image'] = $opt_barcode;
					$item['options'][$key] = $val;
				}
				//##############################


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

			$shipping_provider_seq = ($_GET['shipping_provider_seq'])? $_GET['shipping_provider_seq'] : null;

			$this->load->helper('shipping');
			$arr_shipping_method = get_shipping_method('all');

			// reset
			$shipping_tot['shipping_cost'] = 0;
			$shipping_tot['std_shipping_cost'] = 0;		//선불배송비
			$shipping_tot['add_shipping_cost'] = 0;		//추가배송비
			$shipping_tot['hop_shipping_cost'] = 0;
			$shipping_tot['postpaid_cost'] = 0;			//총배송비
			$shipping_tot['std_postpaid_cost'] = 0;		//선불배송비
			$shipping_tot['add_postpaid_cost'] = 0;		//추가배송비
			$shipping_tot['hop_postpaid_cost'] = 0;		//희망배송
			$shipping_tot['goods_shipping_cost'] = 0;
			$shipping_tot['add_goods_shipping_cost'] = 0;
			$shipping_tot['international_cost'] = 0;

			foreach($shipping_group_items as $shipping_seq=>$row){

				$shipping_res	= $this->ordermodel->get_order_shipping($order_seq,$shipping_provider_seq,$shipping_seq,'limit');
				$tmp_key		= array_keys($shipping_res);
				$shipping		= $shipping_res[$tmp_key[0]];

				/*
				 $query = $this->db->query("select a.*, b.provider_name
				 from fm_order_shipping a
				 inner join fm_provider b on a.provider_seq = b.provider_seq
				 where a.shipping_seq=?",$shipping_seq);
				 $shipping = $query->row_array();
				 */

				// 본사 배송만 출력되도록
				if( $pagemode == 'company_catalog' && $shipping['provider_seq'] != 1){
					unset($shipping_group_items[$shipping_seq]);
					continue;
				}

				$shipping['shipping_method_name'] = $arr_shipping_method[$shipping['shipping_method']];

				$shipping_tot[$order_seq]['shipping_promotion_code_sale']	+= $shipping['shipping_promotion_code_sale'];
				$shipping_tot[$order_seq]['shipping_coupon_sale']			+= $shipping['shipping_coupon_sale'];

				$total_sale += $shipping_tot[$order_seq]['shipping_promotion_code_sale'];
				$total_sale += $shipping_tot[$order_seq]['shipping_coupon_sale'];

				// 배송비 종류별 계산 :: 2016-08-18 lwh
				if($shipping['shipping_type'] == 'prepay'){
					$shipping_tot['shipping_cost']		+= $shipping['shipping_cost'];			//총배송비
					$shipping_tot['std_shipping_cost']	+= $shipping['delivery_cost'];			//선불배송비
					$shipping_tot['add_shipping_cost']	+= $shipping['add_delivery_cost'];		//추가배송비
					$shipping_tot['hop_shipping_cost']	+= $shipping['hop_delivery_cost'];		//희망배송
					$shipping['shipping_pay_type']		= getAlert("sy002"); // "주문시 결제";

					// 착불배송비 계산 (@2016-09-28 pjm)
				}elseif($shipping['shipping_type'] == 'postpaid'){
					$shipping_tot['postpaid_cost']			+= $shipping['delivery_cost'] + $shipping['add_delivery_cost'] + $shipping['hop_delivery_cost'];		//총배송비
					$shipping_tot['std_postpaid_cost']		+= $shipping['delivery_cost'];			//선불배송비
					$shipping_tot['add_postpaid_cost']	+= $shipping['add_delivery_cost'];	 //추가배송비
					$shipping_tot['hop_postpaid_cost']	+= $shipping['hop_delivery_cost'];	 //희망배송
					$shipping['shipping_pay_type']		= getAlert("sy003"); // "착불";
				}else{
					$shipping['shipping_pay_type'] = getAlert("sy010"); // "무료";
				}

				if(preg_match( '/each_delivery/',$shipping['shipping_method'])){
					$shipping_tot['goods_shipping_cost']		+= $row['goods_shipping_cost'];
					$shipping_tot['add_goods_shipping_cost']	+= $shipping['add_delivery_cost'];
				}

				$shipping_tot['international_cost']				+= $shipping['international_cost'];

				/*
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
				 */

				if	($shipping['provider_seq'] > 1){
					$salescost[$shipping['provider_seq']]['salescost']['shippingcost']	+= $shipping['salescost_provider_coupon'];
					$salescost[$shipping['provider_seq']]['salescost']['shippingcost']	+= $shipping['salescost_provider_promotion'];
					$salescost[$shipping['provider_seq']]['salescost']['shippingcoupon']	+= $shipping['salescost_provider_coupon'];
					$salescost[$shipping['provider_seq']]['salescost']['shippingpromotion']	+= $shipping['salescost_provider_promotion'];
					$salescost[$shipping['provider_seq']]['original']['shippingcoupon']	+= $shipping['shipping_coupon_sale'];
					$salescost[$shipping['provider_seq']]['original']['shippingpromotion']	+= $shipping['shipping_promotion_code_sale'];
				}

				$shipping_group_items[$shipping_seq]['shipping'] = $shipping;
			}

			$orders['total_sale'] = $total_sale;

			// 회원 정보 가져오기
			if($orders['member_seq']){
				$members = $this->membermodel->get_member_data($orders['member_seq']);
				$this->template->assign(array('members'=>$members));
			}

			// 배송방법
			$orders['mshipping'] = $this->ordermodel->get_delivery_method($orders);

			$this->load->helper('shipping');
			$shipping = use_shipping_method();
			if( $shipping ) foreach($shipping as $key => $data){
				if($data) $shipping_cnt[$key] = count($data);
			}
			$shipping_policy['policy'] 	= $shipping;

			$data_export = $this->exportmodel->get_export_for_order($order_seq);

			//반품정보 가져오기
			$this->load->model('returnmodel');
			$data_return = $this->returnmodel->get_return_for_order($order_seq,"return");
			if( $data_return )foreach($data_return as $k=>$data){
				$data['mstatus'] = $this->returnmodel->arr_return_status[$data['status']];
				$data_return[$k] = $data;
			}

			//교환정보 가져오기
			$data['exchange_list_ea'] = 0;
			$data_exchange = $this->returnmodel->get_return_for_order($order_seq,"exchange");
			if($data_exchange) foreach($data_exchange as $data){
				$data['mstatus'] = $this->returnmodel->arr_return_status[$data['status']];
				$data_exchange[$k] = $data;
			}

			//환불정보 가져오기
			$this->load->model('refundmodel');
			$data_refund = $this->refundmodel->get_refund_for_order($order_seq);
			if( $data_refund )foreach($data_refund as $k=>$data){
				$data['mstatus'] = $this->refundmodel->arr_refund_status[$data['status']];
				$data_refund[$k] = $data;
			}

			$this->load->model('salesmodel');
			//세금계산서 or 현금영수증
			$sc['whereis']	= ' and typereceipt = "'.$orders['typereceipt'].'" and order_seq="'.$order_seq.'" ';
			$sc['select']		= '  cash_no, tstep, seq  ';
			$sales 		= $this->salesmodel->get_data($sc);
			if( $sales ) {
				if($sales['tstep']=='1')
				{
					$cash_msg = "발급신청";
				}
				else if($sales['tstep']=='2')
				{
					$cash_msg = "발급완료";
				} else if($sales['tstep']=='3')
				{
					$cash_msg = "발급취소";
				} else if($sales['tstep']=='4')
				{
					$cash_msg = "발급실패";
				}

				if(!($orders['payment'] =='card' && $orders['payment'] =='cellphone') ) {
					if( $orders['typereceipt'] == 2 ) {
						$cash_receipts_no = ($sales['cash_no'])?$sales['cash_no']:$orders['cash_receipts_no'];
						if(!$cash_receipts_no) {
							$cash_msg = "발급실패";
						}
					}
				}
				//$this->template->assign(array('sales_cash_msg'	=> $cash_msg));
				$data_arr['sales_cash_msg']  = $cash_msg;
			}

			// 2016.04.20 바코드 노출 기능 추가 pjw
			$order_barcode = $this->barcodemodel->create_barcode_html('use_code_order', $orders['order_seq'], 30);
			$orders['order_barcode'] = $order_barcode;

			// 개별 배송메세지 추가 :: 2018-03-29 lkh
			if($orders['each_msg_yn'] == "Y")	$orders['memo'] = $ship_message;

			// 외부주문 linkage_mallname_text 정의 2020-05-27
			$this->orderlibrary->get_order_market_name($orders);

			//개인정보 마스킹 표시
			$this->load->library('privatemasking');
			$orders 	 = $this->privatemasking->masking($orders, 'order');
			$pay_log 	 = $this->privatemasking->masking($pay_log, 'order_log');
			$process_log = $this->privatemasking->masking($process_log, 'order_log');
			$cancel_log  = $this->privatemasking->masking($cancel_log, 'order_log');

			$data_arr['order']			= $orders;
			$data_arr['data_export']	= $data_export;
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


			$data_arr['shipping_tot']= $shipping_tot;
			$data_arr['able_step_action']= $this->ordermodel->able_step_action;
			$loop[] = $data_arr;
		}

		$this->template->assign(array('scm_cfg' => $this->scm_cfg));
		$this->template->assign(array('loop' => $loop));
		$this->template->define(array('tpl'	=> $file_path));
		$this->template->print_("tpl");
	}

	public function download_list(){
		$this->admin_menu();
		$this->tempate_modules();

		$this->db->order_by("seq","desc");
		$this->db->where(array('gb'=>'ORDER',"provider_seq"=>'1'));
		$query = $this->db->get("fm_exceldownload");
		foreach ($query->result_array() as $row){
			$row['count'] = count(explode("|",$row['item']));
			$loop[] = $row;
		}
		$this->template->assign('loop',$loop);

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function download_write(){
		$this->admin_menu();
		$this->tempate_modules();
		$this->load->helper('shipping');
		$this->load->model('excelmodel');

		if(isset($_GET['seq'])){
			$seq			= (int) $_GET['seq'];
			$this->excelmodel->get_exceldownload($seq);
			$data = $this->excelmodel->data_exceldownload;
			$this->template->assign('items', $data['item']);
		}
		if(!$data['criteria']) {
			$data['criteria'] = 'ORDER';
		}

		if($this->excelmodel->only_real == 'REAL'){
			$this->template->assign('only_real', true);
		}
		$this->template->assign($data);

		## 주문엑셀 형식에 따른 셀항목
		foreach(array('ORDER' ,'ITEM') as $criteria){
			unset($itemList);
			$this->excelmodel->setting_type = $criteria;
			$this->excelmodel->set_cell();
			foreach($this->excelmodel->all_cells as $data){
				if( $data[1] == 'option' || (!preg_match('/^option/', $data[1]) && !preg_match('/^addoption/', $data[1])) ){
					$itemList[$data[1]] = $data[0];
				}
			}
			$this->template->assign(strtolower($criteria).'_list_arr',$itemList);
		}

		## 주문별필수
		foreach($this->excelmodel->require_cells['ORDER'] as $data){
			$orderrequireds[] = $data[1];
		}
		$this->template->assign('orderrequireds',$orderrequireds);

		## 상품별필수
		foreach($this->excelmodel->require_cells['ITEM'] as $data){
			$itemrequireds[] = $data[1];
		}
		$this->template->assign('itemrequireds',$itemrequireds);

		$file_path	= $this->template_path();
		$excel_export_path = dirname($this->template_path()).'/_excel_delivery_code.html';
		$this->template->define(array('excel_delivery_code'=>$excel_export_path));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	//결제취소 -> 환불
	public function order_refund(){
		$this->load->model('ordermodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');

		$npay_use = npay_useck();

		if(!$this->arr_step)	$this->arr_step = config_load('step');
		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		if(!$this->cfg_order)	$this->cfg_order = config_load('order');

		$pg = config_load($this->config_system['pgCompany']);

		$order_seq	= $_POST['order_seq'];
		$able_steps	= $this->ordermodel->able_step_action['cancel_payment'];

		$orders		= $this->ordermodel->get_order($order_seq);
		$items 		= $this->ordermodel->get_item($order_seq);
		$tot		= array();
		$order_total_ea = $this->ordermodel->get_order_total_ea($order_seq);

		foreach($items as $key=>$item){
			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);

			if($options) foreach($options as $k=>$option){
				//$this->db->select("sum(ea) as ea");
				$options[$k]['mstep']	= $this->arr_step[$options[$k]['step']];
				# (맞교환)재주문 일때
				if($option['top_item_option_seq']) $item_option_seq = $option['top_item_option_seq'];
				else $item_option_seq = $option['item_option_seq'];
				if($option['top_item_seq']) $item_seq = $option['top_item_seq'];
				else $item_seq = $option['item_seq'];

				$rf_ea = $this->refundmodel->get_refund_option_ea($item_seq,$item_option_seq);
				//반품갯수는 환불갯수에서 차감(출고된 건수만큼 반품)@2017-01-23
				$return_item = $this->returnmodel->get_return_item_ea($item_seq,$item_option_seq);
				$rt_ea = $return_item['ea'];
				if($rt_ea>0) $rf_ea = $rf_ea- $rt_ea;

				$step_complete = $this->ordermodel->get_option_export_complete($order_seq,$option['shipping_provider_seq'],$item['item_seq'],$option['item_option_seq']);
				$options[$k]['able_refund_ea'] = $option['ea'] - $rf_ea - $step_complete;

				$tot['ea'] += $option['ea'];
				$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $option['item_option_seq'], array("step in ('".implode("','",$able_steps)."')","refund_ea<ea"));

				if($suboptions) foreach($suboptions as $k_sub=>$suboption){
					//$this->db->select("sum(ea) as ea");
					$suboptions[$k_sub]['mstep']	= $this->arr_step[$suboptions[$k_sub]['step']];

					$rf_ea = $this->refundmodel->get_refund_suboption_ea($item['item_seq'],$suboption['item_suboption_seq']);
					//반품갯수는 환불갯수에서 차감(출고된 건수만큼 반품)@2017-01-23
					$return_item = $this->returnmodel->get_return_subitem_ea($item['item_seq'],$suboption['item_suboption_seq']);
					$rt_ea = $return_item['ea'];
					if($rt_ea>0) $rf_ea = $rf_ea- $rt_ea;

					$step_complete = $this->ordermodel->get_suboption_export_complete($order_seq,$option['shipping_provider_seq'],$item['item_seq'],$suboption['item_suboption_seq']);
					$suboptions[$k_sub]['able_refund_ea'] = $suboption['ea'] - $rf_ea - $step_complete;

					$tot['ea'] += $suboption['ea'];
				}
				if($suboptions) $options[$k]['suboptions'] = $suboptions;

				$options[$k]['inputs']	= $this->ordermodel->get_input_for_option($options[$k]['item_seq'], $options[$k]['item_option_seq']);
			}

			$items[$key]['options'] = $options;
		}

		$orders['kspay_authty']	= '1010';	// KSPAY - 신용카드
		if		($orders['payment'] == 'account')
			$orders['kspay_authty']	= '2010';	// KSPAY - 계좌이체
		elseif	($orders['payment'] == 'cellphone')
			$orders['kspay_authty']	= 'M110';	// KSPAY - 휴대폰

		$show_refund_method = 'Y';
		if($orders['payment'] == 'card' || $orders['payment'] == 'cellphone'){
			$show_refund_method = 'N';
		}

		// 간편결제 환불방법 미노출 처리
		if (is_order_simple_payment($orders)) {
			$show_refund_method = 'N';
		}

		// @todo 톡구매클레임 연동개발 되면 삭제
		if ( in_array($orders['pg'], ['talkbuy']) ) {
			$show_refund_method = 'N';
		}

		$orders['show_refund_method'] = $show_refund_method;

		//npay 사용여부 확인, 취소사유 코드 불러오기
		if($npay_use){
			$this->load->library('naverpaylib');
			$npay_reason_code	= $this->naverpaylib->get_npay_code("claim_cancel");
		}

		$this->template->assign(array('pg'	=> $pg));
		$this->template->assign(array('orders'	=> $orders));
		$this->template->assign(array('items'	=> $items));
		$this->template->assign(array('items_tot'	=> $tot));
		$this->template->assign(array('order_total_ea'	=> $order_total_ea));
		$this->template->assign(array('npay_use'=>$npay_use,'npay_reason_code'	=> $npay_reason_code));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	// 결제취소 기타
	public function order_refund_etc(){
		$this->load->model('ordermodel');
		$this->load->model('refundmodel');

		if(!$this->arr_step)	$this->arr_step = config_load('step');
		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		if(!$this->cfg_order)	$this->cfg_order = config_load('order');

		$pg = config_load($this->config_system['pgCompany']);

		$order_seq	= $_POST['order_seq'];
		$able_steps	= $this->ordermodel->able_step_action['cancel_payment'];

		$data_order		= $this->ordermodel->get_order($order_seq);
		$data_order['refund_price']	= (int) $this->refundmodel->get_refund_price_for_order($order_seq);

		$this->template->assign(array('pg'	=> $pg));
		$this->template->assign(array('data_order'	=> $data_order));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	//반품 or 맞교환 -> 환불
	public function order_return(){
		$this->load->model('ordermodel');
		$this->load->model('returnmodel');
		$this->load->model('refundmodel');
		$this->load->model('providermodel');
		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('shippingmodel');

		$aPostParam = $this->input->post();
		$aGetParam = $this->input->get();

		$order_seq	= $aPostParam['order_seq'];
		$type		= $aPostParam['type'];
		$mode		= $aPostParam['mode'] ? $aPostParam['mode'] : $aGetParam['mode'];
		if(!$this->arr_step)	$this->arr_step = config_load('step');
		$able_steps	= $this->ordermodel->able_step_action['return_list'];

		$orders		= $this->ordermodel->get_order($order_seq);
		$items 		= $this->ordermodel->get_item($order_seq);

		if( strstr($orders['recipient_zipcode'],'-') ) {
			$orders['recipient_new_zipcode'] 	= str_replace("-","",$orders['recipient_zipcode']);
		}else{
			$orders['recipient_new_zipcode'] 	= $orders['recipient_zipcode'];
		}
		if($orders['order_phone']) $orders['order_phone'] = explode('-',$orders['order_phone']);
		if($orders['order_cellphone']) $orders['order_cellphone'] = explode('-',$orders['order_cellphone']);

		$reasonLoop = array();
		$npay_use	= npay_useck();
		//npay 사용여부 확인, 반품사유 코드 불러오기
		if($npay_use && $orders['npay_order_id']){
			$this->load->library('naverpaylib');
			$reasonLoop = $this->naverpaylib->get_npay_return_reason();
		}else{
			// 사유코드
			$reasons = code_load('return_reason');

			if( $_GET['mode'] == 'return_coupon' ) {
				$qry = "select * from fm_return_reason where return_type='coupon' order by idx asc";
				$query = $this->db->query($qry);
				$reasonLoop = $query -> result_array();
			}else{
				$qry = "select * from fm_return_reason where return_type!='coupon' order by idx asc";
				$query = $this->db->query($qry);
				$reasonLoop = $query -> result_array();
			}
			$npay_use = false;
		}
		$this->template->assign(array('reasonLoop'=> $reasonLoop, 'reasons' => $reasons));

		// 	계좌설정 정보
		$bank = $payment = $escrow = "";
		$arr = config_load('bank');
		if($arr) foreach(config_load('bank') as $k => $v){
			list($tmp) = code_load('bankCode',$v['bank']);
			$v['bank'] = $tmp['value'];
			$bank[] = $v;
			if( $v['accountUse'] == 'y' ) $payment['bank'] = true;
		}
		$this->template->assign(array('bank'		=> $bank));

		// 반품배송비 입금 계좌설정 정보
		$aReturnBanks	= array();
		$aCfgBanks		= config_load('bank_return');
		if( $aCfgBanks ) foreach($aCfgBanks	as $sKeyCfgBank	=> $sValCfgBank){
			if($sValCfgBank['accountUseReturn'] == 'y'){
				list($sValCfgBank['bank'])	= code_load('bankCode', $sValCfgBank['bankReturn']);
				$aReturnBanks[]				= $sValCfgBank;
			}
		}
		$this->template->assign(array('bankReturn'	=> $aReturnBanks));

		// 출력데이터
		$loop = array();

		$cfg_order = config_load('order');

		// 출고정보
		$exports = $this->exportmodel->get_export_for_order($order_seq);

		//주문상품의 실제 1건당 금액계산 @2014-11-27
		foreach($items as $key=>$item){
			if ( $item['goods_kind'] != 'coupon' ) continue;
			$reOption	= array();
			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
			$rowspan	= 0;
			if($options) foreach($options as $k => $data){
				// 매입
				$data['out_supply_price'] = $data['supply_price']*$data['ea'];
				// 정산
				$data['out_commission_price'] = $data['commission_price']*$data['ea'];

				// 상품금액
				$data['out_price'] = $data['price']*$data['ea'];

				// 할인
				$data['out_event_sale'] = $data['event_sale'];
				$data['out_multi_sale'] = $data['multi_sale'];
				$data['out_member_sale'] = $data['member_sale']*$data['ea'];
				$data['out_coupon_sale'] = ($data['download_seq'])?$data['coupon_sale']:0;
				$data['out_fblike_sale'] = $data['fblike_sale'];
				$data['out_mobile_sale'] = $data['mobile_sale'];
				$data['out_promotion_code_sale'] = $data['promotion_code_sale'];
				$data['out_referer_sale'] = $data['referer_sale'];

				// 할인 합계
				$data['out_tot_sale'] = $data['out_event_sale'];
				$data['out_tot_sale'] += $data['out_multi_sale'];
				$data['out_tot_sale'] += $data['out_member_sale'];
				$data['out_tot_sale'] += $data['out_coupon_sale'];
				$data['out_tot_sale'] += $data['out_fblike_sale'];
				$data['out_tot_sale'] += $data['out_mobile_sale'];
				$data['out_tot_sale'] += $data['out_promotion_code_sale'];
				$data['out_tot_sale'] += $data['out_referer_sale'];

				// 할인가격
				$data['out_sale_price'] = $data['out_price'] - $data['out_tot_sale'];
				$data['sale_price'] = $data['out_sale_price'] / $data['ea'];
				$order_one_option_sale_price[$data['item_option_seq']] = $data['sale_price'];

				// 예상적립
				$data['out_reserve'] = $data['reserve']*$data['ea'];
				$data['out_point'] = $data['point']*$data['ea'];

				###
				unset($data['inputs']);
				$data['inputs'] = $this->ordermodel->get_input_for_option($data['item_seq'],$data['item_option_seq']);

				$options[$k] = $data;

				$tot['ea']					+= $data['ea'];
				$tot['ready_ea']			+= $data['ready_ea'];
				$tot['step_complete']		+= $data['step_complete'];
				$tot['step25']				+= $data['step25'];
				$tot['step85']				+= $data['step85'];
				$tot['step45']				+= $data['step45'];
				$tot['step55']				+= $data['step55'];
				$tot['step65']				+= $data['step65'];
				$tot['step75']				+= $data['step75'];
				$tot['supply_price']		+= $data['out_supply_price'];
				$tot['commission_price']	+= $data['out_commission_price'];
				$tot['consumer_price']		+= $data['out_consumer_price'];
				$tot['price']				+= $data['out_price'];
				$tot['out_sale_price']		+= $data['out_sale_price'];


				$tot['member_sale']			+= $data['out_member_sale'];
				$tot['coupon_sale']			+= $data['out_coupon_sale'];
				$tot['fblike_sale']			+= $data['out_fblike_sale'];
				$tot['mobile_sale']			+= $data['out_mobile_sale'];
				$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];
				$tot['referer_sale']		+= $data['out_referer_sale'];

				$tot['coupon_provider']		+= $data['coupon_provider'];
				$tot['promotion_provider']	+= $data['promotion_provider'];
				$tot['referer_provider']	+= $data['referer_provider'];

				$tot['reserve']				+= $data['out_reserve'];
				$tot['point']				+= $data['out_point'];
				$tot['real_stock']			+= $real_stock;
				$tot['stock']				+= $stock;

				$return_item = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq']);
				$able_return_ea += (int) $data['step75'] + (int) $data['step55'] + (int) $data['step65']  - (int) $return_item['ea'];

				$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);
				if($suboptions) foreach($suboptions as $k => $subdata){
					###
					$subdata['out_supply_price']		= $subdata['supply_price']*$subdata['ea'];
					$subdata['out_commission_price']	= $subdata['commission_price']*$subdata['ea'];
					$subdata['out_consumer_price']		= $subdata['consumer_price']*$subdata['ea'];
					$subdata['out_price']				= $subdata['price']*$subdata['ea'];

					// 할인
					$subdata['out_event_sale'] = $subdata['event_sale'];
					$subdata['out_multi_sale'] = $subdata['multi_sale'];
					$subdata['out_member_sale'] = $subdata['member_sale']*$data['ea'];
					$subdata['out_coupon_sale'] = ($subdata['download_seq'])?$subdata['coupon_sale']:0;
					$subdata['out_fblike_sale'] = $subdata['fblike_sale'];
					$subdata['out_mobile_sale'] = $subdata['mobile_sale'];
					$subdata['out_promotion_code_sale'] = $subdata['promotion_code_sale'];
					$subdata['out_referer_sale'] = $subdata['referer_sale'];

					// 할인 합계
					$subdata['out_tot_sale'] = $subdata['out_event_sale'];
					$subdata['out_tot_sale'] += $subdata['out_multi_sale'];
					$subdata['out_tot_sale'] += $subdata['out_member_sale'];
					$subdata['out_tot_sale'] += $subdata['out_coupon_sale'];
					$subdata['out_tot_sale'] += $subdata['out_fblike_sale'];
					$subdata['out_tot_sale'] += $subdata['out_mobile_sale'];
					$subdata['out_tot_sale'] += $subdata['out_promotion_code_sale'];
					$subdata['out_tot_sale'] += $subdata['out_referer_sale'];

					// 할인가격
					$subdata['out_sale_price'] = $subdata['out_price'] - $subdata['out_tot_sale'];
					$subdata['sale_price'] = $subdata['out_sale_price'] / $subdata['ea'];
					$order_one_option_sale_price[$data['item_option_seq']] += $subdata['sale_price'];

					$subdata['out_reserve']				= $subdata['reserve']*$subdata['ea'];
					$subdata['out_point']				= $subdata['point']*$subdata['ea'];

					$tot['out_sale_price']		+= $subdata['out_sale_price'];
				}
			}
		}

		$gift_cnt = 0;
		foreach( $exports as $k => $data_export ){

			$data_export['item'] =  $this->exportmodel->get_export_item($data_export['export_code']);

			foreach($data_export['item'] as $i=>$data){
				if ( ($data['goods_kind'] != 'coupon' && $_GET['mode'] == 'return_coupon') || ($data['goods_kind'] == 'coupon' && $_GET['mode'] != 'return_coupon')  ) continue;//티켓상품 반품/맞교환 제외@2013-11-12

				$data['export_code']		= $data_export['export_code'];
				if	($data['is_bundle_export'] == 'Y') {
					$data['export_code'] = $data_export['bundle_export_code'];
				}
				$data['reasons'] = $reasons;
				$data['reasonLoop'] = $reasonLoop;
				$data['mstep'] = $this->arr_step[$data['step']];

				//티켓상품의 1개의 실제 결제금액 @2014-11-27
				$coupon_real_total_price = $order_one_option_sale_price[$data['option_seq']];

				$it_s = $data['item_seq'];
				$it_ops = $data['option_seq'];

				if($data['opt_type']=='opt'){
					$return_item = $this->returnmodel->get_return_item_ea($it_s,$it_ops,$data_export['export_code']);
				}
				if($data['opt_type']=='sub'){
					$return_item = $this->returnmodel->get_return_subitem_ea($it_s,$it_ops,$data_export['export_code']);
				}

				## 주문의 전체 출고수량이 아닌 해당 출고수량에 대해 체크하도록 수정 by hed
				$it_subops = "";
				if( $data['opt_type'] == 'sub') $it_subops = $data['option_seq'];
				$exp_data			= $this->exportmodel->get_export_item_ea($data_export['export_code'],$data['item_option_seq'],$it_subops);
				$data['rt_ea']		= (int) $exp_data['ea'] - (int) $return_item['ea'];

				if($data['goods_type'] == 'gift' && $data['rt_ea'] > 0) $gift_cnt++;	//사은품 반품가능 수량 @2015-09-15 pjm

				//티켓상품의 취소(환불) 가능여부
				if ( $data['goods_kind'] == 'coupon' ) {
					$coupontotal++;//티켓상품@2013-11-06
					$data['rt_ea'] = 1;//출고당 기본 1개 @2016-07-20 ysm

					$data['couponinfo'] = get_goods_coupon_view($data_export['export_code']);
					$orders['coupon_use_return'] = $data['couponinfo']['coupon_use_return'];
					$orders['order_socialcp_cancel_return_title'] = $data['couponinfo']['order_socialcp_cancel_return_title'];
					$data['socialcp_return_disabled'] = false;
					$coupon_refund_emoney = $coupon_remain_price = $coupon_deduction_price = 0;
					$coupon_remain_real_percent = $coupon_remain_real_price = $coupon_remain_price = $coupon_deduction_price = 0;

					if( $return_item['ea'] ) {//환불접수된 경우
						$data['rt_ea'] = 0;
						$data['coupon_refund_type']		= 'price';
						$data['socialcp_return_disabled'] = true;
					}else{
						if( date("Ymd")>substr(str_replace("-","",$data['social_end_date']),0,8) ) {//유효기간 종료 후 마일리지환불 신청가능여부
							//$orders['socialcp_valid_coupons'] = true;
							/**
							 //관리자 : 미사용티켓상품 환불대상 불가 허용
							 if( $data['socialcp_use_return'] == 1) {//미사용티켓상품 환불대상
							 }else{//불가
							 }
							 **/
							if( order_socialcp_cancel_return($data['socialcp_use_return'], $data['coupon_value'], $data['coupon_remain_value'], $data['social_start_date'], $data['social_end_date'] , $data['socialcp_use_emoney_day'] ) === true ) {//미사용티켓상품여부 잔여값어치합계
								if ( $data['socialcp_input_type'] == 'price' ) {//금액
									$coupon_remain_price_tmp			= (int) $data['coupon_remain_value'];
									$coupon_deduction_price_tmp	= (int) $data['coupon_value'];
								}else{//횟수
									$coupon_remain_price_tmp			= (int) (100 * ($data['coupon_input_one'] * $data['coupon_remain_value']) / 100);
									$coupon_deduction_price_tmp	= (int) ($data['coupon_input_one'] * $data['coupon_value']);
								}
								$coupon_remain_real_percent = 100 * ($coupon_remain_price_tmp / $coupon_deduction_price_tmp);//잔여값어치율

								//실제결제금액
								$coupon_remain_real_price			= (int) ($coupon_remain_real_percent * ($coupon_real_total_price) / 100);

								$coupon_remain_price			= (int) ($data['socialcp_use_emoney_percent'] * ($coupon_remain_real_price) / 100);
								$coupon_deduction_price	= (int) ($coupon_real_total_price) - $coupon_remain_price;
								//$cancel_total_price  += $coupon_remain_price;//취소총금액
							}else{
								$data['socialcp_return_disabled'] = true;
							}
						}else{//유효기간 이전
							if( $data['coupon_remain_value'] >0) {//잔여값어치가 남아있으면
								/**
								 if( $data['coupon_value'] != $data['coupon_remain_value'] && $data['socialcp_cancel_use_refund'] == '1' ) {
								 //부분 사용한 티켓상품은 취소(환불) 불가 @2014-10-07
								 $data['rt_ea'] = 0;
								 $data['coupon_refund_type']		= 'price';
								 $data['socialcp_return_disabled'] = true;
								 }else{
								 }
								 ***/
								list($data['socialcp_refund_use'], $data['socialcp_refund_cancel_percent']) = order_socialcp_cancel_refund(
										$order_seq,
										$data['item_seq'],
										$orders['deposit_date'],
										$data['social_start_date'],
										$data['social_end_date'],
										$data['socialcp_cancel_payoption'],
										$data['socialcp_cancel_payoption_percent']
										);//취소(환불) 가능여부

										if( $data['coupon_value'] == $data['coupon_remain_value'] ) {//전체체크 미사용
											//실제결제금액
											$coupon_remain_price			= (int) ($data['socialcp_refund_cancel_percent'] * $coupon_real_total_price / 100);
											$coupon_deduction_price	= (int) ($coupon_real_total_price) - $coupon_remain_price;
											$coupon_remain_real_percent = "100";
											$coupon_remain_real_price = $coupon_real_total_price;
											$data['coupon_refund_type']	= 'price';
											$cancel_total_price  += $coupon_remain_price;//취소총금액
										}else{//사용
											$data['coupon_refund_type']		= 'price';
											$data['socialcp_return_disabled'] = true;

											if ( $data['socialcp_input_type'] == 'price' ) {//금액
												$coupon_remain_price_tmp			= (int) $data['coupon_remain_value'];
												$coupon_deduction_price_tmp	= (int) $data['coupon_value'];
											}else{//횟수
												$coupon_remain_price_tmp			= (int) (100 * ($data['coupon_input_one'] * $data['coupon_remain_value']) / 100);
												$coupon_deduction_price_tmp	= (int) ($data['coupon_input_one'] * $data['coupon_value']);
											}
											$coupon_remain_real_percent = 100 * ($coupon_remain_price_tmp / $coupon_deduction_price_tmp);//잔여값어치율

											//실제결제금액
											$coupon_remain_price			= (int) ($data['socialcp_refund_cancel_percent'] * ($coupon_remain_price_tmp) / 100);
											$coupon_deduction_price	= (int) ($coupon_real_total_price) - $coupon_remain_price;
											//$cancel_total_price  += $coupon_remain_price;//취소총금액
										}
							}else{
								$data['rt_ea'] = 0;
								$data['coupon_refund_type']		= 'price';
								$data['socialcp_return_disabled'] = true;
							}

						}

						$cancel_memo = socialcp_cancel_memo($data, $coupon_remain_real_percent, $coupon_real_total_price, $coupon_remain_real_price, $coupon_remain_price, $coupon_deduction_price);
						//echo "유효기간전";
						//debug_var($data['socialcp_return_disabled']);
						//debug_var($data['socialcp_refund_use']);
						//debug_var($data['socialcp_refund_cancel_percent']);
						//debug_var($coupon_refund_emoney);
						//debug_var("coupon_remain_price_tmp=>".$coupon_remain_price_tmp);
						//debug_var("coupon_deduction_price_tmp=>".$coupon_deduction_price_tmp);
						//debug_var("coupon_remain_real_percent=>".$coupon_remain_real_percent);
						//debug_var("coupon_remain_real_price=>".$coupon_remain_real_price);
						//debug_var("coupon_remain_price=>".$coupon_remain_price);
						//debug_var("coupon_deduction_price=>".$coupon_deduction_price);
						//debug_var("cancel_memo=>".$cancel_memo);//exit;
					}

					//$data['coupon_refund_emoney']		= $coupon_refund_emoney;//티켓상품 잔여 값어치의 실제금액
					$data['coupon_remain_price']			= $coupon_remain_price;//티켓상품 결제금액의 실제금액
					$data['coupon_deduction_price']		= $coupon_deduction_price;//티켓상품 결제금액의 조정금액
					$data['cancel_memo']		= $cancel_memo;//티켓상품 결제금액의 조정금액
				}else{
					$goodstotal++;
				}
				//if($cfg_order['buy_confirm_use'] && $data_export['buy_confirm']!='none') $data['rt_ea'] = 0;

				//청약철회상품체크
				unset($goods);
				$goods = $this->goodsmodel->get_goods($data['goods_seq']);
				$data['cancel_type'] = $goods['cancel_type'];

				unset($data['inputs']);
				$data['inputs']	= $this->ordermodel->get_input_for_option($data['item_seq'], $data['option_seq']);

				// 교환신청 일 때 교환배송비 설정
				if ($mode === 'exchange') {
					$data['pay_shiping_cost'] = $data['swap_shiping_cost'];
				
				// 주문당시 무료배송 상품 반품일 때 반품배송비 2배 설정
				} else if ((int) $data['shipping_cost'] === 0 && $data['shipping_type'] === 'free' && $data['shiping_free_yn'] === 'Y') {
					$data['pay_shiping_cost'] = get_currency_price($data['refund_shiping_cost'] * 2, 1);
				
				// 유료배송 반품신청 일때 반품배송비 설정
				} else {
					$data['pay_shiping_cost'] = $data['refund_shiping_cost'];
				}

				// 구매확정후 반품을 위해 변수 저장 by hed #32095
				$data['keep_rt_ea'] = $data['rt_ea'];

				# 구매확정 사용시 : 지급예정수량(출고수량-지급예정반품수량-지급수량-소멸수량)
				if($cfg_order['buy_confirm_use'] && $data['reserve_ea']==0) $data['rt_ea'] = 0;

				// 구매확정후 반품가능 처리 by hed #32095
				$after_refund = '';
				if($cfg_order['buy_confirm_use'] && $type == 'return' && $data['rt_ea'] == 0){
					$data['rt_ea'] = $data['keep_rt_ea'];
					// 구매확정 후 환불 여부 by hed #32095
					$after_refund = ($data['rt_ea'])?'1':'';
				}
				$data['after_refund']= $after_refund;

				$ex_code_shipping_provider_seq = $data_export['item'][0]['shipping_provider_seq']."_".$data_export['item'][0]['export_code'];
				$loop[$ex_code_shipping_provider_seq]['export_item'][] = $data;
				$loop[$ex_code_shipping_provider_seq]['tot_rt_ea'] += $data['rt_ea'];

			}
		}

		if ( $_GET['mode'] == 'return_coupon' ) {
			if (!$coupontotal || empty($coupontotal) ){
				echo null;
				//$this->template->assign('backalert',true);
				//$msg = "환불신청 티켓상품이 없습니다.!";
				//echo js('alert("'.$msg.'");setTimeout(function(){closeDialog("order_refund_layer");},300);');//exit;
			}
		}elseif( !$goodstotal || empty($goodstotal) ) {
			echo null;
			/*
			 $this->template->assign('backalert',true);
			 if($_GET['mode'] == 'exchange') {
			 $msg = "맞교환신청 상품이 없습니다!";
			 }else{
			 $msg = "반품신청 상품이 없습니다!";
			 }
			 echo js('alert("'.$msg.'");setTimeout(function(){closeDialog("order_refund_layer");},300);');//exit;
			 */
		}

		if(($coupontotal > 0 && !empty($coupontotal)) || ($goodstotal > 0 && !empty($goodstotal))){
			foreach($loop as $ex_code_shipping_provider_seq=>$v){
				list($shipping_provider_seq, $export_code) = explode("_",$ex_code_shipping_provider_seq);
				$grp_sql = "SELECT refund_address_seq,refund_scm_type FROM fm_shipping_grouping WHERE shipping_provider_seq = {$shipping_provider_seq} AND default_yn = 'Y' LIMIT 1";
				$grpping = $this->db->query($grp_sql);
				$grpping = $grpping->row_array();
				$grp_seq = $grpping['refund_address_seq'];
				$grp_scm_type = $grpping['refund_scm_type'];
				$address = $this->shippingmodel->get_shipping_address($grp_seq, $grp_scm_type);

				$return_address = '';

				if($address['address_street']){
					$return_address = $address['address_street'];
					$deli_address1	= $address['address_street'];
				}else{
					$return_address = $address['address'];
					$deli_address1	= $address['address'];
				}
				$return_address .= " ".$address['address_detail'];
				$deli_address2	= $address['address_detail'];


				$loop[$ex_code_shipping_provider_seq]['shipping_provider'] = $this->providermodel->get_provider($shipping_provider_seq);

				$loop[$ex_code_shipping_provider_seq]['shipping_provider']['deli_zipcode'] = $address['address_zipcode'];
				$loop[$ex_code_shipping_provider_seq]['shipping_provider']['deli_address1'] = $deli_address1;
				$loop[$ex_code_shipping_provider_seq]['shipping_provider']['deli_address2'] = $deli_address2;

				$loop[$ex_code_shipping_provider_seq]['return_address'] = $return_address;
			}

			// 비현금성 주문일 경우 환불방법 미노출 처리 (신용카드, 휴대폰결제) :: 2018-07-20
			$show_refund_method = 'Y';
			if (in_array($orders['payment'], ['card', 'cellphone'])) {
				$show_refund_method = 'N';
			}

			// 간편결제 환불방법 미노출 처리
			if (is_order_simple_payment($orders)) {
				$show_refund_method = 'N';
			}

			$orders['show_refund_method'] = $show_refund_method;


			$this->template->assign(array('orders'		=> $orders));
			$this->template->assign(array('loop'		=> $loop));
			$this->template->assign(array('items'		=> $items));
			$this->template->assign(array('cancel_total_price'	=> $cancel_total_price,'gift_cnt'=>$gift_cnt));
			$this->template->assign(array('npay_use'	=>$npay_use));
			$this->template->assign(array('npay_reasons'=> $npay_reasons));
			$this->template->assign(array('mode'		=> $mode));
			$file_path = $this->template_path();

			if($_GET['mode'] == 'return_coupon') {//티켓상품 환불
				$file_path = str_replace('order_return','order_return_coupon',$file_path);
			}

			$this->template->define(array('tpl'=>$file_path));
			$this->template->print_("tpl");
		}
	}


	###
	public function temporary(){
		$this->load->library('privatemasking');
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$record = "";
		if($_GET['header_search_keyword']) {
			$_GET['keyfield'][] = 'order_seq';
			$_GET['keyword'][] = $_GET['header_search_keyword'];
		}

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_GET) == 0 ){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/order/temporary');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$_GET['regist_date'][0]		= $search_date['start_date'];
				$_GET['regist_date'][1]		= $search_date['end_date'];
				foreach($data_search_default as $key => $val){
					$key = str_replace("default_","",$key);
					$_GET[$key]		= $val;
				}
			}else{
				// 기본세팅이 없는경우 오늘의 입금확인 주문접수 주문을 검색조건으로 합니다.
				$_GET['regist_date'][0] = date('Y-m-d');
				$_GET['regist_date'][1] = date('Y-m-d');
				$_GET['chk_step'][15] = 1;
				//$_GET['chk_step'][25] = 1;//입금확인제외
			}
		}

		### 2012-08-10
		if($_GET['mode']=='bank'){
			$_GET['regist_date'][0] = date("Y-m-d", mktime(0,0,0,date("m")-1, date("d"), date("Y")));
			$_GET['regist_date'][1] = date('Y-m-d');
			$_GET['chk_step'][15] = 1;
			$where_order[] = " ord.settleprice >= '".$_GET['sprice']."' ";
			$where_order[] = " ord.settleprice <= '".$_GET['eprice']."' ";
		}


		// 검색어
		if( $_GET['keyword'] ){
			$keyword = str_replace("'","\'",$_GET['keyword']);
			$where[] = "
			(
				(
					CONCAT(
						order_user_name,
						recipient_user_name,
							ifnull(depositor,' '),
						order_email,
						order_phone,
						order_cellphone,
						recipient_phone,
						recipient_cellphone,
						order_seq,
						IFNULL(mem.userid,' ')
					) LIKE '%" . $keyword . "%'
				) OR (
					order_seq IN
					(
						SELECT order_seq FROM fm_order_item WHERE goods_name LIKE '%".$keyword."%'
					)
				)
			)
			";

		}

		// 주문일
		$date_field = $_GET['date_field'] ? $_GET['date_field'] : 'ord.regist_date';
		if($_GET['regist_date'][0]){
			$where[] = "ord.regist_date >= '".$_GET['regist_date'][0]." 00:00:00'";
		}
		if($_GET['regist_date'][1]){
			$where[] = "ord.regist_date <= '".$_GET['regist_date'][1]." 24:00:00'";
		}

		// 주문상태
		if( $_GET['chk_step'] ){
			unset($arr);
			foreach($_GET['chk_step'] as $key => $data){
				$arr[] = "ord.step = '".$key."'";
			}
			$where[] = "(".implode(' OR ',$arr).")";
		}



		// 결제수단
		if( $_GET['payment'] ){
			unset($arr);
			foreach($_GET['payment'] as $key => $data){
				$arr[] = "payment = '".$key."'";
				if( in_array($key,array('virtual','account')) ){
					$arr[] = "payment = 'escrow_".$key."'";
				}
			}
			$where[] = "(".implode(' OR ',$arr).")";
		}


		###
		$where[] = "hidden = 'Y'";


		$this->db->order_by("seq","desc");
		$this->db->where(array('gb'=>'ORDER',"provider_seq"=>'1'));
		$query = $this->db->get("fm_exceldownload");
		foreach ($query->result_array() as $row){
			$row['count'] = count(explode("|",$row['item']));
			$loop[] = $row;
		}


		if($where){
			$query = "SELECT * FROM (
				SELECT
					ord.*,
					(SELECT goods_name FROM fm_order_item WHERE order_seq=ord.order_seq ORDER BY item_seq LIMIT 1) goods_name,
					(SELECT count(item_seq) FROM fm_order_item WHERE order_seq=ord.order_seq) item_cnt,
					(SELECT group_name FROM fm_member_group WHERE group_seq=mem.group_seq) group_name,
					mem.userid
				FROM
					fm_order ord
					LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
				WHERE ord.step!=0 AND " . implode(' AND ',$where) . "
			) t ORDER BY step ASC, regist_date DESC";
			$query = $this->db->query($query,$bind);

			foreach($query->result_array() as $k => $data)
			{
				$no++;
				$data['mstep'] = $this->arr_step[$data['step']];
				$data['mpayment'] = $this->arr_payment[$data['payment']];
				$step_cnt[$data['step']]++;
				$tot_settleprice[$data['step']] += $data['settleprice'];
				$tot[$data['step']][$data['important']] += $data['settleprice'];

				$data['step_cnt'] = $step_cnt;
				$data['tot_settleprice'] = $tot_settleprice;
				$data['tot'][$data['important']] = $tot[$data['step']][$data['important']];

				$data['loop'] = $loop;
	
				//개인정보 마스킹 표시
				$data = $this->privatemasking->masking($data, 'order');

				$record[$k] = $data;
				if($step_cnt[$data['step']] == 1)
				{
					$record[$k]['start'] = true;
					$ek = $k-1;
					if($ek >= 0 ){
						$record[$ek]['end'] = true;
					}
				}
			}

			if($record)
			{
				$record[$k]['end'] = true;
				foreach($record as $k => $data){
					$record[$k]['no'] = $no;
					$no--;
				}
			}
		}

		// 기본통화 symbol
		$currency_symbol = get_currency_symbol($this->config_system['basic_currency']);
		$this->template->assign(array('currency_symbol' => $currency_symbol));

		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign(array('record' => $record));
		$this->template->print_("tpl");
	}


	###
	public function autodeposit(){
		$auth = $this->authmodel->manager_limit_act('autodeposit_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}
		$this->admin_menu();
		$this->tempate_modules();

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_GET) == 0 ){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/order/autodeposit');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$_GET['sdate']			= $search_date['start_date'];
				$_GET['edate']			= $search_date['end_date'];
				foreach($data_search_default as $key => $val){
					$key = str_replace("default_","",$key);
					$_GET[$key]		= $val;
				}
			}
		}

		###
		$sc = $_GET;
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):'20';

		$sc['sdate']			= str_replace("-","",$_GET['sdate']);
		$sc['edate']			= str_replace("-","",$_GET['edate']);

		if($sc['ad_chk'][0]){
			$sc['ad_chk1'] = $sc['ad_chk'][0];
			$checked['ad_chk'][0] = "checked";
		}
		if($sc['ad_chk'][1]){
			$sc['ad_chk2'] = $sc['ad_chk'][1];
			$checked['ad_chk'][1] = "checked";
		}
		if($sc['ad_chk'][2]){
			$sc['ad_chk3'] = $sc['ad_chk'][2];
			$checked['ad_chk'][2] = "checked";
		}

		//unset($sc['ad_chk']); 페이지 이동시 자동확인 검색안되서 주석해제함. 2016-07-13 hyem
		###
		$this->load->model('usedmodel');

		$chks = $this->usedmodel->autodeposit_check();
		$this->template->assign(array('bankChk'=>$chks['chk'],'bankCount'=>$chks['count']));
		$this->template->assign(array('checked'=>$checked));

		if($chks['chk']=='Y'){
			$data = $this->usedmodel->get_bank_list($sc);

			//개인정보 마스킹 표시
			$this->load->library('privatemasking');
			foreach($data['list'] as $k => $v){
				$data['list'][$k] = $this->privatemasking->masking($data['list'][$k], 'order');
			}
			$private_masking = $this->authmodel->manager_limit_act('private_masking');

			$sc['searchcount']	 = $data['total'];
			$sc['total_page']	 = ceil($sc['searchcount'] / $sc['perpage']);
			$sc['totalcount']	 = $data['total'];

			$file_nm = end(explode("/",$file_path));
			$file_arr = explode(".",$file_nm);
			$paginlay = pagingtag($sc['searchcount'],$sc['perpage'],$file_arr[0].'?', getLinkFilter('',array_keys($sc)) );

			if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';

			$this->template->assign(array('loop' => $data['list']));
			$this->template->assign('private_masking',$private_masking);
			$this->template->assign('pagin',$paginlay);
			$this->template->assign('perpage',$sc['perpage']);
			$this->template->assign('sc',$sc);

			###
			$banks = config_load('bank_set');
			$this->template->assign('banks',$banks);
		}

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function autodeposit_memo_get(){
		$memo_num	= $_GET['memo_num'];
		$memo		= get_simple_memo('autodeposit', $memo_num);
		echo json_encode($memo);
	}

	public function autodeposit_memo_set(){
		$memo_num	= (int)$_POST['memo_num'];
		$memo		= $_POST['memo'];
		$return		= set_simple_memo('autodeposit', $memo_num, $memo);
		echo json_encode($return);
	}

	public function order_settle_admin(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->model('cartmodel');

		// 선택 상품을 장바구니에 합쳐줌
		$this->cartmodel->merge_for_choice();

		$cart = $this->cartmodel->cart_list("admin");

		$template_dir = $this->template->template_dir;
		$compile_dir = $this->template->compile_dir;

		$arr = config_load('bank');
		if($arr) foreach(config_load('bank') as $k => $v){
			list($tmp) = code_load('bankCode',$v['bank']);
			$v['bank'] = $tmp['value'];
			$bank[] = $v;
			if( $v['accountUse'] == 'y' ) $payment['bank'] = true;
		}

		$this->template->assign('firstmallcartid',session_id());
		$this->template->assign('list',$cart['list']);
		$this->template->assign('total',$cart['total']);
		$this->template->assign('bank',$bank);
		$this->template->assign('shipping_price',$cart['shipping_price']);
		$this->template->assign('shipping_company_cnt',$cart['shipping_company_cnt']);
		$this->template->assign('provider_shipping_policy',$cart['provider_shipping_policy']);
		$this->template->assign('provider_shipping_price',$cart['provider_shipping_price']);
		$this->template->assign('shop_shipping_policy',$cart['shop_shipping_policy']);
		$this->template->assign('total_price',$cart['total_price']);
		$this->template->assign('member_seq',$this->userInfo['member_seq']);

		$containerHeight = !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 600;
		$this->template->assign(array('containerHeight'=>$containerHeight));


		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}


	public function order_settle_person(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->model('cartmodel');

		// 선택 상품을 장바구니에 합쳐줌
		$this->cartmodel->merge_for_choice();

		$cart = $this->cartmodel->cart_list("");

		$template_dir = $this->template->template_dir;
		$compile_dir = $this->template->compile_dir;

		$query = "select cart_seq from fm_person_cart where person_seq = '0'";
		$query = $this->db->query($query,$bind);

		foreach($query->result_array() as $k => $data)
		{
			//주문 안된 장바구니 삭제
			$this->db->query("delete from fm_person_cart_option where cart_seq = '".$data['cart_seq']."'");
			$this->db->query("delete from fm_person_cart_input where cart_seq = '".$data['cart_seq']."'");
			$this->db->query("delete from fm_person_cart_suboption where cart_seq = '".$data['cart_seq']."'");
			$this->db->query("delete from fm_person_cart where cart_seq = '".$data['cart_seq']."'");
		}

		$bank = $payment = $escrow = "";
		$arr = config_load('bank');
		if($arr) foreach(config_load('bank') as $k => $v){
			list($tmp) = code_load('bankCode',$v['bank']);
			$v['bank'] = $tmp['value'];
			$bank[] = $v;
			if( $v['accountUse'] == 'y' ) $payment['bank'] = true;
		}
		if( $this->config_system['pgCompany'] ){
			$payment_gateway = config_load($this->config_system['pgCompany']);
			$payment_gateway['arrKcpCardCompany'] = code_load('kcpCardCompanyCode');

			foreach($payment_gateway['arrKcpCardCompany'] as $k => $v){
				$payment_gateway['arrCardCompany'][$v['codecd']]=$v['value'];
			}

			if(isset($payment_gateway['payment'])) foreach($payment_gateway['payment'] as $k => $v){
				$payment[$v] = true;
			}

			if(isset($payment_gateway['escrow'])) foreach($payment_gateway['escrow'] as $k => $v){
				$escrow[$v] = true;
			}

		}
		$this->template->assign('firstmallcartid',session_id());
		$this->template->assign('list',$cart['list']);
		$this->template->assign('total',$cart['total']);
		$this->template->assign('bank',$bank);
		$this->template->assign('payment',$payment);
		$this->template->assign('shipping_price',$cart['shipping_price']);
		$this->template->assign('shipping_company_cnt',$cart['shipping_company_cnt']);
		$this->template->assign('provider_shipping_policy',$cart['provider_shipping_policy']);
		$this->template->assign('provider_shipping_price',$cart['provider_shipping_price']);
		$this->template->assign('shop_shipping_policy',$cart['shop_shipping_policy']);
		$this->template->assign('total_price',$cart['total_price']);
		$this->template->assign('member_seq',$this->userInfo['member_seq']);

		$containerHeight = !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 600;
		$this->template->assign(array('containerHeight'=>$containerHeight));


		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}


	public function order_admin_option(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$containerHeight = !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 600;
		$this->template->assign(array('containerHeight'=>$containerHeight));


		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}


	public function goods_select(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$containerHeight = !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 600;
		$this->template->assign(array('containerHeight'=>$containerHeight));

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}


	public function cart(){

		$this->load->model('membermodel');

		$cart_table		= (trim($_GET["cart_table"])) ? trim($_GET["cart_table"]) : 'admin';
		$cfg['order']	= config_load('order');
		$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');

		if	($_GET["member_seq"] > 0){
			$members				= $this->membermodel->get_member_data($_GET["member_seq"]);
		}

		if(!$_GET['option'] && $_GET['list_num'] > 0){
			echo "선택한 옵션이 없습니다.";
			exit;
		}

		/*
		 if($_GET['inputsTitle']){
		 $this->load->model('goodsmodel');
		 $inputs		= $this->goodsmodel->get_goods_input($_GET['option_select_goods_seq']);
		 foreach($inputs as $key_input => $data_input){
		 if	($_GET['inputsValue']) foreach($_GET['inputsValue'] as $k1 => $ipt){
		 if	($ipt) foreach($ipt as $k2 => $iptVal){
		 $iptVal	= str_replace('(필수)', '', $iptVal);
		 if($data_input['input_require'] == 1 && !$iptVal){
		 echo $data_input['input_name'] . " 옵션은 필수입니다.";
		 exit;
		 }elseif($data_input['input_require'] == 1){
		 $inputs_required = true;
		 }
		 }
		 }
		 }
		 }
		 */

		if($cart_table == "person"){
			$this->load->model('personcartmodel');
			$ordertype	= 'person';
			$cart		= $this->personcartmodel->catalog($members["member_seq"],'',$_GET);
		}else{
			$this->load->model('cartmodel');
			$ordertype	= (!$_GET["cart_table"])? 'admin':$_GET["cart_table"];
			$cart		= $this->cartmodel->catalog($ordertype,$_GET);
			// 선택 상품을 장바구니에 합쳐줌(개인결제,관리자주문,미매칭,재주문 아닐때)
			if($ordertype) $this->cartmodel->merge_for_choice();
		}

		$cart['max_option_key'] = $_GET['max_option_key'];

		$page_type				= 'cart';

		if	($_GET['issettle'] == 'y')	$page_type	= 'order';

		$result	= $this->ordermodel->remanufacture_cart($cart, $page_type, $ordertype);
		if	(!$result['status']){
			if		($result['action'] == 'optiondel_back' && $ordertype == 'admin'){
				$this->cartmodel->delete_option($result['cart_option_seq'],'');
			}elseif	($result['action'] == 'suboptiondel_back' && $ordertype == 'admin'){
				$this->cartmodel->delete_option($data['cart_option_seq'],'');
			}else{
				echo $result['err_msg'];
				//openDialogAlert($result['err_msg'], 400, 150);
				exit;
			}
		}else{
			// 함수내 변수로 재정의
			foreach($result as $k => $data)	$$k	= $data;
		}

		// 사용한 코드
		$cart_promotioncode		= $this->session->userdata('cart_promotioncode_'.session_id());

		if($cart_table == "admin"){
			// 사은품 추출
			foreach($cart['data_goods'] as $goods_seq => $data){
				$gift_goods[] 			= $goods_seq;
				$gift_provider[]		= $data['provider_seq'];
				$gift_shipping[]		= $data['shipping_group_seq'];
				$gift['goods_seq']		= $goods_seq;
				$gift['provider_seq']	= $data['provider_seq'];
				$gift['ea']				= $data['ea'];
				$gift['tot_price']		= $data['price'];
				$gift_loop[]			= $gift;
				foreach($data['r_category'] as $category_code){
					$gift_categorys[] = $category_code;
					$category[] = $category_code;
				}
			}

			// 넘기는 데이터가 잘못 되어 수정함 :: 2019-09-02 pjw
			$gift	= $this->ordermodel->get_gift_event($gift_categorys, $gift_goods, $gift_shipping, null, $gift_loop);
			foreach($gift['gloop'] as &$row) {
				$row['title'] = htmlspecialchars($row['title'],ENT_QUOTES);
			}
			$this->template->assign(array('gift_cnt'=>$gift['gift_cnt'],'gloop'=>$gift['gloop']));
		}

		// 입점사 갯수 카운터 :: 2015-10-08 lwh
		if($cart_table == 'person' || $cart_table == 'admin'){
			foreach($cart['list'] as $k => $val)
				$provider_cnt[$val['provider_seq']] += 1;

				$provider_cnt = count($provider_cnt);
				$this->template->assign('provider_cnt' , $provider_cnt);
		}

		if($members['cellphone']){
			$members['cellphone_detail'] = explode("-",$members['cellphone']);
		}

		if($members['phone']){
			$members['phone_detail'] = explode("-",$members['phone']);
		}

		// ### NEW 배송 그룹 정보 추출 ### :: START -> shipping library 계산
		$this->load->library('shipping');
		if($_POST['nation'])	$ship_ini['nation']	= $_POST['nation'];
		$ini_info				= $this->shipping->set_ini($ship_ini);
		$shipping_group_list	= $this->shipping->get_shipping_groupping($cart['list'],'adminCart');

		// 상세 배송비 금액 추출
		$shipping_cost_detail	= $shipping_group_list['shipping_cost_detail'];
		unset($shipping_group_list['shipping_cost_detail']);

		// 전체 배송비 금액 추출
		$total_shipping_price	= $shipping_group_list['total_shipping_price'];
		unset($shipping_group_list['total_shipping_price']);

		// 최종 결제 금액 재계산
		$cart['total_price'] = $cart['total_price'] + $total_shipping_price;

		// 배송가능 해외국가 추출
		$ship_gl_arr	= $this->shippingmodel->get_gl_shipping();
		$ship_gl_list	= $this->shippingmodel->split_nation_str($ship_gl_arr);
		// ### NEW 배송 그룹 정보 추출 및 계산 :: END ###

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		// 신)배송비 관련 변수 정의
		$this->template->assign(array(
						'ship_gl_arr'=>$ship_gl_arr,
						'ship_gl_list'=>$ship_gl_list
		)); // 국가목록
		$this->template->assign('ini_info',$ini_info); // 배송ini 설정정보
		$this->template->assign('shipping_group_list',$shipping_group_list); // 배송그룹LIST
		$this->template->assign('shipping_cost_detail',$shipping_cost_detail); // 배송비 상세
		$this->template->assign('total_shipping_price',$total_shipping_price); // 전체 배송비
		// 신)배송비 관련 assign END

		$this->template->assign('cart_promotioncode' , $cart_promotioncode);
		$this->template->assign('ordertype',$ordertype);
		$this->template->assign('firstmallcartid',session_id());
		$this->template->assign('cart_table',$cart_table);
		$this->template->assign('members',$members);
		$this->template->assign('member_seq',$members['member_seq']);
		$this->template->assign('total',$cart['total']);
		$this->template->assign('total_sale',$cart['total_sale']);
		$this->template->assign('total_sale_list',$cart['total_sale_list']);
		$this->template->assign('total_reserve',$cart['total_reserve']);
		$this->template->assign('total_price',$cart['total_price']);
		$this->template->assign('list',$cart['list']);
		$this->template->assign('data_goods',$cart['data_goods']);
		$this->template->assign('goodsImageSize',config_load('goodsImageSize'));
		$this->template->assign('shipping_cart_list',$shipping_cart_list);
		$this->template->assign('shipping_price',$cart['shipping_price']);
		$this->template->assign('shipping_company_cnt',$cart['shipping_company_cnt']);
		$this->template->assign('provider_shipping_policy',$cart['provider_shipping_policy']);
		$this->template->assign('provider_shipping_price',$cart['provider_shipping_price']);
		$this->template->assign('shop_shipping_policy',$cart['shop_shipping_policy']);
		$this->template->assign('is_coupon',$is_coupon);
		$this->template->assign('is_goods',$is_goods);
		$this->template->assign('goodscancellation',$goodscancellation);

		// 상품별 주문배송방법 선택
		$this->template->assign('shipping_group_policy',$cart['shipping_group_policy']);
		$this->template->assign('shipping_group_price',$cart['shipping_group_price']);

		// 주문 페이지용 추가 데이터 로드
		if	($_GET['issettle'] == 'y'){
			$member_usable_coupons	= 0;
			if	($members['member_seq'] > 0){
				// 보유 할인쿠폰수
				$this->load->model('couponmodel');
				$dsc['whereis']		= " and member_seq = ".$members['member_seq']." and use_status='unused' AND ( (issue_startdate is null  AND issue_enddate is null ) OR (issue_startdate <='".date('Y-m-d')."' AND issue_enddate >='".date('Y-m-d')."') )";
				$member_usable_coupons	= $this->couponmodel->get_download_total_count($dsc);
			}

			$this->template->assign('member_usable_coupons', $member_usable_coupons);

			// 상품후기 지급 마일리지
			$cfg_reserve					= ($this->reserves)?$this->reserves:config_load('reserve');
			$this->template->assign('cfg_reserve', $cfg_reserve);

			$file_path	= str_replace('cart.html', 'settle_cart.html', $file_path);
		}

		## 상품선택 창 내에서 임시장바구니 스킨으로 변경 @2015-08-03 pjm
		if($_GET['mode'] == "goods_select" || $_GET['mode'] == "tmp") $file_path = "default/order/cart_new.html";

		$this->template->define(array("tpl"=>$file_path));
		$this->template->print_("tpl");
	}


	public function optional_changes(){

		$cart_seq = (int) $_GET['no'];
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');

		//$this->load->helper('order');

		if($_GET['cart_table'] == "person"){
			$this->load->model('personcartmodel');
			$cart = $this->personcartmodel->get_cart($cart_seq);
			$cart_options = $this->personcartmodel->get_cart_option($cart_seq);
			$cart_suboptions = $this->personcartmodel->get_cart_suboption($cart_seq);
		}else{
			$this->load->model('cartmodel');
			$cart = $this->cartmodel->get_cart($cart_seq);
			$cart_options = $this->cartmodel->get_cart_option($cart_seq);
			$cart_suboptions = $this->cartmodel->get_cart_suboption($cart_seq);
		}

		$goods_seq = $cart['goods_seq'];



		$categorys = $this->goodsmodel->get_goods_category($goods_seq);
		if($categorys) foreach($categorys as $key => $data_category){
			if( $data_category['link'] == 1 ){
				$category_code = $this->categorymodel->split_category($data_category['category_code']);
			}
		}

		$goods = $this->goodsmodel->get_goods($goods_seq);
		$options = $this->goodsmodel->get_goods_option($goods_seq);
		$suboptions = $this->goodsmodel->get_goods_suboption($goods_seq);

		foreach($cart_options as $k => $cart_opt){
			/* 이벤트 할인 */
			$cart_opt['event'] = get_event_price($cart_opt['price'], $goods_seq, $category_code, $cart_opt['consumer_price'], $goods);
			if($cart_opt['event']['event_seq']) {
				if($cart_opt['event']['target_sale'] == 1 && $cart_opt['consumer_price'] > 0 ){//정가기준 할인시
					$cart_opt['price'] = ($cart_opt['consumer_price'] > $cart_opt['event']['event_sale_unit'])?$cart_opt['consumer_price'] - (int) $cart_opt['event']['event_sale_unit']:0;
				}else{
					$cart_opt['price'] = ($cart_opt['price'] > $cart_opt['event']['event_sale_unit'])?$cart_opt['price'] - (int) $cart_opt['event']['event_sale_unit']:0;
				}
			}
			$cart_options[$k] = $cart_opt;
		}

		foreach($options as $k => $opt){
			/* 이벤트 할인 */
			$opt['event'] = get_event_price($opt['price'], $goods_seq, $category_code, $goods);
			if($opt['event']['event_seq']) {
				if($opt['event']['target_sale'] == 1 && $opt['consumer_price'] > 0 ){//정가기준 할인시
					$opt['price'] = ($opt['consumer_price'] > $opt['event']['event_sale_unit'])?$opt['consumer_price'] - (int) $opt['event']['event_sale_unit']:0;
				}else{
					$opt['price'] = ($opt['price'] > $opt['event']['event_sale_unit'])?$opt['price'] - (int) $opt['event']['event_sale_unit']:0;
				}
			}

			/* 대표가격 */
			if($opt['default_option'] == 'y'){
				$goods['price'] 			= $opt['price'];
				$goods['consumer_price'] 	= $opt['consumer_price'];
				$goods['reserve'] 			= $opt['reserve'];
				$goods['point'] 			= $opt['point'];
			}

			// 재고 체크
			$opt['chk_stock'] = check_stock_option($goods['goods_seq'],$opt['option1'],$opt['option2'],$opt['option3'],$opt['option4'],$opt['option5'],$opt['ea'],$cfg_order);
			$options[$k] = $opt;
		}

		if(isset($options[0]['option_divide_title'])) $goods['option_divide_title'] = $options[0]['option_divide_title'];
		$file = str_replace('optional_changes','_optional_changes',$this->template_path());
		$this->template->assign(array('cart'=>$cart));
		$this->template->assign(array('goods'=>$goods));
		$this->template->assign(array('options'=>$options));
		$this->template->assign(array('suboptions'=>$suboptions));
		$this->template->assign(array('cart_options'=>$cart_options));
		$this->template->assign(array('cart_suboptions'=>$cart_suboptions));
		$this->template->define(array('LAYOUT'=>$file));
		$this->template->print_('LAYOUT');
	}


	public function modify()
	{
		$person_table = "";
		if($_GET['cart_table'] == "person"){
			$person_table="_person";
		}
		$seq = $_GET['seq'];
		$where[] = "cart_seq=?";
		$where_val[] = $seq;
		$query = "update fm".$person_table."_cart_option set ea='".$_POST['ea'][$seq]."' where ".implode(' and ',$where);
		$this->db->query($query,$where_val);
		//pageReload('','parent');
		openDialogAlert("상품을 변경하였습니다.",400,140,'parent',"parent.cart('".$_GET['cart_table']."');");
	}

	public function add_cart(){

		$this->load->library('upload');
		$this->load->model('cartmodel');
		$this->load->model('goodsmodel');
		$this->load->model('membermodel');

		$member_seq		= $_GET['member_seq'];
		$pre_cart_seqs	= "";
		$mode			= "admin";
		$goods_seq		= (int) $_GET['goodsSeq'];
		$cfg['order']	= config_load('order');
		$goods			= $this->goodsmodel->get_goods($goods_seq);
		$inputs			= $this->goodsmodel->get_goods_input($goods_seq);
		$options		= $this->goodsmodel->get_goods_option($goods_seq);
		$suboptions		= $this->goodsmodel->get_goods_suboption_required($goods_seq);
		$member_data	= $this->membermodel->get_member_data($member_seq);

		// 상품상태 체크
		if($goods['goods_status'] != 'normal'){
			if		($goods['goods_status'] == 'unsold')	$err_msg	= '은 판매중지 상품입니다.';
			else											$err_msg	= '은 품절된 상품입니다.';
			alert($goods['goods_name'].$err_msg);
			exit;
		}
		// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
		if( $goods['event']['event_goodsStatus'] === true ){
			$err_msg = "단독이벤트 기간에만 구매가 가능한 상품입니다.";
			alert($err_msg);
			exit;
		}

		// 가격대체문구 비회원
		if	($goods['string_price_use'] && !$member_data['member_seq']){
			$price_msg = '비회원은 ' . $goods['string_price'];
		}
		// 가격대체문구 회원
		if	($goods['member_string_price_use'] && $member_data['group_seq'] == 1){
			$price_msg = '일반회원은 ' . $goods['member_string_price'];
		}
		// 가격대체문구 일반이상회원
		if	($goods['allmember_string_price_use'] && $member_data['group_seq'] > 1){
			$price_msg = '모든회원은 ' . $goods['allmember_string_price'];
		}

		// 가격 대체문구 상품 강제 구매 :: 2015-07-28 lwh
		if($price_msg && !$_GET['price_confirm']){
			echo("<script>top.openDialogConfirm('".$price_msg."<br/><br/>쇼핑몰 운영 정책상 해당 소비자가 구매할 수 없는 상품입니다.<br/>그럼에도 불구하고 구매할 수 있도록 하시겠습니까?<br/>','430','250',function(){location.href='./person_cart?goodsSeq=".$_GET['goodsSeq']."&member_seq=".$_GET['member_seq']."&price_confirm=Y';},function(){});</script>");
			exit;
		}

		// 최소구매 수량에 따른 구매 수량 변경
		$order_ea		= 1;
		if	($goods['min_purchase_ea'] > 0)	$order_ea	= $goods['min_purchase_ea'];

		foreach($options as $o => $opt){
			// 필수 옵션 재고 체크
			$chk	= check_stock_option($goods_seq, $opt['option1'], $opt['option2'],
					$opt['option3'], $opt['option4'], $opt['option5'],
					$order_ea, $cfg['order'], 'view_stock');
			if	( $chk['stock'] < 0 )	{
				continue;
			}else{
				$option	= $opt;
				break;
			}
		}
		if	($option){
			unset($options);
			$options[0]	= $option;
		}else{
			$err_msg	= '구매 가능한 필수옵션이 없습니다.';
			alert($err_msg);
			exit;
		}

		// 필수 추가구성옵션 재고 체크
		if	($suboptions)foreach($suboptions as $sub_title => $subArr){
			$chk	= false;
			if	($subArr)foreach($subArr as $s => $sub){
				$chk	= check_stock_suboption($goods_seq, $sub['suboption_title'],
						$sub['suboption'], 1,
						$cfg['order'], 'view_stock');
				if	( $chk['stock'] < 0 )	continue;

				$chk	= true;
				$reSuboptions[]	= $sub;
				break;
			}
			if	(!$chk){
				$err_msg	= '필수 추가구성옵션' . $sub_title . '를 구매할 수 없습니다.';
				alert($err_msg);
				exit;
			}
		}
		$suboptions	= $reSuboptions;

		$session_id = session_id();

		$insert_data['goods_seq'] 	= $goods_seq;
		$insert_data['session_id'] 	= $session_id;
		$insert_data['member_seq'] 	= $member_seq;
		$insert_data['distribution'] = $mode;
		$insert_data['regist_date '] = $insert_data['update_date'] = date('Y-m-d H:i:s',time());
		$insert_data['fblike'] = 'N';
		$this->db->insert('fm_cart', $insert_data);
		$cart_seq = $this->db->insert_id();

		unset($insert_data);
		for($i=0;$i<5;$i++){
			if( !isset($options[0]["opts"][$i]) || !$options[0]["option_divide_title"][$i] ) $options[0]["opts"][$i] = "";
			if( !isset($options[0]["opts"][$i]) || !$options[0]["option_divide_title"][$i] ) $options[0]["option_divide_title"][$i] = null;
		}

		$insert_data['option1']		= $options[0]["opts"][0];
		$insert_data['title1']		= $options[0]["option_divide_title"][0];
		$insert_data['option2']		= $options[0]["opts"][1];
		$insert_data['title2']		= $options[0]["option_divide_title"][1];
		$insert_data['option3']		= $options[0]["opts"][2];
		$insert_data['title3']		= $options[0]["option_divide_title"][2];
		$insert_data['option4']		= $options[0]["opts"][3];
		$insert_data['title4']		= $options[0]["option_divide_title"][3];
		$insert_data['option5']		= $options[0]["opts"][4];
		$insert_data['title5']		= $options[0]["option_divide_title"][4];
		$insert_data['ea'] 			= $order_ea;
		$insert_data['cart_seq']	= $cart_seq;
		$insert_data['promotion_code_seq']	= $this->session->userdata('cart_promotioncodeseq_'.session_id());
		$insert_data['promotion_code_serialnumber']	= $this->session->userdata('cart_promotioncode_'.session_id());
		if	($goods['shipping_policy'] =='goods')	$insert_data['shipping_method'] = 'each_delivery';
		else										$insert_data['shipping_method'] = 'delivery';
		$this->db->insert('fm_cart_option', $insert_data);
		$cart_option_seq = $this->db->insert_id();

		// 입력옵션이 있는 빈 값으로 입력옵션을 추가
		unset($insert_data);
		krsort($inputs);
		if	($inputs)foreach($inputs as $k => $int){
			$insert_data['cart_option_seq']	= $cart_option_seq;
			$insert_data['cart_seq']		= $cart_seq;
			$insert_data['type']			= $int['input_form'];
			$insert_data['input_title']		= $int['input_name'];
			$insert_data['input_value']		= '';
			$this->db->insert('fm_cart_input', $insert_data);
		}

		// 필수 추가구성옵션 추가
		unset($insert_data);
		if	($suboptions)foreach($suboptions as $k => $sub){
			$insert_data['cart_option_seq']	= $cart_option_seq;
			$insert_data['cart_seq']		= $cart_seq;
			$insert_data['suboption_title']	= $sub['suboption_title'];
			$insert_data['suboption']		= $sub['suboption'];
			$insert_data['ea']				= 1;
			$this->db->insert('fm_cart_suboption', $insert_data);
		}

		unset($insert_data);
		$config['upload_path'] = ROOTPATH."data/order/";
		$config['allowed_types'] = implode('|', array('jpg','jpeg','png','gif','bmp','tif','pic'));
		$config['max_size']	= $this->config_system['uploadLimit'];
		$aGetinputsValue = $this->input->get('inputsValue');
		if ($aGetinputsValue) {
			$fileNum = 0;
			$textNum = 0;
			foreach($inputs as $key_input => $data_input){
				if ($data_input['input_form']=='file') {
					$this->upload->initialize($config, true);
					if ($this->upload->do_upload('inputsValue', $fileNum)) {
						$insert_data['type'] = 'file';
						$insert_data['input_title'] = $data_input['input_name'];
						$insert_data['input_value'] = $this->upload->file_name;
						$fileNum++;
					}
				} else {
					if ($aGetinputsValue[$textNum]) {
						$insert_data['type'] = 'text';
						$insert_data['input_title'] = $data_input['input_name'];
						$insert_data['input_value'] = $aGetinputsValue[$textNum];
						$textNum++;
					}
				}
				if ($insert_data['input_value']) {
					$insert_data['cart_seq'] = $cart_seq;
					$insert_data['cart_option_seq'] = $cart_option_seq;
					$this->db->insert('fm_cart_input', $insert_data);
				}
			}
		}

		unset($insert_data);
		if( isset($_GET['suboption']) ){
			foreach($_GET['suboption'] as $k1 => $suboption){
				$insert_data['ea']				= $_GET['suboptionEa'][$k1];
				$insert_data['suboption_title']	= $_GET['suboptionTitle'][$k1];
				$insert_data['suboption']		= $suboption;
				$insert_data['cart_seq'] 		= $cart_seq;
				$insert_data['cart_option_seq'] 	= $cart_option_seq;
				$this->db->insert('fm_cart_suboption', $insert_data);
			}
		}

		// 이전에 담긴 같은상품 합치기
		$this->cartmodel->merge_for_goods($goods_seq,$cart_seq);

		/* 상품분석 수집 */
		$this->load->model('goodslog');
		$this->goodslog->add('admin',$goods_seq);

		echo("<script>top.cart('admin');</script>");


	}


	## 장바구니 insert 전 상품 상태 체크 @2015-08-25 pjm
	public function add_cart_check(){

		$this->load->model('cartmodel');
		$this->load->model('goodsmodel');
		$this->load->model('membermodel');

		if(!$_GET['cart_table']) $_GET['cart_table'] = "admin";
		$member_seq		= $_GET['member_seq'];
		$cart_table		= $_GET['cart_table'];
		$pre_cart_seqs	= "";
		$goods_seq		= (int) $_GET['goodsSeq'];
		$cfg['order']	= config_load('order');
		$goods			= $this->goodsmodel->get_goods($goods_seq);
		$inputs			= $this->goodsmodel->get_goods_input($goods_seq);
		$options		= $this->goodsmodel->get_goods_default_option($goods_seq);
		$suboptions		= $this->goodsmodel->get_goods_suboption_required($goods_seq);
		$member_data	= $this->membermodel->get_member_data($member_seq);

		// 상품상태 체크
		if($goods['goods_status'] != 'normal'){
			if		($goods['goods_status'] == 'unsold')	$err_msg	= '은 판매중지 상품입니다.';
			else											$err_msg	= '은 품절된 상품입니다.';
			echo json_encode(array("res"=>false,"msg"=>$goods['goods_name'].$err_msg));
			//alert($goods['goods_name'].$err_msg);
			exit;
		}

		//미노출 체크 @2016-12-05
		if( $goods['goods_view'] == 'notLook'){
			$msg	= '미노출 상품 입니다.';
			echo json_encode(array("res"=>false,"msg"=>$goods['goods_name'].'은 미노출 상품입니다.'));
			exit;
		}

		// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
		if( $goods['event']['event_goodsStatus'] === true ){
			echo json_encode(array("res"=>false,"msg"=>"단독이벤트 기간에만 구매가 가능한 상품입니다."));
			//alert($err_msg);
			exit;
		}

		// 가격대체문구 비회원
		if	($goods['string_price_use'] && !$member_data['member_seq']){
			$price_msg = '비회원은 ' . $goods['string_price'];
		}
		// 가격대체문구 회원
		if	($goods['member_string_price_use'] && $member_data['group_seq'] == 1){
			$price_msg = '일반회원은 ' . $goods['member_string_price'];
		}
		// 가격대체문구 일반이상회원
		if	($goods['allmember_string_price_use'] && $member_data['group_seq'] > 1){
			$price_msg = '모든회원은 ' . $goods['allmember_string_price'];
		}

		// 가격 대체문구 상품 강제 구매 :: 2015-07-28 lwh
		if($price_msg && !$_GET['price_confirm']){
			echo json_encode(array("res"=>false,"errtype"=>"price_confirm","msg"=>$price_msg."<br/><br/>쇼핑몰 운영 정책상 해당 소비자가 구매할 수 없는 상품입니다.<br/>그럼에도 불구하고 구매할 수 있도록 하시겠습니까?<br/>"));
			exit;
		}

		// 최소구매 수량에 따른 구매 수량 변경
		$order_ea		= 1;
		if	($goods['min_purchase_ea'] > 0)	$order_ea	= $goods['min_purchase_ea'];

		foreach($options as $o => $opt){
			// 필수 옵션 재고 체크
			$chk	= check_stock_option($goods_seq, $opt['option1'], $opt['option2'],
					$opt['option3'], $opt['option4'], $opt['option5'],
					$order_ea, $cfg['order'], 'view_stock');
			if	( $chk['stock'] < 0 )	continue;

			$option	= $opt;
			break;
		}
		if	($option){
			unset($options);
			$options[0]	= $option;
		}else{
			$err_msg	= '구매 가능한 필수옵션이 없습니다.';
			echo json_encode(array("res"=>false,"msg"=>$err_msg));
			exit;
		}

		// 필수 추가구성옵션 재고 체크
		if	($suboptions)foreach($suboptions as $sub_title => $subArr){
			$chk	= false;
			if	($subArr)foreach($subArr as $s => $sub){
				$chk	= check_stock_suboption($goods_seq, $sub['suboption_title'],
						$sub['suboption'], 1,
						$cfg['order'], 'view_stock');
				if	( $chk['stock'] < 0 )	continue;

				$chk	= true;
				break;
			}
			if	(!$chk){
				$err_msg	= '필수 추가구성옵션' . $sub_title . '를 구매할 수 없습니다.';
				echo json_encode(array("res"=>false,"msg"=>$err_msg));
				exit;
			}
		}

		echo json_encode(array("res"=>true,"msg"=>$err_msg,"cart_table"=>$cart_table));

	}

	// 관리자 주문 장바구니 삭제
	public function cart_del()
	{
		$this->load->model('cartmodel');
		if( !isset($_GET['cart_seq']) ){
			openDialogAlert("삭제할 상품이 없습니다.",400,140,'parent',"");
			exit;
		}
		if	($_GET['cart_option_seq'] > 0){
			$this->db->query("delete from fm_cart_option where cart_option_seq = '".$_GET['cart_option_seq']."'");
			$this->db->query("delete from fm_cart_input where cart_option_seq = '".$_GET['cart_option_seq']."'");
			$this->db->query("delete from fm_cart_suboption where cart_option_seq = '".$_GET['cart_option_seq']."'");
			$sql	= "select count(*) cnt from fm_cart_option where cart_seq = '".$_GET['cart_seq']."'";
			$query	= $this->db->query($sql);
			$result	= $query->row_array();
			if	(!($result['cnt'] > 0)){
				$this->db->query("delete from fm_cart where cart_seq = '".$_GET['cart_seq']."'");
			}
		}else{
			$this->db->query("delete from fm_cart_option where cart_seq = '".$_GET['cart_seq']."'");
			$this->db->query("delete from fm_cart_input where cart_seq = '".$_GET['cart_seq']."'");
			$this->db->query("delete from fm_cart_suboption where cart_seq = '".$_GET['cart_seq']."'");
			$this->db->query("delete from fm_cart where cart_seq = '".$_GET['cart_seq']."'");
		}
		openDialogAlert("상품을 삭제하였습니다.",400,140,'parent',"parent.cart();");
	}

	public function person_cart_del()
	{
		$this->load->model('cartmodel');
		if( !isset($_GET['cart_seq']) ){
			openDialogAlert("선택된 주문이 없습니다.",400,140,'parent',"");
			exit;
		}


		$this->db->query("delete from fm_person_cart_option where cart_seq = '".$_GET['cart_seq']."'");
		$this->db->query("delete from fm_person_cart_input where cart_seq = '".$_GET['cart_seq']."'");
		$this->db->query("delete from fm_person_cart_suboption where cart_seq = '".$_GET['cart_seq']."'");
		$this->db->query("delete from fm_person_cart where cart_seq = '".$_GET['cart_seq']."'");
		openDialogAlert("상품을 삭제하였습니다.",400,140,'parent',"parent.cart('person');");
	}


	public function download_member(){

		$this->load->model('membermodel');
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$ordertype = $_GET["ordertype"];

		### GROUP
		$group_arr = $this->membermodel->find_group_list();


		// 선택 상품을 장바구니에 합쳐줌
		$containerHeight = !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 600;
		$this->template->assign(array('containerHeight'=>$containerHeight));
		$this->template->assign(array('ordertype'=>$ordertype,'group_arr'=>$group_arr));


		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");


	}


	// 회원검색리스트
	public function download_member_list()
	{
		$this->load->model('snsmember');
		$this->load->model('membermodel');

		### SEARCH
		$sc = $_POST;
		$sc['search_text']		= ($sc['search_text'] == '이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소') ? '':$sc['search_text'];
		$sc['orderby']		= 'A.member_seq';
		$sc['sort']				= (isset($_POST['sort'])) ?		$_POST['sort']:'desc';
		$sc['perpage']		= (!empty($_POST['perpage'])) ?	intval($_POST['perpage']):10;
		$sc['page']			= (!empty($_POST['page'])) ?		intval(($_POST['page'] - 1) * $sc['perpage']):0;
		$sc['groupsar']		= $coupongroupsar;

		//개인결재생성시 가입 승인회원만 검색 @2016-07-22 ysm
		if( $sc['ordertype'] == 'person' ) $sc['status'] = 'done';

		### MEMBER
		$data = $this->membermodel->popup_member_list($sc);
		// 페이징 처리 위한 변수 셋팅
		$page =  get_current_page($sc);
		$pagecount =  get_page_count($sc, $data['count']);

		### PAGE & DATA
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']		= @ceil($sc['searchcount']/ $sc['perpage']);
		$sc['totalcount']		= $this->membermodel->get_item_total_count();

		$idx = 0;


		foreach($data['result'] as $datarow){

			$class = ($download_coupons)?" class='bg-gray' ":"";

			if($datarow['business_seq'] != ""){
				$datarow['user_name'] = $datarow['bname'];
				$datarow['address_type'] 	= $datarow['baddress_type'];
				$datarow['address'] = $datarow['baddress'];
				$datarow['address_street'] 	= $datarow['baddress_street'];
				$datarow['address_detail'] = $datarow['baddress_detail'];
				$datarow['phone'] = $datarow['bphone'];
				$datarow['cellphone'] = $datarow['bcellphone'];
				$datarow['zipcode'] = $datarow['bzipcode'];
			}

			$datarow['number'] = $data['count'] - ( ( $page -1 ) * $sc['perpage'] + $i + 1 ) + 1;
			$datarow['type']	= $datarow['business_seq'] ? '기업' : '개인';
			$html .= '<tr  '.$class.' >';
			if($download_coupons) {
				$html .= '	<td  class="its-td-align center"> </td>';
			}else{
				$html .= '	<td  class="its-td-align center"><span class="btn small gray"><button class="resp_btn v2" onclick="chkmember(this);" name="member_chk[]" seq="'.$datarow['member_seq'].'" cellphone="'.$datarow['cellphone'].'" email="'.$datarow['email'].'"  userid="'.$datarow['userid'].'"  user_name="'.$datarow['user_name'].'" phone="'.$datarow['phone'].'" class="member_chk" '.$disabled.' zipcode="'.$datarow['zipcode'].'" address_type="'.$datarow['address_type'].'" address="'.$datarow['address'].'" address_street="'.$datarow['address_street'].'" address_detail="'.$datarow['address_detail'].' "  group_name="'.$datarow['group_name'].'" type="'.$datarow['business_seq'].'" bname="'.$datarow['bname'].'"bceo="'.$datarow['bceo'].'"bitem="'.$datarow['bitem'].'"bstatus="'.$datarow['bstatus'].'"bno="'.$datarow['bno'].'">선택</button></span></td>';
			}
			$html .= '	<td class="its-td-align center">'.$datarow['number'].'</td>';
			$html .= '	<td class="its-td-align center">'.$datarow['type'].'</td>';
			$snsHTML	= '';
			if	($datarow['rute'] != "none" ) {
				$snsmbsc['select']		= ' * ';
				$snsmbsc['whereis']		= ' and member_seq = \''.$datarow['member_seq'].'\' ';
				$snslist	= $this->snsmember->snsmb_list($snsmbsc);
				if	($snslist) foreach($snslist['result'] as $s => $sns){
					if	($sns['rute']){
						$snsHTML	.= '<img src="/admin/skin/default/images/sns/sns_'.substr($sns['rute'], 0, 1).'0.gif" align="absmiddle"> ';
					}
				}
			}
			$html .= '	<td class="its-td-align left pdl5 bold">'.$snsHTML.$datarow['userid'].'</td>';
			$html .= '	<td class="its-td-align center">'.$datarow['user_name'].'</td>';
			$html .= '	<td class="its-td-align center">'.$datarow['email'].'</td>';
			$html .= '	<td class="its-td-align center">'.$datarow['cellphone'].'</td>';
			$html .= '	<td class="its-td-align center">'.$datarow['phone'].'</td>';
			$html .= '</tr>';
			$i++;
		}//foreach end

		if($i==0){
			if($sc['search_text']){
				$html .= '<tr ><td class="its-td-align center" colspan="8" >"'.$sc['search_text'].'"로(으로) 검색된 회원이 없습니다.</td></tr>';
			}else{
				$html .= '<tr ><td class="its-td-align center" colspan="8" >회원이 없습니다.</td></tr>';
			}
		}

		if(!empty($html)) {
			$result = array( 'content'=>$html, 'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}else{
			$result = array( 'content'=>"", 'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}
		echo json_encode($result);
		exit;
	}


	//개인결제리스트
	public function personal(){
		$this->load->library('privatemasking');
		serviceLimit('H_FR','process');

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$aGetParam = $this->input->get();

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($aGetParam) == 0 ){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/order/personal');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$aGetParam['regist_sdate'] = $search_date['start_date'];
				$aGetParam['regist_edate'] = $search_date['end_date'];
			}
		}

		//유입매체
		$sitemarketplaceloop = sitemarketplace($aGetParam['sitemarketplace'], 'image', 'array');

		if($aGetParam['header_search_keyword']) $aGetParam['keyword'] = $aGetParam['header_search_keyword'];


		// 검색어
		if( $aGetParam['keyword'] ){
			$keyword = str_replace("'","\'",$aGetParam['keyword']);
			$where[] = "
			(
				(
					CONCAT(
						order_user_name,
						order_email,
						order_phone,
						order_cellphone,
						order_seq,
						IFNULL(userid,' ')
					) LIKE '%" . $keyword . "%'
				) OR (
					person_seq IN
					(
						SELECT person_seq FROM fm_person_cart WHERE goods_seq IN
							(
								select goods_seq from fm_goods where goods_name like '%".$keyword."%'
							)
					)
				)
			)
			";

		}

		// 주문일
		if($aGetParam['regist_sdate']){
			$where_order[] = "regist_date >= '".$aGetParam['regist_sdate']." 00:00:00'";
		}else{
			//$aGetParam['regist_date'][0] = date('Y-m-d',strtotime("-1 month"));
			//$where_order[] = "regist_date >= '".date('Y-m-d',strtotime("-1 month"))." 00:00:00'";
		}

		if($aGetParam['regist_edate']){
			$where_order[] = "regist_date <= '".$aGetParam['regist_edate']." 24:00:00'";
		}else{
			//$aGetParam['regist_date'][1] = date('Y-m-d');
			//$where_order[] = "regist_date <= '".date('Y-m-d')." 24:00:00'";
		}

		//결제 유효기간
		if($aGetParam['expiry_sdate']){
			$where_order[] = "regist_date >= '".date('Y-m-d',strtotime("-8 day", strtotime($aGetParam['expiry_sdate'])))." 00:00:00'";
		}

		if($aGetParam['expiry_edate']){
			$where_order[] = "regist_date <= '".date('Y-m-d',strtotime("-8 day", strtotime($aGetParam['expiry_edate'])))." 24:00:00'";
		}

		$this->db->order_by("person_seq","desc");

		if($where_order){
			$str_where_order = " AND " . implode(' AND ',$where_order) ;
		}

		if($where){
			$str_where = " WHERE " . implode(' AND ',$where) ;
		}

		$key = get_shop_key();
		$query = "
				select * from (
				SELECT title, order_user_name, total_price, order_seq, enuri, member_seq, order_email, order_phone, order_cellphone, person_seq, regist_date,
					(SELECT userid FROM fm_member WHERE member_seq=pr.member_seq) userid,
					(SELECT AES_DECRYPT(UNHEX(email), '{$key}') as email FROM fm_member WHERE member_seq=pr.member_seq) mbinfo_email,
					(SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=pr.member_seq) group_name,
					(select goods_name from fm_goods where goods_seq
						in (select goods_seq from fm_person_cart where person_seq = pr.person_seq) limit 1) goods_name,
					(select count(goods_seq) from fm_person_cart where person_seq = pr.person_seq) item_cnt
				FROM fm_person pr where 1=1 ".$str_where_order.") t ".$str_where. " order by person_seq desc";

		$query = $this->db->query($query,$bind);

		foreach($query->result_array() as $k => $data)
		{
			//개인정보 마스킹 표시
			$data = $this->privatemasking->masking($data, 'order');

			$no++;
			$record[$k] = $data;
			if($step_cnt[$data['step']] == 1)
			{
				$record[$k]['start'] = true;
				$ek = $k-1;
				if($ek >= 0 ){
					$record[$ek]['end'] = true;
				}
			}
		}

		if($record)
		{
			$record[$k]['end'] = true;
			foreach($record as $k => $data){
				$record[$k]['no'] = $no;
				$regist_date = $record[$k]['regist_date'];
				$record[$k]['expiry_date'] = date('Y-m-d',strtotime("+8 day", strtotime($regist_date)))." 00:00:00";
				$no--;
			}
		}

		$this->template->assign(array('sc'=>$aGetParam));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign(array('record' => $record));
		$this->template->print_("tpl");
	}



	public function pay(){
		$this->load->model('ssl');
		$this->ssl->decode();

		$auth = $this->authmodel->manager_limit_act('personal_act');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}

		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
		$this->load->library('validation');
		$this->validation->set_rules('title', '개인 결제 타이틀','trim|required|max_length[200]|xss_clean');
		$this->validation->set_rules('order_user_name', '주문자','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('order_cellphone[]', '주문자 휴대폰','trim|required|max_length[4]|xss_clean');
		$this->validation->set_rules('order_cellphone[]', '주문자 유선전화','trim|numeric|max_length[4]|xss_clean');
		$this->validation->set_rules('order_email', '이메일','trim|valid_email|max_length[100]|xss_clean');
		$this->validation->set_rules('payment[]', '결제수단','trim|required|xss_clean');


		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$this->load->model('personcartmodel');
		$cart = $this->personcartmodel->cart_list($_POST["member_seq"], '0');

		if($cart["total"] == 0){
			openDialogAlert("주문 상품이 없습니다.",400,140,'parent','');
			exit;
		}

		$total_price = (int)(str_replace(",", "", $cart["total_price"])) - $_POST['enuri'];
		if($total_price < 0){
			openDialogAlert("에누리가 주문 금액보다 클 수 없습니다.",400,140,'parent','');
			exit;
		}


		foreach($cart['list'] as $key => $data) {
			$cart_options = $data['cart_options'];
			$cart_suboptions  = $data['cart_suboptions'];
			$cart_inputs  = $data['cart_inputs'];

			foreach($cart_options as $k => $cart_option){
				list($price,$opt_reserve) = $this->goodsmodel->get_goods_option_price(
						$data['goods_seq'],
						$cart_option['option1'],
						$cart_option['option2'],
						$cart_option['option3'],
						$cart_option['option4'],
						$cart_option['option5']
						);

				// 재고 체크
				$chk = check_stock_option(
						$data['goods_seq'],
						$cart_option['option1'],
						$cart_option['option2'],
						$cart_option['option3'],
						$cart_option['option4'],
						$cart_option['option5'],
						$cart_option['ea'],
						$cfg['order']
						);


				if( !$chk ){
					openDialogAlert($data['goods_name'].'의 재고가 없습니다.',400,140,'parent','');
					exit;
				}
			}

			/* 개인결제 추가구성 필수 옵션 체크 추가 2014-12-29 leewh */
			if ($data['cnt_sub_required'] > 0 && count($cart_suboptions) < 1) {
				openDialogAlert($data['goods_name'].' : '.$data['suboption_title_required'].' 옵션은 필수입니다.',400,140,'parent','');
				exit;
			}
		}

		/* 개인결제 저장 */
		//$this->db->trans_begin();
		$person_seq = $this->ordermodel->insert_order_person();

		for($i=0; $i<count($cart["list"]); $i++){
			$this->db->query("update fm_person_cart set person_seq = '".$person_seq."' where cart_seq = '".$cart["list"][$i]["cart_seq"]."'");
		}

		//개인결제 SMS 발송
		if($_POST['send_sms']=='Y'){
			$cellphone	= preg_replace('/[^0-9]/', '', $_POST['cellphone']);
			if	($cellphone)
			{
				$str = trim($_POST["msg"]);
				$str = str_replace("[주문자]", $_POST["order_user_name"], $str);
				$params['msg'] = $str;
				$commonSmsData['member']['phone'] = $_POST['cellphone'];
				$commonSmsData['member']['params'] = $params;

				$result = commonSendSMS($commonSmsData);

				//sendSMS_Msg($str, $cellphone);
			}
		}

		openDialogAlert("개인결제가 등록되었습니다.",400,140,'parent',"top.location.replace('/admin/order/personal');");

	}


	public function person_cart(){

		$this->load->library('upload');
		$this->load->model('goodsmodel');
		$this->load->model('membermodel');

		$member_seq		= $_GET['member_seq'];
		$pre_cart_seqs	= "";
		$mode			= "cart";
		$goods_seq		= (int) $_GET['goodsSeq'];
		$cfg['order']	= config_load('order');
		$goods			= $this->goodsmodel->get_goods($goods_seq);
		$inputs			= $this->goodsmodel->get_goods_input($goods_seq);
		$options		= $this->goodsmodel->get_goods_default_option($goods_seq);
		$suboptions		= $this->goodsmodel->get_goods_suboption_required($goods_seq);
		$member_data	= $this->membermodel->get_member_data($member_seq);

		// 상품상태 체크
		if($goods['goods_status'] != 'normal'){
			if		($goods['goods_status'] == 'unsold')	$err_msg	= '은 판매중지 상품입니다.';
			else											$err_msg	= '은 품절된 상품입니다.';
			alert($goods['goods_name'].$err_msg);
			exit;
		}
		// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
		if( $goods['event']['event_goodsStatus'] === true ){
			$err_msg = "단독이벤트 기간에만 구매가 가능한 상품입니다.";
			alert($err_msg);
			exit;
		}

		// 가격대체문구 비회원
		if	($goods['string_price_use'] && !$member_data['member_seq']){
			$price_msg = '비회원은 ' . $goods['string_price'];
		}
		// 가격대체문구 회원
		if	($goods['member_string_price_use'] && $member_data['group_seq'] == 1){
			$price_msg = '일반회원은 ' . $goods['member_string_price'];
		}
		// 가격대체문구 일반이상회원
		if	($goods['allmember_string_price_use'] && $member_data['group_seq'] > 1){
			$price_msg = '모든회원은 ' . $goods['allmember_string_price'];
		}

		// 가격 대체문구 상품 강제 구매 :: 2015-07-28 lwh
		if($price_msg && !$_GET['price_confirm']){
			echo("<script>top.openDialogConfirm('".$price_msg."<br/><br/>쇼핑몰 운영 정책상 해당 소비자가 구매할 수 없는 상품입니다.<br/>그럼에도 불구하고 구매할 수 있도록 하시겠습니까?<br/>','430','200',function(){location.href='./person_cart?goodsSeq=".$_GET['goodsSeq']."&member_seq=".$_GET['member_seq']."&price_confirm=Y';},function(){});</script>");
			exit;
		}

		// 최소구매 수량에 따른 구매 수량 변경
		$order_ea		= 1;
		if	($goods['min_purchase_ea'] > 0)	$order_ea	= $goods['min_purchase_ea'];

		foreach($options as $o => $opt){
			// 필수 옵션 재고 체크
			$chk	= check_stock_option($goods_seq, $opt['option1'], $opt['option2'],
					$opt['option3'], $opt['option4'], $opt['option5'],
					$order_ea, $cfg['order'], 'view_stock');
			if	( $chk['stock'] < 0 )	continue;

			$option	= $opt;
			break;
		}
		if	($option){
			unset($options);
			$options[0]	= $option;
		}else{
			$err_msg	= '구매 가능한 필수옵션이 없습니다.';
			alert($err_msg);
			exit;
		}

		// 필수 추가구성옵션 재고 체크
		if	($suboptions)foreach($suboptions as $sub_title => $subArr){
			$chk	= false;
			if	($subArr)foreach($subArr as $s => $sub){
				$chk	= check_stock_suboption($goods_seq, $sub['suboption_title'],
						$sub['suboption'], 1,
						$cfg['order'], 'view_stock');
				if	( $chk['stock'] < 0 )	continue;

				$chk	= true;
				$reSuboptions[]	= $sub;
				break;
			}
			if	(!$chk){
				$err_msg	= '필수 추가구성옵션' . $sub_title . '를 구매할 수 없습니다.';
				alert($err_msg);
				exit;
			}
		}
		$suboptions	= $reSuboptions;

		$session_id = session_id();

		$insert_data['goods_seq'] 	= $goods_seq;
		$insert_data['session_id'] 	= $session_id;
		$insert_data['member_seq'] 	= $member_seq;
		$insert_data['distribution'] = $mode;
		$insert_data['regist_date '] = $insert_data['update_date'] = date('Y-m-d H:i:s',time());
		$insert_data['fblike'] = 'N';

		$this->db->insert('fm_person_cart', $insert_data);
		$cart_seq = $this->db->insert_id();

		unset($insert_data);
		for($i=0;$i<5;$i++){
			if( !isset($options[0]["opts"][$i]) || !$options[0]["option_divide_title"][$i] ) $options[0]["opts"][$i] = "";
			if( !isset($options[0]["opts"][$i]) || !$options[0]["option_divide_title"][$i] ) $options[0]["option_divide_title"][$i] = null;
		}

		$insert_data['option1']		= $options[0]["opts"][0];
		$insert_data['title1']		= $options[0]["option_divide_title"][0];
		$insert_data['option2']		= $options[0]["opts"][1];
		$insert_data['title2']		= $options[0]["option_divide_title"][1];
		$insert_data['option3']		= $options[0]["opts"][2];
		$insert_data['title3']		= $options[0]["option_divide_title"][2];
		$insert_data['option4']		= $options[0]["opts"][3];
		$insert_data['title4']		= $options[0]["option_divide_title"][3];
		$insert_data['option5']		= $options[0]["opts"][4];
		$insert_data['title5']		= $options[0]["option_divide_title"][4];
		$insert_data['ea'] 			= $order_ea;
		$insert_data['cart_seq']	= $cart_seq;
		$insert_data['promotion_code_seq']	= $this->session->userdata('cart_promotioncodeseq_'.session_id());
		$insert_data['promotion_code_serialnumber']	= $this->session->userdata('cart_promotioncode_'.session_id());
		// 개인결제시 개별배송여부 체크 :: 2015-02-02 lwh
		$goodsinfo	= $this->goodsmodel->get_goods($goods_seq);
		if($goodsinfo['shipping_policy'] == 'shop')
			$insert_data['shipping_method']	= 'delivery';
			else	$insert_data['shipping_method']	= 'each_delivery';
			$this->db->insert('fm_person_cart_option', $insert_data);
			$cart_option_seq = $this->db->insert_id();

			// 입력옵션이 있는 빈 값으로 입력옵션을 추가
			unset($insert_data);
			krsort($inputs);
			if	($inputs)foreach($inputs as $k => $int){
				$insert_data['cart_option_seq']	= $cart_option_seq;
				$insert_data['cart_seq']		= $cart_seq;
				$insert_data['type']			= $int['input_form'];
				$insert_data['input_title']		= $int['input_name'];
				$insert_data['input_value']		= '';
				$this->db->insert('fm_person_cart_input', $insert_data);
			}

			// 필수 추가구성옵션 추가
			unset($insert_data);
			if	($suboptions)foreach($suboptions as $k => $sub){
				$insert_data['cart_option_seq']	= $cart_option_seq;
				$insert_data['cart_seq']		= $cart_seq;
				$insert_data['suboption_title']	= $sub['suboption_title'];
				$insert_data['suboption']		= $sub['suboption'];
				$insert_data['ea']				= 1;
				$this->db->insert('fm_person_cart_suboption', $insert_data);
			}

			unset($insert_data);
			$config['upload_path'] = ROOTPATH."data/order/";
			$config['allowed_types'] = implode('|', array('jpg','jpeg','png','gif','bmp','tif','pic'));
			$config['max_size']	= $this->config_system['uploadLimit'];
			$aGetinputsValue = $this->input->get('inputsValue');
			if ($aGetinputsValue) {
				$fileNum = 0;
				$textNum = 0;
				foreach($inputs as $key_input => $data_input){
					if($data_input['input_form']=='file'){
						$this->upload->initialize($config, true);
						if ($this->upload->do_upload('inputsValue', $fileNum)) {
							$insert_data['type'] = 'file';
							$insert_data['input_title'] = $data_input['input_name'];
							$insert_data['input_value'] = $this->upload->file_name;
							$fileNum++;
						}
					}else{
						if ($aGetinputsValue[$textNum]) {
							$insert_data['type'] = 'text';
							$insert_data['input_title'] = $data_input['input_name'];
							$insert_data['input_value'] = $aGetinputsValue[$textNum];
							$textNum++;
						}
					}

					if ($insert_data['input_value']) {
						$insert_data['cart_seq'] = $cart_seq;
						$insert_data['cart_option_seq'] = $cart_option_seq;
						$this->db->insert('fm_person_cart_input', $insert_data);
					}
				}
			}

			unset($insert_data);
			if( isset($_GET['suboption']) ){
				foreach($_GET['suboption'] as $k1 => $suboption){
					$insert_data['ea']				= $_GET['suboptionEa'][$k1];
					$insert_data['suboption_title']	= $_GET['suboptionTitle'][$k1];
					$insert_data['suboption']		= $suboption;
					$insert_data['cart_seq'] 		= $cart_seq;
					$this->db->insert('fm_person_cart_suboption', $insert_data);
				}
			}

			/* 상품분석 수집 */
			$this->load->model('goodslog');
			$this->goodslog->add('admin',$goods_seq);

			echo("<script>top.cart('person');</script>");
	}


	public function calculate(){

		$this->load->helper('shipping');
		$this->load->helper('order');
		if($_GET["member_seq"] != ""){
			$member_seq = $_GET["member_seq"];
		}

		if($_GET["enuri"] != ""){
			$enuri = $_GET["enuri"];
		}

		$this->load->model('personcartmodel');
		$this->load->model('couponmodel');
		$this->load->model('membermodel');
		$this->load->model('providermodel');

		$this->load->model('configsalemodel');

		$sc['type'] = 'mobile';
		$systemmobiles = $this->configsalemodel->lists($sc);
		$sc['type'] = 'fblike';
		$systemfblike = $this->configsalemodel->lists($sc);

		$members = "";
		$err_reserve = "";
		$total_price = 0;
		$goods_weight = 0;
		$sum_goods_price = 0;
		$total_coupon_sale = 0;
		$total_fblike_sale = 0;
		$total_mobile_sale = 0;
		$total_goods_price = 0;
		$total_member_sale = 0;
		$cfg['order'] = config_load('order');
		$international_shipping_price = 0;

		// 입점사별 상품구매금액 합계
		$this->provider_sum_goods_price = array();

		// 입점사별 상품 무게 합계
		$this->provider_goods_weight = array();

		// 입점사별 해외배송비 합계
		$this->provider_international_shipping_price = array();

		// 입점사별 기본배송비
		$this->provider_shipping_cost = array();

		echo "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
		$scripts[] = "<script type='text/javascript'>";
		$scripts[] = "$(function() {";


		$shipping = use_shipping_method();
		$this->shipping_order = $shipping;

		if($shipping) $international_shipping = $shipping[1][$_POST['shipping_method_international']];

		//if($this->userInfo['member_seq']) $members = $this->membermodel->get_member_data($this->userInfo['member_seq']);
		echo $person_seq;
		$cart = $this->personcartmodel->cart_list($member_seq, '0');

		$this->cart = $cart;

		### TAX : EXEMPT
		$exempt_price	= $cart['exempt_shipping'] + $cart['exempt_price'];
		if($cart['taxtype']=='mix'){
			$sum_price		= $cart['total_price']-$exempt_price;
			$tax_price		= round($sum_price/1.1);
			$cart['comm_tax_mny']	= $tax_price;
			$cart['comm_vat_mny']	= $sum_price - $tax_price;
			$cart['comm_free_mny']	= $exempt_price;
		}else if($cart['taxtype']=='exempt'){
			$cart['comm_tax_mny']	= 0;
			$cart['comm_vat_mny']	= 0;
			$cart['comm_free_mny']	= $cart['total_price'];
		}else{
			$tax_price		= round($cart['total_price']/1.1);
			$cart['comm_tax_mny']	= $tax_price;
			$cart['comm_vat_mny']	= $cart['total_price'] - $tax_price;
			$cart['comm_free_mny']	= 0;
		}
		$this->freeprice		= $cart['comm_free_mny'];
		$this->comm_tax_mny		= $cart['comm_tax_mny'];
		$this->comm_vat_mny		= $cart['comm_vat_mny'];
		//echo $cart['comm_tax_mny']." : ".$cart['comm_vat_mny']." : ".$cart['comm_free_mny'];

		foreach($cart['list'] as $key => $data){
			$category = array();
			$tmp = $this->goodsmodel->get_goods_category($data['goods_seq']);
			foreach($tmp as $row) $category[] = $row['category_code'];
			$cart_sale			= get_cutting_price($data['tot_price']);
			$sum_goods_price	+= get_cutting_price($data['tot_price']);

			// 입점사별 상품구매금액 합산
			$this->provider_sum_goods_price[$data['provider_seq']] += get_cutting_price($data['tot_price']);

			$cart_options = $data['cart_options'];
			$cart_suboptions  = $data['cart_suboptions'];
			$cart_inputs  = $data['cart_inputs'];

			$coupon_goods_sale = 0;
			$promotion_goods_sale = 0;
			$fblike_goods_sale = 0;
			$mobile_goods_sale = 0;
			$member_sale = 0;
			$reserve = 0;
			$cart_reserve = 0;
			$goods_sale = 0;
			$data['reserve'] = 0;

			// 구매수량 체크
			$sum_opt_ea = $data['ea'] - $data['sub_ea'];
			$data_goods = $this->goodsmodel->get_goods($data['goods_seq']);
			if($data_goods['min_purchase_ea'] && $data_goods['min_purchase_ea'] > $sum_opt_ea){
				openDialogAlert($data['goods_name'].'은 '.$data_goods['min_purchase_ea'].'개 이상 구매하셔야 합니다.',400,140,'parent','');
				exit;
			}
			if($data_goods['max_purchase_ea'] && $data_goods['max_purchase_ea'] < $sum_opt_ea){
				openDialogAlert($data['goods_name'].'은 '.$data_goods['max_purchase_ea'].'개 이상 구매하실 수 없습니다.',400,140,'parent','');
				exit;
			}

			foreach($cart_options as $k => $cart_option){
				list($price,$opt_reserve) = $this->goodsmodel->get_goods_option_price(
						$data['goods_seq'],
						$cart_option['option1'],
						$cart_option['option2'],
						$cart_option['option3'],
						$cart_option['option4'],
						$cart_option['option5']
						);

				// 재고 체크
				$chk = check_stock_option(
						$data['goods_seq'],
						$cart_option['option1'],
						$cart_option['option2'],
						$cart_option['option3'],
						$cart_option['option4'],
						$cart_option['option5'],
						$cart_option['ea'],
						$cfg['order']
						);

				if( !$chk ){
					openDialogAlert($data['goods_name'].'의 재고가 없습니다.',400,140,'parent','');
					exit;
				}


				// 적립 계산
				$opt_price = $cart_option['price'];
				if( $cart_option['member_sale'] ){
					$opt_price -= $cart_option['member_sale'];
				}
				if( $cart_option['coupon_sale'] ){
					$opt_price -= $cart_option['coupon_sale'] / $cart_option['ea'];
				}
				if( $cart_option['fblike_sale'] ){
					$opt_price -= $cart_option['fblike_sale'] / $cart_option['ea'];
				}
				if( $cart_option['mobile_sale'] ){
					$opt_price -= $cart_option['mobile_sale'] / $cart_option['ea'];
				}

				// 구매적립
				$order_reserve = 0;
				$order_reserve = $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'],$opt_price,$cfg_reserve['default_reserve_percent'],$cart_option['reserve_rate'],$cart_option['reserve_unit'],$cart_option['reserve']);

				// 이벤트 마일리지
				$event_reserve = $cart_option['r_event']['event_reserve_unit'];

				// 회원추가적립
				$member_reserve = 0;
				$member_reserve = $this->membermodel->get_group_addreseve($this->userInfo['member_seq'],$opt_price,$cart['total'],$cart_option['goods_seq'],$category);

				// 좋아요적립
				$opt_fblike_sale_emoney = 0;
				if($data['fblike'] == 'Y'){//facebook like %할인, 추가적립
					foreach($systemfblike['result'] as $fblike => $systemfblike_price) {
						if($systemfblike_price['price1']<= $cart['total'] && $systemfblike_price['price2'] >= $cart['total']){
							$opt_fblike_sale_emoney = get_cutting_price($systemfblike_price['sale_emoney'] * $opt_price / 100);  // 좋아요 적립
							break;
						}//endif
					}//end foreach
				}

				// 모바일적립
				$opt_mobile_sale_emoney = 0;
				if($this->mobileMode  || $this->_is_mobile_agent) {//mobile 접속시  %할인, 추가적립
					foreach($systemmobiles['result'] as $fblike => $systemmobiles_price) {
						if($systemmobiles_price['price1']<= $cart['total'] && $systemmobiles_price['price2'] >= $cart['total']){
							$opt_mobile_sale_emoney = get_cutting_price($systemmobiles_price['sale_emoney'] * $opt_price / 100); // 모바일 적립
							break;
						}//endif
					}//end foreach
				}
				$cart_option['reserve'] = get_cutting_price($order_reserve) + get_cutting_price($member_reserve) + get_cutting_price($opt_fblike_sale_emoney) + get_cutting_price($opt_mobile_sale_emoney) + get_cutting_price($event_reserve);

				$reserve_log = '';
				if( $order_reserve > 0 ) $reserve_log = ($reserve_log?' / ':'').'구매  : '.(is_numeric($order_reserve)?get_currency_price($order_reserve):$order_reserve);
				if( $event_reserve > 0 ) $reserve_log .= ($reserve_log?' / ':'').'이벤트  : '.(is_numeric($event_reserve)?get_currency_price($event_reserve):$event_reserve);
				if( $member_reserve > 0 ) $reserve_log .= ($reserve_log?' / ':'').'회원  : '.(is_numeric($member_reserve)?get_currency_price($member_reserve):$member_reserve);
				if( $opt_fblike_sale_emoney > 0 ) $reserve_log .= ($reserve_log?' / ':'').'좋아요  : '.(is_numeric($opt_fblike_sale_emoney)?get_currency_price($opt_fblike_sale_emoney):$opt_fblike_sale_emoney);
				if( $opt_mobile_sale_emoney > 0 ) $reserve_log .= ($reserve_log?' / ':'').'모바일  : '.(is_numeric($opt_mobile_sale_emoney)?get_currency_price($opt_mobile_sale_emoney):$opt_mobile_sale_emoney);
				$cart_option['reserve_log'] = $reserve_log;

				$data['reserve'] += $cart_option['reserve']*$cart_option['ea'];
				$cart_options[$k] = $cart_option;
			}
			if($cart_suboptions){
				foreach($cart_suboptions as $k => $cart_suboption){
					// 재고 체크
					$chk = check_stock_suboption(
							$data['goods_seq'],
							$cart_suboption['suboption_title'],
							$cart_suboption['suboption'],
							$cart_suboption['ea'],
							$cfg['order']
							);
					if( !$chk ){
						openDialogAlert($data['goods_name'].'의 재고가 없습니다.',400,140,'parent','');
						exit;
					}
				}
			}

			$cart['list'][$key]['cart_options'] = $cart_options;
			$cart['list'][$key]['cart_suboptions'] = $cart_suboptions;
			$cart['list'][$key]['cart_inputs'] = $cart_inputs;

			if( $data['shipping_weight_policy'] == "shop" ){
				$goods_weight += $international_shipping['defaultGoodsWeight'];

				// 입점사별 상품 무게 합산
				$this->provider_goods_weight[$data['provider_seq']] += $international_shipping['defaultGoodsWeight'];
			}else{
				$goods_weight += $data['goods_weight'];

				// 입점사별 상품 무게 합산
				$this->provider_goods_weight[$data['provider_seq']] += $data['goods_weight'];
			}

			/* 상품 가격 합계  */
			$total_goods_price += get_cutting_price($cart_sale);
		}

		/* 해외 배송비 */
		/*
		 $start = 0;
		 if($international_shipping['goodsWeight']) foreach($international_shipping['goodsWeight'] as $key => $weight){
		 $end = $weight;
		 if($start < $goods_weight && $end >= $goods_weight ){
		 $goods_row = $key;
		 }
		 $start = $weight;
		 }
		 $cost_key = $_POST['region'] + (count($international_shipping['region'])*$goods_row);
		 $international_shipping_price = (int) $international_shipping['deliveryCost'][$cost_key];
		 */

		foreach($this->provider_goods_weight as $provider_seq => $value){
			$start = 0;
			if($international_shipping['goodsWeight']){
				foreach($international_shipping['goodsWeight'] as $key => $weight){
					if($start < $this->provider_goods_weight[$provider_seq]){
						$goods_row = $key;
						$start = $weight;
					}else{
						break;
					}
				}
			}
			$cost_key = $_POST['region'] + (count($international_shipping['region'])*$goods_row);
			$this->provider_international_shipping_price[$provider_seq] = get_cutting_price($international_shipping['deliveryCost'][$cost_key]);
		}

		$_POST['shipping_method'] = "delivery";
		/* 배송비 */
		$this->shipping_cost = 0;//기본배송비체크
		if( $_POST['international'] == 0 ){
			if( $_POST['shipping_method'] == 'delivery' ){
				// 입점사별 지역별 추가 배송비
				$total_shipping_price = 0;

				foreach($cart['provider_shipping_policy'] as $provider_seq=>$door2door){
					$addDeliveryCost = 0;
					if($door2door['sigungu']) foreach($door2door['sigungu'] as $sigungu_key => $sigungu){
						if(preg_match('/'.$sigungu.'/',$_POST['recipient_address'])){
							$addDeliveryCost += $door2door['addDeliveryCost'][$sigungu_key];
						}
					}
					$cart['provider_shipping_price'][$provider_seq]['shop'] += get_cutting_price($addDeliveryCost) * $cart['provider_box_ea'][$provider_seq];
				}

				foreach($cart['provider_shipping_price'] as $provider_seq=>$data){
					$total_shipping_price += array_sum($data);
					$this->provider_shipping_cost[$provider_seq] = get_cutting_price($data['shop']);
				}
				$this->shipping_cost = array_sum($this->provider_shipping_cost);

				/*
				 if($shipping[0][0]['code'] == 'delivery'){
				 $door2door = $shipping[0][0];
				 $addDeliveryCost = 0;
				 if($door2door['sigungu']) foreach($door2door['sigungu'] as $sigungu_key => $sigungu){
				 if(preg_match('/'.$sigungu.'/',$_POST['recipient_address'])){
				 $addDeliveryCost += $door2door['addDeliveryCost'][$sigungu_key];
				 }
				 }
				 $cart['shipping_price']['shop'] += (int) $addDeliveryCost * $cart['box_ea'];
				 }
				 $total_shipping_price = array_sum($cart['shipping_price']);
				 $this->shipping_cost = (int) $cart['shipping_price']['shop'];
				 */

			}else{
				$total_shipping_price = 0;
			}
		}else{
			//$total_shipping_price = $international_shipping_price;
			$total_shipping_price = array_sum($this->provider_international_shipping_price);
			$this->shipping_cost = get_cutting_price($total_shipping_price);
			foreach($this->provider_international_shipping_price as $provider_seq => $value){
				$this->provider_shipping_cost[$provider_seq] = $value;
			}
		}

		if(isset($enuri) && $enuri > 0){
			$enuri = $enuri;
		}else{
			$enuri = 0;
		}

		/* 총 결제금액 */
		$settle_price = $total_goods_price + $total_shipping_price - $enuri;

		/* 마일리지 사용할 수 있는 금액 계산*/
		if( $members && isset($_POST['emoney']) && $_POST['emoney'] > 0 ){
			$reserve_use = true;
			$reserves = config_load('reserve');

			if( $_POST['emoney'] > $members['emoney'] ){
				$reserve_use = false;
				$err_reserve = get_currency_price( $members['emoney'] ,3)." 이상 사용하실 수 없습니다.";
			}

			if($reserves['emoney_use_limit'] > $members['emoney']){
				$reserve_use = false;
				$err_reserve = get_currency_price($reserves['emoney_use_limit'],3)." 이상 적립하여야 합니다.";
			}

			if($reserves['emoney_price_limit'] > $sum_goods_price){
				$reserve_use = false;
				$err_reserve = "상품을 ".get_currency_price($reserves['emoney_price_limit'],3)." 이상 사야 합니다.";
			}

			if($members['emoney'] >= $reserves['emoney_use_limit'] ){
				if($reserves['max_emoney_policy'] == 'percent_limit' && $reserves['max_emoney_percent']){
					$max_emoney = get_cutting_price($sum_goods_price * $reserves['max_emoney_percent'] / 100);
				}else if($reserves['max_emoney_policy'] == 'price_limit' && $reserves['max_emoney']){
					$max_emoney = get_cutting_price($reserves['max_emoney']);
				}

				if($max_emoney > $settle_price) $max_emoney = $settle_price;

				if($_POST['emoney'] < $reserves['min_emoney']){
					$reserve_use = false;
					$err_reserve = "마일리지은  최소 ".get_currency_price($reserves['min_emoney'],3)." 부터 사용가능 합니다.";
				}
				if($_POST['emoney'] > $max_emoney && $reserves['max_emoney_policy'] != 'unlimit'){
					$reserve_use = false;
					$err_reserve = "마일리지은  최대 ".get_currency_price($max_emoney,3)." 까지 사용가능 합니다.";
				}
			}

			if($err_reserve){
				echo '<script>$("input[name=\'emoney\']",parent.document).val(0);</script>';
				openDialogAlert($err_reserve,400,140,'parent',"");
				exit;
			}

			$settle_price -= $_POST['emoney'];
		}

		$this->sum_goods_price	= get_cutting_price($sum_goods_price);
		$this->settle_price		= get_cutting_price($settle_price);
		$this->cart_lists		= $cart['list'];

		if( $this->settle_price >= 50000 ) $scripts[] = '$("#escrow").hide();';
		else  $scripts[] = '$("#escrow").show();';

		//$sum_goods_price

		$scripts[] = '$(".settle_price",parent.document).html("'.get_currency_price($settle_price).'");';
		$scripts[] = '$("span#total_goods_price",parent.document).html("'.get_currency_price($total_goods_price).'");';

		$scripts[] = '$("span#total_coupon_sale",parent.document).html("'.get_currency_price($total_coupon_sale).'");';

		$scripts[] = '$("span#total_fblike_sale",parent.document).html("'.get_currency_price($total_fblike_sale).'");';
		$scripts[] = '$("span#total_mobile_sale",parent.document).html("'.get_currency_price($total_mobile_sale).'");';

		$scripts[] = '$("span#total_shipping_price",parent.document).html("'.get_currency_price($total_shipping_price).'");';

		//@프로모션코드
		if($this->session->userdata('cart_promotioncode_'.session_id())){
			$scripts[] = '$("#cartpromotioncode",parent.document).val("'.$this->session->userdata('cart_promotioncode_'.session_id()).'");';
			$scripts[] = '$(".cartpromotioncodedellay",parent.document).show();';
		}else{
			$scripts[] = '$("#cartpromotioncode",parent.document).val("");';
			$scripts[] = '$(".cartpromotioncodedellay",parent.document).hide();';
		}
		$scripts[] = '$("span#total_promotion_goods_sale",parent.document).html("'.get_currency_price($total_promotion_goods_sale).'");';

		if(isset($this->shipping_promotion_code_sale)){//배송비프로모션선택시
			$scripts[] = '$(".shipping_promotioncode_salelay",parent.document).show();';
			$scripts[] = '$("span#shipping_promotioncode_sale",parent.document).html("'.get_currency_price($this->shipping_promotion_code_sale).'");';
		}else{
			$scripts[] = '$(".shipping_promotioncode_salelay",parent.document).hide();';
			$scripts[] = '$("span#shipping_promotioncode_sale",parent.document).html("");';
		}

		if(isset($this->shipping_coupon_sale)){//배송비쿠폰선택시
			$scripts[] = '$("span#coupon_shipping_price",parent.document).html("- 배송비할인 쿠폰 '.get_currency_price($this->shipping_coupon_sale).'");';
		}else{
			$scripts[] = '$("span#coupon_shipping_price",parent.document).html("");';
			$scripts[] = '$("#download_seq",parent.document).val("");';
			$scripts[] = '$("#shipping_coupon_sale",parent.document).val("");';
		}

		$scripts[] = "});";
		$scripts[] = "</script>";

		foreach($scripts as $script){
			echo $script."\n";
		}

	}

	public function person_view(){

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$person_seq = $_GET["person_seq"];

		$key = get_shop_key();
		$query = "
				select * from (
				SELECT
					title, order_user_name, total_price, order_seq, enuri, member_seq, order_email, order_phone, order_cellphone, person_seq,
					pay_type,
					(SELECT userid FROM fm_member WHERE member_seq=pr.member_seq) userid,
					(SELECT AES_DECRYPT(UNHEX(email), '{$key}') as email FROM fm_member WHERE member_seq=pr.member_seq) mbinfo_email,
					(SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=pr.member_seq) group_name,
					(select goods_name from fm_goods where goods_seq
						in (select goods_seq from fm_person_cart where person_seq = pr.person_seq) limit 1) goods_name,
					(select count(goods_seq) from fm_person_cart where person_seq = pr.person_seq) item_cnt
				FROM fm_person pr where person_seq = '".$person_seq."') t order by person_seq desc
		";

		$query = $this->db->query($query);
		$list = $query->row_array();

		$this->load->model('personcartmodel');
		$cart = $this->personcartmodel->catalog($list["member_seq"], $list["person_seq"]);

		foreach($cart['list'] as $key => $data){
			// 해당 상품의 전체 카테고리
			$category	= array();
			$tmp		= $this->goodsmodel->get_goods_category($data['goods_seq']);
			foreach($tmp as $row)	$category[]		= $row['category_code'];

			//----> sale library 적용
			unset($param, $sales);
			$param['option_type']				= 'option';
			$param['consumer_price']			= $data['consumer_price'];
			$param['price']						= $data['org_price'];
			$param['sale_price']				= $data['price'];
			$param['ea']						= $data['ea'];
			$param['goods_ea']					= $cart['data_goods'][$data['goods_seq']]['ea'];
			$param['category_code']				= $category;
			$param['goods_seq']					= $data['goods_seq'];
			$param['goods']						= $data;
			$this->sale->set_init($param);
			$sales								= $this->sale->calculate_sale_price('order');
			$cart['list'][$key]['sales']		= $sales;

			foreach($data['cart_suboptions'] as $sub_k=> $subdata){

				//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
				unset($param, $sales);
				$param['option_type']			= 'suboption';
				$param['sub_sale']				= $subdata['sub_sale'];
				$param['consumer_price']		= $subdata['consumer_price'];
				$param['price']					= $subdata['price'];
				$param['sale_price']			= $subdata['price'];
				$param['ea']					= $subdata['ea'];
				$param['goods_ea']				= '';
				$param['category_code']			= $category;
				$param['goods_seq']				= $data['goods_seq'];
				$param['goods']					= $data;
				$this->sale->set_init($param);
				$sales								= $this->sale->calculate_sale_price('order');

				$cart['list'][$key]['cart_suboptions'][$sub_k]['sales']		= $sales;
			}
		}

		# 개인결제 완료 시
		if($list["order_seq"] != ""){

			$query = "select * from fm_order where order_seq = '".$list["order_seq"]."'";

			$query = $this->db->query($query);
			$orderData = $query->row_array();
			$orderData['mpayment'] = $this->arr_payment[$orderData['payment']];
		}

		$arr_pay_type = explode('|',$list[pay_type]);
		foreach($arr_pay_type as $pay_type){
			$pay_types[] = $this->arr_payment[$pay_type];
		}

		if($orderData['settleprice'] == ""){
			$orderData['settleprice'] = $list["total_price"] - $list["enuri"];
		}


		// ### NEW 배송 그룹 정보 추출 ### :: START -> shipping library 계산
		$this->load->library('shipping');
		if($_POST['nation'])	$ship_ini['nation']	= $_POST['nation'];
		$ini_info				= $this->shipping->set_ini($ship_ini);
		$shipping_group_list	= $this->shipping->get_shipping_groupping($cart['list']);

		// 상세 배송비 금액 추출
		$shipping_cost_detail	= $shipping_group_list['shipping_cost_detail'];
		unset($shipping_group_list['shipping_cost_detail']);

		// 전체 배송비 금액 추출
		$total_shipping_price	= $shipping_group_list['total_shipping_price'];
		unset($shipping_group_list['total_shipping_price']);

		// 최종 결제 금액 재계산
		$cart['total_price'] = $cart['total_price'] + $total_shipping_price;

		//개인정보 마스킹 표시
		$this->load->library('privatemasking');
		$list 		= $this->privatemasking->masking($list, 'order');
		$orderData 	= $this->privatemasking->masking($orderData, 'order');

		// 신)배송비 관련 변수 정의
		$this->template->assign(array(
						'ship_gl_arr'=>$ship_gl_arr,
						'ship_gl_list'=>$ship_gl_list
		)); // 국가목록
		$this->template->assign('ini_info',$ini_info); // 배송ini 설정정보
		$this->template->assign('shipping_group_list',$shipping_group_list); // 배송그룹LIST
		$this->template->assign('shipping_cost_detail',$shipping_cost_detail); // 배송비 상세
		$this->template->assign('total_shipping_price',$total_shipping_price); // 전체 배송비

		$this->template->assign('firstmallcartid',session_id());
		$this->template->assign(array('cart_table'=>$_GET["cart_table"]));
		//$this->template->assign('total',$cart['total']);
		//$this->template->assign('shipping_price',$cart['shipping_price']);
		//$this->template->assign('shipping_company_cnt',$cart['shipping_company_cnt']);
		//$this->template->assign('provider_shipping_policy',$cart['provider_shipping_policy']);
		//$this->template->assign('provider_shipping_price',$cart['provider_shipping_price']);
		//$this->template->assign('shop_shipping_policy',$cart['shop_shipping_policy']);

		$this->template->assign('total_price',$cart['total_price']);
		$this->template->assign('total_reserve',$cart['total_reserve']);
		$this->template->assign('total_point',$cart['total_point']);
		$this->template->assign('member_seq',$this->userInfo['member_seq']);
		$this->template->assign('pay_types',$pay_types);
		//$this->template->assign('list',$cart['list']);
		$this->template->assign(array('record' => $list));
		$this->template->assign(array('orderData' => $orderData));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	public function personal_del(){

		if( !isset($_POST['person_seq']) ){
			openDialogAlert("삭제할 상품이 없습니다.",400,140,'parent',"");
			exit;
		}

		for($i=0; $i<count($_POST['person_seq']); $i++){

			$query = "
					select cart_seq from fm_person_cart where person_seq = '".$_POST['person_seq'][$i]."'
			";
			$query = $this->db->query($query);
			$cart = $query->row_array();

			$this->db->query("delete from fm_person_cart_option where cart_seq = '".$cart['cart_seq']."'");
			$this->db->query("delete from fm_person_cart_input where cart_seq = '".$cart['cart_seq']."'");
			$this->db->query("delete from fm_person_cart_suboption where cart_seq = '".$cart['cart_seq']."'");
			$this->db->query("delete from fm_person_cart where cart_seq = '".$cart['cart_seq']."'");
			$this->db->query("delete from fm_person_cart where cart_seq = '".$cart['cart_seq']."'");
			$this->db->query("delete from fm_person where person_seq = '".$_POST['person_seq'][$i]."'");

		}

		openDialogAlert("삭제되었습니다.",400,140,'parent',"parent.document.location.reload();");
	}

	public function catalog_ajax(){
		$aPostParams = $this->input->post();

		// validation
		if ($aPostParams) {
			$this->validation->set_data($aPostParams);
			$this->validation->set_rules('header_search_keyword', '검색어', 'trim|string|xss_clean');
			$this->validation->set_rules('header_search_type', '검색선택', 'trim|string|xss_clean');
			$this->validation->set_rules('pagemode', '모드', 'trim|string|xss_clean');
			$this->validation->set_rules('page', '페이지', 'trim|numeric|xss_clean');
			$this->validation->set_rules('shipping_provider_seq', '배송주체', 'trim|numeric|xss_clean');
			$this->validation->set_rules('detailmode', '상세', 'trim|string|xss_clean');
			$this->validation->set_rules('last_step_cnt', '주문수', 'trim|string|xss_clean');
			$this->validation->set_rules('last_step_settleprice', '금액', 'trim|string|xss_clean');
			$this->validation->set_rules('searchTime', '검색일시', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value'],422);
			}
		}

		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('ordermodel');
		$this->load->library('privatemasking');
		//$this->load->model('openmarketmodel');
		$_PARAM			= $_POST;//$_GET//$_POST
		$pagemode		= $_POST['pagemode'];
		unset($_PARAM['stepBox']);

		$this->load->library('Connector');
		$MarketLinkage = config_load('MarketLinkage');
		$connector	= $this->connector::getInstance();

		//마켓연동 연동 서비스 사용유무
		if ($connector->isConnectorUse()){
			$marketList		= $connector->getAllMarkets(true);
			
			//샵링커 추가 2017-09-27 jhs
			$this->load->model('connectormodel');
			$shopLinkermarketList = array();
			$shopLinkerUseMarketList = $this->connectormodel->getLinkageMarketGroup();
			foreach($shopLinkerUseMarketList as $marketInfo){
				$shopLinkermarketList[$marketInfo['marketCode']] = array('name'=>$marketInfo['marketName'],'productLink'=>'');
			}
			unset($marketList['shoplinker']);
		}

		$this->template->assign('pagemode',$pagemode);

		$npay_use = npay_useck();

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_PARAM) == 0 || $_POST['noquery'] ){
			$this->load->model('searchdefaultconfigmodel');
			$page_id = $_PARAM['no_receipt_address'] ? 'admin/order/no_receipt_address' : 'admin/order/catalog';
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config($page_id);
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
				$_PARAM['regist_date'][0] = date('Y-m-d');
				$_PARAM['regist_date'][1] = date('Y-m-d');
				// 본사배송 주문상품 예외처리 :: 2017-03-31 lwh
				if($_PARAM['pagemode'] != 'company_catalog') $_PARAM['chk_step'][15] = 1;
				$_PARAM['chk_step'][25] = 1;
				if($_PARAM['no_receipt_address']) {
					$_PARAM['chk_step'] = [
						'25'=>1,
						'85'=>1,
					];
				}
			}
		}

		// 필수 검색 조건이 없을 경우
		if( ! $_PARAM['header_search_keyword'] && ! $_PARAM['keyword'] && ( ! $_PARAM['regist_date'][0] || ! $_PARAM['regist_date'][1])){
			$_PARAM['regist_date'][0] = date('Y-m-d', strtotime('-365 days'));
			$_PARAM['regist_date'][1] = date('Y-m-d');
		}

		$page			= (trim($_PARAM['page'])) ? trim($_PARAM['page']) : 1;
		$bfStep			= trim($_PARAM['bfStep']);
		$no				= trim($_PARAM['nnum']);

		// 양식 데이터
		$this->db->order_by("seq","desc");
		$this->db->where(array('gb'=>'ORDER',"provider_seq"=>'1'));
		$query = $this->db->get("fm_exceldownload");
		foreach ($query->result_array() as $row){
			$loop[]		= $row;
		}

		// 매장 정보 추출
		$this->load->library("o2o/o2oservicelibrary");
		$o2oStoreList = $this->o2oservicelibrary->get_o2o_config(array(),999);

		//배송방법 셋팅
		foreach($_PARAM['shipping_set_code'] as $k => $v){
			foreach($v as $kk){
				$shipping_method[] = $kk;
			}
			$shipping_set_code[$k] = $shipping_method;
		}

		$shipping_method = array_unique($shipping_method);

		$_PARAM['shipping_set_code'] = $shipping_set_code;
		$_PARAM['shipping_method'] = $shipping_method;

		$_PARAM['limit_s'] = ( $page - 1) * 20;
		$_PARAM['limit_e'] = 20;
		if($_POST['set_search_partial'] == 'on'){ //텍스트 like 검색 허용
		    $_PARAM['search_partial'] = true;
		} else {
		    $_PARAM['search_partial'] = false;
		}

		$query	= $this->ordermodel->get_order_catalog_query_spout($_PARAM);
		if	($query){
			if	($page == 1){
				$_PARAM['query_type']	= 'total_record';
				$totalQuery				= $this->ordermodel->get_order_catalog_query_spout($_PARAM);
				$no						= $totalQuery[0]['cnt'];
			}

			if ($_PARAM['last_step_cnt'] > 0) {
				$stepCount = $_PARAM['last_step_cnt'];
			} else {
				$stepCount = 0;
			}

			if ($_PARAM['last_step_settleprice'] > 0) {
				$stepTotalPrice = $_PARAM['last_step_settleprice'];
			} else {
				$stepTotalPrice = 0;
			}

			if ($_PARAM['last_step_payprice'] > 0) {
			    $stepTotalPayPrice = $_PARAM['last_step_payprice'];
			} else {
			    $stepTotalPayPrice = 0;
			}

			foreach($query as $k => $order_seq){
				$orderQuery = $this->ordermodel->get_order_catalog_query(array('order_seq' => $order_seq['order_seq'], 'pagemode' => $_PARAM['pagemode']));
				$orderData = $orderQuery->result_array();
				$data = $orderData[0];

				// 결제금액에 예치금 합산 #34379 by hed
				$data['payprice'] = $data['settleprice'] +  $data['cash'];

				# 본사 배송(상품상태기준) 주문리스트에서는 옵션 step을 기준으로 출력

				if($_PARAM['pagemode'] == "company_catalog"){
					// 본사배송일 경우 옵션step 기준으로 정렬되어 출력되었으나
					// 기준쿼리를 추출하는 영역(get_order_catalog_query_spout)에서 옵션정렬을 처리하지 않고 있으며
					// 옵션 step 정렬을 하려면 option과 suboption을 조인해야하므로 속도가 비정상적으로 느려짐
					// 이에 본사배송 주문이라도 주문 기준 step을 출력하도록 변경함 by hed #43892
					// $data['step']            = $data['opt_step'];
				}

				if ($data['linkage_id'] == 'connector') {
					if(substr($data['linkage_mall_code'],0,3) == "API"){
						$data['linkage_mallname'] = "오픈마켓 : ".$shopLinkermarketList[$data['linkage_mall_code']]['name'];
						$data['linkage_mallname_text']	= $shopLinkermarketList[$data['linkage_mall_code']]['name'];
					}else{
						$data['linkage_mallname'] = "오픈마켓 : ".$marketList[$data['linkage_mall_code']]['name'];
						$data['linkage_mallname_text']	= $marketList[$data['linkage_mall_code']]['name'];
					}
				}elseif ($data['linkage_id'] == 'pos' && $o2oStoreList) {
					foreach($o2oStoreList as $o2oStore){
						if($o2oStore['o2o_store_seq'] == $data['linkage_mall_code']){
							$data['linkage_mallname_text']	= $o2oStore['pos_name'];
						}
					}
				} else {
					$data['linkage_mallname'] = "내사이트 : ".$this->config_basic['shopName'];
				}


				$data['referer_naver']		= sitemarketplaceNaver($data, 'naver');//유입매체:네이버 url 파라미터 제거

				$data['mstep'] = $this->arr_step[$data['step']];

				$data['sitetypetitle']		= sitetype($data['sitetype'], 'image', '');//판매환경
				$data['marketplacetitle']	= sitemarketplace($data['marketplace'], 'image', '');//유입매체

				$tmp = explode(' ',$data['bank_account']);
				$data['bankname'] = $tmp[0];

				// 올앳 모빌리언스 사명변경
				$pg_log = $this->ordermodel->get_pg_log($data['order_seq']);
				if($data['pg'] == 'allat' && $pg_log[0]['pg'] == 'Mobilians') {
					$data['pg'] = $pg_log[0]['pg'];
				}
				###
				//$data['opt_cnt']	= $this->ordermodel->get_option_count('opt', $data['order_seq']);
				//$data['gift_cnt']	= $this->ordermodel->get_option_count('gift', $data['order_seq']);
				$data['gift_nm']	= $this->ordermodel->get_gift_name($data['order_seq']);
				$data['tot_ea']	= $data['opt_ea']+$data['sub_ea'];

				//반품정보 가져오기
				$data['return_list_ea'] = 0;
				$data_return = $this->returnmodel->get_return_for_order($data['order_seq'],"return");
				if($data_return) foreach($data_return as $row_return){
					$data['return_list_ea'] += $row_return['ea'];
				}

				//교환정보 가져오기
				$data['exchange_list_ea'] = 0;
				$data_exchange = $this->returnmodel->get_return_for_order($data['order_seq'],"exchange");
				if($data_exchange) foreach($data_exchange as $row_exchange){
					$data['exchange_list_ea'] += $row_exchange['ea'];
				}

				//환불정보 가져오기
				$data['refund_list_ea'] = 0;
				$data['cancel_list_ea'] = 0;
				$data_refund = $this->refundmodel->get_refund_for_order($data['order_seq']);
				if($data_refund) foreach($data_refund as $row_refund){
					if( $row_refund['refund_type'] == 'cancel_payment' ){
						$data['cancel_list_ea'] += $row_refund['ea'];
					}else{
						$data['refund_list_ea'] += $row_refund['ea'];
					}
				}

				$data['mpayment'] = $this->arr_payment[$data['payment']];
				$step_cnt[$data['step']]++;
				$tot_settleprice[$data['step']] += $data['settleprice'];
				$tot[$data['step']][$data['important']] += $data['settleprice'];

				$data['step_cnt'] = $step_cnt;
				$data['tot_settleprice'] = $tot_settleprice;
				$data['tot'][$data['important']] = $tot[$data['step']][$data['important']];

				if($data['member_seq']){
					$data['member_type']	= $data['mbinfo_business_seq'] ? '기업' : '개인';
				}

				$data['loop']	= $loop;
				$data['no']		= $no;
				if	($data['payment'] == 'bank' && $data['bank_account']){
					$bank_tmp			= explode(' ', $data['bank_account']);
					$bank_name			= str_replace('은행', '', $bank_tmp[0]);
					$data['bank_name']	= $bank_name;
				}

				if ($data['deposit_date']=="0000-00-00 00:00:00") $data['deposit_date'] = "";

				if	($_PARAM['stepBox'][$data['step']]){
					if		($_POST['stepBox'][$data['step']] == 'select'){
						$data['thischeck']	= true;
					}elseif	($_POST['stepBox'][$data['step']] == 'important'){
						if	($data['important'])
							$data['thischeck']	= true;
					}elseif	($_POST['stepBox'][$data['step']] == 'not-important'){
						if	(!$data['important'])
							$data['thischeck']	= true;
					}
				}

				## 시작점과 종료점
				if	($bfStep != $data['step']){
					$data['start_step']	= $data['step'];
					if ($bfStep){
						$record[$k]['end_step']			= $bfStep;
						$_PARAM['end_step']				= $bfStep;
						$data['end_mstep']				= $this->arr_step[$bfStep];
						$data['end_step']				= $bfStep;

						$data['end_step_cnt']			= $stepCount;
						$data['end_step_settleprice']	= $stepTotalPrice;
						$data['end_step_payprice']		= $stepTotalPayPrice;

						$stepCount = 0;
						$stepTotalPrice = 0;
						$stepTotalPayPrice = 0;
					}

					$stepCount++;
					$stepTotalPrice += $data['settleprice'];
					$stepTotalPayPrice += $data['payprice'];

					$bfStep	= $data['step'];
				} else {
					$stepCount++;
					$stepTotalPrice += $data['settleprice'];
				    $stepTotalPayPrice += $data['payprice'];
				}

				if ($no == 1){
					$_PARAM['query_type']			= 'summary';
					$_PARAM['end_step']				= $data['step'];
					$data['last_step']				= $data['step'];
					$data['last_step_cnt']			= $stepCount;
					$data['last_step_settleprice']	= $stepTotalPrice;
				    $data['last_step_payprice']		= $stepTotalPayPrice;
				}

				//개인정보 마스킹 표시
				$data = $this->privatemasking->masking($data, 'order');

				$record[$k] = $data;
				$final_step	= $data['step'];

				$no--;
			}
		}

		foreach($data['loop'] as $k => $v){
			$down_forms[$k]['seq']	= $v['seq'];
			$down_forms[$k]['name'] = $v['name'];
		}

		// 기본통화 symbol
		$currency_symbol = get_currency_symbol($this->config_system['basic_currency']);
		$this->template->assign(array('currency_symbol' => $currency_symbol));

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign('down_forms', $down_forms);
		$this->template->assign(array('stepBox' => $_PARAM['stepBox']));
		$this->template->assign(array('page' => $page));
		$this->template->assign(array('final_no' => $no));
		$this->template->assign(array('final_step' => $final_step));
		$this->template->assign(array('last_step_cnt' => $stepCount));
		$this->template->assign(array('last_step_settleprice' => $stepTotalPrice));
		$this->template->assign(array('last_step_payprice' => $stepTotalPayPrice));
		$this->template->assign(array('record' => $record));
		$this->template->assign(array('able_step_action' => $this->ordermodel->able_step_action));
		$this->template->assign(array('npay_use' => $npay_use));
		$this->template->assign(array('bankChk' => $_PARAM['bankChk']));
		$this->template->assign(array('no_receipt_address' => $_PARAM['no_receipt_address']));
		$this->template->print_("tpl");
	}

	public function order_barcode_image(){
		$order_seq = $_GET['order_seq'];
		$this->load->library('barcode');
		$this->barcode->codetype ="code128";
		$this->barcode->text = $order_seq;
		$this->barcode->draw();
	}

	public function order_seq_chk(){
		$order_seq = preg_replace("/[^0-9]/i","",$_GET['order_seq']);
		$query = $this->db->query("select count(*) as cnt from fm_order where order_seq=?",$order_seq);
		$result = $query->row_array();
		echo $result['cnt'] ? '1' : '0';
	}

	// 출고
	public function order_export_popup(){

		$auth = $this->authmodel->manager_limit_act('order_goods_export');
		if(!$auth){
			pageClose("권한이 없습니다.	");
			exit;
		}

		$this->load->library('Connector');
		$connector	= $this->connector::getInstance();

		//마켓연동 연동 서비스 사용유무
		if ($connector->isConnectorUse())
			$marketList		= $connector->getAllMarkets(true);

		//샵링커 추가 2017-09-27 jhs
		$this->load->model('connectormodel');
		$shopLinkermarketList = array();
		$shopLinkerUseMarketList = $this->connectormodel->getLinkageMarketGroup();
		foreach($shopLinkerUseMarketList as $marketInfo){
			$shopLinkermarketList[$marketInfo['marketCode']] = array('name'=>$marketInfo['marketName'],'productLink'=>'');
		}

		unset($marketList['shoplinker']);

		$npay_use = npay_useck();	//Npay v2.1 사용여부
		if($npay_use){
			$this->load->library('naverpaylib');
			$npay_hold_reason = $this->naverpaylib->get_npay_code("return_hold");	//npay 반품 보류 코드
		}
		$talkbuy_use = talkbuy_useck();	//카카오페이 구매 사용여부

		// 기본 배송책임 지정
		$provider_seq = $_GET['provider_seq'];

		$meta_title = "출고처리 및 출고상태 변경";
		$this->template->assign(array('meta_title'=>$meta_title));

		$this->load->model('providershipping');
		$this->load->model('order2exportmodel');
		$this->load->model('giftmodel');
		$this->load->model('orderpackagemodel');
		$this->load->model('shippingmodel');
		$this->load->library('privatemasking');
		
		// 매장 정보 추출
		$this->load->library("o2o/o2oservicelibrary");
		$o2oStoreList = $this->o2oservicelibrary->get_o2o_config(array(),999);

		// 기본 검색 설정
		$this->load->model('searchdefaultconfigmodel');
		$data_search_default_order = $this->searchdefaultconfigmodel->get_search_default_config('admin/order/order_export_popup');
		$data_search_default_export = $this->searchdefaultconfigmodel->get_search_default_config('admin/export/batch_status');
		$data_search_default_str = $data_search_default_order['search_info'] ."&". $data_search_default_export['search_info'];
		parse_str($data_search_default_str, $data_search_default);
		$this->template->assign(array('data_search_default'=>$data_search_default));
		$export_default_search_path = dirname($this->template_path()).'/../setting/_export_default_search.html';
		$this->template->define(array('export_default_search_path'=>$export_default_search_path));

		switch ( $data_search_default['order_default_period'] )  {
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
			$_GET['data_field'] = $data_search_default['order_default_date_field'];
			$_GET['start_search_date'] = $start_date;
			$_GET['end_search_date'] = $end_date;
			foreach($data_search_default['order_default_step'] as $default_step){
				$_GET['step'][$default_step] = $default_step;
			}
		}

		$this->order2exportmodel->courier_for_provider[1] = $this->providershipping->get_provider_courier(1);
		$provider	= $this->providermodel->provider_goods_list_sort();
		$exist_goods = false;
		$exist_ticket = false;
		$exist_provider = false;

		$today = date("Y-m-d");
		if( !$_GET['date_field'] ){
			$_GET['date_field'] = "regist_date";
		}

		// 배송지 미등록건 조회 안함
		$where_order[] = "(ord.label IS NULL OR (ord.label='present' AND ord.recipient_zipcode !=''))";

		if( $_GET['start_search_date'] ){
			$where_order[] = "ord.".$_GET['date_field'] . ">= ?";
			$bind[] = substr($_GET['start_search_date'],0,4)."-".substr($_GET['start_search_date'],5,2)."-".substr($_GET['start_search_date'],8,2)." 00:00:00";
		}

		if( $_GET['end_search_date'] ){
			$where_order[] = "ord.".$_GET['date_field'] . "<= ?";
			$bind[] = substr($_GET['end_search_date'],0,4)."-".substr($_GET['end_search_date'],5,2)."-".substr($_GET['end_search_date'],8,2)." 23:59:59";
		}
		if( $_GET['step']['15'] ){
			unset( $_GET['step']['15'] );
		}
		if(!$_GET['step']){
			$_GET['step']['25'] = '25';
		}
		if( $_GET['step'] ){
			$where_order[] =  "ord.step in('".implode("','",$_GET['step'])."')";
		}

		// 검색어 설명 문구
		if(!$_GET['search_type']){
			$search_type = 'order_seq';
		}else{
			$search_type = $_GET['search_type'];
		}
		$arr_search_type = array('order_seq'=>'주문번호','userid'=>'아이디','order_user_name'=>'주문자','recipient_user_name'=>'수령자','depositor'=>'입금자','order_email'=>'이메일','order_phone'=>'연락처','order_cellphone'=>'휴대폰','goods_name'=>'상품명','goods_seq'=>'상품번호','goods_code'=>'상품코드','npay_order_id'=>'N페이주문번호','npay_product_order_id'=>'N페이상품주문번호');
		if($_GET['keyword']){
			$_GET['search_type_text'] = sprintf("%s : %s", $arr_search_type[$_GET['search_type']], $_GET['keyword']);
			switch($search_type){
				case 'order_seq' :
					$where_order[] =  "ord.order_seq = ?";
					$bind[] = $_GET['keyword'];
					break;
				case 'userid' :
					$where_order[] =  "ord.member_seq in (select member_seq from fm_member where userid like ?)";
					$bind[] = '%'.$_GET['keyword'].'%';
					break;
				case 'order_user_name' :
					$where_order[] =  "ord.order_user_name like ?";
					$bind[] = '%'.$_GET['keyword'].'%';
					break;
				case 'depositor' :
					$where_order[] =  "ord.depositor like ?";
					$bind[] = '%'.$_GET['keyword'].'%';
					break;
				case 'recipient_user_name' :
					$where_order[] =  "ord.recipient_user_name like ?";
					$bind[] = '%'.$_GET['keyword'].'%';
					break;
				case 'order_email' :
					$where_order[] =  "ord.order_email like ?";
					$bind[] = '%'.$_GET['keyword'].'%';
					break;
				case 'order_phone' :
					$where_order[] =  "ord.order_phone like ?";
					$bind[] = '%'.$_GET['keyword'].'%';
					break;
				case 'order_cellphone' :
					$where_order[] =  "ord.order_cellphone like ?";
					$bind[] = '%'.$_GET['keyword'].'%';
					break;
				case 'goods_name' :
					$where_order[] =  "ord.order_seq in (select oit.order_seq from fm_order_item oit where ord.order_seq=oit.order_seq and oit.goods_name like ?)";
					$bind[] = '%'.$_GET['keyword'].'%';
					break;
				case 'goods_seq' :
					$where_order[] =  "ord.order_seq in (select oit.order_seq from fm_order_item oit where ord.order_seq=oit.order_seq and oit.goods_seq = ?)";
					$bind[] = $_GET['keyword'];
					break;
				case 'goods_code' :
					$where_order[] =  "ord.order_seq in (select oit.order_seq from fm_order_item oit where ord.order_seq=oit.order_seq and oit.goods_code = ?)";
					$bind[] = $_GET['keyword'];
					break;
				case 'npay_order_id' :
					$where_order[] =  "ord.npay_order_id = ?";
					$bind[] = $_GET['keyword'];
					break;
				case 'npay_product_order_id' :
					$where_order[] =  "io.npay_product_order_id = ?";
					$bind[] = $_GET['keyword'];
					break;
			}
		}

		// 배송책임 검색
		if( $provider_seq ){
			$where_order[] = "shi.provider_seq = ?";
			$bind[] = $provider_seq;
		}

		// 배송방법 검색
		if( $_GET['shipping_method'] ){
			$where_order[] =  "shi.shipping_method in('".implode("','",$_GET['shipping_method'])."')";
		}

		// Npay 주문 검색
		if($_GET['search_npay_order'] == 'y'){
			$where_order[] = "ord.pg='npay'";
			$bind[] = $_GET['search_npay_order'];
		}

		// 카카오페이 구매 주문 검색
		if($_GET['search_talkbuy_order'] == 'y'){
			$where_order[] = "ord.pg='talkbuy'";
			$bind[] = $_GET['search_talkbuy_order'];
		}

		// 주문번호 검색
		if($_GET['seq']){
			$where_order[] = "ord.order_seq in (".str_replace("|",",",$_GET['seq']).")";
		}

		if(!$_GET['page']) $_GET['page'] = 1;
		$num = ($_GET['page'] - 1)*100;
		$query = $this->ordermodel->get_order2export_list($where_order);
		$result_page = select_page(100,$_GET['page'],10,$query,$bind);

		if(!$_GET['provider_seq_consignment']) $_GET['provider_seq_consignment'] = "all";
		switch($_GET['provider_seq_consignment']){
			case "all": $provider_seq_consignment = ''; break;
			case "shop": $provider_seq_consignment = '1';  break;
			case "provider": $provider_seq_consignment = '0';  break;
			default : $provider_seq_consignment = $_GET['provider_seq_consignment']; break;
		}

		foreach($result_page['record'] as $data_order){
			$order_seq = $data_order['order_seq'];
			$result_data = array();
			$params = array(
							'order_seq'=>$order_seq,
							'provider_seq'=>$_GET['provider_seq'],
							'search_shipping_method'=>$_GET['search_shipping_method'],
							'base_inclusion'=>$_GET['base_inclusion'],
							'provider_seq_consignment'=>$provider_seq_consignment
			);
			$data = $this->order2exportmodel->get_data_for_batch_export_item($params);

			// 주문 필터링
			foreach($data as $data_){
				if( $data_['shipping_seq'] == $data_order['shipping_seq']) {
					if( $data_order['coupon_option_seq']){
						if( $data_['options'][$data_order['coupon_option_seq']] ){
							$coupon_option = $data_['options'][$data_order['coupon_option_seq']];
							unset($data_['options']);
							$data_['options'][$data_order['coupon_option_seq']] = $coupon_option;
						}else{
							continue;
						}
					}

					$data_['shipping_set_code'] = $data_['shipping_method'];
					// NEW 배송정보 추출 :: 2016-09-22 lwh
					$shipping = $this->ordermodel->get_order_shipping($order_seq,null,$data_order['shipping_seq']);
					if($shipping){
						$shipping_group_arr	= explode('_', $data_['shipping_group']);
						$data_['shipping_grp_seq']	= $shipping_group_arr[0];
						$data_['shipping_set_seq']	= $shipping_group_arr[1];
						$data_['shipping_set_code'] = ($shipping_group_arr[3]) ? $shipping_group_arr[2].'_'.$shipping_group_arr[3] : $shipping_group_arr[2];

						$ship_set_arr = $this->shippingmodel->get_shipping_set($data_['shipping_grp_seq']);
						$data_['shipping_grp_info'] = $ship_set_arr;

						// 매장수령 정보 추출
						if($data_['shipping_set_code'] == 'direct_store'){
							$ship_store_arr = $this->shippingmodel->get_shipping_store($data_['shipping_set_seq'],'shipping_set_seq');
							$data_['shipping_store_info'] = $ship_store_arr;
						}
					}
					$result_data[] = $data_;
				}
			}
			$data = $result_data;
			$result_order = $this->ordermodel->get_order($order_seq);

			// 불필요 값 제거 작업
			if( $result_order['order_phone'] == '--' ) $result_order['order_phone'] = '';
			if( $result_order['order_cellphone'] == '--' ) $result_order['order_cellphone'] = '';
			if( $result_order['recipient_phone'] == '--' ) $result_order['recipient_phone'] = '';
			if( $result_order['recipient_cellphone'] == '--' ) $result_order['recipient_cellphone'] = '';

			// 받는정보
			foreach($data as $key_shipping => $data_shipping){

				$num++;

				// 공급사 배송 존재 여부
				if( $data_shipping['provider_seq']!=1 && $data_shipping['provider_seq'] ){
					$exist_provider = true;
				}

				if(! $data_provider_shipping_method[$data_shipping['provider_seq']] ){
					$data_provider_shipping_method[$data_shipping['provider_seq']] = $this->providershipping->get_provider_shipping($data_shipping['provider_seq']);
				}

				$data[$key_shipping]['arr_shipping_method'] = $data_provider_shipping_method[$data_shipping['provider_seq']];

				$data[$key_shipping]['export_exist'] = false;
				foreach($data_shipping['options'] as $key_option => $data_option){

					# Npay 주문, 맞교환으로 인한 재주문건
					if($npay_use && $data_option['npay_product_order_id'] && $data_option['top_item_option_seq']){
						# 원주문건의 교환보류가 걸려있는지 확인
						$sql = "select
								ret.npay_flag,ret.return_code
							from
								fm_order_return_item as ret_item
								left join fm_order_return as ret on ret.return_code=ret_item.return_code
							where
								ret_item.option_seq=?
								and ret.order_seq=?
							";
						$query = $this->db->query($sql,array($data_option['top_item_option_seq'],$data_shipping['orign_order_seq']));
						$ret_data = $query->row_array();
						if($ret_data['npay_flag']){
							if(array_key_exists(strtoupper($ret_data['npay_flag']),$npay_hold_reason)){
								$data[$key_shipping]['options'][$key_option]['exchange_return_code'] = $ret_data['return_code'];
								$data[$key_shipping]['options'][$key_option]['npay_flag_msg'] = $npay_hold_reason[strtoupper($ret_data['npay_flag'])];
							}
						}
					}

					if($data_option['export_ea']>0){
						$data[$key_shipping]['export_exist'] = true;
					}
					if( $data_option['request_ea'] > 0){ // 보낼수량
						$data[$key_shipping]['request_exist'] = true;
					}
					if( $data_option['stock'] === '미매칭' && $data_option['request_ea'] > 0 ){
						$data_option['nomatch']++;
						$data[$key_shipping]['miss_goods'] = true;
					}

					if($data_option['package_yn'] == 'y'){
						$data_option['packages'] = $this->orderpackagemodel->get_option($data_option['item_option_seq']);
						$data[$key_shipping]['rowspan'] += count($data_option['packages']);
						foreach($data_option['packages'] as $key=>$data_package){
							$stock			= (int) $data_package['stock'];
							$badstock		= (int) $data_package['badstock'];
							$reservation	= (int) $data_package['reservation'.$this->cfg_order['ableStockStep']];
							$ablestock		= $stock - $badstock - $reservation;
							$data_option['packages'][$key]['ablestock'] = $ablestock;
							$data_option['packages'][$key]['goods_seq'] = $data_package['goods_seq'];

							if(!$data_package['option_seq']){
								$data_option['nomatch']++;
								$data_option['packages'][$key]['stock'] = "미매칭";
							}
						}
					}

					foreach($data_option['suboptions'] as $key_suboption => $data_suboption){
						if($data_suboption['export_ea']>0){
							$data[$key_shipping]['export_exist'] = true;
						}
						if( $data_suboption['request_ea'] > 0){
							$data[$key_shipping]['request_exist'] = true;
						}
						if( $data_suboption['stock'] === '미매칭' && $data_suboption['request_ea'] > 0 ){
							$data_option['nomatch']++;
							$data[$key_shipping]['miss_goods'] = true;
						}

						if($data_suboption['package_yn'] == 'y'){
							$data_suboption['packages'] = $this->orderpackagemodel->get_suboption($data_suboption['item_suboption_seq']);
							$data[$key_shipping]['rowspan'] += count($data_suboption['packages']);
							foreach($data_suboption['packages'] as $key=>$data_package){
								$stock			= (int) $data_package['stock'];
								$badstock		= (int) $data_package['badstock'];
								$reservation	= (int) $data_package['reservation'.$this->cfg_order['ableStockStep']];
								$ablestock		= $stock - $badstock - $reservation;
								$data_suboption['packages'][$key]['ablestock'] = $ablestock;
								$data_suboption['packages'][$key]['goods_seq'] = $data_package['goods_seq'];
							}
							$data_option['suboptions'][$key_suboption]['packages'] = $data_suboption['packages'];

							if(!$data_package['option_seq']){
								$data_option['nomatch']++;
								$data_suboption['packages'][$key]['stock'] = "미매칭";
							}
						}
					}
				}

				if($result_order['linkage_id'] == 'pos' && $data[$key_shipping]['miss_goods']){
					$data[$key_shipping]['request_exist'] = 'pos';
				}
				$data[$key_shipping]['options'][$key_option] = $data_option;

				$arr_recipient_info = array();
				if($result_order['recipient_address_type']=='street'){
					$arr_recipient_info[] = $result_order['recipient_address_street'];
					$arr_recipient_info[] = $result_order['recipient_address_detail'];
					$arr_recipient_info[] = $result_order['recipient_user_name'];
				}else{
					$arr_recipient_info[] = $result_order['recipient_address'];
					$arr_recipient_info[] = $result_order['recipient_address_detai'];
					$arr_recipient_info[] = $result_order['recipient_user_name'];
				}

				if($data_shipping['shipping_method']=='coupon'){
					$arr_recipient_info = array();
					$arr_recipient_info[] = $result_order['recipient_cellphone'];
					$arr_recipient_info[] = $result_order['recipient_user_name'];

					$exist_ticket = true;
				}else{
					$exist_goods = true;
				}

				if(preg_match( '/each/',$data_shipping['shipping_method'])){
					$data[$key_shipping]['shipping_method'] = str_replace("each_","",$data_shipping['shipping_method']);
				}

				$data[$key_shipping]['num'] = $num;
				$data[$key_shipping]['recipient_info'] = implode(' ',$arr_recipient_info);
				$data[$key_shipping]['provider_name'] = $this->order2exportmodel->provider_data[$data_shipping['provider_seq']]['provider_name'];
			}

			// 회원정보
			$result_member = $this->membermodel->get_member_data($result_order['member_seq']);

			if ($result_order['linkage_id'] == 'connector'){
				if(substr($result_order['linkage_mall_code'],0,3) == "API"){
					$result_order['linkage_mallname_text']	= $shopLinkermarketList[$result_order['linkage_mall_code']]['name'];
				}else{
					$result_order['linkage_mallname_text']	= $marketList[$result_order['linkage_mall_code']]['name'];
				}
			}elseif ($result_order['linkage_id'] == 'pos' && $o2oStoreList) {
				foreach($o2oStoreList as $o2oStore){
					if($o2oStore['o2o_store_seq'] == $result_order['linkage_mall_code']){
						$result_order['linkage_mallname_text']	= $o2oStore['pos_name'];
					}
				}
			}

			//개인정보 마스킹 표시
			$result_order = $this->privatemasking->masking($result_order, 'order');

			$result['order'][$result_order['order_seq']] = $result_order;
			if($result_member['member_seq'] ){
				$result['member'][$result_member['member_seq']] = $result_member;
			}

			$result['ordershipping'][] = $data;
		}
		$this->tempate_modules();

		// 입점사 정보 가져오기
		$present_provider_seq = 1;
		if (defined('__SELLERADMIN__') === true) {
			$present_provider_seq = $this->providerInfo['provider_seq'];
		}

		// 택배자동화 가이드
		$invoice_guide_path = dirname($this->template_path()).'/_invoice_guide.html';
		$this->template->define(array('invoice_guide'=>$invoice_guide_path));

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

		$data_present_provider = $this->providermodel->get_provider($present_provider_seq);

		// 물류 창고 선택 plugin options
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		$scmOptions	= array(
						'boxName'				=> 'scm_wh',
						'goodsinfoSelector'		=> '.optioninfo',
						'disStockSelector'		=> 'span.stock',
						'stockSelector'			=> '.whstock',
						'autoWhSelector'		=> '.auto_warehousing',
						'supplyPriceSelector'	=> '.wh_supply_price',
						'eaSelector'			=> '.export_ea',
						'eaPackageSelector'		=> '.package_ea',
						'eaDefaultValSelector'	=> '.orderEA',
						'whNameSelector'		=> '.wh_name',
						'locCodeSelector'		=> '.location-code',
						'badstockSelector'		=> '.wh_badstock',
						'whInfoSelector'		=> '.wh_info',
						'whInfoSkinStr'			=> '[:WH_NAME:] ([:LOC_CODE:]) : [:STOCK:]([:BAD_STOCK:])',
						'suffixHTML'			=> '<br />',
						'defaultValue'			=> $this->scm_cfg['export_wh']
		);
		if	(!$this->config_system)	$this->config_system	= config_load('system');
		if	($this->scm_cfg['use'] == 'Y'){
			$data_present_provider['default_export_stock_check']	= 'limit';
		}

		// 기본 출고 처리 설정
		$default_stock_check_path = dirname($this->template_path()).'/_default_stock_check.html';
		$this->template->define(array('default_stock_check'=>$default_stock_check_path));

		// mode 에 따른 출력 변환
		switch($_GET['view_mode']){
			case	'get_bundle_tmp_list' :
				$for_bundle_delivery	= array();
				foreach((array)$result['ordershipping'] as $shipping_list){
					foreach((array)$shipping_list as $row){
						$now_bundle			= array();
						$bundle_seq			= ($row['shipping_group_seq']) ? $row['shipping_group_seq'] : $row['shipping_seq'];

						$order_user_name	= $result['order'][$row['order_seq']]['order_user_name'];
						$order_user_name	.= ($result['order'][$row['order_seq']]['member_seq']) ? ' (회원)' : ' (비회원)';

						$bundle_row['bundle_seq']				= $bundle_seq;
						$bundle_row['member_seq']				= $result['order'][$row['order_seq']]['member_seq'];
						$bundle_row['order_seq']				= $row['order_seq'];
						$bundle_row['provider_seq']				= ($row['provider_seq'] > 0) ? $row['provider_seq'] : 1;
						$bundle_row['order_user_name']			= $order_user_name;
						$bundle_row['recipient_user_name']		= trim($result['order'][$row['order_seq']]['recipient_user_name']);
						$bundle_row['recipient_phone']			= trim($result['order'][$row['order_seq']]['recipient_phone']);
						$bundle_row['recipient_cellphone']		= trim($result['order'][$row['order_seq']]['recipient_cellphone']);
						$bundle_row['recipient_cellphone_chk']	= preg_replace('/[^0-9]/','',$bundle_row['recipient_cellphone']);
						$bundle_row['recipient_address_type']	= trim($result['order'][$row['order_seq']]['recipient_address_type']);
						$bundle_row['recipient_zipcode']		= trim($result['order'][$row['order_seq']]['recipient_zipcode']);
						$bundle_row['recipient_address']		= trim($result['order'][$row['order_seq']]['recipient_address']);
						$bundle_row['recipient_address_street']	= trim($result['order'][$row['order_seq']]['recipient_address_street']);
						$bundle_row['recipient_address_detail']	= trim($result['order'][$row['order_seq']]['recipient_address_detail']);
						$bundle_row['shipping_method']			= $row['shipping_method'];
						if($npay_use){
							$bundle_row['npay_order_seq']		= $row['npay_order_id'];
						}

						$for_bundle_delivery[$bundle_seq]		= $bundle_row;
					}
				}

				header('Content-Type: application/json');
				$for_bundle_delivery	= json_encode($for_bundle_delivery);
				echo $for_bundle_delivery;
				exit;
				break;

			case 'get_bundle_list'	:
				$provider_seq_list	= array();
				$provider_seq_list[1]['provider_seq']	= 1;	//입점 판매처가 있을때는 본사 없을대는 상호
				$provider_seq_list[1]['provider_name']	= (count($provider) > 0) ? '본사' : $this->config_basic['shopName'];

				foreach((array)$provider as $row){
					$provider_seq_list[$row['provider_seq']]['provider_seq']		= $row['provider_seq'];
					$provider_seq_list[$row['provider_seq']]['provider_name']		= $row['provider_name'];
				}

				//묶음배송용 주문정보 재 생성
				$selected_bundle_list	= array();
				$selected_provider_list	= array();
				foreach((array)$result['ordershipping'] as $shipping_list){
					foreach((array)$shipping_list as $row){
						$selected_bundle_list['shipping_list'][$row['order_seq']][$row['shipping_seq']]	= $row;

						if($row['provider_seq'] > 0 && isset($selected_bundle_list['provider_list'][$row['provider_seq']]) === false){
							$selected_bundle_list['provider_list'][$row['provider_seq']]	= $provider_seq_list[$row['provider_seq']];
						}
					}
				}

				//입점몰은  국제배송이 없음
				$cfg_international_shipping						= array();
				$selected_bundle_list['international_shipping']	= $cfg_international_shipping;

				header('Content-Type: application/json');
				$bundle_list	= json_encode($selected_bundle_list);
				echo $bundle_list;
				exit;
				break;
		}

		// O2O 주문의 주문상태 변경 불가 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_order_order_export_popup();

		$this->template->assign($result);
		$this->template->assign(array('ship_set_code' => $this->shippingmodel->ship_set_code));
		$this->template->assign(array('scm_cfg' => $this->scm_cfg));
		$this->template->assign(array('scmOption' => $scmOptions));
		$this->template->assign(array('shopSno' => $this->config_system['shopSno']));
		$this->template->assign(array('data_present_provider'=>$data_present_provider));
		$this->template->assign(array('exist_ticket'=>$exist_ticket));
		$this->template->assign(array('exist_goods'=>$exist_goods));
		$this->template->assign(array('exist_provider'=>$exist_provider));
		$this->template->define(array('tpl' =>$this->template_path()));
		$this->template->assign(array('provider'=>$provider));
		$this->template->assign(array('npay_use'=>$npay_use));
		$this->template->assign(array('talkbuy_use'=>$talkbuy_use));
		$this->template->assign('data_page',$result_page['page']);
		$this->template->print_("tpl");
	}

	public function order_export_popup_confirm(){
		debug($_POST);
	}

	// 상품준비 처리 팝업
	public function goods_ready(){
		$order_seq	= trim($_GET['seq']);

		$orders 	= $this->ordermodel->get_order($order_seq);
		$items 		= $this->ordermodel->get_item($order_seq);
		if	($items)foreach($items as $i => $item){
			$loop[$i]	= $item;

			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
			if	($options)foreach($options as $o => $opt){
				$opt['mstep']				= $this->arr_step[$opt['step']];
				$loop[$i]['options'][$o]	= $opt;

				$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $opt['item_option_seq']);
				if	($suboptions)foreach($suboptions as $s => $sub){
					$sub['mstep']				= $this->arr_step[$sub['step']];
					$loop[$i]['options'][$o]['suboptions'][$s]	= $sub;
				}
			}
		}

		$file_path	= $this->template_path();
		$this->template->assign(array('orders' => $orders));
		$this->template->assign(array('loop' => $loop));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	public function goods_option_matching()
	{
		$this->load->model('goodsmodel');
		$goods_seq = (int) $_GET['goods_seq'];
		$item_option_seq = (int) $_GET['item_option_seq'];
		$item_suboption_seq = (int) $_GET['item_suboption_seq'];

		$goods = $this->goodsmodel->get_goods($goods_seq);
		if($item_option_seq > 0) $options = $this->goodsmodel->get_goods_option($goods_seq);
		if($item_suboption_seq > 0) $suboptions = $this->goodsmodel->get_goods_suboption($goods_seq);

		$file_path	= $this->template_path();
		$this->template->assign(array('data_goods' => $goods));
		$this->template->assign(array('data_options' => $options));
		$this->template->assign(array('data_suboptions' => $suboptions));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function goods_matching()
	{
		$this->load->model('goodsmodel');
		$goods_seq = (int) $_GET['goods_seq'];
		$item_seq = (int) $_GET['item_seq'];

		$goods = $this->goodsmodel->get_goods($goods_seq);

		$file_path	= $this->template_path();
		$this->template->assign(array('data_goods' => $goods));
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// 일괄출고
	public function batch_export(){
		$this->load->model('goodsmodel');
		$cfg_order = config_load('order');

		$this->load->model('providershipping');

		$this->load->helper('shipping');
		$shipping = use_shipping_method();
		if( $shipping ) foreach($shipping as $key => $data){
			if($data) $shipping_cnt[$key] = count($data);
		}
		$shipping_policy['policy'] 	= $shipping;
		$shipping_policy['count'] 	= $shipping_cnt;

		foreach($_POST['seq'] as $k => $order_seq) {
			$itemproviderlists	= $this->ordermodel->get_item_providerlist($order_seq);
			foreach($itemproviderlists as $itemproviderlist) {

				$order			= $this->ordermodel->get_order($itemproviderlist['order_seq']);
				$itemproviders	= $this->ordermodel->get_item_provider($itemproviderlist['order_seq'], $itemproviderlist['provider_seq'], $itemproviderlist['shipping_seq']);
				if( in_array($order['step'],$this->ordermodel->able_step_action['goods_export']) ) {
					unset($items);
					foreach($itemproviders as $ik => $itemprovider){
						$itemprovider['shipping_method'] = str_replace('each_','',$itemprovider['shipping_method']);
						unset($item);
						if($ik == 0)	$item['goods_name']	= $itemprovider['goods_name'];
						$options	= $this->ordermodel->get_option_for_item($itemprovider['item_seq']);
						if($options)foreach($options as $k => $data){
							$item['ea']				+= $data['ea'];
							$item['step85']			+= $data['step85'];
							$step_complete			= $data['step45'] + $data['step55'] + $data['step65'] + $data['step75'];
							$item['step_complete']	+= $step_complete;
							$item['step_remind']	+= $data['ea'] - $step_complete - $data['step85'];

							$data_shipping_group = $this->ordermodel->get_shipping_for_option($data['item_option_seq']);
							$order['shipping_method'] = $itemprovider['shipping_method'];

							$suboptions	= $this->ordermodel->get_suboption_for_option($itemprovider['item_seq'], $data['item_option_seq']);
							if($suboptions)foreach($suboptions as $k => $subdata){
								$item['ea']				+= $subdata['ea'];
								$item['step85']			+= $subdata['step85'];
								$step_complete			= $subdata['step45'] + $subdata['step55'] + $subdata['step65'] + $subdata['step75'];
								$item['step_complete']	+= $step_complete;
								$item['step_remind']	+= $subdata['ea'] - $step_complete - $subdata['step85'];
							}
						}

						$items[$itemprovider['goods_kind']][]	= $itemprovider;
						if	($itemprovider['goods_kind'] == 'coupon'){
							$order['kind']['coupon']['item_seq']		= $itemprovider['item_seq'];
							$order['kind']['coupon']['ea']				+= $item['ea'];
							$order['kind']['coupon']['step85']			+= $item['step85'];
							$order['kind']['coupon']['step_complete']	+= $item['step_complete'];
							$order['kind']['coupon']['step_remind']		+= $item['step_remind'];
						}else{
							$order['kind']['goods']['item_seq']			= $itemprovider['item_seq'];
							$order['kind']['goods']['ea']				+= $item['ea'];
							$order['kind']['goods']['step85']			+= $item['step85'];
							$order['kind']['goods']['step_complete']	+= $item['step_complete'];
							$order['kind']['goods']['step_remind']		+= $item['step_remind'];
						}
					}
					if	(count($items['coupon']) > 0){
						$coupon_cnt++;
						$order['kind']['coupon']['goods_name']	= $items['coupon'][0]['goods_name'];
						if	(count($items['coupon']) > 1)
							$order['kind']['coupon']['goods_name']	.= '외 '.(count($items['coupon'])-1).'건';
					}
					if	(count($items['goods']) > 0){
						$goods_cnt++;
						$order['kind']['goods']['goods_name']	= $items['goods'][0]['goods_name'];
						if	(count($items['goods']) > 1)
							$order['kind']['goods']['goods_name']	.= '외 '.(count($items['goods'])-1).'건';
					}
				}
				$order['shipping_seq']	= $itemproviderlist['shipping_seq'];
				$order['provider_name']	= $itemproviderlist['provider_name'];
				$order['provider_seq']	= $itemproviderlist['provider_seq'];

				$provider_shipping = $this->providershipping->get_provider_shipping($itemproviderlist['provider_seq']);

				$provider_shipping_policy['policy'] = $provider_shipping;
				$provider_shipping_policy['count'] 	= $provider_shipping['delivery_cnt'];
				$order['shipping'] = $provider_shipping_policy;
				if(  $order['kind']['goods']['step_remind'] > 0 )
				{
					$orders[] = $order;
				}
			}
		}

		$smsinfo	= get_sms_remind_count();
		$this->template->assign(array('smsinfo'	=> $smsinfo));
		$this->template->assign(array('cfg_order'	=> $cfg_order));
		$this->template->assign(array('coupon_cnt'	=> $coupon_cnt));
		$this->template->assign(array('goods_cnt'	=> $goods_cnt));
		$this->template->assign(array('orders'	=> $orders));
		$this->template->assign(array('shipping_policy'	=> $shipping_policy));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function goods_export(){

		$this->load->helper('shipping');
		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('providershipping');
		$this->load->model('giftmodel');

		$order_seq = $_GET['seq'];
		$cfg_order = config_load('order');

		$orders	= $this->ordermodel->get_order($order_seq);
		if( !in_array($orders['step'],$this->ordermodel->able_step_action['goods_export']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 출고처리를 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		$itemproviderlists	= $this->ordermodel->get_item_providerlist($order_seq);

		foreach($itemproviderlists as $itemproviderlist) {//goods_name
			$order = $this->ordermodel->get_order($itemproviderlist['order_seq']);
			$itemproviders = $this->ordermodel->get_item_provider($itemproviderlist['order_seq'], $itemproviderlist['provider_seq'], $itemproviderlist['shipping_seq']);
			$provider_shipping = use_shipping_method($itemproviderlist['provider_seq']);

			unset($itemslist);$coupon_cnt = $goods_cnt = 0;
			foreach($itemproviders as $key=>$item) {

				## 사은품
				$item['gift_title'] = "";
				if($item['goods_type'] == "gift"){
					$giftlog = $this->giftmodel->get_gift_title($order_seq,$item['item_seq']);
					$order_shipping['shipping_items'][$itemKey]['gift_title'] = $giftlog['gift_title'];
				}

				$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
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
					$data['real_stock'] = $real_stock;
					$data['stock'] = $stock;
					$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];
					$data['step_remind'] = $data['ea'] - $data['step85'] -  $data['step_complete'];
					$options[$k] = $data;

					$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);
					if($suboptions) foreach($suboptions as $s => $subdata){
						$real_stock = $this->goodsmodel -> get_goods_suboption_stock(
								$item['goods_seq'],
								$subdata['title'],
								$subdata['suboption']
								);
						$rstock = $this->ordermodel -> get_suboption_reservation(
								$this->cfg_order['ableStockStep'],
								$item['goods_seq'],
								$subdata['title'],
								$subdata['suboption']
								);

						$stock = (int) $real_stock - (int) $rstock;
						$subdata['real_stock'] = (int) $real_stock;
						$subdata['stock'] = (int) $stock;
						$subdata['step_complete'] = $subdata['step45']+$subdata['step55']+$subdata['step65']+$subdata['step75'];
						$subdata['step_remind'] = $subdata['ea'] - $subdata['step85'] -  $subdata['step_complete'];
						$suboptions[$s] = $subdata;
					}

					$data['inputs']	= $this->ordermodel->get_input_for_option($options[$k]['item_seq'], $options[$k]['item_option_seq']);
					$data['suboptions']	= $suboptions;

					// 티켓상품 발송 결과
					if	($item['goods_kind'] == 'coupon'){
						$export				= $this->exportmodel->get_export_item_for_order($item['item_seq'], $data['item_option_seq']);
						unset($export_send);
						for ($z = 0; $z < $data['ea']; $z++){
							if	($export[$z]){
								$export_send[$z]['export_code']		= $export[$z]['export_code'];
								$export_send[$z]['email']			= $export[$z]['recipient_email'];
								$export_send[$z]['sms']				= $export[$z]['recipient_cellphone'];
								$export_send[$z]['mail_status']		= $export[$z]['mail_status'];
								$export_send[$z]['sms_status']		= $export[$z]['sms_status'];
							}else{
								$export_send[$z]['export_code']		= '';
								$export_send[$z]['email']			= $orders['recipient_email'];
								$export_send[$z]['sms']				= $orders['recipient_cellphone'];
								$export_send[$z]['mail_status']		= 'x';
								$export_send[$z]['sms_status']		= 'x';
							}
						}
						$data['export_send']	= $export_send;
					}
					$options[$k]		= $data;
				}

				$item['options']	= $options;

				// 최근 매입처
				$goodsquery = $this->db->query(" select D.provider_name, D.provider_seq from fm_goods A LEFT JOIN fm_provider D ON A.provider_seq = D.provider_seq where A.goods_seq = '{$item['goods_seq']}'");
				$goodsprovider = $goodsquery->row_array();
				$item['goods_provider_seq'] = $goodsprovider['provider_seq'];
				$item['goods_provider_name'] = $goodsprovider['provider_name'];
				if($item['provider_seq']=='1'){
					$query = $this->db->query("
							select c.supplier_name
							from fm_stock_history_item as a
							inner join fm_stock_history as b on a.stock_code = b.stock_code
							inner join fm_supplier as c on b.supplier_seq = c.supplier_seq
							where a.goods_seq = '{$item['goods_seq']}'
							order by b.stock_date desc, b.regist_date desc
							limit 1
					");
					$tmp = $query->row_array();
					$item['goods_lastest_supplier_name'] = $tmp['supplier_name'];
				}

				if	($item['goods_kind'] == 'coupon')	$coupon_cnt++;
				else									$goods_cnt++;

				$itemslist[] 		= $item;
				$shipping_method = $item['shipping_method'];
			}

			$provider_shipping = $this->providershipping->get_provider_shipping($itemproviderlist['provider_seq']);
			$provider_shipping_policy['policy'] = $provider_shipping;
			$provider_shipping_policy['count'] 	= $provider_shipping['delivery_cnt'];
			$itemslistar[$item['item_seq']]['shipping_method']	= $shipping_method;
			$itemslistar[$item['item_seq']]['coupon_cnt']	= $coupon_cnt;
			$itemslistar[$item['item_seq']]['goods_cnt']	= $goods_cnt;
			$itemslistar[$item['item_seq']]['shipping']		= $provider_shipping_policy;
			$itemslistar[$item['item_seq']]['items']		= $itemslist;
			$itemslistar[$item['item_seq']]['provider']		= $itemproviderlist;
		}

		/*
		 //오픈마켓연동정보
		 if($orders['linkage_mall_code']){
		 $this->load->model('openmarketmodel');
		 // 설정된 판매마켓 정보
		 $linkage_mallnames = array();
		 $linkage_malldata		= $this->openmarketmodel->get_linkage_mall();

		 foreach($linkage_malldata as $k => $data){
		 if	($data['mall_code'] == $orders['linkage_mall_code']){
		 $orders['linkage_mall_name'] = $data['mall_name'];
		 break;
		 }
		 }
		 }*/

		$smsinfo	= get_sms_remind_count();
		$this->template->assign(array('smsinfo'	=> $smsinfo));
		$this->template->assign(array('cfg_order'	=> $cfg_order));
		$this->template->assign(array('orders'	=> $orders));
		$this->template->assign(array('itemslist'	=> $itemslistar));

		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function _goods_export($order_seq,$cfg_order,$mode){

		$this->load->helper('shipping');
		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('providershipping');

		//$order_seq = $_GET['seq'];

		$orders	= $this->ordermodel->get_order($order_seq);
		if($mode=='view' && !in_array($orders['step'],$this->ordermodel->able_step_action['goods_export']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 출고처리를 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		$itemproviderlists	= $this->ordermodel->get_item_providerlist($order_seq);

		foreach($itemproviderlists as $itemproviderlist) {//goods_name
			$order = $this->ordermodel->get_order($itemproviderlist['order_seq']);
			$itemproviders = $this->ordermodel->get_item_provider($itemproviderlist['order_seq'], $itemproviderlist['provider_seq'], $itemproviderlist['shipping_seq']);
			$provider_shipping = use_shipping_method($itemproviderlist['provider_seq']);
			unset($itemslist);$coupon_cnt = $goods_cnt = 0;$option_cnt=0;
			foreach($itemproviders as $key=>$item) {

				$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
				$option_cnt += count($options);
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
							$cfg_order['ableStockStep'],
							$item['goods_seq'],
							$data['option1'],
							$data['option2'],
							$data['option3'],
							$data['option4'],
							$data['option5']
							);

					$stock = (int) $real_stock - (int) $rstock;

					$data['real_stock'] = $real_stock;
					$data['stock'] = $stock;
					$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];
					$data['step_remind'] = $data['ea'] - $data['step85'] -  $data['step_complete'];
					$options[$k] = $data;

					$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);
					$option_cnt += count($suboptions);
					if($suboptions) foreach($suboptions as $s => $subdata){
						$real_stock = $this->goodsmodel -> get_goods_suboption_stock(
								$item['goods_seq'],
								$subdata['title'],
								$subdata['suboption']
								);
						$rstock = $this->ordermodel -> get_suboption_reservation(
								$cfg_order['ableStockStep'],
								$item['goods_seq'],
								$subdata['title'],
								$subdata['suboption']
								);

						$stock = (int) $real_stock - (int) $rstock;
						$subdata['real_stock'] = (int) $real_stock;
						$subdata['stock'] = (int) $stock;
						$subdata['step_complete'] = $subdata['step45']+$subdata['step55']+$subdata['step65']+$subdata['step75'];
						$subdata['step_remind'] = $subdata['ea'] - $subdata['step85'] -  $subdata['step_complete'];
						$suboptions[$s] = $subdata;
					}

					$data['inputs']	= $this->ordermodel->get_input_for_option($options[$k]['item_seq'], $options[$k]['item_option_seq']);
					$data['suboptions']	= $suboptions;

					// 티켓상품 발송 결과
					if	($item['goods_kind'] == 'coupon'){
						$export				= $this->exportmodel->get_export_item_for_order($item['item_seq'], $data['item_option_seq']);
						unset($export_send);
						for ($z = 0; $z < $data['ea']; $z++){
							if	($export[$z]){
								$export_send[$z]['export_code']		= $export[$z]['export_code'];
								$export_send[$z]['email']			= $export[$z]['recipient_email'];
								$export_send[$z]['sms']				= $export[$z]['recipient_cellphone'];
								$export_send[$z]['mail_status']		= $export[$z]['mail_status'];
								$export_send[$z]['sms_status']		= $export[$z]['sms_status'];
							}else{
								$export_send[$z]['export_code']		= '';
								$export_send[$z]['email']			= $orders['recipient_email'];
								$export_send[$z]['sms']				= $orders['recipient_cellphone'];
								$export_send[$z]['mail_status']		= 'x';
								$export_send[$z]['sms_status']		= 'x';
							}
						}
						$data['export_send']	= $export_send;
					}
					$options[$k]		= $data;
				}

				$item['options']	= $options;

				// 최근 매입처
				$goodsquery = $this->db->query(" select D.provider_name, D.provider_seq from fm_goods A LEFT JOIN fm_provider D ON A.provider_seq = D.provider_seq where A.goods_seq = '{$item['goods_seq']}'");
				$goodsprovider = $goodsquery->row_array();
				$item['goods_provider_seq'] = $goodsprovider['provider_seq'];
				$item['goods_provider_name'] = $goodsprovider['provider_name'];
				if($item['provider_seq']=='1'){
					$query = $this->db->query("
							select c.supplier_name
							from fm_stock_history_item as a
							inner join fm_stock_history as b on a.stock_code = b.stock_code
							inner join fm_supplier as c on b.supplier_seq = c.supplier_seq
							where a.goods_seq = '{$item['goods_seq']}'
							order by b.stock_date desc, b.regist_date desc
							limit 1
					");
					$tmp = $query->row_array();
					$item['goods_lastest_supplier_name'] = $tmp['supplier_name'];
				}

				if	($item['goods_kind'] == 'coupon')	$coupon_cnt++;
				else									$goods_cnt++;

				$itemslist[] 		= $item;
				$shipping_method = $item['shipping_method'];
			}

			$provider_shipping = $this->providershipping->get_provider_shipping($itemproviderlist['provider_seq']);
			$provider_shipping_policy['policy'] = $provider_shipping;
			$provider_shipping_policy['count'] 	= $provider_shipping['delivery_cnt'];

			$itemslistar[$itemproviderlist['shipping_seq']]['shipping_method']	= $shipping_method;
			$itemslistar[$itemproviderlist['shipping_seq']]['coupon_cnt']	= $coupon_cnt;
			$itemslistar[$itemproviderlist['shipping_seq']]['goods_cnt']	= $goods_cnt;
			$itemslistar[$itemproviderlist['shipping_seq']]['option_cnt']	= $option_cnt;
			$itemslistar[$itemproviderlist['shipping_seq']]['shipping']		= $provider_shipping_policy;
			$itemslistar[$itemproviderlist['shipping_seq']]['items']		= $itemslist;
			$itemslistar[$itemproviderlist['shipping_seq']]['provider']		= $itemproviderlist;
			$tot_coupon_cnt += $coupon_cnt;
			$tot_goods_cnt += $goods_cnt;
		}



		return array(
						'smsinfo' => $smsinfo,
						'orders' => $orders,
						'itemslist' => $itemslistar,
						'tot_goods_cnt' => $tot_goods_cnt,
						'tot_goods_cnt' => $tot_goods_cnt
		);

	}

	public function goods_export_old(){
		$order_seq = $_GET['seq'];
		$cfg_order = config_load('order');

		$orders	= $this->ordermodel->get_order($order_seq);
		if( !in_array($orders['step'],$this->ordermodel->able_step_action['goods_export']) ){
			openDialogAlert($this->arr_step[$orders['step']]."에서는 출고처리를 하실 수 없습니다.",400,140,'parent',"");
			exit;
		}

		$this->load->model('goodsmodel');
		$items = $this->ordermodel->get_item($order_seq);
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

				$stock					= (int) $real_stock - (int) $rstock;
				$data['real_stock']		= $real_stock;
				$data['stock']			= $stock;
				$data['step_complete']	= $data['step45']+$data['step55']+$data['step65']+$data['step75'];
				$data['step_remind']	= $data['ea'] - $data['step85'] -  $data['step_complete'];
				$options[$k]			= $data;
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

				$stock					= (int) $real_stock - (int) $rstock;
				$data['real_stock']		= (int) $real_stock;
				$data['stock']			= (int) $stock;
				$data['step_complete']	= $data['step45']+$data['step55']+$data['step65']+$data['step75'];
				$data['step_remind']	= $data['ea'] - $data['step85'] -  $data['step_complete'];
				$suboptions[$k]			= $data;
			}
			$item['suboptions']	= $suboptions;
			$item['options']	= $options;
			$items[$key] 		= $item;
		}

		$this->load->helper('shipping');
		$shipping = use_shipping_method();
		if( $shipping ) foreach($shipping as $key => $data){
			if($data) $shipping_cnt[$key] = count($data);
		}
		$shipping_policy['policy'] 	= $shipping;
		$shipping_policy['count'] 	= $shipping_cnt;

		$invoice_guide_path = dirname($this->template_path()).'/_invoice_guide.html';
		$this->template->define(array('invoice_guide'=>$invoice_guide_path));


		$this->template->assign(array('cfg_order'	=> $cfg_order));
		$this->template->assign(array('orders'	=> $orders));
		$this->template->assign(array('items'	=> $items));
		$this->template->assign(array('shipping_policy'	=> $shipping_policy));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}




	public function batch_export_old(){
		$this->load->model('goodsmodel');
		$cfg_order = config_load('order');

		$this->load->model('providershipping');

		$this->load->helper('shipping');
		$shipping = use_shipping_method();
		if( $shipping ) foreach($shipping as $key => $data){
			if($data) $shipping_cnt[$key] = count($data);
		}
		$shipping_policy['policy'] 	= $shipping;
		$shipping_policy['count'] 	= $shipping_cnt;

		foreach($_POST['seq'] as $k => $order_seq) {
			$itemproviderlists	= $this->ordermodel->get_item_providerlist($order_seq);
			foreach($itemproviderlists as $itemproviderlist) {
				$order			= $this->ordermodel->get_order($itemproviderlist['order_seq']);
				$itemproviders	= $this->ordermodel->get_item_provider($itemproviderlist['order_seq'], $itemproviderlist['provider_seq'], $itemproviderlist['shipping_seq']);
				if( in_array($order['step'],$this->ordermodel->able_step_action['goods_export']) ) {
					unset($items);
					foreach($itemproviders as $ik => $itemprovider){
						unset($item);
						if($ik == 0)	$item['goods_name']	= $itemprovider['goods_name'];
						$options	= $this->ordermodel->get_option_for_item($itemprovider['item_seq']);
						if($options)foreach($options as $k => $data){
							$item['ea']				+= $data['ea'];
							$item['step85']			+= $data['step85'];
							$step_complete			= $data['step45'] + $data['step55'] + $data['step65'] + $data['step75'];
							$item['step_complete']	+= $step_complete;
							$item['step_remind']	+= $data['ea'] - $step_complete - $data['step85'];

							$data_shipping_group = $this->ordermodel->get_shipping_for_option($data['item_option_seq']);
							$order['shipping_method'] = $itemprovider['shipping_method'];

							$suboptions	= $this->ordermodel->get_suboption_for_option($itemprovider['item_seq'], $data['item_option_seq']);
							if($suboptions)foreach($suboptions as $k => $subdata){
								$item['ea']				+= $subdata['ea'];
								$item['step85']			+= $subdata['step85'];
								$step_complete			= $subdata['step45'] + $subdata['step55'] + $subdata['step65'] + $subdata['step75'];
								$item['step_complete']	+= $step_complete;
								$item['step_remind']	+= $subdata['ea'] - $step_complete - $subdata['step85'];
							}
						}

						$items[$itemprovider['goods_kind']][]	= $itemprovider;
						if	($itemprovider['goods_kind'] == 'coupon'){
							$order['kind']['coupon']['item_seq']		= $itemprovider['item_seq'];
							$order['kind']['coupon']['ea']				+= $item['ea'];
							$order['kind']['coupon']['step85']			+= $item['step85'];
							$order['kind']['coupon']['step_complete']	+= $item['step_complete'];
							$order['kind']['coupon']['step_remind']		+= $item['step_remind'];
						}else{
							$order['kind']['goods']['item_seq']			= $itemprovider['item_seq'];
							$order['kind']['goods']['ea']				+= $item['ea'];
							$order['kind']['goods']['step85']			+= $item['step85'];
							$order['kind']['goods']['step_complete']	+= $item['step_complete'];
							$order['kind']['goods']['step_remind']		+= $item['step_remind'];
						}
					}
					if	(count($items['coupon']) > 0){
						$coupon_cnt++;
						$order['kind']['coupon']['goods_name']	= $items['coupon'][0]['goods_name'];
						if	(count($items['coupon']) > 1)
							$order['kind']['coupon']['goods_name']	.= '외 '.(count($items['coupon'])-1).'건';
					}
					if	(count($items['goods']) > 0){
						$goods_cnt++;
						$order['kind']['goods']['goods_name']	= $items['goods'][0]['goods_name'];
						if	(count($items['goods']) > 1)
							$order['kind']['goods']['goods_name']	.= '외 '.(count($items['goods'])-1).'건';
					}
				}
				$order['shipping_seq']	= $itemproviderlist['shipping_seq'];
				$order['provider_name']	= $itemproviderlist['provider_name'];
				$order['provider_seq']	= $itemproviderlist['provider_seq'];

				$provider_shipping = $this->providershipping->get_provider_shipping($itemproviderlist['provider_seq']);

				$provider_shipping_policy['policy'] = $provider_shipping;
				$provider_shipping_policy['count'] 	= $provider_shipping['delivery_cnt'];
				$order['shipping'] = $provider_shipping_policy;
				if(  $order['kind']['goods']['step_remind'] > 0 )
				{
					$orders[] = $order;
				}
			}
		}

		$smsinfo	= get_sms_remind_count();
		$this->template->assign(array('smsinfo'	=> $smsinfo));
		$this->template->assign(array('cfg_order'	=> $cfg_order));
		$this->template->assign(array('coupon_cnt'	=> $coupon_cnt));
		$this->template->assign(array('goods_cnt'	=> $goods_cnt));
		$this->template->assign(array('orders'	=> $orders));
		$this->template->assign(array('shipping_policy'	=> $shipping_policy));
		$this->template->define(array('tpl'=>$this->template_path()));
		$this->template->print_("tpl");
	}

	// 상품별 일괄출고
	public function order_export(){
		$this->load->helper('shipping');
		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('providershipping');
		$this->load->model('giftmodel');

		$orderSeqs	= $_POST['seq'];
		$cfg_order	= config_load('order');
		if	($orderSeqs && is_array($orderSeqs) && count($orderSeqs) > 0){
			foreach($orderSeqs as $k => $order_seq) {
				$orders		= $this->ordermodel->get_order($order_seq);
				if( !in_array($orders['step'],$this->ordermodel->able_step_action['goods_export']) ){
					openDialogAlert("주문번호 ".$order_seq."는 ".$this->arr_step[$orders['step']]."로 출고처리할 수 없습니다.",400,140,'parent',"");
					exit;
				}

				unset($itemslistarr);
				$itemproviderlists	= $this->ordermodel->get_item_providerlist($order_seq);
				foreach($itemproviderlists as $itemproviderlist) {
					$itemproviders		= $this->ordermodel->get_item_provider($itemproviderlist['order_seq'], $itemproviderlist['provider_seq'], $itemproviderlist['shipping_seq']);
					$provider_shipping	= use_shipping_method($itemproviderlist['provider_seq']);

					unset($itemslist);
					$coupon_cnt = $goods_cnt = $rowspan = 0;
					foreach($itemproviders as $key=>$item) {

						## 사은품
						$item['gift_title'] = "";
						if($item['goods_type'] == "gift"){
							$giftlog = $this->giftmodel->get_gift_title($order_seq,$item['item_seq']);
							$order_shipping['shipping_items'][$itemKey]['gift_title'] = $giftlog['gift_title'];
						}

						$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
						if($options) foreach($options as $k => $data){
							$rowspan++;
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

							$stock					= (int) $real_stock - (int) $rstock;
							$data['real_stock']		= $real_stock;
							$data['stock']			= $stock;
							$data['step_complete']	= $data['step45']
							+ $data['step55']
							+ $data['step65']
							+ $data['step75'];
							$data['step_remind']	= $data['ea']
							- $data['step85']
							-  $data['step_complete'];
							$options[$k]			= $data;

							$suboptions	= $this->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);
							if($suboptions) foreach($suboptions as $s => $subdata){
								$rowspan++;
								$real_stock = $this->goodsmodel -> get_goods_suboption_stock(
										$item['goods_seq'],
										$subdata['title'],
										$subdata['suboption']
										);
								$rstock = $this->ordermodel -> get_suboption_reservation(
										$this->cfg_order['ableStockStep'],
										$item['goods_seq'],
										$subdata['title'],
										$subdata['suboption']
										);

								$stock						= (int) $real_stock - (int) $rstock;
								$subdata['real_stock']		= (int) $real_stock;
								$subdata['stock']			= (int) $stock;
								$subdata['step_complete']	= $subdata['step45']
								+ $subdata['step55']
								+ $subdata['step65']
								+ $subdata['step75'];
								$subdata['step_remind']		= $subdata['ea']
								- $subdata['step85']
								-  $subdata['step_complete'];
								$suboptions[$s]				= $subdata;
							}

							$data['inputs']		= $this->ordermodel->get_input_for_option($options[$k]['item_seq'], $options[$k]['item_option_seq']);
							$data['suboptions']	= $suboptions;

							// 티켓상품 발송 결과
							if	($item['goods_kind'] == 'coupon'){
								$export	= $this->exportmodel->get_export_item_for_order($item['item_seq'], $data['item_option_seq']);
								unset($export_send);
								for ($z = 0; $z < $data['ea']; $z++){
									if	($export[$z]){
										$export_send[$z]['export_code']		= $export[$z]['export_code'];
										$export_send[$z]['email']			= $export[$z]['recipient_email'];
										$export_send[$z]['sms']				= $export[$z]['recipient_cellphone'];
										$export_send[$z]['mail_status']		= $export[$z]['mail_status'];
										$export_send[$z]['sms_status']		= $export[$z]['sms_status'];
									}else{
										$export_send[$z]['export_code']		= '';
										$export_send[$z]['email']			= $orders['recipient_email'];
										$export_send[$z]['sms']				= $orders['recipient_cellphone'];
										$export_send[$z]['mail_status']		= 'x';
										$export_send[$z]['sms_status']		= 'x';
									}
								}
								$data['export_send']	= $export_send;
							}
							$options[$k]		= $data;
						}

						$item['options']	= $options;

						// 최근 매입처
						$goodsquery		= $this->db->query(" select D.provider_name, D.provider_seq from fm_goods A LEFT JOIN fm_provider D ON A.provider_seq = D.provider_seq where A.goods_seq = '{$item['goods_seq']}'");
						$goodsprovider	= $goodsquery->row_array();
						$item['goods_provider_seq']		= $goodsprovider['provider_seq'];
						$item['goods_provider_name']	= $goodsprovider['provider_name'];
						if($item['provider_seq']=='1'){
							$query = $this->db->query("
										select c.supplier_name
										from fm_stock_history_item as a
										inner join fm_stock_history as b on a.stock_code = b.stock_code
										inner join fm_supplier as c on b.supplier_seq = c.supplier_seq
										where a.goods_seq = '{$item['goods_seq']}'
										order by b.stock_date desc, b.regist_date desc
										limit 1
										");
							$tmp = $query->row_array();
							$item['goods_lastest_supplier_name'] = $tmp['supplier_name'];
						}

						if	($item['goods_kind'] == 'coupon')	$coupon_cnt++;
						else									$goods_cnt++;

						$itemslist[] 		= $item;
						$shipping_method = $item['shipping_method'];
					}

					$provider_shipping = $this->providershipping->get_provider_shipping($itemproviderlist['provider_seq']);
					$provider_shipping_policy['policy'] = $provider_shipping;
					$provider_shipping_policy['count'] 	= $provider_shipping['delivery_cnt'];

					$itemslistarr[$item['shipping_seq']]['rowspan']			+= $rowspan;
					$itemslistarr[$item['shipping_seq']]['shipping_method']	= $shipping_method;
					$itemslistarr[$item['shipping_seq']]['coupon_cnt']		= $coupon_cnt;
					$itemslistarr[$item['shipping_seq']]['goods_cnt']		= $goods_cnt;
					$itemslistarr[$item['shipping_seq']]['shipping']		= $provider_shipping_policy;
					$itemslistarr[$item['shipping_seq']]['items']			= $itemslist;
					$itemslistarr[$item['shipping_seq']]['provider']		= $itemproviderlist;
				}	// End Foreach itemproviderlists


				/*	//오픈마켓연동정보
				 if($orders['linkage_mall_code']){
				 $this->load->model('openmarketmodel');
				 if	($this->openmarketmodel->chk_linkage_service()){
				 // 설정된 판매마켓 정보
				 $linkage_mallnames		= array();
				 $linkage_malldata		= $this->openmarketmodel->get_linkage_mall();
				 foreach($linkage_malldata as $k => $data){
				 if	($data['mall_code'] == $orders['linkage_mall_code']){
				 $orders['linkage_mall_name'] = $data['mall_name'];
				 break;
				 }
				 }
				 }
				 }	// End If linkage_mall_code
				 */

				$orderlist[]	= array('orders'=>$orders, 'itemslistarr' => $itemslistarr);

			}	// End Foreach orderSeqs

		}	// End If orderSeqs

		$smsinfo	= get_sms_remind_count();
		$this->template->assign(array(
						'smsinfo'		=> $smsinfo,
						'cfg_order'		=> $cfg_order,
						'orderlist'		=> $orderlist
		));
		$this->template->define(array('tpl'			=>$this->template_path()));
		$this->template->print_("tpl");

	}	// End Function order_export

	public function order_settle(){
		serviceLimit('H_FR','process');

		$auth = $this->authmodel->manager_limit_act('personal_act');
		if(!$auth){
			if($_GET['ajaxcall']){
				echo "auth";
			}else{
				pageBack("관리자 권한이 없습니다.");
			}
			exit;
		}

		$this->load->helper('shipping');

		$this->admin_menu();
		$this->tempate_modules();
		$file_path		= $this->template_path();
		$template_dir	= $this->template->template_dir;
		$compile_dir	= $this->template->compile_dir;
		$ordertype		= (trim($_GET['ordertype'])) ? trim($_GET['ordertype']) : 'admin';

		if	($ordertype == 'person'){
			$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');
			$this->load->model('personcartmodel');
			$this->personcartmodel->delete_dummy_cart();
		}

		$bank = $payment = $escrow	= '';
		$arr						= config_load('bank');
		if	($arr)	foreach(config_load('bank') as $k => $v){
			list($tmp)	= code_load('bankCode',$v['bank']);
			$v['bank']	= $tmp['value'];
			$bank[]		= $v;
			if	( $v['accountUse'] == 'y' )	$payment['bank']	= true;
		}
		if	( $this->config_system['pgCompany'] ){
			$pgCompany								= $this->config_system['pgCompany'];
			$payment_gateway						= config_load($pgCompany);
			$payment_gateway['arrKcpCardCompany']	= code_load('kcpCardCompanyCode');
			if	($payment_gateway['arrKcpCardCompany']){
				foreach($payment_gateway['arrKcpCardCompany'] as $k => $v){
					$payment_gateway['arrCardCompany'][$v['codecd']]	= $v['value'];
				}
			}

			if	(isset($payment_gateway['payment'])){
				foreach($payment_gateway['payment'] as $k => $v){
					$payment[$v]	= true;
				}
			}

			if	(isset($payment_gateway['escrow'])){
				foreach($payment_gateway['escrow'] as $k => $v){
					$escrow[$v]		= true;
				}
			}

			$escrow_view	= true;
		}
		// 카카오페이 추가 :: 2015-02-11 lwh
		if( $this->config_system['not_use_kakao'] == 'n' ){
			$payment['kakaopay']	= true;
		}

		// 페이코 추가
		if( $this->config_system['not_use_payco'] == 'n' ){
			$payment['payco']	= true;
		}

		// 페이팔 추가
		if( $this->config_system['not_use_paypal'] == 'n' ){
			$payment['paypal']	= true;
		}

		// 엑심베이 추가
		if( $this->config_system['not_use_eximbay'] == 'n' ){
			$payment['eximbay']	= true;
		}

		// 배송설정
		$shipping = use_shipping_method();
		// 해외배송불가카테고리 체크
		foreach($shipping[1] as $i=>$row){
			foreach($row['exceptCategory'] as $exceptCategory){
				if(in_array($exceptCategory,$category)){
					unset($shipping[1][$i]);
				}
			}
		}
		if(!count($shipping[1])) unset($shipping[1]);
		if( is_array($shipping) ) {
			foreach($shipping as $key => $data){
				if($data) $shipping_cnt[$key] = count($data);
			}
		}
		$shipping_policy['policy'] 	= $shipping;
		$shipping_policy['count'] 	= $shipping_cnt;
		// 검사하지 않아도 됨. 기초 셋팅시 자동으로 기본 배송이 들어감. :: 2017-08-07 lwh
		if($shipping_policy['count']==0 && !serviceLimit('H_ST') && false){
			if($_GET['ajaxcall']){
				echo "noshipping";
				exit;
			}else{
				openDialogAlert("배송방법이 존재하지 않습니다.<br/>쇼핑몰 고객센터에 문의 해 주세요.",400, 140);
				exit;
			}
		}

		$arrBasic		= ($this->config_basic)	? $this->config_basic	: config_load('basic');

		//비회원 개인정보 수집-이용 약관동의 추가
		$member			= config_load('member');
		$policy			= str_replace("{domain}", $arrBasic['domain'], str_replace("{shopName}", $arrBasic['shopName'], $member['policy']));

		// 청약철회 ( 미리 넣어둔다. )
		$arrOrder		= config_load('order');
		$cancellation	= str_replace("{domain}", $arrBasic['domain'], str_replace("{shopName}", $arrBasic['shopName'], $arrOrder['cancellation']));

		$containerHeight	= !empty($_GET['containerHeight']) ? $_GET['containerHeight'] : 600;
		$this->template->assign(array(
						'firstmallcartid'			=> session_id(),
						'list'						=> $cart['list'],
						'total'						=> $cart['total'],
						'ordertype'					=> $ordertype,
						'bank'						=> $bank,
						'payment'					=> $payment,
						'shipping_price'			=> $cart['shipping_price'],
						'shipping_company_cnt'		=> $cart['shipping_company_cnt'],
						'provider_shipping_policy'	=> $cart['provider_shipping_policy'],
						'provider_shipping_price'	=> $cart['provider_shipping_price'],
						'shop_shipping_policy'		=> $cart['shop_shipping_policy'],
						'total_price'				=> $cart['total_price'],
						'containerHeight'			=> $containerHeight,
						'shipping_policy'			=> $shipping_policy,
						'cancellation'				=> $cancellation,
						'policy'					=> $policy,
						'cfg_reserve'				=> $cfg_reserve,
		));

		$this->template->assign('mode',"admin");

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	public function download_coupon(){

		switch($_GET['mode']){
			case	'coupon_ordno_goods_ordersheet' :
				$search_type	= 'goods_ordersheet_order_seq';
				break;

			case	'coupon_ordno' :
				$search_type	= 'item_order_seq';
				break;

			case	'coupon_down' :
				$search_type	= 'download_seq';
				break;

			case	'coupon_shipping' :
				$search_type	= 'shipping_order_seq';
				break;
		}

		$this->template->assign(array(
						'search_type'		=> $search_type
						,'search_text'		=> $_GET['no']
		));

		$this->template->define(array('tpl'	=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function get_use_coupon_list(){
		### SEARCH
		$sc					= $_POST;
		$sc['search_text']	= ($sc['search_text'] == '주문번호, 아이디, 이름' || $sc['order_seq']) ? '':$sc['search_text'];
		$sc['perpage']		= (!empty($_POST['perpage'])) ? intval($_POST['perpage']):10;
		$sc['page']			= (!empty($_POST['page'])) ? intval(($_POST['page'] - 1) * $sc['perpage']):0;

		$data		= $this->ordermodel->use_coupon_list($sc);

		// 페이징 처리 위한 변수 셋팅
		$page		= get_current_page($sc);
		$pagecount	= get_page_count($sc, $data['count']);

		### PAGE & DATA
		$sc['searchcount']	= $data['count'];
		$sc['total_page']	= @ceil($sc['searchcount']/ $sc['perpage']);
		$idx	= 0;

		$html	= $this->get_download_cpupon_html($data, $sc,  $page);

		if(!empty($html)) {
			$result = array( 'content'=>$html,'totalsaleprcie'=>$coupon_sale['coupon_sale'], 'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}else{
			$result = array( 'content'=>"",'totalsaleprcie'=>$coupon_sale['coupon_sale'],  'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}
		echo json_encode($result);
		exit;
	}

	//쿠폰관리 > 발급내역
	function get_download_cpupon_html($data, $sc, $page){
		$html = '';
		$i = 0;
		foreach($data['result'] as $datarow){

			if(strstr($datarow['type'],'shipping')){
				//배송비 쿠폰
				$order_list		= $this->ordermodel->get_order_shipping_coupon($datarow['member_seq'], $datarow['download_seq']);

				foreach((array)$order_list AS $val){
					if($val['provider_seq'] == $datarow['provider_seq']){
						$order_coupon	= $val;
						break;
					}
				}

				$items 			= $this->ordermodel->get_item($order_coupon['order_seq']);

				$item_count					= count($items) - 1;
				$order_coupon['goods_name']	= $items[0]['goods_name'];
				$order_coupon['goods_seq']	= $items[0]['goods_seq'];
				$order_coupon['image']		= $items[0]['image'];

				if($item_count > 0)	$order_coupon['goods_name']	.= " 외 {$item_count}건";

				$datarow['salepricetitle']				= ($datarow['shipping_type'] == 'free') ? '무료, 최대 '.get_currency_price($datarow['max_percent_shipping_sale'],3) : '배송비 '.get_currency_price($datarow['won_shipping_sale'],3);//

			}else if(strstr($datarow['type'],'ordersheet')){
				//주문서 쿠폰
				$order_list		= $this->ordermodel->get_order_ordersheet_coupon($datarow['member_seq'], $datarow['f_order_seq'], $datarow['download_seq']);

				foreach((array)$order_list AS $val){
					$order_coupon	= $val;
					break;
				}

				$items 			= $this->ordermodel->get_item($order_coupon['order_seq']);

				$item_count					= count($items) - 1;
				$order_coupon['goods_name']	= $items[0]['goods_name'];
				$order_coupon['goods_seq']	= $items[0]['goods_seq'];
				$order_coupon['image']		= $items[0]['image'];

				if($item_count > 0)	$order_coupon['goods_name']	.= " 외 {$item_count}건";

				$datarow['salepricetitle']		= ($datarow['sale_type'] == 'percent' ) ? $datarow['percent_goods_sale'].'% 할인, <br/>최대 '.get_currency_price($datarow['max_percent_goods_sale']): get_currency_price($datarow['won_goods_sale'],3).' 할인';
			}else{
				//일반 쿠폰
				$order_list	= $this->ordermodel->get_option_coupon_item($datarow['member_seq'], $datarow['download_seq'], true);

				foreach((array)$order_list AS $val){
					if($val['item_option_seq'] == $datarow['item_option_seq']){
						$order_coupon	= $val;
						break;
					}
				}

				$datarow['salepricetitle']		= ($datarow['sale_type'] == 'percent' ) ? $datarow['percent_goods_sale'].'% 할인, <br/>최대 '.get_currency_price($datarow['max_percent_goods_sale']): get_currency_price($datarow['won_goods_sale'],3).' 할인';
			}

			if($order_coupon['coupon_order_saleprice'] < 1) continue;

			// 중복할인 여부 추가
			$datarow['duplication_text'] = '';
			if(isset($datarow['duplication_use']) && $datarow['duplication_use'] === '1') {
			    if($datarow['type'] == 'shipping' || $datarow['type'] == 'mobile' || $datarow['type'] == 'download') {
			        $datarow['duplication_text'] = '<div style="margin-top:10px">중복다운로드 및 중복할인</div>';
			    } else {
			        $datarow['duplication_text'] = '<div style="margin-top:10px">중복할인</div>';
			    }
			}
			$datarow['number']					= $data['count'] - ( ( $page -1 ) * $sc['perpage'] + $i + 1 ) + 1;
			$datarow['date']					= substr($datarow['regist_date'],2,14);//등록일
			$datarow['use_date']				= substr($datarow['use_date'],2,14);
			$datarow['coupon_order_saleprice']	= get_currency_price($order_coupon['coupon_order_saleprice'],3).'&nbsp;';
			$datarow['limit_goods_price_title'] = get_currency_price($datarow['limit_goods_price'],3);//제한금액 이상&nbsp;
			$datarow['coupon_same_time_title']	= ($datarow['coupon_same_time']=='N') ? "단독" : "동시";
			$datarow['issue_type_title']		= ($datarow['issue_type']=='issue' || $datarow['issue_type']=='except') ? "제한" : "전체";
			$datarow['sale_payment_title']		= ($datarow['sale_payment']=='b') ? "무통장" : "X";
			$datarow['sale_referer_title']		= ($datarow['sale_referer']=='n' || $datarow['sale_referer']=='y') ? "제한" : "무관";
			$datarow['sale_agent_title']		= ($datarow['sale_agent']=="m") ? 'Mobile' : "X";//<img src="/images/common/icon_mobile.gif" >
			$datarow['limit_title']				= $datarow['coupon_same_time_title'].'/'.$datarow['limit_goods_price_title'].'/'.$datarow['issue_type_title'].'/'.$datarow['sale_agent_title'].'/'.$datarow['sale_payment_title'].'/'.$datarow['sale_referer_title'];
			$datarow['issuedate']				= substr($datarow['issue_startdate'],2,10).' <br/> '.substr($datarow['issue_enddate'],2,10);//유효기간

			$html	.= <<<HTML
				<tr>
					<td class="its-td-align center">{$datarow['number']}</td>
					<td class="its-td-align center">{$datarow['coupon_name']}</td>
					<td class="its-td-align center"><span class=" userinfo hand bold blue"  onclick="userinfo('{$datarow['member_seq']}');"  mid="{$datarow['userid']}" mseq="{$datarow['member_seq']}" >{$datarow['userid']}</span></td>';
					<td class="its-td-align center bold"><span class=" userinfo hand bold blue"  onclick="userinfo('{$datarow['member_seq']}');"   mid="{$datarow['userid']}" mseq="{$datarow['member_seq']}" >{$datarow['user_name']}</span></td>';
					<td class="its-td-align center">{$datarow['date']}</td>
					<td class="its-td-align center">
						{$datarow['coupon_same_time_title']}/{$datarow['limit_goods_price_title']}/{$datarow['issue_type_title']}/{$datarow['sale_agent_title']}/{$datarow['sale_payment_title']}/{$datarow['sale_referer_title']}
					</td>
					<td class="its-td-align center">{$datarow['issuedate']}</td>
					<td class="its-td-align center">{$datarow['salepricetitle']}{$datarow['duplication_text']}</td>
					<td class="its-td-align right">{$datarow['coupon_order_saleprice']}</td>
					<td class="its-td-align center">
						<span class="btn small gray">
							<input type="button" onclick="coupon_info_view(this)" class="coupongoodsreviewbtnpopup" coupon_type="online" coupon_seq="{$datarow['coupon_seq']}" download_seq="{$datarow['download_seq']}"  value="조회" />
						</span>
					</td>
					<td class="its-td-align center"><span class="blue" >사용함</span></td>
					<td class="its-td-align center">
						<span class="goods_name1 hand orderview bold blue" onclick="orderinfo('{$order_coupon['order_seq']}');">
							[{$order_coupon['order_seq']}]
						</span><br/>
						<img src="{$order_coupon['image']}" /><br />
						<span class="goods_name1 hand goodsview bold blue" onclick="goodsinfo('{$order_coupon['goods_seq']}');">
							{$order_coupon['goods_name']}
						</span>
					</td>
					<td class="its-td-align center">{$datarow['use_date']}</td>
				</tr>
HTML;

							$i++;
		}//foreach end

		if($i==0){
			if($sc['search_text']){
				$html .= '<tr ><td class="its-td-align center" colspan="13" >"'.$sc['search_text'].'"로(으로) 검색된 쿠폰내역이 없습니다.</td></tr>';
			}else{
				$html .= '<tr ><td class="its-td-align center" colspan="13" >사용내역이 없습니다.</td></tr>';
			}
		}
		return $html;
	}

	public function download_promotion(){

		switch($_GET['mode']){
			case	'promotion_ordno' :
				$search_type	= 'order_seq';
				break;

			case	'promotion_code' :
				$search_type	= 'download_seq';
				break;

			case	'promotion_shipping' :
				break;
		}


		$this->template->assign(array(
						'search_type'		=> $search_type
						,'search_text'		=> $_GET['no']
		));

		$this->template->define(array('tpl'	=>$this->template_path()));
		$this->template->print_("tpl");
	}

	public function get_use_promotion_list()	{
		$no			= (int) $_POST['no'];

		### SEARCH
		$sc					= $_POST;
		$sc['search_text']	= ($sc['search_text'] == '주문번호, 아이디, 이름') ? '' : $sc['search_text'];
		$sc['orderby']		= (!empty($_POST['orderby'])) ?	$_POST['orderby']:'download_seq';
		$sc['perpage']		= (!empty($_POST['perpage'])) ?	intval($_POST['perpage']):10;
		$sc['page']			= (!empty($_POST['page'])) ? intval(($_POST['page'] - 1) * $sc['perpage']):0;


		$data = $this->ordermodel->use_promotion_list($sc);

		// 페이징 처리 위한 변수 셋팅
		$page =  get_current_page($sc);
		$pagecount = get_page_count($sc, $data['count']);

		### PAGE & DATA
		$sc['searchcount']	= $data['count'];
		$sc['total_page']	= @ceil($sc['searchcount']/ $sc['perpage']);

		$idx = 0;
		$html = $this->get_download_promotion_html($data, $sc,  $page);
		if(!empty($html)) {
			$result = array( 'content'=>$html,'totalsaleprcie'=>$promotion_order_saleprice['promotion_code_sale'],  'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}else{
			$result = array( 'content'=>"",'totalsaleprcie'=>$promotion_order_saleprice['promotion_code_sale'],  'searchcount'=>$sc['searchcount'], 'totalcount'=>$sc['totalcount'], 'nowpage'=>(($sc['page']/$sc['perpage'])+1), 'total_page'=>$sc['total_page'], 'page'=>$page, 'pagecount'=>(int)$pagecount);
		}
		echo json_encode($result);
		exit;
	}

	//코드관리 > 발급내역

	function get_download_promotion_html($data, $sc, $page){
		$this->load->model('membermodel');
		$html = '';
		$i = 0;
		foreach($data['result'] as $datarow){

			$memberbuyer	= $this->membermodel->get_member_data($datarow['member_seq_buy']);//구매회원정보

			if($memberbuyer){
				$datarow['userid_buy']		= $memberbuyer['userid'];
				$datarow['user_name_buy']	= $memberbuyer['user_name'];
			}else{
				$datarow['user_name_buy']	= '비회원';
				$datarow['user_name']		= ($datarow['user_name']) ? $datarow['user_name'] : '비회원';
			}


			if(strstr($datarow['type'],'shipping')){
				//배송비 프로모션 코드
				$order_list			= $this->ordermodel->get_order_shipping_promotion($datarow['member_seq_buy'], $datarow['download_seq']);
				$items				= $this->ordermodel->get_item($datarow['order_seq']);
				$order_promotion	= $order_list[0];

				$item_count						= count($items) - 1;
				$order_promotion['goods_name']	= $items[0]['goods_name'];
				$order_promotion['goods_seq']	= $items[0]['goods_seq'];
				$order_promotion['image']		= $items[0]['image'];

				if($item_count > 0)	$order_promotion['goods_name']	.= " 외 {$item_count}건";

				$datarow['salepricetitle']		= ($datarow['sale_type'] == 'shipping_free' ) ? "무료, 최대 ".get_currency_price($datarow['max_percent_shipping_sale'],3) : "배송비 ".get_currency_price($datarow['won_shipping_sale'],3);
				foreach((array)$order_list AS $val){

					$get_download_promotion_goodsinfo .= '
					<span class="goods_name1 hand orderview bold blue" onclick="orderinfo(\''.$val[order_seq].'\');" order_seq="'.$val[order_seq].'" >['.$val[order_seq].']</span><br/><img src="'.$val[image].'" /> <br />
					<span class="goods_name1 hand goodsview bold blue" onclick="goodsinfo(\''.$val[goods_seq].'\');" goods_seq="'.$val[goods_seq].'" >'.$val[goods_name].'</span>';
				}
			}else{
				//일반 프로모션 코드
				$order_list		= $this->ordermodel->get_option_promotioncode_item($datarow['member_seq_buy'], $datarow['download_seq'], true);
				$order_promotion['promotion_order_saleprice'] = 0;
				$get_download_promotion_goodsinfo = '';
				foreach((array)$order_list AS $val){
					if($val['promotion_order_saleprice'] < 1) continue;
					$order_promotion['promotion_order_saleprice'] += $val['promotion_order_saleprice'];
					$get_download_promotion_goodsinfo .= '<span class="goods_name1 hand orderview bold blue" onclick="orderinfo(\''.$val[order_seq].'\');" order_seq="'.$val[order_seq].'" >['.$val[order_seq].']</span><br/><img src="'.$val[image].'" /> <br />
					<span class="goods_name1 hand goodsview bold blue" onclick="goodsinfo(\''.$val[goods_seq].'\');" goods_seq="'.$val[goods_seq].'" >'.$val[goods_name].'</span>';

				}

				$datarow['salepricetitle']			= ($datarow['sale_type'] == 'percent' ) ? "{$datarow['percent_goods_sale']}% 할인, 최대 ".get_currency_price($datarow['max_percent_goods_sale'],3): "판매가격의 ".get_currency_price($datarow['won_goods_sale'],3);
			}

			if($datarow['order_promotion_code_sale'] < 1) continue;

			$datarow['number']						= $data['count'] - ( ( $page -1 ) * $sc['perpage'] + $i + 1 ) + 1;
			$datarow['date']						= substr($datarow['regist_date'],2,14);//등록일
			$datarow['use_date']					= ($datarow['use_status'] == 'used') ? substr($datarow['use_date'],2,14):'';
			$promotion_order_saleprice	= get_currency_price($datarow['order_promotion_code_sale'],3).'&nbsp;';
			$datarow['limit_goods_price_title']		= get_currency_price($datarow['limit_goods_price']).' 이상 구매 시&nbsp;';//제한금
			$datarow['issuedate']					= substr($datarow['issue_startdate'],2,10).' ~ '.substr($datarow['issue_enddate'],2,10);//유효기간


			$html	.= <<<HTML
					<tr>
						<td class="its-td-align center">{$datarow['number']}</td>
						<td class="its-td-align center">{$datarow['promotion_name']}</td>
						<td class="its-td-align center">
							<span class=" userinfo hand bold blue"  onclick="userinfo('{$datarow['member_seq']}');"  mid="{$datarow['userid']}" mseq="{$datarow['member_seq']}">
								{$datarow['userid']}<br/>{$datarow['user_name']}
							</span>
						</td>
						<td class="its-td-align center">{$datarow['date']}</td>
						<td class="its-td-align center">{$datarow['limit_goods_price_title']}</td>
						<td class="its-td-align center">{$datarow['issuedate']}</td>
						<td class="its-td-align center">{$datarow['salepricetitle']}</td>
						<td class="its-td-align right">{$promotion_order_saleprice}</td>
						<td class="its-td-align center">{$datarow['promotion_input_serialnumber']}</td>
						<td class="its-td-align center">
							<span class=" userinfo hand bold blue"  onclick="userinfo('{$datarow['member_seq_buy']}');"  mid="{$datarow['userid_buy']}" mseq="{$datarow['member_seq_buy']}">
								{$datarow['userid_buy']}<br/>{$datarow['user_name_buy']}
							</span>
						</td>
						<td class="its-td-align center"><span class="blue" >사용함</span></td>
						<td class="its-td-align center">{$get_download_promotion_goodsinfo}</td>
						<td class="its-td-align center">{$datarow['use_date']}</td>
			</tr>
HTML;
								$i++;
		}//foreach end

		if($i==0){
			if($sc['search_text']){
				$html .= '<tr ><td class="its-td-align center" colspan="13" >"'.$sc['search_text'].'"로(으로) 검색된 프로모션내역이 없습니다.</td></tr>';
			}else{
				$html .= '<tr ><td class="its-td-align center" colspan="13" >발급/사용내역이 없습니다.</td></tr>';
			}
		}

		return $html;
	}


	# 마일리지 및 구매확정 개선 관련 참고용 @2015-07-23 pjm
	public function export_reserve_ea(){

		$this->load->model('exportmodel');
		$order_seq = trim($_GET['order_seq']);

		if($_POST['mode'] == "complete_update"){

			foreach($_POST['complete_date'] as $export_code=>$complete_date){

				$complete_date = trim($complete_date);
				$this->db->query("update fm_goods_export set complete_date='".$complete_date."' where export_code='".$export_code."'");
			}

			echo '<script>alert("업데이트완료");document.location.href="/admin/order/export_reserve_ea?order_seq='.$_POST['order_seq'].'";</script>';
			exit;

		}else{

			$cfg_order	= config_load('order');
			$que	= "select * from fm_goods_export as exp where exp.order_seq=?";
			$query	= $this->db->query($que,array($order_seq));

			foreach($query->result_array() as $data){

				$export_items = $this->exportmodel->get_export_item($data['export_code']);

				foreach($export_items as $items){

					$que = "select
								ret.return_code
								,ret.refund_code
								,ret.status as return_status
								,ret_item.ea as return_ea
								,ret_item.give_reserve_ea
								,ret_item.give_reserve as give_reserve
								,ret_item.give_point as give_point
							from
								fm_order_return as ret
								left join fm_order_return_item as ret_item on ret_item.return_code=ret.return_code
							where
								ret_item.export_code=?
							";
					$return_where = array();
					$return_where[] = $items['export_code'];
					if($items['opt_type'] == "opt"){
						$que .= " and ret_item.option_seq=? and ret_item.suboption_seq=''";
						$return_where[] = $items['item_option_seq'];
					}

					if($items['opt_type'] == "sub"){
						$que .= " and ret_item.suboption_seq=?";
						$return_where[] = $items['option_seq'];
					}
					$query = $this->db->query($que,$return_where);
					$return_item = array();
					foreach($query->result_array() as $data1){

						$que = "select
								ref.status as refund_status
								,sum(ref_item.ea) as refund_ea
								,sum(ref_item.give_reserve) as give_reserve
								,sum(ref_item.give_point) as give_point
							from
								fm_order_refund as ref
								left join fm_order_refund_item as ref_item on ref_item.refund_code=ref.refund_code
							where
								ref_item.refund_code=?
							";
						$refund_where = array();
						$refund_where[] = $data1['refund_code'];
						if($items['opt_type'] == "opt"){
							$que .= " and ref_item.option_seq=? and ref_item.suboption_seq=''";
							$refund_where[] = $items['item_option_seq'];
						}

						if($items['opt_type'] == "sub"){
							$que .= " and ref_item.suboption_seq=?";
							$refund_where[] = $items['option_seq'];
						}
						$que .= " group by ref_item.option_seq,ref_item.suboption_seq ";
						$query2			= $this->db->query($que,$refund_where);
						$data2			= $query2->result_array();
						if($data2[0]){
							$return_item[]	= array_merge($data1,$data2[0]);
						}else{
							$return_item[]	= $data1;
						}
					}

					if(!in_array($data['export_code'],$arr_export_code)){
						$items['complete_date'] = $data['complete_date'];
						$items['rowspan']		= true;
					}else{
						$items['complete_date'] =  "";
						$items['rowspan']		= false;
					}

					$items['buy_confirm'] = $data['buy_confirm'];
					$items['confirm_date'] = $data['confirm_date'];
					$items['reserve_save'] = $data['reserve_save'];
					$items['goods_nm'] = $items['goods_name'];
					if($items['option1']) $items['goods_nm'] .= "/".$items['option1'];
					if($items['option2']) $items['goods_nm'] .= "/".$items['option2'];
					if($items['option3']) $items['goods_nm'] .= "/".$items['option3'];
					if($items['option4']) $items['goods_nm'] .= "/".$items['option4'];
					if($items['option5']) $items['goods_nm'] .= "/".$items['option5'];

					if(!$return_item) $return_item[] = array();

					$rows[$data['export_code']]['rows']++;
					if(count($return_item) > 1) $rows[$data['export_code']]['rows'] += count($return_item)-1;

					$items['return'] = $return_item;
					$loop[] = $items;

					$arr_export_code[] = $data['export_code'];

				}
			}

			$que	= "select * from fm_emoney where ordno=? order by emoney_seq desc";
			$query	= $this->db->query($que,array($order_seq));
			$emoney_loop = $query->result_array();

			$this->template->assign(array(
							'loop'		=> $loop,
							'emoney_loop'		=> $emoney_loop,
							'cfg_order'		=> $cfg_order,
							'rows'		=> $rows
			));

			$file_path	= $this->template_path();

			$this->template->define(array('tpl'	=>$file_path));
			$this->template->print_("tpl");
		}
	}

	// 주문 목록 데이터 json return
	public function search_order(){

		$this->load->model('usedmodel');

		unset($sc);
		$sc['perpage']			= '5';
		$sc['page']				= $this->input->post('page');
		$sc['totalCnt']			= $this->input->post('totalCnt');
		$sc['srcSdate']			= $this->input->post('srcSdate');
		$sc['srcEdate']			= $this->input->post('srcEdate');
		$sc['srcBkname']		= $this->input->post('srcBkname');
		$sc['srcBknum']			= $this->input->post('srcBknum');
		$sc['srcBkjukyo']		= $this->input->post('srcBkjukyo');
		$sc['srcSprice']		= $this->input->post('srcSprice');
		$sc['srcEprice']		= $this->input->post('srcEprice');
		$sc['srcStatus']		= $this->input->post('srcStatus');
		$stepArr				= array('15' => '주문접수', '25' => '결제확인', '35' => '상품준비',
						'40' => '부분출고준비', '45' => '출고준비', '50' => '부분출고완료',
						'55' => '출고완료', '60' => '부분배송중', '65' => '배송중',
						'70' => '부분배송완료', '75' => '배송완료', '95' => '주문무효');
		$result					= $this->ordermodel->get_bank_order_list($sc);
		if	($result['record']) foreach($result['record'] as $k => $data){
			$orderSeqArr[]						= $data['order_seq'];
			$result['record'][$k]['stepMsg']	= $stepArr[$data['step']];
			$result['record'][$k]['ismatch']	= 'N';
		}
		$matchArr				= $this->usedmodel->chk_bank_match($orderSeqArr);
		if	(is_array($matchArr) && count($matchArr) > 0 && $result['record']){
			foreach($result['record'] as $k => $data){
				if	(in_array($data['order_seq'], $matchArr)){
					$result['record'][$k]['ismatch']	= 'Y';
				}
			}
		}

		echo json_encode($result);
	}

	public function excel_download(){
		redirect('/admin/excel_spout/excel_download?category=2&searchflag=1');
	}

	public function delete_cart()
	{
		$cart_seq = $_POST['cart_seq'];

		if($cart_seq <= 0){
			echo 'ERROR';
			exit;
		}

		$data = array(
			'cart_seq' => $cart_seq,
			'distribution' => 'admin'
		);

		$this->db->delete('fm_cart', $data);

		echo $this->db->affected_rows();
		exit;
	}

	/**
	 * 휴면/탈퇴 회원 주문 조회
	 */
	public function personal_info() {
		$auth = $this->authmodel->manager_limit_act('personal_info_view');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->library('personalinfolibrary');
		$this->load->model('personalinfomodel');
		$this->load->helper('xssfilter');
		xss_clean_filter();

		$record = "";
		$aGetParams = $this->input->get();

		$sc = $aGetParams;

		$sc['page'] = (!empty($aGetParams['page'])) ? intval($aGetParams['page']) : 0;
		$sc['perpage'] = (!empty($aGetParams['perpage'])) ?	intval($aGetParams['perpage']) : 10;

		// 검색조건 적용하여 데이터 조회 후 가공
		$result = $this->personalinfomodel->getPersonalInfoList($sc);
		$data = $this->personalinfolibrary->dataRebuild($result);
		$this->template->assign('sc', $sc);

		$record = $data['result'];
		$sc['searchcount'] = $data['count'];

		$paginlay =  pagingtag($sc['searchcount'] ,$sc['perpage'], './personal_info?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);

		$search_form_path = dirname($this->template_path()).'/_personal_info_form.html';
		$this->template->define(array('search_form'=>$search_form_path));

		$config_order = config_load('order');
		$this->template->assign('personalInfoChk', $config_order['separating_personal_info']);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign(array('record' => $record));
		$this->template->print_("tpl");
	}
}
/* End of file order.php */
/* Location: ./app/controllers/admin/order.php */
