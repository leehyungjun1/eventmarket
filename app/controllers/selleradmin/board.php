<?php
/**
 * 게시판/게시물 관련 관리자
 * @author gabia
 * @since version 2.0 - 2012.06.29
 */
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/selleradmin_base".EXT);

class Board extends selleradmin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');

		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('id', '게시판ID', 'trim|string|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$this->load->helper(array('text','board','file','download','cookie'));

		$this->load->model('Boardmanager');
		$this->load->model('providermodel');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		$this->load->model('Boardscorelog');
		$this->load->model('Boardindex');//공지용

		$thisfile = $this->uri->rsegments[count($this->uri->rsegments)];
		if($thisfile != 'manager_write' && ($thisfile == 'board' || strstr($thisfile,'write') || strstr($thisfile,'view') ) ) {//게시글 목록/등록/보기
			if ( empty($_GET['id']) ) {//id 없는 경우 공지사항
				$_GET['id'] = 'notice';
			}
			define('BOARDID',$_GET['id']);

			if ( isset($_GET['seq']) ) {//게시글 상세인 경우
				$this->load->model('Boardcomment');
				$querystr = (isset($_GET['reply'])) ? str_replace("seq=".$_GET['seq'],"",str_replace("&seq=".$_GET['seq'],"",str_replace("?reply=".$_GET['reply'],"",str_replace("&reply=".$_GET['reply'],"",str_replace("id=".BOARDID,"",str_replace("&id=".BOARDID,"",$_SERVER['QUERY_STRING'])))))):str_replace("seq=".$_GET['seq'],"",str_replace("&seq=".$_GET['seq'],"",str_replace("id=".BOARDID,"",str_replace("&id=".BOARDID,"",$_SERVER['QUERY_STRING']))));
			}else{
				$querystr = str_replace("id=".BOARDID,"",str_replace("&id=".BOARDID,"",$_SERVER['QUERY_STRING']));
			}

			$querystr = str_replace("&&","&",str_replace("&&&","&",$querystr));
			parse_str($querystr);

			if( in_array(BOARDID, $this->Boardmanager->renewlist)) {
				if($thisfile == 'board' ){
					$file_path = $this->skin.'/board/'.BOARDID.'.html';
				}
				$this->Boardmanager->realboardwriteurl		= '../board/'.BOARDID.'_write?id=';							//게시물등록
				$this->Boardmanager->realboardviewurl		= '../board/'.BOARDID.'_view?id=';							//게시물보기
			}elseif( BOARDID == 'onlinepromotion' || BOARDID == 'offlinepromotion' ){
				if($thisfile == 'board' ){
					$file_path = $this->skin.'/board/promotion.html';
				}
				$this->Boardmanager->realboardwriteurl		= '../board/promotion_write?id=';							//게시물등록
				$this->Boardmanager->realboardviewurl		= '../board/promotion_view?id=';							//게시물보기
			}
			$this->boardurl->lists			= $this->Boardmanager->realboardurl.BOARDID;						//게시물관리
			$this->boardurl->write		= $this->Boardmanager->realboardwriteurl.BOARDID.$querystr;				//게시물등록
			$this->boardurl->modify		= $this->Boardmanager->realboardwriteurl.BOARDID.$querystr.'&seq=';	//게시물수정
			$this->boardurl->view			= $this->Boardmanager->realboardviewurl.BOARDID.$querystr.'&seq=';	//게시물보기
			$this->boardurl->reply			= $this->Boardmanager->realboardwriteurl.BOARDID.$querystr.'&reply=y&seq=';	//게시물답변
			$this->boardurl->querystr	= $querystr;	//검색키
			$this->boardurl->goodsview		= '/goods/view?no=';						//접근권한

			if( BOARDID == 'goods_qna' ) {
				$this->load->model('Goodsqna','Boardmodel');
			}elseif( BOARDID == 'goods_review' ) {
				$this->load->model('Goodsreview','Boardmodel');
				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				foreach ($user_arr as $user){
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$goodsreview_sub[] = $user;
				}
				$this->template->assign('goodsreview_sub', $goodsreview_sub);
			}elseif( BOARDID == 'bulkorder' ) {//대량구매게시판
				$this->load->model('Boardbulkorder','Boardmodel');
				//대량구매 추가양식 정보
				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				foreach ($user_arr as $user){
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$bulkorder_sub[] = $user;
				}
				$this->template->assign('bulkorder_sub', $bulkorder_sub);

			}else{
				$this->load->model('Boardmodel');
			}

		}else{//게시판관리

			if ( isset($_GET['id']) ) {
				define('BOARDID',$_GET['id']);
				$this->boardurl->lists			= $this->Boardmanager->realboardurl.BOARDID;						//게시물관리
				$this->boardurl->write		= $this->Boardmanager->realboardwriteurl.BOARDID;				//게시물등록
				$this->boardurl->modify		= $this->Boardmanager->realboardwriteurl.BOARDID.'&seq=';	//게시물수정
				$this->boardurl->view			= $this->Boardmanager->realboardviewurl.BOARDID.'&seq=';	//게시물보기
				$this->boardurl->reply			= $this->Boardmanager->realboardwriteurl.BOARDID.'&reply=y&seq=';	//게시물답변
				$this->boardurl->goodsview		= '/goods/view?no=';						//접근권한

				if( BOARDID == 'goods_qna' ) {
					$this->load->model('Goodsqna','Boardmodel');
				}elseif( BOARDID == 'goods_review' ) {
					$this->load->model('Goodsreview','Boardmodel');
					$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
					$query = $this->db->query($qry);
					$user_arr = $query -> result_array();
					unset($goodsreview_sub);
					foreach ($user_arr as $user){
						$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
						$goodsreview_sub[] = $user;
					}
					$this->template->assign('goodsreview_sub', $goodsreview_sub);
				}elseif( BOARDID == 'bulkorder' ) {//대량구매게시판
					$this->load->model('Boardbulkorder','Boardmodel');
					//대량구매 추가양식 정보
					$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
					$query = $this->db->query($qry);
					$user_arr = $query -> result_array();
					unset($bulkorder_sub);
					foreach ($user_arr as $user){
						$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
						$bulkorder_sub[] = $user;
					}
					$this->template->assign('bulkorder_sub', $bulkorder_sub);
				}else{
					$this->load->model('Boardmodel');
				}
			}
		}
		$this->joinform = ($this->joinform)?$this->joinform:config_load('joinform');
		$this->board_editor = config_load('board_editor');

		getboardicon();
		/**
		$this->icon_file_img				= $this->Boardmanager->file_icon_src;//첨부파일icon
		$this->icon_image_img		= $this->Boardmanager->img_icon_src;//이미지icon
		$this->icon_video_img			= $this->Boardmanager->video_icon_src;//동영상icon
		$this->icon_mobile_img		= $this->Boardmanager->mobile_icon_src;//모바일icon
		$this->icon_best_img			= $this->Boardmanager->best_icon_src;//best
		$this->icon_best_gray_img	= $this->Boardmanager->best_gray_icon_src;//best_gray
		$this->icon_award_img		= $this->Boardmanager->award_icon_src;//goodsreview

		$this->icon_hidden_img		= $this->Boardmanager->hidden_icon_src;//비밀글icon
		$this->notice_img				= $this->Boardmanager->notice_icon_src;//공지글icon
		$this->re_img						= $this->Boardmanager->re_icon_src;//답변글icon
		$this->blank_img					= $this->Boardmanager->blank_icon_src;//blank


		$this->snst_img					= $this->Boardmanager->snst_icon_src;//twitter
		$this->snsf_img					= $this->Boardmanager->snsf_icon_src;//facebook
		$this->snsm_img					= $this->Boardmanager->snsm_icon_src;//m2day
		$this->snsy_img					= $this->Boardmanager->snsy_icon_src;//요즘
		**/

		define('FILE_PATH', $file_path);
		$this->template->assign('ismobile',$this->_is_mobile_agent);//ismobile
		$this->template->assign('sms_send_use',$sms_send_use);

		//상단 타이틀 및 검색폼
		$searchform = $this->skin.'/board/searchform.html';
		$this->template->define(array("searchform"=>$searchform));
		$this->template->define(array('tpl'=>FILE_PATH));

		if( BOARDID == 'goods_review' ) {
			$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
			$this->template->assign('reserve_goods_review',$reserves['reserve_goods_review']);
		}
		$emoneyform = dirname($this->template_path())."/_emoney.html";
		$this->template->define(array('emoneyform'=>$emoneyform));

		boardalllist();//게시판전체리스트
		//foreach($this->boardmanagerlist as $boardinfo) $boardinfoar[] = $boardinfo['id'];
		//if(!in_array(BOARDID,$boardinfoar)){pageBack('권한이 없습니다.');exit;}

	}

	public function index()
	{
		if( isset($_GET['id']) ) {//개별 게시물리스트
			$this->data_list();//
		}else{
			$this->all_data_list();
		}
		$this->template->print_("tpl");
	}

	public function board()
	{
		if( isset($_GET['id']) ) {//개별 게시물리스트
			$this->data_list();//
		}else{
			$this->all_data_list();
		}
		$this->template->print_("tpl");
	}

	/**
	 * 관리자 > 게시물 등록/수정
	 * @param id : 게시판아이디
	**/
	public function data_list()
	{
		$managersql['whereis']	= ' and id= "'.BOARDID.'" ';
		$managersql['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($managersql);//게시판정보
		$this->manager['write_admin_format'] = $this->manager['write_admin'];

		$this->manager['file_use'] = ($this->manager['file_use'] == 'Y' && $this->manager['onlyimage_use'] == 'Y' )?'img':$this->manager['file_use'];
		if (!isset($this->manager['id'])) pageBack('존재하지 않는 게시판입니다.');
		$this->template->assign('manager',$this->manager);
		if( BOARDID != 'goods_qna' && BOARDID != 'goods_review' && BOARDID != 'bulkorder' && BOARDID != 'mbqna'  && BOARDID != 'gs_seller_qna'  && BOARDID != 'gs_seller_notice'  ) {
			boarduploaddir($this->manager);//게시판 스킨생성 및 복사
			$this->board_dir = $this->Boardmanager->board_skin_dir.BOARDID.'/';
			$this->board_skin = $this->Boardmanager->board_skin_dir.BOARDID.'/'.$this->manager['skin'];
		}

		$this->boardurl->userurl = '../../board/?id='.BOARDID;//사용자보기
		$this->template->assign('boardurl',$this->boardurl);//link url

		//분류리스트 categorylist
		if($this->manager['category']){
			$categorylist = @explode(",",$this->manager['category']);
			$this->template->assign('categorylist',$categorylist);
		}

		$sc['whereis']	= ' and skin_type = "default" and id != "'.BOARDID.'" ';//기본
		$sc['whereis']	.= ' and id not in ("goods_qna","goods_review","bulkorder","gs_seller_qna","gs_seller_notice") ';//기본
		$sc['select']		= ' seq, id, name, totalnum ';
		$boardmanagercopylist = $this->Boardmanager->manager_whereis_list($sc);//게시판정보
		$this->template->assign('boardmanagercopylist',$boardmanagercopylist);//게시판복사목록

		/**
		 * notice setting
		**/
		$idxsc['orderby']			= 'gid';
		$idxsc['sort']				= '';
		$idxsc['notice']				= '1';

		$ndata = $this->Boardindex->idx_list($idxsc);//공지글목록

		/**
		 * list setting
		**/
		$sc							= $_GET;
		if ($sc['search_text'])
		{
			$sc['search_text'] = trim($sc['search_text']);
			$sc['search_text']= stripslashes(htmlspecialchars($sc['search_text']));
		}

		// 평점 정보 추가
		if($_GET['id'] == 'store_review'){
			$sc['score_avg']		= (!empty($_GET['score_avg']))	?	$_GET['score_avg']		:'';
		}

		$sc['orderby']			= (!empty($_GET['orderby'])) ?	$_GET['orderby']:'gid asc';//, m_date asc
		$sc['sort']					= (!empty($_GET['sort'])) ?			$_GET['sort']:' ';
		$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):0;
		$sc['perpage']			= (!empty($_GET['perpage'])) ?	intval($_GET['perpage']):10;
		$sc['perpage']			= (get_cookie('itemlist_qty'.BOARDID))? get_cookie('itemlist_qty'.BOARDID):$sc['perpage'];
		if(!empty($_GET['member_seq'])) $sc['member_seq'] = $_GET['member_seq'];
		if( defined('__SELLERADMIN__') === true ) {
			if(BOARDID == 'gs_seller_qna' ) {//입점사문의게시판인경우
				$sc['mseq']				= '-'.$this->providerInfo['provider_seq'];
			}
		}
		$data = $this->Boardmodel->data_list($sc);//게시글목록

		/**
		 * count setting
		**/
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = @ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $this->Boardmodel->get_item_total_count($sc);
		/**
		 * icon setting
		$this->icon_new_img			= ($this->manager['icon_new_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_new_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$this->manager['icon_new_img'].'?'.time():$this->Boardmanager->new_icon_src;//newicon
		$this->icon_hot_img			= ($this->manager['icon_hot_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_hot_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$this->manager['icon_hot_img'].'?'.time():$this->Boardmanager->hot_icon_src;//hoticon
		$this->icon_review_img			= ($this->manager['icon_review_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_review_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$this->manager['icon_review_img'].'?'.time():$this->Boardmanager->review_icon_src;//hoticon
		**/

		if( defined('__SELLERADMIN__') === true ) {
			$multi_copymove = false;//게시글이동복사 불가
		}else{
			$multi_copymove = true;
		}
		switch ( BOARDID ) {
		 case "goods_qna":
			 $multi_copymove =false;//게시글이동복사 불가
		 break;
		 case "goods_review":
			 $multi_copymove =false;//게시글이동복사 불가
			$this->template->assign('reserves',$reserves);

			$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
			$query = $this->db->query($qry);
			$user_arr = $query -> result_array();
			$this->boardform_arr = $user_arr;
			unset($goodsreview_sub);
			foreach ($user_arr as $user){
				$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
				$goodsreview_sub[] = $user;
			}
			$this->template->assign('goodsreview_sub', $goodsreview_sub);
		 break;
		 case "faq":
			 $multi_copymove =false;//게시글이동복사 불가
		 break;
		 case "mbqna":
		 break;
		  case "gs_seller_qna":
		 break;
		 case "bulkorder"://대량구매게시판
			 $multi_copymove =false;//게시글이동복사 불가
				//대량구매 추가양식 정보
				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				unset($bulkorder_sub);
				foreach ($user_arr as $user){
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$bulkorder_sub[] = $user;
				}
				$this->template->assign('bulkorder_sub', $bulkorder_sub);
			 break;
		 default:
		 break;
		}
		$this->board_lists($ndata, $data, $sc, $noticeloop, $loop );

		$this->template->assign('multi_copymove',$multi_copymove);

		/**
		 * pagin setting
		**/
		$paginlay =  pagingtag($sc['searchcount']	,$sc['perpage'],$this->Boardmanager->realboardurl.BOARDID, getLinkFilter('',array_keys($sc)) );

		if(isset($noticeloop)) $this->template->assign('noticeloop',$noticeloop);
		if(isset($loop)) $this->template->assign('loop',$loop);

		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('sc',$sc);
	}

	/**
	 * 관리자 > 게시물 전체
	 * @param id : 게시판아이디
	**/
	public function all_data_list()
	{
		$this->template->assign('realboardurl',$this->Boardmanager->realboardurl);
		$this->template->assign('realboardwriteurl',$this->Boardmanager->realboardwriteurl);
		$this->template->assign('realboardviewurl',$this->Boardmanager->realboardviewurl);

		$this->manager['name'] = '전체';
		$this->template->assign('manager',$this->manager);

		$limit = (isset($_GET['perpage']))?($_GET['perpage']):5;

		//boardalllist();//게시판전체리스트

		unset($bdwidget, $widgetloop,$boardurl);
		$bdwidget['boardid']	= 'goods_qna';
		$bdwidget['limit']		= $limit;//
		$this->getSellerBoardWidgets($bdwidget, $widgetloop, $name, $totalcount);
		$this->template->assign(array('goodsqnaname'=>$name,'goodsqnatotalcount'=>$totalcount));
		if(isset($widgetloop)) $this->template->assign('goodsqnaloop',$widgetloop);

		unset($bdwidget, $widgetloop,$boardurl);
		$bdwidget['boardid']	= 'goods_review';
		$bdwidget['limit']		= $limit;//
		$this->getSellerBoardWidgets($bdwidget, $widgetloop, $name, $totalcount);
		$this->template->assign(array('goodsreviewname'=>$name,'goodsreviewtotalcount'=>$totalcount));
		if(isset($widgetloop)) $this->template->assign('goodsreviewloop',$widgetloop);

		unset($bdwidget, $widgetloop,$boardurl);


		if( defined('__SELLERADMIN__') === true ) {
		$bdwidget['boardid']	= 'gs_seller_qna';
		}else{
			$bdwidget['boardid']	= 'mbqna';
		}
		$bdwidget['limit']		= $limit;//
		$this->getSellerBoardWidgets($bdwidget, $widgetloop, $name, $totalcount);
		$this->template->assign(array('mbqnaname'=>$name,'mbqnatotalcount'=>$totalcount));
		if(isset($widgetloop)) $this->template->assign('mbqnaloop',$widgetloop);

		unset($bdwidget, $widgetloop,$boardurl);
		if( defined('__SELLERADMIN__') === true ) {
			$bdwidget['boardid']	= 'gs_seller_notice';
		}else{
			$bdwidget['boardid']	= 'notice';
		}
		$bdwidget['limit']		= $limit;//
		$this->getSellerBoardWidgets($bdwidget, $widgetloop, $name, $totalcount);
		$this->template->assign(array('noticename'=>$name,'noticetotalcount'=>$totalcount));
		if(isset($widgetloop)) $this->template->assign('noticeloop',$widgetloop);
	}


	public function board_lists($ndata, $data, $sc, & $noticeloop, & $loop)
	{
		if( BOARDID != 'faq' ) {//공지사항용
			$idx = 0;
			foreach($ndata['result'] as $ndatarow){$idx++;

					$noticesql['whereis']	= ' and gid= "'.$ndatarow['gid'].'" ';
					if( !(BOARDID == 'goods_qna' || BOARDID == 'goods_review'  || BOARDID == 'bulkorder') ) {
						$noticesql['whereis']	.= ' and boardid= "'.BOARDID.'" ';
					}

					if( BOARDID == 'goods_review' ) {
						$noticesql['select']		= ' seq, hit, subject, upload, r_date, m_date, name, ip, category, comment, mseq, mtype, mid, agent, contents, best, file_key_w, adddata, goods_seq ';
					}else{
							$noticesql['select']		= ' seq, hit, subject, upload, r_date, m_date,  name, ip, category, comment, mseq, mtype, mid, agent, contents, file_key_w, goods_seq ';
					}

				$notice = $this->Boardmodel->get_data($noticesql);//게시판목록

				if( $ndatarow['onlynotice'] == '1' && ($ndatarow['onlynotice_sdate'] && $ndatarow['onlynotice_edate']) && !( date('Y-m-d') >=  $ndatarow['onlynotice_sdate'] && date('Y-m-d')  <=  $ndatarow['onlynotice_edate']) ) continue;//공지만노출시 기간체크

				if(isset($notice['seq'])) {
					$notice['number'] = (!isset($_GET['seq']) || $_GET['seq'] != $notice['seq']) ? '<img src="'.$this->notice_img.'" title="공지" >' : ' <span class="now">&gt;&gt;</span> ';//공지

					$this->manager = get_admin_name(array(
						'mtype'=>$notice['mtype'],
						'mseq'=>$notice['mseq'],
						'manager'=>$this->manager,
						'write_admin_format'=>$this->manager['write_admin_format']
					));

					getminfo($this->manager, $notice, $minfo, $boardname);//회원정보
					$notice['name'] = $boardname;
					$notice['subject_real'] = $notice['subject'];

						if($this->manager['icon_new_day'] > 0 &&  date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$notice['r_date']),0,8))) >= date("Ymd") ) {//new
						$notice['iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ';
					}

					if($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $notice['hit'] ) {//조회수
						$notice['iconhot']		= ' <img src="'.$this->icon_hot_img.'" title="hot" align="absmiddle"> ';
					}

					if(getBoardFileck($notice['upload'], $notice['contents']) ) {//첨부파일
						$notice['iconfile']		= ' <img src="'.$this->icon_file_img.'" title="첨부파일" align="absmiddle" > ';
					}
					if(boardisimage($notice['upload'], $notice['contents']) ) {//첨부파일 > image
						$notice['iconimage']		= ' <img src="'.$this->icon_image_img.'" title="첨부파일" align="absmiddle" > ';
							$notice['isphoto']		= '포토';
						}else{
							$notice['isphoto']		= '일반';
					}

					if( $notice['file_key_w'] && uccdomain('fileswf',$notice['file_key_w']) ) {//첨부파일 > video
						$notice['iconvideo']		= ' <img src="'.$this->icon_video_img.'" title="동영상" align="absmiddle" > ';
					}

					if(isMobilecheck($notice['agent'])) {//agent > mobile ckeck
						$notice['iconmobile']		= ' <img src="'.$this->icon_mobile_img.'" title="모바일" align="absmiddle" > ';
					}

					$notice['iconbest']		= ($notice['best'] == 'checked')?' <img src="'.$this->icon_best_img.'" title="best" /> ':' <img src="'.$this->icon_best_gray_img.'" title="not best" /> ';
					$notice['iconaward']		= ($notice['best'] == 'checked')?' <img src="'.$this->icon_award_img.'" title="best" /> ':'';


					$notice['subject']		= ($notice['comment']>0) ?'<span class="hand blue boad_view_btn" viewlink="'.$this->boardurl->view.$notice['seq'].'" board_seq="'.$notice['seq'].'"  board_id="'.BOARDID.'" >'.$notice['subject'].'</span> <span class="cyanblue">('.number_format($notice['comment']).')</span>':'<span class="hand blue boad_view_btn" viewlink="'.$this->boardurl->view.$notice['seq'].'" board_seq="'.$notice['seq'].'"  board_id="'.BOARDID.'" >'.$notice['subject'].'</span>';
					$notice['date']			= substr($notice['r_date'],0,16);//등록일

					if($notice['mseq'] != '-1'){//슈퍼관리자게시글은 제외
						$notice['modifybtn'] = '<input type="button" class="resp_btn v2" name="boad_modify_btn" board_seq="'.$notice['seq'].'"  board_id="'.BOARDID.'" value="수정">';
					}

							//if($this->manager['auth_reply_use'] == 'Y' ) $notice['replaybtn'] = '<span class="btn small valign-middle"><input type="button" name="boad_reply_btn" board_seq="'.$notice['seq'].'"  board_id="'.BOARDID.'" value="답변" /></span>';

							if( BOARDID == 'goods_review' ) {//상품후기 평가노출
					//평가정보노출
					if( $notice['adddata'] ){
						$msubdata = @explode("|",$notice['adddata']);
						if(!$adddata[$msubdatakeyseq])$add=0;
						foreach ($msubdata as $msubdataar){
							$msubdatakey = @explode("^^",$msubdataar);
							$msubdatakeyseq = str_replace("bulkorderform_seq=","",$msubdatakey[1]);
							foreach ($msubdatakey as $msubdataval){if(!$adddata[$msubdatakeyseq])$add=0;
								$msubdatavalreal = @explode("=",$msubdataval);
								if($msubdatavalreal[1]) $adddata[$msubdatakeyseq][$add][$msubdatavalreal[0]] = $msubdatavalreal[1];
							}
							if($adddata[$msubdatakeyseq])$add++;
						}
								$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
						$query = $this->db->query($qry);
						$user_arr = $query -> result_array();
						unset($goodsreview_sub);
						foreach ($user_arr as $user){
							$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']],'view');
							$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
							$goodsreview_sub[] = $user;
						}
						$notice['goodsreview_sub'] = $goodsreview_sub;
					}

							$notice['autoemoneylay']		=  getBoardEmoneyAutotxt($notice, $reviewless);//상품후기 삭제시 회수정보
							}
							$notice['deletebtn'] = '<input type="button" class="resp_btn v3" name="boad_delete_btn" board_seq="'.$notice['seq'].'"  board_id="'.BOARDID.'" value="삭제">';

					if( BOARDID == 'goods_qna' || BOARDID == 'goods_review'  || BOARDID == 'bulkorder'  ) {
						if(!empty($notice['goods_seq']) && $notice['depth'] == 0 ){

							if( BOARDID == 'bulkorder' ) {
								$notice['goodsInfo']		= getBulkorderGoodsinfo($notice, $notice['goods_seq'], 'write');
								if($notice['goodsInfo'][0]) $notice['goodsInfo'] = $notice['goodsInfo'][0];
								$notice['goodsview']	= getBulkorderGoodsinfo($notice, $notice['goods_seq'], 'list');
							}else{
								$notice['goodsInfo']		= getGoodsinfo($notice, $notice['goods_seq'], 'write');
								if($notice['goodsInfo'][0]) $notice['goodsInfo'] = $notice['goodsInfo'][0];
								$notice['goodsview']	= getGoodsinfo($notice, $notice['goods_seq'], 'list');
							}
						} else {
							$notice['goodsview']	= $notice['iconmobile'].$notice['subject'].$notice['iconvideo'].$notice['iconimage'].$notice['iconfile'].$notice['iconnew'].$notice['iconhot'].$notice['iconhidden'];
						}
					}

					$noticeloop[] = $notice;
				}
				unset($notice);
			}
		}

		$idx = 0;
		foreach($data['result'] as $datarow){$idx++;
			$datarow['number'] = ((isset($_GET['seq']) && ($_GET['seq']) != $datarow['seq']) || (!isset($_GET['seq']))) ? $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1: ' <span class="now">&gt;&gt;</span> ';//번호

			$this->manager = get_admin_name(array(
				'mtype'=>$datarow['mtype'],
				'mseq'=>$datarow['mseq'],
				'manager'=>$this->manager,
				'write_admin_format'=>$this->manager['write_admin_format']
			));

			getminfo($this->manager, $datarow, $minfo, $boardname);//회원정보
			$datarow['name'] = $boardname;
			$datarow['subject_real'] = $datarow['subject'];

			$datarow['reply_title']		= ($datarow['re_contents'])?'<span class="blue" >'.getAlert("sy062").'</span>':'<span class="gray" >'.getAlert("sy063").'</span>';//상태 답변완료 답변대기

			if( !(BOARDID == 'notice' || BOARDID == 'faq') ){
			$datarow['emoneylay']	 =  getBoardEmoneybtn($datarow, $this->manager);//마일리지
			}
			if (BOARDID == 'store_reservation'){
				$datarow['replaybtn'] = ($datarow['re_contents'])?'<span class="btn small valign-middle"><input type="button" name="boad_reply_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="답변수정" /></span>':'<span class="btn small valign-middle"><input type="button" name="boad_reply_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="답변등록" /></span>';//관리자만가능
				}

			if( BOARDID == 'faq' ) {//faq hidden 노출여부로 이용됨
				$hiddenckeck	= ($datarow['hidden'] == "1") ? ' checked ':'';//비밀글/노출글 '노출':'미노출'
				$datarow['tdclass']	= ($datarow['hidden'] == "1") ? ' checked-tr-background2  ':'';

				$datarow['hiddenbtn'] = '<input type="checkbox" name="hidden" id="listhidden'.$datarow['seq'].'" class="listhidden" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'"  value="1" '.$hiddenckeck.' /><label for="listhidden'.$datarow['seq'].'">노출</label>';//노출여부
			}else{
				$datarow['tdclass']	= ($datarow['re_contents']) ? ' checked-tr-background2  ':'';
			}

				/**$replysc['whereis']	= ' and gid > '.$datarow['gid'].' and gid < '.(intval($datarow['gid'])+1) . ' ';//답변여부
			//$replysc['select']		= " gid ";
				$datarow['replyor']	= $this->Boardindex->get_data_numrow($replysc);**/
			$replysc['whereis'] = ' and gid > '.$datarow['gid'].' and gid < '.(intval($datarow['gid'])+1).' and parent = '.($datarow['seq']).' ';//답변여부
			$datarow['replyor'] = $this->Boardmodel->get_data_numrow($replysc);

			if( BOARDID == 'goods_review' ){
				if($this->manager['goods_review_type'] == 'INT' && $datarow['reviewcategory'] ){
					$datarow['scorelay'] = getGoodsScore($datarow['score_avg'], $this->manager);
				if(sizeof(explode(",",$datarow['reviewcategory']))>1) $datarow['score_avg_lay'] = 'score_avg';
				}else{
					$datarow['scorelay'] = getGoodsScore($datarow['score'], $this->manager);
			}
				$datarow['autoemoneylay']		=  getBoardEmoneyAutotxt($datarow, $reviewless);//상품후기 삭제시 회수정보

			//평가정보노출
			if($datarow['adddata']){
				unset($adddata);
				$msubdata = @explode("|",$datarow['adddata']);

				if(!$adddata[$msubdatakeyseq])$add=0;
				foreach ($msubdata as $msubdataar){
					$msubdatakey = @explode("^^",$msubdataar);
					$msubdatakeyseq = str_replace("bulkorderform_seq=","",$msubdatakey[1]);
					foreach ($msubdatakey as $msubdataval){if(!$adddata[$msubdatakeyseq])$add=0;
						$msubdatavalreal = @explode("=",$msubdataval);
						if($msubdatavalreal[1]) $adddata[$msubdatakeyseq][$add][$msubdatavalreal[0]] = $msubdatavalreal[1];
					}
					if($adddata[$msubdatakeyseq])$add++;
				}
					$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				unset($goodsreview_sub);
				foreach ($user_arr as $user){
					$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']],'view');
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$goodsreview_sub[] = $user;
				}
				$datarow['goodsreview_sub'] = $goodsreview_sub;
			}
			}else{
				//평점이 있는경우 평점정보 추가
				if( $datarow['score_avg'] ) {
					$img_full = "<img src='/data/skin/store_default/images/design/star_full.gif' title='".$datarow['score_avg']."' />";
					$img_half = "<img src='/data/skin/store_default/images/design/star_half.gif' title='".$datarow['score_avg']."' />";
					$img_empty = "<img src='/data/skin/store_default/images/design/star_empty.gif' title='".$datarow['score_avg']."' />";

					$fullStar = $datarow['score_avg'] / 2;
					$halfStar = $datarow['score_avg'] % 2;

					$totalStar = 0;
					$emptyStar = 0;
					$printStar = "";

					for ($i = 1; $i <= $fullStar; $i++) { $printStar .= $img_full; $totalStar++; }
					for ($i = 1; $i <= $halfStar; $i++) { $printStar .= $img_half; $totalStar++; }

					$emptyStar = 5 - $totalStar;
					for ($i = 1; $i <= $emptyStar; $i++) { $printStar .= $img_empty; }

					$datarow['scorelay'] = $printStar;
				}
				}

			if($datarow['display'] == 1 ){//삭제시
				$datarow['iconnew']	= '';
				$datarow['iconhot']		= '';
				$datarow['iconfile']		= '';
				$datarow['iconimage']	= '';
				$datarow['iconmobile']		= '';
				$datarow['iconhidden'] = '';

				if( BOARDID == 'goods_review' ||  BOARDID == 'goods_qna' ) {
				$datarow['blank']			= ($datarow['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($datarow['depth']-1)*53).'" height="1"><img src="'.$this->re_img.'" title="답변" >':'';//답변
				}else{
				$datarow['blank']			= ($datarow['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($datarow['depth']-1)*13).'" height="1"><img src="'.$this->re_img.'" title="답변" >':'';//답변
			}

				$commentcnt = ($datarow['comment']>0) ? ' <span class="comment cyanblue">('.number_format($datarow['comment']).')</span>':'';
				$datarow['commentcnt'] = $commentcnt;

				if( BOARDID == 'faq' ) {
					$datarow['subject']		= $datarow['blank'].' <span class="hand gray boad_faqview_btn" viewlink="'.$this->boardurl->view.$datarow['seq'].'" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" >삭제되었습니다 ['.substr($datarow['r_date'],0,16).']</span>'.$commentcnt;
			}else{
				$datarow['subject']		= $datarow['blank'].' <span class="hand gray boad_view_btn" viewlink="'.$this->boardurl->view.$datarow['seq'].'" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" >삭제되었습니다 ['.substr($datarow['r_date'],0,16).']</span>'.$commentcnt;
				}
				$datarow['date']			= substr($datarow['r_date'],0,16);
				$datarow['subjectcut']		= "<span class='gray'>삭제되었습니다</span>";

				if($datarow['mseq'] != '-1' && $datarow['replyor'] == 0 && $datarow['comment'] == 0) {//삭제후 답변이나  댓글이 없는 경우 삭제가능
					$datarow['deletebtn'] = '<input type="button" class="resp_btn v3" name="boad_delete_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="삭제">';
				}
			}else{
				$commentcnt = ($datarow['comment']>0) ? ' <span class="comment cyanblue">('.number_format($datarow['comment']).')</span>':'';
				$datarow['commentcnt'] = $commentcnt;

				if($this->manager['icon_new_day'] > 0 &&  date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$datarow['r_date']),0,8))) >= date("Ymd") ) {//new
					$datarow['iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ';
				}else{
					$datarow['iconnew'] ='';
				}

				$datarow['iconhot']		= ($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $datarow['hit']) ? ' <img src="'.$this->icon_hot_img.'" title="hot" align="absmiddle"> ':'';//조회수
				$datarow['iconfile']		= ( getBoardFileck($datarow['upload'], $datarow['contents'])  ) ?' <img src="'.$this->icon_file_img.'" title="첨부파일" align="absmiddle" > ':'';//첨부파일 $datarow['upload'] &&

				$datarow['iconbest']		= ($datarow['best'] == 'checked')?' <img src="'.$this->icon_best_img.'" title="best" /> ':' <img src="'.$this->icon_best_gray_img.'" title="not best" /> ';

				$datarow['iconaward']		= ($datarow['best'] == 'checked')?' <img src="'.$this->icon_award_img.'" title="award" /> ':'';

				if(boardisimage($datarow['upload'],$datarow['contents']) ) {//첨부파일 > image
					$datarow['iconimage']		= ' <img src="'.$this->icon_image_img.'" title="첨부파일" align="absmiddle" > ';
					$datarow['isphoto']		= '포토';
				}else{
					$datarow['isphoto']		= '일반';
				}

  				if( $datarow['file_key_w'] && uccdomain('fileswf',$datarow['file_key_w']) ) {//첨부파일 > video
					$datarow['iconvideo']		= ' <img src="'.$this->icon_video_img.'" title="동영상" align="absmiddle" > ';
				}

				if(isMobilecheck($datarow['agent'])) {//agent > mobile ckeck
					$datarow['iconmobile']		= ' <img src="'.$this->icon_mobile_img.'" title="모바일" align="absmiddle" > ';
				}

				$datarow['iconhidden'] = ($datarow['hidden'] == 1  && BOARDID != 'faq' ) ? ' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ':'';

				$datarow['date']			= substr($datarow['r_date'],0,16);//등록일
				if( BOARDID == 'goods_review' ||  BOARDID == 'goods_qna' ) {
				$datarow['blank']			= ($datarow['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($datarow['depth']-1)*53).'" height="1"><img src="'.$this->re_img.'" title="답변" >':'';//답변
				}else{
				$datarow['blank']			= ($datarow['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($datarow['depth']-1)*13).'" height="1"><img src="'.$this->re_img.'" title="답변" >':'';//답변
			}

			if( BOARDID == 'faq' ) {
					$datarow['subject']		= $datarow['blank'].' <span class="hand blue boad_faqview_btn" viewlink="'.$this->boardurl->view.$datarow['seq'].'" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" >'.$datarow['subject'].'</span>'.$commentcnt;
			}else{
					$datarow['subject']		= $datarow['blank'].' <span class="hand blue boad_view_btn" viewlink="'.$this->boardurl->view.$datarow['seq'].'" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" >'.$datarow['subject'].'</span>'.$commentcnt;
			}

			if($datarow['mseq'] != '-1'){//슈퍼관리자게시글은 제외
				$datarow['modifybtn'] = '<input type="button" class="resp_btn v2"  name="boad_modify_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="수정">';
				$datarow['deletebtn'] = '<input type="button" class="resp_btn v3" name="boad_delete_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="삭제">';
			}
			if( BOARDID == 'bulkorder' || BOARDID == 'mbqna' || BOARDID == 'goods_qna'  || BOARDID == 'gs_seller_qna' ) {
				$datarow['replaybtn'] = ($datarow['re_contents'])?'<input type="button" class="resp_btn active" name="boad_reply_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="수정">':'<input type="button" class="resp_btn active" name="boad_reply_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="등록">';//관리자만가능
			}else{
				if($this->manager['auth_reply_use'] == 'Y') $datarow['replaybtn'] = '<input type="button" class="resp_btn" name="boad_reply_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="답변">';
			}
		}

			# npay 문의글일때 수정/삭제 불가
			if( in_array(BOARDID, array('naverpay_qna','talkbuy_qna')) ) {
				$datarow['modifybtn']  = '';
				$datarow['deletebtn']  = '';

				# 답변은 한번만 등록 가능. 등록 후 수정가능.
				if($datarow['re_contents']){ $datarow['replaybtn'] = ''; }
			}

			if( BOARDID == 'goods_qna' || BOARDID == 'goods_review'  || BOARDID == 'bulkorder'  ) {
				if(!empty($datarow['goods_seq']) && $datarow['depth'] == 0 ){

					if( BOARDID == 'bulkorder' ) {
						$datarow['goodsInfo']		= getBulkorderGoodsinfo($datarow, $datarow['goods_seq'], 'write');
						if($datarow['goodsInfo'][0]) $datarow['goodsInfo'] = $datarow['goodsInfo'][0];
						$datarow['goodsview']	= getBulkorderGoodsinfo($datarow, $datarow['goods_seq'], 'list');
							}else{
						$datarow['goodsInfo']		= getGoodsinfo($datarow, $datarow['goods_seq'], 'write');
						if($datarow['goodsInfo'][0]) $datarow['goodsInfo'] = $datarow['goodsInfo'][0];
						$datarow['goodsview']	= getGoodsinfo($datarow, $datarow['goods_seq'], 'list');
						}
							}else{
					$datarow['goodsview']	= $datarow['iconmobile'].$datarow['subject'].$datarow['iconvideo'].$datarow['iconimage'].$datarow['iconfile'].$datarow['iconnew'].$datarow['iconhot'].$datarow['iconhidden'];
							}
						}

			$datarow['buyertitle']	= ($datarow['order_seq'] || $datarow['npay_product_order_id'] || $datarow['talkbuy_product_order_id'])?'구매':'미구매';

			if( BOARDID == 'faq' ){
				getBoardUploadAllfiles($datarow);//전체첨부파일 가져오기(상단에서 이미지만 추출함)
			}

			$loop[] = $datarow;
		}
	}

	/**
	 * 관리자 > 게시물 보기
	 * @param id : 게시판아이디
	**/
	public function view()
	{

		if	(!in_array($_GET['id'],array('gs_seller_qna','gs_seller_notice','goods_qna','goods_review'))){
			$auth = $this->authmodel->manager_limit_act('board_view');
			if(!$auth){
				openDialogAlert("권한이 없습니다",400,140,'parent','location.reload()');
				die();
			}
		}

		if (!isset($_GET['id'])) pageBack('존재하지 않는 게시판입니다.');
		if (!isset($_GET['seq'])) pageBack('잘못된 접근입니다.');
		$sc['whereis']	= ' and id= "'.BOARDID.'" ';
		$sc['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		$this->manager['write_admin_format'] = $this->manager['write_admin'];
		if (!isset($this->manager['id'])) pageBack('존재하지 않는 게시판입니다.');

		// 입점사 일 경우 작성자 값 fix 삭제 :: 2016-06-27 pjw
		//if( defined('__SELLERADMIN__') === true ) {
			////$this->manager['writetitle'] = $this->providerInfo['provider_name'];
		//}else{
			if($this->manager['write_admin_type'] == 'IMG' ) {
				$this->manager['icon_admin_img_src']		= ($this->manager['write_admin_type'] == 'IMG' && $this->manager['icon_admin_img'] && is_file($this->Boardmanager->board_data_dir.$this->manager['id'].'/'.$this->manager['icon_admin_img']) ) ? $this->Boardmanager->board_data_src.$this->manager['id'].'/'.$this->manager['icon_admin_img'].'?'.time():$this->Boardmanager->board_icon_src.'icon_admin.gif';
				$this->manager['writetitle'] = '<img src="'.$this->manager['icon_admin_img_src'].'" id="icon_admin_img"  align="absmiddle" style="vertical-align:middle;"/>';
			}else{
				$this->manager['writetitle'] = $this->manager['write_admin'];
			}
		//}

		$video_size = explode("X" , $this->manager['video_size']);
		$this->manager['video_size0'] = $video_size[0];
		$this->manager['video_size1'] = $video_size[1];

		$video_size_mobile = explode("X" , $this->manager['video_size_mobile']);
		$this->manager['video_size_mobile0'] = $video_size_mobile[0];
		$this->manager['video_size_mobile1'] = $video_size_mobile[1];

		$sc['whereis']	= ' and seq= "'.$_GET['seq'].'" ';
		//본래게시글 추출@2017-05-12
		if( !(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder') ) {
			$sc['whereis']	.= ' and boardid= "'.BOARDID.'" ';
		}
		$sc['select']		= ' * ';
		$data = $this->Boardmodel->get_data($sc);//게시글

		if( BOARDID == 'goods_review' || BOARDID == 'goods_qna'  || BOARDID == 'bulkorder' ) {
			$data['boardid'] = ($data['boardid'])?$data['boardid']:BOARDID;
		}
		if( defined('__SELLERADMIN__') === true ) {
			$data['cmt_name'] = $this->providerInfo['provider_name'];
		}else{
			$data['cmt_name'] = $this->managerInfo['mname'];
		}
		if (!isset($data['seq'])) pageBack('존재하지 않는 게시물입니다.');


		if( BOARDID == 'bulkorder' ) {
			if( $data['adddata'] ){
			$msubdata = @explode("|",$data['adddata']);
			if(!$adddata[$msubdatakeyseq])$add=0;
			foreach ($msubdata as $msubdataar){
				$msubdatakey = @explode("^^",$msubdataar);
				$msubdatakeyseq = str_replace("bulkorderform_seq=","",$msubdatakey[1]);
				foreach ($msubdatakey as $msubdataval){if(!$adddata[$msubdatakeyseq])$add=0;
					$msubdatavalreal = @explode("=",$msubdataval);
					if($msubdatavalreal[1]) $adddata[$msubdatakeyseq][$add][$msubdatavalreal[0]] = $msubdatavalreal[1];
				}
				if($adddata[$msubdatakeyseq])$add++;
			}

			//대량구매 추가양식 정보
				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
			$query = $this->db->query($qry);
			$user_arr = $query -> result_array();
				foreach ($user_arr as $user){
					if($user['bulkorderform_seq'] == '1' && $user['used'] == 'Y'){//
						$data['person_name_title']	 =  $user['label_title'];
					}elseif($user['bulkorderform_seq'] == '2'  && $user['used'] == 'Y'){//
						$data['person_email_title']	 =  $user['label_title'];
					}elseif($user['bulkorderform_seq'] == '3'  && $user['used'] == 'Y'){//
						$data['person_tel1_title']	 =  $user['label_title'];
					}elseif($user['bulkorderform_seq'] == '4'  && $user['used'] == 'Y'){//
						$data['person_tel2_title']	 =  $user['label_title'];
					}elseif($user['bulkorderform_seq'] == '5'  && $user['used'] == 'Y'){//
						$data['company_title']	 =  $user['label_title'];
					}elseif($user['bulkorderform_seq'] == '6'  && $user['used'] == 'Y'){//
						$data['shipping_date_title']	 =  $user['label_title'];
					}else{
						$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']],'view');
						$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
						$bulkorder_sub[] = $user;
				}
			}
			$this->template->assign('bulkorder_sub', $bulkorder_sub);
		}

		}elseif( BOARDID == 'goods_review' ){//상품후기 추가양식

			if( $data['adddata'] ){
				$msubdata = @explode("|",$data['adddata']);
				if(!$adddata[$msubdatakeyseq])$add=0;
				foreach ($msubdata as $msubdataar){
					$msubdatakey = @explode("^^",$msubdataar);
					$msubdatakeyseq = str_replace("bulkorderform_seq=","",$msubdatakey[1]);
					foreach ($msubdatakey as $msubdataval){if(!$adddata[$msubdatakeyseq])$add=0;
						$msubdatavalreal = @explode("=",$msubdataval);
						if($msubdatavalreal[1]) $adddata[$msubdatakeyseq][$add][$msubdatavalreal[0]] = $msubdatavalreal[1];
					}
					if($adddata[$msubdatakeyseq])$add++;
				}
				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				unset($goodsreview_sub);
				foreach ($user_arr as $user){
					$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']],'view');
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$goodsreview_sub[] = $user;
				}
				$this->template->assign('goodsreview_sub', $goodsreview_sub);
			}
		}


		//파일리스트 filelist
			if(!empty($_GET['reply']) != 'Y') {
			getBoardViewUploadAllfiles($data);
			}

		/**
		 * icon setting
		$this->icon_new_img			= ($this->manager['icon_new_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_new_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$this->manager['icon_new_img'].'?'.time():$this->Boardmanager->new_icon_src;//newicon
		$this->icon_hot_img			= ($this->manager['icon_hot_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_hot_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$this->manager['icon_hot_img'].'?'.time():$this->Boardmanager->hot_icon_src;//hoticon
		**/
		getboardicon();

		if($this->manager['icon_new_day'] > 0 &&  date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$data['r_date']),0,8))) >= date("Ymd") ) {//new
			$data['iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ';
		}

		if($this->manager['icon_new_day'] > 0 &&  date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$data['cmt_date']),0,8))) >= date("Ymd") ) {//new
			$data['cmt_iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ';//댓글최근등록날짜
		}

		if($this->manager['icon_hot_visit'] > 0 &&  $this->manager['icon_hot_visit'] <= $data['hit'] ) {//조회수
			$data['iconhot']		= ' <img src="'.$this->icon_hot_img.'" title="hot" align="absmiddle"> ';
		}

		if($data['upload'] ) {//첨부파일
			$data['iconfile']		= ' <img src="'.$this->icon_file_img.'" title="첨부파일" align="absmiddle" > ';
		}
		if(($data['upload']) && getBoardFileck($data['upload'], $data['contents']) ) {//첨부파일
			$data['iconfile']		= ' <img src="'.$this->icon_file_img.'" title="첨부파일" align="absmiddle" > ';
		}
		if($data['upload']  && boardisimage($data['upload'],$data['contents']) ) {//첨부파일 > image
			$data['iconimage']		= ' <img src="'.$this->icon_image_img.'" title="첨부파일" align="absmiddle" > ';
		}
		if(isMobilecheck($data['agent'])) {//agent > mobile ckeck
			$data['iconmobile']		= ' <img src="'.$this->icon_mobile_img.'" title="모바일" align="absmiddle" > ';
		}

		if($data['hidden'] == 1 ) {//비밀글
			$data['iconhidden'] = ' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ';
		}

		$data['comment']= number_format($data['comment']);
		if($data['display'] == 1 ) {
			$data['subject'] = ' 삭제되었습니다. ['.$data['r_date'].']';
			$data['contents'] = '<span class="gray" >삭제된 글입니다.</span>';
		}

		$data['contents'] = getcontents($data);//xss/csrf
		$data['buyertitle'] = ($data['order_seq'] || $datarow['npay_product_order_id'] || $datarow['talkbuy_product_order_id'])?'구매':'미구매';

		$data['snst'] = $this->snst_img;
		$data['snsf'] = $this->snsf_img;
		$data['snsm'] = $this->snsm_img;
		$data['snsy'] = $this->snsy_img;

		$data['noticeckeck']		= ($data['notice'] == "1") ? 'checked':'';//공지
		$data['hiddenckeck']	= ($data['hidden'] == "1") ? 'checked':'';//비밀글
		$data['subject']			= htmlspecialchars($data['subject']);

		if(BOARDID == 'goods_review' ) {
			$data['iconaward']		= ($data['best'] == 'checked')?' <img src="'.$this->icon_award_img.'" title="best" /> ':'';

			if($this->manager['goods_review_type'] == 'INT' && $data['reviewcategory']){
				$data['scorelay'] = getGoodsScore($data['score_avg'], $this->manager);
				if(sizeof(explode(",",$data['reviewcategory']))>1) $data['score_avg_lay'] = 'score_avg';
			}else{
				$data['scorelay'] = getGoodsScore($data['score'], $this->manager);
		}

			$autoemoneypoint	 =  getBoardEmoneyAutotxt($data, $reviewless);//상품후기 삭제시 회수정보
		}
		$data['emoneyviewlay']	=  getBoardEmoneybtn($data, $this->manager, 'view');//마일리지
		$data['emoneylay']			=  getBoardEmoneybtn($data, $this->manager, 'btn');// 마일리지

		$replysc['whereis'] = ' and gid > '.$data['gid'].' and gid < '.(intval($data['gid'])+1).' and parent = '.($data['seq']).' ';//답변여부
		$data['replyor'] = $this->Boardmodel->get_data_numrow($replysc);

		$this->manager = get_admin_name(array(
			'mtype'=>$data['mtype'],
			'mseq'=>$data['mseq'],
			'manager'=>$this->manager,
			'write_admin_format'=>$this->manager['write_admin_format']
		));

		getminfo($this->manager, $data, $minfo, $mbname,'','board_view');//회원정보
		$data['name'] = $mbname;

		if( defined('__SELLERADMIN__') === true ) {
			// 한번 읽은글은 브라우저를 닫기전까지는 조회수 증가
			$ss_hit_name = 'board_hit_'.BOARDID;
			$boardhitss = $this->session->userdata($ss_hit_name);
			if ( ( !strstr($boardhitss,'['.$_GET['seq'].']') && isset($boardhitss)) || empty($boardhitss)) {
				$boardhitssadd = (isset($boardhitss)) ? $boardhitss.'['.$_GET['seq'].']':'['.$_GET['seq'].']';
				$this->Boardmodel->hit_update($_GET['seq']);
				$this->session->set_userdata($ss_hit_name, $boardhitssadd );
			}
		}

		//상품후기의 평가점수
		if($this->manager['reviewcategory']){
			$reviewcategoryar = @explode(",",$this->manager['reviewcategory']);
			$reviewcategorydataar = @explode(",",$data['reviewcategory']);
			foreach($reviewcategoryar as $k=>$reviewcategory){
				$reviewcategorylistar['idx'] = $k;
				$reviewcategorylistar['title'] = $reviewcategory;
				$reviewcategorylistar['score'] = $reviewcategorydataar[$k];
				$reviewcategorylist[] = $reviewcategorylistar;
			}
			$data['reviewcategorylist'] = $reviewcategorylist;
		}else{
			$reviewcategoryar = @explode(",","평점");
			$reviewcategorydataar = @explode(",","평점");
			foreach($reviewcategoryar as $k=>$reviewcategory){
				$reviewcategorylistar['idx'] = $k;
				$reviewcategorylistar['title'] = $reviewcategory;
				$reviewcategorylistar['score'] = $reviewcategorydataar[$k];
				$reviewcategorylist[] = $reviewcategorylistar;
			}
			$data['reviewcategorylist'] = $reviewcategorylist;
		}

		$data['cmthiddenlay']	= ( $this->Boardmanager->cmthidden == "Y") ? '':' hide';//비밀댓글사용여부

		if(!empty($data['goods_seq'])){
			if( BOARDID == 'bulkorder') {
				if(strstr($this->manager['bulk_show'],'[goods]')){//상품사용시
					$goodsview	= getBulkorderGoodsinfo($data, $data['goods_seq'], 'view');
				}
			}else{
				$goodsview	= getGoodsinfo($data, $data['goods_seq'], 'view');
			}
		}else{
			if( BOARDID == 'bulkorder') {
				if(strstr($this->manager['bulk_show'],'[goods]')){//상품사용시
					$goodsview	= '상품정보가 없습니다.';
				}
			}else{
				$goodsview	= '상품정보가 없습니다.';
			}
		}
		$this->template->assign('goodsview',$goodsview);//상품정보

		$this->boardurl->userviewurl = get_connet_protocol().$_SERVER['HTTP_HOST'].'/board/view?id='.BOARDID.'&seq='.$_GET['seq'];//사용자보기(sns)

		if(isset($_GET['cmtpage'])) {
			$this->boardurl->cmtview = str_replace("&cmtpage=".$_GET['cmtpage'],"",str_replace("&cmtlist=1","&",$this->boardurl->view.$_GET['seq'])).'&cmtlist=1';
		}else{
			$this->boardurl->cmtview = str_replace("&cmtlist=1","&",$this->boardurl->view).$_GET['seq'].'&cmtlist=1';//
		}

		if(BOARDID == 'goods_review' ) {
			//sms 신청여부 및 신청건수
			require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/SMS_send.class.php";
			$sms	= new SMS_SEND();
			$params	= "sms_id=" . $sms->sms_account . '&sms_pw=' . $sms->sms_password;
			$params = makeEncriptParam($params);
			$limit	= ($sms->limit != -1) ? number_format($sms->limit) : 0;
			$sms_chk = $sms->sms_account;
			$smslnk = ' onclick="alert(\'입점판매자는 권한이 없습니다.\');" ';
			$this->template->assign(array('count'=>$limit,'param'=>$params,'chk'=>$sms_chk,'smslnk'=>$smslnk));
		}

		//댓글skin
		$commentskin = dirname($this->template_path()).'/_comment.html';
		$this->template->define(array("commentskin"=>$commentskin));

		//이전글/다음글skin
		$prenextskin = dirname($this->template_path()).'/_prenext.html';
		$this->template->define(array("prenextskin"=>$prenextskin));

		//게시글평가skin
		if( $this->manager['auth_recommend_use'] == 'Y' ){
			$data['is_recommend'] = '_y';
			/**
				$scoresql['whereis']	= ' and boardid= "'.BOARDID.'" ';
				$scoresql['whereis']	.= ' and parent= "'.$data['seq'].'" ';//게시글
				$scoresql['whereis']	.= ' and mseq= "-'.$this->managerInfo['manager_seq'].'" ';
				$getscoredata = $this->Boardscorelog->get_data($scoresql);
				if($getscoredata) $data['is_recommend'] = '_y';
			**/
			$scoreskin = dirname($this->template_path()).'/_score.html';
			$this->template->define(array("scoreskin"=>$scoreskin));
		}

		$reply_manager = get_admin_name(array(
			'mtype'=>$data['re_mtype'],
			'mseq'=>$data['re_mseq'],
			'manager'=>$this->manager,
			'write_admin_format'=>$this->manager['write_admin_format']
		));

		$comment_manager = get_admin_name(array(
			'mtype'=>'',
			'mseq'=>'',
			'manager'=>$this->manager,
			'write_admin_format'=>$this->manager['write_admin_format']
		));

		$this->template->assign('managerwritetitle',$this->manager['writetitle']);
		$this->template->assign('managername',$this->manager['name']);
		$this->template->assign('commentlay',$this->manager['auth_cmt_use']);
		$this->template->assign('replylay',$this->manager['auth_reply_use']);
		$this->template->assign('managerview',$this->manager);
		$this->template->assign('replymanagerview',$reply_manager);
		$this->template->assign('commentmanager',$comment_manager);

		$this->template->assign($data);

		$this->template->assign('boardurl',$this->boardurl);
		$this->getCommentlists($_GET['seq']);//댓글출력
		$this->template->print_("tpl");
	}

	/**
	 * 관리자 > 상품후기 등록/수정
	 * @param id : 게시판아이디
	**/
	public function goods_review_view()
	{
		$this->view();
	}

	/**
	 * 관리자 > 상품문의 등록/수정
	 * @param id : 게시판아이디
	**/
	public function goods_qna_view()
	{
		$this->view();
	}

	/**
	 * 관리자 > 1:1문의 등록/수정
	 * @param id : 게시판아이디
	**/
	public function mbqna_view()
	{
		$this->view();
	}

	/**
	 * 관리자 > 대량구매1:1문의 등록/수정
	 * @param id : 게시판아이디
	**/
	public function bulkorder_view()
	{
		$this->view();
	}

	/**
	 * 관리자 > 입점몰공지
	 * @param id : 게시판아이디
	**/
	public function gs_seller_notice_view()
	{
		$this->view();
	}

	/**
	 * 관리자 > 입점몰공지
	 * @param id : 게시판아이디
	**/
	public function gs_seller_notice_view_main()
	{
		$this->view();

	}

	/**
	 * 관리자 > 입점몰문의
	 * @param id : 게시판아이디
	**/
	public function gs_seller_qna_view()
	{
		$this->view();
	}

	/**
	 * 관리자 > qna 등록/수정
	 * @param id : 게시판아이디
	**/
	public function faq_view()
	{
		$this->view();
	}

	/**
	 * 관리자 > promotion보기
	 * @param id : 게시판아이디
	**/
	public function promotion_view()
	{
		$this->view();
	}



	/**
	 * 관리자 > 게시물 등록/수정
	 * @param id : 게시판아이디
	**/
	public function write()
	{
		$this->template->assign('writeditorjs',true);//에딧터

		if (!isset($_GET['id'])) pageBack('존재하지 않는 게시판입니다.');

		$sc['whereis']	= ' and id= "'.BOARDID.'" ';
		$sc['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		$this->manager['write_admin_format'] = $this->manager['write_admin'];
		if (!isset($this->manager['id'])) pageBack('존재하지 않는 게시판입니다.');

		$this->manager = get_admin_name(array(
			'mtype'=>'',
			'mseq'=>'',
			'manager'=>$this->manager,
			'write_admin_format'=>$this->manager['write_admin_format']
		));

		$this->manager['file_use'] = ($this->manager['file_use'] == 'Y' && $this->manager['onlyimage_use'] == 'Y' )?'img':$this->manager['file_use'];
		$this->template->assign('manager',$this->manager);

		//boardalllist();//게시판전체리스트
		$displayGoods = '';
		if ( isset($_GET['seq']) ) {

			$sc['whereis']	= ' and seq= "'.$_GET['seq'].'" ';
			//본래게시글 추출@2017-05-12
			if( !(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder') ) {
				$sc['whereis']	.= ' and boardid= "'.BOARDID.'" ';
			}
			$sc['select']		= ' * ';
			$data = $this->Boardmodel->get_data($sc);//게시글보기

			//jhs 2017-05-17 수정 & -> &amp;
			$data['contents'] = str_replace("&lt;","&amp;lt;",$data['contents']);
			$data['contents'] = str_replace("&gt;","&amp;gt;",$data['contents']);
			// xxs
			$data['contents'] = getcontents($data);

			if( $data['mseq'] == -1 && !($_GET['reply'] == 'y' && BOARDID == 'goods_qna') ) {
				pageReload('입점사관리자는 권한이 없습니다.');
				exit;
			}

			if( BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder' ) {
				$data['boardid'] = ($data['boardid'])?$data['boardid']:BOARDID;
			}
			if (!isset($data['seq'])) pageBack('존재하지 않는 게시물입니다.');

			$idxsc['select']			= ' * ';
			$idxsc['whereis']		= '  and boardid = "'.BOARDID.'" and gid = "'.$data['gid'].'" ';
			$idxdata = $this->Boardindex->get_data($idxsc);//공지글목록
			if( $idxdata ) {
				$data['onlynotice'] = $idxdata['onlynotice'];
				$data['onlynotice_sdate'] = $idxdata['onlynotice_sdate'];
				$data['onlynotice_edate'] = $idxdata['onlynotice_edate'];
			}

			if($data['tmpcode']){
				$this->session->set_userdata('tmpcode',$data['tmpcode']);
			}else{
				//첨부파일 등록
				$data['tmpcode'] = BOARDID.'^^'.substr(microtime(), 2, 6).'^^'.$data['mseq'];
				$this->session->set_userdata('tmpcode',$data['tmpcode']);
			}

			if(isset($data['goods_seq'])){
				if( BOARDID == 'bulkorder' ) {
					$goodsview	= getBulkorderGoodsinfo($data, $data['goods_seq'], 'view');
				}else{
					$goodsview	= getGoodsinfo($data, $data['goods_seq'], 'view');
				}
			}else{
				$goodsview	= '상품정보가 없습니다.';
			}
			$this->template->assign('goodsview',$goodsview);//상품정보

			getminfo($this->manager, $data, $mdata, $mbname);//회원정보
			$data['real_name'] = ($data['name'])?$data['name']:$mdata['user_name'];
			$data['mbname'] = $mbname;
			$data['name'] = $mbname;

			if (($_GET['reply']) == 'y' && !(BOARDID == 'store_reservation' || BOARDID == 'mbqna' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder' || BOARDID == 'gs_seller_qna') ) {
				$data['subject']		= $this->Boardmanager->board_restr.$data['subject'];//답변
				$data['contents']		= $this->Boardmanager->board_cont_restr.$data['contents'];
				if( defined('__SELLERADMIN__') === true ) {
					$data['name']			= $this->providerInfo['provider_name'];//관리자명자동저장
				}else{
					$data['name']			= $this->managerInfo['mname'];//관리자명자동저장
				}
				$mode = "board_write";
			}else{
				$mode = "board_modify";
			}

			if( BOARDID == 'bulkorder' ) {
				$cfg['order'] = config_load('order');
				$this->template->assign('cfg',$cfg);

				if( $this->config_system['pgCompany'] ){
					$payment_gateway = config_load($this->config_system['pgCompany']);
					$payment_gateway['arrKcpCardCompany'] = code_load('kcpCardCompanyCode');

					foreach($payment_gateway['arrKcpCardCompany'] as $k => $v){
						$payment_gateway['arrCardCompany'][$v['codecd']]=$v['value'];
					}

					if(isset($payment_gateway['payment'])) foreach($payment_gateway['payment'] as $k => $v){
						$payment[$v] = true;
					}
					$this->template->assign('payment',$payment);
				}

				$msubdata = @explode("|",$data['adddata']);
				if(!$adddata[$msubdatakeyseq])$add=0;
				foreach ($msubdata as $msubdataar){
					$msubdatakey = @explode("^^",$msubdataar);
					$msubdatakeyseq = str_replace("bulkorderform_seq=","",$msubdatakey[1]);
					foreach ($msubdatakey as $msubdataval){if(!$adddata[$msubdatakeyseq])$add=0;
						$msubdatavalreal = @explode("=",$msubdataval);
						if($msubdatavalreal[1]) $adddata[$msubdatakeyseq][$add][$msubdatavalreal[0]] = $msubdatavalreal[1];
					}
					if($adddata[$msubdatakeyseq])$add++;
				}

				//대량구매 추가양식 정보
				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				foreach ($user_arr as $user){
					if (isset($_GET['reply']) == 'Y' ){//
						if($user['bulkorderform_seq'] == '1' && $user['used'] == 'Y'){//
							$data['person_name_title']	 =  $user['label_title'];
						}elseif($user['bulkorderform_seq'] == '2'  && $user['used'] == 'Y'){//
							$data['person_email_title']	 =  $user['label_title'];
						}elseif($user['bulkorderform_seq'] == '3'  && $user['used'] == 'Y'){//
							$data['person_tel1_title']	 =  $user['label_title'];
						}elseif($user['bulkorderform_seq'] == '4'  && $user['used'] == 'Y'){//
							$data['person_tel2_title']	 =  $user['label_title'];
						}elseif($user['bulkorderform_seq'] == '5'  && $user['used'] == 'Y'){//
							$data['company_title']	 =  $user['label_title'];
						}elseif($user['bulkorderform_seq'] == '6'  && $user['used'] == 'Y'){//
							$data['shipping_date_title']	 =  $user['label_title'];
						}else{
							$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']],'view');
							$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
							$bulkorder_sub[] = $user;
						}
					}else{
						$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']]);
						$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
						$bulkorder_sub[] = $user;
					}
				}
				$this->template->assign('bulkorder_sub', $bulkorder_sub);

			}elseif( BOARDID == 'goods_review' ) {

				$this->template->assign('reviewcategoryloop', @explode("|",$data['reviewcategory']));
				$msubdata = @explode("|",$data['adddata']);
				if(!$adddata[$msubdatakeyseq])$add=0;
				foreach ($msubdata as $msubdataar){
					$msubdatakey = @explode("^^",$msubdataar);
					$msubdatakeyseq = str_replace("bulkorderform_seq=","",$msubdatakey[1]);
					foreach ($msubdatakey as $msubdataval){if(!$adddata[$msubdatakeyseq])$add=0;
						$msubdatavalreal = @explode("=",$msubdataval);
						if($msubdatavalreal[1]) $adddata[$msubdatakeyseq][$add][$msubdatavalreal[0]] = $msubdatavalreal[1];
					}
					if($adddata[$msubdatakeyseq])$add++;
				}

				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				unset($goodsreview_sub);
				foreach ($user_arr as $user){
					if (isset($_GET['reply']) == 'Y' ){//
						$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']],'view');
						$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
						$goodsreview_sub[] = $user;
					}else{
						$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']]);
						$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
						$goodsreview_sub[] = $user;
					}
				}
				$this->template->assign('goodsreview_sub', $goodsreview_sub);
			}

			$data['subject']			= htmlspecialchars($data['subject']);
			$data['hit']					= number_format($data['hit']);

			if(!empty($data['goods_seq'])){
				if( BOARDID == 'bulkorder' ) {
					$displayGoods = getBulkorderGoodsinfo($data, $data['goods_seq'].','.$_GET['goods_seq'], 'write');
				}else{
					$displayGoods = getGoodsinfo($data, $data['goods_seq'].','.$_GET['goods_seq'], 'write');
				}
			}

			//파일리스트 filelist
			if( $_GET['reply'] == 'y'  && (BOARDID == 'mbqna' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder' || BOARDID == 'gs_seller_qna') ) {//답변글첨부파일
				unset($realfilelist);
				//본문내용에서 가져오기 제거 @2016-01-29
				/**
				@preg_match_all("/<img[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i",$data['re_contents'],$list_image);
				foreach($list_image[1] as $allfilenamear){
					$filenamear = @explode(" ",$allfilenamear);
					$filenamear = $filenamear[0];
					$filelistar = @explode("/",$filenamear);
					$thumbimg		= @end($filelistar);
					$realfile		= (strstr($thumbimg,'temp_') && is_file($this->Boardmodel->upload_path.str_replace('temp_','',$thumbimg)))?str_replace('temp_','',$thumbimg):$thumbimg;
					$writefilesize = get_file_info($this->Boardmodel->upload_path.$realfile, 'size');
					$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$realfile);
					$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
					$orignalfile	= $filenamear;
					$sizefile		= $writefilesize['size'];
					$typefile		= @end(explode('.', $realfile));//확장자추출
					if($writefilesize){
						$data['filelist'][] = array('orignfile'=>$realfile,'realfilename'=>$realfile,'realfile'=>$this->Boardmodel->upload_src.$realfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>$this->Boardmodel->upload_path.$realfile,'realfileurl'=>$this->Boardmodel->upload_src.$realfile,'realthumbfiledir'=>$this->Boardmodel->upload_path.$thumbimg,'realthumbfileurl'=>$this->Boardmodel->upload_src.$thumbimg,'realsizefile'=>getSizeFormat($sizefile,1));
					}else{
						$writefilesize = get_file_info(ROOTPATH.$orignalfile, 'size');
						$filetypetmp = @getimagesize(ROOTPATH.$orignalfile);
						$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
						$orignalfile	= $filenamear;
						$sizefile		= $writefilesize['size'];
						$typefile		= @end(explode('.', $realfile));//확장자추출
						if( $writefilesize ){//다른폴더인경우(이전등의사유로)
							$data['filelist'][] = array('orignfile'=>$realfile,'realfilename'=>$realfile,'realfile'=>$orignalfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>ROOTPATH.$orignalfile,'realfileurl'=>$orignalfile,'realthumbfiledir'=>ROOTPATH.$thumbimg,'realthumbfileurl'=>$thumbimg,'realsizefile'=>getSizeFormat($sizefile,1));
						}
					}
				}
				**/
				if($data['re_upload']){
					$uploadar = @explode("|",$data['re_upload']);
					foreach($uploadar as $filenamear){
						$filelistar = @explode("^^",$filenamear);
						@list($realfile, $orignalfile, $sizefile, $typefile) = $filelistar;
						if(preg_match("/[\xA1-\xFE\xA1-\xFE]/",$realfile)) {//데이타이전->한글파일명처리
							$realfilename = iconv('utf-8','cp949',$realfile);
							$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$realfilename);
							$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
							if(empty($typefile)) {
								if(!$filetypetmp['mime']){
									$typefile =end(explode('.', $realfile));//확장자추출
								}else{
									$typefile =$filetypetmp['mime'];
								}
							}

							if(!$sizefile) {
								$realfile_temp			= @explode("/",$realfile);
								$orignalfile		= @end($realfile_temp);
							}

							if(!$sizefile) {
								$writefilesize = get_file_info($this->Boardmodel->upload_path.$realfilename, 'size');
								$sizefile		= $writefilesize['size'];
							}
						}

						if(is_file($this->Boardmodel->upload_path.$realfile) || is_file($this->Boardmodel->upload_path.$realfilename)) {

							$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$realfile);
							$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
							if(empty($typefile)) {
								if(!$filetypetmp['mime']){
									$typefile =end(explode('.', $realfile));//확장자추출
								}else{
									$typefile =$filetypetmp['mime'];
								}
							}

							if(!$sizefile) {
								$realfile_temp			= @explode("/",$realfile);
								$orignalfile		= @end($realfile_temp);
							}

							if(!$sizefile) {
								$writefilesize = get_file_info($this->Boardmodel->upload_path.$realfile, 'size');
								$sizefile		= $writefilesize['size'];
							}
							if(is_array($data['filelist'])) {
								$usefile = false;
								foreach($data['filelist'] as $realfilenew) {
									if($realfilenew['orignfile'] == $realfile) {$usefile=true;break;}
								}
								if(!$usefile) {
									$data['filelist'][] = array('orignfile'=>$orignalfile,'realfilename'=>$realfile,'realfile'=>$this->Boardmodel->upload_src.$realfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>$this->Boardmodel->upload_path.$realfile,'realfileurl'=>$this->Boardmodel->upload_src.$realfile,'realthumbfiledir'=>$this->Boardmodel->upload_path.$thumbimg,'realthumbfileurl'=>$this->Boardmodel->upload_src.$thumbimg,'realsizefile'=>getSizeFormat($sizefile,1));
								}
							} else {
								$data['filelist'][] = array('orignfile'=>$orignalfile,'realfilename'=>$realfile,'realfile'=>$this->Boardmodel->upload_src.$realfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>$this->Boardmodel->upload_path.$realfile,'realfileurl'=>$this->Boardmodel->upload_src.$realfile,'realthumbfiledir'=>$this->Boardmodel->upload_path.$thumbimg,'realthumbfileurl'=>$this->Boardmodel->upload_src.$thumbimg,'realsizefile'=>getSizeFormat($sizefile,1));
							}
						}
					}
				}
			}else{//원본글첨부파일
				//본문내용에서 가져오기 제거 @2016-01-29
				/**
				@preg_match_all("/<img[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i",$data['contents'],$list_image);
				foreach($list_image[1] as $allfilenamear){
					$filenamear = @explode(" ",$allfilenamear);
					$filenamear = $filenamear[0];
					$filelistar = @explode("/",$filenamear);
					$thumbimg		= @end($filelistar);
					$realfile		= (strstr($thumbimg,'temp_') && is_file($this->Boardmodel->upload_path.str_replace('temp_','',$thumbimg)))?str_replace('temp_','',$thumbimg):$thumbimg;
					$writefilesize = get_file_info($this->Boardmodel->upload_path.$realfile, 'size');
					$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$realfile);
					$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
					$orignalfile	= $filenamear;
					$sizefile		= $writefilesize['size'];
					$typefile		= @end(explode('.', $realfile));//확장자추출
					if($writefilesize){
						$data['filelist'][] = array('orignfile'=>$realfile,'realfilename'=>$realfile,'realfile'=>$this->Boardmodel->upload_src.$realfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>$this->Boardmodel->upload_path.$realfile,'realfileurl'=>$this->Boardmodel->upload_src.$realfile,'realthumbfiledir'=>$this->Boardmodel->upload_path.$thumbimg,'realthumbfileurl'=>$this->Boardmodel->upload_src.$thumbimg,'realsizefile'=>getSizeFormat($sizefile,1));
					}else{
						$writefilesize = get_file_info(ROOTPATH.$orignalfile, 'size');
						$filetypetmp = @getimagesize(ROOTPATH.$orignalfile);
						$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
						$orignalfile	= $filenamear;
						$sizefile		= $writefilesize['size'];
						$typefile		= @end(explode('.', $realfile));//확장자추출
						if( $writefilesize ){//다른폴더인경우(이전등의사유로)
							$data['filelist'][] = array('orignfile'=>$realfile,'realfilename'=>$realfile,'realfile'=>$orignalfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>ROOTPATH.$orignalfile,'realfileurl'=>$orignalfile,'realthumbfiledir'=>ROOTPATH.$thumbimg,'realthumbfileurl'=>$thumbimg,'realsizefile'=>getSizeFormat($sizefile,1));
						}
					}
				}
				**/
				unset($realfilelist);
				if( empty($_GET['reply']) ) {
					if($data['upload']){
						$uploadar = @explode("|",$data['upload']);
						foreach($uploadar as $filenamear){
							$filelistar = @explode("^^",$filenamear);
							@list($realfile, $orignalfile, $sizefile, $typefile) = $filelistar;
							if(preg_match("/[\xA1-\xFE\xA1-\xFE]/",$realfile)) {//데이타이전->한글파일명처리
								$realfilename = iconv('utf-8','cp949',$realfile);
								$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$realfilename);
								$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
								if(empty($typefile)) {
									if(!$filetypetmp['mime']){
										$typefile =end(explode('.', $realfile));//확장자추출
									}else{
										$typefile =$filetypetmp['mime'];
									}
								}

								if(!$sizefile) {
									$realfile_temp			= @explode("/",$realfile);
									$orignalfile		= @end($realfile_temp);
								}

								if(!$sizefile) {
									$writefilesize = get_file_info($this->Boardmodel->upload_path.$realfilename, 'size');
									$sizefile		= $writefilesize['size'];
								}
							}

							if(is_file($this->Boardmodel->upload_path.$realfile) || is_file($this->Boardmodel->upload_path.$realfilename)) {

								$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$realfile);
								$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
								if(empty($typefile)) {
									if(!$filetypetmp['mime']){
										$typefile =end(explode('.', $realfile));//확장자추출
									}else{
										$typefile =$filetypetmp['mime'];
									}
								}

								if(!$sizefile) {
									$realfile_temp			= @explode("/",$realfile);
									$orignalfile		= @end($realfile_temp);
								}

								if(!$sizefile) {
									$writefilesize = get_file_info($this->Boardmodel->upload_path.$realfile, 'size');
									$sizefile		= $writefilesize['size'];
								}
								if(is_array($data['filelist'])) {
									$usefile = false;
									foreach($data['filelist'] as $realfilenew) {
										if($realfilenew['orignfile'] == $realfile) {$usefile=true;break;}
									}
									if(!$usefile) {
										$data['filelist'][] = array('orignfile'=>$orignalfile,'realfilename'=>$realfile,'realfile'=>$this->Boardmodel->upload_src.$realfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>$this->Boardmodel->upload_path.$realfile,'realfileurl'=>$this->Boardmodel->upload_src.$realfile,'realthumbfiledir'=>$this->Boardmodel->upload_path.$thumbimg,'realthumbfileurl'=>$this->Boardmodel->upload_src.$thumbimg,'realsizefile'=>getSizeFormat($sizefile,1));
									}
								} else {
									$data['filelist'][] = array('orignfile'=>$orignalfile,'realfilename'=>$realfile,'realfile'=>$this->Boardmodel->upload_src.$realfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>$this->Boardmodel->upload_path.$realfile,'realfileurl'=>$this->Boardmodel->upload_src.$realfile,'realthumbfiledir'=>$this->Boardmodel->upload_path.$thumbimg,'realthumbfileurl'=>$this->Boardmodel->upload_src.$thumbimg,'realsizefile'=>getSizeFormat($sizefile,1));
								}
							}
						}
					}
				}
			}

			$data['noticeckeck']		= ($data['notice'] == "1") ? 'checked':'';//공지
			$data['hiddenlay']	= ( $this->manager['secret_use'] == "Y" || $this->manager['secret_use'] == "A" ) ? '':' hide';//비밀글사용여부
			$data['hiddenckeck']	= ($data['hidden'] == "1") ? 'checked':'';//비밀글/노출글

			$this->boardurl->view = $this->boardurl->view.$_GET['seq'];//

			if(BOARDID == 'goods_review' ) {
				$data['scorelay']		= getGoodsScore($data['score'], $this->manager, 'write');
			}

			//sms 신청여부 및 신청건수
			$masterauth = config_load('master');
			$sms_id = $this->config_system['service']['sms_id'];
			$sms_api_key = $masterauth['sms_auth'];
			if( $sms_id && $sms_api_key ) {
				require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/SMS_send.class.php";
				$sms	= new SMS_SEND();
				$params	= "sms_id=" . $sms->sms_account . '&sms_pw=' . $sms->sms_password;
				$params = makeEncriptParam($params);
				$limit	= ($sms->limit != -1) ? number_format($sms->limit) : 0;
				$sms_chk = $sms->sms_account;
				$smslnk = ' onclick="window.open(\'../member/sms_charge\')" ';
				$this->template->assign(array('count'=>$limit,'param'=>$params,'chk'=>$sms_chk,'smslnk'=>$smslnk));
			}else{
				$this->template->assign(array('count'=>0));
			}

		} else {//등록

			if( $this->manager['content_default'] ) {//기본내용
				$this->template->assign('contents',$this->manager['content_default']);
			}

			$mode = "board_write";

			$data['filelist'] = '';//파일리스트 filelist
			$data['noticeckeck'] = '';
			$data['hiddenlay']	= ( $this->manager['secret_use'] == "Y" || $this->manager['secret_use'] == "A" ) ? '':' hide';//비밀글사용여부
			$data['hiddenckeck']	= ($data['hidden'] == "1" || (BOARDID == "faq") ) ? 'checked':'';//비밀글/노출글

			if( defined('__SELLERADMIN__') === true ) {
				if( isset($this->providerInfo['provider_seq']) ) {
					$data['name']	= $this->providerInfo['provider_name'];
					$data['pw']		= $this->providerInfo['password'];
					$data['email']	= $this->providerInfo['email'];
					$data['tel1']		= $this->providerInfo['phone'];
					$data['tel2']		= $this->providerInfo['cellphone'];
				}
			}

			if(BOARDID == 'goods_review' ) {
				$data['scorelay']		= getGoodsScore('', $this->manager, 'write');
			}
			if( defined('__SELLERADMIN__') === true ) {
				$data['name'] = $this->providerInfo['provider_name'];//관리자명자동저장
			}else{
				$data['name'] = $this->managerInfo['mname'];//관리자명자동저장
			}

			//첨부파일 등록
			$this->session->unset_userdata('tmpcode');
			if( defined('__SELLERADMIN__') === true ) {
				$tmpcode = BOARDID.'^^'.substr(microtime(), 2, 6).'^^'.$this->providerInfo['provider_seq'];
			}else{
				$tmpcode = BOARDID.'^^'.substr(microtime(), 2, 6).'^^'.$this->userInfo['member_seq'];
			}
			$this->session->set_userdata('tmpcode',$tmpcode);

			if( BOARDID == 'bulkorder' ) {
				$cfg['order'] = config_load('order');
				$this->template->assign('cfg',$cfg);
				if( $this->config_system['pgCompany'] ){
					$payment_gateway = config_load($this->config_system['pgCompany']);
					$payment_gateway['arrKcpCardCompany'] = code_load('kcpCardCompanyCode');

					foreach($payment_gateway['arrKcpCardCompany'] as $k => $v){
						$payment_gateway['arrCardCompany'][$v['codecd']]=$v['value'];
					}

					if(isset($payment_gateway['payment'])) foreach($payment_gateway['payment'] as $k => $v){
						$payment[$v] = true;
					}
					$this->template->assign('payment',$payment);
				}
				//대량구매 추가양식 정보
				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				foreach ($user_arr as $user){
					$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$msubdata);
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$bulkorder_sub[] = $user;
				}
				$this->template->assign('bulkorder_sub', $bulkorder_sub);

			}elseif( BOARDID == 'goods_review' ) {

				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				unset($goodsreview_sub);
				foreach ($user_arr as $user){
					$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$msubdata);
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$goodsreview_sub[] = $user;
			}
				$this->template->assign('goodsreview_sub', $goodsreview_sub);

			}

			if(!empty($_GET['goods_seq'])){
				if( BOARDID == 'bulkorder' ) {
					$displayGoods = getBulkorderGoodsinfo('', $_GET['goods_seq'], 'write');
				}else{
					$displayGoods = getGoodsinfo('', $_GET['goods_seq'], 'write');
				}
			}

		}

		if ( BOARDID == 'goods_review' ) {
			$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
			$this->template->assign("reserves",$reserves);
		}

		if($data['videotmpcode']){
			$this->session->set_userdata('boardvideotmpcode',$data['videotmpcode']);
		}else{
			if($this->manager['video_use'] == 'Y' ){//사용중인경우
				$boardvideotmpcode = substr(microtime(), 2, 8);
				$this->session->set_userdata('boardvideotmpcode',$boardvideotmpcode);
			}
		}

		//분류리스트 categorylist
		if($this->manager['category']){
			$categorylist = @explode(",",$this->manager['category']);
			$data['categorylist'] = $categorylist;
		}

		//상품후기 > 평가점수
		if($this->manager['reviewcategory']){
			$reviewcategoryar = @explode(",",$this->manager['reviewcategory']);
			$reviewcategorydataar = @explode(",",$data['reviewcategory']);
			foreach($reviewcategoryar as $k=>$reviewcategory){
				$reviewcategorylistar['idx'] = $k;
				$reviewcategorylistar['title'] = $reviewcategory;
				$reviewcategorylistar['score'] = $reviewcategorydataar[$k];
				$reviewcategorylist[] = $reviewcategorylistar;
			}
			$data['reviewcategorylist'] = $reviewcategorylist;
		}

		//동영상연결(기본 파일찾기)
		$this->template->assign("uccdomainscript",uccdomain('','',$this->manager));

		if( !(BOARDID == 'notice' || BOARDID == 'faq') ){
			$data['emoneyuse']	 =  getBoardEmoneybtn($data, $this->manager,'use');//마일리지지급여부
		}

		$backtype = $this->session->userdata('backtype');
		$this->template->assign('backtype',$backtype);
		$this->template->assign($data);
		$this->template->assign('mode',$mode);
		$this->boardurl->lists = $this->boardurl->lists;

		$this->template->assign('displayGoods',$displayGoods);//상품정보

		//공지글/비밀글 폼
		$noticehidden = dirname($this->template_path())."/_notice_hidden.html";
		$this->template->define(array('noticehidden'=>$noticehidden));

		$this->template->assign('provider_seq',$this->providerInfo['provider_seq']);
		$this->template->assign('boardurl',$this->boardurl);
		$this->template->print_("tpl");
	}


	/**
	 * 관리자 > 상품후기 등록/수정
	 * @param id : 게시판아이디
	**/
	public function goods_review_write()
	{
		$this->write();
	}

	/**
	 * 관리자 > 상품문의 등록/수정
	 * @param id : 게시판아이디
	**/
	public function goods_qna_write()
	{
		$this->write();
	}

	/**
	 * 관리자 > 1:1문의 등록/수정
	 * @param id : 게시판아이디
	**/
	public function mbqna_write()
	{
		$this->write();
	}

	/**
	 * 관리자 > 대량구매1:1문의 등록/수정
	 * @param id : 게시판아이디
	**/
	public function bulkorder_write()
	{
		$this->write();
	}


	/**
	 * 관리자 > 입점몰공지
	 * @param id : 게시판아이디
	**/
	public function gs_seller_notice_write()
	{
		$this->write();
	}

	/**
	 * 관리자 > 입점몰문의
	 * @param id : 게시판아이디
	**/
	public function gs_seller_qna_write()
	{
		$this->write();
	}

	/**
	 * 관리자 > qna 등록/수정
	 * @param id : 게시판아이디
	**/
	public function faq_write()
	{
		$this->write();
	}



	/**
	 * 관리자 > promotion 등록/수정
	 * @param id : 게시판아이디
	**/
	public function promotion_write()
	{
		$this->write();
	}


	/**
	 * 관리자 > 상점 후기 등록
	 * @param id : 게시판아이디
	**/
	public function store_review_write()
	{
		$this->write();
	}

	/**
	 * 관리자 > 예약 등록
	 * @param id : 게시판아이디
	**/
	public function store_reservation_write()
	{
		$this->write();
	}


	/**
	 * 게시판 리스트
	 * @
	**/
	public function main()
	{

		$skinlist = BoardManagerskinlist('');//스킨폴더정보
		$this->template->assign('managerurl',$this->Boardmanager->managerurl);

		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'type desc, seq';
		$sc['sort']					= (isset($_GET['sort'])) ?			$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):'10';
		$sc['perpage']			= (get_cookie('itemlist_qty_manager'))? get_cookie('itemlist_qty_manager'):$sc['perpage'];

		if ($_GET['search_text'])
		{
			$sc['search_text'] = trim($_GET['search_text']);
			$sc['search_text']= stripslashes(htmlspecialchars($sc['search_text']));
		}

		$sc['skin']					= (isset($_GET['skin'])) ?				 $_GET['skin']:"";

		$data = $this->Boardmanager->manager_list($sc);//게시판목록
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = @ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $this->Boardmanager->get_item_total_count($sc);
		$idx = 0;
		foreach($data['result'] as $datarow){$idx++;
			$datarow['number'] = ((isset($_GET['seq']) && ($_GET['seq']) != $datarow['seq']) || (!isset($_GET['seq']))) ? $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1: ' <span class="now">&gt;&gt;</span> ';//번호
			if($datarow['skin_type'] == 'goods'){
				$datarow['skintitle'] = "상품기본형";
			}elseif($datarow['id'] == 'mbqna' || $datarow['id'] == 'bulkorder'  || $datarow['id'] == 'gs_seller_qna' || $datarow['id'] == 'store_reservation'){
				$datarow['skintitle'] = "1:1문의형";
			}elseif($datarow['id'] == 'faq'){
				$datarow['skintitle'] = "FAQ형";
			}elseif($datarow['id'] == 'store_review'){
				$datarow['skintitle'] = "상점기본형";
			}else{
				$datarow['skintitle'] = $skinlist[$datarow['skin']];//스킨
			}
			$datarow['configval'] = '<b>읽기</b> : '.$datarow['auth_read_title'].' / <b>쓰기</b> : '.$datarow['auth_write_title'].' / <b>답글</b> : '.$datarow['auth_reply_use_title'].' / <b>댓글</b> :   '.$datarow['auth_cmt_use_title'].' ';

			$datarow['configvalread']	= $datarow['auth_read_title'];
			$datarow['configvalwrite']	= $datarow['auth_write_title'];
			$datarow['configvalreply']	= $datarow['auth_reply_use_title'];
			$datarow['configvalcmt']		= $datarow['auth_cmt_use_title'].' ';

			$datarow['totalnum'] = number_format($datarow['totalnum']);

			$datarow['userurl']			= '../../board/?id='.$datarow['id'];//사용자보기
			$datarow['dataurl']			= $this->Boardmanager->realboardurl.$datarow['id'];//게시글관리
			if( !$this->isplusfreenot && $datarow['id'] == 'bulkorder' ){
				$datarow['managermodifybtn']	= '<span class="btn small valign-middle"><input type="button" name="bulkorder_free_btn" value="수정" /></span>';//수정
			}else{
				$datarow['managermodifybtn']	= '<span class="btn small valign-middle"><input type="button" name="manager_modify_btn" value="수정" board_seq="'.$datarow['seq'].'"  board_id="'.$datarow['id'].'" board_name="'.$datarow['name'].'"  /></span>';//수정
			}
			$datarow['managercopybtn'] = ($datarow['skin_type'] != 'goods' && $datarow['id'] != 'mbqna' && $datarow['id'] != 'bulkorder' && $datarow['id'] != 'faq' && $datarow['id'] != 'gs_seller_qna' && $datarow['id'] != 'gs_seller_notice' && $datarow['id'] != 'store_review' && $datarow['id'] != 'store_reservation') ? '<span class="btn small valign-middle"><input type="button" name="boardmanagercopybtn"  board_seq="'.$datarow['seq'].'"  board_id="'.$datarow['id'].'" board_name="'.$datarow['name'].'"  value="복사" /></span>':'';//상품일반 그외의경우 복사가능
			$datarow['managerdeletebtn'] = ($datarow['type'] == 'A') ? '<span class="btn small valign-middle"><input type="button" name="boarddelete" board_seq="'.$datarow['seq'].'"  board_id="'.$datarow['id'].'" board_name="'.$datarow['name'].'" value="삭제" /></span>':'';//추가인 경우 삭제가능

			$dataloop[] = $datarow;
			boarduploaddir($datarow);//폴더생성 및 스킨 복사
		}

		//boardalllist();//게시판전체리스트

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('board');
		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		$this->template->assign('skinlist',$skinlist);//스킨폴더정보
		if(isset($dataloop)) $this->template->assign('loop',$dataloop);

		$paginlay = pagingtag($sc['searchcount']	,$sc['perpage'],$this->Boardmanager->managerurl.'?', getLinkFilter('',array_keys($sc)) );
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);

		$this->template->assign('use_board_cnt',$sc['totalcount']);

		$this->template->assign('perpage',$sc['perpage']);
		$this->template->assign('sc',$sc);

		$this->template->print_("tpl");
	}

	/**
	 * 게시판 등록/수정
	 * @param id : 게시판아이디
	**/
	public function manager_write()
	{
	}

	/**
	 * 게시판권한 설정폼
	 * @param $tag[id] : 필드아이디
	 * @param $tag[name] : 필드명
	 * @param $tag[selected] : 선택정보
	**/
	protected function authForm($tag, $boardid = null, $type = 'B' ){
		$all = $admin = $member = $write = '';
		$formtype = 'radio';
		$tag['id'] = (isset($tag['id']) ) ? $tag['id']:$tag['name'];
		if( $tag['name'] == 'auth_cmt_recommend'  ) {
			return ' 댓글 작성 권한과 같음(회원전용)';
		}elseif( $tag['name'] == 'auth_recommend'  ) {
			return ' 읽기 권한과 같음(회원전용)';
		}

		$all = ( (isset($tag['selected']) && strstr($tag['selected'],'[all]')) || (empty($tag['selected']) )) ? ' checked="checked" ':' ';
		$allbuyer = (isset($tag['selected']) && strstr($tag['selected'],'[allbuyer]') ) ? ' checked="checked" ':'';
		$onlybuyer = (isset($tag['selected']) && strstr($tag['selected'],'[onlybuyer]') ) ? ' checked="checked" ':'';
		$admin = (isset($tag['selected']) && strstr($tag['selected'],'[admin]') ) ? ' checked="checked" ':'';
		$member = (isset($tag['selected']) && strstr($tag['selected'],'[member]') ) ? ' checked="checked" ':'';
		$memberdisable = (isset($tag['selected']) && strstr($tag['selected'],'[member]') ) ? '':' disabled="disabled" ';


		if( $boardid == 'goods_review' && $tag['name'] == 'auth_write_cmt'  ) {
			$html = ' <input type="radio" name="'.$tag['name'].'[]" value="all" id="'.$tag['id'].'all" '.$all.' class="'.$tag['name'].'" /><label for="'.$tag['id'].'all">전체</label>';
		}else{
			$html = ' <input type="radio" name="'.$tag['name'].'[]" value="all" id="'.$tag['id'].'all" '.$all.' class="'.$tag['name'].'" /><label for="'.$tag['id'].'all">전체</label> <br>';
		}

		if( $boardid == 'goods_review' && $tag['name'] == 'auth_write_cmt'  ) {// || $boardid == 'notice'
			$member = (isset($tag['selected']) && strstr($tag['selected'],'[memberall]') ) ? ' checked="checked" ':'';
			//회원그룹 선택
			$html .= ' <input type="radio" name="'.$tag['name'].'[]" value="memberall" id="'.$tag['id'].'member" class="'.$tag['id'].'"  '.$member.'/><label for="'.$tag['id'].'member">회원</label>';
		}else{
			//회원그룹 선택
			$html .= ' <input type="radio" name="'.$tag['name'].'[]" value="member" id="'.$tag['id'].'member" class="'.$tag['id'].'"  '.$member.'/><label for="'.$tag['id'].'member">회원</label> ( ';
			$query = $this->db->query("select group_seq,group_name from fm_member_group");
			foreach($query->result_array() as $row){
				$group_checked = (!empty($member) && isset($tag['selected']) && strstr($tag['selected'],'[group:'.$row['group_seq'].']') ) ? ' checked="checked" ':'';
				$html .= ' <label><input type="checkbox" name="'.$tag['name'].'_group[]" id="'.$tag['id'].'_group_'.$row['group_seq'].'"  value="'.$row['group_seq'].'" class="line '.$tag['id'].'_group" '.$group_checked.' '.$memberdisable.' /><label for="'.$tag['id'].'_group_'.$row['group_seq'].'" >'.$row['group_name'].'</label>';
				$html .= ',';
			}
			$html = substr($html,0,-1).' )';
		}


		if( $boardid == 'goods_review' && $tag['name'] == 'auth_write'  ) {
			$html .= ' <br><input type="radio" name="'.$tag['name'].'[]" value="onlybuyer" id="'.$tag['id'].'onlybuyer" '.$onlybuyer.' class="'.$tag['name'].'" /><label for="'.$tag['id'].'onlybuyer">구매자(회원/비회원) : \'배송완료\'된 상품을 조회하여 상품후기 작성 </label>';
		}else if( $boardid == 'store_review' && $tag['name'] == 'auth_write'  ) {
			$html .= ' <br><input type="radio" name="'.$tag['name'].'[]" value="onlybuyer" id="'.$tag['id'].'onlybuyer" '.$onlybuyer.' class="'.$tag['name'].'" /><label for="'.$tag['id'].'onlybuyer">구매자(회원) : 쿠폰을 구매하여 사용한 회원</label>';
		}elseif($type == 'A' && $tag['name'] != 'auth_read' ){
			$html .= ' <br>  <input type="radio" name="'.$tag['name'].'[]" value="admin" id="'.$tag['id'].'admin" class="'.$tag['id'].'"  '.$admin.'/><label for="'.$tag['id'].'admin">관리자</label> <br> ';
		}
		return $html;
	}

	/**
	 * 게시판 표시항목
	 * @param $tag[id] : 필드아이디
	 * @param $tag[name] : 필드명
	 * @param $tag[selected] : 선택정보
	**/
	protected function BoardListShowForm($tag) {
		if( $this->manager['recommend_type'] == '3' ) {
			$this->scoretitle = "<span id='scoreid'>5단계평가수</span>";
		}elseif( $this->manager['recommend_type'] == '2' ) {
			$this->scoretitle = "<span id='scoreid'>추천수/비추천수</span>";
		}else{
			$this->scoretitle = "<span id='scoreid'>추천수</span>";
		}
		if( BOARDID == 'goods_qna'  ) {
			$show = array('[num]'=>'번호', '[images]'=>'이미지(상품)', '[subject]'=>'상품명+제목 <input type="text" name="subjectcut" value="'.$tag['subjectcut'].'"  class="line onlynumber" size="2" >자', '[contents]'=>'내용 <input type="text" name="contcut" value="'.$tag['contcut'].'"  class="line onlynumber" size="2" >자','[writer]'=>'작성자', '[date]'=>'등록일', '[hit]'=>'조회수', '[score]'=>$this->scoretitle);
		}elseif( BOARDID == 'goods_review' ){
			$iconaward = ' <img src="'.$this->icon_award_img.'" title="best" align="absmiddle"/> ';
			$show = array('[num]'=>'번호','[reviewinfo]'=>'평가정보', '[images]'=>'이미지(상품)', '[subject]'=>'상품명+베스트 아이콘'.$iconaward.'+제목 <input type="text" name="subjectcut" value="'.$tag['subjectcut'].'"  class="line onlynumber" size="2" >자', '[contents]'=>'내용 <input type="text" name="contcut" value="'.$tag['contcut'].'"  class="line onlynumber" size="2" >자','[writer]'=>'작성자', '[date]'=>'등록일', '[hit]'=>'조회수', '[emoney]'=>'평가점수(평점)', '[order_seq]'=>'구매여부', '[score]'=>$this->scoretitle);
		}else{
			$show = array('[num]'=>'번호', '[subject]'=>'제목', '[subject]'=>'제목  <input type="text" name="subjectcut" value="'.$tag['subjectcut'].'"  class="line onlynumber" size="2" >자','[writer]'=>'작성자', '[date]'=>'등록일', '[hit]'=>'조회수', '[score]'=>$this->scoretitle);
		}
		$tag['id'] = (isset($tag['id']) ) ? $tag['id']:$tag['name'];
		$html = $checked =  '';
		foreach($show as $id => $title) {
				$disabled = ($id == '[subject]' || $id == '[images]' || (BOARDID == 'bulkorder' && $id == '[writer]')) ? ' disabled="disabled" ':'';
				$checked = (isset($tag['selected']) && strstr($tag['selected'],$id) ) ? ' checked="checked" ':'';
				$html .= ' <label for="'.$tag['id'].$id.'"><input type="checkbox" name="'.$tag['name'].'[]" value="'.$id.'" id="'.$tag['id'].$id.'" '.$checked.' '.$disabled.'/>'.$title.'</label>&nbsp;&nbsp; ';
		}
		return $html;
	}

	/* 게시판 추가 신청 */
	public function board_payment(){
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();
		$mall_id	= isset($mall_id) ? $mall_id : $this->config_system['service']['cid'];
		$domain		= isset($this->config_system['subDomain']) ? $this->config_system['subDomain'] : $_SERVER['SERVER_NAME'];
		$arr_domain	= explode(".", $domain);
		if ($arr_domain[0]=="www") {
			$domain = substr($domain, 4);
		}

		$req_type = 'BOARD';
		$param = "req_domain=".$domain."&req_mallid=".$mall_id."&req_type=".$req_type."&req_url=/myshop";
		$param = makeEncriptParam($param);

		$this->template->assign('param',$param);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	//댓글
	public function getCommentlists($parent) {

		$sc['orderby']			= 'seq';
		$sc['sort']					= 'desc';
		$sc['cmtpage']			= (!empty($_GET['cmtpage'])) ?		intval($_GET['cmtpage']):'0';
		$_GET['cmtpage']		= $sc['cmtpage'];//페이징처리를 위해
		$sc['perpage']			= 10;
		$sc['parent']				= (!empty($parent))?$parent:'';

		$cmtdata = $this->Boardcomment->data_list($sc);//댓글목록

		$sc['searchcount']	 = $cmtdata['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $this->Boardcomment->get_data_total_count($sc);
		$idx = 0;
		foreach($cmtdata['result'] as $cmtdatarow){$idx++;
			$this->manager = get_admin_name(array(
				'mtype'=>$cmtdatarow['mtype'],
				'mseq'=>$cmtdatarow['mseq'],
				'manager'=>$this->manager,
				'write_admin_format'=>$this->manager['write_admin_format']
			));

			getminfo($this->manager, $cmtdatarow, $mdata, $boardname);//회원정보
			$cmtdatarow['name'] = $boardname;
			$cmtdatarow['mbname'] = $this->userInfo['user_name'];

			get_auth($this->manager, $cmtdatarow, 'write', $isperm);//접근권한체크
			if( defined('__ADMIN__') === true || defined('__SELLERADMIN__') === true){
				$isperm_write = '';
				$cmtdatarow['isperm_moddel'] = '';
				$cmtdatarow['isperm_hide'] = '';
			}else{
				$isperm_write	= (defined('__ISUSER__') === true)?'':'_no';//답글은 회원전용

				$cmtdatarow['isperm_moddel'] = ( $isperm['isperm_moddel'] === true)?'':'_no';
				$cmtdatarow['isperm_hide'] = $cmtdatarow['isperm_moddel'];

				if( ($cmtdatarow['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) || ( !empty($cmtdatarow['mseq']) && !defined('__ISUSER__') ) ) {
					$cmtdatarow['isperm_moddel'] = '_mbno';//버튼숨김(회원 > 본인만 가능함

					if( ($cmtdatarow['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) || $cmtdatarow['mseq']  < 0  ) {
						$cmtdatarow['isperm_hide'] = 'hide';//버튼숨김(회원 > 본인만 가능함 or 관리자인경우 숨김
					}

				}else{
					// 비번입력후 브라우저를 닫기전까지는 등록/삭제가능함
				$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$cmtdatarow['parent'];
					$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
					if ( strstr($boardpwwritess,'['.$cmtdatarow['seq'].']') && !empty($boardpwwritess)) {
						$cmtdatarow['isperm_moddel'] = '';//비회원 > 접근권한있음
					}
				}

				if(isset($cmtdatarow['hidden']) && $cmtdatarow['hidden'] == 1 ) {//비밀글
					if( $cmtdatarow['isperm_moddel'] == '_mbno' ) {
						$cmtdatarow['isperm_moddel'] = '_hidden_mbno';
						$cmtdatarow['isperm_hidden'] = '_hidden_mbno';
					}elseif( $cmtdatarow['isperm_moddel'] == '_no' ) {
						$cmtdatarow['isperm_moddel'] = '_hidden_no';
						$cmtdatarow['isperm_hidden'] = '_hidden_no';
					}
				}
			}

			//비밀글
			$cmtdatarow['iconhidden'] = ( $cmtdatarow['hidden'] == 1)?' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ':'';

			if( $this->manager['auth_cmt_recommend_use'] == 'Y' ){
				/**
					$scoresql['whereis']	= ' and boardid= "'.BOARDID.'" ';
					$scoresql['whereis']	.= ' and parent= "'.$cmtdatarow['parent'].'" ';//게시글
					$scoresql['whereis']	.= ' and cparent= "'.$cmtdatarow['seq'].'" ';//게시글
					$scoresql['whereis']	.= ' and mseq= "-'.$this->managerInfo['manager_seq'].'" ';
					$getscoredata = $this->Boardscorelog->get_data($scoresql);
					if($getscoredata) $cmtdatarow['is_cmt_recommend'] = '_y';
				**/
				$cmtdatarow['is_cmt_recommend'] = '_y';
			}


			$replytitle = ($isperm_write == '_no') ? '로그인 후 이용해 주세요.':'';
			$replytitlereadonly = ($isperm_write == '_no') ? ' readonly="readonly" ':'';
			$cmtdatarow['isperm_write'] = $isperm_write;
			$cmtdatarow['replytitle'] = $replytitle;
			$cmtdatarow['replytitlereadonly'] = $replytitlereadonly;

			if($cmtdatarow['display'] == 1 ){//삭제시
				$cmtdatarow['iconnew']	= '';
				$cmtdatarow['content']		= ' <span class="hand gray  " >삭제되었습니다 ['.substr($cmtdatarow['r_date'],0,16).']</span>';

				$cmtdatarow['deletebtn'] = '<span class="btn small valign-middle"><input type="button" name="boad_cmt_delete_btn'.$cmtdatarow['isperm_moddel'].'"   board_cmt_seq="'.$cmtdatarow['seq'].'" value="삭제" /></span>';
			}else{
				if( !empty($cmtdatarow['isperm_hidden'])  ){//비밀글
					//$cmtdatarow['content']		= ' <span class="hand gray boad_cmt_content_'.$cmtdatarow['seq'].' " board_cmt_seq="'.$cmtdatarow['seq'].'"><span class="boad_cmt_content'.$cmtdatarow['isperm_hidden'].'" board_cmt_seq="'.$cmtdatarow['seq'].'" >비밀댓글입니다.</span></span>';
				}
				$cmtdatarow['iconnew']	= ( date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$cmtdatarow['r_date']),0,8))) >= date("Ymd") ) ? ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ' :'';

				$cmtdatarow['date']			= substr($cmtdatarow['r_date'],0,16);//등록일
				$cmtdatarow['org_content']	= $cmtdatarow['content']; // 본래 내용 추가 2014-01-14 lwh
				$cmtdatarow['content']		= ' <span  board_seq="'.$cmtdatarow['seq'].'"  board_id="'.$cmtdatarow['boardid'].'"  >'.$cmtdatarow['content'].'</span>';
			}

				$cmtdatarow['idx'] = $idx;

			/**
			댓글의 답글
			**/
			$sc['orderby']			= 'seq';
			$sc['sort']					= 'desc';
			$sc['parent']				= (isset($cmtdatarow['parent']))?$cmtdatarow['parent']:'';
			$sc['cmtparent']		= (isset($cmtdatarow['seq']))?$cmtdatarow['seq']:'';
			$cmtreplyqry = $this->Boardcomment->data_list_reply($sc);//댓글목록
			$cmthtml = '';
			$replyidx = 0;
			unset($cmtreplyloop);
			foreach($cmtreplyqry['result'] as $cmtreply){$replyidx++;
				$this->manager = get_admin_name(array(
					'mtype'=>$cmtreply['mtype'],
					'mseq'=>$cmtreply['mseq'],
					'manager'=>$this->manager,
					'write_admin_format'=>$this->manager['write_admin_format']
				));

				getminfo($this->manager, $cmtreply, $mdata, $boardname);//회원정보
				$cmtreply['name'] = $boardname;

				if( defined('__ADMIN__') === true || defined('__SELLERADMIN__') === true ){
					$cmtreply['isperm_moddel'] = '';
					$cmtreply['isperm_hide'] = '';
				}else{
					$cmtreply['isperm_moddel'] = ( ($cmtreply['mseq'] == $this->userInfo['member_seq'] && defined('__ISUSER__') === true) )?'':'_no';//답글은 회원만
					$cmtreply['isperm_hide'] = $cmtreply['isperm_moddel'];

					if( ($cmtreply['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) || ( !empty($cmtreply['mseq']) && !defined('__ISUSER__') ) ) {
						$cmtreply['isperm_moddel'] = '_mbno';//버튼숨김(회원 > 본인만 가능함

						if( ($cmtreply['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) ||  $cmtreply['mseq']  < 0  ) {
							$cmtreply['isperm_hide'] = 'hide';//버튼숨김(회원 > 본인만 가능함 or 관리자인경우 숨김
						}

					}else{
						// 비번입력후 브라우저를 닫기전까지는 등록/삭제가능함
					$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$cmtdatarow['parent'];
						$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
						if ( strstr($boardpwwritess,'['.$cmtreply['seq'].']') && !empty($boardpwwritess)) {
							$cmtreply['isperm_moddel'] = '';//비회원 > 접근권한있음
						}
					}

					if( isset($cmtreply['hidden']) && $cmtreply['hidden'] == 1 ) {//비밀글
						if ( $cmtdatarow['isperm_moddel'] ) {
							if( $cmtreply['isperm_moddel'] == '_mbno' ) {
								$cmtreply['isperm_moddel'] = '_hidden_mbno';
								$cmtreply['isperm_hidden'] = '_hidden_mbno';
							}elseif( $cmtreply['isperm_moddel'] == '_no' ) {
								$cmtreply['isperm_moddel'] = '_hidden_no';
								$cmtreply['isperm_hidden'] = '_hidden_no';
							}
						}
					}
				}
				$cmtreply['iconhidden'] = ( $cmtreply['hidden'] == 1)?' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ':'';

				if( $this->manager['auth_cmt_recommend_use'] == 'Y' ){
					/**
						$scoresql['whereis']	= ' and boardid= "'.BOARDID.'" ';
						$scoresql['whereis']	.= ' and parent= "'.$cmtreply['parent'].'" ';//게시글
						$scoresql['whereis']	.= ' and cparent= "'.$cmtreply['seq'].'" ';//게시글
						$scoresql['whereis']	.= ' and mseq= "-'.$this->managerInfo['manager_seq'].'" ';
						$getscoredata = $this->Boardscorelog->get_data($scoresql);
						if($getscoredata) $cmtreply['is_cmt_recommend'] = '_y';
					**/
					$cmtreply['is_cmt_recommend'] = '_y';
				}


				$cmtreply['iconnew']	= ( date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$cmtreply['r_date']),0,8))) >= date("Ymd") ) ? ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ' :'';


				if( !empty($cmtreply['isperm_hidden'])  ){//비밀글
					//$cmtreply['content']		= ' <span class="hand gray boad_cmt_reply_content_'.$cmtreply['seq'].'" board_cmt_seq="'.$cmtdatarow['seq'].'" board_cmt_reply_seq="'.$cmtreply['seq'].'" board_cmt_parent_seq="'.$cmtdatarow['seq'].'" ><span class=" boad_cmt_reply_content'.$cmtreply['isperm_hidden'].' " board_cmt_seq="'.$cmtreply['seq'].'"    board_cmt_reply_seq="'.$cmtdatarow['seq'].'" board_cmt_parent_seq="'.$cmtdatarow['seq'].'" >비밀댓글입니다.</span></span>';
				}

				$cmtreply['date']			= substr($cmtreply['r_date'],0,16);//등록일
				$cmtreply['content']		= ' <span  board_seq="'.$cmtreply['seq'].'"  board_id="'.$cmtreply['boardid'].'" > '.($cmtreply['content']).'</span>';

				$cmtreply['deletebtn'] = '<span class="small valign-middle hand"><img src="'.$this->cmt_reply_del_img.'" title="답글삭제" class="boad_cmt_delete_btn'.$cmtreply['isperm_moddel'].'"   board_cmt_seq="'.$cmtreply['seq'].'" ></span>';
				$cmtreplyloop[] = $cmtreply;
			}
			$cmtdatarow['cmtreplyloop'] = $cmtreplyloop;
			$cmtloop[] =$cmtdatarow;
		}

		if(!empty($_GET['cmtpage'])) {
			$returnurl = str_replace("&cmtpage=".$_GET['cmtpage'],"",str_replace("&cmtlist=1","&",$this->boardurl->cmtview)).'&cmtlist=1';
		}else{
			$returnurl = $this->boardurl->cmtview.$_GET['seq'].'&cmtlist=1';//
		}

		$paginlay =  pagingtag($sc['searchcount']	,$sc['perpage'],$returnurl, getLinkFilter('',array_keys($sc)),'cmtpage', array('viewlinkurl','viewlinkurl') );

		if($sc['searchcount'] > 0) {
			$paginlay = (!empty($paginlay)) ? $paginlay:'<p><a class="on red">1</a><p>';
		}

		if(isset($cmtloop)) $this->template->assign('cmtloop',$cmtloop);
		$this->template->assign('cmtpagin',$paginlay);
	}

	//아이디자인 상품후기 게시글선택
	public function review_select(){
		$file_path	= $this->template_path();

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	//아이디자인 상품후기 게시글선택
	public function review_select_list(){
		$this->tempate_modules();
		$file_path	= $this->template_path();

		/**
		 * list setting
		**/
		$sc							= $_GET;
		if ($sc['search_text'])
		{
			$sc['search_text'] = trim($sc['search_text']);
			$sc['search_text']= stripslashes(htmlspecialchars($sc['search_text']));
		}
		$sc['orderby']			= (!empty($_GET['orderby'])) ?	$_GET['orderby'].', m_date asc':'gid asc, m_date asc';
		$sc['sort']					= (!empty($_GET['sort'])) ?			$_GET['sort']:' ';
		$sc['page']				= (!empty($_GET['page'])) ?		intval($_GET['page']):0;
		$sc['perpage']			= (!empty($_GET['perpage'])) ?	intval($_GET['perpage']):10;
		$sc['perpage']			= (get_cookie('itemlist_qty'.BOARDID))? get_cookie('itemlist_qty'.BOARDID):$sc['perpage'];
		if(!empty($_GET['member_seq'])) $sc['member_seq'] = $_GET['member_seq'];
		$data = $this->Boardmodel->data_list($sc);//게시글목록
		/**
		 * count setting
		**/
		$sc['searchcount']	 = $data['count'];
		$sc['total_page']	 = @ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $this->Boardmodel->get_item_total_count($sc);
			/**
		 * icon setting
		**/
		$this->icon_new_img			= ($this->manager['icon_new_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_new_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$this->manager['icon_new_img'].'?'.time():$this->Boardmanager->new_icon_src;//newicon
		$this->icon_hot_img			= ($this->manager['icon_hot_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_hot_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$this->manager['icon_hot_img'].'?'.time():$this->Boardmanager->hot_icon_src;//hoticon

		$this->icon_review_img			= ($this->manager['icon_review_img'] && @is_file($this->Boardmodel->upload_path.$this->manager['icon_review_img']) ) ? $this->Boardmanager->board_data_src.BOARDID.'/'.$this->manager['icon_review_img'].'?'.time():$this->Boardmanager->review_icon_src;//hoticon

		$this->board_lists($ndata, $data, $sc, $noticeloop, $loop);//debug_var($loop);

		/**
		 * pagin setting
		**/
		$paginlay =  pagingtag($sc['searchcount']	,$sc['perpage'],'/selleradmin/board/review_select_list?id='.BOARDID.'&displayId='.$_GET['displayId'], getLinkFilter('',array_keys($sc)) );
		if(isset($loop)) $this->template->assign('loop',$loop);
		if(empty($paginlay))$paginlay = '<p><a class="on red">1</a><p>';
		$this->template->assign('pagin',$paginlay);
		$this->template->assign('sc',$sc);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}


	public function icon(){
		$icon = code_load('goodsReviewIcon');
		foreach($icon as $k){
			$path = $this->Boardmanager->goodsreviewicondir.$k['codecd'];
			if(file_exists($path)) $loop[] = $k;
		}
		if($loop){
			echo json_encode( $loop );
		}else{
			echo "";
		}
	}


	//동영상등록새창
	public function popup_video()
	{
		$this->load->model('videofiles');
		$this->load->helper('readurl');

		if($_POST['file_key_W']) {
			$file_key_w = $_POST['file_key_W'];//웹 인코딩 코드
		}
		if($_POST['file_key_I']) {
			$file_key_i = $_POST['file_key_I'];//스마트폰 인코딩 코드
		}
		$boardid = ($_POST['id'])?$_POST['id']:$_GET['id'];


		/* 파라미터 검증*/
		$seq = ($_POST['seq'])?(int) $_POST['seq']:($_GET['seq']);

		if(!$this->session->userdata('boardvideotmpcode')){
			$boardvideotmpcode = substr(microtime(), 2, 6);
			$this->session->set_userdata('boardvideotmpcode',$boardtmpcode);
		}

		if($file_key_w || $file_key_i) {
			if($seq ){
				$params['file_key_w']	= $file_key_w;//웹 인코딩 코드
				$params['file_key_i']	= $file_key_i;//웹 인코딩 코드
				$params['seq']				= $seq;
				$this->Boardmodel->data_modify($params);
			}

			$videofiles['parentseq']			= ($seq)?$seq:'';
			$videofiles['upkind']					= 'board';
			$videofiles['type']						= $boardid;
			$videofiles['tmpcode']				= $this->session->userdata('boardvideotmpcode');//
			$videofiles['mbseq']					= $this->managerInfo['manager_seq'];//
			$videofiles['r_date']					= date("Y-m-d H:i:s");
			$videofiles['file_key_w']			= $file_key_w;//웹 인코딩 코드
			$videofiles['file_key_i']				= $file_key_i;//웹 인코딩 코드
			$videoinforesult = readurl(uccdomain('fileinfo',$file_key_w));
			if($videoinforesult){
				$videoinfoarr = xml2array($videoinforesult);
				$videofiles['playtime']		 = ($videoinfoarr['class']['playtime'])?$videoinfoarr['class']['playtime']:'';
			}
			$videofiles['memo']				= $_POST['memo'];//
			$videofiles['encoding_speed']	= ($_POST['encoding_speed'])?$_POST['encoding_speed']:400;
			$videofiles['encoding_screen'] = (is_array($_POST['encoding_screen'])) ? @implode("X",($_POST['encoding_screen'])):'400X300';
			$videoseq = $this->videofiles->videofiles_write($videofiles);
		}

		$uccdomainembedsrc = uccdomain('fileswf',$file_key_w);
		$this->template->assign("uccdomainembedsrc",$uccdomainembedsrc);
		$this->template->assign("file_key_w",$file_key_w);
		$this->template->assign("file_key_i",$file_key_i);
		$this->template->assign("videoseq",$videoseq);
		$this->template->assign("seq",$seq);
		$this->template->assign("encoding_screen",$_POST['encoding_screen']);
		$this->template->assign("encoding_speed",$_POST['encoding_speed']);

		$file_path	= $this->template_path();
		//동영상연결(기본 파일찾기)
		$this->template->assign("uccdomain",uccdomain());
		if( $_POST['file_key_W'] || $_POST['file_key_I'] ) {
			$this->template->assign("videook",true);
		}else{
			$this->template->assign("videook",false);
		}

		if( $_POST['error']) {
			$this->template->assign("videoerror",true);
			$this->template->assign("error",$_POST['error']);
		}else{
			$this->template->assign("videoerror",false);
		}
		$sc['whereis']	= ' and id= "'.BOARDID.'" ';
		$sc['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		$this->template->assign('manager',$this->manager);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

		// 게시판 > 게시글 메인화면 추출용
	function getSellerBoardWidgets($bdwidget, & $widgetloop, & $name, & $totalcount)
	{
		unset($this->wigetBoardmodel,$this->widgetboardid);
		$this->widgetboardid = $bdwidget['boardid'];
		//$this->load->helper('text');//strcut
		//$this->load->model('Boardmanager');
		if( $this->widgetboardid == 'goods_qna' ) {
			$this->load->model('Goodsqna','qnaBoardmodel');
			$this->wigetBoardmodel = $this->qnaBoardmodel;
		}elseif( $this->widgetboardid == 'goods_review' ) {
			$this->load->model('Goodsreview','reviewBoardmodel');
			$this->wigetBoardmodel = $this->reviewBoardmodel;
			$qry = "select * from fm_boardform  where boardid='".$this->widgetboardid."'  order by sort_seq";
			$query = $this->db->query($qry);
			$user_arr = $query -> result_array();
			unset($goodsreview_sub);
			foreach ($user_arr as $user){
				$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
				$goodsreview_sub[] = $user;
			}
			$this->template->assign('goodsreview_sub', $goodsreview_sub);
		}elseif( $this->widgetboardid == 'bulkorder' ) {
			$this->load->model('Boardbulkorder','bulkBoardmodel');
			$this->wigetBoardmodel = $this->bulkBoardmodel;
			//대량구매 추가양식 정보
			$qry = "select * from fm_boardform  where boardid='".$this->widgetboardid."'  order by sort_seq";
			$query = $this->db->query($qry);
			$user_arr = $query -> result_array();
			foreach ($user_arr as $user){
				$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
				$bulkorder_sub[] = $user;
			}
			$this->template->assign('bulkorder_sub', $bulkorder_sub);
		}else{
			$this->load->model('Boardmodel');
			$this->wigetBoardmodel = $this->Boardmodel;
		}
		$this->load->model('Boardindex');


		$querystr = '';
		$sc['whereis']	= ' and id= "'.$this->widgetboardid.'" ';
		$sc['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		if(!$this->manager){return;}
		$name = $this->manager['name'];
		$totalcount = $this->manager['totalnum'];


		$this->wigetBoardmodel->upload_path		= $this->Boardmanager->board_data_dir.$this->widgetboardid.'/';
		$this->wigetBoardmodel->upload_src		= $this->Boardmanager->board_data_src.$this->widgetboardid.'/';

		$this->boardurl->lists		= $this->Boardmanager->realboarduserurl.$this->widgetboardid.$querystr;				//게시물관리

		$this->boardurl->write		= $this->Boardmanager->realboardwriteurl.$this->widgetboardid.$querystr;				//게시물등록
		$this->boardurl->modify	= $this->Boardmanager->realboardwriteurl.$this->widgetboardid.$querystr.'&seq=';	//게시물수정
		$this->boardurl->view		= $this->Boardmanager->realboardviewurl.$this->widgetboardid.$querystr.'&seq=';	//게시물보기
		$this->boardurl->reply		= $this->Boardmanager->realboardwriteurl.$this->widgetboardid.$querystr.'&reply=y&seq=';	//게시물답변

		$this->boardurl->perm		= $this->Boardmanager->realboardpermurl.$this->widgetboardid.'&returnurl=';						//접근권한
		$this->boardurl->pw			= $this->Boardmanager->realboardpwurl.$this->widgetboardid.'&returnurl=';						//접근권한

		$this->icon_file_img				= $this->Boardmanager->file_icon_src;//첨부파일icon
		$this->icon_image_img		= $this->Boardmanager->img_icon_src;//이미지icon
		$this->icon_video_img			= $this->Boardmanager->video_icon_src;//동영상icon
		$this->icon_mobile_img		= $this->Boardmanager->mobile_icon_src;//모바일icon

		$this->icon_hidden_img		= $this->Boardmanager->hidden_icon_src;//비밀글icon
		$this->notice_img				= $this->Boardmanager->notice_icon_src;//공지글icon
		$this->re_img						= $this->Boardmanager->re_icon_src;//답변글icon
		$this->blank_img					= $this->Boardmanager->blank_icon_src;//blank
		$this->print_img					= $this->Boardmanager->print_icon_src;//print

		$boardurl = $this->boardurl;

		/**
		 * icon setting
		**/
		$this->icon_new_img			= ($this->manager['icon_new_img'] && @is_file($this->wigetBoardmodel->upload_path.$this->manager['icon_new_img']) ) ? $this->Boardmanager->board_data_src.$this->widgetboardid.'/'.$this->manager['icon_new_img'].'?'.time():$this->Boardmanager->new_icon_src;//newicon
		$this->icon_hot_img			= ($this->manager['icon_hot_img'] && @is_file($this->wigetBoardmodel->upload_path.$this->manager['icon_hot_img']) ) ? $this->Boardmanager->board_data_src.$this->widgetboardid.'/'.$this->manager['icon_hot_img'].'?'.time():$this->Boardmanager->hot_icon_src;//hoticon

		$this->icon_review_img			= ($this->manager['icon_review_img'] && @is_file($this->wigetBoardmodel->upload_path.$this->manager['icon_review_img']) ) ? $this->Boardmanager->board_data_src.$this->widgetboardid.'/'.$this->manager['icon_review_img'].'?'.time():$this->Boardmanager->review_icon_src;//hoticon

		get_auth($this->manager, '', 'read', $isperm);//접근권한체크
		$this->manager['isperm_read'] = ($isperm['isperm_read'] === true)?'':'_no';
		$this->manager['fileperm_read']= (isset($isperm['fileperm_read']))?$isperm['fileperm_read']:'';

		get_auth($this->manager, '', 'write', $isperm);//접근권한체크
		$this->manager['isperm_write'] = ($isperm['isperm_write'] === true)?'':'_no';

		if( $this->widgetboardid == 'goods_qna') {
			$widgetsql['orderby']			= 'gid asc, m_date asc';
			$widgetsql['sort']					= ' ';
			$widgetsql['page']				= '0';
			$widgetsql['perpage']			= $bdwidget['limit'];
			//$widgetsql['provider_seq']	= $this->providerInfo['provider_seq'];//해당입점상품문의인경우
			$wdata = $this->wigetBoardmodel->data_list($widgetsql);//게시판목록
		}elseif( $this->widgetboardid == 'goods_review') {
			$widgetsql['orderby']			= 'gid asc, m_date asc';
			$widgetsql['sort']					= ' ';
			$widgetsql['page']				= '0';
			$widgetsql['perpage']			= $bdwidget['limit'];
			//$widgetsql['provider_seq']	= $this->providerInfo['provider_seq'];//해당입점상품문의인경우
			$wdata = $this->wigetBoardmodel->data_list($widgetsql);//게시판목록
		}elseif( $this->widgetboardid == 'gs_seller_qna') {
			//입점사문의글인경우
			$widgetsql['orderby']			= 'gid asc, m_date asc';
			$widgetsql['sort']					= ' ';
			$widgetsql['boardid']			= $this->widgetboardid;
			$widgetsql['page']				= '0';
			$widgetsql['perpage']			= $bdwidget['limit'];
			$widgetsql['mseq']				= '-'.$this->providerInfo['provider_seq'];
			$wdata = $this->wigetBoardmodel->data_list($widgetsql);//게시판목록
		}else{
			$widgetsql['orderby']			= 'gid asc, m_date asc';
			$widgetsql['sort']					= ' ';
			$widgetsql['boardid']			= $this->widgetboardid;
			$widgetsql['page']				= '0';
			$widgetsql['perpage']			= $bdwidget['limit'];

			$wdata = $this->wigetBoardmodel->data_list($widgetsql);//게시판목록
		}

		$totalcount = $wdata['count'];
		$idx = ($bdwidget['limit']<$totalcount) ? $bdwidget['limit']:$totalcount;

		foreach($wdata['result'] as $widget){

			if(isset($widget['seq'])) {

				$widget['number'] = $idx;//번호
				$widget['category'] = (!empty($widget['category']) )? ' <span class="cat">['.$widget['category'].']</span>':'';

				$this->manager = get_admin_name(array(
					'mtype'=>$widget['mtype'],
					'mseq'=>$widget['mseq'],
					'manager'=>$this->manager,
					'write_admin_format'=>$this->manager['write_admin_format']
				));

				getminfo($this->manager, $widget, $mdata, $boardname);//회원정보
				$widget['name'] = $boardname;
				$widget['reply_title']		= ($widget['re_contents'])?'<span class="blue" >'.getAlert("sy062").'</span>':'<span class="gray" >'.getAlert("sy063").'</span>';//상태 답변완료 답변대기

				if($widget['display'] == 1 ){//삭제시
					$widget['iconnew']	= '';
					$widget['iconhot']	= '';
					$widget['iconfile']		= '';
					$widget['iconimage']		= '';
					$widget['iconmobile']		= '';
					$widget['iconhidden'] = '';
					$widget['blank']			= ($widget['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($widget['depth']-1)*13).'" ><img src="'.$this->re_img.'" title="답변" >':'';//답변
					$commentcnt = ($widget['comment']>0) ? ' <span class="comment">('.number_format($widget['comment']).')</span>':'';
					$widget['subject']		= $widget['blank'].' <span class="hand gray boad_view_btn'.$this->manager['isperm_read'].'" viewlink="'.$this->boardurl->view.$widget['seq'].'"  fileperm_read="'.$this->manager['fileperm_read'].'"  board_seq="'.$widget['seq'].'"  board_id="'.$this->widgetboardid.'" ><a>삭제되었습니다 ['.substr($widget['r_date'],0,16).']</a></span>'.$commentcnt;
					$widget['date']			= substr($widget['r_date'],0,16);

					if($widget['replyor'] == 0 && $widget['comment'] == 0) {//삭제후 답변이나  댓글이 없는 경우 삭제가능
						$widget['deletebtn'] = '<span class="btn small  valign-middle"><input type="button" name="boad_delete_btn" board_seq="'.$widget['seq'].'"  board_id="'.$this->widgetboardid.'" value="삭제" /></span>';
					}
				}else{

					if( $this->widgetboardid == 'goods_qna' || $this->widgetboardid == 'mbqan' ) {
						$widget['subject']		= getstrcut(strip_tags($widget['subject']), 36);
					}else{
						$widget['subject']		= getstrcut(strip_tags($widget['subject']), 36);
					}
					if( $this->manager['icon_new_day'] > 0 && date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$widget['r_date']),0,8))) >= date("Ymd") ) {//new
						$widget['iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ';
					}else{
						$widget['iconnew'] ='';
					}
					$widget['iconhot']		= ($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $widget['hit']) ? ' <img src="'.$this->icon_hot_img.'" title="hot" align="absmiddle"> ':'';//조회수
					$widget['iconfile']		= ($widget['upload']  && getBoardFileck($widget['upload'], $widget['contents']) ) ?' <img src="'.$this->icon_file_img.'" title="첨부파일" align="absmiddle" > ':'';//첨부파일

					if(boardisimage($widget['upload'], $widget['contents']) ) {//첨부파일 > image $widget['upload']  &&
						$widget['iconimage']		= ' <img src="'.$this->icon_image_img.'" title="첨부파일" align="absmiddle" > ';
					}
					if(isMobilecheck($widget['agent'])) {//agent > mobile ckeck
						$widget['iconmobile']		= ' <img src="'.$this->icon_mobile_img.'" title="모바일" align="absmiddle" > ';
					}
					$widget['iconhidden'] = ($widget['hidden'] == 1 ) ? ' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ':'';

					$widget['date']			= substr($widget['r_date'],0,16);//등록일
					$widget['blank']			= ($widget['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($widget['depth']-1)*53).'" ><img src="'.$this->re_img.'" title="답변" >':'';//답변
					$commentcnt = ($widget['comment']>0) ? ' <span class="comment">('.number_format($widget['comment']).')</span>':'';
					$widget['subject']		= $widget['blank'].$widget['category'].' '.$widget['subject'].''.$commentcnt;
				}

				$widget['subject']		= ' <span class="hand underline '.$this->widgetboardid.'_boad_view_btn" viewlink="'.$this->boardurl->view.$widget['seq'].'" board_seq="'.$widget['seq'].'"  board_id="'.$this->widgetboardid.'" >'.$widget['subject'].'</span>';

				if(  $this->widgetboardid == 'goods_review' ) {
					if($this->manager['goods_review_type'] == 'INT' && $widget['reviewcategory']){
						$widget['scorelay'] = getGoodsScore($widget['score_avg'], $this->manager);
					if(sizeof(explode(",",$widget['reviewcategory']))>1) $widget['score_avg_lay'] = 'score_avg';
					}else{
					$widget['scorelay'] = getGoodsScore($widget['score'], $this->manager);
					}
					$widget['emoneylay']	=  getBoardEmoneybtn($widget, $this->manager,'view');
				}

				if(!empty($widget['goods_seq']) && $widget['depth'] == 0 ){
					if( BOARDID == 'bulkorder' ) {
						$widget['goodsInfo']		= getBulkorderGoodsinfo($widget, $widget['goods_seq'], 'write');
						if($widget['goodsInfo'][0]) $widget['goodsInfo'] = $widget['goodsInfo'][0];
						$widget['goodsview']	= getBulkorderGoodsinfo($widget, $widget['goods_seq'], 'view');
					}else{
						$widget['goodsInfo']		= getGoodsinfo($widget, $widget['goods_seq'], 'write');
						if($widget['goodsInfo'][0]) $widget['goodsInfo'] = $widget['goodsInfo'][0];
					$widget['goodsview']	= getGoodsinfo($widget, $widget['goods_seq'], 'view');
					}
				}else{
					$widget['goodsview']	= '';
				}

				$widgetloop[] = $widget;
			}
			$idx--;
			unset($widget);
		}
	}

}
/* End of file board.php */
/* Location: ./app/controllers/selleradmin/board.php */

