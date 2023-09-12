<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}
/**
 * 주문의 개인정보 분리 및 관리
 * 2023-03-30
 */
class PersonalInfoLibrary
{
	public $orderField;
	public $returnField;

	public function __construct() {
		$this->CI =& get_instance();
		$this->CI->load->model('ordermodel');
		$this->CI->load->model('exportmodel');
		$this->CI->load->model('refundmodel');
		$this->CI->load->model('returnmodel');
		$this->CI->load->model('personalinfomodel');

		// 주문테이블 분리 필드
		$this->orderField =	[
			'depositor', 
			'bank_account', 
			'virtual_account', 
			'order_user_name', 
			'order_phone', 
			'order_cellphone', 
			'order_email', 
			'recipient_user_name', 
			'recipient_phone', 
			'recipient_cellphone', 
			'recipient_zipcode', 
			'recipient_address_type', 
			'recipient_address', 
			'recipient_address_street', 
			'recipient_address_detail', 
			'recipient_address_street_gf', 
			'international', 
			'shipping_method_international', 
			'region', 
			'nation_key', 
			'international_address', 
			'international_town_city', 
			'international_county', 
			'international_postcode', 
			'international_country', 
			'recipient_email'
		];

		// 반품테이블 분리 필드
		$this->returnField = [
			'sender_zipcode', 
			'sender_address_type', 
			'sender_address', 
			'sender_address_street', 
			'sender_address_detail', 
			'phone', 
			'cellphone', 
			'shipping_price_depositor'
		];
	}

	/**
	 * 개인정보보호 대상 주문 설정 여부 체크
	 * @return boolean
	 */
	public function isPersonalInfoUse() {
		$cfg_order = config_load('order');

		$use = false;
		if (isset($cfg_order['separating_personal_info']) && $cfg_order['separating_personal_info'] == 'y') {	
			$use = true;
		}

		return $use;
	}

	/**
	 * 휴면/탈퇴 회원 주문 조회 view로 넘길 데이터 가공
	 * @param array $personalInfoData
	 * @return array $personalInfoData
	 */
	public function dataRebuild($personalInfoData = [])
	{
		$result = $personalInfoData['result'];

		foreach($result as $k => $row) {
			// D2304121178,D2304121179 와 같이 주문번호와 1:n
			$result[$k]['export_code'] = explode(",", $row['export_code']);
			$result[$k]['refund_code'] = explode(",", $row['refund_code']);
			$result[$k]['return_code'] = explode(",", $row['return_code']);

			// 개인정보 분리 데이터 저장 시 json 타입을 저장하기에 배열로 변환
			$order_data = json_decode($row['order_data'], true);

			// 주문자 정보
			$order_info = [
				'order_user_name'	=> $order_data['order_user_name'],
				'order_cellphone'	=> $order_data['order_cellphone'],
				'order_phone'		=> $order_data['order_phone'],
				'order_email'		=> $order_data['order_email']
			];

			// 배송지 정보
			if($order_data['international'] == 'domestic') { // 국내
				$shipping_info = [
					'recipient_user_name'		=> $order_data['recipient_user_name'],
					'recipient_cellphone'		=> $order_data['recipient_cellphone'],
					'recipient_phone'			=> $order_data['recipient_phone'],
					'recipient_zipcode'			=> '('.$order_data['recipient_zipcode'].')',
					'recipient_address_street'	=> $order_data['recipient_address_street'],
					'recipient_address'			=> $order_data['recipient_address'],
					'recipient_address_detail'	=> $order_data['recipient_address_detail']
				];
			} elseif($order_data['international'] == 'international') { // 해외
				$shipping_info = [
					'recipient_user_name'		=> $order_data['recipient_user_name'],
					'recipient_cellphone'		=> $order_data['recipient_cellphone'],
					'recipient_phone'			=> $order_data['recipient_phone'],
					'international_postcode'	=> '('.$order_data['international_postcode'].')',
					'international_country'		=> $order_data['international_country'],
					'international_address'		=> $order_data['international_address'],
					'international_town_city'	=> $order_data['international_town_city'],
					'international_county'		=> $order_data['international_county']
				];
			}

			$result[$k]['order_info'] = $order_info;
			$result[$k]['shipping_info'] = $shipping_info;

			unset($result[$k]['order_data']);
			unset($result[$k]['return_data']);
		}
		$personalInfoData['result'] = $result;

		return $personalInfoData;
	}

