<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class member_process extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('admin/setting');
		$this->load->helper('member');
		$this->load->model('membermodel');
	}

	### 실명확인
	public function realname(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$arrBasic = ($this->config_basic)?$this->config_basic:config_load('basic');
		$arrrealname		= config_load('realname');
		$useRealname		= ($_POST['useRealname'])?$_POST['useRealname']:'N';
		$useRealnamephone	= ($_POST['useRealnamephone'])?$_POST['useRealnamephone']:'N';
		$useIpin			= ($_POST['useIpin'])?$_POST['useIpin']:'N';
		$useRealnamephone_adult	= ($_POST['useRealnamephone_adult'])?$_POST['useRealnamephone_adult']:'N';
		$useIpin_adult		= ($_POST['useIpin_adult'])?$_POST['useIpin_adult']:'N';
		$useRealnamephone_dormancy	= ($_POST['useRealnamephone_dormancy'])?$_POST['useRealnamephone_dormancy']:'N';
		$useIpin_dormancy		= ($_POST['useIpin_dormancy'])?$_POST['useIpin_dormancy']:'N';

		if($arrBasic['operating'] == "adult"){
			if($useIpin == 'N' && $useRealnamephone == 'N'){
				$callback = "";
				openDialogAlert('현재 "성인쇼핑몰"을 운영 중이십니다.<br/>성인쇼핑몰은 본인인증(휴대폰 또는 아이핀) 수단을 필수로 사용하셔야 합니다.<br/>성인쇼핑몰이 아니라면, [설정>운영방식]에서 일반 또는 회원전용 쇼핑몰로 변경하신 후<br/>본인인증 수단을 미사용하실 수 있습니다.',570,180,'parent',$callback);
				exit;
			}
		}

		if($useIpin_adult == 'Y' ){
			if( !($_POST['ipinSikey'] && $_POST['ipinKeyString'])  ) {
				$callback = "";
				openDialogAlert("아이핀 세팅정보를 정확히 입력해 주세요.",400,140,'parent',$callback);
				exit;
			}
		}

		if($useRealnamephone_adult == 'Y' ) {
			if( !($_POST['realnamephoneSikey'] && $_POST['realnamePhoneSipwd']) ) {
				$callback = "";
				openDialogAlert("휴대폰인증 세팅정보를 정확히 입력해 주세요.",400,140,'parent',$callback);
				exit;
			}
		}

		config_save('realname',array('useRealname'=>$useRealname));
		config_save('realname',array('useIpin'=>$useIpin));
		config_save('realname',array('useRealnamephone'=>$useRealnamephone));

		config_save('realname',array('useIpin_adult'=>$useIpin_adult));
		config_save('realname',array('useRealnamephone_adult'=>$useRealnamephone_adult));

		config_save('realname',array('useIpin_dormancy'=>$useIpin_dormancy));
		config_save('realname',array('useRealnamephone_dormancy'=>$useRealnamephone_dormancy));

		config_save('realname',array('ipinSikey'=>trim($_POST['ipinSikey'])));
		config_save('realname',array('ipinKeyString'=>trim($_POST['ipinKeyString'])));

		config_save('realname',array('realnameId'=>$_POST['realnameId']));
		config_save('realname',array('realnamePwd'=>$_POST['realnamePwd']));

		config_save('realname',array('realnamephoneSikey'=>trim($_POST['realnamephoneSikey'])));
		config_save('realname',array('realnamePhoneSipwd'=>trim($_POST['realnamePhoneSipwd'])));

		###
		$callback = "parent.set_member_html();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	### 가입
	public function agreement(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		###
		$config['upload_path']		= $path = ROOTPATH."/data/config/";
		$config['overwrite']			= TRUE;
		$this->load->library('Upload');

		if (is_uploaded_file($_FILES['privacy_html']['tmp_name'])) {
			$file_ext = end(explode('.', $_FILES['privacy_html']['name']));//확장자추출
			$config['allowed_types']	= 'html';
			$config['file_name']			= 'privacy_html.'.$file_ext;
			$this->upload->initialize($config);
			if ($this->upload->do_upload('privacy_html')) {
				config_save('member',array('privacy_html'=>$config['file_name']));
			}else{
				$callback = "";
				openDialogAlert("html 만 가능합니다.",400,140,'parent',$callback);
				exit;
			}
		}

		### 설정저장
		/**
		agreement			: 기존 이용약관 필드
		policy_agreement	: 신규 이용약관 필드
		**/
		$policy_agreement = $this->input->post('policy_agreement');
		config_save('member',array('agreement'=>$policy_agreement));
		config_save('member',array('policy_agreement'=>$policy_agreement));

		// 카카오싱크 정보 변경
		if(isKakaoSyncUse()){
			$this->load->library('AdditionService/kakaosync/Client');
			$this->client->kakaosyncModify();
		}

		###
		$callback = "parent.set_member_html();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);		
	}

	public function privacy(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		###
		$config['upload_path']		= $path = ROOTPATH."/data/config/";
		$config['overwrite']		= TRUE;
		$this->load->library('Upload');

		if (is_uploaded_file($_FILES['privacy_html']['tmp_name'])) {
			$file_ext = end(explode('.', $_FILES['privacy_html']['name']));//확장자추출
			$config['allowed_types']	= 'html';
			$config['file_name']			= 'privacy_html.'.$file_ext;
			$this->upload->initialize($config);
			if ($this->upload->do_upload('privacy_html')) {
				config_save('member',array('privacy_html'=>$config['file_name']));
			}else{
				$callback = "";
				openDialogAlert("html 만 가능합니다.",400,140,'parent',$callback);
				exit;
			}
		}

		$privacy				= $this->input->post('policy_privacy');
		$policy_joinform		= $this->input->post('policy_joinform');
		$policy_joinform_option = $this->input->post('policy_joinform_option');
		$policy_marketing		= $this->input->post('policy_marketing');
		$policy_order			= $this->input->post('policy_order');
		$policy_board			= $this->input->post('policy_board');
		$policy_comment			= $this->input->post('policy_comment');
		$policy_restock			= $this->input->post('policy_restock');

		$joinform_optionYN		= $this->input->post('joinform_optionYN');

		### 설정저장 --------------------------------------------------------------
		# 기존 필드 저장.
		config_save('member',array('privacy'=>$privacy));									// 개인정보처리방침
		config_save('member',array('policy'=>$policy_joinform));							// [회원가입] 개인정보 수집 및 이용 (필수)
		# 신규필드 저장
		config_save('member',array('policy_privacy'=>$privacy));							// 개인정보처리방침
		config_save('member',array('policy_joinform'=>$policy_joinform));					// [회원가입] 개인정보 수집 및 이용 (필수)
		config_save('member',array('policy_joinform_option'=>$policy_joinform_option));		// [회원가입] 개인정보 수집 및 이용 (선택)
		config_save('member',array('policy_marketing'=>$policy_marketing));					// [회원가입] 마케팅 및 광고 활용 동의
		config_save('member',array('policy_order'=>$policy_order));							// [비회원 주문] 개인정보 수집 및 이용
		config_save('member',array('policy_board'=>$policy_board));							// [비회원 게시글 작성] 개인정보 수집 및 이용
		config_save('member',array('policy_comment'=>$policy_comment));						// [비회원 댓글 작성] 개인정보 수집 및 이용
		config_save('member',array('policy_restock'=>$policy_restock));						// [재입고알림] 개인정보 수집 및 이용

		config_save('member',array('joinform_optionYN'=>$joinform_optionYN));

		// 카카오싱크 정보 변경
		if(isKakaoSyncUse()){
			$this->load->library('AdditionService/kakaosync/Client');
			$this->client->kakaosyncModify();
		}

		###
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent', "parent.set_member_html();");
	}

	public function policy(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		###
		$policy_delegation	= $this->input->post('policy_delegation');
		$delegationYN		= $this->input->post('delegationYN');
		$thirdPartyYN		= $this->input->post('thirdPartyYN');

		config_save('member',array('policy_delegation'=>$policy_delegation));
		config_save('member',array('delegationYN'=>$delegationYN));
		config_save('member',array('thirdPartyYN'=>$thirdPartyYN));

		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent', "parent.set_member_html();");
	}

	## 청약철회 관련 방침
	public function cancellation(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		###
		$cancellation = $this->input->post('policy_cancellation');
		# 기존 필드 저장.
		config_save('order',array('cancellation'=>$cancellation));
		# 신규필드 저장
		config_save('member',array('policy_cancellation'=>$cancellation));

		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent', "parent.set_member_html();");
	}

	### 개인정보 제3자 제공동의 :: 2017-05-11 lwh
	public function policy_third_party(){
		// 에디터 이미지 경로 재정의
		$editor_dir = ROOTPATH.'data/editor/';

		if(!file_exists($editor_dir)){
			@mkdir($editor_dir);
		}
		@chmod($editor_dir,0777);
		$editor_dir = str_replace(ROOTPATH,"",$editor_dir);

		$contents = adjustEditorImages($_POST['view_textarea'], '/'.$editor_dir.'/');

		if(serviceLimit('H_AD')){
			config_save('member',array('policy_third_party'=>$contents));
		}else{
			config_save('member',array('policy_third_party_normal'=>$contents));
		}

		###
		$callback = "parent.third_party_reload();";
		openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
	}

	public function joinform(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		// O2O 서비스 사용 중 휴대폰 정보 변경 인증 비활성화 방지
		//$this->load->library('o2o/o2orequiredlibrary');
		//$this->o2orequiredlibrary->required_block_disable_cellphone($_POST);

		if( $_POST['Label_cnt'] > 0 ){
			 $this->db->empty_table('fm_joinform');
		}
		$user_sub_arr = $_POST['labelItem']['user'];
		$sort_user=0;
		foreach($user_sub_arr as $k => $sub_arr){
			if($sub_arr['use'] =='')$sub_arr['use'] ='N';
			if($sub_arr['required'] =='')$sub_arr['required'] ='N';
			$sort_user++;
			$data = array(
							'joinform_seq'=> $sub_arr['joinform_seq'],
							'join_type' => 'user',
							'label_title' => $sub_arr['name'],
							'label_desc' => $sub_arr['exp'],
							'label_type' => $sub_arr['type'],
							'label_value' => $sub_arr['value'],
							'required' => $sub_arr['required'],
							'used' => $sub_arr['use'],
							'sort_seq' => $sort_user,
							'regist_date' => date('Y-m-d H:i:s'),
						);
			$this->db->insert('fm_joinform', $data);
		}
		$sort_order=0;
		$order_sub_arr = $_POST['labelItem']['order'];
		foreach($order_sub_arr as $k => $sub_arr){
			if($sub_arr['use'] =='')$sub_arr['use'] ='N';
			if($sub_arr['required'] =='')$sub_arr['required'] ='N';
			$sort_order++;
			$data = array(
							'joinform_seq'=> $sub_arr['joinform_seq'],
							'join_type' => 'order',
							'label_title' => $sub_arr['name'],
							'label_desc' => $sub_arr['exp'],
							'label_type' => $sub_arr['type'],
							'label_value' => $sub_arr['value'],
							'required' => $sub_arr['required'],
							'used' => $sub_arr['use'],
							'sort_seq' => $sort_order,
							'regist_date' => date('Y-m-d H:i:s'),
					);
			$this->db->insert('fm_joinform', $data);

		}
		### 설정저장
		$joinformar['user_icon'] = $_POST['user_icon'];
		$joinformar['email_userid'] = $_POST['email_userid'];
		$joinformar['join_type'] = $_POST['join_type'];

		// 만 14세 법정 동의서 파일 이동
		$agree_inlaw_filename = $this->input->post('agree_inlaw_filename');
		list($name,$type) = explode('.', $agree_inlaw_filename, 2);

		if(!empty($agree_inlaw_filename)){
			$upload_dir = ROOTPATH.'data/agreement';
			if(!is_dir($upload_dir)){
				mkdir($upload_dir);
				chmod($upload_dir, 0777);
				chown($upload_dir, "nobody");
				chgrp($upload_dir, "nobody");
			}

			$tmp_path			= ROOTPATH."data/tmp/".$agree_inlaw_filename;
			$new_filename		= "agreement_".date('YmdHis').".".$type;
			$new_path			= ROOTPATH."data/agreement/".$new_filename;

			// 파일 이동 소스
			if(!copy($tmp_path, $new_path))  {
				openDialogAlert('"법정 대리인 동의 파일 업로드 실패."',400,150,'parent');
				exit;
			}else{
				$joinformar['agree_inlaw_filename'] = $new_filename;
			}
		}

		// 만14세 미만 회원가입 설정
		$joinformar['kid_join_use'] = $_POST['kid_join_use'];
		if(!$_POST['birthday_use'] && !$_POST['birthday_required']){
			if($_POST['kid_join_use'] == 'Y' || $_POST['kid_join_use'] == 'N'){
				echo '<script> alert("만 14세 미만 회원가입 제한 시, 생일 항목은 필수입니다. 해제를 원하는 경우, 가입 제한을 \'제한 없음\'으로 변경해주세요."); </script>';
				exit;
			}
		}

		if($_POST['birthday_use'] && !$_POST['birthday_required']){
			if($_POST['kid_join_use'] == 'Y'){
				echo '<script> alert("만 14세 미만 회원가입 \'관리자 인증 후 가입\' 선택 시, 반드시 개인 회원가입 입력 항목에 \'생일\'을 필수로 선택해주세요."); </script>';
				exit;
			}else if($_POST['kid_join_use'] != 'A'){
				echo '<script> alert("만 14세 미만 회원가입 \'가입 불가\' 선택 시, 반드시 개인 회원가입 입력 항목에 \'생일\'을 필수로 선택해주세요."); </script>';
				exit;
			}
		}

		//snsmb
		if($_POST['join_type'] == 'member_only' ){
			unset($_POST['join_sns_bizonly'], $_POST['join_sns_mbbiz']);
		}

		if($_POST['join_type'] == 'member_business' ){
			unset($_POST['join_sns_mbonly'], $_POST['join_sns_bizonly']);
		}

		if($_POST['join_type'] == 'business_only' ){
			unset($_POST['join_sns_mbonly'], $_POST['join_sns_mbbiz']);
		}

		$joinformar['join_sns_mbonly'] = $_POST['join_sns_mbonly'];
		$joinformar['join_sns_bizonly'] = $_POST['join_sns_bizonly'];
		$joinformar['join_sns_mbbiz'] = $_POST['join_sns_mbbiz'];

		$joinformar['use_f']		= $_POST['use_f'];
		$joinformar['use_home']		= $_POST['use_home'];

		$snssocialar['use_f']			= $_POST['use_f'];
		$snssocialar['puny_domain']		= $_POST['puny_domain'];
		$snssocialar['uni_domain']		= $_POST['uni_domain'];
		//sns use
		if($_POST['key_f']){
		  $snssocialar['key_f']			= $_POST['key_f'];
		  $snssocialar['secret_f']		= $_POST['secret_f'];
		  $snssocialar['name_f']		= $_POST['name_f'];
		}else{
		  $snssocialar['key_f']			= '';
		  $snssocialar['secret_f']		= '';
		  $snssocialar['name_f']		= '';
		}

		if($_POST['use_t']){
		  $snssocialar['use_t']	= $_POST['use_t'];
		  $snssocialar['key_t']	= $_POST['key_t'];
		  $snssocialar['secret_t']	= $_POST['secret_t'];

		  $joinformar['use_t']		= $_POST['use_t'];
		}else{
		 $snssocialar['use_t']		= 0;
		 $snssocialar['key_t']		= '';
		 $snssocialar['secret_t']	= '';

		  $joinformar['use_t']		= 0;
		}

		/* naver login key */
		if(!$_POST['use_n']) $_POST['use_n'] = '0';
		$snssocialar['use_n'] = $_POST['use_n'];
		$snssocialar['key_n'] = $_POST['key_n'];
		$snssocialar['secret_n'] = $_POST['secret_n'];
		$joinformar['use_n'] = $_POST['use_n'];

		/* kakao login key */
		if(!trim($_POST['use_k'])) $_POST['use_k'] = '0';
		$snssocialar['use_k'] = $_POST['use_k'];
		$snssocialar['key_k'] = $_POST['key_k'];
		$snssocialar['kakaotalk_app_javascript_key'] = $_POST['key_k'];
		$joinformar['use_k'] = $_POST['use_k'];

		/* apple login key 추가 :: 2020-02-26 pjw */
		if(!$_POST['use_a']) $_POST['use_a'] = '0';
		$snssocialar['use_a']		= $_POST['use_a'];
		$snssocialar['key_a']		= trim($_POST['key_a']);
		$snssocialar['team_a']		= trim($_POST['team_a']);
		$snssocialar['clientid_a']	= trim($_POST['clientid_a']);
		$snssocialar['keyfile_a']	= $_POST['keyfile_a'];
		$joinformar['use_a']		= $_POST['use_a'];

		// 애플 인증파일이 업로드 된 경우 실제 경로로 이동 처리
		if(!empty($snssocialar['keyfile_a'])){
			$this->load->library('snssocial');
			$apple_private_key = $this->snssocial->apple_keyfile_value($snssocialar['keyfile_a']);

			// 업로드 여부에 따라 인증파일 설정값 분기처리
			if(!$apple_private_key['result']){
				$msg	= '애플 인증파일 업로드에 실패하였습니다.';
				$result = false;
			}else{
				// 인증파일명은 이동처리 하면서 바꾸므로 이동이 완료되면 고정이름으로 설정
				$snssocialar['private_key_a'] = $apple_private_key['private_key'];
				$snssocialar['keyfile_a']	  = 'AuthKey_'.$snssocialar['key_a'].'.p8';
			}
		}

		###
		if($_POST['join_type'] == "business_only"){
			if(isset($_POST['disabled_userid_business'])){
				$_POST['disabled_userid_business'] = str_replace(" ","",$_POST['disabled_userid_business']);
				$joinformar['disabled_userid'] = $_POST['disabled_userid_business'];
			}
		}else{
			if(isset($_POST['disabled_userid'])){
				$_POST['disabled_userid'] = str_replace(" ","",$_POST['disabled_userid']);
				$joinformar['disabled_userid'] = $_POST['disabled_userid'];
			}
		}

		###
		$user_arr = array('userid', 'password', 'user_name', 'email', 'phone', 'cellphone', 'address', 'recommend', 'birthday', 'sex', 'anniversary', 'nickname', 'o2oauthnum', 'o2ousername');
		$buss_arr = array('bname', 'bceo', 'bno', 'bitem', 'badress', 'bperson', 'bpart', 'bphone','bemail', 'bcellphone');
		for($i=0;$i<count($user_arr);$i++){
			$use_name = $user_arr[$i]."_use";
			$required_name = $user_arr[$i]."_required";
			$joinformvalue = if_empty($_POST, $use_name, 'N');
			$joinformar[$use_name] = $joinformvalue;//$this->joinform_config_save($_POST, $use_name);

			$required_joinformvalue = if_empty($_POST, $required_name, 'N');
			$joinformar[$required_name] = $required_joinformvalue;//$this->joinform_config_save($_POST, $required_name);
		}
		###
		for($i=0;$i<count($buss_arr);$i++){
			$use_name = $buss_arr[$i]."_use";
			$required_name = $buss_arr[$i]."_required";

			$joinformvalue = if_empty($_POST, $use_name, 'N');
			$joinformar[$use_name] = $joinformvalue;//$this->joinform_config_save($_POST, $use_name);

			$required_joinformvalue = if_empty($_POST, $required_name, 'N');
			$joinformar[$required_name] = $required_joinformvalue;//$this->joinform_config_save($_POST, $required_name);
		}

		if(is_array($joinformar)) config_save_array('joinform',$joinformar);
		if(is_array($snssocialar)) config_save_array('snssocial',$snssocialar);

		# ID/PW 찾기 보안문자 입력 사용여부 @2016-09-13 pjm
		if(!$_POST['find_id_use_captcha']) $_POST['find_id_use_captcha'] = "n";
		if(!$_POST['find_pass_use_captcha']) $_POST['find_pass_use_captcha'] = "n";
		config_save_array('find_idpass',array("find_id_use_captcha"=>$_POST['find_id_use_captcha'],
										"find_pass_use_captcha"=>$_POST['find_pass_use_captcha'],
										));


		// 카카오싱크 정보 변경
		if(isKakaoSyncUse()){
			$this->load->library('AdditionService/kakaosync/Client');
			$this->client->kakaosyncModify();
		}

		###
		$callback = "parent.set_member_html();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	## 전용앱 설정 저장(트위터 / 페이스북 / 카카오 / 인스타그램)
	public function joinform_sns_update(){

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_snsconf_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,140,'parent',null);
			exit;
		}

		$aPostParams = $this->input->post();

		// 고객들이 키 입력 시 공란을 자주 입력하여 post 값 모두 trim 처리 2019-05-08
		array_walk_recursive($aPostParams, function(&$item, $key) {
			$item = trim($item);

		});

		// 결과값 변수 정의
		$result = true;
		$msg	= '';

		// 페이스북 값 체크
		if( $aPostParams['snsmode'] == 'facebook' && $aPostParams['use_f'] && (!$aPostParams['key_f'] || !$aPostParams['secret_f']  || !$aPostParams['name_f']) ){
			$msg	= '페이스북의 설정값을 정확히 입력해 주세요.';
			$result = false;
		}

		// 트위터 값 체크
		if( $aPostParams['snsmode'] == 'twitter' && $aPostParams['use_t'] && (!$aPostParams['key_t'] || !$aPostParams['secret_t']) ){
			$msg = '트위터의 설정값을 정확히 입력해 주세요.';
			$result = false;
		}

		// 카카오 값 체크
		if( $aPostParams['snsmode'] == 'kakao' && $aPostParams['use_k'] && !$aPostParams['key_k'] ){
			if (($aPostParams['type_k'] == 'rest' && !$aPostParams['rest_key_k']) || ($aPostParams['type_k'] == 'js' && !$aPostParams['key_k'])) {
				$msg = '카카오의 설정값을 정확히 입력해 주세요.';
				$result = false;
			}
		}

		// 애플 값 체크
		if( $aPostParams['snsmode'] == 'apple' && $aPostParams['use_a'] == '1' && (!trim($aPostParams['key_a']) || !trim($aPostParams['team_a']) || !trim($aPostParams['clientid_a']) || !trim($aPostParams['keyfile_a'])) ){
			$msg = '애플의 설정값을 정확히 입력해 주세요.';
			$result = false;
		}

		// 유효성 검사를 통과했을때만 데이터 저장
		if($result){
			// 페이스북 설정
			$joinformar['use_f']		= $aPostParams['use_f'];
			$snssocialar['use_f']		= $aPostParams['use_f'];
			$snssocialar['key_f']		= $aPostParams['key_f'];
			$snssocialar['secret_f']	= $aPostParams['secret_f'];
			$snssocialar['name_f']		= $aPostParams['name_f'];
			$snssocialar['type_f']		= $aPostParams['type_f'];

			// 페이스북 전용앱 여부에 따라 분기처리
			if( $aPostParams['key_f'] != '455616624457601' ) {
				if($aPostParams['sns_req_type'])	$snssocialar['sns_req_type']	= $aPostParams['sns_req_type'];
				if($aPostParams['domain_f'])		$snssocialar['domain_f']		= $aPostParams['domain_f'];
			}

			// 트위터 설정
			$joinformar['use_t']		= $aPostParams['use_t'];
			$snssocialar['use_t']		= $aPostParams['use_t'];
			$snssocialar['key_t']		= $aPostParams['key_t'];
			$snssocialar['secret_t']	= $aPostParams['secret_t'];

			// 카카오 설정
			$joinformar['use_k']							= $aPostParams['use_k'];
			$snssocialar['use_k']							= $aPostParams['use_k'];

			if( $aPostParams['snsmode'] == 'kakao' ) {
				$snssocialar['type_k']							= $aPostParams['type_k'];
				$snssocialar['key_k']							= $aPostParams['key_k'];
				$snssocialar['rest_key_k']						= $aPostParams['rest_key_k'];
				$snssocialar['kakaotalk_app_javascript_key']	= $aPostParams['key_k'];
			}

			$snssocialar['use_talk_login']					= $aPostParams['use_talk_login'];

			// 애플 설정 추가 :: 2020-02-26 pjw
			$snssocialar['use_a']			= $aPostParams['use_a'];
			$snssocialar['key_a']			= trim($aPostParams['key_a']);
			$snssocialar['team_a']			= trim($aPostParams['team_a']);
			$snssocialar['clientid_a']		= trim($aPostParams['clientid_a']);
			$snssocialar['keyfile_a']		= $aPostParams['keyfile_a'];
			$joinformar['use_a']			= $aPostParams['use_a'];

			// 애플 인증파일이 업로드 된 경우 실제 경로로 이동 처리
			if($aPostParams['keyfile_new_a'] == 'y'){
				$this->load->library('snssocial');
				$apple_private_key = $this->snssocial->apple_keyfile_value($snssocialar['keyfile_a']);

				// 업로드 여부에 따라 인증파일 설정값 분기처리
				if(!$apple_private_key['result']){
					$msg	= '애플 인증파일 업로드에 실패하였습니다.';
					$result = false;
				}else{
					// 인증파일명은 이동처리 하면서 바꾸므로 이동이 완료되면 고정이름으로 설정
					$snssocialar['private_key_a']	= $apple_private_key['private_key'];
					$snssocialar['keyfile_a']		= 'AuthKey_'.$snssocialar['key_a'].'.p8';
				}
			}

			if(is_array($joinformar))	config_save_array('joinform',$joinformar);
			if(is_array($snssocialar))	config_save_array('snssocial',$snssocialar);
		}

		echo json_encode(array("result"=>$result, "msg"=>$msg));

	}


	# 네이버 로그인 사용여부 설정 @2015-12-14 pjm
	public function naver_login_use_change(){

		/* naver login key */
		//sdebug($_POST['use_n']);
		if($_POST['use_n'] == "Y"){
			config_save('joinform',array('use_n'=>'1'));
			config_save('snssocial',array('use_n'=>'1'));
		}else{
			config_save('joinform',array('use_n'=>'0'));
			config_save('snssocial',array('use_n'=>'0'));
		}
		echo json_encode(array("result"=>true));

	}

	public function nid_icon_delete(){

		if($_POST['nid_icon']){

			$file_url	= explode("/",$_POST['nid_icon']);
			$file_name	= $file_url[sizeof($file_url)-1];
			$folder		= ROOTPATH."data/icon/common/".$file_name;
			$res		= unlink($folder);

			if($res){
				config_save_array('snssocial',array("nid_icon_url"=>""));
				echo json_encode(array('result'=>'ok'));
			}else{
				echo json_encode(array('result'=>'fail'));
			}
		}else{
			echo json_encode(array('result'=>'noimg'));
		}

		exit;
	}

	# 네이버 로그인 API 호출전 stats값 생성 @2016-01-06 pjm
	public function nid_api_stats(){

		$nid_stats = mktime().(microtime()*1000000);
		config_save('nid_api',array('nid_stats'=>$nid_stats));

		echo $nid_stats;
	}

	# 네이버 로그인 API 상점 icon 생성 @2016-01-06 pjm
	public function joinform_naver_fileupload(){

		$this->load->helper('board');//
		$folder	= ROOTPATH."/data/icon/common/";

		foreach($_FILES as $key => $value)
		{
			$tmpname	= $value['tmp_name'];
			$file_ext	= end(explode('.', $value['name']));//확장자추출
			$file_name	= "nid_consumer_imagE_url_".mktime().".".$file_ext;
			$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
			$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
			$saveFile	= $folder.$file_name;
			$config['allowed_types'] = 'jpg|gif|jpeg|png';
			$tmp = @getimagesize($value['tmp_name']);
			if(!$tmp['mime']){
				$_FILES['Filedata']['type'] = $file_ext;//확장자추출
			}else{
				$_FILES['Filedata']['type'] = $tmp['mime'];
			}

			if($value['size'] > 512000){
				$error = array('status' => 0,'message'=>'쇼핑몰 로고 이미지는 500kb를 넘을 수 없습니다.','saveFile' => "",'file_name' => "");
				echo "[".json_encode($error)."]";
				//openDialogAlert("쇼핑몰 로고 이미지는 500kb를 넘을 수 없습니다.",400,140,'parent',$callback);
				exit;
			}
			$fileresult = board_upload($key, $file_name, $folder, $config, $saveFile, 0, 'nid');//status  error, fileInfo
			if(!$fileresult['status']){
				$error = array('status' => 0,'msg' => $fileresult['error'],'desc' => '업로드 실패');
				echo "[".json_encode($error)."]";
				exit;
			}

		}

		$domain = $this->config_system['subDomain'];
		if($this->config_system['ssl_status'] == '1' && !empty($this->config_system['ssl_domain'])) {
		    $domain = $this->config_system['ssl_domain'];
		}

		$result = array('status' => 1,'saveFile' => "/data/icon/common/".$file_name,'file_name' => get_connet_protocol().$domain."/data/icon/common/".$file_name);
		echo "[".json_encode($result)."]";
		exit;
	}

	# 네이버 로그인 API 처리 완료 @2016-01-06 pjm
	# 실제 처리 데이터는 /sns_process/nid_api_callback 에서 처리함. (curl로 admin 접근 불가)
	public function joinform_naver_end(){

		$message = $_POST['message'];

		if($_POST['result'] == "ok"){

			/* 주요행위 기록 */
			$this->load->model('managermodel');
			$this->managermodel->insert_action_history($this->managerInfo['manager_seq'],'sns_nid_api_setting',$message." (".$_SERVER['REMOTE_ADDR'].")");

			$width	= 300;
			$height = 150;
			if($_POST['mode'] == "apply" || $_POST['mode'] == "reapply"){
				$width	= 350;
				$height = 190;
				$message .= "<br />네이버 아이디로 로그인 서비스를<br />[사용함]으로 변경해 주세요.";
			}

			openDialogAlert( $message ,$width,$height,'parent','parent.nidReload()');

		}else{

			$width = 400;
			if($_POST['code'] == "99") $width = 300;

			$message_tmp = explode("<br />",$message);
			$message = $message_tmp[0];

			if($_POST['code'] == "04" || $_POST['code'] == "05"){
				openDialogConfirm($message,400,160,'parent','parent.nidReApplyLayMov();','');
			}else{
				openDialogAlert( $message ,$width,150,'parent');
				//parent.nidCancelLayMov();
			}

		}
	}


	public function joinform_snsconfig_save($params, $name){
		$value = if_empty($params, $name, 'N');
		//echo $name." : ".$value."<br>";
		config_save('joinform',array($name=>$value));
	}

	public function joinform_config_save($params, $name){
		$value = if_empty($params, $name, 'N');
		//echo $name." : ".$value."<br>";
		config_save('joinform',array($name=>$value));
	}


	### 승인/혜택
	public function approval(){
		$aPostParams = $this->input->post();

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		if( $aPostParams['invitecount'] > $aPostParams['invitemaxcount'] ){
			openDialogAlert('누적기준 초대인원수는 페이스북 초대인원수보다 크게 설정해 주세요.',450,140,'parent','');
			exit;
		}

		if( $aPostParams['emoneyInvitedCnt']>0 && $aPostParams['emoneyInvitedCnt']<10){//10이상입력
			openDialogAlert('친구를 초대할 때마다 지급되는 마일리지은 [10원]이상 설정해 주세요.',450,140,'parent','');
			exit;
		}

		### Validation
		$this->validation->set_rules('emoneyJoin', '회원 가입 시 마일리지','trim|required|numeric|max_length[10]|xss_clean');
		$this->validation->set_rules('emoneyRecommend', '추천 받은 자 마일리지','trim|required|numeric|max_length[10]|xss_clean');
		$this->validation->set_rules('emoneyLimit', '마일리지 제한','trim|required|numeric|max_length[10]|xss_clean');
		$this->validation->set_rules('emoneyJoiner', '추천 한 자 마일리지','trim|required|numeric|max_length[10]|xss_clean');
		$this->validation->set_rules('emoneyLimit', '마일리지','trim|required|numeric|max_length[10]|xss_clean|greater_than_equal_to[1]');

		if( $this->isplusfreenot && $this->isplusfreenot['ispoint'] ) {
			$this->validation->set_rules('pointLimit', '포인트','trim|required|numeric|max_length[10]|xss_clean|greater_than_equal_to[1]');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		//페이스북 친구초대 타이틀/문구
		$snstitle			= ($aPostParams['snstitle'])?$aPostParams['snstitle']:'';
		$snsDescription		= ($aPostParams['snsDescription'])?$aPostParams['snsDescription']:'';

		### 설정저장
		config_save('snssocial',array('snstitle'=> $snstitle));
		config_save('snssocial',array('snsDescription'=> $snsDescription));

		$memberar['autoApproval']			= $aPostParams['autoApproval'];
		$memberar['autoApproval_biz']		= $aPostParams['autoApproval_biz'];
		$memberar['emoneyJoin']				= get_cutting_price($aPostParams['emoneyJoin']);
		$memberar['emoneyRecommend']		= get_cutting_price($aPostParams['emoneyRecommend']);
		$memberar['emoneyTerm']				= $aPostParams['emoneyTerm'];
		$memberar['emoneyLimit']			= $aPostParams['emoneyLimit'];
		$memberar['emoneyJoiner']			= get_cutting_price($aPostParams['emoneyJoiner']);

		$memberar['emoneyInvitees']			= $aPostParams['emoneyInvitees'];
		$memberar['emoneyInvited']			= $aPostParams['emoneyInvited'];

		$memberar['emoneyTerm_invited']		= $aPostParams['emoneyTerm_invited'];
		$memberar['emoneyLimit_invited']	= $aPostParams['emoneyLimit_invited'];

		$memberar['invitecount']			= $aPostParams['invitecount'];
		$memberar['emoneyInvitedCnt']		= $aPostParams['emoneyInvitedCnt'];
		$memberar['invitemaxcount']			= $aPostParams['invitemaxcount'];


		###	POINT
		$memberar['reserve_select']			= $aPostParams['reserve_select'];
		$memberar['reserve_year']			= $aPostParams['reserve_year'];
		$memberar['reserve_direct']			= $aPostParams['reserve_direct'];

		$memberar['joiner_reserve_select']	= $aPostParams['joiner_reserve_select'];
		$memberar['joiner_reserve_year']	= $aPostParams['joiner_reserve_year'];
		$memberar['joiner_reserve_direct']	= $aPostParams['joiner_reserve_direct'];


		$memberar['recomm_reserve_select']	= $aPostParams['recomm_reserve_select'];
		$memberar['recomm_reserve_year']	= $aPostParams['recomm_reserve_year'];
		$memberar['recomm_reserve_direct']	= $aPostParams['recomm_reserve_direct'];
		$memberar['start_date']				= $aPostParams['start_date'];
		$memberar['end_date']				= $aPostParams['end_date'];
		$memberar['emoneyJoin_limit']		= get_cutting_price($aPostParams['emoneyJoin_limit']);

		###
		$memberar['invit_reserve_select']	= $aPostParams['invit_reserve_select'];
		$memberar['invit_reserve_year']		= $aPostParams['invit_reserve_year'];
		$memberar['invit_reserve_direct']	= $aPostParams['invit_reserve_direct'];

		$memberar['invited_reserve_select'] = $aPostParams['invited_reserve_select'];
		$memberar['invited_reserve_year']	= $aPostParams['invited_reserve_year'];
		$memberar['invited_reserve_direct'] = $aPostParams['invited_reserve_direct'];

		$memberar['cnt_reserve_select']		= $aPostParams['cnt_reserve_select'];
		$memberar['cnt_reserve_year']		= $aPostParams['cnt_reserve_year'];
		$memberar['cnt_reserve_direct']		= $aPostParams['cnt_reserve_direct'];



		if( $this->isplusfreenot) {//무료몰이아닌경우에만 적용 @2013-01-14
			$memberar['pointJoin']				= get_cutting_price($aPostParams['pointJoin']);
			$memberar['pointJoiner']			= get_cutting_price($aPostParams['pointJoiner']);

			$memberar['point_select']			= $aPostParams['point_select'];
			$memberar['point_year']				= $aPostParams['point_year'];
			$memberar['point_direct']			= $aPostParams['point_direct'];

			$memberar['joiner_point_select']	= $aPostParams['joiner_point_select'];
			$memberar['joiner_point_year']		= $aPostParams['joiner_point_year'];
			$memberar['joiner_point_direct']	= $aPostParams['joiner_point_direct'];
			$memberar['pointJoin_limit']		= get_cutting_price($aPostParams['pointJoin_limit']);

			$memberar['pointRecommend']			= get_cutting_price($aPostParams['pointRecommend']);
			$memberar['pointTerm']				= $aPostParams['pointTerm'];
			$memberar['pointLimit']				= $aPostParams['pointLimit'];

			$memberar['recomm_point_select']	= $aPostParams['recomm_point_select'];
			$memberar['recomm_point_year']		= $aPostParams['recomm_point_year'];
			$memberar['recomm_point_direct']	= $aPostParams['recomm_point_direct'];

			$memberar['invit_point_select']		= $aPostParams['invit_point_select'];
			$memberar['invit_point_year']		= $aPostParams['invit_point_year'];
			$memberar['invit_point_direct']		= $aPostParams['invit_point_direct'];

			$memberar['pointInvited']			= $aPostParams['pointInvited'];
			$memberar['pointTerm_invited']		= $aPostParams['pointTerm_invited'];
			$memberar['pointLimit_invited']		= $aPostParams['pointLimit_invited'];

			$memberar['pointInvitees']			= $aPostParams['pointInvitees'];
			$memberar['invited_point_select']	= $aPostParams['invited_point_select'];
			$memberar['invited_point_year']		= $aPostParams['invited_point_year'];
			$memberar['invited_point_direct']	= $aPostParams['invited_point_direct'];

			$memberar['pointInvitedCnt']		= $aPostParams['pointInvitedCnt'];

			$memberar['cnt_point_select']		= $aPostParams['cnt_point_select'];
			$memberar['cnt_point_year']			= $aPostParams['cnt_point_year'];
			$memberar['cnt_point_direct']		= $aPostParams['cnt_point_direct'];
		}
		if(is_array($memberar)) config_save_array('member',$memberar);

		###
		$callback = "parent.set_member_html();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	### 등급
	public function grade(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		if($_POST['grade_mode']=='deleteGrade'){
			for ($i = 0 ; $i < count($_POST['group_seq']) ; $i++) {
				$group_seq = $_POST['group_seq'][$i];
				$result = $this->db->delete('fm_member_group', array('group_seq' => $group_seq));
				$result = $this->db->delete('fm_member_group_issuegoods', array('group_seq' => $group_seq));
				$result = $this->db->delete('fm_member_group_issuecategory', array('group_seq' => $group_seq));
				$result = $this->db->delete('fm_category_group', array('group_seq' => $group_seq));
				$result = $this->db->delete('fm_brand_group', array('group_seq' => $group_seq));
			}
			if($result){
				$callback = "parent.set_member_html();";
				openDialogAlert("삭제되었습니다.",400,140,'parent',$callback);
			}
		}else{
			config_save('grade_clone',array('start_month'=>$_POST['start_month']));
			config_save('grade_clone',array('chg_term'	=>$_POST['chg_term']));
			config_save('grade_clone',array('chg_day'	=>$_POST['chg_day']));
			config_save('grade_clone',array('chk_term'	=>$_POST['chk_term']));
			config_save('grade_clone',array('keep_term'	=>$_POST['keep_term']));
			$callback = "parent.set_member_html();";
			openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
		}
	}
	public function grade_write(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		### Validation
		$this->validation->set_rules('group_name', '명칭','trim|required|max_length[15]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if($_POST['order_sum_price']) $_POST['order_sum_price'] = get_cutting_price($_POST['order_sum_price']);

		###
		/* 자동관리 산정기준 저장 안됨으로 추가 leewh 2014-09-16 */
		if($_POST['use_type']=="AUTOPART"){
			$_POST['order_sum_use']		= $_POST['order_sum_use2'];
			$_POST['order_sum_price']	= get_cutting_price($_POST['order_sum_price2']);
			$_POST['order_sum_ea']		= $_POST['order_sum_ea2'];
			$_POST['order_sum_cnt']		= $_POST['order_sum_cnt2'];
		}

		$params = $_POST;
		$params['regist_date'] = date('Y-m-d H:i:s');
		if(isset($_POST['order_sum_use'])) if(is_array($_POST['order_sum_use'])) $params['order_sum_use'] = serialize($_POST['order_sum_use']);
		$data = filter_keys($params, $this->db->list_fields('fm_member_group'));
		$result = $this->db->insert('fm_member_group', $data);
		$group_seq = $this->db->insert_id();

		### SALE
		for($i=0;$i<count($_POST['issueGoods']);$i++){
			if($_POST['issueGoods'][$i])
			$result = $this->db->insert('fm_member_group_issuegoods', array('group_seq'=>$group_seq,'goods_seq'=>$_POST['issueGoods'][$i],'type'=>'sale'));
		}
		for($i=0;$i<count($_POST['issueCategoryCode']);$i++){
			$result = $this->db->insert('fm_member_group_issuecategory', array('group_seq'=>$group_seq,'category_code'=>$_POST['issueCategoryCode'][$i],'type'=>'sale'));
		}

		### EMONEY
		for($i=0;$i<count($_POST['exceptIssueGoods']);$i++){
			if($_POST['exceptIssueGoods'][$i])
			$result = $this->db->insert('fm_member_group_issuegoods', array('group_seq'=>$group_seq,'goods_seq'=>$_POST['exceptIssueGoods'][$i],'type'=>'emoney'));
		}
		for($i=0;$i<count($_POST['issueCategoryCode']);$i++){
			$result = $this->db->insert('fm_member_group_issuecategory', array('group_seq'=>$group_seq,'category_code'=>$_POST['exceptIssueCategoryCode'][$i],'type'=>'emoney'));
		}



		###
		if($result){
			//$callback = "parent.set_member_html();parent.closeDialog('gradePopup');";
			$callback = "parent.formMove('grade',4);";
			openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
		}
	}
	public function grade_modify(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		### Validation
		$this->validation->set_rules('group_name', '명칭','trim|required|max_length[15]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if($_POST['order_sum_price']) $_POST['order_sum_price'] = get_cutting_price($_POST['order_sum_price']);

		### 무료몰인경우초기화@2013-01-14
		if( !$this->isplusfreenot || $_POST['seq'] == 1) {
			$_POST['point_price2']			= '';
			$_POST['point_select']			= '';
			$_POST['point_price_type2']		= '';
			$_POST['point_year']			= '';
			$_POST['point_direct']			= '';
		}

		if($_POST['use_type']=="AUTOPART"){
			$_POST['order_sum_use']			= $_POST['order_sum_use2'];
			$_POST['order_sum_price']		= get_cutting_price($_POST['order_sum_price2']);
			$_POST['order_sum_ea']			= $_POST['order_sum_ea2'];
			$_POST['order_sum_cnt']			= $_POST['order_sum_cnt2'];
		}

		# 기본 등급일 경우 등업조건 없음(초기화)@2014-06-09
		if( $_POST['seq'] == 1 ){
			$_POST['order_sum_use']			= '';
			$_POST['order_sum_price']		= '';
			$_POST['order_sum_ea']			= '';
			$_POST['order_sum_cnt']			= '';
		}

		$params = $_POST;
		//$params['sale_use'] = if_empty($params, 'sale_use', 'N');
		//$params['point_use'] = if_empty($params, 'point_use', 'N');
		$params['update_date'] = date('Y-m-d H:i:s');

		if( is_array($_POST['order_sum_use']) ) $params['order_sum_use'] = serialize($_POST['order_sum_use']);
		$data = filter_keys($params, $this->db->list_fields('fm_member_group'));

		$this->db->where('group_seq', $params['seq']);
		$result = $this->db->update('fm_member_group', $data);

		###
		if($result){
			//$callback = "parent.set_member_html();parent.closeDialog('gradePopup');";
			$callback = "parent.formMove('grade',4);";
			openDialogAlert("설정이 수정 되었습니다.",400,140,'parent',$callback);
		}
	}


	### 로그아웃/탈퇴/재가입
	public function withdraw(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_member_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		// O2O 서비스 사용 중 휴대폰 정보 변경 인증 비활성화 방지
		$this->load->library('o2o/o2orequiredlibrary');
		$this->o2orequiredlibrary->required_block_disable_confirm_phone($_POST);

		if ( $_POST['confirmPhone'] === 'Y' ) {
			// 휴대폰 정보 변경 인증 문자 검사 :: 2016-04-19 lwh
			if(!preg_match('/\{phonecertify\}/',$_POST['confirmsendmsg'])){
				openDialogAlert('인증번호 치환코드 &#123;phonecertify&#125; 는 필수입니다.',400,140,'parent','');
				exit;
			}

			config_save('member',array('confirmsendmsg'=>$_POST['confirmsendmsg']));
		}
		### 설정저장
		config_save('member',array('confirmPhone'=>$_POST['confirmPhone']));
		config_save('member',array('confirmPW'=>$_POST['confirmPW']));
		config_save('member',array('sessLimit'=>$_POST['sessLimit']));
		config_save('member',array('sessLimitMin'=>$_POST['sessLimitMin']));
		config_save('member',array('modifyPW'=>$_POST['modifyPW']));
		config_save('member',array('modifyPWMin'=>$_POST['modifyPWMin']));
		config_save('member',array('dormancy'=>$_POST['dormancy']));
		###
		$callback = "parent.set_member_html();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}


	public function withdrawal_set(){
		// 회원 리스트에서 탈퇴 기능 없앰.
		exit;
		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('withdrawal_act');
		if(!$auth){
			pageBack("관리자 권한이 없습니다.");
			exit;
		}

		//$this->load->model('membermodel');
		$member_arr = $_GET['member_chk'];
		###
		$params['regist_date']	= date('Y-m-d H:i:s');
		$params['reason']		= "관리자 리스트 탈퇴처리";
		$params['regist_ip']	= $_SERVER['REMOTE_ADDR'];
		$this->load->library('memberlibrary');
		//회원탈퇴
		$withdrawalMsg = $this->memberlibrary->set_withdrawal($params, $member_arr);
		###
		$callback = "parent.location.reload();";
		openDialogAlert($withdrawalMsg['msg'],400,140,'parent',$callback);
	}

	# 회원정보 변경 로그
	public function memberinfo_change_log($member,$params){

		$chk_field = array("status"			=> "회원 승인여부"
							,"recommend"		=> "추천인 정보"
							,"mailing"			=> "이메일 수신여부"
							,"sms"				=> "핸드폰 수신여부"
					);

		if($params['user_type'] == "business"){
			$chk_field_add = array("bname"				=> "업체명 정보"
								,"bceo"				=> "대표자명 정보"
								,"bno"				=> "사업자 등록번호"
								,"bitem"			=> "업태 정보"
								,"bstatus"			=> "종목 정보"
								,"companyAddress"	=> "사업장 주소"
								,"bperson"			=> "담당자명 정보 정보"
								,"bpart"			=> "담당자 부서명 정보"
								,"bphone"			=> "담당자 전화번호"
								,"bcellphone"		=> "담당자 핸드폰 정보"
								,"email"			=> "이메일 정보"
						);
		}else{
			$chk_field_add = array("address"			=> "주소"
								,"address_street"	=> "도로명 주소"
								,"email"			=> "이메일 정보"
								,"cellphone"		=> "핸드폰 정보"
								,"phone"			=> "전화번호 정보"
								,"nickname"			=> "회원닉네임 정보"
								,"fb_invite"		=> "Facebook 초대인 정보"
								,"birthday"			=> "회원생일 정보"
								,"sex"				=> "회원성별 정보"
								,"anniversary"		=> "회원기념일 정보"
								,"user_name"		=> "회원이름 정보"
						);
		}

		$array_sex		= array('none' => '없음','male' => '남자','female' => '여자');
		$array_status	= array('done' => '승인','hold' => '미승인');

		$chk_field		= array_merge($chk_field,$chk_field_add);
		$log_str		= array();

		foreach($chk_field as $field => $title){

			$before		= $member[$field] ? $member[$field] : '없음';
			$after		= $params[$field] ? $params[$field] : '없음';

			if($field == "sex"){
				if($array_sex[$before] != "없음")	$before = $array_sex[$before];
				if($array_sex[$after] != "없음")	$after	= $array_sex[$after];
			}elseif($field == "status"){
				if($array_status[$before] != "없음")$before = $array_status[$before];
				if($array_status[$after] != "없음")	$after	= $array_status[$after];
			}elseif($field == "address"){
				$before		= $member['address'].$member['address_detail'];
				$after		= $params['address'].$params['address_detail'];
				if(!$before)	$before = "없음";
				if(!$after)		$after	= "없음";
			}elseif($field == "companyAddress"){
				$before		= $member['address'].$member['address_detail'];
				$after		= $params['companyAddress'].$params['baddress_detail'];
				if(!$before)	$before = "없음";
				if(!$after)		$after	= "없음";
			}

			if($before != $after){
				$log_str[] = "<div>".$this->managerInfo['manager_id']."에 의해 ".$title."가 ".date("Y년m월d일 H시i분s초")."에 변경됨 [".$before."] → [".$after."] (".$_SERVER['REMOTE_ADDR'].")</div>";
			}
		}

		return implode("",$log_str);

	}

	public function member_modify(){

		$aPostParams	 = $this->input->post();

		if($aPostParams['user_type'] == "business"){
			unset($aPostParams['cellphone']);
		}

		// validation
		if ($aPostParams) {
			$this->validation->set_data($aPostParams);
			$this->validation->set_rules('member_seq', '회원 번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('business_seq', '기업 회원 번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('status', '상태', 'trim|string|xss_clean');
			$this->validation->set_rules('group_seq', '그룹 번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('group_name', '그룹명', 'trim|string|xss_clean');
			$this->validation->set_rules('user_type', '사용자 구분', 'trim|string|xss_clean');
			$this->validation->set_rules('user_name', '사용자명', 'trim|string|xss_clean');
			$this->validation->set_rules('nickname', '별명', 'trim|string|xss_clean');
			$this->validation->set_rules('email', '이메일', 'trim|valid_email|xss_clean');
			$this->validation->set_rules('mailing', '메일링', 'trim|string|xss_clean');
			$this->validation->set_rules('phone[]', '전화번호', 'trim|numeric|xss_clean');
			$this->validation->set_rules('cellphone[]', '핸드폰', 'trim|numeric|xss_clean');
			$this->validation->set_rules('sms', 'SMS여부', 'trim|string|xss_clean');
			$this->validation->set_rules('recommend', '추천', 'trim|string|xss_clean');
			$this->validation->set_rules('birthday', '생년월일', 'trim|string|xss_clean');
			$this->validation->set_rules('sex', '성별', 'trim|string|xss_clean');
			$this->validation->set_rules('bname', '상호', 'trim|string|xss_clean');
			$this->validation->set_rules('bceo', '대표자', 'trim|string|xss_clean');
			$this->validation->set_rules('bno', '사업자번호', 'trim|string|xss_clean');
			$this->validation->set_rules('bitem', '업태', 'trim|string|xss_clean');
			$this->validation->set_rules('bstatus', '상태', 'trim|string|xss_clean');

			if (isset($aPostParams['user_type']) && $aPostParams['user_type'] == 'business') {
				$this->validation->set_rules('companyAddress_type', '주소타입', 'trim|string|xss_clean');
				$this->validation->set_rules('companyZipcode[]', '우편번호', 'trim|numeric|xss_clean');
				$this->validation->set_rules('companyAddress', '주소', 'trim|string|xss_clean');
				$this->validation->set_rules('companyAddress_street', '도로명주소', 'trim|string|xss_clean');
				$this->validation->set_rules('baddress_detail', '도로명주소', 'trim|string|xss_clean');
			} else {
				$this->validation->set_rules('Address_type', '주소 타입', 'trim|string|xss_clean');
				$this->validation->set_rules('Zipcode[]', '우편번호', 'trim|numeric|xss_clean');
				$this->validation->set_rules('Address', '주소', 'trim|string|xss_clean');
				$this->validation->set_rules('Address_street', '도로명 주소', 'trim|string|xss_clean');
				$this->validation->set_rules('address_detail', '상세 주소', 'trim|string|xss_clean');
			}

			$this->validation->set_rules('bperson', '담당자', 'trim|string|xss_clean');
			$this->validation->set_rules('bpart', '부서', 'trim|string|xss_clean');
			$this->validation->set_rules('bphone[]', '담당자 연락처', 'trim|numeric|xss_clean');
			$this->validation->set_rules('bcellphone[]', '담당자 핸드폰', 'trim|numeric|xss_clean');
			if ($this->validation->exec() === false) {
				alert($this->validation->error_array['value']);
				exit;
			}
		}

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('member_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		###
		$params						= $aPostParams;
		$seq						= $aPostParams['member_seq'];
		$params['zipcode']			= implode('',$aPostParams['Zipcode']);
		$params['address_type']		= $aPostParams['Address_type'];
		$params['address']			= $aPostParams['Address'];
		$params['address_street']	= $aPostParams['Address_street'];
		$params['mtype']			= $aPostParams['user_type'];
		$params['mall_t_check']		= ($aPostParams['mall_t_check'] == 'Y') ? 'Y' : 'N';
		$params['mailing']			= ($aPostParams['mailing'] == 'y') ? 'y' : 'n';
		$params['sms']				= ($aPostParams['sms'] == 'y') ? 'y' : 'n';

		unset($params['member_seq']);

		$label_pr					= $aPostParams['label'];
		$label_sub_pr				= $aPostParams['labelsub'];

		$member = $this->membermodel->get_member_data($seq);
		if ($member['status'] == 'withdrawal') {
			$callback = "";
			openDialogAlert("탈퇴회원 정보수정은 불가합니다",400,140,'parent',$callback);
			exit;
		}

		### COMMON
		if(!empty($params['anniversary'][0]) && !empty($params['anniversary'][1])){
			$params['anniversary'] = implode("-",$params['anniversary']);
		}else{
			$params['anniversary'] = '';
		}

		$params['phone'] = $this->input->post('phone');
		$params['cellphone'] = $this->input->post('cellphone');
		$params['bphone'] = $this->input->post('bphone');
		$params['bcellphone'] = $this->input->post('bcellphone');

		if(isset($params['phone'])) {
			$params['phone'] = implode("-",array_filter($params['phone']));
		}

		if(isset($params['cellphone'])) {
			$params['cellphone'] = implode("-",array_filter($params['cellphone']));
		}

		if(isset($params['bphone'])) {
			$params['bphone'] = implode("-",array_filter($params['bphone']));
		}

		if(isset($params['bcellphone'])) {
			$params['bcellphone'] = implode("-",array_filter($params['bcellphone']));
		}

		// 회원 이름명 OR 업체명 20자 제한
		if (isset($params['user_type'])) {
			if (isset($params['user_name'])) {
				$this->validation->set_rules('user_name', getAlert('mb016'),'trim|max_length[20]|xss_clean');
			}
	
			if (isset($params['bname'])) {
				$this->validation->set_rules('bname', getAlert('mb026'),'trim|max_length[20]|xss_clean');
			}

			if ($this->validation->exec()===false) {
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();".$callback_default;
				openDialogAlert($err['value'],400,140,'parent',$callback);
				exit;
			}
		}

		$admin_log = "";
		if($params['passwd_chg'] || $params['busi_passwd_chg']) {

            // 비밀번호 유효성 체크
            $pre_enc_password = $member['password'];
            $enc_password = hash('sha256',md5($params['password']));

            $check_password = $this->input->post('password');
            $password_params = array(
                'birthday'                => array($this->input->post('birthday'), $params['anniversary']),
                'phone'                   => array($params['phone'], $params['bphone']),
				'cellphone'                   => array($params['cellphone'], $params['bcellphone']),
                'pre_enc_password'        => $pre_enc_password,
                'enc_password'            => $enc_password,
            );
            $this->load->library('memberlibrary');
            $result = $this->memberlibrary->check_password_validation($check_password, $password_params);
            if($result['code'] != '00' && $result['alert_code']){
                openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback);
                exit;
            }

			### Validation
			$this->validation->set_rules('password', '비밀번호','trim|required|max_length[20]|xss_clean');
			$this->validation->set_rules('manager_password', '관리자 비밀번호','trim|required|max_length[32]|xss_clean');

			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,140,'parent',$callback);
				exit;
			}

			### 관리자 비밀번호 검증
			$str_md5 = md5($params['manager_password']);
			$str_sha256_md5 = hash('sha256',$str_md5);
			$query = "select * from fm_manager where manager_id=? and (mpasswd=? OR mpasswd=?)";
			$query = $this->db->query($query,array($this->managerInfo['manager_id'],$str_md5,$str_sha256_md5));
			$data = $query->row_array();
			if(!$data){
				$callback = "";
				openDialogAlert("관리자 정보가 일치하지 않습니다.",400,140,'parent',$callback);
				exit;
			}

			$params['password'] = hash('sha256',md5($params['password']));
			$admin_log .= "<div>".$this->managerInfo['manager_id']."에 의해 비밀번호가 ".date("Y년m월d일 H시i분s초")."에 변경됨 (".$_SERVER['REMOTE_ADDR'].")</div>";
		}

		### 추가정보 저장
		if($label_pr){
			$this->db->delete('fm_member_subinfo', array('member_seq'=>$seq));
			foreach ($label_pr as $k => $data){
				foreach ($data['value'] as $j => $subdata){
					$setdata['label_value']= $subdata;
					$setdata['label_sub_value']= $label_sub_pr[$k]['value'][$j];
					$query = $this->db->get_where('fm_joinform',array('joinform_seq'=> $k));
					$form_result = $query -> row_array();
					$setdata['label_title'] = $form_result['label_title'];
					$setdata['joinform_seq'] = $form_result['joinform_seq'];
					$setdata['member_seq'] = $seq;
					$setdata['regist_date'] = date('Y-m-d H:i:s');
				$result = $this->db->insert('fm_member_subinfo', $setdata);
				}//debug_var($setdata);
			}
		}

		### LOG
		if($member['mailing']!=$params['mailing']||$member['sms']!=$params['sms']){
			$params['marketing_agree_send_date'] = date("Y-m-d H:i:s", time());
		}

		$params['mailing'] = $this->input->post('mailing')!='y'?'n':$this->input->post('mailing');
		$params['sms'] = $this->input->post('sms')!='y'?'n':$this->input->post('sms');

		if($params['group_seq']!=$member['group_seq']){
			$params['grade_update_date'] = date('Y-m-d H:i:s');

			### 자동등급으로 변경시 등급유지기간 설정
			$sql = "select * from fm_member_group where group_seq = '".$params['group_seq']."'";
			$query = $this->db->query($sql);
			$mbgpdata = $query->row_array();
			$change_group = false;
			$cfg_grade = config_load('grade_clone');
			if( $cfg_grade['keep_term'] && ( $mbgpdata['use_type']=='AUTO' || $mbgpdata['use_type']=='AUTOPART' ) ) {
				$keep_term			= $cfg_grade['keep_term'];
				$keep_term_date	= date('Y-m-d',strtotime('+'.$keep_term.' month'));
				$change_group = true;
			}

			### LOG
			$i_qry = "insert into fm_member_group_log set member_seq = ?, prev_group_seq = ?, chg_group_seq = ?, regist_date=now()";
			$this->db->query($i_qry,array($member['member_seq'],$member['group_seq'],$params['group_seq']));

			if($change_group)
			{
				$grade_msg = "(".$keep_term."개월간 등급 유지, 즉 ". $keep_term_date."까지)";
				$params['group_set_date'] = $keep_term_date;
			}else{//초기화
				$grade_msg						 = '';
				$params['group_set_date']  = '';
			}
			$admin_log .= "<div>[수동] ".date('Y-m-d H:i:s')." ".$member['group_name']." → ".$params['group_name'].$grade_msg." (".$this->managerInfo['manager_id'].", ".$_SERVER['REMOTE_ADDR'].")</div>";
		}

		### LOG 회원정보 변경 로그 일괄 처리 @2016-03-25 pjm
		$admin_log			.= $this->memberinfo_change_log($member,$params);

		// 기존 로그에 추가처리 @2016-06-08 pjw
		$result_log = $this->db->query('select admin_log from fm_member where member_seq = '.$seq);
		$result_log = $result_log->row_array();
		$params['admin_log'] = $admin_log.$_POST['admin_log'].$result_log['admin_log'];

		$params['user_icon']	= if_empty($params, 'user_icon', '0');;//@2014-08-06 icon

		// 가입 상태 승인시 만 14세 승인 설정도 Y
		if($_POST['status'] == 'done'){
			$params['kid_auth'] = 'Y';
		}

		// 기업회원일경우 이름 전달
		if(isset($_POST['user_type']) && $_POST['user_type']=='business'){
			$params['user_name']		= $params['bname'];
			$params['address_type']		= $params['baddress_type'];
			$params['address']			= $params['baddress'];
			$params['address_detail']	= $params['baddress_detail'];
			$params['phone']			= $params['bphone'];
			$params['cellphone']		= $params['bcellphone'];
		}

		###
		$data		= filter_keys($params, $this->db->list_fields('fm_member'));
		$this->db->where('member_seq', $seq);
		$result		= $this->db->update('fm_member', $data);

		## 개인정보 암호화
		$this->membermodel->update_private_encrypt($seq, $data);

		### BUSINESS CHK
		$business_seq = $params['business_seq'];
		$params['bzipcode'] = implode('',$aPostParams['companyZipcode']);
		$params['baddress_type'] = $aPostParams['companyAddress_type'];
		$params['baddress'] = $aPostParams['companyAddress'];
		$params['baddress_street'] = $aPostParams['companyAddress_street'];
		if($aPostParams['user_type']=='business'){
			unset($params['business_seq']);
			$data = filter_keys($params, $this->db->list_fields('fm_member_business'));
			//print_r($data);
			if($business_seq){
				$this->db->where('business_seq', $business_seq);
				$result = $this->db->update('fm_member_business', $data);
			}else{
				$data['member_seq'] = $seq;
				$result = $this->db->insert('fm_member_business', $data);
			}
		}else{
			if($business_seq){
				$sql = "delete from fm_member_business where member_seq = '{$seq}'";
				$this->db->query($sql);
			}
		}

		###
		$app = config_load('member');
		//수동승인설정시 승인변경한 경우에만 체크
		if( (($_POST['user_type']!='business' && $app['autoApproval']=='N') || ($_POST['user_type']=='business' && $app['autoApproval_biz']=='N')) && $params['status']!=$member['status'] && $params['status'] == 'done' ) {

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
				$joinsc['whereis'] = ' and type = \'join\' and gb = \'plus\' and member_seq = \''.$seq.'\' ';
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
					$this->membermodel->emoney_insert($emoney, $seq);
				}
			}

			if( $app['pointJoin'] ) {
				$joinsc['whereis'] = ' and type = \'join\' and gb = \'plus\' and member_seq = \''.$seq.'\' ';
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
					$this->membermodel->point_insert($iparam, $seq);
				}
			}

			//추천시
			if($params['recommend']){
				$chk = get_data("fm_member",array("userid"=>$params['recommend'],"status"=>"done"));
				if($chk[0]['member_seq']) {

					//추천받은자의 추천받은건수 증가 @2013-06-19
					$this->membermodel->member_recommend_cnt($chk[0]['member_seq']);

					//추천 받은 자 -> 제한함
					$todaymonth = date("Y-m");
					if($app['emoneyRecommend']>0) {
						$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\' and ( member_seq_to = \''.$seq.'\' or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
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
								$emoney['member_seq_to']	= $seq;//2015-02-16
								$this->membermodel->emoney_insert($emoney, $chk[0]['member_seq']);
							}
						}
					}
					if($app['pointRecommend']>0) {
						$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\' and ( member_seq_to = \''.$seq.'\' or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
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
								$point['member_seq_to']		= $seq;//2015-02-16
								$this->membermodel->point_insert($point, $chk[0]['member_seq']);
							}
						}
					}

					if($app['emoneyJoiner']>0){
						$recommendfromsc['whereis'] = ' and type = \'recommend_from\' and gb = \'plus\' and member_seq = \''.$seq.'\' and ( member_seq_to = \''.$chk[0]['member_seq'].'\'  or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) )  ';
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
							$this->membermodel->emoney_insert($emoney, $seq);
						}
					}

					if($app['pointJoiner']>0){
						$recommendfromsc['whereis'] = ' and type = \'recommend_from\' and gb = \'plus\' and member_seq = \''.$seq.'\' and ( member_seq_to = \''.$chk[0]['member_seq'].'\'  or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
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
							$this->membermodel->point_insert($point, $seq);
						}
					}

				}
			}

			//초대시
			if($params['fb_invite']){
				$chk = get_data("fm_member",array("member_seq"=>$params['fb_invite']));
				if($chk[0]['member_seq']) {

					if($app['emoneyInvited']>0){
						$invitefromsc['whereis']	= ' and type = \'invite_from\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and ( member_seq_to = \''.$seq.'\'  or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
						$invitefromsc['select']		= ' emoney_seq ';
						$eminvitefromck = $this->emoneymodel->get_data_numrow($invitefromsc);//초대받고가입한회원 마일리지 지급여부
						if( !$eminvitefromck ) {//초대 한 자  -> 제한함
							$todaymonth = date("Y-m");
							$invitedtosc['whereis'] = ' and type = \'invite_from\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
							$invitedtosc['select']	 = ' count(*) as totalcnt, sum(emoney) as totalemoney ';
							$eminvitedtock = $this->emoneymodel->get_data($invitedtosc);//추천한 회원 마일리지 지급여부
							$maxinvited = ($app['emoneyLimit_invited']*$app['emoneyInvited']);

							if( $eminvitedtock['totalcnt'] <= $app['emoneyLimit_invited'] && $eminvitedtock['totalemoney'] <= $maxinvited ) {
								unset($emoney);
								$emoney['type']					= 'invite_from';
								$emoney['emoney']				= $app['emoneyInvited'];
								$emoney['gb']					= 'plus';
								$emoney['memo']					= '초대 마일리지';
								$emoney['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp275");   // 초대 마일리지
								$emoney['limit_date']			= get_emoney_limitdate('invite_from');
								$emoney['member_seq_to']		= $seq;//2015-02-16
								$this->membermodel->emoney_insert($emoney, $chk[0]['member_seq']);
 							}
						}
					}
					if($app['pointInvited']>0){
						$invitefromsc['whereis']	= ' and type = \'invite_from\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\' and ( member_seq_to = \''.$seq.'\'  or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
						$invitefromsc['select']		= ' point_seq ';
						$eminvitefromck = $this->pointmodel->get_data_numrow($invitefromsc);//초대받고가입한회원 마일리지 지급여부
						if( !$eminvitefromck ) {//초대 한 자  -> 제한함
							$todaymonth = date("Y-m");
							$invitedtosc['whereis'] = ' and type = \'invite_from\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
							$invitedtosc['select']	 = ' count(*) as totalcnt, sum(point) as totalpoint ';
							$pminvitedtock = $this->pointmodel->get_data($invitedtosc);//추천한 회원 마일리지 지급여부
							$maxinvited = ($app['pointLimit_invited']*$app['pointInvited']);

							if( $pminvitedtock['totalcnt'] <= $app['pointLimit_invited'] && $pminvitedtock['totalpoint'] <= $maxinvited ) {
								unset($point);
								$point['type']				= 'invite_from';
								$point['point']				= $app['pointInvited'];
								$point['gb']				= 'plus';
								$point['memo']				= '초대 포인트';
								$point['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp276");   // 초대 포인트
								$point['limit_date']		= get_point_limitdate('invite_from');
								$point['member_seq_to']		= $seq;//2015-02-16
								$this->membermodel->point_insert($point, $chk[0]['member_seq']);
							}
						}
					}

					if($app['emoneyInvitees']>0) {
						$invitetosc['whereis'] = ' and type = \'invite_to\' and gb = \'plus\' and member_seq = \''.$seq.'\'  and ( member_seq_to = \''.$chk[0]['member_seq'].'\'  or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
						$invitetosc['select']	 = ' emoney_seq ';
						$eminvitetock = $this->emoneymodel->get_data_numrow($invitetosc);//초대한 회원 마일리지 지급여부
						if( !$eminvitetock){//초대 받은 자(가입자)
							unset($emoney);
							$emoney['type']					= 'invite_to';
							$emoney['emoney']				= $app['emoneyInvitees'];//추천받은자
							$emoney['gb']					= 'plus';
							$emoney['memo']					= '초대 회원 마일리지';
							$emoney['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp277");   // 초대 회원 마일리지
							$emoney['limit_date']			= get_emoney_limitdate('invite_to');
							$emoney['member_seq_to']		= $chk[0]['member_seq'];//2015-02-16
							$this->membermodel->emoney_insert($emoney, $seq);
						}
					}

					if($app['pointInvitees']>0) {
						$invitetosc['whereis'] = ' and type = \'invite_to\' and gb = \'plus\' and member_seq = \''.$seq.'\'  and ( member_seq_to = \''.$chk[0]['member_seq'].'\'  or (member_seq_to is null and regist_date between \''.substr($member['regist_date'],0,16).':00\' and \''.substr($member['regist_date'],0,16).':59\' ) ) ';
						$invitetosc['select']	 = ' point_seq ';
						$pminvitetock = $this->pointmodel->get_data_numrow($invitetosc);//초대한 회원 포인트 지급여부
						if( !$pminvitetock){//초대 받은 자(가입자)
							unset($point);
							$point['type']				= 'invite_to';
							$point['point']				= $app['pointInvitees'];
							$point['gb']				= 'plus';
							$point['memo']				= '초대 회원 포인트';
							$point['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp278");   // 초대 회원 포인트
							$point['limit_date']		= get_point_limitdate('invite_to');
							$point['member_seq_to']		= $chk[0]['member_seq'];//2015-02-16
							$this->membermodel->point_insert($point, $seq);
						}
					}
				}
			}
		}


		//수정된회원의 추천건수 수정
		if($seq && $params['recommend']!=$member['recommend']) {
			$this->membermodel->member_recommend_cnt($seq);
			if($params['recommend']){
				$chk = get_data("fm_member",array("userid"=>$params['recommend'],"status"=>"done"));
				if($chk && $chk[0]['member_seq']) {
					$this->membermodel->member_recommend_cnt($chk[0]['member_seq']);
				}
			}
			if($member['recommend']){
				$chkold = get_data("fm_member",array("userid"=>$member['recommend'],"status"=>"done"));
				if($chkold && $chkold[0]['member_seq']) {
					$this->membermodel->member_recommend_cnt($chkold[0]['member_seq']);
				}
			}
		}
		//수정된회원의 초대건수 수정
		if($seq && $params['fb_invite']!=$member['fb_invite']){
			$this->membermodel->member_invite_cnt($seq);
			if($params['fb_invite']){
				$chk = get_data("fm_member",array("member_seq"=>$params['fb_invite'],"status"=>"done"));
				if($chk && $chk[0]['member_seq']) {
					$this->membermodel->member_invite_cnt($chk[0]['member_seq']);
				}
			}
			if($member['fb_invite']){
				$chkold = get_data("fm_member",array("member_seq"=>$member['fb_invite'],"status"=>"done"));
				if($chkold && $chkold[0]['member_seq']) {
					$this->membermodel->member_invite_cnt($chkold[0]['member_seq']);
				}
			}
		}

		//관리자 로그 남기기
		$this->load->library('managerlog');

		foreach($params as $k => $v){
			if(strpos($k, '_old') !== false){
				$kname = str_replace('_old', '', $k);
				if($v != $params[$kname]){
					$params_before = array();
					$params_after = array();

					$params_before[$kname] = $v;
					$params_after[$kname] = $params[$kname];

					$logInfo = array(
						'params_before'	=> $params_before,
						'params' => $params_after,
						'userinfo' => array('member_seq' => $member['member_seq'],
						'userid' => $member['userid'],
						'user_name' => $params['user_name'])
					);
					$this->managerlog->insertData($logInfo);
				}
			}

			if($k == 'passwd_chg' && $v == 'on'){
				$logInfo = array(
					'params' => array('password' => 'on'),
					'userinfo' => array('member_seq' => $member['member_seq'],
					'userid' => $member['userid'],
					'user_name' => $params['user_name'])
				);
				$this->managerlog->insertData($logInfo);
			}
		}

		###
		$callback = "parent.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function member_withdrawal(){
		### Validation
		$this->validation->set_rules('reason', '탈퇴사유','trim|required|xss_clean');
		$this->validation->set_rules('memo', '내용','trim|required|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		###
		$params	= $_POST;
		$params['regist_date']	= date('Y-m-d H:i:s');
		$params['regist_ip']	= $_SERVER['REMOTE_ADDR'];
		$this->load->library('memberlibrary');
		//회원탈퇴
		$withdrawalMsg = $this->memberlibrary->set_withdrawal($params);
		###
		$callback = "parent.location.href = '/admin/member/withdrawal';";
		openDialogAlert($withdrawalMsg['msg'],400,140,'parent',$callback);
	}


	public function sms(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('member_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$list			= $_POST['group_list'];
		$admins_num1	= $_POST['admins_num1'];
		$admins_num2	= $_POST['admins_num2'];
		$admins_num3	= $_POST['admins_num3'];
		$admins_num_cnt	= count($_POST['admins_num1']);
		if	(!is_array($list) || count($list) < 1){
			$callback = "";
			openDialogAlert("저장할 목록이 없습니다.",400,140,'parent',$callback);
			exit;
		}

		### 메시지 및 수신여부

		foreach ($list as $k => $sms){
			$this->validation->set_rules($sms . '_user', '내용','trim|required|xss_clean');

			if($this->validation->exec()===false){
				$err = $this->validation->error_array;

				$pos = strpos($err['key'], 'coupon_');
				if ($pos !== false) {
					$tab_no = 3; //쿠폰 tab
				} else {
					$tmp_str = str_replace("user","",$err['key']);
					$tab2_arr = array("released_","released2_","delivery_","delivery2_","cancel_","refund_");
					$tab_no = 1; //공통 tab
					if (in_array($tmp_str,$tab2_arr)) {
						$tab_no = 2; //실물 발송 상품 tab
					}
				}

				if(isset($_POST[$err['key']])){
					$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) {parent.tabmenu('{$tab_no}');parent.document.getElementsByName('{$err['key']}')[0].focus();}";
					openDialogAlert('SMS 메시지를 입력해 주세요.',400,140,'parent',$callback);
					exit;
				}
			}

			$config_save['sms'][$sms.'_user']		= $_POST[$sms.'_user'];
			$config_save['sms'][$sms.'_admin']		= $_POST[$sms.'_admin'];

			## 문자 검증 :: 2015-01-26 lwh
			$chkString	= $config_save['sms'][$sms.'_user'] . " " . $config_save['sms'][$sms.'_admin'];
			if(preg_match('/\%[^\s]+/',$chkString)){
				openDialogAlert("%이후에는 문자가 올수 없습니다.<br/>예) 30%DC -> 30% DC",400,140,'parent',$callback);
				exit;
			}

			$config_save['sms'][$sms.'_user_yn']	= if_empty($_POST, $sms.'_user_yn', 'N');
			if	(isset($_POST[$sms.'_provider_yn']))
				$config_save['sms'][$sms.'_provider_yn']	= if_empty($_POST, $sms.'_provider_yn', 'N');

			$idx	= 0;
			for($j = 0; $j < $admins_num_cnt; $j++){
				if(isset($admins_num1[$j]) && isset($admins_num2[$j]) && isset($admins_num3[$j])){
					$value	= $admins_num1[$j].'-'.$admins_num2[$j].'-'.$admins_num3[$j];
					if($value != '--' && !in_array($value, $saved_arr)){
						$codecd							= $sms.'_admins_yn_' . $idx;
						$config_save['sms'][$codecd]	= if_empty($_POST, $sms.'_admins_yn_'.$j, 'N');
						$saved_arr[]					= $value;
						$idx++;
					}
				}
			}
			unset($saved_arr);
		}

		## 관리자 발신번호
		//$config_save['sms_info']['send_num']	= implode("-",$_POST['send_num']);

		## 관리자 수신번호
		$cnt	= 0;
		for($i = 0; $i < $admins_num_cnt; $i++){
			if(isset($admins_num1[$i]) && isset($admins_num2[$i]) && isset($admins_num3[$i])){
				$value	= $admins_num1[$i].'-'.$admins_num2[$i].'-'.$admins_num3[$i];
				if($value != '--' && !in_array($value, $saved_arr)){
					$codecd								= 'admins_num_' . $cnt;
					$config_save['sms_info'][$codecd]	= $value;
					$saved_arr[]						= $value;
					$cnt++;
				}
			}
		}
		if($cnt > 0)
			$config_save['sms_info']['admis_cnt']	= $cnt;


		## sms_info 정보 초기화
		$sql	= "delete from fm_config where groupcd = 'sms_info'";
		$query	= $this->db->query($sql);

		## sms 정보 초기화
		$sql	= "delete from fm_config where groupcd = 'sms' and (codecd not like '%_write_admin%' and codecd not like '%_reply_user%') ";
		$query	= $this->db->query($sql);

		## sms 및 sms_info 정보 저장
		foreach($config_save as $groupcd => $data){
			foreach($data as $codecd => $value){
				config_save($groupcd, array($codecd	=> $value));
			}
		}

		config_save("sms", array("deposit_send_day"		=> $_POST['deposit_send_day']));
		config_save("sms", array("deposit_send_time"	=> $_POST['deposit_send_time']));
		config_save("sms", array("dormancy_send_time"	=> $_POST['dormancy_send_time']));

		###
		$callback = "parent.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function sms_goods_limit() {
		$post = $this->input->post();
		## SMS 상품명 길이 제한
		config_delete('sms_goods_limit');
		$goods_limit = array('ord_item_use'=>$post['ord_item_use']
				,'repay_item_use'=>$post['repay_item_use']
				,'go_item_use'=>$post['go_item_use']
				,'goods_item_use'=>$post['goods_item_use']
				,'ord_item_limit'=>$post['ord_item_limit']
				,'repay_item_limit'=>$post['repay_item_limit']
				,'go_item_limit'=>$post['go_item_limit']
				,'goods_item_limit'=>$post['goods_item_limit']
			);
		config_save_array('sms_goods_limit', $goods_limit);

		$callback = "parent.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function email_info(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('member_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}
		$this->config->load('emailGroup');
		$email_group = $this->config->item('email_group');

		$post = $this->input->post();
		foreach($email_group as $group) {
			foreach($group as $data) {

				$user_chk = $data['name']."_user_yn";
				$admin_chk = $data['name']."_admin_yn";

				$user_checked 	= isset($post[$user_chk]) ? $post[$user_chk] : "N";
				$admin_checked 	= isset($post[$admin_chk]) ? $post[$admin_chk] : "N";

				config_save('email', array($user_chk => $user_checked));
				config_save('email', array($admin_chk => $admin_checked));
			}
		}

		###
		$callback = "parent.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function email(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('member_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		$mail_form = $this->input->post('mail_form');
		$title = $this->input->post('title');

		if(isset($mail_form)){
			config_save('email',array($mail_form."_title"=>$title));
			config_save('email',array($mail_form."_skin"=>adjustEditorImages(htmlspecialchars_decode($_POST['contents']))));

			$admin_email = $mail_form."_admin_email";
			config_save('email',array($admin_email=>if_empty($this->input->post(), $admin_email, $basic['companyEmail'])));

		}

		$path = ROOTPATH."/data/email/".get_lang(true)."/".$mail_form.".html";
		setHtmlFile($path, adjustEditorImages(htmlspecialchars_decode($_POST['contents'])), 1);

		###
		$callback = "parent.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	## sms 발송시간 제한 설정
	public function sms_restriction(){

		$sms_board = array("board_reserve_time","board_time_s","board_time_e","board_toadmin","board_touser");
		$sms_restriction = config_load('sms_restriction');
		$params = array();
		foreach($_POST as $k=>$v){
			if($k != 'mode'){
				if(is_array($v)){
					foreach($v as $k2=>$v2){
						$params[$k."__".$k2] = $v2;
					}
				}else{
					$params[$k] = $v;
				}
			}
		}
		foreach($sms_restriction as $k=>$v){
			if($_POST['mode'] == "board"){
				if(in_array($k,$sms_board)){
					if($params[$k]){
						$sms_params[$k] = $params[$k];
					}else{
						$sms_params[$k] = 'off';
					}
				}else{
						$sms_params[$k] = $v;
				}
			}else{
				if(!in_array($k,$sms_board)){
					if($params[$k]){
						$sms_params[$k] = $params[$k];
					}else{
						$sms_params[$k] = 'off';
					}
				}else{
						$sms_params[$k] = $v;
				}

			}
		}

		if($params)		$sms_rest = $params;
		if($sms_params) $sms_rest = $sms_params;
		if($sms_params && $params){
			$sms_rest = array_merge($params,$sms_params);
		}
		config_save_array('sms_restriction',$sms_rest);

		if($_POST['mode'] == "board"){
			$callback = "parent.document.location.href='../board/main';";
		}else{
			$callback = "parent.document.location.href='../member/sms';";
		}
		openDialogAlert("저장되었습니다.",400,140,'parent',$callback);

	}

	## 고객 리마인드 서비스
	public function curation_info(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('member_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$id				= $this->input->post('mail_form');
		config_save('sms_personal',array($id."_title"=>$this->input->post('title_sms')));
		config_save('email_personal',array($id."_title"=>$this->input->post('title_email')));
		config_save('email_personal',array($id."_skin"=>adjustEditorImages($_POST['contents'])));

		$path = ROOTPATH."/data/email/".get_lang(true)."/".$id.".html";
		setHtmlFile($path, adjustEditorImages($_POST['contents']), 1);

		$callback = "parent.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}
	public function curation(){

		#### AUTH
		$auth = $this->authmodel->manager_limit_act('member_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		### 서비스 제한
		if	($_POST['mail'] == 'personal_timesale'){
			serviceLimit('H_FR','process');
		}

		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');

		config_save('personal_goods_limit',array("go_item_limit"=>$this->input->post('go_item_limit')));//알림 사용여부
		config_save('personal_goods_limit',array("go_item_use"=>$this->input->post('go_item_use')));//알림 사용여부
		$shorturl_use		= ($this->input->post('shorturl_use'))?$this->input->post('shorturl_use'):'N';
		config_save('snssocial',array('shorturl_use'=> $shorturl_use));

		$personal_use	= $this->input->post("personal_use");
		$user_yn_sms	= $this->input->post("user_yn_sms");
		$user_yn_email	= $this->input->post("user_yn_email");
		$personal_day	= $this->input->post("personal_day");
		$personal_time	= $this->input->post("personal_time");

		$curation_name = $this->input->post('curation_name');

		foreach($curation_name as $key => $name) {
			## sms reservation 정보 초기화 (title 제외 - title은 popup 에서 별도 관리)
			$sql	= "delete from fm_config where groupcd = 'sms_personal' and codecd like '".$name."%' AND codecd NOT LIKE '%_title'";
			$query	= $this->db->query($sql);
			## email reservation 정보 초기화 (title 제외 - title은 popup 에서 별도 관리)
			$sql	= "delete from fm_config where groupcd = 'email_personal' and codecd like '".$name."%' AND codecd NOT LIKE '%_title'";
			$query	= $this->db->query($sql);

			config_save('personal_use',array($name."_use"=>$personal_use[$key]));		//알림 사용여부
			config_save('sms_personal',array($name."_user_yn"=>$user_yn_sms[$key]));
			config_save('email_personal',array($name."_user_yn"=>$user_yn_email[$key]));

			if($personal_day[$key]){
				config_save('sms_personal',array($name."_day"=>$personal_day[$key]));			//예약시간
				config_save('email_personal',array($name."_day"=>$personal_day[$key]));		//예약시간
			}
			if($personal_time[$key]){
				config_save('sms_personal',array($name."_time"=>$personal_time[$key]));			//예약시간
				config_save('email_personal',array($name."_time"=>$personal_time[$key]));		//예약시간
			}
		}

		###
		$callback = "parent.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	## 고객 리마인드 서비스 세팅값 불러오기
	public function getPersonalReservation(){

		$this->load->helper('reservation');

		$id				= $this->input->get('id');
		$personal_use	= config_load('personal_use');
		$email_config	= config_load('email_personal');
		$sms_config		= config_load('sms_personal');

		$user_yn		= $selected = array();

		## 서비스 타입별 안내메세지 및 타이틀
		$title_sms		= $sms_config[$id."_title"];
		$title_email	= $email_config[$id."_title"];

		/* db에 저장된 SMS/Email Title 이 없을 때 */
		if(!$title_sms){
			$this->config->load('smsGroup');
			$personal_title_sms = $this->config->item('sms_personal_title');
			$title_sms 		= $personal_title_sms[$id];
		}
		if(!$title_email){
			$this->config->load('emailGroup');
			$personal_title_email = $this->config->item('email_personal_title');
			$title_email 	= $personal_title_email[$id];
		}

		$personal_sms = '
		<div class="use_sms hide">
			<label><input type="checkbox" name="user_yn_sms" id="user_yn_sms" value="Y" '.$user_yn['sms'].' '.$disabled.' onclick="smsRequire(this)" class="hide">수신 동의 고객에게 SMS 발송 </label> <span style="color:#ff0000;">'.$personal_sms_desc.'</span>
		</div>
		<input type="text" name="title_sms" id="title_sms" value="'.$title_sms.'" '.$disabled.' size="120">
		<input type="button" class="resp_btn" value="치환코드" name="coupon" title="사용가능한 치환코드" onclick="info_code(\''.$id.'\')"/>
		';

		$personal_email = '
		<div class="use_email hide">
			<label><input type="checkbox" name="user_yn_email" id="user_yn_email" value="Y" '.$user_yn['email'].' '.$disabled.'> 수신 동의 고객에게 EMAIL 발송 (선택) </label>
			<span style="color:#ff0000;">'.$personal_email_desc.'</span>
		</div>
		<input type="text" name="title_email" id="title_email" value="'.$title_email.'" '.$disabled.' size="120">
		<input type="button" value="치환코드" name="coupon" class="resp_btn" title="사용가능한 치환코드"  onclick="info_code(\''.$id.'\')"/>
		';

		# title
		foreach($menu_loop as $k=>$v){
			if($v['name'] == $id) $personal_title = $v['title']." ".$v['etc'];
		}

		###
		$path = ROOTPATH."/data/email/".get_lang(true)."/".$id.".html";
		$data = getHtmlFile($path);

		$result = array("personal_sms"		=>$personal_sms,
						"personal_email"	=>$personal_email,
						"contents"			=>$data,
						"html"				=>$html
					);

		// 카카오 메세지 정보 호출 :: 2018-03-15 lwh
		$this->load->model('kakaotalkmodel');
		$scParams['msg_code'] = $id;
		$msg_list = $this->kakaotalkmodel->get_msg_code($scParams);
		if($msg_list[$id]){
			$talk_txt = '발송 안 함';
			if ($msg_list[$id]['msg_yn'] == 'Y')	$talk_txt = '발송';
			$personal_talk = '
			<div class="use_talk">
				<span>' . $msg_list[$id]['msg_txt'] . ' : <b>' . $talk_txt . '</b></span>
				&nbsp;
				<span class="btn small orange"><input type="button" value="메시지 수정" name="talk" title="메시지수정" onclick="window.open(\'/admin/member/kakaotalk_msg?no=4\')"></span>
			</div>';
			$result['personal_talk'] = $personal_talk;
		}


		echo "[".json_encode($result)."]";
	}

	public function getmail(){
		$id		= $_GET['id'];

		$email = config_load('email');
		$title = isset($email[$id.'_title']) ? $email[$id.'_title'] : "";
		$contents = isset($email[$id.'_skin']) ? $email[$id.'_skin'] : " ";
		$admin_email	= $email[$id."_admin_email"];

		## 쿠폰발송상품(출고완료, 배송완료) => 고객에게 무조건 발송 2015-05-18 pjm
		if(in_array($id,array("coupon_released","coupon_delivery"))){
		    $user_chk	= "checked";
		    $fixed		= "onclick='this.checked=true'";
		}else{
		    $fixed = '';
		}

		$essential = '';
		if(in_array($id,array('findid','findpwd'))) {
		    $essential = "onclick='essential_func(this)'";
		}

		$marketing_agree_confirm = '';
		$disabled = '';
		if ($id == 'marketing_agree') {
		    $marketing_agree_confirm = "onclick='marketing_agree_confirm(this)'";
		}

		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');
		if(!$admin_email) $admin_email = $basic['companyEmail'];

		$customer_text	= "고객";
		if (preg_match('/^sorder/', $id)){
		    $customer_text	= "거래처";
		}

		if ($id == 'marketing_agree' && $user_chk == "checked") {
		    $html = "<label>".$customer_text."</label> ";
		} else {
		    $html = "<label><input type='checkbox' name='".$id."_user_yn' ".$fixed." value='Y' ".$user_chk." ".$essential." ".$marketing_agree_confirm." /> ".$customer_text."</label> ";
		}

		if(!$essential && !in_array($id,array('marketing_agree','marketing_agree_status'))){
		    $html .= "<label><input type='checkbox' name='".$id."_admin_yn' value='Y' ".$admin_chk."/> 관리자</label> ";
		    $html .= "<input type='text' name='".$id."_admin_email' value='{$admin_email}' />";
		    if($id == 'goods_qna'){
		        $html .= " <label><input type='checkbox' name='".$id."_provider_yn' value='Y' ".$provider_chk."/> 입점판매자(CS)</label>";
		    }
		}

		if ($id == 'marketing_agree') {
		    $title = "[{shopName}] 광고성 정보 수신 동의 확인 안내";
		} else if ($id == 'marketing_agree_status') {
		    $title = "[{shopName}] 수신동의변경 결과 안내";
		}

		###
		$path = ROOTPATH."/data/email/".get_lang(true)."/".$id.".html";
		$data = getHtmlFile($path);

		$result = array("title"=>$title,"contents"=>$data,"html"=>$html);

		echo "[".json_encode($result)."]";
	}

	public function logmail(){
		$seq	= $_GET['seq'];
		$query = $this->db->query("select * from fm_log_email where seq = '{$seq}'");
		$emailData = $query->result_array();
		$title = isset($emailData[0]['subject']) ? $emailData[0]['subject'] : "";
		$contents = isset($emailData[0]['contents']) ? $emailData[0]['contents'] : " ";
		$result = array("title"=>$title,"contents"=>$contents);
		echo "[".json_encode($result)."]";
	}

	public function getlogmail(){
		$seq	= $_GET['seq'];
		$query = $this->db->query("select * from fm_log_email where seq = '{$seq}'");
		$emailData = $query->result_array();
		$contents = isset($emailData[0]['contents']) ? $emailData[0]['contents'] : " ";
		$subject = isset($emailData[0]['subject']) ? $emailData[0]['subject'] : " ";
		$total = isset($emailData[0]['total']) ? $emailData[0]['total'] : " ";
		$regdate = isset($emailData[0]['regdate']) ? $emailData[0]['regdate'] : " ";

		// contents ssl 치환처리 :: 2018-09-12 pjw
		if(check_ssl_protocol()){
			$contents = str_replace('http://', 'https://', $contents);
		}else{
			$contents = str_replace('https://', 'http://', $contents);
		}

		$result = array("subject"=>$subject,"total"=>$total,"regdate"=>$regdate,"contents"=>$contents);
		//echo $result;
		echo "[".json_encode($result)."]";
	}

	## 고객리마인드서비스 메일발송내역 상세
	public function getlogcuration(){
		$seq			= $_GET['seq'];
		$query			= $this->db->query("select * from fm_log_curation_email where seq = '{$seq}'");
		$emailData		= $query->result_array();
		$contents		= isset($emailData[0]['contents']) ? $emailData[0]['contents'] : " ";
		$subject		= isset($emailData[0]['subject']) ? $emailData[0]['subject'] : " ";
		$to_email		= isset($emailData[0]['to_email']) ? $emailData[0]['to_email'] : " ";
		$regist_date	= isset($emailData[0]['regist_date']) ? $emailData[0]['regist_date'] : " ";
		$result			= array("subject"=>$subject,"to_email"=>$to_email,"regist_date"=>$regist_date,"contents"=>$contents);
		echo "[".json_encode($result)."]";
	}

	public function getSmsForm(){
		$get = $this->input->get();

		$sc['page']				= (isset($get['page'])) ?		intval($get['page']):'0';
		$sc['perpage']			= (isset($get['perpage'])) ?	intval($get['perpage']):'8';
		$sc['category']			= (isset($get['category'])) ?	urldecode($get['category']) : null;
		$sc['sms_search']		= (isset($get['sms_search'])) ? $get['sms_search'] : null;

		$data = $this->membermodel->sms_form_list($sc);

		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = get_rows('fm_sms_album');

		$paginlay = pagingtag($sc['searchcount'],$sc['perpage'], 'javascript:searchSMSPaging(\'',getLinkFilter('',array_keys(array())).'\');' );
		if(empty($paginlay)) $paginlay = '<p><a class="on red">1</a><p>';

		$result = '';
		if($data['result'])
		{
			$result .= "<ul class='section_dvs_lattice ea4 member_form_list'>";

			foreach($data['result'] as $datarow){
				$result .= "<li class='sms_area'>";
				$result .= "	<div class='contents'><div class='smsItem sms_item'>";
				$result .= "		<div class='sms-define-form'>";
				$result .= "			<div class='sdf-body-wrap '>";
				$result .= "				<div class='sdf-body'>";
				$result .= "					<textarea name='_user' readonly class='sms_contents' codecd='{$datarow['category']}' groupcd='sms_form'>".htmlspecialchars($datarow['msg'])."</textarea>";
				$result .= "					<div class='sdf-body-foot clearbox'>";
				$result .= "					</div>";
				$result .= "				</div>";
				$result .= "			</div>";
				$result .= "		</div>";
				$result .= "	</div>";
				$result .= "						<div class='right mr10'><b class='send_byte'>0</b>byte</div>";
				$result .= "<div class='right mt5 mr10' ><button type=\"button\" class='mod_form resp_btn v2 size_S' seq=\"".$datarow['seq']."\">수정</button><button type=\"button\" class='del_form resp_btn size_S ml3' seq=\"".$datarow['seq']."\">삭제</button></div></div>";
				$result .= "</li>";
			}

			$result .= "</ul>";
		}else{
			$result .= "<div class='center pd10'>추가한 SMS가 없습니다.</div>";
		}
		$result .= "<div class=\"paging_navigation cboth\" style=\"width:100%;text-align:center;\">".$paginlay."</div>";

		echo json_encode($result);
	}

	public function delete_smsform(){
		if(isset($_GET['seq'])){
			$result = $this->db->delete('fm_sms_album', array('seq' => $_GET['seq']));
			//$callback = "parent.document.getElementById('container').src='../member/sms_form';";
			openDialogAlert("삭제 되었습니다.",400,140,'parent',$callback);
		}
	}


	public function sms_process(){
		### Validation
		if(isset($_POST['sms_form_group'])){
			$this->validation->set_rules('sms_form_group', '그룹선택','trim|required|xss_clean');
		}else{
			$this->validation->set_rules('sms_form_name', '그룹명','trim|required|xss_clean');
		}
		$this->validation->set_rules('sms_form_text', '보관메세지','trim|required|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		###
		$params['category'] = isset($_POST['sms_form_group']) ? $_POST['sms_form_group'] : $_POST['sms_form_name'];
		$params['msg'] = $_POST['sms_form_text'];

		if($_POST['album_seq']){
			$this->db->where('seq', $_POST['album_seq']);
			$result = $this->db->update('fm_sms_album', $params);
		}else{
			$result = $this->db->insert('fm_sms_album', $params);
		}

		###
		$callback = "parent.loadSmsForm(''); parent.closeDialog('add_sms_popup');";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}



	public function send_email(){
		### Validation
		if($_POST['send_num'] < 1){
			$callback = "";
			openDialogAlert('받는사람이 없습니다.',400,140,'parent',$callback);
			exit;
		}
		$this->validation->set_rules('title', '제목','trim|required|max_length[100]|xss_clean');
		//$this->validation->set_rules('contents', '내용','trim|required|xss_clean');
		$this->validation->set_rules('send_email', '보내는사람','trim|required|max_length[50]|valid_email|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		###
		unset($mailArr);
		$mailArr = explode(",", $_POST["send_to"]);
		unset($mailArr[0]);
		if(isset($_POST['add_num_chk'])!='Y'){
			$key = get_shop_key();
			switch($_POST['member']){
				case "all":
					$query = $this->db->query("select AES_DECRYPT(UNHEX(email), '{$key}') as email from fm_member where status != 'withdrawal' and email<>'' and mailing = 'y'");
					$data = $query->result_array();
					if(count($data)>0){
						foreach($data as $k){
							array_push($mailArr,$k['email']);
						}
					}
					break;
				case "search":
					//echo urldecode($_POST["serialize"]);
					$tempArr = explode("&",urldecode($_POST["serialize"]));
					foreach($tempArr as $k){
						$tmp = explode("=",$k);
						if($tmp[1]){
							$sc[$tmp[0]] = $tmp[1];
						}
					}
					$sc['mailing'] = 'y';
					if($sc["keyword"] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
						$sc["keyword"] = "";
					}
					//$this->load->model('membermodel');
					$data = $this->membermodel->admin_search_list($sc);
					if(count($data['result'])>0){
						foreach($data['result'] as $k){
							array_push($mailArr,$k['email']);
						}
					}
					break;
				case "select":
					$tempArr = explode(",", $_POST["serialize"]);
					unset($tempArr[0]);
					foreach($tempArr as $k){
						$query = $this->db->query("select AES_DECRYPT(UNHEX(email), '{$key}') as email from fm_member where member_seq = '{$k}' and email<>'' and mailing = 'y'");
						$data = $query->result_array();
						if($data[0]['email']) array_push($mailArr,$data[0]['email']);
					}
					break;
				case "excel":
					break;
			}

		}
		//print_r($mailArr);
		//exit;

		if (count($mailArr) < 1) {
			$callback = "";
			openDialogAlert('받는사람 이메일은 필수입니다.',400,140,'parent',$callback);
			exit;
		}

		###
		$this->load->model('usedmodel');
		$email_chk = $this->usedmodel->hosting_check();

		###
		$total = count($mailArr);
		$toMonth = date("Y-m");
		$sql = "select sum(total) as count from fm_log_email where regdate like '{$toMonth}%'";
		$query = $this->db->query($sql);
		$emailData = $query->result_array();
		$usedMail	= $emailData[0]['count'] + $total;
		if(3000 < $usedMail  && !$email_chk){
			$callback = "";
			openDialogAlert('본 이메일 발송 기능은 월 3,000통 발송이 가능합니다.<br>더 많은 이메일 발송은 대량발송 서비스를 이용해 주십시오.',400,140,'parent',$callback);
			exit;
		}
		###
		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');
		//sendDirectMail($mailArr, $_POST['send_email'], $_POST['title'], $_POST['contents']);
		require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/Email_send.class.php";
		$mail = new Mail(isset($params));
		$body = adjustEditorImages($_POST['contents']);
		foreach($mailArr as $k){
			if(filter_var($k,FILTER_VALIDATE_EMAIL)!=false){
				$headers['From']    = $_POST['send_email'];
				$headers['Name']	= !$basic['companyName'] ? 'http://'.$_SERVER['HTTP_HOST'] : $basic['companyName'];
				$headers['Subject'] = $_POST['title'];
				$headers['To'] = $k;
				$resSend = $mail->send($headers, $body);
			}
		}

		### LOG
		$params['regdate']		= date('Y-m-d H:i:s');
		$params['gb']			= 'MANUAL';
		$params['total']		= $total;
		$params['from_email']	= $_POST['send_email'];
		$params['subject']		= $_POST['title'];
		$params['contents']		= $body;
		$data = filter_keys($params, $this->db->list_fields('fm_log_email'));
		$result = $this->db->insert('fm_log_email', $data);

		### MASTER
		config_save('master',array('mail_count'=>(3000 - $usedMail)));

		$callback = "parent.document.getElementById('container').src='../member/email_form';";
		//$callback = "parent.location.reload();";
		$msg = "메일이 발송 되었습니다.";
		openDialogAlert($msg,400,140,'parent',$callback);
		exit;
	}


	public function send_sms(){

		### Validation
		if($_POST['send_num'] < 1){
			$callback = "parent.container.sms_loading_stop();";
			openDialogAlert('받는사람이 없습니다.',400,140,'parent',$callback);
			exit;
		}
		$this->validation->set_rules('send_message', '내용','trim|required|xss_clean');
		$this->validation->set_rules('send_sms', '보내는사람','trim|required|max_length[50]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus(); parent.container.sms_loading_stop();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		###
		$phoneNo = explode(",", $_POST["send_to"]);
		unset($phoneNo[0]);
		$key = get_shop_key();

		if(isset($_POST['add_num_chk'])!='Y'){
			switch($_POST['member']){
				case "all":
					$sql = "SELECT
									AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone ,
									B.bcellphone, B.business_seq
									 FROM fm_member A
									LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq
									WHERE status != 'withdrawal' and sms = 'y' ";
					$query = $this->db->query($sql);
					$data = $query->result_array();
					if(count($data)>0){
						foreach($data as $k){
							if($k['business_seq'] ) { //기업회원
								if($k['bcellphone']) array_push($phoneNo, $k['bcellphone']);
							}else{
								if($k['cellphone']) array_push($phoneNo, $k['cellphone']);
							}
						}
					}
					break;
				case "search":
					//echo urldecode($_POST["serialize"]);
					$tempArr = explode("&",urldecode($_POST["serialize"]));
					foreach($tempArr as $k){
						$tmp = explode("=",$k);
						if($tmp[1]){
							$sc[$tmp[0]] = $tmp[1];
						}
					}
					//$sc['sms'] = 'y';
					if($sc["keyword"] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
						$sc["keyword"] = "";
					}

					//$this->load->model('membermodel');
					$data = $this->membermodel->admin_search_list($sc);
					if(count($data['result'])>0){
						foreach($data['result'] as $k){
							//array_push($phoneNo,$k['cellphone']);
							if($k['business_seq'] ) { //기업회원
								if($k['bcellphone']) array_push($phoneNo, $k['bcellphone']);
							}else{
								if($k['cellphone']) array_push($phoneNo, $k['cellphone']);
							}

						}
					}
					break;
				case "select":
					$tempArr = explode(",", $_POST["serialize"]);
					unset($tempArr[0]);
					foreach($tempArr as $k){
					$sql = "SELECT
									AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone ,
									B.bcellphone, B.business_seq
									 FROM fm_member A
									LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq
									WHERE A.member_seq = '{$k}'  ";

						$query = $this->db->query($sql);
						$data = $query->result_array();

						if($data[0]['business_seq'] ) { //기업회원
							if($data[0]['bcellphone']) array_push($phoneNo, $data[0]['bcellphone']);
						}else{
							if($data[0]['cellphone']) array_push($phoneNo, $data[0]['cellphone']);
						}
					}
					break;
				case "excel":
					break;
			}
		}
		###
		$params['msg'] = trim($_POST["send_message"]);
		$euckr_str = mb_convert_encoding($params['msg'],'EUC-KR','UTF-8');
		$len = strlen($euckr_str);

		$from		= $_POST["send_sms"];

		if($len > 90){
			$sms_type = "LMS ";
		}else{
			$sms_type = "SMS ";
		}

		$commonSmsData['member']['phone'] = $phoneNo;
		$commonSmsData['member']['params'] = $params;

		$result = commonSendSMS($commonSmsData);

		$callback = "parent.container.sms_loading_stop();";
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
				//$callback = "parent.location.reload();";
				$result_msg = $sms_type."발송에 성공하였습니다.";
			}
		}

		openDialogAlert($result_msg,400,140,'parent',$callback);
		exit;
	}


	public function set_emoney(){
		### Validation
		if($_POST['send_member'] < 1){
			$callback = "";
			openDialogAlert('선택된 회원이 없습니다.',400,140,'parent',$callback);
			exit;
		}
		### Validation
		$this->validation->set_rules('emoney', '마일리지','trim|required|xss_clean');
		$this->validation->set_rules('memo', '사유','trim|required|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		unset($memberArr);
		//$this->load->model('membermodel');
		switch($_POST['member']){
			case "all":
				$key = get_shop_key();
				$query = $this->db->query("select member_seq from fm_member where status != 'withdrawal'");
				foreach($query->result_array() as $v){
					$memberArr[] = $v['member_seq'];
				}
				break;
			case "search":
				//echo urldecode($_POST["serialize"]);
				$tempArr = explode("&",urldecode($_POST["serialize"]));
				foreach($tempArr as $k){
					$tmp = explode("=",$k);
					if($tmp[1]){
						$sc[$tmp[0]] = $tmp[1];
					}
				}
				$data = $this->membermodel->admin_search_list($sc);
				$memberArr = $data['result'];
				break;
			case "select":
				$memberArr = explode(",", $_POST["serialize"]);
				unset($memberArr[0]);
				break;
			case "excel":
				break;
		}

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
		foreach($memberArr as $k){
			$this->membermodel->emoney_insert($_POST, $k);
		}

		$callback = "parent.location.reload(); parent.document.getElementById('container').src='../member/emoney_form';";
		openDialogAlert("마일리지가 적용 되었습니다.",400,140,'parent',$callback);
		exit;
	}

	public function set_point(){
		### Validation
		if($_POST['send_member'] < 1){
			$callback = "";
			openDialogAlert('선택된 회원이 없습니다.',400,140,'parent',$callback);
			exit;
		}
		### Validation
		$this->validation->set_rules('point', '포인트','trim|required|xss_clean');
		$this->validation->set_rules('memo', '사유','trim|required|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		unset($memberArr);
		//$this->load->model('membermodel');
		switch($_POST['member']){
			case "all":
				$key = get_shop_key();
				$query = $this->db->query("select member_seq from fm_member where status != 'withdrawal'");
				foreach($query->result_array() as $v){
					$memberArr[] = $v['member_seq'];
				}
				break;
			case "search":
				//echo urldecode($_POST["serialize"]);
				$tempArr = explode("&",urldecode($_POST["serialize"]));
				foreach($tempArr as $k){
					$tmp = explode("=",$k);
					if($tmp[1]){
						$sc[$tmp[0]] = $tmp[1];
					}
				}
				$data = $this->membermodel->admin_search_list($sc);
				$memberArr = $data['result'];
				break;
			case "select":
				$memberArr = explode(",", $_POST["serialize"]);
				unset($memberArr[0]);
				break;
			case "excel":
				break;
		}

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
		foreach($memberArr as $k){
			$this->membermodel->point_insert($_POST, $k);
		}

		$callback = "parent.location.reload(); parent.document.getElementById('container').src='../member/point_form';";
		openDialogAlert("포인트가 적용 되었습니다.",400,140,'parent',$callback);
		exit;
	}

	/**
	 * 마일리지 지급
	 */
	public function emoney_detail(){

		// 관리자 마일리지 지급 권한 체크
		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('member_promotion');
		if(!$auth){
			$callback = "parent.location.reload();";
			openDialogAlert("권한이 없습니다.", 400, 140,'parent', $callback);
			exit;
		}

		// 마일리지 지급 유효성 체크
		$aEmoneyData = $this->input->post();
		$aMemberData = $this->membermodel->get_member_data($aEmoneyData['member_seq']);

		if($aMemberData['status'] == 'dormancy' || $aMemberData['status'] == 'withdrawal'){
			openDialogAlert('휴면/탈퇴 회원에게 마일리지 지급/차감 하실 수 없습니다.', 400, 140,'parent');
			exit;
		}

		if($aEmoneyData['emoney']){
			$aEmoneyData['emoney'] = get_currency_price($aEmoneyData['emoney'], 1);
		}
		$this->validation->set_rules('emoney', '마일리지','trim|required|xss_clean');

		// 지급 사유 유효성 체크
		if($aEmoneyData['memo_type']=='direct'){
			$this->validation->set_rules('memo_direct', '사유','trim|required|xss_clean');
			$aEmoneyData['memo'] = $aEmoneyData['memo_direct'];
		}else{
			$this->validation->set_rules('memo_type', '사유','trim|required|xss_clean');
			$aEmoneyData['memo'] = $aEmoneyData['memo_type'];
		}

		// SMS 전송 데이터 유효성 체크
		if($aEmoneyData['send_sms']=='Y'){
			$this->validation->set_rules('cellphone', '핸드폰번호','trim|required|xss_clean');
			$this->validation->set_rules('msg', '메세지','trim|required|xss_clean');
		}

		// 유효 기간 제한 유효성 체크
		if($aEmoneyData['reserve_select']=='direct'){
			$this->validation->set_rules('reserve_direct', '제한개월','trim|required|max_length[4]|min_length[1]|numeric|xss_clean');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'], 400, 140,'parent', $callback);
			exit;
		}

		// 마일리지 지급 수단 설정
		$aEmoneyData['type'] = 'direct';
		// 마일리지 지급 관리자
		$aEmoneyData['manager_seq'] = $this->managerInfo['manager_seq'];
		// 유효기간 설정 시 마일리지 사용 제한 날짜 설정
		if($aEmoneyData['reserve_select']){
			if($aEmoneyData['reserve_select']=='year'){
				$aEmoneyData['limit_date'] = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$aEmoneyData['reserve_year']));
			}else{
				$aEmoneyData['limit_date'] = date("Y-m-d", mktime(0,0,0,date("m")+$aEmoneyData['reserve_direct'], date("d"), date("Y")));
			}
		}

		// 마일리지 차감 유효성 체크
		if($aEmoneyData['gb'] == 'minus') {
			$emoney = $this->membermodel->get_emoney($aEmoneyData['member_seq']);
			if ( $aEmoneyData['emoney'] > $emoney) {
				openDialogAlert('보유한 마일리지 보다 큰 금액을 차감할 수 없습니다.', 400, 140,'parent');
				exit;
			}
		}

		// 마일리지 데이터 업데이트
		$this->membermodel->emoney_insert($aEmoneyData, $aEmoneyData['member_seq']);

		// SMS 발송 결과 변수 초기화
		$sms_result = "";
		// SMS 발송 데이터 가공
		if($aEmoneyData['send_sms']=='Y'){

			$str = trim($aEmoneyData["msg"]);
			$euckr_str = mb_convert_encoding($str,'EUC-KR','UTF-8');
			$len = strlen($euckr_str);

			if($len > 90){
				$sms_type = "LMS ";
			}else{
				$sms_type = "SMS ";
			}

			$params['msg'] = $str;
			$commonSmsData['member']['phone'] = $aEmoneyData['cellphone'];
			$commonSmsData['member']['params'] = $params;

			// 문자 전송
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
					$result_msg = $sms_type."발송에 성공하였습니다.";
				}
			}
			$sms_result = "<br> SMS : ".$result_msg;
		}

		$callback = "parent.emoney_pop();parent.location.reload();";
		openDialogAlert("마일리지가 적용 되었습니다.".$sms_result,400,140,'parent',$callback);
		exit;
	}

	/**
	 * 포인트 지급
	 */
	public function point_detail(){

		// 관리자 포인트 지급 권한 체크
		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('member_promotion');
		if(!$auth){
			$callback = "parent.location.reload();";
			openDialogAlert("권한이 없습니다.",400,140,'parent',$callback);
			exit;
		}

		// 포인트 지급 유효성 체크
		$aPointData = $this->input->post();
		$aMemberData = $this->membermodel->get_member_data($aPointData['member_seq']);

		if($aMemberData['status'] == 'dormancy' || $aMemberData['status'] == 'withdrawal'){
			openDialogAlert('휴면/탈퇴 회원에게 마일리지 지급/차감 하실 수 없습니다.', 400, 140,'parent');
			exit;
		}

		$this->validation->set_rules('point', '포인트','trim|required|xss_clean');

		// 지급 사유 유효성 체크
		if($aPointData['memo_type']=='direct'){
			$this->validation->set_rules('memo_direct', '사유','trim|required|xss_clean');
			$aPointData['memo'] = $aPointData['memo_direct'];
		}else{
			$this->validation->set_rules('memo_type', '사유','trim|required|xss_clean');
			$aPointData['memo'] = $aPointData['memo_type'];
		}

		// SMS 전송 데이터 유효성 체크
		if($aPointData['send_sms']=='Y'){
			$this->validation->set_rules('cellphone', '핸드폰번호','trim|required|xss_clean');
			$this->validation->set_rules('msg', '메세지','trim|required|xss_clean');
		}

		// 유효 기간 제한 유효성 체크
		if($aPointData['reserve_select']=='direct'){
			$this->validation->set_rules('reserve_direct', '제한개월','trim|required|max_length[4]|min_length[1]|numeric|xss_clean');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		// 포인트 지급 수단 설정
		$aPointData['type'] = 'direct';
		// 포인트 지급 관리자
		$aPointData['manager_seq'] = $this->managerInfo['manager_seq'];
		// 유효기간 설정 시 포인트 사용 제한 날짜 설정
		if($aPointData['reserve_select']){
			if($aPointData['reserve_select']=='year'){
				$aPointData['limit_date'] = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$aPointData['reserve_year']));
			}else{
				$aPointData['limit_date'] = date("Y-m-d", mktime(0,0,0,date("m")+$aPointData['reserve_direct'], date("d"), date("Y")));
			}
		}

		// 포인트 차감 유효성 체크
		if($aPointData['gb'] == 'minus') {
			$point = $this->membermodel->get_emoney($aPointData['member_seq'], 'point');
			if ( $aPointData['point'] > $point) {
				openDialogAlert('보유한 포인트 보다 큰 금액을 차감할 수 없습니다.',400,140,'parent');
				exit;
			}
		}

		// 포인트 데이터 업데이트
		$this->membermodel->point_insert($aPointData, $aPointData['member_seq']);

		// SMS 발송 결과 변수 초기화
		$sms_result = "";
		// SMS 발송 데이터 가공
		if($aPointData['send_sms']=='Y'){

			$str = trim($aPointData["msg"]);
			$euckr_str = mb_convert_encoding($str,'EUC-KR','UTF-8');
			$len = strlen($euckr_str);

			if($len > 90){
				$sms_type = "LMS ";
			}else{
				$sms_type = "SMS ";
			}

			$params['msg'] = $str;

			$commonSmsData['member']['phone'] = $aPointData['cellphone'];
			$commonSmsData['member']['params'] = $params;

			// 문자 전송
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
					$result_msg = $sms_type."발송에 성공하였습니다.";
				}
			}
			$sms_result = "<br> SMS : ".$result_msg;
		}

		$callback = "parent.location.reload();";
		openDialogAlert("포인트가 적용 되었습니다.".$sms_result,400,140,'parent',$callback);
		exit;
	}

	public function sms_pop(){
		$aParams = $this->input->post();

		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('member_send');
		$private_masking = $this->authmodel->manager_limit_act('private_masking');
		if(!$auth){
			$callback = "parent.location.reload();";
			openDialogAlert("권한이 없습니다.",400,140,'parent',$callback);
			exit;
		}

		### Validation
		if(!$aParams['order_seq'] || ($aParams['order_seq'] && !$private_masking)) {
			$this->validation->set_rules('cellphone[]', '핸드폰번호','trim|required|valid_cellphone|xss_clean');
		}
		$this->validation->set_rules('msg', '메세지','trim|required|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		// 주문상세>주문자정보>SMS보내기
		if($aParams['order_seq'] && $private_masking){
			$type = $aParams['type'] ?? 'order_cellphone';
			$this->load->model('ordermodel');
			$data = $this->ordermodel->get_order_info($aParams['order_seq']);
			$aParams['cellphone'] = str_replace('-','',$data[$type]);
		}

		if($aParams['send_sms']=='Y'){
			if($aParams['board_id'] || $aParams['type']== "provider_person") {
				// 빈 값이 있는 경우, 제외하고 보내기
				foreach( explode(",",$aParams['cellphone']) as $cellphone){
					if($cellphone) $phone[] = $cellphone;
				}
			}else{
				$phone		= $aParams['cellphone'];
			}
			$params['msg'] = trim($aParams["msg"]);
			
			// 기본 member로 넘어가나 입점사 미출고 SMS 관련 provider_person인 경우가 추가됨
			$aParams['type'] = ( $aParams['type']== "provider_person") ? "provider_person" : "member";

			$commonSmsData[$aParams['type']]['phone'] = $phone;
			$commonSmsData[$aParams['type']]['params'] = $params;

			$result = commonSendSMS($commonSmsData);

			$chk_popup_close = true;
			if($result['msg'] == "fail"){
				$result_msg = "%이후에는 문자가 올수 없습니다.<br>예) 30%DC -> 30% DC";
			}else{
				$result_code = $result['code'];
				if($result_code != "0000"){
					if($result_code == "E001"){
						$result_msg = "SMS 인증 정보가 잘못되었습니다.";
					}else if($result_code == "800"){
						$result_msg = "SMS 발신번호가 등록되지 않았습니다.";
					}else{
						$result_msg = $sms_type."발송에 실패했습니다.";
					}
				}else{
					$result_msg = $sms_type."발송에 성공하였습니다.";
					$chk_popup_close = false;
				}
			}
		}

		$callback = ($chk_popup_close) ? "" :"parent.closeDialog('sendPopup');";
		openDialogAlert($result_msg,400,140,'parent',$callback);
		exit;
	}

	public function email_pop(){
		$aParams = $this->input->post();

		$this->load->model('authmodel');
		$auth = $this->authmodel->manager_limit_act('member_send');
		if(!$auth){
			$callback = "parent.location.reload();";
			openDialogAlert("권한이 없습니다.",400,140,'parent',$callback);
			exit;
		}

		if($aParams['order_seq']){
			$type = $aParams['type'] ?? 'order_email';

			$this->load->model('ordermodel');
			$data = $this->ordermodel->get_order_info($aParams['order_seq']);

			$aParams['email'] = $data[$type];
		} else {
			$this->validation->set_rules('email', '받는사람','trim|required|max_length[50]|valid_email|xss_clean');
		}

		$this->validation->set_rules('title', '제목','trim|required|max_length[100]|xss_clean');
		/* xss_clean 삭제 : css 주석처리 적용문제, 인라인 css 적용이 잘리는 현상 발생으로 삭제 */
		$this->validation->set_rules('contents', '내용','trim|required');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		###
		$this->load->model('usedmodel');
		$email_chk = $this->usedmodel->hosting_check();

		###
		$total = 1;
		$emailData 	= $this->membermodel->get_send_history_email_month();
		$usedMail	= $emailData['count'] + $total;

		if(3000 < $usedMail  && !$email_chk){
			$callback = "";
			openDialogAlert('본 이메일 발송 기능은 월 3,000통 발송이 가능합니다.<br>더 많은 이메일 발송은 대량발송 서비스를 이용해 주십시오.',400,140,'parent',$callback);
			exit;
		}

		## 회원 검증
		if($aParams['member_seq']){
			$member_email = $this->membermodel->get_member_email($aParams['member_seq']);
			if($member_email['email'] != $aParams['email']){
				$callback = "";
				openDialogAlert('이메일 주소 정보가 일치하지 않습니다.',400,140,'parent',$callback);
				exit;
			}
		}

		###
		$basic = ($this->config_basic)?$this->config_basic:config_load('basic');
		$callback = "parent.location.reload();";
		//sendDirectMail($mailArr, $aParams['send_email'], $aParams['title'], $aParams['contents']);
		require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/Email_send.class.php";
		$mail = new Mail(isset($params));
		$body = adjustEditorImages($this->input->post('contents', false));

		if(filter_var($aParams['email'],FILTER_VALIDATE_EMAIL) !== false){
			$headers['From']		= $basic['companyEmail'] ?? 'gabia@gabia.com';
			$headers['Name']		= $basic['shopName'] ?? 'http://'.$_SERVER['HTTP_HOST'];
			$headers['Subject']		= $aParams['title'];
			$headers['To']			= $aParams['email'];
			$resSend				= $mail->send($headers, $body);
		}

		### LOG
		$params['regdate']		= date('Y-m-d H:i:s');
		$params['gb']			= 'MANUAL';
		$params['total']		= $total;
		$params['from_email']	= $basic['companyEmail'];
		$params['subject']		= $aParams['title'];
		$params['contents']		= $body;
		$data = filter_keys($params, $this->db->list_fields('fm_log_email'));
		$result = $this->db->insert('fm_log_email', $data);

		### MASTER
		config_save('master',array('mail_count'=>(3000 - $usedMail)));

		$callback = "parent.closeDialog('sendPopup');";
		$msg = "메일이 발송 되었습니다.";
		openDialogAlert($msg,400,140,'parent',$callback);
		exit;
	}


	public function sms_excel(){
		echo "EXCEL";
	}

	public function sms_auth(){
		$callback = "parent.location.reload();";

		if($this->input->post('mode') == 'admin') {
			$auth = $this->authmodel->manager_limit_act('member_send');
			if(!$auth){
				openDialogAlert("권한이 없습니다.",400,140,'parent',$callback);
				exit;
			}

			$admins_num1	= $_POST['admins_num1'];
			$admins_num2	= $_POST['admins_num2'];
			$admins_num3	= $_POST['admins_num3'];
			$admins_num_cnt	= count($_POST['admins_num1']);

			## 관리자 수신번호
			$cnt	= 0;
			for($i = 0; $i < $admins_num_cnt; $i++){
				if(isset($admins_num1[$i]) && isset($admins_num2[$i]) && isset($admins_num3[$i])){
					$value	= $admins_num1[$i].'-'.$admins_num2[$i].'-'.$admins_num3[$i];
					if($value != '--' && !in_array($value, $saved_arr)){
						$codecd								= 'admins_num_' . $cnt;
						$config_save['sms_info'][$codecd]	= $value;
						$saved_arr[]						= $value;
						$cnt++;
					}
				}
			}
			if($cnt > 0)
				$config_save['sms_info']['admis_cnt']	= $cnt;

			## sms_info 정보 초기화
			$sql	= "delete from fm_config where groupcd = 'sms_info'";
			$query	= $this->db->query($sql);

			## sms 및 sms_info 정보 저장
			foreach($config_save as $groupcd => $data){
				foreach($data as $codecd => $value){
					config_save($groupcd, array($codecd	=> $value));
				}
			}
			$callback = "parent.location.reload();";
			openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
		} else {
			if($this->managerInfo['manager_yn'] != 'Y'){
				openDialogAlert("권한이 없습니다.",400,140,'parent',$callback);
				exit;
			}

			### Validation
			$this->validation->set_rules('sms_auth', 'SMS 인증번호','trim|required|xss_clean');
			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,140,'parent',$callback);
				exit;
			}

			$this->load->model("smsmodel");
			$authData = $this->smsmodel->checkSafeKey($_POST['sms_auth']);

			if	($authData['code'] == "200") {
				config_save('master',array('sms_auth'=>$_POST['sms_auth']));

				/* 주요행위 기록 */
				$this->load->model('managermodel');
				$this->managermodel->insert_action_history($this->managerInfo['manager_seq'],'secret_key_setting');

				###
				openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
			} else {
				###
				openDialogAlert("보안키가 맞지 않습니다",400,140,'parent',$callback);
			}
		}
	}


	public function getAuthPopup(){
		$this->load->helper('admin');
		$result = getGabiaPannel('smsAuthInfoNew');
		echo $result;
	}

	public function getAuthSendPopup(){
		$this->load->helper('admin');
		$result = getGabiaPannel('smsAuthSendInfo');
		echo $result;
	}

	public function getSendPopup(){
		$this->load->helper('admin');
		$result = getGabiaPannel('smsSendInfo');
		echo $result;
	}

	public function getExcelPopup(){
		$result = "";
		$result .= "<form name='popForm' method='post' action='../member_process/sms_excel' target='actionFrame'>";
		$result .= "<table width='100%' cellspacing='0'>";
		$result .= "<tr><td>";
		$result .= "▶ 엑셀 데이터 발송 안내<br/>";
		$result .= "SMS 발송 대상을 엑셀 데이터로 등록할 수 있습니다.<br/>";
		$result .= "엑셀 양식을 다운로드 받아 형식에 맞게 데이터를 입력해 주세요. ";
		$result .= "</td></tr>";
		$result .= "<tr><td>";
		$result .= "▶ 엑셀 데이터 입력 방법<br/>";
		$result .= "1. 기본적으로 다운로드 받은 엑셀 약식을 유지한 후 데이터만 입력<br/>";
		$result .= "2. 반드시 A열에는 이름, B열에는 핸드폰번호를 입력<br/>";
		$result .= "3. 반드시 핸드폰번호는 '-'를 포함하여 입력<br/>";
		$result .= "예시) 010-123-4567<br/>";
		$result .= "4. 입력완료 후 엑셀파일 저장 형식을 *.xls로 저장 ";
		$result .= "</td></tr>";
		$result .= "<tr><td>";
		$result .= "▶ 엑셀 파일 업로드<br/>";
		$result .= "<input type='file' name=''>";
		$result .= "</td></tr>";
		$result .= "</table>";
		$result .= "<span class='btn small gray center'><button type='button' onclick='document.popForm.submit();'>확인</button></span>";
		$result .= "</form>";
		echo $result;
	}

	public function amail(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('member_act');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		### Validation
		$this->validation->set_rules('name', '이름','trim|required|max_length[100]|xss_clean');
		$this->validation->set_rules('email', '이메일','trim|required|max_length[64]|valid_email|xss_clean');
		$this->validation->set_rules('phoneArr[0]', '전화번호','trim|required|max_length[4]|numeric|xss_clean');
		$this->validation->set_rules('phoneArr[1]', '전화번호','trim|required|max_length[4]|numeric|xss_clean');
		$this->validation->set_rules('phoneArr[2]', '전화번호','trim|required|max_length[4]|numeric|xss_clean');
		$this->validation->set_rules('mobileArr[0]', '휴대폰','trim|required|max_length[4]|numeric|xss_clean');
		$this->validation->set_rules('mobileArr[1]', '휴대폰','trim|required|max_length[4]|numeric|xss_clean');
		$this->validation->set_rules('mobileArr[2]', '휴대폰','trim|required|max_length[4]|numeric|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		### SAVE
		$phone		= implode("-",$_POST['phoneArr']);
		$cellphone	= implode("-",$_POST['mobileArr']);
		config_save('email_mass',array('name'=>$_POST['name']));
		config_save('email_mass',array('email'=>$_POST['email']));
		config_save('email_mass',array('phone'=>$phone));
		config_save('email_mass',array('cellphone'=>$cellphone));

		$callback = "";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
		exit;
	}


	public function amail_send_set(){
		ini_set("memory_limit",-1);
		set_time_limit(0);

		###
		$cid = preg_replace("/-/","", $this->config_system['service']['cid']);

		###
		$params = "";
		$anp = "";
		$first_yn = "Y";
		$cnt = 0;
		$subCnt = 0;
		$groupName = "SIMPLE MAIL";
		unset($mailArr);
		if	($_GET['send_to'])	$mailArr = explode(",", $_GET["send_to"]);
		else					$mailArr = explode(",", $_GET["no"]);
		unset($mailArr[0]);
		$add_email = $this->input->get('add_email');
		if($add_email) $add_email_count = count($mailArr);
		$send_count = 0;

		###
		if($_GET['add_num_chk'] != 'Y'){
			$key = get_shop_key();
			$send_count = $this->input->get('send_count');
			switch($_GET['member']){
				case "all":
					// 기업회원 누 락되는 부분 수정 :: 2018-01-15 lkh
					$sql = "SELECT
								AES_DECRYPT(UNHEX(A.email), '{$key}') as email, A.user_name, A.userid, B.business_seq, B.bname
							FROM
								fm_member A
							LEFT JOIN
								fm_member_business B
							ON
								A.member_seq = B.member_seq
							WHERE
								status != 'withdrawal' and email != '' and A.mailing = 'y'";
					$query	= $this->db->query($sql);
					$data	= $query->result_array();

					if(count($data)>0){
						foreach($data as $k){
							if($k['email']) {
								$arr['email']		= $k['email'];
								$arr['user_name']	= $k['business_seq'] ? $k['bname'] : $k['user_name'];
								$arr['userid']		= $k['userid'];
								array_push($mailArr,$arr);
							}
						}
					}
					break;
				case "search":
					foreach($_GET as $keyval => $value){
							$sc[$keyval] = $value;
					}
					if($sc["keyword"] == "이름, 아이디, 이메일, 전화번호, 핸드폰(뒷자리4), 주소, 닉네임"){
						$sc["keyword"] = "";
					}
					$sc['mailing'] = 'y';
					$sc['nolimit']	= 'y';
					//$this->load->model('membermodel');
					$data = $this->membermodel->admin_member_list_spout($sc);
					if(count($data['result'])>0){
						foreach($data['result'] as $k){
							if($k['email']) {
								$arr['email'] = $k['email'];
								$arr['user_name'] = $k['user_name'];
								$arr['userid'] = $k['userid'];
								if($k['business_seq']){
									$arr['user_name'] = $k['bname'];
								}
								array_push($mailArr,$arr);
							}
						}
					}
					break;
				case "select":
					foreach($_GET as $keyval => $value){
						if($keyval == 'member_chk'){
							foreach($value as $keyval2 => $member_seq){
								$sql = "select
								AES_DECRYPT(UNHEX(A.email), '{$key}') as email, A.user_name, A.userid, B.business_seq, B.bname from
								fm_member A
								LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq
								where A.member_seq = '".$member_seq."' and A.email<>'' and A.mailing = 'y'";
								$query = $this->db->query($sql);
								$data = $query->result_array();
								if($data[0]['email']){
									$arr['email'] = $data[0]['email'];
									$arr['user_name'] = $data[0]['user_name'];
									$arr['userid'] = $data[0]['userid'];
									if($data[0]['business_seq']){
										$arr['user_name'] = $data[0]['bname'];
									}
									array_push($mailArr,$arr);
								}
							}
						}
					}
					break;
				case "excel":
					break;
			}
		}

		###
		if($mailArr){
			foreach($mailArr as $v){
				if(is_array($v)){
					$params .= $anp."mid[".$cnt."]=".addslashes($v['userid'])."&email[".$cnt."]=".addslashes($v['email'])."&name[".$cnt."]=".addslashes(iconv("utf-8", "euc-kr", $v['user_name']));
				}else{
					$params .= $anp."mid[".$cnt."]=".addslashes($v)."&email[".$cnt."]=".addslashes($v)."&name[".$cnt."]=".addslashes($v);
				}

				if($subCnt % 1000 != 0 || $subCnt == 0){
					$cnt++;
				} else {
					$params .= "&first_yn=".$first_yn."&domain=".addslashes($_SERVER["SERVER_NAME"])."&userid=".$cid."&groupName=".$groupName."\n";

					$this->setEmails($params);

					$params = "";
					$first_yn = "N";
					$anp = "";
					$cnt = 0;

				}
				$anp = "&";
				$subCnt++;
			}

			$params .= "&first_yn=".$first_yn."&domain=".addslashes($_SERVER["SERVER_NAME"])."&userid=".$cid."&groupName=".$groupName."\n";
			$this->setEmails($params);

			$mailEndCnt = $this->getEmailCnt($cid);
			$mailStartCnt = $send_count+$add_email_count;
			if($cnt > 0) $result = array("result"=>TRUE, "msg"=>"요청하신 총 {$mailStartCnt}개의 이메일 중 수신 동의를 한 이메일 {$mailEndCnt}개가 세팅되었습니다.");
			else $result = array("result"=>FALSE, "msg"=>"이메일 셋팅에 실패하였습니다.");
		}else{
			$result = array("result"=>FALSE, "msg"=>"전송할 회원이 없습니다.");
		}
		echo "[".json_encode($result)."]";
	}

	public function setEmails($params) {

		$fp = fsockopen ("amail.firstmall.kr", 80);
		$strLen =  strlen($params);

		fputs($fp,"POST http://amail.firstmall.kr/new_amail.input.php HTTP/1.0\n");
		fputs($fp,"User-Agent: navyism\n");
		fputs($fp,"Content-type: application/x-www-form-urlencoded\n");
		fputs($fp,"Content-length: ".$strLen."\n");
		fputs($fp,"\n");
		fputs($fp,$params);
		fputs($fp,"\n");

		while(! feof ($fp))
		{
			$file = fgets ($fp, 1024);
		}
		fclose ($fp);

		return $file;
	}


	public function iconUpload(){

		$this->load->library('Upload');
		if (is_uploaded_file($_FILES['grade_icon']['tmp_name'])) {

			// 경로 설정 및 권한 설정
			$dir						= '/data/icon/common/';
			@mkdir($dir);
			@chmod($dir,0707);

			// 파일 정보 설정
			$config['upload_path']		= ROOTPATH . $dir;
			$file_ext					= end(explode('.', $_FILES['grade_icon']['name']));//확장자추출
			$config['allowed_types']	= 'jpg|gif|jpeg|png';
			$config['overwrite']		= TRUE;
			$config['file_name']		= substr(microtime(), 2, 6).'.'.$file_ext;
			$this->upload->initialize($config);

			// 업로드 처리
			if ($this->upload->do_upload('grade_icon')) {

				// 업로드 파일 권한 설정
				$file_real_path = $config['upload_path'].$config['file_name'];
				@chmod($file_real_path, 0777);

				// 업로드 성공 후 이미지 리사이징 추가 :: 2019-09-03 pjw
				ImgResize($file_real_path, $file_real_path, 15, 16);

				$callback = "parent.iconDisplay('{$config[file_name]}');parent.iconRegist.reset();";
				openDialogAlert("등록하였습니다.",400,140,'parent',$callback);
			}else{
				$callback = "";
				openDialogAlert("gif, jpg,jpeg, png 만 가능합니다.",400,140,'parent',$callback);
			}
		}else{
			$callback = "";
			openDialogAlert("등록 파일이 없습니다.",400,140,'parent',$callback);
		}
		exit;
	}

	public function myiconUpload(){

		$this->load->library('Upload');
		if (is_uploaded_file($_FILES['my_grade_icon']['tmp_name'])) {

			// 경로 설정 및 권한 설정
			$dir						= ROOTPATH.'/data/icon/mypage/';
			@mkdir($dir);
			@chmod($dir,0707);

			// 파일 정보 설정
			$config['upload_path']		= $dir;
			$file_ext					= end(explode('.', $_FILES['my_grade_icon']['name']));//확장자추출
			$config['allowed_types']	= 'jpg|gif|jpeg|png';
			$config['overwrite']		= TRUE;
			$config['file_name']		= substr(microtime(), 2, 6).'.'.$file_ext;
			$this->upload->initialize($config);

			// 업로드 처리
			if ($this->upload->do_upload('my_grade_icon')) {

				// 업로드 파일 권한 설정
				$file_real_path = $config['upload_path'].$config['file_name'];
				@chmod($file_real_path, 0777);

				// 업로드 성공 후 이미지 리사이징 추가 :: 2019-09-03 pjw
				ImgResize($file_real_path, $file_real_path, 60, 60);

				$callback = "parent.myiconDisplay('{$config[file_name]}');";
				openDialogAlert("등록하였습니다.",400,140,'parent',$callback);
			}else{
				$callback = "";
				openDialogAlert("gif, jpg,jpeg, png 만 가능합니다.",400,140,'parent',$callback);
			}

		}else{
			$callback = "";
			openDialogAlert("등록 파일이 없습니다.",400,140,'parent',$callback);
		}
		exit;
	}

	// 아이콘 삭제 추가 :: 2019-12-03 pjw
	public function iconDelete(){

		$icon_type	= $this->input->post('icon_type');
		$icon		= $this->input->post('icon');

		if(empty($icon_type) || empty($icon)){
			$msg	= '아이콘 정보가 없습니다.';
			$result	= '0';
		}else{

			$dir = ROOTPATH.'/data/icon/';
			if($icon_type == 'mypage')	$dir .= 'mypage/';
			else						$dir .= 'common/';

			@unlink($dir.$icon);
			$msg	= '아이콘 삭제 완료.';
			$result	= '1';
		}

		echo json_encode(array('result' => $result, 'msg' => $msg));
		exit;
	}


	public function getEmailCnt($cid) {
		$params = "userid=".$cid."";

		$fp = fsockopen ("amail.firstmall.kr", 80);
		$strLen =  strlen($params);

		fputs($fp,"POST http://amail.firstmall.kr/getEmailCnt.php HTTP/1.0\n");
		fputs($fp,"User-Agent: navyism\n");
		fputs($fp,"Content-type: application/x-www-form-urlencoded\n");
		fputs($fp,"Content-length: ".$strLen."\n");
		fputs($fp,"\n");
		fputs($fp,$params);
		fputs($fp,"\n");

		while(! feof ($fp))
		{
			$file = fgets ($fp, 1024);
		}
		fclose ($fp);

		return $file;
	}

	// 실제 주문을 검색하여 회원의 주문수와 주문금액과 초대수, 추천수 업데이트합니다.
	public function all_update_orders($mseq=null)
	{
		set_time_limit(0);
		//$this->load->model('membermodel');
		$cfg_member_update_order				= config_load('member_update_order');
		$cfg_member_update_recommend		= config_load('member_update_recommend');
		$cfg_member_update_invite				= config_load('member_update_invite');

		$_GET['page']	 = ($_GET['page']) ? intval($_GET['page']):'1';
		$sc['limitnum'] = 500;//500

		$loop = $this->membermodel->member_cnt_batch_list($sc);

		### PAGE & DATA
		$query = "select count(*) cnt from fm_member where rute != 'withdrawal' ";
		$query = $this->db->query($query);
		$data = $query->row_array();
		$all_count = $data['cnt'];

		$idx = 0;
		foreach($loop['record'] as $k => $datarow) {
			//$this->membermodel->member_cnt_batch($datarow['member_seq']);
			$this->membermodel->member_order($datarow['member_seq']);
			//주문건/주문금액 필드추가 및 실시간업데이트 @2013-06-19
			$this->membermodel->member_order_batch($datarow['member_seq']);

			/**
			// 예) 7~8월 : 2개월 이전주문건을 배송완료처리시 주문건/주문금액이 업데이트 수동처리시 이용
			$this->membermodel->member_order_old_gabia($datarow['member_seq'],'2014-07');
			$this->membermodel->member_order_old_gabia($datarow['member_seq'],'2014-08');
			$this->membermodel->member_order_batch($datarow['member_seq']);
			**/
			$idx++;
		}

		if( ($_GET['page']>1 && $all_count <= ( ($sc['limitnum']*$_GET['page'])) ) || ($_GET['page']==1 && $all_count == $idx ) ) {
			if( !$cfg_member_update_order['update_date'] ) {
				config_save('member_update_order',array('update_date'=>date('Y-m-d H:i:s')));
			}

			if( !$cfg_member_update_recommend['update_date'] ) {
				config_save('member_update_recommend',array('update_date'=>date('Y-m-d H:i:s')));
			}

			if( $cfg_member_update_invite['update_date'] ) {
				config_save('member_update_invite',array('update_date'=>date('Y-m-d H:i:s')));
			}
			$status = 'FINISH';
		}else{
			$status = 'NEXT';
		}

		$totalpage = @ceil($all_count/ $sc['limitnum']);

		$result = array('status' => $status,'totalcount'=>$all_count,'nextpage'=>($_GET['page']+1),'totalpage'=>$totalpage);
		echo json_encode($result);
		exit;
	}

	public function excel_down(){
		$this->load->model('excelmembermodel');
		if(is_array($_POST)){
			$params = $_POST;
		}else{
			$params = $_GET;
		}

		if($params['excel_type'] == 'select' && count($params['member_chk']) <= 0){
			echo "회원을 선택 해 주세요.";
			exit;
		}

		//$this->excelmembermodel->create_excel_list($_GET);
		//회원 정보 다운로드 비밀번호 검증
		if ($this->session->userdata['member_excel_download'] != "y") {
			$callback = "parent.openDialog('회원정보 다운로드','admin_member_download', {'width':550,'height':420});parent.$('input[name=member_download_passwd]').val('');parent.$('input[name=member_download_passwd]').focus();";
			openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
			exit;
		}

		$limitCount = 5000;

		// 전체 회원 다운로드 시에는 전체 회원수가 넘어갈 수 있도록 처리
		// 실제 다운로드는 전체 회원 다운로드가 되고있었으나 개수가 잘못넘어갔음
		$download_count = $params['searchcount'];
		if($params['excel_type'] == 'all') {
			$download_count = $params['totalcount'];
		}
		if( $download_count > $limitCount){ //압축 다운로드 시
			//type >> 1:goods, 2:order, 3:member
			$regDate = date('Y-m-d H:i:s');
			$setData = array(
				'id'			=> '',
				'provider_seq'	=> 1,
				'manager_id'	=> $this->managerInfo['manager_id'],
				'category'		=> '3',
				'excel_type'	=> $params['excel_type'],
				'context'		=> serialize($params),
				'count'			=> $download_count,
				'state'			=> 0,
				'limit_count'	=> $limitCount,
				'reg_date'		=> $regDate
			);

			$this->db->insert('fm_queue', $setData);
			$queueID = $this->db->insert_id();
			if( $queueID > 0 ){
				$expectTime = ((ceil($download_count/$limitCount)) * 5) + 1200;
				echo '엑셀 파일 생성 중 (예상 소요시간 : '.gmdate("H시 i분 s초", $expectTime).')';
			} else {
				echo "Job Insert Errors";
			}
		} else {
			$this->excelmembermodel->create_excel_list_spout($params);
		}

		exit;
	}

	public function download_write(){

		if(count($_POST['downloads_item_use'])<1){
			$callback = "";
			openDialogAlert("다운로드 항목을 1개 이상 설정해 주세요.",400,140,'parent',$callback);
			exit;
		}

		$item = implode("|",$_POST['downloads_item_use']);
		$params['item']			= $item;

		$this->db->where('gb', 'MEMBER');
		$result = $this->db->update('fm_exceldownload', $params);
		$msg	= "수정 되었습니다.";
		$func	= "parent.closeDialog('download_list_setting');";

		openDialogAlert($msg,400,140,'parent',$func);

	}

   	//회원아이콘 설정
	public function membericonsave(){
		$this->mdata = $this->membermodel->get_member_data($_GET['mseq']);//회원정보
		$user_icon = $this->mdata['user_icon'];
		$user_icon_file = $this->mdata['user_icon_file'];
		if($_FILES['membericonFile']['tmp_name']){
			$this->load->model('usedmodel');
			$data_used = $this->usedmodel->used_limit_check();
			if( $data_used['type'] ){
				$config['upload_path'] = './data/icon/member';
				$config['max_size']	= $this->config_system['uploadLimit'];
				$tmp = @getimagesize($_FILES['membericonFile']['tmp_name']);
				if( $tmp[0] > 30 && $tmp[1] > 30 ){
					$msg = '가로*세로 사이즈가 30*30 이하이어야 합니다.';
					openDialogAlert($msg,400,150,'parent');
					exit;
				}

				if($user_icon_file){
					@unlink($_SERVER['DOCUMENT_ROOT'].$config['upload_path'].'/'.$user_icon_file);
				}
				$_FILES['membericonFile']['type'] = $tmp['mime'];

				$file_ext		= end(explode('.', $_FILES['membericonFile']['name']));//확장자추출
				$file_name	= 'm_'.$_GET['mseq'].'.'.$file_ext;//'.str_replace(" ", "", (substr(microtime(), 2, 6))).'
				$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
				$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
				$config['file_name'] = $file_name;
				$config['allowed_types'] = 'jpg|gif|jpeg|png';
				$config['overwrite'] = true;
				$this->load->library('upload', $config);
				if ( ! $this->upload->do_upload('membericonFile'))
				{
					$error = $this->upload->display_errors();
					openDialogAlert($error,400,150,'parent');
					exit;
				}
				$uploadData = $this->upload->data();
				$user_icon = str_replace($_SERVER['DOCUMENT_ROOT'],'',$uploadData['file_path']).$uploadData['raw_name'].$uploadData['file_ext'];
				$this->db->where('member_seq', $_GET['mseq']);
				$result = $this->db->update('fm_member', array("user_icon"=>99, "user_icon_file"=>$file_name));
			}else{
				openDialogAlert($data_used['msg'],400,140,'parent','');
			}
		}

		$callback = "parent.membericonDisplay('{$user_icon}?".time()."');";
		openDialogAlert("등록하였습니다.",400,140,'parent',$callback);
	}

	public function safe_key_check(){

		$this->load->model("smsmodel");
		$return = $this->smsmodel->checkSafeKey();

		echo json_encode($return);
		exit;

	}

	// 수신거부 URL 생성
	public function getUnsubscribeUrl(){

		$aPostParams = $this->input->post();
	    if(empty($aPostParams['protocol'])){
	        $aPostParams['protocol'] = 'http://';
	    } else {
	        $aPostParams['protocol'] = stripslashes($aPostParams['protocol']);
	    }
		$lengText 		= ['kor' => '수신거부', 'eng' => 'Click here'];
		$return['url'] = '<a href="'.$aPostParams['protocol'].$aPostParams['domain'].'/member/unsubscribe?ussKey={unSubScribeKey}&verify='.md5($this->config_system['shopSno']).'">[_lengText_]</a>';
		$return['url'] = str_replace('_lengText_', $lengText[$aPostParams['language']], $return['url']);

		echo json_encode($return);
		exit;
	}

	# KAKAO TALK FNC ADD - START - :: 2018-03-02 lwh
	// 카카오 알림톡 신청하기
	public function kakaotalk_regist(){

		$this->load->model('kakaotalkmodel');

		// 설정정보 호출
		$kakaotalk_config	= $this->kakaotalkmodel->get_service();
		if	($kakaotalk_config['authKey'] && $_POST['type'] != 'regist'){
			$this->kakaotalk_modify();
			exit;
		}

		$auth = $this->authmodel->manager_limit_act('kakaotalk_setting');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		if (!$_POST['agree1'] || !$_POST['agree2'] || !$_POST['agree3']){
			unset($agree_chk);
			$agree_txt = array('서비스 이용약관', '개인정보수집', '개인정보 위탁');
			for($i=1; $i<=3; $i++){
				if(!$_POST['agree'.$i]){
					$agree_chk[] = $agree_txt[$i-1];
				}
			}
			openDialogAlert(implode(',',$agree_chk) . '에 동의를 하셔야 합니다.',500,150,'parent',$callback);
			exit;
		}

		if($_FILES['filedata']){
			if($_FILES['filedata']['type'] != 'image/jpeg'){
				openDialogAlert('jpg 이미지만 가능합니다.',400,150,'parent',$callback);
				exit;
			}
			if($_FILES['filedata']['size'] > '500000'){
				openDialogAlert('500kb 이하의 이미지만 가능합니다.',400,150,'parent',$callback);
				exit;
			}
		}else{
			openDialogAlert('사업자 등록증 이미지를 업로드하세요.',400,150,'parent',$callback);
			exit;
		}

		### Validation
		$this->validation->set_rules('phoneNumber', '휴대폰번호','trim|required|numeric|xss_clean');
		$this->validation->set_rules('authKey', '인증번호','trim|required|xss_clean');
		$this->validation->set_rules('token', '인증번호','trim|required|numeric|xss_clean');
		$this->validation->set_rules('yellowId', '플러스친구 아이디','trim|required|xss_clean');
		$this->validation->set_rules('businessLicense', '사업자등록번호','trim|required|xss_clean|min_length[10]|max_length[10]');
		$this->validation->set_rules('category3', '업종','trim|required|xss_clean');
		$this->validation->set_rules('category_name', '업종','trim|required|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,150,'parent',$callback);
			exit;
		}

		// 옐로아이디 공백 예외처리 :: 2018-07-13 lwh
		$_POST['yellowId'] = str_replace(' ','',$_POST['yellowId']);

		$apiParams	= $_POST;
		$result		= $this->kakaotalkmodel->apiSender('setRegist', $apiParams);

		if($result['code'] == '200'){
			$callback = "parent.location.reload();";
			openDialogAlert('신청되었습니다.',400,150,'parent',$callback);
			exit;
		}else{
			openDialogAlert($result['errmsg'],400,150,'parent',$callback);
			exit;
		}
	}

	// 카카오톡 설정 수정
	public function kakaotalk_modify(){
		$auth = $this->authmodel->manager_limit_act('kakaotalk_setting');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$this->load->model('kakaotalkmodel');

		$use_service = $this->input->post('use_service');
		$this->kakaotalkmodel->set_kakaoConfig(['use_service' => $use_service]);
		
		if ($use_service === 'Y') {
			$this->kakaotalkmodel->set_template_default_code();
			$this->kakaotalkmodel->set_template_sync();
		}

		$callback = "parent.location.reload();";
		openDialogAlert('알림톡 사용 설정이 저장되었습니다.',400,150,'parent',$callback);
	}

	// 카카오톡 메세지 사용여부 수정
	public function kakaotalk_msg_use_modify(){
		$auth = $this->authmodel->manager_limit_act('kakaotalk_setting');
		if(!$auth){
			echo $this->auth_msg;
			exit;
		}

		$this->load->model('kakaotalkmodel');

		$setParams['msg_code']	= $_POST['msg_code'];
		$setParams['msg_yn']	= ($_POST['msg_yn'] == 'true') ? 'Y' : 'N';;

		$result = $this->kakaotalkmodel->set_template_use($setParams);
		if($result){
			echo $setParams['msg_yn'];
		}
	}

	// 카카오톡 템플릿 수정
	public function kakaotalk_template_modify(){
		$auth = $this->authmodel->manager_limit_act('kakaotalk_setting');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		### Validation
		if (!$_POST['msg_code']){
			$msg = '오류가 발생하였습니다.<br/>새로고침 후 다시 시도해주세요.<br/><br/>같은문제가 지속되면 고객센터로 연락바랍니다.<br/>Tel : 1544-3270';
			openDialogAlert($msg,400,200,'parent',$callback);
			exit;
		}
		$content_len = strlen($_POST['templateContents']);
		if ($content_len <= 0){
			$msg = '내용은 필수 입니다';
			$callback = 'parent.document.getElementsByName(\'templateContents\')[0].focus()';
			openDialogAlert($msg,400,150,'parent',$callback);
			exit;
		}
		$linkFlag = false;
		$kkoBtnYn = $_POST['kkoBtnYn'];		// 버튼 사용여부
		$linkType = $_POST['kkoLinkType'];	// 버튼 타입 Arr
		if ($linkType == 'Y' && count($linkType) > 0) foreach($linkType as $k => $val){
			if ($val == 'WL'){ // 웹링크시 버튼명, 링크 필수
				if (!$_POST['kkoLinkPc'][$k]){
					$errField = '링크주소는';
					$linkFlag = true;
				}
				if (!$_POST['kkoLinkName'][$k]){
					$errField = '버튼명은';
					$linkFlag = true;
				}
				$msg = '버튼링크 사용시 ' . $errField . ' 필수 입니다';
			}
			if ($linkFlag){
				openDialogAlert($msg,400,150,'parent',$callback);
				exit;
			}
		}

		// 요청 프로세스 시작
		$this->load->model('kakaotalkmodel');
		$apiParams	= $_POST;
		$result		= $this->kakaotalkmodel->apiSender('registTemplte', $apiParams);

		if($result['code'] == '200'){
			$callback = "parent.location.reload();";
			openDialogAlert('카카오에 승인 요청되었습니다.',400,150,'parent',$callback);
			exit;
		}else{
			openDialogAlert($result['errmsg'],400,150,'parent',$callback);
			exit;
		}
	}

	// 카카오 템플릿 문의하기
	public function kakaotalk_template_comment(){
		$auth = $this->authmodel->manager_limit_act('kakaotalk_setting');
		if(!$auth){
			$callback = "";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		$params['kkoBizCode']	= $_POST['reject_kkoBizCode'];
		$params['comment']		= $_POST['reject_comment'];
		if (!$params['kkoBizCode']){
			$msg = '오류가 발생하였습니다.<br/>새로고침 후 다시 시도해주세요.<br/><br/>같은문제가 지속되면 고객센터로 연락바랍니다.<br/>Tel : 1544-3270';
			openDialogAlert($msg,400,200,'parent',$callback);
			exit;
		}
		if (!$params['comment']){
			$msg = '답변내용을 입력해주세요.';
			openDialogAlert($msg,400,150,'parent',$callback);
			exit;
		}

		$this->load->model('kakaotalkmodel');
		$result		= $this->kakaotalkmodel->apiSender('commentTemplte', $params);

		if($result['code'] == '200'){
			$callback = "parent.closeDialog('rejectPopup');";
			openDialogAlert('템플릿문의가 전송되었습니다.',400,150,'parent',$callback);
			exit;
		}else{
			openDialogAlert($result['errmsg'],400,150,'parent',$callback);
			exit;
		}
	}

	# KAKAO TALK FNC ADD - END -
}

/* End of file member_process.php */
/* Location: ./app/controllers/admin/member_process.php */
