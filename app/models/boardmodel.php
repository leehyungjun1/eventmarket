<?php
/**
 * 게시글 관련 모듈
 * @author gabia
 * @since version 1.0 - 2012.06.29
 */
class Boardmodel extends CI_Model {

	function __construct() {
		parent::__construct();
		$this->table_data = 'fm_boarddata';
		$this->table_data_fildes = " `seq`, `boardid`, `gid`, `depth`, `parent`, `hidden`, `display`, `notice`, `mseq`, `mid`, `name`, `pw`, `email`, `tel1`, `tel2`, `category`, `subject`, `contents`, `re_subject`, `re_contents`, `re_date`, `upload`, `re_upload`, `hit`, `comment`, `sns`, `r_date`, `m_date`, `d_date`, `cmt_date`, `ip`, `agent`, `rsms`, `remail`, `adddata`, `asort`, `goods_seq`, `order_seq`, `file_key_w`, `file_key_i`, `videotmpcode`, `score_avg`, `editor`, `onlynotice`, `insert_image`, `recommend`, `recommend1`, `recommend2`, `recommend3`, `recommend4`, `recommend5`, `none_rec`, `score`, `onlypopup`, `onlypopup_sdate`, `onlypopup_edate` ";
		if(!empty($_GET['seq'])) {//게시글상세
			$this->seq = $_GET['seq'];
		}
		if ( $this->widgetboardid ) {
			$this->upload_path		= $this->Boardmanager->board_data_dir.$this->widgetboardid.'/';
			$this->upload_src		= $this->Boardmanager->board_data_src.$this->widgetboardid.'/';
		}else{
			$this->upload_path		= $this->Boardmanager->board_data_dir.BOARDID.'/';
			$this->upload_src		= $this->Boardmanager->board_data_src.BOARDID.'/';
		}
	}

