<?php
/*
* lg 유플러스 크로스 브라우징 결제 모듈
* 2017-05-24 jhs create
* 수정될때 아래에 이력을 남겨주세요 (no. 날짜 이니셜 (내용))
* 1. 2017-05-29 jhs (iframe 방식 문제점 수정)
* 2. 2017-05-30 jhs (결제 실패시 오작동 수정)
* 3. 2017-05-31 jhs (LG 결제 컨트롤 정리)
* 4. 2017-06-01 jhs (LG 결제 실패 추가)
*/

if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

class lg extends front_base {

	protected $json_pg_param; //order 컨트롤러에서 넘어온 결제 정보

	public function __construct() {
		parent::__construct();
		$this->load->helper('order');
		$this->load->helper('shipping');

		$this->load->model('cartmodel');
		$this->load->model('ordermodel');
		$this->load->model('membermodel');
		$this->load->model('couponmodel');
		$this->load->model('goodsmodel');
		$this->load->model('promotionmodel');
		$this->load->model('paymentlog');

		$this->cfgPg = config_load($this->config_system['pgCompany']);
	}

	/*
	 * 결제 요청
	 */
	public function request()
	{
		//공통적으로 세션을 호출을 안하기에 각 클래스 함수별로 호출
		//공통적으로 세션을 호출할시에는 이부분 제거
		session_start();
		/*
		 * 1. 기본결제 인증요청 정보 변경
		 *
		 * 기본정보를 변경하여 주시기 바랍니다.(파라미터 전달시 POST를 사용하세요)		 *
		 */

		 //app/order.php function pay에서 전달 해준 데이터
		$this->json_pg_param= json_decode(base64_decode($_POST["jsonParam"]),true);
		$pg_param = array();
		$pg = $this->cfgPg;

		// #28841 settle_price 위변조 체크 19.02.12 kmj
		$orders	= $this->ordermodel->get_order($this->json_pg_param['order_seq']);

		if( intval(floor($orders['settleprice'])) !== intval($this->json_pg_param['settle_price']) ){
			echo("<script>alert('결제 금액이 일치하지 않습니다. 다시 한 번 시도해 주세요.');</script>");
			exit;
		}

		if( $pg['nonInterestTerms'] == 'manual' &&  isset($pg['pcCardCompanyCode']) ){
			foreach($pg['pcCardCompanyCode'] as $key => $code){
				$arr = explode(',',$pg['pcCardCompanyTerms'][$key]);
				$terms = array();
				foreach($arr as $term){
					$terms[] = sprintf('%02d',$term);
				}
				$codes[] = $code . '-' . implode(':',$terms);
			}
			$this->json_pg_param['lg_noint_quota'] = implode(',',$codes);
		}
		$pg_param['quotaopt'] = $pg['interestTerms'];
		$pg_param = array_merge($pg_param,$this->json_pg_param);
		$payment = str_replace('escrow_','',$pg_param['payment']);
		if($payment != $pg_param['payment']){
			$pg_param['escorw'] = 1;
			$pg_param['payment'] = $payment;
		}

		$pg['platform'] = "service";
		if( $pg['mallCode'] == 'gb_gabiatest01' )	$pg['mallCode'] = "gabiatest01";	//gabia test
		if( $pg['mallCode'] == 'gabiatest01' )		$pg['platform'] = "test"; //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)

		//$LGD_INSTALLRANGE = "0:2:3:4:5:6:7:8:9:10:11:12";		// LG유플러스 할부 기본값 (일시불 ~ 12개월)  2019-08-23 sms
		$quotaopt = (int)$pg_param['quotaopt'];
		if(1 < $quotaopt){
			$installrange = "0";
			for($i=2; $i<=$quotaopt; $i++){
				$installrange = $installrange.":".$i;
			}
			$LGD_INSTALLRANGE = $installrange;
		}else{
			$LGD_INSTALLRANGE = "0:2:3:4:5:6:7:8:9:10:11:12";
		}

		$CST_PLATFORM = $pg['platform'];		//LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
		$CST_MID = $pg['mallCode'];					//상점아이디(LG유플러스으로 부터 발급받으신 상점아이디를 입력하세요)

																			//테스트 아이디는 't'를 반드시 제외하고 입력하세요.
		$LGD_MID = (("test" == $CST_PLATFORM)?"t":"").$CST_MID;	//상점아이디(자동생성)
		$LGD_OID = $pg_param['order_seq'];				//주문번호(상점정의 유니크한 주문번호를 입력하세요)
		$LGD_AMOUNT = $pg_param['settle_price'];			//결제금액("," 를 제외한 결제금액을 입력하세요)
		$LGD_BUYER = $pg_param['order_user_name'];		//구매자명
		$LGD_PRODUCTINFO = $pg_param['goods_name'];		//상품명
		$LGD_BUYEREMAIL = $pg_param['order_email'];		//구매자 이메일
		$LGD_TIMESTAMP = date(YmdHms);							//타임스탬프
		$LGD_CUSTOM_SKIN = "red";							   //상점정의 결제창 스킨 (red, blue, cyan, green, yellow)
		$LGD_OSTYPE_CHECK = "P";								//값 P: XPay 실행(PC 결제 모듈): PC용과 모바일용 모듈은 파라미터 및 프로세스가 다르므로 PC용은 PC 웹브라우저에서 실행 필요.
		$LGD_MERTKEY = $pg['merchantKey'];						//상점MertKey(mertkey는 상점관리자 -> 계약정보 -> 상점정보관리에서 확인하실수 있습니다)
		$configPath = ROOTPATH . 'pg/lgdacom/';					//LG유플러스에서 제공한 환경파일("/conf/lgdacom.conf") 위치 지정.
		$LGD_BUYERID = $this->userInfo['userid'];				//구매자 아이디
		$LGD_BUYERIP = $_SERVER['REMOTE_ADDR'];					//구매자IP
		$LGD_CUSTOM_SWITCHINGTYPE   = "IFRAME";					//신용카드 카드사 인증 페이지 연동 방식 (수정불가)
		switch($pg_param["payment"]){
			case "card" : $LGD_CUSTOM_USABLEPAY = "SC0010"; break;
			case "account" : $LGD_CUSTOM_USABLEPAY = "SC0030"; break;
			case "virtual" : $LGD_CUSTOM_USABLEPAY = "SC0040"; break;
			case "cellphone" : $LGD_CUSTOM_USABLEPAY = "SC0060"; break;
		}
		// LG 에스크로 설정 전달되도록 처리 :: 2018-01-30 lkh
		$LGD_ESCROW_USEYN = "N";
		if($pg_param['escorw']){
			$LGD_ESCROW_USEYN = "Y";
		}
		// LG 현금영수증 신청 노출되지 않도록 처리 :: 2018-01-30 lkh
		if($payment == "account" || $payment == "virtual"){
			$LGD_CASHRECEIPTYN = "N";
		}

		###
		$param['LGD_TAXFREEAMOUNT'] = $pg_param['freeprice'];

		/*
		 * LGD_RETURNURL 을 설정하여 주시기 바랍니다. 반드시 현재 페이지와 동일한 프로트콜 및  호스트이어야 합니다. 아래 부분을 반드시 수정하십시요.
		 */
		$LGD_RETURNURL				= get_connet_protocol().$_SERVER['HTTP_HOST']."/lg/auth";	// 응답수신페이지
		$LGD_VERSION   				= "PHP_Non-ActiveX_Standard";					// 버전정보 (삭제하지 마세요)
		$LGD_WINDOW_VER				= "2.5";							//결제창 버젼정보
		$LGD_WINDOW_TYPE			= "iframe";							//결제창 호출방식 (수정불가)

		/*
		 * 가상계좌(무통장) 결제 연동을 하시는 경우 아래 LGD_CASNOTEURL 을 설정하여 주시기 바랍니다.
		 */
		$LGD_CASNOTEURL	= get_connet_protocol().$_SERVER['HTTP_HOST']."/lg/status";

		// 만료일시 추가
		$LGD_CLOSEDATE = $pg_param['VanExpirationDate']; // 2022.02.15 가상계좌 만료일시추가 YYYYMMDDHHiiss

		/*
		 *************************************************
		 * 2. MD5 해쉬암호화 (수정하지 마세요) - BEGIN
		 *
		 * MD5 해쉬암호화는 거래 위변조를 막기위한 방법입니다.
		 *************************************************
		 *
		 * 해쉬 암호화 적용( LGD_MID + LGD_OID + LGD_AMOUNT + LGD_TIMESTAMP + LGD_MERTKEY )
		 * LGD_MID		  : 상점아이디
		 * LGD_OID		  : 주문번호
		 * LGD_AMOUNT	   : 금액
		 * LGD_TIMESTAMP	: 타임스탬프
		 * LGD_MERTKEY	  : 상점MertKey (mertkey는 상점관리자 -> 계약정보 -> 상점정보관리에서 확인하실수 있습니다)
		 *
		 * MD5 해쉬데이터 암호화 검증을 위해
		 * LG유플러스에서 발급한 상점키(MertKey)를 환경설정 파일(lgdacom/conf/mall.conf)에 반드시 입력하여 주시기 바랍니다.
		 */
		require_once($configPath."XPayClient.php");
		$xpay = new XPayClient($configPath, $CST_PLATFORM);
	   	$xpay->Init_TX($LGD_MID);

		$LGD_HASHDATA = md5($LGD_MID.$LGD_OID.$LGD_AMOUNT.$LGD_TIMESTAMP.$xpay->config[$LGD_MID]);
		$LGD_CUSTOM_PROCESSTYPE = "TWOTR";

		/*
		 *************************************************
		 * 2. MD5 해쉬암호화 (수정하지 마세요) - END
		 *************************************************
		 */
		$param['CST_PLATFORM']		   = $CST_PLATFORM;				// 테스트, 서비스 구분
		$param['LGD_WINDOW_TYPE']		= $LGD_WINDOW_TYPE;			// 수정불가
		$param['CST_MID']				= $CST_MID;					// 상점아이디
		$param['LGD_MID']				= $LGD_MID;					// 상점아이디
		$param['LGD_OID']				= $LGD_OID;					// 주문번호
		$param['LGD_BUYER']			  = $LGD_BUYER;					// 구매자
		$param['LGD_PRODUCTINFO']		= $LGD_PRODUCTINFO;	 		// 상품정보
		$param['LGD_AMOUNT']			 = $LGD_AMOUNT;					// 결제금액
		$param['LGD_INSTALLRANGE']	= $LGD_INSTALLRANGE;		// 할부기간
		$param['LGD_BUYEREMAIL']		 = $LGD_BUYEREMAIL;				// 구매자 이메일
		$param['LGD_CUSTOM_SKIN']		= $LGD_CUSTOM_SKIN;			// 결제창 SKIN
		$param['LGD_CUSTOM_PROCESSTYPE'] = $LGD_CUSTOM_PROCESSTYPE;		// 트랜잭션 처리방식
		$param['LGD_TIMESTAMP']		  = $LGD_TIMESTAMP;				// 타임스탬프
		$param['LGD_HASHDATA']		   = $LGD_HASHDATA;				// MD5 해쉬암호값
		$param['LGD_RETURNURL']   		 = $LGD_RETURNURL;	  		// 응답수신페이지
		$param['LGD_VERSION']		 	 = $LGD_VERSION;				// 버전정보 (삭제하지 마세요)
		$param['LGD_CUSTOM_USABLEPAY']   = $LGD_CUSTOM_USABLEPAY;	// 디폴트 결제수단
		$param['LGD_CUSTOM_SWITCHINGTYPE']  = $LGD_CUSTOM_SWITCHINGTYPE;// 신용카드 카드사 인증 페이지 연동 방식
		$param['LGD_OSTYPE_CHECK']		  = $LGD_OSTYPE_CHECK;		// 값 P: XPay 실행(PC용 결제 모듈), PC, 모바일 에서 선택적으로 결제가능
		$param['LGD_WINDOW_VER'] 			= $LGD_WINDOW_VER;
		$param['LGD_ENCODING'] 			= "UTF-8";						//결제창 호출문자 인코딩방식  (기본값: EUC-KR)

		// 가상계좌(무통장) 결제연동을 하시는 경우  할당/입금 결과를 통보받기 위해 반드시 LGD_CASNOTEURL 정보를 LG 유플러스에 전송해야 합니다 .
		$param['LGD_CASNOTEURL'] = $LGD_CASNOTEURL;						// 가상계좌 NOTEURL

		$param['LGD_ESCROW_USEYN'] = $LGD_ESCROW_USEYN;					// 에스크로 여부 :: 2018-01-30 lkh

		if($LGD_CASHRECEIPTYN){
			$param['LGD_CASHRECEIPTYN'] = $LGD_CASHRECEIPTYN;			// 현금영수증 사용여부 :: 2018-01-30 lkh
		}

		//Return URL에서 인증 결과 수신 시 셋팅될 파라미터 입니다.*/
		$param['LGD_RESPCODE']		   = "";
		$param['LGD_RESPMSG']			= "";
		$param['LGD_PAYKEY']			 = "";
		
		// 가상계좌일때만
		if ($payment === 'virtual') {
			$param['LGD_CLOSEDATE'] = $LGD_CLOSEDATE; // 가상 계좌 만료 일시추가 2022-02-15
		}
		
		$_SESSION['PAYREQ_MAP'] = $param;

		## 접속 브라우저 확인 IE/기타.
		$userAgenr = getBrowser();
		if($userAgenr['nickname'] == "MSIE"){
			$browser = "IE";
		}else{
			$browser = "etc";
		}

		// 모바일 일경우 모바일 결제창
		if( $this->_is_mobile_agent)
		{
			if($this->pg_param['mobilenew'] == 'y') $this->pg_open_script();
			echo("<form name='lg_settle_form' method='post' target='tar_opener' action='../lg_mobile/auth'>");
			echo("<input type='hidden' name='order_seq' value='".$this->pg_param['order_seq']."' />");
			echo("<input type='hidden' name='goods_name' value='".$this->pg_param['goods_name']."' />");
			echo("<input type='hidden' name='goods_seq' value='".$this->pg_param['goods_seq']."' />");
			echo("<input type='hidden' name='mobilenew' value='".$this->pg_param['mobilenew']."' />");
			echo("</form>");
			echo("<script>document.lg_settle_form.submit();</script>");
			exit;
		}

		$this->template->assign("browser",$browser);
		$this->template->assign("param",$param);
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir = BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_lg_nax.html'));
		$this->template->print_('tpl');
	}


