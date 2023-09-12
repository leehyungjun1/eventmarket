<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

class member_process extends front_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->load->library('snssocial');
        $this->load->model('membermodel');
        $this->load->model('appmembermodel');
		$this->load->helper('member');

		//스킨패치 하지 않은 사용자를 위해 우편번호 합치기
		if($_POST['zipcode'][1]){
			$_POST['zipcode'][0] = @implode('',$_POST['zipcode']);
			unset($_POST['zipcode'][1]);
		}
		if($_POST['bzipcode'][1]){
			$_POST['bzipcode'][0] = @implode('',$_POST['bzipcode']);
			unset($_POST['bzipcode'][1]);
		}
	}


	###
	public function register(){

		$this->load->library('memberlibrary');

		$url		= '';
		$urlParam 	= '';
		$param 		= array();
		$agree 		= $this->input->post('agree');
		$agree2 	= $this->input->post('agree2');
		$join_type 	= $this->input->post('join_type');
		$mailing 	= $this->input->post('mailing');
		$sms	 	= $this->input->post('sms');

		/**
		 * kid_agree 변수는 Y , N , '', null  4가지 값이 생성된다.
		 * Y : "만 14세" 체크박스 선택
		 * N : "만 14세" 체크박스 미선택
		 * '' : "만 14세" 체크박스 기본값
		 * null : 스킨 패치전 이라 "만 14세" 체크박스가 노출되지 않음
		 */
		$kid_agree  = $this->input->post('kid_agree');
		$mtype		= $join_type;

		if($agree!='Y' || $agree2!='Y'){
			$key	= $agree!='Y' ? "agree" : "agree2";
			$name	= $agree!='Y' ? "이용약관에 동의하셔야합니다." : "개인정보처리방침에 동의하셔야합니다.";
			$callback = "if(parent.document.getElementsByName('{$key}')[0]) parent.document.getElementsByName('{$key}')[0].focus();";
			openDialogAlert($name,400,140,'parent',$callback);
			exit;
		}

		/*
		kid agree 변수가 존재 않을 때 :: 만 14세 미만 관련 스킨 패치가 안된 상태.
		만 14세 미만 설정값 관계없이 기존 로직대로 가입 진행.
		*/
		if(isset($kid_agree)){
			$skin_patch_14years_old = true;
		}else{
			$skin_patch_14years_old = false;
		}
		$this->memberlibrary->kidAgreeCheck(array('mtype'=>$mtype, 'kid_agree'=>$kid_agree, 'skin_patch_14years_old'=>$skin_patch_14years_old));

		// 14세 가입 불가일때 필수동의 체크 :: 2020-06-15 sms
		if(empty($kid_agree)!=true)$param['kid_agree'] = 'kid_agree='.$kid_agree;
		$auth		= $this->session->userdata('auth');
		$joinform	= config_load('joinform');

		if($auth){
			if($joinform['kid_join_use'] != 'A') $kid_agree = 'kid_agree=Y';
			else $kid_agree = NULL;
		}else{
			// 만 14세미만 미체크시 가입 불가.
			// 단, 스킨 미패치시에는 해당 기능 통과
			if($mtype != 'business' && $joinform['kid_join_use'] == 'N' && !$kid_agree && $skin_patch_14years_old){
				openDialogAlert(getAlert('mb262'),400,140,'parent','');
				exit;
			}
		}
		

		if(empty($join_type)!=true)$param['join_type'] = 'join_type='.$join_type;
		if(empty($mailing)!=true)$param['mailing'] = 'mailing=y';
		if(empty($sms)!=true)$param['sms'] = 'sms=y';
		if(empty($param)!=true)	$urlParam = '?'.implode('&',$param);

		$url = '../member/register'.$urlParam;

		pageRedirect($url,'','parent');
	}

	###
	public function id_chk($chk_key = null){

		//#####  2018.02.05 gcs ksm : RSA 17.11.30~ 패치
		// https 보안강화를 위해 아이디 체크 기능 변경 front 영역은 common.js에서 호출
		if($chk_key == null ){
			$this->load->model('ssl');
			$this->ssl->decode();
		}
		//#####  2018.02.05 gcs ksm : RSA 17.11.30~ 패치

		$conf = config_load('joinform');

		if ( $conf['email_userid'] == 'Y' ) {
			if($_POST['userid'] == '@' ) $_POST['userid'] = '';
		}

		$userid = $_POST['userid'];
		if(!$userid) die();
		$userid = strtolower($userid);

		if ( $conf['email_userid'] == 'Y' ) {
			//이메일
			$this->validation->set_rules('userid', getAlert('mb051'),'trim|required|valid_email|xss_clean');
			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				//유효하지 않는 이메일 형식입니다.
				$text = getAlert('mb052');//$err['value'];
				$result = array("return_result" => $text, "userid" => $_POST['userid'], "return" => false, "returns" => false);

				if($chk_key){
					return $result;
				}else{
					//#####  2018.02.05 gcs ksm : RSA 17.11.30~ 패치
					if($_POST['sslEncodedString'] || ($_POST['jCryption'] && $_POST['encryptionKey'])){
						echo "<script>parent.callbackIdChk('".json_encode($result)."');</script>";
					}else{
						echo json_encode($result);
					}
					//#####  2018.02.05 gcs ksm : RSA 17.11.30~ 패치


					//echo json_encode($result);
				}
			}
		}

		###
		$count = get_rows('fm_member',array('userid'=>$userid));

		###
		$disabled_userid = explode(",",$conf['disabled_userid']);

		$return = true;
		if ( $conf['email_userid'] == 'Y' ) {
			$text = "OK";
			if(in_array($userid, $disabled_userid)) {
				//금지 이메일 입니다.
				$text = getAlert('mb053');
				$return = false;
			}else if($count > 0){
				//이미 등록된 이메일 입니다.
				$text = getAlert('mb054');
				$return = 'duplicate';
			}
		}else{
			//사용할 수 있는 아이디 입니다.
			$text = getAlert('mb055');
			if(strlen($userid)<6 || strlen($userid)>20){
				//아이디 글자 제한 수를 맞춰주세요.
				$text = getAlert('mb056');
				$return = false;
			}else if(preg_match("/[^a-z0-9\-_]/i", $userid)) {
				//사용할 수 없는 아이디 입니다.
				$text = getAlert('mb057');
				$return = false;
			}else if(in_array($userid, $disabled_userid)) {
				//금지 아이디 입니다.
				$text = getAlert('mb058');
				$return = false;
			}else if($count > 0){
				//이미 사용중인 아이디 입니다.
				$text = getAlert('mb059');
				$return = false;
			}
		}
		$result = array("return_result" => $text, "userid" => $userid, "return" => $return, "returns" => $return);

		if($chk_key){
			return $result;
		}else{
			//#####  2018.02.05 gcs ksm : RSA 17.11.30~ 패치
			if($_POST['sslEncodedString'] || ($_POST['jCryption'] && $_POST['encryptionKey'])){
				echo "<script>parent.callbackIdChk('".json_encode($result)."');</script>";
			}else{
				echo json_encode($result);
			}
			//#####  2018.02.05 gcs ksm : RSA 17.11.30~ 패치

			//echo json_encode($result);
		}
	}

	### 비밀번호 유효성체크
	public function pw_chk($chk_key = null){

		$conf = config_load('joinform');

		$password = $_POST['password'];
		if(!$password) die();
		//비밀번호
		$this->validation->set_rules('password', getAlert('mb060'),'trim|required|min_length[6]|max_length[32]|xss_clean');
		if($this->validation->exec()===false){
			//유효하지 않은 비밀번호 형식입니다.
			$text = getAlert('mb061');
			$result = array("return_result" => $text, "password" => $password, "return" => false, "returns" => false);

			if($chk_key){
				return $result;
			}else{
				echo json_encode($result);
			}
		}

		###
		$return = true;
		$text = "OK";
		$mix_check = 0;
		//소문자영문체크
		if(preg_match("/[a-z]/",$_POST['password'])){
			$mix_check += 1;
		}

		//대문자영문체크
		if(preg_match("/[A-Z]/",$_POST['password'])){
			$mix_check += 1;
		}

		//숫자체크
		if(preg_match("/[0-9]/",$_POST['password'])){
			$mix_check += 1;
		}

		//특수문자체크
		if(preg_match("/[!#$%^&*()?+=\/]/",$_POST['password'])){
			$mix_check += 1;
		}
		if(strlen($password)<6 || strlen($password)>20){
			//비밀번호 글자 제한 수를 맞춰주세요.
			$text = getAlert('mb062');
			$return = false;
		}

		if($mix_check < 2){
			//비밀번호는 6~20자 영문 대소문자, 숫자, 특수문자 중<br> 2가지 이상 조합이어야 합니다.
			$text = getAlert('mb037');
			$return = false;
		}
		$result = array("return_result" => $text, "password" => $password, "return" => $return, "returns" => $return);

		if($chk_key){
			return $result;
		}else{
			echo json_encode($result);
		}
	}

	###
	public function bno_chk($chk_key = null){
		$joinform = config_load('joinform');

		$bno = trim($_POST['bno']);
		$count = get_rows('fm_member_business',array('bno'=>$bno));
		$bno = str_replace('-','',$bno);

		$text = "";
		$return = true;
		if($joinform['bno_use']=='Y' && ($joinform['bno_required']=='Y' || $_POST['bno']) ) {//사용중이면서 필수 또는 입력된 경우에만
			$bcheck = $this->membermodel->bizno_check($bno);
			if( $bcheck === false ) {
				//올바르지 않은 사업자등록번호 입니다.
				$text = getAlert('mb064');
				$return = false;
			}

			if($count > 0){
				//이미 가입된 사업자등록번호 입니다.
				$text = getAlert('mb065');
				$return = false;
			}
		}
		$result = array("return_result" => $text, "bno" => $bno, "return" => $return, "returns" => $return);

		if($chk_key){
			return $result;
		}else{
			echo json_encode($result);
		}
	}



	###
	public function register_ok(){
		$this->load->model('ssl');
		$this->ssl->decode();

		$joinform				= config_load('joinform');
		$config_member			= config_load('member');

		$params					= $this->input->post();
		$label_pr				= $params['label'];
		$label_sub_pr			= $params['labelsub'];
		$label_required			= $params['required'];
		$label_required_title	= $params['required_title'];
		$_POST['userid']		= strtolower($params['userid']);

		$kid_auth				= '';
		$kid_agree				= $params['kid_agree'];
		$skin_patch_14years_old	= $this->session->userdata('skin_patch_14years_old');

		## 회원가입 버튼 재 노출
		$callback_default = "parent.document.getElementById('btn_register').style.display='block';";

		if( $joinform['join_type']=='member_business' && !$params['mtype'] ) {
			print "<script type='text/javascript'>alert('회원 유형을 선택해주세요.');</script>";
			pageRedirect($url='/member/agreement', '', 'parent');
			exit;
		} else {
			$params['mtype'] = isset($params['mtype']) ? $params['mtype'] : str_replace("_only","",$joinform['join_type']);
		}

		### Validation
		if ( $joinform['email_userid'] == 'Y' ) {
			//아이디
			$this->validation->set_rules('userid', getAlert('mb010'),'trim|required|valid_email|xss_clean');
			//아이디확인
			$this->validation->set_rules('re_userid', getAlert('mb011'),'trim|required|valid_email|xss_clean');
		}else{
			//아이디
			$this->validation->set_rules('userid', getAlert('mb010'),'trim|required|min_length[6]|max_length[20]|xss_clean');
		}

		### COMMON
		if(!empty($_POST['anniversary'][0]) && !empty($_POST['anniversary'][1]))
			$_POST['anniversary'] = implode("-",$_POST['anniversary']);
		else
			$_POST['anniversary'] = '';

		### COMMON
		if(isset($_POST['email'])) $_POST['email'] = implode("@",$_POST['email']);
		if($_POST['email'] == '@' ) $_POST['email'] = '';

		### COMMON
		if ( $joinform['email_userid'] == 'Y' ) {//&& !$_POST['email']
			$_POST['email'] = $_POST['userid'];
		}

		if( is_array($_POST['births']) ) {
			if( $_POST['births'][0] && $_POST['births'][1] && $_POST['births'][2]) {
				$_POST['birthday'] =  $_POST['births'][0].'-'.str_pad($_POST['births'][1],2 ,"0", STR_PAD_LEFT).'-'.str_pad($_POST['births'][2],2 ,"0", STR_PAD_LEFT);
			}
		}else{
			if($_POST['births']){
				$_POST['birthday'] = $_POST['births'];
			}else{
				$_POST['birthday'] = $_POST['birthday'] ? $_POST['birthday'] : '';
			}
		}

		if($joinform['recommend_use']=='Y'){
			//추천인
			if($joinform['recommend_required']=='Y') $this->validation->set_rules('recommend', getAlert('mb014'),'trim|required|max_length[100]|xss_clean');
			else $this->validation->set_rules('recommend', getAlert('mb014'),'trim|max_length[100]|xss_clean');

		}

		if(!isset($_POST['new_zipcode']) && $_POST['zipcode']){
			$_POST['new_zipcode']	= implode('',$_POST['zipcode']);
			unset($_POST['zipcode']);
		}

		// 비밀번호 유효성 체크
		$pre_enc_password = '';
		$enc_password = '';

		$check_password = $this->input->post('password');
		$password_params = array(
			'birthday'                => array($_POST['birthday'], $_POST['anniversary']),
			'phone'                   => array(implode("-",$_POST['phone']), implode("-",$_POST['bphone'])),
			'cellphone'                   => array(implode("-",$_POST['cellphone']), implode("-",$_POST['bcellphone'])),
			'pre_enc_password'        => $pre_enc_password,
			'enc_password'            => $enc_password,
		);
		$this->load->library('memberlibrary');
		$result = $this->memberlibrary->check_password_validation($check_password, $password_params);
		if($result['code'] != '00' && $result['alert_code']){
			openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback_default);
			exit;
		}

		//비밀번호
		$this->validation->set_rules('password', getAlert('mb012'),'trim|required|min_length[8]|max_length[20]|xss_clean');
		//비밀번호확인
		$this->validation->set_rules('re_password', getAlert('mb013'),'trim|required|min_length[8]|max_length[20]|xss_clean');
		
		### MEMBER
		if(isset($_POST['mtype']) && $_POST['mtype']=='member'){

			if($joinform['email_use']=='Y'){
				if($joinform['email_required']=='Y') {
					//이메일
					$this->validation->set_rules('email', getAlert('mb015'),'trim|required|max_length[64]|valid_email|xss_clean');
				}elseif( !empty($_POST['email'])) {
					$this->validation->set_rules('email', getAlert('mb015'),'trim|max_length[64]|valid_email|xss_clean');
				}
			}

			if($joinform['user_name_use']=='Y'){
				//이름
				if($joinform['user_name_required']=='Y') $this->validation->set_rules('user_name', getAlert('mb016'),'trim|string|required|max_length[20]|xss_clean');
				else $this->validation->set_rules('user_name', getAlert('mb016'),'trim|string|max_length[20]|xss_clean');
			}
			if($joinform['phone_use']=='Y'){
				//연락처
				if($joinform['phone_required']=='Y') $this->validation->set_rules('phone[]', getAlert('mb017'),'trim|required|max_length[4]|numeric|xss_clean');
				else $this->validation->set_rules('phone[]', getAlert('mb017'),'trim|max_length[4]|xss_clean');
			}
			if($joinform['cellphone_use']=='Y'){
				//휴대폰번호
				if($joinform['cellphone_required']=='Y') $this->validation->set_rules('cellphone[]', getAlert('mb018'),'trim|required|max_length[4]|numeric|xss_clean');
				else  $this->validation->set_rules('cellphone[]', getAlert('mb018'),'trim|max_length[4]|xss_clean');
			}
			if($joinform['address_use']=='Y'){
				if($joinform['address_required']=='Y'){
					if(isset($_POST['new_zipcode'])){
						//우편번호
						$this->validation->set_rules('new_zipcode', getAlert('mb019'),'trim|required|max_length[7]|xss_clean');
					}else{
						$this->validation->set_rules('zipcode[]', getAlert('mb019'),'trim|required|max_length[7]|xss_clean');
					}
					//주소
					$this->validation->set_rules('address', getAlert('mb020'),'trim|required|string|max_length[100]|xss_clean');
					//상세 주소
					$this->validation->set_rules('address_detail', getAlert('mb021'),'trim|required|string|max_length[100]|xss_clean');
				} else {
					if(isset($_POST['new_zipcode'])){
						//우편번호
						$this->validation->set_rules('new_zipcode', getAlert('mb019'),'trim|max_length[7]|xss_clean');
					}else{
						$this->validation->set_rules('zipcode[]', getAlert('mb019'),'trim|max_length[7]|xss_clean');
					}
					//주소
					$this->validation->set_rules('address', getAlert('mb020'),'trim|string|max_length[100]|xss_clean');
					//상세 주소
					$this->validation->set_rules('address_detail', getAlert('mb021'),'trim|string|max_length[100]|xss_clean');
				}
			}
			if($joinform['birthday_use']=='Y'){
				//생일
				if($joinform['birthday_required']=='Y') $this->validation->set_rules('birthday', getAlert('mb022'),'trim|required|max_length[10]|xss_clean');
				else  $this->validation->set_rules('birthday', getAlert('mb022'),'trim|max_length[10]|xss_clean');
			}
			if($joinform['anniversary_use']=='Y'){
				//기념일
				if($joinform['anniversary_required']=='Y') $this->validation->set_rules('anniversary', getAlert('mb023'),'trim|required|max_length[5]|xss_clean');
				else  $this->validation->set_rules('anniversary', getAlert('mb023'),'trim|max_length[5]|xss_clean');
			}
			if($joinform['nickname_use']=='Y'){
				//닉네임
				if($joinform['nickname_required']=='Y') $this->validation->set_rules('nickname', getAlert('mb024'),'trim|required|max_length[10]|string|xss_clean');
				else  $this->validation->set_rules('nickname', getAlert('mb024'),'trim|max_length[10]|string|xss_clean');
			}
			if($joinform['sex_use']=='Y'){
				//성별
				if($joinform['sex_required']=='Y') $this->validation->set_rules('sex', getAlert('mb025'),'trim|required|max_length[6]|xss_clean');
				else  $this->validation->set_rules('sex', getAlert('mb025'),'trim|max_length[6]|xss_clean');
			}
		}

		### BUSINESS
		if(isset($_POST['mtype']) && $_POST['mtype']=='business'){
			if($joinform['bemail_use']=='Y'){
				if($joinform['bemail_required']=='Y') {
					//이메일
					$this->validation->set_rules('email', getAlert('mb015'),'trim|required|max_length[64]|valid_email|xss_clean');
				}elseif( !empty($_POST['email']) ) {
					$this->validation->set_rules('email', getAlert('mb015'),'trim|max_length[64]|valid_email|xss_clean');
				}
			}


			if($joinform['bname_use']=='Y'){
				//업체명
				if($joinform['bname_required']=='Y') $this->validation->set_rules('bname', getAlert('mb026'),'trim|required|max_length[20]|string|xss_clean');
				else  $this->validation->set_rules('bname', getAlert('mb026'),'trim|max_length[20]|string|xss_clean');
			}
			if($joinform['bceo_use']=='Y'){
				//대표자명
				if($joinform['bceo_required']=='Y') $this->validation->set_rules('bceo', getAlert('mb027'),'trim|required|max_length[32]|string|xss_clean');
				else  $this->validation->set_rules('bceo', getAlert('mb027'),'trim|max_length[32]|string|xss_clean');
			}
			if($joinform['bno_use']=='Y'){
				//사업자 등록번호
				if($joinform['bno_required']=='Y') $this->validation->set_rules('bno', getAlert('mb028'),'trim|required|max_length[12]|xss_clean');
				else  $this->validation->set_rules('bno', getAlert('mb028'),'trim|max_length[12]|xss_clean');
			}
			if($joinform['bitem_use']=='Y'){
				if($joinform['bitem_required']=='Y') {
					//업태
					$this->validation->set_rules('bitem', getAlert('mb029'),'trim|required|max_length[40]|string|xss_clean');
					//종목
					$this->validation->set_rules('bstatus', getAlert('mb030'),'trim|required|max_length[40]|string|xss_clean');
				}
				else{
					//업태
					$this->validation->set_rules('bitem', getAlert('mb029'),'trim|max_length[40]|string|xss_clean');
					//종목
					$this->validation->set_rules('bstatus', getAlert('mb030'),'trim|max_length[40]|string|xss_clean');
				}
			}

			if(!isset($_POST['new_bzipcode']) && $_POST['bzipcode']){
				$_POST['new_bzipcode']	= implode('',$_POST['bzipcode']);
				unset($_POST['bzipcode']);
			}

			if($joinform['badress_use']=='Y'){
				if($joinform['badress_required']=='Y'){
					if(isset($_POST['new_bzipcode'])){
						//우편번호
						$this->validation->set_rules('new_bzipcode', getAlert('mb019'),'trim|required|max_length[7]|xss_clean');
					}else{
						$this->validation->set_rules('bzipcode[]', getAlert('mb019'),'trim|required|max_length[3]|xss_clean');
					}
					//주소
					$this->validation->set_rules('baddress', getAlert('mb020'),'trim|required|max_length[100]|string|xss_clean');
					//상세 주소
					$this->validation->set_rules('baddress_detail', getAlert('mb021'),'trim|required|max_length[100]|string|xss_clean');
				} else {
					if(isset($_POST['new_bzipcode'])){
						//우편번호
						$this->validation->set_rules('new_bzipcode', getAlert('mb019'),'trim|max_length[7]|xss_clean');
					}else{
						$this->validation->set_rules('bzipcode[]', getAlert('mb019'),'trim|max_length[3]|xss_clean');
					}
					//주소
					$this->validation->set_rules('baddress', getAlert('mb020'),'trim|max_length[100]|string|xss_clean');
					//상세 주소
					$this->validation->set_rules('baddress_detail', getAlert('mb021'),'trim|max_length[100]|string|xss_clean');
				}
			}
			if($joinform['bperson_use']=='Y'){
				//담당자 명
				if($joinform['bperson_required']=='Y') $this->validation->set_rules('bperson', getAlert('mb031'),'trim|required|max_length[32]|string|xss_clean');
				else  $this->validation->set_rules('bperson', getAlert('mb031'),'trim|max_length[32]|string|xss_clean');
			}
			if($joinform['bpart_use']=='Y'){
				//담당자 부서명
				if($joinform['bpart_required']=='Y') $this->validation->set_rules('bpart', getAlert('mb032'),'trim|required|string|max_length[32]|xss_clean');
				else  $this->validation->set_rules('bpart', getAlert('mb032'),'trim|max_length[32]|string|xss_clean');
			}
			if($joinform['bphone_use']=='Y'){
				//전화번호
				if($joinform['bphone_required']=='Y') $this->validation->set_rules('bphone[]', getAlert('mb034'),'trim|required|max_length[4]|numeric|xss_clean');
				else $this->validation->set_rules('bphone[]', getAlert('mb034'),'trim|max_length[4]|xss_clean');
			}

			if($joinform['bcellphone_use']=='Y'){
				//휴대폰번호
				if($joinform['bcellphone_required']=='Y') $this->validation->set_rules('bcellphone[]', getAlert('mb018'),'trim|required|max_length[4]|numeric|xss_clean');
				else  $this->validation->set_rules('bcellphone[]', getAlert('mb018'),'trim|max_length[4]|xss_clean');
			}
		}

		### //넘어온 추가항목 seq
		foreach($label_pr as $l => $data){$label_arr[]=$l;}
		//추가항목 공백체크
		foreach($label_required as $lk => $v){
			if(!in_array($v,$label_arr)){
				if	($label_required_title[$lk])	$msg	= $label_required_title[$lk].getAlert('mb035'); //은 필수입니다.
				else								$msg	= getAlert('mb036'); //체크된 항목은 필수항목입니다.
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();".$callback_default;
				openDialogAlert($msg,400,140,'parent',$callback);
				exit;
			}else{
				$query = $this->db->get_where('fm_joinform',array('joinform_seq'=> $v));
				$form_result = $query -> row_array();
				$label_title = $form_result['label_title'];
				$this->validation->set_rules('label['.$v.'][value][]', $label_title,'trim|required|xss_clean');
			}
		}
		###

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();".$callback_default;
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if(isset($_POST['mtype']) && $_POST['mtype']=='business' && $joinform['bno_use']=='Y'){
			###
			$return_result = $this->bno_chk('re_chk');
			if(!$return_result['return']){
				$callback = "if(parent.document.getElementsByName('bno')[0]) parent.document.getElementsByName('bno')[0].focus();".$callback_default;
				openDialogAlert($return_result['return_result'],400,140,'parent',$callback);
				exit;
			}
		}


		# QA #53295 첫주문 시, 주문자 란에 입력기능이 없어지는 문제 - 생녕월일 선택조건 추가
		$birthday = $this->input->post('birthday');
		$now = date('Y-m-d');

		if (strtotime($birthday) > strtotime($now)) {
			$callback = "if(parent.document.getElementsByName('birthday')[0]) parent.document.getElementsByName('birthday')[0].focus();".$callback_default;
			openDialogAlert('생년월일을 확인해주세요.',400,140,'parent',$callback);
			exit;
		}
		// 아이디 == 패스워드 동일여부 검사 :: 2017-08-11 lwh
		if($_POST['password'] == $_POST['userid']){
			$text = getAlert('mb063');
			$callback = "if(parent.document.getElementsByName('password')[0]) parent.document.getElementsByName('password')[0].focus();".$callback_default;
			openDialogAlert($text,400,140,'parent',$callback);
			exit;
		}

		if($joinform['recommend_use']=='Y'){
			if(trim($_POST['recommend']) == trim($_POST['userid'])){
				$callback = "if(parent.document.getElementsByName('recommend')[0]) parent.document.getElementsByName('recommend')[0].focus();".$callback_default;
				//본인아이디를 추천할 수 없습니다.
				openDialogAlert(getAlert('mb038'),400,140,'parent',$callback);
				exit;
			}

			if($_POST['recommend']){
				$this->db->where('userid', trim($_POST['recommend']));
				$query = $this->db->get("fm_member");
				$mem_chk = $query->result_array();
				if(!$mem_chk){
					$callback = "if(parent.document.getElementsByName('userid')[0]) parent.document.getElementsByName('userid')[0].focus();".$callback_default;
					//존재하지 않는 추천인ID입니다.
					openDialogAlert(getAlert('mb039'),400,140,'parent',$callback);
					exit;
				}
			}
		}
		###
		###
		$return_result = $this->id_chk('re_chk');
		if(!$return_result['return']){
			$callback = "if(parent.document.getElementsByName('userid')[0]) parent.document.getElementsByName('userid')[0].focus();".$callback_default;
			openDialogAlert($return_result['return_result'],400,140,'parent',$callback);
			exit;
		}

		###
		$this->db->where('userid', $_POST['userid']);
		$query = $this->db->get("fm_member");
		$mem_chk = $query->result_array();
		if($mem_chk){
			$callback = "if(parent.document.getElementsByName('userid')[0]) parent.document.getElementsByName('userid')[0].focus();".$callback_default;
			//이미 등록된 아이디 입니다.
			openDialogAlert(getAlert('mb040'),400,140,'parent',$callback);
			exit;
		}
		
		###
		if($_POST['password'] != $_POST['re_password']){
			$callback = "if(parent.document.getElementsByName('required')[0]) parent.document.getElementsByName('required')[0].focus();".$callback_default;
			//비밀번호 확인이 일치하지 않습니다.
			openDialogAlert(getAlert('mb042'),400,140,'parent',$callback);
			exit;
		}

		// 만 14세 체크
		if(isset($_POST['birthday'])){
			$req_birth = str_replace('-','',$_POST['birthday']);
			$birthday = date("Ymd", strtotime( $req_birth ));
			$nowday =  date('Ymd');
			$age      = floor(($nowday - $birthday) / 10000);
		}

		/*
		만 14세 미만 가입 불가 설정 시
		*/
		if($_POST['mtype'] == 'member' && $joinform['kid_join_use'] == 'N' && $skin_patch_14years_old ){ ### 가입 불가
			if($age < 14){
				$callback = "parent.location.href = '../'";
				openDialogAlert(getAlert('mb262'),400,140,'parent',$callback);
				exit;
			}		
			$kid_auth = 'Y';
		}else if($_POST['mtype']=='member' && $joinform['kid_join_use'] == 'Y'){ ### 승인 후 가입
			if($age < 14){
				//14세 미만이므로 kid_auth = N
				$kid_auth = 'N';
			}else{
				$kid_auth = 'Y';
			}
		}else{ ### 제한 없음
			$kid_auth = $_POST['kid_auth'];
		}

		// 인증번호 체크
		$this->load->library('o2o/o2orequiredlibrary');
		unset($params_validate_auth_cellphone);
		$params_validate_auth_cellphone['cellphone']	= implode("-",$_POST['o2o_cellphone']);
		$params_validate_auth_cellphone['authnum']		= $_POST['authnum'];
		$o2o_merge_request								= ($_POST['o2omerge']=='y')?true:false;
		$this->o2orequiredlibrary->required_validate_auth_cellphone($params_validate_auth_cellphone, null, $o2o_merge_request);
		
		###
		$params = $this->input->post();
		$params['kid_auth']			= $kid_auth;
		$params['kid_agree']		= !empty($kid_agree) ? $kid_agree : null;
		$params['regist_date']		= date('Y-m-d H:i:s');
		$params['lastlogin_date']	= $params['regist_date'];
		$params['group_seq']		= '1';
		if(isset($params['phone']))  $params['phone'] = implode("-",$params['phone']);
		if(isset($params['cellphone']))  $params['cellphone'] = implode("-",$params['cellphone']);
		if(isset($params['zipcode']))  $params['zipcode'] = implode("",$params['zipcode']);
		if(isset($params['new_zipcode']))  $params['zipcode'] = $params['new_zipcode'];
		// password 는 암호화 하지 않고 전달-join_member함수내에서 암호화 처리
		// $params['password']	= hash('sha256',md5($_POST['password']));
		$params['marketplace'] = !empty($_COOKIE['marketplace']) ? $_COOKIE['marketplace'] : '';//유입매체
		$params['referer']			= $_COOKIE['shopReferer'];
		$params['referer_domain']	= $_COOKIE['refererDomain'];
		$platform	= 'P';
		if		($this->fammerceMode || $this->storefammerceMode)	$platform	= 'F';
		elseif	($this->_is_mobile_app_agent_android)		$platform	= 'APP_ANDROID';
		elseif	($this->_is_mobile_app_agent_ios)		$platform	= 'APP_IOS';
		elseif	($this->mobileMode || $this->storemobileMode)		$platform	= 'M';
		$params['platform']	= $platform;

		###
		$auth = $this->session->userdata('auth');
		if(isset($auth) && $auth['auth_yn']){
			$params['auth_type']	= $auth['namecheck_type'];
			$params['auth_code']	= $auth['namecheck_check'];
			if($params['auth_type'] != "safe"){//"ipin", "phone"

				/* 실명인증 중복 가입 체크 추가 leewh 2014-12-24 */
				$qry = "select count(*) as cnt from fm_member where auth_code='".$auth["namecheck_check"]."'";
				$query = $this->db->query($qry);
				$member = $query -> row_array();

				if($member["cnt"] > 0) {
					$callback = "parent.location.href = '/member/login?return_url=/mypage/myinfo';";
					//이미 가입된정보입니다. 로그인해주세요.
					$msg = getAlert('mb043');
					$this->session->unset_userdata('auth');
					if ($_SESSION['auth']) $_SESSION['auth']= '';
					openDialogAlert($msg,400,140,'parent',$callback);
					exit;
				}

				$params['auth_vno']		= $auth['namecheck_vno'];
			}else{
				$params['auth_vno']		= $auth['namecheck_key'];
			}
		}

		//초대
		$params['fb_invite']	= $this->session->userdata('fb_invite');

		$params['user_icon']	= ($_POST['user_icon'])?$_POST['user_icon']:1;//@2014-08-06 icon

		// 본인인증을 통해 가입했는지 확인 :: 2015-06-04 lwh
		$auth_intro = $this->session->userdata('auth_intro');
		if($auth_intro['auth_intro_yn'] == 'Y'){
			$params['adult_auth']	= 'Y';
		}

		###########################################################################
		## 2018.0.5.11 userapp : api_key 생성
		$params['api_key'] = $this->appmembermodel->create_api_key($_POST['userid']);
		//-->###########################################################################

		//$data = filter_keys($params, $this->db->list_fields('fm_member'));
		
		//마케팅 수신동의 변경  후 메일 발송 휴효기간 입력
		if (strtolower($params['mailing']) == 'y' || strtolower($params['sms']) == 'y') {
		    $marketingDate = date("Y-m-d H:i:s", strtotime("+2 year -1 day", time()));
		} else {
		    $marketingDate = "0000-00-00 00:00:00";
		}
		$params['marketing_agree_send_date'] = $marketingDate;
		
		$memberseq = null;
		$callback = "parent.location.href = '/main/index'";
		
		if($o2o_merge_request){
			// O2O 통합 처리
			$this->load->library('o2o/o2oservicelibrary');
			$memberseq = $this->o2oservicelibrary->merge_member_o2o($params);
		}
		
		// 회원 라이브러리 처리
		$this->load->library('memberlibrary');
		$memberJoinMsg['msg'] = '회원가입 중 문제가 발생했습니다.';
		if(empty($memberseq)){
			$memberJoinMsg = $this->memberlibrary->join_member($params);
			if($memberJoinMsg['memberseq']){
				$memberseq = $memberJoinMsg['memberseq'];
			}
		}else{
			$memberJoinMsg['msg'] = '온/오프라인 회원통합을 완료했습니다.';
		}

		if($memberseq){
			$this->db->where('member_seq', $memberseq);
			$query = $this->db->get("fm_member");
			$member_check = $query->result_array();
			$callback = "parent.location.href = '/member/register_ok'";

			if($member_check[0]['kid_auth'] == 'N' && $skin_patch_14years_old){ //만 14세 미만 체크
				### SESSION
				$this->session->set_userdata('kid_auth',$member_check[0]['kid_auth']);
				$callback = "parent.location.href = '/member/register_ok?kid_auth=N'";
			}else if( ($_POST['mtype'] != 'business' && $config_member['autoApproval']=='Y') 
				|| ($_POST['mtype'] == 'business' && $config_member['autoApproval_biz']=='Y')){ //자동승인인 경우

				if( $jcresult['code'] == 'success' ||  $jcresult['code'] == 'emoney_pay' ) {
					$common_msg['jcresult_msg'] = $jcresult['msg'];
				}
			}
		}

		openDialogAlert($memberJoinMsg['msg'],400,140,'parent',$callback);
		exit;		
	}

	/**
	*
	* @
	*/
	public function create_member_session($data=array()){

		$this->load->helper('member');
		create_member_session($data);
		/**
		$data['rute'] = ($data['rute']!='f' && $data['sns_f'])?'facebook':$data['rute'];

		// 사업자 회원일 경우 업체명->이름
		if($data['business_seq']){
			$data['user_name'] = $data['bname'];
		}
		$member_data = array(
			'member_seq'		=> $data['member_seq'],
			'userid'			=> $data['userid'],
			'user_name'			=> $data['user_name'],
			'birthday'			=> $data['birthday'],
			'sex'				=> $data['sex'],
			'rute'				=> substr($data['rute'],0,1)
		);
		$tmp = config_load('member');
		if(isset($tmp['sessLimit']) && $tmp['sessLimit']=='Y'){
			$limit = 60 * $tmp['sessLimitMin'];
			$this->session->sess_expiration = $limit;
		}
		$this->session->set_userdata(array('user'=>$member_data));
		**/
	}

	### 회원정보 수정 비밀번호 재확인 :: 2016-04-19 lwh
	public function myinfo_pwchk(){
		$this->load->model('ssl');
		$this->ssl->decode();

		//비밀번호를 입력해주세요.
		if(!$_POST['pwchk']) { openDialogAlert(getAlert('mb160'),400,140,'parent',''); exit; }

		$query = "select password(?) pass";
		$query = $this->db->query($query,array($_POST['pwchk']));
		$data = $query->row_array();

		$member_config = config_load('member');
		$passwordId = ($member_config['passwordid'])?$member_config['passwordid'] : "";

		$str_md5 = md5($_POST['pwchk']);
		$str_sha	=	hash('sha256',$_POST['pwchk']);
		$str_password = $data['pass'];
		$str_sha_md5 = hash('sha256',$str_md5);
		$str_sha_password = hash('sha256',$data['pass']);
		$str_sha_newpassword = hash('sha512', md5($_POST['pwchk']).$passwordId.$this->userInfo['userid']);

		$query = "select count(*) cnt,member_seq from fm_member where member_seq=? and (`password`=? or `password`=? or `password`=? or `password`=? or `password`=? or `password`=?)";
		$query = $this->db->query($query,array($this->userInfo['member_seq'],$str_password,$str_md5,$str_sha,$str_sha_md5,$str_sha_password,$str_sha_newpassword));
		$data = $query->row_array();
		$count = $data['cnt'];

		if($count > 0){
			echo '
			<form method="post" name="form_chk" id="form_chk" action="../mypage/myinfo" target="_parent">
			<input type="hidden" name="pwchk" value="Y">
			<input type="hidden" name="chk_member_seq" value="'.trim($data['member_seq']).'">
			</form>
			<script>document.getElementById("form_chk").submit();</script>
			';
		}else{
			$callback = "parent.document.getElementById('registFrm').reset();";
			//비밀번호가 올바르지 않습니다.
			openDialogAlert(getAlert('mb161'),400,140,'parent',$callback);
			exit;
		}
	}

	### 휴대폰 인증 :: 2016-04-19 lwh
	public function authphone(){
		$this->load->model('membermodel');
		$member		= $this->membermodel->get_member_data($this->userInfo['member_seq']);
		$auth_cnt	= 1;
		$sendresult	= false;

		//잘못된 접근입니다.
		if(!$member['member_seq']) { echo getAlert('mb066'); exit;}

		
		$phone		= $this->input->get('phone');
		
		// cellphone 조합
		$array_cellphone = $this->input->get('cellphone');
		
		if(count($array_cellphone)>0) {
		    foreach($array_cellphone as $k=>&$v){
		        $v = base64_decode($v);
		    }
		    $cellphone = implode("-",$array_cellphone);
		} else {
		    $cellphone = $phone;
		    if(  preg_match( '/^(\d{3})(\d{4})(\d{4})$/', $phone,  $matches ) )
		    {
		        if(count($matches)===4) {
		            $cellphone = $matches[1] . '-' .$matches[2] . '-' . $matches[3];
		        }
		    }
		    
		}
		
		// 중복회원 가입 확인 cellphone
		unset($params_member);
		$params_member['cellphone'] = $cellphone;
		$this->load->library('o2o/o2orequiredlibrary');
		$this->o2orequiredlibrary->required_block_duplicate_cellphone_member_o2o($params_member);
		
		if($member['phone_auth']){
			$phoneAuth	= explode('|',$member['phone_auth']);
			$auth_cnt	= $phoneAuth[0];
			$auth_date	= $phoneAuth[1];

			if($auth_cnt >= 3 && date('Ymd') == $auth_date){
				//1일 제한횟수를 모두 사용하셨습니다.
				$msg = getAlert('mb067');
				$result = array("result"=>$sendresult, "msg"=>$msg);
				echo json_encode($result);
				exit;
			}

			if(date('Ymd') == $auth_date){
				$auth_cnt = $auth_cnt + 1;
			}else{
				$auth_cnt = 1;
			}
		}

		
		$config		= config_load('member','confirmsendmsg');
		$sendMsg	= $config['confirmsendmsg'];
		$authnum	= rand(10000,99999);

		$sendMsg	= str_replace("{shopname}", $this->config_basic['shopName'], $sendMsg);
		$sendMsg	= str_replace("{phonecertify}", $authnum, $sendMsg);

		$params['msg'] = trim($sendMsg);
		$commonSmsData['member']['phone'] = $phone;
		$commonSmsData['member']['params'] = $params;

		$result = commonSendSMS($commonSmsData);
		if($result['code'] == 0000){
			// 발송횟수 저장
			$this->membermodel->set_member_authphone($auth_cnt,$this->userInfo['member_seq']);

			// 인증번호 세션
			$auth_phone = array('authnum'=>$authnum,'phone'=>$phone);
			$this->session->sess_expiration = (60 * 3);
			$this->session->set_userdata('auth_phone',$auth_phone);

			//발송되었습니다. 3분이내 입력하시기바랍니다.
			$msg = getAlert('mb068');
			$sendresult = true;
		}else{
			//발송에 실패하였습니다. 새로고침 후 시도해주세요.
			$msg = getAlert('mb069');
		}

		$result = array("result"=>$sendresult, "msg"=>$msg);
		echo json_encode($result);
	}

	### 휴대폰 인증 세션 삭제 :: 2016-04-25 lwh
	public function authphone_del(){
		$this->session->unset_userdata('auth_phone');
		echo 'ok';
	}

	### 휴대폰 인증 :: 2016-04-19 lwh
	public function authphone_confirm(){
		$auth_phone = $this->session->userdata('auth_phone');
		if(!$auth_phone['authnum']){
			//인증번호 발송 후 입력해주세요.
			echo "<script>alert('".getAlert('mb070')."');</script>";
			exit;
		}
		if(!$_GET['authnum']){
			//인증번호를 입력해주세요.
			echo "<script>alert('".getAlert('mb071')."');</script>";
			exit;
		}
		if($auth_phone['authnum'] == $_GET['authnum']){
			$this->session->unset_userdata('auth_phone');
			echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
			//인증되었습니다.
			echo '<script>
				var phone = "";
				var ptype = $("#phonetype",parent.document).val();
				$.each($("input[name=\'chg_phone[]\']",parent.document),function(){ phone += $(this).val(); });
				if(phone == "'.$auth_phone['phone'].'"){
					alert("'.getAlert('mb072').'");
					$.each($("input[name=\'chg_phone[]\']",parent.document),function(idx){
						$("input[name=\'"+ptype+"[]\']",parent.document).eq(idx).val($(this).val());
					});
					if(typeof parent.removeCenterLayer === "function" && typeof parent.document.getElementById("authphone") === "object" ) {
                        parent.removeCenterLayer("#authphone");
                    } 
                    if(typeof parent.closeDialog === "function" && typeof parent.document.getElementById("authphone") === "object" ) {
                        parent.closeDialog("authphone");
                    }
					$(".chg_phone",parent.document).attr(\'disabled\',false);
				}
			</script>';
		}else{
			//인증번호가 일치하지 않습니다.
			echo '<script>alert("'.getAlert('mb073').'");</script>';
		}
	}

	/**
	 * [공통]휴대폰 번호로 인증번호 전송
	 */
	public function certify_cellphone() {
		$this->load->library('memberlibrary');
		$cellphone = $this->input->get('cellphone');
		if(!$cellphone) {
			$result = array("result"=>false, "msg"=>getAlert('mb066'));
			echo json_encode($result);
			exit;
		}

		// 실제 sms 발송
		$result = $this->memberlibrary->send_certify_cellphone($cellphone);
		echo json_encode($result);
		exit;
	}

	/**
	 * [공통] 휴대폰번호로 발송된 인증번호 확인
	 */
	public function certify_confirm() {
		$phonecertify = $this->input->post('phonecertify');
		if(!$phonecertify) {
			//인증번호를 입력해주세요.
			openDialogAlert(getAlert('mb071'),400,140,'parent',$callback);
			exit;
		}

		$this->load->library('memberlibrary');
		$certify_confirm = $this->memberlibrary->certify_confirm($this->input->post());
		if($certify_confirm['result']){
			//인증되었습니다.
			$callback = "parent.certify_cellphone.complete();";
			openDialogAlert(getAlert('mb072'),400,140,'parent',$callback);
			exit;
		}else{
			//인증번호가 일치하지 않습니다.
			openDialogAlert($certify_confirm['msg'],400,140,'parent',$callback);
			exit;
		}
	}

	/**
	 * [공통] 휴대폰 인증 세션 삭제
	 */
	public function certify_del() {
		$this->load->library('memberlibrary');
		$this->memberlibrary->unset_certify_cellphone();
	}

	###
	public function myinfo_modify(){

		$this->load->model('ssl');
		$this->ssl->decode();

		if($_POST['seq']!=$this->userInfo['member_seq']){
			 $returnMsg = getAlert('et018');//잘못된 접근입니다.
			openDialogAlert($returnMsg,400,140,'parent',$callback);
			exit;
		}

		$this->mdata = $this->membermodel->get_member_data($this->userInfo['member_seq']);//회원정보


		if( $this->isdemo['isdemo'] && $this->mdata['userid'] == $this->isdemo['isdemoid'] ){
			openDialogAlert($this->isdemo['msg'],500,140,'parent',$callback);
			exit;
		}

		$joinform = config_load('joinform');
		###
		$mtype = 'member';
		if($this->mdata['business_seq']){
			$mtype = 'business';
		}
		###

		$label_pr = $_POST['label'];
		$label_sub_pr = $_POST['labelsub'];
		$label_required = $_POST['required'];

		### Validation
		if( $mtype == 'member' ) {
			//$this->validation->set_rules('user_name', '이름','trim|required|max_length[32]|xss_clean');
		}
		if(!empty($_POST['anniversary'][0]) && !empty($_POST['anniversary'][1]))
			$_POST['anniversary'] = implode("-",$_POST['anniversary']);
		else
			$_POST['anniversary'] = '';

		if(isset($_POST['email'])) $_POST['email'] = implode("@",$_POST['email']);
		if($_POST['email'] == '@' ) $_POST['email'] = '';

		if ( $joinform['email_userid'] == 'Y' && !$_POST['email'] ) {
			$_POST['email'] = $_POST['userid'];
		}

		if( is_array($_POST['births']) ) {
			if( $_POST['births'][0] && $_POST['births'][1] && $_POST['births'][2]) {
				$_POST['birthday'] =  $_POST['births'][0].'-'.str_pad($_POST['births'][1],2 ,"0", STR_PAD_LEFT).'-'.str_pad($_POST['births'][2],2 ,"0", STR_PAD_LEFT);
			}
		}else{
			if($_POST['births']){
				$_POST['birthday'] = $_POST['births'];
			}else{
				$_POST['birthday'] = $_POST['birthday'] ? $_POST['birthday'] : '';
			}
		}

		if(isset($_POST['zipcode']) && !isset($_POST['new_zipcode'])){
			$_POST['new_zipcode'] = implode('',$_POST['zipcode']);
			unset($_POST['zipcode']);

		}
		if(isset($_POST['bzipcode']) && !isset($_POST['new_bzipcode'])){
			$_POST['new_bzipcode'] = implode('',$_POST['bzipcode']);
			unset($_POST['bzipcode']);
		}

		// 비밀번호 유효성 체크
		$pre_enc_password = $this->mdata['password'];
		$enc_password = hash('sha256',md5($_POST['new_password']));

		$check_password = $_POST['new_password'];
		$password_params = array(
			'birthday'                => array($_POST['birthday'], $_POST['anniversary']),
			'phone'                   => array(implode("-",$_POST['phone']), implode("-",$_POST['bphone'])),
			'cellphone'                   => array(implode("-",$_POST['cellphone']), implode("-",$_POST['bcellphone'])),
			'pre_enc_password'        => $pre_enc_password,
			'enc_password'            => $enc_password,
		);
		$this->load->library('memberlibrary');
		$result = $this->memberlibrary->check_password_validation($check_password, $password_params);
		if($result['code'] != '00' && $result['alert_code']){
			$member_config = config_load('member');
			if ( $member_config['confirmPhone'] ) {
				// 본인인증 휴대폰번호 수정 :: 2016-04-19 pjw
				echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
				echo "<script>$(\"input[name='bcellphone[]']\",parent.document).attr(\"disabled\",true);
				$(\"input[name='cellphone[]']\",parent.document).attr(\"disabled\",true);</script>";
			}
			openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback);
			exit;
		}

		if( $this->mdata['rute'] == 'none' ) {
			//비밀번호
			$this->validation->set_rules('old_password', getAlert('mb012'),'trim|required|max_length[32]|xss_clean');
		}

		### MEMBER
		if($mtype=='member'){

			if($joinform['email_use']=='Y'){
				if($joinform['email_required']=='Y') {
					//이메일
					$this->validation->set_rules('email', getAlert('mb015'),'trim|required|max_length[64]|valid_email|xss_clean');
				}elseif( !empty($_POST['email'])) {
					$this->validation->set_rules('email', getAlert('mb015'),'trim|max_length[64]|valid_email|xss_clean');
				}
			}

			if($joinform['user_name_use']=='Y'){
				//이름
				if($joinform['user_name_required']=='Y') $this->validation->set_rules('user_name', getAlert('mb016'),'trim|required|max_length[20]|string|xss_clean');
				else $this->validation->set_rules('user_name', getAlert('mb016'),'trim|max_length[20]|string|xss_clean');
			}
			if($joinform['phone_use']=='Y'){
				//연락처
				if($joinform['phone_required']=='Y') $this->validation->set_rules('phone[]', getAlert('mb017'),'trim|required|max_length[4]|numeric|xss_clean');
				else $this->validation->set_rules('phone[]', getAlert('mb017'),'trim|max_length[4]|xss_clean');
			}
			if($joinform['cellphone_use']=='Y'){
				//휴대폰번호
				if($joinform['cellphone_required']=='Y') $this->validation->set_rules('cellphone[]', getAlert('mb018'),'trim|required|max_length[4]|numeric|xss_clean');
				else  $this->validation->set_rules('cellphone[]', getAlert('mb018'),'trim|max_length[4]|xss_clean');
			}
			if($joinform['address_use']=='Y'){
				if($joinform['address_required']=='Y'){
					//우편번호
					$this->validation->set_rules('new_zipcode', getAlert('mb019'),'trim|required|max_length[7]|xss_clean');
					//주소
					$this->validation->set_rules('address', getAlert('mb020'),'trim|required|max_length[100]|string|xss_clean');
					//상세 주소
					$this->validation->set_rules('address_detail', getAlert('mb021'),'trim|max_length[100]|string|xss_clean');
				}
				else{
					$this->validation->set_rules('new_zipcode', getAlert('mb019'),'trim|max_length[7]|xss_clean');
					$this->validation->set_rules('address', getAlert('mb020'),'trim|max_length[100]|string|xss_clean');
					$this->validation->set_rules('address_detail', getAlert('mb021'),'trim|max_length[100]|string|xss_clean');
				}
			}
			if($joinform['birthday_use']=='Y'){
				//생일
				if($joinform['birthday_required']=='Y') $this->validation->set_rules('birthday', getAlert('mb022'),'trim|required|max_length[10]|xss_clean');
				else  $this->validation->set_rules('birthday', getAlert('mb022'),'trim|max_length[10]|xss_clean');
			}
			if($joinform['anniversary_use']=='Y'){
				//기념일
				if($joinform['anniversary_required']=='Y') $this->validation->set_rules('anniversary', getAlert('mb023'),'trim|required|max_length[5]|xss_clean');
				else  $this->validation->set_rules('anniversary', getAlert('mb023'),'trim|max_length[5]|xss_clean');
			}
			if($joinform['nickname_use']=='Y'){
				//닉네임
				if($joinform['nickname_required']=='Y') $this->validation->set_rules('nickname', getAlert('mb024'),'trim|required|max_length[10]|string|xss_clean');
				else  $this->validation->set_rules('nickname', getAlert('mb024'),'trim|max_length[10]|string|xss_clean');
			}
			if($joinform['sex_use']=='Y'){
				//성별
				if($joinform['sex_required']=='Y') $this->validation->set_rules('sex', getAlert('mb025'),'trim|required|max_length[6]|xss_clean');
				else  $this->validation->set_rules('sex', getAlert('mb025'),'trim|max_length[6]|xss_clean');
			}
		}

		### BUSINESS
		if($mtype=='business'){
			if($joinform['bemail_use']=='Y'){
				if($joinform['bemail_required']=='Y') {
					//이메일
					$this->validation->set_rules('email', getAlert('mb015'),'trim|required|max_length[64]|valid_email|xss_clean');
				}elseif( !empty($_POST['email']) ) {
					$this->validation->set_rules('email', getAlert('mb015'),'trim|max_length[64]|valid_email|xss_clean');
				}
			}

			if($joinform['bname_use']=='Y'){
				//업체명
				if($joinform['bname_required']=='Y') $this->validation->set_rules('bname', getAlert('mb026'),'trim|required|max_length[20]|string|xss_clean');
				else  $this->validation->set_rules('bname', getAlert('mb026'),'trim|max_length[20]|string|xss_clean');
			}
			if($joinform['bceo_use']=='Y'){
				//대표자명
				if($joinform['bceo_required']=='Y') $this->validation->set_rules('bceo', getAlert('mb027'),'trim|required|max_length[32]|string|xss_clean');
				else  $this->validation->set_rules('bceo', getAlert('mb027'),'trim|max_length[32]|string|xss_clean');
			}

			if($joinform['bno_use']=='Y'){
				//사업자 등록번호
				if($joinform['bno_required']=='Y') $this->validation->set_rules('bno', getAlert('mb028'),'trim|required|max_length[12]|xss_clean');
				else  $this->validation->set_rules('bno', getAlert('mb028'),'trim|max_length[12]|xss_clean');
			}
			if($joinform['bitem_use']=='Y'){
				if($joinform['bitem_required']=='Y') {
					//업태
					$this->validation->set_rules('bitem', getAlert('mb029'),'trim|required|max_length[40]|string|xss_clean');
					//종목
					$this->validation->set_rules('bstatus', getAlert('mb030'),'trim|required|max_length[40]|string|xss_clean');
				}
				else{
					$this->validation->set_rules('bitem', getAlert('mb029'),'trim|max_length[40]|string|xss_clean');
					$this->validation->set_rules('bstatus', getAlert('mb030'),'trim|max_length[40]|string|xss_clean');
				}
			}
			if($joinform['badress_use']=='Y'){
				if($joinform['badress_required']=='Y'){
					//우편번호
					$this->validation->set_rules('new_bzipcode', getAlert('mb019'),'trim|required|max_length[7]|xss_clean');
					//주소
					$this->validation->set_rules('baddress', getAlert('mb020'),'trim|required|max_length[100]|string|xss_clean');
					//상세 주소
					$this->validation->set_rules('baddress_detail', getAlert('mb021'),'trim|max_length[100]|string|xss_clean');
				}
				else{
					$this->validation->set_rules('new_bzipcode', getAlert('mb019'),'trim|max_length[7]|xss_clean');
					$this->validation->set_rules('baddress', getAlert('mb020'),'trim|max_length[100]|string|xss_clean');
					$this->validation->set_rules('baddress_detail', getAlert('mb021'),'trim|max_length[100]|string|xss_clean');
				}
			}
			if($joinform['bperson_use']=='Y'){
				//담당자 명
				if($joinform['bperson_required']=='Y') $this->validation->set_rules('bperson', getAlert('mb031'),'trim|required|max_length[32]|string|xss_clean');
				else  $this->validation->set_rules('bperson', getAlert('mb031'),'trim|max_length[32]|string|xss_clean');
			}
			if($joinform['bpart_use']=='Y'){
				//담당자 부서명
				if($joinform['bpart_required']=='Y') $this->validation->set_rules('bpart', getAlert('mb033'),'trim|required|max_length[32]|string|xss_clean');
				else  $this->validation->set_rules('bpart', getAlert('mb033'),'trim|max_length[32]|string|xss_clean');
			}
			if($joinform['bphone_use']=='Y'){
				//전화번호
				if($joinform['bphone_required']=='Y') $this->validation->set_rules('bphone[]', getAlert('mb034'),'trim|required|max_length[4]|numeric|xss_clean');
				else $this->validation->set_rules('bphone[]', getAlert('mb034'),'trim|max_length[4]|xss_clean');
			}
			if($joinform['bcellphone_use']=='Y'){
				//휴대폰번호
				if($joinform['bcellphone_required']=='Y') $this->validation->set_rules('bcellphone[]', getAlert('mb018'),'trim|required|max_length[4]|numeric|xss_clean');
				else  $this->validation->set_rules('bcellphone[]', getAlert('mb018'),'trim|max_length[4]|xss_clean');
			}
		}

		//넘어온 추가항목 seq
		foreach($label_pr as $l => $data){$label_arr[]=$l;}
		//추가항목 공백체크
		foreach($label_required as $v){
			if(!in_array($v,$label_arr)){
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				//체크된 항목은 필수항목입니다.
				openDialogAlert(getAlert('mb074'),400,140,'parent',$callback);
				exit;
			}else{
				$query = $this->db->get_where('fm_joinform',array('joinform_seq'=> $v));
				$form_result = $query -> row_array();
				$label_title = $form_result['label_title'];
				$this->validation->set_rules('label['.$v.'][value][]', $label_title,'trim|required|xss_clean');
			}
		}

		if($this->validation->exec()===false){
			$member_config = config_load('member');
			if ( $member_config['confirmPhone'] ) {
				// 본인인증 휴대폰번호 수정 :: 2016-04-19 pjw
				echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
				echo "<script>$(\"input[name='bcellphone[]']\",parent.document).attr(\"disabled\",true);
				$(\"input[name='cellphone[]']\",parent.document).attr(\"disabled\",true);</script>";
			}

			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if($mtype=='business' && $joinform['bno_use']=='Y'){
			###
			$return_result = $this->bno_chk('re_chk');
			if(!$return_result['return'] && $this->mdata['bno'] != $_POST['bno']){
				$callback = "if(parent.document.getElementsByName('bno')[0]) parent.document.getElementsByName('bno')[0].focus();";
				openDialogAlert($return_result['return_result'],400,140,'parent',$callback);
				exit;
			}
		}


		###
		$params = $_POST;
		$seq	= $_POST['seq'];
		if( $_POST['rute'] == 'none' ) {

			$query = "select password(?) pass";
			$query = $this->db->query($query,array($_POST['old_password']));
			$data = $query->row_array();

			$member_config = config_load('member');
			$passwordId = ($member_config['passwordid'])?$member_config['passwordid'] : "";

			$str_md5 = md5($_POST['old_password']);
			$str_sha	=	hash('sha256',$_POST['old_password']);
			$str_password = $data['pass'];
			// $str_oldpassword = $data['old_pass'];
			$str_sha_md5 = hash('sha256',$str_md5);
			$str_sha_password = hash('sha256',$data['pass']);
			// $str_sha_oldpassword = hash('sha256',$data['old_pass']);
			$str_sha_newpassword = hash('sha512', md5($_POST['old_password']).$passwordId.$this->userInfo['userid']);
			$query = "select count(*) cnt from fm_member where member_seq=? and (`password`=? or `password`=? or `password`=? or `password`=? or `password`=? or `password`=? or `password`=?)";
			$query = $this->db->query($query,array($seq,$str_md5,$str_sha,$str_password,$str_oldpassword,$str_sha_md5,$str_sha_password,$str_sha_newpassword));

			$data = $query->row_array();
			$count = $data['cnt'];

			if($count<1){
				$callback = "if(parent.document.getElementsByName('old_password')[0]) parent.document.getElementsByName('old_password')[0].focus();";
				//기존 비밀번호가 올바르지 않습니다.
				openDialogAlert(getAlert('mb075'),400,140,'parent',$callback);
				exit;
			}
		}

		if(isset($_POST['phone'])) {
			$_POST['phone'] = array_filter($_POST['phone']);
			$params['phone'] = implode("-",$_POST['phone']);
		}
		if(isset($_POST['cellphone'])) {
			$_POST['cellphone'] = array_filter($_POST['cellphone']);
			$params['cellphone'] = implode("-",$_POST['cellphone']);
		}
		if(isset($_POST['new_zipcode']))  $params['zipcode'] = $_POST['new_zipcode'];
		if(isset($_POST['new_password']) && $_POST['new_password'])  $params['password'] = hash('sha256',md5($_POST['new_password']));
		$params['mailing'] = if_empty($params, 'mailing', 'n');
		$params['sms'] = if_empty($params, 'sms', 'n');
		$params['marketing_agree_send_date'] = date("Y-m-d H:i:s", time());

		// 기업회원일경우 이름 전달
		if(isset($mtype) && $mtype=='business'){
			$params['user_name']		= $params['bname']; 
			$params['address_type']		= $params['baddress_type'];
			$params['address']			= $params['baddress'];
			$params['address_detail']	= $params['baddress_detail'];
			$params['phone']			= implode("-",$params['bphone']); 
			$params['cellphone']		= implode("-",$params['bcellphone']);
		}

		$params['user_icon']	= if_empty($params, 'user_icon', '1');;//@2014-08-06 icon
		$data = filter_keys($params, $this->db->list_fields('fm_member'));
		//print_r($data);
		$result = $this->db->update('fm_member',$data,array('member_seq'=>$seq));
		###

		### BUSINESS CHK
		if($mtype=='business') {
			if(isset($_POST['bphone']))		$params['bphone']		= implode("-",$_POST['bphone']);
			if(isset($_POST['bcellphone']))	$params['bcellphone']	= implode("-",$_POST['bcellphone']);
			if(isset($_POST['new_bzipcode']))  $params['bzipcode'] = $_POST['new_bzipcode'];
			$params['baddress_type']	= $_POST['baddress_type'];
			$params['baddress']			= $_POST['baddress'];
			$params['baddress_street']	= $_POST['baddress_street'];
			$data = filter_keys($params, $this->db->list_fields('fm_member_business'));
			if($this->mdata['business_seq']){
				$this->db->where('business_seq', $this->mdata['business_seq']);
				$result = $this->db->update('fm_member_business', $data);
			}else{
				$data['member_seq'] = $seq;
				$result = $this->db->insert('fm_member_business', $data);
			}
		}

		### //추가정보 저장
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
				}
			}
		}
		###

		###
		// 회원정보 수정 시 이메일, 휴대폰, 연락처 빈값일 때 저장하지 않도록 수정 2016-11-14 by rhm
		$this->membermodel->update_private_encrypt($seq, $params);

		###
		if($result){
		    $email = config_load('email');
		    
		    //광고 수신 동의 정보 변경 메일
		    if ( ($this->mdata['mailing'] != strtolower($params['mailing']) || $this->mdata['sms'] != strtolower($params['sms']))
		        && $email["marketing_agree_status_user_yn"] == 'Y') {
	            $marketing_data = array();
	            $marketing_data['userid'] = $this->mdata['userid'];
	            
	            $update_date = date('Y년  m월 d일  H시 i분 ');
	            
	            if ($_POST['sms'] == 'Y') {
	                $marketing_data['sms_agree_status'] = "동의";
	                $marketing_data['sms_agree_date'] = $update_date;
	            } else {
	                $marketing_data['sms_agree_status'] = "거부";
	                $marketing_data['sms_agree_date'] = "-";
	            }
	            
	            if ($_POST['mailing'] == 'Y') {
	                $marketing_data['email_agree_status'] = "동의";
	                $marketing_data['email_agree_date'] = $update_date;
	            } else {
	                $marketing_data['email_agree_status'] = "거부";
	                $marketing_data['email_agree_date'] = "-";
	            }
	            
	            if ($params['email']) {
	                $to_email = $params['email'];
	            } else {
	                $to_email = $this->mdata['email'];
	            }
	            
	            $title    = "[".$this->config_basic['shopName']."] 수신동의변경 결과 안내";
	            $contents = sendMail($to_email, 'marketing_agree_status', $this->mdata['userid'], $marketing_data);
	            $contents = str_replace('\\','',http_src($contents));
	            
	            if(filter_var($to_email, FILTER_VALIDATE_EMAIL) != false){
	                $from     = $this->config_basic['companyEmail'];
	                $fromname = !$this->config_basic['shopName'] ? $this->config_basic['domain'] : $this->config_basic['shopName'];
	                
	                $this->email->from($from, $fromname);
	                $this->email->to($to_email);
	                $this->email->subject($title);
	                $this->email->message($contents);
	                $this->email->send();
	                $this->email->clear();
	            }
	            
	            $logParams = array();
	            $logParams['regdate']     = date('Y-m-d H:i:s');
	            $logParams['gb']          = 'AUTO';
	            $logParams['total']       = '1';
	            $logParams['to_email']    = $to_email;
	            $logParams['member_seq']  = $this->mdata['member_seq'];
	            $logParams['subject']     = $title;
	            $logParams['contents']    = $contents;
	            $logParams['order_seq']   = 0;
	            $logParams['memo']		  = 'marketing_agree_status';
	            
	            $result =  $this->db->insert('fm_log_email', $logParams);
	        }
		        
			unset($_POST);

			/*######################## 18.03.12 gcs userapp : 앱 처리 (정보 수정) s */

			if($this->mobileapp=='Y'){
			    $send_params = $this->appmembermodel->memberInfo();
				if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
					$auto_login = 'y';
				}else{
					$auto_login = 'n';
				}

				echo "<script>
					var param = {
										   member_seq : ".$send_params['member_seq'].",
										   user_id : '".$send_params['user_id']."',
										   user_name : '".$send_params['user_name']."',
										   session_id : '".session_id()."',
										   channel : '".$send_params['channel']."',
										   reserve : '".$send_params['reserve']."',
										   balance : '".$send_params['balance']."',
										   coupon : '".$send_params['coupon']."',
										   auto_login : '".$auto_login."',
										   api_key : '".$send_params['api_key']."'
									  };
					var strParam = JSON.stringify(param);

					var dataStr = 'MemberInfo?' + strParam; ";

				if($this->m_device=='iphone') {
					echo "window.webkit.messageHandlers.CSharp.postMessage(dataStr);";
				}else{
					echo "CSharp.postMessage(dataStr);";
				}
				echo "</script>";
			}

			/*######################## 18.03.12 gcs userapp : 앱 처리 (정보 수정) e */

			$callback = "parent.location.href = '../mypage'";
			//수정 되었습니다.
			openDialogAlert(getAlert('mb078'),400,140,'parent',$callback);
		}
	}


	###
	public function withdrawal(){
		//탈퇴사유
		$this->validation->set_rules('reason', getAlert('mb196'),'trim|required|max_length[30]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		if( $this->isdemo['isdemo'] && $data['userid'] == $this->isdemo['isdemoid'] ){
			openDialogAlert($this->isdemo['msg'],500,140,'parent',$callback);
			exit;
		}

		$data = $this->membermodel->get_member_data($this->userInfo['member_seq']);
		### withdrawal_insert
		$params = $this->input->post();
		$params['member_seq']	= $this->userInfo['member_seq'];
		$params['regist_date']	= date('Y-m-d H:i:s');
		$params['regist_ip']	= $_SERVER['REMOTE_ADDR'];
		$params['user_name']	= $this->userInfo['user_name'];
		$this->load->library('memberlibrary');
		//회원탈퇴
		$withdrawalMsg = $this->memberlibrary->set_withdrawal($params);
		###
		$withdrawalMsg['msg'] = "이미 회원 탈퇴가 이뤄졌습니다.";
		if($withdrawalMsg['result']) {
			$commonSmsData = array();
			$commonSmsData['withdrawal']['phone'][] = $data['cellphone'];
			$commonSmsData['withdrawal']['params'][] = $params;
			$commonSmsData['withdrawal']['mid'][] = $data['userid'];
			commonSendSMS($commonSmsData);
			sendMail($data['email'], 'withdrawal', $data['userid'], $params);
			$withdrawalMsg['msg'] = getAlert('mb197');
		}
		### logout
		$callback = "parent.location.href = '../login_process/logout'";
		openDialogAlert($withdrawalMsg['msg'],400,140,'parent',$callback);
	}

