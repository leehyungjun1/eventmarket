<?php
/**
 * @author pjm
 * @version 1.0.0
 * @license copyright by GABIA_pjm
 * @since 14. 7. 22
 * @project name : personal reserve
 */

	function curation_menu(){
		
		$personalUse	= config_load('personal_use');

		### 큐레이션 서비스 제한
		if(serviceLimit('H_FR')){
			$timesaleArr = array();
		}else{
			$timesaleArr = array('name'=>'personal_timesale'	,'title'=>'단독 상품 이벤트 종료','etc'=>'단독 상품 이벤트');
		}

		### 큐레이션 발송 구분
		$loop	= array(
					array('name'=>'personal_coupon'		,'title'=>'이번 주 쿠폰','etc'=>'쿠폰 만료 주 월요일',),
					array('name'=>'personal_emoney'		,'title'=>'다음 달 소멸 마일리지','etc'=>'마일리지 소멸 전월','day_txt'=>'일'),
					$timesaleArr,
					array('name'=>'personal_membership'	,'title'=>'회원 등급 변경','etc'=>'등급 변경일로부터','day_txt'=>'일 후'),
					array('name'=>'personal_cart'		,'title'=>'장바구니/위시리스트 상품','etc'=>'마지막 상품을 담은 날짜','day_txt'=>'일 후'),
					array('name'=>'personal_review'		,'title'=>'상품 리뷰 혜택 안내','etc'=>'배송 완료','day_txt'=>'일 후'),
					array('name'=>'personal_birthday'	,'title'=>'생일 축하','etc'=>'생일','day_txt'=>'일 전'),
					array('name'=>'personal_anniversary','title'=>'기념일 축하','etc'=>'기념일','day_txt'=>'일 전')
				);

		foreach($loop as $k=>$v){
			if	(!$v['name']) { unset($loop[$k]); continue; }
			if	(trim($personalUse[$v['name']."_use"]) == "y"){
				$loop[$k]['use_yn'] = "<span class='blue ".$v['name']."Use'>[사용중]</span>";
			}else{
				$loop[$k]['use_yn'] = "<span class='red ".$v['name']."Use'>[미사용중]</span>";
			}
		}

		return $loop;
	}

## 개인맞춤형알림 미리보기 메뉴
	function personal_review_menu(){

		$loop = curation_menu();
		echo "<style>div#rev1{font-size:12px;padding:10px;border:2px solid #666;line-height:18px;}</style>";
		echo "<div id='rev1'><ul>";
		foreach($loop as $mn){
			$tmp = explode("_",$mn['name']);
			echo "<li><a href='".$tmp[1]."?emode=view&smode=view&logview=y'>".$mn['title']."</a></li>";
		}
		echo "</ul></div>";

	}

## 발송&리뷰 테스트
	function personal_review_msg($id,$str){

		//$str[] = "<a href='".$id."?emode=view&smode=view&logview=y&admin=y'>강제리뷰</a>";
		//$str[] = "<a href='".$id."?emode=send&smode=send&logview=y&admin=y'>강제전송</a>";

		if(is_array($str)) $str_tmp = implode("<br />",$str);

		if(!$str_tmp){ $str_tmp = "nothing ";	}

		echo "<style>div#rev2{font-size:12px;padding:15px;border:2px solid #666;margin-top:5px;}</style>";
		echo "<div id='rev2'>".$str_tmp."</div>";

	}

## 발송&리뷰 테스트
	function personal_birthday_msg($id,$str){

		//$str[] = "<a href='".$id."?emode=view&smode=view&logview=y&admin=y'>강제리뷰</a>";
		//$str[] = "<a href='".$id."?emode=send&smode=send&logview=y&admin=y'>강제전송</a>";

		if(is_array($str)) $str_tmp = implode("<br />",$str);

		if(!$str_tmp){ $str_tmp = "nothing ";	}

		echo "<style>div#rev2{font-size:12px;padding:15px;border:2px solid #666;margin-top:5px;}</style>";
		echo "<div id='rev2'>".$str_tmp."</div>";

	}

## 발송&리뷰 테스트
	function personal_anniversary_msg($id,$str){

		//$str[] = "<a href='".$id."?emode=view&smode=view&logview=y&admin=y'>강제리뷰</a>";
		//$str[] = "<a href='".$id."?emode=send&smode=send&logview=y&admin=y'>강제전송</a>";

		if(is_array($str)) $str_tmp = implode("<br />",$str);

		if(!$str_tmp){ $str_tmp = "nothing ";	}

		echo "<style>div#rev2{font-size:12px;padding:15px;border:2px solid #666;margin-top:5px;}</style>";
		echo "<div id='rev2'>".$str_tmp."</div>";

	}



## SMS 발송 미리 보기 내용 치환
	function reservation_replace($params,$str,$mode='email'){

		$CI =& get_instance();
		$sns = $CI->arrSns;

		if($mode == "sms"){
			$str = str_replace("mypage_short_url","mypage_short_url_m",$str);
		}else{
			$str = str_replace("mypage_short_url","mypage_short_url_e",$str);
		}
		$title_patterns = $title_replacements = array();
		foreach ($params as $key => $val){
			$title_patterns[]		= "/{".$key."}/";
			$title_replacements[]	= $val;
		}
		return preg_replace($title_patterns, $title_replacements,$str);

	}

## 접속환경 확인
	function access_config(){
		## 접속환경 확인(PC/Mobile)
		$petten = '/(iPhone|Android|Opera Mini|SymbianOS|Windows CE|BlackBerry|Nokia|SonyEricesson|WebOS|PalmOS)/i';
		if(preg_match($petten,$_SERVER['HTTP_USER_AGENT'])){
			$access_type = "MOBILE";
		}else{
			$access_type = "PC";
		}
		return $access_type;
	}

 /* 로그 관련 시작 */
	function setSMSLog($smode,$mdata,$msg){

		$CI =& get_instance();
		if($smode == "view"){
			$smode_txt = "미리보기";
		}elseif($smode == "send"){
			$smode_txt = "전송결과";
		}
		$send_title = "SMS ".$smode_txt." => 예약시간 : ".$CI->sms_reserve. " / 발송시간 : ".date("Y-m-d H:i:s",mktime())." / ".$mdata['user_name'] ."(".$mdata['member_seq'].") / ".$mdata['cellphone'] ." / result : ".$msg;

		return $send_title;
	}

	function setEMAILLog($smode,$mdata,$msg){

		if($emode == "view"){
			$smode_txt = "미리보기";
		}elseif($emode == "send"){
			$smode_txt = "전송결과";
		}
		$send_title = "EMAIL ".$smode_txt." => 발송시간 : ".date("Y-m-d H:i:s",mktime())." / ".$mdata['user_name'] ."(".$mdata['member_seq'].") / ".$mdata['email'] ." / result : ".$msg;

		return $send_title;
	}
	function getLog($log_str,$logview,$cron=''){

		if($logview == "y"){
			if($cron == "y"){
				foreach($log_str['email'] as $sendmsg) echo iconv("utf-8","euc-kr",$sendmsg) ."\r\n";
				foreach($log_str['sms'] as $sendmsg) echo iconv("utf-8","euc-kr",$sendmsg) ."\r\n";
			}else{
				echo "<div style='font-size:12px;'><pre>";
				foreach($log_str['email'] as $sendmsg) echo $sendmsg ."<br />";
				foreach($log_str['sms'] as $sendmsg) echo $sendmsg ."<br />";
				echo "</pre><Br /></div>";
			}
		}
	}
 /* 로그 관련 끝 */

/* 마이페이지 링크 */
	function mypage_url($inflow='',$param='',$mode='v'){
		$CI =& get_instance();
		$sns = $CI->arrSns;

		$mypage_short_url = ($CI->config_system['domain']) ? "http://".$CI->config_system['domain'] : "http://".$CI->config_system['subDomain'];

		if($inflow && $param){

			$param_req = array();
			$param['inflow'] = $inflow;
			$param['send_date'] = date("Y-m-d H:i:s",mktime());
			if(!$mode){
				$param['to_msg'] = str_replace("{mypage_short_url}","",$param['to_msg']);
				$param['to_msg'] = str_replace("{mypage_short_url_m}","",$param['to_msg']);
			}

			$reqfield = array("member_seq","inflow","send_date");
			//$reqfield = array("to_msg","to_reception","member_seq","inflow","send_date");
			foreach($param as $key=>$val){
				if(in_array($key,$reqfield)) $param_req[$key] = $val;
			}
			$param_tmp	= base64_encode(serialize($param_req));
			## 짧은 URL 사용시(문자발송일때만)
			if($sns['shorturl_use'] == "Y" && strstr($param['inflow'],"sms")){
				$mypage_short_url	= $mypage_short_url."/personal_referer/access?param=".$param_tmp;
				list($mypage_short_url2, $shorturl_result) = get_shortURL($mypage_short_url);
				## 짧은 URL 오류시 긴 URL로 대체
				if( $shorturl_result === false || (parse_url($mypage_short_url2, PHP_URL_SCHEME)!='https' && $sns['shorturl_keyType'] == 'token')){
					$mypage_short_url2 = $mypage_short_url;
				}
			}else{
				$mypage_short_url2 = $mypage_short_url."/personal_referer/access?param=".$param_tmp;
			}
			$mypage_short_url = $mypage_short_url2;
		}
		return $mypage_short_url;
	}

	/*
	* 짧은 url 통신 후 db 연결이 끊기는 현상이 발생하여 db 재연결 후 sms 발송
	*/
	function reservationSendSMS($commonSmsData) {
		if(count($commonSmsData) > 0){	
			$CI =& get_instance();

			$CI->db->close();
			$CI->db->initialize();
			$CI->db->reconnect();
			commonSendSMS($commonSmsData);
		}
	}

	function sendCheckSMS() {
		// 보안키 및 발신번호 체크
		$auth = config_load('master','sms_auth'); // 보안키
		$sms_api_key = $auth['sms_auth'];
		$send_phone = getSmsSendInfo(); // 발신번호인증
		// 보안키 및 발신번호 미인증시 처리
		if($sms_api_key && $send_phone){
			return true;
		}else{
			return false;
		}
	}

