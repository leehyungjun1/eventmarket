<?php
/**
 * @author lgs
 * @version 1.0.0
 * @license copyright by GABIA_lgs
 * @since 12. 5. 31 11:19
 */

// 상품(옵션) 재고 체크 ( 리스트용 : query를 하지 않고 전부 인자로 받아서 처리 )
function check_stock_option_list($goodsinfo, $cfg, $stock, $reserve15, $reserve25, $ea, $mode = 'cart'){
	$CI =& get_instance();
	if(!$cfg ) $cfg	= config_load('order');

	// 개별세팅
	if	($goodsinfo['runout_policy']){
		$cfg['runout']			= $goodsinfo['runout_policy'];
		$cfg['ableStockLimit']	= $goodsinfo['able_stock_limit'];
	}

	if ($cfg['runout'] == 'unlimited') { // 재고와 상관없이
		return true;
	}

	$able_stock_limit = 0; // 설정한 가용재고 수
	$reservation = 0; // 출고 예약량
	if ($cfg['runout'] == 'ableStock') {
		$able_stock_limit	= (int)$cfg['ableStockLimit'];
		$reservation		= ${'reserve'.$cfg['ableStockStep']};
	}

	$sale_able_stock	= (int) $stock - (int) $reservation - (int) $able_stock_limit;
	$result_stock		= $sale_able_stock  - (int) $ea;

	if ($mode == 'cart') {
		if ($result_stock < 0) {
			return false;
		}
	} elseif ($mode == 'view') {
		if ($result_stock <= 0) {
			return false;
		}
	} elseif ($mode == 'view_stock') {
		$result_sale_able_stock = 0;
		if ($sale_able_stock > 0) {
			$result_sale_able_stock = $sale_able_stock;
		}

		return ['stock' => $result_stock,
			'able_stock' => $result_stock,
			'runout' => $cfg['runout'],
			'sale_able_stock' => $result_sale_able_stock];
	}

	return true;
}

// 상품(옵션) 재고 체크
function check_stock_option($goods_seq,$option1,$option2,$option3,$option4,$option5,$ea,$cfg='',$mode='cart') {
	$CI =& get_instance();
	if(!$cfg ) $cfg = config_load('order');

	if(!$CI->goodsmodel) $CI->load->model('goodsmodel');
	if(!$CI->ordermodel) $CI->load->model('ordermodel');

	$data_goods = $CI->goodsmodel->get_goods($goods_seq);
	if($data_goods['runout_policy']){//개별세팅
		$cfg['runout'] = $data_goods['runout_policy'];
		$cfg['ableStockLimit'] = $data_goods['able_stock_limit'];
	}

	if( $cfg['runout'] != 'unlimited' ){
		if( $cfg['runout'] == 'stock' ) $able_stock_limit = 0;
		else $able_stock_limit = (int) $cfg['ableStockLimit'];
	}else{
		return true;
	}

	$option_stock = $CI->goodsmodel->get_goods_option_stock(
		$goods_seq,
		$option1,
		$option2,
		$option3,
		$option4,
		$option5
	);

	// 출고예약량
	if( $cfg['runout'] == 'ableStock' ){
		$reservation = $CI->ordermodel->get_option_reservation(
			$cfg['ableStockStep'],
			$goods_seq,
			$option1,
			$option2,
			$option3,
			$option4,
			$option5
		);
	}

	$sale_able_stock = (int) $option_stock - (int) $reservation - (int) $able_stock_limit;
	$stock = $sale_able_stock  - (int) $ea;
	if($mode == 'cart'){
		if( $stock < 0 ) return false;
	}else if($mode == 'view'){
		if( $stock <= 0 ) return false;
	}else if($mode == 'view_stock'){
		$result_sale_able_stock = $sale_able_stock;
		if($sale_able_stock < 0) $result_sale_able_stock = 0;
		return array('stock'=>$stock,'able_stock'=>$stock,'runout'=>$cfg['runout'],'sale_able_stock'=>$result_sale_able_stock);
	}

	return true;
}

// 상품(추가옵션) 재고 체크
function check_stock_suboption($goods_seq,$title,$option,$ea,$cfg='',$mode='cart') {
	$CI =& get_instance();
	if(!$cfg ) $cfg = config_load('order');
	if(!$CI->goodsmodel) $CI->load->model('goodsmodel');
	if(!$CI->ordermodel) $CI->load->model('ordermodel');

	$data_goods = $CI->goodsmodel->get_goods($goods_seq);
	if($data_goods['runout_policy']){//개별재고
		$cfg['runout'] = $data_goods['runout_policy'];
		$cfg['ableStockLimit'] = $data_goods['able_stock_limit'];
	}

	if( $cfg['runout'] != 'unlimited' ){
		if( $cfg['runout'] == 'stock' ) $able_stock_limit = 0;
		else $able_stock_limit = (int) $cfg['ableStockLimit'];
	}else{
		return true;
	}

	$option_stock = $CI->goodsmodel->get_goods_suboption_stock(
		$goods_seq,
		$title,
		$option
	);

	// 출고예약량
	if( $cfg['runout'] == 'ableStock' ){
		$reservation = $CI->ordermodel->get_suboption_reservation(
			$cfg['ableStockStep'],
			$goods_seq,
			$title,
			$option
		);
	}

	$sale_able_stock = (int) $option_stock - (int) $reservation - (int) $able_stock_limit;
	$stock = $sale_able_stock  - (int) $ea;
	if($mode == 'cart'){
		if( $stock < 0 ) return false;
	}else if($mode == 'view'){
		if( $stock <= 0 ) return false;
	}else if($mode == 'view_stock'){
		$result_sale_able_stock = $sale_able_stock;
		if($sale_able_stock < 0) $result_sale_able_stock = 0;
		return array('stock'=>$stock,'able_stock'=>$stock,'runout'=>$cfg['runout'],'sale_able_stock'=>$result_sale_able_stock);
	}

	return true;
}

// 주문접수 메일
function send_mail_step15($order_seq){

	$CI =& get_instance();
	$CI->load->model('ordermail');

	$email_mode 		= "email";
	$CI->config_email	= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);

	## 고객/관리자에게 발송여부 확인
	$case = "order";
	$send_yn	= ($CI->config_email[$case."_user_yn"])?$CI->config_email[$case."_user_yn"]:'N';
	$send_admin_yn	= ($CI->config_email[$case."_admin_yn"])?$CI->config_email[$case."_admin_yn"]:'N';

	$CI->ordermail->set_user_yn = $send_yn;		//고객에게발송 사용여부
	if($send_admin_yn == "Y") $CI->ordermail->set_admin_yn = "Y";

	if($send_yn == "Y" || $send_admin_yn == "Y"){

		$CI->ordermail->step 			=  15;
		$CI->ordermail->order_seq 	= $order_seq;
		$CI->ordermail->from_email 	= $CI->config_basic['companyEmail'];
		$CI->ordermail->from_name 	= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
		$CI->ordermail->subject 		= $CI->config_email['order_title'];
		$data_arr 							= $CI->ordermail->get_mail_info();

		$CI->ordermail->set_contents_file();
		$CI->ordermail->set_subject($data_arr);
		$CI->ordermail->set_body($data_arr);
		if($send_yn == "Y"){
			$CI->ordermail->send();
			$CI->ordermail->send_log();
		}
		if($send_admin_yn == "Y"){ //관리자발송
			$CI->ordermail->memo		= "admin";
			$CI->ordermail->to_email	= $CI->config_email[$case."_admin_email"];
			$CI->ordermail->send();
			$CI->ordermail->send_log();
		}
	}
	return true;

}

// 결제확인 메일
function send_mail_step25($order_seq, $arr=[]){
	$CI =& get_instance();
	$CI->load->model('ordermail');

	$email_mode 						= "email";
	$CI->config_email					= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);

	## 고객/관리자에게 발송여부 확인
	$case = "settle";
	$send_yn	= ($CI->config_email[$case."_user_yn"])?$CI->config_email[$case."_user_yn"]:'N';
	$send_admin_yn	= ($CI->config_email[$case."_admin_yn"])?$CI->config_email[$case."_admin_yn"]:'N';

	/**
	 * 선물하기 주문 배송지 
	 * 		주소미등록시 : (입점)관리자 미발송 regist_address=false
	 * 		주소최초 등록 시 : 주문자 미발송 regist_address=true
	 */
	if($arr['label'] == 'present') {
		if($arr['regist_address']) {
			// 배송지 등록
			$send_yn = "N";
		} else {
			// 배송지 미등록
			$send_admin_yn = "N";
		}
	}

	// 모두 미발송일 때 return;
	if ($send_yn == 'N' && $send_admin_yn == 'N') {
		return;
	}

	$CI->ordermail->set_user_yn = $send_yn;		//고객에게발송 사용여부
	$CI->ordermail->set_admin_yn = $send_admin_yn;

	$site = explode("/",uri_string());
	if($site[0] == "admin" || $site[0] == "selleradmin"){

		$order				= $CI->ordermodel->get_order($order_seq);
		$order_shippings	= $CI->ordermodel->get_shipping($order_seq);
		$order['coupon_sale']					= $order_shippings['shipping_coupon_sale'];
		$order['shipping_promotion_code_sale']	= $order_shippings['shipping_promotion_code_sale'];
		$order['mstep']							= $arr_step[$order['step']];
		$order['mpayment']						= $arr_payment[$order['payment']];

		$items 		= $CI->ordermodel->get_item($order_seq);
		$data_opt 	= $CI->ordermodel->get_item_option($order_seq);
		$data_sub 	= $CI->ordermodel->get_item_suboption($order_seq);

		if($order['international']=='international'){
			$order['shipping_cost'] = $order['international_cost'];
		}

		$order['goods_shipping_cost'] = 0;
		foreach($items as $key=>$item){
			$order['goods_shipping_cost']	+= $item['goods_shipping_cost'];
		}

		foreach($data_opt as $data){
			$goods[$data['item_option_seq']]['goods_name'] = $data['goods_name'];
			$goods[$data['item_option_seq']]['ea'] += $data['ea'];
			$goods[$data['item_option_seq']]['price'] += $data['price']*$data['ea'];

			$options_arr	= get_options_print_array($data, ':');
			if	($options_arr)
				$goods[$data['item_option_seq']]['options']	= implode(' / ', $options_arr);
			unset($options_arr);

			// 값어치
			if	($data['goods_kind'] == 'coupon' && $data['coupon_input'] > 0){
				if	($data['socialcp_input_type'] == 'pass')
					$goods[$data['item_option_seq']]['options']	.= ' ['.number_format($data['coupon_input']).'회]';
				else
					$goods[$data['item_option_seq']]['options']	.= ' ['.get_currency_price($data['coupon_input'],3).']';
			}

			//promotion sale
			$order['goods_coupon_sale']			+= $data['coupon_sale'];
			$order['member_sale']				+= $data['member_sale']*$data['ea'];
			$order['fblike_sale']				+= $data['fblike_sale'];
			$order['referer_sale']				+= $data['referer_sale'];
			$order['goods_mobile_sale']			+= $data['mobile_sale'];
			$order['goods_fblike_sale']			+= $data['fblike_sale'];
			$order['goods_promotion_code_sale']	+= $data['promotion_code_sale'];

			$order['price'] += $data['price']*$data['ea'];
			$order['reserve'] 	+= $data['reserve']*$data['ea'];
			$order['point'] 	+= $data['point']*$data['ea'];

			$goods[$data['item_option_seq']]['inputs']	= 	$CI->ordermodel->get_input_for_option($data['item_seq'], $data['item_option_seq']);
		}

		if($data_sub) foreach($data_sub as $data){
			//promotion sale
			$order['goods_coupon_sale']			+= $data['coupon_sale'];
			$order['member_sale']				+= $data['member_sale']*$data['ea'];
			$order['fblike_sale']				+= $data['fblike_sale'];
			$order['referer_sale']				+= $data['referer_sale'];
			$order['goods_mobile_sale']			+= $data['mobile_sale'];
			$order['goods_fblike_sale']			+= $data['fblike_sale'];
			$order['goods_promotion_code_sale']	+= $data['promotion_code_sale'];

			$order['price']						+= $data['price']*$data['ea'];
			$order['goods_price']				+= $data['price']*$data['ea'];
			$data['price']						= $data['price'] * $data['ea'];
			$goods[$data['item_option_seq']]['sub'][]	= $data;
		}

		if( $order['recipient_address_street'] && $order['recipient_address_type'] != 'zibun' ) $order['recipient_address'] = $order['recipient_address_street'];

		$file_path	= "../../data/email/".get_lang(true)."/settle.html";
		$CI->template->assign(array('order'=>$order,'goods'=>$goods));

		//주문상품 정보추출
		$CI->ordermail->step 			=  25;
		$CI->ordermail->order_seq 	= $order_seq;
		$CI->ordermail->from_email 	= $CI->config_basic['companyEmail'];
		$CI->ordermail->from_name 	= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
		$CI->ordermail->subject 		= $CI->config_email['settle_title'];
		$data_arr 							= $CI->ordermail->get_mail_info();
		$CI->template->assign($data_arr);

		$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";
		$CI->template->define(array('tpl'=>$file_path));
		$out = $CI->template->fetch("tpl");

		$email = config_load('email');
		$email['settle_skin'] = $out;
		$email['member_seq'] = $order['member_seq'];
		$email['shopName']		= $CI->config_basic['shopName'];
		$email['ordno']				= $order['order_seq'];
		$email['user_name']	= $order['order_user_name'];

		saveMail($order['order_email'], 'settle', '', $email);
	}else{
		$CI->ordermail->step 			=  25;
		$CI->ordermail->order_seq 	= $order_seq;
		$CI->ordermail->from_email 	= $CI->config_basic['companyEmail'];
		$CI->ordermail->from_name 	= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
		$CI->ordermail->subject 		= $CI->config_email['settle_title'];
		$data_arr 							= $CI->ordermail->get_mail_info();

		$CI->ordermail->set_contents_file();
		$CI->ordermail->set_subject($data_arr);
		$CI->ordermail->set_body($data_arr);
		if($send_yn == "Y"){
			$CI->ordermail->send();
			$CI->ordermail->send_log();
		}
		if($send_admin_yn == "Y"){ //관리자발송
			$CI->ordermail->memo		= "admin";
			
			// fm_config codecd = settle_admin_email 정보가 없으면 관리자 대표메일로 발송한다
			$toAdminEmail = (isset($CI->config_email[$case . '_admin_email'])) ? $CI->config_email[$case . '_admin_email'] : $CI->config_basic['companyEmail'];
			$CI->ordermail->to_email	= $toAdminEmail;
			$CI->ordermail->send();
			$CI->ordermail->send_log();
		}
	}
	return true;
}

