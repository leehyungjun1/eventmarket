<?php
/**
 * 게시글 관련 모듈
 * @author gabia
 * @since version 1.0 - 2012.06.29
 */
class Boardmanager extends CI_Model {

	# 리스트 스타일 종류 게시글추가 작업
	var $styles = array(
		'display_lattice_a'		=>	array('name'=>'격자형A','count_w'=>4),
		'display_lattice_b'		=>	array('name'=>'격자형B','count_w'=>2,'count_w_fixed'=>true),
		'display_list'			=>	array('name'=>'리스트형','count_w'=>1,'count_w_fixed'=>true)
	);


	var $typeNames = array(
		'text'		=> '텍스트박스',
		'select'   	=> '셀렉트박스',
		'radio'		=> '여러개 중 택1',
		'checkbox'	=> '체크박스',
		'textarea'	=> '에디트박스'
	);



	function __construct() {
		parent::__construct();

		if(isset($_GET['id'])) $this->id = $_GET['id'];
		$this->table_manager = 'fm_boardmanager';
		$this->cmthidden				= 'Y';//댓글비밀글
		if( defined('__ADMIN__') && !defined('__DESIGNIS__')) {//관리자접근dir
			$this->managerurl				= '/admin/board/main';							//게시판관리
			$this->realboardurl				= '/admin/board/board?id=';				//게시물관리
			$this->realboarduserurl		= '/admin/board/?id=';							//게시물관리
			$this->realboardwriteurl		= '/admin/board/write?id=';					//게시물등록
			$this->realboardviewurl		= '/admin/board/view?id=';					//게시물보기

			$this->realboardpermurl		= './permcheck?id=';			//접근권한페이지
			$this->realboardpwurl			= './pwcheck?id=';			//로그인페이지(직접접근시
		}elseif( defined('__SELLERADMIN__') && !defined('__DESIGNIS__') ) {//관리자접근dir
			$this->managerurl				= '/selleradmin/board/main';							//게시판관리
			$this->realboardurl				= '/selleradmin/board/board?id=';				//게시물관리
			$this->realboarduserurl		= '/selleradmin/board/?id=';							//게시물관리
			$this->realboardwriteurl		= '/selleradmin/board/write?id=';					//게시물등록
			$this->realboardviewurl		= '/selleradmin/board/view?id=';					//게시물보기

			$this->realboardpermurl		= './permcheck?id=';			//접근권한페이지
			$this->realboardpwurl			= './pwcheck?id=';			//로그인페이지(직접접근시
		}else{

			$this->managerurl			= '../board/main';				//게시판관리
			$this->realboardurl			= '../board/board?id=';			//게시물관리
			$this->realboarduserurl		= '../board/?id=';				//게시물관리
			$this->realboardwriteurl	= '../board/write?id=';			//게시물등록
			$this->realboardviewurl		= '/board/view?id=';			//게시물보기

			$this->realboardpermurl		= '../board/permcheck?id=';		//접근권한페이지
			$this->realboardpwurl		= '../board/pwcheck?id=';		//로그인페이지(직접접근시
		}

		$this->renewlist = array("mbqna","faq","goods_qna","goods_review","bulkorder","gs_seller_qna","gs_seller_notice","store_review","store_reservation");//기본스킨리스트

		$this->board_tmp_dir = ROOTPATH.'data/tmp/';//임시저장소
		$this->board_tmp_src = '/data/tmp/';//임시저장소 저장시

		$this->board_capt_dir = ROOTPATH.'data/captcha/';//임시저장소
		$this->board_capt_src = '/data/captcha/';//임시저장소 저장시
		$this->board_captcha_ttf = ROOTPATH.'data/board/verdanab.ttf';//
		$this->board_captcha_ttf_new = ROOTPATH.'data/board/_fonts';//

		if( defined('__ADMIN__') || defined('__SELLERADMIN__') ) {//관리자접근dir
			$this->board_icon_src = '/data/skin/'.$this->workingSkin.'/images/board/icon/';
			$this->board_icon_dir = ROOTPATH.'/data/skin/'.$this->workingSkin.'/images/board/icon/';
		}else{
			$this->board_icon_src = '/data/skin/'.$this->skin.'/images/board/icon/';
			$this->board_icon_dir = ROOTPATH.'/data/skin/'.$this->skin.'/images/board/icon/';
		}

		$this->admin_board_icon_src = '/admin/skin/'.$this->config_system['adminSkin'].'/images/board/icon/';
		$this->admin_board_icon_dir = ROOTPATH.'/admin/skin/'.$this->config_system['adminSkin'].'/images/board/icon/';

		if( defined('__ADMIN__') ||  defined('__SELLERADMIN__') ) {//관리자접근dir
			$this->board_skin_dir = ROOTPATH.'data/skin/'.$this->workingSkin.'/board/';
			$this->board_skin_src = '/data/skin/'.$this->workingSkin.'/board/';
		}else{
			$this->board_skin_dir = ROOTPATH.'data/skin/'.$this->skin.'/board/';
			$this->board_skin_src = '/data/skin/'.$this->skin.'/board/';
		}

		$this->board_data_dir = ROOTPATH.'data/board/';//첨부파일폴더
		$this->board_data_src = '/data/board/';

		$this->board_originalskin_dir = ROOTPATH.'board_original/'.$this->config_system['language'].'/';//스킨폴더
		$this->board_originalskin_src = '/board_original/'.$this->config_system['language'].'/';
		$bdiconarray = array("hot"=>"icon_hot.gif","new"=>"icon_new.gif","review"=>"icon_review.gif","award"=>"icon_award.png",
			"best"=>"icon_best.gif","best_gray"=>"icon_best_gray.gif","admin"=>"icon_admin.gif","file"=>"ico_file.gif","img"=>"icon_img.gif",
			"video"=>"icon_video.gif","mobile"=>"icon_mobile.gif","hidden"=>"ico_hidden.gif","notice"=>"icon_notice.gif","re"=>"icon_comment_reply.gif",
			"blank"=>"blank.gif","print"=>"b_print.gif","cmt_reply"=>"cmt_reply_btn_delete.gif",
			"snst"=>"sns_t0.gif","snsf"=>"sns_f0.gif","snsm"=>"sns_m0.gif","snsy"=>"sns_y0.gif");
		foreach( $bdiconarray as $key=>$val ) {
			$this->{$key.'_icon_src'} =  $this->admin_board_icon_src.$val;//$this->board_icon_src.$val;
		}


		$bdiconarray = array("recommend"=>"icon_recommend.png","none_rec"=>"icon_none_rec.png",
			"recommend1"=>"icon_recommend1.png","recommend2"=>"icon_recommend2.png","recommend3"=>"icon_recommend3.png","recommend4"=>"icon_recommend4.png","recommend5"=>"icon_recommend5.png",
			"cmt_recommend"=>"icon_cmt_recommend.png","cmt_none_rec"=>"icon_cmt_none_rec.png");
		foreach( $bdiconarray as $key=>$val ) {
			$this->{$key.'_icon_src'} =  $this->admin_board_icon_src.$val;
		}

		$this->board_restr = 'RE:';//답글제목형식
		$this->board_cont_restr = '<br /><blockquote style="border-left: #000000 2px solid; padding-bottom: 0px; margin: 0px 0px 0px 5px; padding-left: 5px; adding-right: 0px; padding-top: 0px"><div>------------Original Message------------</div>';//답글내용형식

		if	($this->mobileMode || $this->storemobileMode)
			$this->board_cont_restr = "-----------Original Message-----------\n";


		$this->goodsreviewicondir = ROOTPATH.'/data/icon/goods_review/';//상품후기의 평가정보아이콘위치
		$this->goodsreviewicon = '/data/icon/goods_review/';//상품후기의 평가정보아이콘위치
	}

