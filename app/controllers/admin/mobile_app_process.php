<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);
class mobile_app_process extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('admin/setting');
		$this->load->model('managermodel');
		//$this->load->helper('member');
		$this->load->model('membermodel');
		$this->load->model("authmodel");

		$userAppUrl = 'http://userapp.firstmall.kr';
		$this->mobileApiUrl = array(
			'regist'	=> $userAppUrl . '/push/queue_push.php',
			'controll'	=> $userAppUrl . '/push/controll_push.php',
			'setting'	=> $userAppUrl . '/setmobileappreleaseforsolution'
		);
	}

	public function setting(){

		$msg = "설정이 저장 되었습니다.";
		$old_app_config = config_load('app_config'); // 기존 정보

		// 앱 컴파일 정보 갱신
		if(($old_app_config['footer_style'] != $_POST['footer_style']) || ($old_app_config['app_notice_popup'] != $_POST['app_notice_popup'])){
			// 신청 이후 상태가 존재할때...
			if($_POST['status_txt_and'] || $_POST['status_txt_ios']){
				$this->load->helper('readurl');
				$domain	= $this->config_system['domain'];
				$subDomain	= $this->config_system['subDomain'];
				$params['shopSno']				= $this->config_system['shopSno'];
				$params['mobileapp_underbar']	= $_POST['footer_style'];
				$params['mobileapp_notice_popup']	= $_POST['app_notice_popup'];
				$params['mobileapp_os']['ANDROID'] = $_POST['Android'];
				$params['mobileapp_os']['IOS'] = $_POST['IOS'];
				$call_url		= $this->mobileApiUrl['setting'];
				$headers		= array('userapp_domain'=>$domain,'userapp_subDomain'=>$subDomain);
				$read_data		= readurl($call_url,$params,false,7,$headers);
				$set_res		= json_decode($read_data,true);
				$set_res['msg'] = base64_decode($set_res['msg']);
			}
			if($set_res['result'] == '1'){
				$app_config['footer_style']		= $_POST['footer_style'];
				$app_config['app_notice_popup']		= $_POST['app_notice_popup'];
			}else{
				$msg .= '<br/><span class="red">앱 하단 스타일은 저장되지 않았습니다.</span>';
				if($set_res['msg'])
					$msg .= '<br/>(사유 : ' . $set_res['msg'] . ')';
			}
		}

		// 앱 관련 설정
		$app_config['app_popup_use']	= $_POST['app_popup_use'];
		if($app_config['app_popup_use'] == 'Y'){
			$app_config['popup_url_ios']	= $_POST['popup_url_ios'];
			$app_config['popup_url_and']	= $_POST['popup_url_and'];

			if($_POST['popup_type'] == 'custom'){
				if($_POST['custom_popup_img']){
					$this->load->model('usedmodel');
					$data_used = $this->usedmodel->used_limit_check(); // 용량체크
					if( $data_used['type'] ){						
						if(preg_match("/^\/?data\/tmp/i", $_POST['custom_popup_img'])){
							if(!is_dir(ROOTPATH.'data/popup')){
								@mkdir(ROOTPATH.'data/popup');
								@chmod(ROOTPATH.'data/popup',0777);
							}
							$ext = explode("/", $_POST['custom_popup_img']);
							$ext = $ext[count($ext)-1];									
							$custom_popup_img = "/data/popup/{$ext}";
							copy(ROOTPATH.$_POST['custom_popup_img'],ROOTPATH.$custom_popup_img);
							chmod(ROOTPATH.$custom_popup_img,0777);
						}else{
							$custom_popup_img = $_POST['custom_popup_img'];
						}
						
						/*
						$file_ext = end(explode('.', $_FILES['custom_popup_img']['name']));//확장자추출
						$config['upload_path']	= ROOTPATH.'data/popup';
						$config['max_size']		= $this->config_system['uploadLimit'];
						$config['file_name']	= 'custom_popup_img_'.time().'.'.$file_ext;
						$config['allowed_types'] = 'png|jpg|jpeg';
						$this->load->library('upload', $config);
						if ( ! $this->upload->do_upload('custom_popup_img'))
						{
							$error = $this->upload->display_errors();
							openDialogAlert($error,400,150,'parent');
							exit;
						}*/

						//$uploadData = $this->upload->data();
						//$custom_popup_img = str_replace($_SERVER['DOCUMENT_ROOT'],'',$uploadData['file_path']).$uploadData['raw_name'].'.'.$file_ext;
						if($custom_popup_img){
							$app_config['custom_popup_img'] = $custom_popup_img;
							$app_config['pop_html']			= '<div class="popWrap"><img src="'.$app_config['custom_popup_img'].'" onclick="appClosepopup(\'set\');"/><ul class="bont"><li><span class="hand popup_style_close_day" onclick="appClosepopup(\'day\');">오늘 이 창을 열지 않음</span></li><li><a class="hand popup_style_close" onclick="appClosepopup(\'close\');">닫기</a></li></ul></div>';
							$app_config['popup_type']		= $_POST['popup_type'];
						}else{
							$callback = "parent.window.location.reload();";
							openDialogAlert('팝업 이미지를 저장하지 못했습니다.<br/>잠시 후 다시 시도해주세요.',400,160,'parent',$callback);
							exit;
						}
					}else{
						openDialogAlert($data_used['msg'],400,150,'parent','');
						exit;
					}
				}else{
					openDialogAlert('팝업 이미지를 업로드 해주세요.',400,160,'parent',$callback);
					exit;
				}
			}else{
				$app_config['popup_type']		= $_POST['new_popup_type'];
				$app_config['pop_title']		= $_POST['pop_title'];
				$app_config['pop_subtitle']		= $_POST['pop_subtitle'];
				$app_config['pop_sale']			= $_POST['pop_sale'];
				$app_config['pop_sale_unit']	= $_POST['pop_sale_unit'];
				$app_config['pop_footer_txt']	= $_POST['pop_footer_txt'];
				$app_config['pop_footer_close']	= $_POST['pop_footer_close'];
				$app_config['pop_html']			= $_POST['pop_html'];
			}
		}

		config_save('app_config',$app_config);

		$callback = "parent.window.location.reload();";
		openDialogAlert($msg,400,160,'parent',$callback);
	}
	
	############################################################################################
	## queue push 
	protected function getMemberSeq()
	{    
	    
	    if($_POST['search_member_yn']=='y'){
            $sc['sms_member'] = "y";
            $sc['page'] = 0;
            if($_POST['searchSelect'] == "select"){
                $sc['member_seq'] = $_POST['selectMember'];
            }else{
                $tempArr = explode("&",urldecode($_POST["serialize"]));
                foreach($tempArr as $k){
                    $tmp = explode("=",$k);
                    $sc[$tmp[0]] = $tmp[1];                   
                }           
                
                
                $data = $this->membermodel->admin_member_list($sc);
                $totalCount = count($data['result']);
                $sendCnt = 0;
                $array_member_seq = array();
                echo "total_cnt = " . $totalCount;
                if(count($data['result'])>0){
                    foreach($data['result'] as $k){
                        //echo "member_seq : " . $k['member_seq'] . "<br>";
                        $array_member_seq[] = $k['member_seq'];
                    }
                    
                    //debug_var($array_member_seq);
                    $sc['member_seq'] = implode(",", $array_member_seq);
                }
            }
	    }else {
	        $sc['member_seq'] = "-1";
	    }
        //echo "member_seq = " . $sc['member_seq'];
        return $sc['member_seq'];
	    //}
	}
	protected function imageUpload()
	{
	    $filenm = 'send_image';
	    $is_upload_file = true;
	    $this->load->library('upload');

		if(preg_match("/^\/?data\/tmp/i", $_POST['send_image'])){
			if(!is_dir(ROOTPATH.'data/app_push')){
				@mkdir(ROOTPATH.'data/app_push');
				@chmod(ROOTPATH.'data/app_push',0777);
			}
			$ext = explode("/", $_POST['send_image']);
			$ext = $ext[count($ext)-1];			
			$new_path = "/data/app_push/{$ext}";
			$config['file_name'] = $ext;	
			$config['upload_path'] = ROOTPATH.'data/app_push/';			
			copy(ROOTPATH.$_POST['send_image'],ROOTPATH.$new_path);
			chmod(ROOTPATH.$new_path,0777);
		}else{
			$callback = "";
	        //openDialogAlert("등록 파일이 없습니다.",400,140,'parent',$callback);
	        //echo "no file~~";
	        $config['upload_path'] = "";
	        $config['file_name'] = "";
	        $is_upload_file = false;
		}
	    /*
	    if (is_uploaded_file($_FILES[$filenm]['tmp_name'])) {
	        $config['upload_path']		= $path = ROOTPATH."data/tmp/";
	        $file_ext = end(explode('.', $_FILES[$filenm]['name']));//확장자추출
	        
	        echo "ext = " . $file_ext . "&path = " . $path . "<br>";
	        //if($upload_kind=='img'){
	        $arrImageExtensions = array('gif|jpg|png');
	        //}
	        
	        
	    }else{
	        
	    }*/
	    return array("path" => $config['upload_path'],
	        "name" => $config['file_name']);
	}
	protected function getCurlValue($filename, $contentType, $postname)
	{
	    // PHP 5.5 introduced a CurlFile object that deprecates the old @filename syntax
	    // See: https://wiki.php.net/rfc/curl-file-upload
	    if (function_exists('curl_file_create')) {
	        return curl_file_create($filename, $contentType, $postname);
	    }
	    
	    // Use the old style if using an older version of PHP
	    $value = "@{$filename};filename=" . $postname;
	    if ($contentType) {
	        $value .= ';type=' . $contentType;
	    }
	    
	    return $value;
	}
	protected function send_queue_push($member_seq_array, $path, $name)
	{	
		
		
	    $send_file = "";
	    if( $path != "" && $name != "") {
	       $send_file     = $this->getCurlValue($path.$name,'image/jpeg',$name);
	    }

		

	    $data = array(
			'shopSno'		=> $this->config_system['shopSno'],
	        'send_image'    => $send_file,
	        'send_type'     => $_POST['send_type'],
	        'send_title'    => $_POST['send_title'],
            'send_body'     => $_POST['send_body'],
            'send_link'     => $_POST['send_link'],
	        'reserve_datetime' => $_POST['reserve_date'] . " " . $_POST['reserve_hour'] . ":" . $_POST['reserve_min'],
            'member_seq_array' => $member_seq_array
	    );
	    
	    $ch = curl_init();
	    $options = array(CURLOPT_URL => $this->mobileApiUrl['regist'],
	        CURLOPT_RETURNTRANSFER => true,
	        CURLINFO_HEADER_OUT => false, //Request header
	        CURLOPT_HEADER => false, //Return header
	        CURLOPT_SSL_VERIFYPEER => false, //Don't veryify server certificate
	        CURLOPT_POST => true,
	        CURLOPT_POSTFIELDS => $data
	    );
	    
	    curl_setopt_array($ch, $options);
	    $result = curl_exec($ch);	   
	    curl_close($ch);
        
        //debug_var($result);
	    $response_array = json_decode($result, true);
	    $result_int = $response_array['result'];
	    $result_msg = $response_array['msg'];
	     
	    if($result == false){
	        $result_int = 0;
	        $result_msg = curl_error($ci);	        	        
	    }  

		
	    return array("result" => $result_int,
	               "msg" => $result_msg);
	    
	}
	public function regist_push()
	{	    
	    //1. member_seq 를 얻어 오자.
	    $member_seq_array = $this->getMemberSeq();
	    
	    //2. 이미지가 있으면 이미지 저장
	    $image_file = $this->imageUpload();
	    $image_path    = $image_file['path'];
	    $image_name    = $image_file['name'];
	    
	    //echo "filename = " . $image_path . $image_name;
	    # userapp 서버의 queue_push에 데이터 전송
        $result = $this->send_queue_push($member_seq_array, $image_path, $image_name);	            
        
	    
	    $msg = "푸시가 발송 되었습니다.";
	    $callback = "parent.window.location.reload();";
	    if( $result['result'] == 1) {
    	    if( $_POST['send_type'] == 'r' ) {
    	        $msg = "푸시 발송이 예약 되었습니다.";
    	    }
	    }
	    else {
	        $callback  = "";
	        $msg       = "다음과 같은 오류로 발송이 되지 않았습니다.<br>" . $result['msg'];
	    }
	    openDialogAlert($msg,400,160,'parent',$callback);
	    exit;
	    
	}

	// 푸시 수정 및 취소 process :: 2020-01-09 lwh
	public function controll_push(){
		$gParams		= $this->input->post();

		// 이미지가 있으면 이미지 저장
	    $image_file		= $this->imageUpload();
	    $image_path		= $image_file['path'];
	    $image_name		= $image_file['name'];
		$send_file		= '';
	    if( $image_path != "" && $image_name != "") {
	       $send_file	= $this->getCurlValue($image_path.$image_name,'image/jpeg',$image_name);
	    }

	    $data = array(
			'push_seq'			=> $gParams['push_seq'],
			'push_type'			=> $gParams['push_type'],
			'shopSno'			=> $this->config_system['shopSno'],
	        'send_image'		=> $send_file,
	        'send_title'		=> $gParams['send_title'],
            'send_body'			=> $gParams['send_body'],
            'send_link'			=> $gParams['send_link'],
	        'reserve_datetime'	=> $gParams['reserve_date'] . " " . $gParams['reserve_hour'] . ":" . $gParams['reserve_min'],
	    );

	    $ch = curl_init();
	    $options = array(CURLOPT_URL => $this->mobileApiUrl['controll'],
	        CURLOPT_RETURNTRANSFER => true,
	        CURLINFO_HEADER_OUT => false, //Request header
	        CURLOPT_HEADER => false, //Return header
	        CURLOPT_SSL_VERIFYPEER => false, //Don't veryify server certificate
	        CURLOPT_POST => true,
	        CURLOPT_POSTFIELDS => $data
	    );
	    curl_setopt_array($ch, $options);
		if(!$result = curl_exec($ch)){
			debug(curl_error($ch));
		}
	    curl_close($ch);
        
	    $response_array = json_decode($result, true);
	    $result_int = $response_array['result'];
	    $result_msg = $response_array['msg'];
	     
	    if($result == false){
	        $result_int = 0;
	        $result_msg = curl_error($ci);	        	        
	    }

		$act_type = '수정';
		if($gParams['push_type'] == 'cancel')				$act_type = '취소';
		else if($gParams['push_type'] == 'batch_cancel')	$act_type = '일괄취소';

		$callback	= "parent.window.location.reload();";
		if( $result_int == 1) {
			if($gParams['push_type'] == 'cancel' || $gParams['push_type'] == 'batch_cancel'){
				$msg	= '푸쉬 발송 예약이 ' . $act_type . '되었습니다.';
			}else{
				$msg	= '푸시 발송내역 상세가 수정되었습니다.';
			}
	    } else {
	        $callback	= '';
	        $msg		= '다음과 같은 오류로 ' . $act_type . '이(가) 되지 않았습니다.<br/>[<span class="red">' . $result_msg . '</span>]';
	    }
	    openDialogAlert($msg,400,160,'parent',$callback);
	    exit;
	}
	############################################################################################

	
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
	    
	    $sc = $_POST;
	    $sc['orderby']			= (isset($_POST['orderby'])) ?	$_POST['orderby']:'restock_notify_seq';
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
	    
	    if($_POST['searchSelect'] == "select"){
	        $sc['restock_notify_seq'] = $_POST['selectMember'];
	    }else{
	        parse_str(urldecode($_POST["serialize"]),$tempArr);
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
}
