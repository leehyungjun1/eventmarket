<?php
class Eventmodel extends CI_Model {
	function __construct() {
		parent::__construct();

	}

	function is_event_template_file($template_path){
		return preg_match("/(.*)\/event[0-9]{7}.html$/",$template_path) ? true : false;
	}

	function is_gift_template_file($template_path){
		return preg_match("/(.*)\/gift[0-9]{7}.html$/",$template_path) ? true : false;
	}

	// 상품의 사은품이벤트 정보 가져오기
	public function get_gift_event_all($goods_seq)
	{
		$today = date('Y-m-d');
		$r_query[] = "
		select b.*,e.title,e.start_date,e.end_date from fm_gift_benefit b left join fm_gift e on b.gift_seq=e.gift_seq where
		e.gift_seq = b.gift_seq and e.goods_rule='all' and e.display='y' and e.start_date <= '$today' and e.end_date >= '$today' ";
		$r_query[] = "
		select b.*,e.title,e.start_date,e.end_date from fm_gift_benefit b left join fm_gift e on b.gift_seq=e.gift_seq where
		e.gift_seq = b.gift_seq and e.goods_rule='category' and e.display='y' and e.start_date <= '$today' and e.end_date >= '$today'
		and	(select count(*) from fm_gift_choice where gift_seq = b.gift_seq and choice_type = 'category' and goods_seq = '$goods_seq') > 0";
		$r_query[] = "
		select b.*,e.title,e.start_date,e.end_date from fm_gift_benefit b left join fm_gift e on b.gift_seq=e.gift_seq where
		e.gift_seq = b.gift_seq and e.goods_rule='goods_view' and e.display='y' and e.start_date <= '$today' and e.end_date >= '$today'
		and	(select count(*) from fm_gift_choice where gift_seq = b.gift_seq and choice_type = 'goods' and goods_seq = '$goods_seq') > 0";
		$query = 'select * from (('.implode(') union (',$r_query).')) t order by t.end_date asc';
		$query = $this->db->query($query);
		$result = $query->result_array();
		foreach($query->result_array() as $row){
			$groups[] = $row;
		}
		return $result;
	}

	public function get($params, $field_str='', $orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		if( $field_str ) $this->db->select($field_str);
		return $this->db->get('fm_event');
	}

	public function get_event($eventSeq){

		$this->db->select("* ,if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status");
		$query	= $this->db->get_where('fm_event',array('event_seq'=>$eventSeq));
		$result	= $query->row_array();

		return $result;
	}

	public function update_event_view($eventSeq){

		$sql	= "update fm_event set pageview=pageview+1 where event_seq = '".$eventSeq."' ";
		$this->db->query($sql);
	}

	public function get_solo_event_goods($eventSeq){
		$cache_item_id = sprintf('event_solo_goods_%s', $eventSeq);
		$result = cache_load($cache_item_id);
		if ($result === false) {
			$sql	= "select g.* from
							fm_event_choice as ec
							inner join fm_goods as g on ec.goods_seq = g.goods_seq
						where
							ec.event_seq = '".$eventSeq."' and
							ec.choice_type = 'goods'";
			$query	= $this->db->query($sql);
			$result	= $query->row_array();

			//
			if (! is_cli()) {
				cache_save($cache_item_id, $result);
			}
		}

		return $result;
	}

	public function update_solo_event_stnum($goods_seq, $st_num){
		if	($goods_seq > 0 && $st_num > 0){
			$sql	= "update fm_goods set event_st_num = '".$st_num."' where goods_seq = '".$goods_seq."' ";
			$this->db->query($sql);
		}
	}

	public function get_event_order_result($event_seq){
		$sql	= "select
					count(*)														 as cnt,
					sum(ifnull(opt.ea,0) + ifnull(sub.ea,0))						as ea,
					sum(ifnull((opt.price*opt.ea),0)+ifnull((sub.price*sub.ea),0))	as price
				from
					fm_order_item						as item
					inner join fm_order					as ord on item.order_seq = ord.order_seq
					inner join fm_order_item_option		as opt on ( item.item_seq = opt.item_seq and opt.step > 15 and opt.step < 80 )
					left join fm_order_item_suboption	as sub on ( opt.item_option_seq = sub.item_option_seq and sub.step > 15 and sub.step < 80 )
				where
					item.event_seq = '".$event_seq."'
				group by item.event_seq ";
		$query	= $this->db->query($sql);
		$result	= $query->row_array();

		return $result;

	}

	public function chk_solo_event_duple($param){
		if	($param['event_seq'])
			$addWhere	= " and evt.event_seq != '".$param['event_seq']."' ";

		$sql	= "select evt.event_seq
					from fm_event as evt
					inner join fm_event_choice	as chc on evt.event_seq = chc.event_seq
					where evt.event_type = 'solo' and ((evt.start_date between '".$param['start_date']."' and '".$param['end_date']."') or (evt.end_date between '".$param['start_date']."' and '".$param['end_date']."')) and chc.goods_seq = '".$param['goods_seq']."' ".$addWhere;
		$query	= $this->db->query($sql);
		$result	= $query->row_array();

		if	($result['event_seq'])	return true;
		else						return false;
	}

