<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}
require_once APPPATH . 'controllers/base/api_base' . EXT;

use App\Libraries\Auth\AuthToken;

/**
 * 제디터 연동 관련 API
 * @api
 */
class geditor extends api_base
{
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * 제디터 인증키 콜백 API
	 */
	public function setGeditor_post()
	{
		try {
			$this->load->helper('common');
			$head = $this->head();
			$params = $this->input->post();

			// 토큰 체크
			$auth = new AuthToken();

			$result = $auth->getAuthMe($head['Authorization']);

			if ($result['success'] == false) {
				throw new Exception($result['message'], $result['httpCode']);
			}

			$this->load->library('geditorlib');

			$result = $this->geditorlib->setting($params);
		} catch (\Exception $e) {
			$message = 'API 통신중 에러가 발생했습니다.';
			if ($e->getMessage()) {
				$message = $e->getMessage();
			}
			writeCsLog($message, 'api', 'geditor', 'hour');
			return 'API Error : ' . $message . ' Error Code : ' . $e->getCode();
		}
		echo json_encode($result);

		return;
	}
}
