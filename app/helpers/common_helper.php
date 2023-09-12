<?php
function DBConnect(){
    $CI =& get_instance();
    include(APPPATH.'/config/database.php');
    if($db['selectonly']['hostname']){
        $CI->db->SELECTONLY = $CI->load->database('selectonly', TRUE);
    }
}
function debug($str,$result=null){
	if( $_SERVER['REMOTE_ADDR']=='61.35.204.100' || $_SERVER['REMOTE_ADDR']=='106.246.242.226' || $_SERVER['REMOTE_ADDR']=='1.237.178.27' ) {
		if($result) return true;
		debug_var($str);
	}
}

//TIME얻기
function getNowTimes()
{
	$MicroTsmp = explode(' ',microtime());
	return $MicroTsmp[0]+$MicroTsmp[1];
}

/**
* @주문 : 판매환경, 회원 : 가입환경
* @sitetype 선택값
* @formview : 0 명칭, 1 이미지명
* @form 출력방식 array 전체출력 , 그외 명칭출력 or image
**/
function sitetype($sitetype = NULL, $formview = 'image', $form = 'array'){
	$CI =& get_instance();

	$sitetypeary = array(
		"P"=>array("name"=>"PC", "image"=>"icon_list_pc.gif"),
		"M"=>array("name"=>"모바일/테블릿", "image"=>"icon_list_mobile.gif"),
		"F"=>array("name"=>"페이스북", "image"=>"icon_fb.gif"),
		"APP_ANDROID"=>array("name"=>"Android 앱", "image"=>"icon_android.gif"),
		"APP_IOS"=>array("name"=>"iOS 앱", "image"=>"icon_ios.gif")
	);

	// o2o 가입환경 추가
	$CI->load->library('o2o/o2oinitlibrary');
	$CI->o2oinitlibrary->init_sitetype($sitetypeary);

	if($form=='array'){
		return $sitetypeary;
	}else{
		if($formview == 'name'){
			return $sitetypeary[$sitetype]['name'];
		}else{
			if(is_file(ROOTPATH.'admin/skin/'.$CI->skin.'/images/common/icon/'.$sitetypeary[$sitetype]['image'])) {
				$imgtag = '<img src="../skin/'.$CI->skin.'/images/common/icon/'.$sitetypeary[$sitetype]['image'].'" alt="'.$sitetypeary[$sitetype]['name'].'" title="'.$sitetypeary[$sitetype]['name'].'" />';
			}else{
				$imgtag = $sitetypeary[$sitetype]['name'];
			}
			return $imgtag;
		}
	}
}

// 주문, 방문 시 sitetype/platform 통일 2020-05-08
function get_sitetype() {
	$CI =& get_instance();
	// 기본 PC
	$sitetype = "P";

	//판매환경
	if($CI->_is_mobile_app_agent_android) {//APP_ANDROID
		$sitetype		= 'APP_ANDROID';
	}elseif($CI->_is_mobile_app_agent_ios) {//APP_IOS
		$sitetype		= 'APP_IOS';
	}elseif($CI->_is_mobile_agent) {//mobile
		$sitetype		= 'M';
	}elseif($CI->fammerceMode) {//fammerce
		$sitetype		= 'F';
	}

	return $sitetype;
}

/**
* @주문 : 유입매체
* @marketplace 선택값
* @formview : 0 명칭, 1 이미지명
* @form 출력방식 array 전체출력 , 그외 명칭출력 or image
**/
function sitemarketplace($marketplace = NULL, $formview = 'image', $form = 'array'){
	$CI =& get_instance();

	$sitemarketplaceary = array("daum_shopping"=>array("name"=>"쇼핑하우", "image"=>"icon_search_daumshop.gif"), "daum"=>array("name"=>"다음", "image"=>"icon_search_daum.gif"), "about"=>array("name"=>"어바웃", "image"=>"icon_search_about.gif"), "nate"=>array("name"=>"바스켓", "image"=>"icon_search_nate.gif"), "naver"=>array("name"=>"지식쇼핑", "image"=>"icon_search_naver.gif"), "yahoo"=>array("name"=>"야후", "image"=>"icon_search_yahoo.gif"), "google"=>array("name"=>"구글", "image"=>"icon_search_google.gif"), "etc"=>array("name"=>"기타", "image"=>"icon_search_etc.gif"));//"NO"=>array("name"=>"", "image"=>"icon_no"),
	if($form=='array'){
		return $sitemarketplaceary;
	}else{
		if($formview == 'name'){
			return $sitemarketplaceary[$marketplace]['name'];
		}else{
			if(is_file(ROOTPATH.'admin/skin/'.$CI->skin.'/images/common/icon/'.$sitemarketplaceary[$marketplace]['image'])) {
				$imgtag = '<img src="../skin/'.$CI->skin.'/images/common/icon/'.$sitemarketplaceary[$marketplace]['image'].'" alt="'.$sitemarketplaceary[$marketplace]['name'].'" title="'.$sitemarketplaceary[$marketplace]['name'].'" />';
			}else{
				$imgtag = $sitemarketplaceary[$marketplace]['name'];
			}
			return $imgtag;
		}
	}
}

/**
* @주문 : 유입매체:네이버url 제거
* @marketplace 선택값
**/
function sitemarketplaceNaver($datas, $marketplace = 'naver'){
	$$referertag = "";
	if( $marketplace == 'naver' ) {
		//네이버검색과 동일하게 이동하기 위해 uri 제거
		if( strstr($datas['referer_domain'],'search.naver.com') && strstr($datas['referer'],'url=') ) {
			$referer_host = explode("?",$datas['referer']);//
			$referer_query = explode("&",$referer_host['1']);
			foreach($referer_query as $referer_querys) {
				if(strstr($referer_querys,"url=")) continue;
				$referer_naver .= $referer_querys."&";
			}
			$referertag = $referer_host['0']."?".$referer_naver;
		}else{
			$referertag = $datas['referer'];
		}
	}
	return $referertag;
}

//
function getSearchsitemarketplace($url)
{
	$CI =& get_instance();
	$CI->load->model('visitorlog');
	return $CI->visitorlog->get_referer_sitecd($url);
}

/**
* @회원 : 가입방법
* @rute 선택값
* @formview : 0 명칭, 1 이미지명
* @form 출력방식 array 전체출력 , 그외 명칭출력 or image
**/
function memberrute($rute = NULL, $formview = 'image', $form = 'array', $mode = 'statistic'){
	$ruteary = array(
				"none"=>array("name"=>"쇼핑몰", "image"=>"sns_home.gif"),
				"sns_f"=>array("name"=>"페이스북", "image"=>"sns_f0.gif"),
				"sns_t"=>array("name"=>"트위터", "image"=>"sns_t0.gif"),
				"sns_c"=>array("name"=>"싸이월드", "image"=>"sns_c0.gif"),
				"sns_m"=>array("name"=>"미투데이", "image"=>"sns_m0.gif"),
				"sns_n"=>array("name"=>"네이버", "image"=>"sns_n0.gif"),
				"sns_k"=>array("name"=>"카카오", "image"=>"sns_k0.gif"),
				"sns_d"=>array("name"=>"[종료]다음", "image"=>"sns_d00.gif", "helpicon"=>"<span class=\"helpicon\" title=\"다음을 통한 회원가입 및 로그인 서비스가 종료되었습니다.\"></span>"), //#27792 2019-01-18 ycg Daum 연동 서비스 종료
				"sns_i"=>array("name"=>"[종료]인스타그램", "image"=>"sns_i0.gif"),
				"sns_a"=>array("name"=>"애플", "image"=>"sns_a0.gif")
				);
	unset($ruteary['sns_m']); //2014-07-01 미투데이 서비스 종료

	if($mode == 'search') {
		// 검색은 기타 항목으로 뺌
		unset($ruteary['sns_t']);
		unset($ruteary['sns_c']);
		unset($ruteary['sns_d']);
		unset($ruteary['sns_i']);
		$ruteary['sns_etc'] = array("name"=>"기타", "etc" => "sns_t|sns_c|sns_d|sns_i");
	}

	if($form=='array'){
		return $ruteary;
	}else{
		if($formview == 'name'){
			return $ruteary[$rute][$formview];
		}else{
			if($formview == 'name'){
				return $ruteary[$rute]['name'];
			}else{
				if(is_file(ROOTPATH.'admin/skin/'.$CI->skin.'/images/sns/'.$ruteary[$rute]['image'])) {
					$imgtag = '<img src="../skin/'.$CI->skin.'/images/sns/'.$ruteary[$rute]['image'].'" alt="'.$ruteary[$rute]['name'].'"  title="'.$ruteary[$rute]['name'].'" />';
				}else{
					$imgtag = $ruteary[$rute]['name'];
				}
				return $imgtag;
			}
		}
	}
}

/* 용량포맷 */
function getSizeFormat($bytes){
	if($bytes>1024*1024) return number_format($bytes/1024/1024) . "MB";
	else if($bytes>1024) return number_format($bytes/1024) . "KB";
	else return number_format($bytes) . "Byte";
}

/* */
function str_split_arr($str, $gb, $number=0){
	$tmp_arr = explode($gb, $str);
	return $tmp_arr[$number];
}

/* 에디터 이미지 임시파일 경로 보정 */
function adjustEditorImages(&$contents, $savedir = '/data/editor/', $goodsSeq=''){
	// 임시파일업로드 ocw : 2012-07-23
	if(preg_match_all("/[\"|']?\/(data\/tmp\/[^\"']+)[\"|']?/",$contents,$matches)){

		foreach($matches[1] as $tPath){
			// 상품등록 이미지 경로 재정의 :: 2016-04-21 lwh
			if($goodsSeq){
				$arr = explode('/', $tPath);
				$fn = $arr[count($arr)-1];
				$arr2 = explode('_', $fn);
				if($arr2[0]==$goodsSeq){
					$seqLen=strlen($arr2[0]);
					$fn = substr($fn,$seqLen+1);
				}else{
					$targetdir = $savedir.$goodsSeq.'_';
				}
				/**
				 * #24306 개선 이후 파일명 앞에 temp_ 가 안붙는 문제 수정
				 * 2019-07-24
				 * @author Sunha Ryu
				 */
				$filename = pathinfo(ROOTPATH . $tPath, PATHINFO_FILENAME );
				if ( substr($filename, 0, 5) !== 'temp_' ) {
				    $regex = "/data\/tmp\//";
				} else {
				    $regex = "/data\/tmp\/temp_/";
				}

				// 에디터파일 경로
				$dPath = preg_replace($regex,$targetdir,$tPath);
			} else {
				// 에디터파일 경로
				$dPath = preg_replace("/data\/tmp\//",$savedir,$tPath);
			}

			// 파일 이동
			@rename(ROOTPATH.$tPath,ROOTPATH.$dPath);
			@chmod(ROOTPATH.$dPath,0777);

			// 정규식 문자열처리
			$tPathForReg = str_replace(array("/","."),array("\/","\."),$tPath);

			// 보정
			$contents = preg_replace("/\/".$tPathForReg."/",$dPath,$contents);
		}
	}

	return $contents;
}


/* 업로드 이미지 임시파일 경로 보정 */
function adjustUploadImage($imagePath, $savedir, $newFileName=null){

	if(empty($imagePath)) return $imagePath;

	// 임시 파일경로
	$tPath = preg_replace("/^\//","",$imagePath);
	$savedir = preg_replace("/^\//","",$savedir);

	$tFilename = basename($tPath);
	$tmp = explode(".",$tFilename);
	$tFilename = $tmp[0];

	if(file_exists(ROOTPATH.$tPath)){
		// 에디터파일 경로
		$dPath = preg_replace("/^data\/tmp\//",$savedir,$tPath);

		if($tPath!=$dPath){
			if($newFileName){
				$dPath = preg_replace("/".$tFilename."/",$newFileName,$dPath);
			}

			if(file_exists(ROOTPATH.$dPath)){
				unlink(ROOTPATH.$dPath);
			}

			// 파일 이동
			@rename(ROOTPATH.$tPath,ROOTPATH.$dPath);
			@chmod(ROOTPATH.$dPath,0777);
		}
		$imagePath = "/".$dPath;
	}else{
		$imagePath = "/".$tPath;
	}

	return $imagePath;
}

function sendDirectMail($to_email = array(), $from_email, $title='', $contents=''){
	### SEND
	$CI =& get_instance();
	$CI->load->library('email');

	foreach($to_email as $k){
		if(preg_match("/(^[_0-9a-zA-Z-]+(\.[_0-9a-zA-Z-]+)*@[0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*$)/", $k)) {
			$CI->email->from($from_email, $from_email);
			$CI->email->to($k);
			$CI->email->subject($title);
			$contents = str_replace('\\','',http_src($contents));
			$CI->email->message($contents);
			$CI->email->send();
			$CI->email->clear();
		}
	}
}

/* ini 파일 저장 */
function set_ini_file($filepath,$data,$useSection=false){
	if(file_exists($filepath)){

		$text = '';

		if($useSection){
			foreach($data as $section=>$row){
				$text .= "[".$section."]\r\n";
				foreach($row as $k=>$v){
					$text .= $k." = \"".$v."\"\r\n";
				}
				$text .= "\r\n";
			}
		}else{
			foreach($data as $k=>$v){
				$text .= $k." = \"".$v."\"\r\n";
			}
			$text .= "\r\n";
		}

		return file_put_contents($filepath,$text);
	}else return false;
}

/* 카테고리 리스트를 계층구조의 트리형태로 파싱하는 재귀함수 */
/*
	@since 2016.03.24
	@author pjw
	@description
	 카테고리 데이터가 많을 경우 재귀호출시 시간이 오래걸려서 수정 ( 최대 실행 수 : 카테고리 총개수의 카테고리 depth 제곱 )
	 총 리스트 만큼의 반복문과 각 반복문 마다 최대 3번의 반복문 실행	(최대 실행 수 : 카테고리 총 개수 * 3)
	 카테고리숨김 오류로 카테고리재귀함수 속도개선 복원 @2016-06-23 ysm
	 2016.06.29 수정 php 7 버전 대비 &변수가 문제 생겨 알고리즘 변경 ( 최대 실행 수 : 카테고리 총 개수 * 2 )
*/
function divisionCategoryDepths($category_list,$category=array(),$idx_code=''){

	if(is_array($category_list)) {
		foreach($category_list as $row){
			$code = $row['category_code'];
			$code_depth = strlen($row['category_code']) / 4;

			if($code_depth > 1){
				$tmp_category[$code_depth][substr($code, 0, strlen($code) - 4)][$code] = $row;
			}else{
				$tmp_category[$code_depth][$code] = $row;
			}
		}

		foreach($tmp_category[1] as $key => $data){
			$category[$key] = $data;
		}

		foreach($tmp_category[2] as $key => $data){
			if($category[$key])
				$category[$key]['childs'] = $data;
		}

		foreach($tmp_category[3] as $key => $data){
			$tmp_key = str_split($key, 4);
			if($category[$tmp_key[0]]['childs'][$key])
				$category[$tmp_key[0]]['childs'][$key]['childs'] = $data;
		}

		foreach($tmp_category[4] as $key => $data){
			$tmp_key = str_split($key, 4);
			if($category[$tmp_key[0]]['childs'][$tmp_key[0].$tmp_key[1]]['childs'][$key])
				$category[$tmp_key[0]]['childs'][$tmp_key[0].$tmp_key[1]]['childs'][$key]['childs'] = $data;
		}
	}

	return $category;
}

/* 지역 리스트를 계층구조의 트리형태로 파싱하는 재귀함수 */
function divisionLocationDepths($category_list,$category=array(),$idx_code=''){
	if(is_array($category_list)) foreach($category_list as $row){
		if(preg_match("/^{$idx_code}/",$row['location_code'])) {
			if(strlen($idx_code)+4 == strlen($row['location_code'])){
				$category[$row['location_code']] = $row;
				$category[$row['location_code']]['childs'] = array();
				$category[$row['location_code']]['childs'] = divisionLocationDepths($category_list,$category[$row['location_code']]['childs'],$row['location_code']);
			}
		}
	}
	return $category;
}

/* fontDecoration json 값을 기준으로 HTML Element Attribute 반환
 * 호출 :	get_node_text_attr('{"color":"#363636", "font":"dotum", "size":"9"}','css','style');
 * 		get_node_text_attr('{"color":"#363636", "font":"dotum", "size":"9"}','script','onmouseover');
 * 반환 : return '"color:#363636;font-family:dotum;font-size:9pt"';
 */
function font_decoration_attr($string, $type, $attrName=null){
	$codes = $string ? json_decode($string) : array();
	$result = "";

	if($type=='css'){
		foreach($codes as $k=>$v){
			switch($k){
				case 'color':
					$result .= "color:{$v};";
				break;
				case 'font':
					$result .= "font-family:{$v};";
				break;
				case 'size':
					$result .= "font-size:{$v}pt;";
				break;
				case 'bold':
					$result .= "font-weight:{$v};";
				break;
				case 'underline':
					$result .= "text-decoration:{$v};";
				break;
			}
		}
	}

	if($type=='script'){
		foreach($codes as $k=>$v){
			switch($k){
				case 'color':
					$result .= "this.style.color='{$v}';";
				break;
				case 'font':
					$result .= "this.style.fontFamily='{$v}';";
				break;
				case 'size':
					$result .= "this.style.fontSize='{$v}pt';";
				break;
				case 'bold':
					$result .= "this.style.fontWeight='{$v}';";
				break;
				case 'underline':
					$result .= "this.style.textDecoration='{$v}';";
				break;
			}
		}
	}

	$result = $attrName.'="'.$result.'"';

	return $result;

}


function array_notnull($arr)
{
	if (!is_array($arr)) return;
	foreach ($arr as $k=>$v) if (!$v) unset($arr[$k]);
	return $arr;
}


function remove_value_in_array($arr,$values){
	if(is_array($arr)){
		if(!is_array($values)) $values = array($values);
		$result = array();
		foreach($arr as $k=>$v){
			if(!in_array($v,$values)){
				$result[$k] = $v;
			}
		}
		return $result;
	}else{
		return $arr;
	}
}

// 문자열 길이 구하기
function strBytes_for_sms($str)
{
	$strlen_var = strlen($str);
	$d = 0;
	$euckr_str = mb_convert_encoding($str,'EUC-KR','UTF-8');
	for ($c = 0; $c < $strlen_var; ++$c) {

		$ord_var_c = ord($euckr_str{$d});
		switch (true) {
			case (($ord_var_c >= 0x20) && ($ord_var_c <= 0x7F)):
				// characters U-00000000 - U-0000007F (same as ASCII)
				$d++;
			break;

			case (($ord_var_c & 0xE0) == 0xC0):
				// characters U-00000080 - U-000007FF, mask 110XXXXX
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$d+=2;
			break;

			case (($ord_var_c & 0xF0) == 0xE0):
				// characters U-00000800 - U-0000FFFF, mask 1110XXXX
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$d+=3;
			break;

			case (($ord_var_c & 0xF8) == 0xF0):
				// characters U-00010000 - U-001FFFFF, mask 11110XXX
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$d+=4;
			break;

			case (($ord_var_c & 0xFC) == 0xF8):
				// characters U-00200000 - U-03FFFFFF, mask 111110XX
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$d+=5;
			break;

			case (($ord_var_c & 0xFE) == 0xFC):
				// characters U-04000000 - U-7FFFFFFF, mask 1111110X
				// see http://www.cl.cam.ac.uk/~mgk25/unicode.html#utf-8
				$d+=6;
				break;
				default:
				$d++;
		}

		if($d >= 80){
			$result[] = $c;
			$d = 0;
		}
	}

	if(!$result[0]) $result[0] = $strlen_var;

	return $result;
}




// utf-8 글자수 계산 함수
function strlen_utf8($str, $checkmb = false) {
	preg_match_all('/[\xE0-\xFF][\x80-\xFF]{2}|./', $str, $match); // target for BMP
	$m = $match[0];
	$mlen = count($m); // length of matched characters

	if (!$checkmb) return $mlen;
	$count=0;

	for ($i=0; $i < $mlen; $i++) {
		$count += ($checkmb && strlen($m[$i]) > 1)?2:1;
	}
	return $count;
}



function sendMail($to_email, $case, $params='', $data=array())
{

	## 개인맞춤형알림(예약 발송) 추가로 인한 메일구분
	$case_tmp = explode("_",$case);
	if($case_tmp[0] == "personal"){
		$email_mode = "email_personal";
		$gb			= "PERSONAL";
	}else{
		$email_mode = "email";
		$gb			= "AUTO";
	}

	$CI =& get_instance();

	// 오프라인 관련으로는 문자나 이메일이 발송되지 않음
	// O2O 환경 체크 false : 일반 접속, true : 오프라인 요청
	// 해당 변수를 o2o 서비스가 시작되면 갱신함
	// common_base에서 선언함
	if($CI->o2o_pos_env){
		// kakaotalk/sms_send
		$result['msg']	= 'fail';
		$result['code']	= '525';
		return $result;
	}

	$CI->config_basic	= ($CI->config_basic)?$CI->config_basic:config_load('basic');
	$CI->config_system	= ($CI->config_system)?$CI->config_system:config_load('system');
	$CI->config_email	= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);

	$CI->config_basic['domain'] = ($CI->config_system['domain']) ? "http://".$CI->config_system['domain'] : "http://".$CI->config_system['subDomain'];
	$CI->config_basic['domain'] = str_replace("http://http://", "http://", $CI->config_basic['domain']);

	$CI->load->library('email');
	$CI->email->mailtype='html';

	$from		= $CI->config_basic['companyEmail'];
	$fromname	= !$CI->config_basic['shopName'] ? $CI->config_basic['domain'] : $CI->config_basic['shopName'];

	###
	if($case == 'board_reply'){
		$mailFile = "../../data/email/".get_lang(true)."/cs.html";
	}else{
		$mailFile = "../../data/email/".get_lang(true)."/".$case.".html";
	}
	$bodyTpl	= "";
	$sendCount	= 0;
	$CI->template->assign('basic',$CI->config_basic);
	$CI->template->assign($data);
	$CI->template->define('tpl', $mailFile);
	$bodyTpl	= $CI->template->fetch('tpl');
	$body		= trim($bodyTpl);

	$body	= str_replace("http://http://", "http://", $body);

	if ($case == "marketing_agree_status" || $case == "marketing_agree" ) {
	    return $body;
	}

	###
	switch($case){
		case 'board_reply'://문의게시판외 추가게시판 답변시 (관리자무조건제외)
			if(preg_match("/(^[_0-9a-zA-Z-]+(\.[_0-9a-zA-Z-]+)*@[0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*$)/", $to_email)) {
				$arr = sendCheck('cs', 'email', 'user', $data, '', $CI->config_email);
				$arrlog	= $arr;
				if(count($arr)>1){

					$CI->email->from($from, $fromname);
					$CI->email->to($to_email);
					$CI->email->subject($arr[0]);
					$body = str_replace('\\','',http_src($body));
					$CI->email->message($body);
					$CI->email->send();
					$CI->email->clear();
				}
			}
			break;
		default://기타

			$senduse	= true;
			$adminsend	= true;		// admin 발송여부
			$providersend	= true;		// 공급처 CS

			### USER
			if(preg_match("/(^[_0-9a-zA-Z-]+(\.[_0-9a-zA-Z-]+)*@[0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*$)/", $to_email)) {
				$arr		= sendCheck($case, $email_mode, 'user', $data, '', $CI->config_email);
				$arrlog		= $arr;

				# 리마인드일 경우 제목이 없으면 발송 안함.
				if($email_mode == "email_personal"){
					if(!trim($arr[0])){
						$senduse	= false;
						$errmsg		= "ERROR : Subject 누락";
					}
					$adminsend = false;
				}

				if(count($arr)>1 && $senduse){
					$CI->email->from($from, $fromname);
					$CI->email->to($to_email);
					$CI->email->subject($arr[0]);
					$body = str_replace('\\','',http_src($body));
					$CI->email->message($body);
					$CI->email->send();
					$CI->email->clear();

					$sendCount++;
				}
			}else{
				$senduse	= false;
				$errmsg		= "메일주소오류";
			}

			### ADMIN
			$adminsend_yn = 'N';
			if($adminsend){
				$arr = sendCheck($case, 'email', 'admin', $data, '',$CI->config_email);
				$CI->config_email	= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);
				if($CI->config_email[$case."_admin_email"]) $admin_email = $CI->config_email[$case."_admin_email"];

				if(count($arr)>1){

					$CI->email->from($from, $fromname);
					$CI->email->to($admin_email);
					$CI->email->subject($arr[0]);
					$body = str_replace('\\','',http_src($body));
					$CI->email->message($body);
					$CI->email->send();
					$CI->email->clear();

					$adminsend_yn = 'Y';
					$sendCount++;
				}
			}


			### PROVIDER
			if($providersend){
				$arr = sendCheck($case, 'email', 'provider', $data, '',$CI->config_email);
				$CI->config_email	= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);

				if($CI->config_email[$case."_provider_yn"] == 'Y'){
					$provider_mail	= $data['provider_email'];

					if(count($arr)>1){
						$CI->email->from($from, $fromname);
						$CI->email->to($provider_mail);
						$CI->email->subject($arr[0]);
						$body = str_replace('\\','',http_src($body));
						$CI->email->message($body);
						$CI->email->send();
						$CI->email->clear();
						$sendCount++;
					}
				}
			}

			break;
	}

	###
	$subject		= $arrlog[0];
	if	(!$subject){
		$subject	= ($headers['Subject'])?$headers['Subject']:$CI->config_email[$case."_title"];
	}

	### LOG
	if($email_mode == "email_personal"){
		### 고객리마인드서비스용 발송로그
		if($data['kind']){
			if($senduse){
				$sql = "select seq from fm_log_curation_summary where inflow_kind='".$data['kind']."' and send_date ='".date("Y-m-d",mktime())."'";
				$query	= $CI->db->query($sql);
				$res	= $query->row_array();
				if(!$res['seq']){
					$CI->db->query("insert into fm_log_curation_summary set inflow_kind='".$data['kind']."',send_sms_total=0,send_date ='".date("Y-m-d",mktime())."'");
					$summary_seq = $CI->db->insert_id();
				}else{
					$summary_seq = $res['seq'];
				}
			}else{ $summary_seq = 0; }

			if(!$subject) $subject = "[제목없음]";

			$memo = $errmsg;
			if($memo){ $memo .= "@@".serialize($CI->config_email)."@@".serialize($arrlog); }

			unset($log_params);
			$log_params['regist_date']	= date('Y-m-d H:i:s');
			$log_params['summary_seq']	= $summary_seq;
			$log_params['sendres']		= ($senduse)? 'y':'n';				//제목없으면 false, 발송안함.
			$log_params['kind']			= $data['kind'];
			$log_params['to_email']		= $to_email;
			$log_params['member_seq']	= $data['member_seq'];
			$log_params['subject']		= $subject;
			$log_params['contents']		= $body;
			$log_params['memo']			= $memo;
			$log_data = filter_keys($log_params, $CI->db->list_fields('fm_log_curation_email'));
			$log_result =  $CI->db->insert('fm_log_curation_email', $log_data);
			### 발송 통계
			if($log_result && $senduse){
				if($summary_seq){
					$CI->db->query("update fm_log_curation_summary set send_email_total=send_email_total+1 where seq='".$summary_seq."'");
				}else{
					$CI->db->query("insert into fm_log_curation_summary set inflow_kind='".$data['kind']."',send_email_total=1,send_date ='".date("Y-m-d",mktime())."'");
				}
			}
		}
	}else{
		## 일반 메일 발송로그
		if($sendCount > 0){
			unset($params);
			$order_seq = "";
			if (!empty($data['order_seq'])) $order_seq = $data['order_seq'];
			if (!empty($data['ordno'])) $order_seq = $data['ordno'];
			$params['regdate']		= date('Y-m-d H:i:s');
			$params['gb']			= $gb;
			$params['total']		= '1';
			$params['to_email']		= $to_email;
			$params['member_seq']	= $data['member_seq'];
			$params['subject']		= $subject;
			$params['contents']		= $body;
			$params['order_seq']	= $order_seq;
			$params['memo']			= $case;
			$params_data = filter_keys($params, $CI->db->list_fields('fm_log_email'));
			$result =  $CI->db->insert('fm_log_email', $params_data);
		}

		// 관리자 메일 발송로그 추가 (가비아씨엔에스 채우형 / 2017-07-18 오후 3:08)
		if($adminsend_yn == 'Y'){
			unset($params);
			$order_seq = "";
			if (!empty($data['order_seq'])) $order_seq = $data['order_seq'];
			if (!empty($data['ordno'])) $order_seq = $data['ordno'];
			$params['regdate']		= date('Y-m-d H:i:s');
			$params['gb']			= $gb;
			$params['total']		= '1';
			$params['to_email']		= $admin_email;
			$params['member_seq']	= $data['member_seq'];
			$params['subject']		= $subject;
			$params['contents']		= $body;
			$params['order_seq']	= $order_seq;
			$params['memo']			= "admin";
			$params_data = filter_keys($params, $CI->db->list_fields('fm_log_email'));
			$result =  $CI->db->insert('fm_log_email', $params_data);
		}
	}

	return true;
}


