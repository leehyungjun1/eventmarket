<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}
/**
 * 개인정보 마스킹 라이브러리
 * 2021-07-23
 */
class PrivateMasking
{
	public function __construct()
	{
		$this->CI = &get_instance();

		$this->chk_masking = false;

		$this->name_group = [
			'actor',
			'bank_depositor',
			'bkjukyo',
			'co_ceo',
			'depositor',
			'order_name',
			'order_user_name',
			'orderer_name',
			'person',
			'recipient_name',
			'recipient_user_name',
			'shipping_recipient_user_name',
			'user_name',
		];
		$this->phone_group = [
			'cellphone',
			'creceipt_number',
			'order_phone',
			'order_cellphone',
			'orderer_cellphone',
			'orderer_tel',
			'phone',
			'recipient_cellphone',
			'recipient_phone',
		];
		$this->address_group = [
			'address_detail',
			'recipient_address_detail',
			'sender_address_detail',
		];
		$this->email_group = [
			'email',
			'mbinfo_email',
			'order_email',
			'recipient_email',
		];
		$this->account_group = [
			'account',
			'bank_account',
		];
	}

	/**
	 * 개인정보 마스킹 처리
	 * @param params
	 * @param type
	 * @return result_params
	 */
	public function masking($params, $type = '')
	{
		$this->CI->load->model('authmodel');

		//개인정보 마스킹 표시 권한 체크
		$this->chk_masking = $this->CI->authmodel->manager_limit_act('private_masking');
		if ($this->chk_masking && count($params) > 0) {
			$result_params = $params;
			if ($type === 'order') {
				foreach ($params as $k => $item) {
					unset($masking_str);
					if (in_array($k, $this->name_group)) {
						$masking_str = $this->_getMaskedName($item);
					} elseif (in_array($k, $this->phone_group)) {
						$masking_str = $this->_getMaskedPhone($item);
					} elseif (in_array($k, $this->address_group)) {
						$masking_str = $this->_getMaskedAddress($item);
					} elseif (in_array($k, $this->email_group)) {
						$masking_str = $this->_getMaskedEmail($item);
					} elseif (in_array($k, $this->account_group)) {
						$masking_str = $this->_getMaskedAccount($item);
					} else {
						$masking_str = $item;
					}

					$result_params['private_masking'] = $this->chk_masking;
					$result_params[$k] = $masking_str;
				}

				// 추가 예외 처리
				$this->_getMaskedAddException($result_params);

			} elseif ($type === 'order_log') {
				foreach ($params as $k => $item) {
					if (!in_array($item['actor'], ['관리자', '시스템', '주문자']) && in_array($item['mtype'], ['u', 'n']) && !$item['add_info']) {
						$result_params[$k]['actor'] = $this->_getMaskedName($item['actor']);
					}
				}
			}

			return $result_params;
		} else {
			return $params;
		}
	}

	/**
	* 이름 마스킹 처리
	* @param name
	* @return maskedName
	*/
	private function _getMaskedName($name)
	{
		/*
		* 이름 첫 번째, 마지막 외 전체 마스킹
		* 예시) 가비아 / 퍼스트몰 / First => 가*아 / 퍼**몰 / F***t
		* */

		$regex = '/(?!^.?).(?!.{0}$)/u';
		$len = mb_strlen($name, 'utf-8');
		if ($len > '2') {
			if (preg_match($regex, $name, $matchResult)) {
				$maskedName = preg_replace($regex, '*', $name);
			} else {
				return $name;
			}
		} elseif ($len == '2') {
			$maskedName = mb_substr($name, 0, 1, 'utf-8').str_repeat('*', $len - 1);
		} else {
			return $name;
		}

		return $maskedName;
	}

