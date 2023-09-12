<?
if (!defined('BASEPATH')) exit('No direct script access allowed');

function sql_injection_check($alert = true)
{
	
	// 자동 이메일 발송폼 저장 예외처리
	if (preg_match('/admin\/member_process\/email/', $_SERVER['REQUEST_URI'])) {
		return true;
	}
	// 관리자 게시판 개별 validation을 위해 예외 처리
	if (preg_match('/admin\/board_process/', $_SERVER['REQUEST_URI'])) {
		return true;
	}
	$injection = injection_match($_POST,"POST");
	if ($injection['check'] == 'N') {
		$injection = injection_match($_GET,"GET");
	}
	if ($injection['check'] == 'N') {
		$REQUEST_URI_ARRAY = @explode("?", $_SERVER["REQUEST_URI"]);
		$REQUEST_URI_ARRAY = @explode("&", $REQUEST_URI_ARRAY[1]);
		$injection = injection_match($REQUEST_URI_ARRAY,"REQUEST_URI");
	}
	if ($alert) {
		$injection['alert'] = $alert;
	}
	if ($injection['check'] != 'N') {
		if ($injection['alert']) {
			echo "<script language='javascript'>\n";
			echo "alert('유효하지 않은 문자가 체크되었습니다.');\n";
			echo "history.back();\n";
			echo "</script>\n";
			exit;
		}
	}
}

/**
 * injection function
 */
function injection_match($params, $name) {
	$CI = &get_instance();
	$injection = array(
		'check' => 'N',
		'pattern_val' => '',
		'key' => '',
		'value' => '',
	);

	foreach ($params as $key => $value) {
		if($name == "POST") {
			$pass_post = pass_post_value();
			if (in_array($key, $pass_post)) {
				continue;
			}
		}

		if (value_injection_checking($value)) {
			$injection['check'] = $name;
			$injection['key'] = $key;
			$injection['value'] = $value;
			return $injection;
		}
	}
	return $injection;
}

/**
 * 실제 value 값을 preg_match 로 값 체킹
 */
function value_injection_checking($value) {	
	// value가 array인 경우 recursive function 
	if(is_array($value)) {
		foreach($value as $key => $sub_value) {
			return value_injection_checking($sub_value);
		}
	} else {
		$value = urldecode($value);
		return param_injection_checking($value);
	}

	return false;
}

/**
 *인코딩되지 않은 값을 preg_match 로 값 체킹
 */
function param_injection_checking($stringValue) {
	$CI = &get_instance();
	$CI->config->load('injection');
	$eregi_pattern = $CI->config->item('injection_pattern');	
	$sql_injection_checking = false;

	if (preg_match('/' . $eregi_pattern . '/i', $stringValue)) {
		$sql_injection_checking = true;
		return $sql_injection_checking;
	}

	return $sql_injection_checking;
}

/**
 * 관리자에서 예외적인 post 값 정의
 */
function pass_post_value() {
	$CI = &get_instance();
	$managerInfo = $CI->session->userdata('manager');
	$sUrl = uri_string();

	if ($managerInfo['manager_seq']) {
		$pass_post = array(
			'contents',
			'mobile_contents',
			'mobile_contents_hidden',
			'commonContents',
			're_contents',
			'adminMemo',
			'memo'
		);
		if (preg_match("/^admin\/(category|brand|location|page_manager)/i", $sUrl)) {
			$pass_post = array('top_html');
		} else if (preg_match("/^admin\/(design|webftp)/i", $sUrl)) {
			$pass_post[] = 'tpl_source';
			$pass_post[] = 'tplSource';
		} else {
			$pass_post[] = 'view_textarea';
			$pass_post[] = 'domain';
		}
	}
	return $pass_post;
}

