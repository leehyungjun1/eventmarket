<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/common_base".EXT);

class snsredirect extends  common_base {

	public function __construct() {
		parent::__construct();
		error_reporting(0);//0 E_ALL 
		$this->load->library('validation');
		$this->load->library('snssocial');
		### MEMBER SESSION
		$this->userInfo = ( $this->session->userdata('user') )?$this->session->userdata('user'):$_SESSION['user'];
		if ( isset($this->userInfo['member_seq']) ) {
			define('__ISUSER__',true);//회원로그인
		}

	}

	function facebookloginjs(){
		$scripts[] = "<script type='text/javascript'>";
		$scripts[] = " var plus_app_id = '{$this->__APP_ID__}';";
$scripts[] = "
window.fbAsyncInit = function() {
FB.init({	appId: plus_app_id,	status: true,	cookie: true,	xfbml: true,	oauth: true,version    : 'v{$this->__APP_VER__}'});//@2015-04-28
/**
FB.getLoginStatus(function(response) {
	if (response.status === 'connected') {
		FB.api('/me', function(response) {if(response.id)$.cookie('fbuser', response.id);});
	}
});
**/
};
(function(d, s, id) {
var js, fjs = d.getElementsByTagName(s)[0];
if (d.getElementById(id)) return;
js = d.createElement(s); js.id = id;
js.src = '//connect.facebook.net/ko_KR/sdk.js';
fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));
";
		$scripts[] = "</script>";
		echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
		echo '<script type="text/javascript" src="/app/javascript/plugin/jstree/_lib/jquery.cookie.js"></script>';
		foreach($scripts as $script){
			echo $script."\n";
		}
	}