	/*
	 * 게시물관리
	 * @param
	*/
	public function manager_list($sc) {

		$board_arr = serviceLimit('F1','return');
		if(is_array($board_arr))	$where = "id NOT IN ('" . implode("', '",$board_arr) ."')";
		else						$where = '1';

		$sql = "select  SQL_CALC_FOUND_ROWS seq, name, id, totalnum, skin_type, skin, type, auth_read, auth_write, auth_reply, auth_cmt, auth_reply_use, auth_cmt_use,
		CASE WHEN type = 'A' THEN '추가' ELSE '기본' END AS typetitle,
		CASE WHEN auth_reply_use = 'Y' THEN '사용함' ELSE '미사용' END AS auth_reply_use_title,
		CASE WHEN auth_cmt_use = 'Y' THEN '사용함' ELSE '미사용' END AS auth_cmt_use_title,
		CASE WHEN id='gs_seller_qna' AND auth_read = '[all]' THEN '입점사'
				 WHEN auth_read = '[all]' AND secret_use='Y' THEN '전체(비밀글사용)'
				 WHEN auth_read = '[all]' THEN '전체'
				 WHEN auth_read = '[admin]' THEN '관리자'
				 WHEN auth_read like '[member%' THEN '회원'
				 ELSE '관리자' END AS auth_read_title,
		CASE WHEN auth_write = '[all]' THEN '전체'
				 WHEN auth_write = '[admin]' THEN '관리자'
				 WHEN auth_write = '[onlybuyer]' THEN '구매자'
				 WHEN auth_write like '[member%' THEN '회원'
				 ELSE '관리자' END AS auth_write_title
		from  ".$this->table_manager."  where " . $where;

		if( !empty($sc['skin']) )
		{
			$skin_typein = @implode("','",$sc['skin']);
			$sql .= " and skin IN ('{$skin_typein}') ";
		}

		if( !empty($sc['type']) )
		{
			if($sc['type'] === 'A') {
				$sql .= ' AND type = "A" ';
			}
			else {
				$sql .= ' AND type != "A" ';
			}
		}

		if( !empty($sc['search_text']))
		{
			$columns = [
				'id',
				'name',
			];
			switch($sc['search_type']) {
				case 'id':
					$columns = ['id'];
				break;
				case 'name':
					$columns = ['name'];
				break;
				default:
			}
			$sql .= ' AND ('.implode(' OR ', array_map(function($column) use ($sc) {
				return "{$column} LIKE \"%{$sc['search_text']}%\"";
			}, $columns)).') ';
		}

		$sql .=" order by {$sc['orderby']} {$sc['sort']}";
		$limit =" limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();

		$sql			= "SELECT FOUND_ROWS() as COUNT";
		$query_count	= $this->db->query($sql);
		$res_count		= $query_count->result_array();
		$data['count']	= $res_count[0]['COUNT'];

		return $data;
	}

