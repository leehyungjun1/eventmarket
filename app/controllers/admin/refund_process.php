<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class refund_process extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->arr_step 	= config_load('step');

		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('refund_act');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

	}

	// 기존 환불 처리 save 분기 처리 :: 2018-06-01 lwh
	public function save(){
		if($_POST['refund_version']){
			$this->refund_new_save();
		}else{
			$this->refund_old_save();
		}
	}

	public function refund_new_save(){
		
		$this->load->model('ordermodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('emoneymodel');
		$this->load->model('membermodel');
		$this->load->model('eventmodel');
		$this->load->model('connectormodel');
		$this->load->model('salesmodel');
		$this->load->model('accountallmodel');
		$this->load->model('goodsmodel');
		$this->load->helper('text');
		$this->load->helper('order');

		$aPostParams = $this->input->post();
		//comma 제거 pjm
		foreach($aPostParams as $k=>$v){
			if(is_array($v)) foreach($v as $kk=>$vv) $aPostParams[$k][$kk] = str_replace(",","",$vv);
			else $aPostParams[$k] = str_replace(",","",$v);
		}
		
		$cfg_order		= config_load('order');
		$cfg_reserve	= config_load('reserve');	//마일리지/예치금 환경 로드
		$npay_use		= npay_useck();				//npay 사용여부
		
		$order_seq		= $aPostParams['order_seq'];
		$refund_code	= $aPostParams['refund_code'];
		
		## 하위 주문번호 조회(원주문의 맞교환(재주문)건)
		$all_order_seq	= array($order_seq);
		if($aPostParams['top_orign_order_seq']){
			$top_orign_order_seq	= $aPostParams['top_orign_order_seq'];
			$all_order_seq[]		= $aPostParams['top_orign_order_seq'];
		}else{
			$top_orign_order_seq	= $order_seq;
		}
		$aOrderSeqs = $this->ordermodel->get_order_seqs_by_top_orign_order_seq($order_seq);
		if( $aOrderSeqs ){
			foreach($aOrderSeqs as $sTmpOrderSeq){
				if( !in_array($sTmpOrderSeq, $all_order_seq) && $sTmpOrderSeq ){
					$all_order_seq[] = $sTmpOrderSeq;
				}
			}
		}
		$all_order_seq	= array_unique($all_order_seq);
		
		// 신청상태 신청상태 유지 제거 - 관리자 수정 상태 파악의도 :: 2019-02-11 lwh
		if($aPostParams['status'] == 'request'){
			openDialogAlert("환불 처리 상태를 `환불 처리 중` 또는 `환불 완료`로 변경해주세요. ", 450, 150, 'parent');
			exit;
		}
		
		/* 예치금 미사용 시 */
		if($cfg_reserve['cash_use']=='N' && ($aPostParams['refund_cash'] > 0 || $aPostParams['refund_method'] == "cash")){
			openDialogAlert("예치금 환불이 불가능 합니다.<br />설정=>마일리지/포인트/예치금 설정을 확인해 주세요.",400,140,'parent');
			exit;
		}		
		
		// 총 결제금액(현금성+마일리지+예치금)
		$data_order			= $this->ordermodel->get_order($top_orign_order_seq);
		$data_refund		= $this->refundmodel->get_refund($refund_code);
		$pay_price			= $data_order['settleprice'] + $data_order['emoney'] + $data_order['cash'];
		$shipping_price		= $data_order['shipping_cost'];		
		$data_return		= $this->returnmodel->get_return_refund_code($refund_code);
		
		// 톡체크아웃 환불건은 관리자에서 처리할 수 없음
		$kakao_pay_chk = talkbuy_useck();
		if ($kakao_pay_chk && $data_order["talkbuy_order_id"] && $data_order["pg"] === "talkbuy") {
			openDialogAlert("톡체크아웃의 대한 환불처리는 불가능합니다.",400,140,'parent',$callback);
			exit;
		}
		
		// 반품이 완료되지 않은 경우 환불 불가
		if	($data_refund['refund_type'] == 'return' && $aPostParams['status'] == 'complete' && $data_return['status'] != 'complete'){
			openDialogAlert('반품이 완료되지 않았습니다.<br/>반품을 먼저 완료해 주시기 바랍니다.', 500, 170, 'parent', '');
			exit;
		}
		# npay 반품환불/주문취소 환불 승인
		# 처리가능작업 :
		#	- 환불신청 -> 환불완료(O)
		#	- 환불신청 -> 환불처리중(X)
		#	- 환불처리중 -> 환불완료(X)
		#	- 환불처리중 -> 환불신청(X)
		
		if($npay_use && $data_order['pg'] == "npay"){

			// 네이버페이 환불승인은 건별 처리 됨.
			foreach($aPostParams['refund_item_for_ship'] as $_shipping_seq => $_item_seq){
				if(!$shipping_seq) $shipping_seq		= $_shipping_seq;
			}

			// 동일 배송그룹의 총 주문 금액(상품금액 + 배송비)
			$data_refund_item	= $this->refundmodel->get_refund_item($refund_code,$all_order_seq);
			$data_shipping		= $this->ordermodel->get_order_shipping($top_orign_order_seq,'',$shipping_seq);
			$ships = array();
			foreach($data_shipping as $_data){ $ships[$_data['shipping_seq']] = $_data; }

			$total_shipping_group_price = $ships[$shipping_seq]['shipping_cost'];

			foreach($data_refund_item as $_data){
				if($_data['shipping_seq'] == $shipping_seq){
					$total_shipping_group_price += ($_data['price'] * $_data['refund_ea']);
				}
			}

			// 동일 배송그룹의 환불금액
			$complete_refund_data	= $this->refundmodel->get_refund_group_price($order_seq,$shipping_seq);
			// 배송비 포함한 환불 총액
			$complete_refund_price	= $complete_refund_data['refund_goods_price']
										+ $complete_refund_data['refund_goods_emoney']
										+ $complete_refund_data['refund_goods_cash']
										+ $complete_refund_data['refund_shipping_emoney']
										+ $complete_refund_data['refund_shipping_cash']
										+ $complete_refund_data['refund_shipping_cost'];

			// 동일 배송그룹의 배송비 환불 완료 금액
			$complete_refund_delivery_price = $complete_refund_data['refund_shipping_emoney'] + $complete_refund_data['refund_shipping_cash'] + $complete_refund_data['refund_shipping_cost'];

			// 환불요청금액(배송비포함)
			$post_refund_price += array_sum($aPostParams['refund_goods_price']) + array_sum($aPostParams['refund_delivery_price_tmp']);

			if($complete_refund_delivery_price + array_sum($aPostParams['refund_delivery_price_tmp']) > $ships[$shipping_seq]['shipping_cost']){
				$errMsg = "주문시 결제한 배송비보다 환불 배송비가 더 큽니다.<br />새로고침 후 다시 진행해 주세요.";
				openDialogAlert($errMsg, 450, 170, 'parent', '');
				exit;
			}

			if(($complete_refund_price+$post_refund_price) > $total_shipping_group_price){
				$errMsg = "환불 금액 오류입니다. 새로고침 후 다시 진행해 주세요.";
				openDialogAlert($errMsg, 450, 140, 'parent', '');
				exit;
			}

			$npay_result		= $this->_naverpay_cancel($aPostParams, $refund_code);
			if($npay_result){
				$aPostParams['status'] = $npay_result;
			}
			$refund_price		= '0';
			$refund_delivery	= '0';
			
		}else{
			$npay_use = false;
			$_refund_goods_price		= array_sum($aPostParams['refund_goods_price']);
			$_refund_cash_tmp			= array_sum($aPostParams['refund_cash_tmp']);
			$_refund_emoney_tmp			= array_sum($aPostParams['refund_emoney_tmp']);
			$_refund_delivery_price_tmp	= array_sum($aPostParams['refund_delivery_price_tmp']);
			$_refund_delivery_cash_tmp	= array_sum($aPostParams['refund_delivery_cash_tmp']);
			$_refund_delivery_emoney_tmp= array_sum($aPostParams['refund_delivery_emoney_tmp']);
			$refund_shipping_price		= $aPostParams['refund_shipping_price'];	// 반품배송비
			$cash_refund_shipping_price = $refund_shipping_price; // 환급할 예치금 배송비
			$refund_pg_price_sum		= 0;
			$refund_pg_delivery_sum		= 0;
			$refund_complete			= 0;
			$refund_complete_delivery	= 0;
			$delivery_beginning_price	= 0;

			// 3차 환불 개선으로 변수 추가 :: 2018-11- lkh
			$refund_deductible_price	= $aPostParams['refund_deductible_price'] ? $aPostParams['refund_deductible_price'] : 0;
			$refund_delivery_deductible_price	= $aPostParams['refund_delivery_deductible_price'] ? $aPostParams['refund_delivery_deductible_price'] : 0;
			$refund_penalty_deductible_price	= $aPostParams['refund_penalty_deductible_price'] ? $aPostParams['refund_penalty_deductible_price'] : 0;
			$refund_all_deductible_price = $refund_deductible_price + $refund_delivery_deductible_price + $refund_penalty_deductible_price;

			
			if( $data_order['settleprice'] == 0 && $data_order['cash'] > 0 && $data_order['emoney'] == 0 && ($_refund_goods_price > 0 || $_refund_emoney_tmp > 0  || $_refund_delivery_price_tmp > 0 || $_refund_delivery_emoney_tmp > 0) ){
				openDialogAlert("전액 예치금 결제일 경우 예치금 환불만 가능합니다.", 400, 190, 'parent');
				exit;
			}
			
			if( $data_order['settleprice'] == 0 && $data_order['emoney'] > 0 && $data_order['cash'] == 0 && ($_refund_goods_price > 0 || $_refund_cash_tmp > 0 || $_refund_delivery_price_tmp > 0 || $_refund_delivery_cash_tmp > 0) ){
				openDialogAlert("전액 적립금 결제일 경우 적립금 환불만 가능합니다.", 400, 190, 'parent');
				exit;
			}
			
			// 최종환불액(상품+배송)
			// 예치금 환불을 위해 예치금도 추가 :: 2018-08-09 pjw
			$refund_price		= $_refund_goods_price + $_refund_cash_tmp + $_refund_emoney_tmp + $_refund_delivery_price_tmp + $_refund_delivery_cash_tmp + $_refund_delivery_emoney_tmp;
			$refund_pg_price	= $_refund_goods_price;

			//환불배송비 합(관리자입력)
			$refund_delivery	= $_refund_delivery_price_tmp + $_refund_delivery_cash_tmp + $_refund_delivery_emoney_tmp;
			$refund_pg_delivery = $_refund_delivery_price_tmp;

			// 3차 환불 개선으로 변수 추가 :: 2018-11- lkh
			$refund_price		-= $refund_all_deductible_price;// 환불총액 - 조정금액 전체(상품+배송비+환불위약금)			
			$refund_price		-= $refund_shipping_price;		// 환불총액 - 반품배송비
			
			# 최종환불액(결제통화기준)
			# 환불(배송비) 경우에도 refund_pg_price 계산 필요 2019-12-09 by hyem
			if($refund_pg_price > 0 || $refund_pg_delivery > 0){
				// 3차 환불 개선으로 변수 추가 :: 2018-11- lkh
				$refund_pg_price_sum = $refund_pg_price + $refund_pg_delivery - $refund_all_deductible_price - $refund_shipping_price;
				
				//실 환불금액이 0보다 작은 경우에는 예치금 환불금에서 반품배송비 차감
				//아닌 경우에는 이미 실 결제금액에서 반품배송비 차감하여 예치금으로 환급할 배송비가 없음
				if($refund_pg_price_sum < 0) {
					$refund_pg_price_sum		= 0;
				}
				
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency'] && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_pg_price_sum = get_currency_exchange($refund_pg_price_sum, $data_order['pg_currency'], '', 'front');
				}
			}
			
			$data_refund['refund_pg_price'] = $refund_pg_price_sum;
			
			# 환불배송비 합(결제통화기준)
			if($refund_pg_delivery > 0){
				$refund_pg_delivery_sum = $refund_pg_delivery;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency'] && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_pg_delivery_sum = get_currency_exchange($refund_pg_delivery,$data_order['pg_currency'],'','front');
				}
			}
			
			//동일주문의 기 환불금액 현금성 마일리지, 예치금
			$data_refund_item			= $this->refundmodel->get_refund_item($refund_code, $all_order_seq);
			$refund_comp				= $this->refundmodel->get_refund_complete_price($all_order_seq);
			if( $refund_comp['refund_goods_price'] )	$refund_complete_price		+= $refund_comp['refund_goods_price'];
			if( $refund_comp['refund_delivery_price'] )	$refund_complete_delivery	+= $refund_comp['refund_delivery_price'];
			if( $refund_comp['refund_cash'] )	$refund_complete_price		+= $refund_comp['refund_cash'];
			if( $refund_comp['refund_delivery_cash'] )	$refund_complete_delivery	+= $refund_comp['refund_delivery_cash'];
			if( $refund_comp['refund_emoney'] )	$refund_complete_price		+= $refund_comp['refund_emoney'];
			if( $refund_comp['refund_delivery_emoney'] )	$refund_complete_delivery	+= $refund_comp['refund_delivery_emoney'];
			
			if( $refund_comp['coupon_deduction_price'] )	$refund_coupon_deduction_price	+= $refund_comp['coupon_deduction_price'];

			$refund_complete_pg_price = $refund_comp['refund_goods_price'] + $refund_comp['refund_delivery_price'];		//예치금, 마일리지 제외한 동일주문의 기환불금액

			// 네이버페이 결제건 환불 시 결제할 때 사용한 마일리지/예치금보다 더 큰 금액일때
			if ($data_order['pg'] == 'naverpayment') {
				if ($data_order['emoney'] < $refund_comp['refund_emoney'] + $aPostParams['refund_emoney']) {
					openDialogAlert('결제 시 사용한 마일리지를 초과한 금액으로 환불은 불가합니다.', 500, 140, 'parent');
					exit;
				}
				if ($data_order['cash'] < $refund_comp['refund_cash'] + $aPostParams['refund_cash']) {
					openDialogAlert('결제 시 사용한 예치금을 초과한 금액으로 환불은 불가합니다.', 500, 140, 'parent');
					exit;
				}
			}

			$all_refund_option_arr		= array();
			foreach($data_refund_item as $k => $data){
				$refund_option_info			= $data_refund['order_seq']."".$data['opt_type']."".$data['option_seq'];
				if(!in_array($refund_option_info,$all_refund_option_arr)){
					$all_refund_option_arr[]	= $refund_option_info;
				}

				if(!$shipping_seq) $shipping_seq = $data['shipping_seq'];
			}

			// 배송그룹내 마지막 환불 코드
			$refund_maxcode = $this->refundmodel->shipping_refund_maxcode_duty($data_refund['order_seq']);

			// 배송그룹별 주문수량, 취소수량, 배송수량
			$rest_ea_data = $this->refundmodel->shipping_refund_ea($shipping_seq);

			// 주문번호에 대한 남은 반품개수
			$rest_unrefund_ea = $this->refundmodel->shipping_unrefund_order($shipping_seq);
			
			
			// 결제 취소 건을 뺀 나머지 반품건 수
			$unrefund_ea = $rest_unrefund_ea['total_unrefund_ea'] - $rest_ea_data['cancel_ea'];

			# 최초 배송비 환불
			#	배송그룹내 출고전(배송안함) 수량이 없고 남은 반품수량이 없고 and 
			#	(
			#		현재 환불코드가 마지막 환불코드 이거나 or
			#		반품귀책사유가 판매자에게 있거나 or
			#		반품이 아닌 주문취소로 인한 환불 일때
			#	)
			$deliv_refurn = 'N';
			if($rest_ea_data != null){
				if($unrefund_ea == 0 && ($data_return['refund_ship_duty'] == "seller" || ($refund_maxcode == $data_refund['refund_code'] || $data_refund['refund_type'] == 'cancel_payment' ))){
					$deliv_refurn = 'Y';
				}
			}

			// 배송비 환불일 경우 무조건 노출 :: 2018-07-23 pjw
			if($data_refund['refund_type'] == 'shipping_price'){
				$deliv_refurn = 'Y';
			}

			# 배송비 환불이 불가할 때(반품귀책사유가 고객에게 있거나 출고건이 남아 있을 떄 등)
			# 이미 환불해준 배송비가 있다면 최초배송비를 중복검사할 필요 없음
			# 총 환불 가능 금액에서 주문시 최초 배송비를 제외한다.
			$delivery_beginning_price = 0;
			if($data_refund['refund_type'] != "cancel_payment" && $refund_complete_delivery == '0'){
				if(!isset($aPostParams['refund_delivery_price_tmp']) && $deliv_refurn != 'Y'){
					foreach($aPostParams['refund_item_for_ship'] as $shipping_seq => $item_seq){
						# 동일 배송그룹의 최초 배송비(기본배송비 or 개별배송비 가져오기 + 추가배송비)
						$delivery_beginning_price	+= $this->ordermodel->get_delivery_existing_price($top_orign_order_seq,$shipping_seq);
					}
				}
			}

			// 환불액이 환불가능금액 보다 클경우 경고창.
			// 기 환불 배송비 추가 :: 2018-08-09 pjw
			// 환불가능금액 = 결제금액 - 기환불 상품금액 - 기환불 배송비 - 최초배송비(조건)
			$refund_remain		= $pay_price - $refund_complete_price - $refund_complete_delivery - $delivery_beginning_price - $refund_coupon_deduction_price;
			if($refund_remain < $refund_price){
				openDialogAlert("환불이 불가 합니다.<br />환불금액(".get_currency_price($refund_price,2).")은 환불가능 금액(".get_currency_price($refund_remain,2).")을 초과하는 금액입니다.",400,190,'parent');
				exit;
			}
			
			//마일리지 환불 유효기간 체크
			if( $data_order['member_seq'] && ($_refund_emoney_tmp + $_refund_delivery_emoney_tmp) > 0 ){
				if($aPostParams['refund_emoney_limit_type'] == "n"){
					$aPostParams['refund_emoney_limit_date'] = "";
				}elseif(empty($aPostParams['refund_emoney_limit_date']) || $aPostParams['refund_emoney_limit_date'] < date("Y-m-d", mktime()) ){
					openDialogAlert("마일리지 환불 유효기간은 오늘 이후(" . date("Y-m-d", mktime()) . ")로 설정하셔야 합니다.", 500, 140, 'parent');
					exit;
				}
			}
			
			//배송비 환불의 경우 예외처리
			$base_refund_info	= $this->refundmodel->get_refund($aPostParams['refund_code']);
			if($base_refund_info['refund_type'] == 'shipping_price'){
				foreach((array)$aPostParams['refund_delivery_price_tmp'] as $item_seq => $item_val){
					$aPostParams['refund_item_seq'][$item_seq]	= $item_seq;
					$aPostParams['refund_ea'][$item_seq]		= '1';		// 배송비는 무조건 ea=1 2020-01-07
				}
			}
		}
		
		// 전액 예치금, 적립금 결제 일 경우
		
		// 배송그룹별 환불금액 저장 :: 2018-06-08 lwh
		foreach($aPostParams['refund_item_for_ship'] as $ship_seq => $item_seq){
			$refund_delivery_price[$item_seq]		= $aPostParams['refund_delivery_price_tmp'][$ship_seq];
			$refund_delivery_emoney[$item_seq]		= $aPostParams['refund_delivery_emoney_tmp'][$ship_seq];
			$refund_delivery_cash[$item_seq]		= $aPostParams['refund_delivery_cash_tmp'][$ship_seq];
		}
		
		$aPostParams['adjust_use_coupon']		= 0;
		$aPostParams['adjust_use_promotion']	= 0;
		$aPostParams['adjust_use_emoney']		= 0;
		$aPostParams['adjust_use_cash']			= 0;
		$aPostParams['adjust_use_enuri']		= 0;
		$aPostParams['adjust_refund_price']		= 0;
		
		/* 환불 정보 저장 */
		$saveData = array(
			'adjust_use_coupon'			=> get_cutting_price($aPostParams['adjust_use_coupon']),
			'adjust_use_promotion'		=> get_cutting_price($aPostParams['adjust_use_promotion']),
			'adjust_use_emoney'			=> get_cutting_price($aPostParams['adjust_use_emoney']),
			'adjust_use_cash'			=> get_cutting_price($aPostParams['adjust_use_cash']),
			'adjust_use_enuri'			=> get_cutting_price($aPostParams['adjust_use_enuri']),
			'adjust_refund_price'		=> get_cutting_price($aPostParams['adjust_refund_price']),
			'refund_method'				=> $aPostParams['refund_method'],
			'refund_price'				=> get_cutting_price($refund_price),
			'refund_emoney'				=> get_cutting_price($aPostParams['refund_emoney']),
			'refund_emoney_limit_date'	=> $aPostParams['refund_emoney_limit_date'],
			'refund_cash'				=> get_cutting_price($aPostParams['refund_cash']),
			'refund_delivery'			=> get_cutting_price($refund_delivery),
			'refund_pg_price'			=> $refund_pg_price_sum,
			'refund_pg_delivery'		=> $refund_pg_delivery_sum,
			'refund_ordersheet'			=> $aPostParams['refund_ordersheet'],
			'refund_deductible_price'	=> $refund_deductible_price,
			'refund_delivery_deductible_price'	=> $refund_delivery_deductible_price,
			'refund_penalty_deductible_price'	=> $refund_penalty_deductible_price,
		);
		
		$this->db->where('refund_code', $refund_code);
		$this->db->update("fm_order_refund",$saveData);
		
		$data_refund['refund_price']	= $refund_price;
		$data_refund['refund_emoney']	= $aPostParams['refund_emoney'];
		$data_refund['refund_cash']		= $aPostParams['refund_cash'];
		$data_refund['refund_ordersheet']	= $aPostParams['refund_ordersheet'];
		$data_refund['refund_method']	= $aPostParams['refund_method'];
		// 3차 환불 개선으로 변수 추가 :: 2018-11- lkh
		$data_refund['refund_deductible_price']				= $refund_deductible_price;
		$data_refund['refund_delivery_deductible_price']	= $refund_delivery_deductible_price;
		$data_refund['refund_penalty_deductible_price']		= $refund_penalty_deductible_price;
		
		$refund_provider = array();
		foreach($aPostParams['refund_item_seq'] as $refund_item_seq){
			
			$refund_goods_price			= str_replace(",","",$aPostParams['refund_goods_price'][$refund_item_seq]);
			$refund_goods_promotion		= str_replace(",","",$aPostParams['refund_goods_promotion'][$refund_item_seq]);
			$refund_goods_coupon		= str_replace(",","",$aPostParams['refund_goods_coupon'][$refund_item_seq]);
			$refund_delivery_coupon		= str_replace(",","",$aPostParams['refund_delivery_coupon'][$refund_item_seq]);
			$refund_delivery_promotion	= str_replace(",","",$aPostParams['refund_delivery_promotion'][$refund_item_seq]);
			
			// 배송관련 추가 필드 저장 :: 2018-06-08 lwh
			$refund_delivery_price		= str_replace(",","",$aPostParams['refund_delivery_price_tmp'][$refund_item_seq]);
			$refund_delivery_cash		= str_replace(",","",$aPostParams['refund_delivery_cash_tmp'][$refund_item_seq]);
			$refund_delivery_emoney		= str_replace(",","",$aPostParams['refund_delivery_emoney_tmp'][$refund_item_seq]);
			
			// 상품개당 적립금 및 예치금 계산 적용 :: 2018-06-07 lwh
			// 정산의 계산 방식과 동일하게 수정 by hed 2019-06-18 
			$refund_emoney				= str_replace(",","",$aPostParams['refund_emoney_tmp'][$refund_item_seq]);
			$refund_cash				= str_replace(",","",$aPostParams['refund_cash_tmp'][$refund_item_seq]);
			$refund_ea					= $aPostParams['refund_ea'][$refund_item_seq];
			
			// 정산 계산방식과 동일하게 수정하기 위해 임의로 나누기 비율을 설정.
			// 금액 비율로 나눠지는 것이 아닌 개당으로 나눠져야 함.
			$set_ratio_array = array(
				'0' => array(
					'sale_ratio_unit' => 1/$refund_ea * 100
					, 'ea' => $refund_ea
				)
			);
			$this->accountallmodel->_tmp_emoney_rest = array();
			$set_ratio_array_emoney = $this->accountallmodel->calculate_promotion_unit($refund_emoney, '0', $set_ratio_array, 'emoney');
			$emoney_sale_unit			= $set_ratio_array_emoney[0]['emoney_sale_unit'];
			$emoney_sale_rest			= $set_ratio_array_emoney[0]['emoney_sale_rest'] + $this->accountallmodel->_tmp_emoney_rest['emoney'];
			
			$set_ratio_array_cash = $this->accountallmodel->calculate_promotion_unit($refund_cash, '0', $set_ratio_array, 'cash');
			$refund_cash_unit			= $set_ratio_array_cash[0]['cash_sale_unit'];
			$refund_cash_rest			= $set_ratio_array_cash[0]['cash_sale_rest'] + $this->accountallmodel->_tmp_emoney_rest['cash'];
			
			# 상품 환불금액(결제통화기준)
			if($refund_goods_price > 0){
				$refund_goods_pg_price = $refund_goods_price;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency']  && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_goods_pg_price = get_currency_exchange($refund_goods_price,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_goods_pg_price = 0;
			}
			# 배송비 환불금액(결제통화기준)
			if($refund_delivery_price > 0){
				$refund_delivery_pg_price = $refund_delivery_price;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency']  && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_delivery_pg_price = get_currency_exchange($refund_delivery_price,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_delivery_pg_price = 0;
			}
			
			$saveData = array(
				'refund_goods_price'		=> get_cutting_price($refund_goods_price),
				'refund_goods_pg_price'		=> $refund_goods_pg_price,
				'refund_goods_coupon'		=> get_cutting_price($refund_goods_coupon),
				'refund_goods_promotion'	=> get_cutting_price($refund_goods_promotion),
				'refund_delivery_price'		=> get_cutting_price($refund_delivery_price),
				'refund_delivery_pg_price'	=> $refund_delivery_pg_price,
				'refund_delivery_cash'		=> $refund_delivery_cash,
				'refund_delivery_emoney'	=> $refund_delivery_emoney,
				'refund_delivery_coupon'	=> get_cutting_price($refund_delivery_coupon),
				'refund_delivery_promotion'	=> get_cutting_price($refund_delivery_promotion),
				'emoney_sale_unit'			=> get_cutting_price($emoney_sale_unit),
				'emoney_sale_rest'			=> get_cutting_price($emoney_sale_rest),
				'cash_sale_unit'			=> get_cutting_price($refund_cash_unit),
				'cash_sale_rest'			=> get_cutting_price($refund_cash_rest),
			);
			
			$this->db->where('refund_item_seq', $refund_item_seq);
			$this->db->update("fm_order_refund_item",$saveData);
			
			/* 입점사별 환불 정보 pjm */
			$provider_seq = $aPostParams['refund_provider_seq'][$refund_item_seq];
			if($provider_seq){
				$refund_provider[$provider_seq]['provider_seq']			= $provider_seq;
				$refund_provider[$provider_seq]['refund_expect_price']	= 0;
				$refund_provider[$provider_seq]['adjust_refund_price']	+= $refund_goods_price+$refund_delivery_price;
				$refund_provider[$provider_seq]['refund_price']			+= $refund_goods_price+$refund_delivery_price;
			}
		}
		
		/* 입점사별 환불 정보 pjm */
		foreach($refund_provider as $provider_data){
			$this->refundmodel->set_provider_refund($refund_code, $provider_data);
		}
		
		
		/* 저장된 정보 로드 */
		$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
		$data_order_item	= $this->ordermodel->get_item($data_refund['order_seq']);
		$data_member		= $this->membermodel->get_member_data($data_order['member_seq']);
		
		$order_total_ea = $this->ordermodel->get_order_total_ea($data_refund['order_seq']);
		$refund_total_ea = 0;
		if($data_refund_item) foreach($data_refund_item as $item) 	$refund_total_ea += $item['ea'];
		
		if(!$npay_use){
			//this->refundmodel->get_refund_item :: 반품건 외 전체 주문아이템 불러옴.
			//복합과세 결제여부 : 전체주문아이템중 비과세 상품 찾기 @2015-06-02 pjm
			$tmp_tax	= array();
			$free_tax	= "n";
			if($data_order_item){
				foreach($data_order_item as $item){
					$tmp_tax[]		= $item['tax'];
					if($item['tax'] == "exempt") $free_tax = "y";
				}
			}
			
			//kcp 전체 비과세일때 복합과세로 전송되도록 수정
			if( !in_array("tax",$tmp_tax) && $free_tax == "n" ) $free_tax = "y";
			
			$data_refund['free_tax'] = $free_tax;
			
			//환북액 과세/비과세 금액 나누기  @2015-06-02 pjm
			$data_refund['tax_price']	= "0";
			$data_refund['free_price']	= "0";
			if($data_refund_item){
				foreach($data_refund_item as $item){
					$refund_seq		= $item['refund_item_seq'];
					$refund_deliv	+= $aPostParams['refund_delivery_price_tmp'][$refund_seq];
					//과세
					if($item['tax'] == "tax"){
						$data_refund['tax_price'] += $aPostParams['refund_goods_price'][$refund_seq];
					}elseif($item['tax'] == "exempt"){
						$data_refund['free_price'] += $aPostParams['refund_goods_price'][$refund_seq];
					}
				}
			}
			
			//과세상품이 한건이라도 있으면 배송비는 과세, 전체 비과세 주문일때만 배송비 비과세.  @2015-06-02 pjm
			if(in_array("tax",$tmp_tax)){
				$data_refund['tax_price'] += $refund_deliv;
			}else{
				$data_refund['free_price'] += $refund_deliv;
			}
			
			if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		}
		// 과세 대상 환불 금액을 기준으로 과세금액와 부과세를 계산
		$data_refund['comm_tax_mny']	= 0;		// 과세금액
		$data_refund['comm_vat_mny']	= 0;		// 부과세
		if($data_refund['tax_price']){
			$vat = $cfg_order['vat'] ? $cfg_order['vat'] : 10;
			$sum_price		= $data_refund['tax_price'];
			$tax_price		= round($sum_price / (1 + ($vat / 100)));
			if($sum_price>$tax_price){
				$data_refund['comm_tax_mny']	= $tax_price;
				$data_refund['comm_vat_mny']	= $sum_price - $tax_price;
			}
		}
		
		// 올앳 & 카카오페이 의 경우 과세,부과세 금액을 주문 내역을 기준으로 계산
		$pgCompany = $this->config_system['pgCompany'];
		if($pgCompany=="allat" || $data_order['pg']=="kakaopay" || $data_order['pg']=="kicc"){
			// 전체 과세금액 추출
			$refund_type = "complete";
			$order_seq = $data_refund['order_seq'];
			# 주문 데이터를 토대로 과세상품액, 비과세액, 과세 배송비금액 구해오기
			$all_order_list		= $this->ordermodel->get_order($order_seq);
			$tax_invoice_type	= ($all_order_list['typereceipt'] == 1) ? true : false;		//세금 계산서 신청여부
			// 환불가능 과세금액 계산
			$order_tax_prices	= $this->ordermodel->get_order_prices_for_tax($order_seq,$all_order_list,$tax_invoice_type,$refund_type);
			
			$data_tax = $this->salesmodel->tax_calulate(
				$order_tax_prices["tax"],
				$order_tax_prices["exempt"],
				$order_tax_prices["shipping_cost"],
				$order_tax_prices["sale"],
				$order_tax_prices["tax_sale"],'SETTLE',$order_tax_prices['tax_goods_cnt']);
			
			$supply			= get_cutting_price($data_tax['supply']);
			$surtax			= get_cutting_price($data_tax['surtax']);
			$taxprice		= get_cutting_price($data_tax['supply']) + get_cutting_price($data_tax['surtax']);
			
			// 남은 환불가능 과세금액과 환불예정 과세금액이 동일할 경우
			// 전체 과세금액으로부터 과세,부과세를 역산한다.
			if($data_refund['tax_price']==$taxprice && $taxprice > 0){
				// 전체 공급가액 계산
				$tot_tax_prices	= $this->ordermodel->get_order_prices_for_tax($order_seq,$all_order_list,$tax_invoice_type,"all_order");
				$tot_data_tax = $this->salesmodel->tax_calulate(
					$tot_tax_prices["tax"],
					$tot_tax_prices["exempt"],
					$tot_tax_prices["shipping_cost"],
					$tot_tax_prices["sale"],
					$tot_tax_prices["tax_sale"],'SETTLE');
				$tot_supply		= get_cutting_price($tot_data_tax['supply']);
				$tot_surtax		= get_cutting_price($tot_data_tax['surtax']);
				
				// 기존환불 과세금액 계산
				$re_tax_refund_data_list = $this->refundmodel->get_refund_for_order($data_refund['order_seq']);
				$re_tax_sum_tax_price = 0;
				$re_tax_sum_comm_tax_mny = 0;
				$re_tax_sum_comm_vat_mny = 0;
				$re_tax_sum_free_price = 0;
				foreach($re_tax_refund_data_list as $re_tax_refund_data){
					if($re_tax_refund_data['status']=='complete'){
						$re_tax_sum_tax_price += $re_tax_refund_data['tax_price'];
						$re_tax_sum_comm_tax_mny += $re_tax_refund_data['comm_tax_mny'];
						$re_tax_sum_comm_vat_mny += $re_tax_refund_data['comm_vat_mny'];
						$re_tax_sum_free_price += $re_tax_refund_data['freeprice'];
					}
				}
				// 검산 : 기 환불금액
				if($re_tax_sum_tax_price+$re_tax_sum_free_price != ($refund_complete_pg_price)){
					openDialogAlert('기환불금액 오류<br/> 기환불금액('.get_currency_price($refund_complete_pg_price,3).')이 과세금액('.get_currency_price($re_tax_sum_tax_price,3).')와 면세금액('.get_currency_price($re_tax_sum_free_price,3).')의 합과 다릅니다.',400,190,'parent');
					exit;
				}
				// 검산 : 기환불 과세금액
				if($re_tax_sum_tax_price != ($re_tax_sum_comm_tax_mny+$re_tax_sum_comm_vat_mny)){
					openDialogAlert('기환불금액 오류<br/> 과세금액('.get_currency_price($re_tax_sum_tax_price,3).')이 공급가액('.get_currency_price($re_tax_sum_comm_tax_mny,3).')와 부가세('.get_currency_price($re_tax_sum_comm_vat_mny,3).')의 합과 다릅니다.',400,190,'parent');
					exit;
				}
				
				$re_tax_comm_tax_mny = $tot_supply - $re_tax_sum_comm_tax_mny;
				$re_tax_comm_vat_mny = $taxprice - $re_tax_comm_tax_mny;
				
				// 검산 : 환불요청 금액 //  $aPostParams['refund_price'] 기존 데이터에는 마일리지가 포함되어 있으므로 순수 환불 금액으로 재계산 by hed
				$real_refund_price = $_refund_goods_price + $_refund_delivery_price_tmp;
				if($real_refund_price != ($re_tax_comm_tax_mny+$re_tax_comm_vat_mny+$data_refund['free_price'])){
					openDialogAlert('환불요청 금액 오류<br/> 환불요청 금액('.get_currency_price($real_refund_price,3).')이 공급가액('.get_currency_price($re_tax_comm_tax_mny,3).')와 부가세('.get_currency_price($re_tax_comm_vat_mny,3).')와 비과세('.get_currency_price($data_refund['free_price'],3).')의 합과 다릅니다.',400,190,'parent');
					exit;
				}
				
				$data_refund['comm_tax_mny'] = $re_tax_comm_tax_mny;
				$data_refund['comm_vat_mny'] = $re_tax_comm_vat_mny;
			}
		}
		
		// 환불 과세 정보 저장
		$taxSaveData = array(
			'tax_price'		=> get_cutting_price($data_refund['tax_price']),
			'comm_tax_mny'		=> get_cutting_price($data_refund['comm_tax_mny']),
			'comm_vat_mny'		=> get_cutting_price($data_refund['comm_vat_mny']),
			'freeprice'	=> get_cutting_price($data_refund['free_price']),
		);
		
		$this->db->where('refund_code', $refund_code);
		$this->db->update("fm_order_refund",$taxSaveData);
		
		// allat 의 경우 인코딩 전에 데이터를 처리해야하므로 과세 비과세 부과세 추출
		if($aPostParams['get_allat_multi_amt']){
			echo json_encode($data_refund);
			exit;
		}
		
		$saveData = array();
		$saveData['status'] = $aPostParams['status'];
		
		/* 환불신청 또는 환불처리중에서 환불완료로 변경될때 */
		if(!$npay_use && $data_refund['status']!='complete' && $aPostParams['status']=='complete')
		{
			$this->load->model('couponmodel');
			$this->load->model('promotionmodel');
			
			$saveData['refund_date'] = date('Y-m-d H:i:s');
			$saveData['manager_seq'] = $this->managerInfo['manager_seq'];
			
			/* 무통장 환불 처리 */
			if(in_array($aPostParams['refund_method'],array('bank','manual','cash','emoney')))
			{
				// 별다른 처리 없음
			}
			/* PG 결제취소 처리 */
			// 환불 금액이 0원 이상인 경우에만 PG 환불 진행
			else if ($refund_pg_price_sum > 0)
			{
				if(!$data_order['payment_price'] && $data_order['pg_currency'] == $this->config_system['basic_currency']){
					$data_order['payment_price'] = $data_order['settleprice'];
				}
				
				if($data_order['payment_price'] < $refund_pg_price_sum){
					openDialogAlert("환불금액이 실결제금액보다 클 수 없습니다.",400,140,'parent');
					exit;
				}
				
				$pgCompany = $this->config_system['pgCompany'];
				
				// 카카오 페이의 PG사를 추출하기 위한 데이터 :: 2015-02-25 lwh
				switch($data_order['pg']){
					case 'kakaopay':
					case 'payco':
					case 'naverpayment':
						$pglog_tmp				= $this->ordermodel->get_pg_log($data_order['order_seq']);
						$pg_log_data			= $pglog_tmp[0];
						$data_order['pg_log']	= $pg_log_data;
						$pgCompany				= $data_order['pg'];
						break;
					case 'paypal':
						$pgCompany				= $data_order['pg'];
						break;
					case 'eximbay':
						$pgCompany				= $data_order['pg'];
						break;
				}
				
				$pgCancelType = $data_refund['cancel_type'];
				
				/* 카드일땐 금액에 따라 전체취소할지 부분취소할지 결정함 */
				if($data_order['settleprice']== $refund_pg_price_sum && $order_total_ea==$refund_total_ea){
					// 전체금액일땐 전체취소
					$data_refund['cancel_type'] = 'full';
				}else{
					// 부분금액일땐 부분취소
					$data_refund['cancel_type'] = 'partial';
				}
				/* PG 부분취소 */
				if($data_refund['cancel_type']=='partial')
				{
					$cancelMessage = "부분매입취소 실패";
				}
				/* PG 전체취소 */
				else
				{
					if($data_order['settleprice'] != $refund_pg_price_sum ){
						openDialogAlert("PG 전체취소시에는 결제금액과 환불금액이 동일해야합니다.",400,140,'parent');
						exit;
					}
					$cancelMessage = "결제 취소 실패";
				}
				
				$cancelFunction = "{$pgCompany}_cancel";
				$cancelResult	= $this->refundmodel->$cancelFunction($data_order,$data_refund);
				
				if(!$cancelResult['success']){
					openDialogAlert("{$pgCompany} ".$cancelMessage."<br /><font color=red>{$cancelResult['result_code']} : {$cancelResult['result_msg']}</font>",400,160,'parent','');
					exit;
				}
			}
			
			$tot_reserve	= 0;
			$tot_point		= 0;
			// 추가옵션 관련 아이템 재배열
			$items_array	= array();
			if($data_refund_item)foreach($data_refund_item as $item) {
				
				if( $item['goods_kind'] == 'coupon' ) {//티켓상품 마일리지/포인트, 재고, 할인쿠폰 반환없음
					$refund_goods_coupon_ea++;
				}
				
				if($item['title1'])		$item['options_str']  = $item['title1'] .":".$item['option1'];
				if($item['title2'])		$item['options_str'] .= " / ".$item['title2'] .":".$item['option2'];
				if($item['title3'])		$item['options_str'] .= " / ".$item['title3'] .":".$item['option3'];
				if($item['title4'])		$item['options_str'] .= " / ".$item['title4'] .":".$item['option4'];
				
				if	($item['opt_type'] == 'sub'){
					$item['price']								= $item['price'] * $item['ea'];
					$item['sub_options']							= $item['options_str'];
					if	($first_option_seq)
						$items_array[$first_option_seq]['sub'][]		= $item;
						else
							$items_array[$item['option_seq']]['sub'][]		= $item;
				}else{
					$items_array[$item['option_seq']]['price']			+= $item['price'] * $item['ea'];
					$items_array[$item['option_seq']]['ea']			+= $item['ea'];
					$items_array[$item['option_seq']]['option_ea']	+= $item['option_ea'];
					$items_array[$item['option_seq']]['goods_name']	= $item['goods_name'];
					$items_array[$item['option_seq']]['options']		= $item['options_str'];
					$items_array[$item['option_seq']]['inputs']		= $this->ordermodel->get_input_for_option($item['item_seq'], $item['option_seq']);
					$items_array[$item['option_seq']]['image']		= $item['image'];
				}
				if	(!$first_option_seq)	$first_option_seq	= $item['option_seq'];
				
				# 지급했던 마일리지, 포인트 금액 가져오기 201-04-06 pjm
				$tot_reserve	+= $item['give_reserve'];
				$tot_point		+= $item['give_point'];
				
				/* 상품 할인쿠폰 복원 */
				if($item['refund_goods_coupon'] && $aPostParams['refund_goods_coupon'][$item['refund_item_seq']] ){
					$refund_goods_cp = $this->couponmodel->restore_used_coupon($item['refund_goods_coupon']);
				}
				/* 상품 배송비할인쿠폰 복원 */
				if($item['refund_delivery_coupon'] && $aPostParams['refund_delivery_coupon'][$item['refund_item_seq']] ){
					$refund_deliv_cp = $this->couponmodel->restore_used_coupon($item['refund_delivery_coupon']);
				}
				/* 상품 상품 프로모션 복원 */
				if($item['refund_goods_promotion'] && $aPostParams['refund_goods_promotion'][$item['refund_item_seq']] ){
					$refund_goods_pro = $this->promotionmodel->restore_used_promotion($item['refund_goods_promotion']);
				}
				/* 상품 배송비 프로모션 복원 */
				if($item['refund_delivery_promotion'] && $aPostParams['refund_delivery_promotion'][$item['refund_item_seq']] ){
					$refund_deliv_pro = $this->promotionmodel->restore_used_promotion($item['refund_delivery_promotion']);
				}
			}
			
			if ($data_refund['refund_emoney']) {
				$return_emoney_pay = true;
			} else {
				$return_emoney_pay = false;
			}

			// 실제 환불되는 예치금은 총액에서 조정금액을 제외한 금액 by hed
			if ($data_refund['refund_cash']) {
				$tmp_refund_cash = $data_refund['refund_cash'] - $cash_refund_shipping_price;
			} else {
				$tmp_refund_cash = 0;
			}
			// 환불방법:예치금 || 예치금>0 인 경우 환불가능
			if ($tmp_refund_cash > 0 || $aPostParams['refund_method'] == 'cash') {
				$return_cash_pay = true;
			} else {
				$return_cash_pay = false;
			}

			/*
			-----------------------------------------------------------------------------------------
			최종 환불금액, 예치금 환불액, 마일리지 환불액에 대한 금액 검증 필수!!!
			-----------------------------------------------------------------------------------------
			**** 아래 소스 이하부터는 모든 금액에 대한 변동 처리 하지 말 것. ***
			*/

			/* 마일리지 지급 */
			if($return_emoney_pay)
			{
				$params = array(
					'gb'			=> 'plus',
					'type'			=> 'refund',
					'limit_date'	=> $aPostParams['refund_emoney_limit_date'],
					'emoney'		=> get_cutting_price($data_refund['refund_emoney']),
					'ordno'			=> $data_order['order_seq'],
					'memo'			=> "[환불] 주문환불({$data_refund['refund_code']})에 의한 마일리지로 환불",
					'memo_lang'		=> $this->membermodel->make_json_for_getAlert("mp264",$data_refund['refund_code']), // [환불] 주문환불(%s)에 의한 마일리지으로 환불
				);
				$this->membermodel->emoney_insert($params, $data_order['member_seq']);
			}
			
			/* 예치금 지급 */
			if($return_cash_pay){
				$params = array(
					'gb'		=> 'plus',
					'type'		=> 'refund',
					'cash'		=> get_cutting_price($tmp_refund_cash),
					'ordno'		=> $data_order['order_seq'],
					'memo'		=> "[환불] 주문환불({$data_refund['refund_code']})에 의한 예치금으로 환불",
					'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp265",$data_refund['refund_code']), // [환불] 주문환불(%s)에 의한 예치금로 환불
				);
				$this->membermodel->cash_insert($params, $data_order['member_seq']);
			}
			
			## 티켓상품 아닐 경우에만
			if( !$refund_goods_coupon_ea ) {
				// 회수할 마일리지, 포인트가 있을때
				{
					/* 마일리지 회수 */
					if($tot_reserve && $data_refund['refund_type']=='return'){
						$params = array(
							'gb'		=> 'minus',
							'type'		=> 'refund',
							'emoney'	=> get_cutting_price($tot_reserve),
							'ordno'		=> $data_order['order_seq'],
							'memo'		=> "[차감] 주문환불({$data_order['order_seq']})에 의하여 배송완료시 지급된 마일리지 차감",
							'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp258",$data_order['order_seq']),  // [차감] 주문환불(%s)에 의하여 배송완료시 지급된 마일리지 차감
						);
						$this->membermodel->emoney_insert($params, $data_order['member_seq']);
					}
					
					/* 포인트 회수 */
					if($tot_point && $data_refund['refund_type']=='return'){
						$params = array(
							'gb'		=> 'minus',
							'type'		=> 'refund',
							'point'		=> get_cutting_price($tot_point),
							'ordno'		=> $data_order['order_seq'],
							'memo'		=> "[차감] 주문환불({$data_order['order_seq']})에 의하여 배송완료시 지급된 포인트 차감",
							'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp259",$data_order['order_seq']),  // [차감] 주문환불(%s)에 의하여 배송완료시 지급된 포인트 차감
						);
						$this->membermodel->point_insert($params, $data_order['member_seq']);
					}
				}
			}

			/* 주문서 쿠폰 복원 */
			if($aPostParams['refund_ordersheet']){
				$refund_ordersheet_cp = $this->couponmodel->restore_used_coupon($aPostParams['refund_ordersheet']);
			}

			$order_itemArr = array();
			$order_itemArr = array_merge($order_itemArr,$data_order);
			$order_itemArr['order_seq']				= $data_order['order_seq'];
			$order_itemArr['mpayment']				= $data_order['mpayment'];
			$order_itemArr['deposit_date']			= $data_order['deposit_date'];
			$order_itemArr['pg_transaction_number'] = $data_order['pg_transaction_number'];
			
			/* 환불처리완료 안내메일 발송 */
			$params = array_merge($saveData,$data_refund);
			$params['refund_reason']		= htmlspecialchars($data_refund['refund_reason']);
			$params['refund_date']			= $saveData['refund_date'];
			$params['mstatus'] 				= $this->refundmodel->arr_refund_status[$aPostParams['status']];
			$params['refund_price']			= get_currency_price($data_refund['refund_price']);
			$params['refund_emoney']		= get_currency_price($data_refund['refund_emoney']);
			$params['mrefund_method']		= $this->arr_payment[$data_refund['refund_method']];
			$params['order']				= $order_itemArr;
			if($data_refund['refund_method']=='bank'){
				$params['mrefund_method']		.= " 환불";
			}elseif($data_refund['cancel_type']=='full'){
				$params['mrefund_method'] 		.= " 결제취소";
			}elseif($data_refund['cancel_type']=='partial'){
				$params['mrefund_method'] 		.= " 부분취소";
			}
			$params['items'] 			= $items_array;
			
			if( $data_order['order_email'] ) {
				
				// 오픈마켓 주문 메일/문자 발송 금지
				$isMarketOrder		= $this->connectormodel->checkIsMarketOrder($data_order['order_seq']);
				
				if ($isMarketOrder == false) {
					$couponsms		= ( $refund_goods_coupon_ea ) ? "coupon_":"";
					$smsemailtype	= ($data_refund['refund_type']=='return') ? 'refund' : 'cancel';
					sendMail($data_order['order_email'], $couponsms.$smsemailtype, $data_member['userid'], $params);
				}
			}
			
			// 주문이 환불완료 일경우 주문한 회원의 구매횟수 및 구매금액 업데이트
			if($data_order['member_seq']){
				// 이미 위에서 refund_price 계산함 , membermodel 에는 refund_price 계산할 필요가 없으므로 주석처리 함 2020-11-11
				//$refund_price = $data_refund['refund_price'] + $data_refund['refund_emoney'];
				$this->membermodel->member_order($data_order['member_seq']);
				//주문건/주문금액 필드추가 및 실시간업데이트 @2013-06-19
				$this->membermodel->member_order_batch($data_order['member_seq']);
			}
			
		}
		
		$this->db->where('refund_code', $aPostParams['refund_code']);
		$this->db->update("fm_order_refund",$saveData);
		/* 환불신청 또는 환불처리중에서 환불완료로 변경될때 */
		if($data_refund['status']!='complete' && $aPostParams['status']=='complete')
		{
			$this->load->model('accountmodel');
			$this->accountmodel->set_refund($refund_code,$saveData['refund_date']);
			
			// 세금계산서 목록 추출
			$sc['whereis']	= ' and typereceipt = 1 and tstep = 1 and order_seq="'.$data_refund['order_seq'].'" ';
			$sc['select']	= ' * ';
			$taxitems 		= $this->salesmodel->get_data($sc);
			if	($taxitems['seq']){ // 세금계산서 금액 재 업데이트
				$remain = $refund_remain - $refund_price;
				if($remain <= 0){ // 전체 환불시 취소로 상태 업데이트
					$params = array('tstep'=>'3', 'price'=>'0', 'supply'=>'0', 'surtax'=>'0');
					$params['seq'] = $taxitems['seq'];
					$this->salesmodel->sales_modify($params);
				}else{
					$this->ordermodel->update_tax_sales($data_refund['order_seq']);
				}
			}
			
			/* 로그저장 */
			$logTitle = "환불완료(".$refund_code.")";
			$logDetail = "관리자가 환불완료처리를 하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($data_order['order_seq'],'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams);
			
			//회원일경우 id 불러오기
			if(trim($data_order['member_seq'])){
				$userid		= $this->membermodel->get_member_userid(trim($data_order['member_seq']));
			}
			
			$params = array();
			$params['shopName']		= $this->config_basic['shopName'];
			$params['ordno']		= $data_order['order_seq'];
			$params['user_name']	= $data_order['order_user_name'];
			$params['member_seq']	= $data_order['member_seq'];
			if( $data_order['order_cellphone'] ) {
				// 오픈마켓 주문 메일/문자 발송 금지
				$isMarketOrder		= $this->connectormodel->checkIsMarketOrder($data_order['order_seq']);
				
				if ($isMarketOrder == false) {
					if($refund_goods_coupon_ea){
						$this->load->model('returnmodel');
						$data_return = $this->returnmodel->get_return_refund_code($refund_code);
						$data_return_item 	= $this->returnmodel->get_return_item($data_return['return_code']);
						if($data_refund['refund_type']=='return') {
							coupon_send_sms_refund($data_return_item[0]['export_code'],$data_order);
						}else{
							coupon_send_sms_cancel($data_return_item[0]['export_code'],$data_order);
						}
					}else{
						$smsemailtype = ($data_refund['refund_type']=='return') ? 'refund' : 'cancel';
						//SMS 데이터 생성
						$commonSmsData[$smsemailtype]['phone'][] = $data_order['order_cellphone'];
						$commonSmsData[$smsemailtype]['params'][] = $params;
						$commonSmsData[$smsemailtype]['order_no'][] = $data_order['order_seq'];
						if(count($commonSmsData) > 0){
							commonSendSMS($commonSmsData);
						}
						//sendSMS($data_order['order_cellphone'], $smsemailtype, '', $params);
					}
				}
			}
			
			//이벤트 판매건/주문건/주문금액 @2013-11-15
			if($data_refund_item){
				foreach($data_refund_item as $item) {
					if( $item['event_seq'] ) {
						$this->eventmodel->event_order($item['event_seq']);
						$this->eventmodel->event_order_batch($item['event_seq']);
					}
				}
			}
			
			/**
			 * 4-2 환불관련 정산개선 시작
			 * step1->step2 순차로 진행되어야 합니다.
			 * @
			 **/
			$this->load->helper('accountall');
			if(!$this->accountallmodel)	$this->load->model('accountallmodel');
			if(!$this->providermodel)	$this->load->model('providermodel');
			if(!$this->refundmodel)		$this->load->model('refundmodel');
			if(!$this->returnmodel)		$this->load->model('returnmodel');
			
			//step1 주문금액별 정의/비율/단가계산 후 정렬 => step2 적립금/이머니 update
			/* 저장된 정보 로드 $data_order, $data_refund, $data_refund_item */
			/*
			 if( $data_refund['refund_emoney'] || $data_refund['refund_cash'] ) {
			 $this->accountallmodel->update_ratio_emoney_cash_refund($data_order['order_seq'], $refund_code, $data_order, $data_refund, $data_refund_item);
			 }
			 */
			//step2 통합정산 생성(미정산매출 환불건수 업데이트)

			// 3차 환불 개선으로 티켓상품 처리 추가 :: 2018-11- lkh
			if($refund_goods_coupon_ea){
				$this->accountallmodel->update_calculate_sales_coupon_remain($data_order['order_seq']);
				$this->accountallmodel->update_calculate_sales_coupon_ac_ea($data_order['order_seq'],$data_return['return_code'], 'return', $data_return_item, $data_order, $data_return);
			}else{
				//정산대상 수량업데이트
				$this->accountallmodel->update_calculate_sales_ac_ea($data_order['order_seq'],$refund_code, 'refund', $data_refund_item, $data_order, $data_return);
			}
			//정산확정 처리 insert_calculate_sales_order_refund에서 선처리하도록 수정했으므로 해당 프로세스 제거
			// $this->accountallmodel->update_calculate_refund_sales_buyconfirm($data_order['order_seq'], $refund_code, $data_order);
			/* 저장된 정보 로드 $data_order, $data_refund, $data_refund_item */
			$this->accountallmodel->insert_calculate_sales_order_refund($data_order['order_seq'],$refund_code, $data_refund['cancel_type'], $data_order, $data_refund, $data_refund_item);
			/* 저장된 정보 로드 $data_order, $data_refund, $data_refund_item */
			// 3차 환불 개선으로 함수 처리 추가 :: 2018-11- lkh
			$this->accountallmodel->insert_calculate_sales_order_deductible($data_order['order_seq'],$refund_code, $data_refund['cancel_type'], $data_order, $data_refund, $data_refund_item);
			//debug($this->db->queries);
			//debug_var($this->db->query_times);
			if($data_return && $data_return['refund_ship_duty'] == "buyer" && in_array($data_return['refund_ship_type'],array("M","A","D")) && $data_return['return_shipping_gubun'] == 'company' && $data_return['return_shipping_price']) {
				//step2 통합정산 생성(미정산매출 환불건수 업데이트)
				$this->accountallmodel->update_calculate_sales_order_returnshipping($data_return['order_seq'],$data_return['return_code'],$saveData['refund_date']);
				//debug_var($this->db->queries);
				//debug_var($this->db->query_times);
			}
			/**
			* 4-2 환불관련 정산개선 끝
			* step1->step2 순차로 진행되어야 합니다.
			* @
			**/

			// 가용재고의 계산
			$aReservationGoods = array();
			if($data_refund_item){
				foreach($data_refund_item as $item) {
					if(!in_array($item['goods_seq'], $aReservationGoods)) {
						$aReservationGoods[] = $item['goods_seq'];
					}
				}
				foreach($aReservationGoods as $aReservationGoodsSeq){
					$this->goodsmodel->modify_reservation_real($aReservationGoodsSeq);
				}
			}

			// [판매지수 EP] 주문완료 후 통계테이블에 ep 정보 저장 :: 2018-09-14 pjw
			if(!$this->statsmodel) $this->load->model('statsmodel');
			$this->statsmodel->set_refund_sale_ep($aPostParams['refund_code']);
			
			$callback = "parent.document.location.reload();";
			openDialogAlert("환불처리가 완료되었습니다.",400,140,'parent',$callback);
		}else{
			$callback = "parent.document.location.reload();";
			openDialogAlert("환불정보가 저장되었습니다.",400,140,'parent',$callback);
		}
	}
	
	protected function _naverpay_cancel($aPostParams, $refund_code){
		if($aPostParams['status'] == "request"){
			openDialogAlert("이 환불건은 네이버페이 환불건으로 환불신청으로 되돌리기 불가합니다.",500,160,'parent','');
			return false;
		}
		if($aPostParams['status'] == "ing"){
			openDialogAlert("이 환불건은 네이버페이 환불건으로 환불처리중 처리가 불가합니다.",500,160,'parent','');
			return false;
		}
		if($data_refund['status'] == "ing" && $aPostParams['status'] == "complete"){
			openDialogAlert("이 환불건은 네이버페이 환불건으로 환불완료 처리가 불가합니다.",500,160,'parent','');
			return false;
		}
		if($aPostParams['status']=='complete'){
			if($aPostParams['refund_type'] == "return"){
				openDialogAlert("이 환불건은 네이버페이 반품건이므로 직접 처리 불가합니다.",500,160,'parent','');
				return false;
				
			}else{				
				//npay 취소요청 승인 API
				$this->load->model("naverpaymodel");
				foreach($aPostParams['refund_npay_product_order_id'] as $npay_product_order_id){
					$npay_data = array("npay_product_order_id"	=> $npay_product_order_id,
						"order_seq"				=> $aPostParams['order_seq'],
						'refund_code'			=> $refund_code
					);
					$npay_res = $this->naverpaymodel->approve_cancel($npay_data);
				}
				if($npay_res['result'] != "SUCCESS"){
					openDialogAlert("네이버페이 결제 취소요청 승인 실패<br /><font color=red>".$npay_res['message']."</font>",500,160,'parent','');
					return false;
				}else{
					# 출고준비건이 있으면 삭제
					# 네이버페이에서 환불완료 수집시에는 이미 출고건 삭제되고 있었음.
					$this->load->model('exportmodel');
					$this->exportmodel->delete_export_ready($aPostParams['order_seq'],$aPostParams['refund_npay_product_order_id']);	

					$callback = "parent.document.location.reload();";
					openDialogAlert("Npay 결제 취소요청 승인 완료하였습니다.",500,160,'parent',$callback);
					$aPostParams['status'] = "request";
					return $aPostParams['status'];
				}
			}
			return false;
		}
		
		return false;
	}

	public function refund_old_save(){

		$this->load->model('ordermodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('emoneymodel');
		$this->load->model('membermodel');
		$this->load->model('eventmodel');
		$this->load->model('connectormodel');
		$this->load->model('salesmodel');
		$this->load->model('goodsmodel');
		$this->load->helper('text');
		$this->load->helper('order');

		$cfg_order		= config_load('order');
		$cfg_reserve	= config_load('reserve');		//마일리지/예치금 환경 로드
		$npay_use		= npay_useck();					//npay 사용여부

		/* 마일리지/예치금 환경 로드 */
		$cfg_reserve	= config_load('reserve');

		/* 예치금 미사용 시 */
		if($cfg_reserve['cash_use']=='N' && ($_POST['refund_cash'] > 0 || $_POST['refund_method'] == "cash")){
			openDialogAlert("예치금 환불이 불가능 합니다.<br />설정=>마일리지/포인트/예치금 설정을 확인해 주세요.",400,140,'parent');
			exit;
		}

		//comma 제거 pjm
		foreach($_POST as $k=>$v){
			if(is_array($v)) foreach($v as $kk=>$vv) $_POST[$k][$kk] = str_replace(",","",$vv);
			else $_POST[$k] = str_replace(",","",$v);
		}

		## (맞교환)재주문 환불
		## 1) 원주문의 총 주문 금액을 가져온다.
		## 2) 원주문과 맞교환 재주문건의 기 환불액을 가져온다.
		$all_order_seq = array($_POST['order_seq']);

		if($_POST['top_orign_order_seq']){
			$top_orign_order_seq = $all_order_seq[] = $_POST['top_orign_order_seq'];
		}else{
			$top_orign_order_seq = $_POST['order_seq'];
		}

		## 하위 주문번호 조회(원주문의 맞교환(재주문)건)
		$query = "select order_seq from fm_order where top_orign_order_seq='".$_POST['order_seq']."'";
		$query = $this->db->query($query);
		foreach($query->result_array() as $sub_order) $all_order_seq[] = $sub_order['order_seq'];

		$all_order_seq = array_unique($all_order_seq);

		$refund_code		= $_POST['refund_code'];

		// 총 결제금액(현금성+마일리지+예치금)
		$data_order			= $this->ordermodel->get_order($top_orign_order_seq);
		$data_refund		= $this->refundmodel->get_refund($refund_code);
		$pay_price			= $data_order['settleprice'] + $data_order['emoney'] + $data_order['cash'];
		$data_return		= $this->returnmodel->get_return_refund_code($refund_code);

		// 반품이 완료되지 않은 경우 환불 불가
		if	($data_refund['refund_type'] == 'return' && $_POST['status'] == 'complete' && $data_return['status'] != 'complete'){
			openDialogAlert('반품이 완료되지 않았습니다.<br/>반품을 먼저 완료해 주시기 바랍니다.', 500, 170, 'parent', '');
			exit;
		}

		# npay 반품환불/주문취소 환불 승인
		# 처리가능작업 :
		#	- 환불신청 -> 환불완료(O)
		#	- 환불신청 -> 환불처리중(X)
		#	- 환불처리중 -> 환불완료(X)
		#	- 환불처리중 -> 환불신청(X)

		if($npay_use && $data_order['pg'] == "npay"){

			if($_POST['status'] == "request"){
				openDialogAlert("이 환불건은 네이버페이 환불건으로 환불신청으로 되돌리기 불가합니다.",500,160,'parent','');
				exit;
			}
			if($_POST['status'] == "ing"){
				openDialogAlert("이 환불건은 네이버페이 환불건으로 환불처리중 처리가 불가합니다.",500,160,'parent','');
				exit;
			}
			if($data_refund['status'] == "ing" && $_POST['status'] == "complete"){
				openDialogAlert("이 환불건은 네이버페이 환불건으로 환불완료 처리가 불가합니다.",500,160,'parent','');
				exit;
			}
			if($_POST['status']=='complete'){

				if($_POST['refund_type'] == "return"){
					openDialogAlert("이 환불건은 네이버페이 반품건이므로 직접 처리 불가합니다.",500,160,'parent','');
					exit;

				}else{

					//npay 취소요청 승인 API
					$this->load->model("naverpaymodel");
					foreach($_POST['refund_npay_product_order_id'] as $npay_product_order_id){
						$npay_data = array("npay_product_order_id"	=> $npay_product_order_id,
											"order_seq"				=> $_POST['order_seq'],
											'refund_code'			=> $refund_code
											);
						$npay_res = $this->naverpaymodel->approve_cancel($npay_data);
					}
					if($npay_res['result'] != "SUCCESS"){
						openDialogAlert("네이버페이 결제 취소요청 승인 실패<br /><font color=red>".$npay_res['message']."</font>",500,160,'parent','');
						exit;
					}else{
						$callback = "parent.document.location.reload();";
						openDialogAlert("Npay 결제 취소요청 승인 완료하였습니다.",500,160,'parent',$callback);
						exit;
					}

					$_POST['status'] = "request";
				}
				exit;

			}
			$refund_price		= '0';
			$refund_delivery	= '0';

		}else{
			$npay_use = false;

			//최종환불액(상품+배송)
			$refund_price		= array_sum($_POST['refund_goods_price'])+array_sum($_POST['refund_delivery_price']);
			//환불배송비 합(관리자입력)
			$refund_delivery	= array_sum($_POST['refund_delivery_price']);

			# 최종환불액(결제통화기준)
			if($refund_price > 0){
				$refund_pg_price_sum = $refund_price;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency'] && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_pg_price_sum = get_currency_exchange($refund_price,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_pg_price_sum = 0;
			}
			$data_refund['refund_pg_price'] = $refund_pg_price_sum;

			# 환불배송비 합(결제통화기준)
			if($refund_delivery > 0){
				$refund_pg_delivery_sum = $refund_delivery;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency'] && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_pg_delivery_sum = get_currency_exchange($refund_delivery,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_pg_delivery_sum = 0;
			}

			//동일주문의 기 환불금액 현금성
			$data_refund_item			= $this->refundmodel->get_refund_item($refund_code,$all_order_seq);
			$refund_complete	= 0;
			$refund_complete_delivery	= 0;
			foreach($data_refund_item as $k => $data){
				$refund_comp = $this->refundmodel->get_refund_complete_price($all_order_seq,$data['option_seq'],$data['opt_type']);
				$refund_complete_price += $refund_comp['complete_price'];
				$refund_complete_delivery += $refund_comp['complete_delivery'];
			}

			//동일주문의 기 환불금액 마일리지, 예치금
			$refund_comp = $this->refundmodel->get_refund_complete_emoney($all_order_seq);
			$refund_complete_price	+= $refund_comp['complete_emoney'];
			$refund_complete_price	+= $refund_comp['complete_cash'];

			//환불액이 환불가능금액 보다 클경우 경고창.
			$refund_remain = $pay_price - $refund_complete_price;
			if($refund_remain < $refund_price){
				openDialogAlert("환불이 불가 합니다.<br />환불금액(".get_currency_price($refund_price,3).")은 환불가능 금액(".get_currency_price($refund_remain,3).")을 초과하는 금액입니다.",400,190,'parent');
				exit;
			}

			//마일리지 환불 유효기간 체크
			if($data_order['member_seq']){
				if($_POST['refund_emoney_limit_type'] == "n"){
					$_POST['refund_emoney_limit_date'] = "";
				}else{
					if($_POST['refund_emoney_limit_date'] < date("Y-m-d",mktime())){
						openDialogAlert("마일리지 환불 유효기간은 오늘 이후(".date("Y-m-d",mktime()).")로 설정하셔야 합니다.",500,140,'parent');
						exit;
					}
				}
			}

			//배송비 환불의 경우 예외처리
			$base_refund_info	= $this->refundmodel->get_refund($_POST['refund_code']);
			if($base_refund_info['refund_type'] == 'shipping_price'){

				# 동일 배송그룹의 최초 배송비(기본배송비 or 개별배송비 가져오기 + 추가배송비)
				$delivery_existing_price	= $this->ordermodel->get_delivery_existing_price($_POST['order_seq'],$data_refund_item[0]['shipping_seq']);
	
				foreach((array)$_POST['refund_delivery_price'] as $item_seq => $item_val){
					$_POST['refund_item_seq'][$item_seq]	= $item_seq;

					$now_refund_delivery	= $refund_complete_delivery + $item_val;
				}

				if($now_refund_delivery > $delivery_existing_price){
					openDialogAlert('배송비환불이 불가 합니다.<br />환불금액('.get_currency_price($now_refund_delivery,3).')은 환불가능 금액('.get_currency_price($delivery_existing_price,3).')을 초과하는 금액입니다.',400,190,'parent');
					exit;
				}
			}
		}

		$_POST['adjust_use_coupon']		= 0;
		$_POST['adjust_use_promotion']	= 0;
		$_POST['adjust_use_emoney']		= 0;
		$_POST['adjust_use_cash']		= 0;
		$_POST['adjust_use_enuri']		= 0;
		$_POST['adjust_refund_price']	= 0;

		/* 환불 정보 저장 */
		$saveData = array(
			'adjust_use_coupon'			=> get_cutting_price($_POST['adjust_use_coupon']),
			'adjust_use_promotion'		=> get_cutting_price($_POST['adjust_use_promotion']),
			'adjust_use_emoney'			=> get_cutting_price($_POST['adjust_use_emoney']),
			'adjust_use_cash'			=> get_cutting_price($_POST['adjust_use_cash']),
			'adjust_use_enuri'			=> get_cutting_price($_POST['adjust_use_enuri']),
			'adjust_refund_price'		=> get_cutting_price($_POST['adjust_refund_price']),
			'refund_method'				=> $_POST['refund_method'],
			'refund_price'				=> get_cutting_price($refund_price),
			'refund_emoney'				=> get_cutting_price($_POST['refund_emoney']),
			'refund_emoney_limit_date'	=> $_POST['refund_emoney_limit_date'],
			'refund_cash'				=> get_cutting_price($_POST['refund_cash']),
			'refund_delivery'			=> get_cutting_price($refund_delivery),
			'refund_pg_price'			=> $refund_pg_price_sum,
			'refund_pg_delivery'		=> $refund_pg_delivery_sum,
			'refund_ordersheet'			=> $_POST['refund_ordersheet'],
		);

		$this->db->where('refund_code', $refund_code);
		$this->db->update("fm_order_refund",$saveData);

		$data_refund['refund_price']	= $refund_price;
		$data_refund['refund_emoney']	= $_POST['refund_emoney'];
		$data_refund['refund_cash']		= $_POST['refund_cash'];
		$data_refund['refund_ordersheet']		= $_POST['refund_ordersheet'];

		$refund_provider = array();
		foreach($_POST['refund_item_seq'] as $refund_item_seq){

			$refund_goods_price			= str_replace(",","",$_POST['refund_goods_price'][$refund_item_seq]);
			$refund_goods_promotion		= str_replace(",","",$_POST['refund_goods_promotion'][$refund_item_seq]);
			$refund_delivery_price		= str_replace(",","",$_POST['refund_delivery_price'][$refund_item_seq]);
			$refund_goods_coupon		= str_replace(",","",$_POST['refund_goods_coupon'][$refund_item_seq]);
			$refund_delivery_coupon		= str_replace(",","",$_POST['refund_delivery_coupon'][$refund_item_seq]);
			$refund_delivery_promotion	= str_replace(",","",$_POST['refund_delivery_promotion'][$refund_item_seq]);

			# 상품 환불금액(결제통화기준)
			if($refund_goods_price > 0){
				$refund_goods_pg_price = $refund_goods_price;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency'] && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_goods_pg_price = get_currency_exchange($refund_goods_price,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_goods_pg_price = 0;
			}
			# 배송비 환불금액(결제통화기준)
			if($refund_delivery_price > 0){
				$refund_delivery_pg_price = $refund_delivery_price;
				//기본통화와 주문통화가 동일하면 그대로  @2017-02-24
				if($data_order['pg_currency'] && $this->config_system['basic_currency'] != $data_order['pg_currency'] ){
					$refund_delivery_pg_price = get_currency_exchange($refund_delivery_price,$data_order['pg_currency'],'','front');
				}
			}else{
				$refund_delivery_pg_price = 0;
			}

			$saveData = array(
				'refund_goods_price'		=> get_cutting_price($refund_goods_price),
				'refund_goods_pg_price'		=> $refund_goods_pg_price,
				'refund_goods_coupon'		=> get_cutting_price($refund_goods_coupon),
				'refund_goods_promotion'	=> get_cutting_price($refund_goods_promotion),
				'refund_delivery_price'		=> get_cutting_price($refund_delivery_price),
				'refund_delivery_pg_price'	=> $refund_delivery_pg_price,
				'refund_delivery_coupon'	=> get_cutting_price($refund_delivery_coupon),
				'refund_delivery_promotion'	=> get_cutting_price($refund_delivery_promotion),
			);

			$this->db->where('refund_item_seq', $refund_item_seq);
			$this->db->update("fm_order_refund_item",$saveData);

			/* 입점사별 환불 정보 pjm */
			$provider_seq = $_POST['refund_provider_seq'][$refund_item_seq];
			if($provider_seq){
				$refund_provider[$provider_seq]['provider_seq']			= $provider_seq;
				$refund_provider[$provider_seq]['refund_expect_price']	= 0;
				$refund_provider[$provider_seq]['adjust_refund_price']	+= $refund_goods_price+$refund_delivery_price;
				$refund_provider[$provider_seq]['refund_price']			+= $refund_goods_price+$refund_delivery_price;
			}
		}

		/* 입점사별 환불 정보 pjm */
		foreach($refund_provider as $provider_data){
			$this->refundmodel->set_provider_refund($refund_code, $provider_data);
		}


		/* 저장된 정보 로드 */
		$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
		$data_order_item	= $this->ordermodel->get_item($data_refund['order_seq']);
		$data_member		= $this->membermodel->get_member_data($data_order['member_seq']);

		$order_total_ea = $this->ordermodel->get_order_total_ea($data_refund['order_seq']);
		if($data_refund_item && empty($refund_ea)) foreach($data_refund_item as $item) 	$refund_ea += $item['ea'];

		if(!$npay_use){
			//this->refundmodel->get_refund_item :: 반품건 외 전체 주문아이템 불러옴.
			//복합과세 결제여부 : 전체주문아이템중 비과세 상품 찾기 @2015-06-02 pjm
			$tmp_tax	= array();
			$free_tax	= "n";
			if($data_order_item){
				foreach($data_order_item as $item){
					$tmp_tax[]		= $item['tax'];
					if($item['tax'] == "exempt") $free_tax = "y";
				}
			}

			//kcp 전체 비과세일때 복합과세로 전송되도록 수정
			if( !in_array("tax",$tmp_tax) && $free_tax == "n" ) $free_tax = "y";

			$data_refund['free_tax'] = $free_tax;

			//환북액 과세/비과세 금액 나누기  @2015-06-02 pjm
			$data_refund['tax_price']	= "0";
			$data_refund['free_price']	= "0";
			if($data_refund_item){
				foreach($data_refund_item as $item){
					$refund_seq		= $item['refund_item_seq'];
					$refund_deliv	+= $_POST['refund_delivery_price'][$refund_seq];
					//과세
					if($item['tax'] == "tax"){
						$data_refund['tax_price'] += $_POST['refund_goods_price'][$refund_seq];
					}elseif($item['tax'] == "exempt"){
						$data_refund['free_price'] += $_POST['refund_goods_price'][$refund_seq];
					}
				}
			}

			//과세상품이 한건이라도 있으면 배송비는 과세, 전체 비과세 주문일때만 배송비 비과세.  @2015-06-02 pjm
			if(in_array("tax",$tmp_tax)){
				$data_refund['tax_price'] += $refund_deliv;
			}else{
				$data_refund['free_price'] += $refund_deliv;
			}

			if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		}
		// 과세 대상 환불 금액을 기준으로 과세금액와 부과세를 계산
		$data_refund['comm_tax_mny']	= 0;		// 과세금액
		$data_refund['comm_vat_mny']	= 0;		// 부과세
		if($data_refund['tax_price']){
			$order_cfg = ($this->cfg_order) ? $this->cfg_order : config_load('order');
			$vat = $order_cfg['vat'] ? $order_cfg['vat'] : 10;
			$sum_price		= $data_refund['tax_price'];
			$tax_price		= get_cutting_price($sum_price / (1 + ($vat / 100)));
			if($sum_price>$tax_price){
				$data_refund['comm_tax_mny']	= $tax_price;
				$data_refund['comm_vat_mny']	= $sum_price - $tax_price;
			}
		}
		// 올앳 & 카카오페이 의 경우 과세,부과세 금액을 주문 내역을 기준으로 계산
		$pgCompany = $this->config_system['pgCompany'];
		if($pgCompany=="allat" || $data_order['pg']=="kakaopay" || $data_order['pg']=="payco" || $data_order['pg']=="kicc"){
			// 전체 과세금액 추출
			$refund_type = "complete";
			$order_seq = $data_refund['order_seq'];
			# 주문 데이터를 토대로 과세상품액, 비과세액, 과세 배송비금액 구해오기
			$all_order_list		= $this->ordermodel->get_order($order_seq);
			$tax_invoice_type	= ($all_order_list['typereceipt'] == 1) ? true : false;		//세금 계산서 신청여부
			// 환불가능 과세금액 계산
			$order_tax_prices	= $this->ordermodel->get_order_prices_for_tax($order_seq,$all_order_list,$tax_invoice_type,$refund_type);

			$data_tax = $this->salesmodel->tax_calulate(
											$order_tax_prices["tax"],
											$order_tax_prices["exempt"],
											$order_tax_prices["shipping_cost"],
											$order_tax_prices["sale"],
											$order_tax_prices["tax_sale"],'SETTLE');

			$supply			= get_cutting_price($data_tax['supply']);
			$surtax			= get_cutting_price($data_tax['surtax']);
			$taxprice		= get_cutting_price($data_tax['supply']) + get_cutting_price($data_tax['surtax']);
			
			// 남은 환불가능 과세금액과 환불예정 과세금액이 동일할 경우 
			// 전체 과세금액으로부터 과세,부과세를 역산한다.
			if($data_refund['tax_price']==$taxprice && $taxprice > 0){
				// 전체 공급가액 계산
				$tot_tax_prices	= $this->ordermodel->get_order_prices_for_tax($order_seq,$all_order_list,$tax_invoice_type,"all_order");
				$tot_data_tax = $this->salesmodel->tax_calulate(
												$tot_tax_prices["tax"],
												$tot_tax_prices["exempt"],
												$tot_tax_prices["shipping_cost"],
												$tot_tax_prices["sale"],
												$tot_tax_prices["tax_sale"],'SETTLE');
				$tot_supply		= get_cutting_price($tot_data_tax['supply']);
				$tot_surtax		= get_cutting_price($tot_data_tax['surtax']);

				// 기존환불 과세금액 계산
				$re_tax_refund_data_list = $this->refundmodel->get_refund_for_order($data_refund['order_seq']);
				$re_tax_sum_tax_price = 0;
				$re_tax_sum_comm_tax_mny = 0;
				$re_tax_sum_comm_vat_mny = 0;
				$re_tax_sum_free_price = 0;
				foreach($re_tax_refund_data_list as $re_tax_refund_data){
					if($re_tax_refund_data['status']=='complete'){
						$re_tax_sum_tax_price += $re_tax_refund_data['tax_price'];
						$re_tax_sum_comm_tax_mny += $re_tax_refund_data['comm_tax_mny'];
						$re_tax_sum_comm_vat_mny += $re_tax_refund_data['comm_vat_mny'];
						$re_tax_sum_free_price += $re_tax_refund_data['freeprice'];
					}
				}
				// 검산 : 기 환불금액
				if($re_tax_sum_tax_price+$re_tax_sum_free_price != ($_POST['complete_price'])){
					openDialogAlert('기환불금액 오류<br/> 기환불금액('.get_currency_price($_POST['complete_price'],3).')이 과세금액('.get_currency_price($re_tax_sum_tax_price,3).')와 면세금액('.get_currency_price($re_tax_sum_free_price,3).')의 합과 다릅니다.',400,190,'parent');
					exit;
				}
				// 검산 : 기환불 과세금액
				if($re_tax_sum_tax_price != ($re_tax_sum_comm_tax_mny+$re_tax_sum_comm_vat_mny)){
					openDialogAlert('기환불금액 오류<br/> 과세금액('.get_currency_price($re_tax_sum_tax_price,3).')이 공급가액('.get_currency_price($re_tax_sum_comm_tax_mny,3).')와 부가세('.get_currency_price($re_tax_sum_comm_vat_mny,3).')의 합과 다릅니다.',400,190,'parent');
					exit;
				}
				
				$re_tax_comm_tax_mny = $tot_supply - $re_tax_sum_comm_tax_mny;
				$re_tax_comm_vat_mny = $taxprice - $re_tax_comm_tax_mny;
				
				// 검산 : 환불요청 금액
				if($_POST['refund_price'] != ($re_tax_comm_tax_mny+$re_tax_comm_vat_mny+$data_refund['free_price'])){
					openDialogAlert('환불요청 금액 오류<br/> 환불요청 금액('.get_currency_price($_POST['refund_price'],3).')이 공급가액('.get_currency_price($re_tax_comm_tax_mny,3).')와 부가세('.get_currency_price($re_tax_comm_vat_mny,3).')와 비과세('.get_currency_price($data_refund['free_price'],3).')의 합과 다릅니다.',400,190,'parent');
					exit;
				}
				
				$data_refund['comm_tax_mny'] = $re_tax_comm_tax_mny;
				$data_refund['comm_vat_mny'] = $re_tax_comm_vat_mny;
			}
		}
		
		// 환불 과세 정보 저장
		$taxSaveData = array(
			'tax_price'		=> get_cutting_price($data_refund['tax_price']),
			'comm_tax_mny'		=> get_cutting_price($data_refund['comm_tax_mny']),
			'comm_vat_mny'		=> get_cutting_price($data_refund['comm_vat_mny']),
			'freeprice'	=> get_cutting_price($data_refund['free_price']),
		);

		$this->db->where('refund_code', $refund_code);
		$this->db->update("fm_order_refund",$taxSaveData);		

		// allat 의 경우 인코딩 전에 데이터를 처리해야하므로 과세 비과세 부과세 추출
		if($_POST['get_allat_multi_amt']){
			echo json_encode($data_refund);
			exit;
		}
		

		$saveData = array();
		$saveData['status'] = $_POST['status'];

		
		/* 환불신청 또는 환불처리중에서 환불완료로 변경될때 */
		if(!$npay_use && $data_refund['status']!='complete' && $_POST['status']=='complete')
		{
			$this->load->model('couponmodel');
			$this->load->model('promotionmodel');

			$saveData['refund_date'] = date('Y-m-d H:i:s');
			$saveData['manager_seq'] = $this->managerInfo['manager_seq'];

			/* 무통장 환불 처리 */
			if($_POST['refund_method']=='bank' || $_POST['refund_method']=='manual' || $_POST['refund_method']=='cash' || $_POST['refund_method']=='emoney')
			{
				// 별다른 처리 없음
			}
			/* PG 결제취소 처리 */
			else
			{
				if(!$data_order['payment_price'] && $data_order['pg_currency'] == $this->config_system['basic_currency']){
					$data_order['payment_price'] = $data_order['settleprice'];

				}

				if($data_order['payment_price'] < $data_refund['refund_pg_price']){
					openDialogAlert("환불금액이 실결제금액보다 클 수 없습니다.",400,140,'parent');
					exit;
				}

				$pgCompany = $this->config_system['pgCompany'];

				// 카카오 페이의 PG사를 추출하기 위한 데이터 :: 2015-02-25 lwh
				switch($data_order['pg']){
					case 'kakaopay':
					case 'payco':
						$pglog_tmp				= $this->ordermodel->get_pg_log($data_order['order_seq']);
						$pg_log_data			= $pglog_tmp[0];
						$data_order['pg_log']	= $pg_log_data;
						$pgCompany				= $data_order['pg'];
						break;
					case 'paypal':
						$pgCompany				= $data_order['pg'];
						break;
					case 'eximbay':
						$pgCompany				= $data_order['pg'];
						break;
				}

				$pgCancelType = $data_refund['cancel_type'];

				/* 카드일땐 금액에 따라 전체취소할지 부분취소할지 결정함 */
				if($data_order['settleprice']==$data_refund['refund_price'] && $order_total_ea==$refund_ea){
					// 전체금액일땐 전체취소
					$data_refund['cancel_type'] = 'full';
				}else{
					// 부분금액일땐 부분취소
					$data_refund['cancel_type'] = 'partial';
				}
				/* PG 부분취소 */
				if($data_refund['cancel_type']=='partial')
				{
					$cancelMessage = "부분매입취소 실패";
				}
				/* PG 전체취소 */
				else
				{
					if($data_order['settleprice']!=$data_refund['refund_price']){
						openDialogAlert("PG 전체취소시에는 결제금액과 환불금액이 동일해야합니다.",400,140,'parent');
						exit;
					}
					$cancelMessage = "결제 취소 실패";
				}

				$cancelFunction = "{$pgCompany}_cancel";
				// 부분반품 시 기 동일 결제 방식 반품 금액을 계산하기위해 환불처리 방식을 전달이 필요 by hed 20180601
				$refund_method_set = false;
				if(empty($data_refund['refund_method'])){
					$data_refund['refund_method'] = $_POST['refund_method'];
					$refund_method_set = true;
				}
				$cancelResult	= $this->refundmodel->$cancelFunction($data_order,$data_refund);
				// 이후 처리에 영향을 주지 않기 위해 다시 unset
				if($refund_method_set){
					unset($data_refund['refund_method']);
				}
				
				if(!$cancelResult['success']){
					openDialogAlert("{$pgCompany} ".$cancelMessage."<br /><font color=red>{$cancelResult['result_code']} : {$cancelResult['result_msg']}</font>",400,160,'parent','');
					exit;
				}


			}

			$tot_reserve	= 0;
			$tot_point		= 0;
			// 추가옵션 관련 아이템 재배열
			$items_array	= array();
			if($data_refund_item)foreach($data_refund_item as $item) {

				if( $item['goods_kind'] == 'coupon' ) {//티켓상품 마일리지/포인트, 재고, 할인쿠폰 반환없음
					$refund_goods_coupon_ea++;
				}

				if($item['title1'])		$item['options_str']  = $item['title1'] .":".$item['option1'];
				if($item['title2'])		$item['options_str'] .= " / ".$item['title2'] .":".$item['option2'];
				if($item['title3'])		$item['options_str'] .= " / ".$item['title3'] .":".$item['option3'];
				if($item['title4'])		$item['options_str'] .= " / ".$item['title4'] .":".$item['option4'];

				if	($item['opt_type'] == 'sub'){
					$item['price']								= $item['price'] * $item['ea'];
					$item['sub_options']							= $item['options_str'];
					if	($first_option_seq)
						$items_array[$first_option_seq]['sub'][]		= $item;
					else
						$items_array[$item['option_seq']]['sub'][]		= $item;
				}else{
					$items_array[$item['option_seq']]['price']			+= $item['price'] * $item['ea'];
					$items_array[$item['option_seq']]['ea']			+= $item['ea'];
					$items_array[$item['option_seq']]['option_ea']	+= $item['option_ea'];
					$items_array[$item['option_seq']]['goods_name']	= $item['goods_name'];
					$items_array[$item['option_seq']]['options']		= $item['options_str'];
					$items_array[$item['option_seq']]['inputs']		= $this->ordermodel->get_input_for_option($item['item_seq'], $item['option_seq']);
					$items_array[$item['option_seq']]['image']		= $item['image'];
				}
				if	(!$first_option_seq)	$first_option_seq	= $item['option_seq'];

				# 지급했던 마일리지, 포인트 금액 가져오기 201-04-06 pjm
				$tot_reserve	+= $item['give_reserve'];
				$tot_point		+= $item['give_point'];

				/* 상품 할인쿠폰 복원 */
					if($item['refund_goods_coupon'] && $_POST['refund_goods_coupon'][$item['refund_item_seq']] ){
					$refund_goods_cp = $this->couponmodel->restore_used_coupon($item['refund_goods_coupon']);
				}
				/* 상품 배송비할인쿠폰 복원 */
					if($item['refund_delivery_coupon'] && $_POST['refund_delivery_coupon'][$item['refund_item_seq']] ){
					$refund_deliv_cp = $this->couponmodel->restore_used_coupon($item['refund_delivery_coupon']);
				}
				/* 상품 상품 프로모션 복원 */
					if($item['refund_goods_promotion'] && $_POST['refund_goods_promotion'][$item['refund_item_seq']] ){
					$refund_goods_pro = $this->promotionmodel->restore_used_promotion($item['refund_goods_promotion']);
				}
				/* 상품 배송비 프로모션 복원 */
					if($item['refund_delivery_promotion'] && $_POST['refund_delivery_promotion'][$item['refund_item_seq']] ){
					$refund_deliv_pro = $this->promotionmodel->restore_used_promotion($item['refund_delivery_promotion']);
				}
			}

				/* 마일리지 지급 */
				if($data_refund['refund_emoney'])
				{
					$params = array(
						'gb'			=> 'plus',
						'type'			=> 'refund',
						'limit_date'	=> $_POST['refund_emoney_limit_date'],
						'emoney'		=> get_cutting_price($data_refund['refund_emoney']),
						'ordno'			=> $data_order['order_seq'],
						'memo'			=> "[환불] 주문환불({$data_refund['refund_code']})에 의한 마일리지으로 환불",
						'memo_lang'		=> $this->membermodel->make_json_for_getAlert("mp264",$data_refund['refund_code']), // [환불] 주문환불(%s)에 의한 마일리지으로 환불
					);
					$this->membermodel->emoney_insert($params, $data_order['member_seq']);
				}

				/* 예치금 지급 */
				if($data_refund['refund_cash'] > 0 || $_POST['refund_method'] == "cash")
				{
					if($_POST['refund_method'] == "cash"){
						$data_refund['refund_cash'] += array_sum($_POST['refund_goods_price']) +$refund_delivery;
					}

					$params = array(
						'gb'		=> 'plus',
						'type'		=> 'refund',
						'cash'		=> get_cutting_price($data_refund['refund_cash']),
						'ordno'		=> $data_order['order_seq'],
						'memo'		=> "[환불] 주문환불({$data_refund['refund_code']})에 의한 예치금로 환불",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp265",$data_refund['refund_code']), // [환불] 주문환불(%s)에 의한 예치금로 환불
					);
					$this->membermodel->cash_insert($params, $data_order['member_seq']);
				}

				/* 주문서 쿠폰 복원 */
				if($_POST['refund_ordersheet']){
					$refund_ordersheet_cp = $this->couponmodel->restore_used_coupon($_POST['refund_ordersheet']);
				}

			## 티켓상품 아닐 경우에만
			if( !$refund_goods_coupon_ea ) {
				// 회수할 마일리지, 포인트가 있을때
				{
					/* 마일리지 회수 */
					if($tot_reserve && $data_refund['refund_type']=='return'){
						$params = array(
							'gb'		=> 'minus',
							'type'		=> 'refund',
							'emoney'	=> get_cutting_price($tot_reserve),
							'ordno'		=> $data_order['order_seq'],
							'memo'		=> "[차감] 주문환불({$data_order['order_seq']})에 의하여 배송완료시 지급된 마일리지 차감",
							'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp258",$data_order['order_seq']),  // [차감] 주문환불(%s)에 의하여 배송완료시 지급된 마일리지 차감
						);
						$this->membermodel->emoney_insert($params, $data_order['member_seq']);
					}

					/* 포인트 회수 */
					if($tot_point && $data_refund['refund_type']=='return'){
						$params = array(
							'gb'		=> 'minus',
							'type'		=> 'refund',
							'point'		=> get_cutting_price($tot_point),
							'ordno'		=> $data_order['order_seq'],
							'memo'		=> "[차감] 주문환불({$data_order['order_seq']})에 의하여 배송완료시 지급된 포인트 차감",
							'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp259",$data_order['order_seq']),  // [차감] 주문환불(%s)에 의하여 배송완료시 지급된 포인트 차감
						);
						$this->membermodel->point_insert($params, $data_order['member_seq']);
					}
				}
			}

			$order_itemArr = array();
			$order_itemArr = array_merge($order_itemArr,$data_order);
			$order_itemArr['order_seq']				= $data_order['order_seq'];
			$order_itemArr['mpayment']				= $data_order['mpayment'];
			$order_itemArr['deposit_date']			= $data_order['deposit_date'];
			$order_itemArr['pg_transaction_number'] = $data_order['pg_transaction_number'];

			/* 환불처리완료 안내메일 발송 */
			$params = array_merge($saveData,$data_refund);
			$params['refund_reason']		= htmlspecialchars($data_refund['refund_reason']);
			$params['refund_date']			= $saveData['refund_date'];
			$params['mstatus'] 				= $this->refundmodel->arr_refund_status[$_POST['status']];
			$params['refund_price']			= get_currency_price($data_refund['refund_price']);
			$params['refund_emoney']		= get_currency_price($data_refund['refund_emoney']);
			$params['mrefund_method']		= $this->arr_payment[$data_refund['refund_method']];
			$params['order']				= $order_itemArr;
			if($data_refund['refund_method']=='bank'){
				$params['mrefund_method']		.= " 환불";
			}elseif($data_refund['cancel_type']=='full'){
				$params['mrefund_method'] 		.= " 결제취소";
			}elseif($data_refund['cancel_type']=='partial'){
				$params['mrefund_method'] 		.= " 부분취소";
			}
			$params['items'] 			= $items_array;

			if( $data_order['order_email'] ) {

				// 오픈마켓 주문 메일/문자 발송 금지
				$isMarketOrder		= $this->connectormodel->checkIsMarketOrder($data_order['order_seq']);
	
				if ($isMarketOrder == false) {
					$couponsms		= ( $refund_goods_coupon_ea ) ? "coupon_":"";
					$smsemailtype	= ($data_refund['refund_type']=='return') ? 'refund' : 'cancel';
					sendMail($data_order['order_email'], $couponsms.$smsemailtype, $data_member['userid'], $params);
				}
			}

			// 주문이 환불완료 일경우 주문한 회원의 구매횟수 및 구매금액 업데이트
			if($data_order['member_seq']){
				$refund_price = $data_refund['refund_price'] + $data_refund['refund_emoney'];
				$this->membermodel->member_order($data_order['member_seq']);
				//주문건/주문금액 필드추가 및 실시간업데이트 @2013-06-19
				$this->membermodel->member_order_batch($data_order['member_seq']);
			}

			// [판매지수 EP] 주문완료 후 통계테이블에 ep 정보 저장 :: 2018-09-14 pjw
			if(!$this->statsmodel) $this->load->model('statsmodel');
			$this->statsmodel->set_refund_sale_ep($_POST['refund_code']);

		}

		$this->db->where('refund_code', $_POST['refund_code']);
		$this->db->update("fm_order_refund",$saveData);
		/* 환불신청 또는 환불처리중에서 환불완료로 변경될때 */
		if($data_refund['status']!='complete' && $_POST['status']=='complete')
		{
			$this->load->model('accountmodel');
			$this->accountmodel->set_refund($refund_code,$saveData['refund_date']);

			// 세금계산서 목록 추출
			$sc['whereis']	= ' and typereceipt = 1 and tstep = 1 and order_seq="'.$data_refund['order_seq'].'" ';
			$sc['select']	= ' * ';
			$taxitems 		= $this->salesmodel->get_data($sc);
			if	($taxitems['seq']){ // 세금계산서 금액 재 업데이트
				$remain = $refund_remain - $refund_price;
				if($remain <= 0){ // 전체 환불시 취소로 상태 업데이트
					$params = array('tstep'=>'3', 'price'=>'0', 'supply'=>'0', 'surtax'=>'0');
					$params['seq'] = $taxitems['seq'];
					$this->salesmodel->sales_modify($params);
				}else{
					$this->ordermodel->update_tax_sales($data_refund['order_seq']);
				}
			}

			/* 로그저장 */
			$logTitle = "환불완료(".$refund_code.")";
			$logDetail = "관리자가 환불완료처리를 하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($data_order['order_seq'],'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams);

			//회원일경우 id 불러오기
			if(trim($data_order['member_seq'])){
				$userid		= $this->membermodel->get_member_userid(trim($data_order['member_seq']));
			}

			$params = array();
			$params['shopName']		= $this->config_basic['shopName'];
			$params['ordno']		= $data_order['order_seq'];
			$params['user_name']	= $data_order['order_user_name'];
			$params['member_seq']	= $data_order['member_seq'];
			if( $data_order['order_cellphone'] ) {
				// 오픈마켓 주문 메일/문자 발송 금지
				$isMarketOrder		= $this->connectormodel->checkIsMarketOrder($data_order['order_seq']);
	
				if ($isMarketOrder == false) {
				if($refund_goods_coupon_ea){
					$this->load->model('returnmodel');
					$data_return = $this->returnmodel->get_return_refund_code($refund_code);
					$data_return_item 	= $this->returnmodel->get_return_item($data_return['return_code']);
					if($data_refund['refund_type']=='return') {
						coupon_send_sms_refund($data_return_item[0]['export_code'],$data_order);
					}else{
						coupon_send_sms_cancel($data_return_item[0]['export_code'],$data_order);
					}
				}else{
					$smsemailtype = ($data_refund['refund_type']=='return') ? 'refund' : 'cancel';
					//SMS 데이터 생성
					$commonSmsData[$smsemailtype]['phone'][] = $data_order['order_cellphone'];
					$commonSmsData[$smsemailtype]['params'][] = $params;
					$commonSmsData[$smsemailtype]['order_no'][] = $data_order['order_seq'];
					if(count($commonSmsData) > 0){
						commonSendSMS($commonSmsData);
					}
					//sendSMS($data_order['order_cellphone'], $smsemailtype, '', $params);
				}
				}
			}

			//이벤트 판매건/주문건/주문금액 @2013-11-15
			if($data_refund_item){
				foreach($data_refund_item as $item) {
					if( $item['event_seq'] ) {
						$this->eventmodel->event_order($item['event_seq']);
						$this->eventmodel->event_order_batch($item['event_seq']);
					}
				}
			}

			/**
			* 4-2 환불관련 정산개선 시작
			* step1->step2 순차로 진행되어야 합니다.
			* @
			**/
			  $this->load->helper('accountall');
			  if(!$this->accountallmodel)	$this->load->model('accountallmodel');
			  if(!$this->providermodel)	$this->load->model('providermodel');
			  if(!$this->refundmodel)		$this->load->model('refundmodel');
			  if(!$this->returnmodel)		$this->load->model('returnmodel');
			
			  //step1 주문금액별 정의/비율/단가계산 후 정렬 => step2 적립금/이머니 update
			  /* 저장
			  된 정보 로드 $data_order, $data_refund, $data_refund_item */
//			  if( $data_refund['refund_emoney'] || $data_refund['refund_cash'] ) {
//			  	$this->accountallmodel->update_ratio_emoney_cash_refund($data_order['order_seq'], $refund_code, $data_order, $data_refund, $data_refund_item);
//			  }
			  //step2 통합정산 생성(미정산매출 환불건수 업데이트)
			  
			  //정산대상 수량업데이트
			  $this->accountallmodel->update_calculate_sales_ac_ea($data_order['order_seq'],$refund_code, 'refund', $data_refund_item);
			  //정산확정 처리
			  /* 저장된 정보 로드 $data_order, $data_refund, $data_refund_item */
			  $this->accountallmodel->insert_calculate_sales_order_refund($data_order['order_seq'],$refund_code, $data_refund['cancel_type'], $data_order, $data_refund, $data_refund_item);
			  //debug_var($this->db->queries);
			  //debug_var($this->db->query_times);
			/**
			* 4-2 환불관련 정산개선 끝
			* step1->step2 순차로 진행되어야 합니다.
			* @
			**/

			// 가용재고의 계산
			$aReservationGoods = array();
			if($data_refund_item){
				foreach($data_refund_item as $item) {
					if(!in_array($item['goods_seq'], $aReservationGoods)) {
						$aReservationGoods[] = $item['goods_seq'];
					}
				}
				foreach($aReservationGoods as $aReservationGoodsSeq){
					$this->goodsmodel->modify_reservation_real($aReservationGoodsSeq);
				}
			}

			$callback = "parent.document.location.reload();";
			openDialogAlert("환불처리가 완료되었습니다.",400,140,'parent',$callback);
		}else{
			$callback = "parent.document.location.reload();";
			openDialogAlert("환불정보가 저장되었습니다.",400,140,'parent',$callback);
		}
	}

	// 관리자메모 변경
	public function admin_memo()
	{
		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('refund_act');
		if(!$auth){
			$callback = "history.go(-1);";
			$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
			$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
			$this->template->print_("denined");
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$refund_seq = $_GET['seq'];
		$this->validation->set_rules('admin_memo','관리자메모','trim|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		$data['admin_memo'] = $_POST['admin_memo'];
		$this->db->where('refund_seq', $refund_seq);
		$this->db->update('fm_order_refund', $data);
		openDialogAlert("관리자 메모가 변경 되었습니다.",400,140,'parent','');
	}

	public function batch_reverse_refund(){
		$result = array();
		foreach($_POST['code'] as $refund_code){
			$result[] = $this->exec_reverse_refund($refund_code);
		}
		echo implode("<br />",$result);
	}

	public function exec_reverse_refund($refund_code,$mode='',$npay_data=array()){

		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		$this->load->model('exportmodel');
		$this->load->helper('order');

		$data_refund 		= $this->refundmodel->get_refund($refund_code);
		$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
		$data_order			= $this->ordermodel->get_order($data_refund['order_seq']);

		# npay 환불 삭제 불가
		$npay_use = npay_useck();
		if($npay_use && $mode != 'npay' && $data_order['pg'] == "npay"){
			return $refund_code." - Npay 환불 건은 삭제하실 수 없습니다.";
			exit;
		}
		
		# KakaoPay 구매 환불 삭제 불가
		$kakao_pay_chk = talkbuy_useck();
		if ($kakao_pay_chk && $data_order["talkbuy_order_id"] && $data_order["pg"] === "talkbuy") {
			return $refund_code." - KakaoPay 구매 환불 건은 삭제하실 수 없습니다.";
			exit;
		}

		if($data_refund['refund_type'] == 'return'){
			return "{$refund_code} 환불 철회 불가 <br/>반품에 의해 발생한 환불 건은 반품 리스트에서 철회해주세요";
		}

		if($data_refund['status'] == 'complete'){
			return "{$refund_code} - 환불 완료된 건은 삭제하실 수 없습니다.";
		}

		$this->db->trans_begin();
		$rollback = false;

		// 출고량 업데이트를 위한 변수선언
		$r_reservation_goods_seq = array();
		$reject_item				= array();

		//배송비환불 삭제시 옵션별처리 제외 @2016-09-02
		if($data_refund['refund_type'] != 'shipping_price') {
			foreach($data_refund_item as $refund_item){

				$reject_use = true;
				if($npay_use && $mode == "npay" && $refund_item['npay_product_order_id']){
					if(in_array($refund_item['npay_product_order_id'],$npay_data)){
						$reject_item[] = $refund_item['refund_item_seq'];
					}else{
						$reject_use = false;
					}
				}

				if($reject_use){

					if($refund_item['opt_type']=='opt'){
						
						//재주문(맞교환)의 환불취소로 인한 원주문 추출 및 교체 @2016-11-29
						$query = $this->db->get_where('fm_order_item_option',
							array(
							'order_seq'=>$data_refund['order_seq'],
							'top_item_option_seq'=>$refund_item['option_seq'],
							'top_item_seq'=>$refund_item['item_seq'])
						);
						$result = $query->row_array();

						if($result['item_option_seq']) $refund_item['option_seq'] = $result['item_option_seq'];
						if($result['item_seq']) $refund_item['item_seq'] = $result['item_seq'];

						$option_seq = $refund_item['option_seq'];

						$query = "select * from fm_order_item_option where item_option_seq=?";
						$query = $this->db->query($query,array($option_seq));
						$optionData = $query->row_array();

						if($optionData['step']==85){
							$this->db->set('step','25');
						}

							$this->db->set('refund_ea','refund_ea-'.$refund_item['ea'],false);
							$this->db->where('item_option_seq',$option_seq);
							$this->db->update('fm_order_item_option');

						$opt_type = 'option';
						$this->ordermodel->set_step_ea(85,-$refund_item['ea'],$option_seq,$opt_type);

						if($data_refund['refund_type'] != 'return'){
							// 출고량 업데이트를 위한 변수정의
							if(!in_array($refund_item['goods_seq'],$r_reservation_goods_seq)){
								$r_reservation_goods_seq[] = $refund_item['goods_seq'];
							}
						}

						## 마일리지&포인트 적립내역이 없으면. fm_goods_export_item 마일리지 지급예정 수량 업데이트 2015-03-30 pjm
						if($refund_item['reserve'] == 0 && $refund_item['point'] == 0){
							$this->db->set('reserve_return_ea','reserve_return_ea-'.$refund_item['ea'],false);
							$this->db->set('reserve_ea','reserve_ea+'.$refund_item['ea'],false);
							$this->db->where('item_seq',$refund_item['item_seq']);
							$this->db->where('option_seq',$refund_item['option_seq']);
							$this->db->update('fm_goods_export_item');
						}

					}else if($refund_item['opt_type']=='sub'){
						
						//재주문(맞교환)의 환불취소로 인한 원주문 추출 및 교체 @2016-11-29
						$query = $this->db->get_where('fm_order_item_suboption',
							array(
							'top_item_suboption_seq'=>$refund_item['option_seq'])
						);
						$result = $query->row_array();
						if($result['item_suboption_seq']) $refund_item['option_seq'] = $result['item_suboption_seq'];

						$suboption_seq = $refund_item['option_seq'];

						$query = "select * from fm_order_item_suboption where item_suboption_seq=?";
						$query = $this->db->query($query,array($suboption_seq));
						$optionData = $query->row_array();

						if($optionData['step']==85){
							$this->db->set('step','25');
						}
						$this->db->set('refund_ea','refund_ea-'.$refund_item['ea'],false);
						$this->db->where('item_suboption_seq',$suboption_seq);
						$this->db->update('fm_order_item_suboption');

						$opt_type = 'suboption';
						$this->ordermodel->set_step_ea(85,-$refund_item['ea'],$suboption_seq,$opt_type);

						if($data_refund['refund_type'] != 'return'){
							// 출고량 업데이트를 위한 변수정의
							if(!in_array($refund_item['goods_seq'],$r_reservation_goods_seq)){
								$r_reservation_goods_seq[] = $refund_item['goods_seq'];
							}
						}

						## 마일리지&포인트 적립내역이 없으면. fm_goods_export_item 마일리지 지급예정 수량 업데이트 2015-03-30 pjm
						if($refund_item['reserve'] == 0 && $refund_item['point'] == 0){
							$this->db->set('reserve_return_ea','reserve_return_ea-'.$refund_item['ea'],false);
							$this->db->set('reserve_ea','reserve_ea+'.$refund_item['ea'],false);
							$this->db->where('item_seq',$refund_item['item_seq']);
							$this->db->where('suboption_seq',$refund_item['suboption_seq']);
							$this->db->update('fm_goods_export_item');
						}
					}
				}

				// 출고예약량 업데이트
				$this->goodsmodel->modify_reservation_real($refund_item['goods_seq']);
			}//endforeach
		}//endif

		if($mode == "npay"){
			$actor = "Npay";

			$logTitle	= "취소철회({$refund_code})";
			$logDetail	= "{$reject_product_id} 환불건을 삭제처리했습니다.";
			$this->ordermodel->set_log($data_order['order_seq'],'process',"Npay",$logTitle,$logDetail,'','',
				'npay');


		}else{
			$actor = $this->managerInfo['mname'];
		}

		$logTitle	= "환불삭제({$refund_code})";
		$logDetail	= "{$refund_code} 환불건을 삭제처리했습니다.";
		$this->ordermodel->set_log($data_order['order_seq'],'process',$actor,$logTitle,$logDetail,'','',$mode);

		// 환불 삭제시 주문상태값 되돌리기 추가:배송비환불 삭제시 상태변경 제외 @2016-09-02 ysm
		if ( ($data_refund['refund_type'] != 'shipping_price') && in_array($data_order['step'], array('45','55','65','75','85'))) {
			$prev_step = $data_order['step'];
			$this->ordermodel->set_order_step($data_order['order_seq']);
			$data_order	= $this->ordermodel->get_order($data_order['order_seq']);
			$target_step = $data_order['step'];

			if($prev_step != $target_step){
				$this->ordermodel->set_log($data_order['order_seq'],'process',$actor,'되돌리기 ('.$this->arr_step[$prev_step].' => '.$this->arr_step[$target_step].')','-','','',$mode);
			}
		}
		if($npay_use && $mode == "npay" && $reject_item){

			$sql = "delete from fm_order_refund_item where refund_item_seq in(".implode(",",$reject_item).")";
			$this->db->query($sql);

			if(count($reject_item) == count($data_refund_item)){
				$sql = "delete from fm_order_refund where refund_code=?";
				$this->db->query($sql, $refund_code);
			}

		}else{

			$sql = "delete from fm_order_refund where refund_code=?";
			$this->db->query($sql, $refund_code);

			$sql = "delete from fm_order_refund_item where refund_code=?";
			$this->db->query($sql, $refund_code);

		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		if ($this->db->trans_status() === FALSE || $rollback == true)
		{
		    $this->db->trans_rollback();
		    echo "환불삭제 처리중 오류가 발생했습니다.";
			exit;
		}
		else
		{
		    $this->db->trans_commit();
		}

		return "{$refund_code} 환불 철회 완료 <br/>해당 주문건의 주문 상태가 ".$this->arr_step[$prev_step]." → ".$this->arr_step[$target_step]."로 변경되었습니다.";
	}

	public function shipping_price_refund(){

		$return['success']		= 'Y';


		$this->load->model('ordermodel');
		$this->load->model('refundmodel');

		$refund_data = array(
				'order_seq'			=> $_POST['order_seq'],
				'bank_name'			=> ($_POST['refund_bank_code'] > 0) ? $_POST['refund_bank_name'] : '',
				'bank_depositor'	=> $_POST['refund_depositor'],
				'bank_account'		=> $_POST['refund_account'],
				'refund_reason'		=> $_POST['reason_detail'],
				'refund_type'		=> 'shipping_price',
				'cancel_type'		=> 'partial',
				'regist_date'		=> date('Y-m-d H:i:s'),
				'manager_seq'		=> $this->managerInfo['manager_seq']
		);


		$item_list		= $this->ordermodel->get_item_option($_POST['order_seq']);
		$refund_item	= array();

		foreach((array)$item_list as $row){
			if($row['provider_seq'] == $_POST['provider_seq']){
				//반품수량이 0인 임의 상품 등록
				$refund_item[0]['item_seq']			= $row['item_seq'];
				$refund_item[0]['option_seq']		= $row['item_option_seq'];
				$refund_item[0]['ea']				= 0;
				$refund_item[0]['give_reserve']		= 0;
				$refund_item[0]['give_point']		= 0;
				$refund_item[0]['give_reserve_ea']	= 0;

				# 동일 배송그룹의 최초 배송비(기본배송비 or 개별배송비 가져오기 + 추가배송비)
				$refund_delivery	= $this->ordermodel->get_delivery_existing_price($_POST['order_seq'],$row['shipping_seq']);
				$refund_item[0]['refund_delivery_price']	= $refund_delivery;
				break;
			}
		}

		if(isset($refund_item[0])){
			$refund_code = $this->refundmodel->insert_refund($refund_data,$refund_item);

			$logTitle	= "배송비 환불신청(".$refund_code.")";
			$logDetail	= "배송비 환불을 신청하였습니다.";
			$logParams	= array('refund_code' => $refund_code);
			$this->ordermodel->set_log($_POST['order_seq'],'process',$this->managerInfo['mname'],$logTitle,$logDetail,$logParams);

			$refund_provider['provider_seq']			= $_POST['provider_seq'];
			$refund_provider['refund_expect_price']		= 0;
			$refund_provider['adjust_refund_price']		= 0;
			$refund_provider['refund_price']			= 0;

			$this->refundmodel->set_provider_refund($refund_code, $refund_provider);

			$return['success']		= 'Y';
		}else{
			$return['success']		= 'N';
		}

		echo json_encode($return);

	}

}

/* End of file refund_process.php */
/* Location: ./app/controllers/admin/refund_process.php */

