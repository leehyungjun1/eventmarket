<?php
/**
 * @author lgs
 * @version 1.0.0
 * @license copyright by GABIA_lgs
 * @since 12. 5. 29 16:54 ~
 */

function use_shipping_method($provider_seq=1){
	$CI =& get_instance();
	$loop = $result = "";
	$codes = code_load('shipping');
	$CI->load->model('providershipping');
	$data_provider_shipping = $CI->providershipping->get_provider_shipping($provider_seq);
	foreach($data_provider_shipping['shipping_method'] as $code => $method){
		$data['code'] = $code;
		$data['method'] = $method;
		if( $code=='delivery' ){
			$data['deliveryCompanyCode'] = $data_provider_shipping['deliveryCompanyCode'];
			$data['deliveryCostPolicy'] = $data_provider_shipping['delivery_cost_policy'];
			$data['payDeliveryCost']	= $data_provider_shipping['pay_delivery_cost'];
			$data['postpaidDeliveryCostYn']	= $data_provider_shipping['postpaid_delivery_cost_yn'];
			$data['postpaidDeliveryCost']	= $data_provider_shipping['postpaid_delivery_cost'];
			$data['ifpayDeliveryCost'] = $data_provider_shipping['ifpay_delivery_cost'];
			$data['ifpayFreePrice'] = $data_provider_shipping['ifpay_free_price'];
			$data['ifpostpaidDeliveryCostYn']	= $data_provider_shipping['ifpostpaid_delivery_cost_yn'];
			$data['ifpostpaidDeliveryCost']	= $data_provider_shipping['ifpostpaid_delivery_cost'];
			$data['sigungu'] = $data_provider_shipping['sigungu'];
			$data['addDeliveryCost'] = $data_provider_shipping['addDeliveryCost'];
			$result[] = $data;
		}

		if( $code=='postpaid' ){
			$data_postpaid = $data_provider_shipping;
			$data_postpaid['method'] =  $method;
			$data_postpaid['code'] =  $code;
			$result[] = $data_postpaid;
		}
	}

	if($result) $loop[0]= $result;

	$arr = $result = "";
	$codes = code_load('internationalShipping');
	foreach($codes as $code){
		$arr = config_load('internationalShipping'.$code['codecd']);
		$arr['code']  = $code['codecd'];
		$arr['method'] = $code['value'];
		if( isset($arr['company']) && $arr['company'] ){
			if($arr['useYn']=='y') $result[] = $arr;
		}
	}
	if( $result ) $loop[1] = $result;

	return $loop;
}

function get_shipping_company($international,$method_code,$shipping_provider_seq=1){
	$CI =& get_instance();

//	if($shipping_provider_seq){
//		$CI->load->model('invoiceapimodel');
//		foreach(get_invoice_company($shipping_provider_seq) as $k=>$data){
//			$result[$k] = $data;
//		}
//	}

	$loop = use_shipping_method($shipping_provider_seq);
	if( $international == 'domestic' ){
		foreach($loop[0] as $data){
			if( $data['code'] == $method_code )
			{
				foreach($data['deliveryCompanyCode'] as $delivery_code)
				{
					$code_num = str_replace('code','',$delivery_code);
					if($code_num >= 90){ // 자동화
						$invoice = get_invoice_company($shipping_provider_seq, $delivery_code);
						$invoice_key	= array_keys($invoice);
						$invocie_code	= $invoice_key[0];
						$result[$invocie_code] = $invoice[$invocie_code];
					}else{
						$arr = config_load('delivery_url',$delivery_code);
						$result[$delivery_code] = $arr[$delivery_code];
					}
				}
			}
		}
	}else{
		//return $loop[1];
	}

	return $result;

}

// 해당 입점사 택배사 추출 :: 2017-02-01 lwh
function get_shipping_company_provider($shipping_provider_seq=1){
	$CI =& get_instance();

	if	($shipping_provider_seq > 1){
		$deliveryCompanyCode	= config_load('providerDeliveryCompanyCode', $shipping_provider_seq);
		$deliveryCompanyCode	= $deliveryCompanyCode[$shipping_provider_seq];
	}else{
		$shipping_provider_seq	= 1;
		$deliveryCompanyCode	= config_load('shippingdelivery', 'deliveryCompanyCode');
		$deliveryCompanyCode	= $deliveryCompanyCode['deliveryCompanyCode'];
	}
	if($deliveryCompanyCode){
		foreach($deliveryCompanyCode as $k => $code){
			$code_num = str_replace('code','',$code);
			if($code_num >= 90){
				$invoice_info	= get_invoice_company($shipping_provider_seq, $code);
				if(!$invoice_info) continue;
				$tmp_key		= array_keys($invoice_info);
				$invoice_key	= $tmp_key[0];
				$result[$invoice_key]	= $invoice_info[$invoice_key];
			}else{
				$deli_info		= config_load('delivery_url',$code);
				$result[$code]		= $deli_info[$code];
			}
		}
	}

	return $result;
}