function saveMail($to_email, $case, $params='', $data=array())
{

	## 개인맞춤형알림(예약 발송) 추가로 인한 메일구분
	$case_tmp = explode("_",$case);
	if($case_tmp[0] == "personal"){
		$email_mode = "email_personal";
		$gb			= "PERSONAL";
	}else{
		$email_mode = "email";
		$gb			= "AUTO";
	}

	$CI =& get_instance();
	$CI->config_basic	= ($CI->config_basic)?$CI->config_basic:config_load('basic');
	$CI->config_system	= ($CI->config_system)?$CI->config_system:config_load('system');
	$CI->config_email	= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);

	$CI->config_basic['domain'] = ($CI->config_system['domain']) ? "http://".$CI->config_system['domain'] : "http://".$CI->config_system['subDomain'];

	$CI->load->library('email');
	$CI->email->mailtype='html';

	$from		= $CI->config_basic['companyEmail'];
	$fromname	= !$CI->config_basic['shopName'] ? 'http://'.$CI->config_basic['domain'] : $CI->config_basic['shopName'];

	###
	if($case == 'board_reply'){
		$mailFile = "../../data/email/".get_lang(true)."/cs.html";
	}else{
		$mailFile = "../../data/email/".get_lang(true)."/".$case.".html";
	}
	$bodyTpl = "";
	$sendCount = 0;
	$CI->template->assign('basic',$CI->config_basic);
	$CI->template->assign($data);
	$CI->template->define('tpl', $mailFile);
	$bodyTpl = $CI->template->fetch('tpl');
	$body	= trim($bodyTpl);

	$body	= str_replace("http://http://", "http://", $body);

	###

	switch($case){
		default://기타

			$senduse	= true;
			$adminsend	= true;		// admin 발송여부

			### USER
			if(preg_match("/(^[_0-9a-zA-Z-]+(\.[_0-9a-zA-Z-]+)*@[0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*$)/", $to_email)) {
				$arr		= sendCheck($case, $email_mode, 'user', $data, '', $CI->config_email);
				$arrlog		= $arr;

				# 리마인드일 경우 제목이 없으면 발송 안함. / admin 발송안함
				if($email_mode == "email_personal"){
					if(!trim($arr[0])){
						$senduse	= false;
						$errmsg		= "ERROR : Subject 누락";
					}
					$adminsend = false;
				}

				// 받는이, 보내는이 메일주소가 없는 경우
				if	(!$to_email || !$from){
					$senduse	= false;
					if	(!$to_email)	$errmsg		= "ERROR : to_email 누락";
					if	(!$from)		$errmsg		= "ERROR : from_email 누락";
				}

				if(count($arr)>1 && $senduse){
					$body = str_replace('\\','',http_src($body));
					/* DB insert */
					$params = array();
					$params['location'] = $case;
					$params['subject'] = $arr[0];
					$params['contents'] = $body;
					$params['from_name'] = $fromname;
					$params['from_email'] = $from;
					$params['to_email'] = $to_email;
					$params['division'] = "user";
					$params['regist_date'] = date("Y-m-d H:i:s");

					$CI->db->insert("fm_email", $params);

					/*
					$CI->email->from($from, $fromname);
					$CI->email->to($to_email);
					$CI->email->subject($arr[0]);
					$body = str_replace('\\','',http_src($body));
					$CI->email->message($body);
					$CI->email->send();
					$CI->email->clear();
					*/
					$sendCount++;
				}
			}else{
				$senduse	= false;
				$errmsg		= "메일주소오류";
			}
			### ADMIN
			$adminsend_yn = 'N';
			if($adminsend){
				$arr = sendCheck($case, 'email', 'admin', $data, '',$CI->config_email);

				$CI->config_email	= ($CI->config_email['groupcd'] == $email_mode )?$CI->config_email:config_load($email_mode);
				if($CI->config_email[$case."_admin_email"]) $admin_email = $CI->config_email[$case."_admin_email"];

				if(count($arr)>1 && $admin_email && $from){

					$body = str_replace('\\','',http_src($body));
					/* DB insert */
					$params = array();
					$params['location'] = $case;
					$params['subject'] = $arr[0];
					$params['contents'] = $body;
					$params['from_name'] = $fromname;
					$params['from_email'] = $from;
					$params['to_email'] = $admin_email;
					$params['division'] = "admin";
					$params['regist_date'] = date("Y-m-d H:i:s");

					$CI->db->insert("fm_email", $params);

					/*
					$CI->email->from($from, $fromname);
					$CI->email->to($admin_email);
					$CI->email->subject($arr[0]);
					$body = str_replace('\\','',http_src($body));
					$CI->email->message($body);
					$CI->email->send();
					$CI->email->clear();
					*/
					$adminsend_yn = 'Y';
					$sendCount++;
				}
			}

			break;
	}

	###
	$subject		= $arrlog[0];
	if	(!$subject){
		$subject	= ($headers['Subject'])?$headers['Subject']:$CI->config_email[$case."_title"];
	}

	### LOG
	if($email_mode == "email_personal"){
		### 고객리마인드서비스용 발송로그
		if($data['kind']){
			if($senduse){
				$sql = "select seq from fm_log_curation_summary where inflow_kind='".$data['kind']."' and send_date ='".date("Y-m-d",mktime())."'";
				$query	= $CI->db->query($sql);
				$res	= $query->row_array();
				if(!$res['seq']){
					$CI->db->query("insert into fm_log_curation_summary set inflow_kind='".$data['kind']."',send_sms_total=0,send_date ='".date("Y-m-d",mktime())."'");
					$summary_seq = $CI->db->insert_id();
				}else{
					$summary_seq = $res['seq'];
				}
			}else{ $summary_seq = 0; }

			if(!$subject) $subject = "[제목없음]";

			$memo = $errmsg;
			if($memo){ $memo .= "@@".serialize($CI->config_email)."@@".serialize($arrlog); }

			unset($log_params);
			$log_params['regist_date']	= date('Y-m-d H:i:s');
			$log_params['summary_seq']	= $summary_seq;
			$log_params['sendres']		= ($senduse)? 'y':'n';				//제목없으면 false, 발송안함.
			$log_params['kind']			= $data['kind'];
			$log_params['to_email']		= $to_email;
			$log_params['member_seq']	= $data['member_seq'];
			$log_params['subject']		= $subject;
			$log_params['contents']		= $body;
			$log_params['memo']			= $memo;
			$log_data = filter_keys($log_params, $CI->db->list_fields('fm_log_curation_email'));
			$log_result =  $CI->db->insert('fm_log_curation_email', $log_data);
			### 발송 통계
			if($log_result && $senduse){
				if($summary_seq){
					$CI->db->query("update fm_log_curation_summary set send_email_total=send_email_total+1 where seq='".$summary_seq."'");
				}else{
					$CI->db->query("insert into fm_log_curation_summary set inflow_kind='".$data['kind']."',send_email_total=1,send_date ='".date("Y-m-d",mktime())."'");
				}
			}
		}
	}else{

		## 일반 메일 발송로그
		if($sendCount > 0){
			unset($params);
			$order_seq = "";
			if (!empty($data['order_seq'])) $order_seq = $data['order_seq'];
			if (!empty($data['ordno'])) $order_seq = $data['ordno'];
			$params['regdate']		= date('Y-m-d H:i:s');
			$params['gb']			= $gb;
			$params['total']		= '1';
			$params['to_email']		= $to_email;
			$params['member_seq']	= $data['member_seq'];
			$params['subject']		= $subject;
			$params['contents']		= $body;
			$params['order_seq']	= $order_seq;
			$params_data = filter_keys($params, $CI->db->list_fields('fm_log_email'));
			$result =  $CI->db->insert('fm_log_email', $params_data);
		}
		## 관리자 메일 발송로그 2015-05-19 pjm
		if($adminsend_yn == 'Y'){
			unset($params);
			$order_seq = "";
			if (!empty($data['order_seq'])) $order_seq = $data['order_seq'];
			if (!empty($data['ordno'])) $order_seq = $data['ordno'];
			$params['regdate']		= date('Y-m-d H:i:s');
			$params['gb']			= $gb;
			$params['total']		= '1';
			$params['to_email']		= $admin_email;
			$params['member_seq']	= $data['member_seq'];
			$params['subject']		= $subject;
			$params['contents']		= $body;
			$params['order_seq']	= $order_seq;
			$params['memo']			= "admin";
			$params_data = filter_keys($params, $CI->db->list_fields('fm_log_email'));
			$result =  $CI->db->insert('fm_log_email', $params_data);
		}
	}

	return true;
}


function sendCheck($case, $type, $gb = 'user', $params = array(),$order_no,$info=null){

	$CI		=& get_instance();
	if(!$info) $info	= config_load($type);

	// gb = admin / case = findid,findpwd,'marketing_agree','marketing_agree_status' 무조건 N!!!!!
	// 위 4개의 case 는 관리자 메일 발송하지 않음 2020-04-10
	if( $gb == "admin" && in_array($case,array('findid','findpwd','marketing_agree','marketing_agree_status')) ) {
		return false;
	}

	$send_yn	= ($info[$case."_".$gb."_yn"])?$info[$case."_".$gb."_yn"]:'N';//$gb=='user' ? $info[$case."_".$gb."_yn"] : 'Y';

	// 티켓상품의 출고와 배송 완료는 무조건 발송.
	if	($gb == 'user' && in_array($case, array('coupon_released', 'coupon_delivery'))){
		$send_yn	= 'Y';
	}

	## 상품명 길이 제한 2014-08-27
	if($type == "sms"){
		$goods_limit     = config_load('sms_goods_limit');                 //게시판
	}

	$CI->config_basic	= ($CI->config_basic)?$CI->config_basic:config_load('basic');
	$CI->config_system	= ($CI->config_system)?$CI->config_system:config_load('system');
	$CI->config_basic['domain'] = ($CI->config_system['domain']) ? $CI->config_system['domain'] : $CI->config_system['subDomain'];

	if( $send_yn != 'Y' && $gb=='user' && ($type=="sms" || $type=="sms_personal")){
		return false;
	}else if($send_yn != 'Y' && ($type=="email" || $type == "email_personal")){
		return false;
	}else{
		if( $order_no && !$params['ordno'] ) $params['ordno']	= $order_no;
		if( !$order_no && $params['ordno'] ) $order_no			= $params['ordno'];

		if( $case == 'released' && $params['export_code']){
			if( !$params['goods_name'] ) {
				$CI->load->model('exportmodel');
				$items = $CI->exportmodel->get_export_item($params['export_code']);
				$params['goods_name']	= $items[0]['goods_name'];
				if	(count($items) > 1)
					$params['goods_name']	.= '외 '.(count($items) - 1).'건';
			}
		}else if($order_no){
			if( !$params['goods_name'] ) {
				$items = get_data("fm_order_item",array("order_seq"=>$order_no));
				$params['goods_name']	= $items[0]['goods_name'];
				if	(count($items) > 1)
					$params['goods_name']	.= '외 '.(count($items) - 1).'건';
			}
		}

		if($params['goods_name']) $params['goods_name']	= strip_tags(str_replace("%","% ",$params['goods_name']));
		if($params['delivery_company']){
			$params['delivery_company'] = str_replace("(업무자동화)","",$params['delivery_company']);
		}

		$shopDomain	= ($CI->config_basic['domain']) ? get_connet_protocol().$CI->config_basic['domain'] : get_connet_protocol().$_SERVER['HTTP_HOST'];
		$replaceArr['{domain}'				]	= $shopDomain;
		$replaceArr['{shopDomain}'			]	= $shopDomain;
		$replaceArr['{shopName}'			]	= $CI->config_basic['shopName'				];
		$replaceArr['{사업자등록번호}'			]	= $CI->config_basic['businessLicense'		];
		$replaceArr['{통신판매업신고번호}'		]	= $CI->config_basic['mailsellingLicense'	];
		$replaceArr['{대표자}'				]	= $CI->config_basic['ceo'					];
		$replaceArr['{상점주소}'				]	= $CI->config_basic['companyAddress'		];
		$replaceArr['{상점전화}'				]	= $CI->config_basic['companyPhone'			];
		$replaceArr['{상점팩스}'				]	= $CI->config_basic['companyFax'			];
		$replaceArr['{password}'			]	= $params['passwd'							];
		$replaceArr['{쿠폰번호}'				]	= $params['coupon_serial'					];
		$replaceArr['{티켓번호}'				]	= $params['coupon_serial'					];
		$replaceArr['{계좌번호}'				]	= $params['bankinfo'						];
		$replaceArr['{trader.trader_name}'	]	= $params['trader_name'						];
		$replaceArr['{boardName}'			]	= $params['board_name'				]; //board

		## 상품명 길이 제한 수정 2014-08-27
		if($type == "sms"){
		   if($goods_limit['go_item_use'] == 'y'){
				$go_item = getstrcut($params['goods_name'],$goods_limit['go_item_limit']);
		   }else{
				$go_item = $params['goods_name'];
		   }
		   if($goods_limit['ord_item_use'] == 'y'){
				$ord_item = getstrcut($params['goods_name'],$goods_limit['ord_item_limit']);
		   }else{
				$ord_item = $params['goods_name'];
		   }
		   if($goods_limit['repay_item_use'] == 'y'){
				$repay_item = getstrcut($params['goods_name'],$goods_limit['repay_item_limit']);
		   }else{
				$repay_item = $params['goods_name'];
		   }
		   if($goods_limit['goods_name_use'] == 'y'){
				$goods_name = getstrcut($params['goods_name'],$goods_limit['goods_name_limit']);
		   }else{
				$goods_name = $params['goods_name'];
		   }
		}

		$replaceArr['{go_item}'				]     = $go_item;
		$replaceArr['{ord_item}'			]     = $ord_item;
		$replaceArr['{repay_item}'			]     = $repay_item;
		$replaceArr['{goods_name}'			]     = $goods_name;

		$replaceArr['{bank_account}'		]	= $params['bank_account'		];
		$replaceArr['{userid}'				]	= $params['userid'				];
		$replaceArr['{user_name}'			]	= $params['user_name'			];
		$replaceArr['{username}'			]	= $params['user_name'			];
		$replaceArr['{userName}'			]	= $params['user_name'			];
		$replaceArr['{settle_kind}'			]	= $params['settle_kind'			];
		$replaceArr['{delivery_company}'	]	= $params['delivery_company'	];
		$replaceArr['{delivery_number}'		]	= $params['delivery_number'		];

		## 고객리마인드 서비스 관련 추가 2014-07-22
		$replaceArr['{coupon_count}'		]	= $params['coupon_count'		];		//만료되는 할인쿠폰 갯수, 2014-07-22
		if($type == "sms_personal"){
			$replaceArr['{mypage_short_url}'	]	= $params['mypage_short_url_m'	];
		}elseif($type == "email_personal"){
			$replaceArr['{mypage_short_url}'	]	= $params['mypage_short_url_e'	];
		}
		$replaceArr['{mileage_rest}'		]	= $params['mileage_rest'		];		//마일리지

		###
		if(!$order_no){
			$replaceArr['{ordno}'			]	= $params['ordno'				];
			$replaceArr['{userid}'			]	= $params['userid'				];
			$replaceArr['{user_name}'		]	= $params['user_name'			];
		}else{
			$params['ordno'] = $order_no;
		}


		if($case == 'sorder_draft'){
			$replaceArr['{거래처명}'		]	= $params['trader_name'			];
			$replaceArr['{발주번호}'		]	= $params['sorder_code'			];
			$replaceArr['{발주일시}'		]	= $params['sorder_time'			];
			$replaceArr['{발주종수}'		]	= $params['sorder_item_cnt'		];
			$replaceArr['{발주수량}'		]	= $params['total_ea'			];
			$replaceArr['{발주서상세URL}'	]	= $params['sorder_url'			];
		}

		## etc
		foreach($params as $key => $val){
			$pattern	= '{'.$key.'}';
			if	(!$replaceArr[$pattern] && !is_array($val) && !is_numeric($key))
				$replaceArr[$pattern]	= $val;
		}

		### 회원정보 공통 치환
		$CI->load->model('membermodel');
		$replaceText	= $CI->membermodel->get_replacetext();
		foreach ($replaceText as $k => $v){
			$value	= '';
			if	($v['key']){
				if		(${$v['val']}[$v['key']])	$value	= ${$v['val']}[$v['key']];
				elseif	($v['val'] == 'params'){
					if		($params['member_seq']){
						if	(!$tmp)	$tmp	= $CI->membermodel->get_member_data($params['member_seq']);
						if	($tmp[$v['key']])		$value	= $tmp[$v['key']];
					}elseif	($params['userid']){
						if	(!$tmp)	$tmp	= $CI->membermodel->get_member_data_only($params['userid']);
						if	($tmp[$v['key']])		$value	= $tmp[$v['key']];
					}
				}
			}else{
				$value	= $$v['val'];
			}

			if	($v['type'] == 'number')
				$value	= number_format($value);

			$replaceTitleArr[$k]	= $value;
		}

		### 프로모션 발급 자동메일에서 치환안되는 값
		if(!$tmp) $tmp = $CI->membermodel->get_member_data($params['member_seq']);
		if(!$replaceArr['{username}'			]) $replaceArr['{username}'			]	= $tmp['user_name'			];
		if(!$replaceArr['{userid}'			]) $replaceArr['{userid}'			]	= $tmp['userid'				];

		//비회원이면 주문자명으로 대체
		if(!$replaceArr['{username}'] && $params['order_user_name'] ) $replaceArr['{username}'] = $params['order_user_name'];

		unset($tmp);
		###

		if( $params['ordno']){

			$orders = get_data("fm_order",array("order_seq"=>$params['ordno']));

			$replaceArr['{settleprice}']	 = get_currency_price($orders[0]['settleprice'],2);
			$replaceArr['{ordno}']	 = $params['ordno'];
			$replaceArr['{user_name}']	 = $orders[0]['order_user_name'];
			$replaceArr['{order_user}']		= $orders[0]['order_user_name'];
			switch($orders[0]['payment']){
				case "card": $temp_text = "신용카드 결제완료"; break;
				case "bank": $temp_text = substr($orders[0]['bank_account'],0,12)." 입금확인"; break;
				case "account": $temp_text = substr($orders[0]['bank_account'],0,12)." 계좌이체완료"; break;
				case "cellphone": $temp_text = "휴대폰 결제완료"; break;
				case "virtual": $temp_text = substr($orders[0]['virtual_account'],0,12)." 입금확인"; break;
				case "escrow_virtual": $temp_text = substr($orders[0]['virtual_account'],0,12)." 입금확인"; break;
				case "escrow_account": $temp_text = substr($orders[0]['bank_account'],0,12)." 계좌이체완료"; break;
				default: $temp_text = "결제완료"; break;
			}
			$replaceArr['{settle_kind}']	 = $temp_text;
			if($orders[0]['step']>='25' && $orders[0]['step']<='85'){
				if($params['export_code']){
					$exports = get_data("fm_goods_export",array("export_code"=>$params['export_code']));
				}else{
					$exports = get_data("fm_goods_export",array("order_seq"=>$params['ordno']));
				}
				if($exports){
					//받는분
					if($exports[0]['shipping_seq']){
						$shipping = get_data("fm_order_shipping",array("shipping_seq"=>$exports[0]['shipping_seq']));
						$replaceArr['{recipient_user}']	= $shipping[0]['recipient_user_name'];
					}
					$replaceArr['{export_code}']	 = $exports[0]['export_code'];
					if	(!$replaceArr['{delivery_number}'])
						$replaceArr['{delivery_number}']	 = $exports[0]['delivery_number'];
					if	(!$replaceArr['{delivery_company}']){
						$tmp = config_load('delivery_url',$exports[0]['delivery_company_code']);
						$replaceArr['{delivery_company}']	 = $tmp[$exports[0]['delivery_company_code']]['company'];
					}
				}
				if(!$shipping[0]['recipient_user_name']) $replaceArr['{recipient_user}'] = $orders[0]['recipient_user_name'];
			}

			$replaceArr['{deadline}'] = date('m월 d일', strtotime($orders[0]['deposit_date'] . '+4 day'));
			$replaceArr['{inputUrl}'] = present_input_url([
				'order_seq' => $orders[0]['order_seq'],
				'present_receive' => $orders[0]['recipient_cellphone'],
			]);

		}

		//휴면계정 처리문자 2015-08-10 jhr
		if($params['dormancy_du_date']){
			$replaceArr['{dormancy_du_date}']	 = $params['dormancy_du_date'];
		}

		//입점사문의 처리 문자 2020-08-03
		if($params['seller_id']){
			$replaceArr['{seller_id}']	 = $params['seller_id'];
		}

		foreach ($replaceArr as $key => $val){
			$patterns[]		= "/".$key."/";
			$replacements[]	= $val;

			$title_patterns[]		= "/".$key."/";
			$title_replacements[]	= $val;
		}
		foreach ($replaceTitleArr as $key => $val){
			$patterns[]		= "/".$key."/";
			$replacements[]	= $val;

			$title_patterns[]		= "/".$key."/";
			$title_replacements[]	= $val;
		}
		if($type=='sms'){
			$send_msg	= $info[$case."_".$gb];
			$msg	= preg_replace($patterns, $replacements, $send_msg);
			return $msg;
		}else{
			$send_msg	= $info[$case."_skin"];
			$msg	= preg_replace($patterns, $replacements, $send_msg);
			$info_title = $info[$case."_title"];
			/* 리마인드 발송 시 db에 저장된 SMS/Email Title 이 없을 때 */
			if(!$info_title && in_array($type, array('sms_personal','email_personal'))){
				if($type == 'sms_personal') {
					$CI->config->load('smsGroup');
					$personal_title = $CI->config->item('sms_personal_title');
				} else {
					$CI->config->load('emailGroup');
					$personal_title = $CI->config->item('email_personal_title');
				}
				$info_title 		= $personal_title[$case];
			}
			$send_title	= preg_replace($title_patterns, $title_replacements, $info_title);
			//$send_title	= $info[$case."_title"];
			if(in_array($case,array("personal_coupon","personal_review","personal_timesale","personal_cart","personal_membership","personal_emoney"))){ $case_title =$case."_title"; }else{ $case_title = ''; }

			$return = array($send_title, $msg,$case_title);
			return $return;
		}
	}
	return false;
}

/**
 * 선물하기 짧은 url 생성
 * @param array ['order_seq' => $orders['order_seq'],
				'present_receive' => $orders['recipient_cellphone']]
 * @return string url
 */
function present_input_url($params)
{
	$CI = &get_instance();
	$shopDomain = ($CI->config_basic['domain']) ? get_connet_protocol() . $CI->config_basic['domain'] : get_connet_protocol() . $_SERVER['HTTP_HOST'];
	$params = base64_encode(serialize($params));

	// 짧은url 변경
	$result = get_shortURL_advance($shopDomain . '/mypage/present_delivery?params=' . $params);

	return $result;
}



function adminSendChK($case, $number=0){
	$CI		=& get_instance();
	$CI->config_sms = ($CI->config_sms)?$CI->config_sms:config_load('sms');
	$send_yn	= $CI->config_sms[$case."_admins_yn_".$number] ? $CI->config_sms[$case."_admins_yn_".$number] : "N";
	return $send_yn;
}


//개별메일발송
function getSendMail($data=array()){
	$CI =& get_instance();
	$CI->load->library('email');

	$CI->config_basic	= ($CI->config_basic)?$CI->config_basic:config_load('basic');
	$CI->config_system	= ($CI->config_system)?$CI->config_system:config_load('system');
	$CI->config_basic['domain']	= ($CI->config_system['domain'])? $CI->config_system['domain'] : $CI->config_system['subDomain'];

	$title			= $data['title'];
	$contents		= $data['contents'];
	$email			= $data['email'];

	$body = adjustEditorImages($contents);
	if(preg_match("/(^[_0-9a-zA-Z-]+(\.[_0-9a-zA-Z-]+)*@[0-9a-zA-Z-]+(\.[0-9a-zA-Z-]+)*$)/", $email)) {
		$from_email		= !$CI->config_basic['companyEmail'] ? 'gabia@gabia.com' : $CI->config_basic['companyEmail'];
		$from_name	= !$CI->config_basic['companyName'] ? 'http://'.$CI->config_basic['domain'] : $CI->config_basic['companyName'];

		$CI->email->from($from_email, $from_name);
		$CI->email->to($email);
		$CI->email->subject($title);
		$body = str_replace('\\','',http_src($body));
		$CI->email->message($body);
		$CI->email->send();
		$CI->email->clear();
	}

	### LOG
	$emailparams['regdate']		= date('Y-m-d H:i:s');
	$emailparams['gb']			= 'MANUAL';
	$emailparams['from_email']	= $CI->config_basic['companyEmail'];
	$emailparams['to_email']	= $email;
	$emailparams['subject']		= $title;
	$emailparams['member_seq']	= $data['member_seq'];
	$emailparams['total']		= '1';
	$emailparams['contents']	= $body;
	$data = filter_keys($emailparams, $CI->db->list_fields('fm_log_email'));
	$CI->db->insert('fm_log_email', $data);
	return $resSend;
}




