<?
if ( ! defined('BASEPATH')) exit('No direct script access allowed');


/*
 * 2차원배열 금액비율 기준 내림차순
 * @param 
*/ 
function account_order_sale_desc($x, $y) {
	if ($x['sale_ratio'] == $y['sale_ratio']){
		return 0;
	} else if ($x['sale_ratio'] < $y['sale_ratio']) {
		return 1;
	} else {
		return -1;
	}
}

/*
 * 주문경로 문구처리
 * #param 무통장  PG(inicis)  네이버페이  네이버페이(PG)  카카오페이  11번가  스마트스토어  쿠팡
 * @param 
*/ 
function account_order_referer($pg, $params=null){
	$CI =& get_instance();
	if($params['linkage_id'] && $params['linkage_mall_code']){
		$referer	= $params['linkage_mall_code'];
	}elseif($params['npay_order_id'] && $params['order_referer_npay']){
		$referer	= $params['order_referer_npay'];
	}elseif(($pg=='bank' && $params['payment']== 'bank') || (!$pg || $params['payment']== 'bank')){
		$referer	= 'shop';
	}elseif($pg){
		$referer	= $pg;
	}else{
		$referer	= 'pg';
	}
	return $referer;
}

function account_order_referer_title($pg, $params=null){
	$CI =& get_instance();

	$pgArr = available_pg(['nation' => 'all']);

	if($pg == 'all'){
		$referer = "전체";
	}elseif($CI->accountallmodel->order_referer_om_ar[$pg] || $params['linkage_mall_code']){
		$openmarket = ($params['linkage_mall_code'])?$params['linkage_mall_code']:$pg;
		$referer	= $CI->accountallmodel->order_referer_om_ar[$openmarket];
	}elseif(strpos($pg, 'API') > -1){	// 샵링커인 경우
		//샵링커 추가 2017-09-27 jhs
		$CI->load->model('connectormodel');
		if(empty($CI->shopLinkermarketList)){
			$CI->accountShopLinkermarketList = array();
			$shopLinkerUseMarketList = $CI->connectormodel->getLinkageMarketGroup();
			foreach($shopLinkerUseMarketList as $marketInfo){
				$CI->shopLinkermarketList[$marketInfo['marketCode']] = array('name'=>$marketInfo['marketName'],'productLink'=>'');
			}
		}
		$referer = $CI->shopLinkermarketList[$pg]['name'];
	}else{
		if($params['npay_order_id'] && $params['order_referer_npay']){
			$referer	= $CI->accountallmodel->order_referer_ar[$params['order_referer_npay']];
		}elseif($params['order_referer'] === 'talkbuy'){
			$referer	= $CI->accountallmodel->order_referer_ar[$params['order_referer']];
		}elseif($pg=='pg' && $params['pg']){
			$referer	= $CI->accountallmodel->order_referer_ar['pg']."(".$params['pg'].")";
		}elseif($pg == $CI->config_system['pgCompany'] || in_array($pg,$pgArr)){
			$referer	= $CI->accountallmodel->order_referer_ar['shop'];
		}elseif($pg){
			$referer	= $CI->accountallmodel->order_referer_ar[$pg];
		}elseif(!$pg && $params['payment']== 'bank'){
			$referer	= $CI->accountallmodel->order_referer_ar['shop'];
		}else{
			$referer	= $CI->accountallmodel->order_referer_ar['pg']."(".$CI->config_system['pgCompany'].")";
		}
	}
	return $referer;
}

/*
 * 주문경로별 결제수단 처리
 * #param 무통장  PG(inicis)  네이버페이  네이버페이(PG)  카카오페이  11번가  스마트스토어  쿠팡
 * @param
*/ 
function account_order_referer_payment($referer){
	switch($referer){
	 case "npay":
	 case "npg":
	 case "open11st":
	 case "coupang":
	 case "naverstorefarm":
		$paymentar	= array($referer.'_card',$referer.'_cellphone',$referer.'_account',$referer.'_virtual',$referer.'_point',$referer.'_account');
		break;
	 case "pg":
		$paymentar	= array('card','cellphone','account','virtual','account');
		break;
	 case "kakaopay":
		$paymentar	= array('kakaopay');//현재는 card 결제만가능
		break;
	 case "shop":
		$paymentar	= array('bank');
		 break;
	}
	return $paymentar;
}

/*
* 정산/매출 필수옵션정보 추출
 * #acinsdata 주문정보
 * #optdata 옵션정보
 * #paycharge 전자결제정보
 * #cal 매출/정산 구분
 * @param 
*/
function account_ins_option_ck($acinsdata, $optdata,$paycharge, $cal=null ) {
	$CI =& get_instance();
	if($optdata['step'] == '95' || $optdata['step'] == '99') return;

	//네이버페이 미입금취소(API)로 옵션상태는 주문무효'95'이면서 주문상태는 결제취소'85'는 예외처리 
	if($acinsdata['npay_order_id'] && ($optdata['step'] == '95' && $optdata['step'] == '85') ) return;
	if($acinsdata['npay_order_id'] && !$acinsdata['deposit_date']) {
		//네이버페이 결제일이 없으면 npay결제일 또는 수집일을 기준으로
		$optdata['deposit_date'] = ($acinsdata['npay_order_pay_date'])?$acinsdata['npay_order_pay_date']:$acinsdata['regist_date'];
	}

	$optdata['account_type']		= ($acinsdata['orign_order_seq'])?"exchange":"order";
	$optdata['order_type']			= "option";
	$optdata['account_target']		= get_account_target($acinsdata, $optdata);//정산대상(매출/정산)
	
	$optdata['order_type_view'] 	= $CI->accountallmodel->order_type_ar[$optdata['order_type']];
	$optdata['order_form_seq']		= $optdata['item_option_seq'];
	$optdata['provider_name']		= ($optdata['provider_name'])?$optdata['provider_name']:$acinsdata['provider_name'];
	/**
	사용적립금/이머니/에누리
	**/
	$optdata['out_emoney_use']		= ($optdata['emoney_sale_unit']*$optdata['ea'])+$optdata['emoney_sale_rest'];
	$optdata['out_cash_use']		= ($optdata['cash_sale_unit']*$optdata['ea'])+$optdata['cash_sale_rest'];
	$optdata['out_enuri_use']		= ($optdata['enuri_sale_unit']*$optdata['ea'])+$optdata['enuri_sale_rest'];
	$optdata['out_npay_point_use']	= ($optdata['npay_point_sale_unit']*$optdata['ea'])+$optdata['npay_point_sale_rest'];

	/**
	할인항목별 처리시작
	**/
	if($optdata['multi_sale'] && !$optdata['multi_sale_unit'] )		$optdata['multi_sale_unit']	= $optdata['multi_sale'];
	if($optdata['event_sale'] && !$optdata['event_sale_unit'] )		$optdata['event_sale_unit']	= ($optdata['event_sale']/$optdata['ea']);
	if($optdata['member_sale'] && !$optdata['member_sale_unit'] )	$optdata['member_sale_unit']	= $optdata['member_sale'];

	acc_promotion_sales_unit('emoney',	$optdata['emoney_sale_unit'],	$optdata['emoney_sale_provider'],		$optdata);
	acc_promotion_sales_unit('cash',	$optdata['cash_sale_unit'],		$optdata['cash_sale_provider'],			$optdata);
	acc_promotion_sales_unit('enuri',	$optdata['enuri_sale_unit'],	$optdata['enuri_sale_provider'],		$optdata);
	acc_promotion_sales_unit('npay_point',$optdata['npay_point_sale_unit'],$optdata['npay_point_sale_provider'],$optdata);

	acc_promotion_sales_unit('multi',	$optdata['multi_sale_unit'],	$optdata['multi_sale_provider'],	$optdata);
	acc_promotion_sales_unit('event',	$optdata['event_sale_unit'],	$optdata['event_sale_provider'],	$optdata);		//입점사 할인부담율로 재계산
	acc_promotion_sales_unit('member',	$optdata['member_sale_unit'],	$optdata['member_sale_provider'],	$optdata);
	acc_promotion_sales_unit('coupon',	$optdata['coupon_sale_unit'],	$optdata['coupon_sale_provider'],	$optdata);
	acc_promotion_sales_unit('fblike',	$optdata['fblike_sale_unit'],	$optdata['fblike_sale_provider'],	$optdata);
	acc_promotion_sales_unit('mobile',	$optdata['mobile_sale_unit'],	$optdata['mobile_sale_provider'],	$optdata);
	acc_promotion_sales_unit('code',	$optdata['code_sale_unit'],		$optdata['code_sale_provider'],		$optdata);
	acc_promotion_sales_unit('referer',	$optdata['referer_sale_unit'],	$optdata['referer_sale_provider'],	$optdata);
	/**
	할인항목별 처리끝
	**/

	/**
	** 할인부담금 시작
	$optdata['salescost_admin_sales'] = $optdata['salescost_event'] + $optdata['salescost_event_rest']
	   + $optdata['salescost_multi'] + $optdata['salescost_multi_rest']
	   + 
	$optdata['salescost_provider_sales'] =    $optdata['salescost_event_provider'] + $optdata['salescost_event_provider_rest']
	   + $optdata['salescost_multi_provider'] + $optdata['salescost_multi_provider_rest']
	   + 
	$salescost_sales =   $optdata['multi_sale'] + $optdata['multi_sale_rest']
												   + $optdata['event_sale'] + $optdata['event_sale_rest']
												   + 
	**/
	// 본사 부담금
	$optdata['salescost_admin_promotion']	= $optdata['salescost_emoney'] + $optdata['salescost_emoney_rest']
											   + $optdata['salescost_cash'] + $optdata['salescost_cash_rest']
											   + $optdata['salescost_enuri'] + $optdata['salescost_enuri_rest'];
											  // + $optdata['salescost_npay_point'] + $optdata['salescost_npay_point_rest'];
	$optdata['salescost_admin_sales'] = $optdata['salescost_event'] + $optdata['salescost_event_rest']
											   + $optdata['salescost_multi'] + $optdata['salescost_multi_rest']
											   + $optdata['salescost_member'] + $optdata['salescost_member_rest']
											   + $optdata['salescost_coupon'] + $optdata['salescost_coupon_rest']
											   + $optdata['salescost_fblike'] + $optdata['salescost_fblike_rest']
											   + $optdata['salescost_mobile'] + $optdata['salescost_mobile_rest']
											   + $optdata['salescost_code'] + $optdata['salescost_code_rest']
											   + $optdata['salescost_referer'] + $optdata['salescost_referer_rest'];
	$optdata['salescost_admin']				= $optdata['salescost_admin_promotion'] + $optdata['salescost_admin_sales'];

	//입점사 부담금
	$optdata['salescost_provider_promotion']= $optdata['salescost_emoney_provider'] + $optdata['salescost_emoney_provider_rest']
											   + $optdata['salescost_cash_provider'] + $optdata['salescost_cash_provider_rest']
											   + $optdata['salescost_enuri_provider'] + $optdata['salescost_enuri_provider_rest'];
											  // + $optdata['salescost_npay_point_provider'] + $optdata['salescost_npay_point_provider_rest'];
	$optdata['salescost_provider_sales'] =    $optdata['salescost_event_provider'] + $optdata['salescost_event_provider_rest']
											   + $optdata['salescost_multi_provider'] + $optdata['salescost_multi_provider_rest']
											   + $optdata['salescost_member_provider'] + $optdata['salescost_member_provider_rest']
											   + $optdata['salescost_coupon_provider'] + $optdata['salescost_coupon_provider_rest']
											   + $optdata['salescost_fblike_provider'] + $optdata['salescost_fblike_provider_rest']
											   + $optdata['salescost_mobile_provider'] + $optdata['salescost_mobile_provider_rest']
											   + $optdata['salescost_code_provider'] + $optdata['salescost_code_provider_rest']
											   + $optdata['salescost_referer_provider'] + $optdata['salescost_referer_provider_rest'];
	$optdata['salescost_provider']			= $optdata['salescost_provider_promotion'] + $optdata['salescost_provider_sales'];

	// 부담금 합계
	$optdata['acc_promotion_total']			= ($optdata['salescost_admin_promotion']) + ($optdata['salescost_provider_promotion']); 
	$optdata['acc_sale_total']				= $optdata['salescost_admin_sales'] + $optdata['salescost_provider_sales'];
	$optdata['salescost_total']				= $optdata['acc_promotion_total'] + $optdata['acc_sale_total'];
	/**
	** 할인부담금 끝
	**/

	/**
	정산계산식 수동처리
	**/
	// 기본통화에 맞춰서 cutting 처리 추가 :: 2018-07-24 pjw
	$optdata['price']		= get_cutting_price($optdata['price']);
	$optdata['org_price']	= get_cutting_price($optdata['org_price']);
	$optdata['sales_price']	= get_cutting_price($optdata['price']*$optdata['ea']);		//총 결제금액
	$optdata['sale_price']	= get_cutting_price($optdata['org_price']*$optdata['ea']);	//총 판매금액

	if( $CI->accountallmodel->account_fee_ar['goods'] ) {//상품별 수수료 적용시
		$acc_unit_payprice			= (int)$optdata['price'] - $optdata['salescost_total'] + $optdata['salescost_cash'] + $optdata['salescost_cash_rest'];//실결제금액(단가)
		$opt_price_tmp				= (int)$optdata['price'] - $optdata['salescost_provider'];//정산대상금액

		$salescost_provider					= array();
		$salescost_provider['event']		= $optdata['salescost_event_provider'];
		$salescost_provider['multi']		= $optdata['salescost_multi_provider'];
		$salescost_provider['member']		= $optdata['salescost_member_provider'];
		$salescost_provider['coupon']		= $optdata['salescost_coupon_provider'];
		$salescost_provider['fblike']		= $optdata['salescost_fblike_provider'];
		$salescost_provider['mobile']		= $optdata['salescost_mobile_provider'];
		$salescost_provider['code']			= $optdata['salescost_code_provider'];
		$salescost_provider['referer']		= $optdata['salescost_referer_provider'];
		$salescost_provider['promotion']	=  $optdata['salescost_provider_promotion'];

		$optdata['pay_price']		= $acc_unit_payprice;
		$optdata['target_price']	= $opt_price_tmp;

		$_commission_info				= array();
		foreach(get_commission_info_field() as $_field) $_commission_info[$_field] = $optdata[$_field];
		$_commission_info['salescost_provider'] = $salescost_provider;
		$_return_commission			= get_commission($_commission_info);
		$total_commission_price		= $_return_commission['commission_price'];
		$total_feeprice				= $_return_commission['feeprice'];
		$commission_unit_price		= $_return_commission['commission_unit_price'];
		$commission_price_rest		= $_return_commission['commission_price_rest'];
		$sales_unit_feeprice		= $_return_commission['feeprice_unit'];
		$sales_feeprice_rest		= $_return_commission['feeprice_rest'];
		$commission_text			= $_return_commission['commission_text'];

		/*
		if($optdata['commission_type'] == 'SACO' || $optdata['commission_type'] == ''){
			$opt_price = $opt_price_tmp;
			//수수료 방식 정산
			if($optdata['price']) {
				## 이벤트 기간 입점사 판매 수수료 계산
				if ($optdata['provider_seq'] > 1 && $optdata['event']['event_seq']) {
						//안쓰는듯(신정산 이전 계산식 인듯)
					if ($optdata['event']['saller_rate_type']==1) {
						$optdata['commission_rate'] = $optdata['event']['saller_rate'];
					} else if ($optdata['event']['saller_rate_type']==2) {
						// 이벤트 기간 입점사 판매 수수료 계산 수정
						$optdata['commission_rate'] = (float) ( $optdata['commission_rate'] + $optdata['event']['saller_rate'] );
					}
				}elseif ($optdata['provider_seq'] == 1){
					$optdata['commission_rate'] = 100;
				}
				//$commission_price_tmp = $optdata['price']*(100-$optdata['commission_rate'])/100;
				$acc_charge_str			= $optdata['commission_rate']."%";
				if($optdata['commission_rate']) {
					$commission_price_tmp	= $opt_price*(100-$optdata['commission_rate'])/100;
					$sales_unit_feeprice	= $opt_price*($optdata['commission_rate'])/100;//수수료(단가)
				}else{
					$commission_price_tmp	= $optdata['price'];
					$sales_unit_feeprice	= 0;
				}
			}
			//debug_var($sales_unit_feeprice."=>".$acc_unit_payprice." - ".$commission_price_tmp);
		}else{
			$opt_price = $acc_unit_payprice;
			//공급가 방식 정산
			if($optdata['commission_type'] == 'SUPR'){
				$commission_price_tmp	= $optdata['commission_rate'] - $optdata['salescost_provider'];
				$sales_unit_feeprice	= $opt_price - floor($commission_price_tmp);//수수료단가
			}else{
				$commission_price_tmp	= ((int)$optdata['consumer_price'] * $optdata['commission_rate'] /100) - $optdata['salescost_provider'];
				$sales_unit_feeprice	= $opt_price - floor($commission_price_tmp);//수수료단가
			}
			//debug_var($sales_unit_feeprice."=>".$acc_unit_payprice." - ".$commission_price_tmp);
		}//commission_rate, commission_type 저장됨

		$commission_price_tmp				= floor($commission_price_tmp);
		$commission_price					= $commission_price_tmp;//단품으로 계산 sales_unit_feeprice
		*/

		if($commission_unit_price < 0) $commission_unit_price = 0;

		$sales_unit_minfee					= 0;

		$optdata['commission_price']		= $commission_unit_price;								//개당 정산금액
		$optdata['commission_price_rest']	= $commission_price_rest;								//개당 정산금액-짜투리
		$optdata['sales_unit_feeprice']		= $sales_unit_feeprice;									//정산 수수료금액-개당
		$optdata['sales_feeprice_rest']		= $sales_feeprice_rest;									//정산 수수료금액-짜투리
		$optdata['sales_unit_minfee']		= $sales_unit_minfee;									//정산 추가수수료 개당(+)
		$optdata['sales_unit_payprice']		= $acc_unit_payprice;									//실 결제액 (개당)
		$optdata['commission_text']			= $commission_text;										//정산계산식 상세설명

		$optdata['total_commission_price']		= $total_commission_price;
		$optdata['total_feeprice']				= $total_feeprice;


	}elseif($CI->accountallmodel->account_fee_ar['pg']){//강원마트전용 결제수수료
		//엑셀그대로 적용되기 때문에 검증용

		$optdata['npay_point_unit_feeprice'] = 0;
		if($optdata['npay_point_sale_unit']){
			$npay_point_unit_feeprice				= acc_string_floor(($optdata['npay_point_sale_unit'] * $CI->accountallmodel->npay_point_paycharge['commission_rate']) / 100,$acinsdata['pg']);
			$optdata['npay_point_unit_feeprice']	= $npay_point_unit_feeprice;
			$npay_point_feeprice_rest				= $optdata['out_npay_point_use']-($npay_point_unit_feeprice*$optdata['ea']);
			$optdata['npay_point_feeprice_rest']	= ($npay_point_feeprice_rest);
		}
		$sales_unit_minfee = 0;
		if($paycharge['min_fee'] > 0){
			$tmp_acc_unit_mincharge = $paycharge['min_fee'] * $optdata['sale_ratio']/100;
			$sales_unit_minfee		= acc_string_floor(($tmp_acc_unit_mincharge / $optdata['ea']), $acinsdata['pg']);
		}
		$acc_unit_payprice			= $optdata['price'] - $optdata['salescost_admin'];//$optdata['salescost_total']
		//$acc_unit_payprice		= $acc_unit_payprice / $optdata['ea']; //■ B 실 결제액 (개당)
		$sales_unit_feeprice		= acc_string_floor(($acc_unit_payprice * $paycharge['commission_rate'] / 100), 'kakaopay'); //■ C 수수료
		$acc_charge_str = "";
		if( $paycharge['commission_rate'] > 0)					$acc_charge_str	.= $paycharge['commission_rate']."%";
		if( $acc_charge_str !='' && $paycharge['min_fee'] > 0)	$acc_charge_str	.= " + ";
		if( $paycharge['min_fee'] > 0)							$acc_charge_str	.= $paycharge['min_fee']."원 (개당 {$sales_unit_minfee}원)";

		$tot_commission_unit				= $sales_unit_feeprice + $sales_unit_minfee;// + $optdata['salescost_provider'];//개당
		$commission_price					= $acc_unit_payprice - $tot_commission_unit;	//개당 임시정산 - 수수료합계
		$commission_price					= floor($commission_price);// 정산가 소수점 버림 처리
		if($commission_price < 0)			$commission_price = 0;
		$optdata['commission_rate']			= $paycharge['commission_rate'];				//수수료율
		$optdata['commission_price']		= $commission_price;							//개당 정산금액
		$optdata['sales_unit_feeprice']		= $sales_unit_feeprice;							//정산 수수료금액-개당
		$optdata['sales_unit_minfee']		= $sales_unit_minfee;							//정산 추가수수료 개당(+)
		$optdata['sales_unit_payprice']		= $acc_unit_payprice;							//실 결제액 (개당)
		
		$optdata['commission_text']			= $optdata['price']." - ".$optdata['salescost_admin'].
												"-".$sales_unit_feeprice.
												"-".$sales_unit_minfee.
												"=".$commission_price." ".$acc_charge_str." ";			//정산계산식 상세설명 
												//"-".$optdata['salescost_provider'].
	}
	/**
	정산계산식 수동처리
	**/
	
	
	
	$optdata['sitetype']						= $acinsdata['sitetype'];				// 판매환경
	// fm_order_item 에 사용하지 않는 linkage_order_id 필드가 있어 정상적으로 데이터를 병합하지 못 하여 강제 할당 by hed
	$optdata['linkage_order_id']				= $acinsdata['linkage_order_id'];		// 연동업체 주문번호
	$optdata['linkage_mall_order_id']			= $acinsdata['linkage_mall_order_id'];	// 연동마켓 주문번호
	$optdata['linkage_mall_code']				= $acinsdata['linkage_mall_code'];		// 연동몰 코드
	
	return $optdata;
}