	// 게시물총건수
    function get_item_total_count($sc='')
    {
		$board_arr = serviceLimit('F1','return');
		if(is_array($board_arr))	$where = "id NOT IN ('" . implode("', '",$board_arr) ."')";
		else						$where = '1';
		$sql		= "select count(seq) as cnt from " . $this->table_manager . " where " . $where ;
		$query		= $this->db->query($sql);
		$res_count	= $query->row_array();

		return $res_count['cnt'];
    }


	/*
	 * 게시판정보
	 * @param
	*/
	public function manager_whereis_list($sc)
	{
		if( defined('__SELLERADMIN__') === true ) {
			$sc['whereis']		= ' and id in (\'goods_qna\',\'goods_review\',\'gs_seller_qna\',\'gs_seller_notice\') ';
		}

		$whereis = (!empty($sc['whereis']))?$sc['whereis']:'';
		$select = (!empty($sc['select']))?$sc['select']:' * ';
		$sql = "select ".$select." from  ".$this->table_manager."  where 1 ". $whereis;
		if( $sc['manager_setting'] ) {//게시판접근권한
			$sql .=" order by type asc, seq asc ";
		}else{
		$sql .=" order by type desc, seq asc ";
		}
		$query = $this->db->query($sql);
		$data = $query->result_array();

		if( defined('__SELLERADMIN__') === true ) {
			foreach($data as $key => $val){
				switch($val['id']){
					CASE "goods_qna" : 
						$table = 'fm_goods_qna';
						$where = 'provider_seq = "'.$this->providerInfo['provider_seq'].'"';
						break;
					CASE "goods_review" : 
						$table = 'fm_goods_review';
						$where = 'provider_seq = "'.$this->providerInfo['provider_seq'].'"';
						break;
					CASE "gs_seller_notice" : 
						$table = 'fm_boarddata';
						$where = 'mseq = "-1" AND notice = 0 and boardid = "'.$val['id'].'"';
						break;
					CASE "gs_seller_qna" : 
						$table = 'fm_boarddata';
						$where = 'boardid = "'.$val['id'].'" and mseq = "-'.$this->providerInfo['provider_seq'].'"';
						break;
				}
				$sql = "select count(*) as 'newtotal_count' from ".$table." WHERE ".$where;
				$query		= $this->db->query($sql);
				$newtotal_count	= $query->row('newtotal_count')==TRUE?$query->row('newtotal_count'):0;
				$data[$key]['totalnum'] = $newtotal_count;
			}
		}

		return $data;
	}


