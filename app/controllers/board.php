<?php
/**
 * 게시판/게시물 관련 클래스
 * @author gabia
 * @since version 2.0 - 2012.06.29
 */
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);

class Board extends front_base {
	protected $boardid;
	var $manager;

	function __construct() {
		parent::__construct();
		$this->set_construct_init();
		$this->load->helper("member");
		
		// 반응형에선 left 메뉴 따로 정의 :: 2019-02-13 pjw
		if($this->config_system['operation_type'] == 'light'){
			$this->template->define('board_lnb', $this->skin."/_modules/common/board_lnb.html");
		}
	}

	function set_construct_init(){

		$_GET['popup']	= chk_parameter_xss_clean($_GET['popup']);
		$_GET['iframe']	= chk_parameter_xss_clean($_GET['iframe']);

		$file_path	= $this->template_path();
		define('FILE_PATH', $file_path);
		if ( !defined('BOARDID') && $_GET['id'] ) {
			secure_vulnerability('board', 'boardid', $_GET['id']);
			define('BOARDID',$_GET['id']);
		}
		$this->load->helper(array('text','board','file','download','cookie'));
		$this->load->library('Upload');
		$this->load->model('Boardmanager');
		$this->load->model('providermodel');
		$this->load->model('membermodel');
		$this->load->model('boardadmin');
		$this->load->model('Boardscorelog');
		//$this->load->model('boardfiles');
		if ( defined('BOARDID') ) {
			if( BOARDID == 'goods_qna' ) {
				$this->load->model('Goodsqna','Boardmodel');
			}elseif( BOARDID == 'goods_review' ) {
				$this->load->model('Goodsreview','Boardmodel');
			}elseif( BOARDID == 'bulkorder' ) {//대량구매게시판
				$this->load->model('Boardbulkorder','Boardmodel');
			}else{
				$this->load->model('Boardmodel');
			}
		}
		if( !$this->isplusfreenot && BOARDID == 'bulkorder' ){
			pageClose('무료몰Plus+에서는 대량구매게시판을 지원하지 않는 기능입니다.\n프리미엄몰+ 또는 독립몰+로 업그레이드 하시면 대량구매게시판을 이용 가능합니다.');
			exit;
		}

		if( BOARDID == 'gs_seller_qna' || BOARDID == 'gs_seller_notice' ){
			pageClose('입점사전용게시판입니다.');
			exit;
		}
		$this->board_editor = config_load('board_editor');

		$this->load->model('Boardindex');//공지용

		$this->thisfile = $this->uri->rsegments[count($this->uri->rsegments)];

		if ( isset($_GET['seq']) ) {//게시글 상세인 경우
			$this->load->model('Boardcomment');
			$querystr = (isset($_GET['reply'])) ? str_replace("&iframe=&","&",str_replace("&popup=&","&",str_replace("?seq=".$_GET['seq'],"?",str_replace("&seq=".$_GET['seq'],"",str_replace("reply=".$_GET['reply'],"",str_replace("&reply=".$_GET['reply'],"",str_replace("id=".BOARDID,"",str_replace("&id=".BOARDID,"",$_SERVER['QUERY_STRING'])))))))):str_replace("&iframe=&","",str_replace("&popup=&","",str_replace("?seq=".$_GET['seq'],"?",str_replace("&seq=".$_GET['seq'],"",str_replace("id=".BOARDID,"",str_replace("&id=".BOARDID,"",$_SERVER['QUERY_STRING']))))));
		}else{
			$querystr = str_replace("&iframe=&","&",str_replace("&popup=&","&",str_replace("id=".BOARDID,"",str_replace("&id=".BOARDID,"",$_SERVER['QUERY_STRING']))));
		}
		$querystr = str_replace("&&","&",str_replace("&&&","&",$querystr));
		if( $querystr &&  substr($querystr,0,1) != "&" ) $querystr = "&".$querystr;
		parse_str($querystr);

		if ( defined('BOARDID') ) {
			$sql['whereis']	= ' and id= "'.BOARDID.'" ';
			$sql['select']		= ' * ';
			$this->manager = $this->Boardmanager->managerdataidck($sql);//게시판정보
			$this->manager['write_admin_format'] = $this->manager['admin_format'];

			// 반응형 아니고 gallery02 스킨사용하면 gallery01 로 변경 2019-12-11
			if($this->config_system['operation_type'] != 'light' && $this->manager['skin'] == 'gallery02'){
				$this->manager['skin'] = 'gallery01';
			}
			boarduploaddir($this->manager);//게시판 스킨생성 및 복사
		}

		$this->joinform = ($this->joinform)?$this->joinform:config_load('joinform');
		$cfg_goods = config_load('goods');
		if($this->manager['video_use'] == 'Y' ) {
			$video_size = explode("X" , $this->manager['video_size']);
			$this->manager['video_size0'] = ($video_size[0])?$video_size[0]:'400';
			$this->manager['video_size1'] = ($video_size[1])?$video_size[1]:'200';

			$video_size_mobile = explode("X" , $this->manager['video_size_mobile']);
			$this->manager['video_size_mobile0'] = ($video_size_mobile[0])?$video_size_mobile[0]:'200';
			$this->manager['video_size_mobile1'] = ($video_size_mobile[1])?$video_size_mobile[1]:'100';
		}else{
			unset($this->manager['file_key_w'],$this->manager['file_key_i'],$this->manager['video_size']);
			//$this->manager['video_use']	= 'N';
		}

		$this->manager['file_use'] = (!(  $this->mobileMode || $this->_is_mobile_agent) && $this->manager['file_use'] == 'Y' && $this->manager['onlyimage_use'] == 'Y' )?'img':$this->manager['file_use'];

		$this->template->assign('manager',$this->manager);
		$this->board_dir = $this->Boardmanager->board_skin_dir.BOARDID.'/';
		$this->board_skin = $this->Boardmanager->board_skin_dir.BOARDID.'/'.$this->manager['skin'];
		getBoardCommentPrenextCopy();

		define('BORADSKIN', BOARDID.'/'.$this->manager['skin']);
		$this->template->assign('skindir', BORADSKIN);
		$this->template->assign('skinimgdir', BORADSKIN.'/image/');

		$this->boardurl->lists			= $this->Boardmanager->realboarduserurl.BOARDID.$querystr;				//게시물관리

		$popup = (!empty($_GET['popup'])) ? $_GET['popup']:'';
		$iframe = (!empty($_GET['iframe'])) ? $_GET['iframe']:'';
		$gdviewer = (!empty($_GET['gdviewer'])) ? '&gdviewer='.$_GET['gdviewer']:'';

		if( !strstr($querystr,'&popup=1') && $iframe==1) {//새창인경우
			$this->boardurl->resets		= (!empty($_GET['popup']) || !empty($_GET['iframe']))?$this->Boardmanager->realboarduserurl.BOARDID.'&popup=1&iframe='.$iframe.$gdviewer:$this->Boardmanager->realboarduserurl.BOARDID;				//게시물관리
			$this->boardurl->write		= $this->Boardmanager->realboardwriteurl.BOARDID.$querystr.'&popup=1';				//게시물등록
			$this->boardurl->reply			= $this->Boardmanager->realboardwriteurl.BOARDID.$querystr.'&popup=1&reply=y&seq=';	//게시물답변
			$this->boardurl->modify		= $this->Boardmanager->realboardwriteurl.BOARDID.$querystr.'&popup=1&seq=';	//게시물수정
			$this->boardurl->view			= $this->Boardmanager->realboardviewurl.BOARDID.$querystr.'&popup=1&seq=';	//게시물보기
			$this->boardurl->perm		= $this->Boardmanager->realboardpermurl.BOARDID.'&popup=1&returnurl=';						//접근권한
			$this->boardurl->pw			= $this->Boardmanager->realboardpwurl.BOARDID.'&popup=1&returnurl=';						//접근권한
			$this->boardurl->querystr			= $querystr;	//검색키
		}else{
			$this->boardurl->resets		= $this->Boardmanager->realboarduserurl.BOARDID;				//게시물관리
			if($iframe==1)	$this->boardurl->resets		.= $querystr;								//게시물관리 iframe

			$this->boardurl->write		= $this->Boardmanager->realboardwriteurl.BOARDID.$querystr;				//게시물등록
			$this->boardurl->reply			= $this->Boardmanager->realboardwriteurl.BOARDID.$querystr.'&reply=y&seq=';	//게시물답변
			$this->boardurl->modify		= $this->Boardmanager->realboardwriteurl.BOARDID.$querystr.'&seq=';	//게시물수정
			$this->boardurl->view			= $this->Boardmanager->realboardviewurl.BOARDID.$querystr.'&seq=';	//게시물보기
			$this->boardurl->perm		= $this->Boardmanager->realboardpermurl.BOARDID.'&returnurl=';						//접근권한
			$this->boardurl->pw		= $this->Boardmanager->realboardpwurl.BOARDID.'&returnurl=';						//접근권한
			$this->boardurl->querystr			= $querystr;	//검색키
		}

		$this->boardurl->goodsview		= '/goods/view?no=';						//상품접근

		$this->icon_file_img				= $this->Boardmanager->file_icon_src;//첨부파일icon
		$this->icon_image_img		= $this->Boardmanager->img_icon_src;//이미지icon
		$this->icon_video_img			= $this->Boardmanager->video_icon_src;//동영상icon
		$this->icon_mobile_img		= $this->Boardmanager->mobile_icon_src;//모바일icon

		$this->icon_award_img		= $this->Boardmanager->award_icon_src;//goodsreview

		$this->icon_hidden_img		= $this->Boardmanager->hidden_icon_src;//비밀글icon
		$this->notice_img				= $this->Boardmanager->notice_icon_src;//공지글icon
		$this->re_img						= $this->Boardmanager->re_icon_src;//답변글icon
		$this->blank_img					= $this->Boardmanager->blank_icon_src;//blank
		$this->print_img					= $this->Boardmanager->print_icon_src;//print

		$this->snst_img					= $this->Boardmanager->snst_icon_src;//twitter
		$this->snsf_img					= $this->Boardmanager->snsf_icon_src;//facebook
		$this->snsm_img					= $this->Boardmanager->snsm_icon_src;//m2day
		$this->snsy_img					= $this->Boardmanager->snsy_icon_src;//요즘

		getboardicon();//icon setting
		$this->template_path = str_replace('board/','board/'.BORADSKIN.'/',$this->template_path);
		$this->template->assign(array(
				"template_path"=>$this->template_path
		));
		$this->template->assign('ismobile',$this->_is_mobile_agent);//ismobile

		$this->protocol = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https://' : 'http://';

		//메타테그 치환용 정보
		if(!$_GET['seq']){
			$add_meta_info['board_neme']		= $this->manager['name'];
			$this->template->assign('add_meta_info',$add_meta_info);
		}

		if( BOARDID == 'goods_review' ) {
			$reserves = ($this->reserves)?$this->reserves:config_load('reserve');//마일리지자동지급관련
			if( !$this->isplusfreenot['ispoint'] ) {//포인트 미사용
				if( $reserves['autopoint_video'] >0 ) $reserves['autopoint_video'] = 0;
				if( $reserves['autopoint_photo'] >0 ) $reserves['autopoint_photo'] = 0;
				if( $reserves['autopoint_review'] >0 ) $reserves['autopoint_review'] = 0;
			}
			if( $reserves['autoemoney'] == 1 &&  $reserves['autoemoneytype'] != 3 ){
				if( $reserves['autoemoney_video'] > 0 || $reserves['autoemoney_photo'] > 0  || $reserves['autoemoney_review'] > 0 || $reserves['autopoint_video'] > 0 || $reserves['autopoint_photo'] > 0  || $reserves['autopoint_review'] > 0 ) {
					$reserves['autoemoneytitle'] = true;
				}
			}
			$this->template->assign('reserves',$reserves);
			$this->session->unset_userdata('sess_order');//비회원주문번호세션제거
		}
		$this->template->assign("templateskin","../../".$this->skin);//댓글, 이전/다음글, 파일첨부 경로추출

	}

	//사용자 게시물 등록/보기/리스트 스킨출력
	public function index($mode=null)
	{
		$this->lists();

		$this->template->assign('manager',$this->manager);//접근권한체크
		$board = $this->skin.'/board/'.BORADSKIN.'/index.html';

		// 상품상세 페이지 노출인 경우, return_url 세팅
		if($this->input->get('iframe') && (BOARDID == 'goods_review' || BOARDID == 'goods_qna')){
			$return_url= urlencode("/goods/view?no=".$this->input->get('goods_seq'));
			$this->template->assign('goods_seq',$this->input->get('goods_seq'));
			$this->template->assign('param',$this->input->get());
		}else{
			$return_url= urlencode($_SERVER['REQUEST_URI']);
		}
		$this->template->assign('return_url',$return_url);

		if($mode == 'view'){
			$this->template->assign("viewlist",$mode);
			$this->template->define(array("listskin"=>$board));//게시글 상세 하단의 리스트출력
		}else{
			$this->template->define(array("skin"=>$board));
			$this->print_layout(FILE_PATH);
		}
	}