	public function get_event_list($sc){

		$sc['sort'] = (in_array($sc['sort'], ['asc',''] )) ? 'evt.event_seq desc' : $sc['sort'];
		$sc['keyword'] = $sc['keyword'];
		$sc['page'] = (isset($sc['page']) && $sc['page'] > 1) ? intval($sc['page']) : '0';
		$sc['perpage'] = (isset($sc['perpage'])) ? intval($sc['perpage']) : '10';
		if(isset($sc['search_text'])) $sc['search_mode']	= "search";

		if($sc['date'] == "all")				$sc['date']				= "";
		if($sc['sc_goods_type'] == "all")		$sc['sc_goods_type']	= "";
		if($sc['event_status'] == "all")		$sc['event_status']		= "";
		if($sc['sc_event_type'] == "all")		$sc['sc_event_type']	= "";
		if($sc['use_type'] == "all")			$sc['use_type']			= "";
		if($sc['display'] == "all")				$sc['display']			= "";
		if($sc['event_view'] == "all")			$sc['event_view']		= "";

		$where = [];
		$bindData = [];

		if($sc['keyword']) $sc['keyword'] = trim($sc['keyword']);

		// 검색어
		if( !empty($sc['keyword']) ){
			$where[] = "
				CONCAT(
					title
				) LIKE '%" . addslashes($sc['keyword']) . "%'
			";
		}

		// 일자검색
		if( !empty($sc['date']) ){
			$date_field = $sc['date'];
			if($sc['sdate'] && $sc['edate']) $where[] = "{$date_field} between '{$sc['sdate']} 00:00:00' and '{$sc['edate']} 23:59:59'";
			else if($sc['sdate']) $where[] = "'{$sc['sdate']} 00:00:00' < {$date_field},'%Y-%m-%d'";
			else if($sc['edate']) $where[] = "{$date_field},'%Y-%m-%d' < '{$sc['edate']} 23:59:59'";
		}

		// 이벤트진행상태
		if( !empty($sc['event_status']) ){
			switch($sc['event_status']){
				case "before":
					$where[] = "start_date > CURRENT_TIMESTAMP()";
				break;
				case "ing":
					$where[] = "CURRENT_TIMESTAMP() between start_date and end_date";
				break;
				case "end":
					$where[] = "end_date < CURRENT_TIMESTAMP()";
				break;
			}
		}

		// 단독이벤트 관련 검색
		if(!empty($sc['sc_event_type'])){
			$where[]	= " evt.event_type = ? ";
			$bindData[] 	= $sc['sc_event_type'];
			if($sc['sc_event_type'] == 'solo'){
				if		(!empty($sc['sc_start_st']) && !empty($sc['sc_end_st'])){
					$where[]	= " evt.st_num between ? and ? ";
					$bindData[] 	= $sc['sc_start_st'];
					$bindData[] 	= $sc['sc_end_st'];
				}elseif	(!empty($sc['sc_start_st']) && empty($sc['sc_end_st'])){
					$where[]	= " evt.st_num >= ? ";
					$bindData[] 	= $sc['sc_start_st'];
				}elseif	(empty($sc['sc_start_st']) && !empty($sc['sc_end_st'])){
					$where[]	= " evt.st_num <= ? ";
					$bindData[] 	= $sc['sc_end_st'];
				}
				if		(!empty($sc['sc_goods_type'])){
					$where[]	= " g.goods_kind = ? ";
					$bindData[] 	= $sc['sc_goods_type'];
				}
				if		(!empty($sc['sc_goods_name'])){
					$where[]	= " g.goods_name like ? ";
					$bindData[] 	= "%".$sc['sc_goods_name']."%";
				}
				if		(!empty($sc['sc_goods_info'])){
					$where[]	= " (g.goods_name like ? OR g.goods_seq = ?) ";
					$bindData[] 	= "%".$sc['sc_goods_info']."%";
					$bindData[] 	= $sc['sc_goods_info'];
				}
			}
		}

		//이벤트 > 사용제한
		if(!empty($sc['use_type'])){
			$where[] = "( ".$sc['use_type']." = 'n' )";
		}

		//사용자 접속 설정
		if(!empty($sc['display'])){
			$where[]	= "evt.display = ? ";
			$bindData[] = $sc['display'];
		}

		//전체이벤트페이지 설정
		if(!empty($sc['event_view'])){
			$where[]	= "evt.event_view = ? ";
			$bindData[] = $sc['event_view'];
		}

		$sqlWhereClause = $where ? implode(' AND ',$where) : "";		

		$sql = array();
		$sql['field'] = "
					evt.*,
					g.".implode(',g.',get_all_field('fm_goods', array('goods_seq', 'regist_date', 'update_date'))).",
					if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status,
					bft.target_sale as target_sale,
					bft.event_sale as event_sale,
					bft.saller_rate_type,
					bft.saller_rate,
					IFNULL(event_order_cnt,0)		as order_cnt,
					IFNULL(event_order_ea,0)		as order_ea,
					IFNULL(event_order_price,0)		as order_price";

		$sql['table'] = "
					fm_event as evt
					left join fm_event_benefits as bft on ( evt.event_seq = bft.event_seq and bft.event_benefits_seq  = concat(bft.event_seq,'_1') )
					left join fm_goods as g on evt.goods_seq = g.goods_seq
					";
		$sql['wheres']	= $sqlWhereClause;
		$sql['orderby'] = " ORDER BY {$sc['orderby']} {$sc['sort']}";
		$sql['limit']	= " LIMIT {$sc['page']}, {$sc['perpage']}";

		$result = pagingNumbering($sql, $sc, true, $bindData);

		$ingeventsql = 'select count(event_seq) as cnt from fm_event where CURRENT_TIMESTAMP() between start_date and end_date';
		$ingeventquery = $this->db->query($ingeventsql);
		$tmp = $ingeventquery->row_array();
		$count['ing'] = $tmp['cnt'];

		$endeventsql = 'select count(event_seq) as cnt from fm_event where end_date < CURRENT_TIMESTAMP()';
		$endeventquery = $this->db->query($endeventsql);
		$tmp = $endeventquery->row_array();
		$count['end'] = $tmp['cnt'];

		return array('result' => $result, 'count' => $count, 'sc' => $sc);
	}

	//단독이벤트 판매건/주문건/주문금액 실시간업데이트 @2015-09-04 pjm
	public function event_order_stat($event_stats){
		array_unique($event_stats);
		foreach($event_stats as $event_seq){
			$this->event_order($event_seq);
			$this->event_order_batch($event_seq);
		}
	}

