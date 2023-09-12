<?php
class Goodsreview extends CI_Model {
	function __construct() {
		parent::__construct();
		$this->table_data = 'fm_goods_review';
		$this->goods = 'fm_goods';
		$this->provider = 'fm_provider';
		$this->table_data_fildes = " `seq`, `gid`, `goods_seq`, `depth`, `parent`, `hidden`, `display`, `notice`, `mseq`, `mid`, `name`, `pw`, `email`, `tel1`, `tel2`, `category`, `subject`, `contents`, `upload`, `score`, `emoney`, `hit`, `comment`, `sns`, `r_date`, `m_date`, `d_date`, `cmt_date`, `ip`, `agent`, `order_seq`, `best`, `adddata`, `sizetype`, `colortype`, `file_key_w`, `file_key_i`, `reviewcategory`, `remail`, `rsms`, `editor`, `onlynotice`, `insert_image`, `recommend`, `recommend1`, `recommend2`, `recommend3`, `recommend4`, `recommend5`, `none_rec`, `provider_seq`, `score_avg`, `videotmpcode`,`npay_reviewid`,`npay_reviewtype`,`npay_product_order_id`,`talkbuy_review_id`,`talkbuy_product_order_id`  ";
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

		$this->colortype = array('무응답','보다 어둡다','와 같다','보다 밝다');
		$this->sizetype = array('무응답','보다 작다 &nbsp;&nbsp;','와 같다','보다 크다');

		if( $this->db->es_use === true ){
			$this->load->library('elasticsearch');	

			$this->platform = 'P';
			if($this->fammerceMode || $this->storefammerceMode){ 
				$this->platform	= 'F';
			} elseif ($this->_is_mobile_agent || $this->mobileMode || $this->storemobileMode){
				$this->platform	= 'M';
			}
		}
	}