	/*
	 * 게시물관리
	 * @param
	*/
	public function data_list($sc, $func = null) {
		$sqlSelectClause = "select ".$this->table_data_fildes;
		$sqlFromClause = " from ".$this->table_data." ";

		if ($sc['boardid']) {
			$sqlWhereClause = " where boardid= '".$sc['boardid']."'";
		} else if (defined('BOARDID')) {
			$sqlWhereClause = " where boardid = '".BOARDID."'";
		} else {
			$sqlWhereClause = " where (1=1) ";
		}

		if (!defined('__ADMIN__') && !defined('__SELLERADMIN__')) {
			$sqlWhereClause .= " and (onlynotice != '1') ";
		}

		// 평점 정보 추가
		if( $sc['score_avg'] ) {
			$sqlWhereClause .= " and score_avg = '".$sc['score_avg']."' ";
		}

		if(!empty($sc['mid'])) $sqlWhereClause.= ' and mid='.$sc['mid'];//회원
		if(!empty($sc['member_seq'])) $sqlWhereClause.= ' and mseq='.$sc['member_seq'];//회원
		if(!empty($sc['mseq'])) $sqlWhereClause.= ' and mseq='.$sc['mseq'];//회원

		// 등록일 검색(시작)
		if($sc['rdate_s'] AND !$sc['rdate_f']) {
			$start_date = $sc['rdate_s'].' 00:00:00';
			$sqlWhereClause.=" AND r_date >= '{$start_date}' ";
		}

		// 등록일 검색(끝)
		if($sc['rdate_f'] AND !$sc['rdate_s']) {
			$start_date = $sc['rdate_f'].' 23:59:59';
			$sqlWhereClause.=" AND r_date <= '{$start_date}' ";
		}

		// 등록일 검색
		if($sc['rdate_s'] AND $sc['rdate_f']) {
			$start_date = $sc['rdate_s'].' 00:00:00';
			$end_date = $sc['rdate_f'].' 23:59:59';
			$sqlWhereClause.=" AND r_date BETWEEN '{$start_date}' AND '{$end_date}' ";
		}

		if( defined('__ADMIN__') != true ){// 프로모션 기간
			//( BOARDID == 'onlinepromotion' || BOARDID == 'offlinepromotion' ) &&
			if($sc['finish'] == 'finish') {//종료된프로모션
				$end_date = date("Y-m-d").' 23:59:59';
				$sqlWhereClause.=" AND d_date < '{$end_date}' ";
			}elseif($sc['finish'] == 'ing') {//종료된프로모션//진행중인프로모션
				$end_date = date("Y-m-d").' 00:00:00';
				$sqlWhereClause.=" AND m_date  <= '{$end_date}' ";
				$sqlWhereClause.=" AND d_date >= '{$end_date}' ";
			}
		}

		if( !empty($sc['isimage']) ) {//첨부파일 : 이미지검색
			$sqlWhereClause .= " and ( upload like '%image/%') ";
		}

		if( !empty($sc['display']) ) {//삭제글
			$display = ($sc['display']-1);
			$sqlWhereClause .= " and display='{$display}' ";
		}


		if(!empty($sc['hidden']) && $sc['hidden'] != 'all' ){
			if( $sc['hidden'] == '2' ) {//비밀글
				$sqlWhereClause .= " and hidden ='1' ";
			}elseif( $sc['hidden'] == '1' ) {//비밀글
				$sqlWhereClause .= " and hidden !='1' ";
			}
		}

		if( !empty($sc['notice']) ) {//공지글(팝업공지글)
			$notice = ($sc['notice']-1);
			$sqlWhereClause .= " and notice='{$notice}' ";
		}

		if( !empty($sc['onlypopup']) ) {//팝업여부
			$sqlWhereClause .= " and ( onlypopup='y' or (onlypopup ='d' and onlypopup_sdate <= '".date("Y-m-d")."' and onlypopup_edate >= '".date("Y-m-d")."' )) ";
		}

		if( !empty($sc['searchreply']) ) {//답변여부
			if( ($sc['searchreply'])=='y' ) {//답변대기중
				$sqlWhereClause .= " and (re_contents = '' or re_contents is null) ";
			}else{
				$sqlWhereClause .= " and re_contents !='' ";
			}
		}

		//동영상
		if( $sc['file_key_w'] ){
			$sql .= " and ( file_key_w != '') ";
		}

		if(!empty($sc['category']))
		{
			$sc['category'] = htmlspecialchars(trim(addslashes(str_replace(' ','',$sc['category']))));
			$sqlWhereClause .= " and ( REPLACE(category,' ','') like '%{$sc['category']}%' ) ";
		}

		$targetColumns = [];
		switch($sc['search_type']) {
			case 'subject':
				$targetColumns = [
					'subject',
				];
			break;
			case 'content':
				$targetColumns = [
					'contents',
				];
			break;
			case 'user_id':
				$targetColumns = [
					'mid',
				];
			break;
			case 'name':
				$targetColumns = [
					'name',
				];
			break;
			default:
				$targetColumns = [
					'subject',
					'name',
					'contents',
					'mid',
				];
		}

		if(!empty($sc['search_text']))
		{
			$sqlWhereClause .= ' AND ('
				.join(' OR ',
					array_map(function($column) use ($sc) {
						return "{$column} LIKE \"%{$sc['search_text']}%\"";
					}, $targetColumns)
				)
				.')';
		}

		$sqlOrderClause =" order by {$sc['orderby']} {$sc['sort']}";
		$sqlOrderClause .=", boardid desc ";

		if( !$sc['onlypopup'] && $sc['perpage'] ) {
			$limit =" limit {$sc['page']}, {$sc['perpage']} ";
		}

		$sql = "
			{$sqlSelectClause}
			{$sqlFromClause}
			{$sqlWhereClause}
			{$sqlOrderClause}
		";

		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();

		if( !$func ){
			//총건수
			$cnt_query = 'select count(*) as cnt '. $sqlFromClause . $sqlWhereClause;
			$cntquery = $this->db->query($cnt_query);
			$cntrow = $cntquery->row_array();
			$data['count'] = $cntrow['cnt'];
		}

		return $data;
	}

	// 미답변 갯수 추출 :: 2014-10-22 lwh
	public function reply_count($sc) {
		$sqlSelectClause	= "select count(*) cnt ";
		if( $sc['boardid'] == 'goods_qna' ){
			$sqlFromClause		= " from fm_".$sc['boardid']." ";
			$sqlWhereClause		= " where 1=1 ";
		}else{
			$sqlFromClause		= " from ".$this->table_data." ";
			$sqlWhereClause		= " where boardid= '".$sc['boardid']."' ";
		}

		if( !empty($sc['searchreply']) ) {//답변여부
			if( ($sc['searchreply'])=='y' ) {//답변대기중
				$sqlWhereClause .= " and (re_contents = '' or re_contents is null) and display != 1 ";
			}else{
				$sqlWhereClause .= " and re_contents != '' ";
			}
		}

		$sql = "
				{$sqlSelectClause}
				{$sqlFromClause}
				{$sqlWhereClause}
			";

		$query		= $this->db->query($sql);
		$cnt_data	= $query->result_array();

		return $cnt_data[0]['cnt'];
	}


