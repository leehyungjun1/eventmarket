<?php

if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}
/**
 * 통합 처리 내역 로그 lib
 */
class ActionHistoryLibrary
{
	public function __construct()
	{
		$this->CI = &get_instance();
		$this->CI->load->model('actionhistorymodel');
	}

	// 로그 기록
	public function recordHistory($arr)
	{
		if (!$arr['action'] || !$arr['category']) {
			return;
		}

		$params['category'] = $arr['category'];
		$params['action'] = $arr['action'];
		$params['detail'] = $arr['detail'];

		// actor 기본 system
		$params['actor'] = 'system';
		// __ADMIN__ >> manager
		if (defined('__ADMIN__') === true && $this->CI->managerInfo) {
			$params['actor'] = 'manager';
			$params['manager_seq'] = $this->CI->managerInfo['manager_seq'];
		} elseif (defined('__SELLERADMIN__') === true && $this->CI->providerInfo) {
			$params['actor'] = 'provider';
			$params['provider_seq'] = $this->CI->providerInfo['sub_provider_seq'];
		}

		$params['regist_ip'] = $this->CI->input->server('REMOTE_ADDR') ? $this->CI->input->server('REMOTE_ADDR') : '';
		$params['regist_date'] = date('Y-m-d H:i:s');

		$this->CI->actionhistorymodel->insert($params);
	}

	// 로그 확인
	public function getHistorys($param = [])
	{
		$this->CI->load->model('managermodel');

		// param 데이터 우선 param 데이터 없을 때 get
		if (empty($param)) {
			$sc = $this->CI->input->get();
			$sc['perpage'] = (!empty($sc['perpage'])) ? intval($sc['perpage']) : 10;
			$sc['page'] = (!empty($sc['page'])) ? intval($sc['page']) : 0;
			$sc['searchcount'] = true;
			$param = $sc;
		}

		$historys = $this->CI->actionhistorymodel->getHistorys($param);

		$page['searchcount'] = $this->CI->actionhistorymodel->getHistorysCount();

		$nowpage = $param['page'];
		$totalPage = ceil($page['searchcount'] / $param['perpage']);
		$pages = [];

		$rno = $page['searchcount'] - ($param['perpage'] * ($param['page'] - 1));

		$page['html'] = pagingtagjs($nowpage, $totalPage, $page['searchcount'], "historyPageClick([:PAGE:],'".$param['category']."')");
		$managerInfo = [];
		foreach ($historys as $key => &$row) {
			$historys[$key]['rno'] = $rno;
			$rno--;

			//관리자 정보
			$manager_seq = $row['manager_seq'];
			if (!is_array($managerInfo[$manager_seq])) {
				$managerInfo[$manager_seq] = $this->CI->managermodel->get_manager($manager_seq);
			}

			$historys[$key]['manager_id'] = $managerInfo[$manager_seq]['manager_id'];
			$historys[$key]['mname'] = $managerInfo[$manager_seq]['mname'];
		}

		$result = [
			'page' => $page,
			'historys' => $historys,
		];

		return $result;
	}
}