## 이번주에 만료되는 쿠폰 안내
	function send_reserv_coupon($emode='', $smode='',$logview='n'){

		$CI =& get_instance();
		$CI->load->model('couponmodel');
		$CI->load->model('membermodel');
		$CI->load->helper('coupon');

		$remind_send = true;
		$email_personal		= config_load('email_personal');
		$sms_personal		= config_load('sms_personal');

		## Email/SMS 발송 여부
		$email_send_use		= $email_personal['personal_coupon_user_yn'];
		$sms_send_use		= $sms_personal['personal_coupon_user_yn'];

		$email_title		= $email_personal['personal_coupon_title'];
		# SMS 매주월요일 사용자 지정 시간에 발송
		$reserve_time		= $sms_personal['personal_coupon_time'];
		if($reserve_time){
			$CI->sms_reserve= date("Y-m-d {$reserve_time}:00:00", mktime());
		}else{
			$remind_send = false;
			return "SMS Reservation set-up";
		}

		if($CI->sms_reserve <= date("Y-m-d H:i:s", mktime())){
			$remind_send = false;
			return "SMS Time over";
		}

		if(sendCheckSMS() === false) {
			$remind_send = false;
			return "SMS Not Setting";
		}

		if($remind_send){

			$today				= date('Y-m-d H:i:s',mktime());
			$sunday				= 7 - date("w",mktime());
			$week_last_day		= date("Y-m-d 23:59:59", strtotime($today.' +'.$sunday.' days'));

			$today				= substr($today,0,10);
			$week_last_day		= substr($week_last_day,0,10);

			## 이번주 만료 쿠폰 소지 회원 리스트
			$param				= array();
			$param['startdt']	= $today;
			$param['enddt']		= $week_last_day;
			$member_list		= $CI->membermodel->get_member_receive_coupon($param);

			$personal_mode		= "personal_coupon";
			$file_path			= "../../data/email/".get_lang(true)."/".$personal_mode.".html";
			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";

			$send_title = array();
			## 회원 발송

			if($member_list){
				foreach($member_list as $mdata){

					## SMS 만료되는 쿠폰 안내 
					if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

						## Email 발송 : 즉시 발송 => SMS 발송시에만
						if($email_send_use == "Y" && $mdata['mailing'] == "y" && $mdata['email']){

							$personal_param		= array();
							$myLinkparams_email = array();
							$email_title		= $email_personal[$personal_mode.'_title'];

							## Email 발송시 사용할 치환변수
							$personal_param['kind']					= "coupon";
							$personal_param['shopName']				= $CI->config_basic['shopName'];
							$personal_param['member_seq']			= $mdata['member_seq'];
							$personal_param['username']				= $mdata['user_name'];
							$personal_param['coupon_count']			= $mdata['coupon_count'];

							## email (발송 제목 포함)
							$myLinkparams_email						= $personal_param;
							$myLinkparams_email['to_msg']			= reservation_replace($personal_param,$email_title,'email');
							$myLinkparams_email['to_reception']		= $mdata['email'];
							$personal_param['mypage_short_url_e']	= mypage_url('email_coupon',$myLinkparams_email);	//이메일용

							$CI->template->assign('username',$mdata['user_name']);

							# 보유쿠폰 리스트
							unset($sc);
							### SEARCH
							$sc					= "";
							$sc['orderby']		= 'download_seq';
							$sc['sort']			= 'desc';
							$sc['page']			= 0;
							$sc['perpage']		= 1000;
							$sc['member_seq']	= $mdata['member_seq'];
							$sc['today']		= $today;
							$sc['use_status']	= "unused";
							$sc['issue_date']['sdate']	= $today;
							$sc['issue_date']['edate']	= $week_last_day;			//이번주 마지막날 구하기.

							$data = $CI->couponmodel->my_download_list($sc,'all');

							unset($dataloop);
							$dataloop = array();
							foreach($data['result'] as $datarow){
					
								$coupons = $CI->couponmodel->get_coupon($datarow['coupon_seq']);
								$datarow = downloadlist_tab1($today, $datarow, $coupons);
								if(!$datarow['limit_goods_price']){
									$datarow['limit_price'] = "없음";
								}else{
									$datarow['limit_price'] =  get_currency_price($datarow['limit_goods_price'],2)." ".getAlert("os191")."";
								}

								$dataloop[] = $datarow;
							}//

							## 마이페이지 링크
							$CI->template->assign('mypage_short_url',$personal_param['mypage_short_url_e']);

							## 쿠폰리스트 
							if(isset($dataloop)) $CI->template->assign('loop',$dataloop);
							$CI->template->define(array('tpl'=>$file_path));

							unset($res); $res = "";

							## page view
							if($emode == "view"){

								$CI->template->print_("tpl");
								$email_msg				= $myLinkparams_email['to_msg'];
								$send_title['email'][]	= setEMAILLog($emode,$mdata,$res." :: ".$email_msg);

							## mail send
							}elseif($emode == "send"){

								$res					= sendMail($mdata['email'], $personal_mode, '', $personal_param);
								$send_title['email'][]	= setEMAILLog($emode,$mdata,$res);

							}//mail send

						}else{
							$send_title['email'][]	= setEMAILLog($emode,$mdata,"수신거부 또는 수신이메일 누락");
						}

						$personal_param		= array();
						$myLinkparams_sms	= array();
						$sms_title			= $sms_personal[$personal_mode.'_title'];

						## Email/SMS 발송시 사용할 치환변수
						$personal_param['kind']					= "coupon";
						$personal_param['shopName']				= $CI->config_basic['shopName'];
						$personal_param['member_seq']			= $mdata['member_seq'];
						$personal_param['username']				= $mdata['user_name'];
						$personal_param['coupon_count']			= $mdata['coupon_count'];
						## sms link url (발송 제목 포함)
						$myLinkparams_sms						= $personal_param;
						$myLinkparams_sms['to_msg']				= reservation_replace($personal_param,$sms_title,'sms');
						$myLinkparams_sms['to_reception']		= $mdata['cellphone'];
						$personal_param['mypage_short_url_m']	= mypage_url('sms_coupon',$myLinkparams_sms);	//모바일용

						unset($res); $res = "";

						if($smode == "view"){
							$sms_msg							= $myLinkparams_sms['to_msg'];
							$send_title['sms'][]				= setSMSLog($smode,$mdata,$sms_msg);
						}elseif($smode == "send"){
							if($CI->sms_reserve){
								//$res							= sendSMS($mdata['cellphone'], $personal_mode, '', $personal_param );
								$commonSmsData[$personal_mode]['phone'][] = $mdata['cellphone'];
								$commonSmsData[$personal_mode]['params'][] = $personal_param;
								$send_title['sms'][]			= setSMSLog($smode,$mdata,$res);
							}
						}
					}else{
						$send_title['sms'][] = setSMSLog($smode,$mdata,"수신거부 또는 수신번호 누락");
					}
				}
				
				reservationSendSMS($commonSmsData);

				getLog($send_title,$logview); //로그보기
				return "SMS : ".count($send_title['sms'])." / Email : ".count($send_title['email'])." send";

			}else{
				return "Members nothing";
			}
		}

	}


## 다음달에 소멸되는 마일리지 안내
	function send_reserv_emoney($emode='', $smode='',$logview='n'){
	
		$CI =& get_instance();
		$CI->load->model('membermodel');

		$remind_send = true;
		$email_personal		= config_load('email_personal');
		$sms_personal		= config_load('sms_personal');

		## Email/SMS 발송 여부
		$email_send_use		= $email_personal['personal_emoney_user_yn'];
		$sms_send_use		= $sms_personal['personal_emoney_user_yn'];

		# SMS 사용자 지정 시간에 발송 예약
		$reserve_day		= $sms_personal['personal_emoney_day'];
		$reserve_time		= $sms_personal['personal_emoney_time'];
		if($reserve_day && $reserve_time){
			$CI->sms_reserve	= date("Y-m-{$reserve_day} {$reserve_time}:00:00", mktime());
		}else{
			$remind_send = false;
			return "SMS Reservation set-up";
		}

		if($CI->sms_reserve <= date("Y-m-d H:i:s", mktime())){
			$remind_send = false;
			return "SMS Time over";
		}

		if(sendCheckSMS() === false) {
			$remind_send = false;
			return "SMS Not Setting";
		}

		if($remind_send){

			$today				 = date("Y-m-d",mktime());

			## 다음달 소멸예정 마일리지 보유 회원 리스트
			$param				= array();
			$param['startdt']	= date("Y-m-01", strtotime(date("Y-m-d").' +1 months'));
			$param['enddt']		= date("Y-m-t", strtotime(date("Y-m-d").' +1 months'));
			$member_list		= $CI->membermodel->get_member_receive_emoney($param);

			$personal_mode		= "personal_emoney";
			$file_path	= "../../data/email/".get_lang(true)."/".$personal_mode.".html";
			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";

			$send_title = array();
			## 회원 발송
			if($member_list){
				foreach($member_list as $mdata){

					## SMS 다음달 소멸되는 마일리지 안내 
					if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

						## 소멸예정 총 마일리지
						if($mdata['mileage_rest'] > 0){
							$mdata['mileage_rest'] = get_currency_price($mdata['mileage_rest']);
						}

						## Email 발송 => SMS 발송시에만
						if($email_send_use == "Y" && $mdata['mailing'] == "y" && $mdata['email']){

							$personal_param		= array();
							$myLinkparams_email = array();
							$email_title		= $email_personal[$personal_mode.'_title'];

							## Email 발송시 사용할 치환변수
							$personal_param['kind']					= "emoney";
							$personal_param['shopName']				= $CI->config_basic['shopName'];
							$personal_param['member_seq']			= $mdata['member_seq'];
							$personal_param['username']				= $mdata['user_name'];
							$personal_param['mileage_rest']			= $mdata['mileage_rest'];
							$personal_param['limit_date']			= $mdata['limit_date'];
							## email (발송 제목 포함)
							$myLinkparams_email						= $personal_param;
							$myLinkparams_email['to_msg']			= reservation_replace($personal_param,$email_title,'email');
							$myLinkparams_email['to_reception']		= $mdata['email'];
							$personal_param['mypage_short_url_e']	= mypage_url('email_emoney',$myLinkparams_email);	//이메일용

							$personal_param['today']				= $today;

							$CI->template->assign('username',$mdata['user_name']);
							$CI->template->assign('mileage_rest',$mdata['mileage_rest']);
							$CI->template->assign('limit_date',$mdata['limit_date']);
							$CI->template->assign('today',$today);
							$CI->template->define(array('tpl'=>$file_path));

							unset($res); $res = "";

							## 마이페이지 링크
							$CI->template->assign('mypage_short_url',$personal_param['mypage_short_url_e']);

							## page view
							if($emode == "view"){

								$CI->template->print_("tpl");
								$email_msg				= $myLinkparams_email['to_msg'];
								$send_title['email'][]	= setEMAILLog($emode,$mdata,$res." :: ".$email_msg);

							## mail send
							}elseif($emode == "send"){

								$res					= sendMail($mdata['email'], $personal_mode, '', $personal_param);
								$send_title['email'][]	= setEMAILLog($emode,$mdata,$res);

							}//mail send

						}else{
							$send_title['email'][]	= setEMAILLog($emode,$mdata,"수신거부 또는 수신이메일 누락");
						}

						$personal_param		= array();
						$myLinkparams_sms	= array();
						$sms_title			= $sms_personal[$personal_mode.'_title'];

						## sms 발송시 사용할 치환변수
						$personal_param['kind']					= "emoney";
						$personal_param['shopName']				= $CI->config_basic['shopName'];
						$personal_param['member_seq']			= $mdata['member_seq'];
						$personal_param['username']				= $mdata['user_name'];
						$personal_param['mileage_rest']			= $mdata['mileage_rest'];
						$personal_param['limit_date']			= $mdata['limit_date'];
						## sms link url (발송 제목 포함)
						$myLinkparams_sms						= $personal_param;
						$myLinkparams_sms['to_msg']				= reservation_replace($personal_param,$sms_title,'sms');
						$myLinkparams_sms['to_reception']		= $mdata['cellphone'];
						$personal_param['mypage_short_url_m']	= mypage_url('sms_emoney',$myLinkparams_sms);	//모바일용

						unset($res); $res = "";

						if($smode == "view"){

							$sms_msg							= $myLinkparams_sms['to_msg'];
							$send_title['sms'][]				= setSMSLog($smode,$mdata,$sms_msg);

						}elseif($smode == "send"){

							if($CI->sms_reserve){
								//$res							= sendSMS($mdata['cellphone'], $personal_mode, '', $personal_param );
								$commonSmsData[$personal_mode]['phone'][] = $mdata['cellphone'];
								$commonSmsData[$personal_mode]['params'][] = $personal_param;

								$send_title['sms'][]			= setSMSLog($smode,$mdata,$res);
							}
						}
					}else{
						$send_title['sms'][] = setSMSLog($smode,$mdata,"수신거부 또는 수신번호 누락");
					}

				}

				reservationSendSMS($commonSmsData);

				getLog($send_title,$logview); //로그보기
				return "SMS : ".count($send_title['sms'])." / Email : ".count($send_title['email'])." send";

			} else{
				return "Members nothing";
			}

		}
	}


