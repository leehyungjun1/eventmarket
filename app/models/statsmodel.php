<?php
class statsmodel extends CI_Model {
	public function __construct(){
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

	public function get_referer_url($referer){
		$result['referer']			= '';
		$result['referer_domain']	= '';
		if	($referer && preg_match('/^http[s]*\:\/\//', $referer)){
			$tmp	= parse_url($referer);
			if	($tmp['host']){
				$domain						= $tmp['host'];
				$domain						= preg_replace('/^(www\.|m\.)/', '', $domain);
				$result['referer_domain']	= $domain;
			}
			$result['referer']				= $referer;
		}

		return $result;
	}

	public function insert_member_stats($member_seq,$birthday,$address,$sex)
	{
		$platform	= 'P';
		if		($this->fammerceMode || $this->storefammerceMode)	$platform	= 'F';
		elseif	($this->_is_mobile_agent || $this->mobileMode || $this->storemobileMode)		$platform	= 'M';
		if($address) $r_address = explode(" ",$address);
		$refererArr	= $this->get_referer_url($_COOKIE['shopReferer']);
		$insert_member_stats_params = array(
				'member_seq' 	=> $member_seq,
				'member_age' 	=> $birthday && $birthday!='0000-00-00' ? date('Y') - substr($birthday,0,4) + 1 : 0,
				'member_area'	=> $r_address[0],
				'member_sex' 	=> $sex && $sex!='none' ? $sex : 'none',
				'referer_domain'	=> $refererArr['referer_domain'],
				'referer'			=> $refererArr['referer'],
				'ip'			=> $_SERVER['REMOTE_ADDR'],
				'platform'		=> $platform,
				'regist_date'	=> date('Y-m-d H:i:s')
		);
		$this->db->insert('fm_member_stats', $insert_member_stats_params);
	}

	public function insert_order_stats($order_seq,$refererArr=array())
	{
		// naverpaymodel.php 에서는 refererArr 값을 전달해줌 2018-08-02
		if(empty($refererArr)) $refererArr	= $this->get_referer_url($_COOKIE['shopReferer']);
		$insert_order_stats_params = array(
				'order_seq' 	=> $order_seq,
				'buyer_age' 	=> $this->userInfo['birthday'] && $this->userInfo['birthday']!='0000-00-00' ? date('Y') - substr($this->userInfo['birthday'],0,4) + 1 : 0,
				'buyer_area'	=> null,
				'buyer_sex' 	=> $this->userInfo['sex'] && $this->userInfo['sex']!='none' ? $this->userInfo['sex'] : 'none',
				'referer_domain'	=> $refererArr['referer_domain'],
				'referer'			=> $refererArr['referer'],
				'ip'			=> $_SERVER['REMOTE_ADDR']
		);
		$this->db->insert('fm_order_stats', $insert_order_stats_params);
	}

	public function insert_cart_stats($params)
	{
		$refererArr	= $this->get_referer_url($_COOKIE['shopReferer']);

		if($this->db->es_use === true){
			$this->insert_stats_es($params, $refererArr, 'cart');
		} else {
			$insert_stats_params = array(
					'regist_ymd'	=> date('Y-m-d'),
					'regist_date'	=> date('Y-m-d H:i:s'),
					'provider_seq' 	=> $params['provider_seq'],
					'goods_seq' 	=> $params['goods_seq'],
					'goods_name' 	=> $params['goods_name'],
					'option1' 		=> $params['option1'],
					'option2' 		=> $params['option2'],
					'option3' 		=> $params['option3'],
					'option4' 		=> $params['option4'],
					'option5' 		=> $params['option5'],
					'ea' 			=> $params['ea'],
					'age' 			=> $this->userInfo['birthday'] && $this->userInfo['birthday']!='0000-00-00' ? date('Y') - substr($this->userInfo['birthday'],0,4) + 1 : 0,
					'userid' 		=> $this->userInfo['userid'],
					'sex' 			=> $this->userInfo['sex'] && $this->userInfo['sex']!='none' ? $this->userInfo['sex'] : 'none',
					'referer_domain'	=> $refererArr['referer_domain'],
					'referer'			=> $refererArr['referer'],
					'ip'			=> $_SERVER['REMOTE_ADDR']
			);
			$this->db->insert('fm_cart_stats', $insert_stats_params);
		}
	}

	public function insert_wish_stats($params)
	{
		$refererArr	= $this->get_referer_url($_COOKIE['shopReferer']);

		if($this->db->es_use === true){
			$this->insert_stats_es($params, $refererArr, 'wish');
		} else {
			$insert_stats_params = array(
					'regist_ymd'	=> date('Y-m-d'),
					'regist_date'	=> date('Y-m-d H:i:s'),
					'provider_seq' 	=> $params['provider_seq'],
					'goods_seq' 	=> $params['goods_seq'],
					'goods_name' 	=> $params['goods_name'],
					'age' 			=> $this->userInfo['birthday'] && $this->userInfo['birthday']!='0000-00-00' ? date('Y') - substr($this->userInfo['birthday'],0,4) + 1 : 0,
					'userid' 		=> $this->userInfo['userid'],
					'sex' 			=> $this->userInfo['sex'] && $this->userInfo['sex']!='none' ? $this->userInfo['sex'] : 'none',
					'referer_domain'	=> $refererArr['referer_domain'],
					'referer'			=> $refererArr['referer'],
					'ip'			=> $_SERVER['REMOTE_ADDR']
			);
			$this->db->insert('fm_wish_stats', $insert_stats_params);
		}
	}

	public function insert_stats_es($params, $refererArr, $mode=null){
		if($mode){
			if($mode === "search"){
				$index_type = "stats_search";
			} else {
				$index_type = "stats_goods";
			}

			$params['referer_domain']	= $refererArr['referer_domain'];
			$params['referer']			= $refererArr['referer'];
			$params['platform']			= $this->platform;

			$esParams = $this->elasticsearch->get_stats_params($index_type, $mode, $params, $this->userInfo);
			if($esParams){
				$cid = $this->elasticsearch->index_check($index_type);
				if($cid !== false){
					$this->elasticsearch->esClientM->index($esParams);
				}
			}
		}
	}

	public function insert_search_stats($keyword, $member_seq=0)
	{
		$refererArr	= $this->get_referer_url($_COOKIE['shopReferer']);

		if($this->db->es_use === true){
			$params = array();
			$params['keyword']		= $keyword;
			if($member_seq > 0){
				$params['member_seq'] = $member_seq;
			}
			$this->insert_stats_es($params, $refererArr, 'search');
		} else {
			$today = date('Y-m-d',time());
			$list_seq = $this->check_search_keyword($keyword,$today);
			if($list_seq){
				$query = "update fm_search_list set cnt=cnt+1 where list_seq=?";
				$this->db->query($query,array($list_seq));
			}else{
				$query = "insert into fm_search_list set `cnt`=1,`keyword`=?,regist_date=?";
				$this->db->query($query,array($keyword,$today));
			}

			$insert_stats_params = array(
					'regist_date'	=> date('Y-m-d H:i:s'),
					'keyword' 		=> $keyword,
					'age' 			=> $this->userInfo['birthday'] && $this->userInfo['birthday']!='0000-00-00' ? date('Y') - substr($this->userInfo['birthday'],0,4) + 1 : 0,
					'userid' 		=> $this->userInfo['userid'],
					'sex' 			=> $this->userInfo['sex'] && $this->userInfo['sex']!='none' ? $this->userInfo['sex'] : 'none',
					'referer_domain'	=> $refererArr['referer_domain'],
					'referer'			=> $refererArr['referer'],
					'ip'			=> $_SERVER['REMOTE_ADDR']
			);
			$this->db->insert('fm_search_stats', $insert_stats_params);

			if(get_cookie('searchRecent') != 'off'){
				$stodayTime = date('Y-m-d H:i:s',time());
				$iMemberSeq	= $this->userInfo['member_seq'];
				$sIp		= $_SERVER['REMOTE_ADDR'];
				$this->insertSearchRecent($keyword, $stodayTime, $iMemberSeq, $sIp);
			}
		}
	}

	public function getSearchRecent($sIp, $sMemberSeq, $iLimit){
		if( $sIp ){
			$aWhere[]	= "ip = ?";
			$aBind[]	= $sIp;
		}
		if( $sMemberSeq ){
			$aWhere[] = "member_seq = ?";
			$aBind[]	= $sMemberSeq;
		}
		
		if($aWhere){
			$sWhere = "WHERE " . implode(' AND ', $aWhere);
			$sQuery	= "SELECT recent_seq, keyword, update_date FROM fm_search_recent ".$sWhere." ORDER BY cnt DESC, update_date DESC LIMIT ".$iLimit;
			return $this->db->query($sQuery, $aBind);
		}
		return false;
	}

	public function getSearchPopular($sEnd){
		$query = "select keyword, sum(cnt) scnt from (select keyword, cnt from `fm_search_list` where regist_date >= ? order by regist_date DESC limit 500) t group by keyword order by scnt desc limit 10";
		return $this->db->query($query,array($sEnd));
	}

	public function insertSearchRecent($sKeyword, $stodayTime, $iMemberSeq, $sIp){
		$iRecentSeq = $this->checkKeywordRecent($sKeyword, $iMemberSeq, $sIp);
		if(!$iRecentSeq){
			$sQuery = "INSERT INTO fm_search_recent (`regist_date`,`update_date`,`keyword`,`member_seq`,`ip`,`cnt`) VALUES(?, ?, ?, ?, ?, 1)";
			$bind = array($stodayTime, $stodayTime, $sKeyword, $iMemberSeq, $sIp);
		}else{
			$sQuery = "UPDATE fm_search_recent SET `update_date` = ?, `cnt` = `cnt` + 1 WHERE recent_seq = ?";
			$bind = array($stodayTime, $iRecentSeq);
		}
		$this->db->query($sQuery, $bind);
	}

	public function delSearchRecent($aParams){
		if( $aParams['iRecentSeq'] ){
			$sQuery = "DELETE FROM fm_search_recent WHERE recent_seq = ?";
			$bind = array($aParams['iRecentSeq']);
		}else if( $aParams['sIp'] ){
			$sQuery = "DELETE FROM fm_search_recent WHERE ip = ?";
			$bind = array($aParams['sIp']);
		}else if( $aParams['iMemberSeq'] ){
			$sQuery = "DELETE FROM fm_search_recent WHERE member_seq = ?";
			$bind = array($aParams['iMemberSeq']);
		}
		$this->db->query($sQuery, $bind);
	}

	protected function checkKeywordRecent($sKeyword, $iMemberSeq, $sIp){
		$query = "select recent_seq from fm_search_recent where keyword = ? and (member_seq = ? or ip = ?)";
		$bind[] =  $sKeyword;
		$bind[] =  $iMemberSeq;
		$bind[] =  $sIp;
		$query = $this->db->query($query, $bind);
		if($query) $row = $query->row_array();
		return $row['recent_seq'];
	}

	public function check_search_keyword($keyword,$today){
		$query = "select list_seq from fm_search_list where keyword = ? and regist_date=?";
		$bind[] =  $keyword;
		$bind[] =  $today;
		$query = $this->db->query($query,$bind);
		if($query) $row = $query->row_array();
		return $row['list_seq'];
	}

	public function last_year_delete()
	{
		$timestamp = strtotime('-1 year');
		$bind_date[] = date('Y-m-t 00:00:00',$timestamp);
		/*
		$query = "delete from fm_order_stats where regist_date < ?";
		$this->db->query($query,$bind_date);
		*/
		$query = "delete from fm_cart_stats where regist_date < ?";
		$this->db->query($query,$bind_date);
		$query = "delete from fm_wish_stats where regist_date < ?";
		$this->db->query($query,$bind_date);
		$query = "delete from fm_search_stats where regist_date < ?";
		$this->db->query($query,$bind_date);
		//debug_var($this->db->queries);
	}

	// 상품 장바구니 통계 ( 상품기준 - 순위 추출 )
	public function get_goods_cart_stats($params){
		if($params) {
			foreach($params as $k => $v){
				$$k	= $v;
			}
		}

		// 날짜검색
		/* SQL INJECTION 방지를 위한 데이터 바인딩 처리 */
		$bindData = [];
		if (!empty($sdate) && !empty($edate)) {
			$addWhere .= " AND cs.stats_date >= ? " . " AND cs.stats_date <= ? ";
			$bindData[] = $sdate;
			$bindData[] = $edate;
			
		} elseif (empty($sdate) && !empty($edate)) {
			$addWhere .= " AND cs.stats_date <= ? ";
			$bindData[] = $edate;
		} elseif (!empty($sdate) && empty($edate)) {
			$addWhere .= " AND cs.stats_date >= ? ";
			$bindData[] = $sdate;
		}

		// 상품명 검색
		if		(!empty($keyword)){
			$addWhere		.= " AND cs.goods_name like '%".addslashes($keyword)."%' ";
		}

		// 카테고리 검색
		if		(!empty($category1)){
			$category_code	= max(array($category1,$category2,$category3,$category4));
			$addWhere		.= " AND gcl.category_code = '".$category_code."' ";
			$addFrom		.= "LEFT JOIN fm_category_link AS gcl ON cs.goods_seq = gcl.goods_seq ";
		}

		// 브랜드 검색
		if		(!empty($brands1)){
			$brandCode		= max(array($brands1,$brands2,$brands3,$brands4));
			$addWhere		.= " AND gbl.category_code = '".$brandCode."' ";
			$addFrom		.= "LEFT JOIN fm_brand_link AS gbl ON cs.goods_seq = gbl.goods_seq ";
		}

		// 입점사 검색
		if		(!empty($provider_seq) && $provider_seq > 1){
			$addWhere		.= " AND g.provider_seq = '".$provider_seq."' ";
		}

		// 정렬
		$orderBy			= " ORDER BY goods_cnt desc, goods_name";
		if		($order_by == 'users'){
			$orderBy		= " ORDER BY goods_user_cnt desc, goods_name";
		}

		$sql	= "SELECT * 
					FROM (
						SELECT 
							cs.goods_seq						as goods_seq,
							cs.goods_name						as goods_name, 
							SUM(IFNULL(cs.option_ea,0))			as goods_ea,
							SUM(IFNULL(cs.option_user_ea,0))	as goods_user_ea, 
							SUM(IFNULL(cs.option_cnt,0))		as goods_cnt,
							SUM(IFNULL(cs.option_user_cnt,0))	as goods_user_cnt, 
							g.page_view							as page_view,
							g.review_count						as now_review_cnt, 
							IF(cs.goods_seq,(select count(*) from fm_cart where goods_seq = cs.goods_seq),0) as now_cart_cnt,
							IF(cs.goods_seq,(select count(*) from fm_goods_wish where goods_seq = cs.goods_seq),0) as now_wish_cnt,
							IF(cs.goods_seq,(select count(*) from (select count(*), goods_seq from fm_goods_fblike where member_seq>0 group by goods_seq, member_seq) tmp where goods_seq = cs.goods_seq group by goods_seq),0) as now_like_cnt,
							IF(cs.goods_seq,(select count(*) as cnt from fm_goods_restock_notify  where goods_seq=cs.goods_seq and notify_status='none' group by goods_seq),0) as now_restock_cnt 
						FROM 
							fm_accumul_cart_stats				as cs 
							LEFT JOIN fm_goods					as g
								ON cs.goods_seq = g.goods_seq 
							".$addFrom."
						WHERE 
							cs.goods_seq > 0  
							".$addWhere."
						GROUP BY
							cs.goods_seq ) tmp 
					ORDER BY 
						goods_cnt desc, goods_seq 
					LIMIT 100";

		return $this->db->query($sql, $bindData);
	}

	// 옵션 장바구니 통계 ( 상품 하위 옵션 정보 추출 )
	public function get_option_cart_stats($params){
		if ($params) {
			foreach($params as $k => $v) {
				$$k	= $v;
			}
		}

		// 상품고유번호 검색
		if ($goods_seq > 0) {
			$addWhere .= " AND cs.goods_seq = '".$goods_seq."' ";
		}

		// 날짜검색
		/* SQL INJECTION 방지를 위한 데이터 바인딩 처리 */
		$bindData = [];
		if (!empty($sdate) && !empty($edate)){
			$addWhere .= " AND cs.stats_date >= ? " . " AND cs.stats_date <= ? ";
			$bindData[] = $sdate;
			$bindData[] = $edate;
		}elseif	(empty($sdate) && !empty($edate)){
			$addWhere .= " AND cs.stats_date <= ? ";
			$bindData[] = $edate;
		}elseif	(!empty($sdate) && empty($edate)){
			$addWhere .= " AND cs.stats_date >= ? ";
			$bindData[] = $sdate;
		}

		$sql	= "SELECT 
						cs.option1				as option1,
						cs.option2				as option2,
						cs.option3				as option3,
						cs.option4				as option4,
						cs.option5				as option5,
						SUM(cs.option_ea)		as option_ea,
						SUM(cs.option_user_ea)	as option_user_ea,
						SUM(cs.option_cnt)		as option_cnt,
						SUM(cs.option_user_cnt)	as option_user_cnt,
						gs.stock				as stock,
						gs.badstock				as badstock,
						gs.reservation15		as reservation15,
						gs.reservation25		as reservation25 
					FROM 
						fm_accumul_cart_stats				as cs 
						LEFT JOIN fm_goods_option			as go
							ON ( cs.goods_seq = go.goods_seq and cs.option1 = go.option1 and cs.option2 = go.option2 and cs.option3 = go.option3 and cs.option4 = go.option4 and cs.option5 = go.option5 )
						LEFT JOIN fm_goods_supply			as gs
							ON go.option_seq = gs.option_seq 
					WHERE 
						cs.goods_seq > 0  
						".$addWhere."
					GROUP BY
						cs.goods_seq, cs.option1, cs.option2, cs.option3, cs.option4, cs.option5 
					ORDER BY 
						cs.goods_name, cs.option1, cs.option2, cs.option3, cs.option4, cs.option5 ";
		
		return $this->db->query($sql, $bindData);
	}

	public function get_goods_wish_stats($params){
		if($params) {
			foreach($params as $k => $v) {
				$$k	= $v;
			}
		}

		/* SQL INJECTION 방지를 위한 데이터 바인딩 처리 */
		$bindData = [];
		if (!empty($sdate) && !empty($edate)){
			$addWhere .= " and ws.regist_date >= ? " . " and ws.regist_date <= ? ";
			$bindData[] = $sdate." 00:00:00";
			$bindData[] = $edate." 23:59:59";
		} elseif (empty($sdate) && !empty($edate)) {
			$addWhere .= " and ws.regist_date <= ? ";
			$bindData[] = $edate." 23:59:59";
		} elseif (!empty($sdate) && empty($edate)) {
			$addWhere .= " and ws.regist_date >= ? ";
			$bindData[] = $sdate." 00:00:00";
		}

		##
		if (!empty($provider_seq)) {
			$addWhere .= " and ws.provider_seq = '".$provider_seq."' ";
		}

		##
		if (!empty($keyword)) {
			$addWhere .= " and ws.goods_name like '%".addslashes($keyword)."%' ";
		}

		##
		if (!empty($category1)) {
			$category_code	= max(array($category1,$category2,$category3,$category4));
			$addWhere .= " and gcl.category_code = '".$category_code."' ";
			$addFrom .= "LEFT JOIN fm_category_link as gcl on ws.goods_seq = gcl.goods_seq ";
		}

		##
		if (!empty($brands1)) {
			$brandCode = max(array($brands1,$brands2,$brands3,$brands4));
			$addWhere .= " and gbl.category_code = '".$brandCode."' ";
			$addFrom .= "LEFT JOIN fm_brand_link as gbl on ws.goods_seq = gbl.goods_seq ";
		}

		##
		$orderBy = " order by wss.goods_cnt desc, ws.goods_name";
		if ($order_by == 'users'){
			$orderBy = " order by wss2.user_cnt desc, ws.goods_name";
		}


		$query	= "select
					ws.goods_seq			as goods_seq,
					ws.goods_name			as stat_goods_name,
					wss.goods_cnt			as goods_cnt,
					wss2.user_cnt			as user_cnt,
					gs.tstock				as tstock,
					gs.tbadstock			as tbadstock,
					gs.treservation15		as treservation15,
					gs.treservation25		as treservation25,
					g.page_view				as page_view,
					g.review_count			as now_review_cnt,
					IF(ws.goods_seq,(select count(*) from fm_cart where goods_seq = ws.goods_seq),0) as now_cart_cnt,
					IF(ws.goods_seq,(select count(*) from fm_goods_wish where goods_seq = ws.goods_seq),0) as now_wish_cnt,
					IF(ws.goods_seq,(select count(*) from (select count(*), goods_seq from fm_goods_fblike where member_seq>0 group by goods_seq, member_seq) tmp where goods_seq = ws.goods_seq group by goods_seq),0) as now_like_cnt,
					IF(ws.goods_seq,(select count(*) as cnt from fm_goods_restock_notify  where goods_seq=ws.goods_seq and member_seq>0 and notify_status='none' group by goods_seq),0) as now_restock_cnt
				from
					fm_wish_stats						as ws
					INNER JOIN (select count( * ) as goods_cnt, goods_name FROM fm_wish_stats group by goods_name)	as wss
					on ws.goods_name = wss.goods_name
					LEFT JOIN (select count(*) as user_cnt, goods_name from (select  goods_name, userid from fm_wish_stats where userid is not null group by goods_name, userid) as tmp group by goods_name)			as wss2
					on ws.goods_name = wss2.goods_name
					LEFT JOIN fm_goods					as g
					on ws.goods_seq = g.goods_seq
					LEFT JOIN (select goods_seq, sum(stock) as tstock, sum(badstock) as tbadstock, sum(reservation15) as treservation15, sum(reservation25) as treservation25 FROM fm_goods_supply group by goods_seq)	as gs
					on g.goods_seq = gs.goods_seq
					".$addFrom."
				where
					ws.wish_stats_seq > 0
					".$addWhere."
				group by
					ws.goods_name
				".$orderBy."
				limit 0, 100";
		return $this->db->query($query, $bindData);
	}

	public function get_goods_search_stats($params){
		if ($params) {
			foreach($params as $k => $v) {
				$$k	= $v;
			}
		}

		/* SQL INJECTION 에 의한 쿼리 바인딩 처리 */
		##
		$bindData = [];
		if (!empty($sdate) && !empty($edate)) {
			$addWhere .= " and regist_date >= ? ". " and regist_date <= ? ";
			$bindData[] = $sdate." 00:00:00";
			$bindData[] = $edate." 23:59:59";
		}elseif	(empty($sdate) && !empty($edate)){
			$addWhere	.= " and regist_date <= ? ";
			$bindData[] = $edate." 23:59:59";
		}elseif	(!empty($sdate) && empty($edate)){
			$addWhere	.= " and regist_date >= ? ";
			$bindData[] = $sdate." 00:00:00";
		}

		##
		if (!empty($keyword)) {
			$addWhere .= " and keyword like '%".addslashes($keyword)."%' ";
		}

		$query = "
				select
					keyword, count(*) keyword_cnt from fm_search_stats
				where
					search_stats_seq > 0
					".$addWhere."
				group by keyword
				order by keyword_cnt desc
				limit 0, 100";
		return $this->db->query($query, $bindData);
	}

	public function get_goods_search_by_age($keyword,$period)
	{
		$start = date('Y-m-d',strtotime('-'.$period.' days'))." 00:00:00";
		$query = "select IF(ISNULL(userid),'NONE','MEMBER') AS member_check,age,count(*) cnt  from fm_search_stats where keyword=? and regist_date>=? group by member_check, age";
		return $this->db->query($query,array($keyword,$start));
	}

	public function get_goods_search_by_sex($keyword,$period)
	{
		$start = date('Y-m-d',strtotime('-'.$period.' days'))." 00:00:00";
		$query = "select IF(ISNULL(userid),'NONE','MEMBER') AS member_check,sex,count(*) cnt  from fm_search_stats where keyword=? and regist_date>=? group by member_check,sex";
		return $this->db->query($query,array($keyword,$start));
	}

	public function get_goods_search_by_date($keyword,$period)
	{
		$start = date('Y-m-d',strtotime('-'.$period.' days'))." 00:00:00";
		$query = "
		select * from (
			select substring(regist_date,1,10) regist_date,count(*) cnt  from fm_search_stats where keyword=? and regist_date>=? group by substring(regist_date,1,10)
		) t order by t.cnt desc limit 5
		";
		return $this->db->query($query,array($keyword,$start));
	}

	public function get_goods_search_paging_by_date($keyword='', $start='', $end='', $page=1)
	{
		$this->db->select('*')->from('fm_search_stats');
		if ($keyword) {
			$this->db->where('keyword',$keyword);
		}
		if( $start ){
			$start = $start." 00:00:00";
			$this->db->where('regist_date >= ', $start);
		}
		if( $end ){
			$end = $end." 23:59:59";
			$this->db->where('regist_date <= ', $end);
		}

		$query = $this->db->get();
		$query = $this->db->last_query();
		// paging (페이지당출력수,현재페이지넘버,페이지숫자링크갯수,쿼리,인자)
		$result = select_page(10,$page,10,$query);
		return $result;
	}

	public function get_goods_review_stats($params){
		if ($params) {
			foreach($params as $k => $v) {
				$$k	= $v;
			}
		}

		/* SQL INJECTION 에 의한 쿼리 바인딩 처리 */
		##
		$bindData = [];
		if (!empty($sdate) && !empty($edate)) {
			$addWhere .= " and g.regist_date >= ? " . " and g.regist_date <= ? ";
			$bindData[] = $sdate." 00:00:00";
			$bindData[] = $edate." 23:59:59";

		} elseif (empty($sdate) && !empty($edate)){
			$addWhere .= " and g.regist_date <= ? ";
			$bindData[] = $edate." 23:59:59";
		} elseif (!empty($sdate) && empty($edate)){
			$addWhere .= " and g.regist_date >= ? ";
			$bindData[] = $sdate." 00:00:00";
		}

		##
		if (!empty($keyword)) {
			$addWhere .= " and g.goods_name like '%".addslashes($keyword)."%' ";
		}

		##
		if (!empty($category1)) {
			$category_code = max(array($category1,$category2,$category3,$category4));
			$addWhere .= " and gcl.category_code = '".$category_code."' ";
			$addFrom .= "LEFT JOIN fm_category_link as gcl on g.goods_seq = gcl.goods_seq ";
		}

		##
		if (!empty($brands1)) {
			$brandCode = max(array($brands1,$brands2,$brands3,$brands4));
			$addWhere .= " and gbl.category_code = '".$brandCode."' ";
			$addFrom .= "LEFT JOIN fm_brand_link as gbl on g.goods_seq = gbl.goods_seq ";
		}

		##
		if (!empty($provider_seq)){
			$addWhere .= " and g.provider_seq = '".$provider_seq."' ";
		}

		$query	= "select
					g.goods_seq				as goods_seq,
					g.goods_name			as stat_goods_name,
					gr.reviewCnt		as review_cnt,
					gs.tstock				as tstock,
					gs.tbadstock			as tbadstock,
					gs.treservation15		as treservation15,
					gs.treservation25		as treservation25,
					g.page_view				as page_view,
					g.review_count			as now_review_cnt,
					(select count(*) from fm_cart where goods_seq = g.goods_seq)	as now_cart_cnt,
					(select count(*) from fm_goods_wish where goods_seq = g.goods_seq)	as now_wish_cnt,
					(select count(*) from (select count(*), goods_seq from fm_goods_fblike where member_seq>0 group by goods_seq, member_seq) tmp where goods_seq = g.goods_seq group by goods_seq) as now_like_cnt,
					(select count(*) as cnt from fm_goods_restock_notify  where goods_seq=g.goods_seq and member_seq>0 and notify_status='none' group by goods_seq) as now_restock_cnt
				from
					fm_goods							as g
					INNER JOIN (select count(*) reviewCnt, goods_seq from fm_goods_review
								where length(goods_seq) > 0 ".$addWhere2." group by goods_seq )	as gr
						on (INSTR(gr.goods_seq, CONCAT(g.goods_seq, ',')) or
							INSTR(gr.goods_seq, CONCAT(',', g.goods_seq)) or
							gr.goods_seq = g.goods_seq)
					LEFT JOIN (select goods_seq, sum(stock) as tstock, sum(badstock) as tbadstock, sum(reservation15) as treservation15, sum(reservation25) as treservation25 FROM fm_goods_supply group by goods_seq)	as gs
					on g.goods_seq = gs.goods_seq
					".$addFrom."
				where
					g.goods_seq > 0
					".$addWhere."
				group by g.goods_seq
				order by gr.reviewCnt desc
				limit 0, 100";

		return $this->db->query($query, $bindData);
	}

	public function get_goods_restock_stats($params){
		if($params) {
			foreach($params as $k => $v) {
				$$k	= $v;
			}
		} 

		##
		/* SQL INJECTION 에 의한 쿼리 바인딩 처리 */
		$bindData = [];
		if (!empty($sdate) && !empty($edate)) {
			$addWhere .= " and grn.regist_date >= ? " . " and grn.regist_date <= ? ";
			$bindData[] = $sdate." 00:00:00";
			$bindData[] = $edate." 23:59:59";
		}elseif	(empty($sdate) && !empty($edate)) {
			$addWhere .= " and grn.regist_date <= ? ";
			$bindData[] = $edate." 23:59:59";
		}elseif	(!empty($sdate) && empty($edate)){
			$addWhere .= " and grn.regist_date >= ? ";
			$bindData[] = $sdate." 00:00:00";
		}

		##
		if (!empty($provider_seq)) {
			$addWhere .= " and g.provider_seq = '".$provider_seq."' ";
		}

		##
		if (!empty($keyword)) {
			$addWhere .= " and g.goods_name like '%".addslashes($keyword)."%' ";
		}

		##
		if (!empty($category1)) {
			$category_code	= max(array($category1,$category2,$category3,$category4));
			$addWhere .= " and gcl.category_code = '".$category_code."' ";
			$addFrom .= "LEFT JOIN fm_category_link as gcl on g.goods_seq = gcl.goods_seq ";
		}

		##
		if (!empty($brands1)) {
			$brandCode = max(array($brands1,$brands2,$brands3,$brands4));
			$addWhere .= " and gbl.category_code = '".$brandCode."' ";
			$addFrom .= "LEFT JOIN fm_brand_link as gbl on g.goods_seq = gbl.goods_seq ";
		}

		$query	= "select
					g.goods_seq				as goods_seq,
					g.goods_name			as stat_goods_name,
					count(grn.goods_seq)	as restock_cnt,
					gs.tstock				as tstock,
					gs.tbadstock			as tbadstock,
					gs.treservation15		as treservation15,
					gs.treservation25		as treservation25,
					g.page_view				as page_view,
					g.review_count			as now_review_cnt,
					(select count(*) from fm_cart where goods_seq = g.goods_seq)	as now_cart_cnt,
					(select count(*) from fm_goods_wish where goods_seq = g.goods_seq)	as now_wish_cnt,
					(select count(*) from (select count(*), goods_seq from fm_goods_fblike where member_seq>0 group by goods_seq, member_seq) tmp where goods_seq = g.goods_seq group by goods_seq) as now_like_cnt,
					(select count(*) as cnt from fm_goods_restock_notify  where goods_seq=g.goods_seq and member_seq>0 and notify_status='none' group by goods_seq) as now_restock_cnt
				from
					fm_goods							as g
					INNER JOIN fm_goods_restock_notify	as grn
					on grn.goods_seq = g.goods_seq
					LEFT JOIN (select goods_seq, sum(stock) as tstock, sum(badstock) as tbadstock, sum(reservation15) as treservation15, sum(reservation25) as treservation25 FROM fm_goods_supply group by goods_seq)	as gs
					on g.goods_seq = gs.goods_seq
					".$addFrom."
				where
					grn.notify_status='none'
					".$addWhere."
				group by grn.goods_seq
				order by restock_cnt desc
				limit 0, 100";

		return $this->db->query($query, $bindData);
	}

	public function get_sales_sales_monthly_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($q_type == 'order'){

			$addWhere	= "";
			if	(is_array($sitetype) && count($sitetype) > 0){
				$addWhere	= " AND sitetype in ('".implode("','",$sitetype)."') ";
			}
			if	(is_array($not_sitetype) && count($not_sitetype) > 0){
				$addWhere	= " AND sitetype not in ('".implode("','",$not_sitetype)."') ";
			}

			// added by jpseo	- 2015-06-26
			// 통계재기획으로 인한 재 수정 :: 2015-09-11 lwh
			$query	="
				SELECT
					 YEAR(deposit_date)				AS stats_year
					,MONTH(deposit_date)			AS stats_month
					,SUM(settleprice_sum)			AS month_settleprice_sum
					,SUM(m_settleprice)				AS m_settleprice_sum
					,SUM(p_settleprice)				AS p_settleprice_sum
					,SUM(m_shipping_cost)			AS m_shipping_cost_sum
					,SUM(p_shipping_cost)			AS p_shipping_cost_sum
					,SUM(enuri_sum)					AS month_enuri_sum
					,SUM(m_enuri_sum)				AS m_enuri_sum
					,SUM(p_enuri_sum)				AS p_enuri_sum
					,SUM(emoney_use_sum)			AS month_emoney_use_sum
					,SUM(cash_use_sum)				AS month_cash_use_sum
					,SUM(npay_point_use_sum)		AS month_npay_point_use_sum
					,SUM(count_sum)					AS month_count_sum
					,SUM(shipping_cost_sum)			AS shipping_cost_sum
					,SUM(goods_shipping_cost_sum)	AS goods_shipping_cost_sum
					,SUM(m_goods_shipping_cost_sum)	AS m_goods_shipping_cost_sum
					,SUM(p_goods_shipping_cost_sum)	AS p_goods_shipping_cost_sum
					,SUM(IFNULL(shipping_cost_sum,0) + IFNULL(goods_shipping_cost_sum,0))										AS month_shipping_cost_sum

					,SUM(IFNULL(shipping_coupon_sale_sum,0) + IFNULL(option_coupon_sale_sum,0))									AS month_coupon_sale_sum
					,SUM(IFNULL(option_member_sale_sum,0)+IFNULL(suboption_member_sale_sum,0))									AS month_member_sale_sum
					,SUM(option_fblike_sale_sum)	AS month_fblike_sale_sum
					,SUM(option_mobile_sale_sum)	AS month_mobile_sale_sum
					,SUM(IFNULL(shipping_promotion_code_sale_sum,0)+IFNULL(option_promotion_code_sale_sum,0))					AS month_promotion_code_sale_sum
					,SUM(option_referer_sale_sum)	AS month_referer_sale_sum
					,SUM(option_npay_sale_seller_sum)	AS month_npay_sale_seller_sum
					,SUM(option_npay_sale_npay_sum)	AS month_npay_sale_npay_sum
					,SUM(IFNULL(option_supply_price_sum,0)+IFNULL(suboption_supply_price_sum,0))									AS month_supply_price_sum
					,SUM(IFNULL(option_commission_price_sum,0)+IFNULL(suboption_commission_price_sum,0))						AS month_commission_price_sum
					,SUM(IFNULL(option_commission_price_sum_krw,0)+IFNULL(suboption_commission_price_sum_krw,0))			AS month_commission_price_sum_krw
					,SUM(IFNULL(settleprice_sum,0)) + SUM(IFNULL(emoney_use_sum,0)) + SUM(IFNULL(cash_use_sum,0)) + SUM(IFNULL(npay_point_use_sum,0)) -  SUM(IFNULL(shipping_cost_sum,0)) - SUM(IFNULL(goods_shipping_cost_sum,0))
													AS month_goods_price_sum
				FROM
					fm_accumul_sales_mdstats
				WHERE
					YEAR(deposit_date) = '".$year."'
					".$addWhere."
				GROUP BY
					DATE_FORMAT(deposit_date, '%Y-%m')
			";

		}elseif	($q_type == 'refund'){

			if	(is_array($sitetype) && count($sitetype) > 0){
				$addWhere	.= " and sitetype in ('".implode("','",$sitetype)."') ";
			}
			if	(is_array($not_sitetype) && count($not_sitetype) > 0){
				$addWhere	= " AND sitetype not in ('".implode("','",$not_sitetype)."') ";
			}

			/* 데이터 추출 : 환불금액, 환불건수 */
			// 통계재기획으로 인한 재 수정 :: 2015-09-11 lwh
			$query	= "
					SELECT
						stats_year
						,stats_month
						,SUM(refund_price_total_sum)	AS month_refund_price_total_sum
						,SUM(m_refund_price_sum)		AS month_m_refund_price_total_sum
						,SUM(p_refund_price_sum)		AS month_p_refund_price_total_sum

						,SUM(refund_price_sum)			AS month_refund_settle_sum
						,SUM(refund_emoney_sum)			AS month_refund_emoney_sum
						,SUM(refund_cash_sum)			AS month_refund_cash_sum

						,SUM(refund_price_sum_A)		AS month_refund_price_sum_A
						,SUM(refund_price_sum_A) + SUM(refund_emoney_sum) + SUM(refund_cash_sum)
														AS month_refund_price_sum
						,SUM(m_refund_price_sum_A)		AS month_m_refund_price_sum
						,SUM(p_refund_price_sum_A)		AS month_p_refund_price_sum

						,SUM(refund_price_sum_R)		AS month_rollback_price_sum
						,SUM(m_refund_price_sum_R)		AS month_m_rollback_price_sum
						,SUM(p_refund_price_sum_R)		AS month_p_rollback_price_sum

						,SUM(refund_count_sum_A)		AS month_refund_count_sum
						,SUM(refund_count_sum_R)		AS month_rollback_count_sum

						,SUM(refund_supply_price_sum_A)	AS month_refund_supply_price_sum
						,SUM(refund_commission_price_sum_A)
														AS month_refund_commission_price_sum
						,SUM(refund_commission_price_sum_A_krw)
														AS month_refund_commission_price_sum_krw
						,SUM(refund_rollback_supply_price_sum_R)
														AS refund_rollback_supply_price_sum
						,SUM(refund_rollback_commission_price_sum_R)
														AS refund_rollback_commission_price_sum
					FROM
					(
						SELECT
							refund_type
							,stats_year
							,stats_month
							,SUM(refund_price_sum+refund_emoney_sum+refund_cash_sum)
													AS refund_price_total_sum
							,SUM(m_refund_price)	AS m_refund_price_sum
							,SUM(p_refund_price)	AS p_refund_price_sum

							,SUM(refund_price_sum)	AS refund_price_sum
							,IFNULL(SUM(refund_emoney_sum),0)
													AS refund_emoney_sum
							,IFNULL(SUM(refund_cash_sum),0)
													AS refund_cash_sum

							,IF(refund_type='A',SUM(refund_price_sum),0)
													AS refund_price_sum_A
							,IF(refund_type='A',SUM(m_refund_price),0)
													AS m_refund_price_sum_A
							,IF(refund_type='A',SUM(p_refund_price),0)
													AS p_refund_price_sum_A


							,IF(refund_type='R',SUM(refund_price_sum),0)
													AS refund_price_sum_R
							,IF(refund_type='R',SUM(m_refund_price),0)
													AS m_refund_price_sum_R
							,IF(refund_type='R',SUM(p_refund_price),0)
													AS p_refund_price_sum_R

							,IF(refund_type='A',SUM(refund_count_sum),0)
													AS refund_count_sum_A
							,IF(refund_type='R',SUM(refund_count_sum),0)
													AS refund_count_sum_R

							,IF(refund_type='A',SUM(IFNULL(option_supply_price_sum,0))+SUM(IFNULL(suboption_supply_price_sum,0)),0)
													AS refund_supply_price_sum_A
							,IF(refund_type='A',SUM(IFNULL(option_commission_price_sum,0))+SUM(IFNULL(suboption_commission_price_sum,0)),0)
													AS refund_commission_price_sum_A
							,IF(refund_type='A',SUM(IFNULL(option_commission_price_sum_krw,0))+SUM(IFNULL(suboption_commission_price_sum_krw,0)),0)
													AS refund_commission_price_sum_A_krw
							,IF(refund_type='R',SUM(IFNULL(option_supply_price_sum,0))+SUM(IFNULL(suboption_supply_price_sum,0)),0)
													AS refund_rollback_supply_price_sum_R
							,IF(refund_type='R',SUM(IFNULL(option_commission_price_sum,0))+SUM(IFNULL(suboption_commission_price_sum,0)),0)
													AS refund_rollback_commission_price_sum_R
						FROM
							fm_accumul_sales_refund
						WHERE
							YEAR(refund_date) = '".$year."'
							".$addWhere."
						GROUP BY refund_type, stats_month
					) as tmp
					GROUP BY stats_month
					";
		}

		return $this->db->query($query);
	}

