<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/board".EXT);

class mypage extends board
{
	function __construct()
	{
		parent::__construct();

		//
		$this->load->library('snssocial');
		$this->load->model('membermodel');
		$this->load->helper('member');
		$this->load->helper('order');
		$this->load->library('validation');
		$this->load->library('memberlibrary');

		if ($this->userInfo['member_seq']) {
			$this->mdata = $this->membermodel->get_member_data($this->userInfo['member_seq']);//회원정보

			$this->mdata['new_zipcode'] = str_replace("-", "", $this->mdata['zipcode']);
			$this->mdata['zipcode'] = substr($this->mdata['new_zipcode'],0,3)."-".substr($this->mdata['new_zipcode'],3,3);

			$this->mdata['new_bzipcode'] = str_replace("-", "", $this->mdata['bzipcode']);
			$this->mdata['bzipcode'] = substr($this->mdata['new_bzipcode'],0,3)."-".substr($this->mdata['new_bzipcode'],3,3);

			// 상.하위 등급 정보 추출
			$sc['group_seq']	= $this->mdata["group_seq"];
			$group				= $this->membermodel->get_member_group_flow($sc);
			$currentGroup		= $group['currentGroup'];
			$nextGroup			= $group['nextGroup'];

			// 소멸 예정 쿠폰
			$sc['member_seq']	= $this->userInfo['member_seq'];
			$extinction			= $this->membermodel->get_extinction($sc);

			$this->template->assign('mypoint',$this->mdata['point']);
			$this->template->assign('myinfo_sns_f',$this->mdata['sns_f']);
			$this->template->assign(array('myicon'=>$currentGroup["myicon"]));
			$this->template->assign(array('member_group'=>$currentGroup));
			$this->template->assign(array('next_group'=>$nextGroup));
			$this->template->assign(array('extinction'=>$extinction));

			$this->template->include_('assignMypageSummaryData');
			assignMypageSummaryData();
		}

		$this->joinform = ($this->joinform)?$this->joinform:config_load('joinform');
		$this->template->assign('joinform',$this->joinform);

		//포인트교환사용여부
		$configReserve_menu = config_load('reserve');
		if( $this->isplusfreenot["isemoney_exchange"] && $configReserve_menu["emoney_exchange_use"] == 'y' ) {
			$this->template->assign(array('emoney_exchange_use'=>$this->isplusfreenot["isemoney_exchange"]));
		}

		/**
		** @ board start
		**/
		$this->myqnatbl					= 'mbqna';//1:1문의
		$this->mygdqnatbl				= 'goods_qna';//상품문의
		$this->mygdreviewtbl			= 'goods_review';//상품후기

		$this->myqna->boardurl->resets		= '/mypage/myqna_catalog?';
		$this->myqna->boardurl->lists			= '/mypage/myqna_catalog?';
		$this->myqna->boardurl->view			= '/mypage/myqna_view?seq=';
		$this->myqna->boardurl->write			= '/mypage/myqna_write?';
		$this->myqna->boardurl->modify		= $this->myqna->boardurl->write.'&seq=';
		$this->myqna->boardurl->reply		= $this->myqna->boardurl->write.'&reply=y&seq=';
		$this->myqna->boardurl->goodsview		= '/goods/view?no=';						//상품접근

		$this->mygdqna->boardurl->resets	= '/mypage/mygdqna_catalog?';
		$this->mygdqna->boardurl->lists		= '/mypage/mygdqna_catalog?';
		$this->mygdqna->boardurl->view		= '/mypage/mygdqna_view?seq=';
		$this->mygdqna->boardurl->write		= '/mypage/mygdqna_write?';
		$this->mygdqna->boardurl->modify	= $this->mygdqna->boardurl->write.'&seq=';
		$this->mygdqna->boardurl->reply	= $this->mygdqna->boardurl->write.'&reply=y&seq=';
		$this->mygdqna->boardurl->goodsview		= '/goods/view?no=';						//상품접근

		$this->mygdreview->boardurl->resets	= '/mypage/mygdreview_catalog?';
		$this->mygdreview->boardurl->lists		= '/mypage/mygdreview_catalog?';
		$this->mygdreview->boardurl->view		= '/mypage/mygdreview_view?seq=';
		$this->mygdreview->boardurl->write		= '/mypage/mygdreview_write?';
		$this->mygdreview->boardurl->modify	= $this->mygdreview->boardurl->write.'&seq=';
		$this->mygdreview->boardurl->reply		= $this->mygdreview->boardurl->write.'&reply=y&seq=';
		$this->mygdreview->boardurl->goodsview		= '/goods/view?no=';						//상품접근

		$this->template->define(array('catalog_top'=>$this->skin.'/mypage/catalog_top.html'));
		/**
		** @ board end
		**/
	}

	public function main_index()
	{
		redirect("/mypage/index");
	}