// 출고완료 메일
function send_mail_step55($export_code,$data_export='',$data_order=''){
	$CI =& get_instance();
	$CI->load->model('ordermail');

	$email_mode 						= "email";
	$CI->config_email					= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);

	## 고객/관리자에게 발송여부 확인
	$case = "released";
	$send_yn	= ($CI->config_email[$case."_user_yn"])?$CI->config_email[$case."_user_yn"]:'N';
	$send_admin_yn	= ($CI->config_email[$case."_admin_yn"])?$CI->config_email[$case."_admin_yn"]:'N';

	$CI->ordermail->set_user_yn = $send_yn;		//고객에게발송 사용여부
	if($send_admin_yn == "Y") $CI->ordermail->set_admin_yn = "Y";

	if($send_yn == "Y" || $send_admin_yn == "Y"){
		$site = explode("/",uri_string());
		if($site[0] == "admin" || $site[0] == "selleradmin"){
			$export = $CI->exportmodel -> get_export($export_code);
			$order = $CI->ordermodel -> get_order($export['order_seq']);
			$item = $CI->exportmodel -> get_export_item($export_code);

			$order['international'] = $export['international'];
			$order['shipping_method'] = $export['domestic_shipping_method'];

			// 배송방법
			$order['mshipping'] = $CI->ordermodel->get_delivery_method($order);

			if($export['international'] == 'domestic'){
				if($export['domestic_shipping_method'] == 'delivery'){
					$tmp = config_load('delivery_url',$export['delivery_company_code']);
					$export['mdelivery'] = $arr_delivery[$export['delivery_company_code']]['company'];
					$export['mdelivery_number'] = $export['delivery_number'];
					$export['tracking_url'] = $arr_delivery[$export['delivery_company_code']]['url'].$export['delivery_number'];
				}
			}else{
				$export['mdelivery'] = $export['international_shipping_method'];
				$export['mdelivery_number'] = $export['international_delivery_no'];
			}

			foreach($item  as $row){
				$options_arr	= get_options_print_array($row, ':');
				if	($options_arr)		$row['options_str']	= implode(' / ', $options_arr);
				unset($options_arr);

				if	($row['opt_type'] == 'sub'){
					$row['price']								= $row['price'] * $row['ea'];
					$row['sub_options']							= $row['options_str'];
					if	($first_option_seq)
						$goods[$first_option_seq]['sub'][]		= $row;
					else
						$goods[$row['option_seq']]['sub'][]		= $row;
				}else{
					$goods[$row['option_seq']]['price']			+= $row['price'] * $row['ea'];
					$goods[$row['option_seq']]['ea']			+= $row['ea'];
					$goods[$row['option_seq']]['goods_name']	= $row['goods_name'];
					$goods[$row['option_seq']]['options']		= $row['options_str'];
					$goods[$row['option_seq']]['inputs']		= $CI->ordermodel->get_input_for_option($row['item_seq'], $row['option_seq']);
				}
				if	(!$first_option_seq)	$first_option_seq	= $row['option_seq'];
			}

			if( $order['recipient_address_street'] && $order['recipient_address_type'] != 'zibun' ) $order['recipient_address'] = $order['recipient_address_street'];

			$file_path	= "../../data/email/".get_lang(true)."/released.html";
			$CI->template->assign(array('order'=>$order,'goods'=>$goods,'export'=>$export));

			//주문상품 정보추출
			$CI->ordermail->step 			=  55;
			$CI->ordermail->export_code =  array($export_code);
			$CI->ordermail->from_email 	= $CI->config_basic['companyEmail'];
			$CI->ordermail->from_name 	= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
			$CI->ordermail->subject 		= $CI->config_email["released_title"];
			$data_arr 							= $CI->ordermail->get_mail_info();
			$CI->template->assign($data_arr);

			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";
			$CI->template->define(array('tpl'=>$file_path));
			$out = $CI->template->fetch("tpl");

			$email = config_load('email');
			$email['released_skin'] = $out;
			$email['member_seq']	= $order['member_seq'];
			$email['shopName']		= $CI->config_basic['shopName'];
			$email['ordno']			= $order['order_seq'];
			$email['user_name']		= $order['order_user_name'];
			$email['export_code']	= $export_code;

			saveMail($order['order_email'], 'released', '', $email);
		}else{
			$CI->ordermail->step 			=  55;
			$CI->ordermail->export_code =  array($export_code);
			$CI->ordermail->from_email 	= $CI->config_basic['companyEmail'];
			$CI->ordermail->from_name 	= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
			$CI->ordermail->subject 		= $CI->config_email["released_title"];
			$data_arr 							= $CI->ordermail->get_mail_info();

			$CI->ordermail->set_contents_file();
			$CI->ordermail->set_subject($data_arr);
			$CI->ordermail->set_body($data_arr);
			if($send_yn == "Y"){
				$CI->ordermail->send();
				$CI->ordermail->send_log();
			}
			if($send_admin_yn == "Y"){ //관리자발송
				$CI->ordermail->memo		= "admin";
				$CI->ordermail->to_email	= $CI->config_email[$case."_admin_email"];
				$CI->ordermail->send();
				$CI->ordermail->send_log();
			}
		}
	}
	return true;

}

// 배송완료 메일
function send_mail_step75($export_code){
	$CI =& get_instance();
	$CI->load->model('ordermail');

	$email_mode 						= "email";
	$CI->config_email					= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);

	## 고객/관리자에게 발송여부 확인
	$case = "delivery";
	$send_yn	= ($CI->config_email[$case."_user_yn"])?$CI->config_email[$case."_user_yn"]:'N';
	$send_admin_yn	= ($CI->config_email[$case."_admin_yn"])?$CI->config_email[$case."_admin_yn"]:'N';

	$CI->ordermail->set_user_yn = $send_yn;		//고객에게발송 사용여부
	if($send_admin_yn == "Y") $CI->ordermail->set_admin_yn = "Y";

	if($send_yn == "Y" || $send_admin_yn == "Y"){
		$site = explode("/",uri_string());
		if($site[0] == "admin" || $site[0] == "selleradmin"){
			$CI->load->model('ordermodel');
			$CI->load->model('exportmodel');
			$arr_delivery = config_load('delivery_url');

			$export = $CI->exportmodel -> get_export($export_code);
			$order = $CI->ordermodel -> get_order($export['order_seq']);
			$item = $CI->exportmodel -> get_export_item($export_code);

			$order['international'] = $export['international'];
			$order['shipping_method'] = $export['domestic_shipping_method'];

			// 배송방법
			$order['mshipping'] = $CI->ordermodel->get_delivery_method($order);

			if($export['international'] == 'domestic'){
				if($export['domestic_shipping_method'] == 'delivery'){
					$tmp = config_load('delivery_url',$export['delivery_company_code']);
					$export['mdelivery'] = $arr_delivery[$export['delivery_company_code']]['company'];
					$export['mdelivery_number'] = $export['delivery_number'];
					$export['tracking_url'] = $arr_delivery[$export['delivery_company_code']]['url'].$export['delivery_number'];
				}
			}else{
				$export['mdelivery'] = $export['international_shipping_method'];
				$export['mdelivery_number'] = $export['international_delivery_no'];
			}

			foreach($item  as $row){
				$options_arr	= get_options_print_array($row, ':');
				if	($options_arr)		$row['options_str']	= implode(' / ', $options_arr);
				unset($options_arr);

				if	($row['opt_type'] == 'sub'){
					$row['price']								= $row['price'] * $row['ea'];
					$row['sub_options']							= $row['options_str'];
					if	($first_option_seq)
						$goods[$first_option_seq]['sub'][]		= $row;
					else
						$goods[$row['option_seq']]['sub'][]		= $row;
				}else{
					$goods[$row['option_seq']]['price']			+= $row['price'] * $row['ea'];
					$goods[$row['option_seq']]['ea']			+= $row['ea'];
					$goods[$row['option_seq']]['goods_name']	= $row['goods_name'];
					$goods[$row['option_seq']]['options']		= $row['options_str'];
					$goods[$row['option_seq']]['inputs']		= $CI->ordermodel->get_input_for_option($row['item_seq'], $row['option_seq']);
				}
				if	(!$first_option_seq)	$first_option_seq	= $row['option_seq'];
			}

			if( $order['recipient_address_street'] && $order['recipient_address_type'] != 'zibun' ) $order['recipient_address'] = $order['recipient_address_street'];

			$file_path	= "../../data/email/".get_lang(true)."/delivery.html";
			$CI->template->assign(array('order'=>$order,'goods'=>$goods,'export'=>$export));

			//주문상품 정보추출
			$CI->ordermail->step 			=  75;
			$CI->ordermail->export_code =  array($export_code);
			$CI->ordermail->from_email 		= $CI->config_basic['companyEmail'];
			$CI->ordermail->from_name 		= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
			$CI->ordermail->subject 		= $CI->config_email["delivery_title"];
			$data_arr 						= $CI->ordermail->get_mail_info();
			$CI->template->assign($data_arr);

			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";
			$CI->template->define(array('tpl'=>$file_path));
			$out = $CI->template->fetch("tpl");

			$email = config_load('email');
			$email['delivery_skin'] = $out;
			$email['member_seq'] = $order['member_seq'];
			$email['shopName']		= $CI->config_basic['shopName'];
			$email['ordno']				= $order['order_seq'];
			$email['user_name']	= $order['order_user_name'];

			saveMail($order['order_email'], 'delivery', '', $email);
		}else{

			$CI->ordermail->step 			=  75;
			$CI->ordermail->export_code =  array($export_code);
			$CI->ordermail->from_email 					= $CI->config_basic['companyEmail'];
			$CI->ordermail->from_name 					= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
			$CI->ordermail->subject 						= $CI->config_email["delivery_title"];
			$data_arr 							= $CI->ordermail->get_mail_info();

			$CI->ordermail->set_contents_file();
			$CI->ordermail->set_subject($data_arr);
			$CI->ordermail->set_body($data_arr);
			if($send_yn == "Y"){
				$CI->ordermail->send();
				$CI->ordermail->send_log();
			}
			if($send_admin_yn == "Y"){ //관리자발송
				$CI->ordermail->memo		= "admin";
				$CI->ordermail->to_email	= $CI->config_email[$case."_admin_email"];
				$CI->ordermail->send();
				$CI->ordermail->send_log();
			}
		}
	}
	return true;
}