	public function get_sales_sales_daily_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($q_type == 'order'){

			if	(is_array($sitetype) && count($sitetype) > 0){
				$addWhere	= " and sitetype in ('".implode("','",$sitetype)."') ";
			}

			/* 데이터 추출 : 주문금액, 결제금액, 마일리지사용, 할인금액, 배송비, 매입금액 */
			$sql = "
					SELECT
						stats_year
						,stats_month
						,stats_day
						,SUM(settleprice_sum)		AS day_settleprice_sum
						,SUM(enuri_sum)				AS day_enuri_sum
						,SUM(emoney_use_sum)		AS day_emoney_use_sum
						,SUM(cash_use_sum)			AS day_cash_use_sum
						,SUM(count_sum)				AS day_count_sum
						,SUM(shipping_cost_sum+goods_shipping_cost_sum)
													AS day_shipping_cost_sum
						,SUM(option_ori_price_sum)	AS day_ori_price_sum
						,SUM(IFNULL(shipping_coupon_sale_sum,0)+IFNULL(option_coupon_sale_sum,0))						AS day_coupon_sale_sum
						,SUM(IFNULL(shipping_promotion_code_sale_sum,0)+IFNULL(option_promotion_code_sale_sum,0))		AS day_promotion_code_sale_sum
						,SUM(IFNULL(option_fblike_sale_sum,0))															AS day_fblike_sale_sum
						,SUM(IFNULL(option_mobile_sale_sum,0))															AS day_mobile_sale_sum
						,SUM(IFNULL(option_member_sale_sum,0)+IFNULL(suboption_member_sale_sum,0))						AS day_member_sale_sum
						,SUM(IFNULL(option_referer_sale_sum,0))															AS day_referer_sale_sum
						,SUM(IFNULL(option_supply_price_sum,0)+IFNULL(suboption_supply_price_sum,0))						AS day_supply_price_sum
					FROM
						fm_accumul_sales_mdstats
					WHERE
						YEAR(deposit_date)='".$year."'
						AND MONTH(deposit_date)='".$month."'
						".$addWhere."
					GROUP BY stats_day
				";
		}elseif($q_type == 'refund'){
			if	(is_array($sitetype) && count($sitetype) > 0){
				$addWhere	.= " AND sitetype in ('".implode("','",$sitetype)."') ";
			}

			/* 데이터 추출 : 환불금액, 환불건수 */
			$sql = "
				SELECT
					stats_year
					,stats_month
					,stats_day
					,SUM(refund_price_sum+refund_emoney_sum+refund_cash_sum)																AS day_refund_price_sum
					,IF(refund_type='A',SUM(refund_count_sum),0)	AS day_refund_count_sum_A
					,IF(refund_type='R',SUM(refund_count_sum),0)	AS day_refund_count_sum_R
					,IF(refund_type='A',SUM(refund_price_sum),0)	AS refund_price_sum
					,IF(refund_type='A',SUM(refund_cash_sum),0)		AS refund_cash_sum
					,IF(refund_type='A',SUM(refund_emoney_sum),0)	AS refund_emoney_sum
					,IF(refund_type='R',SUM(refund_price_sum+refund_emoney_sum+refund_cash_sum),0)					AS day_rollback_price_sum
					,SUM(refund_price_sum+refund_emoney_sum+refund_cash_sum)										AS day_refund_price_sum_total
					,SUM(IFNULL(option_supply_price_sum,0)+IFNULL(suboption_supply_price_sum,0))						AS day_refund_supply_price_sum
				FROM
					fm_accumul_sales_refund
				WHERE
					YEAR(refund_date)='".$year."'
					AND MONTH(refund_date)='".$month."'
					".$addWhere."
				GROUP BY stats_day
			";
		}