/*
* 정산/매출 추가옵션정보 추출
 * #acinsdata 주문정보
 * #subdata 추가옵션정보
 * #paycharge 전자결제정보
 * #cal 매출/정산 구분
 * @param 
*/
function account_ins_suboption_ck($acinsdata, $subdata,$paycharge, $cal=null ) {
	$CI =& get_instance();
	if( $subdata['step'] == '95' || $subdata['step'] == '99' ) return;
	//$subdata = array_merge($acinsdata,$subdata);

	//네이버페이 미입금취소(API)로 옵션상태는 주문무효'95'이면서 주문상태는 결제취소'85'는 예외처리 
	if($acinsdata['npay_order_id'] && ($subdata['step'] == '95' && $subdata['step'] == '85')) return;
	if($acinsdata['npay_order_id'] && !$acinsdata['deposit_date']) {
		//네이버페이 결제일이 없으면 npay결제일 또는 수집일을 기준으로
		$subdata['deposit_date'] = ($acinsdata['npay_order_pay_date'])?$acinsdata['npay_order_pay_date']:$acinsdata['regist_date'];
	}

	$subdata['account_type']		= ($acinsdata['orign_order_seq'])?"exchange":"order";
	$subdata['order_type']			= "suboption";
	$subdata['account_target']		= get_account_target($acinsdata, $subdata);//정산대상(매출/정산)
	$subdata['order_type_view'] 	= $CI->accountallmodel->order_type_ar[$subdata['order_type']];
	$subdata['order_form_seq']		= $subdata['item_suboption_seq'];
	$subdata['provider_name']		= ($subdata['provider_name'])?$subdata['provider_name']:$acinsdata['provider_name'];

	/**
	사용적립금/이머니/에누리
	**/
	$subdata['out_emoney_use']		= ($subdata['emoney_sale_unit']*$subdata['ea'])+$subdata['emoney_sale_rest'];
	$subdata['out_cash_use']		= ($subdata['cash_sale_unit']*$subdata['ea'])+$subdata['cash_sale_rest'];
	$subdata['out_enuri_use']		= ($subdata['enuri_sale_unit']*$subdata['ea'])+$subdata['enuri_sale_rest'];
	$subdata['out_npay_point_use']	= ($subdata['npay_point_sale_unit']*$subdata['ea'])+$subdata['npay_point_sale_rest'];

	/**
	할인항목별 처리시작
	**/
	if($subdata['member_sale'] && !$subdata['member_sale_unit'] )	$subdata['member_sale_unit']	= $subdata['member_sale'];

	acc_promotion_sales_unit('emoney',	$subdata['emoney_sale_unit'],	$subdata['emoney_sale_provider'],		$subdata);
	acc_promotion_sales_unit('cash',	$subdata['cash_sale_unit'],		$subdata['cash_sale_provider'],			$subdata);
	acc_promotion_sales_unit('enuri',	$subdata['enuri_sale_unit'],	$subdata['enuri_sale_provider'],		$subdata);
	acc_promotion_sales_unit('npay_point',$subdata['npay_point_sale_unit'],$subdata['npay_point_sale_provider'],	$subdata);

	acc_promotion_sales_unit('multi',	$subdata['multi_sale_unit'],	$subdata['multi_sale_provider'],	$subdata);
	acc_promotion_sales_unit('event',	$subdata['event_sale_unit'],	$subdata['event_sale_provider'],	$subdata);
	acc_promotion_sales_unit('member',	$subdata['member_sale_unit'],	$subdata['member_sale_provider'],	$subdata);
	acc_promotion_sales_unit('coupon',	$subdata['coupon_sale_unit'],	$subdata['coupon_sale_provider'],	$subdata);
	acc_promotion_sales_unit('fblike',	$subdata['fblike_sale_unit'],	$subdata['fblike_sale_provider'],	$subdata);
	acc_promotion_sales_unit('mobile',	$subdata['mobile_sale_unit'],	$subdata['mobile_sale_provider'],	$subdata);
	acc_promotion_sales_unit('code',	$subdata['code_sale_unit'],		$subdata['code_sale_provider'],		$subdata);
	acc_promotion_sales_unit('referer',	$subdata['referer_sale_unit'],	$subdata['referer_sale_provider'],	$subdata);

	/**
	할인항목별 처리끝
	**/

	/**
	** 할인부담금 시작
	$subdata['salescost_admin_sales']		= $subdata['salescost_event'] + $subdata['salescost_event_rest']
											   + $subdata['salescost_multi'] + $subdata['salescost_multi_rest']
											   + 
	$subdata['salescost_provider_sales']		= $subdata['salescost_event_provider'] + $subdata['salescost_event_provider_rest']
												   + $subdata['salescost_multi_provider'] + $subdata['salescost_multi_provider_rest']
												   + 
	$salescost_sales		= $subdata['multi_sale'] + $subdata['multi_sale_rest']
													   + $subdata['event_sale'] + $subdata['event_sale_rest']
													   + 
	
	**/
	## 본사 부담금
	$subdata['salescost_admin_promotion']	= $subdata['salescost_emoney'] + $subdata['salescost_emoney_rest']
											   + $subdata['salescost_cash'] + $subdata['salescost_cash_rest']
											   + $subdata['salescost_enuri'] + $subdata['salescost_enuri_rest'];
											   //+ $subdata['salescost_npay_point'] + $subdata['salescost_npay_point_rest'];
	$subdata['salescost_admin_sales']		= $subdata['salescost_event'] + $subdata['salescost_event_rest']
											   + $subdata['salescost_multi'] + $subdata['salescost_multi_rest']
											   + $subdata['salescost_member'] + $subdata['salescost_member_rest']
											   + $subdata['salescost_coupon'] + $subdata['salescost_coupon_rest']
											   + $subdata['salescost_fblike'] + $subdata['salescost_fblike_rest']
											   + $subdata['salescost_mobile'] + $subdata['salescost_mobile_rest']
											   + $subdata['salescost_code'] + $subdata['salescost_code_rest']
											   + $subdata['salescost_referer'] + $subdata['salescost_referer_rest'];
	$subdata['salescost_admin']				= $subdata['salescost_admin_promotion'] + $subdata['salescost_admin_sales'];

	//입점사 부담금
	$subdata['salescost_provider_promotion']	= $subdata['salescost_emoney_provider'] + $subdata['salescost_emoney_provider_rest']
												   + $subdata['salescost_cash_provider'] + $subdata['salescost_cash_provider_rest']
												   + $subdata['salescost_enuri_provider'] + $subdata['salescost_enuri_provider_rest'];
												  // + $subdata['salescost_npay_point_provider'] + $subdata['salescost_npay_point_provider_rest'];
	$subdata['salescost_provider_sales']		= $subdata['salescost_event_provider'] + $subdata['salescost_event_provider_rest']
												   + $subdata['salescost_multi_provider'] + $subdata['salescost_multi_provider_rest']
												   + $subdata['salescost_member_provider'] + $subdata['salescost_member_provider_rest']
												   + $subdata['salescost_coupon_provider'] + $subdata['salescost_coupon_provider_rest']
												   + $subdata['salescost_fblike_provider'] + $subdata['salescost_fblike_provider_rest']
												   + $subdata['salescost_mobile_provider'] + $subdata['salescost_mobile_provider_rest']
												   + $subdata['salescost_code_provider'] + $subdata['salescost_code_provider_rest']
												   + $subdata['salescost_referer_provider'] + $subdata['salescost_referer_provider_rest'];
	$subdata['salescost_provider']				= $subdata['salescost_provider_promotion'] + $subdata['salescost_provider_sales'];

	// 부담금 합계
	$subdata['acc_promotion_total']				= ($subdata['salescost_admin_promotion']) + ($subdata['salescost_provider_promotion']); 
	$subdata['acc_sale_total']					= $subdata['salescost_admin_sales'] + $subdata['salescost_provider_sales'];
	$subdata['salescost_total']					= $subdata['acc_promotion_total'] + $subdata['acc_sale_total'];
	/**
	** 할인부담금 끝
	**/

	/**
	정산계산식 수동처리
	**/
	// 기본통화에 맞춰서 cutting 처리 추가 :: 2018-07-24 pjw
	$subdata['price']		= get_cutting_price($subdata['price']);
	$subdata['org_price']	= get_cutting_price($subdata['org_price']);
	$subdata['sales_price']	= get_cutting_price($subdata['price']*$subdata['ea']);			//총 결제금액
	$subdata['sale_price']	= get_cutting_price($subdata['org_price']*$subdata['ea']);		//총 판매금액

	if( $CI->accountallmodel->account_fee_ar['goods'] ) {//상품별 수수료 적용시
		$acc_unit_payprice		= (int)$subdata['price'] - $subdata['salescost_total'] + $subdata['salescost_cash'] + $subdata['salescost_cash_rest'];//실결제금액(단가)
		$subopt_price_tmp		= (int)$subdata['price'] - $subdata['salescost_provider'];

		if(!$subdata['provider_seq']) $subdata['provider_seq']	= $acinsdata['provider_seq'];
		$subdata['pay_price']		= $acc_unit_payprice;
		$subdata['target_price']	= $subopt_price_tmp;

		$salescost_provider					= array();
		$salescost_provider['event']		= $subdata['salescost_event_provider'];
		$salescost_provider['multi']		= $subdata['salescost_multi_provider'];
		$salescost_provider['member']		= $subdata['salescost_member_provider'];
		$salescost_provider['coupon']		= $subdata['salescost_coupon_provider'];
		$salescost_provider['fblike']		= $subdata['salescost_fblike_provider'];
		$salescost_provider['mobile']		= $subdata['salescost_mobile_provider'];
		$salescost_provider['code']			= $subdata['salescost_code_provider'];
		$salescost_provider['referer']		= $subdata['salescost_referer_provider'];
		$salescost_provider['promotion']	=  $subdata['salescost_provider_promotion'];

		$_commission_info			= array();
		foreach(get_commission_info_field() as $_field) $_commission_info[$_field] = $subdata[$_field];
		$_commission_info['salescost_provider'] = $salescost_provider;

		$_return_commission			= get_commission($_commission_info);
		$total_commission_price		= $_return_commission['commission_price'];
		$total_feeprice				= $_return_commission['feeprice'];
		$commission_unit_price		= $_return_commission['commission_unit_price'];
		$commission_price_rest		= $_return_commission['commission_price_rest'];
		$sales_unit_feeprice		= $_return_commission['feeprice_unit'];
		$sales_feeprice_rest		= $_return_commission['feeprice_rest'];
		$commission_text			= $_return_commission['commission_text'];

		if($commission_unit_price < 0) $commission_unit_price = 0;

		$sales_unit_minfee					= 0;

		$subdata['commission_price']		= $commission_unit_price;								//개당 정산금액
		$subdata['sales_unit_feeprice']		= $sales_unit_feeprice;									//정산 수수료금액-개당
		$subdata['sales_unit_minfee']		= $sales_unit_minfee;									//정산 추가수수료 개당(+)
		$subdata['sales_unit_payprice']		= $acc_unit_payprice;									//실 결제액 (개당)
		$subdata['commission_text']			= $commission_text;										//정산계산식 상세설명

		$subdata['total_commission_price']		= $total_commission_price;
		$subdata['total_feeprice']				= $total_feeprice;

	}elseif($CI->accountallmodel->account_fee_ar['pg']){//강원마트전용 결제수수료
		//엑셀그대로 적용되기 때문에 검증용

		$subdata['npay_point_unit_feeprice'] = 0;
		if($subdata['npay_point_sale_unit']){
			$npay_point_unit_feeprice				= acc_string_floor(($subdata['npay_point_sale_unit'] * $CI->accountallmodel->npay_point_paycharge['commission_rate']) / 100, 'kakaopay');
			$subdata['npay_point_unit_feeprice']	= $npay_point_unit_feeprice;
			$npay_point_feeprice_rest				= $subdata['out_npay_point_use']-($npay_point_unit_feeprice*$subdata['ea']);
			$subdata['npay_point_feeprice_rest']	= ($npay_point_feeprice_rest);
		}

		$sales_unit_minfee = 0;
		if($paycharge['min_fee'] > 0){
			$tmp_acc_unit_mincharge = $paycharge['min_fee'] * $subdata['sale_ratio']/100;
			$sales_unit_minfee = acc_string_floor(($tmp_acc_unit_mincharge / $subdata['ea']),$acinsdata['pg']);
		}
		$acc_unit_payprice				= $subdata['price'] - $subdata['salescost_admin'];//$optdata['salescost_total']
		//$acc_unit_payprice			= $acc_unit_payprice / $subdata['ea']; //■ B 실 결제액 (개당)
		$sales_unit_feeprice			= acc_string_floor(($acc_unit_payprice * $paycharge['commission_rate'] / 100), 'kakaopay'); //■ C 수수료
		$acc_charge_str = "";
		if( $paycharge['commission_rate'] > 0)					$acc_charge_str	.= $paycharge['commission_rate']."%";
		if( $acc_charge_str !='' && $paycharge['min_fee'] > 0)	$acc_charge_str	.= " + ";
		if( $paycharge['min_fee'] > 0)							$acc_charge_str	.= $paycharge['min_fee']."원 (개당 {$sales_unit_minfee}원)";

		$tot_commission_unit				= $sales_unit_feeprice + $sales_unit_minfee;// + $subdata['salescost_provider'];//개당
		$commission_price					= $acc_unit_payprice - $tot_commission_unit;	//개당 임시정산 - 수수료합계
		$commission_price					= floor($commission_price);// 정산가 소수점 버림 처리
		if($commission_price < 0)			$commission_price = 0;
		$subdata['commission_rate']			= $paycharge['commission_rate'];			//비율
		$subdata['commission_price']		= $commission_price;						//개당 정산금액
		$subdata['sales_unit_feeprice']		= $sales_unit_feeprice;						//정산 수수료금액-개당
		$subdata['sales_unit_minfee']		= $sales_unit_minfee;						//정산 추가수수료 개당(+)
		$subdata['sales_unit_payprice']		= $acc_unit_payprice;						//실 결제액 (개당)

		$subdata['commission_text']			= $subdata['price']." - ".$subdata['salescost_admin'].
												"-".$sales_unit_feeprice.
												"-".$sales_unit_minfee.
												"=".$commission_price." ".$acc_charge_str." ";				//정산계산식 상세설명
												//"-".$subdata['salescost_provider'].
	}
	/**
	정산계산식 수동처리
	**/
	
	$subdata['sitetype']						= $acinsdata['sitetype'];				// 판매환경
	// fm_order_item 에 사용하지 않는 linkage_order_id 필드가 있어 정상적으로 데이터를 병합하지 못 하여 강제 할당 by hed
	$subdata['linkage_order_id']				= $acinsdata['linkage_order_id'];		// 연동업체 주문번호
	$subdata['linkage_mall_order_id']			= $acinsdata['linkage_mall_order_id'];	// 연동마켓 주문번호
	$subdata['linkage_mall_code']				= $acinsdata['linkage_mall_code'];		// 연동몰 코드
	
	return $subdata;
}
/*
* 정산/매출 배송정보 추출
 * #acinsdata	주문정보
 * #shipping	배송정보
 * #paycharge	전자결제정보
 * #cal			정산 구분
 * @param 
*/
function account_ins_shipping_ck($acinsdata, $shipping, $paycharge, $cal=null ) {
	$CI =& get_instance();

	//$shipping = array_merge($acinsdata,$shipping);
	if($acinsdata['npay_order_id'] && !$acinsdata['deposit_date']) {
		//네이버페이 결제일이 없으면 npay결제일 또는 수집일을 기준으로
		$shipping['deposit_date'] = ($acinsdata['npay_order_pay_date'])?$acinsdata['npay_order_pay_date']:$acinsdata['regist_date'];
	}

	$shipping['account_type']		= ($acinsdata['orign_order_seq'])?"exchange":"order";
	$shipping['order_type']			= "shipping";
	$shipping['order_goods_kind']	= "shipping";
	$shipping['account_target']		= get_account_target($acinsdata, $shipping);//정산대상(매출/정산)
	$shipping['order_type_view'] 	= $CI->accountallmodel->order_type_ar[$shipping['order_type']];
	$shipping['item_option_seq']	= $shipping['item_option_seq'];
	$shipping['order_form_seq']		= $shipping['shipping_seq'];
	$shipping['order_goods_name']	= "";
	$shipping['goods_code']			= '';
	//$shipping['mstatus'] 			= $CI->arr_step[$acinsdata['step']];
	$shipping['ea']					= 1;
	$shipping['ac_ea']				= 1;
	$shipping['exp_ea']				= 1;
	$shipping['commission_type']	= "SACO";
	$shipping['commission_rate']	= ($shipping['shipping_provider_seq'] == 1) ? 100 : $shipping['shipping_charge'];

	//네이버페이 주문건 prepay_info 값이 없을때 배송방법과 통합 :: 2018-07-16 lkh
	if(!$shipping['prepay_info']) $shipping['prepay_info'] = $shipping['shipping_method'];

	// 배송비합 : 일반 + 개별
	unset($shipping_tot);
	//if($shipping['shipping_method']=='delivery'){
	if($shipping['shipping_method']!='direct_store' && $shipping['prepay_info']=='delivery'){
		$shipping_tot['basic_cost']				= $shipping['shipping_cost'];
		//$shipping_tot['add_shipping_cost']		= $shipping['add_delivery_cost'];
	}

	if(preg_match( '/each_delivery/',$shipping['shipping_method'])){
		$shipping_tot['goods_cost']				= $shipping['delivery_cost'];//$shipping['goods_shipping_cost'];
		$shipping_tot['add_shipping_cost']		= $shipping['add_delivery_cost'];
	}
	$shipping['sales_price'] = $shipping_tot['basic_cost'] + $shipping_tot['goods_cost'] + $shipping_tot['add_shipping_cost'];
	//debug_var($shipping['sales_price']." = ".$shipping_tot['basic_cost']." + ".$shipping_tot['goods_cost']." + ".$shipping_tot['add_shipping_cost']);
	$shipping['sale_price']	= $shipping['sales_price'];

	if($shipping['sales_price']) 
	{
		/**
		사용적립금/이머니/에누리 계산식
		**/
		$shipping['out_emoney_use']		= $shipping['emoney_sale_unit']+$shipping['emoney_sale_rest'];
		$shipping['out_cash_use']		= $shipping['cash_sale_unit']+$shipping['cash_sale_rest'];
		$shipping['out_enuri_use']		= $shipping['enuri_sale_unit']+$shipping['enuri_sale_rest'];
		$shipping['out_npay_point_use']	= $shipping['npay_point_sale_unit']+$shipping['npay_point_sale_rest'];
		
		//네이밍 매칭
		$shipping['coupon_sale_unit']	= $shipping['shipping_coupon_sale'];
		$shipping['code_sale_unit']		= $shipping['shipping_promotion_code_sale'];

		/**
		할인항목별 처리
		**/
		acc_promotion_sales_unit('emoney',		$shipping['emoney_sale_unit'],	$shipping['emoney_sale_provider'],		$shipping);
		acc_promotion_sales_unit('cash',		$shipping['cash_sale_unit'],	$shipping['cash_sale_provider'],		$shipping);
		acc_promotion_sales_unit('enuri',		$shipping['enuri_sale_unit'],	$shipping['enuri_sale_provider'],		$shipping);
		acc_promotion_sales_unit('npay_point',	$shipping['npay_point_sale_unit'],$shipping['npay_point_sale_provider'],$shipping);

		acc_promotion_sales_unit('coupon',		$shipping['shipping_coupon_sale'],			$shipping['coupon_sale_provider'],		$shipping);
		acc_promotion_sales_unit('code',		$shipping['shipping_promotion_code_sale'],	$shipping['code_sale_provider'],		$shipping);
		/**
		할인항목별 처리끝
		**/

		/**
		** 할인부담금 시작
		**/
		// 본사 부담금
		$shipping['salescost_admin_promotion']		= $shipping['salescost_emoney'] + $shipping['salescost_emoney_rest']
													   + $shipping['salescost_cash'] + $shipping['salescost_cash_rest']
													   + $shipping['salescost_enuri'] + $shipping['salescost_enuri_rest'];
		$shipping['salescost_admin_sales']			= $shipping['salescost_coupon'] + $shipping['salescost_coupon_rest']
													   + $shipping['salescost_code'] + $shipping['salescost_code_rest'];
		$shipping['salescost_admin']				= $shipping['salescost_admin_promotion'] + $shipping['salescost_admin_sales'];

		//입점사 부담금
		$shipping['salescost_provider_promotion']	= $shipping['salescost_emoney_provider'] + $shipping['salescost_emoney_provider_rest']
													   + $shipping['salescost_cash_provider'] + $shipping['salescost_cash_provider_rest']
													   + $shipping['salescost_enuri_provider'] + $shipping['salescost_enuri_provider_rest'];
		$shipping['salescost_provider_sales']		= $shipping['salescost_coupon_provider'] + $shipping['salescost_coupon_provider_rest']
													   + $shipping['salescost_code_provider'] + $shipping['salescost_code_provider_rest'];
		$shipping['salescost_provider']				= $shipping['salescost_provider_promotion'] + $shipping['salescost_provider_sales'];
		
		// 부담금 합계
		$shipping['acc_promotion_total']			= ($shipping['salescost_admin_promotion']) + ($shipping['salescost_provider_promotion']);
		$shipping['acc_sale_total']					= $shipping['salescost_admin_sales'] + $shipping['salescost_provider_sales'];
		$shipping['salescost_total']				= $shipping['acc_promotion_total'] + $shipping['acc_sale_total'];
		/**
		** 할인부담금 끝
		**/
		$shipping['npay_point_unit_feeprice'] = 0;
		if($shipping['npay_point_sale_unit']){
			$npay_point_sale_unit					= $shipping['out_npay_point_use'];
			$npay_point_unit_feeprice				= acc_string_floor(($npay_point_sale_unit * $CI->accountallmodel->npay_point_paycharge['commission_rate']) / 100,$acinsdata['pg']);
			$shipping['npay_point_unit_feeprice']	= $npay_point_unit_feeprice;
			$npay_point_feeprice_rest				= $npay_point_sale_unit-($npay_point_unit_feeprice);
			$shipping['npay_point_feeprice_rest']	= ($npay_point_feeprice_rest);
		}

		/**
		정산계산식 수동처리
		**/
		if( $CI->accountallmodel->account_fee_ar['goods']) {//상품별 수수료 적용시
			// 배송비 수수료 추가
			$shipping_price_tmp					= (int)$shipping['sales_price'] - $shipping['salescost_provider'];//정산대상금액
			
			$acc_charge_str						= "";
			$acc_charge_str						= $shipping['commission_rate']."%";

			$shipping['price']					= $shipping['sales_price'];//상품단가
			//$shipping['price']					= $commission_price = $acc_unit_payprice = $shipping['sales_price'] - $shipping['salescost_admin'];//상품단가
			//$commission_price 					= $shipping['sales_price'] - $shipping['salescost_provider']; // 정산금액
			$commission_price_tmp				= $shipping_price_tmp*(100-$shipping['commission_rate'])/100;
			$sales_unit_feeprice				= $shipping_price_tmp*($shipping['commission_rate'])/100;//수수료(단가)

			$acc_unit_payprice					= $shipping['sales_price'] - $shipping['salescost_total']; // 실결제금액
			$sales_unit_minfee					= 0;

			$commission_price_tmp				= floor($commission_price_tmp);
			$commission_price					= $commission_price_tmp;//단품으로 계산 sales_unit_feeprice
			
			$shipping['org_price']				= $shipping['sales_price'];//상품의 판매가
			$shipping['consumer_price']			= $shipping['sales_price'];//개당 정가

			$shipping['commission_price']		= $commission_price;							//개당 정산금액
			$shipping['sales_unit_feeprice']	= $sales_unit_feeprice;							//정산 수수료금액-개당
			$shipping['sales_unit_minfee']		= $sales_unit_minfee;							//정산 추가수수료 개당(+)
			$shipping['sales_unit_payprice']	= $acc_unit_payprice;							//실 결제액 (개당)
			$shipping['commission_text']		= $shipping['sales_price'].
													"-".$sales_unit_feeprice.
													"-".$sales_unit_minfee.
													"=".$commission_price." ".$acc_charge_str."";			//정산계산식 상세설명

		}elseif($CI->accountallmodel->account_fee_ar['pg']){//강원마트전용 결제수수료
			//엑셀그대로 적용되기 때문에 검증용
			$sales_unit_minfee = 0;
			if($paycharge['min_fee'] > 0){
				$tmp_acc_unit_mincharge	= $paycharge['min_fee'] * $shipping['sale_ratio']/100;
				$sales_unit_minfee		= acc_string_floor(($tmp_acc_unit_mincharge / $shipping['ea']),$acinsdata['pg']);
			}
			$acc_unit_payprice			= $shipping['sales_price'] - $shipping['salescost_admin'];
			$acc_unit_payprice			= $acc_unit_payprice; //■ B 실 결제액 (개당) 
			$sales_unit_feeprice		= acc_string_floor(($acc_unit_payprice * $paycharge['commission_rate'] / 100), 'pg'); //■ C 수수료
			$acc_charge_str = "";
			if( $paycharge['commission_rate'] > 0)					$acc_charge_str	.= $paycharge['commission_rate']."%";
			if( $acc_charge_str !='' && $paycharge['min_fee'] > 0)	$acc_charge_str	.= " + ";
			if( $paycharge['min_fee'] > 0)							$acc_charge_str	.= $paycharge['min_fee']."원 (개당 {$sales_unit_minfee}원)";

			$tot_commission_unit			= $sales_unit_feeprice + $sales_unit_minfee;// + $shipping['salescost_provider'];//단
			$commission_price				= $acc_unit_payprice - $tot_commission_unit; //개당  임시정산 - 수수료합계
			$commission_price				= floor($commission_price);
			if($commission_price < 0)			$commission_price = 0;

			$shipping['price']				= $shipping['sales_price'];//상품단가
			$shipping['org_price']			= $shipping['sales_price'];//상품의 판매가
			$shipping['consumer_price']		= $acc_unit_payprice;//개당 정가

			$shipping['commission_rate']		= $paycharge['commission_rate'];				//정산수수료율
			$shipping['commission_price']		= $commission_price;							//개당 정산금액
			$shipping['sales_unit_feeprice']	= $sales_unit_feeprice;							//정산 수수료금액-개당
			$shipping['sales_unit_minfee']		= $sales_unit_minfee;							//정산 추가수수료 개당(+)
			$shipping['sales_unit_payprice']	= $acc_unit_payprice;							//실 결제액 (개당)
			
			$shipping['commission_text']		= $shipping['sales_price']." - ".$shipping['salescost_admin'].
													"-".$sales_unit_feeprice.
													"-".$sales_unit_minfee.
													"=".$commission_price." ".$acc_charge_str."";			//정산계산식 상세설명
													//"-".$shipping['salescost_provider'].
		}
		//$shipping = array_merge($data_order,$shipping);
	}
	
	
	$shipping['sitetype']						= $acinsdata['sitetype'];				// 판매환경
	// fm_order_item 에 사용하지 않는 linkage_order_id 필드가 있어 정상적으로 데이터를 병합하지 못 하여 강제 할당 by hed
	$shipping['linkage_order_id']				= $acinsdata['linkage_order_id'];		// 연동업체 주문번호
	$shipping['linkage_mall_order_id']			= $acinsdata['linkage_mall_order_id'];	// 연동마켓 주문번호
	$shipping['linkage_mall_code']				= $acinsdata['linkage_mall_code'];		// 연동몰 코드
	
	return $shipping;
}

/*
* 정산/매출 환불(배송비/추가배송)정보 추출
 * #acinsdata 환불상품정보(get_refund_item)
 * #refunddata 배송정보
 * #paycharge 전자결제정보
 * #cal 매출/정산 구분
 * @param 
*/
function account_ins_refund_ck($acinsdata, $refunddata) {
	$CI =& get_instance();

	$refunddata['account_type']		= "refund";	
	$refunddata['order_form_seq']	= $acinsdata['refund_item_seq'];
	$keyArr = array('price',
				'sales_price',
				'sales_unit_feeprice',
				'commission_price',
				'event_sale_unit',
				'multi_sale_unit',
				'coupon_sale_unit',
				'code_sale_unit',
				'member_sale_unit',
				'fblike_sale_unit',
				'mobile_sale_unit',
				'referer_sale_unit',
				'emoney_sale_unit',
				'cash_sale_unit',
				'enuri_sale_unit',
				'npay_point_sale_unit',
				'event_sale_rest',
				'multi_sale_rest',
				'coupon_sale_rest',
				'code_sale_rest',
				'member_sale_rest',
				'fblike_sale_rest',
				'mobile_sale_rest',
				'referer_sale_rest',
				'emoney_sale_rest',
				'cash_sale_rest',
				'enuri_sale_rest',
				'npay_point_sale_rest',
				'salescost_event',
				'salescost_multi',
				'salescost_coupon',
				'salescost_code',
				'salescost_member',
				'salescost_fblike',
				'salescost_mobile',
				'salescost_referer',
				'salescost_enuri',
				'salescost_emoney',
				'salescost_cash',
				'salescost_npay_point',
				'salescost_event_rest',
				'salescost_multi_rest',
				'salescost_coupon_rest',
				'salescost_code_rest',
				'salescost_member_rest',
				'salescost_fblike_rest',
				'salescost_mobile_rest',
				'salescost_referer_rest',
				'salescost_enuri_rest',
				'salescost_emoney_rest',
				'salescost_cash_rest',
				'salescost_npay_point_rest',
				'salescost_event_provider',
				'salescost_multi_provider',
				'salescost_coupon_provider',
				'salescost_code_provider',
				'salescost_member_provider',
				'salescost_fblike_provider',
				'salescost_mobile_provider',
				'salescost_referer_provider',
				'salescost_enuri_provider',
				'salescost_emoney_provider',
				'salescost_cash_provider',
				'salescost_npay_point_provider',
				'salescost_event_provider_rest',
				'salescost_multi_provider_rest',
				'salescost_coupon_provider_rest',
				'salescost_code_provider_rest',
				'salescost_member_provider_rest',
				'salescost_fblike_provider_rest',
				'salescost_mobile_provider_rest',
				'salescost_referer_provider_rest',
				'salescost_enuri_provider_rest',
				'salescost_emoney_provider_rest',
				'salescost_cash_provider_rest',
				'salescost_npay_point_provider_rest',
				'api_pg_price',
				'api_pg_sale_price',
				'api_pg_commission_price');
	foreach($refunddata as $key => $value){
		if(in_array($key, $keyArr)){
			$resultVal = acc_coupon_remain_sales_unit($value,$refunddata['coupon_remain_real_percent']);
			$refunddata[$key] = $resultVal;
		}
	}
	$refunddata['sales_price']		= $refunddata['refund_goods_price'];

	/**
	사용적립금/이머니/에누리
	**/
	$refunddata['out_emoney_use']		= ($refunddata['emoney_sale_unit']*$refunddata['ea'])+$refunddata['emoney_sale_rest'];
	$refunddata['out_cash_use']			= ($refunddata['cash_sale_unit']*$refunddata['ea'])+$refunddata['cash_sale_rest'];
	$refunddata['out_enuri_use']		= ($refunddata['enuri_sale_unit']*$refunddata['ea'])+$refunddata['enuri_sale_rest'];
	$refunddata['out_npay_point_use']	= ($refunddata['npay_point_sale_unit']*$refunddata['ea'])+$refunddata['npay_point_sale_rest'];

	/**
	할인항목별 처리시작
	**/
	acc_promotion_sales_unit('emoney',	$refunddata['emoney_sale_unit'],	$refunddata['emoney_sale_provider'],		$refunddata);
	acc_promotion_sales_unit('cash',	$refunddata['cash_sale_unit'],		$refunddata['cash_sale_provider'],			$refunddata);
/* 이미 계산되어 있는 값을 이용하는데 필요없는 부분이라 삭제 처리 :: 2018-07-17 lkh
	acc_promotion_sales_unit('enuri',	$refunddata['enuri_sale_unit'],		$refunddata['enuri_sale_provider'],			$refunddata);
	acc_promotion_sales_unit('npay_point',$refunddata['npay_point_sale_unit'],$refunddata['npay_point_sale_provider'],$refunddata);

	acc_promotion_sales_unit('multi',	$refunddata['multi_sale_unit'],		$refunddata['multi_sale_provider'],		$refunddata);
	acc_promotion_sales_unit('event',	$refunddata['event_sale_unit'],		$refunddata['event_sale_provider'],		$refunddata);
	acc_promotion_sales_unit('member',	$refunddata['member_sale_unit'],	$refunddata['member_sale_provider'],	$refunddata);
	acc_promotion_sales_unit('coupon',	$refunddata['coupon_sale_unit'],	$refunddata['coupon_sale_provider'],	$refunddata);
	acc_promotion_sales_unit('fblike',	$refunddata['fblike_sale_unit'],	$refunddata['fblike_sale_provider'],	$refunddata);
	acc_promotion_sales_unit('mobile',	$refunddata['mobile_sale_unit'],	$refunddata['mobile_sale_provider'],	$refunddata);
	acc_promotion_sales_unit('code',	$refunddata['code_sale_unit'],		$refunddata['code_sale_provider'],		$refunddata);
	acc_promotion_sales_unit('referer',	$refunddata['referer_sale_unit'],	$refunddata['referer_sale_provider'],	$refunddata);
*/
	/**
	할인항목별 처리끝
	**/

	/**
	** 할인부담금 시작
	$refunddata['salescost_admin_sales'] = $refunddata['salescost_event'] + $refunddata['salescost_event_rest']
	   + $refunddata['salescost_multi'] + $refunddata['salescost_multi_rest']
	   + 
	$refunddata['salescost_provider_sales'] =    $refunddata['salescost_event_provider'] + $refunddata['salescost_event_provider_rest']
	   + $refunddata['salescost_multi_provider'] + $refunddata['salescost_multi_provider_rest']
	   + 
	$salescost_sales =   $refunddata['multi_sale'] + $refunddata['multi_sale_rest']
												   + $refunddata['event_sale'] + $refunddata['event_sale_rest']
												   + 
	**/
	## 본사 부담금
	$refunddata['salescost_admin_promotion']	= $refunddata['salescost_emoney'] + $refunddata['salescost_emoney_rest']
											   + $refunddata['salescost_cash'] + $refunddata['salescost_cash_rest']
											   + $refunddata['salescost_enuri'] + $refunddata['salescost_enuri_rest'];
											  // + $refunddata['salescost_npay_point'] + $refunddata['salescost_npay_point_rest'];
	$refunddata['salescost_admin_sales'] = $refunddata['salescost_event'] + $refunddata['salescost_event_rest']
											   + $refunddata['salescost_multi'] + $refunddata['salescost_multi_rest']
											   + $refunddata['salescost_member'] + $refunddata['salescost_member_rest']
											   + $refunddata['salescost_coupon'] + $refunddata['salescost_coupon_rest']
											   + $refunddata['salescost_fblike'] + $refunddata['salescost_fblike_rest']
											   + $refunddata['salescost_mobile'] + $refunddata['salescost_mobile_rest']
											   + $refunddata['salescost_code'] + $refunddata['salescost_code_rest']
											   + $refunddata['salescost_referer'] + $refunddata['salescost_referer_rest'];
	$refunddata['salescost_admin']				= $refunddata['salescost_admin_promotion'] + $refunddata['salescost_admin_sales'];

	//입점사 부담금
	$refunddata['salescost_provider_promotion']= $refunddata['salescost_emoney_provider'] + $refunddata['salescost_emoney_provider_rest']
											   + $refunddata['salescost_cash_provider'] + $refunddata['salescost_cash_provider_rest']
											   + $refunddata['salescost_enuri_provider'] + $refunddata['salescost_enuri_provider_rest'];
											 //  + $refunddata['salescost_npay_point_provider'] + $refunddata['salescost_npay_point_provider_rest'];
	$refunddata['salescost_provider_sales'] =    $refunddata['salescost_event_provider'] + $refunddata['salescost_event_provider_rest']
											   + $refunddata['salescost_multi_provider'] + $refunddata['salescost_multi_provider_rest']
											   + $refunddata['salescost_member_provider'] + $refunddata['salescost_member_provider_rest']
											   + $refunddata['salescost_coupon_provider'] + $refunddata['salescost_coupon_provider_rest']
											   + $refunddata['salescost_fblike_provider'] + $refunddata['salescost_fblike_provider_rest']
											   + $refunddata['salescost_mobile_provider'] + $refunddata['salescost_mobile_provider_rest']
											   + $refunddata['salescost_code_provider'] + $refunddata['salescost_code_provider_rest']
											   + $refunddata['salescost_referer_provider'] + $refunddata['salescost_referer_provider_rest'];
	$refunddata['salescost_provider']			= $refunddata['salescost_provider_promotion'] + $refunddata['salescost_provider_sales'];

	// 부담금 합계
	$refunddata['acc_promotion_total']			= ($refunddata['salescost_admin_promotion']) + ($refunddata['salescost_provider_promotion']); 
	$refunddata['acc_sale_total']				=  $refunddata['salescost_admin_sales'] + $refunddata['salescost_provider_sales'];
	$refunddata['salescost_total']				= $refunddata['acc_promotion_total'] + $refunddata['acc_sale_total'];
	/**
	** 할인부담금 끝
	**/

	/**
	정산계산식 수동처리
	**/
	//동일하게 처리

	$acc_unit_payprice_tmp					= acc_string_floor($refunddata['refund_goods_price'] / $refunddata['ea']); //■ 환불액 (개당)
	//$sales_unit_feeprice					= $acc_unit_payprice_tmp*($refundshipping['commission_rate'])/100;//수수료(단가)
	$refunddata['sales_unit_payprice']		= $acc_unit_payprice_tmp;											//실 결제액 (개당)
	//$refunddata['sales_unit_feeprice']		= $sales_unit_feeprice;												//수수료 단가
	/**
	정산계산식 수동처리
	**/

	return $refunddata;

}

/*
* 정산/매출 환불 추가배송정보 추출
 * #acinsdata 환불정보(get_refund)
 * #refundshipping 배송정보
 * #paycharge 전자결제정보
 * #cal 매출/정산 구분
 * @param 
*/
function account_ins_refundshipping_ck($acinsdata, $refundshipping) {
	$CI =& get_instance();

	$refundshipping['account_type']			= "refund";
	$refundshipping['order_form_seq']		= $acinsdata['refund_seq'];
	$refundshipping['sales_price']			= $refundshipping['refund_delivery_price'];
	if($refundshipping['sales_price'] || $acinsdata['cancel_type'] == 'full') 
	{
		/**
		할인항목별 처리
		**/
		acc_promotion_sales_unit('emoney',		$refundshipping['emoney_sale_unit'],	$refundshipping['emoney_sale_provider'],	$refundshipping);
		acc_promotion_sales_unit('cash',		$refundshipping['cash_sale_unit'],		$refundshipping['cash_sale_provider'],		$refundshipping);
		acc_promotion_sales_unit('enuri',		$refundshipping['enuri_sale_unit'],		$refundshipping['enuri_sale_provider'],		$refundshipping);
/* 이미 계산되어 있는 값을 이용하는데 필요없는 부분이라 삭제 처리 :: 2018-07-17 lkh
		acc_promotion_sales_unit('npay_point',	$refundshipping['npay_point_sale_unit'],$refundshipping['npay_point_sale_provider'],$refundshipping);

		acc_promotion_sales_unit('coupon',		$refundshipping['shipping_coupon_sale'],		$refundshipping['coupon_sale_provider'],$refundshipping);
		acc_promotion_sales_unit('code',		$refundshipping['shipping_promotion_code_sale'],$refundshipping['code_sale_provider'],	$refundshipping);
*/
		/**
		할인항목별 처리끝
		**/

		/**
		** 할인부담금 시작
		**/
		## 본사 부담금
		$refundshipping['salescost_admin_promotion']	= $refundshipping['salescost_emoney'] + $refundshipping['salescost_emoney_rest']
														   + $refundshipping['salescost_cash'] + $refundshipping['salescost_cash_rest']
														   + $refundshipping['salescost_enuri'] + $refundshipping['salescost_enuri_rest'];
		$refundshipping['salescost_admin_sales']		= $refundshipping['salescost_coupon'] + $refundshipping['salescost_coupon_rest']
														   + $refundshipping['salescost_code'] + $refundshipping['salescost_code_rest'];
		$refundshipping['salescost_admin']				= $refundshipping['salescost_admin_promotion'] + $refundshipping['salescost_admin_sales'];

		//입점사 부담금
		$refundshipping['salescost_provider_promotion']	= $refundshipping['salescost_emoney_provider'] + $refundshipping['salescost_emoney_provider_rest']
														   + $refundshipping['salescost_cash_provider'] + $refundshipping['salescost_cash_provider_rest']
														   + $refundshipping['salescost_enuri_provider'] + $refundshipping['salescost_enuri_provider_rest'];
		$refundshipping['salescost_provider_sales']		= $refundshipping['salescost_coupon_provider'] + $refundshipping['salescost_coupon_provider_rest']
														   + $refundshipping['salescost_code_provider'] + $refundshipping['salescost_code_provider_rest'];
		$refundshipping['salescost_provider']			= $refundshipping['salescost_provider_promotion'] + $refundshipping['salescost_provider_sales'];

		// 부담금 합계
		$refundshipping['acc_promotion_total']			= ($refundshipping['salescost_admin_promotion']) + ($refundshipping['salescost_provider_promotion']);
		$refundshipping['acc_sale_total']				= $refundshipping['salescost_admin_sales'] + $refundshipping['salescost_provider_sales'];
		$refundshipping['salescost_total']				= $refundshipping['acc_promotion_total'] + $refundshipping['acc_sale_total'];
		/**
		** 할인부담금 끝
		**/

		/**
		정산계산식 수동처리
		**/
		if( $CI->accountallmodel->account_fee_ar['goods'] || ($acinsdata['shipping_provider_seq']==1 && $acinsdata['provider_seq'] != 1) ) {//상품별 수수료 적용시
			//현재 퍼스트몰기준으로 배송비에는 수수료 미적용되어 동일
			//정산시 위탁배송은 상품가만 처리되기 때문에 입점사 본사배송일 경우 위탁배송은 수수료 0원처리
			/*
			 * 환불시에도 정상 계산되어 등록되도록 수정 :: 2018-05-30 lkh
			 * 환불시 등록된 금액 그대로 등록되도록 수정 :: 2018-08-01 lkh
			 * 3차 환불 개선으로 환불시 입력된 금액으로 처리되도록 수정 :: 2018-11- lkh
			*/ 
			/*
			 * 환불이 진행될 때 배송비는 입력된 금액 그대로 환불 처리해야함 #24179 2019-06-18 by hed
			$acc_unit_payprice_tmp					= $refundshipping['sales_price'];
			$acc_unit_payprice						= $acc_unit_payprice_tmp - $refundshipping['acc_promotion_total'];//환불적립금/환불이머니 차감
			$refundshipping['sale_price']			= $refundshipping['sales_price'];
			$refundshipping['price']				= $acc_unit_payprice_tmp;//상품단가
			$refundshipping['org_price']			= $acc_unit_payprice_tmp;//상품의 판매가
			$refundshipping['consumer_price']		= $acc_unit_payprice_tmp;//개당 정가
			$refundshipping['commission_price']		= $acc_unit_payprice;	//개당 정산금액
			//$refundshipping['commission_price_rest']= 0;					//개당 정산금액-짜투리
			$refundshipping['sales_unit_payprice']	= $acc_unit_payprice_tmp;//실 결제액 (개당)
			// 배송비 수수료 추가
			$refundshipping_price_tmp			= (int)$refundshipping['sales_price'] - $refundshipping['salescost_provider'];//정산대상금액
			if($acinsdata['shipping_provider_seq']==1){
				$refundshipping['commission_rate'] = 100;
			}
			
			$acc_charge_str						= "";
			$acc_charge_str						= $refundshipping['commission_rate']."%";

			$refundshipping['price']			= $refundshipping['sales_price'];//상품단가
			//$refundshipping['price']					= $commission_price = $acc_unit_payprice = $refundshipping['sales_price'] - $refundshipping['salescost_admin'];//상품단가
			//$commission_price 					= $refundshipping['sales_price'] - $refundshipping['salescost_provider']; // 정산금액
			$commission_price_tmp				= $refundshipping_price_tmp*(100-$refundshipping['commission_rate'])/100;
			$sales_unit_feeprice				= $refundshipping_price_tmp*($refundshipping['commission_rate'])/100;//수수료(단가)

			$acc_unit_payprice					= $refundshipping['sales_price'] - $refundshipping['salescost_total']; // 실결제금액
			$sales_unit_minfee					= 0;

			$commission_price_tmp				= floor($commission_price_tmp);
			$commission_price					= $commission_price_tmp;//단품으로 계산 sales_unit_feeprice
			
			$refundshipping['org_price']			= $refundshipping['sales_price'];//상품의 판매가
			$refundshipping['consumer_price']		= $refundshipping['sales_price'];//개당 정가

			$refundshipping['commission_price']		= $commission_price;							//개당 정산금액
			$refundshipping['sales_unit_feeprice']	= $sales_unit_feeprice;							//정산 수수료금액-개당
			$refundshipping['sales_unit_minfee']	= $sales_unit_minfee;							//정산 추가수수료 개당(+)
			$refundshipping['sales_unit_payprice']	= $acc_unit_payprice;							//실 결제액 (개당)
			$refundshipping['commission_text']		= $refundshipping['sales_price'].
														"-".$sales_unit_feeprice.
														"-".$sales_unit_minfee.
														"=".$commission_price." ".$acc_charge_str."";			//정산계산식 상세설명
			*/
			
			// 환불이 진행될 때 배송비는 입력된 금액 그대로 환불 처리해야함 #24179 2019-06-18 by hed
			$acc_unit_payprice_tmp					= acc_string_floor($refundshipping['refund_delivery_price'] / $refundshipping['ea']); //■ 환불액 (개당)
			$refundshipping['sales_unit_payprice']	= $acc_unit_payprice_tmp;											//실 결제액 (개당)

		}elseif($CI->accountallmodel->account_fee_ar['pg']){//강원마트전용 결제수수료
			//엑셀그대로 적용되기 때문에 검증용
			$acc_unit_payprice							= $refundshipping['sales_price'];
			$refundshipping['sale_price']				= $acc_unit_payprice;
			$refundshipping['sales_price']				= $acc_unit_payprice;//상품단가
			$refundshipping['price']					= $acc_unit_payprice;//상품단가
			$refundshipping['org_price']				= $acc_unit_payprice;//상품의 판매가
			$refundshipping['consumer_price']			= $acc_unit_payprice;//개당 정가
			$refundshipping['sales_unit_payprice']		= $acc_unit_payprice;//실 결제액 (개당)

			$commission_price							= $refundshipping['sales_price']-($refundshipping['salescost_admin']+$refundshipping['sales_unit_feeprice']+$refundshipping['sales_unit_minfee']);
			$refundshipping['commission_price']			= $commission_price;//개당 정산금액
			//$refundshipping['commission_price_rest']	= $refundshipping['commission_price_rest'];//개당 정산금액-짜투리
			//$refundshipping['sales_unit_feeprice']	= $refundshipping['sales_unit_feeprice'];//PG수수료금액-개당
			//$refundshipping['sales_unit_minfee']		= $refundshipping['sales_unit_minfee'];//PG수단별-추가수수료 개당(+)	
			$acc_charge_str = "";
			if( $acc_charge_str !='' && $refundshipping['sales_unit_minfee'] > 0)	$acc_charge_str	.= " + ";
			if( $refundshipping['sales_unit_minfee'] > 0)							$acc_charge_str	.= $refundshipping['sales_unit_minfee']."원 (개당 {$sales_unit_minfee}원)";
			$refundshipping['commission_text']			= $refundshipping['sales_price']." - ".$refundshipping['salescost_admin'].
															"-".$refundshipping['sales_unit_feeprice'].
															"-".$refundshipping['sales_unit_minfee'].
															"=".$commission_price." ".$acc_charge_str."";			//정산계산식 상세설명
															//"-".$shipping['salescost_provider'].
		}
	}
	return $refundshipping;
}