	// 게시물총건수
	public function get_item_total_count($sc)
	{
		$cnt_query = 'select count(*) as cnt from '.$this->table_data;

		if ( !empty($sc['boardid']) ) {
			$cnt_query .= " where boardid = '".$sc['boardid']."' ";
		}elseif ( defined('BOARDID') ) {
			$cnt_query .= " where boardid = '".BOARDID."' ";
		}else{
			$cnt_query .= " where 1 ";
		}

		// 입점사 문의 게시판일 때 해당 입점사 글만 카운팅
		if( defined('__SELLERADMIN__') == true && BOARDID == 'gs_seller_qna' ){
			$cnt_query.= ' and mseq='.$this->providerInfo['provider_seq'];//입점사
		}else{
			if( $this->pagetype == 'mypage' || BOARDID == 'mbqna') {
				if(!empty($sc['member_seq'])) $cnt_query.= ' and mseq='.$sc['member_seq'];//회원
				if(!empty($sc['mseq'])) $cnt_query.= ' and mseq='.$sc['mseq'];//회원
			}
		}

		//프론트 공지만노출여부
		if( defined('__ADMIN__') != true ) {
			$cnt_query .= " and (onlynotice != '1') ";
			if( BOARDID == 'faq')$cnt_query .= " and (hidden = '1') ";
		}

		$cntquery = $this->db->query($cnt_query);
		$cntrow = $cntquery->row_array();
		return $cntrow['cnt'];
	}


	/*
	 * 게시물정보
	 * @param
	*/
	public function get_data($sc, $bindData = []) {
		$sc['select'] = ($sc['select'])?$sc['select']:$this->table_data_fildes;

		$sql = "select ".$sc['select']." from  ".$this->table_data."  where 1 ". $sc['whereis'];
		$sql .=" order by gid asc";

		if( $sc['page'] && $sc['perpage'] ) {
			$sql .=" limit ?, ? ";	
			$bindData[] = $sc['page'];
			$bindData[] = $sc['perpage'];
		}
		$query = $this->db->query($sql, $bindData);
		$data = $query->row_array();
		return $data;
	}

	/*
	 * 게시물정보
	 * @param
	*/
	public function get_data_prenext($sc, $bindData = []) {
		$sc['select'] = ($sc['select'])?$sc['select']:$this->table_data_fildes;

		$sql = "select ".$sc['select']." from  ".$this->table_data."  where 1 ". $sc['whereis'];
		$sql .="order by ".$sc['orderby']."  limit 0,1 ";

		$query = $this->db->query($sql, $bindData);
		$data = $query->row_array();
		return $data;
	}

	/*
	 * 게시물정보
	 * @param
	*/
	public function get_data_numrow($sc) {
		$sc['select'] = ($sc['select'])?$sc['select']:$this->table_data_fildes;

		$sql = "select ".$sc['select']." from  ".$this->table_data."  where 1 ". $sc['whereis'];
		$sql .=" order by gid ";
		$query = $this->db->query($sql);
		return $query->num_rows();
	}

	/*
	 * 게시물생성
	 * @param
	*/
	public function data_write($params) {
		$data = filter_keys($params, $this->db->list_fields($this->table_data));
		$result = $this->db->insert($this->table_data, $data);
		return $this->db->insert_id();
	}

	/*
	 * 게시물정보
	 * @param
	*/
	public function get_data_optimize() {
		$sql = "optimize table  ".$this->table_data;
		$this->db->query($sql);
	}

	// 조회수 증가
	function hit_update($seq) {
		if(empty($seq))return false;
		$this->db->set('hit', 'hit + 1', FALSE);
		$this->db->update($this->table_data, null, array('seq' => $seq,'boardid' => BOARDID));
	}
	
	/**
	 * 댓글수를 최신 상태로 갱신
	 * @param int $seq
	 * @return $upboarddata : 댓글수, 댓글갱신일자
	 * @author rsh 2019-03-04
	 */
	public function comment_update($seq) {
	    if(empty($seq))return false;
	    $this->load->model("boardcomment");
	    $sc = array(
	        'select'             =>      '1',
	        'whereis'           =>      "and parent = '{$seq}'",
	    );
	    if(defined('BOARDID')) {
	        $sc['whereis'] .= " AND boardid = '" . BOARDID . "'";
	    }
	    $commentCnt = (int) ($this->boardcomment->get_data_numrow($sc));
	    $upboarddata = array(
	        "comment"      =>      $commentCnt,
	    );
	    $upboarddata['cmt_date'] = date("Y-m-d H:i:s");
	    $this->db->set($upboarddata);
	    $this->db->update($this->table_data, null, array('seq' => $seq,'boardid' => BOARDID));
	    return $upboarddata;
	}

