<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 회원과 관련된 소스들이 member_process, sns_process 등
 * 컨트롤러에 산재되어 있어 향후 병합을 위한 라이브러리 구조
 * 2018-08-06
 * by hed
 */
class MemberLibrary
{
	protected $PASSWORD_ALERT_CODE = array(
		'00' => '',
		'01' => 'mb253',				// 1. 영문 대문자 (26개) / 영문 소문자 (26개) / 숫자 (10개) / 특수문자 (26개)
		'02' => 'mb254',				// 2. abc,123  같은 연속된 영문,숫자,특수문자 3자 이상 사용 불가
		'03' => 'mb254',				// 3. aaa,111 같은 동일한 영문, 숫자,특수문자 3자 이상 사용 불가
		'04' => 'mb255',				// 4. 회원의 생년월일 사용 불가
		'05' => 'mb256',				// 5. 회원의 전화번호 사용 불가
		'06' => 'mb260',				// 5. 회원의 휴대폰 번호 사용 불가
		'07' => 'mb257',				// 6. 키보드 상 나란히 있는 문자열 사용 불가
		'08' => 'mb258',				// 7. 잘 알려진 단어 사용 불가
		'09' => 'mb259',				// 8. 비밀번호 변경 시, 이전과 동일한 비밀번호 사용 제한
		'99' => '',						// 입력 안 함, 입력여부 유효성 체크는 기존 로직 이용
	);

	function __construct() {
		$this->CI =& get_instance();
		$this->CI->load->model('membermodel');
		$this->CI->load->model('couponmodel');
	}


	// TODO
	// SMS & 이메일 발송
	// 자동 로그인에 따른 세션 처리
	// 앱처리
	// alert 처리
	// SNS 가입 처리