	/*
	*PAYKEY 인증
	*/
	public function auth(){
		//공통적으로 세션을 호출을 안하기에 각 클래스 함수별로 호출
		//공통적으로 세션을 호출할시에는 이부분 제거
		session_start();

		/*
		payreq_crossplatform 에서 세션에 저장했던 파라미터 값이 유효한지 체크
		세션 유지 시간(로그인 유지시간)을 적당히 유지 하거나 세션을 사용하지 않는 경우 DB처리 하시기 바랍니다.
		*/
		if(!isset($_SESSION['PAYREQ_MAP'])){
			pageReload(getAlert('os217'),'parent');
		}

		$param = $_SESSION['PAYREQ_MAP'];//결제 요청시, Session에 저장했던 파라미터 MAP
		$HTTP_POST_VARS = $_POST;

		$LGD_RESPCODE = $HTTP_POST_VARS['LGD_RESPCODE'];
		$LGD_RESPMSG 	= $HTTP_POST_VARS['LGD_RESPMSG'];
		$LGD_PAYKEY	  = "";

		$param['LGD_RESPCODE'] = $LGD_RESPCODE;
		$param['LGD_RESPMSG']	=	$LGD_RESPMSG;

		$LGD_OID = $param['LGD_OID'];

		if($LGD_RESPCODE == "0000"){
			$LGD_PAYKEY = $HTTP_POST_VARS['LGD_PAYKEY'];
			$param['LGD_PAYKEY'] = $LGD_PAYKEY;
		}
		else{
			$log = "LGU+ 결제 인증 실패";
			// 주문취소
			$this->ordermodel->set_log($LGD_OID,'pay','주문자','결제 인증 실패',$log);
			//결제 실패하였습니다.
			pageReload(getAlert('os217'),'parent');
		}


		$this->template->assign("param",$param);
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir = BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_lg_nax_auth.html'));
		$this->template->print_('tpl');
	}