	/*
	 * 게시물관리
	 * @param
	*/
	public function data_list($sc, $func = null) {
		$sqlSelectClause = "select ".$this->table_data_fildes;
		$sqlFromClause = " from ".$this->table_data." ";

		if (defined('__ADMIN__') || defined('__SELLERADMIN__')) {
			$sqlWhereClause = "where 1=1 ";
		} else {
			$sqlWhereClause = "where onlynotice != '1' ";
		}

		if(!empty($sc['mid'])) $sqlWhereClause.= " and mid='".$sc['mid']."'";//회원
		if(!empty($sc['goods_seq'])) $sqlWhereClause.= " and goods_seq='".$sc['goods_seq']."'";//상품 goods_seq like "%,'.$sc['goods_seq'].'" or goods_seq like "'.$sc['goods_seq'].',%" or goods_seq like "%,'.$sc['goods_seq'].',%" or
		if(!empty($sc['member_seq'])) $sqlWhereClause.= " and mseq='".$sc['member_seq']."'";//회원
		if(!empty($sc['mseq'])) $sqlWhereClause.= " and mseq='".$sc['mseq']."'";//회원

		if( defined('__SELLERADMIN__') === true ) {
			$sqlWhereClause.= ' and provider_seq='.$this->providerInfo['provider_seq'];//입점사
		}

		// 등록일 검색(시작)
		if($sc['rdate_s'] AND !$sc['rdate_f']) {
			$start_date = $sc['rdate_s'].' 00:00:00';
			$sqlWhereClause.=" AND m_date >= '{$start_date}' ";
		}

		// 등록일 검색(끝)
		if($sc['rdate_f'] AND !$sc['rdate_s']) {
			$start_date = $sc['rdate_f'].' 23:59:59';
			$sqlWhereClause.=" AND m_date <= '{$start_date}' ";
		}

		// 등록일 검색
		if($sc['rdate_s'] AND $sc['rdate_f']) {
			$start_date = $sc['rdate_s'].' 00:00:00';
			$end_date = $sc['rdate_f'].' 23:59:59';
			$sqlWhereClause.=" AND m_date BETWEEN '{$start_date}' AND '{$end_date}' ";
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

		if( !empty($sc['order_seq']) ) {//주문번호검색
			$sqlWhereClause .= " and order_seq like '%{$sc['order_seq']}%' ";
		}

		if( !empty($sc['isorder_seq']) && empty($sc['noorder_seq']) || !empty($sc['ordered_review']) && $sc['ordered_review'] === 'y') {//구매자
			$sqlWhereClause .= " and ( order_seq != 0 or npay_product_order_id != 0 or talkbuy_product_order_id != 0 ) ";
		}elseif( empty($sc['isorder_seq']) && !empty($sc['noorder_seq']) || !empty($sc['ordered_review']) && $sc['ordered_review'] === 'n') {//비구매
			$sqlWhereClause .= " and ( order_seq = 0 or order_seq is null ) and ( npay_product_order_id = 0 or npay_product_order_id is null)";
		}

		if( !empty($sc['mborder_seq']) && empty($sc['nomborder_seq']) || !empty($sc['member_review']) && $sc['member_review'] === 'y') {//회원
			$sqlWhereClause .= " and ( mseq != 0) ";
		}elseif( !empty($sc['nomborder_seq']) && empty($sc['mborder_seq']) || !empty($sc['member_review']) && $sc['member_review'] === 'n') {//비회원
			$sqlWhereClause .= " and ( mseq = 0 or mseq is null ) ";
		}

		if( !empty($sc['isseq']) && is_array($sc['isseq']) ) {//게시글수동선택
			$sqlWhereClause .= " and seq in ('".implode("','",$sc['isseq'])."') ";
		}

		if( !empty($sc['best']) && $sc['best'] == 'checked' || $sc['review_type'] === 'best') {//best 검색
			$sqlWhereClause .= " and best='checked' ";
		}

		if( !empty($sc['npay']) && $sc['npay'] == 'checked' || $sc['review_type'] === 'npay') {//npay 상품후기 검색
			$sqlWhereClause .= " and ifnull(npay_reviewid,'')!='' ";
		}

		if( !empty($sc['talkbuy']) && $sc['talkbuy'] == 'checked' || $sc['review_type'] === 'talkbuy') {//톡체크아웃 상품후기 검색
			$sqlWhereClause .= " and ifnull(talkbuy_review_id,'')!='' ";
		}

		if( !empty($sc['score']) ) {//평점검색
			if($this->isplusfreenot && $this->boardform_arr){
				$scoreavgstart = ($sc['score']==1)?0:($sc['score']*10)+1;
				$scoreavgend = ($sc['score']*20);
				$sqlWhereClause.=" and (";
				$sqlWhereClause.=" (score_avg BETWEEN '{$scoreavgstart}' AND '{$scoreavgend}'  AND reviewcategory is not null) ";
				$sqlWhereClause.= "or ";
				$sqlWhereClause .= " (score='{$sc['score']}' and reviewcategory is null )";
				$sqlWhereClause.= ") ";
			}else{//무료인경우
				$sqlWhereClause .= " and score='{$sc['score']}' ";
			}
		}

		if(!empty($sc['category']))
		{
			$sc['category'] = trim(addslashes(str_replace(' ','',$sc['category'])));
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
			case 'goods_name':
			case 'goods_summary':
			case 'goods_content':
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
			$sqlWhereClause .= ' and ( '
				.(
					join(' OR ', array_filter([
						join(' OR ',
							array_map(function($column) use ($sc) {
								return "{$column} LIKE \"%{$sc['search_text']}%\"";
							}, $targetColumns)
						),
						$this->getGoodsSearch($sc)
					], function($x){return !!$x;}))
					?:'false'
				)
				.' ) ';
		}elseif(!empty($sc['catalog_code'])) {
			$goodsSearch = $this->getGoodsSearch($sc);//상품명검색
			if(!$goodsSearch) {
				return;
			}
			$sqlWhereClause .= ' and ('.$goodsSearch.' )';
		}elseif(!empty($sc['brand_code'])) {
			$goodsSearch = $this->getGoodsSearch($sc);//상품명검색
			if(!$goodsSearch) {
				return;
			}
			$sqlWhereClause .= ' and ('.$goodsSearch.' )';
		}

		$sqlOrderClause =" order by {$sc['orderby']} {$sc['sort']}";
		$limit =" limit {$sc['page']}, {$sc['perpage']} ";

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
			$cntrow = $cntquery->result_array();
			$data['count'] = $cntrow[0]['cnt'];
		}

		return $data;
	}

