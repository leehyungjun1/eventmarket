<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 자바스크립트 관련 helper 모음.
 * @author gabia
 * @since version 1.0 - 2009. 7. 7.
 */

use App\Libraries\AssetManager;
use App\Libraries\BuildInfo;

function js($content, $set_header = true) {
	if($set_header && !headers_sent()) {
		header('Content-Type: text/html; charset=utf-8');
	}
	return '<script nonce="'.script_nonce().'">'.$content.'</script>';
}

function alert($msg) {
	$msg = str_replace('<br />','\n',$msg);
	echo js("alert('$msg')");
}

function pageRedirect($url, $msg = '', $target = 'self') {
	if ($msg) {
		alert($msg);
	}
	echo js($target . ".document.location.replace('$url')");
}

function pageLocation($url, $msg = '', $target = 'self') {
	if ($msg) {
		alert($msg);
	}
	echo js($target . ".document.location.href='$url'");
}

function pageBack($msg = '', $target = 'self', $allow_exit = true) {
	if ($msg) {
		alert($msg);
	}

	// 리퍼러 체크해서 없으면 메인으로 이동 :: 2019-04-08 pjw
	$CI			=& get_instance();
	$referer	= $CI->input->server('HTTP_REFERER');

	// 리퍼러 도메인이 현재 페이지의 도메인과 다르면 root 로 redirect :: 2019-05-14 rsh
	if(!empty($referer)) {
	    $refererInfo = parse_url($referer);
	    $refererHost = $refererInfo['host'];
	    $host = $CI->input->server('HTTP_HOST');
	    if($host !== $refererHost) {
	        unset($referer);
	    }
	}

	if(empty($referer)){
		echo js($target . ".document.location.href='/';");
	}else{
		echo js("history.back();");
	}

	if($allow_exit){
		exit;
	}
}

function pageReload($msg = '', $target = 'self') {
	if ($msg) {
		alert($msg);
	}
	echo js($target . ".document.location.reload();");
	if($target=='parent' || $target=='top') echo js("document.location.href='about:blank';");
}

function pageClose($msg = '') {
	if ($msg) {
		alert($msg);
	}
	echo js("self.close();");
}

function openerRedirect($url, $msg = '') {
	if ($msg) {
		alert($msg);
	}
	echo js("opener.document.location.replace('$url')");
}

function openDialog($title, $layerId, $customOptions=array(), $target = 'self', $callback='') {
	$CI =& get_instance();

	if	(strpos($_SERVER['HTTP_USER_AGENT'], "Firefox") !== false) {
		if (strpos($callback, "location.reload()") !== false) $callback = str_replace("location.reload()","location.reload(true)",$callback);
	}

	echo("<script type='text/javascript'>");
	echo("{$target}.loadingStop('body',true);");
	echo("{$target}.loadingStop();");
	echo("{$target}.openDialog('{$title}', '{$layerId}', ".json_encode($customOptions).", function(){{$callback}});");
	echo("</script>");
}

function openDialogAlert($msg,$width,$height,$target = 'self',$callback='',$options=array()) {
	$CI =& get_instance();
	if($CI->mobileMode){
		$msg = str_replace(array("<br />","<br/>","<br>"),"",$msg);
		$msg = strip_tags($msg, '<br>');
	}

	if (strpos($_SERVER['HTTP_USER_AGENT'], "Firefox") !== false) {
		if (strpos($callback, "location.reload()") !== false) $callback = str_replace("location.reload()","location.reload(true)",$callback);
	}

	echo("<script type='text/javascript'>");
	echo("{$target}.loadingStop('body',true);");
	echo("{$target}.loadingStop();");
	echo("{$target}.openDialogAlert('{$msg}','{$width}','{$height}',function(){{$callback}},".json_encode($options).");");
	echo("</script>");
}

function openDialogConfirm($msg,$width,$height,$target = 'self',$yesCallback='',$noCallback='') {
	$CI =& get_instance();
	if($CI->mobileMode){
		$msg = str_replace(array("<br />","<br/>","<br>"),"",$msg);
		$msg = strip_tags($msg);
	}
	echo("<script type='text/javascript'>");
	echo("{$target}.loadingStop();");
	echo("{$target}.openDialogConfirm('{$msg}','{$width}','{$height}',function(){{$yesCallback}},function(){{$noCallback}});");
	echo("</script>");
}

