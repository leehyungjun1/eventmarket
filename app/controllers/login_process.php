<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

class login_process extends front_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
	}

	/**
	*
	* @
	*/
	public function login(){


		$this->load->model('ssl');
		$this->ssl->decode();

		// return_url 에 http나 https가 있을 경우 외부 도메인으로 보낼 수 없도록 처리 by hed #24462
		block_out_link_return_url();
		
		// 로그인 제한.
		if($_COOKIE['wronglogin'] >= 5){
			openDialogAlert(getAlert('mb250'),400,140,'parent','parent.location.reload();');
			exit;
		}

		// 비회원 로그인 세션 만료 :: 2019-02-08 :: pjw
		$this->session->unset_userdata('sess_order');
		
		/*아이디자인 test아이디 로그인시 조건문 추가 if, else if*/
		
		if($_POST['log_code'] == 'eyedesign_t_id' && $this->session->userdata('manager')) {
			$key = get_shop_key();
			$query = "select A.*,B.business_seq,B.bname,C.group_name, AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone from fm_member A LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq left join fm_member_group C on C.group_seq=A.group_seq where A.member_seq=?";
			$query = $this->db->query($query, $_POST['t_mem_no']);
			$data = $query->result_array();
			$_POST['userid'] = $data['userid'];
			$this->validation->set_rules('userid', getAlert('mb201'),'trim|required|max_length[60]|xss_clean');

		} else if($_POST['log_code'] == 'eyedesign_t_id' && !$this->session->userdata('manager')) {
			
			openDialogAlert("관리자 로그인 확인해주세요.",400,140,'parent','parent.location.reload();');
				exit;
		} /*end*/
		else {
			
			### Validation
			//아이디
			$this->validation->set_rules('userid', getAlert('mb201'),'trim|required|max_length[60]|xss_clean');
			//비밀번호
			$this->validation->set_rules('password', getAlert('mb202'),'trim|required|max_length[32]|xss_clean');

			
			if($this->validation->exec()===false){
				$err = $this->validation->error_array;
				$callback = "parent.setDefaultText();if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'],400,140,'parent',$callback);
				exit;
			}

			### QUERY
			//$query = "select password(?) pass,old_password(?) old_pass";
			$query = "select password(?) pass";
			$query = $this->db->query($query,array($_POST['password']));
			$data = $query->row_array();

			$member_config = config_load('member');
			$passwordId = ($member_config['passwordid'])?$member_config['passwordid'] : "";

			$str_md5 = md5($_POST['password']);
			$str_sha	=	hash('sha256',$_POST['password']);
			$str_password = $data['pass'];
			$str_sha_md5 = hash('sha256',$str_md5);
			$str_sha_password = hash('sha256',$data['pass']);
			$str_sha_newpassword = hash('sha512', md5($_POST['password']).$passwordId.$_POST['userid']);

			$key = get_shop_key();
			$query = "select A.*,B.business_seq,B.bname,C.group_name, AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone, AES_DECRYPT(UNHEX(A.email), '{$key}') as email from fm_member A LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq left join fm_member_group C on C.group_seq=A.group_seq where A.userid=? and (A.password=? or A.password=? or A.password=? or A.password=? or A.password=? or A.password=?) and A.status !='withdrawal'";
			$query = $this->db->query($query,array($_POST['userid'],$str_md5,$str_sha,$str_password,$str_sha_md5,$str_sha_password, $str_sha_newpassword));

			$data = $query->result_array();
		}

		if(!$data[0]['member_seq']){
			$callback = "parent.setDefaultText();if(parent.document.getElementsByName('userid')[0]) parent.document.getElementsByName('userid')[0].focus();";
			//일치하는 회원정보가 없습니다.
			openDialogAlert(getAlert('mb203'),400,140,'parent',$callback);

			$wronglogin_cnt = ($_COOKIE['wronglogin']) ? $_COOKIE['wronglogin'] : 0;
			setcookie('wronglogin', $wronglogin_cnt+1, time()+(60*5));	//5분동안 저장
			exit;
		}else{
			setcookie('wronglogin', '', -1);		// 값을 비우고 휘발성으로 전환
		}

		if($data[0]['status']=='hold'){
			$callback = "parent.setDefaultText();if(parent.document.getElementsByName('userid')[0]) parent.document.getElementsByName('userid')[0].focus();";
			if($data[0]['mtype'] == 'business' ) {
				//<b>{$data[0]['bname']}</b>은(는) 아직 가입승인되지 않았습니다.
				openDialogAlert(getAlert('mb204','<b>'.$data[0]['bname'].'</b>'),400,140,'parent',$callback);
			} else {
				$msg = getAlert('mb205','<b>'.$data[0]['user_name'].'</b>');
				if($data[0]['kid_auth'] == 'N') $callback = "parent.location.href = '/member/kid_check'";
				
				//<b>{$data[0]['user_name']}</b>님은 아직 가입승인되지 않았습니다.
				openDialogAlert($msg,400,140,'parent',$callback);
			}
			exit;
		}

		$params = $data[0];
		### 휴면계정 체크
		if($params['dormancy_seq']){
			$member_config = config_load('member');
			if($member_config['dormancy']){
				$this->load->model('membermodel');
				switch($member_config['dormancy']){
					case 'auto':
						$this->membermodel->dormancy_off($params['member_seq']);
						//휴면처리가 성공적으로 해제되었습니다.<br> 재로그인후 정상적으로 쇼핑몰 이용이 가능합니다.
						$msg = getAlert('mb206');
						break;
					case 'email':
						$member_dr = $this->membermodel->get_dormancy($params['member_seq']);
						//아이디를 알아보지 못하게 변환한다
						$member_dr['dormancy_userid'] = implode(strForASCII($member_dr['userid'],'enc'),'l');
						if($member_dr['email_real']){
							sendMail($member_dr['email_real'], 'dormancy', $member_dr['userid'], $member_dr);
							//가입한 이메일<br> '.$member_dr['email_real'].' 으로<br> 인증 메일이 발송되었습니다<br> 인증하신 후에 정상적으로 쇼핑몰을 이용할 수 있습니다.
							$msg = getAlert('mb207',$member_dr['email_real']);
						}else{
							$this->membermodel->dormancy_off($params['member_seq']);
							//휴면처리가 성공적으로 해제되었습니다.<br>재로그인후 정상적으로 쇼핑몰 이용이 가능합니다.
							$msg = getAlert('mb026');
						}
						break;
					case 'namecheck':
						$realname = config_load('realname');
						$auth = $this->session->userdata('auth');

						if( $realname['useRealnamephone_dormancy']=='Y' || $realname['useIpin_dormancy']=='Y' ) {
							$url = '/member/login';
							if($auth['auth_yn']!='Y'){
								$url = '/member/dormancy_auth?dormancy_seq='.$params['member_seq'];
								$auth_dormancy_data = array('auth_dormancy_type'=>'auth', 'auth_dormancy_yn'=>'N');
								$this->session->sess_expiration = (60 * 5);
								$this->session->set_userdata(array('auth_dormancy'=>$auth_dormancy_data));
							}
							pageLocation($url,'','parent');
							exit;
						}else{
							$this->membermodel->dormancy_off($params['member_seq']);
							//휴면처리가 성공적으로 해제되었습니다.<br> 재로그인후 정상적으로 쇼핑몰 이용이 가능합니다.
							$msg = getAlert('mb206');
						}
						break;
				}
				$callback = "";
				openDialogAlert($msg,400,170,'parent',$callback);
				exit;
			}
		}

		### SESSION
		$this->create_member_session($params);

		### LOG
		$this->load->library('memberlibrary');
		$this->memberlibrary->make_login_history($params['member_seq']);

		### 성인인증세션 처리
		if($params['adult_auth'] == 'Y'){
			$auth_intro_data = array('auth_intro_type'=>'auth', 'auth_intro_yn'=>'Y');
			$this->session->sess_expiration = (60 * 5);
			$this->session->set_userdata(array('auth_intro'=>$auth_intro_data));
		}else{
			$this->session->unset_userdata('auth_intro');
			$_SESSION['auth_intro']		= '';
		}

		### 장바구니 MERGE
		$this->load->model('cartmodel');
		$this->cartmodel->merge_for_member($params['member_seq']);

		//fblike 할인 MERGE
		$this->db->where('session_id',session_id());
		$this->db->update('fm_goods_fblike', array('member_seq' => $params['member_seq']));

		### 로그인 이벤트
		$this->load->model('joincheckmodel');
		$jcresult = $this->joincheckmodel->login_joincheck($params['member_seq']);

		// 사용자앱 설치 쿠폰 발행
		if(checkUserApp(getallheaders())){
			$this->load->model('couponmodel');
			$sc['whereis'] = ' and (type="app_install")  and issue_stop != 1 ';//발급중지가 아닌경우
			$coupon_multi_list = $this->couponmodel->get_coupon_multi_list($sc);
			$coupon_multicnt = 0;
			foreach($coupon_multi_list as $coupon_multi){  $coupon_multicnt++;
				$this->couponmodel->_members_downlod( $coupon_multi['coupon_seq'], $params['member_seq']);
			}
		}

		/* 고객리마인드서비스 상세유입로그 */
		$this->load->helper('reservation');
		$curation = array("action_kind"=>"login");
		curation_log($curation);

		switch($jcresult['code']){
			case 'success' :
				alert($jcresult['msg']);
			break;
			case 'emoney_pay' :
				alert($jcresult['msg']);
			break;
		}

		$this->load->helper('cookie');

		if($_POST['idsave'] == 'checked' ){
			setcookie('userlogin',$_POST['userid'],time()+(86400*365),'/');	//1년간 저장
		}else{
			setcookie('userlogin', '', 0, '/');		// 값을 비우고 휘발성으로 전환
		}
        
        /*######################## 17.12.18 gcs yjy : 앱 처리 s */		
		
		//자동로그인
		if($_POST['app_auto_login'] == 'checked' ){
			setcookie('auto_login','y',time()+(86400*365),'/',".".$this->config_basic['domain']);	//1년간 저장
			$auto_login = 'y';
		}else{
			setcookie('auto_login','n',time()+(86400*365),'/',".".$this->config_basic['domain']);
			$auto_login = 'n';
		}
		$this->session->set_userdata('auto_login',$auto_login);
		

		//쿠폰보유건
		/*$this->load->model('couponmodel');
		$sc['today']			= date('Y-m-d',time());
		$dsc['whereis'] = " and member_seq=".$params['member_seq']." and use_status='unused' AND ( (issue_startdate is null  AND issue_enddate is null ) OR (issue_startdate <='".$sc['today']."' AND issue_enddate >='".$sc['today']."') )";//사용가능한
		$coupondownloadtotal = $this->couponmodel->get_download_total_count($dsc);*/

		$api_key = $params['api_key'];                
        
		if($this->mobileapp=='Y'){
        
			### PAGE MOVE
			$script = array();
			if ($this->m_device=='iphone') {            
                $script[] = "var param = {member_seq : ".$params['member_seq'].", user_id : '".$params['userid']."', user_name : '".$params['user_name']."', session_id : '".session_id()."', channel : 'none', reserve : '".$params['emoney']."', balance : '".$params['cash']."', coupon : '".$coupondownloadtotal."', auto_login : '".$auto_login."', key : '".$api_key."' }; var strParam = JSON.stringify(param); 
				var dataStr = 'MemberInfo' + '?' + strParam; window.webkit.messageHandlers.CSharp.postMessage(dataStr);
				";				
								
			}else{
				$script[] = "var param = {member_seq : ".$params['member_seq'].", user_id : '".$params['userid']."', user_name : '".$params['user_name']."', session_id : '".session_id()."', channel : 'none', reserve : '".$params['emoney']."', balance : '".$params['cash']."', coupon : '".$coupondownloadtotal."', auto_login : '".$auto_login."', key : '".$api_key."' }; var strParam = JSON.stringify(param);
				var dataStr = 'MemberInfo' + '?' + strParam; CSharp.postMessage(dataStr);
				";	
            }
            

			if(!empty($_POST['order_auth'])){
				if(strstr(urldecode($_POST['return_url']),"/board/write") || strstr(urldecode($_POST['return_url']),"goods/review_write") || strstr(urldecode($_POST['return_url']),"/mypage/mygdreview_write") ) {
					//echo js("parent.opener.location.reload();parent.self.close();");//상품후기 >> 비회원 주문검색시 새창처리
					if ( $this->mobileMode  || $this->storemobileMode || $this->_is_mobile_agent) {
						$script[] = "parent.opener.document.writeform.action='';parent.opener.document.writeform.target='';parent.opener.document.writeform.submit();parent.self.close();"; //상품후기 >> 비회원 주문검색시 새창처리
					}
				}else{
					//######################## 17.12.18 gcs yjy : 회원 통합 (return_url 변경)
				
					$script[] = "parent.document.location.replace('/mypage/order_catalog');";
					
					
				}
			
			}elseif(!empty($_POST['return_url'])){
				$script[] = "top.document.location.replace('".urldecode($_POST['return_url'])."');"; //######################## 17.12.18 gcs yjy : 앱 처리
				

			}else{
				$script[] = "parent.document.location='/main/index';";
				$_POST['return_url'] = '/main/index';

			}
			
			echo "<script type='text/javascript' charset='utf-8'>";
			foreach($script as $s => $sc){
				echo $sc;
			}
			echo "
			
			</script>";
		
		}else{
		
        /*######################## 17.12.18 gcs yjy : 앱 처리 e */
            ### PAGE MOVE
            if(!empty($_POST['order_auth'])){
                if(strstr(urldecode($_POST['return_url']),"/board/write") || strstr(urldecode($_POST['return_url']),"goods/review_write") || strstr(urldecode($_POST['return_url']),"/mypage/mygdreview_write") ) {
                    //echo js("parent.opener.location.reload();parent.self.close();");//상품후기 >> 비회원 주문검색시 새창처리
                    if ( $this->mobileMode  || $this->storemobileMode || $this->_is_mobile_agent) {
                        echo js("parent.opener.document.writeform.action='';parent.opener.document.writeform.target='';parent.opener.document.writeform.submit();parent.self.close();");//상품후기 >> 비회원 주문검색시 새창처리
                    }else{
                    echo js("parent.opener.document.writeform.action='';parent.opener.document.writeform.target='';if(parent.opener.readyEditorForm(parent.opener.document.writeform)){parent.opener.document.writeform.submit();}parent.self.close();");//상품후기 >> 비회원 주문검색시 새창처리
                    }
                }else{
                    pageRedirect('/mypage/order_catalog','','parent');
                }
            }elseif($_POST['adult_auth']){
                if(!empty($_POST['return_url']))
                    $return_url = '?return_url='.urldecode($_POST['return_url']);
                pageRedirect('/member/adult_auth'.$return_url,'','parent');
            }elseif(!empty($_POST['return_url'])){
                pageRedirect(urldecode($_POST['return_url']),'','parent');
            }else{
                /*아이디자인 테스트 로그인 시 경고창 추가 start*/
                if($_POST['log_code'] == 'eyedesign_t_id' && $this->session->userdata('manager')) {
					echo 'success';
					exit;
				}
                /*end*/
                pageRedirect('/main/index','','parent');
            }
        }

	}