	/**
	* 전화번호, 휴대폰 마스킹 처리
	* @param phoneNum
	* @return maskedPhoneNum
	*/
	private function _getMaskedPhone($phoneNum)
	{
		/*
		* 중간번호 전체 마스킹
		* 예시) 010-3270-3270 / 01032703270 => 010-****-3270 / 010****3270
		* */

		if (is_array($phoneNum)) {
			// 배열로 넘어온 경우
			$maskedPhoneNum = $phoneNum;
			$maskedPhoneNum[1] = str_repeat('*', strlen($phoneNum[1]));
		} else {
			if (!strpos($phoneNum, '-')) {
				// 숫자만 넘어온 경우
				$phoneNum = preg_replace('/[^0-9]/', '', $phoneNum);
				$len = strlen($phoneNum);

				switch ($len) {
					case 11:
						$phoneNum = preg_replace('/([0-9]{3})([0-9]{4})([0-9]{4})/', '$1****$3', $phoneNum);

						break;
					case 10:
						if (substr($phoneNum, 0, 2) == '02') {
							$phoneNum = preg_replace('/([0-9]{2})([0-9]{4})([0-9]{4})/', '$1****$3', $phoneNum);
						} else {
							$phoneNum = preg_replace('/([0-9]{3})([0-9]{3})([0-9]{4})/', '$1***$3', $phoneNum);
						}

						break;
					case 9:
						if (substr($phoneNum, 0, 2) == '02') {
							$phoneNum = preg_replace('/([0-9]{2})([0-9]{3})([0-9]{4})/', '$1***$3', $phoneNum);
						}

						break;
				}
				$maskedPhoneNum = $phoneNum;
			} else {
				// "-" 포함되어 넘온 경우
				$phoneNum = explode('-', $phoneNum);
				$maskedPhoneNum = $phoneNum[0] . '-' . str_repeat('*', strlen($phoneNum[1])) . '-' . $phoneNum[2];
			}
		}

		return $maskedPhoneNum;
	}

	/**
	* 주소 마스킹 처리
	* @param address
	* @return maskedAddress
	*/
	private function _getMaskedAddress($address)
	{
		/*
		* 마지막 상세주소 전체 마스킹
		* 예시)  경기도 성남시 분당구 대왕판교로 670 (유스페이스2) 322호 => 경기도 성남시 분당구 대왕판교로 670 (유스페이스2) ****
		* */

		$maskedAddress = str_repeat('*', mb_strlen($address, 'utf-8'));

		return $maskedAddress;
	}

	/**
	* 이메일주소 마스킹 처리
	* @param email
	* @return maskedEmail
	*/
	private function _getMaskedEmail($email)
	{
		/*
		* @ 앞 2자리, 뒤 2번째부터 전체 마스킹
		* 예시) gabia3270@gabiacns.com => gabia32**@g***********
		* */

		if (strpos($email, '@')) {
			$email = explode('@', $email);

			$len = mb_strlen($email[0], 'utf-8');
			// 이메일 @ 앞 마스킹
			if ($len <= '2') {
				$email[0] = str_repeat('*', $len);
			} else {
				$email[0] = mb_substr($email[0], 0, -2, 'utf-8') . '**';
			}

			$len = mb_strlen($email[1], 'utf-8');
			// 이메일 @ 뒤 마스킹
			if ($len <= '1') {
				$email[1] = str_repeat('*', $len);
			} else {
				$email[1] = mb_substr($email[1], 0, 1, 'utf-8') . str_repeat('*', $len - 1);
			}
			$maskedEmail = $email[0] . '@' . $email[1];
		} else {
			return $email;
		}

		return $maskedEmail;
	}

	/**
	* 계좌번호 마스킹 처리
	* @param bankAccount
	* @return maskedBankAccount
	*/
	private function _getMaskedAccount($bankAccount)
	{
		/*
		* 6자리 이하 전체 마스킹
		* 예시) 123456789012 => 12345*******
		* */

		if (!strpos(trim($bankAccount), ' ')) {
			// 계좌번호만 넘어온 경우
			$len = strlen($bankAccount);
			if ($len <= '5') {
				$maskedBankAccount = str_repeat('*', $len);
			} else {
				$maskedBankAccount = substr($bankAccount, 0, 5) . str_repeat('*', $len - 5);
			}
		} else {
			return $bankAccount;
		}

		return $maskedBankAccount;
	}
	