/*
* 정산/매출 반품배송비 입점사에게 지불시 정보추출
 * #acinsdata 주문정보
 * #returnshipping 반품배송비정보
 * #paycharge 전자결제정보
 * #cal 매출/정산 구분
 * @param 
*/
function account_ins_returnshipping_ck($returnshipping) {
	$CI =& get_instance();
	if($returnshipping['return_shipping_gubun'] != 'company' || $returnshipping['status'] != 'complete' || !$returnshipping['return_shipping_price'] ) return;

	$returnshipping['account_type']			= "return";
	$returnshipping['order_type']			= "shipping";//returnshipping
	$returnshipping['order_goods_kind']		= "shipping";
	$returnshipping['order_type_view'] 		= $CI->accountallmodel->order_type_ar[$returnshipping['order_type']];
	$returnshipping['goods_code']			= '';
	$returnshipping['order_form_seq']		= $returnshipping['return_seq'];
	$returnshipping['return_code']			= $returnshipping['return_code'];
	$returnshipping['refund_code']			= $returnshipping['refund_code'];
	$returnshipping['refund_type']			= 'return';
	$returnshipping['ea']					= 1;
	$returnshipping['exp_ea']				= 1;
	$returnshipping['ac_ea']				= 1;
	$returnshipping['order_goods_name']		= "";
	$returnshipping['step']					= 85;
	// 반품배송비 수수료
	$returnshipping['commission_type']		= "SACO";//수수료방식 수수료율
	$returnshipping['commission_rate']		= $returnshipping['return_shipping_charge'];//수수료율
	
	// 총 결재금액 = 매출액 배송비 입금을 통한 매출액 추가 by hed
	$returnshipping['total_payprice']		= $returnshipping['return_shipping_price'];

	/**
	빈값으로 등록되어야 해서 초기화
	**/
	// 할인금액 초기화
	$returnshipping['emoney_sale_unit']				= 0;
	$returnshipping['cash_sale_unit']				= 0;
	$returnshipping['enuri_sale_unit']				= 0;
	$returnshipping['npay_point_sale_unit']			= 0;

	$returnshipping['multi_sale_unit']				= 0;
	$returnshipping['event_sale_unit']				= 0;
	$returnshipping['member_sale_unit']				= 0;
	$returnshipping['coupon_sale_unit']				= 0;
	$returnshipping['fblike_sale_unit']				= 0;
	$returnshipping['mobile_sale_unit']				= 0;
	$returnshipping['code_sale_unit']				= 0;
	$returnshipping['referer_sale_unit']			= 0;
	
	$returnshipping['emoney_sale_rest']				= 0;
	$returnshipping['cash_sale_rest']				= 0;
	$returnshipping['enuri_sale_rest']				= 0;
	$returnshipping['npay_point_sale_rest']			= 0;

	$returnshipping['multi_sale_rest']				= 0;
	$returnshipping['event_sale_rest']				= 0;
	$returnshipping['member_sale_rest']				= 0;
	$returnshipping['coupon_sale_rest']				= 0;
	$returnshipping['fblike_sale_rest']				= 0;
	$returnshipping['mobile_sale_rest']				= 0;
	$returnshipping['code_sale_rest']				= 0;
	$returnshipping['referer_sale_rest']			= 0;

	$returnshipping['shipping_coupon_sale']			= 0;
	$returnshipping['shipping_promotion_code_sale']	= 0;

	$returnshipping['emoney_sale_provider']			= 0;
	$returnshipping['cash_sale_provider']			= 0;
	$returnshipping['enuri_sale_provider']			= 0;
	$returnshipping['npay_point_sale_provider']		= 0;

	$returnshipping['multi_sale_provider']			= 0;
	$returnshipping['event_sale_provider']			= 0;
	$returnshipping['member_sale_provider']			= 0;
	$returnshipping['coupon_sale_provider']			= 0;
	$returnshipping['fblike_sale_provider']			= 0;
	$returnshipping['mobile_sale_provider']			= 0;
	$returnshipping['code_sale_provider']			= 0;
	$returnshipping['referer_sale_provider']		= 0;

	$returnshipping['coupon_sale_provider']			= 0;
	$returnshipping['code_sale_provider']			= 0;
	/**
	빈값으로 등록되어야 해서 초기화끝
	**/

	/**
	할인항목별 처리
	**/
	acc_promotion_sales_unit('emoney',		$returnshipping['emoney_sale_unit'],			$returnshipping['emoney_sale_provider'],	$returnshipping);
	acc_promotion_sales_unit('cash',		$returnshipping['cash_sale_unit'],				$returnshipping['cash_sale_provider'],		$returnshipping);
	acc_promotion_sales_unit('enuri',		$returnshipping['enuri_sale_unit'],				$returnshipping['enuri_sale_provider'],		$returnshipping);
	acc_promotion_sales_unit('npay_point',	$returnshipping['npay_point_sale_unit'],		$returnshipping['npay_point_sale_provider'],$returnshipping);
	
	acc_promotion_sales_unit('multi',		$returnshipping['multi_sale_unit'],				$returnshipping['multi_sale_provider'],		$returnshipping);
	acc_promotion_sales_unit('event',		$returnshipping['event_sale_unit'],				$returnshipping['event_sale_provider'],		$returnshipping);
	acc_promotion_sales_unit('member',		$returnshipping['member_sale_unit'],			$returnshipping['member_sale_provider'],	$returnshipping);
	acc_promotion_sales_unit('coupon',		$returnshipping['coupon_sale_unit'],			$returnshipping['coupon_sale_provider'],	$returnshipping);
	acc_promotion_sales_unit('fblike',		$returnshipping['fblike_sale_unit'],			$returnshipping['fblike_sale_provider'],	$returnshipping);
	acc_promotion_sales_unit('mobile',		$returnshipping['mobile_sale_unit'],			$returnshipping['mobile_sale_provider'],	$returnshipping);
	acc_promotion_sales_unit('code',		$returnshipping['code_sale_unit'],				$returnshipping['code_sale_provider'],		$returnshipping);
	acc_promotion_sales_unit('referer',		$returnshipping['referer_sale_unit'],			$returnshipping['referer_sale_provider'],	$returnshipping);

	acc_promotion_sales_unit('coupon',		$returnshipping['shipping_coupon_sale'],		$returnshipping['coupon_sale_provider'],	$returnshipping);
	acc_promotion_sales_unit('code',		$returnshipping['shipping_promotion_code_sale'],$returnshipping['code_sale_provider'],		$returnshipping);
	/**
	할인항목별 처리끝
	**/

	/**
	** 할인부담금 시작
	**/
	## 본사 부담금
	$returnshipping['salescost_admin_promotion']	= $returnshipping['salescost_emoney'] + $returnshipping['salescost_emoney_rest']
													   + $returnshipping['salescost_cash'] + $returnshipping['salescost_cash_rest']
													   + $returnshipping['salescost_enuri'] + $returnshipping['salescost_enuri_rest'];
	$returnshipping['salescost_admin_sales']		= $returnshipping['salescost_coupon'] + $returnshipping['salescost_coupon_rest']
													   + $returnshipping['salescost_code'] + $returnshipping['salescost_code_rest'];
	$returnshipping['salescost_admin']				= $returnshipping['salescost_admin_promotion'] + $returnshipping['salescost_admin_sales'];

	//입점사 부담금
	$returnshipping['salescost_provider_promotion']	= $returnshipping['salescost_emoney_provider'] + $returnshipping['salescost_emoney_provider_rest']
													   + $returnshipping['salescost_cash_provider'] + $returnshipping['salescost_cash_provider_rest']
													   + $returnshipping['salescost_enuri_provider'] + $returnshipping['salescost_enuri_provider_rest'];
	$returnshipping['salescost_provider_sales']		= $returnshipping['salescost_coupon_provider'] + $returnshipping['salescost_coupon_provider_rest']
													   + $returnshipping['salescost_code_provider'] + $returnshipping['salescost_code_provider_rest'];
	$returnshipping['salescost_provider']			= $returnshipping['salescost_provider_promotion'] + $returnshipping['salescost_provider_sales'];
	$returnshipping['acc_promotion_total']			= ($returnshipping['salescost_admin_promotion']) + ($returnshipping['salescost_provider_promotion']);
	$returnshipping['acc_sale_total']				= $returnshipping['salescost_admin_sales'] + $returnshipping['salescost_provider_sales'];
	$returnshipping['salescost_total']				= $returnshipping['acc_promotion_total'] + $returnshipping['acc_sale_total'];
	/**
	** 할인부담금 끝
	**/

	/**
	정산계산식 수동처리
	**/
	if( $CI->accountallmodel->account_fee_ar['goods'] ) {//상품별 수수료 적용시
		// 배송비 수수료 추가
		// 할인이 없기 때문에 등록된 가격으로 처리
		//$return_shipping_price_tmp					= (int)$returnshipping['return_shipping_price'] - $returnshipping['salescost_provider'];//정산대상금액
		$return_shipping_price_tmp					= (int)$returnshipping['return_shipping_price'];//정산대상금액
		
		$acc_charge_str								= "";
		$acc_charge_str								= $returnshipping['commission_rate']."%";

		$returnshipping['price']					= $returnshipping['return_shipping_price'];//상품단가
		$returnshipping['sale_price']				= $returnshipping['return_shipping_price'];//상품단가
		$returnshipping['sales_price']				= $returnshipping['return_shipping_price'];//상품단가
		//$shipping['price']						= $commission_price = $acc_unit_payprice = $shipping['sales_price'] - $shipping['salescost_admin'];//상품단가
		//$commission_price 						= $shipping['sales_price'] - $shipping['salescost_provider']; // 정산금액
		$commission_price_tmp						= $return_shipping_price_tmp*(100-$returnshipping['commission_rate'])/100;
		$sales_unit_feeprice						= $return_shipping_price_tmp*($returnshipping['commission_rate'])/100;//수수료(단가)

		// 할인이 없기 때문에 등록된 가격으로 처리
		//$acc_unit_payprice							= $returnshipping['return_shipping_price'] - $returnshipping['salescost_total']; // 실결제금액
		$acc_unit_payprice							= $returnshipping['return_shipping_price']; // 실결제금액
		$sales_unit_minfee							= 0;

		$commission_price_tmp						= floor($commission_price_tmp);
		$commission_price							= $commission_price_tmp;//단품으로 계산 sales_unit_feeprice
		
		$returnshipping['org_price']				= $returnshipping['return_shipping_price'];//상품의 판매가
		$returnshipping['original_price']			= $returnshipping['return_shipping_price'];//상품의 판매가
		$returnshipping['consumer_price']			= $returnshipping['return_shipping_price'];//개당 정가
		
		$returnshipping['api_pg_price']				= 0;//배송비는 전자결재를 통해 이루어 지지 않음 by hed
		$returnshipping['api_pg_sale_price']		= 0;//PG정산 결제금액
		$returnshipping['api_pg_commission_price']	= 0;//PG정산 정산금액 (개당)

		$returnshipping['commission_price']			= $commission_price;							//개당 정산금액
		$returnshipping['sales_unit_feeprice']		= $sales_unit_feeprice;							//정산 수수료금액-개당
		$returnshipping['sales_unit_minfee']		= $sales_unit_minfee;							//정산 추가수수료 개당(+)
		$returnshipping['sales_unit_payprice']		= $acc_unit_payprice;							//실 결제액 (개당)
		$returnshipping['commission_text']			= $returnshipping['return_shipping_price'].
													"-".$sales_unit_feeprice.
													"-".$sales_unit_minfee.
													"=".$commission_price." ".$acc_charge_str."";			//정산계산식 상세설명
/*
		//현재 퍼스트몰기준으로 배송비에는 수수료 미적용
		$acc_unit_payprice_tmp						= $returnshipping['return_shipping_price'];
		$acc_unit_payprice							= $acc_unit_payprice_tmp - $returnshipping['acc_promotion_total'];//환불적립금/환불이머니 차감
		$returnshipping['sale_price']				= $acc_unit_payprice;
		$returnshipping['sales_price']				= $acc_unit_payprice;//상품단가
		$returnshipping['price']					= $acc_unit_payprice;//상품단가
		$returnshipping['org_price']				= $acc_unit_payprice;//상품의 판매가
		$returnshipping['original_price']			= $acc_unit_payprice;//상품의 판매가
		$returnshipping['consumer_price']			= $acc_unit_payprice;//개당 정가
		$returnshipping['sales_unit_payprice']		= $acc_unit_payprice;//실 결제액 (개당)
		$returnshipping['commission_price']			= $acc_unit_payprice;//개당 정산금액
		$returnshipping['api_pg_sale_price']		= 0;//PG정산 결제금액
		$returnshipping['api_pg_commission_price']	= 0;//PG정산 정산금액 (개당)

		$returnshipping['commission_price_rest']	= 0;//개당 정산금액-짜투리
		$returnshipping['sales_unit_feeprice']		= 0;//PG수수료금액-개당
		$returnshipping['sales_unit_minfee']		= 0;//PG수단별-추가수수료 개당(+)	
		$returnshipping['commission_text']			= "";
*/
	}elseif($CI->accountallmodel->account_fee_ar['pg']){//강원마트전용 결제수수료
		//엑셀그대로 적용되기 때문에 검증용
		$acc_unit_payprice							= $returnshipping['return_shipping_price'];
		$returnshipping['sale_price']				= $acc_unit_payprice;
		$returnshipping['sales_price']				= $acc_unit_payprice;//상품단가
		$returnshipping['price']					= $acc_unit_payprice;//상품단가
		$returnshipping['org_price']				= $acc_unit_payprice;//상품의 판매가
		$returnshipping['consumer_price']			= $acc_unit_payprice;//개당 정가
		$returnshipping['sales_unit_payprice']		= $acc_unit_payprice;//실 결제액 (개당)

		$commission_price							= $returnshipping['return_shipping_price']-($returnshipping['salescost_admin']+$returnshipping['sales_unit_feeprice']+$returnshipping['sales_unit_minfee']);
		$returnshipping['commission_price']			= $commission_price;//개당 정산금액
		//$returnshipping['commission_price_rest']	= $returnshipping['commission_price_rest'];//개당 정산금액-짜투리
		//$returnshipping['sales_unit_feeprice']	= $returnshipping['sales_unit_feeprice'];//PG수수료금액-개당
		//$returnshipping['sales_unit_minfee']		= $returnshipping['sales_unit_minfee'];//PG수단별-추가수수료 개당(+)	
		$acc_charge_str = "";
		if( $acc_charge_str !='' && $returnshipping['sales_unit_minfee'] > 0)	$acc_charge_str	.= " + ";
		if( $returnshipping['sales_unit_minfee'] > 0)							$acc_charge_str	.= $returnshipping['sales_unit_minfee']."원 (개당 {$sales_unit_minfee}원)";
		$returnshipping['commission_text']			= $returnshipping['sales_price']." - ".$returnshipping['salescost_admin'].
														"-".$returnshipping['sales_unit_feeprice'].
														"-".$returnshipping['sales_unit_minfee'].
														"=".$commission_price." ".$acc_charge_str."";			//정산계산식 상세설명
														//"-".$shipping['salescost_provider'].
														
		$returnshipping['api_pg_price']				= $acc_unit_payprice;
		$returnshipping['api_pg_support_price']		= 0;
		
		$returnshipping['api_pg_commission_price']			= $returnshipping['commission_price'];
		$returnshipping['api_pg_commission_price_rest']		= 0;
		$returnshipping['api_pg_sales_unit_feeprice']		= 0;
		$returnshipping['api_pg_sales_feeprice_rest']		= 0;
	}

	/**
	정산계산식 수동처리
	**/
	return $returnshipping;
}

/*
* 정산/매출 조정금액(상품,배송,환불위약금) 추출
 * #acinsdata 공제상품정보(get_refund_item)
 * #deductibledata 배송정보
 * #paycharge 전자결제정보
 * #cal 매출/정산 구분
 * @param 
*/
function account_ins_deductible_ck($deductibledata) {
	$CI =& get_instance();
	if(!$deductibledata || $deductibledata['status'] != 'complete' || !$deductibledata['deductible_price'] ) return;

	$deductibledata['account_type']			= "deductible";
	$deductibledata['goods_code']			= '';
	$deductibledata['order_form_seq']		= $deductibledata['refund_seq'];
	$deductibledata['return_code']			= $deductibledata['return_code'];
	$deductibledata['refund_code']			= $deductibledata['refund_code'];
	$deductibledata['ea']					= 1;
	$deductibledata['exp_ea']				= 1;
	$deductibledata['ac_ea']				= 1;
	$deductibledata['step']					= 85;

	/**
	빈값으로 등록되어야 해서 초기화
	**/
	// 할인금액 초기화
	$deductibledata['emoney_sale_unit']				= 0;
	$deductibledata['cash_sale_unit']				= 0;
	$deductibledata['enuri_sale_unit']				= 0;
	$deductibledata['npay_point_sale_unit']			= 0;

	$deductibledata['multi_sale_unit']				= 0;
	$deductibledata['event_sale_unit']				= 0;
	$deductibledata['member_sale_unit']				= 0;
	$deductibledata['coupon_sale_unit']				= 0;
	$deductibledata['fblike_sale_unit']				= 0;
	$deductibledata['mobile_sale_unit']				= 0;
	$deductibledata['code_sale_unit']				= 0;
	$deductibledata['referer_sale_unit']			= 0;
	
	$deductibledata['emoney_sale_rest']				= 0;
	$deductibledata['cash_sale_rest']				= 0;
	$deductibledata['enuri_sale_rest']				= 0;
	$deductibledata['npay_point_sale_rest']			= 0;

	$deductibledata['multi_sale_rest']				= 0;
	$deductibledata['event_sale_rest']				= 0;
	$deductibledata['member_sale_rest']				= 0;
	$deductibledata['coupon_sale_rest']				= 0;
	$deductibledata['fblike_sale_rest']				= 0;
	$deductibledata['mobile_sale_rest']				= 0;
	$deductibledata['code_sale_rest']				= 0;
	$deductibledata['referer_sale_rest']			= 0;

	$deductibledata['shipping_coupon_sale']			= 0;
	$deductibledata['shipping_promotion_code_sale']	= 0;

	$deductibledata['emoney_sale_provider']			= 0;
	$deductibledata['cash_sale_provider']			= 0;
	$deductibledata['enuri_sale_provider']			= 0;
	$deductibledata['npay_point_sale_provider']		= 0;

	$deductibledata['multi_sale_provider']			= 0;
	$deductibledata['event_sale_provider']			= 0;
	$deductibledata['member_sale_provider']			= 0;
	$deductibledata['coupon_sale_provider']			= 0;
	$deductibledata['fblike_sale_provider']			= 0;
	$deductibledata['mobile_sale_provider']			= 0;
	$deductibledata['code_sale_provider']			= 0;
	$deductibledata['referer_sale_provider']		= 0;

	$deductibledata['coupon_sale_provider']			= 0;
	$deductibledata['code_sale_provider']			= 0;
	/**
	빈값으로 등록되어야 해서 초기화끝
	**/

	/**
	할인항목별 처리
	**/
	acc_promotion_sales_unit('emoney',		$deductibledata['emoney_sale_unit'],			$deductibledata['emoney_sale_provider'],	$deductibledata);
	acc_promotion_sales_unit('cash',		$deductibledata['cash_sale_unit'],				$deductibledata['cash_sale_provider'],		$deductibledata);
	acc_promotion_sales_unit('enuri',		$deductibledata['enuri_sale_unit'],				$deductibledata['enuri_sale_provider'],		$deductibledata);
	acc_promotion_sales_unit('npay_point',	$deductibledata['npay_point_sale_unit'],		$deductibledata['npay_point_sale_provider'],$deductibledata);
	
	acc_promotion_sales_unit('multi',		$deductibledata['multi_sale_unit'],				$deductibledata['multi_sale_provider'],		$deductibledata);
	acc_promotion_sales_unit('event',		$deductibledata['event_sale_unit'],				$deductibledata['event_sale_provider'],		$deductibledata);
	acc_promotion_sales_unit('member',		$deductibledata['member_sale_unit'],			$deductibledata['member_sale_provider'],	$deductibledata);
	acc_promotion_sales_unit('coupon',		$deductibledata['coupon_sale_unit'],			$deductibledata['coupon_sale_provider'],	$deductibledata);
	acc_promotion_sales_unit('fblike',		$deductibledata['fblike_sale_unit'],			$deductibledata['fblike_sale_provider'],	$deductibledata);
	acc_promotion_sales_unit('mobile',		$deductibledata['mobile_sale_unit'],			$deductibledata['mobile_sale_provider'],	$deductibledata);
	acc_promotion_sales_unit('code',		$deductibledata['code_sale_unit'],				$deductibledata['code_sale_provider'],		$deductibledata);
	acc_promotion_sales_unit('referer',		$deductibledata['referer_sale_unit'],			$deductibledata['referer_sale_provider'],	$deductibledata);

	acc_promotion_sales_unit('coupon',		$deductibledata['shipping_coupon_sale'],		$deductibledata['coupon_sale_provider'],	$deductibledata);
	acc_promotion_sales_unit('code',		$deductibledata['shipping_promotion_code_sale'],$deductibledata['code_sale_provider'],		$deductibledata);
	/**
	할인항목별 처리끝
	**/

	/**
	** 할인부담금 시작
	**/
	## 본사 부담금
	$deductibledata['salescost_admin_promotion']	= $deductibledata['salescost_emoney'] + $deductibledata['salescost_emoney_rest']
													   + $deductibledata['salescost_cash'] + $deductibledata['salescost_cash_rest']
													   + $deductibledata['salescost_enuri'] + $deductibledata['salescost_enuri_rest'];
	$deductibledata['salescost_admin_sales']		= $deductibledata['salescost_coupon'] + $deductibledata['salescost_coupon_rest']
													   + $deductibledata['salescost_code'] + $deductibledata['salescost_code_rest'];
	$deductibledata['salescost_admin']				= $deductibledata['salescost_admin_promotion'] + $deductibledata['salescost_admin_sales'];

	//입점사 부담금
	$deductibledata['salescost_provider_promotion']	= $deductibledata['salescost_emoney_provider'] + $deductibledata['salescost_emoney_provider_rest']
													   + $deductibledata['salescost_cash_provider'] + $deductibledata['salescost_cash_provider_rest']
													   + $deductibledata['salescost_enuri_provider'] + $deductibledata['salescost_enuri_provider_rest'];
	$deductibledata['salescost_provider_sales']		= $deductibledata['salescost_coupon_provider'] + $deductibledata['salescost_coupon_provider_rest']
													   + $deductibledata['salescost_code_provider'] + $deductibledata['salescost_code_provider_rest'];
	$deductibledata['salescost_provider']			= $deductibledata['salescost_provider_promotion'] + $deductibledata['salescost_provider_sales'];
	$deductibledata['acc_promotion_total']			= ($deductibledata['salescost_admin_promotion']) + ($deductibledata['salescost_provider_promotion']);
	$deductibledata['acc_sale_total']				= $deductibledata['salescost_admin_sales'] + $deductibledata['salescost_provider_sales'];
	$deductibledata['salescost_total']				= $deductibledata['acc_promotion_total'] + $deductibledata['acc_sale_total'];
	/**
	** 할인부담금 끝
	**/

	/**
	정산계산식 수동처리
	**/
	if( $CI->accountallmodel->account_fee_ar['goods'] ) {//상품별 수수료 적용시
		// 배송비 수수료 추가
		// 할인이 없기 때문에 등록된 가격으로 처리
		$deductible_price_tmp						= (int)$deductibledata['deductible_price'];//정산대상금액
		
		$acc_charge_str								= "";
		$acc_charge_str								= $deductibledata['commission_rate']."%";

		$deductibledata['price']					= $deductibledata['deductible_price'];//상품단가
		$deductibledata['sale_price']				= $deductibledata['deductible_price'];//상품단가
		$deductibledata['sales_price']				= $deductibledata['deductible_price'];//상품단가
		//$shipping['price']						= $commission_price = $acc_unit_payprice = $shipping['sales_price'] - $shipping['salescost_admin'];//상품단가
		//$commission_price 						= $shipping['sales_price'] - $shipping['salescost_provider']; // 정산금액
		if($deductibledata['commission_type'] == "SACO"){
			$commission_price_tmp					= $deductible_price_tmp*(100-$deductibledata['commission_rate'])/100;
			$sales_unit_feeprice					= $deductible_price_tmp*($deductibledata['commission_rate'])/100;//수수료(단가)
		}else{
			//공급가 방식 정산
			if($optdata['commission_type'] == 'SUPR'){
				$deductibledata['commission_rate']	= $deductible_price_tmp;
			}else{
				$deductibledata['commission_rate']	= 100;
			}
			$commission_price_tmp					= $deductible_price_tmp;
			$sales_unit_feeprice					= 0;//수수료단가
		}

		// 할인이 없기 때문에 등록된 가격으로 처리
		$acc_unit_payprice							= $deductibledata['deductible_price']; // 실결제금액
		$sales_unit_minfee							= 0;

		$commission_price_tmp						= floor($commission_price_tmp);
		$commission_price							= $commission_price_tmp;//단품으로 계산 sales_unit_feeprice
		
		$deductibledata['org_price']				= $deductibledata['deductible_price'];//상품의 판매가
		$deductibledata['original_price']			= $deductibledata['deductible_price'];//상품의 판매가
		$deductibledata['consumer_price']			= $deductibledata['deductible_price'];//개당 정가
		
		$deductibledata['api_pg_sale_price']		= 0;//PG정산 결제금액
		$deductibledata['api_pg_commission_price']	= 0;//PG정산 정산금액 (개당)

		$deductibledata['commission_price']			= $commission_price;							//개당 정산금액
		$deductibledata['sales_unit_feeprice']		= $sales_unit_feeprice;							//정산 수수료금액-개당
		$deductibledata['sales_unit_minfee']		= $sales_unit_minfee;							//정산 추가수수료 개당(+)
		$deductibledata['sales_unit_payprice']		= $acc_unit_payprice;							//실 결제액 (개당)
		$deductibledata['commission_text']			= $deductibledata['deductible_price'].
													"-".$sales_unit_feeprice.
													"-".$sales_unit_minfee.
													"=".$commission_price." ".$acc_charge_str."";			//정산계산식 상세설명

	}elseif($CI->accountallmodel->account_fee_ar['pg']){//강원마트전용 결제수수료
		//엑셀그대로 적용되기 때문에 검증용
		$acc_unit_payprice							= $deductibledata['return_shipping_price'];
		$deductibledata['sale_price']				= $acc_unit_payprice;
		$deductibledata['sales_price']				= $acc_unit_payprice;//상품단가
		$deductibledata['price']					= $acc_unit_payprice;//상품단가
		$deductibledata['org_price']				= $acc_unit_payprice;//상품의 판매가
		$deductibledata['consumer_price']			= $acc_unit_payprice;//개당 정가
		$deductibledata['sales_unit_payprice']		= $acc_unit_payprice;//실 결제액 (개당)

		$commission_price							= $deductibledata['return_shipping_price']-($deductibledata['salescost_admin']+$deductibledata['sales_unit_feeprice']+$deductibledata['sales_unit_minfee']);
		$deductibledata['commission_price']			= $commission_price;//개당 정산금액
		//$deductibledata['commission_price_rest']	= $deductibledata['commission_price_rest'];//개당 정산금액-짜투리
		//$deductibledata['sales_unit_feeprice']	= $deductibledata['sales_unit_feeprice'];//PG수수료금액-개당
		//$deductibledata['sales_unit_minfee']		= $deductibledata['sales_unit_minfee'];//PG수단별-추가수수료 개당(+)	
		$acc_charge_str = "";
		if( $acc_charge_str !='' && $deductibledata['sales_unit_minfee'] > 0)	$acc_charge_str	.= " + ";
		if( $deductibledata['sales_unit_minfee'] > 0)							$acc_charge_str	.= $deductibledata['sales_unit_minfee']."원 (개당 {$sales_unit_minfee}원)";
		$deductibledata['commission_text']			= $deductibledata['sales_price']." - ".$deductibledata['salescost_admin'].
														"-".$deductibledata['sales_unit_feeprice'].
														"-".$deductibledata['sales_unit_minfee'].
														"=".$commission_price." ".$acc_charge_str."";			//정산계산식 상세설명
														//"-".$shipping['salescost_provider'].
														
		$deductibledata['api_pg_price']				= $acc_unit_payprice;
		$deductibledata['api_pg_support_price']		= 0;
		
		$deductibledata['api_pg_commission_price']			= $deductibledata['commission_price'];
		$deductibledata['api_pg_commission_price_rest']		= 0;
		$deductibledata['api_pg_sales_unit_feeprice']		= 0;
		$deductibledata['api_pg_sales_feeprice_rest']		= 0;
	}

	/**
	정산계산식 수동처리
	**/
	return $deductibledata;
}


