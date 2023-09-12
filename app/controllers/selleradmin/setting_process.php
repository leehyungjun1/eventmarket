<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class setting_process extends selleradmin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('admin/setting');

	}

	public function shipping_address_delete(){
		$this->load->model('shippingmodel');

		#### Form validation
		$this->validation->set_rules('add_chk[]','고유키','required|trim|xss_clean|max_length[255]');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$arr_param_list = array(
			'add_chk',
		);
		foreach($arr_param_list as $reciveParamName){
			// 가변 변수에 데이터 할당
			${$reciveParamName} = $this->input->post($reciveParamName);
		}

		// 입력 장소 불러오기
		$sc									= array();
		$sc['shipping_address_seq']			= $add_chk;
		$list = $this->shippingmodel->shipping_address_list($sc);

		if(count($list['record']) != count($add_chk)){
			$callback = "parent.closeDialog('deleteInfoLayer');";
			openDialogAlert("이미 삭제된 데이터가 있습니다.<br/>새로고침 후 다시 시도해주세요.</a>",400,160,'parent',$callback);
			exit;
		}

		$arrSeq = array();

		$refund_address_include = false;
		$shipping_store_include = false;

		foreach($list['record'] as $row){
			$arrSeq[] = $row['store_seq'];
			if($row['refund_address_seq'] && !$refund_address_include){
				$address['refund'] = '반송지';
				$refund_address_include = true;
			}
			if($row['shipping_store_seq'] && !$shipping_store_include){
				$address['store'] = '매장수령';
				$shipping_store_include = true;
			}
		}
		$msg = implode(', ', $address);

		if($refund_address_include || $shipping_store_include){
			$callback = "parent.closeDialog('deleteInfoLayer');";
			openDialogAlert("선택한 매장은 ".$msg."(으)로 사용 중 입니다. ".$msg." 설정 변경 후 다시 삭제해주세요.</a>",400,180,'parent',$callback);
			exit;
		}

		// 입력장소 정보 삭제
		$sc									= array();
		$sc['shipping_address_seq']			= $add_chk;

		$result = $this->shippingmodel->del_shipping_address($sc);

		// file cache 삭제
		cache_clean('shipping_refund_address');

		if($result){
			$callback = "parent.closeDialog('deleteInfoLayer');parent.location.reload();";
			openDialogAlert("처리 되었습니다.",400,140,'parent',$callback);
		}else{
			$callback = "parent.closeDialog('deleteInfoLayer');parent.location.reload();";
			openDialogAlert("처리 중 문제가 발생했습니다.",400,140,'parent',$callback);
		}
	}

	/* 관리자 등록 */
	public function manager_reg(){
		#### AUTH
		$auth = $this->authmodel->manager_limit_act('setting_manager_act');
		if(!$auth){
			$callback = "parent.set_member_html();";
			openDialogAlert($this->auth_msg,400,140,'parent',$callback);
			exit;
		}

		// 관리자 비밀번호 xss_clean : input->post() 는 이미 변환이 되어 original POST GET 이용해야함
		$this->load->helper('Security');
		$this->load->helper('xssfilter');
		xss_clean_basic($_POST['provider_id']);
		xss_clean_basic($_POST['mpasswd']);

		// 비밀번호 유효성 체크
		$pre_enc_password = '';
		$enc_password = '';

		$check_password = $this->input->post('mpasswd');
		$password_params = array(
			'birthday'				=> '',
			'phone'					=> '',
			'cellphone'					=> '',
			'pre_enc_password'		=> $pre_enc_password,
			'enc_password'			=> $enc_password,
		);
		$this->load->library('memberlibrary');
		$result = $this->memberlibrary->check_password_validation($check_password, $password_params);
		if($result['code'] != '00' && $result['alert_code']){
			openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback);
			exit;
		}

		### required
		$this->validation->set_rules('provider_id', '아이디','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('mpasswd', '비밀번호','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('mpasswd_re', '비밀번호확인','trim|required|max_length[20]|xss_clean');
		$this->validation->set_rules('provider_name', '이름','trim|required|max_length[32]|xss_clean');
		if(isset($_POST['ip_chk']) && $_POST['ip_chk']=='Y')  $this->validation->set_rules('limit_ip', '제한 아이피','trim|max_length[15]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		if($_POST['mpasswd'] != $_POST['mpasswd_re']){
			$callback = "parent.document.getElementsByName('mpasswd_re')[0].focus();";
			openDialogAlert("비밀번호 확인이 다릅니다.",400,140,'parent',$callback);
			exit;
		}
		###
		$return_result = $this->id_chk('re_chk');
		if(!$return_result['return']){
			$callback = "parent.document.getElementsByName('provider_id')[0].focus();";
			openDialogAlert($return_result['return_result'],400,140,'parent',$callback);
			exit;
		}

		### AUTH
		// 입점사용꺼 만들것
		$this->load->model('membermodel');
		$auth = $this->membermodel->seller_manager_auth_list();

		### 관리자 만들때 입점사ID의 앞4글자로 그룹화 함. :: 2017-02-09 lwh
		$pId_front = substr($this->providerInfo['provider_id'],0,4);
		if($_POST['provider_id'] && substr($_POST['provider_id'],0,4) != $pId_front){
			$_POST['provider_id'] = $pId_front.'_'.$_POST['provider_id'];
		}

		###
		$params = $_POST;
		$params['regdate']			= date('Y-m-d H:i:s');
		$params['provider_passwd']	= md5($_POST['mpasswd']);
		$params['provider_name']	= $_POST['provider_name'];

		$this->load->library('managerlibrary');
		$this->managerlibrary->exec_valid_limit_ip($params);

		$data = filter_keys($params, $this->db->list_fields('fm_provider'));
		$data['manager_auth']	= $auth;
		$data['provider_group'] = $this->providerInfo['provider_seq'];
        $result = $this->db->insert('fm_provider', $data);

        //관리자 로그 남기기
        $this->load->library('managerlog');
        $this->managerlog->insertData();

		$callback = "parent.document.location.href='/selleradmin/setting/manager';";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	public function manager_modify(){

		### 부관리자는 본인것만 수정 가능함
		if	($this->providerInfo['manager_yn'] != 'Y' && ($this->providerInfo['sub_provider_seq'] != $_POST['provider_seq'])){
			pageBack("잘못된 접근입니다.");
			exit;
		}

		### required
		$this->validation->set_rules('provider_name', '이름','trim|required|max_length[32]|xss_clean');
		if(isset($_POST['ip_chk']) && $_POST['ip_chk']=='Y')  $this->validation->set_rules('limit_ip', '제한 아이피','trim|max_length[15]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		### AUTH
		$this->load->model('membermodel');
        $auth = $this->membermodel->seller_manager_auth_list();

        //for log
        $query			= $this->db->query('seLECT provider_id, manager_auth FROM fm_provider WHERE provider_seq = ?', $_POST['provider_seq']);
        $before_data	= $query->result_array();
        $before_auth_arr= explode("||", $before_data[0]['manager_auth']);

        $params_before = array();
        foreach($before_auth_arr as $v){
            $v = explode('=', $v);
            if(count($v) == 2){
                $params_before[$v[0]] = $v[1];
            }
        }

        $_POST['provider_id']	= $before_data[0]['provider_id'];

		###
		$params = $_POST;

		// 업데이트할 입점사가 manager_yn 이 Y이 있고 현재 로그인된 계정이 manager_act 권한이 있는 경우
		// manager_auth를 null 로 업데이트한다.
		// 2019-03-20 rsh
		if(!empty($params['provider_seq'])) {
		    if(!$this->providermodel)     $this->load->model("providermodel");
		    if(!$this->authmodel)     $this->load->model("authmodel");
		    $isManager = $this->providermodel->is_manager($params['provider_seq']);
		    if($isManager) {
		        $auth_limit = $this->authmodel->manager_limit_act('manager_act');
		        $auth = null;
		    }
		}
		$pwFlag = false;

		### 관리자 비밀번호 검증
		if(isset($_POST['passwd_chg']) && $_POST['passwd_chg']=='Y'){
			// 관리자 비밀번호 xss_clean : input->post() 는 이미 변환이 되어 original POST GET 이용해야함
			$this->load->helper('Security');
			$this->load->helper('xssfilter');
			xss_clean_basic($_POST['provider_passwd']);

			// 비밀번호 유효성 체크
			$query = "select * from fm_provider where provider_seq=?";
			$query = $this->db->query($query,array($_POST['provider_seq']));
			$data = $query->row_array();
			$pre_enc_password = $data['provider_passwd'];
			$enc_password = md5($_POST['mpasswd']);

			$check_password = $this->input->post('mpasswd');
			$password_params = array(
				'birthday'				=> '',
				'phone'					=> '',
				'cellphone'					=> '',
				'pre_enc_password'		=> $pre_enc_password,
				'enc_password'			=> $enc_password,
			);
			$this->load->library('memberlibrary');
			$result = $this->memberlibrary->check_password_validation($check_password, $password_params);
			if($result['code'] != '00' && $result['alert_code']){
				openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback);
				exit;
			}

			$this->validation->set_rules('mpasswd', '비밀번호','trim|required|min_length[8]|max_length[20]|xss_clean');
			$this->validation->set_rules('mpasswd_re', '비밀번호확인','trim|required|min_length[8]|max_length[20]|xss_clean');
			$this->validation->set_rules('manager_password', '현재 관리자 비밀번호','trim|required|max_length[32]|xss_clean');

			if($_POST['mpasswd'] != $_POST['mpasswd_re']){
				$callback = "parent.document.getElementsByName('mpasswd_re')[0].focus();";
				openDialogAlert("비밀번호 확인이 다릅니다.",400,140,'parent',$callback);
				exit;
			}

			$str_md5 = md5($_POST['manager_password']);
			$str_sha256_md5 = hash('sha256',$str_md5);
			$query = "select * from fm_provider where provider_id=? and (provider_passwd=? OR provider_passwd=?)";
			$query = $this->db->query($query,array($this->providerInfo['provider_id'],$str_md5,$str_sha256_md5));
			$data = $query->row_array();
			if(!$data){
				$callback = "";
				openDialogAlert("현재 로그인된 관리자 비밀번호가 일치하지 않습니다.",400,140,'parent',$callback);
				exit;
			}

			$str_md5 = md5($_POST['mpasswd']);
			$str_sha256_md5 = hash('sha256',$str_md5);
			$sql = "select count(*) as cnt from (select * from fm_provider_pwd_history where provider_seq=? order by regist_date desc limit 2) a where a.pwd=? or a.pwd=?;";
			$query = $this->db->query($sql,array($_POST['provider_seq'],$str_md5,$str_sha256_md5));
			$res = $query->row_array();
			if($res['cnt']){
				openDialogAlert("사용할 수 없는 비밀번호입니다.",400,140,'parent',"");
				exit;
			}

			$pwFlag = true;
			$params['provider_passwd']		= md5($_POST['mpasswd']);
		}

		## IP제한
		$this->load->library('managerlibrary');
		$this->managerlibrary->exec_valid_limit_ip($params);

		if($_POST['hp_chk']!='Y'){
			$_POST['auth_hp'] = "";
		}else{
			if(trim($_POST['auth_hp']) == ''){
				openDialogAlert("인증 휴대폰 번호를 입력하여 주세요.",400,140,'parent','');
				exit;
			}

			$hp_value_chk_cnt = 0;
			if(preg_match("/[a-z]/",$_POST["auth_hp"])) $hp_value_chk_cnt += 1;
			if(preg_match("/[A-Z]/",$_POST["auth_hp"])) $hp_value_chk_cnt += 1;
			if(preg_match("/[!#$%^&*()?+=\/]/",$_POST["auth_hp"])) $hp_value_chk_cnt += 1;

			if($hp_value_chk_cnt > 0){
				openDialogAlert("휴대폰 번호는 숫자로만 입력해주세요.",400,140,'parent','');
				exit;
			}

		}

		$data = filter_keys($params, $this->db->list_fields('fm_provider'));
		unset($data['provider_id']);
		unset($data['provider_seq']);
		$data['provider_log'] = "<div>".date("Y-m-d H:i:s")." 관리자(".$this->providerInfo['provider_name'].")가 정보를 수정하였습니다. (".$_SERVER['REMOTE_ADDR'].")</div>".$_POST['provider_log'];

		$data['manager_auth'] = $auth;
		//관리자 권한이 없을 경우엔 수정못함
		if($this->providerInfo['manager_yn'] != 'Y') unset($data['manager_auth']);

		$this->db->where('provider_seq', $params['provider_seq']);
		$result = $this->db->update('fm_provider', $data);

		//비밀번호가 변경되면 히스토리를 남긴다
		if	($pwFlag){
			$sql = "insert into fm_provider_pwd_history set provider_seq=?, pwd=?, regist_date=now()";
			$query = $this->db->query($sql,array($_POST['provider_seq'],$params['provider_passwd']));
        }

        if($this->providerInfo['manager_yn'] == 'Y' && $this->providerInfo['provider_id'] != $params['provider_id']){ //본사의 대표운영자는 저장하지 않음
			//관리자 로그 남기기
			$this->load->library('managerlog');
			$logInfo = array(
				'params_before'	=> $params_before,
				'params'		=> $params_after
			);
			$this->managerlog->insertData($logInfo);
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}



	public function id_chk($chk_key = null){
		$provider_id = $_REQUEST['provider_id'];
		if(!$provider_id) die();

		###
		$count = get_rows('fm_provider',array('provider_id'=>$provider_id));

		$text = "사용할 수 있는 아이디 입니다.";
		$return = true;
		if(strlen($provider_id)<4 || strlen($provider_id)>16){
			$text = "글자 제한 수를 맞춰주세요.";
			$return = false;
		}else if(preg_match("/[^a-z0-9\-_]/i", $provider_id)) {
			$text = "사용할 수 없는 아이디 입니다.";
			$return = false;
		}else if($count > 0){
			$text = "이미 사용중인 아이디 입니다.";
			$return = false;
		}
		$result = array("return_result" => $text, "provider_id" => $provider_id, "return" => $return);

		if($chk_key){
			return $result;
		}else{
			echo json_encode($result);
		}
	}

	public function manager_delete(){
		$manager_arr = $_GET['provider_seq'];
		foreach($manager_arr as $k){
             //관리자 로그 남기기
            $query = $this->db->query("seLECT provider_id, provider_name FROM fm_provider WHERE provider_seq = ?", $k);
			$data = $query->row_array();

			$this->load->library('managerlog');
            $this->managerlog->insertData(array('params' => array('provider_id' => $data['provider_id'], 'provider_name' => $data['provider_name'])));

            $result = $this->db->delete('fm_provider', array('provider_seq' => $k));
		}
		echo $result;
	}


	public function iconUpload(){
		$this->load->library('Upload');
		if (is_uploaded_file($_FILES['manager_icon']['tmp_name'])) {
			$config['upload_path']		= $path = ROOTPATH."/data/icon/manager/";
			$file_ext = end(explode('.', $_FILES['manager_icon']['name']));//확장자추출
			$config['allowed_types']	= 'jpg|gif|jpeg|png';
			$config['overwrite']			= TRUE;
			$config['file_name']			= substr(microtime(), 2, 6).'.'.$file_ext;
			$this->upload->initialize($config);

			if ($this->upload->do_upload('manager_icon')) {
				@chmod($config['upload_path'].$config['file_name'], 0777);
				$callback = "parent.iconDisplay('{$config[file_name]}');";
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

	## 굿스플로 업무자동화서비스 세팅 :: 2015-06-23 lwh
	function goodsflow_setting(){
		$this->load->model('goodsflowmodel');

		$param = $_POST;
		unset($param['gf_mode']);
		if(!$this->providerInfo['provider_seq']){
			openDialogAlert("잘못된 접근입니다.",400,140,'parent','');
			exit;
		}

		$param['provider_seq'] = $this->providerInfo['provider_seq'];

		foreach($_POST['boxSize'] as $k => $val){
			if($box_arr[$val]){
				openDialogAlert("박스타입은 중복될수 없습니다.",400,140,'parent','');
				exit;
			}
			$box_arr[$val] = $val;
		}

		if($_POST['goodsflow_notuse']){
			$step_param['goodsflow_msg'] = '이용 중지 – 관리자에 의해 중지';
			// $this->goodsflowmodel->set_goodsflow_step($param['provider_seq'],$step_param);
			config_save('system',array('goodsflow_use'=>0));
		}else{
			if(!$_POST['mallId']){
				openDialogAlert("자동설정되는 아이디가 지정되어지지 않았습니다.\n새로고침 후 계속 같은 문제가 발생하면 고객센터로 연락주세요.",400,140,'parent');
				exit;
			}
			$this->validation->set_rules('mallName', '쇼핑몰명','required|trim|xss_clean');
			$this->validation->set_rules('centerName', '발송지명','required|trim|xss_clean');
			$this->validation->set_rules('goodsflowZipcode[]', '발송지주소','required|trim|xss_clean');
			$this->validation->set_rules('centerTel1', '발송지전화1','required|trim|numeric|xss_clean');
			$this->validation->set_rules('centerTel2', '발송지전화2','trim|numeric|xss_clean');
			if($_POST['gf_mode'] != 'modify'){
				$this->validation->set_rules('bizNo', '사업자 번호','required|trim|numeric|xss_clean');
				$this->validation->set_rules('deliveryCode', '택배사','required|trim|xss_clean');
				$this->validation->set_rules('contractNo', '택배사 계약코드','required|trim|xss_clean');
				if($_POST['deliveryCode']=='EPOST'){
					$this->validation->set_rules('contractCustNo', '택배사가 우체국인경우 `택배사 업체코드`','required|trim|xss_clean');
				}
			}

			$this->validation->set_rules('boxSize[]', '박스타입','required|trim|xss_clean');
			$this->validation->set_rules('shFare[0]', '선불배송','required|numeric|trim|xss_clean');
			$this->validation->set_rules('scFare[0]', '신용배송','required|numeric|trim|xss_clean');
			$this->validation->set_rules('bhFare[0]', '착불배송','required|numeric|trim|xss_clean');
			$this->validation->set_rules('rtFare[0]', '반품배송','required|numeric|trim|xss_clean');

			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,140,'parent',$callback);
				exit;
			}

			// 최종 신청 전 설정값 재 검사
			$config_goodsflow = $this->goodsflowmodel->get_goodsflow_setting($param['provider_seq']);
			$goodsflow_step		= $config_goodsflow['goodsflow_step'];

			// 정보 수정시
			if($_POST['gf_mode'] == 'modify'){
				$apiParam['requestKey'] = $config_goodsflow['requestKey'];
				$result	= $this->goodsflowmodel->apiSender('updateService',$apiParam);
				if($result['verified']){
					$this->goodsflowmodel->set_goodsflow_setting($param);
					openDialogAlert('수정되었습니다.',400,140,'parent','top.location.reload();');
					exit;
				}else{
					$step_param['goodsflow_msg'] = '이용중';
					$step_param['goodsflow_err'] = $result['msg'];
					$this->goodsflowmodel->set_goodsflow_step($param['provider_seq'],$step_param);

					$result['msg'] = str_replace("'","\'",$result['msg']);
					$retuen_msg	= '아래 사유로 인해 수정이 거절되었습니다.<br/>' . $result['msg'];
					openDialogAlert($retuen_msg,400,140,'parent',$callback);
					exit;
				}
			}else{
			// 신규 신청시
				if($goodsflow_step == '1' || $goodsflow_step == '2'){
					$callback = "parent.document.location.reload();";
					openDialogAlert('서비스 신청이 불가능한 상태입니다.<br/>새로고침 후 다시 시도해주세요.',400,140,'parent',$callback);
					exit;
				}

				$result = $this->goodsflowmodel->apiSender('requestService',$param);
			}

			/* ##### goodsflow_step 에 따른 상태
				1 -> 이용중 - 정상이용
				2 -> 이용중지 - 연동신청중 (신청이력이 있는경우 자동)
				3 -> 이용중지 - 연동불가 (연동이 되지 않았을경우-굿스플로사유)
				4 -> 이용중지 - 소진(충전필요)
			##### */

			config_save("system",array('goodsflow_use'=>1));

			if($result['verified']){	// 성공적으로 저장
				if($result['verified'] == 'Y'){
					// 충전 건수 확인 충전 X 면 4로 셋팅
					$param['goodsflow_step']	= '1';
					$param['goodsflow_msg']		= '이용중';
				}else{
					$param['goodsflow_step']	= '2';
					$param['goodsflow_msg']		= '이용 중지- 연동 신청중';
				}

				$param['requestKey']		= $result['requestKey'];
				$this->goodsflowmodel->set_goodsflow_setting($param);
			}else{
				$param['goodsflow_step']	= '3';
				$param['goodsflow_msg']		= '이용 중지- 연동 불가 ( 사유 : '.$result['msg'].') ';

				// 굿스플로 API 이상으로 인한 임시처리 - 추후 삭제
				if($result['msg'] == '계약확인 중인 서비스 신청 정보가 있습니다.'){
					$param['goodsflow_step']= '2';
					$param['goodsflow_msg']	= '이용 중지- 연동 신청중';
					//$param['requestKey']			= $result['requestKey'];
				}

				$this->goodsflowmodel->set_goodsflow_setting($param);
				$callback = "parent.document.location.reload();";

				$result['msg'] = str_replace("'","\'",$result['msg']);
				openDialogAlert($result['msg'],400,200,'parent','');
				exit;
			}
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
		exit;
	}

	## 굿스플로 서비스 취소
	function goodsflow_cancel(){
		if($this->providerInfo['provider_seq']){
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= $_POST['provider_seq'];
		}
		$this->load->model('goodsflowmodel');
		if($provider_seq){
			$apiParam['requestKey'] = $_POST['requestKey'];
			$result = $this->goodsflowmodel->apiSender('cancelService',$apiParam);
			if($result['result']){
				$this->goodsflowmodel->del_goodsflow_setting($provider_seq);
			}
		}else{
			$result['result']	= false;
			$result['msg']		= "잘못된 경로입니다 (로그인정보 오류)";
		}

		echo json_encode($result);
	}

	## 택배업무자동화서비스 세팅
	function invoice_setting(){

		$this->load->model('invoiceapimodel');
		$this->load->model('shippingmodel');

		// 본사 배송정보 가져오기
		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= $_POST['provider_seq'];
			if(!$provider_seq)
				$provider_seq = 1;
		}

		if($_POST['invoice_notuse']){
			// 롯데택배 설정 삭제
			$this->invoiceapimodel->del_invoice_setting($provider_seq,'hlc');
		}else{
			$data_providershipping = $this->shippingmodel->get_shipping_base($provider_seq);

			if(!$data_providershipping['refund_address_seq']){
				openDialogAlert("기본배송그룹의 반송지 주소를 먼저 세팅해주세요.",400,140,'parent');
				exit;
			}

			$this->validation->set_rules('branch_name', '계약 대리점명','required|trim|max_length[20]|xss_clean');
			$this->validation->set_rules('auth_code[]', '신용코드','required|trim|max_length[6]|numeric|xss_clean');

			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,140,'parent',$callback);
				exit;
			}

			$arr_invoice_vendor = array();
			foreach($_POST['auth_code'] as $invoice_vendor=>$auto_code){
				if($auto_code){
					$arr_invoice_vendor[] = $invoice_vendor;
				}
			}

			if(!$arr_invoice_vendor){
				openDialogAlert("신용코드 인증이 필요합니다.",400,140,'parent');
				exit;
			}

			foreach($_POST['auth_code'] as $invoice_vendor=>$auto_code){

				$params = array();
				$params['invoice_use']	= $auto_code ? 1:0;
				$params['auth_code']	= $auto_code;

				if($invoice_vendor=='hlc'){
					$params['branch_name']	= $_POST['branch_name'];
					$params['print_type']	= $_POST['print_type'];
				}
				$params['invoice_use_date']	=  date('Y-m-d H:i:s');

				$this->invoiceapimodel->set_invoice_setting($provider_seq,$invoice_vendor,$params);
			}
		}

		$callback = "parent.document.location.reload();";
		openDialogAlert("설정이 저장 되었습니다.",400,140,'parent',$callback);
	}

	## 롯데택배 신용코드 인증
	function hlc_auth(){
		$this->load->model('invoiceapimodel');
		$result = $this->invoiceapimodel->hlc_auth($_POST['auth_code']);
		echo json_encode($result);
	}

	public function export_default_search()
	{
		$this->load->model('searchdefaultconfigmodel');

		$param_order = array(
				'search_page' => 'admin/order/order_export_popup',
				'order_default_date_field' => $_POST['order_default_date_field'],
				'order_default_period'		=> $_POST['order_default_period'],
				'order_default_step'		=> $_POST['order_default_step'],
				'order_detail_view'			=> $_POST['order_detail_view']
		);
		$this->searchdefaultconfigmodel->set_search_default($param_order);

		$param_export = array(
				'search_page' => 'admin/export/batch_status',
				'export_default_date_field' => $_POST['export_default_date_field'],
				'export_default_period'		=> $_POST['export_default_period'],
				'export_default_status'		=> $_POST['export_default_status'],
				'export_detail_view'			=> $_POST['export_detail_view']
		);
		$this->searchdefaultconfigmodel->set_search_default($param_export);

		$callback = "parent.closeDialog('search_detail_dialog');";
		openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
	}

	// 입력된 장소 정보 추출 :: 2016-06-08 lwh
	public function get_shipping_address_ajax(){
		$this->load->model('shippingmodel');
		$result = $this->shippingmodel->get_shipping_address($_POST['seq']);

		echo json_encode($result);
	}

	// 장소 리스트 등록 및 수정 :: 2016-06-07 lwh
	public function set_shipping_address(){
		$this->load->model('shippingmodel');
		$data = $_POST;

		if($data['address_category'] == 'direct_input'){
			$this->validation->set_rules('address_category_direct', '분류','required|trim|xss_clean');
		}else{
			$this->validation->set_rules('address_category', '분류','required|trim|xss_clean');
		}

		$this->validation->set_rules('zoneZipcode[]', '우편번호','required|trim|xss_clean');
		if($data['address_nation'] == 'global'){
			$this->validation->set_rules('international_country', '국가','required|trim|xss_clean');
			$this->validation->set_rules('international_town_city', '도시','required|trim|xss_clean');
			$this->validation->set_rules('international_county', '주/도','required|trim|xss_clean');
			$this->validation->set_rules('international_address', '주소','required|trim|xss_clean');
		}else{
			$this->validation->set_rules('zoneAddressDetail', '주소','required|trim|xss_clean');
		}

		$this->validation->set_rules('address_name', '명칭','required|trim|xss_clean');
		$this->validation->set_rules('shipping_phone', '연락처','required|trim|xss_clean');

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		if (defined('__SELLERADMIN__') === true) {
			$provider_seq	= $this->providerInfo['provider_seq'];
		}else{
			$provider_seq	= $_POST['provider_seq'];
			if(!$provider_seq)	$provider_seq = 1;
		}
		$data['address_provider_seq'] = $provider_seq;

		// 등록 / 수정 model 에서 처리
		$this->shippingmodel->set_shipping_address($data);
		$msg = '등록되었습니다.';
		if($data['shipping_address_seq'])	$msg = '수정되었습니다.';

		echo "<script>alert('".$msg."');parent.closeDialog('shipping_address_insert');parent.document.insFrm.reset();parent.international_chg();parent.category_chg();parent.applyAddress();</script>";
		exit;
	}

	// ###:1 배송설정 item 추가 :: 2016-06-09 lwh
	public function add_shipping_item(){

		$this->load->model('shippingmodel');

		// --- 수정 시 params 제작 :: START
		if($_GET['mode'] == 'modify'){
			$grp_seq = $_GET['grp_seq'];
			if(!$grp_seq){
				$callback = "parent.document.location.href='./shipping_group'";
				openDialogAlert('해당그룹 정보가 누락되었습니다<br/>다시 새로고침 후 시도하여 주세요.',400,160,'parent',$callback);
				exit;
			}

			// 수정용 POST 값 제작
			$limit = ($_GET['num']) ? $_GET['num'] : 0; // 1개씩 가져오기
			$params = $this->shippingmodel->ship_set_modify_params($grp_seq,$limit);

			if($params)		$next_num = $limit + 1;
			else			exit;

			// 필수값 체크를 위한 데이터
			$_POST = $params;
		}else{
			$params = $_POST;
		}

		// --- 수정 시 params 제작 :: END

		$ship_set_code		= $this->shippingmodel->ship_set_code;
		$shipping_type_arr	= $this->shippingmodel->shipping_type_arr;
		$shipping_otp_type	= $this->shippingmodel->shipping_otp_type;
		$weekday			= $this->shippingmodel->weekday;

		// ### 배송저장에 변수정의 :: START ### //
		$shipping_group_seq = $params['shipping_group_seq']; // 최상위 그룹번호
		$ship_set	= array(); // 배송 설정
		$ship_opt	= array(); // 배송 방법
		$ship_cost	= array(); // 배송 금액
		$ship_zone	= array(); // 배송 지역
		$ship_store = array(); // 수령매장
		// ### 배송저장에 변수정의 :: END ### //

		$this->validation->set_rules('shipping_set_code', '배송설정','required|trim|xss_clean');

		$ship_set['shipping_set_code']	= $params['shipping_set_code']; // 배송설정 코드
		if($params['custom_set_use']=='Y'){ // 배송설정 명 정의
			$this->validation->set_rules('shipping_set_name', '배송설정 명','required|trim|xss_clean');
			$ship_set['shipping_set_name']	= $params['shipping_set_name'];
			$ship_set['custom_set_use']		= $params['custom_set_use'];
		}else{
			$ship_set['shipping_set_name']	= $ship_set_code[$params['shipping_set_code']];
		}

		$ship_set['shipping_set_seq']		= $params['shipping_set_seq'];	// 수정시 Seq 정보 추출
		$ship_set['prepay_info']			= $params['prepay_info'];		// 배송비 결제정보
		$ship_set['delivery_nation']		= $params['delivery_nation'];	// 배송가능국가
		$ship_set['delivery_type']			= $params['delivery_type'];		// 구매방식 - basic (1회구매)
		$ship_set['delivery_limit']			= $params['delivery_limit'];	// 배송지역 제한 여부

		// 기본값 설정
		if($ship_set['delivery_limit'] == 'unlimit'){
			if($params['delivery_nation'] == 'korea'){
				$params['shipping_area_name']['std'][0] = '대한민국';
			}else{
				$params['shipping_area_name']['std'][0] = '전세계';
			}
		}

		if($params['add_use'] == 'Y'){
			if($params['delivery_nation'] == 'global'){
				if(!$params['shipping_area_name']['add'][0]){
					$params['shipping_area_name']['add'][0] = '국가1';
				}
			}else{
				if(!$params['shipping_area_name']['add'][0]){
					$params['shipping_area_name']['add'][0] = '지역1';
				}
			}
		}

		foreach($shipping_type_arr as $type => $tit){

			// 사용여부 체크
			$ship_set[$type]['use_yn'] = $params[$type.'_use'];

			// 사용 시
			if($ship_set[$type]['use_yn'] == 'Y'){

				// 배송방법 타입
				$ship_opt[$type]['shipping_opt_type'] = $params['shipping_opt_type'][$type];

				// 무료인 및 고정인 경우 Fix 값 - 그외 단위 지정
				$ship_opt[$type]['shipping_opt_unit'] = $this->config_system['basic_currency'];
				if($ship_opt[$type]['shipping_opt_type'] == 'free' || $ship_opt[$type]['shipping_opt_type'] == 'fixed'){
					unset($params['section_st'][$type]);
					unset($params['section_ed'][$type]);
					$params['section_st'][$type][0]		= 0;
					$params['section_ed'][$type][0]		= 0;
				}else if($ship_opt[$type]['shipping_opt_type'] == 'cnt' || $ship_opt[$type]['shipping_opt_type'] == 'cnt_rep'){ // 수량
					$ship_opt[$type]['shipping_opt_unit'] = '개';
				}else if($ship_opt[$type]['shipping_opt_type'] == 'weight' || $ship_opt[$type]['shipping_opt_type'] == 'weight_rep'){ // 무게
					$ship_opt[$type]['shipping_opt_unit'] = 'Kg';
				}

				// 지역별 설정 정보 무결성 검사
				foreach($params['issue'][$type] as $k => $val){
					if($val >= '1'){
						$_POST['zone_chk'][$type][$k] = '1';
					}else{
						unset($_POST['zone_chk'][$type][$k]);
						openDialogAlert($tit . ' 배송비의 지역정보 항목은 필수입니다.',400,150,'parent',$callback);
						exit;
					}
					$this->validation->set_rules('zone_chk['.$type.']['.$k.']', '지역정보','required|trim|xss_clean');
				}

				// 각 배송비 구간 결과 추출
				$ins = 0;
				$idx = 0;
				$ship_cost_sum = 0;
				foreach($params['section_st'][$type] as $s => $section){
					$ship_opt[$type]['section_st'][$s] = $section; // 시작구간
					$ship_opt[$type]['section_ed'][$s] = $params['section_ed'][$type][$s];	// 끝구간

					// 지역
					$idx2 = 0;
					foreach($params['shipping_area_name'][$type] as $z => $zone){
						$ship_cost[$type][$s]['shipping_area_name'][$z] = $zone; // 지역명
						if($type == 'hop'){
							// 당일배송 검증 - 당일배송이 가능하면 어느지역이든 당일배송이 존재하여야함.
							if($params['hopeday_limit_set'] == 'time' && array_search('Y',$params['today_yn']) === FALSE){
								openDialogAlert('희망배송일의 당일배송비가 1개이상 설정되어야 합니다.',400,150,'parent',$callback);
								exit;
							}
							$ship_cost[$type][$s]['shipping_today_yn'][$z] = $params['today_yn'][$idx2]; // 당일여부
							if($ship_cost[$type][$s]['shipping_today_yn'][$z] == 'Y'){
								$ship_cost[$type][$s]['shipping_cost_today'][$z] = $params['shipping_cost_today'][$type][$ins];
								$ins++;
							}
						}

						// 배송비 매칭
						$ship_cost[$type][$s]['shipping_cost'][$z] = ($params['shipping_cost'][$type][$idx]) ? $params['shipping_cost'][$type][$idx] : 0;
						$ship_cost[$type][$s]['shipping_cost_seq'][$z] = ($params['shipping_cost_seq'][$type][$idx]) ? $params['shipping_cost_seq'][$type][$idx] : 0;
						$idx ++;

						// 무료가 아닌경우 배송비 합계 계산
						if($ship_opt[$type]['shipping_opt_type'] != 'free'){
							$ship_cost_sum += $ship_cost[$type][$s]['shipping_cost'][$z];
						}

						// 지역 매칭
						if($params['delivery_nation'] != 'korea'){
							foreach($params['sel_address_street'][$type][$z] as $k => $address){
								$ship_zone[$type]['seq'] = $params['shipping_opt_seq_list'][$type][0];
								$ship_zone[$type]['sel_address_street'][$z][$k]	= $address;
								$ship_zone[$type]['sel_address_zibun'][$z][$k]	= $params['sel_address_zibun'][$type][$z][$k];
								$ship_zone[$type]['sel_address_join'][$z][$k]	= $params['sel_address_join'][$type][$z][$k];
								$ship_zone[$type]['sel_address_txt'][$z][$k]	= $params['sel_address_txt'][$type][$z][$k];
							}
						}
						$idx2++;
					}
				}

				// 배송비가 지정되는경우 예외처리:: 2017-01-06 lwh // $tit - 타입명
				if($type != 'store' && $ship_opt[$type]['shipping_opt_type'] != 'free'){
					// 배송비 검증 추가
					if($ship_cost_sum < 1){
						openDialogAlert("입력된 " . $tit . " 배송비가 모두 \'0\'입니다.<br/>" . $tit . " 배송비 유형을 \'무료\'로 선택하세요.",440,160,'parent',$callback);
						exit;
					}

					// 구간 무결성 검사
					$section_ed_cnt = count($params['section_ed'][$type]) - 1;
					if($params['section_ed'][$type][$section_ed_cnt] < 1 && preg_match('/_rep/', $ship_opt[$type]['shipping_opt_type'])){
						openDialogAlert($tit . ' 배송비 구간의 마지막은 \'0\'이 될수 없습니다.',400,150,'parent',$callback);
						exit;
					}
				}

				// 배송안내 설정
				$ship_set[$type]['delivery_info_type'] = $params['delivery_'.$type.'_type'];
				if($ship_set[$type]['delivery_info_type'] == 'N'){ // 직접입력 시 메세지
					$this->validation->set_rules('delivery_'.$type.'_input', '직접입력시 배송안내','required|trim|xss_clean');
					$ship_set[$type]['delivery_info_input'] = $params['delivery_'.$type.'_input'];
				}

				// 각종 예외처리 시작
				if			($type == 'hop')		{ // 희망배송일 예외처리
					$ship_set['npay_order_use']		= false;					//네이버페이 주문불가-희망배송일 사용
					// 희망배송일 선택설정
					$ship_set['hopeday_required']	= $params['hopeday_required'];
					// 희망배송일 선택 시작일 타입 - default 값 지정
					$ship_set['hopeday_limit_set']	= ($params['hopeday_limit_set']) ? $params['hopeday_limit_set'] : 'time';
					// 희망배송일 선택 시작일 설정 - default 값 지정
					$ship_set['hopeday_limit_val'] = ($params['hopeday_limit_val_'.$ship_set['hopeday_limit_set']]) ? $params['hopeday_limit_val_'.$ship_set['hopeday_limit_set']] : '1330';
					$this->validation->set_rules('hopeday_limit_val_'.$ship_set['hopeday_limit_set'], '주문당일 설정','required|trim|xss_clean');
					// 희망배송일 요일불가 설정
					$hopeday_limit_week = array(0,0,0,0,0,0,0);
					if($params['hopeday_limit_week']){
						$hopeday_week_arr = $params['hopeday_limit_week'];
						foreach($hopeday_week_arr as $k => $week){
							$hopeday_limit_week[$week] = 1;
						}
					}
					$ship_set['hopeday_limit_week']			= $hopeday_limit_week;
					$ship_set['hopeday_limit_week_real']	= implode('',$hopeday_limit_week);

					// 희망배송일 반복선택불가일
					$limit_repeat_day_arr = explode(',',preg_replace('/[^0-9\-\,]/','',$params['hopeday_limit_repeat_day'])); // 지정 문자 외 삭제 후 검사
					sort($limit_repeat_day_arr);
					foreach($limit_repeat_day_arr as $k => $day){
						if(date('Y-m-d',strtotime(date('y').'-'.$day)) != '1970-01-01'){
							$tmp_repeat_day[] = $day;
						}
					}
					$limit_repeat_day = implode(',',$tmp_repeat_day);
					$ship_set['hopeday_limit_repeat_day'] = $limit_repeat_day;

					//희망배송일 선택불가일
					$hope_year = $params['hope_year'];
					sort($hope_year);
					foreach($hope_year as $k => $year){
						$year = preg_replace('/[^0-9]/','',$year);
						unset($limit_day_arr);
						$limit_day_arr = explode(',',preg_replace('/[^0-9\-\,]/','',$params['hopeday_limit_day'][$k])); // 지정 문자 외 삭제 후 검사
						sort($limit_day_arr);
						foreach($limit_day_arr as $i => $day){
							if(date('Y-m-d',strtotime($year.'-'.$day)) != '1970-01-01'){
								$tmp_day[] = $year.'-'.$day;
								$tmp_serialize[$year][] = $day;
							}
						}
					}
					sort($tmp_day);
					$limit_day = implode(',',$tmp_day);
					$limit_day_serialize = serialize($tmp_serialize);
					$ship_set['hopeday_limit_day'] = $limit_day;
					$ship_set['limit_day_serialize'] = $limit_day_serialize;
					$ship_set['limit_day_tmp'] = $tmp_serialize;
				} else if	($type == 'store')		{ // 수령매장 예외처리
					if($_GET['mode'] == 'modify'){
						$this->validation->set_rules('shipping_address_seq[]', '수령매장 설정','required|trim|xss_clean');
					}else{
						$this->validation->set_rules('shipping_address_seq[]', '수령매장 설정','required|trim|xss_clean');
					}
					// 연결된 수령매장
					if($params['shipping_address_seq']){
						$shipping_address_seq = $params['shipping_address_seq'];
						foreach($shipping_address_seq as $k => $address_seq){
							// 배송지 고유번호
							$ship_store['rel'][$k]['shipping_address_seq'] = $address_seq;
							// 수령매장명
							$ship_store['rel'][$k]['shipping_store_name'] = $params['shipping_store_name'][$k];
							// 매장 전화번호
							$ship_store['rel'][$k]['store_phone'] = $params['store_phone'][$k];
							// 수령매장 창고연결여부
							$ship_store['rel'][$k]['store_scm_type'] = $params['store_scm_type'][$k];
							// 수령매장 재고설정
							$ship_store['rel'][$k]['store_supply_set'] = $params['store_supply_set'][$k];
							// 수령매장 재고보기설정
							$ship_store['rel'][$k]['store_supply_set_view'] = $params['store_supply_set_view'][$k];
							// 수령매장 재고수량설정
							$ship_store['rel'][$k]['store_supply_set_order'] = $params['store_supply_set_order'][$k];

							// 분류
							$ship_store['tmp'][$k]['shipping_address_category'] = $params['shipping_address_category'][$k];
							// 해외여부
							$ship_store['tmp'][$k]['shipping_address_nation'] = $params['shipping_address_nation'][$k];
							// 주소
							$ship_store['tmp'][$k]['shipping_address_full'] = trim($params['shipping_address_full'][$k]);
						}
					}
				}
			}else{ // 사용안할 시
				continue;
			}

			$getDatas = array(
				'shipping_set_seq'		=> $params['shipping_set_seq'],
				'shipping_group_seq'	=> $params['shipping_group_seq'],
				'shipping_set_type'		=> $type
			);
			$optSeqs = $this->shippingmodel->get_option_seqs($getDatas);
			$shipping_opt_seq[$type]	= $optSeqs;

			if(!$params['zone_count']){
				if(count($optSeqs) > 0){
					$getDatas = array(
						'shipping_set_seq'		=> $params['shipping_set_seq'],
						'shipping_group_seq'	=> $params['shipping_group_seq'],
						'shipping_set_type'		=> $type,
						'delivery_limit'		=> 'limit'
					);
					$costDatas = $this->shippingmodel->get_cost_seqs($optSeqs, $getDatas);

					$costs = end($costDatas);
					foreach($costs as $seq){
						$zone_count = $this->shippingmodel->get_shipping_zone_count($seq,'shipping_cost_seq');
						$params['zone_count'][$type][$k] = $zone_count[0]['shipping_zone_count'];
						$params['zone_cost_seq'][$type][$k] = $seq;
						$k++;
					}
				}
			}
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			echo "<pre>";
			print_r($err);
			echo "</pre>";
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$this->tempate_modules();
		if($_GET['mode'] == 'modify'){
			$this->template->assign("mode",'modify');
			$this->template->assign("grp_seq",$grp_seq);
			$this->template->assign("num",$next_num);
			$this->template->assign("default_yn", $params['default_yn']);
		}

		// 기존 등록내용 수정시
		if($params['idx']){
			$this->template->assign("idx",$params['idx']);
		}

		# 해당 배송정책이 네이버페이/톡체크아웃 주문시 사용가능한지 체크 @2016-10-17
		list($npay_possible,$npay_impossible_message) = $this->shippingmodel->add_shipping_partner_possible_check($params);
		$ship_set['npay_order_possible']		= ($npay_possible)? "Y":"N";
		$ship_set['npay_order_impossible_msg']	= ($npay_impossible_message)? implode(", ",$npay_impossible_message):"";
		list($talkbuy_possible,$talkbuy_impossible_message) = $this->shippingmodel->add_shipping_partner_possible_check($params, "talkbuy");
		$ship_set['talkbuy_order_possible']		= ($talkbuy_possible)? "Y":"N";
		$ship_set['talkbuy_order_impossible_msg']	= ($talkbuy_impossible_message)? implode(", ",$talkbuy_impossible_message):"";

		// 반품 배송비 관련 추가 :: 2018-05-10 lwh
		$ship_set['refund_shiping_cost']		= ($params['refund_shiping_cost'])	? $params['refund_shiping_cost'] : 0;
		$ship_set['swap_shiping_cost']			= ($params['swap_shiping_cost'])	? $params['swap_shiping_cost'] : 0;
		$ship_set['shiping_free_yn']			= ($params['shiping_free_yn'])		? $params['shiping_free_yn'] : 'N';

		$this->template->assign("punit",'KRW'); // config 에서 불러옴
		$this->template->assign(array(
			'shipping_group_seq'=> $shipping_group_seq,
			'shipping_group_real_seq'=> $params['shipping_group_real_seq'],
			'ship_set_code'		=> $ship_set_code,
			'shipping_type'		=> $shipping_type_arr,
			'shipping_otp_type'	=> $shipping_otp_type,
			'weekday'			=> $weekday,
			'ship_set'			=> $ship_set,
			'ship_opt'			=> $ship_opt,
			'ship_cost'			=> $ship_cost,
			'ship_store'		=> $ship_store,
			'zone_count'		=> $params['zone_count'],
			'zone_cost_seq'		=> $params['zone_cost_seq'],
			'shipping_opt_seq'	=> $shipping_opt_seq
		));

		if($params['delivery_nation'] != 'korea'){
			$this->template->assign('ship_zone', $ship_zone);
		}

		$file_path	= str_replace('setting_process/add_shipping_item.html', 'setting/add_national_view.html', $this->template_path());
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	// ###:2 배송그룹 최종 저장 :: 2016-06-16 lwh
	public function save_shipping_group(){
		if($this->input->post('provider_seq') != $this->providerInfo['provider_seq']){
			echo "<script>alert('권한 없음');history.go(-1);</script>";
			exit;
		}

		if($_POST['shipping_group_real_seq'] > 0){
			$_POST['shipping_group_seq'] = $_POST['shipping_group_real_seq'];
		}

		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_shipping_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,160,'parent',$callback);
			exit;
		}

		// 임시 저장 제한 POST 값의 제한으로 인한 제한 :: 2017-01-02 lwh
		$cnt_kr = count($_POST['shipping_set_code']['korea']);
		$cnt_gl = count($_POST['shipping_set_code']['global']);
		if( ($cnt_kr + $cnt_gl) > 6 ){
			openDialogAlert('한 배송그룹 내에 배송방법은 6개를 넘을 수 없습니다.',400,140,'parent','');
			exit;
		}

		$this->validation->set_rules('shipping_group_name', '배송그룹명','required|trim|xss_clean');
		$this->validation->set_rules('refund_address_seq', '반송지','required|trim|xss_clean');
		$this->validation->set_rules('default_yn', '관리=>기본','required|trim|xss_clean');

		$callback = "parent.document.location.href='./shipping_group'";
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			openDialogAlert($err['value'],400,160,'parent','');
			exit;
		}

		// ### 배송저장에 변수정의 :: START ### //
		$grp_sum	= array(); // 배송 그룹 요약
		$ship_grp	= array(); // 배송 그룹
		$ship_set	= array(); // 배송 설정
		$ship_opt	= array(); // 배송 방법
		$ship_cost	= array(); // 배송 금액
		$ship_zone	= array(); // 배송 지역
		$ship_store = array(); // 수령매장
		// ### 배송저장에 변수정의 :: END ### //

		//더미 데이터 삭제용
		$set_seqs = array();
		$cost_seqs = array();

		$this->load->model('shippingmodel');
		$this->db->trans_begin();

		// 더미 데이터 유효성 검사
		$shipping_group_dummy = $this->shippingmodel->get_shipping_group($this->input->post('shipping_group_dummy_seq'));
		if(!$shipping_group_dummy) {
			$callback = "parent.document.location.href='./shipping_group'";
			openDialogAlert("다른 관리자가 수정중 입니다.<br/>다시 시도해주세요.", 400, 180, 'parent', $callback);
			exit;
		}

		if($_POST['shipping_group_real_seq'] > 0){
			$this->shippingmodel->reset_shipping($_POST['shipping_group_real_seq'], $shipping_group_dummy['shipping_group_seq'], $_POST['shipping_calcul_type']);
		}
		$ship_nation		= array('korea', 'global');
		$ship_set_code		= $this->shippingmodel->ship_set_code;
		$shipping_type_arr	= $this->shippingmodel->shipping_type_arr;
		$shipping_otp_type	= $this->shippingmodel->shipping_otp_type;
		$weekday			= $this->shippingmodel->weekday;

		$nowDate = date('Y-m-d H:i:s');

		// 업데이트 모드
		if($_POST['shipping_group_seq']){
			$reg_mode = 'modify';
		}else{
			$reg_mode = 'save';
		}

		// ### 배송그룹 저장 :: START
		$ship_grp['shipping_group_name']		= $_POST['shipping_group_name'];
		$ship_grp['shipping_group_type']		= $_POST['shipping_group_type'];
		$ship_grp['shipping_calcul_type']		= $_POST['shipping_calcul_type'];
		$ship_grp['shipping_calcul_free_yn']	= ($_POST[$ship_grp['shipping_calcul_type'].'_calcul_free_yn']) ? 'Y' : 'N';
		$ship_grp['shipping_std_free_yn']		= ($_POST[$ship_grp['shipping_calcul_type'].'_std_free_yn']) ? 'Y' : 'N';
		$ship_grp['shipping_add_free_yn']		= ($_POST[$ship_grp['shipping_calcul_type'].'_add_free_yn']) ? 'Y' : 'N';
		$ship_grp['shipping_hop_free_yn']		= ($_POST[$ship_grp['shipping_calcul_type'].'_hop_free_yn']) ? 'Y' : 'N';
		$ship_grp['sendding_scm_type']			= $_POST['refund_scm_type'];
		$ship_grp['sendding_address_seq']		= $_POST['refund_address_seq'];
		$ship_grp['refund_address_seq']			= $_POST['refund_address_seq'];
		$ship_grp['refund_scm_type']			= $_POST['refund_scm_type'];
		$ship_grp['provider_shipping_use']		= $_POST['provider_shipping_use'];
		$ship_grp['shipping_provider_seq']		= $this->input->post('provider_seq');
		$ship_grp['target_goods_cnt']			= ($_POST['rel_goods_seq']) ? count($_POST['rel_goods_seq']) : 0;
		$ship_grp['admin_memo']					= addslashes($_POST['admin_memo']);
		$ship_grp['default_yn']					= ($_POST['base_grp']=='Y') ? 'Y':'N';
		$ship_grp['provider_shipping_use']		= ($_POST['base_grp']=='Y') ? 'Y':'N';
		$ship_grp['update_date']				= $nowDate;
		if($reg_mode == 'save'){
			$ship_grp['regist_date']			= $nowDate;
			$mode_msg = '등록';
		}else{
			$mode_msg = '수정';
		}

		// 시스템 메모 저장
		$ship_grp['system_memo'] = $nowDate . ' ' . $this->providerInfo['provider_name'] . '(' . $this->providerInfo['provider_id'] . ') ' . $mode_msg . ' [' . $_SERVER['REMOTE_ADDR'] . ']';

		// ## fm_shipping_grouping Save
		if($reg_mode == 'save'){
			$shipping_group_seq = $this->shippingmodel->set_shipping_group($ship_grp);
			if(!$shipping_group_seq){
				openDialogAlert($fail_msg.'<br/>ErrCode: 01',400,140,'parent',$callback);
				exit;
			}
		}else{
			$shipping_group_seq = $this->shippingmodel->set_shipping_group($ship_grp);

			// 하위 데이터 지우기
			$this->shippingmodel->reset_shipping_group($shipping_group_seq);

			// 삭제된 set 설정 지우기
			foreach($_POST['delete_set_seq'] as $k => $del_set_seq){
				$this->shippingmodel->del_shipping_set($del_set_seq);
			}
		}

		// ### 배송그룹 저장 :: END

		// ### 배송그룹연결상품 조정
		$this->shippingmodel->group_cnt_adjust();

		// failed_msg
		$fail_msg = $mode_msg . '에 실패하였습니다.<br/>새로고침 후 다시 시도해주세요.';

		// -요약- 배송그룹번호
		$grp_sum['shipping_group_seq']			= $shipping_group_seq;
		// 기본배송방법 추출
		$tmpDefault = explode('_',$_POST['default_yn']);
		$default_yn[$tmpDefault[0]][$tmpDefault[1]] = 'Y';

		// 국가별 LOOP START
		foreach($ship_nation as $nation_key => $nation){
			if($nation != 'korea'){
				$this->shippingmodel->del_shipping_addr_global($shipping_group_seq);
			}

			// 배송설정 LOOP START
			foreach($_POST['shipping_set_code'][$nation] as $setKey => $set_code){

				// ### 배송설정 저장 :: START
				unset($ship_set);
				$ship_set['shipping_group_seq']			= $shipping_group_seq; // group_seq FK
				$ship_set['shipping_set_seq']			= $_POST['shipping_set_seq'][$nation][$setKey];
				$ship_set['default_yn']					= ($default_yn[$nation][$setKey]=='Y') ? 'Y' : 'N';
				$ship_set['shipping_set_code']			= $set_code; // 설정 코드명
				$ship_set['shipping_set_name']			= $_POST['shipping_set_name'][$nation][$setKey]; // 배송설정 명
				$ship_set['prepay_info']				= $_POST['prepay_info'][$nation][$setKey]; // 선불/착불정보
				$ship_set['delivery_nation']			= $_POST['delivery_nation'][$nation][$setKey]; // 배송가능국가
				$ship_set['delivery_type']				= $_POST['delivery_type'][$nation][$setKey]; // 구매방식
				// 네이버페이 주문시 해당 배송정책 사용 가능여부
				$ship_set['npay_order_possible']		= $_POST['npay_order_possible'][$nation][$setKey];
				$ship_set['npay_order_impossible_msg']	= $_POST['npay_order_impossible_msg'][$nation][$setKey];

				// 톡체크아웃 주문시 해당 배송정책 사용 가능여부
				$ship_set['talkbuy_order_possible']		 = $_POST['talkbuy_order_possible'][$nation][$setKey];
				$ship_set['talkbuy_order_impossible_msg']= $_POST['talkbuy_order_impossible_msg'][$nation][$setKey];

				$ship_set['delivery_limit'] = ($_POST['delivery_limit'][$nation][$setKey]) ? $_POST['delivery_limit'][$nation][$setKey] : 'unlimit'; // 배송지역 제한
				// 추가배송비 사용여부
				$ship_set['add_use'] = ($_POST['add_use'][$nation][$setKey]=='Y') ? 'Y' : 'N';
				// 희망배송일 사용여부
				$ship_set['hop_use'] = ($_POST['hop_use'][$nation][$setKey]=='Y') ? 'Y' : 'N';
				// 수령매장 사용여부
				$ship_set['store_use'] = ($_POST['store_use'][$nation][$setKey]=='Y') ? 'Y' : 'N';
				// 희망배송일 선택설정
				$ship_set['hopeday_required'] = ($_POST['hopeday_required'][$nation][$setKey]=='Y') ? 'Y' : 'N';
				// 희망배송일 선택 시작일 타입
				$ship_set['hopeday_limit_set'] = $_POST['hopeday_limit_set'][$nation][$setKey];
				// 희망배송일 선택 시작일 설정
				$ship_set['hopeday_limit_val'] = $_POST['hopeday_limit_val'][$nation][$setKey];
				// 희망배송일 요일불가 설정
				$ship_set['hopeday_limit_week'] = $_POST['hopeday_limit_week'][$nation][$setKey];
				// 희망배송일 반복선택불가일
				$ship_set['hopeday_limit_repeat_day'] = $_POST['hopeday_limit_repeat_day'][$nation][$setKey];
				// 희망배송일 선택불가일
				$ship_set['hopeday_limit_day'] = $_POST['hopeday_limit_day'][$nation][$setKey];
				// 희망배송일 선택불가일 serialize
				$ship_set['limit_day_serialize'] = $_POST['limit_day_serialize'][$nation][$setKey];

				// 반품 배송비 관련 추가 :: 2018-05-14 lwh
				$ship_set['refund_shiping_cost']	= $_POST['refund_shiping_cost'][$nation][$setKey];
				$ship_set['swap_shiping_cost']		= $_POST['swap_shiping_cost'][$nation][$setKey];
				$ship_set['shiping_free_yn']		= $_POST['shiping_free_yn'][$nation][$setKey];

				// 배송비 안내타입 저장
				foreach($shipping_type_arr as $set_type => $tit){
					// 배송비 안내 타입
					$ship_set['delivery_'.$set_type.'_type'] = $_POST['delivery_'.$set_type.'_type'][$nation][$setKey];
					// 배송비 직접입력
					$ship_set['delivery_'.$set_type.'_input'] = $_POST['delivery_'.$set_type.'_input'][$nation][$setKey];
				}
				// ## fm_shipping_set Save
				if($reg_mode == 'save'){
					$shipping_set_seq = $this->shippingmodel->set_shipping_set($ship_set);
				}else{
					if($_POST['shipping_set_seq'][$nation][$setKey]){
						$shipping_set_seq = $_POST['shipping_set_seq'][$nation][$setKey];
						if(!$shipping_set_seq){
							openDialogAlert($fail_msg.'<br/>ErrCode: 02',400,140,'parent',$callback);
							exit;
						}
						$ship_set['shipping_set_seq'] = $shipping_set_seq;
						$this->shippingmodel->set_shipping_set($ship_set);
					}else{
						$shipping_set_seq = $this->shippingmodel->set_shipping_set($ship_set);
					}
				}

				if(!$shipping_set_seq){
					// 배송그룹 롤백
					//$this->shippingmodel->del_shipping_group($shipping_group_seq);
					openDialogAlert($fail_msg.'<br/>ErrCode: 02',400,140,'parent',$callback);
					exit;
				} else {
					$set_seqs[] = $shipping_set_seq;
				}
				// ### 배송설정 저장 :: END
				// 배송설정 타입 Loop START
				unset($infostr);
				unset($set_sum);
				foreach($shipping_type_arr as $set_type => $tit){

				// 사용 시 에만 저장 - 예외처리
				if	($ship_set[$set_type.'_use'] == 'N' || $set_type == 'store' || $set_type == 'reserve')	continue;

				// 최대값/최소값 초기화
				if($set_type == 'std' || $set_type == 'add'){
					${$set_type.'_max_cost'} = 0;
					${$set_type.'_min_cost'} = 9999999;
				}

				// 구간 LOOP START
				$section_st_arr = $_POST['section_st'][$nation][$setKey][$set_type];
				$section_ed_arr = $_POST['section_ed'][$nation][$setKey][$set_type];

				foreach($section_st_arr as $otpKey => $section_st){
					// ### 배송방법 저장 :: START
					$ship_opt['shipping_opt_seq'] = $_POST['shipping_opt_seq'][$nation][$setKey][$set_type][$otpKey];
					$ship_opt['shipping_group_seq'] = $shipping_group_seq; // group_seq FK
					$ship_opt['shipping_set_seq'] = $shipping_set_seq; // set_seq FK
					$ship_opt['shipping_set_code'] = $set_code; // set_code FK

					// 배송 입점사 번호 - 반정규화
					$ship_opt['shipping_provider_seq'] = $ship_grp['shipping_provider_seq'];
					// 배송설정명 - 반정규화
					$ship_opt['shipping_set_name'] = $ship_set['shipping_set_name'];

					// 배송설정 타입
					$ship_opt['shipping_set_type'] = $set_type;
					// 배송방법 타입
					$ship_opt['shipping_opt_type'] = $_POST['shipping_opt_type'][$nation][$setKey][$set_type];

					// 배송지역 제한 - 반정규화
					$ship_opt['delivery_limit'] = ($set_type=='std') ? $ship_set['delivery_limit'] : 'limit';
					// 배송비 기준 여부 - 반정규화
					$ship_opt['default_yn'] = $ship_set['default_yn'];

					// 시작구간
					$ship_opt['section_st'] = $section_st_arr[$otpKey];
					// 끝구간
					$ship_opt['section_ed'] = $section_ed_arr[$otpKey];

					// 배송비계산기준 - 반정규화
					$ship_opt['shipping_calcul_type'] = $ship_grp['shipping_calcul_type'];
					// 계산 무료화 여부 - 반정규화
					$ship_opt['shipping_calcul_free_yn'] = $ship_grp['shipping_calcul_free_yn'];
					// 기본배송비 무료화 - 반정규화
					$ship_opt['shipping_std_free_yn'] = $ship_grp['shipping_std_free_yn'];
					// 추가배송비 무료화 - 반정규화
					$ship_opt['shipping_add_free_yn'] = $ship_grp['shipping_add_free_yn'];
					// 희망배송일 무료화 - 반정규화
					$ship_opt['shipping_hop_free_yn'] = $ship_grp['shipping_hop_free_yn'];

					// 무료계산시 기본 배송비는 무조건 무료 -> 검증추가
					$shipping_opt_seq = false;
					if($ship_grp['shipping_calcul_type'] == 'free' && $set_type == 'std' && $ship_opt['shipping_opt_type'] != 'free'){
						$fail_msg = '배송비 계산기준이 `무료`일때 기본 배송비는 `무료`가 아닐수 없습니다.';
					} else {
						// ## fm_shipping_option Save
						$shipping_opt_seq = $this->shippingmodel->set_shipping_opt($ship_opt);
					}

					if(!$shipping_opt_seq){
						// 배송그룹 롤백
						$this->shippingmodel->del_shipping_group($shipping_group_seq);
						// 배송설정 롤백
						$this->shippingmodel->del_shipping_set($shipping_set_seq);
						openDialogAlert($fail_msg.'<br/>ErrCode: 04',400,140,'parent',$callback);
						exit;
					}
					// ### 배송방법 저장 :: END

					// -요약- 무료배송여부
					if($set_type == 'std' && $ship_set['default_yn'] == 'Y'){
						// 기본 배송방법
						$grp_sum['free_set_code']	= $set_code;
						$grp_sum['first_cost']		= $_POST['shipping_cost'][$nation][$setKey][$set_type][0][0];

						// 기본 배송방법 배송비 결제타입
						$grp_sum['prepay_info']		= $ship_set['prepay_info'];

						// 기본 배송체크
						$grp_sum['free_shipping_use']	= 'N';
						$grp_sum['fixed_cost']			= Null;
						if($ship_opt['shipping_opt_type'] == 'free'){
							$grp_sum['default_type']		= 'free';
							$grp_sum['free_shipping_use']	= 'Y';
						}else{
							if($ship_opt['shipping_opt_type'] == 'fixed' && $ship_set['delivery_limit'] == 'unlimit'){
								$grp_sum['default_type']	= 'fixed';
								$grp_sum['fixed_cost']		= $_POST['shipping_cost'][$nation][$setKey][$set_type][0][0];
							}
						}
					}
					// -요약- 기본배송비 배송타입 저장 추가
					if($ship_set['default_yn'] == 'Y' && ($set_type == 'std' || $set_type == 'add')){
						$grp_sum[$set_type.'_opt_type']	= $ship_opt['shipping_opt_type'];
					}

					// 배송금액 LOOP START
					$shipping_area_name_arr = $_POST['shipping_area_name'][$nation][$setKey][$set_type];
					foreach($shipping_area_name_arr as $costKey => $area_name){
						// -배송안내- 배송비
						$set_sum[$set_type]['cost'][] = $_POST['shipping_cost'][$nation][$setKey][$set_type][$otpKey][$costKey];
						// -배송안내- 당일배송비
						$set_sum[$set_type]['today_cost'][] = $_POST['shipping_cost_today'][$nation][$setKey][$set_type][$otpKey][$costKey];
						$set_sum[$set_type]['shipping_today_yn'][] = $_POST['shipping_today_yn'][$nation][$setKey][$set_type][$costKey];

						// ### 배송금액 저장 :: START
						$ship_cost['shipping_cost_seq'] = $_POST['shipping_cost_seq'][$nation][$setKey][$set_type][$otpKey][$costKey];
						// 배송방법 번호
						$ship_cost['shipping_opt_seq']			= $shipping_opt_seq;
						// 배송그룹 번호 삭제용
						$ship_cost['shipping_group_seq_tmp']	= $shipping_group_seq;

						// 지역명
						$ship_cost['shipping_area_name'] = $area_name;
						// 금액
						$ship_cost['shipping_cost']	= $_POST['shipping_cost'][$nation][$setKey][$set_type][$otpKey][$costKey];

						// 당일여부
						$ship_cost['shipping_today_yn']	= $_POST['shipping_today_yn'][$nation][$setKey][$set_type][$costKey];
						// 당일금액
						$ship_cost['shipping_cost_today']	= $_POST['shipping_cost_today'][$nation][$setKey][$set_type][$otpKey][$costKey];

						// 최대값/최소값 정의
						if(($set_type == 'std' || $set_type == 'add') && $ship_set['default_yn'] == 'Y'){
							// 네이버페이 수량(구간반복) 첫번째 배송설정은 무시되도록 개선 2018-06-07
							if ( !($grp_sum[$set_type.'_opt_type'] == 'cnt_rep' && $ship_opt['section_ed'] == 1  && $ship_cost['shipping_cost'] == 0 )) {
								$nowCost = ($ship_cost['shipping_cost'] > $ship_cost['shipping_cost_today']) ? $ship_cost['shipping_cost'] : $ship_cost['shipping_cost_today'];

								// 최대값 정의
								if(${$set_type.'_max_cost'} < $nowCost)
									${$set_type.'_max_cost'} = $nowCost;
								// 최소값 정의
								if(${$set_type.'_min_cost'} > $nowCost)
									${$set_type.'_min_cost'} = $nowCost;
							}
						}

						// ## fm_shipping_option Save
						$shipping_cost_seq = $this->shippingmodel->set_shipping_cost($ship_cost);
						if(!$shipping_cost_seq){
							// 배송그룹 롤백
							$this->shippingmodel->del_shipping_group($shipping_group_seq);
							// 배송설정 롤백
							$this->shippingmodel->del_shipping_set($shipping_set_seq);
							// 배송금액 롤백
							$this->shippingmodel->del_shipping_opt($shipping_opt_seq);
							openDialogAlert($fail_msg.'<br/>ErrCode: 05',400,140,'parent',$callback);
							exit;
						} else {
							$cost_seqs[] = $shipping_cost_seq;
						}

						// 배송지 지역제한이 없을 경우
						// 이미 등록되어 있는 배송지 지역 정보를 삭제한다.
						if($ship_opt['delivery_limit'] == 'unlimit' && $shipping_cost_seq){
							$del_area_detail_params = array();
							$del_area_detail_params['shipping_cost_seq'] =  $shipping_cost_seq;
							$del_area_detail_params['shipping_group_seq_tmp'] = $ship_opt['shipping_group_seq'];
							$this->shippingmodel->del_shipping_area_detail($del_area_detail_params);
						}

						if($nation != 'korea'){
							// 배송지역 상세 LOOP START
							$detail_adress_arr = $_POST['sel_address_join'][$nation][$setKey][$set_type][$costKey];
							foreach($detail_adress_arr as $zoneKey => $address){
								// ### 배송지역 상세 저장 :: START

								// 배송금액 번호
								$ship_zone['shipping_cost_seq'] = $shipping_cost_seq;
								// 배송그룹 번호 삭제용
								$ship_zone['shipping_group_seq_tmp'] = $shipping_group_seq;
								// 배송지역 국가타입
								$ship_zone['area_nation_type'] = $nation;
								// 배송지역 상세 주소 join
								$ship_zone['area_detail_address_join'] = $address;
								// 배송지역 상세 지번주소
								$ship_zone['area_detail_address_zibun'] = $_POST['sel_address_zibun'][$nation][$setKey][$set_type][$costKey][$zoneKey];
								// 배송지역 상세 도로명주소
								$ship_zone['area_detail_address_street'] = $_POST['sel_address_street'][$nation][$setKey][$set_type][$costKey][$zoneKey];
								// 배송지역 상세 주소 Full text
								$ship_zone['area_detail_address_txt'] = $_POST['sel_address_txt'][$nation][$setKey][$set_type][$costKey][$zoneKey];

								// ## fm_shipping_area_detail Save
								$this->shippingmodel->set_shipping_zone($ship_zone);

								if(!$shipping_opt_seq){
									// 배송그룹 롤백
									$this->shippingmodel->del_shipping_group($shipping_group_seq);
									// 배송설정 롤백
									$this->shippingmodel->del_shipping_set($shipping_set_seq);
									// 배송방법 롤백
									$this->shippingmodel->del_shipping_opt($shipping_opt_seq);
									// 배송금액 롤백
									$this->shippingmodel->del_shipping_cost($shipping_cost_seq);
									openDialogAlert($fail_msg.'<br/>ErrCode: 06',400,140,'parent',$callback);
									exit;
								}
								// ### 배송지역 상세 저장 :: END
							} // END 배송지역 상세 LOOP
						}
						// ### 배송금액 저장 :: END
					} // END 배송금액 LOOP
				} // END 구간설정 LOOP

				// -요약- 배송여부
				if($nation == 'korea')	$grp_sum['kr_'.$set_code.'_yn'] = 'Y';
				else					$grp_sum['gl_'.$set_code.'_yn'] = 'Y';

				// -요약- 기본 배송타입 정의 :: 2017-02-15 lwh
				if($ship_set['default_yn'] == 'Y'){
					if($set_type == 'std' && !$grp_sum['default_type']){
						$grp_sum['max_cost']		= ${$set_type.'_max_cost'};
						$grp_sum['min_cost']		= ${$set_type.'_min_cost'};

						if(${$set_type.'_min_cost'} > 0)
											$grp_sum['default_type']	= 'ifpay';
						else				$grp_sum['default_type']	= 'iffree';
					}else if($set_type == 'add'){
						$grp_sum['add_max_cost']	= ${$set_type.'_max_cost'};
						$grp_sum['add_min_cost']	= ${$set_type.'_min_cost'};
					}

					$grp_sum['default_nation']		= ($nation=='korea') ? 'kr' : 'gl';
					$grp_sum['default_set_code']	= $set_code;
				}

				// -배송안내- 자동안내 내용 요약
				if($ship_set['delivery_'.$set_type.'_type'] == 'Y' && is_numeric($set_sum[$set_type]['cost'][0]) && $set_sum[$set_type]['cost'][0] >= 0){
					unset($strParams);
					$strParams['nation']	= $nation;
					$strParams['kind']		= $set_type;
					$strParams['type']		= $ship_opt['shipping_opt_type'];
					$strParams['st']		= $section_st_arr;
					$strParams['ed']		= $section_ed_arr;
					$strParams['cost']		= $set_sum[$set_type]['cost'];
					$strParams['area']		= $_POST['shipping_area_name'][$nation][$setKey][$set_type];
					$strParams['tcost']		= $set_sum[$set_type]['today_cost'];
					$strParams['tcost_yn']	= $set_sum[$set_type]['shipping_today_yn'];
					$strParams['limitd']	= $_POST['delivery_limit'][$nation][$setKey];
					$strParams['limit']		= $_POST['hopeday_limit_set'][$nation][$setKey][$set_type];
					$strParams['times']		= $_POST['hopeday_limit_val'][$nation][$setKey][$set_type];
					$strParams['reserve']	= $_POST['reserve_sdate'][$nation][$setKey][$set_type];

					$auto_info_str	= $this->shippingmodel->shipping_info_str($strParams);
					$infostr['delivery_' . $set_type . '_input']	= $auto_info_str['kr'];
					if($set_type != 'hop'){
					$infostr['delivery_' . $set_type . '_input_us']	= $auto_info_str['us'];
					$infostr['delivery_' . $set_type . '_input_cn']	= $auto_info_str['cn'];
					$infostr['delivery_' . $set_type . '_input_jp']	= $auto_info_str['jp'];
					}
				}

				// 배송자동안내 UPDATE
				if($infostr){
					$infostr['shipping_set_seq']	= $shipping_set_seq;
					$this->shippingmodel->set_shipping_set($infostr);
				}
			} // END 배송설정 타입 LOOP

				// ### 매장수령 저장 :: START
			if($ship_set['store_use'] == 'Y'){
				$store_arr = $_POST['store_address_seq'][$nation][$setKey];
				foreach( $store_arr as $storeKey => $store_seq){
					// 배송설정 번호
					$ship_store['shipping_set_seq'] = $shipping_set_seq;
					// 배송지 고유번호
					$ship_store['shipping_address_seq'] = $store_seq;
					// 배송그룹 번호 삭제용
					$ship_store['shipping_group_seq_tmp'] = $shipping_group_seq;
					// 수령매장명
					$ship_store['shipping_store_name'] = $_POST['shipping_store_name'][$nation][$setKey][$storeKey];
					// 매장 전화번호
					$ship_store['store_phone'] = $_POST['store_phone'][$nation][$setKey][$storeKey];
					// 매장안내
					$ship_store['store_information'] = $_POST['store_information'][$nation][$setKey][$storeKey];
					// 창고연결여부
					$ship_store['store_scm_type'] = $_POST['store_scm_type'][$nation][$setKey][$storeKey];
					// 수령매장 재고설정
					$ship_store['store_supply_set'] = $_POST['store_supply_set'][$nation][$setKey][$storeKey];
					// 수령매장 재고보기설정
					$ship_store['store_supply_set_view'] = $_POST['store_supply_set_view'][$nation][$setKey][$storeKey];
					// 수령매장 재고수량설정
					$ship_store['store_supply_set_order'] = $_POST['store_supply_set_order'][$nation][$setKey][$storeKey];

					// 수령매장 타입
					$ship_store['store_type'] = $_POST['store_type'][$nation][$setKey][$storeKey];
					// 수령매장 창고 고유키
					$ship_store['store_scm_seq'] = $_POST['store_scm_seq'][$nation][$setKey][$storeKey];

					// ## fm_shipping_option Save
					$shipping_store_seq = $this->shippingmodel->set_shipping_store($ship_store);

					if(!$shipping_store_seq){
						// 배송그룹 롤백
						$this->shippingmodel->del_shipping_group($shipping_group_seq);
						// 배송설정 롤백
						$this->shippingmodel->del_shipping_set($shipping_set_seq);
						openDialogAlert($fail_msg.'<br/>ErrCode: 03',400,140,'parent',$callback);
						exit;
					}
				}

				// -요약- 매장수령여부
				if($nation == 'korea')	$grp_sum['kr_direct_store_yn'] = 'Y';
				else					$grp_sum['gl_direct_store_yn'] = 'Y';

				// -요약- 매장수령 무료배송여부
				if($ship_set['default_yn'] == 'Y'){
					$grp_sum['free_shipping_use']	= 'Y';
					$grp_sum['free_set_code']		= 'direct_store';
					$grp_sum['default_type']		= 'free';
					$grp_sum['min_cost']			= '0';
					$grp_sum['max_cost']			= '0';
				}
			}
			// ### 매장수령 저장 :: END

			// -요약- 배송여부
			if($nation == 'korea')	$grp_sum['kr_shipping_yn'] = 'Y';
			else					$grp_sum['gl_shipping_yn'] = 'Y';
			} // END 배송설정 LOOP
		} // END 국가별 LOOP

		// ## fm_shipping_group_summary Save
		$this->shippingmodel->set_shipping_group_summary($grp_sum);
		$this->shippingmodel->del_shipping_dummy($shipping_group_seq, $set_seqs, $cost_seqs);

		// dummy 데이터 최종 점검
		$inspect_data = [
			'shipping_group_seq' => $shipping_group_seq,
		];
		$this->shippingmodel->inspect_shipping_data($inspect_data);

		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			openDialogAlert('배송그룹 생성중 오류가 발생했습니다.',400,140,'parent',$callback);
			exit;
		} else {
			// 저장완료
			$this->db->trans_commit();
			if($reg_mode == 'save') {
				$suc_callback	= 'parent.location.href="./shipping_group_regist?shipping_group_seq='.$shipping_group_seq.'"';
			} else {
				$suc_callback	= 'parent.document.location.reload();';
			}

			openDialogAlert($mode_msg.'이 완료되었습니다.',400,140,'parent',$suc_callback);
		}
	}

	// 배송그룹 선택삭제 :: 2016-06-16 lwh
	public function rm_shipping_group(){
		$this->load->model('shippingmodel');

		$grp_seq = $_POST['grp_seq'];
		foreach($grp_seq as $k => $seq){
			$res = $this->shippingmodel->del_shipping_group($seq);
			if(!$res){
				$return['res'] = false;
				$return['msg'] = '배송그룹삭제에 실패했습니다.<br/>새로고침 후 다시 시도하여주세요.';
				echo json_encode($return);
				exit;
			}
		}

		$return['res'] = true;
		$return['msg'] = '배송그룹이 삭제되었습니다.';
		echo json_encode($return);
		exit;
	}

	// 택배사 설정 저장
	public function save_delivery_company(){

		$provider_seq			= $this->providerInfo['provider_seq'];

		if	(count($_POST['selected_delivery_company']) > 0){
			$deliveryCompanyCode	= serialize($_POST['selected_delivery_company']);
			config_save('providerDeliveryCompanyCode', array($provider_seq => $deliveryCompanyCode));
			$callback	= 'parent.location.reload();';
			openDialogAlert("저장되었습니다.", 400, 150, 'parent', $callback);
			exit;
		}else{
			$callback	= 'parent.location.reload();';
			openDialogAlert("선택된 택배사가 없습니다.", 400, 150, 'parent', $callback);
			exit;
		}
	}

	// 배송그룹 복사
	public function copyShippingGroup(){
		### SERVICE CHECK
		$auth = $this->authmodel->manager_limit_act('setting_shipping_act');
		if(!$auth){
			openDialogAlert($this->auth_msg,400,150,'parent',$callback);
			exit;
		}

		$this->load->model('shippingmodel');
		$oldGroupSeq = $this->input->post('group_seq');
		if(!$oldGroupSeq){
			$return['result'] = false;
			$return['msg'] = "배송그룹이 선택되지 않았습니다.";
			echo json_encode($return);
			exit;
		}

		$this->db->trans_begin();

		$fail_msg = '배송그룹 복사에 실패하였습니다.<br/>새로고침 후 다시 시도해주세요.';

		// 배송그룹 가져오기
		$ship_grp = array();
		$ship_grp = $this->shippingmodel->get_shipping_group($oldGroupSeq);
		// 배송그룹 고유번호 초기화
		unset($ship_grp['shipping_group_seq']);
		// 시스템 메모 초기화
		$mode_msg = $oldGroupSeq . "번 배송그룹 복사";
		$ship_grp['system_memo'] = $nowDate . ' ' . $this->managerInfo['mname'] . '(' . $this->managerInfo['manager_id'] . ') ' . $mode_msg . ' [' . $_SERVER['REMOTE_ADDR'] . ']';
		// 날짜 초기화
		$ship_grp['regist_date'] = $ship_grp['update_date'] = date('Y-m-d H:i:s');
		// 상품갯수 초기화
		$ship_grp['target_package_cnt']	= 0;
		$ship_grp['target_goods_cnt']	= 0;
		$ship_grp['trust_goods_cnt']	= 0;
		// 기본배송지 초기화
		$ship_grp['default_yn'] = 'N';

		// 배송그룹 저장
		$shipping_group_seq = $this->shippingmodel->set_shipping_group($ship_grp);
		if(!$shipping_group_seq){
			$return['result'] = false;
			$return['msg'] = $fail_msg.'<br/>copyErrCode: 01';
			echo json_encode($return);
			exit;
		}

		// 배송설정 가져오기
		$ship_set_arr = array();
		$ship_set_arr = $this->shippingmodel->get_shipping_set($oldGroupSeq);
		foreach($ship_set_arr as $ship_set){
			$oldShipSetSeq = $ship_set['shipping_set_seq'];
			unset($ship_set['shipping_set_seq']);
			$ship_set['shipping_group_seq'] = $shipping_group_seq;
			// 배송설정 저장
			$shipping_set_seq = $this->shippingmodel->set_shipping_set($ship_set);
			if(!$shipping_set_seq){
				$return['result'] = false;
				$return['msg'] = $fail_msg.'<br/>copyErrCode: 02';
				echo json_encode($return);
				exit;
			}

			// 배송방법 가져오기
			$ship_opt_arr = array();
			$ship_opt_arr = $this->shippingmodel->get_shipping_opt($oldShipSetSeq,"shipping_set_seq");
			foreach($ship_opt_arr as $ship_opt){
				$oldShipOptSeq = $ship_opt['shipping_opt_seq'];
				unset($ship_opt['shipping_opt_seq']);
				$ship_opt['shipping_group_seq'] = $shipping_group_seq;
				$ship_opt['shipping_set_seq'] = $shipping_set_seq;
				// 배송방법 저장
				$shipping_opt_seq = $this->shippingmodel->set_shipping_opt($ship_opt);
				if(!$shipping_opt_seq){
					$return['result'] = false;
					$return['msg'] = $fail_msg.'<br/>copyErrCode: 04';
					echo json_encode($return);
					exit;
				}

				// 배송금액 가져오기
				$ship_cost_arr = array();
				$ship_cost_arr = $this->shippingmodel->get_shipping_cost($oldShipOptSeq,"shipping_opt_seq");
				foreach($ship_cost_arr as $ship_cost){
					$oldShipCosSeq = $ship_cost['shipping_cost_seq'];
					unset($ship_cost['shipping_cost_seq']);
					$ship_cost['shipping_opt_seq'] = $shipping_opt_seq;
					$ship_cost['shipping_group_seq_tmp'] = $shipping_group_seq;
					// ## 배송금액 저장
					$shipping_cost_seq = $this->shippingmodel->set_shipping_cost($ship_cost);
					if(!$shipping_cost_seq){
						$return['result'] = false;
						$return['msg'] = $fail_msg.'<br/>copyErrCode: 05';
						echo json_encode($return);
						exit;
					}

					// 배송지역 가져오기
					$ship_zone_arr = array();
					$ship_zone_arr = $this->shippingmodel->get_shipping_zone($oldShipCosSeq,"shipping_cost_seq");
					foreach($ship_zone_arr as $ship_zone){
						$oldShipZonSeq = $ship_zone['area_detail_seq'];
						unset($ship_zone['area_detail_seq']);
						$ship_zone['shipping_cost_seq'] = $shipping_cost_seq;
						$ship_zone['shipping_group_seq_tmp'] = $shipping_group_seq;
						// 배송지역 저장
						$shipping_zone_seq = $this->shippingmodel->set_shipping_zone($ship_zone);
						if(!$shipping_zone_seq){
							$return['result'] = false;
							$return['msg'] = $fail_msg.'<br/>copyErrCode: 06';
							echo json_encode($return);
							exit;
						}
					}
				}
			}

			// 수령매장 가져오기
			$ship_store_arr = array();
			$ship_store_arr = $this->shippingmodel->get_shipping_store($oldShipSetSeq,'shipping_set_seq');
			foreach($ship_store_arr as $ship_store){
				$oldShipStoSeq = $ship_store['shipping_store_seq '];
				unset($ship_store['shipping_store_seq']);
				$ship_store['shipping_set_seq'] = $shipping_set_seq;
				$ship_store['shipping_group_seq_tmp'] = $shipping_group_seq;
				// 수령매장 저장
				$shipping_store_seq = $this->shippingmodel->set_shipping_store($ship_store);
				if(!$shipping_store_seq){
					$return['result'] = false;
					$return['msg'] = $fail_msg.'<br/>copyErrCode: 03';
					echo json_encode($return);
					exit;
				}
			}
		}

		// 배송요약 가져오기
		$grp_sum = "";
		$grp_sum = $this->shippingmodel->get_shipping_group_summary($oldGroupSeq);
		unset($grp_sum['shipping_summary_seq']);
		unset($grp_sum['default_type_txt']);
		$grp_sum['shipping_group_seq'] = $shipping_group_seq;
		// 배송요약 저장
		$group_summary_seq = $this->shippingmodel->set_shipping_group_summary($grp_sum);
		if(!$group_summary_seq){
			$return['result'] = false;
			$return['msg'] = $fail_msg.'<br/>copyErrCode: 07';
			echo json_encode($return);
			exit;
		}
		if($this->db->trans_status() === FALSE){
		    $this->db->trans_rollback();
			$return['res'] = false;
			$return['msg'] = '배송그룹 복사중 오류가 발생했습니다.';
			echo json_encode($return);
			exit;
		}else{
			// 저장완료
		    $this->db->trans_commit();
			$return['res'] = true;
			$return['msg'] = '등록되었습니다.';
			echo json_encode($return);
			exit;
		}
	}

	public function print_setting(){

		if	(!$_POST['orderPrintAddInfo']){
			$_POST['orderPrintPackage']			= '';
			$_POST['orderPrintSubRelation']		= '';
			$_POST['orderPrintWarehouse']		= '';
			$_POST['orderPrintGoodsCode']		= '';
		}
		if	(!$_POST['exportPrintAddInfo']){
			$_POST['exportPrintPackage']		= '';
			$_POST['exportPrintSubRelation']	= '';
			$_POST['exportPrintWarehouse']		= '';
			$_POST['exportPrintGoodsCode']		= '';
		}

		$provider_seq	= $this->providerInfo['provider_seq'];
		$query			= $this->db->query("select * from fm_setting_print where provider_seq=?",$provider_seq);
		$result			= $query->row_array();


		if($_POST['shopLogoType'] == "img" && $_FILES['shopLogoImg']['tmp_name']){
			$file_ext					= end(explode('.', $_FILES['shopLogoImg']['name']));
			$file_name					= 'shoplogo'.time().rand(10,99).'.'.$file_ext;
			$file_name					= str_replace("\'", "", $file_name); 	// ' " 제거
			$file_name					= str_replace("\"", "", $file_name); 	// ' " 제거
			$tmp						= getimagesize($_FILES['shopLogoImg']['tmp_name']);
			$_FILES['Filedata']['type'] = $tmp['mime'];
			$config['upload_path']		= './data/icon/favicon';
			$config['allowed_types']	= 'jpeg|jpg|png|gif|png';
			$config['max_size']			= $this->config_system['uploadLimit'];
			$config['file_name']		= $file_name;
			$this->load->library('Upload', $config);
			if (  $this->upload->do_upload('shopLogoImg'))
			{
				if( $result['shop_logo_img']) unlink($result['shop_logo_img']);
				$shopLogoImg = substr($config['upload_path']."/".$file_name,1);
			}
		}

		$data_params = array(
							"provider_seq"				=> $provider_seq,
							"order_barcode"				=> $_POST['orderPrintOrderBarcode'],
							"order_addinfo"				=> $_POST['orderPrintAddInfo'],
							"order_package"				=> $_POST['orderPrintPackage'],
							"order_sub_relation"		=> $_POST['orderPrintSubRelation'],
							"order_warehouse"			=> $_POST['orderPrintWarehouse'],
							"order_goods_code"			=> $_POST['orderPrintGoodsCode'],
							"order_goods_barcode"		=> $_POST['orderPrintGoodsBarcode'],
							"order_goods_image"			=> $_POST['orderPrintGoodsImage'],
							"order_centerinfo"			=> $_POST['orderPrintCenterInfo'],
							"order_centerinfo_message"	=> $_POST['orderPrintCenterInfoInput'],
							"export_code_barcode"		=> $_POST['exportPrintExportcodeBarcode'],
							"export_addinfo"			=> $_POST['exportPrintAddInfo'],
							"export_package"			=> $_POST['exportPrintPackage'],
							"export_sub_relation"		=> $_POST['exportPrintSubRelation'],
							"export_warehouse"			=> $_POST['exportPrintWarehouse'],
							"export_goods_code"			=> $_POST['exportPrintGoodsCode'],
							"export_goods_barcode"		=> $_POST['exportPrintGoodsBarcode'],
							"export_goods_image"		=> $_POST['exportPrintGoodsImage'],
							"export_centerinfo"			=> $_POST['exportPrintCenterInfo'],
							"export_centerinfo_message"	=> $_POST['exportPrintCenterInfoInput'],
							"shop_logo_type"			=> $_POST['shopLogoType'],
							"shop_logo_text"			=> $_POST['shopLogoText'],
							"shop_logo_img"				=> $shopLogoImg,
							"update_date"				=> date("Y-m-d H:i:s")
						);

		if($result){
			unset($data_params['provider_seq']);
			$this->db->where("provider_seq",$provider_seq);
			$this->db->update("fm_setting_print",$data_params);
		}else{
			$this->db->insert("fm_setting_print",$data_params);
		}

		$callback = "parent.location.reload();";
		openDialogAlert("저장 되었습니다.",400,140,'parent',$callback);
	}

}

/* End of file setting_process.php */
/* Location: ./app/controllers/selleradmin/setting_process.php */