function printHangul($str){
	preg_match('/^([\x00-\x7e]|.{2})*/',$str,$r_str);
	return $r_str[0];
}



// ICONS
function find_icons($type = 'common'){
	$dir = ROOTPATH."data/icon/".$type;
	//echo $dir;
	if(!is_dir($dir)){
		if(!is_dir(ROOTPATH."data/icon")){
			@mkdir(ROOTPATH."data/icon");
			@chmod(ROOTPATH."data/icon",0777);
		}
		@mkdir($dir);
		@chmod($dir,0777);
	}
	$path = $dir;

	$icon = dir($path);
	if($icon){
		while ($entry = $icon->read()) {
			if (preg_match("/(\.gif)$/i", $entry) || preg_match("/(\.png)$/i", $entry) || preg_match("/(\.jpg)$/i", $entry) || preg_match("/(\.jpeg)$/i", $entry)){
				$retArray[] = $entry;
			}
		}
	}

	/* 설정 > 회원 > 등급 아이콘 등록일순 정렬 추가 2014-09-22 */
	if ($type=="common") {
		natsort($retArray);
		$arrFile1 = array();
		$arrFile2 = array();
		foreach($retArray as $key => $val) {
			if(strpos($val, "icon_grade") !== false) {
				$arrFile1[] = $val;
			} else {
				$arrFile2[] = $val;
			}
		}

		$retArray = array_merge($arrFile1, $arrFile2);
	}
	return $retArray;
}

// ADMIN > GOODS > LIST
function viewImg($goodSeq, $type, $img_size='N'){
	$CI =& get_instance();

	$CI->db->where(array('goods_seq'=>$goodSeq,'cut_number'=>'1','image_type'=>$type));
	$query = $CI->db->get('fm_goods_image');
	$data = $query->result_array();

	$CI->load->library('goodsList');
	// 19mark 이미지
	$markingAdultImg = $CI->goodslist->checkingMarkingAdultImg(['goods_seq' => $goodSeq]);
	if ($markingAdultImg) {
		return  $CI->goodslist->adultImg;
	}
	$size = config_load('goodsImageSize', $type);

	$data[0]['image'] = trim($data[0]['image']);
	if(preg_match('/http:\/\//',$data[0]['image']) || preg_match('/https:\/\//',$data[0]['image'])){
		return $data[0]['image'];
	}else if(isset($data[0]['image']) && $data[0]['image'] && file_exists(ROOTPATH.$data[0]['image'])){
		$data[0]['image'] .= "?dummy=".time();
		if($img_size=='Y'){
			return "<img src='".$data[0]['image']."' width='".$size[$type]['width']."' height='".$size[$type]['height']."'/>";
		}else{
			return $data[0]['image'];
		}
	}else{
		if(substr($type,0,5)=='thumb'){
			if($img_size=='Y'){
				return "<img src='/admin/skin/default/images/common/noimage_list.gif' width='".$size[$type]['width']."' height='".$size[$type]['height']."'/>";
			}else{
				return "/admin/skin/default/images/common/noimage_list.gif";
			}
		}else{
			if($img_size=='Y'){
				return "<img src='/admin/skin/default/images/common/noimage.gif' width='".$size[$type]['width']."' height='".$size[$type]['height']."'/>";
			}else{
				return "/admin/skin/default/images/common/noimage.gif";
			}
		}
	}
}


/**
 * ajax 현재 페이지
 * @param array $search_context
 * @return int
 */
function get_current_page($search_context) {
	return (int)($search_context['page'] / $search_context['perpage']) + 1;
}

/**
 * ajax 페이지 갯수를 구한다.
 * @param array $search_context
 * @param int $total_count
 * @return int
 */
function get_page_count($search_context, $total_count) {
	$pagecount = (int)(($total_count + $search_context['perpage']  - 1) / $search_context['perpage'] );
	$pagecount = $pagecount == 0 ? 1 : $pagecount;
	return $pagecount;
}


function get_return_data($data, $number, $type = "*"){
	$len	= strlen($data) - $number;
	$f_str	= substr($data,0,$number);
	$e_str	= "";
	for($i=0;$i<$len;$i++){
		$e_str .= $type;
	}
	return $f_str.$e_str;
}



function getHtmlFile($file)
{
	if ($fp = fopen($file, "r"))
	{
		$data = fread($fp, filesize($file));
		fclose($fp);

		return $data;
	} else
	{
		return false;
	}
}


function setHtmlFile($file,$data,$enc=0,$charset="")
{
	if ($fp = fopen($file,"w"))
	{
		//if (strtolower($charset)!="euc-kr") $data = iconv("UTF-8","EUC-KR",$data);
		fwrite($fp,toInputBox($data, $enc));
		fclose($fp);
		@chmod($file, 0777);
		return true;
	} else
	{
		return false;
	}
}

function toInputBox($var,$enc=0)
{
	if (!$enc) $var = htmlspecialchars($var, ENT_QUOTES);
	return $var;
}


/*
* RELATED ITEM ###
*
*
*/
function get_related_goods($seq, $type, $count){
	$CI =& get_instance();

	if(!$seq) return "";

	$now_date = date('Y-m-d');

	switch($type){
		case "AUTO":
			$CI->load->model('goodsdisplay');
			$CI->load->model('goodsmodel');

			$sql = "select category_code from fm_category_link where goods_seq = ? and link = 1";
			$query = $CI->db->query($sql,$seq);
			$cate = $query->row_array();

			$sql = "select * from fm_goods where goods_seq = ?";
			$query = $CI->db->query($sql,$seq);
			$goods = $query->row_array();

			$sql = "select * from fm_design_display where kind = 'relation'";
			$query = $CI->db->query($sql);
			$display = $query->row_array();

			if($goods['relation_count_w']==0 && $goods['relation_count_h']==0){
				$display['count_w'] = 4;
				$display['count_h'] = 1;
			}else{
				$display['count_w'] = $goods['relation_count_w'];
				$display['count_h'] = $goods['relation_count_h'];
			}
			$display['image_size'] = $goods['relation_image_size'];
			$display['auto_criteria'] = $goods['relation_criteria'];

			$sc = $CI->goodsdisplay->search_condition($display['auto_criteria'], $sc,'relation');

			$sc['limit']	= $display['count_w']*$display['count_h'];
			$sc['sort']		= $sc['auto_order'];
			if(!$sc['category']) $sc['category'] = $cate['category_code'];
			$sc['goods_seq_exclude']= $seq;

			$list = $CI->goodsmodel->goods_list($sc);

			return $list['record'];

			break;

		case "MANUAL":
			$sql = "SELECT
				B.*, C.consumer_price, C.price
			FROM
				fm_goods_relation A
				LEFT JOIN fm_goods B ON A.relation_goods_seq = B.goods_seq
				LEFT JOIN fm_goods_option C ON A.relation_goods_seq = C.goods_seq AND C.default_option = 'y'
			WHERE
				A.goods_seq = {$seq}
				AND B.goods_status = 'normal'
				AND (B.goods_view = 'look' or ( B.display_terms = 'AUTO' and B.display_terms_begin <= '".$now_date."' and B.display_terms_end >= '".$now_date."'))
			ORDER BY
				relation_seq ASC
			limit {$count}";
			$query = $CI->db->query($sql);
			$data = $query->result_array();
			return $data;
			break;

	}
	return "";
}

//모바일접속체크
function isMobilecheck($agent)
{
	$CI = & get_instance();
	// 구글 vertification 예외처리
	if ($CI->uri->rsegments[2] == 'googleToken') {
		return '';
	}

	$MobileArray = array(
		"iphone",
		"lgtelecom",
		"skt",
		"mobile",
		"samsung",
		"nokia",
		"blackberry",
		"android",
		"iPad",
		"sony",
		"phone"
	);
	$checkCount = 0;
	for ($i = 0; $i < sizeof($MobileArray); $i ++) {
		if ($MobileArray[$i] == 'skt') {
			if (preg_match("/$MobileArray[$i]/", strtolower($agent)) && ! preg_match("/asktb/", strtolower($agent))) {
				return $MobileArray[$i];
			}
		} else {
			if (preg_match("/$MobileArray[$i]/", strtolower($agent))) {
				return $MobileArray[$i];
			}
		}
	}
	return '';
}

//bookmark 체크
function bookmarkckeck($bookmarkuser,$title)
{
	$CI =& get_instance();
	$reserves = ($CI->reserves)?$CI->reserves:config_load('reserve');
	$bm_url = ($CI->config_system['domain'])?$CI->config_system['domain']:$CI->config_system['subDomain'];
	$bm_url .="/main/index";

	$default_reserve_bookmark = $reserves['default_reserve_bookmark'];
	$default_point_bookmark = $reserves['default_point_bookmark'];

	$bookmarkuser = $_COOKIE['bookmark'];
	$bookmarkpointuser = $_COOKIE['bookmarkpoint'];

	if( ( $default_reserve_bookmark > 0 &&  !$bookmarkuser ) || ( $default_point_bookmark > 0 && !$bookmarkpointuser) ) {
		if($title) {
			$bookmark = 'javascript:;"  onclick="bookmarksitelay(\'http://'.$bm_url.'\', \''.$title.'\',  \'/member/login?return_url='.urlencode($_SERVER['REQUEST_URI']).'\' )';
		}else{
			$bookmark = 'javascript:;"  onclick="bookmarksitelay(\'http://'.$bm_url.'\', \''.$CI->config_basic['shopName'].'\',  \'/member/login?return_url='.urlencode($_SERVER['REQUEST_URI']).'\' )';
		}
	}else{
		if($title) {
			$bookmark = 'javascript:;"  onclick="bookmarksite(\'http://'.$bm_url.'\', \''.$title.'\')';
		}else{
			$bookmark = 'javascript:;"  onclick="bookmarksite(\'http://'.$bm_url.'\', \''.$CI->config_basic['shopName'].'\')';
		}
	}
	return $bookmark;
}


function urlencode_rfc3986($input) {
	if (is_scalar($input)) {
			return str_replace('+',' ',str_replace('%7E', '~', rawurlencode($input)));
	} else {
		return '';
	}
}

/**
* 과세 + 비과세 주문등의 이유로 세금계산서 두건처리
* date : 2017-07-07
**/
function typereceipt_setting($order_seq, $seq = null){
	$CI =& get_instance();
	$typereceiptsql = "SELECT * FROM fm_sales WHERE order_seq = '{$order_seq}' AND tstep = 1";
	$typereceiptquery			= $CI->db->query($typereceiptsql);
	$typereceipt		= $typereceiptquery->result_array();
	foreach($typereceipt as $typereceiptdata){
		$result = firstmall_typereceipt($order_seq,$typereceiptdata['seq']);
	}
}

# 하이웍스 연동(세금계산서/현금영수증)
function firstmall_typereceipt($order_seq, $seq = null){

	$CI =& get_instance();

	$CI->load->model('salesmodel');

	###
	if($seq){
		$query = $CI->db->from('fm_sales')
		->where('seq', $seq)
		->where('tstep', 1);
	}else{
		// 2016.05.19 세금계산서 자동연동 기능 추가 pjw
		$query = $CI->db->from('fm_sales')
		->where('order_seq', $order_seq)
		->where('tstep', 1);
	}
	$query = $query->get();
	$typereceipt = $query->row_array();

	###
	if($CI->config_system['pgCompany']){
		$pg	= config_load($CI->config_system['pgCompany']);
	}

	if($typereceipt['typereceipt'] == '1'){
		$CI->salesmodel->sales_log_wirte($typereceipt['seq'], "하이웍스 연동 호출");
	}else if($typereceipt['typereceipt'] == '2' && $pg){
		$CI->salesmodel->sales_log_wirte($typereceipt['seq'], "현금영수증 연동 시작");
	}

	if($typereceipt['seq']){
		$typereceipt['supply'] = (int)get_cutting_price($typereceipt['supply']);
		$typereceipt['surtax'] = (int)get_cutting_price($typereceipt['surtax']);

		// 2016.05.19 세금계산서 분기처리 추가 pjw
		// 세금계산서 연동
		if($typereceipt['typereceipt'] == '1'){
			## 삭제대상.
			## $this->salesmodel->sales_modify 부분이 정상적으로 update 시
			## 아래와 같은 비정상적인 상태값은 발생할 수 없음.
			## 단, 기존 발급건 중 잔여 비정상 상태건이 있을 수 있으므로 당분간 유지 @2015-06-30 pjm
			$license_id		= $CI->config_system['webmail_admin_id'];
			$license_no		= $CI->config_system['webmail_key'];
			if($license_no != "") {

				$CI->salesmodel->sales_log_wirte($typereceipt['seq'], "하이웍스로 전송시작[".$typereceipt['order_seq']."]");

				if (!empty($typereceipt['hiworks_status']) && $typereceipt['hiworks_status']=='W' && $typereceipt['tstep']=='1') {
					$this->db->where('seq',$typereceipt['seq']);
					$this->db->update('fm_sales',array('tstep'=>'2'));

					$log_msg	= '이미 발행 된 세금계산서';
					$CI->salesmodel->sales_log_wirte($typereceipt['seq'], $log_msg);
					return false;
				}
				## 삭제대상 여기까지.

				$taxResult = $CI->salesmodel->hiworks_bill_send($typereceipt);

				if ( $taxResult['result'] == true ){
					$tstep = 2;
					$log_msg	= '하이웍스로 전송성공['.$typereceipt['order_seq'].']';
				}else{
					$tstep = 4;
					$log_msg	= '하이웍스로 전송실패<br>'.$taxResult['message'];
				}

				$upResult['seq']				= $typereceipt['seq'];
				$upResult['tstep']				= $tstep;
				$upResult['up_date']			= date('Y-m-d H:i:s');
				$upResult['issue_date']			= date('Y-m-d H:i:s');
				if($cashparams['order_seq']) $upResult['order_seq']	= $cashparams['order_seq'];
				$CI->salesmodel->sales_modify($upResult);
				if(strlen($taxResult['message']) == 1){
					$log_msg .= "(".$CI->salesmodel->hiworks_status_msg($taxResult['message']).")";
				}
				$CI->salesmodel->sales_log_wirte($typereceipt['seq'], $log_msg);
			}

			return true;

		// 현금영수증 연동
		}else if($typereceipt['typereceipt'] == '2' && $pg){

			$CI->load->library('cashtax');

			$cashparams['creceipt_number']			= $typereceipt['creceipt_number'];
			$cashparams['typereceipt'	]			= 2;
			$cashparams['type'			]			= $typereceipt['type'];
			$cashparams['order_seq'		]			= $order_seq;
			$cashparams['member_seq']				= $typereceipt['member_seq'];
			$cashparams['price'			]			= (int) $typereceipt['price'];
			$cashparams['person']					= $typereceipt['person'];
			$cashparams['cuse']						= $typereceipt['cuse'];
			$cashparams['goodsname'		]			= $typereceipt['goodsname'];
			$cashparams['paydt']					= date("Y-m-d H:i:s");
			$cashparams['surtax'			]		= (int) $typereceipt['surtax'];
			$cashparams['supply'			]		= (int) $typereceipt['supply'];
			$cashparams['mallId'			]		= $pg['mallId'];
			$cashparams['email']						= $typereceipt['email'];
			$cashparams['phone']					= $typereceipt['phone'];

			$taxResult = $CI->cashtax->getCashTax('pay', $cashparams);

			if (is_array($taxResult) == true)
			{
				$taxResult['seq']		= $typereceipt['seq'];
				$taxResult['tstep']		= 2;//발급완료
				$taxResult['up_date']		= date("Y-m-d H:i:s");
				$taxResult['order_seq'] = $cashparams['order_seq'];
				$taxResult['pg_kind']	= $CI->config_system['pgCompany'];
				$CI->salesmodel->sales_modify($taxResult);
				$log_msg	= $CI->config_system['pgCompany'] . '(으)로 전송성공';
				$CI->salesmodel->sales_log_wirte($typereceipt['seq'], $log_msg);
				return true;
			}
			else
			{
				$upResult['seq']		= $typereceipt['seq'];
				$upResult['tstep']		= 4;//발급취소
				$upResult['order_seq']	= $cashparams['order_seq'];
				$CI->salesmodel->sales_modify($upResult);
				$CI->cashtax->getCashTax('mod', $cashparams);
				$log_msg	= $CI->config_system['pgCompany'] . '(으)로 전송실패 '.$taxResult;
				$CI->salesmodel->sales_log_wirte($typereceipt['seq'], $log_msg);
				return false;
			}
		}
	}else{
		$CI->salesmodel->sales_log_wirte($typereceipt['seq'], "연동실패 - 연동데이터없음");
	}

	return false;
}

###
function getSaleStatus($gb="pg", $type="text"){
	$CI =& get_instance();
	$orders = config_load('order');
	print_r($orders);
	switch($gb){
		case "pg":
			if($CI->config_system['pgCompany']){
				$param['type'] = true;
				$param['text'] = "자동발급";
			}else{
				$param['type'] = false;
				$param['text'] = "발급불가";
			}
			break;
		case "cash":
			if($orders['cashreceiptuse']){
				$param['type'] = true;
				$param['text'] = "자동발급";
			}else{
				$param['type'] = false;
				$param['text'] = "발급불가";
			}
			break;
	}
	return $param[$type];
}

function get_file_down($path, $filenm, $pathDir = 'order'){

	$realPath 	= realpath($path);
	$pathChk 	= preg_match("/\/data\/".$pathDir."/", $realPath) ? true : false;

	if ($pathChk === false || !file_exists($realPath))
	{
		openDialogAlert("정상적인 파일이 아닙니다.",400,140,'parent');
		exit;
	}

	$filenm = iconv('UTF-8', 'EUC-KR', $filenm);
	header('Content-type: application/octet-stream');
	header("Content-Disposition: attachment; filename=".$filenm."");

	ob_clean();
	flush();

	readfile($path);
}

function get_args_list($exp=array('page')){
	if(!is_array($exp)) $exp = array($exp);
	$data = $_GET;
	foreach($exp as $v){
		if($v) unset($data[$v]);
	}
	return http_build_query($data, '', '&');
}

// paging (페이지당출력수,현재페이지넘버,페이지숫자링크갯수,쿼리,인자)
function select_page($number, $page, $page_number, $query, $bind, $SELECD_DB = null, $mode, $iTotcount='')
{
	if (! $bind) {
		$bind = array();
	}
	$params['query'] = $query;
	$params['bind'] = $bind;
	$params['mode'] = $mode; // (+추가)더보기 버튼시 사용.
	$CI = & get_instance();
	if ($CI->operation_type == 'light' && ! preg_match("/admin/", uri_string())) {
		$page_number = 5;
	}
	$CI->blockpage->perpage = $page_number;
	$CI->blockpage->page = $page;
	$CI->blockpage->page_number = $number;
	if ($iTotcount != '') {
		$CI->blockpage->iTotcount = $iTotcount;
		$CI->blockpage->block_number = $number;
		$CI->blockpage->limit_add = 0;
	} else {
		$CI->blockpage->set();
	}
	if ($mode == 'all_item') {
		$CI->blockpage->startPage = 1;
	}

	return $CI->blockpage->page_html($CI->blockpage->page_query($params, $SELECD_DB));
}

// paging (페이지당출력수,현재페이지넘버,페이지숫자링크갯수,쿼리,인자)
function select_script_page($number,$page,$page_number,$query,$bind,$script,$SELECD_DB=null) {
	if ( ! $bind) {
		$bind = array();
	}
	if ($page < 1) {
		$page = 1;
	}
	$params['query']	= $query;
	$params['bind']	= $bind;
	$CI =& get_instance();
	if($CI->operation_type == 'light' && !preg_match("/admin/", uri_string())){
	    $page_number = 5;
	}
	$CI->blockpage->perpage			= $page_number;
	$CI->blockpage->page				= $page;
	$CI->blockpage->page_number	= $number;
	$CI->blockpage->set();
	return $CI->blockpage->page_script($CI->blockpage->page_query($params,$SELECD_DB), $script);
}

function login_check(){

	$CI =& get_instance();

	$session_arr = ( $CI->session->userdata('user') )?$CI->session->userdata('user'):$_SESSION['user'];

	if(!$session_arr['sess_order'] && $_SESSION['sess_order'] ) {
		$session_arr['sess_order'] = $_SESSION['sess_order'];
		$CI->session->set_userdata(array('sess_order'=>$session_arr['sess_order']));
	}

	## 회원또는 비회원 로그인(주문조회)
	if(!$session_arr['member_seq']){
		// 보안 이슈 처리 by hed 2018-01-12
		// Referer 을 변조하여 로그인을 시도 할 때 본문에 XSS가 가능함을 방지함
		$REQUEST_URI = $_SERVER["REQUEST_URI"];
		$CI->load->helper('Security');
		$REQUEST_URI = xss_clean($REQUEST_URI);
		$url = "/member/login?return_url=".$REQUEST_URI;

		$nomemberpageck = array("order_view","order_refund","order_return","coupon_view","export_list");//비회원접근가능페이지
		if( $session_arr['sess_order'] && in_array($CI->uri->rsegments[2], $nomemberpageck) ) {
			return true;
		}else{
			if($_GET['designMode']){
				$msg = "마이페이지 영역의 페이지들을 정확하게 디자인하기 위해서 회원 로그인 해 주세요.\\n로그인 페이지에서 로그인 하면, 선택한 마이페이지 영역의 페이지로 바로 이동합니다.\\n이제, 마이페이지 영역의 페이지도 회원로그인 후 바로 EYE-DESIGN 하세요!";
			}else{
				//로그인이 필요한 페이지입니다.
				// 문구 변경 "비회원은 이용할 수 없습니다." :: 2019-02-08 pjw
				if($session_arr['sess_order']){

					// 비회원 블락 후 이전페이지로 되돌아가게 처리 :: 2019-02-08 pjw
					$msg = getAlert('mb252');
					pageBack($msg);
					exit;

				}else{

					$msg = getAlert('mb245');
					if( $CI->fammerceMode  || $CI->storefammerceMode ) {
						pageRedirect($url,'','self');
					}else{
						pageRedirect($url,'','parent');
					}
					die();
				}
			}

			//if($CI->session->userdata('fammercemode')){

		}
	}
}

function login_check_confirm(){

	$CI =& get_instance();
	$session_arr = ( $CI->session->userdata('user') )?$CI->session->userdata('user'):$_SESSION['user'];

	if(!$session_arr['member_seq']){
		if( strstr($_SERVER["REQUEST_URI"],"popup=1") ) $_SERVER["REQUEST_URI"] = str_replace("popup=1","",$_SERVER["REQUEST_URI"]);
		$url = "/member/login?return_url=".$_SERVER["REQUEST_URI"];

		//로그인이 필요한 페이지입니다.<br/><strong>로그인하시겠습니까?</strong>
		$msg = getAlert('mb244');
		if ( $CI->returnpopup ) {//레이어인경우
			$yescallback = "parent.location.href='".$url."';";//opener
			openDialogConfirm($msg,400,160,'parent',$yescallback,'');
		}elseif( $CI->fammerceMode  || $CI->storefammerceMode ) {
			$yescallback = "self.location.href='".$url."';";
			openDialogConfirm($msg,400,160,'self',$yescallback,'');
		}else{
			$yescallback = "parent.location.href='".$url."';";
			openDialogConfirm($msg,400,160,'parent',$yescallback,'');
		}
		die();
	}
}

/**
 * 페이징.
 * @param int totalrows 게시글총건수
 * @param int perpage 현재페이지
 * @param text paginurl 링크url
 * @param text qstr href 이외의 onclick 등의 이벤트 예) onclick="window.open(this.href);return false;"
 * @param text query_string_segment 페이징 명칭시
 */
function pagingtag($totalrows, $perpage, $paginurl, $qstr, $query_string_segment='page', $anchor_class=array())
{
	$CI =& get_instance();
	$CI->load->library('Pagination');
	$config['suffix'] 				= $qstr;
	$config['num_links'] 			= 5;//본래글 좌우 출력숫자
	$config['page_query_string'] 	= TRUE;//?page=1  쿼리로 넘기기
	$config['reuse_query_string'] 	= TRUE;// first_page 도 GET 값 사용 2018-05-23
	$config['query_string_segment'] = $query_string_segment;//'page';
	$config['base_url']				= $paginurl;//링크url
	$config['total_rows']			= $totalrows;//총갯수
	$config['per_page']				= $perpage;//출력페이지
	if($anchor_class)
		$config['attributes'] 		= array($anchor_class[0] => $anchor_class[1]);		// CI버전에 맞게 attributes 로 대체 2018-03-07

	$config['prev_link'] 			= '<span class="prev btn"></span>';//◀ 이전
	$config['prev_tag_open']		= '';
	$config['prev_tag_close'] 		= '';

	$config['first_link'] 			= '<span class="first btn"></span>';//맨처음
	$config['last_link'] 			= '<span class="end btn"></span>';//맨마지막

	$config['next_link'] 			= '<span class="next btn "></span>';//다음 ▶
	$config['next_tag_open'] 		= '';
	$config['next_tag_close'] 		= '';

	$config['cur_tag_open'] 		= '<a class="on red">';
	$config['cur_tag_close'] 		= '</a>';
	$CI->pagination->initialize($config);

	return $CI->pagination->create_links();

}

/**
 * 페이징.
 * @param int totalrows 게시글총건수
 * @param int perpage 현재페이지
 * @param text paginurl 링크url
 * @param text qstr href 이외의 onclick 등의 이벤트 예) onclick="window.open(this.href);return false;"
 * @param text query_string_segment 페이징 명칭시
 */