## 멤버쉽 혜택 안내
	function send_reserv_membership($emode='', $smode='',$logview='n'){

		$CI =& get_instance();
		$CI->load->model('membermodel');

		$remind_send = true;
		$email_personal	= config_load('email_personal');
		$sms_personal	= config_load('sms_personal');

		## Email/SMS 발송 여부
		$email_send_use	= $email_personal['personal_membership_user_yn'];
		$sms_send_use	= $sms_personal['personal_membership_user_yn'];

		## 예약일(등급조정일 +O일)
		$reserve_day	= $sms_personal['personal_membership_day'];
		$reserve_time	= $sms_personal['personal_membership_time'];

		## 수신동의 회원 리스트
		$member_list = $CI->membermodel->get_member_receive_membership($reserve_day);

		$today			= date("Y년 m월 d일",mktime());

		## SMS 수신동의 고객 지정시간에 발송
		if($reserve_day && $reserve_time){
			$reserve_date		= date("Y-m-d {$reserve_time}:00:00");
			$CI->sms_reserve	=  date("Y-m-d {$reserve_time}:00:00");
		}else{
			$remind_send = false;
			return "SMS Reservation set-up";
		}

		if($CI->sms_reserve <= date("Y-m-d H:i:s", mktime())){
			$remind_send = false;
			return "SMS Time over";
		}

		if(sendCheckSMS() === false) {
			$remind_send = false;
			return "SMS Not Setting";
		}

		if($remind_send){

			## 등급조정 설정정보
			$grade_clone	= config_load('grade_clone');
			$chg_day		= $grade_clone['chg_day'];

			$chg_term		= $grade_clone['chg_term'];
			$chk_term		= $grade_clone['chk_term'];
			$keep_term		= $grade_clone['keep_term'];
			$month			= $grade_clone['start_month'] ? $grade_clone['start_month'] : '1';

			## 등급기간/산출기간 뽑아오기
			if($chg_day){

				$data = $CI->membermodel->calculate_date($month,$chg_day,$chg_term,$chk_term,$keep_term);

				## 등급기간
				foreach($data['chg_text'] as $k=>$chkdt){
					if($chkdt < date("Y-m-d") && (!$chkdt_stand || $chkdt_stand < $chkdt)){
						$chgdt_stand	= $chkdt;		//기준일
						$chgdt_k		= $k;
					}
				}
				if($data['keep_text_start'][$chgdt_k]){
					$keep_s_tmp = explode("-",$data['keep_text_start'][$chgdt_k]);
					$keep_s		= $keep_s_tmp[0]."년 ".$keep_s_tmp[1]."월 ".$keep_s_tmp[2]."일";
				}
				if($data['keep_text_end'][$chgdt_k]){
					$keep_e_tmp = explode("-",$data['keep_text_end'][$chgdt_k]);
					$keep_e		= $keep_e_tmp[0]."년 ".$keep_e_tmp[1]."월 ".$keep_e_tmp[2]."일";
				}
				$grade_date = $keep_s." ~ ".$keep_e;
				## 산출기간
				$grade_chkdt = $grade_clone['chk_term'];
				
				$CI->template->assign('grade_date',$grade_date);
				$CI->template->assign('grade_chkdt',$grade_chkdt);

			}

			## 등급리스트
			$glist_tmp	= $CI->membermodel->member_sale_group_list();
			## 기본등급 혜택 값 구해오기
			$grp_sale	= $CI->membermodel->get_member_sale_default();
			foreach($glist_tmp as $glist){

				if($glist['group_seq'] > 0){

					$loop['grade_seq']	= $glist['group_seq'];		//등급 일련번호
					$loop['grade_name'] = $glist['group_name'];		//등급명

					if(trim($glist['myicon']) && file_exists(ROOTPATH."/data/icon/mypage/".$glist['myicon'])){
						$loop['grade_icon']	= '<div style="height:60px;width:60px;padding:0px;margin:0px;vertical-align:middle;display:table-cell;text-align:center;"><img src="/data/icon/mypage/'.$glist['myicon'].'"></div>';			//등급 아이콘
					}else{
						$loop['grade_icon'] = '<div style="height:60px;width:60px;padding:0px;margin:0px;color:#999999;font-size:11px;font-family:Verdana;vertical-align:middle;display:table-cell;text-align:center;">NOIMG</div>';
					}

					## 등급에 해당하는 혜택 조건 
					$sale_detail		= $grp_sale[$glist['group_seq']];
					## 혜택 조건
						if($sale_detail["sale_use"] == "Y"){
							$loop["sale_use"]				= get_currency_price($sale_detail["sale_limit_price"],2)." 이상 구매";
						}else{
							$loop["sale_use"]				= "조건없음";
						}
					## 혜택 금액
						if($sale_detail["sale_price"]){

							if($sale_detail["sale_price_type"] == "PER"){
								$loop["sale_price"] = " ".number_format($sale_detail["sale_price"])."% 할인";
							}else{
								$loop["sale_price"] = " ".get_currency_price($sale_detail["sale_price"],2)." 할인";
							}
						}else{
							$loop["sale_price"] = '';
						}

					##  추가옵션 혜택 금액
					/*
						if($sale_detail["sale_option_price"]){
							$loop["sale_option_price"] 		= number_format($sale_detail["sale_option_price"]);

							if($sale_detail["sale_option_price_type"] == "WON"){
								$loop["sale_option_price"]		.= "원 할인";
							}else{
								$loop["sale_option_price"]		.= "% 할인";
							}
						}else{
							$loop["sale_option_price"] = '';
						}
						*/

					## 추가 적립
						$loop["point_use"]				= $sale_detail["point_use"];

						if($sale_detail["point_use"] == "Y"){
							$loop["point_use"]				= get_currency_price($sale_detail["point_limit_price"],2)." 이상 구매";
						}else{
							$loop["point_use"]				= "조건없음";
						}

						if($sale_detail["point_price"]){

							if($sale_detail["point_price_type"] == "PER"){
								$loop["point_price"] = " ".number_format($sale_detail["point_price"])."% 적립";
							}else{
								$loop["point_price"] = " ".get_currency_price($sale_detail["point_price"],2)." 적립";
							}
						}else{
							$loop["point_price"] = "";
						}

						if($sale_detail["reserve_price"]){
							if($sale_detail["reserve_price_type"] == "PER"){
								$loop["reserve_price"] = number_format($sale_detail["reserve_price"])."% 적립";
							}else{
								$loop["reserve_price"] = get_currency_price($sale_detail["reserve_price"],2)." 적립";
							}
						}else{
							$loop["reserve_price"] = "";
						}

						//$loop["sale_price"]		= ($loop["sale_price"])? "추가 ".$loop["sale_price"]:"";
						//$loop["point_price"]	= ($loop["point_price"])? "포인트 ".$loop["point_price"]:"";
						//$loop["reserve_price"]	= ($loop["reserve_price"])? "마일리지 ".$loop["reserve_price"]:"";

					$grade_list[] = $loop;
				}
			}

			$personal_mode	= "personal_membership";
			$file_path		= "../../data/email/".get_lang(true)."/".$personal_mode.".html";
			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";

			if($member_list){
				foreach($member_list as $mdata){

					$personal_param['mypage_short_url_m']	= mypage_url('sms_membership',$mdata['member_seq']);	//모바일용

					## sms 다음달 소멸되는 마일리지 안내 
					if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

						## Email 만료되는 쿠폰 안내 => SMS 발송시에만
						if($email_send_use == "Y" && $mdata['mailing'] == "y" && $mdata['email']){

							$personal_param		= array();
							$myLinkparams_email = array();
							$email_title		= $email_personal[$personal_mode.'_title'];

							$personal_param['kind']					= "membership";
							$personal_param['shopName']				= $CI->config_basic['shopName'];
							$personal_param['member_seq']			= $mdata['member_seq'];
							$personal_param['username']				= $mdata['user_name'];
							$personal_param['userlevel']			= $mdata['group_name'];
							
							## email (발송 제목 포함)
							$myLinkparams_email						= $personal_param;
							$myLinkparams_email['to_msg']			= reservation_replace($personal_param,$email_title,'email');
							$myLinkparams_email['to_reception']		= $mdata['email'];
							$personal_param['mypage_short_url_e']	= mypage_url('email_membership',$myLinkparams_email);	//이메일용

							if(trim($mdata['myicon']) && file_exists(ROOTPATH."/data/icon/mypage/".$mdata['myicon'])){
								$mdata['grade_icon']	= '<div style="height:60px;width:60px;padding:0px;margin:0px;vertical-align:middle;display:table-cell;text-align:center;"><img src="/data/icon/mypage/'.$mdata['myicon'].'"></div>';			//등급 아이콘
							}else{
								$mdata['grade_icon'] = '<div style="height:60px;width:60px;padding:0px;margin:0px;color:#999999;font-size:11px;font-family:Verdana;vertical-align:middle;display:table-cell;text-align:center;">NOIMG</div>';
							}

							$CI->template->assign('username',$mdata['user_name']);
							$CI->template->assign('grade_name',$mdata['group_name']);
							$CI->template->assign('grade_icon',$mdata['grade_icon']);
							$CI->template->assign('grade_list',$grade_list);

							$CI->template->define(array('tpl'=>$file_path));

							unset($res); $res = "";

							## 마이페이지 링크
							$CI->template->assign('mypage_short_url',$personal_param['mypage_short_url_e']);

							## page view
							if($emode == "view"){
								$CI->template->print_("tpl");
								$email_msg				= $myLinkparams_email['to_msg'];
								$send_title['email'][]	= setEMAILLog($emode,$mdata,$res." :: ".$email_msg);
							## mail send
							}elseif($emode == "send"){
								$res					= sendMail($mdata['email'], $personal_mode, '', $personal_param);
								$send_title['email'][]	= setEMAILLog($emode,$mdata,$res);
							}//mail send
						}else{
							$send_title['email'][]	= setEMAILLog($emode,$mdata,"수신거부 또는 수신이메일 누락");
						}

						$personal_param		= array();
						$myLinkparams_sms = array();
						$sms_title			= $sms_personal[$personal_mode.'_title'];

						$personal_param['kind']					= "membership";
						$personal_param['shopName']				= $CI->config_basic['shopName'];
						$personal_param['member_seq']			= $mdata['member_seq'];
						$personal_param['username']				= $mdata['user_name'];
						$personal_param['userlevel']			= $mdata['group_name'];
						
						## sms (발송 제목 포함)
						$myLinkparams_sms						= $personal_param;
						$myLinkparams_sms['to_msg']				= reservation_replace($personal_param,$sms_title,'sms');
						$myLinkparams_sms['to_reception']		= $mdata['cellphone'];
						$personal_param['mypage_short_url_m']	= mypage_url('sms_membership',$myLinkparams_sms);	//이메일용

						unset($res); $res = "";

						if($smode == "view"){

							$sms_msg							= $myLinkparams_sms['to_msg'];
							$send_title['sms'][]				= setSMSLog($smode,$mdata,$sms_msg);

						}elseif($smode == "send"){

							if($CI->sms_reserve){
								//$res							= sendSMS($mdata['cellphone'], $personal_mode, '', $personal_param );
								$commonSmsData[$personal_mode]['phone'][] = $mdata['cellphone'];
								$commonSmsData[$personal_mode]['params'][] = $personal_param;

								$send_title['sms'][]			= setSMSLog($smode,$mdata,$res);
							}
						}
					}else{
						$send_title['sms'][] = setSMSLog($smode,$mdata,"수신거부 또는 수신번호 누락");
					}

				}

				reservationSendSMS($commonSmsData);

				getLog($send_title,$logview); //로그보기
				return "SMS : ".count($send_title['sms'])." / Email : ".count($send_title['email'])." send";

			}else{
				return "Members nothing";
			}
		}

	}


