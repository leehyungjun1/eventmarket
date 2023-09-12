<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
// Composer load
require_once(ROOTPATH.'vendor/autoload.php');

// RESTful API
use chriskacerguis\RestServer\RestController;
use App\Libraries\Auth\AuthToken;

/**
 * JWT 인증
 *
 * 2019-11-21
 * @author Sunha Ryu
 */
class Auth extends RestController {

	/**
	 * 인증 토큰 발급
	 * @api
	 * @param clientId : 클라이언트 아이디
	 * @param secretKey : 보안 키
	 * @return accessToken : 인증 토큰
	 * @return refreshToken : 갱신 토큰
	 */
	public function create_post()
	{
		foreach(array('providerSeq','managerSeq','secretKey') as $param) {
			${$param} = $this->post($param);
		}

		if(empty($providerSeq) || empty($managerSeq) || empty($secretKey)) {
			$this->response(array(
				'success' => false,
				'code' => "auth_error",
				'message' => "필수 요청값이 누락되었습니다.",
			), parent::HTTP_UNAUTHORIZED);
		}

		$this->load->model('providermodel');
		$this->load->model('managermodel');
		$provider = $this->providermodel->get_provider_one($providerSeq);
		$manager = $this->managermodel->get_manager($managerSeq);

		if(empty($provider) || empty($manager)) {  // 입점사정보가 없거나 관리자 정보가 없는 경우
			$this->response(array(
				'success' => false,
				'code' => "not_manager",
				'message' => "현재 등록된 관리자가 아닙니다.",
			), parent::HTTP_UNAUTHORIZED);
		}

		$this->load->model('jwtmodel');
		$this->load->helper('broadcast');

		$certData = isSecretKeyCorrect($secretKey);

		if(empty($certData)) {// secretkey 가 일치 하지 않은 경우
			$this->response(array(
				'success' => false,
				'code' => "not_match",
				'message' => "요청에 제공된 인증 자격 증명이 유효하지 않습니다.",
			), parent::HTTP_UNAUTHORIZED);
		}

		if(isBroadcastUse() !== true) {// 서비스 신청이 안된 상태
			$this->response(array(
				'success' => false,
				'code' => "not_use",
				'message' => "라이브 서비스 신청을 하지 않은 도메인입니다.",
			), parent::HTTP_UNAUTHORIZED);
		}

		$payload = array(
			'provider_seq' => $providerSeq,
			'manager_seq' => $managerSeq,
			'grant_type' => 'app',
		);
		$token = $this->jwtmodel->createToken($payload);

		$response = array(
			'success'   =>      true,
			'data'      =>      array('accessToken' => $token),
		);
		$this->response($response);
	}

	/**
	 * 공통 인증 토큰 발급
	 * @api
	 * @param storekey : 상점 고유키
	 * @return accessToken : 인증 토큰
	 * @return refreshToken : 갱신 토큰
	 */
	public function token_post()
	{
		$this->load->helper('basic');

		$secret = $this->head("X-Firstmall-Secret");
		$timestamp = $this->head("X-Firstmall-Timestamp");

		$certData = isSecretCorrect($secret, $timestamp);

		if($certData === false) {// secretkey 가 일치 하지 않은 경우
			$this->response(array(
				'success' => false,
				'code' => "not_match",
				'message' => "요청에 제공된 인증 자격 증명이 유효하지 않습니다.",
			), parent::HTTP_UNAUTHORIZED);
		}

		$this->load->model('jwtmodel');

		$token = $this->jwtmodel->createToken();

		$this->response( array(
			'success'   =>      true,
			'data'      =>      array('accessToken' => $token),
		));
	}

	/**
	 * 개발 및 QA 용 로그인
	 */
	public function login_post() {

		foreach(array('manager_id','mpasswd') as $param) {
			${$param} = $this->post($param);
		}

		if(empty($manager_id) || empty($mpasswd)) {
			$this->response(array(
				'success' => false,
				'code' => "auth_error",
				'message' => "필수 요청값이 누락되었습니다.",
			), parent::HTTP_UNAUTHORIZED);
		}

		$this->load->model("loginmodel");
		$data = $this->loginmodel->getAdminData($manager_id, $mpasswd);

		if(!$data){
			$this->response(array(
				'success' => false,
				'code' => "not_manager",
				'message' => "일치하는 관리자 정보가 없습니다.",
			), parent::HTTP_UNAUTHORIZED);
		}

		$this->load->model('authmodel');
		$this->load->helper('basic');
		$cfg_system	= ($this->config_system) ? $this->config_system : config_load('system');

		$wheres['shopSno']		= $cfg_system['shopSno'];
		$wheres['manager_seq']	= $data['manager_seq'];
		$wheres['codecd']		= 'manager_yn';
		$orderbys['idx'] 		= 'asc';
		$query	= $this->authmodel->select('*',$wheres,$orderbys);
		$manager_auth = $query->row_array();

		if(!$manager_auth){
			$this->response(array(
				'success' => false,
				'code' => "not_manager",
				'message' => "일치하는 관리자 정보가 없습니다.",
			), parent::HTTP_UNAUTHORIZED);
		}

		$response = array(
			'success'   =>      true,
			'data'      =>      array('managerSeq' => $data['manager_seq'],
									'managerYn' => $manager_auth['value'],
									'managerName' => $data['mname']
									),
		);
		$this->response($response);
	}

	/**
	* broadcast 사용하는지 체크
	* api/auth/broadcast 로 변경
	* 정식오픈 버전은 expire_date 변수여부로 확인 가능
	*/
	public function broadcast_get() {
		$this->load->helper('basic');
		$this->load->helper('broadcast');
		$use = isBroadcastUse();
		$conf = getBroadcastConf();

		$this->load->library('ssllib');
		$sslEnv = $this->ssllib->getSslEnvironment();
		$ssl = true;
		if(empty($sslEnv['data'])){
			$ssl = false;
		}

		// 만료일 체크 (아래 2가지 목적에 쓰임)
		// 1.정식버전 여부 2. 서비스 만료여부
		$success = $conf['expire_date'] > date('Y-m-d');

		$res = array(
			'success' => $success,
			'use' => $use,
			'ssl' => $ssl,
			'service_code' => $this->config_system['service']['code'],
			'expire_date' => $conf['expire_date'],
			'version' => $conf['version']
		);
		return $this->response($res);
	}

	/**
     * AccessToken을 가졍
     * Gabia C&S Auth 에서 토큰 발급
     */
    public function authToken_post()
    {
		try {
			$this->load->helper('basic');

			$auth = new AuthToken();

			$response = $auth->getAuthToken();
			
			if ($response['success'] == false) {
				echo json_encode($response);
				return;
			}

			$accessToken = isset($response['data']->token) ?$response['data']->token : null;
	
		} catch (\Exception $e) {
			//
		}

		echo $accessToken;

		return;
    }

}