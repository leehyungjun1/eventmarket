<?
//라이브러리
include_once ROOTPATH."/app/libraries/lib/gabia_xmlrpccommon.php";
include_once ROOTPATH."/app/libraries/lib/simple_parser.php";
include_once ROOTPATH."/app/libraries/SofeeXmlParser.php";

class gabiaSmsApi extends XmlRpcCommon
{
	var $gabiaUrl			= 'https://firstmall.kr';
	var $gabiaUrlPath		= '/payment_firstmall/get_apikey.php';
	var $api_host			= "sms.firstmall.kr";
	var $api_curl_url		= "https://sms.firstmall.kr/assets/api_upload.php";
	var $user_id			= "";
	var $user_pw			= "";
	var $m_szResultXML		= "";
	var $m_oResultDom		= null;
	var $m_szResultCode		= "";
	var $m_szResultMessage	= "";
	var $m_szResult			= "";
	var $sms_reserve		= 0;
	var $m_nBefore			= 0;
	var $m_nAfter			= 0;
	var $md5_access_token	= "";
	var $RESULT_OK			= "0000";
	var $CALL_ERROR			= -1;
	var $sms_id;
	var $api_key;
	var $sms_pw;
	/**
	 * 카카오 알림톡 실패시 SMS로 대체발송인지 여부
	 * @var boolean
	 */
	var $by_kakao;

	//function __construct($id, $api_key, $pw="")
	function gabiaSmsApi($id, $api_key, $pw="")
	{
		$CI =& get_instance();
		$sms_info = config_load('master');
		//기존 방식인 경우 신규 방식으로 변환위해 api_key 셋팅 및 업데이트
		if($sms_info["sms_auth"] != "" && strlen($sms_info["sms_auth"]) != 32){
			$data = makeEncriptParam("mallid=" . $CI->config_system['service']['cid'] . "&sms_id=" . $CI->config_system['service']['sms_id']);
			$res = file_get_contents($this->gabiaUrl.$this->gabiaUrlPath."?params=".$data);
			if ($res){
				$api_key = $res;
				$CI->db->where('codecd', 'sms_auth');
				$CI->db->update('fm_config', array('value' => $api_key));
			}
		}

		$this->sms_id = $id;
		$this->api_key = $api_key;
		$this->sms_pw = $pw;
		$this->by_kakao = false;

		$nonce = $this->gen_nonce();
		$this->md5_access_token = $nonce.md5($nonce.$this->api_key);

	}

	function __destruct()
	{
		unset($this->m_szResultXML);
		unset($this->m_oResultDom);
	}

	/*
	 * nonce 생성
	 */
	function gen_nonce()
	{
		$nonce = '';
		for($i=0; $i<8; $i++)
		{
			$nonce .= dechex(rand(0, 15));
		}

		return $nonce;
	}

	function getSmsCount()
	{
		$request_xml = sprintf("<request>
<sms-id>%s</sms-id>
<access-token>%s</access-token>
<response-format>xml</response-format>
<method>SMS.getUserInfo</method>
<params>
</params>
</request>", $this->sms_id, $this->md5_access_token );

		$nCount = 0;

		if ($this->xml_do($request_xml) == $this->RESULT_OK)
		{
			if (strpos($this->m_szResult, "<?xml") == 0)
			{
				$xmlParser = new SofeeXmlParser();
				$xmlParser->parseString($this->m_szResult);
				$tree = $xmlParser->getTree();

				$child = null;
				foreach((array)$tree['root'] as $k=>$v) $child->$k = $tree['root'][$k]['value'];

				if ( isset($child->sms_quantity) )
					$nCount = $child->sms_quantity;

			}

		}

		return $nCount;
	}

	function get_result_xml($result)
	{
		$sp = new SimpleParser();
		$sp->parse_xml($result);

		$result_xml = $sp->getValue("RESPONSE|RESULT");

		return base64_decode($result_xml);
	}

	function get_status_by_ref($refkey)
	{
		if(is_array($refkey))
		{
			$ref_keys = implode(",", $refkey);
		}else
		{
			$ref_keys = $refkey;
		}
		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->sms_id}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>SMS.getStatusByRef</method>
<params>
	<ref_key>{$ref_keys}</ref_key>
</params>
</request>
DOC_XML;

		if ($this->xml_do($request_xml) == $this->RESULT_OK)
		{
			$r = array();
			$resultXML = simplexml_load_string($this->m_szResult);

			$child = $resultXML>children();

			foreach($child->smsResult->entries->children() as $n)
			{
				$child2 = $n->children();
				$szKey = (string)$child2->SMS_REFKEY;
				$szCode = (string)$child2->CODE;
				$szMesg = (string)$child2->MESG;

				if (array_key_exists($szKey, $r))
				{
					$r[$szKey]["CODE"] = $szCode;
					$r[$szKey]["MESG"] = $szMesg;
				}
				else
					$r[$szKey] = array("CODE" => $szCode, "MESG" => $szMesg);
			}

			return $r;
		}
		else false;
	}

