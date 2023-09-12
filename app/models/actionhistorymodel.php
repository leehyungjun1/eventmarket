<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

/**
 * 통합 처리 내역 로그 모델
 */
class actionhistorymodel extends CI_Model
{
	public function __construct()
	{
	}

	/**
	 * 로그 기록
	 * @param array $params
	 * @return boolean
	 */
	public function insert($params = [])
	{
		$filter_params = filter_keys($params, $this->db->list_fields('fm_action_history_log'));
		$result = $this->db->insert('fm_action_history_log', $filter_params);
		if ($result !== true) {
			return false;
		}

		return $this->db->insert_id();
	}

	/**
	 * 로그 리스트
	 */
	public function getHistorys($params = [])
	{
		$query = $this->db->select('SQL_CALC_FOUND_ROWS *', false)->from('fm_action_history_log l');

		if ($params['category']) {
			// 카테고리가 여러 개인 경우 처리
			if(is_array($params['category'])) $params['category'] = implode("','", $params['category']);
			$query->where('l.category in ', "('".$params['category']."')", false);
		}
		$sort = (!empty($params['sort']) && $params['sort'] != 'null') ? $params['sort'] : 'desc';
		$orderby = $params['orderby'] ? $params['orderby'] . ' ' . $sort : 'l.seq ' . $sort;

		$query->order_by($orderby);
		if ($params['perpage']) {
			$page = 0;
			if ($params['page'] > 0) {
				$page = $params['perpage'] * ($params['page'] - 1);
			}
			$query->limit($params['perpage'], $page);
		}
		$query = $query->get();

		return $query->result_array();
	}

	/**
	 * 검색된 result 의 count
	 * @return int
	 */
	public function getHistorysCount()
	{
		$query = $this->db->select('FOUND_ROWS() as COUNT', false)->get()->row_array();

		return $query['COUNT'];
	}

	/**
	* 총 로그 개수
	* @return int
	*/
	public function getHistorysTotal()
	{
		$query = $this->db->select('count(*) as CNT')->from('fm_action_history_log l');

		$query = $query->get();
		$query = $query->row_array();

		return $query['CNT'];
	}
}