	public function receive()
	{
		// 헤더 선언
		header('Content-Type: text/html; charset=EUC-KR');

		$this->load->model('reDepositmodel');
		$this->load->library('added_payment');

		$aPost = $this->input->post();

		// 파일 로그 저장
		$this->added_payment->write_log($aPost['LGD_OID'], 'P', 'lg', 'receive', 'process0100', $aPost);

		global $pg; // XPayClient 모듈에서 사용
		$pg = $this->cfgPg;
		$order_count = 0;
		$aResult = array();
		$aUsePayMethod = array(
			'SC0010' => 'card',
			'SC0030' => 'account',
			'SC0040' => 'virtual',
			'SC0060' => 'cellphone'
		);
		$aReservationGoodsSeq = array();
		$this->coupon_order_sms = array();
		$this->coupon_reciver_sms = array();
		$configPath = ROOTPATH . 'pg/lgdacom/';
		$bDuplicateTransaction = false;

		require_once ($configPath . "XPayClient.php");

		// 주문 정보 불러오기
		$orders	= $this->ordermodel->get_order($aPost['LGD_OID']);
		$result_shipping = $this->ordermodel->get_order_shipping($orders['order_seq']);
		$result_option = $this->ordermodel->get_item_option($orders['order_seq']);
		$result_suboption = $this->ordermodel->get_item_suboption($orders['order_seq']);
		$aPost['LGD_MID'] = $aPost['CST_MID'];
		if ($aPost['CST_PLATFORM'] == 'test') {
			$aPost['LGD_MID'] = 't' . $aPost['LGD_MID'];
		}
		$aPost['DB_AMOUNT'] = $orders['settleprice'];
		if ($orders['pg_currency'] == 'KRW') {
			$aPost['DB_AMOUNT'] = floor($orders['settleprice']);
		}
		if ($orders['orign_order_seq']) {
			$add_log = "[재주문]";
		}
		if ($orders['admin_order']) {
			$add_log = "[관리자주문]";
		}
		if ($orders['person_seq']) {
			$add_log = "[개인결제]";
		}

		try {
			// 필수값 체크
			if ( ! $aPost['LGD_OID']) {
				throw new Exception('Require LGD_OID : [LGD_OID]'.$aPost['LGD_OID']);
			}
			if ( ! $aPost['LGD_MID']) {
				throw new Exception('Require LGD_MID : [LGD_MID]'. $aPost['LGD_MID']);
			}
			if ( ! $aPost['LGD_PAYKEY']) {
				throw new Exception('Require LGD_PAYKEY : [LGD_PAYKEY]'.$aPost['LGD_PAYKEY']);
			}
			if ( ! $aPost['DB_AMOUNT']) {
				throw new Exception('Require DB_AMOUNT :  [DB_AMOUNT]'.$aPost['DB_AMOUNT']);
			}
			if ( ! $orders['order_seq']) {
				throw new Exception('Require order : [order_seq]' . $orders['order_seq']);
			}

			// 주문서 결제처리 가능 여부
			if (preg_match('/virtual/', $orders['payment'])) {
				if ($orders['step'] >= '15' && $orders['step'] <= '75') {
					throw new Exception('Wrong order status : [step]' . $orders['step'] . '[payment]' . $orders['payment']);
				}
			} else {
				if ($orders['step'] >= '25' && $orders['step'] <= '75') {
					throw new Exception('Wrong order status : [step]' . $orders['step'] . '[payment]' . $orders['payment']);
				}
			}

			// 결제 시작 마킹
			if ( $aPost['LGD_OID'] )  {
				$reDepositSeq = $this->reDepositmodel->insert(
					array(
						'order_seq' => $aPost['LGD_OID'],
						'pg' => $this->config_system['pgCompany'],
						'params' => json_encode($aPost),
						'regist_date' => date('Y-m-d H:i:s')
					)
				);
			}

			// 이전 거래 조회
			if ($orders['pg_transaction_number']) {
				$xpayCheck = new XPayClient($configPath, $aPost['CST_PLATFORM']);
				$aCheck = $this->added_payment->view($orders['sitetype'], 'lg', $orders['payment'], $orders['order_seq'], $orders['pg_transaction_number']);
				// 파일 로그 저장
				$this->added_payment->write_log($orders['order_seq'], 'P', 'lg', 'receive', 'process0200', $aCheck);
				if ($aCheck) {
					// 처리 코드 선언
					$aResult['LGD_RESPCODE'] = '0000';
					$aResult['LGD_PAYTYPE'] = $aCheck['LGD_PAYTYPE'];
					if (preg_match('escrow', $orders['order_payment'])) {
						$aResult['LGD_ESCROWYN'] = 'Y';
					}
					$bDuplicateTransaction = true;
				}
				$xpayCheck->close();
				unset($xpayCheck);
			}

			$xpay = new XPayClient($configPath, $aPost['CST_PLATFORM']);

			// 결제 처리 (PG)
			if ( ! $bDuplicateTransaction) {
				$xpay->Init_TX($aPost['LGD_MID']);
				$xpay->Set('LGD_TXNAME', 'PaymentByKey');
				$xpay->Set('LGD_PAYKEY', $aPost['LGD_PAYKEY']);
				$xpay->Set("LGD_AMOUNTCHECKYN", "Y");
				$xpay->Set("LGD_AMOUNT", $aPost['DB_AMOUNT']);
				if ($xpay->TX()) {
					foreach ($xpay->Response_Names() as $sKey) {
						$aResult[$sKey] = $xpay->Response($sKey, 0);
					}
					// 파일 로그 저장
					$aResultLog['LGD_RESPMSG'] = convert_to_utf8($aResult['LGD_RESPMSG']);
					$aResultLog['LGD_RESPCODE'] = $aResult['LGD_RESPCODE'];
					$this->added_payment->write_log($orders['order_seq'], 'P', 'lg', 'receive', 'process0300', $aResultLog);
				} else {
					$log = "LGU+ 결제 실패" . chr(10) . "[TX 통신 응답코드 : " . $xpay->Response_Code() . chr(10) . " TX 통신 응답메시지 : " . convert_to_utf8($xpay->Response_Msg()) . "]";
					$this->ordermodel->set_log($orders['order_seq'], 'pay', '주문자', '결제실패', $log);
					throw new Exception('Payment Fail : [LGD_MID]' . $aPost['LGD_MID'] . '[LGD_PAYKEY]' . $aPost['LGD_PAYKEY'] . '[DB_AMOUNT]' . $aPost['DB_AMOUNT']);
				}
			}

			// DB 재연결
			$this->db->reconnect();

			// 주문서 실패 처리
			if (! $bDuplicateTransaction && $aResult['LGD_RESPCODE'] != '0000' && $aResult['LGD_RESPCODE'] != "S007") {
				if ($aResult['LGD_RESPCODE'] == "XC01") {
					$log = "[" . $aResult['LGD_RESPCODE'] . convert_to_utf8($aResult['LGD_RESPMSG']) . "] 중복결제";
				}
				$xpay->Rollback("상점 DB처리 실패로 인하여 Rollback 처리 [TID:" . $aResult['LGD_TID'] . ",MID:" . $aResult['LGD_MID'] . ",OID:" . $orders['order_seq'] . "]");
				$this->ordermodel->set_step($orders['order_seq'], '95');
				$this->ordermodel->set_log($orders['order_seq'], 'pay', '주문자', '결제취소', $log);
				throw new Exception('Payment Fail : [LGD_TID]' . $aResult['LGD_TID'] . '[LGD_MID]' . $aResult['LGD_MID'] . '[OID]' . $aPost['order_seq']);
			}

			// 주문서 성공 처리
			if ($aResult['LGD_RESPCODE'] == '0000') {
				// 주문서 거래번호, 결제방법 저장
				$aModify = '';
				if ($aResult['LGD_PAYTYPE']) {
					$aResult['order_payment'] = $aUsePayMethod[$aResult['LGD_PAYTYPE']];
				}
				if ($aResult['LGD_ESCROWYN'] == 'Y' && $aResult['order_payment'] == 'virtual') {
					$aResult['order_payment'] = 'escrow_' . $aResult['order_payment'];
				}
				if ($aResult['LGD_ESCROWYN'] == 'Y' && $aResult['order_payment'] == 'account') {
					$aResult['order_payment'] = 'escrow_' . $aResult['order_payment'];
				}
				if ($aResult['order_payment']) {
					$aModify['payment'] = $aResult['order_payment'];
				}
				if ($aResult['LGD_TID']) {
					$aModify['pg_transaction_number'] = $aResult['LGD_TID'];
				}
				if ($aModify) {
					$this->db->update('fm_order', $aModify, array(
						'order_seq' => $orders['order_seq']
					));
				}

				if ($aResult['LGD_PAYTYPE'] == 'SC0040') {
					// 주문서 주문접수 처리
					$data = '';
					if ($aResult['LGD_FINANCENAME']) {
						$aResult['virtualAccount'] = $aResult['LGD_FINANCENAME'];
					}
					if ($aResult['LGD_ACCOUNTNUM']) {
						$aResult['virtualAccount'] .= ' ' . $aResult['LGD_ACCOUNTNUM'];
					}
					if ($aResult['LGD_PAYER']) {
						$aResult['virtualAccount'] .= ' ' . $aResult['LGD_PAYER'];
					}
					if ($aResult['virtualAccount']) {
						$data['virtual_account'] = convert_to_utf8($aResult['virtualAccount']);
					}
					$this->ordermodel->set_step($orders['order_seq'], '15', $data);
					$log_title = $add_log . "주문접수" . "(" . $orders['mpayment'] . ")";
					$log = "LGU+ 가상계좌 주문접수" . chr(10) . "[" . $aResult['LGD_RESPCODE'] . convert_to_utf8($aResult['LGD_RESPMSG'])  . "]" . chr(10) . implode(chr(10), $data);
					$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $log_title, $log);
					$mail_step = '15';
				} else {
					// 주문서 결제확인 처리
					$this->ordermodel->set_step($orders['order_seq'], '25');
					$log = "LGU+ 결제 확인" . chr(10) . "[" . $aResult['LGD_RESPCODE'] . convert_to_utf8($aResult['LGD_RESPMSG']) . "]" . chr(10) . implode(chr(10), $data);
					if (preg_match('/account/', $aResult['order_payment'])) {
						$log .= chr(10) . "계좌이체 은행:" . convert_to_utf8($aResult['LGD_FINANCENAME']);
					}
					$log_title = $add_log . "결제확인(" . $orders['mpayment'] . ")";
					$this->ordermodel->set_log($orders['order_seq'], 'pay', $orders['order_user_name'], $log_title, $log);
					if (preg_match('/account/', $aResult['order_payment'])) {
						typereceipt_setting($orders['order_seq']);
					}
					ticket_payexport_ck($orders['order_seq']);
					$mail_step = '25';
				}

				// 마일리지 예치금 쿠폰 프로모션코드
				if ($orders['emoney'] > 0 && $orders['member_seq'] && $orders['emoney_use'] == 'none') {
					$params = array(
						'gb' => 'minus',
						'type' => 'order',
						'emoney' => $orders['emoney'],
						'ordno' => $orders['order_seq'],
						'memo' => "[차감]주문 (" . $orders['order_seq'] . ")에 의한 마일리지 차감",
						'memo_lang' => $this->membermodel->make_json_for_getAlert("mp260", $orders['order_seq'])
					);
					$this->membermodel->emoney_insert($params, $orders['member_seq']);
					$this->ordermodel->set_emoney_use($orders['order_seq'], 'use');
				}
				if ($orders['cash'] > 0 && $orders['member_seq'] && $orders['cash_use'] == 'none') {
					$params = array(
						'gb' => 'minus',
						'type' => 'order',
						'cash' => $orders['cash'],
						'ordno' => $orders['order_seq'],
						'memo' => "[차감]주문 (" . $orders['order_seq'] . ")에 의한 예치금 차감",
						'memo_lang' => $this->membermodel->make_json_for_getAlert("mp261", $orders['order_seq'])
					);
					$this->membermodel->cash_insert($params, $orders['member_seq']);
					$this->ordermodel->set_cash_use($orders['order_seq'], 'use');
				}
				if ($result_option) {
					foreach ($result_option as $item_option) {
						if ($item_option['download_seq']) {
							$this->couponmodel->set_download_use_status($item_option['download_seq'], 'used');
						}
					}
				}
				if ($result_shipping) {
					foreach ($result_shipping as $shipping) {
						if ($shipping['shipping_coupon_down_seq']) {
							$this->couponmodel->set_download_use_status($shipping['shipping_coupon_down_seq'], 'used');
						}
					}
				}
				if ($orders['download_seq']) {
					$this->couponmodel->set_download_use_status($orders['download_seq'], 'used');
				}
				if ($orders['ordersheet_seq']) {
					$this->couponmodel->set_download_use_status($orders['ordersheet_seq'], 'used');
				}
				$this->promotionmodel->setPromotionpayment($orders);
				if ($orders['mode']) {
					$this->cartmodel->delete_mode($orders['mode']);
				}

				// 재고 처리
				if ($result_option) {
					foreach ($result_option as $data_option) {
						if (! in_array($data_option['goods_seq'], $aReservationGoodsSeq)) {
							$aReservationGoodsSeq[] = $data_option['goods_seq'];
						}
					}
				}
				if ($result_suboption) {
					foreach ($result_suboption as $data_suboption) {
						if (! in_array($data_suboption['goods_seq'], $aReservationGoodsSeq)) {
							$aReservationGoodsSeq[] = $data_suboption['goods_seq'];
						}
					}
				}
				if (count($aReservationGoodsSeq) > 0) {
					foreach ($aReservationGoodsSeq as $goods_seq) {
						$this->goodsmodel->modify_reservation_real($goods_seq);
					}
				}

				// 메시지 처리
				if ($mail_step == '25') {
					$commonSmsData = array();
					$order_count = 0;
					if (count($this->coupon_reciver_sms['order_cellphone']) > 0) {
						foreach (array_keys($this->coupon_reciver_sms['order_cellphone']) as $key) {
							$coupon_arr_params[$order_count] = $this->coupon_reciver_sms['params'][$key];
							$coupon_order_no[$order_count] = $this->coupon_reciver_sms['order_no'][$key];
							$coupon_order_cellphones[$order_count] = $this->coupon_reciver_sms['order_cellphone'][$key];
							$order_count = $order_count + 1;
						}
						$commonSmsData['coupon_released']['phone'] = $coupon_order_cellphones;
						$commonSmsData['coupon_released']['params'] = $coupon_arr_params;
						$commonSmsData['coupon_released']['order_no'] = $coupon_order_no;
					}
					$order_count = 0;
					if (count($this->coupon_order_sms['order_cellphone']) > 0) {
						foreach (array_keys($this->coupon_order_sms['order_cellphone']) as $key) {
							$reciver_arr_params[$order_count] = $this->coupon_order_sms['params'][$key];
							$reciver_order_no[$order_count] = $this->coupon_order_sms['order_no'][$key];
							$reciver_order_cellphones[$order_count] = $this->coupon_order_sms['order_cellphone'][$key];
							$order_count = $order_count + 1;
						}
						$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;
						$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
						$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;
					}
					if (count($commonSmsData) > 0) {
						commonSendSMS($commonSmsData);
					}
				}
			}
		} catch (Exception $e) {
			$errorMsg = $e->getMessage();

			// 파일 로그 저장
			$this->added_payment->write_log($orders['order_seq'], 'P', 'lg', 'receive', 'process0400', array('errorMsg' => $errorMsg));
		}