// 티켓상품 배송완료 메일
function coupon_send_mail_step75($export_code){
	$CI =& get_instance();
	$CI->load->model('ordermail');

	$email_mode 						= "email";
	$CI->config_email					= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);

	## 고객/관리자에게 발송여부 확인
	$case			= "coupon_delivery";
	$send_yn		= "Y";
	$send_admin_yn	= ($CI->config_email[$case."_admin_yn"])?$CI->config_email[$case."_admin_yn"]:'N';

	$CI->ordermail->set_user_yn = $send_yn;		//고객에게발송 사용여부
	if($send_admin_yn == "Y") $CI->ordermail->set_admin_yn = "Y";

	if($send_yn == "Y" || $send_admin_yn == "Y"){
		$CI->ordermail->step 			=  75;
		$CI->ordermail->goods_kind 		= "ticket";
		$CI->ordermail->export_code[]	=  $export_code;
		$CI->ordermail->from_email 				= $CI->config_basic['companyEmail'];
		$CI->ordermail->from_name 					= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
		$CI->ordermail->subject 						= $CI->config_email["coupon_delivery_title"];
		$data_arr 							= $CI->ordermail->get_mail_info();

		$CI->ordermail->set_contents_file();
		$CI->ordermail->set_subject($data_arr);
		$CI->ordermail->set_body($data_arr);
		if($send_yn == "Y"){
			$CI->ordermail->send();
			$CI->ordermail->send_log();
		}
		if($send_admin_yn == "Y"){ //관리자발송
			$CI->ordermail->memo		= "admin";
			$CI->ordermail->to_email	= $CI->config_email[$case."_admin_email"];
			$CI->ordermail->send();
			$CI->ordermail->send_log();
		}
	}

	return $result;
}

// 티켓상품 출고완료 SMS
function coupon_send_sms_step55($export_code, $send_sms, $send_sms2=''){
	if	($send_sms){
		$CI =& get_instance();
		$CI->load->model('ordermodel');
		$CI->load->model('exportmodel');

		$config_system	= config_load('system');
		$export			= $CI->exportmodel -> get_export($export_code);
		$order			= $CI->ordermodel -> get_order($export['order_seq']);
		$items			= $CI->exportmodel -> get_export_item($export_code);
		$item			= $items[0];
		$providerList[]	= $item['provider_seq'];

		$params['shopName']		= $CI->config_basic['shopName'];
		$params['ordno']		= $order['order_seq'];
		$params['user_name']	= $order['order_user_name'];
		$params['recipient_user']	= $order['recipient_user_name'];
		$params['member_seq']	= $order['member_seq'];						// 회원seq
		$params['export_code'] = $export_code;

		// 치환코드
		$params['order_user']	= $order['order_user_name'];				// 주문자명
		$params['goods_name']	= $item['goods_name'];						// 상품명
		$params['coupon_serial']= $item['coupon_serial'];					// 티켓번호
		$params['couponNum']	= $item['coupon_st'].'/'.$item['opt_ea'];	// 회차
		// 값어치
		if	($item['coupon_input'] > 0){
			if	($item['socialcp_input_type'] == 'pass')
				$params['coupon_value']	= '사용가능횟수 : '.number_format($item['coupon_input']);
			else
				$params['coupon_value']	= '사용가능금액 : '.get_currency_price($item['coupon_input']);
		}
		// 옵션
		$options_arr	= get_options_print_array($item, ':');
		if	($options_arr)		$params['options']	= implode(chr(10), $options_arr);

		//$result	= sendSMS($send_sms, 'coupon_released', '', $params);
		$CI->coupon_order_sms['order_cellphone'][] = $send_sms;
		$CI->coupon_order_sms['params'][] = $params;
		$CI->coupon_order_sms['order_no'][] = $order['order_seq'];

		// 재발송용 추가
		$CI->resend_sms_common_data['coupon_released']['phone'][]		= $send_sms;
		$CI->resend_sms_common_data['coupon_released']['params'][]		= $params;
		$CI->resend_sms_common_data['coupon_released']['order_no'][]	= $order['order_seq'];

		sendSMS_for_provider('coupon_released', $providerList, $params);
		# 주문자와 받는분이 다를때 받는분에게도 문자 전송
		if( $send_sms2 && (preg_replace("/[^0-9]/", "", $send_sms) !=  preg_replace("/[^0-9]/", "", $send_sms2))){

			$CI->coupon_reciver_sms['order_cellphone'][] = $send_sms2;
			$CI->coupon_reciver_sms['params'][] = $params;
			$CI->coupon_reciver_sms['order_no'][] = $order['order_seq'];

			//sendSMS($send_sms2, 'coupon_released2', '', $params);          //주문자
		}

		return true;
	}

	return false;
}


// 티켓상품 배송완료 SMS
function coupon_send_sms_step75($export_code){

	$CI =& get_instance();
	$CI->load->model('ordermodel');
	$CI->load->model('exportmodel');

	$config_system	= config_load('system');
	$export			= $CI->exportmodel -> get_export($export_code);
	$order			= $CI->ordermodel -> get_order($export['order_seq']);
	$items			= $CI->exportmodel -> get_export_item($export_code);
	$uselog			= $CI->exportmodel -> get_coupon_use_history($items[0]['coupon_serial']);
	$item			= $items[0];
	$send_sms		= $item['recipient_cellphone'];
	$send_sms2		= $order['order_cellphone'];		//주문자
	$providerList[]	= $item['provider_seq'];
	$params['shopName']			= $CI->config_basic['shopName'];
	$params['ordno']			= $order['order_seq'];
	$params['user_name']		= $order['order_user_name'];
	$params['member_seq']		= $order['member_seq'];

	// 티켓상품 사용 내역
	$params['used_time']		= date('Y년 m월 d일 H시 i분', strtotime($uselog[0]['regist_date']));
	$params['used_location']	= $uselog[0]['coupon_use_area'];
	$params['confirm_person']	= $uselog[0]['confirm_user'];

	// 치환코드
	$params['order_user']		= $order['order_user_name'];				// 주문자명
	$params['goods_name']		= $item['goods_name'];						// 상품명
	$params['coupon_serial']	= $item['coupon_serial'];					// 티켓번호
	$params['couponNum']		= $item['coupon_st'].'/'.$item['opt_ea'];	// 회차

	//회원일경우 userid 불러오기
	if(trim($order['member_seq'])){
		$CI->load->model('membermodel');
		$userid		= $CI->membermodel->get_member_userid(trim($order['member_seq']));
	}

	if($userid)	$params['userid']	= $userid;									// 회원id(존재할 경우에만)
	$params['order_user']			= $order['order_user_name'];				//주문자명
	$params['recipient_user']		= $order['recipient_user_name'];			//수취인명
	// 치환코드 끝
	// 값어치
	if	($item['coupon_input'] > 0){
		if	($item['socialcp_input_type'] == 'pass'){
			$params['coupon_remain']	= '잔여:'.number_format($item['coupon_remain_value']).'회';
			$params['coupon_used']		= number_format($uselog[0]['coupon_use_value']).'회 사용';
			$params['coupon_value']		= '사용가능횟수 : '.number_format($item['coupon_input']);
		}else{
			$params['coupon_remain']	= '잔여:'.get_currency_price($item['coupon_remain_value'],3);
			$params['coupon_used']		= get_currency_price($uselog[0]['coupon_use_value']).' 사용';
			$params['coupon_value']		= '사용가능금액 : '.get_currency_price($item['coupon_input']);
		}
	}
	// 옵션
	$options_arr	= get_options_print_array($item, ':');
	if	($options_arr)		$params['options']	= implode(chr(10), $options_arr);


	$CI->exportSmsData['coupon_delivery2']['phone'][] = $send_sms;
	$CI->exportSmsData['coupon_delivery2']['params'][] = $params;
	$CI->exportSmsData['coupon_delivery2']['order_no'][] = $order['order_seq'];

	sendSMS_for_provider('coupon_delivery', $providerList, $params);
	# 주문자와 받는분 전화번호가 다를때 티켓사용(받는분) 문자 전송
	if( $send_sms2 && (preg_replace("/[^0-9]/", "", $send_sms) !=  preg_replace("/[^0-9]/", "", $send_sms2))){
		$CI->exportSmsData['coupon_delivery']['phone'][] = $send_sms2;
		$CI->exportSmsData['coupon_delivery']['params'][] = $params;
		$CI->exportSmsData['coupon_delivery']['order_no'][] = $order['order_seq'];

	}
	return $result;
}

// 티켓상품 결제취소완료 SMS
function coupon_send_sms_cancel($export_code,$order){

	$CI =& get_instance();
	$CI->load->model('ordermodel');
	$CI->load->model('exportmodel');

	$CI->config_system	= ($CI->config_system)?$CI->config_system:config_load('system');
	$export			= $CI->exportmodel -> get_export($export_code);
	//$order			= $CI->ordermodel -> get_order($export['order_seq']);
	$items			= $CI->exportmodel -> get_export_item($export_code);
	$uselog			= $CI->exportmodel -> get_coupon_use_history($items[0]['coupon_serial']);
	$item			= $items[0];
	$send_sms		= $item['recipient_cellphone'];
	$providerList[]	= $item['provider_seq'];
	$params['shopName']		= $CI->config_basic['shopName'];
	$params['ordno']			= $order['order_seq'];
	$params['user_name']		= $order['order_user_name'];
	$params['member_seq']		= $order['member_seq'];

	// 티켓상품 사용 내역
	$params['used_time']			= date('Y년 m월 d일 H시 i분', strtotime($uselog[0]['regist_date']));
	$params['used_location']		= $uselog[0]['coupon_use_area'];
	$params['confirm_person']		= $uselog[0]['confirm_user'];

	// 치환코드
	$params['goods_name']			= $item['goods_name'];						// 상품명
	$params['coupon_serial']		= $item['coupon_serial'];					// 티켓번호
	$params['couponNum']			= $item['coupon_st'].'/'.$item['opt_ea'];	// 회차

	// 치환코드 끝

	// 값어치
	if	($item['coupon_input'] > 0){
		if	($item['socialcp_input_type'] == 'pass'){
			$params['coupon_remain']	= '잔여:'.number_format($item['coupon_remain_value']).'회';
			$params['coupon_used']		= number_format($uselog[0]['coupon_use_value']).'회 사용';
			$params['coupon_value']		= '사용가능횟수 : '.number_format($item['coupon_input']);
		}else{
			$params['coupon_remain']	= '잔여:'.get_currency_price($item['coupon_remain_value'],3);
			$params['coupon_used']		= get_currency_price($uselog[0]['coupon_use_value']).' 사용';
			$params['coupon_value']		= '사용가능금액 : '.get_currency_price($item['coupon_input']);
		}
	}
	// 옵션
	$options_arr	= get_options_print_array($item, ':');
	if	($options_arr)		$params['options']	= implode(chr(10), $options_arr);

	//$result	= sendSMS($send_sms, 'coupon_cancel', '', $params);
	//SMS 데이터 생성
	$commonSmsData['coupon_cancel']['phone'][] = $send_sms;
	$commonSmsData['coupon_cancel']['params'][] = $params;
	$commonSmsData['coupon_cancel']['order_no'][] = $order['order_seq'];
	$result = commonSendSMS($commonSmsData);

	//sendSMS_for_provider('coupon_cancel', $providerList, $params);
	return $result;
}

