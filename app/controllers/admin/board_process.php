<?php
/**
 * 게시글 관련 관리자 process
 * @author gabia
 * @since version 2.0 - 2012.06.29
 */
 if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

use App\libraries\Password;

class Board_process extends admin_base {

	public function __construct() {
		parent::__construct();


		/* 관리자 권한 체크 : 시작 */
		$auth = $this->authmodel->manager_limit_act('board_act');
		if(!$auth){
			if($_POST['mode']) {
				$return = array('result'=>false, 'name'=>$manager['name'], 'msg'=>$this->auth_msg);
				echo json_encode($return);
			}else{
				$this->admin_menu();
				$this->tempate_modules();
				$callback = "history.go(-1);";
				$this->template->assign(array('auth_msg'=>$this->auth_msg,'callback'=>$callback));
				$this->template->define(array('denined'=>$this->skin.'/common/denined.html'));
				$this->template->print_("denined");
			}
			exit;
		}
		/* 관리자 권한 체크 : 끝 */

		$boardid = (!empty($_POST['board_id'])) ? $_POST['board_id']:$_GET['board_id'];
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
	}

	/* 기본 */
	public function index()
	{
		$mode = (!empty($_POST['mode']))?$_POST['mode']:$_GET['mode'];

		$sc['whereis']	= ' and id= "'.BOARDID.'" ';
		$sc['select']		= ' * ';
		$manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		if (!isset($manager['id'])) {
			openDialogAlert("존재하지 않는 게시판입니다.",400,140,'parent','');
			exit;
		}
		boarduploaddir($manager);//폴더생성 및 스킨 복사

		/* 게시글등록 */
		if($mode == 'board_write') {
			$requestPost = $this->input->post();
			// 에디터 내용은 xss 필터링에서 제외
			$requestPost['contents'] = $this->input->post('contents', false);
			$requestPost['re_contents'] = $this->input->post('re_contents', false);
			
			### //넘어온 추가항목 seq
			if( BOARDID == 'bulkorder'  ||  BOARDID == 'goods_review' ) {
				$label_pr = $requestPost['label'];
				$label_sub_pr = $requestPost['labelsub'];
				$label_required = $requestPost['required'];
				foreach($label_pr as $l => $data){$label_arr[]=$l;}
			}

			if( $requestPost['subject'] == '제목을 입력해 주세요' ) {
				$requestPost['subject'] = '';
			}

			if($requestPost['contents'] == "<p><br></p>") {
				$requestPost['contents'] = "";
			}

			### Validation
			$this->validation->set_rules('subject', '제목','trim|required|xss_clean');
			$this->validation->set_rules('contents', '내용','trim|required');

			if($requestPost['category'] == 'newadd') {
				$this->validation->set_rules('newcategory', '신규분류명','trim|required|xss_clean');
			}
			if($this->validation->exec()===false){
			   $err = $this->validation->error_array;
			   $callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();parent.setDefaultText();parent.submitck();";
			   openDialogAlert($err['value'],400,140,'parent',$callback);
			   exit;
			}


			if( $requestPost['notice'] && $requestPost['onlynotice'] == 1 ){
				if(strtotime($requestPost['onlynotice_sdate']) > strtotime($requestPost['onlynotice_edate'])){
					$callback = "parent.$('#onlynotice_edate').focus();";
					openDialogAlert("기간 종료일이 시작일보다 이후에 있어야 합니다.<br/>날짜를 조정해 주세요.",450,180,'parent',$callback);
					exit;
				}
			}

			$params['boardid']		=  BOARDID;
			$params['notice']			=  if_empty($requestPost, 'notice', '0');//공지
			$params['hidden']		=  if_empty($requestPost, 'hidden', '0');//비밀글

			if( BOARDID == 'gs_seller_notice' ) {
				$params['onlypopup']	=  if_empty($requestPost, 'onlypopup', '0');//팝업여부
				$params['onlypopup_sdate']		= $requestPost['onlypopup_sdate'];//팝업시작일
				$params['onlypopup_edate']		= $requestPost['onlypopup_edate'];//팝업완료일
			}

			$params['subject']		=  $requestPost['subject'];
			$params['editor']			=  ($requestPost['daumedit'])?1:0;//모바일
			$params['name']			=  (!empty($requestPost['name']))?$requestPost['name']:'';
			$params['category']		=  (!empty($requestPost['category']))?htmlspecialchars($requestPost['category']):'';

			//신규분류
			if(!empty($requestPost['newcategory']) && $requestPost['category']=='newadd'){
				$params['category'] = htmlspecialchars($requestPost['newcategory']);
			}
			$params['contents']		=  $requestPost['contents'];

			$params['re_contents'] = $requestPost['re_contents'];//1:1문의 답변시
			$params['re_contents'] = adjustEditorImages($requestPost['re_contents'], $this->Boardmodel->upload_src);
			if($this->_is_mobile_agent){//모바일인경우 text
				$params['re_contents'] = nl2br($params['re_contents']);
			}
			$params['re_subject'] = $requestPost['re_subject'];//1:1문의 답변시
			if($requestPost['reply'] && $requestPost['re_subject']) $params['re_date']		= date("Y-m-d H:i:s");

			if( !empty($requestPost['pw']) ) $params['pw']  = Password::encrypt($requestPost['pw']);//값이 잇는경우에만 변경

			if(!empty($requestPost['email'])) $params['email']=($requestPost['email']);
			if(!empty($requestPost['tel1'])) $params['tel1']=($requestPost['tel1']);
			if(!empty($requestPost['tel2'])) $params['tel2']=($requestPost['tel2']);

			if(!empty($requestPost['board_sms_hand'])) $params['tel1']=($requestPost['board_sms_hand']);
			if(!empty($requestPost['board_sms_email'])) $params['email']=($requestPost['board_sms_email']);

			if(!empty($requestPost['board_sms']))		$params['rsms']='Y';
			if(!empty($requestPost['board_email']))	$params['remail']='Y';

			if( !empty($requestPost['score']) ) $params['score']  = ($requestPost['score']);//값이 잇는경우에만 변경
			if( !empty($requestPost['score_avg']) )	$params['score_avg'] = $requestPost['score_avg'];

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

			if (isset($requestPost['seq']) ) {//답변시

				$parentsql['whereis']	= ' and seq= "'.$requestPost['seq'].'" ';
				$parentsql['select']		= '  seq, gid, comment, upload, depth, subject, contents, r_date, parent, pw, hidden, name ';
				$parentdata = $this->Boardmodel->get_data($parentsql);
				if(empty($parentdata)) {
					$callback = "parent.document.location.href='".$this->Boardmanager->realboardurl.BOARDID."';";
					openDialogAlert("존재하지 않는 게시물입니다.",400,140,'parent',$callback);
					exit;
				}

				$parentsql['whereis']	= ' and gid >= '.$parentdata['gid'].' and gid < '.(intval($parentdata['gid'])+1);
				//$parentsql['select']		= ' gid ';
				$parentrumrow = $this->Boardindex->get_data_numrow($parentsql);
				if($parentrumrow>98) {
					openDialogAlert("죄송합니다. 더이상 답변을 달 수 없습니다.",400,140,'parent','');
					exit;
				}

				$gidup['set']				= ' gid=gid+0.01 ';
				$gidup['whereis']		= ' gid > '.$parentdata['gid'].' and gid < '.(intval($parentdata['gid'])+1);
				$this->Boardmodel->data_gid_save($gidup);//data gid update
				$this->Boardindex->data_gid_save($gidup);//idx gid update

				$params['parent']	= $requestPost['seq'];
				$params['gid']			= $parentdata['gid']+0.01;
				$params['depth']		= $parentdata['depth']+1;

				$params['pw']			= $parentdata['pw'];//부모글비밀번호입니다.

				if( defined('__SELLERADMIN__') === true ) {
					$params['re_mseq']			= '-'.$this->providerInfo['provider_seq'];
					$params['re_mtype']			= 'p';
				}else{
					$params['re_mtype']			= 'r';
				}

				if(!empty($requestPost['board_sms']) || !empty($requestPost['board_email']) ) {//답변시 본래글의 휴대폰/이메일정보 업데이트
					if(!empty($requestPost['board_sms']))		$parentdataupsmsemail['rsms']='Y';
					if(!empty($requestPost['board_email']))	$parentdataupsmsemail['remail']='Y';

					if(!empty($requestPost['board_sms_hand'])) $parentdataupsmsemail['tel1']=($requestPost['board_sms_hand']);
					if(!empty($requestPost['board_sms_email'])) $parentdataupsmsemail['email']=($requestPost['board_sms_email']);
					$parentdataupsmsemail['seq'] = $requestPost['seq'];
					$this->Boardmodel->data_modify($parentdataupsmsemail);
				}


			}else{//새글
					$minsql['whereis']	= ' ';
				$minsql['select']		= ' min(gid) as mingid ';
				$mindata = $this->Boardmodel->get_data($minsql);
				$parentgid = $mindata['mingid'] ? $mindata['mingid']-1 : 100000000.00;
				$params['parent']	= 0;
				$params['gid']			= $parentgid;
				$params['depth']		= 0;

				if( defined('__SELLERADMIN__') === true ) {
					$params['mtype']			= 'p';
				}else{
					$params['mtype']			= 'r';
				}
			}

			if( defined('__SELLERADMIN__') === true ) {
				$params['mseq']			= '-'.$this->providerInfo['provider_seq'];//입점사$this->providerInfo['provider_seq'];
				$params['mid']				= $this->providerInfo['provider_id'];
			}else{
				$params['mseq']			= '-1';
				$params['mid']				= $this->managerInfo['manager_id'];
			}
 			$params['r_date']		= date("Y-m-d H:i:s");
			$params['m_date']		= date("Y-m-d H:i:s");
			$params['test11']		= $requestPost['test11'];
			$params["ip"]				= $_SERVER['REMOTE_ADDR'];
			$params["agent"]				= $_SERVER['HTTP_USER_AGENT'];

			$_REQUEST['tx_attach_files'] = (!empty($requestPost['tx_attach_files'])) ? $requestPost['tx_attach_files']:'';
			$params['contents'] = adjustEditorImages($params['contents'], $this->Boardmodel->upload_src);// /data/tmp 임시폴더변경 /data/editor
			if($this->_is_mobile_agent){//모바일인경우 text
				$params['contents'] = nl2br($params['contents']);
			}

			//첨부파일추가
			foreach($_FILES as $key => $value)
			{
				for ( $num=0;$num<count($_FILES['file_info']['name']);$num++) {
					if( ! empty($value['name'][$num])){
						$folder			= $this->Boardmodel->upload_path;
						$tmpname	= $value['tmp_name'][$num];
						$file_ext		= end(explode('.', $value['name'][$num]));//확장자추출
						$file_name	= str_replace(" ", "", (substr(microtime(), 2, 6))).'.'.$file_ext;
						$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
						$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
						$saveFile		= $folder.$file_name;
						$tmp = getimagesize($value['tmp_name']);
						if(!$tmp['mime']){
							$_FILES['Filedata']['type'] = $file_ext;//확장자추출
						}else{
							$_FILES['Filedata']['type'] = $tmp['mime'];
						}


						$fileresult = board_upload($key, $file_name, $folder, $conf, $saveFile, $num);//status  error, fileInfo
						if( $fileresult['status'] == 1 ) {

							if(is_array($realfilename)) {
								$usefile = false;
								foreach($realfilename as $realfile) {
									$realfilear = @explode("^^",$realfile);
									if($realfilear[0] == $file_name) {$usefile=true;break;}
								}
								if(!$usefile) {
									$realfilename[] = $file_name."^^".$value['name'][$num]."^^".$value['size'][$num]."^^".$value['type'][$num];
								}
							} else {
								$realfilename[] = $file_name."^^".$value['name'][$num]."^^".$value['size'][$num]."^^".$value['type'][$num];
							}

						}
					}
				}
			}

			//에딧터이미지파일용
			$filenamenumber=0;
			if(!empty($requestPost['tx_attach_files'])) {
				if(is_array($requestPost['tx_attach_files'])){

					foreach($requestPost['tx_attach_files'] as $tx_attach_file){
						$editerimg = end(explode('/', $tx_attach_file));//확장자추출
						if(preg_match("/[\xA1-\xFE\xA1-\xFE]/",$editerimg)) {//데이타이전->한글파일명처리
							$editerimgkorean = iconv('utf-8','cp949',$editerimg);
						}

						$client_name = ($requestPost['tx_attach_files_name'][$filenamenumber])?$requestPost['tx_attach_files_name'][$filenamenumber]:$editerimg;
						if( @is_file($this->Boardmodel->upload_path.$editerimg) ||  @is_file($this->Boardmodel->upload_path.$editerimgkorean) ) {

							@rename($this->Boardmanager->board_tmp_dir.'_thumb_'.$editerimg,$this->Boardmodel->upload_path.'/'.'_thumb_'.$editerimg);//파일복사
													@rename($this->Boardmanager->board_tmp_dir.'_thumb_'.$editerimgkorean,$this->Boardmodel->upload_path.'/'.'_thumb_'.$editerimgkorean);//파일복사

							$filesizetmp = @filesize($this->Boardmodel->upload_path.$editerimg);
							$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$editerimg);
							if(!$filetypetmp['mime']){
								$typefile =end(explode('.', $editerimg));//확장자추출
							}else{
								$typefile =$filetypetmp['mime'];
							}


							if(is_array($realfilename)) {
								$usefile = false;
								foreach($realfilename as $realfile) {
									$realfilear = @explode("^^",$realfile);
									if($realfilear[0] == $editerimg) {$usefile=true;break;}
								}
								if(!$usefile) {
									$realfilename[] = $editerimg."^^".$client_name."^^".$filesizetmp."^^".$typefile;
								}
							} else {
								$realfilename[] = $editerimg."^^".$client_name."^^".$filesizetmp."^^".$typefile;
							}
						}else{
							if ( @is_file($this->Boardmanager->board_tmp_dir.$editerimg) || @is_file($this->Boardmanager->board_tmp_dir.$editerimgkorean) ) {

								@rename($this->Boardmanager->board_tmp_dir.$editerimg,$this->Boardmodel->upload_path.'/'.$editerimg);//파일복사
								@rename($this->Boardmanager->board_tmp_dir.'_thumb_'.$editerimg,$this->Boardmodel->upload_path.'/'.'_thumb_'.$editerimg);//파일복사

								@rename($this->Boardmanager->board_tmp_dir.$editerimgkorean,$this->Boardmodel->upload_path.'/'.$editerimgkorean);//파일복사
								@rename($this->Boardmanager->board_tmp_dir.'_thumb_'.$editerimgkorean,$this->Boardmodel->upload_path.'/'.'_thumb_'.$editerimgkorean);//파일복사


								$filesizetmp = @filesize($this->Boardmodel->upload_path.$editerimg);
								$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$editerimg);
								if(!$filetypetmp['mime']){
									$typefile =end(explode('.', $editerimg));//확장자추출
								}else{
									$typefile =$filetypetmp['mime'];
								}


								if(is_array($realfilename)) {
									$usefile = false;
									foreach($realfilename as $realfile) {
										$realfilear = @explode("^^",$realfile);
										if($realfilear[0] == $editerimg) {$usefile=true;break;}
									}
									if(!$usefile) {
										$realfilename[] = $editerimg."^^".$client_name."^^".$filesizetmp."^^".$typefile;
									}
								} else {
									$realfilename[] = $editerimg."^^".$client_name."^^".$filesizetmp."^^".$typefile;
								}
							}
						}
						$filenamenumber++;
					}
				}
			}