	/**
	 * 회원 가입 처리
	 * password 는 암호화 하지 않고 전달-join_member함수내에서 암호화 처리
	 *
	 * @param type $params
	 * @param type $check_join_benefit
	 * @return type
	 */
	public function join_member($params, $check_join_benefit=true){

		$app = config_load('member');
		
		$memberseq = null;
		$common_msg = array();

		$label_pr				= $params['label'];
		$label_sub_pr			= $params['labelsub'];
		$label_required			= $params['required'];
		$label_required_title	= $params['required_title'];
		$params['userid']		= strtolower($params['userid']);
		// debug($params);

		// 파라미터 기본값 설정, 그 이외의 파라미터는 호출 시 설정
		$params['regist_date']			= date('Y-m-d H:i:s');
		$params['lastlogin_date']		= $params['regist_date'];
		$params['group_seq']			= '1';
		$params['mtype']				= $params['mtype'] ? $params['mtype'] : 'member';

		// 비밀번호 암호화
		$params['password']	= hash('sha256',md5($params['password']));

		// 유입매체 설정
		$params['marketplace']			= !empty($_COOKIE['marketplace']) ? $_COOKIE['marketplace'] : '';//유입매체
		$params['referer']				= $_COOKIE['shopReferer'];
		$params['referer_domain']		= $_COOKIE['refererDomain'];

		//만 14세 미만 체크 설정
		$skin_patch_14years_old	= $this->CI->session->userdata('skin_patch_14years_old');
		if(isset($params['kid_auth']) && $params['kid_auth'] == 'N' && $skin_patch_14years_old){
			$params['status'] = 'hold';
		}
		if($params['kid_auth'] == ""){
			$params['kid_auth'] = null;
		}
		if($params['kid_agree'] == ""){
			$params['kid_agree'] = null;
		}

		// 가입 환경 설정 : 수신 파라미터가 없을 시 자동 체크
		$platform	= 'P';
		if		($this->CI->fammerceMode || $this->CI->storefammerceMode)	$platform	= 'F';
		elseif	($this->CI->_is_mobile_app_agent_android)		$platform	= 'APP_ANDROID';
		elseif	($this->CI->_is_mobile_app_agent_ios)		$platform	= 'APP_IOS';
		elseif	($this->CI->mobileMode || $this->CI->storemobileMode)		$platform	= 'M';
		$params['platform']	= ((empty($params['platform']))?$platform:$params['platform']);

		// 실명인증 체크
		###
		$auth = $this->CI->session->userdata('auth');
		if(isset($auth) && $auth['auth_yn']){
			$params['auth_type']	= $auth['namecheck_type'];
			$params['auth_code']	= $auth['namecheck_check'];
			if($params['auth_type'] != "safe"){//"ipin", "phone"

				/* 실명인증 중복 가입 체크 추가 leewh 2014-12-24 */
				unset($params_auth_member_cnt);
				$params_auth_member_cnt['auth_code'] = $auth["namecheck_check"];
				$auth_member_cnt = $this->CI->membermodel->get_item_total_count($params_auth_member_cnt);

				// 중복회원 가입 불가 처리
				if($auth_member_cnt > 0) {
					$this->CI->session->unset_userdata('auth');
					if ($_SESSION['auth']) $_SESSION['auth']= '';
					$common_msg['error_msg'] = getAlert('mb043');
					return $common_msg;
				}

				$params['auth_vno']		= $auth['namecheck_vno'];
			}else{
				$params['auth_vno']		= $auth['namecheck_key'];
			}
		}

		//초대
		$params['fb_invite']	= $this->CI->session->userdata('fb_invite');

		// 아이콘
		$params['user_icon']	= ($params['user_icon'])?$params['user_icon']:1;//@2014-08-06 icon

		// 본인인증을 통해 가입했는지 확인 :: 2015-06-04 lwh
		$auth_intro = $this->CI->session->userdata('auth_intro');
		if($auth_intro['auth_intro_yn'] == 'Y'){
			$params['adult_auth']	= 'Y';
		}

		###########################################################################
		## 2018.0.5.11 userapp : api_key 생성
		$this->CI->load->model('appmembermodel');
		$params['api_key'] = $this->CI->appmembermodel->create_api_key($params['userid']);
		//-->###########################################################################

		// 기업회원일경우 회사명 전달
		if(isset($params['mtype']) && $params['mtype']=='business'){
			$params['user_name']		= $params['bname'];
			$params['address_type']		= $params['baddress_type'];
			$params['address']			= $params['baddress'];
			$params['address_detail']	= $params['baddress_detail'];
			$params['phone']			= implode("-",$params['bphone']);
			$params['cellphone']		= implode("-",$params['bcellphone']);
		}

		// 회원 데이터 입력
		$memberseq = $this->CI->membermodel->insert_member($params);
		$params['member_seq'] = $memberseq;

		### Private Encrypt
		$this->private_encrypt($memberseq, $params);

		### 기업회원 데이터 입력
		$business_seq = false;
		if(isset($params['mtype']) && $params['mtype']=='business'){
			if(isset($params['bphone']))  $params['bphone'] = implode("-",$params['bphone']);
			if(isset($params['bcellphone']))  $params['bcellphone'] = implode("-",$params['bcellphone']);
			if(isset($params['new_bzipcode']))  $params['bzipcode'] = $params['new_bzipcode'];
			$business_seq = $this->CI->membermodel->insert_member_business($params);
		}
		$params['business_seq'] = $business_seq;
		###

		### //추가정보 저장
		unset($subinfo_seq);
		$subinfo_seq = array();
		foreach ($label_pr as $k => $data){
			foreach ($data['value'] as $j => $subdata){
				$setdata['label_value']= $subdata;
				$setdata['label_sub_value']= $label_sub_pr[$k]['value'][$j];
				$setdata['joinform_seq'] = $k;
				$setdata['member_seq'] = $memberseq;
				$subinfo_seq[] = $this->CI->membermodel->insert_member_subinfo($setdata);
			}
		}
		$params['subinfo_seq'] = $subinfo_seq;
		###

		// TODO SNS 회원 가입
		// ================================================
		// 향후 통합 시 추가 개발 필요!!!!!
		// ================================================

		// 회원 가입 혜택 및 가입 승인 처리
		if($memberseq && $check_join_benefit){
			unset($common_benefit_msg);
			$common_benefit_msg = array();
			// TODO
			// 회원의 수동 승인 후 혜택 지급에 대한 추가 분석 필요
			// 포인트와 마일리지는 승인 되었을 때만 지급
			if(!$this->join_hold($params)){	// 가입 대기가 아닐 시 혜택 지급
				$common_benefit_msg = $this->join_benefit($params);
			}

			// 쿠폰은 자동승인/수동승인에 관계없이 지급하게 수정
			$coupon_msg = $this->join_coupon($params);
			if(!empty($coupon_msg)) {
			    $common_benefit_msg['coupon_msg'] = $coupon_msg;
			}

			$common_msg = array_merge($common_msg, $common_benefit_msg);
		}

		// 회원 가입 통계 저장
		$this->CI->load->model('statsmodel');
		$this->CI->statsmodel->insert_member_stats($memberseq,$params['birthday'],$params['address'],$params['sex']);

		### SMS & Email 발송
		$commonSmsData = array();
		if	($params['mtype'] == 'business' && $params['bcellphone']){
			$commonSmsData['join']['phone'][] = $params['bcellphone'];
			$commonSmsData['join']['params'][] = $params;
			$commonSmsData['join']['mid'][] = $params['userid'];
		}else if($params['cellphone']) {
			$commonSmsData['join']['phone'][] = $params['cellphone'];
			$commonSmsData['join']['params'][] = $params;
			$commonSmsData['join']['mid'][] = $params['userid'];
		}
		if(count($commonSmsData) > 0){
			commonSendSMS($commonSmsData);
		}
		if($params['email']){
			sendMail($params['email'], 'join', $params['userid'], $params);
		}

		$this->CI->session->unset_userdata('fb_invite');//초대회원초기화

		// 회원가입 후 tmp_userid 세션 발급 완료 페이지에서 unset
		$this->CI->session->set_userdata('tmp_userid', $params['userid']);


		if(($params['mtype'] != 'business' && $app['autoApproval']=='Y') || ($params['mtype'] == 'business' && $app['autoApproval_biz']=='Y'))  {//자동승인인 경우

			### LOG
			$qry = "update fm_member set login_cnt = login_cnt+1, lastlogin_date = now(), login_addr = '".$_SERVER['REMOTE_ADDR']."' where member_seq = '{$memberseq}'";
			$result = $this->CI->db->query($qry);

			$key = get_shop_key();
			## 가입된 회원정보 세션용 재검색 :: 2015-01-26 lwh
			$query = "select A.*,B.business_seq,B.bname,C.group_name, AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone, AES_DECRYPT(UNHEX(A.email), '{$key}') as email from fm_member A LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq left join fm_member_group C on C.group_seq=A.group_seq where A.member_seq = '".$memberseq."'";
			$query			= $this->CI->db->query($query);
			$member_data	= $query->result_array();

			### 로그인 이벤트
			$this->CI->load->model('joincheckmodel');
			$jcresult = $this->CI->joincheckmodel->login_joincheck($memberseq);

			if( $jcresult['code'] == 'success' ||  $jcresult['code'] == 'emoney_pay' ) {
				$common_msg['jcresult_msg'] = $jcresult['msg'];
			}

			$this->make_login_history($memberseq);

			### SESSION
			$params					= $member_data[0];
			$params['member_seq']	= $memberseq;
			$this->CI->create_member_session($params);

			// 사용자앱 설치 쿠폰 발행
			// 회원가입 후 자동 로그인 시에도 발급
			if(checkUserApp(getallheaders())){
				$sc['whereis'] = ' and (type="app_install")  and issue_stop != 1 ';//발급중지가 아닌경우
				$coupon_multi_list = $this->CI->couponmodel->get_coupon_multi_list($sc);
				$coupon_multicnt = 0;
				foreach($coupon_multi_list as $coupon_multi){  $coupon_multicnt++;
					$this->CI->couponmodel->_members_downlod( $coupon_multi['coupon_seq'], $memberseq);
				}
			}

			//unset($params);
			//unset($params);
			if($params['layermode'] == 'layer' ){
				echo js("parent.openjoinokLayer('{$params['user_name']}');");
			}else{
				$params['user_name'] = urlencode($params['user_name']);
				$callback = "parent.location.href = '/member/register_ok'";
				//가입 되었습니다.
				$msg = getAlert('mb047');

				//2016-05-26 jhr 메세지 재정의
				$msg .= '<br />'.$common_msg['coupon_msg'];

				//$msg .= '<br />가입 '.$common_msg['emoneyJoin'].' '.$common_msg['pointJoin'].' 지급되었습니다';
				if	($common_msg['emoneyJoin'])
					$msg .= getAlert('mb048',array($common_msg['emoneyJoin'],$common_msg['pointJoin']));

				//$msg .= '<br />추천 '.$common_msg['emoneyJoiner'].' '.$common_msg['pointJoiner'].' 지급되었습니다';
				if	($common_msg['emoneyJoiner'])
					$msg .= getAlert('mb049',array($common_msg['emoneyJoiner'],$common_msg['pointJoiner']));

				//$msg .= '<br />초대 '.$common_msg['emoneyInvitees'].' '.$common_msg['pointInvitees'].' 지급되었습니다';
				if	($common_msg['emoneyInvitees'])
					$msg .= getAlert('mb050',array($common_msg['emoneyInvitees'],$common_msg['pointInvitees']));

				if	($common_msg['jcresult_msg'])
					$msg .= '<br />'.$common_msg['jcresult_msg'];



				/*######################## 17.12.18 gcs userapp : 앱 처리 s */
				if($this->CI->mobileapp=='Y'){
					$api_key =  $params['api_key'];

					//쿠폰보유건
					/*$this->CI->load->model('couponmodel');
					$sc['today']			= date('Y-m-d',time());
					$dsc['whereis'] = " and member_seq=".$params['member_seq']." and use_status='unused' AND ( (issue_startdate is null  AND issue_enddate is null ) OR (issue_startdate <='".$sc['today']."' AND issue_enddate >='".$sc['today']."') )";//사용가능한
                    $coupondownloadtotal = $this->CI->couponmodel->get_download_total_count($dsc);*/
                    $coupondownloadtotal = 0;

					echo "<script>var param = {member_seq : ".$params['member_seq'].", user_id : '".$params['userid']."', user_name : '".$params['user_name']."', session_id : '".$this->CI->session->userdata('session_id')."', channel : 'none', reserve : '".$params['emoney']."', balance : '".$params['cash']."', coupon : '".$coupondownloadtotal."', auto_login : 'n', api_key : '".$api_key."'}; var strParam = JSON.stringify(param);";

					if ($this->CI->m_device=='iphone') {
						echo "var dataStr = 'MemberInfo' + '?' + strParam;  window.webkit.messageHandlers.CSharp.postMessage(dataStr);</script>";
					}else{

						echo "var dataStr = 'MemberInfo' + '?' + strParam; CSharp.postMessage(dataStr);</script>";
					}
				}
				/*######################## 17.12.18 gcs userapp : 앱 처리 e */
			}
		}else{
			if($params['layermode'] == 'layer' ){
				//echo js("parent.openjoinokLayer('{$params['user_name']}');");
				echo js("parent.location.href = '/main/index';");
			}else{
				$params['user_name'] = urlencode($params['user_name']);
				$callback = "parent.location.href = '/member/register_ok'";
				//가입 되었습니다.
				$msg = getAlert('mb047');

				//2016-05-26 jhr 메세지 재정의
				$msg .= '<br />'.$common_msg['coupon_msg'];

				//$msg .= '<br />가입 '.$common_msg['emoneyJoin'].' '.$common_msg['pointJoin'].' 지급되었습니다';
				if	($common_msg['emoneyJoin'])
					$msg .= getAlert('mb048',array($common_msg['emoneyJoin'],$common_msg['pointJoin']));

				//$msg .= '<br />추천 '.$common_msg['emoneyJoiner'].' '.$common_msg['pointJoiner'].' 지급되었습니다';
				if	($common_msg['emoneyJoiner'])
					$msg .= getAlert('mb049',array($common_msg['emoneyJoiner'],$common_msg['pointJoiner']));

				//$msg .= '<br />초대 '.$common_msg['emoneyInvitees'].' '.$common_msg['pointInvitees'].' 지급되었습니다';
				if	($common_msg['emoneyInvitees'])
					$msg .= getAlert('mb050',array($common_msg['emoneyInvitees'],$common_msg['pointInvitees']));
			}
		}

		$common_msg['msg'] = $msg;
		$common_msg['memberseq'] = $memberseq;
		$common_msg['userid'] = $params['userid'];

		return $common_msg;
	}
	/**
	 * 회원 가입 혜택 처리
	 * @param type $memberseq
	 */
	protected function join_benefit($params){
		$memberseq = $params['member_seq'];

		###
		$app = config_load('member');
		$common_msg = array();

		$this->CI->load->model('emoneymodel');
		$this->CI->load->model('pointmodel');

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
			$emoney['memo']			= "회원 가입 마일리지";
			$emoney['memo_lang']	= $this->CI->membermodel->make_json_for_getAlert("mp288");    // 회원 가입 마일리지
			$emoney['limit_date']   = get_emoney_limitdate('join');
			$this->CI->membermodel->emoney_insert($emoney, $memberseq);
			//'마일리지 '.$app['emoneyJoin'].'원'
			$common_msg['emoneyJoin'] = getAlert('mb044',get_currency_price($app['emoneyJoin'],2));
		}