	// 추천/비추천/추천5가지 증가
	function board_score_update($seq, $scoreid, $plus = ' + ') {
		if(empty($seq))return false;
		$this->db->set($scoreid, 'IFNULL('.$scoreid.', 0) '.$plus.' 1', FALSE);
		$result = $this->db->update($this->table_data, null, array('seq' => $seq,'boardid' => BOARDID));
		return $result;
	}

	// tmpcode 저장
	function tmpcode_update($seq,$tmpcode) {
		if(empty($seq))return false;
		$this->db->set('tmpcode', $tmpcode, FALSE);
		$this->db->update($this->table_data, null, array('seq' => $seq,'boardid' => BOARDID));
	}

	/*
	 * 게시물 수정
	 * @param
	*/
	public function data_modify($params) {
		if(empty($_POST['seq']))return false;
		$data = filter_keys($params, $this->db->list_fields($this->table_data));
		$result = $this->db->update($this->table_data, $data,array('seq'=>$_POST['seq']));
		return $result;
	}


	/*
	 * 게시물 개별수정
	 * @param
	*/
	public function data_item_save($params, $seq) {
		if(empty($seq))return false;
		$data = filter_keys($params, $this->db->list_fields($this->table_data));
		$result = $this->db->update($this->table_data, $data,array('seq'=>$seq));
		return $result;
	}


	/*
	 * 게시물 gidupdate
	 * @param
	*/
	public function data_gid_save($gidup) {
		$sql = "update ".$this->table_data." set ".$gidup['set']." where ".$gidup['whereis'];
		/**if ( defined('BOARDID') ) {
			$sql .= " and boardid = '".BOARDID."' ";
		}elseif ( !empty($gidup['boardid']) ) {
			$sql .= " and boardid = '".$gidup['boardid']."' ";
		}**/
		$result = $this->db->query($sql);
		return $result;
	}

	/*
	 * 게시물삭제
	 * @param
	*/
	public function data_delete_modify($params,$seq) {
		if(empty($seq))return false;
		$data = filter_keys($params, $this->db->list_fields($this->table_data));
		$result = $this->db->update($this->table_data, $data,array('seq'=>$seq));
		return $result;
	}

	/*
	 * 게시물개별삭제
	 * @param
	*/
	public function data_delete($seq) {
		if(empty($seq))return false;
		$result = $this->db->delete($this->table_data, array('seq' => $seq,'boardid' => BOARDID));
		return $result;
	}

	/*
	 * 게시물전체삭제
	 * @param
	*/
	public function data_delete_id($boardid) {
		$result = $this->db->delete($this->table_data, array('boardid' => $boardid));
		return $result;
	}

	/*
	 * 게시물이동
	 * @param
	*/
	public function data_move($params, $seq) {
		if(empty($seq))return false;
		$data = filter_keys($params, $this->db->list_fields($this->table_data));
		$result = $this->db->update($this->table_data, $data,array('seq'=>$seq));
		return $result;
	}

	/*
	 * 게시물복사
	 * @param
	*/
	public function data_copy($params) {
		$data = filter_keys($params, $this->db->list_fields($this->table_data));
		$result = $this->db->insert($this->table_data, $data);
		return $this->db->insert_id();
	}

	public function get_issue_count($start_date){
		// 입점사문의,1:1문의 수
		$union_query[] = "
		select count(*) as cnt, 'mbqna' as 'type'
		from fm_boarddata
		where (boardid='mbqna' or boardid='gs_seller_qna') and (re_contents = '' or re_contents is null)
		and display !=1 and r_date>=?
		";

		// 상품문의수
		$union_query[] = "
		select count(*) as cnt, 'gdqna' as 'type' from fm_goods_qna where (re_contents = '' or re_contents is null)
		and display !=1 and r_date>=?
		";

		$sql = "
		SELECT *
		FROM (
			".implode(' union ',$union_query)."
		) as a
		";
		return $this->db->query($sql,array($start_date,$start_date));
	}

	public function get_issue_count_provider($start_date,$provider_seq){
		$sql = "
		SELECT sum(scnt) as cnt, 'mbqna' as 'type'
		FROM (
			select count(*) as scnt from fm_goods_qna where provider_seq = ?  and (re_contents = '' or re_contents is null)
			and r_date>=?
		) as a
		";
		return $this->db->query($sql,array($provider_seq,$start_date));
	}

	/*
	 * 상위 게시물 작성자 정보 가져오기
	 * @param
	*/
	public function get_parent_article_author_info($seq='') {
		$sql = "select mseq, mtype, name from ".$this->table_data." where gid in(select concat(substring_index(gid, '.', 1), '.00') from ".$this->table_data." where seq='".$seq."') limit 1";
		$query = $this->db->query($sql);
		if($query) $data = $query->row_array();
		return $data;
	}

	
}
/* End of file board.php */
/* Location: ./app/models/board */