	//이벤트 주문통계
	public function event_order($event_seq) {

		if(!$event_seq) return;

		/*
		$query = "select  mon,
					sum(settleprice55) step55_price, step55_count,sum(opt55_ea) opt55_ea,sum(sub55_ea) sub55_ea,
					sum(settleprice) step75_price, step75_count,sum(opt_ea) opt_ea,sum(sub_ea) sub_ea,
					sum(refund_price) refund_price,sum(refund_count) refund_count,sum(refund_ea) refund_ea
				from (
					select
					sum(case when opt.step55>0 or opt.step75>0 then 1 else 0 end) step55_count,
					sum(ifnull((opt.price*(opt.step55+opt.step75)),0)+ifnull((sub.price*(sub.step55+sub.step75)),0))	as settleprice55,
					ifnull(sum(opt.step55+opt.step75),0) opt55_ea,
					ifnull(sum(sub.step55+sub.step75),0) sub55_ea,
					sum(case when opt.step75>0 then 1 else 0 end) step75_count,
					sum(ifnull((opt.price*opt.step75),0)+ifnull((sub.price*sub.step75),0))	as settleprice,
					ifnull(sum(opt.step75),0) opt_ea,
					ifnull(sum(sub.step75),0) sub_ea,
					ifnull((substring(ord.regist_date,1,7)),'') as mon,
					ifnull((select sum(refund_price+refund_emoney) from fm_order_refund_item a,fm_order_refund b where a.refund_code=b.refund_code and a.item_seq = item.item_seq),0) refund_price,
					ifnull((select count(*) from fm_order_refund as  b inner join fm_order_refund_item as a on a.refund_code=b.refund_code  where a.item_seq = item.item_seq),0) refund_count,
					ifnull((select sum(ea) from fm_order_refund_item a,fm_order_refund b where a.refund_code=b.refund_code and a.item_seq = item.item_seq),0) refund_ea
				from
					fm_order_item										as item
					inner join fm_order								as ord on item.order_seq = ord.order_seq
					inner join fm_order_item_option			as opt on item.item_seq = opt.item_seq
						left join fm_order_item_suboption	as sub on opt.item_option_seq = sub.item_option_seq
					where item.event_seq=? and opt.step in('55','75') and ord.regist_date>=?  and ord.regist_date<=?
				) t
				group by t.mon";
		*/

		/* @2015-05-20 pjm 출고이상 통계 추가 step55+step75 */
		/* @2015-09-04 pjm 통계기준 : 판매수량(해당 단독이벤트로 결제된 상품의 수) */
		$query = "select
						mon
						,count(*) step25_count
						,sum( step25_opt_ea + step25_sub_ea) as step25_ea
						,sum( step25_opt_price + step25_sub_price) as step25_price
						,sum(refund_price) refund_price
						,sum(refund_count) refund_count
						,sum(refund_ea) refund_ea
					from (
						select
							ord.order_seq
							,ifnull((substring(ord.deposit_date,1 ,7)), '') as mon
							,sum(ifnull(opt.ea, 0)) as step25_opt_ea
							,sum(ifnull(opt.price ,0)) * ifnull(opt.ea, 0) as step25_opt_price
							,ifnull((select sum(ifnull(ea,0)) from fm_order_item_suboption
									where item_option_seq = opt.item_option_seq),0) as step25_sub_ea
							,ifnull((select sum( ifnull(price ,0)* ifnull(ea ,0)) from fm_order_item_suboption
									where item_option_seq = opt.item_option_seq),0) as step25_sub_price
							,ifnull((select max(refund_price+refund_emoney) from fm_order_refund_item a,fm_order_refund b
									where a.refund_code=b.refund_code and a.item_seq=item.item_seq and b.order_seq=ord.order_seq and b.status='complete'),0) refund_price
							,ifnull((select max('1') from fm_order_item_suboption os
								where os.item_option_seq=opt.item_option_seq and os.order_seq=ord.order_seq and os.step >= 25 and os.step <85),0) refund_count
							,ifnull((select sum(ea) from fm_order_refund_item a,fm_order_refund b
									where a.refund_code=b.refund_code and b.order_seq=ord.order_seq and b.status='complete'),0) refund_ea
						from
							fm_order_item as item
							inner join fm_order  as ord on item.order_seq = ord.order_seq
							inner join fm_order_item_option  as opt on item. item_seq = opt.item_seq
						where
							item.event_seq =?
							and ( ord.step >= 25 and ord.step < 85 )
							and ord.deposit_yn= 'y'
							and ord.deposit_date>= ?
							and ord.deposit_date<= ?
						group by ord.order_seq
					) as t group by t.mon;
					";
		$start	= date('Y-').str_pad((date('m')-1),2 ,"0", STR_PAD_LEFT)."-01 00:00:00";
		$end	= date('Y-m')."-".date("t")." 23:59:59";
		$query = $this->db->query($query,array($event_seq,$start,$end));
		if( $query ) {

			//lek 18.06.20 단독할인이벤트 주문건(0건)시 리셋
			if(!$query->result_array())
			{
				$query = "update fm_event_order set	step25_count='',step25_price='',step25_ea='', refund_count='',refund_price='',refund_ea='',month=''";
				$this->db->query($query);
				return;
			}
			//--------------------------------------------------------------

			foreach($query->result_array() as $row){
				if(!$row['mon']) continue;
				$row['mon'] = str_replace("-","",$row['mon']);
				$param = array();
				$query = "delete from fm_event_order where event_seq=? and month=?";
				$this->db->query($query,array($event_seq,$row['mon']));
				$query = "insert into fm_event_order set
							step25_count=?,step25_price=?,step25_ea=?,
							refund_count=?,refund_price=?,refund_ea=?,
							event_seq=?,month=?";

				$param[] = $row['step25_count'];
				$param[] = $row['step25_price'];
				$param[] = $row['step25_ea'];

				$param[] = $row['refund_count'];
				$param[] = $row['refund_price'];
				$param[] = $row['refund_ea'];
				$param[] = $event_seq;
				$param[] = $row['mon'];
				$this->db->query($query,$param);
			}
		}
	}

	/* 단독이벤트의 판매수량/주문건수/주문금액 일괄 업데이트 업데이트 @2013-11-15 */
	public function event_order_batch($event_seq) {
		if(!$event_seq) return;

		/*
		$eventupquery = "select
			( select sum( CONVERT(step75_ea * 1, SIGNED) - CONVERT(refund_ea * 1, SIGNED)  ) from fm_event_order where event_seq=A.event_seq ) event_order_ea,
			( select sum( step75_count ) from fm_event_order where event_seq=A.event_seq ) event_order_cnt,
			( select sum( CONVERT(step75_price * 1, SIGNED) - CONVERT(refund_price * 1, SIGNED) ) from fm_event_order where event_seq=A.event_seq ) event_order_price
		from fm_event A
		where A.event_seq =?";
		*/
		/* @2015-05-20 pjm 단독이벤트 현재 구매갯수 노출용 필드 업데이트 : 배송완료->출고완료로 변경 */
		/* @2015-09-04 pjm 통계기준 : 판매수량(해당 단독이벤트로 결제된 상품의 수) */
		$eventupquery = "select
							( select sum(CONVERT(step25_ea*1,SIGNED)-CONVERT(refund_ea*1, SIGNED))
								from fm_event_order where event_seq=A.event_seq) event_order_ea,
							( select sum(CONVERT(step25_count*1,SIGNED)-CONVERT(refund_count*1, SIGNED))
								from fm_event_order where event_seq=A.event_seq) event_order_cnt,
							( select sum( CONVERT(step25_price * 1, SIGNED) - CONVERT(refund_price * 1, SIGNED))
								from fm_event_order where event_seq=A.event_seq ) event_order_price
						from fm_event as A
						where A.event_seq =?";
		$eventup = $this->db->query($eventupquery,array($event_seq));
		$member_cnt = $eventup->row_array();
		$member_cnt['event_order_cnt']	= ($member_cnt['event_order_cnt']>0)?$member_cnt['event_order_cnt']:0;		//주문건수
		$member_cnt['event_order_ea']	= ($member_cnt['event_order_ea']>0)?$member_cnt['event_order_ea']:0;		//판매수량
		$member_cnt['event_order_price']= ($member_cnt['event_order_price']>0)?$member_cnt['event_order_price']:0;	//주문금액

		$this->db->where('event_seq', $event_seq);
		$result = $this->db->update('fm_event', array('event_order_ea'=>$member_cnt['event_order_ea'],'event_order_cnt'=>$member_cnt['event_order_cnt'],'event_order_price'=>$member_cnt['event_order_price']));
		return $result;
	}

	// fm_event 정보 추출
	public function get_today_event(){
		$cache_item_id = 'event_today';
		$result = cache_load($cache_item_id);
		if ($result === false) {
			$ndate			= date('Ymd');
			$nweek			= date('w');
			if(  $nweek == 0 ) $nweek = 7;

			$sql			= "select * from fm_event
								where DATE_FORMAT(start_date, '%Y%m%d') <= '".$ndate."'
								and DATE_FORMAT(end_date, '%Y%m%d') >= '".$ndate."'
								and (app_week is null or app_week = '' or app_week like '%".$nweek."%') order by event_type='solo' desc";
			$query			= $this->db->query($sql);
			$result			= $query->result_array();

			//
			if (! is_cli()) {
				cache_save($cache_item_id, $result);
			}
		}
		return $result;
	}

	// fm_event_benefit 정보 추출
	public function get_event_benefit($event_seq,$orderby=null){
		$this->db->from('fm_event_benefits')->where('event_seq',$event_seq);
		if($orderby) $this->db->order_by($orderby);
		$query		= $this->db->get();
		$result		= $query->result_array();
		return $result;
	}

