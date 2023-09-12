<?php

namespace App\Libraries\AdditionService\kakaosync;
/**
 * 쇼핑몰 정보 조회하기 데이터 가공
 * 
 * @package    Firstmall
 * @author     WooSuk Choi <cws@gabiacns.com>
 * @copyright  2022 Gabia C&S
 */

class ClientKakaosyncShopInfo
{
	protected $shopInfo;
	protected $protocol;

	public function __construct($shopInfo = null)
	{
		$this->ci = & get_instance();

		// 보안서버 사용중인 도메인 정보가 있으면 https로 전달
		if( $this->ci->config_system['ssl_multi_domain'] ) {
			$this->protocol = 'https://';
		} else {
			$this->protocol = 'http://';
		}

		$this->shopInfo = $shopInfo ?: $this->setShopInfo();
	}

	public function getShopInfo() {
		return $this->shopInfo;
	}

	public function setShopInfo() {
		return [
			'appKey' => $this->getAppKey(),
			'business' => $this->getBusiness(),
			'siteData' => $this->getSiteData(),
			'sync' => $this->getSync()
		];
	}

	// 쇼핑몰 REST API 키
	public function getAppKey()
	{
		$cfg_sns = config_load('snssocial');
		return $cfg_sns['status_ks'] == 1 ? $cfg_sns['rest_key_k'] : '';
	}

	// 쇼핑몰 기본 정보
	private function getSiteData()
	{
		$this->ci->load->model('adminenvmodel');
		$cfg = config_load('system');

		$get_params = ['shopSno' => $cfg['shopSno']];
		$query = $this->ci->adminenvmodel->get($get_params);
		$env_data = $query->row_array();

		$this->domain = $env_data['domain'];
		$logo_image = $this->domain . '/data/skin/' . $this->ci->config_system['skin'] . '/images/design/logo.png';

		$site_data = [
			'name' => $env_data['admin_env_name'] ? $env_data['admin_env_name'] : "",
			'url' => $this->domain ? $this->protocol . $this->domain : "",
			'image' => $logo_image ? $this->protocol . $logo_image : "",
		];
		return $site_data;
	}

	// 쇼핑몰 사업자 정보
	private function getBusiness()
	{
		$cfg_basic = config_load('basic');

		$business_data = [
			'identificationNumber' => $cfg_basic['businessLicense'],
			'name' => $cfg_basic['companyName'],
			'representativeName' => $cfg_basic['ceo'],
			'category' => $cfg_basic['businessConditions'] ? $cfg_basic['businessConditions'] : "",
			'categoryItem' => $cfg_basic['businessLine'] ? $cfg_basic['businessLine'] : "",
			'address' => [
				'zipcode' => $cfg_basic['companyZipcode'] ? $cfg_basic['companyZipcode'] : "",
				'baseAddress' => $cfg_basic['companyAddress_street'] ? $cfg_basic['companyAddress_street'] : $cfg_basic['companyAddress'],
				'detailAddress' => $cfg_basic['companyAddressDetail'] ? $cfg_basic['companyAddressDetail'] : ""
			]
		];

		return $business_data;
	}


	// 카카오싱크 설정 정보
	private function getSync()
	{
		$sync_data = [
			'items' => $this->joinform_matching(config_load('joinform')),
			'privacyPolicyUrl' => $this->protocol . $this->domain . '/service/privacy',
			'terms' => $this->terms(),
			'url' => [
				$this->protocol . $this->domain,
				$this->protocol . 'm.' . $this->domain,
			],
			'redirectUri' => [
				$this->protocol . $this->domain . '/sns_process/kakao_callback',
				$this->protocol . 'm.' . $this->domain . '/sns_process/kakao_callback',
			],
		];
		return $sync_data;
	}

