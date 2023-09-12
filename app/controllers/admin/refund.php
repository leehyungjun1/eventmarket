<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class refund extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->helper('order');

		$auth = $this->authmodel->manager_limit_act('refund_view');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}
	}

	public function index()
	{
		redirect("/admin/order/catalog");
	}

	public function important()
	{
		$val = $_GET['val'];
		$no = str_replace('important_','',$_GET['no']);
		$query = "update fm_order_refund set important=? where refund_seq=?";
		$this->db->query($query,array($val,$no));
	}

	public function set_search_refund(){

		$this->load->model('searchdefaultconfigmodel');

		$param_order = $_POST;
		$param_order['search_page'] = 'admin/refund/catalog';
		$this->searchdefaultconfigmodel->set_search_default($param_order);

		$callback = "parent.closeDialog('search_detail_dialog');";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function get_search_refund(){

		$this->load->model('searchdefaultconfigmodel');
		$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/refund/catalog');
		parse_str($data_search_default_str['search_info'], $data_search_default);
		echo json_encode($data_search_default);
	}

	public function catalog()
	{
		$this->admin_menu();
		$this->tempate_modules();

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('refund_view');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->load->model('returnmodel');
		$this->load->model('refundmodel');
		$this->load->model('ordermodel');
		$this->load->model('providermodel');

		$npay_use = npay_useck();

		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_GET) == 0 ){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/refund/catalog');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$_GET['sdate']			= $search_date['start_date'];
				$_GET['edate']			= $search_date['end_date'];
				$_GET['default_period']	= $data_search_default['default_period'];
				$_GET['refund_status']	= $data_search_default['default_refund_status'];
				$this->template->assign("search_default",$data_search_default);
			}

			// 기본 검색 기간 설정 :: 2018-01-02 lwh
			if(!$_GET['sdate'] && !$_GET['edate'] && !$data_search_default['default_period']){
				$_GET['sdate'] = date('Y-m-d',strtotime("-1 week"));
				$_GET['edate'] = date('Y-m-d');
			}
		}

		// 검색내용
		$sc = $_GET;
		$this->template->assign("sc",$sc);

		// 현재의 처리 프로세스
		$orders = config_load('order');
		$this->template->assign($orders);
		$this->template->define(array('order_process'=>$this->skin."/setting/_order_process.html"));

		// 기본통화 symbol
		$currency_symbol = get_currency_symbol($this->config_system['basic_currency']);
		$this->template->assign(array('currency_symbol' => $currency_symbol));

		$this->template->assign('query_string',get_query_string());
		$this->template->define(array('tpl' => $this->template_path()));
		$this->template->assign(array('npay_use' => $npay_use));
		$this->template->print_("tpl");
	}

	// 환불리스트 페이징용 :: 2017-09-11 lwh
	public function catalog_ajax(){

		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('ordermodel');
		$this->load->library('privatemasking');
		//$this->load->model('openmarketmodel');
		$_PARAM			= $_POST;//$_GET//$_POST
		$pagemode		= $_POST['pagemode'];
		unset($_PARAM['stepBox']);
		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		// 검색조건이 없을 경우 기본 세팅 검색조건을 가져옵니다.
		if( count($_PARAM) == 0 || $_PARAM['noquery'] ){
			$this->load->model('searchdefaultconfigmodel');
			$data_search_default_str = $this->searchdefaultconfigmodel->get_search_default_config('admin/refund/catalog');
			if($data_search_default_str['search_info']){
				parse_str($data_search_default_str['search_info'], $data_search_default);
				$search_date = $this->searchdefaultconfigmodel->get_search_format_date($data_search_default['default_period']);
				$_PARAM['sdate']			= $search_date['start_date'];
				$_PARAM['edate']			= $search_date['end_date'];
				$_PARAM['default_period']	= $data_search_default['default_period'];
				$_PARAM['refund_status']	= $data_search_default['default_refund_status'];
				$this->template->assign("search_default",$data_search_default);
			}

			// 기본 검색 기간 설정 :: 2018-01-02 lwh
			if(!$_PARAM['sdate'] && !$_PARAM['edate'] && !$data_search_default['default_period']){
				$_PARAM['sdate'] = date('Y-m-d',strtotime("-1 week"));
				$_PARAM['edate'] = date('Y-m-d');
			}
		}

		$page			= (trim($_PARAM['page'])) ? trim($_PARAM['page']) : 1;
		$bfStep			= trim($_PARAM['bfStep']);
		$no				= trim($_PARAM['nnum']);

		$query	= $this->refundmodel->get_refund_catalog_query($_PARAM);
		if($query){
			if	($page == 1){
				$_PARAM['query_type']	= 'total_record';
				$totalQuery				= $this->refundmodel->get_refund_catalog_query($_PARAM);
				$totalData				= $totalQuery->result_array();
				$no						= $totalData[0]['cnt'];
			}
	
			foreach($query->result_array() as $k => $data)
			{
				$data['mstatus']		= $this->refundmodel->arr_refund_status[$data['status']];
				$data['returns_status']	= $this->returnmodel->arr_return_status[$data['return_status']];
				$data['mpayment']		= $this->arr_payment[$data['payment']];
				
				// refund_price 에  배송비를 포함한 모든 환불금액이 저장되어 있으므로 배송비 금액을 가산할 필요 없음 by hed
				$data['refund_total']	= $data['refund_price']; //	+ $data['refund_delivery_price'] + $data['refund_delivery_cash'] + $data['refund_delivery_emoney'];
				
				$status_cnt[$data['status']]++;
				$data['status_cnt'] = $status_cnt;

				if($data['member_seq']){
					$data['member_type']	= $data['mbinfo_business_seq'] ? '기업' : '개인';
				}

				//개인정보 마스킹 표시
				$data = $this->privatemasking->masking($data, 'order');

				$record[$k]			= $data;
				$final_step			= $data['status'];
				$record[$k]['no']	= $no;
				$no--;

				if	($status_cnt[$data['status']] == 1)
				{
					if	($bfStep != $data['status']){
						$record[$k]['start']	= true;

						// 합계 집계내기 :: 2017-09-11 lwh
						$_PARAM['query_type']	= 'summary';
						$_PARAM['summary_type']	= $bfStep;
						$summary_query			= $this->refundmodel->get_refund_catalog_query($_PARAM);
						$summary['status_cnt'][$bfStep]	= 0;
						$summary['tot_price'][$bfStep]	= 0;
						foreach($summary_query->result_array() as $z => $sum){
							$summary['status_cnt'][$sum['status']]++;
							$summary['tot_price'][$sum['status']] += $sum['refund_price'];
						}
						$bfStep = $data['status'];
					}
					$ek = $k-1;
					if($ek >= 0 ){
						$record[$ek]['end']		= true;
					}
				}

				if	($no == 0){
					$record[$k]['end']	= true;
					$_PARAM['query_type']	= 'summary';
					$_PARAM['summary_type']	= $bfStep;
					$summary_query			= $this->refundmodel->get_refund_catalog_query($_PARAM);
					$summary['status_cnt'][$bfStep]	= 0;
					$summary['tot_price'][$bfStep]	= 0;
					foreach($summary_query->result_array() as $z => $sum){
						$summary['status_cnt'][$sum['status']]++;
						$summary['tot_price'][$sum['status']] += $sum['refund_price'];
					}
				}
				
				$record[$k]['k'] = $k;
			}
		}

		$file_path	= $this->template_path();
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign(array('stepBox' => $_PARAM['stepBox']));
		$this->template->assign(array('page' => $page));
		$this->template->assign(array('final_no' => $no));
		$this->template->assign(array('final_step' => $final_step));
		$this->template->assign(array('record' => $record));
		$this->template->assign(array('summary' => $summary));
		$this->template->assign(array('arr_refund_status' => $this->refundmodel->arr_refund_status));
		$this->template->print_("tpl");
	}

	// 기존 환불 처리 view 분기 처리 :: 2018-05-25 lwh
	public function view(){
		$this->admin_menu();
		$this->tempate_modules();

		$this->load->model('returnmodel');
		$this->load->model('refundmodel');
		$this->load->model('ordermodel');
		$this->load->model('accountallmodel');

		$refund_code = $_GET['no'];

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('refund_view');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		// 정산 반영 데이터 확인 :: 2018-05-25 lwh
		$data_refund 		= $this->refundmodel->get_refund($refund_code);		
		
		//환불코드로 등록된 데이터가 없을 경우 이전페이지로 이동 pjw
		if( is_null($data_refund) ) {
			pageBack("존재하지 않는 데이터 입니다.");
			exit;
		}
		
		$data_order		= $this->ordermodel->get_order($data_refund['order_seq']);
		
		$this->template->assign(array('pg_kind'=>$data_order['pg_kind'],'pg'=>$data_order['pg']));

		$magration		= config_load('accountall_setting','accountall_migration_date');
		if($magration['accountall_migration_date'] && ($magration['accountall_migration_date'] == '0000-00-00' || date('Y-m-01',strtotime('-3 month',$magration['accountall_migration_date'])) <= date('Y-m-d',$data_refund['regist_date'])) && !$_GET['test']){
			$this->refund_new_view($refund_code, $data_refund, $data_order);
		}else{
			$this->refund_new_view($refund_code, $data_refund, $data_order);
		}
	}

	// 새로운 반품 환불 방식 추가 :: 2018-05-25 lwh
	public function refund_new_view($refund_code, $data_refund, $data_order){
		$this->admin_menu();
		$this->tempate_modules();

		$this->load->helper('text');
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->model('membermodel');
		$this->load->model('managermodel');
		$this->load->model('couponmodel');
		$this->load->model('promotionmodel');
		$this->load->model('giftmodel');
		$this->load->library('refundlibrary');

		$cfg_order = config_load('order');
		
		// 전액 예치금/이머니 결제 일 경우
		$data_refund['refund_method_cash_only']		= false;
		if( $data_order['settleprice'] == 0 && ($data_order['emoney'] > 0 || $data_order['cash'] > 0) && $data_refund['refund_method'] ){
			$data_refund['refund_method']			= 'cash';
			$data_refund['refund_method_cash_only']	= true;
		}

		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		if(!$reserves['cash_use']) $reserves['cash_use'] = "N";
		$this->template->assign($reserves);

		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');

		//배송비 환불의 경우 처리
		$shipping_price_return	= false;
		if($data_refund['refund_type'] == 'shipping_price'){
			$shipping_price_return	= true;
			$refund_shipping_info	= $this->refundmodel->get_provider_refund($refund_code);

			$refund_shipping_list	= array();
			foreach((array)$refund_shipping_info as $row){
				$refund_shipping_list[$row['refund_provider_seq']]	= $row;
				if($row['refund_provider_seq'] > 1 || $data_refund['refund_provider_seq'] == ''){
					$data_refund['refund_provider_seq']		= $row['refund_provider_seq'];
					$data_refund['refund_provider_name']	= $row['provider_name'];
				}
			}
		}

		$order_seq			= $data_refund['order_seq'];
		$data_order_item	= $this->ordermodel->get_item($order_seq);

		$npay_use = npay_useck();
		//npay 사용여부 확인, 취소사유 코드 불러오기
		if($npay_use && $data_order['pg'] == "npay"){

			$this->load->library('naverpaylib');
			$npay_return_hold	= $this->naverpaylib->get_npay_code("cancel_hold");
			if($npay_return_hold[strtoupper($data_refund['npay_flag'])]){
				$data_refund['npay_flag_msg'] = $npay_return_hold[strtoupper($data_refund['npay_flag'])];
			}else{
				$data_refund['npay_flag_msg'] = '';
			}

		}

		## 하위 주문번호 조회(원주문의 맞교환(재주문)건)
		$all_order_seq = array($order_seq);

		if($data_order['top_orign_order_seq']){

			$query = "select order_seq from fm_order where top_orign_order_seq='".$data_order['top_orign_order_seq']."'";
			$query = $this->db->query($query);
			foreach($query->result_array() as $sub_order) $all_order_seq[] = $sub_order['order_seq'];

			$query						= "select * from fm_order where order_seq=?";
			$query						= $this->db->query($query,$data_order['top_orign_order_seq']);
			$ori_order					= $query->row_array();
			$orign_order_seq			= $all_order_seq[] = $data_order['top_orign_order_seq'];
			$data_order['settleprice']	= $ori_order['settleprice'];
			$data_order['cash']			= $ori_order['cash'];
			$data_order['emoney']		= $ori_order['emoney'];
			$data_order['enuri']		= $ori_order['enuri'];
			$data_order['shipping_cost']= $ori_order['shipping_cost'];
			$new_order_seq				= $data_order['order_seq'];
		}else{
			$orign_order_seq			= $data_order['order_seq'];
			$new_order_seq				= '';
		}
		$all_order_seq = array_unique($all_order_seq);

		$data_refund_item	= $this->refundmodel->get_refund_item($refund_code,$orign_order_seq,$new_order_seq);
		$process_log 		= $this->ordermodel->get_log($data_refund['order_seq'],'process',array('refund_code'=>$refund_code));
		$data_member		= $this->membermodel->get_member_data($data_refund['member_seq']);

		# 처리자 @2015/07/30 pjm
		$manager			= $this->managermodel->get_manager($data_refund['manager_seq']);
		$data_refund['manager_name'] = $manager['mname'];

		// 원주문의 배송정보
		$data_shipping		= $this->ordermodel->get_order_shipping($orign_order_seq);
		if	($data_shipping)foreach($data_shipping as $k => $ship){

			//복원된 배송비쿠폰 여부 shipping_coupon_sale
			if($ship['shipping_coupon_down_seq']){
				$ship['restore_used_coupon_refund'] = $this->couponmodel->restore_used_coupon_refund($ship['shipping_coupon_down_seq']);
			}
			//복원된 배송비프로모션코드 여부
			if($ship['shipping_promotion_code_seq']){
				//발급받은 프로모션 타입(일반, 개별) - 일반(공용) 코드는 복원 불가(계속 사용가능)
				if($ship['shipping_promotion_code_sale'] > 0){
					$shipping_promotion = $this->promotionmodel->get_download_promotion($ship['shipping_promotion_code_seq']);
					$ship['shipping_promotion_type'] = $shipping_promotion['type'];
				}
				$ship['restore_used_promotioncode_refund'] = $this->promotionmodel->restore_used_promotioncode_refund($ship['shipping_promotion_code_seq']);
			}

			$ship['international']			= $data_order['international'];
			if($ship['shipping_summary']){
				$ship['default_type']	= $ship['shipping_summary']['default_type'];
				$ship['first_cost']		= $ship['shipping_summary']['first_cost'];
				$ship['max_cost']		= $ship['shipping_summary']['max_cost'];
				$ship['min_cost']		= $ship['shipping_summary']['min_cost'];
			}
			$ships[$ship['shipping_seq']]	= $ship;
		}

		// 개인정보 조회 로그
		//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderexcel', 'exportexcel'
		$this->load->model('logPersonalInformation');
		$this->logPersonalInformation->insert('refund',$this->managerInfo['manager_seq'],$data_refund['refund_seq']);

		// 환불수단 정의
		$refund_payment = $this->refundlibrary->get_refund_payment($data_order, $data_refund);

		/* 반품에 의한 환불일경우 주문시 지급 마일리지합계 표시 */
		if($data_refund['refund_type']=='return'/* && !$cfg_order['buy_confirm_use']*/)
		{
			$optquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_option where order_seq=?";
			$optquery = $this->db->query($optquery,$data_refund['order_seq']);
			$optres = $optquery->row_array();

			$suboptquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_suboption where order_seq=?";
			$suboptquery = $this->db->query($suboptquery,$data_refund['order_seq']);
			$suboptres = $suboptquery->row_array();

			$tot['reserve_sum'] = $optres['reserve_sum']+$suboptres['reserve_sum'];
			$tot['point_sum'] = $optres['point_sum']+$suboptres['point_sum'];
		}

		// 반품정보 추출 :: 2018-05-28 lwh
		$query	= "SELECT * FROM fm_order_return WHERE refund_code=?";
		$query	= $this->db->query($query,$data_refund['refund_code']);
		$res	= $query->row_array();
		$data_refund['returns_status'] = $this->returnmodel->arr_return_status[$res['status']];
		
		// 반품 배송비 추가 정보 추출 :: 2018-05-28 lwh
		$data_refund['refund_ship_duty']		= $res['refund_ship_duty'];
		$data_refund['refund_ship_type']		= $res['refund_ship_type'];
		$data_refund['return_shipping_price']	= $res['return_shipping_price'];
		
		if(!$data_refund['refund_ship_duty']){
			$data_refund['refund_ship_duty'] = "seller";
		}
		

		$data_refund['mstatus']			= $this->refundmodel->arr_refund_status[$data_refund['status']];
		$data_refund['mrefund_type']	= $this->refundmodel->arr_refund_type[$data_refund['refund_type']];
		$data_refund['mcancel_type']	= $this->refundmodel->arr_cancel_type[$data_refund['cancel_type']];
		$data_order['mpayment']			= $this->arr_payment[$data_order['payment']];

		// 기본 마일리지 유효기간 계산
		if(!$data_refund['refund_emoney_limit_date']){
			$reserve_str_ts			= '';
			$reserve_limit_date		= '';
			$cfg_reserves			= config_load('reserve');
			if( $cfg_reserves['reserve_select'] == 'direct' ){
				$reserve_str_ts = "+".$cfg_reserves['reserve_direct']." month";
				$reserve_limit_date = date('Y-m-d',strtotime($reserve_str_ts));
			}
			if( $cfg_reserves['reserve_select'] == 'year' ){
				$reserve_str_ts = "+".$cfg_reserves['reserve_year']." year";
				$reserve_limit_date = date('Y-12-31',strtotime($reserve_str_ts));
			}
			$data_refund['refund_emoney_limit_date'] = $reserve_limit_date;
		}

		if($data_order['international']=='international'){
			$data_order['real_shipping_cost'] = $data_order['international_cost'];
		}else{
			$data_order['real_shipping_cost'] = $data_order['shipping_cost'];
		}

		$goods_exist				= 0;
		$order_goods_cnt			= 0; // 기획팀 요청에 의해 반품 배송비에 의한 줄 병합 기능 제거 by hed 190813_신정산_솔루션기획팀_QA_1차.pptx 7page ($data_refund['refund_ship_type'] == 'M' && $data_refund['refund_ship_duty'] == 'buyer')	? 1 : 0;
		$refund_items				= array();
		$shipping_group_array		= array();
		$refund_ship				= array();
		$return_formula_tmp			= array();	//회수해야할 마일리지/포인트 계산식
		$all_refund_option_arr		= array();
		$return_shipping_cost_tmp	= array();	//동일배송그룹의 환불배송비
		$total_price = 0;
		
		//복원된 주문서쿠폰 여부
		if($data_order['ordersheet_seq']){
			$data_order['use_ordersheetcoupon'] = $this->couponmodel->get_download_coupon($data_order['ordersheet_seq']);
			$data_order['restore_used_ordersheetcoupon_refund'] = $this->couponmodel->restore_used_coupon_refund($data_order['ordersheet_seq']);
		}
		
		//환불::저장되지 않은 상태 여부(저장버튼을 누르면 상태변경이 필수) 2019-11-13 pjm
		if($data_refund['status'] == 'request' && (int)$data_refund['refund_price'] == 0) $refund_unsaved_state = true;
		else $refund_unsaved_state = false;

		// 동일 배송그룹의 배송비 환불 완료 금액 :  해당 배송그룹의 배송비가 환불되었는지 체크
		$arr_order_item_shipping_seq = array();
		foreach($data_order_item as $order_item){
			if(!in_array($order_item['shipping_seq'], $arr_order_item_shipping_seq)){
				$arr_order_item_shipping_seq[] = $order_item['shipping_seq'];
			}
		}
		foreach($arr_order_item_shipping_seq as $order_item_shipping_seq){
			$complete_refund_code = '';
			if(!$refund_unsaved_state){
				$complete_refund_code = $refund_code;
			}
			$complete_shipping = $this->refundmodel->get_refund_complete_shipping_price($order_seq,$complete_refund_code,$order_item_shipping_seq);
			if($complete_shipping['except_shipping_data']['refund_shipping_cost'] > 0){
				unset($ships[$order_item_shipping_seq]);
				$ships[$order_item_shipping_seq]['shipping_seq'] = $order_item_shipping_seq;
			}
		}

		$refund_emoney_limit_use = false; // 환불 마일리지 유효기간 사용 여부

		// 환불 상품 데이터 정리
		foreach($data_refund_item as $k => $data){

			// 환불 수량 없을때는 스킵 :: 2018-05-30 lwh
			// 위 작업으로인해 배송비 환불이 작동하지 않아 조건 추가 :: 2018-07-23 pjw
			if($data['ea'] == 0 && $data_refund['refund_type'] != 'shipping_price')	continue;			

			$tot['order_ea'] += $data['option_ea'];		//총계:주문수량
			$tot['ea']		 += $data['ea'];			//총계:환불수량
			// 3차 환불 개선으로 실물,티켓 구분 :: 2018-11- lkh
			$tot['goods_kind']= $data['goods_kind'];
	
			## 환불처리 테이블 rowspan 구하기 @2015-07-24 pjm
			if(in_array($data['item_seq'],$arr_rows['item_seq']) && in_array($data['option_seq'],$arr_rows['option_seq'])){
				$refund_rows[$data['option_seq']]++;
				$data['first_rows'] = false;
			}else{
				$refund_rows[$data['option_seq']] = 1;
				$data['first_rows'] = true;
			}
			$arr_rows['item_seq'][] = $data['item_seq'];
			$arr_rows['option_seq'][] = $data['option_seq'];

			## 맞교환 주문건 일때
			if($data['top_item_option_seq']){
				if($data['opt_type'] == "opt"){
					$query = "select * from fm_order_item_option where item_option_seq=?";
				}else{
					$query = "select * from fm_order_item_suboption where item_option_seq=?";
				}
				$query = $this->db->query($query,$data['top_item_option_seq']);
				$ori_option = $query->row_array();

				$orign_item_seq			= $ori_option['item_seq'];
				$data['price']			= $ori_option['price'];
				$data['consumer_price'] = $ori_option['supply_price'];
				$data['supply_price']	= $ori_option['consumer_price'];
				$data['supply_price']	= $ori_option['consumer_price'];

			}else{
				$orign_item_seq		= $data['item_seq'];
			}
			
			//티켓상품
			if ( $data['goods_kind'] == 'coupon' ) {//

				$data_return		= $this->returnmodel->get_return_refund_code($refund_code);
				$data_return_item 	= $this->returnmodel->get_return_item($data_return['return_code']);
				$data_refund_item[$k]['couponinfo'] = get_goods_coupon_view($data_return_item[0]['export_code']);
				$data_refund_item[$k]['coupon_use_return'] = $data_refund_item[$k]['couponinfo']['coupon_use_return'];

				if ( $data['coupon_refund_type'] == 'emoney' ) {//유효기간지나면
					$tot['coupon_valid_over']++;
					$tot['price'] += $data['coupon_refund_emoney'];//마일리지으로 추가
					$data_order['emoney'] += $data['coupon_refund_emoney'];//마일리지으로 추가
				}else{
					$tot['price'] += $data['coupon_remain_price'];
				}

				//총 할인액(주문기준)				
				// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
				// 에누리 추가 :: 2018-07-31 pjw
				$data['total_sale'] = $data['event_sale'] + $data['multi_sale'] + ($data['member_sale']*$data['option_ea'])+$data['coupon_sale']
										+$data['fblike_sale']+$data['mobile_sale']+$data['referer_sale']
										+$data['promotion_code_sale'] + ($data['enuri_sale_unit']*$data['option_ea']) + $data['enuri_sale_rest'] + $data['unit_ordersheet'];

				if ( !in_array($data['item_seq'],$itemCoupontot) ) {
					$itemCoupontot[] = $data['item_seq'];

					//promotion sale
					// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
					// 에누리 추가 :: 2018-07-31 pjw
					$tot['event_sale']			+= $data['event_sale'];
					$tot['multi_sale']			+= $data['multi_sale'];
					$tot['member_sale']			+= $data['member_sale']*$data['ea'];
					$tot['coupon_sale']			+= $data['coupon_sale'];
					$tot['coupon_sale']			+= $data['unit_ordersheet'];
					$tot['fblike_sale']			+= $data['fblike_sale'];
					$tot['mobile_sale']			+= $data['mobile_sale'];
					$tot['referer_sale']		+= $data['referer_sale'];
					$tot['promotion_code_sale'] += $data['promotion_code_sale'];
					$tot['enuri_sale']			+= ($data['enuri_sale_unit']*$data['option_ea']) + $data['enuri_sale_rest'];

					if($data_refund['refund_type']=='return'/* && !$cfg_order['buy_confirm_use']*/){
						$tot['return_reserve'] += $data['reserve']*$data['ea'];
						$tot['return_point'] += $data['point']*$data['ea'];
					}
				}

				$refund_option_info = $data_refund['order_seq']."".$data['opt_type']."".$data['option_seq'];

				//동일주문의 기 환불금액 pjm
				//환불완료금액에 마일리지, 예치금 추가 :: 2018-07-27 pjw
				if(!in_array($refund_option_info,$all_refund_option_arr)){
					$refund_complete = $this->refundmodel->get_refund_complete_price($all_order_seq,$data['option_seq'],$data['opt_type'],$data_refund['refund_code']);			
					
					// 현재 데이터에서 마일리지와 예치금을 더해서 실제 환불데이터랑 맞지않음 환불된 데이터로 수정 :: 2018-07-31 pjw
					$data['refund_complete_ea']			=	$refund_complete['complete_ea'];
					$data['refund_complete_price']		=	$refund_complete['complete_price'];
					$data['refund_complete_delivery']	=	$refund_complete['refund_delivery_price'] + $refund_complete['refund_delivery_emoney'] + $refund_complete['refund_delivery_cash'];

					// 기환불 배송비금액은 상품row에 저장된 값에 따라 변동되므로 배송정보에 저장하여 일관성을 유지한다.
					// 동일 배송그룹에 배송비 금액이 다른 경우가 발생한다면 배송그룹 출력 프로세스 검토 필요 by hed
					if(empty($ships[$data['shipping_seq']]['refund_delivery_price'])){
						$ships[$data['shipping_seq']]['refund_delivery_price']		= $data['refund_delivery_price'];
					}
					if(empty($ships[$data['shipping_seq']]['refund_complete_delivery'])){
						$ships[$data['shipping_seq']]['refund_complete_delivery']		= $data['refund_complete_delivery'];
					}

					// 상품별 환불금액 계산 2018-08-31
					// 3차 환불 개선으로 환불위약금 추가 :: 2018-11- lkh
					$data['refund_price']			= $refund_complete['refund_goods_price']+$refund_complete['refund_emoney']+$refund_complete['refund_cash']-$refund_complete['coupon_deduction_price'];
	
					$tot['refund_complete_price']		+=	$refund_complete['complete_price'];
					$tot['refund_complete_total']		+=	$data['refund_price'] + $data['refund_complete_delivery'];

					$all_refund_option_arr[] = $refund_option_info;
				}
				// 환불 된 기 배송비 금액 표기 :: 2018-05-29 lwh
				$refund_delivery_price_sum = $data['refund_complete_delivery'];
				
				// 티켓상품은 배송비 행 노출하지 않도록 처리 :: 2018-07-13 lkh
				$data_refund['refund_ship_duty'] = "";

				// 3차 환불 개선으로 환불 위약금 :: 2018-11- lkh
				$refundPenaltyDeductiblePriceTmp += $data['coupon_deduction_price'];

			}else{

				$tot['price']		+= $data['price']*$data['ea'];

				//총 할인액(주문기준)
				// 에누리 추가 :: 2018-07-31 pjw
				$data['total_sale'] = $data['event_sale'] + $data['multi_sale'] + ($data['member_sale']*$data['option_ea'])+$data['coupon_sale']
										+$data['fblike_sale']+$data['mobile_sale']+$data['referer_sale']
										+$data['promotion_code_sale'] 
										+($data['enuri_sale_unit']*$data['option_ea']) + $data['enuri_sale_rest'] 
										+ $data['unit_ordersheet'];
				// promotion sale
				// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
				// 에누리 추가 :: 2018-07-31 pjw
				$tot['event_sale']			+= $data['event_sale'];
				$tot['multi_sale']			+= $data['multi_sale'];
				$tot['member_sale']			+= $data['member_sale']*$data['ea'];
				$tot['coupon_sale']			+= $data['coupon_sale'];
				$tot['coupon_sale']			+= $data['unit_ordersheet'];
				$tot['fblike_sale']			+= $data['fblike_sale'];
				$tot['mobile_sale']			+= $data['mobile_sale'];
				$tot['referer_sale']		+= $data['referer_sale'];
				$tot['promotion_code_sale']	+= $data['promotion_code_sale'];
				$tot['enuri_sale']			+= ($data['enuri_sale_unit']*$data['option_ea']) + $data['enuri_sale_rest'];

				$refund_option_info = $data_refund['order_seq']."".$data['opt_type']."".$data['option_seq'];

				//동일주문의 기 환불금액 pjm
				//환불완료금액에 마일리지, 예치금 추가 :: 2018-07-27 pjw
				if(!in_array($refund_option_info,$all_refund_option_arr)){
					$refund_complete = $this->refundmodel->get_refund_complete_price($all_order_seq,$data['option_seq'],$data['opt_type'],$data_refund['refund_code']);			
					
					// 현재 데이터에서 마일리지와 예치금을 더해서 실제 환불데이터랑 맞지않음 환불된 데이터로 수정 :: 2018-07-31 pjw
					$data['refund_complete_ea']			=	$refund_complete['complete_ea'];
					$data['refund_complete_price']		=	$refund_complete['refund_goods_price'] + $refund_complete['refund_emoney'] + $refund_complete['refund_cash'];
					$data['refund_complete_delivery']	=	$refund_complete['refund_delivery_price'] + $refund_complete['refund_delivery_emoney'] + $refund_complete['refund_delivery_cash'];

					// 기환불 배송비금액은 상품row에 저장된 값에 따라 변동되므로 배송정보에 저장하여 일관성을 유지한다.
					// 동일 배송그룹에 배송비 금액이 다른 경우가 발생한다면 배송그룹 출력 프로세스 검토 필요 by hed
					if(empty($ships[$data['shipping_seq']]['refund_delivery_price'])){
						$ships[$data['shipping_seq']]['refund_delivery_price']		= $data['refund_delivery_price'];
					}
					if(empty($ships[$data['shipping_seq']]['refund_complete_delivery'])){
						$ships[$data['shipping_seq']]['refund_complete_delivery']		= $data['refund_complete_delivery'];
					}

					// 상품별 환불금액 계산 2018-08-31
					// 3차 환불 개선으로 환불위약금 추가 :: 2018-11- lkh
					$data['refund_price']			= $refund_complete['refund_goods_price']+$refund_complete['refund_emoney']+$refund_complete['refund_cash']-$refund_complete['coupon_deduction_price'];
	
					$tot['refund_complete_price']		+=	$data['refund_complete_price'];
					$tot['refund_complete_total']		+=	$data['refund_complete_price'] + $data['refund_complete_delivery'];

					$all_refund_option_arr[] = $refund_option_info;
				}
				// 환불 된 기 배송비 금액 표기 :: 2018-05-29 lwh
				$refund_delivery_price_sum = $data['refund_complete_delivery'];
			}

			//차감할 마일리지, 포인트 pjm
			if($data_refund['refund_type']=='return' && $data['ea'] > 0 /* && !$cfg_order['buy_confirm_use']*/){
				$tot['return_reserve']	+= $data['give_reserve'];
				$tot['return_point']	+= $data['give_point'];
			}

			if( $data['refund_item_seq'] ) {
				$refund_ship[$data['shipping_seq']] = $data['refund_item_seq'];
			}

			if($data['ea'] > 0){

				//복원된 할인쿠폰 여부
				if($data['download_seq']){
					$data['use_coupon'] = $this->couponmodel->get_download_coupon($data['download_seq']);
					$data['restore_used_coupon_refund'] = $this->couponmodel->restore_used_coupon_refund($data['download_seq']);
				}

				//복원된 프로모션코드 여부
				if($data['promotion_code_seq']){
					$data['use_promotion'] = $this->promotionmodel->get_download_promotion($data['promotion_code_seq']);
					$data['restore_used_promotioncode_refund'] = $this->promotionmodel->restore_used_promotioncode_refund($data['promotion_code_seq']);
				}

				//청약철회상품체크
				unset($ctgoods);
				$ctgoods = $this->goodsmodel->get_goods($data['goods_seq']);
				$data['cancel_type'] = $ctgoods['cancel_type'];
				$data_refund_item[$k]['cancel_type'] = $ctgoods['cancel_type'];

				if( $data['opt_type']  == 'opt' && !$data['new_option_seq'] ) {
					$data['inputs'] = $this->ordermodel->get_input_for_option($data['item_seq'], $data['option_seq']);
				}
				
				// 추가옵션의 경우 개당판매가(slae_price)를 계산한 후 DB에 저장하지 않고 있음.
				// 이로 인해 추가옵션 환불 시 salse_price를 정상적으로 추출할 수 없어
				// 기존에는 price로 대처했으나 할인 금액을 추가한 기준으로 재계산함. by hed
				// $tmp_row_good_price		: 상품종판매금액(할인전)		: 판매가 * 주문수량
				// $tmp_row_discont_price	: 상품종할인가				: total_sale (해당 상품에 관련된 모든 할인 내역(수량포함))
				// $tmp_row_sale_price		: 상품종판매가				: 상품종판매금액 - 상품종할인가
				// $tmp_each_sale_price		: 개당판매가					: 버림(상품종판매가 / 주문수량)
				// $tmp_each_sale_rest		: 개당판매가나머지			: 상품종판매가 - (개당판매가 * 주문수량)
				$tmp_row_good_price			= $data['price'] * $data['option_ea'];
				$tmp_row_discont_price		= $data['total_sale']; 
				$tmp_row_sale_price			= $tmp_row_good_price - $tmp_row_discont_price;
				$tmp_each_sale_price		= pfloor($tmp_row_sale_price / $data['option_ea']);
				$tmp_each_sale_rest			= $tmp_row_sale_price - ($tmp_each_sale_price * $data['option_ea']);		// 최종 환불금액은 하단에서 가산
				$modify_sale_price = false;
				if((empty($data['sale_price']) || $data['sale_price'] == '0' || $data['sale_price'] == '0.00') && $tmp_each_sale_price > 0){
					$data['sale_price'] = $tmp_each_sale_price;
					$modify_sale_price = true;
				}
				
				$refund_items[$data['item_seq']]['items'][]					= $data;
				$refund_items[$data['item_seq']]['refund_ea']				+= $data['ea'];
				// 배송그룹별 환불 신청 수량 by hed
				$refund_items[$data['item_seq']]['refund_shipping_each_ea'][$data['shipping_seq']] += $data['ea'];
				$refund_items[$data['item_seq']]['shipping_policy']			= $data['shipping_policy'];
				$refund_items[$data['item_seq']]['goods_shipping_policy']	= $data['shipping_unit']?'limited':'unlimited';
				$refund_items[$data['item_seq']]['unlimit_shipping_price']	= $data['goods_shipping_cost'];	//개별배송비
				$refund_items[$data['item_seq']]['limit_shipping_price']	= $data['basic_shipping_cost'];	//기본배송비
				$refund_items[$data['item_seq']]['limit_shipping_ea']		= $data['shipping_unit'];		//배송수량
				$refund_items[$data['item_seq']]['limit_shipping_subprice'] = $data['add_shipping_cost'];	//추가배송비

				$goods[$data['goods_seq']]++;

				if($data['goods_type'] == "goods") $goods_exist++;

				$data_refund_item[$k]['inputs']	= $data['inputs'];

				## 환불신청 갯수 pjm 2015-03-12
				$total_refund_ea += $data['refund_ea'];

				## 회수해야할 마일리지 및 포인트 계산식 노출 @2015-09-17 pjm
				$return_formula_tmp['reserve'][]	= "".get_currency_price($data['reserve'])."*".$data['ea']."";
				$return_formula_tmp['point'][]		= "".get_currency_price($data['point'])."*".$data['ea']."";

				$refund_total_rows++;
				$return_shipping = 1;

			}else{
				$return_shipping = 0;
				if($shipping_price_return === true && isset($refund_shipping_list[$data['provider_seq']]) === true){
					$return_shipping	= 1;

					if( $data['refund_item_seq'] ) {
						$refund_ship[$data['shipping_seq']] = $data['refund_item_seq'];
					}
				}
			}

			// 실 환불금액 저장된 내역 없을때 기본값 지정 :: 2018-05-30 lwh
			$data['refund_item_cash']	= ($data['cash_sale_unit'] * $data['ea']);
			$data['refund_item_emoney'] = ($data['emoney_sale_unit'] * $data['ea']);
			$data['request_enuri_sale'] = ($data['enuri_sale_unit'] * $data['ea']);
			if ($refund_unsaved_state){
				// 나머지(짜투리)는 중복되서 가산되면 안 되므로 최초 환불 신청시에만 나머지를 가산. 
				// 동일 아이템의 최초 신청 여부는 기 환불 금액이 있는지 여부로 확인 by hed 2019-10-16
				if(empty($data['refund_complete_price'])){
					$data['refund_item_cash'] =  $data['refund_item_cash'] + $data['cash_sale_rest'];
					$data['refund_item_emoney'] =  $data['refund_item_emoney'] + $data['emoney_sale_rest'];
					$data['request_enuri_sale'] =  $data['request_enuri_sale'] + $data['enuri_sale_rest'];
				}

				if( $data_refund['refund_cash'] == '0.00' )		{
					$data_refund['refund_cash']		+= $data['refund_item_cash'];
				}
				if( $data_refund['refund_emoney'] == '0.00')	{
					$data_refund['refund_emoney']	+= $data['refund_item_emoney'];
				}
				
				$sale_price_tmp = ($data['sale_price'] * $data['ea']);
				
				// 나머지(짜투리)는 중복되서 가산되면 안 되므로 최초 환불 신청시에만 나머지를 가산. 
				if(empty($data['refund_complete_price']) && $modify_sale_price){
					$sale_price_tmp = $sale_price_tmp + $tmp_each_sale_rest;
				}

				if($sale_price_tmp > 0){
					$data['refund_goods_price'] = $sale_price_tmp;
					// 추가옵션은 마일리지, 에누리, 예치금은 이미 sale_price 에 포함되어있음. 
					if($data['opt_type'] === 'opt') {
						$data['refund_goods_price'] -= ($data['refund_item_cash'] + $data['refund_item_emoney'] + $data['request_enuri_sale']);
					}
				}else{
					$data['refund_goods_price'] = 0;
				}
			}else{
				$data['refund_item_cash']	= ($data['refund_cash_sale_unit'] * $data['ea']) + $data['refund_cash_sale_rest'];
				$data['refund_item_emoney']	= ($data['refund_emoney_sale_unit'] * $data['ea']) + $data['refund_emoney_sale_rest'];
			}

			// 배송비 환불금액 저장된 내역 없을때 기본값 지정 :: 2018-05-30 lwh 
			// 에누리가 빠져있어 추가 처리 :: 2018-07-09 lkh
			// 마일리지, 에누리 둘다 빠져있어 추가 :: 2018-08-01 pjw
			// 배송비 할인 (코드, 쿠폰) 추가 :: 2018-08-24 pjw
			$refund_delivery_price = ($data['refund_delivery_price'] + $data['refund_delivery_emoney'] + $data['refund_delivery_cash']);

			if ($refund_unsaved_state){
				if($ships){
					$return_shipping_cost_tmp[$data['shipping_seq']] = $ships[$data['shipping_seq']]['shipping_cost'] - 
						(($ships[$data['shipping_seq']]['cash_sale_unit'])+($ships[$data['shipping_seq']]['cash_sale_rest'])) - 
					(($ships[$data['shipping_seq']]['emoney_sale_unit'])+($ships[$data['shipping_seq']]['emoney_sale_rest'])) - 
					(($ships[$data['shipping_seq']]['enuri_sale_unit'])+($ships[$data['shipping_seq']]['enuri_sale_rest'])) - 
					$ships[$data['shipping_seq']]['shipping_promotion_code_sale'] - $ships[$data['shipping_seq']]['shipping_coupon_sale'];
				}else{
					$return_shipping_cost_tmp[$data['shipping_seq']] = 0;
				}
			}else{
				$return_shipping_cost_tmp[$data['shipping_seq']] += $data['refund_delivery_price'];
			}

			$return_shipping_cost_tmp[$data['shipping_seq']] = $return_shipping_cost_tmp[$data['shipping_seq']] > 0 ? $return_shipping_cost_tmp[$data['shipping_seq']] : 0;

			// 3차 환불 개선으로 배송비할인 내역 추가 :: 2018-11- lkh
			$tot['shipping_promotion_code_sale']	+= $ships[$data['shipping_seq']]['shipping_promotion_code_sale'];
			$tot['shipping_coupon_sale']			+= $ships[$data['shipping_seq']]['shipping_coupon_sale'];
			if(!$tot['shipping_iffree']) $tot['shipping_iffree'] = 0;
			if($ships[$data['shipping_seq']]['default_type'] && $ships[$data['shipping_seq']]['default_type'] == 'iffree'){
				// 조건부 배송비일 경우 할인 여부와 상관 없이 안내 문구를 노출해야함 by hed
				$tot['shipping_iffree'] = 1;
				// if( ($ships[$data['shipping_seq']]['shipping_promotion_code_sale'] + $ships[$data['shipping_seq']]['shipping_coupon_sale'] < $ships[$data['shipping_seq']]['max_cost']) && ( ($ships[$data['shipping_seq']]['shipping_promotion_code_sale'] + $ships[$data['shipping_seq']]['shipping_coupon_sale'] + $ships[$data['shipping_seq']]['delivery_cost']) < $ships[$data['shipping_seq']]['max_cost']) ){
				// }
			}

			// 배송비 마일리지 예치금 표기 :: 2018-06-08 lwh
			$ships[$data['shipping_seq']]['refund_delivery_cash']	+= $data['refund_delivery_cash'];
			$ships[$data['shipping_seq']]['refund_delivery_emoney'] += $data['refund_delivery_emoney'];

			// 반품가능 수량으로 배송비 노출 여부 판단 (반품처리가 안되거나 구매확정이 되지않은경우 판매자 부담이여도 미노출) :: 2018-07-19 pjw
			$deliv_refurn			= 'N';	//배송비 환불여부

			// 배송그룹내 마지막 환불 코드
			$refund_maxcode = $this->refundmodel->shipping_refund_maxcode($data_refund['order_seq'], $data['shipping_seq']);

			// 배송그룹별 주문수량, 취소수량, 배송수량
			$rest_ea_data = $this->refundmodel->shipping_refund_ea($data['shipping_seq']);

			// 배송그룹에 대한 남은 반품개수
			$rest_unrefund_ea = $this->refundmodel->shipping_unrefund_order($data['shipping_seq']);
			
			// 주문수량-반품수량-취소수량 = 출고예정수량
			$unrefund_ea = $rest_unrefund_ea['total_unrefund_ea'] - $rest_ea_data['cancel_ea'];

			// 배송그룹별로 환불신청수량을 집계하여 환불이 아닌 취소요청인 경우 
			// 동일배송그룹의 취소수량이 주문수량과 동일할때 배송비를 환불 할 수 있도록 수정 by hed
			if(empty($request_refund_ea[$data['shipping_seq']])){
				$request_refund_ea[$data['shipping_seq']] = 0;
			}
			if($data_refund['status'] != 'complete'){
				// 배송그룹별 환불 신청 수량 by hed
				// 초기화 후 재계산
				$request_refund_ea[$data['shipping_seq']] = 0;
				// item_seq & shipping_seq 별 수량을 shipping_seq 기준으로 합산
				foreach($refund_items as $k_item_seq => $tmp_refund_items_row){
					$request_refund_ea[$data['shipping_seq']] += $tmp_refund_items_row['refund_shipping_each_ea'][$data['shipping_seq']];
				}
			}

			# 최초 배송비 환불
			#	배송그룹내 출고예정수량이 없음
			#	and
			#	현재 환불코드가 마지막 환불코드 -> 마지막 환불시에만 최초배송비 환불이 가능함.
			#	and
			#	환불완료수와 환불신청수가 전체 갯수와 일치 -> 배송그룹의 모든 상품이 환불처리
			if($rest_ea_data != null){
				if(
					$unrefund_ea == 0 
					&& $refund_maxcode == $data_refund['refund_code'] 
					&& $request_refund_ea[$data['shipping_seq']] + $rest_unrefund_ea['total_refund_ea_complete'] == $rest_ea_data['ea']
				){
					$deliv_refurn = 'Y';
				}
			}

			// 배송비 환불일 경우 무조건 노출 :: 2018-07-23 pjw
			if($data_refund['refund_type'] == 'shipping_price'){
				$deliv_refurn = 'Y';
			}
			// 완료상태이며 배송비 환불 내역이 있을때 강제 출력 by hed
			if($data_refund['status'] == 'complete' && $refund_delivery_price > 0){
				$deliv_refurn = 'Y';
			}

			// 배송비 할인금 총액 마/예/에 제외
			$delivery_price = $ships[$data['shipping_seq']]['shipping_cost'];
			$delivery_sale_price = $ships[$data['shipping_seq']]['shipping_promotion_code_sale'] + $ships[$data['shipping_seq']]['shipping_coupon_sale'];
			// 완료상태이며 배송비 환불 내역은 없으나 할인에 의해 0원이 되었을 경우 강제 출력 by hed
			if($data_refund['status'] == 'complete' && $refund_delivery_price == 0 && $delivery_price-$delivery_sale_price <= 0){
				$deliv_refurn = 'Y';
			}

			// 배송비 및 반품 배송비 표기 :: 2018-05-29 lwh
			if($data_refund['refund_ship_duty'] == 'seller' && $deliv_refurn == 'Y')	$plus_cnt = 1;
			else if($data_refund['refund_ship_duty'] == 'buyer')					$plus_cnt = 0;
			else if($refund_shipping_items[$data['shipping_seq']]['shipping_cnt'])		$plus_cnt = 0;
			else																	$plus_cnt = 0;

			// 환불배송비 :: 환불이 한번도 저장되지 않은 상태 일때 기초데이터 생성 2019-11-13 pjm
			if($refund_unsaved_state){
				$ships[$data['shipping_seq']]['refund_delivery_emoney'] = $ships[$data['shipping_seq']]['emoney_sale_unit'] + $ships[$data['shipping_seq']]['emoney_sale_rest'];
				$ships[$data['shipping_seq']]['refund_delivery_cash']	= $ships[$data['shipping_seq']]['cash_sale_unit'] + $ships[$data['shipping_seq']]['cash_sale_rest'];
			}else{
				if($data_refund['refund_delivery'] == 0){
					$ships[$data['shipping_seq']]['refund_delivery_price'] = 0;
				}
			}

			$ships[$data['shipping_seq']]['ori_refund_delivery_emoney']  = $ships[$data['shipping_seq']]['refund_delivery_emoney'] ;
			$ships[$data['shipping_seq']]['ori_refund_delivery_cash']	= $ships[$data['shipping_seq']]['refund_delivery_cash'] ;

			if($data_refund['refund_ship_duty'] != 'seller' || $deliv_refurn != 'Y'){
				$deliv_refurn = 'N';
			}

			$order_goods_cnt++;
			$cash_price_total	+= ($data['cash_sale_unit'] * $data['option_ea']) + $data['cash_sale_rest'];
			$total_price += ($data['price']*$data['option_ea'])-$data['total_sale'];
			
			// 총 상품 환불가격
			$data_refund['refund_price_sum']	+=  $data['refund_goods_price'] + $data['refund_delivery_price'];
			
			$refund_shipping_items[$data['shipping_seq']]['items'][]							= $data;
			$refund_shipping_items[$data['shipping_seq']]['shipping']							= $ships[$data['shipping_seq']];
			$refund_shipping_items[$data['shipping_seq']]['return_shipping_cnt']				+= $return_shipping;
			$refund_shipping_items[$data['shipping_seq']]['return_shipping_cost']				=  $return_shipping_cost_tmp[$data['shipping_seq']];
			$refund_shipping_items[$data['shipping_seq']]['refund_delivery_price_sum']			=  $total_ship_cost;
			$refund_shipping_items[$data['shipping_seq']]['refund_delivery_price_sum_except']	=  $except_ship_cost;
			$refund_shipping_items[$data['shipping_seq']]['return_shipping']					=  $refund_ship;
			$refund_shipping_items[$data['shipping_seq']]['refund_flag']						=  $deliv_refurn;
			$refund_shipping_items[$data['shipping_seq']]['shipping_cnt']++;
			$refund_shipping_items[$data['shipping_seq']]['refund_payment']						= $refund_payment;
			$refund_shipping_items[$data['shipping_seq']]['plus_cnt']							= $plus_cnt;

			// 환불 마일리지 유무 체크(유효기간 노출)
			if ($data['refund_item_emoney'] > 0) {
				$refund_emoney_limit_use = true;
			}

		}
		
		// 배송비 노출에 의한 추가 필드 병합 처리
		foreach($refund_shipping_items as $row){
			$order_goods_cnt	= $order_goods_cnt + $row['plus_cnt'];
		}
		

		## 회수해야할 마일리지 및 포인트 계산식 노출 @2015-09-17 pjm
		if($return_formula_tmp){
			foreach($return_formula_tmp as $gubun=>$formula){
				foreach($formula as $k=>$data) {
					if($k > 0) $return_formula[$gubun] .= "+";
					$return_formula[$gubun] .= "(".$data.")";
				}
			}
			foreach($return_formula as $gubun=>$formula) $return_formula[$gubun] .= ' = ';
		}

		//동일주문의 기 환불금액(마일리지, 예치금) pjm
		$refund_complete = $this->refundmodel->get_refund_complete_emoney($all_order_seq);
		$tot['refund_complete_emoney']	= $refund_complete['complete_emoney'];
		$tot['refund_complete_cash']	= $refund_complete['complete_cash'];
		// 위에서 더하므로 주석처리 :: 2018-08-29
		//$tot['refund_complete_total']	+=$tot['refund_complete_emoney'];
		//$tot['refund_complete_total']	+=$tot['refund_complete_cash'];

		// 3차 환불 개선으로 실물,티켓 구분 :: 2018-11- lkh
		$tot['refund_deductible_price']				= $data_refund['refund_deductible_price'];
		$tot['refund_delivery_deductible_price']	= $data_refund['refund_delivery_deductible_price'];
		$tot['refund_penalty_deductible_price']		= 0;
		if($refundPenaltyDeductiblePriceTmp)
			$tot['refund_penalty_deductible_price'] = $refundPenaltyDeductiblePriceTmp;
		$tot['refund_all_deductible_price'] = $tot['refund_deductible_price']+$tot['refund_delivery_deductible_price']+$tot['refund_penalty_deductible_price'];

		$tot['refund_goods_sale_txt'] = "";
		$tot['refund_shipping_sale_txt'] = "";
		$tot['refund_shipping_iffree_txt'] = "";
		$refundGoodsSaleTmp = array();
		if($tot['event_sale']>0) $refundGoodsSaleTmp[] = "이벤트";
		if($tot['multi_sale']>0) $refundGoodsSaleTmp[] = "복수구매";
		if($tot['member_sale']>0) $refundGoodsSaleTmp[] = "등급";
		if($tot['coupon_sale']>0) $refundGoodsSaleTmp[] = "쿠폰";
		if($tot['promotion_code_sale']>0) $refundGoodsSaleTmp[] = "코드";
		if($tot['fblike_sale']>0) $refundGoodsSaleTmp[] = "좋아요";
		if($tot['mobile_sale']>0) $refundGoodsSaleTmp[] = "모바일";
		if($tot['referer_sale']>0) $refundGoodsSaleTmp[] = "유입경로";
		if($tot['enuri_sale']>0) $refundGoodsSaleTmp[] = "에누리";
		if($refundGoodsSaleTmp)
			$tot['refund_goods_sale_txt'] = implode(", ",$refundGoodsSaleTmp);
		$refundShipSaleTmp = array();
		if($tot['shipping_coupon_sale']>0) $refundShipSaleTmp[] = "쿠폰";
		if($tot['shipping_promotion_code_sale']>0) $refundShipSaleTmp[] = "코드";
		if($refundShipSaleTmp)
			$tot['refund_shipping_sale_txt'] = implode(", ",$refundShipSaleTmp);
		if($tot['shipping_iffree']){
			$tot['refund_shipping_iffree_txt'] = "조건부 배송비";
		}

		$tot['goods_cnt']	= array_sum($goods);
		$tmp_shipping_seq	= array_keys($refund_shipping_items);

		// 입점사별 배송비,배송정책
		$provider_order_shipping = $this->ordermodel->get_order_shipping($order_seq);
		foreach($provider_order_shipping as $data_order_shipping){
			if(in_array($data_order_shipping['shipping_seq'] ,$tmp_shipping_seq) ){
				$refund_shipping_cost = $this->refundmodel->get_refund_shipping_cost(
					$data_order,
					$data_order_item,
					$data_refund,
					$data_refund_item,
					$data_order_shipping
				);
				$tot['refund_shipping_cost'] += $refund_shipping_cost;

				$tot['shipping_coupon_sale'] += $data_order_shipping['shipping_coupon_sale'];
				$tot['shipping_promotion_code_sale'] += $data_order_shipping['shipping_promotion_code_sale'];
			}
		}

		$pg = config_load($this->config_system['pgCompany']);
		$naxCheck = $pg["nonActiveXUse"];

		$this->template->assign(array('pg'	=> $pg));
		$this->template->assign('pgCompany',$this->config_system['pgCompany']);
		$this->template->assign('naxCheck',$naxCheck);

		$data_order['kspay_authty']	= '1010';	// KSPAY - 신용카드
		if		($data_order['payment'] == 'account')
			$data_order['kspay_authty']	= '2010';	// KSPAY - 계좌이체
		elseif	($data_order['payment'] == 'cellphone')
			$data_order['kspay_authty']	= 'M110';	// KSPAY - 휴대폰

		$gift_order = 'y';
		if($goods_exist) $gift_order = 'n';

		//회수(차감) 가능한 마일리지 및 포인트 pjm
		$tot['return_reserve_use']	= false;
		$tot['return_point_use']	= false;
		if($tot['return_reserve']==0 || ($tot['return_reserve'] && $data_member['emoney'] > $tot['return_reserve'])) $tot['return_reserve_use'] = true;
		if($tot['return_point']==0 || ($tot['return_point'] && $data_member['point'] > $tot['return_point'])) $tot['return_point_use'] = true;

		if($data_refund['refund_method']){
			$refund_method = $data_refund['refund_method'];
		}else{
			$refund_method = $data_order['payment'];
		}

		$refund_method_name = $this->refundlibrary->set_refund_method_name($refund_method,$data_order['pg']);	//환불방법명

		# 환불방법에 따라 총 환불액 뿌려주기
		if($refund_method == "cash"){
			$data_refund['refund_cash_sum']		= $data_refund['refund_cash'];
			$data_refund['refund_price_sum']	= 0;
		}else{
			$data_refund['refund_cash_sum']		= $data_refund['refund_cash'];
		}
		// 3차 환불 개선으로 총 환불금액 계산 :: 2018-11- lkh
		$data_refund['refund_total_price']		= $data_refund['refund_price_sum'] + $data_refund['refund_cash'] + $data_refund['refund_emoney'] - $tot['refund_all_deductible_price'];
		if($npay_use){
			$data_refund['refund_total_price'] -= (int)$data_refund['npay_claim_price'];
		}

		// 반품 환불 배송비 :: 2018-06-01 lwh		
		if($data_refund['refund_ship_duty'] == 'buyer' &&  $data_refund['refund_ship_type'] == 'M'){
			$data_refund['refund_total_price'] -= (int)$data_refund['return_shipping_price'];
		}else{
			$data_refund['return_shipping_price'] = 0;
		}

		# 기본통화 정보
		$basic_amount	= get_exchange_rate($this->config_system['basic_currency']);
		$currency_info	= array();
		$currency_info['basic_currency']	= $this->config_system['basic_currency'];
		$currency_info['basic_amount']		= $basic_amount;

		//개인정보 마스킹 표시
		$this->load->library('privatemasking');
		$data_refund = $this->privatemasking->masking($data_refund, 'order');
		$process_log = $this->privatemasking->masking($process_log, 'order_log');

		$this->template->assign(
			array(
				'refund_shipping_items'	=>$refund_shipping_items,
				'refund_total_rows'		=>$refund_total_rows,
				'process_log'			=>$process_log,
				'refund_method'			=>$refund_method,
				'refund_method_name'	=>$refund_method_name,
				'data_refund'			=>$data_refund,
				'data_refund_item'		=>$data_refund_item,
				'refund_items'			=>$refund_items,
				'tot'					=>$tot,
				'gift_order'			=>$gift_order,
				'data_order'			=>$data_order,
				'members'				=>$data_member,
				'order_goods_cnt'		=>$order_goods_cnt,
				'npay_use'				=>$npay_use,
				'basic_currency_info'	=>$currency_info,
				'refund_rows'			=>$refund_rows,
				'return_formula'		=>$return_formula,
				'cash_price_total'		=>$cash_price_total,
			    'total_price'            =>  $total_price,
				'refund_emoney_limit_use' => $refund_emoney_limit_use,
			)
		);

		if($_SERVER['QUERY_STRING']){
			$tmp = explode("&",$_SERVER['QUERY_STRING']);
			foreach($tmp as $k=>$v){
				if(preg_match("/^no=/",$v)) unset($tmp[$k]);
			}
			$query_string = implode("&",$tmp);
		}
		
		$file_path = str_replace('view.html','new_view.html',$this->template_path());
		$this->template->define(array('tpl'=>$file_path));
		$this->template->assign('query_string',$query_string);
		$this->template->print_("tpl");
	}
	
	public function refund_old_view($refund_code,$data_refund,$data_order){
		$this->admin_menu();
		$this->tempate_modules();

		$this->load->helper('text');
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->model('membermodel');
		$this->load->model('managermodel');
		$this->load->model('couponmodel');
		$this->load->model('promotionmodel');
		$this->load->model('giftmodel');

		$cfg_order = config_load('order');

		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		if(!$reserves['cash_use']) $reserves['cash_use'] = "N";
		$this->template->assign($reserves);

		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');

		//배송비 환불의 경우 처리
		$shipping_price_return	= false;
		if($data_refund['refund_type'] == 'shipping_price'){
			$shipping_price_return	= true;
			$refund_shipping_info	= $this->refundmodel->get_provider_refund($refund_code);

			$refund_shipping_list	= array();
			foreach((array)$refund_shipping_info as $row){
				$refund_shipping_list[$row['refund_provider_seq']]	= $row;
				if($row['refund_provider_seq'] > 1 || $data_refund['refund_provider_seq'] == ''){
					$data_refund['refund_provider_seq']		= $row['refund_provider_seq'];
					$data_refund['refund_provider_name']	= $row['provider_name'];
				}
			}
		}

		$data_order_item	= $this->ordermodel->get_item($data_refund['order_seq']);
		$order_seq			= $data_refund['order_seq'];

		$npay_use = npay_useck();
		//npay 사용여부 확인, 취소사유 코드 불러오기
		if($npay_use && $data_order['pg'] == "npay"){

			$this->load->library('naverpaylib');
			$npay_return_hold	= $this->naverpaylib->get_npay_code("cancel_hold");
			if($npay_return_hold[strtoupper($data_refund['npay_flag'])]){
				$data_refund['npay_flag_msg'] = $npay_return_hold[strtoupper($data_refund['npay_flag'])];
			}else{
				$data_refund['npay_flag_msg'] = '';
			}

		}

		## 하위 주문번호 조회(원주문의 맞교환(재주문)건)
		$all_order_seq = array($data_order['order_seq']);

		if($data_order['top_orign_order_seq']){

			$query = "select order_seq from fm_order where top_orign_order_seq='".$data_order['top_orign_order_seq']."'";
			$query = $this->db->query($query);
			foreach($query->result_array() as $sub_order) $all_order_seq[] = $sub_order['order_seq'];

			$query						= "select * from fm_order where order_seq=?";
			$query						= $this->db->query($query,$data_order['top_orign_order_seq']);
			$ori_order					= $query->row_array();
			$orign_order_seq			= $all_order_seq[] = $data_order['top_orign_order_seq'];
			$data_order['settleprice']	= $ori_order['settleprice'];
			$data_order['cash']			= $ori_order['cash'];
			$data_order['emoney']		= $ori_order['emoney'];
			$data_order['enuri']		= $ori_order['enuri'];
			$data_order['shipping_cost']= $ori_order['shipping_cost'];
			$new_order_seq				= $data_order['order_seq'];
		}else{
			$orign_order_seq			= $data_order['order_seq'];
			$new_order_seq				= '';
		}
		$all_order_seq = array_unique($all_order_seq);

		$data_refund_item	= $this->refundmodel->get_refund_item($refund_code,$orign_order_seq,$new_order_seq);

		$process_log 		= $this->ordermodel->get_log($data_refund['order_seq'],'process',array('refund_code'=>$refund_code));
		$data_member		= $this->membermodel->get_member_data($data_refund['member_seq']);

		# 처리자 @2015/07/30 pjm
		$manager			= $this->managermodel->get_manager($data_refund['manager_seq']);
		$data_refund['manager_name'] = $manager['mname'];

		//복원된 주문서쿠폰 여부
		if($data_order['ordersheet_seq']){
			$data_order['use_ordersheetcoupon'] = $this->couponmodel->get_download_coupon($data_order['ordersheet_seq']);
			$data_order['restore_used_ordersheetcoupon_refund'] = $this->couponmodel->restore_used_coupon_refund($data_order['ordersheet_seq']);
		}
		
		// 원주문의 배송정보
		$data_shipping		= $this->ordermodel->get_order_shipping($orign_order_seq);
		if	($data_shipping)foreach($data_shipping as $k => $ship){

			//복원된 배송비쿠폰 여부 shipping_coupon_sale
			if($ship['shipping_coupon_down_seq']){
				$ship['restore_used_coupon_refund'] = $this->couponmodel->restore_used_coupon_refund($ship['shipping_coupon_down_seq']);
			}
			//복원된 배송비프로모션코드 여부
			if($ship['shipping_promotion_code_seq']){
				//발급받은 프로모션 타입(일반, 개별) - 일반(공용) 코드는 복원 불가(계속 사용가능)
				if($ship['shipping_promotion_code_sale'] > 0){
					$shipping_promotion = $this->promotionmodel->get_download_promotion($ship['shipping_promotion_code_seq']);
					$ship['shipping_promotion_type'] = $shipping_promotion['type'];
				}
				$ship['restore_used_promotioncode_refund'] = $this->promotionmodel->restore_used_promotioncode_refund($ship['shipping_promotion_code_seq']);
			}

			$ship['international']			= $data_order['international'];
			$ships[$ship['shipping_seq']]	= $ship;
		}

		// 개인정보 조회 로그
		//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderexcel', 'exportexcel'
		$this->load->model('logPersonalInformation');
		$this->logPersonalInformation->insert('refund',$this->managerInfo['manager_seq'],$data_refund['refund_seq']);


		/* 반품에 의한 환불일경우 주문시 지급 마일리지합계 표시 */
		if($data_refund['refund_type']=='return'/* && !$cfg_order['buy_confirm_use']*/)
		{
			$optquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_option where order_seq=?";
			$optquery = $this->db->query($optquery,$data_refund['order_seq']);
			$optres = $optquery->row_array();

			$suboptquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_suboption where order_seq=?";
			$suboptquery = $this->db->query($suboptquery,$data_refund['order_seq']);
			$suboptres = $suboptquery->row_array();

			$tot['reserve_sum'] = $optres['reserve_sum']+$suboptres['reserve_sum'];
			$tot['point_sum'] = $optres['point_sum']+$suboptres['point_sum'];
		}

		$query	= "SELECT status FROM fm_order_return WHERE refund_code=?";
		$query	= $this->db->query($query,$data_refund['refund_code']);
		$res	= $query->row_array();
		$data_refund['returns_status'] = $this->returnmodel->arr_return_status[$res['status']];


		$data_refund['mstatus']			= $this->refundmodel->arr_refund_status[$data_refund['status']];
		$data_refund['mrefund_type']	= $this->refundmodel->arr_refund_type[$data_refund['refund_type']];
		$data_refund['mcancel_type']	= $this->refundmodel->arr_cancel_type[$data_refund['cancel_type']];
		$data_order['mpayment']			= $this->arr_payment[$data_order['payment']];

		// 기본 마일리지 유효기간 계산
		if(!$data_refund['refund_emoney_limit_date']){
			$reserve_str_ts			= '';
			$reserve_limit_date		= '';
			$cfg_reserves			= config_load('reserve');
			if( $cfg_reserves['reserve_select'] == 'direct' ){
				$reserve_str_ts = "+".$cfg_reserves['reserve_direct']." month";
				$reserve_limit_date = date('Y-m-d',strtotime($reserve_str_ts));
			}
			if( $cfg_reserves['reserve_select'] == 'year' ){
				$reserve_str_ts = "+".$cfg_reserves['reserve_year']." year";
				$reserve_limit_date = date('Y-12-31',strtotime($reserve_str_ts));
			}
			$data_refund['refund_emoney_limit_date'] = $reserve_limit_date;
		}

		if($data_order['international']=='international'){
			$data_order['real_shipping_cost'] = $data_order['international_cost'];
		}else{
			$data_order['real_shipping_cost'] = $data_order['shipping_cost'];
		}

		$goods_exist				= 0;
		$refund_items				= array();
		$shipping_group_array		= array();
		$refund_ship				= array();
		$return_formula_tmp			= array();	//회수해야할 마일리지/포인트 계산식

		foreach($data_refund_item as $k => $data){

			$tot['order_ea'] += $data['option_ea'];		//주문수량
			$tot['ea']		 += $data['ea'];			//환불수량

			## 환불처리 테이블 rowspan 구하기 @2015-07-24 pjm
			if(in_array($data['item_seq'],$arr_rows['item_seq']) && in_array($data['option_seq'],$arr_rows['option_seq'])){
				$refund_rows[$data['option_seq']]++;
				$data['first_rows'] = false;
			}else{
				$refund_rows[$data['option_seq']] = 1;
				$data['first_rows'] = true;
			}
			$arr_rows['item_seq'][] = $data['item_seq'];
			$arr_rows['option_seq'][] = $data['option_seq'];

			## 맞교환 주문건 일때
			if($data['top_item_option_seq']){
				if($data['opt_type'] == "opt"){
					$query = "select * from fm_order_item_option where item_option_seq=?";
				}else{
					$query = "select * from fm_order_item_suboption where item_option_seq=?";
				}
				$query = $this->db->query($query,$data['top_item_option_seq']);
				$ori_option = $query->row_array();

				$orign_item_seq			= $ori_option['item_seq'];
				$data['price']			= $ori_option['price'];
				$data['consumer_price'] = $ori_option['supply_price'];
				$data['supply_price']	= $ori_option['consumer_price'];
				$data['supply_price']	= $ori_option['consumer_price'];

			}else{
				$orign_item_seq		= $data['item_seq'];
			}

			//티켓상품
			if ( $data['goods_kind'] == 'coupon' ) {//

				$data_return		= $this->returnmodel->get_return_refund_code($refund_code);
				$data_return_item 	= $this->returnmodel->get_return_item($data_return['return_code']);
				$data_refund_item[$k]['couponinfo'] = get_goods_coupon_view($data_return_item[0]['export_code']);
				$data_refund_item[$k]['coupon_use_return'] = $data_refund_item[$k]['couponinfo']['coupon_use_return'];

				if ( $data['coupon_refund_type'] == 'emoney' ) {//유효기간지나면
					$tot['coupon_valid_over']++;
					$tot['price'] += $data['coupon_refund_emoney'];//마일리지으로 추가
					$data_order['emoney'] += $data['coupon_refund_emoney'];//마일리지으로 추가
				}else{
					$tot['price'] += $data['coupon_remain_price'];
				}

				//총 할인액(주문기준)
				// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
				
				$data['total_sale'] = $data['event_sale'] + $data['multi_sale'] + ($data['member_sale']*$data['option_ea'])+$data['coupon_sale']
										+$data['fblike_sale']+$data['mobile_sale']+$data['referer_sale']
										+$data['promotion_code_sale'] + $data['unit_ordersheet'];

				if ( !in_array($data['item_seq'],$itemCoupontot) ) {
					$itemCoupontot[] = $data['item_seq'];

					//promotion sale
					// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
					$tot['event_sale']			+= $data['event_sale'];
					$tot['multi_sale']			+= $data['multi_sale'];
					$tot['member_sale']			+= $data['member_sale']*$data['ea'];
					$tot['coupon_sale']			+= $data['coupon_sale'];
					$tot['coupon_sale']			+= $data['unit_ordersheet'];
					$tot['fblike_sale']			+= $data['fblike_sale'];
					$tot['mobile_sale']			+= $data['mobile_sale'];
					$tot['referer_sale']		+= $data['referer_sale'];
					$tot['promotion_code_sale'] += $data['promotion_code_sale'];

					if($data_refund['refund_type']=='return'/* && !$cfg_order['buy_confirm_use']*/){
						$tot['return_reserve'] += $data['reserve']*$data['ea'];
						$tot['return_point'] += $data['point']*$data['ea'];
					}
				}

				//티켓상품 기존환불금액을 환불계산식의 결제금액 - 최종환불금액 계산식
				$tot['refund_complete_price']		+= $data['coupon_deduction_price'];
				$tot['refund_complete_total']		+= $data['coupon_deduction_price'];

			}else{

				$tot['price']		+= $data['price']*$data['ea'];

				//총 할인액(주문기준)
				// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
				$data['total_sale'] = $data['event_sale'] + $data['multi_sale'] + ($data['member_sale']*$data['option_ea'])+$data['coupon_sale']
										+$data['fblike_sale']+$data['mobile_sale']+$data['referer_sale']
										+$data['promotion_code_sale'] + $data['unit_ordersheet'];
				//promotion sale
				// 이벤트, 복수구매 할인 추가 :: 2018-07-16 pjw
				$tot['event_sale']			+= $data['event_sale'];
				$tot['multi_sale']			+= $data['multi_sale'];
				$tot['member_sale']			+= $data['member_sale']*$data['ea'];
				$tot['coupon_sale']			+= $data['coupon_sale'];
				$tot['coupon_sale']			+= $data['unit_ordersheet'];
				$tot['fblike_sale']			+= $data['fblike_sale'];
				$tot['mobile_sale']			+= $data['mobile_sale'];
				$tot['referer_sale']		+= $data['referer_sale'];
				$tot['promotion_code_sale']	+= $data['promotion_code_sale'];

				//동일주문의 기 환불금액 pjm
				$refund_complete = $this->refundmodel->get_refund_complete_price($all_order_seq,$data['option_seq'],$data['opt_type'],$data_refund['refund_code']);
				$data['refund_complete_ea']			= $refund_complete['complete_ea'];
				$data['refund_complete_price']		= $refund_complete['complete_price'];
				$data['refund_complete_delivery']	= $refund_complete['complete_delivery'];

				// complete_price : 상품환불금액 + 배송비환불금액 포함
				$tot['refund_complete_price']		+= $refund_complete['complete_price'];
				$tot['refund_complete_total']		+= $refund_complete['complete_price']
														+$refund_complete['complete_emoney']
														+$refund_complete['complete_cash'];

			}
			//차감할 마일리지, 포인트 pjm
			if($data_refund['refund_type']=='return' && $data['ea'] > 0 /* && !$cfg_order['buy_confirm_use']*/){
				$tot['return_reserve']	+= $data['give_reserve'];
				$tot['return_point']	+= $data['give_point'];
			}

			if( $data['refund_item_seq'] && !$refund_ship[$data['shipping_seq']] ) {
				$refund_ship[$data['shipping_seq']] = $data['refund_item_seq'];
			}

			if($data['ea'] > 0){

				//복원된 할인쿠폰 여부
				if($data['download_seq']){
					$data['use_coupon'] = $this->couponmodel->get_download_coupon($data['download_seq']);
					$data['restore_used_coupon_refund'] = $this->couponmodel->restore_used_coupon_refund($data['download_seq']);
				}

				//복원된 프로모션코드 여부
				if($data['promotion_code_seq']){
					$data['use_promotion'] = $this->promotionmodel->get_download_promotion($data['promotion_code_seq']);
					$data['restore_used_promotioncode_refund'] = $this->promotionmodel->restore_used_promotioncode_refund($data['promotion_code_seq']);
				}

				//청약철회상품체크
				unset($ctgoods);
				$ctgoods = $this->goodsmodel->get_goods($data['goods_seq']);
				$data['cancel_type'] = $ctgoods['cancel_type'];
				$data_refund_item[$k]['cancel_type'] = $ctgoods['cancel_type'];

				if( $data['opt_type']  == 'opt' && !$data['new_option_seq'] ) {
					$data['inputs'] = $this->ordermodel->get_input_for_option($data['item_seq'], $data['option_seq']);
				}

				$refund_items[$data['item_seq']]['items'][]					= $data;
				$refund_items[$data['item_seq']]['refund_ea']				+= $data['ea'];
				$refund_items[$data['item_seq']]['shipping_policy']			= $data['shipping_policy'];
				$refund_items[$data['item_seq']]['goods_shipping_policy']	= $data['shipping_unit']?'limited':'unlimited';
				$refund_items[$data['item_seq']]['unlimit_shipping_price']	= $data['goods_shipping_cost'];	//개별배송비
				$refund_items[$data['item_seq']]['limit_shipping_price']	= $data['basic_shipping_cost'];	//기본배송비
				$refund_items[$data['item_seq']]['limit_shipping_ea']		= $data['shipping_unit'];		//배송수량
				$refund_items[$data['item_seq']]['limit_shipping_subprice'] = $data['add_shipping_cost'];	//추가배송비

				$goods[$data['goods_seq']]++;

				if($data['goods_type'] == "goods") $goods_exist++;

				$data_refund_item[$k]['inputs']	= $data['inputs'];

				## 환불신청 갯수 pjm 2015-03-12
				$total_refund_ea += $data['refund_ea'];

				## 회수해야할 마일리지 및 포인트 계산식 노출 @2015-09-17 pjm
				$return_formula_tmp['reserve'][]	= "".$data['reserve']."*".$data['ea']."";
				$return_formula_tmp['point'][]		= "".$data['point']."*".$data['ea']."";

				$refund_total_rows++;
				$return_shipping = 1;


			}else{
				$return_shipping = 0;
				if($shipping_price_return === true && isset($refund_shipping_list[$data['provider_seq']]) === true){
					$return_shipping	= 1;

					if( $data['refund_item_seq'] && !$refund_ship[$data['shipping_seq']] ) {
						$refund_ship[$data['shipping_seq']] = $data['refund_item_seq'];
					}
				}
			}

			$order_goods_cnt++;

			$refund_shipping_items[$data['shipping_seq']]['items'][]	= $data;
			$refund_shipping_items[$data['shipping_seq']]['shipping']	= $ships[$data['shipping_seq']];
			$refund_shipping_items[$data['shipping_seq']]['shipping_cnt']++;
			$refund_shipping_items[$data['shipping_seq']]['return_shipping_cnt'] += $return_shipping;
			$refund_shipping_items[$data['shipping_seq']]['return_shipping_cost'] += $data['refund_delivery_price'];
			$refund_shipping_items[$data['shipping_seq']]['return_shipping']	  = $refund_ship;
		}

		## 회수해야할 마일리지 및 포인트 계산식 노출 @2015-09-17 pjm
		if($return_formula_tmp){
			foreach($return_formula_tmp as $gubun=>$formula){
				foreach($formula as $k=>$data) {
					if($k > 0) $return_formula[$gubun] .= "+";
					$return_formula[$gubun] .= "(".$data.")";
				}
			}
			foreach($return_formula as $gubun=>$formula) $return_formula[$gubun] .= ' = ';
		}

		//동일주문의 기 환불금액(마일리지, 예치금) pjm
		$refund_complete = $this->refundmodel->get_refund_complete_emoney($all_order_seq);
		$tot['refund_complete_emoney']	= $refund_complete['complete_emoney'];
		$tot['refund_complete_cash']	= $refund_complete['complete_cash'];
		$tot['refund_complete_total']	+=$tot['refund_complete_emoney'];
		$tot['refund_complete_total']	+=$tot['refund_complete_cash'];

		$tot['goods_cnt']	= array_sum($goods);
		$tmp_shipping_seq	= array_keys($refund_shipping_items);

		// 입점사별 배송비,배송정책
		$provider_order_shipping = $this->ordermodel->get_order_shipping($data_order['order_seq']);
		foreach($provider_order_shipping as $data_order_shipping){
			if(in_array($data_order_shipping['shipping_seq'] ,$tmp_shipping_seq) ){
				$tot['refund_shipping_cost'] += $this->refundmodel->get_refund_shipping_cost(
					$data_order,
					$data_order_item,
					$data_refund,
					$data_refund_item,
					$data_order_shipping
				);

				$tot['shipping_coupon_sale'] += $data_order_shipping['shipping_coupon_sale'];
				$tot['shipping_promotion_code_sale'] += $data_order_shipping['shipping_promotion_code_sale'];
			}

		}

		// 환불 상품 입점사별 상품금액 집계 :: 2014-12-26 lwh
		/*
		$refund_provider_list	= $this->refundmodel->get_provider_refund($refund_code);
		if(count($refund_provider_list) < 1){
			// 환불 데이터가 없으면 기본 입점사 정보 추출
			$rp_list	= $this->refundmodel->refund_provider_list($refund_code);
			foreach($rp_list as $key => $rp_data){
				# 배송비 환불 여부
				//$ship_cost	= ($rp_data['total_ea']==$rp_data['refund_ea']) ? $rp_data['shipping_cost_sum'] : 0;
				$ship_cost	= ($refund_shipping[$rp_data['shipping_seq']]) ? $rp_data['shipping_cost_sum'] : 0;

				$refund_provider_list[$key]['refund_provider_seq']			= $rp_data['provider_seq'];
				$refund_provider_list[$key]['provider_name']				= $rp_data['provider_name'];
				$refund_provider_list[$key]['provider_refund_expect_price'] = $rp_data['price_sum'] - $rp_data['sale_sum'] + $ship_cost;
				$refund_provider_list[$key]['adjust_provider_refund_price'] = 0;
				$refund_provider_list[$key]['provider_refund_price']		= $refund_provider_list[$key]['provider_refund_expect_price'];
			}
		}

		$this->template->assign(array('refund_provider_list' => $refund_provider_list));
		*/

		$pg = config_load($this->config_system['pgCompany']);
		$this->template->assign(array('pg'	=> $pg));

		$data_order['kspay_authty']	= '1010';	// KSPAY - 신용카드
		if		($data_order['payment'] == 'account')
			$data_order['kspay_authty']	= '2010';	// KSPAY - 계좌이체
		elseif	($data_order['payment'] == 'cellphone')
			$data_order['kspay_authty']	= 'M110';	// KSPAY - 휴대폰

		$gift_order = 'y';
		if($goods_exist) $gift_order = 'n';

		//회수(차감) 가능한 마일리지 및 포인트 pjm
		$tot['return_reserve_use']	= false;
		$tot['return_point_use']	= false;
		if($tot['return_reserve']==0 || ($tot['return_reserve'] && $data_member['emoney'] > $tot['return_reserve'])) $tot['return_reserve_use'] = true;
		if($tot['return_point']==0 || ($tot['return_point'] && $data_member['point'] > $tot['return_point'])) $tot['return_point_use'] = true;

		if($data_refund['refund_method']){
			$refund_method = $data_refund['refund_method'];
		}else{
			$refund_method = $data_order['payment'];
		}

		switch($refund_method){
			case "card":			$refund_method_name = "신용카드"; break;
			case "account":			$refund_method_name = "계좌이체"; break;
			case "escrow_account":	$refund_method_name = "계좌이체"; break;
			case "virtual":			$refund_method_name = "가상계좌"; break;
			case "escrow_virtual":	$refund_method_name = "가상계좌"; break;
			case "cellphone":		$refund_method_name = "휴대폰"; break;
			case "bank":			$refund_method_name = "무통장"; break;
			default :				$refund_method_name = "무통장"; break;
		}
		if($data_order['pg'] == "npay"){
			switch($refund_method){
				case "card":			$refund_method_name = "신용카드"; break;
				case "account":			$refund_method_name = "계좌이체"; break;
				case "escrow_account":	$refund_method_name = "계좌이체"; break;
				case "virtual":			$refund_method_name = "가상계좌"; break;
				case "escrow_virtual":	$refund_method_name = "가상계좌"; break;
				case "cellphone":		$refund_method_name = "휴대폰"; break;
				case "bank":			$refund_method_name = "무통장"; break;
				case "point":			$refund_method_name = "Npay포인트"; break;
				default :				$refund_method_name = "무통장"; break;
			}
		}
		//$refund_method_name .= " ".$data_refund['mcancel_type'];

		# 환불방법에 따라 총 환불액 뿌려주기
		if($refund_method == "cash"){
			$data_refund['refund_cash_sum']		= $data_refund['refund_price']+$data_refund['refund_cash'];
			$data_refund['refund_price_sum']	= 0;
		}else{
			$data_refund['refund_cash_sum']		= $data_refund['refund_cash'];
			$data_refund['refund_price_sum']	= $data_refund['refund_price'];
		}
		$data_refund['refund_total_price']		= $data_refund['refund_price']
													+ $data_refund['refund_cash']+$data_refund['refund_emoney'];

		if($npay_use){
			$data_refund['refund_total_price'] -= (int)$data_refund['npay_claim_price'];
		}

		# 기본통화 정보
		$basic_amount	= get_exchange_rate($this->config_system['basic_currency']);
		$currency_info	= array();
		$currency_info['basic_currency']	= $this->config_system['basic_currency'];
		$currency_info['basic_amount']		= $basic_amount;

		//개인정보 마스킹 표시
		$this->load->library('privatemasking');
		$data_refund = $this->privatemasking->masking($data_refund, 'order');
		$process_log = $this->privatemasking->masking($process_log, 'order_log');

		$this->template->assign(
			array(
				'refund_shipping_items'	=>$refund_shipping_items,
				'refund_total_rows'		=>$refund_total_rows,
				'process_log'			=>$process_log,
				'refund_method'			=>$refund_method,
				'refund_method_name'	=>$refund_method_name,
				'data_refund'			=>$data_refund,
				'data_refund_item'		=>$data_refund_item,
				'refund_items'			=>$refund_items,
				'tot'					=>$tot,
				'gift_order'			=>$gift_order,
				'data_order'			=>$data_order,
				'members'				=>$data_member,
				'order_goods_cnt'		=>$order_goods_cnt,
				'npay_use'				=>$npay_use,
				'basic_currency_info'	=>$currency_info,
				'refund_rows'			=>$refund_rows,
				'return_formula'=>$return_formula,
			)
		);

		if($_SERVER['QUERY_STRING']){
			$tmp = explode("&",$_SERVER['QUERY_STRING']);
			foreach($tmp as $k=>$v){
				if(preg_match("/^no=/",$v)) unset($tmp[$k]);
			}
			$query_string = implode("&",$tmp);
		}
		$this->template->assign('query_string',$query_string);
		$this->template->define(array('tpl' => $this->template_path()));
		$this->template->print_("tpl");
	}	

	public function view_old()
	{
		$this->admin_menu();
		$this->tempate_modules();
		$refund_code = $_GET['no'];

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('refund_view');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$this->load->helper('text');
		$this->load->model('refundmodel');
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->model('returnmodel');
		$this->load->model('membermodel');

		$cfg_order = config_load('order');

		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		if(!$reserves['cash_use']) $reserves['cash_use'] = "N";
		$this->template->assign($reserves);

		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');

		$data_refund 		= $this->refundmodel->get_refund($refund_code);
		$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
		$data_order			= $this->ordermodel->get_order($data_refund['order_seq']);
		$data_order_item	= $this->ordermodel->get_item($data_refund['order_seq']);
		$process_log 		= $this->ordermodel->get_log($data_refund['order_seq'],'process',array('refund_code'=>$refund_code));
		$data_member		= $this->membermodel->get_member_data($data_refund['member_seq']);
		// 원주문의 배송정보
		$data_shipping		= $this->ordermodel->get_order_shipping($data_refund['order_seq']);
		if	($data_shipping)foreach($data_shipping as $k => $ship){
			$ship['international']			= $data_order['international'];
			$ships[$ship['shipping_seq']]	= $ship;
		}

		$order_seq = $data_refund['order_seq'];

		// 개인정보 조회 로그
		//'member', 'memberlist', 'order', 'export', 'return', 'refund', 'orderexcel', 'exportexcel'
		$this->load->model('logPersonalInformation');
		$this->logPersonalInformation->insert('refund',$this->managerInfo['manager_seq'],$data_refund['refund_seq']);

		$this->load->model('couponmodel');
		$this->load->model('promotionmodel');
		//복원된 배송비쿠폰 여부
		if($data_order['download_seq']){
			$data_order['restore_used_coupon_refund'] = $this->couponmodel->restore_used_coupon_refund($data_order['download_seq']);
		}
		//복원된 주문서쿠폰 여부
		if($data_order['ordersheet_seq']){
			$data_order['restore_used_ordersheetcoupon_refund'] = $this->couponmodel->restore_used_coupon_refund($data_order['ordersheet_seq']);
		}
		//복원된 배송비프로모션코드 여부
		if($data_order['shipping_promotion_code_seq']){
			$data_order['restore_used_promotioncode_refund'] = $this->promotionmodel->restore_used_promotioncode_refund($data_order['shipping_promotion_code_seq']);
		}

		/* 반품에 의한 환불일경우 주문시 지급 마일리지합계 표시 */
		if($data_refund['refund_type']=='return'/* && !$cfg_order['buy_confirm_use']*/)
		{
			$optquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_option where order_seq=?";
			$optquery = $this->db->query($optquery,$data_refund['order_seq']);
			$optres = $optquery->row_array();

			$suboptquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_suboption where order_seq=?";
			$suboptquery = $this->db->query($suboptquery,$data_refund['order_seq']);
			$suboptres = $suboptquery->row_array();

			$tot['reserve_sum'] = $optres['reserve_sum']+$suboptres['reserve_sum'];
			$tot['point_sum'] = $optres['point_sum']+$suboptres['point_sum'];
		}

		$query = "SELECT status FROM fm_order_return WHERE refund_code=?";
		$query = $this->db->query($query,$data_refund['refund_code']);
		$res = $query->row_array();
		$data_refund['returns_status'] = $this->returnmodel->arr_return_status[$res['status']];


		$data_refund['mstatus'] = $this->refundmodel->arr_refund_status[$data_refund['status']];
		$data_refund['mrefund_type'] = $this->refundmodel->arr_refund_type[$data_refund['refund_type']];
		$data_refund['mcancel_type'] = $this->refundmodel->arr_cancel_type[$data_refund['cancel_type']];
		$data_order['mpayment'] = $this->arr_payment[$data_order['payment']];

		// 기본 마일리지 유효기간 계산
		if(!$data_refund['refund_emoney_limit_date']){
			$reserve_str_ts = '';
			$reserve_limit_date = '';
			$cfg_reserves = config_load('reserve');
			if( $cfg_reserves['reserve_select'] == 'direct' ){
				$reserve_str_ts = "+".$cfg_reserves['reserve_direct']." month";
				$reserve_limit_date = date('Y-m-d',strtotime($reserve_str_ts));
			}
			if( $cfg_reserves['reserve_select'] == 'year' ){
				$reserve_str_ts = "+".$cfg_reserves['reserve_year']." year";
				$reserve_limit_date = date('Y-12-31',strtotime($reserve_str_ts));
			}
			$data_refund['refund_emoney_limit_date'] = $reserve_limit_date;
		}

		if($data_order['international']=='international'){
			$data_order['real_shipping_cost'] = $data_order['international_cost'];
		}else{
			$data_order['real_shipping_cost'] = $data_order['shipping_cost'];
		}

		$goods_exist = 0;

		$refund_items			= array();
		$shipping_group_array	= array();
		foreach($data_refund_item as $k => $data){
			$tot['ea'] += $data['ea'];

			//티켓상품
			if ( $data['goods_kind'] == 'coupon' ) {//

				$data_return		= $this->returnmodel->get_return_refund_code($refund_code);
				$data_return_item 	= $this->returnmodel->get_return_item($data_return['return_code']);
				$data_refund_item[$k]['couponinfo'] = get_goods_coupon_view($data_return_item[0]['export_code']);
				$data_refund_item[$k]['coupon_use_return'] = $data_refund_item[$k]['couponinfo']['coupon_use_return'];

				if ( $data['coupon_refund_type'] == 'emoney' ) {//유효기간지나면
					$tot['coupon_valid_over']++;
					$tot['price'] += $data['coupon_refund_emoney'];//마일리지으로 추가
					$data_order['emoney'] += $data['coupon_refund_emoney'];//마일리지으로 추가
				}else{
					$tot['price'] += $data['coupon_remain_price'];
				}

				if ( !in_array($data['item_seq'],$itemCoupontot) ) {
					$itemCoupontot[] = $data['item_seq'];

					//promotion sale
					$tot['member_sale'] += $data['member_sale']*$data['ea'];
					$tot['coupon_sale'] += $data['coupon_sale'];
					$tot['coupon_sale'] += $data['unit_ordersheet'];
					$tot['fblike_sale'] += $data['fblike_sale'];
					$tot['mobile_sale'] += $data['mobile_sale'];
					$tot['referer_sale'] += $data['referer_sale'];
					$tot['promotion_code_sale'] += $data['promotion_code_sale'];

					if($data_refund['refund_type']=='return'/* && !$cfg_order['buy_confirm_use']*/){
						$tot['return_reserve'] += $data['reserve']*$data['ea'];
						$tot['return_point'] += $data['point']*$data['ea'];
					}
				}

			}else{
				$tot['price'] += $data['price']*$data['ea'];

				//promotion sale
				$tot['member_sale'] += $data['member_sale']*$data['ea'];
				$tot['coupon_sale'] += $data['coupon_sale'];
				$tot['coupon_sale'] += $data['unit_ordersheet'];
				$tot['fblike_sale'] += $data['fblike_sale'];
				$tot['mobile_sale'] += $data['mobile_sale'];
				$tot['referer_sale'] += $data['referer_sale'];
				$tot['promotion_code_sale'] += $data['promotion_code_sale'];

				if($data_refund['refund_type']=='return'/* && !$cfg_order['buy_confirm_use']*/){
					$tot['return_reserve']	+= $data['reserve']*$data['ea'];
					$tot['return_point']	+= $data['point']*$data['ea'];
				}
			}

			## 사은품
			$data['gift_title'] = "";
			if($data['goods_type'] == "gift"){
				$giftlog = $this->giftmodel->get_gift_title($data_refund['order_seq'],$data['item_seq']);
				$data_refund_item[$k]['gift_title'] = $giftlog['gift_title'];
			}

			//복원된 할인쿠폰 여부
			if($data['download_seq']){
				$data['restore_used_coupon_refund'] = $this->couponmodel->restore_used_coupon_refund($data['download_seq']);
			}

			//복원된 프로모션코드 여부
			if($data['promotion_code_seq']){
				$data['restore_used_promotioncode_refund'] = $this->promotionmodel->restore_used_promotioncode_refund($data['promotion_code_seq']);
			}

			//청약철회상품체크
			unset($ctgoods);
			$ctgoods = $this->goodsmodel->get_goods($data['goods_seq']);
			$data['cancel_type'] = $ctgoods['cancel_type'];
			$data_refund_item[$k]['cancel_type'] = $ctgoods['cancel_type'];
			$data['inputs'] = $this->ordermodel->get_input_for_option($data['item_seq'], $data['option_seq']);

			unset($data['inputs']);
			if( $data['opt_type']  == 'opt' ) {
				$data['inputs'] = $this->ordermodel->get_input_for_option($data['item_seq'], $data['option_seq']);
			}

			$deliv_refurn = false;	//배송비 환불여부
			## 배송그룹내 배송전 이거나 배송한 상품이 있는지 확인 2015-03-12 pjm
			if(!in_array($shipping_seq, $shipping_group_array)){

				# 배송그룹내 마지막 환불 코드
				$refund_maxcode = $this->refundmodel->shipping_refund_maxcode($data_refund['order_seq'],$data['shipping_seq']);

				## 배송그룹별 주문수량, 취소수량, 배송수량
				$rest_ea_data = $this->refundmodel->shipping_order_ea($data['shipping_seq']);
				if($rest_ea_data){
					## 배송그룹내 (남은 배송 안함 수량이 없고 출고수량이 없을때, 현재 환불코드가 마지막 환불코드 => 배송비 환불
					if(($rest_ea_data['deliv_ea'] == 0 && $rest_ea_data['rest_ea'] == 0) && $refund_maxcode == $refund_code){
						$deliv_refurn = true;
					}
				}
			}
			$shipping_group_array[] = $data['shipping_seq'];
			## 배송그룹별 배송비 환불 여부 2015-03-12 pjm
			$refund_shipping[$data['shipping_seq']] = $deliv_refurn;
			if(!$deliv_refurn){
				$data['goods_shipping_cost'] = 0;
			}

			$refund_items[$data['item_seq']]['items'][]					= $data;
			$refund_items[$data['item_seq']]['refund_ea']				+= $data['ea'];
			$refund_items[$data['item_seq']]['shipping_policy']			= $data['shipping_policy'];
			$refund_items[$data['item_seq']]['goods_shipping_policy']	= $data['shipping_unit']?'limited':'unlimited';
			$refund_items[$data['item_seq']]['unlimit_shipping_price']	= $data['goods_shipping_cost'];		//개별배송비
			$refund_items[$data['item_seq']]['limit_shipping_price']	= $data['basic_shipping_cost'];		//기본배송비
			$refund_items[$data['item_seq']]['limit_shipping_ea']		= $data['shipping_unit'];			//배송수량
			$refund_items[$data['item_seq']]['limit_shipping_subprice'] = $data['add_shipping_cost'];		//추가배송비

			if($data['goods_type'] == "goods") $goods_exist++;

			$data_refund_item[$k]['inputs']	= $data['inputs'];

			$refund_shipping_items[$data['shipping_seq']]['items'][]	= $data;
			$refund_shipping_items[$data['shipping_seq']]['shipping']	= $ships[$data['shipping_seq']];
			$refund_shipping_items[$data['shipping_seq']]['shipping_cnt']++;

			## 환불신청 갯수 pjm 2015-03-12
			$total_refund_ea += $data['refund_ea'];

			$refund_total_rows++;
		}

		foreach($refund_items as $item_seq => $data){

			$goods[$data['goods_seq']]++;

			// order_item의 ea합
			$query = $this->db->query("select sum(ea) as ea from fm_order_item_option where order_seq=? and item_seq=?", array($order_seq,$item_seq));
			$order_item_option_ea = $query->row_array();
			$query = $this->db->query("select sum(ea) as ea from fm_order_item_suboption where order_seq=? and item_seq=?", array($order_seq,$item_seq));
			$order_item_suboption_ea = $query->row_array();
			$order_item_ea = $order_item_ea['ea'] + $order_item_suboption_ea['ea'];

			if($data['unlimit_shipping_price']){
				$remain_item_shipping_cost = $this->goodsmodel->get_goods_delivery(array(
					'shipping_policy'			=> $data['shipping_policy'],
					'goods_shipping_policy'		=> $data['goods_shipping_policy'],
					'unlimit_shipping_price'	=> $data['unlimit_shipping_price'],
					'limit_shipping_price'		=> $data['limit_shipping_price'],
					'limit_shipping_ea'			=> $data['limit_shipping_ea'],
					'limit_shipping_subprice'	=> $data['limit_shipping_subprice'],
				),$order_item_ea-$data['refund_ea']);

				$refund_items[$item_seq]['refund_goods_shipping_cost'] = $data['unlimit_shipping_price']-$remain_item_shipping_cost['price'];

				$tot['refund_goods_shipping_cost'] += $refund_items[$item_seq]['refund_goods_shipping_cost'];

				$tot['goods_shipping_cnt']++;
			}else{
				$refund_items[$item_seq]['refund_goods_shipping_cost'] = 0;
			}

		}
		$tot['goods_cnt']	= array_sum($goods);
		$tmp_shipping_seq	= array_keys($refund_shipping_items);

		// 입점사별 배송비,배송정책
		$provider_order_shipping = $this->ordermodel->get_order_shipping($data_order['order_seq']);
		foreach($provider_order_shipping as $data_order_shipping){
			if(in_array($data_order_shipping['shipping_seq'] ,$tmp_shipping_seq) ){
				$tot['refund_shipping_cost'] += $this->refundmodel->get_refund_shipping_cost(
					$data_order,
					$data_order_item,
					$data_refund,
					$data_refund_item,
					$data_order_shipping
				);

				$tot['shipping_coupon_sale'] += $data_order_shipping['shipping_coupon_sale'];
				$tot['shipping_promotion_code_sale'] += $data_order_shipping['shipping_promotion_code_sale'];
			}

		}

		/*
		$tot['refund_shipping_cost'] = $this->refundmodel->get_refund_shipping_cost(
			$data_order,
			$data_order_item,
			$data_refund,
			$data_refund_item
		);
		*/

		// 환불 상품 입점사별 상품금액 집계 :: 2014-12-26 lwh
		$refund_provider_list	= $this->refundmodel->get_provider_refund($refund_code);
		if(count($refund_provider_list) < 1){
			// 환불 데이터가 없으면 기본 입점사 정보 추출
			$rp_list	= $this->refundmodel->refund_provider_list($refund_code);
			foreach($rp_list as $key => $rp_data){
				# 배송비 환불 여부
				//$ship_cost	= ($rp_data['total_ea']==$rp_data['refund_ea']) ? $rp_data['shipping_cost_sum'] : 0;
				$ship_cost	= ($refund_shipping[$rp_data['shipping_seq']]) ? $rp_data['shipping_cost_sum'] : 0;

				$refund_provider_list[$key]['refund_provider_seq']			= $rp_data['provider_seq'];
				$refund_provider_list[$key]['provider_name']				= $rp_data['provider_name'];
				$refund_provider_list[$key]['provider_refund_expect_price'] = $rp_data['price_sum'] - $rp_data['sale_sum'] + $ship_cost;
				$refund_provider_list[$key]['adjust_provider_refund_price'] = 0;
				$refund_provider_list[$key]['provider_refund_price']		= $refund_provider_list[$key]['provider_refund_expect_price'];
			}
		}

		$this->template->assign(array('refund_provider_list' => $refund_provider_list));

		$pg = config_load($this->config_system['pgCompany']);
		$this->template->assign(array('pg'	=> $pg));

		$data_order['kspay_authty']	= '1010';	// KSPAY - 신용카드
		if		($data_order['payment'] == 'account')
			$data_order['kspay_authty']	= '2010';	// KSPAY - 계좌이체
		elseif	($data_order['payment'] == 'cellphone')
			$data_order['kspay_authty']	= 'M110';	// KSPAY - 휴대폰

		$gift_order = 'y';
		if($goods_exist) $gift_order = 'n';

		$this->template->assign(
			array(
			'refund_shipping_items'=>$refund_shipping_items,
			'refund_total_rows'=>$refund_total_rows,
			'process_log'=>$process_log,
			'data_refund'=>$data_refund,
			'data_refund_item'=>$data_refund_item,
			'refund_items'=>$refund_items,
			'tot'=>$tot,
			'gift_order'=>$gift_order,
			'data_order'=>$data_order,
			'members'=>$data_member)
		);

		$this->template->define(array('tpl' => $this->template_path()));
		$this->template->print_("tpl");
	}


	/* 할인쿠폰 복원*/
	public function restore_used_coupon(){
		$this->load->model('couponmodel');

		if($this->couponmodel->restore_used_coupon($_GET['download_seq'])){
			$msg = "쿠폰이 복원되었습니다.";
		}else{
			$msg = "쿠폰 사용 내역을 찾을 수 없습니다.";
		}

		echo $msg;

	}


	/* 프로모션코드 복원*/
	public function restore_used_promotioncode(){
		$this->load->model('promotionmodel');

		if($this->promotionmodel->restore_used_promotion($_GET['download_seq'])){
			$msg = "프로모션코드가 복원되었습니다.";
		}else{
			$msg = "프로모션코드 사용 내역을 찾을 수 없습니다.";
		}

		echo $msg;

	}
}

/* End of file refund.php */
/* Location: ./app/controllers/admin/refund.php */