	/*
	 * 게시판정보
	 * @param
	*/
	public function get_managerdata($sc)
	{
		$whereis = (!empty($sc['whereis']))?$sc['whereis']:'';
		$select = (!empty($sc['select']))?$sc['select']:' * ';
		$sql = "select ".$select." from  ".$this->table_manager."  where 1 ". $whereis;
		$sql .=" order by type desc, seq asc ";
		$query = $this->db->query($sql);
		$data = $query->result_array();
		return ($data) ? $data[0]:'';
	}

	/*
	 * 게시판정보
	 * @param
	*/
	public function managerdataidck($sc) {
		$sc['binds'] = isset($sc['binds']) ? $sc['binds'] : [];
		$sql = "select ".$sc['select']." from  ".$this->table_manager."  where 1 ". $sc['whereis'];
		$sql .=" order by seq asc ";
		$query = $this->db->query($sql, $sc['binds']);
		$data = $query->row_array();
		if($data) {
			$gallerycell = explode("X" , $data['gallerycell']);//겔러리인경우
			$data['gallerycell0'] = $gallerycell[0];
			$data['gallerycell1'] = $gallerycell[1];

			$video_screen = explode("X" , $data['video_screen']);
			$data['video_screen0'] = $video_screen[0];
			$data['video_screen1'] = $video_screen[1];

			$video_size = explode("X" , $data['video_size']);
			$data['video_size0'] = $video_size[0];
			$data['video_size1'] = $video_size[1];

			$video_size_mobile = explode("X" , $data['video_size_mobile']);
			$data['video_size_mobile0'] = $video_size_mobile[0];
			$data['video_size_mobile1'] = $video_size_mobile[1];

			$data['subjectcut']				= ($data['subjectcut']>0)?$data['subjectcut']:30;
			$data['mobile_subjectcut']	= $data['subjectcut'];//($data['subjectcut']>0)?intval($data['subjectcut']/2):15;

			if($data['id'] == 'goods_review'){//@2012-11-06 상품후기 답글 미사용처리
				$data['auth_write_reply'] = ($data['auth_write_reply'])?$data['auth_write_reply']:'[all]';//
				$data['viewtype'] = ($data['viewtype'])?$data['viewtype']:'layer';//page, layer
			}
			if($data['id'] == 'goods_qna'){
				$data['viewtype'] =($data['viewtype'])?$data['viewtype']:'layer';//page, layer
			}

			if( $data['recommend_type'] == '3' ) {
				$data['scoretitle'] = "<span>평가</span>";
			}elseif( $data['recommend_type'] == '2' ) {
				$data['scoretitle'] = "<span>추천/비추천</span>";
			}else{
				$data['scoretitle'] = "<span>추천</span>";
			}

			//문자답변 사용여부
			$sms_reply_user_yn	= config_load('sms',$data['id'].'_reply_user_yn');
			$sms_reply_user		= config_load('sms',$data['id'].'_reply_user');

			// 카카오 알림톡 관련 추가 :: 2018-03-26 lwh
			$this->load->model('kakaotalkmodel');
			$kakaotalk_config	= $this->kakaotalkmodel->get_service();
			if($kakaotalk_config['status'] == 'A' && $kakaotalk_config['use_service'] == 'Y'){
				$scParams['msg_code'] = $data['id'].'_reply_user';
				$msg_info = $this->kakaotalkmodel->get_msg_code($scParams,false);
				if($sms_reply_user_yn[$data['id'].'_reply_user_yn'] != 'Y')
					$sms_reply_user_yn[$data['id'].'_reply_user_yn'] = $msg_info[0]['msg_yn'];
			}

			if(trim($sms_reply_user[$data['id'].'_reply_user']) != ''){
				$data['sms_reply_user_yn'] = $sms_reply_user_yn[$data['id'].'_reply_user_yn'];

				// 발송제한 설정 시간 및 예약발송시간
				$sms_rest			= config_load('sms_restriction');
				$board_time_s		= $sms_rest['board_time_s'];
				$board_time_e		= $sms_rest['board_time_e'];
				$board_reserve_time	= $sms_rest['board_reserve_time'];
				if($board_time_s && $board_time_e && $board_reserve_time){
				   $restriction_msg = "<br /><span style='color:#d90000;line-height:14px;'>발송제한시간 : ";
				   $restriction_msg.= $board_time_s."시~".$board_time_e."시 ";
				   $restriction_msg.= " ▶ 08시 +".$board_reserve_time."분</span>";
				}else{
				   $restriction_msg = "";
				}
				$data['restriction_msg'] = $restriction_msg;
			}
		}
		return $data;
	}

