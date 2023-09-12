<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class naverpaymentlib
{
	protected $apiUrl;
	protected $detailUrl;
	protected $timeout;
	protected $naverpaymentCfg;

	public function __construct()
	{
		$this->CI = &get_instance();
		$this->CI->load->helper('readurl');
		$this->CI->load->helper('common');

		$this->naverpaymentCfg = config_load('naverpayment');

		if (!$this->api_url) {
			$this->setApi();
		}
	}

	// 네이버페이 간편결제 연동 호출
	public function callApi($type, $params, $timeout = 10)
	{
		$this->timeout = $timeout;

		$call_info = $this->detailUrl[$type];
		$call_info['call_url'] = $this->api_url . $this->getUri($call_info);
		$call_info['header'] = $this->getHeader();

		$response = $this->_call($call_info, $params);

		return (array)$response;
	}

	// API 도메인 및 API 명 정의
	protected function setApi()
	{
		$api_url['y'] = 'https://apis.naver.com';
		$api_url['test'] = 'https://dev.apis.naver.com';
		
		$this->api_url  = $api_url[$this->naverpaymentCfg['use']];
		$this->detailUrl = [
			'approve' 	=> ['method'=>'post', 'type'=>'urlencoded', 'uri'=>'/v2.2/apply/payment'],		// 결제승인
			'cancel' 	=> ['method'=>'post', 'type'=>'urlencoded',	'uri'=>'/v1/cancel',],				// 결제취소
			'list' 		=> ['method'=>'post', 'type'=>'json',		'uri'=>'/v2.2/list/history'],		// 결제내역조회
			'confirm' 	=> ['method'=>'post', 'type'=>'urlencoded',	'uri'=>'/v1/purchase-confirm'],		// 거래완료
			'naverpoint'=> ['method'=>'post', 'type'=>'urlencoded',	'uri'=>'/v1/naverpoint-save'],		// 포인트 적립 요청
			'receipt' 	=> ['method'=>'get',  'type'=>'urlencoded',	'uri'=>'/v1/receipt/cash-amount'],	// 현금영수증 발급대상 금액조회
		];
	}

	// 네이버페이 간편결제 백엔드 API 서버 URL 형식으로 리턴
	protected function getUri($call_info)
	{
		if ($call_info) {
			$partner_id = $this->naverpaymentCfg['partner_id'];
			// https://{API 도메인} / {파트너 ID} / naverpay / payments / {API 버전} / {API명}
			return "/{$partner_id}/naverpay/payments" . $call_info['uri'];
		} else {
			$this->setApi();
			$this->getUri($call_info);
		}
	}

	// header 생성 (oAuth 2.0 인증을 위해 클라이언트 ID와 클라이언트 시크릿을 전달)
	protected function getHeader()
	{
		$header['X-Naver-Client-Id'] = $this->naverpaymentCfg['client_id'];
		$header['X-Naver-Client-Secret'] = $this->naverpaymentCfg['client_secret'];

		return $header;
	}

	protected function _call($call_info, $params)
	{
		$url = $call_info['call_url'];
		$method = $call_info['method'];
		$headers = $call_info['header'];

		$curl = curl_init();
		switch (strtoupper($method)) {
			case 'POST':
				curl_setopt($curl, CURLOPT_POST, 1);
				if ($params && $call_info['type'] == 'urlencoded') {
					curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($params));
				} else {
					curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
				}

				break;
			case 'PUT':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
				if ($params) {
					curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
				}

				break;
			default:
				if ($params) {
					$url = sprintf('%s?%s', $url, http_build_query($params));
				}
		}

		// OPTIONS:
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSLVERSION, 1);
		curl_setopt($curl, CURLOPT_URL, $url);

		if($headers) {
			foreach($headers as $key => $val){
				$send_header[] = $key . ':' . $val;
			}
			switch($call_info['type']) {
				case 'json':
					$send_header[] = "Content-Type: application/json";
					break;
				case 'urlencoded':
					$send_header[] = "Content-Type: application/x-www-form-urlencoded";
					break;
			}
			curl_setopt ($curl, CURLOPT_HTTPHEADER, $send_header);
		}

		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

		/* 필요시 아래 주석 해제 후 curl 요청 디버깅 옵션 설정 */
		// curl_setopt($curl, CURLOPT_VERBOSE, true);
		// $verboseStream = fopen('php://memory', 'w+');
		// curl_setopt($curl, CURLOPT_STDERR, $verboseStream);

		$result = curl_exec($curl);
		$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		// rewind($verboseStream);
		// $debug['debug'] = stream_get_contents($verboseStream);

		if (in_array($httpCode, [200,201])) {
			$response = json_decode($result, true);
		} else {
			$errCode['error'] = true;
			$errCode['httpCode'] = $httpCode;
			$errCode['result'] = $result;
			$errCode['info'] = curl_getinfo($curl);
		}

		if (!$result) {
			die('Connection Failure');
		}
		curl_close($curl);

		$debug['url']		= $url;
		$debug['body']		= $params;
		$debug['method']	= $method;
		$debug['headers']	= $send_header;
		$debug['response']	= $response;
		$debug['errCode']	= $errCode;

		writeCsLog($debug, "api" , "naverpayment", "hour");

		if (!empty($errCode)) {
			return $errCode;
		}

		return $response;
	}

	// 네이버페이 결제형에서 지원하는 카드사 매칭
	public function getCardName($cardCode)
	{
		if ( !$cardCode ) {
			return null;
		}

		$cardInfo = [
			'C0' => '신한', 'C1' => '비씨', 'C2' => '광주', 'C3' => 'KB국민',
			'C4' => 'NH', 'C5' => '롯데', 'C6' => '산업', 'C7' => '삼성',
			'C8' => '수협', 'C9' => '씨티', 'CA' => '외환', 'CB' => '우리',
			'CC' => '전북', 'CD' => '제주', 'CF' => '하나-외환', 'CH' => '신한'
		];

		$cardName = $cardInfo[$cardCode];

		return $cardName ?: '기타';
	}

	// 네이버페이 결제형에서 지원하는 은행 매칭
	public function getBankName($bankCode)
	{
		if ( !$bankCode ) {
			return null;
		}

		$bankInfo = [
			'002' => '산업은행', '003' => '기업은행', '004' => '국민은행', '005' => '외환은행',
			'007' => '수협', '011' => '농협', '012' => '지역농·축협', '020' => '우리은행',
			'023' => 'SC제일은행', '027' => '씨티은행', '031' => '대구은행', '032' => '부산은행',
			'034' => '광주은행', '035' => '제주은행', '037' => '전북은행', '039' => '경남은행',
			'045' => '새마을금고', '048' => '신협', '050' => '저축은행', '071' => '우체국',
			'081' => '하나은행', '088' => '신한은행', '089' => '케이뱅크', '090' => '카카오뱅크',
			'092' => '토스뱅크', '102' => '대신저축은행', '103' => '에스비아이저축은행', '104' => '에이치케이저축은행',
			'105' => '웰컴저축은행', '106' => '신한저축은행', '209' => '유안타증권', '218' => 'KB증권',
			'221' => '상상인증권', '222' => '한양증권', '223' => '리딩투자증권', '224' => 'BNK투자증권',
			'225' => 'IBK투자증권', '227' => '다올투자증권', '230' => '미래에셋', '238' => '미래에셋',
			'240' => '삼성증권', '243' => '한국투자증권', '247' => 'NH투자증권', '261' => '교보증권',
			'262' => '하이투자증권', '263' => '현대차증권', '264' => '키움증권', '265' => '이베스트투자증권',
			'266' => 'SK증권', '267' => '대신증권', '269' => '한화증권', '270' => '하나금융투자',
			'278' => '신한금융투자', '279' => 'DB금융투자', '280' => '유진투자증권', '287' => '메리츠증권',
			'288' => '카카오페이증권', '290' => '부국증권', '291' => '신영증권', '292' => '케이프투자증권',
			'293' => '한국증권금융', '294' => '한국포스증권', '295' => '우리종합금융'
		];

		$bankName = $bankInfo[$bankCode];

		return $bankName ?: '기타';
	}

	// 네이버페이 에러응답에 따른 문구 리턴
	public function getMessage($resultCode, $resultMessage)
	{
		if ($resultMessage) {
			return $resultMessage;
		}

		switch($resultCode) {
			case 'UserCancel':
				$message = '결제를 취소하셨습니다. 주문 내용 확인 후 다시 결제해주세요.';
				break;
			case 'OwnerAuthFail':
				$message = '타인 명의 카드는 결제가 불가능합니다. 회원 본인 명의의 카드로 결제해주세요.';
				break;
			case 'paymentTimeExpire':
				$message = '결제 가능한 시간이 지났습니다. 주문 내용 확인 후 다시 결제해주세요.';
				break;
			case 'minAmountUnder':
				$message = '100원 미만 상품은 네이버페이로 결제할 수 없습니다.\n다른 결제수단을 선택해 주시기 바랍니다.';
				break;
			case 'CancelDeadlineExpired':
				$message = '취소 기한이 만료되어 취소가 불가합니다. 가맹점의 자체 환불이 필요합니다.';
				break;
			default:
				$message = $resultCode;
		}

		return $message ?: getAlert('os217');
	}
}