// 티켓상품 환불완료 SMS
function coupon_send_sms_refund($export_code,$order){

	$CI =& get_instance();
	$CI->load->model('ordermodel');
	$CI->load->model('exportmodel');

	$CI->config_system	= ($CI->config_system)?$CI->config_system:config_load('system');
	$export			= $CI->exportmodel -> get_export($export_code);
	//$order			= $CI->ordermodel -> get_order($export['order_seq']);
	$items			= $CI->exportmodel -> get_export_item($export_code);
	$uselog			= $CI->exportmodel -> get_coupon_use_history($items[0]['coupon_serial']);
	$item			= $items[0];
	$send_sms		= $item['recipient_cellphone'];
	$providerList[]	= $item['provider_seq'];
	$params['shopName']		= $CI->config_basic['shopName'];
	$params['ordno']			= $order['order_seq'];
	$params['user_name']		= $order['order_user_name'];
	$params['member_seq']		= $order['member_seq'];

	// 티켓상품 사용 내역
	$params['used_time']			= date('Y년 m월 d일 H시 i분', strtotime($uselog[0]['regist_date']));
	$params['used_location']		= $uselog[0]['coupon_use_area'];
	$params['confirm_person']		= $uselog[0]['confirm_user'];

	// 치환코드
	$params['order_user']			= $order['order_user_name'];				// 주문자명
	$params['goods_name']			= $item['goods_name'];						// 상품명
	$params['coupon_serial']		= $item['coupon_serial'];					// 티켓번호
	$params['couponNum']			= $item['coupon_st'].'/'.$item['opt_ea'];	// 회차

	// 치환코드 끝

	// 값어치
	if	($item['coupon_input'] > 0){
		if	($item['socialcp_input_type'] == 'pass'){
			$params['coupon_remain']	= '잔여:'.number_format($item['coupon_remain_value']).'회';
			$params['coupon_used']		= number_format($uselog[0]['coupon_use_value']).'회 사용';
			$params['coupon_value']		= '사용가능횟수 : '.number_format($item['coupon_input']);
		}else{
			$params['coupon_remain']	= '잔여:'.get_currency_price($item['coupon_remain_value'],3);
			$params['coupon_used']		= get_currency_price($uselog[0]['coupon_use_value'],3).' 사용';
			$params['coupon_value']		= '사용가능금액 : '.get_currency_price($item['coupon_input']);
		}
	}
	// 옵션
	$options_arr	= get_options_print_array($item, ':');
	if	($options_arr)		$params['options']	= implode(chr(10), $options_arr);

	//$result	= sendSMS($send_sms, 'coupon_refund', '', $params);
	//SMS 데이터 생성
	$commonSmsData['coupon_refund']['phone'][] = $send_sms;
	$commonSmsData['coupon_refund']['params'][] = $params;
	$commonSmsData['coupon_refund']['order_no'][] = $order['order_seq'];
	$result = commonSendSMS($commonSmsData);
	//sendSMS_for_provider('coupon_refund', $providerList, $params);
	return $result;
}


// 필수옵션 title + option형태로 배열화
function get_options_print_array($param, $division) {
	$CI =& get_instance();
	if(!$CI->goodsmodel) $CI->load->model('goodsmodel');

	// 특수옵션 처리
	if	($param['newtype']){
		// 특수옵션 유효기간 ( 유효기간 날짜로 변경 )
		if	($param['social_start_date'] && $param['social_end_date']){
			$expire_arr				= array('dayauto', 'date', 'dayinput');
			$sp_option['expire']	= true;
		}
		// 특수옵션 주소 ( 연락처 추가 )
		if	($param['biztel']){
			$address_arr			= array('address');
			$sp_option['address']	= true;
		}

		$dayauto_type = ($param['dayauto_type']  == 'day')?"이후":"";
		$newtype	= explode(',', $param['newtype']);
		foreach($newtype as $k => $types){
			if	($sp_option['expire'] && in_array($types, $expire_arr)){
				$key					= $k + 1;
				if( $types == 'date' ) {
					$param['option'.$key.'_datetitle'] = '';
					//$param['option'.$key]	= $param['codedate'];
				}elseif( $types == 'dayauto' ) {
					$param['option'.$key.'_dayautotitle'] = '"결제확인" 후 '.$CI->goodsmodel->dayautotype[$param['dayauto_type']].' '.$param['sdayauto'].'일 '.$dayauto_type.'부터 +'.$param['fdayauto'].'일 '.$CI->goodsmodel->dayautoday[$param['dayauto_day']];
					$param['option'.$key]	= $param['social_start_date'] . ' ~ ' . $param['social_end_date'];
				}else{
					$param['option'.$key.'_dayinputtitle'] = $param['sdayinput'] . ' ~ ' . $param['fdayinput'];
					$param['option'.$key]	= $param['social_start_date'] . ' ~ ' . $param['social_end_date'];
				}
			}
			if	($sp_option['address'] && in_array($types, $address_arr)){
				$param['option'.$key]	= $param['option'.$key] . ' (' . $param['biztel'] . ')';
			}
		}
	}

	if	($param['option1'])	$result[]	= $param['title1'].$division.$param['option1'];
	if	($param['option2'])	$result[]	= $param['title2'].$division.$param['option2'];
	if	($param['option3'])	$result[]	= $param['title3'].$division.$param['option3'];
	if	($param['option4'])	$result[]	= $param['title4'].$division.$param['option4'];
	if	($param['option5'])	$result[]	= $param['title5'].$division.$param['option5'];

	return $result;
}

function get_pg_config($company,$mode){
	$pg = config_load($company);
	if($mode == 'mobile'){
		unset($pg['interestTerms'],$pg['payment'],$pg['escrow'],$pg['pcCardCompanyCode']);
		$pg['interestTerms'] = $pg['mobileInterestTerms'];
		$pg['payment'] = $pg['mobilePayment'];
		$pg['escrow'] = $pg['mobileEscrow'];
		$pg['pcCardCompanyCode'] = $pg['mobileCardCompanyCode'];
		foreach($pg['pcCardCompanyCode'] as $key => $code) $pg['pcCardCompanyTerms'][$key] = $tmp['mobileCardCompanyTerms'][$key];
	}
	return $pg;
}

function item_step_msg($data_options,$data_suboptions){
	$r_step = array();
	if($data_options) foreach($data_options as $data){
		if(!in_array($data['step'],$r_step)){
			$r_step[] = $data['step'];
		}
	}
	if($data_suboptions) foreach($data_suboptions as $data){
		if(!in_array($data['step'],$r_step)){
			$r_step[] = $data['step'];
		}
	}
	return $r_step;
}


/**
* 티켓상품 미사용티켓 환불여부
* false 환불불가로 disabled 처리
* true 환불가능로 disabled 없음
**/
function order_socialcp_cancel_return($socialcp_use_return, $export_coupon_value, $export_coupon_remain_value, $social_start_date, $social_end_date , $socialcp_use_emoney_day, $type='order') {

	//debug_var($socialcp_use_return.', '.$export_coupon_value.', '.$export_coupon_remain_value.', '.$social_start_date.', '.$social_end_date.', '.$socialcp_use_emoney_day.', '.$type);

	$socialcp_use_day = ($socialcp_use_emoney_day>0)?date("Ymd",strtotime('+'.$socialcp_use_emoney_day.' day '.substr(str_replace("-","",$social_end_date),0,8))):date("Ymd",strtotime(substr(str_replace("-","",$social_end_date),0,8)));
	if( $type =='viewer' ){
		return $socialcp_use_day;
	}else{
		if(  $export_coupon_remain_value < 1 ) {//잔여값어치가 없음
				return false;
		}else{
			if( $socialcp_use_return != 1 || (($social_start_date == '1970-01-01' || !$social_start_date) && ($social_end_date == '1970-01-01' || !$social_end_date)) ) {//유효기간이 잘못되었거나 없으면
					return false;
			}elseif ( $socialcp_use_return == 1 ) {//미사용티켓 환불가능
				$today = date("Ymd");
				//if($today<=$social_end_date) {//유효기간이전이면 불가
				if( $socialcp_use_day < $today ) {//설정기간이후면 불가
					return false;
				}
			}
		}
		return true;//환불가능
	}
}