	public function index()
	{
		login_check();

		//
		$this->load->model('ordermodel');
		$this->load->model('exportmodel');
		$this->load->model('buyconfirmmodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('wishmodel');
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->library('sale');

		// 최근 1개월간 데이터만 읽게끔 regist_date 인자값 추가 :: 2019-01-30 pjw
		$sc = [
			'perpage'     => '5',
			'member_seq'  => $this->userInfo['member_seq'],
			'hidden'      => 'N',
			'step_type'   => 'non_attempt',
			'regist_date' => [
				date('Y-m-d', strtotime('-1 month'))
			],
		];
		$orders = $this->get_my_orders($sc);

		//
		$this->template->assign([
			'orders' => $orders['record']
		]);

		//
		$cfg_reserve        = $this->reserves ?: config_load('reserve');
		$cfg_goodsImageSize = $this->goodsImageSize ?: config_load('goodsImageSize');

		//--> sale library 할인 적용 사전값 전달
		$applypage = 'wish';
		$param = [
			'cal_type'    => 'list',
			'total_price' => 0,
			'member_seq'  => $this->userInfo['member_seq'],
			'group_seq'   => $this->userInfo['group_seq'],
		];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<-- //sale library 할인 적용 사전값 전달
		// 19mark 이미지
		$this->load->library('goodsList');

		$wishImageSize	= 'list2';
		$result = $this->wishmodel->get_list( $this->userInfo['member_seq'], $wishImageSize );
		foreach ($result['record'] as &$goods) {
			// 카테고리정보
			$tmparr2 = [];
			$categorys = $this->goodsmodel->get_goods_category($goods['goods_seq']);
			foreach ($categorys as $val) {
				$tmparr = $this->categorymodel->split_category($val['category_code']);
				foreach ($tmparr as $cate) {
					$tmparr2[] = $cate;
				}
			}
			if ($tmparr2) {
				$tmparr2 = array_values(array_unique($tmparr2));
				$goods['r_category'] = $tmparr2;
			}

			$goods['string_price'] = get_string_price($goods);
			$goods['string_price_use'] = 0;
			if ($goods['string_price'] != '') {
				$goods['string_price_use'] = 1;
			}

			//----> sale library 적용
			unset($param, $sales);
			$param = [
				'consumer_price' => $goods['consumer_price'],
				'total_price'    => $goods['price'],
				'price'          => $goods['price'],
				'ea'             => 1,
				'category_code'  => $goods['r_category'],
				'goods_seq'      => $goods['goods_seq'],
				'goods'          => $goods,
			];
			$this->sale->set_init($param);
			$sales	= $this->sale->calculate_sale_price($applypage);

			//
			$goods['org_price']  = $goods['consumer_price'] ?: $goods['price'];
			$goods['sale_price'] = $sales['result_price'];
			// 포인트
			$goods['point'] = (int) $this->goodsmodel->get_point_with_policy($sales['result_price']) + $sales['tot_point'];
			// 마일리지
			$goods['reserve'] = (int) $this->goodsmodel->get_reserve_with_policy($goods['reserve_policy'],$sales['result_price'],$cfg_reserve['default_reserve_percent'],$goods['reserve_rate'],$goods['reserve_unit'],$goods['reserve']) + $sales['tot_reserve'];

			$this->sale->reset_init();
			unset($sales);
			//<---- sale library 적용

			// 배송정보 가져오기
			$goods['delivery'] = $this->goodsmodel->get_goods_delivery($goods);

			// 19mark 이미지
			$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($goods);
			if ($markingAdultImg) {
				$goods['image']	= $this->goodslist->adultImg;
			}

			// 이미지 부분을 컨트롤에서 지정 :: 2015-03-14 lwh
			//#20299 2018-08-01 ycg 이미지 경로에 https가 포함된 경우에도 표시되도록 수정
			if ((!preg_match('/http[s]*:\/\//',$goods['image']) && !file_exists(ROOTPATH.$goods['image'])) || $goods['image'] == '') {
				$goods['image'] = '/data/skin/' . $this->skin . '/images/common/noimage_list.gif';
			}

			$wish_size = $cfg_goodsImageSize[$wishImageSize];
			$goods['image_html'] = "<img src='" . $goods['image'] . "' width='" . $wish_size['width'] . "' align='absmiddle' hspace='5' style='border:1px solid #ddd;' />";

			//예약 상품의 경우 문구를 넣어준다 2016-11-07
			$goods['goods_name'] = get_goods_pre_name($goods);
		}

		// 날짜 검색 추가 (1개월기준으로 변경) :: 2019-01-30 pjw
		$sc = [
			'member_seq' => $this->userInfo['member_seq'],
			's_date'     => date('Y-m-d H:i:s', strtotime('-1 month'))
		];
		$eainfo	= $this->ordermodel->get_ea_for_step(true, true, $sc);
		$counts = [];
		if ($eainfo) {
			foreach ($eainfo as $data) {
				$step	= $this->ordermodel->get_step_by_ea($data, 2);
				$counts[$step]++;
			}
		}

		// 공통 - 모바일 사이드 회원 바코드
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_front_mobile_side_barcode();

		// 반품/교환 취소/환불 수량 구하기
		$sc = [
			'mode'       => 'count',
			'member_seq' => $this->userInfo['member_seq'],
		];
		$counts = [
			'refund' => $this->refundmodel->get_refund_list($sc),
			'return' => $this->returnmodel->get_return_list($sc),
		];

		//
		$this->template->assign(['counts' => $counts]);
		$this->template->assign(['wish' => $result]);
		$this->template->assign(array('mypage_index'=>true));
 		$this->print_layout($this->template_path());
	}

	public function order_catalog()
	{
		// 비회원 로그인이 되어있는 경우 주문상세로 리다이렉트 :: 2019-02-08 pjw
		$sessOrder = $this->session->userdata('sess_order');
		if (!empty($sessOrder) && !$this->userInfo['member_seq']) {
			redirect("/mypage/order_view");
			exit;
		}

		if (!$this->userInfo['member_seq']) {
			redirect("/member/login?order_auth=1");
			exit;
		}

		//
		$this->load->model('ordermodel');
		$this->load->helper('order');

		//
		$aGetParams   = $this->input->get();

		//
		if (!$this->cfg_order) {
			$this->cfg_order	= config_load('order');
		}

		//
		if ($this->realMobileSkinVersion > 2 && $this->mobileMode && $aGetParams['sc_date'] != 'direct') {
			// 기본검색 3주
			if ($aGetParams['sc_date'] == '') {
				$aGetParams['sc_date']	= 3;
			}

			// 전체 기간이 아닌 경우에만 시작날짜 지정 :: 2019-02-11 pjw
			if ($aGetParams['sc_date'] != 0) {
				$aGetParams['sc_sdate'] = date('Y-m-d', strtotime("-".($aGetParams['sc_date']*7+1)." day"));
			}
			$aGetParams['sc_edate'] = date('Y-m-d');
			$_GET = $aGetParams;
		}

		if ($this->config_system['operation_type'] == 'light' && $aGetParams['sc_date'] != 'direct' ) {
			// 기본검색 3주
			if ($aGetParams['sc_date'] == '') {
				$aGetParams['sc_date']	= 3;
			}

			if ($aGetParams['sc_date'] > 0 && $aGetParams['sc_date'] < 4 ) {
				$sTimes   = "-" . $aGetParams['sc_date'] * 7 + 1 . " day";
			}
			if ($aGetParams['sc_date'] == 4) {
				$sTimes   = "-1 month";
			}
			if ($aGetParams['sc_date'] == 8) {
				$sTimes   = "-2 month";
			}
			if ($aGetParams['sc_date'] == 12) {
				$sTimes   = "-3 month";
			}

			// 전체 기간이 아닌 경우에만 시작날짜 지정 :: 2019-02-11 pjw
			if ($aGetParams['sc_date'] != 0) {
				$aGetParams['sc_sdate'] = date('Y-m-d', strtotime($sTimes));
			}

			$aGetParams['sc_edate'] = date('Y-m-d');
		}

		$this->template->assign(array('cfg_order'	=> $this->cfg_order));

		/**
		 * list setting
		**/
		$sc = [
			'page'				=> $_GET['page'] ?: 1,
			'perpage'			=> $perpage ?: 10,
			'member_seq'		=> $this->userInfo['member_seq'],
			'keyword'			=> $aGetParams['keyword'],
			'regist_date'		=> [
				$aGetParams['sc_sdate'] ?: $aGetParams['regist_date'][0],
				$aGetParams['sc_edate'] ?: $aGetParams['regist_date'][1],
			],
			'hidden'			=> 'N',
			'step_type'			=> $aGetParams['step_type'] == 'export' ? 'export_and_complete' : $aGetParams['step_type'],
		];
		if (! $sc['step_type']) {
			$sc['step_type'] = 'non_attempt';
		}

		//
		$orders = $this->get_my_orders($sc);

		//
		$this->template->assign([
			'cfg_order'          => $this->cfg_order,
			'order_step_arr'     => $this->ordermodel->arr_step,
			'aParams'            => $aGetParams
		]);
		$this->template->assign($orders);

		$this->print_layout($this->template_path());
	}


	public function order_view()
	{
		login_check();

		// reset
		$is_direct_store = false;
		$cfg = array();

		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->model('shippingmodel');
		$this->load->model('exportmodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('buyconfirmmodel');
		$this->load->model('giftmodel');
		$this->load->helper('order');
		$this->load->library('orderlibrary');
		$this->load->library('exportlibrary');

		$this->load->helper('shipping');
		$arr_shipping_method = get_shipping_method('all');

		if(!$this->arr_step)	$this->arr_step = config_load('step');
		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		if(!$this->cfg_order)	$this->cfg_order = config_load('order');

		// 기본 로드
		$cfg['order'] = ($this->cfg_order) ? $this->cfg_order : config_load('order');
		$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');
		$this->template->assign('cfg_reserve',$cfg_reserve);

		$this->template->assign(array('cfg_order'	=> $this->cfg_order));

		$file_path	= $this->template_path();

		if(!$this->userInfo['member_seq']){
			$order_seq = $this->session->userdata('sess_order');
			if(!$order_seq) {
				redirect("/member/login?order_auth=1");
				exit;
			}

			$orders 			= $this->ordermodel->get_order($order_seq);
		}else{
			$order_seq 		= (int) $_GET['no'];
			$member_seq	= (int) $this->userInfo['member_seq'];

			$orders 			= $this->ordermodel->get_order($order_seq, array("member_seq"=>$member_seq));

			// 회원인 경우 최근 배송 메세지 추출 :: 2016-11-03 lwh
			$this->load->model("ordermodel");
			$lately_msg = $this->ordermodel->get_ship_message($this->userInfo['member_seq'],'2');
			if($lately_msg) $this->template->assign('lately_msg', $lately_msg);
		}

		# 자동 주문 무효 사용시 :: 2017-05-26 lwh
		if($this->cfg_order['autocancel'] == 'y'){
			$this->cfg_order['autocancel_txt'] = getAlert("sy005", date('Y'.getAlert("sy006").' m'.getAlert("sy007").' d'.getAlert("sy008").'', strtotime($orders['regist_date']."+".$this->cfg_order['cancelDuration']." days"))); // %s까지 (이후 입금되지 않았을 경우 자동으로 주문무효 처리)
		}
		$this->template->assign(array('cfg_order'	=> $this->cfg_order));

		// 주문 당시 배송정책 존재여부
		$this->load->library('shipping');
		list($shipping_group_seq, $shipping_set_seq, $shipping_set_code, $shipping_method) = $this->shipping->getOrderShippingInfo($order_seq);
		$shipping_group_exists = $this->shippingmodel->shipping_group_exists($shipping_group_seq);
		$shipping_group_set_exists = $this->shippingmodel->shipping_group_set_exists($shipping_group_seq, $shipping_set_seq, $shipping_set_code);
		$orders['shipping_group_exists'] = $shipping_group_exists ? 'Y' : 'N';
		$orders['shipping_group_set_exists'] = $shipping_group_set_exists ? 'Y' : 'N';
		$orders['shipping_method'] = $orders['shipping_method'] ? $orders['shipping_method'] : $shipping_method;

		// 배송가능 해외국가 추출
		$ship_gl_arr	= $this->shippingmodel->get_gl_shipping();
		$ship_gl_list	= $this->shippingmodel->split_nation_str($ship_gl_arr);

		// 신)배송비 관련 변수 정의
		$this->template->assign(array(
			'ship_gl_arr'=>$ship_gl_arr,
			'ship_gl_list'=>$ship_gl_list
		)); // 국가목록


		// ### NEW 배송 그룹 정보 추출 ### :: START -> shipping library 계산
		
		$nation = $this->shippingmodel->get_gl_nation($orders['nation_name_eng']);
		$ini_info = $this->shipping->set_ini(array('nation' => $nation, 'nation_key' => $orders['nation_key']));

		// 배송지 주소 추출 :: 2016-08-02 lwh
		$add_sql = "
			SELECT address_group
			FROM fm_delivery_address
			WHERE
				member_seq=? AND
				address_group is not null AND
				address_group !=''
			GROUP BY address_group
			ORDER BY address_group ASC
		";
		$query = $this->db->query($add_sql,$this->userInfo['member_seq']);
		$arr_address_group = $query->result_array();

		// 기본 그룹일 경우 수정
		foreach($arr_address_group as $k=>$v){
			$arr_address_group[$k]['address_group'] = ($arr_address_group[$k]['address_group']=="기본 그룹")?getAlert("dv007"):$arr_address_group[$k]['address_group'];
		}

		if(!$arr_address_group){
			$arr_address_group[]['address_group'] = getAlert("dv007"); // '기본 그룹';
		}
		$this->template->assign('arr_address_group',$arr_address_group); // 배송지 주소정보

		# 현금영수증 발행신청 가능 기한
		if(strtotime($orders['deposit_date']."+10 days") > time()) {
			$orders['cash_receipt_possible'] = true;

			switch($this->config_system['language']) {
				case 'US': $orders['cash_receipt_possible_txt'] = '(Available until ' . date('M d, Y', strtotime($orders['deposit_date']."+10 days")) . ')'; break;
				case 'CN': $orders['cash_receipt_possible_txt'] = '(' . date('Y年 m月 d日', strtotime($orders['deposit_date']."+10 days")) . '可申请)'; break;
				case 'KR': $orders['cash_receipt_possible_txt'] = '(' . date('Y년 m월 d일', strtotime($orders['deposit_date']."+10 days")) . '까지 신청가능)'; break;
				case 'JP': $orders['cash_receipt_possible_txt'] = '(' . date('Y年 m月 d日', strtotime($orders['deposit_date']."+10 days")) . 'までにお申し込み可能)'; break;
			}


		}

		# 세금계산서 발행신청 가능 기한 (익월 5일까지, 익월 6일부터 제한)
		$tax_limit_date = date('Y-m-05 23:59:59', strtotime($orders['deposit_date']."+1 month"));
		if(time() < strtotime($tax_limit_date)) {
			$orders['tax_receipt_possible'] = true;
//			$orders['tax_receipt_possible_txt'] = '(' . date('Y년 m월 d일', strtotime($tax_limit_date)) . '까지 신청가능)';

			switch($this->config_system['language']) {
				case 'US': $orders['tax_receipt_possible_txt'] = '(Available until ' . date('M d, Y', strtotime($tax_limit_date)) . ')'; break;
				case 'CN': $orders['tax_receipt_possible_txt'] = '(' . date('Y年 m月 d日', strtotime($tax_limit_date)) . '可申请)'; break;
				case 'KR': $orders['tax_receipt_possible_txt'] = '(' . date('Y년 m월 d일', strtotime($tax_limit_date)) . '까지 신청가능)'; break;
				case 'JP': $orders['tax_receipt_possible_txt'] = '(' . date('Y年 m月 d日', strtotime($tax_limit_date)) . 'までにお申し込み可能)'; break;
			}
		}

		if($orders['step'] == 0 || $orders['hidden']=='Y' || $orders['hidden']=='T'){
			//잘못된 접근입니다.
			pageBack(getAlert('os216'));
			exit;
		}

		// 결제정보 가져오기
		$order_pg_log = $this->ordermodel->get_pg_log($order_seq);
		if(is_array($order_pg_log)) {
			$order_pg_log = array_shift($order_pg_log);
		}

		$pay_log 		= $this->ordermodel->get_log($order_seq,'pay');
		$process_log 	= $this->ordermodel->get_log($order_seq,'process');


		$giftorder 			= $this->ordermodel->get_gift_item($order_seq);

		// 간편결제 수단 View 변경 :: 2015-02-26 lwh
		if		($orders['pg'] == 'kakaopay'){
			$orders['payment_cd'] = $order_pg_log['payment_cd'];
			if($order_pg_log['payment_cd'] == 'MONEY'){
				$orders['mpayment'] = '카카오페이 (카카오머니)';
			}else{
				$orders['mpayment']	= "카카오페이 (".$this->arr_payment[$orders['payment']].")";
			}
		}else if($orders['pg'] == 'payco'){
			$orders['mpayment']	= "페이코 (".$this->arr_payment[$orders['payment']].")";
		}else{
			$orders['mpayment'] = $this->arr_payment[$orders['payment']];
		}
		$orders['mstep'] 	= $this->arr_step[$orders['step']];

		// 카드 할부 정보 표기 :: 2018-09-05 lwh
		if ($order_pg_log['quota'] > 0)	$orders['card_quota'] = $order_pg_log['quota'];


		$orders = $this->memberlibrary->replace_mypage_order($orders);

		$arr = config_load('bank');
		if($arr) foreach(config_load('bank') as $k => $v){
			list($tmp) = code_load('bankCode',$v['bank']);
			$v['bank'] = $tmp['value'];
			$bank[] = $v;
		}

		// 배송비 관련 추가 재계산 :: 2016-08-10 lwh
		$able_return_ea = $tot['coupontotal'] =$tot['goodstotal'] = 0;

		/**
		 * order_view_front return
		 * 	'is_goods' => $is_goods,
			'is_coupon' => $is_coupon,
			'items_tot' => $tot,
			'items' => $items,
			'is_direct_store' => $is_direct_store,
			'shipping_tot' => $shipping_tot,
			'able_return_ea' => $able_return_ea,
			'shipping_group_items' => $shipping_group_items,
		 */
		$order_view_front = $this->orderlibrary->order_view_front($orders);
		$able_return_ea = $order_view_front['able_return_ea'];
		$this->template->assign($order_view_front);

		// 회원 정보 가져오기
		if($orders['member_seq']){
			$members = $this->membermodel->get_member_data($orders['member_seq']);
			if($members["bzipcode"]){
				$business_info["co_new_zipcode"]	= str_replace('-','',$members["bzipcode"]);
				$business_info["co_zipcode"][]		= substr($business_info["co_new_zipcode"],0,3);
				$business_info["co_zipcode"][]		= substr($business_info["co_new_zipcode"],3,3);
			}

			$business_info["bname"] = $members["bname"];
			$business_info["bno"] = $members["bno"];
			$business_info["bCEO"] = $members["bceo"];
			// 거꿀로 저장되어 업태/업종 변경
			$business_info["bstatus"] = $members["bitem"];
			$business_info["bitem"] = $members["bstatus"];

			$business_info["bperson"] = $members["bperson"];
			$business_info["email"] = $members["email"];
			$business_info["bphone"] = ($members["bphone"])? str_replace("-","",$members["bphone"]) : "";
			$business_info["baddress1"] = ($members["baddress_type"] == 'street')?$members["baddress_street"]:$members["baddress"];
			$business_info["baddress2"] = $members["baddress_detail"];
			$business_info["baddress_type"] = $members["baddress_type"];
			$business_info["baddress_street"]			= $members["baddress_street"];
			$this->template->assign(array('members'=>$members));
			$this->template->assign(array('business_info'=>$business_info));
		}

		// 배송방법
		$orders['mshipping'] = $this->ordermodel->get_delivery_method($orders);

		$this->load->helper('shipping');
		$shipping = use_shipping_method();
		if( $shipping ) foreach($shipping as $key => $data){
			if($data) $shipping_cnt[$key] = count($data);
		}
		$shipping_policy['policy'] 	= $shipping;


		// 출고정보
		// exports data assign
		$export_view_front = $this->exportlibrary->export_view_front($orders['order_seq']);
		// 하단에서 이용하는 변수 정의
		$exports = $export_view_front['exports'];
		$export_cnt = $export_view_front['export_cnt'];
		$buy_confirm_cnt = $export_view_front['buy_confirm_cnt'];
		$able_return_ea_ck = $export_view_front['able_return_ea_ck'];		
		$this->template->assign($export_view_front);


		/**
		* 반품/맞교환 신청버튼 숨김처리
		* 출고일 export_date 출고완료일 complete_date 배송완료일 shipping_date
		- 반품신청 가능갯수가 없으면
		- 출고시 배송완료일로 신청이 가능한 기간
		* @2016-11-17
		**/
		if( $buy_confirm_cnt  == $export_cnt ){
			$orders['buy_confirm'] = true;
			// 출고 갯수와 구매확정 갯수가 동일할 경우 모든 상품이 구매확정되었다는 의미이므로 반품 가능 수량이 없어야함.
			$able_return_ea = 0;
		}
		if(($able_return_ea<1) || ($able_return_ea && (!$able_return_ea_ck)) )$able_return_ea = 0;
		// 반품가능한 주문상품수량
		$orders['able_return_ea'] = $able_return_ea;
		$this->template->assign(array('return_able_ea'=>$able_return_ea));

		// 결제취소 가능수량(상품준비 취소 옵션)
		$refund_able_ea = $this->refundmodel->get_refund_able_ea($order_seq);
		if($this->cfg_order['cancelDisabledStep35'] == '1' && $orders['step']>=35){
			$refund_able_ea		= 0;
			$result_option		= $this->ordermodel->get_item_option($order_seq);
			$result_suboption	= $this->ordermodel->get_item_suboption($order_seq);

			//부분 상품준비의 경우 취소 가능수량 확인
			foreach((array)$result_option as $opt_row)			if($opt_row['step'] == 25)		$refund_able_ea		+= $opt_row['ea'];
			foreach((array)$result_suboption as $subopt_row)	if($subopt_row['step'] == 25)	$refund_able_ea		+= $subopt_row['ea'];
		}

		$this->load->model('salesmodel');
		//세금계산서
		$sc['whereis']	= ' and typereceipt = 1 and order_seq="'.$order_seq.'" ';
		$sc['select']		= ' * ';
		$taxs 		= $this->salesmodel->get_data($sc);
		if( $taxs ) {
			$zipcodear[0] = substr(str_replace("-", "", $taxs['zipcode']),0,3);
			$zipcodear[1] = substr(str_replace("-", "", $taxs['zipcode']),3,3);
			$taxs['new_zipcode'] = $taxs['zipcode'];
			$taxs['zipcode0'] = $zipcodear[0];
			$taxs['zipcode1'] = $zipcodear[1];
			$this->template->assign(array('tax'	=> $taxs));
		}

		$qry = "select * from fm_sales where order_seq = '".$order_seq."'";
		$query = $this->db->query($qry);
		$tax_array = $query -> result_array();
		$this->template->assign(array('tax_array'	=> $tax_array));

		//현금영수증
		$sc['whereis']	= ' and typereceipt = 2 and order_seq="'.$order_seq.'" ';
		$sc['select']		= ' * ';
		$creceipts 		= $this->salesmodel->get_data($sc);
		if( $creceipts ) {
			$creceipts['goods_name'] = ( count($items) > 1 ) ? $items[0]['goods_name'] .getAlert("os125",(count($items)-1)):$items[0]['goods_name'];		// $items[0]['goods_name'] ."외".(count($items)-1)."건":$items[0]['goods_name'];
			$creceipts['cash_receipts_no'] = ($creceipts['cash_no'])?$creceipts['cash_no']:$order['cash_receipts_no'];
			$this->template->assign(array('creceipt'	=> $creceipts));
		}

		### 카드결제
		$sc['whereis']	= ' and typereceipt = 0 and order_seq="'.$order_seq.'" ';
		$sc['select']		= ' * ';
		$cards 		= $this->salesmodel->get_data($sc);
		if($cards){
			$this->template->assign(array('cards'=> $cards));
		}

		###
		$pg = config_load($this->config_system['pgCompany']);
		$naxCheck = $pg["nonActiveXUse"];

		$this->template->assign('pg',$pg);
		$this->template->assign('pgCompany',$this->config_system['pgCompany']);
		$this->template->assign('naxCheck',$naxCheck);

		if( $this->config_system['pgCompany'] == 'lg' && $orders['pg_transaction_number']) {
			$orders['authdata'] = md5($pg['mallCode'] . $orders['pg_transaction_number'] . $pg['merchantKey']);
		}else{
			$orders['authdata'] = '';
		}

		$cancel_log 	= $this->ordermodel->get_log($order_seq,'cancel');
		foreach($cancel_log as $k=>$row){
			$cancel_log[$k]['detail']='';
		}

		//반품정보 가져오기
		$orders['return_list_ea'] = 0;
		$this->load->model('returnmodel');
		$data_return = $this->returnmodel->get_return_for_order($order_seq);
		if( $data_return )foreach($data_return as $k=>$data){
			$data['mstatus'] = $this->returnmodel->arr_return_status[$data['status']];
			//관리자가 처리했을경우 ID가져오기
			if($data['manager_seq']){
				$rtsql = "select * from fm_manager where manager_seq=?";
				$m_seq=$data['manager_seq'];
				$query = $this->db->query($rtsql,array($m_seq));
				$m_data = $query->row_array();
				$data['manager_id']=$m_data['manager_id'];
			}

			$data_return[$k] = $data;
			$orders['return_list_ea'] += $data['ea'];
		}



		//환불정보 가져오기
		$orders['cancel_list_ea'] = 0;
		$orders['refund_list_ea'] = 0;
		$this->load->model('refundmodel');
		$data_refund = $this->refundmodel->get_refund_for_order($order_seq);
		if( $data_refund )foreach($data_refund as $k=>$data){

			// 반품정보를 가져와서 반품배송비 차감여부 검사 후 마이너스 처리 :: 2018-08-24 pjw
			/*
			$return_data = $this->returnmodel->get_return_refund_code($data['refund_code']);
			if($return_data['refund_ship_duty'] == 'buyer' && $return_data['refund_ship_type'] == 'M'){
				$data['refund_price'] -= $return_data['return_shipping_price'];
			}
			*/
			//반품배송비 판매자부담, 환불해준 배송비(refund_delivery)에 대해서 차감
			if($return_data['refund_ship_duty'] == 'seller'){
				$data['refund_price'] -= $data['refund_delivery'];
			}

			$data['mstatus'] = $this->refundmodel->arr_refund_status[$data['status']];
			if($data['manager_seq']){
				$rtsql = "select * from fm_manager where manager_seq=?";
				//관리자가 처리했을경우 ID가져오기
				$m_seq=$data['manager_seq'];
				$query = $this->db->query($rtsql,array($m_seq));
				$m_data = $query->row_array();
				$data['manager_id']=$m_data['manager_id'];
			}

			$data_refund[$k] = $data;

			if( $data['refund_type'] == 'cancel_payment' ){
				$orders['cancel_list_ea'] += $data['ea'];
			}else{
				$orders['refund_list_ea'] += $data['ea'];
			}
		}

		/* 모바일 입금정보 표시 leewh 2014-09-04 */
		if (in_array($orders['payment'], array("bank","virtual","escrow_account","escrow_virtual"))) {
			$num_account = ($orders['payment']=='bank')? $orders['bank_account'] : $orders['virtual_account'];
			$depositor = ($orders['payment']=='bank')? sprintf("<div style='margin-top:5px;'>".getAlert("os063")." : %s</div>",$orders['depositor']) : '';
			$deposit_info = sprintf("%s %s", $num_account, $depositor);
			$deposit_css = sprintf('style="height:%s"', ($depositor)? "90px" : "70px");
			$this->template->assign(array('deposit_css' => $deposit_css));
			$this->template->assign(array('deposit_info' => $deposit_info));

			// 하나은행 강상계좌시 은행명에 공백이 들어가는 현상으로 인해 공백 제거 2018-03-15 가비아CNS 정현수
			if(strpos($num_account,'KEB 하나(구 외환)') !== false){
				$num_account = str_replace('KEB 하나(구 외환)','KEB하나(구외환)',$num_account);
			}

			// 다국어 모바일 전용-계좌번호와 입금자 정보 분리 (2017-06-28 오후 2:57  가비아씨엔에스 채우형)
			$account_number = explode(' ', $num_account);
			if(!$account_number[2]) $account_number[2] = '';

			// 배열의 인덱스가 3개를 초과할때는 초과한 인덱스를 제거해야 array_combine이 제대로 동작함 2019-05-20 :: rsh
			$indexCnt = count($account_number);
			if($indexCnt>3) {
			    for($i=3; $i<$indexCnt; $i++) {
			        unset($account_number[$i]);
			    }
			}
			$account_number = array_combine(array('bank_name','account_number','account_owner'), $account_number);
			$account_number['account_owner'] = preg_replace('/^\((.*)\)$/Ui', '$1', $account_number['account_owner']);

			// 예금주를 다국어로 변환
			$account_number['account_owner'] = str_replace("예금주",getAlert("sy079"),$account_number['account_owner']);

			$this->template->assign(array('account_number' => $account_number));
		}

		if($orders['virtual_date'] > '1900-01-01 00:00:00'){
			$orders['virtual_date_view']	= date('Y-m-d',strtotime($orders['virtual_date']));
		}else{ $orders['virtual_date_view'] = ''; }

		// mobile ver 2.0에서 사용
		$order_shippings[0]				= $orders;
		$order_shippings[0]['exports']	= $exports;

		// 개별 메세지 처리 :: 2016-09-02 lwh
		if($orders['each_msg_yn'] == "Y")	$orders['memo'] = $ship_message;

		// 총 주문수량
		$order_total_ea = $this->ordermodel->get_order_total_ea($orders['order_seq']);

		// 세금계산서 신청 제한 :: 2017-08-22 lwh
		$tot_refund_ea = $orders['return_list_ea'] + $orders['cancel_list_ea'] + $orders['refund_list_ea'];
		$orders['sales_tax_allow'] = 'Y';
		if	( $tot_refund_ea > 0 ) foreach($data_refund as $k => $refund){
			if($refund['status'] != 'complete'){
				// 세금계산서 신청 불가
				$orders['sales_tax_allow'] = 'N';
			}
		}

		// 총 결제금액 비교통화
		$this->template->include_('showCompareCurrency');
		$total_price_compare = showCompareCurrency('',$orders['settleprice'],'return',$compare_class);

		$pg_log = $this->ordermodel->get_pg_log($order_seq);
		$orders['pg_log'] = $pg_log[0];

		$this->template->assign('cfg',$cfg);
		$this->template->assign('nation',$nation);
		$this->template->assign('ini_info',$ini_info); // 배송ini 설정정보
		$this->template->assign('order_pg_log', $order_pg_log);
		$this->template->assign('order_total_ea', $order_total_ea);
		$this->template->assign('total_price_compare', $total_price_compare);
		$this->template->assign(array('order_shippings'		=> $order_shippings));
		$this->template->assign(array('coupon_export'		=> $coupon_export));
		$this->template->assign(array('giftorder'	=> $giftorder));
		$this->template->assign(array('orders'	=> $orders));
		$this->template->assign(array('bank'	=> $bank));
		$this->template->assign(array('pay_log'	=> $pay_log));
		$this->template->assign(array('process_log'	=> $process_log));
		$this->template->assign(array('cancel_log'	=> $cancel_log));
		$this->template->assign(array('data_return'	=> $data_return));
		$this->template->assign(array('data_refund'	=> $data_refund));
		$this->template->assign(array('shipping_policy'	=> $shipping_policy));
		$this->template->assign(array('able_step_action' => $this->ordermodel->able_step_action));
		$this->template->assign(array('refund_able_ea'	=> $refund_able_ea));

		//2016.03.31 거래명세서 버튼 추가 pjw
		if($this->config_basic['usetradeinfo'] == 'Y' && $orders['step'] != 85 && $orders['step'] != 95){
			// 구 스킨용
			$btn_script = "window.open('/prints/form_print_trade?no=".$orders['order_seq']."', '_trade', 'width=960,height=640,scrollbars=yes');";
			$btn_style  = 'font-size:11px;display: inline-block; background: #000; color: #fff; line-height: 20px; padding: 0px 7px; cursor: pointer;margin-left:5px';
			$btn_tag	= '<span class="btn_trade" style="'.$btn_style.'" onclick="'.$btn_script.'">'.getAlert("mp296").'</span>';	// 거래명세서
			$this->template->assign(array('btn_tradeinfo'=>$btn_tag));

			// 신 스킨용
			$this->template->assign(array('is_btn_tradeinfo'=>true, 'btn_tradeinfo_script'=>$btn_script));
		}

		if($_GET['mode']=='summary'){
			$file_path = str_replace('order_view','order_view_summary',$this->template_path());
			$this->template->define(array('tpl'=>$file_path));
			$this->template->print_("tpl");
		}else{
			$this->print_layout($this->template_path());
		}
	}

	//결제취소 -> 환불
	public function order_refund(){
		$this->load->model('ordermodel');
		$this->load->model('refundmodel');
		$this->load->helper('order');

		$sSessionOrder	= $this->session->userdata('sess_order');
		$sMemberSeq		= (int) $this->userInfo['member_seq'];

		if(!$this->arr_step)	$this->arr_step		= config_load('step');
		if(!$this->arr_payment)	$this->arr_payment	= config_load('payment');
		if(!$this->cfg_order)	$this->cfg_order	= config_load('order');

		$pg = config_load($this->config_system['pgCompany']);

		$order_seq	= $_POST['order_seq'] ? (int) $_POST['order_seq'] : (int) $_GET['order_seq'];
		$able_steps	= $this->ordermodel->able_step_action['cancel_payment'];

		if(!$sMemberSeq && !$sSessionOrder){
			//잘못된 접근입니다.
			echo getAlert('os216');
			exit;
		}

		if( $sSessionOrder && $order_seq != $sSessionOrder){
			//잘못된 접근입니다.
			echo getAlert('os216');
			exit;
		}

		if( !$sMemberSeq && $sSessionOrder ){
			$orders 			= $this->ordermodel->get_order($sSessionOrder);
		}else if( $sMemberSeq ) {
			$orders 			= $this->ordermodel->get_order($order_seq, array("member_seq"=>$sMemberSeq));
		}

		if( $sMemberSeq && $sMemberSeq!=$orders['member_seq'] ){
			//잘못된 접근입니다.
			pageBack(getAlert('os216'));
			exit;
		}

		if($orders['step'] == 0 || $orders['hidden']=='Y' || $orders['hidden']=='T'){
			//잘못된 접근입니다.
			pageBack(getAlert('os216'));
			exit;
		}

		//결제취소/주문무효-> 결제취소 불가 @2016-07-12 ysm
		if( in_array($orders['step'],array('85','95')) ){
			$msg = $this->arr_step[$orders['step']]."에서는 환불신청을 하실 수 없습니다.";
			if($_GET['use_layout']){
				pageBack($msg);exit;
			}else{
				echo js('alert("'.$msg.'");');exit;
			}
		}

		$items 				= $this->ordermodel->get_item($orders['order_seq']);
		$tot				= array();
		$order_total_ea		= $this->ordermodel->get_order_total_ea($orders['order_seq']);

		$result_option		= $this->ordermodel->get_item_option($orders['order_seq']);
		$result_suboption	= $this->ordermodel->get_item_suboption($orders['order_seq']);

		//주문취소가능 수량(주문접수, 결제완료, 상품준비) @2015-06-05 pjm
		$remain_ea = 0;
		foreach($result_option as $opt){
			if($this->cfg_order['cancelDisabledStep35'] != '1' || ($this->cfg_order['cancelDisabledStep35'] == '1' && $opt['step'] == '25')){
				$remain_ea += $opt['ea']-((int)$opt['step85']+(int)$opt['step45']+(int)$opt['step55']+(int)$opt['step65']+(int)$opt['step75']);
			}
		}
		foreach($result_suboption as $opt){
			if($this->cfg_order['cancelDisabledStep35'] != '1' || ($this->cfg_order['cancelDisabledStep35'] == '1' && $opt['step'] == '25')){
				$remain_ea += $opt['ea']-((int)$opt['step85']+(int)$opt['step45']+(int)$opt['step55']+(int)$opt['step65']+(int)$opt['step75']);
			}
		}

		if($remain_ea == 0){
			if( $_GET['use_layout'] ) {
				//결제취소 가능한 수량이 없습니다.
				pageBack(getAlert('mo074'));
			}else{
				//결제취소 가능한 수량이 없습니다.
				echo "<div class='center'>".getAlert('mo074')."</div>";
			}
			exit;
		}

		$loop = array();

		foreach($items as $key=>$item){
			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);

			if($options) foreach($options as $k=>$option){

				if ( ($item['goods_kind'] == 'coupon' && $options[$k]['step']>=35 )  ) continue;//티켓상품 출고준비이상 제외@2013-11-12
				$options[$k]['mstep']	= $this->arr_step[$options[$k]['step']];

				$rf_ea = $this->refundmodel->get_refund_option_ea($item['item_seq'],$option['item_option_seq']);
				$step_complete = $this->ordermodel->get_option_export_complete($order_seq,$option['shipping_provider_seq'],$item['item_seq'],$option['item_option_seq']);

				if($this->cfg_order['cancelDisabledStep35'] == '1'){
					$step_complete	+= $options[$k]['step35'];
				}

				$options[$k]['able_refund_ea'] = $option['ea'] - $rf_ea - $step_complete;
				$tot['ea'] += $option['ea'];
				$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $option['item_option_seq'], '');

				if($suboptions) foreach($suboptions as $k_sub=>$suboption){
					if ( ($item['goods_kind'] == 'coupon' && $suboptions[$k_sub]['step']>=35 )  ) continue;//티켓상품 출고준비이상 제외@2013-11-12
					$suboptions[$k_sub]['mstep']	= $this->arr_step[$suboptions[$k_sub]['step']];

					$rf_ea = $this->refundmodel->get_refund_suboption_ea($item['item_seq'],$suboption['item_suboption_seq']);
					$step_complete = $this->ordermodel->get_suboption_export_complete($order_seq,$option['shipping_provider_seq'],$item['item_seq'],$suboption['item_suboption_seq']);

					if($this->cfg_order['cancelDisabledStep35'] == '1'){
						$step_complete	+= $suboptions[$k_sub]['step35'];
					}

					$suboptions[$k_sub]['able_refund_ea'] = $suboption['ea'] - $rf_ea - $step_complete;

					$tot['ea'] += $suboption['ea'];
				}
				if($suboptions) $options[$k]['suboptions'] = $suboptions;

				$options[$k]['inputs']	= $this->ordermodel->get_input_for_option($options[$k]['item_seq'], $options[$k]['item_option_seq']);
			}


			$items[$key]['options'] = $options;

			$loop[$item['shipping_seq']]['items'][$item['item_seq']] = $item;
			$loop[$item['shipping_seq']]['items'][$item['item_seq']]['options'] = $options;
			$loop[$item['shipping_seq']]['items'][$item['item_seq']]['suboptions'] = $suboptions;

		}

		foreach($loop as $shipping_seq=>$v){
			$shipping = $this->ordermodel->get_order_shipping($orders['order_seq'], null, $shipping_seq);
			$shipping_methods = array_keys($shipping);

			$loop[$shipping_seq]['shipping'] = $shipping[$shipping_methods[0]];
			$loop[$shipping_seq]['shipping_provider'] = $this->providermodel->get_provider($shipping[$shipping_methods[0]]['provider_seq']);
			$loop[$shipping_seq]['return_address'] = $this->providermodel->get_provider_return_address($shipping[$shipping_methods[0]]['provider_seq']);
		}

		$orders['kspay_authty']	= '1010';	// KSPAY - 신용카드
		if		($orders['payment'] == 'account')
			$orders['kspay_authty']	= '2010';	// KSPAY - 계좌이체
		elseif	($orders['payment'] == 'cellphone')
			$orders['kspay_authty']	= 'M110';	// KSPAY - 휴대폰
		
		// 비현금성 주문일 경우 환불방법 미노출 처리 (신용카드, 휴대폰결제) :: 2018-07-20 pjw
		$show_refund_method = 'Y';
		if (in_array($orders['payment'], ['card', 'kakaomoney', 'cellphone'])){
			$show_refund_method = 'N';
		}

		// 간편결제 환불방법 미노출 처리
		if (is_order_simple_payment($orders)) {
			$show_refund_method = 'N';
		}

		$orders['show_refund_method'] = $show_refund_method;

		$this->template->assign(array('pg'				=> $pg));
		$this->template->assign(array('orders'			=> $orders));
		$this->template->assign(array('loop'			=> $loop));
		$this->template->assign(array('items'			=> $items));
		$this->template->assign(array('items_tot'		=> $tot));
		$this->template->assign(array('order_total_ea'	=> $order_total_ea));

		if($_GET['use_layout']){
			$this->print_layout($this->template_path());
		}else{
			$this->template->define(array('tpl'=>$this->template_path()));
			$this->template->print_("tpl");
		}
	}

