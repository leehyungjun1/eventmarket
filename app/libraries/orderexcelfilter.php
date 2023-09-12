<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class orderexcelfilter extends CI_Model
{
	var $data_order					= '';
	var $data_provider				= '';
	var $data_linkage				= '';
	var $done						= array();
	var $data_paymethod				= array(); // orderexcel_pay_method
	var $data_tax					= array(); // orderexcel_tax
	var $data_step					= array(); // config:step
	var $data_shipping_group_name	= array();	//shipping group name
	var $shippinggroup_cnt			= 0;
	var $only_real				 = false;
	
	public function check_stock_order()
	{
		$data_order		= $this->data_order;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				if( !$data_option['package_yn']=='n' && $data_option['stock'] === '미매칭' ) return true;
				else foreach($this->packages($data_option,'stock') as $data_package) if( $data_package === '미매칭' ) return true;
				foreach($data_option['suboptions'] as $data_suboption){
					if( !$data_suboption['package_yn']=='n' && $data_suboption['stock'] === '미매칭' ) return true;
					else foreach($this->packages($data_suboption,'stock') as $data_package) if( $data_package === '미매칭' ) return true;
				}
			}
		}
		return false;
	}
	
	public function check_package_option()
	{
		$data_order		= $this->data_order;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				foreach($this->packages($data_option) as $data_package) return true;
				foreach($data_option['suboptions'] as $data_suboption){
					foreach($this->packages($data_suboption) as $data_package) return true;
				}
			}
		}
		return false;
	}
	
	public function packages($data_option, $field='', $str='')
	{
		foreach($data_option['packages'] as $data_package){
			if( !$data_package ) continue;
			if( $field == 'goods_name' ){
				$tmp_option = array();
				$goods_name = $data_package['goods_name'];
				for($i=1;$i<=5;$i++) if( $data_package['option'.$i] ) $tmp_option[] = $data_package['option'.$i];
				$str_option = implode(',',$tmp_option);
				if($str_option) $goods_name .= ' ('.$str_option.')';
				$result[] = $goods_name;
			}else if( $field ){
				$result[] = $data_package[$field];
			}else{
				$result[] = $str;
			}
		}
		return $result;
	}
	
	public function export_item_seq()
	{
		$data_order		= $this->data_order;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				$export_item_seq = $data_option[ 'export_item_seq'];
				$result[] = $export_item_seq;
				foreach($this->packages($data_option,'package_option_seq') as $data_package) $result[] = $export_item_seq.'-P'.$data_package;
				foreach($data_option['suboptions'] as $data_suboption){
					$export_item_seq = $data_suboption[ 'export_item_seq'];
					$result[] = $export_item_seq;
					foreach($this->packages($data_suboption,'package_suboption_seq') as $data_package) $result[] = $export_item_seq.'-P'.$data_package;
				}
			}
		}
		return $result;
	}
	
	public function shipping_provider(){
		$data_order		= $this->data_order;
		$data_provider	= $this->data_provider;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				$provider_name = $data_provider[$data_shipping['provider_seq']]['provider_name'];
				$provider_name = $data_shipping['shipping_provider'];
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $provider_name;
				}
				foreach($this->packages($data_option) as $data_package) {
					$result[] = $provider_name;
				}
				foreach($data_option['suboptions'] as $data_suboption){
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $provider_name;
					}
					foreach($this->packages($data_suboption) as $data_package) {
						$result[] = $provider_name;
					}
				}
			}
		}
		return $result;
	}
	
	public function provider_name(){
		$data_order		= $this->data_order;
		$data_provider	= $this->data_provider;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				$provider_name = $data_shipping['items'][$data_option['item_seq']]['provider_name'];
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
				   $result[] = $provider_name;
				}
				foreach($this->packages($data_option) as $data_package) {
					$result[] = $provider_name;
				}
				foreach($data_option['suboptions'] as $data_suboption){
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $provider_name;
					}
					foreach($this->packages($data_suboption) as $data_package) {
						$result[] = $provider_name;
					}
				}
			}
		}
		return $result;
	}
	
	public function shipping_seq(){
		$data_order		= $this->data_order;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				$shipping_seq = $data_shipping['shipping_seq'];
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $shipping_seq;
				}
				foreach($this->packages($data_option) as $data_package) {
					$result[] = $shipping_seq;
				}
				foreach($data_option['suboptions'] as $data_suboption){
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $shipping_seq;
					}
					foreach($this->packages($data_suboption) as $data_package) {
						$result[] = $shipping_seq;
					}
				}
			}
		}
		return $result;
	}
	
	public function order_seq_shipping_seq(){
		$data_order		= $this->data_order;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				$order_seq_shipping_seq = $data_order['order']['order_seq'].'-'.$data_shipping['shipping_seq'];
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $order_seq_shipping_seq;
				}
				
				foreach($this->packages($data_option) as $data_package) {
					$result[] = $order_seq_shipping_seq;
				}
				
				foreach($data_option['suboptions'] as $data_suboption){
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $order_seq_shipping_seq;
					}
					foreach($this->packages($data_suboption) as $data_package) {
						$result[] = $order_seq_shipping_seq;
					}
				}
			}
		}
		return $result;
	}
	
	public function userid(){
		$data_order		= $this->data_order;
		return $data_order['member']['userid'];
	}
	
	public function linkage_mallname(){
		$data_order		= $this->data_order;
		if($data_order['order']['linkage_id'] == 'connector') {
			//퍼스트몰 마켓 연동
			$this->load->library('Connector');
			$connector	= $this->connector::getInstance();
			$marketList	= $connector->getAllMarkets(true);
			return $marketList[$data_order['order']['linkage_mall_code']]['name'];
		} else {
			return "내사이트";
		}
	}
	
	public function export_date(){
		return date("Y-m-d");
	}
	
	//받는방법
	public function shipping_method(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			$shipping_seq	= "";
			$_filter_k		= 0;
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					if($shipping_seq == $data_option['shipping_seq']){
						$shipping_method = '(상동)';
					} else {
						$shipping_method = $data_shipping['shopping_method_msg'];
					}
					$result[] = $shipping_method;
				}
				foreach ($this->packages($data_option) as $k => $data_package) {
					if($k == 0 && $this->only_real == 'REAL' && $data_option['packages']){
						$result[] =  $data_shipping['shopping_method_msg'];
					} else {
						$result[] = '(상동)';
					}
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = '(상동)';
					}
					foreach($this->packages($data_suboption) as $data_package) {
						$result[] = '(상동)';
					}
					
				}
				$shipping_seq = $data_option['shipping_seq'];
				$_filter_k++;
			}
		}
		return $result;
	}
	
	//택배사
	public function delivery_company(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			$delivery_compay = '';
			foreach($data_shipping['couriers'] as $data_couriers){
				if (!$delivery_compay) {
					$delivery_compay	= $data_couriers['company'];
				}
			}
			$shipping_seq	= "";
			foreach ($data_shipping['options'] as $data_option) {
				if ($shipping_seq == $data_option['shipping_seq']) {
					$delivery_compay = '(상동)';
				}
				$result[] = $delivery_compay;
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = '(상동)';
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = '(상동)';
					}
					
					foreach($this->packages($data_suboption) as $data_package) {
						$result[] = '(상동)';
					}
				}
				$shipping_seq = $data_option['shipping_seq'];
			}
		}
		return $result;
	}
	
	//운송장번호
	public function delivery_number(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			$delivery_compay = '';
			foreach ($data_shipping['couriers'] as $data_couriers) {
				if (!$delivery_compay) {
					$delivery_compay = $data_couriers['company'];
				}
			}
			if (!$delivery_compay) {
				$result[] = "";
			} else {
				$shipping_seq	= "";
				foreach ($data_shipping['options'] as $data_option) {
					if($shipping_seq == $data_option['shipping_seq']){
						$delivery_number = '(상동)';
					}else{
						$delivery_number = '입력하세요';
					}
					$result[] = $delivery_number;
					foreach ($this->packages($data_option) as $data_package) {
						$result[] = '(상동)';
					}
					
					foreach ($data_option['suboptions'] as $data_suboption) {
						if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
							$result[] = '(상동)';
						}
						foreach ($this->packages($data_suboption) as $data_package) {
							$result[] = '(상동)';
						}
					}
					$shipping_seq = $data_option['shipping_seq'];
				}
			}
		}
		return $result;
	}
	
	public function recipient_info(){
		$data_order		= $this->data_order;
		
		if($data_order['order']['international'] == 'international') {
			if( $data_order['order']['international_postcode'] ){
				$result[] = '('.str_replace('-','',$data_order['order']['international_postcode']).')';
			}
			if(!empty($data_order['order']['international_country']) || !empty($data_order['order']['international_address'])) {
				$result[] = $data_order['order']['international_country'];
				$result[] = $data_order['order']['international_town_city'];
				$result[] = $data_order['order']['international_county'];
				$result[] = $data_order['order']['international_address'];
			}
		} else {
			if( $data_order['order']['recipient_zipcode'] ){
				$result[] = '('.str_replace('-','',$data_order['order']['recipient_zipcode']).')';
			}
			if($data_order['order']['recipient_address_type'] == 'street' && $data_order['order']['recipient_address_street'] ){
				$result[] = $data_order['order']['recipient_address_street'];
			}else if( $data_order['order']['recipient_address'] ){
				$result[] = $data_order['order']['recipient_address'];
			}
			if( $data_order['order']['recipient_address_detail'] ){
				$result[] = $data_order['order']['recipient_address_detail'];
			}
		}
		
		if($result) $str_result = implode(' ',$result);
		
		return $str_result;
	}
	
	public function recipient_zipcode(){
		$data_order		= $this->data_order;
		if($data_order['order']['international'] == 'international') {
			$result = str_replace('-','',$data_order['order']['international_postcode']);
		} else {
			$result = str_replace('-','',$data_order['order']['recipient_zipcode']);
		}
		return $result;
	}
	
	public function recipient_address_all(){
		$data_order		= $this->data_order;
		
		$result = array();
		if($data_order['order']['international'] == 'international') {
			if(!empty($data_order['order']['international_country']) || !empty($data_order['order']['international_address'])) {
				$result[] = $data_order['order']['international_country'];
				$result[] = $data_order['order']['international_town_city'];
				$result[] = $data_order['order']['international_county'];
				$result[] = $data_order['order']['international_address'];
			}
		} else if($data_order['order']['recipient_address']){
			$result[] = $data_order['order']['recipient_address'];
			$result[] = $data_order['order']['recipient_address_detail'];
		}
		
		$str_result = implode(' ',$result);
		
		return $str_result;
	}
	
	public function recipient_address_street_all(){
		$data_order		= $this->data_order;
		
		$result = array();
		if($data_order['order']['international'] == 'international') {
			if(!empty($data_order['order']['international_country']) || !empty($data_order['order']['international_address'])) {
				$result[] = $data_order['order']['international_country'];
				$result[] = $data_order['order']['international_town_city'];
				$result[] = $data_order['order']['international_county'];
				$result[] = $data_order['order']['international_address'];
			}
		} else if($data_order['order']['recipient_address_street']){
			$result[] = $data_order['order']['recipient_address_street'];
			$result[] = $data_order['order']['recipient_address_detail'];
		}
		$str_result = implode(' ',$result);
		return $str_result;
	}
	
	//배송비
	public function shipping_cost(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			$shipping_seq	= "";
			$_filter_k		= 0;
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					if ($shipping_seq == $data_option['shipping_seq']) {
						$shipping_cost = '(상동)';
					}else{
						$shipping_cost = get_krw_currency($data_shipping['shipping_cost']);
					}
					$result[] = $shipping_cost;
				}
				foreach ($this->packages($data_option) as $k => $data_package) {
					if($k == 0 && $this->only_real == 'REAL' && $data_option['packages']){
						$result[] =  get_krw_currency($data_shipping['shipping_cost']);
					} else {
						$result[] = '(상동)';
					}
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = '(상동)';
					}
					foreach($this->packages($data_suboption) as $data_package) {
						$result[] = '(상동)';
					}
				}
				$shipping_seq = $data_option['shipping_seq'];
				$_filter_k++;
			}
		}
		return $result;
	}
	
	public function goods_seq(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$goods_seq  = $data_shipping['items'][$data_option['item_seq']]['goods_seq'];
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[]   = $goods_seq;
				}
				foreach ($this->packages($data_option,'goods_seq') as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $goods_seq;
					}
					foreach ($this->packages($data_suboption,'goods_seq') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function hscode(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$hscode	 = $data_shipping['items'][$data_option['item_seq']]['hscode'];
				$result[]   = $hscode;
				foreach ($this->packages($data_option,'hscode') as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $hscode;
					}
					foreach($this->packages($data_suboption,'hscode') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function goods_code(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$goods_code	 = $data_shipping['items'][$data_option['item_seq']]['goods_code'];
				$option_code	= $data_option['optioncode1'] . $data_option['optioncode2'] . $data_option['optioncode3'] . $data_option['optioncode4'] . $data_option['optioncode5'];
				$result[]	   = $goods_code.$option_code;
				foreach ($this->packages($data_option,'goods_code') as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$option_code = $data_suboption['suboption_code'];
						$result[] = $goods_code.$option_code;
					}
					foreach ($this->packages($data_suboption,'goods_code') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function npay_product_order_id(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$npay_product_order_id  = $data_option['npay_product_order_id'];
				$result[]			   = $npay_product_order_id;
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $npay_product_order_id;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] =$npay_product_order_id;
					}
					foreach ($this->packages($data_suboption) as $data_package) {
						$result[] = $npay_product_order_id;
					}
				}
			}
		}
		return $result;
	}
	
	public function goods_name(){
		$data_order = $this->data_order;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				$tmp_option		= array();
				$goods_name		= $data_shipping['items'][$data_option['item_seq']]['goods_name'];
				
				for ($i=1;$i<=5;$i++) {
					if ($data_option['option'.$i]) {
						$tmp_option[] = $data_option['option'.$i];
					}
				}
				
				$option_str	 = implode(',',$tmp_option);
				$str_goods_name	= $goods_name;
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					if ($option_str) {
						$str_goods_name .= ' ('.$option_str.')';
					}
					$result[] = trim($str_goods_name);
				}
				
				foreach ($this->packages($data_option,'goods_name') as $data_package) {
					$result[] = $data_package;
				}
				
				foreach ($data_option['suboptions'] as $k => $data_suboption) {
					$str_goods_name	= $goods_name;
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						if ($data_suboption['suboption']) {
							$str_goods_name	 .= ' ('.$data_suboption['title'].'/'.$data_suboption['suboption'].')';
							$result[] = trim($str_goods_name);
						}
					}
					
					foreach ($this->packages($data_suboption, 'goods_name') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function location(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($data_option['packages']) {
					$result[] = strip_tags(html_entity_decode($data_option['package_msg']));
				} else {
					$result[] = $data_option['location_position'];
				}
				foreach ($this->packages($data_option,'location_position') as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						if ($data_suboption['packages']) {
							$result[] = strip_tags(html_entity_decode($data_suboption['package_msg']));
						} else {
							$result[] = $data_suboption['location_position'];
						}
					}
					
					foreach ($this->packages($data_suboption, 'location') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function stock(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					if ($data_option['package_msg']) {
						$result[] = strip_tags(html_entity_decode($data_option['package_msg']));
					}else{
						$result[] = $data_option['stock'];
					}
				}
				
				foreach ($this->packages($data_option,'stock') as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						if ($data_suboption['package_msg']) {
							$result[] = strip_tags(html_entity_decode($data_suboption['package_msg']));
						}else{
							$result[] = $data_suboption['stock'];
						}
					}
					foreach ($this->packages($data_suboption,'stock') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function purchase_goods_name(){
		$data_order		= $this->data_order;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				$purchase_goods_name	= $data_shipping['items'][$data_option['item_seq']]['purchase_goods_name'];
				if( $data_option['package_msg'] ){
					$purchase_goods_name = strip_tags(html_entity_decode($data_option['package_msg']));
				}
				$result[] = $purchase_goods_name;
				foreach($this->packages($data_option,'purchase_goods_name') as $data_package) $result[] = $data_package;
				
				foreach($data_option['suboptions'] as $data_suboption){
					$purchase_goods_name	= $data_shipping['items'][$data_option['item_seq']]['purchase_goods_name'];;
					if( $data_suboption['package_msg'] ){
						$purchase_goods_name = strip_tags(html_entity_decode($data_option['package_msg']));
					}
					$result[] = $purchase_goods_name;
					foreach($this->packages($data_suboption,'purchase_goods_name') as $data_package) $result[] = $data_package;
				}
			}
		}
		return $result;
	}
	
	public function supply_price(){
		$data_order		= $this->data_order;
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					if( $data_option['package_msg'] ){
						$result[] = strip_tags(html_entity_decode($data_option['package_msg']));
					}else{
						$result[] = get_krw_currency($data_option['supply_price']);
					}
				}
				foreach($this->packages($data_option,'supply_price') as $data_package) $result[] = $data_package;
				foreach($data_option['suboptions'] as $data_suboption){
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						if( $data_suboption['package_msg'] ){
							$result[] = strip_tags(html_entity_decode($data_suboption['package_msg']));
						}else{
							$result[] = get_krw_currency($data_suboption['supply_price']);
						}
					}
					foreach($this->packages($data_suboption,'supply_price') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function consumer_price(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = get_krw_currency($data_option['consumer_price']);
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = get_krw_currency($data_suboption['consumer_price']);
					}
					foreach ($this->packages($data_suboption, 'consumer_price') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function price(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = get_krw_currency($data_option['price']);
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = get_krw_currency($data_suboption['price']);
					}
					foreach ($this->packages($data_suboption, 'price') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function ea_price(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = get_krw_currency($data_option['price']*$data_option['ea']);
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = get_krw_currency($data_suboption['price']*$data_suboption['ea']);
					}
					foreach ($this->packages($data_suboption, 'ea_price') as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	public function tax(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$tax = '';
				$tax_code = $data_shipping['items'][$data_option['item_seq']]['tax'];
				foreach ($this->data_tax as $data_tax) {
					if ($data_tax['codecd'] == $tax_code) {
						$tax = $data_tax['value'];
					}
				}
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $tax;
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = $tax;
				}
			}
		}
		return $result;
	}
	
	public function subinputoption(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$result[] = $data_option['subinputoption'];
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = $data_option['subinputoption'];
				}
			}
		}
		return $result;
	}
	
	public function ea(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $data_option['ea'];
				}
				foreach ($this->packages($data_option,'unit_ea') as $data_package) {
					$result[] = $data_package*$data_option['ea'];
				}
				foreach ($data_option['suboptions'] as $data_suboption){
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $data_suboption['ea'];
					}
					foreach ($this->packages($data_suboption,'unit_ea') as $data_package) {
						$result[] = $data_package*$data_suboption['ea'];
					}
				}
			}
		}
		return $result;
	}
	
	public function refund_ea(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $data_option['refund_ea'];
				}
				foreach ($this->packages($data_option,'unit_ea') as $data_package) {
					$result[] = $data_package* $data_option['refund_ea'];
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $data_suboption['refund_ea'];
					}
					foreach ($this->packages($data_suboption,'unit_ea') as $data_package) {
						$result[] = $data_package*$data_suboption['refund_ea'];
					}
				}
			}
		}
		return $result;
	}
	
	public function export_ea(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $data_option['export_ea'];
				}
				foreach ($this->packages($data_option,'unit_ea') as $data_package) {
					$result[] = $data_package*$data_option['export_ea'];
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $data_suboption['export_ea'];
					}
					foreach ($this->packages($data_suboption,'unit_ea') as $data_package) {
						$result[] = $data_package*$data_option['export_ea'];
					}
				}
			}
		}
		return $result;
	}
	
	public function request_ea(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $data_option['request_ea'];
				}
				foreach ($this->packages($data_option,'unit_ea') as $data_package) {
					$result[] = $data_package*$data_option['request_ea'];
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $data_suboption['request_ea'];
					}
					foreach ($this->packages($data_suboption,'unit_ea') as $data_package) {
						$result[] = $data_package*$data_suboption['request_ea'];
					}
				}
			}
		}
		return $result;
	}
	
	public function step(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$step = '';
				foreach ($this->data_step as $step_code => $step_msg) {
					if ($data_option['step'] ==  $step_code) {
						$step = $step_msg;
					}
				}
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $step;
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $step;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$step = '';
					foreach ($this->data_step as $step_code => $step_msg) {
						if ($data_suboption['step'] ==  $step_code) {
							$step = $step_msg;
						}
					}
					$result[] = $step;
				}
			}
		}
		return $result;
	}
	
	// 이벤트 할인 추가 :: 2018-08-01 pjw
	public function event_sale(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $data_option['event_sale'];
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = $data_suboption['event_sale'];
					}
					foreach ($this->packages($data_suboption) as $data_package) {
						$result[] = $data_package;
					}
				}
			}
		}
		return $result;
	}
	
	// 복수구매 할인 추가 :: 2018-08-01 pjw
	public function multi_sale(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $data_option['multi_sale'];
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = $data_suboption['multi_sale'];
				}
			}
		}
		return $result;
	}
	
	public function goods_coupon_sale(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = get_krw_currency($data_option['goods_coupon_sale']);
				}//DB명과 변수명이 달라서 엑셀노출불가 오류 해결 18.09.06 kmj
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = get_krw_currency($data_suboption['coupon_sale']);
				}
			}
		}
		return $result;
	}
	
	public function promotion_code_sale(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = get_krw_currency($data_option['promotion_code_sale']);
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = get_krw_currency($data_suboption['promotion_code_sale']);
				}
			}
		}
		return $result;
	}
	
	public function member_sale() {
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = get_krw_currency($data_option['member_sale'] * $data_option['ea']);
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = get_krw_currency($data_suboption['member_sale']);
				}
			}
		}
		return $result;
	}
	
	public function mobile_sale(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = get_krw_currency($data_option['mobile_sale']);
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = get_krw_currency($data_suboption['mobile_sale']);
				}
			}
		}
		return $result;
	}
	
	public function fblike_sale(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = get_krw_currency($data_option['fblike_sale']);
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = get_krw_currency($data_suboption['fblike_sale']);
				}
			}
		}
		return $result;
	}
	
	public function referer_sale(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = get_krw_currency($data_option['referer_sale']);
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption){
					$result[] = get_krw_currency($data_suboption['referer_sale']);
				}
			}
		}
		return $result;
	}
	
	public function shipping_coupon_sale(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$shipping_coupon_sale = get_krw_currency($data_shipping['shipping_coupon_sale']);
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $shipping_coupon_sale;
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $shipping_coupon_sale;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = $shipping_coupon_sale;
				}
			}
		}
		return $result;
	}
	public function shipping_promotion_code_sale(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$shipping_promotion_code_sale = get_krw_currency($data_shipping['shipping_promotion_code_sale']);
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $shipping_promotion_code_sale;
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $shipping_promotion_code_sale;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$result[] = $shipping_promotion_code_sale;
				}
			}
		}
		return $result;
	}
	public function payment(){
		$data_order		= $this->data_order;
		foreach( $this->data_paymethod as $data_paymethod) if( $data_paymethod['codecd'] == $data_order['order']['payment'] ) return $data_paymethod['value'];
	}
	
	public function reserve(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$reserve = get_krw_currency($data_option['reserve']);
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $reserve;
				}
				foreach($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach($data_option['suboptions'] as $data_suboption){
					$reserve = get_krw_currency($data_suboption['reserve']);
					$result[] = $reserve;
				}
			}
		}
		return $result;
	}
	
	public function point(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$point = get_krw_currency($data_option['point']);
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $point;
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $data_package;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					$point = get_krw_currency($data_suboption['point']);
					$result[] = $point;
				}
			}
		}
		return $result;
	}
	
	public function memo() {
		$data_order		= $this->data_order;
		if ( $data_order['order']['each_msg_yn'] == 'N' ) return $data_order['order']['memo'];
		foreach($data_order['ordershipping'] as $data_shipping){
			foreach($data_shipping['options'] as $data_option){
				$result[] = $data_option['ship_message'];
			}
			
		}
		return $result;
	}
	
	//배송그룹번호
	public function shipping_group(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				if ($data_shipping['shipping_group']) {
					$_tmp_shipping_group	= explode("_",$data_shipping['shipping_group']);
					$shipping_group			= $this->data_shipping_group_name[$_tmp_shipping_group[0]]."(".$_tmp_shipping_group[0].")";
				}else{
					$shipping_group			= '';
				}
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					$result[] = $shipping_group;
				}
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $shipping_group;
				}
				foreach ($data_option['suboptions'] as $data_suboption){
					$result[] = $shipping_group;
				}
			}
		}
		return $result;
	}
	
	//배송유형:배송비결제타입
	public function shipping_pay_type(){
		$data_order = $this->data_order;
		//배송비결제타입(선불:prepayed,착불postpaid:,무료:free)
		$_arr_shipping_paytype = array("prepay"=>"선불", "prepayed"=>"선불", "postpaid"=>"착불", "free"=>"선불");
		foreach ($data_order['ordershipping'] as $data_shipping) {
			if (!$shipping_type) {
			    $shipping_type = $data_shipping['shipping_set_name'];
			}
			$shipping_seq	= "";
			$_filter_k	   = 0;
			foreach ($data_shipping['options'] as $data_option) {
				if ($this->only_real != 'REAL' || !$data_option['packages']) {
					if ($shipping_seq == $data_option['shipping_seq']) {
						$shipping_type = '(상동)';
					} else {
						$shipping_type = $_arr_shipping_paytype[$data_shipping['shipping_type']];
					}
					$result[] = $shipping_type;
				}
				foreach ($this->packages($data_option) as $k => $data_package) {
					if($k == 0 && $this->only_real == 'REAL' && $data_option['packages']){
						$result[] =  $_arr_shipping_paytype[$data_shipping['shipping_type']];
					} else {
						$result[] = '(상동)';
					}
				}
				foreach ($data_option['suboptions'] as $data_suboption){
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] = '(상동)';
					}
					foreach ($this->packages($data_suboption) as $data_package) {
						$result[] = '(상동)';
					}
				}
				$shipping_seq = $data_option['shipping_seq'];
				$_filter_k++;
			}
		}
		return $result;
	}

	public function emoney(){
		return get_currency_price($this->data_order['order']['emoney'],1);
	}

	public function cash(){
		return get_currency_price($this->data_order['order']['cash'],1);
	}

	public function enuri(){
		return get_currency_price($this->data_order['order']['enuri'],1);
	}

	//주문서쿠폰
	public function ordersheet_sale()
	{
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			$result[] = get_krw_currency($data_shipping['ordersheet_sale']);
		}
		return $result;
	}

	/**
	 * 톡체크아웃 관련 주문/상품주문 번호
	 */
	public function talkbuy_product_order_id(){
		$data_order = $this->data_order;
		foreach ($data_order['ordershipping'] as $data_shipping) {
			foreach ($data_shipping['options'] as $data_option) {
				$talkbuy_product_order_id  = $data_option['talkbuy_product_order_id'];
				$result[]			   = $talkbuy_product_order_id;
				foreach ($this->packages($data_option) as $data_package) {
					$result[] = $talkbuy_product_order_id;
				}
				foreach ($data_option['suboptions'] as $data_suboption) {
					if ($this->only_real != 'REAL' || $data_suboption['package_yn'] == 'n') {
						$result[] =$talkbuy_product_order_id;
					}
					foreach ($this->packages($data_suboption) as $data_package) {
						$result[] = $talkbuy_product_order_id;
					}
				}
			}
		}

		return $result;
	}
}