/**
* 티켓상품 취소(환불)
* false 환불불가로 disabled 처리
* true 환불가능로 disabled 없음
* return cancel_percent 실제 적용율
**/
function order_socialcp_cancel_refund($order_seq, $item_seq, $deposit_date, $social_start_date, $social_end_date, $socialcp_cancel_payoption, $socialcp_cancel_payoption_percent, $type='order') {
	$CI =& get_instance();
	if(!$CI->ordermodel) $CI->load->model('ordermodel');
	$today = date("Ymd");
	//debug_var($order_seq.', '.$item_seq.', '.$deposit_date.', '.$social_start_date.', '.$social_end_date.', '.$socialcp_cancel_payoption.', '.$socialcp_cancel_payoption_percent.', '.$type);

	//유효기간이 잘못되었거나 없으면
	if( (($social_start_date == '1970-01-01' || !$social_start_date) || ($social_end_date == '1970-01-01' || !$social_end_date)) && $type =='order'  )  {
		return array(false,0);
	}else{
		$socialcp_cancel = $CI->ordermodel->get_item_socialcp_cancel($order_seq, $item_seq);
		if($socialcp_cancel) {

			if( $socialcp_cancel[0]['socialcp_cancel_type'] == 'pay' ) {//결제확인 후 몇일 이내에 취소(환불) 가능
				$socialcp_cancel_refund_day = ($socialcp_cancel[0]['socialcp_cancel_day']>0)?date("Ymd",strtotime('+'.$socialcp_cancel[0]['socialcp_cancel_day'].' day '.substr(str_replace("-","",$deposit_date),0,8))):date("Ymd",strtotime(substr(str_replace("-","",$deposit_date),0,8)));
				if( $type =='viewer' ){
					return array('pay',$socialcp_cancel_refund_day,$socialcp_cancel[0]['socialcp_cancel_day']);
				}else{
					if( $today <= $socialcp_cancel_refund_day ){
						return array(true,100);
					}
				}

			}elseif( $socialcp_cancel[0]['socialcp_cancel_type'] == 'option' ) {//유효기간 이내에만 취소(환불) 가능
				if( $type =='viewer' ){
					return array('option',$social_end_date);
				}else{
					if($today<=str_replace("-","",$social_end_date) ) {// $today>=str_replace("-","",$social_start_date)  &&
						return array(true,100);
					}
				}

			}elseif( $socialcp_cancel[0]['socialcp_cancel_type'] == 'payoption' ) {//유효기간 설정

				$scnt=0;
				if( $socialcp_cancel_payoption == 1 && $today>=str_replace("-","",$social_start_date) && $today<=str_replace("-","",$social_end_date)  ) {
					//유효기간내 취소(환불) 가능
					$view_socialcp_cancel_refund_day			= $social_start_date;
					$view_socialcp_cancel_refund_prevday	= $social_end_date;
					$max_percent										= $socialcp_cancel_payoption_percent;

					if( $type =='viewer' ){
						return array('payoption',$view_socialcp_cancel_refund_day,$view_socialcp_cancel_refund_prevday,$max_percent,'social_date');
					}else{
						return array(true,$max_percent);
					}
				}else{
					rsort($socialcp_cancel); //재정렬( 0>5>10)
					$idx = sizeof($socialcp_cancel);
					foreach($socialcp_cancel as $k=>$canceldata) {
						$isprevday = ($k == (sizeof($socialcp_cancel)-1))?false:$k+1;//취소(환불) 마지막 결제확인 시작일(100%)

						if( $isprevday === false ) {//마지막은 전일까지
							$socialcp_cancel_refund_day = date("Ymd",strtotime(substr(str_replace("-","",$deposit_date),0,8)));//취소(환불) 시작일 == 결제일
							$socialcp_cancel_refund_prevday = date("Ymd",strtotime('-'.(($canceldata['socialcp_cancel_day'])+1).' day ', strtotime(substr(str_replace("-","",$social_start_date),0,8))));//취소(환불) 완료일
						}else{
							$socialcp_cancel_refund_day = date("Ymd",strtotime('-'.($socialcp_cancel[$isprevday]['socialcp_cancel_day']).' day ',strtotime(substr(str_replace("-","",$social_start_date),0,8))));//취소(환불) 시작일은 전일

							$socialcp_cancel_refund_prevday =date("Ymd",strtotime('-'.($canceldata['socialcp_cancel_day']+1).' day ', strtotime(substr(str_replace("-","",$social_start_date),0,8))));//취소(환불) 완료일
						}
						if( $k == 0 ) {//첫번째
							$socialcp_cancel_refund_sday_start = $socialcp_cancel_refund_day;
							$socialcp_cancel_refund_sday_end = $socialcp_cancel_refund_prevday;
							$socialcp_cancel_refund_sday_day		= $canceldata['socialcp_cancel_day'];
							$socialcp_cancel_refund_sday_percent		= ($idx == 1)?$canceldata['socialcp_cancel_percent']:(100-$canceldata['socialcp_cancel_percent']);
						}elseif( $k == (sizeof($socialcp_cancel)-1)){//마지막
							$socialcp_cancel_refund_eday_start	 = $socialcp_cancel_refund_day;
							$socialcp_cancel_refund_eday_end		= $socialcp_cancel_refund_prevday;
							$socialcp_cancel_refund_eday_day		= $canceldata['socialcp_cancel_day'];
							$socialcp_cancel_refund_eday_percent		= ($idx == 1)?$canceldata['socialcp_cancel_percent']:(100-$canceldata['socialcp_cancel_percent']);
						}

						if( $socialcp_cancel_refund_day <= $today && $socialcp_cancel_refund_prevday >= $today )
						{
							$max_percent	= ($idx == 1)?$canceldata['socialcp_cancel_percent']:(100-$canceldata['socialcp_cancel_percent']);
							$view_socialcp_cancel_refund_day			= $socialcp_cancel_refund_day;//취소(환불) 시작일
							$view_socialcp_cancel_refund_prevday	= $socialcp_cancel_refund_prevday;//취소(환불) 완료일
							$max_socialcp_cancel_day						= $canceldata['socialcp_cancel_day'];
							$scnt++;
							break;
						}
						$idx--;
					}

					if( date("Ymd")>substr(str_replace("-","",$social_end_date),0,8)) {//유효기간 종료 후
						if ( $socialcp_cancel_payoption == 1 ){//유효기간내 취소율
							$view_socialcp_cancel_refund_day			= $social_start_date;
							$view_socialcp_cancel_refund_prevday	= $social_end_date;
							$max_percent										= $socialcp_cancel_payoption_percent;
						}else{
							$view_socialcp_cancel_refund_day			= $social_start_date;
							$view_socialcp_cancel_refund_prevday	= $social_end_date;
							$max_percent										= ($idx == 1)?$socialcp_cancel[$k]['socialcp_cancel_percent']:(100-$socialcp_cancel[$k]['socialcp_cancel_percent']);
						}
					}

					if( $type =='viewer' ){
						if(!$scnt) {
							if( $socialcp_cancel_refund_eday_day > $today) {//오늘이 마지막일보다 큰경우
								$view_socialcp_cancel_refund_day			= $socialcp_cancel_refund_eday_start;
								$view_socialcp_cancel_refund_prevday	= $socialcp_cancel_refund_eday_end;
								$max_socialcp_cancel_day						= $socialcp_cancel_refund_eday_day;
								$max_percent										= $socialcp_cancel_refund_eday_percent;
							}else{
								$view_socialcp_cancel_refund_day			= $socialcp_cancel_refund_sday_start;
								$view_socialcp_cancel_refund_prevday	= $socialcp_cancel_refund_sday_end;
								$max_socialcp_cancel_day						= $socialcp_cancel_refund_sday_day;
								$max_percent										= $socialcp_cancel_refund_sday_percent;
							}
						}
						return array('payoption',$view_socialcp_cancel_refund_day,$view_socialcp_cancel_refund_prevday,$max_percent,$max_socialcp_cancel_day);
					}else{
						if( $scnt ){
							return array(true,$max_percent);
						}
					}
				}
			}//endif;
		}//endif;
	}

	if( $type =='viewer' ){
		return array(false);
	}else{
		return array(false,$max_percent);//취소(환불) 불가
	}
}


//티켓상품정보만 추출하기(결제확인이후)
function get_goods_coupon_view($export_code){

	$CI =& get_instance();
	$CI->load->model('ordermodel');
	$CI->load->model('exportmodel');

	$config_system	= config_load('system');
	$export			= $CI->exportmodel -> get_export($export_code);
	$order			= $CI->ordermodel -> get_order($export['order_seq']);
	$items			= $CI->exportmodel -> get_export_item($export_code);
	$uselog			= $CI->exportmodel -> get_coupon_use_history($items[0]['coupon_serial']);
	$item			= $items[0];
	$send_sms		= $item['recipient_cellphone'];
	$providerList[]	= $item['provider_seq'];


	// 티켓상품 사용 내역
	if($uselog[0]) {
		$params['used_time']			= date('Y년 m월 d일 H시 i분', strtotime($uselog[0]['regist_date']));
		$params['used_location']		= $uselog[0]['coupon_use_area'];
		$params['confirm_person']		= $uselog[0]['confirm_user'];
		$params['coupon_used_log']	= $uselog[0];
	}

	$params['social_start_date']		= $item['social_start_date'];
	$params['social_end_date']			= $item['social_end_date'];

	// 치환코드
	$params['order_user']			= $order['order_user_name'];				// 주문자명
	$params['goods_name']			= $item['goods_name'];						// 상품명
	$params['coupon_serial']		= $item['coupon_serial'];					// 티켓번호
	$params['couponNum']			= $item['coupon_st'].'/'.$item['opt_ea'];	// 회차
	$params['coupontotalprice']	= $item['price']*$item['ea'];	// 구매금액
	// 값어치
	if	($item['coupon_input'] > 0){
		if	($item['socialcp_input_type'] == 'pass'){
			$params['coupon_remain']	= '잔여:'.number_format($item['coupon_remain_value']).'회';
			$params['coupon_used']		= number_format($uselog[0]['coupon_use_value']).'회 사용';
			$params['coupon_value']		= '사용가능횟수: '.number_format($item['coupon_input']).'회';
		}else{
			$params['coupon_remain']	= '잔여:'.get_currency_price($item['coupon_remain_value'],3);
			$params['coupon_used']		= get_currency_price($uselog[0]['coupon_use_value'],3).' 사용';
			$params['coupon_value']		= '사용가능금액: '.get_currency_price($item['coupon_input'],3);
		}
	}


	if( $item['socialcp_use_return'] == 1 ){
		$params['order_socialcp_cancel_return'] = order_socialcp_cancel_return($item['socialcp_use_return'], $item['coupon_remain_value'], $item['coupon_remain_value'], $item['social_start_date'], $item['social_end_date'] , $item['socialcp_use_emoney_day'], 'viewer');
		//$params['order_socialcp_cancel_return_title'] = '유효기간 종료 후 '.date("Y년 m월 d일", strtotime($params['order_socialcp_cancel_return'])).'이내 잔여값어치에 '.$item['socialcp_use_emoney_percent'].'% 마일리지환불';
		$params['order_socialcp_cancel_return_title'] = '유효기간 종료 후 '.$item['socialcp_use_emoney_day'].'일 이내('.date("Y년 m월 d일", strtotime($params['order_socialcp_cancel_return'])).'까지) 구매금액에 '.$item['socialcp_use_emoney_percent'].'% 취소(환불) ';

		$params['coupon_use_return']		= '미사용티켓환불 대상 ';
		$params['coupon_use_return']		.= '<br/>('.$params['order_socialcp_cancel_return_title'].')';
		$params['coupon_use_return_status']		= '미사용티켓환불 대상은 '.$params['order_socialcp_cancel_return_title'].'';
	}else{
		$params['coupon_use_return']		= '미사용티켓환불 대상아님';
		$params['coupon_use_return_status']		= '미사용티켓환불 대상아님';
	}

	$socialcp_cancel_refund_day = order_socialcp_cancel_refund($export['order_seq'], $item['item_seq'], $order['deposit_date'], $item['social_start_date'], $item['social_end_date'], $item['socialcp_cancel_payoption'], $item['socialcp_cancel_payoption_percent'], 'viewer');
	if ($socialcp_cancel_refund_day[0] === 'payoption') {
		// 유효기간 시작일
		$socialcp_cancel_start_day = date("Y년 m월 d일", strtotime($socialcp_cancel_refund_day[1]));
		// 유효기간 완료일
		$socialcp_cancel_end_day = date("Y년 m월 d일", strtotime($socialcp_cancel_refund_day[2]));
		// 취소율
		$socialcp_cancel_percent = $socialcp_cancel_refund_day[3] . "%";

		// 취소 마감시간
		$params['socialcp_cancel_refund_day'] = "  " . $socialcp_cancel_start_day . " ~ " . $socialcp_cancel_end_day . " " . $socialcp_cancel_percent;
		$params['socialcp_cancel_refund_day_status'] = "유효기간 " . $socialcp_cancel_start_day . " ~ " . $socialcp_cancel_end_day . " " . $socialcp_cancel_percent;

		// 상품 결제일이 유효기간 시작일보다 크거나 유효기간 내 환불 불가능일 경우
		if (strtotime($socialcp_cancel_refund_day[1]) > strtotime($item['social_start_date']) || $socialcp_cancel_refund_day[4] != 'social_date') {
			$params['socialcp_cancel_refund_day'] = "  " . $socialcp_cancel_end_day . " " . $socialcp_cancel_percent;
			$params['socialcp_cancel_refund_day_status'] = "유효기간 시작 " . $socialcp_cancel_refund_day[4] . "일 전( " . $socialcp_cancel_end_day . "까지) " . $socialcp_cancel_percent;
		}

	}elseif( $socialcp_cancel_refund_day[0] == 'pay' ){//array('','','')
		$params['socialcp_cancel_refund_day'] = "결제확인 이후 ".date("Y년 m월 d일", strtotime($socialcp_cancel_refund_day[1]))."이내에만";
		$params['socialcp_cancel_refund_day_status'] = "결제확인 후 ".$socialcp_cancel_refund_day[2]."일 이내(".date("Y년 m월 d일", strtotime($socialcp_cancel_refund_day[1]))."까지)";
	}elseif( $socialcp_cancel_refund_day[0] == 'option' ){//array('','')
		$params['socialcp_cancel_refund_day'] = "유효기간 ".date("Y년 m월 d일", strtotime($socialcp_cancel_refund_day[1]))."이내에만";
		$params['socialcp_cancel_refund_day_status'] = "유효기간 시작 전";
	}

	// 옵션
	$options_arr	= get_options_print_array($item, ':');
	if	($options_arr)		$params['options']	= implode("<br/>", $options_arr);
	return $params;
}