	/**
	 * 회원가입 동의항목 매칭
	 *  Sync > items[]

	 *	AGE: 연령대 - 매칭필드없음
	 *	ADDRESS: 배송지 정보
	 *	BIRTH_DAY: 생일
	 *	BIRTH_YEAR: 출생연도
	 *	EMAIL: 이메일
	 *	GENDER: 성별
	 *	NAME: 이름
	 *	PHONE_NUMBER: 전화번호
	 *	PROFILE: 닉네임/프로필 사진 - 동의항목 분리 전
	 *	PROFILE_NICKNAME: 닉네임 / PROFILE_IMAGE: 프로필 사진 - 동의항목 분리
	 */
	protected function joinform_matching($cfg_joinform)
	{
		$cfg_joinform = config_load('joinform');

		$joinform_type_arr = [
			'address'	=> 'ADDRESS',
			'birthday'	=> ['BIRTH_DAY', 'BIRTH_YEAR'],
			'email'		=> 'EMAIL',
			'sex'		=> 'GENDER',
			'user_name'	=> 'NAME',
			'cellphone'	=> 'PHONE_NUMBER',
			'nickname'	=> ['PROFILE', 'PROFILE_NICKNAME']
		];

		foreach ($joinform_type_arr as $type => $fields) {
			if ($cfg_joinform[$type . '_use'] == 'Y') {
				if (is_array($fields)) {
					foreach ($fields as $field) {
						$match[] = [
							'type' => $field,
							'required' => $cfg_joinform[$type . '_required'] == 'Y' ? true : false,
						];
					}
				} else {
					$match[] = [
						'type' => $fields,
						'required' => $cfg_joinform[$type . '_required'] == 'Y' ? true : false,
					];
				}
			}
		}

		return $match;
	}

	/**
	 * 약관 목록
	 * Sync > terms[]
	 */
	protected function terms()
	{
		$cfg_joinform    = config_load('joinform');
		$hosting_prefix = 'gabiacns_';

		$terms_title = [
			'policy_agreement' => [
				'ko' => '쇼핑몰 이용약관',
				'en' => 'Shopping Mall Terms and Conditions'
			],
			'policy_joinform' => [
				'ko' => '개인정보 수집 및 이용',
				'en' => 'Collecting and using personal information'
			],
			'mailing_agreement' => [
				'ko' => '이메일 수신',
				'en' => 'Receive email'
			],
			'sms_agreement' => [
				'ko' => 'SMS 수신',
				'en' => 'Receive sms'
			],
			'user_age_check' => [
				'ko' => '만 14세 이상입니다',
				'en' => '14 years of age or older'
			],
		];

		foreach ($terms_title as $type => $data) {
			switch($type) {
				case 'policy_agreement':
					$url = $this->protocol . $this->domain . '/service/agreement';
					break;
				case 'policy_joinform':
					$url = $this->protocol . $this->domain . '/service/privacy';
					break;
				case 'mailing_agreement':
				case 'sms_agreement':
					$url = $this->protocol . $this->domain . '/service/marketing';
					break;
				default:
					$url = '';
					break;
			}

			if (in_array($type, ['policy_agreement', 'policy_joinform'])) {
				$required = true;
			}

			// 이메일 수신 약관은 Email 항목 사용시에만 전달
			if ($type == 'mailing_agreement' && $cfg_joinform['email_use'] != 'Y') {
				continue;
			}

			// SMS 수신 약관은 핸드폰 항목 사용시에만 전달
			if ($type == 'sms_agreement' && $cfg_joinform['cellphone_use'] != 'Y') {
				continue;
			}

			// 만 14세 이상 약관 조건에 따른 처리
			if ($type == 'user_age_check') {
				if ($cfg_joinform['kid_join_use'] == 'Y') {
					$required = false;
				} elseif ($cfg_joinform['kid_join_use'] == 'N') {
					$required = true;
				} else {
					continue;
				}
			}

			$term = [
				'title' => $data,
				'url' => $url,
				'tag' => ($type != 'user_age_check') ? $hosting_prefix . $type : $type,
				'required' => $required ? true : false
			];

			unset($url);
			unset($required);

			$result[] = $term;
		}

		return $result;
	}
}
