<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}
/**
 * 개인정보 분리 모델
 * 2023-03-30
 */
class Personalinfomodel extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->table = 'fm_personal_info';
	}

	/**
	 * 개인정보 보호 대상 분리된 주문을 가져온다.
	 * @param string $param
	 * @return array
	 */
	public function getPersonalInfo($param)
	{
		return $this->db->from($this->table)
				->where($param)
				->get()
				->result_array();
	}

	/**
	 * 검색조건에 맞는 개인정보 보호 주문리스트를 가져온다.
	 * @param array $sc 
	 * @return array $data
	 */
	public function getPersonalInfoList($sc)
	{
		# 조회 데이터 설정
		$this->db->select('SQL_CALC_FOUND_ROWS *', false);

		# 테이블 설정
		$this->db->from($this->table);

		# 조건 설정
		$keyword_type = $sc['keyword_type'] ? $sc['keyword_type'] : 'userid';
		if(in_array($keyword_type, ['userid', 'order_seq'])) {
			$this->db->where($keyword_type, $sc['keyword']);
		} else {
			$this->db->where("CONCAT(',',".$keyword_type.", ',') LIKE CONCAT('%,', '".$sc['keyword']."', ',%')");
		}

		$date_field = $sc['date_field'] ? $sc['date_field'] : 'order_date';
		if($sc['regist_date'][0]) {
			$this->db->where($date_field.' >=', $sc['regist_date'][0].' 00:00:00');
		}
		if($sc['regist_date'][1]) {
			$this->db->where($date_field.' <=', $sc['regist_date'][1].' 23:59:59');
		}

		if($sc['status'] && $sc['status'] != 'all') {
			$this->db->where('status', $sc['status']);
		}

		# 정렬 설정
		$this->db->order_by('order_date', 'DESC');

		# 페이징 설정
		$this->db->limit($sc['perpage'], $sc['page']);

		$result = $this->db->get()->result_array();
		$data['result'] = $result;

		//검색총건수
		$sql = "SELECT FOUND_ROWS() as COUNT";
		$query_count = $this->db->query($sql);
		$res_count= $query_count->result_array();
		$data['count'] = $res_count[0]['COUNT'];

		return $data;
	}

	/**
	 * 개인정보 분리 주문을 batch로 저장한다.
	 * @param array $params
	 * @return boolean
	 */
	public function insertBatch($params = [])
	{
		$result = $this->db->insert_batch($this->table, $params);

		return $result ? true : false;
	}

	/**
	 * 개인정보 분리 주문을 batch로 삭제한다.
	 * @param string $member_seq
	 * @return boolean
	 */
	public function delete($params = [])
	{
		// $params 배열이 비어있는지 검증
		if (empty($params)) {
			return false;
		}

		// $params 배열에서 모든 키와 값을 검증하고 유효한 데이터면 delete문 실행
		foreach ($params as $key => $value) {
			if (empty($key) || empty($value)) {
				return false;
			}
		}

		return $this->db->delete($this->table, $params);
	}
}