## 장바구니/위시리스트 상품 알림
	function send_reserv_cart($emode='', $smode='',$logview='n',$cron='n'){

		$CI =& get_instance();
		$CI->load->model('membermodel');
		$CI->load->model("cartmodel");
		$CI->load->model('wishmodel');
		$CI->load->model('goodsmodel');
		$remind_send = true;
		$email_personal		= config_load('email_personal');
		$sms_personal		= config_load('sms_personal');

		## Email/SMS 발송 여부
		$email_send_use		= $email_personal['personal_cart_user_yn'];
		$sms_send_use		= $sms_personal['personal_cart_user_yn'];

		# SMS 사용자 지정 시간에 발송 예약
		$reserve_day		= $sms_personal['personal_cart_day'];
		$reserve_time		= $sms_personal['personal_cart_time'];
		if($reserve_time){
			$CI->sms_reserve= date("Y-m-d {$reserve_time}:00:00", mktime());
		}else{
			$remind_send = false;
			return "SMS Reservation set-up";
		}

		if($CI->sms_reserve <= date("Y-m-d H:i:s", mktime())){
			$remind_send = false;
			return "SMS Time over";
		}

		if(sendCheckSMS() === false) {
			$remind_send = false;
			return "SMS Not Setting";
		}

		if($remind_send){
			## 상품 글자수 제한 설정 값
			$goodsname_length		= config_load('personal_goods_limit');

			$member_list		= $CI->membermodel->get_member_receive_cart($reserve_day);

			$personal_mode		= "personal_cart";
			$file_path			= "../../data/email/".get_lang(true)."/".$personal_mode.".html";
			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";

			$CI->template->assign('shopdomain',mypage_url());
			## 회원 발송
			if($member_list){
				foreach($member_list as $mdata){

					## 장바구니/위시리스트 내용 가져오기
					if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

						$goods_name_tmp = '';
						$goods_name		= '';

						## 장바구니
						$cart_loop = array();
						if($mdata['cart_cnt'] > 0){

							$param				 = array();
							$param['member_seq'] = $mdata['member_seq'];

							$cart = $CI->cartmodel->catalog($param);

							foreach($cart['list'] as $key => $data){
								## 상품이미지 이미지호스팅 등의 외부이미지구문추가 @2016-06-22
								if( trim($data['image']) && (@file_exists(ROOTPATH.$data['image']) || strpos($data['image'], 'http') !== FALSE) ){
									$data['goods_image']	= '<div style="height:80px;width:80px;padding:0px;margin:0px;vertical-align:middle;display:table-cell;text-align:center;"><img src="'.$data['image'].'" style="height:60px;width:60px;border:0px;padding:10px;"></div>';			//등급 아이콘
								}else{
									$data['goods_image'] = '<div style="height:60px;width:60px;padding:10px;margin:0px;color:#999999;font-size:11px;font-family:Verdana;vertical-align:middle;display:table-cell;text-align:center;">NOIMG</div>';
								}
								## 옵션정보 정리
								$option = array();
								if($data['option1']){
									$option[] = ($data['title1'])? $data['title1']." : ".$data['option1'] : $data['option1'];
								}
								if($data['option2']){
									$option[] = ($data['title2'])? $data['title2']." : ".$data['option2'] : $data['option2'];
								}
								if($data['option3']){
									$option[] = ($data['title3'])? $data['title3']." : ".$data['option3'] : $data['option3'];
								}
								if($data['option4']){
									$option[] = ($data['title4'])? $data['title4']." : ".$data['option4'] : $data['option4'];
								}
								if($data['option5']){
									$option[] = ($data['title5'])? $data['title5']." : ".$data['option5'] : $data['option5'];
								}
								$data['options']	= implode(" / ",$option);
								$data['price']		= get_currency_price($data['price']);

								if(!$goods_name_tmp) $goods_name_tmp = $data['goods_name'];
								$cart_loop[]		= $data;
							}
						}

						$cart_real_cnt = count($cart_loop);
						$CI->template->assign('cart_count',$cart_real_cnt);

						## 위시리스트
						$wish_loop = array();
						if($mdata['wish_cnt'] > 0){

							$wish		= $CI->wishmodel->get_list( $mdata['member_seq'],'list2');
							//$wish_cnt	= count($wish['record']);
							foreach($wish['record'] as $key => $goods){

								// 등급혜택가격,정보 포함
								$goods['sale_price'] = $CI->goodsmodel->get_sale_price($goods['goods_seq'], $goods['price'], $goods['r_category'] , $goods['sale_seq']);
								$goods['string_price'] = get_string_price($goods);
								$goods['string_price_use'] = 0;
								if($goods['string_price']!='') $goods['string_price_use'] = 1;

								## 상품이미지 이미지호스팅 등의 외부이미지구문추가 @2016-06-22
								if( trim($goods['image']) && (@file_exists(ROOTPATH.$goods['image']) || strpos($goods['image'], 'http') !== FALSE) ) {
									$goods['goods_image']	= '<div style="height:80px;width:80px;padding:0px;margin:0px;vertical-align:middle;display:table-cell;text-align:center;"><img src="'.$goods['image'].'" width=60 style="width:60px;padding:10px;border:0px;"></div>';			//등급 아이콘
								}else{
									$goods['goods_image'] = '<div style="height:60px;width:60px;padding:10px;margin:0px;color:#999999;font-size:11px;font-family:Verdana;vertical-align:middle;display:table-cell;text-align:center;">NOIMG</div>';
								}

								$goods['price'] = get_currency_price($goods['price']);

								$wish_loop[] = $goods;
								if(!$goods_name_tmp) $goods_name_tmp = $goods['goods_name'];
							}
						}
						$wish_real_cnt = count($wish_loop);
						$CI->template->assign('wish_count',$wish_real_cnt);

						if($goods_name_tmp) $goods_name_tmp = htmlspecialchars(strip_tags($goods_name_tmp));
						if(($cart_real_cnt+$wish_real_cnt) > 1){
							## 상품명 길이 제한
							if($goodsname_length['go_item_use'] == 'y'){
								$goods_name = getstrcut($goods_name_tmp,$goodsname_length['go_item_limit']);
							}else{
								$goods_name = $goods_name_tmp;
							}
							$goods_name = $goods_name." 외 ".(($cart_real_cnt+$wish_real_cnt)-1)."개";
						}else{
							$goods_name = $goods_name_tmp;
						}

					}

					## Email 장바구니/위시리스트 상품 알림 
					if($cart_real_cnt > 0 || $wish_real_cnt > 0){

						## 멤버쉽 혜택 안내 SMS
						if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

							## sms 발송시에만 메일도 발송.
							unset($res); $res = "";

							$personal_param		= array();
							$myLinkparams_sms	= array();
							$sms_title			= $sms_personal[$personal_mode.'_title'];

							$personal_param['kind']					= "cart";
							$personal_param['shopName']				= $CI->config_basic['shopName'];
							$personal_param['member_seq']			= $mdata['member_seq'];
							$personal_param['username']				= $mdata['user_name'];
							$personal_param['go_item']				= $goods_name;
							## sms (발송 제목 포함)
							$myLinkparams_sms						= $personal_param;
							$myLinkparams_sms['to_msg']				= reservation_replace($personal_param,$sms_title,'sms');
							$myLinkparams_sms['to_reception']		= $mdata['sms'];
							$personal_param['mypage_short_url_m']	= mypage_url('sms_cart',$myLinkparams_sms);	//이메일용

							if($smode == "view"){
								$sms_msg							= $myLinkparams_sms['to_msg'];
								$send_title['sms'][]				= setSMSLog($smode,$mdata,$sms_msg);
							}elseif($smode == "send"){
								if($CI->sms_reserve){
									//$res		= sendSMS($mdata['cellphone'], $personal_mode, '', $personal_param );
									$commonSmsData[$personal_mode]['phone'][] = $mdata['cellphone'];
									$commonSmsData[$personal_mode]['params'][] = $personal_param;
									$send_title['sms'][]			= setSMSLog($smode,$mdata,$res);
								}
							}

							## sms 발송시에만 메일도 발송.
							if($email_send_use == "Y" && $mdata['mailing'] == "y" && $mdata['email']){

								$personal_param		= array();
								$myLinkparams_email = array();
								$email_title		= $email_personal[$personal_mode.'_title'];

								$personal_param['kind']			= "cart";
								$personal_param['shopName']		= $CI->config_basic['shopName'];
								$personal_param['member_seq']	= $mdata['member_seq'];
								$personal_param['username']		= $mdata['user_name'];
								$personal_param['go_item']		= $goods_name;
								## email (발송 제목 포함)
								$myLinkparams_email						= $personal_param;
								$myLinkparams_email['to_msg']			= reservation_replace($personal_param,$email_title,'email');
								$myLinkparams_email['to_reception']		= $mdata['email'];
								$personal_param['mypage_short_url_e']	= mypage_url('email_cart',$myLinkparams_email);	//이메일용
								$link_email_wish						= mypage_url('email_wish',$myLinkparams_email);	//이메일용

								$CI->template->assign('username',$mdata['user_name']);
								$CI->template->assign('cartlist',$cart_loop);
								$CI->template->assign('wishlist',$wish_loop);

								$CI->template->define(array('tpl'=>$file_path));

								## 마이페이지 링크
								$CI->template->assign('mypage_short_url_cart',$personal_param['mypage_short_url_e']);
								$CI->template->assign('mypage_short_url_wish',$link_email_wish);

								unset($res); $res = "";

								## page view
								if($emode == "view"){
									$CI->template->print_("tpl");
									$email_msg						= $myLinkparams_email['to_msg'];
									$send_title['email'][]			= setEMAILLog($emode,$mdata,$res." :: ".$email_msg);

								## mail send
								}elseif($emode == "send"){
									$res							= sendMail($mdata['email'], $personal_mode, '', $personal_param);
									$send_title['email'][]			= setEMAILLog($emode,$mdata,$res);
								}

							}else{
								$send_title['email'][]	= setEMAILLog($emode,$mdata,"수신거부 또는 수신이메일 누락");
							}

						}else{
							$send_title['sms'][] = setSMSLog($smode,$mdata,"수신거부 또는 수신번호 누락");
						}
					}else{
						$send_title['email'][]	= setEMAILLog($emode,$mdata,"실제 장바구니 또는 위시리스트 상품 없음");
						$send_title['sms'][]	= setSMSLog($smode,$mdata,"실제 장바구니 또는 위시리스트 상품 없음");
					}

				}

				reservationSendSMS($commonSmsData);

				getLog($send_title,$logview,$cron); //로그보기
				return "SMS : ".count($send_title['sms'])." / Email : ".count($send_title['email'])." send";

			}else{
				return "Members nothing";
			}
		}
	}