		return $this->db->query($sql);
	}

	public function get_sales_sales_hour_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		// 마이그레이션 날짜에맞춰서 통계가 노출되어야하기 때문에 추가 :: 2019-01-21 lkh
		$this->load->helper('accountall');
		if($month){
			$conv_month = sprintf("%02d",$month);
		}
		$accountAllMiDate			= getAccountSetting();
		$accountAllStatsV2			= $accountAllMiDate['accountall_stats_v2'];		// 통계 개선 2019-06-19 by hed
		
		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 시작 by hed 2019-06-19 #34379
		// ==========================================================================
		if($accountAllStatsV2 && date("Ym",$accountAllStatsV2) <= $year.$conv_month){
			$params_stats_v2 = array();
			$params_stats_v2['year']				= $year;
			$params_stats_v2['month']				= $month;
			$params_stats_v2['conv_month']			= $conv_month;
			$params_stats_v2['sitetype']			= $sitetype;
			
			$this->load->model('accountallmodel');
			$statsData = $this->accountallmodel->get_sales_sales_hour_stats_v2($params_stats_v2);
			return $statsData;
		}
		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 종료 by hed 2019-06-19 #34379
		// ==========================================================================

		/* 데이터 추출 : 결제금액, 건수 */
		$addWhere	= " and a.deposit_yn='y' and a.step between '15' and '75'"
					. " and year(a.deposit_date) = '".$year."' ";
		if(!empty($month))
			$addWhere	.= " and month(a.deposit_date) = '".$month."' ";
		if	(is_array($sitetype) && count($sitetype) > 0){
			$addWhere	.= " and a.sitetype in ('".implode("','",$sitetype)."') ";
		}

		$query = "
				select
					a.order_seq as order_seq
					,year(a.deposit_date) as stats_year
					,month(a.deposit_date) as stats_month
					,hour(a.deposit_date) as stats_hour
					,day(a.deposit_date) as stats_day
					,sum(a.settleprice) as month_settleprice_sum
					,count(*) as month_count_sum
				from fm_order as a
				where a.order_seq > 0 ".$addWhere."
				group by stats_hour";

		return $this->db->query($query);
	}

	// 판매통계 > 상품 > 일별 정산테이블로 수정됨 :: 2018-07-27 pjw
	public function get_sales_goods_daily_stats($params,$type='web'){
		// params 변수처리
		if($params) foreach($params as $k => $v){$$k	= $v;	}	
		
		// 테이블 명 없는경우 다시 세팅
		if($table_name == '') $table_name = $this->get_stat_table($edate);

		if	($q_type == 'list'){

			if($sort == "deposit_ymd desc") $sort = "ord.deposit_date desc";

			if	($type == 'web')	$pagein = " limit 0, 300 ";
			if	($keyword)			$addWhere2	.= " and concat(ord.order_goods_seq,ifnull(ord.goods_code,''),ord.order_goods_name) like '%".addslashes($keyword)."%'";
			if	((int)$provider_seq > 0)	$providerWhere	= " AND ord.provider_seq = ".$provider_seq;
			$orderBy	= " order by ".$sort.", ord.order_goods_seq asc";			
			
			if($type == 'sum') {
				$selectquery = " SUM( ord.event_sale_unit * ord.ea + event_sale_rest) AS event_sale, 
								SUM( ord.multi_sale_unit * ord.ea + multi_sale_rest) AS multi_sale, 
								SUM( ord.member_sale_unit * ord.ea + member_sale_rest) AS member_sale, 
								SUM( ord.fblike_sale_unit * ord.ea + fblike_sale_rest) AS fblike_sale, 
								SUM( ord.mobile_sale_unit * ord.ea + mobile_sale_rest) AS mobile_sale, 
								SUM( ord.code_sale_unit * ord.ea + code_sale_rest) AS promotion_code_sale, 
								SUM( ord.referer_sale_unit * ord.ea + referer_sale_rest) AS referer_sale, 
								SUM( ord.coupon_sale_unit * ord.ea + coupon_sale_rest) AS coupon_sale, 
								SUM( ord.price * ord.ea ) AS goods_price, 
								p.provider_name ";
			}else{
				$selectquery = "
					ord.*
					,date_format(ord.deposit_date,'%Y-%m-%d') AS deposit_ymd
					,p.provider_name
					,ord.price * ord.ea AS goods_price
				";
			}

			$query	= "
				SELECT
						".$selectquery."
				FROM ".$table_name."	AS ord
					LEFT JOIN fm_provider AS p ON ord.provider_seq = p.provider_seq
				WHERE 1=1
					and account_type = 'order'
					and order_type != 'shipping'
					and status in ('complete', 'overdraw')
					".$providerWhere."
					".$addWhere2."
					".$orderBy."
					".$pagein;
			
		}elseif($q_type == 'order'){
			// 기본배송비, 개별배송비, 마일리지사용, 예치금사용, 에누리
			/*
			shipping_cost_sum(기본 배송비)는 order_type 구분으로 행으로 들어가기에 서브쿼리로 처리
			goods_shipping_cost_sum(개별 배송비)는 배송그룹 업데이트 후 사용하지 않으므로 0 처리
			*/
			$where  = " and status in ('complete', 'overdraw') and account_type = 'order' ";
			
			$query	= "
				SELECT
					 SUM(price * ea)												AS settleprice_sum
					,SUM(enuri_sale_unit + enuri_sale_rest)							AS enuri_sum					
					,SUM((emoney_sale_unit * ea) + emoney_sale_rest)				AS emoney_use_sum
					,SUM((cash_sale_unit * ea) + cash_sale_rest)					AS cash_use_sum		
					,SUM((event_sale_unit * ea)		+ event_sale_rest )			AS event_sale 
					,SUM((multi_sale_unit * ea )	+ multi_sale_rest)				AS multi_sale 
					,SUM( member_sale_unit * ea	+ member_sale_rest )				AS member_sale 
					,SUM( fblike_sale_unit * ea	+ fblike_sale_rest )				AS fblike_sale 
					,SUM( mobile_sale_unit * ea	+ mobile_sale_rest )				AS mobile_sale 
					,SUM(referer_sale_unit * ea	+ referer_sale_rest)				AS referer_sale_sum
					,SUM(coupon_sale_unit * ea	+ coupon_sale_rest)					AS coupon_sale_sum
					,SUM(code_sale_unit * ea	+ code_sale_rest)					AS promotion_code_sale_sum
					,SUM(0)															AS goods_shipping_cost_sum
					,(SELECT SUM(price) FROM ".$table_name." WHERE 1=1 and order_type = 'shipping' ".$where.")	AS shipping_cost_sum
					,(SELECT SUM(coupon_sale_unit * ea	+ coupon_sale_rest) FROM ".$table_name." WHERE 1=1 and order_type = 'shipping' ".$where.")	AS shipping_coupon_sale_sum
					,(SELECT SUM(code_sale_unit * ea	+ code_sale_rest) FROM ".$table_name." WHERE 1=1 and order_type = 'shipping' ".$where.")	AS shipping_promotion_code_sale_sum
					,(SELECT SUM(price) from ".$table_name." WHERE 1=1 and account_type = 'return' AND order_type = 'shipping' ) AS return_shipping_cost_sum
				FROM
					".$table_name."
				WHERE 1=1 
					".$where;

		}elseif($q_type == 'refund'){

			/*	주문테이블 에서 집계 테이블로 By jpseo - 2015-06-23
				데이터 추출 : 환불금액, 환불건수*/
			$where		= " and status in ('complete', 'overdraw') and account_type = 'refund' ";			
			$query		= "
				SELECT  SUM( (emoney_sale_unit * ea)	+ emoney_sale_rest)			AS refund_emoney_sum,
 					    SUM( (cash_sale_unit * ea)		+ cash_sale_rest)			AS refund_cash_sum,
						SUM( enuri_sale_unit + enuri_sale_rest)						AS refund_enuri_sum,
					    SUM( (event_sale_unit * ea)		+ event_sale_rest )			AS event_sale, 
						SUM( (multi_sale_unit * ea )	+ multi_sale_rest)			AS multi_sale, 
						SUM( member_sale_unit * ea	+ member_sale_rest )			AS member_sale, 
						SUM( fblike_sale_unit * ea	+ fblike_sale_rest )			AS fblike_sale, 
						SUM( mobile_sale_unit * ea	+ mobile_sale_rest )			AS mobile_sale, 
						SUM( code_sale_unit	 * ea	+ code_sale_rest )				AS promotion_code_sale, 
						SUM( referer_sale_unit * ea	+ referer_sale_rest )			AS referer_sale, 
						SUM( coupon_sale_unit * ea	+ coupon_sale_rest )			AS coupon_sale,
					   (SELECT SUM(price * ea) FROM ".$table_name." WHERE 1=1  ".$where.") AS refund_price_sum 
				FROM ".$table_name." 
				WHERE 1=1 ".$where;
		}
//		debug($query);
		$query = $this->db->query($query);
		return $query;
	}

	public function get_sales_goods_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		##
		if		(!empty($sdate) && !empty($edate)){
			$accumul_where	.= " and st.deposit_ymd between '".$sdate."'  and '".$edate."' ";
		}elseif	(empty($sdate) && !empty($edate)){
			$accumul_where	.= " and st.deposit_ymd <= '".$edate."' ";
		}elseif	(!empty($sdate) && empty($edate)){
			$accumul_where	.= " and st.deposit_ymd >= '".$sdate."' ";
		}

		##
		if		(!empty($keyword)){
			$addWhere	.= " and (st.goods_seq like '".$keyword."%' OR ifnull(st.goods_code,'') like '%".$keyword."%' OR st.order_goods_name like '%".$keyword."%') ";
		}

		##
		if		(!empty($category1)){
			$category_code	= max(array($category1,$category2,$category3,$category4));
			if	($category_code){
				$addWhere	.= " and find_in_set('".$category_code."', st.category_codes)";
				/*$addFrom	.= "INNER JOIN fm_category_link		as cl
								on g.goods_seq = cl.goods_seq  ";*/
			}
		}

		##
		if		(!empty($brands1)){
			$brands_code	= max(array($brands1,$brands2,$brands3,$brands4));
			if	($brands_code){
				$addWhere	.= " and find_in_set('".$brands_code."', st.brand_codes)";
				/*$addFrom	.= "INNER JOIN fm_brand_link		as bl
								on g.goods_seq = bl.goods_seq  ";*/
			}
		}

		##
		if		(!empty($provider_seq)){
			$addWhere2	.= " and g.provider_seq = '".$provider_seq."' ";
		}

		$orderBy1	= "goods_cnt desc";
		$orderBy2	= "option_cnt desc";
		if	($order_by == 'price'){
			$orderBy1	= "goods_price desc";
			$orderBy2	= "option_price desc";
		}

		$query	= "select
					ord.goods_seq,
					p.provider_name,
					ord.goods_name			as stat_goods_name,
					ord.goods_price,
					ord.goods_cnt,
					ord.option1,
					ord.option2,
					ord.option3,
					ord.option4,
					ord.option5,
					ord.title1,
					ord.title2,
					ord.title3,
					ord.title4,
					ord.title5,
					ord.page_view			as page_view,
					ord.review_count		as now_review_cnt,
					gs.stock				as tot_stock,
					(select count(*) cart_cnt from fm_cart_option co,fm_cart ca where ca.cart_seq=co.cart_seq and ca.goods_seq=ord.goods_seq and ca.distribution='cart' and ifnull(co.option1,'')=ord.option1 and ifnull(co.option2,'')=ord.option2 and ifnull(co.option3,'')=ord.option3 and ifnull(option4,'')=ord.option4 and ifnull(option5,'')=ord.option5) as now_cart_cnt,
					(select count(*) from fm_goods_wish where goods_seq = ord.goods_seq) as now_wish_cnt,
					(select count(*) from fm_goods_fblike where member_seq>0 and goods_seq = ord.goods_seq) as now_like_cnt,
					(select count(*) as cnt from fm_goods_restock_notify  where goods_seq=ord.goods_seq and member_seq>0 and notify_status='none') as now_restock_cnt,
					go.package_option_seq1,
					go.package_option_seq2,
					go.package_option_seq3,
					go.package_option_seq4,
					go.package_option_seq5
				from
					(
					 select
						goods_seq,
						sum(ea) as goods_cnt,
						sum(price*t.ea) as goods_price,
						order_goods_name  as goods_name,
						option1,
						option2,
						option3,
						option4,
						option5,
						title1,title2,title3,title4,title5,
						page_view,
						review_count,
						purchase_ea,
						provider_seq
					 from
					 (
					  select
						st.goods_seq,
						st.ea,
						st.price,
						st.order_goods_name,
						ifnull(st.option1,'') option1,
						ifnull(st.option2,'') option2,
						ifnull(st.option3,'') option3,
						ifnull(st.option4,'') option4,
						ifnull(st.option5,'') option5,
						st.title1,st.title2,st.title3,st.title4,st.title5,
						g.page_view,
						g.review_count,
						g.purchase_ea,
						g.provider_seq
					  from
						fm_accumul_stats_sales as st LEFT JOIN fm_goods	as g
						on g.goods_seq = st.goods_seq
					  where
						st.goods_seq > 0 and
						g.goods_type = 'goods'
						".$accumul_where."
						".$addWhere."
						".$addWhere2."
					) t
					group by goods_seq,goods_name,option1,option2,option3,option4,option5
					order by ".$orderBy1.", goods_seq
					limit 0, 100)	as ord
					LEFT JOIN fm_goods_option   as go on ord.goods_seq = go.goods_seq
						and ord.option1=ifnull(go.option1,'') and ord.option2=ifnull(go.option2,'') and ord.option3=ifnull(go.option3,'')
						and ord.option4=ifnull(go.option4,'') and ord.option5=ifnull(go.option5,'')
					LEFT JOIN fm_goods_supply	as gs on go.option_seq = gs.option_seq
					LEFT JOIN fm_provider as p on ord.provider_seq = p.provider_seq
				order by ".$orderBy1.", purchase_ea desc ";
		$query =  $this->db->query($query);
		return $query;
	}

	/* 추가 옵션 상품에 따른 쿼리 :: 2014-08-01 lwh */
	public function get_sales_option_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		##
		if		(!empty($sdate) && !empty($edate)){
			$addWhere	.= " and o.deposit_date >= '".$sdate." 00:00:00' "
						. " and o.deposit_date <= '".$edate." 23:59:59' ";
		}elseif	(empty($sdate) && !empty($edate)){
			$addWhere	.= " and o.deposit_date <= '".$edate." 23:59:59' ";
		}elseif	(!empty($sdate) && empty($edate)){
			$addWhere	.= " and o.deposit_date >= '".$sdate." 00:00:00' ";
		}

		$orderBy1	= "goods_cnt desc";
		$orderBy2	= "option_cnt desc";
		if	($order_by == 'price'){
			$orderBy1	= "goods_price desc";
			$orderBy2	= "option_price desc";
		}

		$sql	= "select
						oi.goods_seq		as goods_seq,
						sum(oio.ea)			as option_cnt,
						sum(oio.price*oio.ea)as option_price,
						oio.option1			as option1,
						oio.option2			as option2,
						oio.option3			as option3,
						oio.option4			as option4,
						oio.option5			as option5,
						gs.stock			as stock,
						gs.badstock			as badstock,
						gs.reservation15	as reservation15,
						gs.reservation25	as reservation25
					from
						fm_order					as o,
						fm_order_item				as oi,
						fm_order_item_option		as oio,
						fm_goods					as g,
						fm_goods_option				as go
						LEFT JOIN fm_goods_supply	as gs
						on go.option_seq = gs.option_seq
					where
						oio.item_seq = oi.item_seq and
						oi.order_seq = o.order_seq and
						oi.goods_seq = g.goods_seq and
						g.goods_seq = go.goods_seq and
						oio.option1 = go.option1 and
						oio.option2 = go.option2 and
						oio.option3 = go.option3 and
						oio.option4 = go.option4 and
						oio.option5 = go.option5 and
						o.deposit_yn = 'y' and
						o.step between '15' and '75'
						and g.goods_seq	= '".$goods_seq."'
						".$addWhere."
					group by
						oi.goods_seq, oio.option1, oio.option2, oio.option3, oio.option4, oio.option5
					order by ".$orderBy2;

		$query	= $this->db->query($sql);
		$data	= $query->result_array();

		return $data;
	}

	public function get_sales_payment_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		/* 데이터 추출 : 주문금액, 결제금액, 마일리지사용, 할인금액, 배송비, 매입금액 */
		$addWhere	= " and year(a.deposit_date)='".$year."' ";
		if(!empty($month))
			$addWhere	.= " and month(a.deposit_date)='".$month."' ";
		if	(is_array($sitetype) && count($sitetype) > 0){
			$addWhere	.= " and a.sitetype in ('".implode("','",$sitetype)."') ";
		}

		$query	= "select
					a.order_seq as order_seq
					,year(a.deposit_date) as stats_year
					,month(a.deposit_date) as stats_month
					,a.pg,a.payment as payment
					,IF(a.pg='paypal','paypal',IF(a.pg='talkbuy','talkbuy',IF(a.pg='kakaopay','kakaopay',if(a.pg='npay','npay',IF(a.pg = 'payco', 'payco', if(a.pg='eximbay','eximbay','')))))) as pgs
					,day(a.deposit_date) as stats_day
					,sum(a.settleprice) as month_settleprice_sum
					,count(order_seq) as month_count_sum
				from fm_order as a
				where a.deposit_yn='y' and a.step between '15' and '85'
				".$addWhere."
				group by a.payment, a.pg";
				//group by a.payment"; 카드결제 구분이 되지지 않아서 group by 구문에 a.pg 추가 :: 2018-09-12 lkh
				//group by a.payment, lgs"; 2018-04-19 group by 구문에서 pgs 제외 (npay_point 가 나와야 하기 때문에)
		return $this->db->query($query);
	}

	public function get_sales_platform_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		/* 데이터 추출 : 주문금액, 결제금액, 마일리지사용, 할인금액, 배송비, 매입금액 */
		$addWhere	= " and year(a.deposit_date)='".$year."' ";
		if(!empty($month))
			$addWhere	.= " and month(a.deposit_date)='".$month."' ";
		if	(is_array($sitetype) && count($sitetype) > 0){
			$addWhere	.= " and a.sitetype in ('".implode("','",$sitetype)."') ";
		}

		$query = "select
					sitetype,
					sum(settleprice) as settleprice_sum,
					count(*) as count_sum
				from fm_order as a
				where a.deposit_yn='y' and a.step between '15' and '75'
				".$addWhere."
				group by sitetype";

		return $this->db->query($query);
	}

	public function get_sales_etc_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		$addWhere	.= " and year(a.deposit_date) = '".$year."' ";
		if(!empty($month))
			$addWhere	.= " and month(a.deposit_date)='".$month."' ";
		if	(is_array($sitetype) && count($sitetype) > 0){
			$addWhere	.= " and a.sitetype in ('".implode("','",$sitetype)."') ";
		}

		if	($q_type == 'sexage'){

			$addWhere	.= " and b.buyer_age is not null ";
			$addWhere	.= " and b.buyer_sex is not null ";

			/* 데이터 추출 : 주문금액, 결제금액, 마일리지사용, 할인금액, 배송비, 매입금액 */
			$query	= "select
						a.order_seq as order_seq
						,year(a.deposit_date) as stats_year
						,month(a.deposit_date) as stats_month
						,day(a.deposit_date) as stats_day
						,case
							when b.buyer_age < 20 then '10대 이하'
							when b.buyer_age < 30 then '20대'
							when b.buyer_age < 40 then '30대'
							when b.buyer_age < 50 then '40대'
							when b.buyer_age < 60 then '50대'
							when b.buyer_age >= 60 then '60대 이상'
						end as buyer_age
						,case
							when b.buyer_sex = 'male' then '남'
							when b.buyer_sex = 'female' then '여'
						end as buyer_sex
						,sum(a.settleprice) as month_settleprice_sum
						,count(*) as month_count_sum
					from fm_order as a
						inner join fm_order_stats as b on a.order_seq=b.order_seq
					where a.deposit_yn='y' and a.step between '15' and '75'
					".$addWhere."
					group by buyer_age,buyer_sex";
		}elseif($q_type == 'location'){
			/* 데이터 추출 : 주문금액, 결제금액, 마일리지사용, 할인금액, 배송비, 매입금액 */
			$query = "select
						a.order_seq as order_seq
						,year(a.deposit_date) as stats_year
						,month(a.deposit_date) as stats_month
						,day(a.deposit_date) as stats_day
						,substring(recipient_address,1,2) as location
						,sum(a.settleprice) as month_settleprice_sum
						,count(*) as month_count_sum
					from fm_order as a
					where a.deposit_yn='y' and a.step between '15' and '75'
					".$addWhere."
					group by location";
		}

		return $this->db->query($query);
	}

	public function get_sales_referer_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($dateSel_type == 'daily'){
			$selVal			= "SUBSTRING(o.deposit_date, 9, 2) as `date`, ";
			$scDate			= $year . '-' . str_pad($month, 2, "0", STR_PAD_LEFT);
			$addBy			= " `date` ";
		}else{
			$selVal			= "SUBSTRING(o.deposit_date, 6, 2) as `date`, ";
			$scDate			= $year;
			$addBy			= " `date` ";
		}

		if	($dateSel_type == '30days'){
			$selVal			= "SUBSTRING(o.deposit_date, 9, 2) as `date`, ";
			$addBy			= " `date` ";
			$sdate			= date('Y-m-d', strtotime('-29 day')) . ' 00:00:00';
			$edate			= date('Y-m-d') . ' 23:59:59';
			$addWhere		= " and o.deposit_date between '".$sdate."' and '".$edate."' ";
		}else if($dateSel_type == '10days'){
			$selVal			= 'os.referer as referer_url, ';
			$addBy			= '';
			$ordBy			= " `cnt` desc ";
			$sdate			= date('Y-m-d', strtotime('-9 day')) . ' 00:00:00';
			$edate			= date('Y-m-d') . ' 23:59:59';
			$addWhere		= " and o.deposit_date between '".$sdate."' and '".$edate."' ";
		}else if($dateSel_type == 'main10days'){
			$selVal			= 'os.referer as referer_url, ';
			$addBy			= '';
			$ordBy			= " `cnt` desc ";
			$sdate			= date('Y-m-d', strtotime('-10 day')) . ' 00:00:00';
			$edate			= date('Y-m-d', strtotime('-1 day')) . ' 23:59:59';
			$addWhere		= " and o.deposit_date between '".$sdate."' and '".$edate."' ";
		}else{
			$addWhere		= " and o.deposit_date like '".$scDate."%' ";
		}

		$limitSql			= ($limit) ? " limit " . $limit : '';

		if	($referer_name){
			$addWhere .= " and IF(rg.referer_group_no>0, rg.referer_group_name,
							IF(LENGTH(os.referer)>0,'기타','직접입력')) = '" . $referer_name . "' ";
		}

		if($addBy!=''){
			$addBy	= ", ".$addBy;
			$ordBy	= " `date` ";
		}

		$query	= "select
					".$selVal."
					count(*)			as cnt,
					sum(settleprice)	as price,
					IF(rg.referer_group_no>0, rg.referer_group_name,
						IF(LENGTH(os.referer)>0,'기타','직접입력')) as referer_name
				from
					fm_order					as o
					LEFT JOIN fm_order_stats	as os
						on o.order_seq = os.order_seq
					LEFT JOIN fm_referer_group	as rg
						on os.referer_domain = rg.referer_group_url
				where
					o.order_seq > 0 and
					o.deposit_yn = 'y' and o.step between '15' and '75'
					".$addWhere."
				group by referer_name " . $addBy . "
				order by " . $ordBy . $limitSql;

		return $this->db->query($query);
	}

	public function get_sales_category_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		##
		$tb_type	= "C";
		if	($sc_type == 'brand'){
			$tb_type	= "B";
		}

		##
		$dates		= "month_date	as date, ";
		$addWhere	.= " and deposit_date like '".$year."%' ";
		if	($dateSel_type == 'daily'){
			$dates		= "day_date	as date, ";
			$addWhere	.= " and deposit_date like '".$year."-".str_pad($month, 2, "0", STR_PAD_LEFT)."%' ";
		}

		$query	= "select t1.total_cnt, t1.total_price,
						t2.category_code, t2.category_name, t2.date, t2.cnt, t2.price
					from
						(
							select
								category_code,
								sum(cnt) as total_cnt,
								sum(price) as total_price
							from
								fm_accumul_stats_category
							where t_type = '".$tb_type."'
								".$addWhere."
							group by category_code
						)	as t1,
						(
							select
								category_name,
								category_code,
								".$dates."
								sum(cnt) as cnt,
								sum(price) as price
							from
								fm_accumul_stats_category
							where t_type = '".$tb_type."'
								".$addWhere."
							group by category_code, date
						)	as t2
					where t1.category_code = t2.category_code
					order by t1.total_cnt desc, t2.date, t1.category_code ";

		return $this->db->query($query);
	}

	public function get_member_basic_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if		($date_type == 'hour'){
			$regDate	= $year.'-'.$month.'-'.$day;
			$regDate	= date('Y-m-d', strtotime($regDate));
			$dateFld	= "SUBSTRING(regist_date, 12, 2)";
		}elseif	($date_type == 'daily'){
			$regDate	= $year.'-'.str_pad($month, 2, '0', STR_PAD_LEFT);
			$dateFld	= "SUBSTRING(regist_date, 9, 2)";
		}else{
			$regDate	= $year;
			$dateFld	= "SUBSTR(regist_date, 6, 2)";
		}

		$query	= "select ".$dateFld." as date, count(*) as cnt
					from fm_member_stats
					where regist_date like '".$regDate."%'
					group by date";

		return $this->db->query($query);
	}

	public function get_member_referer_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		##
		if		($date_type == 'month'){
			$sfld		= "SUBSTRING(ms.regist_date, 6, 2) as date ";
			$addWhere	.= " and ms.regist_date like '".$year."%' ";
			$fld		= "date, referer_name";
			$orderby	= " order by cnt desc, date";
		}elseif	($date_type == 'daily'){
			$date		= $year.'-'.$month.'-01';
			$date		= date('Y-m', strtotime($date));
			$sfld		= "SUBSTRING(ms.regist_date, 9, 2) as date ";
			$addWhere	.= " and ms.regist_date like '".$date."%' ";
			$fld		= "date, referer_name";
			$orderby	= " order by cnt desc, date";
		}elseif ($date_type == '30days'){
			$sdate		= date('Y-m-d', strtotime('-29 day'));
			$edate		= date('Y-m-d');
			$addWhere	.= " and ms.regist_date between '".$sdate." 00:00:00' and '".$edate." 23:59:59' ";
			$sfld		= "SUBSTRING(ms.regist_date, 9, 2) as date ";
			$fld		= "date";
			$orderby	= " order by ms.regist_date ";
		}elseif	($sDate && $eDate){
			$sfld		= "SUBSTRING(ms.regist_date, 9, 2) as date ";
			$addWhere	.= " and ms.regist_date between '".$sDate." 00:00:00' and '".$eDate." 23:59:59' ";
			$fld		= "date, referer_name";
			$orderby	= " order by cnt desc, date";
		}else{
			if	($date){
				$date		= date('Y-m-d', strtotime($date));
				$addWhere	.= " and ms.regist_date like '".$date."%' ";
				$fld = $sfld = "referer";
			}
		}

		if	($referer_name){
			$addWhere .= " and IF(rg.referer_group_no>0, rg.referer_group_name,
							IF(LENGTH(ms.referer)>0,'기타','직접입력')) = '" . $referer_name . "' ";
		}

		$query	= "select ".$sfld.", ms.regist_date as regist_date, count(*) as cnt,
						IF(rg.referer_group_no>0, rg.referer_group_name,
							IF(LENGTH(ms.referer)>0,'기타','직접입력')) as referer_name
					from fm_member_stats	as ms
					LEFT JOIN fm_referer_group	as rg
						on ms.referer_domain = rg.referer_group_url
					where ms.member_stats_seq > 0
					".$addWhere."
					group by ".$fld.$orderby;

		return $this->db->query($query);
	}

	public function get_member_platform_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($month){
			$date	= date('Y-m', strtotime($year.'-'.$month.'-01'));
			$query	= "select platform, count(*) as cnt
						from fm_member_stats
						where regist_date like '".$date."%'
						group by platform";
		}else{
			$query	= "select platform, count(*) as cnt
						from fm_member_stats
						where regist_date like '".$year."%'
						group by platform";
		}

		return $this->db->query($query);
	}

	public function get_member_rute_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($month){
			$date	= date('Y-m', strtotime($year.'-'.$month.'-01'));
			$query	= "select rute, count(*) as cnt
						from fm_member
						where regist_date like '".$date."%'
						group by rute";
		}else{
			$query	= "select rute, count(*) as cnt
						from fm_member
						where regist_date like '".$year."%'
						group by rute";
		}

		return $this->db->query($query);
	}

	public function get_summary_visitor_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if		($stats_type == 'total'){
			$query	= "select sum(count_sum) as total from fm_stats_visitor_count
						where count_type = 'visit'
						and stats_date between '".$sDate."' and '".$eDate."'
						group by stats_year";
		}elseif	($stats_type == 'referer'){
			$query	= "select IF(rg.referer_group_no>0, rg.referer_group_name,
						IF(LENGTH(vr.referer)>0,'기타','직접입력')) as referer_name,
						sum(vr.count) as cnt
						from fm_stats_visitor_referer	as vr
						LEFT JOIN fm_referer_group		as rg
							on vr.referer_domain = rg.referer_group_url
						where vr.stats_date between '".$sDate."' and '".$eDate."'
						group by referer_name
						order by cnt desc
						LIMIT 3";
		}else{
			$query	= "select stats_date, stats_month, stats_day, count_sum from fm_stats_visitor_count
						where count_type = 'visit'
						and stats_date between '".$sDate."' and '".$eDate."'
						order by stats_date";
		}

		return $this->db->query($query);
	}

	public function get_summary_member_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if		($stats_type == 'total'){
			$query	= "select count(*) total from fm_member_stats
						where regist_date between '".$sDate." 00:00:00'
						and '".$eDate." 23:59:59'";
		}elseif	($stats_type == 'referer'){
			$query	= "select IF(rg.referer_group_no>0, rg.referer_group_name,
						IF(LENGTH(ms.referer)>0,'기타','직접입력')) as referer_name,
						count(*) as cnt
						from fm_member_stats 			as ms
						LEFT JOIN fm_referer_group		as rg
							on ms.referer_domain = rg.referer_group_url
						where ms.regist_date between '".$sDate." 00:00:00' and '".$eDate." 23:59:59'
						group by referer_name
						order by cnt desc
						LIMIT 3";
		}else{
			$query	= "select LEFT(regist_date, 10) as date, count(*) cnt from fm_member_stats
						where regist_date between '".$sDate." 00:00:00'
						and '".$eDate." 23:59:59'
						group by date order by regist_date";
		}

		return $this->db->query($query);
	}

	public function get_summary_order_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if		($stats_type == 'total'){
			$query	= "
						SELECT
							SUM(settleprice_sum+emoney_use_sum+cash_use_sum) AS total
						FROM
							fm_accumul_sales_mdstats
						WHERE
							deposit_date BETWEEN '".$sDate." 00:00:00'
							AND '".$eDate." 23:59:59'
					";
		}elseif	($stats_type == 'referer'){
			$query	= "
						SELECT
							IF(rg.referer_group_no>0, rg.referer_group_name,
							IF(LENGTH(os.referer)>0,'기타','직접입력')) AS referer_name,
							SUM(settleprice_sum+emoney_use_sum+cash_use_sum) AS price
						FROM
							fm_order_stats 							AS os
							INNER JOIN fm_accumul_sales_mdstats		AS o
								on os.order_seq = o.order_seq
							LEFT JOIN fm_referer_group		AS rg
								on os.referer_domain = rg.referer_group_url
						WHERE
							o.deposit_date between '".$sDate." 00:00:00'
							AND '".$eDate." 23:59:59'
						GROUP BY referer_name
						ORDER BY price desc
						LIMIT 3
					";
		}else{
			$this->load->helper('accountall');
			$_accountSettings			= getAccountSetting();
			$accountAllStatsV2			= $_accountSettings['accountall_stats_v2'];		// 통계 개선 2019-06-19 by hed
			// 통계개선 패치 이후 10일이상 경과 했을 경우 변경된 통계 데이터로 출력
			if($accountAllStatsV2 && (date("Ymd", $accountAllStatsV2) < date("Ymd", strtotime("-10 day")))){
				// 판매금액 조회 기준을 정산 데이터를 기준으로 변경 by hed
				$this->load->model("dailystatsmodel");
				$query = "
					SELECT
						daily_date AS date
						, daily_sales_price AS price
					FROM
						".$this->dailystatsmodel->table_sales_price."
					WHERE 
						daily_date BETWEEN '".$sDate."' AND '".$eDate."'
				";
			}else{
				$query	= "
							SELECT
								LEFT(deposit_date, 10) AS date, SUM(settleprice_sum+emoney_use_sum+cash_use_sum) AS price
							FROM
								fm_accumul_sales_mdstats
							WHERE
								deposit_date BETWEEN '".$sDate." 00:00:00'
								AND '".$eDate." 23:59:59'
							GROUP BY date ORDER BY deposit_date
						";
			}
		}

		return $this->db->query($query);
	}

	public function get_summary_goods_stats($params){
		
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if($o_type)	$where = "AND o_type = '".$o_type."'";
		if(!$limit) $limit = 3;

		if (isset($provider_seq)) {

			$where = " AND g.provider_seq ='".$provider_seq."'";
			$join  = " INNER 
						JOIN fm_goods as g 
						ON s.goods_seq = g.goods_seq";
		} 

		// 상품 매출 수집통계에서 수집 :: 2015-09-18 lwh
		if	($stats_type == 'total')	{
			$query	= "
				SELECT
					*
				FROM
					fm_accumul_stats_sales
				WHERE
					deposit_ymd BETWEEN '".$sDate."' AND '".$eDate."'
					".$where."
			";
		}	else	{
			$query	= "
				SELECT
					SUM(IFNULL(price, 0) * IFNULL(ea, 0)) AS price,
					s.order_goods_name AS goods_name,
					SUM( ea ) AS tot_ea,
					s.goods_seq
				FROM
					fm_accumul_stats_sales as s
					".$join."
				WHERE
					s.deposit_ymd BETWEEN '".$sDate."' AND '".$eDate."'
					".$where."
				GROUP BY goods_seq ORDER BY price DESC
				LIMIT ".$limit."
			";
		}
		return $this->db->query($query);
	}

	public function get_summary_category_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		// 상품 매출 수집통계에서 수집 :: 2015-09-18 lwh
		$query	= "
				SELECT
					SUM(IFNULL(ac.price, 0) * IFNULL(ac.ea, 0)) AS price,
					(select title from fm_category where category_code = cl.category_code limit 1) as title
				FROM
					fm_accumul_stats_sales			AS ac
					INNER JOIN fm_category_link		AS cl
						ON ( ac.goods_seq = cl.goods_seq AND cl.link = 1 )
				WHERE
					deposit_ymd BETWEEN '".$sDate."' AND '".$eDate."'
				GROUP BY cl.category_code
				ORDER BY ac.price DESC
				LIMIT 3
				";
		/*
		$query	= "select
						sum(ifnull(( select sum(price * ea) from fm_order_item_option where item_seq = oi.item_seq ), 0) +
						ifnull(( select sum(price * ea) from fm_order_item_suboption where item_seq = oi.item_seq ), 0) ) as price,
						(select title from fm_category where category_code = cl.category_code limit 1) as title
					from
						fm_order					as o
						INNER JOIN fm_order_item	as oi 	on o.order_seq = oi.order_seq
						INNER JOIN fm_category_link	as cl 	on ( oi.goods_seq = cl.goods_seq and cl.link = 1 )
					where o.deposit_yn = 'y' and o.step between '15' and '75' and
						o.deposit_date between '".$sDate." 00:00:00' and '".$eDate." 23:59:59'
					group by cl.category_code order by price desc
					limit 3";
		*/
		return $this->db->query($query);
	}

	public function get_summary_brand_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		// 상품 매출 수집통계에서 수집 :: 2015-09-18 lwh
		$query	= "
				SELECT
					SUM(IFNULL(ac.price, 0) * IFNULL(ac.ea, 0)) AS price,
					(select title from fm_brand where category_code = bl.category_code limit 1) as title
				FROM
					fm_accumul_stats_sales			AS ac
					INNER JOIN fm_brand_link		AS bl
						ON ( ac.goods_seq = bl.goods_seq AND bl.link = 1 )
				WHERE
					deposit_ymd BETWEEN '".$sDate."' AND '".$eDate."'
				GROUP BY bl.category_code
				ORDER BY ac.price DESC
				LIMIT 3
				";
		/*
		$query	= "select
					(ifnull(( select sum(price * ea) from fm_order_item_option where item_seq = oi.item_seq ), 0) +
					ifnull(( select sum(price * ea) from fm_order_item_suboption where item_seq = oi.item_seq ), 0) ) as price,
					(select title from fm_brand where category_code = bl.category_code limit 1) as title
				from
					fm_order					as o
					INNER JOIN fm_order_item	as oi 	on o.order_seq = oi.order_seq
					INNER JOIN fm_brand_link	as bl 	on ( oi.goods_seq = bl.goods_seq and bl.link = 1 )
				where o.deposit_yn = 'y' and o.step between '15' and '75' and
					o.deposit_date between '".$sDate." 00:00:00' and '".$eDate." 23:59:59'
				group by bl.category_code order by price desc
				limit 3";
		*/
		return $this->db->query($query);
	}

	public function get_summary_cart_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		$query	= "select count(*) as cnt, goods_name
					from fm_cart_stats
					where regist_ymd between '".$sDate."' and '".$eDate."'
					group by goods_name order by cnt desc
					limit 3";

		return $this->db->query($query);
	}

	public function get_summary_wish_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		$query	= "select count(*) as cnt, goods_name
					from fm_wish_stats
					where regist_ymd between '".$sDate."' and '".$eDate."'
					group by goods_name order by cnt desc
					limit 3";

		return $this->db->query($query);
	}

	public function get_summary_keyword_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		$query	= "select count(*) as cnt, keyword
					from fm_search_stats
					where regist_date between '".$sDate." 00:00:00'
					and '".$eDate." 23:59:59'
					group by keyword order by cnt desc
					limit 3";

		return $this->db->query($query);
	}

	public function get_current_cart_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($type == 'brand'){
			$addFrom	= "INNER JOIN fm_brand_link		as bl
							on ( c.goods_seq = bl.goods_seq
								and bl.category_code = '".$category_code."' ) ";
		}elseif	($type == 'category'){
			$addFrom	= "INNER JOIN fm_category_link		as cl
							on ( c.goods_seq = cl.goods_seq
								and cl.category_code = '".$category_code."' ) ";
		}

		if	($get_type == 'total'){
			$query	= "select count(*) as cnt
						from fm_cart	as c
							INNER JOIN fm_goods	as g
								on c.goods_seq = g.goods_seq
							".$addFrom."
						order by cnt desc ";
		}else{
			$query	= "select count(*) as cnt, g.goods_name, g.goods_seq
						from fm_cart	as c
							INNER JOIN fm_goods	as g
								on c.goods_seq = g.goods_seq
							".$addFrom."
						group by c.goods_seq
						order by cnt desc
						limit 3";
		}

		return $this->db->query($query);
	}

	public function get_current_wish_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($type == 'brand'){
			$addFrom	= "INNER JOIN fm_brand_link		as bl
							on ( gw.goods_seq = bl.goods_seq
								and bl.category_code = '".$category_code."' ) ";
		}elseif	($type == 'category'){
			$addFrom	= "INNER JOIN fm_category_link		as cl
							on ( gw.goods_seq = cl.goods_seq
								and cl.category_code = '".$category_code."' ) ";
		}

		if	($get_type == 'total'){
			$query	= "select count(*) as cnt
						from fm_goods_wish	as gw
							INNER JOIN fm_goods	as g
								on gw.goods_seq = g.goods_seq
							".$addFrom."
						order by cnt desc ";
		}else{
			$query	= "select count(*) as cnt, g.goods_name, g.goods_seq
						from fm_goods_wish	as gw
							INNER JOIN fm_goods	as g
								on gw.goods_seq = g.goods_seq
							".$addFrom."
						group by gw.goods_seq
						order by cnt desc
						limit 3";
		}

		return $this->db->query($query);
	}

	public function get_current_like_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($type == 'brand'){
			$addFrom	= "INNER JOIN fm_brand_link		as bl
							on ( fbl.goods_seq = bl.goods_seq
								and bl.category_code = '".$category_code."' ) ";
		}elseif	($type == 'category'){
			$addFrom	= "INNER JOIN fm_category_link		as cl
							on ( fbl.goods_seq = cl.goods_seq
								and cl.category_code = '".$category_code."' ) ";
		}

		if	($get_type == 'total'){
			$query	= "select count(*) as cnt
						from fm_goods_fblike	as fbl
							INNER JOIN fm_goods	as g
								on fbl.goods_seq = g.goods_seq
							".$addFrom."
						order by cnt desc ";
		}else{
			$query	= "select count(*) as cnt, g.goods_name, g.goods_seq
						from fm_goods_fblike	as fbl
							INNER JOIN fm_goods	as g
								on fbl.goods_seq = g.goods_seq
							".$addFrom."
						group by fbl.goods_seq
						order by cnt desc
						limit 3";
		}

		return $this->db->query($query);
	}

	public function get_current_restock_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($catenbrand == 'brand'){
			$addFrom	= "INNER JOIN fm_brand_link		as bl
							on ( rsn.goods_seq = bl.goods_seq
								and bl.category_code = '".$category_code."' ) ";
		}elseif	($type == 'category'){
			$addFrom	= "INNER JOIN fm_category_link		as cl
							on ( rsn.goods_seq = cl.goods_seq
								and cl.category_code = '".$category_code."' ) ";
		}

		if	($get_type == 'total'){
			$query	= "select count(*) as cnt
						from fm_goods_restock_notify 	as rsn
							INNER JOIN fm_goods	as g
								on rsn.goods_seq = g.goods_seq
							".$addFrom."
						where rsn.notify_status = 'none' ";
		}else{
			$query	= "select count(*) as cnt, g.goods_name, g.goods_seq
						from fm_goods_restock_notify 	as rsn
							INNER JOIN fm_goods	as g
								on rsn.goods_seq = g.goods_seq
							".$addFrom."
						where rsn.notify_status = 'none'
						group by rsn.goods_seq
						order by cnt desc
						limit 3";
		}

		return $this->db->query($query);
	}

	public function get_statistic_order_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		switch($date_term){
			case 'today':
				$keyFld		= "SUBSTRING(o.deposit_date, 12, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d')." 00:00:00'
								and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '7days':
				$keyFld		= "SUBSTRING(o.deposit_date, 9, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-6 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '10days':
				$keyFld		= "SUBSTRING(o.deposit_date, 9, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-9 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '30days':
				$keyFld		= "SUBSTRING(o.deposit_date, 9, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-29 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			default:
			case 'yesterday':
				$keyFld		= "SUBSTRING(o.deposit_date, 12, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-1 day'))."
								00:00:00' and '".date('Y-m-d', strtotime('-1 day'))." 23:59:59' ";
			break;
		}

		if	($goods_seq){
			$addOn	= " and oi.goods_seq = '".$goods_seq."' ";
		}


		if	($q_type == 'rank'){
			$query	= "select o.order_seq, (oio.price + oiso.price) as price,
							g.goods_seq, g.goods_name
						from fm_order as o
							INNER JOIN fm_order_item as oi
								on ( o.order_seq = oi.order_seq )
							INNER JOIN fm_goods as g
								on ( oi.goods_seq = g.goods_seq )
							INNER JOIN (select item_seq, item_option_seq as option_seq, sum(price*ea) as price from fm_order_item_option group by item_seq)  as oio on ( oi.item_seq = oio.item_seq )
							LEFT JOIN (select option_seq, sum(price*ea) price
									from fm_order_item_suboption group by option_seq)  as oiso
								on ( oio.option_seq = oiso.option_seq )
						where
							o.deposit_yn = 'y' and
							o.step between '15' and '75'
							".$addWhere."
						group by oi.goods_seq
						order by price desc
						limit 2";
		}else{
			$query	= "select ".$keyFld." as dates, o.order_seq, o.settleprice as settleprice,
							oio.price as price, g.goods_seq, g.goods_name,
							IF(rg.referer_group_no>0, rg.referer_group_name,
								IF(LENGTH(os.referer)>0,'기타','직접입력')) as referer_name
						from fm_order as o
							INNER JOIN fm_order_item as oi
								on ( o.order_seq = oi.order_seq ".$addOn." )
							INNER JOIN (select item_seq, sum(price*ea) as price from fm_order_item_option group by item_seq)  as oio on ( oi.item_seq = oio.item_seq )
							INNER JOIN fm_goods as g
								on ( oi.goods_seq = g.goods_seq )
							LEFT JOIN fm_order_stats as os
								on ( o.order_seq = os.order_seq )
							LEFT JOIN fm_referer_group as rg
								on os.referer_domain = rg.referer_group_url
						where
							o.deposit_yn = 'y' and
							o.step between '15' and '75'
							".$addWhere."
						group by o.order_seq, g.goods_seq, dates";
		}

		return $this->db->query($query);
	}


	public function get_statistic_category_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		switch($date_term){
			case 'today':
				$keyFld		= "SUBSTRING(o.deposit_date, 12, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d')." 00:00:00'
								and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '7days':
				$keyFld		= "SUBSTRING(o.deposit_date, 9, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-6 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '30days':
				$keyFld		= "SUBSTRING(o.deposit_date, 9, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-29 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			default:
			case 'yesterday':
				$keyFld		= "SUBSTRING(o.deposit_date, 12, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-1 day'))."
								00:00:00' and '".date('Y-m-d', strtotime('-1 day'))." 23:59:59' ";
			break;
		}

		if	($catenbrand == 'brand'){
			$addFrom	= "INNER JOIN fm_brand_link		as lk
								on ( oi.goods_seq = lk.goods_seq and lk.link = 1 )
							INNER JOIN fm_brand			as c
								on lk.category_code = c.category_code ";

			$addFrom2	= "INNER JOIN fm_brand_link		as lk
							on ( oi.goods_seq = lk.goods_seq
								and lk.category_code in ('".$category_code."', '".$first."' ) )
							INNER JOIN fm_brand			as c
								on lk.category_code = c.category_code ";
		}else{
			$addFrom	= "INNER JOIN fm_category_link	as lk
								on ( oi.goods_seq = lk.goods_seq and lk.link = 1 )
							INNER JOIN fm_category		as c
								on lk.category_code = c.category_code ";

			$addFrom2	= "INNER JOIN fm_category_link	as lk
							on ( oi.goods_seq = lk.goods_seq
								and lk.category_code in ('".$category_code."', '".$first."' ) )
							INNER JOIN fm_category		as c
								on lk.category_code = c.category_code ";
		}

		if	($q_type == 'first'){
			$addWhere	.= " and lk.category_code != '".$category_code."' ";
			$query		= "select c.category_code
							from fm_order as o
								INNER JOIN fm_order_item as oi
									on  o.order_seq = oi.order_seq
								INNER JOIN fm_order_item_option as oio
									on oi.item_seq = oio.item_seq
								".$addFrom."
							where
								o.deposit_yn = 'y' and
								o.step between '15' and '75'
								".$addWhere."
							group by c.category_code
							order by price desc
							limit 1";
		}else{
			$query	= "select ".$keyFld." as dates, c.title, sum(oio.price) as price
						from fm_order as o
							INNER JOIN fm_order_item as oi
								on  o.order_seq = oi.order_seq
							INNER JOIN fm_order_item_option as oio
								on ( oi.item_seq = oio.item_seq )
							".$addFrom2."
						where
							o.deposit_yn = 'y' and
							o.step between '15' and '75'
							".$addWhere."
						group by c.category_code, dates
						order by price desc, title";
		}

		return $this->db->query($query);
	}

	public function get_statistic_referer_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		switch($date_term){
			case 'today':
				$keyFld		= "SUBSTRING(o.deposit_date, 12, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d')." 00:00:00'
								and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '7days':
				$keyFld		= "SUBSTRING(o.deposit_date, 9, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-6 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '30days':
				$keyFld		= "SUBSTRING(o.deposit_date, 9, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-29 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			default:
			case 'yesterday':
				$keyFld		= "SUBSTRING(o.deposit_date, 12, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-1 day'))."
								00:00:00' and '".date('Y-m-d', strtotime('-1 day'))." 23:59:59' ";
			break;
		}

		$query	= "select ".$keyFld." as dates, sum(oio.price) as price,
						IF(rg.referer_group_no>0, rg.referer_group_name,
						IF(LENGTH(os.referer)>0,'기타','직접입력')) as referer_name
					from fm_order as o
						INNER JOIN fm_order_item as oi
							on ( o.order_seq = oi.order_seq and oi.goods_seq = '".$goods_seq."' )
						INNER JOIN fm_order_item_option as oio
							on ( oi.item_seq = oio.item_seq )
						INNER JOIN fm_order_stats as os
							on ( o.order_seq = os.order_seq )
						LEFT JOIN fm_referer_group as rg
							on os.referer_domain = rg.referer_group_url
					where
						o.deposit_yn = 'y' and
						o.step between '15' and '75'
						".$addWhere."
					group by rg.referer_group_cd , dates";

		return $this->db->query($query);
	}


	public function get_statistic_etc_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		switch($date_term){
			case 'today':
				$keyFld		= "SUBSTRING(o.deposit_date, 12, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d')." 00:00:00'
								and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '7days':
				$keyFld		= "SUBSTRING(o.deposit_date, 9, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-6 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '30days':
				$keyFld		= "SUBSTRING(o.deposit_date, 9, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-29 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			default:
			case 'yesterday':
				$keyFld		= "SUBSTRING(o.deposit_date, 12, 2)";
				$addWhere	= " and o.deposit_date between '".date('Y-m-d', strtotime('-1 day'))."
								00:00:00' and '".date('Y-m-d', strtotime('-1 day'))." 23:59:59' ";
			break;
		}

		$query	= "select
						case
							when os.buyer_age < 20 then '10대 이하'
							when os.buyer_age < 30 then '20대'
							when os.buyer_age < 40 then '30대'
							when os.buyer_age < 50 then '40대'
							when os.buyer_age < 60 then '50대'
							when os.buyer_age >= 60 then '60대 이상'
						end as buyer_age,
						case
							when os.buyer_sex = 'male' then '남'
							when os.buyer_sex = 'female' then '여'
						end as buyer_sex,
						os.buyer_area,
						count(*) as cnt
					from fm_order as o
						INNER JOIN fm_order_item as oi
							on ( o.order_seq = oi.order_seq and oi.goods_seq = '".$goods_seq."' )
						INNER JOIN fm_order_stats as os
							on ( o.order_seq = os.order_seq )
					where
						o.deposit_yn = 'y' and
						o.step between '15' and '75'
						".$addWhere."
					group by buyer_area, buyer_age, buyer_sex";

		return $this->db->query($query);
	}

	public function get_statistic_cart_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		switch($date_term){
			case 'today':
				$keyFld		= "SUBSTRING(regist_date, 12, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d')." 00:00:00'
								and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '7days':
				$keyFld		= "SUBSTRING(regist_date, 9, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-6 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '10days':
				$keyFld		= "SUBSTRING(regist_date, 9, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-9 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			case 'main10days':
				$keyFld		= "SUBSTRING(regist_date, 9, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-10 day'))."
								00:00:00' and '".date('Y-m-d', strtotime('-1 day'))." 23:59:59' ";
			break;
			case '30days':
				$keyFld		= "SUBSTRING(regist_date, 9, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-29 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			default:
			case 'yesterday':
				$keyFld		= "SUBSTRING(regist_date, 12, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-1 day'))."
								00:00:00' and '".date('Y-m-d', strtotime('-1 day'))." 23:59:59' ";
			break;
		}

		if	($q_type == 'rank'){
			$query	= "select ".$keyFld." as dates, count(*) cnt,
						goods_seq, goods_name
						from fm_cart_stats
						where goods_seq > 0 ".$addWhere."
						group by goods_seq
						order by cnt desc
						limit 2";
		}else{
			$query	= "select ".$keyFld." as dates, count(*) cnt
						from fm_cart_stats
						where goods_seq = '".$goods_seq."' ".$addWhere."
						group by dates
						order by regist_date";
		}

		return $this->db->query($query);
	}

	public function get_statistic_wish_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		switch($date_term){
			case 'today':
				$keyFld		= "SUBSTRING(regist_date, 12, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d')." 00:00:00'
								and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '7days':
				$keyFld		= "SUBSTRING(regist_date, 9, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-6 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			case '10days':
				$keyFld		= "SUBSTRING(regist_date, 9, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-9 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			case 'main10days':
				$keyFld		= "SUBSTRING(regist_date, 9, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-10 day'))."
								00:00:00' and '".date('Y-m-d', strtotime('-1 day'))." 23:59:59' ";
			break;
			case '30days':
				$keyFld		= "SUBSTRING(regist_date, 9, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-29 day'))."
								00:00:00' and '".date('Y-m-d')." 23:59:59' ";
			break;
			default:
			case 'yesterday':
				$keyFld		= "SUBSTRING(regist_date, 12, 2)";
				$addWhere	= " and regist_date between '".date('Y-m-d', strtotime('-1 day'))."
								00:00:00' and '".date('Y-m-d', strtotime('-1 day'))." 23:59:59' ";
			break;
		}

		if	($q_type == 'rank'){
			$query	= "select ".$keyFld." as dates, count(*) cnt,
						goods_seq, goods_name
						from fm_wish_stats
						where goods_seq > 0 ".$addWhere."
						group by goods_seq
						order by cnt desc
						limit 2";
		}else{
			$query	= "select ".$keyFld." as dates, count(*) cnt
						from fm_wish_stats
						where goods_seq = '".$goods_seq."' ".$addWhere."
						group by dates
						order by regist_date";
		}

		return $this->db->query($query);
	}

	public function get_statistic_visitor_stats($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		$query	= "select stats_year, stats_month, stats_day,
						IF(rg.referer_group_no>0, rg.referer_group_name,
							IF(LENGTH(vr.referer)>0,'기타','직접입력')) as referer_name,
						sum(vr.count) as cnt,
						(select count_sum from fm_stats_visitor_count where stats_date = vr.stats_date and count_type = 'visit' limit 1 ) as vcnt
					from
						fm_stats_visitor_referer		as vr
						LEFT JOIN fm_referer_group		as rg
							on vr.referer_domain = rg.referer_group_url
					where vr.stats_date between '".$sDate."' and '".$eDate."'
					group by referer_name, stats_day
					order by stats_date";

		return $this->db->query($query);
	}

	public function get_selleradmin_statistic_json($provider_seq)
	{
		$ago10day		=  strtotime('-10 day');
		$ago1day		=  strtotime('-1 day');
		$sDate			= date('Y-m-d', $ago10day);
		$eDate			= date('Y-m-d', $ago1day);
		$sDateTime		= date('Y-m-d 00:00:00', $ago10day);
		$eDateTime		= date('Y-m-d 23:59:59', $ago1day);

		$result['sDate']				= $sDate;
		$result['eDate']				= $eDate;
		$result['rank']['order']		= array();
		$result['rank']['cart']			= array();

		$params = array();
		$params['sDate']		= $sDate;
		$params['eDate']		= $eDate;
		$params['provider_seq']		= $provider_seq;
		$params['limit']		= 6;
		$query	= $this->statsmodel->get_summary_goods_stats($params);
		foreach($query->result_array() as $data){
			$data['tot_price'] = $data['price'];
			$result['rank']['order'][] = $data;
		}


		$query = "SELECT
						s.goods_seq,
						s.goods_name,
						sum(s.ea) as tot_ea
					FROM fm_cart_stats s, fm_goods g
					WHERE
					s.goods_seq=g.goods_seq
					AND	s.goods_seq > 0
					AND s.regist_ymd between ? AND ?
					AND g.provider_seq=?
					GROUP BY s.goods_seq
					ORDER BY tot_ea DESC
					LIMIT 6";
		$query = $this->db->query($query,array($sDateTime,$eDateTime,$provider_seq));
		foreach($query->result_array() as $data){
			$result['rank']['cart'][]	= $data;
		}

		$today = date("Y-m-d");
		$query = "
			SELECT 
				sum(opt.price * opt.ea) as price, o.order_seq
			FROM
				fm_order_item_option as opt 
				left join fm_order as o on o.order_seq=opt.order_seq
			WHERE 
					opt.provider_seq = {$provider_seq}
					and o.step BETWEEN  '25' AND  '75'
					AND o.deposit_yn = 'y'
					AND o.deposit_date BETWEEN  '{$today} 00:00:00' AND  '{$today} 23:59:59'
			GROUP BY o.order_seq
			union all
			SELECT 
				sum(sub.price * sub.ea) as price, o.order_seq
			FROM
				fm_order_item_suboption as sub 
				left join fm_order_item_option as opt on sub.order_seq=opt.order_seq and sub.item_seq=opt.item_seq and sub.item_option_seq=opt.item_option_seq
				left join fm_order as o on o.order_seq=opt.order_seq
			WHERE 
					opt.provider_seq = {$provider_seq}
					and o.step BETWEEN  '25' AND  '75'
					AND o.deposit_yn = 'y'
					AND o.deposit_date BETWEEN  '{$today} 00:00:00' AND  '{$today} 23:59:59'
			GROUP BY o.order_seq
			";

		
		$query = $this->db->query($query);
		$result['today_stat_order']['price'] = 0;
		$today_order_list = array();
		foreach($query->result_array() as $data)
		{
			$today_order_list[]						 = $data['order_seq'];
			$result['today_stat_order']['price']	+= $data['price'];
		}
        $result['today_stat_order']['cnt']		= count(array_unique($today_order_list));

		return $result;
	}

	public function get_main_statistic_json(){
		$ago10day		=  strtotime('-10 day');
		$ago1day		=  strtotime('-1 day');
		$sDate			= date('Y-m-d', $ago10day);
		$eDate			= date('Y-m-d', $ago1day);
		$sDateTime		= date('Y-m-d 00:00:00', $ago10day);
		$eDateTime		= date('Y-m-d 23:59:59', $ago1day);
		$dateRange		= getDatesFromRange($sDate,$eDate);

		$result['sDate']					= $sDate;
		$result['eDate']				= $eDate;
		$result['rank']['order']		= array();
		$result['rank']['cart']			= array();
		$result['rank']['wish']			= array();
		$result['rank']['provider']		= array();

		$params['sDate']		= $sDate;
		$params['eDate']		= $eDate;
		$query	= $this->statsmodel->get_summary_order_stats($params);
		foreach($query->result_array() as $data){
			$date_data[$data['date']] = $data['price'];
		}
		foreach($dateRange as $_date){
			if($date_data[$_date]){
				$result['day_stat'][$_date]['tot_price'] = $date_data[$_date];
			}else{
				$result['day_stat'][$_date]['tot_price'] = 0;
			}
		}

		if( !serviceLimit('H_FR') ){ // 무료몰불가 
			$params = array();
			$params['sDate']		= $sDate;
			$params['eDate']		= $eDate;
			$params['limit']		= 6;
			$query	= $this->statsmodel->get_summary_goods_stats($params);
			foreach($query->result_array() as $data){
				$data['tot_price'] = $data['price'];
				$result['rank']['order'][] = $data;
			}
		}

		/* url별 회원가입 통계 */
		$query = "select count(*) cnt,ms.referer_domain,if(rg.referer_group_name is null and ms.referer_domain !=  '', 'etc', rg.referer_group_name) as referer_group_name
		from fm_member_stats ms left join fm_referer_group rg on ms.referer_domain=rg.referer_group_url where ms.regist_date between ? AND ? group by ms.referer_domain order by cnt desc";
		$query = $this->db->query($query,array($sDateTime,$eDateTime));
		foreach($query->result_array() as $data){
			if(!$data['referer_domain']) $data['referer_group_name']					= '직접입력';
			if($data['referer_group_name']=='etc') $data['referer_group_name']	= '기타';
			$result['day_stat_member'][]	= $data;
		}

		/*  방문통계 */
		$query = "select stats_date, sum(count_sum) cnt from fm_stats_visitor_count where count_type='visit' and stats_date between ? and ? group by stats_date order by stats_date asc";
		$query = $this->db->query($query,array($sDate,$eDate));
		foreach($query->result_array() as $data){
			$result['day_stat_visit'][]	= $data;
		}

		/* referer 방문통계 */
		if( !serviceLimit('H_FR') ){
			$query = "SELECT SUM( count ) AS cnt, IF( (rg.referer_group_name IS NULL || rg.referer_group_name =  ''), IF( vr.referer_domain !=  '',  '기타',  '직접입력' ) , rg.referer_group_name ) AS new_group_name, vr.referer_domain FROM fm_stats_visitor_referer vr LEFT JOIN fm_referer_group rg ON vr.referer_domain = rg.referer_group_url WHERE vr.stats_date BETWEEN  ? AND ? GROUP BY new_group_name ORDER BY cnt DESC limit 10";
			$query = $this->db->query($query,array($sDate,$eDate));
			foreach($query->result_array() as $data){
				if ( $data['new_group_name'] == '기타' ) $data['referer_domain'] = '';
				$data['referer_group_name']	= $data['new_group_name'];
				$result['day_stat_referer'][]	= $data;
			}
		}

		$result['order_summary']	= $this->main_order_summary();
		$result['goods_summary']	= $this->main_goods_summary();
		$result['board_summary']	= $this->main_board_summary();
		$result['scm_summary']		= $this->main_scm_summary();
		$result['count_summary']	= $this->main_count_bar();

		return $result;
	}

	/* 상단 카운터 바  :: 2014-10-21 lwh*/
	public function main_count_bar($type=""){

		####################### 오늘 ############################
		## 오늘 신규회원 ##
		$query = $this->db->query("select count(*) as cnt from fm_member where regist_date between ? and ?",array(date('Y-m-d 00:00:00'),date('Y-m-d 23:59:59')));
		list($todayCount['new_member'])		= array_values($query->row_array());
		$todayCount['new_member']			= number_format($todayCount['new_member']);

		## 오늘 방문자 ##
		$query = $this->db->query("select count_sum from fm_stats_visitor_count where count_type = 'visit' and stats_date = ?",date('Y-m-d'));
		list($todayCount['visit'])			= array_values($query->row_array());
		$todayCount['visit']				= number_format($todayCount['visit']);

		## 오늘 결제 금액 ##  ## 오늘 결제 건수 ##
		$query = $this->db->query("select deposit_yn, sum(settleprice) as total_price, count(*) as total_cnt from fm_order where step between '15' and '75' and deposit_date between '".date('Y-m-d')." 00:00:00' and '".date('Y-m-d')." 23:59:59' group by deposit_yn");
		foreach($query->result_array() as $data)
		{
			if($data['deposit_yn'] == 'y')
			{
				$todayCount['total_price']	+= $data['total_price'];
				$todayCount['total_cnt']	+= $data['total_cnt'];
			}
		}
		
		// 오늘 매출액은 정산 테이블에서 조회처리
		$this->load->model('accountallmodel');
		$main_count_bar = $this->accountallmodel->get_main_count_bar_total_price(date('Y-m-d'));
		$todayCount['total_price'] = $main_count_bar['total_price'];
		

		## 오늘 주문접수건
		$query = $this->db->query("select count(*) as total_cnt from fm_order where step between '15' and '75' and regist_date between '".date('Y-m-d')." 00:00:00' and '".date('Y-m-d')." 23:59:59'");
		foreach($query->result_array() as $data)
		{
			$todayCount['order_cnt']	= $data['total_cnt'];
		}

		## 휴면처리 예정인 ##
		$a = explode('-', date('Y-m-d'));
		$dr_date = date('Y-m-d', mktime(0, 0, 0, $a[1], $a[2] - 335, $a[0]));
		$dr_date_ing = date('Y-m-d', mktime(0, 0, 0, $a[1], $a[2] + 30, $a[0]));

		$start_dr_date = $dr_date.' 00:00:00';
		$end_dr_date = $dr_date.' 23:59:59';
		$query = $this->db->query("select count(member_seq) as dormancy_cnt from fm_member where ((lastlogin_date between '{$start_dr_date}' and '{$end_dr_date}') or (lastlogin_date = '0000-00-00 00:00:00' and (regist_date between '{$start_dr_date}' and '{$end_dr_date}'))) and status != 'withdrawal' and (dormancy_seq is null or dormancy_seq = '')");
		list($todayCount['dormancy_cnt'])			= array_values($query->row_array());
		$todayCount['dormancy_cnt']				= number_format($todayCount['dormancy_cnt']);
		$todayCount['dormancy_guide'] = $dr_date;
		$todayCount['dormancy_ing'] = $dr_date_ing;

		####################### 누적 ############################

		## 누적회원 ##
		$query = $this->db->query("select count(*) as cnt from fm_member where `status` in ('done','hold','dormancy')");
		list($totalCount['member']) = array_values($query->row_array());
		$totalCount['member']		= $totalCount['member'];


		## 누적마일리지 ##
		$query = $this->db->query("select sum(emoney) as emoney_sum from fm_member where `status`='done'");
		list($totalCount['emoney']) = array_values($query->row_array());
		$totalCount['emoney']		= $totalCount['emoney'];

			## 누적포인트 ##
		$query = $this->db->query("select sum(point) as point_sum from fm_member where `status`='done'");
		list($totalCount['point'])	= array_values($query->row_array());
		$totalCount['point']		= $totalCount['point'];

			###################### 타입별 분기 #######################

		return array('total'=>$totalCount,'today'=>$todayCount);
	}

	/* 주문처리 (최근 100일) */
	public function main_order_summary($type=""){
		$fromDay	=  date('Y-m-d 00:00:00',strtotime('-100day'));
		$orderSummary	= array();
		$step_arr		= array('15'=>'주문접수', '25'=>'결제확인', '35'=>'상품준비', '40'=>'부분출고준비', '45'=>'출고준비', '50'=>'부분출고완료', '55'=>'출고완료', '60'=>'부분배송중', '65'=>'배송중', '70'=>'부분배송완료');

		$sql	= "SELECT count(*) as cnt , step FROM `fm_order` WHERE hidden = 'N' and regist_date>=? and step>=? and step<=?  and (label IS NULL OR (label='present' AND recipient_zipcode !='')) GROUP BY step	";
		$query	= $this->db->query($sql,array($fromDay,'15','75'));
		foreach ($query->result_array() as $row){
			$result[$row['step']]	= $row['cnt'];
		}

		$prevDate = date('Y-m-d', strtotime('-100day'));
		$nowDate  = date('Y-m-d');
		foreach ($step_arr as $key => $val){
			$orderSummary[$key] = array(
					'count'			=> ($result[$key]) ? $result[$key] : 0,
					'name'			=> $val,
					'link'			=> "../order/catalog?chk_step[".$key."]=1&regist_date[]=".$prevDate."&regist_date[]=".$nowDate
			);

			if($key == '45' || $key == '55' || $key == '65'){
				$orderSummary[$key]['link_export'] = "../export/catalog?export_status[".$key."]=1";
			}
		}

		/* 반품 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_return where `status` = 'request'");
		$result = $query->row_array();
		$orderSummary['101'] = array(
				'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
				'name'		=> '반품접수',
				'link'		=> '../returns/catalog?return_status[]=request'
		);

		/* 환불 접수 */
		$query = $this->db->query("select count(*) as cnt from fm_order_refund where `status` = 'request'");
		$result = $query->row_array();
		$orderSummary['102'] = array(
				'count'		=> ($result['cnt']) ? $result['cnt'] : 0,
				'name'		=> '환불접수',
				'link'		=> '../refund/catalog?refund_status[]=request'
		);
		// debug($orderSummary);
		return $orderSummary;
	}

	/* 상품현황 요약 :: 2014-10-21 lwh */
	public function main_goods_summary($type=""){
		// alter table fm_goods add safe_stock_status varchar(7) null after goods_view;
		//## 판매중의 의미 : 내가 보유한 판매가 가능한 상품의 수 (노출의 여부는 중요 X)
		$goodsSummary = array(
				'safe_stock'=>array(
						'goods'		=>array('count'=>0,'link'=>'/admin/goods/catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=less&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'coupon'		=>array('count'=>0,'link'=>'/admin/goods/social_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&stock_compare=less&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'package'	=>array('count'=>0,'link'=>'/admin/goods/package_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=less&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&searchflag=1')
				),
				'normal'=>array(
						'goods'		=>array('count'=>0,'link'=>'/admin/goods/catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=normal&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'coupon'		=>array('count'=>0,'link'=>'/admin/goods/social_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=normal&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'package'	=>array('count'=>0,'link'=>'/admin/goods/package_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=normal&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&searchflag=1')
				),
				'runout'=>array(
						'goods'		=>array('count'=>0,'link'=>'/admin/goods/catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=runout&goodsStatus%5B%5D=purchasing&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'coupon'		=>array('count'=>0,'link'=>'/admin/goods/social_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=runout&goodsStatus%5B%5D=purchasing&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'package'	=>array('count'=>0,'link'=>'/admin/goods/package_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=runout&goodsStatus%5B%5D=purchasing&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&searchflag=1')
				),
				'unsold'=>array(
						'goods'		=>array('count'=>0,'link'=>'/admin/goods/catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=unsold&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'coupon'		=>array('count'=>0,'link'=>'/admin/goods/social_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=unsold&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&excel_type=&searchflag=1'),
						'package'	=>array('count'=>0,'link'=>'/admin/goods/package_catalog?query_string=&no=&sort=desc&keyword=&search_type=&show_search_form=open&provider_seq_selector=0&provider_seq=&provider_name=&search_provider_status=&commission_type_sel=&s_commission_rate=&e_commission_rate=&category1=&category2=&category3=&category4=&brands1=&brands2=&brands3=&brands4=&goodsStatus%5B%5D=unsold&location1=&location2=&location3=&location4=&date_gb=regist_date&sdate=&edate=&price_gb=consumer_price&sprice=&eprice=&shipping_group_seq=&stock_compare=&sstock=&estock=&sweight=&eweight=&sale_seq=&spage_view=&epage_view=&event_seq=&gift_seq=&referersale_seq=&select_search_icon=&chk_icon=list&orderby=desc_goods_seq&perpage=10&searchflag=1')
				)
		);
		$query = $this->db->query("SELECT goods_status, goods_kind, package_yn, safe_stock_status, count(*) as cnt FROM fm_goods WHERE goods_type = 'goods' GROUP BY goods_status, goods_kind, package_yn, safe_stock_status");
		foreach($query->result_array() as $data)
		{
			if($data['package_yn']=='y') $data['goods_kind'] = 'package';
			if($data['safe_stock_status']=='y')
			{
				$goodsSummary['safe_stock'][$data['goods_kind']]	['count']	+= (int) $data['cnt'];
			}
			if($data['goods_status']=='normal')
			{
				$goodsSummary['normal'][$data['goods_kind']]	['count']		+= (int) $data['cnt'];
			}
			if(in_array($data['goods_status'],array('runout','purchasing')))
			{
				$goodsSummary['runout'][$data['goods_kind']]	['count']		+= (int) $data['cnt'];
			}
			if($data['goods_status']=='unsold'){
				$goodsSummary['unsold'][$data['goods_kind']]	['count']		+= (int) $data['cnt'];
			}
		}
		return $goodsSummary;
	}

	/* 게시글 현황 */
	public function main_board_summary($type=""){
		$limit = 6;
		$this->load->model('boardmodel');
		$this->load->helper('board');

		$query = $this->db->query("select count(*) cnt from fm_goods_qna a where a.re_subject is null");
		$data = $query->row_array();
		$board_summary[] = array(
				'id'			=> $bdwidget['boardid'],
				'name'	=> '상품문의',
				'count'	=> $data['cnt'],
				'link'		=> '../board/board?id=goods_qna&searchreply=y'
		);

		unset($bdwidget, $widgetloop,$boardurl);
		$bdwidget['boardid']	= 'mbqna';
		$bdwidget['limit']			= $limit;//
		getAdminBoardWidgets($bdwidget, $widgetloop, $name, $totalcount);
		$reply_row = $this->boardmodel->get_data(array('select'=>'COUNT(*) `count`', 'whereis'=>"AND `boardid`='".$bdwidget['boardid']."' AND `re_subject` <> ''"));

		$board_summary[] = array(
				'id'			=> $bdwidget['boardid'],
				'name'	=> '1:1문의',
				'count'	=> $totalcount-$reply_row['count'],
				'link'		=> '../board/board?id=mbqna&searchreply=y'
		);

		$this->load->model('counselmodel');
		$params['counsel_status']	= 'request';
		$fields	= 'count(counsel_seq) as cnt';
		$data		= $this->counselmodel->get($params,$fields)->row_array();
		$board_summary[] = array(
				'id'			=> 'counsel',
				'name'	=> '고객상담',
				'count'	=> $data['cnt'],
				'link'		=> '../board/counsel_catalog?&counsel_status%5B%5D=request'
		);
		if( serviceLimit('H_AD') ){
			unset($bdwidget, $widgetloop,$boardurl);
			$bdwidget['boardid']	= 'gs_seller_qna';
			$bdwidget['limit']			= $limit;//
			getAdminBoardWidgets($bdwidget, $widgetloop, $name, $totalcount);
			$reply_row = $this->boardmodel->get_data(array('select'=>'COUNT(*) `count`', 'whereis'=>"AND `boardid`='".$bdwidget['boardid']."' AND `re_subject` <> ''"));

			$board_summary[] = array(
					'id'			=> $bdwidget['boardid'],
					'name'	=> '입점사문의',
					'count'	=> $totalcount-$reply_row['count'],
					'link'		=> '../board/board?id=gs_seller_qna&searchreply=y'
			);
		}
		return $board_summary;
	}

	/* 게시글 현황 */
	public function main_scm_summary($type=""){
		$startdate_time	= date('Y-m-d H:i:s', strtotime('-9 day'));
		$enddate_time	= date('Y-m-d H:i:s');
		$startdate			= substr($startdate_time, 0, 10);
		$enddate			= substr($enddate_time, 0, 10);

		$totalcount	= 0;
		// 자동발주
		$query			= "select count(*) as count  from fm_scm_autoorder_order as sao where sao.aoo_seq > ?  and sao.option_type = ? and sao.regist_date >= ? and sao.regist_date <= ? group by DATE_FORMAT(sao.regist_date, '%Y-%m-%d'), sao.goods_seq, sao.option_type, sao.option_seq";
		$query			= $this->db->query($query,array(0, 'option', $startdate_time, $enddate_time));
		$data				= $query->row_array();
		$totalcount		= (int) $data['count'];
		$scm_summary[] = array(
				'name'	=> '자동발주',
				'count'	=> $totalcount,
				'link'		=> '../scm_warehousing/autoorder?keyword=&keyword_sType=&sc_trader_group=&sc_trader=&sc_warehouse=&sc_sstock=&sc_estock=&date_selected=today&sc_sdate='.$startdate.'&sc_edate='.$enddate.'&searchflag=1'
		);
		// 발주대기
		$query			= "select count(*) count from fm_scm_order	as so where so.sorder_seq > ?  and so.sorder_status = ?  and so.regist_date >= ?  and so.regist_date <=?";
		$query			= $this->db->query($query,array(0, 0, $startdate_time, $enddate_time));
		$data				= $query->row_array();
		$totalcount		= (int) $data['count'];
		$scm_summary[] = array(
				'name'	=> '발주대기',
				'count'	=> $totalcount,
				'link'		=> '../scm_warehousing/sorder?keyword=&keyword_sType=&sc_sorder_status=0&sc_trader_group=&sc_trader=&sc_date_fld=regist&date_selected=today&sc_sdate='.$startdate.'&sc_edate='.$enddate.'&searchflag=1'
		);
		// 입고대기
		$query			= "select count(*) count from fm_scm_warehousing as swhs where swhs.whs_seq > ?  and swhs.whs_status = ?  and swhs.regist_date >= ?  and swhs.regist_date <= ?";
		$query			= $this->db->query($query,array(0, 0, $startdate_time, $enddate_time));
		$data				= $query->row_array();
		$totalcount		= (int) $data['count'];
		$scm_summary[] = array(
				'name'	=> '입고대기',
				'count'	=> $totalcount,
				'link'		=> '../scm_warehousing/warehousing?keyword=&keyword_sType=&sc_whs_status=0&sc_trader_group=&sc_trader=&sc_date_fld=regist&date_selected=today&sc_sdate='.$startdate.'&sc_edate='.$enddate.'&searchflag=1'
		);
		// 반출대기
		$query			= "select count(*) count from fm_scm_carryingout	as scro where scro.cro_seq > ?  and scro.cro_status = ?  and scro.regist_date >= ?  and scro.regist_date <= ?";
		$query			= $this->db->query($query,array(0, 0, $startdate_time, $enddate_time));
		$data				= $query->row_array();
		$totalcount		= (int) $data['count'];
		$scm_summary[] = array(
				'name'	=> '반출대기',
				'count'	=> $totalcount,
				'link'		=> '../scm_warehousing/carryingout?keyword=&keyword_sType=&sc_trader_group=&sc_trader=&sc_cro_status=0&sc_wh_seq=&sc_date_fld=regist&date_selected=today&sc_sdate='.$startdate.'&sc_edate='.$enddate.'&searchflag=1'
		);
		return $scm_summary;
	}

	public function get_main_statistic_data(){
		$rank_array				= array('first', 'second', 'third');

		## 매출추이 수집데이터 :: 2015-09-18 lwh
		//관리자 메인 오늘제외한 최근 10일까지 @2016-06-22 ysm
		$params['sDate']		= date('Y-m-d', strtotime('-10 day'));
		$params['eDate']		= date('Y-m-d', strtotime('-1 day'));
		$query					= $this->get_summary_order_stats($params);
		$order					= $query->result_array();
		if	($order){
			foreach($order as $k => $data){
				$sales_date		= date('d', strtotime($data['date']));
				$orderChart[$sales_date] += $data['price'];
			}
		}

		## 판매상품 수집데이터 :: 2015-09-18 lwh
		$params['stats_type']	= 'total';
		$params['o_type']		= 'otp';
		$query					= $this->get_summary_goods_stats($params);
		$goods					= $query->result_array();

		if	($goods){
			foreach($goods as $k => $data){
				// $data['price'] * $data['ea']; -> $data['ea'];
				$prices[$data['goods_seq']]			+= $data['ea'];
				$names[$data['goods_seq']]			= $data['order_goods_name'];

				//어제일자 통계 @2016-06-22
				if	($data['deposit_ymd'] == date('Y-m-d', strtotime('-1 day'))){
					$Tprices[$data['goods_seq']]	+= $data['ea'];
					$Tnames[$data['goods_seq']]		= $data['order_goods_name'];
				}
			}
		}

		## 판매상품 순위 데이터
		arsort($prices);
		$r		= 0;
		$stat['rank'][0]['order']		= array();
		$stat['rank'][1]['order']		= array();
		foreach($prices as $goods_seq => $price){
			if	($r < 2){
				$stat['rank'][$r]['order']['price']			= $price;
				$stat['rank'][$r]['order']['goods_name']	= $names[$goods_seq];
				$stat['rank'][$r]['order']['goods_seq']		= $goods_seq;
			}
			$r++;
		}
		if	($Tprices){
			arsort($Tprices);
			$seq_arr	= array_keys($Tprices);
			$Tgoods_seq	= $seq_arr[0];
		}
		$stat['rank'][2]['order']['price']			= $Tprices[$Tgoods_seq];
		$stat['rank'][2]['order']['goods_name']		= $Tnames[$Tgoods_seq];
		$stat['rank'][2]['order']['goods_seq']		= $Tgoods_seq;


		## 장바구니
		$params['q_type']		= 'rank';
		$params['date_term']	= 'main10days';//오늘제외한 최근 10일까지 @2016-06-22 ysm
		$query		= $this->get_statistic_cart_stats($params);
		$cart		= $query->result_array();
		$stat['rank'][0]['cart']		= array();
		$stat['rank'][1]['cart']		= array();
		for ($r = 0; $r < 2; $r++){
			$stat['rank'][$r]['cart']['cnt']		= $cart[$r]['cnt'];
			$stat['rank'][$r]['cart']['goods_name']	= $cart[$r]['goods_name'];
			$stat['rank'][$r]['cart']['goods_seq']	= $cart[$r]['goods_seq'];
		}
		$params['q_type']		= 'rank';
		$params['date_term']	= 'yesterday';//오늘제외한 최근 10일까지 @2016-06-22 ysm
		$query		= $this->get_statistic_cart_stats($params);
		$cart		= $query->result_array();
		$stat['rank'][2]['cart']['cnt']			= $cart[0]['cnt'];
		$stat['rank'][2]['cart']['goods_name']	= $cart[0]['goods_name'];
		$stat['rank'][2]['cart']['goods_seq']	= $cart[0]['goods_seq'];



		## 위시리스트
		$params['get_type']		= 'rank';
		$params['date_term']	= 'main10days';//오늘제외한 최근 10일까지 @2016-06-22 ysm
		$query		= $this->get_statistic_wish_stats($params);
		$wish		= $query->result_array();
		$stat['rank'][0]['wish']		= array();
		$stat['rank'][1]['wish']		= array();
		for ($r = 0; $r < 2; $r++){
			$stat['rank'][$r]['wish']['cnt']		= $wish[$r]['cnt'];
			$stat['rank'][$r]['wish']['goods_name']	= $wish[$r]['goods_name'];
			$stat['rank'][$r]['wish']['goods_seq']	= $wish[$r]['goods_seq'];
		}
		$params['q_type']		= 'rank';
		$params['date_term']	= 'yesterday';//오늘제외한 최근 10일까지 @2016-06-22 ysm
		$query		= $this->get_statistic_wish_stats($params);
		$wish		= $query->result_array();
		$stat['rank'][2]['wish']['cnt']			= $wish[0]['cnt'];
		$stat['rank'][2]['wish']['goods_name']	= $wish[0]['goods_name'];
		$stat['rank'][2]['wish']['goods_seq']	= $wish[0]['goods_seq'];

		## 검색어 어제일자 변경 @2016-06-22
		$params['sdate']	= date('Y-m-d', strtotime('-10 day'));
		$params['edate']	= date('Y-m-d', strtotime('-1 day'));
		$query		= $this->get_goods_search_stats($params);
		$keyword	= $query->result_array();
		$stat['rank'][0]['keyword']		= array();
		$stat['rank'][1]['keyword']		= array();
		for ($r = 0; $r < 2; $r++){
			$stat['rank'][$r]['keyword']['cnt']		= $keyword[$r]['keyword_cnt'];
			$stat['rank'][$r]['keyword']['keyword']	= $keyword[$r]['keyword'];
		}
		//오늘제외한 최근 10일까지 @2016-06-22 ysm
		$params['sdate']	= date('Y-m-d', strtotime('-1 day'));
		$params['edate']	= date('Y-m-d', strtotime('-1 day'));
		$query		= $this->get_goods_search_stats($params);
		$keyword	= $query->result_array();
		$stat['rank'][2]['keyword']['cnt']			= $keyword[0]['keyword_cnt'];
		$stat['rank'][2]['keyword']['keyword']		= $keyword[0]['keyword'];


		## 회원 오늘제외한 최근 10일까지 @2016-06-22 ysm
		$params['sDate']		= date('Y-m-d', strtotime('-10 day'));
		$params['eDate']		= date('Y-m-d', strtotime('-1 day'));
		$query					= $this->get_member_referer_stats($params);
		$member					= $query->result_array();
		if	($member){
			foreach($member as $k => $data){
				if	(date('Ymd', strtotime($data['regist_date'])) >= date('Ymd', strtotime('-9 day'))){
					$memberChart[$data['date']]								+= $data['cnt'];
					$mRefererChart[$data['referer_name']][$data['date']]	+= $data['cnt'];
				}
			}
		}

		## 방문 오늘제외한 최근 10일까지 @2016-06-22 ysm
		$params['sDate']	= date('Y-m-d', strtotime('-10 day'));
		$params['eDate']	= date('Y-m-d', strtotime('-1 day'));
		$query				= $this->get_statistic_visitor_stats($params);
		$visitor			= $query->result_array();
		if	($visitor){
			foreach($visitor as $k => $data){
				$visitorChart[$data['stats_day']]							= $data['vcnt'];
				$vRefererChart[$data['referer_name']][$data['stats_day']]	+= $data['cnt'];
			}
		}



		## Chart 데이터
		$oReferer_arr	= array_keys($oRefererChart);
		$oReferer_cnt	= count($oReferer_arr);
		$mReferer_arr	= array_keys($mRefererChart);
		$mReferer_cnt	= count($mReferer_arr);
		$vReferer_arr	= array_keys($vRefererChart);
		$vReferer_cnt	= count($vReferer_arr);

		//오늘제외한 최근 10일까지 @2016-06-22 ysm
		$start_time		= strtotime('-10 day');
		$nDate			= date('Y-m-d', $start_time);
		while (date('Y-m-d') != $nDate){
			$addDay++;
			$day			= date('d', strtotime($nDate));
			$orderPrice		= ($orderChart[$day]) ? floor($orderChart[$day]/1000) : 0;
			$memberCnt		= ($memberChart[$day]) ? $memberChart[$day] : 0;
			$visitorCnt		= ($visitorChart[$day]) ? $visitorChart[$day] : 0;

			// 매출 유입처
			if	($oReferer_cnt > 0){
				for ($or = 0; $or < $oReferer_cnt; $or++){
					$referer		= $oReferer_arr[$or];
					$oRefererPrice	= ($oRefererChart[$referer][$day]) ? floor($oRefererChart[$referer][$day]/1000) : 0;

					$dataForChart['매출유입경로'][$referer][]	= array($day.'일', $oRefererPrice);

					$maxValue['매출유입경로']	= ($maxValue['매출유입경로'] < $oRefererPrice)	? $oRefererPrice	: $maxValue['매출유입경로'];
				}
			}else{
				$dataForChart['매출유입경로']['no_data'][]	= array($day.'일', 0);
			}

				// 회원 유입처
			if	($mReferer_cnt > 0){
				for ($mr = 0; $mr < $mReferer_cnt; $mr++){
					$referer		= $mReferer_arr[$mr];
					$mRefererCnt	= ($mRefererChart[$referer][$day]) ? $mRefererChart[$referer][$day] : 0;

					$dataForChart['회원유입경로'][$referer][]	= array($day.'일', $mRefererCnt);

					$maxValue['회원유입경로']	= ($maxValue['회원유입경로'] < $mRefererCnt)	? $mRefererCnt	: $maxValue['회원유입경로'];
				}
			}else{
				$dataForChart['회원유입경로']['no_data'][]	= array($day.'일', 0);
			}

			// 방문 유입처
			if	($vReferer_cnt > 0){
				for ($vr = 0; $vr < $vReferer_cnt; $vr++){
					$referer		= $vReferer_arr[$vr];
					$vRefererCnt	= ($vRefererChart[$referer][$day]) ? $vRefererChart[$referer][$day] : 0;

					$dataForChart['방문유입경로'][$referer][]	= array($day.'일', $vRefererCnt);

					$maxValue['방문유입경로']	= ($maxValue['방문유입경로'] < $vRefererCnt)	? $vRefererCnt	: $maxValue['방문유입경로'];
				}
			}else{
				$dataForChart['방문유입경로']['no_data'][]	= array($day.'일', 0);
			}

			$dataForChart['매출'][]	= array($day.'일', $orderPrice);
			$dataForChart['회원'][]	= array($day.'일', $memberCnt);
			$dataForChart['방문'][]	= array($day.'일', $visitorCnt);

			$maxValue['매출']	= ($maxValue['매출'] < $orderPrice)	? $orderPrice	: $maxValue['매출'];
			$maxValue['회원']	= ($maxValue['회원'] < $memberCnt)	? $memberCnt	: $maxValue['회원'];
			$maxValue['방문']	= ($maxValue['방문'] < $visitorCnt)	? $visitorCnt	: $maxValue['방문'];

			$nDate		= date('Y-m-d', strtotime('+'.$addDay.' day', $start_time));
		}

		$this->dataForChart	= $dataForChart;
		$this->maxValue		= $maxValue;
		$this->stat			= $stat;
		$this->rank_array	= $rank_array;
	}

	public function get_referer_grouplist(){
		$query	= "select referer_group_cd, referer_group_name
					from fm_referer_group group by referer_group_cd order by seq";
		$query	= $this->db->query($query);

		$return = $query->result_array();
		if ($return) {
			$cnt = count($return);
			for ($i=0;$i<$cnt;$i++) {
				if ($return[$i]['referer_group_name']=="다움검색_광고") {
					$return[$i]['referer_group_name'] = "다음검색_광고";
				}
			}
		}
		return $return;
	}

	/* 상품 일별 구매 통계 데이터 :: 2014-08-04 lwh */
	public function get_daily_sales_stats($sdate='', $edate=''){

		if($sdate=='' || $edate==''){
			$sdate	= date('Y-m-d');
			$edate	= date('Y-m-d');
		}

		$addWhere	= " and a.deposit_date between '".$sdate." 00:00:00' and '".$edate." 23:59:59' ";

		$sql = "
			select
				b.goods_seq,
				b.goods_code,
				b.goods_name as order_goods_name,
				c.supply_price as supply_price,
				c.consumer_price as consumer_price,
				c.price as price,
				sum(c.ea) as ea,
				a.shipping_cost,
				a.emoney,
				a.cash,
				a.enuri,
				a.npay_point,
				b.shipping_policy as shipping_policy,
				sum(c.coupon_sale) as coupon_sale,
				sum(c.member_sale*c.ea) as member_sale,
				sum(c.fblike_sale) as fblike_sale,
				sum(c.mobile_sale) as mobile_sale,
				sum(c.promotion_code_sale) as promotion_code_sale,
				sum(c.referer_sale) as referer_sale,
				sum(ifnull(c.npay_sale_seller,0)) as npay_sale_seller,
				sum(ifnull(c.npay_sale_npay,0)) as npay_sale_npay,
				c.title1 as title1,
				c.option1 as option1,
				c.title2 as title2,
				c.option2 as option2,
				c.title3 as title3,
				c.option3 as option3,
				c.title4 as title4,
				c.option4 as option4,
				c.title5 as title5,
				c.option5 as option5,
				date_format(a.deposit_date,'%Y-%m-%d') deposit_ymd,
				(select group_concat(category_code) from fm_category_link where goods_seq=b.goods_seq group by goods_seq) as category_codes,
				(select group_concat(category_code) from fm_brand_link where goods_seq=b.goods_seq group by goods_seq) as brand_codes,
				'otp' as o_type
			from fm_order as a
				inner join fm_order_item b on a.order_seq=b.order_seq
				inner join fm_order_item_option c on c.item_seq = b.item_seq
			where a.deposit_yn='y' and
				a.step between '15' and '85'
				".$addWhere."
			group by deposit_ymd, b.goods_seq, c.price,
				concat_ws('',c.title1,c.option1,c.title2,c.option2,c.title3,c.option3,c.title4,c.option4,c.title5,c.option5)
		union
			select
				b.goods_seq,
				b.goods_code,
				b.goods_name as order_goods_name,
				c.supply_price as supply_price,
				c.consumer_price as consumer_price,
				c.price as price,
				sum(c.ea) as ea,
				a.shipping_cost,
				a.emoney,
				a.cash,
				a.enuri,
				a.npay_point,
				b.shipping_policy as shipping_policy,
				0 as coupon_sale,
				0 as member_sale,
				0 as fblike_sale,
				0 as mobile_sale,
				0 as promotion_code_sale,
				0 as referer_sale,
				0 as npay_sale_npay,
				0 as npay_sale_seller,
				c.title as title1,
				c.suboption as option1,
				'' as title2,
				'' as option2,
				'' as title3,
				'' as option3,
				'' as title4,
				'' as option4,
				'' as title5,
				'' as option5,
				date_format(a.deposit_date,'%Y-%m-%d') deposit_ymd,
				(select group_concat(category_code) from fm_category_link where goods_seq=b.goods_seq group by goods_seq) as category_codes,
				(select group_concat(category_code) from fm_brand_link where goods_seq=b.goods_seq group by goods_seq) as brand_codes,
				'sub' as o_type
			from fm_order as a
				inner join fm_order_item b on a.order_seq=b.order_seq
				inner join fm_order_item_suboption c on c.item_seq = b.item_seq
			where a.deposit_yn='y' and
				a.step between '15' and '85'
				".$addWhere."
			group by deposit_ymd,b.goods_seq,c.price,
				concat_ws('',c.title,c.suboption)
			";

		$query	= $this->db->query($sql);

		return $query->result_array();
	}

	/* 상품 일별 구매 집계 데이터 삽입 :: 2014-08-04 lwh */
	public function set_accumul_stats_sales($data){
		$this->db->insert("fm_accumul_stats_sales",$data);
	}

	/* 상품 일별 구매 집계 데이터 삭제 :: 2014-08-04 lwh */
	public function delete_accumul_stats_sales($sdate='', $edate=''){
		if($sdate && $edate){
			$sql = "delete from fm_accumul_stats_sales where deposit_ymd between '".$sdate."' and '".$edate."'";

			$query	= $this->db->query($sql);
		}
	}

	/* 상품 일별 구매 집계 데이터 페이징 :: 2014-08-06 lwh */
	// 정산테이블 기준으로 변경 :: 2018-07-30 pjw
	public function get_sales_goods_daily_pagin($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if	($keyword)					$addWhere2	.= " and concat(order_goods_seq,ifnull(goods_code,''),order_goods_name) like '%".addslashes($keyword)."%'";
		if	((int)$provider_seq > 0)	$addWhere2	= " AND ord.provider_seq = ".$provider_seq;

		$orderBy	= " order by ".$sort.", order_goods_seq asc";
		$pagein		= " limit ".$start_page.", ".$end_page." ";

		$sql	= "
				SELECT
					ord.*, ord.price*ord.ea as goods_price, p.provider_name
				FROM
					".$table_name." AS ord
					LEFT JOIN fm_provider as p on ord.provider_seq = p.provider_seq
				WHERE
					ord.order_goods_seq > 0
					AND ord.account_type = 'order'
					AND ord.order_type != 'shipping'
					AND ord.deposit_date BETWEEN '".$sdate."' AND '".$edate."'
					".$addWhere2."
				".$orderBy." ".$pagein;

//		debug($sql);
		$query	= $this->db->query($sql);
		return $query->result_array();
	}

	/* 구매통계 매출 통계 데이터 :: 2014-08-07 lwh */ // 2015-09-08 commission 추가
	public function get_sales_mdstats($sdate='', $edate=''){

		if($sdate=='' || $edate==''){
			$sdate	= date('Y-m-d');
			$edate	= date('Y-m-d');
		}

		$addWhere	= " AND a.deposit_date BETWEEN '".$sdate." 00:00:00' AND '".$edate." 23:59:59' ";

		$sql = "
		SELECT
			a.order_seq					AS order_seq
			,YEAR(a.deposit_date)		AS stats_year
			,MONTH(a.deposit_date)		AS stats_month
			,DAY(a.deposit_date)		AS stats_day
			,SUM(a.settleprice)			AS settleprice_sum
			,SUM(a.enuri)				AS enuri_sum
			,SUM(a.emoney)				AS emoney_use_sum
			,SUM(a.cash)				AS cash_use_sum
			,SUM(ifnull(a.npay_point,0))				AS npay_point_use_sum
			,IFNULL((select sum(shipping_coupon_sale) from fm_order_shipping where order_seq = a.order_seq),0)				AS shipping_coupon_sale_sum
			,IFNULL((select sum(shipping_promotion_code_sale) from fm_order_shipping where order_seq = a.order_seq),0) AS shipping_promotion_code_sale_sum
			,COUNT(*)					AS count_sum
			,(select sum(ori_price*ea) from fm_order_item_option where order_seq=a.order_seq)
										AS option_ori_price_sum

			,(select sum(ifnull(coupon_sale,0)) from fm_order_item_option where order_seq=a.order_seq)		AS option_coupon_sale_sum
			,(select sum(ifnull(member_sale,0)*ea) from fm_order_item_option where order_seq=a.order_seq)		AS option_member_sale_sum
			,(select sum(ifnull(ois.member_sale,0)*ois.ea) from fm_order_item_suboption ois, fm_order_item_option io where ois.order_seq=a.order_seq and io.item_option_seq = ois.item_option_seq)		AS suboption_member_sale_sum
			,(select sum(ifnull(fblike_sale,0)) from fm_order_item_option where order_seq=a.order_seq)		AS option_fblike_sale_sum
			,(select sum(ifnull(mobile_sale,0)) from fm_order_item_option where order_seq=a.order_seq)		AS option_mobile_sale_sum
			,(select sum(ifnull(promotion_code_sale,0)) from fm_order_item_option where order_seq=a.order_seq)		AS option_promotion_code_sale_sum
			,(select sum(ifnull(referer_sale,0)) from fm_order_item_option where order_seq=a.order_seq)		AS option_referer_sale_sum
			,(select sum(ifnull(npay_sale_seller,0)) from fm_order_item_option where order_seq=a.order_seq)		AS option_npay_sale_seller_sum
			,(select sum(ifnull(npay_sale_npay,0)) from fm_order_item_option where order_seq=a.order_seq)		AS option_npay_sale_npay_sum

			,(select sum(goods_shipping_cost) from fm_order_item where order_seq=a.order_seq)
										AS goods_shipping_cost_sum
			,(select sum(goods_shipping_cost) from fm_order_item where order_seq=a.order_seq and provider_seq = 1)
										AS m_goods_shipping_cost_sum
			,(select sum(goods_shipping_cost) from fm_order_item where order_seq=a.order_seq and provider_seq > 1)
										AS p_goods_shipping_cost_sum
			,(select ifnull(sum(supply_price*ea),0) from fm_order_item_option where order_seq=a.order_seq and provider_seq = 1)
										AS option_supply_price_sum
			,(select ifnull(sum(ois.supply_price*ois.ea),0) from fm_order_item_suboption ois, fm_order_item_option io where ois.order_seq=a.order_seq and io.item_option_seq = ois.item_option_seq and io.provider_seq = 1)
										AS suboption_supply_price_sum
			,(select ifnull(sum(commission_price*ea),0) from fm_order_item_option where order_seq=a.order_seq and provider_seq > 1)
										AS option_commission_price_sum
			,(select ifnull(sum(ois.commission_price*ois.ea),0) from fm_order_item_suboption ois, fm_order_item_option io where ois.order_seq=a.order_seq and io.item_option_seq = ois.item_option_seq and io.provider_seq > 1)
										AS suboption_commission_price_sum
			,(select ifnull(sum(commission_price_krw*ea),0) from fm_order_item_option where order_seq=a.order_seq and provider_seq > 1)
										AS option_commission_price_sum_krw
			,(select ifnull(sum(ois.commission_price_krw*ois.ea),0) from fm_order_item_suboption ois, fm_order_item_option io where ois.order_seq=a.order_seq and io.item_option_seq = ois.item_option_seq and io.provider_seq > 1)
										AS suboption_commission_price_sum_krw 
			,ifnull((select sum(shipping_cost) from fm_order_shipping where order_seq = a.order_seq group by order_seq),0)
											AS shipping_cost_sum 
			,ifnull((select sum(shipping_cost) from fm_order_shipping where order_seq = a.order_seq and provider_seq > 1 group by order_seq),0)
											AS p_shipping_cost
			,a.deposit_date
			,a.sitetype
		FROM fm_order AS a
		WHERE a.deposit_yn='y' and
			a.step between '15' and '85'
		".$addWhere."
		GROUP BY a.order_seq
		";

		$query		= $this->db->query($sql);
		$result		= $query->result_array();
		$order_arr	= array();
		$return		= array();
		foreach($result as $k => $val){
			$order_arr[]	= $val['order_seq'];
		}

		if(count($order_arr) > 0){

			// 옵션들의 실결제(결제-할인) 본사/입점사 추출 @2017-04-05
			$opt_sql	= "
				SELECT
					oi.order_seq					AS order_seq,
					IF(oi.provider_seq >1,'P','M')	AS provider_type,
					SUM(IFNULL(oio.price,0)*IFNULL(oio.ea,0)) - SUM(IFNULL(oio.coupon_sale,0)) - SUM(IFNULL(oio.member_sale,0)*IFNULL(oio.ea,0)) - SUM(IFNULL(oio.fblike_sale,0)) - SUM(IFNULL(oio.mobile_sale,0)) - SUM(IFNULL(oio.promotion_code_sale,0)) - SUM(IFNULL(oio.referer_sale,0))
					- SUM(IFNULL(oio.npay_sale_npay,0)) - SUM(IFNULL(oio.npay_sale_seller,0)) AS opt_price 
				FROM
					fm_order_item			AS oi,
					fm_order_item_option	AS oio
				WHERE
					oi.order_seq in ('".implode("','",$order_arr)."') AND
					oi.item_seq = oio.item_seq
				GROUP BY
					oi.order_seq, IF(oi.provider_seq >1,'P','M')
			";

			$opt_query	= $this->db->query($opt_sql);
			$opt_res	= $opt_query->result_array();

			foreach($opt_res as $k => $val){
				$opt_arr[$val['order_seq']][$val['provider_type']] = $val;
			}
			
			// 추가옵션들의 실결제(결제-할인) 본사/입점사 추출 @2017-04-05
			$subopt_sql	= "
				SELECT 
					oi.order_seq					AS order_seq,
					IF(oi.provider_seq >1,'P','M')	AS provider_type,
					sum(ifnull(oio.price,0)*ifnull(oio.ea,0)) - sum(ifnull(oio.member_sale,0)*ifnull(oio.ea,0)) AS	sub_price
				FROM
					fm_order_item			AS oi,
					fm_order_item_suboption	AS oio
				WHERE
					oi.order_seq in ('".implode("','",$order_arr)."') AND
					oi.item_seq = oio.item_seq
				GROUP BY
					oi.order_seq, IF(oi.provider_seq >1,'P','M')
			";
			$subopt_query	= $this->db->query($subopt_sql);
			$subopt_res	= $subopt_query->result_array();
			foreach($subopt_res as $k => $val){
				$opt_arr[$val['order_seq']][$val['provider_type']]['sub_price'] = $val['sub_price'];
			}

			// 최종 결과 조합 :: 2015-10-05 lwh
			foreach($result as $order){
				$o_obj						= $opt_arr[$order['order_seq']];
				$order['m_settleprice']		= $o_obj['M']['opt_price'] + $o_obj['M']['sub_price'];
				$order['p_settleprice']		= $o_obj['P']['opt_price'] + $o_obj['P']['sub_price'];
				$order['m_shipping_cost']	= $order['shipping_cost_sum'] - $order['p_shipping_cost'];

				// 에누리 분리
				if($order['enuri_sum'] > 0){
					if($o_obj['M']['order_seq'])	$order['m_enuri_sum'] = $order['enuri_sum'];
					else							$order['p_enuri_sum'] = $order['enuri_sum'];
				}

				$return[] = $order;
			}
		}

		return $return;
	}

	/* 구매통계 매출 집계 데이터 삽입 :: 2014-08-07 lwh */
	public function set_accumul_sales_mdstats($data){
		$this->db->insert("fm_accumul_sales_mdstats",$data);
	}

	/* 구매통계 매출 집계 체크 :: 2015-09-30 lwh */
	public function set_accumul_mark($order_seq,$mark='Y'){
		if(is_array($order_seq)){
			$sql = "update fm_order set accumul_mark = '".$mark."' where order_seq IN ('".implode("','",$order_seq)."') ";
		}else{
			$sql = "update fm_order set accumul_mark = '".$mark."' where order_seq = '".$order_seq."' ";
		}
		$query	= $this->db->query($sql);
	}

	/* 구매통계 매출 집계 데이터 삭제 :: 2014-08-07 lwh */
	public function delete_accumul_sales_mdstats($sdate='', $edate=''){
		if($sdate && $edate){
			$sql = "delete from fm_accumul_sales_mdstats where deposit_date between '".$sdate." 00:00:00' and '".$edate." 23:59:59'";

			$query	= $this->db->query($sql);
		}
	}

	/* 환불 롤백 데이터 저장 :: 2015-09-08 lwh */
	public function rollback_stat_refund($order_seq){
		$sql = "
		SELECT
			o.order_seq,
			o.settleprice as refund_price_sum,
			(
				SELECT
					SUM(IFNULL(oio.ea,0))
				FROM
					fm_order_item AS oi, fm_order_item_option oio
				WHERE
					oi.order_seq = o.order_seq
					AND oi.item_seq = oio.item_seq
					AND oi.provider_seq = 1
			) AS M_refund_ea,
			(
				SELECT
					SUM(IFNULL(oio.ea,0))
				FROM
					fm_order_item AS oi, fm_order_item_option oio
				WHERE
					oi.order_seq = o.order_seq
					AND oi.item_seq = oio.item_seq
					AND	oi.provider_seq > 1
			) AS P_refund_ea,
			(
				SELECT IFNULL(sum( oio.supply_price * oio.ea ), 0)
				FROM fm_order_item oi, fm_order_item_option oio
				WHERE oi.order_seq = o.order_seq
				AND oi.item_seq = oio.item_seq
				AND oi.provider_seq = 1
			) AS option_supply_price_sum,
			(
				SELECT IFNULL(sum( ois.supply_price * ois.ea ), 0)
				FROM fm_order_item oi, fm_order_item_suboption ois
				WHERE oi.order_seq = o.order_seq
				AND oi.item_seq = ois.item_seq
				AND oi.provider_seq = 1
			) AS suboption_supply_price_sum,
			(
				SELECT IFNULL(sum( oio.commission_price * oio.ea ), 0)
				FROM fm_order_item oi, fm_order_item_option oio
				WHERE oi.order_seq = o.order_seq
				AND oi.item_seq = oio.item_seq
				AND oi.provider_seq > 1
			) AS option_commission_price_sum,
			(
				SELECT IFNULL(sum( ois.commission_price * ois.ea ), 0)
				FROM fm_order_item oi, fm_order_item_suboption ois
				WHERE oi.order_seq = o.order_seq
				AND oi.item_seq = ois.item_seq
				AND oi.provider_seq > 1
			) AS suboption_commission_price_sum,
			o.sitetype
		FROM fm_order as o
		WHERE o.order_seq = ?
		";

		$query		= $this->db->query($sql,$order_seq);
		$res_stat	= $query->result_array();
		$rollback_stat = $res_stat[0];

		$total			= $rollback_stat['M_refund_ea'] + $rollback_stat['P_refund_ea'];

		// 전체 나눌 금액
		$remain_price = $rollback_stat['refund_price_sum'];
		if($remain_price > 0){
			// 각각 백분율 계산
			$m_per	= round(($rollback_stat['M_refund_ea'] / $total) * 100);
			$p_per	= round(($rollback_stat['P_refund_ea'] / $total) * 100);

			// 각각의 추가될 금액
			$rollback_stat['m_refund_price'] = round(($rollback_stat['refund_price_sum'] * $m_per / 100));
			$rollback_stat['p_refund_price'] = round(($rollback_stat['refund_price_sum'] * $p_per / 100));

			unset($rollback_stat['M_refund_ea']);
			unset($rollback_stat['P_refund_ea']);
		}

		$rollback_stat['refund_type']		= 'R';
		$rollback_stat['stats_year']		= date('Y');
		$rollback_stat['stats_month']		= date('n');
		$rollback_stat['stats_day']			= date('j');
		$rollback_stat['refund_emoney_sum']	= '0'; // 환불예치금합계
		$rollback_stat['refund_cash_sum']	= '0'; // 환불캐쉬합계
		$rollback_stat['refund_count_sum']	= '1'; // 환불횟수합계
		$rollback_stat['cancel_count_sum']	= '1'; // 취소횟수합계
		$rollback_stat['return_price_sum']	= '0'; // 반품금액합계
		$rollback_stat['return_count_sum']	= '0'; // 반품횟수합계
		$rollback_stat['cancel_price_sum']	= $rollback_stat['refund_price_sum'];
												   // 취소금액합계
		$rollback_stat['refund_date']		= date('Y-m-d');


		$this->db->insert("fm_accumul_sales_refund",$rollback_stat);
		$this->set_accumul_mark($order_seq,'N');
	}

	/* 구매통계 환불 통계 데이터 :: 2014-08-08 lwh */ // 2015-09-08 commission 추가
	public function get_sales_refund($sdate='', $edate=''){
		$return = array();

		if($sdate=='' || $edate==''){
			$sdate	= date('Y-m-d');
			$edate	= date('Y-m-d');
		}

		$addWhere	= " AND a.refund_date BETWEEN '".$sdate." 00:00:00' AND '".$edate." 23:59:59' ";

		$sql = "
		SELECT
			a.refund_code			AS refund_code,
			a.order_seq				AS order_seq,
			YEAR( a.refund_date )	AS stats_year,
			MONTH( a.refund_date )	AS stats_month,
			DAY( a.refund_date )	AS stats_day,
			SUM( a.refund_price )	AS refund_price_sum,
			SUM( a.refund_emoney )	AS refund_emoney_sum,
			SUM( a.refund_cash )	AS refund_cash_sum,
			COUNT( * )				AS refund_count_sum,
			(
				IFNULL((SELECT SUM( IFNULL(io.supply_price,0) * IFNULL(ri.ea,0) )
				FROM fm_order_refund_item ri, fm_order_item_option io
				WHERE ri.refund_code = a.refund_code
				AND ri.option_seq >0
				AND ri.option_seq = io.item_option_seq
				AND io.provider_seq = 1),0)
			) AS option_supply_price_sum,
			(
				IFNULL((SELECT SUM( IFNULL(ois.supply_price,0) * IFNULL(ri.ea,0) )
				FROM fm_order_refund_item ri, fm_order_item_suboption ois, fm_order_item_option io
				WHERE ri.refund_code = a.refund_code
				AND ri.suboption_seq >0
				AND ri.suboption_seq = ois.item_suboption_seq
				AND io.item_option_seq = ois.item_option_seq
				AND io.provider_seq = 1),0)
			) AS suboption_supply_price_sum,
			(
				IFNULL((SELECT SUM( IFNULL(io.commission_price,0) * IFNULL(ri.ea,0) )
				FROM fm_order_refund_item ri, fm_order_item_option io
				WHERE ri.refund_code = a.refund_code
				AND ri.option_seq >0
				AND ri.option_seq = io.item_option_seq
				AND io.provider_seq > 1),0)
			) AS option_commission_price_sum,
			(
				IFNULL((SELECT SUM( IFNULL(ois.commission_price,0) * IFNULL(ri.ea,0) )
				FROM fm_order_refund_item ri, fm_order_item_suboption ois, fm_order_item_option io
				WHERE ri.refund_code = a.refund_code
				AND ri.suboption_seq >0
				AND ri.suboption_seq = ois.item_suboption_seq
				AND io.item_option_seq = ois.item_option_seq
				AND io.provider_seq > 1),0)
			) AS suboption_commission_price_sum,
			(
				IFNULL((SELECT SUM( IFNULL(io.commission_price_krw,0) * IFNULL(ri.ea,0) )
				FROM fm_order_refund_item ri, fm_order_item_option io
				WHERE ri.refund_code = a.refund_code
				AND ri.option_seq >0
				AND ri.option_seq = io.item_option_seq
				AND io.provider_seq > 1),0)
			) AS option_commission_price_sum_krw,
			(
				IFNULL((SELECT SUM( IFNULL(ois.commission_price_krw,0) * IFNULL(ri.ea,0) )
				FROM fm_order_refund_item ri, fm_order_item_suboption ois, fm_order_item_option io
				WHERE ri.refund_code = a.refund_code
				AND ri.suboption_seq >0
				AND ri.suboption_seq = ois.item_suboption_seq
				AND io.item_option_seq = ois.item_option_seq
				AND io.provider_seq > 1),0)
			) AS suboption_commission_price_sum_krw,
			SUM( if( a.refund_type = 'cancel_payment', a.refund_price, 0 ) ) AS cancel_price_sum,
			SUM( if( a.refund_type = 'cancel_payment', 1, 0 ) ) AS cancel_count_sum,
			SUM( if( a.refund_type = 'return', a.refund_price, 0 ) ) AS return_price_sum,
			SUM( if( a.refund_type = 'return', 1, 0 ) ) AS return_count_sum,
			a.refund_date,
			b.sitetype
		FROM fm_order_refund AS a
		LEFT JOIN fm_order AS b ON a.order_seq = b.order_seq
		WHERE a.order_seq >0
			AND b.step BETWEEN '15'	AND '85'
			AND a.status = 'complete'
			" . $addWhere . "
		GROUP BY a.refund_code
		";

		$query		= $this->db->query($sql);
		$result		= $query->result_array();
		$refund_arr = array();

		foreach($result as $k => $val){
			$refund_arr[] = $val['refund_code'];
		}

		if(count($refund_arr) > 0){
			$rf_sql = "
					SELECT
						refund_code,
						IF(oi.provider_seq >1,'P','M')				AS provider_type,
						IF(oi.provider_seq = 1, SUM(ri.ea), 0)		AS M_refund_ea,
						IF(oi.provider_seq > 1, SUM(ri.ea), 0)		AS P_refund_ea,
						IF(oi.provider_seq = 1, SUM(ri.refund_goods_price), 0)
																	AS M_refund_goods_price,
						IF(oi.provider_seq > 1, SUM(ri.refund_goods_price), 0)
																	AS P_refund_goods_price,
						IF(oi.provider_seq = 1, SUM(ri.refund_delivery_price), 0)
																	AS M_refund_delivery_price,
						IF(oi.provider_seq > 1, SUM(refund_delivery_price), 0)
																	AS P_refund_delivery_price
					FROM
						fm_order_refund_item AS ri LEFT JOIN fm_order_item AS oi
						ON ri.item_seq = oi.item_seq
					WHERE
						refund_code IN ('".implode("','",$refund_arr)."')
					GROUP BY
						refund_code, IF(oi.provider_seq >1,'P','M')
				";
			$rf_query	= $this->db->query($rf_sql);
			$rf_res		= $rf_query->result_array();

			foreach($rf_res as $k => $val){
				$rf_arr[$val['refund_code']][$val['provider_type']] = $val;
			}

			// 최종 결과 조합 :: 2015-10-05 lwh
			foreach($result as $refund){
				$m_add_price	= 0;
				$p_add_price	= 0;
				$rf_obj			= $rf_arr[$refund['refund_code']];
				$total			= $rf_obj['M']['M_refund_ea'] + $rf_obj['P']['P_refund_ea'];

				// 전체 나눌 금액
				$remain_price = $refund['refund_emoney_sum'] + $refund['refund_cash_sum'];
				if($remain_price > 0){
					// 각각 백분율 계산
					$m_per	= round(($rf_obj['M']['M_refund_ea'] / $total) * 100);
					$p_per	= round(($rf_obj['P']['P_refund_ea'] / $total) * 100);

					// 각각의 추가될 금액
					$m_add_price = round(($remain_price * $m_per / 100));
					$p_add_price = round(($remain_price * $p_per / 100));
				}

				$refund['m_refund_price'] = $rf_obj['M']['M_refund_goods_price'] + $rf_obj['M']['M_refund_delivery_price'] + $m_add_price;
				$refund['p_refund_price'] = $rf_obj['P']['P_refund_goods_price'] + $rf_obj['P']['P_refund_delivery_price'] + $p_add_price;

				$return[] = $refund;
			}
		}

		return $return;
	}

	/* 구매통계 환불 집계 데이터 삽입 :: 2014-08-08 lwh */
	public function set_accumul_sales_refund($data){
		$this->db->insert("fm_accumul_sales_refund",$data);
	}

	/* 구매통계 환불 집계 데이터 삭제 :: 2014-08-08 lwh */
	public function delete_accumul_sales_refund($sdate='', $edate=''){
		if($sdate && $edate){
			$sql = "delete from fm_accumul_sales_refund where refund_date between '".$sdate."' and '".$edate."' and refund_type = 'A'";

			$query	= $this->db->query($sql);
		}
	}

	/* 구매통계 카테고리/브랜드 통계 데이터 :: 2014-08-11 lwh */
	public function get_sales_category($type='C', $sdate='', $edate=''){

		if($sdate=='' || $edate==''){
			$sdate	= date('Y-m-d');
			$edate	= date('Y-m-d');
		}

		if($type == 'C'){
			$tb_cl_name	= 'fm_category_link';
			$tb_c_name	= 'fm_category';
		}else{
			$tb_cl_name	= 'fm_brand_link';
			$tb_c_name	= 'fm_brand';
		}

		$addWhere	= " and o.deposit_date between '".$sdate." 00:00:00' and '".$edate." 23:59:59' ";

		$sql = "
		select
			category_name,category_code,month_date,day_date,count(*) as cnt,sum(price) as price,deposit_date
		from  (
			select
				c.title as category_name,
				c.category_code as category_code,
				SUBSTRING(o.deposit_date, 6, 2)	as month_date,
				SUBSTRING(o.deposit_date, 9, 2) as day_date,
				sum(oio.price*oio.ea) as price,
				o.deposit_date
			from
				fm_order as o,
				fm_order_item as oi INNER JOIN fm_order_item_option as oio
				on ( oi.item_seq = oio.item_seq ),
				".$tb_cl_name."	as cl,
				".$tb_c_name."	as c
			where
				o.order_seq = oi.order_seq and
				oi.item_seq = oio.item_seq and
				oi.goods_seq = cl.goods_seq and
				cl.category_code = c.category_code and
				cl.link = 1 and o.deposit_yn = 'y' and
				o.step between '25' and '75'
				" . $addWhere . "
			group by o.order_seq,cl.category_code, month_date, day_date
		) k
		group by k.category_code
		order by month_date, day_date
		";

		$query	= $this->db->query($sql);

		return $query->result_array();
	}

	/* 구매통계 환불 집계 데이터 삽입 :: 2014-08-08 lwh */
	public function set_accumul_sales_category($data){
		$this->db->insert("fm_accumul_stats_category",$data);
	}

	/* 구매통계 환불 집계 데이터 삭제 :: 2014-08-08 lwh */
	public function delete_accumul_sales_category($sdate='', $edate=''){
		if($sdate && $edate){
			$sql = "delete from fm_accumul_stats_category where deposit_date between '".$sdate." 00:00:00' and '".$edate." 23:59:59'";

			$query	= $this->db->query($sql);
		}
	}

	/* 입점사별 구매통계 :: 2015-04-24 pjm 수정 */
	public function seller_sales_stat($param){
		
		// 마이그레이션 날짜에맞춰서 통계가 노출되어야하기 때문에 추가 :: 2019-01-21 lkh
		$this->load->helper('accountall');
		$sdate				= $param['sdate'];
		if(!$tb_act_ym) $tb_act_ym	= str_replace("-","",substr($sdate,0,7));
		$accountAllMiDate			= getAccountSetting();
		$accountAllStatsV2			= $accountAllMiDate['accountall_stats_v2'];		// 통계 개선 2019-06-19 by hed
		
		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 시작 by hed 2019-06-19 #34379
		// ==========================================================================
		if($accountAllStatsV2 && date("Ym",$accountAllStatsV2) <= $tb_act_ym){
			$params_stats_v2 = array();
			$params_stats_v2['sdate']				= $param['sdate'];
			$params_stats_v2['edate']				= $param['edate'];
			$params_stats_v2['sitetype']			= $param['sitetype'];
			$params_stats_v2['provider_seq']		= $param['provider_seq'];
			
			$this->load->model('accountallmodel');
			$statsData = $this->accountallmodel->get_sales_stat_v2($params_stats_v2);
			return $statsData;
		}
		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 종료 by hed 2019-06-19 #34379
		// ==========================================================================
		
		// 주문 목록
		$order_sql	= "
			SELECT
				i.provider_seq,
				sum( (IFNULL(io.price,0) * IFNULL(io.ea,0)) + (IFNULL(isubo.price,0) * IFNULL(isubo.ea,0)) ) AS price_sum,
				sum( IFNULL(io.coupon_sale,0) + ( IFNULL(io.member_sale,0) * IFNULL(io.ea,0) ) + IFNULL(io.fblike_sale,0) + IFNULL(io.mobile_sale,0) + IFNULL(io.promotion_code_sale,0) + IFNULL(io.referer_sale,0) ) AS sale_sum,
				sum( IFNULL(os.shipping_cost,0) ) AS shipping_sum,
				i.goods_seq,
				sum(IFNULL(io.ea,0)) as total_ea
			FROM
				fm_order_item_option	as io
				left join fm_order_item_suboption	as isubo
						on io.order_seq=isubo.order_seq and io.item_seq=isubo.item_seq
						and io.item_option_seq=isubo.item_option_seq
				left join fm_order_item  as  i on i.item_seq=io.item_seq
				left join fm_order_shipping as os on i.shipping_seq = os.shipping_seq
			WHERE
				io.order_seq IN (
					SELECT order_seq
					FROM fm_order
					WHERE
						deposit_date BETWEEN '".$param['sdate']." 00:00:00' AND '".$param['edate']." 23:59:59' AND
						deposit_yn = 'y' AND
						sitetype IN ('".implode('\',\'',$param['sitetype'])."') AND
						step BETWEEN '15' AND '85'
				)
			GROUP BY
				i.goods_seq, i.provider_seq
			";

		$order_query	= $this->db->query($order_sql);
		$order_list		= $order_query->result_array();

		$provider_sql	= "
			SELECT provider_seq, provider_id, provider_name
			FROM fm_provider
			WHERE provider_id = 'base' OR manager_yn = 'Y'
			ORDER BY provider_seq ASC
			";
		$query			= $this->db->query($provider_sql);
		$provider_list	= $query->result_array();

		foreach($order_list as $data){
			$pseq									= $data['provider_seq'];
			$return[$pseq]['price_sum']				+= $data['price_sum'];
			$return[$pseq]['sale_sum']				+= $data['sale_sum'];
			$return[$pseq]['shipping_sum']			+= $data['shipping_sum'];
			$return[$pseq]['total_ea']				+= $data['total_ea'];
			$return[$pseq]['goods_ea'][$data['goods_seq']]	+= $data['total_ea'];
		}

		// 반품 환불 목록
		$refund_data	= $this->refund_stat($param);

		foreach($refund_data['refund'] as $data){
			$pseq									= $data['provider_seq'];
			$return[$pseq]['refund_price']			= $data['refund_price'];
		}

		foreach($refund_data['return'] as $data){
			$pseq									= $data['provider_seq'];
			$return[$pseq]['return_shipping_price']	= $data['return_shipping_price'];
		}


		foreach($provider_list as $data){
			$pseq									= $data['provider_seq'];
			$return[$pseq]['provider_seq']			= $data['provider_seq'];
			$return[$pseq]['provider_name']			= $data['provider_name'];
			$return[$pseq]['provider_id']			= $data['provider_id'];

			$return_data[]	= $return[$pseq];
		}

		return $return_data;
	}

	/* 입점사 상품별 구매통계 :: 2015-04-24 pjm 수정 */
	public function seller_goods_stat($param){
		
		// 마이그레이션 날짜에맞춰서 통계가 노출되어야하기 때문에 추가 :: 2019-01-21 lkh
		$this->load->helper('accountall');
		$sdate				= $param['sdate'];
		if(!$tb_act_ym) $tb_act_ym	= str_replace("-","",substr($sdate,0,7));
		$accountAllMiDate			= getAccountSetting();
		$accountAllStatsV2			= $accountAllMiDate['accountall_stats_v2'];		// 통계 개선 2019-06-19 by hed
		
		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 시작 by hed 2019-06-19 #34379
		// ==========================================================================
		if($accountAllStatsV2 && date("Ym",$accountAllStatsV2) <= $tb_act_ym){
			$params_stats_v2 = array();
			$params_stats_v2['sdate']				= $param['sdate'];
			$params_stats_v2['edate']				= $param['edate'];
			$params_stats_v2['sitetype']			= $param['sitetype'];
			$params_stats_v2['provider_seq']		= $param['provider_seq'];
			
			$this->load->model('accountallmodel');
			$statsData = $this->accountallmodel->get_goods_stat_v2($params_stats_v2);
			return $statsData;
		}
		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 종료 by hed 2019-06-19 #34379
		// ==========================================================================
		
		$sql = "
			SELECT
				i.goods_name, i.goods_seq,
				sum( IFNULL(io.ea,0) ) as ea_sum,
				sum( (IFNULL(io.price,0) * IFNULL(io.ea,0)) + (IFNULL(isubo.price,0) * IFNULL(isubo.ea,0)) ) AS price_sum,
				sum( IFNULL(io.coupon_sale,0) + ( IFNULL(io.member_sale,0) * IFNULL(io.ea,0) ) + IFNULL(io.fblike_sale,0) + IFNULL(io.mobile_sale,0) + IFNULL(io.promotion_code_sale,0) + IFNULL(io.referer_sale,0) ) AS sale_sum
			FROM
				fm_order_item_option	as io
				left join fm_order_item_suboption	as isubo
						on io.order_seq=isubo.order_seq and io.item_seq=isubo.item_seq
						and io.item_option_seq=isubo.item_option_seq
				left join fm_order_item  as  i on i.item_seq=io.item_seq
			WHERE
				i.provider_seq = '".$param['provider_seq']."' AND
				io.order_seq IN (
					SELECT order_seq
					FROM fm_order
					WHERE
						deposit_date BETWEEN '".$param['sdate']." 00:00:00' AND '".$param['edate']." 23:59:59' AND
						deposit_yn = 'y' AND
						sitetype IN ('".implode('\',\'',$param['sitetype'])."') AND
						step BETWEEN '15' AND '85'
				)
			group by i.goods_seq
			";

		$query			= $this->db->query($sql);
		$list			= $query->result_array();

		return $list;
	}

	//상품별 배송그룹 배송비계산 @2016-08-30 ysm
	public function seller_goods_shipping_code($param){
		$sql = "
			SELECT
				sum( IFNULL(os.shipping_cost,0) ) AS shipping_sum
			FROM
				fm_order_item as i
				left join fm_order_shipping as os on i.shipping_seq = os.shipping_seq
			WHERE
				i.goods_seq = '".$param['goods_seq']."' AND
				i.provider_seq = '".$param['provider_seq']."' AND
				i.order_seq IN (
					SELECT order_seq
					FROM fm_order
					WHERE
						deposit_date BETWEEN '".$param['sdate']." 00:00:00' AND '".$param['edate']." 23:59:59' AND
						deposit_yn = 'y' AND
						sitetype IN ('".implode('\',\'',$param['sitetype'])."') AND
						step BETWEEN '15' AND '85'
				)
			group by i.goods_seq
			";
		$query			= $this->db->query($sql);
		$list			= $query->row_array();

		return $list;
	}

	/* 반품 환불 목록 :: 2014-12-29 lwh */
	public function refund_stat($param){

		// 마이그레이션 날짜에맞춰서 통계가 노출되어야하기 때문에 추가 :: 2019-01-21 lkh
		$this->load->helper('accountall');
		$sdate				= $param['sdate'];
		if(!$tb_act_ym) $tb_act_ym	= str_replace("-","",substr($sdate,0,7));
		$accountAllMiDate			= getAccountSetting();
		$accountAllStatsV2			= $accountAllMiDate['accountall_stats_v2'];		// 통계 개선 2019-06-19 by hed

		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 시작 by hed 2019-06-19 #34379
		// ==========================================================================
		if($accountAllStatsV2 && date("Ym",$accountAllStatsV2) <= $tb_act_ym){
			$params_stats_v2 = array();
			$params_stats_v2['sdate']				= $param['sdate'];
			$params_stats_v2['edate']				= $param['edate'];
			$params_stats_v2['sitetype']			= $param['sitetype'];
			$params_stats_v2['provider_seq']		= $param['provider_seq'];
			$params_stats_v2['goods_seq']			= $param['goods_seq'];

			$this->load->model('accountallmodel');
			$result_stat = $this->accountallmodel->refund_stat_v2($params_stats_v2);
			return $result_stat;
		}
		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 종료 by hed 2019-06-19 #34379
		// ==========================================================================

		if($param['provider_seq']){
			$providerRefund	= " AND rp.provider_seq = '".$param['provider_seq']."' ";
			$providerReturn	= " AND	i.provider_seq = '".$param['provider_seq']."' ";
		}else{
			$providerRefund = "";
			$providerReturn = "";
		}

		// 환불 목록
		/*
		$refund_sql	= "
			SELECT
				i.provider_seq, sum(r.refund_price) as refund_price
			FROM
				fm_order_refund			as r,
				fm_order_refund_item	as ri,
				fm_order_item			as i
			WHERE
				r.refund_code = ri.refund_code AND
				ri.item_seq = i.item_seq AND
				r.status = 'complete' AND
				r.refund_date BETWEEN '".$param['sdate']."' AND '".$param['edate']."'
			GROUP BY
				i.provider_seq
			";
		*/
		$refund_sql	= "
			SELECT
				rp.provider_seq, sum(IFNULL(rp.refund_price,0)) as refund_price
			FROM
				fm_order_refund_provider	as rp,
				fm_order_refund				as r
			WHERE
				rp.refund_code = r.refund_code AND
				r.status = 'complete' AND
				r.refund_date BETWEEN '".$param['sdate']."' AND '".$param['edate']."'
				" . $providerRefund . "
			GROUP BY
				rp.provider_seq
			";

		$refund_query	= $this->db->query($refund_sql);
		$refund_list	= $refund_query->result_array();

		// 반품 목록
		$return_sql	= "
			SELECT
				i.provider_seq, sum(IFNULL(return_shipping_price,0)) as return_shipping_price
			FROM
				fm_order_return			as r,
				fm_order_refund_item	as ri,
				fm_order_item			as i
			WHERE
				r.refund_code = ri.refund_code AND
				ri.item_seq = i.item_seq AND
				r.return_date BETWEEN '".$param['sdate']."' AND '".$param['edate']."'
				" . $providerReturn . "
			GROUP BY
				i.provider_seq
			";

		$return_query	= $this->db->query($return_sql);
		$return_list	= $return_query->result_array();

		$result_stat['refund']	= $refund_list;
		$result_stat['return']	= $return_list;

		return $result_stat;
	}

	public function stats_epc_insert($stats_type,$stats,$month_stats){
		$insert_param['stats_type']		= $stats_type;
		$insert_param['stats_date']		= $stats['s_date'];
		$insert_param['regist_date']	= $stats['now_date'];
		$insert_param['stats_year']		= $stats['s_date_year'];
		$insert_param['stats_month']	= $stats['s_date_month'];
		$insert_param['before_total']	= $stats['before_total'] ? $stats['before_total'] : 0;
		$insert_param['plus']			= $month_stats['plus'] ? $month_stats['plus'] : 0;
		$insert_param['minus']			= $month_stats['minus'] ? $month_stats['minus'] : 0;
		$insert_param['limits']			= $month_stats['end'] ? $month_stats['end'] : 0;
		$insert_param['after_total']	= ($insert_param['before_total']+$insert_param['plus'])-$insert_param['minus']-$insert_param['limits'];

		$this->db->insert('fm_stats_epc', $insert_param);
		return $insert_param['after_total'];
	}

	public function isOverlap_epc($year,$month,$stats_type){
		$ret	= false;
		$que	= "select count(*) cnt from fm_stats_epc where stats_year = '{$year}' and stats_month = '{$month}' and stats_type = '{$stats_type}'";
		$query	= mysqli_query($this->db->conn_id,$que);
		$res	= mysqli_fetch_assoc($query);
		if($res['cnt'] > 0) $ret = true;
		return $ret;
	}

	public function get_stats_epc($start,$end,$stats_type){
		$table = 'fm_'.$stats_type;
		
		//2018-02-13 적립금 통계 수정
		if($stats_type != 'emoney' && $stats_type != 'point'){
			$que = "SELECT gb, sum( ifnull( ".$stats_type.", 0 ) ) ".$stats_type.", sum(
						CASE WHEN limit_date IS NOT NULL AND limit_date != ''AND limit_date < '".$end."' THEN
							ifnull( ".$stats_type.", 0 )
						ELSE 0
						END ) end_epc
				FROM {$table}
				WHERE
					regist_date >= '".$start."'
					and regist_date < '".$end."'
				GROUP BY gb
			";
			$epc_arr = array();
	
			$query		= mysqli_query($this->db->conn_id,$que);
			while($data = mysqli_fetch_assoc($query)){
				$epc_arr[$data['gb']]	= $data[$stats_type];
				$epc_arr['end']			+= $data['end_epc'];
			}
		}else{
			$que = "SELECT gb, sum( ifnull( ".$stats_type.", 0 ) ) ".$stats_type.", 0 as end_epc
			FROM {$table} 
			WHERE
				regist_date >= '".$start."'
				and regist_date < '".$end."'
			GROUP BY gb

			union

            SELECT 'end' as gb, sum( ifnull( remain, 0 ) ) ".$stats_type.", 0 as end_epc
			FROM {$table} 
			WHERE
				limit_date >= '".$start."'
				and limit_date < '".$end."'
			";
			$epc_arr = array();
			
			$query		= mysqli_query($this->db->conn_id,$que);
			while($data = mysqli_fetch_assoc($query)){
				$epc_arr[$data['gb']]	= $data[$stats_type];
			}
		}

		return $epc_arr;
	}

	public function get_stats_epc_accumulate($stats_type){
		$now_date		= date('Y-m-01');
		$end_date		= date('Y-m-d');
		$accumulate		 = 0;

		$sql = "select ifnull(after_total,0) as after_total from fm_stats_epc where stats_type = '{$stats_type}' order by stats_date desc limit 1";

		$query			= $this->db->query($sql);
		if($query) $query			= $query->row_array();

		$month_stats	= $this->get_stats_epc($now_date,$end_date,$stats_type);
		$plus			= $month_stats['plus'] ? $month_stats['plus'] : 0;
		$minus			= $month_stats['minus'] ? $month_stats['minus'] : 0;
		$limit			= $month_stats['end'] ? $month_stats['end'] : 0;
		$accumulate		= ($query['after_total']+$plus)-$minus-$limit;
		return $accumulate;
	}

	// 입점사 정산 금액 추출 :: 2015-09-30 lwh
	public function get_stat_provider_account($params){
		if($params) foreach($params as $k => $v){$$k	= $v;	}

		if($acc_view == 'Y')	$date = 'regist_date';
		else					$date = 'acc_date';
		$query = "
		SELECT
			SUM(acc_price) AS acc_price,
			DATE_FORMAT(STR_TO_DATE(".$date.",'%Y-%m-%d'), '%Y') AS stats_year,
			DATE_FORMAT(STR_TO_DATE(".$date.",'%Y-%m-%d'), '%m') AS stats_month
		FROM `fm_account`
		WHERE
			acc_status = 'complete' AND
			YEAR(STR_TO_DATE(".$date.",'%Y-%m-%d')) = '".$year."'
		GROUP BY
			DATE_FORMAT(STR_TO_DATE(".$date.",'%Y-%m-%d'), '%Y-%m')
		";

		return $this->db->query($query);
	}

	// 상품 장바구니 통계 삭제
	public function delete_accumul_cart_stats($stats_date){
		$this->db->where(array('stats_date' => $stats_date));
		$this->db->delete('fm_accumul_cart_stats');
	}

	// 상품 장바구니 집계 저장
	public function set_accumul_cart_stats($stats_date){
		$sql	= "SELECT *, SUM(IFNULL(ea, 0)) as option_ea, count(*) as option_cnt "
				. "FROM `fm_cart_stats` "
				. "WHERE regist_ymd='" . $stats_date . "' "
				. "GROUP BY goods_seq, option1, option2, option3, option4, option5";
		$query	= $this->db->query($sql);
		if	($query){
			foreach ($query->result_array() as $k => $data){
				unset($insParams, $binds);
				$binds	= array($data['goods_seq'], $data['option1'], $data['option2'], 
								$data['option3'], $data['option4'], $data['option5']);

				$sql	= "SELECT count(*) as user_cnt, SUM(IFNULL(ea,0)) as user_ea "
						. "FROM `fm_cart_stats` "
						. "WHERE (userid IS NOT NULL and TRIM(userid) != '' and LENGTH(userid) > 0 ) "
						. "AND goods_seq = ? AND option1 = ? AND option2 = ? AND option3 = ? "
						. "AND option4 = ? AND option5 = ? "
						. "AND regist_ymd = '" . $stats_date . "' "
						. "GROUP BY goods_seq, option1, option2, option3, option4, option5 ";
				$query	= $this->db->query($sql, $binds);
				$user	= $query->row_array();

				$insParams['stats_date']		= $stats_date;
				$insParams['goods_seq']			= $data['goods_seq'];
				$insParams['goods_name']		= $data['goods_name'];
				$insParams['option1']			= $data['option1'];
				$insParams['option2']			= $data['option2'];
				$insParams['option3']			= $data['option3'];
				$insParams['option4']			= $data['option4'];
				$insParams['option5']			= $data['option5'];
				$insParams['option_ea']			= (int) $data['option_ea'];
				$insParams['option_user_ea']	= (int) $user['user_ea'];
				$insParams['option_cnt']		= (int) $data['option_cnt'];
				$insParams['option_user_cnt']	= (int) $user['user_cnt'];
				$this->db->insert('fm_accumul_cart_stats', $insParams);
			}

			return true;
		}else{
			return false;
		}
	}

	// 날짜에 맞춰 해당 정산테이블을 가져오는 함수 :: 2018-07-27 pjw
	public function get_stat_table($edate){
		// 끝 날짜 기준으로 테이블이름을 가져온다.
		$tb_prefix  = 'fm_account_calculate_';
		$endYM		= date("Ym",strtotime($edate));
		$table_name	= $tb_prefix.$endYM;
		
		// 실제 존재하는 테이블인지 검사
		$sql = "SHOW TABLES LIKE '".$table_name."'";
		$query			= $this->db->query($sql);
		if($query) $query			= $query->result_array();
		
		// 테이블이 없는 경우 빈값처리
		if(count($query) == 0){
			$table_name = '';
		}

		return $table_name;
	}
	
	// [판매지수 EP] 결제완료 시 주문정보 EP 저장 :: 2018-09-14 pjw
	public function set_order_sale_ep($order_seq){
		if(!$this->visitorlog) $this->load->model('visitorlog');
		if(!$this->ordermodel) $this->load->model('ordermodel');

		// 기처리 된 건인지 확인
		if($this->check_ep_sales_done($order_seq, false)) return false;
		
		$items			= $this->ordermodel->get_item($order_seq);				// 해당 주문번호에 대한 상품목록		
		$sales_referer	= $this->visitorlog->get_sales_ep_referer_domains();	// 네이버 EP로 넘어오는 referer 배열		
		$ep_data		= array();												// EP 저장할 결과값
		$now			= date('Y-m-d');										// 당일 날짜

		// 상품 수량, 금액
		foreach($items as $item){

			// 통계에 넣을 데이터 세팅			
			$goods_seq			= $item['goods_seq'];
			$referer_domain		= $item['referer_domain'];
			$total_ea			= 0;
			$total_price		= 0;		

			// 네이버쇼핑 유입이 아닌 경우 블락
			if( !in_array($referer_domain, $sales_referer) ) continue;
			
			// 상품 필수옵션, 추가옵션 가져옴
			$options			= $this->ordermodel->get_option_for_item($item['item_seq']);
			$suboptions			= $this->ordermodel->get_suboption_for_item($item['item_seq']);

			// 필수옵션별 데이터 세팅
//			debug($options);
			foreach($options as $option){
				// 결제 확인 이하 배송완료 이상일 경우 블락
				if($option['step'] < 25 || $option['step'] > 75 ) continue;
				
				$total_ea			+= (int)$option['ea'];
				$total_price		+= (int)$option['sale_price'] * (int)$option['ea'];

				// 마일리지, 예치금 할인 차감
				$total_price -= $option['cash_sale_unit'] * $option['ea'] + $option['cash_sale_rest'];
				$total_price -= $option['emoney_sale_unit'] * $option['ea'] + $option['emoney_sale_rest'];
			}
			
			// 추가옵션별 데이터 세팅
			foreach($suboptions as $suboption){
				// 결제 확인 이하 배송완료 이상일 경우 블락
				if($suboption['step'] < 25 || $suboption['step'] > 75 ) continue;
				
				$total_ea			+= (int)$suboption['ea'];
				$total_price		+= (int)$suboption['sale_price'] * (int)$suboption['ea'];

				// 마일리지, 예치금 할인 차감
				$total_price -= $suboption['cash_sale_unit'] * $suboption['ea'] + $suboption['cash_sale_rest'];
				$total_price -= $suboption['emoney_sale_unit'] * $suboption['ea'] + $suboption['emoney_sale_rest'];
			}
			
			// 상품별 ep에 넣어줄 데이터 배열에 추가
			if($total_ea > 0 && $total_price > 0){
				$ep_data[$goods_seq]['sale_count']	= $total_ea;
				$ep_data[$goods_seq]['sale_price']	= $total_price;

				// 배송 그룹별 상품 목록 정의
				$shipping_list[$item['shipping_seq']][] = $item['goods_seq'];
			}
		}
		
		// ep 데이터가 있을 경우에만
		if(isset($ep_data) && !empty($ep_data)){
			// 배송비를 추가해서 총 합계 계산
			foreach($shipping_list as $shipping_seq => $goods_seq_list){
				$shipping = $this->ordermodel->get_seq_for_order_shipping($shipping_seq);
//				debug($shipping);
				
				// 배송그룹 내 상품 목록에서 첫번째인 상품에만 배송비를 추가하여 계산
				// * ep의 경우 상품별로 저장이 되고, 결과적으로 한가지 상품만 들어와야하기 때문에 이렇게 처리함
				$goods_seq = $goods_seq_list[0];

				// 총 금액에서 배송비 추가
				$ep_data[$goods_seq]['sale_price'] += $shipping['shipping_cost'] + $shipping['add_delivery_cost'];

				// 마일리지, 예치금 차감
				$ep_data[$goods_seq]['sale_price'] -= $shipping['cash_sale_unit'] + $shipping['cash_sale_rest'];
				$ep_data[$goods_seq]['sale_price'] -= $shipping['emoney_sale_unit'] + $shipping['emoney_sale_rest'];
				
				// 주문갯수 추가
				$ep_data[$goods_seq]['order_count'] = 1;
			}

			// 상품아이디별로 통계테이블에 저장
			foreach($ep_data as $goods_seq => $ep){
				foreach($ep as $type => $cnt){
					// 데이터 존재여부 검사
					$stats_goods = $this->get_ep_stats($type, $goods_seq, $now);
					// 기존 데이터가 있으면 update
					if(isset($stats_goods)){
						// EP 테이블 데이터
						$update_ep_data = array(
							'cnt'			=> (int)$stats_goods['cnt'] + $cnt						
						);

						$this->db->where('goods_stats_seq', $stats_goods['goods_stats_seq']);
						$this->db->update('fm_stats_goods', $update_ep_data);
					}else{
						// EP 테이블 데이터
						$insert_ep_data = array(
							'goods_seq'		=> $goods_seq,
							'type'			=> $type,
							'cnt'			=> $cnt,
							'stats_date'	=> $now
						);

						$this->db->insert('fm_stats_goods', $insert_ep_data);
						
					}
				}
			}

			// 처리완료 쿠키 저장
			$this->check_ep_sales_done($order_seq, true);
		}
	}

	// [판매지수 EP] 반품환불 및 결제취소 시 환불정보 EP 저장 :: 2018-09-14 pjw
	public function set_refund_sale_ep($refund_code){
		if(!$this->visitorlog)	$this->load->model('visitorlog');
		if(!$this->refundmodel) $this->load->model('refundmodel');
		if(!$this->ordermodel)	$this->load->model('ordermodel');
		
		$refund			= $this->refundmodel->get_refund($refund_code);
		$refund_items	= $this->refundmodel->get_refund_item($refund_code);
		$sales_referer	= $this->visitorlog->get_sales_ep_referer_domains();
		$refund_able_ea = $this->refundmodel->get_refund_able_ea($refund['order_seq']);
		$ep_data		= array();
		$now			= date('Y-m-d');
		
		// 상품 수량, 금액
		foreach($refund_items as $refund_item){

			// 통계에 넣을 데이터 세팅			
			$goods_seq			= $refund_item['goods_seq'];
			$referer_domain		= $refund_item['referer_domain'];
			$total_ea			= 0;
			$total_price		= 0;		

			// 네이버쇼핑 유입이 아닌 경우 블락
			if( !in_array($referer_domain, $sales_referer) ) continue;
			
			// 실제 환불된 총 수량 및 총 금액
			$total_ea		+= (int) $refund_item['ea'];
			$total_price	+= (int) $refund_item['refund_goods_price'] + $refund_item['refund_delivery_price'];
			
			// 상품별 ep에 넣어줄 데이터 배열에 추가
			if($total_ea > 0 && $total_price > 0){
				$ep_data[$goods_seq]['sale_count']	= $total_ea;
				$ep_data[$goods_seq]['sale_price']	= $total_price;
			
				// 환불가능수량이 0 인경우 (모두 환불됨) order_count 1 차감
				if($refund_able_ea == 0){
					$ep_data[$goods_seq]['order_count'] = 1;
				}
			}
			
		}
		
		// 상품아이디별로 통계테이블에 저장
		if(isset($ep_data) && !empty($ep_data)){
			foreach($ep_data as $goods_seq => $ep){
				foreach($ep as $type => $cnt){
					// 데이터 존재여부 검사
					$stats_goods = $this->get_ep_stats($type, $goods_seq, $now);
					// 기존 데이터가 있으면 update
					if(!empty($stats_goods)){
						// EP 테이블 데이터
						$update_ep_data = array(
							'cnt'			=> (int)$stats_goods['cnt'] - $cnt						
						);

						$this->db->where('goods_stats_seq', $stats_goods['goods_stats_seq']);
						$this->db->update('fm_stats_goods', $update_ep_data);
					}else{
						// EP 테이블 데이터
						$insert_ep_data = array(
							'goods_seq'		=> $goods_seq,
							'type'			=> $type,
							'cnt'			=> $cnt * -1,
							'stats_date'	=> $now
						);

						$this->db->insert('fm_stats_goods', $insert_ep_data);
					}
				}
			}
		}
	}

	// [판매지수 EP] 해당 주문번호 쿠키값을 확인하여 기처리건 검사 :: 2018-09-18 pjw
	public function check_ep_sales_done($order_seq, $need_save = false){
		$this->load->helper('cookie');
		$ep_ord = explode(',', get_cookie('ep_ord'));

		if( !in_array($order_seq, $ep_ord) ){
			if($need_save) 
				$this->save_ep_sales_cookie($ep_ord, $order_seq);
			return false;
		}else{
			return true;
		}
	}
	
	// [판매지수 EP] 해당 주문번호 쿠키값 세팅함수 :: 2018-09-18 pjw
	public function save_ep_sales_cookie($ep_ord, $order_seq){
		$ep_ord[] = $order_seq;
		set_cookie('ep_ord', implode(',', $ep_ord), 1440);
	}

	
	// [판매지수 EP] 기존 당일 EP데이터가 있는지 조회 :: 2018-09-14 pjw
	public function get_ep_stats($type, $goods_seq, $now){
		$query		= "select * from fm_stats_goods where 1=1 and type = '".$type."' and goods_seq = ".$goods_seq." and stats_date = '".$now."'";
		$query		= mysqli_query($this->db->conn_id, $query);
		$stats_data	= mysqli_fetch_assoc($query);

		return $stats_data;
	}

	// [판매지수 EP] 당일 EP데이터 조회 :: 2018-09-19 pjw
	public function get_ep_stats_yesterday(){
		$before_date	= strtotime("-1 days");
		$dt				= date('Y-m-d', $before_date);
		
		$query			= "select * from fm_stats_goods where 1=1 and stats_date = '".$dt."' and type in ( 'sale_count', 'sale_price', 'order_count' )";
		$stats_data		= mysqli_query($this->db->conn_id, $query);
		
		return $stats_data;
	}

	/* 매장별 구매통계 :: 2018-11-01 hed */
	public function o2o_sales_stat($param){

		// 주문 목록
		$order_sql	= "
			SELECT
				o.linkage_mall_code,
				sum( (IFNULL(io.price,0) * IFNULL(io.ea,0)) + (IFNULL(isubo.price,0) * IFNULL(isubo.ea,0)) ) AS price_sum,
				sum( 
					( (IFNULL(io.coupon_sale_unit,0) * IFNULL(io.ea,0)) + IFNULL(io.coupon_sale_rest,0)  )
					+ ( (IFNULL(io.fblike_sale_unit,0) * IFNULL(io.ea,0)) + IFNULL(io.fblike_sale_rest,0)  )
					+ ( (IFNULL(io.mobile_sale_unit,0) * IFNULL(io.ea,0)) + IFNULL(io.mobile_sale_rest,0)  )
					+ ( IFNULL(io.member_sale,0) * IFNULL(io.ea,0) )
					+ IFNULL(io.promotion_code_sale,0 )
					+ ( (IFNULL(io.referer_sale_unit,0) * IFNULL(io.ea,0)) + IFNULL(io.referer_sale_rest,0)  )
					+ IFNULL(io.event_sale,0)
					+ IFNULL(io.multi_sale,0)
				) AS sale_sum,
				sum( IFNULL(os.shipping_cost,0) ) AS shipping_sum,
				i.goods_seq,
				sum(IFNULL(io.ea,0)) as total_ea
			FROM
				fm_order_item_option	as io
				left join fm_order_item_suboption	as isubo
						on io.order_seq=isubo.order_seq and io.item_seq=isubo.item_seq
						and io.item_option_seq=isubo.item_option_seq
				left join fm_order_item  as  i on i.item_seq=io.item_seq
				left join fm_order_shipping as os on i.shipping_seq = os.shipping_seq
				left join fm_order as o  on o.order_seq = i.order_seq
			WHERE
				io.order_seq IN (
					SELECT order_seq
					FROM fm_order
					WHERE
						deposit_date BETWEEN '".$param['sdate']." 00:00:00' AND '".$param['edate']." 23:59:59' AND
						deposit_yn = 'y' and 
						step BETWEEN '15' AND '85'
				)
				and o.linkage_id = 'pos'
			GROUP BY
				i.goods_seq, o.linkage_mall_code
			";

		$order_query	= $this->db->query($order_sql);
		$order_list		= $order_query->result_array();
		
		// 매장 정보 추출
		$this->load->library("o2o/o2oservicelibrary");
		$o2oStoreList = $this->o2oservicelibrary->get_o2o_config(array(),999);

		foreach($order_list as $data){
			$pseq									= $data['linkage_mall_code'];
			$return[$pseq]['price_sum']				+= $data['price_sum'];
			$return[$pseq]['sale_sum']				+= $data['sale_sum'];
			$return[$pseq]['shipping_sum']			+= $data['shipping_sum'];
			$return[$pseq]['total_ea']				+= $data['total_ea'];
			$return[$pseq]['goods_ea'][$data['goods_seq']]	+= $data['total_ea'];
		}

		// 반품 환불 목록
		$refund_data	= $this->o2o_refund_stat($param);

		foreach($refund_data['refund'] as $data){
			$pseq									= $data['linkage_mall_code'];
			$return[$pseq]['refund_price']			= $data['refund_price'];
		}

		foreach($refund_data['return'] as $data){
			$pseq									= $data['linkage_mall_code'];
			$return[$pseq]['return_shipping_price']	= $data['return_shipping_price'];
		}


		foreach($o2oStoreList as $data){
			$pseq									= $data['o2o_store_seq'];
			$return[$pseq]['o2o_store_seq']			= $data['o2o_store_seq'];
			$return[$pseq]['pos_name']				= $data['pos_name'];
			$return[$pseq]['store_seq']				= $data['store_seq'];

			$return_data[]	= $return[$pseq];
		}

		return $return_data;
	}
	
	/* 반품 환불 목록 :: 2018-11-01 hed */
	public function o2o_refund_stat($param){

		if($param['o2o_store_seq']){
			$o2o_storeRefund	= " AND o.linkage_mall_code = '".$param['o2o_store_seq']."' ";
			$o2o_storeReturn	= " AND	o.linkage_mall_code = '".$param['o2o_store_seq']."' ";
		}else{
			$o2o_storeRefund = "";
			$o2o_storeReturn = "";
		}

		// 환불 목록
		$refund_sql	= "
			SELECT
				o.linkage_mall_code, sum(IFNULL(r.refund_price,0)) as refund_price
			FROM
				fm_order_refund				as r,
				fm_order					as o
			WHERE
				o.linkage_id = 'pos' AND
				o.order_seq = r.order_seq AND
				r.status = 'complete' AND
				r.refund_date BETWEEN '".$param['sdate']." 00:00:00' AND '".$param['edate']." 23:59:59'
				" . $o2o_storeRefund . "
			GROUP BY
				o.linkage_mall_code
			";

		$refund_query	= $this->db->query($refund_sql);
		$refund_list	= $refund_query->result_array();

		// 반품 목록
		$return_sql	= "
			SELECT
				o.linkage_mall_code, sum(IFNULL(return_shipping_price,0)) as return_shipping_price
			FROM
				fm_order_return			as r,
				fm_order_refund_item	as ri,
				fm_order_item			as i,
				fm_order				as o
			WHERE
				o.linkage_id = 'pos' AND
				o.order_seq = r.order_seq AND
				r.refund_code = ri.refund_code AND
				ri.item_seq = i.item_seq AND
				r.return_date BETWEEN '".$param['sdate']." 00:00:00' AND '".$param['edate']." 23:59:59'
				" . $o2o_storeReturn . "
			GROUP BY
				o.linkage_mall_code
			";

		$return_query	= $this->db->query($return_sql);
		$return_list	= $return_query->result_array();

		$result_stat['refund']	= $refund_list;
		$result_stat['return']	= $return_list;

		return $result_stat;
	}
	

	/* 매장 상품별 구매통계 :: 2015-04-24 pjm 수정 */
	public function o2o_goods_stat($param){
		$sql = "
			SELECT
				i.goods_name, i.goods_seq,
				sum( IFNULL(io.ea,0) ) as ea_sum,
				sum( (IFNULL(io.price,0) * IFNULL(io.ea,0)) + (IFNULL(isubo.price,0) * IFNULL(isubo.ea,0)) ) AS price_sum,
				sum( 
					( (IFNULL(io.coupon_sale_unit,0) * IFNULL(io.ea,0)) + IFNULL(io.coupon_sale_rest,0)  )
					+ ( (IFNULL(io.fblike_sale_unit,0) * IFNULL(io.ea,0)) + IFNULL(io.fblike_sale_rest,0)  )
					+ ( (IFNULL(io.mobile_sale_unit,0) * IFNULL(io.ea,0)) + IFNULL(io.mobile_sale_rest,0)  )
					+ ( IFNULL(io.member_sale,0) * IFNULL(io.ea,0) )
					+ IFNULL(io.promotion_code_sale,0 )
					+ ( (IFNULL(io.referer_sale_unit,0) * IFNULL(io.ea,0)) + IFNULL(io.referer_sale_rest,0)  )
					+ IFNULL(io.event_sale,0)
					+ IFNULL(io.multi_sale,0)
				) AS sale_sum
			FROM
				fm_order_item_option	as io
				left join fm_order_item_suboption	as isubo
						on io.order_seq=isubo.order_seq and io.item_seq=isubo.item_seq
						and io.item_option_seq=isubo.item_option_seq
				left join fm_order_item  as  i on i.item_seq=io.item_seq
				left join fm_order  as  o on o.order_seq=i.order_seq
			WHERE
				o.linkage_id = 'pos' AND
				o.linkage_mall_code = '".$param['o2o_store_seq']."' AND
				io.order_seq IN (
					SELECT order_seq
					FROM fm_order
					WHERE
						deposit_date BETWEEN '".$param['sdate']." 00:00:00' AND '".$param['edate']." 23:59:59' AND
						deposit_yn = 'y' AND
						step BETWEEN '15' AND '85'
				)
			group by i.goods_seq
			";

		$query			= $this->db->query($sql);
		$list			= $query->result_array();

		return $list;
	}
	
	

	//상품별 배송그룹 배송비계산 2018-11-01 hed
	public function o2o_goods_shipping_code($param){
		$sql = "
			SELECT
				sum( IFNULL(os.shipping_cost,0) ) AS shipping_sum
			FROM
				fm_order_item as i
				left join fm_order_shipping as os on i.shipping_seq = os.shipping_seq
				left join fm_order as o on o.order_seq = i.order_seq
			WHERE
				o.linkage_id = 'pos' AND
				i.goods_seq = '".$param['goods_seq']."' AND
				o.linkage_mall_code = '".$param['order_store_seq']."' AND
				i.order_seq IN (
					SELECT order_seq
					FROM fm_order
					WHERE
						deposit_date BETWEEN '".$param['sdate']." 00:00:00' AND '".$param['edate']." 23:59:59' AND
						deposit_yn = 'y' AND
						step BETWEEN '15' AND '85'
				)
			group by i.goods_seq
			";
		$query			= $this->db->query($sql);
		$list			= $query->row_array();

		return $list;
	}


	//
	public function getSearchList($sKeyword, $enddate){
		$query = "select keyword, sum(cnt) cnt from fm_search_list where regist_date >= ? and keyword like ? group by `keyword` order by cnt desc limit 10";
		$query = $this->db->query($query,array($enddate, "%".$sKeyword."%"));
		foreach ($query->result_array() as $row){
			$row['key'] = $row["keyword"];
			$row['keyword'] = str_replace($sKeyword, "<span class=\"highlight\">".$sKeyword."</span>", htmlspecialchars($row["keyword"]));
			$aResult[] = $row;
		}
		return $aResult;
	}
}