	/**
	 * // 개인정보 보호 주문 분리
	 * @param array $members
	 * @return boolean $result
	 */
	public function separatePersonalInfo($members) {
		if(empty($members)) {
			return false;
		}

		// 회원번호 기준으로 주문데이터 묶음 조회
		$memberSeqArr = array_column($members, 'member_seq');
		$orders = $this->CI->ordermodel->getOrderForMemberSeq($memberSeqArr, $this->orderField);

		foreach($members as $member) {
			$memberArr[$member['member_seq']] = $member;
		}

		foreach($orders as $idx => $order) {
			if (!$order['order_seq']) {
				continue;
			}

			$personalInfo = $this->CI->personalinfomodel->getPersonalInfo(['order_seq'=>$order['order_seq']]);
			// 이미 분리된 주문건은 제외
			if($personalInfo[0]['seq']) {
				continue;
			}

			// 주문번호 기준 각 출고번호,환불번호,반품번호를 가져온다.
			$export = $this->CI->exportmodel->getExportCodeForOrderSeq($order['order_seq']);
			$refund = $this->CI->refundmodel->getRefundCodeForOrderSeq($order['order_seq']);
			$return = $this->CI->returnmodel->getReturnForOrderSeq($order['order_seq'], $this->returnField);

			$orders[$idx]['userid'] = $memberArr[$order['member_seq']]['userid'];
			$orders[$idx]['status'] = $memberArr[$order['member_seq']]['status'];;
			$orders[$idx]['export'] = $export;
			$orders[$idx]['refund'] = $refund;
			$orders[$idx]['return'] = $return;

			// 주문분리 대상 데이터 매칭 후 가공
			$insertData[] = $this->_getMatchingField($orders[$idx]);

			if ($order['order_seq']) {
				// 주문데이터 분리하고 기존 저장값 공란으로 처리
				$newOrderField = array_fill_keys($this->orderField, "''");
				$newOrderField['order_seq'] = $order['order_seq'];
				$updateOrderData[] = $newOrderField;

				// 주문로그 actor 필드가 주문자명이면 공란 업데이트
				$updateOrderLog['actor'] = '';
				$this->CI->ordermodel->updateOrderLog($updateOrderLog, $order['order_seq'], $order['order_user_name']);
			}

			$returnCodeArr = array_column($return, 'return_code');
			// 주문번호 기준 반품번호는 1:n 방식으로 foreach 처리
			foreach($returnCodeArr as $returnCode) {
				if ($returnCode) {
					// 반품데이터 분리하고 기존 저장값 공란으로 처리
					$newReturnField = array_fill_keys($this->returnField, "''");
					$newReturnField['return_code'] = "'".$returnCode."'";
					$updateReturnData[] = $newReturnField;
				}
			}
		}

		$result = true;

		if (count($insertData) > 0) {
			// 개인정보 보호 대상 데이터 별도 테이블에 batch로 Insert
			$result = $this->CI->personalinfomodel->insertBatch($insertData);

			if (count($updateOrderData) > 0) {
				// 기존 주문DB 공란으로 업데이트
				$this->CI->ordermodel->updateOrderData($updateOrderData, 'order_seq');
			}
			if (count($updateReturnData) > 0) {
				// 기존 반품DB 공란으로 업데이트
				$this->CI->returnmodel->updateReturnData($updateReturnData, 'return_code');
			}
		}

		return $result;
	}