/*
 * 임시매출/미정산/당월출고정산/당월매출 데이타 체크
 * $cal 정산/매출 구분
 * @param
*/ 
function ins_calculate_ck($params, $cal=null) {
	// 변수명이 바뀌어 정상적으로 변수 유지를 못 하는 오류가 있어 수정 by hed
	$arr_replace_params_all = array(
		'api_pg_add1' => 'top_orign_order_seq',
		'api_pg_add2' => 'orign_order_seq',
		'sales_basic' => 'basic_sale',	
	);
	$arr_replace_params_suboption = array(
		'optioncode1' => 'suboption_code',	
	);
	foreach($arr_replace_params_all as $after_param=>$before_param){
		if($params[$after_param] && empty($params[$before_param])){
			$params[$before_param] = $params[$after_param];
		}
	}
	if($params['order_type']=='suboption'){
		foreach($arr_replace_params_suboption as $after_param=>$before_param){
			if($params[$after_param] && empty($params[$before_param])){
				$params[$before_param] = $params[$after_param];
			}
		}
	}
	
	$CI =& get_instance();
	//매출 관련 정보
	$regist_date_ck		= str_replace("-","",substr($params['regist_date'],0,7));//Y-m-d			=>Ym
	$deposit_date_ck	= str_replace("-","",substr($params['deposit_date'],0,7));//Y-m-d H:i:s		=>Ym
	$shipping_date_ck	= str_replace("-","",substr($params['shipping_date'],0,7));//Y-m-d			=>Ym
	$confirm_date_ck	= str_replace("-","",substr($params['confirm_date'],0,7));//Y-m-d			=>Ym
	$refund_date_ck		= str_replace("-","",substr($params['refund_date'],0,7));//Y-m-d			=>Ym	
	if($params['order_step'] == '95' || $params['order_step'] == '99' ) return;//주문무효/결제실패 처리 불가

	if($cal){//정산데이타 검증
		if($params['account_type'] == 'refund'){//환불완료시점으로 
			$insdata['acc_table']				= $refund_date_ck;
			$insdata['status']					= ($deposit_date_ck==$refund_date_ck)?"complete":"carryover";//정산상태(이월/당월)
		}else{
			$insdata['acc_table']				= $confirm_date_ck;
			$insdata['status']					= ($deposit_date_ck==$confirm_date_ck)?"complete":"carryover";//정산상태(이월/당월)
		}

		$insdata['acc_date']						= $params['shipping_date'];	//구매확정일과 동일함
	}else{
		$insdata['acc_table']					= ($deposit_date_ck)?$deposit_date_ck:$shipping_date_ck;
		$insdata['status']						= "overdraw";//($deposit_date_ck==$shipping_date_ck)?"complete":"overdraw";//매출상태(당월/차월)
	}

	$insdata['account_type']					= $params['account_type'];	//주문유형(주문/환불/반품배송비/되돌리기)
	$insdata['order_type']						= $params['order_type'];	//주문구분(필수옵션/추가옵션/배송비)
	$insdata['order_referer']					= ($params['order_referer'])?$params['order_referer']:'shop';

	$insdata['regist_date']						= date('Y-m-d H:i:s');
	$insdata['up_date']							= ($params['up_date'])?$params['up_date']:date('Y-m-d H:i:s');
	$insdata['memo']							= $params['ac_memo'];

	$insdata['pg_ordernum']						= ($params['pg_ordernum'])?$params['pg_ordernum']:$params['pg_transaction_number'];//PG거래번호
	$insdata['pg_ordernum_approval']			= ($params['pg_ordernum_approval'])?$params['pg_ordernum_approval']:$params['pg_approval_number'];//PG승인번호

	//엑셀정산 정보(강원마트별) 검증데이타테이블로 개발 

	//외부마켓 정보
	if($params['npay_order_id']){
		$linkage_order_id		= $params['npay_order_id'];
		$linkage_mall_order_id	= $params['npay_product_order_id'];
	// // 톡구매 주문서 아이디 정산에 노출시키기 위해서 db 넣어준다
	} elseif (isset($params['talkbuy_order_id']) === true && strlen($params['talkbuy_order_id']) > 0) {
		$linkage_order_id		= $params['talkbuy_order_id'];
		$linkage_mall_order_id	= $params['talkbuy_product_order_id'];
    }else{
		$linkage_order_id		= $params['linkage_order_id'];
		$linkage_mall_order_id	= $params['linkage_mall_order_id'];
	}
	$insdata['linkage_order_id']				= $linkage_order_id;//연동업체 주문번호(npay 주문번호,openmarket)
	$insdata['linkage_mall_order_id']			= $linkage_mall_order_id;//연동마켓 주문번호(npay 상품 주문 번호,openmarket)
	$insdata['market_option_code']				= $params['market_option_code'];//연동마켓 주문상품옵션코드(coupang)
	$insdata['market_order_seq']				= $params['market_order_seq'];//연동마켓 주문상품번호

	//네이버페이 정보
	$insdata['npay_point']						= $params['npay_point'];
	$insdata['npay_point_unit_feeprice']		= $params['npay_point_unit_feeprice'];
	$insdata['npay_point_feeprice_rest']		= $params['npay_point_feeprice_rest'];

	$insdata['npay_sale_seller']				= $params['npay_sale_seller'];
	$insdata['npay_sale_npay']					= $params['npay_sale_npay'];

	//주문 기본정보
	$insdata['order_seq']						= $params['order_seq'];
	$insdata['api_pg_add1']						= $params['top_orign_order_seq'];//맞교환주문의 원주문번호
	$insdata['api_pg_add2']						= $params['orign_order_seq'];//현주문의 최상단
	$insdata['item_seq']						= ($params['item_seq'])?$params['item_seq']:0;
	//각 옵션/추가옵션/배송그룹/환불/반품 고유번호(item_option_seq/item_suboption_seq/shipping_seq/refund_seq/return_seq)
	$insdata['order_form_seq']					= $params['order_form_seq'];
	$insdata['shipping_group_seq']				= ($params['shipping_seq'])?$params['shipping_seq']:$params['shipping_group_seq'];
	$insdata['shipping_provider_seq']			= $params['shipping_provider_seq'];
	$insdata['shipping_seq']					= $params['shipping_seq'];

	$insdata['pg']								= ($params['pg'])?$params['pg']:$CI->config_system['pgCompany'];//PG업체 order_referer
	$insdata['payment_type']					= ($params['payment_type'])?$params['payment_type']:$params['order_referer'];		//결제구분(shop/pg/오픈마켓/kakaopay 외)
	$insdata['payment']							= ($params['payment'])?$params['payment']:'bank';			//결제수단

	$insdata['order_goods_seq']					= ($params['order_goods_seq'])?$params['order_goods_seq']:$params['goods_seq'];
    if(empty($insdata['order_goods_seq'])){
		$insdata['order_goods_seq'] = '0';
	}
	$insdata['order_member_seq']				= ($params['order_member_seq'])?$params['order_member_seq']:$params['member_seq'];
	$insdata['order_user_name']					= ($params['order_user_name'])?$params['order_user_name']:$params['user_name'];
	$insdata['order_goods_name']				= ($params['order_goods_name'])?$params['order_goods_name']:$params['goods_name'];
	$insdata['order_goods_kind']				= ($params['order_goods_kind'])?$params['order_goods_kind']:$params['goods_kind'];
	$insdata['account_target']					= ($params['account_target'])?$params['account_target']:'sales';//정산대상(매출/정산)
	
	$insdata['step']							= $params['step'];
	$insdata['provider_seq']					= $params['provider_seq'];
	$insdata['goods_code']						= $params['goods_code'];

	$insdata['order_regist_date']				= $params['order_regist_date'];	//최초주문일
	$insdata['deposit_date']					= $params['deposit_date'];		//결제확인-매출
	$insdata['confirm_date']					= $params['confirm_date'];		//구매확정-정산
	$insdata['shipping_date']					= $params['shipping_date'];		//배송완료-정산

	//상품/배송비 상세정보
	$insdata['export_code']						= ($params['export_code'])?$params['export_code']:'';	//구매확정시 출고정보
	$insdata['refund_code']						= ($params['refund_code'])?$params['refund_code']:'';	//환불완료시 환불정보
	$insdata['return_code']						= ($params['return_code'])?$params['return_code']:'';	//반품배송비 완료시 반품정보
	$insdata['refund_type']						= ($params['refund_type'])?$params['refund_type']:'';	//환불타입

	$insdata['ea']								= $params['ea'];										//수량
	$insdata['exp_ea']							= ($params['exp_ea'])?$params['exp_ea']:$params['ea'];	//정산가능수량
	$insdata['ac_ea']							= ($params['ac_ea'])?$params['ac_ea']:$params['ea'];	//정산수량

	$insdata['price']							= ($params['price'])?$params['price']:0;				//상품단가
	$insdata['org_price']						= ($params['goods_price'])?$params['goods_price']:0;	//상품의 판매가
	$insdata['consumer_price']					= ($params['consumer_price'])?$params['consumer_price']:0;	//개당 정가
	$insdata['supply_price']					= ($params['supply_price'])?$params['supply_price']:0;		//개당 매입가

	$insdata['commission_type']					= ($params['commission_type'])?$params['commission_type']:'';			//임시매출데이타전용 수수료율
	$insdata['commission_rate']					= ($params['commission_rate'])?$params['commission_rate']:0;			//임시매출데이타전용 수수료율
	$insdata['commission_price']				= ($params['commission_price'])?$params['commission_price']:0;			//개당 정산금액
	$insdata['commission_price_rest']			= $params['commission_price_rest'];//개당 정산금액-짜투리
	$insdata['commission_text']					= $params['commission_text'];		//정산계산식 상세설명
	$insdata['total_commission_price']			= $params['total_commission_price'];		// @20190520 정산 총 금액
	$insdata['total_feeprice']					= $params['total_feeprice'];				// @20190520 수수료 총 금액
	$insdata['total_payprice']					= $params['total_payprice'];				// 총 결재금액 by hed
	
	$insdata['sitetype']						= $params['sitetype'];				// 판매환경
	
	/**
	*무통장 api 정산 필드 정의
	**/
	if( $params['order_referer'] = 'npg' || $params['order_referer'] = 'kakaopay'  || $params['order_referer'] = 'pg' || $params['pg'] = 'bank' ) {
		$insdata['api_pg_price']					= $insdata['price']*$insdata['ea'];
	}
	//11번가/쿠팡 판매마켓할인과 제휴사할인
	if( $params['api_pg_sale_price'] )		$insdata['api_pg_sale_price']			= $params['api_pg_sale_price'];
	if( $params['api_pg_support_price'] )	$insdata['api_pg_support_price']		= $params['api_pg_support_price'];

	if( $params['order_referer'] == 'shop' && $params['payment'] == 'bank' ) {
		$insdata['api_pg_commission_price']			= $insdata['commission_price'];
		$insdata['api_pg_commission_price_rest']	= $insdata['commission_price_rest'];
		$insdata['api_pg_sales_unit_feeprice']		= $insdata['sales_unit_feeprice'];
		$insdata['api_pg_sales_feeprice_rest']		= $insdata['sales_feeprice_rest'];
	}

	$insdata['sales_price']						= ($params['sales_price'])?$params['sales_price']:$params['sale_price'];//총 결제금액-개당

	$insdata['sales_unit_feeprice']				= ($params['sales_unit_feeprice'])?$params['sales_unit_feeprice']:0;//수수료금액-개당
	$insdata['sales_feeprice_rest']				= $params['sales_feeprice_rest'];//수수료-짜투리
	$insdata['sales_unit_minfee']				= ($params['sales_unit_minfee'])?$params['sales_unit_minfee']:0;	//수단별-추가수수료 개당(+)
	$insdata['sales_unit_payprice']				= ($params['sales_unit_payprice'])?$params['sales_unit_payprice']:0;//실 결제액 (개당)
	$insdata['settleprice']						= ($params['settleprice'])?$params['settleprice']:0;				//총 결제액

	$insdata['title1']							= ($insdata['order_type']=='suboption' && $params['title'])?$params['title']:$params['title1'];
	$insdata['option1']							= ($insdata['order_type']=='suboption' && $params['suboption'])?$params['suboption']:$params['option1'];
	if($params['title2']) $insdata['title2']	= $params['title2'];
	if($params['option2']) $insdata['option2']	= $params['option2'];
	if($params['title3']) $insdata['title3']	= $params['title3'];
	if($params['option3']) $insdata['option3']	= $params['option3'];
	if($params['title4']) $insdata['title4']	= $params['title4'];
	if($params['option4']) $insdata['option4']	= $params['option4'];
	if($params['title5']) $insdata['title5']	= $params['title5'];
	if($params['option5']) $insdata['option5']	= $params['option5'];
	$insdata['optioncode1']						= ($insdata['order_type']=='suboption')?$params['suboption_code']:$params['optioncode1'];
	$insdata['optioncode2']						= $params['optioncode2'];
	$insdata['optioncode3']						= $params['optioncode3'];
	$insdata['optioncode4']						= $params['optioncode4'];
	$insdata['optioncode5']						= $params['optioncode5'];

	$insdata['original_price']					= ($params['original_price'])?$params['original_price']:0;			//할인미적용정가
	$insdata['event_sale_target']				= ($params['event_sale_target'])?$params['event_sale_target']:0;	//이벤트할인 기준(판매가/정가)
	$insdata['sales_basic']						= ($params['basic_sale'])?$params['basic_sale']:0;					//0. 기본할인(개당)
	$insdata['event_sale_unit']					= ($params['event_sale_unit'])?$params['event_sale_unit']:0;		//1 이벤트할인(개당)
	$insdata['multi_sale_unit']					= ($params['multi_sale_unit'])?$params['multi_sale_unit']:0;		//2 복수구매할인(개당)
	
	$insdata['coupon_sale_unit']				= ($params['coupon_sale_unit'])?$params['coupon_sale_unit']:0;		//3 쿠폰할인(개당)
	$insdata['code_sale_unit']					= ($params['code_sale_unit'])?$params['code_sale_unit']:0;			//4 코드할인(개당)
	$insdata['member_sale_unit']				= ($params['member_sale_unit'])?$params['member_sale_unit']:0;		//5 등급할인(개당)
	$insdata['fblike_sale_unit']				= ($params['fblike_sale_unit'])?$params['fblike_sale_unit']:0;		//6 좋아요할인(개당)
	$insdata['mobile_sale_unit']				= ($params['mobile_sale_unit'])?$params['mobile_sale_unit']:0;		//7 모바일할인(개당)
	$insdata['referer_sale_unit']				= ($params['referer_sale_unit'])?$params['referer_sale_unit']:0;	//8 유입경로할인(개당)
	
	$insdata['coupon_sale_rest']				= ($params['coupon_sale_rest'])?$params['coupon_sale_rest']:0;		//3 쿠폰할인-짜투리
	$insdata['code_sale_rest']					= ($params['code_sale_rest'])?$params['code_sale_rest']:0;			//4 코드할인-짜투리
	$insdata['member_sale_rest']				= ($params['member_sale_rest'])?$params['member_sale_rest']:0;		//5 등급할인-짜투리
	$insdata['fblike_sale_rest']				= ($params['fblike_sale_rest'])?$params['fblike_sale_rest']:0;		//6 좋아요할인-짜투리
	$insdata['mobile_sale_rest']				= ($params['mobile_sale_rest'])?$params['mobile_sale_rest']:0;		//7 모바일할인-짜투리
	$insdata['referer_sale_rest']				= ($params['referer_sale_rest'])?$params['referer_sale_rest']:0;	//8 유입경로할인-짜투리
	
	/*
	* 8가지 할인 할인부담금-통신판매중계자와 짜투리
	*/
	$insdata['salescost_event']		= ($params['salescost_event'])?$params['salescost_event']:0;		//1 이벤트할인
	$insdata['salescost_multi']		= ($params['salescost_multi'])?$params['salescost_multi']:0;		//2 복수구매할인
	$insdata['salescost_coupon']	= ($params['salescost_coupon'])?$params['salescost_coupon']:0;		//3 쿠폰할인
	$insdata['salescost_code']		= ($params['salescost_code'])?$params['salescost_code']:0;			//4 코드할인
	$insdata['salescost_member']	= ($params['salescost_member'])?$params['salescost_member']:0;		//5 등급할인
	$insdata['salescost_fblike']	= ($params['salescost_fblike'])?$params['salescost_fblike']:0;		//6 좋아요할인
	$insdata['salescost_mobile']	= ($params['salescost_mobile'])?$params['salescost_mobile']:0;		//7 모바일할인
	$insdata['salescost_referer']	= ($params['salescost_referer'])?$params['salescost_referer']:0;	//8 유입경로할인

	$insdata['salescost_event_rest']	= ($params['salescost_event_rest'])?$params['salescost_event_rest']:0;
	$insdata['salescost_multi_rest']	= ($params['salescost_multi_rest'])?$params['salescost_multi_rest']:0;
	$insdata['salescost_coupon_rest']	= ($params['salescost_coupon_rest'])?$params['salescost_coupon_rest']:0;
	$insdata['salescost_code_rest']		= ($params['salescost_code_rest'])?$params['salescost_code_rest']:0;
	$insdata['salescost_member_rest']	= ($params['salescost_member_rest'])?$params['salescost_member_rest']:0;
	$insdata['salescost_fblike_rest']	= ($params['salescost_fblike_rest'])?$params['salescost_fblike_rest']:0;
	$insdata['salescost_mobile_rest']	= ($params['salescost_mobile_rest'])?$params['salescost_mobile_rest']:0;
	$insdata['salescost_referer_rest']	= ($params['salescost_referer_rest'])?$params['salescost_referer_rest']:0;

	/*
	* 8가지 할인 할인부담금-입점사와 짜투리
	*/
	$insdata['salescost_event_provider']		= ($params['salescost_event_provider'])?$params['salescost_event_provider']:0;		//1 이벤트할인-입점사
	$insdata['salescost_multi_provider']		= ($params['salescost_multi_provider'])?$params['salescost_multi_provider']:0;		//2 복수구매할인-입점사
	$insdata['salescost_coupon_provider']		= ($params['salescost_coupon_provider'])?$params['salescost_coupon_provider']:0;	//3 쿠폰할인-입점사
	$insdata['salescost_code_provider']			= ($params['salescost_code_provider'])?$params['salescost_code_provider']:0;		//4 코드할인-입점사	
	$insdata['salescost_member_provider']		= ($params['salescost_member_provider'])?$params['salescost_member_provider']:0;	//5 등급할인-입점사
	$insdata['salescost_fblike_provider']		= ($params['salescost_fblike_provider'])?$params['salescost_fblike_provider']:0;	//6 좋아요할인-입점사
	$insdata['salescost_mobile_provider']		= ($params['salescost_mobile_provider'])?$params['salescost_mobile_provider']:0;	//7 모바일할인-입점사
	$insdata['salescost_referer_provider']		= ($params['salescost_referer_provider'])?$params['salescost_referer_provider']:0;	//8 유입경로할인-입점사
	$insdata['salescost_event_provider_rest']	= ($params['salescost_event_provider_rest'])?$params['salescost_event_provider_rest']:0;
	$insdata['salescost_multi_provider_rest']	= ($params['salescost_multi_provider_rest'])?$params['salescost_multi_provider_rest']:0;
	$insdata['salescost_coupon_provider_rest']	= ($params['salescost_coupon_provider_rest'])?$params['salescost_coupon_provider_rest']:0;
	$insdata['salescost_code_provider_rest']	= ($params['salescost_code_provider_rest'])?$params['salescost_code_provider_rest']:0;
	$insdata['salescost_member_provider_rest']	= ($params['salescost_member_provider_rest'])?$params['salescost_member_provider_rest']:0;
	$insdata['salescost_fblike_provider_rest']	= ($params['salescost_fblike_provider_rest'])?$params['salescost_fblike_provider_rest']:0;
	$insdata['salescost_mobile_provider_rest']	= ($params['salescost_mobile_provider_rest'])?$params['salescost_mobile_provider_rest']:0;
	$insdata['salescost_referer_provider_rest']	= ($params['salescost_referer_provider_rest'])?$params['salescost_referer_provider_rest']:0;

	/*
	* 4가지 사용 프로모션과 짜투리
	*/
	$insdata['emoney_sale_unit']				= ($params['emoney_sale_unit'])?$params['emoney_sale_unit']:0;	//9. 적립금할인(개당)
	$insdata['cash_sale_unit']					= ($params['cash_sale_unit'])?$params['cash_sale_unit']:0;		//10 이머니할인(개당)
	$insdata['enuri_sale_unit']					= ($params['enuri_sale_unit'])?$params['enuri_sale_unit']:0;	//11 에누리할인(개당)
	$insdata['npay_point_sale_unit']			= ($params['npay_point_sale_unit'])?$params['npay_point_sale_unit']:0;	//Npay포인트사용(개당)

	$insdata['emoney_sale_rest']				= ($params['emoney_sale_rest'])?$params['emoney_sale_rest']:0;
	$insdata['cash_sale_rest']					= ($params['cash_sale_rest'])?$params['cash_sale_rest']:0;
	$insdata['enuri_sale_rest']					= ($params['enuri_sale_rest'])?$params['enuri_sale_rest']:0;
	$insdata['npay_point_sale_rest']			= ($params['npay_point_sale_rest'])?$params['npay_point_sale_rest']:0;

	$insdata['salescost_emoney']				= ($params['salescost_emoney'])?$params['salescost_emoney']:0;		//1 이벤트할인
	$insdata['salescost_cash']					= ($params['salescost_cash'])?$params['salescost_cash']:0;		//2 복수구매할인
	$insdata['salescost_enuri']					= ($params['salescost_enuri'])?$params['salescost_enuri']:0;		//3 쿠폰할인
	$insdata['salescost_npay_point']			= ($params['salescost_npay_point'])?$params['salescost_npay_point']:0;			//4 코드할인

	$insdata['salescost_emoney_rest']			= ($params['salescost_emoney_rest'])?$params['salescost_emoney_rest']:0;
	$insdata['salescost_cash_rest']				= ($params['salescost_cash_rest'])?$params['salescost_cash_rest']:0;
	$insdata['salescost_enuri_rest']			= ($params['salescost_enuri_rest'])?$params['salescost_enuri_rest']:0;
	$insdata['salescost_npay_point_rest']		= ($params['salescost_npay_point_rest'])?$params['salescost_npay_point_rest']:0;
	
	$insdata['salescost_emoney_provider_rest']	= ($params['salescost_emoney_provider_rest'])?$params['salescost_emoney_provider_rest']:0;
	$insdata['salescost_cash_provider_rest']	= ($params['salescost_cash_provider_rest'])?$params['salescost_cash_provider_rest']:0;
	$insdata['salescost_enuri_provider_rest']	= ($params['salescost_enuri_provider_rest'])?$params['salescost_enuri_provider_rest']:0;
	$insdata['salescost_npay_point_provider_rest']	= ($params['salescost_npay_point_provider_rest'])?$params['salescost_npay_point_provider_rest']:0;
	
	// 3차 환불 개선으로 변수추가 :: 2018-11- lkh
	$insdata['coupon_value_type']	= $params['coupon_value_type'];
	$insdata['coupon_value']		= $params['coupon_value'];
	$insdata['coupon_remain_value']	= $params['coupon_remain_value'];

	// 라이브 방송 정보 추가
	$insdata['bs_seq'] = $params['bs_seq'];
	$insdata['bs_type'] = $params['bs_type'];
	
	//debug_var("accountallmodel->ins_calculate_ck() > loop ".__LINE__);
	//debug_var($params);
	//debug_var($insdata);
	return $insdata;
}

//4만원의 3.135%를 계산하면 1254인데 floor 을 거치면 1253이 된다.원인불명. string 처리하면 이상없음
function acc_string_floor($val,$pg=null){
	if( $pg == 'kakaopay'){//카카오페이(kakaopay) 소수점 첫째자리 반올림
		$return =  round($val);
	}else{
		$return =  floor( (string) $val);
	}
	return $return;
}

/**
* 8가지 할인항목의 할인부담금 계산식
* type : 'event', 'multi', 'member', 'mobile','like', 'coupon', 'code', 'referer'
* sale_unit : 할인별 개당
* sale_provider : 할인별 할인금액 부담 비율
* @
**/
function acc_promotion_sales_unit($type, $sale_unit, $sale_provider, &$promotiondata) {

	$salescost_total = ($sale_unit*$promotiondata['ea'])+$promotiondata[$type.'_sale_rest'];

	$salescost_provider			= 0;
	$salescost_provider_rest	= 0;
	$salescost_type				= 0;
	$salescost_type_rest		= 0;
	// unit 나 rest 가 있을 때 
	// 할인금액이 소소한경우 unit 이 없는 경우가 있음
	if( $sale_unit > 0 || $promotiondata[$type.'_sale_rest']>0) {
		if( $sale_provider > 0) {//입점사 할인금액 부담율이 있으면(입점사상품의 복수구매할인/쿠폰/코드/유입경로할인 설정에 따름)
			// 본사부담금
			$tmp_salescost_type		= round($salescost_total*((100-$sale_provider)/100));
			$salescost_type			= acc_string_floor($tmp_salescost_type / $promotiondata['ea']);
			$salescost_type_rest	= $tmp_salescost_type-($salescost_type * $promotiondata['ea']);
			
			//입점사부담금
			$tmp_salescost_provider		= $salescost_total - $tmp_salescost_type;
			$salescost_provider			= acc_string_floor($tmp_salescost_provider / $promotiondata['ea']);			
			$salescost_provider_rest	= $tmp_salescost_provider-($salescost_provider * $promotiondata['ea']);
			
		}else{//본사부담 100%(본사상품의 복수구매할인/이벤트/등급/좋아요/모바일/마일리지/예치금/에누리/
			// 본사부담 100%의 경우 개당 할인금액을 재계산 할 필요가 없음 by hed
			$salescost_type			= $sale_unit;
			$salescost_type_rest	= $promotiondata[$type.'_sale_rest'];
		}
	}

	$salescost_type_total	= $salescost_type + $salescost_type_rest + $salescost_provider+ $salescost_provider_rest;
	$promotiondata['salescost_'.$type.'_provider']		= $salescost_provider;
	$promotiondata['salescost_'.$type.'_provider_rest']	= $salescost_provider_rest;
	$promotiondata['salescost_'.$type]					= $salescost_type;
	$promotiondata['salescost_'.$type.'_rest']			= $salescost_type_rest;
	$promotiondata['salescost_'.$type.'_total']			= $salescost_type_total;
}

/**
* 쿠폰 남은 비율에 맞춰서 값 변경
* $priceTmp : 기존 금액
* percent : 비율
* @
**/
function acc_coupon_remain_sales_unit($priceTmp, $percent) {
	if(!$priceTmp || !$percent || $percent < 0 || $percent == 100){
		return $priceTmp;
	}
	$price = $priceTmp * $percent / 100;
	return $price;
}

/**
* 8가지 할인항목의 할인부담금 정산페이지 노출
* type : 'event', 'multi', 'member', 'mobile','like', 'coupon', 'code', 'referer'
* @
**/
function acc_promotion_sales_viewr($type, &$acinsdata) {
	$salescost_admin		= $acinsdata['salescost_'.$type]* $acinsdata['ea']+$acinsdata['salescost_'.$type.'_rest'];
	$salescost_provider		= $acinsdata['salescost_'.$type.'_provider']* $acinsdata['ea']+$acinsdata['salescost_'.$type.'_provider_rest'];
	$salescost_type_total	= $salescost_admin + $salescost_provider;
	
	$ac_salescost_admin			= $acinsdata['salescost_'.$type]* $acinsdata['exp_ea']+$acinsdata['salescost_'.$type.'_rest'];
	$ac_salescost_provider		= $acinsdata['salescost_'.$type.'_provider']* $acinsdata['exp_ea']+$acinsdata['salescost_'.$type.'_provider_rest'];
	$ac_salescost_type_total	= $ac_salescost_admin + $ac_salescost_provider;

	$out_salescost_use			= (($acinsdata[$type.'_sale_unit']*$acinsdata['ea'])+$acinsdata[$type.'_sale_rest']);
	$out_ac_salescost_use		= (($acinsdata[$type.'_sale_unit']*$acinsdata['exp_ea'])+$acinsdata[$type.'_sale_rest']);
	
	// 정산수량이 없을 수도 있으므로 나머지를 가산하면 나머지만큼 오차 발생 by hed 2019-06-18
	if(empty($acinsdata['exp_ea']) || $acinsdata['exp_ea'] == '0.00'){
		$ac_salescost_admin = '0';
		$ac_salescost_provider = '0';
		$out_ac_salescost_use = '0';
	}
	
	$acinsdata['out_'.$type.'_use']		= $out_salescost_use;
	$acinsdata['out_ac_'.$type.'_use']	= $out_ac_salescost_use;

	$acinsdata[$type.'_admin']			= $salescost_admin;
	$acinsdata[$type.'_provider']		= $salescost_provider;
	$acinsdata['ac_'.$type.'_admin']	= $ac_salescost_admin;
	$acinsdata['ac_'.$type.'_provider']	= $ac_salescost_provider;
	$acinsdata['salescost_'.$type.'_admin']			= $salescost_admin;
	$acinsdata['salescost_'.$type.'_provider']		= $salescost_provider;
	$acinsdata['salescost_ac_'.$type.'_admin']		= $ac_salescost_admin;
	$acinsdata['salescost_ac_'.$type.'_provider']	= $ac_salescost_provider;
	$acinsdata['salescost_'.$type.'_total']			= $salescost_type_total;
	$acinsdata['out_'.$type.'_sale']	= $salescost_type_total;
	$acinsdata['out_ac_'.$type.'_sale']	= $ac_salescost_type_total;
	//$acinsdata['salescost_'.$type.'_admin_rest']	= $salescost_admin_rest;
	//$acinsdata['salescost_'.$type.'_provider_rest']	= $salescost_provider_rest;
}


/**
* 8가지 할인항목의 할인부담금 정산페이지  본사/정산대상금액의 본사 추출
* type : 'event', 'multi', 'member', 'mobile','like', 'coupon', 'code', 'referer'
* @
**/
function acc_promotion_sales_total(&$acinsdata) {
	## 매출 본사 부담금
	$acinsdata['salescost_admin_promotion']		= $acinsdata['salescost_emoney_admin']
													+ $acinsdata['salescost_cash_admin']
													+ $acinsdata['salescost_enuri_admin'];
	
	$acinsdata['salescost_admin_sales']			= $acinsdata['salescost_event_admin'] 
													+ $acinsdata['salescost_multi_admin'] 
													+ $acinsdata['salescost_member_admin'] 
													+ $acinsdata['salescost_coupon_admin'] 
													+ $acinsdata['salescost_fblike_admin']
													+ $acinsdata['salescost_mobile_admin'] 
													+ $acinsdata['salescost_code_admin'] 
													+ $acinsdata['salescost_referer_admin'];
	$acinsdata['salescost_admin']				= $acinsdata['salescost_admin_promotion'] + $acinsdata['salescost_admin_sales'];

	// 매출 입점사 부담금
	$acinsdata['salescost_provider_promotion']	= $acinsdata['salescost_emoney_provider']
													+ $acinsdata['salescost_cash_provider']
													+ $acinsdata['salescost_enuri_provider'];
	$acinsdata['salescost_provider_sales']		= $acinsdata['salescost_event_provider']
													+ $acinsdata['salescost_multi_provider']
													+ $acinsdata['salescost_member_provider']
													+ $acinsdata['salescost_coupon_provider']
													+ $acinsdata['salescost_fblike_provider']
													+ $acinsdata['salescost_mobile_provider']
													+ $acinsdata['salescost_code_provider']
													+ $acinsdata['salescost_referer_provider'];
	$acinsdata['salescost_provider']			= $acinsdata['salescost_provider_promotion'] + $acinsdata['salescost_provider_sales'];
	$acinsdata['acc_promotion_total']			= ($acinsdata['salescost_admin_promotion']) + ($acinsdata['salescost_provider_promotion']);
	$acinsdata['acc_sale_total']				= $acinsdata['salescost_admin_sales'] + $acinsdata['salescost_provider_sales'];
	$acinsdata['salescost_total']				= $acinsdata['acc_promotion_total'] + $acinsdata['acc_sale_total'];
	
	## 정산 본사 부담금
	$acinsdata['ac_salescost_admin_promotion']	= $acinsdata['salescost_ac_emoney_admin']
													+ $acinsdata['salescost_ac_cash_admin']
													+ $acinsdata['salescost_ac_enuri_admin'];
	
	$acinsdata['ac_salescost_admin_sales']		= $acinsdata['salescost_ac_event_admin'] 
													+ $acinsdata['salescost_ac_multi_admin'] 
													+ $acinsdata['salescost_ac_member_admin'] 
													+ $acinsdata['salescost_ac_coupon_admin'] 
													+ $acinsdata['salescost_ac_fblike_admin']
													+ $acinsdata['salescost_ac_mobile_admin'] 
													+ $acinsdata['salescost_ac_code_admin'] 
													+ $acinsdata['salescost_ac_referer_admin'];
	$acinsdata['ac_salescost_admin']			= $acinsdata['ac_salescost_admin_promotion'] + $acinsdata['ac_salescost_admin_sales'];

	// 정산 입점사 부담금
	$acinsdata['ac_salescost_provider_promotion']= $acinsdata['salescost_ac_emoney_provider']
													+ $acinsdata['salescost_ac_cash_provider']
													+ $acinsdata['salescost_ac_enuri_provider'];
	$acinsdata['ac_salescost_provider_sales']	= $acinsdata['salescost_ac_event_provider'] 
													+ $acinsdata['salescost_ac_multi_provider'] 
													+ $acinsdata['salescost_ac_member_provider'] 
													+ $acinsdata['salescost_ac_coupon_provider']
													+ $acinsdata['salescost_ac_fblike_provider']
													+ $acinsdata['salescost_ac_mobile_provider']
													+ $acinsdata['salescost_ac_code_provider']
													+ $acinsdata['salescost_ac_referer_provider'];
	$acinsdata['ac_salescost_provider']			= $acinsdata['ac_salescost_provider_promotion'] + $acinsdata['ac_salescost_provider_sales'];
	$acinsdata['acc_ac_promotion_total']		= ($acinsdata['salescost_admin_promotion']) + ($acinsdata['ac_salescost_provider_promotion']);
	$acinsdata['acc_ac_sale_total']				= $acinsdata['ac_salescost_admin_sales'] + $acinsdata['ac_salescost_provider_sales'];
	$acinsdata['ac_salescost_total']			= $acinsdata['acc_ac_promotion_total'] + $acinsdata['acc_ac_sale_total'];
}

//네이버페이 결제방식 구분
function acc_npay_referer($data_order) {
	$CI =& get_instance(); 
	if($data_order['payment_type']) {
	  if(!preg_match('/네이버결제/',$data_order['payment_type'])){
	   $data_order['order_referer_npay']	= 'npg';
	  }else{
	   $data_order['order_referer_npay']	= 'npay';
	  }
	}else{
	  $npay_log = $CI->ordermodel->get_log($data_order['order_seq'],'pay',array("add_info"=>"npay"));
	  if(!preg_match('/간편결제/',$npay_log[0]['title']) && $npay_log[0]['title']){
		 $data_order['order_referer_npay'] = "npg";
	  }
	  if(!$data_order['order_referer_npay']) $data_order['order_referer_npay'] = 'npay';
	}
	return $data_order['order_referer_npay'];
}


/**
* 정산대상 구분
* 매출 : 본사/위탁상품 주문은 미정산처리 대상으로 매출까지만 처리(미정산-당월)
* 정산 : 입점사상품/입점사반품배송비 주무은 정산대상
@
**/
function get_account_target($acinsdata, $optdata) {
	$account_target = 'sales';//미정산대상
	
	if( $optdata['order_type'] == 'shipping' ) {//배송비
		// 본사 또는 위탁배송 체크
		if($optdata['shipping_provider_seq'] > 1 && $optdata['provider_seq'] > 1) {//입점사상품 > 입점사배송일때
			$account_target = 'calculate';//정산대상
		}
	}else{//옵션
		if($optdata['provider_seq'] > 1) {//입점사상품
			$account_target = 'calculate';//정산대상
		}
	}
	return $account_target;
}

function acc_payment($payment) {
	$CI =& get_instance(); 
	if( $payment == 'point' ){
		$paymentstr = "N포인트";
	}else{
		$paymentstr = $CI->arr_payment[$payment];
	}
	return $paymentstr;
}

