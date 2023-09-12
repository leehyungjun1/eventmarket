<?php
$sYearMon		   = date('Ym');
$sCronLog		   = 'daily_member_'.$sYearMon.'.log';
$iFuncNum			= 1;
$aExecFunc[]	= array(
	'sNextIndex'	=> $iFuncNum,
	'sFunctionName'	=> 'member_emoney_deduction',
	'sMsg'			=> '만료된 마일리지 차감',
	'sLogFile'		=> $sCronLog
);
$iFuncNum++;
$aExecFunc[]	= array(
	'sNextIndex'	=> $iFuncNum,
	'sFunctionName'	=> 'member_point_deduction',
	'sMsg'			=> '만료된 포인트 차감',
	'sLogFile'		=> $sCronLog
);
$iFuncNum++;
$aExecFunc[]	= array(
	'sNextIndex'	=> $iFuncNum,
	'sFunctionName'	=> 'log_member_excel_delete',
	'sMsg'			=> '회원정보 다운로드 로그 기록 삭제',
	'sLogFile'		=> $sCronLog
);
$iFuncNum++;
$aExecFunc[]	= array(
	'sNextIndex'	=> $iFuncNum,
	'sFunctionName'	=> 'exec_update_provider_group',
	'sMsg'			=> '입점사 등급 자동 갱신',
	'sLogFile'		=> $sCronLog
);
$iFuncNum++;
$aExecFunc[]	= array(
	'sNextIndex'	=> $iFuncNum,
	'sFunctionName'	=> 'update_member_grade',
	'sMsg'			=> '회원등급 업그레이드',
	'sLogFile'		=> $sCronLog
);
$iFuncNum++;
$aExecFunc[]	= array(
	'sNextIndex'	=> $iFuncNum,
	'sFunctionName'	=> 'dormancy_request',
	'sMsg'			=> '미접속자 휴면처리',
	'sLogFile'		=> $sCronLog
);
$iFuncNum++;
$aExecFunc[]	= array(
	'sNextIndex'	=> $iFuncNum,
	'sFunctionName'	=> 'update_marketing_agree_date',
	'sMsg'			=> '광고수신동의 메일 발송 일자 수정',
	'sLogFile'		=> $sCronLog
);
$iFuncNum++;
$aExecFunc[]	= array(
	'sNextIndex'	=> $iFuncNum,
	'sFunctionName'	=> 'send_marketing_agree',
	'sMsg'			=> '광고수신동의 메일 발송 대상 수집',
	'sLogFile'		=> $sCronLog
);
$iFuncNum++;
$aExecFunc[]	= array(
	'sNextIndex'	=> $iFuncNum,
	'sFunctionName'	=> 'migration_member_separate',
	'sMsg'			=> '휴면/탈퇴 회원 개인정보 주문 분리',
	'sLogFile'		=> $sCronLog
);
foreach($aExecFunc as $key => $dataExecFunc) $aExecFunc[$key]['sCfg'] = str_replace(APPPATH.'config/', '', __FILE__);
$config['aExecFunc'] = $aExecFunc;
?>
