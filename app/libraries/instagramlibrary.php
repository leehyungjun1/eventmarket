<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

/**
 * 인스타그램 API
 *
 * @package    Firstmall
 * @author     WooSuk Choi <cws@gabiacns.com>
 * @copyright  2022 Gabia C&S
 */
class instagramlibrary
{
	// 인스타그램 API URL
	private $api_url = [
		'refresh' => 'https://graph.instagram.com/refresh_access_token',
		'media' => 'https://graph.instagram.com/me/media',
	];

	private $_config;

	public function __construct()
	{
		$this->CI = &get_instance();

		// 인스타그램 연동 정보
		$this->_config = $this->getConfig();
	}

	/**
	 * 인스타그램 연동 설정 저장
	 * @param array $params
	 */
	public function setConfig($params = [])
	{
		config_save('instagram', $params);

		$this->_config = $this->getConfig();
	}

	/**
	 * 인스타그램 연동 설정 정보
	 * @return array
	 */
	public function getConfig()
	{
		$items = config_load('instagram');

		return $items;
	}

	/**
	 * 인스타그램 피드 생성
	 */
	public function createFeed()
	{
		$this->CI->load->model('instagramfeedmodel');

		// 인스타그램 토큰 유효기간 체크 후 갱신처리
		if ($this->checkTokenExpireOver() === true) {
			$token = $this->getRefreshToken();
			$this->setRefreshToken($token);
		}

		// 인스타그램 피드 가져오기
		$feedList = $this->getUserMedia();

		if (is_array($feedList)) {
			// 인스타그램 피드 삭제
			$this->CI->instagramfeedmodel->deleteFeed($this->_config['username']);
			$data = $this->getFeedParameter($feedList);

			// 인스타그램 피드 저장
			$this->setFeedData($data);
		}

		// 인스타그램 피드 업데이트 시간 저장
		$params['feed_update_time'] = date('Y-m-d H:i:s');
		$this->setConfig($params);
	}

	/**
	 * 새로 갱신된 토큰 저장
	 * @param array $data
	 * @return boolean
	 */
	public function setRefreshToken($data)
	{
		if (empty($data['access_token']) || empty($data['expires_in'])) {
			return false;
		}

		$params['token'] = $data['access_token'];
		$params['expires'] = time() + $data['expires_in'];

		$this->setConfig($params);

		return true;
	}

	/**
	 * 인스타그램 60일 토큰 갱신 API
	 * @return array
	 */
	public function getRefreshToken()
	{
		$params['grant_type'] = 'ig_refresh_token';
		$params['access_token'] = $this->_config['token'];

		$result = $this->callAPI('GET', $this->api_url['refresh'], $params);

		return $result;
	}

	/**
	 * 인스타그램 피드 가져오기 API
	 * @return array
	 */
	public function getUserMedia()
	{
		$params['fields'] = 'id,media_type,media_url,permalink,thumbnail_url,username,caption';
		$params['access_token'] = $this->_config['token'];
		$params['limit'] = 30;

		$result = $this->callAPI('GET', $this->api_url['media'], $params);

		return $result['data'];
	}

	/**
	 * 인스타그램 토큰 유효기간 지났는지 체크
	 * @return boolean
	 */
	public function checkTokenExpireOver()
	{
		$current_time = time(); // 현재 시간
		$expire_time = $this->_config['expires'] - (60 * 60 * 24 * 30); // 토큰 유효기간(30일)

		// 토큰이 30일 이상 지나면 토큰 갱신
		if ($current_time > $expire_time) {
			return true;
		}

		return false;
	}

	/**
	 * 인스타그램 피드 저장할 파라미터 생성
	 * @param array $feedList
	 * @return array
	 */
	public function getFeedParameter($feedList)
	{
		$sort_seq = 1; // 정렬 번호
		foreach ($feedList as $feed) {
			// 이미지 종류만 저장 (피드 가져오기 API에서 동영상도 넘어올 수 있음.)
			if (in_array($feed['media_type'], ['IMAGE', 'CAROUSEL_ALBUM']) === false) {
				continue;
			}

			// 피드 가져오기 API data와 fm_instagram_feed 테이블 필드 매칭
			$params['sort_seq'] = $sort_seq;
			$params['user_name'] = $this->_config['username'];
			$params['image_url'] = $feed['media_url'];
			$params['post_link'] = $feed['permalink'];
			$params['update_date'] = date('Y-m-d H:i:s');

			$datarow[] = $params;

			$sort_seq++;

			// 최근 게시물 기준 최대 30개
			if ($sort_seq > 30) {
				break;
			}
		}

		return $datarow;
	}

	/**
	 * 인스타그램 피드 저장
	 * @param array $data
	 * @return boolean
	 */
	public function setFeedData($data)
	{
		$this->CI->load->model('instagramfeedmodel');

		if (isset($data)) {
			$result = $this->CI->instagramfeedmodel->insertBatchFeed($data);
		} else {
			return true;
		}

		return $result;
	}

	// 인스타그램 피드 자동 업데이트
	public function setFeedAuto()
	{
		// 비교할 날짜 변수 생성
		$currentTime = strtotime(date('Y-m-d H:i:s'));
		$updateTime = strtotime($this->_config['feed_update_time']);

		// 현재시간 - 피드 업데이트 시간
		$diff = $currentTime - $updateTime;

		// 인스타그램 피드가 업데이트 된지 1시간 이상이면 자동 업데이트
		if (1 <= ($diff / (60 * 60))) {
			$this->createFeed();
		}
	}

	/**
	 * API 요청 CURL 함수
	 * @param string $method
	 * @param string $url
	 * @param array $params
	 * @return API result
	 */
	protected function callAPI($method, $url, $params)
	{
		$curl = curl_init();
		switch ($method) {
			case 'POST':
				curl_setopt($curl, CURLOPT_POST, 1);
				if ($params) {
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
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
		]);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		// EXECUTE:
		$result = curl_exec($curl);
		if (!$result) {
			die('Connection Failure');
		}
		curl_close($curl);

		// 인스타그램 API 연동 결과 파일 로그
		$debug['uri_string'] = $this->CI->uri->uri_string;
		$debug['get'] = $this->CI->input->get();
		$debug['post'] = $this->CI->input->post();
		$debug['request'] = $params;
		$debug['response'] = $result;

		writeCsLog($debug, 'instagram', 'api', 'hour');

		return json_decode($result, true);
	}
}
