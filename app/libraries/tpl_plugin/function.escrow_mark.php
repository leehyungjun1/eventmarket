<?php

/**
 * @author lgs
 */

function escrow_mark($max_width=null,$max_height=null)
{
	$CI =& get_instance();
	$pg = config_load($CI->config_system['pgCompany']);
	$businessLicense = str_replace('-','',$CI->config_basic['businessLicense']);

	// 토스페이먼츠 로고가 작다고 별도 요청이 들어와 로고 크기 수정
	if ($CI->config_system['pgCompany'] === 'lg') {
		$max_width = "125";
	}

	$imageStyle = "";
	if($max_width)	$imageStyle .= "max-width:{$max_width}px;";
	if($max_height)	$imageStyle .= "max-height:{$max_heigh}px;";
	if($imageStyle)	$imageStyle = ' style="'.$imageStyle.'"';

	if($CI->config_system['pgCompany'] == 'lg'){
		//$result .= '<script type="text/javascript" src="https://pgweb.uplus.co.kr/pg/wmp/mertadmin/jsp/mertservice/escrowValid.js"></script>';
		$result .= '<script type="text/javascript" src="'.get_connet_protocol().'pgweb.uplus.co.kr/WEB_SERVER/js/escrowValid.js"></script>';
		$result .= '<a style="cursor:pointer;" onclick="goValidEscrow(\''.$pg['mallCode'].'\')">';
		$result .= '<img src="/data/icon/escrow_mark/'.$pg['escrowMark'].'" '.$imageStyle.' />';
		$result .= '</a>';
	}
	if($CI->config_system['pgCompany'] == 'kcp' ){
		$result .= '<form name="shop_check" method="post" action="'.get_connet_protocol().'admin.kcp.co.kr/Modules/escrow/kcp_pop.jsp" target="kcp_pop">';
		$result .= '<input type="hidden" name="site_cd" value="'.$pg['mallCode'].'">';
		$result .= '</form>';
		$result .= '<script type="text/javascript">';
		$result .= 'function go_check() {';
		$result .= '	var status = "width=500 height=450 menubar=no,scrollbars=no,resizable=no,status=no";';
		$result .= '	var obj = window.open("", "kcp_pop", status);';
		$result .= '	document.shop_check.submit();';
		$result .= '}';
		$result .= '</script>';
		$result .= '<a href="javascript:go_check();">';
		$result .= '<img src="/data/icon/escrow_mark/'.$pg['escrowMark'].'" '.$imageStyle.' />';
		$result .= '</a>';
	}
	if($CI->config_system['pgCompany'] == 'allat'){
		$result .= '<script type="text/javascript">';
		$result .= 'function f_escrowAllat(shopbsnno, mid){';
		$result .= '	var status = "top=0,left=0,width=980,height=600,scrollbars,menubar=no,resizable,status,location=yes,toolbar=yes";';
		$result .= '	var link_url = "https://www.allatpay.com/servlet/AllatBiz/svcinfo/si_escrowview.jsp?menu_id=idS16&shop_id=" + mid + "&business_no=" + shopbsnno;';
		$result .= '	 var obj = window.open(link_url, "allat_escrow", status);';
		$result .= '}';
		$result .= '</script>';
		$result .= '<a href="javascript:f_escrowAllat(\''.$businessLicense.'\',\''.$pg['escrowMallCode'].'\')">';
		$result .= '<img src="/data/icon/escrow_mark/'.$pg['escrowMark'].'" '.$imageStyle.' />';
		$result .= '</a>';
	}
	if($CI->config_system['pgCompany'] == 'kspay'){
		$result .= '<script type="text/javascript">';
		$result .= 'function f_escrowKsnet(){';
		$result .= 'window.open("'.get_connet_protocol().'pgims.ksnet.co.kr/pg_infoc/src/dealinfo/pg_shop_info2.jsp?shop_id='.$pg['mallId'].'", ';
		$result .= '"ksnet_escrow", ';
		$result .= '"top=0,left=0,width=800,height=450,scrollbars,menubar=no,resizable,status,location=yes,toolbar=yes");';
		$result .= '}';
		$result .= '</script>';
		$result .= '<img src="/data/icon/escrow_mark/'.$pg['escrowMark'].'" ';
		$result .= 'style="cursor:pointer;" onclick="f_escrowKsnet();" />';
	}
	if($CI->config_system['pgCompany'] == 'kicc'){
		$result .= '<script type="text/javascript">';
		$result .= 'function f_escrowKicc(){';
		$result .= 'window.open("'.get_connet_protocol().'www.easypay.co.kr/escrow/escrow_memb_auth.jsp?memb_id='.$pg['mallCode'].'", ';
		$result .= '"kicc_escrow", ';
		$result .= '"top=0,left=0,width=800,height=450,scrollbars,menubar=no,resizable,status,location=yes,toolbar=yes");';
		$result .= '}';
		$result .= '</script>';
		$result .= '<img src="/data/icon/escrow_mark/'.$pg['escrowMark'].'" ';
		$result .= 'style="cursor:pointer;" onclick="f_escrowKicc();" />';
	}
	return $result;
}
?>