	// 반응형 이벤트 리스트 검색 쿼리 :: 2018-12-12 lwh
	public function get_eventpage_list($sc = array()){

		$today = date('Y-m-d');
		$time = date('H:i:s');

		$where_arr = array("1");
		$start = ($sc['page'] - 1) * $sc['limit'];
		$limit = $sc['limit'];

		// 검색필터 검색
		if($sc['sc_filter']){
			switch($sc['sc_filter']){
				case 'sales' : // 할인/기획전
					$tableName = 'fm_event';
					$seq_colName = 'event_seq';
				break;
				case 'gift' : // 사은품
					$tableName = 'fm_gift';
					$seq_colName = 'gift_seq';
					$time = '';
				break;
				case 'attendance' : // 출석체크
					$tableName = 'fm_joincheck';
					$seq_colName = 'joincheck_seq';
					$time = '';
				break;
			}
		}

		// 이벤트 상태 검색
		if ($sc['sc_status']) {
			switch($sc['sc_status']) {
				case 'expired' : // 종료
					$where_arr[] = "end_date < '{$today} {$time}'";
					break;
				case 'ing' : // 진행중
					if ($tableName != 'fm_event') {
						$where_arr[] = ($this->managerInfo) ? "end_date >= '{$today}'" : "CURRENT_DATE() between start_date and end_date";
					} else {
						$where_arr[] = ($this->managerInfo) ? "end_date >= '{$today} {$time}'" : "CURRENT_TIMESTAMP() between start_date and end_date";
					}
					break;
				default : // 전체
					if(!$this->managerInfo) {
						$where_arr[] = "start_date <= '{$today} {$time}'";
					}
					break;
			}
		}

		$bindData = [];
		// 이벤트명 검색
		if ($sc['event_name']) {
			$where_arr[] = " title like ? ";
			$bindData[] = "%".$sc['event_name']."%";
		}
		if ($sc['sc_filter'] == 'attendance') {
			$where_arr[] = " joincheck_view = 'Y' ";
		}
		if ($sc['sc_filter'] != 'attendance') {
			// 출석체크는 전체 이벤트 페이지 노출 여부설정이 없음.
			$where_arr[] = " event_view = 'y' ";

			// 출석체크 이벤트가 아니고, 관리자가 아니면 노출상태만 표출함.
			if(!$this->managerInfo)	{
				$where_arr[] = " display= 'y' ";
			}
		}

		$where	= implode(' AND ', $where_arr);
		$sql = "
			SELECT	SQL_CALC_FOUND_ROWS *
			FROM	{$tableName}
			WHERE	{$where}
			ORDER by start_date DESC";
		// sequence 변수가 선언되어 있을때만 ORDER 필드 추가
		if(isset($seq_colName)) {
		    $sql .= ", {$seq_colName} DESC";
		}
		$sql .= " LIMIT {$start}, {$limit}";

		$query = $this->db->query($sql, $bindData);
		$result = $query->result_array();

		//총건수
		$sql = "SELECT FOUND_ROWS() AS COUNT";
		$query_count = $this->db->query($sql);
		$res_count = $query_count->row_array();
		$return['count'] = $res_count['COUNT'];

		$nowtime = mktime();
		$set_close_day = 1; // 종료 1일전부터 마감임박을 띄운다..

		foreach((array)$result as $key => $val) {
			if($tableName != 'fm_event'){
				$val['start_date'] = date('Y-m-d 00:00:00',strtotime($val['start_date']));
				$val['end_date'] = date('Y-m-d 23:59:59',strtotime($val['end_date']));
			}
			preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/', $val['end_date'], $end_date_tmp);
			preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/', $val['start_date'], $start_date_tmp);

			$end_time_stamp	= mktime($end_date_tmp[4], $end_date_tmp[5], $end_date_tmp[6], $end_date_tmp[2], $end_date_tmp[3], $end_date_tmp[1]);
			$d_day_stamp = $end_time_stamp - $nowtime;

			$result[$key]['tpl_path'] = urlencode($val['tpl_path']);

			if ($start_date_tmp[1] == $end_date_tmp[1]) {
				$result[$key]['period'] = "{$start_date_tmp[1]}-{$start_date_tmp[2]}-{$start_date_tmp[3]} ~ {$end_date_tmp[2]}-{$end_date_tmp[3]}";
			} else {
				$result[$key]['period'] = "{$start_date_tmp[1]}-{$start_date_tmp[2]}-{$start_date_tmp[3]} ~ {$end_date_tmp[1]}-{$end_date_tmp[2]}-{$end_date_tmp[3]}";
			}

			if ($d_day_stamp < 0) {
				$result[$key]['timestamp'] = 0;
				$result[$key]['status'] = 'end';
				$result[$key]['d_day'] = '00일 00:00:00';
			} else {
				$day = ($d_day_stamp > 86400) ? floor($d_day_stamp / 86400) : 0;
				$day_c = ($day > 0) ? $d_day_stamp % 86400 : $d_day_stamp;
				$day_t = str_pad($day, 2, '0', STR_PAD_LEFT);

				$hour = ($day_c > 3600) ? floor($day_c / 3600) : 0;
				$hour_c = ($hour > 0) ? $day_ch % 3600 : $day_c;
				$hour_t = str_pad($hour, 2, '0', STR_PAD_LEFT);

				$hour = floor($day_c / 3600);
				$hour_c = $day_c % 3600;
				$hour_t = str_pad($hour, 2, '0', STR_PAD_LEFT);

				$minit = floor($hour_c / 60);
				$minit_t = str_pad($minit, 2, '0', STR_PAD_LEFT);

				$sec = $hour_c % 60;
				$sec_t = str_pad($sec, 2, '0', STR_PAD_LEFT);

				$result[$key]['timestamp'] = $d_day_stamp;
				$result[$key]['d_day'] = "{$day_t}일 {$hour_t}:{$minit_t}:{$sec_t}";
				$result[$key]['status'] = ($set_close_day >= $day) ? 'close' : 'ing';
			}

			if ($val['event_banner']) {
				$banner_img = getimagesize(ROOTPATH."/data/event/{$val['event_banner']}");
				$result[$key]['banner_width'] = $banner_img[0];
				$result[$key]['banner_height'] = $banner_img[0];
			}

			if ($val['m_event_banner']) {
				$banner_img = getimagesize(ROOTPATH."/data/event/{$val['m_event_banner']}");
				$result[$key]['m_banner_width'] = $banner_img[0];
				$result[$key]['m_banner_height'] = $banner_img[0];
			}

			if	( $val['event_type'] == 'solo' ) {
				$solo_event = $this->get(array('event_seq'=>$val['event_seq']));
				$solo_event = $solo_event->row_array();
				$result[$key]['goods_seq'] = $solo_event['goods_seq'];
			}
		}

		$return['list']	= $result;
		return $return;
	}