// 티켓상품(옵션) 사용기간 체크
function check_coupon_date_option($goods_seq,$option1,$option2,$option3,$option4,$option5, $optionssel=null, $data = null) {
	$CI =& get_instance();
	$today = date("Y-m-d");
	if(!$CI->goodsmodel) $CI->load->model('goodsmodel');

	//특수정보 관련 추가@2013-10-22
	list($data_goods_opt['optioncode1'],$data_goods_opt['optioncode2'],$data_goods_opt['optioncode3'],$data_goods_opt['optioncode4'],$data_goods_opt['optioncode5'],$data_goods_opt['color'],$data_goods_opt['zipcode'],$data_goods_opt[0]['address_type'],$data_goods_opt['address'],$data_goods_opt[0]['address_street'],$data_goods_opt['addressdetail'],$data_goods_opt['biztel'],$data_goods_opt['coupon_input'],$data_goods_opt['codedate'],$data_goods_opt['sdayinput'],$data_goods_opt['fdayinput'],$data_goods_opt['dayauto_type'],$data_goods_opt['sdayauto'],$data_goods_opt['fdayauto'],$data_goods_opt['dayauto_day'],$data_goods_opt['newtype'],$data_goods_opt['address_commission']) = $CI->goodsmodel->get_goods_option_code(
		$goods_seq,
		$option1,
		$option2,
		$option3,
		$option4,
		$option5
	);
	$types = explode(",",$data_goods_opt['newtype']);
	$couponexpire =  true;
	if( $optionssel ) {
		$optionssel = ($optionssel == 'detail' ) ? 0:($optionssel);
		if( $types[$optionssel] == 'date' ) {
			$social_start_date = $data['codedate'];
			$social_end_date = $data['codedate'];
			if( $social_end_date < $today ) $couponexpire = false;
		}elseif( $types[$optionssel] == 'dayauto' ) {
		}elseif( $types[$optionssel] == 'dayinput' ) {
			$social_start_date = $data['sdayinput'];
			$social_end_date = $data['fdayinput'];
			if( $social_end_date < $today ) $couponexpire = false;
		}
	}else{
		if( in_array('date', $types) ) {
			$social_start_date = $data_goods_opt['codedate'];
			$social_end_date = $data_goods_opt['codedate'];
			if( $social_end_date < $today ) $couponexpire = false;
		}elseif( in_array('dayauto', $types) ) {
		}elseif( in_array('dayinput', $types) ) {
			$social_start_date = $data_goods_opt['sdayinput'];
			$social_end_date = $data_goods_opt['fdayinput'];
			if( $social_end_date < $today ) $couponexpire = false;
		}
	}

	return array('couponexpire'=>$couponexpire,'social_start_date'=>$social_start_date,'social_end_date'=>$social_end_date);
}

// 티켓상품(추가옵션) 사용기간 체크
function check_coupon_date_suboption($goods_seq,$suboption_title,$suboption) {
	$CI =& get_instance();
	$today = date("Y-m-d");
	if(!$CI->goodsmodel) $CI->load->model('goodsmodel');
//특수정보 관련 추가@2013-10-22
	list($data_goods_opt['suboption_code'],$data_goods_opt['color'],$data_goods_opt['zipcode'],$data_goods_opt['address'],$data_goods_opt['addressdetail'],$data_goods_opt['biztel'],$data_goods_opt['coupon_input'],$data_goods_opt['codedate'],$data_goods_opt['sdayinput'],$data_goods_opt['fdayinput'],$data_goods_opt['dayauto_type'],$data_goods_opt['sdayauto'],$data_goods_opt['fdayauto'],$data_goods_opt['dayauto_day'],$data_goods_opt['newtype']) = $CI->goodsmodel->get_goods_suboption_code($goods_seq,$suboption_title,$suboption);
	//debug_var($data_goods_opt);

	$types = explode(",",$data_goods_opt['newtype']);
	$couponexpire =  true;
	if( in_array('date', $types) ) {
		$social_start_date = $data_goods_opt['codedate'];
		$social_end_date = $data_goods_opt['codedate'];
		if( $social_end_date < $today ) $couponexpire = false;
	}elseif( in_array('dayauto', $types) ) {
	}elseif( in_array('dayinput', $types) ) {
		$social_start_date = $data_goods_opt['sdayinput'];
		$social_end_date = $data_goods_opt['fdayinput'];
		if( $social_end_date < $today ) $couponexpire = false;
	}

	return array('couponexpire'=>$couponexpire,'social_start_date'=>$social_start_date,'social_end_date'=>$social_end_date);

}

/**
** 환불시 로그쌓기
* 신청상태
- $socialcp_refund_help_11 : 유효기간(0000년 00월 00일~ 0000년 00월 00일)
- $socialcp_refund_help_12 : 유효기간 시작 전 or 후
- $socialcp_refund_help_13 : 전체미사용 or 부분미사용
* 취소조건 $socialcp_refund_help_21
* 환불계산
- $socialcp_refund_help_31 : 값어치
- $socialcp_refund_help_32 : 구매금액
- $socialcp_refund_help_33 : 잔여값어치
- $socialcp_refund_help_34 : 본래환불금액
- $socialcp_refund_help_35 : 실제환불
**/
function socialcp_cancel_memo($data, $coupon_remain_real_percent, $coupon_real_total_price, $coupon_remain_real_price, $coupon_remain_price, $coupon_deduction_price) {
		$socialcp_refund_help_11 = date("Y년 m월 d일", strtotime($data['couponinfo']['social_start_date']))."~".date("Y년 m월 d일", strtotime($data['couponinfo']['social_end_date']));
		if( date("Ymd")>substr(str_replace("-","",$data['couponinfo']['social_end_date']),0,8) ) {
			$socialcp_refund_help_12 = "종료 후";
			$socialcp_refund_help_21 = $data['couponinfo']['coupon_use_return_status']."";
		}else{
			$socialcp_refund_help_12 = "시작 전";
			$socialcp_refund_help_21 = $data['couponinfo']['socialcp_cancel_refund_day_status']." 취소(환불)";
		}

		if( $data['coupon_value'] == $data['coupon_remain_value'] ) {//전체체크 미사용
			$socialcp_refund_help_13 = "전체미사용";
		}else{
			$socialcp_refund_help_13 = "부분미사용";
		}

		$socialcp_refund_help_1 = "유효기간(".$socialcp_refund_help_11.") " .$socialcp_refund_help_12. " & " . $socialcp_refund_help_13;
		$socialcp_refund_help_2 = $socialcp_refund_help_21."";//

		$socialcp_refund_help_31 = end(explode(":",$data['couponinfo']['coupon_value']));
		$socialcp_refund_help_32 = get_currency_price($coupon_real_total_price);
		$socialcp_refund_help_33 = end(explode(":",$data['couponinfo']['coupon_remain']));
		$socialcp_refund_help_34 = get_currency_price($coupon_remain_real_price);
		if( $coupon_remain_price != $coupon_real_total_price ) $socialcp_refund_help_35 = "▶".get_currency_price($coupon_remain_price,3);

		$socialcp_refund_help_3 = "값어치 : ".trim($socialcp_refund_help_31)."/ 구매금액 : ".$socialcp_refund_help_32." → 잔여값어치 : ".trim($socialcp_refund_help_33)." / 환불 : ".get_currency_price($socialcp_refund_help_34,3).$socialcp_refund_help_35;

		$cancel_memo = "신청상태 : {$socialcp_refund_help_1}
		취소조건 : {$socialcp_refund_help_2}
		환불계산 : {$socialcp_refund_help_3}";
	return $cancel_memo;
}

//티켓상품 : 주문상태
function get_socialcp_status($socialcp_status) {
	foreach($socialcp_status as $key=>$val){
		$idx++;
		/**if( $key == 3 ) {
			$socialcp_status_loop[$idx]['title'] = $val[0];
			$socialcp_status_loop[$idx]['desc'] = $val[1];
			$socialcp_status_loop[$idx]['number'] = $val[2];
			$socialcp_status_loop[$idx]['key'] = $key;
			$idx++;
		}**/
		$socialcp_status_loop[$idx]['title'] = $val[0];
		$socialcp_status_loop[$idx]['desc'] = $val[1];
		$socialcp_status_loop[$idx]['number'] = $val[2];
		$socialcp_status_loop[$idx]['key'] = $key;
	}
	return $socialcp_status_loop;
}

// 쿠폰상품 출고완료 메일
function coupon_send_mail_step55($export_code, $send_email){
	if	($send_email){
		$CI =& get_instance();
		$CI->load->model('ordermail');
		$CI->load->model('ordermodel');
		$CI->load->model('exportmodel');

		$export	= $CI->exportmodel -> get_export($export_code);
		$order	= $CI->ordermodel -> get_order($export['order_seq']);
		$items	= $CI->exportmodel -> get_export_item($export_code);
		$item	= $items[0];

		// 옵션 문자열화
		$options_arr	= get_options_print_array($item, ':');
		if	($options_arr)		$item['options_str']	= implode(' / ', $options_arr);

		// 취소 정책
		$config_cancel	= $CI->ordermodel -> get_order($export['order_seq'], $item['item_seq']);

		// 쿠폰번호
		$export['coupon_serial']	= $item['coupon_serial'];
		// 회차
		$export['couponNum']		= $item['coupon_st'].'/'.$item['opt_ea'];
		// 값어치
		if	($item['coupon_input'] > 0){
			$export['exists_option']++;
			if	($item['socialcp_input_type'] == 'pass'){
				$export['coupon_input_count']	= $item['coupon_input'];
				$item['options_str']			.= '[' . number_format($item['coupon_input']) . '회]';
			}else{
				$export['coupon_input_price']	= $item['coupon_input'];
				$item['options_str']			.= '[' . get_currency_price($item['coupon_input'],3) . ']';
			}
		}
		// 옵션 목록화
		$export['optionlist']	= $options_arr;
		$export['cancel_rule']	= 'option';
		$export['cancel_day']	= $config_cancel['socialcp_cancel_day'];
		$export['refund_rule']	= $config_cancel['socialcp_use_return'];
		if	($config_cancel['socialcp_cancel_type'] == 'pay')	$export['cancel_rule']	= 'pay';

		if	($item['opt_type'] == 'sub'){
			$item['price']							= $item['price'] * $item['ea'];
			$item['sub_options']					= $item['options_str'];
			$goods[$item['option_seq']]['sub'][]	= $item;
		}else{
			$goods[$item['option_seq']]['price']		+= $item['price'] * $item['ea'];
			$goods[$item['option_seq']]['ea']			+= $item['ea'];
			$goods[$item['option_seq']]['goods_name']	= $item['goods_name'];
			$goods[$item['option_seq']]['options']		= $item['options_str'];
			$goods[$item['option_seq']]['inputs']		= $CI->ordermodel->get_input_for_option($item['item_seq'], $item['option_seq']);
		}

		if( $order['recipient_address_street'] && $order['recipient_address_type'] != 'zibun' ) $order['recipient_address'] = $order['recipient_address_street'];

		$CI->ordermail->step 			=  55;
		$CI->ordermail->export_code =  array($export_code);
		$CI->ordermail->from_email 	= $CI->config_basic['companyEmail'];
		$CI->ordermail->from_name 	= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
		$CI->ordermail->subject 		= $CI->config_email["released_title"];
		$data_arr = $CI->ordermail->get_mail_info();
		$CI->template->assign($data_arr);
		
		$file_path	= "../../data/email/".get_lang(true)."/coupon_released.html";
		$CI->template->assign(array('order'=>$order,'goods'=>$goods,'export'=>$export));
		$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";
		$CI->template->define(array('tpl'=>$file_path));
		$out = $CI->template->fetch("tpl");

		$email = config_load('email');
		$email['released_skin']	= $out;
		$email['member_seq']	= $order['member_seq'];
		$email['shopName']		= $CI->config_basic['shopName'];
		$email['ordno']				= $order['order_seq'];
		$email['user_name']	= $order['order_user_name'];

		$result	= sendMail($send_email, 'coupon_released', '', $email);
		return $result;
	}

	return false;
}