		// 결제 종료 마킹
		if ($reDepositSeq) {
			$this->reDepositmodel->del(array('re_deposit_seq' => $reDepositSeq));
		}

		$resultLog = array(
			'pg' => 'lg',
			'order_seq' => $aResult['LGD_OID'],
			'tno' => $aResult['LGD_TID'],
			'amount' => $aResult['LGD_TRANSAMOUNT'],
			'app_time' => $aResult['LGD_PAYDATE'],
			'bank_code' => $aResult['LGD_FINANCECODE'],
			'bank_name' => convert_to_utf8($aResult['LGD_FINANCENAME']),
			'escw_yn' => $aResult['LGD_ESCROWYN'],
			'quota' => $aResult['LGD_CARDINSTALLMONTH'],
			'noinf' => $aResult['LGD_CARDNOINTYN'],
			'card_cd' => $aResult['LGD_FINANCEAUTHNUM'],
			'depositor' => convert_to_utf8($aResult['LGD_PAYER']),
			'account' => $aResult['LGD_ACCOUNTNUM'],
			'biller' => convert_to_utf8($aResult['LGD_BUYER']),
			'res_cd' => $aResult['LGD_RESPCODE'],
			'res_msg' => convert_to_utf8($aResult['LGD_RESPMSG'])
		);
		$this->added_payment->set_pg_log($resultLog);