	public function get_all_type_event($sc = array()){

		$today		= date('Y-m-d');
		$time		= date('H:i:s');

		$where_arr	= array("1");
		$start		= ($sc['page'] - 1) * $sc['limit'];
		$limit		= $sc['limit'];
		//관리자는 전체/진행중 이벤트 모두 볼수 있도록 개선 @2016-11-18
		switch($sc['target']){
			case	'end' ://종료
				$where_arr[]	= "end_date < '{$today} {$time}'";
				break;
			case	'all' ://전체
				if(!$this->managerInfo) $where_arr[]	= "start_date <= '{$today} {$time}'";
				break;
			default ://진행중
				$where_arr[]	= ($this->managerInfo)?"end_date > '{$today} {$time}'":"CURRENT_TIMESTAMP() between start_date and end_date";
				break;
		}

		if(!$this->managerInfo) $where_arr[]	= " display= 'y' ";

		$where	= implode(' AND ', $where_arr);

		$sql	= "
			SELECT	SQL_CALC_FOUND_ROWS * FROM(
				SELECT	 gift_seq						AS event_seq
						,title							AS event_title
						,event_introduce				AS event_introduce
						,event_introduce_color			AS event_introduce_color
						,m_event_introduce				AS m_event_introduce
						,m_event_introduce_color		AS m_event_introduce_color
						,event_banner					AS event_banner
						,m_event_banner					AS m_event_banner
						,banner_filename				AS banner_file
						,start_date						AS start_date
						,concat(end_date,' 23:59:59')	AS end_date
						,'gift'							AS event_type
						,tpl_path						AS tpl_path
						,event_move						AS event_move
				FROM	fm_gift
				WHERE	{$where} AND event_view = 'y'

				UNION

				SELECT	 event_seq						AS event_seq
						,title							AS title
						,event_introduce				AS event_introduce
						,event_introduce_color			AS event_introduce_color
						,m_event_introduce				AS m_event_introduce
						,m_event_introduce_color		AS m_event_introduce_color
						,event_banner					AS event_banner
						,m_event_banner					AS m_event_banner
						,banner_filename				AS banner_file
						,start_date						AS start_date
						,end_date						AS end_date
						,event_type						AS event_type
						,tpl_path						AS tpl_path
						,event_move						AS event_move
				FROM	fm_event
				WHERE	{$where} AND event_view = 'y'
			)event
			ORDER by event.start_date DESC
			LIMIT {$start}, {$limit}";

		$query				= $this->db->query($sql);
		$result				= $query->result_array();

		//총건수
		$sql				= "SELECT FOUND_ROWS() AS COUNT";
		$query_count		= $this->db->query($sql);
		$res_count			= $query_count->row_array();
		$return['count']	= $res_count['COUNT'];

		$nowtime		= mktime();
		$view_info		= config_load('event');
		$set_close_day	= $view_info['display']['close_icon_day'];

		foreach((array)$result as $key => $val){
			preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/', $val['end_date'], $end_date_tmp);
			preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/', $val['start_date'], $start_date_tmp);


			$end_time_stamp	= mktime($end_date_tmp[4], $end_date_tmp[5], $end_date_tmp[6], $end_date_tmp[2], $end_date_tmp[3], $end_date_tmp[1]);
			$d_day_stamp	= $end_time_stamp - $nowtime;

			$result[$key]['tpl_path']	= urlencode($val['tpl_path']);


			if($start_date_tmp[1] == $end_date_tmp[1])	$result[$key]['period']		= "{$start_date_tmp[1]}-{$start_date_tmp[2]}-{$start_date_tmp[3]} ~ {$end_date_tmp[2]}-{$end_date_tmp[3]}";
			else										$result[$key]['period']		= "{$start_date_tmp[1]}-{$start_date_tmp[2]}-{$start_date_tmp[3]} ~ {$end_date_tmp[1]}-{$end_date_tmp[2]}-{$end_date_tmp[3]}";


			if($d_day_stamp < 0){
				$result[$key]['timestamp']	= 0;
				$result[$key]['status']		= 'end';
				$result[$key]['d_day']		= '00일 00:00:00';
			}else{
				$day		= ($d_day_stamp > 86400) ? floor($d_day_stamp / 86400) : 0;
				$day_c		= ($day > 0) ? $d_day_stamp % 86400 : $d_day_stamp;
				$day_t		= str_pad($day, 2, '0', STR_PAD_LEFT);

				$hour		= ($day_c > 3600) ? floor($day_c / 3600) : 0;
				$hour_c		= ($hour > 0) ? $day_ch % 3600 : $day_c;
				$hour_t		= str_pad($hour, 2, '0', STR_PAD_LEFT);

				$hour		= floor($day_c / 3600);
				$hour_c		= $day_c % 3600;
				$hour_t		= str_pad($hour, 2, '0', STR_PAD_LEFT);

				$minit		= floor($hour_c / 60);
				$minit_t	= str_pad($minit, 2, '0', STR_PAD_LEFT);

				$sec		= $hour_c % 60;
				$sec_t		= str_pad($sec, 2, '0', STR_PAD_LEFT);

				$result[$key]['timestamp']	= $d_day_stamp;
				$result[$key]['d_day']		= "{$day_t}일 {$hour_t}:{$minit_t}:{$sec_t}";
				$result[$key]['status']		= ($set_close_day >= $day) ? 'close' : 'ing';
			}

			if($val['event_banner']){
				$banner_img		= getimagesize(ROOTPATH."/data/event/{$val['event_banner']}");
				$result[$key]['banner_width']		= $banner_img[0];
				$result[$key]['banner_height']		= $banner_img[0];
			}

			if($val['m_event_banner']){
				$banner_img		= getimagesize(ROOTPATH."/data/event/{$val['m_event_banner']}");
				$result[$key]['m_banner_width']		= $banner_img[0];
				$result[$key]['m_banner_height']	= $banner_img[0];
			}

			if	( $val['event_type'] == 'solo' ) {
				$solo_event = $this->get(array('event_seq'=>$val['event_seq']));
				$solo_event = $solo_event->row_array();
				$result[$key]['goods_seq'] = $solo_event['goods_seq'];
			}
		}

		$return['list']	= $result;
		return $return;
	}


