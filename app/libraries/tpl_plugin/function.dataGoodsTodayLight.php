<?php
/**
 * @author lgs
 */
function dataGoodsTodayLight($mode = 'list', $limit = null)
{
	$CI =& get_instance();

	//
	$result = [];
	$today_view = $_COOKIE['today_view'];
	if ($today_view) {
		$today_view = unserialize($today_view, ['allowed_classes' => false]);
		if ($mode == 'count') {
			return count($today_view);
		}

		$CI->load->model('goodslistmodel');
		$aSearch = [
			'page'       => 1,
			'perpage'    => 12,
			'sorting'    => 'regist',
			'image_size' => 'thumbScroll',
			'platform'   => ($CI->mobileMode || $CI->_is_mobile_agent) ? 'M' : 'P',
		];
		if ($limit) {
			$today_view = array_slice($today_view, -$limit);
			$aSearch['perpage'] = $limit;
		}

		//
		$CI->db->select('go.goods_seq')
			->from('fm_goods go')
			->where_in('go.goods_seq', $today_view);
		$sGoodsQuery = '(' . $CI->db->get_compiled_select() . ')';
		$aTmp = $CI->goodslistmodel->goodsSearch($aSearch, $sGoodsQuery);

		//최신 본 순으로 정렬 by kmj
		$today_view = array_reverse($today_view);
		foreach ($aTmp['record'] as $seq => $val) {
			$result[array_search($val['goods_seq'], $today_view)] = $val;
		}
		ksort($result);
	}
	if ($mode == 'count') {
		return false;
	} else {
		return $result;
	}
}