		pageRedirect('../order/complete?no='.$orders['order_seq'], '', 'parent');
	}

	//LG 유플러스에서 조회
	public function status()
	{
		$this->load->model('ordermodel');
		$this->load->model('goodsmodel');
		$pg = $this->cfgPg;

		$this->send_for_provider = array();

		$HTTP_POST_VARS 		 = $_POST;
		$LGD_RESPCODE			= $HTTP_POST_VARS["LGD_RESPCODE"];			 // 응답코드: 0000(성공) 그외 실패
		$LGD_RESPMSG			 = $HTTP_POST_VARS["LGD_RESPMSG"];			  // 응답메세지
		$LGD_MID				 = $HTTP_POST_VARS["LGD_MID"];				  // 상점아이디
		$LGD_OID				 = $HTTP_POST_VARS["LGD_OID"];				  // 주문번호
		$LGD_AMOUNT			  = $HTTP_POST_VARS["LGD_AMOUNT"];			   // 거래금액
		$LGD_TID				 = $HTTP_POST_VARS["LGD_TID"];				  // LG유플러스에서 부여한 거래번호
		$LGD_PAYTYPE			 = $HTTP_POST_VARS["LGD_PAYTYPE"];			  // 결제수단코드
		$LGD_PAYDATE			 = $HTTP_POST_VARS["LGD_PAYDATE"];			  // 거래일시(승인일시/이체일시)
		$LGD_HASHDATA			= $HTTP_POST_VARS["LGD_HASHDATA"];			 // 해쉬값
		$LGD_FINANCECODE		 = $HTTP_POST_VARS["LGD_FINANCECODE"];		  // 결제기관코드(은행코드)
		$LGD_FINANCENAME		 = $HTTP_POST_VARS["LGD_FINANCENAME"];		  // 결제기관이름(은행이름)
		$LGD_ESCROWYN			= $HTTP_POST_VARS["LGD_ESCROWYN"];			 // 에스크로 적용여부
		$LGD_TIMESTAMP		   = $HTTP_POST_VARS["LGD_TIMESTAMP"];			// 타임스탬프
		$LGD_ACCOUNTNUM		  = $HTTP_POST_VARS["LGD_ACCOUNTNUM"];		   // 계좌번호(무통장입금)
		$LGD_CASTAMOUNT		  = $HTTP_POST_VARS["LGD_CASTAMOUNT"];		   // 입금총액(무통장입금)
		$LGD_CASCAMOUNT		  = $HTTP_POST_VARS["LGD_CASCAMOUNT"];		   // 현입금액(무통장입금)
		$LGD_CASFLAG			 = $HTTP_POST_VARS["LGD_CASFLAG"];			  // 무통장입금 플래그(무통장입금) - 'R':계좌할당, 'I':입금, 'C':입금취소
		$LGD_CASSEQNO			= $HTTP_POST_VARS["LGD_CASSEQNO"];			 // 입금순서(무통장입금)
		$LGD_CASHRECEIPTNUM	  = $HTTP_POST_VARS["LGD_CASHRECEIPTNUM"];	   // 현금영수증 승인번호
		$LGD_CASHRECEIPTSELFYN   = $HTTP_POST_VARS["LGD_CASHRECEIPTSELFYN"];	// 현금영수증자진발급제유무 Y: 자진발급제 적용, 그외 : 미적용
		$LGD_CASHRECEIPTKIND	 = $HTTP_POST_VARS["LGD_CASHRECEIPTKIND"];	  // 현금영수증 종류 0: 소득공제용 , 1: 지출증빙용
		$LGD_PAYER	 			 = $HTTP_POST_VARS["LGD_PAYER"];	  			// 입금자명
		$LGD_BUYER			   = $HTTP_POST_VARS["LGD_BUYER"];				// 구매자
		$LGD_PRODUCTINFO		 = $HTTP_POST_VARS["LGD_PRODUCTINFO"];		  // 상품명
		$LGD_BUYERID			 = $HTTP_POST_VARS["LGD_BUYERID"];			  // 구매자 ID
		$LGD_BUYERADDRESS		= $HTTP_POST_VARS["LGD_BUYERADDRESS"];		 // 구매자 주소
		$LGD_BUYERPHONE		  = $HTTP_POST_VARS["LGD_BUYERPHONE"];		   // 구매자 전화번호
		$LGD_BUYEREMAIL		  = $HTTP_POST_VARS["LGD_BUYEREMAIL"];		   // 구매자 이메일
		$LGD_BUYERSSN			= $HTTP_POST_VARS["LGD_BUYERSSN"];			 // 구매자 주민번호
		$LGD_PRODUCTCODE		 = $HTTP_POST_VARS["LGD_PRODUCTCODE"];		  // 상품코드
		$LGD_RECEIVER			= $HTTP_POST_VARS["LGD_RECEIVER"];			 // 수취인
		$LGD_RECEIVERPHONE	   = $HTTP_POST_VARS["LGD_RECEIVERPHONE"];		// 수취인 전화번호
		$LGD_DELIVERYINFO		= $HTTP_POST_VARS["LGD_DELIVERYINFO"];		 // 배송지

		$LGD_MERTKEY = $pg['merchantKey']; //LG유플러스에서 발급한 상점키로 변경해 주시기 바랍니다.
		$orders = $this->ordermodel->get_order($LGD_OID);
		## 가격 검증
		if	($orders['pg_currency'] == 'KRW')
			$orders['settleprice']	= floor($orders['settleprice']);

		$LGD_HASHDATA2 = md5($LGD_MID.$LGD_OID.$orders['settleprice'].$LGD_RESPCODE.$LGD_TIMESTAMP.$LGD_MERTKEY);

		/*
		 * 상점 처리결과 리턴메세지
		 *
		 * OK  : 상점 처리결과 성공
		 * 그외 : 상점 처리결과 실패
		 *
		 * ※ 주의사항 : 성공시 'OK' 문자이외의 다른문자열이 포함되면 실패처리 되오니 주의하시기 바랍니다.
		 */
		$resultMSG =  mb_convert_encoding("해쉬값 불일치",'EUC-KR','UTF-8');


		if ( $LGD_HASHDATA2 == $LGD_HASHDATA ) { //해쉬값 검증이 성공이면
			if ( "0000" == $LGD_RESPCODE ){ //결제가 성공이면
				if( "R" == $LGD_CASFLAG ) {
					$resultMSG = "OK";
				}else if( "I" == $LGD_CASFLAG ) {
					if($orders['step'] < '25' || $orders['step'] > '85'){ // 결제이후 프로세스 진행 이후에는 결제확인 처리 하지 않음
						// sms 발송을 위한 변수 저장
						$this->coupon_reciver_sms = array();
						$this->coupon_order_sms = array();
						$this->send_for_provider = array();
	 					$this->ordermodel->set_step($LGD_OID,25,$data);
					}
					$log = "LGU+ 결제 확인". chr(10)."[" .$LGD_RESPCODE . $LGD_RESPMSG . "]" . chr(10). implode(chr(10),$data);

					$add_log = "";
					if($orders['orign_order_seq']) $add_log = "[재주문]";
					if($orders['admin_order']) $add_log = "[관리자주문]";
					if($orders['person_seq']) $add_log = "[개인결제]";
					$log_title =  $add_log."결제확인"."(".$orders['mpayment'].")";
					$this->ordermodel->set_log($LGD_OID,'pay',$orders['order_user_name'],$log_title,$log);

					// 가상계좌 결제의 경우 현금영수증
					if( $orders['step'] < '25' || $orders['step'] > '85' ){
						$result = typereceipt_setting($orders['order_seq']);
					}

					$result_option = $this->ordermodel->get_item_option($LGD_OID);
					$result_suboption = $this->ordermodel->get_item_suboption($LGD_OID);

					// 출고량 업데이트를 위한 변수선언
					$r_reservation_goods_seq = array();
					$providerList = array();

					// 해당 주문 상품의 출고예약량 업데이트
					if($result_option){
						foreach($result_option as $data_option){
							if($data_option['provider_seq']) $providerList[$data_option['provider_seq']]	= 1;

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

					// 출고예약량 업데이트
					foreach($r_reservation_goods_seq as $goods_seq){
						$this->goodsmodel->modify_reservation_real($goods_seq);
					}

					// 결제확인 mail/sms발송
					send_mail_step25($orders['order_seq']);
					if( $orders['sms_25_YN'] != 'Y'){
						$params['shopName']		= $this->config_basic['shopName'];
						$params['ordno']		= $orders['order_seq'];
						$params['user_name']	= $orders['order_user_name'];

						if($orders['order_cellphone']){
							$commonSmsData['settle']['phone'][]		= $orders['order_cellphone'];
							$commonSmsData['settle']['params'][]	= $params;
							$commonSmsData['settle']['order_seq'][] = $orders['order_seq'];
						}

						sendSMS_for_provider('settle', $providerList, $params);
						//입점관리자 SMS 데이터
						if(count($this->send_for_provider['order_cellphone']) > 0){
							$provider_count = 0;
							foreach($this->send_for_provider['order_cellphone'] as $key=>$value){
								$provider_msg[$provider_count]				= $this->send_for_provider['msg'][$key];
								$provider_order_cellphones[$provider_count] = $this->send_for_provider['order_cellphone'][$key];
								$provider_count								= $provider_count+1;
							}

							$commonSmsData['provider']['phone']				= $provider_order_cellphones;
							$commonSmsData['provider']['msg']				= $provider_msg;
						}
						$this->db->where('order_seq', $orders['order_seq']);
						$this->db->update('fm_order', array('sms_25_YN'=>'Y'));
					}

					if($orders['step'] < '25' || $orders['step'] > '85'){
						//티켓상품 자동 출고처리구문 순차진행을 위해 분리함 @2017-08-16
						ticket_payexport_ck($orders['order_seq']);

						//받는 사람 티켓상품 SMS 데이터
						if(count($this->coupon_reciver_sms['order_cellphone']) > 0){
							$order_count = 0;
							foreach($this->coupon_reciver_sms['order_cellphone'] as $key=>$value){
								$coupon_arr_params[$order_count]		= $this->coupon_reciver_sms['params'][$key];
								$coupon_order_no[$order_count]			= $this->coupon_reciver_sms['order_no'][$key];
								$coupon_order_cellphones[$order_count] = $this->coupon_reciver_sms['order_cellphone'][$key];
								$order_count					=$order_count+1;
							}

							$commonSmsData['coupon_released']['phone'] = $coupon_order_cellphones;;
							$commonSmsData['coupon_released']['params'] = $coupon_arr_params;
							$commonSmsData['coupon_released']['order_no'] = $coupon_order_no;
						}

						//주문자 티켓상품 SMS 데이터
						if(count($this->coupon_order_sms['order_cellphone']) > 0){
							$order_count = 0;
							foreach($this->coupon_order_sms['order_cellphone'] as $key=>$value){
								$reciver_arr_params[$order_count]		= $this->coupon_order_sms['params'][$key];
								$reciver_order_no[$order_count]			= $this->coupon_order_sms['order_no'][$key];
								$reciver_order_cellphones[$order_count] = $this->coupon_order_sms['order_cellphone'][$key];
								$order_count					=$order_count+1;
							}

							$commonSmsData['coupon_released2']['phone'] = $reciver_order_cellphones;;
							$commonSmsData['coupon_released2']['params'] = $reciver_arr_params;
							$commonSmsData['coupon_released2']['order_no'] = $reciver_order_no;
						}
					}
					if(count($commonSmsData) > 0){
						commonSendSMS($commonSmsData);
					}

					// [판매지수 EP] 쿠키로 ep 등록 처리된 주문건인지 확인 후 EP 수집 :: 2018-09-18 pjw
					if(!$this->statsmodel) $this->load->model('statsmodel');
					$this->statsmodel->set_order_sale_ep($order_seq);

					$resultMSG = "OK";
				}else if( "C" == $LGD_CASFLAG ) {
	 			   	// 주문취소
					$this->ordermodel->set_step($LGD_OID,95);
					$log = "[" .$LGD_RESPCODE . $LGD_RESPMSG . "] 결제취소";
					$this->ordermodel->set_log($LGD_OID,'pay','주문자','결제취소',$log);

					//if( 무통장 입금취소 성공 상점처리결과 성공 )
					$resultMSG = "OK";
				}
			} else { //결제가 실패이면
				/*
				 * 거래실패 결과 상점 처리(DB) 부분
				 * 상점결과 처리가 정상이면 "OK"
				 */
				//if( 결제실패 상점처리결과 성공 )
				$resultMSG = "OK";
			}
		} else { //해쉬값이 검증이 실패이면
			/*
			 * hashdata검증 실패 로그를 처리하시기 바랍니다.
			 */
			$resultMSG =  mb_convert_encoding("결제결과 상점 DB처리(LGD_CASNOTEURL) 해쉬값 검증이 실패하였습니다.",'EUC-KR','UTF-8');
		}

		## 로그저장
		$pg_log['pg']			= $this->config_system['pgCompany'];
		$pg_log['res_cd'] 		= $LGD_RESPCODE;
		$pg_log['res_msg'] 		= convert_to_utf8($LGD_RESPMSG);
		$pg_log['order_seq'] 	= $LGD_OID;
		$pg_log['amount'] 		= $LGD_CASCAMOUNT;
		$pg_log['tno'] 			= $LGD_TID;
		$pg_log['app_time'] 	= $LGD_PAYDATE;
		$pg_log['bank_code'] 	= $LGD_FINANCECODE;
		$pg_log['bank_name'] 	= convert_to_utf8($LGD_FINANCENAME);
		$pg_log['escw_yn'] 		= $LGD_ESCROWYN;
		$pg_log['account'] 		= $LGD_ACCOUNTNUM;
		$pg_log['depositor'] 	= convert_to_utf8($LGD_PAYER);
		$pg_log['biller'] 		= convert_to_utf8($LGD_BUYER);
		$pg_log['regist_date'] = date('Y-m-d H:i:s');
		$this->db->insert('fm_order_pg_log', $pg_log);

		echo $resultMSG;

	}
}