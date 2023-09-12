<?php
/**
 * 게시글 관련 관리자 process
 * @author gabia
 * @since version 1.0 - 2012.06.29
 */
 if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

use App\libraries\Password;

class Board_process extends front_base {

	public function __construct() {
		parent::__construct();

		$this->load->model('ssl');
		$this->ssl->decode();

		$boardid = (!empty($_POST['board_id'])) ? $_POST['board_id']:$_GET['board_id'];
		secure_vulnerability('board', 'boardid', $boardid,array('parent','parent.submitck();'));
		define('BOARDID',$boardid);

		$this->load->library('validation');
		$this->load->library('Upload');
		$this->load->helper('download');
		$this->load->helper('board');//
		$this->load->model('videofiles');

		$this->load->model('Boardmanager');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
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

		$_POST['contents']	= chkIframeInBoardContents($_POST['contents']);
	}

	/* 기본 */
	public function index()
	{
		$sc['whereis']	= ' and id= "'.BOARDID.'" ';
		$sc['select']		= ' * ';

		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		if (!isset($this->manager['id'])) {
			//$callback = "parent.document.location.href='".$this->Boardmanager->managerurl."';";
			//존재하지 않는 게시판입니다.
			openDialogAlert(getAlert('et269'),400,140,'parent','parent.submitck();');
			exit;
		}
		boarduploaddir($this->manager);//폴더생성 및 스킨 복사

		$mode = (!empty($_POST['mode']))?$_POST['mode']:$_GET['mode'];
		/* 게시글등록 */
		if($mode == 'board_write') {
			$requestPost = $this->input->post();
			// 에디터 내용은 xss 필터링에서 제외
			$requestPost['contents'] = $this->input->post('contents', false);

			$requestPost['seq'] = (int) $requestPost['seq'];
			$requestPost['delseq'] = (int) $requestPost['delseq'];

			get_auth($this->manager, '', 'write' , $isperm);//접근권한체크
			if ( $isperm['isperm_write'] === false ) {
				$callback = "parent.submitck();parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';";
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			// 상점리뷰 게시판시 필수값 조사 및 데이터 조합
			if( BOARDID == 'store_review' ){
				if(!$requestPost['seq'])		$requestPost['seq']		= $requestPost['delseq'];
				if(!$requestPost['contents'])	$requestPost['contents']	= $requestPost['reply_contents_'.$requestPost['delseq']];
				if(!$requestPost['name'])		$requestPost['name']		= $requestPost['name_'.$requestPost['delseq']];
				if(!$requestPost['pw'])		$requestPost['pw']		= $requestPost['pw_'.$requestPost['delseq']];
				//비밀번호
				if(!$this->userInfo['member_seq'])	$this->validation->set_rules('pw', getAlert('et271'),'trim|required|xss_clean');
			}

			// 예약게시판시 필수값 조사 및 데이터 조합
			if( BOARDID == 'store_reservation') {
				if(!$requestPost['seq'])		$requestPost['seq']		= $requestPost['delseq'];
				if(!$requestPost['contents'])	$requestPost['contents']	= $requestPost['reply_contents_'.$requestPost['delseq']];
				if(!$requestPost['name'])		$requestPost['name']		= $requestPost['name_'.$requestPost['delseq']];
				if(!$requestPost['pw'])		$requestPost['pw']		= $requestPost['pw_'.$requestPost['delseq']];

				//연락처
				$this->validation->set_rules('phone_num2', getAlert('et272'),'trim|required|xss_clean');
				$this->validation->set_rules('phone_num3', getAlert('et272'),'trim|required|xss_clean');
				//예약날짜
				$this->validation->set_rules('reserve_date', getAlert('et273'),'trim|required|xss_clean');
				//비밀번호
				if(!$this->userInfo['member_seq'])	$this->validation->set_rules('pw', getAlert('et271'),'trim|required|xss_clean');

				$tmp_date_arr = explode('-', $requestPost['reserve_date']);
				if(!checkdate($tmp_date_arr[1], $tmp_date_arr[2], $tmp_date_arr[0])) {
					//예약 날짜 형식이 올바르지 않습니다.
					openDialogAlert(getAlert('et274'),400,140,'parent','parent.submitck();');
					exit;
				}

				$requestPost['tel1'] = $requestPost['phone_num1'] . "-" . $requestPost['phone_num2'] . "-" . $requestPost['phone_num3'];
				$requestPost['reserve_date'] = $requestPost['reserve_date'] . " " . str_pad($requestPost['reserve_time_h'], 2, "0", STR_PAD_LEFT) . ":" . str_pad($requestPost['reserve_time_m'], 2, "0", STR_PAD_LEFT);
			}

			if( $requestPost['name'] == '작성자를 입력해 주세요' ) {
				$requestPost['name'] = '';
			}

			if( strstr($requestPost['pw'],'비밀번호를 입력해 주세요') ) {
				$requestPost['pw'] = '';
				if (!defined('__ISUSER__'))
				{
					//비밀번호를 정확히 입력해 주세요.
					openDialogAlert(getAlert('et275'),400,140,'parent','parent.submitck();');
					exit;
				}
			}


			if( $requestPost['subject'] == '제목을 입력해 주세요' ) {
				$requestPost['subject'] = '';
			}

			if( strtolower($requestPost['contents']) == "<p>&nbsp;</p>" || strtolower($requestPost['contents']) == "<p><br></p>"  ) $requestPost['contents']='';

			/* Emoji(4byte unicode) 처리 - Added by JP 2015-07-06
			   이모티콘을 직접저장(스마트폰에서는 보이게하기 위해서) 하기위해서는 테이블 케릭터넷을 4byte unicode 로 바꿔야함.
			*/
		    $requestPost['contents']	= preg_replace('/[\x{1F600}-\x{1F64F}]/u', '?', $requestPost['contents']);

			//이름
			$this->validation->set_rules('name', getAlert('et276'),'trim|required|xss_clean');
			//제목
			$this->validation->set_rules('subject', getAlert('et277'),'trim|required|xss_clean');
			//내용
			$this->validation->set_rules('contents', getAlert('et278'),'trim|required|xss_clean[board]');

			if( BOARDID == 'bulkorder' || BOARDID == 'goods_review' ) {
				$label_pr = $requestPost['label'];
				$label_sub_pr = $requestPost['labelsub'];
				$label_required = $requestPost['required'];

				### //넘어온 추가항목 seq
				foreach($label_pr as $l => $data){$label_arr[]=$l;}
				//추가항목 공백체크
				foreach($label_required as $v){
					if(!in_array($v,$label_arr)){
						$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();parent.submitck();";
						//체크된 항목은 필수항목입니다.
						openDialogAlert(getAlert('et279'),400,140,'parent',$callback);
						exit;
					}else{
						$query = $this->db->get_where('fm_boardform',array('bulkorderform_seq'=> $v,'boardid'=>BOARDID));
						$form_result = $query -> row_array();
						$label_title = $form_result['label_title'];
						$this->validation->set_rules('label['.$v.'][value][]', $label_title,'trim|required|xss_clean');
					}
				}
				###
			}

			if($this->validation->exec()===false){
			   $err = $this->validation->error_array;
			   $callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();parent.submitck();";
			   openDialogAlert($err['value'],400,140,'parent',$callback);
			   exit;
			}

			// 스팸방지 코드 체크 board_helper 2020-01-13 by hyem
			boardCaptchValidation();

			$params['boardid']		=  BOARDID;
			$params['notice']			=  if_empty($requestPost, 'notice', '0');//공지
			if( $requestPost['notice']){
				$params['onlynotice']			= ($requestPost['onlynotice'])?$requestPost['onlynotice']:0;//공지영역만 노출여부
			}else{
				$params['onlynotice']			=	0;//공지영역만 노출여부
			}

			if( $this->manager['secret_use'] == "A" ) {//무조건비밀글
				$params['hidden']		= 1;//비밀글
			}else{
				$params['hidden']		= if_empty($requestPost, 'hidden', '0');//비밀글
			}

			$params['subject']		=  $requestPost['subject'];
			$params['editor']			=  ($requestPost['daumedit'])?1:0;//모바일
			$params['name']			=  if_empty($requestPost, 'name', '');
			$params['category']		=  (!empty($requestPost['category']))?htmlspecialchars($requestPost['category']):'';
			$params['contents']		=  $requestPost['contents'];

			$pw = (!empty($requestPost['pw'])) ? Password::encrypt($requestPost['pw']) : '';
			$params['pw']				=  (!empty($requestPost['oldpw']))?($requestPost['oldpw']):$pw;

			$params['email']			=  (!empty($requestPost['email']))?($requestPost['email']):'';
			$params['tel1']				=  (!empty($requestPost['tel1']))?($requestPost['tel1']):'';
			$params['tel2']				=  (!empty($requestPost['tel2']))?($requestPost['tel2']):'';

			$params['rsms']			=  if_empty($requestPost, 'board_sms', 'N');//수신여부
			$params['remail']			=  if_empty($requestPost, 'board_email', 'N');//수신여부

			$params['score_avg']	=  $requestPost['score_avg'];

			if( !empty($requestPost['score']) ) $params['score']  = ($requestPost['score']);//값이 잇는경우에만 변경

			// 비밀번호 유효성 체크
			$pre_enc_password = '';
			$enc_password = '';

			$check_password = $requestPost['pw'];
			$password_params = array(
				'birthday'                => '',
				'phone'                   => '',
				'cellphone'                   => '',
				'pre_enc_password'        => $pre_enc_password,
				'enc_password'            => $enc_password,
			);
			$this->load->library('memberlibrary');
			$result = $this->memberlibrary->check_password_validation($check_password, $password_params);
			if($result['code'] != '00' && $result['alert_code']){
				$callback = 'parent.submitck();';
				openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback);
				exit;
			}

			//상품문의/후기
			if($requestPost['displayGoods']) {//상품관련
				$this->load->model('goodsmodel');
				foreach($requestPost['displayGoods'] as $displayGoods){
					$goods = $this->goodsmodel->get_goods($displayGoods);
					$providerseq[] = $goods['provider_seq'];
					$displayGoodsar[] = (int) $displayGoods;
				}
				$params['provider_seq']				=  (isset($providerseq) && is_array($providerseq))?implode(",",$providerseq):'';
			}else{
				if( BOARDID == 'bulkorder' ) {//대량구매게시판
					$params['provider_seq'] = $this->userInfo['member_seq'];
				}
			}
			$params['goods_seq']				=  (isset($displayGoodsar) && is_array($displayGoodsar))?implode(",",$displayGoodsar):'';

			$params['goods_cont']				=  (isset($requestPost['displayGoods_cont']) && is_array($requestPost['displayGoods_cont']))?implode("^|^",$requestPost['displayGoods_cont']):'';

			//회원정보
			if( defined('__ISUSER__') === true ) {
				$this->load->model('membermodel');
				$this->minfo = $this->membermodel->get_member_data($this->userInfo['member_seq']);
				$params['mseq']		= $this->userInfo['member_seq'];
				$params['mid']			= $this->userInfo['userid'];
				if (!$requestPost['seq']) $params['pw']			= $this->minfo['password'];//답변이 아닌경우에만 본인의 비밀글로 처리됨
			}

			if (!empty($requestPost['seq']) ) {//답변시

				$parentsql['whereis']	= ' and seq= "'.$requestPost['seq'].'" ';
				//본래게시글 추출@2017-05-12
				if( !(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder') ) {
					$parentsql['whereis']	.= ' and boardid= "'.BOARDID.'" ';
				}
				$parentsql['select']		= '  seq, gid, comment, upload, depth, display ';
				$parentdata = $this->Boardmodel->get_data($parentsql);
				if(empty($parentdata)) {
					$callback = "parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';parent.submitck();";
					//존재하지 않는 게시물입니다.
					openDialogAlert(getAlert('et281'),400,140,'parent',$callback);
					exit;
				}

				$parentsql['whereis']	= ' and gid >= '.$parentdata['gid'].' and gid < '.(intval($parentdata['gid'])+1);
				//$parentsql['select']		= ' gid ';
				$parentrumrow = $this->Boardindex->get_data_numrow($parentsql);
				if($parentrumrow>98) {
					//죄송합니다. 더이상 답글을 달 수 없습니다.
					openDialogAlert(getAlert('et282'),400,140,'parent','parent.submitck();');
					exit;
				}

				//답변 권한 체크
				get_auth($this->manager, $parentdata, 'reply' , $isperm);//접근권한체크
				if ( $isperm['isperm_reply'] === false && $parentdata['display'] == 0 ) {
					$callback = "parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';parent.submitck();";
					openDialogAlert(getAlert('et270'),400,140,'parent',$callback);//"접근권한이 없습니다!"
					exit;
				}

				$gidup['set']				= ' gid=gid+0.01 ';
				$gidup['whereis']		= ' gid > '.$parentdata['gid'].' and gid < '.(intval($parentdata['gid'])+1);
				$this->Boardmodel->data_gid_save($gidup);//data gid update
				$this->Boardindex->data_gid_save($gidup);//idx gid update

				$params['parent']	= $requestPost['seq'];
				$params['gid']			= $parentdata['gid']+0.01;
				$params['depth']		= $parentdata['depth']+1;


				if( defined('__ISUSER__') === true ) {
					$params['re_mseq'] = $this->userInfo['member_seq'];
					$params['re_mtype'] = 'u';
				} else {
					$params['re_mtype'] = 'o';
				}

			}else{//새글
				$minsql['whereis']	= ' ';
				$minsql['select']		= ' min(gid) as mingid ';
				$mindata = $this->Boardmodel->get_data($minsql);
				$parentgid = $mindata['mingid'] ? $mindata['mingid']-1 : 100000000.00;
				$params['parent']	= 0;
				$params['gid']			= $parentgid;
				$params['depth']		= 0;

				if( defined('__ISUSER__') === true ) {
					$params['mtype'] = 'u';
				} else {
					$params['mtype'] = 'o';
				}
			}

			$params['r_date']		= date("Y-m-d H:i:s");
			$params['m_date']		= date("Y-m-d H:i:s");
			$params["ip"]				= $this->input->ip_address();
			$params["agent"]		= $_SERVER['HTTP_USER_AGENT'];

			$_REQUEST['tx_attach_files'] = (!empty($requestPost['tx_attach_files'])) ? $requestPost['tx_attach_files']:'';
			$params['contents'] = adjustEditorImages($params['contents'], $this->Boardmodel->upload_src);// /data/tmp 임시폴더변경 /data/editor

			//board_mobile_file($parentdata, $realfilename, $incimage);//첨부파일처리
			//#####################################################################
			// lgs image upload 2019-05-07

			$aIncimage		= array();
			$aRemoveImg		= array();
			$is_file_upload	= false;

			$sRemoveImg		= $this->input->post('remove_img');
			$sIncimage		= $this->input->post('incimage');
			$sRealfilename	= $this->input->post('realfilename');
			
			// 파일명에 상대경로가 들어가면 안된다
			if (strpos(stripslashes($sRealfilename), '../') > -1) {
				// 올바른 파일이 아닙니다.
				openDialogAlert(getAlert('et001'), 400, 140, 'parent', 'parent.submitck();');
				exit;
			}

			if( $_POST['realfilename'] ) $is_file_upload = true;
			if($is_file_upload) {
				if( $sRemoveImg )	$aRemoveImg	= explode(",", $sRemoveImg);
				if( $sIncimage )	$aIncimage	= explode(",", $sIncimage);
				$aRealfilename = explode(",", $sRealfilename);

				foreach($aRealfilename as $k => $sFilename) {
					$delChk = false;
					foreach( $aRemoveImg as $sDelFilename){
						if( preg_match('/' . $sDelFilename . '/', $sFilename ) ){
							$delChk = true;
						}
					}
					if( !$delChk ){
						$realfilename[]	= $sFilename;
						$incimage[]		= base64_decode($aIncimage[$k]);
					}
				}
			}else{
				board_mobile_file($parentdata, $realfilename, $incimage);//첨부파일처리
			}

			//-->#####################################################################


			if(isset($realfilename)){
				$params['upload'] = @implode("|",$realfilename);
			}else{
				$params['upload'] = '';//초기화
			}

			if(  !$params['editor'] || ( $this->mobileMode || $this->storemobileMode ) || $this->_is_mobile_agent ){//모바일인경우 text
				if ( $requestPost['insert_image'] == 'top') {
					$params['contents'] = implode(" ",$incimage).'<br /><br />'.nl2br($params['contents']);
				}elseif ( $requestPost['insert_image'] == 'bottom') {
					$params['contents'] = nl2br($params['contents']).'<br /><br />'.implode(" ",$incimage);
						}else{
					$params['contents'] = nl2br($params['contents']);
						}
			}
			$params['insert_image'] =  if_empty($requestPost, 'insert_image', 'none');

			//신규분류
			if(!empty($requestPost['newcategory']) && $requestPost['category']=='newadd'){
				$params['category'] = htmlspecialchars($requestPost['newcategory']);
			}

			if( BOARDID == 'bulkorder' ) {
				$params['payment']				=  (!empty($requestPost['payment']))?($requestPost['payment']):'';
				$params['typereceipt']			=  (!empty($requestPost['typereceipt']))?($requestPost['typereceipt']):'';
				$params['total_price']			=  (!empty($requestPost['total_price']))?($requestPost['total_price']):'0';
				unset($addsetdata);
				### //추가정보 저장
				foreach ($label_pr as $k => $data) {
					foreach ($data['value'] as $j => $subdata) {
						if($k == '1' ){//
							$params['person_name']	 =  $subdata;
						}elseif($k == '2' ){//
							$params['person_email']	= $subdata;
							//$params['email']				=  $params['person_email'];
						}elseif($k == '3' ){//
							$params['person_tel1']	= $subdata;
						}elseif($k == '4' ){//
							$params['person_tel2']	= $subdata;
							//$params['tel1']				=  $params['person_tel2'];
						}elseif($k == '5' ){//
							$params['company']			= $subdata;
						}elseif($k == '6' ){//
							$params['shipping_date']	= $subdata;
						}
						$query = $this->db->get_where('fm_boardform',array('bulkorderform_seq'=> $k,'boardid'=>BOARDID));
						$form_result = $query -> row_array();
						$setdata = 'label_title='.$form_result['label_title'];
						$setdata .= '^^bulkorderform_seq='.$form_result['bulkorderform_seq'];
						$setdata .= '^^label_value='.$subdata;
						$setdata .= '^^label_sub_value='.$label_sub_pr[$k]['value'][$j];
						$addsetdata[] = $setdata;
					}
				}
				if($addsetdata) $params['adddata'] = @implode("|",$addsetdata);

			}elseif( BOARDID == 'goods_review' ) {//상품후기

				//평가정보
				unset($addsetdata);
				### //추가정보 저장
				foreach ($label_pr as $k => $data) {
					foreach ($data['value'] as $j => $subdata) {
						$query = $this->db->get_where('fm_boardform',array('bulkorderform_seq'=> $k,'boardid'=>BOARDID));
						$form_result = $query -> row_array();
						$setdata = 'label_title='.$form_result['label_title'];
						$setdata .= '^^bulkorderform_seq='.$form_result['bulkorderform_seq'];
						$setdata .= '^^label_value='.$subdata;
						$setdata .= '^^label_sub_value='.$label_sub_pr[$k]['value'][$j];
						$addsetdata[] = $setdata;
					}
				}
				if($addsetdata) $params['adddata'] = @implode("|",$addsetdata);

				//평가점수
				if( is_array($requestPost['reviewcategory']) ) {
					$scoresum =0;
					foreach($requestPost['reviewcategory'] as $reviewcategory){
						$scoresum+=$reviewcategory[0];
						$reviewcategoryar[] = $reviewcategory[0];
					}
					if($reviewcategoryar) $params['reviewcategory'] = @implode(",",$reviewcategoryar);
					$scorecnt		= count($requestPost['reviewcategory']);
						$params['score'] = round(($scoresum/$scorecnt));
						$params['score_avg'] =  round(($scoresum/($scorecnt*5))*100);//round(( ($scoresum*($scorecnt*5))/$scorecnt ));
				}

				if( !isset($requestPost['displayGoods']) ){
					//상품을 선택해 주세요.
					openDialogAlert(getAlert('et283'),400,160,'parent','parent.submitck();');
					exit;
				}

				$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
				$contents_tmp = str_replace('&nbsp;', ' ', $params['contents']);
				$cntlenth = mb_strlen(strip_tags($contents_tmp));

				//상품후기 구매자체크, 마일리지 지급시 구매자체크 : 상품 + 주문번호 체크
				if( (strstr($this->manager['auth_write'],'[onlybuyer]') ) ) {//( $reserves['autoemoney'] == 1 && $reserves['autoemoneytype'] != 3 )
					if( !$requestPost['ordergoodslist']){
						//는 구매자만 작성이 가능합니다.<br/>주문을 선택해 주세요.
						openDialogAlert($this->manager['name'].getAlert('et284'),400,160,'parent','parent.submitck();');
						exit;
					}
				}

				if( $reserves['autoemoney'] == 1 ) {//자동지급 사용시에만
					if($reserves['autoemoneytype'] == 2) {//조건2) 배송완료 구매자 + **자 후기등록
						 if( ($reserves['autoemoneystrcut1'] > 0 && $reserves['autoemoneystrcut1'] > $cntlenth)  || !$cntlenth) {
							$contentcutck = false;
							$contentcutlenth = $reserves['autoemoneystrcut1'];
						 }else{
							 $contentcutck = true;
						 }
					}elseif($reserves['autoemoneytype'] == 3) {//조건3) **자 후기등록
						 if( ($reserves['autoemoneystrcut2'] > 0 && $reserves['autoemoneystrcut2'] > $cntlenth) || !$cntlenth) {
							$contentcutck = false;
							$contentcutlenth = $reserves['autoemoneystrcut2'];
						 }else{
							 $contentcutck = true;
						 }
					}elseif($reserves['autoemoneytype'] == 1){//구매자인 회원만 마일리지지급가능
						$contentcutck = true;
					}

					if( defined('__ISUSER__') === true && $contentcutck != true) {//마일리지자동지급 : 회원만체크합니다.
						if(!$cntlenth){
							//를 입력해 주세요.
							openDialogAlert($this->manager['name'].getAlert('et285'),400,140,'parent','parent.submitck();');
							exit;
						}else{
							//마일리지가
							$review_str = getAlert('et286');
							if($reserves['autopoint_review'] > 0){
								//포인트가
								$review_str = getAlert('et287');
							}
							if($reserves['autoemoney_review'] > 0 && $reserves['autopoint_review'] > 0){
								//마일리지,포인트가
								$review_str = getAlert('et288');
							}

							if( $requestPost['review_reserve_ok'] != 'ok' ) {
								//openDialogAlert($this->manager['name']."를 ".$contentcutlenth."자 이상 입력해 주세요.",400,160,'parent','parent.submitck();');
								//"를 ".$contentcutlenth."자 이상 입력 시 ".$review_str." 지급됩니다.<br/>".$this->manager['name']."를 추가입력하시겠습니까?  "
								openDialogConfirm($this->manager['name'].getAlert('et289',array($contentcutlenth,$review_str,$this->manager['name'])),400,160,'parent','parent.submitck();parent.$(".review_reserve_ok").val("");parent.loadingStop("body",true);','parent.submitck();parent.chk_review_reserve();');
								exit;
							}else{
								//$contentcutck = false;
							}
						}
					}
				}
				$params['order_seq'] = (int) $requestPost['ordergoodslist'];
				$this->load->model('ordermodel');
				$orders				= $this->ordermodel->get_order($params['order_seq']);
				if ( $requestPost['ordergoodslist'] && !$orders['order_seq'] ) {
					$callback = "parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';";
					openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
					exit;
				}
			}

			$params['file_key_w']		= (!empty($requestPost['file_key_w']))?($requestPost['file_key_w']):'';//웹 인코딩 코드
			$params['file_key_i']		= (!empty($requestPost['file_key_i']))?($requestPost['file_key_i']):'';//스마트폰 인코딩 코드
			if($this->session->userdata('boardvideotmpcode') && $params['file_key_w'] )
				$params['videotmpcode'] = $this->session->userdata('boardvideotmpcode');//코드

			if( BOARDID == 'mbqna' ) {//1:1문의
				$params['order_seq'] = (int) $requestPost['ordergoodslist'];

				$this->load->model('ordermodel');
				$orders				= $this->ordermodel->get_order($params['order_seq']);
				if ( $requestPost['ordergoodslist'] && !$orders['order_seq'] ) {
					$callback = "parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';";
					openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
					exit;
				}
			}

			if( BOARDID == 'store_reservation' ) { //매장용 예약게시판 추가데이터
				$params['reserve_date'] = $requestPost['reserve_date'];
			}

			$result = $this->Boardmodel->data_write($params);

			// 게시글 생성&수정 후 실패나 성공여부에 상관 없이 작성요청 IP에 발생된 스팸방지 데이터를 초기화한다.
			$this->load->model('Captchamodel');
			$this->Captchamodel->data_delete(null,$this->input->ip_address());

			if($result) {
				$newseq = $result;

				//동영상관리
				if( $params['file_key_w'] ){
					$videofiles['file_key_w']			= $params['file_key_w'];
					$videofiles['parentseq']			= $newseq;
					$videofiles['type']						= BOARDID;
					$videofiles['upkind']					= 'board';
					$this->videofiles->videofiles_modify_key($videofiles);
				}

				if( BOARDID == 'goods_review' ) {//상품후기 등록시 자동마일리지지급
					goods_review_count($params, $newseq);
					$this->_goods_review_autoemoney($this->manager, $params, $cntlenth, $newseq);
				}elseif( BOARDID == 'goods_qna' ) {//상품문의 건수
					goods_qna_count($params, $newseq);
				}


				if(!empty($requestPost['newcategory']) && $requestPost['category']=='newadd'){
					$upmanagerparams['category']		= $this->manager['category'].",".$requestPost['newcategory'];
					$upmanagerparams['category']		= str_replace(",,",",",$upmanagerparams['category']);
					//카테고리추가하기
				}

				//게시글수save
				$upmanagerparams['totalnum']		= $this->manager['totalnum']+1;
				$result = $this->Boardmanager->manager_item_save($upmanagerparams,BOARDID);//게시글증가

				if( BOARDID == 'goods_review' && defined('__ISUSER__') === true) {
					$upsql = "update fm_member set review_cnt = review_cnt+1 where member_seq = '".$this->userInfo['member_seq']."'";
					$this->db->query($upsql);
				}

				if( BOARDID == 'goods_review' && isset($requestPost['displayGoods']) && is_array($requestPost['displayGoods']) ){
					/* 상품분석 수집 */
					$this->load->model('goodslog');
					foreach($requestPost['displayGoods'] as $goods_seq){
						$this->goodslog->add('review',$goods_seq);
					}
				}

				//공지 Boardindex
				$idxparams['hidden']	= $params['hidden'];//비밀글여부
				$idxparams['notice']		= $params['notice'];//공지여부
				$idxparams['gid']			= $params['gid'];//고유번호
				$idxparams['boardid']	= $params['boardid'];//id
				$this->Boardindex->idx_write($idxparams);

				if ($params['gid'] == '100000000.00')
				{
					$this->Boardmodel->get_data_optimize();
					$this->Boardindex->get_data_optimize();
				}

				//비회원이 등록 후 본문 확인가능함
				if( !defined('__ISUSER__') ) {// && $params['hidden'] == 1
					// 비번입력후 브라우저를 닫기전까지는 접근가능함
					$ss_pwhidden_name = 'board_pwhidden_'.BOARDID;
					$boardpwhiddenss = $this->session->userdata($ss_pwhidden_name);
					$boardpwhiddenssadd = (!empty($boardpwhiddenss)) ? $boardpwhiddenss.'['.$newseq.']':'['.$newseq.']';
					$this->session->set_userdata($ss_pwhidden_name, $boardpwhiddenssadd );
				}

				$this->session->unset_userdata('backtype');
				$requestPost['backtype'] = (!empty($requestPost['backtype']))?($requestPost['backtype']):'list';
				$this->session->set_userdata('backtype',$requestPost['backtype']);

				//if($_POST['tel1']) {//SMS발송 글등록시2013-04-24
					$this->manager['userid']			 = ($this->minfo['userid'])?$this->minfo['userid']:$params['name'];//비회원은 작성자명

					if($this->manager['id'] == 'goods_qna'){
						$this->load->model('goodsmodel');
						$goods			= $this->goodsmodel->get_goods($params['goods_seq']);
						$provider_seq	= $goods['provider_seq'];

						if($provider_seq > 1){
							$this->load->model('providermodel');
							$provider_info	= $this->providermodel->get_person($provider_seq);
							foreach((array)$provider_info as $row){
								if($row['gb'] == 'cs' && trim($row['mobile'])){
									$this->manager['provider_mobile']	= $row['mobile'];
									break;
								}
							}
						}
					}

					$this->manager['board_name'] = $this->manager['name'];
					$this->manager['user_name']	 = ($this->minfo['userid'])?$this->minfo['user_name']:$params['name'];//작성자명
					$commonSmsData[BOARDID."_write"]['phone'][] = $requestPost['tel1'];
					$commonSmsData[BOARDID."_write"]['mid'][] = $this->minfo['userid'];
					$commonSmsData[BOARDID."_write"]['params'][] = $this->manager;

					if(count($commonSmsData) > 0){
						commonSendSMS($commonSmsData);
					}


					// 관리자 푸시발송 2018-01-02 jhr
					if	( BOARDID == 'mbqna' ) {
						push_for_admin(array(
							'kind'		=> 'mbqna',
							'unique'	=> $newseq,
							'userid'	=> $this->manager['user_name']
						));
					}
					if	( BOARDID == 'goods_qna' ) {
						// 상품이 있을 때에는 각 입점사에게 push 발송되고, 없으면 본사에게 발송되도록 수정 2019-06-17 by hyem
						if( $goods['goods_seq'] ) {
							push_for_admin(array(
								'kind'			=> 'goods_qna',
								'unique'		=> $newseq,
								'goods_name'	=> $goods['goods_name'],
								'provider_list'	=> array($provider_seq)
							));
						} else {
							push_for_admin(array(
								'kind'			=> 'goods_qna',
								'unique'		=> $newseq,
								'msg'			=> '상품문의가 접수되었습니다.'
							));
						}
					}
				//}

				$parent = 'parent';
				$closepopup = 'parent.submitck();';
				if($requestPost['backtype'] == 'list') {
					$callback = ($requestPost['returnurl'])?$parent.".document.location.replace('".$requestPost['returnurl']."');":$parent.".document.location.replace('".$this->Boardmanager->realboarduserurl.BOARDID."');";
				} elseif($requestPost['backtype'] == 'view') {
					$callback = ($requestPost['returnurl'] && empty($requestPost['seq']))?$parent.".document.location.replace('".$requestPost['returnurl'].$newseq."');":$parent.".document.location.replace('".$this->Boardmanager->realboardviewurl.BOARDID."&seq=".$newseq."');";
				} else {
					$callback = '';
				}
				if( $requestPost['mygdreview'] == 'mygdreview' ) {
					//상품평 등록 및 포인트 지급
					$callback = "<script>parent.openDialog(getAlert('et290'),'writefinishlay',{'width':'430','height':'230'});</script>";
					echo $callback;
					exit;

				}else{

					if( BOARDID == 'goods_review' ) {//상품후기 등록시 자동마일리지지급

						if( $this->arrSns['facebook_review'] == 'Y' && ($this->arrSns['key_f'] != '455616624457601' && $this->arrSns['facebook_publish_actions']) ) {//@2015-04-22 facebook version 2.* 권한 제한으로 publish_actions 값이 있을 때에만 적용
							if( $this->__APP_DOMAIN__ == $_SERVER['HTTP_HOST'] ) {
									$callback .= $parent.".getfbopengraph('{$newseq}', 'write', '{$_SERVER[HTTP_HOST]}','".BOARDID."');";
							}else{//전용앱이거나 앱적용도메인과 현재 접근도메인이 다른경우
								$callback .= $parent.".getfbopengraph('{$newseq}', 'write', '{$this->config_system[subDomain]}','".BOARDID."');";
							}
						}

						// 통계데이터(review) 전송 사용안함
						//echo "<script>parent.statistics_firstmall('review','".$goods_seq."','','".$params['score']."');</script>";
					}

					if ($requestPost['iframe'])
						$callback	= str_replace(BOARDID, BOARDID.'&iframe='.$requestPost['iframe'], $callback);

					// 게시글 중복 submit 방지
					echo "<script type='text/javascript'>parent.loadingStop('.board_detail_btns2')</script>";

					if ($requestPost['calllink'] == 'mypage')
						$callback	= "parent.document.location.replace('/mypage/myreserve_catalog');";
					//게시글을 등록 하였습니다.
					openDialogAlert(getAlert('et291'),400,140,'parent',$callback.$closepopup);
				}
			}else{
				//게시글 등록에 실패되었습니다.
				openDialogAlert(getAlert('et292'),400,140,'parent','parent.submitck();');
			}
		}

		/* 게시글수정 */
		elseif($mode == 'board_modify') {
			$requestPost = $this->input->post();
			// 에디터 내용은 xss 필터링에서 제외
			$requestPost['contents'] = $this->input->post('contents', false);


			$requestPost['seq']				= (int) $requestPost['seq'];
			$requestPost['delseq']			= (int) $requestPost['delseq'];

			get_auth($this->manager, '', 'write' , $isperm);//접근권한체크
			if ( $isperm['isperm_write'] === false ) {
				$callback = "parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';parent.submitck();";
				if	($requestPost['iframe'])	$callback	= str_replace(BOARDID, BOARDID.'&iframe='.$requestPost['iframe'], $callback);
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			$sc['whereis']		= ' and seq= "'.$requestPost['seq'].'" ';

			// 작성자 세션 확인 :: 2017-08-16 lwh
			$user_session = $this->session->userdata();
			if($user_session['user']){
				$sc['whereis']	.= ' and mid= "'.$user_session['user']['userid'].'" ';
			}

			//본래게시글 추출@2017-05-12
			if( !(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder') ) {
				$sc['whereis']	.= ' and boardid= "'.BOARDID.'" ';
			}
			$sc['select'] = ' * ';
			$orgData = $this->Boardmodel->get_data($sc);//게시글보기

			// 데이터가 존재하지 않으면 접근 실패
			if (!$orgData) {
				// 잘못된 접근입니다.
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			// 회원이면서 자신이 작성한 글이 아닐 경우 접근 실패
			if (
				defined('__ISUSER__') === true
				&& $orgData['mseq'] > 0
				&& $orgData['mseq'] != $this->userInfo['member_seq']
			) {
				// 잘못된 접근입니다.
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			// 비회원이 작성한 글이 아닌데 비회원일 경우
			if (
				defined('__ISUSER__') === false
				&& $orgData['mseq'] > 0
			) {
				// 잘못된 접근입니다.
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}


			if( $requestPost['subject'] == '제목을 입력해 주세요' ) {
				$requestPost['subject'] = '';
			}

			if( strstr($requestPost['pw'],'비밀번호를 입력해 주세요') ) {
				$requestPost['pw'] = '';
				if (!defined('__ISUSER__'))
				{
					//비밀번호를 정확히 입력해 주세요.
					openDialogAlert(getAlert('et275'),400,140,'parent','parent.submitck();');
					exit;
				}
			}

			if( strtolower($requestPost['contents']) == "<p>&nbsp;</p>" || strtolower($requestPost['contents']) == "<p><br></p>"  ) $requestPost['contents']='';

			/* Emoji(4byte unicode) 처리 - Added by JP 2015-07-06
			   이모티콘을 직접저장(스마트폰에서는 보이게하기 위해서) 하기위해서는 테이블 케릭터넷을 4byte unicode 로 바꿔야함.
			*/
		    $requestPost['contents']	= preg_replace('/[\x{1F600}-\x{1F64F}]/u', '?', $requestPost['contents']);


			//제목
			$this->validation->set_rules('subject', getAlert('et277'),'trim|required|xss_clean');

			// 매장용 추가 검사
			if( BOARDID == 'store_review' || BOARDID == 'store_reservation' ){
				//수정내용
				$this->validation->set_rules('modify_contents_'.$requestPost['delseq'], getAlert('et293'),'trim|required');
				$requestPost['contents'] = $requestPost['modify_contents_'.$requestPost['delseq']];
				$requestPost['name'] = $requestPost['real_name'];
				$requestPost['seq'] = $requestPost['delseq'];
			}else{
			//내용
			$this->validation->set_rules('contents', getAlert('et278'),'trim|required|xss_clean[board]');
			}

			if( BOARDID == 'bulkorder' ||  BOARDID == 'goods_review' ) {
				$label_pr = $requestPost['label'];
				$label_sub_pr = $requestPost['labelsub'];
				$label_required = $requestPost['required'];

				### //넘어온 추가항목 seq
				foreach($label_pr as $l => $data){$label_arr[]=$l;}
				//추가항목 공백체크
				foreach($label_required as $v){
					if(!in_array($v,$label_arr)){
						$callback = "parent.submitck();if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
						//체크된 항목은 필수항목입니다.
						openDialogAlert(getAlert('et308'),400,140,'parent',$callback);
						exit;
					}else{
						$query = $this->db->get_where('fm_boardform',array('bulkorderform_seq'=> $v));
						$form_result = $query -> row_array();
						$label_title = $form_result['label_title'];
						$this->validation->set_rules('label['.$v.'][value][]', $label_title,'trim|required|xss_clean');
					}
				}
				###
			}


			if($this->validation->exec()===false){
			   $err = $this->validation->error_array;
			   $callback = "parent.submitck();if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";
			   openDialogAlert($err['value'],400,140,'parent',$callback);
			   exit;
			}

			// 비밀번호 유효성 체크
            $pre_enc_password = $requestPost['oldpw'];
            $enc_password = '';

            if ($pre_enc_password != $requestPost['pw']) {
                $check_password = $requestPost['pw'];
                $password_params = array(
                    'birthday'                => '',
                    'phone'                   => '',
                    'cellphone'                   => '',
                    'pre_enc_password'        => $pre_enc_password,
                    'enc_password'            => $enc_password,
                );
                $this->load->library('memberlibrary');
                $result = $this->memberlibrary->check_password_validation($check_password, $password_params);
                if($result['code'] != '00' && $result['alert_code']){
                    $callback = 'parent.submitck();';
                    openDialogAlert(getAlert($result['alert_code']),400,160,'parent',$callback);
                    exit;
                }
            }

			if( empty($requestPost['name']) && !defined('__ISUSER__')) {
				//이름을 입력해 주세요.
				alert(getAlert('et294'));
				exit;
			}

			// 스팸방지 코드 체크 board_helper 2020-01-13 by hyem
			boardCaptchValidation();

			$parentsql['whereis']	= ' and seq= "'.$requestPost['seq'].'" ';
			if( BOARDID == 'mbqna' || BOARDID == 'goods_qna' ) {
				$parentsql['select']		= ' seq, gid, comment, upload, depth, re_contents, file_key_w ';
			}else{
				$parentsql['select']		= ' seq, gid, comment, upload, depth, file_key_w  ';
			}

			$parentdata = $this->Boardmodel->get_data($parentsql);

			if(empty($parentdata)) {
				$callback = "parent.submitck();parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';";
				if($requestPost['iframe']){
					$callback = "parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID.'&iframe='.$requestPost['iframe']."';";
				}

				//존재하지 않는 게시물입니다.
				openDialogAlert(getAlert('et281'),400,140,'parent',$callback);
				exit;
			}

			if ( $parentdata['re_contents'] && ( BOARDID == 'mbqna' || BOARDID == 'goods_qna' ) ) {//답변상태수정불가
				//답변이 등록된 상태입니다.<br/>수정하실 수 없습니다.
				openDialogAlert(getAlert('et295'),400,140,'parent','parent.submitck();');
				exit;
			}

			$params['boardid']		=  BOARDID;

			if( $this->manager['secret_use'] == "A" ) {//무조건비밀글
				$params['hidden']		= 1;//비밀글
			}else{
				$params['hidden']		= if_empty($requestPost, 'hidden', '0');//비밀글
			}

			$params['subject']		=  $requestPost['subject'];
			$params['editor']			=  ($requestPost['daumedit'])?1:0;//모바일
			$params['name']			=  (!empty($requestPost['name']))?$requestPost['name']:'';
			$params['category']		= (!empty($requestPost['category']))?$requestPost['category']:'';

			if( BOARDID != 'store_review' && BOARDID != 'store_reservation' ){
				$pw								=  (!empty($requestPost['pw']))?Password::encrypt($requestPost['pw']):'';
				$params['pw']				=  (!empty($requestPost['oldpw']))?($requestPost['oldpw']):$pw;
				$params['tel1']				=  (!empty($requestPost['tel1']))?($requestPost['tel1']):'';
			}

			if( BOARDID == 'store_reservation' ) { //매장용 예약게시판 추가데이터
				$requestPost['tel1']			= $requestPost['phone_num1'] . "-" . $requestPost['phone_num2'] . "-" . $requestPost['phone_num3'];
				$requestPost['reserve_date']	= $requestPost['reserve_date'] . " " . str_pad($requestPost['reserve_time_h'], 2, "0", STR_PAD_LEFT) . ":" . str_pad($requestPost['reserve_time_m'], 2, "0", STR_PAD_LEFT);
			}

			$params['email']			=  (!empty($requestPost['email']))?($requestPost['email']):'';
			$params['tel2']				=  (!empty($requestPost['tel2']))?($requestPost['tel2']):'';

			$params['rsms']			=  if_empty($requestPost, 'board_sms', 'N');//수신여부
			$params['remail']			=  if_empty($requestPost, 'board_email', 'N');//수신여부

			if( !empty($requestPost['score']) ) $params['score']  = ($requestPost['score']);//값이 잇는경우에만 변경

			$params['m_date']		= date("Y-m-d H:i:s");
			$params["ip"]				= $this->input->ip_address();
			$params["agent"]		= $_SERVER['HTTP_USER_AGENT'];
			$_REQUEST['tx_attach_files'] = (!empty($requestPost['tx_attach_files'])) ? $requestPost['tx_attach_files']:'';

			//(/data/tmp 임시폴더에서 게시판폴더로 이동변경 $this->Boardmodel->upload_src
			$params['contents'] = adjustEditorImages($requestPost['contents'], $this->Boardmodel->upload_src);

			//board_mobile_file($parentdata, $realfilename, $incimage);//첨부파일처리
			//#####################################################################
			// lgs image upload 2019-05-07

			$aIncimage		= array();
			$aRemoveImg		= array();
			$is_file_upload	= false;

			$sRemoveImg		= $this->input->post('remove_img');
			$sIncimage		= $this->input->post('incimage');
			$sRealfilename	= $this->input->post('realfilename');			
			if( $requestPost['realfilename'] ) $is_file_upload = true;


			// 파일명에 상대경로가 들어가면 안된다
			if (strpos(stripslashes($sRealfilename), '../') > -1) {
				// 올바른 파일이 아닙니다.
				openDialogAlert(getAlert('et001'), 400, 140, 'parent', 'parent.submitck();');
				exit;
			}

			if( $_POST['realfilename'] ) $is_file_upload = true;
			if($is_file_upload) {
				if( $sRemoveImg )	$aRemoveImg	= explode(",", $sRemoveImg);
				if( $sIncimage )	$aIncimage	= explode(",", $sIncimage);
				$aRealfilename = explode(",", $sRealfilename);

				foreach($aRealfilename as $k => $sFilename) {
					$delChk = false;
					foreach( $aRemoveImg as $sDelFilename){
						if( preg_match('/' . $sDelFilename . '/', $sFilename ) ){
							$delChk = true;
						}
					}
					if( !$delChk ){
						$realfilename[]	= $sFilename;
						$incimage[]		= base64_decode($aIncimage[$k]);
					}
				}
				board_mobile_file($parentdata, $realfilename, $incimage);
			}else{
				board_mobile_file($parentdata, $realfilename, $incimage);//첨부파일처리
			}

			//-->#####################################################################

			if(isset($realfilename)){
				$params['upload'] = @implode("|",$realfilename);
			} else {
				$params['upload'] = '';//초기화
			}

			if(  !$params['editor'] || ( $this->mobileMode || $this->storemobileMode ) || $this->_is_mobile_agent ){//모바일인 순서바꾸지마세요.
				if ( $requestPost['insert_image'] == 'top') {
					$params['contents'] = @implode(" ",$incimage).'<br /><br />'.nl2br($params['contents']);
				}elseif ( $requestPost['insert_image'] == 'bottom') {
					$params['contents'] = nl2br($params['contents']).'<br /><br />'.@implode(" ",$incimage);
						}else{
					$params['contents'] = nl2br($params['contents']);
						}
								}
			$params['insert_image'] =  if_empty($requestPost, 'insert_image', 'none');

			//신규분류
			if(!empty($requestPost['newcategory']) && $requestPost['category']=='newadd'){
				$params['category'] = $requestPost['newcategory'];
			}

			if( BOARDID == 'bulkorder' ) {
				$params['payment']				=  (!empty($requestPost['payment']))?($requestPost['payment']):'';
				$params['typereceipt']			=  (!empty($requestPost['typereceipt']))?($requestPost['typereceipt']):'';
				$params['total_price']			=  (!empty($requestPost['total_price']))?($requestPost['total_price']):'0';
				unset($addsetdata);
				### //추가정보 저장
				foreach ($label_pr as $k => $data) {
					foreach ($data['value'] as $j => $subdata) {
						if($k == '1' ){//
							$params['person_name']	 =  $subdata;
						}elseif($k == '2' ){//
							$params['person_email']	= $subdata;
							//$params['email']				=  $params['person_email'];
						}elseif($k == '3' ){//
							$params['person_tel1']	= $subdata;
						}elseif($k == '4' ){//
							$params['person_tel2']	= $subdata;
							//$params['tel1']				=  $params['person_tel2'];
						}elseif($k == '5' ){//
							$params['company']			= $subdata;
						}elseif($k == '6' ){//
							$params['shipping_date']	= $subdata;
						}
						$query = $this->db->get_where('fm_boardform',array('bulkorderform_seq'=> $k,'boardid'=>BOARDID));
						$form_result = $query -> row_array();
						$setdata = 'label_title='.$form_result['label_title'];
						$setdata .= '^^bulkorderform_seq='.$form_result['bulkorderform_seq'];
						$setdata .= '^^label_value='.$subdata;
						$setdata .= '^^label_sub_value='.$label_sub_pr[$k]['value'][$j];
						$addsetdata[] = $setdata;
					}
				}
				if($addsetdata) $params['adddata'] = @implode("|",$addsetdata);

			}elseif( BOARDID == 'goods_review' ) {

				//평가정보
				unset($addsetdata);
				### //추가정보 저장
				foreach ($label_pr as $k => $data) {
					foreach ($data['value'] as $j => $subdata) {
						$query = $this->db->get_where('fm_boardform',array('bulkorderform_seq'=> $k,'boardid'=>BOARDID));
						$form_result = $query -> row_array();
						$setdata = 'label_title='.$form_result['label_title'];
						$setdata .= '^^bulkorderform_seq='.$form_result['bulkorderform_seq'];
						$setdata .= '^^label_value='.$subdata;
						$setdata .= '^^label_sub_value='.$label_sub_pr[$k]['value'][$j];
						$addsetdata[] = $setdata;
					}
				}
				if($addsetdata) $params['adddata'] = @implode("|",$addsetdata);
				//평가점수
				if( is_array($requestPost['reviewcategory']) ) {
					$scoresum =0;
					foreach($requestPost['reviewcategory'] as $reviewcategory){
						$scoresum+=$reviewcategory[0];
						$reviewcategoryar[] = $reviewcategory[0];
					}
					if($reviewcategoryar) $params['reviewcategory'] = @implode(",",$reviewcategoryar);
					$scorecnt		= count($requestPost['reviewcategory']);

					$params['score'] = round(($scoresum/$scorecnt));
					$params['score_avg'] =  round(($scoresum/($scorecnt*5))*100);//round(( ($scoresum*($scorecnt*5))/$scorecnt ));
				}
			}


			//동영상연동
			if($requestPost['video_del'] == 1) $params['file_key_w'] = '';//원본파일코드초기화
			if($requestPost['file_key_w']) $params['file_key_w'] = $requestPost['file_key_w'];//웹 인코딩 코드
			if($requestPost['file_key_i']) $params['file_key_i'] = $requestPost['file_key_i'];//스마트폰 인코딩 코드

			if($this->session->userdata('boardvideotmpcode') && $params['file_key_w'] )
				$params['videotmpcode'] = $this->session->userdata('boardvideotmpcode');//코드

			$boardidar	= @explode('^^', $this->session->userdata('tmpcode'));
			$params['tmpcode'] = ($boardidar[1])?$boardidar[1]:'';//첨부파일코드

			//상품문의/후기
			$params['goods_seq']				=  (isset($requestPost['displayGoods']) && is_array($requestPost['displayGoods']))?implode(",",$requestPost['displayGoods']):'';

			if($requestPost['displayGoods']) {//상품관련
				$this->load->model('goodsmodel');
				foreach($requestPost['displayGoods'] as $displayGoods){
					$goods = $this->goodsmodel->get_goods($displayGoods);
					$providerseq[] = $goods['provider_seq'];
				}
				$params['provider_seq']				=  (isset($providerseq) && is_array($providerseq))?implode(",",$providerseq):'';
			}else{
				if( BOARDID == 'bulkorder' ) {//대량구매게시판
					$params['provider_seq'] = $this->userInfo['member_seq'];
				}
			}



			if( BOARDID == 'mbqna' ) {//1:1문의
				$params['order_seq'] = $requestPost['order_seq'];
			}


			$params['goods_cont']				=  (isset($requestPost['displayGoods_cont']) && is_array($requestPost['displayGoods_cont']))?implode("^|^",$requestPost['displayGoods_cont']):'';

			$result = $this->Boardmodel->data_modify($params);

			// 게시글 생성&수정 후 실패나 성공여부에 상관 없이 작성요청 IP에 발생된 스팸방지 데이터를 초기화한다.
			$this->load->model('Captchamodel');
			$this->Captchamodel->data_delete(null,$this->input->ip_address());

			if($result) {

				//동영상관리
				if($requestPost['video_del'] == 1 && $parentdata['file_key_w']){//연결해제(삭제)
					$this->videofiles->videofiles_delete_key('board',BOARDID,$parentdata['file_key_w']);
				}
				if( $params['file_key_w'] ){
					$videofiles['file_key_w']			= $params['file_key_w'];
					$videofiles['parentseq']			= $requestPost['seq'];
					$videofiles['type']						= BOARDID;
					$videofiles['upkind']					= 'board';
					$this->videofiles->videofiles_modify_key($videofiles);
				}

				if( BOARDID == 'goods_review' ) {//상품후기 수정시 평점갱신
					if( !$this->goodsmodel ) $this->load->model('goodsmodel');
					$this->goodsmodel->goods_review_sum($params['goods_seq']);
				}

				if(!empty($requestPost['newcategory']) && $requestPost['category']=='newadd'){
					$upmanagerparams['category']		= $this->manager['category'].",".$requestPost['newcategory'];
					$upmanagerparams['category']		= str_replace(",,",",",$upmanagerparams['category']);
					$result = $this->Boardmanager->manager_item_save($upmanagerparams,BOARDID);//카테고리변경
					//카테고리추가하기
				}

				//공지 Boardindex
				$idxparams['hidden']	= $params['hidden'];//비밀글여부
				//$idxparams['notice']		= $params['notice'];//공지여부
				$idxparams['gid']			= $parentdata['gid'];//고유번호
				$idxparams['boardid']	= $params['boardid'];//id
				$this->Boardindex->idx_modify($idxparams);

				if (isset($requestPost['board_sms']) && isset($requestPost['board_sms_hand'])) {//답변시
					//SMS
				}

				if (isset($requestPost['board_email']) && isset($requestPost['board_sms_email']) ) {//답변시
					//Email
				}

				$this->session->unset_userdata('backtype');
				$requestPost['backtype'] = (!empty($requestPost['backtype']))?($requestPost['backtype']):'list';
				$this->session->set_userdata('backtype',$requestPost['backtype']);

				$parent = 'parent';
					$closepopup = 'parent.submitck();';

				if($requestPost['backtype'] == 'list') {
					$callback = (!empty($requestPost['returnurl'])) ?$parent.".document.location.href='".$requestPost['returnurl']."';":$parent.".document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';";
				}elseif($requestPost['backtype'] == 'view'){
					$callback = (!empty($requestPost['returnurl'])) ?$parent.".document.location.href='".$requestPost['returnurl']."';":$parent.".document.location.href='".$this->Boardmanager->realboardviewurl.BOARDID."&seq=".$requestPost['seq']."';";
				}else {
					$callback = '';
				}

				if	($requestPost['iframe'])	$callback	= str_replace(BOARDID, BOARDID.'&iframe='.$requestPost['iframe'], $callback);
				//게시글을 수정하였습니다.
				openDialogAlert(getAlert('et296'),400,140,'parent',$callback.$closepopup);
			}else{
				//게시글 수정이 실패 되었습니다.
				alert(getAlert('et297'));
			}
			exit;
		}

		/* 게시글삭제 */
		elseif ($mode === 'board_delete') {
			$requestPost = $this->input->post();
			
			$deleteSeq = (int) $requestPost['delseq'];
						
			//접근권한체크
			get_auth($this->manager, '', 'write' , $isperm);
			if ($isperm['isperm_write'] === false) {
				$callback = "parent.document.location.href='" . $this->Boardmanager->realboarduserurl . BOARDID . "';";
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et270'), 400, 140, 'parent', $callback);
				exit;
			}

			/**
			 * request값을 int 로 형 변환 과정에서 string 문자열은 사라지기 때문에 숫자만 남게 되지만
			 * 보안처리를 명시적으로 하기 위해서 숫자만 받는 로직을 추가한다
			 */
			if (is_numeric($deleteSeq) === false) {
				//존재하지 않는 게시물입니다.
				openDialogAlert(getAlert('et281'), 400, 140, 'parent', $callback);
				exit;
			}

			$num = 0;
			$parentsql['whereis'] = ' and seq= "' . $deleteSeq . '" ';
			// 작성자 세션 확인 :: 2017-08-16 lwh
			$user_session = $this->session->userdata();
			if ($user_session['user']) {
				$parentsql['whereis'] .= ' and mid= "' . $user_session['user']['userid'] . '" ';
			}
			//본래게시글 추출@2017-05-12
			if (!(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder')) {
				$parentsql['whereis'] .= ' and boardid= "' . BOARDID . '" ';
			}
			$parentsql['select'] = ' * ';

			$parentdata = $this->Boardmodel->get_data($parentsql);

			// 데이터가 존재하지 않으면 접근 실패
			if (!$parentdata) {
				// 잘못된 접근입니다.
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			// 회원이면서 자신이 작성한 글이 아닐 경우 접근 실패
			if (
				defined('__ISUSER__') === true
				&& $parentdata['mseq'] > 0
				&& $parentdata['mseq'] != $this->userInfo['member_seq']
			) {
				// 잘못된 접근입니다.
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			// 비회원이 작성한 글이 아닌데 비회원일 경우
			if (
				defined('__ISUSER__') === false
				&& $parentdata['mseq'] > 0
			) {
				// 잘못된 접근입니다.
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			$replyor = '';
			$replysc['whereis'] = ' and gid > '.$parentdata['gid'].' and gid < '.(intval($parentdata['gid'])+1).' and parent = '.($parentdata['seq']).' ';//답변여부
			$replyor = $this->Boardmodel->get_data_numrow($replysc);

			//게시글 삭제시 마일리지/포인트 회수 한번만!
			if( $parentdata['display'] != 1  && $parentdata['mseq'] ) {
				if( BOARDID == 'goods_review' ) {
					$this->_goods_review_less($this->manager, $parentdata);
				}else{
					$this->_board_less($this->manager, $parentdata);
				}
			}

						
			if ($replyor == 0 && $parentdata['comment'] == 0) {//답변과 댓글이 없는 경우 real 삭제
				$num++;
				$result = $this->Boardmodel->data_delete($deleteSeq); //게시글삭제
				if ($result) {
					$this->Boardindex->idx_delete($parentdata['gid']); //index 삭제

					//게시글평가제거
					$this->Boardscorelog->data_parent_delete($deleteSeq);

					//첨부파일삭제
					if (!empty($parentdata['upload'])) {
						$oldfile = @explode('|', $parentdata['upload']);
						for ($f = 0; $f < count($oldfile); $f++) {
							$oldrealfile = @explode('^^', $oldfile[$f]);
							if (@is_file($this->Boardmodel->upload_path . $oldrealfile[0])) {
								$realFileName = basename($oldrealfile[0]);
								@unlink($this->Boardmodel->upload_path . $realFileName); //기존위치의 파일삭제
								@unlink($this->Boardmodel->upload_path . '_thumb_' . $realFileName); //기존위치의 파일삭제
								@unlink($this->Boardmodel->upload_path . '_widget_thumb_' . $realFileName); //기존위치의 파일삭제
							}
						}
					}

					//게시글수 save
					$upmanagerparams['totalnum'] = $this->manager['totalnum'] - 1;
					$result = $this->Boardmanager->manager_item_save($upmanagerparams, BOARDID); //본래게시판의 게시글감소

					if (BOARDID == 'goods_review') {
						//상품정보 > 상품후기건수 차감
						if ($parentdata['goods_seq']) {
							$reviewparentdata['goods_seq'] = $parentdata['goods_seq'];
							goods_review_count($reviewparentdata, $parentdata['seq'], 'minus');
						}

						if ($parentdata['mseq']) {
							//회원정보체크
							$this->load->model('membermodel');
							$minfo = $this->membermodel->get_member_data($parentdata['mseq']);
							if ($minfo['review_cnt'] > 0) {
								$upsql = "update fm_member set review_cnt = review_cnt-1 where member_seq = '" . $parentdata['mseq'] . "'";
								$this->db->query($upsql);
							}
						}
					} elseif (BOARDID == 'goods_qna') {//상품문의 건수 차감
						if ($parentdata['goods_seq']) {
							$qnaparentdata['goods_seq'] = $parentdata['goods_seq'];
							goods_qna_count($qnaparentdata, $parentdata['seq'], 'minus');
						}
					}

					//$callback = "parent.document.location.reload();";
					$parent = 'parent';
					$callback = (!empty($_POST['returnurl'])) ? $parent . ".document.location.href='" . $_POST['returnurl'] . "';" : $parent . ".document.location.href='" . $this->Boardmanager->realboarduserurl . BOARDID . "';";
					//게시글을 삭제하였습니다.
					openDialogAlert(getAlert('et298'), 400, 140, 'parent', $callback);
				} else {
					//게시글 삭제가 실패 되었습니다.
					alert(getAlert('et299'));
				}
				exit;
			} else {
				$params['display'] = '1'; //삭제글여부1
				$params['subject'] = ''; //초기화함
				$params['contents'] = ''; //초기화함
				//$params['comment']		= '';//댓글수 초기화
				$params['upload'] = ''; //첨부파일 초기화
				$params['r_date'] = date('Y-m-d H:i:s');
				$result = $this->Boardmodel->data_delete_modify($params, $deleteSeq);
				if ($result) {
					//공지글삭제
					$idxparams['display'] = 1; //삭제여부
					$idxparams['notice'] = 0; //공지 해지
					$idxparams['gid'] = $parentdata['gid']; //고유번호
					$this->Boardindex->idx_delete_modify($idxparams);

					//첨부파일삭제
					if (!empty($parentdata['upload'])) {
						$oldfile = @explode('|', $parentdata['upload']);
						for ($i = 0; $i < count($oldfile); $i++) {
							$oldrealfile = @explode('^^', $oldfile[$i]);
							if (@is_file($this->Boardmodel->upload_path . $oldrealfile[0])) {//기존위치에 수정시 변경
								$realFileName = basename($oldrealfile[0]);
								@unlink($this->Boardmodel->upload_path . $realFileName); //기존위치의 파일삭제
								@unlink($this->Boardmodel->upload_path . '_thumb_' . $realFileName); //기존위치의 파일삭제
								@unlink($this->Boardmodel->upload_path . '_widget_thumb_' . $realFileName); //기존위치의 파일삭제
							}
						}
					}

					$parent = 'parent';
					$callback = (!empty($_POST['returnurl'])) ? $parent . ".document.location.href='" . $_POST['returnurl'] . "';" : $parent . ".document.location.href='" . $this->Boardmanager->realboarduserurl . BOARDID . "';";
					//게시글을 삭제하였습니다.
					openDialogAlert(getAlert('et298'), 400, 140, 'parent', $callback);
				} else {
					//게시글 삭제가 실패 되었습니다.
					alert(getAlert('et299'));
				}
				exit;
			}
		}


		/* 게시글삭제 */
		elseif ($mode === 'board_modifydelete_pwckeck') {
			$requestPost = $this->input->post();
			// xss 필터링에서 제외
			$requestPost['pw'] = $this->input->post('pw', false);


			$deleteSeq = (int) $requestPost['seq'];
			$boardPassword = $requestPost['pw'];
			$modeType = $requestPost['modetype'];

			get_auth($this->manager, '', 'write' , $isperm);//접근권한체크
			if ( $isperm['isperm_write'] === false ) {
				$callback = "parent.document.location.href='".$this->Boardmanager->realboarduserurl.BOARDID."';";
				//잘못된 접근입니다.
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			/**
			 * request값을 int 로 형 변환 과정에서 string 문자열은 사라지기 때문에 숫자만 남게 되지만
			 * 보안처리를 명시적으로 하기 위해서 숫자만 받는 로직을 추가한다
			 */
			if (is_numeric($deleteSeq) === false) {
				//존재하지 않는 게시물입니다.
				openDialogAlert(getAlert('et281'), 400, 140, 'parent', $callback);
				exit;
			}

			if (empty($deleteSeq)) {
				//잘못된 접근입니다.
				$return = ['result' => false, 'msg' => getAlert('et270')];
				echo json_encode($return);
				exit;
			}

			if (empty($boardPassword)) {
				//잘못된 접근입니다.
				$return = ['result' => false, 'msg' => getAlert('et270')];
				echo json_encode($return);
				exit;
			}

			$num = 0;
			$parentsql['whereis'] = ' and seq= "' . $deleteSeq . '" ';
			//본래게시글 추출@2017-05-12
			if (!(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder')) {
				$parentsql['whereis'] .= ' and boardid= "' . BOARDID . '" ';
			}
			$parentsql['select'] = ' * ';
			$parentdata = $this->Boardmodel->get_data($parentsql);
			if (empty($parentdata['seq'])) {
				//존재하지 않는 게시물입니다.
				$return = ['result' => false, 'msg' => getAlert('et281')];
				echo json_encode($return);
				exit;
			}
			
			if (Password::isConfirm($requestPost['pw'], $parentdata['pw']) === true) {//비밀번호가 맞는 경우

				if($requestPost['modetype'] == 'board_delete') {//비회원 > 게시글 삭제임...
					$replyor = '';
					$replysc['whereis'] = ' and gid > '.$parentdata['gid'].' and gid < '.(intval($parentdata['gid'])+1).' and parent = '.($parentdata['seq']).' ';//답변여부
					$replyor = $this->Boardmodel->get_data_numrow($replysc);

					//게시글 삭제시 마일리지/포인트 회수 한번만!
					if( $parentdata['display'] != 1  && $parentdata['mseq'] ) {
						if( BOARDID == 'goods_review' ) {
							$this->_goods_review_less($this->manager, $parentdata);
						}else{
							$this->_board_less($this->manager, $parentdata);
						}
					}

					if($replyor==0 && $parentdata['comment']==0) {//답변과 댓글이 없는 경우 real 삭제
						$num++;
						$result = $this->Boardmodel->data_delete($deleteSeq);//게시글삭제
						if($result) {
							$this->Boardindex->idx_delete($parentdata['gid']);//index 삭제

							//게시글평가제거
							$this->Boardscorelog->data_parent_delete($deleteSeq);

							//첨부파일삭제
							if(!empty($parentdata['upload'])){
								$oldfile = @explode("|",$parentdata['upload']);
								for ( $f=0;$f<count($oldfile);$f++) {
										$oldrealfile = @explode("^^",$oldfile[$f]);
									if(@is_file($this->Boardmodel->upload_path.$oldrealfile[0])){//기존위치에 수정시 변경
										$realFileName = basename($oldrealfile[0]);
										@unlink($this->Boardmodel->upload_path . $realFileName); //기존위치의 파일삭제
										@unlink($this->Boardmodel->upload_path . '_thumb_' . $realFileName); //기존위치의 파일삭제
										@unlink($this->Boardmodel->upload_path . '_widget_thumb_' . $realFileName); //기존위치의 파일삭제
									}
								}
							}

							//게시글수 save
							$upmanagerparams['totalnum']		= $this->manager['totalnum']-1;
							$result = $this->Boardmanager->manager_item_save($upmanagerparams,BOARDID);//본래게시판의 게시글감소

							if( BOARDID == 'goods_review' ) {
								//상품정보 > 상품후기건수 차감
								if( $parentdata['goods_seq'] ) {
									$reviewparentdata['goods_seq'] = $parentdata['goods_seq'];
									goods_review_count($reviewparentdata, $parentdata['seq'], 'minus');
								}

								if( $parentdata['mseq']) {
									//회원정보체크
									$this->load->model('membermodel');
									$minfo = $this->membermodel->get_member_data($parentdata['mseq']);
									if($minfo['review_cnt'] > 0 ){
										$upsql = "update fm_member set review_cnt = review_cnt-1 where member_seq = '".$parentdata['mseq']."'";
										$this->db->query($upsql);
									}
								}
							}elseif( BOARDID == 'goods_qna' ) {//상품문의 건수 차감
								if( $parentdata['goods_seq'] ) {
									$qnaparentdata['goods_seq'] = $parentdata['goods_seq'];
									goods_qna_count($qnaparentdata, $parentdata['seq'], 'minus');
								}
							}

							//정상적으로 삭제되었습니다.
							$return = array('result'=>true, 'msg'=>getAlert('et300'));
							echo json_encode($return);
							exit;
						}else{
							//게시글 삭제가 실패 되었습니다.
							$return = array('result'=>false, 'msg'=>getAlert('et299'));
							echo json_encode($return);
							exit;
						}
					}else{
						$params['display']			= '1';//삭제글여부1
						$params['subject']			= '';//초기화함
						$params['contents']			= '';//초기화함
						//$params['comment']		= '';//댓글수 초기화
						$params['upload']			= '';//첨부파일 초기화
						$params['r_date']			= date("Y-m-d H:i:s");
						$result = $this->Boardmodel->data_delete_modify($params, $deleteSeq);
						if($result) {

							//공지글삭제
							$idxparams['display']	= 1;//삭제여부
							$idxparams['notice']		= 0;//공지 해지
							$idxparams['gid']			= $parentdata['gid'];//고유번호
							$this->Boardindex->idx_delete_modify($idxparams);

							//첨부파일삭제
							if(!empty($parentdata['upload'])){
								$oldfile = @explode("|",$parentdata['upload']);
								for ( $i=0;$i<count($oldfile);$i++) {
									$oldrealfile = @explode("^^",$oldfile[$i]);
									if(@is_file($this->Boardmodel->upload_path.$oldrealfile[0])){//기존위치에 수정시 변경
										$realFileName = basename($oldrealfile[0]);
										@unlink($this->Boardmodel->upload_path . $realFileName); //기존위치의 파일삭제
										@unlink($this->Boardmodel->upload_path . '_thumb_' . $realFileName); //기존위치의 파일삭제
										@unlink($this->Boardmodel->upload_path . '_widget_thumb_' . $realFileName); //기존위치의 파일삭제
									}
								}
							}

							//정상적으로 삭제되었습니다.
							$return = array('result'=>true, 'msg'=>getAlert('et300'));
							echo json_encode($return);
							exit;
						}else{
							//게시글 삭제가 실패 되었습니다.
							$return = array('result'=>false, 'msg'=>getAlert('et299'));
							echo json_encode($return);
							exit;
						}
					}
				} else {//수정인경우

					// 비번입력후 브라우저를 닫기전까지는 등록/삭제가능함
					$ss_pwwrite_name = 'board_pwwrite_'.BOARDID;
					$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
					if ((!strstr($boardpwwritess, '[' . $deleteSeq . ']') && !empty($boardpwwritess)) || empty($boardpwwritess)) {
						$boardpwwritessadd = (!empty($boardpwwritess)) ? $boardpwwritess . '[' . $deleteSeq . ']' : '[' . $deleteSeq . ']';
						$this->session->set_userdata($ss_pwwrite_name, $boardpwwritessadd);
					}

					$return = ['result' => true];
					echo json_encode($return);
					exit;
				}
			} else {
				//비밀번호가 일치하지 않습니다.
				$return = ['result' => false, 'msg' => getAlert('et301')];
				echo json_encode($return);
				exit;
			}
			exit;
		}

		/* 게시글다중삭제 */
		elseif($mode == 'board_multi_delete') {

		}

		/* 게시글다중복사 */
		elseif($mode == 'board_multi_copy') {

		}

		/* 게시글 다중이동 */
		elseif($mode == 'board_multi_move') {

		}

		/* 게시글 파일삭제 */
		elseif($mode == 'board_file_delete') {
			
			$aParamsPost 	= $this->input->post();
			$realfiledir 	= ROOTPATH.'data/board/'.BOARDID.'/';				// 삭제할 파일 위치 고정
			$realfilename 	= basename($aParamsPost['realfiledir']);			// 삭제할 파일명만 가져오기		

			if(empty($realfilename)) {// || empty($_SERVER['HTTP_REFERER']) ie8
				echo getAlert('et002');		//존재하지 않는 파일입니다.
				exit;
			}
			
			// 게시글 파일 본인 확인
			unset($sc, $bindData);
			$sc['select'] = "seq, mseq";
			$sc['whereis'] = "AND seq = ? AND upload like ? ";
			$bindData = [$aParamsPost['board_seq'], "%".$realfilename."%"];
			$writerCheck = $this->Boardmodel->get_data($sc, $bindData);
			/* 
			* 비회원인 경우: seq, upload 기준으로 해당하는 seq 존재하는지 체크
			* 회원인 경우: seq, upload 기준으로 해당하는 mseq와 로그인된 member_seq가 일치하는지 체크
			*/
			if($this->userInfo['member_seq']){ // 회원
				if($writerCheck['mseq'] != $this->userInfo['member_seq']) {
					echo getAlert('et083');
					exit;
				}
			}else{ // 비회원
				// 비번입력후 브라우저를 닫기전까지는 접근가능함
				$ss_pwwrite_name = 'board_pwwrite_'.BOARDID;
				$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
				if ( ( !strstr($boardpwwritess,'['.$aParamsPost['board_seq'].']') && !empty($boardpwwritess)) || empty($boardpwwritess) || empty($writerCheck['seq'])) {
					echo getAlert('et083');
					exit;
				}
			}
			$dirRealpath	= realpath($aParamsPost['realfiledir']);			// 삭제할 (임시)파일명의 실제 위치
			$nameRealpath	= realpath($aParamsPost['realfilename']);			// 삭제할 파일명의 실제 위치

			/**
			 * 삭제할 파일 경로 체크
			 *  realpath 가 존재할때 고정된 삭제위치와 비교함하여 경로가 다르면 fail
			 * /../../../../../../../var/www/html/data/.htaccess 식의 접근 시 실제 reailpath 가 존재하더라도 realfiledir의 경로가 아니므로 fail
			*/
			if((!empty($dirRealpath) && !preg_match('/'.str_replace('/','\/',$realfiledir).'/',$dirRealpath))
			  || !empty($nameRealpath) && !preg_match('/'.str_replace('/','\/',$realfiledir).'/',$nameRealpath))
			  {
				echo getAlert('et002');		//존재하지 않는 파일입니다.
				exit;
			}

			/*
			* 삭제할 파일 존재 여부 체크
			* 지정된 경로(홈디렉토리/data/board/게시판id/)에 파일이 존재할때 삭제 처리.
			*/
			$filedel = false;
			if (is_file($realfiledir . '' . $realfilename)) {
				$filedel = true;
			} elseif (!is_file($realfiledir . '' . $realfilename) && is_file($realfiledir . '' . $realfilename)) {
				$filedel = true;
				$realfilename = basename($aParamsPost['realfilename']);
				if (!$realfilename) {
					$realfilename = $aParamsPost['realfilename'];
				} 	// realfilename은 보통 photo.jpg 와 같이 파일명으로 넘어오기에 basename이 없을 수 있음.
			}
			if ($filedel) {
				@unlink($realfiledir . '_thumb_' . $realfilename);
				@unlink($realfiledir . '_widget_thumb_' . $realfilename);
				@unlink($realfiledir . '' . $realfilename);
				echo getAlert('et060');		// 삭제되었습니다.
			} else {
				echo getAlert('et002');		//존재하지 않는 파일입니다.
			}
			exit;

		}

		/* 게시글 파일다운 */
		elseif($mode == 'board_file_down') {
			// 다운받을 파일 가져오기
			$sUploadInfo	= $_GET['realfiledir'];
			$oUploadInfo	= json_decode(base64_decode($sUploadInfo));
			$iSeq		= $oUploadInfo->seq;
			$sField		= $oUploadInfo->field;
			$iUploadKey	= $oUploadInfo->idx;

			$iSeq			= (int) mysqli_real_escape_string($this->db->conn_id, $iSeq);

			$paramSelect = ['upload','seq'];
			if(BOARDID!=='goods_review'){
				// 상품후기 게시판은 re_upload 필드 없음, 답변도 못씀
				$paramSelect[] = 're_upload';
			}
			if (in_array(BOARDID, ['goods_qna','goods_review','bulkorder']) === false) {
				// 위 게시판은 각각 테이블이 만들어져서 boardid 필요 없음
				$paramSelect[] = 'boardid';
			}

			$aParam['select'] = implode(',',$paramSelect);
			$aParam['whereis']	=  " and seq='".$iSeq."'";
			$aBoardData		= $this->Boardmodel->get_data($aParam);

			if($aBoardData[$sField]){
				$aFiles							= explode("|", $aBoardData[$sField]);
				list($sRealFile, $sOrignalFile, $sSizeFile, $sTypeFile)	= explode("^^", $aFiles[$iUploadKey]);
				$sRealFileDir						= $this->Boardmodel->upload_path.$sRealFile;
			}

			if(empty($sRealFileDir) ) {
				$callback = "document.history(-1)";
				//다운받을 파일을 선택해 주세요.
				openDialogAlert(getAlert('et302'),400,140,'parent',$callback);
				exit;
			}

			if( strstr($sRealFileDir,'..') ) {
				$callback = "document.history(-1)";
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}


			if( !strstr($sRealFileDir,'/data/') ) {
				$callback = "document.history(-1)";
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			//데이타이전->한글파일명처리@2012-11-22
			if(preg_match("/[\xA1-\xFE\xA1-\xFE]/",$sOrignalFile) && preg_match("/[\xA1-\xFE\xA1-\xFE]/",$sRealFileDir)) {
				$sRealFileDir = str_replace(basename($sRealFileDir),"",($sRealFileDir)).iconv('utf-8','cp949',($sOrignalFile));
			}

			if(is_file($sRealFileDir)){
				$data = @file_get_contents($sRealFileDir);
				force_download(rawurlencode(str_replace(" ","_",$sOrignalFile)), $data);
				exit;
			}
		}

		/* 게시글 파일보기 */
		elseif($mode == 'board_file_review') {
			if(empty($_GET['realfiledir']) ) {// || empty($_SERVER['HTTP_REFERER']) ie8 no
				$callback = "document.history(-1)";
				//다운받을 파일을 선택해 주세요.
				openDialogAlert(getAlert('et302'),400,140,'parent',$callback);
				exit;
			}

			if( strstr($_GET['realfiledir'],'..') ) {
				$callback = "document.history(-1)";
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}


			if( !strstr($_GET['realfiledir'],'/data/') ) {
				$callback = "document.history(-1)";
				openDialogAlert(getAlert('et270'),400,140,'parent',$callback);
				exit;
			}

			//데이타이전->한글파일명처리@2012-11-22
			if(preg_match("/[\xA1-\xFE\xA1-\xFE]/",$_GET['realfilename']) && preg_match("/[\xA1-\xFE\xA1-\xFE]/",$_GET['realfiledir'])) {
				$_GET['realfiledir'] = str_replace(basename($_GET['realfiledir']),"",($_GET['realfiledir'])).iconv('utf-8','cp949',($_GET['realfilename']));
			}

			if(is_file($_GET['realfiledir'])){
				$data = @file_get_contents($_GET['realfiledir']);
				echo $data;
				exit;
			}
		}

		/* 게시글 비밀글 > 비밀번호 체크 */
		elseif($mode == 'board_hidden_pwcheck') {
			$requestPost = $this->input->post(null, false);
			// xss 필터링에서 제외
			$requestPost['pw'] = $this->input->post('pw', false);


			$requestPost['seq']			= (int) $requestPost['seq'];

			if(empty($requestPost['seq'])) {
				//잘못된 접근입니다.
				$result = array('result'=>false, 'msg'=>getAlert('et270'));
				echo json_encode($result);
				exit;
			}

			if(empty($requestPost['pw'])) {
				//비밀번호가 일치하지 않습니다.
				$result = array('result'=>false, 'msg'=>getAlert('et301'));
				echo json_encode($result);
				exit;
			}

			$parentsql['whereis']	= ' and seq= "'.$requestPost['seq'].'" ';
			//본래게시글 추출@2017-05-12
			if( !(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder') ) {
				$parentsql['whereis']	.= ' and boardid= "'.BOARDID.'" ';
			}
			$parentsql['select']		= '  seq, gid, comment, upload, depth, pw, mseq, mid, parent';
			$parentdata = $this->Boardmodel->get_data($parentsql);//게시물정보
			if(empty($parentdata['seq'])) {
				$callback = "document.history(-1)";
				//존재하지 않는 게시물입니다.
				openDialogAlert(getAlert('et281'),400,140,'parent',$callback);
				exit;
			}

			$topparentsql['whereis']	= ' and seq= "'.$parentdata['parent'].'" ';
			//본래게시글 추출@2017-05-12
			if( !(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder') ) {
				$topparentsql['whereis']	.= ' and boardid= "'.BOARDID.'" ';
			}
			$topparentsql['select']		= '  seq, gid, comment, upload, depth, pw, mseq, mid, parent';
			$topparentdata = $this->Boardmodel->get_data($topparentsql);//게시물정보

			//원본글 이나 부모글 비밀번호가 동일한경우
			if (
				Password::isConfirm($requestPost['pw'], $parentdata['pw']) === true
				|| Password::isConfirm($requestPost['pw'], $topparentdata['pw']) === true
			) {
				// 비번입력후 브라우저를 닫기전까지는 접근가능함
				$ss_pwhidden_name = 'board_pwhidden_'.BOARDID;
				$boardpwhiddenss = $this->session->userdata($ss_pwhidden_name);
				if ( ( !strstr($boardpwhiddenss,'['.$requestPost['seq'].']') && !empty($boardpwhiddenss)) || empty($boardpwhiddenss)) {
					$boardpwhiddenssadd = (!empty($boardpwhiddenss)) ? $boardpwhiddenss.'['.$requestPost['seq'].']':'['.$requestPost['seq'].']';
					$this->session->set_userdata($ss_pwhidden_name, $boardpwhiddenssadd );
				}

				$result = array('result'=>true);
				echo json_encode($result);
			}else{
				//비밀번호가 일치하지 않습니다.
				$result = array('result'=>false, 'msg'=>getAlert('et301'));
				echo json_encode($result);
				//잘못된 비밀번호입니다........
			}
			exit;

		}

		/* 스팸방지 새로고침 */
		elseif($mode == 'captcha_code_refresh') {
			$cap = boardcaptcha('refresh');
			if( $cap['image'] ) {
				$result = array('result'=>true, 'img'=>$cap['image']);
			}else{
				//생성하지 못하였습니다.
				$result = array('result'=>false, 'msg'=>getAlert('et303'));
			}
			echo json_encode($result);
			exit;
		}
	}

	/* 상품후기 자동마일리지 지급 */
	public function _goods_review_autoemoney($manager, $data, $cntlenth, $goodsreviewparent)
	{
		//emoney history
		$this->load->model('emoneymodel');
		$this->load->model('membermodel');

		$reserves = ($this->reserves)?$this->reserves:config_load('reserve');

		if( $reserves['autoemoney'] == 1 ) {//자동지급 사용시에만
			if($reserves['autoemoneytype'] == 2) {//조건2) 배송완료 구매자 + **자 후기등록
				 if( ($reserves['autoemoneystrcut1'] > 0 && $reserves['autoemoneystrcut1'] > $cntlenth)  || !$cntlenth) {
					$contentcutck = false;
					$contentcutlenth = $reserves['autoemoneystrcut1'];
				 }else{
					 $contentcutck = true;
				 }
			}elseif($reserves['autoemoneytype'] == 3) {//조건3) **자 후기등록
				 if( ($reserves['autoemoneystrcut2'] > 0 && $reserves['autoemoneystrcut2'] > $cntlenth) || !$cntlenth) {
					$contentcutck = false;
					$contentcutlenth = $reserves['autoemoneystrcut2'];
				 }else{
					 $contentcutck = true;
				 }
			}elseif($reserves['autoemoneytype'] == 1){//구매자인 회원만 마일리지지급가능
				$contentcutck = true;
			}
		}


		if($reserves['autoemoney'] == 1 && $this->userInfo['member_seq'] && $contentcutck === true ){//사용함

			//동영상 > 포토 > 기본게시글 우선순위중 지급@2014-05-12
			if( ($data['file_key_w'] && uccdomain('fileswf',$data['file_key_w'])) || ($data['file_key_i'] && uccdomain('fileswf',$data['file_key_i'])) ) {//동영상 > video
				$type = 'goods_review_auto_video';
				$goods_review_emoney = $reserves['autoemoney_video'];
				$goods_review_memo = '동영상 '.$manager['name'].' 작성 마일리지';
				$goods_review_memo_lang = $this->membermodel->make_json_for_getAlert("mp269",$manager['name']);    // 동영상 %s 작성 마일리지
				$goods_review_emoney_limit_date = get_emoney_limitdate('video_reserve');

				$goods_review_point = $reserves['autopoint_video'];
				$goods_review_memo_point = '동영상 '.$manager['name'].' 작성 포인트';
				$goods_review_memo_point_lang = $this->membermodel->make_json_for_getAlert("mp270",$manager['name']);    // 동영상 %s 작성 포인트
				$goods_review_point_limit_date = get_point_limitdate('video_point');
			}elseif($data['upload']  && boardisimage($data['upload'], $data['contents']) ) {//첨부파일 > image
				$type = 'goods_review_auto_photo';
				$goods_review_emoney = $reserves['autoemoney_photo'];
				$goods_review_memo = '포토 '.$manager['name'].' 작성 마일리지';
				$goods_review_memo_lang = $this->membermodel->make_json_for_getAlert("mp286",$manager['name']);    // 포토 %s 작성 마일리지
				$goods_review_emoney_limit_date = get_emoney_limitdate('photo_reserve');

				$goods_review_point = $reserves['autopoint_photo'];
				$goods_review_memo_point = '포토 '.$manager['name'].' 작성 포인트';
				$goods_review_memo_point_lang = $this->membermodel->make_json_for_getAlert("mp287",$manager['name']);    // 포토 %s 작성 포인트
				$goods_review_point_limit_date = get_point_limitdate('photo_point');
			}else{
				$type = 'goods_review_auto';
				$goods_review_emoney = $reserves['autoemoney_review'];
				$goods_review_memo = '일반 '.$manager['name'].' 작성 마일리지';
				$goods_review_memo_lang = $this->membermodel->make_json_for_getAlert("mp272",$manager['name']);    // 일반 %s 작성 마일리지
				$goods_review_emoney_limit_date = get_emoney_limitdate('default_reserve');

				$goods_review_point = $reserves['autopoint_review'];
				$goods_review_memo_point = '일반 '.$manager['name'].' 작성 포인트';
				$goods_review_memo_point_lang = $this->membermodel->make_json_for_getAlert("mp273",$manager['name']);    // 일반 %s 작성 포인트
				$goods_review_point_limit_date = get_point_limitdate('default_point');
			}

			### 특정기간 추가마일리지 또는 추가 포인트 및 유효기간체크 @2014-05-12
			if($reserves['bbs_start_date'] && $reserves['bbs_end_date']){
				$today = date("Y-m-d");
				if($today>=$reserves['bbs_start_date'] && $today<=$reserves['bbs_end_date']){
					$type_add = 'goods_review_date';
					$goods_review_emoney_add	= $reserves['emoneyBbs_limit'];
					$goods_review_memo_add = '특정기간 '.$manager['name'].' 작성 추가마일리지';
                                        $goods_review_memo_add_lang = $this->membermodel->make_json_for_getAlert("mp284",$manager['name']);    // 특정기간 %s 작성 추가마일리지
					$goods_review_emoney_limit_date_add = get_emoney_limitdate('date_reserve');

					$goods_review_point_add	= $reserves['pointBbs_limit'];
					$goods_review_memo_point_add = '특정기간 '.$manager['name'].' 작성 추가포인트';
                                        $goods_review_memo_point_add_lang = $this->membermodel->make_json_for_getAlert("mp285",$manager['name']);    // 특정기간 %s 작성 추가포인트
					$goods_review_point_limit_date_add = get_point_limitdate('date_point');
				}
			}

			if($reserves['autoemoneytype'] == 3) {//조건3) **자 후기등록 => 중복가능
				if($goods_review_emoney > 0 ) {
					$emoney['type']			= $type;
					$emoney['emoney']		= $goods_review_emoney;
					$emoney['gb']			= 'plus';
					$emoney['goods_review']         = $data['goods_seq'];
					$emoney['goods_review_parent']  = $goodsreviewparent;
					$emoney['ordno']		= $data['order_seq'];
					$emoney['memo']                 = $goods_review_memo;
					$emoney['memo_lang']		= $goods_review_memo_lang;
					$emoney['limit_date']           = $goods_review_emoney_limit_date;
					$this->membermodel->emoney_insert($emoney, $this->userInfo['member_seq']);
				}

				if($goods_review_point > 0 ) {
					$point['type']			= $type;
					$point['point']			=$goods_review_point;
					$point['gb']			= 'plus';
					$point['goods_review']          = $data['goods_seq'];
					$point['goods_review_parent']   = $goodsreviewparent;
					$point['ordno']			= $data['order_seq'];
					$point['memo']			= $goods_review_memo_point;
					$point['memo_lang']		= $goods_review_memo_point_lang;
					$point['limit_date']            = $goods_review_point_limit_date;
					$this->membermodel->point_insert($point, $this->userInfo['member_seq']);
				}


				### 특정기간 추가마일리지 또는 추가 포인트 및 유효기간체크 @2014-05-12
				if($goods_review_emoney_add > 0 ) {
					$emoney['type']			= $type_add;
					$emoney['emoney']		= $goods_review_emoney_add;
					$emoney['gb']			= 'plus';
					$emoney['goods_review']         = $data['goods_seq'];
					$emoney['goods_review_parent']  = $goodsreviewparent;
					$emoney['ordno']		= $data['order_seq'];
					$emoney['memo']                 = $goods_review_memo_add;
					$emoney['memo_lang']		= $goods_review_memo_add_lang;
					$emoney['limit_date']           = $goods_review_emoney_limit_date_add;
					$this->membermodel->emoney_insert($emoney, $this->userInfo['member_seq']);
				}

				### 특정기간 추가마일리지 또는 추가 포인트 및 유효기간체크 @2014-05-12
				if($goods_review_point_add > 0 ) {
					$point['type']			= $type_add;
					$point['point']			= $goods_review_point_add;
					$point['gb']			= 'plus';
					$point['goods_review']          = $data['goods_seq'];
					$point['goods_review_parent']   = $goodsreviewparent;
					$point['ordno']			= $data['order_seq'];
					$point['memo']			= $goods_review_memo_point_add;
					$point['memo_lang']             = $goods_review_memo_point_add_lang;
					$point['limit_date']            = $goods_review_point_limit_date_add;
					$this->membermodel->point_insert($point, $this->userInfo['member_seq']);
				}

			}else{

				if( $data['order_seq']) {//주문번호 and 회원 and 주문상품

					$itemwhere_arr = array('order_seq'=>$data['order_seq'], 'goods_seq'=>$data['goods_seq']);
					$itemdata = get_data('fm_order_item', $itemwhere_arr);
					if(!$itemdata){//주문상품이 없는경우
						return false;
					}

					$autoemoneysc['whereis'] = " and emoney_use!='less'  and (type ='goods_review_date' or type ='goods_review_auto_video' or type = 'goods_review_auto' or type = 'goods_review_auto_photo') and gb = 'plus' and member_seq = '".$this->userInfo['member_seq']."' and ordno  = '".$data['order_seq']."' and goods_review = '".$data['goods_seq']."' ";
					$autoemoneysc['select']	= ' emoney, type, emoney_seq ';
					$emautoemoneyck = $this->emoneymodel->get_data_numrow($autoemoneysc);//지급여부

					if( !$emautoemoneyck ) {//자동지급안된경우

						if($goods_review_emoney > 0 ) {
						$emoney['type']			= $type;
						$emoney['emoney']		= $goods_review_emoney;
						$emoney['gb']			= 'plus';
						$emoney['goods_review']         = $data['goods_seq'];
						$emoney['goods_review_parent']  = $goodsreviewparent;
						$emoney['ordno']		= $data['order_seq'];
						$emoney['memo']                 = $goods_review_memo;
						$emoney['memo_lang']            = $goods_review_memo_lang;
						$emoney['limit_date']           = $goods_review_emoney_limit_date;
						$this->membermodel->emoney_insert($emoney, $this->userInfo['member_seq']);
					}

						if($goods_review_emoney_add > 0 ) {
							$emoney['type']			= $type_add;
							$emoney['emoney']		= $goods_review_emoney_add;
							$emoney['gb']			= 'plus';
							$emoney['goods_review']         = $data['goods_seq'];
							$emoney['goods_review_parent']  = $goodsreviewparent;
							$emoney['ordno']		= $data['order_seq'];
							$emoney['memo']                 = $goods_review_memo_add;
							$emoney['memo_lang']		= $goods_review_memo_add_lang;
							$emoney['limit_date']           = $goods_review_emoney_limit_date_add;
							$this->membermodel->emoney_insert($emoney, $this->userInfo['member_seq']);
						}

					}

					//주문번호 and 회원 and 주문상품
					$autoemoneysc = $this->db->query("select  point, type, point_seq  from fm_point where  point_use!='less' and  (type ='goods_review_date' or type ='goods_review_auto_video' or type = 'goods_review_auto' or type = 'goods_review_auto_photo') and gb = 'plus' and member_seq = '".$this->userInfo['member_seq']."' and ordno  = '".$data['order_seq']."' and goods_review = '".$data['goods_seq']."' ");
					$emautopointck = $autoemoneysc->num_rows();
					if( !$emautopointck) {//자동지급안된경우
						if( $goods_review_point > 0 ) {
						$point['type']                      = $type;
						$point['point']                     = $goods_review_point;
						$point['gb']                        = 'plus';
						$point['goods_review']              = $data['goods_seq'];
						$point['goods_review_parent']       = $goodsreviewparent;
						$point['ordno']                     = $data['order_seq'];
						$point['memo']                      = $goods_review_memo_point;
						$point['memo_lang']                 = $goods_review_memo_point_lang;
						$point['limit_date']                = $goods_review_point_limit_date;
						$this->membermodel->point_insert($point, $this->userInfo['member_seq']);
					}

					if($goods_review_point_add > 0 ) {
						$point['type']                      = $type_add;
						$point['point']                     = $goods_review_point_add;
						$point['gb']                        = 'plus';
						$point['goods_review']              = $data['goods_seq'];
						$point['goods_review_parent']       = $goodsreviewparent;
						$point['ordno']                     = $data['order_seq'];
						$point['memo']                      = $goods_review_memo_point_add;
						$point['memo_lang']                 = $goods_review_memo_point_add_lang;
						$point['limit_date']                = $goods_review_point_limit_date_add;
						$this->membermodel->point_insert($point, $this->userInfo['member_seq']);
					}
					}

				}
			}
		}//endif
	}

	/* 상품후기 마일리지/포인트 회수 */
	public function _goods_review_less($manager, $parentdata)
	{
		$this->load->model('membermodel');
		$this->load->model('emoneymodel');

		/************
		* 마일리지 회수시작
		*************/
		$emautoemoneysc = $this->db->query("select * from fm_emoney where emoney_use !='less' and (type = 'goods_review_auto' or type = 'goods_review_auto_photo' or type = 'goods_review_auto_video' or type = 'goods_review_date') and gb = 'plus' and member_seq = '".$parentdata['mseq']."' and ( (ordno  = '".$parentdata['order_seq']."' and goods_review = '".$parentdata['goods_seq']."' and (ordno != '' or ordno != 0 ) and goods_review_parent='".$parentdata['seq']."' ) or ( goods_review_parent='".$parentdata['seq']."' ) ) ");
		$emautoemoneyar = $emautoemoneysc->result_array();
		if( $emautoemoneyar ) {
			foreach($emautoemoneyar as $emautoemoneyck=>$emautoemoney) {
				$board_less_emoney += $emautoemoney['emoney'];

				//지급>회수완료업데이트
				$this->db->where('emoney_seq',$emautoemoney['emoney_seq']);
				$this->db->update('fm_emoney',array('emoney_use'=>'less'));

			}//end foreach
		}

		//수동마일리지 지급여부
		$joinsc['whereis'] = " and emoney_use !='less' and type = 'goods_review' and gb = 'plus' and member_seq = '".$parentdata['mseq']."' and goods_review = '".$parentdata['seq']."' ";
		$joinsc['select']	= ' * ';
		$emjoinck = $this->emoneymodel->get_data($joinsc);
		if( $emjoinck ){
			$board_less_emoney += $emjoinck['emoney'];

			//지급>회수완료업데이트
			$this->db->where('emoney_seq',$emjoinck['emoney_seq']);
			$this->db->update('fm_emoney',array('emoney_use'=>'less'));
		}

		if( $board_less_emoney ) {
			$params = array(
				'gb'				=> 'minus',
				'type'				=> 'goods_review_less',
				'emoney'			=> $board_less_emoney,
				'goods_review'			=> $parentdata['goods_seq'],
				'goods_review_parent'           => $parentdata['seq'],
				'memo'				=> "[회수]".$manager['name']." 삭제에 의한 마일리지 차감",
				'memo_lang'			=> $this->membermodel->make_json_for_getAlert("mp266",$manager['name']),   // [회수]%s 삭제에 의한 마일리지 차감
			);
			$this->membermodel->emoney_insert($params, $parentdata['mseq']);
		}

		/************
		* 마일리지 회수끝
		*************/

		/************
		* 포인트 회수시작
		*************/
		$emautopointsc = $this->db->query("select  * from fm_point where point_use !='less' and (type = 'goods_review_auto' or type = 'goods_review_auto_photo' or type = 'goods_review_auto_video' or type = 'goods_review_date') and gb = 'plus' and member_seq = '".$parentdata['mseq']."' and ( (ordno  = '".$parentdata['order_seq']."' and goods_review = '".$parentdata['goods_seq']."' and (ordno != '' or ordno != 0 ) ) or ( goods_review_parent='".$parentdata['seq']."' ) ) ");
		$emautopointar = $emautopointsc->result_array();

		if( $emautopointar) {
			foreach($emautopointar as $emautopointck=>$emautopoint) {
				$board_less_point += $emautopoint['point'];

				//지급>회수완료업데이트
				$this->db->where('point_seq',$emautopoint['point_seq']);
				$this->db->update('fm_point',array('point_use'=>'less'));
			}//end foreach
		}

		if( $board_less_point ){
			$params = array(
				'gb'			=> 'minus',
				'type'			=> 'goods_review_less',
				'point'			=> $board_less_point,
				'goods_review'		=> $parentdata['goods_seq'],
				'goods_review_parent'	=> $parentdata['seq'],
				'memo'			=> "[회수]".$manager['name']." 삭제에 의한 포인트 차감",
				'memo_lang'		=> $this->membermodel->make_json_for_getAlert("mp267",$manager['name']),   // [회수]%s 삭제에 의한 포인트 차감
			);
			$this->membermodel->point_insert($params, $parentdata['mseq']);
		}
		/************
		* 포인트 회수끝
		*************/

	}



	/* 상품후기외 수동마일리지 회수 */
	public function _board_less($manager, $parentdata)
	{
		$this->load->model('membermodel');
		$this->load->model('emoneymodel');

		/************
		* 마일리지 회수시작
		*************/

		//수동마일리지 지급여부
		$joinsc['whereis'] = " and emoney_use !='less' and type = 'board_".$manager['id']."' and gb = 'plus' and member_seq = '".$parentdata['mseq']."' and (goods_review = '".$parentdata['seq']."' or goods_review_parent = '".$parentdata['seq']."')  ";
		$joinsc['select']	= ' * ';
		$emjoinck = $this->emoneymodel->get_data($joinsc);
		if( $emjoinck ){
			$board_less_emoney += $emjoinck['emoney'];
			/**
				$params = array(
					'gb'					=> 'minus',
					'type'					=> 'board_'.$manager['id'].'_less',
					'emoney'				=> $emjoinck['emoney'],
					'goods_review'				=> $emjoinck['seq'],
					'memo'					=> "[회수]".$manager['name']." 삭제에 의한 마일리지 차감",
					'memo_lang'				=> $this->membermodel->make_json_for_getAlert("mp266",$manager['name']), // [회수]%s 삭제에 의한 마일리지 차감
				);
				$this->membermodel->emoney_insert($params, $parentdata['mseq']);
			**/

			//지급>회수완료업데이트
			$this->db->where('emoney_seq',$emjoinck['emoney_seq']);
			$this->db->update('fm_emoney',array('emoney_use'=>'less'));
		}

		if( $board_less_emoney ) {
			$params = array(
				'gb'                        => 'minus',
				'type'                      => 'board_'.$manager['id'].'_less',
				'emoney'                    => $board_less_emoney,
				'goods_review'              => $parentdata['goods_seq'],
				'goods_review_parent'       => $parentdata['seq'],
				'memo'                      => "[회수]".$manager['name']." 삭제에 의한 마일리지 차감",
				'memo_lang'                 => $this->membermodel->make_json_for_getAlert("mp266",$manager['name']),   // [회수]%s 삭제에 의한 마일리지 차감
			);
			$this->membermodel->emoney_insert($params, $parentdata['mseq']);
		}

		/************
		* 마일리지 회수끝
		*************/

	}

	//게시글평가하기
	public function board_score_save()
	{
		$sc['whereis']	= ' and id= "'.BOARDID.'" ';
		$sc['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		if (!isset($this->manager['id'])) {
			//존재하지 않는 게시판입니다.
			$result = array('result'=>false, 'msg'=>getAlert('et269'));
			echo json_encode($result);
			exit;
		}

		// 로그인 체크
		$session_arr = ( $this->session->userdata('user') )?$this->session->userdata('user'):$_SESSION['user'];
		if(!$session_arr['member_seq']){
			//회원만 사용가능합니다.
			$result = array('result'=>false, 'msg'=>getAlert('et309'));
			echo json_encode($result);
			exit;
		}

		if( $this->manager['auth_recommend_use'] == 'Y' ) {
			$_POST['parent']			= (int) $_POST['parent'];
			$sc['whereis']	= ' and seq= "'.$_POST['parent'].'" ';
			$sc['select']		= ' seq, gid, comment, upload, depth, file_key_w  ';
			$data = $this->Boardmodel->get_data($sc);

			if(empty($data)) {
				//존재하지 않는 게시물입니다.
				$result = array('result'=>false, 'msg'=>getAlert('et281'));
				echo json_encode($result);
				exit;
			}

			//권한체크
			//비밀글 > 비회원 또는 회원은 본인이 아닌 경우
			if($data['hidden'] == 1 && $data['notice'] == 0) {//공지글은 무조건보기가능
				$parentsql['whereis']	= ' and seq= "'.$data['parent'].'" ';
				$parentsql['select']		= '  seq, gid, comment, upload, depth, pw, mseq, mid, parent';
				$parentdata = $this->Boardmodel->get_data($parentsql);//게시물정보

				if( $data['mseq'] > 0  || ($data['parent'] && $parentdata['mseq'] > 0 ) ) {//회원이 쓴글인경우
					if( ( ($data['mseq'] != $this->userInfo['member_seq'] && $parentdata['mseq'] != $this->userInfo['member_seq']) && defined('__ISUSER__'))  || ( !defined('__ISUSER__') ) ) {//작성자가 아니거나 비회원인 경우
						if(!defined('__ISADMIN__')) {
							//평가권한이 없습니다.
							$result = array('result'=>false, 'msg'=>getAlert('et304'));
							echo json_encode($result);
							exit;
						}
					}
				}
				else{
					// 비번입력후 브라우저를 닫기전까지는 접근가능함
					$ss_pwhidden_name = 'board_pwhidden_'.BOARDID;
					$boardpwhiddenss = $this->session->userdata($ss_pwhidden_name);
					if ( ( !strstr($boardpwhiddenss,'['.$_POST['parent'].']') && isset($boardpwhiddenss)) || empty($boardpwhiddenss)) {
						if(!defined('__ISADMIN__')) {
							$result = array('result'=>false, 'msg'=>getAlert('et304'));
							echo json_encode($result);
							exit;
						}
					}
				}
			}

			get_auth($this->manager, $data, 'read', $isperm);//접근권한체크
			if ( $isperm['isperm_read'] === false ) {
				$result = array('result'=>false, 'msg'=>getAlert('et304'));
				echo json_encode($result);
				exit;
			}
			$_POST['parent']			= (int) $_POST['parent'];
			$_POST['cparent']		= (int) $_POST['cparent'];

			$parentsql['whereis']	= ' and boardid= "'.$_POST['board_id'].'" ';
			$parentsql['whereis']	.= ' and type= "board" ';
			$parentsql['whereis']	.= ' and parent= "'.$_POST['parent'].'" ';//게시글
			if($_POST['cparent']) $parentsql['whereis']	.= ' and cparent= "'.$_POST['cparent'].'" ';//댓글
			$parentsql['whereis']	.= ' and mseq= "'.$this->userInfo['member_seq'].'" ';
			$getscore = $this->Boardscorelog->get_data($parentsql);

			if(!$getscore) {
				//recommend/none_rec/recommend1/recommend2/recommend3/recommend4/recommend5
				$scoreid=  $_POST['scoreid'];
				$result = $this->Boardmodel->board_score_update($data['seq'], $scoreid,' + ');
				 if( $result ) {
					$params['type']				= 'board';
					$params['boardid']			= $_POST['board_id'];
					$params['scoreid']			= $scoreid;
					$params['parent']			= $_POST['parent'];
					if($_POST['cparent']) $params['cparent']			= $_POST['cparent'];
					$params['mseq']			= $this->userInfo['member_seq'];
					$params['regist_date']	= date("Y-m-d H:i:s");
					$this->Boardscorelog->data_write($params);

					$sc['whereis']	= ' and seq= "'.$_POST['parent'].'" ';
					$sc['select']		= ' seq, gid, comment, upload, depth, file_key_w,'.$scoreid;
					$getscoredata = $this->Boardmodel->get_data($sc);
					//회원님의 평가가 반영되었습니다.
					 $msg = getAlert('et305');
				 }else{
					 //회원님의 평가가 실패되었습니다.
					 $msg = getAlert('et306');
				 }
			}else{
				//이미 평가하신 게시글입니다.
				 $msg = getAlert('et307');
			}
		}else{
			//잘못된 접근입니다.
			 $msg = getAlert('et270');
		}

		if( $result ) {
			$result = array('result'=>true, 'msg'=>$msg, 'scoreid'=>$getscoredata[$scoreid]);
		}else{
			$result = array('result'=>false, 'msg'=>$msg);
		}

		echo json_encode($result);
		exit;
	}

	public function mobile_upload() {

	    if(function_exists('return_bytes') === true) {
	        $postMaxSize = return_bytes(ini_get('post_max_size'));
	    } else {
	        $postMaxSize = 20971520;
	    }

	    $contentLen = (int) $this->input->server("CONTENT_LENGTH");
	    if( $contentLen > $postMaxSize) {
	        $this->load->helper('number');
	        $postMaxSizeText = byte_format($postMaxSize, 0);
	        $msg = getAlert('et413', array($postMaxSizeText));
	        $result = array('status' => false,'uploadFile' => null, 'incFile' => null, 'is_image' => true, 'msg' => $msg, 'board' => null);
	        $result = json_encode($result);
	        exit($result);
	    }

	    $_POST['insert_image'] = $_GET['insert_image'];
		$uploadsRes = board_mobile_file($parentdata, $realfilename, $incimage, $res_status);//첨부파일처리

		if ($uploadsRes === false) {
			$result = [
				'status' => false,
				'uploadFile' => null,
				'incFile' => null,
				'is_image' => true,
				'msg' => "업로드 실패",
				'board' => null
			];
			exit(json_encode($result));
		}

		$status = true;
		if(is_array($res_status)) foreach($res_status as $key => $res){
			if($res['status'] == 1 && $status === true){
				$status = true;
				$board = $res['board'];
			}else{
				$status = false;
				$msg	= $res['status'];
			}
		}

		// incimage 변수 serialize
		if(!empty($incimage)){
			$tmp = array();
			foreach($incimage as $inci){
				$tmp[] = base64_encode($inci);
			}

			$incimage = implode(',', $tmp);
		}

	    $result = array('status' => $status,'uploadFile' => $realfilename, 'incFile' => $incimage, 'is_image' => true, 'msg' => $msg, 'board' => $board);

	    echo "[".json_encode($result)."]";
	    exit;
	}

	/**
	 * 게시글 신고하기 전 유효성체크
	 */
	public function report_check() {
		$result = [
			'result' => FALSE,
			'msg' => getAlert('et270'),
		];
		// 회원체크
		if(defined('__ISUSER__') === FALSE) {
			echo json_encode($result);
			exit;
		}
		unset($result['msg']);
		$param = $this->input->post();

		$this->load->library('Board/BoardReportLibrary');
		/**
		 * 기존에 중복 신고한 글이 있는지 체크
		 * 중복이면 false 반환
		 */
		$arr = [
			'board_id' => $param['board_id'],
			'board_seq' => $param['board_seq'],
			'board_type' => $param['board_type'],
			'member_seq' => $this->userInfo['member_seq'],
		];
		$result['result'] = $this->boardreportlibrary->canReportBoard($arr);
		if($result['result'] === FALSE) {
			$result['msg'] = getAlert('et418');
		}

		echo json_encode($result);
		exit;
	}

	// 게시글 신고하기
	public function report() {
		if(defined('__ISUSER__') === FALSE) {
			openDialogAlert(getAlert('et270'),400,140,'parent');
			exit;
		}

		$param = $this->input->post();

		$this->validation->set_rules('board_seq', '게시글정보','trim|required|xss_clean');
		$this->validation->set_rules('board_id', '게시글정보','trim|required|xss_clean');
		$this->validation->set_rules('board_type', '게시글정보','trim|required|xss_clean');
		$this->validation->set_rules('contents', getAlert('et419'),'trim|required|xss_clean|min_length[5]|max_length[100]');
		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();parent.submitck();";
			openDialogAlert($err['value'],400,140,'parent',$callback);
			exit;
		}

		$this->load->library('Board/BoardReportLibrary');
		/**
		 * 기존에 중복 신고한 글이 있으면 alert
		 */
		$arr = [
			'board_id' => $param['board_id'],
			'board_seq' => $param['board_seq'],
			'board_type' => $param['board_type'],
			'member_seq' => $this->userInfo['member_seq'],
		];
		$canReport = $this->boardreportlibrary->canReportBoard($arr);
		if($canReport === FALSE) {
			openDialogAlert(getAlert('et418'),400,140,'parent');
			exit;
		}

		$this->db->trans_begin();

		// 게시글 신고
		$arr['contents'] = $param['contents'];
		$result = $this->boardreportlibrary->setReport($arr);

		if ($this->db->trans_status() === FALSE || $result === FALSE)
		{
			
			$this->db->trans_rollback();
			openDialogAlert(getAlert('et270'),400,140,'parent','parent.location.reload();');
			exit;
		}

		$this->db->trans_commit();
		openDialogAlert(getAlert('et420'),400,140,'parent','parent.location.reload();');
		exit;
	}

	/**
	 * 게시글 차단하기 전 체크
	 */
	public function block_check() {
		$result = [
			'result' => FALSE,
			'msg' => getAlert('et270'),
		];
		if(defined('__ISUSER__') === FALSE) {
			echo json_encode($result);
			exit;
		}
		$param = $this->input->post();

		$this->validation->set_rules('board_seq', '게시글번호','trim|required|xss_clean');
		$this->validation->set_rules('board_id', '게시글명','trim|required|xss_clean');
		$this->validation->set_rules('block_onoff', '차단여부','trim|required|xss_clean');
		if($this->validation->exec()===false){
			echo json_encode($result);
			exit;
		}

		$result = [];
		$this->load->library('Board/BoardBlockLibrary');

		// 차단 가능한지 체크
		$arr = [
			'board_id' => $param['board_id'],
			'board_seq' => $param['board_seq'],
			'board_type' => $param['board_type'],
			'member_seq' => $this->userInfo['member_seq'],
		];
		$result['result'] = $this->boardblocklibrary->canBlockBoard($arr);
		if($result['result'] === TRUE) {
			if($param['block_onoff'] == 'off') {
				// %s 회원님을 차단하시겠습니까?%n상대방의 게시글과 댓글을 확인할 수 없습니다.
				$result['msg'] = getAlert('et421');
			} else {
				$result['msg'] = getAlert('et422');
			}
		} else {
			$result['msg'] = getAlert('et270');
		}

		echo json_encode($result);
		exit;
	}

	// 게시글 차단하기
	public function block() {
		$result = [
			'result' => FALSE,
			'msg' => getAlert('et270'),
		];
		if(defined('__ISUSER__') === FALSE) {
			echo json_encode($result);
			exit;
		}

		$param = $this->input->post();

		$this->validation->set_rules('board_seq', '게시글번호','trim|required|xss_clean');
		$this->validation->set_rules('board_id', '게시글명','trim|required|xss_clean');
		if($this->validation->exec()===false){
			echo json_encode($result);
			exit;
		}

		$this->load->library('Board/BoardBlockLibrary');

		/**
		 * 기존에 중복 차단 글이 있으면 alert
		 */
		$arr = [
			'board_id' => $param['board_id'],
			'board_seq' => $param['board_seq'],
			'board_type' => $param['board_type'],
			'member_seq' => $this->userInfo['member_seq'],
			'block_onoff' => $param['block_onoff'],
		];
		$canBlock = $this->boardblocklibrary->canBlockBoard($arr);
		if($canBlock === FALSE) {
			echo json_encode($result);
			exit;
		}

		$this->db->trans_begin();

		// 실제 차단(해제) 처리
		$block = $this->boardblocklibrary->processBlock($arr);

		if ($this->db->trans_status() === FALSE || $block === FALSE)
		{
			$this->db->trans_rollback();
			echo json_encode($result);
			exit;
		}

		$this->db->trans_commit();
		echo json_encode(['result'=>TRUE]);
		exit;
	}
}

/* End of file board_process.php */
/* Location: ./app/controllers/admin/board_process.php */