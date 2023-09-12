<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Snssocial
{
	function __construct() {
		$this->ci =& get_instance();

		$this->arrSystem	= ($this->ci->config_system)?$this->ci->config_system:config_load('system');
		$this->fammerceplusUrl			= 'http://fammerce.firstmall.kr';//page연동을위해
		$this->fammerceplusUrlPath	= '/fammerce-plus-page-connect_new.php';//page연동을위해->자체앱전용추가

		//publish_stream, -> feed 등록시 필요로 skin 상에서처리 //@2015-04-28
		$this->userauth		= ($this->ci->userauth)?$this->ci->userauth:'email,publish_actions';
		$this->adminauth	= ($this->ci->adminauth)?$this->ci->adminauth:'email,manage_pages, publish_actions';
		$this->mefields		= 'id,email,name,first_name,last_name,gender,birthday,picture';//facebook info fields

		$this->snstype = array('sns_f', 'sns_t', 'sns_n', 'sns_k', 'sns_i', 'sns_a');//, 'sns_y' 2013-05-03 요즘서비스종료

		require_once APPPATH.'/libraries/social/facebook/facebook.php';
		$this->arrSns	= ($this->ci->arrSns)?$this->ci->arrSns:config_load('snssocial');
		$this->__APP_USE__				= $this->arrSns['fb_use'];
		$this->__APP_ID__				= $this->arrSns['key_f'];
		$this->__APP_SECRET__		= $this->arrSns['secret_f'];
		$this->__APP_PAGE__			= $this->arrSns['page_id_f'];
		if( !__FB_APP_VER__ ) {
			/**
			** facebook grapy api upgrade version
			**  array("20150430"=>'1.0',"20160807"=>'2.0',"20161030"=>'2.1',"20170325"=>'2.2');
			**  array("20170325"=>'2.2',"20170708"=>'2.3',"20171007"=>'2.4',"20180412"=>'2.5',"20180713"=>'2.6',"20181005"=>'2.7');
			** @2015-04-21->@2017-03-30
			** array("20181023"=>'3.2');
			**/
			if(!$this->fb_available_until)
				$this->fb_available_until = array("20210223"=>'10.0');
			foreach($this->fb_available_until as $fbdate=>$fbver) {
				if( $fbdate >= date("Ymd") ) {
					$fbversion = $fbver;
					break;
				}
				$fbversion = $fbver;//윗단에서 미체크시 마지막버젼이 자동적용
			}

			if( $this->arrSns['key_f'] != '455616624457601' ) {//전용앱
				$this->__APP_VER__				= (isset($this->arrSns['fb_ver']))?$this->arrSns['fb_ver']:$fbversion;//버전 기본앱 1.0, 전용앱 2014-04-30 이후 2.0
			}else{
				$this->__APP_VER__ =$fbversion;
			}
			define('__FB_APP_VER__',$this->__APP_VER__);
		}

		if($_GET['facebook']=='Y' || $_GET['signed_request'] || ($_COOKIE['fammercemode'] && $_COOKIE['fammercemode'] !='deleted')){
			$this->fbuser = $this->facebookuserid();
			if ( !$this->fbuser ) {
				$this->facebook = new Facebook(array(
				  'appId'  => $this->__APP_ID__,
				  'secret' => $this->__APP_SECRET__,
				  "cookie" => true
				));
				// Get User ID
				$this->fbuser = $this->facebook->getUser();
				if($this->fbuser && !$this->ci->session->userdata('fbuser')){
					$this->ci->session->set_userdata('fbuser', $this->fbuser);
				}else{
					$this->fbuser = $this->facebooklogin();
					if( $this->fbuser && !$this->ci->session->userdata('fbuser') ) {
						$this->ci->session->set_userdata('fbuser', $this->fbuser);
					}
				}
				if($this->ci->session->userdata('fbuser')) {
					$_SESSION['fbuser'] = $this->ci->session->userdata('fbuser');
				}
			}else{
				if( !$this->ci->session->userdata('fbuser') ) {
					$this->ci->session->set_userdata('fbuser', $this->fbuser);
				}
			}

			if($_GET['signed_request']){
				setcookie('fammercemode', $_GET['signed_request'], 0, '/');
			}elseif($_GET['facebook-page']){
				setcookie('fammercemode', $_GET['facebook'], 0, '/');
			}else{
				setcookie('fammercemode', $_COOKIE['fammercemode'], 0, '/');
			}
		}else{
			setcookie('fammercemode', '', 0, '/');
		}
		if($_COOKIE['fammercemode'] && $_COOKIE['fammercemode'] !='deleted'){//main iframe 접근시
			$this->ci->template->assign('fammercemode', true);
		}

		// Create our Application instance (replace this with your appId and secret).
		$this->facebook = new Facebook(array(
		  'appId'  => $this->__APP_ID__,
		  'secret' => $this->__APP_SECRET__,
		  "cookie" => true
		));//
	}

	//
	function facebookuserid() {
		$this->facebook = new Facebook(array(
		  'appId'  => $this->__APP_ID__,
		  'secret' => $this->__APP_SECRET__,
		  "cookie" => true
		));//"cookie" => true
		// Get User ID

		// Get User ID
		$this->fbuser = $this->facebook->getUser();
		if( $this->ci->session->userdata('fbuser') < 1 ) {
			$this->ci->session->set_userdata('fbuser', $this->fbuser);
		}
		if($this->ci->session->userdata('fbuser')) {
			$_SESSION['fbuser'] = $this->ci->session->userdata('fbuser');
		}
		return $this->fbuser;
	}

	function facebooklogin($ftype=null) {
		$this->fbuser_profile = null;

		// Create our Application instance (replace this with your appId and secret).
		if($_GET['code']){
			$access_tokenuri = $this->get_token($this->__APP_ID__, $this->__APP_SECRET__,$_GET['code'],$ftype);
			$accessarar0 = explode("&",str_replace("\"","",$access_tokenuri));
			$accessarar = explode("=",str_replace("\"","",$accessarar0[0]));
			$access_token = $accessarar[1];
			$this->ci->session->set_userdata('access_token', $access_token);
		}

		$this->facebook = new Facebook(array(
		  'appId'  => $this->__APP_ID__,
		  'secret' => $this->__APP_SECRET__,
		  "cookie" => true
		));//"cookie" => true

		if($this->ci->session->userdata('fbuser') && $this->facebook->getUser() ) {
			$this->fbuser_profile = $this->facebook->api($this->ci->session->userdata('fbuser')); // 유저 프로필을 가져 옵니다.
			if($this->ci->session->userdata('fbuser')) $this->fbuser				= $this->ci->session->userdata('fbuser');
			if($this->ci->session->userdata('accesstoken')) $this->accesstoken		= $this->ci->session->userdata('accesstoken');
			if($this->ci->session->userdata('signedrequest'))  $this->signedrequest		= $this->ci->session->userdata('signedrequest');
			if(!$this->ci->session->userdata('user_accesstoken'))  {
					$token = $this->facebook->getAccessToken();
					$tokenar = @explode("|",$token);
					$token = ($tokenar[1])?$tokenar[1]:$tokenar[0];
					$this->ci->session->set_userdata('user_accesstoken', $token);
			}
		}else{

			// Get User ID
			$this->fbuser = $this->facebook->getUser();

			if ($this->fbuser) {
				try {
					$_SESSION['fbuser'] = $this->fbuser;
					$getaccesstoken = $this->facebook->getAccessToken();
					$tokenar = @explode("|",$getaccesstoken);
					$token = ($tokenar[1])?$tokenar[1]:$tokenar[0];

					$this->fbuser_profile = $this->facebook->api($this->fbuser.'?fields='.$this->mefields); // 유저 프로필을 가져 옵니다.

					if(!$this->fbuser_profile) {
						$this->fbuser_profile = $this->facebook->api($this->fbuser.'?fields='.$this->mefields,'GET',array('access_token'=>$token)); // 유저 프로필을 가져 옵니다.
					}

					$this->signedrequest = $this->facebook->getSignedRequest();
					if($token){
						$fb_accounts = $this->facebook->api($this->fbuser.'/accounts','GET',array('access_token'=>$token));
					}else{
						$fb_accounts = $this->facebook->api($this->fbuser.'/accounts');
					}
					$this->accesstoken = ($fb_accounts['data'][1]['access_token'])?$fb_accounts['data'][1]['access_token']:$token;

					$newdata = array(
										'fbuser'  => $this->fbuser,
										'user_accesstoken'     => $token,
										'signedrequest'     => $this->signedrequest,
										'accesstoken' => $this->accesstoken
									);
					$this->ci->session->set_userdata($newdata);


				} catch (FacebookApiException $e) {
					$this->fbuser = null;
					$unsetuserdata = array('fbuser'=>'','accesstoken'=>'','signedrequest'=>'');
					$this->ci->session->unset_userdata($unsetuserdata);
				}
			}else{
				$this->fbuser = null;
				$unsetuserdata = array('fbuser'=>'','accesstoken'=>'','signedrequest'=>'');
				$this->ci->session->unset_userdata($unsetuserdata);
			}
		}

		return $this->fbuser_profile;
	}

	/**
	@ twitter api start
	------------------------------------------------------------
	**/
		/**
		@ twitter login
		**/
		function twitterloginurl($mtype, $mform = 'login', $facebooktype) {
			$http_protocol = $_SERVER['HTTPS'] ? 'https' : 'http';
			if($mform == 'join') {
				$this->arrSns['callbackurl_t'] = $http_protocol.'://'.($_SERVER['HTTP_HOST']).'/sns_process/twitterjoin?mtype='.$mtype.'&facebooktype='.$facebooktype;
			}else{
				$this->arrSns['callbackurl_t'] = $http_protocol.'://'.($_SERVER['HTTP_HOST']).'/sns_process/twitterlogin?mtype='.$mtype;
			}

			require_once(APPPATH.'/libraries/social/twitter/twitteroauth/twitteroauth.php');
			require_once(APPPATH.'/libraries/social/twitter/config.php');

			$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET);
			$request_token = $connection->getRequestToken(OAUTH_CALLBACK);/* Get temporary credentials. */

			switch ($connection->http_code) {
			  case 200:
				$url = $connection->getAuthorizeURL($request_token['oauth_token']);
				$_SESSION['oauth_token'] = $request_token['oauth_token'];
				$_SESSION['oauth_token_secret'] = $request_token['oauth_token_secret'];
				//$this->ci->session->set_userdata('oauth_token', $request_token['oauth_token']);
				//$this->ci->session->set_userdata('oauth_token_secret', $request_token['oauth_token_secret']);
				$authloginckurl	= $url;
				break;
			  default:
				$authloginckurl = null;
			  break;
			}

			return $authloginckurl;
		}


		function twitteraccount($oauth_verifier, $mtype, $mform = 'login',$facebooktype) {
			$this->twuser_profile = null;
			$http_protocol = $_SERVER['HTTPS'] ? 'https' : 'http';
			if($mform == 'join') {
				$this->arrSns['callbackurl_t'] = $http_protocol.'://'.($_SERVER['HTTP_HOST']).'/sns_process/twitterjoin?mtype='.$mtype.'&facebooktype='.$facebooktype;
			}else{
				$this->arrSns['callbackurl_t'] = $http_protocol.'://'.($_SERVER['HTTP_HOST']).'/sns_process/twitterlogin?mtype='.$mtype;
			}

			require_once(APPPATH.'/libraries/social/twitter/twitteroauth/twitteroauth.php');
			require_once(APPPATH.'/libraries/social/twitter/config.php');

			$connection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $_SESSION['oauth_token'], $_SESSION['oauth_token_secret']);
			/* Request access tokens from twitter */
			$access_token = $connection->getAccessToken($oauth_verifier);
			if($mform == 'join') {
				//$newconnection = new TwitterOAuth(CONSUMER_KEY, CONSUMER_SECRET, $access_token['oauth_token'], $access_token['oauth_token_secret']);
				//$this->twuser_profile = $newconnection->get('account/verify_credentials');//
				$this->twuser_profile['id']						= $access_token['user_id'];
				$this->twuser_profile['screen_name']		= $access_token['screen_name'];
			}else{
				$this->twuser_profile['id'] = $access_token['user_id'];
				$this->twuser_profile['screen_name'] = $access_token['screen_name'];
			}
			return $this->twuser_profile;
		}
	/**
	@ twitter api end
	------------------------------------------------------------
	**/


	/**
	@ me2day api start
	------------------------------------------------------------
	**/

		/**
		@ me2day login //me2dayURL(새창1)
		**/
		function me2dayloginurl($mtype, $mform = 'login') {
			$authloginckurl = null;

			$this->ci->load->library('SofeeXmlParser');
			$xmlParser = new SofeeXmlParser();

			$xmlParser->parseFile("http://me2day.net/api/get_auth_url.xml?akey=".$this->arrSns['key_m']);
			$tree = $xmlParser->getTree();
			if($tree['auth_token']){
				$authloginckurl = $tree['auth_token']['url']['value'];
				$this->ci->session->set_userdata('me2day_oauth_token', $tree['auth_token']['token']['value']);
			}
			return $authloginckurl;
		}

		//me2day계정 인증체크(callbackurl 시 새창2)
		function me2dayaccountck($mtype, $mform = 'login') {
			$this->m2user_profile = $person = null;

			$xmlFile = $this->get_me2day_tokenuser("http://me2day.net/api/get_person/".$_GET['user_id'],$_GET['user_id']);
			$this->ci->load->library('XMLParser');
			$xmlParsers = new XMLParser($xmlFile);
			$xmlParsers->Parse();
			$xmlparsesize = count($xmlParsers->document->tagChildren);
			for($i=0;$i<$xmlparsesize;$i++){
				$person[$xmlParsers->document->tagChildren[$i]->tagName] = $xmlParsers->document->tagChildren[$i]->tagData;
			}
			if(isset($_GET['token']) && isset($_GET['result']) && $_GET['result'] == true && $person) {//인증된경우
				$person['user_key'] = $_GET['user_key'];
				$this->m2user_profile		= $person;

				$authKey = "20120619" . md5("20120619" . $_GET['user_key']);
				$me2day_data = array('me2day_user_id'		=> $_GET['user_id'],'me2day_user_key'	=> $_GET['user_key']);
				$this->ci->session->set_userdata(array('m2user'=>$me2day_data));
				$this->get_me2day_tokenar("http://me2day.net/api/noop?uid=".$_GET['user_id']."&ukey=".$authKey."&akey=".$this->arrSns['key_m'],$_GET['user_id'],$authKey,$this->arrSns['key_m']);
			}else{
				$this->m2user_profile = null;
			}
			return $this->m2user_profile;
		}

		//계정정보 읽어오기(본래창)
		function me2dayaccount($mtype, $mform = 'login') {
			$m2user = $this->ci->session->userdata('m2user');
			$xmlFile = $this->get_me2day_tokenuser("http://me2day.net/api/get_person/".$m2user['me2day_user_id'],$m2user['me2day_user_id']);
			$this->ci->load->library('XMLParser');
			$xmlParsers = new XMLParser($xmlFile);
			$xmlParsers->Parse();
			$xmlparsesize = count($xmlParsers->document->tagChildren);
			for($i=0;$i<$xmlparsesize;$i++) {
				$person[$xmlParsers->document->tagChildren[$i]->tagName] = $xmlParsers->document->tagChildren[$i]->tagData;
			}

			if($m2user && $person) {//인증된경우
				$person['user_key'] = $m2user['me2day_user_key'];
				$this->m2user_profile		= $person;
			}else{
				$this->m2user_profile = null;
			}
			return $this->m2user_profile;
		}

		//me2day app token xmlurl read
		function get_me2day_token($url)
		{
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			$data = curl_exec($ch);
			return ($data);
		}

		//me2day app token xmlurl read
		function get_me2day_tokenuser($url,$userid=null)
		{
			$userid = ($userid)?$userid:$_GET['user_id'];
			$args = array('user_id' => $userid);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
			$data = curl_exec($ch);
			return ($data);
		}


		//me2day app token xmlurl read
		function get_me2day_tokenar($url,$uid, $ukey, $akey)
		{
			$args = array('uid' => $uid,'ukey' => $ukey,'akey' => $akey);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
			$data = curl_exec($ch);
			return ($data);
		}

	/**
	@ me2day api end
	------------------------------------------------------------
	**/

	/**
	@ yozm api start
	------------------------------------------------------------
	**/
		/**
		@ yozm login
		**/
		function yozmloginurl($mtype, $mform = 'login') {
			$this->arrSns['callbackurl_y'] = 'http://'.($_SERVER['HTTP_HOST']).'/sns_process/yozmuserck?mtype='.$mtype;

			require_once(APPPATH.'/libraries/social/daum/daumOAuth.php');
			$connection = new DaumOAuth($this->arrSns['key_y'], $this->arrSns['secret_y'], $this->arrSns['callbackurl_y']);
			$request_token = $connection->getRequestToken();

			if($request_token['oauth_callback_confirmed'] == true ) {
				$authloginckurl = $connection->getAuthorizeURL($request_token);
				$this->ci->session->set_userdata('request_token', $request_token);
			}else{
				$authloginckurl = null;
			}
			return $authloginckurl;
		}

		function yozmaccesstoken($mtype, $mform = 'login') {
			$this->arrSns['callbackurl_y'] = 'http://'.($_SERVER['HTTP_HOST']).'/sns_process/yozmuserck?mtype='.$mtype;
			require_once(APPPATH.'/libraries/social/daum/daumOAuth.php');

			$o_token = $_GET['oauth_token'];
			$o_verifier = $_GET['oauth_verifier'];
			if($o_token && $o_verifier){
				$rTok = $this->ci->session->userdata('request_token');
				$connection = new DaumOAuth($this->arrSns['key_y'], $this->arrSns['secret_y'], $this->arrSns['callbackurl_y'], $o_token, $rTok['oauth_token_secret']);
				$access_token = $connection->getAccessToken($o_verifier);
				$this->ci->session->set_userdata('yozm_request_token', $access_token);
				$this->ci->session->set_userdata('yozm_o_verifier', $o_verifier);
			}
			return $access_token;
		}

		function yozmaccount($mtype, $mform = 'login') {
			$this->ci->load->helper('readurl');

			$this->user_profile = null;
			$o_verifier = $this->ci->session->userdata('yozm_o_verifier');
			$access_token = $this->ci->session->userdata('yozm_request_token');
			$this->arrSns['callbackurl_y'] = 'http://'.($_SERVER['HTTP_HOST']).'/sns_process/yozmuserck?mtype='.$mtype;
			require_once(APPPATH.'/libraries/social/daum/daumOAuth.php');

			$newconnection = new DaumOAuth($this->arrSns['key_y'], $this->arrSns['secret_y'], $this->arrSns['callbackurl_y'], $access_token['oauth_token'], $access_token['oauth_token_secret']);

			//yozm 가입체크1
			$yozmjoinedxmlFile = $newconnection->OAuthRequest('https://apis.daum.net/yozm/v1_0/user/joined.xml', '', 'GET');
			$yozmjoinedreturn = xml2array($yozmjoinedxmlFile);
 			if($yozmjoinedreturn['info']['status'] == '200' && $yozmjoinedreturn['info']['joined'] != 'true' ) {//요청상태 성공과 미가입된 경우
				$this->user_profile['yozm_joined'] = true;
				return $this->user_profile;
 			}

			//yozm 사용자정보 가져오기
			$yozmuser = $newconnection->OAuthRequest('https://apis.daum.net/yozm/v1_0/user/show.xml', '', 'GET');
			$yozmarr = xml2array($yozmuser);
			if($yozmarr['info']['status'] == '200' ) {
				$this->user_profile = $yozmarr['info']['user'];
				$this->user_profile['id'] = $yozmarr['info']['user']['url_name'];//Daum ID
				$this->ci->session->set_userdata(array('yzuser'=>$this->user_profile['id']));
			}

			return $this->user_profile;
		}

	/**
	@ yozm api end
	------------------------------------------------------------
	**/

	/**
	@ cyworld api start
	------------------------------------------------------------
	**/
		/**
		@ cyworld login
		**/
		function cyworldloginurl($mtype, $mform = 'login') {
			$this->ci->load->helper('readurl');

			$this->arrSns['callbackurl_c'] = 'http://'.($_SERVER['HTTP_HOST']).'/sns_process/cyworlduserck?mtype='.$mtype;

			$oauth_signature_method = "HMAC-SHA1";
			$oauth_timestamp = time();
			$oauth_nonce = md5(microtime().mt_rand()); // md5s look nicer than numbers;
			$oauth_version = "1.0";

			$get_request_token_url = "https://oauth.nate.com/OAuth/GetRequestToken/V1a";

			//Get Request Token									----------------->>1
			//!!파라메터 이름 순서로 조합해야 한다.//!!파라메터의 이름과 값은 rfc3986 으로 encode//[Name=Valeu&Name=Value…] 형식으로 연결
			$Query_String  = urlencode_rfc3986("oauth_callback")."=".urlencode_rfc3986($this->arrSns['callbackurl_c']);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_consumer_key")."=".urlencode_rfc3986($this->arrSns['key_c']);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_nonce")."=".urlencode_rfc3986($oauth_nonce);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_signature_method")."=".urlencode_rfc3986($oauth_signature_method);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_timestamp")."=".urlencode_rfc3986($oauth_timestamp);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_version")."=".urlencode_rfc3986($oauth_version);

			//Base String 요소들을 rfc3986 으로 encode	------------------>>2
			$Base_String = urlencode_rfc3986("POST")."&".urlencode_rfc3986($get_request_token_url)."&".urlencode_rfc3986($Query_String);

			//지금 단계에서는 $oauth_token_secret이 ""	------------------->>3
			$Key_For_Signing = urlencode_rfc3986($this->arrSns['secret_c'])."&".urlencode_rfc3986($oauth_token_secret);

			//oauth_signature 생성									------------------->>4
			$oauth_signature=base64_encode(hash_hmac('sha1', $Base_String, $Key_For_Signing, true));

			//Authorization Header 조합							------------------->>5
			$Authorization_Header  = "Authorization: OAuth ";
			$Authorization_Header .= urlencode_rfc3986("oauth_version")."=\"".urlencode_rfc3986($oauth_version)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_nonce")."=\"".urlencode_rfc3986($oauth_nonce)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_timestamp")."=\"".urlencode_rfc3986($oauth_timestamp)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_consumer_key")."=\"".urlencode_rfc3986($this->arrSns['key_c'])."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_callback")."=\"".urlencode_rfc3986($this->arrSns['callbackurl_c'])."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_signature_method")."=\"".urlencode_rfc3986($oauth_signature_method)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_signature")."=\"".urlencode_rfc3986($oauth_signature)."\"";

			$parsed = parse_url($get_request_token_url);
			$scheme = $parsed["scheme"];
			$path = $parsed["path"];
			$ip = $parsed["host"];
			$port = @$parsed["port"];

			if ($scheme == "http")
			{
				if(!isset($parsed["port"])) { $port = "80"; } else { $port = $parsed["port"]; };
				$tip = $ip;
			} else if ($scheme == "https")
			{
				if(!isset($parsed["port"])) { $port = "443"; } else { $port = $parsed["port"]; };
				$tip =  "ssl://" . $ip;
			}
			$timeout = 5;
			$error = null;
			$errstr = null;

			//Request 만들기								------------------->>finish 6
			$out  = "POST " . $path . " HTTP/1.1\r\n";
			$out .= "Host: ". $ip . "\r\n";
			$out .= $Authorization_Header . "\r\n";
			$out .= "Accept-Language: ko\r\n";
			$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$out .= "Content-Length: 0\r\n\r\n";//Request Token 받기에서는 post body에 들어가는 파라메터가 없어서 0

			//Request 보내기
			$fp = @fsockopen($tip, $port, $errno, $errstr, $timeout);

			//Reponse 받기
			if (!$fp) {
				//echo("ERROR!!");
				return null;
			} else {
				fwrite($fp, $out);
				$response = "";
				while ($s = fread($fp, 4096)) {
					$response .= $s;
				}
			}

			//Response Header와 Body 분리
			$bi = strpos($response, "\r\n\r\n");
			$body = substr($response, $bi+4);

			$tmpArray = explode("&",$body);
			$TokenArray = 	explode("=",$tmpArray[0]);
			$TokenSCArray = 	explode("=",$tmpArray[1]);
			$request_token = $TokenArray[1];
			$request_token_secret = $TokenSCArray[1];

			//다음 단계인 Nate Login을 위해 페이지 이동
			$authloginckurl = "https://oauth.nate.com/OAuth/Authorize/V1a?oauth_token=".$request_token;
			$this->ci->session->set_userdata('cyworld_request_token_secret', $request_token_secret);

			return $authloginckurl;
		}

		/**
		@ cyworld token
		**/
		function cyworldaccesstoken($mtype, $mform = 'login') {

			$this->ci->load->helper('readurl');
			$this->user_profile = null;

			$request_token	= $_GET['oauth_token'];
			$oauth_verifier	= $_GET['oauth_verifier'];
			$oauth_token_secret = $this->ci->session->userdata('cyworld_request_token_secret');

			$oauth_signature_method = "HMAC-SHA1";
			$oauth_timestamp = time();
			$oauth_nonce = md5(microtime().mt_rand()); // md5s look nicer than numbers;
			$oauth_version = "1.0";

			$get_access_token_url = "https://oauth.nate.com/OAuth/GetAccessToken/V1a";

			//Get Request Token									----------------->>1
			//!!파라메터 이름 순서로 조합해야 한다.//!!파라메터의 이름과 값은 rfc3986 으로 encode//[Name=Valeu&Name=Value…] 형식으로 연결
			$Query_String = urlencode_rfc3986("oauth_consumer_key")."=".urlencode_rfc3986($this->arrSns['key_c']);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_nonce")."=".urlencode_rfc3986($oauth_nonce);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_signature_method")."=".urlencode_rfc3986($oauth_signature_method);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_timestamp")."=".urlencode_rfc3986($oauth_timestamp);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_token")."=".urlencode_rfc3986($request_token);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_verifier")."=".urlencode_rfc3986($oauth_verifier);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_version")."=".urlencode_rfc3986($oauth_version);

			//Base String 요소들을 rfc3986 으로 encode	------------------>>2
			$Base_String = urlencode_rfc3986("POST")."&".urlencode_rfc3986($get_access_token_url)."&".urlencode_rfc3986($Query_String);

			//지금 단계에서는 $oauth_token_secret이 ""	------------------->>3
			$Key_For_Signing = urlencode_rfc3986($this->arrSns['secret_c'])."&".urlencode_rfc3986($oauth_token_secret);

			//oauth_signature 생성									------------------->>4
			$oauth_signature=base64_encode(hash_hmac('sha1', $Base_String, $Key_For_Signing, true));

			//Authorization Header 조합							------------------->>5
			$Authorization_Header  = "Authorization: OAuth ";
			$Authorization_Header .= urlencode_rfc3986("oauth_version")."=\"".urlencode_rfc3986($oauth_version)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_nonce")."=\"".urlencode_rfc3986($oauth_nonce)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_timestamp")."=\"".urlencode_rfc3986($oauth_timestamp)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_consumer_key")."=\"".urlencode_rfc3986($this->arrSns['key_c'])."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_token")."=\"".urlencode_rfc3986($request_token)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_verifier")."=\"".urlencode_rfc3986($oauth_verifier)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_signature_method")."=\"".urlencode_rfc3986($oauth_signature_method)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_signature")."=\"".urlencode_rfc3986($oauth_signature)."\"";

			$parsed = parse_url($get_access_token_url);
			$scheme = $parsed["scheme"];
			$path = $parsed["path"];
			$ip = $parsed["host"];
			$port = @$parsed["port"];

			if ($scheme == "http")
			{
				if(!isset($parsed["port"])) { $port = "80"; } else { $port = $parsed["port"]; };
				$tip = $ip;
			} else if ($scheme == "https")
			{
				if(!isset($parsed["port"])) { $port = "443"; } else { $port = $parsed["port"]; };
				$tip =  "ssl://" . $ip;
			}
			$timeout = 5;
			$error = null;
			$errstr = null;

			//Request 만들기								------------------->>finish 6
			$out  = "POST " . $path . " HTTP/1.1\r\n";
			$out .= "Host: ". $ip . "\r\n";
			$out .= $Authorization_Header . "\r\n";
			$out .= "Accept-Language: ko\r\n";
			$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$out .= "Content-Length: 0\r\n\r\n";//Request Token 받기에서는 post body에 들어가는 파라메터가 없어서 0

			//Request 보내기
			$fp = @fsockopen($tip, $port, $errno, $errstr, $timeout);

			//Reponse 받기
			if (!$fp) {
				//echo("ERROR!!");
				return null;
			} else {
				fwrite($fp, $out);
				$response = "";
				while ($s = fread($fp, 4096)) {
					$response .= $s;
				}
			}
			//fclose ($fp);

			//Response Header와 Body 분리
			$bi = strpos($response, "\r\n\r\n");
			$body = substr($response, $bi+4);

			$tmpArray = explode("&",$body);
			$TokenArray = 	explode("=",$tmpArray[0]);
			$TokenSCArray = 	explode("=",$tmpArray[1]);
			$access_token = $TokenArray[1];
			$access_token_secret = $TokenSCArray[1];

			$oauth_timestamp = time();
			$oauth_nonce = md5(microtime().mt_rand()); // md5s look nicer than numbers;

			$get_nateon_GetProfile_token_url = "https://openapi.nate.com/OApi/RestApiSSL/ON/250020/nateon_GetProfile/v1";

			//Get Request Token									----------------->>1
			//!!파라메터 이름 순서로 조합해야 한다.//!!파라메터의 이름과 값은 rfc3986 으로 encode//[Name=Valeu&Name=Value…] 형식으로 연결

			$Query_String = urlencode_rfc3986("oauth_consumer_key")."=".urlencode_rfc3986($this->arrSns['key_c']);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_nonce")."=".urlencode_rfc3986($oauth_nonce);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_signature_method")."=".urlencode_rfc3986($oauth_signature_method);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_timestamp")."=".urlencode_rfc3986($oauth_timestamp);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_token")."=".urlencode_rfc3986($access_token);
			$Query_String .= "&";
			$Query_String .= urlencode_rfc3986("oauth_version")."=".urlencode_rfc3986($oauth_version);

			///Base String 구성 요소를 &로 연결	------------------>>2
			$Base_String = urlencode_rfc3986("POST")."&".urlencode_rfc3986($get_nateon_GetProfile_token_url)."&".urlencode_rfc3986($Query_String);

			//지금 단계에서는 $oauth_token_secret에 request_token_secret을 사용	------------------->>3
			$Key_For_Signing = urlencode_rfc3986($this->arrSns['secret_c'])."&".urlencode_rfc3986($access_token_secret);

			//oauth_signature 생성									------------------->>4
			$oauth_signature=base64_encode(hash_hmac('sha1', $Base_String, $Key_For_Signing, true));

			//Authorization Header 조합							------------------->>5
			$Authorization_Header  = "Authorization: OAuth ";
			$Authorization_Header .= urlencode_rfc3986("oauth_version")."=\"".urlencode_rfc3986($oauth_version)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_nonce")."=\"".urlencode_rfc3986($oauth_nonce)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_timestamp")."=\"".urlencode_rfc3986($oauth_timestamp)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_consumer_key")."=\"".urlencode_rfc3986($this->arrSns['key_c'])."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_token")."=\"".urlencode_rfc3986($access_token)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_signature_method")."=\"".urlencode_rfc3986($oauth_signature_method)."\",";
			$Authorization_Header .= urlencode_rfc3986("oauth_signature")."=\"".urlencode_rfc3986($oauth_signature)."\"";

			$parsed = parse_url($get_nateon_GetProfile_token_url);
			$scheme = $parsed["scheme"];
			$path = $parsed["path"];
			$ip = $parsed["host"];
			$port = @$parsed["port"];

			$queryStr="oauth_consumer_key=".$this->arrSns['key_c']."&oauth_nonce=".$oauth_nonce."&oauth_signature_method=".$oauth_signature_method."&oauth_timestamp=".$oauth_timestamp."&oauth_token=".$access_token."&oauth_version=".$oauth_version;
			$queryLength = (strlen($queryStr));

			if ($scheme == "http")
			{
				if(!isset($parsed["port"])) { $port = "80"; } else { $port = $parsed["port"]; };
				$tip = $ip;
			} else if ($scheme == "https")
			{
				if(!isset($parsed["port"])) { $port = "443"; } else { $port = $parsed["port"]; };
				$tip =  "ssl://" . $ip;
			}
			$timeout = 5;
			$error = null;
			$errstr = null;

			//Request 만들기								------------------->>finish 6
			$out  = "POST " . $path . " HTTP/1.1\r\n";
			$out .= "Host: ". $ip . "\r\n";
			$out .= $Authorization_Header . "\r\n";
			$out .= "Accept-Language: ko\r\n";
			$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$out .= "Content-Length: " . $queryLength . "\r\n\r\n";
			$out .= $queryStr;

			//Request 보내기
			$fp = @fsockopen($tip, $port, $errno, $errstr, $timeout);

			//Reponse 받기
			if (!$fp) {
				//echo("ERROR!!");
				return null;
			} else {
				fwrite($fp, $out);
				$response = "";
				while ($s = fread($fp, 4096)) {
					$response .= $s;
				}
				$bi = strpos($response, "\r\n\r\n");
				$body = substr($response, $bi+4);
			}
			//fclose ($fp);
			$cyworldarr = xml2array($body);
			//cyworld 사용자정보 가져오기
			if($cyworldarr['response']['header']['rcode'] == 'RET0000' ) {
				$this->user_profile = $cyworldarr['response']['body']['profile'];
				$this->ci->session->set_userdata(array('cyuser'=>$this->user_profile));
			}
			return $this->user_profile;
		}


		/**
		@ cyworld token
		**/
		function cyworldaccount($mtype, $mform = 'login') {
			$this->user_profile = $this->ci->session->userdata('cyuser');
			return $this->user_profile;
		}
	/**
	@ cyworld api end
	------------------------------------------------------------
	**/


	/**
	@ naver api start
	------------------------------------------------------------
	**/

		/**
		@ naver login
		**/
		function naverloginurl($mtype, $mform = 'login') {

			$this->ci->load->helper('readurl');

			$this->arrSns['callbackurl_n'] = $this->getCallbackURI($this->arrSns['nid_callbackurl']);
			$this->arrSns['callbackurl_n'] .= '/sns_process/naveruserck';

			$get_request_token_url = "https://nid.naver.com/oauth2.0/authorize";

			$this->client_id		= $this->arrSns['nid_client_id'] ? $this->arrSns['nid_client_id'] : $this->arrSns['key_n'];
			$this->client_secret	= $this->arrSns['nid_client_secret'] ? $this->arrSns['nid_client_secret'] : $this->arrSns['secret_n'];

			$mt			= microtime();
			$rand		= mt_rand();
			$state		= md5( $mt . $rand );

			## www로 접근시 사용
			$this->ci->session->set_userdata("http_host",$_SERVER['HTTP_HOST']);

			$this->ci->session->set_userdata("naver_state",$state);
			## 지정된 파라메타 값 외에 리턴 안해줌.
			$this->ci->session->set_userdata("mtype",$mtype);

			$_SESSION['http_host']		= $_SERVER['HTTP_HOST'];
			$_SESSION['naver_state']	= $state;
			$_SESSION['mtype']			= $mtype;

			$authloginckurl = $get_request_token_url . "?response_type=code&client_id=" . $this->client_id . "&state=" . $state . "&redirect_uri=" . urlencode($this->arrSns['callbackurl_n']);

			return $authloginckurl;
		}

		/**
		@ naver token
		**/
		function naveraccesstoken($mtype, $mform = 'login') {

			$this->ci->load->helper('readurl');
			$this->user_profile = null;

			$code	= $_GET['code'];	//
			$state	= $_GET['state'];	//세션확인용 값

			$sess_naver_state = ($this->ci->session->userdata('naver_state'))? $this->ci->session->userdata('naver_state'):$_SESSION['naver_state'];

			if($_GET['error']){
				$unsetuserdata = array('naver_state'=>'','mtype'=>'');
				$this->ci->session->unset_userdata($unsetuserdata);
				$this->user_profile = array("error"=>$_GET['error']);
			}else{

				## 세션확인 :: 세션이 동일하지 않을 시 초기화 시킴.
				if( $sess_naver_state != $state ) {
					$unsetuserdata = array('naver_state'=>'','mtype'=>'');
					$this->ci->session->unset_userdata($unsetuserdata);
					$this->user_profile = array("error"=>"session_error");
				}else{

					$this->client_id		= $this->arrSns['nid_client_id'] ? $this->arrSns['nid_client_id'] : $this->arrSns['key_n'];
					$this->client_secret	= $this->arrSns['nid_client_secret'] ? $this->arrSns['nid_client_secret'] : $this->arrSns['secret_n'];

					## accesstoken 유효시간 동안은 그대로 이용.
					if($this->ci->session->userdata("naver_token_time") >= strtotime(date("Y-m-d H:i:s")) && $this->ci->session->userdata("naver_access_token") ){
						$res['error']			= '';
						$res['access_token']	= $this->ci->session->userdata("naver_access_token");
					}else{
						## accesstoken 받아오기
						$accurl = "https://nid.naver.com/oauth2.0/token";
						$args	= array();
						$args	= array(
									'grant_type' =>'authorization_code',
									'client_id' => $this->client_id,
									'client_secret' => $this->client_secret,
									'code' => $code,
									'state' => $state
							);
						$res	= $this->getnavertoken($accurl,$args);
					}
					##---------------------------------------------------------------------------
					## AccessToken 발급 오류시
					if($res['error']){
						$unsetuserdata = array('naver_state'=>'','mtype'=>'');
						$this->ci->session->unset_userdata($unsetuserdata);
					}else{

						## accesstoken session 생성
						if($res['expires_in'] > 0){
							$this->ci->session->set_userdata("naver_access_token",$res['access_token']);
							$_SESSION['naver_access_token'] = $res['access_token'];
							$mktime = strtotime(date("Y-m-d H:i:s")) + ($res['expires_in']-3500);
							$this->ci->session->set_userdata("naver_token_time",$mktime);
						}

						$this->user_profile = $_SESSION['naver_access_token'];
						## 사용자 정보 가져오기
						$data	= $this->getnaverinfo($res['access_token']);
						$data	= $this->convertObjectToArray($data);

						if($data['result']['resultcode'] == '00'){
							$nvuser = array();
							//$data['response']['enc_id'] = '';
							$nvuser['nickname']		= $data['response']['nickname'];
							$nvuser['name']			= $data['response']['name'];
							$nvuser['age']			= $data['response']['age'];
							$nvuser['gender']		= $data['response']['gender'];
							$nvuser['birthday']		= $data['response']['birthday'];
							$this->user_profile = $nvuser;

							$nvuser['email']		= $data['response']['email'];
							$nvuser['enc_id']		= $data['response']['enc_id'];	//회원고유id(네이버에서 삭제 예정)
							$nvuser['id']			= $data['response']['id'];		//Client ID별 회원고유 id
							$this->ci->session->set_userdata(array('nvuser'=>$nvuser));
							$_SESSION['nvuser'] = $nvuser;
						}else{
							//회원정보 가져오기 실패하였습니다
							$unsetuserdata = array('naver_state'=>'','naver_access_token'=>'','mtype'=>'');
							$this->ci->session->unset_userdata($unsetuserdata);
						}
					}
				}
			}

			return $this->user_profile;
		}

		/* 네이버 로그인 엑세스토큰 */
		function getnavertoken($url,$args){

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
			$data = curl_exec($ch);

			$res = json_decode($data);
			if((object)$res){
				$res = (array)$res;
			}

			return $res ;
		}

		/* 네이버 로그인 회원정보 */
		function getnaverinfo($access_token){

			$ch = curl_init();

			$url = "https://openapi.naver.com/v1/nid/getUserProfile.xml";
			//$url = "https://apis.naver.com/nidlogin/nid/getUserProfile.xml";
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$access_token));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$data = curl_exec($ch);

			$res = simplexml_load_string($data, null, LIBXML_NOCDATA);

			return $res ;

		}

		/**
		@ naver token
		**/
		function naveraccount($mtype, $mform = 'login') {

			$sess_nvuser= ($this->ci->session->userdata('nvuser'))? $this->ci->session->userdata('nvuser'):$_SESSION['nvuser'];
			$this->user_profile = $sess_nvuser;
			return $this->user_profile;
		}
	/**
	@ naver api end
	------------------------------------------------------------
	**/

	/**
	@ kakao login api start
	------------------------------------------------------------
	**/
		/* 카카오 로그인 회원정보(닉네임,id 만 가져옴)) */
		function setkakaouser($userdata){
			$kkouser = array();
			$kkouser['nickname'] = $userdata['properties']['nickname'];
			$kkouser['id'] = $userdata['id'];
			$this->ci->session->set_userdata('kkouser', $kkouser);
			$_SESSION['kkouser'] = $kkouser;
			unset($userdata);
		}

		/**
		@ kakao token
		**/
		function kakaoaccount($mtype, $mform = 'login') {
			$this->user_profile = $this->ci->session->userdata('kkouser');
			return $this->user_profile;
		}

	/**
	@ kakao login api end
	------------------------------------------------------------
	**/


	/**
	@ daum api start
	------------------------------------------------------------
	**/

		/**
		@ daum login
		**/
		function daumloginurl($mtype, $mform = 'login') {

			$this->ci->load->helper('readurl');

			if(strstr("http",$this->arrSystem['domain'])) $this->arrSystem['domain'] = str_replace("http://","",$this->arrSystem['domain']);

			//한글 도메인일경우 Punycode 변환
			if(preg_match('/[^\x00-\x7f]/',$this->arrSystem['domain'])){
				$this->ci->load->library('punycode');
				$this->arrSystem['domain']	= $this->ci->punycode->encodeHostName($this->arrSystem['domain']);
			}

			## 등록된 쇼핑몰 도메인에서 host 만 가져오기
			$this->arrSystem['domain']	= "http://".$this->arrSystem['domain'];
			$host_tmp				= parse_url($this->arrSystem['domain']);
			$domain					= $host_tmp['host'];

			if($domain){
				if( preg_match("/^m\./",$_SERVER['HTTP_HOST']) && !preg_match("/^m\./",$domain) ){
					$domain = "m.".$domain;
				}
				$this->arrSns['callbackurl_d'] = 'http://'.($domain).'/sns_process/daumuserck';
			}else{
				if( preg_match("/^m\./",$_SERVER['HTTP_HOST']) && !preg_match("/^m\./",$this->arrSystem['subDomain']) ) {
					$this->arrSystem['subDomain'] = "m.".$this->arrSystem['subDomain'];
				}
				$this->arrSns['callbackurl_d'] = 'http://'.($this->arrSystem['subDomain']).'/sns_process/daumuserck';
			}

			$get_request_token_url = "https://apis.daum.net/oauth2/authorize";

			$this->client_id		= $this->arrSns['key_d'];
			$this->client_secret	= $this->arrSns['secret_d'];

			## 지정된 파라메타 값 외에 리턴 안해줌.
			$this->ci->session->set_userdata("http_host",$_SERVER['HTTP_HOST']);

			## 지정된 파라메타 값 외에 리턴 안해줌.
			$this->ci->session->set_userdata("mtype",$mtype);

			$_SESSION['http_host'] = $_SERVER['HTTP_HOST'];
			$_SESSION['mtype']		= $mtype;

			$authloginckurl = $get_request_token_url . "?response_type=token&client_id=" . $this->client_id ."&redirect_uri=" . urlencode($this->arrSns['callbackurl_d'])."&scope=user";

			return $authloginckurl;
		}

		/**
		@ daum token
		**/
		function daumuserprofile($mtype, $mform = 'login') {

			$this->ci->load->helper('readurl');
			$this->user_profile = null;

			$token_tmp		= explode("#access_token=",urldecode($_POST['str']));
			$token_tmp2		= explode("&",$token_tmp[1]);
			$access_token	= $token_tmp2[0];

			$this->ci->session->set_userdata('daum_access_token',$access_token);
			$_SESSION['daum_access_token']		= $access_token;

			## 사용자 정보 가져오기
			$data	= $this->getdauminfo($access_token);
			$data	= $this->convertObjectToArray($data);

			$error_msg = $this->getdaumerrormsg(trim($res['error']));
			if($data['message'] == "OK"){
				$dmuser				= array();
				$dmuser['id']		= $data['result']['id'];
				$dmuser['nickname'] = $data['result']['nickname'];
				$this->user_profile = $dmuser;
				$this->ci->session->set_userdata(array('dmuser'=>$dmuser));
				$_SESSION['dmuser']		= $dmuser;
			}else{
				//회원정보 가져오기 실패하였습니다
				$unsetuserdata = array('daum_access_token'=>'','mtype'=>'');
				$this->ci->session->unset_userdata($unsetuserdata);
			}

			return $this->user_profile;
		}

		public function getdaumerrormsg($cd){

			$login_error_msg = array();
			$login_error_msg['invalid_request']			= "올바른 요청이 아닙니다. 시스템관리자에게 문의해 주세요.";
			$login_error_msg['invalid_grant']			= "올바른 인증 방법이 아닙니다. 시스템관리자에게 문의해 주세요.";
			$login_error_msg['unauthorized_client']		= "올바른 클라이언트가 아닙니다. 시스템관리자에게 문의해 주세요.";
			$login_error_msg['unsupported_grant_type']	= "지원하지 않는 인증 방법입니다. 시스템관리자에게 문의해 주세요.";
			$login_error_msg['unauthorized_client']		= "지원하지 않는 응답 방식입니다. 시스템관리자에게 문의해 주세요.";
			$login_error_msg['unsupported_response_type'] = "정의되어있지 않은 response type 입니다. 시스템관리자에게 문의해 주세요.";
			$login_error_msg['invalid_scope']			= "올바른 요청영역이 아닙니다. 시스템관리자에게 문의해 주세요.";
			$login_error_msg['server_error']			= "다음(Daum) 오픈 API 서비스 내부 시스템 에러. 시스템관리자에게 문의해 주세요.";
			$login_error_msg['temporary_unavailable']	= "다음(Daum) 오픈 API 서비스 연결 실패. 서비스 시스템 과부하 또는 장애로 인한 서비스 연결 실패.";

			return $login_error_msg[$cd];
		}

		/* dayn 로그인 엑세스토큰 */
		function getdaumtoken($url,$args){

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_USERAGENT, "content-type: application/x-www-form-urlencoded");
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
			$data = curl_exec($ch);

			$res = json_decode($data);
			if((object)$res){
				$res = (array)$res;
			}

			return $res ;
		}

		/* dayn 로그인 회원정보 */
		function getdauminfo($access_token){

			$ch = curl_init();

			$url = "https://apis.daum.net/user/v1/show.xml";
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$access_token));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$data = curl_exec($ch);

			$res = simplexml_load_string($data, null, LIBXML_NOCDATA);

			return $res ;

		}

		/**
		@ daum token
		**/
		function daumaccount($mtype, $mform = 'login') {
			$sess_dmuser= ($this->ci->session->userdata('dmuser'))? $this->ci->session->userdata('dmuser'):$_SESSION['dmuser'];
			$this->user_profile = $sess_dmuser;
			return $this->user_profile;
		}
	/**
	@ daum api end
	------------------------------------------------------------
	**/

	public function convertObjectToArray($obj)
	{
		if(is_object($obj)) $obj = (array) $obj;
		if(is_array($obj)) {
			$new = array();
			foreach($obj as $key => $val) {
				$new[$key] = self::convertObjectToArray($val);
			}
		}
		else
		{
			$new = $obj;
		}
		return $new;
	}


	//허용권한체크
	function facebookpermissions($facebook) {
		// Get User ID
		if ($this->ci->session->userdata('fbuser')) {
			try{
				$fbpermissions =$facebook->api($this->fbuser.'/permissions',array('access_token'=>$this->ci->session->userdata('user_accesstoken')));
				foreach($fbpermissions['data'] as $fbpermissionskey => $fbpermissionsvalue){
					if($fbpermissionsvalue['permission']) $fbpermissionsar[] = $fbpermissionsvalue['permission'];
				}
			}catch (FacebookApiException $o){
				//debug_var($o);
				$fbpermissions['error'] = true;
			}
		}
		return ($fbpermissionsar)?$fbpermissionsar:$fbpermissions;
	}

	//app token
	function get_app_token($appid, $appsecret)
	{
		$args = array(
		'grant_type' => 'client_credentials',
		'client_id' => $appid,
		'client_secret' => $appsecret
		);

		$ch = curl_init();
		$url = 'https://graph.facebook.com/v'.$this->__APP_VER__.'/oauth/access_token';
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
		$data = curl_exec($ch);

		return json_encode($data);
	}

	//app token
	function get_token($appid, $appsecret,$code,$ftype='domain_facebook')
	{
		$args = array(
		'redirect_uri'	=> 'http://'.$_SERVER['HTTP_HOST'].'/admin/sns/'.$ftype,
		'client_id' => $appid,
		'client_secret' => $appsecret,
		'code' => $code
		);
		/**
		'grant_type'=>'fb_exchange_token',
		'fb_exchange_token'=>'EXISTING_ACCESS_TOKEN',
		**/
		$ch = curl_init();
		$url = 'https://graph.facebook.com/v'.$this->__APP_VER__.'/oauth/access_token';
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
		$data = curl_exec($ch);

		return json_encode($data);
	}


	/**
	* @ facebook page tabs read
	* @ $params app_id
	**/
	function facebook_tabs_read($params)
	{
		$ret_obj = $this->facebook->api('/'.$params['page_id'].'/tabs');
		return $ret_obj['data'];
	}

	/**
	* @ facebook page tabs page read
	* @ $params app_id
	**/
	function facebook_page_read($params, &$appuseck,$facebook=null)
	{
		$facebook = ($facebook)?$facebook:$this->facebook;
		try{
			if($this->ci->session->userdata('access_token')){
				$pageapiobj = $facebook->api(array( 'method' => 'fql.query', 'query' =>"SELECT  page_id, page_url, name, pic_square, type, has_added_app FROM page WHERE page_id IN (SELECT page_id FROM page_admin WHERE uid=me()) and name!=''","access_token" => $this->ci->session->userdata('access_token')));
			}else{
				$pageapiobj = $facebook->api(array( 'method' => 'fql.query', 'query' =>"SELECT  page_id, page_url, name, pic_square, type, has_added_app FROM page WHERE page_id IN (SELECT page_id FROM page_admin WHERE uid=me()) and name!=''"));
			}
		}catch (FacebookApiException $o){
			//REST API is deprecated for versions v2.1 and higher
			//debug_var($o);// echo $e->getMessage();
		}

		$appuseck = 'N';
		foreach($pageapiobj as $pagekey => $pagevalue)
		{
			$appidck = $this->facebook->api('/'.$pagevalue['page_id'].'/tabs');//debug_var($appidck);debug_var($pageapiobj);
			unset($pageidinfo);
			$i =0;
			$appuse =  false;
			unset($pagevalue['page_app_link']);
			foreach($appidck['data'] as $tabskey => $tabsvalue){
				if($tabsvalue['application']['id'] && !in_array($tabsvalue['application']['id'],$pageidinfo) )
				{
					$pageidinfo[$i]['name']			= $tabsvalue['application']['name'];
					$pageidinfo[$i]['namespace']	= $tabsvalue['application']['namespace'];
					$pageidinfo[$i]['id']					= $tabsvalue['application']['id'];
					$pageidinfo[$i]['link']				= $tabsvalue['link'];
					if($this->__APP_ID__ == $tabsvalue['application']['id'] ) {//이미등록된 앱페지인경우
						$appuse = true;
						$appuseck = 'Y';
						$pagevalue['page_app_link'] = $tabsvalue['link'];
					}
					$i++;
				}
			}
			$pagevalue['appinfo'] = $pageidinfo;
			if($appuse) {
				$pagevalue['appuse'] = 'Y';
			}else{
				$pagevalue['appuse'] = 'N';
			}
			$newpageinfo[] = $pagevalue;
		}
		return $newpageinfo;

	}

	/**
	* @ facebook page tabs add
	* @ $params page_id
	* @ $params app_id
	**/
	function facebook_tabs_add($params)
	{
		$page_accounts = $this->facebook->api('/'.$params['page_id'].'/','GET',array("fields" => "access_token"));
		$this->page_accesstoken = $page_accounts['access_token'];
		try{
			$ret_obj = $this->facebook->api('/'.$params['page_id'].'/tabs', 'POST',  array("app_id" => $params['app_id'], "access_token" => $this->page_accesstoken));
		}catch (FacebookApiException $o){
			debug_var($o);
		}
		return $ret_obj;
	}

	/**
	* @ facebook page tabs del
	* @ $params page_id
	* @ $params app_id
	**/
	function facebook_tabs_delete($params)
	{
		$page_accounts = $this->facebook->api('/'.$params['page_id'].'/','GET',array("fields" => "access_token"));
		$this->page_accesstoken = $page_accounts['access_token'];
		try{
			$ret_obj = $this->facebook->api('/'.$params['page_id'].'/tabs/app_'.$params['app_id'], 'DELETE',  array("access_token" => $this->page_accesstoken));
		}catch (FacebookApiException $o){
			debug_var($o);
		}
		return $ret_obj;
	}

	function publishCustomAction($product_url, $type)
	{
		if($this->ci->arrSns['key_f'] == '455616624457601' || ($this->ci->arrSns['key_f'] != '455616624457601' && !$this->ci->arrSns['facebook_publish_actions'] ) ) {
			return;//@2015-04-22 facebook version 2.* 권한 제한으로 publish_actions 값이 있을 때에만 적용
		}

		$this->fbuser = $this->facebookuserid();
		if ( !$this->fbuser ) {
			$this->facebook = new Facebook(array(
			  'appId'  => $this->__APP_ID__,
			  'secret' => $this->__APP_SECRET__,
			  "cookie" => true
			));
			// Get User ID
			$this->fbuser = $this->facebook->getUser();
			if($this->fbuser && !$this->ci->session->userdata('fbuser')){
				$this->ci->session->set_userdata('fbuser', $this->fbuser);
			}else{
				$this->fbuser = $this->facebooklogin();
				if( $this->fbuser && !$this->ci->session->userdata('fbuser') ) {
					$this->ci->session->set_userdata('fbuser', $this->fbuser);
				}
			}
		}else{
			if( !$this->ci->session->userdata('fbuser') ) {
				$this->ci->session->set_userdata('fbuser', $this->fbuser);
			}
		}

		if( ($this->ci->arrSns['facebook_review'] != 'Y' && $type=='write') || ($this->ci->arrSns['facebook_interest'] != 'Y' && $type=='interests') || ($this->ci->arrSns['facebook_buy'] != 'Y' && $type=='buy') ) return false;//사용여부

		if($this->ci->session->userdata('fbuser')){
			if( $this->ci->__APP_NAMES__ ) {
				try{
					if($type=='interests'){//위시리스트
						$actiontype = $this->ci->__APP_STORY_INTERESTS__;
					}elseif($type=='write'){
						$actiontype = $this->ci->__APP_STORY_WRITE__;
					}elseif($type=='buy'){
						$actiontype = $this->ci->__APP_STORY_BUY__;
					}else{
						$actiontype = $this->ci->__APP_STORY__;
					}
					$ret_obj = $this->facebook->api($this->fbuser.'/'.$this->ci->__APP_NAMES__ .':'.$actiontype .'', 'POST', array($this->ci->__APP_TYPE__ => $product_url,'access_token'=>$this->ci->session->userdata('user_accesstoken') ));
					//debug_var($ret_obj);
				}catch (FacebookApiException $o){
					$result['message'] = $o.message;
					$result['error'] = true;
					//debug_var($result);
				}
			}
		}
		return $ret_obj;
  }

	function publishCustomActionLike($product_url)
	{
		$ret_obj = array();
		if($this->ci->session->userdata('fbuser')){
			if(!$this->ci->session->userdata('user_accesstoken'))  {
				$this->facebook = new Facebook(array(
				  'appId'  => $this->__APP_ID__,
				  'secret' => $this->__APP_SECRET__,
				  "cookie" => true
				));
				$token = $this->facebook->getAccessToken();
				$tokenar = @explode("|",$token);
				$token = ($tokenar[1])?$tokenar[1]:$tokenar[0];
				$this->ci->session->set_userdata('user_accesstoken', $token);
			}
			try{
				$ret_obj = $this->facebook->api($this->fbuser.'/og.likes', 'POST', array('object' => $product_url,'access_token'=>$this->ci->session->userdata('user_accesstoken') ));
				//debug_var($ret_obj);
			}catch (FacebookApiException $o){
				//debug_var($o);
				$ret_obj['message'] = $o.message;
				if( strstr($ret_obj['message'],"3501") ) {// && strstr($ret_obj['message'],"Original Action ID")
					$action_id = explode("Original Action ID: ",str_replace("message","",$ret_obj['message']));
					$ret_obj['original_action_id'] = (int)$action_id[1];
				}else{
					$ret_obj['error'] = true;
				}
			}
		}
		return $ret_obj;
	}

	/**
	* @ facebook page tabs del
	* @ $params page_id
	* @ $params app_id
	**/
	function publishCustomActionLikedelete($action_id)
	{
		try{
			$ret_obj = $this->facebook->api('/'.$action_id, 'DELETE');
		}catch (FacebookApiException $o){
			//debug_var($o);
		}
		return $ret_obj;
	}

	//facebook like_count => total_count link_stat
	function facebooklikestat($url, $type='like_count'){
		if( $this->__APP_ID__ == '455616624457601' && $this->__APP_VER__ == '1.0' ) {//기본앱 1.0버전
			try{
				$fql_app_user = "SELECT ".$type." FROM  link_stat  WHERE url  = '". $url ."' ";
				$ret_obj = $this->facebook->api(array('method' => 'fql.query', 'query' => $fql_app_user));
				$ret_obj = $ret_obj[0];
				//debug_var($ret_obj);
				return $ret_obj;
			}catch (FacebookApiException $o){
				//debug_var($o);
				$ret_obj['message'] = $o.message;
				$ret_obj['error'] = true;
				return $ret_obj;;
			}
		}else{
			$CI = &get_instance();
			$goodseq = @end(explode('=', $url));
			$query = $CI->db->query('SELECT like_count  FROM `fm_goods` where goods_seq = ?', [$goodseq]);
			list($row) = $query->result_array();
			return $row;
		}
	}

	//facebook like 한 경우 체크하기
	function facebook_goodsLike($product_url)
	{
		return false;//@2012-11-14 실시간체크 제외
	}

	/**
	* @ facebook friends
	**/
	function facebook_searchfriends($usename)
	{
		//$fbuser_profile = $this->facebooklogin();
		$this->fbuser = $this->facebookuserid();
		if ( !$this->fbuser ) {
			$this->facebook = new Facebook(array(
			  'appId'  => $this->__APP_ID__,
			  'secret' => $this->__APP_SECRET__,
			  "cookie" => true
			));
			// Get User ID
			$this->fbuser = $this->facebook->getUser();
			if($this->fbuser && !$this->ci->session->userdata('fbuser')){
				$this->ci->session->set_userdata('fbuser', $this->fbuser);
			}else{
				$this->fbuser = $this->facebooklogin();
				if( $this->fbuser && !$this->ci->session->userdata('fbuser') ) {
					$this->ci->session->set_userdata('fbuser', $this->fbuser);
				}
			}
		}else{
			if( !$this->ci->session->userdata('fbuser') ) {
				$this->ci->session->set_userdata('fbuser', $this->fbuser);
			}
		}

		if(!$this->fbuser){
			return $ret_obj['error'] = true;
		}else{

			try{
				$ret_obj = $this->facebook->api($this->fbuser.'/friends','GET',array('access_token'=>$this->ci->session->userdata('user_accesstoken'), "fields" => "id, name, picture"));
			}catch (FacebookApiException $o){
				$ret_obj['message'] = $o.message;
				$ret_obj['error'] = true;
				return $ret_obj;//debug_var($o);
			}
			return $ret_obj;
		}
	}

	//facebook 초대하기
	function facebook_friendfeed($data)
	{
		//$fbuser_profile = $this->facebooklogin();
		$this->fbuser = $this->facebookuserid();
		if ( !$this->fbuser ) {
			$this->facebook = new Facebook(array(
			  'appId'  => $this->__APP_ID__,
			  'secret' => $this->__APP_SECRET__,
			  "cookie" => true
			));
			// Get User ID
			$this->fbuser = $this->facebook->getUser();
			if($this->fbuser && !$this->ci->session->userdata('fbuser')){
				$this->ci->session->set_userdata('fbuser', $this->fbuser);
			}else{
				$this->fbuser = $this->facebooklogin();
				if( $this->fbuser && !$this->ci->session->userdata('fbuser') ) {
					$this->ci->session->set_userdata('fbuser', $this->fbuser);
				}
			}
		}else{
			if( !$this->ci->session->userdata('fbuser') ) {
				$this->ci->session->set_userdata('fbuser', $this->fbuser);
			}
		}

		if(!$this->fbuser){
			return $result['error'] = true;
		}else{
			try{
				$params = array('access_token'=>$this->ci->session->userdata('user_accesstoken'),  'message'=>$data['message'],'name'=>$data['name'],'link'=> $data['link'],'description'=> $data['description']);
				$result = $this->facebook->api('/'.$data['friendid'].'/feed?access_token='.$this->ci->session->userdata('user_accesstoken'),'POST', $params);
			}catch (FacebookApiException $o){
				$result['message'] = $o.message;
				$result['error'] = true;
			}
			return $result;
		}
	}

	//facebook 글남기기
	function facebook_mefeed($data)
	{
		//$fbuser_profile = $this->facebooklogin();
		$this->fbuser = $this->facebookuserid();
		if ( !$this->fbuser ) {
			$this->facebook = new Facebook(array(
			  'appId'  => $this->__APP_ID__,
			  'secret' => $this->__APP_SECRET__,
			  "cookie" => true
			));
			// Get User ID
			$this->fbuser = $this->facebook->getUser();
			if($this->fbuser && !$this->ci->session->userdata('fbuser')){
				$this->ci->session->set_userdata('fbuser', $this->fbuser);
			}else{
				$this->fbuser = $this->facebooklogin();
				if( $this->fbuser && !$this->ci->session->userdata('fbuser') ) {
					$this->ci->session->set_userdata('fbuser', $this->fbuser);
				}
			}
		}else{
			if( !$this->ci->session->userdata('fbuser') ) {
				$this->ci->session->set_userdata('fbuser', $this->fbuser);
			}
		}

		if(!$this->fbuser){
			return $result['error'] = true;
		}else{
			try{
				$params = array('access_token'=>$this->ci->session->userdata('user_accesstoken'), 'message'=>$data['message'],'name'=>$data['name'],'link'=> $data['link']);
				$result = $this->facebook->api($this->fbuser.'/feed?access_token='.$this->ci->session->userdata('user_accesstoken'),'POST', $params);
			}catch (FacebookApiException $o){
				$result['message'] = $o.message;
				$result['error'] = true;
			}
			return $result;
		}
	}

	/**
	 * 카카오톡 인앱브라우저 자동로그인
	 * 조건 / 카카오싱크 로그인 사용 중, UserAgent: Kakao, referer: 없음 (최초 쇼핑몰 접속)
	 */
	public function kakaosyncAutoLogin() {
		if(preg_match('/KAKAOTALK/', $_SERVER['HTTP_USER_AGENT']) && empty($_SERVER['HTTP_REFERER'])) {
			$this->arrSns = ($this->arrSns)?$this->arrSns:config_load('snssocial');
			if(isKakaoSyncUse() && $this->arrSns['use_k'] && $this->arrSns['rest_key_k'] && $this->arrSns['use_talk_login'] ) {
				$ks = "";
				$ks .= "<script src=\"/app/javascript/plugin/kakao/kakao.min.js\"></script>";
				$ks .= "<script src=\"/app/javascript/js/skin-snslogin.js\"></script>";
				$ks .= "<script>";
				$ks .= "var jointype = '';";
				$ks .= "window.onload = function(){";
				$ks .= " loginWithKakaoBrowser();";
				$ks .= "}";
				$ks .= "</script>";

				echo $ks;
			}
		}
	}

	function snsencode($params) {
		$this->load->helper('cookiesecure');
		$encoded = base64_encode(cookieEncode(serialize($params), 50));
		return $encoded;
	}

	function snsdecode($params) {
		$this->load->helper('cookiesecure');
		$decoded = unserialize(cookieDecode(base64_decode($params),50));

		if(is_array($decoded)){
			foreach($decoded as $k=>$v){
				$_POST[$k] = $v;
			}
		}
	}

	//로그분석을 위해
	function _writeLog($fnumber)
	{
		if($_POST){
			$PageCall_time = date("H:i:s");
			$valuear['PageCall time'][] = $PageCall_time;
		}
		foreach ($_POST as $key => $value)
		{
			// If magic quotes is enabled strip slashes
			if (get_magic_quotes_gpc())
			{
				$_POST[$key] = stripslashes($value);
				$value = stripslashes($value);
			}
			$value = urlencode($value);
			$req .= "&$key=$value";
			$valuear[$key][] = $value;
		}

		foreach ($_COOKIE as $key => $value)
		{
			// If magic quotes is enabled strip slashes
			if (get_magic_quotes_gpc())
			{
				$_COOKIE[$key] = stripslashes($value);
				$value = stripslashes($value);
			}
			$value = urlencode($value);
			$req .= "&$key=$value";
			$valuear[$key][] = $value;
		}

		$msg  = $valuear;


	    $file = $fnumber."_input_".date("Ymdhi").".log";
		$path = "data/tmp/log/";
	    if(!($fp = fopen($path.$file, "a+"))) return 0;

	    ob_start();
	    print_r($msg);
	    $ob_msg = ob_get_contents();
	    ob_clean();

	    if(fwrite($fp, " ".$ob_msg."\n") === FALSE)
	    {
	        fclose($fp);
	        return 0;
	    }
	    fclose($fp);
	    return 1;
	}



	function writeLog($fnumber)
	{
		if($_POST){
			$PageCall_time = date("H:i:s");
			$valuear['PageCall time'][] = $PageCall_time;
		}
		foreach ($_POST as $key => $value)
		{
			// If magic quotes is enabled strip slashes
			if (get_magic_quotes_gpc())
			{
				$_POST[$key] = stripslashes($value);
				$value = stripslashes($value);
			}
			$value = urlencode($value);
			$req .= "&$key=$value";
			$valuear[$key][] = $value;
		}

		foreach ($_COOKIE as $key => $value)
		{
			// If magic quotes is enabled strip slashes
			if (get_magic_quotes_gpc())
			{
				$_COOKIE[$key] = stripslashes($value);
				$value = stripslashes($value);
			}
			$value = urlencode($value);
			$req .= "&$key=$value";
			$valuear[$key][] = $value;
		}

		$msg  = $valuear;


		$file = $fnumber."_input_".date("Ymdhi").".log";
		$path = "data/tmp/";
		if(!($fp = fopen($path.$file, "a+"))) return 0;

		ob_start();
		print_r($msg);
		$ob_msg = ob_get_contents();
		ob_clean();

		if(fwrite($fp, " ".$ob_msg."\n") === FALSE)
		{
			fclose($fp);
			return 0;
		}
		fclose($fp);
		return 1;
	}

	function getCallbackURI($callbackuri=''){

		if($callbackuri){
			//지정된 콜백 도메인이 있을 때
			$_domain = $callbackuri;

		}else{

			//한글 도메인일경우 Punycode 변환
			if(preg_match('/[^\x00-\x7f]/',$this->arrSystem['domain'])){
				$this->ci->load->library('punycode');
				$this->arrSystem['domain']	= $this->ci->punycode->encodeHostName($this->arrSystem['domain']);
			}

			$_domain	= $this->arrSystem['domain'];
			if(!$_domain) $_domain = $this->arrSystem['subDomain'];

		}

		//모바일기기 접근 또는 모바일 도메인 접근 시
		if($this->_is_mobile_agent || preg_match("/^m\./",$_SERVER['HTTP_HOST'])) $_is_mobile = "m."; else $_is_mobile = '';

		## 등록된 쇼핑몰 도메인에서 host 만 가져오기
		$_url		= parse_url($_domain);
		$domain		= ($_url['host'])? $_url['host']:$_url['path'];

		$callbackuri = get_connet_protocol().($_is_mobile.$domain);

		return $callbackuri;
	}

	/**
	@ instagram api start
	------------------------------------------------------------
	**/

		/**
		@ instagram login
		**/
		function instagramloginurl($mtype, $mform = 'login') {

			$this->ci->load->helper('readurl');

			$this->arrSns['callbackurl_i'] = $this->getCallbackURI();
			$this->arrSns['callbackurl_i'] .= '/sns_process/instagramuserck';

			$get_request_token_url = "https://api.instagram.com/oauth/authorize";

			$this->client_id		= $this->arrSns['key_i'];
			$this->client_secret	= $this->arrSns['secret_i'];

			$mt			= microtime();
			$rand		= mt_rand();
			$state		= md5( $mt . $rand );

			## www로 접근시 사용
			$this->ci->session->set_userdata("http_host",$_SERVER['HTTP_HOST']);

			$this->ci->session->set_userdata("instagram_state",$state);
			## 지정된 파라메타 값 외에 리턴 안해줌.
			$this->ci->session->set_userdata("mtype",$mtype);

			$_SESSION['http_host']		= $_SERVER['HTTP_HOST'];
			$_SESSION['instagram_state']	= $state;
			$_SESSION['mtype']			= $mtype;
			$_SESSION['instagram_callbackurl']	= $this->arrSns['callbackurl_i'];

			$authloginckurl = $get_request_token_url . "?response_type=code&client_id=" . $this->client_id ."&state=" . $state."&redirect_uri=" . urlencode($this->arrSns['callbackurl_i']);

			return $authloginckurl;
		}

		/**
		@ instagram token
		**/
		function instagramaccesstoken($mtype, $mform = 'login') {

			$this->ci->load->helper('readurl');
			$this->user_profile = null;
			$return_value = '';

			$code	= $_GET['code'];	//
			$state	= $_GET['state'];	//세션확인용 값

			$sess_instagram_state = ($this->ci->session->userdata('instagram_state'))? $this->ci->session->userdata('instagram_state'):$_SESSION['instagram_state'];

			## 세션확인 :: 세션이 동일하지 않을 시 초기화 시킴.
			if( $sess_instagram_state != $state ) {
				$unsetuserdata = array('instagram_state'=>'','mtype'=>'');
				$this->ci->session->unset_userdata($unsetuserdata);
				$this->user_profile = array("error"=>"session_error");
			}else{
				$this->client_id		= $this->arrSns['key_i'];
				$this->client_secret	= $this->arrSns['secret_i'];

				## accesstoken 유효시간 동안은 그대로 이용.
				if($this->ci->session->userdata("instagram_token_time") >= strtotime(date("Y-m-d H:i:s")) && $this->ci->session->userdata("instagram_access_token") ){
					$res['error']			= '';
					$res['access_token']	= $this->ci->session->userdata("instagram_access_token");
				}else{
					## accesstoken 받아오기
					$accurl = "https://api.instagram.com/oauth/access_token";
					$args	= array();
					$args	= array(
								'grant_type' =>'authorization_code',
								'client_id' => $this->client_id,
								'client_secret' => $this->client_secret,
								'code' => $code,
								'state' => $state,
								'redirect_uri' => $_SESSION['instagram_callbackurl']
						);
					$res	= $this->getinstagramtoken($accurl,$args);
				}
				##---------------------------------------------------------------------------
				## AccessToken 발급 오류시
				if($res['error_message']){
					$unsetuserdata = array('instagram_state'=>'','mtype'=>'');
					$this->ci->session->unset_userdata($unsetuserdata);
					$this->user_profile = array("error"=>$res['error_message']);
				}else{
					## accesstoken session 생성
					$this->ci->session->set_userdata("instagram_access_token",$res['access_token']);
					$_SESSION['instagram_access_token'] = $res['access_token'];
					$mktime = strtotime(date("Y-m-d H:i:s")) + ($res['expires_in']-3500);
					$this->ci->session->set_userdata("instagram_token_time",$mktime);

					$this->user_profile = $_SESSION['instagram_access_token'];
					## 사용자 정보 가져오기
					$data	= $this->convertObjectToArray($res['user']);

					if($data['id']){
						$ituser = array();
						$ituser['nickname']		= $data['username'];
						$ituser['name']			= $data['full_name'];
						$ituser['id']			= $data['id'];		//Client ID별 회원고유 id
						$this->ci->session->set_userdata(array('ituser'=>$ituser));
						$_SESSION['ituser']		= $ituser;
						$this->user_profile		= $ituser;
					}else{
						//회원정보 가져오기 실패하였습니다
						$unsetuserdata = array('instagram_state'=>'','instagram_access_token'=>'','mtype'=>'');
						$this->ci->session->unset_userdata($unsetuserdata);
					}
				}
			}

			return $this->user_profile;
		}

		/* instagram 로그인 엑세스토큰 */
		function getinstagramtoken($url,$args){

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $args);
			$data = curl_exec($ch);

			$res = json_decode($data);
			if((object)$res){
				$res = (array)$res;
			}

			return $res ;
		}

		/**
		@ instagram token
		**/
		function instagramaccount($mtype, $mform = 'login') {

			$sess_ituser= ($this->ci->session->userdata('ituser'))? $this->ci->session->userdata('ituser'):$_SESSION['ituser'];
			$this->user_profile = $sess_ituser;
			return $this->user_profile;
		}

		/**
		@ instagram token
		**/


		#### [공통] ####
		function joinform_usesns() {
			$this->ci->load->helper('common');
			$joinform = ($this->ci->joinform)?$this->ci->joinform:config_load('joinform');

			if(!trim($this->arrSns['key_k'])){ $joinform['use_k'] = ""; }

			$use_sns = array();

			if($joinform['use_k']) {
				//카카오싱크 연동 시 키값 변경 (아이콘 이미지 변경 노출)
				if(isKakaoSyncUse()) {
					$use_sns['kakaosync']	= array('nm'=>'카카오','cd'=>'kksync');
				} else {
					$use_sns['kakao']		= array('nm'=>'카카오','cd'=>'kk');
				}
			}

			if($joinform['use_f']) $use_sns['facebook']		= array('nm'=>'페이스북','cd'=>'fb');
			if($joinform['use_t']) $use_sns['twitter']		= array('nm'=>'트위터','cd'=>'tw');
			if($joinform['use_n']) $use_sns['naver']		= array('nm'=>'네이버','cd'=>'nv');

			//			if($joinform['use_i']) $use_sns['instagram']	= array('nm'=>'인스타그램','cd'=>'it');
			if($joinform['use_a']){
				$use_sns['apple']		= array('nm'=>'애플','cd'=>'ap');

				// 애플 로그인 사용 시 인증 URL 생성 :: 2020-02-28 pjw
				$apple_authurl = $this->apple_cert_url();
				$this->ci->template->assign('apple_authurl', $apple_authurl);
			}

			$joinform['use_sns'] = $use_sns;

			return $joinform;
		}

		// 애플 인증파일 업로드 함수 :: 2020-02-27 pjw
		function apple_cert_upload($tmp_file_name='', $keyid){

			if(empty($tmp_file_name)) return false;

			$result			= true;							// 결과값
			$msg			= '';							// 결과메세지
			$target_dir		= ROOTPATH.'.well-known/';		// 업로드 될 경로
			$target_file	= 'AuthKey_'.$keyid.'.p8';
			$tmp_dir		= ROOTPATH.'data/tmp/';			// 임시로 저장 된 경로

			// 임시경로에 파일 존재여부 검사
			if(file_exists($tmp_dir.$tmp_file_name)){

				// 기존 업로드 된 파일이 있는경우 삭제처리
				if(file_exists($target_dir.$target_file)) unlink($target_dir.$target_file);

				// 임시경로에서 업로드 경로로 이동
				if(!copy($tmp_dir.$tmp_file_name, $target_dir.$target_file)){
					$msg	= '인증파일 업로드 처리가 실패 하였습니다.';
					$result = false;
				}

				// 임시파일 삭제처리
				unlink($tmp_dir.$tmp_file_name);

			}else{
				$msg	= '업로드 된 파일이 없습니다.';
				$result = false;
			}

			// 결과값 리턴
			return array(
				'result'	=> $result,
				'msg'		=> $msg,
				'filename'	=> $target_file
			);
		}

		// 애플 키파일 읽기 함수 :: 2020-03-09 pjw
		function apple_keyfile_value($tmp_file_name=''){

			if(empty($tmp_file_name)) return false;

			$result			= true;							// 결과값
			$msg			= '';							// 결과메세지
			$tmp_dir		= ROOTPATH.'data/tmp/';			// 임시로 저장 된 경로

			// 임시경로에 파일 존재여부 검사
			if(file_exists($tmp_dir.$tmp_file_name)){

				// 키 파일 내용 읽기
				$private_key = file_get_contents($tmp_dir.$tmp_file_name);

				// 임시파일 삭제처리
				unlink($tmp_dir.$tmp_file_name);

			}else{
				$msg	= '업로드 된 파일이 없습니다.';
				$result = false;
			}

			// 결과값 리턴
			return array(
				'result'		=> $result,
				'msg'			=> $msg,
				'private_key'	=> $private_key
			);
		}

		// 애플 인증 URL 생성 함수 :: 2020-02-28 pjw
		function apple_cert_url(){
			$snssocial = ($this->arrSns) ? $this->arrSns : config_load('snssocial');

			// 애플 연동 사용 중인 경우에만 실행
			if($snssocial['use_a']){
				$apple_byte		= get_random_string(5);
				$apple_status	= bin2hex($apple_byte);
				$this->ci->session->set_userdata('apple_state', $apple_status);
				$apple_authurl		= 'https://appleid.apple.com/auth/authorize?'.http_build_query(array(
				  'response_type'	=> 'code id_token',
				  'response_mode'	=> 'form_post',
				  'client_id'		=> $snssocial['clientid_a'],
				  'redirect_uri'	=> $this->apple_redirect_url(),
				  'state'			=> $apple_status,
				  'scope'			=> 'name email',
				  'data'			=> 'applelogin'
				));

				return $apple_authurl;
			}else{
				return null;
			}
		}

		// 애플 연동 후 데이터 유효성 검사 :: 2020-03-05 pjw
		function apple_cert_verify($response){

			// 결과값, 응답데이터 변수 설정
			$result				= array();
			$response_code		= $response['code'];
			$response_state		= $response['state'];
			$response_id_token	= $response['id_token'];
			$response_user		= $response['user'];
			$response_error		= $response['error'];

			// 정상 연동 되었는지 여부 검사
			if(!empty($response_code)){

				// 세션 체크로 정상 접근인지 확인
				if($this->ci->session->userdata('apple_state') != $response_state){
					$result['result']	= false;
					$result['msg']		= '세션 값이 만료되었습니다.';
				}

				// 액세스 토큰 가져오기
				$apple_token = $this->apple_get_access_token($response_code);

				if(isset($apple_token)){

					// 데이터가 있는 경우 json 디코딩
					$apple_token = json_decode($apple_token, true);

					// 에러여부 확인
					if($apple_token['error'] || !$apple_token['access_token']){
						$result['result']	= false;
						$result['msg']		= '인증에 실패하였습니다. 설정값을 확인해 주세요. '.$apple_token['error'];
					}else{

						// 토큰 데이터
						$apple_access_token		= $apple_token['access_token'];
						$apple_refresh_token	= $apple_token['refresh_token'];
						$apple_id_token			= explode('.', $apple_token['id_token']);
						$apple_public_key		= json_decode(base64_decode($apple_id_token[0]), true);
						$apple_authinfo			= json_decode(base64_decode($apple_id_token[1]), true);
						$apple_user				= json_decode($response_user, true);

						// 결과값 세팅
						$apple_data = array(
							'apple_access_token'	=> $apple_access_token,
							'apple_refresh_token'	=> $apple_refresh_token,
							'apple_name'			=> $apple_user['name']['firstName'].$apple_user['name']['middleName'].$apple_user['name']['lastName'],
							'apple_email'			=> $apple_authinfo['email'],
							'apple_userid'			=> $apple_authinfo['sub']
						);

						// 연동 성공
						$result['data']		= $apple_data;
						$result['result']	= true;
						$result['msg']		= '애플로그인 인증성공';
					}

				}else{
					$result['result']	= false;
					$result['msg']		= '연동 실패하였습니다. 설정값을 확인해 주세요.';
				}

			}else{

				// code 값이 없는 경우 연동 실패
				$result['result']	= false;
				$result['msg']		= 'code 값이 없습니다.';
			}

			return $result;
		}

		// 애플 access token 발급 :: 2020-03-09 pjw
		function apple_get_access_token($code){
			// 애플 설정 정보 가져옴
			$snssocial = ($this->arrSns) ? $this->arrSns : config_load('snssocial');

			// 토큰 발급시 필요한 정보 세팅
			$privateKey			= $snssocial['private_key_a'];
			$kid				= $snssocial['key_a'];
			$iss				= $snssocial['team_a'];
			$client_swa_id		= $snssocial['clientid_a'];
			$redirect_url		= $this->apple_redirect_url();
			$signed_jwt			= $this->apple_generate_secret($kid, $iss, $client_swa_id, $privateKey);
			$header				= array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json', 'User-Agent: curl');
			$data				= array(
									'client_id'		=> $client_swa_id,
									'client_secret' => $signed_jwt,
									'code'			=> $code,
									'grant_type'	=> 'authorization_code',
									'redirect_url'	=> $redirect_url
								);
			// 발급 요청
			$this->ci->load->helper('readurl');
			$token = readurl('https://appleid.apple.com/auth/token', $data, false, 3, $header);
			return $token;
		}


		// 애플 redirect url 생성 :: 2020-03-19 pjw
		function apple_redirect_url(){
			return 'https://'.$_SERVER['HTTP_HOST'].'/sns_process/applecertificate';
		}

		// 애플 client secret 생성 :: 2020-03-06 pjw
		function apple_generate_secret($kid, $iss, $sub, $key) {

			// 사인키 생성
			$header = array(
				'alg' => "ES256",
				'kid' => $kid
			);
			$body = array(
				'iss' => $iss,
				'iat' => time(),
				'exp' => time() + 3600,
				'aud' => "https://appleid.apple.com",
				'sub' => $sub
			);

			$privKey = openssl_pkey_get_private($key);
			if (!$privKey){
				echo 'pkey error';
				exit;
			}

			$payload	= $this->jwt_encode(json_encode($header)).'.'.$this->jwt_encode(json_encode($body));
			$signature	= '';
			$success	= openssl_sign($payload, $signature, $privKey, OPENSSL_ALGO_SHA256);

			if (!$success){
				echo 'sign error';
				exit;
			}

			$raw_signature = $this->fromDER($signature, 64);
			$client_secret = $payload.'.'.$this->jwt_encode($raw_signature);

			return $client_secret;
		}

		// jwt 인코딩 함수 : 2020-03-06 pjw
		function jwt_encode($data) {
			$encoded = strtr(base64_encode($data), '+/', '-_');
			return rtrim($encoded, '=');
		}

		/**
		 * @param string $der
		 * @param int    $partLength
		 *
		 * @return string
		 */
		protected function fromDER($der, $partLength)
		{
			$hex = unpack('H*', $der);
			$hex = $hex[1];
			if ('30' !== mb_substr($hex, 0, 2, '8bit')) return '';

			if ('81' === mb_substr($hex, 2, 2, '8bit')) $hex = mb_substr($hex, 6, null, '8bit');
			else										$hex = mb_substr($hex, 4, null, '8bit');

			if ('02' !== mb_substr($hex, 0, 2, '8bit'))	return '';

			$Rl		= hexdec(mb_substr($hex, 2, 2, '8bit'));
			$R		= $this->retrievePositiveInteger(mb_substr($hex, 4, $Rl * 2, '8bit'));
			$R		= str_pad($R, $partLength, '0', STR_PAD_LEFT);
			$hex	= mb_substr($hex, 4 + $Rl * 2, null, '8bit');

			if ('02' !== mb_substr($hex, 0, 2, '8bit')) return '';

			$Sl		= hexdec(mb_substr($hex, 2, 2, '8bit'));
			$S		= $this->retrievePositiveInteger(mb_substr($hex, 4, $Sl * 2, '8bit'));
			$S		= str_pad($S, $partLength, '0', STR_PAD_LEFT);

			return pack('H*', $R.$S);
		}

		/**
		 * @param string $data
		 *
		 * @return string
		 */
		protected function retrievePositiveInteger($data)
		{
			while ('00' === mb_substr($data, 0, 2, '8bit') && mb_substr($data, 2, 2, '8bit') > '7f') {
				$data = mb_substr($data, 2, null, '8bit');
			}
			return $data;
		}
}
?>