# 장바구니/위시리스트에 담긴 판매종료(timesale) 상품 안내, 당일/하루전 안내
	function send_reserv_timesale($emode='', $smode='',$logview='n'){

		$CI =& get_instance();
		$CI->load->model("cartmodel");
		$CI->load->model('wishmodel');
		$CI->load->model('goodsmodel');
		$CI->load->model('membermodel');

		$remind_send = true;
		$email_personal		= config_load('email_personal');
		$sms_personal		= config_load('sms_personal');

		## Email/SMS 발송 여부
		$email_send_use		= $email_personal['personal_timesale_user_yn'];
		$sms_send_use		= $sms_personal['personal_timesale_user_yn'];

		## 상품 글자수 제한 설정 값
		$goodsname_length	= config_load('personal_goods_limit');

		# SMS 사용자 지정 시간에 발송 예약
		$reserve_day		= $sms_personal['personal_timesale_day'];
		$reserve_time		= $sms_personal['personal_timesale_time'];
		if($reserve_day && $reserve_time){
			$CI->sms_reserve	= date("Y-m-d {$reserve_time}:00:00", mktime());
		}else{
			$remind_send = false;
			return "SMS Reservation set-up";
		}

		if($CI->sms_reserve <= date("Y-m-d H:i:s", mktime())){
			$remind_send = false;
			return "SMS Time over";
		}

		if(sendCheckSMS() === false) {
			$remind_send = false;
			return "SMS Not Setting";
		}

		if($remind_send){
			## 장바구니/위시리스트에 담긴 타임세일상품(단독이벤트)
			if($reserve_day == "before"){		//하루전(내일 종료되는 타임세일 상품 안내)
				$cartdt['lastday'] = date("Y-m-d",mktime()+(60*60*24));
				$cartdt['appweek'] = date("w",mktime()+(60*60*24));
			}elseif($reserve_day == "lastday"){	//오늘 종료되는 타임세일 상품 안내
				$cartdt['lastday'] = date("Y-m-d");
				$cartdt['appweek'] = date("w",mktime());
			}

			$member_list = $CI->membermodel->get_member_receive_timesale($cartdt);

			$CI->template->assign('shopdomain',mypage_url());

			$personal_mode = "personal_timesale";
			$file_path	= "../../data/email/".get_lang(true)."/".$personal_mode.".html";
			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";

			$yoil		= array("일","월","화","수","목","금","토");
			$lastdate	= explode(" ",$cartdt['lastday']);

			if($member_list){
				foreach($member_list as $mdata){

					## 장바구니/위시리스트 내용 가져오기
					if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']) {

						$goods_name_tmp = '';

						## 장바구니
						$loop = array();

						if($mdata['cart_cnt'] > 0){
							$param				 = array();
							$param['member_seq'] = $mdata['member_seq'];
							$param['today']		 = $cartdt['lastday'];
							$param['todayw']	 = $cartdt['appweek'];
							$cart		= $CI->cartmodel->catalog($param);
							foreach($cart['list'] as $key => $data){
								$todaytime = date('Hi');
								# 단독이벤트 추출
								$solo_query = "select
												e.title,e.start_date,e.end_date,e.event_type
												,e.app_week,e.app_start_time,e.app_end_time
											from 
												fm_event_benefits b left join fm_event e on b.event_seq=e.event_seq
											where
												e.event_seq = b.event_seq 
												and e.goods_rule='goods_view' and e.display='y' 
												and e.event_type = 'solo'
												and (case when e.app_week = '' or e.app_week = '0' or  e.app_week is null  then
														(case when date_format(e.end_date,'%Y-%m-%d') = date_format('".$cartdt['lastday']." 00:00:00','%Y-%m-%d') then 1 else 0 end)
													else 
														(case when e.start_date <= '".$cartdt['lastday']." 23:59:59' and e.end_date >= '".$cartdt['lastday']." 00:00:00' and e.app_week like '%".$cartdt['appweek']."%' then 1 else 0 end)
													end
												) = 1
												and	(select count(*) from fm_event_choice where event_benefits_seq = b.event_benefits_seq and choice_type = 'goods' and goods_seq = '".$data['goods_seq']."') > 0
										";
								$query = $CI->db->query($solo_query);
								$event = $query->row_array();

								# 단독 이벤트(timesale) 상품만
								if($event){

									$end_date	= explode(" ",$event['end_date']);

									## 타임세일 마감시간
									$end_date1	= explode("-",$end_date[0]);
									$end_date2	= explode(":",$end_date[1]);
									$data['event_end_date'] = $end_date1[1]."월 ".$end_date1[2]."일 ".$end_date2[0]."시 ".$end_date2[1]."분";

									## 타임세일 종료일자가 특정 요일인 경우		
									if($event['app_week'] && $event['app_end_time'] != '0000' && $event['app_end_time'] !=''){

										$app_week_title = array();
										for($i=0;$i<strlen($event['app_week']);$i++) {
											$app_week = substr($event['app_week'],$i,1);
											if($yoil[$app_week])$app_week_title[] = $yoil[$app_week];
										}
										$event['app_week_title']		= implode(', ',$app_week_title);
										$event['app_end_time_title']	= substr($event['app_end_time'],0,2)."시 ".substr($event['app_end_time'],2,2)."분";

										$data['event_end_date'] .= "(단, ".$event['app_week_title']." / ".$event['app_end_time_title'].")";
									}

									## 상품이미지 이미지호스팅 등의 외부이미지구문추가 @2016-06-22
									if( trim($data['image']) && (@file_exists(ROOTPATH.$data['image']) || strpos($data['image'], 'http') !== FALSE) ){
										$data['goods_image']	= '<div style="height:80px;width:80px;padding:0px;margin:0px;vertical-align:middle;display:table-cell;text-align:center;"><img src="'.$data['image'].'" style="width:60px;border:0px;padding:10px;"></div>';
									}else{
										$data['goods_image'] = '<div style="height:60px;width:60px;padding:10px;margin:0px;color:#999999;font-size:11px;font-family:Verdana;vertical-align:middle;display:table-cell;text-align:center;">NOIMG</div>';
									}

									## 옵션정보 정리
									$option = array();
									if($data['option1']){
										$option[] = ($data['title1'])? $data['title1']." : ".$data['option1'] : $data['option1'];
									}
									if($data['option2']){
										$option[] = ($data['title2'])? $data['title2']." : ".$data['option2'] : $data['option2'];
									}
									if($data['option3']){
										$option[] = ($data['title3'])? $data['title3']." : ".$data['option3'] : $data['option3'];
									}
									if($data['option4']){
										$option[] = ($data['title4'])? $data['title4']." : ".$data['option4'] : $data['option4'];
									}
									if($data['option5']){
										$option[] = ($data['title5'])? $data['title5']." : ".$data['option5'] : $data['option5'];
									}
									$data['options'] = implode(" / ",$option);

									// 등급혜택가격,정보 포함
									$data['price']		= get_currency_price($data['price']);
									$loop[]				= $data;
									if(!$goods_name_tmp) $goods_name_tmp = $goods['goods_name'];
								}
							}
						}

						$cart_real_cnt = count($loop);
						$CI->template->assign('cart_count',$cart_real_cnt);
						$CI->template->assign('cartlist',$loop);

						## 위시리스트
						$loop = array();
						if($mdata['wish_cnt'] > 0){
							$wish		= $CI->wishmodel->get_list( $mdata['member_seq'],'list2' );
							foreach($wish['record'] as $key => $goods){

								# 단독이벤트 추출
								$solo_query = "select
												e.title,e.start_date,e.end_date,e.event_type
												,e.app_week,e.app_start_time,e.app_end_time
											from 
												fm_event_benefits b left join fm_event e on b.event_seq=e.event_seq
											where
												e.event_seq = b.event_seq 
												and e.goods_rule='goods_view' and e.display='y' 
												and e.event_type = 'solo'
												and (case when e.app_week = '' or e.app_week = '0' or  e.app_week is null  then
														(case when date_format(e.end_date,'%Y-%m-%d') = date_format('".$cartdt['lastday']." 00:00:00','%Y-%m-%d') then 1 else 0 end)
													else 
														(case when e.start_date <= '".$cartdt['lastday']." 23:59:59' and e.end_date >= '".$cartdt['lastday']." 00:00:00' and e.app_week like '%".$cartdt['appweek']."%' then 1 else 0 end)
													end
												) = 1
												and	(select count(*) from fm_event_choice where event_benefits_seq = b.event_benefits_seq and choice_type = 'goods' and goods_seq = '".$goods['goods_seq']."') > 0
										";
								$query = $CI->db->query($solo_query);
								$event = $query->row_array();

								# 단독 이벤트(timesale) 상품만
								if($event){

									$end_date	= explode(" ",$event['end_date']);

									## 타임세일 마감시간
									$end_date1	= explode("-",$end_date[0]);
									$end_date2	= explode(":",$end_date[1]);
									$goods['event_end_date'] = $end_date1[1]."월 ".$end_date1[2]."일 ".$end_date2[0]."시 ".$end_date2[1]."분";

								## 상품이미지 이미지호스팅 등의 외부이미지구문추가 @2016-06-22
								if( trim($goods['image']) && (@file_exists(ROOTPATH.$goods['image']) || strpos($goods['image'], 'http') !== FALSE) ) {
										$goods['goods_image']	= '<div style="height:80px;width:80px;padding:0px;margin:0px;vertical-align:middle;display:table-cell;text-align:center;"><img src="'.$goods['image'].'" style="width:60px;border:0px;padding:10px;"></div>';			//등급 아이콘
									}else{
										$goods['goods_image'] = '<div style="height:60px;width:60px;padding:0px;margin:0px;color:#999999;font-size:11px;font-family:Verdana;vertical-align:middle;display:table-cell;text-align:center;">NOIMG</div>';
									}

									// 등급혜택가격,정보 포함
									$goods['sale_price'] = $CI->goodsmodel->get_sale_price($goods['goods_seq'], $goods['price'], $goods['r_category'] , $goods['sale_seq']);
									$goods['price']		= get_currency_price($goods['sale_price']);

									if(!$goods_name_tmp) $goods_name_tmp = $goods['goods_name'];
									$loop[] = $goods;
								}
							}
						}

						$wish_real_cnt = count($loop);
						$CI->template->assign('wish_count',$wish_real_cnt);
						$CI->template->assign('wishlist',$loop);

						if($goods_name_tmp) $goods_name_tmp = htmlspecialchars(strip_tags($goods_name_tmp));
						if(($cart_real_cnt+$wish_real_cnt) > 1){
							## 상품명 길이 제한
							if($goodsname_length['go_item_use'] == 'y'){
								$goods_name = getstrcut($goods_name_tmp,$goodsname_length['go_item_limit']);
							}else{
								$goods_name = $goods_name_tmp;
							}
							$goods_name = $goods_name." 외 ".(($cart_real_cnt+$wish_real_cnt)-1)."개";
						}else{
							$goods_name = $goods_name_tmp;
						}

					}

					## 실제 발송할 상품이 존재하면
					if($cart_real_cnt > 0 || $wish_real_cnt > 0){

						## SMS 만료되는 쿠폰 안내 
						if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

							## SMS 발송시에만 메일도 발송
							if($email_send_use == "Y" && $mdata['mailing'] == "y" && $mdata['email']){

								$personal_param		= array();
								$myLinkparams_email = array();
								$email_title		= $email_personal[$personal_mode.'_title'];

								## 치환코드
								$personal_param = array();
								$personal_param['kind']					= "timesale";
								$personal_param['shopName']				= $CI->config_basic['shopName'];
								$personal_param['member_seq']			= $mdata['member_seq'];
								$personal_param['username']				= $mdata['user_name'];
								$personal_param['go_item']				= $goods_name;
								## email (발송 제목 포함)
								$myLinkparams_email						= $personal_param;
								$myLinkparams_email['to_msg']			= reservation_replace($personal_param,$email_title,'email');
								$myLinkparams_email['to_reception']		= $mdata['email'];
								$personal_param['mypage_short_url_e']	= mypage_url('email_timesale_cart',$myLinkparams_email);	//이메일용
								$link_email_wish						= mypage_url('email_timesale_wish',$myLinkparams_email);	//이메일용

								$CI->template->assign('username',$mdata['user_name']);
								$CI->template->define(array('tpl'=>$file_path));

								## 마이페이지 링크
								$CI->template->assign('mypage_short_url_cart',$personal_param['mypage_short_url_e']);
								$CI->template->assign('mypage_short_url_wish',$link_email_wish);

								unset($res); $res  = '';
								## page view
								if($emode == "view"){
									$CI->template->print_("tpl");
									$email_msg							= $myLinkparams_email['to_msg'];
									$send_title['email'][]				= setEMAILLog($emode,$mdata,$res." :: ".$email_msg);
								## mail send
								}elseif($emode == "send"){
									$res								= sendMail($mdata['email'], $personal_mode, '', $personal_param);
									$send_title['email'][]				= setEMAILLog($emode,$mdata,$res." :: ".$email_msg);
								}

							}else{
								$send_title['email'][]	= setEMAILLog($emode,$mdata,"수신거부 또는 수신이메일 누락");
							}
							## SMS 발송시에만 메일도 발송

							$personal_param		= array();
							$myLinkparams_sms	= array();
							$sms_title			= $sms_personal[$personal_mode.'_title'];

							## 치환코드
							$personal_param = array();
							$personal_param['kind']					= "timesale";
							$personal_param['shopName']				= $CI->config_basic['shopName'];
							$personal_param['member_seq']			= $mdata['member_seq'];
							$personal_param['username']				= $mdata['user_name'];
							$personal_param['go_item']				= $goods_name;
							## sms (발송 제목 포함)
							$myLinkparams_sms						= $personal_param;
							$myLinkparams_sms['to_msg']				= reservation_replace($personal_param,$sms_title,'sms');
							$myLinkparams_sms['to_reception']		= $mdata['cellphone'];
							$personal_param['mypage_short_url_m']	= mypage_url('sms_timesale',$myLinkparams_sms);	//sms

							unset($res); $res = "";

							if($smode == "view"){
								$sms_msg							= $myLinkparams_sms['to_msg'];
								$send_title['sms'][]				= setSMSLog($smode,$mdata,$sms_msg);
							}elseif($smode == "send"){
								if($CI->sms_reserve){
									//$res						= sendSMS($mdata['cellphone'], $personal_mode, '', $personal_param );
									$commonSmsData[$personal_mode]['phone'][] = $mdata['cellphone'];
									$commonSmsData[$personal_mode]['params'][] = $personal_param;

									$send_title['sms'][]			= setSMSLog($smode,$mdata,$res." :: ".$sms_msg);
								}
							}
						}else{
							$send_title['sms'][] = setSMSLog($smode,$mdata,"수신거부 또는 수신번호 누락");
						}

					}else{
						$send_title['email'][]	= setEMAILLog($emode,$mdata,"실제 장바구니 또는 위시리스트 상품 없음");
						$send_title['sms'][]	= setSMSLog($smode,$mdata,"실제 장바구니 또는 위시리스트 상품 없음");
					}
				}

				reservationSendSMS($commonSmsData);

				getLog($send_title,$logview); //로그보기
				return "SMS : ".count($send_title['sms'])." / Email : ".count($send_title['email'])." send";

			}else{
				return "Members nothing";
			}
		}
	}