// 전체 택배사 구하기 :: 2015-07-08 lwh
function get_shipping_company_all($international,$method_code){
	$CI =& get_instance();
	$query		= "select provider_seq, company_code from fm_provider_shipping";
	$query		= $CI->db->query($query);
	$shipinfo	= $query->result_array();

	foreach($shipinfo as $key => $data_courier){
		$arr_company_code = explode('|',$data_courier['company_code']);
		foreach($arr_company_code as $data_code){
			$data = config_load('delivery_url',$data_code);

			foreach(get_invoice_company($data_courier['provider_seq']) as $k=>$tmp){
				$result[$k] = $tmp;
			}

			foreach($data as $code => $val){
				$result[$code] = $val;
			}
		}
	}

	return $result;
}

function get_invoice_company($provider_seq=1,$code=null){
	$CI =& get_instance();
	$CI->load->model('invoiceapimodel');
	$result = array();

	if($code == 'code98' || $code == Null){
		// 굿스플로 설정 정보호출 :: 2015-06-30 lwh
		$gf_terms	= config_load('goodsflow','terms');
		$gf_system	= config_load('system','goodsflow_use');
		if($gf_system['goodsflow_use'] == '1' || $provider_seq == '1'){
			$CI->load->model('goodsflowmodel');
			$gf_config	= $CI->goodsflowmodel->get_goodsflow_setting($provider_seq);
			if($gf_config['goodsflow_step'] == '1' && $gf_config['gf_use'] == 'Y'){
				$del_code				= $gf_config['deliveryCode'];
				$del_name				= $gf_config['deliveryName'];
				$system_code			= $gf_terms['terms'][$del_code]['code'];
				$delivery_arr			= config_load('delivery_url',$system_code);
				$goodsflow['company']	= $del_name.'(자동-굿스플로)'; 
				$goodsflow['url']		= get_connet_protocol().'trace.goodsflow.com/view/V1/whereis/gabia/'.$del_code.'/';
				$result['auto_'.$del_code]= $goodsflow;
			}
		}
	}

	if($code == 'code99' || $code == Null){
		// 우체국 택배 정보 호출 :: 2016-04-04 lwh
		$CI->load->model('epostmodel');
		$ep_config = $CI->epostmodel->get_epost_requestkey($provider_seq);
		if($ep_config['status'] == '9' && $ep_config['epost_use'] == 'Y'){
			$epost['company']	= '우체국(자동)';
			$epost['url']		= get_connet_protocol().'service.epost.go.kr/trace.RetrieveRegiPrclDeliv.postal?sid1=';
			$result['auto_epostnet']= $epost;
		}
	}

	if($code == 'code97' || $code == Null){
		// 롯데 택배 정보 호출
		$invoice_vendor = $CI->invoiceapimodel->get_usable_invoice_vendor($provider_seq);
		foreach($invoice_vendor as $delivery_code=>$vendor){
			$result['auto_'.$delivery_code] = array(
				'company' => $vendor['company'].'(자동)',
				'url' => $CI->invoiceapimodel->invoice_vendor_cfg[$delivery_code]['url']
			);
		}
	}

	return $result;
}

function get_international_code($key){
	$arr = $result = "";
	$codes = code_load('internationalShipping');
	foreach($codes as $code){
		$arr = config_load('internationalShipping'.$code['codecd']);
		$arr['code']  = $code['codecd'];
		$arr['method'] = $code['value'];
		if( isset($arr['company']) && $arr['company'] ){
			if($arr['useYn']=='y') $result[] = $arr;
		}
	}
	return $result[$key]['code'];
}

