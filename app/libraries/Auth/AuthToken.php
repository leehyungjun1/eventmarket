<?php
namespace App\Libraries\Auth;

final class AuthToken
{
	private $baseUrl = 'https://auth.gabiacns.com/';
	private $auth;

	public function __construct()
	{
		$this->CI = &get_instance();
		$this->CI->load->helper('readurl');
		$this->setAuth();
	}

	// 인증 허가 및 Access Token 발급 요청
	public function getAuthToken($request = 'geditor')
	{
		$url = $this->baseUrl . 'api/auth/token';

		$headers = $this->setHeader();

		$requestData = $this->setData($this->auth);

		$result = readurl($url, $requestData, false, 7, $headers, true, true, 'POST');

		$log = ['method' => 'POST',
				'function' => 'getAuthToken',
				'response' => $result,
				'request' => $requestData,
				'fileName' => $request,
				];

		$response = $this->checkToken($log);

		return $response;
	}

	// 토큰 정보 확인
	public function getAuthMe($accessToken, $request = 'geditor')
	{
		$url = $this->baseUrl . 'api/auth/me';

		$headerData['Authorization'] = $accessToken;

		$headers = $this->setHeader($headerData);

		$result = readurl($url, '', false, 7, $headers, false, true);

		if (empty($this->auth) == false) {
			$isClient = ($this->auth['client_id'] != json_decode($result)->client->client_id) ? true : false;
		}

		$log = ['method' => 'GET',
				'function' => 'getAuthMe',
				'response' => $result,
				'request' => $headers,
				'fileName' => $request,
				'isClient' => $isClient,
				];

		$response = $this->checkToken($log);

		return $response;
	}

	// 토큰값이 정상적인지 확인
	public function checkToken($log)
	{
		$result = $log['response'];

		if (isset($result['httpCode']) && !in_array($result['httpCode'], [200, 201])) {
			$response['httpCode'] = $result['httpCode'];
			$response['message'] = json_decode($result['result'])->message;
			
			$this->writeLog($log);

			return $this->errorResult($response);
		} else if (isset($log['isClient']) && $log['isClient'] === true) { 
			// 클라이언트 에러일 경우
			$response['httpCode'] = 403;
			$response['message'] = '클라이언트 아이디를 확인해주세요.';

			$this->writeLog($log);

			return $this->errorResult($response);
		}

		return $this->successResult($result);
	}

	private function setAuth()
	{
		if (empty($this->auth)) {
			$this->auth = config_load('auth');
		}

		return $this->auth;
	}

	private function setHeader($headerData = [])
	{
		$contentType = 'application/x-www-form-urlencoded';

		$header['accept'] = 'application/json';
		$header['Content-Type'] = ($headerData['contentType']) ?? $contentType;

		if ($headerData['Authorization']) {
			$header['Authorization'] = $headerData['Authorization'];
		}

		return $header;
	}

	private function setData($data = [])
	{
		$requestData = [];

		foreach ($data as $key => $val) {
			$requestData[$key] = $val;
		}

		return $requestData;
	}

	private function writeLog($logData)
	{
		$log = [
			'method' => $logData['method'],
			'function' => $logData['function'],
			'request' => $logData['request'],
			'response' => $logData['result'],
			'fileName' => $logData['fileName'],
		];

		writeCsLog($log, 'auth', $log['fileName'], 'hour');
	}

	// 성공 리턴
	private function successResult($data = [])
	{
		return [
			'success' => true,
			'data' => json_decode($data),
		];
	}

	// 실패 리턴
	private function errorResult($response = [])
	{
		return [
			'success' => false,
			'httpCode' => $response['httpCode'],
			'message' => $response['message']
		];
	}
}