	/**
	* 추가 마스킹 처리
	* @param params
	* @return return
	*/
	private function _getMaskedAddException(&$result_params)
	{

		/*
		오픈마켓 주문 시 주소 마스킹
		공백 기준 앞 3단어 외 모두 마스킹 처리
		*/
		if(trim($result_params['market']) || trim($result_params['linkage_mall_order_id']))
		{
			$tmp_address_group = [
				'recipient_address',
				'recipient_address_street',
			];

			foreach($tmp_address_group as $row) {
				if(trim($result_params[$row]) && !trim($result_params['recipient_address_detail']))
				{
					$arr_address 	= explode(" ", trim($result_params[$row]));
					$data = [];

					foreach($arr_address as $k => $_address)
					{
						if($k < 3)
						{
							$data['address'][] = $_address;
						}else
						{
							$data['detail'][] = $_address;
						}
					}
	
					$result_params[$row] = implode(" ", $data['address']) . " " . $this->_getMaskedAddress(implode(" ", $data['detail']));
				}
			}
		}
	}

	/**
	 * 롯데택배 개인정보 마스킹 처리
	 * @param params
	 * @param type
	 * @return result_params
	 */
	public function hlc_masking($params, $type = 'invoice_prints')
	{

		// 롯데택배 개인정보 마스킹 표시 권한 체크
		if (count($params) > 0) {
			$result_params = $params;
			if ($type === 'invoice_prints') {
				foreach ($params as $k => $item) {
					unset($masking_str);
					if (in_array($k, $this->name_group)) {
						$masking_str = $this->_getHlcMaskedName($item);
					} elseif (in_array($k, $this->phone_group)) {
						$masking_str = $this->_getHlcMaskedPhone($item);
					} else {
						$masking_str = $item;
					}
					
					$result_params[$k] = $masking_str;
				}
			}
			return $result_params;
		} else {
			return $params;
		}
	}
	
	/**
	* 롯데택배 수하인 성명 마스킹 처리
	* @param name
	* @return maskedName
	*/
	private function _getHlcMaskedName($name)
	{
		/*
		* 이름 두 번째 자리 마스킹
		* 예시) 가비아 / 퍼스트몰 / First => 가*아 / 퍼*트몰 / F*rst
		* */
		$regex = '/(?!^.?).(?!.{0}$)/u';
		$len = mb_strlen($name, 'utf-8');
		if ($len > '2') {
			if (preg_match($regex, $name, $matchResult)) {
				$maskedName = preg_replace($regex, '*', $name, 1); // 매칭되는 첫번째만 치환
			} else {
				return $name;
			}
		} elseif ($len == '2') {
			$maskedName = mb_substr($name, 0, 1, 'utf-8').str_repeat('*', $len - 1);
		} else {
			return $name;
		}
		return $maskedName;
	}
	
	/**
	* 롯데택배 수하인 연락처 마스킹 처리
	* @param phoneNum
	* @return maskedPhoneNum
	*/
	private function _getHlcMaskedPhone($phoneNum)
	{
		/*
		* 마지막 네자리 마스킹
		* 예시) 010-3270-3270 / 01032703270 => 010-3270-**** / 0103270****
		* */
		if (is_array($phoneNum)) {
			// 배열로 넘어온 경우
			$maskedPhoneNum = $phoneNum;
			$maskedPhoneNum[1] = str_repeat('*', strlen($phoneNum[2]));
		} else {
			if (!strpos($phoneNum, '-')) {
				// 숫자만 넘어온 경우
				$phoneNum = preg_replace('/[^0-9]/', '', $phoneNum);
				$len = strlen($phoneNum);

				switch ($len) {
					case 11:
						$phoneNum = preg_replace('/([0-9]{3})([0-9]{4})([0-9]{4})/', '$1$2****', $phoneNum);

						break;
					case 10:
						if (substr($phoneNum, 0, 2) == '02') {
							$phoneNum = preg_replace('/([0-9]{2})([0-9]{4})([0-9]{4})/', '$1$2****', $phoneNum);
						} else {
							$phoneNum = preg_replace('/([0-9]{3})([0-9]{3})([0-9]{4})/', '$1$2***', $phoneNum);
						}

						break;
					case 9:
						if (substr($phoneNum, 0, 2) == '02') {
							$phoneNum = preg_replace('/([0-9]{2})([0-9]{3})([0-9]{4})/', '$1$2***', $phoneNum);
						}

						break;
				}
				$maskedPhoneNum = $phoneNum;
			} else {
				// "-" 포함되어 넘온 경우
				$phoneNum = explode('-', $phoneNum);
				$maskedPhoneNum = $phoneNum[0] . '-' . $phoneNum[1] . '-' . str_repeat('*', strlen($phoneNum[2]));
			}
		}
		return $maskedPhoneNum;
	}

}