	public function get_event_set(){
		$view_info	= config_load('event');
		$disp		= $view_info['display'];

		$event_view_set['disp_target']	= ($disp['disp_target']) ? $disp['disp_target'] : 'ing';

		if($this->_is_mobile_agent == 'mobile' || $_GET['setMode'] == 'mobile'){
			$event_view_set['count_w']				= ($disp['m_count_w']) ? $disp['m_count_w'] : 1;
			$event_view_set['count_h']				= ($disp['m_count_h']) ? $disp['m_count_h'] : 9;
			$event_view_set['size_w']				= ($disp['m_size_w']) ? $disp['m_size_w'] : 220;
			$event_view_set['size_h']				= ($disp['m_size_h']) ? $disp['m_size_h'] : 100;
			$event_view_set['over_line_use']		= ($disp['m_over_line_use']) ? $disp['m_over_line_use'] : 'n';
			$event_view_set['non_line_color']		= ($disp['m_non_line_color']) ? $disp['m_non_line_color'] : '#000000';
			$event_view_set['non_line_px']			= ($disp['m_non_line_px']) ? $disp['m_non_line_px'] : 0;
			$event_view_set['over_line_color']		= ($disp['m_over_line_color']) ? $disp['m_over_line_color'] : '#000000';
			$event_view_set['over_line_px']			= ($disp['m_over_line_px']) ? $disp['m_over_line_px'] : 0;
			$event_view_set['over_opacity_use']		= ($disp['m_over_opacity_use']) ? $disp['m_over_opacity_use'] : 'n';
			$event_view_set['over_opacity_per']		= ($disp['m_over_opacity_per']) ? 100 - $disp['m_over_opacity_per'] : 100;
			$event_view_set['end_lay_use']			= ($disp['m_end_lay_use']) ? $disp['m_end_lay_use'] : 0;
			$event_view_set['close_lay_use']		= ($disp['m_close_lay_use']) ? $disp['m_close_lay_use'] : 'n';
			$event_view_set['close_icon_day']		= ($disp['m_close_icon_day']) ? $disp['m_close_icon_day'] : 'n';
			$event_view_set['event_intorduce_use']	= ($disp['m_event_intorduce_use']) ? $disp['m_event_intorduce_use'] : 'n';
			$event_view_set['event_period_use']		= ($disp['m_event_period_use']) ? $disp['m_event_period_use'] : 'n';
			$event_view_set['event_end_icon_use']	= ($disp['m_event_end_icon_use']) ? $disp['m_event_end_icon_use'] : 'n';
			$event_view_set['event_until_use']		= ($disp['m_event_until_use']) ? $disp['m_event_until_use'] : 'n';
			$event_view_set['end_icon']				= (is_file(ROOTPATH.$disp['m_end_icon']) === true) ? $disp['m_end_icon'] : '/data/icon/event/evnet_icon02.png';
			$event_view_set['close_icon']			= (is_file(ROOTPATH.$disp['m_close_icon']) === true) ? $disp['m_close_icon'] : '/data/icon/event/evnet_icon01.png';
		}else{
			$event_view_set['count_w']				= ($disp['count_w']) ? $disp['count_w'] : 4;
			$event_view_set['count_h']				= ($disp['count_h']) ? $disp['count_h'] : 4;
			$event_view_set['size_w']				= ($disp['size_w']) ? $disp['size_w'] : 222;
			$event_view_set['size_h']				= ($disp['size_h']) ? $disp['size_h'] : 302;
			$event_view_set['over_line_use']		= ($disp['over_line_use']) ? $disp['over_line_use'] : 'n';
			$event_view_set['non_line_color']		= ($disp['non_line_color']) ? $disp['non_line_color'] : '#000000';
			$event_view_set['non_line_px']			= ($disp['non_line_px']) ? $disp['non_line_px'] : 0;
			$event_view_set['over_line_color']		= ($disp['over_line_color']) ? $disp['over_line_color'] : '#000000';
			$event_view_set['over_line_px']			= ($disp['over_line_px']) ? $disp['over_line_px'] : 0;
			$event_view_set['over_opacity_use']		= ($disp['over_opacity_use']) ? $disp['over_opacity_use'] : 'n';
			$event_view_set['over_opacity_per']		= ($disp['over_opacity_per']) ? 100 - $disp['over_opacity_per'] : 100;
			$event_view_set['end_lay_use']			= ($disp['end_lay_use']) ? $disp['end_lay_use'] : 0;
			$event_view_set['close_lay_use']		= ($disp['close_lay_use']) ? $disp['close_lay_use'] : 'n';
			$event_view_set['close_icon_day']		= ($disp['close_icon_day']) ? $disp['close_icon_day'] : 'n';
			$event_view_set['event_intorduce_use']	= ($disp['event_intorduce_use']) ? $disp['event_intorduce_use'] : 'n';
			$event_view_set['event_period_use']		= ($disp['event_period_use']) ? $disp['event_period_use'] : 'n';
			$event_view_set['event_end_icon_use']	= ($disp['event_end_icon_use']) ? $disp['event_end_icon_use'] : 'n';
			$event_view_set['event_until_use']		= ($disp['event_until_use']) ? $disp['event_until_use'] : 'n';
			$event_view_set['end_icon']				= (is_file(ROOTPATH.$disp['end_icon']) === true) ? $disp['end_icon'] : '/data/icon/event/evnet_icon02.png';
			$event_view_set['close_icon']			= (is_file(ROOTPATH.$disp['close_icon']) === true) ? $disp['close_icon'] : '/data/icon/event/evnet_icon01.png';
		}

		//$this->skin->file : body_width 체크 @2016-05-30
		$body_width = ($this->layoutconfevent['body_width'])?$this->layoutconfevent['body_width']:920;
		$event_view_set['event_li_width']		= $body_width / $event_view_set['count_w'];
		$event_view_set['event_li_width_per']	= 100 / $event_view_set['count_w'];

		$end_img	= getimagesize(ROOTPATH.$event_view_set['end_icon']);
		$event_view_set['end_icon_width']		= $end_img[0];
		$event_view_set['end_icon_height']		= $end_img[1];

		$close_img	= getimagesize(ROOTPATH.$event_view_set['close_icon']);
		$event_view_set['close_icon_width']		= $close_img[0];
		$event_view_set['close_icon_height']	= $close_img[1];

		if($event_view_set['over_line_use'] == 'y'){
			$event_view_set['size_w_non']	= $event_view_set['size_w'] - $event_view_set['non_line_px'] * 2;
			$event_view_set['size_h_non']	= $event_view_set['size_h'] - $event_view_set['non_line_px'] * 2;
			$event_view_set['size_w_over']	= $event_view_set['size_w'] - $event_view_set['over_line_px'] * 2;
			$event_view_set['size_h_over']	= $event_view_set['size_h'] - $event_view_set['over_line_px'] * 2;
		}else{
			$event_view_set['size_w_non']	= $event_view_set['size_w'];
			$event_view_set['size_h_non']	= $event_view_set['size_h'];
			$event_view_set['size_w_over']	= $event_view_set['size_w'];
			$event_view_set['size_h_over']	= $event_view_set['size_h'];
		}

		return $event_view_set;
	}

	// fm_event_choice 정보 추출
	public function get_event_choice($event_seq, $event_benefits_seq = ''){
		$cache_item_id = sprintf('event_choice_%s%s', $event_seq, $event_benefits_seq ? '_' . $event_benefits_seq : '');
		$result = cache_load($cache_item_id);
		if ($result === false) {
			if	($event_benefits_seq)
				$addWhere	= " and event_benefits_seq = '".$event_benefits_seq."'";

			$sql	= "select * from fm_event_choice
						where event_seq = '".$event_seq."' ".$addWhere;
			$query	= $this->db->query($sql);
			$result	= $query->result_array();

			//
			if (! is_cli()) {
				cache_save($cache_item_id, $result);
			}
		}

		return $result;
	}

	public function get_event_choice_goods($benefit_seq){

		$this->db->from("fm_event_choice");
		$this->db->where("event_benefits_seq",$benefit_seq)->order_by("event_choice_seq ASC");
		$query 		= $this->db->get();
		$issuegoods = $query->result_array();
		$issuegoods = $this->goodsmodel->get_select_goods_list($issuegoods);
		return $issuegoods;

	}



	# 진행중인 이벤트
	public function get_event_ing_list($event_status='ing'){

		// 이벤트진행상태
		$where = array();
		switch($event_status){
			case "before":
				$where[] = "start_date > CURRENT_TIMESTAMP()";
			break;
			case "ing":
				$where[] = "CURRENT_TIMESTAMP() BETWEEN start_date AND end_date";
			break;
			case "end":
				$where[] = "end_date < CURRENT_TIMESTAMP()";
			break;
			case "beforetheend":
				$where[] = "end_date > CURRENT_TIMESTAMP()";
			break;
		}
		if($where) $wheres = "(".implode(' OR ',$where).")";

		$ingeventsql	= "SELECT
								event_seq, title, start_date, end_date
								,if(CURRENT_TIMESTAMP() between start_date and end_date,'진행 중',if(end_date < CURRENT_TIMESTAMP(),'종료','시작 전')) as status
							FROM fm_event WHERE ".$wheres;
		$ingeventquery	= $this->db->query($ingeventsql);
		$event_list		= array();
		foreach($ingeventquery->result_array() as $event_row){
			$event_row['event_title']	= sprintf("%s (%s ~ %s)", $event_row['title'], substr($event_row['start_date'],0,10), substr($event_row['end_date'],0,10));
			$event_list[]				= $event_row;
		}
		return $event_list;
	}