	public function facebook_redirect() {
if(!$_GET['type']) {
		echo '<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="ko" xml:lang="ko"  xmlns:fb="http://ogp.me/ns/fb#"  xmlns:og="http://ogp.me/ns#" >
<head>
<meta charset="utf-8">';
		$this->facebookloginjs();
echo '<body><div id="fb-root"></div>';
}
		
		if(!$this->session->userdata('fbuser') ){
			$fbuserprofile = $this->snssocial->facebooklogin();
			if( !$fbuserprofile) {
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
			}else{
				if( !$this->session->userdata('fbuser') ) {
					$this->session->set_userdata('fbuser', $fbuserprofile);
				}
			}
		}

		if( $this->__APP_DOMAIN__ == $_SERVER['HTTP_HOST'] ) {
			$httphost = $_SERVER['HTTP_HOST'];
		}else{
			$httphost = $this->config_system['subDomain'];
		}

		$no				= ($_POST['no'])?$_POST['no']:$_GET['no'];
		$fblikeseq	= ($_POST['fblikeseq'])?$_POST['fblikeseq']:$_GET['fblikeseq'];//쇼핑몰좋아요seq
		$fblikeid		= ($_POST['fblikeid'])?$_POST['fblikeid']:$_GET['fblikeid'];//페이스북좋아요seq
		$mode			= ($_POST['mode'])?$_POST['mode']:$_GET['mode'];//unlike
		$facebooklikeopen			= ($_POST['facebooklikeopen'])?$_POST['facebooklikeopen']:$_GET['facebooklikeopen'];//unlike
		$product_url = $this->likeurl.'&no='.$no;
		$return_url		= ($_GET['returnurl'])?$_GET['returnurl']:$_SERVER['HTTP_REFERER'];
		$this->load->model('goodsfblike');

		unset($scripts);
		$scripts[] = "<script type='text/javascript'>";
		$scripts[] = "$(function() {";
		if($this->session->userdata('fbuser')) {
			if( $mode == 'unlike' && $fblikeid ) {
				$returndeleteaction = $this->snssocial->publishCustomActionLikedelete($fblikeid);
				$like_seq = $this->goodsfblike->set_fblike_goods($mode, $product_url, $fblikeid);
			}else{
				$returnid = $this->snssocial->publishCustomActionLike($product_url);//debug_var($returnid);
				if( $returnid['original_action_id'] ) {//이미등록된 경우
					$like_seq = $this->goodsfblike->set_fblike_goods($mode, $product_url, $returnid['original_action_id']);
					$facebookactionid = $returnid['original_action_id'];
					if( $mode == 'unlike' ) {
						$returndeleteaction = $this->snssocial->publishCustomActionLikedelete($returnid['original_action_id']);
					}
				}

				if(!$returnid['error']){//좋아요완료
					$like_seq = $this->goodsfblike->set_fblike_goods($mode, $product_url, $returnid['id']);
					$facebookactionid = $returnid['id'];
				}
			}



			if ( $facebooklikeopen == 'Y'){//새창
				$windowdocument = "opener.opener";
			}else{//레이어
				$windowdocument = "parent.parent";
			}

			$this->load->model('goodsmodel');
			$countreal = $this->snssocial->facebooklikestat($product_url,' like_count, share_count ');//실제좋아요갯수
			$this->goodsmodel->goods_like_count($no,$countreal);//like/share count save
			$count = $this->goodsmodel->goods_like_viewer($no);//상품의 좋아요정보가져오기
			$redirecturl = get_connet_protocol().$return_url."/snsredirect/openerreload?no=".$no."&likecount=".$count['like_count']."&fbuser=".$this->session->userdata('fbuser')."&fb_action_id=".$facebookactionid."&like_seq=".$like_seq."&mode=".$mode."&returndeleteaction=".$returndeleteaction."&facebooklikeopen=".$facebooklikeopen;
				if(!$returnid['error']){
					$scripts[] = "document.getElementById('snsFrame').src='".$redirecturl."';";
				}else{
					if($mode != 'unlike' ) {
						$redirecturl .= "&likefalse=1&message=".urlencode($returnid['message']);
					}

					$scripts[] = "document.getElementById('snsFrame').src='".$redirecturl."';";
				}
				if ( $facebooklikeopen == 'Y'){//새창
					$return = array('result'=>true,'retururl'=>$redirecturl);
					echo json_encode($return);
					exit;
				}
		}else{//페이스북로그인체크
			if ( $facebooklikeopen == 'Y'){//새창
				$return = array('result'=>false,'msg'=>'잘못된 접근입니다.');
				echo json_encode($return);
				exit;
			}else{
				$this->load->model('goodsmodel');
				$this->snssocial->facebooklikestat($product_url,' like_count, share_count ');//실제좋아요갯수
				$count = $this->goodsmodel->goods_like_viewer($no);//상품의 좋아요정보가져오기
				$redirecturl = get_connet_protocol().$return_url."/snsredirect/openerreload?no=".$no."&likecount=".$count['like_count']."&fbuser=".$this->session->userdata('fbuser')."&fb_action_id=".$facebookactionid."&like_seq=".$like_seq."&mode=".$mode."&returndeleteaction=".$returndeleteaction."&facebooklikeopen=".$facebooklikeopen;
				if($mode != 'unlike' ) {
					$redirecturl .= "&likefalse=1&message=".urlencode($returnid['message']);
				}
				$scripts[] = "document.getElementById('snsFrame').src='".$redirecturl."';";
			}
		}

		$scripts[] = "});";
		$scripts[] = "</script>";
		foreach($scripts as $script){
			echo $script."\n";
		}
		if(!$_GET['type']) {
			echo '<iframe name="snsFrame" id="snsFrame" src="" frameborder="0" width="0" height="0"></iframe>';//
			echo '</body></html>';
			exit;
		}
	}

