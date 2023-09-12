<?php
/**
 * @author sjp
 * @version 1.0.0
 * @license copyright by GABIA_
 * @since 16. 03. 04 11:03
 */


// 발주서 이메일 발송
function sorder_draft_sender($sono, $mode = 'email'){
	$CI =& get_instance();
	$CI->load->model('scmmodel');
	$basicinfo	= ($CI->config_basic) ? $CI->config_basic	: config_load('basic');

	$sorder		= $CI->scmmodel->get_sorder_draft_info($sono);
	$sorder		= $sorder[0];
	$params		= $sorder['replace_values'];
	if($mode == 'sms'){
		if	($sorder['currency'] == 'KRW'){
			$commonSmsData = array();
			$commonSmsData['sorder_draft']['phone'][]	= $sorder['sorder']['trader']['manager']['cellphone_number'];
			$commonSmsData['sorder_draft']['params'][]	= $params;

			$response	= commonSendSMS($commonSmsData);
			$return		= ($response['code'] != '') ? 'SMS 발송실패' : '성공';
		}
	}else{
		if	($sorder['currency'] == 'KRW')	$draft_type		= 'sorder_draft';
		else								$draft_type		= 'sorder_edraft';
		$file_path		= '../../data/email/' . get_lang(true) . '/' . $draft_type . '.html';
		$CI->template->assign($sorder);
		$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";
		$CI->template->define(array('tpl'=>$file_path));
		$out = $CI->template->fetch("tpl");

		$email						= config_load('email');
		$email						= array_merge($email, $params);

		$email['settle_skin']		= $out;
		$email['member_seq']		= $order['member_seq'];
		$email['shopName']			= $CI->config_basic['shopName'];
		$email['ordno']				= '';
		$email['trader_name']		= $sorder['trader']['trader_name'];

		$response	= sendMail($sorder['trader']['manager']['email'], $draft_type, '', $email);

		$return		= (!$response) ? '이메일 발송실패' : '성공';
	}

	return $return;
}

// 취소 발주서 이메일 발송
function sorder_cancel_draft_sender($sono, $mode = 'email'){
	$CI =& get_instance();
	$CI->load->model('scmmodel');
	$basicinfo	= ($CI->config_basic) ? $CI->config_basic	: config_load('basic');

	$sorder		= $CI->scmmodel->get_sorder_draft_info($sono);
	$sorder		= $sorder[0];
	$params		= $sorder['replace_values'];
	$params['sorder_code'] = preg_replace('/C$/', '', $params['sorder_code']); // 취소표시 발주코드 제거
	if($mode == 'sms'){
		if	($sorder['currency'] == 'KRW'){
			$smsData = array();
			$smsData['sorder_cancel_draft']['phone'][]	= $sorder['sorder']['trader']['manager']['cellphone_number'];
			$smsData['sorder_cancel_draft']['params'][]	= $params;

			$response	= commonSendSMS($smsData);
			$return		= ($response['code'] != '') ? 'SMS 발송실패' : '성공';
		}
	}else{
		if	($sorder['currency'] == 'KRW')	$draft_type		= 'sorder_cancel_draft';
		else								$draft_type		= 'sorder_cancel_edraft';
		$file_path		= '../../data/email/' . get_lang(true) . '/' . $draft_type . '.html';
		$CI->template->assign($sorder);
		$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";
		$CI->template->define(array('tpl'=>$file_path));
		$out = $CI->template->fetch("tpl");

		$email						= config_load('email');
		$email						= array_merge($email, $params);

		$email['settle_skin']		= $out;
		$email['member_seq']		= $order['member_seq'];
		$email['shopName']			= $CI->config_basic['shopName'];
		$email['ordno']				= '';
		$email['trader_name']		= $sorder['trader']['trader_name'];

		$response	= sendMail($sorder['trader']['manager']['email'], $draft_type, '', $email);

		$return		= (!$response) ? '이메일 발송실패' : '성공';
	}

	return $return;
}

// 수정 발주서 이메일 발송
function sorder_modify_draft_sender($sono, $mode = 'email'){
	$CI =& get_instance();
	$CI->load->model('scmmodel');
	$basicinfo	= ($CI->config_basic) ? $CI->config_basic	: config_load('basic');

	$sorder		= $CI->scmmodel->get_sorder_draft_info($sono);
	$sorder		= $sorder[0];
	$params		= $sorder['replace_values'];
	if($mode == 'sms'){
		if	($sorder['currency'] == 'KRW'){
			$smsData = array();
			$smsData['sorder_modify_draft']['phone'][]	= $sorder['sorder']['trader']['manager']['cellphone_number'];
			$smsData['sorder_modify_draft']['params'][]	= $params;

			$response	= commonSendSMS($smsData);
			$return		= ($response['code'] != '') ? 'SMS 발송실패' : '성공';
		}
	}else{
		if	($sorder['currency'] == 'KRW')	$draft_type		= 'sorder_modify_draft';
		else								$draft_type		= 'sorder_modify_edraft';
		$file_path		= '../../data/email/' . get_lang(true) . '/' . $draft_type . '.html';
		$CI->template->assign($sorder);
		$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";
		$CI->template->define(array('tpl'=>$file_path));
		$out = $CI->template->fetch("tpl");

		$email						= config_load('email');
		$email						= array_merge($email, $params);

		$email['settle_skin']		= $out;
		$email['member_seq']		= $order['member_seq'];
		$email['shopName']			= $CI->config_basic['shopName'];
		$email['ordno']				= '';
		$email['trader_name']		= $sorder['trader']['trader_name'];

		$response	= sendMail($sorder['trader']['manager']['email'], $draft_type, '', $email);

		$return		= (!$response) ? '이메일 발송실패' : '성공';
	}

	return $return;
}

/**
 * 로케이션 검색어 체크
 */
function locationAllowCheck($keyword) {
	$allowLocation = false;
	if (preg_match("/^[a-zA-Z0-9\\-]+$/i", $keyword)) {
		$allowLocation = true;
	}
	return $allowLocation;
}
// END
/* End of file scm_helper.php */
/* Location: ./app/helpers/scm_helper.php */