<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * 기존 sns_process 함수 레거시 트레이트
 * sns_process에 있던 레거시는 이 트레이트에서 관리한다
 * 레거시 동작을 보장하기 위해 sns_process에서 이 트레이트를 사용
 * 기존 SNS 연동 후 콜백 함수명은 _legacy 로 변경하고 sns_process에서 스킨 최신 여부에 따라 연동 처리를 분기한다
 */
trait sns_process_legacy {

	//social login url
	public function sociallogin() {
		$this->load->helper('cookiesecure');
		switch($_GET['sns']){
			case 'facebook':
				$this->facebookaccountck();
			break;
			case 'twitter':
				$this->twitterloginck();
			break;
			case 'naver':
				$this->naverloginck();
			break;
			case 'apple':
				$this->appleloginck();
			break;
		}
	}

	/**
	@ facebook api start
	------------------------------------------------------------
	**/
		public function facebookaccountck() {
			if($this->arrSns['use_f']) {//사용여부

				$mtype = ($_POST['mtype'])?$_POST['mtype']:$_GET['mtype'];//member, business
				$mform = ($_POST['mform'])?$_POST['mform']:$_GET['mform'];//join, login
				$scope = ($_POST['scope'])?$_POST['scope']:$_GET['scope'];
				$facebooktype = ($_POST['facebooktype'])?$_POST['facebooktype']:$_GET['facebooktype'];

				$callbackurl = urlencode(get_connet_protocol().$_SERVER['HTTP_HOST'].'/sns_process/facebookloginck?display=popup&mtype='.$mtype.'&mform='.$mform.'&facebooktype='.$facebooktype);
				$login_info = array(
				'scope'			=> $scope,
				'display'		=> ($this->_is_mobile_agent && $this->mobileMode?'touch':'page'),
				'redirect_uri'	=> $callbackurl);
				$loginUrl = $this->snssocial->facebook->getLoginUrl($login_info);
				//$loginurl = 'http://www.facebook.com/dialog/oauth?client_id='.$this->__APP_ID__.'&redirect_uri='.$callbackurl.'&scope=publish_stream,offline_access,user_about_me,email,photo_upload&display='.($this->_is_mobile_agent && $this->mobileMode?'touch':'page').'&state='.$f_start
				if( $loginurl ) {
					$return = array('result'=>true, 'loginurl'=>$loginurl);
				}else{
					//페이스북에서 회원정보를 가져오지 못하였습니다.<br/>관리자에게 문의해 주세요.
					$return = array('result'=>false, 'msg'=>getAlert('mb090'));
				}
				if($_GET['jsoncallback']) {
					echo $_GET["jsoncallback"] ."(".json_encode($return).");";
				}else{
					echo json_encode($return);
				}
				exit;
			}else{
				//잘못된 접근입니다.
				$return = array('result'=>false, 'msg'=>getAlert('mb091'));
				if($_GET['jsoncallback']) {
					echo $_GET["jsoncallback"] ."(".json_encode($return).");";
				}else{
					echo json_encode($return);
				}
				exit;
			}
		}