	function sms_msg_type($msg, $phone=''){

		$patterns[0] = "&";
		$patterns[1] = "<";
		$patterns[2] = ">";
		$replacements[0] = "&amp;";
		$replacements[1] = "&lt;";
		$replacements[2] = "&gt;";

		$msg		= str_replace($replacements, $patterns, $msg);
		$msg		= preg_replace('/\r/', '',$msg);

		$euc_kr_msg = iconv('utf-8', 'euc-kr', $msg);
		if(strlen($euc_kr_msg) > 90){
			$sendType	= "lms";
		}else{
			$sendType	= "sms";
		}

		// 국제 발송 여부 체크
		// array로 폰 번호가 전달 될 시 실제 발송 프로세스에서 호출 한 것이 아님.
		// sms_send 에서 단일건 처리로 호출 될때 체크
		if(!is_array($phone) && $sendType == "sms"){
			$tr_msgtype = $this->check_global_phone($phone);	// 9 : sms, 2 : gms(국제발송)
			if($tr_msgtype == '2'){
				$sendType = 'gms';
			}
		}


		return $sendType;
	}

	// 국제 발송 여부 확인
	// 본 체크 로직을 수정 시
	// @121.78.114.164:/home/sms/sms_service/web_new/sms/models/outhmodel.php
	// @121.78.114.164:/home/sms/sms_service/web_new/cron/restore_base.php
	// @121.78.114.164:/home/sms/sms_service/web_new/sms/views/user_history.html
	// @121.78.114.162:/home/sms2.firstmall.kr/sms/models/outhmodel.php
	// @121.78.114.162:/home/sms2.firstmall.kr/cron/restore_base.php
	// @121.78.114.162:/home/sms2.firstmall.kr/sms/views/user_history.html
	// 위 파일의 g_preg_phone 로 검색하여 동일한 체크 프로세스로 수정 필수
	function check_global_phone($g_preg_phone){
		$smsDomesticCode = code_load("smsDomesticCode");
		$result = "9";
		$g_preg_phone = str_replace("-", "", $g_preg_phone);

		// 매칭 값이 없어야 국외번호
		$cntMatch = 0;
		$g_preg_pattern = '/^01([0|1|6|7|8|9]?)/i';
		if(preg_match($g_preg_pattern,$g_preg_phone)){
			$cntMatch++;
		}
		foreach($smsDomesticCode as $codes){
			$digit = $codes['codecd'];
			$value = str_replace("\\\"", "\"", $codes['value']);
			if($value){
				$value = json_decode($value);
			}
			if($value){
				if(in_array(substr($g_preg_phone,0,$digit), $value)){
					$cntMatch++;
				}
			}
		}

		if($cntMatch==0){
			$result = "2";
		}
		return $result;
	}

	function sms_send($phone, $callback, $msg, $refkey="", $reserve = "0", $ordno="", $sendType=''){

		$title = '';
		$patterns[0] = "/&#x20a9;/";
		$patterns[1] = "/&yen;/";
		$patterns[2] = "/&euro;/";
		$patterns[3] = "/&#x24;/";
		$patterns[4] = "/US&dollar;/";
		$patterns[5] = "/&/";
		$patterns[6] = "/</";
		$patterns[7] = "/>/";

		$replacements[0] = "￦";
		$replacements[1] = "￥";
		$replacements[2] = "€";
		$replacements[3] = "$";
		$replacements[4] = "US$";
		$replacements[5] = "&amp;";
		$replacements[6] = "&lt;";
		$replacements[7] = "&gt;";

		$smsData = array();

		$title = "";

		// msg is not array > to array
		if(is_array($msg) === false) {
			$msg = [$msg];
		}
		if(is_array($phone) === false) {
			$phone = [$phone];
		}

		$checkmsg	= $msg[0];
		foreach($msg as $key=>$value){
			$msg[$key]		= preg_replace($patterns, $replacements,$msg[$key]);

			$sendType = $this->sms_msg_type($msg[$key], $phone[$key]);
			if($sendType == "lms"){
				$smsData['lms']["phone"][$key] = $phone[$key];
				$smsData['lms']["msg"][$key] = $msg[$key];
			}elseif($sendType == "gms"){
				$smsData['gms']["phone"][$key] = $phone[$key];
				$smsData['gms']["msg"][$key] = $msg[$key];
			}else{
				$smsData['sms']["phone"][$key] = $phone[$key];
				$smsData['sms']["msg"][$key] = $msg[$key];
			}

		}

		$boolen = false;
		$pos = substr(strstr($checkmsg, "%"), 1);

		while($pos){
			if(substr($pos, 0, 1) != " "){
				$boolen = true;
			}
			$pos = substr(strstr($pos, "%"), 1);
		}

		if($boolen){
			return "fail";
		}

		foreach($smsData as $smsType=>$smsArray){

			$phone = join(",", $smsArray['phone']);
			$msg = join("|^|", $smsArray['msg']);

		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->sms_id}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>SMS.send4</method>
<params>
	<send_type>{$smsType}</send_type>
	<ref_key>{$refkey}</ref_key>
	<subject>{$title}</subject>
	<message>{$msg}</message>
	<callback>{$callback}</callback>
	<phone>{$phone}</phone>
	<reserve>{$reserve}</reserve>
	<ordno>{$ordno}</ordno>
	<deliveryno>{$deliveryno}</deliveryno>
	<deliveryco>{$deliveryco}</deliveryco>
</params>
</request>
DOC_XML;
			$result = $this->xml_do($request_xml);
		}

