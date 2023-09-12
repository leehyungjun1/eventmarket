<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class itemexcelfilter extends CI_Model
{
	var $data_provider			= '';
	var $data_linkage			= '';
	var $done					= array();
	var $data_paymethod			= array(); // orderexcel_pay_method
	var $data_tax				= array(); // orderexcel_tax
	var $data_step				= array(); // config:step
	var $data_shipping_group_name	= array();	//shipping group name

	public function check_stock($params)
	{
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option	= $params['data_option'];
		$data_package	= $params['data_package'];

		$result = false;
		if($data_option['stock'] === '미매칭') $result = true;
		if($data_option['packages']) $result = false;

		if($data_package){
			$yellow = false;
			if($data_package['stock'] === '미매칭') $result = true;
		}

		return $result;
	}

	public function check_package($params)
	{
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result = false;
		if($data_option['packages'])	$result = true;
		if($data_package)				$result = false;
		return $result;
	}

	public function export_item_seq($params)
	{
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$export_item_seq = $data_option[ 'export_item_seq'];
		if($data_package['package_option_seq'])		$export_item_seq .= '-P'.$data_package['package_option_seq'];
		if($data_package['package_suboption_seq'])	$export_item_seq .= '-P'.$data_package['package_suboption_seq'];
		return $export_item_seq;
	}

	public function shipping_provider($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$data_provider	= $this->data_provider;
		$provider_name = $data_provider[$data_shipping['provider_seq']]['provider_name'];
		return $provider_name;
	}

	public function provider_name($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$data_provider	= $this->data_provider;
		$provider_name = $data_shipping['items'][$data_option['item_seq']]['provider_name'];
		return $provider_name;
	}

	public function shipping_seq($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$shipping_seq		= $data_shipping['shipping_seq'];
		return $shipping_seq;
	}

	public function order_seq_shipping_seq($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$order_seq_shipping_seq = $data_order['order_seq'].'-'.$data_shipping['shipping_seq'];
		return $order_seq_shipping_seq;
	}

	public function userid($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		return $data_member['userid'];
	}

	public function linkage_mallname($params){
		/* 주문별 판매마켓 프로세스와 통일 18.09.10 kmj
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$data_linkage		= $this->data_linkage;
		return $data_linkage[$data_order['linkage_mall_code']];
		*/

		if($params['data_order']['linkage_id'] == 'connector') {
			//퍼스트몰 마켓 연동
			$this->load->library('Connector');
			$connector	= $this->connector::getInstance();
			$marketList	= $connector->getAllMarkets(true);
			return $marketList[$params['data_order']['linkage_mall_code']]['name'];
		} else {
			return "내사이트";
		}
	}

	public function export_date($params){
		return date("Y-m-d");
	}

	public function shipping_method($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option	= $params['data_option'];
		$data_package	= $params['data_package'];
		$shipping_method = $data_shipping['shopping_method_msg'];
		
		if($data_shipping['old_shipping_seq'] == $data_shipping['shipping_seq']){
			$shipping_method = "(상동)";
		}

		return $shipping_method;
	}

	public function delivery_company($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		foreach($data_shipping['couriers'] as $data_couriers){
			if( !$delivery_compay ) $delivery_compay	= $data_couriers['company'];
		}
		return $delivery_compay;
	}

	public function delivery_number($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		return '';
	}

	public function recipient_info($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		if($data_order['international'] == 'international') {
			if( $data_order['international_postcode'] ){
				$result[] = '('.str_replace('-','',$data_order['international_postcode']).')';
			}
			if(!empty($data_order['international_country']) || !empty($data_order['international_address'])) {
				$result[] = $data_order['international_country'];
				$result[] = $data_order['international_town_city'];
				$result[] = $data_order['international_county'];
				$result[] = $data_order['international_address'];
			}
		} else {
			if( $data_order['recipient_zipcode'] ){
				$result[] = '('.str_replace('-','',$data_order['recipient_zipcode']).')';
			}
			if($data_order['recipient_address_type'] == 'street' && $data_order['recipient_address_street'] ){
				$result[] = $data_order['recipient_address_street'];
			}else if( $data_order['recipient_address'] ){
				$result[] = $data_order['recipient_address'];
			}
			if( $data_order['recipient_address_detail'] ){
				$result[] = $data_order['recipient_address_detail'];
			}
		}
		
		if($result) $str_result = implode(' ',$result);
		return $str_result;
	}

	public function recipient_zipcode($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		
		if($data_order['international'] == 'international') {
			$result = str_replace('-','',$data_order['international_postcode']);
		} else {
			$result = str_replace('-','',$data_order['recipient_zipcode']);
		}
		
		return $result;
	}

	public function recipient_address_all($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result = array();
		if($data_order['international'] == 'international') {
			if(!empty($data_order['international_country']) || !empty($data_order['international_address'])) {
				$result[] = $data_order['international_country'];
				$result[] = $data_order['international_town_city'];
				$result[] = $data_order['international_county'];
				$result[] = $data_order['international_address'];
			}
		} else if($data_order['recipient_address']){
			$result[] = $data_order['recipient_address'];
			$result[] = $data_order['recipient_address_detail'];
		}
		
		$str_result = implode(' ',$result);
		
		return $str_result;
	}

	public function recipient_address_street_all($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		
		$result = array();
		if($data_order['international'] == 'international') {
			if(!empty($data_order['international_country']) || !empty($data_order['international_address'])) {
				$result[] = $data_order['international_country'];
				$result[] = $data_order['international_town_city'];
				$result[] = $data_order['international_county'];
				$result[] = $data_order['international_address'];
			}
		} else if($data_order['recipient_address_street']){
			$result[] = $data_order['recipient_address_street'];
			$result[] = $data_order['recipient_address_detail'];
		}
		$str_result = implode(' ',$result);
		return $str_result;
	}

	public function shipping_cost($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option	= $params['data_option'];
		$data_package	= $params['data_package'];

		if($data_shipping['old_shipping_seq'] == $data_shipping['shipping_seq']){
			$data_shipping['shipping_cost'] = "(상동)";
		}else{
			$data_shipping['shipping_cost'] = get_krw_currency($data_shipping['shipping_cost']);
		}
		return $data_shipping['shipping_cost'];
	}

	public function goods_seq($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$goods_seq = $data_shipping['items'][$data_option['item_seq']]['goods_seq'];
		if($data_package['goods_seq']) $goods_seq = $data_package['goods_seq'];
		return $goods_seq;
	}

	public function hscode($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$hscode = $data_shipping['items'][$data_option['item_seq']]['hscode'];
		if($data_package['hscode']) $hscode = $data_package['hscode'];
		return $hscode;
	}

	public function goods_code($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$goods_code = $data_shipping['items'][$data_option['item_seq']]['goods_code'];
		$option_code = '';
		for($i=1;$i<=5;$i++) if( $data_option['optioncode'.$i] ) $option_code .= $data_option['optioncode'.$i];
		if( $data_option['suboption_code'] ) $option_code =  $data_option['suboption_code'];
		$goods_code .= $option_code;
		if($data_package) $goods_code = $data_package['goods_code'];
		return $goods_code;
	}

	public function npay_product_order_id($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$npay_product_order_id = $data_option['npay_product_order_id'];
		return $npay_product_order_id;
	}

	public function goods_name($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$tmp_option		= array();
		$goods_name		= $data_shipping['items'][$data_option['item_seq']]['goods_name'];
		for($i=1;$i<=5;$i++) if( $data_option['option'.$i] ) $tmp_option[] = $data_option['option'.$i];
		$option_str			= implode(',',$tmp_option);
		$str_goods_name	= $goods_name;
		if($option_str) $str_goods_name .= ' ('.$option_str.')';

		if($data_option['suboption']){
			$str_goods_name	= $goods_name;
			$str_goods_name	 .= ' ('.$data_option['title'].'/'.$data_option['suboption'].')';
		}

		if( $data_package ){
			$tmp_option		= array();
			$str_goods_name	= $data_package['goods_name'];
			for($i=1;$i<=5;$i++) if( $data_package['option'.$i] ) $tmp_option[] = $data_package['option'.$i];
			$option_str			= implode(',',$tmp_option);
			if($option_str) $str_goods_name .= ' ('.$option_str.')';
		}

		return $str_goods_name;
	}

	public function location($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result				= $data_option['location_position'];
		if($data_option['packages'])	$result = strip_tags(html_entity_decode($data_option['package_msg']));
		if( $data_package )				$result = $data_package['location'];
		return $result;
	}

	public function stock($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result				= $data_option['stock'];
		if($data_option['packages'])	$result = strip_tags(html_entity_decode($data_option['package_msg']));
		if( $data_package )				$result = $data_package['stock'];
		return $result;
	}

	public function purchase_goods_name($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result				= $data_shipping['items'][$data_option['item_seq']]['purchase_goods_name'];
		if($data_option['packages'])	$result = strip_tags(html_entity_decode($data_option['package_msg']));
		if( $data_package )				$result = $data_package['purchase_goods_name'];
		return $result;
	}

	public function supply_price($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result				= get_krw_currency($data_option['supply_price']);
		if($data_option['packages'])	$result = strip_tags(html_entity_decode($data_option['package_msg']));
		if($data_package)				$result	= get_krw_currency($data_package['supply_price']);
		return $result;
	}

	public function consumer_price($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result				= get_krw_currency($data_option['consumer_price']);
		if($data_package) $result = $data_package['consumer_price'];
		return $result;
	}

	public function price($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result				= get_krw_currency($data_option['price']);
		if($data_package) $result = $data_package['price'];
		return $result;
	}

	public function ea_price($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result				= get_krw_currency($data_option['price']*$data_option['ea']);
		if($data_package) $result = $data_package['ea_price'];
		return $result;
	}

	public function tax($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$tax = '';
		$tax_code = $data_shipping['items'][$data_option['item_seq']]['tax'];
		foreach($this->data_tax as $data_tax) if($data_tax['codecd'] == $tax_code) $tax = $data_tax['value'];
		$result	= $tax;
		return $result;
	}

	public function subinputoption($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result = $data_option['subinputoption'];
		return $result;
	}

	public function ea($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result				= $data_option['ea'];
		if($data_package) $result = $data_option['ea']*$data_package['unit_ea'];
		return $result;
	}

	public function refund_ea($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result				= $data_option['refund_ea'];
		if($data_package) $result = $data_option['refund_ea']*$data_package['unit_ea'];
		return $result;
	}

	public function export_ea($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result				= $data_option['export_ea'];
		if($data_package) $result = $data_option['export_ea']*$data_package['unit_ea'];
		return $result;
	}

	public function request_ea($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result				= $data_option['request_ea'];
		if($data_package) $result = $data_option['request_ea']*$data_package['unit_ea'];
		return $result;
	}

	public function step($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$step = '';
		foreach($this->data_step as $step_code => $step_msg) if( $data_option['step'] ==  $step_code) $step = $step_msg;
		$result = $step;
		return $result;
	}

	public function goods_coupon_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result = get_krw_currency($data_option['goods_coupon_sale']);  //DB명과 변수명이 달라서 엑셀노출불가 오류 해결 18.09.06 kmj
		return $result;
	}

	public function promotion_code_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result = get_krw_currency($data_option['promotion_code_sale']);
		return $result;
	}

	public function event_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result = get_krw_currency($data_option['event_sale']);
		return $result;
	}

	public function multi_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result = get_krw_currency($data_option['multi_sale']);
		return $result;
	}

	public function member_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result = get_krw_currency($data_option['member_sale'] * $data_option['ea']);
		return $result;
	}

	public function mobile_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result = get_krw_currency($data_option['mobile_sale']);
		return $result;
	}

	public function fblike_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result = get_krw_currency($data_option['fblike_sale']);
		return $result;
	}

	public function referer_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result = get_krw_currency($data_option['referer_sale']);
		return $result;
	}

	public function shipping_coupon_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result = get_krw_currency($data_shipping['shipping_coupon_sale']);
		return $result;
	}
	public function shipping_promotion_code_sale($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$result = get_krw_currency($data_shipping['shipping_promotion_code_sale']);
		return $result;
	}
	public function payment($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		foreach( $this->data_paymethod as $data_paymethod) if( $data_paymethod['codecd'] == $data_order['payment'] ) return $data_paymethod['value'];
	}

	public function reserve($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result = get_krw_currency($data_option['reserve']);
		return $result;
	}

	public function point($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];

		$result = get_krw_currency($data_option['point']);
		return $result;
	}

	public function memo($params) {
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];

		if ( $data_order['each_msg_yn'] == 'N' ) {
			return $data_order['memo'];
		} else {
			return $data_option['ship_message'];
		}
	}
	public function shipping_group($params){
		$data_shipping	= $params['data_shipping'];
		if($data_shipping['shipping_group']){
			$_tmp_shipping_group	= explode("_",$data_shipping['shipping_group']);
			$shipping_group			= $this->data_shipping_group_name[$_tmp_shipping_group[0]]."(".$_tmp_shipping_group[0].")";
		}else{
			$shipping_group			= '';
		}
		return $shipping_group;
	}

	public function shipping_pay_type($params){
		//배송비결제타입(선불:prepayed,착불postpaid:,무료:free)
		$_arr_shipping_paytype = array("prepay"=>"선불","prepayed"=>"선불","postpaid"=>"착불","free"=>"선불");
		$data_shipping	= $params['data_shipping'];

		if($data_shipping['old_shipping_seq'] == $data_shipping['shipping_seq']){
			$shipping_type = "(상동)";
		}else{
			$shipping_type = $_arr_shipping_paytype[$data_shipping['shipping_type']];
		}

		return $shipping_type;
	}

	public function option($option){
		$data_order	= $this->data_order['ordershipping'];
		$options = array();
		$keyName = substr($option, 6);
		foreach($data_order as $data_option){
			foreach($data_option['options'] as $op){
				$options[$option] = $op[$keyName];
			}
		}
		return $options;
	}

	public function emoney($params) {
		$data_option	= $params['data_option'];
		$emoney			= str_replace(',','',get_currency_price($data_option['emoney_sale_unit']*$data_option['ea']+$data_option['emoney_sale_rest']));
		return $emoney;
	}

	public function cash($params) {
		$data_option	= $params['data_option'];
		$emoney			= str_replace(',','',get_currency_price($data_option['cash_sale_unit']*$data_option['ea']+$data_option['cash_sale_rest']));
		return $emoney;
	}

	public function enuri($params) {
		$data_option	= $params['data_option'];
		$emoney			= str_replace(',','',get_currency_price($data_option['enuri_sale_unit']*$data_option['ea']+$data_option['enuri_sale_rest']));
		return $emoney;
	}
	
	public function delivery_emoney($params) {
		$data_shipping	= $params['data_shipping'];
		if($data_shipping['old_shipping_seq'] == $data_shipping['shipping_seq']){
			$return = "(상동)";
		}else{
			$return	= str_replace(',','',get_currency_price($data_shipping['emoney_sale_unit']+$data_shipping['emoney_sale_rest']));
		}
		return $return;
	}

	public function delivery_cash($params) {
		$data_shipping	= $params['data_shipping'];
		if($data_shipping['old_shipping_seq'] == $data_shipping['shipping_seq']){
			$return		= "(상동)";
		}else{
			$return		= str_replace(',','',get_currency_price($data_shipping['cash_sale_unit']+$data_shipping['cash_sale_rest']));
		}
		return $return;
	}

	public function delivery_enuri($params) {
		$data_shipping	= $params['data_shipping'];
		if($data_shipping['old_shipping_seq'] == $data_shipping['shipping_seq']){
			$return		 = "(상동)";
		}else{
			$return		= str_replace(',','',get_currency_price($data_shipping['enuri_sale_unit']+$data_shipping['enuri_sale_rest']));
		}
		return $return;
	}

	//주문서쿠폰
	public function ordersheet_sale($params)
	{
		$data_order		= $params['data_order'];
		$data_shipping	= $params['data_shipping'];
		if($data_shipping['old_shipping_seq'] == $data_shipping['shipping_seq']){
			$result = "(상동)";
		}else{
			$result = get_krw_currency($data_order['ordersheet_sale']);
		}
		return $result;
	}
	
	/**
	 * 톡체크아웃 관련 주문/상품주문 번호
	 */
	public function talkbuy_product_order_id($params){
		$data_order		= $params['data_order'];
		$data_member	= $params['data_member'];
		$data_shipping	= $params['data_shipping'];
		$data_option		= $params['data_option'];
		$data_package	= $params['data_package'];
		$talkbuy_product_order_id = $data_option['talkbuy_product_order_id'];
		return $talkbuy_product_order_id;
	}

}