function get_international_company(){
	$arr = $result = "";
	$codes = code_load('internationalShipping');
	foreach($codes as $code){
		$arr = config_load('internationalShipping'.$code['codecd']);
		$arr['code']  = $code['codecd'];
		$arr['method'] = $code['value'];
		if( isset($arr['company']) && $arr['company'] ){
			if($arr['useYn']=='y') $result[] = $arr;
		}
	}
	return $result;
}

function get_delivery_company($code,$mode='company'){
	$arr = get_shipping_company('domestic','delivery');
	return $arr[$code][$mode];
}

function get_delivery_url($code=null){
	$CI =& get_instance();
	$CI->load->model('invoiceapimodel');

	$arr = config_load('delivery_url',$code);

	if($code){
		if(preg_match("/^auto_/",$code)){
			$arrAuto = $CI->invoiceapimodel->invoice_vendor_cfg[str_replace('auto_','',$code)];
			$arr = $arrAuto;
		}
	}else{
		$arrAuto = $CI->invoiceapimodel->invoice_vendor_cfg;
		foreach($arrAuto as $code=>$row){
			$arr['auto_'.$code] = $row;
		}
	}

	// 굿스플로 배송 url 추가 :: 2015-10-06 lwh
	$gf_delivery = get_invoice_company();
	foreach($gf_delivery as $k => $gf){
		$arr[$k] = $gf;
	}

	return $arr;
}

function get_domestic_method($code){

	if($code == 'postpaid'){
		$arr = code_load('shipping','delivery');
		$arr[0]['value'] = "택배 (착불)";
	}else if($code == 'delivery'){
		$arr = code_load('shipping',$code);
		$arr[0]['value'] = "택배 (선불)";
	}else{
		$arr = code_load('shipping',$code);
	}
	return $arr[0]['value'];
}

function get_international_method($code){
	$arr = code_load('internationalShipping',$code);
	return $arr[0]['value'];
}

function get_international_method_code($code){
	$arr = array('EMS'=>'code23','FEDEX'=>'code24');
	return $arr[$code];
}

function get_shipping_method($shipping_method,$mode='paytype'){

	if($mode == "paytype"){
		$prepayed			= "(".getAlert("sy004").")";	// 선불
		$cash_on_delivery	= "(".getAlert("sy003").")";	// 착불
	}else{
		$prepayed			= "";
		$cash_on_delivery	= "";
	}

	$arr_shipping_method['delivery']		= "".getAlert("sy011")."".$prepayed;			// 택배
	$arr_shipping_method['postpaid']		= "".getAlert("sy011")."".$cash_on_delivery;	// 택배
	$arr_shipping_method['each_delivery']	= "".getAlert("sy011")."".$prepayed;			// 택배
	$arr_shipping_method['each_postpaid']	= "".getAlert("sy011")."".$cash_on_delivery;	// 택배
	$arr_shipping_method['each_quick']		= "".getAlert("sy012")."".$cash_on_delivery;	// 퀵서비스
	$arr_shipping_method['each_direct']		= "".getAlert("sy013")."";						// 직접수령
	$arr_shipping_method['quick']			= "".getAlert("sy012")."".$cash_on_delivery;	// 퀵서비스
	$arr_shipping_method['direct']			= "".getAlert("sy013")."";						// 직접수령
	$arr_shipping_method['coupon']			= "".getAlert("sy014")."";						// 티켓
	$arr_shipping_method['direct_store']	= "".getAlert("sy015")."";						// 매장수령
	$arr_shipping_method['custom']			= "".getAlert("sy016")."";						// 직접입력

	if($shipping_method == 'all'){
		return $arr_shipping_method;
	}else{
		return $arr_shipping_method[$shipping_method];
	}

}

/**
 * 외부연동 택배사코드에 해당하는 택배사 코드 목록을 반환한다.
 * @param string $code
 * @return array
 */
if(function_exists('get_delivery_codes') === false) {
    function get_delivery_codes($code) {
        $CI =& get_instance();
        
        $data = array();
        switch($code) {
            case 'code97':
                $CI->load->model('invoiceapimodel');
                $data = $CI->invoiceapimodel->get_invoice_vendors();
                break;
            case 'code98':
                $CI->load->model('goodsflowmodel');
                $data = $CI->goodsflowmodel->get_goodsflow_delivery_codes();
                break;
            case 'code99':
                return array('auto_epostnet');
        }
        
        if(count($data)>0) {
            array_walk($data, function(&$item) { $item = "auto_{$item}"; });
        }
        return $data;
    }
}