			if(isset($realfilename)){
				$params['upload'] = @implode("|",$realfilename);
			}else{
				$params['upload'] = '';//초기화
			}

			$params['tel1'] = $requestPost['board_sms_hand'];
			$params['email'] = $requestPost['board_sms_email'];

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
						}elseif($k == '3' ){//
							$params['person_tel1']	= $subdata;
						}elseif($k == '4' ){//
							$params['person_tel2']	= $subdata;
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
			}

			//동영상연동
			if($requestPost['file_key_w']) $params['file_key_w'] = $requestPost['file_key_w'];//웹 인코딩 코드
			if($requestPost['file_key_i']) $params['file_key_i'] = $requestPost['file_key_i'];//스마트폰 인코딩 코드
			if($this->session->userdata('boardvideotmpcode') && $params['file_key_w'] )
				$params['videotmpcode'] = $this->session->userdata('boardvideotmpcode');//코드

			if(  BOARDID == 'onlinepromotion' || BOARDID == 'offlinepromotion'  ){//online, offline promotion
				$params['m_date']		= (isset($requestPost['start_date']))?($requestPost['start_date']." 00:00:00"):'0000-00-00 00:00:00';
				$params['d_date']		= (isset($requestPost['end_date']))?($requestPost['end_date']." 23:59:59"):'0000-00-00 00:00:00';
				$params['adddata'] 				= "_thumb_".$requestPost['adddata'];
				if(!empty($requestPost['adddata']) && @is_file(ROOTPATH."data/tmp/".$params['adddata'])) {//rename
					@rename(ROOTPATH."data/tmp/".$params['adddata'], $this->Boardmodel->upload_path."/".$params['adddata']);
					@chmod($this->Boardmodel->upload_path."/".$params['adddata'],0707);
				}else{
					if($requestPost['adddata']) $params['adddata'] 				= $requestPost['adddata'];
				}
			}

			//상품후기/문의->상품정보변경
			if ( isset($requestPost['displayGoods']) && is_array($requestPost['displayGoods'])) {
				$params['goods_seq']				=  implode(",",$requestPost['displayGoods']);
			}

			if($requestPost['displayGoods']) {//상품관련
				$this->load->model('goodsmodel');
				foreach($requestPost['displayGoods'] as $displayGoods){
					$goods = $this->goodsmodel->get_goods($displayGoods);
					$providerseq[] = $goods['provider_seq'];
				}
				$params['provider_seq']				=  (isset($providerseq) && is_array($providerseq))?implode(",",$providerseq):'';
			}

			if( $requestPost['notice']){
				$params['onlynotice']			= ($requestPost['onlynotice'])?$requestPost['onlynotice']:0;//공지영역만 노출여부
			}else{
				$params['onlynotice']			=	0;//공지영역만 노출여부
			}

			$result = $this->Boardmodel->data_write($params);
			if($result) {
				$newseq = $result;

				//동영상관리
				$this->load->model('videofiles');
				if( $params['file_key_w'] ){
					$videofiles['file_key_w']			= $params['file_key_w'];
					$videofiles['parentseq']			= $newseq;
					$videofiles['type']						= BOARDID;
					$videofiles['upkind']					= 'board';
					$this->videofiles->videofiles_modify_key($videofiles);
				}

				if( BOARDID == 'goods_review' ) {//상품후기 건수/평점
					goods_review_count($params, $newseq);
				}elseif( BOARDID == 'goods_qna' ) {//상품문의 건수
					goods_qna_count($params, $newseq);
				}

				if(!empty($requestPost['newcategory']) && $requestPost['category']=='newadd'){
					$upmanagerparams['category']		= $manager['category'].",".$requestPost['newcategory'];
					$upmanagerparams['category']		= str_replace(",,",",",$upmanagerparams['category']);
					//카테고리추가하기
				}

				//게시글수save
				$upmanagerparams['totalnum']		= $manager['totalnum']+1;
				$result = $this->Boardmanager->manager_item_save($upmanagerparams,BOARDID);//게시글증가

				//공지 Boardindex
				$idxparams['hidden']	= $params['hidden'];//비밀글여부
				$idxparams['notice']		= $params['notice'];//공지여부
				if( $params['notice']){
					$idxparams['onlynotice']			= ($requestPost['onlynotice'])?$requestPost['onlynotice']:0;//공지영역만 노출여부
					$idxparams['onlynotice_sdate']		= $requestPost['onlynotice_sdate'];//공지노출 시작일
					$idxparams['onlynotice_edate']		= $requestPost['onlynotice_edate'];//공지노출 완료일
				}else{
					$idxparams['onlynotice']			=	0;//공지영역만 노출여부
					$idxparams['onlynotice_sdate']		= '';//공지노출 시작일
					$idxparams['onlynotice_edate']		= '';//공지노출 완료일
				}
				$idxparams['gid']			= $params['gid'];//고유번호
				$idxparams['boardid']	= $params['boardid'];//id
				$this->Boardindex->idx_write($idxparams);

				if ($params['gid'] == '100000000.00')
				{
					$this->Boardmodel->get_data_optimize();
					$this->Boardindex->get_data_optimize();
				}

				//$sendsmscase = ( BOARDID == 'goods_qna')?'qna':(BOARDID == 'mbqna')?'cs':'board_reply';
				if ( !empty($requestPost['board_sms']) && !empty($requestPost['board_sms_hand']) && $requestPost['reply'] ) {//답변시
					$smsparams['msg']		= $requestPost["sms_content1"];
					$this->load->model('membermodel');
					$minfo = $this->membermodel->get_member_data($parentdata['mseq']);
					$smsparams['userid']			 = ($minfo['member_seq'])?$minfo['userid']:$parentdata['name'];//비회원은 작성자명
					$smsparams['user_name']	 = ($minfo['member_seq'])?$minfo['user_name']:$parentdata['name'];//작성자명

					$smsparams['board_name'] = $manager['name'];//게시판명

					$commonSmsData[BOARDID."_reply"]['phone'][] = $requestPost['board_sms_hand'];
					$commonSmsData[BOARDID."_reply"]['mid'][] = $minfo['userid'];
					$commonSmsData[BOARDID."_reply"]['params'][] = $smsparams;

					if(count($commonSmsData) > 0){
						commonSendSMS($commonSmsData);
					}

					$params['tel1'] = $requestPost['board_sms_hand'];
				}

				//회원정보체크
				$this->load->model('membermodel');
				if($parentdata['mseq']) $emoneyminfo = $this->membermodel->get_member_data($parentdata['mseq']);//본래글작성자체크

				if(!empty($emoneyminfo) && $requestPost['board_emoney'] && $requestPost['board_memo']) { //회원정보체크
					if( BOARDID == 'goods_review' ) {//상품후기
						$emoneyboardparams['type']			= 'goods_review';
					}else{
						$emoneyboardparams['type']			= 'board_'.BOARDID;//table
					}
					$emoneyboardparams['gb']				= 'plus';

					$emoneyboardparams['goods_review']	= (isset($requestPost['seq']) ) ?$requestPost['seq']:$newseq;//답변시에는 본래글에
					$emoneyboardparams['member_seq']	= $emoneyminfo['member_seq'];
					$emoneyboardparams['manager_seq']	= $this->managerInfo['manager_seq'];

					$emoneyboardparams['emoney']		= $requestPost['board_emoney'];
					$emoneyboardparams['memo']		= $requestPost['board_memo'];
					if($requestPost['board_reserve_select']=='year'){
						$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$requestPost['board_reserve_year']));
					}else if($requestPost['board_reserve_select']=='direct'){
						$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$requestPost['board_reserve_direct'], date("d"), date("Y")));
					}
					$emoneyboardparams['limit_date']	= $limit_date;
					$this->membermodel->emoney_insert($emoneyboardparams, $emoneyminfo['member_seq']);
				}

				$sendemailcase = ( BOARDID == 'goods_qna' || BOARDID == 'mbqna' )?'cs':( BOARDID == bulkorder )?'bulkorder':'board_reply';
				if ( !empty($requestPost['board_email']) && !empty($requestPost['board_sms_email']) && $requestPost['reply'] ) {//답변시

					$emailparams['subject']		= $parentdata["subject"];
					$emailparams['contents']		= $parentdata["contents"];
					$emailparams['r_date']		= $parentdata["r_date"];

					$emailparams['re_subject']		= (!empty($requestPost['re_subject']))?$requestPost['re_subject']:$requestPost["subject"];
					$emailparams['re_contents']		= (!empty($params["re_contents"]))?$params["re_contents"]:$params["contents"];
					$emailparams['re_date']		= date("Y-m-d H:i:s");
					$this->load->model('membermodel');
					$emailparamsinfo = $this->membermodel->get_member_data($parentdata['mseq']);
					$emailparams['userid']			 = ($emailparamsinfo['member_seq'])?$emailparamsinfo['userid']:$parentdata['name'];
					$emailparams['user_name'] = ($emailparamsinfo['member_seq'])?$emailparamsinfo['user_name']:$parentdata['name'];
					$email = sendMail($requestPost['board_sms_email'], $sendemailcase, $emailparamsinfo['userid'] , $emailparams);
				}

				$this->session->unset_userdata('backtype');
				$requestPost['backtype'] = (!empty($requestPost['backtype']))?($requestPost['backtype']):'list';
				$this->session->set_userdata('backtype',$requestPost['backtype']);
				if( defined('__SELLERADMIN__') === true ) {
					if( BOARDID == 'gs_seller_qna' ) {//입점사문의게시판
						$sendsmscase = 'gs_seller_qna_write';
						$this->load->model('membermodel');

						$commonSmsData[$sendsmscase]['phone'][] = $requestPost['tel1'];
						$commonSmsData[$sendsmscase]['mid'][] = $this->providerInfo['provider_id'];
						$commonSmsData[$sendsmscase]['params'][] = '';

						if(count($commonSmsData) > 0){
							commonSendSMS($commonSmsData);
						}
					}
				}

				if( BOARDID == 'gs_seller_notice' ) {
					push_for_admin(array(
						'kind'			=> 'gs_seller_notice',
						'unique'		=> $newseq,
						'admin_done'	=> 1,
						'provider_list'	=> array(1)
					));
				}

				if($requestPost['backtype'] == 'list') {
					$callback = ($requestPost['returnurl'])?"parent.document.location.href='".$requestPost['returnurl']."';":"parent.document.location.href='".$this->Boardmanager->realboardurl.BOARDID."';";
				} elseif($requestPost['backtype'] == 'view') {
					$callback = ($requestPost['returnurl'])?"parent.boardaddFormDialog('".$requestPost['returnurl']."&seq=".$newseq."', '80%', '800', '".$manager['name']." 게시글 보기','false');":"parent.boardaddFormDialog('".$this->Boardmanager->realboardviewurl.BOARDID."&seq=".$newseq."', '80%', '800', '".$manager['name']." 게시글 보기','false');";
				} else {
					$callback = "parent.boardaddFormDialog('".$this->Boardmanager->realboardwriteurl.BOARDID."', '90%', '800', '".$manager['name']." 게시글 등록','false');";
				}
				openDialogAlert("게시글을 등록 하였습니다.",400,140,'parent',$callback);
			}else{
				openDialogAlert("게시글 등록에 실패되었습니다.",400,140,'parent','');
			}
			exit;
		}

		/* 게시글수정 */
		elseif($mode == 'board_modify') {
			$requestPost = $this->input->post();
			// 에디터 내용은 xss 필터링에서 제외
			$requestPost['contents'] = $this->input->post('contents', false);
			$requestPost['re_contents'] = $this->input->post('re_contents', false);


			if( $requestPost['notice'] && $requestPost['onlynotice'] == 1 ){
				if(strtotime($requestPost['onlynotice_sdate']) > strtotime($requestPost['onlynotice_edate'])){
					$callback = "parent.$('#onlynotice_edate').focus();";
					openDialogAlert("기간 종료일이 시작일보다 이후에 있어야 합니다.<br/>날짜를 조정해 주세요.",450,180,'parent',$callback);
					exit;
				}
			}

			if( $requestPost['reply'] ) {//답변시
				if($requestPost['re_contents'] == '<p><br></p>') {
					$requestPost['re_contents'] = "";
				}

				if( defined('__SELLERADMIN__') === true ) {
					$params['re_mseq']	= '-'.$this->providerInfo['provider_seq'];
					$params['re_mtype']		= 'p';
				}else{
					$params['re_mseq']	= '-1';
					$params['re_mtype']		= 'r';
				}

				### Validation
				$this->validation->set_rules('re_subject', '제목','trim|required|xss_clean');
				$this->validation->set_rules('re_contents', '내용','trim|required');
			}else{
				if($requestPost['contents'] == '<p><br></p>') {
					$requestPost['contents'] = "";
				}

				if( defined('__SELLERADMIN__') === true ) {
					$params['mtype']			= 'p';
				}else{
					$params['mtype']			= 'r';
				}

				### Validation
				$this->validation->set_rules('subject', '제목','trim|required|xss_clean');
				$this->validation->set_rules('contents', '내용','trim|required');
			}

			if($this->validation->exec()===false){
			   $err = $this->validation->error_array;
			   $callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();parent.setDefaultText();parent.submitck();";
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

			### //넘어온 추가항목 seq
			if( BOARDID == 'bulkorder'  ||  BOARDID == 'goods_review' ) {
				$label_pr = $requestPost['label'];
				$label_sub_pr = $requestPost['labelsub'];
				$label_required = $requestPost['required'];
				foreach($label_pr as $l => $data){$label_arr[]=$l;}
			}

			$parentsql['whereis']	= ' and seq= "'.$requestPost['seq'].'" ';
			$parentsql['select']		= ' seq, gid, comment, upload, depth , mseq, subject, contents, r_date, parent, file_key_w, name , goods_seq';
			$parentdata = $this->Boardmodel->get_data($parentsql);//게시판목록
			if(empty($parentdata)) {
				$callback = "parent.document.location.href='".$this->Boardmanager->realboardurl.BOARDID."';";
				openDialogAlert("존재하지 않는 게시물입니다.",400,140,'parent',$callback);
				exit;
			}

			$params['boardid']		=  BOARDID;
			$params['notice']			=  if_empty($requestPost, 'notice', '0');//공지
			$params['hidden']		=  if_empty($requestPost, 'hidden', '0');//비밀글

			if( BOARDID == 'gs_seller_notice' ) {
				$params['onlypopup']	=  if_empty($requestPost, 'onlypopup', '0');//팝업여부
				$params['onlypopup_sdate']		= $requestPost['onlypopup_sdate'];//팝업시작일
				$params['onlypopup_edate']		= $requestPost['onlypopup_edate'];//팝업완료일
			}

			$params['subject']		=  $requestPost['subject'];
			$params['editor']			=  ($requestPost['daumedit'])?1:0;//모바일

			if( $requestPost['reply'] ) {//답변시
				$params['name']			=  (!empty($requestPost['name']))?$requestPost['name']:'';
			}

			if($requestPost['reply'] != 'y'){
				$params['category']		= (!empty($requestPost['category']))?htmlspecialchars($requestPost['category']):'';
			}

			if( !empty($requestPost['pw']) ) {
				$params['pw']  = Password::encrypt($requestPost['pw']);
			}elseif( !empty($requestPost['oldpw']) ) {
				$params['pw']  = $requestPost['oldpw'];
			}


			if(!empty($requestPost['email'])) $params['email']=($requestPost['email']);
			if(!empty($requestPost['tel1'])) $params['tel1']=($requestPost['tel1']);
			if(!empty($requestPost['tel2'])) $params['tel2']=($requestPost['tel2']);

			if(!empty($requestPost['board_sms_hand'])) $params['tel1']=($requestPost['board_sms_hand']);
			if(!empty($requestPost['board_sms_email'])) $params['email']=($requestPost['board_sms_email']);


			if(!empty($requestPost['board_sms']))		$params['rsms']='Y';
			if(!empty($requestPost['board_email']))	$params['remail']='Y';

			if( !empty($requestPost['score']) ) $params['score']  = ($requestPost['score']);//값이 잇는경우에만 변경

			if(!empty($requestPost['re_contents']))  {
				$params['re_contents'] = $requestPost['re_contents'];//1:1문의 답변시
				$params['re_contents'] = adjustEditorImages($requestPost['re_contents'], $this->Boardmodel->upload_src);
				if($this->_is_mobile_agent){//모바일인경우 text
					$params['re_contents'] = nl2br($params['re_contents']);
				}
			}
			if(!empty($requestPost['re_subject'])) $params['re_subject']=($requestPost['re_subject']);
			if($requestPost['re_subject']) $params['re_date']		= date("Y-m-d H:i:s");
			if( defined('__SELLERADMIN__') === true ) {
				if($parentdata['mseq']  < -1  )$params['m_date']		= date("Y-m-d H:i:s");
			}else{
				if($parentdata['mseq'] == '-1' )$params['m_date']		= date("Y-m-d H:i:s");
			}

			if( !($requestPost['re_subject']) && $parentdata['mseq'] == '-1' )  {//답변이 아닌경우
				$params["ip"]				= $_SERVER['REMOTE_ADDR'];
				$params["agent"]		= $_SERVER['HTTP_USER_AGENT'];
			}

			$_REQUEST['tx_attach_files'] = (!empty($requestPost['tx_attach_files'])) ? $requestPost['tx_attach_files']:'';

			//(/data/tmp 임시폴더에서 게시판폴더로 이동변경 $this->Boardmodel->upload_src
			$params['contents'] = adjustEditorImages($requestPost['contents'], $this->Boardmodel->upload_src);
			if($this->_is_mobile_agent){//모바일인경우 text
				$params['contents'] = nl2br($params['contents']);
			}

			//이미등록된 첨부파일 변경시
			if(!empty($requestPost['orignfile_info'])) {
				$oldfile = @explode("|",$parentdata['upload']);
				for ( $num=0;$num<count($requestPost['orignfile_info']);$num++) {
					$oldrealfile = @explode("^^",$requestPost['orignfile_info'][$num]);
					if(@in_array($requestPost['orignfile_info'][$num],$oldfile) && @is_file($this->Boardmodel->upload_path.$oldrealfile[0]) && is_uploaded_file($_FILES['file_info']['tmp_name'][$num]) ){//기존위치에 수정시 변경

						if( ! empty($_FILES['file_info']['tmp_name'][$num])) {
							$folder			= $this->Boardmodel->upload_path;
							$tmpname	= $_FILES['file_info']['tmp_name'][$num];
							$file_ext		= end(explode('.', $_FILES['file_info']['name'][$num]));//확장자추출
							$file_name	= str_replace(" ", "", (substr(microtime(), 2, 6))).'.'.$file_ext;
							$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
							$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
							$saveFile		= $folder.$file_name;
							$tmp = getimagesize($_FILES['file_info']['tmp_name'][$num]);
							$_FILES['Filedata']['type'] = $tmp['mime'];

							$fileresult = board_upload('file_info', $file_name, $folder, $conf, $saveFile, $num);//status  error, fileInfo
							if( $fileresult['status'] == 1 ) {

								if(is_array($realfilename)) {
									$usefile = false;
									foreach($realfilename as $realfile) {
										$realfilear = @explode("^^",$realfile);
										if($realfilear[0] == $file_name) {$usefile=true;break;}
									}
									if(!$usefile) {
										$realfilename[] = $file_name."^^".$_FILES['file_info']['name'][$num]."^^".$_FILES['file_info']['size'][$num]."^^".$_FILES['file_info']['type'][$num];
									}
								} else {
									$realfilename[] = $file_name."^^".$_FILES['file_info']['name'][$num]."^^".$_FILES['file_info']['size'][$num]."^^".$_FILES['file_info']['type'][$num];
								}

								//@unlink($this->Boardmodel->upload_path.$oldrealfile[0]);//기존위치의 파일삭제
								//@unlink($this->Boardmodel->upload_path.'_thumb_'.$oldrealfile[0]);//기존위치의 파일삭제
							}
						}
					}

					elseif ( @in_array($requestPost['orignfile_info'][$num],$oldfile) && @is_file($this->Boardmodel->upload_path.$oldrealfile[0]) && !is_uploaded_file($_FILES['file_info']['tmp_name'][$num]) ) {
						$realfilename[] = $requestPost['orignfile_info'][$num];
					}
				}
			}

			//새로등록하는 첨부파일용
			foreach($_FILES as $key => $value)
			{
				for ( $num=0;$num<count($_FILES['file_info']['name']);$num++) {
					if(  !$requestPost['orignfile_info'][$num] && !empty($value['name'][$num])){
						$folder			= $this->Boardmodel->upload_path;
						$tmpname	= $value['tmp_name'][$num];
						$file_ext		= end(explode('.', $value['name'][$num]));//확장자추출
						$file_name	= str_replace(" ", "", (substr(microtime(), 2, 6))).'.'.$file_ext;
						$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
						$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
						$saveFile		= $folder.$file_name;

						$tmp = getimagesize($value['tmp_name']);
						if(!$tmp['mime']){
							$_FILES['Filedata']['type'] = $file_ext;//확장자추출
						}else{
							$_FILES['Filedata']['type'] = $tmp['mime'];
						}

						$fileresult = board_upload($key, $file_name, $folder, $conf, $saveFile, $num);//status  error, fileInfo
						if( $fileresult['status'] == 1 ) {

							if(is_array($realfilename)) {
								$usefile = false;
								foreach($realfilename as $realfile) {
									$realfilear = @explode("^^",$realfile);
									if($realfilear[0] == $file_name) {$usefile=true;break;}
								}
								if(!$usefile) {
									$realfilename[] = $file_name."^^".$value['name'][$num]."^^".$value['size'][$num]."^^".$value['type'][$num];
								}
							} else {
								$realfilename[] = $file_name."^^".$value['name'][$num]."^^".$value['size'][$num]."^^".$value['type'][$num];
							}
						}
					}
				}
			}

			//에딧터이미지파일용
			$filenamenumber=0;
			if(!empty($requestPost['tx_attach_files'])) {
				if(is_array($requestPost['tx_attach_files'])){
						//array_unique($requestPost['tx_attach_files']);array_unique($requestPost['tx_attach_files_name']);
					foreach($requestPost['tx_attach_files'] as $tx_attach_file){
						//$editerimg = preg_replace("/^\/data\/tmp\//","",$tx_attach_file);
						$editerimg = end(explode('/', $tx_attach_file));//확장자추출
						if(preg_match("/[\xA1-\xFE\xA1-\xFE]/",$editerimg)) {//데이타이전->한글파일명처리
							$editerimgkorean = iconv('utf-8','cp949',$editerimg);
						}

						$client_name = ($requestPost['tx_attach_files_name'][$filenamenumber])?$requestPost['tx_attach_files_name'][$filenamenumber]:$editerimg;
						if( @is_file($this->Boardmodel->upload_path.$editerimg) ||  @is_file($this->Boardmodel->upload_path.$editerimgkorean) ) {

							@rename($this->Boardmanager->board_tmp_dir.'_thumb_'.$editerimg,$this->Boardmodel->upload_path.'/'.'_thumb_'.$editerimg);//파일복사
							@rename($this->Boardmanager->board_tmp_dir.'_thumb_'.$editerimgkorean,$this->Boardmodel->upload_path.'/'.'_thumb_'.$editerimgkorean);//파일복사

							$filesizetmp = @filesize($this->Boardmodel->upload_path.$editerimg);
							$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$editerimg);
							if(!$filetypetmp['mime']){
								$typefile =end(explode('.', $editerimg));//확장자추출
							}else{
								$typefile =$filetypetmp['mime'];
							}


							if(is_array($realfilename)) {
								$usefile = false;
								foreach($realfilename as $realfile) {
									$realfilear = @explode("^^",$realfile);
									if($realfilear[0] == $editerimg) {$usefile=true;break;}
								}
								if(!$usefile) {
									$realfilename[] = $editerimg."^^".$client_name."^^".$filesizetmp."^^".$typefile;
								}
							} else {
								$realfilename[] = $editerimg."^^".$client_name."^^".$filesizetmp."^^".$typefile;
							}
						}else{
							if ( @is_file($this->Boardmanager->board_tmp_dir.$editerimg) || @is_file($this->Boardmanager->board_tmp_dir.$editerimgkorean) ) {
								@rename($this->Boardmanager->board_tmp_dir.$editerimg,$this->Boardmodel->upload_path.'/'.$editerimg);//파일복사
								@rename($this->Boardmanager->board_tmp_dir.'_thumb_'.$editerimg,$this->Boardmodel->upload_path.'/_thumb_'.$editerimg);//파일복사

								@rename($this->Boardmanager->board_tmp_dir.$editerimgkorean,$this->Boardmodel->upload_path.'/'.$editerimgkorean);//파일복사
								@rename($this->Boardmanager->board_tmp_dir.'_thumb_'.$editerimgkorean,$this->Boardmodel->upload_path.'/_thumb_'.$editerimgkorean);//파일복사

								$filesizetmp = @filesize($this->Boardmodel->upload_path.$editerimg);
								$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$editerimg);
								if(!$filetypetmp['mime']){
									$typefile =end(explode('.', $editerimg));//확장자추출
								}else{
									$typefile =$filetypetmp['mime'];
								}


								if(is_array($realfilename)) {
									$usefile = false;
									foreach($realfilename as $realfile) {
										$realfilear = @explode("^^",$realfile);
										if($realfilear[0] == $editerimg) {$usefile=true;break;}
									}
									if(!$usefile) {
										$realfilename[] = $editerimg."^^".$client_name."^^".$filesizetmp."^^".$typefile;
									}
								} else {
									$realfilename[] = $editerimg."^^".$client_name."^^".$filesizetmp."^^".$typefile;
								}
							}
						}
						$filenamenumber++;
					}
				}
			}

			if($requestPost['reply'] == 'y' && (BOARDID == 'store_reservation' || BOARDID == 'mbqna' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder' || BOARDID == 'gs_seller_qna') ) {//답변시 원글의 첨부파일포함되도록
				if(isset($realfilename)){
					$params['re_upload'] = @implode("|",$realfilename);
				}else{
					$params['re_upload'] = '';//초기화
				}
			}else{
				if(isset($realfilename)){
					$params['upload'] = @implode("|",$realfilename);
				}else{
					$params['upload'] = '';//초기화
				}
			}

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
							$params['email']				=  $params['person_email'];
						}elseif($k == '3' ){//
							$params['person_tel1']	= $subdata;
						}elseif($k == '4' ){//
							$params['person_tel2']	= $subdata;
							$params['tel1']				=  $params['person_tel2'];
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

			//상품후기/문의-> 답변시상품정보변경
			if (isset($requestPost['displayGoods']) && is_array($requestPost['displayGoods'])) {
				$params['goods_seq']				=  implode(",",$requestPost['displayGoods']);
			}

			if($requestPost['displayGoods']) {//상품관련
				$this->load->model('goodsmodel');
				foreach($requestPost['displayGoods'] as $displayGoods){
					$goods = $this->goodsmodel->get_goods($displayGoods);
					$providerseq[] = $goods['provider_seq'];
				}
				$params['provider_seq']				=  (isset($providerseq) && is_array($providerseq))?implode(",",$providerseq):'';
			}

			//동영상
			if($requestPost['video_del'] == 1) $params['file_key_w'] = '';//원본파일코드초기화
			if($requestPost['file_key_w']) $params['file_key_w'] = $requestPost['file_key_w'];//웹 인코딩 코드
			if($requestPost['file_key_i']) $params['file_key_i'] = $requestPost['file_key_i'];//스마트폰 인코딩 코드

			if($this->session->userdata('boardvideotmpcode') && $params['file_key_w'] )
				$params['videotmpcode'] = $this->session->userdata('boardvideotmpcode');//코드

			if(  BOARDID == 'onlinepromotion' || BOARDID == 'offlinepromotion'  ){//online, offline promotion
				$params['m_date']		= (isset($requestPost['start_date']))?($requestPost['start_date']." 00:00:00"):'0000-00-00 00:00:00';
				$params['d_date']		= (isset($requestPost['end_date']))?($requestPost['end_date']." 23:59:59"):'0000-00-00 00:00:00';
				$params['adddata'] 				= "_thumb_".$requestPost['adddata'];
				if(!empty($requestPost['adddata']) && @is_file(ROOTPATH."data/tmp/".$params['adddata'])) {//rename
					@rename(ROOTPATH."data/tmp/".$params['adddata'], $this->Boardmodel->upload_path."/".$params['adddata']);
					@chmod($this->Boardmodel->upload_path."/".$params['adddata'],0707);
				}else{
					if($requestPost['adddata']) $params['adddata'] 				= $requestPost['adddata'];
				}
			}


			if( $requestPost['reply'] == 'y' && (BOARDID == 'store_reservation' || BOARDID == 'mbqna' || BOARDID == 'goods_qna'  || BOARDID == 'bulkorder' || BOARDID == 'gs_seller_qna' || BOARDID == 'naverpay_qna'  ) ) {
				unset($params['contents'], $params['subject']);//답변시 원본글  제목,내용 변경안되도록
			}

			if( $requestPost['notice']){
				$params['onlynotice']			= ($requestPost['onlynotice'])?$requestPost['onlynotice']:0;//공지영역만 노출여부
			}else{
				$params['onlynotice']			=	0;//공지영역만 노출여부
			}


			if ($requestPost['reply'] == 'y') {

				if( BOARDID == 'naverpay_qna' ) {
					if(!$requestPost['npay_inquiry_id']){
						openDialogAlert("해당글은 Npay문의건이 아닙니다.",400,140,'parent');
						exit;
					}
					$this->load->model("naverpaymodel");
					$npay_res = $this->naverpaymodel->set_qnswer_customer_inquiry($requestPost);

					if(!$npay_res){
						openDialogAlert("Npay 문의글 답변 저장 오류입니다.",400,140,'parent');
						exit;
					}
				}

				if( BOARDID == 'talkbuy_qna' ) {
					if(!$requestPost['talkbuy_inquiry_id']){
						openDialogAlert("해당글은 톡체크아웃 문의건이 아닙니다.",400,140,'parent');
						exit;
					}
					$this->load->library("talkbuylibrary");
					$talkbuy_res = $this->talkbuylibrary->set_qna_answer($requestPost);

					if($talkbuy_res['success'] != true){
						$errorMessageDetail = '사유 : 톡체크아웃 관리자센터에서 ' . $talkbuy_res['message'];
						openDialogAlert("톡체크아웃 답변을 작성할 수 없습니다.<br/>" . $errorMessageDetail,500,180,'parent');
						exit;
					} else {
						// 답변 id 저장
						$params['talkbuy_answer_id'] = $talkbuy_res['answerId'];
					}
				}
			}

			$result = $this->Boardmodel->data_modify($params);
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

				if( BOARDID == 'goods_review' ) {//상품후기 건수/평점
					if( $params['goods_seq'] != $parentdata['goods_seq'] ) {//변경된 경우
						$oldparams['goods_seq'] = $parentdata['goods_seq'];//이전상품업데이트
						goods_review_count($oldparams, $parentdata['seq']);
						goods_review_count($params, $parentdata['seq']);//변경상품업데이트
					}
				}elseif( BOARDID == 'goods_qna' ) {//상품문의 건수
					if( $params['goods_seq'] != $parentdata['goods_seq'] ) {//변경된 경우
						$oldparams['goods_seq'] = $parentdata['goods_seq'];
						goods_qna_count($oldparams, $parentdata['seq']);
						goods_qna_count($params, $parentdata['seq']);
					}
				}

				if(!empty($requestPost['newcategory']) && $requestPost['category']=='newadd'){
					$upmanagerparams['category']		= $manager['category'].",".$requestPost['newcategory'];
					$upmanagerparams['category']		= str_replace(",,",",",$upmanagerparams['category']);
					$result = $this->Boardmanager->manager_item_save($upmanagerparams,BOARDID);//카테고리변경
					//카테고리추가하기
				}

				//공지 Boardindex
				$idxsc['select']			= ' gid ';
				$idxsc['whereis']		= ' and gid = "'.$parentdata['gid'].'" and boardid = "'.$params['boardid'].'" ';
				$idxdata = $this->Boardindex->get_data($idxsc);//
				$idxparams['hidden']	= $params['hidden'];//비밀글여부
				$idxparams['notice']		= $params['notice'];//공지여부
				if( $params['notice']){
					$idxparams['onlynotice']			= ($requestPost['onlynotice'])?$requestPost['onlynotice']:0;//공지영역만 노출여부
					$idxparams['onlynotice_sdate']		= $requestPost['onlynotice_sdate'];//공지노출 시작일
					$idxparams['onlynotice_edate']		= $requestPost['onlynotice_edate'];//공지노출 완료일
				}else{
					$idxparams['onlynotice']			=	0;//공지영역만 노출여부
					$idxparams['onlynotice_sdate']		= '';//공지노출 시작일
					$idxparams['onlynotice_edate']		= '';//공지노출 완료일
				}
				$idxparams['gid']			= $parentdata['gid'];//고유번호
				$idxparams['boardid']	= $params['boardid'];//id

				if( $idxdata ) {
				$this->Boardindex->idx_modify($idxparams);
				}else{
					$this->Boardindex->idx_write($idxparams);
				}

				if( BOARDID == 'bulkorder' || BOARDID == 'gs_seller_qna' ) {//대량구매 or 입점문의 게시판
					$sendsmscase = BOARDID.'_reply';
				}else{
					$sendsmscase = ( BOARDID == 'goods_qna')?'qna':(BOARDID == 'mbqna')?'cs':'board_reply';
				}
				// 본래 수정시에는 SMS 안가게끔 되어있지만 board_sms 체크시 SMS 발송 되도록 수정 :: 2015-08-24 lwh - 운영팀 류혜미 요청
				if ( !empty($requestPost['board_sms']) && !empty($requestPost['board_sms_hand']) && ($requestPost['reply'] || $requestPost['board_sms']) ) {//답변시
					$smsparams['msg']		= $requestPost["sms_content1"];
					$this->load->model('membermodel');
					$smsparams = $this->membermodel->get_member_data($parentdata['mseq']);
					$smsparams['board_name'] = $manager['name'];
					$smsparams['userid']			 = ($smsparams['member_seq'])?$smsparams['userid']:$parentdata['name'];//비회원은 작성자명
					$smsparams['user_name']	 = ($smsparams['member_seq'])?$smsparams['user_name']:$parentdata['name'];//작성자명
					$commonSmsData[BOARDID."_reply"]['phone'][] = $requestPost['board_sms_hand'];
					$commonSmsData[BOARDID."_reply"]['mid'][] = $smsparams['userid'];
					$commonSmsData[BOARDID."_reply"]['params'][] = $smsparams;

					if(count($commonSmsData) > 0){
						commonSendSMS($commonSmsData);
					}

					$params['tel1'] = $requestPost['board_sms_hand'];
				}

				//회원정보체크
				$this->load->model('membermodel');
				if($parentdata['mseq']) $emoneyminfo = $this->membermodel->get_member_data($parentdata['mseq']);//본래글작성자체크
				if(!empty($emoneyminfo) && $requestPost['board_emoney'] && $requestPost['board_memo']) { //회원정보체크
					if( BOARDID == 'goods_review' ) {//상품후기
						$emoneyboardparams['type']			= 'goods_review';
					}else{
						$emoneyboardparams['type']			= 'board_'.BOARDID;//table
					}
					$emoneyboardparams['gb']				= 'plus';

					$emoneyboardparams['goods_review']= $parentdata['seq'];//본래글
					$emoneyboardparams['member_seq']	= $emoneyminfo['member_seq'];
					$emoneyboardparams['manager_seq']	= $this->managerInfo['manager_seq'];

					$emoneyboardparams['emoney']		= $requestPost['board_emoney'];
					$emoneyboardparams['memo']		= $requestPost['board_memo'];
					if($requestPost['board_reserve_select']=='year'){
						$limit_date = date("Y-m-d", mktime(0,0,0,12, 31, date("Y")+$requestPost['board_reserve_year']));
					}else if($requestPost['board_reserve_select']=='direct'){
						$limit_date = date("Y-m-d", mktime(0,0,0,date("m")+$requestPost['board_reserve_direct'], date("d"), date("Y")));
					}
					$emoneyboardparams['limit_date']	= $limit_date;
					$this->membermodel->emoney_insert($emoneyboardparams, $emoneyminfo['member_seq']);
				}

				if( ( BOARDID == 'gs_seller_qna' ) &&  !empty($requestPost['board_email']) && !empty($requestPost['board_sms_email']) ) {//입점문의 게시판
					$data['email']		= $requestPost['board_sms_email'];
					$data['title']				= (!empty($requestPost['re_subject']))?$requestPost['re_subject']:$params["subject"];
					$data['contents']		= (!empty($params["re_contents"]))?$params["re_contents"]:$params["contents"];
					$data['member_seq']		= $emoneyminfo['member_seq'];
					getSendMail($data);
				}else{
					switch(BOARDID){
						case	'goods_qna'	:
							//#30859 2019-03-20 ycg 상품 문의 답변 시 고객에게 이메일 전송 안되는 문제 수정
							$sendemailcase	= 'cs';
							break;
						case	'mbqna'	:
							$sendemailcase	= 'cs';
							break;
						case	'bulkorder'	:
							$sendemailcase	= 'bulkorder';
							break;
						default	:
							$sendemailcase	= 'board_reply';
							break;
					}

					if ( !empty($requestPost['board_email']) && !empty($requestPost['board_sms_email']) && $requestPost['reply'] ) {//답변시

						if($sendemailcase == 'board_reply'){//추가게시판의 답변은 부모글참조
							$reply_parentsql['whereis']	= ' and seq= "'.$parentdata['parent'].'" ';
							$reply_parentsql['select']		= ' seq, gid, comment, upload, depth , mseq, subject, contents, r_date ';
							$reply_parentdata = $this->Boardmodel->get_data($reply_parentsql);//게시판목록

							if($reply_parentdata) {
								$emailparams['subject']		= $reply_parentdata["subject"];
								$emailparams['contents']		= $reply_parentdata["contents"];
								$emailparams['r_date']		= $reply_parentdata["r_date"];
							}
						}else{
							$emailparams['subject']		= $parentdata["subject"];
							$emailparams['contents']		= $parentdata["contents"];
							$emailparams['r_date']		= $parentdata["r_date"];
						}

						$emailparams['re_subject']		= (!empty($requestPost['re_subject']))?$requestPost['re_subject']:$requestPost["subject"];
						$emailparams['re_contents']		= (!empty($params["re_contents"]))?$params["re_contents"]:$requestPost["contents"];
						$emailparams['goods_name']		= $requestPost['goods_name'];
						$emailparams['provider_email']	= $requestPost['cs_provider_email'];

						$emailparams['re_date']		= date("Y-m-d H:i:s");
						$this->load->model('membermodel');
						$emailparamsinfo = $this->membermodel->get_member_data($parentdata['mseq']);
						$emailparams['userid']			 = ($emailparamsinfo['member_seq'])?$emailparamsinfo['userid']:$parentdata['name'];
						$emailparams['user_name'] = ($emailparamsinfo['member_seq'])?$emailparamsinfo['user_name']:$parentdata['name'];
						$email = sendMail($requestPost['board_sms_email'], $sendemailcase, $emailparamsinfo['userid'] , $emailparams);
					}
				}

				## 입점사 문의 답변 작성시 푸시
				if	( BOARDID == 'gs_seller_qna' && $requestPost['reply'] == 'y' ) {
					$provider_seq = str_replace('-', '', $parentdata['mseq']);
					push_for_admin(array(
						'kind'			=> 'gs_seller_qna',
						'unique'		=> $parentdata['seq'],
						'admin_done'	=> 1,
						'provider_list'	=> array($provider_seq)
					));
				}

				$this->session->unset_userdata('backtype');
				$requestPost['backtype'] = (!empty($requestPost['backtype']))?($requestPost['backtype']):'list';
				$this->session->set_userdata('backtype',$requestPost['backtype']);

				if($requestPost['mainview']) {
					$callback =  "parent.document.location.reload();";
				}else{
					if($requestPost['backtype'] == 'list') {
						$callback = ($requestPost['returnurl'])?"parent.document.location.href='".$requestPost['returnurl']."';":"parent.document.location.href='".$this->Boardmanager->realboardurl.BOARDID."';";
					}elseif($requestPost['backtype'] == 'view'){
						$callback = ($requestPost['returnurl'])?"parent.boardaddFormDialog('".$requestPost['returnurl']."', '80%', '800', '".$manager['name']." 게시글 보기','false');":"parent.boardaddFormDialog('".$this->Boardmanager->realboardviewurl.BOARDID."&seq=".$requestPost['seq']."', '80%', '800', '".$manager['name']." 게시글 보기','false');";
					}else {
						$callback = '';
					}
				}

				if($requestPost['callPage'] == "crm") $callback = "parent.location.reload();";

				if ($requestPost['reply'] == 'y') {
					openDialogAlert("답변이 저장되었습니다.",400,140,'parent',$callback);
				} else {
					openDialogAlert("게시글을 수정하였습니다.",400,140,'parent',$callback);
				}
			}else{
				$callback = "";
				openDialogAlert("게시글 수정이 실패 되었습니다.",400,140,'parent',$callback);
			}
			exit;
		}

		/* 게시글삭제 */
		elseif (in_array($mode,['board_delete','report_delete'])) {
			$this->load->library('Board/BoardReportLibrary');

			$requestPost = $this->input->post();

			$deleteSeq = $requestPost['delseq'];

			if (is_numeric($deleteSeq) === false) {
				//존재하지 않는 게시물입니다.
				openDialogAlert(getAlert('et281'), 400, 140, 'parent', $callback);
				exit;
			}

			getmanagerauth('board_act', 'delete');
			$num = 0;
			$parentsql['whereis'] = ' and seq= "' . $deleteSeq . '" ';
			$parentsql['select'] = ' * ';
			$parentdata = $this->Boardmodel->get_data($parentsql); //게시판목록
			if (empty($parentdata)) {
				if(check_ajax_protocol()) {
					$return = [
						'result' => false,
						'msg' => '존재하지 않는 게시글입니다.'
					];
					echo json_encode($return);
					exit;
				} else {
					$callback = '';
					openDialogAlert('존재하지 않는 게시글입니다.', 400, 140, 'parent', $callback);
					exit;
				}
			}

			$replyor = '';
			$replysc['whereis'] = ' and gid > '.$parentdata['gid'].' and gid < '.(intval($parentdata['gid'])+1).' and parent = '.($parentdata['seq']).'  ';//답변여부
			$replyor = $this->Boardmodel->get_data_numrow($replysc);

			//게시글 삭제시 마일리지/포인트 회수 한번만!
			if( $parentdata['display'] != 1  && $parentdata['mseq'] ) {
				if( BOARDID == 'goods_review' ) {
					$this->_goods_review_less($manager, $parentdata);
				}else{
					$this->_board_less($manager, $parentdata);
				}
			}

			if ($replyor == 0 && $parentdata['comment'] == 0) {//답변과 댓글이 없는 경우 real 삭제
				$num++;
				$result = $this->Boardmodel->data_delete($deleteSeq); //게시글삭제
				if ($result) {
					$this->Boardindex->idx_delete($parentdata['gid']); //index 삭제
					$this->Boardscorelog->data_parent_delete($deleteSeq); //게시글평가제거

					if (BOARDID == 'goods_review') {//상품후기
						//상품정보 > 상품후기건수 차감
						if ($parentdata['goods_seq']) {
							$reviewparentdata['goods_seq'] = $parentdata['goods_seq'];
							goods_review_count($reviewparentdata, $parentdata['seq'], 'minus');
						}

						if ($parentdata['mseq']) {//회원정보체크
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

					//첨부파일삭제
					if (!empty($parentdata['upload'])) {
						$oldfile = @explode('|', $parentdata['upload']);
						for ($f = 0; $f < count($oldfile); $f++) {
							$oldrealfile = @explode('^^', $oldfile[$f]);
							if (@is_file($this->Boardmodel->upload_path . $oldrealfile[0])) {//기존위치에 수정시 변경
								@unlink($this->Boardmodel->upload_path . $oldrealfile[0]); //기존위치의 파일삭제
								@unlink($this->Boardmodel->upload_path . '_thumb_' . $oldrealfile[0]); //기존위치의 파일삭제
								@unlink($this->Boardmodel->upload_path . '_widget_thumb_' . $oldrealfile[0]); //기존위치의 파일삭제
							}
						}
					}

					//동영상테이블삭제
					if ($parentdata['videotmpcode']) {
						$this->videofiles->videofiles_delete_parentseq('board', BOARDID, $parentdata['seq']);
					}


					//게시글수 save
					$upmanagerparams['totalnum'] = $manager['totalnum'] - 1;
					$result = $this->Boardmanager->manager_item_save($upmanagerparams, BOARDID); //본래게시판의 게시글감소
					// 신고처리
					if ($mode === 'report_delete') {
						$arr = [
							'boardid' => BOARDID,
							'boardseq' => $deleteSeq,
							'boardtype' => 'board',
						];
						$this->boardreportlibrary->processReport($arr);
					}
					if(check_ajax_protocol()) {
						$return = [
							'result' => true,
							'msg' => '게시글을 삭제하였습니다.'
						];
						echo json_encode($return);
						exit;
					} else {
						$callback = 'parent.document.location.reload();';
						openDialogAlert('게시글을 삭제하였습니다.', 400, 140, 'parent', $callback);
					}
				} else {
					$callback = 'parent.document.location.reload();';
					openDialogAlert('게시글 삭제가 실패 되었습니다.', 400, 140, 'parent', $callback);
				}
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

					$this->Boardscorelog->data_parent_delete($deleteSeq); //게시글평가제거

					//첨부파일삭제
					if (!empty($parentdata['upload'])) {
						$oldfile = @explode('|', $parentdata['upload']);
						for ($i = 0; $i < count($oldfile); $i++) {
							$oldrealfile = @explode('^^', $oldfile[$i]);
							if (@is_file($this->Boardmodel->upload_path . $oldrealfile[0])) {//기존위치에 수정시 변경
								@unlink($this->Boardmodel->upload_path . $oldrealfile[0]); //기존위치의 파일삭제
								@unlink($this->Boardmodel->upload_path . '_thumb_' . $oldrealfile[0]); //기존위치의 파일삭제
								@unlink($this->Boardmodel->upload_path . '_widget_thumb_' . $oldrealfile[0]); //기존위치의 파일삭제
							}
						}
					}

					//동영상테이블삭제
					if ($parentdata['videotmpcode']) {
						$this->videofiles->videofiles_delete_parentseq('board', BOARDID, $parentdata['seq']);
					}
					// 신고처리
					if ($mode === 'report_delete') {
						$arr = [
							'boardid' => BOARDID,
							'boardseq' => $deleteSeq,
							'boardtype' => 'board',
						];
						$this->boardreportlibrary->processReport($arr);
					}
					if(check_ajax_protocol()) {
						$return = [
							'result' => true,
							'msg' => '게시글을 삭제하였습니다.'
						];
						echo json_encode($return);
						exit;
					} else {
						$callback = 'parent.document.location.reload();';
						openDialogAlert('게시글을 삭제하였습니다.', 400, 140, 'parent', $callback);
					}
				} else {
					$callback = 'parent.document.location.reload();';
					openDialogAlert('게시글 삭제가 실패 되었습니다.', 400, 140, 'parent', $callback);
				}
			}
		}

		/* 게시글다중삭제(원본글) */
		elseif($mode == 'board_multi_delete') {
			$requestPost = $this->input->post();

			$deleteSeqList = $requestPost['delseq'];

			$delseqar = explode(',', $deleteSeqList);

			// 숫자만 허용한다
			$delseqar = array_filter($delseqar, function ($value) {
				return is_numeric($value) === true;
			});

			$num = 0;
			for ($i = 0; $i < sizeof($delseqar); $i++) {
				if (empty($delseqar[$i])) {
					continue;
				}
				$delseq = $delseqar[$i];
				$parentsql['whereis'] = ' and seq= "' . $delseq . '" ';
				$parentsql['select'] = ' * ';
				$parentdata = $this->Boardmodel->get_data($parentsql); //게시판목록
				if (empty($parentdata)) {
					$callback = '';
					openDialogAlert('존재하지 않는 게시물입니다.', 400, 140, 'parent', $callback);
				}
				$replyor = 0;
				//답변여부
				$replysc['whereis'] = ' and gid > ' . $parentdata['gid'] . ' and gid < ' . (intval($parentdata['gid']) + 1) . ' and parent = ' . ($parentdata['seq']) . ' '; //답변여부
				$replyor = $this->Boardmodel->get_data_numrow($replysc);

				//게시글 삭제시 마일리지/포인트 회수 한번만!
				if ($parentdata['display'] != 1 && $parentdata['mseq']) {
					if (BOARDID == 'goods_review') {
						$this->_goods_review_less($manager, $parentdata, 'mulit');
					} else {
						$this->_board_less($manager, $parentdata, 'mulit');
					}
				}

				if ($replyor == 0 && $parentdata['comment'] == 0) {//답변과 댓글이 없는 경우 real 삭제
					$num++;
					$result = $this->Boardmodel->data_delete($delseq); //게시글삭제
					if ($result) {
						$this->Boardindex->idx_delete($parentdata['gid']); //index 삭제

						$this->Boardscorelog->data_parent_delete($delseq); //게시글평가제거

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

						//첨부파일삭제
						if (!empty($parentdata['upload'])) {
							$oldfile = @explode('|', $parentdata['upload']);
							for ($f = 0; $f < count($oldfile); $f++) {
								$oldrealfile = @explode('^^', $oldfile[$f]);
								if (@is_file($this->Boardmodel->upload_path . $oldrealfile[0])) {//기존위치에 수정시 변경
									@unlink($this->Boardmodel->upload_path . $oldrealfile[0]); //기존위치의 파일삭제
									@unlink($this->Boardmodel->upload_path . '_thumb_' . $oldrealfile[0]); //기존위치의 파일삭제
								}
							}
						}

						//동영상테이블삭제
						if ($parentdata['videotmpcode']) {
							$this->videofiles->videofiles_delete_parentseq('board', BOARDID, $parentdata['seq']);
						}
					}
				} else {
					$params['display'] = '1'; //삭제글여부1
					$params['subject'] = ''; //초기화
					$params['contents'] = ''; //초기화
					//$params['comment']		= '';//댓글수 초기화
					$params['upload'] = ''; //첨부파일 초기화
					$params['r_date'] = date('Y-m-d H:i:s');
					$result = $this->Boardmodel->data_delete_modify($params, $delseq);

					if ($result) {
						//공지글삭제
						$idxparams['display'] = 1; //삭제여부
						$idxparams['notice'] = 0; //공지 해지
						$idxparams['gid'] = $parentdata['gid']; //고유번호
						$this->Boardindex->idx_delete_modify($idxparams);

						$this->Boardscorelog->data_parent_delete($delseq); //게시글평가제거

						//첨부파일삭제
						if (!empty($parentdata['upload'])) {
							$oldfile = @explode('|', $parentdata['upload']);
							for ($f = 0; $f < count($oldfile); $f++) {
								$oldrealfile = @explode('^^', $oldfile[$f]);
								if (@is_file($this->Boardmodel->upload_path . $oldrealfile[0])) {//기존위치에 수정시 변경
									@unlink($this->Boardmodel->upload_path . $oldrealfile[0]); //기존위치의 파일삭제
									@unlink($this->Boardmodel->upload_path . '_thumb_' . $oldrealfile[0]); //기존위치의 파일삭제
								}
							}
						}

						//동영상테이블삭제
						if ($parentdata['videotmpcode']) {
							$this->videofiles->videofiles_delete_parentseq('board', BOARDID, $parentdata['seq']);
						}
					}//endif
				}//reply end
			}//endfor

			//게시글수 save
			$upmanagerparams['totalnum'] = $manager['totalnum'] - $num;
			$result = $this->Boardmanager->manager_item_save($upmanagerparams, BOARDID); //삭제게시판의 게시글감소

			$callback = 'parent.document.location.reload();';
			openDialogAlert($num . '건의 게시글을 삭제하였습니다.', 400, 140, 'parent', $callback);
		}

		/* 게시글다중삭제(원본글+덧글) */
		elseif($mode == 'board_multi_data_cmt_delete') {

		}

		/* 게시글다중복사 */
		elseif($mode == 'board_multi_copy') {

			$copyseqar = @explode(",",$_POST['delseq']);
			@sort($copyseqar);//역순
			$num = 0;
			for($i=0;$i<sizeof($copyseqar);$i++){
				if(empty($copyseqar[$i]))continue;
				$copyseq = $copyseqar[$i];
				$sc['whereis']	= ' and seq= "'.$copyseq.'" ';
				$sc['select']		= ' * ';
				$boarddata = $this->Boardmodel->get_data($sc);//게시물정보
				if($boarddata) {
					unset($params, $idxparams);
					$nokey = array('seq', 'boardid', 'gid', 'r_date','m_date');
					foreach($boarddata as $key=>$val) {
						if(in_array($key,$nokey)) continue;
						$params[$key] = $val;
					}

					$minsql['whereis']	= ' ';
					$minsql['select']		= ' min(gid) as mingid ';
					$mindata = $this->Boardmodel->get_data($minsql);
					$parentgid = $mindata['mingid'] ? $mindata['mingid']-1 : 100000000.00;
					$params['gid']			= $parentgid;
					$params['boardid']	= $_POST['copyid'];
					$params['r_date'] = date("Y-m-d H:i:s");
					$params['m_date'] = date("Y-m-d H:i:s");
					$params['videotmpcode'] = substr(microtime(), 2, 8);

					$params['contents'] = adjustMoveCopyImages($params['contents'],$_POST['board_id'], $this->Boardmanager->board_data_src.$_POST['copyid'].'/');//이미지경로변경
					$result = $this->Boardmodel->data_copy($params);
					if($result){$num++;
						$parent = $result;
						$sc['whereis']	= ' and id= "'.$_POST['copyid'].'" ';
						$sc['select']		= ' totalnum, id ';
						$copymanager = $this->Boardmanager->managerdataidck($sc);//게시판정보

						if( BOARDID == 'goods_review') {
							if($boarddata['mseq']) {
								//회원정보체크
								$this->load->model('membermodel');
								$minfo = $this->membermodel->get_member_data($boarddata['mseq']);
								$upsql = "update fm_member set review_cnt = review_cnt+1 where member_seq = '".$boarddata['mseq']."'";
								$this->db->query($upsql);
							}
						}

						//게시글수save
						$upmanagerparams['totalnum']		= $copymanager['totalnum']+1;
						$this->Boardmanager->manager_item_save($upmanagerparams,$_POST['copyid']);//복사게시판의 게시글증가

						//공지 Boardindex
						$idxparams['hidden']	= $params['hidden'];//비밀글여부
						$idxparams['notice']		= $params['notice'];//공지여부
						$idxparams['gid']			= $params['gid'];//고유번호
						$idxparams['boardid']	= $params['boardid'];//id
						$this->Boardindex->idx_write($idxparams);

						//첨부파일복사
						if(!empty($boarddata['upload'])){
							$oldfile = @explode("|",$boarddata['upload']);
							for ( $f=0;$f<count($oldfile);$f++) {
								$oldrealfile = @explode("^^",$oldfile[$f]);
								if(@is_file($this->Boardmodel->upload_path.$oldrealfile[0])){
									@copy($this->Boardmodel->upload_path.$oldrealfile[0],$this->Boardmanager->board_data_dir.$_POST['copyid'].'/'.$oldrealfile[0]);//파일복사
									@copy($this->Boardmodel->upload_path.'_thumb_'.$oldrealfile[0],$this->Boardmanager->board_data_dir.$_POST['copyid'].'/'.'_thumb_'.$oldrealfile[0]);//파일복사
								}
							}
						}//첨부파일

						//동영상테이블복사
						$this->videofiles->videofiles_copy('board', BOARDID, $parentdata['seq'],$_POST['copyid'],$params);

						//댓글복사
						$cmtnokey = array('seq', 'boardid', 'parent');
						$cmtreplynokey = array('seq', 'boardid', 'parent','cmtparent');
						$cmtparentsql['whereis']	= ' and parent= "'.$boarddata['seq'].'"  and boardid= "'.$boarddata['boardid'].'" and depth = 0';
						$cmtparentsql['select']		= ' * ';
						$cmtparentdata = $this->Boardcomment->get_copy_data($cmtparentsql);//댓글정보
						if(!empty($cmtparentdata)) {
							foreach($cmtparentdata as $cmtdatarow) {$idx++;
								foreach($cmtdatarow as $cmtkey=>$cmtval) {
									if(in_array($cmtkey,$cmtnokey)) continue;
									$cmtparams[$cmtkey] = $cmtval;
								}
								$cmtparams['boardid']		= $_POST['copyid'];
								$cmtparams['parent']		= $parent;//복사된새로운글
								$replyseq = $this->Boardcomment->data_write($cmtparams);

								/**
								댓글의 답글
								**/
								$sc['orderby']			= 'seq';
								$sc['sort']					= 'asc';
								$sc['parent']				= (isset($cmtdatarow['parent']))?$cmtdatarow['parent']:'';
								$sc['cmtparent']		= (isset($cmtdatarow['seq']))?$cmtdatarow['seq']:'';
								$cmtreplyqry = $this->Boardcomment->data_list_reply($sc);//댓글목록
								$replyidx = 0;
								foreach($cmtreplyqry['result'] as $cmtreply){$replyidx++;
									foreach($cmtreply as $cmtreplykey=>$cmtval) {
										if(in_array($cmtreplykey,$cmtreplynokey)) continue;
										$cmtreplyparams[$cmtreplykey] = $cmtval;
									}
									$cmtreplyparams['boardid']		= $_POST['copyid'];
									$cmtreplyparams['parent']		= $parent;//복사된새로운글
									$cmtreplyparams['cmtparent']	= $replyseq;//복사된새글에서 새로운댓글
									$this->Boardcomment->data_write($cmtreplyparams);
								}//답글
							}//본래글
						}

					}//복사성공시
				}//$boarddata end
			}
			$callback = "parent.document.location.reload();";
			openDialogAlert($num."건의 게시글을 복사하였습니다.",400,140,'parent',$callback);
		}

		/* 게시글 다중이동 */
		elseif($mode == 'board_multi_move') {

			$copyseqar = @explode(",",$_POST['delseq']);
			@sort($copyseqar);//역순
			$num = 0;
			for($i=0;$i<sizeof($copyseqar);$i++){if(empty($copyseqar[$i]))continue;
				$copyseq = $copyseqar[$i];
				$sc['whereis']	= ' and seq= "'.$copyseq.'" ';
				$sc['select']		= ' * ';
				$boardmanager = $this->Boardmodel->get_data($sc);//게시물정보
				if($boardmanager) {
					unset($params, $idxparams);
					$nokey = array('seq', 'boardid');
					foreach($boardmanager as $key=>$val) {
						if(in_array($key,$nokey)) continue;
						$params[$key] = $val;
					}
					$params['boardid']	= $_POST['copyid'];

					$params['contents'] = adjustMoveCopyImages($params['contents'],$_POST['board_id'], $this->Boardmanager->board_data_src.$_POST['copyid'].'/');//이미지경로변경
					$result = $this->Boardmodel->data_move($params,$copyseq);

					if($result){
						$num++;
						$sc['whereis']	= ' and id= "'.$_POST['copyid'].'" ';
						$sc['select']		= ' totalnum, id ';
						$copymanager = $this->Boardmanager->managerdataidck($sc);//게시판정보
						//게시글수save
						$upmanagerparams['totalnum']		= $copymanager['totalnum']+1;
						$this->Boardmanager->manager_item_save($upmanagerparams,$_POST['copyid']);

						//공지 Boardindex
						/**
						$idxparams['hidden']	= $params['hidden'];//비밀글여부
						$idxparams['notice']		= $params['notice'];//공지여부
						$idxparams['gid']			= $params['gid'];//고유번호
						$idxparams['boardid']	= $params['boardid'];//id
						**/
						$idxsc['select']			= ' gid ';
						$idxsc['whereis']		= ' and gid = "'.$params['gid'].'" and boardid = "'.$params['boardid'].'" ';
						$idxdata = $this->Boardindex->get_data($idxsc);//
						$idxparams['hidden']		= $params['hidden'];//비밀글여부
						$idxparams['notice']		= $params['notice'];//공지여부
						if( $params['notice']){
							$idxparams['onlynotice']			= ($_POST['onlynotice'])?$_POST['onlynotice']:0;//공지영역만 노출여부
							$idxparams['onlynotice_sdate']		= $_POST['onlynotice_sdate'];//공지노출 시작일
							$idxparams['onlynotice_edate']		= $_POST['onlynotice_edate'];//공지노출 완료일
						}else{
							$idxparams['onlynotice']			=	0;//공지영역만 노출여부
							$idxparams['onlynotice_sdate']		= '';//공지노출 시작일
							$idxparams['onlynotice_edate']		= '';//공지노출 완료일
						}
						$idxparams['gid']		= $params['gid'];//고유번호
						$idxparams['boardid']	= $params['boardid'];//id

						if( $idxdata ) {
							$this->Boardindex->idx_modify($idxparams);
						}else{
							$this->Boardindex->idx_write($idxparams);
						}

						//첨부파일이동
						if(!empty($boardmanager['upload'])){
							$oldfile = @explode("|",$boardmanager['upload']);
							for ( $f=0;$f<count($oldfile);$f++) {
								$oldrealfile = @explode("^^",$oldfile[$f]);
								if(@is_file($this->Boardmodel->upload_path.$oldrealfile[0])){
									@rename($this->Boardmodel->upload_path.$oldrealfile[0],$this->Boardmanager->board_data_dir.$_POST['copyid'].'/'.$oldrealfile[0]);//파일복사
									@rename($this->Boardmodel->upload_path.'_thumb_'.$oldrealfile[0],$this->Boardmanager->board_data_dir.$_POST['copyid'].'/'.'_thumb_'.$oldrealfile[0]);//파일복사
								}
							}
						}//첨부파일

						//동영상테이블이동
						if( $params['file_key_w'] ||  $params['file_key_i'] ){//
							unset($videofiles);
							$videofiles['parentseq']			= $copyseq;
							$videofiles['type']						= $_POST['copyid'];
							$this->videofiles->videofiles_move_parentseq($videofiles);
						}

						//댓글이동
						$cmtparams['boardid']		= $_POST['copyid'];
						$this->Boardcomment->data_parent_modify($cmtparams,$copyseq);

					}//이동성공시
				}
			}

			//게시글수save
			$upmanagerparams['totalnum']		= $manager['totalnum']-$num;
			$result = $this->Boardmanager->manager_item_save($upmanagerparams,BOARDID);//본래게시판의 게시글감소

			$callback = "parent.document.location.reload();";
			openDialogAlert($num."건의 게시글을 이동하였습니다.",400,140,'parent',$callback);
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
				echo "존재하지 않는 파일 입니다.";		//존재하지 않는 파일입니다.
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
				echo "삭제되었습니다.";		// 삭제되었습니다.
			} else {
				echo "존재하지 않는 파일 입니다.";		//존재하지 않는 파일입니다.
			}
			exit;
		}

		/* 게시글 파일다운 */
		elseif($mode == 'board_file_down') {

			$realfiledir = json_decode(base64_decode($_GET['realfiledir']), true);
			$_GET['realfiledir'] = $realfiledir['path'];

			if(empty($_GET['realfiledir'])) {// || empty($_SERVER['HTTP_REFERER'])
				$callback = "document.history(-1)";
				openDialogAlert("다운받을 파일을 선택해 주세요.",400,140,'parent',$callback);
				exit;
			}

			if( strstr($_GET['realfiledir'],'..') ) {
				$callback = "document.history(-1)";
				openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
				exit;
			}


			if( !strstr($_GET['realfiledir'],'/data/') ) {
				$callback = "document.history(-1)";
				openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
				exit;
			}

			if(is_file($_GET['realfiledir'])){
				$data = @file_get_contents($_GET['realfiledir']);
				force_download(str_replace(" ","_",$_GET['realfilename']), $data);
				exit;
			}
		}

		/* FAQ 게시글 노출여부설정 */
		elseif($mode == 'board_faq_hidden') {
			$params['hidden'] = (!empty($_POST['hidden']))?1:0;
			$result = $this->Boardmodel->data_modify($params);
			if($result){
				if($params['hidden'] == 1){
					echo "노출로 설정하였습니다.";
				}else{
					echo "노출되지 않도록 설정하였습니다.";
				}
			}else{
				echo "노출을 설정에 실패하였습니다.";
			}
			exit;
		}
	}


	//배너등록하기
	public function upload_file()
	{
		$this->load->helper('board');//

		$folder = "data/tmp/";

		foreach($_FILES as $key => $value)
		{
			$tmpname	= $value['tmp_name'];
			$file_ext		= end(explode('.', $value['name']));//확장자추출
			$file_name	= 'promotion_app_'.str_replace(" ", "", (substr(microtime(), 2, 6))).'.'.$file_ext;
			$file_name	= str_replace("\'", "", $file_name); 	// ' " 제거
			$file_name	= str_replace("\"", "", $file_name); 	// ' " 제거
			$saveFile		= $folder.$file_name;
			$thumbsaveFile		= $folder.'_thumb_'.$file_name;
			$config['allowed_types'] = 'jpg|gif|png';
			$tmp = @getimagesize($value['tmp_name']);
			if(!$tmp['mime']){
				$_FILES['Filedata']['type'] = $file_ext;//확장자추출
			}else{
				$_FILES['Filedata']['type'] = $tmp['mime'];
			}

			$fileresult = board_upload($key, $file_name, $folder, $config, $saveFile, 0, 'promotion');//status  error, fileInfo
			if(!$fileresult['status']){
				$error = array('status' => 0,'msg' => $fileresult['error'],'desc' => '업로드 실패');
				echo "[".json_encode($error)."]";
				exit;
			}
		}
		$result = array('status' => 1,'saveFile' => "/".$saveFile,'thumbsaveFile' => "/".$thumbsaveFile,'file_name' => $file_name);
		echo "[".json_encode($result)."]";
		exit;
	}


	/* 상품후기 지급 마일리지/포인트 회수 */
	public function _goods_review_less($manager, $parentdata, $multi=null)
	{
		$this->load->model('membermodel');
		$this->load->model('emoneymodel');


		if( defined('__SELLERADMIN__') === true ) {
			$manager_seq	= $this->providerInfo['provider_seq'];
		}else{
			$manager_seq	= $this->managerInfo['manager_seq'];
		}

		if(isset($_POST['board_less_emoney']))	$board_less_emoney = trim($_POST['board_less_emoney']);
		if(isset($_POST['board_less_point']))		$board_less_point = trim($_POST['board_less_point']);

		$minfo = $this->membermodel->get_member_data($parentdata['mseq']);

		/************
		* 마일리지 회수시작
		*************/
		//if( $board_less_emoney > 0 ) {
			$emautoemoneysc = $this->db->query("select * from fm_emoney where emoney_use !='less' and (type = 'goods_review_auto' or type = 'goods_review_auto_photo' or type = 'goods_review_auto_video' or type = 'goods_review_date') and gb = 'plus' and member_seq = '".$parentdata['mseq']."' and ( (ordno  = '".$parentdata['order_seq']."' and goods_review = '".$parentdata['goods_seq']."' and (ordno != '' or ordno != 0 ) and goods_review_parent='".$parentdata['seq']."' ) or ( goods_review_parent='".$parentdata['seq']."' ) ) ");
			$emautoemoneyar = $emautoemoneysc->result_array();
			if($emautoemoneyar) {
				foreach($emautoemoneyar as $emautoemoneyck=>$emautoemoney) {
					if( $multi ) $board_less_emoney += $emautoemoney['emoney'];
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
				if( $multi ) $board_less_emoney += $emjoinck['emoney'];
				//지급>회수완료업데이트
				$this->db->where('emoney_seq',$emjoinck['emoney_seq']);
				$this->db->update('fm_emoney',array('emoney_use'=>'less'));

			}

			if( $emautoemoneyar || $emjoinck ) {
				if( isset($board_less_emoney) ) {
					$params = array(
						'gb'			=> 'minus',
						'type'			=> 'goods_review_less',
						'emoney'		=> $board_less_emoney,
						'manager_seq'		=> $manager_seq,
						'goods_review'		=> $parentdata['goods_seq'],
						'goods_review_parent'	=> $parentdata['seq'],
						'memo'                  => "[회수]".$manager['name']." 삭제에 의한 마일리지 차감",
						'memo_lang'		=> $this->membermodel->make_json_for_getAlert("mp266",$manager['name']),  // [회수]%s 삭제에 의한 마일리지 차감
					);
					$this->membermodel->emoney_insert($params, $parentdata['mseq']);
				}
			}
		//}//endif
		/************
		* 마일리지 회수끝
		*************/

		/************
		* 포인트 회수시작
		*************/
		//if( $board_less_point > 0 ) {
			$emautopointsc = $this->db->query("select * from fm_point where point_use !='less' and (type = 'goods_review_auto' or type = 'goods_review_auto_photo' or type = 'goods_review_auto_video' or type = 'goods_review_date') and gb = 'plus' and member_seq = '".$parentdata['mseq']."' and ( (ordno  = '".$parentdata['order_seq']."' and goods_review = '".$parentdata['goods_seq']."' and (ordno != '' or ordno != 0 ) ) or ( goods_review_parent='".$parentdata['seq']."' ) ) ");
			$emautopointar = $emautopointsc->result_array();

			if( $emautopointar ){

				foreach($emautopointar as $emautopointck=>$emautopoint) {
					if( $multi ) $board_less_point += $emautopoint['point'];

					//지급>회수완료업데이트
					$this->db->where('point_seq',$emautopoint['point_seq']);
					$this->db->update('fm_point',array('point_use'=>'less'));

				}//end foreach

				if( isset($board_less_point) ) {
					$params = array(
						'gb'			=> 'minus',
						'type'			=> 'goods_review_less',
						'point'			=> $board_less_point,
						'manager_seq'		=> $manager_seq,
						'goods_review'		=> $parentdata['goods_seq'],
						'goods_review_parent'	=> $parentdata['seq'],
						'memo'			=> "[회수]".$manager['name']." 삭제에 의한 포인트 차감",
						'memo_lang'		=> $this->membermodel->make_json_for_getAlert("mp267",$manager['name']),  // [회수]%s 삭제에 의한 포인트 차감
					);
					$this->membermodel->point_insert($params, $parentdata['mseq']);
				}
			}
		//}//end if
		/************
		* 포인트 회수끝
		*************/

	}


	/* 상품후기외 지급 수동마일리지 회수 */
	public function _board_less($manager, $parentdata, $multi=null)
	{
		$this->load->model('membermodel');
		$this->load->model('emoneymodel');


		if( defined('__SELLERADMIN__') === true ) {
			$manager_seq	= $this->providerInfo['provider_seq'];
		}else{
			$manager_seq	= $this->managerInfo['manager_seq'];
		}

		if(isset($_POST['board_less_emoney']))	$board_less_emoney = trim($_POST['board_less_emoney']);
		//if(isset($_POST['board_less_point']))		$board_less_point = trim($_POST['board_less_point']);

		$minfo = $this->membermodel->get_member_data($parentdata['mseq']);

		/************
		* 마일리지 회수시작
		*************/
		//수동마일리지 지급여부
		$joinsc['whereis'] = " and emoney_use !='less' and type = 'board_".$manager['id']."' and gb = 'plus' and member_seq = '".$parentdata['mseq']."' and (goods_review = '".$parentdata['seq']."' or goods_review_parent = '".$parentdata['seq']."') ";
		$joinsc['select']	= ' * ';
		$emjoinck = $this->emoneymodel->get_data($joinsc);

		if( $emjoinck ){
			if( $multi ) $board_less_emoney += $emjoinck['emoney'];
			//지급>회수완료업데이트
			$this->db->where('emoney_seq',$emjoinck['emoney_seq']);
			$this->db->update('fm_emoney',array('emoney_use'=>'less'));

			if( isset($board_less_emoney) ) {
				$params = array(
					'gb'			=> 'minus',
					'type'			=> 'board_'.$manager['id'].'_less',
					'emoney'		=> $board_less_emoney,
					'manager_seq'		=> $manager_seq,
					'goods_review_parent'	=> $parentdata['seq'],
					'memo'			=> "[회수]".$manager['name']." 삭제에 의한 마일리지 차감",
					'memo_lang'		=> $this->membermodel->make_json_for_getAlert("mp266",$manager['name']), // [회수]%s 삭제에 의한 마일리지 차감
				);
				$this->membermodel->emoney_insert($params, $parentdata['mseq']);
			}

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
			$result = array('result'=>false, 'msg'=>"존재하지 않는 게시판입니다.");
			echo json_encode($result);
			exit;
		}

		if( $this->manager['auth_recommend_use'] == 'Y' ) {
			$sc['whereis']	= ' and seq= "'.$_POST['parent'].'" ';
			$sc['select']		= ' seq, gid, comment, upload, depth, file_key_w  ';
			$data = $this->Boardmodel->get_data($sc);

			if(empty($data)) {
				$result = array('result'=>false, 'msg'=>"존재하지 않는 게시물입니다.");
				echo json_encode($result);
				exit;
			}
			$parentsql['whereis']	= ' and boardid= "'.$_POST['board_id'].'" ';
			$parentsql['whereis']	.= ' and type= "board" ';
			$parentsql['whereis']	.= ' and parent= "'.$_POST['parent'].'" ';//게시글
			if($_POST['cparent']) $parentsql['whereis']	.= ' and cparent= "'.$_POST['cparent'].'" ';//댓글
			$parentsql['whereis']	.= ' and mseq= "-'.$this->managerInfo['manager_seq'].'" ';
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
					$params['mseq']			= '-'.$this->managerInfo['manager_seq'];//$this->userInfo['member_seq'];

					$params['regist_date']	= date("Y-m-d H:i:s");
					$this->Boardscorelog->data_write($params);

					$sc['whereis']	= ' and seq= "'.$_POST['parent'].'" ';
					$sc['select']		= ' seq, gid, comment, upload, depth, file_key_w,'.$scoreid;
					$getscoredata = $this->Boardmodel->get_data($sc);

					 $msg = "회원님의 평가가 반영되었습니다.";
				 }else{
					 $msg = "회원님의 평가가 실패되었습니다.";
				 }
			}else{
				 $msg = "이미 평가하신 게시글입니다.";
			}
		}else{
			 $msg = "잘못된 접근입니다.";
		}

		if( $result ) {
			$result = array('result'=>true, 'msg'=>$msg, 'scoreid'=>$getscoredata[$scoreid]);
		}else{
			$result = array('result'=>false, 'msg'=>$msg);
		}

		echo json_encode($result);
		exit;
	}

	//에딧터의 파일삭제
	public function editor_file_delete(){
		if(empty($_POST['realfiledir'])) {// || empty($_SERVER['HTTP_REFERER'])
			$callback = "document.history(-1)";
			openDialogAlert("다운받을 파일을 선택해 주세요.",400,140,'parent',$callback);
			exit;
		}

		if( !strstr($_POST['realfiledir'],'/data/') ) {
			$callback = "document.history(-1)";
			openDialogAlert("잘못된 접근입니다.",400,140,'parent',$callback);
			exit;
		}

		if(is_file($_POST['realfiledir'])){

			$realfiledir = end(explode("/",$_POST['realfiledir']));
			$realfiledirtmp = str_replace($realfiledir,"",$_POST['realfiledir']);
			@unlink($realfiledirtmp.'_thumb_'.$realfiledir);
			@unlink($realfiledirtmp.'_widget_thumb_'.$realfiledir);

			@unlink($_POST['realfiledir']);
			echo "true";
			exit;
		}
	}

	/**
	 * 신고글 무시(신고글삭제)
	 */
	public function report_delete() {
		$auth = $this->authmodel->manager_limit_act('report_act');
		if(!$auth){
			$result = [
				'result' => FALSE,
				'msg' => '권한이 없습니다.',
			];
			echo json_encode($result);
			exit;
		}

		$seq = $this->input->post('seq');
		if(!$seq) {
			$result = [
				'result' => FALSE,
				'msg' => '잘못된접근입니다.',
			];
			echo json_encode($result);
			exit;
		}

		// 신고글 삭제
		$this->load->library('Board/BoardReportLibrary');
		$report = $this->boardreportlibrary->viewReport($seq);

		if(!$report) {
			$result = [
				'result' => FALSE,
				'msg' => '잘못된접근입니다.',
			];
			echo json_encode($result);
			exit;
		}

		$this->boardreportlibrary->deleteReport($seq);
		$result = [
			'result' => TRUE,
		];
		echo json_encode($result);
		exit;
	}
}

/* End of file board_process.php */
/* Location: ./app/controllers/admin/board_process.php */