###
	public function loginid_chk() {

		
		$conf = config_load('joinform');
		if($_POST['userid'] == '@' ) $_POST['userid'] = '';

		$userid = $_POST['userid'];
		if(!$userid) die();
		//$userid = strtolower($userid);

		//이메일
		$this->validation->set_rules('userid', getAlert('mb208'),'trim|required|valid_email|xss_clean');
		if($this->validation->exec()===false){
			//유효하지 않는 이메일 형식입니다.
			$text = getAlert('mb209');
			$result = array("return_result" => $text, "userid" => $_POST['userid'], "return" => false, "returns" => false);
			echo json_encode($result);
			exit;
		}

		###
		$count = get_rows('fm_member',array('userid'=>$userid));

		$return = true;
		$text = "OK";
		if($count > 0) {
			$return = 'duplicate';
		}else{
			//등록된 이메일이 아닙니다.
			$text = getAlert('mb210');
			$return = 'no_duplicate';
		}
		$result = array("return_result" => $text, "userid" => $userid, "return" => $return, "returns" => $return);
		echo json_encode($result);
	}


	public function loginpw_chk() {
		$conf = config_load('joinform');
		if($_POST['userid'] == '@' ) $_POST['userid'] = '';

		$userid = $_POST['userid'];
		$password = $_POST['password'];
		if(!$userid) die();
		if(!$password) die();

		//이메일
		$this->validation->set_rules('userid', getAlert('208'),'trim|required|valid_email|xss_clean');
		if($this->validation->exec()===false){
			//유효하지 않는 이메일 형식입니다.
			$text = getAlert('mb209');
			$result = array("return_result" => $text, "userid" => $_POST['userid'], "return" => false, "returns" => false);
			echo json_encode($result);
			exit;
		}
		//비밀번호
		$this->validation->set_rules('password', getAlert('mb211'),'trim|required|max_length[32]|xss_clean');

		if($this->validation->exec()===false){
			//유효하지 않는 비밀번호 형식입니다.
			$text = getAlert('mb212');
			$result = array("return_result" => $text, "userid" => $_POST['userid'], "return" => false, "returns" => false);
			echo json_encode($result);
			exit;
		}

		### QUERY
		//$query = "select password(?) pass,old_password(?) old_pass";
		$query = "select password(?) pass";
		$query = $this->db->query($query,array($_POST['password']));
		$data = $query->row_array();

		$str_md5 = md5($_POST['password']);
		$str_password = $data['pass'];
		$str_sha_md5 = hash('sha256',$str_md5);
		$str_sha_password = hash('sha256',$data['pass']);

		$query = "select * from fm_member where userid=? and (`password`=? or `password`=? or `password`=? or `password`=? )";
		$query = $this->db->query($query,array($_POST['userid'],$str_md5,$str_password,$str_sha_md5,$str_sha_password));
		$data = $query->result_array();

		if(!$data){
			//비밀번호가 일치하지 않습니다.
			$text = getAlert('mb213');
			$result = array("return_result" => $text, "userid" => $_POST['userid'], "return" => false, "returns" => false);
			echo json_encode($result);
			exit;
		}

		if($data[0]['status']=='hold'){
			//아직 가입승인되지 않았습니다.
			$text = getAlert('mb214');
			$result = array("return_result" => $text, "userid" => $_POST['userid'], "return" => false, "returns" => false);
			echo json_encode($result);
			exit;
		}

		$return = true;
		$text = "OK";
		$result = array("return_result" => $text, "userid" => $userid, "return" => $return, "returns" => $return);
		echo json_encode($result);
		exit;
	}

	/**
	*
	* @
	*/
	public function logout(){		
		$unsetuserdata = array('user'=>'','fbuser'=>'','accesstoken'=>'','signedrequest'=>'','nvuser'=>'','mtype'=>'','naver_state'=>'','naver_access_token'=>'','kkouser'=>'','dmuser'=>'','daum_access_token'=>'','http_host'=>'','snslogn'=>'','auth_intro'=>'','auth'=>'','cart_promotioncode_'.session_id()=>'');
		$this->session->unset_userdata($unsetuserdata);

		// 비회원 세션도 같이 만료되게 처리 :: 2019-02-08 pjw
		$this->session->unset_userdata('sess_order');

		$_SESSION['user']			= ''; $_SESSION['fbuser']				= '';
		$_SESSION['accesstoken']	= ''; $_SESSION['signedrequest']		= '';
		$_SESSION['nvuser']			= ''; $_SESSION['naver_access_token']	= '';
		$_SESSION['naver_state']	= ''; $_SESSION['mtype']				= '';
		$_SESSION['kkouser']		= '';
		$_SESSION['dmuser']			= ''; $_SESSION['daum_access_token']	= '';
		$_SESSION['http_host']		= ''; $_SESSION['snslogn']				= '';
		$_SESSION['auth_intro']		= ''; $_SESSION['auth']			= '';
		$_SESSION['cart_promotioncode_'.session_id()]	= '';

		unset($this->userInfo, $_SESSION['user'], $_SESSION['fbuser'], $_SESSION['naver_state'], $_SESSION['naver_access_token'], $_SESSION['nvuser'], $_SESSION['accesstoken'], $_SESSION['signedrequest'],$_SESSION['kkouser'],$_SESSION['dmuser'],$_SESSION['daum_access_token'],$_SESSION['auth'],$_SESSION['cart_promotioncode_'.session_id()]);

		// 로그아웃 오류로 인해 프로세스 변경 :: 2020-04-21 pjw
		// 로그아웃 처리 변수
		$msg				= '로그아웃 되었습니다.';
		$referer			= $this->input->server('HTTP_REFERER');
		$channel			= $this->input->get('channel');
		$direct				= $this->input->get('direct');
		$location_type		= '';
		$location_link		= '';		
		$location_target	= '';		

		// 조건식에 따라 분기 처리
		if(strstr($referer, '/member_process/withdrawal') !== false || $direct){				// 앱뷰 영역에서 호출, 회원탈퇴 페이지
			$location_type		= 'redirect';
			$location_link		= '/main/index';
			$location_target	= 'self';
		}else if(strstr($referer, '/member_process/withdrawal') !== false){						// 로그아웃 페이지에서 온 경우 뒤로가기
			$location_type		= 'back';
			$location_target	= 'parent';
		}else if($this->mobileapp != 'Y' && !empty($referer) && strstr($referer, '/order') !== false && strstr($referer, '/mypage') !== false){	// 로그아웃 페이지에서 온 경우 뒤로가기
			$location_type		= 'reload';
			$location_target	= 'parent';
		}else{
			$location_type		= 'redirect';
			$location_link		= '/main/index';
			$location_target	= 'parent';
		}
		
		// 리다이렉트 처리 전 앱 리턴 함수를 먼저 실행해야함
		if($this->mobileapp == 'Y') {

			// 네이버 로그아웃인 경우 
			if($channel == 'nv') {
				echo "<a src='http://nid.naver.com/nidlogin.logout' class='nvLogout'>naver logout</a>";
				echo "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
				echo "<script>$('.nvLogout').click();</script>";
			}
			
			// 기기 별 앱 통신 함수 분기
			if ($this->m_device=='iphone') 		echo "<script>window.webkit.messageHandlers.CSharp.postMessage('Logout?'); </script>";
			else								echo "<script>CSharp.postMessage('Logout?'); </script>";
		}

		// location 처리	
		switch($location_type){
			case 'back':
				pageBack($msg, $location_target);
				break;
			case 'reload':
				pageReload($msg, $location_target);
				break;
			case 'redirect':
			default:
				pageRedirect($location_link, $msg, $location_target);
				break;
		}
	}

	/**
	*
	* @
	*/
	public function create_member_session($data=array()){
		$this->load->helper('member');
		create_member_session($data);
	}


	public function findid(){
		$this->load->model('ssl');
		$this->ssl->decode();
		$aParamsPost = $this->input->post();

		### Validation
		//이름
		$this->validation->set_rules('user_name', getAlert('mb185'),'trim|required|max_length[20]|xss_clean');
		if($_POST['find_gb']=='email'){
			//이메일
			$this->validation->set_rules('email', getAlert('mb186'),'trim|required|max_length[64]|valid_email|xss_clean');
		}else{
			//휴대폰번호
			$this->validation->set_rules('cellphone[]', getAlert('mb187'),'trim|required|max_length[4]|numeric|xss_clean');
			$cellphone = implode("-",$_POST['cellphone']);
		}

		# id찾기 captcha 입력 확인 @2016-09-08 pjm
		$find_idpass	= config_load('find_idpass');

		# 캡챠변수가 존재하면 체크(스킨 패치 안한 업체 오류남)
		if($find_idpass['find_id_use_captcha'] == "y"){
			$this->validation->set_rules('captcha_id_txt', getAlert('et400'),'trim|required|max_length[20]|xss_clean');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]); parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		# id찾기 captcha 입력 확인 @2016-09-08 pjm
		if($find_idpass['find_id_use_captcha'] == "y"){
			include_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/Securimage.php";
			$Securimage = new Securimage();
			$Securimage->setNamespace("id_search");
			if(strtolower($Securimage->getCode()) != strtolower($_POST['captcha_id_txt'])){
				$callback = "if(parent.document.getElementsByName('captcha_id_txt')) parent.document.getElementsByName('captcha_id_txt').focus();";
				//보안 문자 입력 정보를 확인해 주세요
				openDialogAlert(getAlert('mb239'),400,140,'parent',$callback);
				exit;
			}
		}

		### QUERY
		if ($aParamsPost['find_gb'] == 'email') {
			$enc_qry = get_encrypt_where('email', $this->db->escape_str($aParamsPost['email']));
		} else {
			$enc_qry = '(' . get_encrypt_where('cellphone', $cellphone)
						. " or B.bcellphone = '" . $cellphone . "')";
		}

		//실명인증사용시 기본가입회원만가능@2013-08-08
		$realname = config_load('realname');
		if( $realname['useRealname']=='Y' || $realname['useRealnamephone']=='Y' || $realname['useIpin']=='Y' ){
			//$enc_qry .= ' AND A.auth_type = "none" ';
		}

		$key = get_shop_key();

		$queryBinds = [
			$aParamsPost['user_name'],
			$aParamsPost['user_name'],
		];

		$sql = "
			SELECT 
				A.*, 
				AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
				AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone,
				B.business_seq,
				B.bcellphone 
			FROM 
				fm_member A
			LEFT JOIN 
				fm_member_business B ON A.member_seq = B.member_seq
			WHERE 
				(A.rute = 'none' OR (A.rute !='none' and A.sns_change=1)) 
			AND 
				(A.user_name = ? OR B.bname = ?) 
			AND 
				{$enc_qry} 
		";

		$query	= $this->db->query($sql, $queryBinds);
		$data	= $query->result_array();

		//휴면회원일 경우
		if(!$data){
			$sql = "
				SELECT 
					A.*, 
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone,
					B.business_seq, 
					B.bcellphone 
				FROM 
					fm_member_dr A
				LEFT JOIN 
					fm_member_business_dr B ON A.member_seq = B.member_seq
				WHERE 
					(A.rute = 'none' OR (A.rute !='none' and A.sns_change=1)) 
				AND 
					(A.user_name = ? OR B.bname = ?) 
				AND 
					{$enc_qry} 
			";
			$query	= $this->db->query($sql, $queryBinds);
			$data	= $query->result_array();
		}

		$success=0;

		foreach($data as $mbrow) {
			if($aParamsPost['find_gb']=='email'){
				$result = sendMail($mbrow['email'], 'findid', $mbrow['userid'], $mbrow);
				if($result) $success++;
			}else{
				$cellphone	= $mbrow['cellphone'];
				if	($mbrow['business_seq'])	$cellphone	= $mbrow['bcellphone'];
				
				// 필요한 params만 넘김
				unset($member_params);
				$member_params['userid'] = $mbrow['userid'];
				$member_params['user_name'] = $mbrow['user_name'];

				$commonSmsData = array();
				$commonSmsData['findid']['phone'][] = $cellphone;
				$commonSmsData['findid']['params'][] = $member_params;
				$commonSmsData['findid']['mid'][] = $mbrow['userid'];
				$result = commonSendSMS($commonSmsData);

				if($result) $success++;
			}
		}
		$scdocument = "parent.document";
		$scripts[] = "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
		$scripts[] = "<script type='text/javascript'>";
		$scripts[] = "$(function() {";

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
			if($aParamsPost['find_gb']=='email'){
				$scripts[] = '$(".findidresultok2",'.$scdocument.').show();';
				$scripts[] = '$("#findidlay2",'.$scdocument.').text("'.get_return_data($data[0]['email'],5,"*").'");';
			}else{
				$scripts[] = '$(".findidresultok3",'.$scdocument.').show();';
				$scripts[] = '$("#findidlay3",'.$scdocument.').text("'.get_return_data($cellphone,5,"*").'");';
			}
		}else{
			$scripts[] = '$(".findidresultfalse",'.$scdocument.').show();';
		}

		$scripts[] = "});";
		$scripts[] = "</script>";
		foreach($scripts as $script){
			echo $script."\n";
		}
		exit;
	}

	public function findpwd(){
		$this->load->model('ssl');
		$this->ssl->decode();
		$aParamsPost = $this->input->post();

		### Validation
		//이름
		$this->validation->set_rules('user_names', getAlert('mb215'), 'trim|required|max_length[20]|xss_clean');
		//아이디
		$this->validation->set_rules('userids', getAlert('mb216'), 'trim|required|xss_clean');
		if($aParamsPost['finds_gb']=='emails'){
			//이메일
			$this->validation->set_rules('emails', getAlert('mb217'),'trim|required|max_length[64]|valid_email|xss_clean');
		}else{
			//휴대폰번호
			$this->validation->set_rules('cellphones[]', getAlert('mb218'),'trim|required|max_length[4]|numeric|xss_clean');
			$cellphone = implode("-",$aParamsPost['cellphones']);
		}

		# pass찾기 captcha 입력 확인 @2016-09-08 pjm
		$find_idpass	= config_load('find_idpass');

		# 캡챠변수가 존재하면 체크(스킨 패치 안한 업체 오류남)
		if($find_idpass['find_pass_use_captcha'] == "y"){
			$this->validation->set_rules('captcha_pass_txt', getAlert('et400'),'trim|required|max_length[20]|xss_clean');
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		# pass찾기 captcha 입력 확인 @2016-09-08 pjm
		if($find_idpass['find_pass_use_captcha'] == "y"){
			include_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/Securimage.php";
			$Securimage = new Securimage();
			$Securimage->setNamespace("pass_search");
			if(strtolower($Securimage->getCode('',true)) != strtolower($aParamsPost['captcha_pass_txt'])){
				$callback = "if(parent.document.getElementsByName('captcha_pass_txt')) parent.document.getElementsByName('captcha_pass_txt').focus();";
				//보안 문자 입력 정보를 확인해 주세요
				openDialogAlert(getAlert('mb239'),400,140,'parent',$callback);
				exit;
			}
		}

		### QUERY
		if($aParamsPost['finds_gb']=='emails'){
			$enc_qry	= get_encrypt_where('email', $aParamsPost['emails']);
		}else{
			$enc_qry	= "(" . get_encrypt_where('cellphone', $cellphone)
						. " or B.bcellphone = '".$cellphone."')";
		}

		//실명인증사용시 기본가입회원만가능 @2013-08-08
		$realname = config_load('realname');
		if( $realname['useRealname']=='Y' || $realname['useRealnamephone']=='Y' || $realname['useIpin']=='Y' ){
			//$enc_qry .= ' AND A.auth_type = "none" ';
		}
		$queryBinds = [
			$aParamsPost['user_names'],
			$aParamsPost['user_names'],
			$aParamsPost['userids'],
		];

		$key = get_shop_key();
		$sql = "
			SELECT 
				A.*,
				AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
				AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone  
			FROM 
				fm_member A
			LEFT JOIN 
				fm_member_business B ON A.member_seq = B.member_seq
			WHERE 
				(A.rute = 'none' OR (A.rute !='none' and A.sns_change=1)) 
			and  
				(A.user_name = ? OR B.bname = ?) 
			AND 
				A.userid = ? 
			AND  
				{$enc_qry} 
			limit 1
		";
		$query	= $this->db->query($sql, $queryBinds);
		$data	= $query->result_array();

		//휴면회원일 경우
		if (!$data) {
			$sql = "
				SELECT 
					A.*, 
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone  
				FROM 
					fm_member_dr A
				LEFT 
					JOIN fm_member_business_dr B ON A.member_seq = B.member_seq
				WHERE 
					(A.rute = 'none' OR (A.rute !='none' and A.sns_change=1)) 
				AND
					(A.user_name = ? OR B.bname = ?) 
				AND 
					A.userid = ?
				AND  
					{$enc_qry} 
				limit 1
			";
			$query = $this->db->query($sql, $queryBinds);
			$data = $query->result_array();
		}

		###
		$success=0;
		foreach($data as $mbrow) {
			$params = $data[0];
			unset($mbrow['password']);
			/* GABIA 보안점검으로 인한 기존 임시 패스워드 sha1 암호화 후 13자리 적용 */
			$mbrow['password'] = sha1(chr(rand(97,122)).chr(rand(97,122)).chr(rand(97,122)).substr(mktime()*2,1,4));
			$mbrow['password'] = substr($mbrow['password'], 0, 13);
			$mbrow['passwd'] = $mbrow['password'];
			if($aParamsPost['finds_gb']=='emails'){
				$mbrow['regist_date'] = date('Y-m-d H:i:s');
				$email = $mbrow['email'];
				$result = sendMail($mbrow['email'], 'findpwd', $mbrow['userid'], $mbrow);
				if($result) $success++;
			}else{

				// 필요한 params만 넘김
				unset($member_params);
				$member_params['password'] = $mbrow['passwd'];

				$commonSmsData = array();
				$commonSmsData['findpwd']['phone'][] = $cellphone;
				$commonSmsData['findpwd']['params'][] = $member_params;
				$commonSmsData['findpwd']['mid'][] = $mbrow['userid'];
				$result = commonSendSMS($commonSmsData);

				if($result) $success++;
			}
			$mbrow['passwd'] = hash('sha256',md5($mbrow['passwd']));
			$sql = "update fm_member set password = ?, update_date = now() where member_seq = ?";
			$this->db->query($sql,array($mbrow['passwd'],$mbrow['member_seq']));
		}

		$scdocument = "parent.document";
		$scripts[] = "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
		$scripts[] = "<script type='text/javascript'>";
		$scripts[] = "$(function() {";

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
			if($aParamsPost['finds_gb']=='emails'){
				$scripts[] = '$(".findpwresultok2",'.$scdocument.').show();';
				$scripts[] = '$("#findpwlay2",'.$scdocument.').text("'.get_return_data($email,5,"*").'");';
			}else{
				$scripts[] = '$(".findpwresultok3",'.$scdocument.').show();';
				$scripts[] = '$("#findpwlay3",'.$scdocument.').text("'.get_return_data($cellphone,5,"*").'");';
			}
		}else{
			$scripts[] = '$(".findpwresultfalse1",'.$scdocument.').show();';
		}
		$scripts[] = "});";
		$scripts[] = "</script>";
		foreach($scripts as $script){
			echo $script."\n";
		}
		exit;

	}

	public function popup_change_pass(){
		$this->load->model('membermodel');
		
		// 로그인체크
		login_check();

		if($_POST['password_mode'] == "update"){
			if($_POST['update_rate'] != "Y"){
				if($_POST['re_new_password'] != $_POST['new_password']){
					//신규 비밀번호와 신규 비밀번호 확인 값이 다릅니다..
					$text = getAlert('mb192');
					$callback = "if(parent.document.getElementsByName('new_password')[0]) parent.document.getElementsByName('new_password')[0].focus();";
					openDialogAlert($text,400,140,'parent',$callback);
					exit;

				}

				$user_data = $this->membermodel->get_member_data($this->userInfo['member_seq']);//회원정보

				$check_password = $this->input->post('new_password');

				$pre_enc_password = $user_data['password'];
				$enc_password = hash('sha256',md5($check_password));
				
				$password_params = array(
					'birthday'                => $user_data['birthday'],
					'phone'                   => $user_data['phone'],
					'cellphone'               => $user_data['cellphone'],
					'pre_enc_password'        => $pre_enc_password,
					'enc_password'            => $enc_password,
				);

				$this->load->library('memberlibrary');
				$result = $this->memberlibrary->check_password_validation($check_password, $password_params);

				if($result['code'] != '00' && $result['alert_code']){
					openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback);
					exit;
				}

				### QUERY
				$query = "select password(?) pass";
				$query = $this->db->query($query,array($_POST['old_password']));
				$data = $query->row_array();

				$str_md5 = md5($_POST['old_password']);
				$str_password = $data['pass'];
				$str_sha_md5 = hash('sha256',$str_md5);
				$str_sha_password = hash('sha256',$data['pass']);

				$query = "select count(*) as cnt from fm_member A LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq left join fm_member_group C on C.group_seq=A.group_seq where A.member_seq=? and (A.password=? or A.password=? or A.password=? or A.password=?)";
				$query = $this->db->query($query,array($this->userInfo['member_seq'],$str_md5,$str_password,$str_sha_md5,$str_sha_password));
				$data = $query->row_array();

				if($data['cnt'] < 1){
					//기존 비밀번호가 맞지 않습니다.
					$text = getAlert('mb193');
					$callback = "if(parent.document.getElementsByName('new_password')[0]) parent.document.getElementsByName('new_password')[0].focus();";
					openDialogAlert($text,400,140,'parent',$callback);
					exit;
				}

				if($_POST['old_password'] == $_POST['new_password']){
					//기존 비밀번호와 동일한 비밀번호로 변경할 수 없습니다.
					$text = getAlert('mb194');
					$callback = "if(parent.document.getElementsByName('new_password')[0]) parent.document.getElementsByName('new_password')[0].focus();";
					openDialogAlert($text,400,140,'parent',$callback);
					exit;

				}

				$params['password_update_date'] = date("Y-m-d");
				$params['password']	= hash('sha256',md5($_POST['new_password']));

				$this->db->where('member_seq',$this->userInfo['member_seq']);
				$this->db->update('fm_member', $params);

			}else{

				$params['password_update_date'] = date("Y-m-d");

				$this->db->where('member_seq',$this->userInfo['member_seq']);
				$this->db->update('fm_member', $params);
			}
		}

		$usnet_item = array('user' => array('password_update_date' => ''));
		$this->session->unset_userdata($usnet_item);
		unset($_SESSION['user']['password_update_date']);
		if($this->mobileMode || $this->storemobileMode){
			echo "<script>parent.location.replace('/main/index');</script>";
		}else{
			echo "<script>parent.close_popup_change(); parent.location.reload();</script>";
		}
	}
}
/* End of file login_process.php */
/* Location: ./app/controllers/login_process.php */