// 배열로 폼을 만들어서 submit해 주는 함수
function arrayToFormSubmit($formName, $formAction, $params, $formTarget = '', $noSubmit = ''){
	if	(is_array($params) && count($params) > 0){
		echo '<form name="' . $formName . '" method="post" action="' . $formAction . '"';
		if	($formTarget)	echo ' target="' . $formTarget . '"';
		echo '>';
		foreach($params as $name => $value){
			if	(strlen($value) > 255){
				echo '<textarea name="' . $name . '" style="display:none;">' . $value . '</textarea>';
			}else{
				echo '<input type="hidden" name="' . $name . '" value="' . $value . '" />';
			}
		}
		echo '</form>';

		if	($noSubmit != 'y'){
			echo '<script>' . $formName . '.submit();</script>';
		}
	}
}

function jscall($function_name, ...$args) {
	echo js($function_name.'.apply(this,'.json_encode($args, JSON_UNESCAPED_UNICODE).')');
}

/**
 * <script> 태그에서 사용할 nonce를 생성합니다.
 * https://developers.google.com/web/fundamentals/security/csp/
 */
function script_nonce() {
	$CI =& get_instance();
	if(!$CI->script_nonce) {
		$rand = random_bytes(8);
		$CI->script_nonce = base64_encode(hash('sha256', "{$CI->config_system['shopSno']}:{$_SERVER['REMOTE_ADDR']}:{$CI->config_system['time_split'][1]}:{$rand}", true));
	}
	return $CI->script_nonce;
}

function front_config() {
	$CI =& get_instance();
	$config_search = config_load("search");

	$is_admin = $CI->session->userdata('manager');
	$is_selleradmin = $CI->session->userdata('provider');

	$is_exception_security = false;
	if(!empty($is_admin) || !empty($is_selleradmin)){
		$is_exception_security = true;
	}

	$config_member = config_load('member');
	$MemberLogoutLimit = false;
	if(isset($config_member['sessLimitMin']) && $config_member['sessLimit']=='Y'){
		$MemberLogoutLimit = $config_member['sessLimitMin'];
	}
	// 기본 config 값 설정
	$config = [
		'Environment' => [
			'MobileMode' => !defined('__ADMIN__') && ($CI->mobileMode || $CI->storemobileMode || isMobilecheck($_SERVER['HTTP_USER_AGENT'])),
			'SetMode' => $CI->session->userdata('setMode'),
			'Language' => $CI->config_system['language'],
			'isAdmin' => defined('__ADMIN__'),
			'isSellerAdmin' => defined('__SELLERADMIN__'),
			'isUser' => isset($CI->userInfo['member_seq']) ? true : false,
			'Currency' => [
				'Basic' => [
					'Id' => $CI->config_system['basic_currency'],
					'Symbol' => $CI->config_currency[$CI->config_system['basic_currency']]['currency_symbol'] ?? '원',
					'Position' => $CI->config_currency[$CI->config_system['basic_currency']]['currency_symbol_position'] ?? 'after',
				],
				'Skin' => [
					'Id' => $CI->config_system['basic_currency'],
					'Symbol' => $CI->config_currency[$CI->config_system['basic_currency']]['currency_symbol'] ?? '원',
					'Position' => $CI->config_currency[$CI->config_system['basic_currency']]['currency_symbol_position'] ?? 'after',
				],
			],
			'serviceLimit' => [
					'H_FR' => serviceLimit("H_FR"),
					'H_AD' => serviceLimit("H_AD"),
					'H_NFR' => serviceLimit("H_NFR"),
			],
			'OperationType' => $CI->config_system['operation_type'],
			'Protocol' => substr(get_connet_protocol(), 0, -3),
			'CacheBreaker' => BuildInfo::get()->build_commit_id,
		],
		'Security' => [
			'PreventDrag' => !!(!$is_exception_security && $CI->config_system['protectMouseDragcopy']),
			'PreventContextMenu' => !!(!$is_exception_security && $CI->config_system['protectMouseRight']),
			'MemberLogoutLimit' => $MemberLogoutLimit,
		],
		'Search' => [
			'AutoComplete' => $config_search['auto_search']==='y',
			'Suggest' => $config_search['popular_search']==='y',
		],
	];

	return array_merge_recursive($config, front_config_additional());
}