	# 진행중인 사은품 이벤트
	public function get_giftevent_status_list($event_status='ing'){

		// 이벤트진행상태
		$where = array();
		switch($event_status){
			case "before":
				$where[] = "start_date > CURRENT_DATE()";
			break;
			case "ing":
				$where[] = "CURRENT_DATE() BETWEEN start_date AND end_date";
			break;
			case "end":
				$where[] = "end_date < CURRENT_DATE()";
			break;
			case "beforetheend":
				$where[] = "end_date > CURRENT_DATE()";
			break;
			case "noship":
				$where[] = "shipping_group_seq IS NULL";
			break;
		}
		if($where) $wheres = "(".implode(' OR ',$where).")";


		$ingeventsql	= "SELECT
								gift_seq,gift_gb, title, start_date, end_date,
								if(current_date() between start_date and end_date,'진행 중',if(end_date < current_date(),'종료','시작 전')) as status
							FROM fm_gift WHERE ".$wheres;
		$ingeventquery	= $this->db->query($ingeventsql);
		$event_list		= array();
		foreach($ingeventquery->result_array() as $event_row){
			$event_row['event_title']	= sprintf("%s (%s ~ %s)", $event_row['title'], substr($event_row['start_date'],0,10), substr($event_row['end_date'],0,10));
			$event_list[]				= $event_row;
		}
		return $event_list;
	}

	public function get_issue_count(){
		$union_query = array();

		// 할인이벤트
		$union_query[] = "
			SELECT count(*) AS cnt, 'event' as 'type'
			FROM fm_event
			WHERE CURRENT_TIMESTAMP( )
			BETWEEN start_date
			AND end_date
		";

		// 사은품 이벤트
		$union_query[] = "
			SELECT count( * ) AS cnt, 'gift' AS 'type'
			FROM fm_gift
			WHERE CURRENT_DATE( )
			BETWEEN start_date
			AND end_date
		";

		// 출석체크
		$union_query[] = "
			SELECT count( * ) AS cnt, 'joincheck' AS 'type'
			FROM fm_joincheck
			WHERE CURRENT_TIMESTAMP( )
			BETWEEN start_date
			AND end_date
		";

		$sql = "
		SELECT *
		FROM (
			".implode(' union ',$union_query)."
		) as a
		";

		return $this->db->query($sql);
	}

	public function get_discount_event_list($goods_seq='', $category_codes=array()) {
		# reset
		$buff = array();

		#
		$sql	= ""
		."SELECT "
		."A.event_benefits_seq, "
		."A.target_sale, "
		."A.event_sale, "
		."A.event_reserve, "
		."A.event_point, "
		."A.provider_list, "
		."B.event_choice_seq, "
		."B.choice_type, "
		."B.goods_seq item_goods_seq, "
		."B.category_code item_category_code, "
		."C.* "
		."FROM fm_event_benefits A "
		."LEFT JOIN fm_event_choice B ON A.event_seq = B.event_seq "
		."LEFT JOIN fm_event C ON A.event_seq = C.event_seq "
		."WHERE CURRENT_TIMESTAMP() BETWEEN C.start_date AND C.end_date ";

		if($goods_seq) {
			$sql .= " AND (B.choice_type = 'goods' AND B.goods_seq = ?) ";
			$bind[] = $goods_seq;
		}

		if(count($category_codes)) {
			if($goods_seq) {
				$sql .= " OR (B.choice_type = 'category' AND B.category_code in ?)";
			} else {
				$sql .= " AND (B.choice_type = 'category' AND B.category_code in ?)";
			}
			$bind[] = $category_codes;
		}

		if($goods_seq || gettype($category_codes) == 'array') {
			$sql	.= ""
			."AND C.event_seq NOT IN(	"
			."	SELECT D.event_seq "
			."	FROM fm_event_choice D "
			."	WHERE 1";

			if($goods_seq) {
				$sql	.= " AND (D.choice_type='except_goods' AND D.goods_seq = ?) ";
				$bind[] = $goods_seq;
			}

			if(count($category_codes)) {
				$sql	.= " OR (D.choice_type='except_category' AND D.category_code in ?)";
				$bind[] = $category_codes;
			}

			$sql	.= ")";

			//lek 18-06-18 상품 상세 이벤트 노출-------------------------------------------------
			$sql	.= ""
			."AND C.event_seq IN("
			."	SELECT D.event_seq "
			."	FROM fm_event_choice D "
			."	WHERE 1";

			if($goods_seq) {
				$sql	.= " AND (D.choice_type='goods' AND D.goods_seq = ?) ";
				$bind[] = $goods_seq;
			}

			if(count($category_codes)) {
				$sql	.= " OR (D.choice_type='category' AND D.category_code in ?)";
				$bind[] = $category_codes;
			}

			$sql	.= ")";
			//------------------------------------------------------------------------------
		}

		$query	= $this->db->query($sql, $bind);
		$rows	= $query->result_array();

		if(is_array($rows)) {
			foreach($rows as $row) {
				$event_seq = $row['event_seq'];
				$event_benefits_seq = $row['event_benefits_seq'];


				if(($row['choice_type']=='except_goods' || $row['choice_type']=='except_category') # 제외
					||($row['item_goods_seq'] == $goods_seq) # 포함
					||($row['choice_type']=='category' && !$row['item_goods_seq']) # 포함
					||$row['goods_rule']=='all' # 전체
				) {
					if(!is_array($buff[$event_seq]['common'])) {
						$buff[$event_seq]['common'] = array(
							'event_seq' => $row['event_seq'],
							'event_type' => $row['event_type'],
							'title' => $row['title'],
							'start_date'			=> $row['start_date'],
							'end_date'			=> $row['end_date'],
							'weekday'			=> $row['app_week'],
							'app_start_time'	=> $row['app_start_time'],
							'app_end_time'	=> $row['app_end_time'],
						);
					}

					$buff[$event_seq][$event_benefits_seq] = array(
						'event_benefits_seq' => $event_benefits_seq,
						'target_sale' => $row['target_sale'],
						'event_sale' => $row['event_sale'],
						'event_reserve' => $row['event_reserve'],
						'event_point' => $row['event_point'],
						'provider_list' => $row['provider_list'],
					);
				}
			}
		}

		return array_values($buff);
	}

	public function get_event_choices($aEventSeq){
		$query	= "select * from fm_event_choice where event_seq in (".implode(',', $aEventSeq).")";
		$query	= $this->db->query($query);
		foreach($query->result_array() as $data){
			$result[$data['event_seq']][]	= $data;
		}
		return $result;
	}

	public function get_event_benefits($aEventSeq){
		$query		= "select * from fm_event_benefits where event_seq in (".implode(',', $aEventSeq).")";
		$query	= $this->db->query($query);
		foreach($query->result_array() as $data){
			$result[$data['event_seq']][]	= $data;
		}
		return $result;
	}

