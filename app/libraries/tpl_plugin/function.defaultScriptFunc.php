<?php

function defaultScriptFunc(){
	
	$CI =& get_instance();
	
	$ret = "";

	$isAdminPage = false;
	$matchResult = preg_match('/(admin\/)|(selleradmin\/)/', $CI->uri->uri_string);
	// 관리자 / 입점사 관리자 페이지인 경우
	if ($matchResult === 1) {
		$isAdminPage = true;
	}

	// js 핸들링용 스크립트 확인
	if (file_exists(APPPATH.'javascript/js/handlers/'.$CI->uri->uri_string.'.ready.js')) {
		$jsUri = "/app/javascript/js/handlers/".$CI->uri->uri_string.".ready.js";
		requirejs($jsUri, 50);
	}

	if(!strstr($_SERVER['REQUEST_URI'],"/allat/") && !strstr($_SERVER['REQUEST_URI'],"/admin/goods/regist")){
		requirejs('/app/javascript/plugin/jquery.bxslider.js', 50);
	}

	//facebook pixel
	if ($CI->config_system['facebook_pixel_use'] == 'Y' && !$_GET['iframe'] && $isAdminPage === false) {
		//basic code
		$ret .= "<script>";
		$ret .= "!function(f,b,e,v,n,t,s)";
		$ret .= "{if(f.fbq)return;n=f.fbq=function(){n.callMethod?";
		$ret .= "n.callMethod.apply(n,arguments):n.queue.push(arguments)};";
		$ret .= "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';";
		$ret .= "n.queue=[];t=b.createElement(e);t.async=!0;";
		$ret .= " t.src=v;s=b.getElementsByTagName(e)[0];";
		$ret .= "s.parentNode.insertBefore(t,s)}(window, document,'script',";
		$ret .= "'https://connect.facebook.net/en_US/fbevents.js');";
		$ret .= "fbq('init', '".$CI->config_system['facebook_pixel']."', {}, {'agent':'plgabia'});";
		$ret .= "fbq('track', 'PageView');";
		$ret .= "</script>";
		$ret .= "<noscript><img height='1' width='1' style='display:none' src='https://www.facebook.com/tr?id=".$CI->config_system['facebook_pixel']."&ev=PageView&noscript=1' /></noscript>";
	}

	// gtag 연동
	$CI->load->library('googleGtag');
	$ret .= $CI->googlegtag->globalTag();

	$ret .= header_requires();		

	return $ret.PHP_EOL;
}
