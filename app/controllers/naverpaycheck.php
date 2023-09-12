<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class naverpaycheck extends CI_Controller {
	// 네이버페이 결제형 도메인 조회 페이지
	public function index(){
		$this->load->helper('basic');
		if (!$this->config_system) $this->config_system = config_load('system');

		// 호스팅 정보 => RT:임대형 / EX:독립형 / HX:외부호스팅
		$hosting_info = $this->config_system['hosting_info'];
		// FTP 권한 => all:오픈 / data:미오픈
		$ftp = $this->config_system['ftp'];

		echo '<meta name="robots" content="noindex, nofollow" />';

		if ($hosting_info == 'RT' || ($hosting_info == 'EX' && $ftp == 'data')) {
			$msg = "소스 미오픈(검수 생략)";
		} elseif ($hosting_info == 'HX' || ($hosting_info == 'EX' && $ftp == 'all')) {
			$msg = "소스 오픈(독립형/검수 필요)";
		} else {
			$msg = "환경정보 재확인 필요";
		}

		echo $msg;
		exit;
	}
}