	//상품명검색하기
	public function getGoodsSearch($sc) {
		$targetColumns = [];
		switch($sc['search_type']) {
			case 'subject':
			case 'content':
			case 'user_id':
			case 'name':
			break;
			case 'goods_name':
				$targetColumns = [
					'goods_name',
				];
			break;
			case 'goods_summary':
				$targetColumns = [
					'summary',
				];
			break;
			case 'goods_content':
				$targetColumns = [
					'contents',
				];
			break;
			default:
				$targetColumns = [
					'goods_name',
					'summary',
					'contents',
				];
		}

		if(!empty($sc['search_text']) && count($targetColumns))
		{
			$whereis = ' ('
				.join(' OR ',
					array_map(function($column) use ($sc) {
						return "{$column} LIKE \"%{$sc['search_text']}%\"";
					}, $targetColumns)
				)
			.') ';
			$sql = "select goods_seq  from ".$this->goods." where ".$whereis;
			$query = $this->db->query($sql);
			foreach($query->result_array() as $data){
				$arrNo[] = $data['goods_seq'];
			}
			$wheregoods = (isset($arrNo)) ? 'goods_seq in ('.implode(',',$arrNo).')' : '';
			return $wheregoods;
		}elseif(!empty($sc['catalog_code'])) {//상품카테고리

			$sqlFromClause .= "
				left join fm_goods_image i on (g.goods_seq=i.goods_seq and i.cut_number=1 and i.image_type='list2')
				left join fm_goods_option o on (g.goods_seq=o.goods_seq and o.default_option='y')
				left join fm_category_link l on l.goods_seq=g.goods_seq
				left join fm_category_group as cg on l.category_code = cg.category_code
			";
			$sqlGroupbyClause = " group by g.goods_seq";
			$whereis = " and l.category_code like '".$sc['catalog_code']."%' ";
			$sql = "select g.goods_seq  from ".$this->goods." g {$sqlFromClause} where g.goods_view='look' and g.provider_status = '1' ".$whereis.$sqlGroupbyClause;
			$query = $this->db->query($sql);
			foreach($query->result_array() as $data){
				$arrNo[] = $data['goods_seq'];
			}
			if(!$arrNo){
				return;
			}else{
				$wheregoods = "goods_seq in ('".implode("','",$arrNo)."')";
			}
			return $wheregoods;
		}elseif(!empty($sc['brand_code'])) {//상품브랜드
			$sqlFromClause .= "
				left join fm_goods_image i on (g.goods_seq=i.goods_seq and i.cut_number=1 and i.image_type='list2')
				left join fm_goods_option o on (g.goods_seq=o.goods_seq and o.default_option='y')
				left join fm_brand_link bl on bl.goods_seq=g.goods_seq
				left join fm_brand_group as bg on bl.category_code = bg.category_code
			";
			$sqlGroupbyClause = " group by g.goods_seq";
			$whereis .= " bl.category_code like '".$sc['brand_code']."%'";
			$sql = "select g.goods_seq  from ".$this->goods." g {$sqlFromClause} where ".$whereis.$sqlGroupbyClause;
			$query = $this->db->query($sql);
			foreach($query->result_array() as $data){
				$arrNo[] = $data['goods_seq'];
			}
			if(!$arrNo){
				return;
			}else{
				$wheregoods = "goods_seq in ('".implode("','",$arrNo)."')";
			}
			return $wheregoods;
		}else{
			return '';
		}
	}


	//입점사아이디/입점사명 검색하기
	public function getProviderSearch($sc) {
		if(!empty($sc['search_text']))
		{
			$whereis = ' ( provider_id like "%'.$sc['search_text'].'%" or provider_name like "%'.$sc['search_text'].'%" ) ';//
			$sql = "select provider_seq  from ".$this->provider." where ".$whereis;
			$query = $this->db->query($sql);
			foreach($query->result_array() as $data){
				$arrNo[] = $data['provider_seq'];
			}
			$whereprovider = (isset($arrNo)) ? " or provider_seq in ('".implode("','",$arrNo)."')" : '';
			return $whereprovider;
		}else{
			return '';
		}
	}


