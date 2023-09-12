<?php
function snslinkurl($snstype, $subject, $snsviewr='all', $resultType='html')
{
	$CI =& get_instance();
	$CI->load->library('snssocial');
	$CI->load->helper('design');
	$CI->config_basic = config_load('basic');

	$protocol = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == "on") ? "https" : "http");

	if($CI->mobileapp == 'Y' && $CI->m_device == "android"){
		if($resultType == 'script'){
			return false;
		}
		$imageTag = '<div style="width:300px;padding:80px 0px;text-align:center;font-size:16px;">하단 공유 메뉴를 이용해주세요.</div>';
	}else{
		if( defined('__ADMIN__') || defined('__SELLERADMIN__') ) {//관리자접근dir
			$CI->board_icon_src = '/data/skin/'.$CI->workingSkin.'/images/board/icon/';//
			$CI->board_icon_dir = ROOTPATH.'/data/skin/'.$CI->workingSkin.'/images/board/icon/';//
		}else{
			$CI->board_icon_src = '/data/skin/'.$CI->realSkin.'/images/board/icon/';//
			$CI->board_icon_dir = ROOTPATH.'/data/skin/'.$CI->realSkin.'/images/board/icon/';//
		}
	
		$CI->admin_board_icon_dir = ROOTPATH.'/admin/skin/'.$CI->config_system['adminSkin'].'/images/board/icon/';//
	
		$sns_icon_dir['tw']	= $CI->board_icon_dir.'sns_t0.gif';//트위터
		$sns_icon_dir['fa']	= $CI->board_icon_dir.'sns_f0.gif';//페이스북
		$sns_icon_dir['me']	= $CI->board_icon_dir.'sns_m0.gif';//미투데이
		$sns_icon_dir['yo']	= $CI->board_icon_dir.'sns_y0.gif';//요즘
		//$sns_icon_dir['go']	= $CI->board_icon_dir.'sns_g0.gif';//Ask on Google+
		$sns_icon_dir['pi']	= $CI->board_icon_dir.'sns_p0.gif';//핀터레스트 pinterest
		$sns_icon_dir['na']	= $CI->board_icon_dir.'sns_na0.gif';//네이트
	
		$sns_icon_dir['ka']	= $CI->board_icon_dir.'sns_k0.gif';//카카오톡
		$sns_icon_dir['kakaostory']	= $CI->board_icon_dir.'sns_ks0.png';//카카오톡
		$sns_icon_dir['line']	= $CI->board_icon_dir.'sns_ln0.png';//LINE
	
		if( !is_file($sns_icon_dir['go'])) @copy($CI->admin_board_icon_dir.'sns_g0.gif',$sns_icon_dir['go']);
		if( !is_file($sns_icon_dir['ka'])) @copy($CI->admin_board_icon_dir.'sns_k0.gif',$sns_icon_dir['ka']);
		if( !is_file($sns_icon_dir['pi'])) @copy($CI->admin_board_icon_dir.'sns_p0.gif',$sns_icon_dir['pi']);
		if( !is_file($sns_icon_dir['na'])) @copy($CI->admin_board_icon_dir.'sns_na0.gif',$sns_icon_dir['na']);
		if( !is_file($sns_icon_dir['kakaostory'])) @copy($CI->admin_board_icon_dir.'sns_ks0.png',$sns_icon_dir['kakaostory']);
		if( !is_file($sns_icon_dir['line'])) @copy($CI->admin_board_icon_dir.'sns_ln0.png',$sns_icon_dir['line']);
	
		$sns_icon_src['tw']	= $CI->board_icon_src.'sns_t0.gif';//트위터
		$sns_icon_src['fa']	= $CI->board_icon_src.'sns_f0.gif';//페이스북
		$sns_icon_src['me']	= $CI->board_icon_src.'sns_m0.gif';//미투데이
		$sns_icon_src['yo']	= $CI->board_icon_src.'sns_y0.gif';//요즘
	
		//$sns_icon_src['go']	= $CI->board_icon_src.'sns_g0.gif';//Ask on Google+
		$sns_icon_src['pi']	= $CI->board_icon_src.'sns_p0.gif';//핀터레스트 pinterest
		//이미지만을 공유하는 특화된 소셜 네트워크 서비스 입니다.
		$sns_icon_src['na']	= $CI->board_icon_src.'sns_na0.gif';//네이트
	
		$sns_icon_src['ka']	= $CI->board_icon_src.'sns_k0.gif';//카카오톡
		$sns_icon_src['kakaostory']	= $CI->board_icon_src.'sns_ks0.png';//카카오톡
		//카카오톡 mobile 인 경우에만 url복사 카카오톡은 URL이 아니라 어플리케이션이 가지고 있는 URI를 통해서 어플리케이션 자체를 호출하는 방식
		$sns_icon_src['line']	= $CI->board_icon_src.'sns_ln0.png';//LINE

		if($snstype == 'broadcast') {
			$CI->board_icon_src = "/admin/skin/default/images/broadcast/";
			$sns_icon_src['tw']	= $CI->board_icon_src.'i_twitter.png';//트위터
			$sns_icon_src['fa']	= $CI->board_icon_src.'i_facebook.png';//페이스북

			$sns_icon_src['ka']	= $CI->board_icon_src.'i_kakao.png';//카카오톡
			$sns_icon_src['kakaostory']	= $CI->board_icon_src.'i_kastory.png';//카카오톡
			$sns_icon_src['line']	= $CI->board_icon_src.'i_line.png';//LINE
		}
		//snsset['pi']		= '';
	
		/* 따옴표,쌍따옴표 있을경우 에러 발생으로 추가. leewh 2014-10-29 */
		$tmp_sns_tit = str_replace(array('&quot;', '&apos;'), array('"', "'"), $CI->meta_data['title']);
		$tmp_sns_tit = str_replace("'", "\'", $tmp_sns_tit);
		$tmp_sns_tit = str_replace('"', "\'", $tmp_sns_tit);
	
		$tmp_sns_tag = str_replace(array('&quot;', '&apos;'), array('"', "'"), $CI->config_basic['shopTitleTag']);
		$tmp_sns_tag = str_replace("'", "\'", $tmp_sns_tag);
		$tmp_sns_tag = str_replace('"', "\'", $tmp_sns_tag);
	
		$sns_tit	= urlencode($tmp_sns_tit);
		$sns_tag	 = urlencode($tmp_sns_tag);
	
		if($CI->config_system['domain']){
			$sns_url	= urlencode($protocol."://".$CI->config_system['domain'].$_SERVER['REQUEST_URI']);
			$sns_host	= $protocol."://".$CI->config_system['domain'];
		}else{
			$sns_url	= urlencode($protocol."://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
			$sns_host	= $protocol."://".$_SERVER['HTTP_HOST'];
		}
		$sns_url_ka_http	= $protocol."://".$_SERVER['HTTP_HOST'];
	
		if($snstype == 'board' || $snstype == 'cmt'){
			if($CI->config_system['domain']){
				$linkurl		 = ($_GET['id'])?$protocol."://".$CI->config_system['domain'].'/board/view?id='.$_GET['id'].'&seq='.$_GET['seq']:$protocol."://".$_SERVER['HTTP_HOST'].'/board/view?id='.BOARDID.'&seq='.$_GET['seq'];
	
				$sns_url_ka		 = ($_GET['id'])?$sns_url_ka_http.'/board/'.urlencode('view?id='.$_GET['id'].'&seq='.$_GET['seq']):$sns_url_ka_http.'/board/'.urlencode('view?id='.BOARDID.'&seq='.$_GET['seq']);
	
			}else{
				$linkurl		 = ($_GET['id'])?$protocol."://".$_SERVER['HTTP_HOST'].'/board/view?id='.$_GET['id'].'&seq='.$_GET['seq']:$protocol."://".$_SERVER['HTTP_HOST'].'/board/view?id='.BOARDID.'&seq='.$_GET['seq'];
			}
			$sns_url	= urlencode($linkurl);
			$sns_sbj	= urlencode(strip_tags($subject));
		}elseif($snstype == 'goods'){
			$sns_sbj	= urlencode(strip_tags($subject));
	
			## 상품디스플레이에서 리턴되는 상품 url 2014-11-04
			if($CI->uri->uri_string == "common/snslinkurl_tag"){
				$sns_url = urlencode($sns_host."/goods/view?no=".$_GET['no']);
			}
	
			$CI->load->model('goodsmodel');
			$images = $CI->goodsmodel->get_goods_image($_GET['no']);
			if($images){
				foreach($images as $image){
					if($image['list1']['image']) {
						if( substr($image['list1']['image'],0,4) == "http" ) {
							$imgurl			= $image['list1']['image'];
						}else{
							$filetypetmp = @getimagesize(ROOTPATH.$image['list1']['image']);
							if($filetypetmp[0] >= 81) {
								$imgurl			= $image['list1']['image'];
								$imgwidth	= ($filetypetmp[0]>81)?$filetypetmp[0]:'81';
								$imgheight	=($filetypetmp[1]>81)?$filetypetmp[1]:'81';
								$imgurl			= $sns_url_ka_http.$imgurl;
								break;
							}
						}
					}
	
					if($image['list2']['image'] && !$imgurl) {
						if( substr($image['list2']['image'],0,4) == "http" ) {
							$imgurl			= $image['list2']['image'];
						}else{
							$filetypetmp = @getimagesize(ROOTPATH.$image['list2']['image']);
							if($filetypetmp[0] >= 81) {
								$imgurl			= $image['list2']['image'];
								$imgwidth	= ($filetypetmp[0]>81)?$filetypetmp[0]:'81';
								$imgheight	=($filetypetmp[1]>81)?$filetypetmp[1]:'81';
								$imgurl			= $sns_url_ka_http.$imgurl;
								break;
							}
						}
					}
	
					if($image['view']['image'] && !$imgurl) {
						if( substr($image['view']['image'],0,4) == "http" ) {
							$imgurl			= $image['view']['image'];
						}else{
							$filetypetmp = @getimagesize(ROOTPATH.$image['view']['image']);
							if($filetypetmp[0] >= 81) {
								$imgurl			= $image['view']['image'];
								$imgwidth	= ($filetypetmp[0]>81)?$filetypetmp[0]:'81';
								$imgheight	=($filetypetmp[1]>81)?$filetypetmp[1]:'81';
								$imgurl			= $sns_url_ka_http.$imgurl;
								break;
							}
						}
					}
				}//endforeach
	
			}//endif
		}elseif($snstype == 'broadcast') {
			$no = $CI->input->get('no');
			$sns_sbj	= urlencode(strip_tags($subject));
			$sns_url = urlencode($sns_host."/broadcast/player?no=".$no);

			$CI->load->model('broadcastmodel');
			$broadcast = $CI->broadcastmodel->getSchEach($no);
			$filetypetmp = @getimagesize(ROOTPATH.$broadcast['image']);
			if($filetypetmp[0] >= 81) {
				$imgurl			= $broadcast['image'];
				$imgwidth	= ($filetypetmp[0]>81)?$filetypetmp[0]:'81';
				$imgheight	=($filetypetmp[1]>81)?$filetypetmp[1]:'81';
				$imgurl			= $sns_url_ka_http.$imgurl;
			}
		}elseif($snstype == 'event'){
			$sns_sbj	= urlencode(strip_tags($subject));
		}
	
		if(empty($imgurl)) {
			if( is_file(ROOTPATH.$CI->config_system['snslogo']) ) {
				$filetypetmp = @getimagesize(ROOTPATH.$CI->config_system['snslogo']);
				if($filetypetmp[0] >= 81) {
					$imgurl = $sns_url_ka_http.$CI->config_system['snslogo'];
					$imgwidth = $filetypetmp[0];
					$imgheight =$filetypetmp[1];
				}
			}
		}
	
		$sns_arr = getAlert('gv097');
		$sns_arr = explode('|',$sns_arr);
	
		$snskor = array('fa'=>$sns_arr[0],'tw'=>$sns_arr[1],'go'=>$sns_arr[2],'cy'=>$sns_arr[3],'ka'=>$sns_arr[4],'kakaostory'=>$sns_arr[5],'line'=>$sns_arr[6]);//,'pi'=>'핀터레스트''yo'=>'요즘','me'=>'미투데이',
	
		$category_config = skin_configuration($CI->skin);
		if( $CI->storemobileMode || ($CI->mobileMode && $category_config['mobile_version'] >= 2) ) {
			$iconsize = ' width="33" height="33" ';
			$ismobilever3 = (($category_config['mobile_version']>= 3 && $snstype == 'goods' && strstr($CI->template_path,'goods/view') && $_GET['no']) || ($category_config['mobile_version']>= 3 && $snstype == 'cmt'))?true:false;
		}
		if($snstype == 'broadcast') {
			$iconsize= '';
		}
		
		if($resultType == 'script'){
			
			if( ($snstype ==  'kakaostory' || $snstype ==  'line' ) && !$CI->_is_mobile_agent && (!defined('__ADMIN__') && !defined('__SELLERADMIN__')) )	return false;
			if( $snsviewr != 'all' && $snsviewr != $snstype )	return false;
			if( !is_file($sns_icon_dir[$snstype]) )	return false;
				
			if( $snstype ==  'ka' || $snstype ==  'kakaostory' || $snstype ==  'line' ){
				$sns_tit	= urldecode($sns_tit);
				$sns_sbj	= addslashes(urldecode($sns_sbj));
				$sns_tag	= urldecode($sns_tag);
				$sns_url	= ($linkurlka) ? $linkurlka : urldecode($sns_url);
			}
			
			if( $snstype ==  'fa' ){
				return 'snsWin(\''.$snstype.'\',\''.$sns_tit.'\', \''.$sns_sbj.'\', \''.$sns_tag.'\', \''.$sns_url.'\',\''.$CI->_is_mobile_agent.'\',\'\',\'\',\'\');';
			}else if( $snstype == 'ka' ){
				return 'snsWin(\'ka\',\''.$sns_tit.'\', \''.$sns_sbj.'\', \''.$sns_tag.'\', \''.$sns_url.'\',\''.$CI->_is_mobile_agent.'\',\''.$imgurl.'\',\''.$imgwidth.'\',\''.$imgheight.'\'); kakaotalk_link();';
			}else if( $snstype == 'kakaostory'){
				return 'snsWin(\''.$snstype.'\',\''.$sns_tit.'\', \''.$sns_sbj.'\', \''.$sns_tag.'\', \''.$sns_url.'\',\''.$CI->_is_mobile_agent.'\',\''.$imgurl.'\',\''.$imgwidth.'\',\''.$imgheight.'\');';
			}else{
				return 'snsWin(\''.$snstype.'\',\''.$sns_tit.'\', \''.$sns_sbj.'\', \''.$sns_tag.'\', \''.$sns_url.'\',\''.$CI->_is_mobile_agent.'\',\''.$imgurl.'\',\''.$imgwidth.'\',\''.$imgheight.'\');';
			}
		}
		
		$imageTag = ($ismobilever3)?'<ul class="snsbox clearbox">':'';
		foreach($snskor as $_key=>$_val){
			if($snsviewr == 'all' || ($snsviewr != 'all' && $snsviewr == $_key)) {//전체출력이거나 개별출력시
				if( ($_key ==  'kakaostory' || $_key ==  'line' ) && !$CI->_is_mobile_agent && (!defined('__ADMIN__') && !defined('__SELLERADMIN__')) )  continue;//mobile 접속시에만 추가
	
				if( $_key ==  'ka' || $_key ==  'kakaostory' || $_key ==  'line' ){
					$sns_tit	= urldecode($sns_tit);
					$sns_sbj	= addslashes(urldecode($sns_sbj));
					$sns_tag	= urldecode($sns_tag);
					$sns_url	= ($linkurlka)?$linkurlka:urldecode($sns_url);
				}
	
				if( is_file($sns_icon_dir[$_key]) ) {
					if( $_key ==  'fa' ) {
						$imageTag .= ($ismobilever3)?'<li>':'<span class="snsbox hand ">';
						$imageTag .= '<img src="'.$sns_icon_src[$_key].'" alt="'.$_val.'"  title="'.$_val.'" '.$iconsize.' valign="middle" onclick="snsWin(\''.$_key.'\',\''.$sns_tit.'\', \''.$sns_sbj.'\', \''.$sns_tag.'\', \''.$sns_url.'\',\''.$CI->_is_mobile_agent.'\',\'\',\'\',\'\');" />';
						$imageTag .= ($ismobilever3)?'<br />'.$_val.'</li>':'</span>&nbsp;';
					}elseif( $_key ==  'ka' ) {
	
						if(!empty($CI->arrSns['kakaotalk_app_javascript_key']) ) {
							if( !defined('__ADMIN__') && !defined('__SELLERADMIN__') ) {//관리자스크립트충돌수정
								$imageTag .= "<script type='text/javascript'src='/app/javascript/plugin/kakao/kakao.min.js'></script>";
								$imageTag .= "<script>Kakao.init('".$CI->arrSns['kakaotalk_app_javascript_key']."');</script>";
							}
	
							$price		= 0;
							$discount	= 0;
							$rate		= 0;
							$kakao_type	= 'feed';
							if	( $snstype == 'goods' ) {
								// 비회원일시 가격대체문구 표기 설정일 경우 feed 타입으로 전달함. :: rsh 2019-04-02
								$goods = $CI->goodsmodel->get_goods($CI->input->get('no'));
								if($goods['string_price_use'] !== '1') {
									$goodsOption = $CI->goodsmodel->get_goods_default_option_sales($goods);
									$price		= $goodsOption['price'];
									$discount	= $goodsOption['after_price']['event'];
									//공유시 할인 목록은 basic 과 이벤트만 적용
									$rate = floor(($goodsOption['after_price']['event']/$price) * 100);
									$rate = 100 - $rate;
									$kakao_type	= 'commerce';
								}
							}
	
							$imageTag .= ($ismobilever3)?'<li class="kakao-link-btn">':'<span class="snsbox hand  kakao-link-btn">';
							$imageTag .= '<img src="'.$sns_icon_src[$_key].'" alt="'.$_val.'"  title="'.$_val.'" '.$iconsize.' valign="middle" onclick="kakaotalk_link(\''.$kakao_type.'\', \''.$sns_url.'\',\''.$sns_sbj.'\',\''.$sns_tit.'\',\''.$imgurl.'\','.$price.','.$discount.','.$rate.');"/>';
						$imageTag .= ($ismobilever3)?'<br />'.$_val.'</li>':'</span>&nbsp;';
						}else{
							$imageTag .= "<script type='text/javascript'src='/app/javascript/js/kakao.link.js'></script>";
							$imageTag .= ($ismobilever3)?'<li class="kakao-link-btn">':'<span class="snsbox hand  kakao-link-btn">';
							$imageTag .= '<img src="'.$sns_icon_src[$_key].'" alt="'.$_val.'"  title="'.$_val.'" '.$iconsize.' valign="middle" onclick="kakaotalk_link();" oncli="snsWin(\'ka\',\''.$sns_tit.'\', \''.$sns_sbj.'\', \''.$sns_tag.'\', \''.$sns_url.'\',\''.$CI->_is_mobile_agent.'\',\''.$imgurl.'\',\''.$imgwidth.'\',\''.$imgheight.'\');" />';
							$imageTag .= ($ismobilever3)?'<br />'.$_val.'</li>':'</span>&nbsp;';
						}
					}elseif( $_key ==  'kakaostory') {
						$imageTag .= "<script type='text/javascript'src='/app/javascript/plugin/kakao/kakaostory.link.js'></script>";
						$imageTag .= ($ismobilever3)?'<li>':'<span class="snsbox hand ">';
						$imageTag .= '<img src="'.$sns_icon_src[$_key].'" alt="'.$_val.'"  title="'.$_val.'" '.$iconsize.' valign="middle" onclick="snsWin(\''.$_key.'\',\''.$sns_tit.'\', \''.$sns_sbj.'\', \''.$sns_tag.'\', \''.($sns_url).'\',\''.$CI->_is_mobile_agent.'\',\''.$imgurl.'\',\''.$imgwidth.'\',\''.$imgheight.'\');" />';
						$imageTag .= ($ismobilever3)?'<br />'.$_val.'</li>':'</span>&nbsp;';
					}else{
						$imageTag .= ($ismobilever3)?'<li>':'<span class="snsbox hand ">';
						$imageTag .= '<img src="'.$sns_icon_src[$_key].'" alt="'.$_val.'"  title="'.$_val.'" '.$iconsize.' valign="middle" onclick="snsWin(\''.$_key.'\',\''.$sns_tit.'\', \''.$sns_sbj.'\', \''.$sns_tag.'\', \''.$sns_url.'\',\''.$CI->_is_mobile_agent.'\',\''.$imgurl.'\',\''.$imgwidth.'\',\''.$imgheight.'\');" />';
						$imageTag .= ($ismobilever3)?'<br />'.$_val.'</li>':'</span>&nbsp;';
					}
				}
			}
		}//endforeach;
		$imageTag .= ($ismobilever3)?'</ul>':'';
	}//endif

	echo $imageTag;

}
?>