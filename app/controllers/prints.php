<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class prints extends front_base {


	public function __construct() {
		parent::__construct();

		$defines = array();
		$defines["PRINT_PATH"] = $this->print_path();
		$this->load->helper('order');
		$this->load->helper('shipping');
		$this->load->library('snssocial');
		$this->load->library('sale');
		$this->load->model('promotionmodel');
		$this->cfg_order = config_load('order');

		$this->template->define($defines);
	}

	protected function print_path(){
		return $this->skin."/_modules/".implode('/',$this->uri->rsegments).".html";
	}

	//거래명세서
	function form_print_trade(){
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('buyconfirmmodel');
		$this->load->model('giftmodel');
		$this->load->helper('order');

		$this->load->helper('shipping');
		$arr_shipping_method = get_shipping_method('all');

		if(!$this->arr_step)	$this->arr_step = config_load('step');
		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		if(!$this->cfg_order)	$this->cfg_order = config_load('order');
		$this->template->assign(array('cfg_order'	=> $this->cfg_order));

		if(!$this->userInfo['member_seq'] || $this->managerInfo || $this->providerInfo){
			$order_seq = $this->session->userdata('sess_order');
			if($this->managerInfo || $this->providerInfo) $order_seq = $_GET['no'];
			if(!$order_seq) {
				pageClose(getAlert('mo116'));
				exit;
			}
			$orders 			= $this->ordermodel->get_order($order_seq);
		}else{
			$order_seq 	= $_GET['no'];
			$member_seq = $this->userInfo['member_seq'];
			$orders 			= $this->ordermodel->get_order($order_seq, array("member_seq"=>$this->userInfo['member_seq']));
		}
		//주문 상태 체크
		if($orders['step'] == 0  || $orders['hidden']=='Y' || $orders['hidden']=='T' ){
			pageClose(getAlert('mo116'));
			exit;
		}else if($orders['step'] == 95){
			pageClose(getAlert('mo117'));
			exit;
		}else if($orders['step'] == 85){
			pageClose(getAlert('mo118'));
			exit;
		}


		$items 				= $this->ordermodel->get_item($order_seq);

		$orders['settleprice'] = 0;
		$able_return_ea = $tot['coupontotal'] =$tot['goodstotal'] = 0;
		foreach($items as $key=>$item){
			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);

			$data_shipping = $this->ordermodel->get_order_shipping($order_seq,null,$item['shipping_seq']);
			foreach($data_shipping as $row_shipping){
				$item['shipping_method'] = $row_shipping['shipping_method'];
				$item['shipping_method_name'] = $arr_shipping_method[$row_shipping['shipping_method']];
			}

			if($options) foreach($options as $k => $data){
				$item['shipping_cnt']++;
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
				$data['out_consumer_price'] = $data['consumer_price']*$data['ea'];
				$data['out_price'] = $data['price']*$data['ea'];

				//sale 8가지
				$data['out_event_sale']				= $data['event_sale'];
				$data['out_multi_sale']				= $data['multi_sale'];
				$data['out_member_sale']			= $data['member_sale']*$data['ea'];//1
				$data['out_coupon_sale']			= ($data['download_seq'])?$data['coupon_sale']:0;//2
				$data['out_fblike_sale']			= $data['fblike_sale'];//3
				$data['out_mobile_sale']			= $data['mobile_sale'];//4
				$data['out_promotion_code_sale']	= $data['promotion_code_sale'];//5
				$data['out_referer_sale']			= $data['referer_sale'];//6
				$data['out_tot_sale']				= $data['out_event_sale'] + $data['out_multi_sale'] + $data['out_member_sale']+$data['out_coupon_sale']+$data['out_fblike_sale']+$data['out_mobile_sale']+$data['out_promotion_code_sale']+$data['out_referer_sale'];
				$data['out_real_price']				= $data['out_price'] >= $data['out_tot_sale'] ? $data['out_price'] -  $data['out_tot_sale'] : 0;

				$data['out_reserve'] = $data['reserve']*$data['ea'];
				$data['out_point'] = $data['point']*$data['ea'];
				$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];

				###
				$data['inputs']	= $this->ordermodel->get_input_for_option($data['item_seq'], $data['item_option_seq']);

				$tot['ea'] += $data['ea'];
				$tot['supply_price'] += $data['out_supply_price'];
				$tot['consumer_price'] += $data['out_consumer_price'];
				$tot['price'] += $data['out_price'];
				$orders['settleprice'] += $data['out_real_price'];

				//sale 8가지
				$tot['event_sale']  += $data['out_event_sale'];
				$tot['multi_sale']  += $data['out_multi_sale'];
				$tot['member_sale'] += $data['out_member_sale'];
				$tot['coupon_sale'] += $data['out_coupon_sale'];
				$tot['fblike_sale'] += $data['out_fblike_sale'];
				$tot['mobile_sale'] += $data['out_mobile_sale'];
				$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];
				$tot['referer_sale'] += $data['out_referer_sale'];

				$tot['reserve'] += $data['out_reserve'];
				$tot['point'] += $data['out_point'];

				$tot['real_stock'] += $real_stock;
				$tot['stock'] += $stock;

				$return_item = $this->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq']);
				$able_return_ea += ($data['step55']+$data['step65']+$data['step75']) - (int) $return_item['ea'];

				$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);

				if($suboptions) foreach($suboptions as $z => $data_sub){
					$item['shipping_cnt']++;
					$real_stock = $this->goodsmodel -> get_goods_suboption_stock(
							$data_sub['goods_seq'],
							$title,
							$suboption
					);
					$rstock = $this->ordermodel -> get_suboption_reservation(
							$this->cfg_order['ableStockStep'],
							$item['goods_seq'],
							$data_sub['title'],
							$data_sub['suboption']
					);

					$stock = (int) $real_stock - (int) $rstock;
					$data_sub['real_stock'] = (int) $real_stock;
					$data_sub['stock'] = (int) $stock;

					$data_sub['out_supply_price'] = $data_sub['supply_price']*$data_sub['ea'];
					$data_sub['out_consumer_price'] = $data_sub['consumer_price']*$data_sub['ea'];
					$data_sub['out_price'] = $data_sub['price']*$data_sub['ea'];

					$data_sub['out_member_sale']	= $data_sub['member_sale']*$data_sub['ea'];
					$data_sub['out_tot_sale']		= $data_sub['out_member_sale'];

					$data_sub['out_reserve'] = $data_sub['reserve']*$data_sub['ea'];
					$data_sub['out_point'] = $data_sub['point']*$data_sub['ea'];

					$data_sub['mstep']	= $this->arr_step[$data_sub['step']];
					$data_sub['step_complete'] = $data_sub['step45']+$data_sub['step55']+$data_sub['step65']+$data_sub['step75'];

					$suboptions[$z] = $data_sub;

					$tot['ea'] += $data_sub['ea'];
					$tot['supply_price'] 	+= $data_sub['out_supply_price'];
					$tot['consumer_price'] 	+= $data_sub['out_consumer_price'];
					$tot['price'] 			+= $data_sub['out_price'];

					$tot['member_sale'] += $data_sub['out_member_sale'];

					$tot['reserve'] += $data_sub['out_reserve'];
					$tot['point'] += $data_sub['out_point'];

					$tot['real_stock'] 		+= $real_stock;
					$tot['stock'] 			+= $stock;

					$return_item = $this->returnmodel->get_return_item_ea($data_sub['item_seq'],$data_sub['item_suboption_seq']);
					$able_return_ea += ($data_sub['step55']+$data_sub['step65']+$data_sub['step75']) - (int) $return_item['ea'];

					$tmp_out_price = ($data_sub['out_price'] - $data_sub['out_tot_sale']);
					$orders['settleprice'] += $tmp_out_price;
					if($item['tax'] == 'tax'){
						$tmp_p_price	= round($tmp_out_price / 1.1);
						$provider_price += $tmp_p_price;
						$tax_price		+= $tmp_out_price - $tmp_p_price;
					}else{
						$provider_price += $tmp_out_price;
						$tax_price		+= 0;
					}
					//debug($provider_price);
				}

				$data['suboptions']	= $suboptions;
				$options[$k] = $data;

				$item['totaloptitems']		+= count($suboptions) + 1;

				$tot_sales += $data['out_tot_sale'] > $tot['price'] ? $tot['price'] : $data['out_tot_sale'];
				$tot_real_price = $tot['price'];

				$this->template->assign("tot_sales",	$tot_sales);
				$this->template->assign("tot_real_price",	$tot_real_price);

				if($item['tax'] == 'tax'){
					$tmp_p_price	= round($data['out_real_price'] / 1.1);
					$provider_price += $tmp_p_price;
					$tax_price		+= $data['out_real_price'] - $tmp_p_price;
				}else{
					$provider_price += $data['out_real_price'];
					$tax_price		+= 0;
				}
			}
			$item['options']			= $options;
			$items[$key] 				= $item;
			$tot['goods_shipping_cost']	+= $item['goods_shipping_cost'];
		}

		/* 주문상품을 배송그룹별로 분할 */
		$shipping_group_items=array();
		foreach($items as $item){
			$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost_sum']+= $item['goods_shipping_cost'];
			$shipping_group_items[$item['shipping_seq']]['rowspan'] += count($item['options']) + count($item['suboptions']);
			$shipping_group_items[$item['shipping_seq']]['items'][] = $item;
			$shipping_group_items[$item['shipping_seq']]['totalitems'] += $item['totaloptitems'];

			if($item['tax'] == 'tax'){
				$shipping_group_items[$item['shipping_seq']]['istax'] = 1;
			}else{
				if($shipping_group_items[$item['shipping_seq']]['istax'] == 0){
					$shipping_group_items[$item['shipping_seq']]['istax'] = 0;
				}
			}
		}

		$this->load->helper('shipping');
		$arr_shipping_method = get_shipping_method('all');
		foreach($shipping_group_items as $shipping_seq=>$row){
			$query = $this->db->query("select a.*, b.provider_name
			from fm_order_shipping a
			inner join fm_provider b on a.provider_seq = b.provider_seq
			where a.shipping_seq=?",$shipping_seq);
			$shipping = $query->row_array();
			$shipping['shipping_method_name'] = $arr_shipping_method[$shipping['shipping_type']];
			$shipping_group_items[$shipping_seq]['shipping'] = $shipping;

			// 선불로 계산한 경우에만 계산하게 수정
			if($shipping['shipping_type'] == 'prepay'){
				$shipping_tot['basic_cost']				+= $shipping['delivery_cost'];
				$shipping_tot['add_shipping_cost']		+= $shipping['add_delivery_cost'];
				$shipping_tot['shipping_cost']			+= $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
				$tmp_shipping							 = $shipping['delivery_cost'] + $shipping['add_delivery_cost'];
			}

			$shipping_tot['coupon_sale']		+= $shipping['shipping_coupon_sale'];
			$shipping_tot['code_sale']			+= $shipping['shipping_promotion_code_sale'];
			$tmp_shipping						-= ($shipping['shipping_coupon_sale'] + $shipping['shipping_promotion_code_sale']);
			if($row['istax']){
				$tmp_p_price	= round($tmp_shipping / 1.1);
				$provider_price += $tmp_p_price;
				$tax_price		+= $tmp_shipping - $tmp_p_price;
			}else{
				$provider_price += $tmp_shipping;
				$tax_price		+= 0;
			}
		}
		// 할인된 배송비로 계산 - feat.기획팀 :: 2019-02-18 lwh
		$shipping_tot['total_shipping_cost']		= $shipping_tot['basic_cost'] + $shipping_tot['goods_cost'] + $shipping_tot['add_shipping_cost'] - $shipping_tot['coupon_sale'] - $shipping_tot['code_sale'];
		$orders['settleprice'] += $shipping_tot['total_shipping_cost'];

		// 마일리지 할인으로 처리 :: 2018-07-12 lkh
		if($orders['emoney_use'] == "use"){
			$orders['settleprice'] -= $orders['emoney'];
		}

		$this->template->assign(array('shipping_tot'=> $shipping_tot));
		$this->template->assign(array('shipping_group_items'=> $shipping_group_items));

		// 회원 정보 가져오기
		if($orders['member_seq']){
			$members = $this->membermodel->get_member_data($orders['member_seq']);
			$this->template->assign(array('members'=>$members));
		}

		$orders['order_seq'] = $order_seq;

		$this->template->assign(array('orders'	=> $orders));
		$this->template->assign(array('items'	=> $items));
		$this->template->assign(array('items_tot'	=> $tot));

		$this->template->assign(array('provider_price'		=> $provider_price));
		$this->template->assign(array('tax_price'			=> $tax_price));

		$this->template->assign("businessLicense",	$this->config_basic['businessLicense']);
		$this->template->assign("companyName",		$this->config_basic['companyName']);
		$this->template->assign("ceo",				$this->config_basic['ceo']);

		if($this->config_basic['companyAddress_type'] == 'street'){
			$companyAddress = $this->config_basic['companyAddress_street'] . ' ' . $this->config_basic['companyAddressDetail'];
		}else{
			$companyAddress = $this->config_basic['companyAddress'] . ' ' . $this->config_basic['companyAddressDetail'];
		}

		$this->template->assign("companyAddress",	$companyAddress);
		$this->template->assign("companyPhone",		$this->config_basic['companyPhone']);
		$this->template->assign("domain",			$this->config_system['domain']);
		$this->template->assign("signatureicon",	$this->config_system['signatureicon']);
		$this->template->assign("user_name",		$orders['order_user_name']);

		$this->template->print_("PRINT_PATH");
	}

	function form_print_estimate(){
		$code = $this->input->get('code');

		if($code == 'cart'){
			$this->print_cart();
		}else if($code == 'order'){
			$this->print_order();
		}


		//################### 2016.03.28 기존 장바구니에서 추가 된 소스 pjw #######################

		//견적정보
		$estimate_num	= date('YmdHis').$this->get_create_randcode();
		$estimate_date	= date('Y년 m월 d일');

		//사용자정보
		$username		= $this->session->userdata('user');

		//공급가, 부가세 계산
		$this->template->assign("code",				$code);
		$this->template->assign("estimate_num",		$estimate_num);
		$this->template->assign("estimate_date",	$estimate_date);
		$this->template->assign("businessLicense",	$this->config_basic['businessLicense']);
		$this->template->assign("companyName",		$this->config_basic['companyName']);
		$this->template->assign("ceo",				$this->config_basic['ceo']);

		if($this->config_basic['companyAddress_type'] == 'street'){
			$companyAddress = $this->config_basic['companyAddress_street'] . ' ' . $this->config_basic['companyAddressDetail'];
		}else{
			$companyAddress = $this->config_basic['companyAddress'] . ' ' . $this->config_basic['companyAddressDetail'];
		}

		$this->template->assign("companyAddress",	$companyAddress);
		$this->template->assign("companyPhone",		$this->config_basic['companyPhone']);
		$this->template->assign("domain",			$this->config_system['domain']);
		$this->template->assign("signatureicon",	$this->config_system['signatureicon']);
		$this->template->assign("user_name",		$username['user_name']);

		//################### 2016.03.28 기존 장바구니에서 추가 된 소스 pjw #######################

		$this->template->print_("PRINT_PATH");
	}

	function print_cart(){
		$this->load->model('cartmodel');
		$this->load->model('goodsmodel');
		$this->load->model('Providershipping');

		$applypage			= 'cart';
		$total_ea			= 0;
		$goodscancellation	= false;
		$is_coupon			= false;
		$is_goods			= false;

		// 장바구니 및 주문설정, 마일리지설정 정보 추출
		$cart			= $this->cartmodel->catalog();
		$cfg['order']		= ($this->cfg_order) ? $this->cfg_order : config_load('order');
		$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');

		if($person_seq == ""){
			$this->template->assign('firstmallcartid',session_id());
		}

		//----> sale library 적용
		$param['cal_type']				= 'list';
		$param['total_price']			= $cart['total'];
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용


		// 장바구니에서 구한 데이터 초기화
		$cart['total']						= 0;
		$cart['total_ea']					= 0;
		$cart['total_sale']					= 0;
		$cart['total_price']				= 0;
		$cart['total_reserve']				= 0;
		$cart['total_point']				= 0;

		$total_price_for_shop_delivery	= 0;		// 기본배송 상품 합계금액

		$tax_price	= 0;	//과세금액
		$free_price = 0;	//비과세금액

		// 장바구니 목록
		foreach($cart['list'] as $key => $data){

			// 해당 상품의 전체 카테고리
			$category	= array();
			$tmp		= $this->goodsmodel->get_goods_category($data['goods_seq']);
			foreach($tmp as $row)	$category[]		= $row['category_code'];

			//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
			unset($param, $sales);
			$param['option_type']				= 'option';
			$param['consumer_price']			= $data['consumer_price'];
			$param['price']						= $data['org_price'];
			$param['sale_price']				= $data['price'];
			$param['ea']						= $data['ea'];
			$param['goods_ea']					= $cart['data_goods'][$data['goods_seq']]['ea'];
			$param['option_ea']					= $cart['data_goods'][$data['goods_seq']]['option_ea'];
			$param['category_code']				= $category;
			$param['goods_seq']					= $data['goods_seq'];
			$param['goods']						= $data;
			$this->sale->set_init($param);
			$sales								= $this->sale->calculate_sale_price($applypage);

			$data['org_price']					= ($data['consumer_price']) ? $data['consumer_price'] : $data['org_price'];
			$data['sale_price']					= $sales['one_result_price'];
			$data['sales']						= $sales;
			$data['tot_org_price']				= $data['org_price'] * $data['ea'];
			$data['tot_sale_price']				= $sales['total_sale_price'];
			$data['tot_result_price']			= $sales['result_price'];

			// sale_price가 없을 시 여기서 event까지 계산함
			if	(!$data['event'] && $this->sale->cfgs['event']){
				$data['event']					= $this->sale->cfgs['event'];
				$data['eventEnd']				= $sales['eventEnd'];
			}

			// 마일리지 / 포인트 ( 실결제 금액 기준이기에 여기서 계산 )
			// 상품의 마일리지 / 포인트 계산
			// 원단위 마일리지 구매갯수 만큼 계산 안되는 오류로 $sales['result_price'] => $sales['one_result_price'] 변경 2015-03-30
			$data['reserve']	= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'],
									$sales['one_result_price'], $cfg_reserve['default_reserve_percent'],
									$data['reserve_rate'], $data['reserve_unit'], $data['reserve']) *$data['ea'];
			$data['point']		= (int) $this->goodsmodel->get_point_with_policy($sales['one_result_price']) *$data['ea'];

			// 이벤트 마일리지 / 포인트 계산 ( 장바구니에서 혜택적용할인가를 받아서 쓸 경우만 )
			if	($param['sale_price']){
				$this->sale->cfgs['event']		= $data['event'];
				$data['reserve']				+= $this->sale->event_sale_reserve($sales['result_price']);
				$data['point']					+= $this->sale->event_sale_point($sales['result_price']);
			}
			$data['reserve']					= $data['reserve'] + $sales['tot_reserve'];
			$data['point']						= $data['point'] + $sales['tot_point'];

			// 총 합계
			$cart['total']						+= $data['price']*$data['ea'];
			$cart['total_ea']					+= $data['ea'];
			$cart['total_sale']					+= $sales['total_sale_price'];
			$cart['total_price']				+= $sales['result_price'];
			$cart['total_reserve']				+= $data['reserve'];
			$cart['total_point']				+= $data['point'];

			// 총 할인 목록 노출을 위한 배열 생성
			if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $sale_title){
				$data['tsales']['sale_list'][$sale_type]		= $sales['sale_list'][$sale_type];
				$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
				$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
			}
			$this->sale->reset_init();
			//<---- sale library 적용

			// 기본배송 상품 합계금액
			if($data['shipping_policy']=='shop')
				$total_price_for_shop_delivery	+= $sales['result_price'];

			// 추가구성 옵션
			if	($data['cart_suboptions']){
				foreach($data['cart_suboptions'] as $k => $subdata){
					//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
					unset($param, $sales);
					$param['option_type']				= 'suboption';
					$param['sub_sale']					= $subdata['sub_sale'];
					$param['consumer_price']			= $subdata['consumer_price'];
					$param['price']						= $subdata['price'];
					$param['sale_price']				= $subdata['price'];
					$param['ea']						= $subdata['ea'];
					$param['goods_ea']					= $cart['data_goods'][$data['goods_seq']]['ea'];
					$param['category_code']				= $category;
					$param['goods_seq']					= $data['goods_seq'];
					$param['goods']						= $data;
					$this->sale->set_init($param);
					$sales								= $this->sale->calculate_sale_price($applypage);

					$subdata['sale_price']				= $sales['one_result_price'];
					$subdata['sales']					= $sales;
					$subdata['org_price']				= ($subdata['consumer_price']) ? $subdata['consumer_price'] : $subdata['price'];

					// 마일리지 / 포인트
					$subdata['reserve']					= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'], $sales['one_result_price'], $cfg_reserve['default_reserve_percent'], $subdata['reserve_rate'], $subdata['reserve_unit'], $subdata['reserve']) * $subdata['ea'];
					$subdata['point']					= $this->goodsmodel->get_point_with_policy($sales['one_result_price']) * $subdata['ea'];
					$subdata['reserve']					= $subdata['reserve'] + $sales['tot_reserve'];
					$subdata['point']					= $subdata['point'] + $sales['tot_point'];

					$data['tot_org_price']				+= $subdata['org_price'] * $subdata['ea'];
					$data['tot_sale_price']				+= $sales['total_sale_price'];
					$data['tot_result_price']			+= $sales['result_price'];

					$cart['total']						+= $subdata['price']*$subdata['ea'];
					$cart['total_ea']					+= $subdata['ea'];
					$cart['total_sale']					+= $sales['total_sale_price'];
					$cart['total_price']				+= $sales['result_price'];
					$cart['total_reserve']				+= $subdata['reserve'];
					$cart['total_point']				+= $subdata['point'];
					if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $sale_title){
						$data['tsales']['sale_list'][$sale_type]		+= $sales['sale_list'][$sale_type];
						$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
						$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
					}

					$this->sale->reset_init();
					//<---- sale library 적용

					// 기본배송 상품 합계금액
					if($data['shipping_policy']=='shop')
						$total_price_for_shop_delivery	+= $sales['result_price'];

					$data['cart_suboptions'][$k]	= $subdata;
				}
			}

			$cart['list'][$key]		= $data;
			if	($data['shipping_policy'] == 'goods')
				$data['shipping_policy']	.= '_'.$data['cart_seq'];
			$group_key				= $data['goods_type'].'|'.$data['shipping_policy'];
			$shipping_cart_list[$group_key][]				= $data;
			$shipping_cart_list[$group_key][0]['rowspan']	+= count($data['cart_suboptions']) + 1;

			# 비과세액
			if($data['tax'] == "exempt"){
				$free_price += $data['tot_result_price'];	//비과세 상품액
			}
		}

		//좋아요 할인 혜택구분 $cfg['order']['fblike_ordertype'] 0 : 회원/비회원, 1 : 회원만
		$session_arr	= ( $this->session->userdata('user') ) ? $this->session->userdata('user') : $_SESSION['user'];
		// 설정값을 적용여부 값으로 변경
		$cfg['order']['fblike_ordertype']	= ( ($cfg['order']['fblike_ordertype'] == 1 && $session_arr['member_seq'] ) || ($cfg['order']['fblike_ordertype'] != 1) ) ? 1 : 0;

		// 실 결제금액 기준으로 배송비 재계산
		$shipping						= use_shipping_method();

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

		$this->shipping_order			= $shipping;
		if	(is_array($shipping)){
			$international_shipping		= $shipping[1][$_POST['shipping_method_international']];
		}

		/*배송비 받아오지 않아 추가 (기존 소스가 안된다는 조건 하에 (견적서만 안되서 수정)) 20170531 ldb start*/
		// ### NEW 배송 그룹 정보 추출 :: START ### -> shipping library 계산
		$this->load->library('shipping');
		$shipping_group_list	= $this->shipping->get_shipping_groupping($cart['list']);

		foreach ($shipping_group_list as $shipping_group_id => $shipping_group) {
			$shipping_shop_cnt[$shipping_group_id] = 0;

			foreach ($shipping_group['goods'] as $k => $list) {
				if($list['tax'] == 'tax') {
					$shipping_shop_cnt[$shipping_group_id]++;
				}
			}

			# 기본배송상품 모두 비과세 상품일때 : 배송비 비과세
			# 기본배송상품 중 1개라도 과세 상품일때 : 배송비도 과세
			if ($shipping_shop_cnt[$shipping_group_id] == 0) {
				$free_price += $shipping_group['grp_shipping_price'];
			}
		}
		
		// 전체 배송비 금액 추출
		$total_shipping_price	= $shipping_group_list['total_shipping_price'];

		$this->template->assign('shipping_group_list', $shipping_group_list);
		$cart['total_price']	+= $total_shipping_price; //배송비계산식아래쪽으로 이동 ($cart['shipping_price']) 위치변경하지 말아주세요.

		$cart_promotioncode = $this->session->userdata('cart_promotioncode_'.session_id());

		// 스킨 로드
		$template_dir	= $this->template->template_dir;
		$compile_dir	= $this->template->compile_dir;
		$this->template->assign('firstmallcartid', session_id());
		$this->template->assign('list', $cart['list']);
		$this->template->assign('shipping_cart_list', $shipping_cart_list);
		$this->template->assign('data_goods', $cart['data_goods']);
		$this->template->assign('is_coupon',$is_coupon);
		$this->template->assign('is_goods',$is_goods);
		$this->template->assign('cfg',$cfg);
		$this->template->assign('promocodeSale',$cart['promocodeSale']);
		$this->template->assign('total',$cart['total']);
		$this->template->assign('total_ea',$cart['total_ea']);
		$this->template->assign('total_reserve',$cart['total_reserve']);
		$this->template->assign('total_point',$cart['total_point']);
		$this->template->assign('total_sale',$cart['total_sale']);
		$this->template->assign('total_sale_list',$cart['total_sale_list']);
		$this->template->assign('total_price',$cart['total_price']);
		$this->template->assign('shipping_price',$total_shipping_price);
		$this->template->assign('shipping_company_cnt',$cart['shipping_company_cnt']);
		$this->template->assign('shop_shipping_policy',$cart['shop_shipping_policy']);
		$this->template->assign('cart_history',$cart_history);
		$this->template->assign('member_seq',$this->userInfo['member_seq']);
		$this->template->assign('cart_promotioncode' , $cart_promotioncode);

		$this->template->assign('cartpage',true);//현재페이지정보 넘겨줌

		$tax_price		= $cart['total_price']-$free_price;		//총액 - 비과세
		$provider_price	= ceil($tax_price / 1.1);				//비과세를 제외한 공급가액
		$tax_price		= floor($tax_price - $provider_price);//부가세액
		$provider_price	+= $free_price;									//최종 공급가액

		$this->template->assign("provider_price",	$provider_price);
		$this->template->assign("tax_price",		$tax_price);

		$international_shipping_info_path	= str_replace('cart.html', '../goods/_international_shipping_info.html', $this->template_path());
		$this->template->define('INTERNATIONAL_SHIPPING_INFO', $international_shipping_info_path);

	}

	function print_order(){
		$this->load->model('ssl');
		$this->ssl->decode();

		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
		$this->load->library('validation');
		$this->load->model('statsmodel');

		$adminOrder=$_POST['adminOrder'];
		$person_seq=$_POST['person_seq'];

		$this->calculate($adminOrder, $person_seq);

		$tax_price	= 0;	//과세금액
		$free_price = 0;	//비과세금액

		foreach($this->cart['list'] as $key => $data){

			if($data['shipping_policy'] == "goods"){
				$shipping_policy = $data['shipping_policy'].$data['goods_seq'];
			}else{
				$shipping_policy = $data['shipping_policy'];
			}

			$group_key				= $data['goods_type'].'|'.$data['shipping_policy'];
			$shipping_cart_list[$group_key][]				= $data;
			$shipping_cart_list[$group_key][0]['rowspan']	+= count($data['cart_suboptions']) + 1;

			# 비과세액
			if($data['tax'] == "exempt"){
				$free_price += $data['tot_result_price'];	//비과세 상품액
			}
		}

		//추가지역 배송비 @2016-08-12 ysm
		$tot_add_delivery = 0;
		foreach($this->shipping_group_policy as $shipping_group=>$row){
			$add_delivery_cost += (int) $row['add_delivery_cost'];
		}

		/*배송비 받아오지 않아 추가 (기존 소스가 안된다는 조건 하에 (견적서만 안되서 수정)) 20170531 ldb start*/
		// ### NEW 배송 그룹 정보 추출 :: START ### -> shipping library 계산
		$this->load->library('shipping');
		$shipping_group_list	= $this->shipping->get_shipping_groupping($this->cart['list']);

		foreach ($shipping_group_list as $shipping_group_id => $shipping_group) {
			$shipping_shop_cnt[$shipping_group_id] = 0;

			foreach ($shipping_group['goods'] as $k => $list) {
				if($list['tax'] == 'tax') {
					$shipping_shop_cnt[$shipping_group_id]++;
				}
			}

			# 기본배송상품 모두 비과세 상품일때 : 배송비 비과세
			# 기본배송상품 중 1개라도 과세 상품일때 : 배송비도 과세
			if ($shipping_shop_cnt[$shipping_group_id] == 0) {
				$free_price += $shipping_group['grp_shipping_price'];
			}
		}

		// 전체 배송비 금액 추출
		$total_shipping_price	= $shipping_group_list['total_shipping_price'];

		$this->template->assign('shipping_group_list', $shipping_group_list);

		$this->cart['total_price']	+= $total_shipping_price; //배송비계산식아래쪽으로 이동 ($cart['shipping_price']) 위치변경하지 말아주세요.


		$this->cart['shipping_price']['add_delivery_cost'] = $add_delivery_cost;
		$this->template->assign('add_delivery_cost',$this->area_add_delivery_cost);

		$this->template->assign('firstmallcartid', session_id());
		$this->template->assign('list', $this->cart['list']);
		$this->template->assign('shipping_cart_list', $shipping_cart_list);
		$this->template->assign('data_goods', $this->cart['data_goods']);
		$this->template->assign('is_coupon',$is_coupon);
		$this->template->assign('is_goods',$is_goods);
		$this->template->assign('cfg',$cfg);
		$this->template->assign('promocodeSale',$this->cart['promocodeSale']);
		$this->template->assign('total',$this->cart['total']);
		$this->template->assign('total_ea',$this->cart['total_ea']);
		$this->template->assign('total_reserve',$this->cart['total_reserve']);
		$this->template->assign('total_point',$this->cart['total_point']);
		$this->template->assign('total_sale',$this->cart['total_sale']);
		$this->template->assign('total_sale_list',$this->cart['total_sale_list']);
		$this->template->assign('total_price',$this->cart['total_price']);
		$this->template->assign('shipping_price',$total_shipping_price);
		$this->template->assign('shipping_company_cnt',$this->cart['shipping_company_cnt']);
		$this->template->assign('shop_shipping_policy',$this->cart['shop_shipping_policy']);
		$this->template->assign('cart_history',$cart_history);
		$this->template->assign('member_seq',$this->userInfo['member_seq']);
		$this->template->assign('emoney',$this->cart['emoney']);//현재페이지정보 넘겨줌
		$this->template->assign('cash',$this->cart['cash']);//현재페이지정보 넘겨줌
		$this->template->assign('cart_promotioncode' , $cart_promotioncode);
		$this->template->assign('cartpage',true);//현재페이지정보 넘겨줌

		$tax_price		= $this->cart['total_price']-$free_price;		//총액 - 비과세
		$provider_price	= ceil($tax_price / 1.1);						//비과세를 제외한 공급가액
		$tax_price		= floor($tax_price - $provider_price);			//부가세액
		$provider_price	+= $free_price;									//최종 공급가액
		$this->template->assign("provider_price",	$provider_price);
		$this->template->assign("tax_price",		$tax_price);
	}

	function get_create_randcode(){
		$rnd_token = mt_rand(0, 9999);

		for($i=strlen($rnd_token); $i<4; $i++){
			$rnd_token = "0".$rnd_token;
		}

		return $rnd_token;
	}

	/* 단일배송지 실결제배송비 계산 */
	public function _calculate_single_shipping_price(&$scripts,&$cart,$total_goods_price,&$shipping,&$international_shipping){

		/* 해외 배송비 */
		$start = 0;
		if($international_shipping['goodsWeight']) foreach($international_shipping['goodsWeight'] as $key => $weight){
			$end = $weight;
			if($start < $cart['total_goods_weight'] && $end >= $cart['total_goods_weight'] ){
				$goods_row = $key;
			}elseif($start < $cart['total_goods_weight'] && $end < $cart['total_goods_weight']){//그이상의무게인경우 가장큰무게로설정
				$goods_row = $key;
			}
			$start = $weight;
		}
		$cost_key = $_POST['region'] + (count($international_shipping['region'])*$goods_row);
		$international_shipping_price = (int) $international_shipping['deliveryCost'][$cost_key];


		$total_shop_shipping_price		= (int) $cart['shop_shipping_policy']['price']; // 결제할 기본배송비
		$this->shipping_cost			= $total_shop_shipping_price;
		$total_add_shipping_price 		= 0; // 결제할 지역별추가배송비
		$total_goods_shipping_price 	= (int) $cart['shipping_price']['goods']; // 결제할 개별배송비

		// 지역별 추가 배송비
		if($shipping[0][0]['code'] == 'delivery'){
			$door2door = $shipping[0][0];

			$addDeliveryCost = 0;
			$addDeliveryType = config_load('adddelivery', 'addDeliveryType');

			if($_POST["recipient_address_street"] && $addDeliveryType['addDeliveryType'] == 'street'){

				if($door2door["sigungu_street"]){
					asort($door2door["sigungu_street"]);
					$recipientAddressStreet		= array();
					$recipientAddressStreet		= explode(" ", trim($_POST["recipient_address_street"]));

					foreach($door2door["sigungu_street"] as $sigungu_key => $sigungu_street){
						$streetSigungu		= array();
						$address_diff		= array();
						$streetSigungu		= explode(" ", trim($sigungu_street));
						$compare_cnt		= count($streetSigungu);
						$address_compare	= array_intersect($streetSigungu, $recipientAddressStreet);

						if($compare_cnt == count($address_compare)){
							$addDeliveryCost	= $door2door['addDeliveryCost'][$sigungu_key];
						}else if($compare_cnt > 2 && count($address_compare) == 2 && preg_match('/'.$streetSigungu[2].'/',$recipientAddressStreet[2])){
							$addDeliveryCost	= $door2door['addDeliveryCost'][$sigungu_key];
						}
					}
				}

			}else{
				if($door2door['sigungu']){
					foreach($door2door['sigungu'] as $sigungu_key => $sigungu){
						if(preg_match('/'.$sigungu.'/',$_POST["recipient_address"])){
							if((int)$addDeliveryCost < $door2door['addDeliveryCost'][$sigungu_key]){
									$addDeliveryCost = $door2door['addDeliveryCost'][$sigungu_key];
							}
						}
					}
				}
			}
		}

		if( $_POST['international'] == 0 ){
			if( $_POST['shipping_method'] == 'delivery' || !$_POST['shipping_method'] ){



				// 조건부 무료배송 금액차감
				if($shipping[0][0]['deliveryCostPolicy'] == 'ifpay'){
					if($total_goods_price && $shipping[0][0]['ifpayFreePrice']){
						if($shipping[0][0]['ifpayFreePrice'] <= $total_goods_price){
							$this->shipping_cost = $total_shop_shipping_price = 0;
						}
					}
				}


				// 특정상품 구매시 무료
				$orderDeliveryFree = false;
				foreach($cart['data_goods'] as $goods_seq => $data_goods){

					if($shipping[0][0]['issueGoods'] && in_array($goods_seq,$shipping[0][0]['issueGoods'])){
						$orderDeliveryFree = true;

					}

					if( $data_goods['r_category'] ) foreach($data_goods['r_category'] as $catecd){
						if($shipping[0][0]['issueCategoryCode'] && in_array($catecd,$shipping[0][0]['issueCategoryCode'])){
							$orderDeliveryFree = true;
						}
					}

					if( $data_goods['r_brand'] ) foreach($data_goods['r_brand'] as $brandcd){
						if($shipping[0][0]['issueBrandCode'] && in_array($brandcd,$shipping[0][0]['issueBrandCode'])){
							$orderDeliveryFree = true;
						}
					}

					// 개별 배송 상품 지역별 추가 배송비
					if($data_goods['shipping_policy']=='goods' && $data_goods['goods_kind']=='goods'){
						if($data_goods['limit_shipping_ea']){
							$cart['data_goods'][$goods_seq]['add_goods_shipping'] = (int) $addDeliveryCost * ceil($data_goods['ea'] / $data_goods['limit_shipping_ea']);
						}else{
							$cart['data_goods'][$goods_seq]['add_goods_shipping'] = (int) $addDeliveryCost;
						}

						$total_goods_add_shipping_price += $cart['data_goods'][$goods_seq]['add_goods_shipping'];
					}

					if($data_goods['shipping_policy']=='shop'){
						$order_basic_delivery = true;
					}

				}

				foreach($cart['data_goods'] as $goods_seq => $data_goods){
					if(in_array($goods_seq,$shipping[0][0]['exceptIssueGoods'])){
						$orderDeliveryFree = false;
					}
				}

				if( $shipping[0][0]['orderDeliveryFree'] == 'free' && $orderDeliveryFree){
					$this->shipping_cost = $total_shop_shipping_price = 0;
				}


				// 지역별 추가 배송비
				if($shipping[0][0]['code'] == 'delivery'){

					if(count($cart["list"]) == 1 && $cart['box_ea'] > 1){
						$cart['box_ea'] = 1;
					}

					//$cart['shipping_price']['shop'] += (int) $addDeliveryCost * $cart['box_ea'];
					if( $order_basic_delivery ){
						$total_add_shipping_price += (int) $addDeliveryCost;
					}
				}


				$this->total_goods_add_shipping_price += $total_goods_add_shipping_price;
				$this->area_add_delivery_cost += $total_add_shipping_price;
				$this->shipping_cost += $total_add_shipping_price;


			}elseif($_POST['shipping_method'] == 'postpaid'){
				$total_goods_add_shipping_price = 0;
				$total_shop_shipping_price	= 0;
				$this->shipping_cost	= 0;
				$this->postpaid = $total_goods_shipping_price;

				if($shipping[0][0]['deliveryCostPolicy'] == 'ifpay'){
					if(!$total_goods_price || !$shipping[0][0]['ifpayFreePrice'] || $shipping[0][0]['ifpayFreePrice'] > $total_goods_price){
						$this->postpaid += (int) $shipping[0][0]['ifpostpaidDeliveryCost'];
					}
				}else{
					$this->postpaid += $shipping[0][0]['postpaidDeliveryCost'];
				}

				foreach($cart['data_goods'] as $goods_seq => $data_goods){
					if($data_goods['limit_shipping_ea']){
						$cart['data_goods'][$goods_seq]['add_goods_shipping'] = (int) $addDeliveryCost * ceil($data_goods['ea'] / $data_goods['limit_shipping_ea']);
					}else{
						$cart['data_goods'][$goods_seq]['add_goods_shipping'] = (int) $addDeliveryCost;
					}
					$total_goods_add_shipping_price += $data_goods['add_goods_shipping'];
				}
				$this->postpaid += (int) $total_goods_add_shipping_price;

			}else{
				$total_shop_shipping_price	= 0;
				$this->shipping_cost	= 0;
			}
		}else{
			$total_shop_shipping_price = $international_shipping_price;
			$this->shipping_cost = (int) $total_shop_shipping_price;
		}

		//프로모션코드 배송비할인
		$shipping_promotion_code_sale = $this->_get_shipping_promotion_code_sale($total_shop_shipping_price,$cart['total']);
		$total_shop_shipping_price = zerobase($total_shop_shipping_price-$shipping_promotion_code_sale);

		//배송비쿠폰 선택시 - 배송비 할인 본사배송상품만할인
		$shipping_coupon_sale = $this->_get_shipping_coupon_sale($total_shop_shipping_price);
		$total_shop_shipping_price = zerobase($total_shop_shipping_price-$shipping_coupon_sale);

		/* 결제할 배송비 = 기본배송비 + 추가배송비 + 개별배송비 */
		if($total_goods_shipping_price) $this->arr_goods_shipping_ck = $total_goods_shipping_price;//개별배송비 안내문구추가

		$this->total_cart_shop_shipping_price	= $total_shop_shipping_price;
		$this->total_cart_goods_shipping_price	= $total_goods_shipping_price;

		$total_shipping_price = $total_shop_shipping_price + $total_add_shipping_price + $total_goods_shipping_price + $total_goods_add_shipping_price;

		return $total_shipping_price;
	}

	// 프로모션코드 배송비할인
	public function _get_shipping_promotion_code_sale($total_shop_shipping_price, $sum_goods_price){
		//이벤트 배송비코드 사용제한 @2015-08-16
		if( $this->ordernosales_cd_sh ) return;

		if($this->session->userdata('cart_promotioncode_'.session_id())) {
			$shipping_promotions = $this->promotionmodel->get_able_download_saleprice($this->session->userdata('cart_promotioncodeseq_'.session_id()),$this->session->userdata('cart_promotioncode_'.session_id()), $sum_goods_price, '','');
		}

		//프로모션코드 본사배송상품 배송비할인
		$this->shipping_promotion_code_sale=0;
		if($total_shop_shipping_price > 0 && $shipping_promotions){//본사배송상품
			if($shipping_promotions['sale_type'] == 'shipping_free' &&  $shipping_promotions['promotioncode_shipping_sale_max']>0) {//기본배송비무료
				if($total_shop_shipping_price < $shipping_promotions['promotioncode_shipping_sale_max']) {
					$this->shipping_promotion_code_sale = $total_shop_shipping_price;//기본배송비무료
				}else{
					$this->shipping_promotion_code_sale = $shipping_promotions['promotioncode_shipping_sale_max'];
				}
			}elseif($shipping_promotions['sale_type'] == 'shipping_won' && $shipping_promotions['promotioncode_shipping_sale']>0) {//배송비할인가
				if($total_shop_shipping_price < $shipping_promotions['promotioncode_shipping_sale']) {
					$this->shipping_promotion_code_sale = $total_shop_shipping_price;//기본배송비무료
				}else{
					$this->shipping_promotion_code_sale = $shipping_promotions['promotioncode_shipping_sale'];
				}
			}
		}

		return $this->shipping_promotion_code_sale;
	}

	// 배송비쿠폰 선택시 - 배송비 할인 본사배송상품만할인
	public function _get_shipping_coupon_sale($total_shop_shipping_price){
		//이벤트 배송비코드 사용제한 @2015-08-16
		if( $this->ordernosales_cp_sh ) return;

		$this->shipping_coupon_payment_b = false;
		if($_POST['download_seq'] && $_POST['coupon_sale']>0 && $total_shop_shipping_price ) {
			$this->shipping_coupon_down_seq = $_POST['download_seq'];
			$shippingcoupon = $this->couponmodel->get_download_coupon($this->shipping_coupon_down_seq);
			//무통장만 사용가능
			if($shippingcoupon['sale_payment'] == 'b' && $this->shipping_coupon_payment_b != true ) $this->shipping_coupon_payment_b = true;

			if($total_shop_shipping_price < $_POST['coupon_sale'] ){
				$this->shipping_coupon_sale = $total_shop_shipping_price;
			}else{
				$this->shipping_coupon_sale = $_POST['coupon_sale'];
			}
		}

		return $this->shipping_coupon_sale;
	}

	/* 복수배송지 실결제배송비 계산 */
	public function _calculate_multi_shipping_price(&$scripts,&$cart,&$shipping){

		$total_shop_goods_price = 0; // 기본배송 상품금액 합계

		$total_shop_shipping_price		= 0; // 결제할 기본배송비
		$total_add_shipping_price 		= 0; // 결제할 지역별추가배송비
		$total_goods_shipping_price 	= 0; // 결제할 개별배송비

		$this->arr_multi_shop_shipping_price = array();				// 배송지별 기본배송비
		$this->arr_multi_add_shipping_price = array();				// 배송지별 추가배송비
		$this->arr_multi_goods_shipping_price = array(); 			// 배송지별 개별배송비
		$this->arr_multi_shipping_promition_code_sale = array();	// 배송지별 프로모션코드 배송비할인
		$this->arr_multi_shipping_coupon_sale = array();			// 배송지별 쿠폰 배송비할인
		$this->arr_goods_shipping_price = array(); 					// 상품별 개별배송비

		/* 복수배송지 설정 POST값을 토대로 $cart['list']와 유사한 형태의 데이터를 생성 */
		$multiShippingItems = $this->_get_multi_shipping_item_list($cart);

		// 배송지별  지역추가배송비
		if($shipping[0][0]['code'] == 'delivery'){
			$door2door = $shipping[0][0];
			$addDeliveryCost = 0;
			if($door2door['sigungu']) foreach($door2door['sigungu'] as $sigungu_key => $sigungu){
				if(preg_match('/'.$sigungu.'/',$_POST['multi_recipient_address'][$multiIdx])){
					$addDeliveryCost += $door2door['addDeliveryCost'][$sigungu_key];
				}
			}
		}


		foreach($multiShippingItems as $multiIdx => $items){
			$box_ea = 0;
			$default_box_ea = false;
			$order_basic_delivery = false;
			$shipping_price['goods'] = 0;

			// 배송지별 상품개별배송비
			foreach($items as $itemIdx=>$row){
				$goods_shipping = $row['goods_shipping_policy'];

				$row['goods_shipping'] = 0;
				if($row['shipping_policy'] == 'shop'){
					$total_shop_goods_price += $row['price'];
					$default_box_ea = true;
					$shop_shipping_policy = $goods_shipping;
				}else{
					$this->arr_multi_goods_shipping_price[$multiIdx] += $goods_shipping['price'];
					$this->arr_goods_shipping_price[$row['goods_seq']] += $goods_shipping['price'];
					$box_ea += $goods_shipping['box_ea'];
				}

				// 개별 배송 상품 지역별 추가 배송비
				if($row['shipping_policy']=='goods'){
					if($row['limit_shipping_ea']){
						$row['add_goods_shipping'] = (int) $addDeliveryCost * ceil($row['ea'] / $row['limit_shipping_ea']);
					}else{
						$row['add_goods_shipping'] = (int) $addDeliveryCost;
					}
					$this->arr_add_goods_shipping[$multiIdx][$itemIdx] = $row['add_goods_shipping'];
					$this->arr_multi_add_shipping_price[$multiIdx] += (int) $row['add_goods_shipping'];
				}else{
					$order_basic_delivery = true;
				}
			}

			if($default_box_ea) $box_ea += 1;


			if($shipping[0][0]['code'] == 'delivery'){
				if($order_basic_delivery) $this->arr_multi_add_shipping_price[$multiIdx] += (int) $addDeliveryCost;
			}

			// 배송지별 기본배송비
			$this->arr_multi_shop_shipping_price[$multiIdx] = $default_box_ea ? $shop_shipping_policy['price'] : 0;

		}

		// 조건부 무료배송 금액차감
		if($shipping[0][0]['deliveryCostPolicy'] == 'ifpay'){
			if($total_shop_goods_price && $shop_shipping_policy['free']){
				if($shop_shipping_policy['free'] <= $total_shop_goods_price){
					$free_cnt = floor($total_shop_goods_price / $shop_shipping_policy['free']);
					foreach($this->arr_multi_shop_shipping_price as $multiIdx => $v){
						if($free_cnt-- > 0){
							$this->arr_multi_shop_shipping_price[$multiIdx] = 0;
						}
					}
				}
			}
		}

		// 특정상품 구매시 무료
		$orderDeliveryFree = false;
		foreach($cart['data_goods'] as $goods_seq => $data_goods){
			if($shipping[0][0]['issueGoods'] && in_array($goods_seq,$shipping[0][0]['issueGoods'])){
				$orderDeliveryFree = true;
			}
			if($shipping[0][0]['issueCategoryCode'] && in_array($data_goods['r_category'],$shipping[0][0]['issueCategoryCode'])){
				$orderDeliveryFree = true;
			}
			if($shipping[0][0]['issueBrandCode'] && in_array($data_goods['r_brand'],$shipping[0][0]['issueBrandCode'])){
				$orderDeliveryFree = true;
			}

		}
		foreach($cart['data_goods'] as $goods_seq => $data_goods){
			if(in_array($goods_seq,$shipping[0][0]['exceptIssueGoods'])){
				$orderDeliveryFree = false;
			}
		}
		if( $shipping[0][0]['orderDeliveryFree'] == 'free' && $orderDeliveryFree){
			foreach($this->arr_multi_shop_shipping_price as $multiIdx => $v){
				if($free_cnt-- == 0){
					$this->arr_multi_shop_shipping_price[$multiIdx] = 0;
				}
			}
		}

		//프로모션코드 배송비할인
		$shipping_promotion_code_sale = $this->_get_shipping_promotion_code_sale(array_sum($this->arr_multi_shop_shipping_price, $cart['total']));
		if($shipping_promotion_code_sale){
			foreach($this->arr_multi_shop_shipping_price as $multiIdx=>$v){
				if($v>=$shipping_promotion_code_sale){
					$this->arr_multi_shipping_promition_code_sale[$multiIdx] = $shipping_promotion_code_sale;
					$this->arr_multi_shop_shipping_price[$multiIdx] -= $shipping_promotion_code_sale;
					break;
				}
			}
		}

		//배송비쿠폰 선택시 - 배송비 할인 본사배송상품만할인
		$shipping_coupon_sale = $this->_get_shipping_coupon_sale(array_sum($this->arr_multi_shop_shipping_price));
		if($shipping_coupon_sale){
			foreach($this->arr_multi_shop_shipping_price as $multiIdx=>$v){
				if($v>=$shipping_coupon_sale){
					$this->arr_multi_shipping_coupon_sale[$multiIdx] = $shipping_coupon_sale;
					$this->arr_multi_shop_shipping_price[$multiIdx] -= $shipping_coupon_sale;
					break;
				}
			}
		}

		$total_shop_shipping_price = array_sum($this->arr_multi_shop_shipping_price);
		$total_add_shipping_price = array_sum($this->arr_multi_add_shipping_price);
		$total_goods_shipping_price = array_sum($this->arr_multi_goods_shipping_price);

		/* 결제할 배송비 = 기본배송비 + 추가배송비 + 개별배송비 */
		if($total_goods_shipping_price) $this->arr_goods_shipping_ck = $total_goods_shipping_price;//개별배송비 안내문구추가
		$total_shipping_price = $total_shop_shipping_price + $total_add_shipping_price + $total_goods_shipping_price;

		return $total_shipping_price;
	}

	/* 복수배송지 설정 POST값을 토대로 $cart['list']와 유사한 형태의 데이터를 생성 */
	public function _get_multi_shipping_item_list(&$cart){

		$multiShippingItems = array();

		$_POST['multiCartGoodsSeq']		= array_values($_POST['multiCartGoodsSeq']);
		$_POST['multiCartOptionSeq']	= array_values($_POST['multiCartOptionSeq']);
		$_POST['multiCartSuboptionSeq']	= array_values($_POST['multiCartSuboptionSeq']);
		$_POST['multiEaInput']			= array_values($_POST['multiEaInput']);

		foreach($_POST['multiCartGoodsSeq'] as $multiIdx=>$goodsSeqs){

			foreach($goodsSeqs as $itemIdx=>$goodsSeq){

				// 카트에서 상품정보
				foreach($cart['list'] as $item){
					if($item['goods_seq']==$goodsSeq){

						if(!$multiShippingItems[$multiIdx][$goodsSeq]){
							$multiShippingItems[$multiIdx][$goodsSeq]  = $item;
							$multiShippingItems[$multiIdx][$goodsSeq]['ea'] = 0;
							$multiShippingItems[$multiIdx][$goodsSeq]['cart_options'] = array();
							$multiShippingItems[$multiIdx][$goodsSeq]['cart_suboptions'] = array();
						}

						if($item['cart_option_seq'] == $_POST['multiCartOptionSeq'][$multiIdx][$itemIdx] && !$_POST['multiCartSuboptionSeq'][$multiIdx][$itemIdx]){
							$item['ea'] = $_POST['multiEaInput'][$multiIdx][$itemIdx];
							if($item['ea']){
								$cart_options = $item;
								unset($cart_options['cart_suboptions']);
								$multiShippingItems[$multiIdx][$goodsSeq]['ea'] += $cart_options['ea'];
								$multiShippingItems[$multiIdx][$goodsSeq]['cart_options'][] = $cart_options;
							}
						}

						foreach($item['cart_suboptions'] as $cart_suboption){
							if($cart_suboption['cart_suboption_seq'] == $_POST['multiCartSuboptionSeq'][$multiIdx][$itemIdx]){
								$cart_suboption['ea'] = $_POST['multiEaInput'][$multiIdx][$itemIdx];
								if($cart_suboption['ea']){
									$multiShippingItems[$multiIdx][$goodsSeq]['ea'] += $cart_suboption['ea'];
									$multiShippingItems[$multiIdx][$goodsSeq]['cart_suboptions'][] = $cart_suboption;
								}
							}
						}

						if(!$multiShippingItems[$multiIdx][$goodsSeq]['ea']){
							unset($multiShippingItems[$multiIdx][$goodsSeq]);
						}else {
							// 배송지별 상품개별배송비
							$goods_shipping = $this->goodsmodel->get_goods_delivery($item,$multiShippingItems[$multiIdx][$goodsSeq]['ea']);
							$multiShippingItems[$multiIdx][$goodsSeq]['goods_shipping_policy'] = $goods_shipping;
							$multiShippingItems[$multiIdx][$goodsSeq]['goods_shipping'] = 0;
							if($goods_shipping['policy'] != 'shop'){
								$multiShippingItems[$multiIdx][$goodsSeq]['goods_shipping'] = $goods_shipping['price'];
							}

						}

					}

				}

			}

		}

		return $multiShippingItems;
	}

	// 각종 할인 할인 금액 계산, 배송배 계산 및 주문금액 계산
	public function calculate($adminOrder="", $person_seq=''){
		$this->load->helper('coupon');
		$this->load->model('cartmodel');
		$this->load->model('couponmodel');
		$this->load->model('ordermodel');
		$this->load->model('membermodel');

		// 기본값 정의
		$applypage						= 'order';
		$members						= "";
		$err_reserve					= "";
		$total_price					= 0;
		$total_reserve					= 0;
		$total_point					= 0;
		$goods_weight					= 0;
		$sum_goods_price				= 0;
		$total_coupon_sale				= 0;
		$total_fblike_sale				= 0;
		$total_mobile_sale				= 0;
		$total_goods_price				= 0;
		$total_member_sale				= 0;
		$total_real_sale_price			= 0;
		$international_shipping_price	= 0;

		if (!$person_seq && $this->input->post('person_seq')) $person_seq = $this->input->post('person_seq');

		$this->load->library('validation');
		
		$this->validation->set_data($this->input->post());
		$this->validation->set_rules('person_seq', '고유번호', 'trim|numeric|xss_clean');
		if ($this->validation->exec() === false) {
			show_error($this->validation->error_array['value'], 422);
		}

		// 모바일결제 : 체크값 오류시 callback으로 결제창 layer 숨김 처리
		$pg_cancel_script				= ($_POST['mobilenew'] == "y") ? $this->pg_cancel_script() : '';

		// 관리자 주문 예외처리 값
		if(!$adminOrder)	$adminOrder	= ($_GET["adminOrder"]) ? $_GET["adminOrder"] : '';
		if(!$person_seq)	$person_seq	= ($_GET["person_seq"]) ? $_GET["person_seq"] : '';
		$adminOrderType					= ($_GET['adminOrderType'] == 'person') ? 'person' : '';

		$cfg['order'] = ($this->cfg_order) ? $this->cfg_order : config_load('order');
		$cfg_reserve					= ($this->reserves)?$this->reserves:config_load('reserve');
		$pg								= config_load($this->config_system['pgCompany']);
		$shipping						= use_shipping_method();
		$this->shipping_order							= $shipping;

		// 입점사별 상품구매금액 합계
		$this->provider_sum_goods_price					= array();
		// 입점사별 상품 무게 합계
		$this->provider_goods_weight					= array();
		// 입점사별 해외배송비 합계
		$this->provider_international_shipping_price	= array();
		// 입점사별 기본배송비
		$this->provider_shipping_cost					= array();

		if	(is_array($shipping) )
			$international_shipping	= $shipping[1][$_POST['shipping_method_international']];

		// 회원정보 추출
		if		($adminOrder == "admin" && $_GET['member_seq'] && $this->displaymode = 'coupon'){
			$members	= $this->membermodel->get_member_data($_GET['member_seq']);
		}elseif	($adminOrder == "admin" && $_POST['member_seq']){
			$members	= $this->membermodel->get_member_data($_POST['member_seq']);
		}elseif	($this->userInfo['member_seq']){
			$members	= $this->membermodel->get_member_data($this->userInfo['member_seq']);
		}
		$member_seq		= $members['member_seq'];
		$member_group	= $members['group_seq'];
		$total_emoney	= $members['emoney'];
		$total_cash		= $members['cash'];

		// 마일리지 전체 사용일때 회원 총 마일리지 불러오기
		if($_POST['emoney_all'] == "y"){
			$members['emoney']	= $this->membermodel->get_emoney($members['member_seq']);
			$_POST['emoney']	= $members['emoney'];
		}

		// 장바구니 정보 추출
		if		($adminOrder == 'admin' && $adminOrderType == 'person'){
			$this->load->model('personcartmodel');
			$cart	= $this->personcartmodel->catalog($member_seq);
		}elseif	($person_seq > 0){
			$this->load->model('personcartmodel');
			$cart	= $this->personcartmodel->catalog($this->userInfo['member_seq'], $person_seq);
			if	($cart['person']['use_reserve']){
				$this->person_use_reserve				= 1;
				$cfg_reserve['default_reserve_limit']	= $cart['person']['reserve_limit'];
			}
		}else{
			$this->load->model('cartmodel');
			$cart	= $this->cartmodel->catalog($adminOrder);
		}
		$this->cart	= $cart;

		/* 구매 시 마일리지액 제한 조건 추가 leewh 2014-06-25 */
		if ($cfg_reserve['default_reserve_limit']>=2){
			if (isset($_POST['appointed_reserve'])) {
				$appointed_reserve = $_POST['appointed_reserve'];
			}

			if ($cfg_reserve['default_reserve_limit']==3) {
				unset($cal_total_real_sale_price);
				if (isset($_POST['total_real_sale_price'])) {
					$cal_total_real_sale_price = $_POST['total_real_sale_price'];
				}

				$tot_using_reserve = 0; // 상품 사용마일리지

				if ($_POST['emoney'] > 0) {
					// 총 사용 마일리지 재정의 총 상품실결제금액보다 총 결제금액이 클 경우
					if ($cal_total_real_sale_price < $_POST['emoney']) {
						$tot_using_reserve = $cal_total_real_sale_price;
					} else {
						$tot_using_reserve = $_POST['emoney'];
					}
				}
			}
		}

		$is_international_shipping = false; // 해외배송상품
		/**** 재고 체크 및 최대/최소 구매수량 체크 ****/
		foreach($cart['data_goods'] as $goods_seq => $data){
			if($data['option_international_shipping_status'] == 'y'){
				$is_international_shipping = true; // 해외배송상품
			}

			//배송비쿠폰 개별배송상품 체크
			if($data['shipping_policy'] == 'shop'){
				$this->arr_shop_shipping_cnt++;
			}else{
				$this->arr_goods_shipping_price++;
			}

			if($data['ea_for_option'])foreach($data['ea_for_option'] as $option_key => $option_ea){
				$option_r = explode(' ^^ ',$option_key);
				// 재고 체크
				$chk = check_stock_option(
					$goods_seq,
					$option_r[0],
					$option_r[1],
					$option_r[2],
					$option_r[3],
					$option_r[4],
					$option_ea,
					$cfg['order'],
					'view_stock'
				);
			}

			if($data['ea_for_suboption']) foreach($data['ea_for_suboption'] as $option_key => $option_ea){
				$option_r = explode(' ^^ ',$option_key);
				// 재고 체크
				$chk = check_stock_suboption(
					$goods_seq,
					$option_r[0],
					$option_r[1],
					$option_ea,
					$cfg['order'],
					'view_stock'
				);
			}
		}

		/* **************************************************** */

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

		//----> sale library 적용
		$cart['total']					= $cart['total_sale_price'];
		$param['cal_type']				= 'list';
		$param['total_price']			= $cart['total_sale_price'];
		$param['reserve_cfg']			= $cfg_reserve;
		$param['tot_use_emoney']		= $tot_using_reserve;
		$param['member_seq']			= $member_seq;
		$param['group_seq']				= $member_group;
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		foreach($cart['list'] as $key => $data) {

			// 초기값
			$category				= ($data['r_category']) ? $data['r_category'] : array();
			$data['ori_price']		= $data['price'];
			$cart_suboptions		= $data['cart_suboptions'];
			$cart_inputs			= $data['cart_inputs'];
			$coupon_download_seq	= $_POST['coupon_download'][$data['cart_seq']][$data['cart_option_seq']];

			//----> sale library 적용
			unset($param, $sales, $optsalelist);
			$param['option_type']					= 'option';
			$param['consumer_price']				= $data['consumer_price'];
			$param['price']							= $data['org_price'];
			$param['sale_price']					= $data['price'];
			$param['ea']							= $data['ea'];
			$param['goods_ea']						= $cart['data_goods'][$data['goods_seq']]['ea'];
			$param['option_ea']						= $cart['data_goods'][$data['goods_seq']]['option_ea'];
			$param['category_code']					= $category;
			$param['goods_seq']						= $data['goods_seq'];
			$param['goods']							= $data;
			if	($coupon_download_seq)
				$param['coupon_download_seq']		= $coupon_download_seq;
			$this->sale->set_init($param);
			$sales	= $this->sale->calculate_sale_price($applypage);
			//이벤트 쿠폰/코드 사용제한2 @2015-08-13
			if	( $this->sale->goods['event'] ) {
				if( $this->sale->goods['event']['use_coupon'] == 'n' && !in_array($data['goods_seq'],$this->ordernosales_cp) ) {
					$this->ordernosales_cp[$data['goods_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_coupon_shipping'] == 'n' && !in_array($data['shipping']['provider_seq'],$this->ordernosales_cp_sh)  ) {
					$this->ordernosales_cp_sh[$data['shipping']['provider_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_coupon_ordersheet'] == 'n' && !in_array($data['goods_seq'],$this->ordernosales_cp_os)  ) {
					$this->ordernosales_cp_os[$data['goods_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_code'] == 'n' && !in_array($data['goods_seq'],$this->ordernosales_cd)  ) {
					$this->ordernosales_cd[$data['goods_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_code_shipping'] == 'n' && !in_array($data['shipping']['provider_seq'],$this->ordernosales_cd_sh)  ) {
					$this->ordernosales_cd_sh[$data['shipping']['provider_seq']] = $this->sale->goods['event']['event_seq'];
				}
			}

			// 기본 정보
			$data['org_price']							= ($data['consumer_price']) ? $data['consumer_price'] : $data['price'];
			$opt_price									= $sales['one_result_price'];
			$data['sale_price']							= $sales['one_result_price'];
			if	(!$param['sale_price']){
				$data['basic_sale']						= $sales['one_sale_list']['basic'];
				$data['event_sale_target']				= ($sales['target_list']['event'] == 1) ? 'consumer_price' : 'price';
				$data['event_sale']						= $sales['one_sale_list']['event'];
				$data['multi_sale']						= $sales['one_sale_list']['multi'];
				$data['event_reserve']					= $sales['one_reserve_list']['event'];
				$data['event_point']					= $sales['one_point_list']['event'];
			}

			// 쿠폰할인 정보
			$data['coupon_sale']						= $sales['sale_list']['coupon'];
			$data['download_seq']						= $coupon_download_seq;
			if	($coupon_download_seq){
				$coupon_same_time_n						= $this->sale->coupon_same_time_n;
				$coupon_same_time_n_duplication_n		= $this->sale->coupon_duplication_n;
				$coupon_same_time_y						= $this->sale->coupon_same_time_y;
				$coupon_sale_payment_b					= $this->sale->coupon_sale_payment_b;
				$coupon_sale_agent_m					= $this->sale->coupon_sale_agent_m;
			}

			// 쿠폰 사용 팝업에서 전체 쿠폰 추출
			if	( $members && $person_seq == "" && $this->displaymode == 'coupon'){
				if( !$this->ordernosales_cp[$data['goods_seq']] ) {//이벤트 쿠폰 사용제한 @2015-08-13
					$coupons			= $this->couponmodel->get_able_use_list($members['member_seq'],$data['goods_seq'],$category, $cart['total'], $data['price'], $data['ea']);
					$data['coupons']	= $coupons;
				}
			}else{
				$data['coupons']						= $this->sale->couponSales;
			}

			// 회원할인 정보
			$member_sale								+= $data['member_sale'];
			$data['member_sale_unit']					= $sales['one_sale_list']['member'];
			$data['member_sale']						= $sales['sale_list']['member'];

			// 코드할인 정보
			$data['promotion_code_seq']					= $this->sale->code_seq;
			$data['promotion_code_sale']				= $sales['sale_list']['code'];
			$data['promotion']['salescost_admin']		= $this->sale->code_salescost['admin'];
			$data['promotion']['salescost_provider']	= $this->sale->code_salescost['provider'];
			$data['promotion']['provider_list']			= $this->sale->code_salescost['list'];

			// 좋아요 할인 정보
			$data['fblike_sale_unit']					= $sales['one_sale_list']['like'];
			$data['fblike_sale']						= $sales['sale_list']['like'];

			// 모바일할인 정보
			$data['mobile_sale_unit']					= $sales['one_sale_list']['mobile'];
			$data['mobile_sale']						= $sales['sale_list']['mobile'];

			// 유입경로 할인 정보
			$data['referersale_seq']					= $this->sale->referer_seq;
			$data['referer_sale']						= $sales['sale_list']['referer'];
			$data['referersale']['salescost_provider']	= $this->sale->referer_salecode['provider'];
			$data['referersale']['provider_list']		= $this->sale->referer_salecode['list'];


			// sale_price가 없을 시 여기서 event까지 계산함
			if	(!$data['event'] && $this->sale->cfgs['event']){
				$data['event']					= $this->sale->cfgs['event'];
				$data['eventEnd']				= $sales['eventEnd'];
			}

			// 마일리지 / 포인트 ( 실결제 금액 기준이기에 여기서 계산 )
			// 이벤트 마일리지 / 포인트 계산 ( 장바구니에서 혜택적용할인가를 받아서 쓸 경우만 )
			if	($param['sale_price']){
				$this->sale->cfgs['event']		= $data['event'];
				$data['event_reserve']			= $this->sale->event_sale_reserve($sales['one_result_price']);
				$data['event_point']			= $this->sale->event_sale_point($sales['one_result_price']);
			}
			$data['member_reserve']						= $sales['one_reserve_list']['member'];
			$data['member_point']						= $sales['one_point_list']['member'];
			$data['fb_reserve']							= $sales['one_reserve_list']['like'];
			$data['fb_point']							= $sales['one_point_list']['like'];
			$data['mobile_reserve']						= $sales['one_reserve_list']['mobile'];
			$data['mobile_point']						= $sales['one_point_list']['mobile'];

			$total_real_sale_price						+= $sales['result_price'];
			$data['tot_org_price']						= $data['org_price'] * $data['ea'];
			$data['tot_sale_price']						= $sales['total_sale_price'];
			$data['tot_result_price']					= $sales['result_price'];
			$cart['total_sale']							+= $sales['total_sale_price'];
			if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $tmptitle){
				$optsalelist[$sale_type]						= $sales['sale_list'][$sale_type];
				$moptsalelist[$sale_type]						= $sales['sale_list'][$sale_type];
				$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
				$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
			}

			$this->sale->reset_init();
			//<---- sale library 적용


			// 구매적립(마일리지 제한 조건 설정에 따른 분기)
			$reserve_policy_log = '';
			$new_opt_price = 0; // 마일리지 계산용 변수
			if ($cfg_reserve['default_reserve_limit']==3 && $_POST['emoney'] > 0) {

				/* 적립 제한 조건 B설정 추가 leewh 2014-07-04 */
				$each_using_reserve = 0;

				// 필수 옵션 1개 사용마일리지 계산
				$each_using_reserve = $this->goodsmodel->get_reserve_standard_pay($opt_price, $data['ea'], $cal_total_real_sale_price, $tot_using_reserve);

				$new_opt_price = $opt_price - $each_using_reserve;
				$reserve_policy_log	.= sprintf('[제한조건B (실결제금액-사용마일리지 : %s)]', number_format($new_opt_price));
			} else {
				// 마일리지 계산용 가격 분리 leewh 2014-07-09
				$new_opt_price = $opt_price;
			}

			// 포인트
			$data['point']		= $this->goodsmodel->get_point_with_policy($opt_price);
			$data['reserve']	= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'], $new_opt_price, $cfg_reserve['default_reserve_percent'], $data['reserve_rate'], $data['reserve_unit'], $data['reserve']);

			// 마일리지 설정 B일때 마일리지을 사용한 경우 상품마일리지 won(원) 단위 환산하여 지급 2015-04-02
			if ($cfg_reserve['default_reserve_limit'] == 3 && $_POST['emoney'] > 0) {
				if ($new_opt_price < 1) { // 결제금액 0원 일경우 마일리지 0원 처리
					$data['reserve'] = 0;
				} else if($data['reserve_unit'] == 'won') {
					$data['reserve'] = (int) (($data['reserve']/$data['price'])*$new_opt_price);
				}
			}

			// 비회원 마일리지/포인트 제거
			if	(!($member_seq > 0)){
				$data['fb_reserve']			= 0;
				$data['fb_point']			= 0;
				$data['mobile_reserve']		= 0;
				$data['mobile_point']		= 0;
				$data['member_point']		= 0;
				$data['member_reserve']		= 0;
				$data['point_one']			= 0;
				$data['reserve_one']		= 0;
				$data['point']				= 0;
				$data['reserve']			= 0;
			}

			// 마일리지,포인트 로그
			$log = '';
			if	( $reserve_policy_log )	$log	.= $reserve_policy_log;
			if( $data['reserve'] > 0 ) $log .= ($log?' / ':'').'구매  : '.(is_numeric($data['reserve'])?number_format($data['reserve']):$data['reserve']);
			if( $data['event_reserve'] > 0 ) $log .= ($log?' / ':'').'이벤트  : '.(is_numeric($data['event_reserve'])?number_format($data['event_reserve']):$data['event_reserve']);
			if( $data['member_reserve'] > 0 ) $log .= ($log?' / ':'').'회원  : '.(is_numeric($data['member_reserve'])?number_format($data['member_reserve']):$data['member_reserve']);
			if( $data['fb_reserve'] > 0 ) $log .= ($log?' / ':'').'좋아요  : '.(is_numeric($data['fb_reserve'])?number_format($data['fb_reserve']):$data['fb_reserve']);
			if( $data['mobile_reserve'] > 0 ) $log .= ($log?' / ':'').'모바일  : '.(is_numeric($data['mobile_reserve'])?number_format($data['mobile_reserve']):$data['mobile_reserve']);
			$data['reserve_log'] = $log;
			$log = '';
			if( $data['point'] > 0 ) $log .= ($log?' / ':'').'구매  : '.(is_numeric($data['point'])?number_format($data['point']):$data['point']);
			if( $data['event_point'] > 0 ) $log .= ($log?' / ':'').'이벤트  : '.(is_numeric($data['event_point'])?number_format($data['event_point']):$data['event_point']);
			if( $data['member_point'] > 0 ) $log .= ($log?' / ':'').'회원  : '.(is_numeric($data['member_point'])?number_format($data['member_point']):$data['member_point']);
			if( $data['fb_point'] > 0 ) $log .= ($log?' / ':'').'좋아요  : '.(is_numeric($data['fb_point'])?number_format($data['fb_point']):$data['fb_point']);
			if( $data['mobile_point'] > 0 ) $log .= ($log?' / ':'').'모바일  : '.(is_numeric($data['mobile_point'])?number_format($data['mobile_point']):$data['mobile_point']);
			$data['point_log'] = $log;

			// 옵션의 마일리지 포인트
			$data['reserve_one']	= (int) $data['reserve'] + (int) $data['event_reserve'] + (int) $data['member_reserve'] + (int) $data['fb_reserve'] + (int) $data['mobile_reserve'];
			$data['point_one']		= (int) $data['point'] +  (int) $data['event_point'] + (int) $data['member_point'] + (int) $data['fb_point'] + (int) $data['mobile_point'];

			/* 구매 시 마일리지액 제한 조건 추가 leewh 2014-06-25 */
			$reserve_policy_log = '';
			if ($cfg_reserve['default_reserve_limit']==1 && $_POST['emoney'] > 0) {
				$data['reserve_one'] = 0;
				$reserve_policy_log	.= '[제한조건D 지급 마일리지 : 0]';
			} else if ($cfg_reserve['default_reserve_limit']==2 && $_POST['emoney'] > 0) {
				$minus_reserve = 0;
				$reserve_subtract = $appointed_reserve - $_POST['emoney'];

				if ($reserve_subtract > 0) {
					/* 필수 옵션 차감할 1개 사용 마일리지 계산 */
					$minus_reserve = $this->goodsmodel->get_reserve_limit($data['reserve_one']*$data['ea'], $data['ea'], $appointed_reserve, $_POST['emoney']);
					$data['reserve_one'] = $data['reserve_one'] - $minus_reserve;
				} else {
					$minus_reserve = $_POST['emoney'];
					$data['reserve_one'] = 0;  //전액 사용으로 지급안함.
				}
				$reserve_policy_log .= sprintf("[제한조건C 지급 마일리지 : %s]", number_format($data['reserve_one']));
			}

			// 마일리지 정책 A 가 아닐경우 정책명을 제일 앞에 표시
			if ($data['reserve_log'] && $reserve_policy_log) $data['reserve_log'] = $reserve_policy_log." / ".$data['reserve_log'];

			$data['tot_reserve']						= $data['reserve_one'] * $data['ea'];
			$data['tot_point']							= $data['point_one'] * $data['ea'];
			$data['option_suboption_price_sum']			= $data['sale_price'] * $data['ea'];
			$data['option_suboption_price_sum_origin']	= $data['price'] * $data['ea'];

			// 추가구성옵션 계산
			if($data['cart_suboptions']){
				foreach($data['cart_suboptions'] as $k => $cart_suboption){

					//----> sale library 적용
					unset($param, $sales, $subsalelist);
					$param['option_type']			= 'suboption';
					$param['sub_sale']				= $cart_suboption['sub_sale'];
					$param['consumer_price']		= $cart_suboption['consumer_price'];
					$param['price']					= $cart_suboption['price'];
					$param['sale_price']			= $cart_suboption['price'];
					$param['ea']					= $cart_suboption['ea'];
					$param['category_code']			= $category;
					$param['goods_seq']				= $data['goods_seq'];
					$param['goods']					= $data;
					$this->sale->set_init($param);
					$sales	= $this->sale->calculate_sale_price($applypage);

					$cart_suboption['org_price']				= ($cart_suboption['consumer_price']) ? $cart_suboption['consumer_price'] : $cart_suboption['price'];
					if	(!$param['sale_price']){
						$cart_suboption['basic_sale']			= $sales['one_sale_list']['basic'];
						$cart_suboption['event_sale_target']	= ($sales['target_list']['event'] == 1) ? 'consumer_price' : 'price';
						$cart_suboption['event_sale']			= $sales['one_sale_list']['event'];
						$cart_suboption['multi_sale']			= $sales['one_sale_list']['multi'];
					}

					$cart_suboption['member_sale_unit']	= $sales['one_sale_list']['member'];
					$cart_suboption['member_sale']		= $sales['sale_list']['member'];
					$member_sale						+= $cart_suboption['member_sale'];
					$cart_suboption['member_reserve']	= $sales['one_reserve_list']['member'];
					$cart_suboption['member_point']		= $sales['one_point_list']['member'];
					$sale_suboption_price				= $sales['one_result_price'];
					$cart_suboption['sale_price']		= $sales['one_result_price'];
					$total_sale_suboption				+= $cart_suboption['member_sale'];
					$subsaletotalprice					= $sales['total_sale_price'];
					$data['tot_org_price']				+= $cart_suboption['org_price'] * $cart_suboption['ea'];
					$data['tot_sale_price']				+= $sales['total_sale_price'];
					$data['tot_result_price']			+= $sales['result_price'];
					$cart['total_sale']					+= $sales['total_sale_price'];

					if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $tmptitle){
						$subsalelist[$sale_type]						= $sales['sale_list'][$sale_type];
						$moptsalelist[$sale_type]						+= $sales['sale_list'][$sale_type];
						$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
						$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
					}
					$this->sale->reset_init();
					//<---- sale library 적용

					/* $cart_suboption['reserve'] 초기 값이 구매수량이 곱해진 총마일리지가 전달됨.
					추가옵션 상품 1개 기준으로 상품 마일리지 재계산 2015-03-27 leewh */
					$cart_suboption['reserve']	= (int) $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'],$sale_suboption_price,$cfg_reserve['default_reserve_percent'],$cart_suboption['reserve_rate'],$cart_suboption['reserve_unit'],$cart_suboption['reserve']);

					// 구매마일리지(마일리지 제한 조건 설정에 따른 분기)
					$reserve_policy_log = '';
					$new_sale_suboption_price = 0; //추가옵션 마일리지 계산용 변수
					if ($cfg_reserve['default_reserve_limit']==3 && $_POST['emoney'] > 0) {

						/* 적립 제한 조건 B설정 추가 leewh 2014-07-04 */
						$each_sub_using_reserve = 0;

						// 서브옵션 1개 사용마일리지 계산
						$each_sub_using_reserve = $this->goodsmodel->get_reserve_standard_pay($sale_suboption_price, $cart_suboption['ea'], $cal_total_real_sale_price, $tot_using_reserve);

						$new_sale_suboption_price = $sale_suboption_price - $each_sub_using_reserve;
						$reserve_policy_log .= sprintf('[제한조건B (실결제금액-사용마일리지 : %s)]', number_format($new_sale_suboption_price));
					} else {
						// 마일리지 계산용 가격 분리 leewh 2014-07-09
						$new_sale_suboption_price = $sale_suboption_price;
					}

					// 서브옵션 마일리지 및 포인트
					$cart_suboption['point']	= (int) $this->goodsmodel->get_point_with_policy($sale_suboption_price);
					$cart_suboption['reserve']	= (int) $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'], $new_sale_suboption_price, $cfg_reserve['default_reserve_percent'], $cart_suboption['reserve_rate'], $cart_suboption['reserve_unit'], $cart_suboption['reserve']);

					// 마일리지 설정 B일때 마일리지을 사용한 경우 상품마일리지 won(원) 단위 환산하여 지급 2015-04-02
					if ($cfg_reserve['default_reserve_limit'] == 3 && $_POST['emoney'] > 0) {
						if	($new_sale_suboption_price < 1) { // 결제금액 0원 일경우 마일리지 0원 처리
							$cart_suboption['reserve']	= 0;
						} else if($cart_suboption['reserve_unit'] == "won") {
							$cart_suboption['reserve'] = (int) (($cart_suboption['reserve'] / $cart_suboption['price']) * $new_sale_suboption_price);
						}
					}

					// 비회원 마일리지/포인트 제거
					if	(!($member_seq > 0)){
						$cart_suboption['member_point']		= 0;
						$cart_suboption['member_reserve']	= 0;
						$cart_suboption['point_one']		= 0;
						$cart_suboption['reserve_one']		= 0;
						$cart_suboption['point']			= 0;
						$cart_suboption['reserve']			= 0;
					}

					// 추가옵션 마일리지, 포인트 로그
					$log = '';
					if ($reserve_policy_log)	$log .= $reserve_policy_log;
					if ($cart_suboption['reserve'] > 0)	$log .= sprintf("%s구매 : %s", ($log?' / ':''), number_format($cart_suboption['reserve']));
					if ($cart_suboption['member_reserve'] > 0) $log .= sprintf("%s회원 : %s", ($log?' / ':''), number_format($cart_suboption['member_reserve']));
					$cart_suboption['reserve_log'] = $log;

					$log = '';
					if ($cart_suboption['point'] > 0)	$log .= sprintf("%s구매 : %s", ($log?' / ':''), number_format($cart_suboption['point']));
					if ($cart_suboption['member_point'] > 0) $log .= sprintf("%s회원 : %s", ($log?' / ':''), number_format($cart_suboption['member_point']));
					$cart_suboption['point_log'] = $log;

					// 서브 옵션용 마일리지, 포인트 개별 합계 2015-03-27
					$cart_suboption['reserve_one']	= (int) $cart_suboption['reserve'] + (int) $cart_suboption['member_reserve'];
					$cart_suboption['point_one']		= (int) $cart_suboption['point'] + (int) $cart_suboption['member_point'];

					/* 구매 시 마일리지액 제한 조건 추가 leewh 2014-06-25 */
					$reserve_policy_log = '';
					if ($cfg_reserve['default_reserve_limit']==1 && $_POST['emoney'] > 0) {
						$cart_suboption['reserve_one'] = 0;
						$reserve_policy_log	.= '[제한조건D 지급 마일리지 : 0]';
					} else if ($cfg_reserve['default_reserve_limit']==2 && $_POST['emoney'] > 0) {
						$minus_sub_reserve = 0;
						$reserve_sub_subtract = $appointed_reserve - $_POST['emoney'];

						if ($reserve_sub_subtract > 0) {
							/* 서브옵션 차감할 1개 사용 마일리지 계산 */
							$tmp_tot_reserve = $cart_suboption['reserve_one'] * $cart_suboption['ea'];
							$minus_sub_reserve = $this->goodsmodel->get_reserve_limit($tmp_tot_reserve, $cart_suboption['ea'], $appointed_reserve, $_POST['emoney']);
							$cart_suboption['reserve_one'] = $cart_suboption['reserve_one'] - $minus_sub_reserve;
						} else {
							$minus_sub_reserve = $_POST['emoney'];
							$cart_suboption['reserve_one'] = 0; //전액 사용으로 지급안함.
						}
						$reserve_policy_log .= sprintf("[제한조건C 지급 마일리지 : %s]", number_format($cart_suboption['reserve_one']));
					}

					// 마일리지 정책 A 가 아닐경우 정책명을 제일 앞에 표시
					if ($cart_suboption['reserve_log'] && $reserve_policy_log) $cart_suboption['reserve_log'] = $reserve_policy_log." / ".$cart_suboption['reserve_log'];

					$cart_suboption['reserve']	= $cart_suboption['reserve_one']*$cart_suboption['ea'];
					$total_reserve				+= $cart_suboption['reserve'];
					$cart_suboption['point']	= $cart_suboption['point_one'] * $cart_suboption['ea'];
					$total_point				+= $cart_suboption['point'];
					$total_real_sale_price		+= $cart_suboption['sale_price'] * $cart_suboption['ea'];

					$data['cart_suboptions'][$k] = $cart_suboption;
					$data['option_suboption_price_sum'] += $cart_suboption['sale_price']*$cart_suboption['ea'];
					$data['option_suboption_price_sum_origin'] += $cart_suboption['price']*$cart_suboption['ea'];
				}
			}

			$data['cart_sale'] = $data['member_sale']+$data['mobile_sale'] + $data['fblike_sale'] + $data['promotion_code_sale'] + $data['coupon_sale'] + $data['referer_sale'];

			// 상품 무게 계산
			if( $data['shipping_weight_policy'] == "shop" ){
				$goods_weight = $data['goods_weight'] + $international_shipping['defaultGoodsWeight'];
			}else{
				$goods_weight = $data['goods_weight'];
			}

			//$data['goods_weight']	= $goods_weight * $data['ea'];
			$data['goods_weight']	= $goods_weight;
			$cart['list'][$key] = $data;
			// 입점사별 상품 무게 합산
			$this->provider_goods_weight[$data['provider_seq']] += $data['goods_weight'];

			$total_sales_price	+= (int) $data['tot_sale_price'];
			$total_mobile_sale += (int) $data['mobile_sale'];
			$total_fblike_sale += (int) $data['fblike_sale'];
			$total_promotion_code_sale += (int) $data['promotion_code_sale'];
			$total_coupon_sale += (int) $data['coupon_sale'];
			$total_member_sale += (int) $data['member_sale'];
			$total_referer_sale += (int) $data['referer_sale'];
			$total_reserve += $data['tot_reserve'];
			$total_point += $data['tot_point'];
			$total_sale_price += $data['cart_sale'];
			$total_goods_weight += $data['goods_weight'];

			$provider_cart[$data['provider_seq']]['provider_price']	+= $data['tot_price'];
			$provider_cart[$data['provider_seq']]['cart_list'][]	= $data;

			// 배송그룹별 상품할인가 합
			$shipping_group_sum_goods_price[$data['shipping_group']] += $data['tot_price'] - $data['cart_sale'];
		}

		$total_sale_price += $total_sale_suboption;
		$cart['total_mobile_sale'] = $total_mobile_sale;
		$cart['total_fblike_sale'] = $total_fblike_sale;
		$cart['total_promotion_code_sale'] = $total_promotion_code_sale;
		$cart['total_coupon_sale'] = $total_coupon_sale;
		$cart['total_member_sale'] = $total_member_sale;
		$cart['total_referer_sale'] = $total_referer_sale;
		$cart['total_reserve'] = $total_reserve;
		$cart['total_real_sale_price'] = $total_real_sale_price; //총실결제금액합계@2014-07-04
		$cart['total_point'] = $total_point;
		$cart['total_sale_price'] = $total_sale_price;
		$cart['total_goods_weight'] = $total_goods_weight;

		// 할인적용가 기준 배송비 계산
		$total_goods_price = $cart['total'] - $cart['total_sale_price'];
		if($cart['shop_shipping_policy']['free']){
			$cart['shipping_price']['shop'] = (int) $cart['shop_shipping_policy']['price'];
			if($cart['shop_shipping_policy']['free'] <= $total_goods_price){
				$cart['shipping_price']['shop'] = 0;
			}
		}

		/* 배송비 */
		$this->shipping_cost = 0;//기본배송비체크

		// 상품별 주문배송방법 선택
		$total_shipping_price = 0;
		unset($cart['provider_shipping_price']);//cartmodel->cart_list() 정의되어 있어서 재정의 @2017-01-10
		foreach($cart['shipping_group_policy'] as $shipping_group=>$row){

			## 그룹별 배송비 재계산 :: 2015-01-29 lwh
			if($row['policy'] == 'shop'){
				if($shipping_group_sum_goods_price[$shipping_group] && $row['free'] && $row['free'] <= $shipping_group_sum_goods_price[$shipping_group])
						$re_del_price = "무료배송";
				else	$re_del_price = number_format($row['price']);
				if	(!$row['price'])	$re_del_price = "무료배송";

			}

			$addDeliveryCost = 0;
			$addDeliveryType = config_load('adddelivery', 'addDeliveryType');


			//추가 배송비 기준에 따라 추가배송비 계산
			if($_POST["recipient_address_street"] && $addDeliveryType["addDeliveryType"] == "street"){

				if($row["sigungu_street"]){

					asort($row["sigungu_street"]);
					$recipientAddressStreet		= array();
					$recipientAddressStreet		= explode(" ", trim($_POST["recipient_address_street"]));

					foreach($row["sigungu_street"] as $sigungu_key => $sigungu_street){
						$streetSigungu		= array();
						$address_diff		= array();
						$streetSigungu		= explode(" ", trim($sigungu_street));
						$compare_cnt		= count($streetSigungu);
						$address_compare	= array_intersect($streetSigungu, $recipientAddressStreet);

						if($compare_cnt == count($address_compare)){
							$addDeliveryCost	= $row['addDeliveryCost'][$sigungu_key];
						}else if($compare_cnt > 2 && count($address_compare) == 2 && preg_match('/'.$streetSigungu[2].'/',$recipientAddressStreet[2])){
							$addDeliveryCost	= $row['addDeliveryCost'][$sigungu_key];
						}
					}
				}
			}else{
				if($row['sigungu']){
					foreach($row['sigungu'] as $sigungu_key => $sigungu){
						if(preg_match('/'.$sigungu.'/',$_POST['recipient_address'])){
							if( $addDeliveryCost < $row['addDeliveryCost'][$sigungu_key] ){
								$addDeliveryCost	= $row['addDeliveryCost'][$sigungu_key];
								if( (preg_match('/postpaid/',$shipping_group) || preg_match('/delivery/',$shipping_group)) ){
									$row['add_delivery_area'] = $sigungu;
								}
							}
						}
					}
				}
			}

			if( (preg_match('/postpaid/',$shipping_group) || preg_match('/delivery/',$shipping_group)) && $addDeliveryCost > 0 ){
				$row['add_delivery_cost'] = $addDeliveryCost;
				if($row['policy'] == 'goods'){
					$row['add_delivery_cost'] = $addDeliveryCost * $row['box_ea'];
				}
			}

			if( preg_match('/delivery/',$shipping_group) ){

				if($row['policy'] == 'shop'){
					if($shipping_group_sum_goods_price[$shipping_group] && $row['free'] && $row['free'] <= $shipping_group_sum_goods_price[$shipping_group]){
						$shipping_price['shop'] += 0;
						$shipping_group_price[$shipping_group]['shop'] += 0;
						$cart['provider_shipping_price'][$row['provider_seq']]['shop']	+= 0;
						$row['price'] = 0;
					}else{
						$shipping_price['shop'] += $row['policy']=='shop' ? $row['price'] : 0;
						$shipping_group_price[$shipping_group]['shop'] += $row['policy']=='shop' ? $row['price'] : 0;
						$cart['provider_shipping_price'][$row['provider_seq']]['shop']	+=$row['policy']=='shop' ? $row['price'] : 0;
						$total_shipping_price	+= $row['price'];
						$total_shipping_shop	+= $row['price'];
					}

					$shipping_group_price[$shipping_group]['shop'] += $addDeliveryCost;
					$cart['provider_shipping_price'][$row['provider_seq']]['shop']	+= $addDeliveryCost;
					$total_shipping_price	+= $addDeliveryCost;
					$total_shipping_add		+= $addDeliveryCost;
				}

				if($row['policy'] == 'goods'){
					$shipping_group_price[$shipping_group]['goods'] += $row['price'] + ($addDeliveryCost*$row['box_ea']);
					$cart['provider_shipping_price'][$row['provider_seq']]['goods']	+= $addDeliveryCost;
					$total_goods_shipping_price	+= $shipping_group_price[$shipping_group]['goods'];
					$tottal_shipping_goods		+= $row['price'];
					$total_shipping_add			+= $addDeliveryCost;
				}
			}

			$shipping_group_policy[$shipping_group] = $row;
		}

		$this->shipping_group_cost = $shipping_group_price;
		$this->shipping_group_policy = $shipping_group_policy;
		$this->shipping_cost = $total_shipping_price;
		$total_shipping_price += (int) $total_goods_shipping_price;
		foreach($cart['provider_shipping_price'] as $provider_seq=>$data){
			$this->provider_shipping_cost[$provider_seq] = (int) $data['shop'];
		}

		//프로모션코드 배송비할인2
		if($this->session->userdata('cart_promotioncode_'.session_id())) {
			$shipping_promotions = $this->promotionmodel->get_able_download_saleprice($this->session->userdata('cart_promotioncodeseq_'.session_id()),$this->session->userdata('cart_promotioncode_'.session_id()), $cart['total'], '','');
		}

		//프로모션코드 본사배송상품 배송비할인
		$this->shipping_promotion_code_sale	= array();
		if($total_shipping_price > 0 && $shipping_promotions) {//본사배송상품
			foreach($this->provider_shipping_cost as $provider_seq => $shipping_cost){

				//이벤트 배송비코드 사용제한 @2015-08-13
				if( $this->ordernosales_cd_sh[$provider_seq] ) continue;

				$codesales_use		= 'N';
				$shippingcode_sale	= 0;
				if		(!$shipping_promotions['provider_list'] > 0 && $provider_seq == 1)
					$codesales_use	= 'A';
				elseif	($shipping_promotions['provider_list'] && strstr($shipping_promotions['provider_list'], '|'.$provider_seq.'|'))
					$codesales_use	= 'P';

				if		($codesales_use == 'A' || $codesales_use == 'P'){
					if($shipping_promotions['sale_type'] == 'shipping_free' &&  $shipping_cost > 0)		{
						$shippingcode_sale	= ($shipping_cost < $shipping_promotions['promotioncode_shipping_sale_max'])	? $shipping_cost : $shipping_promotions['promotioncode_shipping_sale_max'];
					}elseif($shipping_promotions['sale_type'] == 'shipping_won' && $shipping_cost > 0 && $shipping_cost >= $shipping_promotions['promotioncode_shipping_sale'])	{
						$shippingcode_sale	= $shipping_promotions['promotioncode_shipping_sale'];
					}

					if	($shippingcode_sale > 0){
						$this->shipping_promotion_code_sale[$provider_seq]	= $shippingcode_sale;
						$this->shipping_promotion_code_seq[$provider_seq]	= $shipping_promotions['promotion_seq'];
						$this->shipping_cost								-= $shippingcode_sale;
						$total_shipping_price								-= $shippingcode_sale;

						$this->shipping_promotion_code_salecost[$provider_seq]	= ($codesales_use == 'A')	? 0 : floor($shippingcode_sale * ($shipping_promotions['salescost_provider']/100));
					}
				}
			}
		}

		//배송비쿠폰 할인
		if( $_POST['shippingcoupon_download'] && $total_shipping_price > 0 ) {
			$this->shipping_coupon_payment_b = false;
			$this->shippingcoupon_download_ck = false;
			foreach($_POST['shippingcoupon_download'] as $provider_seq => $download_seq) {

				//이벤트 배송비쿠폰 사용제한 @2015-08-13
				if( $this->ordernosales_cp_sh[$provider_seq] ) continue;

				$shippingcoupons = $this->couponmodel->get_download_coupon($download_seq);
				if	($shippingcoupons){
					if	($shippingcoupons['shipping_type'] == 'won'){
						if	($shippingcoupons['won_shipping_sale'] <= $this->provider_shipping_cost[$provider_seq]){
							$shippingcoupon_sale	= $shippingcoupons['won_shipping_sale'];
						}
					}else{
						if	($shippingcoupons['max_percent_shipping_sale'] <= $this->provider_shipping_cost[$provider_seq]){
							$shippingcoupon_sale	= $shippingcoupons['max_percent_shipping_sale'];
						}else{
							$shippingcoupon_sale	= $this->provider_shipping_cost[$provider_seq];
						}
					}

					if($this->shippingcoupon_download_ck === false && $this->arr_goods_shipping_price >0 ) $this->shippingcoupon_download_ck = true;

					if	($shippingcoupon_sale > 0) {

						//무통장만 사용가능
						if($shippingcoupons['sale_payment'] == 'b' && $this->shipping_coupon_payment_b != true )
							$this->shipping_coupon_payment_b = true;

						if( ( $shippingcoupons['type'] == 'memberGroup_shipping' || $shippingcoupons['type'] == 'member_shipping' || $shippingcoupons['type'] == 'memberlogin_shipping' || $shippingcoupons['type'] == 'membermonths_shipping' ) && $provider_seq == 1 ) {//배송그룹이 본사인경우 0
							$shippingcoupons['salescost_provider'] = 0;
						}
						$salescost										= floor($shippingcoupon_sale * ($shippingcoupons['salescost_provider']/100));
						$total_shipping_price							-= $shippingcoupon_sale;
						$this->shipping_cost							-= $shippingcoupon_sale;
						$this->shipping_coupon_salecost[$provider_seq]	= $salescost;
						$this->shipping_coupon_sale[$provider_seq]		= $shippingcoupon_sale;
						$this->shipping_coupon_down_seq[$provider_seq]	= $download_seq;
					}
				}
			}
		}

		//쿠폰>사용제한>무통장만가능
		if( is_array($coupon_sale_payment_b) || $this->shipping_coupon_payment_b ){
			$cart['coupon_sale_payment_b'] = count($coupon_sale_payment_b);
			if( $this->shipping_coupon_payment_b === true ) $cart['coupon_sale_payment_b'] = (int) ($cart['coupon_sale_payment_b'] + 1);
		}

		//쿠폰>사용제한>모바일/테블릿기기만가능
		if( is_array($coupon_sale_agent_m)){
			$cart['coupon_sale_agent_m'] = count($coupon_sale_agent_m);
		}

		// 에누리
		if($person_seq != ""){
			$query = $this->db->query("select * from fm_person where person_seq='".$this->db->escape_str($person_seq)."'");
			$res = $query->row_array();
			$enuri = $res['enuri'];
		}else{
			$enuri = (int) $_POST['enuri'];
		}

		// enuri가 더 큰 경우 최대값으로 변경
		if	(($cart['total'] - $cart['total_sale_price'] + $total_shipping_price) < $enuri){
			$enuri	= $cart['total'] - $cart['total_sale_price'] + $total_shipping_price;
		}

		/* 총 결제금액 */
		$settle_price = $cart['total_real_sale_price'] + $total_shipping_price - $enuri;
		if($settle_price<0)$settle_price=0;

		/* 주문금액 */
		$this->order_price = $cart['total'] + $total_shipping_price;

		/* 캐쉬 사용할 수 있는 금액 계산*/
		if( $members && ($_POST['cash'] > 0 || $_POST['cash_all']) ){
			$cart['cash'] = (int) $_POST['cash'];
			$settle_price -= (int) $cart['cash'];
		}

		$err_reserve = '';
		if( $members && ($_POST['emoney'] > 0 || $_POST['emoney_all']) ){

			$reserve_use = true;
			$reserves = ($this->reserves)?$this->reserves:config_load('reserve');

			$cart['emoney'] = (int) $_POST['emoney'];
			$settle_price -= (int) $cart['emoney'];
		}

		$this->amount = $settle_price;

		/* 상품결제가합 */
		$this->sum_goods_price = (int) $cart['total'];
		$this->settle_price = (int) $settle_price;
		$cart['total_price'] = $settle_price;

		$this->cart = $cart;

		$cart['total_sale_list']['shippingcoupon']['title']	= '배송비쿠폰';
		$cart['total_sale_list']['shippingcoupon']['price']	= 0;
		$cart['total_sale_list']['shippingcode']['title']	= '배송비코드';
		$cart['total_sale_list']['shippingcode']['price']	= 0;

		$shipping_coupon_sale_price			= array_sum($this->shipping_coupon_sale);
		if	($shipping_coupon_sale_price){
			$total_sales_price	+= $shipping_coupon_sale_price;
			$cart['total_sale_list']['shippingcoupon']['title']	= '배송비쿠폰';
			$cart['total_sale_list']['shippingcoupon']['price']	= $shipping_coupon_sale_price;
			$cart['total_sale_list']['coupon']['price']			+= $shipping_coupon_sale_price;
		}
		$shipping_promotion_code_sale_price	= array_sum($this->shipping_promotion_code_sale);
		if	($shipping_promotion_code_sale_price > 0){
			$total_sales_price	+= $shipping_promotion_code_sale_price;
			$cart['total_sale_list']['shippingcode']['title']	= '배송비코드';
			$cart['total_sale_list']['shippingcode']['price']	= $shipping_promotion_code_sale_price;
		}
		// 에누리
		if	($enuri > 0)	$total_sales_price	+= (int) $enuri;

		// 배송비 합계
		$total_org_shipping_price	= $total_shipping_shop + $tottal_shipping_goods + $total_shipping_add;

		// 추가배송비 처리
		$tot_add_delivery = 0;
		$tot_basic_delivery = 0;
		foreach($shipping_group_policy as $shipping_group=>$row){
			$tot_add_delivery += (int) $row['add_delivery_cost'];
		}

	}

	public function chkPhoneDash($phone) {
		if(strpos($phone,'-')===FALSE) { // add dash
			return preg_replace("/(^02.{0}|^01.{1}|[0-9]{3})([0-9]+)([0-9]{4})/", "$1-$2-$3", $phone);
		}
		else {
			return $phone;
		}
	}

	public function pg_cancel_script(){
		return '$("#wrap",parent.document).show();$("div.pay_layer",parent.document).eq(0).show();$("div.pay_layer",parent.document).eq(1).hide();$("#layer_pay",parent.document).hide();';
	}
}