	// 게시물총건수
	public function get_item_total_count($sc)
	{
		$cnt_query = 'select count(*) as cnt from '.$this->table_data;

		//프론트 공지만노출여부
		if( defined('__ADMIN__') != true ) {
			$cnt_query .= " where onlynotice != '1' ";
		}
		if( defined('__SELLERADMIN__') == true ){
			$cnt_query.= ' and provider_seq='.$this->providerInfo['provider_seq'];//입점사
		}
		if( $this->pagetype == 'mypage' ) {
			if(!empty($sc['member_seq'])) $cnt_query.= ' and mseq='.$sc['member_seq'];//회원
			if(!empty($sc['mseq'])) $cnt_query.= ' and mseq='.$sc['mseq'];//회원
		}

		$cntquery = $this->db->query($cnt_query);
		$cntrow = $cntquery->result_array();
		return $cntrow[0]['cnt'];
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
		if($this->db->es_use === true){
			$cid = $this->elasticsearch->index_check('stats_goods');
			if($cid !== false){
				if($_COOKIE['shopReferer'] && preg_match('/^http[s]*\:\/\//', $_COOKIE['shopReferer'])){
					$tmp	= parse_url($_COOKIE['shopReferer']);
					if ($tmp['host']){
						$domain						= $tmp['host'];
						$domain						= preg_replace('/^(www\.|m\.)/', '', $domain);
						$paramsEs['referer_domain']	= $domain;
					}
					$paramsEs['referer']				= $_COOKIE['shopReferer'];
				}

				$paramsEs['goods_seq']		= intval($params['goods_seq']);
				$paramsEs['goods_name']		= $goodsname;
				$paramsEs['provider_seq']	= intval($params['provider_seq']);
				$paramsEs['platform']		= $this->platform;
				$paramsEs['review_score']	= intval($params['score']);

				$esParams = $this->elasticsearch->get_stats_params('stats_goods', 'review', $paramsEs, $this->userInfo);
				if($esParams){
					$this->elasticsearch->esClientM->index($esParams);
				}
			}

			return true;
		} else {
			$data = filter_keys($params, $this->db->list_fields($this->table_data));
			$result = $this->db->insert($this->table_data, $data);
			return $this->db->insert_id();
		}
	}

	/*
	 * 게시물정보
	 * @param
	*/
	public function get_data_optimize() {
		$sql = "OPTIMIZE TABLE ".$this->table_data;
		$this->db->query($sql);
	}

	// 조회수 증가
	function hit_update($seq) {
		if(empty($seq))return false;
		$this->db->set('hit', 'hit + 1', FALSE);
		$this->db->update($this->table_data, null, array('seq' => $seq));
	}

	// 추천/비추천/추천5가지 증가
	function board_score_update($seq, $scoreid, $plus = ' + ') {
		if(empty($seq))return false;
		$this->db->set($scoreid, $scoreid.' '.$plus.' 1', FALSE);
		$result = $this->db->update($this->table_data, null, array('seq' => $seq));
		return $result;
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
	    $this->db->update($this->table_data, null, array('seq' => $seq));
	    return $upboarddata;
	}

	// tmpcode 저장
	function tmpcode_update($seq,$tmpcode) {
		if(empty($seq))return false;
		$this->db->set('tmpcode', $tmpcode, FALSE);
		$this->db->update($this->table_data, null, array('seq' => $seq));
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
		$result = $this->db->delete($this->table_data, array('seq' => $seq));
		return $result;
	}

	/*
	 * 게시물전체삭제
	 * @param
	*/
	public function data_delete_id($boardid) {
		//$result = $this->db->delete($this->table_data, array('boardid' => $boardid));
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

/* End of file goodsreview.php */
/* Location: ./app/models/goodsreview */