		if($app['pointJoin']>0){
			$point['type']			= 'join';
			$point['point']			= $app['pointJoin'];
			$point['gb']			= 'plus';
			$point['memo']			= "회원 가입 포인트";
			$point['memo_lang']		= $this->CI->membermodel->make_json_for_getAlert("mp289");    // 회원 가입 포인트
			$point['limit_date']	= get_point_limitdate('join');
			$this->CI->membermodel->point_insert($point, $memberseq);
			//'포인트 '.$app['emoneyJoin'].'P'
			$common_msg['pointJoin'] = getAlert('mb045',$app['pointJoin']);
		}

		//추천시
		if($params['recommend'] &&  $params['recommend'] != $params['userid']){//본인추천체크
			$chk = get_data("fm_member",array("userid"=>$params['recommend'],"status"=>"done"));
			if(is_array($chk) && $chk[0]['member_seq']) {

				//추천받은자의 추천받은건수 증가 @2013-06-19
				$this->CI->membermodel->member_recommend_cnt($chk[0]['member_seq']);

				//추천 받은 자 -> 제한함
				$todaymonth = date("Y-m");
				if($app['emoneyRecommend']>0) {
					$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
					$recommendtosc['select']	 = ' count(*) as totalcnt, sum(emoney) as totalemoney ';
					$emrecommendtock = $this->CI->emoneymodel->get_data($recommendtosc);//추천한 회원 마일리지 지급여부

					$maxrecommend = ($app['emoneyLimit']*$app['emoneyRecommend']);

					if( $emrecommendtock['totalcnt'] < $app['emoneyLimit'] && $emrecommendtock['totalemoney'] <= $maxrecommend ) {
						$emoney['type']			= 'recommend_to';
						$emoney['emoney']		= $app['emoneyRecommend'];
						$emoney['gb']			= 'plus';
						$emoney['memo']         = '('.$params['userid'].') 추천 회원 마일리지';
						$emoney['memo_lang']	= $this->CI->membermodel->make_json_for_getAlert("mp236",$params['userid']);    // (%s) 추천 회원 마일리지
						$emoney['limit_date']   = get_emoney_limitdate('recomm');
						$emoney['member_seq_to']	= $memberseq;//2015-02-16
						$this->CI->membermodel->emoney_insert($emoney, $chk[0]['member_seq']);
					}
				}

				if($app['pointRecommend']>0) {
					$recommendtosc['whereis'] = ' and type = \'recommend_to\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
					$recommendtosc['select']	 = ' count(*) as totalcnt, sum(point) as totalepoint ';
					$pmrecommendtock = $this->CI->pointmodel->get_data($recommendtosc);//추천한 회원 마일리지 지급여부
					$maxrecommend = ($app['pointLimit']*$app['pointRecommend']);

					if( $pmrecommendtock['totalcnt'] < $app['pointLimit'] && $pmrecommendtock['totalepoint'] <= $maxrecommend ) {
						$point['type']			= 'recommend_to';
						$point['point']			= $app['pointRecommend'];
						$point['gb']			= 'plus';
						$point['memo']			= '('.$params['userid'].') 추천 회원 포인트';
						$point['memo_lang']		= $this->CI->membermodel->make_json_for_getAlert("mp237",$params['userid']);    // (%s) 추천 회원 포인트
						$point['limit_date']    = get_point_limitdate('recomm');
						$point['member_seq_to']	= $memberseq;//2015-02-16
						$this->CI->membermodel->point_insert($point, $chk[0]['member_seq']);
					}
				}

				//추천한자(가입자)
				if($app['emoneyJoiner']>0) {
					unset($emoney);
					$emoney['type']             = 'recommend_from';
					$emoney['emoney']           = $app['emoneyJoiner'];
					$emoney['gb']               = 'plus';
					$emoney['memo']             = '['.$params['recommend'].'] 추천 마일리지';
					$emoney['memo_lang']        = $this->CI->membermodel->make_json_for_getAlert("mp243",$params['recommend']);    // [%s] 추천 마일리지
					$emoney['limit_date']       = get_emoney_limitdate('joiner');
					$emoney['member_seq_to']    = $chk[0]['member_seq'];//2015-02-16
					$this->CI->membermodel->emoney_insert($emoney, $memberseq);

					//'마일리지 '.$app['emoneyJoiner'].'원'
					$common_msg['emoneyJoiner'] = getAlert('mb044',get_currency_price($app['emoneyJoiner'],3));
				}
				if($app['pointJoiner']>0) {
					unset($point);
					$point['type']				= 'recommend_from';
					$point['point']				= $app['pointJoiner'];
					$point['gb']				= 'plus';
					$point['memo']				= '['.$params['recommend'].'] 추천 포인트';
					$point['memo_lang']			= $this->CI->membermodel->make_json_for_getAlert("mp244",$params['recommend']);    // [%s] 추천 포인트
					$point['limit_date']		= get_point_limitdate('joiner');
					$point['member_seq_to']		= $chk[0]['member_seq'];//2015-02-16
					$this->CI->membermodel->point_insert($point, $memberseq);

					//'포인트 '.$app['pointJoiner'].'P'
					$common_msg['pointJoiner'] = getAlert('mb045',$app['pointJoiner']);
				}
			}
		}

