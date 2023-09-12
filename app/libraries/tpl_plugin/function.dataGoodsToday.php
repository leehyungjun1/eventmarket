<?php

/**
 * @author lgs
 */

function dataGoodsToday($limit=null)
{
	$CI =& get_instance();
	$CI->load->library('snssocial');
	$result = array();
	$today_view = $_COOKIE['today_view'];
	if( $today_view ) {
		$today_view = unserialize($today_view, ['allowed_classes' => false]);
		krsort($today_view);
		if($limit) $today_view = array_slice($today_view,-$limit);
		$CI->load->model('goodsmodel');
		$result = $CI->goodsmodel->get_goods_list($today_view,'thumbScroll');
	}

	return $result;
}
?>