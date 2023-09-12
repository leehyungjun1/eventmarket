<?php
##  플렛폼 별 결재수단
function get_payment($is_mobile_agent,$possible_pay='', $total_price='')
{
	$CI	=& get_instance();
	$payment_count	= 0;
	$bank					= '';
	$payment				= '';
	$escrow				= '';

	if( $is_mobile_agent ) {
		$pg_var						= 'mobilePayment';
		$escrowpg_var			= 'mobileEscrow';
		$escrowAccountLimit	= 'mobileEscrowAccountLimit';
		$escrowVirtualLimit		= 'mobileEscrowVirtualLimit';
	}else{
		$pg_var						= 'payment';
		$escrowpg_var			= 'escrow';
		$escrowAccountLimit	= 'escrowAccountLimit';
		$escrowVirtualLimit		= 'escrowVirtualLimit';
	}

	$cfg_bank	= config_load('bank');
	if( $cfg_bank ){
		switch($CI->config_system['language']){
			case "KR":$bankCode = 'bankCode';break;
			case "US";$bankCode = 'bankCode_en';break;
			case "CN";$bankCode = 'bankCode_cn';break;
			case "JP";$bankCode = 'bankCode_jp';
		}
		foreach($cfg_bank as $value_cfg_bank){
			list($code_bank)				= code_load($bankCode, $value_cfg_bank['bank']);
			$value_cfg_bank['bank']	= $code_bank['value'];
			$bank[]							= $value_cfg_bank;
			if( $value_cfg_bank['accountUse'] == 'y' ){
				$payment['bank'] = true;
			}
		}
	}

	if( $CI->config_system['pgCompany'] && $CI->config_system['not_use_pg'] != 'y'){
		$payment_gateway	= config_load($CI->config_system['pgCompany']);
		$code_kcp				= code_load('kcpCardCompanyCode');
		$payment_gateway['arrKcpCardCompany']	= $code_kcp;
		foreach($code_kcp as $value_code_kcp){
			$payment_gateway['arrCardCompany'][$value_code_kcp['codecd']]	= $value_code_kcp['value'];
		}
		if(isset($payment_gateway[$pg_var])) foreach($payment_gateway[$pg_var] as $value_payment){
			$payment[$value_payment] = true;
		}
		if(isset($payment_gateway[$escrowpg_var])){
			foreach($payment_gateway[$escrowpg_var] as $value_escrow){
				if($value_escrow == 'account'){
					$escrow[$value_escrow] = true;
				}
				if($value_escrow == 'virtual'){
					$escrow[$value_escrow] = true;
				}
			}
		}
	}

	// 다음 카카오 페이 :: 2017-12-11 lwh
	if( $CI->config_system['not_use_daumkakaopay'] == 'n' ){
		$payment['kakaopay']	= true;
		$payment_gateway[$pg_var][] = 'kakaopay';
		
	}
	if($CI->config_system['not_use_kakao'] == 'n'){
		$payment['kakaopay']	= true;
		$payment_gateway[$pg_var][] = 'kakaopay';
	}

	// 페이코
	if( $CI->config_system['not_use_payco'] == 'n' ){
		$payment['payco']	= true;
		$payment_gateway[$pg_var][] = 'payco';

	}

	// 네이버페이
	if( $CI->config_system['not_use_naverpayment'] == 'n' ){
		$payment['naverpayment']	= true;
		$payment_gateway[$pg_var][] = 'naverpayment';

	}

	// 페이팔
	if( $CI->config_system['not_use_paypal'] == 'n' ){
		$payment['paypal']	= true;
		$payment_gateway[$pg_var][] = 'paypal';
	}

	// 엑심베이
	if( $CI->config_system['not_use_eximbay'] == 'n' ){
		$payment['eximbay']	= true;
		$payment_gateway[$pg_var][] = 'eximbay';
	}

	if( $bank ) foreach($bank as $k => $value_bank){
		if( $value_bank['accountUse'] == 'y' ){
			if(count($possible_pay) > 0){
				foreach($possible_pay as $payData){
					if(!in_array('bank', $payData)){
						$payment['bank'] = false;
					}
				}
			}
		}
	}

	if(isset($payment_gateway[$pg_var])) foreach($payment_gateway[$pg_var] as $value_payment){
		if(count($possible_pay) > 0){
			foreach($possible_pay as $payData){
				if(!in_array($value_payment, $payData)){
					$payment[$value_payment] = false;
				}
			}
		}
	}

	if(isset($payment_gateway[$escrowpg_var])) foreach($payment_gateway[$escrowpg_var] as $value_escrow){
		if($value_escrow == 'account'){
			if(count($possible_pay) > 0){
				foreach($possible_pay as $payData){
					if( !in_array("escrow_".$value_escrow, $payData) ){
						$escrow[$value_escrow]	= false;
					}
				}
			}

			if( $total_price < $payment_gateway[$escrowAccountLimit] ) {
				$escrow[$value_escrow]	= false;
				$escrow_view						= false;
			}
		}
		if($value_escrow == 'virtual'){
			if(count($possible_pay) > 0){
				foreach($possible_pay as $payData){
					if(!in_array("escrow_".$value_escrow, $payData)){
						$escrow[$value_escrow]	= false;
					}
				}
			}
			if( $total_price < $payment_gateway[$escrowVirtualLimit] ) {
				$escrow[$value_escrow]	= false;
			}
		}
	}

	foreach($payment as $value_payment) if($value_payment) $payment_count++;
	foreach($escrow as $value_escrow)  if($value_escrow){
		$payment_count++;
		$escrow_count++;
	}

	return array(
		'bank'						=> $bank,
		'payment_gateway'	=> $payment_gateway,
		'payment'					=> $payment,
		'escrow'					=> $escrow,
		'payment_count'		=> $payment_count,
		'escrow_count'		=> $escrow_count
	);
}
?>