		//초대시
		if($params['fb_invite']) {
			$chk = get_data("fm_member",array("member_seq"=>$params['fb_invite']));
			if($chk[0]['member_seq']) {

				$fbuserprofile = $this->CI->snssocial->facebooklogin();
				if($fbuserprofile['id']){
					$this->CI->db->where('sns_f', $fbuserprofile['id']);
					$result = $this->CI->db->update('fm_memberinvite', array("joinck"=>'1'));//가입여부 업데이트
				}

				//초대 한 자  -> 제한함
				$todaymonth = date("Y-m");
				if($app['emoneyInvited']>0) {
					$invitedtosc['whereis'] = ' and type = \'invite_from\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
					$invitedtosc['select']	 = ' count(*) as totalcnt, sum(emoney) as totalemoney ';
					$eminvitedtock = $this->CI->emoneymodel->get_data($invitedtosc);//추천한 회원 마일리지 지급여부
					$maxinvited = ($app['emoneyLimit_invited']*$app['emoneyInvited']);

					if( $eminvitedtock['totalcnt'] <= $app['emoneyLimit_invited'] && $eminvitedtock['totalemoney'] <= $maxinvited ) {
						unset($emoney);
						$emoney['type']				= 'invite_from';
						$emoney['emoney']			= $app['emoneyInvited'];
						$emoney['gb']				= 'plus';
						$emoney['memo']				= '초대 마일리지';
						$emoney['memo_lang']		= $this->CI->membermodel->make_json_for_getAlert("mp275"); // 초대 마일리지
						$emoney['limit_date']		= get_emoney_limitdate('invite_from');
						$emoney['member_seq_to']	= $memberseq;//2015-02-16
						$this->CI->membermodel->emoney_insert($emoney, $chk[0]['member_seq']);
					}
				}
				if($app['pointInvited']>0){
					$invitedtosc['whereis'] = ' and type = \'invite_from\' and gb = \'plus\' and member_seq = \''.$chk[0]['member_seq'].'\'  and regist_date between \''.$todaymonth.'-01 00:00:00\' and \''.$todaymonth.'-31 23:59:59\' ';//
					$invitedtosc['select']	 = ' count(*) as totalcnt, sum(point) as totalpoint ';
					$pminvitedtock = $this->CI->pointmodel->get_data($invitedtosc);//추천한 회원 마일리지 지급여부
					$maxinvited = ($app['pointLimit_invited']*$app['pointInvited']);

					if( $pminvitedtock['totalcnt'] <= $app['pointLimit_invited'] && $pminvitedtock['totalpoint'] <= $maxinvited ) {
						unset($point);
						$point['type']				= 'invite_from';
						$point['point']				= $app['pointInvited'];
						$point['gb']				= 'plus';
						$point['memo']				= '초대 포인트';
						$point['memo_lang']			= $this->CI->membermodel->make_json_for_getAlert("mp276"); // 초대 포인트
						$point['limit_date']		= get_point_limitdate('invite_from');
						$point['member_seq_to']		= $memberseq;//2015-02-16
						$this->CI->membermodel->point_insert($point, $chk[0]['member_seq']);
					}
				}

				//초대 받은 자(가입자)
				if($app['emoneyInvitees']>0){
					$emoney['type']			= 'invite_to';
					$emoney['emoney']		= $app['emoneyInvitees'];
					$emoney['gb']			= 'plus';
					$emoney['memo']			= '초대 회원 마일리지';
					$emoney['memo_lang']		= $this->CI->membermodel->make_json_for_getAlert("mp277"); // 초대 회원 마일리지
					$emoney['limit_date']           = get_emoney_limitdate('invite_to');
					$emoney['member_seq_to']	= $chk[0]['member_seq'];//2015-02-16
					$this->CI->membermodel->emoney_insert($emoney, $memberseq);

					//'마일리지 '.$app['emoneyInvitees'].'원'
					$common_msg['emoneyInvitees'] = getAlert('mb044',get_currency_price($app['emoneyInvitees'],3));
				}

				if($app['pointInvitees']>0){
					unset($point);
					$point['type']				= 'invite_to';
					$point['point']				= $app['pointInvitees'];
					$point['gb']				= 'plus';
					$point['memo']				= '초대 회원 포인트';
					$point['memo_lang']			= $this->CI->membermodel->make_json_for_getAlert("mp278"); // 초대 회원 포인트
					$point['limit_date']		= get_point_limitdate('invite_to');
					$point['member_seq_to']		= $chk[0]['member_seq'];//2015-02-16
					$this->CI->membermodel->point_insert($point, $memberseq);

					//'포인트 '.$app['emoneyInvitees'].'P'
					$common_msg['pointInvitees'] = getAlert('mb045',$app['emoneyInvitees']);
				}
			}
		}

