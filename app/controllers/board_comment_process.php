<?php
/**
 * 게시글 관련 관리자 process
 * @author gabia
 * @since version 1.0 - 2012.06.29
 */
 if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

use App\libraries\Password;

class Board_comment_process extends front_base {

	public function __construct() {

		parent::__construct();

		$this->load->model('ssl');
		$this->ssl->decode();

		$boardid = (!empty($_POST['board_id'])) ? $_POST['board_id']:$_GET['board_id'];
		$_POST['seq'] = (!empty($_POST['seq'])) ? (int) $_POST['seq'] : '';

		secure_vulnerability('board', 'boardid', $boardid, array('parent','parent.submitck();'));
		define('BOARDID',$boardid);

		$this->load->library('validation');
		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		$this->load->helper('captcha');
		$this->load->helper('board');
		$this->load->model('Boardscorelog');

		if( BOARDID == 'goods_qna' ) {
			$this->load->model('Goodsqna','Boardmodel');
		}elseif( BOARDID == 'goods_review' ) {
			$this->load->model('Goodsreview','Boardmodel');
		}elseif( BOARDID == 'bulkorder' ) {//대량구매게시판
				$this->load->model('Boardbulkorder','Boardmodel');
		}else{
			$this->load->model('Boardmodel');
		}
		$this->load->model('Boardindex');
		$this->load->model('Boardcomment');
		if(!empty($_POST['seq'])) {
			$this->boardurl->view			= $this->Boardmanager->realboardviewurl.BOARDID.'&seq='.$_POST['seq'];	//게시물보기
		}else{
			$this->boardurl->view			= $this->Boardmanager->realboardviewurl.BOARDID.'&seq=';	//게시물보기
		}
	}