# 상품리뷰 : 배송완료일 +{지정일수} 
	function send_reserv_review($emode='', $smode='',$logview='n',$cron=''){

		$CI =& get_instance();
		$CI->load->model("ordermodel");
		$CI->load->model('goodsmodel');
		$CI->load->model('membermodel');

		$remind_send = true;
		# SMS 사용자 지정 시간에 발송 예약
		$email_personal	= config_load('email_personal');
		$sms_personal	= config_load('sms_personal');

		## Email/SMS 발송 여부
		$email_send_use	= $email_personal['personal_review_user_yn'];
		$sms_send_use	= $sms_personal['personal_review_user_yn'];

		$reserve_day	= $sms_personal['personal_review_day'];
		$reserve_time	= $sms_personal['personal_review_time'];
		if($reserve_day && $reserve_time){
			$CI->sms_reserve	= date("Y-m-d {$reserve_time}:00:00", mktime());
		}else{
			$remind_send = false;
			return "SMS Reservation set-up";
		}

		if($CI->sms_reserve <= date("Y-m-d H:i:s", mktime())){
			$remind_send = false;
			return "SMS Time over";
		}

		if(sendCheckSMS() === false) {
			$remind_send = false;
			return "SMS Not Setting";
		}

		if($remind_send){

			## 지급 마일리지
			$reserves = ($CI->reserves)?$CI->reserves:config_load('reserve');
			if($reserves['autoemoney_review']){
				$reserves_emoney = get_currency_price($reserves['autoemoney_review']);
				$CI->template->assign('reserves_emoney',$reserves_emoney);
			}

			$member_list = $CI->membermodel->get_member_receive_review($reserve_day);

			$CI->template->assign('shopdomain',mypage_url());

			$personal_mode		= "personal_review";
			$file_path	= "../../data/email/".get_lang(true)."/".$personal_mode.".html";
			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";

			if($member_list && $reserves_emoney){
				foreach($member_list as $mdata){

					$loop = array();
					## 실제 리뷰작성 주문건 불러오기.
					if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

						$sql	= "select 
									 o.order_seq,
									 oi.goods_seq,
									 oi.goods_name,
									 ge.shipping_date,
									 ge.export_code,
									 ge.status,
									 (select image from fm_goods_image where goods_seq=oi.goods_seq and image_type='thumbView' and cut_number=1 limit 1) as image
								from 
									fm_order as o
									left join fm_order_item as oi on oi.order_seq=o.order_seq
									left join fm_goods_export as ge on ge.order_seq=o.order_seq
									left join fm_goods_export_item as gei on gei.export_code=ge.export_seq and gei.item_seq=oi.item_seq
								where 1  	
									and o.member_seq ='".$mdata['member_seq']."'
									and ge.status='75'
									and datediff(now(),ge.shipping_date)=".$reserve_day."
									and (select count(*) from fm_goods_review 
											where order_seq=o.order_seq and goods_seq=oi.goods_seq and mseq=o.member_seq)=0
								order by 
									o.order_seq asc;
								";
						$query	= $CI->db->query($sql);
						$order	= $query->result_array();

						foreach($order as $key => $data){
							## 상품이미지 이미지호스팅 등의 외부이미지구문추가 @2016-06-22
							if( trim($data['image']) && (@file_exists(ROOTPATH.$data['image']) || strpos($data['image'], 'http') !== FALSE) ) {
								$data['goods_image']	= '<div style="height:60px;width:60px;padding:0px;margin:0px;vertical-align:middle;display:table-cell;text-align:center;"><img src="'.$data['image'].'" style="height:60px;width:60px;border:0px;"></div>';			//상품이미지
							}else{
								$data['goods_image'] = '<div style="height:60px;width:60px;padding:0px;margin:0px;color:#999999;font-size:11px;font-family:Verdana;vertical-align:middle;display:table-cell;text-align:center;">NOIMG</div>';
							}

							$data_options	= $CI->ordermodel->get_option_for_order($data['order_seq']);
							$data_suboptions= $CI->ordermodel->get_suboption_for_order($data['order_seq']);

							$optnm = array();
							foreach($data_options as $opt){
								if($opt['option1']) $optnm[] = $opt['option1'];
								if($opt['option2']) $optnm[] = $opt['option2'];
								if($opt['option3']) $optnm[] = $opt['option3'];
								if($opt['option4']) $optnm[] = $opt['option4'];
								if($opt['option5']) $optnm[] = $opt['option5'];
							}

							$data['options']	= implode(", ",$optnm);
							$loop[]				= $data;

						}
					}

					if(count($loop) > 0){

						## SMS 리뷰 작성 알림
						if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

							## Email 리뷰 작성 알림 => SMS 발송시에만 
							if($email_send_use == "Y" && $mdata['mailing'] == "y" && $mdata['email']){

								$personal_param		= array();
								$myLinkparams_email = array();
								$email_title		= $email_personal[$personal_mode.'_title'];

								$personal_param['kind']			= "review";
								$personal_param['shopName']		= $CI->config_basic['shopName'];
								$personal_param['member_seq']	= $mdata['member_seq'];
								$personal_param['username']		= $mdata['user_name'];
								## email (발송 제목 포함)
								$myLinkparams_email						= $personal_param;
								$myLinkparams_email['to_msg']			= reservation_replace($personal_param,$email_title,'email');
								$myLinkparams_email['to_reception']		= $mdata['email'];
								$personal_param['mypage_short_url_e']	= mypage_url('email_review',$myLinkparams_email);	//이메일용

								$CI->template->assign('username',$mdata['user_name']);

								$order_count = number_format(count($loop));
								$CI->template->assign('order_count',$order_count);
								$CI->template->assign('orderlist',$loop);

								## 마이페이지 링크
								$CI->template->assign('mypage_short_url',$personal_param['mypage_short_url_e']);
								$CI->template->define(array('tpl'=>$file_path));
								## 보기모드

								unset($res); $res  = '';
								## page view
								if($emode == "view"){
									$CI->template->print_("tpl");
									$email_msg							= $myLinkparams_email['to_msg'];
									$send_title['email'][]				= setEMAILLog($emode,$mdata,$res." :: ".$email_msg);
								## mail send
								}elseif($emode == "send"){
									$res								= sendMail($mdata['email'], $personal_mode, '', $personal_param);
									$send_title['email'][]				= setEMAILLog($emode,$mdata,$res);
								}

							}else{
								$send_title['email'][]	= setEMAILLog($emode,$mdata,"수신거부 또는 수신이메일 누락");
							}

							$personal_param		= array();
							$myLinkparams_sms	= array();
							$sms_title			= $sms_personal[$personal_mode.'_title'];

							$personal_param['kind']					= "review";
							$personal_param['shopName']				= $CI->config_basic['shopName'];
							$personal_param['member_seq']			= $mdata['member_seq'];
							$personal_param['username']				= $mdata['user_name'];
							## sms link url (발송 제목 포함)
							$myLinkparams_sms						= $personal_param;
							$myLinkparams_sms['to_msg']				= reservation_replace($personal_param,$sms_title,'sms');
							$myLinkparams_sms['to_reception']		= $mdata['cellphone'];
							$personal_param['mypage_short_url_m']	= mypage_url('sms_review',$myLinkparams_sms);	//모바일

							unset($res); $res = "";

							if($smode == "view"){
								$sms_msg							= $myLinkparams_sms['to_msg'];
								$send_title['sms'][]				= setSMSLog($smode,$mdata,$sms_msg);
							}elseif($smode == "send"){
								if($CI->sms_reserve){
									//$res						= sendSMS($mdata['cellphone'], $personal_mode, '', $personal_param );
									$commonSmsData[$personal_mode]['phone'][] = $mdata['cellphone'];
									$commonSmsData[$personal_mode]['params'][] = $personal_param;

									$send_title['sms'][]			= setSMSLog($smode,$mdata,$res);
								}
							}
						}else{
							$send_title['sms'][] = setSMSLog($smode,$mdata,"수신거부 또는 수신번호 누락");
						}
					}else{
						$send_title['email'][]	= setEMAILLog($emode,$mdata,"리뷰를 작성할 주문건이 없음");
						$send_title['sms'][]	= setSMSLog($smode,$mdata,"리뷰를 작성할 주문건이 없음");
					}
				}

				reservationSendSMS($commonSmsData);

				getLog($send_title,$logview,$cron); //로그보기
				return "SMS : ".count($send_title['sms'])." / Email : ".count($send_title['email'])." send";

			}else{
				return "Members nothing or Emoney setting error ";
			}

		}

	}