		return $common_msg;
	}
	/**
	 * 회원 가입 대기
	 * @param type $params
	 */
	protected function join_hold($params){
		$memberseq = $params['member_seq'];

		###
		$app = config_load('member');
		$result = false;

		if(($params['mtype'] != 'business' && $app['autoApproval']=='Y')
			|| ($params['mtype'] == 'business' && $app['autoApproval_biz']=='Y')) {//자동승인인 경우
		}else{
			// 회원 가입 대기 처리
			$this->CI->membermodel->update_status_hold($memberseq);
			$result = true;
		}
		return $result;
	}

	/**
	 * 신규가입 쿠폰 발급
	 * @param type $params
	 * @return string : 쿠폰 발급 안내 문구
	 */
	protected function join_coupon($params) {
	    $memberseq = $params['member_seq'];
	    $coupon_msg = null;

	    //신규회원가입쿠폰발급
	    $sc = array(
	        'whereis' => ' and (type="member" or type="member_shipping")  and issue_stop != 1 ' //발급중지가 아닌경우
	    );
	    $coupon_multi_list = $this->CI->couponmodel->get_coupon_multi_list($sc);
	    $coupon_multicnt = 0;
	    foreach($coupon_multi_list as $coupon_multi){
            $coupon_multicnt++;
            $this->CI->couponmodel->_members_downlod( $coupon_multi['coupon_seq'], $memberseq);
	    }
	    //회원가입 쿠폰이 발행 되었습니다.
	    if($coupon_multicnt > 0) {
	        $coupon_msg =getAlert('mb046');
	    }

	    return $coupon_msg;
	}

	/**
	 * 회원 개인정보 암호화
	 * @param type $params
	 */
	public function private_encrypt($memberseq, $params = []){
		if(!empty($memberseq)){
			$this->CI->membermodel->update_private_encrypt($memberseq, $params);
		}
	}
	/**
	 * 회원 방문일 처리
	 * @param type $member_seq
	 * @param type $lastlogin_spot_name
	 */
	public function make_login_history($member_seq, $lastlogin_spot_name=null, $params=array()){
		$result = null;
		if(!empty($member_seq)){
			### LOG
			$qry = "update fm_member set login_cnt = login_cnt+1, lastlogin_date = now(),lastlogin_spot_name = ?, login_addr = ? where member_seq = ? ";
			$result = $this->CI->db->query($qry, array($lastlogin_spot_name, $_SERVER['REMOTE_ADDR'], $member_seq));
		}
		return $result;
	}
	/**
	 * 회원 탈퇴
	 * @param type $params
	 */
	public function set_withdrawal($params, $member_arr=array()){
		if($member_arr){
			foreach($member_arr as $k){
				$params['member_seq']	= $k;
				$result = $this->CI->membermodel->set_withdrawal_admin($params);
			}
		} else {
			$result = $this->CI->membermodel->set_withdrawal_admin($params);
		}

		if($result) {
			$common_msg['result'] = true;
			$common_msg['msg'] = "탈퇴 처리 되었습니다.";
		} else {
			$common_msg['result'] = false;
			$common_msg['msg'] = "이미 탈퇴 처리한 회원입니다.";
		}

		return $common_msg;
	}

	/**
	 * 비밀번호 유효성 체크
	 * 
	 * 1. 영문 대문자 (26개) / 영문 소문자 (26개) / 숫자 (10개) / 특수문자 (26개) 
	 *	! # $ % & ( ) * + - / : = > ? @ [ ＼ ] ^ _ { | } ~ 중 2가지 이상의 조합 10~20자리 또는
	 *	! # $ % & ( ) * + - / : = > ? @ [ ＼ ] ^ _ { | } ~ 중 3가지 이상의 조합 8~20자리
	 * 
	 * 2. abc,123  같은 연속된 영문,숫자,특수문자 3자 이상 사용 불가
	 *	비고) 연속되는 숫자 0,1,2 포함
	 *
	 * 3. aaa,111 같은 동일한 영문, 숫자,특수문자 3자 이상 사용 불가
	 *
	 * 4. 회원의 생년월일 사용 불가
	 *	비고) 년도 네자리/두자리+월일 또는 월일 제공 불가 예.19910818 , 910818, 0818
	 *
	 * 5. 회원의 전화번호 사용 불가
	 *	비고) 전화번호에 연속되는 번호가 4자 이상 제공 불가 예.010-1234-5678 에서 3456 사용불가
	 *
	 * 6. 키보드 상 나란히 있는 문자열 사용 불가
	 *	비고) 키보드에 나열된 기준으로 적용 예. opqw 해당 없음, op[] 해당됨
	 *
	 * 7. 잘 알려진 단어 사용 불가
	 *	비고) love, happy, password, test, admin 사용 불가
	 *
	 * 8. 비밀번호 변경 시, 이전과 동일한 비밀번호 사용 제한
	 *	비고) 대소문자 구분 없음 예. Mrp15@*1aT 를 Mrp15@*1at 변경 불가
	 *
	 */
	public function check_password_validation($password, $params=array(), $init_validation=array()){
		$result_code 			= '00';	// 00 : 정상, 99 : 미입력, 01~08 : 유효성 에러
		$upper_password 		= strtoupper($password);
		$min_length 			= 10;
		$max_length 			= 20;
		$need_mix_count 		= 2;
		$need_mix_word_list 	= array(
			'[A-Z]',
			'[a-z]',
			'[0-9]',
			'[!#$%&\(\)\*\+\-\/:=>?@\[\\\\\]^_\{\|\}~]',	// ! # $ % & ( ) * + - / : = > ? @ [ ＼ ] ^ _ { | } ~
		);
		$continue_word			= array(
			'01234567890',
			'ABCDEFGHIJKLMNOPQRSTUVWXYZ',	// 대소문자 구별 없음
			'09876543210',					// 역순
			'ZYXWVUTSRQPONMLKJIHGFEDCBA',	// 역순
		);
		$continue_word_len 		= 3;
		
		$same_word 				= array(
			'0123456789',
			'ABCDEFGHIJKLMNOPQRSTUVWXYZ',	// 대소문자 구별 없음
		);
		$same_word_len 			= 3;
		
		$arr_birthday 			= array();
		$arr_phone 				= array();
		$arr_cellphone 			= array();
		
		$phone_word_len 		= 4;
		$cellphone_word_len 	= 4;
		
		$sep = "[[";	//
		$keyboard_word = array(
			'\`\~'.$sep.'1\!'.$sep.'2\@'.$sep.'3\#'.$sep.'4\$'.$sep.'5\%'.$sep.'6\^'.$sep.'7\&'.$sep.'8\*'.$sep.'9\('.$sep.'0\)'.$sep.'\-\_'.$sep.'\=\+',
			'Q'.$sep.'W'.$sep.'E'.$sep.'R'.$sep.'T'.$sep.'Y'.$sep.'U'.$sep.'I'.$sep.'O'.$sep.'P'.$sep.'\[\{'.$sep.'\]\}'.$sep.'\\\\\|',
			'A'.$sep.'S'.$sep.'D'.$sep.'F'.$sep.'G'.$sep.'H'.$sep.'J'.$sep.'K'.$sep.'L'.$sep.'\;\;'.$sep.'\'\"',
			'Z'.$sep.'X'.$sep.'C'.$sep.'V'.$sep.'B'.$sep.'N'.$sep.'M'.$sep.'\,\<'.$sep.'\.\>'.$sep.'\/\?',
		);
		$keyboard_word_len = 3;

		$know_word = array(
			'LOVE',
			'HAPPY',
			'PASSWORD',
			'TEST',
			'ADMIN',
		);

		$pre_enc_password = '';
		$enc_password = '';

		// 기본 제약 조건 갱신
		if($init_validation['min_length']){
			$min_length = $init_validation['min_length'];
		}
		if($init_validation['max_length']){
			$max_length = $init_validation['max_length'];
		}
		if($init_validation['need_mix_count']){
			$need_mix_count = $init_validation['need_mix_count'];
		}
		if($init_validation['need_mix_word_list']){
			$need_mix_word_list = $init_validation['need_mix_word_list'];
		}
		if($init_validation['sequence_word'] && $init_validation['sequence_word_len']){
			$continue_word = $init_validation['sequence_word'];
			$continue_word_len = $init_validation['sequence_word_len'];
		}
		if($init_validation['same_word'] && $init_validation['same_word_len']){
			$same_word = $init_validation['same_word'];
			$same_word_len = $init_validation['same_word_len'];
		}
		if($init_validation['phone_word_len']){
			$phone_word_len = $init_validation['phone_word_len'];
		}
		if($init_validation['cellphone_word_len']){
			$cellphone_word_len = $init_validation['cellphone_word_len'];
		}
		if($init_validation['same_word'] && $init_validation['same_word_len']){
			$same_word = $init_validation['same_word'];
			$same_word_len = $init_validation['same_word_len'];
		}
		if($init_validation['keyboard_word'] && $init_validation['keyboard_word_len']){
			$keyboard_word = $init_validation['keyboard_word'];
			$keyboard_word_len = $init_validation['keyboard_word_len'];
		}
		if($init_validation['know_word']){
			$know_word = $init_validation['know_word'];
		}

		// 추가 검증 파라미터 입력
		if($params['birthday']){
			if(is_array($params['birthday'])){
				$arr_birthday = $params['birthday'];
			}else{
				$arr_birthday[] = $params['birthday'];
			}
		}
		if($params['phone']){
			if(is_array($params['phone'])){
				$arr_phone = $params['phone'];
			}else{
				$arr_phone[] = $params['phone'];
			}
		}
		if($params['cellphone']){
			if(is_array($params['cellphone'])){
				$arr_cellphone = $params['cellphone'];
			}else{
				$arr_cellphone[] = $params['cellphone'];
			}
		}
		if($params['pre_enc_password'] && $params['enc_password']){
			$pre_enc_password = $params['pre_enc_password'];
			$enc_password = $params['enc_password'];
		}

		// 비밀번호 입력 여부 확인
		if($result_code == '00'){
			if(empty(trim($password))){
				$result_code = '99';
			}
		}

		// 1. 영문 대문자 (26개) / 영문 소문자 (26개) / 숫자 (10개) / 특수문자 (26개)
		if($result_code == '00'){
			
			// 혼합사용 체크
			$mix_check = 0;
			foreach($need_mix_word_list as $preg_word){
				if(preg_match("/".$preg_word."/", $password)){
					$mix_check += 1;
				}
			}

			if($mix_check == 2) {			// 2가지 혼합시 10자리 이상
				$min_length = 10;
			}else if($mix_check >= 3) {		// 3가지 이상 혼합 시 8자리 이상
				$min_length = 8;
			}else{							// 그외 오류
				$result_code = '01';
			}
			// 자릿수 체크
			// 20210531 (kjw) : validation instance 를 공유함에 따른 이슈로 password 검증은 validation class instance 를 새로 할당하여 검증 후 unset 처리
			$this->CI->load->library('validation','','check_password_validation');
			$_POST['check_password_validation'] = $password;
			$this->CI->check_password_validation->set_rules('check_password_validation', '01','trim|required|min_length['.$min_length.']|max_length['.$max_length.']|xss_clean');
			if($this->CI->check_password_validation->exec()===false){
				$result_code = '01';
			}
			unset($this->CI->check_password_validation);
			unset($_POST['check_password_validation']);
			
		}

		// 2. abc,123  같은 연속된 영문,숫자,특수문자 3자 이상 사용 불가
		if($result_code == '00'){
			$start = 0;
			foreach($continue_word as $check_word){
				for($i=$start;$i<=strlen($check_word)-$continue_word_len;$i++){
					$preg_word = substr($check_word, $start+$i, $continue_word_len);
					// 대문자로 체크
					if(preg_match("/".$preg_word."/", $upper_password)){
						$result_code = '02';
					}
				}
			}
		}

		// 3. aaa,111 같은 동일한 영문, 숫자,특수문자 3자 이상 사용 불가
		if($result_code == '00'){
			$start = 0;
			foreach($same_word as $check_word){
				for($i=$start;$i<strlen($check_word);$i++){
					$preg_word = substr($check_word, $start+$i, 1);
					// 대문자로 체크
					if(preg_match("/".$preg_word."{".$same_word_len.",}/", $upper_password)){
						$result_code = '03';
					}
				}
			}
		}

		// 4. 회원의 생년월일 사용 불가
		if($result_code == '00'){
			foreach($arr_birthday as $birthday){
				$preg_word = preg_replace('/[\.\-: 년월일]/', "", $birthday);	// 일반적으로 사용되는 생년월일의 특수문자 제거
				// 생년월일과 일치하는지
				if(trim($preg_word)){
					if(preg_match("/".$preg_word."/", $upper_password)){
						$result_code = '04';
					}
				}
				// 생년월일 중 2자리년도와 일치하는지
				if(strlen($preg_word)==8){
					$preg_word = substr($preg_word, 2 ,6);
				}
				if(trim($preg_word)){
					if(preg_match("/".$preg_word."/", $upper_password)){
						$result_code = '04';
					}
				}
				// 생년월일 중 월일과 일치하는지
				if(strlen($preg_word)==6){
					$preg_word = substr($preg_word, 2 ,4);
				}
				if(trim($preg_word)){
					if(preg_match("/".$preg_word."/", $upper_password)){
						$result_code = '04';
					}
				}
			}
		}

		// 5. 회원의 전화번호 사용 불가
		if($result_code == '00'){
			$start = 0;
			foreach($arr_phone as $check_word){
				$check_word = preg_replace('/[\.\- ]/', "", $check_word);	// 일반적으로 사용되는 전화번호의 특수문자 제거
				for($i=$start;$i<=strlen($check_word)-$phone_word_len;$i++){
					$preg_word = substr($check_word, $start+$i, $phone_word_len);
					// 대문자로 체크
					if($preg_word){
						if(preg_match("/".$preg_word."/", $upper_password)){
							$result_code = '05';
						}
					}
				}
			}
		}

		// 6. 회원의 휴대폰 번호 사용 불가
		if($result_code == '00'){
			$start = 0;
			foreach($arr_cellphone as $check_word){
				$check_word = preg_replace('/[\.\- ]/', "", $check_word);	// 일반적으로 사용되는 휴대폰 번호의 특수문자 제거
				for($i=$start;$i<=strlen($check_word)-$cellphone_word_len;$i++){
					$preg_word = substr($check_word, $start+$i, $cellphone_word_len);
					// 대문자로 체크
					if($preg_word){
						if(preg_match("/".$preg_word."/", $upper_password)){
							$result_code = '06';
						}
					}
				}
			}
		}

		// 7. 키보드 상 나란히 있는 문자열 사용 불가
		if($result_code == '00'){
			$start = 0;
			foreach($keyboard_word as $check_word){
				for($i=$start;$i<=strlen($check_word)-$keyboard_word_len;$i++){
					$preg_word = '';

					$tmp = explode($sep, $check_word);
					for($j=0;$j<count($tmp)-2;$j++){
						$preg_word = '['.$tmp[$j].']['.$tmp[$j+1].']['.$tmp[$j+2].']';
						// 대문자로 체크
						if(preg_match("/".$preg_word."/", $upper_password)){
							$result_code = '07';
						}

						// 역순 체크
						$preg_word = '['.$tmp[$j+2].']['.$tmp[$j+1].']['.$tmp[$j].']';
						// 대문자로 체크
						if(preg_match("/".$preg_word."/", $upper_password)){
							$result_code = '07';
						}
					}
				}
			}
		}


		// 8. 잘 알려진 단어 사용 불가
		if($result_code == '00'){
			foreach($know_word as $preg_word){
				// 대문자로 체크
				if(preg_match("/".$preg_word."/", $upper_password)){
					$result_code = '08';
				}
			}
		}

		// 9. 비밀번호 변경 시, 이전과 동일한 비밀번호 사용 제한
		if($result_code == '00'){
			if($pre_enc_password && $enc_password && $pre_enc_password == $enc_password){
				$result_code = '09';
			}
		}

		$result = array(
			'code' => $result_code,
			'alert_code' => $this->PASSWORD_ALERT_CODE[$result_code],
		);
		return $result;
	}

	/* --------------------------------------------------------------------------------------------------------- */
	// 만 14세 동의 체크 :: 2020.06.16 sms
	// 관련 skin patch 유무 체크 2021/05/26 by pjm
	function kidAgreeCheck($param){
		$joinform = ($this->CI->joinform)?$this->CI->joinform:config_load('joinform');
		$auth 		= $this->CI->session->userdata('auth');
		if($auth){
			$param['kid_agree'] = 'Y';
		}

		if($param['skin_patch_14years_old'] === "false") $param['skin_patch_14years_old'] = "";

		// mtype(business 기업, member 일반)
		// 만 14세미만 스킨 패치가 완료(skin_patch_14years_old = true) 되었을 때에만 session 생성.
		if($param['mtype'] == 'member' && ($joinform['kid_join_use'] == 'Y' || $joinform['kid_join_use'] == 'N' ) && $param['skin_patch_14years_old']){
			$kid_auth			= ($param['kid_agree'] != 'Y')? 'N':'Y';
			$kid_agree_check	= ($param['kid_agree'] != 'Y')? 'N':'Y';
			$this->CI->session->set_userdata('kid_auth', $kid_auth);
			$this->CI->session->set_userdata('kid_agree_check', $kid_agree_check);
		}else{
			$this->CI->session->unset_userdata('kid_auth');
			$this->CI->session->unset_userdata('kid_agree_check');
		}

		// 14세 관련 skin patch 유무 true:패치완료, false:미패치
		$this->CI->session->set_userdata('skin_patch_14years_old', $param['skin_patch_14years_old']);
	}

	/**
	 * 회원 휴면 해제처리
	 *
	 * @param memberSeq 회원 고유번호
	 * @param dormancyType 휴면계정 해제방법
	 * 			auto : 자동
	 * 			email : 이메일 인증
	 * 			namecheck : 본인인증
	 */
	function inactiveDormant($memberSeq, $dormancyType) {

		// 휴면처리 결과
		$result = true;
		$msg = getAlert('mb136');

		// 자동 휴면해제처리 가능여부
		$isDormancyChecked = true;

		// 휴면처리 방법에 따라 인증 절차를 진행, 기본값이 자동처리이나 아래 조건에서 변경 될 수 있음
		switch($dormancyType){
			case 'email':
				// 휴면회원 정보 조회
				$dormancyMember = $this->CI->membermodel->get_dormancy($memberSeq);

				// 이메일이 있으면 인증 이메일 발송
				if ($dormancyMember['email_real']) {
					// 아이디를 알아보지 못하게 변환
					$dormancyMember['dormancy_userid'] = implode(strForASCII($dormancyMember['userid'],'enc'), 'l');

					// 인증 이메일 발송
					sendMail($dormancyMember['email_real'], 'dormancy', $dormancyMember['userid'], $dormancyMember);

					// 가입한 이메일<br> '.$dormancyMember['email_real'].' 으로<br>인증 메일이 발송되었습니다<br> 인증하신 후에 정상적으로 쇼핑몰을 이용할 수 있습니다.
					$msg = getAlert('mb228');
					$isDormancyChecked = false;
				}
				break;
			case 'namecheck':

				$realname = config_load('realname');
				$auth = $this->CI->session->userdata('auth');

				// 본인인증을 사용하지 않으면 case 종료 후 아래에서 자동 해제처리
				if ($realname['useRealnamephone_dormancy'] != 'Y' && $realname['useIpin_dormancy'] != 'Y') break;

				// 로그인 페이지 URL
				$url = '/member/login';

				// 인증이 안되어있는 경우
				if ($auth['auth_yn'] != 'Y') {
					$url = '/member/dormancy_auth?dormancy_seq='.$memberSeq;
					$this->CI->session->sess_expiration = (60 * 5);
					$this->CI->session->set_userdata(['auth_dormancy' => ['auth_dormancy_type' => 'auth', 'auth_dormancy_yn' => 'N']]);
				}

				// 본인 인증페이지로 이동합니다
				pageRedirect($url, getAlert('mb135'));
				exit;
				break;
			case 'auto':
			default:
				break;
		}

		// 휴면 해제 처리가 가능한 경우 해제처리 진행
		if ($isDormancyChecked) {
			$this->CI->membermodel->dormancy_off($memberSeq);
		}

		return [
			'result' => $result,
			'msg' => $msg,
		];
	}

	/**
	 * 휴대폰 번호 인증하기
	 */
	function send_certify_cellphone($cellphone) {
		$sendresult = false;
		$memberConfig = ($this->CI->memberConfig)?$this->CI->memberConfig:config_load('member');
		$memberConfig['confirmsendmsg'] = ($memberConfig['confirmsendmsg'])?$memberConfig['confirmsendmsg']:"{shopname} 인증번호는 {phonecertify} 입니다.";

		$phonecertify = rand(10000,99999);
		$sendMsg	= str_replace("{shopname}", $this->CI->config_basic['shopName'], $memberConfig['confirmsendmsg']);
		$sendMsg	= str_replace("{phonecertify}", $phonecertify, $sendMsg);

		$params['msg'] = trim($sendMsg);
		$commonSmsData['member']['phone'] = $cellphone;
		$commonSmsData['member']['params'] = $params;

		$result = commonSendSMS($commonSmsData);
		if($result['code'] == '0000'){
			// 인증번호 세션
			$certify_cellphone = array('phonecertify'=>$phonecertify,'cellphone'=>$cellphone);
			$this->CI->session->sess_expiration = (60 * 3);
			$this->CI->session->set_userdata('certify_cellphone',$certify_cellphone);

			//발송되었습니다. 3분이내 입력하시기바랍니다.
			$msg = getAlert('mb068');
			$sendresult = true;
		}else{
			//발송에 실패하였습니다. 새로고침 후 시도해주세요.
			$msg = getAlert('mb069');
		}

		return ['result' => $sendresult, 'msg' => $msg];
	}
	
	/**
	 * 휴대폰 번호 인증 검사
	 */
	function certify_confirm($data) {
		$certify_cellphone = $this->CI->session->userdata('certify_cellphone');
		if(!$certify_cellphone['phonecertify']){
			return ['result'=>false, 'msg'=>getAlert('mb070')];
		}

		// 인증mode 별로 체크 필요
		$result = $this->CI->memberlibrary->pre_certify_confirm($data['mode'], $data);

		if($result['result'] === false) {
			return $result;
		}

		// session 과 post 의 phonecertify 값 일치여부 확인
		if($certify_cellphone['phonecertify'] != $data['phonecertify']) {
			return ['result' => false, 'msg' => getAlert('mb073')];
		}
		$this->CI->memberlibrary->post_certify_confirm($data['mode'], $data);
		$this->CI->memberlibrary->unset_certify_cellphone();
		return ['result' => true];
	}

	/**
	 * 발급된 인증번호 초기화
	 */
	function unset_certify_cellphone() {
		$this->CI->session->unset_userdata('certify_cellphone');
	}

	/**
	 * 휴대폰 번호 인증 시도 시 분기처리
	 * mode === present_delivery : 선물 수신자 번호 인증
	 */
	function pre_certify_confirm($mode,$data) {
		$certify_cellphone = $this->CI->session->userdata('certify_cellphone');

		switch ($mode) {
			case 'present_delivery' :
				if($certify_cellphone['cellphone'] != str_replace('-','',$data['present_receive'])) {
					return ['result' => false, 'msg'=> '선물 수신자만 인증이 가능합니다.'];
				}
			break;
		}
		return ['result'=>true];
	}

	/**
	 * 휴대폰 번호 인증 성공 시 분기처리
	 * mode === present_delivery : 선물 수신자 번호 인증
	 */
	function post_certify_confirm($mode,$data) {
		switch($mode) {
			case 'present_delivery' :
				// 선물하기 성공 시 present_delivery set session order_seq=>present_receive(=recipient_cellphone)
				$this->CI->session->set_userdata('present_delivery',[$data['order_seq']=>$data['present_receive']]);
			break;
		}
	}

	/**
	 * 마이페이지 주문 조회 시 공통으로 치환
	 */
	function replace_mypage_order ($orders) {
		if($orders['recipient_zipcode']) $orders['recipient_new_zipcode'] 	= str_replace("-","",$orders['recipient_zipcode']);
		if($orders['recipient_zipcode']) $orders['recipient_zipcode'] 	= explode('-',$orders['recipient_zipcode']);
		if($orders['recipient_zipcode']) $orders['merge_recipient_zipcode']	= implode('-', $orders['recipient_zipcode']);

		if($orders['recipient_phone']) $orders['recipient_phone'] 	= explode('-',$orders['recipient_phone']);
		if($orders['recipient_phone']) $orders['merge_recipient_phone']	= implode('-', $orders['recipient_phone']);

		if($orders['recipient_cellphone']) $orders['recipient_cellphone'] 	= explode('-',$orders['recipient_cellphone']);
		if($orders['recipient_cellphone']) $orders['merge_recipient_cellphone']	= implode('-', $orders['recipient_cellphone']);

		return $orders;
	}
	
	/**
	 * 최근본상품 회원테이블 업데이트
	 */

	function today_view_member($member_seq, $data)
	{
		$total = 0;
		$result = [];
		$today_view = unserialize($data['today_view'], ['allowed_classes' => false]);

		if ($today_view) {
			// krsort
			krsort($today_view);
			// 실제 있는 상품만 조회
			$this->CI->load->model('goodsmodel');
			$result = $this->CI->goodsmodel->get_goods_list($today_view, 'thumbScroll');

			// result 에 추출된 상품만 넣기
			foreach ($result as $key => $row) {
				$today_view_data[] = $row['goods_seq'];
			}

			//오늘본 상품 페이징
			if ($data['page'] && $data['limit']) {
				$start = ($data['page'] - 1) * $data['limit'];
				if ($data['limit']) {
					$result = array_slice($result, $start, $data['limit']);
				}
			}

			// 회원인 경우
			if ($member_seq) {
				$upSql = 'update fm_member set today_view = ?, today_cnt = ? where member_seq = ? ';
				$this->CI->db->query($upSql, [json_encode($today_view_data), count($today_view_data), $member_seq]);
			}
		}

		return ['total' => count($result), 'result' => $result];
	}

}
?>