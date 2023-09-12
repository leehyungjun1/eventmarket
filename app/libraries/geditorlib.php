<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

use App\Libraries\Auth\AuthToken;

class geditorlib
{
	private $url = 'https://www.geditor.co.kr/';
	private $geditor;

	public function __construct()
	{
		$this->CI = &get_instance();
	}

	public function setting($params)
	{
		$result = $this->checkParams($params);

		$log = ['method' => 'POST',
				'function' => 'setGeditor',
				'request' => $params,
				'result' => $result,
		];

		$this->writeLog($log);

		if ($result['success'] === false) {
			return $result;
		}

		$this->setServiceGeditor($params);

		return $result;
	}

	// URL 가져오기
	public function getUrl($serviceKey,$deploymentId)
	{
		// editing 사용시 기본 URL
		$url = $this->url . 'linked';

		// 연동은 되었지만 발행번호가 미보유인 경우
		if (isset($serviceKey)) {
			$url .= '?auth=' . $serviceKey;
		}

		// 연동후 발행번호 보유 상태에서 에디터 링크 이동하는 경우
		if (isset($deploymentId)) {
			$url = $this->getEditorUrl($deploymentId);
		}

		return $url;
	}

	// 인증키 가져오기
	public function getServiceKey()
	{
		if (empty($this->geditor)) {
			$this->geditor = config_load('geditor');
		}

		return $this->geditor['service_key'];
	}

	// editing 최신 결과 조회
	public function getGeditorHtml($deploymentId)
	{
		// 인증처리
		$auth = new AuthToken();

		$response = $auth->getAuthToken();
	
		$accessToken = isset($response['data']->token) ? $response['data']->token : null;

		try {
			$url = $this->url . 'deploy/get/' . $deploymentId;

			$headers = $this->setHeader($accessToken);

			if ($response['success'] === false || $accessToken === null) {

				$log = ['method' => 'GET',
						'function' => 'getGeditorHtml',
						'request' => $headers,
						'result' => $response,
						];
	
				$this->writeLog($log);

				throw new Exception($response['message'], $response['httpCode']);
			}
	
			$response = readurl($url, '', false, 7, $headers, false, true);

		} catch (\Exception $e) {
			$message = 'API 통신중 에러가 발생했습니다.';
			if ($e->getMessage()) {
				$message = $e->getMessage();
			}

			throw new \Exception('API Error : ' . $message . ' Error Code : ' . $e->getCode());
		}

		return $response;
	}

	// 성공 리턴
	public function successResult($data = [])
	{
		return [
			'success' => true,
		];
	}

	// 실패 리턴
	public function errorResult($msg)
	{
		return [
			'success' => false,
			'msg' => $msg
		];
	}

	private function setHeader($accessToken)
	{
		$header['accept'] = 'application/json';

		if ($accessToken) {
			$header['Authorization'] = 'Bearer ' . $accessToken;
		}

		return $header;
	}

	private function writeLog($logData)
	{
		$log = [
			'method' => $logData['method'],
			'function' => $logData['function'],
			'request' => $logData['request'],
			'response' => $logData['result'],
		];

		writeCsLog($log, 'api', 'geditor', 'hour');
	}

	private function checkParams($params)
	{
		if (empty($params['service_key'])) {
			$msg = '정상적인 서비스 키를 응답받지 못했습니다.';

			return $this->errorResult($msg);
		}

		if (empty($params['client_id']) || empty($params['client_secret'])) {
			$msg = '정상적인 클라이언트 정보를 응답받지 못했습니다.';

			return $this->errorResult($msg);
		}

		return $this->successResult();
	}

	// 에디터 사용 내용 편집 URL 가져오기
	private function getEditorUrl($deploymentId)
	{
		$serviceKey = $this->getServiceKey();

		$url = $this->url . 'linked/' . $deploymentId . '?auth=' . $serviceKey;

		return $url;
	}

	// 인증키 설정 값 세팅
	private function setServiceGeditor($params)
	{
		config_save('geditor', ['service_key' => $params['service_key']]);
		config_save('auth', ['client_id' => $params['client_id'],
			'client_secret' => $params['client_secret']]);
	}
}