	/*
	 * 게시판생성
	 * @param
	*/
	public function manager_write($params) {
		if(empty($params['id']))return false;
		$data = filter_keys($params, $this->db->list_fields($this->table_manager));
		$result = $this->db->insert($this->table_manager, $data);
		return $this->db->insert_id();
	}

	/*
	 * 게시판수정
	 * @param
	*/
	public function manager_modify($params) {
		if(empty($_POST['seq']))return false;
		$data = filter_keys($params, $this->db->list_fields($this->table_manager));
		$result = $this->db->update($this->table_manager, $data,array('seq'=>$_POST['seq']));
		print_r($result);
		return $result;
	}


	/*
	 * 게시판 개별수정
	 * @param
	*/
	public function manager_item_save($params,$board_id) {
		if(empty($board_id))return false;
		$data = filter_keys($params, $this->db->list_fields($this->table_manager));
		$result = $this->db->update($this->table_manager, $data,array('id'=>$board_id));
		return $result;
	}

	/*
	 * 게시판삭제
	 * @param
	*/
	public function manager_delete($board_id) {
		if(empty($board_id))return false;
		$result = $this->db->delete($this->table_manager, array('id' => $board_id));
		return $result;
	}

	/*
	 * 게시판복사
	 * @param
	*/
	public function manager_copy($params, $olddata, $copyid, $new_id) {
		$result =$this->manager_write($params);
		if($result) {
			$seq = $result;
			boarduploaddir($new_id);
			$upparams = "";
			if($olddata['icon_new_img'] && is_file($this->Boardmanager->board_data_dir.$copyid.'/'.$olddata['icon_new_img']) ) {
				$extar = explode("_new.",$olddata['icon_new_img']);
				@copy($this->Boardmanager->board_data_dir.$copyid.'/'.$olddata['icon_new_img'],$this->Boardmanager->board_data_dir.$new_id.'_new.'.$extar[1]);
				@chmod($this->Boardmanager->board_data_dir.$new_id.'_new.'.$extar[1], 0777);
				$upparams['icon_new_img'] = $new_id.'_new.'.$extar[1];
			}

			if($olddata['icon_hot_img'] && is_file($this->Boardmanager->board_data_dir.$copyid.'/'.$olddata['icon_hot_img']) ) {
				$extar = explode("_hot.",$olddata['icon_hot_img']);
				@copy($this->Boardmanager->board_data_dir.$copyid.'/'.$olddata['icon_hot_img'],$this->Boardmanager->board_data_dir.$new_id.'_hot.'.$extar[1]);
				@chmod($this->Boardmanager->board_data_dir.$new_id.'_hot.'.$extar[1], 0777);
				$upparams['icon_hot_img'] = $new_id.'_hot.'.$extar[1];
			}

			if($olddata['icon_review_img'] && is_file($this->Boardmanager->board_data_dir.$copyid.'/'.$olddata['icon_review_img']) ) {
				$extar = explode("_review.",$olddata['icon_review_img']);
				@copy($this->Boardmanager->board_data_dir.$copyid.'/'.$olddata['icon_review_img'],$this->Boardmanager->board_data_dir.$new_id.'_review.'.$extar[1]);
				@chmod($this->Boardmanager->board_data_dir.$new_id.'_review.'.$extar[1], 0777);
				$upparams['icon_review_img'] = $new_id.'_review.'.$extar[1];
			}
			if($upparams){
				$updata = filter_keys($upparams, $this->db->list_fields($this->table_manager));
				$this->db->update($this->table_manager, $updata,array('seq'=>$seq));
			}
		}
		return $result;
	}




