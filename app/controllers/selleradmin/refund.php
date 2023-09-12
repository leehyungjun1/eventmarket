<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class refund extends selleradmin_base {

	public function __construct() {
		parent::__construct();
	}

	public function index()
	{
		redirect("/selleradmin/order/catalog");
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
		$this->load->helper('order');

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
		}

		// 검색내용
		$sc = $_GET;
		$this->template->assign("sc",$sc);

		// 현재의 처리 프로세스
		$orders = config_load('order');
		$this->template->assign($orders);
		$this->template->define(array('order_process'=>$this->skin."/setting/_order_process.html"));

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

		$_PARAM			= $_POST;//$_GET//$_POST
		$pagemode		= $_POST['pagemode'];
		unset($_PARAM['stepBox']);

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
		}

		$page			= (trim($_PARAM['page'])) ? trim($_PARAM['page']) : 1;
		$bfStep			= trim($_PARAM['bfStep']);
		$no				= trim($_PARAM['nnum']);
		$_PARAM['provider_seq'] = $this->providerInfo['provider_seq'];

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
				$data['mstatus'] = $this->refundmodel->arr_refund_status[$data['status']];
				$data['returns_status'] = $this->returnmodel->arr_return_status[$data['return_status']];
				$data['mpayment'] = $this->arr_payment[$data['payment']];
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

	public function view()
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
		$this->load->model('giftmodel');

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
		if($data_refund['refund_type']=='return' && !$cfg_order['buy_confirm_use'])
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

		if($data_order['international']=='international'){
			$data_order['real_shipping_cost'] = $data_order['international_cost'];
		}else{
			$data_order['real_shipping_cost'] = $data_order['shipping_cost'];
		}

		$goods_exist = 0;

		$refund_items = array();
		foreach($data_refund_item as $k => $data){
			$tot['ea'] += $data['ea'];

			//티켓상품
			if ( $data['goods_kind'] == 'coupon' ) {//

				$data_return = $this->returnmodel->get_return_refund_code($refund_code);
				$data_return_item 	= $this->returnmodel->get_return_item($data_return['return_code']);
				$data_refund_item[$k]['couponinfo'] = get_goods_coupon_view($data_return_item[0]['export_code']);
				$data_refund_item[$k]['coupon_use_return'] = $data_refund_item[$k]['couponinfo']['coupon_use_return'];

				//debug_var($data['coupon_refund_type']);
				//debug_var($data['coupon_remain_price']);
				//debug_var($data['coupon_refund_emoney']);
				if ( $data['coupon_refund_type'] == 'emoney' ) {//
					$tot['price'] += $data['coupon_refund_emoney'];//마일리지으로 추가
					$data_order['emoney'] += $data['coupon_refund_emoney'];//마일리지으로 추가
				}else{
					$tot['price'] += $data['coupon_remain_price'];
				}
			}else{
				$tot['price'] += $data['price']*$data['ea'];
			}

			## 사은품
			$data['gift_title'] = "";
			if($data['goods_type'] == "gift"){
				$giftlog = $this->giftmodel->get_gift_title($data_refund['order_seq'],$data['item_seq']);
				$data_refund_item[$k]['gift_title'] = $giftlog['gift_title'];
			}

			//promotion sale
			$tot['member_sale'] += $data['member_sale']*$data['ea'];
			$tot['coupon_sale'] += $data['coupon_sale'];
			$tot['coupon_sale'] += $data['unit_ordersheet'];
			$tot['fblike_sale'] += $data['fblike_sale'];
			$tot['mobile_sale'] += $data['mobile_sale'];
			$tot['referer_sale'] += $data['referer_sale'];
			$tot['promotion_code_sale'] += $data['promotion_code_sale'];

			if($data_refund['refund_type']=='return' && !$cfg_order['buy_confirm_use']){
				$tot['return_reserve'] += $data['reserve']*$data['ea'];
				$tot['return_point'] += $data['point']*$data['ea'];
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

			$refund_items[$data['item_seq']]['items'][] = $data;
			$refund_items[$data['item_seq']]['refund_ea'] += $data['ea'];
			$refund_items[$data['item_seq']]['shipping_policy'] = $data['shipping_policy'];
			$refund_items[$data['item_seq']]['goods_shipping_policy'] = $data['shipping_unit']?'limited':'unlimited';
			$refund_items[$data['item_seq']]['unlimit_shipping_price'] = $data['goods_shipping_cost'];
			$refund_items[$data['item_seq']]['limit_shipping_price'] = $data['basic_shipping_cost'];
			$refund_items[$data['item_seq']]['limit_shipping_ea'] = $data['shipping_unit'];
			$refund_items[$data['item_seq']]['limit_shipping_subprice'] = $data['add_shipping_cost'];

			if($data['goods_type'] == "goods") $goods_exist++;

			$data_refund_item[$k]['inputs']	= $data['inputs'];
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

		$tot['goods_cnt'] = array_sum($goods);

		// 입점사별 배송비,배송정책
		$provider_order_shipping = $this->ordermodel->get_order_shipping($data_order['order_seq']);
		foreach($provider_order_shipping as $data_order_shipping){
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

		/*
		$tot['refund_shipping_cost'] = $this->refundmodel->get_refund_shipping_cost(
			$data_order,
			$data_order_item,
			$data_refund,
			$data_refund_item
		);
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

		//개인정보 마스킹 표시
		$this->load->library('privatemasking');
		$data_refund = $this->privatemasking->masking($data_refund, 'order');
		$process_log = $this->privatemasking->masking($process_log, 'order_log');

		$this->template->assign(
			array(
			'process_log'=>$process_log,
			'data_refund'=>$data_refund,
			'data_refund_item'=>$data_refund_item,
			'refund_items'=>$refund_items,
			'tot'=>$tot,
			'gift_order'=>$gift_order,
			'data_order'=>$data_order)
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
/* Location: ./app/controllers/selleradmin/refund.php */