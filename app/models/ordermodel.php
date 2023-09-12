<?php
class ordermodel extends CI_Model
{
	public function __construct()
	{
		// 결제실패 99추가(pjm,2014-11-03)
		$this->all_step	= array('15', '25', '35', '45', '55', '65', '75', '85', '95', '40', '50', '60', '70','99');

		// 주문상세 버튼 활성화 상태 정의
		$action['order_deposit'] 		= array('15'); // 입금확인
		$action['goods_ready'] 			= array('25','35', '40', '50', '60', '70');  // 상품준비
		$action['goods_export'] 		= array('25','35','40','50','60','70'); // 출고처리
		$action['cancel_order'] 		= array('15');  // 주문무효
		$action['cancel_payment']		= array('25','35','40','50','60','70'); // 결제취소
		$action['cancel_payment_etc']	= array('25','35'); // 결제취소 (기타)
		// $action['return_list']			= array('55','60','65','70','75'); // 반품신청
		$action['return_coupon_list']	= array('55','60','65','70','75'); // 티켓상품 환불신청
		// $action['exchange_list']		= array('55','60','65','70','75'); // 맞교환신청
		$action['return'] 				= array('70','75'); // 반품처리
		$action['enuri'] 				= array('15'); // 에누리
		$action['change_bank']			= array('15'); // 무통장정보변경
		$action['shipping_region']		= array('15','25','35','40','45'); // 배송정보변경
		$action['cash_receipts'] 		= array('15','25','35','40','45','50','55','60','65','70','75','85'); // 현금영수증
		$action['tax_bill'] 			= array('15','25','35','40','45','50','55','60','65','70','75','85'); // 세금계산서
		$action['card_slips'] 			= array('25','35','40','45','50','55','60','65','70','75','85'); // 카드전표

		$action['canceltype_cancel_order'] 			= array('15');  //청약철회 > 주문무효 가능상태 : 주문접수
		$action['canceltype_cancel_payment'] 	= array('25','35','40','50','60','70');  //청약철회 > 결제취소 불가: 결제확인 이후
		$action['canceltype_return_order'] 			= array('15');  //청약철회 > 반품/교환/환불 불가

		$action['social_cancel_payment'] 	= array('25','35','40','50','55','60','70','75'); // 티켓상품 결제취소
		$this->able_step_action 	= $action;
	}

	/* 사용자화면 주문리스트 */
	public function get_order_list($sc = [])
	{
		// load models
		$this->load->model('returnmodel');
		$this->load->model('refundmodel');

		//
		$this->arr_step    = $this->arr_step ?: config_load('step');
		$this->arr_payment = $this->arr_payment ?: config_load('payment');
		$this->cfg_order   = $this->cfg_order ?: config_load('order');

		//
		$sc['page']    = $sc['page'] ?: '1';
		$sc['perpage'] = $sc['perpage'] ?: '10';

		//
		$this->db->select ('o.*')
			->from('fm_order o');

		// 회원 체크
		if ($sc['member_seq']) {
			$this->db->where('o.member_seq', $sc['member_seq']);
		}

		// 검색어
		$sc['keyword'] = trim($sc['keyword']);
		if ($sc['keyword']) {
			//
			$this->db->join('fm_order_item oi', 'oi.order_seq = o.order_seq', 'inner')
				->distinct()
				->like('o.order_seq', $sc['keyword'], 'both')
				->like('oi.goods_name', $sc['keyword'], 'both');
		}

		// 검색 기간
		if ($sc['regist_date'][0] ) {
			$this->db->where('`o`.`regist_date` >=', $sc['regist_date'][0] . ' 00:00:00');
		}

		// 검색 기간
		if ($sc['regist_date'][1]) {
			$this->db->where('`o`.`regist_date` <=', $sc['regist_date'][1] . ' 23:59:59');
		}

		// 삭제주문 조건 추가
		if ($sc['hidden']) {
			$this->db->where('o.hidden', $sc['hidden']);
		}

		// 상태
		if ($sc['step_type']) {
			switch ($sc['step_type']) {
				case 'order':
					$this->db->where('o.step', '15');
					break;
				case 'deposit':
					$this->db->where_in('o.step', ['25', '35']);
					break;
				case 'deposit_only':
					$this->db->where('o.step', '25');
					break;
				case 'ready':
					$this->db->where_in('o.step', ['35', '40', '45']);
					break;
				case 'ready_only':
					$this->db->where('o.step', '35');
					break;
				case 'export':
					$this->db->where_in('o.step', ['40', '45','50', '55', '60', '65', '70']);
					break;
				case 'export_and_complete':
					$this->db->where_in('o.step', ['40', '45', '50', '55', '60', '65', '70', '75']);
					break;
				case 'delivery_ing':
					$this->db->where("`o`.`step` BETWEEN '50' AND '70'", null, false);
					break;
				case 'delivery_complete':
					$this->db->where('o.step', '75');
					break;
				case 'non_attempt':
					$this->db->where('`o`.`step` <>', '0');
					break;
			}
		}

		//
		$this->db->order_by('o.regist_date', 'DESC');

		//
		$sql = $this->db->get_compiled_select();

		//
		$result = select_page($sc['perpage'], $sc['page'], 10, $sql, $bind);
		$result['page']['querystring'] = get_args_list();

		//
		$no = 0;
		$result_order_seq = [];
		$tot_settleprice = [];
		$tot = [];
		$step_cnt = [];

		foreach ($result['record'] as $k => &$data) {
			$no++;

			$result_order_seq[] = $data['order_seq'];

			//
			$data['mstep']    = $this->arr_step[$data['step']];
			$data['mpayment'] = $this->arr_payment[$data['payment']];

			$step_cnt[$data['step']]++;

			$tot_settleprice[$data['step']] += $data['settleprice'];
			$tot[$data['step']][$data['important']] += $data['settleprice'];

			//
			$data['step_cnt'] = $step_cnt;
			$data['tot_settleprice'] = $tot_settleprice;
			$data['tot'][$data['important']] = $tot[$data['step']][$data['important']];

			###
			$data['opt_cnt']  = $this->get_option_count('opt', $data['order_seq']);
			$data['gift_cnt'] = $this->get_option_count('gift', $data['order_seq']);
			$data['gift_nm']  = $this->get_gift_name($data['order_seq']);

			//반품정보 가져오기 (구 스킨 사용)
			$data['return_list_ea'] = 0;
			$data_return = $this->returnmodel->get_return_for_order($data['order_seq']);
			if ($data_return) {
				foreach ($data_return as $row_return) {
					$data['return_list_ea'] += $row_return['ea'];
				}
			}

			//환불정보 가져오기 (구 스킨 사용)
			$data['refund_list_ea'] = 0;
			$data['cancel_list_ea'] = 0;
			$data_refund = $this->refundmodel->get_refund_for_order($data['order_seq']);
			if ($data_refund) {
				foreach ($data_refund as $row_refund) {
					if ($row_refund['refund_type'] == 'cancel_payment' ) {
						$data['cancel_list_ea'] += $row_refund['ea'];
					} else {
						$data['refund_list_ea'] += $row_refund['ea'];
					}
				}
			}

			//
			$data['reserve'] = $data['reserve'] + $data['subreserve'];
			$data['point'] = $data['point'] + $data['subpoint'];

			if ($step_cnt[$data['step']] == 1) {
				$data['start'] = true;
				$ek = $k - 1;
				if ($ek >= 0 ) {
					$data['end'] = true;
				}
			}
		}

		//
		if ($result['record']) {
			$result['record'][$k]['end'] = true;
			foreach ($result['record'] as &$data) {
				$data['no'] = $no;
				$no--;
			}
		}

		// 구 스킨 사용
		if ($result_order_seq) {
			// 상품번호, 상품명, 이미지를 구하기 위해 sub query 최적화
			$selectedFields = [
				'oi.order_seq',
				'oi.goods_seq',
				'oi.goods_name',
				'oi.image',
				'p.provider_name',
			];
			$query = $this->db->select($selectedFields)
				->from('fm_order_item oi')
				->join('fm_provider p', 'p.provider_seq = oi.provider_seq', 'inner')
				->where_in('oi.order_seq', $result_order_seq)
				->order_by('oi.item_seq', 'ASC')
				->get();

			foreach($query->result_array() as $item_data) {
				list($k) = array_keys($result_order_seq, $item_data['order_seq']);
				if (! $result['record'][$k]['goods_seq']) {
					$result['record'][$k]['goods_seq']	= $item_data['goods_seq'];
					$result['record'][$k]['goods_name']	= $item_data['goods_name'];
					$result['record'][$k]['image']		= $item_data['image'];
					$result['record'][$k]['provider_name']	= $item_data['provider_name'];
				}

				// 배송사명이 본사일 경우
				if ($result['record'][$k]['provider_name'] == "본사") {
					$result['record'][$k]['provider_name'] = getAlert("sy009"); // "본사";
				}

				//주문상품의 이미지가 없는경우 실제상품의 이미지를 가져옴
				if (!$result['record'][$k]['image'] || !file_exists(ROOTPATH.$result['record'][$k]['image'])) {
					$result['record'][$k]['image'] = viewImg($result['record'][$k]['goods_seq'], 'thumbCart');
				}
			}

			/*
			// 필수옵션 마일리지, 포인트 각각  sub query 최적화
			$selectedFields = [
				'oi.order_seq',
				'SUM(`oio`.`reserve` * `oio`.`ea`) `tot_reserve`',
				'SUM(`oio`.`point` * `oio`.`ea`) `tot_point`',
			];
			$query = $this->db->select($selectedFields)
				->from ('fm_order_item oi')
				->join('fm_order_item_option oio', 'oio.item_seq = oi.item_seq', 'left')
				->where_in('oi.order_seq', $result_order_seq)
				->group_by('oi.order_seq')
				->get();
			foreach($query->result_array() as $opt_data) {
				list($k) = array_keys($result_order_seq,$opt_data['order_seq']);
				$result['record'][$k]['reserve'] = $opt_data['tot_reserve'];
				$result['record'][$k]['point']	 = $opt_data['tot_point'];
			}

			// 상품번호, 상품명, 이미지를 구하기 위해 sub query 최적화
			$selectedFields = [
				'oi.order_seq',
				'SUM(`ois`.`reserve` * `ois`.`ea`) `tot_reserve`',
				'SUM(`ois`.`point` * `ois`.`ea`) `tot_point`',
			];
			$query = $this->db->select($selectedFields)
				->from ('fm_order_item oi')
				->join('fm_order_item_suboption ois', 'ois.item_seq = oi.item_seq', 'left')
				->where_in('oi.order_seq', $result_order_seq)
				->group_by('oi.order_seq')
				->get();
			foreach($query->result_array() as $sub_data){
				list($k) = array_keys($result_order_seq, $sub_data['order_seq']);
				$result['record'][$k]['subreserve']	= $sub_data['tot_reserve'];
				$result['record'][$k]['subpoint']	= $sub_data['tot_point'];
			}
			*/
		}

		//
		return $result;
	}


	public function get_option_count($type='opt', $order_seq){
		if($type=='opt'){
			$sql = "select count(item_seq) as cnt from fm_order_item A left join fm_goods B on A.goods_seq = B.goods_seq where A.order_seq = '{$order_seq}'";
		}else{
			$sql = "select count(item_seq) as cnt from fm_order_item A left join fm_goods B on A.goods_seq = B.goods_seq where A.order_seq = '{$order_seq}' AND B.goods_type = 'gift'";
		}
		$query = $this->db->query($sql);
		$data = $query->result_array();
		return $data[0]['cnt'];
	}

	public function get_gift_name($order_seq){
		$sql = "select B.goods_name from fm_order_item A left join fm_goods B on A.goods_seq = B.goods_seq where A.order_seq = '{$order_seq}' AND B.goods_type = 'gift'";

		$query = $this->db->query($sql);
		$gift_nm = "";
		foreach($query->result_array() as $k){
			$temp[] = $k['goods_name'];
		}
		$gift_nm = @implode(", ", $temp);
		return $gift_nm;
	}


	public function get_order_seq($loop = false)
	{
		$query = "select regist_date from fm_order_sequence where regist_date=? limit 1";
		$query = $this->db->query($query,array(date('Y-m-d')));
		$data = $query -> result_array();
		if( !$data[0]['regist_date'] ){
			$query = "truncate table fm_order_sequence";
			$this->db->query($query);
			$query = "alter table fm_order_sequence auto_increment = 17530";
			$this->db->query($query);
		}
		// 중복과 시퀀스 조합을 방지하기 위해 재귀 호출 및 시퀀스 재적용 by hed
		if($loop){
			$query = "alter table fm_order_sequence auto_increment = 27530";
			$this->db->query($query);
		}

		$insert_params['regist_date'] 	= date('Y-m-d');
		$this->db->insert('fm_order_sequence', $insert_params);
		$add_order_seq = $this->db->insert_id();
		if(strlen($add_order_seq)=="1"){
			$order_seq = $this->get_order_seq(true);
		}else{
			$order_seq = date('YmdHis').$add_order_seq;
		}
		return $order_seq;
	}

	/**
	 * 	ordermodel->insert_order 를 실행하기 위해 필요한 파라미터 정리
	 *  아래 목록의 내용을 각각 $_GET, $_POST, $_COOKIE , $params(첫번째 인자)를 통해서 데이터를 입력함

		// =====================================================================
		// 주문 환경 정보
		// =====================================================================
		$_GET['mode']										= 'direct'; // 주문 방식 | 기본 cart | choice : 선택구매, cart : 장바구니구매, admin : 관리자구매, direct : 바로구매
		$_POST['adminOrder']								= ''; // 관리자 주문 여부 | admin : 관리자 주문 | adminOrder 가 admin이고 admin_memo일 때 메모 저장 | 관리자 주문일 경우 $this->session->userdata["manager"]["manager_id"] 세션에서 관리자 고유키 입력
		// $this->session->userdata["manager"]["manager_id"]	= '';
		$_POST['person_seq']								= ''; // 개인 결제 주문 | person_seq 와 admin_memo 동시 입력 시 메모 저장
		$_POST['admin_memo']								= ''; // 관리자 메모
		$_POST['member_seq']								= ''; // 회원 번호 | 일반적으론 $this->userInfo['member_seq']세션에서 추출함, adminOrder 가 admin일때 값 할당
		// $this->userInfo['member_seq']						= '';
		$_POST['clearance_unique_personal_code']			= ''; // 해외배송상품 개인통관번호
		$_POST['overwrite_sitetype']						= ''; // sitetype 덮어쓰기, ci환경에서 만들어내는 변수를 덮어씀 | APP_ANDROID : 안드로이드, APP_IOS : 아이폰, M : 모바일, F : 페이스북, P : PC
		$_POST['overwrite_skintype']						= ''; // skintype 덮어쓰기, ci환경에서 만들어내는 변수를 덮어씀 | M : 모바일, F : 페이스북, OFF_M : 오프라인 모바일, OFF_F : 오프라인 페이스북, OFF_P : 오프라인 PC, P : PC


		// =====================================================================
		// 배송 메세지
		// =====================================================================
		$_POST['each_msg']									= ''; // 개별 배송 메세지 여부 | Y : 개별 메세지, N : 일반
		$_POST['each_memo']									= ''; // 개별 배송 메세지 array 타입
		$_POST['memo']										= ''; // 배송 메세지


		// =====================================================================
		// 배송지 정보
		// =====================================================================
		$_POST['international']								= ''; // 국제발송 여부 | 1 : 국제 발송, 0 : 국내 발송
		$_POST['address_nation_key']						= 'KOR'; // 배송 국가 코드 | 기본 KOR

		$_POST['shipping_method_international']				= ''; // 국제 배송 방법
		$_POST['region']									= ''; // 국제 배송 국가
		$_POST['international_address']						= ''; // 국제 배송 주소
		$_POST['international_town_city']					= ''; // 국제 배송 도시
		$_POST['international_county']						= ''; // 국제 배송 지역
		$_POST['international_postcode']					= ''; // 국제 배송 우편번호
		$_POST['international_country']						= ''; // 국제 배송 국가

		$_POST['shipping_method']							= ''; // 국내 배송 방법 | 미사용
		$_POST['recipient_zipcode']							= ''; // 국내 배송 구 우편번호 6자리 | array | 미사용
		$_POST['recipient_new_zipcode']						= ''; // 국내 배송 신 우편번호 5자리
		$_POST['recipient_address_type']					= ''; // 국내 도로명주소 구분 | street : 도로명, zibun : 지번
		$_POST['recipient_address']							= ''; // 국내 배송지 지번 주소
		$_POST['recipient_address_street']					= ''; // 국내 배송지 도로명 주소
		$_POST['recipient_address_detail']					= ''; // 국내 배송지 상세


		// =====================================================================
		// 결제 정보
		// =====================================================================
		$_POST['payment']									= ''; // 결제 방식 | card : 신용카드, bank : 무통장입금, account : 계좌이체, cellphone | 핸드폰결제, virtual : 가상계좌, escrow_virtual : 에스크로 가상계좌, escrow_account : 에스크로 계좌이체, point : 포인트, paypal : 페이팔
		$_POST['typereceipt']								= ''; // 매출증빙 | 0 : 매출전표, 1: 세금계산서, 2: 현금영수증
		$_POST['depositor']									= ''; // 입금자명
		$_POST['bank']										= ''; // 입금 계좌 정보
		$_POST['emoney']									= ''; // 할인 총 마일리지
		$_POST['cash']										= ''; // 할인 총 예치금
		$_POST['enuri']										= ''; // 할인 총 에누리


		// =====================================================================
		// 주문자&수신자 정보
		// =====================================================================
		$_POST['order_user_name']							= ''; // 주문자명
		$_POST['order_phone']								= ''; // 주문자 연락처 | array
		$_POST['order_cellphone']							= ''; // 주문자 핸드폰 | array
		$_POST['order_email']								= ''; // 주문자 이메일
		$_POST['recipient_user_name']						= ''; // 수신자명
		$_POST['recipient_phone']							= ''; // 수신자 연락처 | array
		$_POST['recipient_cellphone']						= ''; // 수신자 핸드폰 | array
		$_POST['recipient_email']							= ''; // 수신자 이메일 | 티켓 상품 일 때만 입력됨


		// =====================================================================
		// 결제 부가 정보
		// =====================================================================
		$_POST['download_seq']								= ''; // 쿠폰 고유키 : 현재 미사용 추측
		$_POST['coupon_sale']								= ''; // 쿠폰 할인 금액 : 현재 미사용 추측
		$params['krw_exchange_rate']						= ''; // 통화별 환율정보 | get_exchange_rate("KRW")
		$params["ordersheet_seq"]							= ''; // 장바구니 쿠폰 고유키
		$params["ordersheet_sale"]							= ''; // 장바구니 쿠폰 할인 금액
		$params["ordersheet_sale_krw"]						= ''; // 장바구니 쿠폰 할인 금액(원화기준)
		$params['settle_price']								= ''; // 실 결제 금액
		$params['shipping_cost']							= ''; // 총 배송비
		$params['shipping']									= ''; // 배송방법 | 미사용
		$params['pgCompany']								= ''; // 결제 모듈


		// =====================================================================
		// 유입경로 정보
		// =====================================================================
		$_COOKIE['marketplace']								= ''; // 유입매체
		$_COOKIE['refererDomain']							= ''; // 유입경로 도메인
		$_COOKIE['shopReferer']								= ''; // 유입경로 풀 URL
		$_COOKIE["curation"]								= ''; // 고객 리마인드 유입 경로
	 *
	 * @param type $params
	 * @param type $shipInfo
	 * @return type
	 */
	public function insert_order($params,$shipInfo='')
	{
		$settle_price			= $params['settle_price'];
		$shipping_cost			= $params['shipping_cost'];
		$shipping				= $params['shipping'];
		$pgCompany				= $params['pgCompany'];

		$mode		= "cart";
		$emoney_use = "none";
		$order_seq	= $this->get_order_seq();
		//$policy = $shipping[0][0];
		if( isset($_GET['mode']) ) $mode = $_GET['mode'];
		$session_id = session_id();
		if($this->userInfo['member_seq']) $member_seq = $this->userInfo['member_seq'];

		if($_POST['adminOrder'] == 'admin'){
			$member_seq=$_POST['member_seq'];
		}

		$order_cfg = ($this->cfg_order) ? $this->cfg_order : config_load('order');

		# 결제통화, 결제통화기준 환율, 결제통화기준 결제금액 -------------------------------------------------------
		$pg_currency			= $this->config_system['basic_currency'];
		$pg_currency_exchange	= get_exchange_rate($this->config_system['basic_currency']);	//결제통화에 대한 환율
		if($pgCompany == 'paypal'){
            $paypal_config              = config_load("paypal");
			$pg_currency				= $paypal_config['paypal_currency'];
			$pg_currency_exchange		= $this->config_currency[$pg_currency]['currency_exchange'];
		}
		if($pgCompany == 'eximbay'){
			$payment_config				= config_load('eximbay');
			$pg_currency				= $payment_config['eximbay_cur'];
			$pg_currency_exchange		= $this->config_currency[$pg_currency]['currency_exchange'];
		}
		if($pg_currency != $this->config_system['basic_currency']){
			$payment_price				= get_currency_exchange($settle_price, $pg_currency);
		}else{
			$payment_price				= get_cutting_price($settle_price);
		}
		#  ------------------------------------------------------- -----------------------

		if($_POST['international']==null) $_POST['international'] = 0;

		// 카드, 휴대폰일경우 매출전표만 출력가능하게 수정
		if($_POST['payment'] == 'card' || $_POST['payment'] == 'cellphone' ) $_POST['typereceipt'] = 0;

		$insert_params['order_seq'] 						= $order_seq;
		$insert_params['person_seq'] 						= $_POST['person_seq'];
		$insert_params['original_settleprice'] 				= get_cutting_price($settle_price);
		$insert_params['settleprice'] 						= get_cutting_price($settle_price);
		$insert_params['payment_price']						= $payment_price;
		$insert_params['mode'] 								= $mode;
		$insert_params['payment'] 							= $_POST['payment'];
		$insert_params['step'] 								= '0';
		$insert_params['deposit_yn'] 						= 'n';
		$insert_params['depositor'] 						= $_POST['depositor'];
		$insert_params['bank_account'] 						= $_POST['bank'];
		$insert_params['emoney_use'] 						= $emoney_use;
		$insert_params['emoney'] 							= get_cutting_price($_POST['emoney']);
		$insert_params['cash_use'] 							= 'none';
		$insert_params['cash'] 								= get_cutting_price($_POST['cash']);
		$insert_params['enuri'] 							= get_cutting_price($_POST['enuri']);
		$insert_params['member_seq']						= $member_seq;
		$insert_params['order_user_name'] 					= $_POST['order_user_name'];
		$insert_params['order_phone'] 						= implode('-',$_POST['order_phone']);
		$insert_params['order_cellphone'] 					= implode('-',$_POST['order_cellphone']);
		$insert_params['order_email'] 						= $_POST['order_email'];
		$insert_params['recipient_user_name']				= $_POST['recipient_user_name'];
		$insert_params['recipient_phone'] 					= implode('-',$_POST['recipient_phone']);
		$insert_params['recipient_cellphone'] 				= implode('-',$_POST['recipient_cellphone']);
		$insert_params['tax_rate'] 							= $order_cfg['vat'];
		$insert_params['pg'] 								= $pgCompany;
		$insert_params['pg_currency'] 						= $pg_currency;
		$insert_params['pg_currency_exchange_rate']			= $pg_currency_exchange;				//결제당시 PG환율
		$insert_params['krw_exchange_rate']					= $params['krw_exchange_rate'];	//통화별 환율정보
		$insert_params['label']								= $this->input->post('label');		// 주문 라벨

		$insert_params['nation_key']						= $_POST['address_nation_key'];
		if($_POST['international'] == '1'){
			$insert_params['international'] 				= 'international';
			$insert_params['shipping_method_international']	= $_POST['shipping_method_international'];
			$insert_params['region'] 						= $_POST['region'];
			$insert_params['international_address'] 		= $_POST['international_address'];
			$insert_params['international_town_city'] 		= $_POST['international_town_city'];
			$insert_params['international_county'] 			= $_POST['international_county'];
			$insert_params['international_postcode'] 		= $_POST['international_postcode'];
			$insert_params['international_country'] 		= $_POST['international_country'];
			$insert_params['international_cost']			= get_cutting_price($shipping_cost);

			// 주문서에 기본 배송정책 저장
			$insert_params['delivery_cost'] 				= get_cutting_price($shipping_cost);
		}else if($_POST['international'] == '0'){
			$insert_params['international'] 				= 'domestic';
			$insert_params['shipping_method'] 				= $_POST['shipping_method'];
			$insert_params['recipient_zipcode']				= implode('-',$_POST['recipient_zipcode']);
			$insert_params['recipient_zipcode']				= $_POST['recipient_new_zipcode'];
			$insert_params['recipient_address_type'] 		= ( $_POST['recipient_address_type'] == 'street' && trim($_POST['recipient_address_street']) )?$_POST['recipient_address_type']:"zibun";
			$insert_params['recipient_address'] 			= $_POST['recipient_address'];
			$insert_params['recipient_address_street']		= $_POST['recipient_address_street'];
			$insert_params['recipient_address_detail']		= $_POST['recipient_address_detail'];
			$insert_params['shipping_cost']					= get_cutting_price($shipping_cost);

			// 굿스플로용 배송주소 추출 :: 2017-03-08 lwh
			if($insert_params['recipient_address_type'] == 'street'){
				$this->load->helper('zipcode');
				$ZIP_DB		= get_zipcode_db();
				$zip_sql	= "select distinct DONG from zipcode_street_new WHERE ZIPCODE = '" . $insert_params['recipient_zipcode'] . "'";
				$zip_query	= $ZIP_DB->query($zip_sql);
				$zip_result	= $zip_query->result_array();
				$address	= $insert_params['recipient_address_street'];
				$dong		= $zip_result[0]['DONG'];
				if	(preg_match('/\)$/', $address)){
					$address	= preg_replace('/([^\(]*\()([^\)]*\))/', '$1' . $dong . ', $2', $address);
				}else{
					$address	.= ' (' . $dong . ')';
				}

				$insert_params['recipient_address_street_gf'] = $address;
			}

			//티켓상품 받는사람이메일추가@2013-10-22
			$insert_params['recipient_email'] 				= $_POST['recipient_email'];
		}

		// 상품별 메세지 입력 예외처리 추가 :: 2016-09-02 lwh
		$insert_params['memo'] = "";
		$_POST['memo'] = preg_replace('/(이 곳은 집배원님이 보시는 메시지란입니다|배송 메시지를 입력하세요\.)/','',$_POST['memo']);
		if($_POST['each_msg'] == 'Y'){
			$insert_params['each_msg_yn'] = 'Y';
			$insert_params['memo'] = $_POST['each_memo'][0]; // 구버전 예외처리
		}else{
			$insert_params['each_msg_yn'] = 'N';
			$insert_params['memo'] = $_POST['memo'];
		}

		$insert_params['download_seq'] 					= $_POST['download_seq'];
		$insert_params['coupon_sale'] 					= $_POST['coupon_sale'];
		$insert_params['typereceipt'] 					= $_POST['typereceipt'];
		$insert_params['regist_date'] 					= date('Y-m-d H:i:s',time());
		$insert_params['session_id'] 					= $session_id;

		if($_POST["adminOrder"] == "admin"){
			$insert_params['admin_order'] 				= $this->session->userdata["manager"]["manager_id"];
			//#29822 2019-03-04 ycg 관리자 메모 테이블 분리로 메모 데이터 별도 처리
			if($_POST['admin_memo']){
				//관리자 메모 내용 할당
				$this->validation->set_rules('admin_memo','관리자메모','trim|xss_clean');
				$admin_memo = $this->input->post('admin_memo');
				$ip = $this->input->server('REMOTE_ADDR');
				$aOrder_memo = array(
				 'order_seq'=>$insert_params['order_seq'],				//주문 번호
				 'regist_date'=>date("Y-m-d H:i:s"),					//등록일
				 'mname'=>$this->session->userdata["manager"]["mname"],	//관리자 이름
				 'manager_id'=>$insert_params['admin_order'],			//관리자 아이디
				 'admin_memo'=>$admin_memo,								//가공한 관리자 메모
				 'ip'=> $ip												//등록하는 계정의 IP주소
				);
				//연결된 주문 번호가 있는 경우에만 메모가 저장되도록 처리
				if($insert_params['order_seq']){
					$this->db->where('order_seq', $iOrder_seq);
					$this->db->insert('fm_order_memo', $aOrder_memo);
				}
			}

			// 관리자 주문시 유입경로 정보 불필요로 초기화 
			unset($_COOKIE['marketplace']);
			unset($_COOKIE['shopReferer']);
			unset($_COOKIE['refererDomain']);
		}

		//판매환경
		$insert_params['sitetype'] = get_sitetype();
		if($_POST['overwrite_sitetype']){
			$insert_params['sitetype']				= $_POST['overwrite_sitetype'];
		}

		//스킨환경
		if($this->mobileMode) {//mobile 1
			$insert_params['skintype'] 				= 'M';
		}elseif($this->fammerceMode) {//fammerce 3
			$insert_params['skintype'] 				= 'F';
		}elseif($this->storemobileMode) {//mobile 2
			$insert_params['skintype'] 				= 'OFF_M';
		}elseif($this->storefammerceMode) {//fammerce 4
			$insert_params['skintype'] 				= 'OFF_F';
		}elseif($this->storeMode) {//pc 5
			$insert_params['skintype'] 				= 'OFF_P';
		}else{//pc 6
			$insert_params['skintype'] 				= 'P';
		}

		$insert_params['marketplace'] 				= $_COOKIE['marketplace'];//유입매체
		## 유입경로
		$refererDomain = $_COOKIE['refererDomain'];
		$insert_params['referer']					= $_COOKIE['shopReferer'];
		$insert_params['referer_domain']			= $refererDomain;

		## 고객리마인드서비스 유입경로 2014-07-31, 유입로그는 결제확인 시 저장
		if($_COOKIE["curation"]){
			$curation_tmp		= explode("^",$_COOKIE["curation"]);
			$curation_inflow	= $curation_tmp[1];
			$curation_seq		= $curation_tmp[2];
			$insert_params['curation_inflow']	= $curation_inflow;		//고객리마인드 유입구분
			$insert_params['curation_seq']		= $curation_seq;		//고객리마인드 유입로그번호
		}

		## sns 로그인 계정(2014-07-02)
		if($_POST['adminOrder'] != 'admin'){
			$snslogn = $this->session->userdata('snslogn');
			if($snslogn) $insert_params['sns_rute'] 	= $snslogn;
		}

		/*
		 * 개인 결제 주문일 경우 관리자 메모가 있을 때 저장되게 함 leewh 2014-12-01
		 * 상단에서 분기 처리가 되어 있어 기능 중복으로 소스 주석 처리 ycg 2019-03-04
		 */
		/*if (!empty($_POST['person_seq']) && !empty($_POST['admin_memo'])) {
			$insert_params['admin_memo'] 			= $_POST['admin_memo'];
		}*/

		// 해외배송상품 개인통관번호
		$insert_params['clearance_unique_personal_code'] = $_POST['clearance_unique_personal_code'];

		$insert_params['ip']		= $_SERVER["REMOTE_ADDR"];

		// 주문서쿠폰
		$insert_params['ordersheet_seq']		= $params["ordersheet_seq"];
		$insert_params['ordersheet_sale']		= $params["ordersheet_sale"];
		$insert_params['ordersheet_sale_krw']	= $params["ordersheet_sale_krw"];

		// 톡구매 주문
		$insert_params['talkbuy_order_id']		= $params["talkbuy_order_id"];
		$insert_params['talkbuy_order_date']	= $params["talkbuy_order_date"];
		$insert_params['talkbuy_paid_date']		= $params["talkbuy_paid_date"];

		$this->db->insert('fm_order', $insert_params);

		return $order_seq;
	}

	// 주문 총주문수량 / 총상품종류 업데이트 leewh 2014-08-01
	public function update_order_total_info($order_seq) {
		$query = "
		UPDATE fm_order O
		INNER JOIN
		(
			SELECT ord.order_seq,
			(SELECT count(item_seq) FROM fm_order_item WHERE order_seq=ord.order_seq) item_cnt,
			(SELECT IFNULL(SUM(ea), 0) FROM fm_order_item_option WHERE order_seq=ord.order_seq) opt_ea,
			(SELECT IFNULL(SUM(ea), 0) FROM fm_order_item_suboption WHERE order_seq=ord.order_seq) sub_ea
			FROM
			fm_order ord
			WHERE ord.order_seq=?
		) T ON O.order_seq = T.order_seq
		SET O.total_ea = T.opt_ea+T.sub_ea, O.total_type = T.item_cnt WHERE O.order_seq=?";

		$this->db->query($query,array($order_seq,$order_seq));
	}

	// 주문 마일리지 상태 변경
	public function set_emoney_use($order_seq,$status){
		$this->db->where('order_seq',$order_seq);
		$this->db->update('fm_order',array('emoney_use'=>$status));
	}
	public function set_cash_use($order_seq,$status){
		$this->db->where('order_seq',$order_seq);
		$this->db->update('fm_order',array('cash_use'=>$status));
	}

	// 주문서 정보 가져오기
	public function get_order($order_seq, $wheres =array(), $get_all=false, $list=false){
		$sql = "
		SELECT
			ord.*,
			na.nation_name,
			IF(rg.referer_group_no>0, rg.referer_group_name, IF(LENGTH(ord.referer)>0,'기타','직접입력')) as referer_name
		FROM
			fm_order as ord
			LEFT JOIN fm_referer_group rg ON ord.referer_domain = rg.referer_group_url
			LEFT JOIN fm_shipping_nation na ON ord.nation_key = na.nation_key
		";
		if($order_seq){
			$sql .= " WHERE ord.order_seq=? ";
			$binds[]	= $order_seq;
		}elseif($get_all){
			$sql .= " WHERE 1=1 ";
		}else{
			// 검색 조건 없음
			// 기존 order_seq를 필수로 받았기 때문에 order_seq가 없을때 전체 값을 반환하면 안 되도록 처리. by hed
			$sql .= " WHERE 1=0 ";
		}

		if($wheres) {
			foreach($wheres as $k=>$v){
				if(is_array($v)){
					$sql .= " and {$k} in ? ";
					$binds[] = $v;
				}else{
					$sql .= " and {$k} = ? ";
					$binds[] = $v;
				}
			}
		}


		$query = $this->db->query($sql,$binds);
		// fm_order 테이블의 목록을 구하는 함수가 없어서 변경함 by hed
		if($list){
			$orders = $query->result_array($query);
			foreach($orders as &$row){
				$this->extend_info_order($row);
			}
		}else{
			list($orders) = $query->result_array($query);
			$this->extend_info_order($orders);
		}

		return $orders;
	}


	// 주문서 정보 가져오기
	public function get_last_order_by_top_orign($order_seq, $wheres =array(), $get_all=false, $list=false){
		$sql = "
		SELECT
			ord.*,
			na.nation_name,
			IF(rg.referer_group_no>0, rg.referer_group_name, IF(LENGTH(ord.referer)>0,'기타','직접입력')) as referer_name
		FROM
			fm_order as ord
			LEFT JOIN fm_referer_group rg ON ord.referer_domain = rg.referer_group_url
			LEFT JOIN fm_shipping_nation na ON ord.nation_key = na.nation_key
		";
		if($order_seq){
			$sql .= " WHERE ord.order_seq=? ";
			$binds[]	= $order_seq;
		}elseif($get_all){
			$sql .= " WHERE 1=1 ";
		}else{
			$sql .= " WHERE 1=0 ";
		}

		if($wheres) {
			foreach($wheres as $k=>$v){
				if(is_array($v)){
					$sql .= " and {$k} in ? ";
					$binds[] = $v;
				}else{
					$sql .= " and {$k} = ? ";
					$binds[] = $v;
				}
			}
		}

		$sql .= "ORDER BY order_seq DESC";

		$query = $this->db->query($sql,$binds);
		if($list){
			$orders = $query->result_array($query);
			foreach($orders as &$row){
				$this->extend_info_order($row);
			}
		}else{
			list($orders) = $query->result_array($query);
			$this->extend_info_order($orders);
		}

		return $orders;
	}

	public function extend_info_order(&$orders){
		if(!$orders['order_seq']) {
			return;
		}
		if(!$this->arr_payment)	$this->arr_payment = config_load('payment');
		$orders['mpayment'] = $this->arr_payment[$orders['payment']];

		## 국가명 가공 :: 2017-05-23 lwh
		preg_match('/^([^\s\(]+)(\s*\()([^\)]+)(\))$/', trim($orders['nation_name']), $matches);
		$orders['nation_name_kor'] = $matches[1];
		$orders['nation_name_eng'] = $matches[3];

		## 전화번호 예외처리 :: 2017-05-23 lwh
		if($orders['order_phone'] == '--')		$orders['order_phone']		= '';
		if($orders['recipient_phone'] == '--')	$orders['recipient_phone']	= '';

		## 주문상태 :: 2017-05-23 lwh
		if(!$this->arr_step)	$this->arr_step = config_load('step');
		$orders['step_info'] = $this->arr_step[$orders['step']];

		## 가격 검증
		if	($orders['pg_currency'] == 'KRW')
			$orders['settleprice']	= floor($orders['settleprice']);

		if($orders['pg']=='kakaopay'){
			$orders['mpayment']	= '카카오페이';
			$orders['pg_kind']	= 'kakaopay';
		}elseif($orders['pg']=='paypal'){
			$orders['mpayment'] = '페이팔';
		}elseif($orders['pg']=='payco'){
			$orders['mpayment']	= '페이코';
			$orders['pg_kind']	= 'payco';
		}elseif($orders['pg']=='naverpayment'){
			$orders['mpayment']	= '네이버페이';
			$orders['pg_kind']	= 'naverpayment';
		}
	}

	// 주문서 상품 정보 가져오기
	public function get_item($order_seq)
	{
		$selectedFields = [
			'oi.*',
			'p.provider_name',
			'p.provider_id',
			'g.cancel_type',
			'g.master_goods_seq',
		];
		$this->db->select($selectedFields)
			->from('fm_order_item oi')
			->join ('fm_goods g', 'g.goods_seq = oi.goods_seq', 'left')
			->join ('fm_provider p', 'p.provider_seq = oi.provider_seq', 'left')
			->where('oi.order_seq', $order_seq)
			->order_by("(CASE WHEN oi.goods_type =  'goods' THEN 0 ELSE 99 END)", 'ASC')
			->order_by('oi.provider_seq', 'ASC')
			->order_by('oi.shipping_seq', 'ASC')
			->order_by('oi.item_seq', 'ASC');

		if (defined('__SELLERADMIN__') === true) {
			$this->db->where('oi.provider_seq', $this->providerInfo['provider_seq']);
		}

		//
		$query = $this->db->get();

		//
		$items = [];
		foreach ($query->result_array() as $data) {
			//주문상품의 이미지가 없는경우 실제상품의 이미지를 가져옴
			if (!$data['image'] || !is_file(ROOTPATH.$data['image'])) {
				$data['image'] = viewImg($data['goods_seq'],'thumbCart');
			}
			$items[] = $data;
		}

		//
		return $items;
	}

	// 주문서 상품 정보 가져오기
	public function get_gift_item($order_seq){
		$query = "
		SELECT count(*) as cnt
		FROM fm_order_item A LEFT JOIN fm_goods B ON A.goods_seq = B.goods_seq
		WHERE order_seq=? and B.goods_type <> 'gift'";
		$query = $this->db->query($query,array($order_seq));

		list($result) = $query->result_array($query);

		return $result['cnt'];
	}


	// 주문서 상품 정보 가져오기 -> 배송사별
	public function get_item_providerlist($order_seq){
		if( defined('__SELLERADMIN__') === true ){//입점사인경우 (해당 입점상품이고 입점사가 배송인경우에)
			$query = "
			SELECT
			sp.shipping_seq,
			sp.order_seq,
			sp.provider_seq,
			b.

			FROM fm_order_shipping as sp
			LEFT JOIN fm_order_item as a on a.order_seq = sp.order_seq
			LEFT JOIN fm_provider as b on sp.provider_seq = b.provider_seq
			WHERE a.order_seq=? and  a.provider_seq=? and  sp.provider_seq=?
			GROUP BY sp.shipping_seq asc
			";
			$query = $this->db->query($query,array($order_seq, $this->providerInfo['provider_seq'], $this->providerInfo['provider_seq']));

		}else{//배송업체별가져오기
			$query = "
			SELECT
			sp.shipping_seq,
			sp.order_seq,
			sp.provider_seq,
			b.provider_name
			FROM fm_order_shipping as sp
			LEFT JOIN fm_order_item as a on a.order_seq = sp.order_seq
			LEFT JOIN fm_provider as b on sp.provider_seq = b.provider_seq
			WHERE a.order_seq=?
			GROUP BY sp.shipping_seq,sp.shipping_seq asc
			";
			$query = $this->db->query($query,array($order_seq));
		}
		foreach($query->result_array() as $data){
			$items[] = $data;
		}
		return $query->result_array();
	}

	// 주문서 상품 정보 가져오기 -> 해당배송사만
	public function get_item_provider($order_seq, $provider_seq, $shipping_seq, $goods_kind = ''){

		if	($goods_kind){
			$addWhere	= " and a.goods_kind = '".$goods_kind."' ";
		}

		$query = "
		SELECT
		a.*,
		sp.shipping_method,
		b.provider_name
		FROM fm_order_item as a
		LEFT JOIN fm_order_shipping as sp on a.order_seq = sp.order_seq and  a.shipping_seq = sp.shipping_seq
		LEFT JOIN fm_provider as b on a.provider_seq = b.provider_seq
		WHERE a.order_seq=? and sp.provider_seq=?  and  sp.shipping_seq=?
		".$addWhere."
		ORDER BY sp.provider_seq asc
		";
		$query = $this->db->query($query,array($order_seq, $provider_seq, $shipping_seq));
		foreach($query->result_array() as $data){
			$items[] = $data;
		}
		return $items;
	}

	// 주문서 상품 정보 가져오기 -> 해당상품정보만
	public function get_item_provider_seq($item_seq){
		if( defined('__SELLERADMIN__') === true ){//입점사인경우
			$query = "
			SELECT
			a.*,
			b.provider_name
			FROM fm_order_item as a
			LEFT JOIN fm_provider as b on a.provider_seq = b.provider_seq
			WHERE a.item_seq =? and  a.provider_seq=?
			ORDER BY a.provider_seq asc
			";
			$query = $this->db->query($query,array($item_seq, $this->providerInfo['provider_seq']));
		}else{
			$query = "
			SELECT
			a.*,
			b.provider_name
			FROM fm_order_item as a
			LEFT JOIN fm_provider as b on a.provider_seq = b.provider_seq
			WHERE a.item_seq =?
			ORDER BY a.provider_seq asc
			";
			$query = $this->db->query($query,array($item_seq));
		}
		return $query->row_array();
	}

	public function get_option_for_item($item_seq, $where = array()){
		$query = "
		SELECT
			a.*,
			b.provider_seq as shipping_provider_seq,
			b.shipping_group,
			b.shipping_method
		FROM fm_order_item_option a
		left join fm_order_shipping b on a.shipping_seq=b.shipping_seq
		WHERE a.item_seq=?";

		if($where) $query .= " and " . implode(" and ",$where);

		$query = $this->db->query($query,array($item_seq));

		foreach($query->result_array() as $data){
			$items[] = $data;
		}

		return $items;
	}

	public function get_shipping_for_option($item_option_seq){
		$query = "select a.* from fm_order_shipping a where a.shipping_seq=(select shipping_seq from fm_order_item_option where item_option_seq=?)";
		$query = $this->db->query($query,array($item_option_seq));
		$row = $query->row_array();
		return $row;
	}

	public function get_suboption_for_item($item_seq, $where = array()){
		$query = "
		SELECT
			a.*,
			c.provider_seq as shipping_provider_seq,
			c.shipping_group,
			c.shipping_method
		FROM fm_order_item_suboption as a
		left join fm_order_item as b on a.item_seq=b.item_seq
		left join fm_order_shipping c on b.shipping_seq=c.shipping_seq
		WHERE a.item_seq=?";

		if($where) $query .= " and " . implode(" and ",$where);

		$query = $this->db->query($query,array($item_seq));
		foreach($query->result_array() as $data){
			$items[] = $data;
		}
		return $items;
	}

	public function get_option_for_order($order_seq, $where = array()){
		$query = "
		SELECT
		*
		FROM fm_order_item_option
		WHERE order_seq=?";

		if($where) $query .= " and " . implode(" and ",$where);

		$query = $this->db->query($query,array($order_seq));
		foreach($query->result_array() as $data){
			$items[] = $data;
		}

		return $items;
	}

	public function get_suboption_for_order($order_seq, $where = array()){
		$query = "
		SELECT
		*
		FROM fm_order_item_suboption
		WHERE order_seq=?";

		if($where) $query .= " and " . implode(" and ",$where);

		$query = $this->db->query($query,array($order_seq));

		foreach($query->result_array() as $data){
			$items[] = $data;
		}

		return $items;
	}

	// 배송정보로 상품 정보 가져오기
	public function get_item_for_shipping($shipping_seq, $where = array()){
		$query = "
		SELECT
		*
		FROM fm_order_item
		WHERE shipping_seq=?";

		if($where) $query .= " and " . implode(" and ",$where);

		$query = $this->db->query($query,array($shipping_seq));

		foreach($query->result_array() as $data){
			$items[$data['item_seq']] = $data;
		}

		return $items;
	}

	// 배송정보로 옵션 정보 가져오기
	public function get_option_for_shipping($shipping_seq, $where = array()){
		$query = "
		SELECT
		*
		FROM fm_order_item_option
		WHERE shipping_seq=?";

		if($where) $query .= " and " . implode(" and ",$where);

		$query = $this->db->query($query,array($shipping_seq));

		foreach($query->result_array() as $data){
			$items[$data['item_option_seq']] = $data;
		}

		return $items;
	}

	public function get_input_for_item($item_seq){
		$query = "
		SELECT
		*, title as subinputtitle, value as subinputoption
		FROM fm_order_item_input
		WHERE item_seq=?";
		$query = $this->db->query($query,array($item_seq));
		foreach($query->result_array() as $data){
			$items[] = $data;
		}
		return $items;
	}

	public function get_suboption_for_option($item_seq, $option_seq, $where = array()){
		$query = "
		SELECT
		*
		FROM fm_order_item_suboption
		WHERE item_seq=? and item_option_seq=?";

		if($where) $query .= " and " . implode(" and ",$where);

		$query = $this->db->query($query,array($item_seq, $option_seq));
		foreach($query->result_array() as $data){
			$items[$data['item_suboption_seq']] = $data;
		}
		return $items;
	}

	public function get_input_for_option($item_seq, $option_seq){
		$query = "
		SELECT
		*, title as subinputtitle,
		value as subinputoption
		FROM fm_order_item_input
		WHERE item_seq=? and item_option_seq=?";
		$query = $this->db->query($query,array($item_seq, $option_seq));
		foreach($query->result_array() as $data){
			$items[] = $data;
		}
		return $items;
	}

	// 주문 상품과 주문서의 주문 상태 업데이트
	public function set_step($order_seq,$step,$arr=''){

		// 주문상태 체크
		if(!in_array($step,$this->all_step)) return false;

		$data['step']	= $step;
		$event_stats	= array();		// 단독 이벤트 통계

		if($step=='25'){ // 입금확인
			$data['deposit_yn'] = 'y';
			$data['deposit_date'] = date('Y-m-d H:i:s');

			/* 상품분석 수집 */
			$this->load->model('goodslog');
			$query = $this->db->query("SELECT a.goods_seq,
			ifnull((select sum(price) from fm_order_item_option where order_seq=a.order_seq and item_seq=a.item_seq), 0) as option_price_sum,
			ifnull((select sum(price) from fm_order_item_suboption where order_seq=a.order_seq and item_seq=a.item_seq), 0) as suboption_price_sum,
			a.event_seq  FROM `fm_order_item` as a WHERE a.order_seq=?",$order_seq);
			if($query) $items = $query->result_array();
			if( ( $_SERVER['SHELL'] || php_sapi_name() == 'cli' ) && !$items ) {//items null
				return false;
			}

			foreach($items as $item){
				$this->goodslog->add('deposit',$item['goods_seq'],1);
				$this->goodslog->add('deposit_price',$item['goods_seq'],($item['option_price_sum']+$item['suboption_price_sum']));
				if($item['event_seq']) $event_stats[] = $item['event_seq'];
			}
		}


		$this->db->where('order_seq',$order_seq);
		if($arr) $data = array_merge($data,$arr);
		$this->db->update('fm_order',$data);

		$query = "update fm_order_item_option set step=? where order_seq=? and ea!=step85";
		$this->db->query($query,array($step,$order_seq));
		$query = "update fm_order_item_suboption set step=? where order_seq=? and ea!=step85";
		$this->db->query($query,array($step,$order_seq));

		if($step=='25'){ // 입금확인 유효기간 설정
			$this->set_order_valid_date($order_seq,$data['deposit_date']);

			/**
			// 티켓상품 자동 출고처리 소스 분리 helper('order');ticket_payexport_ck() @2017-08-16
			$this->load->model('exportmodel');
			$this->exportmodel->coupon_payexport($order_seq);
			**/

			## 단독이벤트 판매수량/판매금액/판매건수 update @2015-09-07 pjm
			if($event_stats){
				$this->load->model("eventmodel");
				$this->eventmodel->event_order_stat($event_stats);
			}

			// 물류관리 자동발주
			if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
			if	($this->scm_cfg['use'] == 'Y'){
				$this->load->model('scmmodel');
				$this->scmmodel->auto_order_to_order($order_seq);
			}

			/**
			* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 시작
			* 정산개선 - 미정산매출데이타 처리
			* @
			**/
			if(!$this->accountall)		$this->load->helper('accountall');
			if(!$this->accountallmodel)	$this->load->model('accountallmodel');
			if(!$this->providermodel)	$this->load->model('providermodel');
			$this->accountallmodel->insert_calculate_sales_order_deposit($order_seq);

			/**
			* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 끝
			* 정산개선 - 미정산매출데이타 처리
			* @
			**/
		}
	}

	// 유효기간 설정
	/**
	* order_seq : 주문번호
	* deposit_date 결제일시 (date('Y-m-d H:i:s');)
	**/
	public function set_order_valid_date($order_seq,$deposit_date) {
		$deposit_datear = explode("-",$deposit_date);
		//결제확인시
		$result_option = $this->get_item_option($order_seq);
		$result_suboption = $this->get_item_suboption($order_seq);
		if($result_option){
			foreach($result_option as $data_option) {

				$optionnewtype = explode(",",$data_option['newtype']);
				if( in_array("date",$optionnewtype) ) {
					$social_start_date =$social_end_date_tmp = date("Y-m-d",strtotime($data_option['codedate']));

				}elseif( in_array("dayinput",$optionnewtype)) {
					$social_start_date					= date("Y-m-d",strtotime($data_option['sdayinput']));
					$social_end_date_tmp			= date("Y-m-d",strtotime($data_option['fdayinput']));

				}elseif( in_array("dayauto",$optionnewtype) ) {
					$depositmonth = $deposit_datear[1];
					$depositday = $deposit_datear[1];
					$sday = $data_option['sdayauto'];
					$fday = $data_option['fdayauto'];
					if( $data_option['dayauto_type'] == 'month' ) {
						$social_start_date				= ($sday>0)?date("Y-m-d", strtotime($deposit_datear[0]."-".$depositmonth."-".$sday)):date("Y-m-d", strtotime($deposit_datear[0]."-".$depositmonth."-01"));
						$social_end_date_tmp		= ($fday>0)?date("Y-m-d",strtotime('+'.$fday.' day '.$social_start_date)):date("Y-m-d",strtotime($social_start_date));
					}elseif( $data_option['dayauto_type'] == 'day' ) {
						$social_start_date				= ($sday>0)?date("Y-m-d",strtotime('+'.$sday.' day '.$deposit_date)):date("Y-m-d",strtotime($deposit_date));
						$social_end_date_tmp		= ($fday>0)?date("Y-m-d",strtotime('+'.$fday.' day '.$social_start_date)):date("Y-m-d",strtotime($social_start_date));
					}elseif( $data_option['dayauto_type'] == 'next' ) {
						$social_start_date				= ($sday>0)?date("Y-m-d",strtotime(date("Y-m",strtotime('+1 month '.$deposit_date))."-".$sday)):date("Y-m-d",strtotime(date("Y-m",strtotime('+1 month '.$deposit_date))."-01"));
						$social_end_date_tmp		= ($fday>0)?date("Y-m-d",strtotime('+'.$fday.' day '.$social_start_date)):date("Y-m-d",strtotime($social_start_date));
					}
				}

				if( $data_option['dayauto_day'] == 'end' ){//끝나는 날짜의 말일
					$social_end_date = date("Y-m-t", strtotime($social_end_date_tmp));
				}else{
					$social_end_date = date("Y-m-d", strtotime($social_end_date_tmp));
				}

				$this->db->where('item_option_seq',$data_option['item_option_seq']);
				$this->db->where('order_seq',$order_seq);
				$this->db->update('fm_order_item_option',array('social_start_date'=>$social_start_date,'social_end_date'=>$social_end_date));
			}
		}

		if($result_suboption){
			foreach($result_suboption as $data_suboption) {

				if( ($data_suboption['newtype'] == "date") ) {
					$social_start_date =$social_end_date_tmp = date("Y-m-d",strtotime($data_suboption['codedate']));

				}elseif( ($data_suboption['newtype'] == "dayinput")) {
					$social_start_date				= date("Y-m-d",strtotime($data_suboption['sdayinput']));
					$social_end_date_tmp		= date("Y-m-d",strtotime($data_suboption['fdayinput']));

				}elseif( ($data_suboption['newtype'] == "dayauto") ) {
					$depositmonth = $deposit_datear[1];
					$depositday = $deposit_datear[1];
					$sday = $data_suboption['sdayauto'];//day
					$fday = $data_suboption['fdayauto'];//day
					if( $data_suboption['dayauto_type'] == 'month' ) {
						$social_start_date			= ($sday>0)?date("Y-m-d",strtotime($deposit_datear[0]."-".$depositmonth."-".$sday)):date("Y-m-d",strtotime($deposit_datear[0]."-".$depositmonth."-01"));
						$social_end_date_tmp	= ($fday>0)?date("Y-m-d",strtotime('+'.$fday.' day '.$social_start_date)):date("Y-m-d",strtotime($social_start_date));
					}elseif( $data_suboption['dayauto_type'] == 'day' ) {
						$social_start_date			= ($sday>0)?date("Y-m-d",strtotime('+'.$sday.' day '.$deposit_date)):date("Y-m-d",strtotime($deposit_date));
						$social_end_date_tmp	= ($fday>0)?date("Y-m-d",strtotime('+'.$fday.' day '.$social_start_date)):date("Y-m-d",strtotime($social_start_date));
					}elseif( $data_suboption['dayauto_type'] == 'next' ) {
						$social_start_date			= ($sday>0)?date("Y-m-d",strtotime(date("Y-m",strtotime('+1 month '.$deposit_date))."-".$sday)):date("Y-m-d",strtotime(date("Y-m",strtotime('+1 month '.$deposit_date))."-01"));
						$social_end_date_tmp	= ($fday>0)?date("Y-m-d",strtotime('+'.$fday.' day '.$social_start_date)):date("Y-m-d",strtotime($social_start_date));
					}
				}

				if( $data_suboption['dayauto_day'] == 'end' ){//끝나는 날짜의 말일
					$social_end_date = date("Y-m-t", strtotime($social_end_date_tmp));
				}else{
					$social_end_date = date("Y-m-d", strtotime($social_end_date_tmp));
				}
				$this->db->where('item_suboption_seq',$data_suboption['item_suboption_seq']);
				$this->db->where('item_option_seq',$data_suboption['item_option_seq']);
				$this->db->where('order_seq',$order_seq);
				$this->db->update('fm_order_item_suboption',array('social_start_date'=>$social_start_date,'social_end_date'=>$social_end_date));
			}
		}
	}

	// 주문 상품과 주문서의 주문 상태 거꾸로 가기
	public function set_reverse_step($order_seq,$step,$arr='',$mode='normal'){
		$data['step'] = $step;
		$this->load->model('goodsmodel');

		// 출고량 업데이트를 위한 변수선언
		$r_reservation_goods_seq	= array();
		$data_order					= $this->get_order($order_seq);

		if($step=='15' && $mode=='normal'){
			/* 상품분석 수집 */
			$this->load->model('goodslog');
			$query = $this->db->query("SELECT a.goods_seq, (select sum(price) from fm_order_item_option where order_seq=a.order_seq and item_seq=a.item_seq) as option_price_sum, (select sum(price) from fm_order_item_suboption where order_seq=a.order_seq and item_seq=a.item_seq) as suboption_price_sum  FROM `fm_order_item` as a WHERE a.order_seq=?",$order_seq);
			$items = $query->result_array();
			foreach($items as $item){
				$this->goodslog->add('deposit',$item['goods_seq'],-1);
				$this->goodslog->add('deposit_price',$item['goods_seq'],-($item['option_price_sum']+$item['suboption_price_sum']));
			}

			$data['deposit_yn'] = 'n';
			$data['deposit_date'] = '';

			// 해당 주문 상품의 출고예약량 업데이트
			$result_option = $this->get_item_option($order_seq);
	   		$result_suboption = $this->get_item_suboption($order_seq);
			if($result_option){
				foreach($result_option as $data_option){
					// 출고량 업데이트를 위한 변수정의
					if(!in_array($data_option['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $data_option['goods_seq'];
					}
				}
			}
			if($result_suboption){
				foreach($result_suboption as $data_suboption){
					// 출고량 업데이트를 위한 변수정의
					if(!in_array($data_suboption['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
					}

				}
			}

			// 매칭된 입금내역 연결 해제
			$this->load->model('usedmodel');
			$chks = $this->usedmodel->autodeposit_check();
			if	($chks['chk'] == 'Y'){
				$sql	= "UPDATE fm_order SET autodeposit_key = '', autodeposit_type = '' where order_seq = ? ";
				$this->db->query($sql, array($order_seq));
				$bank	= $this->usedmodel->get_bank_data(array('order_seq' => $order_seq));
				if	($bank) foreach($bank as $k => $bdata){
					$this->usedmodel->set_marking_autodeposit($bdata['bkcode'], '');
				}
			}
		}else if($step=='15') { // 주문무효에서 주문접수로 변경시
			$orders = $this->ordermodel->get_order($order_seq);
			$options = $this->ordermodel->get_item_option($order_seq);

			if($orders['member_seq']){
				$this->load->model('membermodel');
				$emoney = 0;
				$orders['emoney'] = $orders['emoney'];
				if($orders['member_seq']){
					 $emoney = $this->membermodel->get_emoney($orders['member_seq']);
				}
				if($orders['emoney'] > 0 && $emoney < $orders['emoney']) return false;

				/* 마일리지 사용 */
				if($orders['emoney_use']=='return' && $orders['emoney'] > 0 )
				{
					$params = array(
						'gb'		=> 'minus',
						'type'		=> 'order',
						'emoney'	=> $orders['emoney'],
						'ordno'		=> $order_seq,
						'memo'		=> "[복원]주문접수({$order_seq})에 의한 마일리지 사용",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp254",$order_seq), // [복원]주문접수(%s)에 의한 마일리지 사용
					);
					$this->membermodel->emoney_insert($params, $orders['member_seq']);
					$this->ordermodel->set_emoney_use($order_seq,'use');
				}

				$cash = 0;
				$orders['cash'] = get_cutting_price($orders['cash']);
				if($orders['member_seq']){
					 $cash = get_member_money('cash', $orders['member_seq']);
				}
				if($orders['cash'] > 0 && $cash < $orders['cash']) return false;

				if($orders['cash_use']=='return'  && $orders['cash'] > 0 )
				{
					$params = array(
						'gb'		=> 'minus',
						'type'		=> 'order',
						'cash'		=> $orders['cash'],
						'ordno'		=> $order_seq,
						'memo'		=> "[복원]주문접수({$order_seq})에 의한 예치금 사용",
						'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp255",$order_seq),   // [복원]주문접수(%s)에 의한 예치금 사용
					);
					$this->membermodel->cash_insert($params, $orders['member_seq']);
					$this->ordermodel->set_cash_use($order_seq,'use');
				}
			}

			// 해당 주문 상품의 출고예약량 업데이트
			$result_option = $this->get_item_option($order_seq);
	   		$result_suboption = $this->get_item_suboption($order_seq);
			if($result_option){
				foreach($result_option as $data_option){
					// 출고량 업데이트를 위한 변수정의
					if(!in_array($data_option['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $data_option['goods_seq'];
					}
				}
			}
			if($result_suboption){
				foreach($result_suboption as $data_suboption){
					// 출고량 업데이트를 위한 변수정의
					if(!in_array($data_suboption['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
					}
				}
			}
		}

		$this->db->where('order_seq',$order_seq);
		if($arr) $data = array_merge($data,$arr);
		$this->db->update('fm_order',$data);

		// 서브쿼리가 index 타지 않아서 join update로 변경 :: 2019-02-14 lkh
		if($step == '25'){
			$query = "update fm_order_item_option foio left join fm_order_item foi
					on foi.item_seq = foio.item_seq
					set foio.step=?, foio.step35=0
					where foi.order_seq = ? and foio.ea!=foio.step85";
			$this->db->query($query,array($step,$order_seq));
			$query = "update fm_order_item_suboption fois left join fm_order_item foi
					on foi.item_seq = fois.item_seq
					set fois.step=?, fois.step35=0
					where foi.order_seq = ? and fois.ea!=fois.step85";
			$this->db->query($query,array($step,$order_seq));
		}else{
			$query = "update fm_order_item_option foio left join fm_order_item foi
					on foi.item_seq = foio.item_seq
					set foio.step=? where foi.order_seq =? and foio.ea!=foio.step85";
			$this->db->query($query,array($step,$order_seq));
			$query = "update fm_order_item_suboption fois left join fm_order_item foi
					on foi.item_seq = fois.item_seq
					set fois.step=?
					where foi.order_seq = ? and fois.ea!=fois.step85";
			$this->db->query($query,array($step,$order_seq));
		}

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}

		return true;
	}

	// 에누리 적용
	public function set_enuri($order_seq,$enuri,$oriEnuri){
		$data[] = $oriEnuri;
		$data[] = $enuri;
		$data[] = $enuri;
		$data[] = $order_seq;
		$query = "update fm_order set settleprice=settleprice+?-?, enuri=? where order_seq=?";
		$this->db->query($query,$data);
	}

	// 주문 삭제
	public function delete_order($order_seq){
		$this->db->query("delete from fm_order_item_input where item_seq in (select item_seq from fm_order_item where order_seq=?)",array($order_seq));
		$this->db->query("delete from fm_order_item_option where item_seq in (select item_seq from fm_order_item where order_seq=?)",array($order_seq));
		$this->db->query("delete from fm_order_item_suboption where item_seq in (select item_seq from fm_order_item where order_seq=?)",array($order_seq));
		$this->db->where('order_seq',$order_seq);
		$this->db->delete(array('fm_order_item','fm_order'));
	}

	// 주문 옵션 가져오기
	public function get_item_option($order_seq){
		$query = "select o.*,i.*, o.goods_code as opt_goods_code from fm_order_item i, fm_order_item_option o where i.item_seq=o.item_seq and i.order_seq=?";
		$query = $this->db->query($query,array($order_seq));
		foreach($query->result_array() as $data){
			$data['goods_code'] = $data['opt_goods_code'];
			$result[] = $data;
		}
		return $result;
	}
	// 주문 서브옵션 가져오기
	public function get_item_suboption($order_seq){
		$query = "select o.*,i.*, o.goods_code as opt_goods_code from fm_order_item i, fm_order_item_suboption o where i.item_seq=o.item_seq and i.order_seq=?";
		$query = $this->db->query($query,array($order_seq));
		foreach($query->result_array() as $data){
			$data['goods_code'] = $data['opt_goods_code'];
			$result[] = $data;
		}
		return $result;
	}

	/**
	* 주문시점의 티켓상품의 취소(환불)설정 가져오기
	* order_seq : 주문고유번호
	* item_seq : 주문상품 고유번호
	* social_start_date : 유효기간시작
	* social_end_date : 유효기간종료
	**/
	public function get_item_socialcp_cancel( $order_seq , $item_seq ) {
		$result = false;
		$sql = "select * from fm_order_socialcp_cancel where order_seq=? and item_seq=? ";
		$sql .= " order by socialcp_seq asc limit 1";//% 취소(환불) 설정 1개
		$query = $this->db->query($sql,array($order_seq, $item_seq));
		if( $query ) {
			foreach($query->result_array() as $data){
				$result[] = $data;
				$firstpercent = $data['socialcp_seq'];
			}
		}

		$sql = "select * from fm_order_socialcp_cancel where order_seq=? and item_seq=?  and socialcp_seq != ? ";
		$sql .= " order by socialcp_cancel_day desc";//% 공제!! 후 취소(환불) 설정
		$query = $this->db->query($sql,array($order_seq, $item_seq,$firstpercent));
		if( $query ) {
			foreach($query->result_array() as $data){
				$result[] = $data;
			}
		}
		return $result;
	}

	public function get_reservation_for_goods($cfg,$goods_seq){
		if( !$cfg ){
			$tmp = config_load('order');
			$cfg = $tmp['ableStockStep'];
		}
		$query = "
			select
				sum(o.ea) ea from fm_order_item i,fm_order_item_option o
			where
				i.item_seq = o.item_seq
				and i.goods_seq = ?
				and o.step >= ?
				and o.step <= '45'
			";
			$query = $this->db->query($query,array($goods_seq,$cfg));

			list($result) = $query->result_array($query);
			return $result['ea'];
	}

	// 출고 예약량 가져오기
	public function get_option_reservation($cfg,$goods_seq,$option1,$option2,$option3,$option4,$option5){
		if(!$this->goodsmodel) $this->load->model('goodsmodel');
		return $this->goodsmodel->get_option_reservation($cfg,$goods_seq,$option1,$option2,$option3,$option4,$option5);
	}

	// 출고 예약량 가져오기
	public function get_suboption_reservation($cfg,$goods_seq,$title,$suboption){
		if(!$this->goodsmodel) $this->load->model('goodsmodel');
		return $this->goodsmodel->get_suboption_reservation($cfg,$goods_seq,$title,$suboption);
	}

	// 주문에서 출고 예약량 가져오기
	public function get_option_reservation_from_order($cfg,$goods_seq,$option1,$option2,$option3,$option4,$option5){
		$query = "
			select
				sum(o.ea) ea from fm_order_item i,fm_order_item_option o
			where
				i.item_seq=o.item_seq
				and i.goods_seq=?
				and o.step >= ?
				and o.step <= '45'
				and o.option1=?
				and o.option2=?
				and o.option3=?
				and o.option4=?
				and o.option5=?
			";
			$query = $this->db->query($query,array(
				$goods_seq,
				$cfg,
				$option1,
				$option2,
				$option3,
				$option4,
				$option5
			));

			list($result) = $query->result_array($query);
			return $result['ea'];
	}

	// 주문에서 출고 예약량 가져오기
	public function get_suboption_reservation_from_order($cfg,$goods_seq,$title,$suboption){
		$query = "
			select
				sum(ea) ea from fm_order_item i,fm_order_item_suboption o
			where
				i.item_seq=o.item_seq
				and i.goods_seq=?
				and o.step >= ?
				and o.step <= '45'
				and o.title=?
				and o.suboption=?
			";
			$query = $this->db->query($query,array(
				$goods_seq,
				$cfg,
				$title,
				$suboption
			));

			list($result) = $query->result_array($query);
			return $result['ea'];
	}

	// 주문 로그 저장
	public function set_log($order_seq,$type,$actor,$title='',$detail='',$caccel_arr='',$export_code='',$add_info='',$refund_code='', $return_code=''){
		$tmp = array(
			'HTTP_HOST'=>$_SERVER['HTTP_HOST'],
			'REMOTE_ADDR'=>$_SERVER['REMOTE_ADDR'],
			'REQUEST_URI'=>$_SERVER['REQUEST_URI'],
			'HTTP_REFERER'=>$_SERVER['HTTP_REFERER'],
			'GET'=>$_GET,
			'POST'=>$_POST
		);

		$sys_memo = serialize($tmp);

		if(!$title) $title = "";

		$uristring = explode('/',$_SERVER['REQUEST_URI']);
		if( defined('__ADMIN__') === true ) {//관리자
			$sMtype	= 'm';//maneger
			$sMseq	= $this->managerInfo['manager_seq'];
		}elseif( defined('__SELLERADMIN__') === true ) {//입점관리자
			$sMtype	= 'p';//provider
			$sMseq	= $this->providerInfo['provider_seq'];
		}elseif( $_SERVER['SHELL'] || $uristring[1]== '_gabia' || $uristring[1]== '_batch' || php_sapi_name() == 'cli' ) {//자동
			$sMtype	= 's';//system
			$sMseq	= '';
		}else{
			if( $this->userInfo['member_seq'] ) {//회원
				$sMtype	= 'u';//user
				$sMseq	= $this->userInfo['member_seq'];
			}else{//비회원
				$sMtype	= 'n';//none
				$sMseq	= '';
			}
		}

		$detail .= chr(10).$sys_memo;
		$data = array();
		$data['order_seq']		= $order_seq;
		$data['type']			= $type;
		$data['actor']			= $actor;
		$data['mtype']			= $sMtype;
		$data['mseq']			= $sMseq;
		$data['title']			= $title;
		$data['detail']			= $detail;
		$data['add_info']		= $add_info;
		$data['refund_code']	= $refund_code;
		$data['return_code']	= $return_code;
		$data['regist_date']	= date('Y-m-d H:i:s');
		if($export_code){
			$data['export_code'] = $export_code;
		}
		if($caccel_arr) $data 	= array_merge($data,$caccel_arr);
		$this->db->insert('fm_order_log', $data);
	}

	// 주문 로그 가져오기
	public function get_log($order_seq,$type,$wheres=array(),$where_in=array()){
		if($type!='all') $this->db->where('type',		$type);
		$this->db->where('order_seq',	$order_seq);
		foreach($wheres as $k=>$v){
			$this->db->where($k,	$v);
		}
		foreach($where_in as $k => $v) {
			$this->db->where_in($k,	$v);
		}

		if( defined('__SELLERADMIN__') === true ) {//입점관리자
			$this->db->where("( (mtype = 'p' AND mseq = '".$this->providerInfo['provider_seq']."') or mtype != 'p' ) ");
		}

		$query = $this->db->get('fm_order_log');
		foreach ($query->result_array() as $data)
		{
		    $result[] = $data;
		}
		return $result;
	}

	public function get_delivery_method($orders){

		if($orders['shipping_method']){
			if(!$this->shippingmodel) $this->load->model('shippingmodel');
			$method_name = $this->shippingmodel->shipping_method_arr[$orders['shipping_method']];
		}else{
			$method_name = '택배 (선불 또는 착불)';
		}

		return $method_name;
	}

	// 출고수량 체크
	public function check_option_remind_ea($ea,$seq)
	{
		$query = "select ea,step35,step45,step55,step65,step75,step85 from fm_order_item_option where item_option_seq = ?";
		$query = $this->db->query($query,$seq);
		$row = $query->row_array();
		$remind = ((int) $row['ea'] + (int) $row['step35']) - ((int) $row['step45'] + (int) $row['step55'] + (int) $row['step65'] + (int) $row['step75'] + (int) $row['step85']) - $ea;
		if( $remind < 0 ) return false;
		return $remind;
	}

	// 출고수량 체크
	public function check_suboption_remind_ea($ea,$seq)
	{
		$query = "select ea,step35,step45,step55,step65,step75,step85 from fm_order_item_suboption where item_suboption_seq = ?";
		$query = $this->db->query($query,$seq);
		$row = $query->row_array();
		$remind = ((int) $row['ea'] + (int) $row['step35']) - ((int) $row['step45'] + (int) $row['step55'] + (int) $row['step65'] + (int) $row['step75'] + (int) $row['step85']) - $ea;
		if( $remind < 0 ) return false;
		return $remind;
	}

	// 취소,배송 상태 별 수량 업데이트
	public function set_step_ea($step,$ea,$seq,$mode='option'){
		if($mode == 'suboption'){
			$table = "fm_order_item_suboption";
			$where_field = "item_suboption_seq";
		}else{
			$table = "fm_order_item_option";
			$where_field = "item_option_seq";
		}
		$field = 'step'.$step;

		$query = "update ".$table." set ".$field." = ".$field." + ( ? ) where ".$where_field." = ?";
		$this->db->query($query,array($ea,$seq));

		// 기타 수량을 상품준비 수량으로 update
		if(in_array( $step,array(35,45,55,85) )){
			$query = "update ".$table." set  step35 =  ifnull(ea,0) - (ifnull(step85,0) + ifnull(step45,0) + ifnull(step55,0) + ifnull(step65,0) + ifnull(step75,0)) where ".$where_field." = ?";
			$this->db->query($query,array($seq));
		}
	}

	// 주문된 옵션,추가옵션 모두 상품준비로 변경
	public function set_step35_ea($order_seq, $option_seq = null, $option_type = null){

		//입점사
		if( defined('__SELLERADMIN__') === true && $this->providerInfo['provider_seq'] ){
			$addWhere	.= " and item_seq in ( select item_seq from fm_order_item where order_seq = '".$order_seq."' and provider_seq = '".$this->providerInfo['provider_seq']."' ) ";
		}

		// update 구분
		if	($order_seq){
			$update_order	= 'all';
			if		($option_seq > 0 && $option_type == 'option'){
				$update_order	= 'option';
				$addWhere		.= " and item_option_seq = '".$option_seq."' ";
			}elseif	($option_seq > 0 && $option_type == 'suboption'){
				$update_order	= 'suboption';
				$addWhere		.= " and item_suboption_seq = '".$option_seq."' ";
			}
		}

		// 옵션 update
		if	($update_order == 'all' || $update_order == 'option'){
			$query = "update fm_order_item_option set step35=ea-step85,step45=0,step55=0,step65=0,step75=0 where order_seq = ? and step = '25' ".$addWhere;
			$this->db->query($query,array($order_seq));
		}

		// 추가옵션 update
		if	($update_order == 'all' || $update_order == 'suboption'){
			$query = "update fm_order_item_suboption set step35=ea-step85,step45=0,step55=0,step65=0,step75=0 where order_seq = ? and step = '25' ".$addWhere;
			$this->db->query($query,array($order_seq));
		}
	}

	public function get_step($row){
		$arr	= array('25','35','45','55','65','75');
		$row['ex_ea'] = $row['ea'] - $row['step85'];
		$row['step25'] = $row['ex_ea'] - (int) $row['step35'] - (int) $row['step45'] - (int) $row['step55'] - (int) $row['step65'] - (int) $row['step75'];

		if($row['ex_ea'] == 0){
			$step = '85';
		}else if( $row['ex_ea'] > 0){
			foreach($arr as $ea_step){
				$field = 'step'.$ea_step;
				if($row[$field] > 0) $max_step = $ea_step;
			}
			$max_field = 'step'.$max_step;

			if( $row[$max_field] == $row['ex_ea'] ){
				$step = $max_step;
			}else{
				$step = $max_step - 5;
			}
		}

		if($step==30) $step=35;

		return $step;
	}

	// 취소,배송 상태 별 수량으로 option,suboption상태 변경
	public function set_option_step($seq,$mode='option'){

		if($mode == 'suboption'){
			$table			= "fm_order_item_suboption";
			$where_field	= "item_suboption_seq";
		}else{
			$table			= "fm_order_item_option";
			$where_field	= "item_option_seq";
		}
		$query = "select * from ".$table." where ".$where_field."=?";
		$query = $this->db->query($query,array($seq));
		list($row) = $query->result_array();

		$remain_ea = $row['ea'] - $row['step85'];
		$arr = array('75','65','55','45','35');
		foreach($arr as $ea_step){
			$field		= 'step'.$ea_step;
			$addFld[]	= $field;
			if( $remain_ea == $row[$field] ){
				$step = $ea_step;
				break;
			}

			// 부분상태로
			if( $row[$field] > 0 && $remain_ea != $row[$field] && $ea_step > 35 && $ea_step < 85){
				$step = $ea_step - 5;
				break;
			}
		}

		// 해당상품이 전수량 취소되었을경우
		if ( $row['ea'] == $row['step85'] ){
			$step = '85';
		}

		if	($step){
			$query = "update ".$table." set step=? where ".$where_field."=?";
			$this->db->query($query,array($step,$seq));
		// 결제확인 상태로 처리 ( 상태별수량의 합이 0이면 결제확인 )
		}else{
			$query = "update ".$table." set step=? where (".implode('+', $addFld).") = 0 and  step >= 25 and ".$where_field."=? ";
			$this->db->query($query,array(25,$seq));
		}
	}

	//묶음배송 처리
	public function set_bundle_order($mode, $order_seq, $bundle_export_code = ''){
		switch($mode){
			case	'set' :		// 묶음배송처리
				$this->db->query("UPDATE fm_order SET bundle_yn='y' WHERE order_seq=?",array($order_seq));
				break;

			case	'to_35' :	// 상품준비중 처리시 묶음배송
				$query			= $this->db->query("SELECT * FROM fm_goods_export WHERE bundle_export_code=?", array($bundle_export_code));
				$reset_bundle	= array();
				$reset_bundle[]	= $order_seq;

				//남은 묶음 배송이 2개 이하이면 묶음배송 해제
				if($query->num_rows < 2){
					if($query->num_rows == 1){
						//같이 확인해야할 묶음 배송 주문
						$rows			= $query->result_array();
						$reset_bundle[]	= $rows[0]['order_seq'];
					}

					$this->db->query("UPDATE fm_goods_export_item	SET bundle_export_code='' WHERE bundle_export_code = '{$bundle_export_code}'");
					$this->db->query("UPDATE fm_goods_export		SET bundle_export_code='' WHERE bundle_export_code = '{$bundle_export_code}'");
				}

				//다른 묶음 배송 출고가 남아있지 않은경우 묶음배송 해제
				foreach($reset_bundle as $now_order_seq){
					$query	= $this->db->query("SELECT * FROM fm_goods_export WHERE order_seq=? AND bundle_export_code LIKE 'B%'", array($now_order_seq));
					if($query->num_rows < 1){
						$this->db->query("UPDATE fm_order SET bundle_yn='n' WHERE order_seq=?",array($now_order_seq));
					}
				}
				break;
		}
	}

	public function set_order_step($order_seq,$mode=''){
		$step = 0;
		$this->load->model('refundmodel');
		$this->load->model('membermodel');
		$this->load->model('eventmodel');

		$query = "
			select * from (
				select ea,step85,step35,step45,step55,step65,step75 from fm_order_item_option
				where order_seq=? and step between ? and ?
				union
				select ea,step85,step35,step45,step55,step65,step75 from fm_order_item_suboption
				where order_seq=? and step between ? and ?
			) t";
		$query = $this->db->query($query,array($order_seq,15,75,$order_seq,15,75));
		foreach($query->result_array() as $data){
			$tot['ea']+=$data['ea'];
			$tot['step85']+=$data['step85'];
			$tot['step35']+=$data['step35'];
			$tot['step45']+=$data['step45'];
			$tot['step55']+=$data['step55'];
			$tot['step65']+=$data['step65'];
			$tot['step75']+=$data['step75'];
		}

		$step = $this->get_step($tot);

		if(!in_array($step,$this->all_step)) return false;


		/* 가장큰상태값, 가장작은상태값 */
		/*
		$query = $this->db->query("
			select
				max(a.step) max_step, min(a.step) min_step
			from (
				select
					step
				from
					fm_order_item_option
				where
					order_seq=?
					and step between ? and ?
				union
				select
					step
				from
					fm_order_item_suboption
				where
					order_seq=? and step between ? and ?
			) as a
		",array($order_seq,15,75,$order_seq,15,75));
		$data = $query->row_array();
		$max_step = $data['max_step'];
		$min_step = $data['min_step'];
		*/

		/* 부분(출고,배송) 상태값 */
		/*
		$query = $this->db->query("select step from fm_order_item_option where order_seq=? and step between ? and ? union select step from fm_order_item_suboption where order_seq=? and step between ? and ?",array($order_seq,15,75,$order_seq,15,75));
		$max_sub = 0;
		foreach($query->result_array() as $data){
			if( substr($data['step'],1) == 0 || in_array($data['step'], array('25','35'))){
				$max_sub = $data['step'];
			}
		}

		if($max_sub && $max_step > 35) $max_step = substr($max_step,0,1).'0';
		$step = $max_step;
		*/


		/* 모든 item이 결제취소일 경우 체크*/
		$query = "
			select sum(t.step85_cnt) as step85_cnt, sum(t.total_cnt) as total_cnt from
			(
				select sum(if(step='85',1,0)) as step85_cnt, count(*) as total_cnt from fm_order_item_option where order_seq=?
				union
				select sum(if(step='85',1,0)) as step85_cnt, count(*) as total_cnt from fm_order_item_suboption where order_seq=?
			) as t
		";
		$query = $this->db->query($query,array($order_seq,$order_seq));
		$res = $query->row_array();
		if($res['step85_cnt'] && $res['step85_cnt']==$res['total_cnt']) $step = 85;

		if($step){
			$query = "update fm_order set step=? where order_seq=?";
			$this->db->query($query,array($step,$order_seq));
		}

		## 주문취소시 이벤트 판매건 업데이트 추가 2015-05-20 pjm
		//if($step == 85){
			$event_stats	= array();
			$data_item 		= $this->get_item($order_seq);
			foreach($data_item as $item) if($item['event_seq']) $event_stats[] = $item['event_seq'];
			if($event_stats) $this->eventmodel->event_order_stat($event_stats);
		//}

		if(!$mode){

			// 주문이 모두 출고완료  경우
			if($step == 55){
				// 에스크로 주문 배송정보 전달
				$data_order = $this->get_order($order_seq);

				$pg = config_load($this->config_system['pgCompany']);
				if( preg_match('/escrow/',$data_order['payment']) ){
					if( preg_match('/virtual/',$data_order['payment']) ){
						$res_cd = '00';
						if($this->config_system['pgCompany']=='kicc'){
							$res_cd = '0000';
						}
						$where_array = array('res_cd'=>$res_cd);
						$data_pg_log = $this->get_pg_log($order_seq,$where_array);

						// 조회되는 pg_log 가 없고, 이니시스 사용중이면 0000으로 다시 조회하도록 수정 2019-06-14 by hyem
						if( $data_pg_log[0]['tno']=="" && $this->config_system['pgCompany']=='inicis' ) {
							$where_array = array('res_cd'=>'0000');
							$data_pg_log = $this->get_pg_log($order_seq,$where_array);
						}
						if( $data_pg_log[0]['tno'] ) $data_order['pg_transaction_number'] = $data_pg_log[0]['tno'];
					}
					$this -> {$this->config_system['pgCompany'].'_delivery'}($data_order,$pg);
				}
			}

			// 주문이 배송완료 일경우 주문한 회원의 구매횟수 및 구매금액 업데이트
			if($step == 75){
				$data_order = $this->get_order($order_seq);
				$data_item_option 	 = $this->get_item_option($order_seq);
				$data_item_suboption = $this->get_item_suboption($order_seq);

				if($data_item_option) foreach($data_item_option as $item) $order_ea += $item['ea'];
				if($data_item_suboption) foreach($data_item_suboption as $item) $order_ea += $item['ea'];

				$refund_price 	= $this->refundmodel->get_refund_price_for_order($order_seq,'cancel_payment','complete');
				$refund_ea 		= $this->refundmodel->get_refund_ea_for_order($order_seq,'cancel_payment','complete');

				$settle_price =  $data_order['settleprice'] - $refund_price;
				$order_ea =  $order_ea - $refund_ea;

				if($data_order['member_seq']){
					$this->membermodel->member_order($data_order['member_seq']);

					//주문건/주문금액 필드추가 및 실시간업데이트 @2013-06-19
					$this->membermodel->member_order_batch($data_order['member_seq']);
				}
			}
		}
		return $step;
	}

	// 주문서 정보 가져오기 > 주문서쿠폰
	public function get_order_ordersheet_coupon($member_seq=null, $order_seq, $ordersheet_download_seq,$mode=null){
		$addmembersql = '';
		if(!$mode){
			if(!$order_seq) return false;//!$member_seq &&
			$addmembersql .= "and ord.order_seq=".$order_seq;
		}
		if(!$ordersheet_download_seq) return false;//!$member_seq &&
		$addmembersql .= ($member_seq)? ' and ord.member_seq='.$member_seq.'  ':'';
		$query = "
			SELECT
				i.goods_name
				, i.goods_seq
				, i.order_seq
				, i.image
				, i.goods_seq
				, ord.ordersheet_sale as coupon_order_saleprice
				, ord.order_seq
			FROM  fm_order_item i
			left join fm_order ord on ord.order_seq = i.order_seq
			WHERE ord.step != 0
				and ord.ordersheet_seq=".$ordersheet_download_seq ." "
			. " ".$addmembersql;
		$query = $this->db->query($query);//s.provider_seq = 1 and
		if ( $query) {
			foreach($query->result_array() as $data){
				$items[] = $data;
			}
		}
		return $items;
	}

	// 주문서 정보 가져오기 > 배송쿠폰
	public function get_order_shipping_coupon($member_seq=null, $shipping_download_seq){
		if(!$shipping_download_seq) return false;//!$member_seq &&
		$addmembersql = ($member_seq)? ' and ord.member_seq='.$member_seq.'  ':'';
		$query = "
		SELECT i.goods_name, i.goods_seq, i.order_seq, i.image, s.shipping_coupon_sale as coupon_order_saleprice, s.provider_seq
		FROM  fm_order_item i
		left join fm_order ord on ord.order_seq = i.order_seq
		left join fm_order_shipping s on i.shipping_seq=s.shipping_seq
		WHERE ord.step != 0 and  s.shipping_coupon_down_seq=".$shipping_download_seq ." ".$addmembersql;

		$query = $this->db->query($query);//s.provider_seq = 1 and
		if ( $query) {
			foreach($query->result_array() as $data){
				$items[] = $data;
			}
		}
		return $items;
		/**
		if(!$download_seq) return false;//!$member_seq &&
		$addmembersql = ($member_seq)? ' and member_seq='.$member_seq.'  ':'';
		$query = $this->db->query('select * from fm_order where download_seq=?'.$addmembersql,array($download_seq));
		list($orders) = $query->result_array($query);
		return $orders;
		**/
	}

	//프로모션코드 > 배송프로모션코드
	public function get_order_shipping_promotion($member_seq=null, $shipping_promotion_code_seq){
		if(!$shipping_promotion_code_seq) return false;//!$member_seq &&
		$addmembersql = ($member_seq)? ' and ord.member_seq='.$member_seq.'  ':'';
		$query = "
		SELECT i.goods_name, i.goods_seq, i.order_seq, i.image, s.shipping_promotion_code_sale as promotion_order_saleprice, s.provider_seq
		FROM  fm_order_item i
		left join fm_order ord on ord.order_seq = i.order_seq
		left join fm_order_shipping s on i.shipping_seq=s.shipping_seq
		WHERE ord.step != 0 and  s.shipping_promotion_code_seq='".$shipping_promotion_code_seq ."'".$addmembersql;
		$query = $this->db->query($query);//s.provider_seq = 1 and
		if ( $query) {
			foreach($query->result_array() as $data){
				$items[] = $data;
			}
		}
		return $items;
	}

	//프로모션코드 주문상품 정보
	//옵션별로 나누기위해 $sprit 추가 2015-09-21 by jp
	public function get_option_promotioncode_item($member_seq=null, $promotion_code_seq, $sprit = false){

		if(!$promotion_code_seq) return false;//!$member_seq &&
		$set_field		= ($sprit === true) ? 'o.promotion_code_sale': 'sum(o.promotion_code_sale)';
		$addmembersql	= ($member_seq)? ' and ord.member_seq='.$member_seq.'  ':'';
		$query = "
		SELECT o.item_option_seq,i.goods_name, i.goods_seq, i.order_seq, i.image, ".$set_field." as promotion_order_saleprice
		FROM  fm_order_item i
		left join fm_order ord on ord.order_seq = i.order_seq
		left join fm_order_item_option o on i.item_seq=o.item_seq
		WHERE  ord.step != 0 and  o.promotion_code_seq='".$promotion_code_seq."'".$addmembersql;
		$query = $this->db->query($query);
		foreach($query->result_array() as $data){
			//주문상품의 이미지가 없는경우 실제상품의 이미지를 가져옴
			if( !$data['image'] || !is_file(ROOTPATH.$data['image']) ) {
				$data['image'] = viewImg($data['goods_seq'],'thumbCart');
			}
			$items[] = $data;
		}
		return $items;
	}

	//쿠폰 주문상품 정보
	//옵션별로 나누기위해 $sprit 추가 2015-09-21 by jp
	public function get_option_coupon_item($member_seq=null, $download_seq, $sprit = false){
		if(!$download_seq) return false;//!$member_seq &&
		$set_field		= ($sprit === true) ? 'o.coupon_sale': 'sum(o.coupon_sale)';
		$addmembersql	= ($member_seq)? ' and ord.member_seq='.$member_seq.'  ':'';
		$query = "
		SELECT o.item_option_seq,i.goods_name, i.goods_seq, i.order_seq, i.image, ".$set_field." as coupon_order_saleprice
		FROM  fm_order_item i
		left join fm_order ord on ord.order_seq = i.order_seq
		left join fm_order_item_option o on i.item_seq=o.item_seq
		WHERE  ord.step != 0 and ord.step != 99 and o.download_seq='".$download_seq."'".$addmembersql;
		$query = $this->db->query($query);
		foreach($query->result_array() as $data){
			//주문상품의 이미지가 없는경우 실제상품의 이미지를 가져옴
			if( !$data['image'] || !is_file(ROOTPATH.$data['image']) ) {
				$data['image'] = viewImg($data['goods_seq'],'thumbCart');
			}
			$items[] = $data;
		}
		return $items;
	}


	// 재주문 넣기
	public function reorder($orign_order_seq,$return_code){

		$this->load->model('returnmodel');
		$data_order = $this->get_order($orign_order_seq);
		$data_item 	= $this->get_item($orign_order_seq);
		$data_option = $this->get_item_option($orign_order_seq);
		$data_sub 	= $this->get_item_suboption($orign_order_seq);

		// 생성할 주문서에 들어갈 값 정리
		$arr_del = array(
			'log',
			'bank_account',
			'virtual_account',
			'virtual_date',
			'pg_transaction_number',
			'pg_approval_number',
			'cash_receipts_no',
			'emoney_use',
			'emoney',
			'cash_use',
			'cash',
			'shipping_cost',
			'international_cost',
			'download_seq',
			'coupon_sale',
			'typereceipt',
			'important'
		);
		$insert_order = $data_order;
		// 반품시 최상위 주문번호 저장 :: 2014-11-27 lwh
		if($data_order['top_orign_order_seq'])
			$insert_order['top_orign_order_seq'] = $data_order['top_orign_order_seq'];
		else
			$insert_order['top_orign_order_seq'] = $orign_order_seq;

		$insert_order['order_seq'] = $order_seq = $this -> get_order_seq();
		$insert_order['orign_order_seq'] = $orign_order_seq;
		$insert_order['original_settleprice'] = 0;
		$insert_order['settleprice'] = 0;
		$insert_order['enuri'] = 0;
		$insert_order['step'] = 25;
		$insert_order['deposit_yn'] = 'y';
		$insert_order['deposit_date'] = date('Y-m-d H:i:s');
		$insert_order['regist_date'] = date('Y-m-d H:i:s');
		$insert_order['payment'] = 'bank';
		foreach($arr_del as $data) unset($insert_order[$data]);

		$newinsert_order = filter_keys($insert_order, $this->db->list_fields('fm_order'));

		//2017-12-21 샵링커 주문 재설정
		if(stripos($newinsert_order['linkage_mall_code'],'API') !== false){
			$newinsert_order['linkage_mall_order_id'] = 'ex-'.$newinsert_order['linkage_mall_order_id'];
		}

		$this->db->insert('fm_order', $newinsert_order);


		$arr_del = array(
			'item_seq',
			'goods_shipping_cost',
			'shipping_policy',
			'shipping_unit',
			'basic_shipping_cost',
			'add_shipping_cost',
			'cancel_type',
			'master_goods_seq'
		);
		$arr_del_option = array(
			'item_option_seq',
			'price',
			'member_sale',
			'download_seq',
			'coupon_sale',
			'promotion_code_sale',
			'promotion_code_seq',
			'referer_sale',
			'consumer_price',
			'supply_price',
			'refund_ea',
			'step85',
			'step35',
			'step45',
			'step55',
			'step65',
			'step75',
			'goods_seq',
			'image',
			'goods_name',
			'goods_shipping_cost',
			'shipping_policy',
			'shipping_unit',
			'basic_shipping_cost',
			'add_shipping_cost',
			'multi_discount_ea',
			'account_date',
			'goods_type',
			'cancel_type',
			'individual_refund',
			'individual_refund_inherit',
			'individual_export',
			'individual_return',
			'tax',
			'fblike_sale',
			'mobile_sale',
			'event_seq',
			'goods_kind',
			'socialcp_input_type',
			'socialcp_use_return',
			'socialcp_use_emoney_day',
			'socialcp_use_emoney_percent',
			'salescost_provider_coupon',
			'salescost_provider_promotion',
			'salescost_provider_referer',

			// 쿠폰 & 마일리지 세일에 의한 값도 초기화 by hed
			// 각종 할인정보 초기화
			'coupon_sale',
			'coupon_sale_krw',
			'basic_sale',
			'event_sale',
			'multi_sale',
			'mobile_sale_unit',
			'fblike_sale_unit',
			'coupon_sale_unit',
			'code_sale_unit',
			'referer_sale_unit',
			'member_sale_rest',
			'mobile_sale_rest',
			'fblike_sale_rest',
			'coupon_sale_rest',
			'code_sale_rest',
			'referer_sale_rest',

			// 마일리지, 예치금, 에누리, npay 포인트 할인정보 초기화
			'emoney_sale_unit',
			'cash_sale_unit',
			'enuri_sale_unit',
			'npay_point_sale_unit',
			'emoney_sale_rest',
			'cash_sale_rest',
			'enuri_sale_rest',
			'npay_point_sale_rest'

		);

		$arr_del_suboption = array(
			'item_suboption_seq',
			'price',
			'member_sale',
			'consumer_price',
			'supply_price',
			'refund_ea',
			'step85',
			'step35',
			'step45',
			'step55',
			'step65',
			'step75',
			'goods_seq',
			'image',
			'goods_name',
			'goods_shipping_cost',
			'shipping_policy',
			'shipping_unit',
			'basic_shipping_cost',
			'add_shipping_cost',
			'multi_discount_ea',

			'goods_type',
			'cancel_type',
			'individual_refund',
			'individual_refund_inherit',
			'individual_export',
			'individual_return',
			'tax',
			'fblike_sale',
			'mobile_sale',
			'event_seq',
			'goods_kind',
			'socialcp_input_type',
			'socialcp_use_return',
			'socialcp_use_emoney_day',
			'socialcp_use_emoney_percent',

			// 쿠폰 & 마일리지 세일에 의한 값도 초기화 by hed
			// 각종 할인정보 초기화
			'coupon_sale',
			'coupon_sale_krw',
			'basic_sale',
			'event_sale',
			'multi_sale',
			'mobile_sale_unit',
			'fblike_sale_unit',
			'coupon_sale_unit',
			'code_sale_unit',
			'referer_sale_unit',
			'member_sale_rest',
			'mobile_sale_rest',
			'fblike_sale_rest',
			'coupon_sale_rest',
			'code_sale_rest',
			'referer_sale_rest',

			// 마일리지, 예치금, 에누리, npay 포인트 할인정보 초기화
			'emoney_sale_unit',
			'cash_sale_unit',
			'enuri_sale_unit',
			'npay_point_sale_unit',
			'emoney_sale_rest',
			'cash_sale_rest',
			'enuri_sale_rest',
			'npay_point_sale_rest'
		);



		$tot_reserve		= $tot_point = 0;
		$date_return_item	= $this->returnmodel->check_return_item($return_code);
		$data_shipping		= array();

		foreach($data_item as $item){

			$item_seq = '';

			// 교환 요청 수량만큼난 조회
			foreach($date_return_item as $return_option){

				if($item['item_seq'] == $return_option['item_seq']){
					if(!isset($data_shipping[$item['shipping_seq']])) {
						$data_shipping_item = $this->get_seq_for_order_shipping($item['shipping_seq']);
						$data_shipping[$item['shipping_seq']] = $data_shipping_item;

						$insert_shipping = $data_shipping_item;
						$insert_shipping['order_seq']		= $order_seq;
						$insert_shipping['shipping_cost']	= 0;
						$insert_shipping['shipping_cost_krw']	= 0;
						unset($insert_shipping['shipping_seq']);
						$this->db->insert('fm_order_shipping', $insert_shipping);
						$shipping_seq = $this->db->insert_id();
					}

					// 추가옵션만 교환했을 경우 order_item 이 생성되지 않아 오류 발생
					if(!$item_seq){
						$insert_item					= $item;
						$insert_item['order_seq']		= $order_seq;
						$insert_item['shipping_seq'] 	= $shipping_seq;
						unset($insert_item['shipping_provider_seq']);	//배송정보초기화
						unset($insert_item['provider_id']);				//입점사id제거
						foreach($arr_del as $data) unset($insert_item[$data]);

						$this->db->insert('fm_order_item', $insert_item);
						$item_seq = $this->db->insert_id();
					}

					if($return_option['option_seq']){

						// 상품 옵션 재주문
						unset($insert_option);
						foreach($data_option as $option){
							if($option['item_option_seq'] == $return_option['option_seq']){
								$insert_option = $option;

								// 연결상품이 있으면
								if($option['package_yn'] == 'y'){
									$package_yn = 'y';
								}

								// 상위 seq 존재시 대체 :: 2014-11-27 lwh
								if($option['top_item_option_seq']){
									$insert_option['top_item_option_seq']	= $option['top_item_option_seq'];
									$insert_option['top_item_seq']			= $option['top_item_seq'];
								}else{
									$insert_option['top_item_option_seq']	= $option['item_option_seq'];
									$insert_option['top_item_seq']			= $option['item_seq'];
								}

								$insert_option['shipping_seq']	= $shipping_seq;
								$insert_option['ea']			= $return_option['ea'];
								$insert_option['order_seq']		= $order_seq;
								$insert_option['item_seq']		= $item_seq;
								$insert_option['step']			= 25;

								# 지급했던 마일리지, 포인트 금액 가져오기 2015-09-14 pjm
								$tot_reserve					+= $return_option['give_reserve'];
								$tot_point						+= $return_option['give_point'];
							}
						}
						if($insert_option){
							unset($insert_option['tax']);
							foreach($arr_del_option as $data) unset($insert_option[$data]);

							$newinsert_option = filter_keys($insert_option, $this->db->list_fields('fm_order_item_option'));
							$this->db->insert('fm_order_item_option', $newinsert_option);
							$item_option_seq = $this->db->insert_id();
						}
					}
					if($return_option['suboption_seq']){

						// 서브상품 옵션 재주문
						unset($insert_sub_option);
						foreach($data_sub as $suboption){

							if($suboption['item_suboption_seq'] == $return_option['suboption_seq']){

								//추가옵션이 필수옵션에 물려있어 출고처리 되지 않음. 해서 아래와 같이 수정. @2016-03-16 pjm
								//추가옵션만 교환했을 경우 상위옵션정보 insert 해준다. 단, 주문수량은 0
								if(!$item_option_seq){

									// 상품 옵션 재주문
									unset($insert_option);
									foreach($data_option as $option){
										if($option['item_option_seq'] == $suboption['item_option_seq']){
											$insert_option = $option;

											// 상위 seq 존재시 대체 :: 2014-11-27 lwh
											if($option['top_item_option_seq']){
												$insert_option['top_item_option_seq'] = $option['top_item_option_seq'];
												$insert_option['top_item_seq'] = $option['top_item_seq'];
											}else{
												$insert_option['top_item_option_seq'] = $option['item_option_seq'];
												$insert_option['top_item_seq'] = $option['item_seq'];
											}

											$insert_option['shipping_seq']	= $shipping_seq;
											$insert_option['ea']			= 0;
											$insert_option['order_seq']		= $order_seq;
											$insert_option['item_seq']		= $item_seq;
											$insert_option['step']			= 25;

											# 지급했던 마일리지, 포인트 금액 가져오기 2015-09-14 pjm
											$tot_reserve					+= $return_option['give_reserve'];
											$tot_point						+= $return_option['give_point'];
										}
									}
									if($insert_option){
										unset($insert_option['tax']);
										foreach($arr_del_option as $data) unset($insert_option[$data]);

										$newinsert_option = filter_keys($insert_option, $this->db->list_fields('fm_order_item_option'));
										$this->db->insert('fm_order_item_option', $newinsert_option);
										$item_option_seq = $this->db->insert_id();
									}
								}

								$insert_sub_option = $suboption;

								// 연결상품이 있으면
								if($suboption['package_yn'] == 'y'){
									$package_yn = 'y';
								}
								// 상위 seq 존재시 대체 :: 2014-11-27 lwh
								if($suboption['top_item_suboption_seq']){
									$insert_sub_option['top_item_suboption_seq'] = $suboption['top_item_suboption_seq'];
								}else{
									$insert_sub_option['top_item_suboption_seq'] = $suboption['item_suboption_seq'];
								}

								$insert_sub_option['shipping_seq']		= $shipping_seq;
								$insert_sub_option['ea']				= $return_option['ea'];
								$insert_sub_option['order_seq']			= $order_seq;
								$insert_sub_option['item_seq']			= $item_seq;
								$insert_sub_option['item_option_seq']	= ($item_option_seq)? $item_option_seq : '';
								$insert_sub_option['step']				= 25;

								# 지급했던 마일리지, 포인트 금액 가져오기 2015-09-14 pjm
								$tot_reserve							+= $return_option['give_reserve'];
								$tot_point								+= $return_option['give_point'];
							}
						}
						if($insert_sub_option){
							unset($insert_sub_option['tax']);
							foreach($arr_del_suboption as $data) unset($insert_sub_option[$data]);

							$newinsert_sub_option = filter_keys($insert_sub_option, $this->db->list_fields('fm_order_item_suboption'));
							$this->db->insert('fm_order_item_suboption', $newinsert_sub_option);
						}
					}
				}
			}
		}

		// 연결상품이 1개라도 있으면 패키지 상품 연결 생성
		if($package_yn == 'y'){
			$this->load->model('orderpackagemodel');
			$this->orderpackagemodel->package_order($order_seq);
		}

		if($tot_reserve > 0 || $tot_point > 0) $this->load->model('membermodel');

		/* 마일리지 회수 */
		if($tot_reserve){
			$params = array(
				'gb'		=> 'minus',
				'type'		=> 'reorder',
				'emoney'	=> $tot_reserve,
				'ordno'		=> $orign_order_seq,
				'memo'		=> "[차감] 맞교환/재주문({$orign_order_seq})에 의하여 배송완료시 지급된 마일리지 차감",
				'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp256",$orign_order_seq), // [차감] 맞교환/재주문(%s)에 의하여 배송완료시 지급된 마일리지 차감
			);
			$this->membermodel->emoney_insert($params, $data_order['member_seq']);
		}

		/* 포인트 회수 */
		if($tot_point){
			$params = array(
				'gb'		=> 'minus',
				'type'		=> 'reorder',
				'point'		=> $tot_point,
				'ordno'		=> $orign_order_seq,
				'memo'		=> "[차감] 맞교환/재주문({$orign_order_seq})에 의하여 배송완료시 지급된 포인트 차감",
				'memo_lang'	=> $this->membermodel->make_json_for_getAlert("mp257",$orign_order_seq), // [차감] 맞교환/재주문(%s)에 의하여 배송완료시 지급된 포인트 차감
			);
			$this->membermodel->point_insert($params, $data_order['member_seq']);
		}

		/**
		* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 시작
		* 정산개선 - 미정산매출데이타 처리
		* @
		**/
		   $this->load->helper('accountall');
		   if(!$this->accountallmodel)	$this->load->model('accountallmodel');
		   if(!$this->providermodel)	$this->load->model('providermodel');
		   //step1 주문금액별 정의/비율/단가계산 후 정렬
		   $set_order_price_ratio = $this->accountallmodel->set_order_price_ratio($order_seq);

		   //step3 임시 매출/정산 저장
		   $this->accountallmodel->insert_calculate_sales_order_tmp($order_seq, $set_order_price_ratio);
		   $this->accountallmodel->insert_calculate_sales_order_deposit($order_seq);

		/**
		* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 끝
		* 정산개선 - 미정산매출데이타 처리
		* @
		**/

		return $order_seq;
	}

	public function get_option_reserve($seq, $type='reserve',$opt_type = 'OPT'){
		if($opt_type == 'OPT'){
			$query = "select ifnull(reserve,0) as reserve, ifnull(point,0) as point from fm_order_item_option where item_option_seq=?";
		}else{
			$query = "select ifnull(reserve,0) as reserve, ifnull(point,0) as point from fm_order_item_suboption where item_suboption_seq=?";
		}
		$query = $this->db->query($query,array($seq));
		$data = $query->result_array();
		return $data[0][$type];
	}

	public function get_suboption_reserve($seq, $type='reserve'){
		$query = "select ifnull(reserve,0) as reserve, ifnull(point,0) as point from fm_order_item_suboption where item_suboption_seq=?";
		$query = $this->db->query($query,array($seq));
		$data = $query->result_array();
		return $data[0][$type];
	}

	//주문 배송지 저장
	public function insert_delivery_address($insert_mode=''){

		$params = $this->input->post();

		if($params['insert_mode']){
			$insert_mode = $params['insert_mode'];
		}

		if($this->userInfo['member_seq']) $member_seq = $this->userInfo['member_seq'];
		if($params['adminOrder'] == 'admin') $member_seq=$params['member_seq'];
		if($member_seq){

			if($params['often_recipient_user_name'])			$params['recipient_user_name']	= $params['often_recipient_user_name'];
			if($params['often_recipient_phone'])				$params['recipient_phone']		= $params['often_recipient_phone'];
			if($params['often_recipient_cellphone'])			$params['recipient_cellphone']	= $params['often_recipient_cellphone'];
			if($params['often_recipient_zipcode'])			$params['recipient_zipcode']		= $params['often_recipient_zipcode'];
			if($params['often_recipient_address_type'])		$params['recipient_address_type']= $params['often_recipient_address_type'];
			if($params['often_recipient_address'])			$params['recipient_address']		= $params['often_recipient_address'];
			if($params['often_recipient_address_street'])	$params['recipient_address_street']	= $params['often_recipient_address_street'];
			if($params['often_recipient_address_detail'])	$params['recipient_address_detail']	= $params['often_recipient_address_detail'];

			if($insert_mode == 'order'){
				$insert_params['often'] 					= 'Y';
				$insert_params['lately'] 					= 'Y';
				$insert_params['regist_date']				= date('Y-m-d H:i:s');
			}elseif($insert_mode == 'insert'){
				$insert_params['address_description']		= $params['address_description'];
				$insert_params['often'] 					= 'Y';
				$insert_params['regist_date']				= date('Y-m-d H:i:s');
			}else{
				$insert_params['lately'] 					= 'Y';
				$insert_params['regist_date']				= date('Y-m-d H:i:s');
			}

			$insert_params['member_seq'] 					= $member_seq;
			$insert_params['recipient_user_name']			= $params['recipient_user_name'];
			$insert_params['nation']						= $params['address_nation'];

			if($params['international'] == '1'){

				$insert_params['international'] 				= 'international';
				$insert_params['region'] 						= $params['region'];
				$insert_params['international_address'] 		= $params['international_address'];
				$insert_params['international_town_city'] 		= $params['international_town_city'];
				$insert_params['international_county'] 			= $params['international_county'];
				$insert_params['international_postcode'] 		= $params['international_postcode'];
				$insert_params['international_country'] 		= $params['international_country'];
				$insert_params['recipient_phone'] 				= implode('-',$params['international_recipient_phone']);
				$insert_params['recipient_cellphone'] 			= implode('-',$params['international_recipient_cellphone']);

			}else{

				$insert_params['international'] 				= 'domestic';

				$insert_params['recipient_zipcode'] 			= implode('-',$params['recipient_zipcode']);

				if($params['recipient_new_zipcode']) $insert_params['recipient_zipcode'] 	= $params['recipient_new_zipcode'];

				$insert_params['recipient_address_type'] 		= ( $params['recipient_address_type'] == 'street' && trim($params['recipient_address_street']) )?$params['recipient_address_type']:"zibun";
				$insert_params['recipient_address'] 			= $params['recipient_address'];
				$insert_params['recipient_address_street'] 		= $params['recipient_address_street'];
				$insert_params['recipient_address_detail'] 		= $params['recipient_address_detail'];
				$insert_params['recipient_phone'] 				= implode('-',$params['recipient_phone']);
				$insert_params['recipient_cellphone'] 			= implode('-',$params['recipient_cellphone']);
			}

			if($params['save_delivery_address']){
				$insert_params['default'] 					= 'Y';
			}

			if($params['address_group']){
				$insert_params['address_group'] 				= $params['address_group'];
			}
			// 기존에 등록된 주소는 update, 아니면 insert
			$address_seq = $params['address_seq'];
			if($address_seq) {
				$this->db->where('address_seq',$address_seq);
				$this->db->where('member_seq',$member_seq);
				$this->db->update('fm_delivery_address', $insert_params);
			} else {
				$this->db->insert('fm_delivery_address', $insert_params);
				$address_seq = $this->db->insert_id();
			}


			### Private Encrypt
			$cellphone = get_encrypt_qry('recipient_cellphone');
			$phone = get_encrypt_qry('recipient_phone');
			$sql = "update fm_delivery_address set  {$cellphone}, {$phone}, update_date = now() where address_seq = {$address_seq}";
			$this->db->query($sql);
			###

			if($params['save_delivery_address']){
				$sql = "update fm_delivery_address set `default` = 'N' where member_seq=? and address_seq!=?";
				$this->db->query($sql,array($member_seq,$address_seq));
			}
		}
	}

	public function update_delivery_address($seq){

		if($this->userInfo['member_seq']) $member_seq = $this->userInfo['member_seq'];
		if($_POST['adminOrder'] == 'admin') $member_seq=$_POST['member_seq'];
		if($member_seq){

			if($_POST['often_recipient_user_name'])		$_POST['recipient_user_name']		= $_POST['often_recipient_user_name'];
			if($_POST['often_recipient_phone'])			$_POST['recipient_phone']			= $_POST['often_recipient_phone'];
			if($_POST['often_recipient_cellphone'])		$_POST['recipient_cellphone']		= $_POST['often_recipient_cellphone'];
			if($_POST['often_recipient_zipcode'])		$_POST['recipient_zipcode']			= $_POST['often_recipient_zipcode'];
			if($_POST['often_recipient_address_type'])	$_POST['recipient_address_type']	= $_POST['often_recipient_address_type'];
			if($_POST['often_recipient_address'])		$_POST['recipient_address']			= $_POST['often_recipient_address'];
			if($_POST['often_recipient_address_street'])$_POST['recipient_address_street']	= $_POST['often_recipient_address_street'];
			if($_POST['often_recipient_address_detail'])$_POST['recipient_address_detail']	= $_POST['often_recipient_address_detail'];

			$params['address_description']			= $_POST['address_description'];
			$params['often'] 						= 'Y';
			$params['member_seq'] 					= $member_seq;
			$params['recipient_user_name']			= $_POST['recipient_user_name'];

			if ($_POST['address_group']) {
				$params['address_group']			= $_POST['address_group'];
			}
			if ($_POST['select_address_group']) {
				$params['address_group']			= $_POST['select_address_group'];
			}
			$params['nation']						= $_POST['nation_select'];

			if($_POST['international'] ){

				$params['international'] 				= 'international';	//배송(해외)
				$params['region'] 						= $_POST['region'];
				$params['international_address'] 		= $_POST['international_address'];
				$params['international_town_city'] 		= $_POST['international_town_city'];
				$params['international_county'] 			= $_POST['international_county'];
				$params['international_postcode'] 		= $_POST['international_postcode'];
				$params['international_country'] 		= $_POST['international_country'];
				$params['recipient_phone'] 		= implode('-',$_POST['international_recipient_phone']);
				$params['recipient_cellphone'] 	= implode('-',$_POST['international_recipient_cellphone']);

			}else if(!$_POST['international']){

				$params['international'] 				= 'domestic';		//배송(국내)

				if($_POST['recipient_new_zipcode']){
					$params['recipient_zipcode'] 			= $_POST['recipient_new_zipcode'];
				}else{
					$params['recipient_zipcode'] 			= implode('',$_POST['recipient_zipcode']);
				}

				$params['recipient_address_type']		= ( $_POST['recipient_address_type'] == 'street' && trim($_POST['recipient_address_street']) )?$_POST['recipient_address_type']:"zibun";
				$params['recipient_address'] 			= $_POST['recipient_address'];
				$params['recipient_address_street'] 	= $_POST['recipient_address_street'];
				$params['recipient_address_detail'] 	= $_POST['recipient_address_detail'];
				$params['recipient_phone'] 				= implode('-',$_POST['recipient_phone']);
				$params['recipient_cellphone'] 			= implode('-',$_POST['recipient_cellphone']);

			}

			if($_POST['save_delivery_address']){
				$params['default'] 					= 'Y';
			}

			$insert_params['update_date']				= date('Y-m-d H:i:s');
			$this->db->where('address_seq',$seq);
			$this->db->update('fm_delivery_address', $params );

			### Private Encrypt
			$cellphone	= get_encrypt_qry('recipient_cellphone');
			$phone		= get_encrypt_qry('recipient_phone');
			$sql = "update fm_delivery_address set  {$cellphone}, {$phone}, update_date = now() where address_seq = {$seq}";
			$this->db->query($sql);
			###

			if($_POST['save_delivery_address']){
				$sql = "update fm_delivery_address set `default` = 'N' where member_seq=? and address_seq!=?";
				$this->db->query($sql,array($member_seq,$seq));
			}
		}
	}

	public function get_order_item_option($option_seq){
		$query = "select *,(select goods_seq from fm_order_item where item_seq=fm_order_item_option.item_seq) goods_seq from fm_order_item_option where item_option_seq=?";
		$query = $this->db->query($query,array($option_seq));
		$data = $query->row_array();
		return $data;
	}

	public function get_order_item_suboption($suboption_seq){
		$query = "select *,(select goods_seq from fm_order_item where item_seq=fm_order_item_suboption.item_seq) goods_seq from fm_order_item_suboption where item_suboption_seq=?";
		$query = $this->db->query($query,array($suboption_seq));
		$data = $query->row_array();
		return $data;
	}

	public function get_order_total_ea($order_seq){
		$order_total_ea = 0;

		$query = "select sum(ea) as total_ea from fm_order_item_option where order_seq=?";
		$query = $this->db->query($query,array($order_seq));
		$data = $query->row_array();
		$order_total_ea += $data['total_ea'];

		$suboption_total_ea = 0;
		$query = "select sum(ea) as total_ea from fm_order_item_suboption where order_seq=?";
		$query = $this->db->query($query,array($order_seq));
		$data = $query->row_array();
		$order_total_ea += $data['total_ea'];

		return $order_total_ea;


	}

	public function get_pg_log($order_seq,$where_array=''){
		$query = "select * from fm_order_pg_log where order_seq=?";
		$bind[] = $order_seq;
		if($where_array){
			foreach($where_array as $field => $val){
				$query .= " and `".$field."`=?";
				$bind[] = $val;
			}
		}
		$query = $this->db->query($query,$bind);
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	# Npay 배송그룹별 잔여 주문 수량 = 총 주문수량 - 취소수량
	public function get_order_remain_ea($order_seq,$packgenumber,$mode='refund',$refund_code='',$return_code=''){

		$order_total_ea		= 0;
		$order_cancel_ea	= 0;

		$query	= "
				select sum(total_ea) total_ea from (
				(select sum(ea) as total_ea from fm_order_item_option where order_seq=? and npay_packgenumber=?)
				union all
				(select sum(ea) as total_ea from fm_order_item_suboption where order_seq=? and npay_packgenumber=?)
				) k";
		$query			= $this->db->query($query,array($order_seq,$packgenumber,$order_seq,$packgenumber));
		$data			= $query->row_array();
		$order_total_ea = $data['total_ea'];

		$bind	= array();
		$bind[] = $order_seq;
		$bind[] = $packgenumber;
		if($mode == "refund"){
			$where = "and ref.".$mode."_code != ?";
			$bind[] = $refund_code;
			$field1 = "sum(refund_goods_price) as refund_goods_price,
						sum(refund_delivery_price) as refund_delivery_price,";
			$field2 = "sum(refund_goods_price) as refund_goods_price,
						sum(refund_delivery_price) as refund_delivery_price,";
		}else{
			if($return_code){
				$where = "and ref.".$mode."_code != ?";
				$bind[] = $return_code;
			}
		}
		$bind[] = $order_seq;
		$bind[] = $packgenumber;
		if($mode == "refund") $bind[] = $refund_code;
		if($mode == "return" && $return_code) $bind[] = $return_code;

		$query	= "
				select ".$field1."
						sum(request_ea) request_ea,
						sum(complete_ea) complete_ea
				from
					(select
						".$field2."
						sum((case when ref.status='request' then ref_item.ea else 0 end)) as request_ea,
						sum((case when ref.status='complete' then ref_item.ea else 0 end)) as complete_ea
					from
						fm_order_item_option as opt
						left join fm_order_".$mode."_item as ref_item on opt.item_option_seq=ref_item.option_seq
							 and (ref_item.suboption_seq is null or ref_item.suboption_seq = '')
						left join fm_order_".$mode." as ref on ref_item.".$mode."_code=ref.".$mode."_code
					where
						opt.order_seq=?
						and opt.npay_packgenumber =?
						".$where."
					union all
					select
						".$field2."
						sum((case when ref.status='request' then ref_item.ea else 0 end)) as request_ea,
						sum((case when ref.status='complete' then ref_item.ea else 0 end)) as complete_ea
					from
						fm_order_item_suboption as opt
						left join fm_order_".$mode."_item as ref_item on opt.item_suboption_seq=ref_item.suboption_seq
						left join fm_order_".$mode." as ref on ref_item.".$mode."_code=ref.".$mode."_code
					where
						opt.order_seq=?
						and opt.npay_packgenumber =?
						".$where."
				) k
					;
				";
		$query		= $this->db->query($query,$bind);
		$data						= $query->row_array();
		$order_cancel_goods_price	= $data['refund_goods_price'];
		$order_cancel_delivery_price= $data['refund_delivery_price'];
		$order_cancel_request_ea	= $data['request_ea'];
		$order_cancel_complete_ea	= $data['complete_ea'];

		if($mode == "return"){
			$remain_ea = $order_total_ea - $order_cancel_ea;
		}else{
			$remain_ea = $order_cancel_request_ea;
		}

		$return = array("order_total_ea"=>$order_total_ea,
				"cancel_request_ea"=>$order_cancel_request_ea,
				"cancel_complete_ea"=>$order_cancel_complete_ea);

		if($mode == "refund"){
			$return["order_cancel_goods_price"]		= $order_cancel_goods_price;
			$return["order_cancel_delivery_price"]	= $order_cancel_delivery_price;
		}

		return $return;
	}

	/* 입점사별 배송비 가져오기 */
	public function get_order_shipping($order_seq,$provider_seq=null,$shipping_seq=null, $wheres=array()){

		$query = "
			select
				s.*,
				p.provider_name
			from
				fm_order_shipping s,fm_provider p
			WHERE s.provider_seq=p.provider_seq";
		if($order_seq) {
			$query .=" and s.order_seq=?";
			$binds[] = $order_seq;
		}
		if($provider_seq) $query .= " and s.provider_seq = '{$provider_seq}'";
		if($shipping_seq) $query .= " and s.shipping_seq = '{$shipping_seq}'";

		if($wheres) {
			foreach($wheres as $k=>$v){
				if(is_array($v)){
					$query .= " and {$k} in ? ";
					$binds[] = $v;
				}else{
					$query .= " and {$k} = ? ";
					$binds[] = $v;
				}
			}
		}
		$query = $this->db->query($query,$binds);
		$this->load->model('shippingmodel');
		foreach($query->result_array() as $data){
			unset($tmp_code_arr);
			if	(!serviceLimit('H_AD'))	unset($data['provider_name']);

			// new 배송설정 정보 추출 :: 2016-08-08 lwh
			$resArr = $this->shippingmodel->get_ship_info($data['shipping_group'], $data['shipping_method']);
			$cart_opt_seq	= $resArr['cart_opt_seq'];
			$ship_grp_seq	= $resArr['ship_grp_seq'];
			$ship_set_seq	= $resArr['ship_set_seq'];
			$shipping_code	= $resArr['shipping_code'];

			$data['shipping_set_code'] = $shipping_code;

			// 주문시 DB에 직접 저장으로 변경 :: 2016-09-23 lwh
			//$set_info		= $this->shippingmodel->get_shipping_set($ship_set_seq, 'shipping_set_seq');
			$data['shipping_set_name'] = $data['shipping_set_name'];
			if		($data['shipping_type'] == 'prepay'){
				$data['shipping_pay_type'] = getAlert("sy002"); // "주문시 결제";
			}else if($data['shipping_type'] == 'postpaid'){
				$data['shipping_pay_type'] = getAlert("sy003"); // "착불";
			}else{
				$data['shipping_pay_type'] = getAlert("sy010"); // "무료";
			}

			// 매장수령 정보 추출
			if($shipping_code == 'direct_store'){
				$scParams['store_scm_type'] = $data['store_scm_type'];
				$scParams['shipping_address_seq'] = $data['shipping_address_seq'];
				$store_info = $this->shippingmodel->get_shipping_store($ship_grp_seq,'shipping_group_seq_tmp',$scParams);

				$data['shipping_store_name'] = $store_info[0]['shipping_store_name'];
			}

			// 배송사명이 본사일 경우
			if($data['provider_name']=="본사"){$data['provider_name'] = getAlert("sy009");} // "본사";

			// 3차 환불 개선으로 배송그룹요약 정보 가져오기 :: 2018-11- lkh
			// 요약 정보를 가져오는 것이 아닌 실제 지정된 배송그룹을 가져오고 해당 배송그룹이 조건부 배송비인지 검사.
			// 해당 내역을 DB에 저장하고 DB 내역을 불러오는 것은 차후 개선 by hed
			// $data['shipping_summary'] = $this->shippingmodel->get_shipping_group_summary($ship_grp_seq);
			$shipping_summary_default_type = 'free'; // $this->shippingmodel->default_type;
			$shipping_opt_info		= $this->shippingmodel->get_shipping_opt($ship_grp_seq, 'shipping_group_seq');
			foreach($shipping_opt_info as $shipping_otp){
				// 일치한 배송옵션 조회
				if(
					$shipping_otp['shipping_set_seq'] == $ship_set_seq
					&& $shipping_otp['shipping_set_code'] == $shipping_code
					&& $shipping_otp['shipping_set_name'] == $data['shipping_set_name']
				){
					// 무료거나 고정일 경우
					if(in_array($shipping_otp['shipping_opt_type'], array('free', 'fixed')) && in_array($shipping_summary_default_type, array('free', 'fixed'))){
						// 고정일 경우 데이터 변경
						if($shipping_otp['shipping_opt_type'] == 'fixed'){
							$shipping_summary_default_type = $shipping_otp['shipping_opt_type'];
						}
					}else{
						// 그 이외의 경우는 무조건 조건부 배송비
						$shipping_summary_default_type = 'iffree'; // 조건부배송비(iffree)와 조건부차등배송비(ifpay)의 동작이 다를 경우 구조 변경 필요.
					}
				}
			}

			$data['shipping_summary']['default_type'] = $shipping_summary_default_type;
			$result[$data['shipping_group']] = $data;
		}

		return $result;
	}

	public function lg_delivery($data_order,$pg){

		$mid =  $pg['mallCode'];
		$oid =  $data_order['order_seq']; //주문번호
		$orderdate = date('YmdHis');

		$dlvtype = '03';
		$mertkey = $pg['merchantKey'];

		$dlvdate =  date('YmdHi');

		$this->load->model('exportmodel');
		$data_export = $this->exportmodel->get_export_for_order($data_order['order_seq']);
		$pg_val['dlv_invoice'] = $data_export[0]['delivery_number'];
		$dlvno =  $pg_val['dlv_invoice'];

		$compID["code0"] = "CJ";	//CJ GLS
		$compID["code1"] = "";		//DHL코리아
		$compID["code2"] = "KB";	//KGB택배
		$compID["code3"] = "";		//경동택배
		$compID["code4"] = "KE";	//대한통운
		$compID["code5"] = "";		//동부택배(훼밀리)
		$compID["code6"] = "LG";	//로젠택배
		$compID["code7"] = "PO";	//우체국택배
		$compID["code8"] = "HN";	//하나로택배
		$compID["code9"] = "HJ";	//한진택배
		$compID["code10"] = "HD";	//롯데택배
		$compID["code11"] = "";		//동원택배
		$compID["code12"] = "DS";	//대신택배
		$compID["code13"] = "";		//세덱스
		$compID["code14"] = "FE";	//동부익스프레스
		$compID["code15"] = "";		//천일택배
		$compID["code16"] = "";		//사가와택배
		$compID["code17"] = "IY";	//일양택배
		$compID["code18"] = "IN";	//이노지스
		$compID["code19"] = "";		//편의점택배
		$compID["code20"] = "";		//건영택배
		$compID["code21"] = "YC";	//엘로우캡

		$dlvcompcode = $compID[$data_export[0]['delivery_company_code']];

		/*
		대한통운 KE
		아주택배 AJ
		우체국택배 PO
		트라넷 TN
		롯데택배 HD
		Bell Express BE
		HTH SS
		KT로지스택배 KT
		일양로지스 IY
		하나로택배 HN
		우편등기 RP
		로젠택배 LG
		엘로우캡 YC
		이젠택배 EZ
		한진택배 HJ
		동부익스프레스 FE
		CJ GLS CJ
		KGB택배 KB
		SC로지스택배 SC
		이노지스택배 IN
		대신택배 DS
		*/

		// 송장번호 -와 공백 제거
		$dlvno = str_replace(array('-',' '),'',$dlvno);

		$hashdata = md5($mid.$oid.$dlvdate.$dlvcompcode.$dlvno.$mertkey);
		$service_url = "https://pgweb.uplus.co.kr/pg/wmp/mertadmin/jsp/escrow/rcvdlvinfo.jsp";

		$str_url = $service_url."?mid=$mid&oid=$oid&productid=$productid&orderdate=$orderdate&dlvtype=$dlvtype&rcvdate=$rcvdate&rcvname=$rcvname&rcvrelation=$rcvrelation&dlvdate=$dlvdate&dlvcompcode=$dlvcompcode&dlvno=$dlvno&dlvworker=$dlvworker&dlvworkertel=$dlvworkertel&hashdata=$hashdata";

		$ch = curl_init();

		curl_setopt ($ch, CURLOPT_URL, $str_url);
		curl_setopt ($ch, CURLOPT_COOKIEJAR, COOKIE_FILE_PATH);
		curl_setopt ($ch, CURLOPT_COOKIEFILE, COOKIE_FILE_PATH);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

		$fp = curl_exec ($ch);

		if(curl_errno($ch)){
				 //실패
		} else {
			if(trim($fp)=="OK") {
					//성공
			} else {
					//실패

			}
		}

	}
	public function kcp_delivery($data_order,$pg){
		setlocale(LC_CTYPE, 'ko_KR.euc-kr');

	    $this->load->model('ordermodel');
	    $order_data = $this->ordermodel->get_order($data_order['order_seq']);

	    $this->load->model('exportmodel');
	    $data_export = $this->exportmodel->get_export_for_order($data_order['order_seq']);
	    $pg_val['dlv_invoice'] = $data_export[0]['delivery_number'];

	    $arr_delivery = config_load('delivery_url');
	    $pg_val['mdelivery'] = $arr_delivery[$data_export[0]['delivery_company_code']]['company'];

	    $g_conf_site_cd   = $pg['mallCode'];
	    /* 테스트 3grptw1.zW0GSo4PQdaGvsF__ */
	    $g_conf_site_key  = $pg['merchantKey'];


	    $mod_type = 'STE1';
	    $tno = $order_data['pg_transaction_number'];
	    $_POST['deli_numb'] = $pg_val['dlv_invoice'];
	    $_POST['deli_corp'] = $pg_val['mdelivery'];

	    require_once ROOTPATH."pg/kcp/sample/pp_ax_hub_lib.php"; // library [수정불가]
	    $c_PayPlus = new C_PP_CLI;
	    $ordr_idxx = $data_order['order_seq'];

	    $g_conf_home_dir  = ROOTPATH."pg/kcp/";
	    $g_conf_gw_url    = $pg['mallCode']=='T0007' ? "testpaygw.kcp.co.kr" : "paygw.kcp.co.kr";

	    $g_conf_log_level = "3";           // 변경불가
	    $g_conf_gw_port   = "8090";        // 포트번호(변경불가)

	    $tran_cd = "00200000";
	    $cust_ip        = getenv( "REMOTE_ADDR"    ); // 요청 IP
	    $c_PayPlus->mf_set_modx_data( "tno",      $tno      );                              // KCP 원거래 거래번호
	    $c_PayPlus->mf_set_modx_data( "mod_type", $mod_type );                              // 원거래 변경 요청 종류
	    $c_PayPlus->mf_set_modx_data( "mod_ip",   $cust_ip  );                              // 변경 요청자 IP
	    $c_PayPlus->mf_set_modx_data( "mod_desc", $mod_desc );                              // 변경 사유

	    if ($mod_type == "STE1")                                                                 // 상태변경 타입이 [배송요청]인 경우
	    {
	         $c_PayPlus->mf_set_modx_data( "deli_numb",   $_POST[ "deli_numb" ] );   // 운송장 번호
	         $c_PayPlus->mf_set_modx_data( "deli_corp",   $_POST[ "deli_corp" ] );   // 택배 업체명
	    }

	    $c_PayPlus->mf_do_tx( $trace_no, $g_conf_home_dir, $g_conf_site_cd, "", $tran_cd, "",
	    $g_conf_gw_url, $g_conf_gw_port, "payplus_cli_slib", $ordr_idxx,
	    $cust_ip, "3" , 0, 0, $g_conf_key_dir, $g_conf_log_dir);

	    $res_cd  = $c_PayPlus->m_res_cd;  // 결과 코드
	    $res_msg = $c_PayPlus->m_res_msg; // 결과 메시지
	}
	public function allat_delivery($data_order,$pg){
	}
	public function kspay_delivery($data_order,$pg){
	}

	public function inicis_delivery($data_order,$pg)
	{
		$this->load->model('exportmodel');
		$arr_delivery = config_load('delivery_url');

		$data_item = $this->get_item($data_order['order_seq']);
		$cnt = count($data_item);

		$data_export = $this->exportmodel->get_export_for_order($data_order['order_seq']);
		$pg_val['dlv_goods'] = iconv('utf-8','euc-kr',$data_item[0]['goods_name']);
		if($cnt > 2) $pg_val['dlv_goods'] .= " 외 ".($cnt-1)."건";

		// 이니시스 배송등록 택배사와 쇼핑몰 택배사 매칭 2020-05-08
		$inicis_delivery = code_load('inicisDeliveryCompanyCode');
		$delivery_company = '';
		foreach($inicis_delivery as $compID){
			if($compID['codecd']==$data_export[0]['delivery_company_code']){
				$delivery_company = $compID['value'];
			}
		}
		// 미리 정의된 택배사가 아닌 경우 9999/기타 전송 (cf.https://manual.inicis.com/code/#escrowGls)
		if($delivery_company) {
			list($dlv_exname, $dlv_excode) = explode("|",$delivery_company);
		} else {
			$dlv_exname = $data_export[0]['mdelivery'];
			$dlv_excode = '9999';
		}

		$today = date('y-m-d');
		$nowtime = date('H:i:s');
		$pg_val['tid'] = $data_order['pg_transaction_number'];
		$pg_val['mid'] = $pg['escrowMallCode'];
		$pg_val['admin'] = $pg['merchantKey'];
		$pg_val['oid'] = $data_order['order_seq'];
		$pg_val['dlv_date'] = $today;
		$pg_val['dlv_time'] = $nowtime;
		$pg_val['dlv_invoice'] = $data_export[0]['delivery_number'];
		$pg_val['dlv_name'] = iconv('utf-8','euc-kr',$this->config_basic['shopName']);
		$pg_val['dlv_excode'] = $dlv_excode;
		$pg_val['dlv_exname'] = iconv('utf-8','euc-kr',$dlv_exname);
		$pg_val['dlv_invoiceday'] = $today.' '.$nowtime;
		$pg_val['dlv_sendname'] = iconv('utf-8','euc-kr',$this->config_basic['shopName']);
		$pg_val['dlv_sendpost'] = $this->config_basic['companyZipcode'];
		$pg_val['dlv_sendaddr1'] = iconv('utf-8','euc-kr',$this->config_basic['companyAddress']);
		$pg_val['dlv_sendaddr2'] = iconv('utf-8','euc-kr',$this->config_basic['companyAddressDetail']);
		$pg_val['dlv_sendtel'] = $this->config_basic['companyPhone'];
		$pg_val['dlv_recvname'] = iconv('utf-8','euc-kr',$data_order['recipient_user_name']);
		$pg_val['dlv_recvpost'] = $data_order['recipient_zipcode'];
		$pg_val['dlv_recvaddr'] = iconv('utf-8','euc-kr',$data_order['recipient_address'].' '.$data_order['recipient_address_detail']);
		$pg_val['dlv_recvtel'] = ($data_order['recipient_phone'])? $data_order['recipient_phone']:$data_order['recipient_cellphone'];
		$pg_val['price'] = $data_order['settleprice'];

		require_once(dirname(__FILE__)."/../../pg/inicis/libs/INILib.php");
		/***************************************
		 * 2. INIpay50 클래스의 인스턴스 생성 *
		 ***************************************/
		$iniescrow = new INIpay50;

		/*********************
		 * 3. 지불 정보 설정 *
		 *********************/
		$iniescrow->SetField("inipayhome", dirname(__FILE__)."/../../pg/inicis");      // 이니페이 홈디렉터리(상점수정 필요)
		$iniescrow->SetField("tid",$pg_val['tid']); // 거래아이디
		$iniescrow->SetField("mid",$pg_val['mid']); // 상점아이디
	    /**************************************************************************************************
	     * admin 은 키패스워드 변수명입니다. 수정하시면 안됩니다. 1111의 부분만 수정해서 사용하시기 바랍니다.
	     * 키패스워드는 상점관리자 페이지(https://iniweb.inicis.com)의 비밀번호가 아닙니다. 주의해 주시기 바랍니다.
	     * 키패스워드는 숫자 4자리로만 구성됩니다. 이 값은 키파일 발급시 결정됩니다.
	     * 키패스워드 값을 확인하시려면 상점측에 발급된 키파일 안의 readme.txt 파일을 참조해 주십시오.
	     **************************************************************************************************/
		$iniescrow->SetField("admin",$pg_val['admin']); // 키패스워드(상점아이디에 따라 변경)
		$iniescrow->SetField("type", "escrow"); 				                    // 고정 (절대 수정 불가)
		$iniescrow->SetField("escrowtype", "dlv"); 				                    // 고정 (절대 수정 불가)
		$iniescrow->SetField("dlv_ip", getenv("REMOTE_ADDR")); // 고정
		$iniescrow->SetField("debug","true"); // 로그모드("true"로 설정하면 상세한 로그가 생성됨)

		$iniescrow->SetField("oid",$pg_val['oid']);
		$iniescrow->SetField("soid","1");
		$iniescrow->SetField("dlv_date",$pg_val['dlv_date']);
		$iniescrow->SetField("dlv_time",$pg_val['dlv_time']);
		$iniescrow->SetField("dlv_report",'I');
		$iniescrow->SetField("dlv_invoice",$pg_val['dlv_invoice']); // 송장번호
		$iniescrow->SetField("dlv_name",$pg_val['dlv_name']); // 배송등록자

		$iniescrow->SetField("dlv_excode",$pg_val['dlv_excode']); // 배송 코드
		$iniescrow->SetField("dlv_exname",$pg_val['dlv_exname']); // 택배사 명
		$iniescrow->SetField("dlv_charge",'BH'); // 배송비 지급방법

		$iniescrow->SetField("dlv_invoiceday",$pg_val['dlv_invoiceday']); // 배송등록 확인일시
		$iniescrow->SetField("dlv_sendname",$pg_val['dlv_sendname']); // 송신자 이름
		$iniescrow->SetField("dlv_sendpost",$pg_val['dlv_sendpost']); // 송신자 우편번호
		$iniescrow->SetField("dlv_sendaddr1",$pg_val['dlv_sendaddr1']); // 송신자 주소1
		$iniescrow->SetField("dlv_sendaddr2",$pg_val['dlv_sendaddr2']); // 송신자 주소2
		$iniescrow->SetField("dlv_sendtel",$pg_val['dlv_sendtel']); // 송신자 전화번호

		$iniescrow->SetField("dlv_recvname",$pg_val['dlv_recvname']); // 수신자 이름
		$iniescrow->SetField("dlv_recvpost",$pg_val['dlv_recvpost']); // 수신자 우편번호
		$iniescrow->SetField("dlv_recvaddr",$pg_val['dlv_recvaddr']); // 수신자 주소
		$iniescrow->SetField("dlv_recvtel",$pg_val['dlv_recvtel']); // 수신자 전화번호

		$iniescrow->SetField("dlv_goodscode",$goodsCode); // 상품 코드
		$iniescrow->SetField("dlv_goods",$pg_val['dlv_goods']); // 상품명(필수)
		$iniescrow->SetField("dlv_goodscnt",$goodCnt); // 상품수량
		$iniescrow->SetField("price",$pg_val['price']); // 상품가격(필수)
		$iniescrow->SetField("dlv_reserved1",$reserved1);
		$iniescrow->SetField("dlv_reserved2",$reserved2);
		$iniescrow->SetField("dlv_reserved3",$reserved3);

		$iniescrow->SetField("pgn",$pgn);

		/*********************
		 * 3. 배송 등록 요청 *
		 *********************/
		$iniescrow->startAction();


		/**********************
		 * 4. 배송 등록  결과 *
		 **********************/
		 $tid        = $iniescrow->GetResult("tid"); 					// 거래번호
		 $resultCode = $iniescrow->GetResult("ResultCode");		// 결과코드 ("00"이면 지불 성공)
		 $resultMsg  = $iniescrow->GetResult("ResultMsg"); 			// 결과내용 (지불결과에 대한 설명)
		 $dlv_date   = $iniescrow->GetResult("DLV_Date");
		 $dlv_time   = $iniescrow->GetResult("DLV_Time");
	}

	public function kicc_delivery($data_order,$pg){
		$this->load->library('kicclib');

		// 거래구분 : 에스크로 변경
		$mgr_txtype = '61';

		// 에스크로 취소 시 변경세부구분
		$mgr_subtype	= 'ES07';	// 배송중

		//변경사유
		$mgr_msg = '에스크로 출고완료';
		$mgr_msg = iconv('utf-8', 'euc-kr', $mgr_msg);

		// 출고정보  : 운송장 번호 및 택배사 코드 번호
		$this->load->model('exportmodel');
		$data_export = $this->exportmodel->get_export_for_order($data_order['order_seq']);
		$pg_val['dlv_invoice'] = $data_export[0]['delivery_number'];
		$dlvno =  $pg_val['dlv_invoice'];

		$compIDs = code_load('kiccDeliveryCompanyCode');
		$dlvcompcode = 'DC13';
		foreach($compIDs as $compID){
			if($compID['codecd']==$data_export[0]['delivery_company_code']){
				$dlvcompcode = $compID['value'];
			}

		}

		$delivery_params = array();
		// 공통
		$delivery_params[$this->kicclib->params_prefix.'tr_cd'		] = '00201000';								//	요청구분	N	8	●	변경:00201000
		$delivery_params[$this->kicclib->params_prefix.'mall_id'		] = $pg['mallCode'];							//	가맹점 아이디
		$delivery_params['mgr_txtype'	] = $mgr_txtype;								//	거래구분	N	2	○	20:매입 31:부분매입취소(신용카드) 32:승인부분취소(신용카드) 33:부분취소(계좌이체) 40:즉시취소(승인/매입자동판단취소),  60:환불,  62:부분환불(가상계좌)
		$delivery_params['mgr_subtype'	] = $mgr_subtype;								//	변경세부구분									AN									4 									△									환불(60) 시 필수
		$delivery_params['org_cno'		] = $data_order['pg_transaction_number'];		//	원거래 고유번호									N									20 									○									PG 거래번호
		$delivery_params['mgr_msg'		] = $mgr_msg;									//	변경사유									AN									100									△
		$delivery_params['req_ip'		] = getenv( "REMOTE_ADDR"    );					//	요청자 IP									ANS									20 									○

		// 에스크로
		$delivery_params['deli_cd'		] = 'DE02';										//	배송구분 DE01 : 자가 DE02 : 택배      (배송요청 시, 필수)
		$delivery_params['deli_corp_cd'	] = $dlvcompcode;								//	택배사코드 참조 (배송요청 시, 필수)
		$delivery_params['deli_invoice'	] = $dlvno;										//	운송장번호 (배송요청 시, 필수)

		$kicc_result = $this->kicclib->callKiccModule($delivery_params);
	}

	public function insert_order_person()
	{
		$mode		= "cart";

		$this->load->model('personcartmodel');

		$payment = "";
		for($i=0; $i<count($_POST['payment']); $i++){
			$payment .= $_POST['payment'][$i]."|";
		}


		$insert_params['title'] 				= $_POST['title'];
		$insert_params['member_seq'] 			= $_POST['member_seq'];
		$insert_params['admin_memo'] 			= $_POST['admin_memo'];
		$insert_params['order_user_name'] 		= $_POST['order_user_name'];
		$insert_params['order_phone'] 			= $_POST['order_phone'][0]."-".$_POST['order_phone'][1]."-".$_POST['order_phone'][2];
		$insert_params['order_cellphone'] 		= $_POST['order_cellphone'][0]."-".$_POST['order_cellphone'][1]."-".$_POST['order_cellphone'][2];
		$insert_params['order_email'] 			= $_POST['order_email'];
		$insert_params['enuri'] 						= $_POST['enuri'];
		$insert_params['pay_type'] 				= $payment;
		$insert_params['total_price']				= (str_replace(",", "", $_POST['total_price_temp'])+str_replace(",", "", $_POST['enuri']));
		$insert_params['admin_memo'] 			= $_POST['admin_memo'];
		$insert_params['use_reserve']			= $_POST['use_reserve'];
		$insert_params['reserve_limit']			= $_POST['reserve_limit'];
		$insert_params['regist_date'] 			= date("Y-m-d H:i:s");


		$this->db->insert('fm_person', $insert_params);
		$person_seq = $this->db->insert_id();


		return $person_seq;
	}


	/* 주문서의 과세금액, 비과세금액 반환, 배송비 반환 */
	public function get_order_prices_for_tax($order_seq,$order='',$tax_invoice=false, $refund_type = false){

		$order_cfg = ($this->cfg_order) ? $this->cfg_order : config_load('order');

		$tax_price				= 0;			// 과세 상품 금액
		$exempt_price			= 0;			// 비과세 금액

		$shipping_cost			= 0;

		if(!$order) $order = $this->get_order($order_seq);
		$items = $this->get_item($order_seq);

		// 환불 정보 추출 :: 2017-08-23 lwh
		$tot_refund = [];
		$this->load->model('refundmodel');
		$data_refund = $this->refundmodel->get_refund_item_for_order($order_seq);
		if ($data_refund){
			foreach($data_refund as $k => $refund){
				// 환불완료된 내역만 추가
				$sum_refund = true;
				if($refund_type!=false && ($refund_type=="all_order" || $refund['status']!=$refund_type)){
					$sum_refund = false;
				}
				if($sum_refund){
					$tot_refund[$refund['tax']]['price']	+= $refund['refund_goods_price'];
					$tot_refund['delivery']	+= $refund['refund_delivery_price'];
					$tmp_refund[$refund['refund_code']]['emoney']	= $refund['refund_emoney'];
					$tmp_refund[$refund['refund_code']]['cash']		= $refund['refund_cash'];
				}
			}

			foreach($tmp_refund as $data){
				$tot_refund['emoney']	+= $data['emoney'];
				$tot_refund['cash']		+= $data['cash'];
			}
		}

		$enuri					= get_cutting_price($order['enuri']);
		$cash					= get_cutting_price($order['cash'] - $tot_refund['cash']);
		$emoney					= $order['emoney'] - $tot_refund['emoney'];
		$settle_price			= get_cutting_price($order['settleprice']);

		$tax_sale				= 0;			// 과세 할인
		$exempt_sale			= $enuri;		// 비과세 할인(에누리)
		$exempt_in_price		= 0;			// 비과세 포함금액(사용한 마일리지/예치금, 단 세금계산서 발행시 포함설정일때만)
		$use_emoney_price		= 0;			// 사용한 마일리지/예치금 합


		//상품명 생성
		$item_name = $items[0]['goods_name'];
		if( (count($items) - 1) > 0){
			$item_name .= " 외 " . ( count($items)-1 ) . "건";
		}

		// 사용한 마일리지/예치금
		$use_emoney_price		+= $emoney;
		$use_emoney_price		+= $cash;

		// 세금계산서 발행 시 마일리지/예치금 포함 설정
		if($tax_invoice === true){
			if($order_cfg["sale_reserve_yn"] == 'Y'){
				$exempt_in_price	+= $emoney;
			}
			if($order_cfg["sale_emoney_yn"] == 'Y'){
				$exempt_in_price	+= $cash;
			}
		}

		$shipping_cost					= array();
		$shipping_delivery_tax_cnt		= 0;	//과세 상품 건수
		$shipping_delivery_exempt_cnt	= 0;	//비과세 상품 건수

		foreach($items as $item){

			$options 	= $this->get_option_for_item($item['item_seq']);
			$suboptions = $this->get_suboption_for_item($item['item_seq']);

			if($options) foreach($options as $data){
				if($item['tax'] == "tax"){
					// 과세
					$shipping_delivery_tax_cnt++;
				}else{
					// 비과세 상품 금액 (상품금액-할인금액)
					$sale_total = ($data['member_sale']*$data['ea'])
					+$data['event_sale']	// 이벤트 할인으로 인한 면세금액이 줄어듬이 누락되어 있었음 by hed
					+$data['coupon_sale']
					+$data['promotion_code_sale']+$data['fblike_sale']
					+$data['mobile_sale']+$data['referer_sale'];
					$exempt_price += ($data['price']*$data['ea'])-$sale_total;
					$shipping_delivery_exempt_cnt++;
				}
			}

			if($suboptions) foreach($suboptions as $data){

				// 비과세 상품 금액
				if($item['tax']!="tax"){
					$exempt_price += (($data['price']-$data['member_sale'])*$data['ea']);
				}
			}
		}

		# 기본배송상품 배송비
		if ($order['international'] == "international") {
			$shipping_delivery_tax_cost = $order['international_cost'];
		} else {
			$shipping_delivery_tax_cost = $order['shipping_cost'];
		}

		# 과세상품이 1개 이상일때 배송비는 과세
		if($shipping_delivery_tax_cnt > 0){
			$shipping_cost['tax'] = $shipping_delivery_tax_cost;
		# 과세상품이 0개이고, 비과세 상품이 1개 이상일때 배송비는 비과세
		}elseif($shipping_delivery_tax_cnt == 0 && $shipping_delivery_exempt_cnt > 0){
			$shipping_cost['exempt'] = $shipping_delivery_tax_cost;
		}

		if($exempt_price){
			# 총 비과세 상품가 = (비과세 상품가 + 비과세 배송비) - 마일리지/예치금 사용액
			// 이미 차감된 에누리, 마일리지, 예치금 중복차감 방지 2017-07-06 lkh
			//$exempt_price	+= array_sum($shipping_cost['exempt']) - $use_emoney_price - $exempt_sale;
			$exempt_price	+= $shipping_cost['exempt'];
			# 총 과세 상품가 = 총 결제액 - 비과세액 - 과세 배송비
			$tax_price		= $settle_price - $exempt_price - $shipping_cost['tax'];
			# 계산서 발행시
			$exempt_price  += $exempt_in_price;
		}else{
			$exempt_price	= 0;
			// 이미 차감된 에누리, 마일리지, 예치금 중복차감 방지 2017-07-06 lkh
			//$tax_price		= $settle_price - array_sum($shipping_cost['tax']) - $use_emoney_price - $exempt_sale;
			$tax_price		= $settle_price - $shipping_cost['tax'];
			$tax_price		+= $exempt_in_price;
		}

		// 환불금액 제외 :: 2017-08-25 lwh
		// * 과세금액
		$tax_price		= $tax_price - $tot_refund['tax']['price'];
		// * 비과세금액
		$exempt_price	= $exempt_price - $tot_refund['exempt']['price'];
		// * 과세 배송비
		$tax_ship_cost	= 0;
		if($shipping_cost['tax'] > 0) {
			$tax_ship_cost = $shipping_cost['tax'] - $tot_refund['delivery'];
		}
		// * 과세금액이 음수이면 아직 에누리, 마일리지, 예치금 제외가 남았다는 의미이므로 과세 배송비에서도 차감해줘야함
		if( $tax_price < 0 ) {
			$tax_ship_cost 	= $tax_ship_cost + $tax_price;
			$tax_price		= 0;
		}

		$debug_log = array();
		$debug_log[] = "settle_price(마일리지/예치금 제외금액)\t : ".$settle_price;
		$debug_log[] = "tax_price(과세금액)\t\t\t : ".$tax_price;
		$debug_log[] = "exempt_price(비과세금액)\t\t : ".$exempt_price;
		$debug_log[] = "use_emoney_price(마일리지/예치금 사용금액) : ".$use_emoney_price;
		$debug_log[] = "exempt_in_price(마일리지/예치금 포함금액) : ".$exempt_in_price;
		$debug_log[] = "sum price(비과세+과세+과세배송비)\t : ".$exempt_price + $tax_price + $tax_ship_cost;
		$debug_log[] = "tax shipping(과세배송비)\t\t : ".$tax_ship_cost;

		$result = array(
			'tax'				=> $tax_price,
			'exempt'			=> $exempt_price,
			'shipping_cost'		=> $tax_ship_cost,
			'tax_sale'			=> $tax_sale,
			'exempt_sale'		=> $exempt_sale,
			'exempt_in_price'	=> $exempt_in_price,
			'goods_name'		=> $item_name,
			'tax_goods_cnt'		=> $shipping_delivery_tax_cnt
		);
		return $result;
	}


	/* 주문서의 과세금액, 부가세, 비과세금액 반환 (사용안하는 듯? 호출되는 곳이 없음.) */
	public function get_order_tax_prices($order_seq){

		$exempt_chk = 0;			// 비과세상품 종수
		$exempt_price = 0;			// 비과세상품 금액
		$exempt_shipping_price = 0; // 비과세상품 배송비

		$order = $this->ordermodel->get_order($order_seq);
		$items = $this->ordermodel->get_item($order_seq);

		foreach($items as $key=>$item){
			$options 	= $this->ordermodel->get_option_for_item($item['item_seq']);
			$suboptions = $this->ordermodel->get_suboption_for_item($item['item_seq']);

			if($item['tax']!="tax"){

				if($options) foreach($options as $k => $data){
					$exempt_price += ($data['price']*$data['ea']);
				}

				if($suboptions) foreach($suboptions as $k => $data){
					$exempt_price += ($data['price']*$data['ea']);
				}

				$exempt_shipping_price += $item['basic_shipping_cost']+$item['add_shipping_cost'];
				$exempt_chk++;
			}
		}

		if(count($items)==$exempt_chk){
			$tax_type = "exempt";
		}else if($exempt_chk == 0){
			$tax_type = "tax";
		}else{
			$tax_type = "mix";
		}

		### TAX : EXEMPT
		$exempt_price	= $exempt_price + $exempt_shipping_price;
		if($tax_type=='mix'){
			$sum_price		= $order['settleprice']-$exempt_price;
			$tax_price		= round($sum_price/1.1);
			$comm_tax_mny	= $tax_price;
			$comm_vat_mny	= $sum_price - $tax_price;
			$comm_free_mny	= $exempt_price;
		}else if($tax_type=='exempt'){
			$comm_tax_mny	= 0;
			$comm_vat_mny	= 0;
			$comm_free_mny	= $order['settleprice'];
		}else{
			$tax_price		= round($order['settleprice']/1.1);
			$comm_tax_mny	= $tax_price;
			$comm_vat_mny	= $order['settleprice'] - $tax_price;
			$comm_free_mny	= 0;
		}

		return array(
			'comm_tax_mny' => $comm_tax_mny,	// 과세 금액
			'comm_vat_mny' => $comm_vat_mny,	// 부가세
			'comm_free_mny' => $comm_free_mny,	// 비과세 금액
		);

	}

	/* 특정 주문의 반품으로 인해  생성된 맞교환 주문번호 반환 */
	public function get_child_order_seq($order_seq){
		$result = array();
		$query = "select * from fm_order where orign_order_seq=?";
		$query = $this->db->query($query,array($order_seq));
		foreach($query->result_array() as $data){
			$result[] = $data['order_seq'];
		}
		return $result;
	}

	// 배송정보 추출 :: 2016-09-29 lwh
	public function get_seq_for_order_shipping($shipping_seq){
		$result = array();
		$this->db->where('shipping_seq',$shipping_seq);
		$query	= $this->db->get("fm_order_shipping");
		$result	= $query->row_array();

		return $result;
	}

	public function get_shipping($order_seq,$shipping_provider_seq=null){
		$result = array();
		$this->db->where('order_seq',$order_seq);
		if($shipping_provider_seq)	$this->db->where('provider_seq',$shipping_provider_seq);
		$this->db->order_by('shipping_seq','asc');
		$query = $this->db->get("fm_order_shipping");
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	// 주문 배송지별 상품 가져오기
	public function get_shipping_item($order_seq, $shipping_seq){
		$query = "select i.*,p.*,
		(select purchase_goods_name from fm_goods where goods_seq = i.goods_seq) as purchase_goods_name,
		ifnull((select sum(goods_shipping_cost) from fm_order_shipping_item where order_item_seq=i.item_seq AND shipping_seq=?),0) as goods_shipping_cost
		from fm_order_item p
		inner join fm_order_item i on (p.order_seq=i.order_seq and p.order_item_seq=i.item_seq)
		where p.order_seq=? and p.shipping_seq=?
		order by p.shipping_seq asc, p.shipping_item_seq asc
		";//(select goods_type from fm_goods where goods_seq = i.goods_seq) as goods_type,
		$query = $this->db->query($query,array($shipping_seq,$order_seq,$shipping_seq));
		foreach($query->result_array() as $item){

			$item['shipping_item_option'] = $this->get_shipping_item_options($item['shipping_item_seq']);
			//$item['shipping_item_suboption'] = $this->get_shipping_item_suboptions($item['shipping_item_seq']);
			if($item['shipping_item_option']) foreach($item['shipping_item_option'] as $k=>$option){
				$item['shipping_item_option'][$k]['inputs']	= $this->get_input_for_option($option['item_seq'], $option['item_option_seq']);
				$item['shipping_item_option'][$k]['shipping_item_suboption'] = $this->get_suboption_for_option_by_shipping($option['item_seq'], $option['item_option_seq'], $option['shipping_seq']);
				$item['tot_goods_cnt']		+= count($item['shipping_item_option'][$k]['shipping_item_suboption']) + 1;
			}

			$result[] = $item;
		}
		return $result;
	}

	// 배송지별 상품 옵션 출고완료 개수 반환
	public function get_option_export_complete($order_seq,$shipping_provider_seq,$order_item_seq,$order_item_option_seq,$export_code=''){

		if(preg_match('/^B/', $export_code)){
			$join_on = "(a.bundle_export_code=b.bundle_export_code)";
		}else{
			$join_on = "(a.export_code=b.export_code)";
		}
		$query = $this->db->query("
			select ifnull(sum(b.ea),0) as complete_ea
			from fm_goods_export as a
			inner join fm_goods_export_item b on ".$join_on."
			where a.order_seq=? and b.item_seq=? and b.option_seq=?
		",array($order_seq,$order_item_seq,$order_item_option_seq));
		$result = $query->row_array();
		return $result['complete_ea'];
	}

	// 배송지별 상품 서브옵션 출고완료 개수 반환
	public function get_suboption_export_complete($order_seq,$shipping_provider_seq,$order_item_seq,$order_item_suboption_seq){

		if(preg_match('/^B/', $export_code)){
			$join_on = "(a.bundle_export_code=b.bundle_export_code)";
		}else{
			$join_on = "(a.export_code=b.export_code)";
		}
		$query = $this->db->query("
			select ifnull(sum(b.ea),0) as complete_ea
			from fm_goods_export as a
			inner join fm_goods_export_item b on ".$join_on."
			where a.order_seq=? and b.item_seq=? and b.suboption_seq=?
		",array($order_seq,$order_item_seq,$order_item_suboption_seq));
		$result = $query->row_array();
		return $result['complete_ea'];
	}

	# 주문리스트 검색 필드 where절 정리
	public function get_order_catalog_search_field($_PARAM = array('list')){
		## -------------------------------------------------------------------------------------------
		## 조회 기준
		if( $_PARAM['pagemode'] == "company_catalog"){
			$inquiry_type = "goods_shipping";			// 상품상태기준(배송책임)
		}elseif(defined('__SELLERADMIN__') === true ){
			$inquiry_type = "goods_provider";			// 상품상태기준(입점사)
		}else{
			$inquiry_type = "order";	//주문상태기준
		}

		$where				= array();		//공통 검색(fm_member)
		$where_order		= array();		//주문 검색(fm_order)
		$where_goods		= array();		//상품 검색(fm_order_item,fm_order_shipping)
		$where_option		= array();		//상품 검색(fm_order_item_option)

		## 2012-08-10
		if($_PARAM['mode']=='bank'){
			$_PARAM['regist_date'][0] = date("Y-m-d", mktime(0,0,0,date("m")-1, date("d"), date("Y")));
			$_PARAM['regist_date'][1] = date('Y-m-d');
			$_PARAM['chk_step'][15] = 1;
			$where_order[] = " ord.settleprice >= '".$_PARAM['sprice']."' ";
			$where_order[] = " ord.settleprice <= '".$_PARAM['eprice']."' ";
		}
		## -------------------------------------------------------------------------------------------
		# 검색필드 정리
			if( $_PARAM['keyword'] ){
				if($_PARAM['body_order_search_type']) $_PARAM['search_type'] = $_PARAM['body_order_search_type'];

				$keyword_type	= preg_replace("/[^a-z_]/i","",trim($_PARAM['search_type']));
				$keyword		= str_replace("'","\'",trim($_PARAM['keyword']));

				if	($keyword_type == 'all')	$keyword_type	= '';

				if(preg_match('/^([0-9]+)$/',$keyword)){
					$add_goodsseq_where = " OR goods_seq = '" . $keyword . "' ";
				}else{
					$add_goodsseq_where = "";
				}

				$arr_field = array(
					'order_seq'			=> 'ord.order_seq',
					'npay_order_id'		=> 'ord.npay_order_id',
					'order_user_name'	=> 'ord.order_user_name',
					'depositor'			=> 'ord.depositor',
					'userid'			=> 'mem.userid',
					'order_cellphone'	=> 'ord.order_cellphone',
					'order_email'		=> 'ord.order_email',
					'goods_seq'			=> 'orditm.goods_seq'
				);
			}
			# -------------------------------------------------------------------------------------------
			# 유입경로 그룹
			if(!$referer_list && $_PARAM['referer']){
				$this->load->model('statsmodel');
				$referer_group	= array();
				$referer_list	= $this->statsmodel->get_referer_grouplist();
				foreach($referer_list as $list){
					$referer_group[$list['referer_group_cd']] = $list['referer_group_name'];
				}
			}
		## ==============================================================================================
		# 공통 검색
			# -------------------------------------------------------------------------------------------
			# 주문일
			$date_field = $_PARAM['date_field'] ? $_PARAM['date_field'] : 'regist_date';
			if($_PARAM['regist_date'][0]){
				$where_order[]	= "ord.".$date_field." >= '".$_PARAM['regist_date'][0]." 00:00:00'";
			}
			if($_PARAM['regist_date'][1]){
				$where_order[]	= "ord.".$date_field." <= '".$_PARAM['regist_date'][1]." 23:59:59'";
			}
			# -------------------------------------------------------------------------------------------
			# 검색시간 검색 후 들어온 데이터를 무시 :: 2015-08-05 lwh
			if( $_PARAM['searchTime'] ){
				$where[] = "ord.regist_date <= '" . $_PARAM['searchTime'] . "'";
			}
			# -------------------------------------------------------------------------------------------
			# 회원검색
			if($_PARAM['member_seq']){
				$where_order[]	= "ord.member_seq = '".$this->db->escape_str($_PARAM['member_seq'])."'";
			}
			# -------------------------------------------------------------------------------------------
			# 주문번호검색
			if($_PARAM['order_seq'] && !$_PARAM['ajaxCall']){
				$where_order[]	= "ord.order_seq = '".$this->db->escape_str($_PARAM['order_seq'])."'";
			}
			# -------------------------------------------------------------------------------------------
			# 합포장
			if($_PARAM['chk_bundle_yn']){
				$where_order[] = "ord.bundle_yn = 'y'";
			}
			# -------------------------------------------------------------------------------------------
			# 주문삭제여부
			$where_order[] = "ord.hidden = 'N'";
			# -------------------------------------------------------------------------------------------
			# 주문환경
			if( $_PARAM['sitetype'] ){
				$where_order[] = "ord.sitetype in('".implode("','",$_PARAM['sitetype'])."')";
			}
			# -------------------------------------------------------------------------------------------
			# 주문유형
			if( $_PARAM['ordertype']){
				unset($arr);
				foreach($_PARAM['ordertype'] as $data){
					if($data == "personal"){
						$arr[] = "ifnull(ord.person_seq,0) > 0";
					}
					if($data == "admin"){
						$arr[] = "ifnull(ord.admin_order,'') != ''";
					}
					if($data == "change"){
						$arr[] = "ifnull(ord.orign_order_seq,'') != ''";
					}
					if($data == "present") {
						$arr[] = "ifnull(ord.label,'') = 'present'";
					}
				}
				$where_order[] = "(".implode(' OR ',$arr).")";
			}
			# -------------------------------------------------------------------------------------------
			# 결제사(pg)
			if( $_PARAM['pg'] && $_PARAM['allpg'] != 'y' ){
				$bank_where = "payment != 'bank'";
				unset($arr,$arr_pg);
				foreach($_PARAM['pg'] as $pg){
					if($pg == "normal"){ //일반pg사
						$arr[] = "((ord.pg = '' and payment != 'bank') or ord.pg in('inicis','kcp','kspay','allat','lg'))";
					}else{
						$arr_pg[] = $pg;
					}
				}
				if($arr_pg){
					$arr[] = "ord.pg in('".implode("','",$arr_pg)."')";
				}
				$where_order[] = "(".implode(' OR ',$arr).")";
			}
			# -------------------------------------------------------------------------------------------
			# 결제수단
			if( $_PARAM['payment']  && $_PARAM['allpayment'] != 'y' ){
				$bank_where = "payment != 'bank'";
				unset($arr,$arr_payment);
				foreach($_PARAM['payment'] as $data){
					if( in_array($data,array('virtual','account')) ){
						$arr[] = "escrow_".$data;
						$arr[] = $data;
					}else{
						$arr[] = $data;
					}
				}
				$where_order[] = "ord.payment in ('".implode("','",$arr)."')";
			}
			if($bank_where) {
				if ( !in_array('bank', $_PARAM['payment'] ) ) $where_order[] = $bank_where;
			}
			# -------------------------------------------------------------------------------------------
			# 주문유입(referer)
			if	($_PARAM['referer'] && $_PARAM['allreferer'] != 'y'){
				unset($arr);
				foreach($_PARAM['referer'] as $data){
					if($referer_group[$data] == "네이버페이"){
						$arr[]	= '네이버페이';
						$arr[]	= '체크아웃';
					}else if($referer_group[$data] == "" && $data == "etc") {
						$arr[]	= '기타';
					}else{
						$arr[]	= $referer_group[$data];
					}
				}
				if($arr) $where_order[] = "(IF(rg.referer_group_no>0, rg.referer_group_name, IF(LENGTH(ord.referer)>0,'기타','직접입력'))) in ('".implode("','",$arr)."')";
			}
			# -------------------------------------------------------------------------------------------
			# 중요주문
			if( $_PARAM['important'] ){
				foreach($_PARAM['important'] as $important){ $importants[] = "'".$important."'"; }
				$where_order[] = "ord.important in(".implode(', ',$importants).")";
			}
			# -------------------------------------------------------------------------------------------
			# 유입매체(X)
			if( $_PARAM['marketplace'] ){
				unset($arr);
				foreach($_PARAM['marketplace'] as $key => $data){
					if($key == 'etc'){
						foreach($sitemarketplaceloop as $marketplace => $tmp){
							if($marketplace != 'etc') $where_marketplace[] = "ord.marketplace != '$marketplace'";
						}
						if($where_marketplace){
							$arr[] = "(".implode(' AND ',$where_marketplace).")";
						}
						$arr[] = "ord.marketplace is null";
					}else{
						$arr[] = "ord.marketplace = '".$key."'";
					}
				}
				$where_order[] = "(".implode(' OR ',$arr).")";
			}

			# -------------------------------------------------------------------------------------------
			# 맞교환(X)
			if( $_PARAM['search_change'] ){
				$where_order[] = "ord.orign_order_seq !=''";
			}

			# -------------------------------------------------------------------------------------------
			# Shoplinker 2014-05-29
			/* 샵링커 검색 제거
			if($_PARAM['linkage_mall_code'] || $_PARAM['not_linkage_order'] || $_PARAM['etc_linkage_order']){
				$arr = array();

				if($_PARAM['not_linkage_order']){
					$arr[] = "(linkage_mall_code is null or linkage_mall_code = '')";
				}
				if($_PARAM['linkage_mall_code']){
					$arr[] = "linkage_mall_code in ('".implode("','",$_PARAM['linkage_mall_code'])."')";
				}
				if($_PARAM['etc_linkage_order']){
					$this->load->model('openmarketmodel');
					$linkage_malldata		= $this->openmarketmodel->get_linkage_mall();
					$linkage_malldata		= $this->openmarketmodel->sort_linkage_mall($linkage_malldata);
					$search_mall_code = array();
					foreach($linkage_malldata as $k => $data){
						if	($data['default_yn'] == 'Y'){
							$search_mall_code[] = $data['mall_code'];
						}
					}
					for($i=0;$i<count($search_mall_code);$i++){
						if($i<10) unset($search_mall_code[$i]);
					}
					$arr[] = "linkage_mall_code in ('".implode("','",$search_mall_code)."')";
				}

				$where_order[] = "(".implode(' OR ',$arr).")";
			}
			if($_PARAM['linkage_mall_order_id']){
				$where[] = "ord.linkage_mall_order_id in ('".implode("','",$_PARAM['linkage_mall_order_id'])."')";
			} */

		# -------------------------------------------------------------------------------------------
		# 오픈마켓 주문서 검색 2017-04-03

		if (isset($_PARAM['selectMarkets']) === true && $_PARAM['allselectMarkets'] != 'y') {
			$connectorMarket	= array_unique($_PARAM['selectMarkets']);
			$newMarketArray		= array();
			foreach ((array)$connectorMarket as $marketCode) {
				$marketCode		= trim($marketCode);
				if (strlen($marketCode) > 1)
					$newMarketArray[]	= $marketCode;
			}


			$marketCnt	= count($newMarketArray);

			if ($marketCnt > 0) {
				$this->load->library('Connector');
				$connector		= $this->connector::getInstance();
				$marketList		= $connector->getAllMarkets(true);

				$justConnector	= true;
				if (in_array('NOT', $newMarketArray) === true) {
					$justConnector	= false;
					$notConnector	= "(ord.linkage_id is null OR ord.linkage_id = '')";
					unset($newMarketArray[array_search('NOT', $newMarketArray)]);
					$newMarketArray	= array_values($newMarketArray);
					$marketCnt--;
				} else {
					$where_order[]	="ord.linkage_id = 'connector'";
				}

				if ($marketCnt < count($marketList)) {
					if ($marketCnt == 1) {
						if ($justConnector === true)
							$where_order[]	= "ord.linkage_mall_code = '{$newMarketArray[0]}'";
						else
							$where_order[]	= "({$notConnector} OR (ord.linkage_id = 'connector' AND ord.linkage_mall_code = '{$newMarketArray[0]}'))";
					} else {

						$marketIn		= implode("','", $newMarketArray);
						if ($justConnector === true) {
							$where_order[]	= "ord.linkage_mall_code IN ('{$marketIn}')";
						} else if(count($newMarketArray) > 0){
							$where_order[]	= "({$notConnector} OR (ord.linkage_id = 'connector' AND ord.linkage_mall_code IN ('{$marketIn}')))";
						} else {
							$where_order[]	= "{$notConnector}";
						}

					}
				}
			}
		}

		## ==============================================================================================
		## 주문상태기준 검색
		if($inquiry_type == "order"){

			# -------------------------------------------------------------------------------------------
			# 배송책임기준의 입점사 검색(본사(본사위탁배송포함), 입점사(본사위탁배송제외)
			if( !empty($_PARAM['shipping_provider_seq']) ){
				if( $_PARAM['shipping_provider_seq'] == 999999999999 ){
					$where_order[] = "EXISTS (select oi.order_seq from fm_order_item as oi left join fm_order_shipping as os on oi.order_seq=os.order_seq and oi.shipping_seq=os.shipping_seq where oi.order_seq=ord.order_seq and os.provider_seq !='1' limit 1)";
				}else{
					$where_order[] = "EXISTS (select oi.order_seq from fm_order_item as oi left join fm_order_shipping as os on oi.order_seq=os.order_seq and oi.shipping_seq=os.shipping_seq where oi.order_seq=ord.order_seq and os.provider_seq='".$_PARAM['shipping_provider_seq']."' limit 1)";
				}
			}
			# -------------------------------------------------------------------------------------------
			# 주문상태  (주문상태기준, 상품상태기준)
			if( $_PARAM['chk_step'] ){
				unset($arr);
				foreach($_PARAM['chk_step'] as $key => $data){
					$arr[] = "ord.step = '".$key."'";
					if( $key == 25 ) $settle_yn = 'y';
				}
				$where_order[] = "(".implode(' OR ',$arr).")";
			}
			# -------------------------------------------------------------------------------------------
			# 검색어
			if( $_PARAM['keyword'] ){
				if($keyword_type){
					if($keyword_type == 'recipient_user_name'){
						$where_order[] = "EXISTS (SELECT order_seq FROM fm_order_shipping WHERE order_seq = ord.order_seq and (recipient_user_name LIKE '%" . $keyword . "%'))";
					}else{
						$where_order[] = $arr_field[$keyword_type]." = '" . $keyword . "'";
					}
				// 검색어가 출고번호 일 경우
				}else if(preg_match('/^D([0-9]{14})$/',$keyword)){
					$where_order[] = "ord.order_seq = (SELECT order_seq FROM fm_goods_export WHERE export_code = '" . $keyword . "')";
				// 검색어가 반품번호 일 경우
				}else if(preg_match('/^R([0-9]{12})$/',$keyword)){
					$where_order[] = "ord.order_seq = (SELECT order_seq FROM fm_order_return WHERE return_code = '" . $keyword . "')";
				// 검색어가 환불번호 일 경우
				}else if(preg_match('/^C([0-9]{12})$/',$keyword)){
					$where_order[] = "ord.order_seq = (SELECT order_seq FROM fm_order_refund WHERE refund_code = '" . $keyword . "')";
				}else{

					$where_order[] = "
						(
							ifnull(ord.order_seq,'') = '" . $keyword . "' OR
							ifnull(ord.npay_order_id,'') LIKE '%" . $keyword . "%' OR
							ifnull(mem.user_name,'') like '%" . $keyword . "%' OR
							ifnull(bus.bname,'') LIKE '%" . $keyword . "%' OR
							ifnull(ord.order_user_name,'')  LIKE '%" . $keyword . "%' OR
							ifnull(ord.depositor,'') LIKE '%" . $keyword . "%' OR
							ifnull(ord.order_email,'') LIKE '%" . $keyword . "%' OR
							INSTR(replace(ord.order_phone,'-',''), '" . str_replace("-","",$keyword) . "') OR
							INSTR(replace(ord.order_cellphone,'-',''), '" . str_replace("-","",$keyword) . "') OR
							INSTR(replace(ord.recipient_phone,'-',''), '" . str_replace("-","",$keyword) . "') OR
							INSTR(replace(ord.recipient_cellphone,'-',''), '" . str_replace("-","",$keyword) . "') OR
							ifnull(mem.userid,'') LIKE '%" . $keyword . "%' OR
							ifnull(ord.recipient_user_name,'') LIKE '%" . $keyword . "%' OR
							ifnull(ord.linkage_mall_order_id,'') LIKE '%" . $keyword . "%' OR
							ifnull(mem.sns_n,'') LIKE '".substr($keyword,0,-2)."%' OR
							EXISTS (
								SELECT
									order_seq
								FROM fm_order_item WHERE order_seq = ord.order_seq and (
									ifnull(goods_name,'') LIKE '%" . $keyword . "%' OR
									ifnull(goods_code,'') LIKE '%" . $keyword . "%'
									" . $add_goodsseq_where . "
									)
							) OR
							EXISTS (
								SELECT order_seq FROM fm_goods_export WHERE order_seq = ord.order_seq and (
									ifnull(delivery_number,'') LIKE '%" . $keyword . "%' OR
									ifnull(international_delivery_no,'') LIKE '%" . $keyword . "%' OR
									ifnull(recipient_user_name,'') LIKE '%" . $keyword . "%')
							)
						)
						";
				}
			}
			# -------------------------------------------------------------------------------------------
			# 주문상품
			if( $_PARAM['goodstype'] ){
				unset($arr);
				foreach($_PARAM['goodstype'] as $key => $data){
					switch($data){
						case "ticket":		# 티켓상품
							$arr[] = " EXISTS (select 'o' from fm_order_item where order_seq = ord.order_seq and goods_kind = 'coupon' limit 1) ";
						break;
						case "gift":		# 사은품
							$arr[] = " EXISTS (select 'o' from fm_order_item where order_seq = ord.order_seq and goods_type = 'gift' limit 1) ";
						break;
						case "package":		# 패키지/복합상품
							$arr[] = " (EXISTS (select 'o' from fm_order_item_option where order_seq = ord.order_seq and package_yn = 'y' limit 1) OR EXISTS (select 'o' from fm_order_item_suboption where order_seq = ord.order_seq and package_yn = 'y' limit 1)) ";
						break;
						case "adult":		# 성인상품
							$arr[] = " EXISTS (select 'o' from fm_order_item where order_seq = ord.order_seq and adult_goods = 'Y' limit 1) ";
						break;
						case "withdraw":	# 청약철회불가상품
							$arr[] = " EXISTS (select 'o' from fm_order_item as oi left join fm_goods as g on oi.goods_seq=g.goods_seq where oi.order_seq = ord.order_seq and g.cancel_type='1' limit 1) ";
						break;
						case "international_shipping":	# 해외배송여부(구매대행여부)
							$arr[] = " EXISTS (select 'o' from fm_order_item where order_seq = ord.order_seq and option_international_shipping_status = 'y' limit 1) ";
						break;
						case "reserve":		# 예약발송상품
							$arr[] = " EXISTS (select 'o' from fm_order_item where order_seq = ord.order_seq and reservation_ship = 'y' limit 1) ";
						break;
					}
				}
				if($arr) $where_order[] = "(".implode(' OR ',$arr).")";
			}
			# -------------------------------------------------------------------------------------------
			# 배송국가(대한민국,해외)
			if( $_PARAM['nation'] ){
				unset($arr,$where_nation);
				// 배송방법
				foreach($_PARAM['nation'] as $nation){

					$where_nation = "(ord.international = '".$nation."'";

					if( $_PARAM['shipping_set_code'][$nation] ){
						unset($arr);
						foreach($_PARAM['shipping_set_code'][$nation] as $shipping_set_code){ $arr[] = "'".$shipping_set_code."'"; }

						$where_nation .= " and EXISTS (select oi.order_seq from fm_order_item as oi left join fm_order_shipping as os on oi.order_seq=os.order_seq and oi.shipping_seq=os.shipping_seq where oi.order_seq=ord.order_seq and os.shipping_method in(".implode(",",$arr).") limit 1)";
					}
					$where_nation .= ")";
					$where_order[] = $where_nation;
				}
			}

			# -------------------------------------------------------------------------------------------
			# 배송 관계 없는 문자/이메일 티켓(검색조건) //20170915 ldb (검색창에는 기존부터 있었음)
			if($_PARAM['shipping_set_code']['ticket'] == 'ticket') {
				//unset($arr);
				$where_order[] = "((select count(*) from fm_order where order_seq = ord.order_seq and recipient_email != '') > 0)";
				$chk_sm_send_cnt = 0;
				if($_PARAM['goodstype']) {
					foreach($_PARAM['goodstype'] as $key => $data){
						if($data == "ticket") $chk_sm_send_cnt += 1;
					}
				}
				if($chk_sm_send_cnt == 0) {
					$where_order[] = " EXISTS (select 'o' from fm_order_item where order_seq = ord.order_seq and goods_kind = 'coupon' limit 1)";
				}
			}

			# -------------------------------------------------------------------------------------------
			# 희망배송일
			if($_PARAM['shipping_hop_use'] == "y"){
				unset($arr,$shipping_hope_date);
				if(trim($_PARAM['shipping_hope_sdate'])){
					$arr[] = "os.shipping_hop_date >= '".$_PARAM['shipping_hope_sdate']."'";
				}
				if(trim($_PARAM['shipping_hope_edate'])){
					$arr[] = "os.shipping_hop_date <= '".$_PARAM['shipping_hope_edate']."'";
				}

				if(!trim($_PARAM['shipping_hope_sdate']) && !trim($_PARAM['shipping_hope_edate'])){
					$arr[] = "os.shipping_hop_date between '0000-00-00' and '".date("Y-m-d")."'";
				}
				if($arr){
					$shipping_hope_date = " and ".implode(" and ",$arr);
				}
				$where_order[] = "EXISTS (select oi.order_seq from fm_order_item as oi left join fm_order_shipping as os on oi.order_seq=os.order_seq and oi.shipping_seq=os.shipping_seq where oi.order_seq=ord.order_seq ".$shipping_hope_date." limit 1)";
			}
			# -------------------------------------------------------------------------------------------
			# 예약상품발송일
			if($_PARAM['shipping_reserve_use'] == "y"){

				unset($arr,$shipping_reserve_date);
				if(trim($_PARAM['shipping_reserve_sdate']) && trim($_PARAM['shipping_reserve_edate'])){
					$arr[] = "os.reserve_sdate between '".$_PARAM['shipping_reserve_sdate']."' and '".$_PARAM['shipping_reserve_edate']."'";
				}else if(trim($_PARAM['shipping_reserve_sdate'])){
					$arr[] = "os.reserve_sdate >= '".$_PARAM['shipping_reserve_sdate']."'";
				}else if(trim($_PARAM['shipping_reserve_edate'])){
					$arr[] = "os.reserve_sdate <= '".$_PARAM['shipping_reserve_edate']."'";
				}else{
					$arr[] = "os.reserve_sdate is not null";
				}

				if($arr)	$shipping_reserve_date	= " and ".implode(" and ",$arr);

				$where_order[] = "EXISTS (select oi.order_seq from fm_order_item as oi left join fm_order_shipping as os on oi.order_seq=os.order_seq and oi.shipping_seq=os.shipping_seq where oi.order_seq=ord.order_seq ".$shipping_reserve_date." limit 1)";
			}
			# -------------------------------------------------------------------------------------------
			# 품절(x)
			if( $_PARAM['search_runout'] ){
				$where_order[] = "(order_seq in (select order_seq from fm_order_item_option where runout=1) OR order_seq in (select order_seq from fm_order_item_suboption where runout=1))";
			}
			# -------------------------------------------------------------------------------------------
			# 본사상품 주문(x)
			if( !empty($_PARAM['base_inclusion']) ){
				$where_provider[] = "EXISTS (select provider_seq from fm_order_item where order_seq=ord.order_seq and provider_seq='1')";
			}
		## ==============================================================================================
		## 상품상태기준 검색
		}else{

			# -------------------------------------------------------------------------------------------
			# 배송책임기준의 입점사 검색(본사(본사위탁배송포함), 입점사(본사위탁배송제외)
			if( !empty($_PARAM['shipping_provider_seq']) ){
				if( $_PARAM['shipping_provider_seq'] == 999999999999 ){
					$where_order[]	= "os.provider_seq!='1')";
				}else{
					if( defined('__SELLERADMIN__') !== true ){
						$where_order[]	= "os.provider_seq='".$_PARAM['shipping_provider_seq']."'";
					}
				}
			}
			# 입점사 관리자일때는 해당 입점사 상품만 검색
			if( defined('__SELLERADMIN__') === true ){
				//$where_order[]	= "i.provider_seq='".$this->providerInfo['provider_seq']."'";
			}
			# -------------------------------------------------------------------------------------------
			# 주문상태  (주문상태기준, 상품상태기준)
			if( $inquiry_type == "goods_shipping"){	//상품주문상태
				unset($arr);
				foreach($_PARAM['chk_step'] as $key => $data){
					$arr[] = "opt.step = '".$key."'";
					if( $key == 25 ) $settle_yn = 'y';
				}
				if($arr) $where_order[] = "(".implode(' OR ',$arr).")";

			}elseif($goods_provider){//입점사

				$addFroms			= "(
						SELECT
							IF( opt.step > 35 &&
								 opt.step < 85 &&
								 opt.step <> sub.step
								 ,opt.step - 5
								 ,opt.step
							) as step,
							opt.order_seq	AS order_seq,
							opt.item_seq	AS item_seq
						FROM
							fm_order_item_option opt
						LEFT JOIN
							 fm_order_item_suboption sub
						ON
							opt.item_option_seq = sub.item_option_seq and sub.step > 15
						WHERE
							opt.step > 15 AND opt.provider_seq = '{$this->session->userdata['provider']['provider_seq']}'
						GROUP BY
							opt.order_seq, opt.step
					) as sord, ";

				$addSelects			= " sord.step as step, ";
				$where_order[]		= " sord.order_seq = ord.order_seq ";
				$where_order[]		= " ord.step >= 25 ";
				$where_order[]		= " ord.step <= 85 ";
				if( $_PARAM['chk_step'] ){
					$step_arr		= array_keys($_PARAM['chk_step']);
					$step_arr_join = implode("', '", $step_arr);

					// $where_order[]		= " ( sord.step in ('".$step_arr_join."') OR sord.item_seq in (select item_seq from fm_order_item_suboption where step in ('".$step_arr_join."') and order_seq = ord.order_seq ) )";

					// $where_order[]	= "( ord.order_seq in (select order_seq from fm_order_item_option where provider_seq = '".$this->session->userdata['provider']['provider_seq']."' and step in ('".$step_arr_join."') ) OR ord.order_seq in (select order_seq from fm_order_item_suboption where step in ('".$step_arr_join."') ) )";

					$where_order[]	= "
					(
						ord.order_seq in
						(
							select order_seq from fm_order_item_option where step in ('".implode("', '", $step_arr)."') and item_seq in (select item_seq from fm_order_item where provider_seq = '".$this->session->userdata['provider']['provider_seq']."')
						)
						OR ord.order_seq in
						(
							select order_seq from fm_order_item_suboption where step in ('".implode("', '", $step_arr)."')
							 and item_seq in (select item_seq from fm_order_item where provider_seq = '".$this->session->userdata['provider']['provider_seq']."')
						)
					)";
				}
			}
			## -------------------------------------------------------------------------------------------
			## 검색어
			if( $_PARAM['keyword'] ){

				if($keyword_type){
					if($keyword_type == 'recipient_user_name'){
						$where_goods[] = "os.recipient_user_name LIKE '%" . $keyword . "%'";
					}else{
						$where_order[] = $arr_field[$keyword_type]." = '" . $keyword . "'";
					}
				// 검색어가 주문번호 일 경우
				}/*else if( preg_match('/^([0-9]{13,19})$/',$keyword)  && (strlen($keyword) == 19 || strlen($keyword) == 13)  ){
					$where[] = "ord.order_seq = '" . $keyword . "'";

				// 검색어가 출고번호 일 경우
				}*/else if(preg_match('/^D([0-9]{14})$/',$keyword)){
					$where[] = "ord.order_seq = (SELECT order_seq FROM fm_goods_export WHERE export_code = '" . $keyword . "')";
				// 검색어가 반품번호 일 경우
				}else if(preg_match('/^R([0-9]{12})$/',$keyword)){
					$where[] = "ord.order_seq = (SELECT order_seq FROM fm_order_return WHERE return_code = '" . $keyword . "')";
				// 검색어가 환불번호 일 경우
				}else if(preg_match('/^C([0-9]{12})$/',$keyword)){
					$where[] = "ord.order_seq = (SELECT order_seq FROM fm_order_refund WHERE refund_code = '" . $keyword . "')";
				}else{

					if(preg_match('/^([0-9]+)$/',$keyword)){
						$add_goodsseq_where = "ifnull(i.goods_seq,'') = '" . $keyword . "' OR ";
					}else{
						$add_goodsseq_where = "";
					}
					$where[] = "(
						ifnull(mem.user_name,'') like '%" . $keyword . "%' OR
						ifnull(bus.bname,'') like '%" . $keyword . "%' OR
						ifnull(mem.userid,'') like '%" . $keyword . "%' OR
						ifnull(mem.sns_n,'') like '".substr($keyword,0,-2)."%'
					)";
					$where_order[] = "
					(
						ifnull(ord.order_seq,'') = '" . $keyword . "' OR
						ifnull(ord.npay_order_id,'') like '%" . $keyword . "%' OR
						ifnull(ord.order_user_name,'')  like '%" . $keyword . "%' OR
						ifnull(ord.depositor,'') like '%" . $keyword . "%' OR
						ifnull(ord.order_email,'') like '%" . $keyword . "%' OR
						ifnull(ord.order_phone,'') like '%" . $keyword . "%' OR
						ifnull(ord.order_cellphone,'') like '%" . $keyword . "%' OR
						ifnull(ord.recipient_phone,'') LIKE '%" . $keyword . "%' OR
						ifnull(ord.recipient_cellphone,'') LIKE '%" . $keyword . "%' OR
						ifnull(ord.recipient_user_name,'') LIKE '%" . $keyword . "%' OR
						ifnull(ord.linkage_mall_order_id,'') LIKE '%" . $keyword . "%' OR
						ifnull(i.goods_name,'') LIKE '%" . $keyword . "%' OR
						ifnull(i.goods_code,'') LIKE '%" . $keyword . "%' OR
						".$add_goodsseq_where."
						EXISTS (
							SELECT order_seq FROM fm_goods_export WHERE order_seq = ord.order_seq and (
								ifnull(delivery_number,'') LIKE '%" . $keyword . "%' OR
								ifnull(international_delivery_no,'') LIKE '%" . $keyword . "%' OR
								ifnull(recipient_user_name,'') LIKE '%" . $keyword . "%')
						)
					)
					";
				}
			}
			# -------------------------------------------------------------------------------------------
			# 주문상품
			if( $_PARAM['goodstype'] ){
				unset($arr);
				foreach($_PARAM['goodstype'] as $key => $data){
					switch($data){
						case "ticket":		# 티켓상품
							$arr[] = "i.goods_kind='coupon'";
						break;
						case "gift":		# 사은품
							$arr[] = "i.goods_type='gift'";
						break;
						case "package":		# 패키지/복합상품
							$arr[] = "(opt.package_yn='y' or sub.package_yn = 'y')";
						break;
						case "adult":		# 성인상품
							$arr[] = "i.adult_goods = 'Y'";
						break;
						case "withdraw":	# 청약철회불가상품
							$arr[] = " EXISTS (select 'o' from fm_goods as g where i.goods_seq=g.goods_seq and g.cancel_type='1' limit 1) ";
						break;
						case "international_shipping":	# 해외배송여부(구매대행여부)
							$arr[] = "i.option_international_shipping_status='y'";
						break;
						case "reserve":		# 예약발송상품
							$arr[] = "i.reservation_ship='y'";
						break;
					}
				}

				if($arr) $where_order[] = "(".implode(' OR ',$arr).")";
			}
			# -------------------------------------------------------------------------------------------
			# 배송국가(대한민국,해외)
			if( $_PARAM['nation'] ){
				unset($arr,$where_nation);
				// 배송방법
				foreach($_PARAM['nation'] as $nation){
					$where_nation = "(ord.international = '".$nation."'";
					if( $_PARAM['shipping_set_code'][$nation] ){
						unset($arr);
						$where_nation .= "os.shipping_method in(".implode(",",$arr).")";
					}
					$where_nation .= ")";
					$where_order[] = $where_nation;
				}
			}
			# -------------------------------------------------------------------------------------------
			# 희망배송일
			if($_PARAM['shipping_hop_use'] == "y"){
				unset($arr,$shipping_hope_date);
				if(trim($_PARAM['shipping_hope_sdate'])){
					$arr[] = "os.shipping_hop_date >= '".$_PARAM['shipping_hope_sdate']."'";
				}
				if(trim($_PARAM['shipping_hope_edate'])){
					$arr[] = "os.shipping_hop_date <= '".$_PARAM['shipping_hope_edate']."'";
				}

				if(!trim($_PARAM['shipping_hope_sdate']) && !trim($_PARAM['shipping_hope_edate'])){
					$arr[] = "os.shipping_hop_date between '0000-00-00' and '".date("Y-m-d")."'";
				}
				if($arr) $where_order[]		= implode(" and ",$arr);
			}
			# -------------------------------------------------------------------------------------------
			# 예약상품발송일
			if($_PARAM['shipping_reserve_use'] == "y"){
				unset($arr,$shipping_reserve_date);
				if(trim($_PARAM['shipping_reserve_sdate'])){
					$arr[] = "os.reserve_sdate >= '".$_PARAM['shipping_reserve_sdate']."'";
				}else if(trim($_PARAM['shipping_reserve_edate'])){
					$arr[] = "os.reserve_sdate <= '".$_PARAM['shipping_reserve_edate']."'";
				}else if(trim($_PARAM['shipping_reserve_sdate']) && trim($_PARAM['shipping_reserve_edate'])){
					$arr[] = "os.reserve_sdate between '".$_PARAM['shipping_reserve_sdate']."' and '".$_PARAM['shipping_reserve_edate']."'";
				}else{
					$arr[] = "os.reserve_sdate is not null";
				}
				if($arr) $where_order[]			= implode(" and ",$arr);
			}
			# -------------------------------------------------------------------------------------------
			# 품절
			if( $_PARAM['search_runout'] ){
				$where_order[] = "(order_seq in (select order_seq from fm_order_item_option where runout=1) OR order_seq in (select order_seq from fm_order_item_suboption where runout=1))";
			}
			# -------------------------------------------------------------------------------------------
			# 본사상품 주문
			if( !empty($_PARAM['base_inclusion']) ) $where_goods[] = "io.provider_seq='1'";
		}

		## -------------------------------------------------------------------------------------------
		## 상품에서 조회
		if($_PARAM['goods_seq'] || $_PARAM['search_type'] == 'goods_seq'){
			$goods_seq					= str_replace("'","\'",$_PARAM['goods_seq']);
			if($goods_seq == "" ) {
				$goods_seq					= $_PARAM['keyword'];
			}
			$_PARAM['regist_date'][0]	= date('Y-m-d',strtotime("-1 month"));
			$_PARAM['regist_date'][1]	= date('Y-m-d');
			$_PARAM['chk_step'][75]		= 1;
			$arr[]						= "ord.step = '75'";
			$where_order[]				= "(".implode(' OR ',$arr).")";
			$where_order[]				= " orditm.goods_seq = '".$goods_seq."' ";
			$goods_seq_field			= "";
			$goodsviewjoin				= " LEFT JOIN fm_order_item orditm ON orditm.order_seq=ord.order_seq ";
		}else{
			$goodsviewjoin				= "";
			$goods_seq_field			= "";
		}
		## -------------------------------------------------------------------------------------------
		## 오더함
		/*
		if( $_PARAM['search_ordered'] ){
			$where_order[] = "(order_seq in (select order_seq from fm_order_item_option where ordered=1) OR order_seq in (select order_seq from fm_order_item_suboption where ordered=1))";
			$where_goods[] =
		}
		*/

		return array($where,$where_order,$where_goods,$where_option,$goodsviewjoin);
	}

	public function get_order_catalog_query( $_PARAM = array('list') ) {

		$page				= (trim($_PARAM['page'])) ? trim($_PARAM['page']) : 1;
		$nperpage			= ($_PARAM['nperpage']) ? $_PARAM['nperpage'] : 20;
		$limit_s			= ($page - 1) * $nperpage;
		$limit_e			= $nperpage;

		$record				= "";

		## -------------------------------------------------------------------------------------------
		## 상품조회 :: 상품에서 조회로 넘어온경우 예외처리 :: 2017-08-03 lwh
		if($_PARAM['goods_seq']){
			$_PARAM['keyword']		= $_PARAM['goods_seq'];
			$_PARAM['search_type']	= 'goods_seq';
			//unset($_PARAM['goods_seq']);
		}
		## -------------------------------------------------------------------------------------------
		## 조회 기준
		if( $_PARAM['pagemode'] == "company_catalog"){
			$inquiry_type = "goods_shipping";			// 상품상태기준(배송책임)
		}else if(defined('__SELLERADMIN__') === true ){
			$inquiry_type = "goods_provider";			// 상품상태기준(입점사)
		}else{
			$inquiry_type = "order";	//주문상태기준
		}
		## -------------------------------------------------------------------------------------------
		## 배송책임 입점사 정의(입점사관리자일때)
		if($_PARAM['pagemode'] === 'company_catalog' && defined('__SELLERADMIN__') !== true) {
		    $_PARAM['shipping_provider_seq']	= 1;
		} else if( defined('__SELLERADMIN__') === true ){
		    $_PARAM['shipping_provider_seq']	= $this->providerInfo['provider_seq'];
		}
		## -------------------------------------------------------------------------------------------
		## 유입매체
		$sitemarketplaceloop = sitemarketplace($_PARAM['sitemarketplace'], 'image', 'array');
		## -------------------------------------------------------------------------------------------
		##
		if($_PARAM['header_search_keyword']) {
			$_PARAM['keyword'] = $_PARAM['header_search_keyword'];
		}
		## ===========================================================================================
		## 검색 Where절 정리
		list($where,$where_order,$where_goods,$where_option,$goodsviewjoin) = $this->get_order_catalog_search_field($_PARAM);

		## -------------------------------------------------------------------------------------------
		## 검색필드 합치기
		if($where_order){
			$str_where_order = " WHERE " . implode(' AND ',$where_order) ;
		}

		if($where){
			$str_where_order = ($str_where_order)?$str_where_order." and ":" WHERE ";
			$str_where_order .= " ".implode(' AND ',$where);
		}

		if( $_PARAM['query_type'] == 'summary' ){
			$str_where_order = ($str_where_order)?$str_where_order." and ":" WHERE ";
			if( $inquiry_type == "goods_provider" ){
				$str_where_order .= " sord.step={$_PARAM['end_step']} ";
			}else{
				$str_where_order .= " ord.step > 0 and ord.step={$_PARAM['end_step']}";
			}
		}else{
			$str_where_order = ($str_where_order)?$str_where_order." and ":" WHERE ";
			$str_where_order .=" ord.step > 0";
		}

		//kmj
		if($_PARAM['shipping_method']){
			$addjoin .= "INNER JOIN fm_order_shipping as os on os.order_seq=opt.order_seq";
		}

		## -------------------------------------------------------------------------------------------
		## 주문상태  (주문상태기준, 상품상태기준)
		if( $inquiry_type == "goods_shipping"){	//상품주문상태
			$where = preg_replace('/ord/','sord',$where);
			if($where) $str_where			= " WHERE " . implode(' OR ',$where) ;
			if( $_PARAM['query_type'] == 'summary' ){
				$str_where = ($str_where)?$str_where." and ":" WHERE ";
				$str_where .= " sord.opt_step={$_PARAM['end_step']}";
			}elseif	($_PARAM['query_type'] == 'total_record'){
				$str_where = ($str_where)?$str_where:" ";
			}else{
				$str_where = ($str_where)?$str_where." and ":" WHERE ";
				$str_where .= " sord.opt_step > 0 ";
			}
			$str_where_goods	= implode(' AND ',$where_order) ;

			if($_PARAM['shipping_method']){
				$str_where_goods .= " AND os.shipping_method IN ('".join("','", $_PARAM['shipping_method'])."')";
			}

			$sort = "ORDER BY sord.step ASC, sord.regist_date DESC";
			if( $_PARAM['pagemode'] == "company_catalog"){
				$sort = "ORDER BY sord.opt_step ASC, sord.regist_date DESC";
			}

			if($_PARAM['shipping_provider_seq'] == 1){
				$search_step = 0;
			}else{
				$search_step = 15;
			}

			// lgs 입점사 관리자 일 경우 주문 제한
			$order_step = 0;

			//본사/입점사배송 주문 상품 리스트일때
			if( defined('__SELLERADMIN__') === true ) {
				$order_step = 15;
				$addFroms			= "
						(
						SELECT
							ord.*,
							i.item_seq,
							i.shipping_seq,
							i.provider_seq,
							i.reservation_ship,
							i.goods_name,i.goods_seq,
							os.provider_seq as shipping_provider_seq,
							IF( opt.step > 35 &&
								 opt.step < 85 &&
								 opt.step <> sub.step
								 ,opt.step - 5
								 ,opt.step
							) as opt_step,
							COUNT(DISTINCT ord.order_seq, i.item_seq)  item_cnt,
							sum(case when i.goods_type='gift'  then 1 else 0 end)  gift_cnt,
							sum(opt.ea) as opt_ea,
							sum(sub.ea) as sub_ea,
							sum(case when opt.package_yn = 'y' then 1 else 0 end) opt_package_yn,
							sum(case when sub.package_yn = 'y' then 1 else 0 end) sub_package_yn
						FROM
							fm_order_item_option opt
							INNER JOIN fm_order_item as i ON i.order_seq=opt.order_seq and i.item_seq=opt.item_seq
							INNER JOIN fm_order_shipping as os on os.order_seq=opt.order_seq and os.shipping_seq=opt.shipping_seq
							INNER JOIN fm_order as ord on ord.order_seq=opt.order_seq
							LEFT JOIN fm_order_item_suboption sub ON opt.item_option_seq = sub.item_option_seq and sub.step > ".$search_step."
							".$addjoin."
						WHERE
							".$str_where_goods."
							AND ord.step > '".$order_step."'
							AND opt.provider_seq = '{$this->session->userdata['provider']['provider_seq']}'
						GROUP BY
							opt.order_seq, opt.step
						) as sord ";
			}else{
				$addFroms			= "
						(
						SELECT
							ord.*,
							i.item_seq,
							i.shipping_seq,
							i.provider_seq,
							i.reservation_ship,
							i.goods_name,i.goods_seq,
							os.provider_seq as shipping_provider_seq,
							IF( opt.step > 35 &&
								 opt.step < 85 &&
								 opt.step <> sub.step
								 ,opt.step - 5
								 ,opt.step
							) as opt_step,
							COUNT(DISTINCT ord.order_seq, i.item_seq)  item_cnt,
							sum(case when i.goods_type='gift'  then 1 else 0 end)  gift_cnt,
							sum(opt.ea) as opt_ea,
							sum(sub.ea) as sub_ea,
							sum(case when opt.package_yn = 'y' then 1 else 0 end) opt_package_yn,
							sum(case when sub.package_yn = 'y' then 1 else 0 end) sub_package_yn
						FROM
							fm_order_item_option opt
							INNER JOIN fm_order_item as i ON i.order_seq=opt.order_seq and i.item_seq=opt.item_seq
							INNER JOIN fm_order_shipping as os on os.order_seq=opt.order_seq and os.shipping_seq=opt.shipping_seq
							INNER JOIN fm_order as ord on ord.order_seq=opt.order_seq
							LEFT JOIN fm_order_item_suboption sub ON opt.item_option_seq = sub.item_option_seq and sub.step > ".$search_step."
							".$addjoin."
						WHERE
							".$str_where_goods."
							AND ord.step > '".$order_step."'
						GROUP BY
							opt.order_seq, opt.step
						) as sord ";
			}
		}elseif($inquiry_type == "goods_provider"){//입점사
			if($_PARAM['order_seq']){
				$str_where_goods .= ' opt.order_seq="'.$_PARAM['order_seq'].'" AND';
			}
			$sort = "ORDER BY sord.step ASC, ord.regist_date DESC";
			$addFroms			= "(
					SELECT
						IF( opt.step > 35 &&
							 opt.step < 85 &&
							 opt.step <> sub.step
							 ,opt.step - 5
							 ,opt.step
						) as step,
						opt.order_seq	AS order_seq,
						opt.item_seq	AS item_seq
					FROM
						fm_order_item_option opt
						LEFT JOIN fm_order_item_suboption sub ON opt.item_option_seq = sub.item_option_seq and sub.step > 15
						".$addjoin."
					WHERE
						opt.step > 15 AND
						".$str_where_goods."
						opt.provider_seq = '{$this->session->userdata['provider']['provider_seq']}'
					GROUP BY
						opt.order_seq, opt.step
				) as sord, ";

			$addSelects			= " sord.step as step, ";
			$where_order[]		= " sord.order_seq = ord.order_seq ";
			$where_order[]		= " ord.step >= 25 ";
			$where_order[]		= " ord.step <= 85 ";
			if( $_PARAM['chk_step'] ){
				$step_arr		= array_keys($_PARAM['chk_step']);
				$step_arr_join = implode("', '", $step_arr);

				$where_order[]	= "
				(
					ord.order_seq in
					(
						select order_seq from fm_order_item_option where step in ('".implode("', '", $step_arr)."') and item_seq in (select item_seq from fm_order_item where provider_seq = '".$this->session->userdata['provider']['provider_seq']."')
					)
					OR ord.order_seq in
					(
						select order_seq from fm_order_item_suboption where step in ('".implode("', '", $step_arr)."')
						 and item_seq in (select item_seq from fm_order_item where provider_seq = '".$this->session->userdata['provider']['provider_seq']."')
					)
				)";
			}
		}
		## -------------------------------------------------------------------------------------------
		## 엑셀다운용...-->
		if	($_PARAM['nolimit'] != 'y')
			$addLimit	= " LIMIT {$limit_s}, {$limit_e} ";

		if	($_PARAM['byOption'] == 'y'){
			$joinOption	= " INNER JOIN fm_order_item ord_item ON ord.order_seq = ord_item.order_seq"
						. " INNER JOIN fm_order_item_option ord_option ON ord_item.item_seq = ord_option.item_seq ";
			$sort .= ", order_seq ";
		}
		// <--- 엑셀다운용...
		## -------------------------------------------------------------------------------------------
		## Query 정리
		if( $where_order || $where ){

			$key = get_shop_key();

			#--------------------------------------------------------------------------------------------------------------
			# 입점사, 상품주문상태기준 검색
			if( $inquiry_type == "goods_shipping"){	//상품주문상태

				$provider_seq = $this->providerInfo['provider_seq'];
				// 요약검색
				if	($_PARAM['query_type'] == 'summary'){
					$query	= "
					SELECT
						count(*) as cnt,
						sum(sord.settleprice) as total_settleprice
						, sum(sord.settleprice + sord.cash) as total_payprice
					FROM
							".$addFroms."
						LEFT JOIN fm_member mem ON mem.member_seq=sord.member_seq
						LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
						LEFT JOIN fm_referer_group rg ON sord.referer_domain = rg.referer_group_url
					{$str_where}
					";
				// 총 레코드수
				}elseif	($_PARAM['query_type'] == 'total_record'){

					$query	= "
					SELECT
						count(*) as cnt,
						sum(sord.settleprice) as total_settleprice
						, sum(sord.settleprice + sord.cash) as total_payprice
					FROM
							".$addFroms."
						LEFT JOIN fm_member mem ON mem.member_seq=sord.member_seq
						LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
						LEFT JOIN fm_referer_group rg ON sord.referer_domain = rg.referer_group_url
					{$str_where}
					";
				// 일반검색
				}else{
					$query	= "
					SELECT
						sord.*,
						mem.userid,
						(AES_DECRYPT(UNHEX(mem.email), '{$key}')) as mbinfo_email,
						(SELECT group_name FROM fm_member_group g WHERE mem.group_seq=g.group_seq) group_name,
						mem.rute as mbinfo_rute,
						mem.user_name as mbinfo_user_name,
						bus.business_seq as mbinfo_business_seq,
						bus.bname as mbinfo_bname,
						sord.referer, sord.referer_domain,
						IF(rg.referer_group_no>0, rg.referer_group_name, IF(LENGTH(sord.referer)>0,'기타','직접입력')) as referer_name,
						sord.opt_package_yn as package_yn,
						sord.sub_package_yn as sub_package_yn
					FROM
							".$addFroms."
						LEFT JOIN fm_member mem ON mem.member_seq=sord.member_seq
						LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
						LEFT JOIN fm_referer_group rg ON sord.referer_domain = rg.referer_group_url
					{$str_where}
						{$sort} LIMIT {$limit_s}, {$limit_e}
					";
				}
			}elseif($inquiry_type == "goods_provider"){//입점사

				// 요약검색
				if	($_PARAM['query_type'] == 'summary'){
					$query	= "
					SELECT
					count(*) as cnt,
					sum(ord.settleprice) as total_settleprice
					, sum(ord.settleprice + ord.cash) as total_payprice
					FROM
					".$addFroms."
					fm_order ord
					".$goodsviewjoin."
					LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
					LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
					LEFT JOIN fm_referer_group rg ON ord.referer_domain = rg.referer_group_url
					{$str_where_order}
					";
				// 총 레코드수
				}elseif	($_PARAM['query_type'] == 'total_record'){

					$query	= "
					SELECT
					count(*) as cnt,
					sum(ord.settleprice) as total_settleprice
					, sum(ord.settleprice + ord.cash) as total_payprice
					FROM
					".$addFroms."
					fm_order ord
					".$goodsviewjoin."
					LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
					LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
					LEFT JOIN fm_referer_group rg ON ord.referer_domain = rg.referer_group_url
					{$str_where_order}
					";
				// 일반검색
				}else{
					$provider_seq = $this->providerInfo['provider_seq'];
					$query	= "
					SELECT
						ord.*,
						ord.recipient_user_name shipping_recipient_user_name,
						".$addSelects."
						(select count(item_seq) from fm_order_item where order_seq = ord.order_seq and goods_type = 'gift') gift_cnt,
						(SELECT goods_name FROM fm_order_item subq_item, fm_order_item_option subq_opt WHERE subq_item.provider_seq='{$this->providerInfo['provider_seq']}' and subq_opt.order_seq = ord.order_seq and subq_item.item_seq = subq_opt.item_seq LIMIT 1 ) goods_name,
						(SELECT count(item_seq) FROM fm_order_item WHERE order_seq=ord.order_seq  and provider_seq='{$this->providerInfo['provider_seq']}' ) item_cnt,
						(SELECT sum(ea) FROM fm_order_item_option A left join fm_order_item B ON A.item_seq = B.item_seq WHERE B.order_seq=ord.order_seq and B.provider_seq='{$this->providerInfo['provider_seq']}') opt_ea,
						(SELECT sum(ea) FROM fm_order_item_suboption A left join fm_order_item B ON A.item_seq = B.item_seq WHERE B.order_seq=ord.order_seq and B.provider_seq='{$this->providerInfo['provider_seq']}') sub_ea,
						(
							SELECT userid FROM fm_member WHERE member_seq=ord.member_seq
						) userid,
						(
							SELECT AES_DECRYPT(UNHEX(email), '{$key}') as email FROM fm_member WHERE member_seq=ord.member_seq
						) mbinfo_email,
						(
							SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=ord.member_seq
						) group_name,
						(SELECT count(shipping_seq) FROM fm_order_shipping WHERE order_seq=ord.order_seq) shipping_cnt,
						mem.rute as mbinfo_rute,
						mem.user_name as mbinfo_user_name,
						bus.business_seq as mbinfo_business_seq,
						bus.bname as mbinfo_bname,
						ord.referer, ord.referer_domain,
						IF(rg.referer_group_no>0, rg.referer_group_name, IF(LENGTH(ord.referer)>0,'기타','직접입력')) as referer_name,
						(SELECT count(package_yn) FROM fm_order_item_option WHERE order_seq=ord.order_seq and package_yn = 'y') package_yn,
						(SELECT count(package_yn) FROM fm_order_item_suboption WHERE order_seq=ord.order_seq and package_yn = 'y') sub_package_yn
					FROM
						".$addFroms."
						fm_order ord
						".$goodsviewjoin."
						LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
						LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
						LEFT JOIN fm_referer_group rg ON ord.referer_domain = rg.referer_group_url
					{$str_where_order}
						{$sort} LIMIT {$limit_s}, {$limit_e}
					";
				}
			#--------------------------------------------------------------------------------------------------------------
			# 슈퍼관리자
			}else{
				//kmj
				if($_PARAM['shipping_method']){
					$goodsviewjoin .= "INNER JOIN fm_order_shipping as os on os.order_seq=ord.order_seq";
					$str_where_order .= " AND os.shipping_method IN ('".join("','", $_PARAM['shipping_method'])."')";
				}

				// 요약검색
				if	($_PARAM['query_type'] == 'summary'){
					$query	= "
					SELECT
					count(*) as cnt,
					sum(ord.settleprice) as total_settleprice
					, sum(ord.settleprice + ord.cash) as total_payprice
					FROM
					fm_order ord
					".$goodsviewjoin."
					LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
					LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
					LEFT JOIN fm_referer_group rg ON ord.referer_domain = rg.referer_group_url
					{$str_where_order}
					";
				// 총 레코드수
				}elseif	($_PARAM['query_type'] == 'total_record'){
					$query	= "
					SELECT
					count(*) as cnt,
					sum(ord.settleprice) as total_settleprice
					, sum(ord.settleprice + ord.cash) as total_payprice
					FROM
					fm_order ord
					".$goodsviewjoin."
					LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
					LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
					LEFT JOIN fm_referer_group rg ON ord.referer_domain = rg.referer_group_url
					{$str_where_order}
					";
				// 일반검색
				}else{
					$sort = "ORDER BY newstep ASC, ord.regist_date DESC";
					$query	= "
					SELECT
					ord.*,
					ord.recipient_user_name shipping_recipient_user_name,
					if(ord.step = 0 , 991, ord.step) as newstep,
					(select count(item_seq) from fm_order_item where order_seq = ord.order_seq and goods_type = 'gift') gift_cnt,
					(SELECT goods_name FROM fm_order_item WHERE order_seq=ord.order_seq ORDER BY item_seq LIMIT 1) goods_name,
					(SELECT count(item_seq) FROM fm_order_item WHERE order_seq=ord.order_seq) item_cnt,
					(SELECT sum(ea) FROM fm_order_item_option WHERE order_seq=ord.order_seq) opt_ea,
					(SELECT sum(ea) FROM fm_order_item_suboption WHERE order_seq=ord.order_seq) sub_ea,
					(
						SELECT userid FROM fm_member WHERE member_seq=ord.member_seq
					) userid,
					(
						SELECT AES_DECRYPT(UNHEX(email), '{$key}') as email FROM fm_member WHERE member_seq=ord.member_seq
					) mbinfo_email,
					(
						SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=ord.member_seq
					) group_name,
					(SELECT count(shipping_seq) FROM fm_order_shipping WHERE order_seq=ord.order_seq) shipping_cnt,
					mem.rute as mbinfo_rute,
					mem.user_name as mbinfo_user_name,
					mem.blacklist as blacklist,
					ord.blacklist as ordblacklist,
					bus.business_seq as mbinfo_business_seq,
					bus.bname as mbinfo_bname,
					ord.referer, ord.referer_domain,
					IF(rg.referer_group_no>0, rg.referer_group_name, IF(LENGTH(ord.referer)>0,'기타','직접입력')) as referer_name,
					(SELECT count(package_yn) FROM fm_order_item_option WHERE order_seq=ord.order_seq and package_yn = 'y') package_yn,
					(SELECT count(package_yn) FROM fm_order_item_suboption WHERE order_seq=ord.order_seq and package_yn = 'y') sub_package_yn
					FROM
					fm_order ord
					".$goodsviewjoin."
					LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
					LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
					LEFT JOIN fm_referer_group rg ON (ord.referer_domain != '' and ord.referer_domain = rg.referer_group_url)
					{$str_where_order} {$sort}".$addLimit;
				}
			}
			return $this->db->query($query,$bind);
		}
	}

	//티켓상품의 취소설정 @2013-10-22
	public function order_insert_socialcp_cancel($goodSeq, $order_seq, $item_seq) {
		$this->db->where('goods_seq', $goodSeq);
		$query = $this->db->get('fm_goods_socialcp_cancel');
		foreach($query->result_array() as $data){
			$params = filter_keys($data, $this->db->list_fields('fm_goods_socialcp_cancel'));
			unset($params['seq'],$params['goods_seq'],$params['regist_date']);
			$params['order_seq'] = $order_seq;
			$params['item_seq'] = $item_seq;
			$result = $this->db->insert('fm_order_socialcp_cancel', $params);
		}
		return $result;
	}

	// 티켓상품 취소 정체 추출
	public function get_order_coupon_cancel($order_seq, $item_seq){
		$sql	= "select * from fm_order_socialcp_cancel
					where order_seq = '".$order_seq."' and item_seq = '".$item_seq."' ";
		$query	= $this->db->query($sql);
		return $query->result_array();
	}

	// 주문의 상품중 상품번호가 없거나 매칭된 상품이 없는 개수 반환
	// 상품준비중 미매칭체크 기능 제외함 @2017-04-17
	public function get_nomatch_item_cnt($order_seq){
		if( defined('__SELLERADMIN__') === true ){
			$query = $this->db->query("select count(*) as cnt from fm_order_item a left join fm_order_shipping s on a.shipping_seq=s.shipping_seq left join fm_goods b on a.goods_seq=b.goods_seq where a.order_seq=? and s.provider_seq=? and (a.goods_seq = 0 or b.goods_seq is null)",array($order_seq,$this->providerInfo['provider_seq']));
		} else {
			$query = $this->db->query("select count(*) as cnt from fm_order_item a left join fm_goods b on a.goods_seq=b.goods_seq where order_seq=? and (a.goods_seq = 0 or b.goods_seq is null)",$order_seq);
		}
		$result = $query->row_array();
		return $result['cnt'];
	}

	// 주문 마일리지,에누리,예치금사용액 상품옵션/추가옵션 별로 나눔
	public function update_unit_emoney_cash_enuri($order_seq)
	{
		$tot = 0;
		$data_order			= $this->get_order($order_seq);
		$result_option		= $this->get_item_option($order_seq);
		$result_suboption	= $this->get_item_suboption($order_seq);

		$result = array();
		$param = array();
		foreach($result_option as $data_option){
			if( $data_option['sale_price'] > 0 ) {
				$tot += get_cutting_price($data_option['sale_price'])* (int) $data_option['ea'];
				$param[] = array(
					'type' => 'option',
					'seq' => $data_option['item_option_seq'],
					'unit_price' => $data_option['sale_price'],
					'ea' => $data_option['ea']
				);
			}
		}

		foreach($result_suboption as $data_suboption){
			// 추가옵션 price 에는 member_sale 이 적용되지 않아 추가함
			$data_suboption['sale_price'] = $data_suboption['price'] - $data_suboption['member_sale'];
			if( $data_suboption['sale_price'] > 0 ) {
				$tot += get_cutting_price($data_suboption['sale_price'])* (int) $data_suboption['ea'];
				$param[] = array(
					'type' => 'suboption',
					'seq' => $data_suboption['item_suboption_seq'],
					'unit_price' => $data_suboption['sale_price'],
					'ea' => $data_suboption['ea']
				);
			}
		}

		if($data_order['emoney']>0)
			$result = $this->calculate_allotment($data_order['emoney'],$tot,$param,'emoney');

		if($data_order['cash']>0)
			$result = $this->calculate_allotment($data_order['cash'],$tot,$param,'cash');

		if($data_order['enuri']>0)
			$result = $this->calculate_allotment($data_order['enuri'],$tot,$param,'enuri');

		foreach($result as $data){
			$bind = array();
			if($data['type']=='option'){
				$bind[] = $data['unit_emoney'];
				$bind[] = $data['unit_cash'];
				$bind[] = $data['unit_enuri'];
				$bind[] = get_cutting_price($data['seq']);
				$query = "update fm_order_item_option set unit_emoney=?,unit_cash=?,unit_enuri=? where item_option_seq=?";
				$this->db->query($query,$bind);
			}else if($data['type']=='suboption'){
				$bind[] = $data['unit_emoney'];
				$bind[] = $data['unit_cash'];
				$bind[] = $data['unit_enuri'];
				$bind[] = get_cutting_price($data['seq']);
				$query = "update fm_order_item_suboption set unit_emoney=?,unit_cash=?,unit_enuri=? where item_suboption_seq=?";
				$this->db->query($query,$bind);
			}
		}
	}

	// 에누리,캐쉬,마일리지 사용액 상품별 계산
	public function calculate_allotment($emoney,$tot,$param,$field){
		$remain = $emoney;
		foreach($param as $k => $data){
			// 마일리지비율 계산
			$emoney_per = $data['unit_price'] * $data['ea'] / $tot;
			if(count($param)-1 == $k ) {
				// 마지막 상품은 남은 마일리지 적용
				$data['unit_'.$field] = $remain;
			} else {
				// 총 마일리지 * 마일리지비율로 계산
				$unit_emoney = $emoney * $emoney_per;
				$data['unit_'.$field] = $unit_emoney;
				// 남은 마일리지 계산
				$remain -= $unit_emoney;
			}
			$data['unit_'.$field] = round($data['unit_'.$field] / $data['ea'],2);
			$param[$k] = $data;
		}
		return $param;
	}

	/**
	 * 관리자 주문 조회 전용
	 */
	public function get_order_catalog_query_spout ($_PARAM = ['list'])
	{
		//
		$this->db->from('fm_order ord')
			->where('ord.step >', '0')
			->where('ord.hidden', 'N');

		// 날짜
		if ($_PARAM['regist_date'][0] && $_PARAM['regist_date'][1]) {
			if ($_PARAM['date_field']) {
				$this->db->where("`ord`.`" . $_PARAM['date_field'] . "` BETWEEN '" . $_PARAM['regist_date'][0] . " 00:00:00' AND '" . $_PARAM['regist_date'][1] . " 23:59:59'", null, false);
			} else {
				$this->db->where("`ord`.`regist_date` BETWEEN '" . $_PARAM['regist_date'][0] . " 00:00:00' AND '" . $_PARAM['regist_date'][1] . " 23:59:59'", null, false);
			}
		}

		// 검색시간 검색 후 들어온 데이터를 무시 :: 2019-08-09 hyem
		if ($_PARAM['searchTime']) {
			$this->db->where('ord.regist_date <=',  $_PARAM['searchTime']);
		}

		// 주문상태
		if ($_PARAM['chk_step']) {
			$aSteps = [];
			foreach ($_PARAM['chk_step'] as $k => $v) {
				if ($v == true) {
					$aSteps[] = strval($k);
				}
			}
			$this->db->where_in('ord.step', $aSteps);
		}

		// 합포장 여부
		if ($_PARAM['chk_bundle_yn']) {
			$this->db->where('ord.bundle_yn', 'y');
		}

		// 중요주문
		if ($_PARAM['important']) {
			$this->db->where_in('ord.important', $_PARAM['important']);
		}

		//-------------------------------------------------------------------------------------------------------
		/*
		결제사, 결제수단 검색 기준 재정의 @20190823 pjm (기획팀 정의)
		1. 무통장 검색을 결제사로 취급하여 결제사와 함께 검색 시 and 조건이 아닌 or 조건으로 검색.
		2. 결제사 + 결제수단 검색시 결제사에 종속되어 검색.
		예시 >>
		- 선택 : 무통장, 카카오페이			=> 무통장, 카카오 결제건 둘다 검색되어 나옴
		- 선택 : 무통장, 카카오,신용카드	=> 무통장, 카카오페이의 신용카드 결제건이 검색되어 나옴.
		- 선택 : 카카오 + 신용카드			=> 카카오페이의 신용카드 결제건 검색
		- 선택 : 신용카드					=> 전체 결제사의 신용카드 결제건 검색
		*/
		$_arr_payment		= $_PARAM['payment'];
		$_arr_pg			= $_PARAM['pg'];
		$_is_payment_bank	= false;

		if (in_array('bank', $_PARAM['payment'])){
			$_is_payment_bank = true;
			$_bank_index	= array_search('bank', $_arr_payment);
			unset($_arr_payment[$_bank_index]);
		}

		// 일반 PG사 검색 시 전체 PG사로 대입
		if ($_arr_pg) {
			$available_pg = available_pg();
			$normal_key = array_search('normal', $_arr_pg);
			if ($normal_key !== false) {
				unset($_arr_pg[$normal_key]);
				$_arr_pg = array_merge($available_pg, $_arr_pg);
			}
		}

		if ($_is_payment_bank) {
			// 무통장 포함 검색 시
			$this->db->group_start()
				->where('ord.payment', 'bank');
			if ($_arr_payment || $_arr_pg) {
				$this->db->or_group_start();
				// 결제수단
				if ($_arr_payment) {
					$this->db->where_in('ord.payment', $_arr_payment);
				}
				// 결제사
				if ($_arr_pg) {
					$this->db->where_in('ord.pg', $_arr_pg);
				}
				$this->db->group_end();
			}
			$this->db->group_end();
		} else {
			// 무통장 미포함 검색 시
			// 결제수단
			if ($_arr_payment) {
				$this->db->where_in('ord.payment', $_arr_payment);
			}
			// 결제사
			if ($_arr_pg) {
				$this->db->where_in('ord.pg', $_arr_pg);
			}
		}

		// #28245 무통장입금 시 기본 pg 정보도 DB에 입력 되어 부득이 추가 kmj
		if (! $_is_payment_bank && ($_PARAM['pg'] || $_PARAM['payment'])) {
			$this->db->where('ord.payment !=', 'bank');
		}
		//-------------------------------------------------------------------------------------------------------

		// 주문환경
		if ($_PARAM['sitetype']) {
			$this->db->where_in('ord.sitetype', $_PARAM['sitetype']);
		}

		// 주문유형
		if ($_PARAM['ordertype']) {
			$this->db->group_start();
			foreach ($_PARAM['ordertype'] as $k => $v) {
				if ($v == 'change') {
					$this->db->or_group_start()
						->where('ord.orign_order_seq !=', null)
						->where('ord.orign_order_seq !=', '')
						->group_end();
				} elseif ($v == 'admin') {
					$this->db->or_group_start()
						->where('ord.admin_order !=', null)
						->where('ord.admin_order !=', '')
						->group_end();
				} elseif ($v == 'personal') {
					$this->db->or_group_start()
						->where('ord.person_seq !=', null)
						->where('ord.admin_order >', 0)
						->group_end();
				} elseif ($v == 'present') {
					$this->db->or_group_start()
						->where('ord.label !=', null)
						->where('ord.label =', 'present')
						->group_end();
				}
			}
			$this->db->group_end();
		}

		// 오픈마켓
		if (!empty($_PARAM['selectMarkets']) && count($_PARAM['selectMarkets']) > 0) {
			/**
			 * '내쇼핑몰' 검색 오류 수정 및
			 * linkage_id 가 아닌 linkage_mall_code로 조건 필드 변경
			 * 2019-07-30
			 * @author Sunha Ryu
			 */
			$this->db->group_start();
			if (($key = array_search('NOT', $_PARAM['selectMarkets'])) !== false) {
				unset($_PARAM['selectMarkets'][$key]);
				$this->db->where('ord.linkage_id', NULL)
					->or_where('ord.linkage_id', '');
			}

			if (count($_PARAM['selectMarkets']) > 0) {
				$this->db->or_where_in('ord.linkage_mall_code', $_PARAM['selectMarkets']);
			}
			$this->db->group_end();
		}

		// 주문유입
		if ($_PARAM['referer']) {
			$this->db->join('fm_referer_group rg', "rg.referer_group_url = ord.referer_domain AND ord.referer_domain != ''", 'left')
				->where_in("IF(`rg`.`referer_group_no` > 0, `rg`.`referer_group_cd`, IF(LENGTH(`ord`.`referer`) > 0, 'etc', 'direct'))", $_PARAM['referer']);
		}

		// 배송책임
		$is_join_order_shipping = false;
		if ($_PARAM['shipping_provider_seq']) {
			$is_join_order_shipping = true;
			$this->db->join('fm_order_shipping os', 'os.order_seq = ord.order_seq', 'inner');
			$this->db->where('os.provider_seq', $_PARAM['shipping_provider_seq']);
		}

		// 희망 배송일
		if ($_PARAM['shipping_hop_use'] && $_PARAM['shipping_hope_sdate'] && $_PARAM['shipping_hope_edate']) {
			if (! $is_join_order_shipping) {
				$is_join_order_shipping = true;
				$this->db->join('fm_order_shipping os', 'os.order_seq = ord.order_seq', 'inner');
			}
			$this->db->where("`os`.`shipping_hop_date` BETWEEN '" . $_PARAM['shipping_hope_sdate'] . "' AND '" . $_PARAM['shipping_hope_edate'] . "'", null, false);
		}

		// 예약 상품 발송일
		if ($_PARAM['shipping_reserve_use'] && $_PARAM['shipping_reserve_sdate'] && $_PARAM['shipping_reserve_edate']) {
			if (! $is_join_order_shipping) {
				$is_join_order_shipping = true;
				$this->db->join('fm_order_shipping os', 'os.order_seq = ord.order_seq', 'inner');
			}
			$this->db->where("`os`.`reserve_sdate` BETWEEN '" . $_PARAM['shipping_reserve_sdate'] . "' AND '" . $_PARAM['shipping_reserve_edate'] . "'", null, false);
		}

		// 배송 방법
		if ($_PARAM['nation']) {
			if (! $is_join_order_shipping) {
				$is_join_order_shipping = true;
				$this->db->join('fm_order_shipping os', 'os.order_seq = ord.order_seq', 'inner');
			}

			$this->db->group_start();
			// 국내 배송
			if (in_array('domestic', $_PARAM['nation'])) {
				$this->db->group_start();
				$this->db->where('ord.international', 'domestic');
				if ($_PARAM['shipping_set_code']['domestic']) {
					$this->db->where_in('os.shipping_method', $_PARAM['shipping_set_code']['domestic']);
				}
				$this->db->group_end();
			}
			// 해외 배송
			if (in_array('international', $_PARAM['nation'])) {
				$this->db->or_group_start();
				$this->db->where('ord.international', 'international');
				if ($_PARAM['shipping_set_code']['international']) {
					$this->db->where_in('os.shipping_method', $_PARAM['shipping_set_code']['international']);
				}
				$this->db->group_end();
			}
			$this->db->group_end();
		}


		// 티켓발송
		$is_join_order_item = false;
		if ($_PARAM['shipping_set_code']['ticket']) {
			$is_join_order_item = true;
			$this->db->join('fm_order_item oi', 'oi.order_seq = ord.order_seq', 'inner');
			$this->db->where('oi.goods_kind', 'coupon');
		}

		// 주문상품
		if ($_PARAM['goodstype']) {
			if (! $is_join_order_item) {
				$is_join_order_item = true;
				$this->db->join('fm_order_item oi', 'oi.order_seq = ord.order_seq', 'inner');
			}

			$this->db->group_start();
			foreach ($_PARAM['goodstype'] as $v) {
				if ($v == 'adult') {
					// 성인 상품
					$this->db->or_where('oi.adult_goods', 'Y');
				} elseif($v == 'withdraw') {
					// 청약철회불가
					$this->db->join('fm_goods g', 'g.goods_seq = oi.goods_seq', 'inner');
					$this->db->or_where('g.cancel_type', '1');
				} elseif($v == 'international_shipping') {
					// 구매대행
					$this->db->or_where('oi.option_international_shipping_status', 'y');
				} elseif($v == 'reserve') {
					// 예약 상품
					$this->db->or_where('oi.reservation_ship', 'y');
				} elseif($v == 'package') {
					// 패키지/복합상품
					$this->db->join('fm_order_item_suboption ois', 'ois.order_seq = ord.order_seq', 'inner');
					$this->db->or_where('ois.package_yn', 'y');
				} elseif($v == 'gift') {
					// 사은품
					$this->db->or_where('oi.goods_type', 'gift');
				} elseif($v == 'ticket'){
					// 티켓
					$this->db->or_where('oi.goods_kind', 'coupon');
				}
			}
			$this->db->group_end();
		}

		if ($_PARAM['no_receipt_address'] == true) {
			// 배송지 미등록 주문 조회 (선물하기 이면서 우편번호 없는경우)
			$this->db->group_start();
			$this->db->where('ord.label', 'present');
			$this->db->where('ord.recipient_zipcode', NULL, false);
			$this->db->group_end();
		} else {
			// 그 외 (선물하기 아니거나, 선물하기 이면서 우편번호가 있는경우)
			$this->db->group_start();	
			$this->db->where('ord.label', NULL, false);
			$this->db->or_group_start();
			$this->db->or_where('ord.label', 'present');
			$this->db->where('ord.recipient_zipcode !=', '');
			$this->db->group_end();
			$this->db->group_end();
		}

		//검색 키워드
		if ($_PARAM['header_search_keyword']) {
			$_PARAM['keyword'] = $_PARAM['header_search_keyword'];
		}

		$_PARAM['keyword'] = trim($_PARAM['keyword']);
		if ($_PARAM['keyword']) {
			if ($_PARAM['search_type'] && $_PARAM['search_type'] != 'all') {
				switch ($_PARAM['search_type']) {
					case 'order_seq':      // 주문번호
						$this->db->where('ord.order_seq', $_PARAM['keyword']);
						break;

					case 'order_user_name': // 주문자명
						$this->db->join('fm_member mem', 'mem.member_seq = ord.member_seq', 'left');
						$this->db->group_start();
						if ($_PARAM['search_partial'] == true) {
							$this->db->like('mem.user_name', $_PARAM['keyword'], 'both')
								->or_like('ord.order_user_name', $_PARAM['keyword'], 'both');
						} else {
							$this->db->where('mem.user_name', $_PARAM['keyword'])
								->or_where('ord.order_user_name', $_PARAM['keyword']);
						}
						$this->db->group_end();
						break;

					case 'depositor':       // 입금자명
						if ($_PARAM['search_partial'] == true) {
							$this->db->like('ord.depositor', $_PARAM['keyword'], 'both');
						} else {
							$this->db->where('ord.depositor', $_PARAM['keyword']);
						}
						break;

					case 'userid':          // 회원 아이디
						$this->db->join('fm_member mem', 'mem.member_seq = ord.member_seq', 'left');
						$this->db->group_start()
							->like('mem.userid', $_PARAM['keyword'], 'after')
							->or_like('ord.npay_order_id', $_PARAM['keyword'], 'after')
							->or_like('ord.linkage_mall_order_id', $_PARAM['keyword'], 'after')
							->or_like('mem.sns_n', $_PARAM['keyword'], 'after')
							->group_end();
						break;

					case 'order_cellphone':         // 주문자 휴대전화
					case 'recipient_cellphone':     // 받는자 휴대전화
					case 'recipient_phone':         // 받는자 전화번호
						$keyword = $this->db->escape_str($_PARAM['keyword']);
						$this->db->group_start()
							->where("INSTR(`ord`.`recipient_cellphone`, '" . $keyword . "')", null, false)
							->or_where("INSTR(`ord`.`order_cellphone`, '" . $keyword . "')", null, false)
							->or_where("INSTR(`ord`.`recipient_phone`, '" . $keyword . "')", null, false)
							->or_where("INSTR(`ord`.`order_phone`, '" . $keyword . "')", null, false)
							->group_end();
						break;

					case 'order_email':             // 주문자 이메일
						$this->db->like('ord.order_email', $_PARAM['keyword'], 'both');
						break;

					case 'recipient_user_name':
						if ($_PARAM['search_partial'] == true) {
							$this->db->like('ord.recipient_user_name', $_PARAM['keyword'], 'both');
						} else {
							$this->db->where('ord.recipient_user_name', $_PARAM['keyword']);
						}
						break;

					case 'goods_name':
						if (! $is_join_order_item) {
							$is_join_order_item = true;
							$this->db->join('fm_order_item oi', 'oi.order_seq = ord.order_seq', 'inner');
						}
						$this->db->like('oi.goods_name', $_PARAM['keyword'], 'both');
						break;


					case 'goods_seq':
						if (! $is_join_order_item) {
							$is_join_order_item = true;
							$this->db->join('fm_order_item oi', 'oi.order_seq = ord.order_seq', 'inner');
						}
						$this->db->where('oi.goods_seq', $_PARAM['keyword']);
						break;

					case 'bar_code':
						if (! $is_join_order_item) {
							$is_join_order_item = true;
							$this->db->join('fm_order_item oi', 'oi.order_seq = ord.order_seq', 'inner');
						}
						$this->db->where('oi.goods_code', $_PARAM['keyword']);
						break;
				}
			} else {
				if (preg_match("/^[A-Za-z]*$/", $_PARAM['keyword'])) { //영어만
					if (! $is_join_order_item) {
						$is_join_order_item = true;
						$this->db->join('fm_order_item oi', 'oi.order_seq = ord.order_seq', 'inner');
					}
					$this->db->join('fm_member mem', 'mem.member_seq = ord.member_seq', 'left');

					// 회원 아이디, 상품명, 주문자명 등
					$this->db->group_start()
						->like('mem.userid', $_PARAM['keyword'], 'after')
						->or_like('ord.npay_order_id', $_PARAM['keyword'], 'after')
						->or_like('ord.linkage_mall_order_id', $_PARAM['keyword'], 'after')
						->or_like('mem.sns_n', $_PARAM['keyword'], 'after')
						->or_like('ord.order_user_name', $_PARAM['keyword'], 'both')
						->or_like('ord.recipient_user_name', $_PARAM['keyword'], 'both')
						->or_like('oi.goods_name', $_PARAM['keyword'], 'both')
						->group_end();
				} elseif (preg_match("/^[0-9]*$/", $_PARAM['keyword'])) { //숫자만
					if (strlen($_PARAM['keyword']) == 19) { //주문번호
						$this->db->where('ord.order_seq', $_PARAM['keyword']);
					} else { //상품번호
						if (! $is_join_order_item) {
							$is_join_order_item = true;
							$this->db->join('fm_order_item oi', 'oi.order_seq = ord.order_seq', 'inner');
						}
						$this->db->group_start()
							->where('oi.goods_seq', $_PARAM['keyword'])
							->or_where('oi.goods_code', $_PARAM['keyword'])
							->group_end();
					}
				} elseif (preg_match("/[\xA1-\xFE\xA1-\xFE]/", $_PARAM['keyword'])) { //한글만
					if (! $is_join_order_item) {
						$is_join_order_item = true;
						$this->db->join('fm_order_item oi', 'oi.order_seq = ord.order_seq', 'inner');
					}
					$this->db->join('fm_member mem', 'mem.member_seq = ord.member_seq', 'left');

					// 주문자명
					if ($_PARAM['search_partial'] == true) {
						$this->db->group_start()
							->like('mem.user_name', $_PARAM['keyword'], 'both')
							->or_like('ord.order_user_name', $_PARAM['keyword'], 'both')
							->or_like('ord.recipient_user_name', $_PARAM['keyword'], 'both')
							->or_like('ord.depositor', $_PARAM['keyword'], 'both')
							->or_like('oi.goods_name', $_PARAM['keyword'], 'both')
							->group_end();
					} else {
						$this->db->group_start()
							->where('mem.user_name', $_PARAM['keyword'])
							->or_where('ord.order_user_name', $_PARAM['keyword'])
							->or_where('ord.recipient_user_name', $_PARAM['keyword'])
							->or_where('ord.depositor', $_PARAM['keyword'])
							->or_like('oi.goods_name', $_PARAM['keyword'], 'both')
							->group_end();
					}
				} elseif (preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $_PARAM['keyword'])) { //이메일 정규식
					$this->db->like('ord.order_email', $_PARAM['keyword'], 'both');
				} elseif (preg_match("/^\d{2,3}-\d{3,4}-\d{4}$/", $_PARAM['keyword'])) { //전화번호 정규식
					$keyword = $this->db->escape_str($_PARAM['keyword']);
					$this->db->group_start()
						->where("INSTR(`ord`.`recipient_cellphone`, '" . $keyword . "')", null, false)
						->or_where("INSTR(`ord`.`order_cellphone`, '" . $keyword . "')", null, false)
						->or_where("INSTR(`ord`.`recipient_phone`, '" . $keyword . "')", null, false)
						->or_where("INSTR(`ord`.`order_phone`, '" . $keyword . "')", null, false)
						->group_end();
				} else {
					if (! $is_join_order_item) {
						$is_join_order_item = true;
						$this->db->join('fm_order_item oi', 'oi.order_seq = ord.order_seq', 'inner');
					}
					$this->db->join('fm_member mem', 'mem.member_seq = ord.member_seq', 'left');

					if ($_PARAM['search_partial'] == true) {
						$this->db->group_start()
							->like('ord.npay_order_id', $_PARAM['keyword'], 'both')
							->or_like('mem.user_name', $_PARAM['keyword'], 'both')
							->or_like('ord.order_user_name', $_PARAM['keyword'], 'both')
							->or_like('ord.depositor', $_PARAM['keyword'], 'after')
							->or_like('mem.userid', $_PARAM['keyword'], 'both')
							->or_like('ord.recipient_user_name', $_PARAM['keyword'], 'both')
							->or_like('ord.linkage_mall_order_id', $_PARAM['keyword'], 'after')
							->or_like('mem.sns_n', $_PARAM['keyword'], 'after')
							->or_like('oi.goods_name', $_PARAM['keyword'], 'both')
							->group_end();
					} else {
						$this->db->group_start()
							->where('ord.npay_order_id', $_PARAM['keyword'])
							->or_where('mem.user_name', $_PARAM['keyword'])
							->or_where('ord.order_user_name', $_PARAM['keyword'])
							->or_where('ord.depositor', $_PARAM['keyword'])
							->or_like('mem.userid', $_PARAM['keyword'], 'after')
							->or_where('ord.recipient_user_name', $_PARAM['keyword'])
							->or_like('ord.linkage_mall_order_id', $_PARAM['keyword'], 'after')
							->or_like('mem.sns_n', $_PARAM['keyword'], 'after')
							->or_like('oi.goods_name', $_PARAM['keyword'], 'both')
							->group_end();
					}
				}
			}
		}

		## Query 정리
		if ($_PARAM['query_type'] == 'total_record') {
			$this->db->select("COUNT(DISTINCT `ord`.`order_seq`) `cnt`");
		} else {
			$this->db->distinct()
				->select('ord.order_seq')
				->order_by('ord.step', 'ASC')
				->order_by('ord.regist_date', 'DESC')
				->order_by('ord.order_seq', 'ASC');
			if (! empty($_PARAM['limit_e'])) {
				$this->db->limit($_PARAM['limit_e'], $_PARAM['limit_s']);
			}
		}

		//
		return $this->db->get()->result_array();
	}

	/*
	 * 입점사 관리자 조회 전용
	 * */
	public function get_order_catalog_query_spout_seller($_PARAM = array('list'))
	{
		$aJoinOdr = array();
		$aJoinItm = array();
		$aWhereSpi = array();
		$aWhereItm = array();
		$aWhereItmo = array();
		$aWhereOdr = array();
		$aWhereSub = array();

		$sProviderSeq = $this->session->userdata['provider']['provider_seq'];
		$aWhereItmo[] = "odr.step > '15'";
		$aWhereOdr[] = "odr.hidden = 'N'";

		// limit
		if (! empty($_PARAM['limit_e'])) {
			$addLimit = " LIMIT " . $_PARAM['limit_s'] . ", " . $_PARAM['limit_e'];
		}

		// 날짜
		if ($_PARAM['regist_date'][0] && $_PARAM['regist_date'][1]) {
			if ($_PARAM['date_field']) {
				$aWhereOdr[] = "odr." . $_PARAM['date_field'] . " >= '" . $_PARAM['regist_date'][0] . " 00:00:00'";
				$aWhereOdr[] = "odr." . $_PARAM['date_field'] . " <= '" . $_PARAM['regist_date'][1] . " 23:59:59'";
			} else {
				$aWhereOdr[] = "odr.regist_date >= '" . $_PARAM['regist_date'][0] . " 00:00:00'";
				$aWhereOdr[] = "odr.regist_date <= '" . $_PARAM['regist_date'][1] . " 23:59:59'";
			}
		}

		// 검색시간 검색 후 들어온 데이터를 무시 :: 2019-08-09 hyem
		if ($_PARAM['searchTime']) {
			$aWhereOdr[] = "odr.regist_date <= '" . $_PARAM['searchTime'] . "'";
		}

		// 주문상태
		if ($_PARAM['chk_step']) {
			$aSteps = array();
			foreach ($_PARAM['chk_step'] as $k => $v) {
				if ($v == true) {
					$aSteps[] = $k;
				}
			}
			$aWhereSub[] = "sub.step IN ('" . join("', '", $aSteps) . "')";
		}

		// 중요주문
		if ($_PARAM['important']) {
			$aWhereOdr[] = "odr.important IN ('" . join("', '", $_PARAM['important']) . "')";
		}

		// 주문환경
		if ($_PARAM['sitetype']) {
			$aWhereOdr[] = "odr.sitetype IN ('" . join("', '", $_PARAM['sitetype']) . "')";
		}

		// 오픈마켓
		if (! empty($_PARAM['selectMarkets']) && count($_PARAM['selectMarkets']) > 0) {
			$selectMarkets = array();
			if (($key = array_search('NOT', $_PARAM['selectMarkets'])) !== false) {
				unset($_PARAM['selectMarkets'][$key]);
				$selectMarkets[] = "odr.linkage_id IS NULL";
				$selectMarkets[] = "odr.linkage_id = ''";
			}

			if (count($_PARAM['selectMarkets']) > 0) {
				$selectMarkets[] = "odr.linkage_mall_code IN ('" . implode("', '", $_PARAM['selectMarkets']) . "') ";
			}

			if (count($selectMarkets) > 1) {
				$selectMarkets = "(" . implode(" OR ", $selectMarkets) . ")";
			} else {
				$selectMarkets = implode(" OR ", $selectMarkets);
			}

			$aWhereOdr[] = $selectMarkets;
		}

		// 주문유형
		if ($_PARAM['ordertype']) {
			unset($arr);
			foreach ($_PARAM['ordertype'] as $data) {
				if ($data == 'personal') {
					$arr[] = 'ifnull(odr.person_seq,0) > 0';
				}
				if ($data == 'admin') {
					$arr[] = "ifnull(odr.admin_order,'') != ''";
				}
				if ($data == 'change') {
					$arr[] = "ifnull(odr.orign_order_seq,'') != ''";
				}
				if ($data == 'present') {
					$arr[] = "ifnull(odr.label,'') = 'present'";
				}
			}
			$aWhereOdr[] = "(" . implode(" OR ", $arr) . ")";
		}

		// 배송책임
		if ($_PARAM['shipping_provider_seq']) {
			$aWhereSpi[] = "spi.provider_seq = '" . $_PARAM['shipping_provider_seq'] . "'";
		}

		if ($_PARAM['shipping_hop_use'] && $_PARAM['shipping_hope_sdate'] && $_PARAM['shipping_hope_edate']) {
			$aWhereSpi[] = "spi.shipping_hop_date >= '" . $_PARAM['shipping_hope_sdate'] . "'";
			$aWhereSpi[] = "spi.shipping_hop_date <= '" . $_PARAM['shipping_hope_edate'] . "'";
		}

		if ($_PARAM['shipping_reserve_use'] && $_PARAM['shipping_reserve_sdate'] && $_PARAM['shipping_reserve_edate']) {
			$aWhereSpi[] = "spi.reserve_sdate BETWEEN '" . $_PARAM['shipping_reserve_sdate'] . "' AND '" . $_PARAM['shipping_reserve_sdate'] . "'";
		}

		if ($_PARAM['nation']) {
			if (array_search('domestic', $_PARAM['nation']) !== false && array_search('international', $_PARAM['nation']) !== false) {
				if ($_PARAM['shipping_set_code']['domestic'] && $_PARAM['shipping_set_code']['international']) {
					$aWhereOdr[] = "((odr.international = 'domestic' AND spi.shipping_method IN ('" . join("','", $_PARAM['shipping_set_code']['domestic']) . "'))
						OR (odr.international = 'international' AND spi.shipping_method IN ('" . join("','", $_PARAM['shipping_set_code']['international']) . "')))";
				} else {
					$aWhereOdr[] = "odr.international IN ('domestic', 'international')";
				}
			} else if (array_search('domestic', $_PARAM['nation']) !== false) {
				if ($_PARAM['shipping_set_code']['domestic']) {
					$aWhereOdr[] = "(odr.international = 'domestic' AND spi.shipping_method IN ('" . join("','", $_PARAM['shipping_set_code']['domestic']) . "'))";
				} else {
					$aWhereOdr[] = "odr.international =  'domestic'";
				}
			} else if (array_search('international', $_PARAM['nation']) !== false) {
				if ($_PARAM['shipping_set_code']['international']) {
					$aWhereOdr[] = "(odr.international = 'international' AND spi.shipping_method IN ('" . join("','", $_PARAM['shipping_set_code']['international']) . "'))";
				} else {
					$aWhereOdr[] = "odr.international =  'international'";
				}
			}
		}

		// 티켓발송
		if ($_PARAM['shipping_set_code']['ticket']) {
			$aWhereItm[] = "itm.goods_kind = 'coupon'";
		}

		// 주문상품
		if ($_PARAM['goodstype']) {
			foreach ($_PARAM['goodstype'] as $k => $v) {
				if ($v == 'adult') {
					$aWhereGoodstype[] = "goods.adult_goods = 'Y'";
				} else if ($v == 'withdraw') {
					$aJoinItm[] = "LEFT JOIN fm_goods goods ON (goods.goods_seq = itm.goods_seq)";
					$aWhereGoodstype[] = "goods.cancel_type = '1'";
				} else if ($v == 'international_shipping') {
					$aWhereGoodstype[] = "goods.option_international_shipping_status = 'y'";
				} else if ($v == 'reserve') {
					$aWhereGoodstype[] = "itm.reservation_ship = 'y'";
				} else if ($v == 'package') {
					$aJoinItm[] = "LEFT JOIN fm_goods goods ON (goods.goods_seq = itm.goods_seq)";
					$aWhereGoodstype[] = "(goods.package_yn = 'y' OR goods.package_yn_suboption = 'y')";
				} else if ($v == 'gift') {
					$aWhereGoodstype[] = "goods.goods_type = 'gift'";
				} else if ($v == 'ticket') {
					$aWhereGoodstype[] = "goods.goods_kind = 'coupon'";
				}
			}
			$aWhereOdr[] = "(" . implode(' OR ', $aWhereGoodstype) . ")";
		}

		// 검색 키워드
		if ($_PARAM['header_search_keyword']) {
			$_PARAM['keyword'] = $_PARAM['header_search_keyword'];
		}

		if ($_PARAM['keyword']) {
			$_PARAM['keyword'] = trim($_PARAM['keyword']);
			if ($_PARAM['search_type'] && $_PARAM['search_type'] != "all") {
				switch ($_PARAM['search_type']) {
					case "order_seq":
						$aWhereOdr[] = "IFNULL(odr.order_seq, '') = '" . $_PARAM['keyword'] . "'";
						break;
					case "order_user_name":
						$aJoinOdr[] = "LEFT JOIN fm_member mem ON (mem.member_seq = odr.member_seq)";
						if ($_PARAM['search_partial'] == true) {
							$aWhereOdr[] = "(IFNULL(mem.user_name, '' ) like '%" . $_PARAM['keyword'] . "%'
								OR IFNULL(odr.order_user_name, '' ) like '%" . $_PARAM['keyword'] . "%')";
						} else {
							$aWhereOdr[] = "(IFNULL( mem.user_name, '' ) = '" . $_PARAM['keyword'] . "'
								OR IFNULL(odr.order_user_name, '') = '" . $_PARAM['keyword'] . "')";
						}
						break;
					case "depositor":
						if ($_PARAM['search_partial'] == true) {
							$aWhereOdr[] = "IFNULL(odr.depositor, '') like '%" . $_PARAM['keyword'] . "%'";
						} else {
							$aWhereOdr[] = "IFNULL(odr.depositor, '') = '" . $_PARAM['keyword'] . "'";
						}
						break;

					case "userid":
						$aJoinOdr[] = "LEFT JOIN fm_member mem ON (mem.member_seq = odr.member_seq)";
						$aWhereOdr[] = "(IFNULL(mem.userid, '') LIKE '" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.npay_order_id, '') LIKE '" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.linkage_mall_order_id, '') LIKE '" . $_PARAM['keyword'] . "%'
							OR IFNULL(mem.sns_n, '') LIKE '" . $_PARAM['keyword'] . "%' )";
						break;
					case "order_cellphone":
					case "recipient_cellphone":
					case "recipient_phone":
						$aWhereOdr[] = "(INSTR(odr.recipient_cellphone, '" . $_PARAM['keyword'] . "')
							OR INSTR(odr.order_cellphone, '" . $_PARAM['keyword'] . "')
							OR INSTR(odr.recipient_phone, '" . $_PARAM['keyword'] . "')
							OR INSTR(odr.order_phone, '" . $_PARAM['keyword'] . "'))";
						break;

					case "order_email":
						$aWhereOdr[] = "IFNULL(odr.order_email, '') LIKE '%" . $_PARAM['keyword'] . "%'";
						break;

					case "recipient_user_name":
						if ($_PARAM['search_partial'] == true) {
							$aWhereOdr[] = "IFNULL(odr.recipient_user_name, '') like '%" . $_PARAM['keyword'] . "%'";
						} else {
							$aWhereOdr[] = "IFNULL(odr.recipient_user_name, '') = '" . $_PARAM['keyword'] . "'";
						}
						break;
					case "goods_name":
						$aWhereItm[] = "IFNULL(itm.goods_name, '' ) LIKE  '%" . $_PARAM['keyword'] . "%'";
						break;

					case "goods_seq":
						$aWhereItm[] = "IFNULL(itm.goods_seq, '' ) = '" . $_PARAM['keyword'] . "'";
						break;
				}
			} else {
				if (preg_match("/^[A-Za-z]*$/", $_PARAM['keyword'])) {
					$aJoinOdr[] = "LEFT JOIN fm_member mem ON (mem.member_seq = odr.member_seq)";
					$aWhereOdr[] = "(IFNULL(mem.userid, '') LIKE '" . $_PARAM['keyword'] . "%'
						OR IFNULL(odr.npay_order_id, '') LIKE '" . $_PARAM['keyword'] . "%'
						OR IFNULL(odr.linkage_mall_order_id, '') LIKE  '" . $_PARAM['keyword'] . "%'
						OR IFNULL(mem.sns_n, '') LIKE '" . $_PARAM['keyword'] . "%'
						OR IFNULL(itm.goods_name, '') LIKE '%" . $_PARAM['keyword'] . "%')";
				} else if (preg_match("/^[0-9]*$/", $_PARAM['keyword'])) { // 숫자만
					if (strlen($_PARAM['keyword']) == 19) { // 주문번호
						$aWhereOdr[] = "IFNULL(odr.order_seq, '') = '" . $_PARAM['keyword'] . "'";
					} else { // 상품번호
						$aWhereItm[] = "(IFNULL(itm.goods_code, '' ) = '" . $_PARAM['keyword'] . "'
							OR itm.goods_seq = '" . $_PARAM['keyword'] . "')";
					}
				} else if (preg_match("/[\xA1-\xFE\xA1-\xFE]/", $_PARAM['keyword'])) { // 한글만
					$aJoinOdr[] = "LEFT JOIN fm_member mem ON (mem.member_seq = odr.member_seq)";
					// 주문자명
					if ($_PARAM['search_partial'] == true) {
						$aWhereOdr[] = "(IFNULL(mem.user_name, '') like '%" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.order_user_name, '') like '%" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.recipient_user_name, '') like '%" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.depositor, '') like '%" . $_PARAM['keyword'] . "%'
							OR IFNULL(itm.goods_name, '') LIKE  '%" . $_PARAM['keyword'] . "%')";
					} else {
						$aWhereOdr[] = "(IFNULL(mem.user_name, '') = '" . $_PARAM['keyword'] . "'
							OR IFNULL(odr.order_user_name, '') = '" . $_PARAM['keyword'] . "'
							OR IFNULL(odr.recipient_user_name, '') = '" . $_PARAM['keyword'] . "'
							OR IFNULL(odr.depositor, '') = '" . $_PARAM['keyword'] . "'
							OR IFNULL(itm.goods_name, '') LIKE  '%" . $_PARAM['keyword'] . "%')";
					}
				} else if (preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $_PARAM['keyword'])) { // 이메일 정규식
					$aWhereOdr[] = "IFNULL(odr.order_email, '') LIKE '%" . $_PARAM['keyword'] . "%'";
				} else if (preg_match("/^\d{2,3}-\d{3,4}-\d{4}$/", $_PARAM['keyword'])) { // 전화번호 정규식
					$aWhereOdr[] = "(INSTR(odr.recipient_cellphone, '" . $_PARAM['keyword'] . "')
						OR INSTR(odr.order_cellphone, '" . $_PARAM['keyword'] . "')
						OR INSTR(odr.recipient_phone, '" . $_PARAM['keyword'] . "')
						OR INSTR(odr.order_phone, '" . $_PARAM['keyword'] . "'))";
				} else {
					$aJoinOdr[] = "LEFT JOIN fm_member mem ON (mem.member_seq = odr.member_seq)";
					if ($_PARAM['search_partial'] == true) {
						$aWhereOdr[] = "(IFNULL(odr.npay_order_id, '') like '%" . $_PARAM['keyword'] . "%'
							OR IFNULL(mem.user_name, '') like '%" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.order_user_name, '') like '%" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.depositor, '') like '%" . $_PARAM['keyword'] . "%'
							OR IFNULL(mem.userid, '') LIKE '" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.recipient_user_name, '') like '%" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.linkage_mall_order_id, '') LIKE '" . $_PARAM['keyword'] . "%'
							OR IFNULL(mem.sns_n, '') LIKE  '" . $_PARAM['keyword'] . "%'
							OR IFNULL(itm.goods_name, '') LIKE '%" . $_PARAM['keyword'] . "%' )";
					} else {
						$aWhereOdr[] = "(IFNULL(odr.npay_order_id, '') = '" . $_PARAM['keyword'] . "'
							OR IFNULL(mem.user_name, '') = '" . $_PARAM['keyword'] . "'
							OR IFNULL(odr.order_user_name, '') = '" . $_PARAM['keyword'] . "'
							OR IFNULL(odr.depositor, '') = '" . $_PARAM['keyword'] . "'
							OR IFNULL(mem.userid, '') LIKE '" . $_PARAM['keyword'] . "%'
							OR IFNULL(odr.recipient_user_name, '') = '" . $_PARAM['keyword'] . "'
							OR IFNULL(odr.linkage_mall_order_id, '') LIKE '" . $_PARAM['keyword'] . "%'
							OR IFNULL(mem.sns_n, '') LIKE  '" . $_PARAM['keyword'] . "%'
							OR IFNULL(itm.goods_name, '') LIKE '%" . $_PARAM['keyword'] . "%')";
					}
				}
			}
		}

		if ($_PARAM['no_receipt_address'] == true) {
			// 배송지 미등록 주문 조회 (선물하기)
			$aWhereOdr[] = "(odr.label='present' AND odr.recipient_zipcode IS NULL)";
		} else {
			// 그 외
			$aWhereOdr[] = "(odr.label IS NULL OR (odr.label='present' AND odr.recipient_zipcode !=''))";
		}

		if (count($aJoinOdr) > 0) {
			$aJoinOdr = array_unique($aJoinOdr);
			$sInnerOdr = implode(' AND ', $aJoinOdr);
		}
		if (count($aJoinItm) > 0) {
			$aJoinItm = array_unique($aJoinItm);
			$sInnerItm = implode(' AND ', $aJoinItm);
		}
		if (count($aWhereSpi) > 0) {
			$aWhereSpi = array_unique($aWhereSpi);
			$sWhereSpi = "AND " . implode(' AND ', $aWhereSpi);
		}
		if (count($aWhereItm) > 0) {
			$aWhereItm = array_unique($aWhereItm);
			$sWhereItm = "AND " . implode(' AND ', $aWhereItm);
		}
		if (count($aWhereItmo) > 0) {
			$aWhereItmo = array_unique($aWhereItmo);
			$sWhereItmo = "AND " . implode(' AND ', $aWhereItmo);
		}
		if (count($aWhereOdr) > 0) {
			$aWhereOdr = array_unique($aWhereOdr);
			$sWhereOdr = " WHERE " . implode(' AND ', $aWhereOdr);
		}
		if (count($aWhereSub) > 0) {
			$aWhereSub = array_unique($aWhereSub);
			$sWhereSub = " WHERE " . implode(' AND ', $aWhereSub);
		}

		$mainQuery = "SELECT order_seq FROM (
			SELECT odr.order_seq,
				CASE
					WHEN MIN(itmo.step) <> MAX(itmo.step) AND MIN(itmo.step) > '15' AND MIN(itmo.step) < '75' AND MAX(itmo.step) > '45' AND MAX(itmo.step) < '85' THEN MAX(itmo.step - 5)
					WHEN MAX(itmo.step) > '75' THEN MIN(itmo.step)
					ELSE MAX(itmo.step)
				END AS step
			FROM fm_order odr
			" . $sInnerOdr . "
			INNER JOIN fm_order_item itm ON (odr.order_seq = itm.order_seq AND itm.provider_seq = " . $sProviderSeq . " " . $sWhereItm . ")
			" . $sInnerItm . "
			INNER JOIN fm_order_item_option itmo ON (itm.item_seq = itmo.item_seq " . $sWhereItmo . ")
			INNER JOIN fm_order_shipping spi ON (spi.shipping_seq = itmo.shipping_seq " . $sWhereSpi . ")
			LEFT JOIN fm_order_item_suboption itms ON (itmo.item_option_seq = itms.item_option_seq)
			" . $sWhereOdr . " GROUP BY odr.order_seq) sub " . $sWhereSub;

		if ($_PARAM['query_type'] == 'total_record') {
			$query = str_replace("SELECT order_seq FROM (", "SELECT count(order_seq) as cnt FROM (", $mainQuery);
		} else {
			$query = $mainQuery . " ORDER BY sub.step ASC, sub.order_seq DESC" . $addLimit;
		}
		$queryDB = $this->db->query($query);
		return $queryDB->result_array();
	}

	public function get_order_catalog_query_seller( $_PARAM = array('list') ) {
		$key = get_shop_key();

		// 배송 그룹 지정
		$shipping_provider = $this->providerInfo['provider_seq'];
		if($_PARAM['pagemode'] == "company_catalog"){
			$shipping_provider = '1';
		}

		$bind[] = $this->providerInfo['provider_seq'];
		$bind[] = $shipping_provider;
		$bind[] = $_PARAM['order_seq'];
		$query = "SELECT odr.*,
			odr.recipient_user_name AS shipping_recipient_user_name,
			COUNT(DISTINCT odr.order_seq, itm.item_seq) AS item_cnt,			
			sum(itmo.ea) AS opt_ea,
			sum(itms.ea) AS sub_ea,
			CONCAT(IF(min(itm.item_seq) = itm.item_seq, itm.goods_name, '')) AS goods_name,
			CASE
				WHEN MIN(itmo.step) <> MAX(itmo.step) AND MIN(itmo.step) > '15' AND MIN(itmo.step) < '75' AND MAX(itmo.step) > '45' AND MAX(itmo.step) < '85' AND MAX(itmo.step) NOT IN (40,50,60,70) THEN MAX(itmo.step - 5)
				WHEN MAX(itmo.step) > '75' THEN MIN(itmo.step)
				ELSE MAX(itmo.step)
			END AS step,
			mbr.userid,
			mbr.rute AS mbinfo_rute,
			mbr.user_name AS mbinfo_user_name,
			AES_DECRYPT(UNHEX(mbr.email), '".$key."') AS email,
			mgp.group_name,
			mbs.business_seq AS mbinfo_business_seq,
			mbs.bname AS mbinfo_bname,
			count(spi.shipping_seq) AS shipping_cnt,
			IF(rfg.referer_group_no > 0, rfg.referer_group_name, IF(LENGTH(odr.referer) > 0, '기타', '직접입력')) AS referer_name
		FROM fm_order odr
		INNER JOIN fm_order_item itm ON (odr.order_seq = itm.order_seq AND itm.provider_seq = ?)
		INNER JOIN fm_order_item_option itmo ON (itm.item_seq = itmo.item_seq)
		INNER JOIN fm_order_shipping spi ON (spi.shipping_seq = itmo.shipping_seq AND spi.provider_seq = ?)
		LEFT JOIN fm_order_item_suboption itms ON (itmo.item_option_seq = itms.item_option_seq)
		LEFT JOIN fm_member mbr ON mbr.member_seq=odr.member_seq
		LEFT JOIN fm_member_group mgp ON mgp.group_seq=mbr.group_seq
		LEFT JOIN fm_member_business mbs ON mbs.member_seq=mbr.member_seq
		LEFT JOIN fm_referer_group rfg ON odr.referer_domain = rfg.referer_group_url
		WHERE odr.order_seq = ?
		GROUP BY odr.order_seq";
		$queryDB = $this->db->query($query, $bind);

		return $queryDB;
	}

	// 세금계산서 증빙금액 업데이트
	public function update_tax_sales($order_seq) {

		$this->load->model('salesmodel');

		$order_tax_prices = $this->get_order_prices_for_tax($order_seq,false,true);
		$data_tax = $this->salesmodel->tax_calulate(
		$order_tax_prices["tax"],
		$order_tax_prices["exempt"],
		$order_tax_prices["shipping_cost"],
		$order_tax_prices["sale"],
		$order_tax_prices["tax_sale"],'SETTLE');

		$data_etc = $this->salesmodel->tax_calulate(
		$order_tax_prices["tax"],
		$order_tax_prices["exempt"],
		$order_tax_prices["shipping_cost"],
		$order_tax_prices["sale"],
		$order_tax_prices["etc_sale"],'SETTLE');

		$taxparams = array();

		// 과세 매출증빙 저장
		if( $data_etc['surtax'] > 0 ){
			$qry = "select seq from fm_sales where tstep=1 and typereceipt=1 and surtax > 0 and order_seq = '".$order_seq."'";
			$query = $this->db->query($qry);
			list($tax_info) = $query->result_array();

			if (!$tax_info) return false;
			$taxparams['seq']			= $tax_info['seq'];
			$taxparams['price']			= get_cutting_price($data_etc['supply']) + get_cutting_price($data_etc['surtax']);
			$taxparams['supply']		= get_cutting_price($data_etc['supply']);
			$taxparams['surtax']		= get_cutting_price($data_etc['surtax']);
			$taxparams['tax_price']		= get_cutting_price($data_tax['supply']) + get_cutting_price($data_tax['surtax']);
			$taxparams['tax_supply']	= get_cutting_price($data_tax['supply']);
			$taxparams['tax_surtax']	= get_cutting_price($data_tax['surtax']);
			$this->salesmodel->sales_modify($taxparams);

		}

		// 비과세 매출증빙 저장
		if( $data_etc['supply_free'] > 0 ){
			$qry = "select seq from fm_sales where tstep=1 and typereceipt=1 and surtax = 0 and order_seq = '".$order_seq."'";
			$query = $this->db->query($qry);
			list($tax_info) = $query->result_array();

			if (!$tax_info) return false;
			$taxparams['seq']			= $tax_info['seq'];
			$taxparams['price']			= get_cutting_price($data_etc['supply_free']);
			$taxparams['supply']		= get_cutting_price($data_etc['supply_free']);
			$taxparams['surtax']		= 0;
			$taxparams['tax_price']		= get_cutting_price($data_tax['supply_free']);
			$taxparams['tax_supply']	= get_cutting_price($data_tax['supply_free']);
			$taxparams['tax_surtax']	= 0;
			$this->salesmodel->sales_modify($taxparams);

		}
	}


	// 세금계산서 증빙금액 업데이트
	public function update_cashreceipt_sales($order_seq) {

		$this->load->model('salesmodel');

		$order_tax_prices = $this->get_order_prices_for_tax($order_seq);

		$data_tax = $this->salesmodel->tax_calulate(
		$order_tax_prices["tax"],
		$order_tax_prices["exempt"],
		$order_tax_prices["shipping_cost"],
		$order_tax_prices["sale"],
		$order_tax_prices["tax_sale"],'SETTLE');

		$cashparams = array();

		$qry = "select seq from fm_sales where tstep=1 and typereceipt=2 and order_seq = '".$order_seq."'";
		$query = $this->db->query($qry);
		list($cash_info) = $query->result_array();
		if (!$cash_info) return false;


		$cashparams['seq']			= $cash_info['seq'];
		$cashparams['price']		= get_cutting_price($data_tax['supply'])
										+ get_cutting_price($data_tax['supply_free'])
										+ get_cutting_price($data_tax['surtax']);
		$cashparams['supply']		= get_cutting_price($data_tax['supply'])
										+ get_cutting_price($data_tax['supply_free']);
		$cashparams['surtax']		= get_cutting_price($data_tax['surtax']);

		$this->salesmodel->sales_modify($cashparams);
	}

	# 마일리지 사용 단위
	function get_cutting_emoney($use_emoney,$member_emoney,$settle_price,$cart_total,$reserve_use=true,$err_reserve='')
	{

		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
		$cutting_using_unit = 0;
		$using_unit = 0;

		/* 마일리지 사용 단위 추가 leewh 2014-06-24 */
		if ($reserve_use===true) {
			$symbol = get_currency_info($this->config_system['basic_currency']);
			$symbol = $symbol['currency_symbol'];
			switch($reserves['emoney_using_unit']){
				case 1:
					$using_unit_tmp = explode(".",$use_emoney);
					if	(!($use_emoney%10 == 0 && !$using_unit_tmp[1])) {
						$using_unit = 1;
						$cutting_using_unit = $use_emoney-$use_emoney%10;
					}
					$using_unit_msg = "10".$symbol;
					break;
				case 2:
					$using_unit_tmp = explode(".",$use_emoney);
					if	(!($use_emoney%100 == 0 && !$using_unit_tmp[1])) {
						$using_unit = 1;
						$cutting_using_unit = $use_emoney-$use_emoney%100;
					}
					$using_unit_msg = "100".$symbol;
					break;
				case 3:
					$using_unit_tmp = explode(".",$use_emoney);
					if	(!($use_emoney%1000 == 0 && !$using_unit_tmp[1])) {
						$using_unit = 1;
						$cutting_using_unit = $use_emoney-$use_emoney%1000;
					}
					$using_unit_msg = "1,000".$symbol;
					break;
				case '0.1':
					$using_unit_tmp	= explode(".",$use_emoney/ 0.1);
					if($using_unit_tmp[1]){
						$using_unit = 1;
						$temp_emoney = explode(".",$use_emoney);
						$cutting_using_unit = $temp_emoney[0].'.'.substr($temp_emoney[1],0,1);
						$using_unit_msg = "0.1".$symbol;
					}
					break;
				case '0.01':
					$using_unit_tmp	= explode(".",$use_emoney / 0.01);
					if($using_unit_tmp[1]){
						$using_unit = 1;
						$temp_emoney = explode(".",$use_emoney);
						$cutting_using_unit = $temp_emoney[0].'.'.substr($temp_emoney[1],0,2);
						$using_unit_msg = "0.01".$symbol;
					}
					break;
				default :
					$using_unit_tmp = explode(".",$use_emoney);
					if	($using_unit_tmp[1] && $using_unit_tmp[1] != '00') {
						$using_unit = 1;
						$cutting_using_unit = $using_unit_tmp[0];
					}
					$using_unit_msg = "1".$symbol;
					break;
			}

			if (($using_unit != 0) && $reserve_use===true) {
				//"마일리지은 ".$using_unit_msg." 단위로 사용가능 합니다."
				$err_reserve = getAlert('os019',$using_unit_msg);
			}else{
				$cutting_using_unit = 0;
			}
		}

		if( ($use_emoney > $settle_price) && $reserve_use===true ){
			$reserve_use = false;
			//"최대 ".number_format($settle_price)."원까지 사용가능 합니다."
			$err_reserve = getAlert('os020',get_currency_price($settle_price,2));
		}

		if( ($use_emoney > $member_emoney) && $reserve_use===true ){
			$reserve_use = false;
			//number_format( $members['emoney'] )."원 이상 사용하실 수 없습니다."
			$err_reserve = getAlert('os021',get_currency_price( $member_emoney ,2));
		}

		if(($reserves['emoney_use_limit'] > $member_emoney) && $reserve_use===true){
			$reserve_use = false;
			//number_format($reserves['emoney_use_limit'])."원 이상 적립하여야 합니다."
			$err_reserve = getAlert('os022',get_currency_price($reserves['emoney_use_limit'],2));
		}

		if(($reserves['emoney_price_limit'] > $cart_total) && $reserve_use===true){
			$reserve_use = false;
				//상품을 ".number_format($reserves['emoney_price_limit'])."원 이상 사야 합니다.
				$err_reserve = getAlert('os023',get_currency_price($reserves['emoney_price_limit'],2));
		}

		if(($member_emoney >= $reserves['emoney_use_limit']) && $reserve_use===true){
			if($reserves['max_emoney_policy'] == 'percent_limit' && $reserves['max_emoney_percent']){
				$max_emoney = get_cutting_price($settle_price * $reserves['max_emoney_percent'] / 100);
			}else if($reserves['max_emoney_policy'] == 'price_limit' && $reserves['max_emoney']){
				$max_emoney = get_cutting_price($reserves['max_emoney']);
			}

			if($max_emoney > $settle_price) $max_emoney = $settle_price;

			if($use_emoney < $reserves['min_emoney']){
				$reserve_use = false;
				//마일리지은  최소 ".number_format($reserves['min_emoney'])."원부터 사용가능 합니다.
				$err_reserve = getAlert('os024',get_currency_price($reserves['min_emoney'],2));
			}
			if($use_emoney > $max_emoney && $reserves['max_emoney_policy'] != 'unlimit'){
				$reserve_use = false;
				//마일리지은  최대 ".number_format($max_emoney)."원까지 사용가능 합니다.
				$err_reserve = getAlert('os025',get_currency_price($max_emoney,2));
			}
		}

		return array($reserve_use,$err_reserve,$cutting_using_unit);

	}

	function get_usable_emoney($total_price,$settle_price, $member_emoney){

		$reserve_use	= true;
		$reserves		= ($this->reserves)?$this->reserves:config_load('reserve');

		$returnInfo		= array();
		$err_reserve	= "";

		$usable_emoney	= get_cutting_price($member_emoney);

		if($usable_emoney > $settle_price){
			$usable_emoney = $settle_price;
		}

		if($member_emoney >= $reserves['emoney_use_limit'] ){
			if($reserves['max_emoney_policy'] == 'percent_limit' && $reserves['max_emoney_percent']){
				$max_emoney = get_cutting_price($settle_price * $reserves['max_emoney_percent'] / 100);
			}else if($reserves['max_emoney_policy'] == 'price_limit' && $reserves['max_emoney']){
				$max_emoney = get_cutting_price($reserves['max_emoney']);
			}

			if($max_emoney > $settle_price) $max_emoney = $settle_price;

			if($usable_emoney < $reserves['min_emoney']){
				$usable_emoney = 0;
				$err_reserve = "마일리지은  최소 ".get_currency_price($reserves['min_emoney'],2)."부터 사용가능 합니다.";
			}
			if($usable_emoney > $max_emoney && $reserves['max_emoney_policy'] != 'unlimit'){
				$usable_emoney = $max_emoney;
			}
		}

		if($reserves['emoney_use_limit'] > $member_emoney){
			$usable_emoney = 0;
			$err_reserve = get_currency_price($reserves['emoney_use_limit'],2)." 이상 적립하여야 합니다.";
		}

		if($reserves['emoney_price_limit'] > $total_price){
			$usable_emoney = 0;
			$err_reserve = "상품을 ".get_currency_price($reserves['emoney_price_limit'],2)." 이상 사야 합니다.";
		}

		$returnInfo['emoney'] = $usable_emoney;
		if ($err_reserve) {
			$returnInfo['err_reserve'] = $err_reserve;
		}

		return $returnInfo;
	}

	function get_usable_cash($total_price,$settle_price, $member_cash){
		$reserve_use = true;

		$usable_cash = $member_cash;

		if( $usable_cash > $settle_price ){
			$usable_cash = $settle_price;
		}

		return $usable_cash;
	}

	// 주문의 각 수량의 sum을 구한다.
	public function get_ea_for_step($add_suboption = true, $reorder = true, $sc = array()){

		$addOrderWhere = $addOptionWhere = $addSuboptionWhere = '';
		$addOrderBind  = $addOptionBind  = $addSuboptionBind  = array();

		// 회원검색
		if	($sc['member_seq'] > 0){
			$addOrderWhere	= " and member_seq = ? ";
			$addOrderBind[]	= $sc['member_seq'];
		}

		// 날짜검색 추가 :: 2019-01-30 pjw
		if($sc['s_date']){
			$addOrderWhere	.= " and regist_date >= ? ";
			$addOrderBind[]	= $sc['s_date'];
		}
		if($sc['e_date']){
			$addOrderWhere	.= " and regist_date <= ? ";
			$addOrderBind[]	= $sc['e_date'];
		}

		// 조건에 맞는 주문번호들 추출
		$ord_sql	= "select order_seq, step from fm_order where step > 0 " . $addOrderWhere;
		$ord_query	= $this->db->query($ord_sql, $addOrderBind);
		$ord_result	= $ord_query->result_array();
		if	($ord_result)foreach($ord_result as $o => $data){
			$order_seq_arr[]					= $data['order_seq'];
			$result[$data['order_seq']]['step']	= $data['step'];
		}

		// 검색된 주문이 있을 경우
		if	(is_array($order_seq_arr) && count($order_seq_arr) > 0){
			// 필수옵션 수량 추출 쿼리
			$opt_sql	= "select
							order_seq,
							sum(ea)			as ea,
							sum(step35)		as step35,
							sum(step45)		as step45,
							sum(step55)		as step55,
							sum(step65)		as step65,
							sum(step75)		as step75,
							sum(step85)		as step85,
							sum(refund_ea)	as refund_ea
						from
							fm_order_item_option
						where
							order_seq in ('" . implode("', '", $order_seq_arr) . "')
							" . $addOptionWhere . "
						group by order_seq ";
			$opt_query	= $this->db->query($opt_sql, $addOptionBind);
			$opt_result	= $opt_query->result_array();
			if	($opt_result)foreach($opt_result as $o => $data){
				$result[$data['order_seq']]['ea']			+= $data['ea'];
				if		($result[$data['order_seq']]['step'] == 15){
					$result[$data['order_seq']]['step15']	+= $data['ea'] - $data['step85'];
					$result[$data['order_seq']]['step25']	+= 0;
				}elseif	($result[$data['order_seq']]['step'] == 25){
					$result[$data['order_seq']]['step15']	+= 0;
					$result[$data['order_seq']]['step25']	+= $data['ea'] - $data['step85'];
				}
				$result[$data['order_seq']]['step35']	+= $data['step35'];
				$result[$data['order_seq']]['step45']	+= $data['step45'];
				$result[$data['order_seq']]['step55']	+= $data['step55'];
				$result[$data['order_seq']]['step65']	+= $data['step65'];
				$result[$data['order_seq']]['step75']	+= $data['step75'];
				$result[$data['order_seq']]['step85']	+= $data['step85'];
				$result[$data['order_seq']]['refund_ea']	+= $data['refund_ea'];
			}

			// 추가옵션의 수량을 포함해서 구할 경우
			if	($add_suboption){
				// 추가옵션 수량 추출 쿼리
				$sub_sql	= "select
								order_seq,
								sum(ea)			as ea,
								sum(step35)		as step35,
								sum(step45)		as step45,
								sum(step55)		as step55,
								sum(step65)		as step65,
								sum(step75)		as step75,
								sum(step85)		as step85,
								sum(refund_ea)	as refund_ea
							from
								fm_order_item_suboption
							where
								order_seq in ('" . implode("', '", $order_seq_arr) . "')
								" . $addSuboptionWhere . "
							group by order_seq ";
				$sub_query	= $this->db->query($sub_sql, $addSuboptionBind);
				$sub_result	= $sub_query->result_array();
				if	($sub_result)foreach($sub_result as $s => $data){
					$result[$data['order_seq']]['ea']			+= $data['ea'];
					if		($result[$data['order_seq']]['step'] == 15){
						$result[$data['order_seq']]['step15']	+= $data['ea'] - $data['step85'];
						$result[$data['order_seq']]['step25']	+= 0;
					}elseif	($result[$data['order_seq']]['step'] == 25){
						$result[$data['order_seq']]['step15']	+= 0;
						$result[$data['order_seq']]['step25']	+= $data['ea'] - $data['step85'];
					}
					$result[$data['order_seq']]['step35']		+= $data['step35'];
					$result[$data['order_seq']]['step45']		+= $data['step45'];
					$result[$data['order_seq']]['step55']		+= $data['step55'];
					$result[$data['order_seq']]['step65']		+= $data['step65'];
					$result[$data['order_seq']]['step75']		+= $data['step75'];
					$result[$data['order_seq']]['step85']		+= $data['step85'];
					$result[$data['order_seq']]['refund_ea']	+= $data['refund_ea'];
				}
			}
		}

		// 해당 주문에 대한 반품/환불 수량 추출
		if	($reorder){
			// 환불 데이터 추출
			if(!empty($order_seq_arr)){
				$ref_sql	= "select order_seq, count(*) cnt from fm_order_refund
							where order_seq in ('" . implode("', '", $order_seq_arr) . "')
							group by order_seq";

				$ref_query	= $this->db->query($ref_sql);
				$ref_result	= $ref_query->result_array();
				if	($ref_result)foreach($ref_result as $s => $data){
					$result[$data['order_seq']]['refund']	= $data['cnt'];
				}

				// 반품 데이터 추출
				$ret_sql	= "select order_seq, count(*) cnt from fm_order_return
							where order_seq in ('" . implode("', '", $order_seq_arr) . "')
							group by order_seq";
				$ret_query	= $this->db->query($ret_sql);
				$ret_result	= $ret_query->result_array();
				if	($ret_result)foreach($ret_result as $s => $data){
					$result[$data['order_seq']]['return']	= $data['cnt'];
				}
			}
		}

		return $result;
	}

	// 수량 기준으로 주문상태를 추출한다. ( 주문접수, 결제취소, )
	public function get_step_by_ea($data, $type = 1){

		$result	= 0;
		$ea		= $data['ea'];

		if	($ea == $data['step85']){
			$result = 85;
		}else{
			$ea = $ea - $data['step85'];	// 취소 수량은 무시
			for ( $s = 75; $s >= 15; $s-=10){
				if	($ea == $data['step'.$s]){
					$result	= $s;
					break;
				}elseif	($result > 0 && ($result%10) == 5 && $data['step'.$s] > 0){
					$result	= $result - 5;
				}elseif	($result == 0 && $data['step'.$s] > 0){
					$result	= $s;
				}
			}
		}

		// 존재하지 않는 상태에 대한 예외처리
		if		($result == 20)	$result	= 25;
		elseif	($result == 30)	$result	= 35;

		// 간략 상태 요청일 경우 부분출고완료부터 부분배송완료까지를 배송중으로 표시
		if	($type == 2 && $result >= 50 && $result <= 60)	$result = 65;

		return $result;
	}

	public function update_item($goods_seq,$provider_seq,$item_seq)
	{
		$bind[] = $goods_seq;
		$bind[] = $goods_seq;
		$bind[] = $goods_seq;
		$bind[] = $provider_seq;
		$bind[] = $item_seq;

		$set_query = implode(',',$arr_set_query);
		$query = "update `fm_order_item` set
				image=(select image from fm_goods_image where goods_seq=? and image_type='thumbScroll' and cut_number=1),
				goods_name=(select goods_name from fm_goods where goods_seq=?),
				goods_seq=?,
				provider_seq=?
				where item_seq=?";
		$this->db->query($query,$bind);

	}

	public function update_option($item_option_seq,$provider_seq,$options)
	{
		$arr_set_query = $bind = array();
		$arr_set_query[]	= "provider_seq=?";
		$bind[]				= $provider_seq;

		foreach($options as $k => $option){
			$j = $k + 1;
			$arr_set_query[]	= "title".$j."=?";
			$arr_set_query[]	= "option".$j."=?";
			$arr_set_query[]	= "optioncode".$j."=?";
			$arr_set_query[]	= "package_yn=?";
			$bind[]				= $option['title'];
			$bind[]				= $option['value'];
			$bind[]				= $option['code'];
			$bind[]				= $option['package_yn'];

			$optioncode[]		= $option['code'];
		}

		// goods_code update 분리 fm_order_item_option 의 goods_code 는 모든 optioncode가 합쳐져야함 2020-03-25
		$arr_set_query[]		= "goods_code=?";
		$bind[]					= $options[0]['goods_code'].implode('',$optioncode);

		$bind[] = $item_option_seq;
		if( $arr_set_query && $item_option_seq ){
			$set_query = implode(',',$arr_set_query);
			$query = "update `fm_order_item_option` set ".$set_query." where item_option_seq=?";
			$this->db->query($query,$bind);
		}

	}

	public function update_suboption($suboptions)
	{
		foreach($suboptions as $suboption){
			$bind = array();
			$bind[] = trim($suboption['title']);
			$bind[] = trim($suboption['value']);
			$bind[] = trim($suboption['code']);
			$bind[] = trim($suboption['goods_code']);
			$bind[]	= $suboption['package_yn'];
			$bind[] = $suboption['item_suboption_seq'];

			$query = "update `fm_order_item_suboption` set title=?,suboption=?,suboption_code=?,goods_code=?,package_yn=? where item_suboption_seq=?";
			$this->db->query($query,$bind);
		}

	}

	// 입력옵션 정보 업데이트
	public function insert_inputoption($insert_inp_option)
	{

		if( $insert_inp_option ){

			$newinsert_inp_option = filter_keys($insert_inp_option, $this->db->list_fields('fm_order_item_input'));
			$this->db->insert('fm_order_item_input', $newinsert_inp_option);
			$this->db->insert_id();
		}

	}

	// 장바구니 목록용 데이터를 재가공한다.
	public function remanufacture_cart($cart, $type = 'cart', $call_point = ''){

		// 기본값 세팅
		$applypage				= $type;
		$category				= array();
		$possible_pay			= array();
		$expectGoodsChk			= false;
		$goodscancellation		= false;
		$is_coupon				= false;
		$is_goods				= false;
		$result['status']		= false;
		$result['err_msg']		= '잘못된 접근입니다.';
		$result['action']		= 'back';
		if	($call_point == 'admin')	$member_seq		= $_GET['member_seq'];
		else							$member_seq		= $this->userInfo['member_seq'];

		// 재계산을 위해 기존값 초기화
		$cart['total']						= 0;
		$cart['total_ea']					= 0;
		$cart['total_sale']					= 0;
		$cart['total_price']				= 0;
		$cart['total_reserve']				= 0;
		$cart['total_point']				= 0;

		// 기본 로드
		$this->load->model('cartmodel');
		$this->load->model('goodsmodel');
		$this->load->model('couponmodel');
		$this->load->model('membermodel');
		$this->load->model('providershipping');
		$this->load->library('sale');
		$cfg['order']	= config_load('order');
		$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');
		if	($member_seq > 0){
			$members	= $this->membermodel->get_member_data($member_seq);
		}

		//----> sale library 적용
		$param['cal_type']				= 'list';
		$param['total_price']			= $cart['total'];
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $members['member_seq'];
		$param['group_seq']				= $members['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		foreach($cart['list'] as $key => $data){

			// 비과세 상품일 경우 체크아웃 체크
			if	($data['tax'] == 'exempt')			$expectGoodsChk		= true;

			// 상품 종류
			if	($data['goods_kind'] == 'coupon')	$is_coupon			= true;
			else									$is_goods			= true;

			// 청약철회상품
			if	($data['cancel_type'] == 1)			$goodscancellation	= true;

			// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
			if	( $data['event']['event_goodsStatus'] === true ){
				$err_msg			= '↓아래 상품은 단독이벤트 기간에만 구매가 가능합니다.';
				$err_msg			.= '<br />'.$data['goods_name'];

				$result['status']	= false;
				$result['err_msg']	= '잘못된 접근입니다.';
				$result['action']	= 'back';
				return $result;
			}

			if	($data['goods_kind'] == 'coupon') {
				if	($data['cart_option_seq']){
					// 티켓상품 기간체크
					$chkcouponexpire	= check_coupon_date_option($data['goods_seq'], $data['option1'], $data['option2'], $data['option3'], $data['option4'], $data['option5']);
					if	( $chkcouponexpire['couponexpire'] === false ){
						$err_msg	= "↓아래 티켓상품의 유효기간은 ".$chkcouponexpire['social_start_date']." ~ ".$chkcouponexpire['social_end_date']." 입니다.";
						$err_msg	.= "<br />".$data['goods_name'];
						if	($opttitle)	$err_msg	.= "(".$opttitle.")";

						$result['status']			= false;
						$result['err_msg']			= $err_msg;
						$result['cart_option_seq']	= $data['cart_option_seq'];
						$result['action']			= 'optiondel_back';
						return $result;
					}
				}

				if	($data['cart_suboption_seq']){
					// 티켓상품 기간체크
					$chkcouponexpire	= check_coupon_date_suboption($data['goods_seq'], $data['suboption_title'], $data['suboption']);
					if	( $chkcouponexpire['couponexpire'] === false ){
						$err_msg	= "↓아래 티켓상품의 유효기간은 ".$chkcouponexpire['social_start_date']." ~ ".$chkcouponexpire['social_end_date']." 입니다.";
						$err_msg	.= "<br />".$data['goods_name'];
						if	($opttitle)	$err_msg	.= "(".$opttitle.")";

						$result['status']			= false;
						$result['err_msg']			= $err_msg;
						$result['cart_option_seq']	= $data['cart_suboption_seq'];
						$result['action']			= 'suboptiondel_back';
						return $result;
					}
				}
			}


			// 재고 체크
			$chk	= check_stock_option($data['goods_seq'], $data['option1'],
											$data['option2'], $data['option3'],
											$data['option4'], $data['option5'],
											$data['ea'], $cfg['order'], 'view_stock' );

			if	( $chk['stock'] < 0 ){
				// 해당상품의 옵션제거
				if	($data['option1'])	$opttitle	= $data['option1'];
				if	($data['option2'])	$opttitle	.= ' '.$data['option2'];
				if	($data['option3'])	$opttitle	.= ' '.$data['option3'];
				if	($data['option4'])	$opttitle	.= ' '.$data['option4'];
				if	($data['option5'])	$opttitle	.= ' '.$data['option5'];

				$err_msg	= "↓아래 상품의 재고는 ".$chk['sale_able_stock']."개 입니다.";
				$err_msg	.= "<br />".$data['goods_name'];
				if	($opttitle)	$err_msg	.= "(".$opttitle.")";

				$result['status']			= false;
				$result['err_msg']			= $err_msg;
				$result['cart_option_seq']	= $data['cart_option_seq'];
				$result['action']			= 'optiondel_back';
				return $result;
			}

			// 추가옵션 재고 체크
			$cart_suboptions	= $data['cart_suboptions'];
			if	($cart_suboptions){
				foreach($cart_suboptions as $k => $cart_suboption){
					// 재고 체크
					$chk	= check_stock_suboption($data['goods_seq'], $cart_suboption['suboption_title'], $cart_suboption['suboption'], $cart_suboption['ea'], $cfg['order'], 'view_stock' );
					if	( $chk['stock'] < 0 ){
						// 해당상품의 옵션제거
						$err_msg	= "↓아래 상품의 재고는 ".$chk['sale_able_stock']."개 입니다.";
						$err_msg	.= "<br />".$data['goods_name'];
						if	($cart_suboption['suboption'])
							$err_msg	.= "(".$cart_suboption['suboption'].")";

						$result['status']			= false;
						$result['err_msg']			= $err_msg;
						$result['cart_option_seq']	= $data['cart_suboption_seq'];
						$result['action']			= 'suboptiondel_back';
						return $result;
					}
				}
			}

			// 해당 상품의 전체 카테고리
			$category	= array();
			$tmp		= $this->goodsmodel->get_goods_category($data['goods_seq']);
			foreach($tmp as $row) $category[] = $row['category_code'];


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
			$data['reserve']	= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'],
									$sales['result_price'], $cfg_reserve['default_reserve_percent'],
									$data['reserve_rate'], $data['reserve_unit'], 0);
			$data['point']		= $this->goodsmodel->get_point_with_policy($sales['result_price']);
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

			// 입점사별 구매금액 합계
			$provider_price[$data['provider_seq']]	+= $data['price']*$data['ea'];

			// 총 할인 목록 노출을 위한 배열 생성
			if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $sale_title){
				$data['tsales']['sale_list'][$sale_type]		= $sales['sale_list'][$sale_type];
				$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
				$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
			}
			$cart['total_sale_list']['shippingcoupon']['title']	= '배송비쿠폰';
			$cart['total_sale_list']['shippingcoupon']['price']	= 0;
			$cart['total_sale_list']['shippingcode']['title']	= '배송비코드';
			$cart['total_sale_list']['shippingcode']['price']	= 0;
			$this->sale->reset_init();
			//<---- sale library 적용


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

					$subdata['sales']					= $sales;
					$subdata['org_price']				= ($subdata['consumer_price']) ? $subdata['consumer_price'] : $subdata['price'];

					// 마일리지 / 포인트
					$subdata['reserve']					= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'], $sales['result_price'], $cfg_reserve['default_reserve_percent'], $subdata['reserve_rate'], $subdata['reserve_unit'], $subdata['reserve']);
					$subdata['point']					= $this->goodsmodel->get_point_with_policy($sales['result_price']);
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

					// 입점사별 구매금액 합계
					$provider_price[$data['provider_seq']]	+= $cart_suboption['price'] * $cart_suboption['ea'];

					$data['cart_suboptions'][$k]	= $subdata;
				}
			}

			$cart['list'][$key]		= $data;
			if	($data['shipping_policy'] == 'goods')
				$data['shipping_policy']	.= '_'.$data['cart_seq'];

			## 임시장바구니(스크립트방식)의 옵션 고유 순번 생성 @2015-08-20 pjm
			if($cart_option_seq){
				$data['list_key'] = $data['cart_option_seq'];
			}else{
				$data['list_key'] = "k".str_pad($key, 2, "0", STR_PAD_LEFT).$_GET['list_num'];
			}
			if($cart['max_option_key']){
				$sub_grp_key = ($key+$cart['max_option_key']);
			}else{
				$sub_grp_key = $key;
			}

			$group_key				= $data['goods_type'].'|'.$data['shipping_policy'];
			$shipping_cart_list[$group_key][$sub_grp_key]				= $data;
			$shipping_cart_list[$group_key][$sub_grp_key]['rowspan']	+= count($data['cart_suboptions']) + 1;
		}

		$cart['total_price']			+= get_cutting_price(array_sum($cart['shipping_price']));

		$result['status']				= true;
		$result['cart']					= $cart;
		$result['shipping_cart_list']	= $shipping_cart_list;
		$result['possible_pay']			= $possible_pay;
		$result['goodscancellation']	= $goodscancellation;
		$result['is_coupon']			= $is_coupon;
		$result['is_goods']				= $is_goods;
		$result['provider_price']		= $provider_price;

		return $result;
	}

	// @ gift_provider = 입점사 목록
	// @ provider_price = 입점사별 구매금액

	// 사은품 목록 추출
	// @ gift_shipping = 배송그룹 목록
	// @ ship_grp_price = 배송그룹별 구매금액
	public function get_gift_event($gift_categorys,$gift_goods,$gift_shipping, $ship_grp_price, $gift_loop,$cart_total){

		### GIFT
		if	(is_array($gift_shipping) && count($gift_shipping) > 0){
			$addWhere	= " and shipping_group_seq in ('".implode("', '", array_unique($gift_shipping))."') ";
		}

		$gift_cnt		= 0;
		$gift_goods_cnt	= 0;
		$today			= date("Y-m-d");
		$sql			= "select * from fm_gift where
						gift_gb = 'order' and display = 'y' and start_date <= '".$today."' and
						end_date >= '".$today."' ".$addWhere;
		$query			= $this->db->query($sql);

		foreach ($query->result_array() as $v){

			unset($g_result);

			$real_target_goods		= array();		//지급대상이 되는 상품
			$real_target_category	= array();		//지급대상이 되는 카테고리

			// 사은품 이벤트 주최 입점사 정보
			$sql		= "select provider_name,deli_group from fm_provider where provider_seq='".$v['provider_seq']."'";
			$pro_query	= $this->db->query($sql);
			$pro_data	= $pro_query->result_array();
			if($v['provider_seq'] > 1 && $pro_data[0][deli_group] == "company"){
				$deli_group = "(위탁배송)";
			}else{
				$deli_group = "";
			}
			$provider_name = $pro_data[0]['provider_name'].$deli_group;

			// 배송그룹별 구매금액
			$order_price	= $ship_grp_price[$v['shipping_group_seq']];

			if($v['goods_rule']=='all'){
				$g_result = gift_order_check_all($v['gift_seq'], $v['gift_rule'], $order_price, $gift_loop);
				foreach($gift_goods as $key => $data){
					$real_target_goods[$gift_shipping[$key]][] = $data;
				}
			}else if($v['goods_rule']=='category'){

				$category_check			= false;
				$gift_provider_goods	= array();
				$gift_categorys			= array_unique($gift_categorys);

				//foreach($gift_categorys as $k=>$cate) $gift_categorys[$k] = "'".$cate."'";

				//구매 카테고리가 사은품 입점사와 연결되었는지 확인.
				if($gift_categorys){
					$query = "select
									a.category_code,a.goods_seq
							from
								fm_category_link as a
								left join fm_goods as b on a.goods_seq=b.goods_seq
							where
								a.category_code in(".implode(",",$gift_categorys).")
								and b.provider_seq='".$v['provider_seq']."'
								and b.goods_seq in(".implode(",",$gift_goods).")";
					$query = $this->db->query($query);
					foreach($query->result_array() as $data){
						$gift_provider_goods[$data['category_code']][] = $data['goods_seq'];
					}
					foreach($gift_provider_goods as $cate=>$goods) $gift_categorys2[] = "'".$cate."'";
				}

				//사은품 이벤트에 해당하는 카테고리인지 확인.
				if($gift_categorys2){
					$query = "SELECT category_code FROM fm_gift_choice WHERE category_code in(".implode(",",$gift_categorys2).") and gift_seq = '".$v['gift_seq']."'";
					$query = $this->db->query($query);
					foreach($query->result_array() as $cate){
						if($cate['category_code']) $category_check = true;
						$real_target_category[] = $cate['category_code'];
						foreach($gift_provider_goods[$cate['category_code']] as $key => $newgoods){
							$real_target_goods[$gift_shipping[$key]][] = $newgoods;
						}
					}
				}
				if($category_check){
					$g_result = gift_order_check_category($v['gift_seq'], $v['gift_rule'], $order_price, $gift_loop);
				}

			}else if($v['goods_rule']=='goods'){
				$goods_check = false;
				foreach($gift_goods as $key => $data){
					$sql = "SELECT count(*) as cnt FROM fm_gift_choice WHERE goods_seq = '{$data}' and gift_seq = '{$v['gift_seq']}'";
					$query = $this->db->query($sql);

					$boolen = $query->result_array();
					if($boolen[0]["cnt"] > 0){
						$goods_check = true;
						$real_target_goods[$gift_shipping[$key]][] = $data;
					}
				}
				if($goods_check){
					$g_result = gift_order_check_goods($v['gift_seq'], $v['gift_rule'], $order_price, $gift_loop);
				}
			}

			//사은품 재고 체크 - 단순 재고개수체크에서 판매설정에 따라 선택 가능하도록 수정 2018-09-12
			$gift_count	= count($g_result['goods']);
			for($i = 0; $i < $gift_count; $i++){
				$sql			= "select a.goods_view, a.goods_status
									from fm_goods a
									where  a.goods_seq = '".$g_result['goods'][$i]."' limit 1";
				$query			= $this->db->query($sql);
				$info			= $query->result_array();
				$display		= $info[0]['goods_view'];
				$goods_status	= $info[0]['goods_status'];
				if($display != "look" || $goods_status != "normal" || check_stock_option($g_result['goods'][$i]) !== true){
					unset($g_result['goods'][$i]);
				}
			}

			if( count($g_result['goods'])>0 ){
				$gifts['gift_seq']				= $v['gift_seq'];
				$gifts['title']					= $v['title'];
				$gifts['gift_contents']			= $v['gift_contents'];
				$gifts['gift_rule']				= $v['gift_rule'];
				$gifts['start_date']			= $v['start_date'];
				$gifts['end_date']				= $v['end_date'];
				$gifts['goods_rule']			= $v['goods_rule'];
				$gifts['ship_grp_seq']			= $v['shipping_group_seq'];
				$gifts['benefit_seq']			= $g_result['benefit_seq'];
				$gifts['goods']					= $g_result['goods'];
				$gifts['ea']					= $g_result['ea'];
				$gifts['provider_seq']			= $v['provider_seq'];
				$gifts['provider_name']			= $provider_name;
				$gifts['real_target_goods']		= $real_target_goods;
				$gifts['real_target_category']	= $real_target_category;
				$gloop[]						= $gifts;
				$gift_goods_cnt					+= count($g_result['goods']);
				$gift_cnt++;
			}
		}

		return array('gift_cnt' => $gift_cnt, 'gloop' => $gloop, 'gift_goods_cnt' => $gift_goods_cnt);
	}

	public function get_order2export_list($arr_where,$bind){
		$query = "select
				ord.order_seq,shi.shipping_seq,
				if(shi.shipping_method='coupon',io.item_option_seq,'') as coupon_option_seq
		from
				fm_order_shipping shi
				left join fm_order ord on ord.order_seq=shi.order_seq
				left join fm_order_item_option io on shi.shipping_seq=io.shipping_seq
		where ".implode(' and ',$arr_where)." group by shi.shipping_seq,coupon_option_seq order by shi.order_seq desc";

		return  $query;
	}


    /*  2015-07-21 상세 주문 할인 정보 - Added by jp
     *  $download_se        = 쿠폰 다운로드 코드
     *  $promotion_code_seq = 프로모션 발금 코드
     *  $referersale_seq    = 유입할인 코드
     */
    public function get_order_discount_info($download_seq = 0, $promotion_code_seq = 0, $referersale_seq = 0){
            $return             = array();

            $coupon_info        = false;
            $promotion_info     = false;
            $referersale_info   = false;

            //쿠폰정보
            if((int)$download_seq > 0){
                $query  = " SELECT  coupon_seq
                                  , coupon_name
                                  , sale_type
                                  , percent_goods_sale
                                  , won_goods_sale
                            FROM fm_download
                            WHERE download_seq = '{$download_seq}'";

                $result = $this->db->query($query);
                $data   = $result->row_array();

                $coupon_info['coupon_seq']  = $data['coupon_seq'];
                $coupon_info['coupon_name'] = $data['coupon_name'];
                $coupon_info['sale_text']   = ($data['sale_type'] == 'percent') ? "{$data['percent_goods_sale']}%" : "{$data['won_goods_sale']}원";
            }


            //프로모션코드 정보
            if((int)$promotion_code_seq > 0){
                $query  = "SELECT   info.promotion_seq      AS promotion_seq
                                  , info.promotion_name     AS promotion_name
                                  , info.sale_type          AS sale_type
                                  , info.percent_goods_sale AS percent_goods_sale
                                  , info.won_goods_sale     AS won_goods_sale
                            FROM
                                fm_promotion            info
                            LEFT JOIN
                                fm_download_promotion   down
                            ON  info.promotion_seq = down.promotion_seq
                            WHERE
                                download_seq = '{$promotion_code_seq}'";
                $result = $this->db->query($query);
                $data   = $result->row_array();

                $promotion_info['promotion_seq']    = $data['promotion_seq'];
                $promotion_info['promotion_name']   = $data['promotion_name'];
                $promotion_info['sale_text']        = ($data['sale_type'] == 'percent') ? "{$data['percent_goods_sale']}%" : "{$data['won_goods_sale']}원";
            }

            //유입할인 정보
            if((int)$referersale_seq > 0){
                $query  = "SELECT   referersale_name
                                  , referersale_url
                                  , sale_type
                                  , percent_goods_sale
                                  , won_goods_sale
                            FROM    fm_referersale
                            WHERE   referersale_seq = '{$referersale_seq}'";

                $result = $this->db->query($query);
                $data   = $result->row_array();

                $url_tmp    = explode('/', $data['referersale_url']);
                $referersale_info['referersale_name']       = $data['referersale_name'];
                $referersale_info['referersale_url']        = $data['referersale_url'];
                $referersale_info['referersale_url_short']  = $url_tmp[0];
                $referersale_info['sale_text']              = ($data['sale_type'] == 'percent') ? "{$data['percent_goods_sale']}%" : "{$data['won_goods_sale']}원";
            }

            $return['coupon_info']      = $coupon_info;
            $return['promotion_info']   = $promotion_info;
            $return['referersale_info'] = $referersale_info;

            return $return;
        }


    /*
     * 사용 쿠폰 리스트
    */
    public function use_coupon_list($sc)
	{
		$add_order_field = false;
		$sStart = $sc['sdate'] . " 00:00:00";
		$sEnd = $sc['edate'] . " 23:59:59";
		$sSearchTextLike = '%' . $sc['search_text'] . '%';

		if ($sc['sdate'] && !$sc['edate']) {
			$where[] = "down.regist_date >= ?";
			$bind[] = $sStart;
		}
		if ($sc['edate'] && !$sc['sdate']) {
			$where[] = "down.regist_date <= ?";
			$bind[] = $sEnd;
		}
		if ($sc['sdate'] && $sc['edate']) {
			$where[] = "down.regist_date BETWEEN ? AND ?";
			$bind[] = $sStart;
			$bind[] = $sEnd;
		}
		if ($sc['item_option_seq']) {
			$where[] = "ord.item_option_seq = ?";
			$bind[] = $sc['item_option_seq'];
		}

		if ( ! empty($sc['search_text'])){
			switch($sc['search_type']){
				case 'download_seq' :
					$where[] = "(down.download_seq = ? OR down.coupon_seq = ?)";
					$bind[] = $sc['search_text'];
					$bind[] = $sc['search_text'];
					break;
				case 'coupon_name' :
					$where[] = "down.coupon_name like ?";
					$bind[] = $sSearchTextLike;
					break;
				case 'order_seq' :
					$where[] = "(ord.order_seq = ? OR ship.order_seq = ? OR f_ord.order_seq = ?)";
					$bind[] = $sc['search_text'];
					$bind[] = $sc['search_text'];
					$bind[] = $sc['search_text'];
					$add_order_field = true;
					break;
				case 'goods_ordersheet_order_seq' :
					$where[] = "(ord.order_seq = ? OR f_ord.order_seq = ?)";
					$bind[] = $sc['search_text'];
					$bind[] = $sc['search_text'];
					$add_order_field = true;
					break;
				case 'item_order_seq':
					$where[] = "ord.order_seq = ?";
					$bind[] = $sc['search_text'];
					break;
				case 'shipping_order_seq' :
					$where[] = "ship.order_seq = ?";
					$bind[] = $sc['search_text'];
					$add_order_field = true;
					break;
				case 'ordersheet_order_seq' :
					$where[] = "f_ord.order_seq = ?";
					$bind[] = $sc['search_text'];
					break;
				case 'user_id' :
					$where[] = "mem.userid  like ?";
					$bind[] = $sSearchTextLike;
					break;
				case 'user_name' :
					$where[] = "mem.user_name like ?";
					$bind[] = $sSearchTextLike;
					break;
				default :
					$where[] = "(down.download_seq = ? OR down.coupon_seq = ? OR ship.order_seq = ? OR ord.order_seq = ? OR f_ord.order_seq = ? OR mem.user_name like ?
					OR mem.userid  like ? OR down.coupon_name like ?)";
					$bind[] = $sc['search_text'];
					$bind[] = $sc['search_text'];
					$bind[] = $sc['search_text'];
					$bind[] = $sc['search_text'];
					$bind[] = $sc['search_text'];
					$bind[] = $sSearchTextLike;
					$bind[] = $sSearchTextLike;
					$bind[] = $sSearchTextLike;
					$add_order_field = true;
					break;
			}
		}
		$bind[] = $sc['page'];
		$bind[] = $sc['perpage'];

		if ($where) {
			$sWhere = ' AND ' . implode(' AND ', $where);
		}

		$sql = "SELECT SQL_CALC_FOUND_ROWS
					ship.provider_seq,
					down.type,
					down.member_seq,
					down.download_seq,
					down.shipping_type,
					down.max_percent_shipping_sale,
					down.won_shipping_sale,
					down.sale_type,
					down.percent_goods_sale,
					down.max_percent_goods_sale,
					down.won_goods_sale,
					down.duplication_use,
					down.regist_date,
					down.use_date,
					down.limit_goods_price,
					down.coupon_same_time,
					down.issue_type,
					down.sale_payment,
					down.sale_referer,
					down.sale_agent,
					down.issue_startdate,
					down.issue_enddate,
					down.coupon_name,
					down.coupon_seq,
					mem.userid,
					IF(mem.user_name IS NOT NULL, mem.user_name, (SELECT bname FROM fm_member_business WHERE member_seq = down.member_seq)) AS user_names,
					ord.order_seq,
					ord.item_option_seq,
					f_ord.order_seq as f_order_seq
				FROM fm_download down
					LEFT JOIN fm_member mem ON mem.member_seq = down.member_seq
					LEFT JOIN fm_order_item_option ord ON down.download_seq = ord.download_seq
					LEFT JOIN fm_order_shipping ship force index (shipping_coupon_down_seq) ON down.download_seq = ship.shipping_coupon_down_seq
					LEFT JOIN fm_order f_ord force index (idx_ordersheet_seq) ON down.download_seq = f_ord.ordersheet_seq
				WHERE down.use_status = 'used' ".$sWhere."
				ORDER BY down.download_seq DESC
				LIMIT ?, ?";
		$query = $this->db->query($sql, $bind);
		$data['result'] = $query->result_array();

		//총건수
		$sql = "SELECT FOUND_ROWS() as COUNT";
		$query_count = $this->db->query($sql, $bind);
		$res_count = $query_count->result_array();
		$data['count'] = $res_count[0]['COUNT'];

		return $data;
	}

    /*
     * 사용 프로모션 코드 리스트
     */
    public function use_promotion_list($sc){
        if((int)$sc['no'] > 0){
            $where  .= ((int)$sc['item_option_seq'] > 0) ? " AND down.download_seq = '{$sc['no']}' AND ord.item_option_seq = '{$sc['item_option_seq']}'" : " AND down.download_seq = '{$sc['no']}'";
        }

        if((int)$sc['order_seq'] > 0)       $where  .= " AND down.order_seq = '{$sc['order_seq']}' AND down.type like '%shipping'";
        if($sc['sdate'] AND !$sc['edate'])  $where  .= " AND down.regist_date >= '{$sc['sdate']} 00:00:00'";
        if($sc['edate'] AND !$sc['sdate'])  $where  .= " AND down.regist_date <= '{$sc['edate']} 23:59:59'";
        if($sc['sdate'] AND $sc['edate'])   $where  .= " AND down.regist_date BETWEEN '{$sc['sdate']} 00:00:00' AND '{$sc['edate']} 23:59:59'";


		$add_order_field		= false;
		if(!empty($sc['search_text'])){
			switch($sc['search_type']){
				case	'download_seq' :
					$where	.= " AND down.download_seq = '{$sc['search_text']}' ";
					break;
				case	'promotion_code' :
					$where	.= " AND down.promotion_input_serialnumber = '{$sc['search_text']}' ";
					break;
				case	'order_seq' :
					$where	.= " AND ord.order_seq = '{$sc['search_text']}' ";
					$add_order_field = true;
					break;
				case	'user_id' :
					$where	.= " AND mem.userid  like '%{$sc['search_text']}%' ";
					break;
				case	'user_name' :
					$where	.= " AND mem.user_name like '%{$sc['search_text']}%' ";
					break;
				default :
					$where	.= " AND (ord.order_seq = '{$sc['search_text']}' OR down.promotion_input_serialnumber = '{$sc['search_text']}' OR mem.user_name like '%{$sc['search_text']}%' OR mem.userid  like '%{$sc['search_text']}%')";
					$add_order_field = true;
					break;
			}
		}


        //shipping_free
        $sql = "SELECT  SQL_CALC_FOUND_ROWS *
                      , (SUM(IFNULL(ord.promotion_code_sale,0)) + IFNULL(ship.shipping_promotion_code_sale, 0))	as order_promotion_code_sale
                      , mem.userid
                      , down.order_seq
                      , ord.item_option_seq
                      , IF(mem.user_name IS NOT NULL, mem.user_name, (SELECT bname FROM fm_member_business WHERE member_seq = down.member_seq)) AS user_names
					  , down.*
                FROM        fm_download_promotion down
                LEFT JOIN   fm_member   mem
                    ON      mem.member_seq = down.member_seq
                LEFT JOIN   fm_order_item_option ord
                    ON      down.order_seq = ord.order_seq
				LEFT JOIN	fm_order_shipping ship
					ON		down.download_seq = ship.shipping_promotion_code_seq
                WHERE       down.use_status = 'used' {$where}
				GROUP BY	down.download_seq
                ORDER BY    down.download_seq DESC
                LIMIT {$sc['page']}, {$sc['perpage']}";

        $query          = $this->db->query($sql);
        $data['result'] = $query->result_array();


        //총건수
        $sql            = "SELECT FOUND_ROWS() as COUNT";
        $query_count    = $this->db->query($sql);
        $res_count      = $query_count->result_array();
        $data['count']  = $res_count[0]['COUNT'];

        return $data;
    }


	public function get_item_one($item_seq){
		$cancelquery		= "select * from fm_order_item where item_seq=?";
		$cancelquery		= $this->db->query($cancelquery,array($item_seq));
		$orditemData		= $cancelquery->row_array();
		return $orditemData;
	}

	public function clearance_unique_personal_code($clearance_unique_personal_code,$order_seq){
		$query = "update fm_order set clearance_unique_personal_code=? where order_seq=?";
		$this->db->query($query,array($clearance_unique_personal_code,$order_seq));
	}

	public function get_recent_order($member_seq){
		$query = "SELECT oi.goods_seq
		FROM fm_order_item oi inner join fm_order o ON  oi.order_seq = o.order_seq
		WHERE member_seq=?
		ORDER BY o.regist_date DESC,oi.item_seq DESC limit 1";
		$query = $this->db->query($query,array($member_seq));
		$row = $query->result_array();

		return $row[0]['goods_seq'];
	}

	# 주문시 스킨정보(관리자 스킨별 검색용) @2016-06-17 pjm
	public function set_order_skin_log(){

		$sql = "select count(skin_seq) cnt from fm_order_skin_log where skin_seq=?";
		$que = $this->db->query($sql,$this->skin_config['skin_seq']);
		$res = $que->row_array();
		if((int)$res['cnt'] == 0){
			$sql = "insert into fm_order_skin_log (skin_seq,skin_name,update_date)
					values('".$this->skin_config['skin_seq']."','".$this->skin_config['skin_name']."',now())";
		}else{
			$sql = "update fm_order_skin_log set
						skin_name='".$this->skin_config['skin_name']."',update_date = now()
					where skin_seq='".$this->skin_config['skin_seq']."'";
		}
		$this->db->query($sql);

	}

	/**
	* 네이버페이 모듈에서 최초배송비를 계산하고 있어서 재사용합니다.
	* 동일 배송그룹내 총 배송비(결제시 지불한 배송비)
	* @2016-07-21
	**/
	public function get_delivery_existing_price($order_seq,$shipping_seq){
		$this->load->model('naverpaymodel');
		$existing_delivery_price	= $this->naverpaymodel->get_delivery_existing_price($order_seq,$shipping_seq);
		return $existing_delivery_price;
	}

	/* 주문수정 */
	public function set_order($set_params,$where_params)
	{
		$this->db->where($where_params);
		return $this->db->update('fm_order', $set_params);
	}

	public function get_data_item($params,$orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		return $this->db->get('fm_order_item');
	}

	public function get_data_item_option($params,$orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		return $this->db->get('fm_order_item_option');
	}

	public function get_last_item_option_by_product($params,$orderbys=''){
		$this->db->where($params);
		// ea 0 인 경우, selector 가 아닌 suboption 의 option 데이터이므로 할당하지 않음
		$this->db->where('ea >', 0);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		return $this->db->get('fm_order_item_option');
	}

	public function get_data_item_suboption($params,$orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		return $this->db->get('fm_order_item_suboption');
	}

	public function get_last_item_suboption_by_product($params,$orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		return $this->db->get('fm_order_item_suboption');
	}

	public function get_data_item_input($params,$orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		return $this->db->get('fm_order_item_input');
	}

	public function get_issue_count($start_date){
		// 주문처리 수
		$union_query[] = "
		select count(*) as cnt, 'order' as 'type'
		from fm_order
		where step in ('15','25','35','40','50','60','70') and hidden = 'N' and regist_date >= ? and (label IS NULL OR (label='present' AND recipient_zipcode !=''))";

		// 촐고처리 수
		$union_query[] = "
		select count(*) as cnt,'export' as 'type'
		from (
			select 1 as cnt from fm_goods_export exp LEFT JOIN fm_order ord ON ord.order_seq=exp.order_seq, fm_goods_export_item as item
			where ord.regist_date >= ? and exp.export_code = item.export_code and exp.status in ('45','55','65') and not(exp.status='45' and ord.step='85')
			group by exp.export_seq
		) as u1";

		// 반품처리 수
		$union_query[] = "
		select count(*) as cnt, 'returns' as 'type'
		from fm_order_return rt left join fm_order rt_ord on rt.order_seq=rt_ord.order_seq
		where `status` in ('request','ing') and rt_ord.regist_date >= ?
		";

		// 환불처리 수
		$union_query[] = "
		select count(*) as cnt, 'refund' as 'type'
		from fm_order_refund rf left join fm_order rf_ord on rf.order_seq=rf_ord.order_seq
		where `status` in ('request','ing') and rf_ord.regist_date >= ?
		";

		$sql = "
		SELECT *
		FROM (
			".implode(' union ',$union_query)."
		) as a
		";
		return $this->db->query($sql,array($start_date,$start_date,$start_date,$start_date));
	}

	public function get_issue_count_provider($start_date,$provider_seq){
		$union_query[] = "
		SELECT count(*) AS cnt,'order' AS 'type' from (
				SELECT 1 as cnt
				FROM fm_order u1_ord
				LEFT JOIN fm_order_item u1_item ON u1_ord.order_seq = u1_item.order_seq
				LEFT JOIN fm_order_item_option u1_opt ON u1_opt.item_seq = u1_item.item_seq
				INNER JOIN fm_order_shipping u3_shi ON u1_opt.shipping_seq=u3_shi.shipping_seq AND u3_shi.provider_seq = ?
				LEFT JOIN fm_order_item_suboption u1_sub ON u1_sub.item_seq = u1_item.item_seq
				WHERE (u1_opt.step IN ('25','35') or u1_sub.step IN ('25','35')) AND u1_ord.hidden ='N'
				AND u1_item.provider_seq = ?
				AND u1_ord.regist_date >= ?
				and (label IS NULL OR (label='present' AND recipient_zipcode !=''))
				GROUP BY u1_ord.order_seq
		) as u1
		";
		$union_query[] = "
		SELECT count(*) as cnt,'export' as 'type' from (
				SELECT 1 as cnt
				FROM fm_goods_export u2_exp
					LEFT JOIN fm_order u2_ord ON u2_ord.order_seq=u2_exp.order_seq,
				fm_goods_export_item u2_item
					LEFT JOIN fm_order_item u2_oitem ON u2_oitem.item_seq = u2_item.item_seq
				WHERE u2_exp.export_code = u2_item.export_code
				AND u2_oitem.order_seq = u2_ord.order_seq
				AND u2_exp.status in ('45','55','65')
				AND not(u2_exp.status='45' and u2_ord.step='85')
				AND u2_oitem.provider_seq = ?
				AND u2_ord.regist_date >= ?
				GROUP BY u2_exp.export_seq
		) as u2
		";
		$union_query[] = "
		SELECT count(*) as cnt, 'returns' as 'type'
		FROM fm_order_return rt
			LEFT JOIN fm_order rt_ord ON rt.order_seq=rt_ord.order_seq
		WHERE rt.status in ('ing','request')
		AND rt.return_code in (
			SELECT rt_a.return_code
			FROM fm_order_return_item rt_a,
			fm_order_item rt_b
			WHERE rt_a.item_seq=rt_b.item_seq
			AND rt_b.provider_seq = ?
		)
		AND rt_ord.regist_date >= ?
		";
		$union_query[] = "
		SELECT count(*) as cnt, 'refund' as 'type'
		FROM 	fm_order_refund rf
			LEFT JOIN fm_order rf_ord on rf.order_seq=rf_ord.order_seq
		WHERE rf.status in ('ing','request')
		AND rf.refund_code in (
			SELECT rf_a.refund_code
			FROM fm_order_refund_item rf_a,
			fm_order_item rf_b
			WHERE rf_a.item_seq=rf_b.item_seq
			AND rf_b.provider_seq=?
		)
		AND rf_ord.regist_date >= ?
		";

		$union_query[] = "
		SELECT count(*) AS cnt, 'company_catalog' AS 'type' from (
				SELECT 1 as cnt
				FROM fm_order u3_ord
				LEFT JOIN fm_order_item u3_item ON u3_ord.order_seq = u3_item.order_seq
				LEFT JOIN fm_order_item_option u3_opt ON u3_opt.item_seq = u3_item.item_seq
				INNER JOIN fm_order_shipping u3_shi ON u3_opt.shipping_seq=u3_shi.shipping_seq AND u3_shi.provider_seq=1
				LEFT JOIN fm_order_item_suboption u3_sub ON u3_sub.item_seq = u3_item.item_seq
				WHERE (u3_opt.step IN ('25','35') or u3_sub.step IN ('25','35')) AND u3_ord.hidden ='N'
				AND u3_item.provider_seq = ?
				AND u3_ord.regist_date >= ?
				GROUP BY u3_ord.order_seq
		) as u3
		";

		$sql = "
		SELECT *
		FROM (	".implode(' union ',$union_query).") as a
		";
		$bind = array($provider_seq,$provider_seq,$start_date,$provider_seq,$start_date,$provider_seq,$start_date,$provider_seq,$start_date,$provider_seq,$start_date);
		return $this->db->query($sql,$bind);
	}

	// 최근 배송메세지 추출 :: 2016-11-03 lwh
	public function get_ship_message($member_seq, $limit='5'){
		$sql = "
			SELECT
				(case when o.each_msg_yn = 'Y' then opt.ship_message else o.memo end) as ship_message
			FROM
				fm_order as o,fm_order_item_option as opt
			WHERE
				o.order_seq=opt.order_seq
				and o.member_seq = ?
				and (case when o.each_msg_yn = 'Y' then opt.ship_message else o.memo end) != ''
			GROUP BY
				(case when o.each_msg_yn = 'Y' then opt.ship_message else o.memo end)
			ORDER BY
				o.regist_date desc
			LIMIT 0, " . $limit;
		$query	= $this->db->query($sql,$member_seq);
		$row	= $query->result_array();

		foreach($row as $k => $v){
			$ptArr	= array(chr(10), chr(13), "\n", "\r");
			$msgArr[]['ship_message'] = str_replace($ptArr,"",$v['ship_message']);
		}

		if($limit == 1)	$msgArr = $msgArr[0];

		return $msgArr;
	}

	// 무통장 입금 주문 목록
	public function get_bank_order_list($sc){
		$selectSql	= "SELECT * ";
		$fromSql	= "FROM fm_order ";
		$whereSql	= "WHERE payment = 'bank' ";
		$groupSql	= "";
		$orderSql	= "ORDER BY order_seq desc ";
		$limitSql	= "";

		// 주문접수일 검색
		if		($sc['srcSdate']){
			$whereSql	.= " AND regist_date >= '{$sc['srcSdate']} 00:00:00' ";
		}
		if		($sc['srcEdate']){
			$whereSql	.= " AND regist_date <= '{$sc['srcEdate']} 23:59:59' ";
		}
		// 은행 정보 검색
		if		($sc['srcBkname'] && $sc['srcBknum']){
			if($sc['srcBkname'] == '하나은행'){
				$whereSql	.= " AND (replace(bank_account,'-','') like '{$sc['srcBkname']} {$sc['srcBknum']}%' || replace(bank_account,'-','') like 'KEB{$sc['srcBkname']} {$sc['srcBknum']}%')";
			} else {
				$whereSql	.= " AND replace(bank_account,'-','') like '{$sc['srcBkname']} {$sc['srcBknum']}%' ";
			}
		}elseif	($sc['srcBkname']){
			if($sc['srcBkname'] == '하나은행'){
				$whereSql	.= " AND (replace(bank_account,'-','') like '{$sc['srcBkname']}%' || replace(bank_account,'-','') like 'KEB{$sc['srcBkname']}%') ";
			} else {
				$whereSql	.= " AND replace(bank_account,'-','') like '{$sc['srcBkname']}%' ";
			}
		}elseif	($sc['srcBknum']){
			$whereSql	.= " AND replace(bank_account,'-','') like '%{$sc['srcBknum']}%' ";
		}
		// 입금자명 검색
		if		($sc['srcBkjukyo']){
			$whereSql	.= " AND depositor = '{$sc['srcBkjukyo']}' ";
		}
		// 결제금액 검색
		if		($sc['srcSprice']){
			$whereSql	.= " AND settleprice >= '{$sc['srcSprice']}' ";
		}
		if		($sc['srcEprice']){
			$whereSql	.= " AND settleprice <= '{$sc['srcEprice']}' ";
		}
		// 주문 상태 검색
		if		(is_array($sc['srcStatus'])){
			if		(in_array('Y', $sc['srcStatus'])){
				for	($s = 25; $s <= 75; $s+=5){
					if	($s != 30)	$step[]	= $s;
				}
			}
			if		(in_array('N', $sc['srcStatus'])){
				$step[]	= 15;
			}
			if		(in_array('R', $sc['srcStatus'])){
				$step[]	= 95;
			}
			if		(is_array($step) && count($step) > 0){
				$whereSql	.= " AND step in ('" . implode("', '", $step) . "') ";
			}
		}
		// paging 처리
		if		($sc['page'] > 0 && $sc['perpage'] > 0){
			$totalCount	= $sc['totalCnt'];
			if	(!$totalCount){
				if	($groupSql){
					$sql	= "select count(*) as total from ( " . $selectSql . $fromSql . $whereSql . $groupSql . " ) as tmp ";
				}else{
					$sql	= "select count(*) as total " . $fromSql . $whereSql;
				}
				$query		= $this->db->query($sql);
				$row		= $query->row_array();
				$totalCount	= $row['total'];
			}
			// page 계산
			$result['pagination']	= get_pagination_info($totalCount, $sc['page'], $sc['perpage'], 10, 'searchOrderList');

			$limitSql				= " LIMIT " . $result['pagination']['slimit'] . ", " . $sc['perpage'] . " ";
		}

		$sql							= $selectSql . $fromSql . $whereSql . $groupSql . $orderSql . $limitSql;
		$query							= $this->db->query($sql);
		$result['record']				= $query->result_array();

		return $result;
	}

	// 주문서의 수동입금확인 매칭값 update
	public function set_marking_autodeposit($bkcode, $order_seq, $type){
		$sql	= "UPDATE fm_order set autodeposit_key = ?, autodeposit_type = ? where order_seq = ? ";
		$this->db->query($sql, array($bkcode, $type, $order_seq));
	}

	// 주문서 정보 가져오기
	public function get_order_bank_account($order_seq){
		$sql = "
		SELECT
			bank_account
		FROM
			fm_order
		WHERE order_seq=?
		";

		$binds[]	= $order_seq;
		$query = $this->db->query($sql,$binds);
		list($bankAccount) = $query->result_array($query);
		return $bankAccount;
	}

	/* #16651 2018-07-10 ycg 관리자 메모 기능 개선 */
	//관리자 메모 전체내역 불러오기
	function get_order_memo($iOrder_seq, $memo_idx = null){
	    if($memo_idx > 0){
	        $sqlWhere = " AND a.memo_idx = ?";
	        $sqlArr    = array($iOrder_seq, $memo_idx);
	    } else {
	        $sqlWhere  = "";
	        $sqlArr    = array($iOrder_seq);
	    }

	    $sql = "SELECT a.*, b.provider_name
                FROM
                    fm_order_memo a INNER JOIN fm_provider b ON b.provider_seq = a.provider_seq
                WHERE
                    a.order_seq = ?".$sqlWhere." ORDER BY memo_idx DESC";

	    $query = $this->db->query($sql, $sqlArr);
		$aResult = $query->result_array();

		return $aResult;
	}

	//관리자 메모 선택 삭제
	function del_order_memo($iMemo_idx){
		$sql = "DELETE FROM fm_order_memo WHERE memo_idx='".$iMemo_idx."'";
		$bResult = $this->db->query($sql);

		return $bResult;
	}
	//관리자 메모 수정시 선택 조회
	function sel_order_memo($iMemo_idx){
		$sql = "SELECT admin_memo FROM fm_order_memo WHERE memo_idx='".$iMemo_idx."'";
		$query = $this->db->query($sql);
		$sResult = $query->row();

		return $sResult;
	}
	/* #16651 2018-07-10 ycg 관리자 메모 기능 개선 */

	function order_gift_partial_cancel($order_seq, $gift_item_seq, $data_order_items, $type='refund') {
		if(!$this->giftmodel) $this->load->model('giftmodel');
		$gifts = $this->giftmodel->get_gift_order_log($order_seq,$gift_item_seq);



		foreach( $gifts as $key => $g ) {
			$check_item = array();
			$remain_price = $total = $cancel = 0;

			list($gift) = $this->ordermodel->get_option_for_item($g['item_seq']);
			if( $gift['ea'] == $gift['step85'] || $gift['ea'] == $gift['refund_ea'] ) {
				// 이미 취소된 사은품이면 다음 사은품 체크
				continue;
			}

			/*
			1. 배송그룹 (gift의 item_esq 동일한 shipping_group_seq 찾기)
			 - 해당 배송그룹에 맞는 상품은 이미 주문 시 정해져서 target_goods 에 할당됨
			2. 전체 or 카테고리 or 상품
			 - 해당하는 상품 고르기
			*/
			$g['target_goods'] = unserialize($g['target_goods']);
			foreach($data_order_items as $item){
				if(in_array($item['goods_seq'],$g['target_goods'])) {
					$check_item[] = $item;
				}
			}
			/*
			3. 사은품 금액 조건
			 - 마지막으로 금액 확인하기
			*/
			foreach($check_item as $citem) {
				$options = $this->ordermodel->get_option_for_item($citem['item_seq']);
				foreach($options as $opt) {
					// 총 상품금액 더하기
					$total += $opt['price'] * $opt['ea'];
					// 취소 선택한 상품 찾아서 취소 금액 구하기
					$item_chk = array_search($citem['item_seq'], $_POST['chk_item_seq']);
					if($item_chk !== false ) {
						$cancel += $opt['price'] * $_POST['chk_ea'][$item_chk];		// 이번에 취소한 금액
					}
					if ( $opt['step'] == '85' || $opt['refund_ea'] > 0) {
						$cancel += $opt['price'] * $opt['step85'];					// 이전에 취소한 금액
						$cancel += $opt['price'] * $opt['refund_ea'];				// 이전에 반품한 금액
					}
				}
			}



			// 남은금액 = 총상품금액 - 취소할 금액 구해서 취소여부 판단하기
			$remain_price = $total - $cancel;

			if($remain_price < $g['benefit_sprice']) {
				// 첫번째는 물어봄
				if( $_POST['gitfcancel']!='ok' ) {
					//사은품 이벤트 조건에 충족하지 않아 사은품은  취소처리 됩니다.
					openDialogConfirm(getAlert("mo155"),400,160,'parent','parent.$("form[name=\'refundForm\']").append("<input type=\'hidden\' name=\'gitfcancel\' id=\'gitfcancel\' value=\'ok\'/>");parent.$("form[name=\'refundForm\']").submit();','parent.location.reload();');
					exit;
				}
				// 취소 해야하는 사은품을 gift_cancel에 담음 모든 사은품 조건 체크 후 취소처리예쩡
				if( $type == 'refund' ) {		// 환불
					$gift_cancel[] = array(
						'item_seq'			=> $gift['item_seq'],
						'item_option_seq'	=> $gift['item_option_seq'],
						'ea'				=> $gift['ea']
					);
				} else {						// 반품
					$chk = array();
					$chk['item_seq']		= $gift['item_seq'];
					$chk['option_seq']		= $gift['item_option_seq'];

					$gexport = $this->exportmodel->get_export_item_by_item_seq('',$chk);
					$gift_cancel[] = array(
						'item_seq'			=> $gexport['item_seq'],
						'item_option_seq'	=> $gexport['option_seq'],
						'ea'				=> $gexport['ea'],
						'export_code'		=> $gexport['export_code'],
					);
				}
			}
		}
		return $gift_cancel;
	}

	// 상품 출고량 업데이트(패키지상품 포함) @2019-02-28 pjm
	public function _release_reservation($order_seq){

		if(!$this->orderpackagemodel) $this->load->model('orderpackagemodel');

		// 출고량 업데이트를 위한 변수선언
		$r_reservation_goods_seq	= array();
		$r_package_goods_seq		= array();
		$return						= array();

		$result_option				= $this->get_item_option($order_seq);
	   	$result_suboption			= $this->get_item_suboption($order_seq);

		if($result_option){
			foreach($result_option as $data_option){
				$providerList[$data_option['provider_seq']]	= 1;
				$r_reservation_goods_seq[]					= $data_option['goods_seq'];			// 출고량 업데이트를 위한 변수정의
				// 패키지 상품 재고 변경
				if($data_option['package_yn'] == 'y'){
					$result_option_package = $this->orderpackagemodel->get_option($data_option['item_option_seq']);
					foreach($result_option_package as $data_option_package){
						$r_package_goods_seq[] = $data_option_package['goods_seq'];
					}
				}
			}
		}
		if($result_suboption){
			foreach($result_suboption as $data_suboption){
				$r_reservation_goods_seq[] = $data_suboption['goods_seq'];			// 출고량 업데이트를 위한 변수정의
				// 패키지 상품 재고 변경
				if($data_suboption['package_yn'] == 'y'){
					$result_option_package = $this->orderpackagemodel->get_suboption($data_suboption['item_option_seq']);
					foreach($result_option_package as $data_option_package){
						$r_package_goods_seq[] = $data_option_package['goods_seq'];
					}
				}
			}
		}

		if($r_reservation_goods_seq) $r_reservation_goods_seq = array_unique($r_reservation_goods_seq);
		if($r_package_goods_seq) $r_package_goods_seq = array_unique($r_package_goods_seq);

		// 출고예약량 업데이트
		foreach($r_reservation_goods_seq as $goods_seq){
			$this->goodsmodel->modify_reservation_real($goods_seq);
		}
		// 패키지 품절 체크
		foreach($r_package_goods_seq as $goods_seq){
			$this->goodsmodel->runout_check($goods_seq);
		}

		$return['providerList'] = $providerList;

		return $return;
	}

	## 하위 주문번호 조회(원주문의 맞교환(재주문)건)
	function get_order_seqs_by_top_orign_order_seq($order_seq){
		$query = "select order_seq from fm_order where top_orign_order_seq=?";
		$query = $this->db->query($query,array($order_seq));
		foreach($query->result_array() as $sub_order){
			$all_order_seq[] = $sub_order['order_seq'];
		}
		return $all_order_seq;
	}

	function get_order_info($order_seq=''){

		if(!$order_seq) {
			return false;
		}

		$query = $this->db->select('*')
		->from('fm_order')
		->where('order_seq', $order_seq)
		->get();

		$ori_order							= $query->row_array();
		$data_order['settleprice']			= $ori_order['settleprice'];
		$data_order['cash']					= $ori_order['cash'];
		$data_order['emoney']				= $ori_order['emoney'];
		$data_order['enuri']				= $ori_order['enuri'];
		$data_order['shipping_cost']		= $ori_order['shipping_cost'];
		$data_order['order_cellphone']		= $ori_order['order_cellphone'];
		$data_order['order_email']			= $ori_order['order_email'];
		$data_order['recipient_cellphone']	= $ori_order['recipient_cellphone'];
		$data_order['recipient_email']		= $ori_order['recipient_email'];

		return $data_order;
	}

	/**
	 * fm_order_item_{option|subopion} update function
	 * param : 업데이트 array
	 * option_seq : option_seq(suboption_seq)
	 * mode : {option:suboption}
	 */
	function set_order_item_option($params, $option_seq, $mode="option") {
		if($mode == "option") {
			$table = "fm_order_item_option";
			$field = "item_option_seq";
		} else {
			$table = "fm_order_item_suboption";
			$field = "item_suboption_seq";
		}
		$filter_params = filter_keys($params, $this->db->list_fields($table));

		foreach($filter_params as $key => $data) {
			if($key == "refund_ea_plus") {
				$this->CI->db->set($key,'refund_ea+'.$data,false);
			} else if($key == "refund_ea_minus") {
				$this->CI->db->set($key,'refund_ea-'.$data,false);
			} else {
				$this->db->set($key, $data);
			}
		}
		$result = $this->db->where($field, $option_seq)->update($table);
		return $result;
	}

	/**
	 * fm_order_item JOIN fm_order_item_{option|subopion}
	 * param : where 절
	 * mode : {option:suboption}
	 */
	function get_item_join_option($params, $mode="option") {
		$result = array();
		$query = $this->db->select("*")->from("fm_order_item item");

		if($mode == "option") {
			$query->join("fm_order_item_option opt", "opt.item_seq = item.item_seq", "left");
		}else if($mode == "suboption") {
			$query->join("fm_order_item_suboption opt", "opt.item_seq = item.item_seq", "left");
		}
		if($params) {
			$query->where($params);
		}
		$query		= $query->get();
		return $query->result_array();
	}

	/**
	 * fm_order 조회
	 */
	function get_order_basic($params=array()) {
		$query = $this->db->select("*")->from("fm_order ord");
		if($params) {
			$query->where($params);
		}
		$query = $query->get();
		return $query->result_array();
	}

	//회원번호로 주문데이터 조회
	function getOrderForMemberSeq($member_seq, $order_field = '*') {
		$default_field = ['member_seq', 'order_seq', 'regist_date', 'deposit_date'];
		if (is_array($order_field)) {
			$order_field = array_merge($default_field, $order_field);
		}

		return $this->db->select($order_field)
				->from('fm_order')
				->where_in('member_seq', $member_seq)
				->get()
				->result_array();
	}

	// 주문테이블 업데이트 batch
	function updateOrderData($updateParams, $where) {
		// where절 검증
		if (empty($where)) {
			return false;
		}
		foreach ($updateParams as $param) {
			if (empty($param[$where])) {
				return false;
			}
		}

		return $this->db->update_batch('fm_order', $updateParams, $where);
	}

	// 주문로그 테이블 업데이트
	function updateOrderLog($updateParams, $order_seq, $actor='') {
		if (empty($order_seq)) {
			return false;
		}

		$this->db->where('order_seq', $order_seq);
		$this->db->where('actor', $actor);
		return $this->db->update('fm_order_log', $updateParams);
	}
}

/* End of file ordermodel.php */
/* Location: ./app/models/ordermodel.php */