## 생일 축하 쿠폰
	function send_reserv_birthday($emode='', $smode='',$logview='n'){
		$CI =& get_instance();
		$CI->load->model('couponmodel');
		$CI->load->model('membermodel');
		$CI->load->helper('coupon');

		$remind_send = true;
		# SMS 사용자 지정 시간에 발송 예약
		$email_personal	= config_load('email_personal');
		$sms_personal	= config_load('sms_personal');

		## Email/SMS 발송 여부
		$email_send_use	= $email_personal['personal_birthday_user_yn'];
		$sms_send_use	= $sms_personal['personal_birthday_user_yn'];

		$reserve_day	= $sms_personal['personal_birthday_day'];
		$reserve_time	= $sms_personal['personal_birthday_time'];
		
		if($reserve_day && $reserve_time){
			$CI->sms_reserve	= date("Y-m-d {$reserve_time}:00:00", mktime());
		}else{
			$remind_send = false;
			return "SMS Reservation set-up";
		}
		
		if($CI->sms_reserve <= date("Y-m-d H:i:s", mktime())){
			$remind_send = false;
			return "SMS Time over";
		}
		
		if(sendCheckSMS() === false) {
			$remind_send = false;
			return "SMS Not Setting";
		}
		
		// null 검색이 되지 않아서 조건절 변경 :: 2018-01-15 lkh
		$in_where = "";
		$coupon_group_query = "select G.group_seq from fm_coupon K LEFT JOIN fm_coupon_group G ON G.coupon_seq = K.coupon_seq where K.type = 'birthday' and K.issue_stop = '0'";
		$coupon_group_query = $CI->db->query($coupon_group_query);
		$coupon_group_result = $coupon_group_query->result_array();
		if($coupon_group_result[0]['group_seq']){
			$cg_array = "";
			foreach($coupon_group_result as $cg_data){
				$cg_array[] = $cg_data['group_seq'];
			}
			$in_where = " and C.group_seq in (".implode(",",$cg_array).")";
		}
		
		$birthdate = date("m-d",strtotime("+".$reserve_day." day"));
		$key = get_shop_key();
		$query ="select
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone,
					A.sms, A.mailing,
					A.userid, A.user_name, A.member_seq, A.birthday
				from fm_member A LEFT JOIN fm_member_group C ON A.group_seq = C.group_seq 
				where substring(A.birthday, 6, 5 ) = '".$birthdate."'".$in_where;
		$query = $CI->db->query($query);
		$result = $query->result_array();
		
		if($remind_send){

			$today				= date('Y-m-d H:i:s',mktime());
			$sunday				= 7 - date("w",mktime());
			$week_last_day		= date("Y-m-d 23:59:59", strtotime($today.' +'.$sunday.' days'));

			$today				= substr($today,0,10);
			$week_last_day		= substr($week_last_day,0,10);

			$personal_mode		= "personal_birthday";
			$file_path			= "../../data/email/".get_lang(true)."/".$personal_mode.".html";
			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";

			if($result){
				foreach($result as $mdata){
					## SMS 만료되는 쿠폰 안내 
					
					## Email 발송 : 즉시 발송 => SMS 발송시에만
					if($email_send_use == "Y" && $mdata['mailing'] == "y" && $mdata['email']){
						
						$personal_param		= array();
						$myLinkparams_email = array();
						$email_title		= $email_personal[$personal_mode.'_title'];
					
						## Email 발송시 사용할 치환변수
						$personal_param['kind']					= "birthday";
						$personal_param['shopName']				= $CI->config_basic['shopName'];
						$personal_param['member_seq']			= $mdata['member_seq'];
						$personal_param['username']				= $mdata['user_name'];
						$birthday								= date("m월 d일", strtotime($mdata['birthday']));
						$birthdaytimes							= date("Y") - date("Y", strtotime($mdata['birthday']))+1;
						$personal_param['userbirthday']			= $birthday;
						## email (발송 제목 포함)
						$myLinkparams_email						= $personal_param;
						$myLinkparams_email['to_msg']			= reservation_replace($personal_param,$email_title,'email');
						$myLinkparams_email['to_reception']		= $mdata['email'];
						$personal_param['mypage_short_url_e']	= mypage_url('email_birthday',$myLinkparams_email);	//이메일용

						$CI->template->assign('username',$mdata['user_name']);
						$CI->template->assign('birthday', $birthday);
						$CI->template->assign('birthdaytimes', $birthdaytimes);
						
						# 생일쿠폰 리스트
						unset($sc);
						### SEARCH
						
						$couponSql = "select * from fm_coupon where type = 'birthday' and issue_stop = '0'";
						$couponQuery = $CI->db->query($couponSql);
						$data = $couponQuery->result_array();
						
						unset($dataloop);
						$dataloop = array();
						
						foreach($data as $datarow){
				
							$coupons = $CI->couponmodel->get_coupon($datarow['coupon_seq']);
							
							$datarow = downloadlist_tab1($today, $datarow, $coupons);
							if(!$datarow['limit_goods_price']){
								$datarow['limit_price'] = "없음";
							}else{
								$datarow['limit_price'] = get_currency_price($datarow['limit_goods_price'],2)." ".getAlert("os191")."";
							}
							
							if($datarow['coupon_img'] == '4'){
								$datarow['coupon_back_img'] = $datarow['coupon_image4'];
							}else{
								$datarow['coupon_back_img'] = "coupon_skin_0{$datarow['coupon_img']}.gif";
							}

							$dataloop[] = $datarow;
						}//
						
						## 마이페이지 링크
						$CI->template->assign('mypage_short_url',$personal_param['mypage_short_url_e']);

						## 쿠폰리스트 
						if(isset($dataloop)) $CI->template->assign('loop',$dataloop);
						$CI->template->define(array('tpl'=>$file_path));
						
						unset($res); $res = "";
						
						## page view
						if($emode == "view"){
							
							$CI->template->print_("tpl");
							$email_msg				= $myLinkparams_email['to_msg'];
							$send_title['email'][]	= setEMAILLog($emode,$mdata,$res." :: ".$email_msg);

						## mail send
						}elseif($emode == "send"){

							$res					= sendMail($mdata['email'], $personal_mode, '', $personal_param);
							$send_title['email'][]	= setEMAILLog($emode,$mdata,$res);

						}//mail send

					}else{
						$send_title['email'][]	= setEMAILLog($emode,$mdata,"수신거부 또는 수신이메일 누락");
					}
					
					if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

						$personal_param		= array();
						$myLinkparams_sms	= array();
						$sms_title			= $sms_personal[$personal_mode.'_title'];

						## Email/SMS 발송시 사용할 치환변수
						$personal_param['kind']					= "birthday";
						$personal_param['shopName']				= $CI->config_basic['shopName'];
						$personal_param['member_seq']			= $mdata['member_seq'];
						$personal_param['username']				= $mdata['user_name'];
						$personal_param['coupon_count']			= $mdata['coupon_count'];
						$birthday								= date("m월 d일", strtotime($mdata['birthday']));
						$personal_param['userbirthday']			= $birthday;
						## sms link url (발송 제목 포함)
						$myLinkparams_sms						= $personal_param;
						$myLinkparams_sms['to_msg']				= reservation_replace($personal_param,$sms_title,'sms');
						$myLinkparams_sms['to_reception']		= $mdata['cellphone'];
						$personal_param['mypage_short_url_m']	= mypage_url('sms_birthday',$myLinkparams_sms);	//모바일용

						unset($res); $res = "";

						if($smode == "view"){
							$sms_msg							= $myLinkparams_sms['to_msg'];
							$send_title['sms'][]				= setSMSLog($smode,$mdata,$sms_msg);
						}elseif($smode == "send"){
							if($CI->sms_reserve){
								//$res							= sendSMS($mdata['cellphone'], $personal_mode, '', $personal_param );
								$commonSmsData[$personal_mode]['phone'][] = $mdata['cellphone'];
								$commonSmsData[$personal_mode]['params'][] = $personal_param;
								$send_title['sms'][]			= setSMSLog($smode,$mdata,$res);
							}
						}
					}else{
						$send_title['sms'][] = setSMSLog($smode,$mdata,"수신거부 또는 수신번호 누락");
					}	
				}
				
				reservationSendSMS($commonSmsData);
				
				getLog($send_title,$logview); //로그보기
				return "SMS : ".count($send_title['sms'])." / Email : ".count($send_title['email'])." send";

			}else{
				return "Members nothing";
			}
		}
	}

