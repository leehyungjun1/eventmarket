<?php
/**
 * 게시판/게시물 관련 관리자
 * @author gabia
 * @since version 2.0 - 2012.06.29
 */
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/admin_base".EXT);

class Board extends admin_base {

	public function __construct() {
		parent::__construct();
		$this->load->library('validation');
		$this->load->model('Boardmanager');
		$this->load->model('providermodel');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		$this->load->model('Boardscorelog');
		$this->load->model('Boardindex');//공지용
		$this->load->helper(array('text','board','file','download','cookie'));

		$aGetParams = $this->input->get();

		// validation
		if ($aGetParams) {
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('id', '게시판ID', 'trim|string|xss_clean');
			$this->validation->set_rules('seq', '일련번호', 'trim|numeric|xss_clean');
			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

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
				$this->Boardmanager->realboardwriteurl		= '/admin/board/'.BOARDID.'_write?id=';							//게시물등록
				$this->Boardmanager->realboardviewurl		= '/admin/board/'.BOARDID.'_view?id=';							//게시물보기
			}elseif( BOARDID == 'onlinepromotion' || BOARDID == 'offlinepromotion' ){
				if($thisfile == 'board' ){
					$file_path = $this->skin.'/board/promotion.html';
				}
				$this->Boardmanager->realboardwriteurl		= '/admin/board/promotion_write?id=';							//게시물등록
				$this->Boardmanager->realboardviewurl		= '/admin/board/promotion_view?id=';							//게시물보기
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

		if($this->input->get("id") == "naverpay_qna"){
			$file_path = str_replace("board.html","npayqna.html",$file_path);
		} else if($this->input->get("id") == "talkbuy_qna"){
			$file_path = str_replace("board.html","talkbuyqna.html",$file_path);
		}
		define('FILE_PATH', $file_path);
		$this->template->assign('ismobile',$this->_is_mobile_agent);//ismobile

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

	}

	public function index()
	{
		return $this->board();
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
		getmanagerauth('board_list');//* 관리자 권한 체크

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
			$this->template->assign('categorylist',array_map('htmlspecialchars_decode', $categorylist));
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
		$sc['search_type']			= $_GET['search_type']??null;

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

		// 셀렉트박스 10~200 개씩 보기 클릭시 limit 시작 숫자 계산 오류로 추가 2015-04-11
		if ($sc['page'] > 0) {
			$sc['page'] = ((floor($sc['page']/$sc['perpage'])+1)-1)*$sc['perpage'];
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
		$this->template->assign([
			'sc'=>$sc,
			'scObj' => json_encode($sc),
		]);
	}

	/**
	 * 관리자 > 게시물 전체
	 * @param id : 게시판아이디
	**/
	public function all_data_list()
	{
		//getmanagerauth('board_view');//* 관리자 권한 체크
		$this->template->assign('realboardurl',$this->Boardmanager->realboardurl);
		$this->template->assign('realboardwriteurl',$this->Boardmanager->realboardwriteurl);
		$this->template->assign('realboardviewurl',$this->Boardmanager->realboardviewurl);

		$this->manager['name'] = '전체';
		$this->template->assign('manager',$this->manager);

		$limit = (isset($_GET['perpage']))?($_GET['perpage']):5;

		//boardalllist();//게시판전체리스트

		if( defined('__SELLERADMIN__') === true ) {//입점사고정
			$boardmainlistar = array("goods_qna"=>1,"gs_seller_qna"=>2,"goods_review"=>3,"gs_seller_notice"=>4);
		}else{
			$boardmainlistar = config_load('board_main');
			if(!$boardmainlistar) {
				$boardmainlistar = array("goods_qna"=>1,"mbqna"=>2,"goods_review"=>3,"notice"=>4);
		    }
		}
		$boardmainlistar = array_flip($boardmainlistar);
		ksort($boardmainlistar);//배열정렬

		if($this->managerInfo['manager_yn'] !== 'Y') {
		    $allowBoards = array();
		    // 권한 체크 :: rsh 2019-03-11
		    if(count($boardmainlistar) > 0 && !empty($this->managerInfo['manager_seq'])) {
		        $allowBoards = getAllowBoards($this->managerInfo['manager_seq']);
		        $allowBoards = array_intersect($boardmainlistar, $allowBoards);
		    }
		    if(count($allowBoards)<1 && count($boardmainlistar)>0) {
				// 게시판 권한 체크 프로세스가 의미가 없어 보여 제거 by lgs
		        // pageBack("권한이 없습니다.");
		    } else {
		        $boardmainlistar = $allowBoards;
		    }
		}

		$idx = $sidx = $nidx = 0;
		foreach($this->boardmanagerlist as $boardar){$idx++;
			$boardid = $boardar['id'];
			unset($bdwidget, $widgetloop,$boardurl);

			if($boardid == 'faq')	continue;

			if( defined('__SELLERADMIN__') === true && solutionServiceCheck(4, NULL, SERVICE_CODE) ) {
				if( $boardid == 'goods_qna' ) $boardid = 'store_reservation';
				elseif( $boardid == 'goods_review' ) $boardid = 'store_review';
				elseif( $boardid == 'mbqna' ) $boardid = 'gs_seller_qna';
				elseif( $boardid == 'notice' ) $boardid = 'gs_seller_notice';

			}elseif ( solutionServiceCheck(4, NULL, SERVICE_CODE) ) {
				if( $boardid == 'goods_qna' ) $boardid = 'store_reservation';
				elseif( $boardid == 'goods_review' ) $boardid = 'store_review';
			}else{
				//오프라인매장 전용게시판 제외
				if ( $boardid == 'store_reservation' || $boardid == 'store_gallery' ) continue;
			}
			if( in_array($boardid, $boardmainlistar) ) {//$sidx++;
				$sidx = array_keys ($boardmainlistar, $boardid);
				$bdwidget['boardid']	= $boardid;
				$bdwidget['limit']			= $limit;
				getAdminBoardWidgets($bdwidget, $widgetloop, $name, $totalnum);
				$boardmainlist[$sidx[0]]['id']					= $boardid;
				$boardmainlist[$sidx[0]]['boardname']	= $name;
				$boardmainlist[$sidx[0]]['totalnum']		= $totalnum;
				$boardmainlist[$sidx[0]]['widgetloop']		= $widgetloop;
			}else{$nidx++;
				$boardmainnosel[$nidx]['boardname']= $boardar['name'];
				$boardmainnosel[$nidx]['id']				= $boardid;
				$boardmainnosel[$nidx]['totalnum']	= $boardar['totalnum'];
			}
		}
		ksort($boardmainlist);//배열정렬
		$this->template->assign('boardmainlist',$boardmainlist);
		$this->template->assign('boardmainnosel',$boardmainnosel);
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
				$noticesql['select']		= ' * ';
				$notice = $this->Boardmodel->get_data($noticesql);//게시판목록

				if( $ndatarow['onlynotice'] == '1' && ($ndatarow['onlynotice_sdate'] && $ndatarow['onlynotice_edate']) && !( date('Y-m-d') >=  $ndatarow['onlynotice_sdate'] && date('Y-m-d')  <=  $ndatarow['onlynotice_edate']) ) {
					$notice['onlynoticeclass']= " onlynoticetr ";//continue;//관리자는 노출
					$notice['subject'] = "<span class='gray'>[공지종료]</span> ".$notice['subject'];
				}
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
						$notice['iconhot']		= ' <img src="'.$this->icon_hot_img.'" title="hot" > ';
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

					$notice['modifybtn'] = '<input type="button" class="resp_btn v2" name="boad_modify_btn" board_seq="'.$notice['seq'].'"  board_id="'.BOARDID.'" value="수정">';

						if( BOARDID == 'goods_review' ) {//상품후기 평가노출

						if($this->manager['goods_review_type'] == 'INT' && $notice['reviewcategory']){
							$notice['scorelay'] = getGoodsScore($notice['score_avg'], $this->manager);
							if(sizeof(explode(",",$notice['reviewcategory']))>1) $notice['score_avg_lay'] = 'score_avg';
						}else{
							$notice['scorelay'] = getGoodsScore($notice['score'], $this->manager);
						}

							//평가정보노출
							if( $notice['adddata'] ){
							unset($adddata);
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

					# npay 문의글일때 수정/삭제 불가
					if( in_array(BOARDID, array('naverpay_qna','talkbuy_qna')) ) {
						$notice['modifybtn'] = '';
						$notice['deletebtn'] = '';
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
			$datarow['subject_real'] 	= $datarow['subject'];

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

				$datarow['hiddenbtn'] = '<label class="resp_checkbox"><input type="checkbox" name="hidden" id="listhidden'.$datarow['seq'].'" class="listhidden" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'"  value="1" '.$hiddenckeck.'></label>';//노출여부
			}else{
				$datarow['tdclass']	= ($datarow['re_contents']) ? ' checked-tr-background2  ':'';
			}

			/**$replysc['whereis']	= ' and gid > '.$datarow['gid'].' and gid < '.(intval($datarow['gid'])+1) . ' ';//답변여부
			//$replysc['select']		= " gid ";
			$datarow['replyor']	= $this->Boardindex->get_data_numrow($replysc);**/
			$replysc['whereis'] = ' and gid > '.$datarow['gid'].' and gid < '.(intval($datarow['gid'])+1).' and parent = '.($datarow['seq']).' ';//답변여부
			$datarow['replyor'] = $this->Boardmodel->get_data_numrow($replysc);

			if( BOARDID == 'goods_review' ){
				if($this->manager['goods_review_type'] == 'INT' && $datarow['reviewcategory']){
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

				if($datarow['replyor'] == 0 && $datarow['comment'] == 0) {//삭제후 답변이나  댓글이 없는 경우 삭제가능
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

				$datarow['iconhot']		= ($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $datarow['hit']) ? ' <img src="'.$this->icon_hot_img.'" title="hot" > ':'';//조회수
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
					$datarow['subject']		= $datarow['blank'].' <span class="hand underline boad_faqview_btn" viewlink="'.$this->boardurl->view.$datarow['seq'].'" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" >'.getstrcut($datarow['subject'], 36).'</span>'.$commentcnt;
				}else{
					$datarow['subject']		= $datarow['blank'].' <span class="hand underline boad_view_btn" viewlink="'.$this->boardurl->view.$datarow['seq'].'" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" >'.getstrcut($datarow['subject'], 36).'</span>'.$commentcnt;
				}

				$datarow['modifybtn'] = '<input type="button" class="resp_btn v2"  name="boad_modify_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="수정">';
				if( BOARDID == 'bulkorder' || BOARDID == 'mbqna' || BOARDID == 'goods_qna'  || BOARDID == 'gs_seller_qna' ) {
					$datarow['replaybtn'] = ($datarow['re_contents'])?'<input type="button" class="resp_btn active" name="boad_reply_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="수정">':'<input type="button" class="resp_btn active" name="boad_reply_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="등록">';//관리자만가능
				}elseif($this->manager['auth_reply_use'] == 'Y') {
					$datarow['replaybtn'] = ($datarow['re_contents'])?'<input type="button" class="resp_btn active" name="boad_reply_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="수정">':'<input type="button" class="resp_btn active" name="boad_reply_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="등록">';//관리자만가능
				}
				$datarow['deletebtn'] = '<input type="button" class="resp_btn v3" name="boad_delete_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="삭제">';

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
						$datarow['goodsInfo'] = getGoodsinfo($datarow, $datarow['goods_seq'], 'write');
						if($datarow['goodsInfo'][0]) $datarow['goodsInfo'] = $datarow['goodsInfo'][0];
						$datarow['goodsview'] = getGoodsinfo($datarow, $datarow['goods_seq'], 'list');
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

		if (!isset($_GET['id'])) pageBack('존재하지 않는 게시판입니다.');
		if (!isset($_GET['seq'])) pageBack('잘못된 접근입니다.');
		$sc['whereis']	= ' and id= "'.BOARDID.'" ';
		$sc['select']		= ' * ';
		$this->manager = $this->Boardmanager->managerdataidck($sc);//게시판정보
		$this->manager['write_admin_format'] = $this->manager['write_admin'];
		if (!isset($this->manager['id'])) pageBack('존재하지 않는 게시판입니다.');

		if( defined('__SELLERADMIN__') === true ) {
			$this->manager['writetitle'] = $this->providerInfo['provider_name'];
		}else{
			if($this->manager['write_admin_type'] == 'IMG' ) {
				$this->manager['icon_admin_img_src']		= ($this->manager['write_admin_type'] == 'IMG' && $this->manager['icon_admin_img'] && is_file($this->Boardmanager->board_data_dir.$this->manager['id'].'/'.$this->manager['icon_admin_img']) ) ? $this->Boardmanager->board_data_src.$this->manager['id'].'/'.$this->manager['icon_admin_img'].'?'.time():$this->Boardmanager->board_icon_src.'icon_admin.gif';
				$this->manager['writetitle'] = '<img src="'.$this->manager['icon_admin_img_src'].'" id="icon_admin_img"  align="absmiddle" style="vertical-align:middle;"/>';
			}else{
				$this->manager['writetitle'] = $this->manager['write_admin'];
			}
		}

		$video_size = explode("X" , $this->manager['video_size']);
		$this->manager['video_size0'] = $video_size[0];
		$this->manager['video_size1'] = $video_size[1];

		$video_size_mobile = explode("X" , $this->manager['video_size_mobile']);
		$this->manager['video_size_mobile0'] = $video_size_mobile[0];
		$this->manager['video_size_mobile1'] = $video_size_mobile[1];
		
		// where bind 재할당
		$bindData = [];
		$sc['whereis']	= ' and seq = ? ';
		$bindData[] = $this->input->get('seq');
		//본래게시글 추출@2017-05-12
		if( !(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder') ) {
			$sc['whereis']	.= ' and boardid= ? ';
			$bindData[] = BOARDID;
		}
		$sc['select']		= ' * ';

		$data = $this->Boardmodel->get_data($sc, $bindData);//게시글
		getmanagerauth('board_view',$data);//* 관리자 권한 체크

		if($data['mid'] && $data['mseq'] < 0 && (defined('__ADMIN__') || defined('__SELLERADMIN__'))){
			if( $data['boardid'] != 'gs_seller_qna' ) {//입점사문의게시판
				$mid_view = ' (' . getstrcut($data['mid'],3,'***') . ')';
			}
			$this->manager['writetitle'] .= $mid_view;
		}

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
			$data['iconhot']		= ' <img src="'.$this->icon_hot_img.'" title="hot" > ';
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


		$data['buyertitle'] = ($data['order_seq'] || $data['npay_product_order_id'] || $datarow['talkbuy_product_order_id'])?'구매':'미구매';

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
			$boardhitss = $_COOKIE[$ss_hit_name];
			if ( ( !strstr($boardhitss,'['.$_GET['seq'].']') && isset($boardhitss)) || empty($boardhitss)) {
				$boardhitssadd = (isset($boardhitss)) ? $boardhitss.'['.$_GET['seq'].']':'['.$_GET['seq'].']';
				$this->Boardmodel->hit_update($_GET['seq']);
				setcookie($ss_hit_name, $boardhitssadd, 0, '/');
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
			// if( BOARDID == 'bulkorder') {
			// 	if(strstr($this->manager['bulk_show'],'[goods]')){//상품사용시
			// 		$goodsview	= '상품정보가 없습니다.';
			// 	}
			// }else{
			// 	$goodsview	= '상품정보가 없습니다.';
			// }
			$goodsview = '';
		}
		$this->template->assign('goodsview',$goodsview);//상품정보

		$this->boardurl->userviewurl = get_connet_protocol().$_SERVER['HTTP_HOST'].'/board/view?id='.BOARDID.'&seq='.$_GET['seq'];//사용자보기(sns)

		if(isset($_GET['cmtpage'])) {
			$this->boardurl->cmtview = str_replace("&cmtpage=".$_GET['cmtpage'],"",str_replace('&cmtlist=1','',str_replace("cmtlist=1".$_GET['seq']."&","",$this->boardurl->view.$_GET['seq']))).'&cmtlist=1';
		}else{
			$this->boardurl->cmtview = str_replace("&cmtlist=1".$_GET['seq']."&","",$this->boardurl->view).$_GET['seq'].'&cmtlist=1';//
		}

		if(BOARDID == 'goods_review' ) {
			//sms 신청여부 및 신청건수
			require_once $_SERVER['DOCUMENT_ROOT']."/app/libraries/SMS_send.class.php";
			$sms	= new SMS_SEND();
			$params	= "sms_id=" . $sms->sms_account . '&sms_pw=' . $sms->sms_password;
			$params = makeEncriptParam($params);
			$limit	= ($sms->limit != -1) ? number_format($sms->limit) : 0;
			$sms_chk = $sms->sms_account;
			$smslnk = ' onclick="window.open(\'../member/sms_charge\')" ';
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
		$this->template->assign('param', $this->input->get());
		$this->getCommentlists($_GET['seq']);//댓글출력
		# npay 문의 스킨 변경
		if(BOARDID == "naverpay_qna"){
			$file_path = str_replace("view.html","npayqna_view.html",$this->template_path());
			$this->template->define(array('tpl'=>$file_path));
		} else if(BOARDID == "talkbuy_qna") {
			$file_path = str_replace("view.html","talkbuyqna_view.html",$this->template_path());
			$this->template->define(array('tpl'=>$file_path));
		}
		$this->template->print_("tpl");
	}

	/**
	 * 관리자 > 상품후기 등록/수정
	 * @param id : 게시판아이디
	**/
	public function goods_review_view()
	{
		// Relected XSS 검증
		xss_clean_filter();
		
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
		getmanagerauth('board_act');//* 관리자 권한 체크
		if (!isset($this->manager['id'])) pageBack('존재하지 않는 게시판입니다.');

		$this->manager = get_admin_name(array(
			'mtype'=>'',
			'mseq'=>'',
			'manager'=>$this->manager,
			'write_admin_format'=>$this->manager['write_admin_format']
		));

		$this->manager['file_use'] = ($this->manager['file_use'] == 'Y' && $this->manager['onlyimage_use'] == 'Y' )?'img':$this->manager['file_use'];

		if($this->manager['write_admin_type'] == 'IMG' ) {
			$this->manager['icon_admin_img_src']		= ($this->manager['write_admin_type'] == 'IMG' && $this->manager['icon_admin_img'] && is_file($this->Boardmanager->board_data_dir.$this->manager['id'].'/'.$this->manager['icon_admin_img']) ) ? $this->Boardmanager->board_data_src.$this->manager['id'].'/'.$this->manager['icon_admin_img'].'?'.time():$this->Boardmanager->board_icon_src.'icon_admin.gif';
			$this->manager['writetitle'] = '<img src="'.$this->manager['icon_admin_img_src'].'" id="icon_admin_img"  align="absmiddle" style="vertical-align:middle;"/>';
		}else{
			$this->manager['writetitle'] = $this->manager['write_admin'];
		}

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
				// $goodsview	= '상품정보가 없습니다.';
				$goodsview = '';
			}
			$this->template->assign('goodsview',$goodsview);//상품정보

			if(BOARDID == 'goods_qna'){
				$goods_info		= getGoodsinfo($data, $data['goods_seq'], 'goods_array');
				$provider_seq	= $goods_info['provider_seq'];
				$goods_name		= $goods_info['goods_name'];
				if($provider_seq > 1){
					$this->load->model('providermodel');
					$provider_info	= $this->providermodel->get_person($provider_seq);
					foreach((array)$provider_info as $row){
						if($row['gb'] == 'cs' && $row['email']){
							$cs_provider_email	= $row['email'];
							break;
						}
					}
				}

				$this->template->assign('goods_name',$goods_name);//상품명
				$this->template->assign('cs_provider_email',$cs_provider_email);//공급사 CS email
			}

			getminfo($this->manager, $data, $mdata, $mbname);//회원정보
			$data['real_name'] = ($data['name'])?$data['name']:$mdata['user_name'];
			$data['mbname'] = $mbname;
			$data['name'] = $mbname;

			if (($_GET['reply'] == 'y') && !(BOARDID == 'store_reservation' || BOARDID == 'mbqna' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder' || BOARDID == 'gs_seller_qna' || BOARDID == 'naverpay_qna' || BOARDID == 'talkbuy_qna') ) {

				$data['subject']		= $this->Boardmanager->board_restr.$data['subject'] .$iconhidden;//답변
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

				$data['subject'] .= ($data['hidden'] == 1 ) ?' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ':'';


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

		$this->template->assign('boardurl',$this->boardurl);

		# npay 문의 스킨 변경
		if(BOARDID == "naverpay_qna"){
			$file_path = str_replace("write.html","npayqna_write.html",$this->template_path());
			$this->template->define(array('tpl'=>$file_path));
		} else if(BOARDID == "talkbuy_qna") {
			$file_path = str_replace("write.html","talkbuyqna_write.html",$this->template_path());
			$this->template->define(array('tpl'=>$file_path));
		}
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
		getmanagerauth('board_view');//* 관리자 권한 체크

		$skinlist = BoardManagerskinlist('');//스킨폴더정보
		$this->template->assign('managerurl',$this->Boardmanager->managerurl);

		$sc['orderby']			= (isset($_GET['orderby'])) ?	$_GET['orderby']:'type desc, seq';
		$sc['sort']					= (isset($_GET['sort'])) ?			$_GET['sort']:'desc';
		$sc['page']				= (isset($_GET['page'])) ?		intval($_GET['page']):'0';
		$sc['perpage']			= (isset($_GET['perpage'])) ?	intval($_GET['perpage']):'10';
		$sc['perpage']			= (get_cookie('itemlist_qty_manager'))? get_cookie('itemlist_qty_manager'):$sc['perpage'];

		$sc['search_type'] = $_GET['search_type'];
		$sc['type'] = $_GET['type'];

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
				$datarow['managermodifybtn']	= '<input type="button" class="resp_btn v2" name="bulkorder_free_btn" value="수정" />';//수정
			}else{
				$datarow['managermodifybtn']	= '<input type="button" class="resp_btn v2" name="manager_modify_btn" value="수정" board_seq="'.$datarow['seq'].'"  board_id="'.$datarow['id'].'" board_name="'.$datarow['name'].'"  />';//수정
			}

			// npay 문의게시판일경우 수정 불가
			if(in_array($datarow['id'], array('naverpay_qna','talkbuy_qna'))) $datarow['managermodifybtn'] = '';

			$auth = $this->authmodel->manager_limit_act('board_manger'); //관리자 게시물관리 권한 체크

			$datarow['managercopybtn'] = ($datarow['skin_type'] != 'goods' && !in_array($datarow['id'],array('mbqna','bulkorder','faq','gs_seller_qna','gs_seller_notice','store_review','store_reservation','naverpay_qna','talkbuy_qna')) && $auth) ? '<input type="button" class="resp_btn v2" name="boardmanagercopybtn"  board_seq="'.$datarow['seq'].'"  board_id="'.$datarow['id'].'" board_name="'.$datarow['name'].'"  value="복사" />':'';//상품일반 그외의경우 복사가능
			$datarow['managerdeletebtn'] = ($datarow['type'] == 'A' && $auth) ? '<input type="button" class="resp_btn v3" name="boarddelete" board_seq="'.$datarow['seq'].'"  board_id="'.$datarow['id'].'" board_name="'.$datarow['name'].'" value="삭제" />':'';//추가인 경우 삭제가능

			$dataloop[] = $datarow;
			boarduploaddir($datarow);//폴더생성 및 스킨 복사
		}

		//boardalllist();//게시판전체리스트

		## SMS발송시간 제한 사용여부
		$sms_rest = config_load('sms_restriction');
		if($sms_rest['board_touser'] == "checked" || $sms_rest['board_toadmin'] == "checked"){
			$sms_rest_board = "y";
			$this->template->assign('sms_rest_board',$sms_rest_board);//스킨폴더정보
		}

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
		getmanagerauth('board_manger');//* 관리자 권한 체크

		// 네이버페이 카카오페이 구매 게시판 수정불가 @2016-07-26 ysm
		if(in_array($this->input->get('id'),array('naverpay_qna','talkbuy_qna'))){
			pageBack('수정할수 없는 게시판입니다.');exit;
		}
		$this->template->assign('writeditorjs',true);//에딧터

		### SERVICE CHECK
		$this->load->model('usedmodel');
		$result = $this->usedmodel->used_service_check('board');
		if(!$result['type']){
			$this->template->assign('service_limit','Y');
		}

		if(isset($_GET['id'])) {//수정시
			$sc['whereis']	= ' and id= "'.BOARDID.'" ';
			$sc['select']		= ' * ';
			$this->manager = $this->Boardmanager->get_managerdata($sc);//게시판정보
			if (!isset($this->manager['id'])) pageBack('존재하지 않는 게시판입니다.');

			$skin = $this->manager['skin'];
			$this->manager['userurl']			= '../../board/?id='.$this->manager['id'];//사용자보기
			$this->manager['dataurl']			= $this->Boardmanager->realboardurl.$this->manager['id'];//게시글관리

			$categorylist = ($this->manager['category'])?@explode(",",$this->manager['category']):'';
			$mode = "boardmanager_modify";

			//상품형은 아이디/게시판명 변경불가@2013-09-05
			//$this->manager['nameread']	= (BOARDID == 'goods_qna' || BOARDID == 'goods_review' ) ? true:false;
			$this->manager['list_show']		.= (BOARDID == 'goods_qna' || BOARDID == 'goods_review' || BOARDID == 'store_review') ? '[images]':'';

			//사용여부 설정가능
			$useform['auth_read']		= (BOARDID == 'notice' || BOARDID == 'faq' || BOARDID == 'mbqna'  || BOARDID == 'goods_qna' || BOARDID == 'gs_seller_notice'  || BOARDID == 'gs_seller_qna' || BOARDID == 'npay_qna' ) ? '':'Y';

			// 작성권한-쓰기
			$useform['auth_write']	= (BOARDID == 'notice' || BOARDID == 'faq' || BOARDID == 'mbqna'  || BOARDID == 'gs_seller_notice' || BOARDID == 'npay_qna' ) ? '':'Y';

			// 작성권한-답글
			$useform['auth_reply']		= (BOARDID == 'notice' || BOARDID == 'faq' || BOARDID == 'mbqna'  || BOARDID == 'bulkorder' || BOARDID == 'goods_review' || BOARDID == 'gs_seller_notice'  || BOARDID == 'gs_seller_qna' || BOARDID == 'store_reservation' || BOARDID == 'store_gallery' || BOARDID == 'npay_qna') ? '':'Y';

			// 작성권한-댓글BOARDID == 'notice' ||
			$useform['auth_cmt']		= (BOARDID == 'faq' || BOARDID == 'mbqna' || BOARDID == 'bulkorder'  || BOARDID == 'gs_seller_notice'  || BOARDID == 'gs_seller_qna' || BOARDID == 'store_review' || BOARDID == 'store_reservation' || BOARDID == 'store_gallery' || BOARDID == 'npay_qna') ? '':'Y';

			//글평가@2014-08-11
			$useform['auth_recommend']		= (BOARDID == 'faq' || BOARDID == 'mbqna' || BOARDID == 'bulkorder'  || BOARDID == 'gs_seller_notice'  || BOARDID == 'gs_seller_qna' || BOARDID == 'npay_qna') ? '':'Y';
			//댓글평가@2014-08-11
			$useform['auth_cmt_recommend']		= (BOARDID == 'faq' || BOARDID == 'mbqna' || BOARDID == 'bulkorder'  || BOARDID == 'gs_seller_notice'  || BOARDID == 'gs_seller_qna' || BOARDID == 'npay_qna') ? '':'Y';

			$useform['display']['skin']['use'] = (BOARDID == 'product_bbs' || BOARDID == 'gallery_bbs' || BOARDID == 'notice' || BOARDID == 'faq' || BOARDID == 'mbqna'  || BOARDID == 'goods_qna' || BOARDID == 'goods_review' || BOARDID == 'bulkorder' || BOARDID == 'gs_seller_notice' || BOARDID == 'gs_seller_qna' || BOARDID == 'store_review' || BOARDID == 'store_reservation' || BOARDID == 'npay_qna') ? false:true;

			if(  $useform['display']['skin']['use'] == false ) {
				if($this->manager['skin_type'] == 'goods'){
					$useform['display']['skin']['title'] = "상품기본형";
				}elseif($this->manager['id'] == 'mbqna' || $this->manager['id'] == 'bulkorder'  || BOARDID == 'gs_seller_qna' || BOARDID == 'store_reservation' ){
					$useform['display']['skin']['title'] = "1:1문의형";
				}elseif($this->manager['id'] == 'faq'){
					$useform['display']['skin']['title'] = "FAQ형";
				}elseif($this->manager['id'] == 'store_review'){
					$useform['display']['skin']['title'] = "상점기본형-리스트";
				}else{
					$useform['display']['skin']['title'] = "리스트형";
				}
			}

			//권한 사용여부 권한설정
			if ( BOARDID == 'notice'  || BOARDID == 'faq' ||  BOARDID == 'goods_qna' ) {
				$useform['display']['auth_read']['title']		= '전체';
				$useform['display']['auth_read']['input']		= '<input type="hidden" name="auth_read[]" value="all" id="auth_readall" /> ';
			}elseif(BOARDID == 'mbqna' || BOARDID == 'npay_qna' ){
				$useform['display']['auth_read']['title']		= '회원 또는 작성자';
				$useform['display']['auth_read']['input']		= '<input type="hidden" name="auth_read[]" value="memberall" id="auth_readmember" >';
			}elseif($this->manager['id'] == 'bulkorder' ){
				$useform['display']['auth_read']['title']		= '회원 또는 작성자';
				$useform['display']['auth_read']['input']		= '<input type="hidden" name="auth_read[]" value="all" id="auth_readmember" >';
			}elseif( BOARDID == 'gs_seller_qna' ) {
				$useform['display']['auth_read']['title']		= '작성자';
				$useform['display']['auth_read']['input']		= '<input type="hidden" name="auth_read[]" value="memberall" id="auth_readmember" >';
			}elseif( BOARDID == 'gs_seller_notice' ){
				$useform['display']['auth_read']['title']		= '입점사';
				$useform['display']['auth_read']['input']		= '<input type="hidden" name="auth_read[]" value="memberall" id="auth_readmember" >';
			}

			if ( BOARDID == 'notice' || BOARDID == 'faq' ) {
				$useform['display']['auth_write']['title']		= '대표 관리자';
				$useform['display']['auth_write']['input']  = '<input type="hidden" name="auth_write[]" value="admin" id="auth_writeadmin" >';
			}elseif( BOARDID == 'mbqna' || BOARDID == 'npay_qna' ) {
				$useform['display']['auth_write']['title']		= '회원';
				$useform['display']['auth_write']['input']		= '<input type="hidden" name="auth_write[]" value="memberall" id="auth_writeamemberall" >';
			}elseif( BOARDID == 'gs_seller_notice' ) {
				$useform['display']['auth_write']['title']		= '대표 관리자';
				$useform['display']['auth_write']['input']		= '<input type="hidden" name="auth_write[]" value="admin" id="auth_writeadmin" >';
			}elseif( BOARDID == 'gs_seller_qna' ) {
				$useform['display']['auth_write']['title']		= '입점사';
				$useform['display']['auth_write']['input']		= '<input type="hidden" name="auth_write[]" value="admin" id="auth_writeamemberall" >';
			}

			// 관리환경에서 관리자 권한 표기
			$authar = array("권한 있음", "사용 시 권한 있음","-");

			$useform['display']['admin_write']						= $authar[0];
			$useform['display']['admin_read']						= $authar[0];
			$useform['display']['admin_recommend']				= $authar[1];
			$useform['display']['admin_cmt_recommend']		= $authar[1];

			switch(BOARDID){
				case 'goods_review':
					$useform['display']['admin_reply']		= $authar[2];
					$useform['display']['admin_cmt']			= $authar[1];
					break;
				case 'store_review':
					$useform['display']['admin_reply']		= $authar[1];
					$useform['display']['admin_cmt']		= $authar[2];
					break;
				case 'goods_qna':
					$useform['display']['admin_reply']		= $authar[0];
					$useform['display']['admin_cmt']			= $authar[1];
					break;
				case 'notice':
					$useform['display']['admin_reply']		= $authar[2];
					$useform['display']['admin_cmt']		= $authar[1];
					break;
				case 'faq':
					$useform['display']['admin_reply']		= $authar[2];
					$useform['display']['admin_cmt']			= $authar[2];
					break;
				case 'mbqna':
					$useform['display']['admin_reply']		= $authar[0];
					$useform['display']['admin_cmt']			= $authar[2];
					break;
				case 'store_reservation':
					$useform['display']['admin_reply']		= $authar[0];
					$useform['display']['admin_cmt']		= $authar[2];
					break;
				case 'bulkorder':
					$useform['display']['admin_reply']		= $authar[0];
					$useform['display']['admin_cmt']			= $authar[2];
					break;
				case 'store_gallery':
					$useform['display']['admin_reply']		= $authar[2];
					$useform['display']['admin_cmt']		= $authar[2];
					break;
				default:
					$useform['display']['admin_reply']		= $authar[1];
					$useform['display']['admin_cmt']			= $authar[1];
					break;
			}

			//기능설정
			$useform['display']['autowrite_use']	= ( BOARDID == 'notice' || BOARDID == 'faq'  || BOARDID == 'mbqna' || BOARDID == 'store_gallery') ? false:true;
			$useform['display']['file_use']			= ( BOARDID == 'goods_qna' || BOARDID == 'faq'  || BOARDID == 'goods_review' || BOARDID == 'store_reservation') ? false:true;
			$useform['display']['newhot']			= ( BOARDID == 'mbqna' || BOARDID == 'faq' || BOARDID == 'store_review' || BOARDID == 'store_reservation') ? false:true;
			$useform['display']['list_show']		= ( BOARDID == 'mbqna' || BOARDID == 'faq' || BOARDID == 'store_review' || BOARDID == 'store_reservation' || BOARDID == 'store_gallery') ? false:true;


			$video_screen = explode("X" , $this->manager['video_screen']);
			$this->manager['video_screen0'] = $video_screen[0];
			$this->manager['video_screen1'] = $video_screen[1];

			$gallerycell = explode("X" , $this->manager['gallerycell']);
			$this->manager['gallerycell0'] = $gallerycell[0];
			$this->manager['gallerycell1'] = $gallerycell[1];

			$video_size = explode("X" , $this->manager['video_size']);
			$this->manager['video_size0'] = $video_size[0];
			$this->manager['video_size1'] = $video_size[1];

			$video_size_mobile = explode("X" , $this->manager['video_size_mobile']);
			$this->manager['video_size_mobile0'] = $video_size_mobile[0];
			$this->manager['video_size_mobile1'] = $video_size_mobile[1];

			$cfg_goods = config_load("goods");
			$this->manager['goods_video_use'] = $this->manager['video_use'];
			$this->manager['ucc_domain'] = $cfg_goods['ucc_domain'];
			$this->manager['ucc_key'] = $cfg_goods['ucc_key'];

			$this->managerurl = $this->Boardmanager->managerurl.'?'.str_replace("&id=".BOARDID,"",str_replace("id=".BOARDID,"",$_SERVER['QUERY_STRING']));
			if ( BOARDID == 'goods_review' ) {

				$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
				$this->template->assign($reserves);
			}

			if ( BOARDID == 'goods_review' ) {

				$this->manager['goodsreviewicon'] = $this->Boardmanager->goodsreviewicon;//평가정보

				//추가 조건 있는지 확인
				$qry = "select count(*) as cnt, max(bulkorderform_seq) as maxid from fm_boardform";
				$query = $this->db->query($qry);
				$sub_row = $query -> row_array();
				$this->template->assign('sub_cnt',$sub_row);

				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				unset($goodsreview_sub);
				foreach ($user_arr as $user){
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$goodsreview_sub[] = $user;
				}
				$this->template->assign('goodsreview_sub', $goodsreview_sub);
				$surveyFilePath = dirname($this->template_path())."/_survey.htm";
				$this->template->define(array('surveyForm'=>$surveyFilePath));

			}else if ( BOARDID == 'bulkorder' ) {

				//추가 조건 있는지 확인
				$qry = "select count(*) as cnt, max(bulkorderform_seq) as maxid from fm_boardform";
				$query = $this->db->query($qry);
				$sub_row = $query -> row_array();
				$this->template->assign('sub_cnt',$sub_row);


				//대량구매 정보
				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				foreach ($user_arr as $user){
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$bulkorder_sub[] = $user;
				}
				$this->template->assign('bulkorder_sub', $bulkorder_sub);
				$surveyFilePath = dirname($this->template_path())."/_survey.htm";
				$this->template->define(array('surveyForm'=>$surveyFilePath));

				##
				$sms_info = config_load('sms_info');
				if($sms_info['send_num']) $send_num = explode("-",$sms_info['send_num']);
				if($sms_info['admis_cnt']>0){
					for($i=0;$i<$sms_info['admis_cnt'];$i++){
						$id = "admins_num_".$i;
						$v['number'] = explode("-",$sms_info[$id]);
						$admins_arr[] = $v;
					}
				}

				###
				$sms_arr = array("bulkorder_write","bulkorder_reply");
				$sms_userdis = array("disabled","");
				$sms_dis = array("","disabled");
				$sms_text = array("글 등록 시","글 답변 시");
				$sms = config_load('sms');
				for($i=0;$i<count($sms_arr);$i++){
					###
					$v['name']	= $sms_arr[$i];
					$v['text']	= $sms_text[$i];

					###
					unset($v['user']);
					unset($v['admin']);
					if(isset($sms[$sms_arr[$i]."_user"])) $v['user'] = $sms[$sms_arr[$i]."_user"];
					if(isset($sms[$sms_arr[$i]."_admin"])) $v['admin'] = $sms[$sms_arr[$i]."_admin"];

					###
					unset($v['user_chk']);
					unset($v['admins_chk']);
					if(isset($sms[$sms_arr[$i]."_user_yn"])) $v['user_chk'] = $sms[$sms_arr[$i]."_user_yn"];
					for($j=0;$j<$sms_info['admis_cnt'];$j++){
						if(isset($sms[$sms_arr[$i]."_admins_yn_".$j])) $v['admins_chk'][] = $sms[$sms_arr[$i]."_admins_yn_".$j];
					}


					$v['user_disabled'] = $sms_userdis[$i];
					$v['disabled'] = $sms_dis[$i];
					$v['arr'] = $admins_arr;
					$loop[]		= $v;
				}

				$this->template->assign('sms_loop',$loop);


			}

			if( BOARDID != 'bulkorder' &&  BOARDID != 'notice' &&  BOARDID != 'faq' &&  BOARDID != 'gs_seller_notice' ){
				##
				$sms_info = config_load('sms_info');
				if($sms_info['send_num']) $send_num = explode("-",$sms_info['send_num']);
				if($sms_info['admis_cnt']>0){
					for($i=0;$i<$sms_info['admis_cnt'];$i++){
						$id = "admins_num_".$i;
						$v['number'] = explode("-",$sms_info[$id]);
						$admins_arr[] = $v;
					}
				}

				###
				$sms_arr = array(BOARDID."_write",BOARDID."_reply");
				$sms_userdis = array("disabled","");
				$sms_dis = array("","disabled");

				if( BOARDID == 'goods_qna' || BOARDID == 'mbqna' ) {
					$sms_text = array("사용자가 글 등록 시","관리자가 글 답변 시");
				}elseif( BOARDID == 'goods_review' ) {
					$sms_text = array("사용자가 글 등록 시","관리자가 글 댓글 시");
				}else{
					$sms_text = array("사용자가 글 등록 시","관리자가 글 답글 시");
				}

				# SMS 발송제한 설정 불가
				$sms_rest_use = true;
				if(in_array(BOARDID,array("notice","faq","store_review","store_gallery"))) $sms_rest_use = false;

				if($sms_rest_use){
					## SMS발송시간 제한
					$sms_rest = config_load('sms_restriction');
					if( $sms_rest['board_time_s'] && $sms_rest['board_time_e'] && $sms_rest['board_reserve_time']){
						if($sms_rest['board_reserve_time'] > 60){
							$sms_rest['board_reserve_time'] = ($sms_rest['board_reserve_time']/60)."시간";
						}else{
							$sms_rest['board_reserve_time'] .= "분";
						}
						$restriction_msg = "<span style='color:#d90000'>발송제한시간 : ";
						$restriction_msg.= $sms_rest['board_time_s']."시~".$sms_rest['board_time_e']."시 ";
						$restriction_msg.= " ▶ 08시 +".$sms_rest['board_reserve_time']."</span>";
					}

					// 카카오 메세지 정보 호출 :: 2018-03-15 lwh
					if(in_array(BOARDID,array("goods_review","goods_qna","mbqna"))){
						$this->load->model('kakaotalkmodel');
						$kakaotalk_config	= $this->kakaotalkmodel->get_service();
						if($kakaotalk_config['status'] == 'A' && $kakaotalk_config['use_service'] == 'Y') $kakaotalk_use = true;
						if ($kakaotalk_use){
							foreach ($sms_arr as $k => $bid){
								$scParams['msg_code'][] = $bid.'_user';
							}
							$msg_list = $this->kakaotalkmodel->get_msg_code($scParams);
						}
					}

					$sms = config_load('sms');

					for($i=0;$i<count($sms_arr);$i++){
						###
						$v = array();
						$v['name']	= $sms_arr[$i];
						$v['text']	= $sms_text[$i];

						## 발송제한시간 안내
						if(strstr($v['name'],"write")){
							if( $sms_rest['board_toadmin'] == "checked"){ $v['sms_rest_admin'] = $restriction_msg;
							}else{ $v['sms_rest_admin'] = ''; }
						}

						if(strstr($v['name'],"reply")){
							if( $sms_rest['board_touser'] == "checked"){ $v['sms_rest_user'] = $restriction_msg;
							}else{ $v['sms_rest_user'] = ''; }
						}

						###
						unset($v['user']);
						unset($v['admin']);
						if(isset($sms[$sms_arr[$i]."_user"])) $v['user'] = $sms[$sms_arr[$i]."_user"];
						if(isset($sms[$sms_arr[$i]."_admin"])) $v['admin'] = $sms[$sms_arr[$i]."_admin"];

						###
						unset($v['user_chk']);
						unset($v['admins_chk']);
						unset($v['sms_provider_chk']);
						if(isset($sms[$sms_arr[$i]."_user_yn"])) $v['user_chk'] = $sms[$sms_arr[$i]."_user_yn"];
						for($j=0;$j<$sms_info['admis_cnt'];$j++){
							if(isset($sms[$sms_arr[$i]."_admins_yn_".$j])) $v['admins_chk'][] = $sms[$sms_arr[$i]."_admins_yn_".$j];
						}

						if(isset($sms[$sms_arr[$i]."_provider_yn"])) $v['sms_provider_chk']	= $sms[$sms_arr[$i]."_provider_yn"];

						$v['user_disabled'] = $sms_userdis[$i];
						$v['disabled'] = $sms_dis[$i];
						$v['arr'] = $admins_arr;
						$v['to_provider_sms']		= 'N';
						if($v['name'] == 'goods_qna_write' && serviceLimit('H_AD') ){
							$v['to_provider_sms']	= 'Y';
						}

						if($v['name'] == 'goods_qna_reply' && serviceLimit('H_AD') ){
							$v['text']	= "관리자(입점판매자)가 글 답변 시";
						}

						$v['provider_chk'] = $sms[$sms_arr[$i]."_provider_yn"];

						// 카카오 메세지 사용여부 :: 2018-03-15 lwh
						// 알림톡 사용여부
						if ($kakaotalk_use){
							if ($msg_list[$v['name'].'_user']){
								$v['kkotalk_use'] = $msg_list[$v['name'].'_user']['msg_yn'];
							} else {
								$v['kkotalk_use'] = false;
							}
						}

						$loop[]		= $v;

					}

					$this->template->assign('sms_loop',$loop);
				}
			}

			if($this->manager['type'] == 'A') {//추가게시판인경우
				$tag['name']		= 'auth_reply';
				$tag['selected']	= (isset($this->manager['auth_reply']))?$this->manager['auth_reply']:'[all]';
				$auth_reply_form = $this->authForm($tag, BOARDID, $this->manager['type']);//답글추가게시판 권한설정

				$tag['name']		= 'auth_cmt';
				$tag['selected']	= (isset($this->manager['auth_cmt']))?$this->manager['auth_cmt']:'[all]';
				$auth_cmt_form = $this->authForm($tag, BOARDID, $this->manager['type']);//댓글추가게시판 권한설정
			}

		}else{
			$this->manager	= '';//게시판정보초기화
			$skin					= '';
			$categorylist		= '';
			$mode					= "boardmanager_write";

			$this->manager['type']				= 'A';//추가게시판
			$this->manager['pagenum']					= '20';//페이지노출수
			$this->manager['icon_new_day']			= '1';//new 등록후 일수
			$this->manager['icon_hot_visit']			= '30';//hot 조횟수
			$this->manager['write_admin']				= '관리자';


			//사용여부 설정가능
			$useform['auth_read']		= 'Y';
			$useform['auth_write']	= 'Y';
			$useform['auth_reply']		= 'Y';
			$useform['auth_cmt']		= 'Y';
			$useform['auth_recommend']			= 'Y';
			$useform['auth_cmt_recommend']	= 'Y';
			$useform['display']['skin']['use'] = true;

			$authar = array("권한 있음", "사용 시 권한 있음","-");
			$useform['display']['admin_write']		= $authar[0];
			$useform['display']['admin_read']		= $authar[0];
			$useform['display']['admin_reply']		= $authar[1];
			$useform['display']['admin_cmt']			= $authar[1];
			$useform['display']['admin_recommend']				= $authar[1];
			$useform['display']['admin_cmt_recommend']		= $authar[1];


			//기능설정
			$useform['display']['autowrite_use']	= true;
			$useform['display']['file_use']				= true;
			$useform['display']['newhot']				= true;
			$useform['display']['list_show']			= true;

			$this->manager['gallerycell0'] = 4;
			$this->manager['gallerycell1'] = 5;

			$this->manager['video_type'] = '400';
			$this->manager['video_screen0'] = '400';
			$this->manager['video_screen1'] = '300';

			$this->manager['video_size0'] = '400';
			$this->manager['video_size1'] = '300';

			$this->manager['video_size_mobile0'] = '200';
			$this->manager['video_size_mobile1'] = '150';

			$this->manager['video_use'] = 'N';

			$cfg_goods = config_load("goods");
			$this->manager['goods_video_use'] = $this->manager['video_use'];
			$this->manager['ucc_domain'] = $cfg_goods['ucc_domain'];
			$this->manager['ucc_key'] = $cfg_goods['ucc_key'];

			$this->managerurl = $this->Boardmanager->managerurl.'?'.$_SERVER['QUERY_STRING'];

			##
			$sms_info = config_load('sms_info');
			if($sms_info['send_num']) $send_num = explode("-",$sms_info['send_num']);
			if($sms_info['admis_cnt']>0){
				for($i=0;$i<$sms_info['admis_cnt'];$i++){
					$id = "admins_num_".$i;
					$v['number'] = explode("-",$sms_info[$id]);
					$admins_arr[] = $v;
				}
			}

			###
			$sms_arr = array(BOARDID."_write",BOARDID."_reply");
			$sms_userdis = array("disabled","");
			$sms_dis = array("","disabled");
			$sms_text = array("사용자가 글 등록 시","관리자가 글 답변 시");
			$sms = config_load('sms');
			for($i=0;$i<count($sms_arr);$i++){
				###
				$v['name']	= $sms_arr[$i];
				$v['text']	= $sms_text[$i];

				###
				unset($v['user']);
				unset($v['admin']);
				$v['user'] = "[{shopName}] 답변드렸습니다.";
				$v['admin'] = "[{boardName}] {userid} 글이 등록되었습니다.";

				###
				unset($v['user_chk']);
				unset($v['admins_chk']);
				if(isset($sms[$sms_arr[$i]."_user_yn"])) $v['user_chk'] = $sms[$sms_arr[$i]."_user_yn"];
				for($j=0;$j<$sms_info['admis_cnt'];$j++){
					if(isset($sms[$sms_arr[$i]."_admins_yn_".$j])) $v['admins_chk'][] = $sms[$sms_arr[$i]."_admins_yn_".$j];
				}
				$v['user_disabled'] = $sms_userdis[$i];
				$v['disabled'] = $sms_dis[$i];
				$v['arr'] = $admins_arr;
				$loop[]		= $v;
			}
			$this->template->assign('sms_loop',$loop);

			$tag['name']		= 'auth_reply';
			$tag['selected']	= (isset($this->manager['auth_reply']))?$this->manager['auth_reply']:'[all]';
			$auth_reply_form = $this->authForm($tag, BOARDID, 'A');//답글추가게시판 권한설정

			$tag['name']		= 'auth_cmt';
			$tag['selected']	= (isset($this->manager['auth_cmt']))?$this->manager['auth_cmt']:'[all]';
			$auth_cmt_form = $this->authForm($tag, BOARDID, 'A');//댓글추가게시판 권한설정

		}//신규게시판생성시

		//boardalllist();//게시판전체리스트

		$tag['name']		= 'auth_read';
		$tag['selected']	= (isset($this->manager['auth_read']))?$this->manager['auth_read']:'[all]';
		$auth_read_form = $this->authForm($tag, BOARDID);//읽기권한설정

		$tag['name']		= 'auth_write';
		$tag['selected']	= (isset($this->manager['auth_write']))?$this->manager['auth_write']:'[all]';
		$auth_write_form = $this->authForm($tag, BOARDID, $this->manager['type']);//쓰기/답글/댓글 권한설정

		if ( BOARDID == 'goods_review' ) {
			$tag['name']		= 'auth_write_cmt';
			$tag['selected']	= (isset($this->manager['auth_write_cmt']))?$this->manager['auth_write_cmt']:'[all]';
			$auth_write_cmt_form = $this->authForm($tag, BOARDID);//댓글 권한설정

			$reviewcategorylist = ($this->manager['reviewcategory'])?@explode(",",$this->manager['reviewcategory']):'';

			// 기본 평가항목 셋팅
			if(!$reviewcategorylist){
				$defaultset_sql = "update fm_boardmanager set reviewcategory = '평점' where id = '".BOARDID."'";
				$query = $this->db->query($defaultset_sql);
				$reviewcategorylist[] = '평점';
			}
		}elseif ( BOARDID == 'notice' ) {
			$tag['name']		= 'auth_write_cmt';
			$tag['selected']	= (isset($this->manager['auth_write_cmt']))?$this->manager['auth_write_cmt']:'[all]';
			$auth_write_cmt_form = $this->authForm($tag, BOARDID);//댓글 권한설정
		}

		if ( BOARDID == 'store_review' ) {
			$tag['name']		= 'auth_reply';
			$tag['selected']	= (isset($this->manager['auth_reply']))?$this->manager['auth_reply']:'[all]';

			$auth_write_reply_form = $this->authForm($tag, BOARDID);// 답글 권한설정
		}

		//글평가권한@2014-08-11
		$tag['name']		= 'auth_recommend';
		$auth_recommend_form = $this->authForm($tag, BOARDID, $this->manager['type']);
		//댓글평가권한@2014-08-11
		$tag['name']		= 'auth_cmt_recommend';
		$auth_cmt_recommend_form = $this->authForm($tag, BOARDID, $this->manager['type']);

		$skinlist = BoardManagerskinlist();//스킨폴더정보
		$tag['name']		= 'list_show';
		$tag['selected']	= (isset($this->manager['list_show']))			?$this->manager['list_show']:'[num][images][subject][contents][date][hit][writer]';//
		$tag['subjectcut']	= (isset($this->manager['subjectcut'])>0)	?$this->manager['subjectcut']:30;
		$tag['contcut']		= (isset($this->manager['contcut'])>0)			?$this->manager['contcut']:200;
		$listshowform		= $this->BoardListShowForm($tag);//리스트화면 표시항목

		###
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		$managerauth = $this->membermodel->admin_manager_list($sc,'all');
		foreach($managerauth['result'] as $managerauthrow) {
			$adsc['boardid']				= (BOARDID)?BOARDID:$_GET['id'];
			$adsc['manager_seq']		= $managerauthrow['manager_seq'];
			$boardadmin = $this->boardadmin->get_data($adsc);
			if( $boardadmin ) {
				if($boardadmin['board_view'] == 2 ) {
					$managerauthrow['board_view_pw'] = 2;
				}
				$managerauthrow['board_view']	= $boardadmin['board_view'];
				$managerauthrow['board_act']	= $boardadmin['board_act'];
			}else{
				if(isset($_GET['id']) || $managerauthrow['manager_yn'] == 'Y') {//수정시
					$managerauthrow['board_view_pw'] = 2;
					$managerauthrow['board_view']	= 1;
					$managerauthrow['board_act']	= 1;
				}
			}
			$managerloop[] = $managerauthrow;
		}
		###
		if(isset($managerauth)) $this->template->assign('managerlist',$managerloop);
		getboardicon();//라인변경주의

		// 운영방식 추가 :: 2018-11-26 pjw
		$operation_type = $this->config_system['operation_type'];

		$this->template->assign($this->manager);//게시판정보
		$this->template->assign(array(
			'operation_type'			=>$operation_type,
			'mode'						=>$mode,
			'useform'					=>$useform,
			'auth_read_form'			=>$auth_read_form,
			'auth_write_form'			=>$auth_write_form,
			'auth_write_reply_form'		=>$auth_write_reply_form,
			'auth_write_cmt_form'		=>$auth_write_cmt_form,
			'auth_reply_form'			=>$auth_reply_form,
			'auth_cmt_form'				=>$auth_cmt_form,
			'auth_recommend_form'		=>$auth_recommend_form,
			'auth_cmt_recommend_form'	=>$auth_cmt_recommend_form,
			'skinlist'					=>$skinlist,
			'managerurl'				=>$this->Boardmanager->managerurl,
			'categorylist'				=>$categorylist,
			'reviewcategorylist'		=>$reviewcategorylist,
			'listshowform'				=>$listshowform,
			'sms_rest_use'				=>$sms_rest_use,
			));
		$this->template->print_("tpl");
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

		$html = ' <label class="resp_radio"><input type="radio" name="'.$tag['name'].'[]" value="all" id="'.$tag['id'].'all" '.$all.' class="'.$tag['name'].'" />전체</label>';

		if( $boardid == 'goods_review' && $tag['name'] == 'auth_write_cmt'  ) {// || $boardid == 'notice'
			$member = (isset($tag['selected']) && strstr($tag['selected'],'[memberall]') ) ? ' checked="checked" ':'';
			//회원그룹 선택
			$html .= ' <label class="resp_radio"><input type="radio" name="'.$tag['name'].'[]" value="memberall" id="'.$tag['id'].'member" class="'.$tag['id'].'"  '.$member.'/>회원</label>';
		}else{
			//회원그룹 선택
			$html .= ' <label class="resp_radio"><input type="radio" name="'.$tag['name'].'[]" value="member" id="'.$tag['id'].'member" class="'.$tag['id'].'"  '.$member.'/>회원</label> ( ';
			$query = $this->db->query("select group_seq,group_name from fm_member_group");
			foreach($query->result_array() as $row){
				$group_checked = (!empty($member) && isset($tag['selected']) && strstr($tag['selected'],'[group:'.$row['group_seq'].']') ) ? ' checked="checked" ':'';
				$html .= ' <label class="resp_checkbox"><input type="checkbox" name="'.$tag['name'].'_group[]" id="'.$tag['id'].'_group_'.$row['group_seq'].'"  value="'.$row['group_seq'].'" class="'.$tag['id'].'_group" '.$group_checked.' '.$memberdisable.' />'.$row['group_name'].'</label>';
				$html .= ',';
			}
			$html = substr($html,0,-1).' )';
		}


		if( $boardid == 'goods_review' && $tag['name'] == 'auth_write'  ) {
			$html .= ' <label class="resp_radio"><input type="radio" name="'.$tag['name'].'[]" value="onlybuyer" id="'.$tag['id'].'onlybuyer" '.$onlybuyer.' class="'.$tag['name'].'" />구매자(회원/비회원) : \'배송완료\'된 상품을 조회하여 상품후기 작성 </label>';
		}else if( $boardid == 'store_review' && $tag['name'] == 'auth_write'  ) {
			$html .= ' <label class="resp_radio"><input type="radio" name="'.$tag['name'].'[]" value="onlybuyer" id="'.$tag['id'].'onlybuyer" '.$onlybuyer.' class="'.$tag['name'].'" />구매자(회원) : 쿠폰을 구매하여 사용한 회원</label>';
		}elseif($type == 'A' && $tag['name'] != 'auth_read' ){
			$html .= ' <label class="resp_radio"><input type="radio" name="'.$tag['name'].'[]" value="admin" id="'.$tag['id'].'admin" class="'.$tag['id'].'"  '.$admin.'/>관리자</label>';
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
			$this->scoretitle = "<span id='scoreid'>5단계평가</span>";
		}elseif( $this->manager['recommend_type'] == '2' ) {
			$this->scoretitle = "<span id='scoreid'>추천/비추천</span>";
		}else{
			$this->scoretitle = "<span id='scoreid'>추천</span>";
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
				$html .= ' <label for="'.$tag['id'].$id.'" class="resp_checkbox"><input type="checkbox" name="'.$tag['name'].'[]" value="'.$id.'" id="'.$tag['id'].$id.'" '.$checked.' '.$disabled.'/>'.$title.'</label>&nbsp; ';
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
			$returnurl = str_replace("&cmtpage=".$_GET['cmtpage'],"",str_replace("&cmtlist=1",'',str_replace("cmtlist=1".$_GET['seq']."&","",$this->boardurl->cmtview))).'&cmtlist=1';
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
		$paginlay =  pagingtag($sc['searchcount']	,$sc['perpage'],'/admin/board/review_select_list?id='.BOARDID.'&displayId='.$_GET['displayId'], getLinkFilter('',array_keys($sc)) );
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
		$sc['whereis']	= ' and id= "'.$boardid.'" ';
		$sc['select']		= ' * ';
		$this->manager  = $this->Boardmanager->managerdataidck($sc);//게시판정보
		$this->template->assign('manager',$this->manager );


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


	public function counsel_catalog(){

		$this->load->model("membermodel");
		$this->load->model("ordermodel");

		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth = $this->authmodel->manager_limit_act('counsel_view');

		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$counsel_act_auth = $this->authmodel->manager_limit_act('counsel_act');
		$this->template->assign("counsel_act_auth",$counsel_act_auth);


		$data['category'] = array("주문","배송","반품","환불");
		$sc["perpage"] = $_GET['perpage'] ? $_GET['perpage'] : 15;
		$sc["page"] = $_GET['page'] ? $_GET['page'] : 1;
		if(!$_GET['dateType']) $_GET['dateType'] = "counsel_regdate";
		if($_GET['sdate']){
			$whereSql .= " and co.".$_GET['dateType']." >= '".$_GET['sdate']." 00:00:00'";
		}

		if($_GET['edate']){
			$whereSql .= " and co.".$_GET['dateType']." <= '".$_GET['edate']." 23:59:59'";
		}

		if($_GET['search_text']){
				$whereSql .= " and (
					co.counsel_contents like '%{$_GET['search_text']}%'
				)";

		}

		if($_GET['manager_name']){
			$whereSql .= " and co.manager_name = '".$_GET['manager_name']."'";
		}

		if($_GET['relationCode']){
			$whereSql .= " and co.".$_GET['relationType']." = '".$_GET['relationCode']."'";
		}


		if(is_array($_GET['counsel_status'])) {
			$counselStatus = "'".join("','", $_GET['counsel_status'])."'";
			if($_GET['counsel_status']){
				$whereSql .= " and co.counsel_status in (".$counselStatus.")";
			}
		}
		else {
			if($_GET['counsel_status']){
				$whereSql .= " and co.counsel_status = '".addslashes($_GET['counsel_status'])."'";
			}
		}

		if	($_GET['search_member']){
			$columns = [
				'user_name',
				'userid',
				'phone',
				'cellphone',
				'email',
			];
			switch($_GET['search_type']) {
				case 'name':
					$columns = ['user_name'];
				break;
				case 'id':
					$columns = ['userid'];
				break;
				case 'tel':
					$columns = ['phone'];
				break;
				case 'cell':
					$columns = ['cellphone'];
				break;
				case 'email':
					$columns = ['email'];
				break;
				default:
			}
			$whereSql .= ' AND ('.implode(' OR ', array_map(function($column) {
				return "mem.{$column} LIKE \"%{$_GET['search_member']}%\"";
			}, $columns)).') ';
		}

		$sql	= "select
						mem.member_seq,
						mem.mtype,
						mem.user_name,
						mem.userid,
						mem.blacklist,
						IF(mem.member_seq>0,(select group_name from fm_member_group where mem.group_seq = group_seq limit 1),'비회원') as group_name,
						co.*
					from fm_counsel as co
						left join fm_member as mem
							on co.member_seq = mem.member_seq
					where co.counsel_seq > 0 " . $whereSql . "
					order by counsel_seq desc";
		$result = select_page($sc['perpage'],$sc['page'],10,$sql,array());

		foreach($result['record'] as $key=>$data){
			if(!$data['member_seq']){
				$odata = $this->ordermodel->get_order($data['order_seq']);
				$result['record'][$key]['order_user_name'] = $odata['order_user_name'];
				$result['record'][$key]['order_seq'] = $odata['order_seq'];
				$result['record'][$key]['blacklist'] = $odata['blacklist'];
			}
		}
		$this->template->assign($result);

		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");

	}

	/**
	 * 게시글 신고관리
	 */
	public function report() {
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth = $this->authmodel->manager_limit_act('report_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}

		$this->load->library('Board/BoardReportLibrary');

		$sc = $this->input->get();
		$sc['perpage']			= (!empty($sc['perpage'])) ? intval($sc['perpage']):10;
		$sc['page']				= (!empty($sc['page'])) ? intval($sc['page']):0;
		$sc['searchcount']		= true;
		if($sc['search_text'] && !$sc['search_type']) {
			$sc['search_type'] = 'all';
		}

		list($loop, $pagin) = $this->boardreportlibrary->getReportList($sc);

		$this->template->assign([
			'sc'=>$sc,
			'scObj'=>json_encode($sc),
			'loop'=>$loop,
			'pagin'=>$pagin,
		]);
		$this->template->define(array('tpl'=>$file_path));
		$this->template->print_("tpl");
	}

	/**
	 * 신고관리 상세
	 */
	public function report_detail() {
		$this->admin_menu();
		$this->tempate_modules();
		$file_path	= $this->template_path();

		$auth = $this->authmodel->manager_limit_act('report_view');
		if(!$auth){
			pageBack("권한이 없습니다.");
			exit;
		}
		
		$this->load->library('Board/BoardReportLibrary');

		$seq = $this->input->get('seq');

		// 신고 내용
		$data = $this->boardreportlibrary->viewReport($seq);
		
		if(!$data) {
			echo json_encode(['result'=>FALSE]);
			exit;
		}
		
		$this->template->assign($data);
		$this->template->define(array('tpl'=>$file_path));
		$html = $this->template->fetch("tpl");
		echo json_encode(['result'=>TRUE, 'html'=>$html]);
		exit;
	}
}
/* End of file board.php */
/* Location: ./app/controllers/admin/board.php */