function pagingtagfront($totalrows, $perpage, $paginurl, $qstr, $query_string_segment='page', $anchor_class=array())
{
	$CI =& get_instance();
	$CI->load->library('Pagination');
	$config['suffix'] = $qstr;
	$config['num_links'] = 5;//본래글 좌우 출력숫자
	$config['page_query_string'] = TRUE;//?page=1  쿼리로 넘기기
	$config['query_string_segment'] = $query_string_segment;//'page';

	/**
	 * $paginurl과 $qstr 이 필드가 겹치는 문제 및 $paginurl의 필드 중복 문제 해결
	 */
	$urlInfo = parse_url($paginurl);
	if(!empty($urlInfo['query'])) {
	    // $paginurl의 쿼리스트링을 배열화
	    parse_str($urlInfo['query'], $baseQueries);
	    if(count($baseQueries) > 0 ) {
	        // page 값이 있을 경우 제거 (1페이지로 이동되지 않는 오류)
	        if(isset($baseQueries['page'])) unset($baseQueries['page']);
	        // $qstr의 &amp; 를 & 로 치환하여 배열화
	        if(!empty($qstr)) {
	            $qstrText = htmlspecialchars_decode($qstr);
	            parse_str($qstrText, $qstrFields);

	            // 배열화한 $qstr 에서 $paginurl 과 중복되는 field가 있을 경우 $paginurl에서 삭제한다.
	            if(count($qstrFields)>0) {
	                foreach($qstrFields as $qstrAttr => $qstrValue) {
	                    if(in_array($qstrAttr, array_keys($baseQueries)) === true) {
	                        unset($baseQueries[$qstrAttr]);
	                    }
	                }
	            }
	        }

	        $paginurl = $urlInfo['path'] . '?' .http_build_query($baseQueries);
	    }
	}

	$config['base_url']		= $paginurl;//링크url
	$config['total_rows']	= $totalrows;//총갯수
	$config['per_page']		= $perpage;//출력페이지
	$config['attributes'] = array($anchor_class[0] => $anchor_class[1]);		// CI버전에 맞게 attributes 로 대체 2018-03-07

	if($CI->mobileMode){
		$config['num_links'] = 2;
		$config['prev_link'] = '<span class="prev">◀ 이전</span>';//◀ 이전
		$config['prev_tag_open'] = false;
		$config['prev_tag_close'] = false;

		$config['first_link'] = false;//맨처음
		$config['last_link'] = false;//맨마지막

		$config['next_link'] = '<span class="next">다음 ▶</span>';//다음 ▶
		$config['next_tag_open'] = false;
		$config['next_tag_close'] = false;

		$config['display_pages'] = false;

		if( !empty($_GET['mobileAjaxCall'])){
		$config['attributes'] = array('mobileAjaxCall', $_GET['mobileAjaxCall']);
		}

		$config['cur_tag_open'] = '<a class="on red">';
		$config['cur_tag_close'] = '</a>';
	}else{
		$config['prev_link'] = '<span class="prev">◀ 이전</span>';//◀ 이전
		$config['prev_tag_open'] = '';
		$config['prev_tag_close'] = '';

		$config['first_link'] = '<span class="first">맨처음</span>';//맨처음
		$config['last_link'] = '<span class="end">맨마지막</span>';//맨마지막

		$config['next_link'] = '<span class="next">다음 ▶</span>';//다음 ▶
		$config['next_tag_open'] = '';
		$config['next_tag_close'] = '';


		$config['cur_tag_open'] = '<a class="on red">';
		$config['cur_tag_close'] = '</a>';
	}

	if($CI->operation_type == 'light' && !preg_match('/admin/', uri_string())){
	   $config['display_pages'] = true;
	   $config['num_links'] = 2; //본래글 좌우 출력숫자
	}

	$CI->pagination->initialize($config);
	return $CI->pagination->create_links();
}

function pagingtagjs($cur_page, $arr_block, $totalpage, $js_tag, $perblock = 10){
	if($CI->operation_type == 'light' && !preg_match('/admin/', uri_string())){
		$perblock = 5;
	}

	if(!is_array($arr_block) && $arr_block > 0 && $totalpage > 0){
		if($totalpage <= $perblock){
			return '<p><a class="on red">1</a><p>';
		}

		$perblock = $arr_block;
		$num_last = ceil($totalpage/$perblock);

		if($cur_page <= $arr_block){
			$num_start	= 1;
			if($perblock >= $num_last){
				$num_end	= $num_last;
			} else {
				$num_end	= $perblock;
			}
		} else {
			$num_start	= $cur_page-ceil(($perblock/2))+1;
			$num_end	= $cur_page+ceil(($perblock/2))-1;
			if($num_end > $num_last){
				$num_start -= $num_end-$num_last;
				$num_end = $num_last;
			}
		}

		$arr_block = array();
		for($i=$num_start; $i<=$num_end; $i++){
			$arr_block[] = $i;
		}

		$totalpage = $num_last;
	}

	if	(!$totalpage){
		// 데이터가 없을 경우 기본 페이징 노출
		return '<p><a class="on red">1</a><p>';
	}else{
		if	($cur_page > $perblock){
			$js				= str_replace('[:PAGE:]', 1, $js_tag);
			$paging_html	.= '<a onclick="' . $js . '" class="first hand"><span class="first btn"></span></a>&nbsp;';
			//$prev_page		= ceil(($cur_page - $perblock) / $perblock) * $perblock;
			$prev_page		= $cur_page - 1;
			$js				= str_replace('[:PAGE:]', $prev_page, $js_tag);
			$paging_html	.= '<a onclick="' . $js . '" class="prev hand"><span class="prev btn "></span></a>&nbsp;';
		}

		foreach ($arr_block as $page){
			$js	= str_replace('[:PAGE:]', $page, $js_tag);
			if	($page == $cur_page) {
				$paging_html .= '<a class="on red">' . $page . '</a>&nbsp;';
			} else {
				$paging_html .= '<a onclick="' . $js . '" class="hand">' . $page . '</a>&nbsp;';
			}
			$block_end_page	= $page;
		}

		if	($block_end_page < $totalpage){
			$js				= str_replace('[:PAGE:]', ($cur_page + 1), $js_tag);
			$paging_html	.= '<a onclick="' . $js . '" class="next hand"><span class="next btn"></span></a>&nbsp;';
			$js				= str_replace('[:PAGE:]', $totalpage, $js_tag);
			$paging_html	.= '<a onclick="' . $js . '" class="end hand"><span class="end btn "></span></a>&nbsp;';
		}
	}

	if($paging_html){
		return preg_replace('/\&nbsp\;$/', '', $paging_html);
	} else {
		return '<p><a class="on red">1</a><p>';
	}


}

/*
페이징 리스트 넘버링
@2020-04-27
*/
function pagingNumbering($sql,$sc=array(),$found_rows=true, $bindData = []){

	$CI =& get_instance();

	/* 전체 게시물 갯수 */
	if($sql['countWheres']){
		if(is_array($sql['countWheres'])){
			$countWheres = " WHERE ".implode(" AND ", $sql['countWheres']);
		}else{
			$countWheres = " WHERE ".$sql['countWheres'];
		}
	}

	$petten = '/(GROUP BY)/i';
	if($sql['groupby'] && !preg_match($petten,strtoupper($sql['groupby']))){
		$countGroupby = ' GROUP BY '.$sql['groupby'];
	}else{
		$countGroupby = $sql['groupby'];
	}

	$countGroupby = trim(str_replace("GROUP BY","",$countGroupby));
	if($countGroupby){
		$query		= "SELECT COUNT(DISTINCT ".$countGroupby.") COUNT FROM {$sql['table']}".$countWheres;
	}else{
		$query		= "SELECT COUNT(*) COUNT FROM {$sql['table']}".$countWheres;
	}

	if($sql['countTable']){
		$query		= "SELECT COUNT(*) COUNT FROM {$sql['countTable']}".$countWheres;
	}

	$result		= $CI->db->query($query)->row_array();
	$total 		= $result['COUNT'];

	$page = $record			= array();
	$page['totalcount']		= $total;

	if($total > 0){
		$sql_found_rows = '';
		// if($found_rows == true) $sql_found_rows = "SQL_CALC_FOUND_ROWS";

		if(is_array($sql['wheres']) && count($sql['wheres']) > 0){
			$wheres = " AND ".implode(" AND ",$sql['wheres']);
		}elseif(!empty($sql['wheres'])){
			$wheres = " AND ".$sql['wheres'];
		}else $wheres = '';

		if(strstr($wheres,"WHERE") || strstr($wheres,"where")){
			$wheres = str_replace("WHERE ","",str_replace("where ","",$wheres));
		}

		$petten = '/(ORDER BY)/i';
		if(!preg_match($petten,strtoupper($sql['orderby']))){
			$sql['orderby'] = 'ORDER BY '.$sql['orderby'];
		}
		$petten = '/(GROUP BY)/i';
		if($sql['groupby'] && !preg_match($petten,strtoupper($sql['groupby']))){
			$sql['groupby'] = 'GROUP BY '.$sql['groupby'];
		}

		$query		= "SELECT {$sql_found_rows} {$sql['field']} FROM {$sql['table']} WHERE (1) {$wheres} {$sql['groupby']} {$sql['orderby']} {$sql['limit']}";
		$record		= $CI->db->query($query, $bindData)->result_array();
		$return_query = $query;

		/* 검색된 게시물 갯수 */
		if($found_rows == true){
			$tmpSql	= "SELECT COUNT(*) COUNT FROM {$sql['table']} WHERE (1) {$wheres}";
			if ($countGroupby) {
				$tmpSql	= "SELECT COUNT(DISTINCT ".$countGroupby.") COUNT  FROM {$sql['table']} WHERE (1) {$wheres}";
			}
			$result = $CI->db->query($tmpSql, $bindData)->row_array();
			$page['searchcount'] = $result['COUNT'];

			$no					= $page['searchcount'] - ( $sc['page'] / $sc['perpage'] * $sc['perpage'] );
			/* 순번 계산 */
			foreach($record as $_key => $_record){
				$record[$_key]['_no'] = $no;
				$no--;
			}
		}
		$page['html']			= pagingtag($page['searchcount'], $sc['perpage'], "?".implode("&",$_parmas) );
	}else{
		$page['searchcount']	= 0;
	}

	if($page['html'] == '') $page['html']			= '<a class=\'on red\'>1</a>';
	$page['querystring']	= get_args_list($sc);

	$result				=  array();
	if($sc['debug']) $result['query']	= $return_query;
	$result['record']	= $record;
	$result['page']		= $page;

	return $result;
}

function pagingScmNumbering($sql,$sc=array(),$found_rows=true){

	$CI =& get_instance();

	/* 전체 게시물 갯수 */
	if($sql['countSql']){
		if(is_array($sql['countSql'])){
			$countSql = " WHERE ".implode(" AND ", $sql['countSql']);
		}else{
			$countSql = " WHERE ".$sql['countSql'];
		}
	}
	$query		= "SELECT COUNT(*) as CNT {$sql['fromSql']}".$countSql;
	$data		= $CI->db->query($query)->row_array();
	$total		= $data['CNT'];

	$page = $record			= array();
	$page['totalcount']		= $total;

	if($total > 0){
		$sql_found_rows = '';
		if($found_rows == true) $sql_found_rows = "SQL_CALC_FOUND_ROWS";

		if(is_array($sql['whereSql'])){
			$wheres = " AND ".implode(" AND ",$sql['whereSql']);
		}elseif($sql['whereSql']){
			$wheres = " AND ".$sql['whereSql'];
		}else $wheres = '';

		if(strstr($wheres,"WHERE") || strstr($wheres,"where")){
			$wheres = str_replace("WHERE ","",str_replace("where ","",$wheres));
		}

		$sql['select'] = preg_replace("/select/", "", $sql['select'], 1);

		$sql['fromSql'] = str_replace("from ","",$sql['fromSql']);
		$page['nowpage']		= $sc['page'];
		if($sc['perpage']) {
			$sc['page'] = ($sc['page'] == 1) ? $sc['page']-1 : $sc['page'];
			$limit		= " LIMIT {$sc['page']}, {$sc['perpage']}";
		}
		$query		= "SELECT {$sql_found_rows} {$sql['select']} FROM {$sql['fromSql']} WHERE (1) {$wheres} {$sql['groupBy']} {$sql['orderBy']} {$limit}";
		$record		= $CI->db->query($query,$sql['addBind'])->result_array();
		$return_query = $query;

		/* 검색된 게시물 갯수 */
		if($found_rows == true){
			$sql				= "SELECT FOUND_ROWS() as COUNT";
			$query				= $CI->db->query($sql);
			$result				= $query->row_array();
			$page['searchcount'] = $result['COUNT'];

			$no					= $page['searchcount'] - ( $sc['page'] / $sc['perpage'] * $sc['perpage'] );
			/* 순번 계산 */
			foreach($record as $_key => $_record){
				$record[$_key]['_no'] = $no;
				$no--;
			}
		}
		$page['html']			= pagingtag($page['searchcount'], $sc['perpage'], "?".implode("&",$_parmas) );
		$page['pagecount']		= ceil($page['searchcount']/$sc['perpage']);
	}else{
		$page['searchcount']	= 0;
	}

	if($page['html'] == '') $page['html']			= '<a class=\'on red\'>1</a>';
	$page['querystring']	= get_args_list($sc);

	$result				=  array();
	if($sc['debug']) $result['query']	= $return_query;
	$result['record']	= $record;
	$result['page']		= $page;

	return $result;
}

// 가격절삭
function get_price_point($price,$config_system='',$mode='sale'){
	if(!$config_system)	$config_system = config_load('system');

	$cutting_action = $config_system['cutting_'.$mode.'_action'];
	$cutting_price = $config_system['cutting_'.$mode.'_price'];
	$cutting_use = $config_system['cutting_'.$mode.'_use'];

	if($cutting_use == 'none') return $price;

	if( $cutting_action=='rounding' ){
		return round($price / $cutting_price) * $cutting_price;
	}else if($cutting_action=='dscending'){
		return floor($price / $cutting_price) * $cutting_price;
	}else if($cutting_action=='ascending'){
		return ceil($price / $cutting_price) * $cutting_price;
	}
	return $price;
}

function getLinkFilter($default,$arr)
{
	foreach($arr as $val) {
		if($val == 'page' || $val == 'cmtpage')continue;
		if (!empty($_GET[$val])) {
			if(is_array($_GET[$val])){
				foreach($_GET[$val] as $k=>$v){
					$default .= '&amp;'.@urlencode($val."[{$k}]").'='.@urlencode($v);
				}
			}else{
				$default .= '&amp;'.$val.'='.@urlencode($_GET[$val]);
			}
		}elseif (!empty($_POST[$val])) {
			if(is_array($_POST[$val])){
				foreach($_POST[$val] as $k=>$v){
					$default .= '&amp;'.@urlencode($val."[{$k}]").'='.@urlencode($v);
				}
			}else{
				$default .= '&amp;'.$val.'='.@urlencode($_POST[$val]);
			}
		}elseif(!empty($GLOBALS[$val])){
			if(is_array($GLOBALS[$val])){
				foreach($GLOBALS[$val] as $k=>$v){
					$default .= '&amp;'.@urlencode($val."[{$k}]").'='.@urlencode($v);
				}
			}else{
				$default .= '&amp;'.$val.'='.@urlencode($GLOBALS[$val]);
			}
		}
	}
	return $default;
}

//한글자름 -1 뒤에서 자를경우 추가 :: 게시글 작성자 글자숨김체크
function getstrcut( $str, $n = 500, $end_char = '...' , $minusnum = 1)
{
  $CI =& get_instance();
  $charset = $CI->config->item('charset');
  if ( mb_strlen( $str , $charset) < $n ) {
    return $str ;
  }

  $str = preg_replace( "/\s+/iu", ' ', str_replace( array( "\r\n", "\r", "\n" ), ' ', $str ) );

  if ( mb_strlen( $str , $charset) <= $n ) {
    return $str;
  }
  return (strstr($n,'-'))?mb_substr(trim($str), $n, $minusnum ,$charset) . $end_char:mb_substr(trim($str), 0, $n ,$charset) . $end_char ;
}

function getPageUrl($file_path) {
	$file_nm = end(explode("/",$file_path));
	$file_arr = explode(".",$file_nm);
	return $file_arr[0];
}

function get_query_string(){
	if($_SERVER['QUERY_STRING']){
		$tmp = explode("&",$_SERVER['QUERY_STRING']);
		foreach($tmp as $k=>$v){
			if(preg_match("/^query_string=/",$v)){
				unset($tmp[$k]);
			}
		}
		$_SERVER['QUERY_STRING'] = implode("&",$tmp);
	}
	return $_SERVER['QUERY_STRING'];
}


/**
*
* @
*/
function if_empty($arr=array(), $key=null, $default=null) {
	if (array_key_exists($key, $arr)) {
		if( empty($arr[$key]) ) {
			return $arr[$key] = $default;
		} else {
			return $arr[$key];
		}
	}
	return $default;
}

/**
 * include_keys에 있는 배열 항목만으로 배열을 구한다.
 * @param array $params
 * @param string $include_keys
 * @return array
 */
function filter_keys($params=array(), $include_keys=array()) {
	$new_arr = array();
	foreach ($params as $key => $val) {
		if($key != 'mode') {
			if ( in_array($key, $include_keys) ) {
				$new_arr[$key] = $params[$key];
			}
		}
	}
	return $new_arr;
}

function get_manager_name($manager_seq){
	$CI =& get_instance();
	$sql = "select mname from fm_manager where manager_seq = ?";
	$query = $CI->db->query($sql,array($manager_seq));
	$info = $query->result_array();
	$mname = $info[0]['mname'] ? $info[0]['mname'] : "";
	return $mname;
}


function get_provider_id($provider_seq){
	$CI =& get_instance();
	$sql = "select provider_id from fm_provider where provider_seq = ?";
	$query = $CI->db->query($sql,array($provider_seq));
	$info = $query->result_array();
	$mname = $info[0]['provider_id'] ? $info[0]['provider_id'] : "";
	return $mname;
}

function get_provider_seq($provider_id){
	$CI =& get_instance();
	$sql = "select provider_seq from fm_provider where provider_id = ?";
	$query = $CI->db->query($sql,array($provider_id));
	$info = $query->result_array();
	$mname = $info[0]['provider_seq'] ? $info[0]['provider_seq'] : "1";
	return $mname;
}


function get_use_check($type='point'){
	$reserves = config_load('reserve');
	$gb = $type."_use";
	if($reserves[$gb]=="Y") return true;
	return false;
}