	public function openerreload(){
		if(!$_GET['fbuser']){
			$this->session->unset_userdata('fbuser');
			$_SESSION['fbuser'] = '';
		}elseif( $_GET['fbuser']!=$this->session->userdata('fbuser')) {
			$fbuser = $_GET['fbuser'];
			$this->session->set_userdata('fbuser', $fbuser);//재설정
			$_SESSION['fbuser'] = $fbuser;
		}

		if ( $_GET['facebooklikeopen'] == 'Y'){//새창
			$windowdocument = "opener";
		}else{//레이어
			$windowdocument = "parent.parent";
		}

		echo '<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="ko" xml:lang="ko"  xmlns:fb="http://ogp.me/ns/fb#"  xmlns:og="http://ogp.me/ns#" >
<head>
<meta charset="utf-8">
<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
echo '<body>';
		$scripts[] = "<script type='text/javascript'>";
		$scripts[] = "$(function() {";
		if( $_GET['mode'] == 'unlike' ){
			$likeokimg = ($this->arrSns['fb_unlikebox_icon'])?$this->arrSns['fb_unlikebox_icon']:"/app/javascript/plugin/fblike/images/fbunlikebox.png?".time();
			$scripts[] = "$('.fb-og-like-img".$_GET['no']."',".$windowdocument.".document).attr('src','".$likeokimg."');";
			$scripts[] = "$('.fb-og-like-lay".$_GET['no']."',".$windowdocument.".document).attr('data-fblikeseq','');";
			$scripts[] = "$('.fb-og-like-lay".$_GET['no']."',".$windowdocument.".document).attr('data-fblikeid','');";
			//$scripts[] = "alert('페이스북 좋아요를 취소하였습니다.');";
		}else{
			if($this->session->userdata('fbuser') && $_GET['likecount']>0) {//$scripts[] = "alert('페이스북 좋아요가 성공하였습니다.');";
				$likeokimg = ($this->arrSns['fb_likebox_icon'])?$this->arrSns['fb_likebox_icon']:"/app/javascript/plugin/fblike/images/fblikebox.png?".time();//fb-og-like-img
				$scripts[] = "$('.fb-og-like-img".$_GET['no']."',".$windowdocument.".document).attr('src','".$likeokimg."');";
			}
		}
		if($_GET['like_seq'] && $_GET['mode'] != 'unlike' ) $scripts[] = "$('.fb-og-like-lay".$_GET['no']."',".$windowdocument.".document).attr('data-fblikeseq','".$_GET['like_seq']."');";
		if($_GET['fb_action_id']) $scripts[] = "$('.fb-og-like-lay".$_GET['no']."',".$windowdocument.".document).attr('data-fblikeid','".$_GET['fb_action_id']."');";

		if( ($_GET['likecount']) >= '0' ) {
			$scripts[] = "$('.fb-og-like-count".$_GET['no']."',".$windowdocument.".document).text('".$_GET['likecount']."');";
		}

		if($this->session->userdata('fbuser')) {
			$scripts[] = "$('.fb-og-like-login',".$windowdocument.".document).addClass('fb-og-like');";
			$scripts[] = "$('.fblikeopengrapybtn',".$windowdocument.".document).removeClass('fb-og-like-login');";
		}

		if(!$_GET['likefalse'] && $this->session->userdata('fbuser') ) {
			if( $_GET['mode'] == 'unlike' ){
				$resultmode = 'unlike';//$scripts[] = "openDialogAlert('페이스북 좋아요.\n 취소');";
			}else{
				$resultmode = 'like';//$scripts[] = "openDialogAlert('페이스북 좋아요.\n 성공');";
			}
		}else{
			$scripts[] = "$('.fb-og-like',".$windowdocument.".document).addClass('fb-og-like-login');";
			$scripts[] = "$('.fblikeopengrapybtn',".$windowdocument.".document).removeClass('fb-og-like');";
			$resultmode = 'false';//$scripts[] = "alert(\"페이스북 좋아요.\n다시 시도해 주세요.\")";//str_replace("\n","",$_GET['message'])
		}


		$scripts[] = "try{".$windowdocument.".order_price_calculate();}catch(e){};";
		if ( $_GET['facebooklikeopen'] == 'Y'){//새창
			$scripts[] = "self.close();";
			$scripts[] = $windowdocument.".facebooklay('".$resultmode."');";
		}else{
			$scripts[] = $windowdocument.".facebooklay('".$resultmode."');";
		}

		// 통계데이터(like) 전송
		/* 사용안함
		if( $_GET['mode'] != 'unlike' ){		
			$arr_goods_seq[] = $_GET['no'];			
			$str_goods_seq = implode('|',$arr_goods_seq);
			$scripts[] = $windowdocument.".statistics_firstmall('like','".$str_goods_seq."','','');";
		}*/

		$scripts[] = "});";
		$scripts[] = "</script>";
		foreach($scripts as $script){
			echo $script."\n";
		}
		echo '</body></html>';
		exit;
	}

	public function twitter_redirect() {
		$this->snssocial->twitter_redirect();
		exit;
	}

	public function me2day_redirect() {
		$this->snssocial->me2day_redirect();
		exit;
	}

	public function cyworldloginck() {
		$this->snssocial->cyworldloginck();
		exit;
	}

	public function setFacebooklikeopsave() {		
		$goods_seq = $this->input->post('no') ? $this->input->post('no', true) : $this->input->get('no', true);
		if(!$goods_seq) exit;
		$this->load->model('goodsmodel');
		if($this->mobileMode || $this->storemobileMode){
			$href = str_replace("//m.","//",$this->likeurl).'&no='.$goods_seq;
		}else{
			$href = $this->likeurl.'&no='.$goods_seq;
		}
		//$this->snssocial->writeLog('1');
		$count = $this->snssocial->facebooklikestat($href,' like_count, share_count ' );
		$this->goodsmodel->goods_like_count($goods_seq,$count);//like count save
		//$this->snssocial->writeLog('2');
	}
}

/* End of file sns_process.php */
/* Location: ./app/controllers/sns_process.php */