	/**
	 * 개인정보 보호 분리 주문 복원
	 * @param int $member_seq
	 */
	public function restorePersonalInfo($member_seq) {
		// 회원번호 기준 복원할 분리 데이터 조회
		$personalInfoArr = $this->CI->personalinfomodel->getPersonalInfo(['member_seq'=>$member_seq]);

		foreach($personalInfoArr as $data) {
			if (!$data['seq']) {
				continue;
			}

			// 주문 개인정보 백업 데이터 json타입으로 저장하여 배열로 변환
			$order_data = json_decode($data['order_data'], true);
			$order = $order_data;
			if($data['order_seq'] && !empty($order)) {
				$order['order_seq'] =  $data['order_seq'];
				// json_decode 배열 그대로 update 진행 시 오류 발생
				// 문자열 앞뒤 따옴표 추가, 값이 없으면 null로 치환quote
				array_walk($order, function (&$value) {
					if ($value) {
						$value = "'".$value."'";
					} else {
						$value = "null";
					}	
				});
				
				$updateOrderData[] = $order;

				// 주문로그 주문자명 복원 업데이트
				$updateOrderLog['actor'] = $order_data['order_user_name'];
				$this->CI->ordermodel->updateOrderLog($updateOrderLog, $data['order_seq']);
			}

			// 반품 개인정보 백업 데이터 json타입으로 저장하여 배열로 변환
			$returnArr = json_decode($data['return_data'], true);
			foreach($returnArr as $idx => $return_data) {
				if($idx && !empty($return_data)) {
					$return_data['return_code'] = $idx;
					// json_decode 배열 그대로 update 진행 시 오류 발생
					// 문자열 앞뒤 따옴표 추가, 값이 없으면 null로 치환quote
					array_walk($return_data, function (&$value) {
						if ($value) {
							$value = "'".$value."'";
						} else {
							$value = "null";
						}	
					});

					$updateReturnData[] = $return_data;
				}
			}
		}

		if (count($updateOrderData) > 0) {
			// 기존 주문DB 복원 업데이트
			$this->CI->ordermodel->updateOrderData($updateOrderData, 'order_seq');
		}
		if (count($updateReturnData) > 0) {
			// 기존 반품DB 복원 업데이트
			$this->CI->returnmodel->updateReturnData($updateReturnData, 'return_code');
		}

		// 복원 후 잔여 데이터 삭제처리
		$this->CI->personalinfomodel->delete(['member_seq'=>$member_seq]);
	}

	/**
	 * 주문분리 대상 데이터 매칭 후 가공
	 * @param array $data
	 * @return array $matchingData
	 */
	private function _getMatchingField($data) {
		$exportCodeArr = array_column($data['export'], 'export_code');
		$refundCodeArr = array_column($data['refund'], 'refund_code');
		$returnCodeArr = array_column($data['return'], 'return_code');

		$matchingData = [
			'member_seq'	=> $data['member_seq'],
			'userid'		=> $data['userid'],
			'status'		=> $data['status'],
			'order_seq'		=> $data['order_seq'],
			'export_code'	=> implode(",", $exportCodeArr),
			'refund_code'	=> implode(",", $refundCodeArr),
			'return_code'	=> implode(",", $returnCodeArr),
			'order_date'	=> $data['regist_date'],
			'deposit_date'	=> $data['deposit_date'],
			'regist_date'	=> date('Y-m-d H:i:s')
		];

		// 주문 개인정보 매칭
		foreach($this->orderField as $field) {
			$orderDataArr[$field] = $data[$field]; 
		}
		// 주문 개인정보 데이터는 json 타입으로 저장
		$matchingData['order_data'] = json_encode($orderDataArr, JSON_UNESCAPED_UNICODE);

		if (empty($data['return'])) {
			// 반품번호가 없는 경우
			$matchingData['return_data'] = '{}';
		} else {
			foreach($data['return'] as $return) {
				// 반품 개인정보 매칭
				foreach($this->returnField as $field) {
					$returnDataArr[$return['return_code']][$field] = $return[$field]; 
				}
			}
			// 반품 개인정보 데이터는 json 타입으로 저장
			$matchingData['return_data'] = json_encode($returnDataArr, JSON_UNESCAPED_UNICODE);
		}

		return $matchingData;
	}

}
?>