function front_config_admin() {
	return ['Admin' => [
		'Manual' => [
			'Visible' => true,
		]
	]] + front_config();
}

/** 설정 내용 중 프론트에서 필요한 부분만 JSON화하여 노출합니다. */
function front_config_json() {
	return json_encode(front_config(), JSON_UNESCAPED_UNICODE);
}

/** 설정 내용 중 프론트에서 필요한 부분만 JSON화하여 하위 호환 기능을 추가한 뒤 본문 HTML에 노출합니다. */
function front_config_js() {
	$config_str = front_config_json();

	/** Config Aliases (DEPRECATED from 202004) */
	$aliases = [
		'gl_operation_type' => 'window.Firstmall.Config.Environment.OperationType',
		'gl_mobile_mode' => 'window.Firstmall.Config.Environment.MobileMode',
		'gl_set_mode' => 'window.Firstmall.Config.Environment.SetMode',
		'gl_language' => 'window.Firstmall.Config.Environment.Language',
		'gl_basic_currency' => 'window.Firstmall.Config.Environment.Currency.Basic.Id',
		'gl_skin_currency' => 'window.Firstmall.Config.Environment.Currency.Skin.Id',
		'gl_basic_currency_symbol' => 'window.Firstmall.Config.Environment.Currency.Basic.Symbol',
		'gl_basic_currency_symbol_position' => 'window.Firstmall.Config.Environment.Currency.Basic.Position',
		'gl_protocol' => 'window.Firstmall.Config.Environment.Protocol+"://"',
		'gl_broadcast' => 'window.Firstmall.Config.Environment.Broadcast',
	];

	$aliases_str = '';
	$aliases_lines = [];
	foreach($aliases as $name => $pointer) {
		$aliases_lines[] = json_encode($name).':'.$pointer;
	}
	$aliases_str = '{'.implode(',', $aliases_lines).'}';
	unset($aliases_lines);

	return <<<JAVASCRIPT
window.Firstmall = window.Firstmall || {};
window.Firstmall.Config = {$config_str};
(function(){ var aliases = {$aliases_str}; for(var attr in aliases) { window[attr] = aliases[attr]; }})();
JAVASCRIPT;
}

/** App\Libraries\AssetManager의 숏컷입니다. 반드시 자바스크립트일 필요는 없습니다. */
function requirejs(...$args) {
	if(is_array($args[0]) && count($args) === 1) {
		foreach($args[0] as $item) {
			if(is_array($item)) requirejs(...$item);
			else requirejs($item);
		}
		return;
	}
	AssetManager::add(...$args);
	return;
}

function header_requires() {
	return AssetManager::create_html();
}

function set_content_security_policy(array $source = ['self']) {
	return header(sprintf('Content-Security-Policy: script-src \'%s\'', implode('\' \'', $source)));
}

// 커스텀으로 기본 config 값에서 추가하는 함수
function front_config_additional() {

	$config = array();
	$addition_config_list = ['broadcast'];

	// 추가되는 config는 addition_config_{이름} 형식의 함수에서 합친다
	foreach($addition_config_list as $addition_config) {
		$addition_function = 'addition_config_'.$addition_config;
		$config = $config + $addition_function();
	}

	return $config;
}

// 라이브커머스 config
function addition_config_broadcast() {
	$CI =& get_instance();
	$CI->load->config('broadcast');
	$broadcast_config = [];
	$expire_date = array_pop(config_load('broadcast','expire_date'));

	if($expire_date >= date('Y-m-d')) {
		$broadcast_config['Environment'] = [
			'Broadcast' => $CI->config->item('broadcast')
		];
	}

	return $broadcast_config;
}

// END
/* End of file helper.php */
/* Location: ./app/helper/javascript.php */
