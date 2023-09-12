<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class batch_process extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('admin/setting');
		$this->load->helper('member');
		$this->load->model('membermodel');
		$this->load->model("authmodel");
	}

	public function download_member_zipfile(){

		$downFileList = $_POST['downFileList'];
		$backup_file_name = ($_POST['backup_file_name'])?$_POST['backup_file_name']:'download_member_zipfile_'.date("YmdHi").'.zip';

		//크롬에서 다운안되는 문제 해결
		$this->load->library('zip');
		foreach($downFileList as $filename){
			$this->zip->read_file($filename);
		}
		foreach($downFileList as $filename){
			unlink($filename);
		}
		$this->zip->download($backup_file_name);

	}

	public function sms_member_download(){
		// 회원정보다운로드 체크
		if ($this->managerInfo['manager_yn']=='Y') {
			$auth_member_down = true;
		} else {
			$auth_member_down	= $this->authmodel->manager_limit_act('member_download');
		}

		if(!$auth_member_down){
			echo "<script>alert('다운로드 권한이 없습니다.');</script>";
			exit;
		}

		$sc = $_POST;
		$sc['orderby']			= (isset($_POST['orderby'])) ?	$_POST['orderby']:'A.member_seq';
		$sc['sort']				= (isset($_POST['sort'])) ?		$_POST['sort']:'desc';

		// 판매환경
		if( $_POST['sitetype'] ){
			$sc['sitetype'] = implode('\',\'',$_POST['sitetype']);
		}

		// 가입양식	if( $_POST['rute'] )$sc['rute'] = implode('\',\'',$_POST['rute']);
 		if( $_POST['snsrute'] ) {
			foreach($_POST['snsrute'] as $key=>$val){$sc[$val] = 1;}
		}

		if(!is_dir(ROOTPATH."data/sms")){
			mkdir(ROOTPATH."data/sms");
			chmod(ROOTPATH."data/sms",0777);
		}

		$limittotalnum = 30000;
		$sc['sms_member'] = "y";
		ini_set("memory_limit",-1);

		//3만건 이상일시 3만건씩 나누어 압축하여 다운로드
		if($_POST['mcount'] > $limittotalnum){

			$count = $_POST['mcount'] / $limittotalnum;

			for($i=0; $i<ceil($count); $i++){

				unset($this->db->queries);
				unset($this->db->query_times);
				unset($result);


				$downfilename = $_SERVER['DOCUMENT_ROOT']."/data/sms/member_down_".date("YmdHi")."_".$i.".csv";

				$newline=chr(10); //LF(줄바꿈)의 ascii 값을 얻음

				$fp = fopen($downfilename, "w") or die("Can't open file score.csv ");  //score.csv 를 새로 연다.
				//fputs("\xEF\xBB\xBF",$fp);
				$title = iconv("utf-8", "euc-kr", "번호").",".iconv("utf-8", "euc-kr", "회원일렬번호").",".iconv("utf-8", "euc-kr", "휴대전화").",".iconv("utf-8", "euc-kr", "이메일").",".iconv("utf-8", "euc-kr", "이름");
				fwrite($fp, $title);
				fwrite($fp,$newline);

				if($_POST['searchSelect'] == "select"){
					$sc['member_seq'] = $_POST['selectMember'];
				}else{
					$tempArr = explode("&",urldecode($_POST["serialize"]));
					foreach($tempArr as $k){
						$tmp = explode("=",$k);
						$sc[$tmp[0]] = $tmp[1];													
					}
				}

				//$sc['mailing'] = 'y';
				if($sc["keyword"] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
					$sc["keyword"] = "";
				}
				if( $sc['snsrute'] ) {
					foreach($sc['snsrute'] as $key=>$val){$sc[$val] = 1;}
				}

				$sc['page'] = $i * $limittotalnum;
				$sc['perpage'] = $limittotalnum;
				$sc['batchProcess'] = 'y';
				$result = $this->membermodel->admin_member_list($sc);

				$numCount = 1;
				foreach($result['result'] as $data){
				 $num = $sc['page']+$numCount;
				 if($data['business_seq']){
					$data['user_name'] = $data['bceo'] ? $data['bceo'] : $data['user_name'];
					$data['cellphone'] = $data['bcellphone'] ? $data['bcellphone'] : $data['cellphone'];
				 }
				 fwrite($fp,$num.",".$data['member_seq'].",".$data['cellphone'].",".$data['email'].",".iconv("utf-8", "euc-kr", $data['user_name']));
				 fwrite($fp,$newline);
				 $numCount++;
				}
				fclose($fp);

				$downFileList[$i] = $downfilename;

			}

			echo "<form name='downfrm' method='post' action='../batch_process/download_member_zipfile'>";
			foreach($downFileList as $filename){
				echo "<input type='text' name='downFileList[]' value='".$filename."'>";
			}
			echo "<form>";
			echo "<script>parent.loadingStop(); document.downfrm.submit();</script>";

			$date_info1 = date("Y-m-d");
			$date_info2 = date("H:i:s");

			// 회원정보 다운로드 로그기록
			$manager_id = $this->managerInfo['manager_id'];
			$insert_data = array();
			$insert_data['manager_seq'] = $this->managerInfo['manager_seq'];
			$insert_data['manager_id'] = $manager_id;
			$down_count = $_POST['mcount'];
			$str_down_count = number_format($_POST['mcount'])."명";
			$insert_data['manager_log'] = sprintf("%s %s %s (%s) %s", $date_info1, $date_info2 ,"관리자가($manager_id)가 회원정보($str_down_count)를 다운로드 하였습니다.", $_SERVER['REMOTE_ADDR'], implode(",",$downFileList));
			$insert_data['ip'] = $_SERVER['REMOTE_ADDR'];
			$insert_data['down_count'] = $down_count;
			$insert_data['file_name'] = 'download_member_zipfile.zip';
			$insert_data['reg_date'] = sprintf("%s %s", $date_info1, $date_info2);
			$result = $this->db->insert('fm_log_member_download', $insert_data);

			/* 주요행위 기록 */
			$this->load->model('managermodel');
			$this->managermodel->insert_action_history($this->managerInfo['manager_seq'],'member_excel_download',number_format($_POST['mcount']).'명, download_member_zipfile.zip');

		//3만건 이하일때 csv 형태로 다운로드
		}else{


			unset($this->db->queries);
			unset($this->db->query_times);
			unset($result);

			$downfile = "/data/sms/member_down_".date("YmdHi").".csv";
			$downfilename = $_SERVER['DOCUMENT_ROOT'].$downfile;

			$newline=chr(10); //LF(줄바꿈)의 ascii 값을 얻음

			$fp = fopen($downfilename, "w") or die("Can't open file score.csv ");  //score.csv 를 새로 연다.
			//fputs("\xEF\xBB\xBF",$fp);
			$title = iconv("utf-8", "euc-kr", "번호").",".iconv("utf-8", "euc-kr", "회원일렬번호").",".iconv("utf-8", "euc-kr", "휴대전화").",".iconv("utf-8", "euc-kr", "이메일").",".iconv("utf-8", "euc-kr", "이름");
			fwrite($fp, $title);
			fwrite($fp,$newline);

			if($_POST['searchSelect'] == "select"){
				$sc['member_seq'] = $_POST['selectMember'];
			}else{
				$tempArr = explode("&",urldecode($_POST["serialize"]));
				foreach($tempArr as $k){
					$tmp = explode("=",$k);					
					$sc[$tmp[0]] = $tmp[1];											
				}
			}

			//$sc['mailing'] = 'y';
			if($sc["keyword"] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
				$sc["keyword"] = "";
			}

			$sc['page'] = 0;
			$sc['perpage'] = $limittotalnum;
			if( $sc['snsrute'] ) {
				foreach($sc['snsrute'] as $key=>$val){$sc[$val] = 1;}
			}
			$sc['batchProcess'] = 'y';
			$result = $this->membermodel->admin_member_list($sc);

			$numCount = 1;
			foreach($result['result'] as $data){
			 $num = $sc['page']+$numCount;
			 if($data['business_seq']){
				$data['user_name'] = $data['bceo'] ? $data['bceo'] : $data['user_name'];
					$data['cellphone'] = $data['bcellphone'] ? $data['bcellphone'] : $data['cellphone'];
			 }
			 fwrite($fp,$num.",".$data['member_seq'].",".$data['cellphone'].",".$data['email'].",".iconv("utf-8", "euc-kr", $data['user_name']));
			 fwrite($fp,$newline);
			 $numCount++;
			}
			fclose($fp);


			echo "<form name='downfrm' method='get' action='/common/download'>";
            echo "<input type='text' name='downfile' value='".$downfile."'>";
            echo "<input type='text' name='excelcount' value='".$numCount."'>";
			echo "<input type='text' name='type' value='member'>";
			echo "<input type='text' name='menu' value='".$_GET['callPage']."_member_catalog'>";
			echo "<form>";
			echo "<script>parent.loadingStop(); document.downfrm.submit();</script>";

			$date_info1 = date("Y-m-d");
			$date_info2 = date("H:i:s");

			// 회원정보 다운로드 로그기록
			$manager_id = $this->managerInfo['manager_id'];
			$insert_data = array();
			$insert_data['manager_seq'] = $this->managerInfo['manager_seq'];
			$insert_data['manager_id'] = $manager_id;
			$down_count = $_POST['mcount'];
			$str_down_count = number_format($_POST['mcount'])."명";
			$insert_data['manager_log'] = sprintf("%s %s %s (%s) %s", $date_info1, $date_info2 ,"관리자가($manager_id)가 회원정보($str_down_count)를 다운로드 하였습니다.", $_SERVER['REMOTE_ADDR'], basename($downfilename));
			$insert_data['ip'] = $_SERVER['REMOTE_ADDR'];
			$insert_data['down_count'] = $down_count;
			$insert_data['file_name'] = basename($downfilename);
			$insert_data['reg_date'] = sprintf("%s %s", $date_info1, $date_info2);
			$result = $this->db->insert('fm_log_member_download', $insert_data);

			/* 주요행위 기록 */
			$this->load->model('managermodel');
			$this->managermodel->insert_action_history($this->managerInfo['manager_seq'],'member_excel_download',number_format($_POST['mcount']).'명, '.basename($downfilename));
		}

		//관리자 로그
		$logInfo = array(
			'params'=> array('excelcount' => $_POST['mcount'], 'callPage' => $_POST['callPage']) 
		);
		$this->load->library('managerlog');
		$this->managerlog->insertData($logInfo);
	}

	public function restock_member_download(){

		$this->load->model("goodsmodel");
		// 회원정보다운로드 체크
		if ($this->managerInfo['manager_yn']=='Y') {
			$auth_member_down = true;
		} else {
			$auth_member_down	= $this->authmodel->manager_limit_act('member_download');
		}

		if(!$auth_member_down){
			echo "<script>alert('다운로드 권한이 없습니다.');</script>";
			exit;
		}

		$sc = $this->input->post();
		$sc['orderby']			= (isset($sc['orderby'])) ?	$sc['orderby']:'restock_notify_seq';
		$sc['sort']				= (isset($sc['sort'])) ?	$sc['sort']:'desc';

		// 판매환경
		if( $sc['sitetype'] ){
			$sc['sitetype'] = implode('\',\'',$sc['sitetype']);
		}

		// 가입양식	if( $_POST['rute'] )$sc['rute'] = implode('\',\'',$_POST['rute']);
 		if( $sc['snsrute'] ) {
			foreach($sc['snsrute'] as $key=>$val){$sc[$val] = 1;}
		}

		if(!is_dir(ROOTPATH."data/sms")){
			mkdir(ROOTPATH."data/sms");
			chmod(ROOTPATH."data/sms",0777);
		}

		$sc['sms_member'] = "y";
		ini_set("memory_limit",-1);


		unset($this->db->queries);
		unset($this->db->query_times);
		unset($result);

		$downfile = "/data/sms/restock_member_down_".date("YmdHi").".csv";
		$downfilename = $_SERVER['DOCUMENT_ROOT'].$downfile;

		$newline=chr(10); //LF(줄바꿈)의 ascii 값을 얻음

		$fp = fopen($downfilename, "w") or die("Can't open file score.csv ");  //score.csv 를 새로 연다.
		//fputs("\xEF\xBB\xBF",$fp);
		$title = iconv("utf-8", "euc-kr", "번호").",".iconv("utf-8", "euc-kr", "회원일렬번호").",".iconv("utf-8", "euc-kr", "휴대전화").",".iconv("utf-8", "euc-kr", "이메일").",".iconv("utf-8", "euc-kr", "이름");
		fwrite($fp, $title);
		fwrite($fp,$newline);

		if($sc['searchSelect'] == "select"){
			$sc['restock_notify_seq'] = $sc['selectMember'];
		}else{
			parse_str(urldecode($sc["serialize"]),$tempArr);
			foreach($tempArr as $k => $v){
				if($k){
					$sc[$k] = $v;
				}
			}
		}

		//$sc['mailing'] = 'y';
		if($sc["keyword"] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
			$sc["keyword"] = "";
		}

		$sc['page'] = 1;
		$sc['perpage'] = 30000;

		debug($wheres);
		$result = $this->goodsmodel->restock_notify_list($sc);

		$numCount = 1;
		foreach($result['record'] as $data){
		 $num = $sc['page']+$numCount;
		 fwrite($fp,$num.",".$data['member_seq'].",".$data['cellphone'].",".$data['email'].",".iconv("utf-8", "euc-kr", $data['user_name']));
		 fwrite($fp,$newline);
		 $numCount++;
		}
		fclose($fp);



		$date_info1 = date("Y-m-d");
		$date_info2 = date("H:i:s");

		// 회원정보 다운로드 로그기록
		$manager_id = $this->managerInfo['manager_id'];
		$insert_data = array();
		$insert_data['manager_seq'] = $this->managerInfo['manager_seq'];
		$insert_data['manager_id'] = $manager_id;
		$down_count = $numCount;
		$str_down_count = number_format($down_count)."명";
		$insert_data['manager_log'] = sprintf("%s %s %s (%s) %s", $date_info1, $date_info2 ,"관리자가($manager_id)가 재입고알림정보($str_down_count)를 다운로드 하였습니다.", $_SERVER['REMOTE_ADDR'], basename($downfilename));
		$insert_data['ip'] = $_SERVER['REMOTE_ADDR'];
		$insert_data['down_count'] = $down_count;
		$insert_data['file_name'] = basename($downfilename);
		$insert_data['reg_date'] = sprintf("%s %s", $date_info1, $date_info2);
		$result = $this->db->insert('fm_log_member_download', $insert_data);


		echo "<form name='downfrm' method='get' action='/common/download'>";
		echo "<input type='text' name='downfile' value='".$downfile."'>";
		echo "<form>";
		echo "<script>parent.loadingStop(); document.downfrm.submit();</script>";



		exit;
	}

	# 회원(승인/등급) 일괄변경 엑셀다운로드 @2016-09-19 pjm
	public function grade_member_download(){

		// 회원정보다운로드 체크
		if ($this->managerInfo['manager_yn']=='Y') {
			$auth_member_down = true;
		} else {
			$auth_member_down	= $this->authmodel->manager_limit_act('member_download');
		}

		if(!$auth_member_down){
			echo "<script>alert('다운로드 권한이 없습니다.');</script>";
			exit;
		}

		$sc = $_POST;
		$sc['orderby']			= (isset($_POST['orderby'])) ?	$_POST['orderby']:'A.member_seq';
		$sc['sort']				= (isset($_POST['sort'])) ?		$_POST['sort']:'desc';

		// 판매환경
		if( $_POST['sitetype'] ){
			$sc['sitetype'] = implode('\',\'',$_POST['sitetype']);
		}

		// 가입양식	if( $_POST['rute'] )$sc['rute'] = implode('\',\'',$_POST['rute']);
 		if( $_POST['snsrute'] ) {
			foreach($_POST['snsrute'] as $key=>$val){$sc[$val] = 1;}
		}

		$dir = "data/grade";
		if(!is_dir(ROOTPATH.$dir)){
			mkdir(ROOTPATH.$dir);
			chmod(ROOTPATH.$dir,0777);
		}

		$limittotalnum		= 30000;
		if($_POST['batch_mode'] == "member_status"){
			$sc['status_member']	= "y";
		}else{
			$sc['grade_member']		= "y";
		}
		ini_set("memory_limit",-1);


		//3만건 이상일시 3만건씩 나누어 압축하여 다운로드
		if($_POST['mcount'] > $limittotalnum){

			$downFileList	= array();
			$count			= $_POST['mcount'] / $limittotalnum;

			for($i=0; $i<ceil($count); $i++){

				$downfile		= "/".$dir."/member_down_".date("YmdHi")."_".$i.".csv";
				$page			= $i * $limittotalnum;
				$downfilename	= $this->_member_down_loop($downfile,$page,$limittotalnum);

				$downFileList[$i] = $downfilename;

			}

			$this->_member_download("multi","",$downFileList);

		//3만건 이하일때 csv 형태로 다운로드
		}else{

			$downfile		= "/".$dir."/member_down_".date("YmdHi").".csv";
			$page			= $i * $limittotalnum;
			$downfilename	= $this->_member_down_loop($downfile,0,$limittotalnum);

			$this->_member_download("",$downfile,$downfilename);

		}

		//관리자 로그
		$logInfo = array(
			'params'=> array('excelcount' => $_POST['mcount'], 'callPage' => $_POST['callPage']) 
		);
		$this->load->library('managerlog');
		$this->managerlog->insertData($logInfo);
	}

	# 회원(승인/등급) 일괄변경 엑셀다운로드 내용처리 @2016-09-19 pjm
	public function _member_down_loop($downfile,$page=0,$limittotalnum){

		unset($this->db->queries);
		unset($this->db->query_times);
		unset($result);

		$downfilename	= $_SERVER['DOCUMENT_ROOT'].$downfile;
		$newline		= chr(10); //LF(줄바꿈)의 ascii 값을 얻음
		$fp				= fopen($downfilename, "w") or die("Can't open file score.csv ");  //score.csv 를 새로 연다.
		//fputs("\xEF\xBB\xBF",$fp);

		if($_POST['batch_mode'] == "member_status"){
			$title_arr = array("num"		=> "번호",
								"member_seq"=> "회원일련번호",
								"status_nm"	=> "승인",
								"email"		=> "이메일",
								"user_name"	=> "이름",
						);
		}elseif($_POST['batch_mode'] == "member_grade"){
			$title_arr = array("num"		=> "번호",
								"member_seq"=> "회원일련번호",
								"group_name"=> "회원등급",
								"email"		=> "이메일",
								"user_name"	=> "이름",
						);
		}
		$title = "";
		foreach($title_arr as $key=>$val){
			if($title) $title .= ",";
			$title .= iconv("utf-8", "euc-kr", $val);
		}
		fwrite($fp, $title);
		fwrite($fp,$newline);

		if($_POST['searchSelect'] == "select"){
			$sc['member_seq'] = $_POST['selectMember'];
		}else{
			$tempArr = explode("&",urldecode($_POST["serialize"]));
			foreach($tempArr as $k){
				$tmp = explode("=",$k);				
				$sc[$tmp[0]] = $tmp[1];				
			}
		}

		//$sc['mailing'] = 'y';
		if($sc["keyword"] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
			$sc["keyword"] = "";
		}

		$sc['page']			= $page;
		$sc['perpage']		= $limittotalnum;
		if( $sc['snsrute'] ) {
			foreach($sc['snsrute'] as $key=>$val){$sc[$val] = 1;}
		}
		$sc['batchProcess'] = 'y';
		$result = $this->membermodel->admin_member_list($sc);

		$numCount = 1;
		foreach($result['result'] as $data){

			if($data['business_seq']){
				$data['user_name'] = $data['bceo'] ? $data['bceo'] : $data['user_name'];
			}

			$write_cont = $sc['page']+$numCount;

			foreach($title_arr as $key=>$val){
				if($key == "num") continue;
				if(in_array($key, array("status_nm","group_name","user_name"))){
					$write_cont .= ",".iconv("utf-8", "euc-kr", $data[$key]);
				}else{
					$write_cont .= ",".$data[$key];
				}
			}

			fwrite($fp,$write_cont);
			fwrite($fp,$newline);
			$numCount++;
		}
		fclose($fp);

		return $downfilename ;

	}

	# 회원(승인/등급) 일괄변경 엑셀다운로드 다운실행 및 로그 쌓기  @2016-09-19 pjm
	public function _member_download($mode,$downfile='',$downFileList=''){

		if($mode == "multi"){
			$action = "../batch_process/download_member_zipfile";
			$method = "post";
		}else{
			$action = "/common/download";
			$method = "get";
		}

		echo "<form name='downfrm' method='".$method."' action='".$action."'>";
		if($mode == "multi"){
			foreach($downFileList as $filename) echo "<input type='hidden' name='downFileList[]' value='".$filename."'>";
		}else{
            echo "<input type='hidden' name='downfile' value='".$downfile."'>";
            echo "<input type='text' name='excelcount' value='".$_POST['mcount']."'>";
			echo "<input type='hidden' name='type' value='member'>";
			echo "<input type='hidden' name='menu' value='".$_GET['callPage']."_member_catalog'>";
		}
		echo "<form>";
		echo "<script>parent.loadingStop(); document.downfrm.submit();</script>";

		$date_info1 = date("Y-m-d");
		$date_info2 = date("H:i:s");

		$log_title	= "관리자가($manager_id)가 회원정보($str_down_count)를 다운로드 하였습니다.";

		if($mode == "multi"){
			$filelist	= implode(",",$downFileList);
			$filename	= 'download_member_zipfile.zip';
		}else{
			$filelist	= basename($downfile);
			$filename	= basename($downfile);
		}

		$server_ip				= $_SERVER['REMOTE_ADDR'];
		$action_historoy_title	= number_format($_POST['mcount']).'명, '.$filename;

		// 회원정보 다운로드 로그기록
		$manager_id						= $this->managerInfo['manager_id'];
		$insert_data					= array();
		$insert_data['manager_seq']		= $this->managerInfo['manager_seq'];
		$insert_data['manager_id']		= $manager_id;
		$down_count						= $_POST['mcount'];
		$str_down_count					= number_format($_POST['mcount'])."명";
		$insert_data['manager_log']		= sprintf("%s %s %s (%s) %s",$date_info1,$date_info2,$log_title,$server_ip,$filelist);
		$insert_data['ip']				= $server_ip;
		$insert_data['down_count']		= $down_count;
		$insert_data['file_name']		= $filename;
		$insert_data['reg_date']		= sprintf("%s %s", $date_info1, $date_info2);
		$result							= $this->db->insert('fm_log_member_download', $insert_data);

		/* 주요행위 기록 */
		$this->load->model('managermodel');
		$this->managermodel->insert_action_history($this->managerInfo['manager_seq'],'member_excel_download',$action_historoy_title);

	}
	
	public function set_emoney(){

		$this->load->model('membermodel');

		### Validation
		if($_POST['mcount'] < 1){
			$callback = "";
			openDialogAlert('검색된 회원이 없습니다.',400,140,'parent',$callback);
			exit;
		}

		### Validation
		$this->validation->set_rules('memo', '사유','trim|required|xss_clean');
		//$this->validation->set_rules('emoney', '마일리지','trim|required|xss_clean'); 제대로 작동하지 않음 kmj
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		unset($memberArr);

		if($_POST['emoney'] <= 0){
			$callback = "";
			openDialogAlert('마일리지 항목은 필수 입니다.',400,140,'parent',$callback);
			exit;
		}

		$sc['orderby']			= (isset($_POST['orderby'])) ?	$_POST['orderby']:'A.member_seq';
		$sc['sort']				= (isset($_POST['sort'])) ?		$_POST['sort']:'desc';

		if($_POST['searchSelect'] == "select"){
			$sc['member_seq'] = $_POST['selectMember'];			
		}else{			
			$tempArr = explode("&",urldecode($_POST["serialize"]));			
			foreach($tempArr as $k){
				$tmp = explode("=",$k);
				$sc[$tmp[0]] = $tmp[1];
			}
		}
		
		if($sc['keyword'] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임") $sc['keyword'] = "";

		$_POST['type'] = 'direct';
		$_POST['manager_seq'] = $this->managerInfo['manager_seq'];//마일리지 수동지급시 관리자정보추가
		if($_POST['reserve_select']){
			if($_POST['reserve_select']=='year'){
				//$_POST['limit_date'] = $_POST['reserve_year']."-12-31";
				$_POST['limit_date'] = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$_POST['reserve_year']));
			}else{
				$year = $_POST['reserve_direct'] / 12;
				$month = $_POST['reserve_direct'] % 12;
				$_POST['limit_date'] = date("Y-m-d", mktime(0,0,0,date("m")+$month, date("d"), date("Y")+$year));
			}
		}
		//마일리지 대량 적립 18.03.08 kmj
		$sc['limitCount'] = 10000;
		if($_POST['gb'] == "plus"){
			$sc['callPage'] = 'emoney';
			if($_POST['mcount'] > $sc['limitCount']){
				$this->set_payments_batch($_POST, $sc);
			} else {
				$this->set_payments_new($_POST, $sc);
			}
			exit;
		}

		//$sc['sms_member']	= "y";
		$limittotalnum = 30000;
		$totalCount = 0; // 총 처리 건수
		$count = $_POST['mcount'] / $limittotalnum;
		$_POST['emoney'] = get_cutting_price($_POST['emoney']);

		if($_POST['mcount'] < $limittotalnum){
			$count = 1;
		}
		
		ini_set("memory_limit",-1);
		set_time_limit(0);
		for($i=0; $i<ceil($count); $i++){

			unset($this->db->queries);
			unset($this->db->query_times);
			unset($data);
			unset($memberArr);
			
			$sc['page'] = $i * $limittotalnum;
			$sc['perpage'] = $limittotalnum;


			//$sc['sms_member'] = "y";
			$sc['batchProcess'] = 'y';
			$data = $this->membermodel->admin_member_list_spout($sc);

			$memberArr = $data['result'];

			foreach($memberArr as $k){
				if ($_POST['gb'] == 'minus' && $k['emoney'] >= $_POST['emoney']) {
					$this->membermodel->emoney_insert($_POST, $k['member_seq']);
					$totalCount++;
				}
			}
		}

		//내역 남기기 kmj
		$setData = array(
		    'id'			=> '',
		    'provider_seq'	=> 1,
		    'manager_id'	=> $this->managerInfo['manager_id'],
		    'category'		=> 11,
		    'excel_type'	=> $_POST['searchSelect'],
		    'context'		=> $_POST['serialize'],
		    'count'			=> $totalCount,
		    'file_name'		=> $_POST['memo']."|".$_POST['emoney']."|".$_POST['gb'],
		    'state'			=> 2,
		    'limit_count'	=> 0,
		    'reg_date'		=> date('Y-m-d H:i:s'),
		    'com_date'		=> date('Y-m-d H:i:s'),
		    'expired_date'	=> $_POST['limit_date']
		);
		$this->db->insert('fm_queue', $setData);
		
		$callback = "parent.location.reload();";
		openDialogAlert("마일리지가 적용 되었습니다.",400,140,'parent',$callback);
		exit;
	}

	public function set_point(){
		### Validation
		$this->load->model('membermodel');
		if($_POST['mcount'] < 1){
			$callback = "";
			openDialogAlert('검색된 회원이 없습니다.',400,140,'parent',$callback);
			exit;
		}
		### Validation
		//$this->validation->set_rules('point', '포인트','trim|required|xss_clean');
		$this->validation->set_rules('memo', '사유','trim|required|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		unset($memberArr);

		if($_POST['point'] <= 0){
			$callback = "";
			openDialogAlert('포인트 항목은 필수 입니다.',400,140,'parent',$callback);
			exit;
		}

		$sc['orderby']			= (isset($_POST['orderby'])) ?	$_POST['orderby']:'A.member_seq';
		$sc['sort']				= (isset($_POST['sort'])) ?		$_POST['sort']:'desc';

		if($_POST['searchSelect'] == "select"){			
			$sc['member_seq'] = $_POST['selectMember'];
		}else{
			$tempArr = explode("&",urldecode($_POST["serialize"]));
			foreach($tempArr as $k){				
				$tmp = explode("=",$k);				
				$sc[$tmp[0]] = $tmp[1];								
			}
		}

		if($sc['keyword'] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임") $sc['keyword'] = "";
		$_POST['type'] = 'direct';
		$_POST['manager_seq'] = $this->managerInfo['manager_seq'];//마일리지 수동지급시 관리자정보추가
		if($_POST['reserve_select']){
			if($_POST['reserve_select']=='year'){
				//$_POST['limit_date'] = $_POST['reserve_year']."-12-31";
				$_POST['limit_date'] = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$_POST['reserve_year']));
			}else{
				$year = $_POST['reserve_direct'] / 12;
				$month = $_POST['reserve_direct'] % 12;
				$_POST['limit_date'] = date("Y-m-d", mktime(0,0,0,date("m")+$month, date("d"), date("Y")+$year));
			}
		}

		//포인트 대량 적립 18.03.08 kmj
		$sc['limitCount'] = 10000;
		if($_POST['gb'] == "plus"){
			$sc['callPage'] = 'point';
			if($_POST['mcount'] > $sc['limitCount']){
				$this->set_payments_batch($_POST, $sc);
			} else {
				$this->set_payments_new($_POST, $sc);
			}
			exit;
		}

		//$sc['sms_member']	= "y";
		$limittotalnum = 30000;
		$totalCount = 0; // 총 처리 건수
		$count = $_POST['mcount'] / $limittotalnum;
		$_POST['point'] = get_cutting_price($_POST['point']);

		if($_POST['mcount'] < $limittotalnum){
			$count = 1;
		}
		ini_set("memory_limit",-1);
		set_time_limit(0);
		for($i=0; $i<ceil($count); $i++){
			unset($this->db->queries);
			unset($this->db->query_times);
			unset($data);
			unset($memberArr);

			$sc['page'] = $i * $limittotalnum;
			$sc['perpage'] = $limittotalnum;


			$data = $this->membermodel->admin_search_list($sc);
			$memberArr = $data['result'];


			foreach($memberArr as $k){
				if ($_POST['gb'] == 'minus' && $k['point'] >= $_POST['point']) {
					$this->membermodel->point_insert($_POST, $k['member_seq']);
					$totalCount++;
				}
			}
		}

		//내역 남기기 kmj
		$setData = array(
		    'id'			=> '',
		    'provider_seq'	=> 1,
		    'manager_id'	=> $this->managerInfo['manager_id'],
		    'category'		=> 12,
		    'excel_type'	=> $_POST['searchSelect'],
		    'context'		=> $_POST['serialize'],
		    'count'			=> $totalCount,
		    'file_name'		=> $_POST['memo']."|".$_POST['point']."|".$_POST['gb'],
		    'state'			=> 2,
		    'limit_count'	=> 0,
		    'reg_date'		=> date('Y-m-d H:i:s'),
		    'com_date'		=> date('Y-m-d H:i:s'),
		    'expired_date'	=> $_POST['limit_date']
		);
		$this->db->insert('fm_queue', $setData);

		$callback = "parent.location.reload(); parent.document.getElementById('container').src='../member/point_form';";
		openDialogAlert("포인트가 적용 되었습니다.",400,140,'parent',$callback);
		exit;
	}

	//지급 프로세스 수정 18.03.19 kmj
	public function set_payments_new($params, $sc){		
		ini_set("memory_limit", -1);
		set_time_limit(0);
		
		if($sc['callPage'] == 'emoney'){
			$txtCallPage = "마일리지";
			$category = '11';
		} else if($sc['callPage'] == 'point'){
			$txtCallPage = "포인트";
			$category = '12';
		} else {
			echo "No Category.";
			exit;
		}

		$this->load->helper('file');

		unset($sc['page']); 
		unset($sc['perpage']); 
		unset($sc['dormancy_count']);

		$sc['excel_spout']		= true;
		$sc['gb']				= $params['gb'];
		$sc[$sc['callPage']]	= $params[$sc['callPage']]; //카테고리 구분 (emoney, point, etc)
		$sc['nolimit']			= 'y';
		
		$totalCnt = 0;

		$data = array();
		$data = filter_keys($params, $this->db->list_fields("fm_{$sc['callPage']}"));
		$data['regist_date'] = date("Y-m-d H:i:s");
		 
		//20210817(kjw) : 가비아 보안점검에 의한 query 로그 주석처리
		//write_file(ROOTPATH."/data/tmp/{$sc['callPage']}_set_query_".date('YmdHis', strtotime($data['regist_date'])).".txt", serialize($sc), 'a');

		unset($this->db->queries); //쿼리 로그 쓰기 위한 셋팅
		unset($this->db->query_times);
		$sc['batchProcess'] = 'y';
		unset($sc['excel_spout']);
		$memberArr = $this->membermodel->admin_member_list_spout($sc);
		
		$dataBatch	 = array();
		$dataBatchUp = array();
		foreach($memberArr['result'] as $k){
			$dataArr					= $data;
			$dataArr['member_seq']		= $k['member_seq'];
			$dataArr[$sc['callPage']]	= $sc[$sc['callPage']];
			$dataArr['remain']			= $sc[$sc['callPage']];
			$dataBatch[]				= $dataArr;

			$dataArrUp					= array();
			$dataArrUp['member_seq']	= $k['member_seq'];
			$dataArrUp[$sc['callPage']]	= "`{$sc['callPage']}`+".$sc[$sc['callPage']];
			$dataBatchUp[]				= $dataArrUp;
			
			$totalCnt++;
		}

		if($sc[$sc['callPage']] <= 0){
			$callback = "parent.location.reload();";
			openDialogAlert($txtCallPage." 항목은 필수 입니다.", 400, 140, 'parent', $callback);
			exit;
		}		
		if($totalCnt != $params['mcount']){
			$callback = "parent.location.reload();";
			openDialogAlert("에러가 발생 했습니다. 에러가 반복 될 경우 관리자에게 문의 하세요.", 400, 140, 'parent', $callback);
			exit;
		}

		$this->db->insert_batch("fm_{$sc['callPage']}", $dataBatch);
		$this->db->update_batch("fm_member", $dataBatchUp, 'member_seq', false); //escape 여부
		
		//20210817(kjw) : 가비아 보안점검에 의한 query 로그 주석처리
		//write_file(ROOTPATH."/data/tmp/{$sc['callPage']}_set_query_".date('YmdHis', strtotime($data['regist_date'])).".txt", serialize($this->db->queries), 'a');
			
		unset($this->db->queries);
		unset($this->db->query_times);

		if($sc['callPage'] == 'emoney'){
		    $category = '11';
		} else if($sc['callPage'] == 'point'){
		    $category = '12';
		}

		$setData = array(
		    'id'			=> '',
		    'provider_seq'	=> 1,
		    'manager_id'	=> $this->managerInfo['manager_id'],
		    'category'		=> $category,
		    'excel_type'	=> $params['searchSelect'],
		    'context'		=> $params['serialize'],
		    'count'			=> $totalCnt,
		    'file_name'		=> $params['memo']."|".$params[$sc['callPage']]."|".$params['gb'],
		    'state'			=> 2,
		    'limit_count'	=> 0,
		    'reg_date'		=> $data['regist_date'],
		    'com_date'		=> date('Y-m-d H:i:s'),
		    'expired_date'	=> $params['limit_date']
		);
		$this->db->insert('fm_queue', $setData);

		$callback = "parent.location.reload();";
		openDialogAlert($totalCnt."명에게 ".$txtCallPage."가 지급 되었습니다.", 400, 140, 'parent', $callback);
		exit;
	}

	//대량 지급 프로세스 추가 18.03.19 kmj
	public function set_payments_batch($params, $sc){	
		unset($sc['excel_spout']);	
		if($sc['callPage'] == 'emoney'){
			$txtCallPage = "마일리지";
			$category = '11';
		} else if($sc['callPage'] == 'point'){
			$txtCallPage = "포인트";
			$category = '12';
		} else {
			echo "No Category.";
			exit;
		}
		
		//비동기 처리 시작
		//state 0:대기, 1:작업중, 2:완료
		$query	 = 'SELECT id FROM fm_queue WHERE category = ? AND state = ? LIMIT 1';
		$queryDB = $this->db->query($query, array($category, 1));
		$res	 = $queryDB->result_array();
		if( !empty($res) ){
			//작업중인게 있으면 안받음
			echo "Wait for a job No.".$res[0]['id']."!\n";
			exit;
		}

		//type  11:마일리지
		$regDate = date('Y-m-d H:i:s');

		$setData = array(
			'id'			=> '',
			'provider_seq'	=> 1,
			'manager_id'	=> $this->managerInfo['manager_id'],
			'category'		=> $category,
			'excel_type'	=> $params['searchSelect'], 
			'context'		=> $params['serialize'],
			'count'			=> $params['mcount'],
			'file_name'		=> $params['memo']."|".$params[$sc['callPage']]."|".$params['gb'],
			'state'			=> 0,
			'limit_count'	=> $sc['limitCount'],
			'reg_date'		=> $regDate
		);

		$this->db->insert('fm_queue', $setData);
		$queueID = $this->db->insert_id();
		if( $queueID > 0 ){
			$shopdomainArr = parse_url(base_url(uri_string()));
			$shopdomain	= $shopdomainArr['host'];

			$postParams = array(
				'queueID'		=> $queueID, 
				'limitCount'	=> 10000, 
				'manager_id'	=> $this->managerInfo['manager_id'],
				'callPage'		=> $sc['callPage'], 
				'amount'		=> $params[$sc['callPage']], 
				'gb'			=> $params['gb'],
				'memo'			=> $params['memo'],
				'limit_date'	=> $params['limit_date']
			);
			$post_string = http_build_query($postParams);

			if(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'){
				$shopport = 'https://';	
			} else {
				$shopport = 'http://';	
			}
			
			$parts = parse_url($shopport.$shopdomain.'/cli/batch_payments/set_payments');
			if ($parts['scheme'] == 'http'){  
				$fp = fsockopen($parts['host'], isset($parts['port'])?$parts['port']:80, $errno, $errstr, 30);  
			}else if ($parts['scheme'] == 'https'){  
				$fp = fsockopen("ssl://" . $parts['host'], isset($parts['port'])?$parts['port']:443, $errno, $errstr, 30);  
			}  

			if (!$fp) {
				echo "$errstr ($errno), open sock erro.<br/>\n";
				exit;
			}
		  
			fwrite($fp, "POST ".$parts['path']." HTTP/1.1\r\n");
			fwrite($fp, "Host: ".$parts['host']."\r\n");
			fwrite($fp, "Content-Type: application/x-www-form-urlencoded\r\n");
			fwrite($fp, "Content-Length: ".strlen($post_string)."\r\n");
			fwrite($fp, "Connection: close\r\n");
			fwrite($fp, "\r\n");

			fwrite($fp, $post_string);
			//response 확인 할때 만
			/*
			while (!feof($fp)) {
				echo fgets($fp, 128);
			}
			*/
			//response 확인 할때 만
			fclose($fp);

			$callback = "parent.location.reload();";
			openDialogAlert("지급 요청 완료 (회원 > ".$txtCallPage." 지급내역에서 확인 가능)", 400, 140, 'parent', $callback);
			exit;
		} else {
			echo "Job Insert Errors";
			exit;
		}

	}

	# 회원(승인/등급) 일괄변경 @2016-09-12 pjm
	public function set_grade(){

		### Validation
		$this->load->model('membermodel');
		if($_POST['mcount'] < 1){
			$callback = "";
			openDialogAlert('검색된 회원이 없습니다.',400,140,'parent',$callback);
			exit;
		}

		if($_POST['batch_mode'] == "member_grade"){
			### Validation
			$this->validation->set_rules('member_old_grade', '기존 등급','trim|required|xss_clean');
			$this->validation->set_rules('member_new_grade', '변경할 등급','trim|required|xss_clean');
			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,140,'parent',$callback);
				exit;
			}

			if($_POST['member_old_grade'] == $_POST['member_new_grade']){
				openDialogAlert("같은 등급으로 변경 불가합니다.",400,140,'parent',$callback);
				exit;
			}
			$message = "회원 등급변경이 완료되었습니다.";
		}else{
			$message = "회원 승인이 완료되었습니다.";
		}

		unset($memberArr);

		$sc['orderby']			= (isset($_POST['orderby'])) ?	$_POST['orderby']:'A.member_seq';
		$sc['sort']				= (isset($_POST['sort'])) ?		$_POST['sort']:'desc';
		$sc['nolimit']			= "n";

		if($_POST['searchSelect'] == "select"){
			$sc['member_seq'] = $_POST['selectMember'];
		}else{
			$tempArr = explode("&",urldecode($_POST["serialize"]));
			foreach($tempArr as $k){
				$tmp = explode("=",$k);
				$sc[$tmp[0]] = $tmp[1];				
			}
		}
		$app = config_load('member');

		$limittotalnum = 30000;

		$count = $_POST['mcount'] / $limittotalnum;

		if($_POST['mcount'] < $limittotalnum){
			$count = 1;
		}
		ini_set("memory_limit",-1);
		for($i=0; $i<ceil($count); $i++){

			unset($this->db->queries);
			unset($this->db->query_times);
			unset($data);
			unset($memberArr);

			$sc['page']		= $i * $limittotalnum;
			$sc['perpage']	= $limittotalnum;

			$data = $this->membermodel->admin_search_list($sc);
			
			// 회원승인
			if($_POST['batch_mode'] == "member_status"){
				foreach($data['result'] as $member) {
					if(($member['mtype'] != 'business' && $app['autoApproval']=='N') || ($member['mtype'] =='business' && $app['autoApproval_biz']=='N')) {
						$this->load->model('emoneymodel');
						$this->load->model('pointmodel');

						### 특정기간
						if($app['start_date'] && $app['end_date']){
							$today = date("Y-m-d");
							if($today>=$app['start_date'] && $today<=$app['end_date']){
								$app['emoneyJoin']	= get_cutting_price($app['emoneyJoin_limit']);
								$app['pointJoin']	= get_cutting_price($app['pointJoin_limit']);
							}
						}

						if( $app['emoneyJoin'] ) {
							$joinsc['whereis'] = ' and type = \'join\' and gb = \'plus\' and member_seq = \''.$member['member_seq'].'\' ';
							$joinsc['select']	= ' emoney_seq ';
							$emjoinck = $this->emoneymodel->get_data_numrow($joinsc);//가입마일리지 지급여부
							if(!$emjoinck){
								### EMONEY
								$emoney['type']			= 'join';
								$emoney['emoney']		= get_cutting_price($app['emoneyJoin']);
								$emoney['gb']			= 'plus';
								$emoney['memo']			= '회원 가입 마일리지';
								$emoney['memo_lang']	= $this->membermodel->make_json_for_getAlert("mp288");   // 회원 가입 마일리지
								$emoney['limit_date'] = get_emoney_limitdate('join');
								$this->membermodel->emoney_insert($emoney, $member['member_seq']);
							}
						}

						if( $app['pointJoin'] ) {
							$joinsc['whereis'] = ' and type = \'join\' and gb = \'plus\' and member_seq = \''.$member['member_seq'].'\' ';
							$joinsc['select']	= ' point_seq ';
							$emjoinck = $this->pointmodel->get_data_numrow($joinsc);//가입포인트 지급여부
							if(!$emjoinck){
								### POINT
								$iparam['gb']			= "plus";
								$iparam['type']			= 'join';
								$iparam['point']		= get_cutting_price($app['pointJoin']);
								$iparam['memo']			= '회원 가입 포인트';
								$iparam['memo_lang']	= $this->membermodel->make_json_for_getAlert("mp289");   // 회원 가입 포인트
								$iparam['limit_date']	= get_point_limitdate('join');
								$this->membermodel->point_insert($iparam, $member['member_seq']);
							}
						}
						
						//추천시
						if($member['recommend'] != ''){
							$chk = get_data("fm_member",array("userid"=>$params['recommend'],"status"=>"done"));
							if($chk[0]['member_seq']) {
								//추천받은자의 추천받은건수 증가 @2013-06-19
								$this->membermodel->member_recommend_cnt($chk[0]['member_seq']);

								//추천 받은 자 -> 제한함
								$todaymonth = date("Y-m");
								if($app['emoneyRecommend']>0) {
									$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\' and ( member_seq_to = \''.$member['member_seq'].'\' or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
									$recommendtosc['select']	 = ' emoney_seq ';
									$emrecommendtock = $this->emoneymodel->get_data_numrow($recommendtosc);//추천한 회원 마일리지 지급여부
									if( !$emrecommendtock ) {
										$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
										$recommendtosc['select']	 = ' count(*) as totalcnt, sum(emoney) as totalemoney ';
										$emrecommendtock = $this->emoneymodel->get_data($recommendtosc);//추천한 회원 마일리지 지급여부
										$maxrecommend = ($app['emoneyLimit']*$app['emoneyRecommend']);

										if( $emrecommendtock['totalcnt'] < $app['emoneyLimit'] && $emrecommendtock['totalemoney'] <= $maxrecommend ) {
											unset($emoney);
											$emoney['type']				= 'recommend_to';
											$emoney['emoney']			= get_cutting_price($app['emoneyRecommend']);
											$emoney['gb']				= 'plus';
											$emoney['memo']				= '추천 회원 마일리지';
											$emoney['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp281");   // 추천 회원 마일리지
											$emoney['limit_date']		= get_emoney_limitdate('recomm');
											$emoney['member_seq_to']	= $member['member_seq'];//2015-02-16
											$this->membermodel->emoney_insert($emoney, $chk[0]['member_seq']);
										}
									}
								}
								if($app['pointRecommend']>0) {
									$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\' and ( member_seq_to = \''.$member['member_seq'].'\' or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
									$recommendtosc['select']	 = ' point_seq ';
									$emrecommendtock = $this->pointmodel->get_data_numrow($recommendtosc);//추천한 회원 포인트 지급여부
									if( !$emrecommendtock ) {//추천 받은 자 -> 제한함
										$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
										$recommendtosc['select']	 = ' count(*) as totalcnt, sum(point) as totalepoint ';
										$pmrecommendtock = $this->pointmodel->get_data($recommendtosc);//추천한 회원 포인트 지급여부
										$maxrecommend = ($app['pointLimit']*get_cutting_price($app['pointRecommend']));

										if( $pmrecommendtock['totalcnt'] < $app['pointLimit'] && $pmrecommendtock['totalepoint'] <= $maxrecommend ) {
											$point['type']				= 'recommend_to';
											$point['point']				= get_cutting_price($app['pointRecommend']);
											$point['gb']				= 'plus';
											$point['memo']				= '추천 회원 포인트';
											$point['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp282");   // 추천 회원 포인트
											$point['limit_date']		= get_point_limitdate('recomm');
											$point['member_seq_to']		= $member['member_seq'];//2015-02-16
											$this->membermodel->point_insert($point, $chk[0]['member_seq']);
										}
									}
								}

								if($app['emoneyJoiner']>0){
									$recommendfromsc['whereis'] = ' and type = \'recommend_from\' and gb = \'plus\' and member_seq = \''.$member['member_seq'].'\' and ( member_seq_to = \''.$chk[0]['member_seq'].'\'  or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) )  ';
									$recommendfromsc['select']	 = ' emoney_seq ';
									$emrecommendfromck = $this->emoneymodel->get_data_numrow($recommendfromsc);//추천받고가입한회원 마일리지 지급여부
									if(!$emrecommendfromck) {//추천한자(가입자)
										unset($emoney);
										$emoney['type']					= 'recommend_from';
										$emoney['emoney']				= get_cutting_price($app['emoneyJoiner']);
										$emoney['gb']					= 'plus';
										$emoney['memo']					= '추천 마일리지';
										$emoney['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp279");   // 추천 마일리지
										$emoney['limit_date']			= get_emoney_limitdate('joiner');
										$emoney['member_seq_to']		= $chk[0]['member_seq'];//2015-02-16
										$this->membermodel->emoney_insert($emoney, $member['member_seq']);
									}
								}

								if($app['pointJoiner']>0){
									$recommendfromsc['whereis'] = ' and type = \'recommend_from\' and gb = \'plus\' and member_seq = \''.$member['member_seq'].'\' and ( member_seq_to = \''.$chk[0]['member_seq'].'\'  or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
									$recommendfromsc['select']	 = ' point_seq ';
									$pmrecommendfromck = $this->pointmodel->get_data_numrow($recommendfromsc);//추천받고가입한회원 포인트 지급여부
									if(!$pmrecommendfromck) {//추천한자(가입자)
										unset($point);
										$point['type']				= 'recommend_from';
										$point['point']				= get_cutting_price($app['pointJoiner']);
										$point['gb']				= 'plus';
										$point['memo']				= '추천 포인트';
										$point['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp280");   // 추천 포인트
										$point['limit_date']		= get_point_limitdate('joiner');
										$point['member_seq_to']		= $chk[0]['member_seq'];//2015-02-16
										$this->membermodel->point_insert($point, $member['member_seq']);
									}
								}
							}
						}
					}
					$memberArr[] = $member['member_seq'];
				}
				$this->membermodel->set_confirm_update($memberArr);
			//등급변경
			}elseif($_POST['batch_mode'] == "member_grade"){
				foreach($data['result'] as $member){
					$memberArr[] = $member['member_seq'];
				}
				$this->membermodel->set_grade_update($memberArr,$_POST['member_old_grade'],$_POST['member_new_grade']);
			}
		}

		$callback = "parent.location.reload();";
		openDialogAlert($message,400,140,'parent',$callback);
		exit;
	}

	public function send_sms(){

		$this->load->model("smsmodel");
		$sms_result	= $this->smsmodel->smsAuth_chk();
		$smsAuth	= $sms_result['auth'];
		if(!$smsAuth){
			if(!$sms_result['msg']) $sms_result['msg'] = '재인증이 필요합니다.';
			openDialogAlert($sms_result['msg'],400,150,"parent","parent.location.reload();");
			exit;
		}

		// 발송가능 잔여건수 체크
		$limit	= commonCountSMS();
		if((int)$limit < 1) {
			openDialogAlert("발송 가능한 잔여건수가 없습니다.",400,140,"parent","parent.closeDialog('processDiv');");
			exit;
		}
		

		// 파일로깅
		$this->smsmodel->sms_log_write($this->input->post('send_message'));

		echo "<style>body {margin:0;padding:0}</style>";
		echo str_repeat(" \r\n",2048);
		echo "<table id='processTable' width='100%' ><tr><td height='30' id='processTableTd' align='center' style='color:#eeeeee; line-height:30px;'><span style='color:#000000;'>발송 준비중입니다...</span></td></tr></table>";
		echo str_repeat(" \r\n",2048);
		flush();
		ob_flush();
		sleep(1);

		### Validation
		$this->validation->set_rules('send_message', '내용','trim|required');
		$this->validation->set_rules('send_sms', '보내는사람','trim|required|max_length[50]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus(); parent.closeDialog('processDiv');";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if($_POST["send_to"] == "전화번호는 ,(콤마)로 구분하여 입력하세요"){
			$_POST["send_to"] = "";
		}

		if($_POST['send_sms'] == ""){
			echo "<script>parent.closeDialog('processDiv'); parent.openSmsSend();</script>";
			exit;
		}

		// 발송시간 제한
		if ($_POST['sms_reserve_yn'] == 'y') {
			$date = $_POST['reserve_date'] ? $_POST['reserve_date'] : date('Y-m-d');
			$reserve_date = $date . ' ' . $_POST['reserve_hour'] . ':' . $_POST['reserve_min'] . ':00';
			$timenow = date('Y-m-d H:i:s');

			// 설정한 시간이 현재 시각과 같거나 이전 시간인 경우 다음날 해당시간으로 발송
			if ($timenow >= $reserve_date) {
				$reserve_date = date('Y-m-d H:i:s', strtotime('+1 day', strtotime($reserve_date)));
			}
			$this->sms_reserve = $reserve_date;
		}

		###
		$phoneNo = explode(",", $_POST["send_to"]);


		foreach($phoneNo as $cellphone){
			if($cellphone){
				$params['userName']	= "고객";
				$commonSmsData['member']['phone'][] = $cellphone;
				$commonSmsData['member']['params'][] = $params;
			}
		}

		//받는사람(직접)이 없거나 받는사람(검색) 검색회원이 없으면 경고
		if( $_POST['search_member_yn']=='y' && $_POST['mcount'] < 1 ) {
			openDialogAlert("받는 사람(검색)시 검색회원이 없습니다.",400,140,"parent","parent.closeDialog('processDiv');");
			exit;
		}

		if( ( $_POST['search_member_yn']=='y' && $_POST['mcount'] < 1) && count($commonSmsData['member']['phone']) < 1 ) {
			openDialogAlert("받는사람이 없습니다.",400,140,"parent","parent.closeDialog('processDiv');");
			exit;
		}

		$key = get_shop_key();

		$sc['orderby']			= (isset($_POST['orderby'])) ?	$_POST['orderby']:'A.member_seq';
		$sc['sort']				= (isset($_POST['sort'])) ?		$_POST['sort']:'desc';

		if($_POST['search_member_yn']=='y'){
			$sc['sms_member'] = "y";

			if($_POST['searchSelect'] == "select"){
				$sc['member_seq'] = $_POST['selectMember'];
			}else{
				$tempArr = explode("&",urldecode($_POST["serialize"]));
				foreach($tempArr as $k){
					$tmp = explode("=",$k);					
					$sc[$tmp[0]] = $tmp[1];					
				}
			}

			$sc['page'] = 0;
			$sc['perpage'] = 30000;
			if($sc['keyword'] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임") $sc['keyword'] = "";
			$sc['batchProcess'] = 'y';
			$data = $this->membermodel->admin_member_list($sc);
			$totalCount = count($data['result']);
			$sendCnt = 0;
			if(count($data['result'])>0){
				foreach($data['result'] as $k){
					$sendCnt++;
					$percent = $sendCnt / $totalCount * 100;
					if($percent > 99) $percent = 99;

					echo "<script>
					document.all.processTable.width='".$percent."%';
					document.all.processTableTd.style.background='#0082ec';
					document.all.processTableTd.innerHTML='".(int)$percent."%';
					</script>";
					echo str_repeat(" \r\n",2048);
					flush();
					ob_flush();

					//array_push($phoneNo,$k['cellphone']);
					if($k['business_seq'] ) { //기업회원
						if($k['bcellphone']){
							$params['userName']	= $k['bceo'];
							$commonSmsData['member']['phone'][] = preg_replace("/[^0-9]*/s", "", $k['bcellphone']);
							$commonSmsData['member']['params'][] = $params;
						}
					}else{
						if($k['cellphone']){
							$params['userName']	= $k['user_name'];
							$commonSmsData['member']['phone'][] = preg_replace("/[^0-9]*/s", "", $k['cellphone']);
							$commonSmsData['member']['params'][] = $params;
						}
					}


				}
			}
		}


		$callback = "parent.closeDialog('processDiv');";
		if(count($commonSmsData['member']['phone']) < 1){
			openDialogAlert('받는사람이 없습니다.',400,140,'parent',$callback);
			exit;
		}

		if(count($commonSmsData['member']['phone']) > 1000){
			$result_msg = "1,000명 이상은 받는 사람을 엑셀로 다운로드 받아<br> 대량SMS 발송 기능으로 SMS를 보내 주십시오.";
			openDialogAlert($result_msg,400,140,'parent',$callback);
			exit;

		}

		$limit	= commonCountSMS();
		if(count($commonSmsData['member']['phone']) > $limit){
			$result_msg = "잔여 건수가 부족합니다. 받는 사람을 엑셀로 다운로드 받아 <a href=\"/admin/batch/sms\" target=\"_blank\"><span class=\"orange\"><b>대량SMS 발송</b></span></a> 기능을 이용하여 발송해 주십시오.";
			openDialogAlert($result_msg,400,140,'parent',$callback);
			exit;

		}



		$result = commonSendSMS($commonSmsData);

		if($result['msg'] == "fail"){
			$result_msg = "%이후에는 문자가 올수 없습니다.<br>예) 30%DC -> 30% DC";
		}else{
			$result_code = $result['code'];
			if($result_code != "0000"){
				if($result_code == "E001"){
					$result_msg = "SMS 인증 정보가 잘못되었습니다.";
				}else{
					$result_msg = $sms_type."발송에 실패했습니다.";
				}
			}else{
				$callback = "parent.location.reload();";
				$result_msg = "문자 시스템에 접수되어 순차적으로 발송처리됩니다.";
			}
		}

		echo "<script>
		document.all.processTable.width='100%';
		document.all.processTableTd.style.background='#0082ec';
		document.all.processTableTd.innerHTML='100%';
		</script>";
		echo str_repeat(" \r\n",2048);
		flush();
		ob_flush();


		openDialogAlert($result_msg,400,140,'parent',$callback);
		exit;
	}

	public function send_email(){

		echo "<style>body {margin:0;padding:0}</style>";
		echo str_repeat(" \r\n",2048);
		echo "<table id='processTable' width='100%' ><tr><td height='30' id='processTableTd' align='center' style='color:#eeeeee; line-height:30px;'><span style='color:#000000;'>발송 준비중입니다...</span></td></tr></table>";
		echo str_repeat(" \r\n",2048);
		flush();
		ob_flush();
		sleep(1);

		### Validation
		$this->validation->set_rules('title', '제목','trim|required|max_length[100]|xss_clean');
		//$this->validation->set_rules('contents', '내용','trim|required|xss_clean');
		$this->validation->set_rules('send_email', '보내는사람','trim|required|max_length[50]|valid_email|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus(); parent.closeDialog('processDiv');";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		###
		$callback = 'parent.closeDialog("processDiv");';
		unset($mailArr);

		$aParmasPost = $this->input->post();

		if($aParmasPost["send_to"] && $aParmasPost["send_to"] != "메일 주소를 입력하세요"){
			$mailArr = explode(",", $aParmasPost["send_to"]);
		}else{
			$mailArr = array();
		}

		$sc['orderby']			= (isset($aParmasPost['orderby'])) ?	$aParmasPost['orderby']:'A.member_seq';
		$sc['sort']				= (isset($aParmasPost['sort'])) ?		$aParmasPost['sort']:'desc';

		if($aParmasPost['search_member_yn']=='y'){
			$key = get_shop_key();
			//echo urldecode($aParmasPost["serialize"]);

			if($aParmasPost['searchSelect'] == "select"){
				$sc['member_seq'] = $aParmasPost['selectMember'];
			}else{
				$tempArr = explode("&",urldecode($aParmasPost["serialize"]));
				foreach($tempArr as $k){					
					$tmp = explode("=",$k);
					$sc[$tmp[0]] = $tmp[1];					
				}
			}

			$sc['page'] = 0;
			$sc['perpage'] = 30000;
			$sc['sms_member'] = "y";

			if($sc["keyword"] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
				$sc["keyword"] = "";
			}
			$sc['batchProcess'] = 'y';
			$data = $this->membermodel->admin_member_list($sc);
			if(count($data['result'])>0){
				foreach($data['result'] as $k){
					array_push($mailArr,$k['email']);
				}
			}
		}

		if (count($mailArr) < 1) {
			$callback = 'parent.closeDialog("processDiv");';
			openDialogAlert('받는 사람이 없습니다.',400,140,'parent',$callback);
			exit;
		}

		###
		$this->load->model('usedmodel');
		$email_chk = $this->usedmodel->hosting_check();

		###
		$total 		= count($mailArr);
		$toMonth 	= date("Y-m");
		$sql 		= "select sum(total) as count from fm_log_email where gb='MANUAL' and regdate like '{$toMonth}%'";
		$query 		= $this->db->query($sql);
		$emailData 	= $query->result_array();
		$usedMail	= $emailData[0]['count'] + $total;

		if(3000 < $usedMail  && !$email_chk && !(preg_match("/^F_SH_/",$this->config_system['service']['hosting_code']) || preg_match("/^SH_D_/",$this->config_system['service']['hosting_code']) || preg_match("/^SH_T_/",$this->config_system['service']['hosting_code']) || preg_match("/^SH_A_/",$this->config_system['service']['hosting_code']))) {
			$callback = "";
			openDialogAlert('본 이메일 발송 기능은 월 3,000통 발송이 가능합니다.<br>더 많은 이메일 발송은 대량발송 서비스를 이용해 주십시오.',400,140,'parent',$callback);
			exit;
		}

		###
		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');
		//sendDirectMail($mailArr, $aParmasPost['send_email'], $aParmasPost['title'], $aParmasPost['contents']);
		require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/Email_send.class.php";
		$mail 	= new Mail(isset($params));
		$bodys 	= adjustEditorImages($aParmasPost['contents']);


		$sendCnt 	= 0;
		$totalCount = count($mailArr);
		foreach($mailArr as $k){

			if(filter_var(trim($k),FILTER_VALIDATE_EMAIL)!=false){
				$sendCnt++;
				/**
				 * 치환 데이터
				**/
				$unsubscribeKey 		= urlencode(base64_encode($k));
				$body 					= str_replace("{unSubScribeKey}", $unsubscribeKey, $bodys);
				$body 					= str_replace("{protocol}", get_connet_protocol(), $body);

				$headers['From']    	= $aParmasPost['send_email'];
				$headers['Name']		= !$basic['companyName'] ? get_connet_protocol().$_SERVER['HTTP_HOST'] : $basic['companyName'];
				$headers['Subject'] 	= $aParmasPost['title'];
				$headers['To'] 			= trim($k);

				$params['location'] 	= "member_list";
				$params['subject'] 		= $aParmasPost['title'];
				$params['contents'] 	= $body;
				$params['from_name'] 	= $headers['Name'];
				$params['from_email'] 	= $aParmasPost['send_email'];
				$params['to_email'] 	= trim($k);
				$params['division'] 	= "user";
				$params['regist_date'] 	= date("Y-m-d H:i:s");

				$this->db->insert("fm_email", $params);

				$percent = $sendCnt / $totalCount * 100;
				if($percent > 99) $percent = 99;


				echo "<script>
				document.all.processTable.width='".$percent."%';
				document.all.processTableTd.style.background='#0082ec';
				document.all.processTableTd.innerHTML='".(int)$percent."%';
				</script>";
				echo str_repeat(" \r\n",2048);
				flush();
				ob_flush();


				//$resSend = $mail->send($headers, $body);
			}
		}

		unset($this->db->queries);
		unset($this->db->query_times);
		unset($this->db->query_times);
		### LOG
		$params['regdate']		= date('Y-m-d H:i:s');
		$params['gb']			= 'MANUAL';
		$params['total']		= $sendCnt;
		$params['from_email']	= $aParmasPost['send_email'];
		$params['subject']		= $aParmasPost['title'];
		$params['contents']		= $body;
		$data = filter_keys($params, $this->db->list_fields('fm_log_email'));

		$result = $this->db->insert('fm_log_email', $data);

		### MASTER
		$master = config_load('master');
		config_save('master',array('mail_count'=>(3000 - $usedMail)));

		echo "<script>
		document.all.processTable.width='100%';
		document.all.processTableTd.style.background='#0082ec';
		document.all.processTableTd.innerHTML='100%';
		</script>";
		echo str_repeat(" \r\n",2048);
		flush();
		ob_flush();


		//$callback = "parent.document.getElementById('container').src='../member/email_form';";
		$callback = "parent.location.reload();";
		$msg = "메일 발송 시스템에 접수되어 순차적으로 발송처리됩니다. ";
		openDialogAlert($msg,400,140,'parent',$callback);
		exit;
	}



	/**
	** 가입, 아이디/패스워드찾기, 성인인증시 : 본인인증/안심체크/아이핀 실명인증 기본 코드생성
	**/
	public function realnamecheck(){
		$this->load->model("smsmodel");
		$this->smsmodel->smsHpAauth();
	}

	public function javascript_return(){
		$code = $_GET['code'];
		$msg = $_GET['msg'];
		$callback = "parent.closeDialog('processDiv');";

		if($code != "200"){
			if($code == "800"){
				echo "<script>parent.closeDialog('processDiv'); parent.openSmsSend();</script>";
			}else{
				openDialogAlert($msg,400,140,'parent',$callback);
			}
		}else{
			//SMS서버에 갔다가 돌아왔을 때 문자발송내역페이지로 이동 @2016-08-09 ysm
			$callback = "top.location.href='../member/sms_history';";//"top.location.reload();";
			openDialogAlert($msg,400,140,'parent',$callback);
		}
		exit;

	}


	public function restock_notify_send_sms(){
		### Validation

		echo "<style>body {margin:0;padding:0}</style>";
		echo str_repeat(" \r\n",2048);
		echo "<table id='processTable' width='100%' ><tr><td height='30' id='processTableTd' align='center' style='color:#eeeeee; line-height:30px;'><span style='color:#000000;'>발송 준비중입니다...</span></td></tr></table>";
		echo str_repeat(" \r\n",2048);
		flush();
		ob_flush();
		sleep(1);


		$this->validation->set_rules('send_message', '내용','trim|required|xss_clean');
		$this->validation->set_rules('send_sms', '보내는사람','trim|required|max_length[50]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus(); parent.closeDialog('processDiv');";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$aParmasPost = $this->input->post();
		$callback = 'parent.closeDialog("processDiv");';
		if($aParmasPost['limit_goods_name_yn'] == "y"){
			if($aParmasPost['limit_goods_name'] == ""){
				openDialogAlert("상품명 길이제한을 입력해주세요.",400,140,'parent',$callback);
				exit;
			}
		}

		if($aParmasPost['limit_goods_option_yn'] == "y"){
			if($aParmasPost['limit_goods_option'] == ""){
				openDialogAlert("상품명 길이제한을 입력해주세요.",400,140,'parent',$callback);
				exit;
			}
		}


		###
		$arrRestockNotifySeq = array();
		unset($phoneNo[0]);
		$key = get_shop_key();


		if($aParmasPost['searchSelect'] == "select"){
			$sc['restock_notify_seq'] = $aParmasPost['selectMember'];
		}else{
			parse_str(urldecode($aParmasPost["serialize"]),$tempArr);
			foreach($tempArr as $k => $v){
				if($k){
					$sc[$k] = $v;
				}
			}
		}

		$sc['orderby']			= (isset($aParmasPost['orderby'])) ?	$aParmasPost['orderby']:'regist_date';
		$sc['sort']				= (isset($aParmasPost['sort'])) ?		$aParmasPost['sort']:'DESC';

		$sc['page'] 			= 0;
		$sc['sms'] 				= 'y';
		$sc['perpage'] 			= 1000;

		$this->load->model('goodsmodel');
		debug($sc);
		$data = $this->goodsmodel->restock_notify_list($sc);
		debug($data);

		if(count($data['record'])>0){
			foreach($data['record'] as $k){
				array_push($arrRestockNotifySeq,$k['restock_notify_seq']);
			}
		}

		$targetList = array();

		$query = $this->db->query("
			select
			a.restock_notify_seq,
			a.member_seq,
			AES_DECRYPT(UNHEX(a.cellphone), '{$key}') as cellphone,
			b.goods_seq,
			b.goods_name,
			c.title1,
			c.option1,
			c.title2,
			c.option2,
			c.title3,
			c.option3,
			c.title4,
			c.option4,
			c.title5,
			c.option5
			from fm_goods_restock_notify as a
			inner join fm_goods as b on a.goods_seq = b.goods_seq
			left join fm_goods_restock_option as c on a.restock_notify_seq = c.restock_notify_seq
			where a.restock_notify_seq in ('".implode("','",$arrRestockNotifySeq)."') and a.notify_status='none'
		");
		$targetResult = $query->result_array();
		$totalCount = count($targetResult);
		foreach($targetResult as $v){
			$targetList[$v['goods_seq']]['phone'][] = $v['cellphone'];

			//재입고알림요청시 개인회원/기업회원/비회원 이름치환 @2016-08-03 ysm
			$userName = '고객';
			if( $v['member_seq'] ) {
				$minfo = $this->membermodel->get_member_data_only_seq($v['member_seq']);
				if( $minfo ) {
					$userName = ($minfo['mbinfo_business_seq'])?$minfo['bname']:$minfo['user_name'];
				}
			}
			$targetList[$v['goods_seq']]['userName'][] = $userName;

			$targetList[$v['goods_seq']]['restock_notify_seq'][] = $v['restock_notify_seq'];
			$targetList[$v['goods_seq']]['goods_name'] = $v['goods_name'];
			$temp = "";
			if($v['option1'] && $v['title1']){
				$temp = $v['title1'].":".$v['option1']." ";
				if($v['option2'] && $v['title2']){
					$temp .= $v['title2'].":".$v['option2']." ";
				}
				if($v['option3'] && $v['title3']){
					$temp .= $v['title3'].":".$v['option3']." ";
				}
				if($v['option4'] && $v['title4']){
					$temp .= $v['title4'].":".$v['option4']." ";
				}
				if($v['option5'] && $v['title5']){
					$temp .= $v['title5'].":".$v['option5']." ";
				}
			}
			$targetList[$v['goods_seq']]['option'][] = $temp;
		}

		debug($targetList);
		if($targetList){
			$smsCnt=0;

			$phoneNo = array();
			$msg = array();
			foreach($targetList as $goods_seq=>$v){

				$dataTo		= array();
				foreach($v['phone'] as $cnt=>$cellphone){
					$smsCnt++;
					$dataTo = $cellphone;
					$send_message = $_POST["send_message"];
					$send_message = str_replace("{상품고유값}",$goods_seq,$send_message);

					if($_POST['limit_goods_name_yn'] == "y") $v['goods_name'] = getstrcut(strip_tags($v['goods_name']),$_POST['limit_goods_name']);
					$send_message = str_replace("{상품명}",strip_tags($v['goods_name']),$send_message);

					if($_POST['limit_goods_option_yn'] == "y") $v['option'][$cnt] = getstrcut(strip_tags($v['option'][$cnt]),$_POST['limit_goods_option']);
					$send_message = str_replace("{옵션}",strip_tags($v['option'][$cnt]),$send_message);

					//재입고알림요청시 개인회원/기업회원/비회원 이름치환 @2016-08-03 ysm
					$send_message = str_replace("{userName}", $v['userName'][$cnt], $send_message);

					$goods_url = ($this->config_system['domain']) ? "http://".$this->config_system['domain'] : "http://".$this->config_system['subDomain'];
					$sns = config_load('snssocial', 'shorturl_keyType');
					## 짧은 URL 사용시
					if($_POST['shorten_url_yn'] == "y"){
						$goods_url	= $goods_url."/goods/view?no=".$goods_seq;
						list($goods_url2, $short_result) = get_shortURL($goods_url);
						## 짧은 URL 오류시 긴 URL로 대체
						if( $short_result === false || (parse_url($goods_url2, PHP_URL_SCHEME)!='https' && $sns['shorturl_keyType'] == 'token')){
							$goods_url2 = $goods_url;
						}
					}else{
						$goods_url2 = $goods_url."/goods/view?no=".$goods_seq;
					}

					$send_message = str_replace("{상품주소}",$goods_url2,$send_message);

					###
					$str = trim($send_message);

					$phoneNo[] = $dataTo;
					$msg[] = $str;

					$percent = $smsCnt / $totalCount * 100;
					if($percent > 99) $percent = 99;

					echo str_repeat(" \r\n",2048);
					echo "<script>
					document.all.processTable.width='".$percent."%';
					document.all.processTableTd.style.background='#0082ec';
					document.all.processTableTd.innerHTML='".(int)$percent."%';
					</script>";
					echo str_repeat(" \r\n",2048);
					flush();
					ob_flush();

					$this->db->query("
						update fm_goods_restock_notify set notify_status='complete', notify_date=now() where restock_notify_seq in ('".implode("','",$v['restock_notify_seq'])."')
					");
				}
			}

			$params['msg'] = $msg;
			$commonSmsData['restock']['phone'] = $phoneNo;;
			$commonSmsData['restock']['params'] = $params;


			$result = commonSendSMS($commonSmsData);

			echo str_repeat(" \r\n",2048);
			echo "<script>
			document.all.processTable.width='100%';
			document.all.processTableTd.style.background='#0082ec';
			document.all.processTableTd.innerHTML='100%';
			</script>";
			echo str_repeat(" \r\n",2048);
			flush();
			ob_flush();

			$callback = "parent.document.location.reload();";
			$result_msg = "문자 시스템에 접수되어 순차적으로 발송처리됩니다.";
		}else{
			$callback = "parent.document.location.reload();";
			$result_msg	= "재입고알림을 통보할 고객이 없습니다.";
		}

		###

		openDialogAlert($result_msg,400,140,'parent',$callback);
		exit;
	}

	public function send_sms_dormancy(){

		### Validation
		$this->validation->set_rules('send_message', '내용','trim|required|xss_clean');
		$this->validation->set_rules('send_sms', '보내는사람','trim|required|max_length[50]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus(); loadingStop();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		
		$sms_type = '';
		$key = get_shop_key();
		$bSearchSelect = false;
		$aTemps = $this->input->post();
		//이메일 전송 조건이 검색 조건인 경우
		if($aTemps['searchSelect'] == "select"){
			$bSearchSelect = true;
		}

		$sc['orderby'] = (isset($aTemps['orderby']))?$aTemps['orderby']:'A.member_seq';
		$sc['sort']	= (isset($aTemps['sort']))?$aTemps['sort']:'desc';

		if($aTemps['search_member_yn']=='y'){
			if($bSearchSelect !== false){
				$sc['member_seq'] = $aTemps['selectMember'];
			}else{
				$tempArr = explode("&",urldecode($aTemps["serialize"]));
				foreach($tempArr as $k){
					$tmp = explode("=",$k);					
					$sc[$tmp[0]] = $tmp[1];						
				}
			}

			$sc['page'] = 0;
			$sc['perpage'] = 30000;
			$sc['batchProcess'] = 'y';

			if($sc['keyword'] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
				$sc['keyword'] = "";
			}

			if( $sc['snsrute'] ) {
				foreach($sc['snsrute'] as $key=>$val){$sc[$val] = 1;}
			}

			if($bSearchSelect !== false && !empty($aTemps['selectMember'])){
				// 선택 전송일때 회원 검색 프로세스
				$data = $this->membermodel->admin_member_list($sc);
			}else{
				// 검색 전송일때 회원 검색 프로세스
				$data = $this->membermodel->admin_member_list_spout($sc);
			}

			if(count($data['result'])>0){
				foreach($data['result'] as $k){
					if($k['business_seq'] ) { //기업회원
						if($k['bcellphone']){
							$params['userName']	= $k['bceo'];
							$params['userid']	= $k['userid'];
							$params['lastlogin_date'] = $k['lastlogin_date'];
							$commonSmsData['dormancy_m']['phone'][] = preg_replace("/[^0-9]*/s", "", $k['bcellphone']);
							$commonSmsData['dormancy_m']['params'][] = $params;
						}
					}else{
						if($k['cellphone']){
							$params['userName']	= $k['user_name'];
							$params['lastlogin_date'] = $k['lastlogin_date'];
							$params['userid']	= $k['userid'];
							$commonSmsData['dormancy_m']['phone'][] = preg_replace("/[^0-9]*/s", "", $k['cellphone']);
							$commonSmsData['dormancy_m']['params'][] = $params;
						}
					}
				}
			}
		}

		$callback = "loadingStop();";
		if(count($commonSmsData['dormancy_m']['phone']) < 1){
			openDialogAlert('받는사람이 없습니다.',400,140,'parent',$callback);
			exit;
		}

		if(count($commonSmsData['dormancy_m']['phone']) > 1000){
			$result_msg = "1,000명 이상은 받는 사람을 엑셀로 다운로드 받아<br> 대량SMS 발송 기능으로 SMS를 보내 주십시오.";
			openDialogAlert($result_msg,400,140,'parent',$callback);
			exit;
		}

		$limit	= commonCountSMS();
		if(count($commonSmsData['dormancy_m']['phone']) > $limit){
			$result_msg = "잔여 건수가 부족합니다. 받는 사람을 엑셀로 다운로드 받아 <a href=\"/admin/batch/sms\" target=\"_blank\"><span class=\"orange\"><b>대량SMS 발송</b></span></a> 기능을 이용하여 발송해 주십시오.";
			openDialogAlert($result_msg,400,140,'parent',$callback);
			exit;

		}

		$result = commonSendSMS($commonSmsData);

		if($result['msg'] == "fail"){
			$result_msg = "%이후에는 문자가 올수 없습니다.<br>예) 30%DC -> 30% DC";
		}else{
			$result_code = $result['code'];
			if($result_code != "0000"){
				if($result_code == "E001"){
					$result_msg = "SMS 인증 정보가 잘못되었습니다.";
				}else{
					$result_msg = $sms_type."발송에 실패했습니다.".$result_code;
				}
			}else{
				$callback = "parent.location.reload();";
				$result_msg = "문자 시스템에 접수되어 순차적으로 발송처리됩니다.";
			}
		}

		openDialogAlert($result_msg,400,140,'parent',$callback);
		exit;
	}

	public function send_email_dormancy(){

		### Validation
		$this->validation->set_rules('title', '제목','trim|required|max_length[100]|xss_clean');
		$this->validation->set_rules('send_email', '보내는사람','trim|required|max_length[50]|valid_email|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		$key = get_shop_key();
		$data = $mailArr = array();
		$bSearchSelect = false;
		$aTemps = $this->input->post();

		//이메일 전송 조건이 검색 조건인 경우
		if($aTemps['searchSelect'] == "select"){
			$bSearchSelect = true;
		}

		if($bSearchSelect){
			$sc['member_seq'] = $aTemps['selectMember'];
		}else{
			$tempArr = explode("&",urldecode($aTemps["serialize"]));
			foreach($tempArr as $k){
				$tmp = explode("=",$k);
				$sc[$tmp[0]] = $tmp[1];				
			}
		}

		$sc['page'] = 0;
		$sc['perpage'] = 30000;
		$sc['batchProcess'] = 'y';
		if($sc["keyword"] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
			$sc["keyword"] = "";
		}
		if( $sc['snsrute'] ) {
			foreach($sc['snsrute'] as $key=>$val){$sc[$val] = 1;}
		}

		if($bSearchSelect !== false && !empty($aTemps['selectMember'])){
			// 선택 전송일때 회원 검색 프로세스
			$data = $this->membermodel->admin_member_list($sc);
		}else{
			// 검색 전송일때 회원 검색 프로세스
			$data = $this->membermodel->admin_member_list_spout($sc);
		}

		if(count($data['result'])>0){
			$idx = 0;
			foreach($data['result'] as $k){
				$dormancy_du_date = $k['lastlogin_date'];
				$dormancy_du_date = (substr($dormancy_du_date,0,4)+1).substr($dormancy_du_date,4,6);
				$mailArr[$idx]['email'] = $k['email'];
				$mailArr[$idx]['dormancy_du_date'] = $dormancy_du_date;
				$idx++;
			}
		}

		if (count($mailArr) < 1) {
			$callback = "";
			openDialogAlert('받는 사람이 없습니다.',400,140,'parent',$callback);
			exit;
		}

		$this->load->model('usedmodel');
		$email_chk = $this->usedmodel->hosting_check();

		###
		
		$iCount = 0;
		foreach($mailArr as $key => $val){
			// 휴먼 회원 중 메일이 존재하는 회원만 발송하므로 실제 메일 발송 수 재계산
			if(empty($val['email'])!== true){
				$iCount++;
			}
		}
		$total = $iCount;
		$toMonth = date("Y-m");
		$sql = "select sum(total) as count from fm_log_email where gb='MANUAL' and regdate like '{$toMonth}%'";
		$query = $this->db->query($sql);
		$emailData = $query->result_array();
		$usedMail	= $emailData[0]['count'] + $total;
		if(3000 < $usedMail  && !$email_chk && !preg_match("/^F_SH_/",$this->config_system['service']['hosting_code'])){
			$callback = "";
			openDialogAlert('본 이메일 발송 기능은 월 3,000통 발송이 가능합니다.<br>더 많은 이메일 발송은 대량발송 서비스를 이용해 주십시오.',400,140,'parent',$callback);
			exit;
		}
		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		$this->config_basic['domain'] = ($this->config_system['domain']) ? get_connet_protocol().$this->config_system['domain'] : get_connet_protocol().$this->config_system['subDomain'];

		require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/Email_send.class.php";
		$mail = new Mail(isset($params));
		$bodies = adjustEditorImages($aTemps['contents']);

		$sendCnt = 0;
		$totalCount = $iCount;
		foreach($mailArr as $k){
			$sendCnt++;
			if(filter_var($k['email'],FILTER_VALIDATE_EMAIL)!=false){

				$unsubscribeKey = urlencode(base64_encode($k['email']));
				$body = str_replace("{unSubScribeKey}", $unsubscribeKey, $bodies);

				$body = str_replace("{shopName}", $basic['shopName'], $body);
				$body = str_replace("{dormancy_du_date}", $k['dormancy_du_date'], $body);

				$body = str_replace("{basic.domain}", $this->config_basic['domain'], $body);
				$body = str_replace("{basic.businessLicense}", $basic['businessLicense'], $body);
				$body = str_replace("{basic.mailsellingLicense}", $basic['mailsellingLicense'], $body);
				$body = str_replace("{basic.ceo}", $basic['ceo'], $body);
				$body = str_replace("{basic.companyAddress}", $basic['companyAddress'], $body);
				$body = str_replace("{basic.companyPhone}", $basic['companyPhone'], $body);
				$body = str_replace("{basic.companyFax}", $basic['companyFax'], $body);

				$send_title = $aTemps['title'];
				$send_title = str_replace("{shopName}", $basic['shopName'], $send_title);

				$headers['From']    = $aTemps['send_email'];
				$headers['Name']	= !$basic['companyName'] ? get_connet_protocol().$_SERVER['HTTP_HOST'] : $basic['companyName'];
				$headers['Subject'] = $send_title;
				$headers['To'] = $k['email'];

				$params['location'] = "member_list";
				$params['subject'] = $send_title;
				$params['contents'] = $body;
				$params['from_name'] = $headers['Name'];
				$params['from_email'] = $aTemps['send_email'];
				$params['to_email'] = $k['email'];
				$params['division'] = "user";
				$params['regist_date'] = date("Y-m-d H:i:s");

				$this->db->insert("fm_email", $params);

				$percent = $sendCnt / $totalCount * 100;
				if($percent > 99) $percent = 99;

				echo str_repeat(" \r\n",2048);
				echo "<script>
				document.all.processTable.width='".$percent."%';
				document.all.processTableTd.style.background='#0082ec';
				document.all.processTableTd.innerHTML='".(int)$percent."%';
				</script>";
				echo str_repeat(" \r\n",2048);
				flush();
				ob_flush();
			}
		}

		unset($this->db->queries);
		unset($this->db->query_times);
		unset($this->db->query_times);

		### LOG
		$params['regdate']		= date('Y-m-d H:i:s');
		$params['gb']			= 'MANUAL';
		$params['total']		= $total;
		$params['from_email']	= $aTemps['send_email'];
		$params['subject']		= $aTemps['title'];
		$params['contents']		= $body;
		$data = filter_keys($params, $this->db->list_fields('fm_log_email'));

		$result = $this->db->insert('fm_log_email', $data);

		### MASTER
		config_save('master',array('mail_count'=>(3000 - $usedMail)));

		echo "<script>
		document.all.processTable.width='100%';
		document.all.processTableTd.style.background='#0082ec';
		document.all.processTableTd.innerHTML='100%';
		</script>";
		flush();
		ob_flush();

		$callback = "parent.location.reload();";
		$msg = "메일 발송 시스템에 접수되어 순차적으로 발송처리됩니다. ";
		openDialogAlert($msg,400,140,'parent',$callback);
		flush();
		ob_flush();
		exit;
	}
}

/* End of file batch_process.php */
/* Location: ./app/controllers/admin/batch_process.php */