<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class kcp_mobile extends front_base {
	public function __construct(){
		parent::__construct();
		$this->load->helper('order');
		$this->load->helper('shipping');
		$this->load->model('ordermodel');
		$this->load->model('cartmodel');
		$this->load->model('membermodel');
		$this->load->model('couponmodel');
		$this->load->model('promotionmodel');
		$this->load->model('goodsmodel');
		$this->load->model('salesmodel');
		$this->load->library('validation');
		$this->load->library('added_payment');
		$this->cfgPg = config_load($this->config_system['pgCompany']);
		$this->cfgOrder = config_load('order');
	}

	public function _site_conf_inc()
	{
		global $pg;
		$pg = $this->cfgPg;
		if( isset($pg['pcCardCompanyCode']) ){
			foreach($pg['pcCardCompanyCode'] as $key => $code){
				$arr = explode(',',$pg['pcCardCompanyTerms'][$key]);
				$terms = array();
				foreach($arr as $term){
					$terms[] = sprintf('%02d',$term);
				}
				$codes[] = $code . '-' . implode(':',$terms);
			}
			$pg_param['kcp_noint_quota'] = implode(',',$codes);
		}
		$pg_param['quotaopt']  = $pg['interestTerms'];
		$pg_param['g_conf_home_dir']  = ROOTPATH."pg/kcp_mobile";
		$pg_param['g_conf_gw_url']	= $pg['mallCode']=='T0007' ? "testpaygw.kcp.co.kr" : "paygw.kcp.co.kr";
		$pg_param['g_conf_site_cd']   = $pg['mallCode'];
		$pg_param['g_conf_site_key']  = $pg['merchantKey'];
		$pg_param['g_conf_site_name'] = $this->config_basic['shopName'];
		$pg_param['g_conf_log_level'] = "3";
		$payment = str_replace('escrow_','',$pg_param['payment']);
		if($payment != $pg_param['payment']){
			$pg_param['escorw'] = 1;
			$pg_param['payment'] = $payment;
		}

		$pg_param['g_wsdl'] = ROOTPATH."pg/kcp_mobile/sample/common/real_KCPPaymentService.wsdl";

		$pg_param['g_conf_gw_port']   = "8090";		// 포트번호(변경불가)

		return $pg_param;
	}

	public function auth()
	{
		header("Content-Type: text/html; charset=UTF-8");

		$aPostParams = $this->input->post();
		$pg_param = $this->_site_conf_inc();
		$data_orders = $this->ordermodel->get_order($aPostParams['order_seq']);
		if	($data_orders['pg_currency'] == 'KRW'){
			$data_orders['settleprice']	= floor($data_orders['settleprice']);
		}

		$g_conf_home_dir  = $pg_param['g_conf_home_dir'];	   // BIN 절대경로 입력 (bin전까지)
		$g_conf_gw_url = $pg_param['g_conf_gw_url'];
		$g_conf_site_cd   = $pg_param['g_conf_site_cd'];
		$g_conf_site_key  = $pg_param['g_conf_site_key'];
		$g_conf_site_name = $pg_param['g_conf_site_name'];
		$g_wsdl = $pg_param['g_wsdl'];
		$g_conf_gw_port = $pg_param['g_conf_gw_port'];

		// 비과세금액
		$r_param['comm_free_mny'] = $aPostParams['comm_free_mny'];

		// 과세금액
		$r_param['comm_tax_mny'] = $aPostParams['comm_tax_mny'];

		// 부가세
		$r_param['comm_vat_mny'] = $aPostParams['comm_vat_mny'];

		## 장바구니 상품정보
		$goods_info = unserialize($aPostParams["goods_info"], ['allowed_classes' => false]);
		if($goods_info){
			$good_info = '';
			foreach($goods_info as $k=>$item){
				if(!$item['good_cntx']) $item['good_cntx'] = 1;
				if(!$item['good_amtx']) $item['good_amtx'] = 0;
				$good_info .= "seq=".($k+1).chr(31);
				$good_info .= "ordr_numb=".$item['ordr_numb'].chr(31);
				$good_info .= "good_name=".addslashes(substr($item['good_name'],0,30)).chr(31);
				$good_info .= "good_cntx=".$item['good_cntx'].chr(31);
				$good_info .= "good_amtx=".$item['good_amtx'].chr(30);
			}
		}
		$r_param['g_conf_home_dir']		= $g_conf_home_dir;
		$r_param['g_conf_gw_url']		= $g_conf_gw_url;
		$r_param['g_conf_site_cd']		= $g_conf_site_cd;
		$r_param['g_conf_site_key']		= $g_conf_site_key;
		$r_param['g_conf_site_name']	= $g_conf_site_name;
		$r_param['g_wsdl']				= $g_wsdl;
		$r_param['g_conf_gw_port']		= $g_conf_gw_port;
		$r_param['req_tx']				= $aPostParams["req_tx"]; // 요청 종류
		$r_param['res_cd']				= $aPostParams["res_cd"]; // 응답 코드
		$r_param['tran_cd']				= $aPostParams["tran_cd"]; // 트랜잭션 코드
		$r_param['goods_name']			= $aPostParams["goods_name"]; // 상품명
		$r_param['good_info']			= $good_info; // 장바구니 상세정보(에스크로)
		$r_param['bask_cntx']			= count($goods_info); // 장바구니갯수(수량아님)(에스크로)
		$r_param['use_pay_method']		= $aPostParams[ "use_pay_method" ]; // 결제 방법
		$r_param['enc_info']			= $aPostParams["enc_info" ]; // 암호화 정보
		$r_param['enc_data']			= $aPostParams["enc_data"]; // 암호화 데이터
		$r_param['param_opt_1']			= $param_opt_1 = $aPostParams["param_opt_1"]; // 기타 파라메터 추가 부분
		$r_param['param_opt_2']			= $param_opt_2 = $aPostParams["param_opt_2"]; // 기타 파라메터 추가 부분
		$r_param['param_opt_3']			= $param_opt_3 = $aPostParams["param_opt_3"]; // 기타 파라메터 추가 부분
		$r_param['tablet_size']			= $tablet_size	  = "1.0"; // 화면 사이즈 조정 - 기기화면에 맞게 수정(갤럭시탭,아이패드 - 1.85, 스마트폰 - 1.0)
		$r_param['payment']				= $aPostParams['payment'];

		// 주문 무효일자 추출 :: 2015-08-10 lwh
		$order_cfg = $this->cfgOrder;
		if($order_cfg['autocancel'] == 'y'){
			$r_param['ipgm_date'] = date("Ymd",time()+24*3600*$order_cfg['cancelDuration']);
		}else{
			$r_param['ipgm_date'] = date("Ymd",time()+24*3600*3);
		}
		$r_param['cancelDuration'] = $order_cfg['cancelDuration'];

		$this->template->assign($r_param);
		$this->template->assign($data_orders);
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_kcp_auth.html'));
		$this->template->print_('tpl');
	}

	public function approval()
	{
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate, pre-check=0");
		header("Pragma: no-cache");
		ini_set("soap.wsdl_cache_enabled", 0);

		$aGetParams	= $this->input->get();
		$pg_param = $this->_site_conf_inc();

		$g_conf_home_dir  = $pg_param['g_conf_home_dir'];	   // BIN 절대경로 입력 (bin전까지)
		$g_conf_gw_url = $pg_param['g_conf_gw_url'];
		$g_conf_site_cd   = $pg_param['g_conf_site_cd'];
		$g_conf_site_key  = $pg_param['g_conf_site_key'];
		$g_conf_site_name = $pg_param['g_conf_site_name'];
		$g_wsdl = $pg_param['g_wsdl'];
		$g_conf_gw_port = $pg_param['g_conf_gw_port'];

		require ROOTPATH."pg/kcp_mobile/sample/common/KCPComLibrary.php";			  // library [수정불가]

		// 쇼핑몰 페이지에 맞는 문자셋을 지정해 주세요.
		$charSetType	  = "utf-8";			 // UTF-8인 경우 "utf-8"로 설정

		$siteCode		 = $aGetParams['site_cd'];
		$orderID		  = $aGetParams['ordr_idxx'];
		$paymentMethod	= $aGetParams['pay_method'];
		$escrow		   = ( $aGetParams['escw_used'] == "Y" ) ? true : false;
		$productName	  = $aGetParams['good_name'];

		// 아래 두값은 POST된 값을 사용하지 않고 서버에 SESSION에 저장된 값을 사용하여야 함.
		$paymentAmount	= $aGetParams['good_mny']; // 결제 금액
		$returnUrl		= $aGetParams['Ret_URL'];

		// Access Credential 설정
		$accessLicense	= "";
		$signature		= "";
		$timestamp		= "";

		// Base Request Type 설정
		$detailLevel	  = "0";
		$requestApp	   = "WEB";
		$requestID		= $orderID;
		$userAgent		= $_SERVER['HTTP_USER_AGENT'];
		$version		  = "0.1";

		try
		{
			$payService = new PayService( $g_wsdl );

			$payService->setCharSet( $charSetType );

			$payService->setAccessCredentialType( $accessLicense, $signature, $timestamp );
			$payService->setBaseRequestType( $detailLevel, $requestApp, $requestID, $userAgent, $version );
			$payService->setApproveReq( $escrow, $orderID, $paymentAmount, $paymentMethod, $productName, $returnUrl, $siteCode );

			$approveRes = $payService->approve();

			printf( "%s,%s,%s,%s", $payService->resCD,  $approveRes->approvalKey,
								   $approveRes->payUrl, $payService->resMsg );

		}
		catch (SoapFault $ex )
		{
			printf( "%s,%s,%s,%s", "95XX", "", "", iconv("EUC-KR","UTF-8","연동 오류 (PHP SOAP 모듈 설치 필요)" ) );
		}
	}

	public function pp_ax_hub()
	{
		header('Content-Type: text/html; charset=EUC-KR'); // PG 처리를 위해 문서 타입 설정

		require ROOTPATH . 'pg/kcp/sample/pp_ax_hub_lib.php';
		$this->load->model('reDepositmodel');

		global $pg, $c_PayPlus;
		$pg = $this->cfgPg;

		$this->coupon_reciver_sms = [];
		$this->coupon_order_sms = [];

		$aParams = $this->input->post();
		$aParamsLog = $aParams;
		$aParamsKey = array('good_name', 'buyr_name', 'good_info', 'res_msg');
		foreach ($aParamsKey as $sKey) {
			if ($aParams[$sKey]) $aParamsLog[$sKey] = iconv('UTF-8', 'EUC-KR//IGNORE', $aParams[$sKey]);
		}
		$this->added_payment->write_log($aParams['ordr_idxx'], 'M', 'kcp', 'pp_ax_hub', 'process0100', $aParamsLog);

		try {
			// 필수 값 체크
			if ( ! $aParams['ordr_idxx']) {
				throw new Exception('Require ordr_idxx : [ordr_idxx]' . $aParams['ordr_idxx']);
			}
			if ( ! $aParams['req_tx']) {
				throw new Exception('Require req_tx : [req_tx]' . $aParams['req_tx']);
			}
			if ( ! $aParams['good_mny']) {
				throw new Exception('Require good_mny : [good_mny]' . $aParams['good_mny']);
			}
			if ($aParams['req_tx'] == 'pay' && ! $aParams['enc_data']) {
				throw new Exception('Require enc_data : [enc_data]' . $aParams['enc_data']);
			}
			if ($aParams['req_tx'] == 'pay' && ! $aParams['enc_info']) {
				throw new Exception('Require enc_info : [enc_info]' . $aParams['enc_info']);
			}

			// 결제 시작 마킹
			if ($aParams['ordr_idxx']) {
				$reDepositSeq = $this->reDepositmodel->insert(
					array(
						'order_seq' =>  $aParams['ordr_idxx'],
						'pg' => $this->config_system['pgCompany'],
						'params' => json_encode($aParams),
						'regist_date' => date('Y-m-d H:i:s')
					)
				);
			}

			## 주문서 정보
			$orders	= $this->ordermodel->get_order($aParams['ordr_idxx']);
			$result_option = $this->ordermodel->get_item_option($orders['order_seq']);
			$result_suboption = $this->ordermodel->get_item_suboption($orders['order_seq']);
			$result_shipping = $this->ordermodel->get_order_shipping($orders['order_seq']);

			// 필수 값 체크
			if ( ! $orders['order_seq']) {
				throw new Exception('Require order : [order_seq]' . $orders['order_seq']);
			}
			if ($aParams['use_pay_method'] == '001000000000') {
				if ($orders['step'] >= '15' && $orders['step'] <= '75') {
					throw new Exception('Wrong order status : [step]' . $orders['step'] . '[use_pay_method]' . $aParams['use_pay_method']);
				}
			} else {
				if ($orders['step'] >= '25' && $orders['step'] <= '75') {
					throw new Exception('Wrong order status : [step]' . $orders['step'] . '[use_pay_method]' . $aParams['use_pay_method']);
				}
			}

			$pg_param = $this->_site_conf_inc();
			$cust_ip = getenv('REMOTE_ADDR'); // 요청 IP

			$r_reservation_goods_seq = array(); // 출고량 업데이트를 위한 변수선언
			$sLogType = '';
			if ($orders['orign_order_seq']) $sLogType = '[재주문]';
			if ($orders['admin_order']) $sLogType = '[관리자주문]';
			if ($orders['person_seq']) $sLogType = '[개인결제]';

			$c_PayPlus = new C_PP_CLI;
			$c_PayPlus->mf_clear();
			if ($aParams['req_tx'] == 'pay') {
				$c_PayPlus->mf_set_ordr_data('ordr_mony',  $orders['settleprice']); // 결제금액 유효성 검증
				$c_PayPlus->mf_set_encx_data($aParams['enc_data'], $aParams['enc_info']);
			} else if ($aParams['req_tx'] == 'mod') {
				$aParams['tran_cd'] = '00200000';
				$c_PayPlus->mf_set_modx_data('tno', $aParams['tno']); // KCP 원거래 거래번호
				$c_PayPlus->mf_set_modx_data('mod_type', $aParams['mod_type']); // 원거래 변경 요청 종류
				$c_PayPlus->mf_set_modx_data('mod_ip', $cust_ip); // 변경 요청자 IP
				$c_PayPlus->mf_set_modx_data('mod_desc', $aParams['mod_desc']); // 변경 사유
			}
			if ($aParams['tran_cd'] != '' && $aParams['req_tx'] == 'pay') { // KCP 거래 상태 확인
				$this->added_payment->write_log($orders['order_seq'], 'M', 'kcp', 'pp_ax_hub', 'process0200', array(ROOTPATH . 'pg/kcp/', $pg['mallCode'], $pg['merchantKey'], '00200000', null, $pg_param['g_conf_gw_url'], '8090', 'payplus_cli_slib', null, $cust_ip, '3', 0, null));
				$aCheckResult = $this->added_payment->view($orders['sitetype'], 'kcp', $orders['payment'], $orders['order_seq'], $orders['pg_transaction_number']);
				$this->added_payment->write_log($orders['order_seq'], 'M', 'kcp', 'pp_ax_hub', 'process0300', $aCheckResult);
				if ($aCheckResult['m_res_data']['res_cd'] == '0000') { // 정상 거래로 확인되어 코드 정상코드 변경
					$c_PayPlus->m_res_cd = $aCheckResult['m_res_data']['res_cd'];
					$c_PayPlus->m_res_msg = $aCheckResult['m_res_data']['res_msg'];
				} else {
					$aKcpRequest = array($pg_param['g_conf_home_dir'], $pg_param['g_conf_site_cd'], $pg_param['g_conf_site_key'], $aParams['tran_cd'], '', $pg_param['g_conf_gw_url'], $pg_param['g_conf_gw_port'], 'payplus_cli_slib', $orders['order_seq'], $cust_ip, '3', 0, 0);
					$this->added_payment->write_log($orders['order_seq'], 'M', 'kcp', 'pp_ax_hub', 'process0400', $aKcpRequest);
					$c_PayPlus->mf_do_tx(null, $aKcpRequest[0], $aKcpRequest[1], $aKcpRequest[2], $aKcpRequest[3], $aKcpRequest[4], $aKcpRequest[5], $aKcpRequest[6], $aKcpRequest[7], $aKcpRequest[8], $aKcpRequest[9], $aKcpRequest[10], $aKcpRequest[11], $aKcpRequest[12]); // 응답 전문 처리
				}
			} else {
				$c_PayPlus->m_res_cd  = '9562';
				$c_PayPlus->m_res_msg = '연동 오류|tran_cd값이 설정되지 않았습니다.';
				throw new Exception('Payment Fail : [tran_cd]' . $aParams['tran_cd'] . '[req_tx]' . $aParams['req_tx']);
			}
			$aKcpResult['res_cd'] = $c_PayPlus->m_res_cd; // 결과 코드
			$aKcpResult['res_msg'] = $c_PayPlus->m_res_msg; // 결과 메시지
			$sResMsgUtf8 = convert_to_utf8($c_PayPlus->m_res_msg);

			if ($aParams['req_tx'] == 'pay') {
				if ($aKcpResult['res_cd'] == '0000') {
					$aKcpResult['tno'] = $c_PayPlus->mf_get_res_data('tno'); // KCP 거래 고유 번호
					$aKcpResult['amount'] = $c_PayPlus->mf_get_res_data('amount'); // KCP 실제 거래 금액
					$aKcpResult['pnt_issue'] = $c_PayPlus->mf_get_res_data('pnt_issue'); // 결제 포인트사 코드
					if ($aParams['use_pay_method'] == '100000000000') {
						$aKcpResult['card_cd'] = $c_PayPlus->mf_get_res_data('card_cd'); // 카드사 코드
						$aKcpResult['card_name'] = $c_PayPlus->mf_get_res_data('card_name'); // 카드 종류
						$aKcpResult['app_time'] = $c_PayPlus->mf_get_res_data('app_time'); // 승인 시간
						$aKcpResult['app_no'] = $c_PayPlus->mf_get_res_data('app_no'); // 승인 번호
						$aKcpResult['noinf'] = $c_PayPlus->mf_get_res_data('noinf'); // 무이자 여부 ('Y' : 무이자)
						$aKcpResult['quota'] = $c_PayPlus->mf_get_res_data('quota'); // 할부 개월 수
					}
					if ($aParams['use_pay_method'] == '010000000000') {
						$aKcpResult['app_time'] = $c_PayPlus->mf_get_res_data('app_time'); // 승인시간
						$aKcpResult['bank_name'] = $c_PayPlus->mf_get_res_data('bank_name'); // 은행명
						$aKcpResult['bank_code'] = $c_PayPlus->mf_get_res_data('bank_code'); // 은행코드
					}
					if ($aParams['use_pay_method'] == '001000000000') {
						$aKcpResult['bankname'] = $c_PayPlus->mf_get_res_data('bankname'); // 입금할 은행 이름
						$aKcpResult['depositor'] = $c_PayPlus->mf_get_res_data('depositor'); // 입금할 계좌 예금주
						$aKcpResult['account'] = $c_PayPlus->mf_get_res_data('account'); // 입금할 계좌 번호
						$aKcpResult['va_date'] = $c_PayPlus->mf_get_res_data('va_date'); // 입금예정일
						$aKcpResult['va_name'] = $c_PayPlus->mf_get_res_data('va_name'); // 입금자명
					}
					if ($aParams['use_pay_method'] == '000100000000') {
						$aKcpResult['pt_idno'] = $c_PayPlus->mf_get_res_data('pt_idno'); // 결제 및 인증 아이디
						$aKcpResult['pnt_amount'] = $c_PayPlus->mf_get_res_data('pnt_amount'); // 마일리지액 or 사용금액
						$aKcpResult['pnt_app_time'] = $c_PayPlus->mf_get_res_data('pnt_app_time'); // 승인시간
						$aKcpResult['pnt_app_no'] = $c_PayPlus->mf_get_res_data('pnt_app_no'); // 승인번호
						$aKcpResult['add_pnt'] = $c_PayPlus->mf_get_res_data('add_pnt'); // 발생 포인트
						$aKcpResult['use_pnt'] = $c_PayPlus->mf_get_res_data('use_pnt'); // 사용가능 포인트
						$aKcpResult['rsv_pnt'] = $c_PayPlus->mf_get_res_data('rsv_pnt'); // 적립 포인트
					}
					if ($aParams['use_pay_method'] == '000010000000') {
						$aKcpResult['app_time'] = $c_PayPlus->mf_get_res_data('hp_app_time'); // 승인 시간
						$aKcpResult['commid'] = $c_PayPlus->mf_get_res_data('commid'); // 통신사 코드
						$aKcpResult['mobile_no'] = $c_PayPlus->mf_get_res_data('mobile_no'); // 휴대폰 번호
					}
					if ($aParams['use_pay_method'] == '000000001000') {
						$aKcpResult['app_time'] = $c_PayPlus->mf_get_res_data('tk_app_time'); // 승인 시간
						$aKcpResult['tk_van_code'] = $c_PayPlus->mf_get_res_data('tk_van_code'); // 발급사 코드
						$aKcpResult['tk_app_no'] = $c_PayPlus->mf_get_res_data('tk_app_no'); // 승인 번호
					}
					$aKcpResult['cash_authno'] = $c_PayPlus->mf_get_res_data('cash_authno'); // 현금 영수증 승인 번호
				}
				$aKcpResult['escw_yn'] = $c_PayPlus->mf_get_res_data('escw_yn'); // 에스크로 여부

				$this->added_payment->write_log($orders['order_seq'], 'M', 'kcp', 'pp_ax_hub', 'process0500', $aKcpResult);  // 결과 로그 저장
				$this->db->reconnect();

				if( ! $orders['order_user_name']) {
					$orders['order_user_name'] = '주문자';
				}
				if ($aKcpResult['res_cd'] != '0000') {
					$sKcpResMsg = $sResMsgUtf8;
					if ($aKcpResult['res_cd'] == '8105') {
						$sKcpResMsg .= '(1000원이하 결제불가)';
					}
					$this->ordermodel->set_step($orders['order_seq'], '99');
					$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], '결제실패[' . $aKcpResult['res_cd'] . ']', 'KCP 결제 실패'. chr(10).'[' . $aKcpResult['res_cd'] . $sKcpResMsg . ']');
					throw new Exception('Payment Fail : [order_seq]' . $orders['order_seq'] . '[res_cd]' . $aKcpResult['res_cd'] . '[step]' . $orders['step']);
				} else {
					if ($result_option) {
						foreach ($result_option as $data_option) {
							// 출고량 업데이트를 위한 변수정의
							if ( ! in_array($data_option['goods_seq'], $r_reservation_goods_seq)) {
								$r_reservation_goods_seq[] = $data_option['goods_seq'];
							}
						}
					}
					if ($result_suboption) {
						foreach ($result_suboption as $data_suboption) {
							// 출고량 업데이트를 위한 변수정의
							if ( ! in_array($data_suboption['goods_seq'],$r_reservation_goods_seq)) {
								$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
							}
						}
					}
					// 회원 마일리지 차감
					if ($orders['emoney'] > 0 && $orders['member_seq'] && $orders['emoney_use'] == 'none') {
						$aEmoney = array(
							'gb'		=> 'minus',
							'type'		=> 'order',
							'emoney'	=> $orders['emoney'],
							'ordno'		=> $orders['order_seq'],
							'memo'		=> '[차감]주문 (' . $orders['order_seq'] . ')에 의한 마일리지 차감',
							'memo_lang'	=> $this->membermodel->make_json_for_getAlert('mp260', $orders['order_seq']) // [차감]주문 (%s)에 의한 마일리지 차감
						);
						$this->membermodel->emoney_insert($aEmoney, $orders['member_seq']);
						$this->ordermodel->set_emoney_use($orders['order_seq'], 'use');
					}
					// 회원 예치금 차감
					if ($orders['cash'] > 0 && $orders['member_seq'] && $orders['cash_use']=='none') {
						$aCash = array(
							'gb'		=> 'minus',
							'type'		=> 'order',
							'cash'		=> $orders['cash'],
							'ordno'		=> $orders['order_seq'],
							'memo'		=> '[차감]주문 (' . $orders['order_seq'] . ')에 의한 예치금 차감',
							'memo_lang'	=> $this->membermodel->make_json_for_getAlert('mp261', $orders['order_seq']),   // [차감]주문 (%s)에 의한 예치금 차감
						);
						$this->membermodel->cash_insert($aCash, $orders['member_seq']);
						$this->ordermodel->set_cash_use($orders['order_seq'], 'use');
					}
					//상품쿠폰사용
					if ($result_option) {
						foreach ($result_option as $item_option) {
							if ($item_option['download_seq']) {
								$this->couponmodel->set_download_use_status($item_option['download_seq'], 'used');
							}
						}
					}
					//배송비쿠폰사용 @2015-06-22 pjm
					if ($result_shipping) {
						foreach ($result_shipping as $shipping) {
							if ($shipping['shipping_coupon_down_seq']) {
								$this->couponmodel->set_download_use_status($shipping['shipping_coupon_down_seq'], 'used');
							}
						}
					}
					//배송비쿠폰사용(사용안함)
					if ($orders['download_seq']) {
						$this->couponmodel->set_download_use_status($orders['download_seq'], 'used');
					}

					//주문서쿠폰 사용 처리 by hed
					if ($orders['ordersheet_seq']) {
						$this->couponmodel->set_download_use_status($orders['ordersheet_seq'], 'used');
					}

					//프로모션코드 상품/배송비 할인 사용처리 @2017-01-23
					$this->promotionmodel->setPromotionpayment($orders);

					// 장바구니 비우기
					if ($orders['mode']) {
						$this->cartmodel->delete_mode($orders['mode']);
					}

					$aOrderStep = array(
						'pg_approval_number' => $aKcpResult['app_no'],
						'pg_transaction_number' => $aKcpResult['tno']
					);
					if ($aKcpResult['cash_authno']) {
						$aOrderStep['typereceipt'] = '2';
						$aOrderStep['cash_receipts_no'] = $aKcpResult['cash_authno'];
					}
					// 06-1-3. 가상계좌
					if ($aParams['use_pay_method'] == '001000000000') {
						$aOrderStep['virtual_account'] = convert_to_utf8($aKcpResult['bankname'] . ' ' . $aKcpResult['account'] . ' ' . $aKcpResult['depositor']);
						$aOrderStep['virtual_date'] = $aKcpResult['va_date'];
						$this->ordermodel->set_step($orders['order_seq'], '15', $aOrderStep);
						$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $sLogType . '주문접수(' . $orders['mpayment'] . ')', 'KCP 가상계좌 주문접수' . chr(10) . '[' . $aKcpResult['res_cd'] . $sResMsgUtf8 . ']' . chr(10) . implode(chr(10), $aOrderStep));
					} else {
						$this->ordermodel->set_step($orders['order_seq'], '25', $aOrderStep);
						$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $sLogType . '결제확인(' . $orders['mpayment'] . ')', 'KCP 결제 확인'. chr(10) . '[' .$aKcpResult['res_cd'] . $sResMsgUtf8 . ']' . chr(10). implode(chr(10), $aOrderStep));
						if (preg_match('/account/',$orders['payment']) && ($orders['step'] < '25' || $orders['step'] > '85')) { // 계좌이체 결제의 경우 현금영수증
							typereceipt_setting($orders['order_seq']);
						}
						ticket_payexport_ck($orders['order_seq']); //티켓상품 자동 출고처리구문 순차진행을 위해 분리함

						$commonSmsData = array();
						if (count($this->coupon_reciver_sms['order_cellphone']) > 0) { //받는 사람 티켓상품 SMS 데이터
							$order_count = 0;
							foreach ($this->coupon_reciver_sms['order_cellphone'] as $key => $value) {
								$coupon_arr_params[$order_count] = $this->coupon_reciver_sms['params'][$key];
								$coupon_order_no[$order_count] = $this->coupon_reciver_sms['order_no'][$key];
								$coupon_order_cellphones[$order_count] = $this->coupon_reciver_sms['order_cellphone'][$key];
								$order_count = $order_count + 1;
							}
							$commonSmsData['coupon_released']['phone'] = $coupon_order_cellphones;;
							$commonSmsData['coupon_released']['params'] = $coupon_arr_params;
							$commonSmsData['coupon_released']['order_no'] = $coupon_order_no;
						}
						if (count($this->coupon_order_sms['order_cellphone']) > 0) { //주문자 티켓상품 SMS 데이터
							$order_count = 0;
							foreach ($this->coupon_order_sms['order_cellphone'] as $key => $value) {
								$reciver_arr_params[$order_count] = $this->coupon_order_sms['params'][$key];
								$reciver_order_no[$order_count] = $this->coupon_order_sms['order_no'][$key];
								$reciver_order_cellphones[$order_count] = $this->coupon_order_sms['order_cellphone'][$key];
								$order_count = $order_count + 1;
							}
							$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;;
							$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
							$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;
						}
						if (count($commonSmsData) > 0) {
							commonSendSMS($commonSmsData);
						}
					}
					foreach ($r_reservation_goods_seq as $goods_seq) { // 출고예약량 업데이트
						$this->goodsmodel->modify_reservation_real($goods_seq);
					}
				}
			}
		} catch (Exception $e) {
			$this->added_payment->write_log($orders['order_seq'], 'M', 'kcp', 'pp_ax_hub', 'process0600', array('errorMsg' => $e->getMessage())); // 파일 로그 저장
		}

		// 결제 종료 마킹
		if ($reDepositSeq) {
			$this->reDepositmodel->del(array('re_deposit_seq' => $reDepositSeq));
		}

		## 로그 저장
		$this->added_payment->set_pg_log(
			array(
				'pg' => 'kcp',
				'res_cd' => $aKcpResult['res_cd'],
				'res_msg' => $sResMsgUtf8,
				'order_seq' => $orders['order_seq'],
				'tno' => $aKcpResult['tno'],
				'amount' => $aKcpResult['amount'],
				'card_cd' => $aKcpResult['card_cd'],
				'card_name' => convert_to_utf8($aKcpResult['card_name']),
				'noinf' => $aKcpResult['noinf'],
				'quota' => $aKcpResult['quota'],
				'bank_code' => $aKcpResult['bank_code'],
				'bank_name' => $aParams['use_pay_method'] == '001000000000' ? convert_to_utf8($aKcpResult['bankname']) : convert_to_utf8($aKcpResult['bank_name']),
				'app_no' => $aKcpResult['app_no'],
				'app_time' => $aKcpResult['app_time'],
				'escw_yn' => $aKcpResult['escw_yn'],
				'depositor' => convert_to_utf8($aKcpResult['depositor']),
				'account' => $aKcpResult['account'],
				'va_date' => $aKcpResult['va_date'],
				'biller' => convert_to_utf8($aKcpResult['va_name']),
				'commid' => $aKcpResult['commid'],
				'mobile_no' => $aKcpResult['mobile_no']
			)
		);

		if($_POST['param_opt_1'] == 'mobilenew'){
			echo("<script>document.location.href = '../order/complete_replace?no=" . $orders['order_seq'] . "&res_cd=" . $aKcpResult['res_cd'] . "';</script>");
		}else{
			echo("<script>opener.location.href = '../order/complete?no=" . $orders['order_seq'] . "'; self.close();</script>");
		}
	}
}