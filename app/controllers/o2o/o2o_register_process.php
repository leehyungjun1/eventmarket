<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class o2o_register_process extends front_base {

	function __construct() {
		parent::__construct();
		
		// 암호화 데이터 디코딩
		$this->load->model('ssl');
		$this->ssl->decode();
		
		$this->load->library('o2o/o2oservicelibrary');
		$this->load->library('validation');
		
		// 인증키 체크
		$this->o2o_auth_info = array(
			'pos_key'	=> $this->input->post('pos_key'),
			'store_seq' => $this->input->post('store_seq'),
			'pos_seq'	=> $this->input->post('pos_seq'),
		);
		if(empty($this->input->post('pos_key'))){
			$this->o2o_auth_info = array(
				'pos_key'	=> $this->input->get('pos_key'),
				'store_seq' => $this->input->get('store_seq'),
				'pos_seq'	=> $this->input->get('pos_seq'),
			);
		}
		$this->o2oConfig = $this->o2oservicelibrary->check_o2o_service($this->o2o_auth_info);
		if(empty($this->o2oConfig)){
			// 에러 페이지로 이동
			redirect('/errdoc/error_404');
		}else{
			// O2O 환경 체크 false : 일반 접속, true : 오프라인 요청
			// 해당 변수를 o2o 서비스가 시작되면 갱신함
			// common_base에서 선언함
			$this->o2o_pos_env = true;
		}
	}
	
	public function register_ok(){
				
		//=================================================
		// parameter init
		//=================================================
		$arr_param_list = array(
			'user_name'			=> null,
			'cellphone'			=> null,
			'authnum'			=> null,
			'sms'				=> 'n',
		);
		foreach($arr_param_list as $reciveParamName => $value){
			// 가변 변수에 데이터 할당
			${$reciveParamName} = $this->input->post($reciveParamName);
			// 데이터 없을 시 기본 값 설정
			if(empty(${$reciveParamName}) && !empty($value)) ${$reciveParamName} = $value;
		}
		if(!empty($cellphone))  $cellphone = implode("-",$cellphone);
		
		// 설정 정보 추가
		$joinform				= config_load('joinform');
		
		//=================================================
		// Form validation start
		//=================================================
		// form 체크 조건 추가
		$this->validation->set_rules('user_name',			'이름',					'required|trim|xss_clean|max_length[20]');
		$this->validation->set_rules('cellphone[]',			'휴대폰번호',			'trim|required|max_length[4]|numeric|xss_clean');
		
		// 인증번호 체크
		$this->validation->set_rules('authnum',				'인증번호',				(($joinform['o2oauthnum_required']=="Y")?'required|':'').'trim|xss_clean|max_length[5]');
		
		$this->validation->set_rules('argee_agreement',		'이용약관 동의',			'required|trim|xss_clean');
		$this->validation->set_rules('argee_privacy',		'개인정보처리방침 동의 ',	'required|trim|xss_clean');
		
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			$callback .= "parent.document.getElementById('btn_register').style.display='block';";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}
		if($joinform['o2oauthnum_required']=="Y"){
			unset($params_validate_auth_cellphone);
			$params_validate_auth_cellphone['cellphone']	= $cellphone;
			$params_validate_auth_cellphone['authnum']		= $authnum;
			$this->load->library('o2o/o2orequiredlibrary');
			$this->o2orequiredlibrary->required_validate_auth_cellphone($params_validate_auth_cellphone);
		}
		//=================================================
		// Form validation end
		//=================================================
		
		//=================================================
		// data save start
		//=================================================
		unset($sqlData);
		$sqlData = array(
			'user_name'				=> $user_name,
			'cellphone'				=> $cellphone,
			'sms'					=> $sms,
			'o2oauthnum_required'	=> $joinform['o2oauthnum_required'],
			'o2oauthnum'			=> $authnum,
			'o2o_auth_info'			=> $this->o2o_auth_info,
		);
		$o2oMemberSeq = $this->o2oservicelibrary->join_o2o_member($sqlData);
		//=================================================
		// data save end
		//=================================================
		
		$result = $o2oMemberSeq;
		if(empty($result['error_msg'])){
			$callback = "parent.location.reload();";
			openDialogAlert($result['msg'],400,190,'parent',$callback);
		}else{
			$callback = "parent.location.reload();";
			openDialogAlert("처리 중 문제가 발생했습니다.<br/>[".$result['error_msg']."]",400,160,'parent',$callback);
		}
	}

	public function create_member_session($data=array()){
		$this->load->helper('member');
		create_member_session($data);
	}
}