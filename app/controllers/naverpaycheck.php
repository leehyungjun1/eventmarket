<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class naverpaycheck extends CI_Controller {
	// 네이버페이 결제형 도메인 조회 페이지
	public function index(){
		$this->load->helper('basic');

		echo '<meta name="robots" content="noindex, nofollow" />';

		if (check_ftp_close()) {
			$msg = "소스 미오픈(검수 생략)";
		} elseif (check_ftp_open()) {
			$msg = "소스 오픈(독립형/검수 필요)";
		} else {
			$msg = "환경정보 재확인 필요";
		}

		echo $msg;
		exit;
	}
}