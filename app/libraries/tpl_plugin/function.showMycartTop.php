<?php
/* 카트 및 위시리스트 상단 출력 */
function showMycartTop($type ='cart')
{
	$CI =& get_instance();
	$resval = 0;
	
	if($type == 'cart'){
		$CI->load->model('cartmodel');
		$cart = $CI->cartmodel->catalog();
		//$resval = ($cart['list'])? count($cart['list']) : 0;
		$resval = $CI->cartmodel->get_cart_count();
	}elseif($type == 'wish'){
		$CI->load->model('wishmodel');
		$resval = $CI->wishmodel->get_wish_count($CI->userInfo['member_seq']);
	}elseif($type == 'recently'){
		$result = array();
		$today_view = $_COOKIE['today_view'];
		$resval = $today_view ? count(unserialize($today_view, ['allowed_classes' => false])) : 0;
	}
	
	return $resval;
}
?>