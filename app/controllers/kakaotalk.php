<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class kakaotalk extends front_base {

	function __construct() {
		parent::__construct();
		$this->load->model('kakaotalkmodel');

		$this->AES_KEY	= '9fe57e5f992a3691';
	}

	public function index()
	{
		echo '잘못된 요청';
	}

	// 서비스 상태 동기화
	public function service_status(){

		// 고유번호 일치시에만 작동.. (솔루션 이전 시 다시 가입 작업 및 이전 작업)
		if ($this->config_system['shopSno'] == $_POST['shopno']){
			$params['authKey']		= $_POST['authKey'];
			$params['status']		= $_POST['status'];
			$params['modify_date']	= date('Y-m-d H:i:s');
			$this->kakaotalkmodel->set_kakaoConfig($params);

			if ($this->kakaotalkmodel->config_kakaotalk['modify_date'] == $params['modify_date']){
				echo 'OK';
			}
		}else{
			echo 'NO';
		}
	}

	// 템플릿 상태 동기화
	public function kakaoTplsync(){

		$shopno			= $_POST['shopno'];
		$kkoBizCode		= json_decode($_POST['kkoBizCode'],true);
		$res_msg		= 'NO';

		// 고유번호 일치시에만 작동.. (미 작동 시 템플릿 다음시간에 다시 동기화)
		if ($this->config_system['shopSno'] == $shopno || $_GET['debug']){
			// 메세지 코드 동기화
			$this->kakaotalkmodel->set_template_default_code();
			if (count($kkoBizCode) > 0 || $_GET['debug']){
				// 템플릿 동기화
				$cnt = $this->kakaotalkmodel->set_template_sync();
				if ($cnt){
					$res_msg = 'OK';
				}else{
					$res_msg = 'NO AFFECT';
				}
			}else{
				$res_msg = 'NO CODE';
			}
		}else{
			$res_msg = 'NO AUTH';
		}
		echo $res_msg;
	}

	// SMS 발송 token 생성
	public function get_sms_token(){
		// 허용 IP 제한
		self::check_ip();

		$second		= "100"; // Token 유효시간

		$shopSno	= $_POST['shopSno'];
		$send_type	= $_POST['send_type'];

		$now		= strtotime($second . ' seconds');
		$tokenStr	= $now . '||' . $shopSno . '||' . $send_type;
		$AEStoken	= AESEncode($this->AES_KEY, $tokenStr);

		echo $AEStoken;
	}

	// SMS 발송 요청
	public function sms_send(){
		// 허용 IP 제한
		self::check_ip();

		$get_token		= $_POST['token'];
		$get_send_type	= $_POST['send_type'];
		$get_smsData	= $_POST['smsData'];
		$smsData		= json_decode($get_smsData,true);
		$respons		= self::check_token($get_token, $get_send_type);

		/* 알림톡 발송실패 시 SMS발송 처리(단, SMS발송 체크 되어 있을 때) */
		$this->config_sms = config_load('sms');
		$this->config_sms_personal = config_load('sms_personal');

		require_once ROOTPATH."/app/libraries/sms.class.php";
		$auth = config_load('master');

		$sms_id			= $this->config_system['service']['sms_id'];
		$sms_api_key	= $auth['sms_auth'];

		$gabiaSmsApi	= new gabiaSmsApi($sms_id,$sms_api_key);
		// 카카오 알림톡 대체 SMS 발송은 관리자 발송하지 않음.
		$gabiaSmsApi->set_by_kakao(true);

		$keys	= array_keys($smsData);

		foreach($keys as $case){
			
			$_smsData		= array();
			$smsSendRequest = false;

			$config_sms = $this->config_sms;
			// 리마인드 sms 는 sms_personal 사용
			$case_tmp = explode('_', $case);
			if ($case_tmp[0] == 'personal') {
				$config_sms = $this->config_sms_personal;
			}

			if ($config_sms[$case . '_user_yn'] == 'Y' || $config_sms[$case . '_admin_yn'] == 'Y') {
				$smsSendRequest = true;
			}

			if	( !$respons && $smsSendRequest) {

				$_smsData[$case] = $smsData[$case];

				$result['msg'] = $gabiaSmsApi->sendSMS($_smsData);
				$result['code'] = $gabiaSmsApi->getResultCode();

				if ($result['code'] == '0000')	$respons = 'OK';
				else							$respons = 'NO';
			}

		}

		echo $respons;
	}

	// EMAIL 발송 요청
	public function email_send(){
		// 허용 IP 제한
		self::check_ip();

		$get_token		= $_POST['token'];
		$get_send_type	= $_POST['send_type'];
		$get_emailData	= $_POST['emailData'];
		$emailData		= json_decode($get_emailData,true);
		$respons		= self::check_token($get_token, $get_send_type);

		if	( !$respons ) {
			sendMail($emailData['to_email'], $emailData['case'], $emailData['userid'] , $emailData);
			$respons = 'OK';
		}

		echo $respons;
	}

	public function check_token($get_token, $get_send_type){

		$token			= AESDecode($this->AES_KEY, $get_token);
		$token_arr		= explode('||', $token);

		$crt_date		= $token_arr[0];
		$shopSno		= $token_arr[1];
		$send_type		= $token_arr[2];

		if($crt_date && $shopSno && $send_type){
			$time		= strtotime(date('YmdHis'));
			// 유효 시간 && 타입 및 샵번호 인증
			if ( ($time > $crt_date) || ($get_send_type != $send_type) || ($this->config_system['shopSno'] != $shopSno) )
				$respons = 'NO AUTH';
		}else{
			$respons	= 'NO PARAM';
		}

		return $respons;
	}


	public function check_ip(){
		$allowIp = array('121.78.197.233', '121.78.197.237', '121.78.197.230','139.150.74.191','10.9.68.38');

		if	( !in_array($_SERVER['REMOTE_ADDR'], $allowIp) && !developIpCheck()) {
			echo 'NO IP';
			exit;
		}
		return;
	}
}