	//사용자 게시물 등록/수정
	public function lists($page=null)
	{
		if ( defined('BOARDID') ) {
			$this->template->assign([
				'ISBOARDID' => BOARDID,
			]);
		}

		$this->load->library('validation');

		$requestParam = $this->input->get();
		$nIframe = $requestParam['iframe'];	//게시글 호출 방식이 iframe
		$sSetMode = $requestParam['setMode'];	//접속 환경이 모바일

		//위 2조건을 만족하는 경우 상품 보기의 관리자 설정 내역을 적용
		// 20201130 접속 환경 관계없이 처리.
		if ($nIframe == 1) {
			$perpage_type = 'goods';
		}

		$this->pagetype = $page;
		$this->template->assign('pagetype',$page);
		if (!isset($this->manager['id'])) {
			pageBack('존재하지 않는 게시판입니다.');
			exit;
		}

		if (!$this->isplusfreenot && $this->manager['id'] == 'bulkorder' ) {
			pageClose('무료몰Plus+에서는 대량구매게시판을 지원하지 않는 기능입니다.\n프리미엄몰+ 또는 독립몰+로 업그레이드 하시면 대량구매게시판을 이용 가능합니다.');
			exit;
		}

		get_auth($this->manager, '', 'read', $isperm);//접근권한체크
		$this->manager['isperm_read'] = ($isperm['isperm_read'] === true)?'':'_no';
		$this->manager['fileperm_read']= (isset($isperm['fileperm_read']))?$isperm['fileperm_read']:'';
		if ( $isperm['isperm_read'] === false  && $page != 'goods' ) {
			if (!defined('__ISADMIN__')) {
				if (BOARDID == 'mbqna') {//1:1문의게시판은 마이페이지에서만 이용가능
					if (!empty($_GET['popup']) ) {
						pageClose('접근권한이 없습니다!');
					} else {
						$board = $this->skin.'/board/_permcheck.html';
						$this->template->assign('boardurl',$this->boardurl);
						$this->template->assign($data);
						$this->print_layout($board);
						exit;
					}
				}
			}
		}

		get_auth($this->manager, '', 'write', $isperm);//접근권한체크
		$this->manager['isperm_write'] = ($isperm['isperm_write'] === true)?'':'_no';//'.$this->manager['isperm_write'].'


		$this->boardurl->userurl = '../../board?id='.BOARDID;//사용자보기
		$this->template->assign('boardurl',$this->boardurl);//link url
		//분류리스트 categorylist
		if($this->manager['category']){
			$categorylist = @explode(",",$this->manager['category']);
			$this->template->assign('categorylist', array_map('htmlspecialchars_decode', $categorylist));
		}
		$this->template->assign('commentlay',$this->manager['auth_cmt_use']);//댓글사용여부
		$this->template->assign('replylay',$this->manager['auth_reply_use']);//답변사용여부 //답변사용여부
		get_auth($this->manager, '', 'reply' , $isperm);//접근권한체크
		$this->template->assign('isperm_reply',$isperm['isperm_reply']);//답변회원등급 권한체크

		// 통합약관 불러오기
		$policy = chkPolicyInfo();

		/**
		 * notice setting
		**/
		$idxsc['orderby'] = 'gid';
		$idxsc['sort'] = '';
		$idxsc['notice'] = '1';

		$ndata = $this->Boardindex->idx_list($idxsc);//공지글목록

		$this->validation->set_data($requestParam);
		$this->validation->set_rules('orderby', '정렬선택', 'trim|alpha|xss_clean');
		$this->validation->set_rules('page', '페이징', 'trim|numeric|xss_clean');
		$this->validation->set_rules('perpage', '페이지갯수', 'trim|numeric|xss_clean');
		$this->validation->set_rules('search_text', '검색어', 'trim|string|xss_clean');

		if ($this->validation->exec() === false) {
			show_error($this->validation->error_array['value'], 422);
		}

		/**
		 * list setting
		**/
		$sc['orderby'] = (!empty($requestParam['orderby'])) ? $this->db->escape_str($requestParam['orderby']):'gid asc';//, m_date desc
		if(BOARDID == 'goods_qna' || BOARDID == 'goods_review') $sc['orderby'] .= ', goods_seq';		// 상품리뷰, 문의 속도 개선
		$sc['sort'] = (!empty($requestParam['sort'])) ?	$requestParam['sort']:' ';
		$sc['page'] = (!empty($requestParam['page'])) ?	intval($requestParam['page']):'0';

		if( (BOARDID == 'goods_qna' || BOARDID == 'goods_review') && ($page == 'goods' || $perpage_type == 'goods') ) {//pagenum
			$requestParam['perpage'] = $sc['perpage'] = $this->manager['goods_num'];
		}else{
			$sc['perpage'] = (
				!empty($requestParam['perpage'])
					?intval($requestParam['perpage'])
					:(
						strstr($this->manager['skin'], 'gallery')
							?(
								!empty($this->manager['gallerycell1'])
								?$this->manager['gallerycell0']*$this->manager['gallerycell1']
								:$this->manager['gallerycell0']
							)
							:$this->manager['pagenum']
					)
			);
		}

		if (!$sc['perpage']) {
			$sc['perpage'] = 10;
		}
		
		if ($page == 'mypage' || BOARDID == 'mbqna' ) {//마이페이지와 1:1문의게시판인경우
			$sc['mseq'] = $this->userInfo['member_seq'];
		}

		if ($requestParam['search_text'])
		{
			$sc['search_text'] = trim($requestParam['search_text']);
			$sc['search_text']= stripslashes(htmlspecialchars($sc['search_text']));
		}
	
		$sc['rdate_s'] = (!empty($requestParam['rdate_s'])) ? $requestParam['rdate_s'] : '';
		$sc['rdate_f'] = (!empty($requestParam['rdate_f'])) ? $requestParam['rdate_f'] : '';
		$sc['category'] = (!empty($requestParam['category'])) ? $requestParam['category'] : '';
		$sc['catalog_code'] = (!empty($requestParam['catalog_code'])) ? $requestParam['catalog_code'] : '';
		$sc['brand_code'] = (!empty($requestParam['brand_code'])) ? $requestParam['brand_code'] : '';
		$sc['goods_seq'] = (!empty($requestParam['goods_seq'])) ? (int) $requestParam['goods_seq'] : '';
		secure_vulnerability('board', 'goods_seq', $requestParam['goods_seq']);
		$sc['display'] = (!empty($requestParam['display'])) ? $requestParam['display'] : '';
		$sc['hidden'] = (!empty($requestParam['hidden'])) ? $requestParam['hidden'] : '';

		if (BOARDID == 'faq') {
			//노출여부
			$sc['hidden'] = 2;
		}

		$sc['score'] = (!empty($requestParam['score'])) ? $this->db->escape_str($requestParam['score']) : ''; //상품후기
		$sc['finish'] = (!empty($requestParam['finish'])) ? $requestParam['finish'] : ''; //프로모션(원더플레이스)
		$data = $this->Boardmodel->data_list($sc); //게시글목록
		getBoardimagefile($data);//첨부파일 리스트추출용

		/**
		 * count setting
		**/
		$sc['searchcount'] = $data['count'];
		$sc['total_page'] = @ceil($sc['searchcount'] / $sc['perpage']);
		$sc['totalcount'] = $this->Boardmodel->get_item_total_count($sc);

		$multi_copymove = true;//게시글이동복사여부
		switch ( BOARDID ) {
			case "goods_qna":
				$multi_copymove =false;
			break;
			case "goods_review":
				$multi_copymove =false;//게시글이동복사 불가
				$qry = "select * from fm_boardform  where boardid='".BOARDID."'  order by sort_seq, boardid asc";
				$query = $this->db->query($qry);
				$user_arr = $query -> result_array();
				$this->boardform_arr = $user_arr;//평점검색용
			break;
			case "faq":
				unset($ndata);
				$multi_copymove =false;//게시글이동복사 불가
			break;
			case "mbqna":
			break;
			case "bulkorder"://대량구매게시판
			break;
			case "store_review":
				$this->template->assign($policy);	//통합약관
			break;
		}
		$this->board_lists($ndata, $data, $sc, $noticeloop, $loop);

		//@2014-02-27 패치 이전소스때문에 board_comment.js 호출여부체크함
		$this->load->helper('file');
		if($page){//goods detail or mypage
			$view_layer = str_replace("_catalog","_view_layer",FILE_PATH);
			$headerhtmlfile	= ROOTPATH."data/skin/".$view_layer;
		}else{
			$headerhtmlfile	= ROOTPATH."data/skin/".$this->skin."/board/".BORADSKIN."/view.html";
		}
		$headerhtmlfilesource = read_file($headerhtmlfile);
		if( strpos($headerhtmlfilesource,'{#commentskin}') ) {
			// 통합약관
			$policy['policy'] = $policy['policy_comment'];
			$this->template->assign($policy);
			$this->template->assign('commentskinjsuse',true);
		}

		$this->template->assign('multi_copymove',$multi_copymove);

		/**
		 * pagin setting
		**/
		$popup = (!empty($_GET['popup'])) ? $_GET['popup']:'';
		$iframe = (!empty($_GET['iframe'])) ? $_GET['iframe']:'';		
		$paginlay = pagingtagfront($sc['searchcount'], $sc['perpage'], $this->boardurl->resets, getLinkFilter('',array_keys($sc)) );
		
		if (isset($noticeloop)) {
			$this->template->assign('noticeloop',$noticeloop);
		}

		if (isset($loop)) {
			$this->template->assign('loop',$loop);
		}

		if (empty($paginlay)) {
			$paginlay = '<p><a class="on red">1</a></p>';		
		}

		$this->template->assign('pagin',$paginlay);
		$this->template->assign('sc',$sc);
	}