	public function getEventAuto($sKeyword){

		$query	= "SELECT event_seq, title, tpl_path  FROM `fm_event` WHERE CURRENT_TIMESTAMP() BETWEEN start_date AND end_date AND title like ? ORDER BY end_date ASC limit 2";
		$query	= $this->db->query($query,array("%".$sKeyword."%"));
		foreach ($query->result_array() as $row){
			$row['keyword'] = str_replace($sKeyword, "<span class=\"highlight\">".$sKeyword."</span>", htmlspecialchars($row["title"]));
			$aResult[] = $row;
		}
		return $aResult;
	}

	//할인이벤트-입점사부담금 계산
	public function get_salecost_provider($params=array()){

		$salescost_provider = 0;

		if($params['event']['salescost_provider'] > 0){
			$salescost_provider = floor(($params['event_sale'] * ($params['event']['salescost_provider'] / 100)) / $params['ea']);
		}

		return $salescost_provider;

	}

	public function get_gift($gift_seq){

		$this->db->select("* ,IF(CURRENT_DATE() BETWEEN start_date AND end_date,'진행 중',IF(end_date < CURRENT_DATE(),'종료','시작 전')) AS status");
		$query 				= $this->db->get_where("fm_gift",array("gift_seq"=>$gift_seq));
		$data 				= $query->row_array();

		return $data;
	}


	public function get_gift_choice($gift_seq){

		$query 		= $this->db->get_where("fm_gift_choice",array("gift_seq"=>$gift_seq));
		$data 		= $query->result_array();
		$issuegoods = $this->goodsmodel->get_select_goods_list($data);
		return $issuegoods;
	}

	public function get_gift_benefit($gift_seq){

		$query 				= $this->db->get_where("fm_gift_benefit",array("gift_seq"=>$gift_seq));
		$data 				= $query->result_array();

		return $data;
	}



	public function get_gift_goods($search){

		$lists = array();
		$sql = "SELECT
					g.goods_seq, g.goods_name, o.price,g.goods_code
					,(select image from fm_goods_image where goods_seq=g.goods_seq and cut_number=1 and image_type='thumbScroll' limit 1) as image
				FROM
					fm_goods g LEFT JOIN fm_goods_option o ON g.goods_seq = o.goods_seq AND o.default_option = 'y'
				WHERE
					g.goods_seq in ($search)";
		$query = $this->db->query($sql);
		foreach ($query->result_array() as $row){
			if(!$row['image'] || !file_exists(ROOTPATH.$row['image'])){
				$row['image'] = "/admin/skin/default/images/common/noimage_list.gif";
			}
			$lists[] = $row;
		}

		return $lists;
	}

	public function get_gift_event_list($sc){

		$where = array();

		// 검색어
		if( $sc['keyword'] ){
			$where[] = "
				CONCAT(
					title
				) LIKE '%" . addslashes($sc['keyword']) . "%'
			";
		}

		// 입점사 검색
		if( $sc['provider_seq'] ){
			$where[] = " provider_seq = '".$sc['provider_seq']."' ";
		}

		// 배송그룹 검색
		if( $sc['ship_grp'] ){
			$where[] = " shipping_group_seq = '".$sc['ship_grp']."' ";
		}

		// 일자검색
		if( $sc['date'] ){
			$date_field = $sc['date'];
			if($sc['sdate'] && $sc['edate']) $where[] = "date_format({$date_field},'%Y-%m-%d') between '{$sc['sdate']}' and '{$sc['edate']}'";
			else if($sc['sdate']) $where[] = "'{$sc['sdate']}' < date_format({$date_field},'%Y-%m-%d')";
			else if($sc['edate']) $where[] = "date_format({$date_field},'%Y-%m-%d') < '{$sc['edate']}'";
		}

		// 종류 검색
		if( $sc['gift_gb'] ){
			if(is_array($sc['gift_gb'])){
				$arr2 = array();
				foreach($sc['gift_gb'] as $key => $data){
					switch($data){
						case "order":
							$arr2[] = "gift_gb = 'order'";
						break;
						case "buy":
							$arr2[] = "gift_gb = 'buy'";
						break;
					}
				}
				if($arr2) $where[] = "(".implode(' OR ',$arr2).")";
			}else{
				switch($sc['gift_gb']){
					case "order":
						$where[] = "gift_gb = 'order'";
					break;
					case "buy":
						$where[] = "gift_gb = 'buy'";
					break;
				}
			}
		}

		// 이벤트진행상태
		if( $sc['event_status'] ){
			if(is_array($sc['event_status'])){
				$arr = array();
				foreach($sc['event_status'] as $key => $data){
					switch($data){
						case "before":
							$arr[] = "start_date > current_date()";
						break;
						case "ing":
							$arr[] = "current_date() between start_date and end_date";
						break;
						case "end":
							$arr[] = "end_date < current_date()";
						break;
						case "noship":
							$arr[] = "shipping_group_seq is Null";
						break;
					}
				}
				if($arr) $where[] = "(".implode(' OR ',$arr).")";
			}else{
				switch($sc['event_status']){
					case "before":
						$where[] = "start_date > current_date()";
					break;
					case "ing":
						$where[] = "current_date() between start_date and end_date";
					break;
					case "end":
						$where[] = "end_date < current_date()";
					break;
					case "noship":
						$where[] = "shipping_group_seq is Null";
					break;
				}
			}
		}

		// 이벤트 페이지 검색
		if(is_array($sc['display']) && count($sc['display']) == 1){
			$where[]	= ($sc['display'][0] == 'n') ? "evt.display = 'n'" : "evt.display = 'y'";
		}elseif(trim($sc['display'])){
			$where[]	= ($sc['display'] == 'n') ? "evt.display = 'n'" : "evt.display = 'y'";
		}

		// 전체이벤트페이지 검색
		if(is_array($sc['event_view']) && count($sc['event_view']) == 1){
			$where[]	= ($sc['event_view'][0] == 'n') ? "evt.event_view = 'n'" : "evt.event_view = 'y'";
		}elseif(trim($sc['event_view'])){
			$where[]	= ($sc['event_view'] == 'n') ? "evt.event_view = 'n'" : "evt.event_view = 'y'";
		}

		$sqlWhereClause = $where ? implode(' AND ',$where) : "";

		$limitStr =" LIMIT {$sc['page']}, {$sc['perpage']} ";

		$sql				= array();
		$sql['field']		= "*,IF(CURRENT_DATE() BETWEEN start_date AND end_date,'진행 중',IF(end_date < CURRENT_DATE(),'종료','시작 전')) AS status";
		$sql['table']		= "fm_gift AS evt";
		$sql['wheres']		= $sqlWhereClause;
		$sql['orderby']		= "ORDER BY {$sc['sort']}";
		$sql['limit']		= $limitStr;

		$result				= pagingNumbering($sql,$sc);

		/*
		$count['total']	 = get_rows('fm_gift');

		$today = date("Y-m-d");
		$sql = "SELECT count(gift_seq) as cnt FROM fm_gift WHERE start_date <= '{$today}' AND end_date >= '{$today}'";
		$query = $this->db->query($sql);
		$temp = $query->row_array();
		$count['ing'] = $temp['cnt'];
		$sql = "SELECT count(gift_seq) as cnt FROM fm_gift WHERE end_date < '{$today}'";
		$query = $this->db->query($sql);
		$temp = $query->row_array();
		$count['end'] = $temp['cnt'];
		*/

		return $result;

	}

}

/* End of file eventmodel.php */
/* Location: ./app/models/eventmodel.php */