	//반품 or 맞교환 -> 환불
	public function order_return(){
		$sSessionOrder	= $this->session->userdata('sess_order');
		$sMemberSeq		= (int) $this->userInfo['member_seq'];
		$order_seq		= $_POST['order_seq'] ? (int) $_POST['order_seq'] : (int) $_GET['order_seq'];
		$type			= $_POST['type'] ? $_POST['type'] : $_GET['type'];
		$mode			= $_POST['mode'] ? $_POST['mode'] : $_GET['mode'];


		if(!$sSessionOrder && !$sMemberSeq) {
			//잘못된 접근입니다.
			pageBack(getAlert('os216'));
			exit;
		}

		if( $order_seq && $sSessionOrder && $order_seq != $sSessionOrder ){
			//잘못된 접근입니다.
			pageBack(getAlert('os216'));
			exit;
		}

		$this->load->model('ordermodel');
		$this->load->model('returnmodel');
		$this->load->model('refundmodel');
		$this->load->model('goodsmodel');
		$this->load->model('exportmodel');
		$this->load->model('providermodel');
		$this->load->model('shippingmodel');

		if(!$this->arr_step)	$this->arr_step = config_load('step');
		$able_steps	= $this->ordermodel->able_step_action['return_list'];

		if( $sMemberSeq && $order_seq ){
			$orders 			= $this->ordermodel->get_order($order_seq, array("member_seq"=>$sMemberSeq));
		}else if($sSessionOrder){
			$orders 			= $this->ordermodel->get_order($sSessionOrder);
		}

		if( $sMemberSeq && $sMemberSeq!=$orders['member_seq'] ){
			//잘못된 접근입니다.
			pageBack(getAlert('os216'));
			exit;
		}

		if($orders['step'] == 0 || $orders['hidden']=='Y' || $orders['hidden']=='T'){
			//잘못된 접근입니다.
			pageBack(getAlert('os216'));
			exit;
		}

		if( strstr($orders['recipient_zipcode'],'-') )
			$orders['recipient_new_zipcode']	= str_replace("-","",$orders['recipient_zipcode']);
		else
			$orders['recipient_new_zipcode'] 	= $orders['recipient_zipcode'];
		if($orders['order_phone'])		$orders['order_phone']		= explode('-',$orders['order_phone']);
		if($orders['order_cellphone'])	$orders['order_cellphone']	= explode('-',$orders['order_cellphone']);

		$items 		= $this->ordermodel->get_item($orders['order_seq']);

		// 사유코드
		$reasons	= code_load('return_reason');
		$reasonLoop	= $this->returnmodel->get_return_reason($_GET['mode']);
		$this->template->assign(array('reasonLoop'=> $reasonLoop, 'reasons' => $reasons));

		// 계좌설정 정보
		$bank = $payment = $escrow = "";
		$aBanks = config_load('bank');
		if($aBanks) foreach($aBanks as $sKeyBank => $sValBank){
			list($sValBank['bank'])	= code_load('bankCode', $sValBank['bank']);
			$bank[]					= $sValBank;
			if( $sValBank['accountUse'] == 'y' )	$payment['bank']	= true;
		}
		$this->template->assign(array('bank'	=> $bank));

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
		$loop		= array();
		$cfg_order	= config_load('order');

		// 출고정보
		$exports = $this->exportmodel->get_export_for_order($orders['order_seq']);

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
				$data['out_event_sale']  = $data['event_sale'];
				$data['out_multi_sale']  = $data['multi_sale'];
				$data['out_member_sale'] = $data['member_sale']*$data['ea'];
				$data['out_coupon_sale'] = ($data['download_seq'])?$data['coupon_sale']:0;
				$data['out_fblike_sale'] = $data['fblike_sale'];
				$data['out_mobile_sale'] = $data['mobile_sale'];
				$data['out_promotion_code_sale'] = $data['promotion_code_sale'];
				$data['out_referer_sale'] = $data['referer_sale'];

				// 할인 합계
				// 이벤트, 복수구매 추가 2018-07-13 pjw
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

				$tot['event_sale']			+= $data['out_event_sale'];
				$tot['multi_sale']			+= $data['out_multi_sale'];
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
				$able_return_ea += (int) $data['step75'] - (int) $return_item['ea'];

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
					// 이벤트, 복수구매 추가 2018-07-13 pjw
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
				}
			}
		}

		## 사은품 반품 가능 수량
		$gift_cnt = 0;

		foreach( $exports as $k => $data_export ){


			$data_export['item'] =  $this->exportmodel->get_export_item($data_export['export_code']);

			foreach($data_export['item'] as $i=>$data){

				if ( ($data['goods_kind'] != 'coupon' && $_GET['mode'] == 'return_coupon') || ($data['goods_kind'] == 'coupon' && $_GET['mode'] != 'return_coupon')  ) continue;//티켓상품 반품/맞교환 제외@2013-11-12

				$data['export_code'] = $data_export['export_code'];
				$data['reasons'] = $reasons;
				$data['reasonLoop'] = $reasonLoop;
				$data['mstep'] = $this->arr_step[$data['step']];

				$data['out_supply_price'] = $data['supply_price']*$data['ea'];
				$data['out_consumer_price'] = $data['consumer_price']*$data['ea'];
				$data['out_price'] = $data['price']*$data['ea'];

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

				if(!$return_item['ea']) $return_item['ea'] = 0;

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
						$data['socialcp_return_disabled'] = true;
					}else{
						if( date("Ymd")>substr(str_replace("-","",$data['social_end_date']),0,8) ) {//유효기간 종료 후 마일리지환불 신청가능여부
							$orders['socialcp_valid_coupons'] = true;
							if( $data['socialcp_use_return'] == 1) {//미사용티켓 환불대상
								if( order_socialcp_cancel_return($data['socialcp_use_return'], $data['coupon_value'], $data['coupon_remain_value'], $data['social_start_date'], $data['social_end_date'] , $data['socialcp_use_emoney_day'] ) === true ) {
									//미사용티켓여부 잔여값어치합계 ==>> 구매금액 % 환불 @2014-10-07
									$data['coupon_refund_type']		= 'price';
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
									$coupon_deduction_price		= (int) ($coupon_real_total_price) - $coupon_remain_price;
									$coupon_refund_emoney		= $coupon_remain_price;//이전스킨적용
									//$cancel_total_price  += $coupon_remain_price;//취소총금액
								}else{
									$data['rt_ea'] = 0;
									$data['socialcp_return_disabled'] = true;
								}
							}else{
								$data['rt_ea'] = 0;
								$data['socialcp_return_disabled'] = true;
							}
						}else{//유효기간 이전
							if( $data['coupon_remain_value'] >0 ) {//잔여값어치가 남아있을때에만 =>> 구매금액 % 환불 @2014-10-07
								if( $data['coupon_value'] != $data['coupon_remain_value'] && $data['socialcp_cancel_use_refund'] == '1' ) {
									//부분 사용한 쿠폰은 취소(환불) 불가 @2014-10-07
									$data['rt_ea'] = 0;
									$data['socialcp_return_disabled'] = true;
								}else{
									list($data['socialcp_refund_use'], $data['socialcp_refund_cancel_percent']) = order_socialcp_cancel_refund(
										$orders['order_seq'],
										$data['item_seq'],
										$orders['deposit_date'],
										$data['social_start_date'],
										$data['social_end_date'],
										$data['socialcp_cancel_payoption'],
										$data['socialcp_cancel_payoption_percent']
									);//취소(환불) 가능여부

									if( $data['socialcp_refund_use'] === true ) {//취소(환불) 100% 또는 XX% 공제
										if( $data['coupon_value'] == $data['coupon_remain_value'] ) {//전체체크
											//실제결제금액
											$coupon_remain_price			= (int) ($data['socialcp_refund_cancel_percent'] * $coupon_real_total_price / 100);
											$coupon_deduction_price	= (int) $coupon_real_total_price - $coupon_remain_price;
											$coupon_remain_real_percent = "100";
											$coupon_remain_real_price = $coupon_real_total_price;
											$data['coupon_refund_type']	= 'price';
											$cancel_total_price  += $coupon_remain_price;//취소총금액
										}else{
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

											$coupon_remain_price			= (int) ($data['socialcp_refund_cancel_percent'] * ($coupon_remain_real_price) / 100);
											$coupon_deduction_price	= (int) ($coupon_real_total_price) - $coupon_remain_price;
											//$cancel_total_price  += $coupon_remain_price;//취소총금액
										}
										$data['coupon_refund_type']		= 'price';
									}else{
										$data['rt_ea'] = 0;
										$data['socialcp_return_disabled'] = true;
									}
								}
							}else{
								$data['rt_ea'] = 0;
								$data['socialcp_return_disabled'] = true;
							}
						}
					}

					$cancel_memo = socialcp_cancel_memo($data, $coupon_remain_real_percent, $coupon_real_total_price, $coupon_remain_real_price, $coupon_remain_price, $coupon_deduction_price);

					$data['coupon_refund_emoney']		= $coupon_refund_emoney;//쿠폰 잔여 값어치의 실제금액
					$data['coupon_remain_price']			= $coupon_remain_price;//쿠폰 결제금액의 실제금액
					$data['coupon_deduction_price']		= $coupon_deduction_price;//쿠폰 결제금액의 조정금액
					$data['cancel_memo']						= $cancel_memo;//취소(환불) 상세내역
				}else{
					/* 실물상품 반품신청 가능 기간 체크 @2016-11-17 */
					if($cfg_order['buy_confirm_use']){
						// 구매확정 사용시 출고완료일 후 n일 내에만 반품신청 가능
						$order_return_sdate = date('Ymd',strtotime('+'.$cfg_order['save_term'].' day',strtotime($data_export['complete_date'])));
						if($data_export['complete_date'] && date('Ymd')>$order_return_sdate){
							$data['rt_ea'] = 0;
						}
					}else{
						// 구매확정 미사용시 배송완료 후 {n}일 이내에만 반품/맞교환 가능
						$order_return_edate = date('Ymd',strtotime('+'.$cfg_order['save_term'].' day',strtotime($data_export['shipping_date'])));
						if($data_export['shipping_date'] != '0000-00-00' && date('Ymd') > $order_return_edate ) {
							$data['rt_ea'] = 0;
						}
					}

					$goodstotal++;
				}

				# 구매확정 사용시 : 지급예정수량(출고수량-지급예정반품수량-지급수량-소멸수량)
				if($cfg_order['buy_confirm_use'] && $data['reserve_ea']==0) $data['rt_ea'] = 0;

				//청약철회상품체크
				unset($goods);
				$goods = $this->goodsmodel->get_goods($data['goods_seq']);
				$data['cancel_type'] = $goods['cancel_type'];
				if( $data['cancel_type'] == 1 )$data['rt_ea'] = 0;

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

				$ex_code_shipping_provider_seq = $data_export['item'][0]['shipping_provider_seq']."_".$data_export['item'][0]['export_code'];

				$loop[$ex_code_shipping_provider_seq]['export_item'][] = $data;
				$loop[$ex_code_shipping_provider_seq]['tot_rt_ea'] += $data['rt_ea'];
			}
		}

		if ( $_GET['mode'] == 'return_coupon' ) {
			if (!$coupontotal || empty($coupontotal) ){
				$this->template->assign('backalert',true);
				//환불신청 티켓상품이 없습니다.!
				$msg = getAlert('mo076');
				if($_GET['use_layout']){
					pageBack($msg);exit;
				}else{
					echo js('alert("'.$msg.'");setTimeout(function(){closeDialog("order_refund_layer");},0);');//exit;
				}
			}
		}elseif( !$goodstotal || empty($goodstotal) ) {
				$this->template->assign('backalert',true);
				if($_GET['mode'] == 'exchange') {
					//맞교환신청 상품이 없습니다!
					$msg = getAlert('mo077');
				}else{
					//반품신청 상품이 없습니다!
					$msg = getAlert('mo078');
				}
				if($_GET['use_layout']){
					pageBack($msg);exit;
				}else{
					echo js('alert("'.$msg.'");setTimeout(function(){closeDialog("order_refund_layer");},0);');exit;
				}
		}

		foreach($loop as $ex_code_shipping_provider_seq=>$v){
			list($shipping_provider_seq, $export_code) = explode("_",$ex_code_shipping_provider_seq);
			$grp_sql = "SELECT refund_address_seq,refund_scm_type FROM fm_shipping_grouping WHERE shipping_provider_seq = {$shipping_provider_seq} AND default_yn = 'Y' LIMIT 1";

			$grpping = $this->db->query($grp_sql);
			$grpping = $grpping->row_array();
			$grp_seq = $grpping['refund_address_seq'];
			$grp_scm_type = $grpping['refund_scm_type'];
			$address = $this->shippingmodel->get_shipping_address($grp_seq, $grp_scm_type);

			$return_zipcode = $return_address = '';

			if($address['address_street']){
				$return_zipcode = $address['address_zipcode'];
				$return_address = $address['address_street'];
				$deli_address1	= $address['address_street'];
			}else{
				$return_zipcode = $address['address_zipcode'];
				$return_address = $address['address'];
				$deli_address1	= $address['address'];
			}
			$return_address .= " ".$address['address_detail'];
			$deli_address2	= $address['address_detail'];


			$loop[$ex_code_shipping_provider_seq]['shipping_provider'] = $this->providermodel->get_provider($shipping_provider_seq);

			$loop[$ex_code_shipping_provider_seq]['shipping_provider']['deli_zipcode'] = $address['address_zipcode'];
			$loop[$ex_code_shipping_provider_seq]['shipping_provider']['deli_address1'] = $deli_address1;
			$loop[$ex_code_shipping_provider_seq]['shipping_provider']['deli_address2'] = $deli_address2;

			$loop[$ex_code_shipping_provider_seq]['return_zipcode'] = $return_zipcode;
			$loop[$ex_code_shipping_provider_seq]['return_address'] = $return_address;
		}

		// 비현금성 주문일 경우 환불방법 미노출 처리 (신용카드, 휴대폰결제) :: 2018-07-20 pjw
		$show_refund_method = 'Y';
		if (in_array($orders['payment'], ['card', 'kakaomoney', 'cellphone'])){
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
		$this->template->assign(array('mode'		=> $mode));
		$file_path = $this->template_path();
		if($_GET['mode'] == 'return_coupon') {//쿠폰 환불
			$file_path = str_replace('order_return','order_return_coupon',$file_path);
		}

		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		if(!$reserves['cash_use']) $reserves['cash_use'] = "N";
		$this->template->assign($reserves);

		if($_GET['use_layout']){
			$this->print_layout($file_path);
		}else{
			$this->template->define(array('tpl'=>$file_path));
			$this->template->print_("tpl");
		}
	}

	//board controller list
	public function _board_list($boardid)
	{
		define('BOARDID',$boardid);
		if( BOARDID == 'goods_qna' ) {
			$this->load->model('Goodsqna','Boardmodel');
		}elseif( BOARDID == 'goods_review' ) {
			$this->load->model('Goodsreview','Boardmodel');
		}elseif( BOARDID == 'bulkorder' ) {//대량구매게시판
			$this->load->model('Boardbulkorder','Boardmodel');
		}else{
			$this->load->model('Boardmodel');
		}
		$sql['whereis']	= ' and id= "'.$boardid.'" ';
		$sql['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sql);//게시판정보
		getboardicon();//icon setting
		$this->template->assign('manager',$this->manager);
		$this->lists('mypage');
	}

	//board controller view
	protected function _board_view($boardid)
	{
		define('BOARDID',$boardid);
		if( BOARDID == 'goods_qna' ) {
			$this->load->model('Goodsqna','Boardmodel');
		}elseif( BOARDID == 'goods_review' ) {
			$this->load->model('Goodsreview','Boardmodel');
		}elseif( BOARDID == 'bulkorder' ) {//대량구매게시판
			$this->load->model('Boardbulkorder','Boardmodel');
		}else{
			$this->load->model('Boardmodel');
		}

		$sql['whereis']	= ' and id= "'.$boardid.'" ';
		$sql['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sql);//게시판정보
		getboardicon();//icon setting
		$this->template->assign('manager',$this->manager);
		$this->view('mypage');
	}

	//board controller write
	protected function _board_write($boardid)
	{
		define('BOARDID',$boardid);
		if( BOARDID == 'goods_qna' ) {
			$this->load->model('Goodsqna','Boardmodel');
		}elseif( BOARDID == 'goods_review' ) {
			$this->load->model('Goodsreview','Boardmodel');
		}elseif( BOARDID == 'bulkorder' ) {//대량구매게시판
			$this->load->model('Boardbulkorder','Boardmodel');
		}else{
			$this->load->model('Boardmodel');
		}

		$sql['whereis']	= ' and id= "'.$boardid.'" ';
		$sql['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sql);//게시판정보
		getboardicon();//icon setting
		$this->template->assign('manager',$this->manager);
		$this->write('mypage');
	}

	public function myqna_catalog()
	{
		login_check();
		$this->boardurl = $this->myqna->boardurl;
		$this->_board_list($this->myqnatbl);
		$this->template->assign('boardurl',$this->boardurl);//link url
		$this->print_layout($this->template_path());
	}

	public function myqna_view()
	{
		login_check();
		$this->boardurl = $this->myqna->boardurl;
		$this->_board_view($this->myqnatbl);
	}

	public function myqna_write()
	{
		login_check();
		$this->boardurl = $this->myqna->boardurl;
		$this->_board_write($this->myqnatbl);
		$this->template->assign('boardurl',$this->boardurl);//link url
	}

	public function mygdqna_catalog()
	{
		login_check();
		$this->boardurl = $this->mygdqna->boardurl;
		$this->_board_list($this->mygdqnatbl);
		$this->template->assign('boardurl',$this->boardurl);//link url
		$this->print_layout($this->template_path());
	}

	public function mygdqna_view()
	{
		login_check();
		$this->boardurl = $this->mygdqna->boardurl;
		$this->_board_view($this->mygdqnatbl);
	}

	public function mygdqna_write()
	{
		login_check();
		$this->boardurl = $this->mygdqna->boardurl;
		$this->_board_write($this->mygdqnatbl);
		$this->template->assign('boardurl',$this->boardurl);//link url
	}

	/* 매장용 스킨 추가 작업본 2013-11-20 이원희 */
	public function myreview_catalog()
	{
		login_check();
		$this->boardurl = $this->myreview->boardurl;
		$this->_board_list($this->myreviewtbl);
		$this->template->assign('boardurl',$this->boardurl);//link url
		$this->print_layout($this->template_path());
	}

	public function myreview_view()
	{
		login_check();
		$this->boardurl = $this->myreview->boardurl;
		$this->_board_view($this->myreviewtbl);
	}

	public function myreview_write()
	{
		login_check();
		$this->boardurl = $this->myreview->boardurl;
		$this->_board_write($this->myreviewtbl);
		$this->template->assign('boardurl',$this->boardurl);//link url
	}

	public function myreserve_catalog()
	{
		login_check();
		$this->boardurl = $this->myreserve->boardurl;
		$this->_board_list($this->myreservetbl);
		$this->template->assign('boardurl',$this->boardurl);//link url
		$this->print_layout($this->template_path());
	}

	public function myreserve_view()
	{
		login_check();
		$this->boardurl = $this->myreserve->boardurl;
		$this->_board_view($this->myreservetbl);
	}

	public function myreserve_write()
	{
		login_check();
		$this->boardurl = $this->myreserve->boardurl;
		$this->_board_write($this->myreservetbl);
		$this->template->assign('boardurl',$this->boardurl);//link url
	}
	/* 매장용 스킨 추가 작업본 End */

	public function mygdreview_catalog()
	{
		login_check();
		$this->boardurl = $this->mygdreview->boardurl;
		//$this->_board_list($this->mygdreviewtbl);
		$this->template->assign('boardurl',$this->boardurl);//link url

		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');//마일리지자동지급관련
		if( $reserves['autoemoney'] == 1 &&  $reserves['autoemoneytype'] != 3 ){
			if( $reserves['autoemoney_video'] > 0 || $reserves['autoemoney_photo'] > 0  || $reserves['autoemoney_review'] > 0 || $reserves['autopoint_video'] > 0 || $reserves['autopoint_photo'] > 0  || $reserves['autopoint_review'] > 0 ) {
				$reserves['autoemoneytitle'] = true;
			}
		}

		$this->template->assign('reserves',$reserves);
		$this->_board_list($this->mygdreviewtbl);
		$this->print_layout($this->template_path());
	}

	public function mygdreview_view()
	{
		login_check();
		$this->boardurl = $this->mygdreview->boardurl;
		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');//마일리지자동지급관련
		if( $reserves['autoemoney'] == 1 &&  $reserves['autoemoneytype'] != 3 ){
			if( $reserves['autoemoney_video'] > 0 || $reserves['autoemoney_photo'] > 0  || $reserves['autoemoney_review'] > 0 || $reserves['autopoint_video'] > 0 || $reserves['autopoint_photo'] > 0  || $reserves['autopoint_review'] > 0 ) {
				$reserves['autoemoneytitle'] = true;
			}
		}
		$this->template->assign('reserves',$reserves);
		$this->template->assign('boardurl',$this->boardurl);//link url
		$this->_board_view($this->mygdreviewtbl);
	}


	public function mygdreview_write()
	{
		if(!$this->userInfo['member_seq']){
			if($this->session->userdata('sess_order')) {//비회원 주문조회후 상품평 등록
				secure_vulnerability('board', 'goods_seq', $_GET['goods_seq']);
				secure_vulnerability('board', 'order_seq', $_GET['order_seq']);
				$_GET['goods_seq'] = (int) $_GET['goods_seq'];
				$_GET['order_seq'] = (int) $_GET['order_seq'];
				redirect("/board/write?id=goods_review&goods_seq=".$_GET['goods_seq']."&order_seq=".$_GET['order_seq']);
				exit;
			}else{
				login_check();
			}
		}
		$this->boardurl = $this->mygdreview->boardurl;
		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');//마일리지자동지급관련
		if( $reserves['autoemoney'] == 1 &&  $reserves['autoemoneytype'] != 3 ){
			if( $reserves['autoemoney_video'] > 0 || $reserves['autoemoney_photo'] > 0  || $reserves['autoemoney_review'] > 0 || $reserves['autopoint_video'] > 0 || $reserves['autopoint_photo'] > 0  || $reserves['autopoint_review'] > 0 ) {
				$reserves['autoemoneytitle'] = true;
			}
		}
		$this->template->assign('reserves',$reserves);
		$this->template->assign('boardurl',$this->boardurl);//link url
		$this->_board_write($this->mygdreviewtbl);
	}

	//세금계산서내역
	public function taxinvoice()
	{
		login_check();

		$this->load->model('salesmodel');
		$this->load->model('ordermodel');
		/**
		 * list setting
		**/
		$sc = $this->input->get();
		$sc['orderby'] = (!empty($sc['orderby'])) ? $sc['orderby'] : 'seq DESC';
		$sc['page'] = (!empty($sc['page'])) ? intval($sc['page']) : '0';
		$sc['perpage'] = (!empty($sc['perpage'])) ? intval($sc['perpage']) : '10';
		$sc['member_seq']	= $this->userInfo['member_seq'];

		$data = $this->salesmodel->sales_tax_list($sc);//게시글목록

		/**
		 * count setting
		**/
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = @ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $this->salesmodel->get_item_total_count($sc);

		$idx = 0;
		foreach($data['result'] as $datarow){$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			$datarow['tstep'] = $datarow['tstep'];
			if($datarow['tstep']=='1')
			{
				$datarow['cash_msg'] = "발급신청";
			}
			else if($datarow['tstep']=='2')
			{
				$datarow['cash_msg'] = "발급완료";
			} else if($datarow['tstep']=='3')
			{
				$datarow['cash_msg'] = "발급취소";
			} else if($datarow['tstep']=='4')
			{
				$datarow['cash_msg'] = "발급실패";
			}
			//deposit_date 10일 +10 day
			$datarow['taxwriteuse'] = ( date("Ymd",strtotime("+10 day ".$datarow['deposit_date'])) < date("Ymd") ) ? false:true;//입금일로부터 10일까지만

			$items = $this->ordermodel->get_item($datarow['order_seq']);
			$datarow['goods_name'] = ( count($items) > 1 ) ? $items[0]['goods_name'] ."외".(count($items)-1)."건":$items[0]['goods_name'];
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtagfront($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);
		$this->print_layout($this->template_path());
	}

	public function taxwrite()
	{

		$this->load->model('salesmodel');
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		if(!$this->arr_step)	$this->arr_step = config_load('step');
		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		if(!$this->cfg_order)	$this->cfg_order = config_load('order');
		$this->template->assign(array('cfg_order'	=> $this->cfg_order));
		$_POST['order_seq']		= (int) $_POST['order_seq'];
		$orders		= $this->ordermodel->get_order($_POST['order_seq']);
		$items 		= $this->ordermodel->get_item($_POST['order_seq']);

		// O2O 주문건 리턴 (단, 스킨패치 이후만 체킹함)
		// $this->input->post('request_mode') 로 스킨패치 여부 판단
		if($orders['linkage_id'] == 'pos' && $this->input->post('request_mode') == 'js') {
			$return = array('result'=>false, 'msg'=>'매장주문은 세금계산서를 신청할 수 없습니다.');
			echo json_encode($return);
			exit;
		}

		foreach($items as $key=>$item){
			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
			$suboptions = $this->ordermodel->get_suboption_for_item($item['item_seq']);

			if($options) foreach($options as $k=>$data){
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

				//sale 7가지
				$data['out_event_sale']			= $data['event_sale'];
				$data['out_multi_sale']			= $data['multi_sale'];
				$data['out_member_sale']		= $data['member_sale']*$data['ea'];
				$data['out_coupon_sale']		= ($data['download_seq'])?$data['coupon_sale']:0;
				$data['out_fblike_sale']		= $data['fblike_sale'];
				$data['out_mobile_sale']		= $data['mobile_sale'];
				$data['out_referer_sale']		= $data['referer_sale'];
				$data['out_promotion_code_sale']= $data['promotion_code_sale'];

				// 이벤트, 복수구매 추가 2018-07-13 pjw
				$data['out_tot_sale']	= $data['out_event_sale'];
				$data['out_tot_sale']	+= $data['out_multi_sale'];
				$data['out_tot_sale']	+= $data['out_member_sale'];
				$data['out_tot_sale']	+= $data['out_coupon_sale'];
				$data['out_tot_sale']	+= $data['out_fblike_sale'];
				$data['out_tot_sale']	+= $data['out_mobile_sale'];
				$data['out_tot_sale']	+= $data['out_promotion_code_sale'];
				$data['out_tot_sale']	+= $data['out_referer_sale'];

				$data['out_reserve']			= $data['reserve']*$data['ea'];
				$data['out_point']				= $data['point']*$data['ea'];
				$data['step_complete']			= $data['step45']+$data['step55']+$data['step65']+$data['step75'];
				$options[$k] = $data;

				$tot['ea']						+= $data['ea'];
				$tot['supply_price']			+= $data['out_supply_price'];
				$tot['consumer_price']			+= $data['out_consumer_price'];
				$tot['price']					+= $data['out_price'];

				$tot['event_sale']				+= $data['out_event_sale'];
				$tot['multi_sale']				+= $data['out_multi_sale'];
				$tot['member_sale']				+= $data['out_member_sale'];
				$tot['coupon_sale']				+= $data['out_coupon_sale'];
				$tot['fblike_sale']				+= $data['out_fblike_sale'];
				$tot['mobile_sale']				+= $data['out_mobile_sale'];
				$tot['referer_sale']			+= $data['out_referer_sale'];
				$tot['promotion_code_sale']		+= $data['out_promotion_code_sale'];

				$tot['reserve']					+= $data['out_reserve'];
				$tot['point']					+= $data['out_point'];
				$tot['real_stock']				+= $real_stock;
				$tot['stock']					+= $stock;

				if($suboptions) foreach($suboptions as $z => $data_sub){

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
				}
				$data['suboptions']			= $suboptions;
				$options[$k]				= $data;

				$item['tot_goods_cnt']		+= count($suboptions) + 1;
			}

			$item['options']			= $options;
			$items[$key] 				= $item;
			$tot['goods_shipping_cost']	+= $item['goods_shipping_cost'];

		}

		/* 주문상품을 배송그룹별로 분할 */
		$shipping_group_items=array();
		foreach($items as $item){

			$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost_sum']+= $item['goods_shipping_cost'];
			$shipping_group_items[$item['shipping_seq']]['rowspan'] += $item['tot_goods_cnt'];
			$shipping_group_items[$item['shipping_seq']]['items'][] = $item;
			$shipping_group_items[$item['shipping_seq']]['totalitems'] += $item['totaloptitems'];
		}

		$this->load->helper('shipping');
		$arr_shipping_method = get_shipping_method('all');
		foreach($shipping_group_items as $shipping_seq=>$row){
			$query = $this->db->query("select a.*, b.provider_name
			from fm_order_shipping a
			inner join fm_provider b on a.provider_seq = b.provider_seq
			where a.shipping_seq=?",$shipping_seq);
			$shipping = $query->row_array();
			$shipping['shipping_method_name'] = $arr_shipping_method[$shipping['shipping_method']];
			$shipping_group_items[$shipping_seq]['shipping'] = $shipping;

			if($shipping['shipping_method']=='delivery'){
				$shipping_tot['basic_cost']				+= $shipping['delivery_cost'];
				$shipping_tot['add_shipping_cost']		+= $shipping['add_delivery_cost'];
				$shipping_tot['shipping_cost']			+= $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
			}

			if($shipping['shipping_method']=='each_delivery'){
				$shipping_tot['goods_cost']				+= $shipping['delivery_cost'];
				$shipping_tot['add_shipping_cost']		+= $shipping['add_delivery_cost'];
				$shipping_tot['goods_shipping_cost']	+= $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
			}
			$shipping_tot['coupon_sale']		+= $shipping['shipping_coupon_sale'];
			$shipping_tot['code_sale']			+= $shipping['shipping_promotion_code_sale'];
		}
		$shipping_tot['total_shipping_cost']		= $shipping_tot['basic_cost'] + $shipping_tot['goods_cost'] + $shipping_tot['add_shipping_cost'];

		$this->template->assign(array('shipping_tot'=> $shipping_tot));
		$this->template->assign(array('shipping_group_items'=> $shipping_group_items));

		$orders['mpayment'] = $this->arr_payment[$orders['payment']];
		$orders['mstep'] 	= $this->arr_step[$orders['step']];

		$this->template->assign(array('orders'		=> $orders));
		$this->template->assign(array('items'		=> $items));
		$this->template->assign(array('items_tot'	=> $tot));

		$_POST['tax_seq']		= (int) $_POST['tax_seq'];
		$sc['whereis']	= ' and typereceipt = 1 and seq="'.$_POST['tax_seq'].'" ';
		$sc['select']		= ' * ';
		$taxs 		= $this->salesmodel->get_data($sc);

		$zipcodear[0] = substr(str_replace("-", "", $taxs['zipcode']),0,3);
		$zipcodear[1] = substr(str_replace("-", "", $taxs['zipcode']),3,3);
		$taxs['new_zipcode'] = $taxs['zipcode'];
		$taxs['zipcode0'] = $zipcodear[0];
		$taxs['zipcode1'] = $zipcodear[1];

		if(!$taxs['bperson']) $taxs['bperson'] = $taxs['person'];
		if(!$taxs['bphone']) $taxs['bphone'] = $taxs['phone'];

		$this->template->assign(array('tax'		=> $taxs));

		$thisfile = str_replace('settle_coupon','_coupon',$this->template_path());
		$this->template->define('*', $this->template_path());
		$html = '';
		$html = $this->template->fetch('*');
		$return = array('taxwrite'=>$html);
		echo json_encode($return);
		exit;
	}

	public function tax_receipt_view()
	{
		$this->load->model('salesmodel');

		$tax_receipt_row = $this->salesmodel->get_data(array(
			'select'=>'`co_name`,`person`,`busi_no`,`email`,`phone`,`co_ceo`,`zipcode`,`address_type`,`address`,`address_detail`,`address_street`,`co_status`,`co_type`',
			'whereis'=>" AND `order_seq`='".$_POST['order_seq']."' AND `seq`='".$_POST['seq']."'"
		));
		$this->template->assign(array('tax_receipt_row' => $tax_receipt_row));
		$this->template->define('*', $this->template_path());

		$html = $this->template->fetch('*');
		$return = array('tax_receipt_view'=>$html);
		echo json_encode($return);
		exit;
	}

	public function coupon()
	{
		login_check();
		$this->load->model('couponmodel');
		$this->load->model('ordermodel');
		$this->load->helper('coupon');
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');

		// 마이페이지 - 쿠폰 목록 처리
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->checkO2OCouponFilter = false;
		$this->o2oinitlibrary->init_front_mypage_coupon();

		if( !empty($this->mdata['birthday']) && $this->mdata['birthday'] != '0000-00-00' ) {
			$this->mdata['thisyear_birthday'] = date("Y").substr($this->mdata['birthday'],4,6);
			if(checkdate(substr($this->mdata['thisyear_birthday'],5,2),substr($this->mdata['thisyear_birthday'],8,2),substr($this->mdata['thisyear_birthday'],0,4)) != true) {
				$this->mdata['thisyear_birthday'] = date("Y-m-d",strtotime('-1 day', strtotime($this->mdata['thisyear_birthday'])));
			}
		}

		if ( !empty($this->mdata['anniversary']) ) {
			$this->mdata['thisyear_anniversary'] = date("Y").'-'.$this->mdata['anniversary'];//기념일(mm-dd) 추가
			if(checkdate(substr($this->mdata['thisyear_anniversary'],5,2),substr($this->mdata['thisyear_anniversary'],8,2),substr($this->mdata['thisyear_anniversary'],0,4)) != true) {
					$this->mdata['thisyear_anniversary'] = date("Y-m-d",strtotime('-1 day', strtotime($this->mdata['thisyear_anniversary'])));
			}
		}

		$mb_group_sort = mb_group_sort();//회원등급순서 재정렬
		//등급조정쿠폰의 등업된 경우에만 다운가능
		if ($this->mdata['grade_update_date'] != '0000-00-00 00:00:00') {
			$fm_member_group_logsql = "select * from fm_member_group_log where member_seq = '".$this->userInfo['member_seq']."' order by regist_date desc limit 0,1";
			$fm_member_group_logquery = $this->db->query($fm_member_group_logsql);
			$fm_member_group_log =  $fm_member_group_logquery->row_array();
			if( ($mb_group_sort[$fm_member_group_log['prev_group_seq']] >= $mb_group_sort[$fm_member_group_log['chg_group_seq']]) || ($this->userInfo['group_seq'] == 1) ) {
				$this->mdata['grade_update_date'] = '';
			}
		}else{
			$this->mdata['grade_update_date'] = substr($this->mdata['regist_date'],0,10);
		}

		###
		//쿠폰 다운내역/다운가능내역
		$sc['member_seq']	= $this->userInfo['member_seq'];
		down_coupon_list('mypage', $sc , $dataloop);//helper('coupon');

		$svcount = $this->couponmodel->get_download_have_total_count($sc,$this->mdata);
		$this->template->assign($svcount);
		###

		if(isset($dataloop)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtagfront($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?tab='.$_GET['tab'], getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);

		$this->template->define(array('coupon_top'=>$this->skin.'/mypage/coupon_top.html'));

		$this->print_layout($this->template_path());
	}

	//오프라인쿠폰 > 인증받기
	public function offlinecoupon()
	{
		login_check();
		$this->print_layout($this->template_path());
	}

	//마일리지내역
	public function emoney()
	{
		login_check();
		###
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['member_seq']	= $this->userInfo['member_seq'];
		$sc['perpage']			= '10';

		$data = $this->membermodel->emoney_list($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_emoney',array('member_seq'=>$member_seq));
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			if( !$this->isplusfreenot ) {//무료몰인경우 @2013-01-14
				$datarow['limit_date'] = '';
			}
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtagfront($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);

		$this->template->assign('tab', "emoney");

		$this->template->assign('userid', $this->mdata['userid'] );
		$this->template->assign('user_name', $this->mdata['user_name'] );
		$this->template->assign('emoney', $this->mdata['emoney']);
		$this->print_layout($this->template_path());
	}


	//마일리지내역
	public function cash()
	{
		login_check();
		if( !$this->isplusfreenot ){//무료몰인경우
			pageBack('잘못된 접근입니다.');
			exit;
		}
		###
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['member_seq']	= $this->userInfo['member_seq'];
		$sc['perpage']			= '10';

		$data = $this->membermodel->cash_list($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_cash',array('member_seq'=>$member_seq));
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			/**
			$datarow['contents'] = "";
			if($datarow['type']=='order'){
				$datarow['contents'] = "주문번호 ".$datarow['ordno'];
			}else if($datarow['type']=='join'){
				$datarow['contents'] = "회원가입";
			}else if($datarow['type']=='bookmark'){
				$datarow['contents'] = "즐겨찾기";
			}else if($datarow['type']=='refund'){
				$datarow['contents'] = "환불 ".$datarow['ordno'];
			}
			**/
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtagfront($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);

		$this->template->assign('tab', "cash");

		$this->template->assign('userid', $this->mdata['userid'] );
		$this->template->assign('user_name', $this->mdata['user_name'] );
		$this->template->assign('cash', $this->mdata['cash']);
		$this->print_layout($this->template_path());
	}

	public function point()
	{
		login_check();

		if( !$this->isplusfreenot ){//무료몰인경우
			pageBack('잘못된 접근입니다.');
			exit;
		}

		###
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['member_seq']	= $this->userInfo['member_seq'];
		$sc['perpage']			= '10';

		$data = $this->membermodel->point_list($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_point',array('member_seq'=>$member_seq));
		$idx = 0;
		foreach($data['result'] as $datarow){
			$idx++;
			$datarow['number']	= $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;
			/**
			$datarow['contents'] = "";
			if($datarow['type']=='order'){
				$datarow['contents'] = "주문번호 ".$datarow['ordno'];
			}else if($datarow['type']=='join'){
				$datarow['contents'] = "회원가입";
			}else if($datarow['type']=='bookmark'){
				$datarow['contents'] = "즐겨찾기";
			}else if($datarow['type']=='refund'){
				$datarow['contents'] = "환불 ".$datarow['ordno'];
			}
			**/
			$dataloop[] = $datarow;
		}

		###
		if(isset($data)) $this->template->assign('loop',$dataloop);
		$paginlay = pagingtagfront($sc['searchcount'],$sc['perpage'],$this->membermodel->admin_member_url($file_path).'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('perpage',$sc['perpage']);

		$this->template->assign('userid', $this->mdata['userid'] );
		$this->template->assign('user_name', $this->mdata['user_name'] );
		$this->template->assign('point', $this->mdata['point']);
		$this->print_layout($this->template_path());
	}

	/**
	* @ sns sns회원가입추가
	**/
	function Snsmyinfojoindb($memberseq) {
		$snswhere_arr = array('session_id' =>session_id());
		$snsjoinmbdata = get_data('fm_membersns_join', $snswhere_arr);
		$snsjoinck = $snsjoinmbdata[0];
		if($snsjoinck) {//있는 경우 업데이트
			$this->db->where('session_id',session_id());
			$this->db->update('fm_membersns_join', array("member_seq"=>$memberseq,"update_date"=>date('Y-m-d H:i:s')));
		}else{
			$snsjoinparams['member_seq']	= $memberseq;
			$snsjoinparams['session_id']		= session_id();
			$snsjoinparams['regist_date']		= date('Y-m-d H:i:s');
			$snsjoinparams['update_date']	= date('Y-m-d H:i:s');
			$data = filter_keys($snsjoinparams, $this->db->list_fields('fm_membersns_join'));
			$this->db->insert('fm_membersns_join', $data);
		}
	}


	public function myinfo()
	{
		login_check();
		###
		$mtype = 'member';
		if($this->mdata['business_seq']){
			$mtype = 'business';
		}
		###
		$email = code_load('email');
		$memberapproval = config_load('member');
		$policy_marketing = config_load('joinform', 'policy_marketing');
		$this->template->assign($policy_marketing);
		## sns 로그인 계정(2016-05-24)
		$snslogn = $this->session->userdata('snslogn');

		// 비밀번호 재확인 :: 2016-04-19 lwh
		if($_POST['pwchk']!='Y' && $memberapproval['confirmPW']=='Y' && !$snslogn){
			$file_path = $this->skin.'/member/register_gate.html';
			$this->print_layout($file_path);
			exit;
		}else{
			if($this->userInfo['member_seq'] != $_POST['chk_member_seq'] && $memberapproval['confirmPW']=='Y' && !$snslogn){
				//잘못된 접근입니다.
				echo("<script>
				alert('".getAlert('mp193')."');
				top.document.location.href='/';
				</script>");
				exit;
			}
		}

		for ($m=1;$m<=12;$m++){	$m_arr[] = str_pad($m, 2, '0', STR_PAD_LEFT); }
		for ($d=1;$d<=31;$d++){	$d_arr[] = str_pad($d, 2, '0', STR_PAD_LEFT); }
		$this->template->assign('m_arr',$m_arr);
		$this->template->assign('d_arr',$d_arr);

		//sns subdomain
		if ( $this->config_system['domain'] && $this->config_system['domain'] == $_SERVER['HTTP_HOST'] )
			$this->template->assign("isdomain",true);//정식도메인
		$this->template->assign('firstmallcartid',session_id());

		###

		//SNS계정사용을 위해 미리 세션굽기
		$this->Snsmyinfojoindb($this->mdata['member_seq']);

		//가입 추가 정보 리스트
		$mdata = $this->mdata;
		$qry = "select * from fm_joinform where used='Y' order by sort_seq";
		$query = $this->db->query($qry);
		$form_arr = $query -> result_array();
		foreach ($form_arr as $k => $data){
		$msubdata=$this->membermodel->get_subinfo($mdata['member_seq'],$data['joinform_seq']);
		$data['label_view'] = $this -> membermodel-> get_labelitem_type($data,$msubdata);
		$sub_form[] = $data;
		}
		$this->template->assign('form_sub',$sub_form);

		$this->mdata['mtype'] = $mtype;

		// 회원 이름명 OR 업체명 20자 제한
		$this->mdata['user_name'] = check_member_name($this->mdata['user_name']);
		$this->mdata['bname'] = check_member_name($this->mdata['bname']);

		if($memberapproval) $this->template->assign('memberapproval',$memberapproval);
		if($email) $this->template->assign('email_arr',$email);
		if($this->mdata['birthday'] == '0000-00-00') $this->mdata['birthday'] ='';
		if($this->mdata) $this->template->assign($this->mdata);

		$this->load->model('snsmember');
		$snsmbsc['select'] = ' * ';
		$snsmbsc['whereis'] = ' and member_seq = \''.$mdata['member_seq'].'\' ';
		$snslist = $this->snsmember->snsmb_list($snsmbsc);
		if($snslist['result'][0]) $this->template->assign(array('snslist'=>$snslist['result']));
		$this->template->assign('snstype',$this->snssocial->snstype);

		$member = config_load('member');
		$member['agreement'] = str_replace("{shopName}",$arrBasic['shopName'],$member['agreement']);
		$member['privacy'] = str_replace("{shopName}",$arrBasic['shopName'],$member['privacy']);
		$member['privacy'] = str_replace("{domain}",$arrBasic['domain'],$member['privacy']);

		//개인정보 관련 문구개선 @2016-09-06 ysm
		$member['privacy'] = str_replace("{책임자명}",$arrBasic['member_info_manager'],$member['privacy']);
		$member['privacy'] = str_replace("{책임자담당부서}",$arrBasic['member_info_part'],$member['privacy']);
		$member['privacy'] = str_replace("{책임자직급}",$arrBasic['member_info_rank'],$member['privacy']);
		$member['privacy'] = str_replace("{책임자연락처}",$arrBasic['member_info_tel'],$member['privacy']);
		$member['privacy'] = str_replace("{책임자이메일}",$arrBasic['member_info_email'],$member['privacy']);

		//개인정보 수집-이용
		$member['policy'] = str_replace("{domain}",$arrBasic['domain'],str_replace("{shopName}",$arrBasic['shopName'],$member['policy']));
		$this->template->assign($member);

		// 사용 중인 sns 가져오기 라이브러리에서 공용처리 :: 2020-03-11 pjw
		$joinform = $this->snssocial->joinform_usesns();
		if(!trim($this->arrSns['key_k'])){ $joinform['use_k'] = ""; }
		if($joinform) $this->template->assign('joinform',$joinform);

		// 현재 로그인 된 계정에 같이 연결된 sns 목록 가져옴 :: 2020-03-11 pjw
		$sns_joined_list = $this->membermodel->get_joined_sns_list($mdata['member_seq']);
		$this->template->assign('sns_joined_list', $sns_joined_list);

		## 로그인 수단 확인
		$fbuser		= $this->session->userdata("fbuser");
		$twuser		= $this->session->userdata("twuser");
		$nvuser		= $this->session->userdata("nvuser");
		$kkouser	= $this->session->userdata("kkouser");
		$apuser		= $this->session->userdata("apuser");

		// 현재 미사용되는 불필요로직으로 보임 :: 2020-03-11 pjw
//		$login_type = array();
//		if($joinform['use_k'] && $kkouser){ $login_type[] = "kakao"; }
//		if($joinform['use_f'] && $fbuser){	$login_type[] = "facebook"; }
//		if($joinform['use_t'] && $twuser){	$login_type[] = "twitter"; }
//		if($joinform['use_n'] && $nvuser){	$login_type[] = "naver"; }
//		if($joinform['use_a'] && $apuser){	$login_type[] = "apple"; }
//		if($login_type) $this->template->assign('login_type',$login_type);

		$this->template->assign('memberIcondata',memberIconConf());//회원아이콘
		$this->template->define(array('form_member'=>$this->skin.'/member/register_form.html'));
		$this->print_layout($this->template_path());
	}

	//초대하기
	public function myfbrecommend()
	{
		if($this->mdata['sns_f']){
			$fbuser_profile = $this->snssocial->facebooklogin();
		}

		if($this->session->userdata('fbuser')) {
			$this->template->assign('fbuser',$this->session->userdata('fbuser'));
			$fbpermissions = $this->snssocial->facebookpermissions($this->snssocial->facebook);
			if( !(array_key_exists('publish_actions', $fbpermissions['data'][0]) || in_array('publish_actions', $fbpermissions) ) ) {
				$this->template->assign('publish_stream',"publish_stream, publish_actions");
			}
		}

		$this->template->assign('fbuser',$this->session->userdata('fbuser'));
		login_check();

		if ( $this->config_system['domain'] && $this->config_system['domain'] == $_SERVER['HTTP_HOST'] )
			$this->template->assign("isdomain",true);//정식도메인

		$this->template->assign('firstmallcartid',session_id());

		$memberapproval = config_load('member');
		$memberapproval['emoneyTerm_invited_title'] = ( $memberapproval['emoneyTerm_invited'] == 'month' ) ? '월':'년';
		$memberapproval['emoneyLimit_invited_title'] = ( $memberapproval['emoneyLimit_invited']*$memberapproval['emoneyInvited'] );

		$this->template->assign('memberapproval',$memberapproval);

		$this->load->model('snsfbinvite');
		$totalinvitesc['whereis']	= ' and member_seq = \''.$this->userInfo['member_seq'].'\' ';
		$totalinvitesc['select']		= ' seq ';
		$totalinviteck = $this->snsfbinvite->get_data_numrow($totalinvitesc);
		$this->template->assign('totalinviteck', $totalinviteck );

		if($this->mdata) $this->template->assign($this->mdata);
		$this->print_layout($this->template_path());
	}

	public function withdrawal()
	{
		login_check();
		###
		$joinform = ($this->joinform)?$this->joinform:config_load('joinform');
		unset($joinform['use_y']);//폐지@2013-04-29
		if($joinform) $this->template->assign('joinform',$joinform);

		$this->load->model('snsmember');
		$snsmbsc['select'] = ' * ';
		$snsmbsc['whereis'] = ' and member_seq = \''.$this->userInfo['member_seq'].'\' ';
		$snslist[] = $this->snsmember->get_data($snsmbsc);
		if($snslist[0]) $this->template->assign(array('snslist'=>$snslist));

		/*
		 * 코드별 출력 내용(KR ORI)
		 * mp292: 배송 주문 불만족
		 * mp293: 사이트 이용 불편
		 * mp294: 상품품질 불만족
		 * mp295: 서비스 불만족
		 * mp291: 기타 / 기타 항목 마지막에 표시
		 */
		$withdrawal = array(
			array(
				'codecd'=>getAlert('mp292'),
				'value'=>getAlert('mp292'),
			),
			array(
				'codecd'=>getAlert('mp293'),
				'value'=>getAlert('mp293'),
			),
			array(
				'codecd'=>getAlert('mp294'),
				'value'=>getAlert('mp294'),
			),
			array(
				'codecd'=>getAlert('mp295'),
				'value'=>getAlert('mp295'),
			),
			array(
				'codecd'=>getAlert('mp291'),
				'value'=>getAlert('mp291'),
			)
		);

		$this->template->assign('withdrawal_arr',$withdrawal);
 		$this->print_layout($this->template_path());
	}
	// 위시리스트 담기
	public function wish_add(){

		// 로그인 체크
		$session_arr = ( $this->session->userdata('user') )?$this->session->userdata('user'):$_SESSION['user'];
		if(!$session_arr['member_seq']){
			$url = "/member/login?return_url=".urlencode($_SERVER["HTTP_REFERER"]);
			//회원만 사용가능합니다.\\n로그인하시겠습니까?
			echo("<script>
			parent.openDialogConfirm('".getAlert('gv009')."',400,140,function(){
				top.document.location.href='".$url."';
			},function(){});
			</script>");
			exit;
		}

		$this->load->model('wishmodel');
		$this->load->model('statsmodel');
		$this->load->model('goodsmodel');

		if($_GET['seqs']){

			/**
			* facebook  opengraph > love item
			**/
			if( $this->arrSns['facebook_interest'] == 'Y' && ($this->arrSns['key_f'] != '455616624457601' && $this->arrSns['facebook_publish_actions'] ) ) {//@2015-04-22 facebook version 2.* 권한 제한으로 publish_actions 값이 있을 때에만 적용
				if( $this->__APP_DOMAIN__ == $_SERVER['HTTP_HOST'] ) {
					foreach($_GET['seqs'] as $goods_seq){
						if($goods_seq){
							$goods_seq = (int) $goods_seq;
							echo("<script>
								parent.getfbopengraph('{$goods_seq}', 'interests', '{$_SERVER[HTTP_HOST]}','');
							</script>");
							//exit;
						}
					}
				}else{//전용앱이거나 앱적용도메인과 현재 접근도메인이 다른경우
					//if($this->session->userdata('fbuser')) {//페이스북회원인경우
						foreach($_GET['seqs'] as $goods_seq){
							if($goods_seq){
								$goods_seq = (int) $goods_seq;
								echo("<script>
									parent.getfbopengraph('{$goods_seq}', 'interests', '{$this->config_system[subDomain]}','');
								</script>");
								//exit;
							}
						}
					//}
				}
			}

			// 위시리스트 통계저장 추가 :: 2015-08-18 lwh
			foreach($_GET['seqs'] as $goods_seq){
				$goods_seq = (int) $goods_seq;
				## 위시리스트 통계 저장
				$goodsinfo = $this->goodsmodel->get_goods($goods_seq);
				$params['goods_seq']	= $goods_seq;
				$params['provider_seq']	= $goodsinfo['provider_seq'];
				$params['goods_name']	= $goodsinfo['goods_name'];
				$this->statsmodel->insert_wish_stats($params);
			}

			$this->wishmodel->add($_GET['seqs']);
			/* 사용안함
			if( $_GET['seqs'][0]['goods_seq'] ){
				$str_goods_seq = implode('|',array_map('intval', $_GET['seqs']));
				echo "<script>";
				echo "parent.statistics_firstmall('wish','".$str_goods_seq."','','');";
				echo "</script>";
			}
			*/
		}

		if($_GET['mode'] == 'cart'){
			if(!$_POST['cart_option_seq']){
				//상품을 선택해주세요.
				openDialogAlert(getAlert('mp042'),400,140,'parent','');
				exit;
			}

			$this->load->model('cartmodel');
			foreach($_POST['cart_option_seq'] as $cart_option_seq){
				$data_cart = $this->cartmodel->get_cart_by_cart_option($cart_option_seq);
				$goods_seq[] = $data_cart['goods_seq'];

				/**
				* facebook  opengraph > love item
				**/
				if( $this->arrSns['facebook_interest'] == 'Y' && ($this->arrSns['key_f'] != '455616624457601' && $this->arrSns['facebook_publish_actions'] ) ) {//@2015-04-22 facebook version 2.* 권한 제한으로 publish_actions 값이 있을 때에만 적용
					if( $this->__APP_DOMAIN__ == $_SERVER['HTTP_HOST'] ) {
						echo("<script>
							parent.getfbopengraph('{$data_cart[goods_seq]}', 'interests', '{$_SERVER[HTTP_HOST]}','');
							</script>");
							//exit;
					}else{//전용앱이거나 앱적용도메인과 현재 접근도메인이 다른경우
						echo("<script>
							parent.getfbopengraph('{$data_cart[goods_seq]}', 'interests', '{$this->config_system[subDomain]}','');
						</script>");
						//exit;
					}
				}

				## 위시리스트 통계 저장
				$goodsinfo = $this->goodsmodel->get_goods($data_cart['goods_seq']);
				$params['goods_seq']	= $data_cart['goods_seq'];
				$params['provider_seq']	= $goodsinfo['provider_seq'];
				$params['goods_name']	= $goodsinfo['goods_name'];
				$this->statsmodel->insert_wish_stats($params);
			}


			$this->wishmodel->add($goods_seq);
		}

		//상품이 wishlist에 담겼습니다.<br/><strong>지금 확인하시겠습니까?</strong>
		echo("<script>
		parent.openDialogConfirm('".getAlert('mp089')."',400,140,function(){
			top.document.location.href='/mypage/wish';
		},function(){top.getRightItemTotal('right_item_wish');history.back();});
		</script>");

	}

	public function wish_add_ajax_toggle(){

		if(!$this->userInfo['member_seq']){
			echo json_encode(array(
				'result' => 'not_login',
				'url' => "/member/login?return_url=".$_SERVER["HTTP_REFERER"]
			));
			exit;
		}else if($_GET['goods_seq']){
			secure_vulnerability('goods', 'no', $_GET['goods_seq']);
			$_GET['goods_seq'] = (int) $_GET['goods_seq'];
			$goods_seq = $_GET['goods_seq'];

			$this->load->model('wishmodel');
			$this->load->model('statsmodel');
			$this->load->model('goodsmodel');

			$query = "select * from fm_goods_wish where goods_seq=? and member_seq=?";
			$query = $this->db->query($query,array($goods_seq,$this->userInfo['member_seq']));
			$data = $query->row_array();

			if(!$data){
				## 위시리스트 통계 저장
				$goodsinfo = $this->goodsmodel->get_goods($goods_seq);
				$params['goods_seq']	= $goods_seq;
				$params['provider_seq']	= $goodsinfo['provider_seq'];
				$params['goods_name']	= $goodsinfo['goods_name'];
				$this->statsmodel->insert_wish_stats($params);

				$this->wishmodel->add(array($goods_seq));
				$type = 'add';
			}else{
				$this->wishmodel->del(array($data['wish_seq']),$goods_seq);
				$type = 'del';
			}

			$this->db->where(array('goods_seq'=>$goods_seq));
			$ret = $this->db->get('fm_goods');
			$ret = $ret->row_array();
			echo json_encode(array(
				'result'	=> $type,
				'goods_seq' => $goods_seq,
				'cnt'		=> $ret['wish_count']
			));
			exit;
		}

		echo json_encode(array());

	}

	// 위시리스트 삭제
	public function wish_del(){
		login_check();
		$this->load->model('wishmodel');
		if($_GET['seqs']){ // mobile_ver2 의 상품상세 위시 취소 버튼 2014-01-11 lwh
			$_GET['seqs']		= (int) $_GET['seqs'];
			$wish_seq = $this->wishmodel->confirm_wish($_GET['seqs']);
			if($wish_seq){
				$seqs[] = $wish_seq;
				$this->wishmodel->del($seqs);
				//취소되었습니다.
				openDialogAlert(getAlert('mp041'),400,140,'parent','history.back();');
			}
			exit;
		}
		if(!$_POST['wish_seq']){
			//상품을 선택해주세요.
			openDialogAlert(getAlert('mp042'),400,140,'parent','history.back();');
			exit;
		}
		if($_POST['wish_seq']){
			$this->wishmodel->del($_POST['wish_seq']);
			//위시리스트 상품을 삭제하였습니다.
			openDialogAlert(getAlert('mp043'),400,140,'parent',"parent.location.reload();");
			exit;
		}
		if($_GET['return_url']) pageRedirect($_GET['return_url'],'','parent');
		else pageRedirect('/mypage/wish','','parent');
	}

	// 위시리스트
	public function wish(){
		login_check();
		$this->load->model('wishmodel');
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->library('sale');
		$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');

		// 19mark 이미지
		$this->load->library('goodsList');

		//--> sale library 할인 적용 사전값 전달
		$applypage						= 'wish';
		$param['cal_type']				= 'list';
		$param['total_price']			= 0;
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<-- //sale library 할인 적용 사전값 전달

		# 비교통화 계산 함수 include
		$this->template->include_('showCompareCurrency');

		$wishImageSize	= 'list2';
		$result = $this->wishmodel->get_list( $this->userInfo['member_seq'],$wishImageSize );
		foreach($result['record'] as $key => $goods){

			// 카테고리정보
			$tmparr2 = array();
			$categorys = $this->goodsmodel->get_goods_category($goods['goods_seq']);
			foreach($categorys as $val){
				$tmparr = $this->categorymodel->split_category($val['category_code']);
				foreach($tmparr as $cate) $tmparr2[] = $cate;
			}
			if($tmparr2){
				$tmparr2 = array_values(array_unique($tmparr2));
				$goods['r_category'] = $tmparr2;
			}

			$goods['string_price']		= get_string_price($goods);
			$goods['string_price_use']	= 0;
			if	($goods['string_price'] != '')	$goods['string_price_use']	= 1;

			// 배송정보 가져오기
			$goods['delivery']	= $this->goodsmodel->get_goods_delivery($goods);

			//----> sale library 적용
			unset($param, $sales);
			$param['consumer_price']		= $goods['consumer_price'];
			$param['total_price']			= $goods['price'];
			$param['price']					= $goods['price'];
			$param['ea']					= 1;
			$param['category_code']			= $goods['r_category'];
			$param['goods_seq']				= $goods['goods_seq'];
			$param['goods']					= $goods;
			$this->sale->set_init($param);
			$sales	= $this->sale->calculate_sale_price($applypage);

			$goods['org_price']				= ($goods['consumer_price']) ? $goods['consumer_price'] : $goods['price'];
			$goods['sale_price']			= $sales['result_price'];
			$goods['sale_price_compare']	= showCompareCurrency('',$sales['sale_price'],'return',array("layClass"=>"wx140 "));	# 총 결제금액 비교통화 노출
			// 포인트
			$goods['point']		= (int) $this->goodsmodel->get_point_with_policy($sales['result_price']) + $sales['tot_point'];
			// 마일리지
			$goods['reserve']	= (int) $this->goodsmodel->get_reserve_with_policy($goods['reserve_policy'],$sales['result_price'],$cfg_reserve['default_reserve_percent'],$goods['reserve_rate'],$goods['reserve_unit'],$goods['reserve']) + $sales['tot_reserve'];

			$this->sale->reset_init();
			unset($sales);
			//<---- sale library 적용

			// 19mark 이미지
			$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($goods);
			if ($markingAdultImg) {
				$goods['image']	= $this->goodslist->adultImg;
			}

			// 이미지 부분을 컨트롤에서 지정 :: 2015-03-14 lwh
			//#20299 2018-08-01 ycg 이미지 경로에 https가 포함된 경우에도 표시되도록 수정
			if(!preg_match('/http[s]*:\/\//',$goods['image']) && !file_exists(ROOTPATH.$goods['image']) || $goods['image'] == '') {
				$goods['image'] = "/data/skin/".$this->skin."/images/common/noimage_list.gif";
			}

			$goods['image_html']	= "<img src='".$goods['image']."' width='170' align='absmiddle' hspace='5' style='border:1px solid #ddd;' />";

			//예약 상품의 경우 문구를 넣어준다 2016-11-07
			$goods['goods_name']	= get_goods_pre_name($goods);

			$result['record'][$key] = $goods;
		}

		// ifdo 연동
		$this->load->library('ifdolibrary');
		$ifdo_tags		= $this->ifdolibrary->wish_view($result['record']);

		$this->template->assign($result);
		$this->print_layout($this->template_path());
	}

	// 위시리스트 담기
	public function wish2cart(){
		login_check();
		$this->load->helper('order');
		$this->load->model('goodsmodel');
		$this->load->model('wishmodel');
		$this->load->model('membermodel');
		$this->load->model('categorymodel');
		$this->load->library('sale');

		// 19mark 이미지
		$this->load->library('goodsList');

		$cfg_order		= config_load('order');
		$wish_seq		= (int) $_GET['no'];
		$wish			= $this->wishmodel->get_wish($wish_seq);
		$goods_seq		= $wish['goods_seq'];
		#$goods			= $this->goodsmodel->get_goods($goods_seq);
		#$options		= $this->goodsmodel->get_goods_option($goods_seq);
		#$suboptions		= $this->goodsmodel->get_goods_suboption($goods_seq);
		#$inputs			= $this->goodsmodel->get_goods_input($goods_seq);
		$images			= $this->goodsmodel->get_goods_image($goods_seq);
		#$goods['image']	= $images[1]['thumbView']['image'];

		$result	= $this->goodsmodel->get_goods_view($goods_seq,true,true);

		if	($result['status'] == 'error'){
			switch($result['errType']){
				case 'echo':
					echo $result['msg'];
					exit;
				break;
				case 'back':
					alert($result['msg']);
					pageReload();
					exit;
				break;
				case 'redirect':
					alert($result['msg']);
					pageRedirect($result['url'],'');
					exit;
				break;
			}
		}else{
			$goods			= $result['goods'];
			$options		= $result['options'];		// 여기 추가
			$suboptions		= $result['suboptions'];	// 여기 추가
			$inputs			= $result['inputs'];		// 여기 추가
			$category		= $result['category'];
			$alerts			= $result['alerts'];

			$goods['image']	= $images[1]['thumbView']['image'];

			if	($result['assign'])foreach($result['assign'] as $key => $val){
				$this->template->assign(array($key	=> $val));
			}


			// 옵션 분리형
			if($goods['option_view_type']=='divide' && $options){
				$options_n0 = $this->goodsmodel->option($goods['goods_seq']);
				$this->template->assign(array('options_n0'	=> $options_n0));
			}

			// 옵션 조합형
			if($goods['option_view_type']=='join' && $options){
				$options_join = $this->goodsmodel->option_join($goods['goods_seq']);
				$this->template->assign(array('options_join'	=> $options_join));
			}

			// 여기서부터 추가
			$foption		= $this->goodsmodel->get_first_options($goods, $options);
			if	($goods['option_view_type'] == 'join'){
				$option_data[0]['title']		= $options[0]['option_title'];
				$option_data[0]['newtype']		= $goods['divide_newtype'][0];
				$option_data[0]['options']		= $foption;
				$option_depth					= 1;
			}else{
				if	($goods['option_divide_title'])foreach($goods['option_divide_title'] as $k => $tit){
					$option_data[$k]['title']		= $tit;
					$option_data[$k]['newtype']		= $goods['divide_newtype'][$k];
					if	($k == 0)	$option_data[$k]['options']	= $foption;
					$option_depth++;
				}
			}
			$this->template->assign(array('option_depth'		=> $option_depth));
			$this->template->assign(array('option_data'			=> $option_data));
			$this->template->assign(array('select_option_mode'	=> 'optional_change'));

			// 옵션 선택 박스
			$option_select_path	= str_replace('mypage/wish2cart', 'goods/_select_options', $this->template_path());
			$this->template->define('OPTION_SELECT', $option_select_path);
			// 여기까지 추가
		}

		// 19mark 이미지
		$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($goods);
		if ($markingAdultImg) {
			$goods['image']	= $this->goodslist->adultImg;
		}

		$file = str_replace('wish2cart','_wish2cart',$this->template_path());
		$this->template->assign(array('cfg_cutting'=>$cfg_cutting));
		$this->template->assign(array('wish'=>$wish));
		$this->template->assign(array('goods'=>$goods));
		$this->template->assign(array('options'=>$options));
		$this->template->assign(array('suboptions'=>$suboptions));
		$this->template->assign(array('inputs'=>$inputs));
		$this->template->define(array('LAYOUT'=>$file));
		$this->template->print_('LAYOUT');

		// 가격대체문구 사용여부
		echo "<script>var gl_string_price_use = 0;</script>";
		if( $goods['string_price_use'] ){
			echo "<script>var gl_string_price_use = ".$goods['string_price_use'].";</script>";
		}

		// 관리자 표시용 메시지 출력
		foreach($alerts as $msg){
			alert($msg);
		}
	}


	public function delivery_address(){
		login_check();
		$this->load->helper('shipping');

		$sc=array();
		$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):'1';
		$sc['perpage']			= $perpage ? $perpage : 10;
		$list_order=$_GET['order'];

		switch($list_order){
			case 'desc_up' :
				$orderby='address_description asc';
				break;
			case 'desc_dn' :
				$orderby='address_description desc';
				break;
			case 'name_up' :
				$orderby='recipient_user_name asc';
				break;
			case 'name_dn' :
				$orderby='recipient_user_name desc';
				break;
			case 'name_dn' :
				$orderby='address_seq desc';
				break;
			default :
				$orderby='address_seq desc';
				break;
		}

		$shipping = use_shipping_method();
		if( $shipping ){
			foreach($shipping as $key => $data){
				if($data) $shipping_cnt[$key] = count($data);
			}
		}
		$shipping_policy['policy'] 	= $shipping;
		$shipping_policy['count'] 	= $shipping_cnt;

		$deli_cnt = count($shipping_policy['policy']);

		$member_seq = $this->userInfo['member_seq'];

		$tab=$_GET['tab'];
		$key = get_shop_key();

		$popup=$_GET['popup'];
		$international=$_GET['view_international'];
		$address_group=$_GET['group'];
		$mobileAjaxCall=$_GET['mobileAjaxCall'];

		$sql="select *,
				AES_DECRYPT(UNHEX(recipient_phone), '{$key}') as recipient_phone,
				AES_DECRYPT(UNHEX(recipient_cellphone), '{$key}') as recipient_cellphone
			from fm_delivery_address where member_seq=".$member_seq;

		if($tab=='2'){
			$sql .= " and lately='Y' ";
		}else{
			$sql .= " and often='Y' ";
		}

		if(preg_match('/^(domestic|international)$/Ui', $international)){
			$sql .= " and international='".$international."' ";
		}

		if($address_group){
			$sql .= " and address_group='".$address_group."' ";
		}

		$sql .= " order by ".$orderby." ";

		if($popup == '1' || $mobileAjaxCall){
			$sql .= " limit 30 ";
			$query = $this->db->query($sql);
			$result['record'] = $query -> result_array();
		}else{
			$result = select_page($sc['perpage'],$sc['page'],10,$sql,array());
		}

		foreach($result['record'] as $data){
			if($data['international'] == 'domestic'){
				$international_show = getAlert("sy017"); // '국내';
			}elseif($data['international'] == 'international'){
				$international_show = getAlert("sy018"); // '해외';
			}
			$data['international_show'] = $international_show;
			$loop[] = $data;
		}

		$query = $this->db->query("select address_group from fm_delivery_address where member_seq=? and address_group is not null and address_group !='' group by address_group order by address_group asc",$member_seq);
		$arr_address_group = $query->result_array();
		// 기본 그룹일 경우 수정
		foreach($arr_address_group as $k=>$v){
			$arr_address_group[$k]['address_group'] = ($arr_address_group[$k]['address_group']=="기본 그룹")?getAlert("dv007"):$arr_address_group[$k]['address_group'];
		}
		if(!$arr_address_group){
			$arr_address_group[] = array(getAlert("dv007"));
		}

		// 배송가능 해외국가 추출
		$this->load->model('shippingmodel');
		$ship_gl_arr	= $this->shippingmodel->get_gl_shipping();
		$this->template->assign(array('ship_gl_arr'=>$ship_gl_arr)); // 국가목록

		// 기본 언어에 따라 기본 배송 국가 변경
		$sDefaultNation = "KOREA";
		if ($this->config_system['default_nation']) {
			$sDefaultNation = $this->config_system['default_nation'];
		}
		$this->template->assign('arr_address_group',$arr_address_group);
		$this->template->assign('shop_shipping_policy',$cart['shop_shipping_policy']);
		$this->template->assign('shipping_policy',$shipping_policy);
		$this->template->assign('default_nation', $sDefaultNation);
		$this->template->assign('loop',$loop);
		$this->template->assign($result);
		$this->print_layout($this->template_path());
	}

	public function delivery_address_ajax(){
		$key = get_shop_key();
		$query = $this->db->query("select *,
				AES_DECRYPT(UNHEX(recipient_phone), '{$key}') as recipient_phone,
				AES_DECRYPT(UNHEX(recipient_cellphone), '{$key}') as recipient_cellphone
			from fm_delivery_address where address_seq=?",$_GET['address_seq']);
		$result = $query->row_array();
		foreach($result as $k=>$v){
			if(is_null($v)) $result[$k] = '';
			if($k == 'default' ) $result['defaults'] = $v;
		}
		$result['recipient_new_zipcode'] = str_replace("-", "", $result['recipient_zipcode']);
		//if(strlen($result['recipient_new_zipcode']) < 7){
			//$result['recipient_zipcode'] = substr($result['recipient_new_zipcode'],0,3)."-".substr($result['recipient_new_zipcode'],3,3);
		//}

		if(!$this->managerInfo['manager_id'] && (empty($result['member_seq']) || $result['member_seq'] != $this->userInfo['member_seq'])){
			$result = array();
			$result['result'] = false;
			//잘못된 접근입니다.
			$result['msg'] = getAlert('et018');

			echo json_encode($result);
			exit;
		}

		$result['result'] = true;


		echo json_encode($result);
	}

	public function refund_catalog()
	{
		// 비회원 로그인이 되어있는 경우 주문상세로 리다이렉트 :: 2019-02-08 pjw
		$sessOrder = $this->session->userdata('sess_order');
		if(!empty($sessOrder) && !$this->userInfo['member_seq']) {
			redirect("/mypage/order_view");
			exit;
		}

		if( !$this->userInfo['member_seq'] ){
			redirect("/member/login?order_auth=1");
			exit;
		}

		$this->load->model('refundmodel');

		/**
		 * list setting
		**/
		$sc=array();
		$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):'1';
		$sc['perpage']			= $perpage ? $perpage : 10;
		$sc['member_seq']		= $this->userInfo['member_seq'];
		$sc['order_seq']		= $_GET['order_seq'];

		$refunds = $this->refundmodel->get_refund_list($sc);

		$this->template->assign($refunds);

		$this->print_layout($this->template_path());
	}

	public function refund_view()
	{
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('giftmodel');

		if(!$this->arr_step)	$this->arr_step = config_load('step');
		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		if(!$this->cfg_order)	$this->cfg_order = config_load('order');
		$this->template->assign(array('cfg_order'	=> $this->cfg_order));

		$refund_code 	= $_GET['refund_code'];

		$data_refund 		= $this->refundmodel->get_refund($refund_code);
		$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
		if(!$this->userInfo['member_seq']){
			$order_seq = $this->session->userdata('sess_order');
			if(!$order_seq) {
				redirect("/member/login?order_auth=1");
				exit;
			}
			$data_order 			= $this->ordermodel->get_order($order_seq);
		}else{
			$member_seq	= (int) $this->userInfo['member_seq'];
			$data_order 			= $this->ordermodel->get_order($data_refund['order_seq'], array("member_seq"=>$member_seq));
		}
		if($data_order['step'] == 0 || $data_order['hidden']=='Y' || $data_order['hidden']=='T'){
			//잘못된 접근입니다.
			pageBack(getAlert('os216'));
			exit;
		}

		$data_order_item	= $this->ordermodel->get_item($data_refund['order_seq']);

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

		/* 반품에 의한 환불일경우 주문시 지급 마일리지/포인트 합계 표시 */
		if($data_refund['refund_type']=='return')
		{
			$optquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_option where order_seq=?";
			$optquery = $this->db->query($optquery,$data_refund['order_seq']);
			$optres = $optquery->row_array();

			$suboptquery = "select sum(reserve*step75) as reserve_sum, sum(point*step75) as point_sum from fm_order_item_suboption where order_seq=?";
			$suboptquery = $this->db->query($suboptquery,$data_refund['order_seq']);
			$suboptres = $suboptquery->row_array();

			$tot['reserve_sum'] = $optres['reserve_sum']+$suboptres['reserve_sum'];
			$tot['point_sum']	= $optres['point_sum']+$suboptres['point_sum'];
		}

		$data_refund['mstatus'] = $this->refundmodel->arr_refund_status[$data_refund['status']];
		$data_refund['mrefund_type'] = $this->refundmodel->arr_refund_type[$data_refund['refund_type']];
		$data_refund['mcancel_type'] = $this->refundmodel->arr_cancel_type[$data_refund['cancel_type']];
		$data_order['mpayment'] = $this->arr_payment[$data_order['payment']];

		if($data_order['pg'] === 'naverpayment') {
			$data_order['mpayment'] = '네이버페이';
		}

		if($data_order['international']=='international'){
			$data_order['real_shipping_cost'] = $data_order['international_cost'];
		}else{
			$data_order['real_shipping_cost'] = $data_order['shipping_cost'];
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

		// 원주문의 배송정보
		$data_shipping		= $this->ordermodel->get_order_shipping($data_refund['order_seq']);
		if	($data_shipping)foreach($data_shipping as $k => $ship){
			$ship['international']			= $data_order['international'];
			$ships[$ship['shipping_seq']]	= $ship;
		}

		$refund_items = array();
		foreach($data_refund_item as $k => $data){

			if( $data['goods_kind'] == 'coupon' ) {//
				$data_return = $this->returnmodel->get_return_refund_code($refund_code);
				$data_return_item 	= $this->returnmodel->get_return_item($data_return['return_code']);
				$data['couponinfo'] = get_goods_coupon_view($data_return_item[0]['export_code']);
				$tot['coupon_use_return'] = $data['couponinfo']['coupon_use_return'];
				$tot['coupontotal']++;

				// 3차 환불 개선으로 환불 위약금 :: 2018-11- lkh
				$refundPenaltyDeductiblePriceTmp += $data['coupon_deduction_price'];
			}else{
				$tot['goodstotal']++;
			}

			//차감할 마일리지, 포인트
			if($data_refund['refund_type']=='return' && $data['ea'] > 0 /* && !$cfg_order['buy_confirm_use']*/){
				$tot['return_reserve']	+= $data['give_reserve'];
				$tot['return_point']	+= $data['give_point'];
			}

			## 사은품
			$data['gift_title'] = "";
			if($data['goods_type'] == "gift"){
				$giftlog = $this->giftmodel->get_gift_title($data_refund['order_seq'],$data['item_seq']);
				$data['gift_title'] = $giftlog['gift_title'];
			}

			$tot['ea']			+= $data['ea'];							//환불수량
			$tot['price']		+= $data['price']*$data['ea'];			//환불금액

			//상품금액*주문수량 pjm
			$data['order_price'] = $data['price']*$data['option_ea'];

			//총 할인액(주문기준) pjm
			$data['total_sale'] = $data['event_sale'] + $data['multi_sale'] + ($data['member_sale']*$data['option_ea'])+$data['coupon_sale']
									+$data['fblike_sale']+$data['mobile_sale']+$data['referer_sale']
									+$data['promotion_code_sale']+$data['unit_ordersheet'];

			$tot['out_supply_price']		+= $data['supply_price']*$data['ea'];
			$tot['out_consumer_price']		+= $data['consumer_price']*$data['ea'];
			$tot['out_price']				+= $data['price']*$data['ea'];

			//환불기준 할인액
			$tot['member_sale'] += $data['member_sale']*$data['ea'];
			$tot['coupon_sale'] += $data['coupon_sale'];
			$tot['coupon_sale'] += $data['unit_ordersheet'];
			$tot['fblike_sale'] += $data['fblike_sale'];
			$tot['mobile_sale'] += $data['mobile_sale'];
			$tot['promotion_code_sale'] += $data['promotion_code_sale'];

			//복원된 쿠폰 여부
			if($data['download_seq']){
				$data['restore_used_coupon_refund'] = $this->couponmodel->restore_used_coupon_refund($data['download_seq']);
			}

			//복원된 프로모션코드 여부
			if($data['promotion_code_seq']){
				$data['restore_used_promotioncode_refund'] = $this->promotionmodel->restore_used_promotioncode_refund($data['promotion_code_seq']);
			}

			if($data['opt_type'] == "opt") $opt_cnt[$data['item_seq']]++;

			$data_refund['refund_price_sum']	+=  $data['refund_goods_price'] + $data['refund_delivery_price'];

			$refund_items[$data['item_seq']]['items'][]					= $data;
			$refund_items[$data['item_seq']]['opt_cnt']					= $opt_cnt[$data['item_seq']];
			$refund_items[$data['item_seq']]['refund_ea']				+= $data['ea'];
			$refund_items[$data['item_seq']]['shipping_policy']			= $data['shipping_policy'];
			$refund_items[$data['item_seq']]['goods_shipping_policy']	= $data['shipping_unit']?'limited':'unlimited';
			$refund_items[$data['item_seq']]['unlimit_shipping_price']	= $data['goods_shipping_cost'];
			$refund_items[$data['item_seq']]['limit_shipping_price']	= $data['basic_shipping_cost'];
			$refund_items[$data['item_seq']]['limit_shipping_ea']		= $data['shipping_unit'];
			$refund_items[$data['item_seq']]['limit_shipping_subprice'] = $data['add_shipping_cost'];

			$refund_shipping_items[$data['shipping_seq']]['items'][]	= $data;
			$refund_shipping_items[$data['shipping_seq']]['shipping']	= $ships[$data['shipping_seq']];
			$refund_shipping_items[$data['shipping_seq']]['shipping_cnt']++;

			$refund_total_rows++;
		}

		//PG 취소금액이 있을 때
		if((int)$data['refund_pg_price'] > 0) {
			$data['refund_price']		= $data['refund_pg_price'];
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

		/*
		$tot['refund_shipping_cost'] = $this->refundmodel->get_refund_shipping_cost(
			$data_order,
			$data_order_item,
			$data_refund,
			$data_refund_item
		);
		*/
		//관맂가 직접 입력한 배송비환불액
		//$tot['refund_shipping_cost'] = $data_refund['refund_delivery'];
		// 환불금액(현금성 + 배송비포함)

		if($data_refund['refund_type'] == 'return') {
			$data_return 		= $this->returnmodel->get_return_refund_code($refund_code);
			if($data_return['refund_ship_duty'] == 'buyer' && $data_return['refund_ship_type'] == 'M') {
				$data_refund['return_shipping_price'] = $data_return['return_shipping_price'];
			}
		}


		# 환불방법에 따라 총 환불액 뿌려주기
		if($data_refund['refund_method']){
			$refund_method = $data_refund['refund_method'];
		}else{
			$refund_method = $data_order['payment'];
		}

		if($refund_method == "cash"){
			$data_refund['refund_cash_sum']		= $data_refund['refund_cash'];
			$data_refund['refund_price_sum']	= 0;
		}else{
			$data_refund['refund_cash_sum']		= $data_refund['refund_cash'];
		}

		// 총 환불금액 계산 
		$tot['refund_penalty_deductible_price']		= ($refundPenaltyDeductiblePriceTmp) ? $refundPenaltyDeductiblePriceTmp : 0;
		$tot['refund_all_deductible_price'] = $data_refund['refund_deductible_price']+$data_refund['refund_delivery_deductible_price']+$tot['refund_penalty_deductible_price'];
		$tot['refund_total_price']		= $data_refund['refund_price_sum'] + $data_refund['refund_cash'] + $data_refund['refund_emoney'] - $tot['refund_all_deductible_price'];

		// 반품 환불 배송비	
		if($data_refund['refund_ship_duty'] == 'buyer' &&  $data_refund['refund_ship_type'] == 'M'){
			$tot['refund_total_price'] -= (int)$data_refund['return_shipping_price'];
		}else{
			$data_refund['return_shipping_price'] = 0;
		}

		// 총환불액 검증부 추가 :: 2019-02-01 lwh
		if($tot['refund_total_price'] != $data_refund['refund_price']){
			echo "<script>console.log('Err : The amount is incorrect. (" . $tot['refund_total_price'] . " != " . $data_refund['refund_price'] . ")');</script>";
		}

		$pg = config_load($this->config_system['pgCompany']);
		$this->template->assign(array('pg'	=> $pg));

		// 시스템 계산금액
		$tot['system_price'] = 0;
		$tot['system_price'] += $tot['price'];
		$tot['system_price'] += $tot['refund_goods_shipping_cost'];
		$tot['system_price'] -= $tot['member_sale'];
		$tot['system_price'] -= $tot['coupon_sale'];
		$tot['system_price'] -= $tot['fblike_sale'];
		$tot['system_price'] -= $tot['mobile_sale'];
		$tot['system_price'] -= $tot['promotion_code_sale'];

		/*
		$tot['system_price'] += $tot['refund_shipping_cost'];
		*/

		// 총 조정금액(사용안함 2015-04-16 pjm)
		/*$tot['adjust_price'] = 0;
		$tot['adjust_price'] += $data_refund['adjust_use_coupon'];
		$tot['adjust_price'] += $data_refund['adjust_use_promotion'];
		$tot['adjust_price'] += $data_refund['adjust_use_emoney'];//마일리지
		$tot['adjust_price'] += $data_refund['adjust_use_cash'];//예치금(캐쉬)
		$tot['adjust_price'] += $data_refund['adjust_use_enuri'];
		*/

		// 환불금액(사용안함 2015-04-16 pjm)
		//$tot['refund_expected_price'] = $tot['system_price']-$tot['adjust_price'];

		// 최종환불금액(사용안함 2015-04-16 pjm)
		//$tot['final_refund_price'] = $tot['refund_expected_price']-$data_refund['adjust_refund_price'];

		$this->template->assign(
			array(
			'refund_shipping_items'=>$refund_shipping_items,
			'refund_total_rows'=>$refund_total_rows,
			'data_refund'=>$data_refund,
			'data_refund_item'=>$data_refund_item,
			'refund_items'=>$refund_items,
			'tot'=>$tot,
			'data_order'=>$data_order)
		);

		$this->print_layout($this->template_path());
	}

	public function return_catalog()
	{
		// 비회원 로그인이 되어있는 경우 주문상세로 리다이렉트 :: 2019-02-08 pjw
		$sessOrder = $this->session->userdata('sess_order');
		if(!empty($sessOrder) && !$this->userInfo['member_seq']) {
			redirect("/mypage/order_view");
			exit;
		}

		if( !$this->userInfo['member_seq'] ){
			redirect("/member/login?order_auth=1");
			exit;
		}

		$this->load->model('returnmodel');

		/**
		 * list setting
		**/
		$sc=array();
		$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):'1';
		$sc['perpage']			= $perpage ? $perpage : 10;
		$sc['member_seq']		= $this->userInfo['member_seq'];
		$sc['order_seq']		= $_GET['order_seq'];

		$refunds = $this->returnmodel->get_return_list($sc);

		$this->template->assign($refunds);

		$this->print_layout($this->template_path());
	}

	public function return_view()
	{
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('return_code', '반품코드', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		// 로그인 검증
		login_check();

		$return_code = $aGetParams['return_code'];


		// 사유코드
		$reasons = code_load('return_reason');

		$this->load->model('returnmodel');
		$this->load->model('refundmodel');
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$this->load->model('giftmodel');
		$this->load->model('shippingmodel');

		$data_return 		= $this->returnmodel->get_return($return_code);
		$data_return_item 	= $this->returnmodel->get_return_item($return_code);
		$data_order			= $this->ordermodel->get_order($data_return['order_seq']);

		if ($this->userInfo['member_seq'] !== $data_order["member_seq"]) {
			pageBack(getAlert('mp094'));
			exit;
		}

		$tmp = $this->refundmodel->get_refund($data_return['refund_code']);
		$data_return['mrefund_status']	= $this->refundmodel->arr_refund_status[$tmp['status']];
		$data_return['mstatus'] 		= $this->returnmodel->arr_return_status[$tmp['status']];

		if( $data_return['phone'] )$data_return['phone'] = explode('-',$data_return['phone']);
		if( $data_return['cellphone'] )$data_return['cellphone'] = explode('-',$data_return['cellphone']);
		if( $data_return['sender_zipcode'] ){
			$data_return['sender_new_zipcode'] = $data_return['sender_zipcode'];
			$zipcodear[0] = substr(str_replace("-", "", $data_return['sender_zipcode']),0,3);
			$zipcodear[1] = substr(str_replace("-", "", $data_return['sender_zipcode']),3,3);
			$data_return['sender_zipcode'] = $zipcodear;
		}

		foreach($data_return_item as $key => $item){
			if( $item['goods_kind'] == 'coupon' ) {//
				$data_return_item[$key]['couponinfo'] = get_goods_coupon_view($item['export_code']);
			}

			## 사은품
			$item['gift_title'] = "";
			if($item['goods_type'] == "gift"){
				$giftlog = $this->giftmodel->get_gift_title($data_return['order_seq'],$item['item_seq']);
				$data_return_item[$key]['gift_title'] = $giftlog['gift_title'];
			}

			/**
			* 반품 실제 회수수량
			* fm_order_return_item return_ea 미사용-> stock_return_ea
			**/
			if($item['package_yn'] = 'y' && $item['package_stock_return_ea']){//패키지상품의 환불수량
				/**$package_stock_return_ea = unserialize($item['package_stock_return_ea']);
				foreach($package_stock_return_ea as $package_stock_return_ea_v){
					$item['return_ea']			+= $package_stock_return_ea_v;
				}**/
				$item['return_ea']			= $item['ea'];//실제 패키지상품 환불수량과 다를수 있어서 접수된 수량처리
			}else{
				$item['return_ea']			= $item['stock_return_ea'];
			}

			$goods_cnt[$item['goods_seq']]++;
			$tot['ea']  		+= $item['ea'];
			$tot['return_ea']	+= $item['return_ea'];

			$tot['out_supply_price']		+= $item['supply_price']*$item['ea'];
			$tot['out_consumer_price']	+= $item['consumer_price']*$item['ea'];
			$tot['out_price']					+= $item['price']*$item['ea'];

			if( $item['reason_code'] > 100 && $item['reason_code'] < 200 ) $tot['user_reason_cnt'] += $item['ea'];
			if( $item['reason_code'] > 200 && $item['reason_code'] < 300 ) $tot['shop_reason_cnt'] += $item['ea'];
			if( $item['reason_code'] > 300 ) $tot['goods_reason_cnt'] += $item['ea'];
			$data_return_item[$key]['reasons'] = $reasons;

			if($item['opt_type'] == "opt") $data_return_item[$key]['opt_cnt']++;

			$query = $this->db->query("select refund_code from fm_order_refund_item where item_seq=?",$item['item_seq']);
			$tmp = $query->row_array();
			$data_return_item[$key]['refund_code'] = $tmp['refund_code'];
		}
		if($goods_ea) $tot['goods_cnt'] = array_sum($goods_cnt);
		$data_return['mstatus'] = $this->returnmodel->arr_return_status[$data_return['status']];
		$data_return['mreturn_type'] = $this->returnmodel->arr_return_type[$data_return['return_type']];
		$data_return['mreturn_method'] = $this->returnmodel->arr_return_method[$data_return['return_method']];

		// 반품배송비 입금 계좌설정 정보
		$bankReturn = array();
		$arr = config_load('bank_return');
		if($arr)foreach($arr as $k=>$v){
			list($tmp) = code_load('bankCode',$v['bankReturn']);
			$v['bank'] = $tmp['value'];
			$bankReturn[] = $v;
		}

		$grp_sql = "SELECT refund_address_seq,refund_scm_type FROM fm_shipping_grouping WHERE shipping_provider_seq = {$data_return_item[0]['provider_seq']} AND default_yn = 'Y' LIMIT 1";
		$grpping = $this->db->query($grp_sql);
		$grpping = $grpping->row_array();

		$grp_seq = $grpping['refund_address_seq'];
		$grp_scm_type = $grpping['refund_scm_type'];
		$address = $this->shippingmodel->get_shipping_address($grp_seq, $grp_scm_type);


		if($address['address_street']){
			$provider_shipping['returnZipcode']			= $address['address_zipcode'];
			$provider_shipping['returnAddress']			= $address['address_street'];
			$provider_shipping['returnAddress_street']	= $address['address_street'];
			$provider_shipping['returnAddressDetail']	= $address['address_detail'];
			$provider_shipping['return_address_type']	= $address['address_type'];
		}else{
			$provider_shipping['returnZipcode']			= $address['address_zipcode'];
			$provider_shipping['returnAddress']			= $address['address'];
			$provider_shipping['returnAddress_street']	= $address['address_street'];
			$provider_shipping['returnAddressDetail']	= $address['address_detail'];
			$provider_shipping['return_address_type']	= $address['address_type'];
		}
		$provider_shipping['return_address_type'] = $address['address_type'];

		$this->template->assign(
			array(
				'data_return'=>$data_return,
				'data_return_item'=>$data_return_item,
				'tot'=>$tot,
				'data_order'=>$data_order,
				'bankReturn'		=> $bankReturn,
				'provider_shipping'		=> $provider_shipping
			)
		);

		$this->print_layout($this->template_path());
	}


	//포인트교환
	public function point_exchange(){
		login_check();

		if( !$this->isplusfreenot ){//무료몰인경우
			//잘못된 접근입니다.
			pageBack(getAlert('mp094'));
			exit;
		}

		if( !$this->isplusfreenot['ispoint'] ){//포인트 사용안함
			//pageBack('잘못된 접근입니다.');
			//exit;
		}

		if( !$this->isplusfreenot["isemoney_exchange"] ){//포인트교환 사용안함
			pageBack(getAlert('mp094'));
			exit;
		}

		### GIFT
		$today = date("Y-m-d");
		$sql = "SELECT * FROM fm_gift WHERE gift_gb = 'buy' AND start_date <= '{$today}' AND end_date >= '{$today}' AND display = 'y' ORDER BY gift_seq DESC limit 1";
		$query = $this->db->query($sql);
		$gift = $tmp = $query->row_array();

		if($gift['gift_seq']){
			$sql = "SELECT A.* FROM fm_gift_benefit A LEFT JOIN fm_goods B ON A.gift_goods_seq = B.goods_seq WHERE gift_seq = '{$gift['gift_seq']}' and (B.goods_view = 'look' or ( B.display_terms = 'AUTO' and B.display_terms_begin <= '".$today."' and B.display_terms_end >= '".$today."')) and B.goods_status = 'normal' ORDER BY sprice";
			$query = $this->db->query($sql);
			foreach($query->result_array() as $k){

				// 상품의 현재 상태값에 따라 노출 2017-02-23 jhr
				$gift_goods_seq_arr = array();

				$sql = "select goods_seq from fm_goods where goods_seq in (".str_replace("|" , ",", $k['gift_goods_seq']).") and goods_status = 'normal' and goods_view = 'look'";

				$rs = $this->db->query($sql);

				foreach($rs->result_array() as $goods) $gift_goods_seq_arr[] = $goods['goods_seq'];

				$k['goods']  = $gift_goods_seq_arr;

				//사은품 노출 체크
				for($i=0; $i<count($k['goods']); $i++){
					$sql	= "SELECT count(*) as cnt FROM fm_goods WHERE goods_seq = '".$k['goods'][$i]."' and  (goods_view = 'look' or ( display_terms = 'AUTO' and display_terms_begin <= '".$today."' and display_terms_end >= '".$today."'))";
					$query	= $this->db->query($sql);
					$info	= $query->result_array();
					$cnt	= $info[0]['cnt'];
					if($cnt < 1){
						unset($k['goods'][$i]);
					}
				}

				$gift_loop[]	= $k;
			}


			$this->template->assign('gift_loop',$gift_loop);


		}
		$this->template->assign('gift_info',$gift);
		$configReserve = config_load('reserve');
		$this->template->assign('configReserve',$configReserve);
		$this->template->assign('myemoney',$this->mdata['emoney']);
		$this->print_layout($this->template_path());
	}


	public function buy_gift(){

		$aGetParams = $this->input->get();
		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('seq', '상품번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('point', '포인트', 'trim|numeric|xss_clean');
			$this->validation->set_rules('goods_rule', '상품번호', 'trim|string|xss_clean');
			$this->validation->set_rules('goods_name', '상품번호', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$goods_seq		= $aGetParams['seq'];
		$point			= $aGetParams['point'];
		$goods_rule		= $aGetParams['goods_rule'];
		$goods_name		= unescape($aGetParams['goods_name']);

		$this->template->assign(array('goods_seq'=>$goods_seq,'point'=>$point,'goods_name'=>$goods_name));

		if($this->userInfo['member_seq']){
			$this->load->model('membermodel');
			$members = $this->membermodel->get_member_data($this->userInfo['member_seq']);
			$tmp = explode('-',$members['phone']);
			foreach($tmp as $k => $data){
				$key = 'phone'.($k+1);
				$members[$key] = $data;
			}

			$tmp = explode('-',$members['cellphone']);
			foreach($tmp as $k => $data){
				$key = 'cellphone'.($k+1);
				$members[$key] = $data;
			}

			$tmp = explode('-',$members['zipcode']);
			foreach($tmp as $k => $data){
				$key = 'zipcode'.($k+1);
				$members[$key] = $data;
			}
		}
		$this->template->assign('members',$members);

		$this->load->helper('shipping');
		$shipping = use_shipping_method();
		if( $shipping ){
			foreach($shipping as $key => $data){
				if($data) $shipping_cnt[$key] = count($data);
			}
		}
		$shipping_policy['policy'] 	= $shipping;
		$shipping_policy['count'] 	= $shipping_cnt;
		$this->template->assign('shipping_policy',$shipping_policy);
		$this->template->assign('goods_rule',$goods_rule);

		$this->template->define(array('LAYOUT'=>$this->template_path()));
		$this->template->print_('LAYOUT');
	}

	//개인결제
	public function personal()
	{

		login_check();

		$this->load->model('goodsdisplay');
		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');

		$code = isset($_GET['code']) ? $_GET['code'] : '';
		$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

		/* 카테고리 정보 */
		$categoryData = $this->categorymodel->get_category_data($code);

		$code = $categoryData['category_code'];

		$childCategoryData = $this->categorymodel->get_list($code,array(
			"hide = '0'",
			"level >= 2"
		));
		//print_r($childCategoryData);
		if(strlen($code)>4 && !$childCategoryData){
			$childCategoryData = $this->categorymodel->get_list(substr($code,0,strlen($code)-4),array(
				"hide = '0'",
				"level >= 2"
			));
		}

		/**
		 * list setting
		**/
		$sc=array();
		$sc['sort']				= $sort ? $sort : $list_default_sort;
		$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):'1';
		$sc['search_text']		= (!empty($_GET['search_text']))?str_replace(array('"',"'"),"",$_GET['search_text']):'';
		$sc['category']			= $code;
		$sc['perpage']			= $_GET['perpage'] ? $_GET['perpage'] : 10;
		$sc['image_size']		= $categoryData['list_image_size'];
		$sc['list_style']		= $_GET['display_style'] ? $_GET['display_style'] : '';
		$sc['list_goods_status']= $categoryData['list_goods_status'];

		if($_GET['so_brand'])	$sc['so_brand']		= $_GET['so_brand'];
		if($_GET['so_option1'])	$sc['so_option1']	= $_GET['so_option1'];
		if($_GET['so_option2'])	$sc['so_option2']	= $_GET['so_option2'];


		/* 쇼핑몰 타이틀 */
		if($this->config_basic['shopCategoryTitleTag'] && $categoryData['title']){
			$title = str_replace("{카테고리명}",$categoryData['title'],$this->config_basic['shopCategoryTitleTag']);
			$this->template->assign(array('shopTitle'=>$title));
		}

		$str_where_order = " AND regist_date >= '".date('Y-m-d',strtotime("-7 day"))." 00:00:00'";

		$key = get_shop_key();
		$query = "
				select * from (
				SELECT title, order_user_name, total_price, order_seq, enuri, member_seq, order_email, order_phone, order_cellphone, person_seq,
					regist_date,
					(SELECT userid FROM fm_member WHERE member_seq=pr.member_seq) userid,
					(SELECT AES_DECRYPT(UNHEX(email), '{$key}') as email FROM fm_member WHERE member_seq=pr.member_seq) mbinfo_email,
					(SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=pr.member_seq) group_name,
					(select goods_name from fm_goods where goods_seq
						in (select goods_seq from fm_person_cart where person_seq = pr.person_seq) limit 1) goods_name,
					(select count(goods_seq) from fm_person_cart where person_seq = pr.person_seq) item_cnt
				FROM fm_person pr where order_seq is null ".$str_where_order." AND pr.member_seq = '".$this->userInfo['member_seq']."') t ".$str_where. " order by person_seq desc
		";

		$list = select_page($sc['perpage'],$sc['page'],10,$query,'');
		$list['page']['querystring'] = get_args_list();
		$list['search_yn'] = $search_yn;

		foreach($list['record'] as $k => $data) {
			$regist_date = $list['record'][$k]['regist_date'];
			$list['record'][$k]['expiry_date'] = date('Y-m-d',strtotime("+8 day", strtotime($regist_date)))." 00:00:00";
		}

		$this->template->assign($list);

		$this->template->assign(array(
			'categoryCode'			=> $code,
			'categoryTitle'			=> $categoryData['title'],
			'categoryData'			=> $categoryData,
			'childCategoryData'		=> $childCategoryData,
		));
		$this->getboardcatalogcode = $code;//상품후기 : 게시판추가시 이용됨

		/**
		 * display
		**/
		$sc['list_style'] = "person";
		$this->goodsdisplay->set('style',$sc['list_style'] ? $sc['list_style'] : $categoryData['list_style']);
		$this->goodsdisplay->set('count_w',$categoryData['list_count_w']);
		$this->goodsdisplay->set('count_h',$categoryData['list_count_h']);
		$this->goodsdisplay->set('perpage',$sc['perpage']);
//		$this->goodsdisplay->set('image_decorations',$categoryData['list_image_decorations']);
		$this->goodsdisplay->set('image_size',$categoryData['list_image_size']);
		$this->goodsdisplay->set('text_align',$categoryData['list_text_align']);
		$this->goodsdisplay->set('info_settings',$categoryData['list_info_settings']);

		$this->goodsdisplay->set('displayGoodsList',$list['record']);
		$goodsDisplayHTML = "<div class='designPersonalGoodsDisplay' designElement='personalGoodsDisplay'>";
		$goodsDisplayHTML .= $this->goodsdisplay->print_(true);
		$goodsDisplayHTML .= "</div>";

		unset($_GET['sort']);
		unset($_GET['perpage']);
		$sortUrlQuerystring = getLinkFilter('',array_keys($_GET));

		$this->template->assign(array(
			'goodsDisplayHTML'		=> $goodsDisplayHTML,
			'sortUrlQuerystring'	=> $sortUrlQuerystring,
			'sort'					=> $sc['sort'],
			'sc'					=> $sc,
			'orders'				=> $this->goodsdisplay->orders,
		));


		$this->print_layout($this->template_path());

	}


	function promotion(){
		login_check();
		if( !$this->isplusfreenot ){//무료몰인경우
			//잘못된 접근입니다.
			pageBack(getAlert('mp027'));
			exit;
		}

		$this->template->assign('myemoney',$this->mdata['emoney']);
		$this->print_layout($this->template_path());

	}

	function emoney_exchange(){
		login_check();
		if( !$this->isplusfreenot ){//무료몰인경우
			pageBack('잘못된 접근입니다.');
			exit;
		}

		$order_config = config_load("order");

		### GIFT
		$today = date("Y-m-d");
		$sql = "SELECT * FROM fm_gift WHERE gift_gb = 'buy' AND start_date <= '{$today}' AND end_date >= '{$today}' AND display = 'y' ORDER BY gift_seq DESC";
		$query = $this->db->query($sql);
		$giftArray = $tmp = $query->result_array();
		$before_title = "";
		foreach($giftArray as $gift){
			if($gift['gift_seq']){
				// 상품 체크는 별도로 체크하도록 수정 2018-11-27 rhm
				$sql = "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift['gift_seq']}'";
				$query = $this->db->query($sql);
				foreach($query->result_array() as $k){

					// 상품의 현재 상태값에 따라 노출 2017-02-23 jhr
					$gift_goods_seq_arr = array();

					$sql = "select goods_seq from fm_goods where goods_seq in (".str_replace("|" , ",", $k['gift_goods_seq']).") and goods_status = 'normal' and goods_view = 'look'";

					$rs = $this->db->query($sql);

					foreach($rs->result_array() as $goods) $gift_goods_seq_arr[] = $goods['goods_seq'];

					$k['goods']  = $gift_goods_seq_arr;
					if($before_title == $k['title']){
						$k['title']		= $gift['title'];
						$k['gift_contents']		= $gift['gift_contents'];
					}

					$k['start_date']	= $gift['start_date'];
					$k['end_date']		= $gift['end_date'];
					//사은품 노출 체크
					for($i=0; $i<count($k['goods']); $i++){
						$sql	= "SELECT count(*) as cnt FROM fm_goods WHERE goods_seq = '".$k['goods'][$i]."' and  (goods_view = 'look' or ( display_terms = 'AUTO' and display_terms_begin <= '".$today."' and display_terms_end >= '".$today."'))";
						$query	= $this->db->query($sql);
						$info	= $query->result_array();
						$cnt	= $info[0]['cnt'];
						if($cnt < 1){
							unset($k['goods'][$i]);
						}
					}
					$before_title = $k['title'];
					$gift_loop[]	= $k;
				}
			}
		}

		## 이번달 소멸 마일리지
		$this->load->model("membermodel");
		$param					= array();
		$param['startdt']		= date("Y-m-01 00:00:00", strtotime(date("Y-m-d")));
		$param['enddt']			= date("Y-m-t 23:59:59", strtotime(date("Y-m-d")));
		$param['member_seq']	= $this->mdata['member_seq'];
		$extincation			= $this->membermodel->get_member_extinction_emoney($param);

		$this->template->assign('gift_loop',$gift_loop);
		$this->template->assign('extinction_emoney',$extincation['emoney']);
		$this->template->assign('order_config',$order_config);
		$this->template->assign('myemoney',$this->mdata['emoney']);
		$this->template->assign('gift_info',$gift);
		$this->print_layout($this->template_path());
	}

	function my_minishop(){

		login_check();
		$this->template->assign(array('user'=>$this->userInfo));

		$this->load->model("myminishopmodel");

		if( $this->mobileMode ) {//모바일스킨에서는 페이징추가
			$sc							= $_GET;
			$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):'1';
			$sc['perpage']			= $perpage ? $perpage : 10;
			$sc['member_seq']		= $this->userInfo['member_seq'];

			$myminishop = $this->myminishopmodel->myminishop_list($sc);
			$this->template->assign($myminishop);
			$this->template->assign(array('sc'=>$sc));
		}else{
			$myshop			= $this->myminishopmodel->get_myminishop($this->userInfo['member_seq']);
			$myshopcnt		= count($myshop);
			$this->template->assign(array('my_total_cnt'=>$myshopcnt));
			$this->template->assign(array('my'=>$myshop));
		}
		if($_GET['ajax']) {
			$result = array('loop'=>$myminishop['record'],'loopcount'=>$myminishop['page']['totalcount']);
			echo json_encode($result);
		}else{
			$this->print_layout($this->template_path());
		}
	}


	// 출고상세
	public function export_view(){

		$this->load->model('ordermodel');
		$this->load->model('exportmodel');
		$this->load->model('buyconfirmmodel');
		$this->load->model('returnmodel');
		$this->load->model('goodsmodel');
		$this->load->helper('order');

		$file_path	= $this->template_path();

		if	(!$this->arr_step)		$this->arr_step		= config_load('step');
		if	(!$this->arr_payment)	$this->arr_payment	= config_load('payment');
		if	(!$this->cfg_order)		$this->cfg_order	= config_load('order');
		$this->template->assign(array('cfg_order'	=> $this->cfg_order));

		if(!$this->userInfo['member_seq'])
			$order_seq = $this->session->userdata('sess_order');
		else
			$order_seq	= trim($_GET['no']);

		if	(!$order_seq){
			if	($this->userInfo['member_seq'])	redirect("./order_catalog?step_type=order");
			else								redirect("/member/login?order_auth=1");
			exit;
		}

		if	($this->userInfo['member_seq'])
			$order_param	= array("member_seq"=>$this->userInfo['member_seq']);

		$orders 			= $this->ordermodel->get_order($order_seq, $order_param);
		if($orders['step'] == 0){
			//잘못된 접근입니다.
			pageBack(getAlert('os216'));
			exit;
		}

		// 회원 정보 가져오기
		if($orders['member_seq']){
			$members = $this->membermodel->get_member_data($orders['member_seq']);
			$this->template->assign(array('members'=>$members));
		}

		$orders['mpayment'] = $this->arr_payment[$orders['payment']];
		$orders['mstep'] 	= $this->arr_step[$orders['step']];

		$orders = $this->memberlibrary->replace_mypage_order($orders);

		$arr = config_load('bank');
		if($arr) foreach(config_load('bank') as $k => $v){
			list($tmp) = code_load('bankCode',$v['bank']);
			$v['bank'] = $tmp['value'];
			$bank[] = $v;
		}

		if	($orders['step'] < 50){
			$items 				= $this->ordermodel->get_item($order_seq);
			$able_return_ea = $tot['coupontotal'] =$tot['goodstotal'] = 0;
			foreach($items as $key=>$item){

				if ( $item['goods_kind'] == 'coupon' ) {
					$tot['coupontotal']++;//티켓상품@2013-11-06
				}else{
					$tot['goodstotal']++;
				}

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

					//sale 5가지
					$data['out_event_sale']					= $data['event_sale'];
					$data['out_multi_sale']					= $data['multi_sale'];
					$data['out_member_sale']				= $data['member_sale']*$data['ea'];//1
					$data['out_coupon_sale']				= ($data['download_seq'])?$data['coupon_sale']:0;//2
					$data['out_fblike_sale']				= $data['fblike_sale'];//3
					$data['out_mobile_sale']				= $data['mobile_sale'];//4
					$data['out_promotion_code_sale']		= $data['promotion_code_sale'];//5
					$data['out_tot_sale']					= $data['out_event_sale'] + $data['out_multi_sale'] + $data['out_member_sale']+$data['out_coupon_sale']+$data['out_fblike_sale']+$data['out_mobile_sale']+$data['out_promotion_code_sale'];

					$data['out_reserve'] = $data['reserve']*$data['ea'];
					$data['out_point'] = $data['point']*$data['ea'];
					$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];

					###
					$data['inputs']	= $this->ordermodel->get_input_for_option($data['item_seq'], $data['item_option_seq']);

					$tot['ea'] += $data['ea'];
					$tot['supply_price'] += $data['out_supply_price'];
					$tot['consumer_price'] += $data['out_consumer_price'];
					$tot['price'] += $data['out_price'];

					//sale 5가지
					$tot['event_sale']  += $data['out_event_sale'];
					$tot['multi_sale']  += $data['out_multi_sale'];
					$tot['member_sale'] += $data['out_member_sale'];
					$tot['coupon_sale'] += $data['out_coupon_sale'];
					$tot['fblike_sale'] += $data['out_fblike_sale'];
					$tot['mobile_sale'] += $data['out_mobile_sale'];
					$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];

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
					}

					$data['suboptions']	= $suboptions;
					$options[$k] = $data;

					$item['tot_goods_cnt']		+= count($suboptions) + 1;
				}
				$item['shipping_item_option']	= $options;
				$item['totaloptitems']			= count($options) + count($suboptions);
				$items[$key] 					= $item;
				$tot['goods_shipping_cost']		+= $item['goods_shipping_cost'];

			}

			$order_shippings[0]						= $orders;
			$order_shippings[0]['shipping_items']	= $items;

		}else{
			// 출고정보
			$exports	= $this->exportmodel->get_export_for_order($order_seq);
			$export_cnt	= $buy_confirm_cnt = 0;
			foreach( $exports as $k => $data_export ){
				$export_cnt ++;
				$shipping_arr['international'] = $data_export['international'];
				if($data_export['international'] == 'domestic'){
					$shipping_arr['shipping_method'] = $shipping_arr['domestic_shipping_method'];
				}else{
					$shipping_arr['shipping_method_international'] = $shipping_arr['international_shipping_method'];
				}
				$data_export['out_shipping_method'] = $this->ordermodel->get_delivery_method($orders);

				$data_export['item'] =  $this->exportmodel->get_export_item($data_export['export_code']);
				$data_export['data_buy_confirm']		= $this->buyconfirmmodel->get_log_buy_confirm($data_export['export_seq']);

				if($data_export['international_shipping_method']){
					$data_export['mdelivery'] = $data_export['international_shipping_method'];
					$data_export['mdelivery_number'] = $data_export['international_delivery_no'];
					$data_export['tracking_url'] = "#";
					if($data_export['international_shipping_method']!='ups'){
						$data_export['tracking_url'] = get_delivery_company(get_international_method_code(strtoupper($data_export['international_shipping_method'])),'url').$data_export['international_delivery_no'];
					}
				}

				if($data_export['buy_confirm'] != 'none') {
					$buy_confirm_cnt++;
				}

				foreach( $data_export['item'] as $i=>$data){

					// 티켓상품 출고일 경우
					if	($data['goods_kind'] == 'coupon'){
						$coupon_export[$data['export_code']]['coupon_serial']		= $data['coupon_serial'];
						$coupon_export[$data['export_code']]['coupon_st']			= $data['coupon_st'];
						$coupon_export[$data['export_code']]['recipient_email']		= $data['recipient_email'];
						$coupon_export[$data['export_code']]['recipient_cellphone']	= $data['recipient_cellphone'];
						$coupon_export[$data['export_code']]['mail_status']			= $data['mail_status'];
						$coupon_export[$data['export_code']]['sms_status']			= $data['sms_status'];
						$coupon_export[$data['export_code']]['coupon_value']		= $data['coupon_value'];
						$coupon_export[$data['export_code']]['coupon_value_type']	= $data['coupon_value_type'];
						$coupon_export[$data['export_code']]['coupon_remain_value']	= $data['coupon_remain_value'];

						$coupon_export[$data['export_code']]['couponinfo'] = get_goods_coupon_view($data['export_code']);
					}else{
						//출고별 마일리지 지급 예상 수량, 마일리지 지급수량
						$exports_tot[$data['export_code']]['reserve_ea']			+= $data['reserve_ea'];
						$exports_tot[$data['export_code']]['reserve_buyconfirm_ea']	+= $data['reserve_buyconfirm_ea'];
						if($data['goods_kind'] == "coupon") $exports_tot[$data['export_code']]['goods_coupon'] = true;
					}

					$it_s = $data['item_seq'];
					$it_ops = $data['option_seq'];

					if($data['opt_type']=='opt'){
						$return_item = $this->returnmodel->get_return_item_ea($it_s,$it_ops,$data_export['export_code']);
					}
					if($data['opt_type']=='sub'){
						$return_item = $this->returnmodel->get_return_subitem_ea($it_s,$it_ops,$data_export['export_code']);
					}

					$data_export['item'][$i]['inputs'] = $this->ordermodel->get_input_for_option($data['item_seq'], $data['option_seq']);

					$data_export['item'][$i]['rt_ea']=$data['ea'] - $return_item['ea'];
					$data_export['rt_ea']+=$data_export['item'][$i]['rt_ea'];
				}

				/* 반품신청 가능 기간 체크 @2016-11-17 */
				if($this->cfg_order['buy_confirm_use']){
					// 구매확정 사용시 출고완료일 후 n일 내에만 반품신청 가능
					$order_return_sdate = date('Ymd',strtotime('+'.$this->cfg_order['save_term'].' day',strtotime($data_export['complete_date'])));
					$data_export['return_able_term']	= ($data_export['complete_date'] && date('Ymd') < $order_return_sdate)?1:0;
				}else{
					// 구매확정 미사용시 배송완료일 후 n일 내에만 반품신청 가능
					$order_return_edate = date('Ymd',strtotime('+'.$this->cfg_order['save_term'].' day',strtotime($data_export['shipping_date'])));
					$data_export['return_able_term']	= ($data_export['shipping_date'] != '0000-00-00' && date('Ymd') < $order_return_edate)?1:0;
				}

				// 배송정보(매장수령의 경우)
				if($data_export['shipping_method'] == 'direct_store') {
					$shipping_direct_store = $this->ordermodel->get_order_shipping($order_seq,null,$data_export['item']['shipping_seq']);
					$data_export['direct_store'] = $shipping_direct_store[$data_export['shipping_group']];
				}

				// 출고준비상태일때는 구매확정불가
				if($data_export['status'] == '45'){
					$data_export['item'][$i]['reserve_ea'] = 0;
				}
				$exports[$k] = $data_export;
			}

			if( $buy_confirm_cnt  == $export_cnt ){
				$orders['buy_confirm'] = true;
			}

			$order_shippings[0]				= $orders;
			$order_shippings[0]['exports']	= $exports;
		}

		$this->template->assign(array('coupon_export'	=> $coupon_export));
		$this->template->assign(array('order_shippings'	=> $order_shippings));
		$this->template->assign(array('shipping_policy'	=> $shipping_policy));
		$this->template->assign(array('orders'			=> $orders));
		$this->template->assign(array('exports'			=> $exports));
		$this->template->assign(array('exports_tot'		=> $exports_tot));

		$this->print_layout($this->template_path());
	}

	// 티켓상품 상세 화면
	public function coupon_view(){

		login_check();
		$this->load->model("exportmodel");
		$this->load->helper("order");
		$export_code		= trim($_GET['code']);

		if	(!$export_code){
			//잘못된 접근입니다.
			pageBack(getAlert('mp028'));
			exit;
		}

		// 쿠폰 상세 정보
		$coupon = $this->exportmodel->get_coupon_info(array('export_code' => $export_code, 'member_seq' => $this->userInfo['member_seq']));
		if	(!$coupon['coupon_serial']){
			//쿠폰정보가 정보가 없습니다.
			pageBack(getAlert('mp029'));
			exit;
		}
		// 쿠폰 사용 내역
		$use_history		= $this->exportmodel->get_coupon_use_history($coupon['coupon_serial']);

		// 해당 상품 주문 정보
		$items				= $this->exportmodel->get_export_item($export_code);
		$item				= $items[0];

		// 쿠폰 사용 가능여부 체크
		$chk_coupon			= $this->exportmodel->chk_coupon(array('export_code' => $export_code));

		// 특수옵션 치환처리
		$coupon['option']	= get_options_print_array($item, ':');
		if	($coupon['coupon_value_type'] == 'price')	$coupon['coupon_unit']	= $this->config_system['basic_currency'];
		else											$coupon['coupon_unit']	= '회';

		$this->template->assign(array('coupon'		=> $coupon));
		$this->template->assign(array('use_history'	=> $use_history));
		$this->template->assign(array('item'		=> $item));
		$this->template->assign(array('chk'			=> $chk_coupon));

		if($_GET['popup']){
			$file_path = $this->template_path();
			$this->template->define(array('tpl'=>$file_path));
			$this->template->print_("tpl");
		}else{
			$this->print_layout($this->template_path());
		}
	}

	// 쿠폰 사용하기
	public function coupon_use(){
		login_check();

		$this->load->model("exportmodel");
		$member_seq = isset($this->userInfo) ? $this->userInfo['member_seq'] : 0;
		$coupon_serial	= trim($_GET['scode']);
		$export_code	= trim($_GET['code']);
		$usetype		= ($_GET['usetype']) ? trim($_GET['usetype']) : 'one';

		if	(!$export_code && !$coupon_serial){
			//잘못된 접근입니다.
			$err_msg	= getAlert('mp030');
		}else{
			// 쿠폰 상세 정보
			if	($export_code)	$param['export_code']	= $export_code;
			else				$param['coupon_serial']	= $coupon_serial;
			$param['member_seq'] = $member_seq;
			$coupon			= $this->exportmodel->chk_coupon($param);

			// 해당 티켓상품 구매한 유저가 아닐 경우
			if ($coupon['result'] == 'notOrderUser') {
				pageLocation('/');
				exit;
			}

			if	($coupon['result'] == 'success'){
				// 해당 상품 주문 정보
				$address			= $coupon['address'];
				$items				= $this->exportmodel->get_export_item($export_code);
				$item				= $items[0];

				// 특수옵션 치환처리
				$coupon['option']	= get_options_print_array($item, ':');
				if	($coupon['coupon_value_type'] == 'price')
					$coupon['coupon_unit']	= $this->config_system['basic_currency'];
				else
					$coupon['coupon_unit']	= '회';

				// 연속 쿠폰 정보 조회 :: 2015-05-11 lwh
				$cp_list = $this->exportmodel->get_export_for_order($coupon['order_seq'], 'coupon');
				foreach($cp_list as $coupon_info){
					if($usetype == 'multi' && $coupon_info['socialcp_status']=='1'){
						$remain_cp[]	= $coupon_info['export_code'];
						$coupon['coupon_remain_value'] = $coupon['coupon_value'];
					}else if($usetype == 'one'){
						if($coupon_info['socialcp_status']==1){
							$remain_cp[]		= $coupon_info['export_code'];
						}else{
							$cp_param['export_code'] = $coupon_info['export_code'];
							$coupons = $this->exportmodel->chk_coupon($cp_param);
							if($coupons['coupon_remain_value']){
								$remain_cp[] = $coupon_info['export_code'];
							}
						}
					}
				}
				$max_coupon = count($remain_cp);
				$this->template->assign(array('max_coupon'=> $max_coupon));
				$this->template->assign(array('remain_cp'=> $remain_cp));

			}else{
				if		($coupon['result'] == 'fail')
					//티켓정보가 정보가 없습니다.
					$err_msg = getAlert('mp031');
				elseif	($coupon['result'] == 'refund')
					//환불된 티켓입니다.
					$err_msg = getAlert('mp032');
				elseif	($coupon['result'] == 'notyet')
					//사용가능한 기간이 아닙니다.
					$err_msg = getAlert('mp033');
				elseif	($coupon['result'] == 'expire')
					//만료된 티켓입니다.
					$err_msg = getAlert('mp034');
				elseif	($coupon['result'] == 'noremain')
					//이미 사용처리 된 티켓입니다.
					$err_msg = getAlert('mp035');
				else
					//사용할 수 없는 티켓입니다.
					$err_msg = getAlert('mp036');
			}

			// 쿠폰 사용 가능여부 체크
			$chk_coupon	= $this->exportmodel->chk_coupon(array('export_code' => $export_code));
			$this->template->assign(array('chk'=> $chk_coupon));

			//티켓 값어치 금액 정수로 처리
			$coupon['coupon_remain_value'] = (int)$coupon['coupon_remain_value'];
		}

		$COMMON_HEADER	= $this->skin.'/_modules/common/html_header.html';
		$this->template->assign(array('err_msg'			=> $err_msg));
		$this->template->assign(array('usetype'			=> $usetype));
		$this->template->assign(array('address'			=> $address));
		$this->template->assign(array('coupon'			=> $coupon));
		$this->template->assign(array('item'			=> $item));

		$this->print_layout($this->template_path());
	}

	// 티켓상품 상세 화면
	public function my_coupon_detail(){
		$this->load->model('couponmodel');
		$download_seq	= trim($_GET['download_seq']);
		$coupon_seq		= trim($_GET['coupon_seq']);

		$data = $this->couponmodel->get_coupon($coupon_seq);
		$coupon = $this->couponmodel->get_download_coupon($download_seq);

		if ($data['issue_priod_type'] == 'day') {
			$data['issue_enddatetitle'] = ($data['after_issue_day']>0) ? getAlert("gv098", $data['after_issue_day']):getAlert("gv099");	//'다운로드 후 '.$data['after_issue_day'].'일 간 사용가능':'다운로드 후 오늘까지만 사용가능';
		}else{
			$data['issue_enddatetitle'] = getAlert("gv100",array(substr($data['issue_enddate'], 5,2),substr($data['issue_enddate'],8,2)));		// substr($data['issue_enddate'], 5,2).'월 '. substr($data['issue_enddate'],8,2).'일 까지 사용가능';
		}

		$issuecategorys	= $this->couponmodel->get_coupon_issuecategory($data['coupon_seq']);

		if($issuecategorys){
			$categoryhtml = array();
			foreach($issuecategorys as $catekey =>$catedata) {
				$categoryhtml[$catekey] = $this->categorymodel -> get_category_name($catedata['category_code']);
			}
			$data['categoryhtml'] = implode(", ",$categoryhtml);
			if($categoryhtml) $data['categoryhtml'] .= ($data['issue_type'] == 'except')?" 카테고리 사용불가":" 카테고리 사용가능";
		}else{
			if($data['issue_type'] != "issue" ) {
				$data['categoryhtml'] = '전체 상품 사용 가능';
			}
		}

		if( $data['coupon_same_time'] == 'N' ) {//단독쿠폰이면
			$data['couponsametimeimg'] = 'sametime';
		}else{
			$data['couponsametimeimg'] = '';
		}

		if ($data['download_enddate']) {
			$data['download_enddatetitle'] = substr($data['download_enddate'], 5,2).'월 '. substr($data['download_enddate'],8,2).'일 까지 다운가능';
		}else{
			$data['download_enddatetitle'] = '다운로드 기간 제한 없음';
		}

		$this->template->assign(array('down_coupon'	=> $coupon));
		$this->template->assign(array('coupon'		=> $data));
		$this->print_layout($this->template_path());
	}


	// 내 할인쿠폰 사용하기
	public function my_coupon_use(){

		$download_seq	= trim($_GET['download_seq']);
		$coupon_seq	= trim($_GET['coupon_seq']);

		if	(!$download_seq || !$coupon_seq){
			//잘못된 접근입니다.
			$err_msg	= getAlert('mp037');
		}else{
			// 쿠폰 상세 정보
			if	($download_seq)	$param['download_seq']	= $download_seq;
			if	($coupon_seq)	$param['coupon_seq']	= $coupon_seq;

			$coupon = $this->couponmodel->get_download_coupon($download_seq);
			$data = $this->couponmodel->get_coupon($coupon_seq);

			if	(!$coupon['coupon_seq']){
				//쿠폰정보가 없습니다.
				$err_msg	= getAlert('mp038');
			}else{
				//이미 사용한 쿠폰입니다.
				if		($coupon['use_status'] != 'unused')	$err_msg = getAlert('mp039');
			}
		}

		$COMMON_HEADER	= $this->skin.'/_modules/common/html_header.html';
		$this->template->assign(array('err_msg'			=> $err_msg));
		$this->template->assign(array('coupon'			=> $data));
		$this->template->define(array('COMMON_HEADER'	=> $COMMON_HEADER));
		$this->template->define(array('LAYOUT'			=> $this->template_path()));
		$this->template->print_('LAYOUT');
	}

	/* 우측 퀵메뉴 wish 리스트 삭제 */
	public function quickWishDel() {
		$msg="fail";
		if($this->userInfo['member_seq']){
			$this->load->model('wishmodel');
			$wish_seq = (int) $_POST['wish_seq'];
			$this->wishmodel->del(array($wish_seq));
			$msg="ok";
		}
		echo $msg;
	}

	public function buy_confirm()
	{
		$this->load->model('exportmodel');

		$export_code = $_GET['export_code'];

		$export = $this->exportmodel->get_export($export_code);
		$export_item = $this->exportmodel->get_export_item($export_code);

		$this->template->assign(array(
			'export'		=> $export,
			'export_item'	=> $export_item,
			'export_code'	=> $export_code,
			'status'		=> $status
		));
		$this->print_layout($this->template_path());
	}

	// 해당 주문의 출고 목록
	public function export_list(){
		login_check();

		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->helper('shipping');

		$order_seq	= trim($_GET['seq']);
		$list_type	= trim($_GET['type']);

		$sSessionOrder	= $this->session->userdata('sess_order');
		$sMemberSeq		= (int) $this->userInfo['member_seq'];

		if (!$order_seq) {
			pageBack(getAlert('os216'));
			exit;
		}

		if (!$sMemberSeq && !$sSessionOrder) {
			pageBack(getAlert('os216'));
			exit;
		}

		if ($sSessionOrder && $order_seq != $sSessionOrder) {
			pageBack(getAlert('os216'));
			exit;
		}

		// 주문 설정
		$this->cfg_order	= ($this->cfg_order) ? $this->cfg_order : config_load('order');

		// 출고목록 데이터 추출
		$export_list		= $this->exportmodel->get_export_for_order($order_seq, $list_type);

		if	($export_list)foreach($export_list as $x => $exp){
		    // 스킨 미패치를 고려하여 바인딩 처리
		    $exp['btn_buyconfirm'] = $exp['buy_confirm_use'];
			$exp['items'] = $this->exportmodel->get_export_item($exp['export_code']);

			// 구매 확정 버튼 활성화 여부 체크
			$this->load->library('buyconfirmlib');
			$buyconfirmInfo = $this->buyconfirmlib->check_buyconfirm($exp);
			$exp['buyconfirmInfo'] = $buyconfirmInfo;

			// 티켓상품일 경우 쿠폰사용 가능 여부
			if	($exp['goods_kind'] == 'coupon'){
				$exp['coupon_check_use']	= $this->exportmodel->chk_coupon(array('export_code' => $exp['export_code']));
				// 사용 횟수
				$exp['coupon_use_value'] = $exp['coupon_input'] - $exp['coupon_remain_value'];
			} else { // 실물상품일 경우
				// 배송지정보
				$exp['shipping'] = $this->ordermodel->get_order($exp['order_seq']);
			}

			// 배송정보(매장수령의 경우)
			if($exp['shipping_method'] == 'direct_store') {
				$shipping_direct_store = $this->ordermodel->get_order_shipping($order_seq,null,$exp['items'][0]['shipping_seq']);
				$exp['direct_store'] = $shipping_direct_store[$exp['shipping_group']];
			}

			if($exp['international_shipping_method']){
				$exp['mdelivery'] = $exp['international_shipping_method'];
				$exp['mdelivery_number'] = $exp['international_delivery_no'];
				$exp['tracking_url'] = "#";
				if($exp['international_shipping_method']!='ups'){
					$exp['tracking_url'] = get_delivery_company(get_international_method_code(strtoupper($exp['international_shipping_method'])),'url').$exp['international_delivery_no'];
				}
			}

			if( !$exp['mdelivery_number'] && $exp['delivery_number'] ){
				$exp['mdelivery'] = $exp['delivery_company_array'][$exp['delivery_company_code']]['company'];
				$exp['mdelivery_number'] = $exp['delivery_number'];
				$exp['tracking_url'] = $exp['delivery_company_array'][$exp['delivery_company_code']]['url'].str_replace('-','',$exp['delivery_number']);
			}

			if(!serviceLimit('H_AD')) {
				unset($exp['provider_name']);
			}

			$export[$x]		= $exp;
		}

		$this->template->assign(array(
			'order_seq'		=> $order_seq,
			'export'		=> $export,
			'buy_confirm_use'		=> $this->cfg_order['buy_confirm_use'],
			'cfg_order'		=> $this->cfg_order
		));

		$file_path = str_replace('export_list','export_list_'.$list_type,$this->template_path());

		if($_GET['popup']){
			$this->template->define(array('tpl'=>$file_path));
			$this->template->print_("tpl");
		}else{
			$this->print_layout($file_path);
		}
	}

	## 사은품 지급 상세 로그  2015-05-14 pjm
	public function gift_use_log(){

		$this->load->model('giftmodel');
		$_POST['order_seq']		= (int) $_POST['order_seq'];

		$giftlog = $this->giftmodel->get_gift_order_log($_POST['order_seq'],$_POST['item_seq']);

		$this->template->assign(array('giftlog'	=> $giftlog[0]));

		$file_path = dirname($this->template_path()).'/gift_use_log.html';
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	// 180727 mypage LNB 파일 추가
	public function mypage_lnb() {
		// 예치금, 포인트 사용여부 추가 :: 2019-01-30 pjw
		$reserve = config_load('reserve');

		// assign 데이터 정의
		$assign_data = array(
			'cash_use'	=> $reserve['cash_use'],
			'point_use' => $reserve['point_use'],
		);

		$this->template->assign($assign_data);
		$this->print_layout($this->template_path());
	}

	private function get_my_orders(array $sc)
	{
		$this->load->model('ordermodel');
		$this->load->model('exportmodel');
		$this->load->helper('shipping');
		$this->load->model('buyconfirmmodel');

		$arr_shipping_method = get_shipping_method('all');

		//
		$orders = $this->ordermodel->get_order_list($sc);

		// 주문 정보 가공 추가 order_catalog()에서 복사 :: 2019-01-30 pjw
		foreach ($orders['record'] as &$data_order) {
			$items = $this->ordermodel->get_item($data_order['order_seq']);

			//
			$reserve = 0;
			$point = 0;
			foreach ($items as &$data_order_item) {
				$data_order_item['options'] = $this->ordermodel->get_option_for_item($data_order_item['item_seq']);
				$data_order_item['suboptions'] = $this->ordermodel->get_suboption_for_item($data_order_item['item_seq']);

				foreach ($data_order_item['options'] as &$data_order_item_option) {
					$data_order_item_option['inputs'] = $this->ordermodel->get_input_for_option($data_order_item_option['item_seq'], $data_order_item_option['item_option_seq']);
					$data_order_item_option['suboptions'] = $this->ordermodel->get_suboption_for_option($data_order_item_option['item_seq'], $data_order_item_option['item_option_seq']);

					$reserve += $data_order_item_option['reserve'] * $data_order_item_option['ea'];
					$point += $data_order_item_option['point'] * $data_order_item_option['ea'];
				}
				foreach ($data_order_item['suboptions'] as $data_order_item_suboption) {
					$reserve += $data_order_item_suboption['reserve'] * $data_order_item_suboption['ea'];
					$point += $data_order_item_suboption['point'] * $data_order_item_suboption['ea'];
				}
				$data_order['goods_kind'][$data_order_item['goods_kind']] = true;
			}

			$data_order['reserve'] = $reserve;
			$data_order['point'] = $point;
			$data_order['items'] = $items;

			/* 주문상품을 배송그룹별로 분할 */
			$shipping_group_items = [];
			foreach ($items as $item) {
				$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost_sum'] += $item['goods_shipping_cost'];
				$shipping_group_items[$item['shipping_seq']]['rowspan'] += count($item['options']) + count($item['suboptions']);
				$shipping_group_items[$item['shipping_seq']]['items'][] = $item;
				$shipping_group_items[$item['shipping_seq']]['totalitems'] += $item['totaloptitems'];
			}

			// 배송비
			$shipping_tot = [];
			foreach ($shipping_group_items as $shipping_seq => $row) {
				$query = $this->db->select('os.*, p.provider_name')
					->from('fm_order_shipping os')
					->join('fm_provider p', 'p.provider_seq = os.provider_seq', 'inner')
					->where('os.shipping_seq', $shipping_seq)
					->get();
				$shipping = $query->row_array();
				$shipping['shipping_method_name'] = $arr_shipping_method[$shipping['shipping_method']];
				$shipping_group_items[$shipping_seq]['shipping'] = $shipping;

				if ($shipping['shipping_method'] == 'delivery') {
					$shipping_tot['basic_cost'] += $shipping['delivery_cost'];
					$shipping_tot['add_shipping_cost'] += $shipping['add_delivery_cost'];
					$shipping_tot['shipping_cost'] += $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
				} elseif ($shipping['shipping_method'] == 'each_delivery') {
					$shipping_tot['goods_cost'] += $shipping['delivery_cost'];
					$shipping_tot['add_shipping_cost'] += $shipping['add_delivery_cost'];
					$shipping_tot['goods_shipping_cost'] += $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
				}
				$shipping_tot['coupon_sale'] += $shipping['shipping_coupon_sale'];
				$shipping_tot['code_sale'] += $shipping['shipping_promotion_code_sale'];
			}
			$shipping_tot['total_shipping_cost'] = $shipping_tot['basic_cost'] + $shipping_tot['goods_cost'] + $shipping_tot['add_shipping_cost'];

			//
			$data_order['shipping_group_items'] = $shipping_group_items;
			$data_order['shipping_tot'] = $shipping_tot;

			//
			if ($data_order['step'] > 40) {
				$data_order['exports'] = $this->exportmodel->get_export_for_order($data_order['order_seq']);

				foreach ($data_order['exports'] as &$data_export) {
					$shipping_arr['international'] = $data_export['international'];
					if ($data_export['international'] == 'domestic') {
						$shipping_arr['shipping_method'] = $shipping_arr['domestic_shipping_method'];
					} else {
						$shipping_arr['shipping_method_international'] = $shipping_arr['international_shipping_method'];
					}
					$data_export['out_shipping_method'] = $this->ordermodel->get_delivery_method($orders);

					$data_export['item'] = $this->exportmodel->get_export_item($data_export['export_code']);

					$data_export['data_buy_confirm'] = $this->buyconfirmmodel->get_log_buy_confirm($data_export['export_seq']);
				}
			}

			if (!serviceLimit('H_AD')) {
				unset($data_order['provider_name']);
			}
		}

		//
		return $orders;
	}
	
	/**
	 * 선물하기 시 배송지 등록 페이지
	 */
	public function present_delivery() {
		$param	= str_replace(" ","+",$this->input->get('params'));
		$param = unserialize(base64_decode($param), ['allowed_classes' => false]);

		// 주문번호 없으면 return
		if (!$param['order_seq']) {
			pageBack(getAlert('os216'));
			exit;
		}
		$this->load->model('ordermodel');
		$this->load->model('exportmodel');
		$this->load->library('orderlibrary');
		$this->load->library('exportlibrary');

		// parameter 데이터와 해당 주문의 수신자 휴대폰과 동일한지 체크	
		$orders = $this->ordermodel->get_order($param['order_seq']);
		if($param['present_receive'] != $orders['recipient_cellphone']) {
			pageBack(getAlert('os216'));
			exit;
		}

		// 주소지 미입력 후 기한 지난지 체크
		$deadline = strtotime($orders['deposit_date'] . '+4 day');
		if(date("Y-m-d") > date("Y-m-d", $deadline)) {
			pageBack(getAlert('mo164'));
			exit;
		}

		$orders['deadline'] = date('Y년 m월 d일', $deadline);

		// 마이페이지용 order replace
		$orders = $this->memberlibrary->replace_mypage_order($orders);
		// 주문상품
		$order_view_front = $this->orderlibrary->order_view_front($orders);
		
		// 배송지 등록전인 경우 true
		$orders['no_receipt_address'] = $orders['recipient_zipcode'] == '' ? true : false;
		
		// 출고정보
		$export_view_front = $this->exportlibrary->export_view_front($orders['order_seq']);		

		$this->template->assign($order_view_front);
		$this->template->assign(['orders'=>$orders]);
		$this->template->assign($export_view_front);
		$this->print_layout($this->template_path());
	}
}