## 기념일 축하 쿠폰
	function send_reserv_anniversary($emode='', $smode='',$logview='n'){
		$CI =& get_instance();
		$CI->load->model('couponmodel');
		$CI->load->model('membermodel');
		$CI->load->helper('coupon');

		$remind_send = true;
		# SMS 사용자 지정 시간에 발송 예약
		$email_personal	= config_load('email_personal');
		$sms_personal	= config_load('sms_personal');

		## Email/SMS 발송 여부
		$email_send_use	= $email_personal['personal_anniversary_user_yn'];
		$sms_send_use	= $sms_personal['personal_anniversary_user_yn'];

		$reserve_day	= $sms_personal['personal_anniversary_day'];
		$reserve_time	= $sms_personal['personal_anniversary_time'];
		
		if($reserve_day && $reserve_time){
			$CI->sms_reserve	= date("Y-m-d {$reserve_time}:00:00", mktime());
		}else{
			$remind_send = false;
			return "SMS Reservation set-up";
		}
		
		if($CI->sms_reserve <= date("Y-m-d H:i:s", mktime())){
			$remind_send = false;
			return "SMS Time over";
		}

		if(sendCheckSMS() === false) {
			$remind_send = false;
			return "SMS Not Setting";
		}
		
		// null 검색이 되지 않아서 조건절 변경 :: 2018-01-15 lkh
		$in_where = "";
		$coupon_group_query = "select G.group_seq from fm_coupon K LEFT JOIN fm_coupon_group G ON G.coupon_seq = K.coupon_seq where K.type = 'anniversary' and K.issue_stop = '0'";
		$coupon_group_query = $CI->db->query($coupon_group_query);
		$coupon_group_result = $coupon_group_query->result_array();
		if($coupon_group_result[0]['group_seq']){
			$cg_array = "";
			foreach($coupon_group_result as $cg_data){
				$cg_array[] = $cg_data['group_seq'];
			}
			$in_where = " and C.group_seq in (".implode(",",$cg_array).")";
		}
		
		$anniversarydate = date("m-d",strtotime("+".$reserve_day." day"));
		$key = get_shop_key();
		$query ="select
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone,
					A.sms, A.mailing,
					A.userid, A.user_name, A.member_seq, A.anniversary
				from fm_member A LEFT JOIN fm_member_group C ON A.group_seq = C.group_seq 
				where A.anniversary = '".$anniversarydate."'".$in_where;				
		$query = $CI->db->query($query);
		$result = $query->result_array();
		
		if($remind_send){

			$today				= date('Y-m-d H:i:s',mktime());
			$sunday				= 7 - date("w",mktime());
			$week_last_day		= date("Y-m-d 23:59:59", strtotime($today.' +'.$sunday.' days'));

			$today				= substr($today,0,10);
			$week_last_day		= substr($week_last_day,0,10);

			$personal_mode		= "personal_anniversary";
			$file_path			= "../../data/email/".get_lang(true)."/".$personal_mode.".html";
			$CI->template->compile_dir = ROOTPATH."data/email/".get_lang(true)."/";

			if($result){
				foreach($result as $mdata){
					## SMS 만료되는 쿠폰 안내 
					
					## Email 발송 : 즉시 발송 => SMS 발송시에만
					if($email_send_use == "Y" && $mdata['mailing'] == "y" && $mdata['email']){
						
						$personal_param		= array();
						$myLinkparams_email = array();
						$email_title		= $email_personal[$personal_mode.'_title'];
					
						## Email 발송시 사용할 치환변수
						$personal_param['kind']					= "anniversary";
						$personal_param['shopName']				= $CI->config_basic['shopName'];
						$personal_param['member_seq']			= $mdata['member_seq'];
						$personal_param['username']				= $mdata['user_name'];
						$anniversary							= date("m월 d일", strtotime(date("Y")."-".$mdata['anniversary']));
						$personal_param['anniversary']			= $anniversary;

						## email (발송 제목 포함)
						$myLinkparams_email						= $personal_param;
						$myLinkparams_email['to_msg']			= reservation_replace($personal_param,$email_title,'email');
						$myLinkparams_email['to_reception']		= $mdata['email'];
						$personal_param['mypage_short_url_e']	= mypage_url('email_anniversary',$myLinkparams_email);	//이메일용

						$CI->template->assign('username',$mdata['user_name']);
						$CI->template->assign('anniversary', $anniversary);
						
						
						# 생일쿠폰 리스트
						unset($sc);
						### SEARCH
						
						$couponSql = "select * from fm_coupon where type = 'anniversary' and issue_stop = '0'";
						$couponQuery = $CI->db->query($couponSql);
						$data = $couponQuery->result_array();
						
						unset($dataloop);
						$dataloop = array();
						
						foreach($data as $datarow){
				
							$coupons = $CI->couponmodel->get_coupon($datarow['coupon_seq']);
							
							$datarow = downloadlist_tab1($today, $datarow, $coupons);
							if(!$datarow['limit_goods_price']){
								$datarow['limit_price'] = "없음";
							}else{
								$datarow['limit_price'] =  get_currency_price($datarow['limit_goods_price'],2)." ".getAlert("os191")."";
							}
							
							if($datarow['coupon_img'] == '4'){
								$datarow['coupon_back_img'] = $datarow['coupon_image4'];
							}else{
								$datarow['coupon_back_img'] = "coupon_skin_0{$datarow['coupon_img']}.gif";
							}

							$dataloop[] = $datarow;
						}//
						
						## 마이페이지 링크
						$CI->template->assign('mypage_short_url',$personal_param['mypage_short_url_e']);

						## 쿠폰리스트 
						if(isset($dataloop)) $CI->template->assign('loop',$dataloop);
						$CI->template->define(array('tpl'=>$file_path));
						
						unset($res); $res = "";
						
						## page view
						if($emode == "view"){
							
							$CI->template->print_("tpl");
							$email_msg				= $myLinkparams_email['to_msg'];
							$send_title['email'][]	= setEMAILLog($emode,$mdata,$res." :: ".$email_msg);

						## mail send
						}elseif($emode == "send"){

							$res					= sendMail($mdata['email'], $personal_mode, '', $personal_param);
							$send_title['email'][]	= setEMAILLog($emode,$mdata,$res);

						}//mail send

					}else{
						$send_title['email'][]	= setEMAILLog($emode,$mdata,"수신거부 또는 수신이메일 누락");
					}

					if($sms_send_use == "Y" && $mdata['sms'] == "y" && $mdata['cellphone']){

						$personal_param		= array();
						$myLinkparams_sms	= array();
						$sms_title			= $sms_personal[$personal_mode.'_title'];

						## Email/SMS 발송시 사용할 치환변수
						$personal_param['kind']					= "anniversary";
						$personal_param['shopName']				= $CI->config_basic['shopName'];
						$personal_param['member_seq']			= $mdata['member_seq'];
						$personal_param['username']				= $mdata['user_name'];
						$personal_param['coupon_count']			= $mdata['coupon_count'];
						$anniversary							= date("m월 d일", strtotime(date("Y")."-".$mdata['anniversary']));
						$personal_param['anniversary']			= $anniversary;
						
						## sms link url (발송 제목 포함)
						$myLinkparams_sms						= $personal_param;
						$myLinkparams_sms['to_msg']				= reservation_replace($personal_param,$sms_title,'sms');
						$myLinkparams_sms['to_reception']		= $mdata['cellphone'];
						$personal_param['mypage_short_url_m']	= mypage_url('sms_anniversary',$myLinkparams_sms);	//모바일용

						unset($res); $res = "";

						if($smode == "view"){
							$sms_msg							= $myLinkparams_sms['to_msg'];
							$send_title['sms'][]				= setSMSLog($smode,$mdata,$sms_msg);
						}elseif($smode == "send"){
							if($CI->sms_reserve){
								//$res							= sendSMS($mdata['cellphone'], $personal_mode, '', $personal_param );
								$commonSmsData[$personal_mode]['phone'][] = $mdata['cellphone'];
								$commonSmsData[$personal_mode]['params'][] = $personal_param;
								$send_title['sms'][]			= setSMSLog($smode,$mdata,$res);
							}
						}
					}else{
						$send_title['sms'][] = setSMSLog($smode,$mdata,"수신거부 또는 수신번호 누락");
					}	
				}

				reservationSendSMS($commonSmsData);

				getLog($send_title,$logview); //로그보기
				return "SMS : ".count($send_title['sms'])." / Email : ".count($send_title['email'])." send";

			}else{
				return "Members nothing";
			}
		}
	}


## 개인맞춤형안내 유입 상세로그 저장
	function curation_log($arr){

		$curation_tmp		= explode("^",$_COOKIE["curation"]);
		$curation['member_seq']		= $curation_tmp[0];
		$curation['inflow']			= $curation_tmp[1];
		$curation['curation_seq']	= $curation_tmp[2];

		if($curation['curation_seq'] && $arr['action_kind']){

			$CI			=& get_instance();
			$typetmp		= explode("_",$curation['inflow']);
			$curation_kind	= $typetmp[1];

			$sess = $CI->session->userdata('user');

			## 구매시 이중 로그 불가
			if($arr['action_kind'] == "order"){
				$sql = "select count(*) as cnt from fm_log_curation_info where curation_seq='".$curation['curation_seq']."' and action_kind='order' and member_seq='".$sess['member_seq']."' and order_seq='".$arr['order_seq']."'";
				$que = $CI->db->query($sql);
				$res = $que->row_array();
				if(!$res['cnt']){ $log_save = 'y'; }
			}else{
				$log_save = 'y';
			}

			if($log_save){
				$access_type = access_config();
				$params = array();
				$params['curation_seq']		= $curation['curation_seq'];
				$params['curation_kind']	= $curation_kind;
				$params['action_kind']		= $arr['action_kind'];
				if($arr['goods_seq'])	$params['goods_seq']	= $arr['goods_seq'];
				if($arr['order_seq'])	$params['order_seq']	= $arr['order_seq'];
				if($arr['wish_seq'])	$params['wish_seq']		= $arr['wish_seq'];
				if($arr['cart_seq'])	$params['cart_seq']		= $arr['cart_seq'];
				if($sess['member_seq'])	$params['member_seq']	= $sess['member_seq'];
				$params['access_type']	= $access_type;
				$params['referer']		= $_SERVER['HTTP_REFERER'];
				$params['register_date']= date("Y-m-d H:i:s",mktime());
				$params['userip']		= $_SERVER['REMOTE_ADDR'];
				$result					= $CI->db->insert('fm_log_curation_info', $params);
				## 유입활동 통계
				if($result){
					if($arr['action_kind'] == "login_sns") $arr['action_kind'] = "login";
					$field = $arr['action_kind']."_cnt";

					$sql = "select count(*) as cnt from fm_log_curation_info_summary where curation_seq='".$curation['curation_seq']."'";
					$que = $CI->db->query($sql);
					$res = $que->row_array();
					if($res['cnt'] > 0){
						$sql = "update fm_log_curation_info_summary set {$field}={$field}+1 where curation_seq='".$curation['curation_seq']."'";
					}else{

						//발송일 불러오기
						$senddt_sql = "select send_date from fm_log_curation where curation_seq='".$curation['curation_seq']."'";
						$senddt_que = $CI->db->query($senddt_sql);
						$senddt_res = $senddt_que->row_array();

						$sql = "insert into fm_log_curation_info_summary set {$field}=1, member_seq='".$sess['member_seq']."',curation_kind='".$curation_kind."', curation_seq='".$curation['curation_seq']."', send_date='".$senddt_res['send_date']."'";
					}
					$CI->db->query($sql);
				}
			}else{
				$result = '';
			}
			return $result;

		}else{
			return false;
		}
	}

?>