function get_emoney_limitdate($type='order'){
	$limit_date = "";

	if($type=='order'){//주문1
		$reserve = config_load('reserve');
		if($reserve['reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$reserve['reserve_year']));
		}else if($reserve['reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$reserve['reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='join'){//가입시2
		$app = config_load('member');
		if($app['reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['reserve_year']));
		}else if($app['reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='recomm'){//추천받은자3
		$app = config_load('member');
		if($app['recomm_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['recomm_reserve_year']));
		}else if($app['recomm_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['recomm_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='joiner'){//추천한자4
		$app = config_load('member');
		if($app['joiner_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['joiner_reserve_year']));
		}else if($app['joiner_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['joiner_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='bookmark'){//즐겨찾기5
		$reserve = config_load('reserve');
		if($reserve['book_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$reserve['book_reserve_year']));
		}else if($reserve['book_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$reserve['book_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='invite'){//초대시 초대한자에게6
		$app = config_load('member');
		if($app['cnt_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['cnt_reserve_year']));
		}else if($app['cnt_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['cnt_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='invite_to'){//초대받은자가 가입시에 초대받은자에게7
		$app = config_load('member');
		if($app['invit_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['invit_reserve_year']));
		}else if($app['invit_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['invit_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='invite_from'){//초대받은자가 가입시 초대한자에게8
		$app = config_load('member');
		if($app['invited_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['invited_reserve_year']));
		}else if($app['invited_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['invited_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='photo_reserve'){//상품후기>사진9
		$app = config_load('reserve');
		if($app['photo_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['photo_reserve_year']));
		}else if($app['photo_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['photo_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='default_reserve'){//상품후기>게시글10
		$app = config_load('reserve');
		if($app['default_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['default_reserve_year']));
		}else if($app['default_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['default_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='video_reserve'){//상품후기>동영상11
		$app = config_load('reserve');
		if($app['video_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['video_reserve_year']));
		}else if($app['video_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['video_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='date_reserve'){//상품후기>특정기간 추가지급
		$app = config_load('reserve');
		if($app['date_reserve_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['date_reserve_year']));
		}else if($app['date_reserve_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['date_reserve_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='exchange_emoney'){//포인트 교환
		$app = config_load('reserve');
		if($app['exchange_emoney_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['exchange_emoney_year']));
		}else if($app['exchange_emoney_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['exchange_emoney_direct'], date("d"), date("Y")));
		}
	}


	return $limit_date;
}

function get_point_limitdate($type='order'){
	$limit_date = "";

	if($type=='order'){//주문1
		$reserve = config_load('reserve');
		if($reserve['point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$reserve['point_year']));
		}else if($reserve['point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$reserve['point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='join'){//가입2
		$app = config_load('member');
		if($app['point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['point_year']));
		}else if($app['point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='recomm'){//추천받은자3
		$app = config_load('member');
		if($app['recomm_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['recomm_point_year']));
		}else if($app['recomm_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['recomm_point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='joiner'){//추천한자4
		$app = config_load('member');
		if($app['joiner_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['joiner_point_year']));
		}else if($app['joiner_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['joiner_point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='bookmark'){//즐겨찾기5
		$reserve = config_load('reserve');
		if($reserve['book_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$reserve['book_point_year']));
		}else if($reserve['book_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$reserve['book_point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='invite'){//추천6
		$app = config_load('member');
		if($app['cnt_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['cnt_point_year']));
		}else if($app['cnt_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['cnt_point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='invite_to'){//초대받은자7
		$app = config_load('member');
		if($app['invit_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['invit_point_year']));
		}else if($app['invit_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['invit_point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='invite_from'){//초대한자8
		$app = config_load('member');
		if($app['invited_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['invited_point_year']));
		}else if($app['invited_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['invited_point_direct'], date("d"), date("Y")));
		}
	}


	else if($type=='photo_point'){//상품후기>사진9
		$app = config_load('reserve');
		if($app['photo_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['photo_point_year']));
		}else if($app['photo_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['photo_point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='default_point'){//상품후기>게시글10
		$app = config_load('reserve');
		if($app['default_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['default_point_year']));
		}else if($app['default_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['default_point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='video_point'){//상품후기>동영상11
		$app = config_load('reserve');
		if($app['video_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['video_point_year']));
		}else if($app['video_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['video_point_direct'], date("d"), date("Y")));
		}
	}

	else if($type=='date_point'){//상품후기>특정기간 추가지급
		$app = config_load('reserve');
		if($app['date_point_select']=='year'){
			$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$app['date_point_year']));
		}else if($app['date_point_select']=='direct'){
			$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$app['date_point_direct'], date("d"), date("Y")));
		}
	}


	return $limit_date;
}


function get_member_money($type='cash', $member_seq){
	if(!$member_seq) return 0;
	$CI =& get_instance();
	$sql = "select * from fm_member where member_seq = '{$member_seq}'";
	$query = $CI->db->query($sql);
	$info = $query->result_array();
	return $info[0][$type];
}


function get_goods_point($price){
	$point = 0;
	$reserves = config_load('reserve');
	if($reserves['default_point_type']=='per'){
		 $point = (int) ($price * $reserves['default_point_percent'] / 100);
	}else{
		if($reserves['default_point_app']>0) $point = round($price / $reserves['default_point_app']) * $reserves['default_point'];
	}
	return $point;
}



function gift_order_check_all($gift_seq, $type, $total, $arr){
	$CI =& get_instance();
	if($type=='default'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}'";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= 1;
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}else if($type=='price'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}' AND eprice >= '{$total}' order by gift_seq desc limit 1";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= 1;
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}else if($type=='quantity'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}' AND eprice >= '{$total}' order by gift_seq desc limit 1";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= $info[0]['ea'];
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}else if($type=='lot'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}'";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= 1;
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}
	if(count($garr)>0){
		$temp['benefit_seq'] = $benefit_seq;
		$temp['goods']	= $garr;
		$temp['ea']		= $ea;
		return $temp;
	}else{
		return false;
	}
}


function gift_order_check_category($gift_seq, $type, $total, $arr){
	$CI =& get_instance();

	$sql = "SELECT * FROM fm_gift_choice WHERE choice_type = 'category' AND gift_seq = '{$gift_seq}'";
	$query	= $CI->db->query($sql);
	foreach($query->result_array() as $k){
		$cate[] = $k['category_code'];
	}

	$total = 0;
	for($i=0;$i<count($arr);$i++){
		$sql = "SELECT * FROM fm_category_link WHERE goods_seq = '{$arr[$i]['goods_seq']}' and category_code in ('".implode("','",$cate)."') limit 1";
		$query	= $CI->db->query($sql);
		$temp = $query->result_array();
		if($temp && $temp[0]['category_code']){
			$total +=  $arr[$i]['tot_price'];
		}
	}

	if($type=='default'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}'";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= 1;
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}else if($type=='price'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}' AND eprice >= '{$total}' order by gift_seq desc limit 1";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= 1;
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}else if($type=='quantity'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}' AND eprice >= '{$total}' order by gift_seq desc limit 1";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= $info[0]['ea'];
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}else if($type=='lot'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}'";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= 1;
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}

	if(count($garr)>0){
		$temp['benefit_seq'] = $benefit_seq;
		$temp['goods']	= $garr;
		$temp['ea']		= $ea;
		return $temp;
	}else{
		return false;
	}
}

function gift_order_check_goods($gift_seq, $type, $total, $arr){
	$CI =& get_instance();

	$sql = "SELECT * FROM fm_gift_choice WHERE choice_type = 'goods' AND gift_seq = '{$gift_seq}'";
	$query	= $CI->db->query($sql);
	foreach($query->result_array() as $k){
		$goods[] = $k['goods_seq'];
	}
	$total = 0;
	for($i=0;$i<count($arr);$i++){
		if(in_array($arr[$i]['goods_seq'], $goods)){
			$total +=  $arr[$i]['tot_price'];
		}
	}

	if($type=='default'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}'";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= 1;
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}else if($type=='price'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}' AND eprice >= '{$total}' order by gift_seq desc limit 1";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= 1;
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}else if($type=='quantity'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}' AND eprice >= '{$total}' order by gift_seq desc limit 1";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= $info[0]['ea'];
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}else if($type=='lot'){
		$sql	= "SELECT * FROM fm_gift_benefit WHERE gift_seq = '{$gift_seq}' AND sprice <= '{$total}'";
		$query	= $CI->db->query($sql);
		$info	= $query->result_array();
		$garr	= explode("|",$info[0]['gift_goods_seq']);
		$ea		= $info[0]['ea'];
		$benefit_seq= $info[0]['gift_benefit_seq'];
	}


	if(count($garr)>0){
		$temp['benefit_seq'] = $benefit_seq;
		$temp['goods']	= $garr;
		$temp['ea']		= $ea;
		return $temp;
	}else{
		return false;
	}

}

function get_gift_image($goods_seq, $type){
	$CI =& get_instance();
	$sql = "SELECT * FROM fm_goods_image WHERE goods_seq = '{$goods_seq}' AND cut_number = 1 AND image_type = '{$type}'";
	$query	= $CI->db->query($sql);
	$info	= $query->result_array();
	return $info[0]['image'];
}

function get_gift_name($goods_seq, $fild_name='goods_name'){
	$CI =& get_instance();
	$sql = "SELECT * FROM fm_goods WHERE goods_seq = '{$goods_seq}' limit 1";
	$query	= $CI->db->query($sql);
	$info	= $query->result_array();
	return strip_tags(str_replace(array("\"","'"),'',$info[0][$fild_name]));
}

function get_gift_stock($goods_seq){
	$CI =& get_instance();
	$sql = "SELECT * FROM fm_goods_option A LEFT JOIN fm_goods_supply B ON A.option_seq = B.option_seq WHERE A.goods_seq = '{$goods_seq}' limit 1";
	$query	= $CI->db->query($sql);
	$info	= $query->result_array();
	return $info[0]['stock'];
}



function get_category_name($category){
	$CI =& get_instance();
	$sql = "SELECT * FROM fm_category WHERE category_code = '{$category}'";
	$query	= $CI->db->query($sql);
	$info	= $query->result_array();

	$html = "";
	if($info[0]['node_type']=='image'){
		if($info[0]['node_image_over']){
			$html = "<img src='".$info[0]['node_image_normal']."' onmouseover=\"this.src='".$info[0]['node_image_over']."'\" onmouseout=\"this.src='".$info[0]['node_image_normal']."'\"/>";
		}else{
			$html = "<img src='".$info[0]['node_image_normal']."'/>";
		}
	}else{
		$html = $info[0]['title'];
	}
	return $html;
}

 function unescape($text){
	return urldecode(preg_replace_callback('/%u([[:alnum:]]{4})/', create_function('$word','return iconv("UTF-16LE", "UTF-8", chr(hexdec(substr($word[1], 2, 2))).chr(hexdec(substr($word[1], 0, 2))));'), $text));
}

/* 다량옵션오류방지를 위하여 인코딩된 옵션폼값을 디코딩 */
function decodeFormValue($encodedFormValue="",$dataType="POST"){
	if(!empty($encodedFormValue)){
		$encodedFormValue = explode(",",$encodedFormValue);
		foreach($encodedFormValue as $item){
			list($key,$value) = explode("=",$item);
			preg_match("/\[(.*)\]/",$key,$matches);
			$tmp = explode("[",$key);
			$keyCode = $tmp[0];
			$keyString = $matches[0];
			$keyValue = urldecode($value);
			$eval = "\$_{$dataType}[{$keyCode}]{$keyString}=\$keyValue;";
			eval($eval);
		}
	}
}

/* 0보다 작으면 무조건 0 반환 */
function zerobase($num=0){
	if($num<0) $num = 0;
	return $num;
}

//동영상연동
function uccdomain($urltype=null,$file_key_w=null,$manager=null){
	$CI =& get_instance();
	$cfg_goods = config_load("goods");
	$uccdomain = $cfg_goods['ucc_domain'];
	$ucc_key = $cfg_goods['ucc_key'];

	if( ($CI->manager) || $manager ) {
		$video_use = ($manager)?$manager['video_use']:$CI->manager['video_use'];
	}else{
		$video_use = ($uccdomain && $ucc_key)?'Y':'N';
	}

	if(!( defined('__ADMIN__')  || defined('__SELLERADMIN__') ) && $video_use == 'N' ) return false;

	// 가비아 API 변경으로 URL 수정 2018-07-23
	$uccdomain = 'play.smartucc.kr';

	//web.mvod.고객 도메인
	switch($urltype) {
	    case 'thumbnail':
	        $uccscripturl= ($file_key_w)?"https://".$uccdomain.'/flash_response/thumbnail_view.php?k='.$file_key_w:'';
        break;
	    case 'fileurl':
	        $uccscripturl = ($file_key_w)?"https://".$uccdomain.'/view_play.php?k='.$file_key_w:'';//play_r->view_play
        break;
		case 'fileinfo':
			/**
			* xml 파일정보
			* filename : 파일명,  //class_name : 분류명 //playtime :동영상플레이시간(초) //thumbnail_root(썸네일경로)
			**/
		    $uccscripturl = ($file_key_w)?"https://".$uccdomain.'/flash_response/get_fileinfo.php?k='.$file_key_w:'';
		break;
		case 'fileswf':
		    $uccscripturl = ($file_key_w)?"https://".$uccdomain.'/swf/gplayer2.swf?host='.$uccdomain.'&k='.$file_key_w:'';
	    break;
		default:
			//&c=분류코드 없음
		    $uccscripturl = ($ucc_key && $video_use == 'Y' )?"https://".$uccdomain.'/gabiaSmartHDUploader.js.php?e=utf-8&k='.$ucc_key:'';
		break;
	}
	return $uccscripturl;
}

function GetValueNameCheck($str , $name){
	if(!$str)return;
	if(!strstr($str,$name))return;

	$pos1 = 0;  //length의 시작 위치
	$pos2 = 0;  //:의 위치

	while( $pos1 <= strlen($str) )
	{
		$pos2 = strpos( $str , ":" , $pos1);
		$len = substr($str , $pos1 , $pos2 - $pos1);
		$key = substr($str , $pos2 + 1 , $len);
		$pos1 = $pos2 + $len + 1;
		if( $key == $name )
		{
			$pos2 = strpos( $str , ":" , $pos1);
			$len = substr($str , $pos1 , $pos2 - $pos1);
			$value = substr($str , $pos2 + 1 , $len);
			return $value;
		}
		else
		{
			// 다르면 스킵한다.
			$pos2 = strpos( $str , ":" , $pos1);
			$len = substr($str , $pos1 , $pos2 - $pos1);
			$pos1 = $pos2 + $len + 1;
		}
	}
}



/**
* @에딧터 동영상/플래시 치환
**/
function showdesignEditor($content) {
	if(!$content) return false;

	$CI =& get_instance();
	/* 플래시매직 치환 {=showDesignFlash(36)} */
	if(preg_match_all("/\{[\s]*=[\s]*showDesignFlash[\s]*\([\s]*([0-9]+)[\s]*\)[\s]*\}/",$content,$matches)){
		$CI->template->include_('showDesignFlash');
		foreach($matches[0] as $idx=>$val){
			$flash_seq = $matches[1][$idx];
			$replaceContents = showDesignFlash($flash_seq, true, 'cach');
			$content = str_replace($val,$replaceContents,$content);
		}
	}

	/* 동영상 치환 {=showDesignVideo(67,"400X300")} */
	if(preg_match_all("/\{[\s]*=[\s]*showDesignVideo[\s]*\([\s]*([0-9]+)[\s]*,*\"[\s]*([0-9]+)[\s]*X[\s]*([0-9]+)[\s]*\"*\)[\s]*\}/",$content,$matches)){
		$CI->template->include_('showDesignVideo');
		foreach($matches[0] as $idx=>$val){
			$video_seq = $matches[1][$idx];
			$video_width = $matches[2][$idx];
			$video_height = $matches[3][$idx];
			$replaceContents = showDesignVideo($video_seq,$video_width."X".$video_height,true);
			$content = str_replace($val,$replaceContents,$content);
		}
	}

	/* 동영상 치환 {=showDesignVideo(39)} */
	if(preg_match_all("/\{[\s]*=[\s]*showDesignVideo[\s]*\([\s]*([0-9]+)[\s]*\)[\s]*\}/",$content,$matches)){
		$CI->template->include_('showDesignVideo');
		foreach($matches[0] as $idx=>$val){
			$video_seq = $matches[1][$idx];
			$replaceContents = showDesignVideo($video_seq,null,true);
			$content = str_replace($val,$replaceContents,$content);
		}
	}

	/* 슬라이드배너 치환 {=showDesignBanner(36)} */
	if(preg_match_all("/\{[\s]*=[\s]*showDesignBanner[\s]*\([\s]*([0-9]+)[\s]*\)[\s]*\}/",$content,$matches)){
		$CI->template->include_('showDesignBanner');
		foreach($matches[0] as $idx=>$val){
			$banner_seq = $matches[1][$idx];
			$replaceContents = showDesignBanner($banner_seq,true);
			$content = str_replace($val,$replaceContents,$content);
		}
	}

	return $content;
}

// 언더바템플릿 태그 제거 제거
function removeUnderbarTempleteTag($content) {
	$pattern = '/\{[\s]*\=[\s]*([a-zA-Z_-])+[\s]*([\(\s0-9,\"\'X\)])*[\s]*\}/';
	$replace_content = preg_replace($pattern, '', $content);
	return $replace_content;
}

/**
* @페이스북 common
**/
function isfacebook() {

	$CI =& get_instance();
	$CI->arrSns = ($CI->arrSns)?$CI->arrSns:config_load('snssocial');
	$CI->joinform = ($CI->joinform)?$CI->joinform:config_load('joinform');
	//$CI->arrSns['key_f'] = 1;$CI->arrSns['likebox_f'] = 1;$CI->arrSns['key_t'] = 1;

	/**
	** facebook grapy api upgrade version
	**  array("20150430"=>'1.0',"20160807"=>'2.0',"20161030"=>'2.1',"20170325"=>'2.2');
		array("20170325"=>'2.2',"20170708"=>'2.3',"20171007"=>'2.4',"20180412"=>'2.5',"20180713"=>'2.6',"20181005"=>'2.7');
	** @2015-04-21->@2017-03-30
	**/
	$CI->fb_available_until = array("20210223"=>'10.0');
	foreach($CI->fb_available_until as $fbdate=>$fbver) {
		if( $fbdate >= date("Ymd") ) {
			$fbversion = $fbver;
			break;
		}
		$fbversion = $fbver;//윗단에서 미체크시 마지막버젼이 자동적용
	}

	$CI->config_system = ($CI->config_system)?$CI->config_system:config_load('system');
	$CI->arrSns['sns_req_type']	= (isset($CI->arrSns['sns_req_type']))?$CI->arrSns['sns_req_type']:'FREE';//기본앱 FREE, 전용앱 그외

	if( $CI->joinform['use_f'] || $CI->arrSns['fb_like_box_type'] != 'NO' ) {//페이스북의 회원과 좋아요 미사용시 해지
		$CI->__APP_USE__				= (isset($CI->arrSns['fb_use']))?$CI->arrSns['fb_use']:'f';
	}
	if( $CI->arrSns['key_f'] != '455616624457601' ) {//전용앱
		$CI->__APP_VER__				= (isset($CI->arrSns['fb_ver']))?$CI->arrSns['fb_ver']:$fbversion;//버전 기본앱 1.0, 전용앱 2014-04-30 이후 2.0
	}else{
		$CI->__APP_VER__ = $fbversion;
	}
	define('__FB_APP_VER__',$CI->__APP_VER__);

	$CI->userauth		= 'email,public_profile,';												//@20190401 user_friends 제거
	$CI->adminauth		= 'email,public_profile,manage_pages,publish_actions,';	//@20190401 user_friends 제거
	if($CI->arrSns['key_f'] == '455616624457601') {//기본앱
		$CI->userauth			.= 'publish_actions,user_birthday,';
		$CI->adminauth		.= 'user_birthday,';
	}

	if($CI->arrSns['key_f'] == '455616624457601') {//기본앱
		$CI->arrSns['facebook_app']	= 'basic';
		$CI->__APP_DOMAIN__		= (isset($CI->arrSns['domain_f']))?$CI->arrSns['domain_f']:$CI->config_system['subDomain'];
		$CI->arrSns['facebook_ob_like']	= 'Y';//facebook like opengraph yes
	}else{//전용앱
		$CI->arrSns['facebook_app']	= 'new';

		if( isset($CI->arrSns['domain_f']) ) {
			$CI->__APP_DOMAIN__	= $CI->arrSns['domain_f'];;
		}else{
			$CI->__APP_DOMAIN__		= ($CI->config_system['domain'] ) ? $CI->config_system['domain']:$CI->config_system['subDomain'];
		}
		$CI->__APP_LIKEBOX__		= (isset($CI->arrSns['likebox_f']))?$CI->arrSns['likebox_f']:'';
		if($CI->__APP_LIKEBOX__){
			$CI->arrSns['facebook_ob_like']	= 'Y';//facebook like opengraph yes
		}else{
			$CI->arrSns['facebook_ob_like']	= 'N';//facebook like opengraph no
		}
	}

	if($CI->__APP_DOMAIN__){
		if( preg_match("/^www\./",$_SERVER['HTTP_HOST']) && !preg_match("/^www\./",$CI->__APP_DOMAIN__) ){
			$CI->__APP_DOMAIN__ = "www.".$CI->__APP_DOMAIN__;
		}
		if( preg_match("/^m\./",$_SERVER['HTTP_HOST']) && !preg_match("/^m\./",$CI->__APP_DOMAIN__) ){
			$CI->__APP_DOMAIN__ = "m.".$CI->__APP_DOMAIN__;
		}
	}
	if (check_ssl_protocol()!=false && preg_match("/\:/",$_SERVER['HTTP_HOST'])) {
		$CI->__APP_DOMAIN__ .= ":".$_SERVER['SERVER_PORT'];
	}

	$CI->__APP_ID__				= (isset($CI->arrSns['key_f']))?$CI->arrSns['key_f']:'';//'455616624457601'
	$CI->__APP_SECRET__		= (isset($CI->arrSns['secret_f']))?$CI->arrSns['secret_f']:'';//
	$CI->__APP_PAGE__			= (isset($CI->arrSns['page_id_f']))?$CI->arrSns['page_id_f']:'';
	$CI->__APP_NAMES__		= (isset($CI->arrSns['name_f']))?$CI->arrSns['name_f']:'';//fammerce_plus or add open grapy name

	/**
	* @2015-04-28 start
	* facebook version 2.* 이후 서비스제한으로 2015년까지만 오픈그라피(활동공유) 제한
	* facebook like 간접방식 제한
	**/
	$CI->__APP_STORY__		= (isset($CI->arrSns['story_f']))?$CI->arrSns['story_f']:'love';//love
	//$CI->__APP_LIKE_TYPE__	= $CI->arrSns['fb_like_box_type'];
	$CI->__APP_LIKE_TYPE__	= 'NO'; //#36327 페북 좋아요 기능 종료 kmj

	$CI->__APP_STORY_INTERESTS__		= (isset($CI->arrSns['story_interests_f']))?$CI->arrSns['story_interests_f']:$CI->__APP_STORY__;
	$CI->__APP_STORY_WRITE__			= (isset($CI->arrSns['story_write_f']))?$CI->arrSns['story_write_f']:$CI->__APP_STORY__;
	$CI->__APP_STORY_BUY__				= (isset($CI->arrSns['story_buy_f']))?$CI->arrSns['story_buy_f']:$CI->__APP_STORY__;
	if($CI->arrSns['key_f'] == '455616624457601') {//기본앱
		$CI->__APP_TYPE__							= 'product';//item or product
	}else{
		$CI->__APP_TYPE__							= ( isset($CI->arrSns['objecttype_f']) && $CI->arrSns['objecttype_f'] != 'item' )?$CI->arrSns['objecttype_f']:'';
	}
	/**
	* @2015-04-28 end
	**/


	$CI->__TW_APP_KEY__			= (isset($CI->arrSns['key_t']))?$CI->arrSns['key_t']:'';//'ifHWJYpPA2ZGYDrdc5wQ'
	$CI->__TW_APP_SECRET__	= (isset($CI->arrSns['secret_t']))?$CI->arrSns['secret_t']:'';
	$CI->arrSns['twitter_app']		= ($CI->arrSns['key_t'] == 'ifHWJYpPA2ZGYDrdc5wQ')?'basic':'new';

	$CI->domainurl	= ($CI->config_system['domain'] ) ? get_connet_protocol().$CI->config_system['domain']:get_connet_protocol().$CI->config_system['subDomain'];
	if($CI->__APP_DOMAIN__ == $_SERVER['HTTP_HOST'] ) {//전용앱과 현재도메인이 동일한경우
		if (check_ssl_protocol()!=false) {
			$CI->domainurl	=  'https://'.$_SERVER['HTTP_HOST'];
		}else{
			$CI->domainurl	=  'http://'.$_SERVER['HTTP_HOST'];
		}
	}

	$CI->firstmallurl	= (check_ssl_protocol()!=false)?'https://'.$CI->config_system['subDomain']:'http://'.$CI->config_system['subDomain'];
	if($CI->config_system['domain']){
		$CI->likeurl		= ($CI->__APP_ID__ != '455616624457601' || !$CI->__APP_USE__ )?'http://'.$CI->config_system['domain'].'/goods/view?':$CI->firstmallurl.'/goods/view?appid='.$CI->__APP_ID__;
	}else{
		$CI->likeurl		= ($CI->__APP_ID__ != '455616624457601' || !$CI->__APP_USE__ )?$CI->firstmallurl.'/goods/view?':$CI->firstmallurl.'/goods/view?appid='.$CI->__APP_ID__;
	}

	if(isset($CI->userauth)) $CI->template->assign('fbuserauth',		$CI->userauth);
	if($CI->session->userdata('fbuser')) $CI->template->assign('fbuser',$CI->session->userdata('fbuser'));//실제로그인된 경우
	if(is_file(ROOTPATH.$CI->config_system['snslogo'])) {//'/data/icon/favicon/'.
		$SNSLOGO = $CI->config_system['snslogo'];
		if( substr($SNSLOGO,0,1) == '/' ) {
			$SNSLOGO = substr($SNSLOGO,1,strlen($SNSLOGO));
		}
		if($SNSLOGO) $SNSLOGO .= "?".time();
		$CI->template->assign('SNSLOGO',$SNSLOGO);
	}

	$CI->is_file_facebook_result = false;
	if( $CI->arrSns['fb_like_box_type'] == 'API'  || !$CI->arrSns['fb_like_box_type'] ) {//직접방식
		$CI->is_file_facebook_result = true;
	}

	if ( strstr(uri_string(),'member/') || strstr(uri_string(),'mypage/') ) {//마이페이지
		$CI->joinform = ($CI->joinform)?$CI->joinform:config_load('joinform');
		if( $CI->joinform['use_f'] && $CI->is_file_facebook_result != true ) {//페이스북회원사용하면
			$CI->is_file_facebook_result = true;
		}
	}elseif ( strstr(uri_string(),'order/') || strstr(uri_string(),'goods/')) {//주문/장바구니
		//좋아요 할인 혜택여부 추가할인 추가적립
		$cache_item_id = 'config_sale_type_fblike';
		$systemfblike = cache_load($cache_item_id);
		if ($systemfblike === false) {
			$CI->load->model('configsalemodel');
			$systemfblike = $CI->configsalemodel->lists(array('type'=>'fblike'));

			if (! is_cli()) {
				cache_save($cache_item_id, $systemfblike);
			}
		}
		$CI->systemfblike = $systemfblike;
		$CI->template->assign('fblikesale',$CI->systemfblike['result']);
		$CI->template->assign('firstmallcartid',session_id());
		if(  $CI->arrSns['fb_like_box_type'] == 'API' && count($CI->systemfblike['result'])  && $CI->is_file_facebook_result != true ) {//라이크할인혜택있으면
			$CI->is_file_facebook_result = true;
		}
	}

	if( $CI->is_file_facebook_result || in_array('register_sns_form',$CI->uri->rsegments)  || ( strstr(uri_string(),'order/complete') && $CI->arrSns['facebook_buy'] == 'Y') ) {
			$CI->is_file_facebook_tag = "<script type='text/javascript'>var fbv='".$CI->__APP_VER__."';</script>";
			$CI->is_file_facebook_tag .= "<script type='text/javascript' src='/app/javascript/js/facebook.js?v=20150501' charset='utf8'></script>";
			$CI->template->assign('is_file_facebook',true);
			$CI->template->assign('is_file_facebook_tag',$CI->is_file_facebook_tag);
		}

	## 카카오 로그인 SDK
	if($CI->arrSns['use_k'] && $CI->arrSns['key_k']){
		$CI->is_file_kakao_tag = "<script src=\"/app/javascript/plugin/kakao/kakao.min.js\"></script>";
		$CI->template->assign('is_file_kakao_tag',$CI->is_file_kakao_tag);
	}

	//html header ogurl @2017-07-25
	$ogurl = $CI->firstmallurl.$_SERVER['REQUEST_URI'];
	if($CI->__APP_ID__ != '455616624457601' || !$CI->__APP_USE__ ){//전용앱이거나 미사용이면 정식도메인
		if( $CI->domainurl ) $ogurl = $CI->domainurl.$_SERVER['REQUEST_URI'];
	}

	$CI->template->assign(array(
		'APP_USE'=>$CI->__APP_USE__,
		'APP_VER'=>$CI->__APP_VER__,
		'APP_LIKE_TYPE'=>$CI->__APP_LIKE_TYPE__,
		'APP_DOMAIN'=>$CI->__APP_DOMAIN__,
		'APP_ID'=>$CI->__APP_ID__,
		'APP_SECRET'=>$CI->__APP_SECRET__,
		'APP_PAGE'=>$CI->__APP_PAGE__,
		'APP_NAMES'=>$CI->__APP_NAMES__,
		'APP_STORY'=>$CI->__APP_STORY__,
		'APP_TYPE'=>$CI->__APP_TYPE__,
		'likeurl'=>$CI->likeurl,
		'url'=>$ogurl,
		'TW_APP_ID'=>$CI->__TW_APP_KEY__,
		'TW_APP_SECRET'=>	$CI->__TW_APP_SECRET__,
		'storyvideo',true)
	);
}


function won_print($price) {
	if($price%10000 == 0){
		return ($price/10000) . '만원';
	}else{
		return number_format($price) . '원';
	}
}

function get_sms_remind_count(){
	require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/SMS_send.class.php";
	$sms			= new SMS_SEND();
	$params			= "sms_id=" . $sms->sms_account . '&sms_pw=' . $sms->sms_password;
	$params			= makeEncriptParam($params);
	$return['cnt']	= ($sms->limit != -1) ? $sms->limit : 0;
	$return['link']	= "/admin/member/sms_charge";

	return $return;
}

// 티켓상품 인증번호 자동생성
function get_coupon_serialnumber($append_str = ''){
	$CI	=& get_instance();
	$CI->load->model('goodsmodel');

	$result	= true;
	while ($result){
		$coupon_serial	= strtoupper(substr(md5(uniqid('').$append_str), 0, 16));
		$result			= $CI->goodsmodel->chkDuple_coupon_serial($coupon_serial);
	}
	return $coupon_serial;
}

// 입점사 문자발송
function sendSMS_for_provider($case, $providerList, $params){

	$CI =& get_instance();
	$CI->load->model('providermodel');
	$CI->load->model('Providershipping');

	$CI->config_basic = ($CI->config_basic)?$CI->config_basic:config_load('basic');
	$CI->config_system	= ($CI->config_system)?$CI->config_system:config_load('system');
	$CI->config_basic['domain']	= $CI->config_system['domain'];
	$CI->config_sms = ($CI->config_sms)?$CI->config_sms:config_load('sms');//$sms_config

	// 입점사 문자발송의 경우 ( 공급사를 구하기 위해 주문번호 필수 !! )
	if	(count($providerList) > 0 && $CI->config_sms[$case.'_provider_yn'] == 'Y'){
		$msg	= sendCheck($case, 'sms', 'admin', $params, '', $CI->config_sms);
		unset($tmp_shipping,$person);
		foreach($providerList as $provider_seq => $val){
			// 본사 또는 위탁배송은 제외
			if(!$tmp_shipping[$provider_seq]) $tmp_shipping[$provider_seq] = $CI->Providershipping->get_provider_shipping($provider_seq);
			if( $provider_seq > 1 && $tmp_shipping[$provider_seq]['provider_seq'] ) {
				if(!$person[$provider_seq]) $person[$provider_seq] = $CI->providermodel->get_person($provider_seq);
				if	($person[$provider_seq]){
					foreach($person[$provider_seq] as $p => $data){
						// 물류담당자에게만 발송
						if	(in_array($data['gb'], array('ds1', 'ds2')) && $data['mobile']){
							$dataTo	= preg_replace("/[^0-9]/", "", $data['mobile']);
							if(in_array($dataTo, $CI->send_for_provider['order_cellphone'])) continue;
							$CI->send_for_provider['order_cellphone'][] = $dataTo;
							$CI->send_for_provider['msg'][] = $msg;
						}
					}
				}
			}
		}
	}

	return $result;
}

if(!function_exists('get_provider_name')){
	function get_provider_name($provider_seq){
		$CI =& get_instance();
		$CI->load->model('providermodel');
		$data = $CI->providermodel->get_provider_one($provider_seq);
		return $data['provider_name'];
	}
}

/**
 * get_shortURL : 단순 url 생성만 진행함(예:bitly 통신)
 * get_shortURL_advance : 짧은 url 사용여부, 생성시 성공/실패 여부에 따라 url 리턴함
 */
function get_shortURL_advance ($url) {
	$CI->arrSns = ($CI->arrSns) ? $CI->arrSns : config_load('snssocial');

	// 짧은url 사용안하는 경우
	if ($CI->arrSns['shorturl_use'] !== 'Y') {
		return $url;
	}

	// 짧은 url 생성
	list($short_url, $short_result) = get_shortURL($url);
	// 성공했을 경우 short_url 반환
	if ($short_result === true) {
		return $short_url;
	}

	// 실패할 경우
	return $url;
}

//상품상세, 게시글보기, 기타 연결 URL 짧은 주소 변환
function get_shortURL($longURL, $shorturl=NULL) {
	$CI =& get_instance();
	$CI->arrSns	= ($CI->arrSns)?$CI->arrSns:config_load('snssocial');
	if( $shorturl ) {
		$shortURL_domain = $shorturl;
	}else{
		$shortURL_domain=($CI->arrSns['shorturl_domain'])?$CI->arrSns['shorturl_domain']:'bitly.com';
	}

	$login	 = $CI->arrSns['shorturl_app_id'];
	$api_key = $CI->arrSns['shorturl_app_key'];
	$token	 = $CI->arrSns['shorturl_app_token'];
	$type	 = $CI->arrSns['shorturl_keyType'];
	$encodeLongURL = urlencode($longURL);

	switch($shortURL_domain) {
		case "bit.ly" :
		case "j.mp" :
		case "bitly.com" :
			//bitly API 버전 및 설정 값 검증(key:API v3, token:API v4)
			if( $type == 'key' ){
				if	( !$login ){
					return $shortURL = array("result"=>false,'shorturl'=>'INVALID_LOGIN');
				}else if( !$api_key ){
					return $shortURL = array("result"=>false,'shorturl'=>'INVALID_APIKEY');
				}
			}else{
				if	( !$token ){
					return $shortURL = array("result"=>false,'shorturl'=>'INVALID_ACCESS_TOKEN');
				}
			}
			//bitly API 버전에 따른 통신 URL 지정
			if( $type == 'token' && $token ){
				$curlopt_url = "https://api-ssl.bitly.com/v4/shorten";
			}else{
				$curlopt_url = "http://api.".$shortURL_domain."/v3/shorten?login=".$login."&apiKey=".$api_key."&uri=".$longURL."&format=txt";
			}
			break;

		case "is.gd" :
			$curlopt_url = "https://is.gd/create.php?format=simple&url=".$encodeLongURL;
			break;

		case "v.gd" :
			$curlopt_url = "http://v.gd/create.php?format=simple&url=".$encodeLongURL;
			break;

		case "tinyurl" :
			$curlopt_url = "http://tinyurl.com/api-create.php?url=".$longURL;
			break;
	}
	$result = false;

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $curlopt_url);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	if($shortURL_domain == "bitly.com" && $type=='token'){
		//API 버전에 따른 통신 설정 지정
		$data['long_url'] = $longURL;
		$payload = json_encode($data);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization:Bearer ".$token, "Content-Type:application/json", "Content-Length:".strlen($payload)));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
	}
	$tmpShortURL	= curl_exec($ch);
	curl_close($ch);

	if($shortURL_domain == "bitly.com"){
		$shortURL = json_decode($tmpShortURL, true);
		if($shortURL['link']){
			//Bitly API에서 전달받은 URL에 개행문자가 있는 경우 제거
			if(bin2hex(substr($shortURL['link'], -1, 1)) == "0a"){
				$shortURL['link'] = substr($shortURL['link'], 0, strlen($shortURL['link'])-1);
			}
			$result = true;
			$shortURL = $shortURL['link'];
		}else if($shortURL['message']){
			$shortURL = $shortURL['message'];
		}
	}

	return array($shortURL, $result);
}

function like_count_print($fbcount) {
	if( $fbcount> 0 && ($fbcount%10000) == 0 ){
		return number_format($fbcount/10000) . '만';
	}else{
		return number_format($fbcount) . '';
	}
}

function http_src($content)
{
	$CI =& get_instance();
	$CI->config_system	= ($CI->config_system)?$CI->config_system:config_load('system');

	$host = ($CI->config_system['domain']) ? get_connet_protocol().$CI->config_system['domain'] : get_connet_protocol().$CI->config_system['subDomain'];
	$host = preg_replace("/:[0-9].+$/","",$host); //포트번호 삭제

	$pattern_a = array(
		"@(\s*href|\s*src)(\s*=\s*'{1})(\/[^']+)('{1})@i",
		"@(\s*href|\s*src)(\s*=\s*\"{1})(\/[^\"]+)(\"{1})@i",
		"@(\s*href|\s*src)(\s*=\s*)(/[^\s>\"\']+)(\s|>)@i"
	);
	$replace_a = "$1$2{$host}$3$4";//"'\\1\\2".($host)."\\3\\4'"; php 버젼업에 따른 src 오류수정 @2017-03-20
	$content = preg_replace($pattern_a, $replace_a, $content);

	return $content;
}

// 특정테그만 삭제
function strip_tag_arrays($str, $strip_tags) {
	$cnt = sizeof($strip_tags);
	for ($i=0; $i<$cnt; $i++) {
		$tag_pattern = "<{$strip_tags[$i]}[^>]*>";
		$str = preg_replace('/'.$tag_pattern.'/i', '', $str);
		$str = preg_replace("/</{$strip_tags[$i]}>/i", '', $str);
	}
	return $str;
}

// 가시이미지 로딩용 테그 만들기
function lazy_image($str)
{

	preg_match_all("/<img[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i",$str,$temp);
	$temp[1] = array_unique($temp[1]);
	foreach($temp[1] as $a){
		$str = str_replace("class=\"lazyload\" src=\"".$a."\"","data-echo=\"".$a."\" src=\"\"",$str);
	}

	return $str;
}

## 접속브라우저 정보 확인
function getBrowser()
{
	$u_agent	= $_SERVER['HTTP_USER_AGENT'];
	$bname		= 'Unknown';
	$platform	= 'Unknown';
	$version	= "";

	//First get the platform?
	if (preg_match('/linux/i', $u_agent)) { $platform = 'linux'; }
	elseif (preg_match('/macintosh|mac os x/i', $u_agent)) { $platform = 'mac'; }
	elseif (preg_match('/windows|win32/i', $u_agent)) { $platform = 'windows'; }

	// Next get the name of the useragent yes seperately and for good reason
	if(preg_match('/(MSIE|Trident)/i',$u_agent) && !preg_match('/Opera/i',$u_agent)) { $bname = 'Internet Explorer'; $ub = "MSIE"; }
	elseif(preg_match('/Firefox/i',$u_agent)) { $bname = 'Mozilla Firefox'; $ub = "Firefox"; }
	elseif(preg_match('/Chrome/i',$u_agent)) { $bname = 'Google Chrome'; $ub = "Chrome"; }
	elseif(preg_match('/Safari/i',$u_agent)) { $bname = 'Apple Safari'; $ub = "Safari"; }
	elseif(preg_match('/Opera/i',$u_agent)) { $bname = 'Opera'; $ub = "Opera"; }
	elseif(preg_match('/Netscape/i',$u_agent)) { $bname = 'Netscape'; $ub = "Netscape"; }

	// finally get the correct version number
	$known = array('Version', $ub, 'other');
	$pattern = '#(?<browser>' . join('|', $known) .
	')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
	if (!preg_match_all($pattern, $u_agent, $matches)) {
		// we have no matching number just continue
	}

	// see how many we have
	$i = count($matches['browser']);
	if ($i != 1) {
		//we will have two since we are not using 'other' argument yet
		//see if version is before or after the name
		if (strripos($u_agent,"Version") < strripos($u_agent,$ub)){ $version= $matches['version'][0]; }
		else { $version= $matches['version'][1]; }
	}
	else { $version= $matches['version'][0]; }

	// check if we have a number
	if ($version==null || $version=="") {$version="?";}
	return array('userAgent'=>$u_agent, 'name'=>$bname, 'nickname'=>$ub, 'version'=>$version, 'platform'=>$platform, 'pattern'=>$pattern);
}

//통합 메세지 발송 - 알림톡 분기 추가 :: 2018-03-05 lwh
function commonSendSMS($smsData){
	$CI =& get_instance();

	// 오프라인 관련으로는 문자나 이메일이 발송되지 않음
	// O2O 환경 체크 false : 일반 접속, true : 오프라인 요청
	// 해당 변수를 o2o 서비스가 시작되면 갱신함
	// common_base에서 선언함
	if($CI->o2o_pos_env){
		// kakaotalk/sms_send
		$result['msg']	= 'fail';
		$result['code']	= '525';
		return $result;
	}

	// 알림톡 분기처리 :: 2018-03-05 lwh
	$CI->load->model('kakaotalkmodel');
	$res = $CI->kakaotalkmodel->apiSender('sendTalk', $smsData);

	if($res['code'] == '200'){ // 알림톡 성공시라도 SmsData 가 변경됨.
		// 추후 알림톡 결과를 받아서 실패치 SMS 처리되도록 별도 구성
		// kakaotalk/sms_send
		$result['msg']	= '';
		$result['code']	= '0000';
		$result['kakao']= 'OK';
	}

	// 알림톡 발송에 따른 smsData 의 변조가 일어남. $res 에서 다시 받아야함.
	// 발송된 것은 smsData['talkYN'] 값이 Y로 기록
	// 예) join_member / order_user 가 발송되었을때 join_member 는 미사용 설정시 SMS 발송.
	// 이때 중앙서버에 발송 자체를 하지 않기 때문에 로그는 따로 찍히지 않음.
	if($res['SmsData'])	$smsData = $res['SmsData'];

	require_once ROOTPATH."/app/libraries/sms.class.php";

	$auth			= config_load('master');

	$sms_id			= $CI->config_system['service']['sms_id'];
	$sms_api_key	= $auth['sms_auth'];

	$gabiaSmsApi	= new gabiaSmsApi($sms_id,$sms_api_key);

	$result['msg']	= $gabiaSmsApi->sendSMS($smsData);
	$result['code']	= $gabiaSmsApi->getResultCode();

	return $result;
}

function commonCountSMS(){

	$CI =& get_instance();

	include_once ROOTPATH."/app/libraries/sms.class.php";
	$auth = config_load('master');
	$sms_id = $CI->config_system['service']['sms_id'];
	$sms_api_key = $auth['sms_auth'];

	$gabiaSmsApi = new gabiaSmsApi($sms_id,$sms_api_key);
	$params	= "sms_id=" . $sms_id . '&sms_pw=' . md5($sms_id);
	$params = makeEncriptParam($params);
	$limit	= $gabiaSmsApi->getSmsCount();

	return $limit;
}

function getSmsSendInfo(){
	$CI =& get_instance();
	$sms_id = $CI->config_system['service']['sms_id'];
	$orderUrl = get_connet_protocol().'sms.firstmall.kr/smsouth/getSendPhone';
	$queryString = 'sms_id='.$sms_id;

	$cu = curl_init();
	curl_setopt($cu, CURLOPT_URL,$orderUrl); // 데이터를 보낼 URL 설정
	curl_setopt($cu, CURLOPT_HEADER, FALSE);
	curl_setopt($cu, CURLOPT_FAILONERROR, TRUE);
	curl_setopt($cu, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded;charset=UTF-8'));
	curl_setopt($cu, CURLOPT_POST, 1); // 데이터를 get/post 로 보낼지 설정.
	curl_setopt($cu, CURLOPT_POSTFIELDS, $queryString); // 보낼 데이터를 설정. 형식은 GET 방식으로설정
	curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1); // REQUEST 에 대한 결과값을 받을 것인지 체크.#Resource ID 형태로 넘어옴 :: 내장 함수 curl_errno 로 체크
	curl_setopt($cu, CURLOPT_TIMEOUT,3); // REQUEST 에 대한 결과값을 받는 시간 설정.
	curl_setopt($cu, CURLOPT_SSL_VERIFYPEER, 0); //
	curl_setopt($cu, CURLOPT_SSL_VERIFYHOST, 1); //

	$result = curl_exec($cu); // 실행

	curl_close($cu);

	return trim($result);


}

//브랜드 네이밍 오름차순 정렬
function firstmallplus_brand_asc($x, $y) {
	 $x['title'] = strip_tags($x['title']);
	 $x['title'] = htmlspecialchars($x['title']);
	 $x['title'] = preg_replace ("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "", $x['title']);

	 $y['title'] = strip_tags($y['title']);
	 $y['title'] = htmlspecialchars($y['title']);
	 $y['title'] = preg_replace ("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "", $y['title']);

	if ($x['title'] == $y['title']){
		return 0;
	} else if ($x['title'] > $y['title']) {
		return 1;
	} else {
		return -1;
	}
}

//브랜드 네이밍 내림차순 정렬
function firstmallplus_brand_desc($x, $y) {
	 $x['title'] = strip_tags($x['title']);
	 $x['title'] = htmlspecialchars($x['title']);
	 $x['title'] = preg_replace ("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "", $x['title']);

	 $y['title'] = strip_tags($y['title']);
	 $y['title'] = htmlspecialchars($y['title']);
	 $y['title'] = preg_replace ("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "", $y['title']);

	if ($x['title'] == $y['title']){
		return 0;
	} else if ($x['title'] < $y['title']) {
		return 1;
	} else {
		return -1;
	}
}

### GET/POST변수 자동 병합
function getVars($except='', $request='')
{
	if ($except) $exc = explode(",",$except);
	if (is_array( $request ) == false) $request = $_REQUEST;
	foreach ($request as $k=>$v){
		if (!@in_array($k,$exc) && $v!=''){
			if (!is_array($v)) $ret[] = "$k=".urlencode(stripslashes($v));
			else {
				$tmp = getVarsSub($k,$v);
				if ($tmp) $ret[] = $tmp;
			}
		}
	}
	if ($ret) return implode("&",$ret);
}

function getVarsSub($key,$value)
{
	foreach ($value as $k2=>$v2){
		if ($v2!='') $ret2[] = $key."[".$k2."]=".urlencode(stripslashes($v2));
	}
	if ($ret2) return implode("&",$ret2);
}

function google_analytics_script(){
	$ga_auth = config_load('GA');
	echo "
	<script>
		(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
		(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
		m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
		})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
		ga('create', '{$ga_auth['ga_id']}', 'auto');
		ga('send', 'pageview');
	</script>";
	return;
}

//GA 전자상거래
function getTransactionJs($orders,$items) {
	//단가는 최종 쿠폰적용가 / 수량
	echo "
	<script type=\"text/javascript\">
		ga('require', 'ecommerce', 'ecommerce.js');
		ga('ecommerce:addTransaction', {
			'id': '{$orders['order_seq']}',
			'affiliation': '{$orders['referer_name']}',
			'revenue': '{$orders['settleprice']}',
			'shipping': '{$orders['tot_shipping_cost']}'
		});";

	foreach($items as $item){
		$price = floor($item['options'][0]['tot_sale_price']/$item['tot_ea']);
		echo "ga('ecommerce:addItem',{
			'id':'{$orders['order_seq']}',
			'sku':'{$item['goods_seq']}',
			'category':'{$item['goods_seq']}',
			'name':'{$item['goods_name']}',
			'quantity':'{$item['tot_ea']}',
			'price':'{$price}'
		});";
	}

	echo "
		ga('ecommerce:send');
	</script>";
	return;
}


function getPage_forGA($url){
	$CI =& get_instance();
	$code = "";
	$ret = "";
	$temp = "";

	$list_arr = array("goods/catalog"=>"카테고리","goods/brand"=>"브랜드","goods/search"=>"검색어","main/index"=>"메인페이지");
	foreach($list_arr as $k => $v){
		if(strstr($url, $k) !== false){
			$temp = $v;
		}
	}

    $aUrl   = parse_url($url);
    if( strstr($url, $_SERVER['HTTP_HOST']) !== false && $aUrl['path'] == '/' ){
        $temp = '메인페이지';
    }

	$reffer = explode("?",$url);

	parse_str($reffer[1]);

	if($temp == "카테고리"){
		$CI->load->model('categorymodel');
		$cate_name = $CI->categorymodel->get_category_name($code);
		if($cate_name) $ret = "카테고리:".$cate_name;
	}else if($temp == "브랜드"){
		$CI->load->model('brandmodel');
		$cate_name = $CI->brandmodel->get_brand_name($code);
		if($cate_name) $ret = "브랜드:".$cate_name;
	}else if($temp == "검색어"){
		$ret = "검색어:".$search_text;
	}else if($temp == "메인페이지"){
		$ret = $temp;
	}

	return $ret;
}

function get_brand_category_arr($seq){
	$CI =& get_instance();
	$CI->load->model('goodsmodel');
	$ret = array();
	$categoryArr = $CI->goodsmodel->get_goods_category($seq);
	$brandArr = $CI->goodsmodel->get_goods_brand($seq);
	if($categoryArr) foreach($categoryArr as $cate_item){
		if($ret['categoryData']) $ret['categoryData'] .= "/";
		$title = '';
		$title = str_replace("'","\'",$cate_item['title']);
		$title = str_replace("/","／",$title);
		$ret['categoryData'] .= $title;
	}

	if($brandArr) foreach($brandArr as $brand_item){
		if($ret['brandData']) $ret['brandData'] .= "/";
		$title = '';
		$title = str_replace("'","\'",$brand_item['title']);
		$title = str_replace("/","／",$title);
		$ret['brandData'] .= $title;
	}
	return $ret;
}

function get_all_brand_category_arr($param_item){
	$CI =& get_instance();
	$CI->load->model('goodsmodel');
	$ret = array();
	$goods_seq_arr = array();
	foreach($param_item as $item_arr){
		$goods_seq_arr[] = $item_arr['goods_seq'];
	}
	$ret = $CI->goodsmodel->all_brand_category(implode(",",$goods_seq_arr));
	return $ret;
}

//GA 향상된 전자상거래
function google_analytics($param,$method=null){
	 $CI =& get_instance();
	 $action = "";
	 $option = "";
	 $parent = "";
	 $event_flag = false;
	 if($method == "cart_add" || $method == "cart_remove" || $method == "payment"){
		  $parent = "parent.";
	 }
	 if($method == "refund") google_analytics_script();
	 /*
		  시작
	 */
	 $ga = "<script>";
	 $ga .= $parent."ga('set', 'nonInteraction', true);";
	 $ga .= "if(!".$parent."ga_require_ec)".$parent."ga('require', 'ec'); ";

	 $ga .= $parent."ga_require_ec = true;";

	 switch($method){
		case "list_count":
			//제품목록조회
			if(sizeOf($param['item']) == 0) break;
			$goods_category_brand_arr = get_all_brand_category_arr($param['item']);

			foreach($param['item'] as $item_arr){
				$item_arr['goods_name'] = str_replace("'","\'",$item_arr['goods_name']);
				$ga .= "
				ga('ec:addImpression', {
					'id': '{$item_arr['goods_seq']}',
					'name': '{$item_arr['goods_name']}',
					'category': '{$goods_category_brand_arr[$item_arr['goods_seq']]['categoryData']}',
					'brand': '{$goods_category_brand_arr[$item_arr['goods_seq']]['brandData']}',
					'list': '{$param['page']}'
				});
				";
			}
			break;
		case "view_count":
			//제품클릭수
			$brandCategory = get_brand_category_arr($param['item']['goods_seq']);
			$param['item']['goods_name'] = str_replace("'","\'",$param['item']['goods_name']);
			$ga .= "
			ga('ec:addProduct', {
				'id': '{$param['item']['goods_seq']}',
				'name': '{$param['item']['goods_name']}',
				'category': '{$brandCategory['categoryData']}',
				'brand': '{$brandCategory['brandData']}'
			});";
			$param['page']  =   addslashes($param['page']);
			if($param['action'] == "detail"){
				$ga .="
				before_page = '{$param['page']}';
				$(\"form[name='goodsForm']\").append(\"<input type='hidden' name='referer_page_ga' value='{$param['page']}'>\");
				";
			}else{
				$event_flag = true;
			}
			$option = ", {'list': '{$param['page']}'}";
			break;
		case "cart_add":
			//장바구니추가
			$brandCategory = get_brand_category_arr($param['item']['goods_seq']);
			$event_flag = true;

			//옵션 개선으로 인해 옵션 처리 부분 수정 2015-06-16 jhr
			$CI->load->model('cartmodel');
			$CI->load->model('goodsmodel');
			$cart_option = $CI->cartmodel->get_cart_option($param['item']['cart_seq']);
			$goodsinfo = $CI->goodsmodel->get_goods($param['item']['goods_seq']);
			$goodsinfo['goods_name'] = str_replace("'","\'",$goodsinfo['goods_name']);

			foreach($cart_option as $cart_option_arr){
				$goods_option = "";

				for($i=1;$i<5;$i++){
					if($goods_option && $cart_option_arr['option'.$i]) $goods_option .= "/";
					$goods_option .= $cart_option_arr['option'.$i];
				}

				$ga .= $parent."ga('ec:addProduct', {
					'id': '{$param['item']['goods_seq']}',
					'name': '{$goodsinfo['goods_name']}',
					'category': '{$brandCategory['categoryData']}',
					'brand': '{$brandCategory['brandData']}',
					'variant': '{$goods_option}',
					'quantity': '{$cart_option_arr['ea']}'
				});
				";
			}

			$option = ", {'list': parent.before_page}";
			break;
		case "cart_remove":
			$event_flag = true;
			foreach($param['item'] as $item_arr){
				$brandCategory = get_brand_category_arr($item_arr['goods_seq']);

				$goods_option = "";
				for($i=1;$i<5;$i++){
					if($goods_option && $item_arr['option'.$i]) $goods_option .= "/";
					$goods_option .= $item_arr['option'.$i];
				}
				$item_arr['goods_name'] = str_replace("'","\'",$item_arr['goods_name']);
				$ga .= $parent."ga('ec:addProduct', {
					'id': '{$item_arr['goods_seq']}',
					'name': '{$item_arr['goods_name']}',
					'category': '{$brandCategory['categoryData']}',
					'brand': '{$brandCategory['brandData']}',
					'variant': '{$goods_option}',
					'quantity': '{$item_arr['ea']}'
				});
				";
			}
			break;
		case "payment":
			//결제 횟수
			$event_flag = true;
			foreach($param['item'] as $item_arr){
				$brandCategory = get_brand_category_arr($item_arr['goods_seq']);

				$goods_option = "";
				for($i=1;$i<5;$i++){
					if($goods_option && $item_arr['option'.$i]) $goods_option .= "/";
					$goods_option .= $item_arr['option'.$i];
				}
				$item_arr['goods_name'] = str_replace("'","\'",$item_arr['goods_name']);
				$ga .= $parent."ga('ec:addProduct', {
					'id': '{$item_arr['goods_seq']}',
					'name': '{$item_arr['goods_name']}',
					'category': '{$brandCategory['categoryData']}',
					'brand': '{$brandCategory['brandData']}',
					'variant': '{$goods_option}',
					'quantity': '{$item_arr['ea']}'
				});
				";
			}
			$option = ", {'step': 1,'option':'{$param['payment']}','list': '{$param['page']}'}";
			break;
		case "order_complete":
			$CI->load->model('couponmodel');
			$CI->load->model('promotionmodel');
			$total_tax = 0;
			//결제완료
			foreach($param['item'] as $item){
				$brandCategory = get_brand_category_arr($item['goods_seq']);

				$coupon = "";
				$coupon_js = "";

				if($item['options'][0]['coupon_sale'] > 0){
					$temp = $CI->couponmodel->get_download_coupon($item['options'][0]['download_seq']);
					$coupon = $temp['coupon_name'];
				}
				//할인쿠폰과 프로모션 둘다 있을 경우엔 쿠폰만 잡히도록 한다.
				if($item['options'][0]['promotion_code_sale'] > 0 && $coupon == ""){
					$temp = $CI->promotionmodel->get_download_promotion($item['options'][0]['promotion_code_seq']);
					$coupon = $temp['promotion_name'];

				}
				if($coupon != "") $coupon_js = "'coupon': '{$coupon}',";

				$goods_option = "";
				for($i=1;$i<5;$i++){
					if($goods_option && $item['options'][0]['option'.$i]) $goods_option .= "/";
					$goods_option .= $item['options'][0]['option'.$i];
				}

				//할인된 개별 가격 합계
				$op_price = 0;
				foreach($item['options'] as $opval){
					$op_price += $opval["tot_sale_price"];
				}

				$price = floor($op_price/$item['tot_ea']);
				//과세
				if($item["tax"] == "tax"){
					//세금빼고 개별가격
					$price = floor(round(($op_price/$item['tot_ea'])/1.1));
					$total_tax += ($op_price-($price*$item['tot_ea']));
				}
				$item['goods_name'] = str_replace("'","\'",$item['goods_name']);
				$ga .= $parent."
				ga('ec:addProduct', {
					'id': '{$item['goods_seq']}',
					'name': '{$item['goods_name']}',
					'category': '{$brandCategory['categoryData']}',
					'brand': '{$brandCategory['brandData']}',
					'variant': '{$goods_option}',
					'price':'{$price}',
					{$coupon_js}
					'quantity':'{$item['tot_ea']}'
				});";
			};

			$order_coupon = "";
			$order_coupon_js = "";

			if($param['orders']['coupon_sale'] > 0){
				$temp = $CI->couponmodel->get_download_coupon($param['orders']['download_seq']);
				$order_coupon = $temp['coupon_name'];
			}
			//할인쿠폰과 프로모션 둘다 있을 경우엔 쿠폰만 잡히도록 한다.
			if($param['orders']['shipping_promotion_code_sale'] > 0 && $order_coupon == ""){
				$temp = $CI->promotionmodel->get_download_promotion($param['orders']['shipping_promotion_code_seq']);
				$order_coupon = $temp['promotion_name'];
			}
			if($order_coupon != "") $order_coupon_js = "'coupon': '{$order_coupon}',";
			$tax = $tax_temp['comm_vat_mny'];

			$order_price = $param['orders']['tot_shipping_cost'];
			//과세상품이 하나라도 있을 경우 배송비도 과세
			if($total_tax > 0){
				$order_price = round($param['orders']['tot_shipping_cost']/1.1);
				$total_tax += ($param['orders']['tot_shipping_cost']-$order_price);
			}
			$ga .= $parent."
			ga('ec:setAction', 'purchase', {
				'id': '{$param['orders']['order_seq']}',
				'affiliation': '{$param['orders']['referer_name']}',
				'revenue': '{$param['orders']['settleprice']}',
				'tax': '{$total_tax}',
				{$order_coupon_js}
				'shipping': '{$order_price}'
			});
			";
			break;
		case "refund":
			//환불
			foreach($param['item'] as $item_arr){
				$ga .= $parent."
				ga('ec:addProduct', {
					'id': '{$item_arr['goods_seq']}',
					'quantity': '{$item_arr['ea']}'
				});
				";
			}
			$option = ", {'id': '{$param['order_seq']}'}";
			break;
		case "promotion":
			//프로모션
			$param['title'] = str_replace("'","\'",$param['title']);
			$param['tpl_path'] = str_replace("'","\'",$param['tpl_path']);
			$ga .= $parent."
			ga('ec:addPromo', {
				'id': '{$param['event_seq']}',
				'name': '{$param['title']}',
				'creative': '{$param['tpl_path']}'
			});";
			break;
	 }

	 /*
		  액션 정의
	 */

	if($param['action']) $ga .= $parent."ga('ec:setAction', '{$param['action']}' {$option}); ";

	 /*
		  마지막 데이터 전송 부분
	 */

	if($method != "promotion"){
		if($event_flag){
			$ga .= $parent."ga('send', 'event', 'common', 'click', '".$method."');";
		}else{
			$ga .= $parent."ga('send','event','Ecommerce','Impression',{'nonInteraction': 1});";
		}
	}else{
		$ga .= $parent."ga('send', 'event', 'common', 'click', '{$param['title']}');";
	}

	 $ga .= "</script>";
	 return $ga;
}

function monthAddMinus($dateStr = '', $month) {
	if ($dateStr == '') {
		return date("Y-m-01", mktime(0, 0, 0, date("m") + $month, date("d"), date("Y")));
	} else {
		$a = explode('-', $dateStr);
		return date('Y-m-01', mktime(0, 0, 0, $a[1] + $month, $a[2], $a[0]));
	}
}

function dateDiffMonth($datefrom, $dateto, $using_timestamps = false) {
	if (!$using_timestamps) {
		$datefrom = strtotime($datefrom, 0);
		$dateto = strtotime($dateto, 0);
	}
	$difference = $dateto - $datefrom; // Difference in seconds
	$months_difference = floor($difference / 2678400);
		while (mktime(date("H", $datefrom), date("i", $datefrom), date("s", $datefrom), date("n", $datefrom)+($months_difference), date("j", $dateto), date("Y", $datefrom)) < $dateto) {
		$months_difference++;
	}
	$months_difference--;
	$datediff = $months_difference;
	return $datediff;
}

//문자열을 아스키코드로 변경 & 아스키코드를 문자열로 변경
function strForASCII($str,$type){
	$ret = array();
	if($type == 'enc'){
		$l = strlen($str);
		for($x=0;$x<$l;$x++){
			$ret[$x] = ord($str{$x});
		}
	}else{
		foreach($str as $v){
			$ret[] = chr($v);
		}
	}
	return $ret;
}

function array_max_key($array,$keys){
	$max_key = null;
	$max_value = null;

	foreach($keys as $key){
		if(is_null($max_key) || $array[$key]>$max_value){
			$max_key = $key;
			$max_value = $array[$key];
		}
	}

	return $max_key;
}

function folder_copy($odir,$ndir) {
	if(filetype($odir) === 'dir') {
	clearstatcache();

	if($fp = @opendir($odir)) {
		while(false !== ($ftmp = readdir($fp))){
			if(($ftmp !== ".") && ($ftmp !== "..") && ($ftmp !== "")) {
				if(filetype($odir.'/'.$ftmp) === 'dir') {
					clearstatcache();

					@mkdir($ndir.'/'.$ftmp);
					echo ($ndir.'/'.$ftmp."<br />\n");
					set_time_limit(0);
					folder_copy($odir.'/'.$ftmp,$ndir.'/'.$ftmp);
				} else {
					copy($odir.'/'.$ftmp,$ndir.'/'.$ftmp);
				}
			}
		}
	}
	if(is_resource($fp)){
		 closedir($fp);
	}
	} else {
	echo $ndir."<br />\n";
	copy($odir,$ndir);
	}
}

function get_simple_memo($group, $num = false){
	$CI		=& get_instance();
	$moemo_val[]	= $group;
	if($num !== false){
		$where			= " AND memo_num = ?";
		$moemo_val[]	= $num;
	}

	$sql	= "SELECT * FROM fm_memo  WHERE memo_group = ? {$where} ORDER BY memo_idx DESC";
	$query	= $CI->db->query($sql,$moemo_val);
	$rows	= $query->result_array();

	return $rows;
}

function set_simple_memo($group, $num, $memo){
	$CI			=& get_instance();
	$num		= ((int)$num < 1) ? 0 : $num;
	$chk_memo	= get_simple_memo($group, $num);
	if(isset($chk_memo[0]['memo_idx']) !== false){
		//있으면 update
		$sql	= "UPDATE `fm_memo` SET `memo`='{$memo}', `update_time`=NOW() WHERE `memo_group`='{$group}' AND `memo_num`='{$num}'";
	}else{
		//없으면 insert
		$sql	= "INSERT INTO `fm_memo` SET `memo_group`='{$group}', `memo_num`='{$num}', `memo`='{$memo}', `update_time`=NOW()";
	}

	return $CI->db->query($sql);
}

function get_skin_version($skin,$name){
	$CI			=& get_instance();
	$skinPath = APPPATH."../data/skin/";
	$configurationPath = $skinPath.$skin."/configuration/skin.ini";
	if( $CI->config_system[$name] ) {
		return $CI->config_system[$name];
	}else{
		if(file_exists($configurationPath)) {
			$configuration = parse_ini_file($configurationPath);
			config_save('system',array($name=>$configuration['mobile_version']));
			return $configuration['mobile_version'];
		} else return null;
	}
}

function get_hangul_amount($num_amount, $hangul_amount = ''){

	if( strstr($num_amount,".") ) {//소수점있을경우 앞자리만 체크 @2016-08-03 ysm
		$num_amount_ar	= explode(".",$num_amount);
		$num_amount		= $num_amount_ar[0];
	}
	$num_base		= array('',10,100,1000,10000,10000,10000,10000,100000000,100000000,100000000,100000000,1000000000000,1000000000000,1000000000000,1000000000000);
	$hangul_unit	= array('','십','백','천','만','만','만','만','억','억','억','억','조','조','조','조');
	$hangul_num		= array('','일','이','삼','사','오','육','칠','팔','구');

	if($num_amount  > 9){
		$num_count		= strlen((int)$num_amount) - 1;
		$check_num		= floor($num_amount / $num_base[$num_count]);
		$next_amount	= $num_amount - (int)$check_num * $num_base[$num_count];
		$fix_count		= strlen($check_num) - 1;
	}else{
		$check_num		= $num_amount;
		$next_amount	= 0;
		$fix_count		= strlen($check_num) - 1;
	}

	$tmp_hangul		= '';

	for($i = $fix_count; $i >= 0; $i--){
		$now_num	= substr($check_num,$fix_count - $i,1);
		if($now_num ==  0)	continue;
		//십자리 1은(일십)은 표시 안함
		$tmp_hangul	.= ($now_num == 1 && $num_amount < 20 && $num_amount > 9) ? $hangul_unit[$i] : $hangul_num[$now_num].$hangul_unit[$i];
	}

	$hangul_amount	= $hangul_amount.$tmp_hangul.$hangul_unit[$num_count];

	if($next_amount > 0){
		return get_hangul_amount($next_amount, $hangul_amount);
	}else{
		return $hangul_amount;
	}

}


function get_currency_symbol($price_type = 'all'){
	$currency_symbol['KRW']		= array('symbol' => '&#x20a9;',	'hangul' => '원');
	$currency_symbol['USD']		= array('symbol' => '&#x24;',	'hangul' => '달러');
	$currency_symbol['CNY']		= array('symbol' => '&yen;',	'hangul' => '위안');
	$currency_symbol['JPY']		= array('symbol' => '&yen;',	'hangul' => '엔');
	$currency_symbol['EUR']		= array('symbol' => '&euro;',	'hangul' => '유로');
	$currency_symbol['TRY']		= array('symbol' => '&#x20a4;', 'hangul' => '리라');
	$currency_symbol['INR']		= array('symbol' => '&#x20B9;', 'hangul' => '루피');
	$currency_symbol['UAH']		= array('symbol' => '&#x20b4;', 'hangul' => '흐리브냐');
	$currency_symbol['MNT']		= array('symbol' => '&#x20ae;', 'hangul' => '투그릭');
	$currency_symbol['PYG']		= array('symbol' => '&#x20b2;', 'hangul' => '과라니');
	$currency_symbol['PHP']		= array('symbol' => '&#x20b1;', 'hangul' => '페소');
	$currency_symbol['LAK']		= array('symbol' => '&#x20ad;', 'hangul' => '키프');
	$currency_symbol['ILS']		= array('symbol' => '&#x20aa;', 'hangul' => '셰켈');
	$currency_symbol['VND']		= array('symbol' => '&#x20ab;', 'hangul' => '동');
	$currency_symbol['RUB']		= array('symbol' => '&#x20bd;', 'hangul' => '루블');

	if($price_type == 'all')	return $currency_symbol;
	else						return $currency_symbol[$price_type];
}

/*
숫자를 절사합니다.
*/
function cutting_number($number,$pos,$mode){
	$multiply = 0;
	$divide = 0;
	switch ($pos){
		case "0.001" :	// 소수셋째짜리 절사
			$multiply = 100;
			break;
		case "0.01" :	// 소수둘짜리 절사
			$multiply = 10;
			break;
		case "0.1" :	// 소수첫재 절사
			$multiply = 1;
			break;
		case "1" :		// 일원단위
			$divide = 10;
			break;
		case "10" :		// 십원단위
			$divide = 100;
			break;
		case "100" :	// 백원단위
			$divide = 1000;
			break;
	}
	if($multiply){
		$number = $number * $multiply;
	}else if($divide){
		$number = $number / $divide;
	}
	switch ($mode){
		case "ascending" :
				$number = ceil($number);
			break;
		case "rounding" :
				$number = round($number);
			break;
		case "dscending" :
				$number = floor($number);
			break;
	}
	if($multiply){
		$number = $number / $multiply;
	}else if($divide){
		$number = $number * $divide;
	}
	return $number;
}

# 절삭처리
function get_cutting_price($price=0,$currency='basic',$mode='front'){

	$CI =& get_instance();

	// 이미 쉼표 처리된 숫자일 경우 get_cutting_price가 쉼표 이후 숫자를 잘라버리는 문제 해결
	// @author Sunha Ryu 2019-07-16
	if(strstr($price, ",") !== false) {
	    $price = str_replace(",","", $price);
	}

	if(!$currency || $currency=='basic') $currency = $CI->config_system['basic_currency'];

	# front용 : 설정 > 상점정보 설정에 따라 처리
	if($mode == "front"){

		$currency_info		= get_currency_info($currency);

		//절삭단위,절삭기준
		$cutting_price		= $currency_info['cutting_price'] * 10;
		$cutting_action		= $currency_info['cutting_action'];


		if($cutting_price > 0){
			$price_tmp = ($price / $cutting_price);
		}else{
			$price_tmp = $price;
		}

		$ori_price = $price;

		switch($cutting_action){
			case "dscending":
				$price		= pfloor($price_tmp) * $cutting_price;
			break;
			case "rounding":
				$price		= round($price_tmp) * $cutting_price;
			break;
			case "ascending":
				$price		= pceil($price_tmp) * $cutting_price;
			break;
		}
	# backoffice(admin)용 : 원화,엔화 소수점 버림/ 그외 소수점 3째자리 버림.
	}else{

		if(in_array($currency,array("KRW","JPY"))){
			$price = (int) pfloor($price);
		}else{
			$price = pfloor($price * 100) / 100;
		}
	}

	return $price;
}

function get_currency_info($currency=''){

	$CI		=& get_instance();
	$cfg	= ($CI->config_system)? $CI->config_system : config_load("system");

	if(!$currency) $currency = "basic";

	if($currency == "basic")		$currency = $cfg['basic_currency'];			//기본통화
	else $currency = $currency; 	//기타 사용자 지정통화

	$currency_info = $CI->config_currency_all[$currency]; // 비교통화가 설정안되어 있어도 통화 정보 가져옴

	return $currency_info;
}



	function get_base_config_system(){
		$CI		=& get_instance();

		if(!$CI->cache) {
			// 캐시 드라이버를 로드하고, 사용하는 드라이버로 APC를 지정하고, APC를 사용할 수 없는 경우 파일 기반 캐싱으로 대체
			$CI->load->driver('cache', array('adapter' => 'apc', 'backup' => 'file'));
		}

		$CI->config_system = ($CI->config_system) ? $CI->config_system : config_load('system');

		$CI->config_system['time_split'] = explode(' ',microtime());
		$CI->config_system['time_start'] = $CI->config_system['time_split'][0]+$CI->config_system['time_split'][1];

		// 관리자 환경 전체 로드후 캐시 적용
		$cache_item_id = 'admin_env_all';
		if ( ! $admin_env_all = cache_load($cache_item_id)) {
			$CI->load->model('adminenvmodel');
			$env_query	= $CI->adminenvmodel->get_all();
			$env_data	= $env_query->result_array();

			$admin_env_all = array();
			foreach($env_data as $v){
				$admin_env_all[$v['shopSno']] = $v;
			}
			if (! is_cli()) {
				cache_save($cache_item_id, $admin_env_all);
			}
		}

		$currency_amout = array();
		foreach(code_load('currency_amout') as $amout){
			$currency_amout[$amout['codecd']] = $amout['value'];
		}

		//
		$configShopSno = $CI->config_system['shopSno'];
		$row = $admin_env_all[$configShopSno];

		$CI->config_system['admin_env_seq']		= $row['admin_env_seq'];
		$CI->config_system['basic_currency']		= $row['currency'];
		$CI->config_system['compare_currency']	= $row['compare_currency'];
		$CI->config_system['language']			= $row['language'];
		$CI->config_system['domain']				= $row['domain'];
		$CI->config_system['basic_amout']			= $currency_amout;	//환율 기준액
		$CI->config_system['first_goods_date']	= $row['first_goods_date'];
		$CI->config_system['solution_division']	= serviceLimit('H_AD');
		$CI->config_system['favicon']				= $row['favicon'];

		// 관리자가 설정하지 않으면 기본 언어에 따라 기본 배송 국가 변경
		if(!isset($CI->config_system['cfg_default_nation'])) {
			switch ($CI->config_system['language']) {
				case "US" :
					$CI->config_system['cfg_default_nation'] = 'U.S.A';
					break;
				case "CN" :
					$CI->config_system['cfg_default_nation'] = 'CHINA';
					break;
				case "JP" :
					$CI->config_system['cfg_default_nation'] = 'JAPAN';
					break;
				default :
					$CI->config_system['cfg_default_nation'] = 'KOREA';
					break;
			}
		}
		foreach(code_load('shipping_nation') as $nation){
			$shipping_nation[$nation['codecd']] = $nation['value'];
		}
		$CI->config_system['default_nation'] = $shipping_nation[$CI->config_system['cfg_default_nation']]." (".$CI->config_system['cfg_default_nation'].")";

		$compare_currency = explode(",",$CI->config_system['compare_currency']);

		$cache_item_id = sprintf('currency_%s', $CI->config_system['admin_env_seq']);
		if ( ! $currency_list_tmp = cache_load($cache_item_id)) {
			$currency_list_tmp = array();
			$CI->load->model('currencymodel');
			$query	= $CI->currencymodel->get(array('admin_env_seq'=>$CI->config_system['admin_env_seq']));
			foreach($query->result_array() as $row){
				$currency_list_tmp[$row['currency']]	= $row;
			}

			if (! is_cli()) {
				cache_save($cache_item_id, $currency_list_tmp);
			}
		}

		//
		$curr_cnt = 0;
		$admin_currency = array();
		foreach($currency_list_tmp as $row){
			$currency_list_tmp[$row['currency']]	= $row;
			$admin_currency[$curr_cnt]['currency'] = $row['currency'];
			$admin_currency[$curr_cnt]['currency_symbol'] = $row['currency_symbol'];
			$curr_cnt++;
		}

		// 모든 통화 정보 정의
		$CI->config_currency_all = $currency_list_tmp;

		# 비교통화 노출 순서에 맞게 재정렬
		$currency_list = array();
		$currency_list[$CI->config_system['basic_currency']] = $currency_list_tmp[$CI->config_system['basic_currency']];
		foreach($compare_currency as $currency){
			$currency_list[$currency]	= $currency_list_tmp[$currency];
		}
		$CI->config_currency = $currency_list;

		define("SERVICE_CODE",$CI->config_system['service']['code']);
		define("SERVICE_NAME",$CI->config_system['service']['name']);

		//멀티 버젼
		$env_all			= false;
		$env_except_list	= array('admin/setting/goods','admin/setting/multi');
		$this_admin_env		= array();
		$env_list			= array();

		$uri_str = uri_string();
		foreach($env_except_list as $except){
			if	(strpos($uri_str,$except) !== false)
				$env_all	= true;
		}

		$lang_arr = array(
			'KR' => '한국어','JP' => '日本語','CN' => '中國語','US' => 'English',
		);
		$lang_arr_select = array(
			'KR' => 'Korea','JP' => 'Japan','CN' => 'China','US' => 'English',
		);
		$lang_img_arr = array(
			'KR' => '/data/brand_country/kr.png',
			'JP' => '/data/brand_country/jp.png',
			'CN' => '/data/brand_country/cn.png',
			'US' => '/data/brand_country/us.png',
		);
		//print_r($admin_currency);
		foreach($admin_env_all as $v){
			if	(!$v['domain']) $v['domain']	= $v['temp_domain'];
			if	($v['shopSno'] == $CI->config_system['shopSno']){
				$this_admin_env['env_seq']		= $v['admin_env_seq'];
				$this_admin_env['env_name']		= $v['admin_env_name'];
				$this_admin_env['currency']		= $v['currency'];
				$this_admin_env['language']		= $v['language'];
				$this_admin_env['domain']		= $v['domain'];
				$this_admin_env['temp_domain']	= $v['temp_domain'];
				$this_admin_env['lang']			= $lang_arr[$v['language']];
				$v['this_admin']				= 'y';
			}
			$v['lang_img']					= "<img src = '".get_connet_protocol().$v['domain'].$lang_img_arr[$v['language']]."' style = 'margin-right:5px;'>";
			$v['lang']							= $lang_arr[$v['language']]; //치환코드용
			$v['lang_list']						= $lang_arr_select[$v['language']]; //언어선택목록용
			foreach($admin_currency as $c_list) {
				if($v['currency'] == $c_list['currency']) {
					$v['currency_symbol'] = $c_list['currency_symbol'];
				}
			}
			$env_list[]							= $v;
		}

		$CI->config_system['admin_env_name']	= $this_admin_env['env_name'];
		$CI->config_system['domain']			= $this_admin_env['domain'];
		$CI->config_system['subDomain']		= $this_admin_env['temp_domain'];

		$CI->config_system['phpSkin'] = config_load('phpSkin', 'able');

		## operation type 설정 로드 :: 2018-12-27 lwh
		$CI->operation_type = (!empty($CI->config_system['operation_type']) && $CI->config_system['operation_type'] != 'fixed') ? strtolower($CI->config_system['operation_type']) : 'heavy';

		$CI->template->assign(array('env_list'=>$env_list));
		$CI->template->assign(array('this_admin_env'=>$this_admin_env));
		$CI->template->assign(array('env_all'=>$env_all));

	}



# 노출할 통화에 따라 표기. @2016-05-24 pjm
# price : 통화 금액
# mode
#		null : number_format 처리
#		1 : number_format 처리 안함
#		2 : number_format 처리 + 설정된 통화심볼 노출
#		3 : number_format 처리 + 통화단위(KRW,USD,CNY,EUR,JPY) 노출(backoffice용)
#		4 : number_format 처리 + 통화단위(한글로 표시) 노출(front용)
# currency : 통화단위
#		null or basic : 기본통화 자동 노출됨
#		KRW,USD,CNY,JPY,EUR : 지정된 통화
# replace_str : 사용자 정의 str , 치환코드 : _str_price_(가격)
# symbol_class : 통화심볼에 적용할 css classname
function get_currency_price($price,$mode='',$currency='',$replace_str='',$symbol_class=''){

	$currency_info	= get_currency_info($currency);
	extract($currency_info);

	// 금액 계산은 mode 3으로 처리
	$tmp_mode		= $mode;
	if	($mode == 4){
		$tmp_mode	= 4;
		$mode		= 3;
	}

	if($mode == 3){
		$cuttong_mode	= "admin";
	}else{
		$cuttong_mode	= "front";
	}

	if(!$price) $price = 0;

	/*20171221 콤마제거*/
	$price = str_replace(',','',$price);

	$price = get_cutting_price($price,$currency,$cuttong_mode);

	#26734 설정을 따르지 않아서 수정 kmj
	if($mode != 1) { //1은 number_format 처리 안함
		if( in_array($currency,array("KRW", "JPY")) ){
			$price = number_format($price);
		} else {
			$float_count = strpos($currency_info['cutting_price'], '1') - 2;
			if($float_count > 0){
				$price = number_format($price, $float_count, '.', ',');
			} else {
				$price = number_format($price);
			}
		}
	}

	# 치환처리
	if($replace_str){
		$price = str_replace("_str_price_",$price,$replace_str);
	}

	# symbol class 적용
	if($symbol_class){
		$currency_symbol = "<span class='".$symbol_class."'>".$currency_symbol."</span>";
	}

	// 단위 노출은 원래 mode로 처리
	$mode		= $tmp_mode;

	# 통화단위 및 심볼 노출(설정값대로)
	if($mode == 2){
		if($currency_symbol_position == "before"){
			$price = $currency_symbol.$price;
		}elseif($currency_symbol_position == "after"){
			$price .= $currency_symbol;
		}
	// 통화단위 및 심볼 노출(통화단위 무조건 뒤에 노출)
	}elseif($mode == 3){
		$price .= $currency;

	// 한글로 표시
	}elseif($mode == 4){
		$currency_symbol = get_currency_symbol($currency);
		$price .= $currency_symbol['hangul'];
	}

	return $price;

}


# 환율
function get_exchange_rate($basic_currency=''){

	$CI				=& get_instance();

	$basic_currency = (!$basic_currency)? $CI->config_system['basic_currency']:$basic_currency;
	$basic_amout	= code_load('currency_amout',$basic_currency);	//기본통화의 환율 기준액

	return $basic_amout[0]['value'];
}

# 비교통화 환율 적용 금액
# price				: 금액
# currency			: 환율 적용할 통화
# basic_currency	: 기본통화
# cutting_mode		: 절삭처리 기준(front / admin)
function get_currency_exchange($price,$currency,$basic_currency='',$cutting_mode='front'){

	$CI				=& get_instance();

	$exchange_rate	= get_exchange_rate($basic_currency);	//기본통화의 환율 기준액
	$currency_info	= get_currency_info($currency);
	$return_price	= 0;

	if(!$exchange_rate) $exchange_rate = 0;
	if(!$currency_info['currency_exchange']) $currency_info['currency_exchange'] = 0;

	if($exchange_rate > 0 && $currency_info['currency_exchange'] > 0){
		$return_price = $price / $exchange_rate * $currency_info['currency_exchange'];
	}else{
		$return_price = 0;
	}

	//절삭처리
	$return_price		= get_cutting_price($return_price,$currency,$cutting_mode);
	//$return_price = get_currency_price($return_price,'',$currency);

	return $return_price;
}

# 1. 주문엑셀의 전체적인 KRW 금액 표기
# - 소수점 이하 존재하는 금액 : 소수점 이하 표기
# - 소수점 이하 존재하지 않는 금액 : 원단위까지만
function get_krw_currency($price){

	$_price = explode(".",$price);

	if((int)$_price[1] > 0){

		$price = (int)$_price[0].".".(int)$_price[1];
	}else{
		$price = (int)$price;
	}

	return $price;
}


function get_texttype($basic_currency=''){

	if(!$basic_currency){
		$cfg = ($CI->config_system)? $CI->config_system : config_load("system");
		$basic_currency		= $cfg['basic_currency'];
	}

	if(in_array($basic_currency,array("KRW","JPY"))){
		$inputtype = "onlynumber";
	}elseif(in_array($basic_currency,array("USD","EUR","CNY"))){
		$inputtype = "onlyfloat";
	}
	echo $inputtype;
}

function getAlert($code = null, $args = null) {
	if ( ! $code) {
		return;
	}
	$CI =& get_instance();
	$lang = 'KR';
	if ($CI->config_system['language']) {
		$lang = $CI->config_system['language'];
	}
	if (preg_match('/^admin\//',uri_string())) {
		$lang = 'KR';
	}
	$cache_item_id = sprintf('alert_%s_%s', $code, $lang);
	if ( ! $CI->aAertMsg[$code]) {
		$ret = cache_load($cache_item_id);
		if ($ret === false) {
			$query = $CI->db->select('*')->from('fm_alert')->where('code', $code)->limit(1)->get_compiled_select();
			$query = str_replace('*', 'SQL_NO_CACHE *', $query);
			$query = $CI->db->query($query);
			$rows = $query->row_array();
			$ret = $rows[$lang];
			$ret = str_replace('%n', '\n', $ret);
			$ret = str_replace('%b', '<br />', $ret);
			if ( ! is_cli()) {
				cache_save($cache_item_id, $ret);
			}
		}
		$CI->aAertMsg[$code] = $ret;
	} else {
		$ret = $CI->aAertMsg[$code];
	}
	if (strpos($ret, '%s') > -1) {
		preg_match_all('/%s/', $ret, $str_match);
		$str_len = sizeOf($str_match[0]);
		$arr_len = sizeOf($args);

		if ($str_len > $arr_len) {
			if ( ! is_array($args)) {
				$args_temp = $args;
				$args = Array();
				$args[] = $args_temp;
				$arr_len = 1;
			}
			for	($i=0; $i<$str_len-$arr_len; $i++) {
				array_push($args,'');
			}
		} else if($str_len < $arr_len) {
			$len = $arr_len - $str_len;
			for	($i=0; $i<$len; $i++) {
				unset($args[($arr_len--)-1]);
			}
		}
		$ret = is_array($args) ? vsprintf($ret, $args) : sprintf($ret, $args);
	}
	return $ret;
}

function getAlert_for_text($text_lang = null, $text = null, $args = null){
	if ( ! $text_lang || ! $text) {
		return $text;
	}
	$CI =& get_instance();
	$lang = $CI->config_system['language'];
	if (preg_match('/^admin\//', uri_string())) {
		$lang = 'KR';
	}
	$query = $CI->db->select('*')->from('fm_alert')->where($text_lang, $text)->limit(1)->get_compiled_select();
	$query = str_replace('*', 'SQL_NO_CACHE *', $query);
	$query = $CI->db->query($query);
	$rows = $query->row_array();
	$ret = $rows[$lang];
	$ret = str_replace('%n', '\n', $ret);
	$ret = str_replace('%b', '<br />', $ret);
	if (strpos($ret,'%s') >  -1) {
		preg_match_all('/%s/', $ret, $str_match);
		$str_len = sizeOf($str_match[0]);
		$arr_len = sizeOf($args);
		if ($str_len > $arr_len) {
			if (!is_Array($args)) {
				$args_temp = $args;
				$args = Array();
				$args[] = $args_temp;
				$arr_len = 1;
			}
			for	($i=0; $i<$str_len-$arr_len; $i++) array_push($args, '');
		}else if($str_len < $arr_len) {
			$len = $arr_len-$str_len;
			for	($i=0; $i<$len; $i++) {
				unset($args[($arr_len--)-1]);
			}
		}
		$ret = is_Array($args) ? vsprintf($ret, $args) : sprintf($ret, $args);
	}
	if (!$ret) {
		$ret = $text;
	}
	return $ret;
}

function get_all_field($table,$except=array()){
	$fields	= array();
	$CI		=& get_instance();
	$query	= $CI->db->query('desc '.$table);
	foreach($query->result_array() as $data){
		if ( in_array($data['Field'], $except) ){
			continue;
		}
		$fields[] = $data['Field'];
	}
	return $fields;
}

/**
* 취약점 체크
* goods 상품/카테고리/브랜드/지역
 - secure_vulnerability('goods', 'no', $_GET['no']);
* board 게시판
 - secure_vulnerability('board', 'boardid', $_GET['id']);
 - secure_vulnerability('board', 'seq', $_GET['seq']);
* member 회원
* @2016-10-19
**/
function secure_vulnerability($form, $type, $ckval, $parent=null,$msgtmp=''){

	if(!$msgtmp) $msgtmp=getAlert('mp094');

	$msg = "";
	if( $form == 'board' ) {
		switch($type){
		 case( 'boardid' ):
			if(!$ckval|| !preg_match("/^([a-z0-9\_\-]+)$/i", $ckval) ) $msg = $msgtmp;
		 break;
		 case( 'seq' ):
			if(!$ckval || !preg_match("/^([0-9]+)$/i", $ckval) ) $msg = $msgtmp;
		 break;
		 case( 'goods_seq' ):
		 case( 'order_seq' ):
			if($ckval && !preg_match("/^([0-9]+)$/i", $ckval) ) $msg = $msgtmp;
		 break;
		}
	}elseif( $form == 'member' ) {
		switch($type){
		 case( 'order_auth' ):
			if($ckval && $ckval != 1 ) $msg = $msgtmp;
		 break;
		}
	}elseif( $form == 'goods' ) {
		switch($type){
		 case( 'no' ):
		 case( 'order_seq' ):
			if($ckval && !preg_match("/^([0-9]+)$/i", $ckval) ) $msg = $msgtmp;
		 break;
		}
	}
	if($msg) {
		if( $parent ) {
			openDialogAlert($msg,400,140,$parent[0],$parent[1]);
			exit;
		}else{
			if(!empty($_GET['popup']) ) {
				pageClose($msg);
			}else{
				pageBack($msg,400,140);
			}
		}
	}
}

/*
	예약상품 관련 앞 문구 추가
	dateChk = true
		쿼리문에서 예약날짜 관련 쿼리가 없는 상품 리스트의 경우
		받아온 값에서 1:1로 날짜 비교를 해서 추가 한다
	style = true
		카트나 주문쪽에 담길땐 태그를 넣지 않고 문구만 추가한다
*/
function get_goods_pre_name($arr, $dateChk = false, $style = false){
	$goods_name		= $arr['goods_name'];
	$pre_txt		= $arr['display_terms_text'];
	$valid_date		= true;

	if	($dateChk){
		$now_date	= date('Y-m-d');
		$valid_date = false;
		if	($arr['display_terms_begin'] <= $now_date && $arr['display_terms_end'] >= $now_date)
			$valid_date = true;
	}

	if	($style)
		$pre_txt	= "<font color='".$arr['display_terms_color']."'>".$arr['display_terms_text']."</font>";

	if	($arr['display_terms'] == 'AUTO' && $arr['display_terms_text'] && $valid_date)
		$goods_name	= $pre_txt.' '.$arr['goods_name'];

	return $goods_name;
}

function get_lang($lowercase=false) {
	$CI =& get_instance();
	$CI->config_system	= ($CI->config_system)?$CI->config_system:config_load('system');
	$language = trim($CI->config_system['language']);

	# 소문자
	$lowercase && $language = strtolower($language);

	return $language;
}

/**
* - 상품디스플레이영역의 노출심벌 통화단위
* - currency or currency_symbol
* - @2017-02-10
**/
function get_currency_symbol_list($currency_symbol='currency_symbol'){
	$CI =& get_instance();
	$currency_symbol_list = array();
	foreach($CI->config_currency as $k=>$v){
		$currency_symbol_list[$k] = code_load($currency_symbol,$k);
	}
	return $currency_symbol_list;
}

//수동이메일 이달 발송건수 체크 @2017-04-14
function master_mail_count(){
	$CI =& get_instance();
	$mail_count = 3000;
	$toMonth = date("Y-m");
	$sql = "select sum(total) as count from fm_log_email where gb='MANUAL' and regdate like '{$toMonth}%'";
	$query = $CI->db->query($sql);
	$emailData = $query->result_array();
	return ($mail_count-$emailData[0]['count']);
}

# 돌아간 이미지 보정 @2017-04-25
function ImgLotate($Img) {
	$CI =& get_instance();
	ini_set("memory_limit",-1);
	set_time_limit(0);
	$exifData = exif_read_data($Img);
	$ImgInfo = getimagesize($Img);
	if(isset($exifData['Orientation'])) {
		// 시계방향으로 90도 돌려줘야 정상인데 270도 돌려야 정상적으로 출력됨
		if($exifData['Orientation'] == 6)  $degree = 270;
		else if($exifData['Orientation'] == 8) $degree = 90;
		else if($exifData['Orientation'] == 3) $degree = 180;
		if($degree) {
			if($exifData['FileType'] == 1) {
				$source = imagecreatefromgif($Img);
				$source = imagerotate ($source , $degree, 0);
				imagegif($source, $Img);
			}
			else if($exifData['FileType'] == 2) {
				$source = imagecreatefromjpeg($Img);
				$source = imagerotate ($source , $degree, 0);
				imagejpeg($source, $Img);
			}
			else if($exifData['FileType'] == 3) {
				$source = imagecreatefrompng($Img);
				$source = imagerotate ($source , $degree, 0);
				imagepng($source, $Img);
			}

			imagedestroy($source);
		}
	}
	$max_upload_width = 2000;//제한할 넓이
	if($ImgInfo[0]>$max_upload_width ) {
		$ImgInfo[0] = $max_upload_width;
	}
	$config['image_library'] = 'gd2';
	$config['source_image'] = $Img;
	$config['maintain_ratio'] = TRUE;
	$config['width'] = $ImgInfo[0];
	$config['height'] = $ImgInfo[1];
	$CI->load->library('Image_lib', $config);
	$CI->image_lib->resize();
}

# 이미지 리사이징 함수 추가 :: 2019-09-03 pjw
function ImgResize($source, $target, $width, $height) {
	$CI =& get_instance();
	ini_set("memory_limit",-1);
	set_time_limit(0);

	// 리사이징 정보 설정
	$CI->load->library('Image_lib');
	$config['image_library']	= 'gd2';
	$config['source_image']		= $source;
	$config['new_image']		= $target;
	$config['quality']			= '100%';
	$config['maintain_ratio']	= TRUE;
	$config['width']			= $width;
	$config['height']			= $height;

	// 이미지 리사이징 처리
	$CI->image_lib->initialize($config);
	if ( ! $CI->image_lib->resize())		$result = array('status' => '0','error' => $CI->image_lib->display_errors());
	else									$result = array('status' => 1);
	$CI->image_lib->clear();

	return $result;
}

# exif정보 출력
function ImgExif($Img) {
	$CI =& get_instance();

	$exifData = exif_read_data($Img);
	$ImgInfo = getimagesize($Img);
	if($exifData['Orientation'] == 6)  $degree = 90;
	else if($exifData['Orientation'] == 8) $degree = -90;
	else if($exifData['Orientation'] == 3) $degree = -180;
	return array('degree'=>$degree,'exif'=>$exifData, 'info'=>$ImgInfo);
}

// page html 및 page값 등 return
function get_pagination_info($totalCount, $page, $perPage, $perBlock, $isLink = 'a'){
	$totalPage = $totalBlock = $block = $slimit = $pageno = $pagerno = $next = $prev = $first = $last = 0;
	if	($totalCount > 0){
		$totalPage		= ceil($totalCount / $perPage);
		$totalBlock		= ceil($totalPage / $perBlock);
		$block			= ceil($page / $perBlock);
		$slimit			= ($page - 1) * $perPage;
		$pageno			= $slimit + 1;
		$pagerno		= $totalCount - $slimit;
		$querystring	= get_args_list();
		$replacePattern	= array('[:PAGE:]', '[:CLASS:]', '[:MESSAGE:]');
		if		($isLink !== 'a'){
			$pageHTML	= "<a href=\"javascript:{$isLink}('[:PAGE:]', '{$querystring}');\" class='[:CLASS:]'><span>[:MESSAGE:]</span></a>";
		}else{
			$pageHTML	= "<a href=\"?page=[:PAGE:]&{$querystring}\" class='[:CLASS:]'><span>[:MESSAGE:]</span></a>";
		}

		$first			= 1;
		if	($block < $totalBlock)	$next	= ($block * $perBlock) + 1;
		if	($block > 1)			$prev	= (($block - 2) * $perBlock) + 1;
		$last			= $totalPage;

		$html			= '';
		$loop_start		= (($block - 1) * $perBlock) + 1;
		$loop_end		= ($loop_start + $perBlock) - 1;
		if	($loop_end > $totalPage)	$loop_end	= $totalPage;
		if	($prev > 0){
			$replaceVal	= array('1', 'first', '◀ 처음');
			$html		.= str_replace($replacePattern, $replaceVal, $pageHTML);
			$replaceVal	= array($prev, 'prev', '◀ 이전');
			$html		.= str_replace($replacePattern, $replaceVal, $pageHTML);
		}
		for	($p = $loop_start; $p <= $loop_end; $p++){
			$pages[]	= $p;
			$replaceVal	= array($p, '', $p);
			if	($p == $page)	$replaceVal[1]	= 'on';
			$html		.= str_replace($replacePattern, $replaceVal, $pageHTML);
		}
		if	($next > 0){
			$replaceVal	= array($next, 'next', '다음 ▶');
			$html		.= str_replace($replacePattern, $replaceVal, $pageHTML);
			$replaceVal	= array($totalPage, 'last', '마지막 ▶');
			$html		.= str_replace($replacePattern, $replaceVal, $pageHTML);
		}
	}

	$return	= array(
		'page'			=> $page,
		'totalCount'	=> $totalCount,
		'totalPage'		=> $totalPage,
		'totalBlock'	=> $totalBlock,
		'block'			=> $block,
		'pageno'		=> $pageno,
		'pagerno'		=> $pagerno,
		'querystring'	=> $querystring,
		'slimit'		=> $slimit,
		'first'			=> $first,
		'next'			=> $next,
		'prev'			=> $prev,
		'last'			=> $last,
		'html'			=> $html,
		'pages'			=> $pages,
	);

	return $return;
}

function pfloor($val){
	return floor( (string) $val);
}

function pceil($val){
	$val	= (int) ($val*10);
	return ceil( $val / 10 );
}

// AES 암호화
function AESEncode($AES_KEY, $value){
	$padSize = 16 - (strlen ($value) % 16) ;
	$value = $value . str_repeat (chr ($padSize), $padSize) ;
	$output = mcrypt_encrypt (MCRYPT_RIJNDAEL_128, $AES_KEY, $value, MCRYPT_MODE_ECB, str_repeat(chr(0),16)) ;
	return base64_encode ($output) ;
}

// AES 복호화
function AESDecode($AES_KEY, $value){
	$value = base64_decode ($value) ;
	$output = mcrypt_decrypt (MCRYPT_RIJNDAEL_128, $AES_KEY, $value, MCRYPT_MODE_ECB, str_repeat(chr(0),16)) ;

	$valueLen = strlen ($output) ;
	if ( $valueLen % 16 > 0 )
		$output = "";

	$padSize = ord ($output{$valueLen - 1}) ;
	if ( ($padSize < 1) or ($padSize > 16) )
		$output = "";                // Check padding.

	for ($i = 0; $i < $padSize; $i++)
	{
		if ( ord ($output{$valueLen - $i - 1}) != $padSize )
			$output = "";
	}
	$output = substr ($output, 0, $valueLen - $padSize) ;

	return $output;
}

function push_for_admin($params){
	$CI		=& get_instance();
	$CI->load->library('push');
	$ret	= array();

	$valid	= $CI->push->valid_check();

	if	( $valid ) {
		if	( !empty($params['member_seq']) ) {
			$CI->load->model('membermodel');
			$params['userid'] = $CI->membermodel->get_member_userid($params['member_seq']);
		}

		if	( !$params['userid'] && $params['user_name'] )
			$params['userid'] = $params['user_name'];

		if	( !empty($params['provider_list']) ) {
			$CI->load->model('providermodel');
			$params['provider_list'] = implode(',', $params['provider_list']);

			## 해당 입점사 그룹 모두에게 보낸다
			if	( in_array($params['kind'], array('order_deposit', 'goods_qna', 'gs_seller_notice')) ) {
				## 입점사 공지는 입점사 모두에게 보낸다
				if ( $params['kind'] == 'gs_seller_notice' )
					$params['provider_list'] = '';

				$list		= $CI->providermodel->get_provider_group($params['provider_list']);

				foreach($list as $k => $v){
					$temp[] = $v['provider_seq'];
				}

				$params['provider_list'] = implode(',', $temp);
			}
		}

		$CI->push->set('_kind',			$params['kind']);
		$CI->push->set('_title',		$params['title']);
		$CI->push->set('_msg',			$params['msg']);
		$CI->push->set('_unique',		$params['unique']);
		$CI->push->set('_provider',		$params['provider_list']);
		$CI->push->set('_admin_done',	$params['admin_done']);
		$CI->push->set('_params',array(
			'ord_item'		=>			$params['ord_item'],
			'ordno'			=>			$params['ordno'],
			'userid'		=>			$params['userid'],
			'goods_name'	=>			$params['goods_name'],
			'provider_name'	=>			$params['provider_name']
		));
		$ret = $CI->push->pushInsert($params);
	}

	return $ret;
}

/**
 * 사용자앱을 통한 접속 환경 확인
 * 헤더의 정보 중 "User-Agent" 에 "user.firstmall.kr" 문자열 존재 여부 확인
* @headers : 헤더 정보
**/
function checkUserApp($headers = array()){
	$checkString = "user.firstmall.kr";
	// 테스트 문자열
	// $checkString = "AppleWebKit/537.36";
	$check = false;
	if(is_array($headers)){
		$headers = array_change_key_case($headers);
		$userAgent = $headers["user-agent"];
	}else{
		$userAgent = $headers;
	}
	if(strpos($userAgent,$checkString) > -1){
		$check = true;
	}
	return $check;
}

/**
 * 사용자앱을 통한 접속 환경 확인
* @headers : 헤더 정보
**/
function checkUserAppAndroid($headers = array()){
	if(checkUserApp($headers)){
		$check = !checkUserAppIos($headers);
	}
	return $check;
}
/**
 * 사용자앱을 통한 접속 환경 확인
* @headers : 헤더 정보
**/
function checkUserAppIos($headers = array()){
	$arrCheckString = array("iPhone","iPad");
	// 테스트 문자열
	// $arrCheckString = array("AppleWebKit/537.36");
	if(checkUserApp($headers)){
		$check = false;
		if(is_array($headers)){
			$headers = array_change_key_case($headers);
			$userAgent = $headers["user-agent"];
		}else{
			$userAgent = $headers;
		}
		foreach($arrCheckString as $checkString){
			if(strpos($userAgent,$checkString) > -1){
				$check = true;
			}
		}
	}
	return $check;
}

/**
 * 현재 접속중인 프로토콜을 반환
 * @return str (http://, https://)
**/
function get_connet_protocol(){
	// $CI		=get_http_protocol& get_instance();
	$protocol = "";

	// cron 호출 인 경우 DB config 값으로 여부 판단 :: 2020-01-21 pjw
	if(_IS_SHELL_MODE_ != 'Y'){
		$protocol = (check_ssl_protocol())? 'https://' : 'http://';
	}else{
		$protocol = (check_ssl_protocol_cron())? 'https://' : 'http://';
	}

	return $protocol;
}
/**
 * 현재 프로토콜이 ssl 여부 확인을 반환
 * @return boolen true : ssl, false : http
**/
function check_ssl_protocol(){
	// $CI		=& get_instance();
	$ssl = "";
	$ssl = ((!empty($_SERVER['HTTPS'])) && ($_SERVER['HTTPS']=='on')) ? true : false;
	return $ssl;
}

/**
 * Cron 전용 프로토콜 확인 함수 :: DB config 값으로 SSL 사용여부 검사
 * @return boolen true : ssl, false : http
**/
function check_ssl_protocol_cron(){
	$CI		=& get_instance();
	$ssl	= false;

	// ssl 모듈 로드 후 필요정보 조회
	if(empty($CI->cron_ssl_info)){
		$CI->load->library('ssllib');

		unset($params);
		$params[$CI->ssllib->sslConfigColumn['certStatus']] = $CI->ssllib->valueSslConfigCertStatus['install'];
		$result = $CI->ssllib->getSslEnvironment($params, 1);
		$CI->cron_ssl_info = $result['data'][0];
	}

	// ssl 사용 여부 설정 후 리턴
	if($CI->cron_ssl_info['cert_status'] == 10){
		$ssl = true;
	}

	return $ssl;
}

/**
 * 현재 요청이 ajax 여부 확인을 반환
 * @return boolen true : ajax, false : http
**/
function check_ajax_protocol(){
	$ajax = false;
	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
	&& strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
	{
	  $ajax = true;
	}
	return $ajax;
}
/**
 * URL 포함 검사
 * 도메인을 제외한 쿼리스트링으로만 검사가능
 * 와일드 카드 검사 가능 ( ex. /cosmos/* :: cosmos 컨트롤러에서 호출되는 모든 페이지 포함)
 * @return boolen true : 포함, false : 미포함
**/
function check_contain_url($now_url, $check_urls){
	$result      = false;
	foreach($check_urls as $check){
		$tmp_urls = explode('/', $check);
		$regex = "/^";
		foreach($tmp_urls as $tmp_url){
			$cell = trim($tmp_url);
			if($cell == '*')        $regex .= "\/.*";
			else if($cell != '')    $regex .= "\/".$cell;
			else                    continue;
		}
		$regex .= "$/";
		if(preg_match($regex, $now_url)){
			$result = true;
		}
	}
	return $result;

}

/**
 * 현재 프로토콜에 맞춰 문자열의 프로토콜을 일괄 변경
 * @return str (http://, https://)
**/
function replace_connect_protocol($str){
	$result			  = $str;
	$protocol		  = check_ssl_protocol() ? 'http://' : 'https://';
	$replace_protocol = 'https://';
	$check_domain_arr = array(
		'interface.firstmall.kr',
		'design.firstmall.kr'
	);

	foreach($check_domain_arr as $domain){
		$result = str_replace($protocol.$domain, $replace_protocol.$domain, $result);
	}

	return $result;
}

function array_sort($array, $on, $order=SORT_ASC)
{
    $new_array = array();
    $sortable_array = array();

    if (count($array) > 0) {
        foreach ($array as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if ($k2 == $on) {
                        $sortable_array[$k] = $v2;
                    }
                }
            } else {
                $sortable_array[$k] = $v;
            }
        }

        switch ($order) {
            case SORT_ASC:
                asort($sortable_array);
            break;
            case SORT_DESC:
                arsort($sortable_array);
            break;
        }

        foreach ($sortable_array as $k => $v) {
            array_push($new_array, $array[$k]);
        }
    }

    return $new_array;
}

/**
 * 외부 통신 시 로그 기록
 * @param array msg : 로그 내용
 * @param string filename : 파일명
 * @param string gubun : data/log/ 이후 폴더명
 */
function writeCsLog($msg, $filename , $gubun, $sep='day')
{
	if(empty($filename)) $filename = "input";
	if(empty($gubun)) $gubun = "tmp";
	// data/logs 폴더 만들기
	$logDir = ROOTPATH.'data/logs';
	if(!is_dir($logDir)){
		mkdir($logDir);@chmod($logDir,0777);
	}

	// data/logs 하위폴더 만들기
	$path	= "data/logs/".$gubun."/";
	$logDir = ROOTPATH.$path;
	if(!is_dir($logDir)){
		mkdir($logDir);@chmod($logDir,0777);
	}

	// 날짜 폴더 만들기
	$logDir .= "/".date('Ymd');
	if(!is_dir($logDir)){
		mkdir($logDir);@chmod($logDir,0777);
	}

	$file	= $filename."_".date("Ymd").".log";
	if($sep=='hour') {
		$file	= $filename."_".date("H").".log";
	}
	$microtime = substr(microtime(),0,10) * 1000000000;

	if(!($fp = fopen($logDir."/".$file, "a+"))) return 0;
	ob_start();
	echo"[".date("Y-m-d H:i:s",mktime()).$microtime."]\n";
	echo"[REMOTE_ADDR : ".$_SERVER['REMOTE_ADDR']."]\n";
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

/**
 * return_url 에 프로토콜(//)이 있을 경우 외부 도메인으로 보낼 수 없도록 처리 by hed #24462
 */
function block_out_link_return_url(){
	if(preg_match('/(\/\/)/', $_POST['return_url'])) $_POST['return_url'] = '/';
	if(preg_match('/(\/\/)/', $_GET['return_url'])) $_GET['return_url'] = '/';
}

/*
	* 지정된 두 날짜 사이에 모든 날짜를 배열로 반환
	* getDatesFromRange('2019-02-07','2019-02-08');
	(
		[0] => 2019-02-07
		[1] => 2019-02-08
	)
*/
function getDatesFromRange($start, $end, $format = 'Y-m-d') {
	$array		= array();
	$interval	= new DateInterval('P1D');

	$realEnd	= new DateTime($end);
	$realEnd->add($interval);

	$period		= new DatePeriod(new DateTime($start), $interval, $realEnd);

	foreach($period as $date) {
		$array[] = $date->format($format);
	}

	return $array;
}

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}

// 랜덤 문자열 생성 :: 2020-02-28 pjw
function get_random_string($length = 10) {
	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$charactersLength = strlen($characters);
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[rand(0, $charactersLength - 1)];
	}
	return $randomString;
}

// truncate 지연 개선을 위해 start
function truncate_to_drop($sTable, $conn){
	$aQuery[] = "CREATE TABLE `".$sTable."_new` LIKE `".$sTable."`";
	$aQuery[] = "RENAME TABLE `".$sTable."` TO `".$sTable."_old`, `".$sTable."_new` TO `".$sTable."`";
	$aQuery[] = "DROP TABLE `".$sTable."_old`";
	foreach($aQuery as $sQuery){
		mysqli_query($conn, $sQuery);
	}
}
// truncate 지연 개선을 위해 end

if (!function_exists('camel_case')) {
	/**
	* 값을 snake case 에서 camel case로 변환한다.
	*
	* @param string $str
	* @return string
	*/
	function camel_case($str)
	{
		return lcfirst(implode('', array_map('ucfirst', explode('_', $str))));
	}
}

if (!function_exists('camel_keys')) {
	/**
	* 배열의 키 값을 snake case 에서 camel case로 변환한다.
	*
	* @param array $array
	* @return string
	*/
	function camel_keys($array)
	{
		$result = [];
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$value = camel_keys($value);
			}
			$result[camel_case($key)] = $value;
		}
		return $result;
	}
}

if (!function_exists('snake_case')) {
	/**
	* 값을 camel case 에서 snake case로 변환한다.
	*
	* @param string $str
	* @return string
	*/
	function snake_case($str)
	{
		return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $str));
	}
}

if (!function_exists('snake_keys')) {
	/**
	* 배열의 키 값을 camel case 에서 snake case로 변환한다.
	*
	* @param  array  $array
	* @param  string $delimiter
	* @return string
	*/
	function snake_keys($array, $delimiter = '_')
	{
		$result = [];
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$value = snake_keys($value, $delimiter);
			}
			$result[snake_case($key, $delimiter)] = $value;
		}
		return $result;
	}
}
/** 인코딩을 감지해서 UTF-8로 변환합니다. */
function convert_to_utf8(/* string */ $str) {
	if (preg_match('//u', $str)) return $str;
	return iconv('CP949', 'UTF-8//TRANSLIT', $str);
}

/**
 * array 또는 object의 내용을 재귀적으로 UTF-8로 변환합니다.
 *
 * @param int &$target 변환할 array 또는 object
 */
function convert_to_utf8_recursive(&$target, /* int */ $max_depth = 10) {
	if (!is_array($target) && !is_object($target)) throw new \Exception('target must be an array or instance of object');
	foreach ($target as &$value) {
		switch (true) {
			case is_string($value):
				$value = convert_to_utf8($value);
			break;
			case is_array($value):
			case is_object($value):
				if($max_depth<1) throw new \Exception('Recursive depth reached max_depth');
				convert_to_utf8_recursive($value, $max_depth-1);
			break;
		}
	}
}

/**
 * 바이트 용량값을 단위 변환하여 반환
 * @param data_capacity : byte
 */
function get_capacity_with_unit($data_capacity) {
	$unit_minimum = 1024;
	$unit_list = array('KB', 'MB', 'GB', 'TB');
	$current_capacity = $data_capacity > 0 ? $data_capacity : 0;
	$current_unit = 'B';

	if ($data_capacity > $unit_minimum) {
		foreach ($unit_list as $key => $val) {
			$tmp_capacity = round($current_capacity / $unit_minimum, $key);
			if (floor($tmp_capacity) > 0) {
				$current_capacity = $tmp_capacity;
				$current_unit = $val;
			} else {
				break;
			}
		}
	}

	return array(
		'capacity' => $current_capacity,
		'unit' => $current_unit
	);
}

/**
 * 파일 다운로드 전에 체크 함수
 * realpath, 확장자, mimetype 추가
 */
function download_allowed_check($url) {
	$real_filename = end(explode("/", $url));
	// 파일 확장자명 체크
	$file_ext		= @end(explode('.', $real_filename));
	if(!in_array($file_ext, array('xlsx','xls','csv','zip'))) {
		echo "[Error] Files not Allowed.";
		return false;
	}

	/**
	 * 파일 경로 체크 현재까지 확인된 경로는 excel_download 와 data/tmp 로 확인됨.
	 * data/tmp 는 admin(selleradmin)/shipping/shipping_zone_download 에서 사용함.
	 * 추가 발견된다면 다운로드 되는 파일 위치를 수정하는 방법으로 진행할 것.
	 */
	$realpath	= realpath($url);
	if(!preg_match('/\/excel_download\/|\/data\/tmp\//',$realpath)) {
		echo "[Error] Paths not Allowed.";
		return false;
	}

	// 파일 mimtype 체크
	$file_mime		= mime_content_type($realpath);
	// csv, zip, xls, xlsx
	$allowed_mime = array(
		'text/csv',
		'application/zip',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
	);
	if(!in_array($file_mime, $allowed_mime)) {
		echo "[Error] Mimetype not Allowed.";
		return false;
	}

	return true;
}

/**
 * 회원 이름명 OR 업체명 20자 초과 시 20자까지 제한
 * @param String username
 */
function check_member_name($username)
{
	$CI =& get_instance();
	$charset = $CI->config->item('charset'); // UTF-8
	$max_length = 20;

	if (!isset($username)) {
		$username = '';
	}

	if (mb_strlen($username, $charset) > $max_length) {
		$username = mb_substr($username, 0, $max_length, $charset);
	}

	return $username;
}

function getAllowDownloadFileExtList() {
	return ['jpg','jpeg','png','gif','pic','tif','tiff','jfif','bmp','txt','hwp','docx','docm','doc','ppt','pptx','pptm','pps','ppsx','xls','xlsx','xlsm','xlam','xla','ai','psd','eps','pdf','ods','ogg','mp4','avi','wmv','zip','rar','tar','7z','tbz','tgz','lzh','gz','dwg','csv'];
}

/**
 * 앱 관련 접속 환경 디바이스 가져오기
 */
function getDeviceEnvirnment() {
	$mobileapp = 'N';
	$device = 'others';

	if (strpos($_SERVER['HTTP_USER_AGENT'], 'Firstmall_App' ) !== false) {
		$mobileapp = 'Y';
		$usr_agent = $_SERVER['HTTP_USER_AGENT'];
		$usr_agent = strtolower($usr_agent);

		if (strpos($usr_agent, 'android' ) !== false) {
			$device = "android";
		} else {
			$device = "iphone";
		}
	}

	return [
		'mobileapp' => $mobileapp,
		'device' => $device,
	];
}

/** 
 * 카카오싱크 연동 여부
 */
function isKakaoSyncUse()
{
	$snssocial = config_load('snssocial');

	if ($snssocial['mode_ks'] == 'SYNC' && $snssocial['status_ks'] == 1) {
		return true;
	} else {
		return false;
	}
}

/**
 * (입점)관리자 , _gabia, _batch, shell, cli 로 실행될 때는 true
 * front 행위는 false
 */
function isAdminSystemMode() {
	
	$result = defined('__ADMIN__') === true || defined('__SELLERADMIN__') === true
		|| in_array('_gabia', $CI->uri->rsegments) || in_array('_batch', $CI->uri->rsegments) || $_SERVER['SHELL'] || php_sapi_name() == 'cli';

	return $result;
}


/**
 * 상품 상세, 장바구니 옵션 수량 변경 등 입력옵션 이모지 체크하여 삭제
 */
function emojiFilter($string) {
	$symbols = "\x{1F100}-\x{1F1FF}" // Enclosed Alphanumeric Supplement
		."\x{1F300}-\x{1F5FF}" // Miscellaneous Symbols and Pictographs
		."\x{1F600}-\x{1F64F}" //Emoticons
		."\x{1F680}-\x{1F6FF}" // Transport And Map Symbols
		."\x{1F900}-\x{1F9FF}" // Supplemental Symbols and Pictographs
		."\x{2600}-\x{26FF}" // Miscellaneous Symbols
		."\x{2700}-\x{27BF}";
	return preg_replace('/['. $symbols . ']+/u', '', $string);
}