/**
* PG수수료방식과 상품수수료(퍼스트몰) 구문 분리
@
**/
function acc_seller_stats_query_str() {
	$CI =& get_instance(); 
	//PG수수료방식
	if($CI->accountallmodel->account_fee_ar['pg']){// (IFNULL(caltb.salescost_event,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_rest,0))
		$CalSelectSql	= "SELECT 
							p.provider_seq as provider_seq, p.provider_id as provider_id, p.provider_name as provider_name, p.calcu_count as calcu_count,
							sum( 
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
										(caltb.exp_ea)
									,0)
								,0) 
							)
								AS refund_sum_ea,
							sum( 
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
									(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
									,0)
								,0)
							) 
								AS refund_sum_price,
							sum( 
								IFNULL(
									if( (caltb.account_type in ('refund','rollback','after_refund')), 
									( 
									
									 (IFNULL(caltb.salescost_code,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_rest,0))
									 +
									 (IFNULL(caltb.salescost_member,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_rest,0))
									)
									,0)
								,0)
							) 
								AS refund_sum_sales_admin_total,
							sum( 
								IFNULL(
									if( ( caltb.account_type in ('refund','rollback','after_refund')), 
									( (IFNULL(caltb.npay_point_sale_unit,0)*IFNULL(caltb.exp_ea,0))+IFNULL(caltb.npay_point_sale_rest,0) )
									,0)
								,0)
							) 
								AS refund_sum_all_npay_point,
							sum( 
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.api_pg_commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.api_pg_commission_price_rest,0))
									,0)
								,0)
							)
								AS refund_sum_commission_price,
								
							sum( 
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.api_pg_sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.api_pg_sales_feeprice_rest,0))
									,0)
								,0)
							)
								AS refund_sum_feeprice,
							sum( 
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
										(caltb.exp_ea)
									,0)
								,0) 
							)
								AS sum_ea,
							sum( 
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund') AND (!(caltb.order_referer ='npay' and caltb.payment ='point')) , 
									(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
									,0)
								,0)
							) 
								AS sum_price,
							sum( 
								IFNULL(
									if( ( caltb.account_type not in ('refund','rollback','after_refund') and caltb.order_referer ='npay' and caltb.payment ='point' ), 
									(IFNULL(caltb.price,0))
									,0)
								,0)
							) 
								AS sum_npay_point,
							sum( 
								IFNULL(
									if( ( caltb.account_type not in ('refund','rollback','after_refund') and caltb.order_referer ='npg'), 
									( (IFNULL(caltb.npay_point_sale_unit,0)*IFNULL(caltb.exp_ea,0))+IFNULL(caltb.npay_point_sale_rest,0) )
									,0)
								,0)
							) 
								AS sum_all_npay_point,
							sum( 
								IFNULL(
									if( (caltb.account_type not in ('refund','rollback','after_refund')), 
									(  
									 (IFNULL(caltb.salescost_code,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_rest,0))
									 +
									 (IFNULL(caltb.salescost_member,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_rest,0))
									)
									,0)
								,0)
							) 
								AS sum_sales_admin_total,
							sum( 
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.api_pg_commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.api_pg_commission_price_rest,0))
									,0)
								,0)
							)
								AS sum_commission_price,
							sum( 
								IFNULL(
									if( caltb.account_type not  in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.api_pg_sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.api_pg_sales_feeprice_rest,0))
									,0)
								,0)
							)
								AS sum_feeprice
						";
	}else{
		$CalSelectSql	= "SELECT 
						IFNULL(
							if( caltb.account_type in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
								(caltb.exp_ea)
							,0)
						,0) 
						AS refund_ea,
						IFNULL(
							if( caltb.account_type in ('refund','rollback','after_refund'), 
							(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
							,0)
						,0)
						AS refund_price,
						IFNULL(
							if( (caltb.account_type in ('refund','rollback','after_refund')), 
							( 
							 (IFNULL(caltb.salescost_event,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_rest,0))
							 +
							 (IFNULL(caltb.salescost_multi,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_rest,0))
							 +
							 (IFNULL(caltb.salescost_coupon,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_coupon_rest,0))
							 +
							 (IFNULL(caltb.salescost_code,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_rest,0))
							 +
							 (IFNULL(caltb.salescost_member,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_rest,0))
							 +
							 (IFNULL(caltb.salescost_fblike,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_rest,0))
							 +
							 (IFNULL(caltb.salescost_mobile,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_rest,0))
							 +
							 (IFNULL(caltb.salescost_referer,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_rest,0))
							 +
							 (IFNULL(caltb.salescost_enuri,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_rest,0))
							 +
							 (IFNULL(caltb.salescost_emoney,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_rest,0))
							 +
							 (IFNULL(caltb.salescost_cash,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_rest,0))
							)
							,0)
						,0) 
						AS refund_sales_admin_total,
						IFNULL(
							if( (caltb.account_type in ('refund','rollback','after_refund')), 
							( 
							 (IFNULL(caltb.salescost_event_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_multi_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_coupon_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_coupon_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_code_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_member_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_fblike_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_mobile_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_referer_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_enuri_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_emoney_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_cash_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_provider_rest,0))
							)
							,0)
						,0)
						AS refund_sales_provider_total,
						IFNULL(
							if( caltb.account_type in ('refund','rollback','after_refund'), 
								(
									case caltb.order_referer
										when 'storefarm' then IFNULL(((caltb.api_pg_sale_price - caltb.api_pg_support_price) + caltb.api_pg_support_price),0)
										when 'open11st' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
										when 'coupang' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
									else 0
									end
								)
							,0)
						,0)
						AS refund_pg_sale_price,
						IFNULL(
							if( caltb.account_type in ('refund','rollback','after_refund'), 
								(IFNULL(caltb.cash_sale_unit,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.cash_sale_rest,0))
							,0)
						,0)
						AS refund_cash_use,
						IFNULL(
							if( caltb.account_type in ('refund','rollback','after_refund'), 
								(IFNULL(caltb.commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.commission_price_rest,0))
							,0)
						,0)
						AS refund_commission_price,
						IFNULL(
							if( caltb.account_type in ('refund','rollback','after_refund'), 
								ROUND(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
							,0)
						,0)
						AS refund_feeprice,
						IFNULL(
							if( caltb.account_type not in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
								(caltb.exp_ea)
							,0)
						,0)
						AS ea,
						IFNULL(
							if( caltb.account_type not in ('refund','rollback','after_refund'), 
							(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
							,0)
						,0)
						AS price,
						IFNULL(
							if( (caltb.account_type not in ('refund','rollback','after_refund')), 
							( 
							 (IFNULL(caltb.salescost_event,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_rest,0))
							 +
							 (IFNULL(caltb.salescost_multi,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_rest,0))
							 +
							 (IFNULL(caltb.salescost_coupon,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_coupon_rest,0))
							 +
							 (IFNULL(caltb.salescost_code,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_rest,0))
							 +
							 (IFNULL(caltb.salescost_member,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_rest,0))
							 +
							 (IFNULL(caltb.salescost_fblike,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_rest,0))
							 +
							 (IFNULL(caltb.salescost_mobile,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_rest,0))
							 +
							 (IFNULL(caltb.salescost_referer,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_rest,0))
							 +
							 (IFNULL(caltb.salescost_enuri,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_rest,0))
							 +
							 (IFNULL(caltb.salescost_emoney,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_rest,0))
							 +
							 (IFNULL(caltb.salescost_cash,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_rest,0))
							)
							,0)
						,0)
						AS sales_admin_total,
						IFNULL(
							if( (caltb.account_type not in ('refund','rollback','after_refund')), 
							( 
							 (IFNULL(caltb.salescost_event_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_multi_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_coupon_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_coupon_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_code_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_member_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_fblike_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_mobile_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_referer_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_enuri_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_emoney_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_provider_rest,0))
							 +
							 (IFNULL(caltb.salescost_cash_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_provider_rest,0))
							)
							,0)
						,0)
						AS sales_provider_total,
						IFNULL(
							if( caltb.account_type not in ('refund','rollback','after_refund'), 
								(
									case caltb.order_referer
										when 'storefarm' then IFNULL(((caltb.api_pg_sale_price - caltb.api_pg_support_price) + caltb.api_pg_support_price),0)
										when 'open11st' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
										when 'coupang' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
									else 0
									end
								)
							,0)
						,0)
						AS pg_sale_price,
						IFNULL(
							if( caltb.account_type not in ('refund','rollback','after_refund'), 
								(IFNULL(caltb.cash_sale_unit,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.cash_sale_rest,0))
							,0)
						,0)
						AS cash_use,
						IFNULL(
							if( caltb.account_type not in ('refund','rollback','after_refund'), 
								(IFNULL(caltb.commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.commission_price_rest,0))
							,0)
						,0)
						AS commission_price,
						IFNULL(
							if( caltb.account_type not  in ('refund','rollback','after_refund'), 
								(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
							,0)
						,0)
						AS feeprice,
						commission_type,
						confirm_date
						";
/* 구버전
		$CalSelectSql	= "SELECT 
						p.provider_seq as provider_seq, p.provider_id as provider_id, p.provider_name as provider_name, p.calcu_count as calcu_count,
						sum( 
							IFNULL(
								if( caltb.account_type in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
									(caltb.exp_ea)
								,0)
							,0) 
						)
							AS refund_sum_ea,
						sum( 
							IFNULL(
								if( caltb.account_type in ('refund','rollback','after_refund'), 
								(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
								,0)
							,0)
						) 
							AS refund_sum_price,
						sum( 
							IFNULL(
								if( caltb.account_type in ('refund','rollback','after_refund'), 
									(IFNULL(caltb.commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.commission_price_rest,0))
								,0)
							,0)
						)
							AS refund_sum_commission_price,
							
						sum( 
							IFNULL(
								if( caltb.account_type in ('refund','rollback','after_refund'), 
									(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
								,0)
							,0)
						)
							AS refund_sum_feeprice,
						sum( 
							IFNULL(
								if( caltb.account_type not in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
									(caltb.exp_ea)
								,0)
							,0) 
						)
							AS sum_ea,
						sum( 
							IFNULL(
								if( caltb.account_type not in ('refund','rollback','after_refund'), 
								(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
								,0)
							,0)
						) 
							AS sum_price,
						sum( 
							IFNULL(
								if( caltb.account_type not in ('refund','rollback','after_refund'), 
									(IFNULL(caltb.commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.commission_price_rest,0))
								,0)
							,0)
						)
							AS sum_commission_price,
						sum( 
							IFNULL(
								if( caltb.account_type not  in ('refund','rollback','after_refund'), 
									(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
								,0)
							,0)
						)
							AS sum_feeprice
						";
*/
	}
return $CalSelectSql;
}

//정산 마이그레이션일자 정보 가져오기
function getAccountSetting($code=''){

	$accountAllMiDate	= config_load("accountall_setting",$code);
	$return				= array();
	$return				= $accountAllMiDate;

	if($accountAllMiDate['accountall_migration_date']){
		$accountall_migration_date		= explode("-",$accountAllMiDate['accountall_migration_date']);
		// 익월 계산 시 01일 기준으로 해야 정확한 익월을 추출할 수 있음
		$accountall_migration_date[2]	= "01";	
		$return['migration_date']		= $accountAllMiDate['accountall_migration_date'];
		$return['migrationYear']		= date("Y",mktime(0,0,0,$accountall_migration_date[1],$accountall_migration_date[2],$accountall_migration_date[0]));
		$return['migrationMonth']		= date("m",strtotime("+1 month",mktime(0,0,0,$accountall_migration_date[1],$accountall_migration_date[2],$accountall_migration_date[0])));
		$return['migrationCheckDate']	= date("Y-m",strtotime("+1 month",mktime(0,0,0,$accountall_migration_date[1],$accountall_migration_date[2],$accountall_migration_date[0])));
	}

	return $return;
}

function getConfirmDay($confirm_day=''){

	if($confirm_day == 8){
		$confirm_name = "익월 7일";
	}elseif($confirm_day == 11){
		$confirm_name = "익월 10일";
	}else{
		$confirm_name = "월말";
	}
	return $confirm_name;
}

function getAccountAllDate($params){

	$accountAllDate		= array();
	$accountAllYearMon	= $params['s_year']."-".$params['s_month'];

	// 정산주기 1회
	$begin								= new DateTime($accountAllYearMon.'-01 00:00:00');
	$accountAllDate['cal1'][0]['start']	= $begin->format('Y-m-d H:i:s');
	$accountAllDate['cal1'][0]['end']	= $begin->format('Y-m-t 23:59:59');

	// 정산주기 2회
	$begin								= new DateTime($accountAllYearMon.'-01 00:00:00');
	$accountAllDate['cal2'][0]['start']	= $begin->format('Y-m-d H:i:s');
	$begin->modify('+14 day');
	$accountAllDate['cal2'][0]['end']	= $begin->format('Y-m-d 23:59:59');
	$begin->modify('+1 day');
	$accountAllDate['cal2'][1]['start']	= $begin->format('Y-m-d H:i:s');
	$accountAllDate['cal2'][1]['end']	= $begin->format('Y-m-t 23:59:59');

	// 정산주기 4회
	$begin								= new DateTime($accountAllYearMon.'-01 00:00:00');
	$accountAllDate['cal4'][0]['start']	= $begin->format('Y-m-d H:i:s');
	$begin->modify('+6 day');
	$accountAllDate['cal4'][0]['end']	= $begin->format('Y-m-d 23:59:59');
	$begin->modify('+1 day');
	$accountAllDate['cal4'][1]['start']	= $begin->format('Y-m-d H:i:s');
	$begin->modify('+6 day');
	$accountAllDate['cal4'][1]['end']	= $begin->format('Y-m-d 23:59:59');
	$begin->modify('+1 day');
	$accountAllDate['cal4'][2]['start']	= $begin->format('Y-m-d H:i:s');
	$begin->modify('+6 day');
	$accountAllDate['cal4'][2]['end']	= $begin->format('Y-m-d 23:59:59');
	$begin->modify('+1 day');
	$accountAllDate['cal4'][3]['start']	= $begin->format('Y-m-d H:i:s');
	$accountAllDate['cal4'][3]['end']	= $begin->format('Y-m-t 23:59:59');

	return $accountAllDate;
}
//정산데이타 재정의리스트
function accountalllist($pagetype='list',$acinsdata, &$loop, &$tot, &$carryoverloop, &$carryovertot, &$overdrawloop, &$overdrawtot, &$extend=array()) {
	// 확장 변수 초기화
	$caller = (empty($extend['caller']))?'admin':$extend['caller'];
	$mode = (empty($extend['mode_accountalllist']))?'all':$extend['mode_accountalllist'];
	$get_order_referer = (empty($extend['get_order_referer']))?'':$extend['get_order_referer'];
	
	$CI =& get_instance();
	
	if(empty($CI->accountallmodel)){	$CI->load->model('accountallmodel');	}
	if(empty($CI->ordermodel)){			$CI->load->model('ordermodel');			}

	$CI->db->queries = array();
	$CI->db->query_times = array();

	/**
	* 입점사정보 한번만 가져오기
	**/
	if(empty($CI->data_provider[$acinsdata['provider_seq']])) {
		$CI->load->model('providermodel');
		// 본사를 포함한 모든 입점사 정보를 가져온다.
		$privider_params					= array();
		$privider_params['include_base']	= '1';
		$providerlist = $CI->providermodel->provider_list_sort($privider_params);
		foreach($providerlist as $data) {

			$provider = array();
			$provider['provider_seq']	= $data['provider_seq'];
			$provider['provider_id']	= $data['provider_id'];
			$provider['provider_name']	= $data['provider_name'];
			$provider['calcu_count']	= $data['calcu_count'] ? $data['calcu_count'] : '1' ;
			
			//입점사 정산주기
			$nowPeriodArr	= $CI->accountallmodel->get_account_provider_period('pre',$provider['provider_seq']);
			if($nowPeriodArr['accountall_period_count']){
				$provider['calcu_count'] = $nowPeriodArr['accountall_period_count'];
			}else{
				$provider['calcu_count'] = "0";
			}
			
			$CI->data_provider[$data['provider_seq']] = $provider;
		}
	}

	/**
	* 입점사정보 한번만 가져오기
	**/
	if($acinsdata['provider_seq']) {
		$data_provider = $CI->data_provider[$acinsdata['provider_seq']];
		$acinsdata['provider_id']		= $data_provider['provider_id'];
		$acinsdata['out_provider_name'] = $data_provider['provider_name'];
		if(!$data_provider['calcu_count']) $data_provider['calcu_count'] = 1;
		$acinsdata['calcu_count'] 		= $data_provider['calcu_count'];
	}

	$acinsdata['out_deposit_date']			= substr($acinsdata['deposit_date'],2,8);
	if($acinsdata['account_type'] == "return" || $acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback" || $acinsdata['account_type'] == "after_refund" || $acinsdata['account_type'] == "deductible") {
		$acinsdata['out_confirm_date']		= substr($acinsdata['regist_date'],2,8);
	}elseif($acinsdata['exp_ea'] > 0 && $acinsdata['ac_ea'] == 0){	// 정산수량(exp_ea) 가 0인데 confirm_date가 있다는 내역은 취소된 주문, 그 이외의 내역은 refund,rollback, after_refund에서 처리됨 by hed
		if( substr(str_replace("-","",$acinsdata['confirm_date']),0,8) != '00000000' ) $acinsdata['out_confirm_date']		= substr($acinsdata['confirm_date'],2,8);
	}
	// 정산 대상여부 확인 :: 2018-05-28 lkh
	if(
		empty($acinsdata['out_ac_acc_status'])	// 정산대상여부를 선언하지 않았고 아래 조건 중 최소 하나를 만족해야함
		&& (
			($acinsdata['provider_seq'] == '1')	// 본사상품이거나
			|| (								// 반품이나 되돌리기 주문이거나
				$acinsdata['account_type'] == 'refund' || $acinsdata['account_type'] == 'rollback'
			) 
			|| (								// 아직 정산완료(ac_ea==0)이 완료됬으나 정산수량(exp_ea)이 없는 경우
				$acinsdata['account_type'] == 'order' && $acinsdata['exp_ea'] == 0 && $acinsdata['ac_ea'] == 0
			) 
			|| (								// 맞교환에 의한 주문일 경우
				$acinsdata['account_type'] == 'exchange'
			) 
		)
	){
		$acinsdata['out_ac_acc_status']			= "no_acc";	//정산대상여부
	}
	
	// 정산 대상이 아닐 경우 정산 수량 리셋, 본사 위탁 배송의 롤백의 경우 정상적으로 정산수량을 지정하지 못 하며 정산 대상이 아닐 경우 강제로 정산 수량을 초기화 by hed.
	if($acinsdata['out_ac_acc_status'] == "no_acc"){
		$acinsdata['exp_ea'] = 0;
		// total_commission_price와 total_feeprice 초기 개발 시 주문과 동시에 해당 값을 입력하게 되어있어 신정산->신정산개선으로 기능이 변하면 정산여부와 상관 없이 금액이 집계되어 정산대상이 아닐 경우 금액 초기화
		$acinsdata['total_commission_price'] = 0;
		$acinsdata['total_feeprice'] = 0;
	}
	
	// 정산수량이 0개가 될 수 있으므로 강제 변환 로직 제거 by hed
	// // 전체환불시 계산이 꼬여서 계산되도록 기본 주문 갯수와 동일하게 처리 :: 2018-05-28 lkh
	// if($acinsdata['exp_ea'] < 1){
	// 	$acinsdata['exp_ea'] = $acinsdata['ea'];
	// }
	$acinsdata['out_order_goods_name']		= htmlspecialchars($acinsdata['order_goods_name']);
	// 반품배송비 수량 - 노출되도록 수정 :: 2018-07-09 lkh
	// if(  $acinsdata['account_type']!="return" && $acinsdata['order_type']=="shipping" ) {
	if( $acinsdata['order_type']=="shipping" ) {
		$acinsdata['out_ea']					= ' - ';
		$acinsdata['out_exp_ea']				= ' - ';
	}else{
		$acinsdata['out_ea']					= ($acinsdata['ea']);
		$acinsdata['out_exp_ea']				= ($acinsdata['exp_ea']);
	}

	if(
		$CI->accountallmodel->order_referer_om_ar[$acinsdata['order_referer']] || 
		$acinsdata['order_referer']=='npay' || 
		// 톡구매 주문서 아이디 정산에 노출
		$acinsdata['order_referer']=='talkbuy' || 
		strpos($acinsdata['order_referer'], 'API') > -1
	) {
		$acinsdata['out_pg_ordernum']			= ($acinsdata['linkage_mall_order_id'])?$acinsdata['linkage_mall_order_id']:$acinsdata['linkage_order_id'];
	}else{
		$acinsdata['out_pg_ordernum']			= $acinsdata['pg_ordernum'];
	}
	$acinsdata['out_order_referer_viewer']	= account_order_referer_title($acinsdata['order_referer'], $acinsdata);
	$acinsdata['out_payment']				= acc_payment($acinsdata['payment']);
	if($acinsdata['payment'] == "bank" && ($mode == 'all' || $mode == 'excel')){
		$bankAccountTmp = $CI->ordermodel->get_order_bank_account($acinsdata['order_seq']);
		$bankAccount = explode(' ', $bankAccountTmp['bank_account']);
		$acinsdata['out_payment'] = $bankAccount[0];
	}
	if ($acinsdata['payment'] === 'point' && $acinsdata['pg'] === 'talkbuy') {
		$acinsdata['out_payment'] = '카카오 머니';
	}

	//Npay 상품 할인액(Npay부담) # npay 쿠폰할인(네이버페이 부담=상품별 할인액-판매자 부담 할인액)
	$acinsdata['out_npay_sale_npay']	= ($acinsdata['npay_sale_npay']);
	//Npay 상품 할인액(판매자부담) : # npay 할인(배송비 할인 + 상품별 할인 - 네이버페이 부담 상품할인액)
	$acinsdata['out_npay_sale_seller']	= ($acinsdata['npay_sale_seller']);
	/* 제휴사 할인 계산 :: 2018-06-01 lkh
	 * 네이스마트스토어 {결제금액 = 주문금액 + 배송비 - 판매자할인 - (제휴사할인-판매자할인) }
	 * 11번가 {결제금액 = 주문금액 + 배송비 - 판매자할인 - 제휴사할인}
	 * 쿠팡 {결제금액 = 주문금액 + 배송비 - 제휴사할인}
	 */
	if($acinsdata['order_referer']=='storefarm'){
		$acinsdata['api_pg_sale_price'] = (($acinsdata['api_pg_sale_price'] - $acinsdata['api_pg_support_price']) + $acinsdata['api_pg_support_price']);
	}elseif($acinsdata['order_referer']=='open11st'){
		$acinsdata['api_pg_sale_price'] = ($acinsdata['api_pg_sale_price'] + $acinsdata['api_pg_support_price']);
	}elseif($acinsdata['order_referer']=='coupang'){
		$acinsdata['api_pg_sale_price'] = ($acinsdata['api_pg_sale_price'] + $acinsdata['api_pg_support_price']);
	}else{
		$acinsdata['api_pg_sale_price'] = 0;
	}
	$acinsdata['api_pg_support_price'] = 0;

	//결제수수료전용으로 npay 포인트 결제금액 체크
	if($CI->accountallmodel->account_fee_ar['pg']){
		if( ($acinsdata['order_referer']=='npay' && $acinsdata['payment']=='point') ) {//||  ($acinsdata['order_referer']=='naverstorefarm' && $acinsdata['status'] != 'overdraw')
			$acinsdata['out_supply_price']			= ($acinsdata['supply_price']);		//매입가
			$acinsdata['out_consumer_price']		= ($acinsdata['consumer_price']);		//실제정가
			$acinsdata['out_original_price']		= ($acinsdata['original_price']);		//할인전정가*수량
			$acinsdata['out_price']					= ($acinsdata['price']);				//할인가(기본+이벤트 차감)*수량

			$acinsdata['out_org_price']				= ($acinsdata['org_price']);			//할인가(기본 차감)*수량
			$acinsdata['out_sales_basic']			= ($acinsdata['sales_basic']);			//기본할인*수량
			$acinsdata['out_sales_price']			= ($acinsdata['sales_price']);							//할인가(8가지할인항목 차감)*수량
		}else{
			//판매수량
			$acinsdata['out_supply_price']			= ($acinsdata['supply_price']*$acinsdata['ea']);		//매입가
			$acinsdata['out_consumer_price']		= ($acinsdata['consumer_price']*$acinsdata['ea']);		//실제정가
			$acinsdata['out_original_price']		= ($acinsdata['original_price']*$acinsdata['ea']);		//할인전정가*수량
			$acinsdata['out_price']					= ($acinsdata['price']*$acinsdata['ea']);				//할인가(기본+이벤트 차감)*수량
			$acinsdata['out_org_price']				= ($acinsdata['org_price']*$acinsdata['ea']);			//할인가(기본 차감)*수량
			$acinsdata['out_sales_basic']			= ($acinsdata['sales_basic']*$acinsdata['ea']);			//기본할인*수량
			$acinsdata['out_sales_price']			= ($acinsdata['sales_price']);							//할인가(8가지할인항목 차감)*수량

			//정산수량
			$acinsdata['out_ac_supply_price']		= ($acinsdata['supply_price']*$acinsdata['exp_ea']);		//매입가
			$acinsdata['out_ac_consumer_price']		= ($acinsdata['consumer_price']*$acinsdata['exp_ea']);		//실제정가
			$acinsdata['out_ac_original_price']		= ($acinsdata['original_price']*$acinsdata['exp_ea']);		//할인전정가*수량
			$acinsdata['out_ac_price']				= ($acinsdata['price']*$acinsdata['exp_ea']);				//할인가(기본+이벤트 차감)*수량
			$acinsdata['out_ac_org_price']			= ($acinsdata['org_price']*$acinsdata['exp_ea']);			//할인가(기본 차감)*수량
			$acinsdata['out_ac_sales_basic']		= ($acinsdata['sales_basic']*$acinsdata['exp_ea']);			//기본할인*수량
		}
	}else{
		//판매수량별
		$acinsdata['out_supply_price']			= ($acinsdata['supply_price']*$acinsdata['ea']);		//매입가
		$acinsdata['out_consumer_price']		= ($acinsdata['consumer_price']*$acinsdata['ea']);		//실제정가
		$acinsdata['out_original_price']		= ($acinsdata['original_price']*$acinsdata['ea']);		//할인전정가*수량
		$acinsdata['out_price']					= ($acinsdata['price']*$acinsdata['ea']);				//할인가(기본+이벤트 차감)*수량
		$acinsdata['out_org_price']				= ($acinsdata['org_price']*$acinsdata['ea']);			//할인가(기본 차감)*수량
		$acinsdata['out_sales_basic']			= ($acinsdata['sales_basic']*$acinsdata['ea']);			//기본할인*수량
		$acinsdata['out_sales_price']			= ($acinsdata['sales_price']);							//할인가(8가지할인항목 차감)*수량

		//정산수량별
		$acinsdata['out_ac_supply_price']		= ($acinsdata['supply_price']*$acinsdata['exp_ea']);		//매입가
		$acinsdata['out_ac_consumer_price']		= ($acinsdata['consumer_price']*$acinsdata['exp_ea']);		//실제정가
		$acinsdata['out_ac_original_price']		= ($acinsdata['original_price']*$acinsdata['exp_ea']);		//할인전정가*수량
		$acinsdata['out_ac_price']				= ($acinsdata['price']*$acinsdata['exp_ea']);				//할인가(기본+이벤트 차감)*수량
		$acinsdata['out_ac_org_price']			= ($acinsdata['org_price']*$acinsdata['exp_ea']);			//할인가(기본 차감)*수량
		$acinsdata['out_ac_sales_basic']		= ($acinsdata['sales_basic']*$acinsdata['exp_ea']);			//기본할인*수량
	}

	/**
	할인항목별 처리
	**/
	acc_promotion_sales_viewr('multi',	$acinsdata);
	acc_promotion_sales_viewr('event',	$acinsdata);
	acc_promotion_sales_viewr('member',	$acinsdata);
	acc_promotion_sales_viewr('coupon',	$acinsdata);
	acc_promotion_sales_viewr('fblike',	$acinsdata);
	acc_promotion_sales_viewr('mobile',	$acinsdata);
	acc_promotion_sales_viewr('code',	$acinsdata);
	acc_promotion_sales_viewr('referer',$acinsdata);

	acc_promotion_sales_viewr('emoney',	$acinsdata);
	acc_promotion_sales_viewr('cash',	$acinsdata);
	acc_promotion_sales_viewr('enuri',	$acinsdata);
	acc_promotion_sales_viewr('npay_point',	$acinsdata);

	/**
	** 할인부담금 시작
	**/

	//판매금액의 본사/정산대상금액의 본사 추출
	acc_promotion_sales_total($acinsdata);

	//쿠폰-할인(본사) out_ac_salescost_admin
	$acinsdata['out_salescost_admin']		= ($acinsdata['salescost_admin_promotion'] + $acinsdata['salescost_admin_sales'] - $acinsdata['out_cash_use']);
	$acinsdata['out_salescost_provider']	= ($acinsdata['salescost_provider_promotion'] + $acinsdata['salescost_provider_sales']);
	$acinsdata['out_salescost_total']		= ($acinsdata['out_salescost_admin'] + $acinsdata['out_salescost_provider']);
	$acinsdata['out_ac_salescost_admin']	= ($acinsdata['ac_salescost_admin_promotion'] + $acinsdata['ac_salescost_admin_sales'] - $acinsdata['out_ac_cash_use']); //정산대상금액(A)>본사
	$acinsdata['out_ac_salescost_provider']	= ($acinsdata['ac_salescost_provider_promotion'] + $acinsdata['ac_salescost_provider_sales']);//정산대상금액(A)>입점사
	$acinsdata['out_ac_salescost_total']	= ($acinsdata['out_ac_salescost_admin'] + $acinsdata['out_ac_salescost_provider']); //정산대상금액(A)>할인전체

	if($CI->accountallmodel->account_fee_ar['pg']){//결제수수료전용으로 엑셀업로드 정산금액으로 적용
		//쿠폰(마일리지)할인>제휴사 -> Npay Point /11번가 제휴사할인
		if( $acinsdata['status'] == 'overdraw' ) {//차월일때에는 결제금액 그대로
			$acinsdata['out_pg_sale_price'] = $acinsdata['pg_sale_price'] = $acinsdata['api_pg_sale_price'];
			 if( $acinsdata['order_referer']=='open11st' ){
				$acinsdata['out_price']	-= $acinsdata['out_pg_sale_price'];//결제금액 11번가 차감
			 }
		}else{
			 if( $acinsdata['order_referer']=='npg' ){
				 $npay_point_sale		= ($acinsdata['out_npay_point_use'] + $acinsdata['api_pg_sale_price']);
				 $ac_npay_point_sale	= ($acinsdata['out_ac_npay_point_use'] + $acinsdata['api_pg_sale_price']);
				$acinsdata['out_price']	-= $npay_point_sale;//결제금액 네이버포인트 차감
				//debug_var($acinsdata['out_npay_point_use']."/".$acinsdata['out_price']);
			 }elseif( $acinsdata['order_referer']=='open11st' ){
				$acinsdata['out_price']	-= $acinsdata['out_pg_sale_price'];//결제금액 11번가 차감
			 }
		}
		if( $acinsdata['order_referer'] == 'npg' || $acinsdata['order_referer'] == 'pg' ) {
			$acinsdata['real_sale_price']	= ($acinsdata['out_price'])-($acinsdata['salescost_total']+$npay_point_sale);//실결제금액1
			$acinsdata['out_sale_price']	= ($acinsdata['real_sale_price'])+($acinsdata['out_cash_use']);//결제금액(A)
			$acinsdata['out_sale_price']	-= ($npay_point_sale);//결제금액 네이버포인트 차감
		}else{
			$acinsdata['out_sale_price']	= ($acinsdata['out_price'])-($acinsdata['salescost_admin']);//결제금액
			$acinsdata['out_ac_sale_price']	= ($acinsdata['out_ac_price'])-($acinsdata['ac_salescost_admin']);//결제금액 ac_salescost_total
		}

		//엑셀정산확정시 노출되도록 개선 중요 별5개~~(퍼스트몰에서는 제외)
		if( $acinsdata['status'] != 'overdraw' && ( !($acinsdata['order_referer'] == 'shop' && $acinsdata['payment'] == 'bank') )  ) {

			if( $acinsdata['order_referer'] = 'npg' &&  $acinsdata['account_type'] == "refund" ) {//npg 환불시 이미 차감된 금액으로
				$acinsdata['out_sale_price']		= $acinsdata['api_pg_price'] - ($acinsdata['salescost_total']);
			}else{
				$acinsdata['out_sale_price']		= $acinsdata['api_pg_price'] - ($npay_point_sale+$acinsdata['salescost_total']);
			}
			$acinsdata['commission_price']		= $acinsdata['api_pg_commission_price'];
			$acinsdata['commission_price_rest']	= $acinsdata['api_pg_commission_price_rest'];
			$acinsdata['sales_unit_feeprice']	= $acinsdata['api_pg_sales_unit_feeprice'];
			$acinsdata['sales_feeprice_rest']	= $acinsdata['api_pg_sales_feeprice_rest'];
			$acinsdata['sales_unit_minfee']		= 0;
		}

	}else{
		// 제휴사 할인 계산 + out_pg_sale_price :: 2018-06-01 lkh
		$acinsdata['out_pg_sale_price']		= $acinsdata['pg_sale_price'] = $acinsdata['api_pg_sale_price'];
		$acinsdata['out_price'] 			= ($acinsdata['out_price']);

		$acinsdata['out_sales_price_total']	= ($acinsdata['out_price']) + $acinsdata['out_salescost_total'];//총 판매금액(8가지할인항목 총합)  + (
		// 제휴사 할인 계산 + out_pg_sale_price :: 2018-06-01 lkh
		$acinsdata['out_sale_price']		= ($acinsdata['out_price'] - $acinsdata['out_pg_sale_price'])-($acinsdata['out_salescost_total'])-$acinsdata['out_cash_use'];//결제금액
		$acinsdata['out_ac_sale_price']		= ($acinsdata['out_ac_price'] - $acinsdata['out_pg_sale_price'])-($acinsdata['out_ac_salescost_total'])-$acinsdata['out_ac_cash_use'];//결제금액
		if($acinsdata['out_ac_sale_price'] < 0){
			$acinsdata['out_ac_sale_price'] = 0;
		}

		// 총 결재금액 2019-06-18 by hed
		if(empty($acinsdata['total_payprice']) || $acinsdata['total_payprice'] == '0.00'){
			$acinsdata['total_payprice'] = $acinsdata['out_sale_price'] + $acinsdata['out_cash_use'];
		}
	}
	/**
	** 할인부담금 끝
	**/

	// 강제 절삭처리
	$currency = $CI->config_system['basic_currency'];
	if(in_array($currency,array("KRW","JPY"))){
		$acinsdata['out_sale_price'] = number_format($acinsdata['out_sale_price'], 0, '.', '');
		$acinsdata['out_cash_use'] = number_format($acinsdata['out_cash_use'], 0, '.', '');
		$acinsdata['total_payprice'] = number_format($acinsdata['total_payprice'], 0, '.', '');
	}
	
	$acinsdata['account_type_view'] 	= $CI->accountallmodel->account_type_ar[$acinsdata['account_type']];

	$acinsdata['out_step'] 				= ($acinsdata['account_type']=="order" && $acinsdata['status']=="overdraw")?$CI->arr_step[$acinsdata['step']]:$acinsdata['account_type_view'];

	$acinsdata['out_order_type'] 		= ($acinsdata['account_type']=="return" && $acinsdata['order_type']=="shipping")?$CI->accountallmodel->order_type_ar['returnshipping']:$CI->accountallmodel->order_type_ar[$acinsdata['order_type']];

	if( $acinsdata['order_goods_kind'] == 'shipping' && $acinsdata['shipping_provider_seq'] == 1) {//위탁배송이면 정산 0원처리
		$acinsdata['out_ac_profit_price']		= $acinsdata['out_ac_sale_price']+$acinsdata['out_ac_cash_use'];		//이익금
		$acinsdata['out_ac_profit_rate']		= sprintf("%.1f",100);		//이익율
		$acinsdata['out_total_ac_price']		= 0;		//정산대상금액(A)>합계
		$acinsdata['out_pg_default_price']		= 0;		//정산대상금액(A)>PG(전쳬)
		$acinsdata['out_ac_salescost_admin']	= 0;		//정산대상금액(A)>본사
		$acinsdata['out_ac_salescost_provider']	= 0;		//정산대상금액(A)>입점사
		$acinsdata['out_ac_salescost_total']	= 0;		//정산대상금액(A)>할인전체
		$acinsdata['out_ac_cash_use']			= 0;		//정산대상금액(A)>이머니
		$acinsdata['out_ac_pg_price']			= 0;		//정산대상금액(A)>제휴사 -> Npay Point
		$acinsdata['out_pg_add_price']			= 0;		//정산대상금액(A)>추가할인
		$acinsdata['out_ac_consumer_real_price']= 0;		//공급금액
		$acinsdata['out_fee_rate']				= 0;		//수수료율
		$acinsdata['out_sales_unit_feeprice']	= 0;		//정산>수수료(B)
		$acinsdata['out_commission_price']		= 0;		//정산> 정산금액(A-B)
		$acinsdata['out_pg_support_price']		= 0;		//판매자 추가할인
		$acinsdata['out_ac_acc_status']			= "no_acc";	//정산대상여부
	}else{
		if($CI->accountallmodel->account_fee_ar['pg'] && !($acinsdata['order_referer'] == 'shop' && $acinsdata['payment'] == 'bank') ){
			if( $acinsdata['order_referer'] == 'npg' || $acinsdata['order_referer'] == 'pg' ) {
				$acinsdata['out_pg_default_price']	= ($acinsdata['out_sale_price']);		//결제금액(A)->정산대상금액  -$npay_point_sale
				$acinsdata['out_total_ac_price']	= ($acinsdata['out_pg_default_price']+$acinsdata['out_salescost_total']);//정산대상금액(A)>합계+$npay_point_sale


			}else{
				$acinsdata['out_total_ac_price']	= (($acinsdata['out_sale_price'])+$npay_point_sale+$acinsdata['ac_salescost_admin']+($acinsdata['out_cash_use']));//정산대상금액(A)>합계
				$acinsdata['out_pg_default_price']	= ($acinsdata['out_sale_price']);		//정산대상금액(A)>PG(전쳬)


			}
		}else{
			// 합계금액 잘못나와 수정 :: 2018-05-21 lkh
			if($acinsdata['commission_type'] == "SUCO"){
				$acinsdata['out_total_ac_price']	= ($acinsdata['out_ac_sale_price']+$acinsdata['out_ac_cash_use']);//정산대상금액(A)>합계
				$acinsdata['out_ac_salescost_admin'] = 0;
			}elseif($acinsdata['commission_type'] == "SUPR"){
				$acinsdata['out_total_ac_price']		= ($acinsdata['out_ac_sale_price']+$acinsdata['out_ac_cash_use']);//정산대상금액(A)>합계
				$acinsdata['out_ac_salescost_admin']	= 0;
			}else{
				$acinsdata['out_total_ac_price']	= (($acinsdata['out_ac_sale_price'])+$acinsdata['out_ac_salescost_admin']+$acinsdata['out_ac_cash_use']);//정산대상금액(A)>합계
			}
			$acinsdata['out_pg_default_price']	= ($acinsdata['out_ac_sale_price'])+$acinsdata['out_ac_cash_use'];		//정산대상금액(A)>PG(전쳬)
		}

		//수수료율 정산금액, 수수료액
		if($acinsdata['commission_type'] == "SACO"){

		$total_recalculation = true;
		if($pagetype == "list2"){
			if(empty($acinsdata['total_feeprice']) || $acinsdata['total_feeprice'] == '0.00'){
				$acinsdata['out_sales_unit_feeprice']	= (round($acinsdata['sales_unit_feeprice']*$acinsdata['exp_ea'])+$acinsdata['sales_unit_minfee']+($acinsdata['sales_feeprice_rest']));
			}else{
				$total_recalculation = false;
				$acinsdata['out_sales_unit_feeprice']	= $acinsdata['total_feeprice'];
			}
		}else{
			$acinsdata['out_sales_unit_feeprice']	= (($acinsdata['sales_unit_feeprice']*$acinsdata['exp_ea'])+$acinsdata['sales_unit_minfee']+($acinsdata['sales_feeprice_rest']));
		}

		//20190429 pjm
		if($pagetype == "list2"){
			if(empty($acinsdata['total_commission_price']) || $acinsdata['total_commission_price'] == '0.00'){
				$acinsdata['out_commission_price']	= $acinsdata['out_total_ac_price'] - $acinsdata['out_sales_unit_feeprice'];		//정산대상금액 - 수수료액
			}else{
				$total_recalculation = false;
				$acinsdata['out_commission_price']	= $acinsdata['total_commission_price'];
			}
		}else{
			//단품 정산금액*ea+짜투리-수수료짜투리
			$acinsdata['out_commission_price']	= $acinsdata['commission_price']*$acinsdata['exp_ea']+($acinsdata['commission_price_rest']);
		}

		}else{

			//단품 정산금액*ea
			$acinsdata['out_commission_price']	= round($acinsdata['commission_price']*$acinsdata['exp_ea']);

			//수수료금액
			if($pagetype == "list2"){
				//$acinsdata['out_sales_unit_feeprice']	= $acinsdata['total_feeprice'];
				$acinsdata['out_sales_unit_feeprice']	= $acinsdata['out_total_ac_price'] - $acinsdata['out_commission_price'];		//정산대상금액 - 수수료액;
			}else{
				$acinsdata['out_sales_unit_feeprice']	= (($acinsdata['sales_unit_feeprice']*$acinsdata['exp_ea'])+$acinsdata['sales_unit_minfee']+($acinsdata['sales_feeprice_rest']));
			}
		}

		// 공급금액 계산
		$acinsdata['out_ac_fee_rate'] = 0;
		if($acinsdata['commission_type'] == "SUCO"){		//공급율
			$acinsdata['out_ac_consumer_real_price']	= round($acinsdata['consumer_price']*$acinsdata['commission_rate']/100)*$acinsdata['exp_ea'];	//공급가액
			if($acinsdata['out_ac_consumer_real_price'] && $acinsdata['out_sales_unit_feeprice']){
				if($acinsdata['out_total_ac_price'] > 0 && $acinsdata['out_sales_unit_feeprice'] > 0)
					$acinsdata['out_ac_fee_rate']		= round((($acinsdata['commission_rate'])),2);			//공급가율
			}
		}elseif($acinsdata['commission_type'] == "SUPR"){	//공급가
			$acinsdata['out_ac_consumer_real_price']	= $acinsdata['commission_rate']*$acinsdata['exp_ea'];								//공급가액				
			if($acinsdata['out_ac_consumer_real_price'] && $acinsdata['out_sales_unit_feeprice']){
				if($acinsdata['out_total_ac_price'] > 0 && $acinsdata['out_sales_unit_feeprice'] > 0)
					$acinsdata['out_ac_fee_rate']		= 0;	//수수료율(공급가율)
			}
		}else{
			$acinsdata['out_ac_consumer_real_price']	= 0;								//공급가액
			$acinsdata['out_ac_fee_rate']				= $acinsdata['commission_rate'];	//수수료율 round($acinsdata['out_fee_rate'],2)
		}
		if( ($acinsdata['account_type'] != 'refund' && $acinsdata['account_type'] != 'rollback') && ($acinsdata['status'] == "complete" && $acinsdata['exp_ea'] > 0) || ($acinsdata['status'] == "carryover" && $acinsdata['exp_ea'] > 0) || ($acinsdata['order_goods_kind'] != 'shipping' && $acinsdata['provider_seq'] == 1) || ($acinsdata['order_goods_kind'] == 'shipping' && $acinsdata['shipping_provider_seq'] == 1) ){
			// 3차 환불 개선으로 티켓상품 금액 처리 추가 :: 2018-11- lkh
			$outPgDefaultPrice = $acinsdata['out_pg_default_price'];
			if($acinsdata['order_goods_kind'] == "coupon"){
				$couponStatusArr = explode("|",$acinsdata['socialcp_status']);
				$couponValArr = explode("|",$acinsdata['coupon_value']);
				$couponRemainValArr = explode("|",$acinsdata['coupon_remain_value']);
				$couponVal = 0;
				$couponUseVal = 0;
				foreach($couponStatusArr as $cpKey => $cpVal){
					$cpValSt = substr($cpVal,0,1);
					$cpStHalfChk = array(7,9);
					if(in_array($cpValSt,$cpStHalfChk)){
						$couponVal += $couponValArr[$cpKey];
						$couponUseVal += ($couponValArr[$cpKey] -$couponRemainValArr[$cpKey]);
					}else{
						$couponVal += $couponValArr[$cpKey];
						$couponUseVal += $couponValArr[$cpKey];
					}
				}
				if($couponVal != $couponUseVal){
					$couponUsePercent = 100 * ($couponUseVal / $couponVal);//사용값 비율
					$acinsdata['out_total_ac_price']		= acc_coupon_remain_sales_unit($acinsdata['out_total_ac_price'],$couponUsePercent);			//정산대상금액(A)>합계
					$acinsdata['out_pg_default_price']		= acc_coupon_remain_sales_unit($acinsdata['out_pg_default_price'],$couponUsePercent);		//정산대상금액(A)>PG(전쳬)
					$acinsdata['out_ac_salescost_admin']	= acc_coupon_remain_sales_unit($acinsdata['out_ac_salescost_admin'],$couponUsePercent);		//정산대상금액(A)>본사
					$acinsdata['out_ac_salescost_provider']	= acc_coupon_remain_sales_unit($acinsdata['out_ac_salescost_provider'],$couponUsePercent);	//정산대상금액(A)>입점사
					$acinsdata['out_ac_salescost_total']	= acc_coupon_remain_sales_unit($acinsdata['out_ac_salescost_total'],$couponUsePercent);		//정산대상금액(A)>할인전체
					$acinsdata['out_ac_cash_use']			= acc_coupon_remain_sales_unit($acinsdata['out_ac_cash_use'],$couponUsePercent);			//정산대상금액(A)>이머니
					$acinsdata['out_ac_pg_price']			= acc_coupon_remain_sales_unit($acinsdata['out_ac_pg_price'],$couponUsePercent);			//정산대상금액(A)>제휴사 -> Npay Point
					$acinsdata['out_pg_add_price']			= acc_coupon_remain_sales_unit($acinsdata['out_pg_add_price'],$couponUsePercent);			//정산대상금액(A)>추가할인
					$acinsdata['out_ac_consumer_real_price']= acc_coupon_remain_sales_unit($acinsdata['out_ac_consumer_real_price'],$couponUsePercent);	//공급금액
					if($total_recalculation){
						$acinsdata['out_sales_unit_feeprice']	= acc_coupon_remain_sales_unit($acinsdata['out_sales_unit_feeprice'],$couponUsePercent);	//정산>수수료(B)
						$acinsdata['out_commission_price']		= acc_coupon_remain_sales_unit($acinsdata['out_commission_price'],$couponUsePercent);		//정산> 정산금액(A-B)
					}
					$acinsdata['out_pg_support_price']		= acc_coupon_remain_sales_unit($acinsdata['out_pg_support_price'],$couponUsePercent);		//판매자 추가할인
					$outPgDefaultPrice 						= $acinsdata['out_pg_default_price'];
				}
			}
			
			// 이익 계산
			$acinsdata['out_ac_profit_price']	= $outPgDefaultPrice-$acinsdata['out_commission_price'];	//이익
			$acinsdata['out_ac_profit_rate'] 	= 0;
			if($acinsdata['out_ac_profit_price'] && $outPgDefaultPrice){
				$acinsdata['out_ac_profit_rate']	= round(($acinsdata['out_ac_profit_price']/$outPgDefaultPrice*100),1);	//이익율
				$acinsdata['out_ac_profit_rate']	= sprintf("%.1f",$acinsdata['out_ac_profit_rate']);
			}
		}

		// 결제 금액이 0원일때 결제금액, 수수료율 0 처리 :: 2018-07-11 lkh
		// 정산대상관련금액 초기화? : 본사할인금액 0원 && 결제금액 0원 && 예치금사용액 0원
		if( ($acinsdata['out_ac_pg_price'] <= 0 && $acinsdata['out_ac_salescost_admin'] <= 0 && $acinsdata['out_ac_sale_price'] <= 0 && $acinsdata['out_cash_use'] <= 0) || ($acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback") ){
			$acinsdata['out_total_ac_price']		= 0;		//정산대상금액(A)>합계
			$acinsdata['out_pg_default_price']		= 0;		//정산대상금액(A)>PG(전쳬)
			$acinsdata['out_ac_salescost_admin']	= 0;		//정산대상금액(A)>본사
			$acinsdata['out_ac_salescost_provider']	= 0;		//정산대상금액(A)>입점사
			$acinsdata['out_ac_salescost_total']	= 0;		//정산대상금액(A)>할인전체
			$acinsdata['out_ac_cash_use']			= 0;		//정산대상금액(A)>이머니
			$acinsdata['out_ac_pg_price']			= 0;		//정산대상금액(A)>제휴사 -> Npay Point
			$acinsdata['out_pg_add_price']			= 0;		//정산대상금액(A)>추가할인
			$acinsdata['out_ac_consumer_real_price']= 0;		//공급금액
			//$acinsdata['out_sales_unit_feeprice']	= 0;		//정산>수수료(B)
			//$acinsdata['out_commission_price']		= 0;		//정산> 정산금액(A-B)

			$acinsdata['out_pg_support_price']		= 0;		//판매자 추가할인
			//$acinsdata['out_ac_fee_rate'] 			= 0;
		}
		//debug_var($acinsdata['out_ac_profit_rate'].'= (('.$acinsdata['out_ac_profit_price'].')/'.$acinsdata['out_pg_default_price'].'*100)');
	}

	switch($acinsdata['status']){//전월/당월/차월 구분
		case "carryover"://통합정산데이타의 전월
		case "not-carryover":

			$carryovertot['total_num']++;
			$carryovertot['out_title']				= $acinsdata['out_title']	= "이월";
			$carryovertot['out_num']				= '합계';
			$carryovertot['out_total_title']		= 'start';

			$acinsdata['account_refund_rollback']		= false;

			//소계영역
			if( $acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback" ) {
				$acinsdata['account_refund_rollback']		= true;
				$acinsdata['minus_sale'] = '1';	// 매출 마이너스 표기 0:양수, 1:음수
				$carryovertot['refund_out_consumer_price']		+= $acinsdata['out_consumer_price'];
				$carryovertot['refund_out_sales_price']			+= $acinsdata['out_sales_price'];
				$carryovertot['refund_salescost_total']			+= $acinsdata['salescost_total'];
				$carryovertot['refund_pg_sale_price']			+= $acinsdata['pg_sale_price'];
				$carryovertot['refund_pg_support_price']		+= $acinsdata['pg_support_price'];

				//통합매출 소계
				$carryovertot['refund_out_ea']					+= $acinsdata['out_ea'];
				$carryovertot['refund_out_price']				+= $acinsdata['out_price'];
				$carryovertot['refund_out_salescost_total']		+= $acinsdata['out_salescost_total'];
				$carryovertot['refund_out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
				$carryovertot['refund_out_cash_use']			+= $acinsdata['out_cash_use'];
				$carryovertot['refund_out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
				$carryovertot['refund_out_sale_price']			+= $acinsdata['out_sale_price'];
				$carryovertot['refund_salescost_admin']			+= $acinsdata['out_salescost_admin'];
				$carryovertot['refund_salescost_provider']		+= $acinsdata['out_salescost_provider'];

				//통합정산 소계
				$carryovertot['refund_out_exp_ea']				+= $acinsdata['out_exp_ea'];
				$carryovertot['refund_out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
				$carryovertot['refund_out_pg_default_price']	+= $acinsdata['out_pg_default_price'];
				$carryovertot['refund_out_ac_salescost_total']	+= $acinsdata['out_ac_salescost_total'];
				$carryovertot['refund_out_ac_cash_use']			+= $acinsdata['out_ac_cash_use'];
				$carryovertot['refund_out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
				$carryovertot['refund_out_pg_add_price']		+= $acinsdata['out_pg_add_price'];
				$carryovertot['refund_sales_unit_feeprice']		+= $acinsdata['out_sales_unit_feeprice'];
				$carryovertot['refund_out_commission_price']	+= $acinsdata['out_commission_price'];

				$carryovertot['refund_ac_salescost_admin']		+= $acinsdata['out_ac_salescost_admin'];
				$carryovertot['refund_ac_salescost_provider']	+= $acinsdata['out_ac_salescost_provider'];

				$carryovertot['refund_out_ac_consumer_real_price']	+= $acinsdata['out_ac_consumer_real_price'];
				$carryovertot['refund_out_ac_profit_price']			+= $acinsdata['out_ac_profit_price'];

			}else{
				$carryovertot['out_consumer_price']		+= $acinsdata['out_consumer_price'];
				$carryovertot['out_sales_price']		+= $acinsdata['out_sales_price'];
				$carryovertot['salescost_total']		+= $acinsdata['salescost_total'];
				$carryovertot['pg_sale_price']			+= $acinsdata['pg_sale_price'];
				$carryovertot['pg_support_price']		+= $acinsdata['pg_support_price'];

				//통합매출 소계
				$carryovertot['out_ea']					+= $acinsdata['out_ea'];
				$carryovertot['out_price']				+= $acinsdata['out_price'];
				$carryovertot['out_salescost_total']	+= $acinsdata['out_salescost_total'];
				$carryovertot['out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
				$carryovertot['out_cash_use']			+= $acinsdata['out_cash_use'];
				$carryovertot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
				$carryovertot['out_sale_price']			+= $acinsdata['out_sale_price'];
				$carryovertot['out_salescost_admin']		+= $acinsdata['out_salescost_admin'];
				$carryovertot['out_salescost_provider']		+= $acinsdata['out_salescost_provider'];

				if(in_array($acinsdata['ac_type'],array("cal","cal_sales")) && (!$acinsdata['out_confirm_date'] || $acinsdata['ac_ea'] != '0')){
					$acinsdata['out_total_ac_price']		= 0;
					$acinsdata['out_pg_default_price']		= 0;
					$acinsdata['out_ac_salescost_total']	= 0;
					$acinsdata['out_ac_cash_use']			= 0;
					$acinsdata['out_ac_pg_price']			= 0;
					$acinsdata['out_pg_add_price']			= 0;
					$acinsdata['out_sales_unit_feeprice']	= 0;
					$acinsdata['out_commission_price']		= 0;
					$acinsdata['out_exp_ea']				= 0;
					$acinsdata['out_ac_salescost_admin']	= 0;
					$acinsdata['out_ac_salescost_provider']	= 0;
					$acinsdata['out_ac_consumer_real_price']= 0;
					$acinsdata['out_ac_fee_rate']			= 0;
					$acinsdata['out_ac_profit_price']		= 0;
					$acinsdata['out_ac_profit_rate']		= 0;
					//$acinsdata['out_ac_profit_price']		= 0;
					//$acinsdata['out_ac_profit_rate']		= 0;
					if(!$acinsdata['out_ac_acc_status']) $acinsdata['out_ac_acc_status'] = "ing_acc";	//정산대상여부
				}

				//통합정산 소계
				$carryovertot['out_exp_ea']					+= $acinsdata['out_exp_ea'];
				$carryovertot['out_total_ac_price']			+= $acinsdata['out_total_ac_price'];
				$carryovertot['out_pg_default_price']		+= $acinsdata['out_pg_default_price'];
				$carryovertot['out_ac_salescost_total']		+= $acinsdata['out_ac_salescost_total'];
				$carryovertot['out_ac_cash_use']			+= $acinsdata['out_ac_cash_use'];
				$carryovertot['out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
				$carryovertot['out_pg_add_price']			+= $acinsdata['out_pg_add_price'];
				$carryovertot['out_sales_unit_feeprice']	+= $acinsdata['out_sales_unit_feeprice'];
				$carryovertot['out_commission_price']		+= $acinsdata['out_commission_price'];
				$carryovertot['out_ac_salescost_admin']		+= $acinsdata['out_ac_salescost_admin'];
				$carryovertot['out_ac_salescost_provider']	+= $acinsdata['out_ac_salescost_provider'];
				$carryovertot['out_ac_consumer_real_price']	+= $acinsdata['out_ac_consumer_real_price'];
				$carryovertot['out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

			}
			if($mode == 'all'){
				$carryoverloop[] = $acinsdata;
			}elseif($mode == 'sum'){
				$carryovertot['carryoverloopcnt']++;
			}elseif($mode == 'excel'){
				draw_excel_accountall($caller, 'create', 'carryoverloop', $acinsdata, $carryovertot['carryoverloopcnt']);
				$carryovertot['carryoverloopcnt']++;
			}
		break;
		case "overdraw"://차월영역
			/**
			* 현재기준 이달이면 통합매출데이타의 당월영역이며 전달이면 차월영역 노출
			* @2018-02-06
			**/
			/*if($_GET['acc_table'] >= date('Ym')) {*/
				$tot['total_num']++;
				$tot['out_title']			= $acinsdata['out_title']	= "당월";
				$tot['out_num']				= '합계';
				$tot['out_total_title']		= 'start';
				//소계영역
				if( $acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback" ) {
					$acinsdata['minus_sale'] = '1';	// 매출 마이너스 표기 0:양수, 1:음수
					$tot['refund_out_consumer_price']		+= $acinsdata['out_consumer_price'];
					$tot['refund_out_sales_price']			+= $acinsdata['out_sales_price'];
					$tot['refund_salescost_total']			+= $acinsdata['salescost_total'];
					$tot['refund_pg_sale_price']			+= $acinsdata['pg_sale_price'];
					$tot['refund_pg_support_price']			+= $acinsdata['pg_support_price'];
					$tot['refund_out_pg_add_price']			+= $acinsdata['out_pg_add_price'];
					if($acinsdata['ac_type'] == "sales") {
						//통합정산 소계
						$acinsdata['out_total_ac_price']		= 0;
						$acinsdata['out_pg_default_price']		= 0;
						$acinsdata['out_ac_salescost_total']	= 0;
						$acinsdata['out_ac_cash_use']			= 0;
						$acinsdata['out_ac_pg_price']			= 0;
						$acinsdata['out_pg_add_price']			= 0;
						$acinsdata['out_sales_unit_feeprice']	= 0;
						$acinsdata['out_commission_price']		= 0;
						$acinsdata['out_exp_ea']				= 0;
						$acinsdata['out_ac_salescost_admin']	= 0;
						$acinsdata['out_ac_salescost_provider']	= 0;
						$acinsdata['out_ac_consumer_real_price']= 0;
						$acinsdata['out_ac_fee_rate']			= 0;


						//$acinsdata['out_ac_profit_price']		= 0;
						//$acinsdata['out_ac_profit_rate']		= 0;
						if(!$acinsdata['out_ac_acc_status']) $acinsdata['out_ac_acc_status'] = "ing_acc";	//정산대상여부


						$tot['out_ac_salescost_admin']		+= 0;
						$tot['out_ac_salescost_provider']	+= 0;

						$tot['refund_out_ea']					+= $acinsdata['out_ea'];
						$tot['refund_out_exp_ea']				+= $acinsdata['out_exp_ea'];
						$tot['refund_out_total_ac_price']		+= 0;//$acinsdata['out_total_ac_price'];
						$tot['refund_out_pg_default_price']		+= 0;//$acinsdata['out_pg_default_price'];
						$tot['refund_out_ac_salescost_total']	+= 0;//$acinsdata['out_ac_salescost_total'];
						$tot['refund_out_ac_cash_use']			+= 0;//$acinsdata['out_cash_use'];
						$tot['refund_out_ac_pg_price']			+= 0;//$acinsdata['out_ac_pg_price'];
						$tot['refund_out_pg_add_price']			+= 0;//$acinsdata['out_pg_add_price'];
						$tot['refund_sales_unit_feeprice']		+= 0;// $acinsdata['out_sales_unit_feeprice'];
						$tot['refund_out_commission_price']		+= 0;//$acinsdata['out_commission_price'];
						$tot['refund_out_pg_support_price']		+= 0;//$acinsdata['out_api_pg_support_price'];

						$tot['refund_ac_salescost_admin']		+= 0;//$acinsdata['out_ac_salescost_admin'];
						$tot['refund_ac_salescost_provider']	+= 0;//$acinsdata['out_ac_salescost_provider'];

						$tot['refund_out_price']				+= $acinsdata['out_price'];
						$tot['refund_out_salescost_total']		+= $acinsdata['out_salescost_total'];
						$tot['refund_out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
						$tot['refund_out_cash_use']				+= $acinsdata['out_cash_use'];
						$tot['refund_out_pg_support_price']		+= $acinsdata['out_pg_support_price'];
						$tot['refund_out_sale_price']			+= $acinsdata['out_sale_price'];
						$tot['refund_salescost_admin']			+= $acinsdata['out_salescost_admin'];
						$tot['refund_salescost_provider']		+= $acinsdata['out_salescost_provider'];
						$tot['refund_out_ac_consumer_real_price']+= $acinsdata['out_ac_consumer_real_price'];
						$tot['refund_out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

					}else{//통합정산 소계

						$tot['refund_out_ea']					+= $acinsdata['out_ea'];
						$tot['refund_out_exp_ea']				+= $acinsdata['out_exp_ea'];
						$tot['refund_out_price']				+= $acinsdata['out_price'];
						$tot['refund_out_salescost_total']		+= $acinsdata['out_salescost_total'];
						$tot['refund_out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
						$tot['refund_out_cash_use']				+= $acinsdata['out_cash_use'];
						$tot['refund_out_pg_support_price']		+= $acinsdata['out_pg_support_price'];
						$tot['refund_out_sale_price']			+= $acinsdata['out_sale_price'];

						$tot['refund_out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
						$tot['refund_out_pg_default_price']		+= $acinsdata['out_pg_default_price'];
						$tot['refund_sales_unit_feeprice']		+= $acinsdata['out_sales_unit_feeprice'];
						$tot['refund_out_commission_price']		+= $acinsdata['out_commission_price'];

						$tot['refund_ac_salescost_admin']		+= 0;//$acinsdata['out_ac_salescost_admin'];
						$tot['refund_ac_salescost_provider']	+= 0;//$acinsdata['out_ac_salescost_provider'];

						$tot['refund_salescost_admin']			+= $acinsdata['out_salescost_admin'];
						$tot['refund_salescost_provider']		+= $acinsdata['out_salescost_provider'];
						$tot['refund_out_ac_salescost_total']	+= $acinsdata['out_salescost_total'];
						$tot['refund_out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
						$tot['refund_out_ac_cash_use']			+= $acinsdata['out_cash_use'];
						$tot['refund_out_ac_consumer_real_price']+= $acinsdata['out_ac_consumer_real_price'];
						$tot['refund_out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

					}
				}else{
					$tot['out_consumer_price']		+= $acinsdata['out_consumer_price'];
					$tot['out_sales_price']			+= $acinsdata['out_sales_price'];
					$tot['salescost_total']			+= $acinsdata['salescost_total'];
					$tot['pg_sale_price']			+= $acinsdata['pg_sale_price'];
					$tot['pg_support_price']		+= $acinsdata['pg_support_price'];

					//통합매출 소계

					if($acinsdata['ac_type'] == "sales") {
						//통합정산 소계
						$acinsdata['out_exp_ea']				= 0;
						$acinsdata['out_total_ac_price']		= 0;
						$acinsdata['out_pg_default_price']		= 0;
						$acinsdata['out_ac_salescost_total']	= 0;
						$acinsdata['out_ac_cash_use']			= 0;
						$acinsdata['out_ac_pg_price']			= 0;
						$acinsdata['out_pg_add_price']			= 0;
						$acinsdata['out_sales_unit_feeprice']	= 0;
						$acinsdata['out_commission_price']		= 0;//out_ac_salescost_total
						$acinsdata['out_pg_support_price']		= 0;//out_api_pg_support_price
						$acinsdata['out_ac_consumer_real_price']= 0;
						$acinsdata['out_ac_fee_rate']			= 0;
						//$acinsdata['out_ac_profit_price']		= 0;
						//$acinsdata['out_ac_profit_rate']		= 0;
						if(!$acinsdata['out_ac_acc_status']) $acinsdata['out_ac_acc_status'] = "ing_acc";	//정산대상여부

						$tot['out_total_ac_price']		+= 0;//$acinsdata['out_total_ac_price'];
						$tot['out_pg_default_price']	+= 0;//$acinsdata['out_pg_default_price'];
						$tot['out_ac_salescost_total']	+= 0;//$acinsdata['out_ac_salescost_total'];
						$tot['out_ac_cash_use']			+= 0;//$acinsdata['out_ac_cash_use'];out_salescost_total
						$tot['out_ac_pg_price']			+= 0;//$acinsdata['out_ac_pg_price'];
						$tot['out_pg_add_price']		+= 0;//$acinsdata['out_pg_add_price'];
						$tot['out_sales_unit_feeprice']	+= 0;//$acinsdata['out_sales_unit_feeprice'];
						$tot['out_commission_price']	+= 0;//$acinsdata['out_commission_price'];out_salescost_total
						$tot['out_pg_support_price']	+= 0;//$acinsdata['out_pg_support_price'];
						$tot['out_ac_consumer_real_price']+= 0;
						//$tot['out_ac_profit_price']		+= 0;

						$tot['out_ea']					+= $acinsdata['out_ea'];
						$tot['out_exp_ea']				+= $acinsdata['out_exp_ea'];
						$tot['out_price']				+= $acinsdata['out_price'];
						$tot['out_salescost_total']		+= $acinsdata['out_salescost_total'];
						$tot['out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
						$tot['out_cash_use']			+= $acinsdata['out_cash_use'];
						$tot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
						$tot['out_sale_price']			+= $acinsdata['out_sale_price'];


						$tot['out_salescost_admin']		+= $acinsdata['out_salescost_admin'];
						$tot['out_salescost_provider']	+= $acinsdata['out_salescost_provider'];
						$tot['out_ac_consumer_real_price']+= $acinsdata['out_ac_consumer_real_price'];
						$tot['out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

						$acinsdata['out_ac_salescost_admin']	= 0;
						$acinsdata['out_ac_salescost_provider']	= 0;

						$tot['out_ac_salescost_admin']		+= 0;
						$tot['out_ac_salescost_provider']	+= 0;

					}else{//통합정산 소계if($acinsdata['ac_type'] == "cal"){

						$tot['out_ea']					+= $acinsdata['out_ea'];
						$tot['out_exp_ea']				+= $acinsdata['out_exp_ea'];
						$tot['out_price']				+= $acinsdata['out_price'];
						$tot['out_salescost_total']		+= $acinsdata['out_salescost_total'];
						$tot['out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
						$tot['out_cash_use']			+= $acinsdata['out_cash_use'];
						$tot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
						$tot['out_sale_price']			+= $acinsdata['out_sale_price'];
						$tot['out_salescost_admin']		+= $acinsdata['out_salescost_admin'];
						$tot['out_salescost_provider']	+= $acinsdata['out_salescost_provider'];

						$tot['out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
						$tot['out_pg_default_price']	+= $acinsdata['out_pg_default_price'];
						$tot['out_ac_salescost_total']	+= $acinsdata['out_ac_salescost_total'];
						$tot['out_ac_cash_use']			+= $acinsdata['out_ac_cash_use'];
						$tot['out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
						$tot['out_pg_add_price']		+= $acinsdata['out_pg_add_price'];
						$tot['out_sales_unit_feeprice']	+= $acinsdata['out_sales_unit_feeprice'];
						$tot['out_commission_price']	+= $acinsdata['out_commission_price'];



						$tot['out_ac_salescost_admin']		+= $acinsdata['out_ac_salescost_admin'];
						$tot['out_ac_salescost_provider']	+= $acinsdata['out_ac_salescost_provider'];

						$tot['out_ac_consumer_real_price']	+= $acinsdata['out_ac_consumer_real_price'];
						$tot['out_ac_profit_price']			+= $acinsdata['out_ac_profit_price'];

					}

				}
				if($mode == 'all'){
					$loop[] = $acinsdata;
				}elseif($mode == 'sum'){
					$tot['loopcnt']++;
				}elseif($mode == 'excel'){
					draw_excel_accountall($caller, 'create', 'loop', $acinsdata, $tot['loopcnt'], $carryovertot['carryoverloopcnt']);
					$tot['loopcnt']++;
				}
		break;
		case "complete"://매출+정산 당월
			$tot['total_num']++;
			$tot['out_title']			= $acinsdata['out_title']	= "당월";
			$tot['out_num']				= '합계';
			$tot['out_total_title']		= 'start';
			//소계영역
			if( $acinsdata['account_type'] == "refund" || $acinsdata['account_type'] == "rollback" || $acinsdata['account_type'] == "after_refund" ) {	// 구매확정후 환불은 정산완료(complete)된 데이터만 있음
				$acinsdata['minus_sale'] = '1';	// 매출 마이너스 표기 0:양수, 1:음수
				$tot['refund_out_consumer_price']		+= $acinsdata['out_consumer_price'];
				$tot['refund_out_sales_price']			+= $acinsdata['out_sales_price'];
				$tot['refund_salescost_total']			+= $acinsdata['salescost_total'];
				$tot['refund_pg_sale_price']			+= $acinsdata['pg_sale_price'];
				$tot['refund_pg_support_price']			+= $acinsdata['pg_support_price'];
				$tot['refund_out_pg_add_price']			+= $acinsdata['out_pg_add_price'];

				//통합매출 소계
				//if($acinsdata['ac_type'] == "sales"){ -> rollback 차감시 필요 @2017-12-05
					$tot['refund_out_ea']					+= $acinsdata['out_ea'];
					$tot['refund_out_price']				+= $acinsdata['out_price'];
					$tot['refund_out_salescost_total']		+= $acinsdata['out_salescost_total'];
					$tot['refund_out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
					$tot['refund_out_cash_use']				+= $acinsdata['out_cash_use'];
					$tot['refund_out_pg_support_price']		+= $acinsdata['out_pg_support_price'];
					$tot['refund_out_sale_price']			+= $acinsdata['out_sale_price'];
				//}
					$tot['refund_out_exp_ea']				+= $acinsdata['out_exp_ea'];

				//통합정산 소계 refund_sales_unit_feeprice
				if($acinsdata['ac_type'] == "cal"){
					$tot['refund_out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
					$tot['refund_out_pg_default_price']		+= $acinsdata['out_pg_default_price'];
					$tot['refund_sales_unit_feeprice']		+= $acinsdata['out_sales_unit_feeprice'];
					$tot['refund_out_commission_price']		+= $acinsdata['out_commission_price'];
					$tot['refund_out_pg_support_price']		+= $acinsdata['out_pg_support_price'];
					$tot['refund_salescost_admin']			+= $acinsdata['out_salescost_admin'];
					$tot['refund_salescost_provider']		+= $acinsdata['out_salescost_provider'];
					$tot['refund_out_ac_salescost_total']	+= $acinsdata['out_salescost_total'];
					$tot['refund_out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
					$tot['refund_out_ac_cash_use']			+= $acinsdata['out_cash_use'];
					$tot['refund_out_ac_consumer_real_price']+= $acinsdata['out_ac_consumer_real_price'];
					$tot['refund_out_ac_profit_price']		+= $acinsdata['out_ac_profit_price'];

					$tot['refund_ac_salescost_admin']		+= $acinsdata['out_ac_salescost_admin'];
					$tot['refund_ac_salescost_provider']	+= $acinsdata['out_ac_salescost_provider'];
				}
			}else{
				$tot['out_consumer_price']		+= $acinsdata['out_consumer_price'];
				$tot['out_sales_price']			+= $acinsdata['out_sales_price'];
				$tot['salescost_total']			+= $acinsdata['salescost_total'];
				$tot['pg_sale_price']			+= $acinsdata['pg_sale_price'];
				$tot['pg_support_price']		+= $acinsdata['pg_support_price'];

				//통합매출 소계
				//if($acinsdata['ac_type'] == "sales"){
					$tot['out_ea']					+= $acinsdata['out_ea'];
					$tot['out_price']				+= $acinsdata['out_price'];
					$tot['out_salescost_total']		+= $acinsdata['out_salescost_total'];
					$tot['out_pg_sale_price']		+= $acinsdata['out_pg_sale_price'];
					$tot['out_cash_use']			+= $acinsdata['out_cash_use'];
					$tot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
					$tot['out_sale_price']			+= $acinsdata['out_sale_price'];
				//}

				//통합정산 소계
					$tot['out_exp_ea']				+= $acinsdata['out_exp_ea'];
				if($acinsdata['ac_type'] == "cal"){
					$tot['out_total_ac_price']		+= $acinsdata['out_total_ac_price'];
					$tot['out_pg_default_price']	+= $acinsdata['out_pg_default_price'];
					$tot['out_ac_salescost_total']	+= $acinsdata['out_ac_salescost_total'];
					$tot['out_ac_cash_use']			+= $acinsdata['out_ac_cash_use'];
					$tot['out_ac_pg_price']			+= $acinsdata['out_ac_pg_price'];
					$tot['out_pg_add_price']		+= $acinsdata['out_pg_add_price'];
					$tot['out_sales_unit_feeprice']	+= $acinsdata['out_sales_unit_feeprice'];
					$tot['out_commission_price']	+= $acinsdata['out_commission_price'];
					$tot['out_pg_support_price']	+= $acinsdata['out_pg_support_price'];
					$tot['out_salescost_admin']		+= $acinsdata['out_salescost_admin'];
					$tot['out_salescost_provider']	+= $acinsdata['out_salescost_provider'];
					$tot['out_ac_salescost_admin']		+= $acinsdata['out_ac_salescost_admin'];
					$tot['out_ac_salescost_provider']	+= $acinsdata['out_ac_salescost_provider'];
					$tot['out_ac_consumer_real_price']	+= $acinsdata['out_ac_consumer_real_price'];
					$tot['out_ac_profit_price']			+= $acinsdata['out_ac_profit_price'];

				}

			}
			if($mode == 'all'){
				$loop[] = $acinsdata;
			}elseif($mode == 'sum'){
				$tot['loopcnt']++;
			}elseif($mode == 'excel'){
				draw_excel_accountall($caller, 'create', 'loop', $acinsdata, $tot['loopcnt'], $carryovertot['carryoverloopcnt']);
				$tot['loopcnt']++;
			}
		break;
	}
	if($extend['accountgroup']){
		foreach($extend['provider'] as $tmp){
			if($acinsdata['provider_seq'] == $tmp['provider_seq']){
				$tmp_provider = $tmp;
			}
		}
		
		$check_calcu_count = true;
		// 정산주기 조건절
		if($extend['pay_period'] && $extend['pay_period']!='all'){
			if($extend['pay_period'] != $tmp_provider['calcu_count']){
				$check_calcu_count = false;
			}
		}
		
		if(empty($extend['accountAllCount'][$tmp_provider['provider_seq']])){
			$extend['accountAllCount'][$tmp_provider['provider_seq']] = array();
		}
		
		if($check_calcu_count){

			// 비교용 날짜 정리
			$diff_accountAllDate['cal2'][0]['start']		= substr($extend['accountAllDate']['cal2'][0]['start'],0,10);
			$diff_accountAllDate['cal2'][0]['end']			= substr($extend['accountAllDate']['cal2'][0]['end'],0,10);
			$diff_accountAllDate['cal2'][1]['start']		= substr($extend['accountAllDate']['cal2'][1]['start'],0,10);
			$diff_accountAllDate['cal2'][1]['end']			= substr($extend['accountAllDate']['cal2'][1]['end'],0,10);
			$diff_accountAllDate['cal4'][0]['start']		= substr($extend['accountAllDate']['cal4'][0]['start'],0,10);
			$diff_accountAllDate['cal4'][0]['end']			= substr($extend['accountAllDate']['cal4'][0]['end'],0,10);
			$diff_accountAllDate['cal4'][1]['start']		= substr($extend['accountAllDate']['cal4'][1]['start'],0,10);
			$diff_accountAllDate['cal4'][1]['end']			= substr($extend['accountAllDate']['cal4'][1]['end'],0,10);
			$diff_accountAllDate['cal4'][2]['start']		= substr($extend['accountAllDate']['cal4'][2]['start'],0,10);
			$diff_accountAllDate['cal4'][2]['end']			= substr($extend['accountAllDate']['cal4'][2]['end'],0,10);
			$diff_accountAllDate['cal4'][3]['start']		= substr($extend['accountAllDate']['cal4'][3]['start'],0,10);
			$diff_accountAllDate['cal4'][3]['end']			= substr($extend['accountAllDate']['cal4'][3]['end'],0,10);
			
			// 정산 합계
			if($acinsdata['provider_seq'] == $tmp_provider['provider_seq']){
				$CI->accountallmodel->sum_provider_account($tmp_provider['provider_seq'], $acinsdata, $tmp_provider, $diff_accountAllDate, $extend['accountAllCount'][$tmp_provider['provider_seq']]);
			}
		}
	}
	
	if($extend['get_provider_seq']){
		/**
		* 입점사정보 한번만 가져오기
		**/
		if($extend['get_provider_seq']) {
			$extend['accountAllCount'][$CI->data_provider[$acinsdata['provider_seq']]['provider_id']]['calcu_count'] = $CI->data_provider[$acinsdata['provider_seq']]['calcu_count'];
		}
		
		$CI->accountallmodel->get_account_all_seller($extend['get_provider_seq'], $acinsdata, $CI->data_provider[$acinsdata['provider_seq']], $extend['accountAllDate'], $extend['accountAllCount']);
	}

	$acinsdata = array();
	$CI->db->queries = array();
	$CI->db->query_times = array();
	if($mode == 'sum'){
		unset($acinsdata);
	}
}

/*
** 구매확정 시 총 정산금액, 총 수수료액 구하기
* @20190530 pjm
1. 공급가(SUPR) 정산 방식
	① 정산대상금액 : 개당 공급가 * 정산수량
	② 정산금액 : round(정산대상금액① - (입점사 할인단가 * 정산수량))
	③ 수수료액 : 정산대상금액① - 정산금액②
2. 공급율(SUCO) 정산 방식 
	① 정산대상금액 : 개당 정가 * 정산수량
	② 정산금액 : round((정산대상금액① - (입점사 할인단가 * 정산수량)) * 공급율 / 100)
	③ 수수료액 : 정산대상금액① - 정산금액②
3. 수수료율(SACO) 정산 방식
	① 정산대상금액 : (개당 판매가 - 입점사 할인단가) * 정산수량
	② 수수료액 : round(정산대상금액① * 수수료율 / 100)
	③ 정산금액 : 정산대상금액① - 수수료액②
*/
function get_buyconfirm_commission($_acc_data){

	$_sales_price		= $_acc_data['out_total_ac_price'];			//판매금액
	$_target_price		= $_acc_data['out_total_ac_price'];			//정산대상총금액
	$commission_rate	= $_acc_data['commission_rate'];			//정산수수료(개당)
	$consumer_price		= $_acc_data['consumer_price'];				//정가(개당)
	$exp_ea				= $_acc_data['exp_ea'];						//정산수량
	$selescost_provider	= $_acc_data['out_ac_salescost_provider'];	//입점사 할인부담총액

	if($_acc_data['commission_type'] == "SUPR"){																	//1. 공급가정산방식
		$_target_price				= $commission_rate * $exp_ea;													//   정산대상금액 = 공급가 * 정산수량
		$total_commission_price		= round($_target_price - $selescost_provider);									//   정산금액 = 공급가 - 입점사부담총할인액
		$total_feeprice				= $_sales_price - $total_commission_price;										//   수수료액 = 판매금액 - 정산금액
	}elseif($_acc_data['commission_type'] == "SUCO"){																//2. 공급율정산방식
		$_target_price				= $consumer_price * $exp_ea;													//   정산대상금액 = (정가 * 정산수량)
		$total_commission_price		= round(($_target_price * $commission_rate / 100) - $selescost_provider) ;		//   정산금액 = (정산대상금액 * 공급율 / 100) - 입점사할인
		$total_feeprice				= $_sales_price - $total_commission_price;										//   수수료액 = 판매금액 - 정산금액
	}else{
		$total_feeprice				= round($_target_price * $commission_rate / 100) ;
		$total_commission_price		= $_target_price - $total_feeprice; 
	}
	$total_payprice = $_acc_data['out_sale_price'] + $_acc_data['out_cash_use'];

	// 강제 절삭처리
	$CI =& get_instance();
	$currency = $CI->config_system['basic_currency'];
	if(in_array($currency,array("KRW","JPY"))){
		$total_payprice = number_format($total_payprice, 0, '.', '');
	}
	
	return array($total_feeprice,$total_commission_price, $total_payprice);
}


//주문 정산금액 계산 시 필요필드
function get_commission_info_field(){

	return array('commission_type','commission_rate','price','consumer_price','target_price','ea','provider_seq','event_sale','salescost_provider','pay_price');
}

	
/* 
** 주문 시 정산수수료, 정산금액 계산
* @20190520 pjm
1. 수수료방식(SACO)
	① 정산대상금액 : 결제금액 + 입점사부담할인액(이벤트+쿠폰+코드 등)
	② 정산  수수료 : round(정산대상금액 * 수수료율)
	③ 정 산  금 액 : 정산대상금액 - 정산수수료(view단에서 처리)
2. 공급가방식(SUPR)
	① 정산대상금액 : 공급가액
	② 정 산  금 액 : round(정산대상금액 - 입점사부담할인액(이벤트+쿠폰+코드 등))
	③ 정산  수수료 : 결제금액 - 정산금액
3. 공급율방식(SUCO)
	① 정산대상금액 : 정가 - 입점사부담할인액(이벤트+쿠폰+코드 등)
	② 정 산  금 액 : round(정산대상금액 * 공급율(소숫점 첫째자리에서 반올림))
	③ 정산  수수료 : 결제금액 - 정산금액
*/
function get_commission($commission_info=array()){

	$commission_type		= $commission_info['commission_type'];			//정산타입(SACO:수수료방식/SUPR:공급가)
	$commission_rate		= $commission_info['commission_rate'];			//정산수수료율,공급가,공급율
	$consumer_price			= $commission_info['consumer_price'];			//정가
	$price					= $commission_info['price'];					//판매가
	$target_price			= $commission_info['target_price'];				//정산대상금액
	$pay_price				= $commission_info['pay_price'];				//결제금액
	$ea						= $commission_info['ea'];						//주문수량
	$salescost_provider		= $commission_info['salescost_provider'];		//입점사할인부담금(개당)

	$acc_charge_str			= "";											// 정산계산식 text

	$target_unit_price		= $target_price;								//(신)정산 정산대상금액 = 판매가 - 입점사할인부담금액 적용
	$target_price			= $target_price * $ea;
	$old_target_price		= $price;										//(구)정산 정산대상금액 = 판매가

	if(is_array($salescost_provider)){
		$salescost_provider_sum		= array_sum($salescost_provider);

		//구정산은 할인이벤트 적용안함.(기존 정산시스템 유지)
		unset($salescost_provider['event']);
		$old_salescost_provider_sum	= array_sum($salescost_provider);

	}else{
		$salescost_provider_sum		= $salescost_provider;
		$old_salescost_provider_sum	= $salescost_provider;
	}

	//수수료 방식 정산
	if($commission_type == 'SACO' || $commission_type == ''){

		if($target_unit_price) {

			$priceTmp					= $target_price;
			$acc_charge_str				= $commission_rate."%";

			$feeprice_unit				= $target_unit_price * ($commission_rate) / 100;		//정산수수료금액(개당)
			$feeprice					= ROUND($feeprice_unit * $ea);							//정산수수료금액(소수점 첫째자리에서 반올림)
			$commission_unit_price		= $target_unit_price - $feeprice_unit;					//정산대상금액(개당)
			$commission_price			= $target_price - $feeprice;							//정산대상금액 - 수수료금액
			$feeprice_rest				= 0;													//정산수수료금액 개당 짜투리
		}

		if($old_target_price) {		
			$old_feeprice_unit			= $old_target_price * ($commission_rate) / 100;			//(구)정산수수료금액(개당)
			$old_commission_unit_price	= $old_target_price - ROUND($old_feeprice_unit) - $old_salescost_provider_sum;	//(구)정산대상금액 - 수수료금액 - 입점사할인부담금
		}

	// 공급가 정산
	}elseif($commission_type == 'SUPR'){					

		$target_unit_price			= $commission_rate;											//정산대상금액 = 공급가
		$target_price				= $target_unit_price * $ea;									
			
		$commission_unit_price		= $target_unit_price - $salescost_provider_sum;				// 정산금액(개당) = 공급가 - 입점사할인부담금
		$commission_price			= ROUND($commission_unit_price * $ea);						// 총정산금액(소수점 첫째자리 반올림)	
		$feeprice_unit				= $pay_price - $commission_unit_price;
		$feeprice					= ($pay_price  * $ea) - $commission_price;

		$old_commission_unit_price	= $commission_unit_price - $old_salescost_provider_sum;	//(구)정산대상금액

	// 공급율 정산
	}elseif($commission_type == 'SUCO'){

		$target_unit_price			= ROUND($consumer_price * $commission_rate /100);			//정산대상금액 : 정가 * 공급율 / 100
		$target_price				= $target_unit_price * $ea;

		$commission_unit_price		= $target_unit_price  - $salescost_provider_sum;			// 정산금액 = 정산대상금액 - 입점사할인부담금
		$commission_price			= ROUND($commission_unit_price * $ea);						// 총정산금액(소수점 첫째자리 반올림)	
		$feeprice_unit				= $pay_price - $commission_unit_price;						// 수수료 = 결제금액 - 정산금액
		$feeprice					= ($pay_price  * $ea) - $commission_price;

		$old_commission_unit_price	= $commission_unit_price - $old_salescost_provider_sum;	//(구)정산대상금액

	}

	$sales_unit_minfee			= 0;															//정산추가수수료(개당)
	$commission_text			= $target_unit_price.
									"-".$feeprice_unit.
									"-".$sales_unit_minfee.
									"=".$commission_unit_price." ".$acc_charge_str." ";			//정산계산식 상세설명

	//환율적용
	if($commission_unit_price > 0){
		$commission_unit_price_krw = get_currency_exchange($commission_unit_price,"KRW",$CI->config_system['basic_currency']);
	}else{
		$commission_unit_price_krw = 0;
	}
	if($old_commission_unit_price > 0){
		$old_commission_unit_price_krw = get_currency_exchange($old_commission_unit_price,"KRW",$CI->config_system['basic_currency']);
	}else{
		$old_commission_unit_price_krw = 0;
	}

	$return = array(
				'target_price'					=> $target_price,
				'target_unit_price'				=> $target_unit_price,
				'feeprice'						=> $feeprice,
				'feeprice_unit'					=> $feeprice_unit,
				'feeprice_rest'					=> $feeprice_rest,
				'commission_price'				=> $commission_price,
				'commission_unit_price'			=> $commission_unit_price,
				'commission_rate'				=> $commission_rate,
				'commission_price_rest'			=> $commission_price_rest,
				'commission_unit_price_krw'		=> $commission_unit_price_krw,
				'commission_text'				=> $commission_text,
				'old_commission_unit_price'		=> $old_commission_unit_price,
				'old_commission_unit_price_krw' => $old_commission_unit_price_krw,
				);

	return $return;

}


# 정산수수료율 재계산(할인이벤트에서 정산수수료율 조정 가능)
function reset_commission_rate($commission_info=array(),$event=array()){
	
	$commission_type	= $commission_info['commission_type'];			//정산타입(SACO:수수료방식/SUPR:공급가)
	$commission_rate	= $commission_info['commission_rate'];			//정산수수료
	$provider_seq		= $commission_info['provider_seq'];				//입점사번호

	$acc_charge_str		= "";		// 정산계산식 text

	//수수료 방식 정산
	if($commission_type == 'SACO' || $commission_type == ''){

		if ($provider_seq > 1){	// 입점사상품
			if($event['event_seq']) { // 이벤트 적용 시 조건에 따라 입점사 정산 수수료 조정
				switch ($event['saller_rate_type']) {
					case 1:
						//안쓰는 듯(신정산 이전 방식인듯)
						$commission_rate = $event['saller_rate'];
					break;
					case 2:
						//안쓰는 듯(신정산 이전 방식인듯)
						$commission_rate =  $commission_rate + $event['saller_rate'];
					break;
					case 0:
						$commission_rate = get_rate_recalculate($event['rate_type_saco'],$commission_rate,$event['saco_value']);  //수수료 조정
					break;
				}
			}
		}else{
			$commission_rate = 100;	//본사상품
		}


	//공급가 방식 정산
	}else{
		if($commission_type == 'SUPR'){					
			// 공급가 정산
			$commission_rate	= get_rate_recalculate($event['rate_type_supr'],$commission_rate,$event['supr_value']);  //수수료 조정
		}else{
			// 공급율 정산
			$commission_rate	= get_rate_recalculate($event['rate_type_suco'],$commission_rate,$event['suco_value']);  //수수료 조정
		}
	}

	return $commission_rate;
}

function get_rate_recalculate($rate_type = 'equal', $commission_rate='',$cus_rate=''){

	if($rate_type == 'ignore'){			// 기존 수수료 무시하고 (%) 적용	
		$commission_rate = $cus_rate;
	}else if($rate_type == 'plus'){		// 기존 수수료에 +(%) 적용
		$commission_rate =  $commission_rate + $cus_rate;
	}else if($rate_type == 'minus'){		// 기존 수수료에 -(%) 적용
		$commission_rate =  $commission_rate - $cus_rate;
	}else{
		$commission_rate ;
	}

	return $commission_rate;
}



/**
 * 정산 검산툴 상세 내역 그리기
 * @param type $checker_diff
 * @return string
 */
function drawAccountallTool($checker_diff, $checker_tool_view_succ=0, $accountData=array()){
	$CI =& get_instance();
	
	$checker_result_html = "";
	$base_result = array();
	$checker_result = array();
	$origin_result = array();
	$account_ea_result = array();
	$success_result = array();

	$base_result[] = $checker_diff['base_result'];
	$checker_result[] = $checker_diff['checker_result'];
	$origin_result[] = $checker_diff['origin_result'];
	$account_ea_result[] = $checker_diff['account_ea_result'];
	$success_result[] = $checker_diff['success_result'];
	$origin_info = $checker_diff['origin_info'];
	$not_show_flag = $checker_diff['not_show_flag'];
	
	
	if($checker_result[0]){
		$checker_flag = '10'; // 오류
		$checker_flag_txt = '오류';
		$checker_flag_class = 'red';
		
		if(empty($origin_info)){
			$checker_flag = '90'; // 매칭데이터 없음
			$checker_flag_txt = '없음';
			$checker_flag_class = 'black';
		}
	}elseif(empty($base_result[0]) || empty($origin_info)){
		$checker_flag = '90'; // 매칭데이터 없음
		$checker_flag_txt = '없음';
		$checker_flag_class = 'black';
		
		$tmp_base_result = array();
		$arr_base_info = $CI->accountallmodel->arr_base_info;
		foreach($arr_base_info as $base_info){
			$tmp_base_result[$base_info] = $accountData[$base_info];
		}
		
		$base_result[] = $tmp_base_result;
	}else{
		$checker_flag = '00'; // 정산
		$checker_flag_txt = '정상';
		$checker_flag_class = '';
	}
	
	if(!in_array($checker_flag, $not_show_flag)){
		foreach($base_result as $k=>$base_info){
			$order_seq_html = '';
			$base_info_html = '';
			$account_ea_html = '';
			$checker_html = '';
			$origin_html = '';
			$success_html = '';
			$tmp_modify_html['error'] = '';
			$tmp_modify_html['succ'] = '';
			foreach($base_info as $key=>$value){
				$base_info_html .= '['.$key.'] : '.$value.'';
				$base_info_html .= '
					<input 
						type="hidden" 
						class="base_info"
						name="'.$key.'" 
						value="'.$value.'"
					/>
				';
				$base_info_html .= '<br/>';
			}
			foreach($checker_result[$k] as $key=>$value){
				$checker_html .= '['.$key.'] : '.$value.'<br/>';
			}
			foreach($origin_result[$k] as $key=>$value){
				$origin_html .= '['.$key.'] : '.$value.'<br/>';
			}
			foreach($success_result[$k] as $key=>$value){
				$success_html .= '['.$key.'] : '.$value.'<br/>';
			}
			foreach($account_ea_result[$k] as $key=>$value){
				$account_ea_html .= '['.$key.'] : '.$value.'<br/>';
			}

			if($checker_flag == '00'){
				$detail_html = '<td colspan="2">';
				if($checker_tool_view_succ){
					$detail_html .= '
						<button 
							type="button" 
							class="active_accountool_attr_list" 
						>보기</button>
						<div class="area_list_modify_accountool" style="display:none;">
						'.$success_html.'
						</div>
					';
					$modify_html = drawAccounallToolBtnModify($success_result[$k]);
				}
				$detail_html .= '</td>';
			}elseif($checker_flag == '10'){
				$detail_html = '
						<td>
							<button 
								type="button" 
								class="active_accountool_attr_list" 
							>보기</button>
							<div class="area_list_modify_accountool" style="display:none;">
							'.$checker_html.'
							</div>
						</td>
						<td>
							<button 
								type="button" 
								class="active_accountool_attr_list" 
							>보기</button>
							<div class="area_list_modify_accountool" style="display:none;">
							'.$origin_html.'
							</div>
						</td>
				';
				$modify_html = drawAccounallToolBtnModify($origin_result[$k]);
			}
			
			$checker_result_html .= '
				<tr>
					<td class="base_info_list">
						<span class="btn small '.$checker_flag_class.'">
							<button type="button"
							>
								'.$checker_flag_txt.'
							</button>
						</span><br/>
						'.$base_info_html.'
					</td>
					<td>
						<button 
							type="button" 
							class="active_accountool_attr_list" 
						>보기</button>
						<div class="area_list_modify_accountool" style="display:none;">
						'.$account_ea_html.'
						</div>
					</td>
					'.$detail_html.'
					<td>'.$modify_html.'</td>
				</tr>
			';
		}
	}
	
	$ignore_checker_list = 'accountallmodel->checker_diff 에서 사용함<br/><br/>';
	foreach($CI->accountallmodel->arr_checker_equation as $checker_equation_k=>$checker_equation_v){
		$ignore_checker_list .= '
			['.$checker_equation_v['title'].']<br/>
			=======================================<br/>
			'.$checker_equation_v['desc'].'<br/>
			=======================================<br/>
			'.$checker_equation_v['equation'].'<br/>
			=======================================<br/><br/><br/>
		';
	}
	
	
	$table_desc = '
			<tr>
				<td>체크 예외 목록</td>
				<td colspan="4">
					<button 
						type="button" 
						class="active_accountool_attr_list" 
					>보기</button>
					<div class="area_list_modify_accountool" style="display:none;font-size: 12px;">
						'.$ignore_checker_list.'
					</div>
				</td>
			</tr>
			<tr>
				<td>검산툴 결과 설명</td>
				<td colspan="4">
					<button 
						type="button" 
						class="active_accountool_attr_list" 
					>보기</button>
					<div class="area_list_modify_accountool" style="display:none;">
						<table border="1">
							<col width="10%">
							<col width="10%">
							<col width="10%">
							<col width="20%">
							<col width="50%">
							<tr>
								<td colspan="4" style="font-size: 14px;color:red;">오류 목록</td>
							</tr>
							<tr>
								<td>이월여부</td>
								<td>정산상태</td>
								<td>주문종류</td>
								<td>필드</td>
								<td>설명</td>
							</tr>
							<tr>
								<td>-</td>
								<td>-</td>
								<td>-</td>
								<td>
									sales_basic<br/>
									optioncode1<br/>
									api_pg_add1<br/>
									api_pg_add2
								</td>
								<td>accountall_helper->ins_calculate_ck 에서 변수명이 바뀌면서 값을 정상적으로 할당하지 못 함.</td>
							</tr>
							<tr>
								<td>-</td>
								<td>-</td>
								<td>환불</td>
								<td>coupon_remain_value</td>
								<td>환불데이터 생성 시 coupon_remain_value의 값을 매출/정산테이블에서 필드명으로 처리하지 못 함</td>
							</tr>
							<tr>
								<td>-</td>
								<td>-</td>
								<td>반품배송비</td>
								<td>shipping_provider_seq</td>
								<td>본사위탁배송의 경우 반품배송비를 입력할때 첫 상품의 배송그룹 정보를 입력하도록 잘못 구성되어있음</td>
							</tr>
							<tr>
								<td>-</td>
								<td>정산확정</td>
								<td>주문</td>
								<td>ac_ea</td>
								<td>구매확정과 교환/반품/환불에 의해 ac_ea값이 정상적이지 않음.</td>
							</tr>
							<tr>
								<td>-</td>
								<td>정산확정</td>
								<td>배송비</td>
								<td>ac_ea</td>
								<td>배송비 정산이 완료됬을 시 ac_ea를 0으로 업데이트해야하나 변경되지 않음.</td>
							</tr>
							<tr>
								<td>-</td>
								<td>정산확정</td>
								<td>교환</td>
								<td>exp_ea</td>
								<td>실제 정산수량이 아닌 exp_ea가 수량으로 설정되어 있음.</td>
							</tr>
							<tr>
								<td colspan="5"><br/><br/></td>
							</tr>
							<tr>
								<td colspan="5" style="font-size: 14px;color:blue;">예외 목록</td>
							</tr>
							<tr>
								<td>이월여부</td>
								<td>정산상태</td>
								<td>주문종류</td>
								<td>필드</td>
								<td>설명</td>
							</tr>
							<tr>
								<td>-</td>
								<td>-</td>
								<td>-</td>
								<td>-</td>
								<td>이벤트로 인해 수수료율이 변경될 경우 검산툴은 이벤트 정보를 참조하지 못 하므로 검산데이터 에러 발생</td>
							</tr>
							<tr>
								<td>-</td>
								<td>-</td>
								<td>교환</td>
								<td>-</td>
								<td>기존 교환 주문의 경우 account_type를 order로 입력하고 있으므로 매칭되는 값 없음</td>
							</tr>
							<tr>
								<td>-</td>
								<td>-</td>
								<td>반품배송비</td>
								<td>confirm_date</td>
								<td>반품배송비를 정산확정시 confirm_date에 넣는것이 아닌 반품이 완료될때 넣으므로 정산확정이 이루어지기 전에는 오차가 있음</td>
							</tr>
							<tr>
								<td>-</td>
								<td>되돌리기</td>
								<td>주문</td>
								<td>-</td>
								<td>결제확인 후 되돌리기로 인한 필수값(deposit_date) 변경으로 검산데이터 누락 발생</td>
							</tr>
							<tr>
								<td>-</td>
								<td>결제취소</td>
								<td>환불</td>
								<td>confirm_date</td>
								<td>결제취소의 경우 confirm_date를 입력하지 않음</td>
							</tr>
							<tr>
								<td>-</td>
								<td>정산예정</td>
								<td>주문</td>
								<td>exp_ea,ac_ea</td>
								<td>정산예정 건의 경우 정산수량 업데이트 기능이 제거 되었으므로 진행상황에 따라 정산수량이 차이남</td>
							</tr>
							<tr>
								<td>-</td>
								<td>정산예정</td>
								<td>교환</td>
								<td>exp_ea,ac_ea</td>
								<td>교환주문은 정산하지 않으므로 오차 발생</td>
							</tr>
							<tr>
								<td>-</td>
								<td>정산확정</td>
								<td>배송비</td>
								<td>confirm_date</td>
								<td>모든 출고를 체크한 후 동작되므로 시 실제 정산확정일과 차이가 있을 수 있음.</td>
							</tr>
							<tr>
								<td>-</td>
								<td>정산확정</td>
								<td>교환</td>
								<td>confirm_date</td>
								<td>원주문이 정산확정 될 때 자식주문이 모두 정산확정 되므로 정산확정일과 차이가 있을 수 있음.</td>
							</tr>
							<tr>
								<td>이월</td>
								<td>정산확정</td>
								<td>-</td>
								<td>status, confirm_date, ac_ea</td>
								<td>조회월에 처리되지 않고 이월되서 정산확정됬을 경우, 검산데이터는 현재상태를 기준으로 구성되므로 과거 데이터와 오차가 있음.</td>
							</tr>
						</table>
					</div>
				</td>
			</tr>	
	';
	// 불필요한 설명 문구 제거
	$table_desc = '';
	
	$table_header = '
		<table border="1" style="width:100%;font-size: 16px;">
			<col width="20%">
			<col width="20%">
			<col width="20%">
			<col width="20%">
			<col width="20%">
			'.$table_desc.'
			<tr>
				<td>기준데이터</td>
				<td>정산확정데이터</td>
				<td>검산데이터</td>
				<td>정산데이터</td>
				<td>수정</td>
			</tr>
	';
	$table_footer = '
		</table>
	';
	
	$account_tool_script = '
	<script type="text/javascript" >
	//if(typeof(account_tool) === "undefined"){
	//	var account_tool = true;
		$(document).ready(function() {
			// 검산툴 상세보기
			$(".checker_tool_detail").unbind("click");
			$(".checker_tool_detail").bind("click", function(){
				openDialog("정산 상세 정보", "checker_tool_detail_"+$(this).data("key"), {"width":1200}); 
			});

			// 영역 상세보기
			$(".active_accountool_attr_list").unbind("click");
			$(".active_accountool_attr_list").bind("click", function(){
				var active_accountool_attr_list			= $(this);
				var area_list_modify_accountool			= active_accountool_attr_list.siblings(".area_list_modify_accountool");
				
				area_list_modify_accountool.toggle();
			});
			
			// 강제 정산마감처리
			$(".exec_accountconfirm").unbind("click");
			$(".exec_accountconfirm").bind("click", function(){
				var exec_accountconfirm			= $(this);
				var base_info_list				= exec_accountconfirm.parent().siblings(".base_info_list");
				var order_seq					= base_info_list.find("input[name=order_seq]").val();

				$.ajax({
					"type" : "post"
					, "url"  : "/admin/accountall/accountall_confirm"
					, "data" : {
						"order_seq" : order_seq
					}
					, "dataType" : "json"
					, "success" : function(result){
						alert("정산데이터 마감이 완료되었습니다.\n새로고침 후 확인해주세요.");
					}
					, "error" : function(){
						alert("정산데이터 마감에 실패했습니다.");
					}
				});
			});
			
			// 정산데이터 수정 영역 활성화
			$(".btn_modify_accountall").unbind("click");
			$(".btn_modify_accountall").bind("click", function(){
				var btn_modify_accountall				= $(this);
				var key_modify_column					= btn_modify_accountall.data("key");
				var mode								= btn_modify_accountall.data("mode");
				if(mode=="exec"){
					btn_modify_accountall				= btn_modify_accountall.parent().siblings(".btn_modify_accountall");
				}
				var obj_modify_column					= btn_modify_accountall.parent().children("input[name=\'"+key_modify_column+"\']");
				var modify_column						= new Object();
				modify_column[key_modify_column]		= obj_modify_column.val();

				var base_info_list						= btn_modify_accountall.parent().parent().parent().siblings(".base_info_list");
				var obj_base_info						= base_info_list.children(".base_info");
				var base_info							= new Object();
				obj_base_info.each(function(){
					base_info[$(this).attr("name")] = $(this).val();
				});
				
				var sql_info							= btn_modify_accountall.parent().children("."+key_modify_column+"_info");
				var sql_info_query						= sql_info.children("textarea[name=\'"+key_modify_column+"_query\']");
							
				if(typeof(base_info) !== "undefined" && typeof(modify_column) !== "undefined"){
					var update_sql = "";
					var select_sql = "";

					$.ajax({
						"type" : "post"
						, "url"  : "/admin/accountall/accountall_modify"
						, "data" : {
							"mode" : mode
							, "base_info" : base_info
							, "modify_column" : modify_column
							, "view_Ym" : $("select[name=\'s_year\']").val()+""+$("select[name=\'s_month\']").val()
						}
						, "dataType" : "json"
						, "success" : function(result){
							if(mode == "select_sql"){
								var result_sql = result["sql"];
								var result_code = result_sql["result"];
								update_sql = result_sql["update"];
								select_sql = result_sql["select"];
								sql_info_query.val(update_sql+"\n\n"+select_sql);

								var confirm_info_txt = "정산데이터 수정을 위한 쿼리를 한번 더 확인해주시기 바랍니다.";
								var confirm_txt = confirm_info_txt;
								if(result_code=="00"){
									alert(confirm_txt);
									sql_info.show();
								}else{
									alert("정산데이터 수정 쿼리 조합에 실패했습니다.");
								}
							}else if(mode == "exec"){
								var result_sql = result["sql"];
								var result_code = result_sql["result"];
								var exec_sql = result_sql["exec"];
								sql_info_query.val(exec_sql);
								
								if(result_code=="00"){
									alert("정산데이터 수정이 완료되었습니다.\n새로고침 후 확인해주세요.");
									// window.location.href=window.location.href;
								}else{
									alert("정산데이터 수정에 실패했습니다.\n실행 쿼리를 확인해주세요.");
								}
							}
						}
						, "error" : function(){
							alert("정산데이터 수정 쿼리 조합에 실패했습니다.");
						}
					});
				}
			});
		});
	//}
	</script>
	';
	
	$html = $table_header.$checker_result_html.$table_footer;
		
	$drawResult['checker_flag']				= $checker_flag;
	$drawResult['checker_flag_txt']			= $checker_flag_txt;
	$drawResult['checker_flag_class']		= $checker_flag_class;
	
	$drawResult['table_header']				= $table_header;
	$drawResult['table_body']				= $checker_result_html;
	$drawResult['table_footer']				= $table_footer;
	$drawResult['table_all']				= $html;
	
	$drawResult['account_tool_script']		= $account_tool_script;
	return $drawResult;
}

/**
 * 검산 수정 버튼
 */
function drawAccounallToolBtnModify($attr_list){
	
	foreach($attr_list as $key=>$value){
		$attr_html .= '
			<span>
				====================<br/>
				['.$key.']
				<button 
					type="button" 
					class="btn_modify_accountall" 
					data-key="'.$key.'"
					data-mode="select_sql"
				>쿼리조합</button> <br/>
				<input name="'.$key.'" value="'.$value.'"/>
				<div class="'.$key.'_info" style="display:none;">
					<button 
						type="button" 
						class="btn_modify_accountall" 
						data-key="'.$key.'"
						data-mode="exec"
					>쿼리실행</button> <br/>
					<textarea name="'.$key.'_query">
					</textarea>
				</div>
			</span><br/>
		';
	}
	
	$modify_html .= '
		<button 
			type="button" 
			class="active_accountool_attr_list" 
		>보기</button>
		<button 
			type="button" 
			class="exec_accountconfirm" 
		>강제정산처리</button>
		<div class="area_list_modify_accountool" style="display:none;">
		'.$attr_html.'
		</div>
	';
	return $modify_html;
}
/**
 * 통계를 위한 할인정보 정리
 */
function set_stat_discount_sale($pre_fix, $acViewData, &$statsData){

	// ====================================================================================================================================================
	// 할인 정보 가공 시작
	// ====================================================================================================================================================		
	$arr_discount_type = array(
		'emoney'	
		, 'enuri'	
		, 'member'	
		, 'mobile'	
		, 'fblike'	
		, 'event'	
		, 'referer'	
		, 'coupon'	
		, 'code'	
		, 'multi'	
	);

	foreach($arr_discount_type as $discount_type){
		$key_discount_type = $discount_type;
		if($discount_type=='emoney'){
			$key_discount_type = $discount_type.'_use';
		}elseif($discount_type=='enuri'){
		}elseif($discount_type=='code'){
			$key_discount_type = 'promotion_'.$discount_type.'_sale';
		}else{
			$key_discount_type = $discount_type.'_sale';
		}
		$statsData[$pre_fix.''.$key_discount_type.'_sum']			+= ($acViewData[$discount_type.'_sale_unit'] * $acViewData['ea']) + $acViewData[$discount_type.'_sale_rest'];	// 할인 가산		            
	}
	$api_pg_sale = '0';
	if(in_array($acViewData['order_referer'], array('storefarm', 'open11st', 'coupang'))){
		$api_pg_sale = $acViewData['api_pg_sale_price'];
		if(in_array($acViewData['order_referer'], array('open11st', 'coupang'))){
			$api_pg_sale += $acViewData['api_pg_support_price'];
		}
	}
	$statsData[$pre_fix.'api_pg_sale_sum']					+= $api_pg_sale;				
	$statsData[$pre_fix.'npay_sale_seller_sum']				+= $acViewData['npay_sale_seller'];	    
	$statsData[$pre_fix.'npay_sale_npay_sum']				+= $acViewData['npay_sale_npay'];	
	// ====================================================================================================================================================
	// 할인 정보 가공 종료
	// ====================================================================================================================================================		
}



function display_price_minus($base_price, $minus){
	$result = number_format($base_price);
	if($base_price > 0){
		$result = $minus.$result;
	}
	return $result;
}
function draw_excel_accountall($caller='admin', $mode = null, $type = null, $acinsdata = array(), $index = 0, $checkloopcnt){
	$CI =& get_instance();

	$html['header'] = '
		<html xmlns:v=\'urn:schemas-microsoft-com:vml\'
			xmlns:o=\'urn:schemas-microsoft-com:office:office\'
			xmlns:x=\'urn:schemas-microsoft-com:office:excel\'
			xmlns=\'http://www.w3.org/TR/REC-html40\'>
			<head>
			<!--[if gte mso 9]>
			<xml>
			 <x:ExcelWorkbook>
			  <x:ExcelWorksheets>
			   <x:ExcelWorksheet>
				<x:Name></x:Name>
				<x:WorksheetOptions>
				 <x:DefaultRowHeight>270</x:DefaultRowHeight>
				 <x:Selected/>
				 <x:DoNotDisplayGridlines/>
				 <x:ProtectContents>False</x:ProtectContents>
				 <x:ProtectObjects>False</x:ProtectObjects>
				 <x:ProtectScenarios>False</x:ProtectScenarios>
				</x:WorksheetOptions>
			   </x:ExcelWorksheet>
			  </x:ExcelWorksheets>
			  <x:WindowHeight>12825</x:WindowHeight>
			  <x:WindowWidth>18945</x:WindowWidth>
			  <x:WindowTopX>120</x:WindowTopX>
			  <x:WindowTopY>30</x:WindowTopY>
			  <x:ProtectStructure>False</x:ProtectStructure>
			  <x:ProtectWindows>False</x:ProtectWindows>
			 </x:ExcelWorkbook>
			</xml>
			<![endif]-->
		<style>
			td {text-align:center;}
			.title {background-color:#efefef;}
			.red {color:red}
			.number {text-align:right; mso-number-format:"@";}
			.left {text-align:left;}
			.center {text-align:center;mso-number-format:"@";}
			.right {text-align:right;}
			
			.accoun-table {position:relative; overflow:hidden; font-size:1em;}
			.accoun-table, .accoun-table *, .accoun-table *:before, .accoun-table *:after {box-sizing:border-box;}
			.account-table-grid-header {overflow-x:hidden; overflow-y:scroll; -webkit-user-select:none; -khtml-user-select:none; -moz-user-select:none; -ms-user-select:none; -o-user-select:none; user-select:none;}
			.account-table-grid-body {overflow-x:auto; overflow-y:scroll; -webkit-overflow-scrolling:touch;}
			.account-table-header-scrollbar {scrollbar-arrow-color:#f1f1f1; scrollbar-base-color:#f1f1f1; scrollbar-3dlight-color:#f1f1f1; scrollbar-highlight-color:#f1f1f1; scrollbar-track-color:#f1f1f1; scrollbar-shadow-color:#f1f1f1; scrollbar-dark-shadow-color:#f1f1f1;}
			.account-table-header-scrollbar::-webkit-scrollbar {visibility:hidden;}
			.account-table-header-scrollbar::-webkit-scrollbar-track {background:#f1f1f1;}
			.account-table-header-sortable:hover {cursor:pointer; background:#fcfcfc;}

			.rate {text-align:right;word-break:break-all;width:50px;}
			.out_ea {text-align:right;word-break:break-all;width:50px;}
			.price {text-align:right;word-break:break-all;width:70px;}

			.mso_number_format{mso-number-format:\'@\';}	
			
			/* 정산 - 테이블 */
			#account_table {padding:0 0;}
			.rate {text-align:right;word-break:break-all;width:50px;}
			.ea {text-align:right;word-break:break-all;width:50px;}
			.price {text-align:right;word-break:break-all;width:70px;padding-right:10px;}

			.calc-table-style {table-layout: fixed; border-collapse:separate; border-top:1px solid #aaa; border-right:1px solid #dadada; mso-number-format:"@";}
			.calc-table-style caption {visibility:hidden; width:0px; height:0px; overflow:hidden; font-size:0; line-height:0;}
			.calc-table-style thead th {border-left:1px solid #dadada; border-bottom:1px solid #dadada; padding:8px 0px; background-color:#f1f1f1; font-weight:normal;}
			.calc-table-style tbody th {border-left:1px solid #dadada; border-bottom:1px solid #dadada; padding:5px; background-color:#f4f4f4; text-align:center; font-weight:normal;}
			.calc-table-style tbody td, .calc-table-style tfoot td {border-left:1px solid #dadada; border-bottom:1px solid #dadada; padding:5px; min-height:20px; text-align:center; line-height:180%; word-break:break-all;}
			.calc-table-style tbody td a {color:#333;}
			.calc-table-style tbody td a:hover {text-decoration:underline;}
			.calc-table-style tbody td.nodata {height:40px;}
			.calc-table-style tbody td.refund {color:#f00;}
			.calc-table-style tbody tr.sum td, .calc-table-style tfoot tr.sum td {background:#f4f4f4; font-weight:bold;}
			.calc-table-style tbody tr.total_sum td {background:#f2f4f8; font-weight:bold;}

			/* 기본 정보 테이블 스타일 */
			.its_tr_carryover_not td, 
			.its_tr_carryover_not a {background-color:#fafafa; color:#aaa;}
			.its_tr_looptotal td, .its_tr_alltotal td {height:40px; background-color:#f4f4f4; font-weight:bold;}
			.its_tr_loopname td {background-color:#ffffff; border:none;}
			.its_tr_looptotal th, .its_tr_alltotal th {background-color:#f4f4f4; font-weight:normal;}

			.calc-table-style .acc_total_bg {background:#2080d0; text-align:right; color:#fff;}
			.calc-table-style .acc_bg {background:rgba(32,128,208,0.02);}
			.calc-table-style .its_tr_looptotal .acc_bg {background:rgba(32,128,208,0.2);}
			.calc-table-style .acc_top {border-top:2px solid #2080d0;}
			.calc-table-style .acc_left {border-left:2px solid #2080d0; !important;}
			.calc-table-style .acc_bottom {border-bottom:2px solid #2080d0;}
			.calc-table-style .acc_right {border-right:2px solid #2080d0; !important;}
			.calc-table-style .acc_name {background:#2080d0; border:2px solid #2080d0; text-align:center; color:#fff; font-weight:bold;}

			.calc-table-style .cal_total_bg {background:#ff4040; text-align:right; color:#fff;}
			.calc-table-style .cal_bg {background:rgba(255,64,64,0.02);}
			.calc-table-style .cal_no_acc_bg {background:rgb(238,238,238);}
			.calc-table-style .its_tr_looptotal .cal_bg {background:rgba(255,64,64,0.2);}
			.calc-table-style .cal_top {border-top:2px solid #ff4040;}
			.calc-table-style .cal_left {border-left:2px solid #ff4040; !important;}
			.calc-table-style .cal_bottom {border-bottom:2px solid #ff4040;}
			.calc-table-style .cal_right {border-right:2px solid #ff4040; !important;}
			.calc-table-style .cal_name {background:#ff4040; border:2px solid #ff4040; text-align:center; color:#fff; font-weight:bold;}

			.calc-table-style .profit_total_bg {background:#1DDB16; text-align:right; color:#fff;}
			.calc-table-style .profit_bg {background:rgba(29,219,22,0.1);}
			.calc-table-style .its_tr_looptotal .profit_bg {background:rgba(29,219,22,0.5);}
			.calc-table-style .profit_top {border-top:2px solid #1DDB16;}
			.calc-table-style .profit_left {border-left:2px solid #1DDB16; !important;}
			.calc-table-style .profit_bottom {border-bottom:2px solid #1DDB16;}
			.calc-table-style .profit_right {border-right:2px solid #1DDB16; !important;}
			.calc-table-style .profit_name {background:#1DDB16; border:2px solid #1DDB16; text-align:center; color:#fff; font-weight:bold;}

			.ui-widget {font-family:\'Dotum\', sans-serif;}
			.account_guide {margin-top:30px; border:1px solid #e0e0e0; background:#f4f4f4; padding:20px;}
			.account_guide li {font-size:12px; line-height:25px;}

			.icon_excel {display:inline-block; background:url(\'../images/common/btn_img_ex.gif\') no-repeat; width:10px; height:10px;}

				
			table.info-table-style .its-td-align {padding:5px;}

			#page-title-bar-area #page-title-bar .page-buttons-right.btn-small {padding-top:14px;}

			.large input, .large button {padding:0 15px; /*font-weight:normal;*/}
		</style>
		</head>
		<body>
			<table width="100%" class="calc-table-style" cellpadding="0" cellspacing="0">
				<!-- 테이블 헤더 : 시작 -->
				<colgroup>
					<col width="80" /><!--순번-->
					<col width="100" /><!--발 생 일<br />(결제/취소/환불)-->
					<col width="130" /><!--완 료 일<br />(구매확정/취소/환불일)-->
					<col width="160" /><!--주문/취소/환불 번호-->
					<col width="80" /><!--구매자-->
					<col width="100" /><!--입점사-->
					<col width="160" /><!--상품/배송비/반품배송비-->
					<col width="50" /><!--판매수량-->
					<col width="80" /><!--판매금액-->
					<col width="80" /><!--할인-본사-->
					<col width="80" /><!--할인-제휴사-->
					<col width="80" /><!--할인-입점사-->
					<col width="80" /><!--결제금액(A)-->
					<col width="80" /><!--실결제액-->
					<col width="80" /><!--예치금-->
					<col width="50" /><!--정산수량-->
					<col width="80" /><!--정산대상금액-->
					<col width="80" /><!--정산대상금액-결제금액-->
					<col width="80" /><!--정산대상금액-본사-->
					<col width="80" /><!--정산대상금액-제휴사-->
					<col width="80" /><!--공급금액-->
					<col width="80" /><!--수수료율-->
					<col width="80" /><!--수수료-->
					<col width="80" /><!--정산금액(B)-->
					'.( ($caller == 'admin') ? '
					<col width="80" /><!--이익금액(C)-(C)=(A)-(B)-->
					<col width="80" /><!--이익금액(C)-이익율-->
					' : '' ).'
					<col width="200" /><!--제휴사(PG)<br/>주문번호-->
					<col width="100" /><!--주문경로-->
					<col width="80" /><!--결제수단-->
				</colgroup>
				<thead>
					<tr>
						<th scope="col" rowspan="2">순번</th>
						<th scope="col" rowspan="2">발 생 일<br />(결제/취소/환불)</th>
						<th scope="col" rowspan="2">완 료 일<br />(구매확정/취소/환불)</th>
						<th scope="col" rowspan="2">주문/환불 번호</th>
						<th scope="col" rowspan="2">구매자</th>
						<th scope="col" rowspan="2">입점사</th>
						<th scope="col" rowspan="2">상품명/배송비/반품배송비</th>
						<th scope="col" rowspan="2">판매<br/>수량</th>
						<th scope="col" rowspan="2">판매금액</th>
						<th scope="col" colspan="3">할인</th>
						<th scope="col" colspan="3">결제금액(A)</th>
						<th scope="col" rowspan="2">정산<br/>수량</th>
						<th scope="col" colspan="4">정산대상금액</th>
						<th scope="col" rowspan="2">공급금액</th>
						<th scope="col" rowspan="2">수수료율</th>
						<th scope="col" rowspan="2">수수료</th>
						<th scope="col" rowspan="2">정산금액(B)</th>
						'.( ($caller == 'admin') ? '
							<th scope="col" colspan="2">이익금액(C)</th>
						' : '' ).'
						<th scope="col" rowspan="2">제휴사(PG)<br/>주문번호</th>
						<th scope="col" rowspan="2">주문경로</th>
						<th scope="col" rowspan="2">결제수단</th>
					</tr>
					<tr>
						<th scope="col">본사</th>
						<th scope="col">제휴사</th>
						<th scope="col">입점사</th>
						<th scope="col"></th>
						<th scope="col">실결제액</th>
						<th scope="col">예치금</th>
						<th scope="col"></th>
						<th scope="col">결제금액</th>
						<th scope="col">본사할인</th>
						<th scope="col">제휴사할인</th>
						'.( ($caller == 'admin') ? '
						<th scope="col">(C)=(A)-(B)</th>
						<th scope="col">이익율</th>
						' : '' ).'
					</tr>
				</thead>
				<tbody>
	';

	$html['footer'] = '
				</tbody>
			</table>
		</body>
		</html>
	';

	$its_tr_carryover_not = '';
	if($acinsdata['ac_type'] == 'cal_sales'){
		$its_tr_carryover_not = ' its_tr_carryover_not ';
	}

	$out_confirm_date = '';
	if($acinsdata['out_confirm_date']){
		$out_confirm_date = $acinsdata['out_confirm_date'].' ('.$acinsdata['out_step'].')';
	}

	$out_link = '';
	if($acinsdata['out_step'] == '환불완료'){
		$out_link = '<a href="../refund/view?no='.$acinsdata['refund_code'].'" target="_blank"><span class="order-step-color-'.$acinsdata['status'].'">'.$acinsdata['refund_code'].'</span></a>';
	}else{
		$out_link = '<a href="../order/view?no='.$acinsdata['order_seq'].'" target="_blank"><span class="order-step-color-'.$acinsdata['status'].'">'.$acinsdata['order_seq'].'</span></a>';
	}

	// order_referer에 따른 witdh 조정
	$width = array();
	$width[0] = '134';
	$width[1] = '78';
	$width[2] = '97';
	$width[3] = '138';
	if(in_array($get_order_referer, array('all', 'pg', 'npay'))){
		$width[3] = '134';
		if($get_order_referer == 'npay'){
			$width[0] = '134';
			$width[1] = '80';
		}
	}elseif($get_order_referer == 'shop'){
		$width[1] = '80';
		$width[2] = '100';
		$width[3] = '135';
	}

	$minus		= ($acinsdata['minus_sale']) ? '-' : '';
	$class_red	= ($acinsdata['minus_sale']) ? 'red' : '';
	$cal_top	= ($index==0)?'cal_top':'';
	$acc_top	= ($index==0)?'acc_top':'';

	$order_goods_name = ($acinsdata['out_order_goods_name']) ? $acinsdata['out_order_goods_name'] : $acinsdata['out_order_type'];
	$out_ea = ( ($acinsdata['out_ea'] > 0) ? $minus : '' ).$acinsdata['out_ea'];

	$arr_display_price_minus = array(
		'out_price',
		'out_salescost_admin',
		'out_pg_sale_price',
		'out_salescost_provider',
		'out_sale_price',
		'out_cash_use',
		'out_total_ac_price',
		'out_pg_default_price',
		'out_ac_salescost_admin',
		'out_ac_pg_price',
		'out_ac_consumer_real_price',
		'out_sales_unit_feeprice',
	);
	foreach($arr_display_price_minus as $base_price){
		${$base_price} = display_price_minus($acinsdata[$base_price], $minus);
	}
	$out_pay_price				= display_price_minus($acinsdata['out_sale_price'] + $acinsdata['out_cash_use'], $minus);
	
	// 정산 상태에 따른 표시 구분
	$arr_out_ac_acc_status = array(
		'out_total_ac_price',
		'out_pg_default_price',
		'out_ac_salescost_admin',
		'out_ac_pg_price',
		'out_ac_consumer_real_price',
		'out_sales_unit_feeprice',
	);
	if($acinsdata['out_ac_acc_status'] == 'no_acc'){
		foreach($arr_out_ac_acc_status as $base_price){
			${$base_price} = '';
		}
		$class_carry_out_ac_acc_status = $cal_top.' cal_no_acc_bg';
		$class_current_out_ac_acc_status = ( (empty($checkloopcnt)) ? $cal_top : '' ).' cal_no_acc_bg';

		$out_exp_ea							= '';
		$out_ac_fee_rate					= '';
		$title_out_sales_unit_feeprice		= '';
		$out_commission_price				= '';
	}elseif($acinsdata['out_ac_acc_status'] == 'ing_acc'){
		foreach($arr_out_ac_acc_status as $base_price){
			${$base_price} = '-';
		}
		$class_carry_out_ac_acc_status = $cal_top.' cal_bg';
		$class_current_out_ac_acc_status = ( (empty($checkloopcnt)) ? $cal_top : '' ).' cal_bg';
		$out_exp_ea							= '-';
		$out_ac_fee_rate					= '  -';
		$title_out_sales_unit_feeprice		= '';
		$out_commission_price				= '-';
	}else{
		$class_carry_out_ac_acc_status = $its_tr_carryover_not.' '.$class_red.' '.$cal_top.' cal_bg';
		$class_current_out_ac_acc_status = $class_red.' '.( (empty($checkloopcnt)) ? $cal_top : '' ).' cal_bg';
		$out_exp_ea							= $acinsdata['out_exp_ea'];
		$out_ac_fee_rate					= ( ($acinsdata['out_ac_fee_rate'] != 0) ? $minus.$acinsdata['out_ac_fee_rate'].'%' : '' );
		$title_out_sales_unit_feeprice		= 'title="('.$acinsdata['sales_unit_feeprice'].'*'.$acinsdata['out_ea'].')+'.$acinsdata['sales_unit_minfee'].'+'.$acinsdata['sales_feeprice_rest'].'"';
		$out_commission_price				= $minus.number_format($acinsdata['out_commission_price']);
	}

	$out_ac_profit_price = ( ($acinsdata['out_ac_profit_price'] && $acinsdata['out_ac_profit_price'] >= 0 ) ? $minus : '' );
	$out_ac_profit_price .= ( ($acinsdata['minus_sale'] && $acinsdata['out_ac_profit_price'] < 0) ? number_format(- $acinsdata['out_ac_profit_price']) : number_format($acinsdata['out_ac_profit_price']) );

	$out_ac_profit_rate = ( ($acinsdata['out_ac_profit_rate'] && $acinsdata['out_ac_profit_rate'] > 0 ) ? $minus : '' );
	if($acinsdata['out_ac_profit_rate'] != 0){
		$out_ac_profit_rate .= ( ($acinsdata['minus_sale'] && $acinsdata['out_ac_profit_rate'] < 0) ? - $acinsdata['out_ac_profit_rate'] : $acinsdata['out_ac_profit_rate'] ).'%';
	}

	$html['carryoverloop'] = '
		<tr>
			<td class="'.$its_tr_carryover_not.'">이월'.$acinsdata['out_num'].'<!-- /'.$acinsdata['seq'].' --><!--순번--></td>
			<td class="'.$its_tr_carryover_not.'"><!-- 정산(전월:\'carryover\', 당월:\'complete\'), 매출(차월:\'overdraw\', 당월:\'complete\') -->'.$acinsdata['out_deposit_date'].'</td>
			<td class="'.$its_tr_carryover_not.'">'.$out_confirm_date.'</td>
			<td class="'.$its_tr_carryover_not.'" style="mso-number-format:\'@\';" width="'.$width[0].'">'.$out_link.'</td>
			<td class="'.$its_tr_carryover_not.'" width="'.$width[1].'" >'.$acinsdata['order_user_name'].'</td>
			<td class="'.$its_tr_carryover_not.' left" width="'.$width[2].'" >'.$acinsdata['out_provider_name'].'</td>
			<td class="'.$its_tr_carryover_not.' left" width="'.$width[3].'" >
				<span alt="'.$acinsdata['order_goods_name'].'" title="'.$acinsdata['order_goods_name'].'">'.$order_goods_name.'</span>
			</td>
			<td class="'.$its_tr_carryover_not.'">'.$acinsdata['out_ea'].'<!-- 판매수량 --></td>
			<td class="'.$its_tr_carryover_not.' right '.$class_red.'">'.$out_price.'<!-- 판매금액 --></td>
			<td class="'.$its_tr_carryover_not.' right '.$class_red.'"><!-- 할인(본사) -->
			'.$out_salescost_admin.'
			</td>
			<td class="'.$its_tr_carryover_not.'  right  '.$class_red.'"><!-- 제휴사(무통장:0/pg) -->
			'.$out_pg_sale_price.'
			</td>
			<td class="'.$its_tr_carryover_not.' right '.$class_red.'"><!-- 할인(입점사) -->
			'.$out_salescost_provider.'
			</td>
			<td class="'.$its_tr_carryover_not.'  right  '.$class_red.'">'.$out_pay_price.'<!-- 결제금액(A) --></td>
			<td class="'.$its_tr_carryover_not.'  right  '.$class_red.'">'.$out_sale_price.'<!-- 실결제액 --></td>
			<td class="'.$its_tr_carryover_not.'  right  '.$class_red.'">'.$out_cash_use.'<!-- 이머니 --></td>
			<td class="'.$class_carry_out_ac_acc_status.' cal_left">'.$out_exp_ea.'
			<!-- 정산수량 --></td>
			<td class="right '.$class_carry_out_ac_acc_status.'">'.$out_total_ac_price.'
			<!-- 정산대상금액 --></td>
			<td class="right '.$class_carry_out_ac_acc_status.'">'.$out_pg_default_price.'
			<!-- 정산대상(수수료방식)-결제금액 --></td>
			<td class="right '.$class_carry_out_ac_acc_status.'">'.$out_ac_salescost_admin.'
			<!-- 정산대상(수수료방식)-본사할인 --></td>
			<td class="right '.$class_carry_out_ac_acc_status.'">'.$out_ac_pg_price.'
			<!-- 정산대상(수수료방식)-제휴사할인 --></td>
			<td class="right '.$class_carry_out_ac_acc_status.'">'.$out_ac_consumer_real_price.'
			<!-- 정산대상(공급가방식) --></td>
			<td class="right '.$class_carry_out_ac_acc_status.'">'.$out_ac_fee_rate.'
			<!-- 수수료율(%) --></td>
			<td class="right '.$class_carry_out_ac_acc_status.'" '.$title_out_sales_unit_feeprice.'>'.$out_sales_unit_feeprice.'
			<!-- 수수료 -->
			</td>
			<td class="right '.$class_carry_out_ac_acc_status.' cal_right">'.$out_commission_price.'
			<!-- 정산금액(B) -->
			</td>
			'.( ($caller == 'admin') ? '
			<td class="right '.$its_tr_carryover_not.' '.$class_red.' profit_bg">'.$out_ac_profit_price.'
			<!-- 이익금액(C)>(C)=(A)-(B) --></td>
			<td class="right '.$its_tr_carryover_not.' '.$class_red.'">'.$out_ac_profit_rate.'
			<!-- 이익금액(C)>이익율 --></td>
			' : '' ).'
			<td class="'.$its_tr_carryover_not.' left"  style=" mso-number-format:\'@\';" >'.$acinsdata['out_pg_ordernum'].'	<!-- <br/>'.$acinsdata['order_seq'].' --></td>
			<td class="'.$its_tr_carryover_not.'">'.$acinsdata['out_order_referer_viewer'].'</td>
			<td class="'.$its_tr_carryover_not.' ">'.$acinsdata['out_payment'].'</td>
		</tr>
	';


	$html['loop'] = '
		<tr>
			<td>당월'.$acinsdata['out_num'].'<!-- /'.$acinsdata['seq'].' --><!--순번--></td>
			<td><!-- 정산(전월:\'carryover\', 당월:\'complete\'), 매출(차월:\'overdraw\', 당월:\'complete\') -->'.$acinsdata['out_deposit_date'].'</td>
			<td>'.$out_confirm_date.'</td>
			<td style="mso-number-format:\'@\';" width="'.$width[0].'">'.$out_link.'</td>
			<td  width="'.$width[1].'" >'.$acinsdata['order_user_name'].'</td>
			<td class="left" width="'.$width[2].'" >'.$acinsdata['out_provider_name'].'</td>
			<td class="left" width="'.$width[3].'" >
				<span alt="'.$acinsdata['order_goods_name'].'" title="'.$acinsdata['order_goods_name'].'">'.$order_goods_name.'</span>
			</td>
			<td class="'.$class_red.' '.$acc_top.' acc_left">'.$out_ea.'<!-- 수량 --></td>
			<td nowrap class="right '.$class_red.' '.$acc_top.' acc_bg">'.$out_price.'<!-- 판매금액 --></td>
			<td class="right  '.$class_red.' '.$acc_top.' acc_bg">'.$out_salescost_admin.'<!-- 할인(본사) --></td>
			<td class="right  '.$class_red.' '.$acc_top.' acc_bg">'.$out_pg_sale_price.'<!-- 제휴사(무통장:0/pg) --></td>
			<td class="right  '.$class_red.' '.$acc_top.' acc_bg">'.$out_salescost_provider.'<!-- 할인(입점사) --></td>
			<td class="right  '.$class_red.' '.$acc_top.' acc_bg">'.$out_pay_price.'<!-- 결제금액(A) --></td>
			<td class="right  '.$class_red.' '.$acc_top.' acc_bg">'.$out_sale_price.'<!-- 실결제액 --></td>
			<td class="right  '.$class_red.' '.$acc_top.' acc_bg acc_right">'.$out_cash_use.'<!-- 이머니 --></td>
			<td class="'.$class_current_out_ac_acc_status.' cal_left">'.$out_exp_ea.'
			<!-- 정산수량 --></td>
			<td class="right '.$class_current_out_ac_acc_status.'">'.$out_total_ac_price.'
			<!-- 정산대상금액 --></td>
			<td class="right '.$class_current_out_ac_acc_status.'">'.$out_pg_default_price.'
			<!-- 정산대상-결제금액 --></td>
			<td class="right '.$class_current_out_ac_acc_status.'">'.$out_ac_salescost_admin.'
			<!-- 정산대상-본사할인 --></td>
			<td class="right '.$class_current_out_ac_acc_status.'">'.$out_ac_pg_price.'
			<!-- 정산대상-제휴사할인 --></td>
			<td class="right '.$class_current_out_ac_acc_status.'">'.$out_ac_consumer_real_price.'
			<!-- 공급금액 --></td>
			<td class="right '.$class_current_out_ac_acc_status.'">'.$out_ac_fee_rate.'
			<!-- 수수료율(%) --></td>
			<td class="right '.$class_current_out_ac_acc_status.'" '.$title_out_sales_unit_feeprice.'>'.$out_sales_unit_feeprice.'
			<!-- 수수료 --></td>
			<td class="right '.$class_current_out_ac_acc_status.' cal_right">'.$out_commission_price.'
			<!-- 정산금액(B) --></td>
			'.( ($caller == 'admin') ? '
			<td class="right '.$class_red.' profit_bg">'.$out_ac_profit_price.'
			<!-- 이익금액(C)>(C)=(A)-(B) --></td>
			<td class="right '.$class_red.'">'.$out_ac_profit_rate.'
			<!-- 이익금액(C)>이익율 --></td>
			' : '' ).'
			<td class="left"  style=" mso-number-format:\'@\';">'.$acinsdata['out_pg_ordernum'].'</td>
			<td>'.$acinsdata['out_order_referer_viewer'].'</td>
			<td>'.$acinsdata['out_payment'].'</td>
		</tr>
	';

	$carryovertot = $acinsdata['carryovertot'];
	$cal_bottom = ( (!$checkloopcnt) ? 'cal_bottom' : '' );
	
	$html['carryovertot'] = '
		<tr class="its_tr_looptotal">
			<td colspan="7">합계</td>
			<td class="right">'.number_format($carryovertot['out_ea']-$carryovertot['refund_out_ea']).'<!-- 수량 --></td>
			<td class="right">'.number_format($carryovertot['out_price']-$carryovertot['refund_out_price']).'<!-- ='.number_format($carryovertot['out_price']).'-'.$carryovertot['refund_out_price'].' --><!-- 판매금액 --></td>
			<td class="right">'.number_format($carryovertot['out_salescost_admin']-$carryovertot['refund_salescost_admin']).'<!-- 할인>본사 --></td>
			<td class="right">'.number_format($carryovertot['out_pg_sale_price']-$carryovertot['refund_pg_sale_price']).'<!-- 할인>제휴사 --></td>
			<td class="right">'.number_format($carryovertot['out_salescost_provider']-$carryovertot['refund_salescost_provider']).'<!-- 할인>입점사 --></td>
			<td class="right">'.number_format(($carryovertot['out_sale_price']+$carryovertot['out_cash_use'])-($carryovertot['refund_out_sale_price']+$carryovertot['refund_out_cash_use'])).'<!-- 결제금액(A) --></td>
			<td class="right">'.number_format($carryovertot['out_sale_price']-$carryovertot['refund_out_sale_price']).'<!-- 실결제액 --></td>
			<td class="right">'.number_format($carryovertot['out_cash_use']-$carryovertot['refund_out_cash_use']).'<!-- 이머니 --></td>
			<td class="pdr20 '.$cal_bottom.' cal_bg cal_left">'.number_format($carryovertot['out_exp_ea']-$carryovertot['refund_out_exp_ea']).'
			<!-- 정산수량 --></td>
			<td class="right '.$cal_bottom.' cal_bg">'.number_format($carryovertot['out_total_ac_price']-$carryovertot['refund_out_total_ac_price']).'
			<!-- 정산대상금액 --></td>
			<td class="right '.$cal_bottom.' cal_bg">'.number_format($carryovertot['out_pg_default_price']-$carryovertot['refund_out_pg_default_price']).'
			<!-- 정산대상금액-결제금액 --></td>
			<td class="right '.$cal_bottom.' cal_bg">'.number_format($carryovertot['out_ac_salescost_admin']-$carryovertot['refund_out_ac_salescost_admin']).'
			<!-- 정산대상금액-본사할인 --></td>
			<td class="right '.$cal_bottom.' cal_bg">'.number_format($carryovertot['out_ac_pg_price']-$carryovertot['refund_out_out_ac_pg_price']).'
			<!-- 정산대상금액-제휴사할인 --></td>
			<td class="right '.$cal_bottom.' cal_bg">'.number_format($carryovertot['out_ac_consumer_real_price']-$carryovertot['refund_out_ac_consumer_real_price']).'
			<!-- 공급금액 --></td>
			<td class="right '.$cal_bottom.' cal_bg">'.( ($carryovertot['out_ac_fee_rate'] != 0) ? $carryovertot['out_ac_fee_rate'].'%' : '0' ).'
			<!-- 수수료율 --></td>
			<td class="right '.$cal_bottom.' cal_bg">'.number_format($carryovertot['out_sales_unit_feeprice']-$carryovertot['refund_sales_unit_feeprice']).'
			<!-- 수수료 --></td>
			<td class="right '.$cal_bottom.' cal_bg cal_right">'.number_format($carryovertot['out_commission_price']-$carryovertot['refund_out_commission_price']).'
			<!-- 정산금액(B) --></td>
			'.( ($caller == 'admin') ? '
			<td class="right '.( (!$checkloopcnt) ? 'profit_bottom' : '' ).' profit_bg">'.number_format($carryovertot['out_ac_profit_price']-$carryovertot['refund_out_ac_profit_price']).'
			<!-- 이익금액(C)>(C)=(A)-(B) --></td>
			<td class="right">'.( ($carryovertot['out_ac_profit_rate'] != 0) ? $carryovertot['out_ac_profit_rate'].'%' : '0' ).'
			<!-- 이익금액(C)>이익율 --></td>
			' : '' ).'
			<td></td>
			<td></td>
			<td></td>
		</tr>
	';

	$acc_bottom = ( (!$checkloopcnt) ? 'acc_bottom' : '' );
	$tot = $acinsdata['tot'];
	$alltot = $acinsdata['alltot'];

	$html['tot'] = '
		<tr  class="its_tr_looptotal">
			<td colspan="7">합계</td>
			<td class="right '.$acc_bottom.' acc_left acc_bg">'.number_format($tot['out_ea']-$tot['refund_out_ea']).'<!-- 수량 --></td>
			<td class="right '.$acc_bottom.' acc_bg ">'.number_format($tot['out_price']-$tot['refund_out_price']).'<!-- 판매금액 --></td>
			<td class="right '.$acc_bottom.' acc_bg">'.number_format($tot['out_salescost_admin']-$tot['refund_salescost_admin']).'<!-- 할인>본사 --></td>
			<td class="right '.$acc_bottom.' acc_bg">'.number_format($tot['out_pg_sale_price']-$tot['refund_pg_sale_price']).'<!-- 할인>제휴사 --></td>
			<td class="right '.$acc_bottom.' acc_bg">'.number_format($tot['out_salescost_provider']-$tot['refund_salescost_provider']).'<!-- 할인>입점사 --></td>
			<td class="right '.$acc_bottom.' acc_bg">'.number_format(($tot['out_sale_price']+$tot['out_cash_use'])-($tot['refund_out_sale_price']+$tot['refund_out_cash_use'])).'<!-- 결제금액(A) --></td>
			<td class="right '.$acc_bottom.' acc_bg">'.number_format($tot['out_sale_price']-$tot['refund_out_sale_price']).'<!-- 실결제액 --></td>
			<td class="right '.$acc_bottom.' acc_bg acc_right">'.number_format($tot['out_cash_use']-$tot['refund_out_cash_use']).'<!-- 이머니 --></td>
			<td class="cal_bottom cal_bg cal_left">'.number_format($alltot['ac_out_exp_ea']).'
			<!-- 정산수량 --></td>
			<td class="right cal_bottom cal_bg ">'.number_format($alltot['ac_out_total_ac_price']).'
			<!-- 정산대상금액 --></td>
			<td class="right cal_bottom cal_bg">'.number_format($alltot['ac_out_pg_default_price']).'
			<!-- 정산대상금액-결제금액 --></td>
			<td class="right cal_bottom cal_bg">'.number_format($alltot['ac_out_salescost_admin']).'
			<!-- 정산대상금액-본사할인 --></td>
			<td class="right cal_bottom cal_bg">'.number_format($alltot['ac_out_ac_pg_price']).'
			<!-- 정산대상금액-제휴사할인 --></td>
			<td class="right cal_bottom cal_bg">'.number_format($alltot['ac_out_consumer_real_price']).'
			<!-- 공급금액 --></td>
			<td class="right cal_bottom cal_bg">'.( ($alltot['ac_out_fee_rate'] != 0) ? $alltot['ac_out_fee_rate'].'%' : '' ).'
			<!-- 수수료율 --></td>
			<td class="right cal_bottom cal_bg">'.number_format($alltot['ac_out_sales_unit_feeprice']).'
			<!-- 수수료 --></td>
			<td class="right cal_bottom cal_bg cal_right">'.number_format($alltot['ac_out_commission_price']).'
			<!-- 정산금액(B) --></td>
			'.( ($caller == 'admin') ? '
			<td class="right profit_bg">'.number_format($alltot['ac_out_profit_price']).'
			<!-- 이익금액(C)>(C)=(A)-(B) --></td>
			<td class="right">'.( ($alltot['ac_out_profit_rate'] != 0) ? $alltot['ac_out_profit_rate'].'%' : '' ).'
			<!-- 이익금액(C)>이익율 --></td>
			' : '' ).'
			<td></td>
			<td></td>
			<td></td>
		</tr>
		<tr class="its_tr_loopname">
			<td colspan="7"></td>
			<td colspan="8" class="acc_name">당월 판매 합계</td>
			<td colspan="9" class="cal_name">당월 정산 합계</td>
			<td class="profit_name">이익 합계</td>
			<td colspan="4"></td>
		</tr>
	';

	$excel_file = ROOTPATH."/data/tmp/accountallexcel_".$type.".html";
	if($mode == 'create' && $type){

		$filemode = array(
			'header' => "w+",
			'footer' => "w+",
			'carryoverloop' => "a+",
			'loop' => "a+",
			'carryovertot' => "a+",
			'tot' => "a+",
		);

		if($acinsdata['out_num'] == '1' && in_array($type, array('carryoverloop', 'loop'))){
			$filemode[$type] = 'w+';
		}

		$fp = fopen($excel_file, $filemode[$type]);
		if(fwrite($fp, " ".$html[$type].chr(13).chr(10)) === FALSE)
		{
			fclose($fp);
			return 0;
		}
		fclose($fp);
	}elseif($mode == 'destory' && $type){
		if(file_exists($excel_file)){
			unlink($excel_file);
		}
	}elseif($mode == 'read' && $type){
		if(file_exists($excel_file)){
			$fp = fopen($excel_file, 'r');
			if ($fp) {
				while (($line = fgets($fp)) !== false) {
					echo $line;
				}	
				fclose($fp);
			}
		}
	}
}


function getHtmlAccountCheckerTool($accountData, $carryover = '', $checker_tool_view_succ=0){
	$CI =& get_instance();
	$CI->load->model('accountallmodel');
	$CI->load->helper('accountall');
	// 정산툴모드로 실행
	$CI->accountallmodel->tool_mode = true;

	$checker_diff = $CI->accountallmodel->checker_tool_for_row($accountData, $carryover);

	$drawAccountallTool = drawAccountallTool($checker_diff, $checker_tool_view_succ, $accountData);

	// 00:정상, 10:오류, 90:매칭데이터 없음
	$checker_flag						= $drawAccountallTool['checker_flag'];
	$checker_flag_txt['status']			= $drawAccountallTool['checker_flag_txt'];
	$checker_flag_txt['class']			= $drawAccountallTool['checker_flag_class'];
	$htmlTable							= $drawAccountallTool['table_all'];
	$account_tool_script				= $drawAccountallTool['account_tool_script'];

	$html = '
		<span class="btn small '.$checker_flag_txt['class'].'">
			<button type="button"
				class="checker_tool_detail"
				data-key="'.$accountData['seq'].'"
				order_seq="'.$accountData['order_seq'].'"
				checker_flag="'.$checker_flag.'"
			>
				'.$checker_flag_txt['status'].'
			</button>
		</span>
		<div class="hide" id="checker_tool_detail_'.$accountData['seq'].'">
			'.$htmlTable.'
		</div>
		'.$account_tool_script.'
	';

	return $html;
}
?>