		public function facebookloginck() {

			$aPostParams = $this->input->post();
			$joinform	= config_load('joinform');
			//18.02.27 kmj 기본앱 일 경우 공지
			if($this->arrSns['key_f'] == "455616624457601"){
				$return = array('result'=>false, 'msg'=>"페이스북의 앱 정책 변경으로 3월 중으로 페이스북을 통한 회원 가입 및 로그인 서비스를 제공하지 못하게 되었습니다.<br />페이스북으로 가입하신 회원은 다른 SNS 또는 신규 ID로 재가입해 주시기 바랍니다.<br />");
				echo json_encode($return);
				exit;
			}

			if($this->arrSns['use_f']){
				$fbuserprofile = $this->snssocial->facebooklogin();
				if($fbuserprofile){
					$params['rute']				= 'facebook';
					$params['sns_type']			= 'sns_f';
					$params['userid']			= ($fbuserprofile['email'])?$fbuserprofile['email']:$fbuserprofile['id'];

					if($fbuserprofile['email']) $params['authemail'] = true;
					$params['sns_f']			= $fbuserprofile['id'];
					$params['password']			= '';
					$params['user_name']		= $fbuserprofile['name'];
					if( $fbuserprofile['email'] ){
						$params['email']		= $fbuserprofile['email'];
					}else{
						$params['email']		= ( strstr($fbuserprofile['id'],"@") )?$fbuserprofile['id']:'';
						//($fbuserprofile['email'])?$fbuserprofile['email']:$fbuserprofile['id'];
					}
					$params['recommend']	= ($this->session->userdata('recommend') && !$params['recommend'] )?$this->session->userdata('recommend'):$_POST['recommend'];
					$params['sms']				= 'n';
					$params['sex']				= ($fbuserprofile['gender'])?$fbuserprofile['gender']:'none';//enum('male', 'female', 'none')
					if($fbuserprofile['birthday']){
						$birthday				= @explode("/",$fbuserprofile['birthday']);
						$params['birthday']		= $birthday[2].'-'.$birthday[0].'-'.$birthday[1];
					}else{
						 $params['birthday']	= '';
					}
					$params['birth_type']		= 'none';
					$params['status']				= $this->sns_mtype($_POST['mtype']);
					$params['emoney']			= 0;
					$params['login_cnt']		= 0;
					$params['order_cn']	 		= 0;
					$params['order_sum']		= 0;
					$params['mtype']			= ($_POST['mtype'] == 'biz')?true:false;
					$params['regist_date']		= date('Y-m-d H:i:s');

					// 만 14세 동의 체크 :: 2020.06.16 sms
					$aPostParams['mtype'] = ($aPostParams['mtype'] == 'biz') ? 'business' : 'member';
					$this->memberlibrary->kidAgreeCheck($aPostParams);

					$kid_agree_check = $this->session->userdata('kid_agree_check');
					if(isset($kid_agree_check)){
						$params['kid_agree']	= $kid_agree_check;
						$params['kid_auth']		= $kid_agree_check;

						// 만 14세 미만 미인증시 가입 상태 '미승인'
						if($params['kid_auth'] == 'N'){
							$params['status'] = 'hold';
						}
					}

					if($_POST['facebooktype'] == 'mbconnect_direct') {//로그인된 상태에서 페북통합하기
						$where_arr = array('sns_f'=>$params['sns_f']);
						$mbdata = get_data('fm_member', $where_arr);
						if(!$mbdata){//회원찾기
							$snsintergration = $this->sns_Integration_direct_ok($params);
							if($snsintergration) {
								$this->sns_login_auth('sns_f');	//로그인세션추가
								$return = array('result'=>true,'retururl'=>'mypage/myinfo','msg'=>"");
								echo json_encode($return);
								exit;
							}else{//통합실패
								//잘못된 접근입니다.
								$return = array('result'=>false, 'msg'=>getAlert('mb091'));
								echo json_encode($return);
								exit;
							}
						}else{//중복체크
								$unsetuserdata = array('fbuser'=>'','accesstoken'=>'','signedrequest'=>'');
								$this->session->unset_userdata($unsetuserdata);
								//이미 가입된 페이스북계정 입니다.<br>다른 페이스북 계정으로 가입해 주세요.
								$return = array('result'=>false, 'msg'=>getAlert('mb092'));
								echo json_encode($return);
								exit;
						}
					}else{
						### QUERY
						$where_arr	= array('sns_f'=>$params['sns_f']);//
						$data		= get_data('fm_member', $where_arr);

						if(!$data) {//정보가 없을 경우 가입후 로그인하기
							if( strstr($_SERVER['HTTP_REFERER'],'/member/login')  || strstr($_SERVER['HTTP_REFERER'],'member/register_sns_form?popup=1&formtype=login') || $_POST['facebooktype'] == 'login') {
								//로그인이면 회원가입페이지로 안내하기
								//#20067 2019-02-07 ycg 페이스북 리다이렉트 경로 지정
								//일치하는 회원정보가 없습니다.<br>회원가입 후 이용해 주세요.
								$return = array('result'=>false, 'msg'=>getAlert('mb093'),'return_url'=>'/member/agreement?join_type=fbmember');
								echo json_encode($return);
								exit;
							}else{
								$snsregister = $this->sns_register_ok($params);
								if($snsregister['result'] === "auth_false" ) {//실명인증 중복 가입 체크 추가 @2016-09-12 ysm
										$unsetuserdata = array('fbuser'=>'','accesstoken'=>'','signedrequest'=>'');
										$this->session->unset_userdata($unsetuserdata);
										$msg	= "이미 가입된 정보입니다.<br>로그인해 주세요.";

										$return		= array('result'=>false,'msg'=>getAlert('mb092'));
										echo json_encode($return);
										exit;
								}elseif($snsregister['result']) {
									// 수동 승인이거나 만 14세 미만 가입시
									if($this->app_status == "hold" || $params['kid_agree'] == "N"){
										$msg		= $params['user_name'].getAlert('mb104'); //님은 아직 가입승인되지 않았습니다.
										$return		= array('result'=>false,'msg'=>$msg, 'retururl'=> '/main/index');
										if($params['kid_agree'] == "N"){
											$return['retururl'] = '/member/register_ok?kid_auth=N';
										}
										echo json_encode($return);
										exit;
									}else{
										$snslogin	= $this->sns_login($params,'sns_f');
										if($snslogin){
											$common_msg = $snsregister['common_msg'];
											//가입 되었습니다.
											$msg = getAlert('mb221');

											$msg .= '<br/>'.$common_msg['coupon_msg'];

											//<br/>가입 '.$common_msg['emoneyJoin'].' '.$common_msg['pointJoin'].' 지급되었습니다
											if	($common_msg['emoneyJoin'])
												$msg .= getAlert('mb222',array($common_msg['emoneyJoin'],$common_msg['pointJoin']));

											//<br/>추천 '.$common_msg['emoneyJoiner'].' '.$common_msg['pointJoiner'].' 지급되었습니다
											if	($common_msg['emoneyJoiner'])
												$msg .= getAlert('mb223');

											//<br/>초대 '.$common_msg['emoneyInvitees'].' '.$common_msg['pointInvitees'].' 지급되었습니다
											if	($common_msg['emoneyInvitees'])
												$msg .= getAlert('mb224',array($common_msg['emoneyInvitees'],$common_msg['pointInvitees']));

											if	($snslogin['jcresult_msg'])	$msg .= '<br/>'.$snslogin['jcresult_msg'];

											/*######################## 17.12.21 gcs userapp : 앱 처리 s */
											$send_params = $this->appmembermodel->config_send_params($snslogin);

											if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
												$auto_login = 'y';
											}else{
												$auto_login = 'n';
											}
											$send_params['auto_login'] = $auto_login;
											/*######################## 17.12.21 gcs userapp : 앱 처리 e */

											$return		= array('result'=>true,'retururl'=>'/member/register_ok','msg'=>$msg);
											//$return = array('result'=>true,'retururl'=>'/mypage/');
											echo json_encode($return);
											exit;
										}
									}
								}else{//가입실패시
									$unsetuserdata = array('fbuser'=>'','accesstoken'=>'','signedrequest'=>'');
									$this->session->unset_userdata($unsetuserdata);
									//이미 가입된 페이스북계정 입니다.<br>다른 페이스북 계정으로 가입해 주세요.
									$return = array('result'=>false, 'msg'=>getAlert('mb092'));
									echo json_encode($return);
									exit;
								}
							}
						}else{//가입된경우 로그인하기
							if($data[0]['status'] == "hold"){
								//님은 아직 가입승인되지 않았습니다.
								$msg	= $params['user_name'].getAlert('mb104');
								if($data[0]['kid_auth'] == 'N'){
									$return = array('result'=>false,'retururl'=>'/member/kid_check','msg'=>$msg);
									echo json_encode($return);
									exit;
								}else{
									$return = array('result'=>false, 'msg'=>$msg);
									echo json_encode($return);
									exit;
								}
							}else{
								$snslogin = $this->sns_login($params,'sns_f');
								if($snslogin) {
									if($snslogin['jcresult_msg'])$msg .= '<br />'.$snslogin['jcresult_msg'];

									/*######################## 17.12.21 gcs userapp : 앱 처리 s */
									$send_params = $this->appmembermodel->config_send_params($snslogin);

									if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
									    $auto_login = 'y';
									}else{
									    $auto_login = 'n';
									}
									$send_params['auto_login'] = $auto_login;
									/*######################## 17.12.21 gcs userapp : 앱 처리 e */

									$return = array('result'=>true,'retururl'=>'../','msg'=>$msg,'send_params'=> $send_params);
									echo json_encode($return);
									exit;
								}else{//로그인실패시
									//탈퇴회원입니다.<br />관리자에게 문의해 주세요.
									$return = array('result'=>false, 'msg'=>getAlert('mb094'));
									echo json_encode($return);
									exit;
								}
							}
						}
					}
				}else{
					$unsetuserdata = array('fbuser'=>'','accesstoken'=>'','signedrequest'=>'');
					$this->session->unset_userdata($unsetuserdata);
					//페이스북에서 회원정보를 가져오지 못하였습니다.<br/>관리자에게 문의해 주세요.
					$return = array('result'=>false,'type'=>5, 'msg'=>getAlert('mb090'));
					echo json_encode($return);
					exit;
				}
			}else{
					//잘못된 접근입니다.
					$return = array('result'=>false, 'msg'=>getAlert('mb091'));
					echo json_encode($return);//
					exit;
			}
		}

		public function facebooklogincknone() {//비회원접근시 체크
			if($this->arrSns['use_f']){
				//$this->snssocial->facebooklogin();
				$fbuserprofile = $this->snssocial->facebookuserid();
				if ( !$fbuserprofile ) {
					$this->facebook = new Facebook(array(
					  'appId'  => $this->__APP_ID__,
					  'secret' => $this->__APP_SECRET__,
					  "cookie" => true
					));
					// Get User ID
					$fbuserprofile = $this->facebook->getUser();
					if($fbuserprofile && !$this->session->userdata('fbuser')){
						$this->session->set_userdata('fbuser', $fbuserprofile);
					}else{
						$fbuserprofile = $this->snssocial->facebooklogin();
						if( $fbuserprofile && !$this->session->userdata('fbuser') ) {
							$this->session->set_userdata('fbuser', $fbuserprofile);
						}
					}
				}else{
					if( !$this->session->userdata('fbuser') ) {
						$this->session->set_userdata('fbuser', $fbuserprofile);
					}
				}
			}
		}

		//페이스북로그아웃처리
		public function facebooklogout(){
			$unsetuserdata = array('user'=>'','fbuser'=>'','accesstoken'=>'','signedrequest'=>'','nvuser'=>'','mtype'=>'','naver_state'=>'','naver_access_token'=>'','kkouser'=>'','dmuser'=>'','daum_access_token'=>'','http_host'=>'','snslogn'=>'');
			$this->session->unset_userdata($unsetuserdata);
			$_SESSION['user']			= ''; $_SESSION['fbuser']				= '';
			$_SESSION['accesstoken']	= ''; $_SESSION['signedrequest']		= '';
			$_SESSION['nvuser']			= ''; $_SESSION['naver_access_token']	= '';
			$_SESSION['naver_state']	= ''; $_SESSION['mtype']				= '';
			$_SESSION['kkouser']		= '';
			$_SESSION['dmuser']			= ''; $_SESSION['daum_access_token']	= '';
			$_SESSION['http_host']		= ''; $_SESSION['snslogn']				= '';
			unset($this->userInfo, $_SESSION['user'], $_SESSION['fbuser'], $_SESSION['naver_state'], $_SESSION['naver_access_token'], $_SESSION['nvuser'], $_SESSION['accesstoken'], $_SESSION['signedrequest'],$_SESSION['kkouser'],$_SESSION['dmuser'],$_SESSION['daum_access_token']);

			$return = array('result'=>true);
			echo json_encode($return);
			exit;
		}

		/**
		* goods view open graph
		**/
		function goodsview_opengraph($product_url, $type){
			$objectid = $this->snssocial->publishCustomAction($product_url, $type);
			return $objectid;
		}

		/**
		* goods like 모든정보체크하기
		**/
		function facebook_goodsLike(){
			$returnid = $this->snssocial->facebook_goodsLike($_POST['product_url']);
			return $returnid;
		}

		/**
		* goods like session 구하기
		**/
		function facebooklikeck() {
			$this->load->helper('cookie');
			$this->load->model('goodsfblike');
			$referer = parse_url($_SERVER['HTTP_REFERER']);

			$fbuserprofile = $this->snssocial->facebookuserid();
			if ( !$fbuserprofile ) {
				$this->facebook = new Facebook(array(
				  'appId'  => $this->__APP_ID__,
				  'secret' => $this->__APP_SECRET__,
				  "cookie" => true
				));
				// Get User ID
				$fbuserprofile = $this->facebook->getUser();
				if($fbuserprofile && !$this->session->userdata('fbuser')){
					$this->session->set_userdata('fbuser', $fbuserprofile);
				}else{
					$fbuserprofile = $this->snssocial->facebooklogin();
					if( $fbuserprofile && !$this->session->userdata('fbuser') ) {
						$this->session->set_userdata('fbuser', $fbuserprofile);
					}
				}
			}else{
				if( !$this->session->userdata('fbuser') ) {
					$this->session->set_userdata('fbuser', $fbuserprofile);
				}
			}

			$mode = ($_POST['mode'])?$_POST['mode']:$_GET['mode'];
			$product_url = ($_POST['product_url'])?$_POST['product_url']:$_GET['product_url'];

			$no = ($_POST['no'])?$_POST['no']:$_GET['no'];
			if( $no ) {
				$goodseq = $no;
				if(!strstr($product_url,"&no=")) $product_url = $product_url."&no=".$goodseq;
			}else{
				$goodseq = @end(explode("=",$product_url));
			}

			$this->goodsfblike->set_fblike_goods($mode,$product_url);

			$this->load->model('goodsmodel');
			$product_url = $this->likeurl.'&no='.$goodseq;
			$countreal = $this->snssocial->facebooklikestat($product_url,' like_count, share_count ');
			$this->goodsmodel->goods_like_count($goodseq,$countreal);//like/share count save
			$count = $this->goodsmodel->goods_like_viewer($goods_seq);//상품의 좋아요정보가져오기
			if( strstr($referer['path'], 'order/settle') ) {
				if($count){
					$return = array('result'=>true, 'ftype'=>"settle",'likecount'=>$count['like_count']);
				}else{
					$return = array('result'=>true, 'ftype'=>"settle",'likecount'=>0);
				}
				if( $_GET["jsoncallback"] ) {
					echo $_GET["jsoncallback"] ."(".json_encode($return).");";
				}else{
					echo json_encode($return);
				}
				exit;
			}else{
				if($count){
					$return = array('result'=>true, 'ftype'=>"",'likecount'=>$count['like_count']);
				}else{
					$return = array('result'=>true, 'ftype'=>"",'likecount'=>0);
				}

				if( $_GET["jsoncallback"] ) {
					echo $_GET["jsoncallback"] ."(".json_encode($return).");";
				}else{
					echo json_encode($return);
				}
				exit;
			}
		}

		/* @ facebook 초대하기 후 창닫기 개별시
		* @ recommendconnect
		**/
		function recommendconnect(){
			$this->load->model('snsfbinvite');
			login_check();
			$params = $this->input->get();
			if($params['post_id']) {

				$memberapproval = config_load('member');

				$sc['sns_f'] = $params['friendid'];
				$sc['whereis']	= ' and member_seq = \''.$this->userInfo['member_seq'].'\' ';
				$sc['select'] = ' seq ';
				$inviteck = $this->snsfbinvite->get_data_numrow($sc);
				if(!$inviteck){//초대여부 -> 마일리지 지급
					$totalinvitesc['whereis']	= ' and member_seq = \''.$this->userInfo['member_seq'].'\' ';
					$totalinvitesc['select']		= ' seq ';
					$totalinviteck = $this->snsfbinvite->get_data_numrow($totalinvitesc);
					if(($totalinviteck+1) <= $memberapproval['invitemaxcount']) {//최대 초대건수
						if( (($totalinviteck+1)%$memberapproval['invitecount']) == 0 ){

							if($memberapproval['emoneyInvitedCnt'] > 0 ) {
								$emoney['type']			= 'invite_whenever';//초대할때마다
								$emoney['emoney']		= $memberapproval['emoneyInvitedCnt'];//초대하는사람에게
								$emoney['gb']			= 'plus';
								$emoney['memo']			= $memberapproval['invitecount'].'명 초대시 마일리지';
								$emoney['memo_lang']	= $this->membermodel->make_json_for_getAlert("mp234",$memberapproval['invitecount']);    // %s명 초대시 마일리지
								$emoney['limit_date']	= get_emoney_limitdate('invite');
								$this->membermodel->emoney_insert($emoney, $this->userInfo['member_seq']);
							}

							if($memberapproval['pointInvitedCnt'] > 0 ) {
								$point['type']			= 'invite_whenever';//초대할때마다
								$point['point']			= $memberapproval['pointInvitedCnt'];//초대하는사람에게
								$point['gb']			= 'plus';
								$point['memo']			= $memberapproval['invitecount'].'명 초대시 포인트';
								$point['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp235",$memberapproval['invitecount']);    // %s명 초대시 포인트
								$point['limit_date']	= get_point_limitdate('invite');
								$this->membermodel->point_insert($point, $this->userInfo['member_seq']);
							}

						}
					}
					$insparams['member_seq'] = $this->userInfo['member_seq'];
					$insparams['sns_f'] = $params['friendid'];
					$insparams['post_id'] = $params['post_id'];
					$insparams['emoney'] = $memberapproval['emoneyInvitedCnt'];
					$insparams['r_date'] = date('Y-m-d H:i:s');
					$this->snsfbinvite->snsinvite_write($insparams);

					//초대한자의 초대건수 증가 @2013-06-19
					$this->membermodel->member_invite_cnt($this->userInfo['member_seq']);

				}//endif
				if( $this->__APP_DOMAIN__ == $_SERVER['HTTP_HOST'] ) {
					//echo js("opener.location.reload()");//'&refererdomain='.$refererdomain.
					//초대하기에 성공하였습니다!
					pageReload(getAlert('mb097'),'opener');
					pageClose();
				}else{
					pageClose(getAlert('mb097'));
				}
			}else{
				if( $this->__APP_DOMAIN__ == $_SERVER['HTTP_HOST'] ) {
					//echo js("opener.location.reload()");
					//초대하기에 실패하였습니다!
					pageReload(getAlert('mb098'),'opener');
					pageClose();
				}else{
					pageClose();
				}
			}
		}
	/**
	@ facebook api end
	------------------------------------------------------------
	**/


	/**
	@ twitter api start
	------------------------------------------------------------
	**/
		//twitter의 로그인체크 (본래창)
		public function twitterloginck() {
			$param = $this->input->post();
			$this->memberlibrary->kidAgreeCheck($param);
			// 트위터 기본앱일 경우 공지 #19795 2018-06-27 hed
			if($this->arrSns['key_t'] == "ifHWJYpPA2ZGYDrdc5wQ" && $this->arrSns['use_t'] == "1"){
				$return = array('result'=>false, 'msg'=>"트위터의 앱 정책 변경으로 트위터를 통한 회원 가입 및 로그인 서비스를 제공하지 못하게 되었습니다.<br />트위터로 가입하신 회원은 다른 SNS 또는 신규 ID로 재가입해 주시기 바랍니다.<br />");
				echo json_encode($return);
				exit;
			}

			if($this->arrSns['use_t']) {//twitter 사용여부
				$mtype = ($param['mtype'])?$param['mtype']:$this->input->get('mtype');//member, business
				$mform = ($param['mform'])?$param['mform']:$this->input->get('mform');//join, login
				$facebooktype = ($param['facebooktype'])?$param['facebooktype']:$this->input->get('facebooktype');
				$loginurl = $this->snssocial->twitterloginurl($mtype,$mform, $facebooktype);
				if($loginurl) {
					$return = array('result'=>true, 'loginurl'=>$loginurl);
					echo json_encode($return);
					exit;
				}else{
					//'트위터에서 회원정보를 가져오지 못하였습니다.<br/>관리자에게 문의해 주세요.'
					$return = array('result'=>false, 'msg'=>getAlert('mb099'));
					echo json_encode($return);
					exit;
				}
			}else{
				//잘못된 접근입니다.
				$return = array('result'=>false, 'msg'=>getAlert('mb091'));
				echo json_encode($return);
				exit;
			}
		}


		//twitter 쇼핑몰회원가입 (새창)
		public function twitterjoin_legacy() {
			// 트위터 기본앱일 경우 공지 #19795 2018-06-27 hed
			if($this->arrSns['key_t'] == "ifHWJYpPA2ZGYDrdc5wQ" && $this->arrSns['use_t'] == "1"){
				pageClose("트위터의 앱 정책 변경으로 트위터를 통한 회원 가입 및 로그인 서비스를 제공하지 못하게 되었습니다.<br />트위터로 가입하신 회원은 다른 SNS 또는 신규 ID로 재가입해 주시기 바랍니다.<br />");
				exit;
			}

			$aParams = $this->input->post();

			if($this->arrSns['use_t']) {//twitter 사용여부
				$mtype = ($aParams['mtype'])?$aParams['mtype']:$this->input->get('mtype');
				$facebooktype = ($aParams['facebooktype'])?$aParams['facebooktype']:$this->input->get('facebooktype');
				$twuserprofile = $this->snssocial->twitteraccount($this->input->get('oauth_verifier'), $mtype, 'join', $facebooktype);

				if($this->input->get('denied')){
					//"트위터에서 회원정보를 가져오지 못하였습니다.\\n관리자에게 문의해 주세요."
					pageClose(getAlert('mb100'));
					exit;
				}elseif( !$_SESSION['oauth_token'] && !$_SESSION['oauth_token_secret'] ) {
					//잘못된 접근입니다.
					pageClose(getAlert('mb091'));
					exit;
				}else{
					if( $twuserprofile['id'] ) {

						$params['rute']				= 'twitter';
						$params['sns_type']				= 'sns_t';
						$params['userid']				= $twuserprofile['screen_name'];//사용자아이디

						$this->db->where('userid', $params['userid']);
						$query = $this->db->get("fm_member");
						$mem_chk = $query->result_array();
						if($mem_chk) $params['userid'] = 'tw_'.$params['userid'];

						if( strstr($twuserprofile['id'],"@") ) $params['authemail'] = true;
						$params['sns_t']				= $twuserprofile['id'];
						$params['password']		= '';
						$params['user_name']		= $twuserprofile['screen_name'];
						$params['email']				= ( strstr($twuserprofile['id'],"@") )?$twuserprofile['id']:'';
						$params['sms']				= 'n';
						$params['sex']					= 'none';
						$params['birthday']			= '';
						$params['birth_type']		= 'none';
						$params['status']				= $this->sns_mtype($mtype);
						$params['emoney']			= 0;
						$params['login_cnt']		= 0;
						$params['order_cn']	 		= 0;
						$params['order_sum']		= 0;
						$params['mtype']				= ($mtype == 'biz')?true:false;
						$params['regist_date']	= date('Y-m-d H:i:s');

						// twitterloginck() 에서 session 생성
						$kid_agree_check = $this->session->userdata('kid_agree_check');
						if(isset($kid_agree_check)){
							$params['kid_agree']	= $kid_agree_check;
							$params['kid_auth']		= $kid_agree_check;

							// 만 14세 미만 미인증시 가입 상태 '미승인'
							if($params['kid_auth'] == 'N'){
								$params['status'] = 'hold';
							}
						}

						if($facebooktype == 'mbconnect_direct') {//로그인된 상태에서 twitter통합하기
							$where_arr	= array('sns_t'=>$params['sns_t'], 'status'=>'done');
							$mbdata		= get_data('fm_member', $where_arr);
							if(!$mbdata){//회원찾기
								$snsintergration = $this->sns_Integration_direct_ok($params);
								if($snsintergration) {
									$msg = '연결되었습니다.\n트위터아이디로 로그인할 수 있습니다.';
									alert($msg);
									$this->sns_login_auth('sns_t');	//로그인세션추가
									//가입완료후 본래창 자동로그인처리하기
									if( $_COOKIE['snsreferer'] != $_SERVER['HTTP_HOST'] && $_COOKIE['snsreferer']){
										pageRedirect(get_connet_protocol().$_COOKIE['snsreferer'].'/sns_process/snsjoinck','');
										exit;
									}else{
										pageRedirect('../mypage/myinfo','','self.close(); opener');
										exit;
									}

								}else{//통합실패
									//잘못된 접근입니다.
									pageClose(getAlert('mb091'));
									exit;
								}
							}else{//중복체크
								$unsetuserdata = array('twuser'=>'','oauth_token'=>'','oauth_token_secret'=>'');
								$this->session->unset_userdata($unsetuserdata);
								$_SESSION['oauth_token']				= "";
								$_SESSION['oauth_token_secret']	= "";
								//이미 가입된 트위터계정 입니다.\\n다른 트위터 계정으로 가입해 주세요.
								pageClose(getAlert('mb101'));
								exit;
							}
						}else{
							### QUERY
							$where_arr = array('sns_t'=>$params['sns_t']);//
							$data = get_data('fm_member', $where_arr);
							if(!$data) {//정보가 없을 경우 가입후 로그인하기
								$snsregister = $this->sns_register_ok($params);
								if($snsregister['result'] === "auth_false" ) {//실명인증 중복 가입 체크 추가 @2016-09-12 ysm
										$unsetuserdata = array('twuser'=>'','oauth_token'=>'','oauth_token_secret'=>'');
										$this->session->unset_userdata($unsetuserdata);
										$_SESSION['oauth_token']				= "";
										$_SESSION['oauth_token_secret']	= "";
										$msg	= "이미 가입된 정보입니다.\\n로그인해 주세요.";
										pageClose(getAlert('mb101'));
										exit;
								}elseif($snsregister['result']) {
									if($this->app_status == "hold"){
										$result = false;
										//님은 아직 가입승인되지 않았습니다.
										$msg	= $params['user_name'].getAlert('mb104');
										//pageClose($msg);
										pageRedirect('../main/index',$msg,'self.close(); opener');
										exit;
									}elseif($kid_agree_check == 'N'){
										$msg	= $params['user_name'].getAlert('mb104');
										pageRedirect('/member/register_ok?kid_auth=N',$msg,'self.close(); opener');
										exit;
									}else{
										$snslogin = $this->sns_login($params,'sns_t');
										$common_msg = $snsregister['common_msg'];
										//가입 되었습니다.
										$msg = getAlert('mb221');

										$msg .= '\n'.$common_msg['coupon_msg'];

										//\n가입 '.$common_msg['emoneyJoin'].' '.$common_msg['pointJoin'].' 지급되었습니다.
										if	($common_msg['emoneyJoin'])
											$msg .= getAlert('mb222',array($common_msg['emoneyJoin'],$common_msg['pointJoin']));

										//\n추천 '.$common_msg['emoneyJoiner'].' '.$common_msg['pointJoiner'].' 지급되었습니다
										if	($common_msg['emoneyJoiner'])
											$msg .= getAlert('mb223',array($common_msg['emoneyJoiner'],$common_msg['pointJoiner']));

										//\n초대 '.$common_msg['emoneyInvitees'].' '.$common_msg['pointInvitees'].' 지급되었습니다
										if	($common_msg['emoneyInvitees'])
											$msg .= getAlert('mb224',array($common_msg['emoneyInvitees'],$common_msg['pointInvitees']));

										if	($snslogin['jcresult_msg'])$msg .= '\n'.$snslogin['jcresult_msg'];

										/*######################## 17.12.21 gcs userapp : 앱 처리 s */
										$send_params = $this->appmembermodel->config_send_params($snslogin);

										if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
										    $auto_login = 'y';
										}else{
										    $auto_login = 'n';
										}
										$send_params['auto_login'] = $auto_login;

										/*######################## 17.12.21 gcs userapp : 앱 처리 e */

										//가입완료후 본래창 자동로그인처리하기
										if( $_COOKIE['snsreferer'] != $_SERVER['HTTP_HOST'] && $_COOKIE['snsreferer']){
											pageRedirect(get_connet_protocol().$_COOKIE['snsreferer'].'/sns_process/snsjoinck',$msg);
											exit;
										}else{
										    //pageRedirect('/member/register_ok',$msg,'self.close(); opener');
										    /*######################## 17.12.21 userapp : 앱 처리 s */
										    if( $this->mobileapp == 'Y' ) {
										        $result_array = json_encode($send_params);
										        echo js(" self.close(); opener.twitterjoinlogin('$result_array'); ");
										    }else {
										        pageRedirect('../main/index',$msg,'self.close(); opener');
										    }

										    /*######################## 17.12.21 userapp : 앱 처리 e */
										    exit;
										}
									}
								}else{//가입실패시
									$unsetuserdata = array('twuser'=>'','oauth_token'=>'','oauth_token_secret'=>'');
									$this->session->unset_userdata($unsetuserdata);
									$_SESSION['oauth_token']				= "";
									$_SESSION['oauth_token_secret']	= "";
									//이미 가입된 트위터계정 입니다.\\n다른 트위터 계정으로 가입해 주세요.
									pageClose(getAlert('mb101'));
									exit;
								}
							}else{//이미가입된경우 로그인하기
								if($data[0]['status'] == "hold"){
									//님은 아직 가입승인되지 않았습니다.
									pageClose($data[0]['user_name'].getAlert('mb104'));
									exit;
								}else{
									$snslogin = $this->sns_login($params,'sns_t');
									if($snslogin) {
										$msg		= "login";
										if($snslogin['jcresult_msg'])$msg .= '<br />'.$snslogin['jcresult_msg'];

										/*######################## 17.12.21 gcs userapp : 앱 처리 s */
										$send_params = $this->appmembermodel->config_send_params($snslogin);

										if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
										    $auto_login = 'y';
										}else{
										    $auto_login = 'n';
										}
										$send_params['auto_login'] = $auto_login;
										/*######################## 17.12.21 gcs userapp : 앱 처리 e */

										//가입완료후 본래창 자동로그인처리하기
										if( $_COOKIE['snsreferer'] != $_SERVER['HTTP_HOST'] && $_COOKIE['snsreferer']){
											pageRedirect(get_connet_protocol().$_COOKIE['snsreferer'].'/sns_process/snsjoinck',$msg);
											exit;
										}else{
										    //pageRedirect('../main/index',$msg,'self.close(); opener');
										    /*######################## 17.12.21 userapp : 앱 처리 s */
										    if( $this->mobileapp == 'Y' ) {
										        $result_array = json_encode($send_params);
										        echo js(" self.close(); opener.twitterjoinlogin('$result_array'); ");
										    }else {
										        pageRedirect('../main/index',$msg,'self.close(); opener');
										    }

										    /*######################## 17.12.21 userapp : 앱 처리 e */
										    exit;

										}
									}else{//로그인실패시
										//탈퇴회원입니다.\\n관리자에게 문의해 주세요.
										pageClose(getAlert('mb102'));
										exit;
									}
								}
							}
						}
					}else{
						//잘못된 접근입니다.
						pageClose(getAlert('mb091').implode("\\n->",$twuserprofile->errors[0]->message));
						exit;
					}
				}
			}else{
				//잘못된 접근입니다.
				pageClose(getAlert('mb091'));
				exit;
			}
		}

		//twitter 쇼핑몰로그인하기 (새창)
		public function twitterlogin_legacy() {
			// 트위터 기본앱일 경우 공지 #19795 2018-06-27 hed
			if($this->arrSns['key_t'] == "ifHWJYpPA2ZGYDrdc5wQ" && $this->arrSns['use_t'] == "1"){
				pageClose("트위터의 앱 정책 변경으로 트위터를 통한 회원 가입 및 로그인 서비스를 제공하지 못하게 되었습니다.<br />트위터로 가입하신 회원은 다른 SNS 또는 신규 ID로 재가입해 주시기 바랍니다.<br />");
				exit;
			}
			if($this->arrSns['use_t']) {//twitter 사용여부
				$mtype = ($_POST['mtype'])?$_POST['mtype']:$_GET['mtype'];
				$twuserprofile = $this->snssocial->twitteraccount($_GET['oauth_verifier'], $mtype, 'login','');
				if($_GET['denied']){
					//"트위터에서 회원정보를 가져오지 못하였습니다.\\n관리자에게 문의해 주세요."
					pageClose(getAlert('mb100'));
					exit;
				}elseif( !$_SESSION['oauth_token'] && !$_SESSION['oauth_token_secret'] ) {
					//잘못된 접근입니다.
					pageClose(getAlert('mb091'));
					exit;
				}else{
					if( $twuserprofile['id']) {
						$params['rute']				= 'twitter';
						$params['userid']			= $twuserprofile['screen_name'];

						$this->db->where('userid', $params['userid']);
						$query		= $this->db->get("fm_member");
						$mem_chk	= $query->result_array();
						if($mem_chk) $params['userid'] = 'tw_'.$params['userid'];

						$params['sns_t']				= $twuserprofile['id'];

						### QUERY
						$where_arr	= array('sns_t'=>$params['sns_t']);//
						$data		= get_data('fm_member', $where_arr);
						if(!$data) {//정보가 없을 경우 가입후 로그인하기
							//#20067 2019-02-07 ycg 트위터 리다이렉트 경로 지정
							//일치하는 회원정보가 없습니다.\\n회원가입 후 이용해 주세요.
							pageRedirect('/member/agreement?join_type=twmember',getAlert('mb103'),'self.close(); opener');
							exit;
						}else{//가입된경우 로그인하기
							if($data[0]['status'] == "hold"){
								//님은 아직 가입승인되지 않았습니다.
								$msg = $data[0]['user_name'].getAlert('mb104');

								if($data[0]['kid_auth'] == "N"){
									pageRedirect('/member/kid_check',$msg,'self.close(); opener');
								}else{
									pageClose($msg);
									exit;
								}
							}else{
								$snslogin = $this->sns_login($params,'sns_t');

								if($snslogin) {
									$msg = "로그인 하였습니다.";
									if($snslogin['jcresult_msg'])$msg .= '<br />'.$snslogin['jcresult_msg'];
									//가입완료후 본래창 자동로그인처리하기
									if( $_COOKIE['snsreferer'] != $_SERVER['HTTP_HOST'] && $_COOKIE['snsreferer']){
										pageRedirect(get_connet_protocol().$_COOKIE['snsreferer'].'/sns_process/snsjoinck',$msg);
										exit;
									}else{
									    /*######################## 18.04.13 byuncs : 앱 처리(자동로그인) s */
									    if( $this->mobileapp == 'Y' ) {
									        $this->userInfo['member_seq'] = $snslogin['member_seq'];
									        $this->userInfo['userid'] = $snslogin['userid'];
									        $this->userInfo['user_name'] = $snslogin['user_name'];

									        //$send_params = $this->membermodel->app_memberInfo();
									        $send_params = $this->appmembermodel->memberInfo();
									        if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
									            $auto_login = 'y';
									        }else{
									            $auto_login = 'n';
									        }

									        $send_params['auto_login'] = $auto_login;
									        $send_params['channel'] = 'tw';
									        $send_params['session_id'] = session_id();
									        $result_array = json_encode($send_params);
									        echo js(" self.close(); opener.twitterjoinlogin('$result_array'); ");

									    }else {
									        //pageRedirect('./member/login',$msg,'self.close(); opener');
									        pageRedirect('../main/index',$msg,'self.close(); opener');
									    }
									    /*######################## 18.04.13 byuncs : 앱 처리(자동로그인) e */
										exit;
									}
									exit;
								}else{//로그인실패시
									//탈퇴회원입니다.<br />관리자에게 문의해 주세요.
									pageClose(getAlert('mb094'));
									exit;
								}
							}
						}
					}else{
						//잘못된 접근입니다.
						pageClose(getAlert('mb091').implode("\\n->",$twuserprofile));
						exit;
					}
				}
			}else{
				//잘못된 접근입니다.
				pageClose(getAlert('mb091'));
				exit;
			}
		}

		/*######################## 18.04.13 byuncs : 앱 처리(자동로그인) s */
		public function twitterlogincomplete()
		{
			$result		= true;
			$retururl	= '../';
			$msg		= "login";
			$send_params 	= isset($_POST['send_params']) ? json_decode(json_decode($_POST['send_params'])) : "";
			$return = array("result"=>$result,"msg"=>$msg,"retururl"=>$retururl,'send_params'=> $send_params);
			echo json_encode($return);
		}
		/*######################## 18.04.13 byuncs : 앱 처리(자동로그인) e */
	/**
	@ twitter api end
	------------------------------------------------------------
	**/

	/**
	@ naver api start
	------------------------------------------------------------
	**/
		//naver 로그인체크 (본래창) //login callback
		public function naverloginck() {
			$param = $this->input->post();
			$this->memberlibrary->kidAgreeCheck($param);

			/*######################## 18.02.27 gcs userapp : 앱 처리(자동로그인) s */
			//자동로그인

			if($param['app_auto_login'] == 'checked' ){
				setcookie('auto_login','y',time()+(86400*365),'/',".".$this->config_basic['domain']);	//1년간 저장
				$auto_login = 'y';
				$this->session->set_userdata('auto_login',$auto_login);
			}


			if(strpos($_SERVER['HTTP_USER_AGENT'], 'Firstmall_App' )!==false) {
				$this->mobileapp = 'Y';
				setcookie('mobileapp','Y',time()+(86400*365),'/',".".$this->config_basic['domain']);	//1년간 저장
				$this->session->set_userdata('mobileapp','Y');
			}


			/*######################## 18.02.27 gcs userapp : 앱 처리(자동로그인) e */

			if($this->arrSns['use_n']) {//naver 사용여부
				$mtype = ($param['mtype'])?$param['mtype']:$_GET['mtype'];//member, business
				$mform = ($param['mform'])?$param['mform']:$_GET['mform'];//join, login
				$loginurl = $this->snssocial->naverloginurl($mtype,$mform);

				if($loginurl) {

					/*######################## 18.02.27 gcs userapp : 앱 처리(app도메인 안잃도록 처리) s */
					if($this->mobileapp=='Y') {
						$loginurl .= "?mobileapp=Y";

						if($auto_login) {
							$loginurl .= "&auto_login=".$auto_login;
						}
					}

					/*######################## 18.02.27 gcs userapp : 앱 처리(app도메인 안잃도록 처리) e */

					if($_POST['m'] == "myinfo") $loginurl = urlencode($loginurl);
					$return = array('result'=>true, 'loginurl'=>$loginurl);
					echo json_encode($return);
					exit;
				}else{
					//네이버에서 회원정보를 가져오지 못하였습니다.<br/>관리자에게 문의해 주세요.
					$return = array('result'=>false, 'msg'=>getAlert('mb105'));
					echo json_encode($return);
					exit;
				}
			}else{
				//잘못된 접근입니다.
				$return = array('result'=>false, 'msg'=>getAlert('mb091'));
				echo json_encode($return);
				exit;
			}
		}

		//naver 로그인체크 callbackurl (새창)
		public function naveruserck_legacy() {
			if($this->arrSns['use_n']) {//naver 사용여부

				$sess_mtype = ($this->session->userdata('mtype'))? $this->session->userdata('mtype'):$_SESSION['mtype'];
				$sess_http_host = ($this->session->userdata('http_host'))? $this->session->userdata('http_host'):$_SESSION['http_host'];
				$sess_naver_state = ($this->session->userdata('naver_state'))? $this->session->userdata('naver_state'):$_SESSION['naver_state'];
				$sess_naver_access_token = (trim($this->session->userdata('naver_access_token')))? $this->session->userdata('naver_access_token'):$_SESSION['naver_access_token'];

				/*######################## 18.02.27 gcs userapp : 앱 처리(앱용 도메인 처리 위함) s */
				if($this->mobileapp =='Y' || $_COOKIE['mobileapp']=='Y' || $_GET['mobileapp']=='Y') {

					$this->mobileapp = 'Y';
					setcookie('mobileapp','Y',time()+(86400*365),'/',".".$this->config_basic['domain']);	//1년간 저장
					$this->session->set_userdata('mobileapp','Y');

					if($_GET['auto_login']) {
						$this->session->set_userdata('auto_login',$_GET['auto_login']);
						setcookie('auto_login',$_GET['auto_login'],time()+(86400*365),'/',".".$this->config_basic['domain']);	//1년간 저장
					}

				}
				/*######################## 18.02.27 gcs userapp : 앱 처리(앱용 도메인 처리 위함) e */


				## callback url host 와 실제 접근한 host 가 서로 다를 경우 실제 host 로 리다이렉트
				if(!$_GET['ok'] && $_SERVER['HTTP_HOST'] != $sess_http_host){

					if($sess_http_host){
						$pram = array();
						foreach($_GET as $k=>$v){
							$pram[] = $k."=".$v;
						}
						$pram_tmp = "&".implode("&",$pram);
						$re_url = $sess_http_host."/sns_process/naveruserck?ok=1".$pram_tmp;
						echo js("location.href='".get_connet_protocol().$re_url."'");
						exit;
					}else{
						//정상적인 접근이 아닙니다.
						pageClose(getAlert('mb134'));
						exit;
					}

				}else{
					//$mtype = ($_POST['mtype'])?$_POST['mtype']:$_GET['mtype'];
					if($_GET['error']){
						if($_GET['error_description'] == "Canceled By User"){
							//취소되었습니다.
							$msg = getAlert('mb106');
						}else{
							$msg = $_GET['error_description'];
						}
						pageClose($msg);
						exit;
					}else{
						$mtype			= $sess_mtype;
						$naveraccesstoken = $this->snssocial->naveraccesstoken($mtype, 'join');
						$err_msg = $this->getnavererrormsg($naveraccesstoken['error']);
						if($err_msg){
							pageClose($err_msg);
							exit;
						}else{
							if( !$sess_naveraccesstoken['error']) {
								echo js(" self.close(); opener.naverjoinlogin(); ");
								exit;
							}else{
								pageClose(getnavererrormsg($naveraccesstoken['error']));
								exit;
							}
						}
					}
				}
			}else{
				//관리자 > 네이버 로그인 사용여부를 확인해 주세요.
				pageClose(getAlert('mb107'));
				exit;
			}
		}

		public function getnavererrormsg($cd){

			$nv_login_error_msg = array();
			//인증받은 세션이 종료되었습니다.\\n새로고침 후 다시 시도해 주세요
			$nv_login_error_msg['session_error']			= getAlert('mb108');
			//$nv_login_error_msg['invalid_request']			= "파라미터 또는 요청문이 정상적이지 않습니다.\n시스템관리자에게 문의해 주세요.";
			//요청문이 정상적이지 않습니다.\\nCallback URL, Client ID, Client Key 값을 다시 한번 확인해 주세요.
			$nv_login_error_msg['invalid_request']			= getAlert('mb109');
			//"인증받지 않은 '인증허가코드' 입니다.\\n시스템관리자에게 문의해 주세요."
			$nv_login_error_msg['unauthorized_client']		= getAlert('mb110');
			//정의되어있지 않은 response type 입니다.\\n시스템관리자에게 문의해 주세요.
			$nv_login_error_msg['unsupported_response_type'] = getAlert('mb111');
			//"네이버 인증서버 오류입니다.\\n시스템관리자에게 문의해 주세요."
			$nv_login_error_msg['server_error']				= getAlert('mb112');

			return $nv_login_error_msg[$cd];
		}

		//naver 쇼핑몰회원가입 (새창)
		public function naverjoin() {

			$sess_mtype		= ($this->session->userdata('mtype'))? $this->session->userdata('mtype'):$_SESSION['mtype'];
			$sess_naver_state = ($this->session->userdata('naver_state'))? $this->session->userdata('naver_state'):$_SESSION['naver_state'];
			$sess_naver_access_token = ($this->session->userdata('naver_access_token'))? $this->session->userdata('naver_access_token'):$_SESSION['naver_access_token'];

			if($this->arrSns['use_n']) {//naver 사용여부

				$mtype = $sess_mtype;
				$naveruserprofile = $this->snssocial->naveraccount( $mtype, 'join');
				if( !$sess_naver_access_token ||  !$sess_naver_state ) {
					$unsetuserdata = array('user_accesstoken'=>'','naver_state'=>'','http_host'=>'','nvuser'=>'','mtype'=>'');
					$this->session->unset_userdata($unsetuserdata);

					$result = false;
					//잘못된 접근입니다.
					$msg	= getAlert('mb091');
				}else{

					if( $naveruserprofile['enc_id'] || $naveruserprofile['id']) {

						$params['rute']				= 'naver';
						$params['sns_type']			= 'sns_n';
						$params['email']			= $naveruserprofile['email'];
						$params['userid']			= $naveruserprofile['email'] ? $naveruserprofile['email'] : $naveruserprofile['id'];//사용자아이디

						//아이디 중복 확인
						$this->db->where('userid', $params['userid']);
						$query = $this->db->get("fm_member");
						$mem_chk = $query->result_array();
						if($mem_chk) $params['userid'] = 'nv_'.$params['userid'];
						$joinform	= config_load('joinform');

						$params['sns_n']				= $naveruserprofile['id'];		//네이버 회원고유번호(Client ID별)
						$params['sns_n_old']			= $naveruserprofile['enc_id'];	//네이버 회원고유번호(공통)
						$params['password']			= '';
						// 닉네임 - 로 전달되는 경우는 공백처리
						if( $naveruserprofile['nickname'] == '-') $naveruserprofile['nickname'] = '';
						$params['user_name']		= ($naveruserprofile['name'])?$naveruserprofile['name']:$naveruserprofile['nickname'];
						$params['nickname']			= $naveruserprofile['nickname'];
						if($params['nickname'] && mb_strlen($params['nickname'],'utf-8') > 10) {
							$params['nickname'] = substr($params['nickname'],0,10);
						}
						$params['nickname']			= ($params['nickname'])?$params['nickname']:"-";	// 닉네임이 없을 시 기본값 처리
						$params['email']			= $naveruserprofile['email'];
						$params['sms']				= 'n';

						if(in_array($naveruserprofile['gender'], array('F','M')))
							$params['sex']			= ($naveruserprofile['gender']=='F')?'female':'male';//Man

						$params['birthday']			= $naveruserprofile['birthday'];
						$params['birth_type']		= 'none';
						$params['status']				= $this->sns_mtype($mtype);
						$params['emoney']			= 0;
						$params['login_cnt']		= 0;
						$params['order_cn']	 		= 0;
						$params['order_sum']		= 0;
						$params['mtype']			= ($mtype == 'biz')?true:false;
						$params['regist_date']		= date('Y-m-d H:i:s');

						// naverloginck() 에서  kidAgreeCheck() 호출 함.
						// 14세 동의 체크 :: 2020.06.16 sms
						$kid_agree_check = $this->session->userdata('kid_agree_check');
						if(isset($kid_agree_check)){
							$params['kid_agree']	= $kid_agree_check;
							$params['kid_auth']		= $kid_agree_check;

							// 만 14세 미만 미인증시 가입 상태 '미승인'
							if($params['kid_auth'] == 'N'){
								$params['status'] = 'hold';
							}
						}

						if($_POST['facebooktype'] == 'mbconnect_direct') {//로그인된 상태에서 네이버 통합하기
							$where_arr = array('sns_n'=>$params['sns_n']);
							$mbdata = get_data('fm_member', $where_arr);
							if(!$mbdata){//회원찾기
								$snsintergration = $this->sns_Integration_direct_ok($params);
								if($snsintergration) {
									$this->sns_login_auth('sns_n');	//로그인세션추가
									$result		= true;
									$retururl	= '/mypage/sns';
									//연결되었습니다.<br />네이버아이디로 로그인할 수 있습니다.
									$msg	= getAlert('mb225');
								}else{//통합실패
									$result = false;
									//잘못된 접근입니다.
									$msg	= getAlert('mb091');
								}
							}else{//중복체크
								$unsetuserdata = array('naver_access_token'=>'','nvuser'=>'');
								$this->session->unset_userdata($unsetuserdata);
								$result = false;
								//이미 가입된 네이버 계정 입니다.<br />다른 네이버 계정으로 가입해 주세요.
								$msg	= getAlert('mb113');
							}
						}else{
							/*
							$where_arr = array('sns_n'=>$params['sns_n']);//
							$data = get_data('fm_member', $where_arr);
							*/
							$data = $this->sns_nid_logindata($params);
							if(!$params['user_name']) { $user_name = "고객"; }else{ $user_name = $params['user_name']; }
							if(!$data){
								//정보가 없을 경우 가입후 로그인하기
								$snsregister = $this->sns_register_ok($params);
								if($snsregister['result'] === "auth_false" ) {//실명인증 중복 가입 체크 추가 @2016-09-12 ysm
									$unsetuserdata = array('naver_access_token'=>'','nvuser'=>'','http_host'=>'','mtype'=>'');
									$this->session->unset_userdata($unsetuserdata);
									$result = false;
									$msg	= getAlert('mb113');
								}elseif($snsregister['result']) {

									if($this->app_status == "hold"){
										$result		= false;
										$msg		= $user_name.getAlert('mb104');	//님은 아직 가입승인되지 않았습니다.
										$retururl	= '../member/register_ok';
									}else{
										$snslogin = $this->sns_login($params,'sns_n');
										if($snslogin) {
											$result = true;
											$retururl = '/member/register_ok';
											$common_msg = $snsregister['common_msg'];
											//가입 되었습니다.
											$msg = getAlert('mb221');

											$msg .= '<br/>'.$common_msg['coupon_msg'];

											//<br/>가입 '.$common_msg['emoneyJoin'].' '.$common_msg['pointJoin'].' 지급되었습니다
											if	($common_msg['emoneyJoin'])
												$msg .= getAlert('mb222',array($common_msg['emoneyJoin'],$common_msg['pointJoin']));

											//<br/>추천 '.$common_msg['emoneyJoiner'].' '.$common_msg['pointJoiner'].' 지급되었습니다
											if	($common_msg['emoneyJoiner'])
												$msg .= getAlert('mb223',array($common_msg['emoneyJoiner'],$common_msg['pointJoiner']));

											//<br/>초대 '.$common_msg['emoneyInvitees'].' '.$common_msg['pointInvitees'].' 지급되었습니다
											if	($common_msg['emoneyInvitees'])
												$msg .= getAlert('mb224',array($common_msg['emoneyInvitees'],$common_msg['pointInvitees']));

											if	($snslogin['jcresult_msg'])	$msg .= '<br />'.$snslogin['jcresult_msg'];

											/*######################## 17.12.21 gcs userapp : 앱 처리 s */
											$send_params = $this->appmembermodel->config_send_params($snslogin);

											if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
											    $auto_login = 'y';
											}else{
											    $auto_login = 'n';
											}
											$send_params['auto_login'] = $auto_login;

											/*######################## 17.12.21 gcs userapp : 앱 처리 e */


										}else if($kid_agree_check=='N'){// 만 14세 미만 가입시
											$result = false;
											//님은 아직 가입승인되지 않았습니다.
											$msg	= $user_name.getAlert('mb104');
											$retururl = '/member/register_ok?kid_auth=N';
										}else{//로그인실패시
											$result = false;
											//탈퇴회원입니다.<br />관리자에게 문의해 주세요.
											$msg	= getAlert('mb094');
										}
									}
								}else{//가입실패시
									$unsetuserdata = array('naver_access_token'=>'','nvuser'=>'','http_host'=>'','mtype'=>'');
									$this->session->unset_userdata($unsetuserdata);
									$result = false;
									//이미 가입했거나 탈퇴회원입니다.
									$msg	= getAlert('mb114');
								}
							}else{

								/* 로그인 */
								if($data['status'] == 'hold'){ //이미 가입된경우 로그인하기
									$result = false;
									//님은 아직 가입승인되지 않았습니다.
									$msg	= $data['user_name'].getAlert('mb104');
								}else{

									$snslogin = $this->sns_login($params,'sns_n');
									$this->session->unset_userdata('mtype');
									if($snslogin) {
										$result		= true;
										$retururl	= '../';
										$msg		= getAlert('mb113');
										if($snslogin['jcresult_msg'])	$msg .= '<br />'.$snslogin['jcresult_msg'];
										/*######################## 17.12.21 gcs userapp : 앱 처리 s */
										$send_params = $this->appmembermodel->config_send_params($snslogin);

										if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
										    $auto_login = 'y';
										}else{
										    $auto_login = 'n';
										}
										$send_params['auto_login'] = $auto_login;
										/*######################## 17.12.21 gcs userapp : 앱 처리 e */




										//$return = array('result'=>true,'retururl'=>'/mypage/');
									}else{//로그인실패시
										$result = false;
										//탈퇴회원입니다.<br />관리자에게 문의해 주세요.
										$msg	= getAlert('mb094');
										if($data['kid_auth'] == 'N'){
											$msg	  = getAlert('mb104');
											$retururl = '/member/kid_check';
										}
									}
								}
							}
						}
					}else{
						$unsetuserdata = array('user_accesstoken'=>'','naver_state'=>'','nvuser'=>'','http_host'=>'','mtype'=>'');
						$this->session->unset_userdata($unsetuserdata);
						$result = false;
						//잘못된 접근입니다.
						$msg	= getAlert('mb091');
					}
				}
			}else{
				$unsetuserdata = array('user_accesstoken'=>'','naver_state'=>'','nvuser'=>'','http_host'=>'','mtype'=>'');
				$this->session->unset_userdata($unsetuserdata);
				$result = false;
				//네이버 로그인 연동 사용 여부를 확인해 주세요.
				$msg	= getAlert('mb115');
			}

			$return = array("result"=>$result,"msg"=>$msg,"retururl"=>$retururl,'send_params'=> $send_params);

			echo json_encode($return);
			exit;

		}

		# naver (enc_id or id) 로 로그인 체크
		public function sns_nid_logindata($params){

			if($params['sns_n_old'] == ''){
				$sql		= "select * from fm_member where status!='withdrawal' and sns_n in('".$params['sns_n']."','".$params['sns_n_old']."') and sns_n is not null and sns_n <> '' ";
			}else{
				$sql		= "select * from fm_member where status!='withdrawal' and sns_n in('".$params['sns_n']."','".$params['sns_n_old']."')";
			}
			$query	= $this->db->query($sql);
			$data	= $query->result_array();

			$sql		= "select sns_f from fm_membersns where member_seq='".$data[0]['member_seq']."'";
			$query		= $this->db->query($sql);
			$datasns	= $query->result_array();

			//☆등록된 네아로id가 end_id(공통회원인증키)일 경우 id(client id별 회원인증키)로 변경.
			if($params['sns_n']){
				if(strlen($data[0]['sns_n']) > 20){
					$this->db->where('member_seq', $data[0]['member_seq']);
					$result = $this->db->update('fm_member', array("sns_n"=>$params['sns_n']));
				}
				if(strlen($datasns[0]['sns_f']) > 20){
					$this->db->where('member_seq', $data[0]['member_seq']);
					$result = $this->db->update('fm_membersns', array("sns_f"=>$params['sns_n']));
				}
			}

			return $data[0];

		}

		//naver 쇼핑몰로그인하기 (새창)
		public function naverlogin() {

			$sess_nvuser		= ($this->session->userdata('nvuser'))? $this->session->userdata('nvuser'):$_SESSION['nvuser'];
			$sess_naver_access_token = ($this->session->userdata('naver_access_token'))? $this->session->userdata('naver_access_token'):$_SESSION['naver_access_token'];

			if($this->arrSns['use_n']) {//naver 사용여부
				$mtype = ($_POST['mtype'])?$_POST['mtype']:$_GET['mtype'];
				$naveruserprofile = $this->snssocial->naveraccount( $mtype, 'login');
				if( !$sess_naver_access_token ||  !$sess_nvuser ) {
					$result = false;
					//잘못된 접근입니다. 브라우저를 새로고침 후 다시 로그인 해주세요.
					$msg	= getAlert('mb116');
					$this->session->unset_userdata('http_host');
				}else{
					if( $naveruserprofile['enc_id'] || $naveruserprofile['id']) {
						$params['rute']				= 'naver';
						$params['email']			= $naveruserprofile['email'];
						$params['userid']			= $naveruserprofile['email'];//사용자아이디
						$params['sns_n']			= $naveruserprofile['id'];		//네이버 회원고유번호(Client ID별)
						$params['sns_n_old']		= $naveruserprofile['enc_id'];	//네이버 회원고유번호(공통)
						$params['password']			= '';
						$params['user_name']		= ($naveruserprofile['name'])?$naveruserprofile['name']:$naveruserprofile['nickname'];
						$params['nickname']			= $naveruserprofile['nickname'];
						if($params['nickname'] && mb_strlen($params['nickname'],'utf-8') > 10) {
							$params['nickname'] = substr($params['nickname'],0,10);
						}
						$params['email']			= $naveruserprofile['email'];
						$params['sms']				= 'n';

						if($naveruserprofile['gender'])
							$params['sex']			= ($naveruserprofile['gender']=='F')?'female':'male';//Man

						$params['birthday']			= $naveruserprofile['birthday'];
						$params['birth_type']		= 'none';
						$params['status']				= $this->sns_mtype($mtype);
						$params['emoney']			= 0;
						$params['login_cnt']		= 0;
						$params['order_cn']	 		= 0;
						$params['order_sum']		= 0;
						$params['mtype']				= ($mtype == 'biz')?true:false;
						$params['regist_date']	= date('Y-m-d H:i:s');
						//$where_arr = array('sns_n'=>$params['sns_n']);//
						//$data = get_data('fm_member', $where_arr);

						$data = $this->sns_nid_logindata($params);

						//정보가 없을 경우 회원가입 안내.
						if(!$data) {
							$result		= false;
							//일치하는 회원정보가 없습니다.<br />회원가입 후 이용해 주세요
							$msg		= getAlert('mb117');
							//#20067 2019-02-07 ycg 네이버 회원가입 리다이렉트 경로 수정
							$callback	= "/member/agreement?join_type=nvmember";
						//가입된경우 로그인하기
						}else{

							if($data['status'] == 'hold'){
								$result = false;
								//님은 아직 가입승인되지 않았습니다.
								$msg	= $data['user_name'].getAlert('mb104');
								if($data['kid_auth'] == 'N') $callback = '/member/kid_check';
							}else{
								$snslogin = $this->sns_login($params,'sns_n');
								if($snslogin) {
									$result		= true;
									$msg		= "login";
									if($snslogin['jcresult_msg'])$msg .= '<br />'.$snslogin['jcresult_msg'];
									$callback	= '../';

									if( strstr($_SERVER['HTTP_REFERER'],'/intro/adult_only') ) {
										$callback	= '/member/adult_auth';
									}

									/*######################## 17.12.21 gcs userapp : 앱 처리 s */
									$send_params = $this->appmembermodel->config_send_params($snslogin);

									if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
									    $auto_login = 'y';
									}else{
									    $auto_login = 'n';
									}
									$send_params['auto_login'] = $auto_login;

									/*######################## 17.12.21 gcs userapp : 앱 처리 e */

								}else{//로그인실패시
									$result = false;
									//탈퇴회원입니다.<br />관리자에게 문의해 주세요.
									$msg	= getAlert('mb094');
								}
							}
						}
					}else{
						$result = false;
						//회원정보 호출 오류입니다.
						$msg	= getAlert('mb118');
						//$msg	= "잘못된 접근입니다.".implode("<br>->",$naveruserprofile);
					}
				}
			}else{
				$result = false;
				//네이버 아이디로 로그인 사용여부를 확인해 주세요.
				$msg	= getAlert('mb119');
			}
			//$return = array('result'=>$result,'msg'=>$msg,'callback'=>$callback);
			//($send_params);
			$return = array('result'=>$result,'msg'=>$msg,'callback'=>$callback, 'send_params'=> $send_params); //######################## 17.12.21 gcs userapp : 앱 처리 ($send_params)

			echo json_encode($return);
			exit;
		}
	/**
	@ naver api end
	------------------------------------------------------------
	**/

	/**
	@ kakao login api start
	------------------------------------------------------------
	**/

		// kakao 사용확인 및 설정 key 값 불러오기
		public function kakaokeys(){
			$this->arrSns = ($this->arrSns)?$this->arrSns:config_load('snssocial');
			if($this->arrSns['use_k']) $return = array('result'=>true,'keys'=>$this->arrSns['key_k']);
				else $return = array('result'=>false,'keys'=>"");
			echo json_encode($return);
		}

		//kakao 아이디로 회원가입
		public function kakaojoin() {

			$aParam = $this->input->post();
			if($this->arrSns['use_k']) {//kakao 사용여부
				$mtype = ($aParam['mtype'])?$aParam['mtype']:$this->input->get('mtype');
				$this->snssocial->setkakaouser($aParam);
				$kakaouserprofile = $this->snssocial->kakaoaccount($mtype,'join');
				if( !$aParam['access_token'] || !$aParam['refresh_token']) {
					$result = false;
					//잘못된 접근입니다. 브라우저를 새로고침 후 다시 로그인 해주세요.
					$msg	= getAlert('mb116');
				}else{

					if( $aParam['id']) {

						$_mtype = ($mtype == 'biz') ? 'business' : 'member';
						$this->memberlibrary->kidAgreeCheck($aParam);

						$params['rute']				= 'kakao';
						$params['userid']			= "kko".$aParam['id']; //사용자아이디

						$this->db->where('userid', $params['userid']);
						$query = $this->db->get("fm_member");
						$mem_chk = $query->result_array();
						if($mem_chk) $params['userid'] = 'kko_'.$params['userid'];

						$params['sns_k']			= $aParam['id'];
						$params['password']			= '';
						$params['user_name']		= ($kakaouserprofile['nickname'])? $kakaouserprofile['nickname']:'';
						$params['nickname']			= ($kakaouserprofile['nickname'])? $kakaouserprofile['nickname']:'';
						if($params['nickname'] && mb_strlen($params['nickname'],'utf-8') > 10) {
							$params['nickname'] = substr($params['nickname'],0,10);
						}
						$params['sms']				= 'n';

						$params['birthday']			= '';
						$params['birth_type']		= 'none';
						$params['sex']					= 'none';
						$params['status']				= $this->sns_mtype($mtype);
						$params['emoney']			= 0;
						$params['login_cnt']		= 0;
						$params['order_cn']	 		= 0;
						$params['order_sum']		= 0;
						$params['mtype']			= ($mtype == 'biz')?true:false;
						$params['regist_date']		= date('Y-m-d H:i:s');
						$params['email'] = (isset($aParam['kakao_account']['email']) === true) ? $aParam['kakao_account']['email'] : '';

						$kid_agree_check = $this->session->userdata('kid_agree_check');
						if(isset($kid_agree_check)){
							$params['kid_agree']	= $kid_agree_check;
							$params['kid_auth']		= $kid_agree_check;

							// 만 14세 미만 미인증시 가입 상태 '미승인'
							if($params['kid_auth'] == 'N'){
								$params['status'] = 'hold';
							}
						}

						if($_POST['facebooktype'] == 'mbconnect_direct') {//로그인된 상태에서 카카오 통합하기
							$where_arr	= array('sns_k'=>$params['sns_k']);
							$mbdata		= get_data('fm_member', $where_arr);
							if(!$mbdata){//회원찾기
								$snsintergration = $this->sns_Integration_direct_ok($params);
								if($snsintergration) {
									$this->sns_login_auth('sns_k');	//로그인세션추가
									$result		= true;
									//연결되었습니다.<br />카카오톡아이디로 로그인 할 수 있습니다.
									$msg		= getAlert('mb226');
									$retururl	= '/mypage/myinfo';
								}else{//통합실패
									$result = false;
									//잘못된 접근입니다
									$msg	= getAlert('mb091');
								}
							}else{//중복체크
								if($mbdata[0]['status']=='hold'){
									$result = false;
									//님은 아직 가입승인되지 않았습니다.
									$msg	= $data[0]['user_name'].getAlert('mb104');
								}else{
									$result = false;
									//이미 가입된 카카오 계정 입니다.<br />다른 카카오 계정으로 가입해 주세요.
									$msg	= getAlert('mb120');
								}
							}
						}else{

							$where_arr = array('sns_k'=>$params['sns_k']);//
							$data = get_data('fm_member', $where_arr);
							
							if(!$data) {//정보가 없을 경우 가입후 로그인하기
								$snsregister = $this->sns_register_ok($params);
								if($snsregister['result'] === "auth_false" ) {//실명인증 중복 가입 체크 추가 @2016-09-12 ysm
										$result = false;
										$msg	= getAlert('mb120');
								}elseif($snsregister['result']) {
									// 미승인 상태
									if($this->app_status == "hold"){
										$result = false;
										//님은 아직 가입승인되지 않았습니다.
										$msg	= $params['user_name'].getAlert('mb104');
										$retururl	= '../member/register_ok';
									}else{
										$snslogin = $this->sns_login($params,'sns_k');
										$common_msg = $snsregister['common_msg'];
										//가입 되었습니다.
										$msg = getAlert('mb221');

										$msg .= '<br/>'.$common_msg['coupon_msg'];

										//<br/>가입 '.$common_msg['emoneyJoin'].' '.$common_msg['pointJoin'].' 지급되	었습니다
										if	($common_msg['emoneyJoin'])
											$msg .= getAlert('mb222',array($common_msg['emoneyJoin'],$common_msg['pointJoin']));

										//<br/>추천 '.$common_msg['emoneyJoiner'].' '.$common_msg['pointJoiner'].' 지	급되었습니다
										if	($common_msg['emoneyJoiner'])
											$msg .= getAlert('mb223',array($common_msg['emoneyJoiner'],$common_msg['pointJoiner']));

										//<br/>초대 '.$common_msg['emoneyInvitees'].' '.$common_msg['pointInvitees'].' 지급되었습니다
										if	($common_msg['emoneyInvitees'])
											$msg .= getAlert('mb224',array($common_msg['emoneyInvitees'],$common_msg['pointInvitees']));

										if	($snslogin['jcresult_msg'])	$msg .= '<br />'.$snslogin['jcresult_msg'];


										/*######################## 17.12.21 gcs userapp : 앱 처리 s */
										$send_params = $this->appmembermodel->config_send_params($snslogin);

										if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
										    $auto_login = 'y';
										}else{
										    $auto_login = 'n';
										}
										$send_params['auto_login'] = $auto_login;

										/*######################## 17.12.21 gcs userapp : 앱 처리 e */

										if($snslogin) {
											$result		= true;
											$retururl = '/member/register_ok';
										}else if($kid_agree_check=='N'){
											$result = false;
											$msg	= $params['user_name'].getAlert('mb104');
											$retururl = '/member/register_ok?kid_auth=N';
										}else{//로그인실패시
											$result = false;
											//탈퇴회원입니다.<br />관리자에게 문의해 주세요.
											$msg	= getAlert('mb094');
										}
									}
								}else{//가입실패시
									$result = false;
									//이미 가입했거나 탈퇴회원입니다.
									$msg	= getAlert('mb114');
								}
							}else{//이미가입된경우 로그인하기

								if($data[0]['status'] == 'hold'){
									$result = false;
									//님은 아직 가입승인되지 않았습니다.
									$msg	= $data[0]['user_name'].getAlert('mb104');
									if($data[0]['kid_auth']=='N'){
										$result	= false;
										$retururl	= '../member/kid_check';
									}
								}else{
									$snslogin = $this->sns_login($params,'sns_k');
									$this->session->unset_userdata('mtype');
									if($snslogin) {
										$result		= true;
										$retururl	= '../';
										// 로그인 성공시에는 별도로 msg return 안함 2020-02-25
										//$msg		= "login";
										if($snslogin['jcresult_msg'])$msg .= '<br />'.$snslogin['jcresult_msg'];

										/*######################## 17.12.21 gcs userapp : 앱 처리 s */
										$send_params = $this->appmembermodel->config_send_params($snslogin);

										if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
										    $auto_login = 'y';
										}else{
										    $auto_login = 'n';
										}
										$send_params['auto_login'] = $auto_login;

										/*######################## 17.12.21 gcs userapp : 앱 처리 e */

									}else{//로그인실패시
										$result = false;
										//탈퇴회원입니다.<br />관리자에게 문의해 주세요.
										$msg	= getAlert('mb094');
									}
								}
							}
						}
					}else{
						$result = false;
						//잘못된 접근입니다
						$msg	= getAlert('mb091');
					}
				}
			}else{
				$result = false;
				//"카카오 아이디로 로그인 사용여부를 먼저 확인해 주세요."
				$msg	= getAlert('mb121');
			}
			$return = array('result'=>$result,'msg'=>$msg,'retururl'=>$retururl, 'send_params'=> $send_params);
			echo json_encode($return);
			exit;
		}

		//kakao 아이디로 로그인하기
		public function kakaologin() {

			$aPostParams = $this->input->post();
			if($this->arrSns['use_k']) {//kakao 사용여부
				$mtype = ($aPostParams['mtype'])?$aPostParams['mtype']:$this->input->get('mtype');
				$this->snssocial->setkakaouser($aPostParams);
				$kakaouserprofile = $this->snssocial->kakaoaccount($mtype,'join');
				if( !$aPostParams['access_token'] || !$aPostParams['refresh_token']) {
					$result = false;
					//잘못된 접근입니다
					$msg	= getAlert('mb091');
				}else{

					if( $aPostParams['id']) {
						$params['rute']				= 'kakao';
						$params['userid']			= "kko".$aPostParams['id']; //사용자아이디

						$this->db->where('userid', $params['userid']);
						$query = $this->db->get("fm_member");
						$mem_chk = $query->result_array();
						if($mem_chk) $params['userid'] = 'kko_'.$params['userid'];

						$params['sns_k']			= $aPostParams['id'];	//사용자확인값
						$params['password']			= '';
						$params['user_name']		= $kakaouserprofile['nickname'];//
						$params['email']			= '';
						$params['sms']				= 'n';

						$params['birthday']			= '';
						$params['birth_type']		= 'none';
						$params['status']			= $this->sns_mtype($mtype);
						$params['emoney']			= 0;
						$params['login_cnt']		= 0;
						$params['order_cn']	 		= 0;
						$params['order_sum']		= 0;
						$params['mtype']			= ($mtype == 'biz')?true:false;
						$params['regist_date']		= date('Y-m-d H:i:s');
						$where_arr					= array('sns_k'=>$params['sns_k']);//
						$data						= get_data('fm_member', $where_arr);
						if(!$data) {//정보가 없을 경우 가입후 로그인하기
							$result = false;
							//일치하는 회원정보가 없습니다.<br />회원가입 후 이용해 주세요
							$msg	= getAlert('mb117');
							//#20067 2019-02-07 ycg 카카오 회원 가입 리턴값 전달
							$retururl = '/member/agreement?join_type=kkmember';
						}else{//가입된경우 로그인하기

							if($data[0]['status'] == 'hold'){
								$result = false;
								//님은 아직 가입승인되지 않았습니다.
								$msg	= $data[0]['user_name'].getAlert('mb104');
								if($data[0]['kid_auth']=='N'){
									$result	= false;
									$retururl	= '../member/kid_check';
								}
							}else{
								$snslogin = $this->sns_login($params,'sns_k');
								if($snslogin) {
									$result		= true;
									// 로그인 성공시에는 별도로 msg return 안함 2020-02-25
									//$msg		= "login";
									if($snslogin['jcresult_msg'])$msg .= '<br />'.$snslogin['jcresult_msg'];
									$retururl	= '../';

									/*######################## 17.12.21 gcs userapp : 앱 처리 s */
									$send_params = $this->appmembermodel->config_send_params($snslogin);

									if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
									    $auto_login = 'y';
									}else{
									    $auto_login = 'n';
									}
									$send_params['auto_login'] = $auto_login;

									/*######################## 17.12.21 gcs userapp : 앱 처리 e */
								}else{//로그인실패시
									$result = false;
									//탈퇴회원입니다.<br />관리자에게 문의해 주세요.
									$msg	= getAlert('mb094');
								}
							}
						}
					}else{
						$result = false;
						//잘못된 접근입니다.
						$msg	= getAlert('mb091').implode("<br>->",$kakaouserprofile);
					}
				}
			}else{
				$result = false;
				//잘못된 접근입니다.
				$msg	= getAlert('mb091');
			}

			//$return = array('result'=>$result,'msg'=>$msg,'retururl'=>$retururl);
			$return = array('result'=>$result,'msg'=>$msg,'retururl'=>$retururl, 'send_params'=> $send_params); //######################## 17.12.21 gcs userapp : 앱 처리 ($send_params)
			echo json_encode($return);
			exit;
		}

	/**
	@ kakao login api end
	------------------------------------------------------------
	**/

	/**
	* @sns 으로 로그인시
	* @
	*/
	// SNS 로그인 전처리 (회원정보 조회 후 상태별 처리)
	public function sns_login($params, $snstype) {

		// 회원 정보 조회
		$member	= $this->membermodel->getMemberBySns($snstype, $params[$snstype]);

		// 회원 정보가 없는 경우 바로 리턴
		if (!$member) return $member;

		// 휴면계정 체크
		if ($member['dormancy_seq'] && $this->app_member['dormancy']) {
			// 휴먼 해제 방법별 처리 후 페이지 이동
			$dormancyResult = $this->memberlibrary->inactiveDormant($member['member_seq'], $this->app_member['dormancy']);
			pageRedirect('/', $dormancyResult['msg']);
			exit;
		}

		// SNS 로그인 정보 중에 이메일 또는 휴대전화가 변경되면 회원정보 업데이트
		$updateInfo = [];
		if ($params['email'] && $member['email'] != $params['email'])
			$updateInfo['email'] = $params['email'];
		if ($params['cellphone'] && $member['cellphone'] != $params['cellphone'])
			$updateInfo['cellphone'] = $params['cellphone'];

		if ($updateInfo['email'] || $updateInfo['cellphone']) {
			$this->membermodel->update_member($updateInfo);
			$this->membermodel->update_private_encrypt($member['member_seq']);
		}

		// 로그인 이력 처리
        $this->memberlibrary->make_login_history($member['member_seq']);

		// sns 로그인/연동 계정 세션 저장
		$this->sns_login_auth($snstype);

		// sns 회원가입추가
		$this->Snsmemberck($params, 'sns_'.substr($params['rute'],0,1), $member);

		// 회원 로그인 세션 생성
		$this->create_member_session($member);

		// 사용자앱 설치 쿠폰 발행 2020-03-06
		if(checkUserApp(getallheaders())){
			$this->load->model('couponmodel');
			// 발급중지가 아닌경우
			$sc['whereis'] = ' and (type="app_install")  and issue_stop != 1 ';
			$coupon_multi_list = $this->couponmodel->get_coupon_multi_list($sc);
			foreach ($coupon_multi_list as $coupon_multi) {
				$this->couponmodel->_members_downlod($coupon_multi['coupon_seq'], $member['member_seq']);
			}
		}

		// 성인인증세션 처리
		$this->session->unset_userdata('auth_intro');
		$_SESSION['auth_intro']	= '';
		if($member['adult_auth'] == 'Y'){
			$this->session->sess_expiration = (60 * 5);
			$this->session->set_userdata(['auth_intro' => ['auth_intro_type'=>'auth', 'auth_intro_yn'=>'Y']]);
		}

		// 장바구니 목록 합치기
		$this->load->model('cartmodel');
		$this->cartmodel->merge_for_member($member['member_seq']);

		// 페이스북 좋아요 할인 합치기
		$this->db->where('session_id',session_id());
		$this->db->update('fm_goods_fblike', ['member_seq' => $member['member_seq']]);

		// 로그인 이벤트
		$this->load->model('joincheckmodel');
		$jcresult = $this->joincheckmodel->login_joincheck($member['member_seq']);
		if ($jcresult['code'] == 'success' ||  $jcresult['code'] == 'emoney_pay') {
			$member['jcresult_msg'] = $jcresult['msg'];
		}

		// 고객리마인드서비스 : 상세유입로그
		$this->load->helper('reservation');
		curation_log(["action_kind" => "login_sns"]);

		// 페이스북, 트위터 기본앱 종료로 인해 기본앱 사용중인 경우 로그인 시 전용앱으로 타입값 업데이트 처리
		if ($params['rute'] == 'facebook' && $member['sns_f_type'] != "1" && $this->arrSns['key_f'] != "455616624457601")
			$this->membermodel->setSocialAppType($member['member_seq'], 'sns_f_type');
		else if ($params['rute'] == 'twitter' && $member['sns_t_type'] != "1" && $this->arrSns['key_f'] != "ifHWJYpPA2ZGYDrdc5wQ")
			$this->membermodel->setSocialAppType($member['member_seq'], 'sns_t_type');

		return $member;
	}

	## 로그인/가입연동한 sns계정 세션 저장
	function sns_login_auth($snstype){

		$this->load->model('membermodel');
		$this->mdata = $this->membermodel->get_member_data($this->userInfo['member_seq']);//회원정보
		if($this->mdata['rute'] != 'none' && !$this->session->userdata("snslogn")){
			switch($snstype){
				case "sns_f": $snstype = "facebook"; break;
				case "sns_t": $snstype = "twitter"; break;
				case "sns_n": $snstype = "naver"; break;
				case "sns_k": $snstype = "kakao"; break;
				case "sns_i": $snstype = "instagram"; break;
				case "sns_a": $snstype = "apple"; break;
			}
			$this->session->set_userdata("snslogn",$snstype);
		}
	}

	// SNS 회원통합하기
	public function sns_Integration_ok($params){
		$this->load->model('ssl');
		$this->ssl->decode();

		### Validation
		//아이디
		$this->validation->set_rules('userid', getAlert('mb154'),'trim|required|max_length[20]|xss_clean');
		//비밀번호
		$this->validation->set_rules('password', getAlert('mb155'),'trim|required|max_length[32]|xss_clean');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$return = array('result'=>false, 'msg'=>$err['value']);
			echo json_encode($return);
			exit;
		}

		### QUERY
		$where_arr = array('userid'=>$_POST['userid'], 'password'=>md5($_POST['password']));
		$data = get_data('fm_member', $where_arr);
		if(!$data){
			//일치하는 회원정보가 없습니다.
			$return = array('result'=>false, 'msg'=>getAlert('mb156'));
			echo json_encode($return);
			exit;
		}

		### LOG
		$mbparams = $data[0];
		$snstype = " sns_".substr($params['rute'],0,1)."='".$params['sns_'.substr($params['rute'],0,1)]."', ";

		$qry = "update fm_member set ".$snstype." login_cnt = login_cnt+1, lastlogin_date = now(), login_addr = '".$_SERVER['REMOTE_ADDR']."' where member_seq = '{$mbparams['member_seq']}'";
		$result = $this->db->query($qry);

		$this->Snsmemberck($params, 'sns_'.substr($params['rute'],0,1), $mbparams);//sns회원가입추가

		### SESSION
		$this->create_member_session($mbparams);

		### 장바구니 MERGE
		$this->load->model('cartmodel');
		$this->cartmodel->merge_for_member($mbparams['member_seq']);

		//fblike 할인 MERGE
		$this->db->where('session_id',session_id());
		$this->db->update('fm_goods_fblike', array('member_seq' => $mbparams['member_seq']));


		### 로그인 이벤트
		$this->load->model('joincheckmodel');
		$jcresult = $this->joincheckmodel->login_joincheck($mbparams['member_seq']);

		if( $jcresult['code'] == 'success' ||  $jcresult['code'] == 'emoney_pay' ) {
			$mbparams['jcresult_msg'] = $jcresult['msg'];
		}
		return $mbparams;
	}


	// SNS 회원통합하기
	public function sns_Integration_direct_ok($params){
		$this->load->model('ssl');

		// 로그인이 되어있는지 검사
		if($this->userInfo['member_seq']){

			// 로그인이 되어있을 때 해당 회원 정보를 가져옴
			$where_arr = array('member_seq'=>$this->userInfo['member_seq']);
			$mbdata = get_data('fm_member', $where_arr);
			$mbparams = $mbdata[0];

			// 회원 정보에 sns_{sns 키} 컬럼에 값을 업데이트
			$snstype 	= " sns_".substr($params['rute'],0,1)."='".$params['sns_'.substr($params['rute'],0,1)]."' ";
			$qry 		= "update fm_member set ".$snstype." where member_seq = '{$mbparams['member_seq']}'";
			$result 	= $this->db->query($qry);

			// fm_membersns 테이블에 추가 또는 갱신처리
			$this->Snsmemberck($params, 'sns_'.substr($params['rute'],0,1), $mbparams );
		}else{

			// 현재 세션아이디값을 가진 sns 연동 회원 정보를 가져온다.
			$snswhere_arr 	= array('session_id'=>session_id());
			$snsjoindataar 	= get_data('fm_membersns_join', $snswhere_arr);
			$snsjoindata 	= $snsjoindataar[0];

			// 회원 정보가 있는경우
			if($snsjoindata['member_seq']){

				// 세션과 일치하는 회원 정보를 가져온다
				$where_arr = array('member_seq'=>$snsjoindata['member_seq']);
				$mbdata = get_data('fm_member', $where_arr);
				$mbparams = $mbdata[0];

				// 회원 정보에 sns_{sns 키} 컬럼에 값을 업데이트
				$snstype = " sns_".substr($params['rute'],0,1)."='".$params['sns_'.substr($params['rute'],0,1)]."' ";
				$qry = "update fm_member set ".$snstype." where member_seq = '{$mbparams['member_seq']}'";
				$result = $this->db->query($qry);

				// fm_membersns 테이블에 추가 또는 갱신처리
				$this->Snsmemberck($params, 'sns_'.substr($params['rute'],0,1), $mbparams );//sns회원가입추가
			}
		}
		return $mbdata;
	}

	/**
	* @ sns 회원통합 > 해제하기
	* @
	**/
	function snsdisconnect() {//회원통합 기본
		$snstype = ($_POST['snstype'])?$_POST['snstype']:'sns_f';
		$snsrute = ($_POST['snsrute'])?$_POST['snsrute']:'facebook';

		$where_arr = array('member_seq'=>$this->userInfo['member_seq']);
		$mbdata = get_data('fm_member', $where_arr);
		$mbparams = $mbdata[0];
		if($mbparams[$snstype]){
			$qry = "update fm_member set ".$snstype."='' where member_seq = '{$this->userInfo['member_seq']}'";
			$result = $this->db->query($qry);
			$this->load->model('snsmember');
			$this->snsmember->snsmb_delete($mbparams[$snstype], $snsrute);//sns회원삭제

			## 로그인/연동sns계정 세션 해제 시작
			$this->snsdisconnect_auth($snstype);
			## 로그인/연동 sns계쩡 세션 해제 끝

			//정상적으로 해제되었습니다.
			$return = array('result'=>$result, 'msg'=>getAlert('mb159'));
			echo json_encode($return);
		}else{
			//이미 해제된 상태입니다..
			$return = array('result'=>false, 'msg'=>getAlert('mb229'));
			echo json_encode($return);
		}
		exit;
	}

	## sns 연동해제에 따른 sns로그인 계정 교체
	function snsdisconnect_auth($snsrute){

		switch($snsrute){
			case "sns_f":
				$snstype = "facebook";
			break;
			case "sns_t":
				$snstype = "twitter";
				$unsetuserdata = array('twuser'=>'','oauth_token'=>'','oauth_token_secret'=>'');
				$this->session->unset_userdata($unsetuserdata);
				$_SESSION['oauth_token']				= "";
				$_SESSION['oauth_token_secret']	= "";
			break;
			case "sns_c":
				$unsetuserdata = array('cyuser'=>'','cyworld_request_token_secret'=>'');
				$this->session->unset_userdata($unsetuserdata);
				$snstype = "cyworld";
			break;
			case "sns_m":
				$snstype = "me2day";
			break;
			case "sns_n":
				$snstype = "naver";
				$unsetuserdata = array('naver_access_token'=>'','naver_state'=>'','nvuser'=>'');
				$this->session->unset_userdata($unsetuserdata);
			break;
			case "sns_k":
				$snstype = "kakao";
				$this->session->unset_userdata('kkouser');
			break;
			case "sns_d":
				$snstype = "daum";
				$unsetuserdata = array('dmuser'=>'','daum_access_token'=>'','http_host'=>'');
				$this->session->unset_userdata($unsetuserdata);
			case "sns_i":
				$snstype = "instagram";
				$unsetuserdata = array('ituser'=>'','instagram_access_token'=>'','http_host'=>'');
				$this->session->unset_userdata($unsetuserdata);
			break;
		}

		## 연결해제할 sns계정과 현재 로그인한 계정이 같을 시 남아 있는 sns 계정으로 교체
		if($this->session->userdata("snslogn") == $snstype){
			## 남아 있는 sns 계정 정보를 불러온다
			$sql	= "select rute,sns_f,email from fm_membersns where member_seq='".$this->userInfo['member_seq']."' and rute!='".$snsrute."' and rute!='' order by seq asc limit 1";
			$query		= $this->db->query($sql);
			$result		= $query->result_array();
			$next_sns	= $result[0]['rute'];
			$next_sns_f = ($result[0]['email'])? "_".$result[0]['email']:$result[0]['sns_f'];

			switch($next_sns){
				case "facebook": $next_rute = "fb"; break;
				case "twitter": $next_rute = "tw"; break;
				case "cyworld": $next_rute = "cy"; break;
				case "me2day":	$next_rute = "m2"; break;
				case "naver":	$next_rute = "nv"; break;
				case "kakao":	$next_rute = "kk"; break;
				case "daum":	$next_rute = "dm"; break;
			}


			$this->load->model('membermodel');
			$this->mdata = $this->membermodel->get_member_data($this->userInfo['member_seq']);//회원정보
			if($this->mdata['rute'] != 'none'){
				$userid = $next_rute."_".$next_sns_f."_".mktime();

				$up_param['userid'] = $userid;
				$up_param['rute'] = ($next_sns == '' || !$next_sns) ? 'none' : $next_sns;

				if( $this->mdata['sns_change'] == 1 ){
					unset($up_param['userid']);
					$userid = $this->mdata['userid'];
				}

				## 남아있는 sns 계정으로 id 교체
				$this->db->where(array('member_seq'=>$this->userInfo['member_seq']));
				$this->db->update('fm_member',$up_param);

				$sess_user = ( $this->session->userdata('user') )?$this->session->userdata('user'):$_SESSION['user'];

				if($snstype == "naver") {
					$sess_user['rute'] = "a";
				}else{
					$sess_user['rute'] = substr($snstype,0,1);
				}
				$sess_user['userid'] = $userid;
				$this->session->set_userdata('user',$sess_user);
			}


			$this->session->set_userdata('snslogn',$next_sns);
		}

	}


	/**
	* SNS 신규 회원가입
	* @ $params userid : sns id
	* @ $params password
	* @ $params user_name
	* @ $params rute enum('facebook', 'twiter', 'none')
	* @ $params sms enum('y', 'n')
	* @ $params sex enum('male', 'female', 'none')
	* @ $params birth_type enum('sola', 'luna', 'none')
	* @ $params status enum('done', 'hold', 'withdrawal')
	* @ $params emoney, login_cnt, order_cnt , order_sum,
	* @ $params lastlogin_date, regist_date, update_date, grade_update_date
	* @ $params auth_type enum('none', 'auth', 'ipin')
	* @
	*/
	public function sns_register_ok($params){
		// 회원가입 추가 정보 세팅
		$params['group_seq'] = '1';
		$params['password']	= md5($params['password']);
		$params['marketplace'] = !empty($_COOKIE['marketplace']) ? $_COOKIE['marketplace'] : ''; //유입매체
		$params['referer'] = $_COOKIE['shopReferer'];
		$params['referer_domain'] = $_COOKIE['refererDomain'];
		$params['user_icon'] = 1;
		$params['lastlogin_date'] = $params['regist_date'] ? $params['regist_date'] :date('Y-m-d H:i:s');
		$params['mtype'] = $params['mtype'] ? 'business' : 'member';
		$params['password_update_date'] = date("Y-m-d");

		// 플랫폼 설정
		$platform	= 'P';
		if		($this->ci->fammerceMode || $this->ci->storefammerceMode)	$platform	= 'F';
		elseif	($this->ci->_is_mobile_app_agent_android)		$platform	= 'APP_ANDROID';
		elseif	($this->ci->_is_mobile_app_agent_ios)		$platform	= 'APP_IOS';
		elseif	(($this->ci->mobileMode || $this->ci->storemobileMode) && $this->ci->agent->is_mobile )	$platform	= 'M';
		$params['platform']	= $platform;

		// 본인인증 여부
		$auth = $this->session->userdata('auth');
		if(isset($auth) && $auth['auth_yn']){
			$params['auth_type']	= $auth['namecheck_type'];
			$params['auth_code']	= $auth['namecheck_check'];
			if($params['auth_type'] != "safe"){//"ipin", "phone"

				// 실명인증 중복 가입 체크 추가 @2016-09-12 ysm
				$qry = "select count(*) as cnt from fm_member where auth_code='".$auth["namecheck_check"]."'";
				$query = $this->db->query($qry);
				$member = $query -> row_array();

				if($member["cnt"] > 0) {
					$this->session->unset_userdata('auth');
					if ($_SESSION['auth']) $_SESSION['auth']= '';
					return array('result'=>'auth_false');
				}else{
					$params['auth_vno']		= $auth['namecheck_vno'];
				}
			}else{
				$params['auth_vno']		= $auth['namecheck_key'];
			}
		}

		// 본인인증을 통해 가입했는지 확인 :: 2015-06-04 lwh
		$auth_intro = $this->session->userdata('auth_intro');
		if($auth_intro['auth_intro_yn'] == 'Y'){
			$params['adult_auth']	= 'Y';
		}

		// 페이스북, 트위터 연동일 경우 각 앱 설정 변수 추가
		// 기존에 있던 기본앱 지원 종료로 값을 전용앱으로 고정
		if ($params['rute'] == 'facebook') {
			$params['sns_f_type'] = 1;
		} else if ($params['rute'] == 'twitter') {
			$params['sns_t_type'] = 1;
		}

		// 회원 이름명 OR 업체명 20자 제한
		$params['user_name'] = check_member_name($params['user_name']);

		// 사용자앱 API KEY 생성
		$params['api_key'] = $this->appmembermodel->create_api_key($_POST['userid']);

		// 회원 정보 insert
		$data = filter_keys($params, $this->db->list_fields('fm_member'));
		$result = $this->db->insert('fm_member', $data);
		$memberseq = $this->db->insert_id();
		$params['member_seq'] = $memberseq;

		// sns회원가입추가
		$this->Snsmemberck($params, 'sns_'.substr($params['rute'],0,1));

		if($memberseq){
			// 회원 가입 통계 저장
			$this->load->model('statsmodel');
			$this->statsmodel->insert_member_stats($memberseq,$params['birthday'],$params['address'],$params['sex']);

			if($params['mtype'] == 'business'){//기업회원인경우
				$bdata = filter_keys($params, $this->db->list_fields('fm_member_business'));
				$this->db->insert('fm_member_business', $bdata);
			}

			### Private Encrypt
			$email = get_encrypt_qry('email');
			$cellphone = get_encrypt_qry('cellphone');
			$sql = "update fm_member set {$email}, {$cellphone},  update_date = now() where member_seq = {$memberseq}";//, {$cellphone}, {$phone}
			$this->db->query($sql);

			###
			if($result){//join success
				###
				$app = config_load('member');
				$reserve = config_load('reserve');
				$common_msg = array();

				if(($params['mtype'] != 'business' && $app['autoApproval']=='Y') || ($params['mtype'] == 'business' && $app['autoApproval_biz']=='Y')) {//자동승인인 경우

					$this->load->model('emoneymodel');
					$this->load->model('pointmodel');

					### 특정기간
					if($app['start_date'] && $app['end_date']){
						$today = date("Y-m-d");
						if($today>=$app['start_date'] && $today<=$app['end_date']){
							$app['emoneyJoin']	= $app['emoneyJoin_limit'];
							$app['pointJoin']	= $app['pointJoin_limit'];
						}
					}

					if($app['emoneyJoin']>0){
						$emoney['type']			= 'join';
						$emoney['emoney']		= $app['emoneyJoin'];
						$emoney['gb']			= 'plus';
						$emoney['memo']			= '회원 가입 마일리지';
						$emoney['memo_lang']	= $this->membermodel->make_json_for_getAlert("mp288");    // 회원 가입 마일리지
						$emoney['limit_date']	= get_emoney_limitdate('join');
						$this->membermodel->emoney_insert($emoney, $memberseq);
						//마일리지 '.$app['emoneyJoin'].'원
						$common_msg['emoneyJoin'] = getAlert('mb230',$app['emoneyJoin']);
					}

					if($app['pointJoin']>0 && $reserve['point_use']=='Y'){
						$point['type']		= 'join';
						$point['point']		= $app['pointJoin'];
						$point['gb']		= 'plus';
						$point['memo']		= '회원 가입 포인트';
						$point['memo_lang']	= $this->membermodel->make_json_for_getAlert("mp289");    // 회원 가입 포인트
						$point['limit_date']	= get_point_limitdate('join');
						$this->membermodel->point_insert($point, $memberseq);
						//포인트 '.$app['emoneyJoin'].'P'
						$common_msg['pointJoin'] = getAlert('mb231',$app['pointJoin']);
					}

					//추천시
					if($params['recommend'] &&  $params['recommend'] != $params['userid']){//본인추천체크
						$chk = get_data("fm_member",array("userid"=>$params['recommend'],"status"=>"done"));
						if(is_array($chk) && $chk[0]['member_seq']) {

							//추천받은자의 추천받은건수 증가 @2013-06-19
							$this->membermodel->member_recommend_cnt($chk[0]['member_seq']);

							//추천 받은 자 -> 제한함
							$todaymonth = date("Y-m");
							if($app['emoneyRecommend']>0) {
								$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
								$recommendtosc['select']	 = ' count(*) as totalcnt, sum(emoney) as totalemoney ';
								$emrecommendtock = $this->emoneymodel->get_data($recommendtosc);//추천한 회원 마일리지 지급여부
								$maxrecommend = ($app['emoneyLimit']*$app['emoneyRecommend']);

								if( $emrecommendtock['totalcnt'] < $app['emoneyLimit'] && $emrecommendtock['totalemoney'] <= $maxrecommend ) {
									$emoney['type']				= 'recommend_to';
									$emoney['emoney']			= $app['emoneyRecommend'];
									$emoney['gb']				= 'plus';
									$emoney['memo']				= '('.$params['userid'].') 추천 회원 마일리지';
									$emoney['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp236",$params['userid']);    // (%s) 추천 회원 마일리지
									$emoney['limit_date']		= get_emoney_limitdate('recomm');
									$emoney['member_seq_to']	= $memberseq;//2015-02-16
									$this->membermodel->emoney_insert($emoney, $chk[0]['member_seq']);
								}
							}

							if($app['pointRecommend']>0) {
								$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
								$recommendtosc['select']	 = ' count(*) as totalcnt, sum(point) as totalepoint ';
								$pmrecommendtock = $this->pointmodel->get_data($recommendtosc);//추천한 회원 마일리지 지급여부
								$maxrecommend = ($app['pointLimit']*$app['pointRecommend']);

								if( $pmrecommendtock['totalcnt'] < $app['pointLimit'] && $pmrecommendtock['totalepoint'] <= $maxrecommend ) {
									$point['type']				= 'recommend_to';
									$point['point']				= $app['pointRecommend'];
									$point['gb']				= 'plus';
									$point['memo']				= '('.$params['userid'].') 추천 회원 포인트';
									$point['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp237",$params['userid']);    // (%s) 추천 회원 포인트
									$point['limit_date']		= get_point_limitdate('recomm');
									$point['member_seq_to']		= $memberseq;//2015-02-16
									$this->membermodel->point_insert($point, $chk[0]['member_seq']);
								}
							}

							//추천한자(가입자)
							if($app['emoneyJoiner']>0) {
								unset($emoney);
								$emoney['type']				= 'recommend_from';
								$emoney['emoney']			= $app['emoneyJoiner'];
								$emoney['gb']				= 'plus';
								$emoney['memo']				= '['.$params['recommend'].'] 추천 마일리지';
								$emoney['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp243",$params['recommend']);    // [%s] 추천 마일리지
								$emoney['limit_date']		= get_emoney_limitdate('joiner');
								$emoney['member_seq_to']	= $chk[0]['member_seq'];//2015-02-16
								$this->membermodel->emoney_insert($emoney, $memberseq);
								//마일리지 '.$app['emoneyJoiner'].'원
								$common_msg['emoneyJoiner'] = getAlert('mb230',$app['emoneyJoiner']);
							}
						if($app['pointJoiner']>0 && $reserve['point_use']=='Y') {
								unset($point);
								$point['type']				= 'recommend_from';
								$point['point']				= $app['pointJoiner'];
								$point['gb']				= 'plus';
								$point['memo']				= '['.$params['recommend'].'] 추천 포인트';
								$point['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp244",$params['recommend']);    // [%s] 추천 포인트
								$point['limit_date']		= get_point_limitdate('joiner');
								$point['member_seq_to']		= $chk[0]['member_seq'];//2015-02-16
								$this->membermodel->point_insert($point, $memberseq);
								//'포인트 '.$app['pointJoiner'].'P'
								$common_msg['pointJoiner'] = getAlert('mb231',$app['pointJoiner']);
							}
						}
					}

					//초대시
					if($params['fb_invite']) {
						$chk = get_data("fm_member",array("member_seq"=>$params['fb_invite']));
						if($chk[0]['member_seq']) {

							//$fbuserprofile = $this->snssocial->facebooklogin();
							$fbuserprofile = $this->snssocial->facebookuserid();
							if ( !$fbuserprofile ) {
								$this->facebook = new Facebook(array(
								  'appId'  => $this->__APP_ID__,
								  'secret' => $this->__APP_SECRET__,
								  "cookie" => true
								));
								// Get User ID
								$fbuserprofile = $this->facebook->getUser();
								if($fbuserprofile && !$this->session->userdata('fbuser')){
									$this->session->set_userdata('fbuser', $fbuserprofile);
								}else{
									$fbuserprofile = $this->snssocial->facebooklogin();
									if( $fbuserprofile && !$this->session->userdata('fbuser') ) {
										$this->session->set_userdata('fbuser', $fbuserprofile);
									}
								}
							}else{
								if( !$this->session->userdata('fbuser') ) {
									$this->session->set_userdata('fbuser', $fbuserprofile);
								}
							}

							if($fbuserprofile['id']){
								$this->db->where('sns_f', $fbuserprofile['id']);
								$result = $this->db->update('fm_memberinvite', array("joinck"=>'1'));//가입여부 업데이트
							}

							//초대 한 자  -> 제한함
							$todaymonth = date("Y-m");
							if($app['emoneyInvited']>0) {
								$invitedtosc['whereis'] = ' and type = \'invite_from\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
								$invitedtosc['select']	 = ' count(*) as totalcnt, sum(emoney) as totalemoney ';
								$eminvitedtock = $this->emoneymodel->get_data($invitedtosc);//추천한 회원 마일리지 지급여부
								$maxinvited = ($app['emoneyLimit_invited']*$app['emoneyInvited']);

								if( $eminvitedtock['totalcnt'] <= $app['emoneyLimit_invited'] && $eminvitedtock['totalemoney'] <= $maxinvited ) {
									unset($emoney);
									$emoney['type']				= 'invite_from';
									$emoney['emoney']			= $app['emoneyInvited'];
									$emoney['gb']				= 'plus';
									$emoney['memo']				= '초대 마일리지';
									$emoney['memo_lang']		= $this->membermodel->make_json_for_getAlert("mp275");    // 초대 마일리지
									$emoney['limit_date']		= get_emoney_limitdate('invite_from');
									$emoney['member_seq_to']	= $memberseq;//2015-02-16
									$this->membermodel->emoney_insert($emoney, $chk[0]['member_seq']);
								}
							}
							if($app['pointInvited']>0){
								$invitedtosc['whereis'] = ' and type = \'invite_from\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
								$invitedtosc['select']	 = ' count(*) as totalcnt, sum(point) as totalpoint ';
								$pminvitedtock = $this->pointmodel->get_data($invitedtosc);//추천한 회원 마일리지 지급여부
								$maxinvited = ($app['pointLimit_invited']*$app['pointInvited']);

								if( $pminvitedtock['totalcnt'] <= $app['pointLimit_invited'] && $pminvitedtock['totalpoint'] <= $maxinvited && $reserve['point_use']=='Y' ) {
									unset($point);
									$point['type']				= 'invite_from';
									$point['point']				= $app['pointInvited'];
									$point['gb']				= 'plus';
									$point['memo']				= '초대 포인트';
									$point['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp276");    // 초대 포인트
									$point['limit_date']		= get_point_limitdate('invite_from');
									$point['member_seq_to']		= $memberseq;//2015-02-16
									$this->membermodel->point_insert($point, $chk[0]['member_seq']);
								}
							}

							//초대 받은 자(가입자)
							if($app['emoneyInvitees']>0){
								$emoney['type']					= 'invite_to';
								$emoney['emoney']				= $app['emoneyInvitees'];
								$emoney['gb']					= 'plus';
								$emoney['memo']					= '초대 회원 마일리지';
								$emoney['memo_lang']			= $this->membermodel->make_json_for_getAlert("mp277");    // 초대 회원 마일리지
								$emoney['limit_date']			= get_emoney_limitdate('invite_to');
								$emoney['member_seq_to']		= $chk[0]['member_seq'];//2015-02-16
								$this->membermodel->emoney_insert($emoney, $memberseq);
								//'마일리지 '.$app['emoneyInvitees'].'원'
								$common_msg['emoneyInvitees'] = getAlert('mb230',$app['emoneyInvitees']);
							}

							if($app['pointInvitees']>0 && $reserve['point_use']=='Y'){
								unset($point);
								$point['type']					= 'invite_to';
								$point['point']					= $app['pointInvitees'];
								$point['gb']					= 'plus';
								$point['memo']					= '초대 회원 포인트';
								$point['memo_lang']				= $this->membermodel->make_json_for_getAlert("mp278");    // 초대 회원 포인트
								$point['limit_date']			= get_point_limitdate('invite_to');
								$point['member_seq_to']			= $chk[0]['member_seq'];//2015-02-16
								$this->membermodel->point_insert($point, $memberseq);
								//'포인트 '.$app['emoneyInvitees'].'P'
								$common_msg['pointInvitees'] = getAlert('mb231',$app['emoneyInvitees']);
							}
						}
					}

				}else{
					$this->db->where('member_seq', $memberseq);
					$result = $this->db->update('fm_member', array("status"=>'hold'));
				}

				// 사용자앱 설치 쿠폰 발행 2020-03-06
				if(checkUserApp(getallheaders())){
					$this->load->model('couponmodel');
					$sc['whereis'] = ' and (type="app_install")  and issue_stop != 1 ';//발급중지가 아닌경우
					$coupon_multi_list = $this->couponmodel->get_coupon_multi_list($sc);
					$coupon_multicnt = 0;
					foreach($coupon_multi_list as $coupon_multi){  $coupon_multicnt++;
						$this->couponmodel->_members_downlod( $coupon_multi['coupon_seq'], $params['member_seq']);
					}
				}

				//신규회원가입쿠폰발급
				$this->load->model('couponmodel');
				$sc['whereis'] = ' and (type="member" or type="member_shipping")  and issue_stop != 1 ';//발급중지가 아닌경우
				$coupon_multi_list = $this->couponmodel->get_coupon_multi_list($sc);
				$coupon_multicnt = 0;
				foreach($coupon_multi_list as $coupon_multi){  $coupon_multicnt++;
					$this->couponmodel->_members_downlod( $coupon_multi['coupon_seq'], $memberseq);
				}
				//회원가입 쿠폰이 발행 되었습니다.
				if($coupon_multicnt) $common_msg['coupon_msg'] = getAlert('mb219');

				### LOG
				$qry = "update fm_member set login_cnt = login_cnt+1, lastlogin_date = now(), login_addr = '".$_SERVER['REMOTE_ADDR']."' where member_seq = '{$memberseq}'";
				$result = $this->db->query($qry);

				###
				$commonSmsData = array();
				$commonSmsData['join']['phone'][] = $params['cellphone'];
				$commonSmsData['join']['params'][] = $params;
				$commonSmsData['join']['mid'][] = $params['userid'];
				commonSendSMS($commonSmsData);
				sendMail($params['email'], 'join', $params['userid'], $params);

				$this->session->unset_userdata('fb_invite');//초대회원초기화
				// 회원가입 후 tmp_userid 세션 발급 완료 페이지에서 unset
				$this->session->set_userdata('tmp_userid', $params['userid']);

				return array('result'=>$result,'common_msg'=>$common_msg);
			}else{
				return array('result'=>false);
			}
		}else{
			return array('result'=>false);
		}
	}


	/**
	* @ sns sns회원가입추가
	**/
	public function Snsmemberck($params, $snstype = 'sns_f', $mbinfo = null ) {
		if (!$params[$snstype]) return '';

		// SNS 가입 정보 조회
		$where_arr = ['sns_f' =>$params[$snstype], 'rute'=>$params['rute']];
		$snsmbdata = get_data('fm_membersns', $where_arr);
		$snsmbparams = $snsmbdata[0];

		// 기존 SNS 가입 정보가 있는 경우 업데이트
		if ($snsmbparams) {
			// 회원 정보 업데이트 항목
			$updateParam = [
				"user_name" => $params['user_name'],
				"email" => $params['email'],
				"sex" => $params['sex'],
				"birthday" => $params['birthday'],
				"member_seq" => $mbinfo['member_seq'],
			];

			// 회원 seq가 있는 경우 해당 seq로 업데이트
			if ($params['member_seq']) $updateParam['member_seq'] = $params['member_seq'];

			// fm_membersns는 각 SNS 별 연동아이디를 sns_f 컬럼으로 통일되어있음
			$this->db->where(['sns_f' => $params[$snstype], 'rute' => $params['rute']]);
			$this->db->update('fm_membersns', $updateParam);
		} else {
			// 가입이 아닌 기존 계정에 통합인 경우 $mbinfo 에 seq로 치환
			if ($mbinfo['member_seq']) $params['member_seq'] = $mbinfo['member_seq'];

			// 연동 정보 insert
			$params['sns_f'] = $params[$snstype];
			$data = filter_keys($params, $this->db->list_fields('fm_membersns'));
			$this->db->insert('fm_membersns', $data);
		}

		$memberseq = ($mbinfo['member_seq']) ? $mbinfo['member_seq'] : $params['member_seq'];
		$this->Snswinopenjoindb($memberseq);
	}

	/**
	* @ sns sns회원가입추가
	**/
	public function Snswinopenjoindb($memberseq) {
		$snswhere_arr = array('session_id' =>session_id());
		$snsjoinmbdata = get_data('fm_membersns_join', $snswhere_arr);
		$snsjoinck = $snsjoinmbdata[0];
		if($snsjoinck) {//있는 경우 업데이트
			$this->db->where('session_id',session_id());
			$this->db->update('fm_membersns_join', array("member_seq"=>$memberseq,"session_id"=>session_id(),"update_date"=>date('Y-m-d H:i:s')));
		}else{
			$this->db->delete('fm_membersns_join', array('member_seq' => $memberseq));
			$snsjoinparams['member_seq']	= $memberseq;
			$snsjoinparams['session_id']		= session_id();
			$snsjoinparams['regist_date']		= date('Y-m-d H:i:s');
			$snsjoinparams['update_date']	= date('Y-m-d H:i:s');
			$data = filter_keys($snsjoinparams, $this->db->list_fields('fm_membersns_join'));
			$this->db->insert('fm_membersns_join', $data);
		}
	}

	/**
	*
	* @
	*/
	function sns_logout(){
		$unsetuserdata = array('user'=>'','fbuser'=>'','accesstoken'=>'','signedrequest'=>'','nvuser'=>'','mtype'=>'','naver_state'=>'','naver_access_token'=>'','kkouser'=>'','dmuser'=>'','daum_access_token'=>'','http_host'=>'','snslogn'=>'');
		$this->session->unset_userdata($unsetuserdata);
		$_SESSION['user']			= ''; $_SESSION['fbuser']				= '';
		$_SESSION['accesstoken']	= ''; $_SESSION['signedrequest']		= '';
		$_SESSION['nvuser']			= ''; $_SESSION['naver_access_token']	= '';
		$_SESSION['naver_state']	= ''; $_SESSION['mtype']				= '';
		$_SESSION['kkouser']		= '';
		$_SESSION['dmuser']			= ''; $_SESSION['daum_access_token']	= '';
		$_SESSION['http_host']		= ''; $_SESSION['snslogn']				= '';
		unset($this->userInfo, $_SESSION['user'], $_SESSION['fbuser'], $_SESSION['naver_state'], $_SESSION['naver_access_token'], $_SESSION['nvuser'], $_SESSION['accesstoken'], $_SESSION['signedrequest'],$_SESSION['kkouser'],$_SESSION['dmuser'],$_SESSION['daum_access_token']);

		pageReload('','parent');
		$_SESSION['user'] = '';
		exit;
	}

	/**
	* SNS 회원세션
	* @
	*/
	function create_member_session($data=array()){

		$this->load->helper('member');
		create_member_session($data);
	}

	function snsredirecturl(){
		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('snsurl', 'SNS URL', 'trim|valid_url|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		if( $aGetParams['snsloginstart'] ){

			$scripts[] = "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
			$scripts[] = '<script type="text/javascript" src="/app/javascript/plugin/jquery.activity-indicator-1.0.0.min.js"></script>';
			$scripts[] = '<script type="text/javascript" src="/app/javascript/js/common.js?v='.date('Ymd').'"></script>';
			echo '<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="ko" xml:lang="ko" >
<head>
<meta charset="utf-8"><link rel="stylesheet" type="text/css" href="/data/skin/'.$this->skin.'/css/common.css" />
<link rel="stylesheet" type="text/css" href="/data/skin/'.$this->skin.'/css/jqueryui/black-tie/jquery-ui-1.8.16.custom.css" />';
			foreach($scripts as $script){
				echo $script."\n";
			}
			echo '<script type="text/javascript">	$(document).ready(function() {
	loadingStart("body",{segments: 12, width: 15.5, space: 6, length: 13, color: \'#000000\', speed: 1.5});
});
</script>
</head><body>
</body>
<div id="openDialogLayer" style="display: none">
<div align="center" id="openDialogLayerMsg"></div>
</div>
<div id="ajaxLoadingLayer" style="display: none"></div>
</html>';
		}else{

			$snsurlar		= explode("?",$_GET[snsurl]);
			parse_str($snsurlar[1],$snsurlparam);

			$snsdataform	= "";
			foreach($snsurlparam as $snsname => $snsvalue){
				$snsdataform .= '<input type="hidden" name="'.$snsname.'" value="'.$snsvalue.'" >';
			}

			/*
			foreach($snsurlparam as $snsurl){
				$snsinput = explode("=",$snsurl);
				$snsdataform .= '<input type="hidden" name="'.$snsinput[0].'" value="'.$snsinput[1].'" >';
			}
			*/
			$scripts[] = "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
			$scripts[] = '<script type="text/javascript" src="/app/javascript/plugin/jquery.activity-indicator-1.0.0.min.js"></script>';
			$scripts[] = '<script type="text/javascript" src="/app/javascript/js/common.js?v='.date('Ymd').'"></script>';
			$scripts[] = "<script type='text/javascript'>";
			$scripts[] = "$(function() {";
			$scripts[] = "loadingStart('body',{segments: 12, width: 15.5, space: 6, length: 13, color: '#000000', speed: 1.5});";
			$scripts[] = 'document.form_chk.submit();';
			$scripts[] = "});";
			$scripts[] = "</script>";
			echo '<html><head>';
			foreach($scripts as $script){
				echo $script."\n";
			}
			echo '</head><body>
			<form  name="form_chk" action="'.$snsurlar[0].'">
			'.$snsdataform.'
			</form>
			</body>
			<div id="openDialogLayer" style="display: none">
			<div align="center" id="openDialogLayerMsg"></div>
			</div>
			<div id="ajaxLoadingLayer" style="display: none"></div>
			</html>
			';
		}
		exit;
	}

	//goods>view : interests/write/buy
	public function fbopengraph()
	{
		//2015-04-22 facebook version 2.* 권한 제한으로 publish_actions 값이 있을 때에만 적용 @2015-07-03
		if( $this->arrSns['key_f'] == '455616624457601'  || ( $this->arrSns['key_f'] != '455616624457601' && !$this->arrSns['facebook_publish_actions'] ) ) exit;

		//$this->snssocial->facebooklogin();
		$fbuserprofile = $this->snssocial->facebookuserid();
		if ( !$fbuserprofile ) {
			$this->facebook = new Facebook(array(
			  'appId'  => $this->__APP_ID__,
			  'secret' => $this->__APP_SECRET__,
			  "cookie" => true
			));
			// Get User ID
			$fbuserprofile = $this->facebook->getUser();
			if($fbuserprofile && !$this->session->userdata('fbuser')){
				$this->session->set_userdata('fbuser', $fbuserprofile);
			}else{
				$fbuserprofile = $this->snssocial->facebooklogin();
				if( $fbuserprofile && !$this->session->userdata('fbuser') ) {
					$this->session->set_userdata('fbuser', $fbuserprofile);
				}
			}
		}else{
			if( !$this->session->userdata('fbuser') ) {
				$this->session->set_userdata('fbuser', $fbuserprofile);
			}
		}

		$no		= ($_POST['no'])?(int) $_POST['no']:(int) $_GET['no'];
		$id		= ($_POST['id'])?$_POST['id']:$_GET['id'];
		$type	= ($_POST['type'])?$_POST['type']:$_GET['type'];
		if( $this->session->userdata('fbuser') ) {
			/**
			* facebook opengraph > love item
			**/
			if($no){
				$fbpermissions = $this->snssocial->facebookpermissions($this->snssocial->facebook);
				if( !$fbpermissions['error'] &&  !(array_key_exists('publish_actions', $fbpermissions['data'][0]) || in_array('publish_actions', $fbpermissions) ) && $type=='interests' ) {
					$session_id = session_id();
					if( $this->__APP_DOMAIN__ == $_SERVER['HTTP_HOST'] ){
						echo("window.open('/member/register_sns_form?popup=1&formtype=wishadd&firstmallcartid={$session_id}&snstype=facebook&stream=publish_actions&snsreferer={$_SERVER[HTTP_HOST]}&return_url={$_SERVER[HTTP_REFERER]}','snspopup','width=800px,height=400px,statusbar=no,scrollbars=no,toolbar=no');");
						exit;
					}else{
						echo("window.open('".get_connet_protocol()."{$this->config_system[subDomain]}/member/register_sns_form?popup=1&formtype=wishadd&firstmallcartid={$session_id}&snstype=facebook&stream=publish_actions&snsreferer={$_SERVER[HTTP_HOST]}&return_url={$_SERVER[HTTP_REFERER]}','snspopup','width=800px,height=400px,statusbar=no,scrollbars=no,toolbar=no');");
						exit;
					}
				}else{
					if($type=='write'){//게시글 등록시 게시글상세페이지로 이동
						if ( empty($id) ) $id = 'goods_review';
						$product_url = $this->domainurl.'/board/view?id='.$id.'&seq='.$no;
					}else{
						$product_url = $this->domainurl.'/goods/view?no='.$no;
					}
					$objectid = $this->goodsview_opengraph($product_url, $type);
					exit;
				}
			}
		}
		exit;
	}

	# 네이버 로그인 API 연동결과 저장 - firstmall로부터 전달받음 @2015-12-14 pjm
	public function nid_api_callback(){

		$message = $_POST['msg'];

		$nid_api = config_load('nid_api');
		if($nid_api['nid_stats'] != $_POST['nid_stats']){
			echo "error_stats";
			exit;
		}

		if($_POST['code'] == "00"){

			if(in_array($_POST['consumer_image_url'],array("undefined",4))){
				$_POST['consumer_image_url'] = '';
			}

			$snssocialar = array();
			$snssocialar['nid_client_id']			= $_POST['client_id'];
			$snssocialar['nid_client_name']			= $_POST['client_name'];
			$snssocialar['nid_client_secret']		= $_POST['client_secret'];
			$snssocialar['nid_service_name']		= $_POST['service_name'];
			$snssocialar['nid_callbackurl']			= $_POST['nid_callbackurl'];
			$snssocialar['nid_client_url']			= $_POST['client_url'];
			$snssocialar['nid_icon_url']			= $_POST['consumer_image_url'];

			if(is_array($snssocialar)) config_save_array('snssocial',$snssocialar);

			//기존 키값 지우기
			//$sql = "delete from fm_config where groupcd='snssocial' and codecd in('secret_n','key_n')";
			//$this->db->query($sql);

			echo "ok";

		}else{

			echo "error_code";

		}

		exit;

	}

	//승인/미승인 함수추가
	function sns_mtype($mtype){
		if( (!($mtype == 'business' || $mtype == 'biz') && $this->app_member['autoApproval']=='Y') || ( ($mtype == 'business' || $mtype == 'biz') && $this->app_member['autoApproval_biz']=='Y'))  {//자동승인인 경우
			$this->app_status = "done";
		}else{
			$this->app_status = "hold";
		}
		return $this->app_status;

	}

	// 애플 로그인 redirect 처리 추가 :: 2020-02-26 pjw
	function applecertificate_legacy(){
		// 애플 인증 결과값 확인
		$params			= $this->input->post();
		$certificate	= $this->snssocial->apple_cert_verify($params);

		// 인증 결과에 따라 세션처리 분기
		if($certificate['result']){
			// 연동 데이터 세션처리
			$this->session->set_userdata('apple_access_token', $certificate['data']['apple_access_token']);
			$this->session->set_userdata('apple_refresh_token', $certificate['data']['apple_refresh_token']);
			$this->session->set_userdata('apple_userid', $certificate['data']['apple_userid']);
			$this->session->set_userdata('apple_name', $certificate['data']['apple_name']);
			$this->session->set_userdata('apple_email', $certificate['data']['apple_email']);
		}else{
			// 연동 오류 시 메세지를 세션에 담음
			$this->session->set_userdata('apple_error_msg', $certificate['msg']);
		}

		// 결과페이지로 리다이렉트
		redirect('/sns/apple_callback');
		exit;
	}

	// 애플 로그인체크 (SNS 통합용)
	public function appleloginck() {
		if($this->arrSns['use_a']) {// 애플로그인 사용여부
			$mtype			= ($_POST['mtype'])?$_POST['mtype']:$_GET['mtype'];//member, business
			$mform			= ($_POST['mform'])?$_POST['mform']:$_GET['mform'];//join, login
			$loginurl		= $this->snssocial->apple_cert_url();
			$sns_connect	= $_POST['facebooktype'];

			// 파라미터 세션처리
			$this->session->set_userdata('mtype', $mtype);
			$this->session->set_userdata('mform', $mform);

			if($loginurl) {
				if($sns_connect) $loginurl = urlencode($loginurl);
				$return = array('result'=>true, 'loginurl'=>$loginurl);
				echo json_encode($return);
				exit;
			}else{
				$return = array('result'=>false, 'msg'=>'애플에서 회원정보를 가져오지 못하였습니다.\n관리자에게 문의해 주세요.');
				echo json_encode($return);
				exit;
			}
		}else{
			$return = array('result'=>false, 'msg'=>'잘못된 접근입니다.');
			echo json_encode($return);
			exit;
		}
	}

	// 애플 회원가입 추가 :: 2020-02-26 pjw
	function applejoin(){

		// 애플 로그인 사용여부
		if($this->arrSns['use_a']) {
			// 애플 연동 정보 세팅
			$mtype			= ($this->input->post('mtype')) ? $this->input->post('mtype') : $this->input->get('mtype');
			$mtype			= ($mtype != 'biz')? substr($mtype, 2, strlen($mtype) -1 ) : $mtype;
			$access_token	= $this->session->userdata('apple_access_token');
			$refresh_token	= $this->session->userdata('apple_refresh_token');
			$skin_patch_14years_old	= $this->session->userdata('skin_patch_14years_old');
			$userid			= $this->input->post('userid') ? $this->input->post('userid') : $this->session->userdata('apple_userid');
			$name			= $this->session->userdata('apple_name');
			$email			= $this->session->userdata('apple_email');

			if( !$access_token || !$refresh_token) {
				$result = false;
				$msg	= "잘못된 접근입니다. 브라우저를 새로고침 후 다시 로그인 해주세요.";
			}else if( $userid ){

				//$_mtype = ($mtype == 'biz') ? 'business' : 'member';
				//$this->memberlibrary->kidAgreeCheck(array('mtype'=>$_mtype, 'kid_agree'=>$kid_agree_check, 'skin_patch_14years_old'=>$skin_patch_14years_old));

				// 기존 회원 여부 검사
				// userid, sns_a 두 컬럼 중 하나라도 애플 연동 아이디값과 같은 경우 블락처리해야함
				$this->db->where('userid', $userid);
				$this->db->or_where('sns_a', $userid);
				$query = $this->db->get("fm_member");
				$mem_chk = $query->result_array();

				// 기본 인자값 설정
				$params['rute']				= 'apple';
				$params['userid']			= $userid;
				$params['sns_a']			= $userid;
				$params['password']			= '';
				$params['user_name']		= ($name)? $name:'';
				$params['sms']				= 'n';
				$params['birthday']			= '';
				$params['birth_type']		= 'none';
				$params['sex']				= 'none';
				$params['status']			= $this->sns_mtype($mtype);
				$params['emoney']			= 0;
				$params['login_cnt']		= 0;
				$params['order_cn']	 		= 0;
				$params['order_sum']		= 0;
				$params['mtype']			= ($mtype == 'biz')?true:false;
				$params['regist_date']		= date('Y-m-d H:i:s');
				$params['nickname']			= ($name)? $name:'';

				// 닉네임은 10자까지 제한하여 잘라버림
				if($params['nickname'] && mb_strlen($params['nickname'],'utf-8') > 10) {
					$params['nickname'] = substr($params['nickname'],0,10);
				}

				// 이미 연동된 계정정보만 가져온다
				$where_arr					= array('sns_a'=>$params['sns_a']);
				$data						= get_data('fm_member', $where_arr);

				$kid_agree_check = $this->session->userdata('kid_agree_check');
				if(isset($kid_agree_check)){
					$params['kid_agree']	= $kid_agree_check;
					$params['kid_auth']		= $kid_agree_check;

					// 만 14세 미만 미인증시 가입 상태 '미승인'
					if($params['kid_auth'] == 'N'){
						$params['status'] = 'hold';
					}
				}

				// 로그인 된 상태에서 애플 계정 연결하기
				if($_POST['facebooktype'] == 'mbconnect_direct') {

					// 마이페이지에서 넘어오는 경우 밖에 없으므로 마이페이지 주소를 기본 리턴 URL로 설정
					$retururl	= '/mypage/myinfo';

					if(!$data){ // 회원 정보가 없는경우

						// 현재 로그인 되어있는 회원 정보를 가져온다
						// 실제 연결처리는 회원이 있을때 아래 함수에서 연결한다
						$snsintergration = $this->sns_Integration_direct_ok($params);
						if($snsintergration) {

							// 회원 정보가 있는 경우 애플아이디를 연결 처리
							$this->sns_login_auth('sns_a');
							$result		= true;
							$msg		= "연결되었습니다.\n애플아이디로 로그인 할 수 있습니다.";
						}else{//통합실패
							$result = false;
							$msg	= "잘못된 접근입니다.";
						}

					}else if($data[0]['status']=='hold'){	// 승인이 안된 계정
						$result = false;
						$msg	= "아직 가입승인되지 않았습니다.";
					}else{	// 이미 가입된 계정
						$result = false;
						$msg	= "이미 가입된 애플 계정 입니다.\n다른 애플 계정으로 가입해 주세요.";
					}
				}else{

					if(!$data) {//정보가 없을 경우 가입후 로그인하기
						$snsregister = $this->sns_register_ok($params);

						if($snsregister['result'] === "auth_false" ) {//실명인증 중복 가입 체크 추가 @2016-09-12 ysm
								$result = false;
								$msg	= "이미 가입된 정보입니다.\n로그인해 주세요.";
						}elseif($snsregister['result']) {
							if($this->app_status == "hold"){
								$result = false;
								$msg	= "아직 가입승인되지 않았습니다.";
								$retururl	= '../member/register_ok';
							}else{
								$snslogin = $this->sns_login($params, 'sns_a');
								$common_msg = $snsregister['common_msg'];
								$msg = "가입 되었습니다.";

								$msg .= "\n".$common_msg['coupon_msg'];

								if	($common_msg['emoneyJoin'])
									$msg .= "\n가입 ".$common_msg['emoneyJoin']." ".$common_msg['pointJoin']." 지급되었습니다";

								if	($common_msg['emoneyJoiner'])
									$msg .= "\n추천 ".$common_msg['emoneyJoiner']." ".$common_msg['pointJoiner']." 지급되었습니다";

								if	($common_msg['emoneyInvitees'])
									$msg .= "\n초대 ".$common_msg['emoneyInvitees']." ".$common_msg['pointInvitees']." 지급되었습니다";

								if	($snslogin['jcresult_msg'])	$msg .= "\n".$snslogin['jcresult_msg'];
								/*######################## 17.12.21 userapp : 앱 처리 s */
								$send_params = $this->appmembermodel->config_send_params($snslogin);

								if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
									$auto_login = 'y';
								}else{
									$auto_login = 'n';
								}
								$send_params['auto_login'] = $auto_login;

								/*######################## 17.12.21 userapp : 앱 처리 e */

								if($snslogin) {
									$result		= true;
									$retururl = '/member/register_ok';
								}else if($kid_agree_check == 'N'){
									$result = true;
									if(!$params['user_name']) $user_name = "고객"; else $user_name = $params['user_name'];
									$msg	= $user_name.getAlert('mb104');
									$retururl = '/member/register_ok?kid_auth=N';
								}else{//로그인실패시
									$result = false;
									$msg	= "탈퇴회원입니다.\n관리자에게 문의해 주세요.";
								}
								$send_params['auto_login'] = $auto_login;

								/*######################## 17.12.21 userapp : 앱 처리 e */
							}
						}else{//가입실패시
							$result = false;
							$msg	= "이미 가입했거나 탈퇴회원입니다.";
						}
					}else{//이미가입된경우 로그인하기

						if($data[0]['status'] == 'hold'){
							$result = false;
							$msg	= "아직 가입승인되지 않았습니다.";
						}else{
							$snslogin = $this->sns_login($params,'sns_a');
							$this->session->unset_userdata('mtype');
							if($snslogin) {
								$result		= true;
								$retururl	= '../';
								$msg		= "로그인 하였습니다.";
								if($snslogin['jcresult_msg'])$msg .= '\n'.$snslogin['jcresult_msg'];

								/*######################## 17.12.21 userapp : 앱 처리 s */
								$send_params = $this->appmembermodel->config_send_params($snslogin);

								if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
									$auto_login = 'y';
								}else{
									$auto_login = 'n';
								}
								$send_params['auto_login'] = $auto_login;

								/*######################## 17.12.21 userapp : 앱 처리 e */
							}else{//로그인실패시
								$result = false;
								$msg	= "탈퇴회원입니다.\n관리자에게 문의해 주세요.";
							}
						}
					}
				}
			}else{
				$result = false;
				$msg	= "연동이 실패하였습니다. [result :: userid is required]";
			}
		}else{
			$result = false;
			$msg	= "애플아이디 로그인 사용여부를 먼저 확인해 주세요.";
		}

		$return = array('result'=>$result,'msg'=>$msg,'retururl'=>$retururl, 'send_params'=> $send_params);
		echo json_encode($return);
		exit;
	}

	// 애플 로그인 추가 :: 2020-02-26 pjw
	function applelogin(){

		// 애플 사용여부
		if($this->arrSns['use_a']) {
			// 애플 연동 정보 세팅
			$mtype			= ($this->input->post('mtype')) ? $this->input->post('mtype') : $this->input->get('mtype');
			$mtype			= substr($mtype, 2, strlen($mtype) -1 );
			$access_token	= $this->session->userdata('apple_access_token');
			$refresh_token	= $this->session->userdata('apple_refresh_token');
			$userid			= $this->input->post('userid') ? $this->input->post('userid') : $this->session->userdata('apple_userid');
			$name			= $this->session->userdata('apple_name');
			$email			= $this->session->userdata('apple_email');


			if( !$access_token || !$refresh_token) {
				$result = false;
				$msg	= "잘못된 접근입니다. 브라우저를 새로고침 후 다시 로그인 해주세요.";
			}else{

				if( $userid ) {

					// apple id 값으로 기존 회원 존재여부 검사
					$this->db->where('userid', $userid);
					$query		= $this->db->get("fm_member");
					$mem_chk	= $query->result_array();
					if($mem_chk) $params['userid'] = 'ap'.$userid;

					// 인자값 설정
					$params['rute']				= 'apple';
					$params['userid']			= $userid; //사용자아이디
					$params['sns_a']			= $userid;	//사용자확인값
					$params['password']			= '';
					$params['user_name']		= ($name)? $name:'';
					$params['nickname']			= ($name)? $name:'';
					$params['email']			= '';
					$params['sms']				= 'n';
					$params['birthday']			= '';
					$params['birth_type']		= 'none';
					$params['status']			= $this->sns_mtype($mtype);
					$params['emoney']			= 0;
					$params['login_cnt']		= 0;
					$params['order_cn']	 		= 0;
					$params['order_sum']		= 0;
					$params['mtype']			= ($mtype == 'biz')?true:false;
					$params['regist_date']		= date('Y-m-d H:i:s');

					// 해당 아이디로 기존 애플 연동 회원정보 조회
					$where_arr	= array('sns_a'=>$params['sns_a']);
					$data		= get_data('fm_member', $where_arr);

					//정보가 없을 경우 가입후 로그인하기
					if(!$data) {

						$result = false;
						$msg	= "일치하는 회원정보가 없습니다.\n회원가입 페이지로 이동합니다.";
						$retururl = '/member/agreement?join_type=apmember';

					}else{

						//가입된경우 로그인하기
						if($data[0]['status'] == 'hold'){
							$result = false;
							$msg	= "아직 가입승인되지 않았습니다.";
							if($data[0]['kid_auth']=='N'){
								$result = true;
								$retururl	= '/member/kid_check';
							}
						}else{
							$snslogin = $this->sns_login($params,'sns_a');
							if($snslogin) {
								$result		= true;
								if($snslogin['jcresult_msg'])$msg .= '\n'.$snslogin['jcresult_msg'];
								$retururl	= '../';
								/*######################## 17.12.21 userapp : 앱 처리 s */
								$send_params = $this->appmembermodel->config_send_params($snslogin);

								if($_COOKIE['auto_login']=='y' || $this->session->userdata('auto_login') =='y') { //동일페이지에서 쿠키가 바로 구워지지 않은 경우 고려해서 세션값도 조건으로 처리
									$auto_login = 'y';
								}else{
									$auto_login = 'n';
								}
								$send_params['auto_login'] = $auto_login;

								/*######################## 17.12.21 userapp : 앱 처리 e */
							}else{//로그인실패시
								$result = false;
								$msg	= "탈퇴회원입니다.\n관리자에게 문의해 주세요.";
							}
						}
					}
				}else{
					$result = false;
					$msg	= "연동이 실패하였습니다. [result :: userid is required]";
				}
			}
		}else{
			$result = false;
			$msg	= "애플아이디 로그인 사용여부를 먼저 확인해 주세요.";
		}

		$return = array('result'=>$result,'msg'=>$msg,'retururl'=>$retururl, 'send_params'=> $send_params); //######################## 17.12.21 userapp : 앱 처리 ($send_params)
		echo json_encode($return);
		exit;
	}
}

/* End of file sns_process_legacy.php */
/* Location: ./app/controllers/sns_process_legacy.php */