// 티켓상품 출고완료 SMS를 상품 옵션별로 그룹핑하여 보내기
function coupon_send_mail_step55_for_option($result_export_code, $send_email){

	if	(!$send_email) return false;
	$CI =& get_instance();
	$CI->load->model('ordermail');

	$email_mode 						= "email";
	$CI->config_email					= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);

	## 고객/관리자에게 발송여부 확인
	$case			= "coupon_released";
	$send_yn		= "Y";
	$send_admin_yn	= ($CI->config_email[$case."_admin_yn"])?$CI->config_email[$case."_admin_yn"]:'N';

	$CI->ordermail->set_user_yn = $send_yn;		//고객에게발송 사용여부
	if($send_admin_yn == "Y") $CI->ordermail->set_admin_yn = "Y";

	if($send_yn == "Y" || $send_admin_yn == "Y"){
		$CI->ordermail->step 			=  55;
		$CI->ordermail->goods_kind 	= "ticket";
		$CI->ordermail->export_code =  $result_export_code;
		$CI->ordermail->from_email 	= $CI->config_basic['companyEmail'];
		$CI->ordermail->from_name 	= !$CI->config_basic['shopName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $CI->config_basic['shopName'];
		$CI->ordermail->to_email 		= $send_email;
		$CI->ordermail->subject 		= $CI->config_email['coupon_released_title'];
		$data_arr 							= $CI->ordermail->get_mail_info();

		$CI->ordermail->set_contents_file();
		$CI->ordermail->set_subject($data_arr);
		$CI->ordermail->set_body($data_arr);
		if($send_yn == "Y"){
			$CI->ordermail->send();
			$CI->ordermail->send_log();
		}
		if($send_admin_yn == "Y"){ //관리자발송
			$CI->ordermail->memo		= "admin";
			$CI->ordermail->to_email	= $CI->config_email[$case."_admin_email"];
			$CI->ordermail->send();
			$CI->ordermail->send_log();
		}
	}
	return true;
}

// 티켓상품 출고완료 SMS를 상품 옵션별로 그룹핑하여 보내기
function coupon_send_sms_step55_for_option($result_export_code, $send_sms, $send_sms2=''){

	if	(!$send_sms) return false;

	$CI =& get_instance();
	$CI->load->model('ordermodel');
	$CI->load->model('exportmodel');

	$config_system	= config_load('system');

	// 마지막 출고번호 같은상품의 출고 이므로 마지막 출고 번호만으로 출고 정보 가져오기
	$last_export_code = end($result_export_code);
	$export	= $CI->exportmodel -> get_export($last_export_code);
	$order	= $CI->ordermodel -> get_order($export['order_seq']);

	foreach( $result_export_code as $export_code ){
		$items	= $CI->exportmodel -> get_export_item($export_code);
		$item	= $items[0];
		$arr_coupon_serial[] = $item['coupon_serial']; // 티켓번호
		$arr_coupon_st_ea[] = $item['coupon_st'].'/'.$item['opt_ea'];//티켓

		$end_st				= $item['coupon_st'];
	}

	$params['couponNum']	= implode(',',$arr_coupon_st_ea);// 회차


	// 값어치
	if( $item['coupon_input'] > 0 ){
		if	($item['socialcp_input_type'] == 'pass')
			$params['coupon_value']	= '사용가능 : '.number_format($item['coupon_input'])."회";
		else
			$params['coupon_value']	= '사용가능 : '.get_currency_price($item['coupon_input'],3);
	}

	$providerList[]	= $item['provider_seq'];

	$params['shopName']		= $CI->config_basic['shopName'];
	$params['ordno']		= $order['order_seq'];
	$params['user_name']	= $order['order_user_name'];
	$params['member_seq']	= $order['member_seq'];						// 회원seq

	// 치환코드
	$params['order_user']	= $order['order_user_name'];				// 주문자명
	$params['recipient_user'] = $order['recipient_user_name'];				// 주문자명
	$params['goods_name']	= $item['goods_name'];						// 상품명

	$params['coupon_serial'] = implode(chr(10),$arr_coupon_serial); // 티켓번호

	// 옵션
	$options_arr	= get_options_print_array($item, ':');
	if	($options_arr)		$params['options']	= implode(chr(10), $options_arr);
	$commonSmsData['coupon_released']['phone'][] = $send_sms;;
	$commonSmsData['coupon_released']['params'][] = $params;
	$commonSmsData['coupon_released']['order_no'][] = $order['order_seq'];


	sendSMS_for_provider('coupon_released', $providerList, $params);

	# 주문자와 받는분이 다를때 주문자에게도 문자 전송
	if( $send_sms2 && (preg_replace("/[^0-9]/", "", $send_sms) !=  preg_replace("/[^0-9]/", "", $send_sms2))){
		$commonSmsData['coupon_released2']['phone'][] = $send_sms2;;
		$commonSmsData['coupon_released2']['params'][] = $params;
		$commonSmsData['coupon_released2']['order_no'][] = $order['order_seq'];
	}

	if(count($commonSmsData) > 0){
		commonSendSMS($commonSmsData);
	}

	return $result;
}


# 재귀호출을 통한 배열 데이터 urlencode/urldecode @2015-09-03 pjm
function array_endecoder($mode='urldecode',$data){

	foreach($data as $k=>$v){
		if(is_array($v)){
			$data[$k] = array_endecoder($mode,$v);
		}else{
			if($mode == "urldecode"){
				$data[$k] = urldecode($v);
			}else if($mode == "rawurldecode"){
				$data[$k] = rawurldecode($v);
			}else{
				$data[$k] = urlencode($v);
			}
		}
	}

	return $data;
}

# Npay 2.1 버전 사용 여부 체크 2016-03-11 pjm
function npay_useck(){
	$npayconfig = config_load("navercheckout");
	if(in_array($npayconfig['use'],array('y','test')) && $npayconfig['version'] == '2.1'){
		$npay_use = true;
	}else{
		$npay_use = false;
	}
	return $npay_use;
}

# 톡체크아웃 사용 여부 2021-04-29 hyem
function talkbuy_useck(){
	$talkbuy = getTalkbuyConfig();
	if(in_array($talkbuy['use'],array('y','test'))){
		$talkbuy_use = true;
	}else{
		$talkbuy_use = false;
	}
	return $talkbuy_use;
}

function getTalkbuyConfig()
{
	// talkbuy 설정 정보 가져오기
	$talkbuyConfigs = config_load("talkbuy");
	
	/**
	 * 톡체크아웃 "사용안함" 상태지만 "연동심사" 진행을 위해서 "톡체크아웃" 버튼을 노출 시킨다.
	 * 
	 * 조건
	 * 1. 상점인증키가 등록 되어 있어야한다.
	 * 2. 톡체크아웃 API에 상점 상태 조회시 값 "INACTIVE(미연동)" 
	 */
	if (
		// 상점키 존재
		strlen($talkbuyConfigs['shopKey']) > 0 
		// 톡체크아웃 미연동 상태
		&& $talkbuyConfigs['talkbuy_service_status'] === 'INACTIVE'
	) {
		$talkbuyConfigs['use'] = 'y';
	}

	return $talkbuyConfigs;
}

# 네이버페이 주문형 버튼 노출 여부
function getNaverpayConfig()
{
	// 네이버페이 설정 정보 가져오기
	$navercheckoutCfg = config_load("navercheckout");
	$naverpaymentCfg = config_load('naverpayment');
	
	/**
	 * 네이버페이 버튼 노출 관련 정책에 따라 버튼을 노출 시킨다.
	 * 
	 * 조건
	 * 1. 주문형 + 결제형 연동 시 : 주문형 버튼 상시 노출
	 * 2. 주문형만 연동 시 : 회원로그인, 비로그인 상태 모두 주문형 버튼 노출
	 * 3. 결제형만 연동 시 : 주문형 버튼 미 노출
	 */
	if (
		// 주문형 + 결제형 연동
		strtolower($navercheckoutCfg['use']) =='y' 
		&& strtolower($naverpaymentCfg['use']) =='y'
	) {
		$navercheckoutCfg['btnView'] = 'all';
	} elseif (
		// 주문형만 연동
		strtolower($navercheckoutCfg['use']) =='y'
	) {
		$navercheckoutCfg['btnView'] = 'all';
	} else {
		$navercheckoutCfg['btnView'] = 'none';
	}

	return $navercheckoutCfg;
}

