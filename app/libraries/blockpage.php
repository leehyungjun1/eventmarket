<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');
class blockpage extends CI_Model {
	var $page				= 1;	// 페이지 위치
	var $page_number	= 10;	// 페이지당 rows
	var $perpage			= 10;	// 페이지숫자 링크 갯수
	var $block				= 1;	// 블럭 위치
	var $block_number	= 0;	// 블럭당 rows
	var $limit_add		= 1;	// 다음 목록이 있는지 여부를 체크할 limit add 값
	var $bind_not = false; // 쿼리 바인딩 처리 여부

	function set()
	{
		$this->block_number = $this->page_number * $this->perpage;
	}

	function page_query($params, $SELECD_DB = null)
	{
		$count	= 0;
		$gab	= 0;
		$query				= $params['query'];
		$bind					= $params['bind'];
		$ar_return['record'] = array();
		if (! $bind) {
			$bind = array();
		}

		$ar_return['next_block'] = false;

		if ($SELECD_DB) {
			$CDB = $SELECD_DB;
		} else {
			$CDB = $this->db;
		}

		// 현재 페이지 범위
		$page_start = ($this->page - 1) * $this->page_number;
		$page_end = $page_start + $this->page_number;

		$this->block = ceil(($page_start + 1) / $this->block_number);
		$query = str_replace('%', '‰', $query);
		$start = ($this->block - 1) * $this->block_number;
		// 물음표(?)를 검색하려 할 때 str_replace 와 vsprintf 로 강제 바인딩을 처리함
		// 이때 쿼리를 재귀형으로 호출하여 2회 이상 강제 바인딩을 시도하는 문제를 해결하기 위해 중복 바인딩 시 치환 처리
		// 2018-02-13 hed
		// 향후 고도화나 수정 시 query bind 기능을 사용하는 것을 권장.
		// $query = preg_replace('/\'(‰)?[?](‰)?\'/',"'%s'",$query);
		foreach ($bind as $key => $val) {
			$bind[$key] = str_replace("'", "\'", $val); // SQL Injection 방지 추가 :: 2019-06-20 lw
		}
		if ($this->bind_not === false) {
			$query = str_replace('?', "'%s'", $query);
			$query	= vsprintf($query, $bind);
		}
		$query = str_replace('‰', '%', $query);

		if ($this->startPage) {
			$oldStart	= $start;
			$start	= ($this->startPage - 1) * $this->page_number;
			$gab	= $oldStart - $start;
		}

		if($params['mode'] != 'query2') {
			$query	= trim($query) . " limit " . $start . " , " . ($this->block_number + $this->limit_add + $gab);
		}

		if ($params['mode'] == 'query1') {
			return $query;
		}
		$query	= $CDB->query($query);

		foreach ($query->result_array() as $row) {
			$pos = $start + $count;
			$row['_no'] = $pos + 1;
			$row['_rno'] = $row['_no'];
			if (! $this->startPage && $this->block_number >= $count && $pos < $page_end && $pos >= $page_start) {
				$ar_return['record'][] = $row;
			}
			if ($this->startPage && $count <= ($this->page * $this->page_number) - 1) {
				$ar_return['record'][] = $row;
			}
			$count ++;
		}

		$totalcount = $this->block_number * ($this->block - 1) + $count;
		if ($this->iTotcount!='') {
			$totalcount = $this->iTotcount;
		}

		$totalpage = ceil($totalcount / $this->page_number);

		$step = ceil($this->page / $this->perpage);
		$querystring	= get_args_list();
		$querystring = preg_replace('/([\&]{0,1}page\=[0-9]*|page\=[0-9]*[\&]{0,1})/', '', $querystring);

		$ar_return['page'] = array(
			'totalpage' => $totalpage,
			'totalcount' => $totalcount,
			'nowpage' => $this->page,
			'page' => array(),
			'next' => false,
			'prev' => false,
			'last' => false,
			'first' => false,
			'querystring' => $querystring
		);
		if ($step * $this->perpage < $totalpage)
			$ar_return['page']['next'] = $step * $this->perpage + 1;
		if ($step != 1)
			$ar_return['page']['prev'] = ($step - 1) * $this->perpage;
		
		if ($ar_return['page']['prev'])
			$ar_return['page']['first'] = 1;
		if ($ar_return['page']['next'])
			$ar_return['page']['last'] = $totalpage;
		
		if ($ar_return['page']['next'])
			$count = $this->perpage;
		else {
			if ($totalpage)
				$count = $totalpage % $this->perpage ? $totalpage % $this->perpage : $this->perpage;
			else
				$count = 0;
		}
		$loop_start = ($step - 1) * $this->perpage + 1;
		for ($i = 0; $i < $count; $i ++)
			$ar_return['page']['page'][$i] = $loop_start + $i;
		return $ar_return;
	}

	public function page_html($ar_return){
		if($ar_return['page']['first'])	$html .= "<a href='?page={$ar_return['page']['first']}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}' class='first'><span>처음</span></a>";
		if($ar_return['page']['prev'])	$html .= "<a href='?page={$ar_return['page']['prev']}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}' class='prev'><span>◀</span></a>";
		foreach($ar_return['page']['page'] as $value){
			if($ar_return['page']['nowpage'] == $value){
				$html .= "<a href='?page={$value}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}' class='on'>{$value}</a>";
			}else{
				$html .= "<a href='?page={$value}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}'>{$value}</a>";
			}
		}

		if($ar_return['page']['next'])		$html .= "<a href='?page={$ar_return['page']['next']}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}' class='next'><span>▶</span></a>";
		$ar_return['page']['html'] = $html;
		return $ar_return;
	}

	public function page_script($ar_return, $script){
		$html = "";
		if($ar_return['page']['first'])	$html .= "<a href=\"javascript:{$script}('page={$ar_return['page']['first']}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}');\" class='first'><span>처음</span></a>";
		if($ar_return['page']['prev'])	$html .= "<a href=\"javascript:{$script}('page={$ar_return['page']['prev']}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}');\" class='prev'><span>◀</span></a>";
		foreach($ar_return['page']['page'] as $value){
			if($ar_return['page']['nowpage'] == $value){
				$html .= "<a href=\"javascript:{$script}('page={$value}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}');\" class='on'>{$value}</a>";
			}else{
				$html .= "<a href=\"javascript:{$script}('page={$value}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}');\">{$value}</a>";
			}
		}

		if($ar_return['page']['last'])	$html .= "<a href=\"javascript:{$script}('page={$ar_return['page']['last']}&amp;perpage={$this->page_number}&amp;{$ar_return['page']['querystring']}');\" class='last'><span>▶</span></a>";

		$ar_return['page']['html'] = $html;

		return $ar_return;
	}
}
?>