	//가입형식 추가 타입별 속성값 가져오기
	public function get_labelitem_type($data, $msdata,$showtype = null){

		switch($data['label_type'])
			{

				case "text" :

					for ($j=0; $j<$data['label_value']; $j++) {
						if ($j > 0) $inputBox .= "<br/>";
						$label_value = ($msdata[$j]) ? $msdata[$j]['label_value'] : '';
						if($showtype == 'view'){
							$inputBox .= $label_value ;
						}else{

							$size = ( $this->mobileMode || $this->storemobileMode )?" ":"size='24' ";
							$inputBox .= '<input type="text" name="label['.$data['bulkorderform_seq'].'][value][]" class=" line text_'.$data['bulkorderform_seq'].'" id="txtlabel_'.$data['bulkorderform_seq'].'" value="'.$label_value.'" '.$size.'>';
						}
					}
				break;

				case "select" :
					$labelArray = explode("|", $data['label_value']);
					$labelCount = count($labelArray)-1;
					$labelindexBox = '';
					$label_value = ($msdata[0]) ? $msdata[0]['label_value'] : '';
					if($showtype == 'view'){
						$inputBox .= $label_value ;
					}else{

						for ($j=0; $j<$labelCount; $j++)
						{
							$labelsubArray = explode(";", $labelArray[$j]);
							$selected = ($labelsubArray[0] == $label_value) ? "selected" : "";
							$labelindexBox .= '<option value="'. $labelsubArray[0] .'" '. $selected .' childs="'.implode(";",array_slice($labelsubArray,1)).'">'. $labelsubArray[0] .'</option>';
						}
						if($msdata[0]){
							$labelsubBox = '<input type="hidden" name="subselect['.$data['bulkorderform_seq'].'] id="subselect_'.$data['bulkorderform_seq'].'" value="'.$msdata[0]['label_sub_value'].'" bulkorderform_seq="'.$data['bulkorderform_seq'].'" class="hiddenLabelDepth">';
						}

						$inputBox .= '<select name="label['.$data['bulkorderform_seq'].'][value][]" id="label_'.$data['bulkorderform_seq'].'" bulkorderform_seq="'.$data['bulkorderform_seq'].'" style="height:18px; line-height:16px;" class="selectLabelDepth1">';
						$inputBox .= $labelindexBox;
						$inputBox .= '</select>';
						$inputBox .= $labelsubBox;
					}

				break;

				case "textarea" :

						switch($data['label_value'])
						{
							case "large" :		$height = "300px";	break;
							case "medium" :		$height = "200px";	break;
							case "small" :		$height = "100px";	break;
						}
						$label_value = ($msdata[0]) ? $msdata[0]['label_value'] : '';
						if($showtype == 'view'){
							$inputBox .= $label_value ;
						}else{
							$inputBox .= '<textarea name="label['.$data['bulkorderform_seq'].'][value][]" id="txtarealabel_'.$data['bulkorderform_seq'].'" style="width:90%; height:'. $height .';">'.$label_value.'</textarea>';
						}

				break;

				case "checkbox" :
					$labelArray = explode("|", $data['label_value']);
					array_pop($labelArray);

					$labelValues = is_array($msdata)?array_map(function($item){
						return $item['label_value'];
					}, $msdata) : [];

					$result = array_reduce($labelArray, function($arr, $item) use ($showtype, $labelArray, $labelValues, $data) {
						$checked = in_array($item, $labelValues);
						if($showtype === 'view') {
							if($checked)
								$arr[] = $item;
						}
						else {
							$arr[] = ''
								.'<label>'
									.'<input type="checkbox" name="label['.$data['bulkorderform_seq'].'][value][]" class="labelCheckbox_'.$data['bulkorderform_seq'].'" value="'. $item .'"'.($checked?' checked':'').'>'
									."<span>{$item}</span>"
								.'</label>'
							;
						}
						return $arr;
					}, []);

					if($showtype === 'view') {
						$inputBox .= '<span class="resp_checkbox">'.implode('', $result).'</span>';
					}
					else {
						$inputBox .= implode(' ', $result);
					}
				break;

				case "radio" :
					$labelArray = explode("|", $data['label_value']);
					$labelIconArray = explode("|", $data['label_icon']);
					$labelCount = count($labelArray)-1;

					for ($j=0; $j<$labelCount; $j++) {
							$labelIconArray[$j] = ($labelIconArray[$j])?$labelIconArray[$j]:'emotion_happy.png';
						if( BOARDID == 'goods_review' && $labelIconArray[$j] ) {
							$iconpath = ROOTPATH.$this->goodsreviewicon.$labelIconArray[$j];
							$iconurl = $this->goodsreviewicon.$labelIconArray[$j];
							if(is_file($iconpath) ) {
								$iconimg = '<img src="'.$iconurl.'" >';
							}else{
								$iconimg='';
							}
						}

						if (is_array($msdata[0])) {
							$checked = ($labelArray[$j] == $msdata[0]['label_value']) ? "checked" : "";
						}else{
							$checked = ( $j == 0 ) ? "checked" : "";
						}
						if ($j > 0) $inputBox .= " ";
						if($showtype == 'view'){
							if($checked ){
								$inputBox .= $iconimg.$labelArray[$j];
							}
						}else{
							$inputBox .= '<label><input type="radio" name="label['.$data['bulkorderform_seq'].'][value][]" class="null" value="'. $labelArray[$j] .'" '. $checked .'> '.$iconimg.$labelArray[$j].'</label>';
						}
					}
				break;
			}

		return $inputBox;
	}