	public function board_lists($ndata, $data, $sc, & $noticeloop, & $loop)
	{
		$this->manager['write_admin_format'] = $this->manager['write_admin'];

		$idx = 0;
		foreach($ndata['result'] as $ndatarow) {$idx++;
			if( $ndatarow['onlynotice'] == 1 && ($ndatarow['onlynotice_sdate'] && $ndatarow['onlynotice_edate']) && !( date('Y-m-d') >=  $ndatarow['onlynotice_sdate'] && date('Y-m-d')  <=  $ndatarow['onlynotice_edate']) ) continue;//공지만노출시 기간체크

			$noticesql['whereis']	= ' and gid= "'.$ndatarow['gid'].'" ';
			if( !(BOARDID == 'goods_qna' || BOARDID == 'goods_review'  || BOARDID == 'bulkorder') ) {
				$noticesql['whereis']	.= ' and boardid= "'.BOARDID.'" ';
			}
				$noticesql['select']		= ' * ';
			$notice = $this->Boardmodel->get_data($noticesql);//게시판목록

			// 입력되어 있는 파일정보가 없을 시 본문의 최상위 동영상 이미지 정보 추출
			$notice = getDesignVideoFileKey($notice);

			if ( ( BOARDID == 'goods_qna' ||  BOARDID == 'goods_review' ) && ($this->pagetype == 'goods' || ($_GET['iframe'] == 1 && $_GET['gdviewer'] == 1)) ) {//상품상세 공지글 노출여부
				if( $notice['goods_seq'] && $notice['goods_seq'] != $_GET['goods_seq'] ) continue;
			}

			if(isset($notice['seq'])) {
				$notice['number'] = (!isset($_GET['seq']) || $_GET['seq'] != $notice['seq']) ? '<img src="'.$this->notice_img.'" title="공지" >' : ' <span class="now">&gt;&gt;</span> ';//공지

				if($notice['hidden'] == 1  && $notice['mseq'] < 1  && BOARDID != 'faq' ) {//비밀글
					$notice['iconhidden'] = ' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ';
				}else{
					$notice['iconhidden'] = '';
				}

				$this->manager = get_admin_name(array(
					'mtype'=>$notice['mtype'],
					'mseq'=>$notice['mseq'],
					'manager'=>$this->manager,
					'write_admin_format'=>$this->manager['write_admin_format']
				));

				getminfo($this->manager, $notice, $minfo, $boardname);//회원정보
				$notice['name'] = $boardname;
				$notice['category'] = (!empty($notice['category']) )? ' <span class="cat">['.$notice['category'].']</span>':'';

				if($this->manager['icon_new_day'] > 0 && date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$notice['r_date']),0,8))) >= date("Ymd") ) {//new
					$notice['iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ';
				}

				if($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $notice['hit'] ) {//조회수
					$notice['iconhot']		= ' <img src="'.$this->icon_hot_img.'" title="hot" align="absmiddle"> ';
				}

				if( getBoardFileck($notice['upload'], $notice['contents']) ) {//첨부파일
					$notice['iconfile']		= ' <img src="'.$this->icon_file_img.'" title="첨부파일" align="absmiddle" > ';
				}
				if( boardisimage($notice['upload'], $notice['contents']) ) {//첨부파일 > image
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

				$notice['subject_real'] 	= $notice['subject'];
				if( $this->manager['viewtype'] != "layer" ) {
					$notice['subject']		= (  $this->mobileMode  || $this->_is_mobile_agent) ? getstrcut(strip_tags($notice['subject']), $this->manager['mobile_subjectcut']):getstrcut(strip_tags($notice['subject']), $this->manager['subjectcut']);
				}
				$notice['subjectcut']		= $notice['subject'];

				if( BOARDID == 'goods_qna' ||  BOARDID == 'goods_review' ) {
					$commentcnt = ($notice['comment']>0 && !(  $this->mobileMode  || $this->_is_mobile_agent) ) ? ' <span class="comment">('.number_format($notice['comment']).')</span>':'';
				}else{
					$commentcnt = ($notice['comment']>0) ? ' <span class="comment">('.number_format($notice['comment']).')</span>':'';
				}
				$notice['commentcnt'] = $commentcnt;

				get_auth($this->manager,$notice, 'read', $isperm);//접근권한체크1
				$this->manager['isperm_read'] = ($isperm['isperm_read'] === true)?'':'_no';
				$this->manager['fileperm_read']= (isset($isperm['fileperm_read']))?$isperm['fileperm_read']:'';
				get_auth_btn($this->manager,$notice);//접근권한체크2
				$notice['isperm_read']		= $this->manager['isperm_read'];
				$notice['fileperm_read']	= $this->manager['fileperm_read'];

				$notice['subject']		= $notice['category'].' <span class="hand highlight-link boad_view_btn'.$this->manager['isperm_read'].'" viewtype="'.$this->manager['viewtype'].'"  pagetype="'.$this->pagetype.'" viewlink="'.$this->boardurl->view.$notice['seq'].'" fileperm_read="'.$this->manager['fileperm_read'].'"  board_seq="'.$notice['seq'].'"  board_id="'.BOARDID.'" ><a>'.$notice['subject'].'</a>&nbsp;&nbsp; </span>'.$commentcnt;

				$notice['date']			= substr($notice['r_date'],0,16);//등록일

			if( BOARDID == 'goods_review' ){
					if($this->manager['goods_review_type'] == 'INT' && $notice['reviewcategory']){
					$notice['scorelay'] = getGoodsScore($notice['score_avg'], $this->manager);
						if(sizeof(explode(",",$notice['reviewcategory']))>1) $notice['score_avg_lay'] = 'score_avg';
				}else{
					$notice['scorelay'] = getGoodsScore($notice['score'], $this->manager);
				}
				$notice['emoneylay']	=  getBoardEmoneybtn($notice, $this->manager, 'view');

						//평가정보노출
					if($notice['adddata']){
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

				//상품후기의 평가점수
				if($this->manager['reviewcategory']){
					unset($reviewcategorylist);
					$reviewcategoryar = @explode(",",$this->manager['reviewcategory']);
					$reviewcategorydataar = @explode(",",$notice['reviewcategory']);
					foreach($reviewcategoryar as $k=>$reviewcategory){
						$reviewcategorylistar['idx'] = $k;
						$reviewcategorylistar['title'] = $reviewcategory;
						$reviewcategorylistar['score'] = $reviewcategorydataar[$k];
						$reviewcategorylist[] = $reviewcategorylistar;
					}
					$notice['reviewcategorylist'] = $reviewcategorylist;
				}

				$notice['buyertitle'] = ($notice['order_seq']||$notice['npay_product_order_id']||$notice['talkbuy_product_order_id'])?getAlert("sy059"):getAlert("sy060");// 구매':'미구매';
			}else{
				//평점이 있는경우 평점정보 추가
				if($notice['score_avg'] ) {//&& ($this->storeMode || $this->storefammerceMode || $this->storemobileMode)
					$img_full = "<img src='/data/skin/".$this->skin."/images/design/star_full.gif' title='".$notice['score_avg']."' />";
					$img_half = "<img src='/data/skin/".$this->skin."/images/design/star_half.gif' title='".$notice['score_avg']."' />";
					$img_empty = "<img src='/data/skin/".$this->skin."/images/design/star_empty.gif' title='".$notice['score_avg']."' />";

					$fullStar = $notice['score_avg'] / 2;
					$halfStar = $notice['score_avg'] % 2;

					$totalStar = 0;
					$emptyStar = 0;
					$printStar = "";

					for ($i = 1; $i <= $fullStar; $i++) { $printStar .= $img_full; $totalStar++; }
					for ($i = 1; $i <= $halfStar; $i++) { $printStar .= $img_half; $totalStar++; }

					$emptyStar = 5 - $totalStar;
					for ($i = 1; $i <= $emptyStar; $i++) { $printStar .= $img_empty; }
					$notice['scorelay'] = $printStar;
				}
			}

				$contents_tmp =  str_replace('&nbsp;', ' ', str_ireplace('</p>',' ',$notice['contents']));
				$contents_tmp = removeUnderbarTempleteTag($contents_tmp);
				$notice['contentcut']		= getstrcut(strip_tags($contents_tmp), $this->manager['contcut']);

				if($notice['hidden'] == 1  && BOARDID != 'faq' && $this->manager['isperm_read']) {
					$notice['contents']		= ' <span class="gray">'.getAlert("sy061").'</span>';	// 비밀글입니다
					$notice['contentcut']		= ' <span class="gray">'.getAlert("sy061").'</span>';	// 비밀글입니다
					$notice['secret_state']	= true;
				} else {
					$notice['secret_state']	= false;
				}

				//리스트노출 게시글평가
				$notice['recommendlay'] =  getRecommendviewer($notice);

				if( BOARDID == 'goods_qna' || BOARDID == 'goods_review'  || BOARDID == 'bulkorder'  ) {
					if(!empty($notice['goods_seq']) && $notice['depth'] == 0 ){
							if( BOARDID == 'bulkorder' ) {
								$notice['goodsInfo']		= getBulkorderGoodsinfo($notice, $notice['goods_seq'], 'write');
								if($notice['goodsInfo'][0]) $notice['goodsInfo'] = $notice['goodsInfo'][0];
								$notice['subject']			= getBulkorderGoodsinfo($notice, $notice['goods_seq'], 'list');
							}else{
								$notice['goodsInfo']		= getGoodsinfo($notice, $notice['goods_seq'], 'write');
								if($notice['goodsInfo'][0]) $notice['goodsInfo'] = $notice['goodsInfo'][0];
								$notice['subject']			= getGoodsinfo($notice, $notice['goods_seq'], 'list');

								// 상품 삭제시 노출이 되어 아래 코드 추가
								if( strstr($this->manager['list_show'],'[contents]') ) {
									$contents_tmp =  str_replace('&nbsp;', ' ',str_ireplace('</p>',' ',$datarow['contents']));
									$contents_tmp = removeUnderbarTempleteTag($contents_tmp);
									$notice['contentcut']		= getstrcut(strip_tags($contents_tmp), $this->manager['contcut']);
								} else {
									$notice['contentcut'] = '';
								}
							}
					}else{
						$notice['subject']		= $notice['category'].' <span class="hand highlight-link boad_view_btn'.$this->manager['isperm_read'].'" viewtype="'.$this->manager['viewtype'].'"  pagetype="'.$this->pagetype.'" viewlink="'.$this->boardurl->view.$notice['seq'].'" fileperm_read="'.$this->manager['fileperm_read'].'"  board_seq="'.$notice['seq'].'"  board_id="'.BOARDID.'" ><a>'.$notice['subject'].'</a>&nbsp;&nbsp; </span>'.$commentcnt;

						// 상품 삭제시 노출이 되어 아래 코드 추가
						if( strstr($this->manager['list_show'],'[contents]') ) {
							$contents_tmp =  str_replace('&nbsp;', ' ',str_ireplace('</p>',' ',$datarow['contents']));
							$contents_tmp = removeUnderbarTempleteTag($contents_tmp);
							$notice['contentcut']		= getstrcut(strip_tags($contents_tmp), $this->manager['contcut']);
						} else {
							$notice['contentcut'] = '';
						}
					}
					}

				$noticeloop[] = $notice;
			}

			unset($notice);
		}//endforeach

		$idx = 0;
		$orgArticleAuthor = array(); // 원본글 작성자 정보(depth: 0)
		foreach($data['result'] as $datarow) {$idx++;

			// 입력되어 있는 파일정보가 없을 시 본문의 최상위 동영상 이미지 정보 추출
			$datarow = getDesignVideoFileKey($datarow);

			($datarow['depth'] == 0 && $datarow['mseq']) && $orgArticleAuthor[(int)$datarow['gid']] = $datarow['mseq'];

			if($datarow['depth'] > 0) {
				$datarow['orgArticleAuthor'] = $orgArticleAuthor[(int)$datarow['gid']];
			}

			$datarow['number'] = ((isset($_GET['seq']) && ($_GET['seq']) != $datarow['seq']) || (!isset($_GET['seq']))) ? $sc['searchcount']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1: ' <span class="now">&gt;&gt;</span> ';//번호

			$datarow['category'] = (!empty($datarow['category']))? ' <span class="cat">['.$datarow['category'].']</span>':'';

			$this->manager = get_admin_name(array(
				'mtype'=>$datarow['mtype'],
				'mseq'=>$datarow['mseq'],
				'manager'=>$this->manager,
				'write_admin_format'=>$this->manager['write_admin_format']
			));

			getminfo($this->manager, $datarow, $mdata, $boardname);//회원정보
			$datarow['name'] = $boardname;

			/**$replysc['whereis']	= ' and gid > '.$datarow['gid'].' and gid < '.(intval($datarow['gid'])+1) . ' ';//답변여부
			//$replysc['select']		= "  count(gid)  ";
			$datarow['replyor']	= $this->Boardindex->get_data_numrow($replysc);**/
			$replysc['whereis'] = ' and gid > '.$datarow['gid'].' and gid < '.(intval($datarow['gid'])+1).' and parent = '.($datarow['seq']).' ';//답변여부
			$datarow['replyor'] = $this->Boardmodel->get_data_numrow($replysc);

			$datarow['re_contents_real'] 	= $datarow['re_contents'];
			$datarow['re_contents']		= (  $this->mobileMode  || $this->_is_mobile_agent) ? getstrcut(strip_tags($datarow['re_contents']), $this->manager['subjectcut']):getstrcut(strip_tags($datarow['re_contents']), $this->manager['subjectcut']);
			$datarow['date']			= substr($datarow['r_date'],0,16);//등록일

			if( $this->mobileMode) {
				if (BOARDID != 'notice') {
					if($datarow['re_contents_real']){
					if($datarow['re_date'] ){
						$datarow['reply_title']		= '<span style="color:#333" >'.getAlert("sy062").'</span>';	//  답변완료
						$datarow['reply_state']	= 'complete';
					}else{
							$datarow['reply_title']		= ($datarow['re_contents_real'])?'<span class="blue" >'.getAlert("sy062").'</span>':'<span class="gray" >'.getAlert("sy063").'</span>';//상태 답변완료 답변대기
							$datarow['reply_state']	= ($datarow['re_contents_real'])?'complete':'standby';
						}
					}else{
						$datarow['reply_title']		= '<span class="gray" >'.getAlert("sy063").'</span>'; // 답변대기
						$datarow['reply_state']	= 'standby';
					}
				} else {
					$data['reply_title']		= '';
					$datarow['reply_state']	= '';
				}
			}else{
				$datarow['reply_title']		= ($datarow['re_contents_real'])?'<span class="blue" >'.getAlert("sy062").'</span>':'<span class="gray" >'.getAlert("sy063").'</span>';//상태 답변완료 답변대기
				$datarow['reply_state']	= ($datarow['re_contents_real'])?'complete':'standby';
			}

			if( BOARDID == 'faq' ) {//faq hidden 노출여부로 이용됨
				$hiddenckeck	= ($datarow['hidden'] == "1") ? ' checked ':'';//비밀글/노출글 '노출':'미노출'
				$datarow['tdclass']	= ($datarow['hidden'] == "1") ? ' bg-silver ':'';
			}else{
				$datarow['tdclass']	= ($datarow['re_contents_real']) ? ' bg-silver ':'';
			}

			$datarow['subject_real'] 	= $datarow['subject'];
			if( BOARDID != 'faq' ) {//faq
				$datarow['subject']		= (  $this->mobileMode  || $this->_is_mobile_agent) ? getstrcut(strip_tags($datarow['subject']), $this->manager['mobile_subjectcut']):getstrcut(strip_tags($datarow['subject']), $this->manager['subjectcut']);
			}
			$datarow['subjectcut']		= $datarow['subject'];

			get_auth($this->manager,$datarow, 'read', $isperm);//접근권한체크
			$this->manager['isperm_read'] = ($isperm['isperm_read'] === true)?'':'_no';
			$this->manager['fileperm_read']= (isset($isperm['fileperm_read']))?$isperm['fileperm_read']:'';

			//if( BOARDID != 'faq' && $datarow['notice'] != '1' ) {//faq hidden 노출여부로 이용됨
			if( BOARDID != 'faq' ) {
				get_auth_btn($this->manager,$datarow);//접근권한체크 isperm_read
			}

			if( BOARDID == 'goods_review' ){
				if($this->manager['goods_review_type'] == 'INT' && $datarow['reviewcategory']) {
					$datarow['scorelay'] = getGoodsScore($datarow['score_avg'], $this->manager);
					if(sizeof(explode(",",$datarow['reviewcategory']))>1) $datarow['score_avg_lay'] = 'score_avg';
				}else{
					$datarow['scorelay'] = getGoodsScore($datarow['score'], $this->manager);
				}
				$datarow['emoneylay']	=  getBoardEmoneybtn($datarow, $this->manager, 'view');

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

				//상품후기의 평가점수
				if($this->manager['reviewcategory']){
					unset($reviewcategorylist);
					$reviewcategoryar = @explode(",",$this->manager['reviewcategory']);
					$reviewcategorydataar = @explode(",",$datarow['reviewcategory']);
					foreach($reviewcategoryar as $k=>$reviewcategory){
						$reviewcategorylistar['idx'] = $k;
						$reviewcategorylistar['title'] = $reviewcategory;
						$reviewcategorylistar['score'] = $reviewcategorydataar[$k];
						$reviewcategorylist[] = $reviewcategorylistar;
					}
					$datarow['reviewcategorylist'] = $reviewcategorylist;
				}

				$datarow['buyertitle'] = ($datarow['order_seq']||$datarow['npay_product_order_id']||$datarow['talkbuy_product_order_id'])?getAlert("sy059"):getAlert("sy060");// '구매':'미구매';
				$datarow['buyerstate'] = ($datarow['order_seq']||$datarow['npay_product_order_id']||$datarow['talkbuy_product_order_id'])?true:false;
			}else{
				//평점이 있는경우 평점정보 추가
				if($datarow['score_avg'] ) {//&& ($this->storeMode || $this->storefammerceMode || $this->storemobileMode)
					$img_full = "<img src='/data/skin/".$this->skin."/images/design/star_full.gif' title='".$datarow['score_avg']."' />";
					$img_half = "<img src='/data/skin/".$this->skin."/images/design/star_half.gif' title='".$datarow['score_avg']."' />";
					$img_empty = "<img src='/data/skin/".$this->skin."/images/design/star_empty.gif' title='".$datarow['score_avg']."' />";

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
				if($datarow['hidden'] == 1  && BOARDID != 'faq' ) {//비밀글
					$datarow['iconhidden'] = ' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ';
				}

				if( BOARDID == 'goods_review' ||  BOARDID == 'goods_qna' ) {
					$datarow['blank']			= ($datarow['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($datarow['depth']-1)*53).'" height="1"><img src="'.$this->re_img.'" title="답변" >':'';//답변
					$commentcnt = ($datarow['comment']>0 && !(  $this->mobileMode  || $this->_is_mobile_agent) ) ? ' <span class="comment">('.number_format($datarow['comment']).')</span>':'';
				}else{
					$datarow['blank']			= ($datarow['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($datarow['depth']-1)*13).'" height="1"><img src="'.$this->re_img.'" title="답변" >':'';//답변
					$commentcnt = ($datarow['comment']>0) ? ' <span class="comment">('.number_format($datarow['comment']).')</span>':'';
				}

				$datarow['commentcnt'] = $commentcnt;

				$datarow['isperm_read']		= $this->manager['isperm_read'];
				$datarow['fileperm_read']	= $this->manager['fileperm_read'];

				if( BOARDID == 'faq' ) {
					$datarow['subject']		= $datarow['blank'].$datarow['category'].' <span class="hand gray boad_faqview_btn " viewtype="'.$this->manager['viewtype'].'"  pagetype="'.$this->pagetype.'"  viewlink="'.$this->boardurl->view.$datarow['seq'].'" fileperm_read="'.$this->manager['fileperm_read'].'"  board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" ><a>'.getAlert('et423').' ['.substr($datarow['d_date'],0,16).']</a></span>'.$commentcnt;
				}else{
					$datarow['subject']		= $datarow['blank'].$datarow['category'].' <span class="hand gray boad_view_btn'.$this->manager['isperm_read'].'" viewtype="'.$this->manager['viewtype'].'"  pagetype="'.$this->pagetype.'"  viewlink="'.$this->boardurl->view.$datarow['seq'].'" fileperm_read="'.$this->manager['fileperm_read'].'"  board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" ><a>'.getAlert('et423').' ['.substr($datarow['d_date'],0,16).']</a></span>'.$commentcnt;
				}
				$datarow['subjectcut']		= "<span class='gray'>".getAlert('et423')."</span>";
				$datarow['subject_real'] 	= $datarow['subjectcut'];

				if($datarow['replyor'] == 0 && $datarow['comment'] == 0) {//삭제후 답변이나  댓글이 없는 경우 삭제가능
					$datarow['deletebtn'] = '<span class="btn small  valign-middle"><input type="button" name="boad_delete_btn" board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" value="삭제" /></span>';
				}

			}else{

				if( $this->manager['icon_new_day'] > 0 && date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$datarow['r_date']),0,8))) >= date("Ymd") ) {//new
					$datarow['iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ';
				}else{
					$datarow['iconnew'] ='';
				}

				$datarow['iconhot']		= ($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $datarow['hit']) ? ' <img src="'.$this->icon_hot_img.'" title="hot" align="absmiddle"> ':'';//조회수
				$datarow['iconfile']		= ( getBoardFileck($datarow['upload'], $datarow['contents'])  ) ?' <img src="'.$this->icon_file_img.'" title="첨부파일" align="absmiddle" > ':'';//첨부파일

				$datarow['iconbest']		= ($datarow['best'] == 'checked')?' <img src="'.$this->icon_best_img.'" title="best" /> ':' <img src="'.$this->icon_best_gray_img.'" title="not best" /> ';
				$datarow['iconaward']		= ($datarow['best'] == 'checked')?' <img src="'.$this->icon_award_img.'" title="베스트" /> ':'';


				if( boardisimage($datarow['upload'],$datarow['contents']) ) {//첨부파일 > image
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

				//동영상
				if( isMobilecheck($datarow['agent']) && $datarow['file_key_i'] ){//모바일이면서 file_key_i 값이 있는 경우
					$datarow['uccdomain_thumbnail']	= uccdomain('thumbnail',$datarow['file_key_i'], $this->manager);
					$datarow['uccdomain_fileswf']			= uccdomain('fileswf',$datarow['file_key_i'], $this->manager);
					$datarow['uccdomain_fileurl']			= uccdomain('fileurl',$datarow['file_key_i'], $this->manager);
				}elseif( uccdomain('thumbnail',$datarow['file_key_w'], $this->manager) && $datarow['file_key_w'] ) {
					$datarow['uccdomain_thumbnail']	= uccdomain('thumbnail',$datarow['file_key_w'], $this->manager);
					$datarow['uccdomain_fileswf']			= uccdomain('fileswf',$datarow['file_key_w'], $this->manager);
					$datarow['uccdomain_fileurl']			= uccdomain('fileurl',$datarow['file_key_w'], $this->manager);
				}
				if( BOARDID == 'goods_review' ||  BOARDID == 'goods_qna' ) {
					$datarow['blank']			= ($datarow['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($datarow['depth']-1)*53).'" height="1"><img src="'.$this->re_img.'" title="답변" >':'';//답변
					$commentcnt = ($datarow['comment']>0 && !(  $this->mobileMode  || $this->_is_mobile_agent) ) ? ' <span class="comment">('.number_format($datarow['comment']).')</span>':'';
				}else{
					$datarow['blank']			= ($datarow['depth']>0) ? ' <img src="'.$this->blank_img.'" title="blank" width="'.(($datarow['depth']-1)*13).'" height="1"><img src="'.$this->re_img.'" title="답변" >':'';//답변
					$commentcnt = ($datarow['comment']>0) ? ' <span class="comment">('.number_format($datarow['comment']).')</span>':'';
				}

				$datarow['commentcnt'] = $commentcnt;


				$datarow['isperm_read']		= $this->manager['isperm_read'];
				$datarow['fileperm_read']	= $this->manager['fileperm_read'];
				$datarow['subject_real'] 	= $datarow['subject'];

				if( BOARDID == 'faq' ) {
					$datarow['subject']		= $datarow['blank'].$datarow['category'].' <span class="hand highlight-link boad_faqview_btn " viewtype="'.$this->manager['viewtype'].'"  pagetype="'.$this->pagetype.'"  viewlink="'.$this->boardurl->view.$datarow['seq'].'" fileperm_read="'.$this->manager['fileperm_read'].'"  board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" ><a>'.$datarow['subject'].'</a> &nbsp;&nbsp; </span>'.$commentcnt;
				}else{
					$datarow['subject']		= $datarow['blank'].$datarow['category'].' <span class="hand highlight-link  boad_view_btn'.$this->manager['isperm_read'].'" viewtype="'.$this->manager['viewtype'].'"  pagetype="'.$this->pagetype.'"  viewlink="'.$this->boardurl->view.$datarow['seq'].'" fileperm_read="'.$this->manager['fileperm_read'].'"  board_seq="'.$datarow['seq'].'"  board_id="'.BOARDID.'" ><a>'.$datarow['subject'].'</a> &nbsp;&nbsp; </span>'.$commentcnt;
				}

				$contents_tmp =  str_replace('&nbsp;', ' ',str_ireplace('</p>',' ',$datarow['contents']));
				$contents_tmp = removeUnderbarTempleteTag($contents_tmp);
				$datarow['contentcut']		= getstrcut(strip_tags($contents_tmp), $this->manager['contcut']);
			}

			if($datarow['hidden'] == 1  && BOARDID != 'faq' && $this->manager['isperm_read']) {
				$datarow['contents']		= ' <span class="gray">'.getAlert("sy061").'</span>';
				$datarow['contentcut']	= ' <span class="gray">'.getAlert("sy061").'</span>';	// 
				$datarow['secret_state']	= true;
			} else {
				$datarow['secret_state']	= false;
			}

			//리스트노출 게시글평가
			$datarow['recommendlay'] =  getRecommendviewer($datarow);

			if( BOARDID == 'faq' ){
				unset($datarow['filelist']);
				getBoardUploadAllfiles($datarow);//전체첨부파일 가져오기(상단에서 이미지만 추출함)
			}

			if( BOARDID == 'goods_qna' || BOARDID == 'goods_review'  || BOARDID == 'bulkorder'  ) {
				if(!empty($datarow['goods_seq']) && $datarow['depth'] == 0 ){
					if( BOARDID == 'bulkorder' ) {
						$datarow['goodsInfo']		= getBulkorderGoodsinfo($datarow, $datarow['goods_seq'], 'write');
						if($datarow['goodsInfo'][0]) $datarow['goodsInfo'] = $datarow['goodsInfo'][0];
						$datarow['subject']			= getBulkorderGoodsinfo($datarow, $datarow['goods_seq'], 'list');
					}else{
						$datarow['goodsInfo']		= getGoodsinfo($datarow, $datarow['goods_seq'], 'write');
						if($datarow['goodsInfo'][0]) $datarow['goodsInfo'] = $datarow['goodsInfo'][0];
						$datarow['subject']			= getGoodsinfo($datarow, $datarow['goods_seq'], 'list');

						// 상품 삭제시 노출이 되어 아래 코드 추가
						if( strstr($this->manager['list_show'],'[contents]') ) {
							$contents_tmp =  str_replace('&nbsp;', ' ',str_ireplace('</p>',' ',$datarow['contents']));
							$contents_tmp = removeUnderbarTempleteTag($contents_tmp);
							$datarow['contentcut']		= getstrcut(strip_tags($contents_tmp), $this->manager['contcut']);
						} else {
							$datarow['contentcut'] = '';
						}
					}
				}else{
					$datarow['subject']	= $datarow['iconmobile'].$datarow['subject'].$datarow['iconimage'].$datarow['iconfile'].$datarow['iconnew'].$datarow['iconhot'].$datarow['iconhidden'];

					if( BOARDID == 'goods_qna' || BOARDID == 'goods_review') {
						if( strstr($this->manager['list_show'],'[contents]') ) {
							$contents_tmp =  str_replace('&nbsp;', ' ',str_ireplace('</p>',' ',$datarow['contents']));
							$contents_tmp = removeUnderbarTempleteTag($contents_tmp);
							$datarow['contentcut']		= getstrcut(strip_tags($contents_tmp), $this->manager['contcut']);
						} else {
							$datarow['contentcut'] = '';
						}
					}
				}
			}

			//게시글평가 skin
			if( $this->manager['auth_recommend_use'] == 'Y' ){
				if( $this->userInfo['member_seq'] ) {
					$scoresql['whereis']	= ' and boardid= "'.BOARDID.'" ';
					$scoresql['whereis']	.= ' and type= "board" ';
					$scoresql['whereis']	.= ' and parent= "'.$datarow['seq'].'" ';//게시글
					$scoresql['whereis']	.= ' and mseq= "'.$this->userInfo['member_seq'].'" ';
					$getscoredata = $this->Boardscorelog->get_data($scoresql);
					if($getscoredata) $datarow['is_recommend'] = '_y';
					if($datarow['display'] == 1) $datarow['is_recommend'] = '_d';
				}
			}

			$loop[] = $datarow;
		}
	}

	//사용자 게시물 등록/수정
	public function write($page=null)
	{

 		if ( !defined('BOARDID') ) {
			if(!empty($_GET['popup']) ) { pageClose('존재하지 않는 게시판입니다.');}else{ pageBack('존재하지 않는 게시판입니다.');}
		}

		if (empty($this->manager['id'])) {
			if(!empty($_GET['popup']) ) { pageClose('존재하지 않는 게시물입니다.');}else{ pageBack('존재하지 않는 게시판입니다.');}
		}
		if( defined('BOARDID') ) $this->template->assign(array('ISBOARDID'=>BOARDID));

		$this->template->assign('writeditorjs',true);//에딧터


		secure_vulnerability('board', 'goods_seq', $_GET['goods_seq']);
		secure_vulnerability('board', 'order_seq', $_GET['order_seq']);
		$_GET['goods_seq']		= (int) $_GET['goods_seq'];
		$_GET['order_seq']		= (int) $_GET['order_seq'];
		if( $page == 'mypage' && $_GET['order_seq'] && $_GET['goods_seq'] && !$_GET['seq']) {//마이페이지 등록된 경우 수정모드
			$goodsreviewsc = $this->db->query("select  seq as board_seq, r_date as board_rdate  from fm_goods_review where mseq = '".$this->userInfo['member_seq']."' and order_seq = '".$_GET['order_seq']."' and goods_seq = '".$_GET['goods_seq']."' ");
			$modify_seq = $goodsreviewsc->row_array();
			if($modify_seq) $_GET['seq'] = $modify_seq['board_seq'];
		}

		// 통합약관 불러오기
		$policy = chkPolicyInfo();

		$displayGoods = '';
		if ( !empty($_GET['seq']) ) {//수정시
			secure_vulnerability('board', 'seq', $_GET['seq']);
			$_GET['seq']		= (int) $_GET['seq'];

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

			if (empty($data['seq'])) {
				if(!empty($_GET['popup']) ) { pageClose('존재하지 않는 게시물입니다.');}else{ pageBack('존재하지 않는 게시물입니다.');}
			}

			if(( $this->mobileMode ) || $this->_is_mobile_agent){// !$data['editor'] ||
				$data['contents'] = strip_tags(str_ireplace('</p>',' ',$data['contents']));
			}

			$idxsc['select']			= ' * ';
			$idxsc['whereis']		= ' and gid = "{$data[gid]}" ';
			$idxdata = $this->Boardindex->get_data($idxsc);//공지글목록
			if( $idxdata ) {
				$data['onlynotice'] = $idxdata['onlynotice'];
				$data['onlynotice_sdate'] = $idxdata['onlynotice_sdate'];
				$data['onlynotice_edate'] = $idxdata['onlynotice_edate'];
			}

			if( BOARDID == 'goods_review' || BOARDID == 'goods_qna'  || BOARDID == 'bulkorder' ) {
				$data['boardid'] = ($data['boardid'])?$data['boardid']:BOARDID;
			}

			if ( $data['re_contents'] && ( BOARDID == 'mbqna' || BOARDID == 'goods_qna' ) ) {//답변상태수정불가
				if(!empty($_GET['popup']) ) { pageClose('답변이 등록된 상태입니다.\n수정하실 수 없습니다.');}else{ pageBack('답변이 등록된 상태입니다.\n수정하실 수 없습니다.');}
			}

			get_auth($this->manager, $data, 'write' , $isperm);//접근권한체크
			if ( $isperm['isperm_write'] === false ) {
				//if(!defined('__ISADMIN__')) {
					if(!empty($_GET['popup']) ) {  pageClose('접근권한이 없습니다!');}else{
						if(!$this->boardurl->perm){
							if($_SERVER['HTTP_REFERER']) {
								pageBack('접근권한이 없습니다!');
								exit;
							}else{
								$url = "/main/index";
								pageRedirect($url,'접근권한이 없습니다!');
								exit;
							}
						}else{
							$board = $this->skin.'/board/_permcheck.html';
							$this->template->assign('boardurl',$this->boardurl);
							$this->template->assign($data);
							$this->print_layout($board);
							exit;
						}
					}
				//}
			}

			get_auth($this->manager, $data, 'reply' , $isperm);//접근권한체크
			if ( $isperm['isperm_reply'] === false && $_GET['reply'] == 'Y' ) {
				//if(!defined('__ISADMIN__')) {
					if(!empty($_GET['popup']) ) {  pageClose('접근권한이 없습니다!');}else{
						if(!$this->boardurl->perm){
								if($_SERVER['HTTP_REFERER']) {
									pageBack('접근권한이 없습니다!');
									exit;
								}else{
									$url = "/main/index";
									pageRedirect($url,'접근권한이 없습니다!');
									exit;
								}
							}else{
							$board = $this->skin.'/board/_permcheck.html';
							$this->template->assign('boardurl',$this->boardurl);
							$this->template->assign($data);
							$this->print_layout($board);
							exit;
						}
					}
				//}
			}

			if($data['tmpcode']){
				$this->session->set_userdata('tmpcode',$data['tmpcode']);
			}else{
				//첨부파일 등록
				$data['tmpcode'] = BOARDID.'^^'.substr(microtime(), 2, 6).'^^'.$data['mseq'];
				$this->session->set_userdata('tmpcode',$data['tmpcode']);
			}

			$isperm_moddel = ( $isperm['isperm_moddel'] === true)?'':'_no';//수정/삭제권한

			if(!empty($data['goods_seq'])){
				if( BOARDID == 'bulkorder' ) {
					$displayGoods = getBulkorderGoodsinfo($data, $data['goods_seq'].','.$_GET['goods_seq'], 'write');
				}else{
					$displayGoods = getGoodsinfo($data, $data['goods_seq'].','.$_GET['goods_seq'], 'write');
				}
			}
			if (!empty($_GET['reply']) == 'Y' && (BOARDID != 'mbqna' && BOARDID != 'bulkorder') ) {//&& BOARDID != 'goods_qna'
				$data['subject']		= $this->Boardmanager->board_restr.$data['subject'];//답변
				$data['contents']		= $this->Boardmanager->board_cont_restr.$data['contents'];
				if( !($this->userInfo['member_seq']) ) {
					$data['name']		= '';
					$data['pw']		= '';
					$data['email']		= '';
					$data['tel1']		= '';
					$data['tel2']		= '';
				}
				$mode = "board_write";
			}else{
				if ( $isperm_moddel == '_no' ) {
					if( (($data['mseq'] > 0 ) && $data['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) || ( !empty($data['mseq']) && !defined('__ISUSER__') ) ) {
						//if(!defined('__ISADMIN__')) {
							if(!empty($_GET['popup']) ){
								pageClose('접근권한이 없습니다!');
							}else{
								if(!$this->boardurl->perm){
									if($_SERVER['HTTP_REFERER']) {
										pageBack('접근권한이 없습니다!');
										exit;
									}else{
										$url = "/main/index";
										pageRedirect($url,'접근권한이 없습니다!');
										exit;
									}
								}else{
									$board = $this->skin.'/board/_permcheck.html';
									$this->template->assign('boardurl',$this->boardurl);
									$this->template->assign($data);
									$this->print_layout($board);
									exit;
								}
							}
						//}
					}else{

						$ss_pwwrite_name = 'board_pwwrite_'.BOARDID;
						$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
						if ( (isset($data['seq']) && !strstr($boardpwwritess,'['.$data['seq'].']') && !empty($boardpwwritess)) || empty($boardpwwritess)) {
							//if(!defined('__ISADMIN__')) {
								if(!empty($_GET['popup']) ){
									pageClose('접근권한이 없습니다!');
								}else{
									if(!$this->boardurl->perm){
										if($_SERVER['HTTP_REFERER']) {
											pageBack('접근권한이 없습니다!');
											exit;
										}else{
											$url = "/main/index";
											pageRedirect($url,'접근권한이 없습니다!');
											exit;
										}
									}else{
										$board = $this->skin.'/board/_permcheck.html';
										$this->template->assign('boardurl',$this->boardurl);
										$this->template->assign($data);
										$this->print_layout($board);
										exit;
									}
								}
							//}
						}
					}
				}

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
					$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']]);
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$bulkorder_sub[] = $user;
				}
				$this->template->assign('bulkorder_sub', $bulkorder_sub);

			}elseif( BOARDID == 'goods_review' ) {
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
					$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']]);
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$goodsreview_sub[] = $user;
				}
				$this->template->assign('goodsreview_sub', $goodsreview_sub);
			}

			$data['subject']			= htmlspecialchars($data['subject']);
			$data['hit']					= number_format($data['hit']);
			$data['datacategory']	= ($data['category'])?$data['category']:$_GET['category'];

			//파일리스트 filelist
			if(!empty($_GET['reply']) != 'Y') {
				if($data['upload']){
					$uploadar = @explode("|",$data['upload']);
					foreach($uploadar as $filenamear){
						$filelistar = @explode("^^",$filenamear);
						@list($realfile, $orignalfile, $sizefile, $typefile) = $filelistar;
						if(preg_match("/[\xA1-\xFE\xA1-\xFE]/",$realfile)) {//데이타이전->한글파일명처리
							$realfilename = iconv('utf-8','cp949',$realfile);

							if(empty($typefile)) {
							$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$realfilename);
							$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
								if(!$filetypetmp['mime']){
									$typefile =end(explode('.', $realfile));//확장자추출
								}else{
									$typefile =$filetypetmp['mime'];
								}
							}else{
								$is_image			= ( preg_match("/image/",$typefile) )?1:0;
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

						if( is_file($this->Boardmodel->upload_path.$realfile) || ($realfilename && is_file($this->Boardmodel->upload_path.$realfilename) )) {
							if(empty($typefile)) {
							$filetypetmp = @getimagesize($this->Boardmodel->upload_path.$realfile);
							$is_image			= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
								if(!$filetypetmp['mime']){
									$typefile =end(explode('.', $realfile));//확장자추출
								}else{
									$typefile =$filetypetmp['mime'];
								}
							}else{
								$is_image			= ( preg_match("/image/",$typefile) )?1:0;
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
									$data['filelist'][] = array('orignfile'=>$orignalfile,'realfilename'=>$realfile,'realfile'=>$this->Boardmodel->upload_src.$realfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>$this->Boardmodel->upload_path.$realfile,'realfileurl'=>$this->Boardmodel->upload_src.$realfile,'realthumbfiledir'=>$this->Boardmodel->upload_path.$thumbimg,'realthumbfileurl'=>$this->Boardmodel->upload_src.$thumbimg);
								}
							} else {
								$data['filelist'][] = array('orignfile'=>$orignalfile,'realfilename'=>$realfile,'realfile'=>$this->Boardmodel->upload_src.$realfile,'realthumbfile'=>$thumbimg,'sizefile'=>$sizefile,'typefile'=>$typefile,'is_image'=>$is_image,'realfiledir'=>$this->Boardmodel->upload_path.$realfile,'realfileurl'=>$this->Boardmodel->upload_src.$realfile,'realthumbfiledir'=>$this->Boardmodel->upload_path.$thumbimg,'realthumbfileurl'=>$this->Boardmodel->upload_src.$thumbimg);
							}
						}
					}
				}
			}

			$data['noticeckeck']		= ($data['notice'] == "1") ? 'checked':'';//공지
			$data['hiddenlay']	= ( $this->manager['secret_use'] == "Y" || $this->manager['secret_use'] == "A" ) ? '':' hide';//비밀글사용여부
			if($this->manager['secret_use'] == "A") {//무조건 비밀글
				$data['hiddenckeck']	= ' disabled="disabled"  checked ';//비밀글/노출글
			}else{
				$data['hiddenckeck']	= ($data['hidden'] == "1" && ($this->manager['secret_use'] == "Y" || BOARDID == "faq") ) ? 'checked':'';//비밀글/노출글
			}

			$this->boardurl->view = $this->boardurl->view.$_GET['seq'];//

			if(BOARDID == 'goods_review' ) {
				$data['scorelay']		= getGoodsScore($data['score'], $this->manager, 'write');
			}

			if( isset($this->userInfo['member_seq']) ) {
				$data['name']	= $this->userInfo['user_name'];
				$data['user_name']	= $this->userInfo['user_name'];
				$this->load->model('membermodel');
				$this->minfo = $this->membermodel->get_member_data($this->userInfo['member_seq']);
				$data['pw']		= $this->minfo['password'];
				$data['email']	= (!empty($data['email'])) ? $data['email'] : $this->minfo['email'];
				$data['tel1']		= (!empty($data['tel1'])) ? $data['tel1'] : $this->minfo['phone'];
				$data['tel2']		= (!empty($data['tel2'])) ? $data['tel2'] : $this->minfo['cellphone'];
			}

			//동영상
			if(  $this->session->userdata('setMode')=='mobile' && $data['file_key_i'] ){//모바일이면서 file_key_i 값이 있는 경우
				$data['uccdomain_thumbnail']		= uccdomain('thumbnail',$data['file_key_i'], $this->manager);
				$data['uccdomain_fileswf']			= uccdomain('fileswf',$data['file_key_i'], $this->manager);
				$data['uccdomain_fileurl']			= uccdomain('fileurl',$data['file_key_i'], $this->manager);
			}elseif( uccdomain('thumbnail',$data['file_key_w'], $this->manager) && $data['file_key_w'] ) {
				$data['uccdomain_thumbnail']		= uccdomain('thumbnail',$data['file_key_w'], $this->manager);
				$data['uccdomain_fileswf']			= uccdomain('fileswf',$data['file_key_w'], $this->manager);
				$data['uccdomain_fileurl']			= uccdomain('fileurl',$data['file_key_w'], $this->manager);
			}


		} else {//등록시
			//모바일-기본내용추출
			if ( ( $this->mobileMode ) ) {
				if($this->manager['content_default_mobile']){
					$this->template->assign('contents',strip_tags(nl2br($this->manager['content_default_mobile'])));
				}
			}else{
				if($this->manager['content_default']) {//기본내용
				$this->template->assign('contents',$this->manager['content_default']);
				}
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

			}elseif( BOARDID == 'goods_review' ){

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

			get_auth($this->manager, '', 'write' , $isperm);//접근권한체크
			if ( $isperm['isperm_write'] === false ) {
				//if(!defined('__ISADMIN__')) {
					if(!empty($_GET['popup']) ){ pageClose('접근권한이 없습니다!');}else{
						if(!$this->boardurl->perm){
							if($_SERVER['HTTP_REFERER']) {
								pageBack('접근권한이 없습니다!');
								exit;
							}else{
								$url = "/main/index";
								pageRedirect($url,'접근권한이 없습니다!');
								exit;
							}
						}else{
							$board = $this->skin.'/board/_permcheck.html';
							$this->template->assign('boardurl',$this->boardurl);
							$this->template->assign($data);
							$this->print_layout($board);
							exit;
						}
					}
				//}
			}
			$mode = "board_write";

			$data['filelist'] = '';//파일리스트 filelist
			$data['noticeckeck'] = '';
			$data['hiddenlay']	= ( $this->manager['secret_use'] == "Y" || $this->manager['secret_use'] == "A" ) ? '':' hide';//비밀글사용여부
			if($this->manager['secret_use'] == "A") {//무조건 비밀글
				$data['hiddenckeck']	= ' disabled="disabled"  checked ';//비밀글/노출글
			}else{
				$data['hiddenckeck'] = '';//비밀글/노출글
			}
			$data['datacategory']	= ($_GET['category'])?$_GET['category']:'';

			if( isset($this->userInfo['member_seq']) ) {

				$this->load->model('membermodel');
				$this->minfo = $this->membermodel->get_member_data($this->userInfo['member_seq']);

				$data['name']	= $this->userInfo['user_name'];
				$data['user_name']	= $this->userInfo['user_name'];
				$data['pw']		= $this->minfo['password'];
				$data['email']	= $this->minfo['email'];
				$data['tel1']		= $this->minfo['phone'];
				$data['tel2']		= $this->minfo['cellphone'];
			}

			if(BOARDID == 'goods_review' ) {
				$data['scorelay']		= getGoodsScore('', $this->manager, 'write');
			}
			if(!empty($_GET['goods_seq'])){
				secure_vulnerability('board', 'goods_seq', $_GET['goods_seq']);
				$_GET['goods_seq'] = (int) $_GET['goods_seq'];
				if( BOARDID == 'bulkorder' ) {
					$displayGoods = getBulkorderGoodsinfo('', $_GET['goods_seq'], 'write');
				}else{
					$displayGoods = getGoodsinfo('', $_GET['goods_seq'], 'write');
				}
			}

			//첨부파일 등록
			$this->session->unset_userdata('tmpcode');
			$tmpcode = BOARDID.'^^'.substr(microtime(), 2, 6).'^^'.$this->userInfo['member_seq'];
			$this->session->set_userdata('tmpcode',$tmpcode);
		}


		if($data['videotmpcode']){
			$this->session->set_userdata('boardvideotmpcode',$data['videotmpcode']);
		}else{
			if($this->manager['video_use'] == 'Y' ){//사용중인경우
				$boardvideotmpcode = substr(microtime(), 2, 8);
				$this->session->set_userdata('boardvideotmpcode',$boardvideotmpcode);
			}
		}

		//동영상관리
		/**
		if($data['file_key_w'] ) {
			$this->load->model('videofiles');
			$videosc['tmpcode']		= $this->session->userdata('boardvideotmpcode');
			$videosc['file_key_w']	= $data['file_key_w'];
			$videosc['upkind']= 'board';
			$videosc['type']= BOARDID;
			$videoboard = $this->videofiles->get_data($videosc);
			if($videoboard) $this->template->assign('videoboard',$videoboard);
		}
		**/

		//분류리스트 categorylist
		if($this->manager['category']){
			$categorylist = @explode(",",$this->manager['category']);
			$data['categorylist'] = $categorylist;
		}


			if ( $_POST && BOARDID == 'goods_review' ){
				$data['subject'] = $_POST['subject'];
				$data['contents'] = $_POST['contents'];
				$data['datacategory'] = $_POST['category'];
				foreach($_POST['reviewcategory'] as $reviewcategory){
					$reviewcategoryar[] = $reviewcategory[0];
				}
				if($reviewcategoryar) $data['reviewcategory'] = @implode(",",$reviewcategoryar);
				$data['hiddenckeck']	= ($_POST['hidden'] == "1") ? 'checked':'';
				$_GET['goods_seq']	= ($_POST['displayGoods']) ? $_POST['displayGoods'][0]:'';

				if(!empty($_GET['goods_seq'])){
					$displayGoods = getGoodsinfo('', $_GET['goods_seq'], 'write');
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
		if( defined('__ISUSER__') != true ) {
			// 통합약관
			$data['privacy'] 	= $policy['privacy'];
			//개인정보 수집-이용
			$data['policy'] 	= $policy['policy_board'];
		}

		// xxs
		if ( !empty($_GET['seq']) ) {//수정시
			$data['contents'] = getcontents($data);
		}

		$backtype = $this->session->userdata('backtype');
		$this->template->assign('backtype',$backtype);
		$this->template->assign($data);
		$this->template->assign('mode',$mode);
		$this->boardurl->lists = $this->boardurl->lists;

		$this->template->assign('displayGoods',$displayGoods);//상품정보

		$this->template->assign('boardurl',$this->boardurl);

		$board = $this->skin.'/board/'.BORADSKIN.'/'.$this->thisfile.'.html';//write
		$this->template->define(array("skin"=>$board));

		//스팸방지
		if($this->manager['autowrite_use'] == 'Y' && $page!='mypage' ) {// || $this->_is_mobile_agent
			$cap = boardcaptcha();

			$securimage = $this->skin.'/board/_securimage_show.html';//view
			$this->template->assign('captcha_image',$cap['image']);
			$this->template->define(array("securimage"=>$securimage));
		}

		//모바일 첨부파일 input file 추가
		$mobile_file = $this->skin.'/board/_mobile_file.html';
		$this->template->define(array("mobile_file"=>$mobile_file));

		//동영상연결(기본 파일찾기)
		$this->template->assign("uccdomain",uccdomain());

		$this->print_layout(FILE_PATH);
	}

	/**
	 * 사용자 > 게시물 보기
	 * @param id : 게시판아이디
	**/
	public function view($page = null){
		$this->load->library('Board/BoardBlockLibrary');

		$this->manager['write_admin_format'] = $this->manager['write_admin'];

		$board_seq = (int) $this->input->get('seq');
		secure_vulnerability('board', 'seq', $board_seq);
		if( defined('BOARDID') ) $this->template->assign(array('ISBOARDID'=>BOARDID));
		$this->pagetype = $page;
		if ( !defined('BOARDID') ) pageBack('존재하지 않는 게시판입니다.');
		if (!isset($board_seq)) pageBack('잘못된 접근입니다.');

		if (!isset($this->manager['id'])) pageBack('존재하지 않는 게시판입니다.');

		$sc['whereis']	= ' and seq= "'.$board_seq.'" ';
		//본래게시글 추출@2017-05-12
		if( !(BOARDID == 'goods_review' || BOARDID == 'goods_qna' || BOARDID == 'bulkorder') ) {
			$sc['whereis']	.= ' and boardid= "'.BOARDID.'" ';
		}
		$sc['select']		= ' * ';
		$data = $this->Boardmodel->get_data($sc);//게시글
		if (!isset($data['seq'])) pageBack('존재하지 않는 게시물입니다.');
		
		// 통합약관 불러오기
		$policy = chkPolicyInfo();

		//메타테그 치환용 정보
		$add_meta_info['board_neme']		= $this->manager['name'];
		$add_meta_info['subject']		= $data['subject'];
		$this->template->assign('add_meta_info',$add_meta_info);

		// 무료보안서버 ssl 다녀온 후 addslashes 처리되어 stripslashes 처리함
		$data['subject'] = stripslashes($data['subject']);

		// 상위게시물 작성자 정보 가져오기
		// 일부 게시판의 경우 상위 게시물이 없음
		if( BOARDID != 'goods_qna' && BOARDID != 'goods_review' ) {
			$parentArticleAuthorInfo = $this->Boardmodel->get_parent_article_author_info($board_seq);
		}
		$data['orgArticleAuthor'] = $parentArticleAuthorInfo['mseq'];

		if( BOARDID == 'goods_review' || BOARDID == 'goods_qna'  || BOARDID == 'bulkorder' ) {
			$data['boardid'] = ($data['boardid'])?$data['boardid']:BOARDID;
		}

		if(  $this->mobileMode ) {
			if($data['re_contents']){
				if( $data['re_date'] ) {
				}else{
					$data['reply_title']	= ($data['re_contents'])?'<span class="blue" >'.getAlert("sy062").'</span>':'<span class="gray" >'.getAlert("sy063").'</span>';//상태  답변완료 답변대기
					$data['reply_state']	= ($data['re_contents'])?true:false;//상태
				}
			}else{
				$data['reply_title']		= '';
				$data['reply_state']		= false;
			}
		}else{
			$data['reply_title']		= ($data['re_contents'])?'<span class="blue" >'.getAlert("sy062").'</span>':'<span class="gray" >'.getAlert("sy063").'</span>';//상태  답변완료 답변대기
			$data['reply_state']		= ($data['re_contents'])?true:false;//상태
		}

		if( BOARDID == 'bulkorder' ) {
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

		}elseif( BOARDID == 'goods_review' ){
			if($data['adddata']) {
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
					if( $user['used'] == 'Y' ) {
					$user['label_view'] = $this->Boardmanager->get_labelitem_type($user,$adddata[$user['bulkorderform_seq']],'view');
					$user['label_ctype'] = $this->Boardmanager->typeNames[$user['label_type']];
					$goodsreview_sub[] = $user;
				}
				}
				$this->template->assign('goodsreview_sub', $goodsreview_sub);
			}
		}

		//비밀글 > 비회원 또는 회원은 본인이 아닌 경우
		if($data['hidden'] == 1 && $data['notice'] == 0) {//공지글은 무조건보기가능
			$currentPageSkin = $this->skin.'/board/'.BORADSKIN.'/'.$this->thisfile.'.html';

			//비밀글이고 답변글일 때 상위글중에서 회원이 쓴글이면 보기권한 부여
			//gid 정수부분이 일치하는 게시글만 추출 :: 2016-01-20 rhm
			$parentsql['whereis'] = ' and (gid < '.$data['gid'].' and gid >= '.substr($data['gid'],0,9).'00) and parent <= '.$data['parent'].' ';
			if( $this->userInfo['member_seq'] ) {
				$parentsql['whereis'] .= ' and mseq = '.$this->userInfo['member_seq'].' ';
			}
			$parentsql['select']		= '  seq, gid, comment, upload, depth, pw, mseq, mid, parent';
			$parentdata = $this->Boardmodel->get_data($parentsql);//게시물정보

			if( $data['mseq'] > 0  || ($data['parent'] && $parentdata['mseq'] > 0 ) ) {//회원이 쓴글인경우
				if( ( ($data['mseq'] != $this->userInfo['member_seq'] && $parentdata['mseq'] != $this->userInfo['member_seq']) && defined('__ISUSER__'))  || ( !defined('__ISUSER__') ) ) {//작성자가 아니거나 비회원인 경우
					if(!defined('__ISADMIN__')) {
						$board = $this->skin.'/board/_pwcheck.html';
						$this->template->assign('boardurl',$this->boardurl);
						
						/* 가비아 보안점검, 메타 데이터에 비밀글이 일부 노출됨에 따른 데이터 초기화 */
						$data['subject'] = null;
						$data['contents'] = null;
						$this->template->assign('add_meta_info', []);

						$this->template->assign('returnurl',urlencode($this->boardurl->view.$data['seq']));//$this->boardurl->pw.

						// TODO(kjw) : 패스워드 체크 페이지를 보여주는경우, $data를 assign 할 필요에 대한 확인(필요없다면, 보안점검 메타데이터를 초기화 코드 불필요)
						$this->template->assign($data);
						
						if( $this->input->get('mode') == 'ajax' ){//new mobile
							$this->template->define(array('tpl'=>$board));//
							$this->template->define(array("skin"=>$currentPageSkin));
							$this->template->print_('tpl');
						} else {
							$this->print_layout($board);
						}
						exit;
					}
				}
			}
			else{
				// 비번입력후 브라우저를 닫기전까지는 접근가능함
				$ss_pwhidden_name = 'board_pwhidden_'.BOARDID;
				$boardpwhiddenss = $this->session->userdata($ss_pwhidden_name);
				if ( ( !strstr($boardpwhiddenss,'['.$board_seq.']') && isset($boardpwhiddenss)) || empty($boardpwhiddenss)) {
					if(!defined('__ISADMIN__') && BOARDID !== 'faq') {
						$board = $this->skin.'/board/_pwcheck.html';
						$this->template->assign('boardurl',$this->boardurl);
						$this->template->assign('returnurl',urlencode($this->boardurl->view.$data['seq']));//$this->boardurl->pw.
						$this->template->assign($data);

						if( $this->input->get('mode') == 'ajax' ){//new mobile
							$this->template->define(array('tpl'=>$board));//
							$this->template->define(array("skin"=>$currentPageSkin));
							$this->template->print_('tpl');
						} else {
							$this->print_layout($board);
						}
						exit;
					}
				}
			}
		}

		get_auth($this->manager, $data, 'read', $isperm);//접근권한체크
		if(!defined('__ISADMIN__') && BOARDID !== 'faq') {
			if($page == 'goods'){//상품상세경고창
				if ( $isperm['isperm_read'] === false ) pageBack('접근권한이 없습니다.');
			}elseif(BOARDID == 'mbqna'){
				//마이페이지 >> 1:1문의인경우 본인만보기가 가능
				if( !($isperm['isperm_moddel'] === true)  && $data['notice'] == 0 ) {
					pageBack('읽기권한이 없습니다.');
					exit;
				}
			}else{
				if ( $isperm['isperm_read'] === false ) {
					$board = $this->skin.'/board/_permcheck.html';
					$this->template->assign('boardurl',$this->boardurl);
					$this->template->assign($data);
					$this->print_layout($board);
					exit;
				}
			}
		}
		$this->manager['isperm_read'] = ($isperm['isperm_read'] === true)?'':'_no';

		get_auth($this->manager, $data, 'write', $isperm);//접근권한체크
		$this->manager['isperm_write']	= ($isperm['isperm_write'] === true)?'':'_no';//등록권한
		$this->manager['isperm_moddel'] = ( $isperm['isperm_moddel'] === true)?'':'_no';//수정/삭제권한

		if( $this->manager['isperm_moddel'] == '_no' )  {

			if( ($data['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) || ( !empty($data['mseq']) && !defined('__ISUSER__') ) ) {
				$this->manager['isperm_moddel'] = '_mbno';//버튼숨김(회원 > 본인만 가능함
			}else{

				$ss_pwwrite_name = 'board_pwwrite_'.BOARDID;
				$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
				if ( strstr($boardpwwritess,'['.$data['seq'].']') && !empty($boardpwwritess)) {
					$this->manager['isperm_moddel'] = '';//비회원 > 접근권한있음
				}
			}
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

		$this->template->assign('commentlay',$this->manager['auth_cmt_use']);
		$this->template->assign('replylay',$this->manager['auth_reply_use']);

		$cfg_goods = config_load('goods');
		if($this->manager['video_use'] == 'Y' ) {
			$video_size = explode("X" , $this->manager['video_size']);
			$this->manager['video_size0'] = $video_size[0];
			$this->manager['video_size1'] = $video_size[1];

			$video_size_mobile = explode("X" , $this->manager['video_size_mobile']);
			$this->manager['video_size_mobile0'] = $video_size_mobile[0];
			$this->manager['video_size_mobile1'] = $video_size_mobile[1];
		}else{
			unset($this->manager['file_key_w'],$this->manager['file_key_i'],$this->manager['video_size']);
			$this->manager['video_use']	= 'N';
		}

		//동영상
		if(  $this->session->userdata('setMode')=='mobile' && $data['file_key_i'] ){//모바일이면서 file_key_i 값이 있는 경우
			$data['uccdomain_thumbnail']		= uccdomain('thumbnail',$data['file_key_i'], $this->manager);
			$data['uccdomain_fileswf']			= uccdomain('fileswf',$data['file_key_i'], $this->manager);
			$data['uccdomain_fileurl']			= uccdomain('fileurl',$data['file_key_i'], $this->manager);
		}elseif( uccdomain('thumbnail',$data['file_key_w'], $this->manager) && $data['file_key_w'] ) {
			$data['uccdomain_thumbnail']		= uccdomain('thumbnail',$data['file_key_w'], $this->manager);
			$data['uccdomain_fileswf']			= uccdomain('fileswf',$data['file_key_w'], $this->manager);
			$data['uccdomain_fileurl']			= uccdomain('fileurl',$data['file_key_w'], $this->manager);
		}

		$this->load->model('managermodel');
		$this->manager = get_admin_name(array(
			'mtype'=>$data['mtype'],
			'mseq'=>$data['mseq'],
			'manager'=>$this->manager,
			'write_admin_format'=>$this->manager['write_admin_format']
		));

		$this->template->assign('managerview',$this->manager);

		// 한번 읽은글은 브라우저를 닫기전까지는 조회수 증가
		$ss_hit_name = 'board_hit_'.BOARDID;
		$boardhitss = $_COOKIE[$ss_hit_name];
		if ( ( !strstr($boardhitss,'['.$board_seq.']') && isset($boardhitss)) || empty($boardhitss)) {
			$boardhitssadd = (isset($boardhitss)) ? $boardhitss.'['.$board_seq.']':'['.$board_seq.']';
			$this->Boardmodel->hit_update($board_seq);
			setcookie($ss_hit_name, $boardhitssadd, 0, '/');
		}

		//파일리스트 filelistif(!empty($_GET['reply']) != 'Y')
		getBoardViewUploadAllfiles($data);

		if($this->manager['icon_new_day'] > 0 && date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$data['r_date']),0,8))) >= date("Ymd") ) {//new
			$data['iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ';
		}

		if($this->manager['icon_new_day'] > 0 &&  date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$data['cmt_date']),0,8))) >= date("Ymd") ) {//new
			$data['cmt_iconnew']	= ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ';//댓글최근등록날짜
		}

		if($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $data['hit'] ) {//조회수
			$data['iconhot']		= ' <img src="'.$this->icon_hot_img.'" title="hot" align="absmiddle"> ';
		}

		if($data['upload'] ) {//첨부파일
			$data['iconfile']		= ' <img src="'.$this->icon_file_img.'" title="첨부파일" align="absmiddle" > ';
		}

		if($data['hidden'] == 1 ) {//비밀글
			$data['iconhidden'] = ' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ';
		}
		if($data['upload']  && boardisimage($data['upload'], $data['contents']) ) {//첨부파일 > image
			$data['iconimage']		= ' <img src="'.$this->icon_image_img.'" title="첨부파일" align="absmiddle" > ';
		}
		if(isMobilecheck($data['agent'])) {//agent > mobile ckeck
			$data['iconmobile']		= ' <img src="'.$this->icon_mobile_img.'" title="모바일" align="absmiddle" > ';
		}

		$data['comment']= number_format($data['comment']);
		if($data['display'] == 1 ) {
			$data['subject'] = getAlert('et423').' ['.$data['r_date'].']';
			$data['contents'] = '<span class="gray" >'.getAlert('et424').'</span>';
		}

		$data['contents']	= getcontents($data);//xss/csrf

		//<,> 처리 2017-05-12 jhs
		//$data['contents']	= str_replace("&amp;lt;","&lt;",$data['contents']);//xss/csrf
		//$data['contents']	= str_replace("&amp;gt;","&gt;",$data['contents']);//xss/csrf
		if(strstr($this->manager['list_show'],'[order_seq]')){
			$data['buyertitle'] = ($data['order_seq'] || $data['npay_product_order_id']||$data['talkbuy_product_order_id'])?getAlert("sy059"):getAlert("sy060");// '구매':'미구매';	
		}
		$data['buyerstate'] = ($data['order_seq']||$data['npay_product_order_id'])?true:false;

		$data['snst'] = $this->snst_img;
		$data['snsf'] = $this->snsf_img;
		$data['snsm'] = $this->snsm_img;
		$data['snsy'] = $this->snsy_img;

		$data['noticeckeck']		= ($data['notice'] == "1") ? 'checked':'';//공지
		$data['hiddenckeck']	= ($data['hidden'] == "1") ? 'checked':'';//비밀글
		$data['subject']			= ($data['subject']);

		if(BOARDID == 'goods_review' ) {
			$data['iconaward']		= ($data['best'] == 'checked')?' <img src="'.$this->icon_award_img.'" title="best" /> ':'';
			if($prenextdata['mseq'] > 0 && $prenextdata['mseq'] != trim($this->userInfo['member_seq']) && defined('__ISUSER__') ) {//본인인 경우에만
				$data['emoneyviewlay']	=  getBoardEmoneybtn($data,$this->manager, 'view');//상품후기의 마일리지여부
			}

			if( $this->manager['goods_review_type'] == 'INT' && $data['reviewcategory'] ) {
				$data['scorelay'] = getGoodsScore($data['score_avg'], $this->manager);
				if(sizeof(explode(",",$data['reviewcategory']))>1) $data['score_avg_lay'] = 'score_avg';
			}else{
				$data['scorelay'] = getGoodsScore($data['score'], $this->manager);
			}
		}

		$replysc['whereis'] = ' and gid > '.$data['gid'].' and gid < '.(intval($data['gid'])+1).' and parent = '.($data['seq']).' ';//답변여부
		$data['replyor'] = $this->Boardmodel->get_data_numrow($replysc);

		getminfo($this->manager, $data, $mdata, $mbname,'','board_view');//회원정보
		$data['name'] = $mbname;


		$this->manager = get_admin_name(array(
			'mtype'=>$data['re_mtype'],
			'mseq'=>$data['re_mseq'],
			'manager'=>$this->manager,
			'write_admin_format'=>$this->manager['write_admin_format']
		));

		getminfo($this->manager, $data, $mdata, $adminname,true);//게시판관리자정보
		$data['adminname'] = $adminname;

		$data['datacategory'] = $data['category'];
		if( isset($this->userInfo['member_seq']) )$data['user_name']	= $this->userInfo['user_name'];

		if(!empty($data['goods_seq'])){
			if( BOARDID == 'bulkorder') {
				if(strstr($this->manager['bulk_show'],'[goods]')){//상품사용시
					$goodsview	= getBulkorderGoodsinfo($data, $data['goods_seq'], 'view');
				}
			}else{
				$goodsview	= getGoodsinfo($data, $data['goods_seq'], 'view');
			}
			if($this->__APP_USE__ == 'f') {//페이스북
			}
			$this->load->model('goodsmodel');
			$images = $this->goodsmodel->get_goods_image($data['goods_seq']);
			if($images){
				foreach($images as $image){
					if($image['view']['image']) {
						$filetypetmp = @getimagesize(ROOTPATH.$image['view']['image']);
						if($filetypetmp[0] > 200){
							$this->template->assign('APP_IMG',	$image['list1']['image']);
							$viewappimg = true;
							break;
						}else{
						$this->template->assign('APP_IMG',	$image['view']['image']);
							$viewappimg = true;
						break;
						}
					}elseif($image['list1']['image']) {
						$this->template->assign('APP_IMG',	$image['list1']['image']);
						$viewappimg = true;
						break;
					}
				}
			}
		}else{
			if( BOARDID == 'bulkorder') {
				if(strstr($this->manager['bulk_show'],'[goods]')){//상품사용시
					$goodsview	= getAlert("sy074"); // '상품정보가 없습니다.';
				}
			}else{
				$goodsview	= getAlert("sy074"); // '상품정보가 없습니다.';
			}
		}

		if( !$viewappimg ) { //페이스북
			if($data['filelistimages']) {
				foreach($data['filelistimages'] as $k=>$image) {
					if($image['realthumbfileurl']){
						$this->template->assign('APP_IMG',	$image['realthumbfileurl']);
						}else{
						$this->template->assign('APP_IMG',	$image['realfile']);
					}
							break;
				}//endforeach
			}else{
				@preg_match_all("/<img[^>]*src=[\"']?([^>\"']+)[\"']?[^>]*>/i",$data['contents'],$list_image);
				foreach($list_image[1] as $allfilenamear){//본문내용에서 이미지추출
					$filenamear = @explode(" ",$allfilenamear);
					$filenamear = $filenamear[0];
					$orignalfile	= $filenamear;
					$filetypetmp = @getimagesize(ROOTPATH.str_replace("../","/",$orignalfile));
					$is_image	= ($filetypetmp && !($filetypetmp[2] == 4 || $filetypetmp[2] == 5) )?1:0;
					$orignalfile	= str_replace("../","/",$filenamear);
					if( $is_image ) {
						$this->template->assign('APP_IMG',$orignalfile);
						break;
					}
				}
			}
		}

		if($page != 'goods'){//상품상세에서는 제외
			$this->template->assign('goodsview',$goodsview);//상품정보
		}

		// 상품상세 페이지 노출인 경우, return_url 세팅
		if($this->input->get('iframe') && (BOARDID == 'goods_review' || BOARDID == 'goods_qna')){
			$return_url= urlencode("/goods/view?no=".$data['goods_seq']."#goods_contents_quick");
			$this->template->assign('goods_seq',$data['goods_seq']);
			$this->template->assign('param',$this->input->get());
		}else{
			$return_url= urlencode($_SERVER['REQUEST_URI']);
		}
		$this->template->assign('return_url',$return_url);

		//스팸방지
		if($this->manager['autowrite_use'] == 'Y' && !defined('__ISUSER__') ) {// || $this->_is_mobile_agent
			$cap = boardcaptcha();

			$securimage = $this->skin.'/board/_securimage_show.html';//view
			$this->template->assign('captcha_image',$cap['image']);
			$this->template->define("securimage",$securimage);
 			$this->template->define("securimage_reply",$securimage);
		}


		if( ( $this->mobileMode  || $this->_is_mobile_agent) && $data['file_key_i'] ){ //모바일화면인경우 처리
			$data['file_key_w'] = $data['file_key_i'];
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

		if(count($data['reviewcategorylist']) < 2 ) unset($data['reviewcategorylist']);//게시글상세 평점 한개이상일때 노출

		$data['hiddenlay']	= ( $this->manager['secret_use'] == "Y" || $this->manager['secret_use'] == "A" ) ? '':' hide';//비밀글사용여부
		if($this->manager['secret_use'] == "A") {//무조건 비밀글
			$data['hiddenckeck']	= ' disabled="disabled"  checked ';//비밀글/노출글
		}else{
			$data['hiddenckeck']	= ($data['hidden'] == "1" && ($this->manager['secret_use'] == "Y" || BOARDID == "faq") ) ? 'checked':'';//비밀글/노출글
		}

		$data['cmthiddenlay']	= ( $this->Boardmanager->cmthidden == "Y") ? '':' hide';//비밀댓글사용여부

		$this->getBoardPreNext($data, 'pre', 'asc', ' > ', 'prelay',$page);//이전글
		$this->getBoardPreNext($data, 'next', 'desc', ' < ', 'nextlay',$page);//다음글

		$cmtpage = $this->input->get('cmtpage');
		if(isset($cmtpage)) {
			$this->boardurl->cmtview = str_replace("&cmtpage=".$cmtpage,"",str_replace("&cmtlist=1","&",$this->boardurl->view).$board_seq);//.'&cmtlist=1&'
		}else{
			$this->boardurl->cmtview = str_replace("&cmtlist=1","&",$this->boardurl->view).$board_seq;//.'&cmtlist=1&'
		}
		$this->template->assign('boardurl',$this->boardurl);

		//게시글평가 skin
		if( $this->manager['auth_recommend_use'] == 'Y' ){
			if( $this->userInfo['member_seq'] ) {
				$scoresql['whereis']	= ' and boardid= "'.BOARDID.'" ';
				$scoresql['whereis']	.= ' and type= "board" ';
				$scoresql['whereis']	.= ' and parent= "'.$data['seq'].'" ';//게시글
				$scoresql['whereis']	.= ' and mseq= "'.$this->userInfo['member_seq'].'" ';
				$getscoredata = $this->Boardscorelog->get_data($scoresql);
				if($getscoredata) $data['is_recommend'] = '_y';
				if($data['display'] == 1) $data['is_recommend'] = '_d';
			}
			$scoreskin = $this->skin.'/board/_score.html';
			$this->template->define(array("scoreskin"=>$scoreskin));
		}
		/**
		 * 신고하기/차단하기 버튼 노출 판단
		 */
		$report_view = isBoardReport($this->manager, $data);
		$this->template->assign('report_view', $report_view);
		// block_view, block_onoff, contents(content)
		$this->boardblocklibrary->assignBoarddata($this->manager, $data);
		$this->template->assign($data);

		$this->getCommentlists($board_seq,$data);//댓글출력

		if($page != 'mypage' && !($this->input->get('mode') == 'ajax' || $this->input->get('mode') == 'layer') )self::index('view');//하단의 리스트출력

		$board = $this->skin.'/board/'.BORADSKIN.'/'.$this->thisfile.'.html';//view

		//@2014-02-27 패치 이전소스때문에 board_comment.js 호출여부체크함
		$this->load->helper('file');
		if($page){
			$headerhtmlfile	= ROOTPATH."data/skin/".FILE_PATH;
		}else{
			$headerhtmlfile	= ROOTPATH."data/skin/".$board;
		}
		$headerhtmlfilesource = read_file($headerhtmlfile);
		if( strpos($headerhtmlfilesource,'{#commentskin}') ) {
			// 통합약관
			$this->template->assign('policy',$policy['policy_comment']);
			$this->template->assign('commentskinjsuse',true);
		}

		//분류리스트 categorylist
		if($this->manager['category']){
			$categorylist = @explode(",",$this->manager['category']);
			$this->template->assign('categorylist', array_map('htmlspecialchars_decode', $categorylist));
		}
		// 모바일 여부
		$this->template->assign('is_mobile_agent', $this->_is_mobile_agent);

		//댓글skin
		$commentskin = $this->skin.'/board/_comment.html';//view
		$this->template->define(array("commentskin"=>$commentskin));

		//이전글/다음글skin
		$prenextskin = $this->skin.'/board/_prenext.html';//view
		$this->template->define(array("prenextskin"=>$prenextskin));
		$this->template->assign("pagemode",$this->input->get('mode'));
		if( $this->input->get('mode') == 'ajax' ){//new mobile
			$this->template->define(array('tpl'=>str_replace("view.html","view_ajax.html",FILE_PATH)));//
			$this->template->define(array("skin"=>$board));
			$this->template->print_('tpl');
		}elseif( $this->input->get('mode') == 'commentview' && strstr(FILE_PATH,'commentview.html') ){//new
				$board = $this->skin.'/board/'.BORADSKIN.'/commentview.html';//view
				$this->template->define(array("skin"=>$board));
				$this->print_layout(str_replace("commentview.html","view.html",FILE_PATH));
		}elseif( $this->input->get('mode') == 'layer' && $this->input->get('iframe') == '1' && $this->manager['viewtype'] == 'layer'  && ( BOARDID == 'goods_qna' || BOARDID == 'goods_review' )  && ($page== 'goods' || $page == 'mypage') ) {
			$this->template->define(array('tpl'=>str_replace("_view.html","_view_layer.html",FILE_PATH)));//
			$this->template->print_('tpl');
		}else{
			$this->template->define(array("skin"=>$board));
			$this->print_layout(FILE_PATH);
		}

	}

	/**
	 * 사용자 > 게시물 보기
	 * @param id : 게시판아이디
	**/
	public function goods_board_view($page=null)
	{
		$boardurl	= (array) $this->boardurl;					// 배열화 ( 객체 = 참조변수 )
		$this->set_construct_init();							// boardurl의 여기서 변경되 버림
		if	($boardurl)	$this->boardurl	= (object) $boardurl;	// 재객체화
		self::view($page);
	}

	/**
	 * 사용자 > 코멘트 보기
	**/
	public function commentview($page=null)
	{
		$boardurl	= (array) $this->boardurl;					// 배열화 ( 객체 = 참조변수 )
		$this->set_construct_init();							// boardurl의 여기서 변경되 버림
		if	($boardurl)	$this->boardurl	= (object) $boardurl;	// 재객체화
		self::view($page);
	}

	//댓글
	public function getCommentlists($parent, $boarddata) {

		$sc['orderby']			= 'seq';
		$sc['sort']					= 'desc';
		$sc['cmtpage']			= (!empty($_GET['cmtpage'])) ?		intval($_GET['cmtpage']):'0';
		$_GET['cmtpage']		= $sc['cmtpage'];//페이징처리를 위해
		if($this->manager['viewtype'] == 'layer' && ($this->pagetype== 'goods' || $this->pagetype == 'mypage') ) {
			$sc['perpage']			= 5;
		}else{
			$sc['perpage']			= 10;
		}

		// ajax 페이징을 위해 :: 2015-07-29 lwh
		$cur_page = 1;
		if($_GET['mode']=='ajax' && $_GET['page']){
			$cur_page = $cur_page + $_GET['page'];
			$sc['perpage'] = $sc['perpage'] * $cur_page;
		}

		$sc['parent']				= (!empty($parent))?$parent:'';

		$boarddata['isperm_view'] = false;
		if ( $boarddata['mseq'] > 0 && $boarddata['mseq'] == $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) {//원작자 읽기권한체크
			$boarddata['isperm_view'] = true;
		}

		$cmtdata = $this->Boardcomment->data_list($sc);//댓글목록
		$boarddata['boardisperm_moddel'] = $this->manager['isperm_moddel'];
		$sc['searchcount']	 = $cmtdata['count'];
		$sc['total_page']	 = ceil($sc['searchcount']	 / $sc['perpage']);
		$sc['totalcount']	 = $this->Boardcomment->get_data_total_count($sc);
		$idx = 0;
		foreach($cmtdata['result'] as $cmtdatarow){$idx++;
			getminfo($this->manager, $cmtdatarow, $mdata, $boardname);//회원정보
			$cmtdatarow['name'] = $boardname;
			$cmtdatarow['mbname'] = $this->userInfo['user_name'];

			get_auth($this->manager, $cmtdatarow, 'write', $isperm);//접근권한체크
			$isperm_write	= (defined('__ISUSER__') === true)?'':'_no';//답글은 회원전용

			$cmtdatarow['isperm_moddel'] = ( $isperm['isperm_moddel'] === true)?'':'_no';
			$cmtdatarow['isperm_hide'] = $cmtdatarow['isperm_moddel'];

			if( (($cmtdatarow['mseq'] > 0 ) && $cmtdatarow['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) || ( !empty($cmtdatarow['mseq']) && !defined('__ISUSER__') ) ) {
				$cmtdatarow['isperm_moddel'] = '_mbno';

				if( ($cmtdatarow['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) || $cmtdatarow['mseq'] < 0 ) {
					$cmtdatarow['isperm_hide'] = 'hide';//버튼숨김(회원 > 본인만 가능함 or 관리자인경우 숨김
				}

			}else{

				$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$cmtdatarow['parent'];
				$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
				if ( strstr($boardpwwritess,'['.$cmtdatarow['seq'].']') && !empty($boardpwwritess)) {
					$cmtdatarow['isperm_moddel'] = '';//비회원 > 접근권한있음
				}
			}

			if( $cmtdatarow['hidden'] == 1) {

				$ss_pwview_name = 'cmthidden_view_'.BOARDID.'_'.$cmtdatarow['parent'];
				$boardpwviews = $this->session->userdata($ss_pwview_name);
				if ( strstr($boardpwviews,'['.$cmtdatarow['seq'].']') && !empty($boardpwviews)) {
					$cmtdatarow['isperm_hidden'] = '';//비회원 > 접근권한있음
				}else{
				if( $cmtdatarow['isperm_moddel'] == '_mbno' ) {
					$cmtdatarow['isperm_moddel'] = '_hidden_mbno';
					$cmtdatarow['isperm_hidden'] = '_hidden_mbno';
				}elseif( $cmtdatarow['isperm_moddel'] == '_no' ) {
					$cmtdatarow['isperm_moddel'] = '_hidden_no';
					$cmtdatarow['isperm_hidden'] = '_hidden_no';
					}elseif( $cmtdatarow['isperm_moddel'] == '_root' ) {
						$cmtdatarow['isperm_moddel'] = '_hidden_root';
						$cmtdatarow['isperm_hidden'] = '_hidden_root';
				}
			}
			}

			$cmtdatarow['isperm_hide'] = ( $cmtdatarow['mseq'] < 0 || $cmtdatarow['display'] == 1 )?'hide':'';//관리가등록글은 수정삭제불가
			$cmtdatarow['isperm_hidden'] = ( $boarddata['isperm_view'] === true )?'':$cmtdatarow['isperm_hidden'];//원작자는 댓글/비밀글보기가능

			//비밀글
			$cmtdatarow['iconhidden'] = ( $cmtdatarow['hidden'] == 1)?' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ':'';

			if( $this->manager['auth_cmt_recommend_use'] == 'Y' ){
				if( $this->userInfo['member_seq'] ) {
					$scoresql['whereis']	= ' and boardid= "'.BOARDID.'" ';
					$scoresql['whereis']	.= ' and type= "comment" ';
					$scoresql['whereis']	.= ' and parent= "'.$cmtdatarow['parent'].'" ';//게시글
					$scoresql['whereis']	.= ' and cparent= "'.$cmtdatarow['seq'].'" ';//게시글
					$scoresql['whereis']	.= ' and mseq= "'.$this->userInfo['member_seq'].'" ';
					$getscoredata = $this->Boardscorelog->get_data($scoresql);
					if($getscoredata) $cmtdatarow['is_cmt_recommend'] = '_y';
					if($cmtdatarow['display'] == 1) $cmtdatarow['is_cmt_recommend'] = '_d';
				}
			}

			$replytitle = ($isperm_write == '_no') ? '로그인 후 이용해 주세요.':'';
			$replytitlereadonly = ($isperm_write == '_no') ? ' readonly="readonly" ':'';
			$cmtdatarow['isperm_write'] = $isperm_write;
			$cmtdatarow['replytitle'] = $replytitle;
			$cmtdatarow['replytitlereadonly'] = $replytitlereadonly;

			if($cmtdatarow['display'] == 1 ){//삭제시
				$cmtdatarow['iconnew']	= '';
				$cmtdatarow['content']		= ' <span class="hand gray  " >'.getAlert('et423').' ['.substr($cmtdatarow['r_date'],0,16).']</span>';

				$cmtdatarow['deletebtn'] = '<span class="btn small valign-middle"><input type="button" name="boad_cmt_delete_btn'.$cmtdatarow['isperm_moddel'].'"   board_cmt_seq="'.$cmtdatarow['seq'].'" value="삭제" /></span>';
			}else{

				//비회원 글에 관리자 비밀댓글인 경우, 비밀댓글 보이도록 수정 2015-10-28 @nsg
				$ss_pwhidden_name = 'board_pwhidden_'.BOARDID;
				$boardpwhiddenss = $this->session->userdata($ss_pwhidden_name);
				if ( ( !strstr($boardpwhiddenss,'['.$_GET['seq'].']') && isset($boardpwhiddenss)) || empty($boardpwhiddenss)) {
					if( !empty($cmtdatarow['isperm_hidden'])  ){//비밀글
					$cmtdatarow['content']		= ' <span class="hand gray boad_cmt_content_'.$cmtdatarow['seq'].' " board_cmt_seq="'.$cmtdatarow['seq'].'"><span class="boad_cmt_content'.$cmtdatarow['isperm_hidden'].'" board_cmt_seq="'.$cmtdatarow['seq'].'" >비밀댓글입니다.</span></span>';
					}
				}

				$cmtdatarow['iconnew']	= ( date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$cmtdatarow['r_date']),0,8))) >= date("Ymd") ) ? ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ' :'';

				$cmtdatarow['date']			= substr($cmtdatarow['r_date'],0,16);//등록일
				$cmtdatarow['org_content']	= $cmtdatarow['content']; // 본래 내용 추가 2014-01-14 lwh
				$cmtdatarow['content']		= ' <span  board_seq="'.$cmtdatarow['seq'].'"  board_id="'.$cmtdatarow['boardid'].'"  >'.$cmtdatarow['content'].'</span>';
			}

			$cmtdatarow['idx'] = $idx;

			// 댓글 신고/차단하기
			$cmtdatarow['report_view'] = isBoardReport($this->manager, $cmtdatarow);
			// block_view, block_onoff, contents(content)
			$this->boardblocklibrary->assignBoarddata($this->manager, $cmtdatarow);

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

				$cmtreply['isperm_moddel'] = ( ($cmtreply['mseq'] == $this->userInfo['member_seq'] && defined('__ISUSER__') === true) )?'':'_no';
				$cmtreply['isperm_hide'] = $cmtreply['isperm_moddel'];

				if( (($cmtreply['mseq'] > 0 ) && $cmtreply['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) || ( !empty($cmtreply['mseq']) && !defined('__ISUSER__') ) ) {
					$cmtreply['isperm_moddel'] = '_mbno';

					if( ($cmtreply['mseq'] != $this->userInfo['member_seq'] && defined('__ISUSER__') === true ) || $cmtreply['mseq'] < 0 ) {
						$cmtreply['isperm_hide'] = 'hide';//버튼숨김(회원 > 본인만 가능함 or 관리자인경우 숨김
					}

				}else{

					$ss_pwwrite_name = 'board_cmtpwhidden_'.BOARDID.'_'.$cmtdatarow['parent'];
					$boardpwwritess = $this->session->userdata($ss_pwwrite_name);
					if ( strstr($boardpwwritess,'['.$cmtreply['seq'].']') && !empty($boardpwwritess)) {
						$cmtreply['isperm_moddel'] = '';//비회원 > 접근권한있음
					}
				}

				if( $cmtreply['hidden'] == 1 ) {//비밀글

					$ss_pwview_name = 'cmthidden_view_'.BOARDID.'_'.$cmtdatarow['parent'];
					$boardpwviews = $this->session->userdata($ss_pwview_name);
					if ( strstr($boardpwviews,'['.$cmtreply['seq'].']') && !empty($boardpwviews)) {
						$cmtreply['isperm_hidden'] = '';//비회원 > 접근권한있음
						if ( $cmtdatarow['isperm_moddel'] ) {
							if( $cmtreply['isperm_moddel'] == '_mbno' ) {
								$cmtreply['isperm_moddel'] = '_hidden_mbno';
							}elseif( $cmtreply['isperm_moddel'] == '_no' ) {
								$cmtreply['isperm_moddel'] = '_hidden_no';
							}
						}
					}else{
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

				$cmtreply['isperm_hide'] = ( $cmtreply['mseq'] < 0 || $cmtreply['display'] == '1'  )?'hide':'';//관리가등록글은 수정삭제불가
				$cmtreply['isperm_hidden'] = ( $boarddata['isperm_view'] === true  )?'':$cmtreply['isperm_hidden'];//원작자는 댓글/비밀글보기가능

				$cmtreply['iconhidden'] = ( $cmtreply['hidden'] == 1)?' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ':'';

				if( $this->manager['auth_cmt_recommend_use'] == 'Y' ){
					if( $this->userInfo['member_seq'] ) {
						$scoresql['whereis']	= ' and boardid= "'.BOARDID.'" ';
						$scoresql['whereis']	.= ' and type= "comment" ';
						$scoresql['whereis']	.= ' and parent= "'.$cmtreply['parent'].'" ';//게시글
						$scoresql['whereis']	.= ' and cparent= "'.$cmtreply['seq'].'" ';//게시글
						$scoresql['whereis']	.= ' and mseq= "'.$this->userInfo['member_seq'].'" ';
						$getscoredata = $this->Boardscorelog->get_data($scoresql);
						if($getscoredata) $cmtreply['is_cmt_recommend'] = '_y';
						if($cmtreply['display'] == '1' ) $cmtreply['is_cmt_recommend'] = '_d';
					}
				}


				$cmtreply['iconnew']	= ( date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$cmtreply['r_date']),0,8))) >= date("Ymd") ) ? ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ' :'';

				$ss_pwhidden_name = 'board_pwhidden_'.BOARDID;
				$boardpwhiddenss = $this->session->userdata($ss_pwhidden_name);
				if ( ( !strstr($boardpwhiddenss,'['.$_GET['seq'].']') && isset($boardpwhiddenss)) || empty($boardpwhiddenss)) {
					if( !empty($cmtreply['isperm_hidden'])  ){//비밀글
					$cmtreply['content']		= ' <span class="hand gray boad_cmt_reply_content_'.$cmtreply['seq'].'" board_cmt_seq="'.$cmtdatarow['seq'].'" board_cmt_reply_seq="'.$cmtreply['seq'].'" ><span class=" boad_cmt_reply_content'.$cmtreply['isperm_hidden'].' " board_cmt_seq="'.$cmtdatarow['seq'].'"    board_cmt_reply_seq="'.$cmtreply['seq'].'" >비밀댓글입니다.</span></span>';
					}
				}

				$cmtreply['date']			= substr($cmtreply['r_date'],0,16);//등록일
				$cmtreply['content']		= ' <span  board_seq="'.$cmtreply['seq'].'"  board_id="'.$cmtreply['boardid'].'" > '.($cmtreply['content']).'</span>';

				$cmtreply['deletebtn'] = '<span class="small valign-middle hand"><img src="'.$this->cmt_reply_img.'" title="답글삭제" class="boad_cmt_delete_reply_btn'.$cmtreply['isperm_moddel'].'" board_cmt_seq="'.$cmtdatarow['seq'].'" board_cmt_reply_seq="'.$cmtreply['seq'].'"  ></span>';

				// 대댓글 신고하기
				$cmtreply['report_view'] = isBoardReport($this->manager, $cmtreply);
				// block_view, block_onoff, contents(content)
				$this->boardblocklibrary->assignBoarddata($this->manager, $cmtreply);
				$cmtreplyloop[] = $cmtreply;
			}
			$cmtdatarow['cmtreplyloop'] = $cmtreplyloop;
			$cmtloop[] =$cmtdatarow;
		}

		if( !empty($_GET['cmtpage']) ) {
			$returnurl = str_replace("&cmtpage=".$_GET['cmtpage'],"",str_replace("&cmtlist=1","",str_replace("&&","&",$this->boardurl->cmtview))).'&cmtlist=1';
		}else{
			$returnurl = $this->boardurl->cmtview.'&cmtlist=1';//.$_GET['seq']
		}
		$returnurl = str_replace("&&","&",str_replace("&&&","&",$returnurl));

		if($this->manager['viewtype'] == 'layer' && ($this->pagetype== 'goods' || $this->pagetype == 'mypage') ) {
			$paginlay =  pagingtagfront($sc['searchcount']	,$sc['perpage'], $returnurl, getLinkFilter('',array_keys($sc)), 'cmtpage', array('onclick',"boardviewtypeshow(this.href,$parent,'".$this->manager['viewtype']."');return false;"));
		}else{
			$paginlay =  pagingtagfront($sc['searchcount']	,$sc['perpage'], $returnurl, getLinkFilter('',array_keys($sc)), 'cmtpage', '');
		}

		if($sc['searchcount'] > 0) {
			$paginlay = (!empty($paginlay)) ? $paginlay:'<p><a class="on" style="color:#ff9102">1</a><p>';
		}

		if($_GET['mode']=='ajax'){
			if($sc['total_page'] > 1 && $sc['searchcount'] > $sc['perpage'])
				$paginlay = '<span class="btn large gray"><input type="button" onclick="more_comment(\''.$_GET['id'].'\',\''.$_GET['seq'].'\');" value="더보기"></span>';
			else $paginlay = '';
		}

		if(isset($cmtloop)) $this->template->assign('cmtloop',$cmtloop);
		$this->template->assign('cmtpagin',$paginlay);
	}

	//이전글, 다음글
	public function getBoardPreNext($data, $type, $order, $whereis, $prenextlayid,$page=null){
		$aGetParams = $this->input->get();
		// 유효성 검사
		if ($aGetParams) {
			$this->load->library('validation');
			$this->validation->set_data($aGetParams);
			$this->validation->set_rules('goods_seq', '상품번호', 'trim|numeric|xss_clean');

			if ($this->validation->exec() === false) {
				show_error($this->validation->error_array['value']);
			}
		}
		
		$bindData = [];
		$whereis = ' and gid ' . $whereis . ' ? ';
		$bindData[] = $data['gid'];

		if ($page == 'mypage' || BOARDID == 'mbqna') {//마이페이지에서 접근시
			$whereis .= ' and mseq = ? ';
			$bindData[] = $this->userInfo['member_seq'];
		}

		if (BOARDID == 'goods_qna' || BOARDID == 'goods_review' || BOARDID == 'bulkorder') {
			if ($aGetParams['goods_seq']) {
				$whereis .= ' and goods_seq= ? ';
				$bindData[] = $aGetParams['goods_seq'];
			}
			$prenextsql['select'] = ' seq, gid, subject, comment, display, m_date, r_date, d_date, hit, upload, hidden  , mseq , name, contents ';
	
		} else {
			$whereis .= ' and boardid = ? ' ;
			$bindData[] = BOARDID;

			$prenextsql['select'] = ' seq, gid, parent, boardid, subject, comment, display, m_date, r_date, d_date, hit, upload, hidden , mseq ,  name, contents ';
		}

		$prenextsql['whereis'] = $whereis;
		$prenextsql['orderby']	= ' gid '.$order.' ';
		$prenextdata = $this->Boardmodel->get_data_prenext($prenextsql, $bindData);

		if (isset($prenextdata['seq'])) {
			$isperm_read = '';
			if(isset($prenextdata['hidden']) && $prenextdata['hidden'] == 1 ){//비밀글
				unset($parentdata);
				if ($prenextdata['parent'] > 0) { // 답변글인경우 체크 추가 leewh 2015-06-08
					$parentsql['whereis']	= ' and seq= "'.$prenextdata['parent'].'" ';
					$parentsql['select']		= '  seq, gid, comment, upload, depth, pw, mseq, mid, parent';
					$parentdata = $this->Boardmodel->get_data($parentsql);//게시물정보
				}
				if ( ($prenextdata['mseq'] > 0 ) || ($prenextdata['parent'] && $parentdata['mseq'] > 0) ) {//작성자가 회원인 경우
					if( ( ($prenextdata['mseq'] != $this->userInfo['member_seq'] && $parentdata['mseq'] != $this->userInfo['member_seq']) && defined('__ISUSER__'))  || ( !defined('__ISUSER__') ) ) {//작성자가 아니거나 비회원인 경우
						$isperm_read = '_mbno';
					}
				}else{//비회원인경우
					// 비번입력후 브라우저를 닫기전까지는 접근가능함
					$ss_pwhidden_name = 'board_pwhidden_'.BOARDID;
					$boardpwhiddenss = $this->session->userdata($ss_pwhidden_name);
					if(!strstr($boardpwhiddenss,'['.$prenextdata['seq'].']') && isset($boardpwhiddenss)) {// ||
						$isperm_read = '_no';
					}
				}
			}
			$prenextdata['subject']			= (  $this->mobileMode  || $this->_is_mobile_agent) ? getstrcut(strip_tags($prenextdata['subject']), $this->manager['mobile_subjectcut']):getstrcut(strip_tags($prenextdata['subject']), $this->manager['subjectcut']);

			if($prenextdata['display'] == 1 ) {
				$prenextdata['subject'] = '<span class="gray" >'.getAlert('et423').' ['.$prenextdata['r_date'].'] </span>';
			}

			$iconnew		= ($this->manager['icon_new_day'] > 0 && date("Ymd",strtotime('+'.$this->manager['icon_new_day'].' day '.substr(str_replace("-","",$prenextdata['r_date']),0,8))) >= date("Ymd") )  ? ' <img src="'.$this->icon_new_img.'" title="new" align="absmiddle" > ':'';
			$iconhot		= ($this->manager['icon_hot_visit'] > 0 && $this->manager['icon_hot_visit'] <= $prenextdata['hit'] ) ? ' <img src="'.$this->icon_hot_img.'" title="hot" align="absmiddle"> ':'';
			$iconfile		= (($prenextdata['upload']) && getBoardFileck($prenextdata['upload'], $prenextdata['contents']) )?' <img src="'.$this->icon_file_img.'" title="첨부파일" align="absmiddle" > ':'';
			$iconhidden = ($prenextdata['hidden'] == 1 ) ?' <img src="'.$this->icon_hidden_img.'" title="비밀글" > ':'';

			$commentcnt = ($prenextdata['comment']>0) ? ' <span class="comment">('.number_format($prenextdata['comment']).')':'';
			$prenextdata['commentcnt']		= $commentcnt;
			$prenextlay['subject'] = '<span class="hand highlight-link boad_view_btn'.$isperm_read.' sbj" viewlink="'.$this->boardurl->view.$prenextdata['seq'].'" viewtype="'.$this->manager['viewtype'].'"  board_seq="'.$prenextdata['seq'].'"  board_id="'.BOARDID.'" ><a >'.$prenextdata['subject'].'</a></span> '.$commentcnt.$iconnew.$iconhot.$iconfile.$iconhidden;
			getminfo($this->manager, $prenextdata, $mdata, $boardname);//회원정보
			$prenextlay['real_name'] = $prenextdata['real_name'];
			$prenextlay['name'] = $boardname;
			$prenextlay['date']	= substr($prenextdata['r_date'],0,16);//등록일
			$prenextlay['m_date']	= substr($prenextdata['m_date'],0,16);
			$prenextlay['d_date']	= substr($prenextdata['d_date'],0,16);
			$this->template->assign($prenextlayid,$prenextlay);
		}
	}


	//비밀번호 찾기
	public function pwcheck()
	{
		$board = $this->skin.'/board/_pwcheck.html';
		$this->template->assign('boardurl',$this->boardurl);
		$this->print_layout($board);
	}


	//잘못된 접근
	public function permcheck()
	{
		$board = $this->skin.'/board/_permcheck.html';
		$this->template->assign('boardurl',$this->boardurl);
		$this->print_layout($board);
	}
	
	/**
	 * 동영상 등록
	 * #1 : 동영상을 입력받아 smartucc에 업로드한다.
	 */
	public function video_upload()
	{
	    $this->load->library('videouploadlibrary');
	    $this->videouploadlibrary->upload();
	}
	
	/**
	 * 동영상 등록
	 * #2 : smartucc에 등록된 동영상을 동영상을 DB에 저장한다.
	 */
	public function video_update()
	{
	    $this->load->library('videouploadlibrary');
	    $this->videouploadlibrary->update();
	}

	//동영상등록새창
	public function popup_video()
	{
	    return $this->video_upload();
	}


	public function zoom()
	{
		$board = $this->skin.'/board/_zoom.html';
		$url		= "../data/board/".$_GET['id']."/".$_GET['url'];
		$width	= $_GET['width'];

		$this->template->assign("url",$url);
		$this->template->assign("width",$width);
		$this->print_layout($board);
	}

	// 180928 cscenter LNB 파일 추가
	public function cscenter_lnb() {
		$this->print_layout($this->template_path());
	}

	/**
	 * 게시글 신고하기 폼 노출
	 */
	public function report() {
		$param = $this->input->post();

		$this->template->assign($param);
		$board = $this->skin.'/board/_report.html';

		$this->template->define('tpl',$board);
		$this->template->print_('tpl');
	}
}
/* End of file board.php */
/* Location: ./app/controllers/board.php */