/**
*## 상품의 가장큰 할인율,가장빨리 종료되는 이벤트 정보 가져오기
* price 할인가 적용시
* goods_seq 상품고유번호
* r_category_code 적용카테고리
* consumer_price 정가 적용시
* $data_goods socialcp_event 단독이벤트만 적용시 이벤트간이 아닌경우 판매중지로 노출(프론트)
**/
function get_event_price($price, $goods_seq, $r_category_code, $consumer_price=0, $data_goods=null, $cart_today='')
{
	$CI					=& get_instance();
	$todaytime		= date('H');
	$app_week_arr	= array("1"=>"월요일","2"=>"화요일","3"=>"수요일","4"=>"목요일","5"=>"금요일","6"=>"토요일","7"=>"일요일");

	if(!is_array($r_category_code)){
		$CI->load->model('categorymodel');
		$r_category_code = $CI->categorymodel->split_category($r_category_code);
	}
	if(!is_array($r_category_code)) $r_category_code = array($r_category_code);
	$where_category = "'".implode("','",$r_category_code)."'";
	
	if(!$cart_today){
		$today		= date('Y-m-d H:i:s');
		$today_w	= date('w');
	}else{
		$today		= $cart_today['today'];
		$today_w	= $cart_today['todayw'];
	}
	if( $today_w == 0 ) $today_w	= 7;
	
	// 가져올 필드
	$selectFields = "b.*,e.title,e.start_date,e.end_date,e.event_type,e.app_week,e.app_start_time,
e.app_end_time,e.tpl_path, e.event_order_cnt,e.event_order_ea,e.event_order_price,e.daily_event,
e.use_coupon,e.use_coupon_shipping,e.use_coupon_ordersheet,e.use_code,e.use_code_shipping";
	
	// fm_event_choice에서 데이터를 가져오는 서브쿼리를 조건별로 키값을 지정하여 배열로 생성
	$eventChoiceList = array(
	    'goods'               =>  'goods_seq',
	    'except_goods'        =>  'goods_seq',
	    'category'            =>  'category_code',
	    'except_category'     =>  'category_code',
	);	
	$eventChoiceQuery = array();
	foreach($eventChoiceList as $field => $cond) {
	    $subQuery = $CI->db->select("count(*)", false)
	    ->from("fm_event_choice")
	    ->where("event_benefits_seq = b.event_benefits_seq", null, false)
	    ->where("choice_type", $field);
	    if($cond === 'goods_seq') {
	        $subQuery = $subQuery->where($cond, $goods_seq);
	    } else if($cond === 'category_code') {
	        $subQuery = $subQuery->where("category_code in ( $where_category )", null, false);
	    }
	    $subQuery = $subQuery->get_compiled_select();
	    $eventChoiceQuery[$field] = $subQuery;
	}
	
	// 단독 이벤트 가져오는 쿼리
    $solo_query = $CI->db->select($selectFields.",e.title_contents,e.bgcolor,e.goods_desc_popup", false)
	->from("fm_event_benefits b")
	->join("fm_event e", "b.event_seq=e.event_seq", "left")
	->where("e.goods_rule", 'goods_view')
	->where("e.display", 'y')
	->where("e.event_type", 'solo')
	->where("e.start_date <= '{$today}'", null, false)
	->where("e.end_date >= '{$today}'", null, false)
	->where("(
    ( e.app_week = '' OR e.app_week = '0' OR e.app_week IS NULL )
    OR
    ( e.app_week like '%{$today_w}%'  AND LEFT(e.app_start_time,2) <= '{$todaytime}' AND LEFT(e.app_end_time,2) >= '{$todaytime}')
)", null, false)
    ->where("({$eventChoiceQuery['goods']}) > 0", null, false)
    ->get_compiled_select();
	
    $query			= 'select * from ('.$solo_query.') t order by t.event_sale desc,t.end_date asc limit 1';
    $query			= $CI->db->query($query);
	$data_event	= $query->row_array();

	if( !$data_event ) {
		// 특정요일에만
		$week_number = date('w');
		if(  $week_number == 0 ) $week_number = 7;
		$str_where_week = "(e.app_week = '' or e.app_week = '0' or  e.app_week is null or e.app_week like '%".$week_number."%')";
		
		// 특정 시간 에만
		$str_where_time = "(e.app_start_time is null OR e.app_start_time='' OR LEFT(e.app_start_time,2) <= '".$todaytime."') and (e.app_start_time is null OR e.app_start_time='' OR LEFT(e.app_end_time,2) >= '".$todaytime."')";

		// 쿼리 공통 데이터
		$rListCommon = array(
			'select' => $selectFields,
			'from'   => 'fm_event_benefits b',
			'join'   => array("table" => "fm_event e", "on" => "b.event_seq=e.event_seq"),
			'where'  => array(
				array('field' => 'e.display', 'value' => 'y'),
				array('field' => 'e.event_type', 'value' => 'multi'),
				array('field' => "e.start_date <= '{$today}'"),
				array('field' => "e.end_date >= '{$today}'"),
				array('field' => $str_where_week),
				array('field' => $str_where_time),
			)
		);
		
		// 입점사 상품일 경우
		if($data_goods['provider_seq'] != '1') {
			$rListCommon['where'][] = array('field' => "b.provider_list LIKE '%|{$data_goods['provider_seq']}|%'");
		} else { // 본사 상품일 경우
			$rListCommon['where'][] = array('field' => "(b.provider_list = '' OR b.provider_list IS NULL)");
		}
		$rList = array($rListCommon, $rListCommon, $rListCommon);
		
		// 전체 상품
		$rList[0]['where'][] = array('field' => 'e.goods_rule', 'value' => 'all');
		$rList[0]['where'][] = array('field' => "({$eventChoiceQuery['except_category']}) = 0");
		$rList[0]['where'][] = array('field' => "({$eventChoiceQuery['except_goods']}) = 0");
		
		// 카테고리로 선정
		$rList[1]['where'][] = array('field' => 'e.goods_rule', 'value' => 'category');
		$rList[1]['where'][] = array('field' => "({$eventChoiceQuery['category']}) > 0");
		$rList[1]['where'][] = array('field' => "({$eventChoiceQuery['except_category']}) = 0");
		$rList[1]['where'][] = array('field' => "({$eventChoiceQuery['except_goods']}) = 0");
		
		// 상품으로 선정
		$rList[2]['where'][] = array('field' => 'e.goods_rule', 'value' => 'goods_view');
		$rList[2]['where'][] = array('field' => "({$eventChoiceQuery['goods']}) > 0");
		
		$r_query = array();
		foreach($rList as $rData) {
			$query = $CI->db->select($rData['select'], false)
			->from($rData['from'])
			->join($rData['join']['table'], $rData['join']['on']);
			foreach($rData['where'] as $row) {
				if(isset($row['value'])) {
					$query = $query->where($row['field'], $row['value']);
				} else {
					$query = $query->where($row['field'], null, false);
				}
				
			}
			$r_query[] = $query->get_compiled_select();
		}
		$query = 'select * from (('.implode(') union (',$r_query).')) t order by t.event_sale desc,t.end_date asc limit 1';
		$query = $CI->db->query($query);
		$data_event = $query->row_array();
	}

	$data_event['event_sale_unit'] = $data_event['event_reserve_unit'] = $data_event['event_point_unit'] = 0;
	$event_price = $price;
	if($data_event['target_sale'] == 1 && $consumer_price > 0) {//정가기준 정가가 있을때
		$consumer_price = get_cutting_price($consumer_price);
		$event_price = $consumer_price;
		$data_event['event_sale_unit'] =  get_cutting_price( $consumer_price * $data_event['event_sale'] / 100 );
	}else if($data_event['target_sale'] == 2){
		$data_event['event_sale_unit'] = get_cutting_price($data_event['event_sale']);		
	}else{
		$data_event['event_sale_unit'] = get_cutting_price($price * $data_event['event_sale'] / 100);
	}
	

	### 이벤트 할인가가 할인 기준가(정가or판매가)보다 높을 경우 적용 불가 22.02.25
	if(is_array($data_event) === true && $data_event['event_sale_unit'] > $event_price) 
		unset($data_event);

	### EMONEY -> 실 결제금액 기준
	if($data_event['event_reserve']) $data_event['event_reserve_unit']	=  get_cutting_price( $price * $data_event['event_reserve'] / 100);
	### POINT -> 실 결제금액 기준
	if($data_event['event_point']) $data_event['event_point_unit']		=  get_cutting_price( $price * $data_event['event_point'] / 100);

	if( $data_event['event_sale_unit'] )			$data_event['event_sale_unit']			= get_price_point($data_event['event_sale_unit']);
	if( $data_event['event_reserve_unit'] )		$data_event['event_reserve_unit']		= get_price_point($data_event['event_reserve_unit']);
	if( $data_event['event_point_unit'] )			$data_event['event_point_unit']			= get_price_point($data_event['event_point_unit']);

	if($data_event['daily_event'] && $data_event['app_week']){
		for($i=0;$i<strlen($data_event['app_week']);$i++) {
			$app_week = substr($data_event['app_week'],$i,1);
			if($app_week_arr[$app_week])$app_week_title[] = $app_week_arr[$app_week];
		}
		$data_event['app_week_title'] = implode(', ',$app_week_title);
		$data_event['app_start_time_title'] = substr($data_event['app_start_time'],0,2).":".substr($data_event['app_start_time'],2,2);
		$data_event['app_end_time_title'] = substr($data_event['app_end_time'],0,2).":".substr($data_event['app_end_time'],2,2);
	}
	return $data_event;
}

# 검색필드 배열정의 @2016-09-28 pjm
function search_arr_field(){

	// 검색 항목 설정
	$return['arr_search_keyword'] = array(
							"order_seq"				=> "주문번호",
							"order_user_name"		=> "주문자명",
							"depositor"				=> "입금자명",
							"userid"				=> "아이디",
							"order_cellphone"		=> "휴대전화",
							"order_email"			=> "이메일",
							"recipient_user_name"	=> "수령자명",
							"recipient_cellphone"	=> "휴대전화",
							"recipient_phone"		=> "일반전화",
							"goods_name"			=> "상품명",
							"goods_seq"				=> "상품번호",
							"bar_code"				=> "바코드",
							"npay_order_id"			=> "네이버페이 주문번호",
							);
	if(!$npay_use) unset($return['arr_search_keyword']['npay_order_id']);
	$return['arr_order_goods_type'] = array(
							"adult"				=> "성인상품",
							"withdraw"			=> "청약철회불가",
							"international_shipping"=> "구매대행",
							"reserve"			=> "예약상품",
							"package"			=> "패키지/복합상품",
							"gift"				=> "사은품",
							"ticket"			=> "티켓",
							);

	$return['arr_order_pg']		= array(
							"normal"		=> "일반PG사",
							"kakaopay"		=> "카카오페이",
							"talkbuy"		=> "톡체크아웃",
							"payco"			=> "페이코",
							"npay"			=> "네이버페이 주문형",
							"naverpayment"	=> "네이버페이 결제형",
							"paypal"		=> "페이팔",
							"eximbay"		=> "엑심베이",
//							"alipay"		=> "알리페이",
//							"exiz"			=> "엑시즈",
						);
	$return['arr_order_payment'] = array(
							"card"			=> "신용카드",
							"cellphone"		=> "휴대폰",
							"account"		=> "계좌이체",
							"escrow_account"=> "에스크로계좌이체",
							"virtual"		=> "가상계좌",
							"escrow_virtual"=> "에스크로가상계좌",
							"point"			=> "포인트",
							"pay_later"		=> "후불결제",
						);

	return $return;
}

// 상품(옵션) 노출/미노출 확인
function check_view_option($goods_seq,$option1,$option2,$option3,$option4,$option5) {
	$CI =& get_instance();

	$option_view = $CI->goodsmodel->get_goods_option(
		$goods_seq,
		array(
			'option1'=> $option1,
			'option2'=> $option2,
			'option3'=> $option3,
			'option4'=> $option4,
			'option5'=> $option5
		)
	);

	return $option_view[0]['option_view'] == 'Y' ? false : true;
}

// 상품(추가옵션) 노출/미노출 확인
function check_view_suboption($goods_seq,$title,$option) {
	$CI =& get_instance();

	$option_view = $CI->goodsmodel->get_goods_suboption(
		$goods_seq,
		array(
			'suboption_title'=> $title,
			'suboption'=> $option
		)
	);

	return $option_view[0][0]['option_view'] == 'Y' ? false : true;
}

/**
* 티켓상품 결제완료 후 자동 출고처리
* @2017-08-16
**/
function ticket_payexport_ck($order_seq) {
	$CI =& get_instance();
	if(!$CI->exportmodel) $CI->load->model('exportmodel');
	$export_cnt = $CI->exportmodel->coupon_payexport($order_seq);
	return $export_cnt;
}

/**
 * 간편결제 파트너 주문 마켓 네임 반환
 * @2021-04-29
 */
function order_market_name($order) {
	if($order["npay_order_id"]) {
		$marketname = "네이버페이";
	} else if($order["talkbuy_order_id"]) {
		$marketname = "톡체크아웃";
	}
	return $marketname;
}

/**
 * 선물하기 주문이면 true
 */
function is_order_present($order) {
	if($order['label'] === 'present') {
		return true;
	}
	return false;
}

/**
 * 배송지 등록되어있으면 true 
 */
function has_recipient_zipcode($order) {
	if( $order['recipient_zipcode'] == '') {
		 return false;
	}
	return true;
}

/**
 * 간편결제 주문이면 true
 * 페이코, 네이버페이
 */
function is_order_simple_payment($order) {
	if(in_array($order['pg'], ['payco', 'naverpayment'])) {
		return true;
	}
	return false;
}

/**
* 약관 내용 입점사명 치환
*/ 
function privacy_converting($privacy, $provider_name) {
	return str_replace('{sellerName}', implode(', ', $provider_name), $privacy);
}

// END
/* End of file order_helper.php */
/* Location: ./app/helpers/order_helper.php */