	# 간편결제 API용 게시판 생성 @2016-02-16 pjm modified by hyem @2021-04-30 
	/**
	 * params
	 * 		board_id : 게시판 아이디
	 * 		board_name : 게시판 명
	 * 		category : 
	 */
	public function set_partner_board_create($params){

		$board_id 			= $params["board_id"];
		$board_name			= $params["board_name"];
		$category			= !isset($params["category"]) ? "상품,배송,반품,교환,환불,기타" : $params["category"];

		$this->load->helper('board');
		$this->load->model('membermodel');
		//전체 관리자에게 권한주기
		$sc = array();
		$sc['orderby']	= 'manager_seq';
		$sc['sort']		= 'desc';
		$sc['page']		= '0';
		$sc['perpage']	= '10';
		$mdata = $this->membermodel->admin_manager_list($sc);
		foreach($mdata['result'] as $data){
			$_POST['managerauth'][$data['manager_seq']]		= $data['manager_seq'];
			$_POST['board_view'][$data['manager_seq']]		= '1';
			$_POST['board_view_pw'][$data['manager_seq']]	= '1';
			$_POST['board_act'][$data['manager_seq']]		= '1';
		}

		# 게시판 존재여부 체크
		$sc['whereis']	= ' and id= "'.$board_id.'" ';
		$sc['select']		= ' seq, id, name ';
		$result = $this->managerdataidck($sc);//게시판정보
		if(!$result){

			$params['id']						=  $board_id;
			$params['name']						=  $board_name;
			$params['type']						=  "B";
			$params['auth_read_use']			=  "Y";
			$params['auth_write_use']			=  "Y";
			$params['auth_reply_use']			=  "N";
			$params['auth_cmt_use']				=  "Y";

			$params['autowrite_use']			=  "N";
			$params['file_use']					=  "N";
			$params['onlyimage_use']			=  "N";
			$params['video_use']				=  "N";
			$params['video_type']				=  '400';
			$params['video_screen']				= '400X300';
			$params['video_size']				= '';//화면크기

			$params['file_type']				= '';
			$params['secret_use']				= "N";

			$params['write_show']				= "ID";
			$params['show_name_type']			= "HID";
			$params['show_grade_type']			= "IMG";

			$params['content_default']			= "<p><br></p>";//기본내용
			$params['content_default_mobile']	= "";//모바일-기본내용

			$params['pagenum']					= 20;
			$params['list_show']				=  array("num","writer","date","hit");

			$params['icon_new_day']				= 1;
			$params['icon_hot_visit']			= 30;
			$params['goods_num']				= '';
			$params['goods_review_type']		= '';
			$params['subjectcut']				= 30;
			$params['contcut']					= 200;

			$params['gallery_list_w']			= 250;
			$params['gallery_list_h']			= 250;

			$params['skin']						= "_mbqna";

			//읽기권한 및 접근권한
			$params['auth_read']				= '[memberall]';
			$params['auth_write']				= '[memberall]';
			$params['auth_reply']				= '[]';
			$params['auth_cmt']					= '[]';
			$params['auth_read_use']			= 'Y';
			$params['auth_write_use']			= 'N';
			$params['auth_reply_use']			= 'Y';
			$params['auth_cmt_use']				= 'N';

			$params['category']					= $category;//카테고리 콤마로 구분
			$params['list_show']				= "[subject]";

			$params['gallerycell']				= "20X10";
			$params['write_admin']				= '관리자';
			$params['write_admin_type']			= 'IMG';

			$params['writer_date']				= 'none';
			$params['recommend_type']			= 1;
			$params['cmt_recommend_type']		= 1;
			$params['auth_recommend_use']		= 'N';
			$params['auth_cmt_recommend_use']	= 'N';
			$params['recommend_icon_file']		= 'N';
			$params['none_rec_icon_file']		= 'N';
			$params['r_date']					= date("Y-m-d H:i:s");
			$params['m_date']					= date("Y-m-d H:i:s");

			$result = $this->manager_write($params);

			if($result) {

				//게시판접근권한
				$this->load->model('boardadmin');
				foreach($_POST['managerauth'] as $k => $manager) {
					$this->boardadmin->boardadmin_delete_all($k,$board_id);
					$board_act		= ($_POST['board_act'][$k]>0)?$_POST['board_act'][$k]:'0';
					$board_view		= ($_POST['board_view'][$k]>0)?$_POST['board_view'][$k]:'0';
					$board_view_pw	= ($board_view && $_POST['board_view_pw'][$k]>0)?$_POST['board_view_pw'][$k]:'0';
					$badparams['boardid']			= $board_id;
					$badparams['manager_seq']		= $k;
					$badparams['board_act']			= $board_act;
					$badparams['board_view']		= ($board_view_pw==2)?$board_view_pw:$board_view;
					$badparams['r_manager_seq']		= $this->managerInfo['manager_seq'];
					$badparams['r_date']			= date('Y-m-d H:i:s');
					$this->boardadmin->boardadmin_write($badparams);
					unset($badparams);
				}


				$sc = array();
				$sc['whereis']	= ' and seq= "'.$result.'" ';
				$sc['select']		= ' * ';
				$manager = $this->get_managerdata($sc);//게시판정보
				boarduploaddir($manager);//폴더생성 및 스킨 복사
				//icon 추가

				return true;

			}else{

				return false;

			}
		}
	}


}
/* End of file boardmanager.php */
/* Location: ./app/models/boardmanager */