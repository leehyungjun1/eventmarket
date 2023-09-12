<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class kakaotalkmodel extends CI_Model {
	var $config_kakaotalk;	// 카카오 설정정보
	var $api_url;			// 통신 api url
	var $timeout;			// readurl 타임아웃
	var $detail_url;		// middleware url

	public function __construct() {
		parent::__construct();
		$this->load->helper('readurl');

		if(!$this->api_url || !$this->api_url){
			$this->set_api();
		}
	}

	protected function set_api(){

		$this->api_url	= "https://kakaobiz.firstmall.kr/";
		$this->timeout	= '10';
		$this->detail_url = array(
			'getService'		=> 'service_info.php',		// 서비스정보
			'getToken'			=> 'service_token.php',		// 토큰 요청
			'setRegist'			=> 'service_regist_v2.php',	// 서비스신청 :: lwh 2018-07-12 - V2로 업그레이드
			'registTemplte'		=> 'template_regist.php',	// 템플릿신청
			'modifyTemplte'		=> 'template_modify.php',	// 템플릿수정
			'commentTemplte'	=> 'template_comment.php',	// 템플릿문의
			'sendTalk'			=> 'send_msg_que.php',		// 알림톡 발송
			'categoryAll'		=> 'service_category.php'	// 카테고리조회
		);
	}

	################ API 연동 데이터 보내기 ####################
	public function apiSender($call_type,$params=array()){
		/*
		echo "apiSender Start<br/>";
		echo "call_type : " . $call_type . " <br/> ";
		print_r($params);
		//exit;
		*/

		$function	= 'set_'.$call_type;
		$data		= $this->$function($call_type, $params);

		return $data;
	}
	############################################################


	/* ##### SETTER :: START ###### */
	// 알림톡 발송
	protected function set_sendTalk($apiType, $commonSmsData){

		// 성공 실패 건수 초기화
		$succ = 0;
		$fail = 0;

		// API 연동 주소 호출
		$call_url	= $this->get_url($apiType);

		// 발송 가능여부 판단 - 건수제한은 중앙에서 관리하여 로그를 쌓도록..
		$config_kakaotalk	= $this->get_service();
		if($config_kakaotalk['status'] != 'A' || $config_kakaotalk['use_service'] == 'N'){
			$result['code']		= '400';
			$result['errmsg']	= '미사용 설정';
			return $result;
		}

		// API 데이터 조합
		$keys = array_keys($commonSmsData);

		foreach($keys as $case){
			
			$case_tmp = explode("_", $case);
			if($case_tmp[0] == "personal"){
				$msg_code = $case;
				$sms_mode = "sms_personal";
			}else{
				$msg_code = $case.'_user';
				$sms_mode = "sms";
			}

			$this->config_sms_info = ($this->config_sms_info)?$this->config_sms_info:config_load('sms_info');
			$this->config_sms		= ($this->config_sms['groupcd'] == $sms_mode )?$this->config_sms:config_load($sms_mode);

			// 알림톡 템플릿 추출
			unset($scParams);
			$scParams['msg_code'] = $msg_code;
			$tmp_tpl	= $this->get_template($scParams, false);
			$tpl_info	= $tmp_tpl[0];
			if ($tpl_info['msg_yn'] != 'Y')		continue; // 미사용 패스.
			if ($tpl_info['approval'] != 'A' && $tpl_info['kkoBizCode'] != $tpl_info['base_kkoBizCode']){ // 미승인시 기본 템플릿으로 발송.
				unset($scParams);
				$scParams['kkoBizCode']	= $tpl_info['base_kkoBizCode'];
				$tmp_tpl	= $this->get_base_template($scParams);
				$tpl_info	= $tmp_tpl[0];
			}
			if ($tpl_info['approval'] != 'A'){
				$failmsg[$case] = 'not use';
				$fail++;
				continue; // 미승인 패스.
			}

			$to_sms			= $commonSmsData[$case]['phone'];
			$params			= $commonSmsData[$case]['params'];
			$order_no		= $commonSmsData[$case]['order_no'];
			$mid			= $commonSmsData[$case]['mid'];

			// 발송 데이터 조합 1
			$sendData['shopno']			= $this->config_system['shopSno'];
			$sendData['authKey']		= $config_kakaotalk['authKey'];
			$sendData['kkoBizCode']		= $tpl_info['kkoBizCode'];

			$to_sms_count = count($to_sms);
			for($i=0; $i<$to_sms_count; $i++){

				// 치환문구 추출
				preg_match_all("/\#\{([^\}]*)\}/",$tpl_info['templateContents'],$matches1);
				$overCode1	= $matches1[1];
				preg_match_all("/\#\{([^\}]*)\}/",$tpl_info['kkoLinkPc'],$matches2);
				$overCode2	= $matches2[1];
				$overCode	= array_merge($overCode1, $overCode2);
				$overCode	= array_unique($overCode);
				// 치환문구에 따른 데이터 추출
				unset($overCode_arr);
				/**
				 * order_seq 필드 문제로 인하여 추가
				 * 2019-10-16
				 * @see common_helper/sendCheck()
				 * @author Sunha Ryu
				 */
				if( $order_no[$i] && !$params[$i]['ordno'] ) $params[$i]['ordno']	= $order_no[$i];
				if( !$order_no[$i] && $params[$i]['ordno'] )  $order_no[$i]			= $params[$i]['ordno'];
				$params[$i]['order_no'] = $order_no[$i];
				$overCode_arr = $this->get_replace_overCode($msg_code, $overCode, $params[$i]);

				// 발송데이터 임시
				$mobile_arr[$i]				= $to_sms[$i];
				$replace_msg_arr[$i]		= $overCode_arr;
				$smsData_arr[$i]['phone']	= $commonSmsData[$case]['phone'][$i];
				$smsData_arr[$i]['params']	= $commonSmsData[$case]['params'][$i];
				$smsData_arr[$i]['order_no']= $commonSmsData[$case]['order_no'][$i];
				$smsData_arr[$i]['mid']		= $commonSmsData[$case]['mid'][$i];
			}
			// 발송 데이터 조합 2
			$sendData['mobile_json']		= json_encode($mobile_arr);
			$sendData['replace_msg_json']	= json_encode($replace_msg_arr);
			$sendData['sms_msg_code']		= $case;
			$sendData['smsData_json']		= json_encode($smsData_arr);

			## 발송시간제한(예약전송)
			$rest_use = 'y';
			$config_sms_rest	= config_load('sms_restriction');	//SMS 발송시간제한
			$sms_use_chk		= $config_sms_rest[$case];
			if($sms_use_chk != "checked")		$rest_use = 'n';//미사용시
			if($sms_mode == "sms_personal")		$rest_use = 'n';

			//회원이(관리자/입점관리자/시스템이 아님) 주문접수/결제확인/배송완료/환불완료 상태일때에는 바로발송
			$userendstep = array('order','settle','cancel','delivery','delivery2','coupon_cancel','coupon_delivery','coupon_delivery2');
			if(
				$rest_use == 'y' &&
				!(
				defined('__ADMIN__') === true || defined('__SELLERADMIN__') === true
					|| in_array('_gabia',$this->uri->rsegments) || in_array('dev',$this->uri->rsegments) || in_array('_batch',$this->uri->rsegments) || $_SERVER['SHELL'] || php_sapi_name() == 'cli'
				)
				&& (in_array($case,$userendstep))
			) {
				$rest_use = 'n';
			}

			## 고객리마인드 예약시간은 /app/helpers/reservation_helper.php 에서 설정됨.
			$reservation = $this->sms_reserve;
			if($rest_use == 'y'){
				$reservation	= $this->sendSMS_restriction($case);
			}

			//미입금 통보 예약시간 설정
			if($case == 'deposit'){
				$reservation = date("Y-m-d")." ".$this->config_sms['deposit_send_time'].":00";
			}

			//휴면계정
			if($case == 'dormancy'){
				$reservation = date("Y-m-d")." ".$this->config_sms['dormancy_send_time'].":00";
			}

			if ($reservation){
				$sendData['reservation_date'] = $reservation;
			}

			// API 요청
			$read_data		= readurl($call_url,$sendData,false,3,$headers,false);
			$read_arr		= json_decode($read_data,true);

			// 결과 추출
			if($read_arr['code'] == '200'){
				$commonSmsData[$case]['talkYN'] = 'Y';
				$succ++;
			}else{
				$failmsg[$case] = 'servermsg : '.$read_arr['errmsg'];
				$commonSmsData[$case]['talkYN'] = 'N';
				$fail++;
			}
		}

		// 총합 결과
		$result['total']	= count($keys);
		$result['succ_cnt']	= $succ;
		$result['fail_cnt']	= $fail;

		if($result['total'] == $result['succ_cnt']){
			$result['code']		= '200';
			$result['SmsData']	= $commonSmsData;
		}else{
			$result['code']		= false;
			$result['failmsg']	= $failmsg;
			$result['SmsData']	= $commonSmsData;
		}


		return $result;
	}

	// 인증번호 발송
	protected function set_getToken($apiType, $params){

		// API 연동 주소 호출
		$call_url	= $this->get_url($apiType);

		if(!$params['domain']){
			$domain = config_load('system','subDomain');
			$params['domain'] = $domain['subDomain'];
		}

		// API 요청
		$read_data	= readurl($call_url,$params);

		return $read_data;
	}

	// 카테고리 조회
	protected function set_categoryAll($apiType, $params){

		// API 연동 주소 호출
		$call_url	= $this->get_url($apiType);

		// API 데이터 조합
		$params['shopno']	= $this->config_system['shopSno'];

		// API 요청
		$read_data			= readurl($call_url,$params,false,$this->timeout,$headers,false);
		$read_arr			= json_decode($read_data,true);
		$category_json		= json_encode($read_arr['category']);
		
		return $category_json;
	}

	// 서비스 이용신청 송수신 API 연동
	protected function set_setRegist($apiType, $params){

		// API 연동 주소 호출
		$call_url	= $this->get_url($apiType);

		// API 데이터 조합
		$headers			= array("'Content-Type:multipart/form-data'");
		$params['shopno']	= $this->config_system['shopSno'];
		$params['domain']	= $this->config_system['subDomain'];

		if ($params['authKey'] && $params['token']){
			if(!$params['domain']){
				$domain = config_load('system','subDomain');
				$params['domain'] = $domain['subDomain'];
			}

			// API 요청
			$read_data		= readurl($call_url,$params,false,$this->timeout,$headers,false);
			$read_arr		= json_decode($read_data,true);

			if ($read_arr['serviceId']){
				// DB 저장
				$setParams['authKey']			= $params['authKey'];
				$setParams['yellowId']			= $params['yellowId'];
				$setParams['phoneNumber']		= $params['phoneNumber'];
				$setParams['businessLicense']	= $params['businessLicense'];
				$setParams['category']			= $params['category3'];
				$setParams['category_name']		= $params['category_name'];
				$setParams['serviceId']			= $read_arr['serviceId'];
				$setParams['status']			= $read_arr['status'];
				$setParams['use_service']		= 'N';
				$setParams['modify_date']		= date('Y-m-d H:i:s');
				$this->set_kakaoConfig($setParams);
			}
		}else{
			$read_arr['code']	= '404';
			$read_arr['errmsg']	= '데이터 오류';
		}

		return $read_arr;
	}

	// 템플릿 수정 API 연동
	protected function set_registTemplte($apiType, $params){

		// 서비스 정보 추출
		$config_kakaotalk = $this->get_service();
		if ($config_kakaotalk){
			if ($config_kakaotalk['status'] != 'A'){
				$result['code']		= '404';
				$result['errmsg']	= '알림톡 서비스 승인 후 가능합니다';

				return $result;
			}
		}else{
			$result['code']		= '404';
			$result['errmsg']	= '알림톡 서비스를 먼저 신청해주세요';

			return $result;
		}

		// 기존 템플릿이 그룹템플릿이면 등록으로, 개별템플릿이면 수정으로 보냄.
		if ($params['kkoBizCode'] != $params['base_kkoBizCode']){
			$apiType = 'modifyTemplte';
		}

		// API 연동 주소 호출
		$call_url	= $this->get_url($apiType);	

		// API 데이터 조합
		$data['senderKeyType']		= 'S';
		$data['authKey']			= $config_kakaotalk['authKey'];
		$data['shopno']				= $this->config_system['shopSno'];
		$data['msg_type']			= $params['msg_type'];
		$data['msg_code']			= $params['msg_code'];
		$data['kkoBizCode']			= $params['kkoBizCode'];
		$data['note']				= '[개별]' . $params['msg_title'] . '_' . date('YmdHis');
		$data['templateContents']	= $params['templateContents'];

		unset($buttons);
		if($params['kkoLinkType'] && $params['kkoBtnYn'] == 'Y'){
			foreach($params['kkoLinkType'] as $k => $v){
				$buttons[$k]['kkoLinkType']	= $params['kkoLinkType'][$k];
				$buttons[$k]['kkoLinkName']	= $params['kkoLinkName'][$k];
				$buttons[$k]['kkoLinkPc']	= $params['kkoLinkPc'][$k];
			}
			$data['buttons']	= $buttons;
		}

		// API 요청
		$read_data		= readurl($call_url,$data,false,$this->timeout,$headers,true);
		$read_arr		= json_decode($read_data,true);
		// 결과 추출
		if($read_arr['code'] == '200'){
			// 템플릿 임시 저장
			$reg_param['senderKeyType']		= 'S';
			$reg_param['approval']			= $read_arr['approval'];
			$reg_param['note']				= $data['note'];
			$reg_param['templateContents']	= $params['templateContents'];
			$reg_param['kkoBtnYn']			= $params['kkoBtnYn'];
			$reg_param['kkoBizCode']		= $read_arr['kkoBizCode'];
			$reg_param['kkoLinkType']		= implode('|ф|', $params['kkoLinkType']);
			$reg_param['kkoLinkName']		= implode('|ф|', $params['kkoLinkName']);
			$reg_param['kkoLinkPc']			= implode('|ф|', $params['kkoLinkPc']);
			$reg_param['regist_date']		= date('Y-m-d H:i:s');
			$this->set_template($reg_param);

			// 요청된 결과로 템플릿 교체작업
			$this->set_msg_type($params['msg_code'], $read_arr['kkoBizCode']);
		}

		return $read_arr;
	}

	// 템플릿 문의하기 API 연동
	protected function set_commentTemplte($apiType, $params){

		// API 연동 주소 호출
		$call_url	= $this->get_url($apiType);	

		// 서비스 정보 추출
		$config_kakaotalk = $this->get_service();
		if ($config_kakaotalk){
			if ($config_kakaotalk['status'] != 'A'){
				$result['code']		= '404';
				$result['errmsg']	= '알림톡 서비스 승인 후 가능합니다';

				return $result;
			}
		}else{
			$result['code']		= '404';
			$result['errmsg']	= '알림톡 서비스를 먼저 신청해주세요';

			return $result;
		}
		$template_info			= $this->get_template(array('kkoBizCode'=>$params['kkoBizCode']), false);

		// API 데이터 조합
		$params['senderKeyType']= $template_info[0]['senderKeyType'];
		$params['authKey']		= $config_kakaotalk['authKey'];
		$params['shopno']		= $this->config_system['shopSno'];

		// API 요청
		$read_data		= readurl($call_url,$params,false,$this->timeout,$headers,true);
		$read_arr		= json_decode($read_data,true);
		
		return $read_arr;
	}

	// 서비스 정보 추출 - 건수 추출시 사용
	protected function set_getService($apiType, $params){
		// 기본 설정정보
		$config_kakaotalk	= $this->get_service();

		// API 연동 주소 호출
		$call_url	= $this->get_url($apiType);

		// API 데이터 조합
		$params['authKey']	= $config_kakaotalk['authKey'];
		$params['shopno']	= $this->config_system['shopSno'];

		if ($params['authKey']){
			// API 요청
			$read_data		= readurl($call_url,$params);
			$read_arr		= json_decode($read_data,true);

			$config_kakaotalk = array_merge($read_arr,$config_kakaotalk);
		}

		return $config_kakaotalk;
	}

	// 템플릿 저장
	public function set_template($params){
		// ON DUPLICATE KEY
		$sql = "
			INSERT INTO fm_kakao_template (
				`kkoBizCode`, `senderKeyType`,
				`approval`, `comments`,	`note`, `templateContents`,
				`kkoBtnYn`, `kkoLinkType`, `kkoLinkName`,
				`kkoLinkPc`, `kkoLinkMo`, `kkoLinkIos`, `kkoLinkAnd`,
				`varUrlYn`,	`regist_date`, `modify_date`
			) VALUES (
				'".$params['kkoBizCode']."', '".$params['senderKeyType']."',
				'".$params['approval']."', '".addslashes($params['comments'])."', '".addslashes($params['note'])."',
				'".addslashes($params['templateContents'])."', '".$params['kkoBtnYn']."',
				'".$params['kkoLinkType']."', '".addslashes($params['kkoLinkName'])."',
				'".addslashes($params['kkoLinkPc'])."', '".addslashes($params['kkoLinkMo'])."',
				'".$params['kkoLinkIos']."', '".$params['kkoLinkAnd']."',
				'".$params['varUrlYn']."', '".$params['regist_date']."',
				'".$params['modify_date']."'
			)
			ON DUPLICATE KEY
			UPDATE
				`kkoBizCode`		= '" . $params['kkoBizCode'] . "',
				`senderKeyType`		= '" . $params['senderKeyType'] . "',
				`approval`			= '" . $params['approval'] . "',
				`comments`			= '" . addslashes($params['comments']) . "',
				`note`				= '" . addslashes($params['note']) . "',
				`templateContents`	= '" . addslashes($params['templateContents']) . "',
				`kkoBtnYn`			= '" . $params['kkoBtnYn'] . "',
				`kkoLinkType`		= '" . $params['kkoLinkType'] . "',
				`kkoLinkName`		= '" . addslashes($params['kkoLinkName']) . "',
				`kkoLinkPc`			= '" . addslashes($params['kkoLinkPc']) . "',
				`kkoLinkMo`			= '" . addslashes($params['kkoLinkMo']) . "',
				`kkoLinkIos`		= '" . $params['kkoLinkIos'] . "',
				`kkoLinkAnd`		= '" . $params['kkoLinkAnd'] . "',
				`varUrlYn`			= '" . $params['varUrlYn'] . "',
				`regist_date`		= '" . $params['regist_date'] . "',
				`modify_date`		= '" . $params['modify_date'] . "',
				`update_date`		= '" . date('Y-m-d H:i:s') . "'
		";
		$result		= $this->db->query($sql);
		$affect		= $this->db->affected_rows(); // 적용내용 | 0:no 1:insert 2:update

		return $result; // DB 정상 실행일 경우 리턴
	}

	// 발송상황 사용여부 수정
	public function set_template_use($params){
		$result = false;
		if($params['msg_code'] && $params['msg_yn']){
			$sql = "
				UPDATE fm_kakao_msg_type
				SET
					msg_yn = ?,
					modify_date = '" . date('Y-m-d H:i:s') . "'
				WHERE
					msg_code = ?
			";
			$this->db->query($sql, array($params['msg_yn'],$params['msg_code']));
			$result = true;
		}

		return $result;
	}

	// 발송상황 저장
	public function set_msg_type($msg_code, $kkoBizCode){
		$sql = "
			UPDATE fm_kakao_msg_type
			SET
				kkoBizCode = '" . $kkoBizCode . "'
			WHERE
				msg_code = ?
		";
		$this->db->query($sql, array($msg_code));
	}

	// 사용자 상태 변경
	public function set_kakaoConfig($params){

		if($params['authKey'])
			config_save('kakaotalk',array('authKey'			=> $params['authKey']));
		if($params['yellowId'])
			config_save('kakaotalk',array('yellowId'		=> $params['yellowId']));
		if($params['phoneNumber'])
			config_save('kakaotalk',array('phoneNumber'		=> $params['phoneNumber']));
		if($params['serviceId'])
			config_save('kakaotalk',array('serviceId'		=> $params['serviceId']));
		if($params['businessLicense'])
			config_save('kakaotalk',array('businessLicense'	=> $params['businessLicense']));
		if($params['category'])
			config_save('kakaotalk',array('category'		=> $params['category']));
		if($params['category_name'])
			config_save('kakaotalk',array('category_name'	=> $params['category_name']));
		if($params['use_service']){
			config_save('kakaotalk',array('use_service'		=> $params['use_service']));
			$this->config_kakaotalk['use_service'] = $params['use_service'];
		}
		if($params['modify_date']){
			config_save('kakaotalk',array('modify_date'	=> $params['modify_date']));
			$this->config_kakaotalk['modify_date'] = $params['modify_date'];
		}
		if($params['status']){
			$status_txt = $this->get_servicecode($params['status']);
			config_save('kakaotalk',array('status_txt'	=> $status_txt));
			$this->config_kakaotalk['status_txt'] = $status_txt;

			config_save('kakaotalk',array('status'		=> $params['status']));
			$this->config_kakaotalk['status'] = $params['status'];
		}

		return true;
	}

	// Template 기본 설정 -> 값이 없을경우 최초 기본 셋팅
	public function set_template_default_code($add_msgType=array()){

		// 중계서버 코드 추출
		$call_url		= $this->get_url('getService');
		$read_data		= readurl($call_url,array('getType'=>'M'));
		$read_arr		= json_decode($read_data,true);
		$msgType_arr	= $read_arr['msgType_arr'];
		$msgType_code	= $read_arr['msgType_code'];
		if($read_arr['msgType_del'])
			$msgType_del	= $read_arr['msgType_del'];

		# 추가시 아래와 같은 형태로 보내서 처리
		//$add_msgType['메세지타입']['순번']['메세지코드'] = '템플릿코드';
		foreach($add_msgType as $msg_type => $value){
			$sort_tmp	= array_keys($value);
			$sort_seq	= $sort_tmp[0];
			$kkoBizCode	= $msg_code_arr;

			$msgType_arr[$msg_type][$sort_seq] = $value[$sort_seq];
		}

		$msg_type = array('common','goods','ticket','remind','present');
		foreach($msg_type as $k => $msgtype){
			foreach($msgType_arr[$msgtype] as $sort_seq => $msg_code_arr){
				$msg_tmp	= array_keys($msg_code_arr);
				$msg_code	= $msg_tmp[0];
				$kkoBizCode	= $msg_code_arr[$msg_code];

				$sql = "
					INSERT INTO fm_kakao_msg_type (
						`msg_code`, `msg_type`, `sort_seq`,
						`kkoBizCode`, `base_kkoBizCode`,
						`msg_txt`
					) VALUES (
						'".$msg_code."', '".$msgtype."', '".$sort_seq."',
						'".$kkoBizCode."', '".$kkoBizCode."',
						'". $msgType_code[$msg_code] ."'
					)
					ON DUPLICATE KEY
					UPDATE
						`msg_type`			= '" . $msgtype . "',
						`sort_seq`			= '" . $sort_seq . "',
						`base_kkoBizCode`	= '" . $kkoBizCode . "',
						`msg_txt`			= '" . $msgType_code[$msg_code] . "'
				";
				$result		= $this->db->query($sql);
				$affect		= $this->db->affected_rows();
			}
		}

		// 중앙 관제 삭제 처리
		if(count($msgType_del) > 0){
			$del_sql = "DELETE FROM `fm_kakao_msg_type` WHERE `msg_code` IN ('" . implode("','", $msgType_del) . "')";
			$this->db->query($del_sql);
		}
	}

	// 템플릿 동기화 ###########################
	// 전체 동기화 / 중앙서버에서 추가된 템플릿 추가는 되지만 기존 더미데이터는 삭제하지 않는다.
	// 연결고리가 정확한 데이터만 실데이터로 인정.
	public function set_template_sync($mode=null){
		if ($mode)
			$data['mode']	= $mode;
		$data['getType']	= 'T';
		$tpl_list			= $this->get_server_tpl($data);
		$cnt = 0;
		if($tpl_list['tplUnit']) foreach($tpl_list['tplUnit'] as $k => $tplInfo){
			$res = $this->set_template($tplInfo);
			if($res)	$cnt++;
		}

		if($cnt == count($tpl_list['tplUnit'])){
			return $cnt;
		}else{
			return false;
		}
	}

	/* ##### SETTER :: END ###### */


	/* ##### GETTER :: START ###### */

	// API URL 구해오기
	protected function get_url($apiType){
		if	(!$apiType) return false;

		if	($this->api_url && $this->detail_url[$apiType]){
			return $this->api_url.$this->detail_url[$apiType];
		}else{
			$this->set_api();
			$this->get_url($apiType);
		}
	}

	// 충전 내역 추출
	public function get_charge_log($params){

		// 기본 설정정보
		$config_kakaotalk	= $this->get_service();

		// API 연동 주소 호출
		$call_url	= $this->get_url('getService');

		// API 데이터 조합
		$params['authKey']	= $config_kakaotalk['authKey'];
		$params['shopno']	= $this->config_system['shopSno'];
		if ($params['shopno']){
			// API 요청
			$read_data		= readurl($call_url,$params);
			$xml_list_tmp	= xml2array($read_data);
			$kakaobiz		= $xml_list_tmp['kakaobiz'];
			if($kakaobiz['chargeList']['chaUnit'][0])
				$kko_log_list	= $kakaobiz['chargeList']['chaUnit'];
			else if($kakaobiz['chargeList']['chaUnit'])
				$kko_log_list[0]= $kakaobiz['chargeList']['chaUnit'];
			else
				unset($kakaobiz['chargeList']);

			$kakaobiz['chargeList']		= $kko_log_list;
			$kakaobiz['serviceInfo']	= ($config_kakaotalk) ? array_merge($kakaobiz['serviceInfo'],$config_kakaotalk) : $kakaobiz['serviceInfo'];

			return $kakaobiz;
		}
	}

	// 템플릿 정보를 중계서버에서 추출
	public function get_server_tpl($params){

		// 기본 설정정보
		$config_kakaotalk	= $this->get_service();

		// API 연동 주소 호출
		$call_url	= $this->get_url('getService');

		// API 데이터 조합
		$params['authKey']	= $config_kakaotalk['authKey'];
		$params['shopno']	= $this->config_system['shopSno'];
		if ($params['shopno']){
			// API 요청
			$read_data		= readurl($call_url,$params);
			$xml_list_tmp	= xml2array($read_data);
			$tpl_list		= $xml_list_tmp['templateList'];
		}

		return $tpl_list;
	}

	// 알림톡 발송 내역 추출
	public function get_send_log($params){

		// 기본 설정정보
		$config_kakaotalk	= $this->get_service();

		// API 연동 주소 호출
		$call_url	= $this->get_url('getService');

		// API 데이터 조합
		$params['authKey']	= $config_kakaotalk['authKey'];
		$params['shopno']	= $this->config_system['shopSno'];
		if ($params['authKey'] && $params['shopno']){
			// API 요청
			$read_data		= readurl($call_url,$params);
			$xml_list_tmp	= xml2array($read_data);
			$log_list		= $xml_list_tmp['kakaobiz'];

			return $log_list;
		}
	}

	// 알림톡 발송 내역 상세 추출
	public function get_send_log_detail($params){
		// 기본 설정정보
		$config_kakaotalk	= $this->get_service();

		// API 연동 주소 호출
		$call_url	= $this->get_url('getService');

		// API 데이터 조합
		$params['authKey']	= $config_kakaotalk['authKey'];
		$params['shopno']	= $this->config_system['shopSno'];
		if ($params['authKey'] && $params['shopno'] && $params['uid']){
			// API 요청
			$read_data		= readurl($call_url,$params);
		}
		return $read_data;
	}

	// 기본 템플릿 추출
	public function get_base_template($scParams){
		// 메세지 발송코드 검색
		if ($scParams['msg_code']){
			$whereis[]	= "mt.msg_code = ?";
			$bind[]		= $scParams['msg_code'];
		}

		// 카카오 템플릿 코드 검색
		if ($scParams['kkoBizCode']){
			$whereis[]	= "kt.kkoBizCode = ?";
			$bind[]		= $scParams['kkoBizCode'];
		}

		$sel_sql = "
			SELECT
				mt.msg_type, mt.msg_code, mt.msg_yn, mt.msg_txt,
				mt.sort_seq, mt.base_kkoBizCode, mt.modify_date AS msg_date,
				kt.*
			FROM fm_kakao_msg_type AS mt INNER JOIN fm_kakao_template AS kt
				ON mt.base_kkoBizCode = kt.kkoBizCode
		";
		if(count($whereis) > 0){
			$sel_sql .= " WHERE ";
			$sel_sql .= implode(" AND ", $whereis);
		}

		$query	= $this->db->query($sel_sql, $bind);
		foreach($query->result_array() as $msg){
			if ($msg['kkoLinkType'] && $msg['kkoBtnYn'] == 'Y'){
				$msg['kkoLinkType_arr']		= explode('|ф|', $msg['kkoLinkType']);
				$msg['kkoLinkName_arr']		= explode('|ф|', $msg['kkoLinkName']);
				$msg['kkoLinkPc_arr']		= explode('|ф|', $msg['kkoLinkPc']);
				$msg['kkoLinkMo_arr']		= explode('|ф|', $msg['kkoLinkMo']);
				$msg['varUrlYn_arr']		= explode('|ф|', $msg['varUrlYn']);
			}

			if ($msg['comments']){
				unset($comments);
				$comments = preg_replace('/(\\\\r\\\\n|\\\\r|\\\\n)/', '<br>', $msg['comments']); 
				$comments_arr			= json_decode($comments,true);
				$msg['comments_arr']	= $comments_arr;
			}

			$result[] = $msg;
		}

		return $result;
	}

	// 솔루션 저장된 템플릿 추출
	public function get_template($scParams, $typeArr=true){

		// 메세지 발송코드 검색
		if ($scParams['msg_code']){
			$whereis[]	= "mt.msg_code = ?";
			$bind[]		= $scParams['msg_code'];
		}
		// 메세지 구분검색
		if ($scParams['msg_type']){
			$whereis[]	= "mt.msg_type = ?";
			$bind[]		= $scParams['msg_type'];
		}
		// 카카오 템플릿 코드 검색
		if ($scParams['kkoBizCode']){
			$whereis[]	= "kt.kkoBizCode = ?";
			$bind[]		= $scParams['kkoBizCode'];
		}
		// 템플릿 상태 검색
		if ($scParams['approval']){
			if(is_array($scParams['approval'])){
				$whereis[]	= "kt.approval IN ('" . implode("', '", $approval) . "')";
			}else{
				$whereis[]	= "kt.approval = ?";
				$bind[]		= $scParams['approval'];
			}
		}

		$sel_sql = "
			SELECT
				mt.msg_type, mt.msg_code, mt.msg_yn, mt.msg_txt,
				mt.sort_seq, mt.base_kkoBizCode, mt.modify_date AS msg_date,
				kt.*
			FROM fm_kakao_msg_type AS mt INNER JOIN fm_kakao_template AS kt
				ON mt.kkoBizCode = kt.kkoBizCode
		";
		if(count($whereis) > 0){
			$sel_sql .= " WHERE ";
			$sel_sql .= implode(" AND ", $whereis);
		}

		// order by
		if ($scParams['order_by']){
			$sel_sql .= " ORDER BY " . $scParams['order_by'][0] . " " . $scParams['order_by'][1];
		}else{
			$sel_sql .= " ORDER BY msg_type, sort_seq ASC";
		}

		$query	= $this->db->query($sel_sql, $bind);
		foreach($query->result_array() as $msg){
			if ($msg['kkoLinkType'] && $msg['kkoBtnYn'] == 'Y'){
				$msg['kkoLinkType_arr']		= explode('|ф|', $msg['kkoLinkType']);
				$msg['kkoLinkName_arr']		= explode('|ф|', $msg['kkoLinkName']);
				$msg['kkoLinkPc_arr']		= explode('|ф|', $msg['kkoLinkPc']);
				$msg['kkoLinkMo_arr']		= explode('|ф|', $msg['kkoLinkMo']);
				$msg['varUrlYn_arr']		= explode('|ф|', $msg['varUrlYn']);
			}

			if ($msg['comments']){
				unset($comments);
				$comments = preg_replace('/(\r|\n|\r\n)/', '<br>', $msg['comments']); 
				$comments_arr			= json_decode($comments,true);
				$msg['comments_arr']	= $comments_arr;
			}

			if($typeArr){
				$result[$msg['msg_type']][$msg['sort_seq']] = $msg;
			}else{
				$result[] = $msg;
			}
		}

		return $result;
	}

	// 솔루션 저장된 메세지타입 추출
	public function get_msg_code($scParams, $typeArr=true){
		// 메세지 발송코드 검색
		if ($scParams['msg_code']){
			if (is_array($scParams['msg_code'])){
				$multiwhere[] = "msg_code IN ('".implode("','",$scParams['msg_code'])."')";
			}else{
				$whereis[]	= "msg_code = ?";
				$bind[]		= $scParams['msg_code'];
			}
		}
		// 메세지 구분검색
		if ($scParams['msg_type']){
			$whereis[]	= "msg_type = ?";
			$bind[]		= $scParams['msg_type'];
		}
		// 카카오 템플릿 코드 검색
		if ($scParams['kkoBizCode']){
			$whereis[]	= "kkoBizCode = ?";
			$bind[]		= $scParams['kkoBizCode'];
		}

		$sel_sql = "SELECT * FROM fm_kakao_msg_type";
		if(count($whereis) > 0 || count($multiwhere) > 0){
			$sel_sql .= " WHERE ";
			if (count($whereis) > 0)	$sel_sql .= implode(" AND ", $whereis);
			if (count($multiwhere) > 0)	$sel_sql .= implode(" AND ", $multiwhere);
		}
		$sel_sql .= " ORDER BY msg_type, sort_seq ASC";

		$query	= $this->db->query($sel_sql, $bind);
		foreach($query->result_array() as $msgcode){
			if($typeArr){
				$result[$msgcode['msg_code']] = $msgcode;
			}else{
				$result[] = $msgcode;
			}
		}

		return $result;
	}

	/* ##### GETTER :: END ###### */

	/* ##### 공용 함수 :: START ###### */

	// 치환코드 내용 추출
	public function get_replace_overCode($msg_code, $overCode, $params = array()){
		$this->load->model('membermodel');
		$this->load->model('exportmodel');
		$this->load->helper('shipping');

		// 공통 작업 ST -------------------------------------------------
		$shopDomain		= ($this->config_basic['domain']) ? $this->config_basic['domain'] : $_SERVER['HTTP_HOST'];
		$goods_limit	= config_load('sms_goods_limit');
		if ( $msg_code == 'released' && $params['export_code']){
			if ( !$params['goods_name'] ) {
				$items = $this->exportmodel->get_export_item($params['export_code']);
				$params['goods_name']	= $items[0]['goods_name'];
				if	(count($items) > 1)
					$params['goods_name']	.= '외 '.(count($items) - 1).'건';
			}
		}else if ($params['order_no']){
			if ( !$params['goods_name'] ) {
				$items = get_data("fm_order_item",array("order_seq"=>$params['order_no']));
				$params['goods_name']	= $items[0]['goods_name'];
				if	(count($items) > 1)
					$params['goods_name']	.= '외 '.(count($items) - 1).'건';
			}
		}
		if ($params['ordno']){
			$orders = get_data("fm_order",array("order_seq"=>$params['ordno']));
			$params['settleprice']		= get_currency_price($orders[0]['settleprice'],2);
			$params['user_name']		= $orders[0]['order_user_name'];
			$params['order_user']		= $orders[0]['order_user_name'];
			$params['order_user_name']	= $orders[0]['order_user_name'];
			switch($orders[0]['payment']){
				case "card": $temp_text = "신용카드 결제완료"; break;
				case "bank": $temp_text = $orders[0]['bank_account']." 입금확인"; break;
				case "account": $temp_text = $orders[0]['bank_account']." 계좌이체완료"; break;
				case "cellphone": $temp_text = "휴대폰 결제완료"; break;
				case "virtual": $temp_text = $orders[0]['virtual_account']." 입금확인"; break;
				case "escrow_virtual": $temp_text = $orders[0]['virtual_account']." 입금확인"; break;
				case "escrow_account": $temp_text = $orders[0]['bank_account']." 계좌이체완료"; break;
				default: $temp_text = "결제완료"; break;
			}
			$params['settle_kind']	 = $temp_text;

			if($orders[0]['step']>='25' && $orders[0]['step']<='85'){
				if($params['export_code']){
					$exports = get_data("fm_goods_export",array("export_code"=>$params['export_code']));
				}else{
					$exports = get_data("fm_goods_export",array("order_seq"=>$params['ordno']));
				}
				if($exports){
					//받는분
					if($exports[0]['shipping_seq']){
						$shipping = get_data("fm_order_shipping",array("shipping_seq"=>$exports[0]['shipping_seq']));
						$params['recipient_user']	= $shipping[0]['recipient_user_name'];
					}
					$params['export_code']	 = $exports[0]['export_code'];
					if	(!$params['delivery_number'])
						$params['delivery_number']	 = $exports[0]['delivery_number'];
					if	(!$params['delivery_company']){
						$tmp = config_load('delivery_url',$exports[0]['delivery_company_code']);
						foreach(get_invoice_company($exports[0]['shipping_provider_seq']) as $k=>$data){
							$tmp[$k] = $data;
						}
						$params['delivery_company']	 = $tmp[$exports[0]['delivery_company_code']]['company'];
					}
				}
				if(!$shipping[0]['recipient_user_name']) $params['recipient_user'] = $orders[0]['recipient_user_name'];
			}
			$params['deadline'] = date('m월 d일', strtotime($orders[0]['deposit_date'] . '+4 day'));
			$params['inputUrl'] = present_input_url([
				'order_seq' => $orders[0]['order_seq'],
				'present_receive' => $orders[0]['recipient_cellphone'],
			]);
		}
		// 공통 작업 ED -------------------------------------------------

		if (count($overCode) > 0) foreach($overCode as $k => $rep_code){
			unset($rep_str);
			switch($rep_code){
				case 'shopName'		:	// 쇼핑몰이름
					$rep_str = $this->config_basic['shopName'];
				break;
				case 'shopDomain'	:	// 쇼핑몰 도메인
					$rep_str = $shopDomain;
				break;
				case 'userid'		:	// 회원아이디
					$userid = $params['userid'];
					if (!$userid){
						if (!$member_tmp){
							$member_tmp = $this->membermodel->get_member_data($params['member_seq']);
						}
						$userid = $member_tmp['userid'];
					}
					$rep_str = $userid;
				break;
				case 'userName'		:	// 회원명
				case 'username'		:	// 회원명
					$user_name = $params['user_name'];
					if (!$user_name){
						if (!$member_tmp){
							$member_tmp = $this->membermodel->get_member_data($params['member_seq']);
						}
						$user_name = $member_tmp['user_name'];
					}
					if (!$user_name && $params['order_user_name']){
						$user_name = $params['order_user_name'];
					}
					$rep_str = $user_name;
				break;
				case 'order_user'	:	// 주문자명
					$order_user = $params['order_user_name'];
					if (!$order_user && $params['ordno']){
						if (!$orders){
							$orders = get_data("fm_order",array("order_seq"=>$params['ordno']));
						}
						$order_user = $orders[0]['order_user_name'];
					}
					$rep_str = $order_user;
				break;
				case 'userlevel' : // 회원등급
					$group_name = $member_tmp['group_name'];
					if (!$group_name){
						if (!$member_tmp){
							$member_tmp = $this->membermodel->get_member_data($params['member_seq']);
						}
						$group_name = $member_tmp['group_name'];
					}
					$rep_str = $group_name;
				break;
				case 'ordno'		:	// 주문번호
					$ordno = ($params['order_no']) ? $params['order_no'] : $params['ordno'];
					$rep_str = $ordno;
				break;
				case 'ord_item'		:	// 주문상품
					if($goods_limit['ord_item_use'] == 'y'){
						$ord_item = getstrcut($params['goods_name'],$goods_limit['ord_item_limit']);
					}else{
						$ord_item = $params['goods_name'];
					}
					$rep_str = $ord_item;
				break;
				case 'bank_account'	:	// 입금은행 계좌번호 예금주
					$rep_str = $params['bank_account'];
				break;
				case 'settleprice'	:	// 입금(결제)금액
					$rep_str = $params['settleprice'];
				break;
				case 'password'		:	// 임시비밀번호
					$rep_str = $params['passwd'];
				break;
				case 'settle_kind'	:	// 결제수단 수단별 확인메시지
					$rep_str = $params['settle_kind'];
				break;
				case 'dormancy_du_date':	// 휴면예정일
					// _batch.php dormancy_request() 에서 넘어오는 데이터는 lastlogin_date 가 없고 dormancy_du_date 만 있음
					// lastlogin_date 있는 경우(휴면 수동고지)에만 재정의되도록 수정 2018-04-18
					if ( isset($params['lastlogin_date']) ) {
						$last_login_date = substr($params['lastlogin_date'],0,10);
						$params['dormancy_du_date'] = substr($last_login_date,0,4)+1;
						$params['dormancy_du_date'] .= substr($last_login_date,4,10);
					}

					$rep_str = $params['dormancy_du_date'];
				break;
				case 'recipient_user':	// 받는분
					$rep_str = $params['recipient_user'];
				break;
				case 'go_item'		:	// 출고완료/배송완료 상품
					if($goods_limit['go_item_use'] == 'y'){
						$params['go_item'] = getstrcut($params['goods_name'],$goods_limit['go_item_limit']);
					}else{
						$params['go_item'] = $params['goods_name'];
					}
					$rep_str = $params['go_item'];
				break;
				case 'delivery_company':	// 택배사명
					$rep_str = $params['delivery_company'];
				break;
				case 'delivery_number':	// 운송장번호
					$rep_str = $params['delivery_number'];
				break;
				case 'repay_item'	:	// 취소/반품->환불완료 상품
					if($goods_limit['repay_item_use'] == 'y'){
						$params['repay_item'] = getstrcut($params['goods_name'],$goods_limit['repay_item_limit']);
					}else{
						$params['repay_item'] = $params['goods_name'];
					}
					$rep_str = $params['repay_item'];
				break;
				case 'coupon_serial':	// 티켓인증코드
					$rep_str = $params['coupon_serial'];
				break;
				case 'couponNum'	:	// 티켓발송회차
					$rep_str = $params['couponNum'];
				break;
				case 'coupon_value'	:	// 티켓값어치
					$rep_str = $params['coupon_value'];
				break;
				case 'options'		:	// 필수옵션
					$rep_str = $params['options'];
				break;
				case 'goods_name'	:	// 티켓상품
					$rep_str = $params['goods_name'];
				break;
				case 'used_time'	:	// 티켓사용일시
					$rep_str = $params['used_time'];
				break;
				case 'coupon_used'	:	// 티켓사용 값어치
					$rep_str = $params['coupon_used'];
				break;
				case 'coupon_remain':	// 티켓잔여 값어치
					$rep_str = $params['coupon_remain'];
				break;
				case 'used_location':	// 티켓 사용처
					$rep_str = $params['used_location'];
				break;
				case 'confirm_person':	// 티켓사용 확인자
					$rep_str = $params['confirm_person'];
				break;
				case 'mypage_short_url':	// short url
					if(!$params['mypage_short_url_m']){
						$mypage_short_url1 = ($CI->config_system['domain']) ? "http://".$CI->config_system['domain'] : "http://".$CI->config_system['subDomain'];
						$mypage_short_url2	= $mypage_short_url1."/personal_referer/access?param=".$param_tmp;
						list($params['mypage_short_url_m'], $shorturl_result) = get_shortURL($mypage_short_url2);
						$sns = config_load('snssocial', 'shorturl_keyType');
						## 짧은 URL 오류시 긴 URL로 대체
						if($shorturl_result === false || (parse_url($params['mypage_short_url_m'], PHP_URL_SCHEME)!='https' && $sns['shorturl_keyType'] == 'token')){
							$params['mypage_short_url_m'] = $mypage_short_url2;
						}
					}
					$rep_str = $params['mypage_short_url_m'];
				break;
				case 'deadline' : // 선물하기 배송지 등록 마감일
					$rep_str = $params['deadline'];
				break;
				case 'inputUrl' : //선물하기 등록 URL
					$rep_str = $params['inputUrl'];
				break;
			}

			$result[$rep_code] = $rep_str;
		}

		return $result;
	}

	// 서비스 정보 추출
	public function get_service(){

		if (array_key_exists('authkey',$this->config_kakaotalk) || array_key_exists('status',$this->config_kakaotalk)) {
			return $this->config_kakaotalk;
		}

		$config_kakaotalk = config_load('kakaotalk');
		if ($config_kakaotalk){
			if (!$config_kakaotalk['status_txt']){
				$config_kakaotalk['status_txt'] = $this->get_servicecode($config_kakaotalk['status']);
			}
			$config_kakaotalk['modify_txt'] = date('Y년 m월 d일',strtotime($config_kakaotalk['modify_date']));

			$this->config_kakaotalk = $config_kakaotalk;
		}

		return $this->config_kakaotalk;
	}

	// 서비스 신청 상태 추출
	public function get_servicecode($status){
		$_APPROVAL['U']	= '검수중';
		$_APPROVAL['R']	= '부결(반려)';
		$_APPROVAL['A']	= '승인완료';

		return $_APPROVAL[$status];
	}

	/**
	* 카카오 알림톡 접근토큰 체크
	* @getparam $request
	* @return string $result
	*/
	public function chkToken($accessToken, $destination){
		$shopSeq	= $this->config_system['shopSno'];
		$kkotInfo	= config_load('kakaotalk');

		if ($destination == $shopSeq){
			$encodeKey	= $shopSeq . date('Ymd') . $kkotInfo['authKey'];
			$encodeVal	= md5(hash('sha256', $encodeKey));
			$tokenArr	= explode('KKOT', $accessToken);
			if ($tokenArr[0] == $encodeVal) {
				$limitTime = base64_decode($tokenArr[1]);
				$validTime = date('His', strtotime('-5 minute'));
				echo $validTime . ' < ' . $limitTime . '<br/>';
				if ($validTime < $limitTime){
					return true;
				}
			}
		}

		return false;
	}

	// 발송시간제한 : 발송예약시간 설정
	function sendSMS_restriction($case){

		$config_sms_rest	= config_load('sms_restriction');	//SMS 발송시간제한
		if(strstr($case,"_write") || strstr($case,"_reply")){
		## 게시판 발송시간 제한(예약)
			if(strstr($case,"_write")){
				$sms_use_chk	= $config_sms_rest['board_toadmin'];
			}else{
				$sms_use_chk	= $config_sms_rest['board_touser'];
			}
			$config_time_s	= $config_sms_rest['board_time_s'];
			$config_time_e	= $config_sms_rest['board_time_e'];
			$reserve_time	= $config_sms_rest['board_reserve_time'];

		}else{
		## 일반 발송시간 제한(예약)
			$config_time_s	= $config_sms_rest['config_time_s'];
			$config_time_e	= $config_sms_rest['config_time_e'];
			$reserve_time	= $config_sms_rest['reserve_time'];
			$sms_use_chk	= $config_sms_rest[$case];

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
				$sms_reserve = date("Y-m-d H:i:s",$rest_etime_tmp);
			}
		}
		return $sms_reserve;
	}

	/* ##### 공용 함수 :: END ###### */

	/**
	 * 사용중인 템플릿 코드 리스트
	 * 
	 * 승인거절되면 기본템플릿으로 발송되기 때문에, 승인거절된 템플릿은 코드는 기본코드로 노출시킨다.
	 */
	public function availableTemplateList($templateList)	
	{
		$templateCount = count($templateList);

		for ($i = 0; $i < $templateCount; $i++) {
			/**
			 * "승인" 상태 템플릿이 아니면, 기본 템플릿을 노출 시킨다. 
			 * "승인" 값 : A
			 * 
			 * ex) 등록, 검수중, 반려, 사용요청 상태일때 노출되면 안된다
			 */
			if ($templateList[$i]['approval'] !== 'A') {
				$templateList[$i]['kkoBizCode'] = $templateList[$i]['base_kkoBizCode'];
			}
		}

		return $templateList;
	}

}
?>