/**
** 본인인증/안심체크/아이핀 실명인증 체크 관련
**/
	public function niceid2_return(){

		$realname = config_load('realname');
		$auth = $this->session->userdata('auth');
		$findtypess = $this->session->userdata('findtypess');
		$findidss = $this->session->userdata('findidss');

		if(!extension_loaded('CPClient')) {
			dl('CPClient.' . PHP_SHLIB_SUFFIX);
		}
		$module = 'CPClient';


		//**************************************** 필수 수정값 ***************************************************************************
		$sSiteCode 	   = $realname['realnameId'];							// 안심체크 사이트 코드
		$sSitePassword = $realname['realnamePwd'];							// 안심체크 사이트 패스워드
		$sIPINSiteCode = $realname['ipinSikey'];							// 아이핀사이트 코드
		$sIPINPassword = $realname['ipinKeyString'];						// 아이핀사이트 패스워드
		//$sReturnURL = $_SERVER["HTTP_HOST"]."/member/niceid2_return";		//결과 수신 : full URL 입력
		$cb_encode_path = $_SERVER["DOCUMENT_ROOT"]."/namecheck/CPClient";	// 암호화 프로그램의 위치 (절대경로+모듈명)_Linux ..

		//*************************************************************************8******************************************************

		$enc_data = $this->input->post_get("enc_data");								// NICE신용평가정보로부터 받은 사용자 암호화된 결과 데이타

		///////////////////////////////////////////////// 문자열 점검///////////////////////////////////////////////
		if(preg_match('~[^0-9a-zA-Z+/=]~', $enc_data, $match)) {echo "입력 값 확인이 필요합니다"; exit;}
		if(base64_encode(base64_decode($enc_data))!=$enc_data) {echo "입력 값 확인이 필요합니다"; exit;}
		///////////////////////////////////////////////////////////////////////////////////////////////////////////

		if ($enc_data != "") {

			$function = 'get_decode_data';
			if (extension_loaded($module)) {
				$plaindata = $function($sSiteCode,$sSitePassword, $enc_data);
			} else {
				$plaindata = "Module get_response_data is not compiled into PHP";
			}

			if ($plaindata == -1){
				//암/복호화 시스템 오류
				$returnMsg  = getAlert('mb166');
			}else if ($plaindata == -4){
				//복호화 처리 오류
				$returnMsg  = getAlert('mb167');
			}else if ($plaindata == -5){
				//HASH값 불일치 - 복호화 데이터는 리턴됨
				$returnMsg  = getAlert('mb168');
			}else if ($plaindata == -6){
				//복호화 데이터 오류
				$returnMsg  = getAlert('mb169');
			}else if ($plaindata == -9){
				//입력값 오류
				$returnMsg  = getAlert('mb170');
			}else if ($plaindata == -12){
				//사이트 비밀번호 오류
				$returnMsg  = getAlert('mb173');
			}else{

				// 복호화가 정상적일 경우 데이터를 파싱합니다.
				//본인인증이 확인되었습니다.
				$returnMsg  = getAlert('mb174');

				$sRequestNO = GetValueNameCheck($plaindata , "REQ_SEQ");
				$sResult = GetValueNameCheck($plaindata , "NC_RESULT");

				if(strcmp($_SESSION["REQ_SEQ"] , $sRequestNO) )
				{
					$sRequestNO = "";
					//세션값이 다릅니다. 올바른 경로로 접근하시기 바랍니다.
					$err_msg = getAlert('mb175');
					pageClose($err_msg);
					exit;
				}else{

					$auth_data["auth_yn"] = "Y";
					$auth_data["namecheck_type"] = "safe";
					$auth_data["namecheck_name"] = iconv("euc-kr", "utf-8", GetValueNameCheck($plaindata , "NAME"));
					$auth_data['namecheck_name'] = check_member_name($auth_data['namecheck_name']);
					$auth_data["namecheck_sex"] = iconv("euc-kr", "utf-8", GetValueNameCheck($plaindata , "GENDER"));
					$auth_data["namecheck_birth"] = iconv("euc-kr", "utf-8", GetValueNameCheck($plaindata , "BIRTHDATE"));
					$auth_data["namecheck_key"] = iconv("euc-kr", "utf-8", GetValueNameCheck($plaindata , "SAFEID"));
					$auth_data["namecheck_check"] = iconv("euc-kr", "utf-8", GetValueNameCheck($plaindata , "IPIN_DI"));
					$auth_data["namecheck_vno"] = iconv("euc-kr", "utf-8", GetValueNameCheck($plaindata , "VNO_NUM"));

					if(isset($_GET['intro']) && $_GET['intro']=='Y') {//성인인증페이지
						if($auth_data["namecheck_birth"]){
							$adult = date("Y") - substr($auth_data["namecheck_birth"], 0, 4) + 1;
						}
						if($adult>19){
							$auth_intro_data = array('auth_intro_type'=>'auth', 'auth_intro_yn'=>'Y');
							$this->session->sess_expiration = (60 * 5);
							$this->session->set_userdata(array('auth_intro'=>$auth_intro_data));
							// 성인인증 로그 :: 2015-03-13 lwh
							$this->adult_log('namecheck');
							//"성인인증이 성공적으로 완료되었습니다."
							$msg = getAlert('mb083');

							if($_GET['type']=='join'){
								$qry = "select count(*) as cnt from fm_member where auth_code='".$auth_data["namecheck_check"]."'";
								$query = $this->db->query($qry);
								$member = $query -> row_array();

								if($member["cnt"] > 0){
									$url = "/member/login?return_url=" . urlencode("/mypage/myinfo");
									//이미 가입된 정보입니다.
									$msg = getAlert('mb176');
									pageLocation($url, $msg, 'opener');
									pageClose();
									exit;
								}

								$this->session->sess_expiration = (60 * 5);
								$this->session->set_userdata(array('auth'=>$auth_data));
								$_GET['return_url'] = '/member/agreement?authok=1';
							}
						}else{
							//미성년자는 이용할 수 없습니다.
							$err_msg = getAlert('mb177');
							pageClose($err_msg);
							exit;
						}

						$return_url = ($_GET['return_url']) ? $_GET['return_url'] : '/main';
						pageLocation($return_url, $msg, 'opener');
						pageClose();
						exit;

					}elseif(isset($_GET['findidpw']) && $_GET['findidpw']=='Y') {//아이디/패스워드 찾기
						$this->_findidpwresult($auth_data, $plaindata);
					}else{//가입페이지

						$qry = "select count(*) as cnt from fm_member where auth_code='".$auth_data["namecheck_check"]."'";
						$query = $this->db->query($qry);
						$member = $query -> row_array();

						if($member["cnt"] > 0){
							$url = "/member/login?return_url=" . urlencode("/mypage/myinfo");
							//이미 가입된 정보입니다.
							$msg = getAlert('mb176');
							pageLocation($url, $msg, 'opener');
							pageClose();
							exit;
						}


						$this->session->sess_expiration = (60 * 5);
						$this->session->set_userdata(array('auth'=>$auth_data));

						pageLocation('/member/agreement?authok=1', "", 'opener');
						pageClose();
						exit;
					}
				}
			}

			//"잠시 후 다시 시도하여주십시오.<br/>오류가 계속 될 경우 고객센터로 문의하세요."
			$msg = getAlert('mb178');
			pageClose($msg);
			exit;
		}
	}

	public function niceid_phone_return(){

		$realname = config_load('realname');
		$joinform = config_load('joinform');
		$auth = $this->session->userdata('auth');
		$findtypess = $this->session->userdata('findtypess');
		$findidss = $this->session->userdata('findidss');

		if(!extension_loaded('CPClient')) {
			dl('CPClient.' . PHP_SHLIB_SUFFIX);
		}
		$module = 'CPClient';


		//**************************************** 필수 수정값 ***************************************************************************
		$sSiteCode 				= $realname['realnamephoneSikey'];			// 본인인증 사이트 코드
		$sSitePassword		= $realname['realnamePhoneSipwd'];			// 본인인증 사이트 패스워드
		$authtype = "M";      	// 없으면 기본 선택화면, X: 공인인증서, M: 핸드폰, C: 카드
		$popgubun 	= "Y";		//Y : 취소버튼 있음 / N : 취소버튼 없음
		$customize 	= "";			//없으면 기본 웹페이지 / Mobile : 모바일페이지

		//$cb_encode_path	= $_SERVER["DOCUMENT_ROOT"]."/namecheck/CPClient";	// 암호화 프로그램의 위치 (절대경로+모듈명)_Linux ..
		//$sType			= "REQ";
		//$reqseq = `$cb_encode_path SEQ $sSiteCode`;

		//$returnurl		= "http://".$_SERVER["HTTP_HOST"]."/member_process/niceid_phone_return";	// 성공시 이동될 URL
		//$errorurl		= "http://".$_SERVER["HTTP_HOST"]."/member_process/niceid_phone_return";		// 실패시 이동될 URL

		//*************************************************************************8******************************************************

		$enc_data = $this->input->post_get("EncodeData");		// 암호화된 결과 데이타
		$sReserved1 = $this->input->post_get('param_r1');
		$sReserved2 = $this->input->post_get('param_r2');
		$sReserved3 = $this->input->post_get('param_r3');

		//////////////////////////////////////////////// 문자열 점검///////////////////////////////////////////////
		if(preg_match('~[^0-9a-zA-Z+/=]~', $enc_data, $match)) {echo "입력 값 확인이 필요합니다 : ".$match[0]; exit;} // 문자열 점검 추가.
		if(base64_encode(base64_decode($enc_data))!=$enc_data) {echo "입력 값 확인이 필요합니다"; exit;}

		if(preg_match("/[#\&\\+\-%@=\/\\\:;,\.\'\"\^`~\_|\!\/\?\*$#<>()\[\]\{\}]/i", $sReserved1, $match)) {echo "문자열 점검 : ".$match[0]; exit;}
		if(preg_match("/[#\&\\+\-%@=\/\\\:;,\.\'\"\^`~\_|\!\/\?\*$#<>()\[\]\{\}]/i", $sReserved2, $match)) {echo "문자열 점검 : ".$match[0]; exit;}
		if(preg_match("/[#\&\\+\-%@=\/\\\:;,\.\'\"\^`~\_|\!\/\?\*$#<>()\[\]\{\}]/i", $sReserved3, $match)) {echo "문자열 점검 : ".$match[0]; exit;}
		///////////////////////////////////////////////////////////////////////////////////////////////////////////


		if ($enc_data != "") {

			//$plaindata = `$cb_encode_path DEC $sSiteCode $sSitePassword $enc_data`;		// 암호화된 결과 데이터의 복호화
			$function = 'get_decode_data';// 암호화된 결과 데이터의 복호화
			if (extension_loaded($module)) {
				$plaindata = $function($sSiteCode, $sSitePassword, $enc_data);
			} else {
				$plaindata = "Module get_response_data is not compiled into PHP";
			}


			if ($plaindata == -1){
				//암/복호화 시스템 오류
				$returnMsg  = getAlert('mb166');
			}else if ($plaindata == -4){
				//복호화 처리 오류
				$returnMsg  = getAlert('mb167');
			}else if ($plaindata == -5){
				//HASH값 불일치 - 복호화 데이터는 리턴됨
				$returnMsg  = getAlert('mb168');
			}else if ($plaindata == -6){
				//복호화 데이터 오류
				$returnMsg  = getAlert('mb169');
			}else if ($plaindata == -9){
				//입력값 오류
				$returnMsg  = getAlert('mb170');
			}else if ($plaindata == -12){
				//사이트 비밀번호 오류
				$returnMsg  = getAlert('mb173');
			}else{
				//본인인증이 확인되었습니다.
				$returnMsg  = getAlert('mb174');

				// 복호화가 정상적일 경우 데이터를 파싱합니다.
 				//$ciphertime = `$cb_encode_path CTS $sSiteCode $sSitePassword $enc_data`;	// 암호화된 결과 데이터 검증 (복호화한 시간획득)

				$requestnumber	= GetValueNameCheck($plaindata , "REQ_SEQ");
				$responsenumber = GetValueNameCheck($plaindata , "RES_SEQ");
				$authtype		= GetValueNameCheck($plaindata , "AUTH_TYPE");
				$name			= GetValueNameCheck($plaindata , "NAME");
				$birthdate		= GetValueNameCheck($plaindata , "BIRTHDATE");
				$gender			= GetValueNameCheck($plaindata , "GENDER");
				$nationalinfo	= GetValueNameCheck($plaindata , "NATIONALINFO");	//내/외국인정보(사용자 매뉴얼 참조)
				$dupinfo		= GetValueNameCheck($plaindata , "DI");
				$conninfo		= GetValueNameCheck($plaindata , "CI");
				$errcode		= GetValueNameCheck($plaindata , "ERR_CODE");
				$phone_number	= GetValueNameCheck($plaindata , "MOBILE_NO");

				// 만 14세 미만 체크
				if($joinform['kid_join_use'] == 'N'){
					$req_birth = str_replace('-','',$birthdate);
					$birthday = date("Ymd", strtotime( $req_birth ));
					$nowday =  date('Ymd');
					$age      = floor(($nowday - $birthday) / 10000);

					if($age < 14){
						pageClose('고객님은 만 14세 미만 회원으로 쇼핑몰 회원가입이 불가합니다.');
						exit;
					}
				}

				if(strcmp($_SESSION["REQ_SEQ_P"], $requestnumber) != 0  || !$dupinfo)
				{

					$requestnumber		= "";
					$responsenumber		= "";
					$authtype			= "";
					$name				= "";
					$birthdate			= "";
					$gender				= "";
					$nationalinfo		= "";
					$dupinfo			= "";
					$conninfo			= "";

					//세션값이 다릅니다. 올바른 경로로 접근하시기 바랍니다.
					$msg = getAlert('mb175');
					pageClose($msg);
					exit;

				}else{

					/**
					echo "[실명확인결과 : ".$sResult."]<br>";
					echo "[이름 : ".iconv("euc-kr", "utf-8", $name)."]<br>";
					echo "[성별 : ".$gender."]<br>";
					echo "[생년월일 : ".$birthdate."]<br>";
					echo "[내/외국인정보 : ".$nationalinfo."]<br>";

					echo "[DI(64 byte) : ".$dupinfo."]<br>";
					echo "[CI(88 byte) : ".$conninfo."]<br>";

					echo "[요청고유번호 : ".$requestnumber."]<br>";
					echo "[RESERVED1 : ".GetValueNameCheck($plaindata , "RESERVED1")."]<br>";
					echo "[RESERVED2 : ".GetValueNameCheck($plaindata , "RESERVED2")."]<br>";
					echo "[RESERVED3 : ".GetValueNameCheck($plaindata , "RESERVED3")."]<br>";
					**/
					// 2018-05-23 jhr 핸드폰 자릿수는 10, 11로 고정
					/*
					2018-06-27 나이스평가에서 검증완료되어 넘어온 핸드폰 번호는 유효성 검사 제거.
					if	( isset($phone_number) && (strlen($phone_number) < 10 || strlen($phone_number) > 11) ) {
						$msg = "유효하지 않는 핸드폰번호 입니다.";
						pageClose($msg);
					}
					*/

					$auth_data["auth_yn"]			= "Y";
					$auth_data["namecheck_type"]	= "phone";
					$auth_data["namecheck_name"]	= iconv("euc-kr", "utf-8", $name);
					$auth_data['namecheck_name'] = check_member_name($auth_data['namecheck_name']);
					$auth_data["namecheck_sex"]		= iconv("euc-kr", "utf-8", $gender);
					$auth_data["namecheck_birth"]	= iconv("euc-kr", "utf-8", $birthdate);
					$auth_data["namecheck_check"]	= iconv("euc-kr", "utf-8", $dupinfo);//중복체크용
					$auth_data["namecheck_vno"]		= iconv("euc-kr", "utf-8", $conninfo);//주민등록번호와고유키
					$auth_data["phone_number"]		= $phone_number;//핸드폰번호

					if(isset($_GET['intro']) && $_GET['intro']=='Y') {
						if($auth_data["namecheck_birth"]){
							$adult = date("Y") - substr($auth_data["namecheck_birth"], 0, 4) + 1;
						}
						if($adult>19){
							$auth_intro_data = array('auth_intro_type'=>'auth', 'auth_intro_yn'=>'Y');
							$this->session->sess_expiration = (60 * 5);
							$this->session->set_userdata(array('auth_intro'=>$auth_intro_data));
							// 성인인증 로그 :: 2015-03-13 lwh
							$this->adult_log('phone');
							//성인인증이 성공적으로 완료되었습니다
							$msg = getAlert('mb083');

							// 회원이 성인인증시에 회원 성인인증여부 업데이트 :: 2020-02-25
							$auth_intro = $this->session->userdata('auth_intro');
							if($auth_intro['auth_intro_yn'] == 'Y' && defined('__ISUSER__') === true){
								$params['adult_auth']	= 'Y';
								$params['member_seq']	= $this->userInfo['member_seq'];
								$this->membermodel->update_member($params);
							}

							if($_GET['type']=='join'){
								$qry = "select count(*) as cnt from fm_member where auth_code='".$auth_data["namecheck_check"]."'";
								$query = $this->db->query($qry);
								$member = $query -> row_array();

								if($member["cnt"] > 0){
									$url = "/member/login?return_url=" . urlencode("/mypage/myinfo");
									//이미 가입된 정보입니다.
									$msg = getAlert('mb176');
									pageLocation($url, $msg, 'opener');
									pageClose();
									exit;
								}

								$this->session->sess_expiration = (60 * 5);
								$this->session->set_userdata(array('auth'=>$auth_data));
								$_GET['return_url'] = '/member/agreement?authok=1';
							}
						}else{
							//미성년자는 이용할 수 없습니다.
							$msg = getAlert('mb177');
							pageClose($msg);
							exit;
						}

						$return_url = ($_GET['return_url']) ? $_GET['return_url'] : '/main';
						pageLocation($return_url, $msg, 'opener');
						pageClose();
						exit;
					}elseif(isset($_GET['findidpw']) && $_GET['findidpw']=='Y') {//아이디/패스워드 찾기
						$this->_findidpwresult($auth_data);
					}elseif(isset($_GET['dormancy']) && $_GET['dormancy']=='Y') {//휴면회원 인증
						$this->membermodel->dormancy_off($_GET['dormancy_seq']);
						$auth_dormancy_data = array('auth_dormancy_type'=>'auth', 'auth_dormancy_yn'=>'Y');
						$this->session->sess_expiration = (60 * 5);
						$this->session->set_userdata(array('auth_dormancy'=>$auth_dormancy_data));
						//휴면처리가 성공적으로 해제되었습니다.\\n재로그인후 정상적으로 쇼핑몰 이용이 가능합니다.
						$msg = getAlert('mb179');
						$url = "/member/login?return_url=" . urlencode("/main");
						pageLocation($url, $msg, 'opener');
						pageClose();
						exit;
					}else{//가입페이지

						if( !$auth_data["namecheck_check"] ){
							//세션값이 다릅니다. 올바른 경로로 접근하시기 바랍니다.
							$msg = getAlert('mb175');
							pageClose($msg);
							exit;
						}
						$qry = "select count(*) as cnt from fm_member where auth_code='".$auth_data["namecheck_check"]."'";
						$query = $this->db->query($qry);
						$member = $query -> row_array();

						if($member["cnt"] > 0) {

							$url = "/member/login?return_url=" . urlencode("/mypage/myinfo");
							//이미 가입된 정보입니다.
							$msg = getAlert('mb176');
							pageLocation($url, $msg, 'opener');
							pageClose();
							exit;
						}

						$this->session->sess_expiration = (60 * 5);
						$this->session->set_userdata(array('auth'=>$auth_data));
						pageLocation('/member/agreement?authok=1', "", 'opener');
						pageClose();
						exit;
					}
				}


			}
			//"잠시 후 다시 시도하여주십시오.<br/>오류가 계속 될 경우 고객센터로 문의하세요."
			$msg = getAlert('mb178');
			pageClose($msg);
			exit;

		} else {
			//처리할 암호화 데이타가 없습니다.
			$sRtnMsg = getAlert('mb180');
		}

		pageClose($sRtnMsg);
		exit;
	}

	public function ipin_chk(){
		$realname = config_load('realname');
		$joinform = config_load('joinform');
		$auth = $this->session->userdata('auth');
		$findtypess = $this->session->userdata('findtypess');
		$findidss = $this->session->userdata('findidss');

		if(!extension_loaded('IPINClient')) {
			dl('IPINClient.' . PHP_SHLIB_SUFFIX);
		}
		$module = 'IPINClient';


		$sSiteCode		= $realname['ipinSikey'];
		$sSitePw		= $realname['ipinKeyString'];

		$sEncData					= "";			// 암호화 된 사용자 인증 정보
		$sDecData					= "";			// 복호화 된 사용자 인증 정보

		$sRtnMsg					= "";			// 처리결과 메세지
		$sModulePath	= $_SERVER["DOCUMENT_ROOT"]."/namecheck/IPINClient";

		$sEncData	= $this->input->post_get("enc_data");


		//////////////////////////////////////////////// 문자열 점검///////////////////////////////////////////////
		if(preg_match('~[^0-9a-zA-Z+/=]~', $sEncData, $match)) {echo "입력 값 확인이 필요합니다"; exit;}
		if(base64_encode(base64_decode($sEncData))!=$sEncData) {echo "입력 값 확인이 필요합니다!"; exit;}
		///////////////////////////////////////////////////////////////////////////////////////////////////////////

		$sCPRequest = $_SESSION['CPREQUEST'];

		if ($sEncData != "") {

			//$sDecData = `$sModulePath RES $sSiteCode $sSitePw $sEncData`;

			// 사용자 정보를 복호화 합니다.
			$function = 'get_response_data';
				if (extension_loaded($module)) {
					$sDecData = $function($sSiteCode, $sSitePw, $sEncData);
				} else {
					$sDecData = "Module get_response_data is not compiled into PHP";
				}


			if ($sDecData == -9) {
				$sRtnMsg = "입력값 오류 : 복호화 처리시, 필요한 파라미터값의 정보를 정확하게 입력해 주시기 바랍니다.";
			} else if ($sDecData == -12) {
				$sRtnMsg = "NICE신용평가정보에서 발급한 개발정보가 정확한지 확인해 보세요.";
			} else {

				$arrData = preg_split("/\^/", $sDecData);
				$iCount = count($arrData);

				if ($iCount >= 5) {

					$strResultCode	= $arrData[0];			// 결과코드
					if ($strResultCode == 1) {
						$strCPRequest	= $arrData[8];			// CP 요청번호

						if ($sCPRequest == $strCPRequest) {
							//사용자 인증 성공
							$sRtnMsg = getAlert('mb181');

							$strVno      		= $arrData[1];	// 가상주민번호 (13자리이며, 숫자 또는 문자 포함)
							$strUserName		= $arrData[2];	// 이름
							$strDupInfo			= $arrData[3];	// 중복가입 확인값 (64Byte 고유값)
							$strAgeInfo			= $arrData[4];	// 연령대 코드 (개발 가이드 참조)
							$strGender			= $arrData[5];	// 성별 코드 (개발 가이드 참조)
							$strBirthDate		= $arrData[6];	// 생년월일 (YYYYMMDD)
							$strNationalInfo	= $arrData[7];	// 내/외국인 정보 (개발 가이드 참조)

							$auth_data["auth_yn"] = "Y";
							$auth_data["namecheck_type"] = "ipin";
							$auth_data["namecheck_name"] = iconv("euc-kr", "utf-8", $strUserName);
							$auth_data['namecheck_name'] = check_member_name($auth_data['namecheck_name']);
							$auth_data["namecheck_sex"] = iconv("euc-kr", "utf-8", $strGender);
							$auth_data["namecheck_birth"] = iconv("euc-kr", "utf-8", $strBirthDate);
							$auth_data["namecheck_check"] = iconv("euc-kr", "utf-8", $strDupInfo);
							$auth_data["namecheck_vno"] = iconv("euc-kr", "utf-8", $strVno);

							// 만 14세 미만 체크
							if($joinform['kid_join_use'] == 'N'){
								$req_birth = str_replace('-','',$auth_data["namecheck_birth"]);
								$birthday = date("Ymd", strtotime( $req_birth ));
								$nowday =  date('Ymd');
								$age      = floor(($nowday - $birthday) / 10000);

								if($age < 14){
									pageClose('고객님은 만 14세 미만 회원으로 쇼핑몰 회원가입이 불가합니다.');
									exit;
								}
							}

							if(isset($_GET['intro']) && $_GET['intro']=='Y'){
								//if($strAgeInfo==7){
								if((int)$strAgeInfo>=6){//##########  2018.02.05 gcs ksm : 17.11.30~ RSA 패치
									$auth_intro_data = array('auth_intro_type'=>'auth', 'auth_intro_yn'=>'Y');
									$this->session->sess_expiration = (60 * 5);
									$this->session->set_userdata(array('auth_intro'=>$auth_intro_data));
									// 성인인증 로그 :: 2015-03-13 lwh
									$this->adult_log('ipin');
									//성인인증이 성공적으로 완료되었습니다
									$msg = getAlert('mb083');

									if($_GET['type']=='join'){
										$qry = "select count(*) as cnt from fm_member where auth_code='".$auth_data["namecheck_check"]."'";
										$query = $this->db->query($qry);
										$member = $query -> row_array();

										if($member["cnt"] > 0){
											$url = "/member/login?return_url=" . urlencode("/mypage/myinfo");
											//이미 가입된 정보입니다.
											$msg = getAlert('mb176');
											pageLocation($url, $msg, 'opener');
											pageClose();
											exit;
										}

										$this->session->sess_expiration = (60 * 5);
										$this->session->set_userdata(array('auth'=>$auth_data));
										$_GET['return_url'] = '/member/agreement?authok=1';
									}
								}else{
									//미성년자는 이용할 수 없습니다.
									$msg = getAlert('mb177');
									pageClose($msg);
									exit;
								}

								$return_url = ($_GET['return_url']) ? $_GET['return_url'] : '/main';
								pageLocation($return_url, $msg, 'opener');
								pageClose();
								exit;
							}elseif(isset($_GET['findidpw']) && $_GET['findidpw']=='Y') {//아이디/패스워드 찾기
								$this->_findidpwresult($auth_data,  $arrData);
							}elseif(isset($_GET['dormancy']) && $_GET['dormancy']=='Y') {//휴면회원 인증
								$this->membermodel->dormancy_off($_GET['dormancy_seq']);
								$auth_dormancy_data = array('auth_dormancy_type'=>'auth', 'auth_dormancy_yn'=>'Y');
								$this->session->sess_expiration = (60 * 5);
								$this->session->set_userdata(array('auth_dormancy'=>$auth_dormancy_data));
								//휴면처리가 성공적으로 해제되었습니다.\\n재로그인후 정상적으로 쇼핑몰 이용이 가능합니다.
								$msg = getAlert('mb179');
								$url = "/member/login?return_url=" . urlencode("/main");
								pageLocation($url, $msg, 'opener');
								pageClose();
								exit;
							}else{//가입페이지
								$qry = "select count(*) as cnt from fm_member where auth_code='".$auth_data["namecheck_check"]."'";
								$query = $this->db->query($qry);
								$member = $query -> row_array();

								if($member["cnt"] > 0){
									$url = "/member/login?return_url=" . urlencode("/mypage/myinfo");
									//이미 가입된 정보입니다.
									$msg = getAlert('mb176');
									pageLocation($url, $msg, 'opener');
									pageClose();
									exit;
								}

								$this->session->sess_expiration = (60 * 5);
								$this->session->set_userdata(array('auth'=>$auth_data));

								pageLocation('/member/agreement?authok=1', "", 'opener');
								pageClose();
								exit;
							}
						} else {
							//CP 요청번호 불일치 : 세션에 넣은 $sCPRequest 데이타를 확인해 주시기 바랍니다.
							$sRtnMsg = getAlert('mb182',$sCPRequest);
						}
					} else {
						//리턴값 확인 후, NICE신용평가정보 개발 담당자에게 문의해 주세요. [$strResultCode]
						$sRtnMsg = getAlert('mb183',$strResultCode);
					}

				} else {
					//리턴값 확인 후, NICE신용평가정보 개발 담당자에게 문의해 주세요. [$strResultCode]
					$sRtnMsg = getAlert('mb183',$strResultCode);
				}

			}
		} else {
			//처리할 암호화 데이타가 없습니다.
			$sRtnMsg = getAlert('mb180');
		}

		pageClose($sRtnMsg);
		exit;
	}


	//아이디/패스워드찾기 완료화면 구성
	public function _findidpwresult($auth_data, $arrData = null) {

		$smsauth = config_load('master');//SMS사용시

		if( $auth_data ) {
			$qry = "select count(*) as cnt, userid, member_seq, rute from fm_member where ";
			if( $this->session->userdata('findtypess') == 'pw'){//비밀번호찾기
				$qry .= " rute = 'none' and   auth_code='".$auth_data["namecheck_check"]."' ";
			}else{
				$qry .= " auth_code='".$auth_data["namecheck_check"]."' ";
			}
			$qry .= " and auth_type != 'none' ";
			$query = $this->db->query($qry);
			$success = $query -> row_array();

			if($success["cnt"] < 1) {
				$qry = "select count(*) as cnt, userid, member_seq, rute from fm_member_dr where ";
				if( $this->session->userdata('findtypess') == 'pw'){//비밀번호찾기
					$qry .= " rute = 'none' and   auth_code='".$auth_data["namecheck_check"]."' ";
				}else{
					$qry .= " auth_code='".$auth_data["namecheck_check"]."' ";
				}
				$qry .= " and auth_type != 'none' ";
				$query = $this->db->query($qry);
				$success = $query -> row_array();
			}

			$success['error'] = false;
			$success['errorid'] = false;
			if($success["cnt"] > 0) {
				if( $this->session->userdata('findidss') ) {
					if( $this->session->userdata('findidss') != $success["userid"] ) {
						$success['error']		= true;
						$success['errorid']	= true;
					}
				}
			}else{
				$success['error'] = true;
			}
		}

		$scdocument = "top.opener.document";
		$scripts[] = "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
		$scripts[] = "<script type='text/javascript'  charset='utf-8'>";
		$scripts[] = "$(function() {";


		if( $this->session->userdata('findtypess') == 'pw'){//비밀번호찾기

			$scripts[] = '$("#findidfromlay",'.$scdocument.').show();';
			$scripts[] = '$("#findidresultlay",'.$scdocument.').hide();';

			$scripts[] = '$("#findpwfromlay",'.$scdocument.').hide();';
			$scripts[] = '$("#findpwresultlay",'.$scdocument.').show();';
			$scripts[] = '$("#findpwlay1",'.$scdocument.').text("");';
			$scripts[] = '$("#findpwlay2",'.$scdocument.').text("");';
			$scripts[] = '$("#findpwlay3",'.$scdocument.').text("");';
			$scripts[] = '$(".findpwresultfalse1",'.$scdocument.').hide();';
			$scripts[] = '$(".findpwresultfalse2",'.$scdocument.').hide();';
			$scripts[] = '$(".findpwresultok1",'.$scdocument.').hide();';
			$scripts[] = '$(".findpwresultok2",'.$scdocument.').hide();';
			$scripts[] = '$(".findpwresultok3",'.$scdocument.').hide();';

			if( $success ) {
				if( $success['error'] === false ){
					unset($params['password']);
					$this->findpw = chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122)).substr(mktime()*2,1,4);
					$scripts[] = '$(".findpwresultok1",'.$scdocument.').show();';
					$scripts[] = '$("#findpwlay1",'.$scdocument.').text("'.($this->findpw).'");';

					$this->findpw = hash('sha256',md5($this->findpw));
					$sql = "update fm_member set password = ?, update_date = now() where member_seq = ?";
					$this->db->query($sql,array($this->findpw,$success["member_seq"]));
				}elseif( $success['errorid'] ) {
					$scripts[] = '$(".findpwresultfalse2",'.$scdocument.').show();';
				}else{
					$scripts[] = '$(".findpwresultfalse1",'.$scdocument.').show();';
				}
			}else{
				$scripts[] = '$(".findpwresultfalse1",'.$scdocument.').show();';
			}

		}else{

			$scripts[] = '$("#findpwfromlay",'.$scdocument.').show();';
			$scripts[] = '$("#findpwresultlay",'.$scdocument.').hide();';

			$scripts[] = '$("#findidfromlay",'.$scdocument.').hide();';
			$scripts[] = '$("#findidresultlay",'.$scdocument.').show();';
			$scripts[] = '$("#findidlay1",'.$scdocument.').text("");';
			$scripts[] = '$("#findidlay2",'.$scdocument.').text("");';
			$scripts[] = '$("#findidlay3",'.$scdocument.').text("");';
			$scripts[] = '$(".findidresultok1",'.$scdocument.').hide();';
			$scripts[] = '$(".findidresultok2",'.$scdocument.').hide();';
			$scripts[] = '$(".findidresultok3",'.$scdocument.').hide();';
			$scripts[] = '$(".findidresultfalse",'.$scdocument.').hide();';

			if( $success ) {
				if( $success['error'] === false ) {
					$scripts[] = '$(".findidresultok1",'.$scdocument.').show();';
					$scripts[] = '$("#findidlay1",'.$scdocument.').text("'.($success["userid"]).'");';
				}else{
					$scripts[] = '$(".findidresultfalse",'.$scdocument.').show();';
				}
			}else{
				$scripts[] = '$(".findidresultfalse",'.$scdocument.').show();';
			}
		}
		$scripts[] = 'self.close();';

		$scripts[] = "});";
		$scripts[] = "</script>";
echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
foreach($scripts as $script){
	echo $script."\n";
}
echo '</head><body></body></html>';
exit;
	}

	###
	public function auth_chk(){
		###
		//이름
		$this->validation->set_rules('name', getAlert('mb084'),'trim|required|max_length[30]|xss_clean');
		if(isset($_POST['regno'])){
			//주민등록번호
			$this->validation->set_rules('regno', getAlert('mb085'),'trim|required|max_length[13]|numeric|xss_clean');
		}else{
			$this->validation->set_rules('regno1', getAlert('mb085'),'trim|required|max_length[6]|numeric|xss_clean');
			$this->validation->set_rules('regno2', getAlert('mb085'),'trim|required|max_length[7]|numeric|xss_clean');
		}
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		###
		$realname = config_load('realname');
		$sSiteID = $realname['realnameId'];
		$sSitePW =  $realname['realnamePwd'];

		$cb_encode_path = "/usr/bin/cb_namecheck";

		$strJumin= isset($_POST['regno']) ? $_POST['regno'] : $_POST["regno1"].$_POST["regno2"];		// 주민번호
		$strName = $_POST["name"];							//이름
		$strName = iconv('utf-8', 'euc-kr', $strName);

		$iReturnCode  = "";
		$iReturnCode = `$cb_encode_path $sSiteID $sSitePW $strJumin $strName`;
		switch($iReturnCode){
			case 1: // 성공
				//실명인증이 성공적으로 완료되었습니다.<br>회원가입정보를 입력해 주시기 바랍니다.
				$msg = getAlert('mb079');
				break;
			case 2:
				//www.namecheck.co.kr 의 실명등록확인 또는 02-1600-1522 콜센터로 문의주시기 바랍니다.
				$msg = getAlert('mb080');
				break;
			case 3:
				//"www.namecheck.co.kr 의 실명등록확인 또는 02-1600-1522 콜센터로 문의주시기 바랍니다."
				$msg = getAlert('mb080');
				break;
			case 50:
				//명의도용차단 서비스 가입자
				$msg = getAlert('mb081');
				break;
			default:
				//인증실패
				$msg = getAlert('mb082');
				break;
		}

		$callback = "";
		if(isset($_POST['intro']) && $_POST['intro']=='Y'){
			if($iReturnCode==1){
				$auth_data = array('auth_type'=>'auth', 'auth_yn'=>'Y');
				$this->session->sess_expiration = (60 * 5);
				$this->session->set_userdata(array('auth'=>$auth_data));
				$callback = "parent.document.location = '/main';";
				//성인인증이 성공적으로 완료되었습니다.
				$msg = getAlert('mb083');
			}
		}else{
			if($iReturnCode==1){
				$auth_data = array('auth_type'=>'auth', 'auth_yn'=>'Y');
				$this->session->sess_expiration = (60 * 5);
				$this->session->set_userdata(array('auth'=>$auth_data));
				$img = "/data/skin/".$this->skin."/images/design/btn_ok.gif";
				$callback = "parent.$('#name').attr('readonly',true);
					parent.$('#regno1').attr('readonly',true);
					parent.$('#regno2').attr('readonly',true);
					parent.$('#submit_btn_area').html(\"<img src='{$img}' id='auth_ok_btn' class='hand'>\");
					parent.$('#r_ipin').html('');
				";
			}
		}
		openDialogAlert($msg,400,140,'parent',$callback);
		exit;

	}
	###

	/**
	** 가입, 아이디/패스워드찾기, 성인인증시 : 본인인증/안심체크/아이핀 실명인증 기본 코드생성
	**/
	public function realnamecheck() {
		$realnametype = ($_POST['realnametype'])?$_POST['realnametype']:$_GET['realnametype'];
		$findidpw = ($_POST['findidpw'])?$_POST['findidpw']:$_GET['findidpw'];
		$intro = ($_POST['intro'])?$_POST['intro']:$_GET['intro'];
		$type = ($_POST['type'])?$_POST['type']:$_GET['type'];
		$dormancy = ($_POST['dormancy'])?$_POST['dormancy']:$_GET['dormancy'];
		$dormancy_seq = ($_POST['dormancy_seq'])?$_POST['dormancy_seq']:$_GET['dormancy_seq'];
		$return_url = ($_POST['return_url'])?$_POST['return_url']:$_GET['return_url'];
		$realname = config_load('realname');

		$sReserved1 = ($_POST['sReserved1'])?$_POST['sReserved1']:$_GET['sReserved1'];
		$sReserved2 = ($_POST['sReserved2'])?$_POST['sReserved2']:$_GET['sReserved2'];
		$sReserved3 = ($_POST['sReserved3'])?$_POST['sReserved3']:$_GET['sReserved3'];

		$unsetuserdata = array('findtypess' => '', 'findidss' => '', 'auth' => '');
		$this->session->unset_userdata($unsetuserdata);
		unset($auth);
		if($findidpw){
			$returnurl_intro = "?findidpw=Y";
		}elseif($intro){
			$returnurl_intro = "?intro=Y";
			if($type){
				$returnurl_intro = $returnurl_intro . "&type=" . $type;
			}
		}elseif($dormancy){
			$returnurl_intro = "?dormancy=Y&dormancy_seq=".$dormancy_seq;
		}

		if($return_url){
			$returnurl_intro = ($returnurl_intro) ? $returnurl_intro."&return_url=".urldecode($return_url) : "?return_url=".urldecode($return_url);
		}

		if($findidpw){
			$this->session->sess_expiration = (60 * 5);
			if($sReserved1){
				$this->session->set_userdata(array('findtypess'=>$sReserved1));
			}

			if($sReserved2){
				$this->session->set_userdata(array('findidss'=>$sReserved2));
			}
		}


		if( $realnametype && ($realname['useRealnamephone']=='Y' || $realname['useRealname']=='Y' || $realname['useIpin']=='Y' || $realname['useRealnamephone_adult']=='Y' || $realname['useIpin_adult']=='Y'|| $realname['useRealnamephone_dormancy']=='Y' || $realname['useIpin_dormancy']=='Y') ) {

			if ($_SERVER['HTTPS'] == "on") {
				$HTTP_HOST = "https://".$_SERVER['HTTP_HOST'];
			}else{
				$HTTP_HOST = "http://".$_SERVER['HTTP_HOST'];
			}

			if( $realnametype == 'phone' ) {//본인인증
				//**************************************** 본인인증 : 휴대폰 필수 수정값***************************************************************************
				if(!extension_loaded('CPClient')) {
					dl('CPClient.' . PHP_SHLIB_SUFFIX);
				}
				$module = 'CPClient';

				$sSiteCode 				= $realname['realnamephoneSikey'];			// 본인인증 사이트 코드
				$sSitePassword		= $realname['realnamePhoneSipwd'];			// 본인인증 사이트 패스워드
				$authtype = "M";      	// 없으면 기본 선택화면, X: 공인인증서, M: 핸드폰, C: 카드
				$popgubun 	= "Y";		//Y : 취소버튼 있음 / N : 취소버튼 없음

				if( $this->_is_mobile_agent) {//$this->mobileMode  ||
					$customize 	= "Mobile";			//없으면 기본 웹페이지 / Mobile : 모바일페이지
				}else{
					$customize 	= "";			//없으면 기본 웹페이지 / Mobile : 모바일페이지
				}

				//$cb_encode_path	= $_SERVER["DOCUMENT_ROOT"]."/namecheck/CPClient";	// 암호화 프로그램의 위치 (절대경로+모듈명)_Linux ..
				//$sType			= "REQ";
				//$reqseq = `$cb_encode_path SEQ $sSiteCode`;

				$reqseq = "REQ_0123456789";     // 요청 번호, 이는 성공/실패후에 같은 값으로 되돌려주게 되므로

				// 업체에서 적절하게 변경하여 쓰거나, 아래와 같이 생성한다.
				$function = 'get_cprequest_no';
				if (extension_loaded($module)) {
					$reqseq = $function($sitecode);
				} else {
					$reqseq = "Module get_request_no is not compiled into PHP";
				}

				$returnurl		= $HTTP_HOST."/member_process/niceid_phone_return".$returnurl_intro;	// 성공시 이동될 URL
				$errorurl		= $HTTP_HOST."/member_process/niceid_phone_return".$returnurl_intro;		// 실패시 이동될 URL

				// reqseq값은 성공페이지로 갈 경우 검증을 위하여 세션에 담아둔다.

				$this->session->set_userdata(array('REQ_SEQ_P'=>$reqseq));//$_SESSION["REQ_SEQ"] = $reqseq;
				$_SESSION["REQ_SEQ_P"] = $reqseq;


				// 입력될 plain 데이타를 만든다.1
				$plaindata =  "7:REQ_SEQ" . strlen($reqseq) . ":" . $reqseq .
										  "8:SITECODE" . strlen($sSiteCode) . ":" . $sSiteCode .
										  "9:AUTH_TYPE" . strlen($authtype) . ":". $authtype .
										  "7:RTN_URL" . strlen($returnurl) . ":" . $returnurl .
										  "7:ERR_URL" . strlen($errorurl) . ":" . $errorurl .
										  "11:POPUP_GUBUN" . strlen($popgubun) . ":" . $popgubun .
										  "9:CUSTOMIZE" . strlen($customize) . ":" . $customize.
										  "9:RESERVED1" . strlen($sReserved1) . ":" . $sReserved1.
										  "9:RESERVED2" . strlen($sReserved2) . ":" . $sReserved2.
										  "9:RESERVED3" . strlen($sReserved3) . ":" . $sReserved3;

				//$enc_data = `$cb_encode_path ENC $sSiteCode $sSitePassword $plaindata`;

				$function = 'get_encode_data';
				if (extension_loaded($module)) {
					$enc_data = $function($sSiteCode, $sSitePassword, $plaindata);
				} else {
					$enc_data = "Module get_request_data is not compiled into PHP";
				}

				if( $enc_data == -1 )
				{
					//암/복호화 시스템 오류입니다.
					$returnMsg = getAlert('mb166');
					//$enc_data = "";
				}
				else if( $enc_data== -2 )
				{
					//암호화 처리 오류입니다.
					$returnMsg = getAlert('mb171');
					//$enc_data = "";
				}
				else if( $enc_data== -3 )
				{
					//암호화 데이터 오류 입니다.
					$returnMsg = getAlert('mb172');
					//$enc_data = "";
				}
				else if( $enc_data== -9 )
				{
					//입력값 오류 입니다.
					$returnMsg = getAlert('mb170');
					//$enc_data = "";
				}
				$sEncData = $enc_data;
			}
			elseif( $realnametype == 'ipin' ) {//아이핀체크

					if(!extension_loaded('IPINClient')) {
						dl('IPINClient.' . PHP_SHLIB_SUFFIX);
					}
					$module = 'IPINClient';

					###
					$sSiteCode		= $realname['ipinSikey'];
					$sSitePw			= $realname['ipinKeyString'];

					$sModulePath	= $_SERVER["DOCUMENT_ROOT"]."/namecheck/IPINClient";
					$sReturnURL		= get_connet_protocol().$_SERVER['HTTP_HOST']."/member_process/ipin_chk".$returnurl_intro;

					##
					$sType			= "SEQ";
					//$sCPRequest = `$sModulePath $sType $sSiteCode`;

					$function = 'get_request_no';
					if (extension_loaded($module)) {
						$sCPRequest = $function($sSiteCode);
					} else {
						$sCPRequest = "Module get_request_no is not compiled into PHP";
					}


					$this->session->set_userdata(array('CPREQUEST'=>$sCPRequest));
					$_SESSION['CPREQUEST'] = $sCPRequest;

					##
					$sType			= "REQ";
					$sEncData		= "";
					$sRtnMsg		= "";

					//$sEncData	= `$sModulePath $sType $sSiteCode $sSitePw $sCPRequest $sReturnURL`;//$sCPRequest $sReturnURL

					$function = 'get_request_data';
					if (extension_loaded($module)) {
						$sEncData = $function($sSiteCode, $sSitePw, $sCPRequest, $sReturnURL);
					} else {
						$sEncData = "Module get_request_data is not compiled into PHP";
					}

					if ($sEncData == -9){
						$sRtnMsg = "입력값 오류 : 암호화 처리시, 필요한 파라미터값의 정보를 정확하게 입력해 주시기 바랍니다.";
					}
			}
			else{//안심체크
				//**************************************** 필수 수정값 ***************************************************************************

				if(!extension_loaded('CPClient')) {
					dl('CPClient.' . PHP_SHLIB_SUFFIX);
				}
				$module = 'CPClient';

				$sSiteCode 				= $realname['realnameId'];							// 안심체크 사이트 코드
				$sSitePassword		= $realname['realnamePwd'];						// 안심체크 사이트 패스워드

				$sIPINSiteCode		= $realname['ipinSikey'];								// 아이핀사이트 코드
				$sIPINPassword		= $realname['ipinKeyString'];						// 아이핀사이트 패스워드
				$sReturnURL			= $HTTP_HOST."/member_process/niceid2_return".$returnurl_intro;		//결과 수신 : full URL 입력
				$cb_encode_path	= $_SERVER["DOCUMENT_ROOT"]."/namecheck/CPClient";	// 암호화 프로그램의 위치 (절대경로+모듈명)_Linux ..

				//*******************************************************************************************************************************

				$sRequestNO = "";									//요청고유번호, 이는 성공/실패후에 같은 값으로 되돌려주게 되므로 필요시 사용
				$sClientImg		= "";								//서비스 화면 로고 선택(full 도메인 입력): 사이즈 100*25(px)

				//$sRequestNO = `$cb_encode_path SEQ $sSiteCode`;		//요청고유번호 / 비정상적인 접속 차단을 위해 필요.

				$function = 'get_cprequest_no';//요청고유번호 / 비정상적인 접속 차단을 위해 필요.
					if (extension_loaded($module)) {
						$sRequestNO = $function($sSiteCode);
					} else {
						$sRequestNO = "Module get_request_no is not compiled into PHP";
					}

				$_SESSION["REQ_SEQ"] = $sRequestNO;					//해킹등의 방지를 위하여 세션을 쓴다면, 세션에 요청번호를 넣는다.
				$this->session->set_userdata(array('REQ_SEQ'=>$sRequestNO));

				//echo "sRequestNO : ".$sRequestNO."<br>";

				// 입력될 plain 데이타를 만든다.2
				$plaindata =  "7:RTN_URL" . strlen($sReturnURL) . ":" . $sReturnURL.
							  "7:REQ_SEQ" . strlen($sRequestNO) . ":" . $sRequestNO.
							  "7:IMG_URL" . strlen($sClientImg) . ":" . $sClientImg.
							  "13:IPIN_SITECODE" . strlen($sIPINSiteCode) . ":" . $sIPINSiteCode.
							  "17:IPIN_SITEPASSWORD" . strlen($sIPINPassword) . ":" . $sIPINPassword.
							  "9:RESERVED1" . strlen($sReserved1) . ":" . $sReserved1.
							  "9:RESERVED2" . strlen($sReserved2) . ":" . $sReserved2.
							  "9:RESERVED3" . strlen($sReserved3) . ":" . $sReserved3;

				$function = 'get_encode_data';

				if (extension_loaded($module)) {
					$sEncData = $function($sSiteCode, $sSitePassword, $plaindata);
				} else {
					$sEncData = "Module get_request_data is not compiled into PHP";
				}


				if( $sEncData == -1 )
				{
					//암/복호화 시스템 오류입니다.
					$returnMsg = getAlert('mb166');
				}
				else if( $sEncData== -2 )
				{
					//암호화 처리 오류입니다
					$returnMsg = getAlert('mb171');
				}
				else if( $sEncData== -3 )
				{
					//암호화 데이터 오류 입니다.
					$returnMsg = getAlert('mb172');
				}
				else if( $sEncData== -9 )
				{
					$returnMsg = "입력값 오류 입니다.";
				}
			}

			if(empty($sEncData)) {//실패시
				$returnMsg = '잘못된 접근입니다.';
				pageClose($returnMsg);
				exit;
			}

			if($returnMsg) {//실패시
				pageClose($returnMsg);
				exit;
			}

			$scripts[] = "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
			$scripts[] = "<script type='text/javascript'>";
			$scripts[] = "$(function() {";
			if( $realnametype == 'phone' ) {//본인인증
				$encodedataform = '<input type="hidden" name="m" value="checkplusSerivce" >';
				$encodedataform .= '<input type="hidden" name="EncodeData" value="'.$sEncData.'" >';
				$action= 'https://nice.checkplus.co.kr/CheckPlusSafeModel/checkplus.cb';
				$scripts[] = 'document.form_chk.submit();';
			}else{
				if( $realnametype == 'ipin' ) {//ipin
					$encodedataform = '<input type="hidden" name="m" value="pubmain" >';
					$action= 'https://cert.vno.co.kr/ipin.cb';
				}else{
					$encodedataform = '<input type="hidden" name="m" value="" >';
					$action = 'https://cert.namecheck.co.kr/NiceID2/certpass_input.asp';
				}
				$encodedataform .= '<input type="hidden" name="enc_data" value="'.$sEncData.'" >';
				$scripts[] = 'document.form_chk.submit();';
			}


			$scripts[] = "});";
			$scripts[] = "</script>";

echo '<html><head>';
foreach($scripts as $script){
	echo $script."\n";
}
echo '</head><body>
<form method="post" name="form_chk" action="'.$action.'">
'.$encodedataform.'
<input type="hidden" name="param_r1" value="'.trim($sReserved1).'">
<input type="hidden" name="param_r2" value="'.trim($sReserved2).'">
<input type="hidden" name="param_r3" value="'.trim($sReserved3).'">
</form>
</body>
</html>
';
			exit;
		}else{
			$returnMsg ="잘못된 접근입니다.";
			pageClose($returnMsg);
			exit;
		}
	}

	public function adult_log($type){
		$sess_data					= $this->session->userdata;

		$log_data['member_seq']		= ($sess_data['user']['member_seq']) ? $sess_data['user']['member_seq'] : '';
		$log_data['userid']			= ($sess_data['user']['userid']) ? $sess_data['user']['userid'] : '';
		$log_data['ip_address']		= ($sess_data['ip_address']) ? $sess_data['ip_address'] : $_SERVER['REMOTE_ADDR'];
		$log_data['auth_type']		= $type;
		$log_data['user_agent']		= $sess_data['user_agent'];
		$log_data['regist_date']	= date('Y-m-d H:i:s');

		$this->db->insert('fm_adult_log', $log_data);

		// 회원테이블에 성인 인증 회원 업데이트 :: 2015-06-04 lwh
		if($sess_data['user']['member_seq']){
			$this->db->where('member_seq', $sess_data['user']['member_seq']);
			$result = $this->db->update('fm_member', array("adult_auth"=>'Y'));
		}
	}
 /**
  ** 본인인증/안심체크/아이핀 실명인증 체크 관련
  **/

   	//회원아이콘 설정
	public function membericonsave(){
		$this->mdata = $this->membermodel->get_member_data($this->userInfo['member_seq']);//회원정보//$this->userInfo['member_seq']
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
					//가로*세로 사이즈가 30*30 이하이어야 합니다.
					$msg = getAlert('mb086');
					openDialogAlert($msg,400,150,'parent');
					exit;
				}

				if($user_icon_file){
					@unlink($_SERVER['DOCUMENT_ROOT'].$config['upload_path'].'/'.$user_icon_file);
				}
				$_FILES['membericonFile']['type'] = $tmp['mime'];

				$file_ext		= end(explode('.', $_FILES['membericonFile']['name']));//확장자추출
				$file_name	= 'm_'.$this->userInfo['member_seq'].'.'.$file_ext;//'.str_replace(" ", "", (substr(microtime(), 2, 6))).'
				$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
				$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
				$config['file_name'] = $file_name;
				$config['allowed_types'] = 'jpg|gif|jpeg|png';
				$config['overwrite'] = true;
				$this->load->library('Upload', $config);
				if ( ! $this->upload->do_upload('membericonFile'))
				{
					$error = $this->upload->display_errors();
					openDialogAlert($error,400,150,'parent');
					exit;
				}
				$uploadData = $this->upload->data();
				$user_icon = str_replace($_SERVER['DOCUMENT_ROOT'],'',$uploadData['file_path']).$uploadData['raw_name'].$uploadData['file_ext'];
				$this->db->where('member_seq', $this->userInfo['member_seq']);
				$result = $this->db->update('fm_member', array("user_icon"=>99, "user_icon_file"=>$file_name));
			}else{
				openDialogAlert($data_used['msg'],400,140,'parent','');
			}
		}

		$callback = "parent.membericonDisplay('{$user_icon}?".time()."');";
		//등록하였습니다.
		openDialogAlert(getAlert('mb087'),400,140,'parent',$callback);
	}

	public function sns_update_id(){
		$params			= $_POST;
		$userid			= $params['userid'];
		$password		= $params['password'];
		$re_password	= $params['re_password'];

		$this->validation->set_rules('userid', '아이디','trim|required|min_length[6]|max_length[20]|xss_clean');
		$this->validation->set_rules('password', '비밀번호','trim|required|min_length[6]|max_length[32]|xss_clean');
		$this->validation->set_rules('re_password', '비밀번호확인','trim|required|min_length[6]|max_length[32]|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			echo $err['value'];
			exit;
		}

		$return_result = $this->id_chk('re_chk');
		if	(!$return_result['return']){
			echo $return_result['return_result'];
			exit;
		}

		if	($password != $re_password){
			echo '비밀번호 확인이 일치하지 않습니다';
			exit;
		}

		$return_result = $this->pw_chk('re_chk');

		if	(!$return_result['return']){
			echo $return_result['return_result'];
			exit;
		}

		$sess_data		= $this->session->userdata;
		$password		= hash('sha256',md5($password));

		
		$where_arra = [
			'member_seq' => $sess_data['user']['member_seq'], 
			'rute !=' => 'none', 
			'sns_change' => 0
		];
		$set_array = [
			'userid' => $userid, 
			'password' => $password, 
			'sns_change' => '1', 
			'update_date' => date('Y-m-d H:i:s')
		];

		$this->db->update('fm_member', $set_array, $where_arra);

		echo 'succ';

	}
}

/* End of file member_process.php */
/* Location: ./app/controllers/member_process.php */