	/* 기본 */
	public function index()
	{
		$mode = (!empty($_POST['mode']))?$_POST['mode']:$_GET['mode'];

		$requestParam = $this->input->post();

		$sc['whereis']	= ' and id= "'.BOARDID.'" ';
		$sc['select']		= ' * ';
		$manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		if (!isset($manager['id'])) {
			//존재하지 않는 게시판입니다.
			openDialogAlert(getAlert('et076'),400,140,'parent','');
			exit;
		}

		/**
		 * icon setting
		**/
		$this->icon_new_img			= ($manager['icon_new_img'] && @is_file($this->Boardmodel->upload_path.$manager['icon_new_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$manager['icon_new_img'].'?'.time():$this->Boardmanager->new_icon_src;//newicon
		$this->icon_hot_img			= ($manager['icon_hot_img'] && @is_file($this->Boardmodel->upload_path.$manager['icon_hot_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$manager['icon_hot_img'].'?'.time():$this->Boardmanager->hot_icon_src;//hoticon


		$this->re_img						= $this->Boardmanager->re_icon_src;//답변글icon
		$this->blank_img					= $this->Boardmanager->blank_icon_src;//blank
		$this->cmt_reply_del_img	= $this->Boardmanager->cmt_reply_icon_src;//cmtreplydel

		$seq = $requestParam['seq'];
		$boardsc['whereis'] = " and seq='{$seq}'";

		//본래게시글 추출@2017-05-12
		if( !(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder') ) {
			$boardsc['whereis']	.= ' and boardid= "'.BOARDID.'" ';
		}
		$boardsc['select']		= ' seq, comment, pw, hidden  ';
		$boarddata = $this->Boardmodel->get_data($boardsc);//원본게시글

		if(empty($boarddata['seq'])) {
			$callback = "parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';";
			//존재하지 않는 게시물입니다.
			openDialogAlert(getAlert('et084'),400,140,'parent',$callback);
			exit;
		}

 		/* 댓글리스트 */
		if($mode == 'board_comment') {

			$this->validation->set_data($requestParam);
			$this->validation->set_rules('orderby', '정렬선택', 'trim|alpha|xss_clean');
			$this->validation->set_rules('sort', '정렬방법', 'trim|alpha|xss_clean');
			$this->validation->set_rules('cmtpage', '페이징', 'trim|numeric|xss_clean');
			$this->validation->set_rules('perpage', '페이지갯수', 'trim|numeric|xss_clean');
			$this->validation->set_rules('search_text', '검색어', 'trim|string|xss_clean');
			$this->validation->set_rules('seq', '일련번호', 'trim|numeric|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value'],422);
			}

			$sc['orderby'] = (!empty($requestParam['orderby'])) ? $requestParam['orderby'] : 'seq';
			$sc['sort'] = (!empty($requestParam['sort'])) ? $requestParam['sort'] : 'desc';
			$sc['cmtpage'] = (!empty($requestParam['cmtpage'])) ? intval($requestParam['cmtpage']) : '0';
			
			$sc['perpage'] = (!empty($requestParam['perpage'])) ? intval($requestParam['perpage']) : '10';

			$sc['search_text'] = (!empty($requestParam['search_text'])) ? $requestParam['search_text'] : '';
			$sc['parent'] = (!empty($seq)) ? (int) $seq : '';

			$data = $this->Boardcomment->data_list($sc);//댓글목록

			$sc['searchcount']	 = $data['count'];
			$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
			$sc['totalcount']	 = $this->Boardcomment->get_data_total_count($sc);
			$idx = 0;
			$html = $this->getcmthtml($data, $sc, $manager, $boarddata);

			if (!empty($requestParam['cmtpage'])) {
				$returnurl = str_replace('&cmtpage=' . $requestParam['cmtpage'], '', str_replace('&cmtlist=1', '', $returnurl)) . '&cmtlist=1';
			} else {
				$returnurl = $requestParam['returnurl'] . $seq . '&cmtlist=1'; //
			}

			//$anchor_class = " onclick=\"parent.boardaddFormDialog(this.href, '80%', '800', '게시글 보기','false');return false;\" ";

			$paginlay =  pagingtag($sc['searchcount']	,$sc['perpage'], $requestParam['returnurl'], getLinkFilter('',array_keys($sc)), 'cmtpage', '');
			$paginlay = (!empty($paginlay)) ? $paginlay:'<p><a class="on red">1</a><p>';

			if(!empty($html)) {
				$result = array('returnurl'=>$requestParam['returnurl'], 'content'=>$html,'paginlay'=>$paginlay, 'search_count'=>$data['count'], 'total_count'=>$sc['totalcount']);
			}else{
				$result = array('returnurl'=>$requestParam['returnurl'], 'content'=>"", 'paginlay'=>$paginlay,  'search_count'=>$data['count'], 'total_count'=>$sc['totalcount']);
			}
			echo json_encode($result);
			exit;
		}

		/* 댓글수정시 정보보여주기 */
		elseif( $mode == 'board_comment_item' ) {
			$_POST['cmtseq']		= (int) $_POST['cmtseq'];

			$parentsc['whereis']	= ' and seq= "'.$_POST['cmtseq'].'" ';
			$parentsc['select']		= ' name, seq, subject, content, mseq, hidden, parent,display  ';
			$parentdata = $this->Boardcomment->get_data($parentsc);//댓글정보
			$parentdata['isperm_moddel'] = true;
			if( ( $parentdata['mseq'] > 0 && $parentdata['mseq'] == $this->userInfo['member_seq'] && defined('__ISUSER__') === true )  || ( empty($parentdata['mseq']) || $parentdata['mseq'] == 0 )  || ($parentdata['display'] == '1' ) ) {//작성자가 아니거나 비회원인 경우

				if ( empty($parentdata['mseq']) || $parentdata['mseq'] == 0 ) {
					$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$parentdata['parent'];
					$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
					if ( !strstr($boardpwwritess,'['.$parentdata['seq'].']') || empty($boardpwwritess) ) {
						$parentdata['isperm_moddel'] = false;//비회원 > 접근권한있음
					}
				}

				$result = array('content'=>$parentdata['content'], 'name'=>$parentdata['name'], 'subject'=>$parentdata['subject'], 'seq'=>$parentdata['seq'], 'mseq'=>$parentdata['mseq'], 'hidden'=>$parentdata['hidden'], 'isperm_moddel'=>$parentdata['isperm_moddel'], 'isperm_display'=>$parentdata['display']);
			}else{
				$result = '';
			}
			echo json_encode($result);
			exit;
		}

		/* 답글수정시 정보보여주기 */
		elseif( $mode == 'board_comment_reply_item' ) {
			$_POST['cmtseq']				= (int) $_POST['cmtseq'];
			$_POST['cmtreplyseq']		= (int) $_POST['cmtreplyseq'];
			$parentsc['whereis']	= ' and seq= "'.$_POST['cmtreplyseq'].'"  and cmtparent= "'.$_POST['cmtseq'].'" ';
			$parentsc['select']		= ' name, seq, subject, content, mseq, hidden, parent ,display  ';
			$parentdata = $this->Boardcomment->get_data($parentsc);//댓글정보
			$parentdata['isperm_moddel'] = true;

			if( ( $parentdata['mseq'] > 0 && $parentdata['mseq'] == $this->userInfo['member_seq'] && defined('__ISUSER__') === true )  || ( empty($parentdata['mseq']) || $parentdata['mseq'] == 0 )  || ($parentdata['display'] == '1') ) {//작성자가 아니거나 비회원인 경우

				if ( empty($parentdata['mseq']) || $parentdata['mseq'] == 0 ) {
					$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$parentdata['parent'];
					$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
					if ( !strstr($boardpwwritess,'['.$parentdata['seq'].']') || empty($boardpwwritess)) {
						$parentdata['isperm_moddel'] = false;//비회원 > 접근권한있음
					}
				}

				$result = array('content'=>$parentdata['content'], 'name'=>$parentdata['name'], 'subject'=>$parentdata['subject'], 'seq'=>$parentdata['seq'], 'mseq'=>$parentdata['mseq'], 'hidden'=>$parentdata['hidden'], 'isperm_moddel'=>$parentdata['isperm_moddel'], 'isperm_display'=>$parentdata['display']);
			}else{
				$result = '';
			}
			echo json_encode($result);
			exit;
		}
		
		elseif($mode == 'board_comment_more'){
			$_POST['seq']				= (int) $_POST['seq'];
			$page		= $_POST['page'] + 1;
			$viewlink	= (!empty($_POST['returnurl'])) ? $_POST['returnurl']."&page=".$page:$this->Boardmanager->realboardviewurl.BOARDID;
			$callback	= "parent.boardviewtype_m_only('".$viewlink."','".$_POST['seq']."','".$manager['viewtype']."','up','".$boarddata['comment']."');";

			echo "<script>".$callback."</script>";
			exit;
		}

		/* 댓글 등록 */
		elseif($mode == 'board_comment_write') {
			$requestPost = $this->input->post();
			// xss 필터링에서 제외
			$requestPost['contents'] = $this->input->post('contents', false);

			$requestPost['seq'] = (int) $requestPost['seq'];

			/* Emoji(4byte unicode) 처리 - Added by JP 2015-07-06
			   이모티콘을 직접저장(스마트폰에서는 보이게하기 위해서) 하기위해서는 테이블 케릭터넷을 4byte unicode 로 바꿔야함.
			*/
			$requestPost['content'] = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '?', $requestPost['content']);
			//이름
			$this->validation->set_rules('name', getAlert('et085'), 'trim|required|xss_clean');
			if (empty(defined('__ISUSER__'))) {
				//비밀번호
				$this->validation->set_rules('pw', getAlert('et415'), 'trim|required|xss_clean');
			}
			//내용
			$this->validation->set_rules('content', getAlert('et086'), 'trim|required|xss_clean');

			if ($this->validation->exec() === false) {
				$err = $this->validation->error_array;
				$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
				openDialogAlert($err['value'], 400, 140, 'parent', $callback);
				exit;
			}

			//비회원 댓글 등록 시 스킨 패치 후에만 개인정보 수집 및 이용 체크
			$is_agree = $this->input->post('agree');
			if (empty(defined('__ISUSER__')) && !empty($is_agree)) {
				if ($is_agree != 'y') {
					$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
					openDialogAlert(getAlert('et416'), 400, 140, 'parent', $callback);
					exit;
				}
			}

			if (BOARDID == 'goods_review') {
				get_auth($manager, '', 'write_cmt', $isperm); //접근권한체크
				if ($isperm['isperm_write_cmt'] != true) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'), 400, 140, 'parent', $callback);
					exit;
				}
			} elseif (BOARDID == 'notice') {//공지게시판인경우
				get_auth($manager, '', 'cmt', $isperm); //접근권한체크
				if ($isperm['isperm_cmt'] != true) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'), 400, 140, 'parent', $callback);
					exit;
				}
			} elseif ($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt', $isperm); //접근권한체크
				if ($isperm['isperm_cmt'] != true) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'), 400, 140, 'parent', $callback);
					exit;
				}
			} else {
				get_auth($manager, '', 'write', $isperm); //접근권한체크
				if ($isperm['isperm_write'] != true) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'), 400, 140, 'parent', $callback);
					exit;
				}
			}

			// 스팸방지 코드 체크 board_helper 2020-01-13 by hyem
			boardCaptchValidation('comment');

			$params['boardid'] = BOARDID;
			$params['parent'] = $requestPost['seq'];
			$params['subject'] = (isset($requestPost['subject'])) ? $requestPost['subject'] : '';
			$params['name'] = (isset($requestPost['name'])) ? $requestPost['name'] : '';
			$params['content'] = $requestPost['content'];

			if ($this->Boardmanager->cmthidden == 'Y') {//비밀댓글
				$params['hidden'] = if_empty($requestPost, 'hidden', '0');
			} else {
				$params['hidden'] = 0;
			}

			$pw = (!empty($requestPost['pw'])) ? Password::encrypt($requestPost['pw']) : '';
			$params['pw'] = (!empty($requestPost['oldpw'])) ? ($requestPost['oldpw']) : $pw;

			$params['r_date'] = date('Y-m-d H:i:s');
			$params['m_date'] = date('Y-m-d H:i:s');
			$params['ip'] = $this->input->ip_address();
			$params['agent'] = $_SERVER['HTTP_USER_AGENT'];

			//회원정보
			if (defined('__ISUSER__') === true) {
				$this->load->model('membermodel');
				$this->minfo = $this->membermodel->get_member_data($this->userInfo['member_seq']);
				$params['mseq'] = $this->userInfo['member_seq'];
				$params['mid'] = $this->userInfo['userid'];
				$params['pw'] = $this->minfo['password'];
				$params['mtype'] = 'u';
			} else {
				$params['mtype'] = 'o';
			}

			$params['cmtparent'] = 0;
			$params['depth'] = 0;

			$result = $this->Boardcomment->data_write($params);

			// 게시글 생성 후 실패나 성공여부에 상관 없이 작성요청 IP에 발생된 스팸방지 데이터를 초기화한다.
			$this->load->model('Captchamodel');
			$this->Captchamodel->data_delete(null, $this->input->ip_address());

			if ($result) {
				// 로직의 마지막 단계에 댓글 수를 Update하게 수정 :: rsh 2019-03-04
				// 댓글 수를 최신 상태로 갱신
				$upboarddata = $this->Boardmodel->comment_update($boarddata['seq']);
				$newseq = $result;

				//비회원이 등록 후 본문 확인가능함
				if (!defined('__ISUSER__')) {// && $params['hidden'] == 1
					// 비번입력후 브라우저를 닫기전까지는 접근가능함
					$ss_pwhidden_name = 'board_cmtpwhidden_' . BOARDID . '_' . $params['parent'];
					$boardpwhiddenss = $this->session->userdata($ss_pwhidden_name);
					$boardpwhiddenssadd = (!empty($boardpwhiddenss)) ? $boardpwhiddenss . '[' . $newseq . ']' : '[' . $newseq . ']';
					$this->session->set_userdata($ss_pwhidden_name, $boardpwhiddenssadd);
				}

				if ($requestPost['viewtype'] == 'ajax' || strstr($requestPost['returnurl'], 'mode=ajax')) {
					$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
					$callback = "parent.boardviewtype_m_only('" . $viewlink . "','" . $requestPost['seq'] . "','" . $manager['viewtype'] . "','up','" . $upboarddata['comment'] . "');";
				} elseif ($manager['viewtype'] == 'layer' && !strstr($_SERVER['HTTP_REFERER'], '/board/view')) {
					$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
					$callback = "parent.boardviewtypeshow('" . $viewlink . "','" . $requestPost['seq'] . "','" . $manager['viewtype'] . "');";
				} else {
					$callback = (!empty($requestPost['returnurl'])) ? "parent.document.location.href='" . $requestPost['returnurl'] . "';" : "parent.document.location.href='" . $this->Boardmanager->realboardviewurl . BOARDID . '&seq=' . $requestPost['seq'] . "';";
				}

				//댓글을 등록하였습니다.
				openDialogAlert(getAlert('et089'), 400, 140, 'parent', $callback);
			} else {
				//댓글 등록에 실패되었습니다.
				alert(getAlert('et090'));
			}
			exit;

		}

		/* 댓글의 답글 등록 */
		elseif($mode == 'board_comment_reply') {
			$requestPost = $this->input->post();
			// xss 필터링에서 제외
			$requestPost['content'] = $this->input->post('content', false);

			$requestPost['seq'] = (int) $requestPost['seq'];

			/* Emoji(4byte unicode) 처리 - Added by JP 2015-07-06
			   이모티콘을 직접저장(스마트폰에서는 보이게하기 위해서) 하기위해서는 테이블 케릭터넷을 4byte unicode 로 바꿔야함.
			*/
			$requestPost['content']	= preg_replace('/[\x{1F600}-\x{1F64F}]/u', '?', $requestPost['content']);

			if (defined('__ISUSER__') === false && ($requestPost['name'] == '이름을 입력해 주세요.' || empty($requestPost['name'])) )
			{
				//이름을 정확히 입력해 주세요.
				$return = array('result'=>false, 'msg'=>getAlert('et091'));
				echo json_encode($return);
				exit;
			}

			if (defined('__ISUSER__') === false && empty($requestPost['pw']) )
			{
				$return = array('result'=>false, 'msg'=>getAlert('et092'));
				echo json_encode($return);
				exit;
			}

			//회원정보
			if( defined('__ISUSER__') === true ) {
				$requestPost['name'] = $this->userInfo['user_name'];
			}

			//이름
			$this->validation->set_rules('name', getAlert('et085'),'trim|required|xss_clean');
			//내용
			$this->validation->set_rules('content', getAlert('et086'),'trim|required|xss_clean');
			if($this->validation->exec()===false){
			   $err = $this->validation->error_array;
				$return = array('result'=>false, 'msg'=>$err['value']);
				echo json_encode($return);
				exit;
			}

			if( BOARDID == 'goods_review' ){
				get_auth($manager, '', 'write_cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_write_cmt'] != true ) {
					//"잘못된 접근입니다."
					$return = array('result'=>false, 'msg'=>getAlert('et083'));
					echo json_encode($return);
					//openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
					exit;
				}
			}elseif(BOARDID == 'notice' ) {//공지게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					$return = array('result'=>false, 'msg'=>getAlert('et083'));
					echo json_encode($return);
					//openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
					exit;
				}
			}elseif($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//"잘못된 접근입니다."
					$return = array('result'=>false, 'msg'=>getAlert('et083'));
					echo json_encode($return);
					//openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
					exit;
				}
			}else{
				get_auth($manager, '', 'write' , $isperm);//접근권한체크
				if ( $isperm['isperm_write'] != true ) {
					//잘못된 접근입니다.
					$return = array('result'=>false, 'msg'=>getAlert('et083'));
					echo json_encode($return);
					//openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
					exit;
				}
			}

			// 스팸방지 코드 체크 board_helper 2020-01-13 by hyem
			boardCaptchValidation('comment',TRUE);

			//비회원 댓글 등록 시 스킨 패치 후에만 개인정보 수집 및 이용 체크
			$is_agree = $this->input->post('agree');
			if(empty(defined('__ISUSER__')) && !empty($is_agree) ){
				if($is_agree != 'y') {
					$return = array('result'=>false, 'msg'=>getAlert('et416'));
					echo json_encode($return);
					exit;
				}
			}

			$params['boardid']		=  BOARDID;
			$params['parent'] = $requestPost['seq'];
			$params['subject'] = (isset($requestPost['subject'])) ? $requestPost['subject'] : '';
			$params['name'] = (isset($requestPost['name'])) ? $requestPost['name'] : '';
			$params['content'] = $requestPost['content'];


			if( $this->Boardmanager->cmthidden == "Y" ) {//비밀댓글
				$params['hidden'] = if_empty($requestPost, 'hidden', '0');
			}else{
				$params['hidden']		= 0;
			}

			$pw = (!empty($requestPost['pw'])) ? Password::encrypt($requestPost['pw']) : '';
			$params['pw'] = (!empty($requestPost['oldpw'])) ? ($requestPost['oldpw']) : $pw;

 			$params['r_date']		= date("Y-m-d H:i:s");
			$params['m_date']		= date("Y-m-d H:i:s");
			$params["ip"]				= $this->input->ip_address();
			$params["agent"]		= $_SERVER['HTTP_USER_AGENT'];

			//회원정보
			if( defined('__ISUSER__') === true ) {

				$this->load->model('membermodel');
				$this->minfo = $this->membermodel->get_member_data($this->userInfo['member_seq']);
				$params['mseq']		= $this->userInfo['member_seq'];
				$params['mid']			= $this->userInfo['userid'];
				$params['pw']			= $this->minfo['password'];
				$params['mtype']	= 'u';
			} else {				
				$params['mtype']	= 'o';
			}

			$parentsql['whereis'] = ' and seq= "' . $requestPost['cmtseq'] . '" ';
			$parentsql['select']		= ' seq, depth, parent ';
			$parentdata = $this->Boardcomment->get_data($parentsql);//댓글정보
			if(empty($parentdata['seq'])) {
				$return = array('result'=>false, 'msg'=>getAlert('et078'));
				echo json_encode($return);
				exit;
			}

			$params['cmtparent'] = $requestPost['cmtseq'];
			$params['depth']			= $parentdata['depth']+1;
			$result = $this->Boardcomment->data_write($params);

			// 게시글 생성 후 실패나 성공여부에 상관 없이 작성요청 IP에 발생된 스팸방지 데이터를 초기화한다.
			$this->load->model('Captchamodel');
			$this->Captchamodel->data_delete(null,$this->input->ip_address());

			if($result) {
				$newseq = $result;

				//비회원이 등록 후 본문 확인가능함
				if( !defined('__ISUSER__') ) {
					// 비번입력후 브라우저를 닫기전까지는 접근가능함
					$ss_pwhidden_name = 'board_cmtpwhidden_'.BOARDID.'_'.$params['parent'];
					$boardpwhiddenss = $this->session->userdata($ss_pwhidden_name);
					$boardpwhiddenssadd = (!empty($boardpwhiddenss)) ? $boardpwhiddenss.'['.$newseq.']':'['.$newseq.']';
					$this->session->set_userdata($ss_pwhidden_name, $boardpwhiddenssadd );
				}

				// 로직의 마지막 단계에 댓글 수를 Update하게 수정 :: rsh 2019-03-04
				// 댓글 수를 최신 상태로 갱신
				$upboarddata = $this->Boardmodel->comment_update($boarddata['seq']);

				if ($requestPost['viewtype'] == 'ajax' || strstr($requestPost['returnurl'], 'mode=ajax')) {
					$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
					$callback = "parent.boardviewtype_m_only('" . $viewlink . "','" . $requestPost['seq'] . "','" . $manager['viewtype'] . "','up','" . $upboarddata['comment'] . "');";
				} elseif ($manager['viewtype'] == 'layer' && !strstr($_SERVER['HTTP_REFERER'], '/board/view')) {
					$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
					$callback = "parent.boardviewtypeshow('" . $viewlink . "','" . $requestPost['seq'] . "','" . $manager['viewtype'] . "');";
				}
				//답글을 등록하였습니다.
				$return = array('result'=>true,'msg'=>getAlert('et093'),"callback"=>$callback);
				echo json_encode($return);
			}else{
				//답글등록에 실패되었습니다.
				$return = array('result'=>false, 'msg'=>getAlert('et094'));
				echo json_encode($return);
			}
			exit;
		}

		/* 댓글 수정 */
		elseif($mode == 'board_comment_modify') {
			$requestPost = $this->input->post();
			// xss 필터링에서 제외
			$requestPost['content'] = $this->input->post('content', false);


			$requestPost['seq'] = (int) $requestPost['seq'];
			$requestPost['cmtseq'] = (int) $requestPost['cmtseq'];

			if (strstr($requestPost['name'], '입력해 주세요.')) {
				$requestPost['name'] = '';
			}

			/* Emoji(4byte unicode) 처리 - Added by JP 2015-07-06
			   이모티콘을 직접저장(스마트폰에서는 보이게하기 위해서) 하기위해서는 테이블 케릭터넷을 4byte unicode 로 바꿔야함.
			*/
			$requestPost['content']	= preg_replace('/[\x{1F600}-\x{1F64F}]/u', '?', $requestPost['content']);

			//이름
			$this->validation->set_rules('name', getAlert('et085'),'trim|required|xss_clean');
			//내용
			$this->validation->set_rules('content', getAlert('et086'),'trim|required|xss_clean');
			if($this->validation->exec()===false){
			   $err = $this->validation->error_array;
			   $callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			   openDialogAlert($err['value'],400,140,'parent',$callback);
			   exit;
			}


			if( BOARDID == 'goods_review' ){
				get_auth($manager, '', 'write_cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_write_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif(BOARDID == 'notice' ) {//공지게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}else{
				get_auth($manager, '', 'write' , $isperm);//접근권한체크
				if ( $isperm['isperm_write'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}

			// 스팸방지 코드 체크 board_helper 2020-01-13 by hyem
			boardCaptchValidation('comment');

			$parentsql['whereis'] = ' and seq= "' . $requestPost['cmtseq'] . '" ';
			$parentsql['select']	= ' seq, pw, mseq, parent ';
			$parentdata = $this->Boardcomment->get_data($parentsql);//댓글정보
			if(empty($parentdata['seq'])) {
				alert(getAlert('et078'));
				exit;
			}

			if( $parentdata['mseq'] < 0 ) {//관리자권한
				//잘못된 접근입니다.
				alert(getAlert('et083'));
				exit;
			}

			$parentdata['isperm_moddel'] =  false;
			if ( defined('__ISUSER__') === true && $parentdata['mseq']  == $this->userInfo['member_seq']) {
				$parentdata['isperm_moddel'] =  true;
			}else{
				$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$parentdata['parent'];
				$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
				if ( strstr($boardpwwritess,'['.$parentdata['seq'].']') && !empty($boardpwwritess)) {
					$parentdata['isperm_moddel'] = true;//비회원 > 접근권한있음
				}else{
					if (empty($requestPost['pw'])) {
						//잘못된 접근입니다
						alert(getAlert('et083'));
						exit;
					}
				}
			}

			$params['subject'] = $requestPost['subject'];
			$params['name'] = $requestPost['name'];
			$params['content'] = $requestPost['content'];
			$params['m_date'] = date('Y-m-d H:i:s');
			$params['ip'] = $this->input->ip_address();
			$pw = (!empty($requestPost['pw'])) ? Password::encrypt($requestPost['pw']) : '';
			$params['pw'] = (!empty($requestPost['oldpw'])) ? ($requestPost['oldpw']) : $pw;

			//회원정보
			if( defined('__ISUSER__') === true  && $parentdata['mseq']  == $this->userInfo['member_seq'] ) {
				$this->load->model('membermodel');
				$this->minfo = $this->membermodel->get_member_data($this->userInfo['member_seq']);
				$params['mseq']		= $this->userInfo['member_seq'];
				$params['mid']			= $this->userInfo['userid'];
				$params['pw']			= $this->minfo['password'];
			}

			if( $this->Boardmanager->cmthidden == "Y" ) {//비밀댓글
				$params['hidden'] = if_empty($requestPost, 'hidden', '0');
			}else{
				$params['hidden']		= 0;
			}

			$result = $this->Boardcomment->data_modify($params);

			// 게시글 생성 후 실패나 성공여부에 상관 없이 작성요청 IP에 발생된 스팸방지 데이터를 초기화한다.
			$this->load->model('Captchamodel');
			$this->Captchamodel->data_delete(null,$this->input->ip_address());
			
			if($result) {

			    // 로직의 마지막 단계에 댓글 수를 Update하게 수정 :: rsh 2019-03-04
			    // 댓글 수를 최신 상태로 갱신
			    $upboarddata = $this->Boardmodel->comment_update($boarddata['seq']);

				if ($requestPost['viewtype'] == 'ajax' || strstr($requestPost['returnurl'], 'mode=ajax')) {
					$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
					$callback = "parent.boardviewtype_m_only('" . $viewlink . "','" . $requestPost['p_seq'] . "','" . $manager['viewtype'] . "','','" . $boarddata['comment'] . "');";
				} elseif ($manager['viewtype'] == 'layer' && !strstr($_SERVER['HTTP_REFERER'], '/board/view')) {
					$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
					$callback = "parent.boardviewtypeshow('" . $viewlink . "','" . $requestPost['seq'] . "','" . $manager['viewtype'] . "');";
				} else {
					$callback = (!empty($requestPost['returnurl'])) ? "parent.document.location.href='" . $requestPost['returnurl'] . "';" : "parent.document.location.href='" . $this->Boardmanager->realboardviewurl . BOARDID . '&seq=' . $requestPost['seq'] . "';";
				}
				//댓글을 수정하였습니다.
				openDialogAlert(getAlert('et095'),400,140,'parent',$callback);
			}else{
				//댓글 수정이 실패 되었습니다.
				alert(getAlert('et096'));
			}
			exit;
		}

		/* 비회원 > 댓글 수정 */
		elseif($mode == 'board_comment_modify_pwcheck') {
			$requestPost = $this->input->post();	
			// xss 필터링에서 제외
			$requestPost['contents'] = $this->input->post('contents', false);


			$requestPost['seq'] = (int) $requestPost['seq'];
			$requestPost['cmtseq'] = (int) $requestPost['cmtseq'];

			if (strstr($requestPost['content'], '입력해 주세요.')) {
				$requestPost['content'] = '';
			}

			/* Emoji(4byte unicode) 처리 - Added by JP 2015-07-06
			   이모티콘을 직접저장(스마트폰에서는 보이게하기 위해서) 하기위해서는 테이블 케릭터넷을 4byte unicode 로 바꿔야함.
			*/
			$requestPost['content'] = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '?', $requestPost['content']);

			if( BOARDID == 'goods_review' ){
				get_auth($manager, '', 'write_cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_write_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif(BOARDID == 'notice' ) {//공지게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}else{
				get_auth($manager, '', 'write' , $isperm);//접근권한체크
				if ( $isperm['isperm_write'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}

			// 스팸방지 코드 체크 board_helper 2020-01-13 by hyem
			boardCaptchValidation('comment');

			//비회원 댓글 등록 시 스킨 패치 후에만 개인정보 수집 및 이용 체크
			$is_agree = $this->input->post('agree');
			if (empty(defined('__ISUSER__')) && !empty($is_agree)) {
				if ($is_agree != 'y') {
					$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
					openDialogAlert(getAlert('et416'), 400, 140, 'parent', $callback);
					exit;
				}
			}

			if (empty($requestPost['cmtseq'])) {
				//잘못된 접근입니다1.
				alert(getAlert('et083'));
				exit;
			}

			$parentsql['whereis'] = ' and seq= "' . $requestPost['cmtseq'] . '" ';
			$parentsql['select']		= ' seq, pw, mseq, parent ';
			$parentdata = $this->Boardcomment->get_data($parentsql);//댓글정보
			if(empty($parentdata['seq'])) {
				alert(getAlert('et078'));
				exit;
			}

			if( $parentdata['mseq'] < 0 ) {//관리자권한
				//잘못된 접근입니다.
				alert(getAlert('et083'));
				exit;
			}

			$parentdata['isperm_moddel'] =  false;
			if ( defined('__ISUSER__') === true && $parentdata['mseq']  == $this->userInfo['member_seq']) {
				$parentdata['isperm_moddel'] =  true;
			}else{
				$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$parentdata['parent'];
				$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
				if ( strstr($boardpwwritess,'['.$parentdata['seq'].']') && !empty($boardpwwritess)) {
					$parentdata['isperm_moddel'] = true;//비회원 > 접근권한있음
				}else{
					if (empty($requestPost['pw'])) {
						//잘못된 접근입니다
						alert(getAlert('et083'));
						exit;
					}
				}
			}

			if ( 
				$parentdata['isperm_moddel'] === true
				|| (Password::isConfirm($requestPost['pw'], $parentdata['pw']) === true && $parentdata['isperm_moddel'] === false) 
			 ) {
				$params['subject']		=  $requestPost['subject'];
				$params['name']			=  $requestPost['name'];
				$params['content']		=  $requestPost['content'];
				$params['m_date']		= date("Y-m-d H:i:s");
				$params["ip"]				= $this->input->ip_address();

				//회원정보
				if( defined('__ISUSER__') === true  && $parentdata['mseq']  == $this->userInfo['member_seq']) {
					$params['mseq']		= $this->userInfo['member_seq'];
					$params['mid']			= $this->userInfo['userid'];
				}

				if( $this->Boardmanager->cmthidden == "Y" ) {//비밀댓글
					$params['hidden'] = if_empty($requestPost, 'hidden', '0');
				}else{
					$params['hidden']		= 0;
				}

				$result = $this->Boardcomment->data_modify($params);

				// 게시글 생성 후 실패나 성공여부에 상관 없이 작성요청 IP에 발생된 스팸방지 데이터를 초기화한다.
				$this->load->model('Captchamodel');
				$this->Captchamodel->data_delete(null,$this->input->ip_address());

				if($result) {

				    // 로직의 마지막 단계에 댓글 수를 Update하게 수정 :: rsh 2019-03-04
				    // 댓글 수를 최신 상태로 갱신
				    $upboarddata = $this->Boardmodel->comment_update($boarddata['seq']);

					if ($requestPost['viewtype'] == 'ajax' || strstr($requestPost['returnurl'], 'mode=ajax')) {
						$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
						$callback = "parent.boardviewtype_m_only('" . $viewlink . "','" . $requestPost['p_seq'] . "','" . $manager['viewtype'] . "','','" . $upboarddata['comment'] . "');";
					} elseif ($manager['viewtype'] == 'layer' && !strstr($_SERVER['HTTP_REFERER'], '/board/view')) {
						$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
						$callback = "parent.boardviewtypeshow('" . $viewlink . "','" . $requestPost['seq'] . "','" . $manager['viewtype'] . "');";
					} else {
						$callback = (!empty($requestPost['returnurl'])) ? "parent.document.location.href='" . $requestPost['returnurl'] . "';" : "parent.document.location.href='" . $this->Boardmanager->realboardviewurl . BOARDID . '&seq=' . $requestPost['seq'] . "';";
					}
					//댓글을 수정하였습니다.
					openDialogAlert(getAlert('et095'),400,140,'parent',$callback);
				}else{
					//댓글 수정이 실패 되었습니다.
					alert(getAlert('et096'));
				}
			}else{
				//비밀번호가 일치하지 않습니다.
				alert(getAlert('et097'));
			}
			exit;
		}

		/* 비회원 > 댓글 > 답글 수정 */
		elseif($mode == 'board_comment_reply_modify_pwcheck') {
			$requestPost = $this->input->post();
			// xss 필터링에서 제외
			$requestPost['contents'] = $this->input->post('contents', false);


			$requestPost['seq'] = (int) $requestPost['seq'];
			$requestPost['cmtseq'] = (int) $requestPost['cmtseq'];

			if (strstr($requestPost['content'], '입력해 주세요.')) {
				$requestPost['content'] = '';
			}


			/* Emoji(4byte unicode) 처리 - Added by JP 2015-07-06
			   이모티콘을 직접저장(스마트폰에서는 보이게하기 위해서) 하기위해서는 테이블 케릭터넷을 4byte unicode 로 바꿔야함.
			*/
			$requestPost['content'] = preg_replace('/[\x{1F600}-\x{1F64F}]/u', '?', $requestPost['content']);

			if( BOARDID == 'goods_review' ){
				get_auth($manager, '', 'write_cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_write_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif(BOARDID == 'notice' ) {//공지게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}else{
				get_auth($manager, '', 'write' , $isperm);//접근권한체크
				if ( $isperm['isperm_write'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}

			// 스팸방지 코드 체크 board_helper 2020-01-13 by hyem 2022.01.27 ajax로 답글 달 경우 얼럿나오도록수정 by pja
			boardCaptchValidation('comment',TRUE);

			//비회원 답글수정 \시 스킨 패치 후에만 개인정보 수집 및 이용 체크
			$is_agree = $this->input->post('agree');
			if (empty(defined('__ISUSER__')) && !empty($is_agree)) {
				if ($is_agree != 'y') {
					$return = ['result' => false, 'msg' => getAlert('et416')];
					echo json_encode($return);
					exit;
				}
			}

			if (empty($requestPost['cmtseq'])) {
				//잘못된 접근입니다.
				$return = ['result' => false, 'msg' => getAlert('et083')];
				echo json_encode($return);
				exit;
			}

			$parentsql['whereis'] = ' and seq= "' . $requestPost['cmtseq'] . '" ';
			$parentsql['select']		= ' seq, pw, mseq, parent ';
			$parentdata = $this->Boardcomment->get_data($parentsql);//댓글정보
			if(empty($parentdata['seq'])) {
				//존재하지 않는 댓글입니다.
				$return = array('result'=>false, 'msg'=>getAlert('et078'));
				echo json_encode($return);
				exit;
			}

			if( $parentdata['mseq'] < 0 ) {//관리자권한
				//잘못된 접근입니다.
				$return = array('result'=>false, 'msg'=>getAlert('et083'));
				echo json_encode($return);
				exit;
			}


			$parentdata['isperm_moddel'] =  false;
			if ( defined('__ISUSER__') === true && $parentdata['mseq']  == $this->userInfo['member_seq']) {
				$parentdata['isperm_moddel'] =  true;
			}else{
				$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$parentdata['parent'];
				$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
				if ( strstr($boardpwwritess,'['.$parentdata['seq'].']') && !empty($boardpwwritess)) {
					$parentdata['isperm_moddel'] = true;//비회원 > 접근권한있음
				}else{
					if (empty($requestPost['pw'])) {
						//잘못된 접근입니다.
						$return = ['result' => false, 'msg' => getAlert('et083')];
						echo json_encode($return);
						exit;
					}
				}
			}

			if (
				$parentdata['isperm_moddel'] === true
				// 비밀번호가 맞는 경우
				|| (Password::isConfirm($requestPost['pw'], $parentdata['pw']) === true && $parentdata['isperm_moddel'] === false ) 
				 
			) {
				$params['subject']		=  $requestPost['subject'];
				$params['name']			=  $requestPost['name'];
				$params['content']		=  $requestPost['content'];

				if( $this->Boardmanager->cmthidden == "Y" ) {//비밀댓글
					$params['hidden'] = if_empty($requestPost, 'hidden', '0');
				}else{
					$params['hidden']		= 0;
				}

				$params['m_date']		= date("Y-m-d H:i:s");
				$params["ip"]				= $this->input->ip_address();

				//회원정보
				if( defined('__ISUSER__') === true  && $parentdata['mseq']  == $this->userInfo['member_seq']) {
					$params['mseq']		= $this->userInfo['member_seq'];
					$params['mid']			= $this->userInfo['userid'];
				}

				$result = $this->Boardcomment->data_modify($params);

				// 게시글 생성 후 실패나 성공여부에 상관 없이 작성요청 IP에 발생된 스팸방지 데이터를 초기화한다.
				$this->load->model('Captchamodel');
				$this->Captchamodel->data_delete(null,$this->input->ip_address());
				
				if($result) {

				    // 로직의 마지막 단계에 댓글 수를 Update하게 수정 :: rsh 2019-03-04
				    // 댓글 수를 최신 상태로 갱신
				    $upboarddata = $this->Boardmodel->comment_update($boarddata['seq']);

					if ($requestPost['viewtype'] == 'ajax' || strstr($requestPost['returnurl'], 'mode=ajax')) {
						$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
						$callback = "boardviewtype_m_only('" . $viewlink . "','" . $requestPost['p_seq'] . "','" . $manager['viewtype'] . "','','" . $upboarddata['comment'] . "');";
					} elseif ($manager['viewtype'] == 'layer' && !strstr($_SERVER['HTTP_REFERER'], '/board/view')) {
						$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
						$callback = "parent.boardviewtypeshow('" . $viewlink . "','" . $requestPost['seq'] . "','" . $manager['viewtype'] . "');";
					}

					//댓글을 수정하였습니다.
					$return = array('result'=>true, 'msg'=>getAlert('et095'),"callback"=>$callback);
					echo json_encode($return);
				}else{
					//댓글 수정이 실패 되었습니다.
					$return = array('result'=>true, 'msg'=>getAlert('et096'));
					echo json_encode($return);
				}
			}else{
				//비밀번호가 일치하지 않습니다.
				$return = array('result'=>false, 'msg'=>getAlert('et097'));
				echo json_encode($return);
			}
			exit;
		}

		/* 회원 > 댓글 삭제 */
		elseif($mode == 'board_comment_delete') {
			$requestParam['contents'] = $this->input->post('contents', false);
			$requestParam['seq'] = (int) $requestParam['seq'];
			$requestParam['delcmtseq'] = (int) $requestParam['delcmtseq'];

			if( BOARDID == 'goods_review' ){
				get_auth($manager, '', 'write_cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_write_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif(BOARDID == 'notice' ) {//공지게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}else{
				get_auth($manager, '', 'write' , $isperm);//접근권한체크
				if ( $isperm['isperm_write'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}

			//회원제한인경우 or 회원전체인경우
			if ( ( strstr($manager['auth_'.$mode],'[member]') || strstr($manager['auth_'.$mode],'[memberall]') ) && defined('__ISUSER__') != true ) {
				//잘못된 접근입니다.
				$return = array('result'=>false, 'msg'=>getAlert('et083'));
				echo json_encode($return);
				exit;
			}

			$parentsql['whereis']	= ' and seq= "'.$requestParam['delcmtseq'].'" ';
			$parentsql['select']		= ' mseq, seq, depth, parent, display';
			$parentdata = $this->Boardcomment->get_data($parentsql);//댓글정보

			// 댓글 고유번호가 없거나, 회원 고유번호가 유효하지 않은 경우
			if(empty($parentdata['seq']) ||  $parentdata['mseq'] < 0 || $this->userInfo['member_seq'] != $parentdata['mseq']) {
				//잘못된 접근입니다.
				$return = array('result'=>false, 'msg'=>getAlert('et083'));
				echo json_encode($return);
				exit;
			}
			
			$replyor  = 0;
			if($parentdata['depth'] == 0 ) {				
				$replysc['whereis']	= ' and cmtparent = '.$parentdata['seq'].' and depth = 1 ';
				$replysc['select']		= " seq ";
				$replyor = $this->Boardcomment->get_data_numrow($replysc);//
			}

			if($replyor==0 || $parentdata['depth'] == 1 ) {//답글이 없거나 답글인 경우 real 삭제				
				$result = $this->Boardcomment->data_delete($requestParam['delcmtseq']);
			}else{
				if($parentdata['display'] == 1) {
					//이미 삭제된 글입니다.
					$return = array('result'=>false, 'msg'=>getAlert('et098'));
					echo json_encode($return);
					exit;
				}
				$params['display']			= '1';//삭제글여부1
				$params['subject']			= '';//초기화함
				$params['content']			= '';//초기화함
				$params['r_date']			= date("Y-m-d H:i:s");
				$result = $this->Boardcomment->data_delete_modify($params, $requestParam['delcmtseq']);				
			}

			if($result) {

				//댓글평가제거
				$this->Boardscorelog->data_cparent_delete($parentdata['parent'], $requestParam['delcmtseq']);

				// 로직의 마지막 단계에 댓글 수를 Update하게 수정 :: rsh 2019-03-04
				// 댓글 수를 최신 상태로 갱신
				$upboarddata = $this->Boardmodel->comment_update($boarddata['seq']);
				
				$skin_config = skin_configuration($this->skin);
				if( $requestParam['viewtype']=='ajax'  || strstr($requestParam['returnurl'],'mode=ajax')) {
					$viewlink =  (!empty($requestParam['returnurl'])) ? $requestParam['returnurl']:$this->Boardmanager->realboardviewurl.BOARDID;
					$callback = "boardviewtype_m_only('".$viewlink."','".$requestParam['p_seq']."','".$manager['viewtype']."','','".$upboarddata['comment']."');";
				}elseif( ($this->mobileMode && $skin_config['mobile_version'] >= 2) ||  ($manager['viewtype'] == 'layer' && !strstr($_SERVER['HTTP_REFERER'],'/board/view')) ) {
					$viewlink =  (!empty($requestParam['returnurl'])) ? $requestParam['returnurl']:$this->Boardmanager->realboardviewurl.BOARDID;
					$callback = "parent.boardviewtypeshow('".$viewlink."','".$requestParam['seq']."','".$manager['viewtype']."');";
				}
				//정상적으로 삭제되었습니다.
				$return = array('result'=>true, 'msg'=>getAlert('et099'),"callback"=>$callback,"comment_cnt"=>$upboarddata['comment']);
				echo json_encode($return);
				exit;
			}else{
				//댓글 삭제가 실패 되었습니다.
				$return = array('result'=>false, 'msg'=>getAlert('et100'));
				echo json_encode($return);
			}
			exit;
		}

		/* 비회원 > 비밀번호 확인 후 > 댓글 삭제 */
		elseif($mode == 'board_comment_delete_pwcheck') {
			$requestPost = $this->input->post();
			// xss 필터링에서 제외
			$requestPost['pw'] = $this->input->post('pw', false);

			$requestPost['seq'] = (int) $requestPost['seq'];
			$requestPost['delcmtseq'] = (int) $requestPost['delcmtseq'];

			if( BOARDID == 'goods_review' ){
				get_auth($manager, '', 'write_cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_write_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif(BOARDID == 'notice' ) {//공지게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}else{
				get_auth($manager, '', 'write' , $isperm);//접근권한체크
				if ( $isperm['isperm_write'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}

			if (empty($requestPost['delcmtseq'])) {
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'), 400, 140, 'parent', $callback);
				exit;
			}

			$parentsql['whereis'] = ' and seq= "' . $requestPost['delcmtseq'] . '" ';
			$parentsql['select']		= ' seq, depth, pw, mseq, parent ';
			$parentdata = $this->Boardcomment->get_data($parentsql);//댓글정보
			if(!isset($parentdata['seq'])) {
				//존재하지 않는 댓글입니다.
				$return = array('result'=>false, 'msg'=>getAlert('et078'));
				echo json_encode($return);
				exit;
			}

			if( $parentdata['mseq'] < 0 ) {//관리자권한
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			if ( $parentdata['mseq'] > 0 && $parentdata['mseq'] != $this->userInfo['member_seq'] ) {//본인글체크
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			$parentdata['isperm_moddel'] =  false;
			if ( defined('__ISUSER__') === true && $parentdata['mseq']  == $this->userInfo['member_seq']) {
				$parentdata['isperm_moddel'] =  true;
			}else{
				$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$parentdata['parent'];
				$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
				if ( strstr($boardpwwritess,'['.$parentdata['seq'].']') && !empty($boardpwwritess)) {
					$parentdata['isperm_moddel'] = true;//비회원 > 접근권한있음
				}else{
					if (empty($requestPost['pw'])) {
						//잘못된 접근입니다.
						openDialogAlert(getAlert('et083'), 400, 140, 'parent', $callback);
						exit;
					}
				}
			}

			if ( 
				$parentdata['isperm_moddel'] === true 
				|| (Password::isConfirm($requestPost['pw'], $parentdata['pw']) === true && $parentdata['isperm_moddel'] === false) 
			) {
				$replyor = 0;
				if($parentdata['depth'] == 0 ) {
					$replysc['whereis']	= ' and cmtparent = '.$parentdata['seq'].' and depth = 1 ';
					$replysc['select']		= " seq ";
					$replyor = $this->Boardcomment->get_data_numrow($replysc);//
				}

				if($replyor==0 || $parentdata['depth'] == 1 ) {//답글이 없거나 답글인 경우 real 삭제
					$result = $this->Boardcomment->data_delete($requestPost['delcmtseq']);
				}else{
					$params['display']			= '1';//삭제글여부1
					$params['subject']			= '';//초기화함
					$params['content']			= '';//초기화함
					$params['r_date']			= date("Y-m-d H:i:s");
					$result = $this->Boardcomment->data_delete_modify($params,$requestPost['delcmtseq']);
				}

				if($result) {
					//댓글평가제거
					$this->Boardscorelog->data_cparent_delete($parentdata['parent'],$requestPost['delcmtseq']);

					// 로직의 마지막 단계에 댓글 수를 Update하게 수정 :: rsh 2019-03-04
					// 댓글 수를 최신 상태로 갱신
					$upboarddata = $this->Boardmodel->comment_update($boarddata['seq']);
					
					if( $requestPost['viewtype']=='ajax'  || strstr($requestPost['returnurl'],'mode=ajax')){
						$viewlink =  (!empty($requestPost['returnurl'])) ? $requestPost['returnurl']:$this->Boardmanager->realboardviewurl.BOARDID;
						$callback = "boardviewtype_m_only('".$viewlink."','".$requestPost['p_seq']."','".$manager['viewtype']."','','".$upboarddata['comment']."');";
					}elseif($manager['viewtype'] == 'layer' && !strstr($_SERVER['HTTP_REFERER'],'/board/view') ) {
						$viewlink =  (!empty($requestPost['returnurl'])) ? $requestPost['returnurl']:$this->Boardmanager->realboardviewurl.BOARDID;
						$callback = "parent.boardviewtypeshow('".$viewlink."','".$requestPost['seq']."','".$manager['viewtype']."');";
					}else{
						$callback = (!empty($requestPost['returnurl'])) ?"parent.document.location.href='".$requestPost['returnurl']."';":"parent.document.location.href='".$this->Boardmanager->realboardviewurl.BOARDID."&seq=".$requestPost['seq']."';";
					}

					// 비밀번호 확인 후 삭제 (등록한 브라우저가 아닌 경우)
					if ($this->input->post('modetype') === 'delete') {
						openDialogAlert(getAlert('et099'), 400, 140, 'parent', $callback);
						exit;
					} else {
						// 댓글 등록 후 동일 브라우저에서 바로 삭제 시
						//"정상적으로 삭제되었습니다."
						$return = ['result' => true, 'msg' => getAlert('et099'), 'callback' => $callback, 'comment_cnt' => $upboarddata['comment']];
						echo json_encode($return);
						exit;
					}

				}else{
					//alert("댓글 삭제가 실패 되었습니다.");
					//댓글 삭제가 실패 되었습니다.
					openDialogAlert(getAlert('et100'),400,140,'parent',$callback);
				}
			}else{
				//비밀번호가 일치하지 않습니다.
				openDialogAlert(getAlert('et097'),400,140,'parent',$callback);
			}
			exit;
		}

		/* 비회원 > 비밀번호 확인 후 > 답글 삭제 */
		elseif($mode == 'board_comment_reply_delete_pwcheck') {
			$requestPost = $this->input->post();
			// xss 필터링에서 제외
			$requestPost['pw'] = $this->input->post('pw', false);

			$requestPost['seq'] = (int) $requestPost['seq'];
			$requestPost['cmtreplyseq'] = (int) $requestPost['cmtreplyseq'];
			$requestPost['delcmtseq'] = (int) $requestPost['delcmtseq'];

			if( BOARDID == 'goods_review' ){
				get_auth($manager, '', 'write_cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_write_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif(BOARDID == 'notice' ) {//공지게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}else{
				get_auth($manager, '', 'write' , $isperm);//접근권한체크
				if ( $isperm['isperm_write'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}

			if (empty($requestPost['delcmtseq'])) {
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			if (empty($requestPost['cmtreplyseq'])) {
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			$parentsql['whereis'] = '  and seq= "' . $requestPost['cmtreplyseq'] . '"  and cmtparent= "' . $requestPost['delcmtseq'] . '" ';
			$parentsql['select']		= ' pw, seq, depth, parent, display, mseq  ';
			$parentdata = $this->Boardcomment->get_data($parentsql);//댓글정보
			if(!isset($parentdata['seq'])) {
				//존재하지 않는 댓글입니다.
				openDialogAlert(getAlert('et078'),400,140,'parent',$callback);
				exit;
			}

			if( $parentdata['mseq'] < 0 ) {//관리자권한
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			if ( $parentdata['mseq'] > 0 && $parentdata['mseq'] != $this->userInfo['member_seq'] ) {//본인글체크
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			$parentdata['isperm_moddel'] =  false;
			if ( defined('__ISUSER__') === true && $parentdata['mseq']  == $this->userInfo['member_seq']) {
				$parentdata['isperm_moddel'] =  true;
			}else{
				$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$parentdata['parent'];
				$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
				if ( strstr($boardpwwritess,'['.$parentdata['seq'].']') && !empty($boardpwwritess)) {
					$parentdata['isperm_moddel'] = true;//비회원 > 접근권한있음
				}else{
					if(empty($requestPost['pw'])) {
						//잘못된 접근입니다.
						openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
						exit;
					}
				}
			}

			if (
				$parentdata['isperm_moddel'] === true 
				|| (Password::isConfirm($requestPost['pw'], $parentdata['pw']) && $parentdata['isperm_moddel'] === false) 
			) {
				$replyor = 0;
				if($parentdata['depth'] == 0 ) {
					$replysc['whereis']	= ' and cmtparent = '.$parentdata['seq'].' and depth = 1 ';
					$replysc['select']		= " seq ";
					$replyor = $this->Boardcomment->get_data_numrow($replysc);//
				}

				if($replyor==0 || $parentdata['depth'] == 1 ) {//답글이 없거나 답글인 경우 real 삭제
					$result = $this->Boardcomment->data_delete($requestPost['cmtreplyseq']);
				}else{
					$params['display']			= '1';//삭제글여부1
					$params['subject']			= '';//초기화함
					$params['content']			= '';//초기화함
					$params['r_date']			= date("Y-m-d H:i:s");
					$result = $this->Boardcomment->data_delete_modify($params,$requestPost['cmtreplyseq']);
				}

				if($result) {
					//댓글평가제거
					$this->Boardscorelog->data_cparent_delete($parentdata['parent'],$requestPost['cmtreplyseq']);
					
					// 로직의 마지막 단계에 댓글 수를 Update하게 수정 :: rsh 2019-03-04
					// 댓글 수를 최신 상태로 갱신
					$upboarddata = $this->Boardmodel->comment_update($boarddata['seq']);

					if ($requestPost['viewtype'] == 'ajax' || strstr($requestPost['returnurl'], 'mode=ajax')) {
						$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
						$callback = "boardviewtype_m_only('" . $viewlink . "','" . $requestPost['p_seq'] . "','" . $manager['viewtype'] . "','','" . $upboarddata['comment'] . "');";
					} elseif ($manager['viewtype'] == 'layer' && !strstr($_SERVER['HTTP_REFERER'], '/board/view')) {
						$viewlink = (!empty($requestPost['returnurl'])) ? $requestPost['returnurl'] : $this->Boardmanager->realboardviewurl . BOARDID;
						$callback = "parent.boardviewtypeshow('" . $viewlink . "','" . $requestPost['seq'] . "','" . $manager['viewtype'] . "');";
					} else {
						$callback = (!empty($requestPost['returnurl'])) ? "parent.document.location.href='" . $requestPost['returnurl'] . "';" : "parent.document.location.href='" . $this->Boardmanager->realboardviewurl . BOARDID . '&seq=' . $requestPost['seq'] . "';";
					}
					openDialogAlert(getAlert('et099'),400,140,'parent',$callback);
					exit;
				}else{
					//alert("댓글 삭제가 실패 되었습니다.");
					//댓글 삭제가 실패 되었습니다.
					openDialogAlert(getAlert('et100'),400,140,'parent',$callback);
				}
			}else{
				//비밀번호가 일치하지 않습니다.
				openDialogAlert(getAlert('et097'),400,140,'parent',$callback);
			}
			exit;
		}

		/* 댓글 비밀글 > 비밀번호 체크 */
		elseif($mode == 'board_hidden_cmt_pwcheck') {
			$requestPost = $this->input->post();
			// xss 필터링에서 제외
			$requestPost['pw'] = $this->input->post('pw', false);


			$requestPost['cmtseq'] = (int) $requestPost['cmtseq'];

			if( BOARDID == 'goods_review' ){
				get_auth($manager, '', 'write_cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_write_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif(BOARDID == 'notice' ) {//공지게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}else{
				get_auth($manager, '', 'write' , $isperm);//접근권한체크
				if ( $isperm['isperm_write'] != true ) {
					//잘못된 접근입니다.
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}

			if(empty($requestPost['cmtseq'])) {
				//"잘못된 접근입니다."
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			if(empty($requestPost['pw'])) {
				//"잘못된 접근입니다."
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			$parentsql['whereis']	= ' and seq= "'.$requestPost['cmtseq'].'" ';
			$parentsql['select']		= ' pw, seq, depth, parent, display, content ';
			$parentdata = $this->Boardcomment->get_data($parentsql);//댓글정보
			if(!isset($parentdata['seq'])) {
				//존재하지 않는 댓글입니다.
				openDialogAlert(getAlert('et078'),400,140,'parent',$callback);
				exit;
			}
			if( $parentdata['display'] == 1 ) {
				//삭제된 댓글입니다.
				openDialogAlert(getAlert('et101'),400,140,'parent',$callback);
				exit;
			}

			if ( 
				(Password::isConfirm($requestPost['pw'], $parentdata['pw']) && $requestPost['view'] != 'comment_view') 
				|| ( 
					(Password::isConfirm($requestPost['pw'], $parentdata['pw']) || Password::isConfirm($requestPost['pw'], $boarddata['pw'])) 
					&& $requestPost['view'] == 'comment_view' 
				)
			) {

				// 비번입력후 브라우저를 닫기전까지는 접근가능함
				$ss_cmtpwhidden_name = 'cmthidden_view_' . BOARDID . '_' . $parentdata['parent'];
				$boardcmtpwhiddenss = $this->session->userdata($ss_cmtpwhidden_name);
				if ((!strstr($boardcmtpwhiddenss, '[' . $parentdata['seq'] . ']') && isset($boardcmtpwhiddenss)) || empty($boardcmtpwhiddenss)) {
					$boardcmtpwhiddenssadd = (isset($boardcmtpwhiddenss)) ? $boardcmtpwhiddenss . '[' . $parentdata['seq'] . ']' : '[' . $parentdata['seq'] . ']';
					$this->session->set_userdata($ss_cmtpwhidden_name, $boardcmtpwhiddenssadd);
				}
				if ($requestPost['view'] != 'comment_view') {
					$ss_cmtpwhidden_name = 'board_cmtpwhidden_' . BOARDID . '_' . $parentdata['parent'];
					$boardcmtpwhiddenss = $this->session->userdata($ss_cmtpwhidden_name);
					if ((!strstr($boardcmtpwhiddenss, '[' . $parentdata['seq'] . ']') && isset($boardcmtpwhiddenss)) || empty($boardcmtpwhiddenss)) {
						$boardcmtpwhiddenssadd = (isset($boardcmtpwhiddenss)) ? $boardcmtpwhiddenss . '[' . $parentdata['seq'] . ']' : '[' . $parentdata['seq'] . ']';
						$this->session->set_userdata($ss_cmtpwhidden_name, $boardcmtpwhiddenssadd);
					}
				}

				if ($requestPost['view'] == 'comment_view') {
					echo js("parent.dialogClose('CmtBoardPwCkNew');");
					echo js("parent.setReplyView('.boad_cmt_content_" . $parentdata['seq'] . "', '" . str_replace(chr(10), '', $parentdata['content']) . "');");
				} else {
					echo js("parent.dialogClose('CmtBoardPwCkNew');");
					echo js("parent.getModifyCmt('" . $parentdata['seq'] . "');");
				}

				exit;
			}else{
				//비밀번호가 일치하지 않습니다.
				openDialogAlert(getAlert('et097'), 400, 140, 'parent', $callback);
			}
			exit;

		}

		/* 답글 비밀글 > 비밀번호 체크 */
		elseif($mode == 'board_hidden_reply_cmt_pwcheck') {
			$requestPost = $this->input->post();			
			// xss 필터링에서 제외
			$requestPost['pw'] = $this->input->post('pw', false);

			$requestPost['cmtreplyseq'] = (int) $requestPost['cmtreplyseq'];
			$requestPost['cmtseq'] = (int) $requestPost['cmtseq'];

			if( BOARDID == 'goods_review' ){
				get_auth($manager, '', 'write_cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_write_cmt'] != true ) {
					//"잘못된 접근입니다."
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif(BOARDID == 'notice' ) {//공지게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//"잘못된 접근입니다."
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}elseif($manager['type'] == 'A') {//추가게시판인경우
				get_auth($manager, '', 'cmt' , $isperm);//접근권한체크
				if ( $isperm['isperm_cmt'] != true ) {
					//"잘못된 접근입니다."
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}else{
				get_auth($manager, '', 'write' , $isperm);//접근권한체크
				if ( $isperm['isperm_write'] != true ) {
					//"잘못된 접근입니다."
					openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
					exit;
				}
			}

			if(empty($requestPost['cmtreplyseq'])) {
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			if(empty($requestPost['cmtseq'])) {
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}
			if(empty($requestPost['pw'])) {
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et083'),400,140,'parent',$callback);
				exit;
			}

			$parentsql['whereis'] = ' and seq= "' . $requestPost['cmtreplyseq'] . '"  and cmtparent= "' . $requestPost['cmtseq'] . '" ';
			$parentsql['select']		= ' pw, seq, cmtparent,  depth, parent, display, content ';
			$parentdata = $this->Boardcomment->get_data($parentsql);//댓글정보
			if(!isset($parentdata['seq'])) {
				openDialogAlert(getAlert('et078'),400,140,'parent',$callback);
				exit;
			}

			if( $parentdata['display'] == 1 ) {
				//삭제된 댓글입니다.
				openDialogAlert(getAlert('et101'),400,140,'parent',$callback);
				exit;
			}

			if( $requestPost['view'] == 'comment_view' ) {
				$topparentsql['whereis']	= ' and seq= "'.$parentdata['cmtparent'].'" ';
				$topparentsql['select']		= '  pw, seq, cmtparent,  depth, parent, display, content  ';
				$topparentdata = $this->Boardcomment->get_data($topparentsql);//게시물정보//$requestPost['view'] comment_view
			}

			
			if (
				// 비번입력후 브라우저를 닫기전까지는 접근가능함				
				(Password::isConfirm($requestPost['pw'], $parentdata['pw']) && $requestPost['view'] != 'comment_view')
				|| (
						//원본글 이나 부모글 비밀번호가 동일한경우
						(
							Password::isConfirm($requestPost['pw'], $parentdata['pw']) 
							|| Password::isConfirm($requestPost['pw'], $topparentdata['pw']) 
							|| Password::isConfirm($requestPost['pw'], $boarddata['pw'])
						) 
						&& $requestPost['view'] == 'comment_view'
					) 
			) {
				$ss_cmtpwhidden_name = 'cmthidden_view_'.BOARDID.'_'.$parentdata['parent'];
				$boardcmtpwhiddenss = $this->session->userdata($ss_cmtpwhidden_name);

				if ( ( !strstr($boardcmtpwhiddenss,'['.$parentdata['seq'].']') && isset($boardcmtpwhiddenss)) || empty($boardcmtpwhiddenss)) {
					$boardcmtpwhiddenssadd = (isset($boardcmtpwhiddenss)) ? $boardcmtpwhiddenss.'['.$parentdata['seq'].']':'['.$parentdata['seq'].']';
					$this->session->set_userdata($ss_cmtpwhidden_name, $boardcmtpwhiddenssadd );
				}

				if( $requestPost['view'] != 'comment_view' ) {
					// 비번입력후 브라우저를 닫기전까지는 접근가능함
					$ss_cmtpwhidden_name = 'board_cmtpwhidden_'.BOARDID.'_'.$parentdata['parent'];
					$boardcmtpwhiddenss = $this->session->userdata($ss_cmtpwhidden_name);

					if ( ( !strstr($boardcmtpwhiddenss,'['.$parentdata['seq'].']') && isset($boardcmtpwhiddenss)) || empty($boardcmtpwhiddenss)) {
						$boardcmtpwhiddenssadd = (isset($boardcmtpwhiddenss)) ? $boardcmtpwhiddenss.'['.$parentdata['seq'].']':'['.$parentdata['seq'].']';
						$this->session->set_userdata($ss_cmtpwhidden_name, $boardcmtpwhiddenssadd );
					}
				}
				if( $requestPost['view'] == 'comment_view' ) {
					echo js("parent.dialogClose('CmtBoardPwCkNew');");
					echo js("parent.setReplyView('.boad_cmt_reply_content_".$parentdata['seq']."', '".str_replace(chr(10),'',$parentdata['content'])."');");
				}else{
					echo js("parent.dialogClose('CmtBoardPwCkNew');");
					echo js("parent.getModifyReplyCmt('".$parentdata['cmtparent']."', '".$parentdata['seq']."', '".$requestPost['cmtreplyidx']."');");
				}
				exit;
			}else{
				//비밀번호가 일치하지 않습니다.
				openDialogAlert(getAlert('et097'),400,140,'parent',$callback);
			}
			exit;

		}

	}


	//링크필터링
	public function getcmthtml($data, $sc, $manager, $boarddata)
	{
		$html = '<div id="cview"><table  class="list_table_style wbox" style="width:100%;margin: 15px 0 5px" >
			<colgroup>
		<col />
		<col width="110" />
		<col width="130" />
		<col width="130"/>
		</colgroup>
		<thead >
		<tr>
			<th> </th>
			<th>작성자</th>
			<th>등록일</th>
			<th> </th>
		</tr>
		</thead>';
		$idx= 0;
		$html .= '<tbody>';
		foreach($data['result'] as $datarow){$idx++;
			//$datarow['number'] = $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;//번호

			$sc['orderby']			= 'seq';
			$sc['sort']					= 'desc';
			$sc['parent']				= (isset($datarow['parent']))?$datarow['parent']:'';
			$sc['cmtparent']		= (isset($datarow['seq']))?$datarow['seq']:'';
			$cmtreplyqry = $this->Boardcomment->data_list_reply($sc);//댓글목록
			$cmthtml = '';
			$replyidx = 0;
			foreach($cmtreplyqry['result'] as $cmtreply){$replyidx++;
				getminfo($manager, $cmtreply, $mdata, $boardname);//회원정보
				$cmtreply['name'] = $boardname;

				$cmtisperm_moddel = ( ($cmtreply['mseq'] == $this->userInfo['member_seq'] && defined('__ISUSER__') === true) )?'':'_no';//답글은 회원만

				$cmtreply['iconnew']	= ( date("Ymd",strtotime('+'.$manager['icon_new_day'].' day '.substr(str_replace("-","",$cmtreply['m_date']),0,8))) >= date("Ymd") ) ? ' <img src="'.$this->icon_new_img.'" title="new" > ' :'';


				$cmtreply['date']			= substr($cmtreply['m_date'],0,16);//등록일
				$cmtreply['content']		= ' <span  board_seq="'.$cmtreply['seq'].'"  board_id="'.$cmtreply['boardid'].'" class="cmtsubject subject" ><img src="'.$this->blank_img.'" title="blank" width="13" >  '.nl2br($cmtreply['content']).'</span>';

				$cmtreply['deletebtn'] = '<span class="small valign-middle hand"><img src="'.$this->cmt_reply_del_img.'" title="답글삭제" class="boad_cmt_delete_btn'.$cmtisperm_moddel.'"   board_cmt_seq="'.$cmtreply['seq'].'" ></span>';
				$cmthtml .= '<tr class="datalist list-row content " >';
				$cmthtml .= '<td  class="its-td cell left" >'.$cmtreply['content'].''.$cmtreply['iconnew'].'</td>';
				$cmthtml .= '<td  class="its-td cell left" >'.$cmtreply['name'].'</td>';
				$cmthtml .= '<td class="date cell right" >'.$cmtreply['date'].'</td>';
				$cmthtml .= '<td class="its-td cell right" >'.$cmtreply['deletebtn'].'</td>';
				$cmthtml .= '</tr>';
			}

			getminfo($manager, $datarow, $mdata, $boardname);//회원정보
			$datarow['name'] = $boardname;

			get_auth($manager, $datarow, 'write', $isperm);//접근권한체크
			$isperm_write	= (defined('__ISUSER__') === true)?'':'_no';//답글은 회원전용
			$isperm_moddel = ( $isperm['isperm_moddel'] === true)?'':'_no';// || ($datarow['mseq'] == $this->userInfo['member_seq'] && defined('__ISUSER__') === true)
			$replytitle = ($isperm_write == '_no') ? '로그인 후 이용해 주세요.':'';
			$replytitlereadonly = ($isperm_write == '_no') ? ' readonly="readonly" ':'';

			if($datarow['display'] == 1 ){//삭제시
				$datarow['iconnew']	= '';
				$datarow['subject']		= ' <span class="hand gray subject" >삭제되었습니다 ['.substr($datarow['r_date'],0,16).']</span>';

				$datarow['deletebtn'] = '<span class="btn small valign-middle"><input type="button" name="boad_cmt_delete_btn'.$isperm_moddel.'"   board_cmt_seq="'.$datarow['seq'].'" value="삭제" /></span>';

				$html .= '<tr  class="list-row"><td class="its-td" align="left"   colspan="4"><a href="javascript:toggleLayer(\''.$idx.'\')">'.$datarow['subject'].' '.$datarow['deletebtn'].'</td></tr>';

				$html .= '<tr  class="datalist " ><td class="content" align="left"  colspan="4">'.$cmthtml.'</td></tr>';//답글

			}else{
				$datarow['iconnew']	= ( date("Ymd",strtotime('+'.$manager['icon_new_day'].' day '.substr(str_replace("-","",$datarow['m_date']),0,8))) >= date("Ymd") ) ? ' <img src="'.$this->icon_new_img.'" title="new" > ' :'';

				$datarow['date']			= substr($datarow['m_date'],0,16);//등록일
				$datarow['subject']		= ' <span  board_seq="'.$datarow['seq'].'"  board_id="'.$datarow['boardid'].'" class="cmtsubject subject" >'.$datarow['subject'].'</span>';

				$datarow['modifybtn'] = '<span class="btn small valign-middle"><input type="button"  name="boad_cmt_modify_btn'.$isperm_moddel.'"  board_cmt_seq="'.$datarow['seq'].'"  value="수정" /></span>';
				$datarow['deletebtn'] = '<span class="btn small valign-middle"><input type="button" name="boad_cmt_delete_btn'.$isperm_moddel.'"   board_cmt_seq="'.$datarow['seq'].'" value="삭제" /></span>';
				$datarow['replaybtn'] =  '<span class="btn small valign-middle"><input type="button"  name="boad_cmt_online_btn"  board_cmt_seq="'.$datarow['seq'].'" board_cmt_idx="'.$idx.'"   value="답글" /></span>';
				$html .= '<tr class="datalist " >';
				$html .= '<td class="left cell">'.$datarow['subject'].''.$datarow['iconnew'].'</td>';
				$html .= '<td align="center"  class="cell">'.$datarow['name'].'</td>';
				$html .= '<td class="date cell" align="right">'.$datarow['date'].'</td>';
				$html .= '<td align="right" class="cell">'.$datarow['replaybtn'].' '.$datarow['modifybtn'].' '.$datarow['deletebtn'].'</td>';
				$html .= '</tr>';
				$html .= '<tr  class=" cmtcontent'.$idx.'" ><td class="cmtcontent left"  colspan="4">'.nl2br($datarow['content']).'</td></tr>';
				$html .= '<tr  class="datalist " ><td   colspan="4">'.$cmthtml.'</td></tr>';//답글
				$html .= '<tr  class="datalist hide cmtreplayform'.$idx.'"  ><td align="left"  colspan="4"><div class="wbox">
				<table>
				<tr>
				<td width="100%"><textarea name="content" id="oneline_comment'.$idx.'" title="" '.$replytitlereadonly.'>'.$replytitle.'</textarea></td>
				<td valign="bottom"><span class="btn large black"><button type="button" name="board_commentsend_reply'.$isperm_write.'" id="board_commentsend_reply'.$isperm_write.'" board_cmt_seq="'.$datarow['seq'].'" board_cmt_idx="'.$idx.'" user_name="'.$this->userInfo['user_name'].'" >답글등록</button></span> </td>
				</tr>
				</table> </div></td></tr>';
			}

			$loop[] = $datarow;
		}//foreach end
		if($idx==0){
			$html .= '<tr  class="list-row"><td class="subject" align="center" colspan="4">등록된 댓글이 없습니다.</td></tr>';
		}
		$html .= '</tbody></table></div>';
		return $html;
	}

 	//댓글평가하기
	public function board_score_save()
	{
		$_POST['cparent'] = (int) $_POST['cparent'];
		$_POST['parent'] = (int) $_POST['parent'];

		$sc['whereis']	= ' and id= "'.BOARDID.'" ';
		$sc['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		if (!isset($this->manager['id'])) {
			//존재하지 않는 게시판입니다
			$result = array('result'=>false, 'msg'=>getAlert('et076'));
			echo json_encode($result);
			exit;
		}

		// 로그인 체크
		$session_arr = ( $this->session->userdata('user') )?$this->session->userdata('user'):$_SESSION['user'];
		if(!$session_arr['member_seq']){
			//회원만 사용가능합니다.
			$result = array('result'=>false, 'msg'=>getAlert('et077'));
			echo json_encode($result);
			exit;
		}

		if( $this->manager['auth_cmt_recommend_use'] == 'Y' ) {

			$sc['whereis']	= ' and seq= "'.$_POST['cparent'].'" ';
			$sc['select']	= ' seq, pw, mseq, parent ';
			$cdata = $this->Boardcomment->get_data($sc);//댓글정보
			if(empty($cdata['seq'])) {
				//존재하지 않는 댓글입니다.
				$result = array('result'=>false, 'msg'=>getAlert('et078'));
				echo json_encode($result);
				exit;
			}

			if( BOARDID == 'goods_review' ||  BOARDID == 'notice' ){
				get_auth($this->manager, $cmtdatarow, 'write_cmt', $isperm);//접근권한체크
				$this->manager['isperm_write_cmt'] = ($isperm['isperm_write_cmt'] === true)?'':'_no';
			}elseif($this->manager['type'] == 'A') {//추가게시판인경우
				get_auth($this->manager, $cmtdatarow, 'reply', $isperm);//접근권한체크
				$this->manager['isperm_reply'] = ($isperm['isperm_reply'] === true)?'':'_no';
				get_auth($this->manager, $cmtdatarow, 'cmt', $isperm);//접근권한체크
				$this->manager['isperm_cmt'] = ($isperm['isperm_cmt'] === true)?'':'_no';
				$this->manager['isperm_write_cmt'] = $this->manager['isperm_cmt'];
			}else{
				$this->manager['isperm_write_cmt'] = $this->manager['isperm_write'];
			}
			if ( $isperm['isperm_write_cmt'] === false ) {
				//평가권한이 없습니다.
				$result = array('result'=>false, 'msg'=>getAlert('et079'));
				echo json_encode($result);
				exit;
			}
			$parentsql['whereis']	= ' and boardid= "'.$_POST['board_id'].'" ';
			$parentsql['whereis']	.= ' and type= "comment" ';
			$parentsql['whereis']	.= ' and parent= "'.$_POST['parent'].'" ';//게시글
			$parentsql['whereis']	.= ' and cparent= "'.$_POST['cparent'].'" ';//댓글
			$parentsql['whereis']	.= ' and mseq= "'.$this->userInfo['member_seq'].'" ';
			$getscore = $this->Boardscorelog->get_data($parentsql);

			if(!$getscore) {
				//recommend/none_rec/recommend1/recommend2/recommend3/recommend4/recommend5
				$scoreid=  $_POST['scoreid'];
				$result = $this->Boardcomment->board_score_update($cdata['seq'], $scoreid,' + ');
				 if( $result ) {
					$params['type']				= 'comment';
					$params['boardid']			= $_POST['board_id'];
					$params['scoreid']			= $scoreid;
					$params['parent']			= $_POST['parent'];
					$params['cparent']			= $_POST['cparent'];
					$params['mseq']			= $this->userInfo['member_seq'];
					$params['regist_date']	= date("Y-m-d H:i:s");
					$this->Boardscorelog->data_write($params);

					$sc['whereis']	= ' and seq= "'.$_POST['cparent'].'" ';
					$sc['select']	= ' seq, pw, mseq, parent ,'.$scoreid;
					$getscoredata = $this->Boardcomment->get_data($sc);//댓글정보

					//회원님의 평가가 반영되었습니다.
					 $msg = getAlert('et080');
				 }else{
					 //회원님의 평가가 실패되었습니다.
					 $msg = getAlert('et081');
				 }
			}else{
				//이미 평가하신 댓글입니다.
				 $msg = getAlert('et082');
			}
		}else{
			//잘못된 접근입니다.
			 $msg = getAlert('et083');
		}

		if( $result ) {
			$result = array('result'=>true, 'msg'=>$msg, 'scoreid'=>$getscoredata[$scoreid]);
		}else{
			$result = array('result'=>false, 'msg'=>$msg);
		}

		echo json_encode($result);
		exit;
	}

}

/* End of file board_comment_process.php */
/* Location: ./app/controllers/admin/board_comment_process.php */