		return $result;
	}

	function lms_send($phone, $callback, $msg, $title="", $refkey="", $reserve = "0")
	{
		$patterns[0] = "/&/";
		$patterns[1] = "/</";
		$patterns[2] = "/>/";
		$replacements[2] = "&amp;";
		$replacements[1] = "&lt;";
		$replacements[0] = "&gt;";

		$msg = preg_replace($patterns, $replacements, $msg);
		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->sms_id}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>SMS.send</method>
<params>
		<send_type>lms</send_type>
		<ref_key>{$refkey}</ref_key>
		<subject>{$title}</subject>
		<message>{$msg}</message>
		<callback>{$callback}</callback>
		<phone>{$phone}</phone>
		<reserve>{$reserve}</reserve>
</params>
</request>
DOC_XML;

		return $this->xml_do($request_xml);
	}

	/*
	 * XMLRPC 발송
	 * $xml_data : 발송정보의 XML 데이터
	 */
	function xml_do($xml_data)
	{
		$this->init($this->api_host, "api", "gabiasms");
		$this->m_szResultXML = $this->call($xml_data);

		if ($this->m_szResultXML)
		{
			$xmlParser = new SofeeXmlParser();
			$xmlParser->parseString($this->m_szResultXML);
			$tree = $xmlParser->getTree();
			$child = null;
			foreach((array)$tree['response'] as $k=>$v) $child->$k = $tree['response'][$k]['value'];

			if (isset($child->code))
			{
				$this->m_szResultCode = $child->code;
				$this->m_szResultMessage = $child->mesg;
			}

			if (isset($child->result))
				$this->m_szResult = base64_decode($child->result);

			$this->m_szResult = strtolower($this->m_szResult);
			$r = strpos($this->m_szResult, "<?xml");
			if ($r == 0 && $r !== FALSE)
			{
				$xmlParser->parseString($this->m_szResultXML);
				$tree = $xmlParser->getTree();
				$child = null;
				foreach((array)$tree['response'] as $k=>$v) $child->$k = $tree['response'][$k]['value'];

				if (isset($child->BEFORE_SMS_QTY))
					$this->m_nBefore = $child->BEFORE_SMS_QTY;

				if (isset($child->AFTER_SMS_QTY))
					$this->m_nAfter = $child->AFTER_SMS_QTY;

				unset($oCountXML);
			}
			unset($this->m_oResultDom);
		}
		else
		{
			$this->m_szResultCode = $this->m_szResultXML;
			$this->m_szResult = $this->getRpcError();
		}

		return $this->m_szResultCode;
	}

	function getResultCode()
	{
		return $this->m_szResultCode;
	}

	function getResultMessage()
	{
		return $this->m_szResultMessage;
	}

	function getBefore()
	{
		return $this->m_nBefore;
	}

	function getAfter()
	{
		return $this->m_nAfter;
	}

	function minusSmsCount($smstype, $msg="고객리마인드 서비스 - 추가차감")
	{
		$request_xml = sprintf("<request>
<sms-id>%s</sms-id>
<access-token>%s</access-token>
<response-format>xml</response-format>
<method>SMS.minusSmscount</method>
<params>
	<smstype>%s</smstype>
	<message>%s</message>
</params>
</request>", $this->sms_id, $this->md5_access_token, $smstype, $msg );
		return $this->xml_do($request_xml);
	}


	//SMS 메시지 치환 등
	function sendSMS($commonSmsData){

		$CI =& get_instance();
		$CI->config_basic = ($CI->config_basic)?$CI->config_basic:config_load('basic');
		$CI->config_basic['domain'] = $CI->config_system['domain'];
		$shopDomain		= ($this->config_basic['domain']) ? $this->config_basic['domain'] : $_SERVER['HTTP_HOST'];
		$from_sms	= $CI->config_sms_info['send_num'] ? $CI->config_sms_info['send_num'] : $CI->config_basic['companyPhone'];
		$from_sms	= preg_replace("/[^0-9]/", "", $from_sms);

		$keys = array_keys($commonSmsData);


		foreach($keys as $case){

			$msg = array();
			$phone = array();

			## 개인맞춤형알림(예약 발송) 추가로 인한 SMS 구분 2014-07-21
			$case_tmp = explode("_",$case);
			if($case_tmp[0] == "personal"){
				$sms_mode = "sms_personal";
			}else{
				$sms_mode = "sms";
			}

			$CI->config_sms_info = ($CI->config_sms_info)?$CI->config_sms_info:config_load('sms_info');
			$CI->config_sms		= ($CI->config_sms['groupcd'] == $sms_mode )?$CI->config_sms:config_load($sms_mode);

			$to_sms		= $commonSmsData[$case]['phone'];
			$params		= $commonSmsData[$case]['params'];
			$order_no	= $commonSmsData[$case]['order_no'];
			$mid		= $commonSmsData[$case]['mid'];

			// 알림톡 발송 여부 체크 :: 2018-03-19 lwh
			$talkYN		= $commonSmsData[$case]['talkYN'];

			$label		= $commonSmsData[$case]['label'];	// 주문데이터 label

			switch ($case) {
				case 'member'://회원 관련 SMS 발송(관리자 없음)
					if ($talkYN == 'Y') {
						break;
					}
					if (is_array($to_sms)) {
						$to_sms_count = count($to_sms);
						for ($i = 0; $i < $to_sms_count; $i++) {
							$makeMsg = str_replace('{userName}', $params[$i]['userName'], $_POST['send_message']);

							//메시지가 없거나 중복된 메시지일 경우 제거 하여 빈값 발송 및 중복 발송 방지
							if (trim($makeMsg)) {
								$msg[] = $makeMsg;
								$phone[] = trim($to_sms[$i]);
							}
						}
						if ($msg) {
							$result = $this->sendSMS_Msg($msg, $phone);
						}
					} else {
						if ($params['msg']) {
							$result = $this->sendSMS_Msg($params['msg'], $to_sms);
						}
					}

					break;
				case 'restock'://재입고 알림 SMS 발송(관리자 없음)
					if ($talkYN == 'Y') {
						break;
					}
					foreach ($params['msg'] as $key => $message) {
						$msg[$key] = $params['msg'][$key];
						$phone[$key] = $to_sms[$key];
					}
					$result = $this->sendSMS_Msg($msg, $phone);

					break;
				case 'goods_review'://상품후기 마일리지지급시(관리자없음)
					if ($talkYN == 'Y') {
						break;
					}
					if ($params['msg']) {
						$result = $this->sendSMS_Msg($params['msg'], $to_sms);
					}

					break;
				case 'board_reply'://게시판답변용(관리자없음)
					if ($talkYN == 'Y') {
						break;
					}
					$makeMsg = sendCheck('cs', 'sms', 'user', $params, false, $CI->config_sms);
					if ($makeMsg) {
						//# 발송시간제한
						$this->sms_reserve = $this->sendSMS_restriction($case);
						$result = $this->sendSMS_Msg($makeMsg, $to_sms);
					}

					break;
				case 'provider'://입점사 출고 알림
					if ($talkYN == 'Y') {
						break;
					}
					$msg = $commonSmsData[$case]['msg'];
					$result = $this->sendSMS_Msg($msg, $to_sms);

					break;
				case 'provider_person'://입점사 미출고 주문 SMS 발송
					if ($talkYN == 'Y') break;
					$makeMsg = $params['msg'];
					
					//메시지가 없거나 중복된 메시지일 경우 제거 하여 빈값 발송 및 중복 발송 방지
					if(trim($makeMsg)){
						$to_sms_count = count($to_sms);
						for ($i = 0; $i < $to_sms_count; $i++) {
							$msg	 = $makeMsg;
							$phone = trim($to_sms[$i]);
							if($msg){
								$result = $this->sendSMS_Msg($msg, $phone);
							}
						}
					}
					break;
				case 'dormancy_m'://휴면회원 수동 SMS 발송(관리자 없음)
					if ($talkYN == 'Y') {
						break;
					}
					if (is_array($to_sms)) {
						$to_sms_count = count($to_sms);
						for ($i = 0; $i < $to_sms_count; $i++) {
							$last_login_date = substr($params[$i]['lastlogin_date'], 0, 10);

							$dormancy_du_date = substr($last_login_date, 0, 4) + 1;
							$dormancy_du_date .= substr($last_login_date, 4, 10);

							$makeMsg = str_replace('{userName}', $params[$i]['userName'], $_POST['send_message']);
							$makeMsg = str_replace('{shopName}', $CI->config_basic['shopName'], $makeMsg);
							$makeMsg = str_replace('{shopDomain}', $shopDomain, $makeMsg);
							$makeMsg = str_replace('{userid}', $params[$i]['userid'], $makeMsg);
							$makeMsg = str_replace('{dormancy_du_date}', $dormancy_du_date, $makeMsg);

							//메시지가 없거나 중복된 메시지일 경우 제거 하여 빈값 발송 및 중복 발송 방지
							if (trim($makeMsg)) {
								$msg[] = $makeMsg;
								$phone[] = trim($to_sms[$i]);
							}
						}
						if ($msg) {
							$result = $this->sendSMS_Msg($msg, $phone);
						}
					} else {
						if ($params['msg']) {
							$result = $this->sendSMS_Msg($params['msg'], $to_sms);
						}
					}

					break;
				default://기본
					//## 알림톡 발송처리된 내용은 발송 금지.
					if ($talkYN != 'Y') {
						//## USER
						$senduse = [];
						$remind_param = [];
						if (is_array($to_sms)) {
							$to_sms_count = count($to_sms);
							$before_ordno = '';
							$before_delivery_number = '';

							for ($i = 0; $i < $to_sms_count; $i++) {
								$makeMsg = sendCheck($case, $sms_mode, 'user', $params[$i], $order_no[$i], $CI->config_sms);
								if ($sms_mode == 'sms_personal') {
									$makeMsg = $makeMsg[0];
								}

								$sms_flag = true;
								$sms_flag = ($params[$i]['ordno'] != $before_ordno);
								if (in_array($case, ['coupon_released', 'coupon_released2'])) {
									$sms_flag = true;
								}

								//메시지가 없거나 중복된 메시지일 경우 제거 하여 빈값 발송 및 중복 발송 방지
								if (trim($makeMsg) && (($sms_flag || $params[$i]['delivery_number'] != $before_delivery_number) || $params[$i]['ordno'] == '')) {
									//# 고객리마인드
									if ($sms_mode == 'sms_personal') {
										$msg[] = $makeMsg;
										$remind_param_tmp = [];
										$remind_param_tmp['data'] = $params[$i];
										$remind_param_tmp['phone'] = $to_sms[$i];
										$remind_param[] = $remind_param_tmp;
									} else {
										//# 일반 sms
										$msg[] = $makeMsg;
									}
									$senduse[] = true;
									$phone[] = trim($to_sms[$i]);
								} else {
									$senduse[] = false;
								}

								$before_ordno = $params[$i]['ordno'];
								$before_delivery_number = $params[$i]['delivery_number'];
							}
						}

						//# 발송시간제한(예약문자)
						$rest_use = 'y';
						if (!$CI->sms_reserve && ($case == 'order' || $case == 'settle')) {
							$CI->config_sms_rest = config_load('sms_restriction');	//SMS 발송시간제한
							$sms_use_chk = $CI->config_sms_rest[$case];
							if ($sms_use_chk != 'checked') {
								$rest_use = 'n';
							}//미사용시
						}
						if ($sms_mode == 'sms_personal') {
							$rest_use = 'n';
						}

						//회원이(관리자/입점관리자/시스템이 아님) 주문접수/결제확인/배송완료/환불완료 상태일때에는 바로발송 @2016-07-27
						$userendstep = ['order', 'settle', 'cancel', 'delivery', 'delivery2', 'coupon_cancel', 'coupon_delivery', 'coupon_delivery2'];
						if (
							$rest_use == 'y' &&
							isAdminSystemMode() === false
							&& (in_array($case, $userendstep))
						) {
							$rest_use = 'n';
						}

						// 선물하기 관련 메시지인경우
						if(in_array($case,['present_receive','present_cancel_order','present_cancel_receive'])) {
							$CI->config_sms_rest = config_load('sms_restriction');
							$rest_use = 'y';
							$sms_use_chk = $CI->config_sms_rest[$case];
							if ($sms_use_chk != 'checked') {
								$rest_use = 'n';
							}
						}

						//# 고객리마인드 예약시간은 /app/helpers/reservation_helper.php 에서 설정됨.
						if ($rest_use == 'y') {
							$CI->sms_reserve = $this->sendSMS_restriction($case);
						}

						//미입금 통보 예약시간 설정
						if ($case == 'deposit') {
							$CI->sms_reserve = date('Y-m-d') . ' ' . $CI->config_sms['deposit_send_time'] . ':00';
						}

						//휴면계정
						if ($case == 'dormancy') {
							$CI->sms_reserve = date('Y-m-d') . ' ' . $CI->config_sms['dormancy_send_time'] . ':00';
						}

						if ($msg) {
							$result = $this->sendSMS_Msg($msg, $phone, $order_no, $sms_mode);
							//## 고객리마인드서비스용 발송 로그저장 ## 발송 LOG
							if ($sms_mode == 'sms_personal') {
								//# 발송여부,발송결과,치환코드,발송내용
								$this->remind_log($senduse, $result, $remind_param, $msg);
							}
						}
					}

					//## ADMIN
					if (($CI->config_sms_info['admis_cnt'] > 0 || isset($params[0]['provider_mobile'])) && $this->by_kakao !== true) { // 카카오알림톡 대체 SMS발송은 관리자 발송 안함.
						$dataTo = [];
						unset($msg);

						if (is_array($to_sms)) {
							$msg = [];

							$makeMsg = sendCheck($case, 'sms', 'admin', $params[0], $order_no[0], $CI->config_sms);
							if ($to_sms_count > 1) {
								$makeMsg = $makeMsg . ' 외 ' . ($to_sms_count - 1) . '건';
							}

							for ($j = 0; $j < $CI->config_sms_info['admis_cnt']; $j++) {
								if (adminSendChK($case, $j) == 'Y') {
									$id = 'admins_num_' . $j;

									$msg[] = $makeMsg;
									$dataTo[] = preg_replace('/[^0-9]/', '', $CI->config_sms_info[$id]);
								}
							}

							//입점사 SMS
							if ($CI->config_sms[$case . '_provider_yn'] == 'Y' && isset($params[0]['provider_mobile'])) {
								if (is_array($params[0]['provider_mobile']) === true) {
									foreach ($params[0]['provider_mobile'] as $key => $val) {
										$dataTo[] = preg_replace('/[^0-9]/', '', $val);
									}
								} else {
									$dataTo[] = preg_replace('/[^0-9]/', '', $params[0]['provider_mobile']);
								}
								$msg[] = $makeMsg;
							}
							// front 에서 결제 확인 시 선물하기 결제이면 관리자 sms 미발송
							if(isAdminSystemMode() === false && $case === "settle" && $label[0] === "present") {
								$dataTo = [];
							}
							if(count($dataTo) > 0) {
								$adminResult = $this->sendSMS_Msg($msg, $dataTo, $order_no);
							}
						} else {
							$msg = [];

							$makeMsg = sendCheck($case, 'sms', 'admin', $params[0], '', $CI->config_sms);

							for ($j = 0; $j < $CI->config_sms_info['admis_cnt']; $j++) {
								if (adminSendChK($case, $j) == 'Y') {
									$id = 'admins_num_' . $j;
									$msg[] = $makeMsg;
									$dataTo[] = preg_replace('/[^0-9]/', '', $CI->config_sms_info[$id]);
								}
							}

							//입점사 SMS
							if ($CI->config_sms[$case . '_provider_yn'] == 'Y' && isset($params[0]['provider_mobile'])) {
								$dataTo[] = preg_replace('/[^0-9]/', '', $params[0]['provider_mobile']);
								if ($to_sms_count > 1) {
									$makeMsg = $makeMsg . ' 외 ' . ($to_sms_count - 1) . '건';
								}
								$msg[] = $makeMsg;
							}

							$adminResult = $this->sendSMS_Msg($msg, $dataTo);
						}
					}

					break;
			}

		}

		return $result;
	}


	#발송시간제한 : 발송예약시간 설정
	function sendSMS_restriction($case){

		$CI		=& get_instance();
		$CI->config_sms_rest	= config_load('sms_restriction');	//SMS 발송시간제한

		if(strstr($case,"_write") || strstr($case,"_reply")){
		## 게시판 발송시간 제한(예약)
			if(strstr($case,"_write")){
				$sms_use_chk	= $CI->config_sms_rest['board_toadmin'];
			}else{
				$sms_use_chk	= $CI->config_sms_rest['board_touser'];
			}
			$config_time_s	= $CI->config_sms_rest['board_time_s'];
			$config_time_e	= $CI->config_sms_rest['board_time_e'];
			$reserve_time	= $CI->config_sms_rest['board_reserve_time'];

		}else{
		## 일반 발송시간 제한(예약)
			$config_time_s	= $CI->config_sms_rest['config_time_s'];
			$config_time_e	= $CI->config_sms_rest['config_time_e'];
			$reserve_time	= $CI->config_sms_rest['reserve_time'];
			$sms_use_chk	= $CI->config_sms_rest[$case];

		}
		if($sms_use_chk == "checked"){

			//24-> 00시 부터 체크합니다.(00~23)  @2016-07-27 ysm
			if( $config_time_s == '24' ) $config_time_s = '00';
			if( $config_time_e == '24' ) $config_time_e = '00';

			//발송제한 시작 시간이 더 크면, 발송제한 종료시간은 익일로 계산.
			$rest_stime	= date("Y-m-d ".$config_time_s.":00:00",mktime());

			$todayday_rest_etime = false;
			if($config_time_s > $config_time_e){
				$rest_etime	= date("Y-m-d ".$config_time_e.":59:59",mktime()+(60*60*24));

				//오늘일자 종료시간을 기준으로 현재시간이 포함되어 있으면 예약발송
				$yesterd_rest_etime	= date("Y-m-d ".$config_time_e.":00:00",mktime());//오늘일자 종료시간
				if( $yesterd_rest_etime >= date("Y-m-d H:i:s",mktime()) ) {
					$todayday_rest_etime = true;
				}
			}else{
				$rest_etime	= date("Y-m-d ".$config_time_e.":59:59",mktime());

				//오늘일자 종료시간을 기준으로 현재시간이 포함되어 있으면 예약발송
				if( $rest_etime >= date("Y-m-d H:i:s",mktime()) ) {
					$todayday_rest_etime = true;
				}
			}
			//SMS발송시각이 발송제한 시간에 해당하면 지정된 예약시간에 발송
			if( $todayday_rest_etime || ($rest_stime <= date("Y-m-d H:i:s",mktime()) && $rest_etime >= date("Y-m-d H:i:s",mktime())) ) {
				if( $todayday_rest_etime ) {
					$rest_etime_tmp = date("Y-m-d 08:00:00",strtotime($rest_stime));	//당일 08시
				}else{
					$rest_etime_tmp = date("Y-m-d 08:00:00",strtotime($rest_stime)+(60*60*24));	//익일 08시
				}
				$rest_etime_tmp = strtotime($rest_etime_tmp) + (60*$reserve_time); //익일 08시+예약time
				if( date("Y-m-d H:i:s",$rest_etime_tmp) < date("Y-m-d H:i:s",mktime())  ) {//최종 예약일 당일/익일 체크
					$rest_etime_tmp = ($rest_etime_tmp + (60*60*24));	//익일 08시
				}
				$this->sms_reserve = date("Y-m-d H:i:s",$rest_etime_tmp);
			}
		}
		return $this->sms_reserve;
	}


	// 자동 SMS 발송용
	function sendSMS_Msg($msg, $dataTo, $order_no="",$sms_mode=''){
		$CI		=& get_instance();
		$CI->config_basic = ($CI->config_basic)?$CI->config_basic:config_load('basic');
		$CI->config_sms_info = ($CI->config_sms_info)?$CI->config_sms_info:config_load('sms_info');

		// 회원리스트 > sms : 발송 보내는 사람
		if (!empty($_POST["send_sms"]) && $_POST["send_sms"] != "Y") {
			$from_sms = preg_replace("/[^0-9]/", "", $_POST["send_sms"]);
		} else {
			$from_sms	= $CI->config_sms_info['send_num'] ? $CI->config_sms_info['send_num'] : $CI->config_basic['companyPhone'];
		}

		if($sms_mode == "sms_personal"){
			$sms_msg_type = $this->sms_msg_type($msg[0], $dataTo);
			if($sms_msg_type == "sms"){
				 $addres	= $this->sms_test('cm2',"고객리마인드 추가차감-".$msg[0]);
				 $sms_type	= "sms";
			}elseif($sms_msg_type == "gms"){
				 $addres	= $this->sms_test('cm4',"고객리마인드 추가차감-".$msg[0]);
				 $sms_type	= "gms";
			}
		}
		$CI->benchmark->mark('code_start');
		$reserve	= !empty($CI->sms_reserve) ? $CI->sms_reserve : 0;
		$result		= $this->sms_send($dataTo, $from_sms, $msg, "", $reserve, $order_no,$sms_type);
		$result_code = $this->getResultCode();

		$CI->benchmark->mark('code_end');
		$sms_send_times = $CI->benchmark->elapsed_time('code_start', 'code_end');

		return $result;
	}

	// SMS 발송건수 차감
	function sms_test($count=1,$msg){

		$CI		=& get_instance();
		$limit		= $this->minusSmsCount($count,$msg);

		return $limit;
	}


	#고객리마인드서비스용 발송 로그저장 ## 발송 LOG
	function remind_log($arr_senduse,$result,$params, $msg){

		$CI =& get_instance();

		foreach($arr_senduse as $k=>$senduse){

			if($senduse){
				## 발송통계
				$sql = "select seq from fm_log_curation_summary where inflow_kind='".$params[$k]['data']['kind']."' and send_date ='".date("Y-m-d",mktime())."'";
				$query	= $CI->db->query($sql);
				$res	= $query->row_array();
				if(!$res['seq']){
					$CI->db->query("insert into fm_log_curation_summary set inflow_kind='".$params[$k]['data']['kind']."',send_sms_total=0,send_date ='".date("Y-m-d",mktime())."'");
					$summary_seq = $CI->db->insert_id();
				}else{
					$summary_seq = $res['seq'];
				}
			}else{ $summary_seq = 0; }

			$memo = "";
			if(!$senduse){ $memo = "ERROR : MSG 누락@@";}
			if(!$result){ $memo = "ERROR : 전송실패!@@"; }
			if($memo){ $memo .= serialize($CI->config_sms)."@@".serialize($msg[$k]); }

			unset($log_params);
			$log_params['regist_date']	= date('Y-m-d H:i:s');
			$log_params['sms_cnt']		= '3';
			$log_params['summary_seq']	= $summary_seq;
			$log_params['sendres']		= ($senduse)? 'y':'n';				//제목없으면 false, 발송안함.
			$log_params['smsres']		= $result;							//SMS전송결과
			$log_params['kind']			= $params[$k]['data']['kind'];
			$log_params['to_mobile']	= $params[$k]['phone'];
			$log_params['member_seq']	= $params[$k]['data']['member_seq'];
			$log_params['sms_msg']		= $msg[$k];
			$log_params['memo']			= $memo;
			$log_params['reserve_date']	= $CI->sms_reserve;

			$logdata = filter_keys($log_params, $CI->db->list_fields('fm_log_curation_sms'));
			$log_result =  $CI->db->insert('fm_log_curation_sms', $logdata);
			### 일자별 발송 통계(정상 발송 되었을 경우에만 저장)
			if($log_result && $senduse){
				if($summary_seq){
					$CI->db->query("update fm_log_curation_summary set send_sms_total=send_sms_total+1 where seq='".$summary_seq."'");
				}else{
					$CI->db->query("insert into fm_log_curation_summary set inflow_kind='".$params[$k]['data']['kind']."',send_sms_total=1,send_date ='".date("Y-m-d",mktime())."'");
				}
			}
		}
	}

	/**
	 * 카카오 알림톡 실패시 SMS로 대체발송인지 여부
	 * @param boolean $by_kakao
	 */
	function set_by_kakao($by_kakao)
	{
	    $this->by_kakao = $by_kakao;
	}

	function history($params)
	{
		$request_xml = <<<DOC_XML
<request>
<sms-id>{$this->sms_id}</sms-id>
<access-token>{$this->md5_access_token}</access-token>
<response-format>xml</response-format>
<method>SMS.getTranLogs</method>
<params>
		<page>{$params['page']}</page>
		<per_page>{$params['per_page']}</per_page>
		<s_date>{$params['s_date']}</s_date>
		<e_date>{$params['e_date']}</e_date>
		<tran_phone>{$params['tran_phone']}</tran_phone>
		<tran_callback>{$params['tran_callback']}</tran_callback>
		<tran_kind>{$params['tran_kind']}</tran_kind>
		<tran_rslt>{$params['tran_rslt']}</tran_rslt>
		<tran_msg>{$params['tran_msg']}</tran_msg>
</params>
</request>
DOC_XML;
		$this->init($this->api_host, "api", "gabiasms");
		$this->m_szResultXML = $this->call($request_xml);
		$xmlParser = new SofeeXmlParser();
		$xmlParser->parseString($this->m_szResultXML);
		$tree = $xmlParser->getTree();
		$xmldata = base64_decode($tree['response']['result']['value']);
		if ($xmldata) {
			$xmlParser->parseString($xmldata);
			$tree = $xmlParser->getTree();
			$result['total'] = $tree['root']['total']['value'];
			$result['success'] = $tree['root']['success']['value'];
			$result['fail'] = $tree['root']['fail']['value'];
			if ($result['total'] == 1) {
				$data[] = $tree['root']['data']['item'];
			} else if ($result['total'] > 1) {
				$data = $tree['root']['data']['item'];
			}
			foreach ($data as $key1 => $data1) {
				foreach ($data1 as $key2 => $data2){
					$result['data'][$key1][$key2] = $data2['value'];
				}
			}
			return $result;
		}
	}

}

?>
