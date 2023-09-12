<?php

use App\libraries\Password;

class Providermodel extends CI_Model {

	var $arr_provider_gb = array(
		'company' => '본사배송',
		'provider'	=> '입점사배송'
	);


	public function get_provider_one($provider_seq){
	    $query = $this->db->select("provider_seq, provider_name, provider_id, provider_gb, provider_status, limit_use, limit_ip, calcu_count, shipping_charge, return_shipping_charge, coupon_penalty_charge")
	    ->from("fm_provider");
	    if(is_array($provider_seq)) {
	        $query = $query->where_in("provider_seq", $provider_seq);
	        $data = $query->get()->result_array();
	    } else {
	        $query = $query->where("provider_seq", $provider_seq);
	        $data = $query->get()->row_array();
	    }
	    return $data;
	}

	// 해당 입점사가 관리자인지 확인한다. :: rsh :: 2019-03-20
	public function is_manager($provider_seq)
	{
	    $query = $this->db->select("manager_yn")->from("fm_provider")->where("provider_seq", $provider_seq)->get();
	    $data = $query->row_array();
	    return $data['manager_yn'] === 'Y';
	}

	public function get_provider_range($provider_arr){

		$query = $this->db->query("select provider_seq, provider_name, provider_id, provider_gb, provider_status, limit_use, limit_ip, deli_group  from fm_provider where provider_seq in ('".implode("', '", $provider_arr)."') ");
		$data = $query->result_array();
		return $data;
	}

	public function get_provider($provider_seq){
		$query = $this->db->query("
			select p.*,c.charge,c.commission_type,d.*, p.provider_seq as provider_seq, g.pgroup_name, g.pgroup_icon  from fm_provider as p
			left join fm_provider_charge as c on (p.provider_seq = c.provider_seq and c.link=1)
			left join fm_provider_shipping as d on p.provider_seq = d.provider_seq
			left join fm_provider_group as g on p.pgroup_seq = g.pgroup_seq
			where p.provider_seq=?
		",array($provider_seq));
		if($query) $data = $query->row_array();
		return $data;
	}

	public function provider_charge_list($provider_seq) {

		$bind = array();
		if(is_array($provider_seq) && count($provider_seq) > 0){
			foreach($provider_seq as $k) $_tmp_provider[] = "?";
			$where = " p.provider_seq IN(".implode(",",$_tmp_provider).")";
			$bind = $provider_seq;
		}elseif(!is_array($provider_seq)){
			$where = "p.provider_seq=?";
			$bind[] = $provider_seq;
		}

		if($where) $wheres = " AND ".$where;

		$query = $this->db->query("
			SELECT
				 p.provider_seq,p.provider_name,p.provider_id,b.title as brand_name
				,c.title,c.category_code,c.charge,c.commission_type,c.link
			FROM
				fm_provider AS p
				LEFT JOIN fm_provider_charge AS c ON p.provider_seq = c.provider_seq
				LEFT JOIN fm_brand AS b ON (b.category_code AND c.category_code = b.category_code)
			WHERE (1) AND ((p.manager_yn='Y' AND p.provider_seq !=1) OR p.provider_seq=1) ". $wheres,$bind);
		return $query->result_array();
	}


	public function provider_list($sc) {

		if(!$sc['pageblock']) $sc['pageblock'] = 10;

		## 미니샵 수 추출
		if($sc['get_mshop'] == 'y'){
			$addSelect	= ", C.cnt	as mshop_cnt ";
			$addFrom	= " left join (select provider_seq, count(*) as cnt
									from fm_member_minishop group by provider_seq) as C
								on A.provider_seq = C.provider_seq ";
		}

		$month = date("Y-m-d H:i:s", mktime(0, 0, 0, intval(date('m', mktime())), 1, intval(date('Y', mktime()))) );

		$search_field = "A.provider_seq as no, A.*, B.*,ifnull(G.pgroup_name,'') pgroup_name
				".$addSelect.",acc.accountall_period_count";

		$search_table = "fm_provider A
				left join fm_provider_charge B on A.provider_seq = B.provider_seq and B.link = 1
				left join fm_provider_group as G on A.pgroup_seq=G.pgroup_seq
				left join (
					SELECT
						t.provider_seq, t.accountall_period_count
					FROM
						fm_account_provider_period as t,
						(SELECT provider_seq,max(regist_date) AS regist_date FROM fm_account_provider_period
							WHERE regist_date <  '".$month."' GROUP BY provider_seq) AS s
					WHERE 1=1 AND t.regist_date=s.regist_date AND t.provider_seq=s.provider_seq
					GROUP BY t.provider_seq ) AS acc ON acc.provider_seq=A.provider_seq
				".$addFrom."";

		## 본사 및 입점사부관리자 제외.
		$sql = " WHERE A.provider_id != 'base' AND A.manager_yn = 'Y'";
		$countWheres[] = "A.provider_id != 'base' AND A.manager_yn = 'Y'";
		###
		if( !empty($sc['search_text'])){
			$sql .= " AND ( A.provider_id like '%".$sc['search_text']."%' OR A.provider_name like '%".$sc['search_text']."%' OR A.info_name like '%".$sc['search_text']."%' ) ";
		}

		###
		if( !empty($sc['sc_provider_name'])){
			$sql .= " AND A.provider_name like '%".$sc['sc_provider_name']."%'";
		}
		###
		if( !empty($sc['provider_seq'])){
			$sql .= " AND A.provider_seq = '".$sc['provider_seq']."' ";
		}
		###
		if( !empty($sc['pgroup_seq'])){
			$sql .= " AND G.pgroup_seq = '".$sc['pgroup_seq']."' ";
		}
		###
		if( !empty($sc['provider_status'])){
			if(is_array($sc['provider_status'])){
				$sql .= " AND A.provider_status IN ('".implode("','",$sc['provider_status'])."') ";
			}else{
				if($sc['provider_status'] != 'all') $sql .= " AND A.provider_status='".$sc['provider_status']."'";
			}
		}
		###
		if( !empty($sc['info_type'])){
			if(is_array($sc['info_type']) && !in_array('all',$sc['info_type']))
				$sql .= " AND A.info_type IN ('".implode("','",$sc['info_type'])."') ";
		}
		###
		if( !empty($sc['calcu_count'])){
			if(is_array($sc['calcu_count']) && !in_array('all',$sc['calcu_count'])){
				$sql .= " AND acc.accountall_period_count IN ('".implode("','",$sc['calcu_count'])."') ";
			}
		}
		###
		if( is_numeric($sc['mshop_cnt_s'])){
			$sql .= " AND IFNULL(C.cnt,0) >= ".(int) $sc['mshop_cnt_s']." ";
		}
		if( is_numeric($sc['mshop_cnt_e'])){
			$sql .= " AND IFNULL(C.cnt,0) <= ".(int) $sc['mshop_cnt_e']." ";
		}
		###
		if( !empty($sc['commission_type'])){
			if($sc['commission_type'] == 'SACO')		$sql .= " AND B.commission_type	= 'SACO' ";
			else if($sc['commission_type'] == 'SUPPLY')	$sql .= " AND B.commission_type IN ('SUCO','SUPR') ";
		}
		###
		if(!empty($sc['regdate'][0]) && !empty($sc['regdate'][1])){
			$sql .= " AND A.regdate between '".$sc['regdate'][0]." 00:00:00' AND '".$sc['regdate'][1]." 23:59:59' ";
		}

		$wheres 			= $sql;

		$orderby			= " ORDER BY {$sc['orderby']} {$sc['sort']}";
		$limit				= " LIMIT {$sc['page']}, {$sc['perpage']} ";

		$sql				= array();
		$sql['field']		= $search_field;
		$sql['table']		= $search_table;
		$sql['wheres']		= $wheres;
		$sql['countWheres'] = $countWheres;
		$sql['orderby']		= $orderby;
		$sql['limit']		= $limit;
		$sql['debug']		= 'y';

		$result				= pagingNumbering($sql,$sc);
		$result['result']	= $result['record'];
		$result['count']	= $result['page']['totalcount'];

		unset($result['record']);

		return $result;
	}

	public function provider_goods_list($where=array()) {

		$wheres = "";
		if(is_array($where) && count($where) > 0){
			$wheres = " AND ".implode(" AND ",$where);
		}

		$sql = "select
				A.provider_seq as no, A.*, B.*,ifnull(G.pgroup_name,'') pgroup_name
			from
				fm_provider A
				left join fm_provider_charge B on A.provider_seq = B.provider_seq and B.link = 1
				left join fm_provider_group as G on A.pgroup_seq=G.pgroup_seq
			where A.provider_id != 'base' and A.manager_yn = 'Y' ".$wheres."
			order by A.provider_seq desc";
		$query = $this->db->query($sql);
		foreach ($query->result_array() as $row){

			$row['mgb'] = $this->arr_provider_gb[$row['provider_gb']];
			$row['provider_seq'] = $row['no'];

			$provider[] = $row;
		}
		return $provider;
	}

	public function provider_goods_list_sort($base=false) {
		$where = "";
		if(!$base){
			$where = " and A.provider_id != 'base' ";
		}
		$sql = "select
				A.provider_seq as no, A.*, B.*,ifnull(G.pgroup_name,'') pgroup_name,
				if(ASCII(SUBSTRING(A.provider_name, 1)) < 128, 2, 1) pm
			from
				fm_provider A
				left join fm_provider_charge B on A.provider_seq = B.provider_seq and B.link = 1
				left join fm_provider_group as G on A.pgroup_seq=G.pgroup_seq
			where A.manager_yn = 'Y'
			".$where."
			order by pm, A.provider_name";
		$query = $this->db->query($sql);
		foreach ($query->result_array() as $row){

			$row['mgb'] = $this->arr_provider_gb[$row['provider_gb']];
			$row['provider_seq'] = $row['no'];

			$provider[] = $row;
		}
		return $provider;
	}

	public function find_group_cnt_list($desc='desc'){

		//$this->db->order_by("group_seq","asc");
		$this->db->order_by("order_sum_price",$desc);
		$this->db->order_by("order_sum_ea",$desc);
		$this->db->order_by("order_sum_cnt",$desc);
		$this->db->order_by("use_type","asc");
		$query = $this->db->get("fm_provider_group");
		foreach ($query->result_array() as $row){
			$qry			= "select count(provider_seq) as count from fm_provider where pgroup_seq = '".$row['pgroup_seq']."' and provider_seq > 1";
			$querys			= $this->db->query($qry);
			$data			= $querys->result_array();
			$row['count']	= $data[0]['count'];

			if($row['order_sum_use']){
				$row['order_sum_use'] = unserialize($row['order_sum_use']);
			}

			$returnArr[]	= $row;
		}
		return $returnArr;
	}

	// 자동 등급조정일 계산
	public function calculate_date($start_month,$grade_clone,$setting=null){

		$this_year = ($setting && date('m') == 12 )? date('Y',strtotime('+1 years')):date('Y');
		$now_st_time	= time();

		//$start_month								//등급 기준월
		$chg_day	= $grade_clone['chg_day'];		//등급 자동갱신 일
		$chg_term	= $grade_clone['chg_term'];		//등급 자동갱신 주기(개월)
		$chk_term	= $grade_clone['chk_term'];		//등급 산출기간(개월)
		$keep_term	= $grade_clone['keep_term'];	//등급 유지기간

		if((int)$start_month < 10)	$s_month = "0".(int)$start_month; else $s_month = $start_month;
		if((int)$chg_day < 10)		$s_day = "0".(int)$chg_day; else $s_day = $chg_day;
		$startdt = date("Y")."".$s_month."".$s_day;

		$monthLst = array();
		$monthTmp = "";
		for($i=$start_month; $i>=1; $i--){

			if(!$monthTmp) $monthTmp = $startdt;
			$monthTmp = date("Ymd",strtotime($monthTmp.' -'.$chg_term.' month'));
			if($i == $start_month) $monthLst[] = $startdt;
			if(date("Y") == substr($monthTmp,0,4)) $monthLst[] = $monthTmp;

		}
		$monthTmp = "";
		for($i=$start_month; $i<=12; $i++){

			if(!$monthTmp) $monthTmp = $startdt;
			$monthTmp = date("Ymd",strtotime($monthTmp.' +'.$chg_term.' month'));
			if(date("Y") == substr($monthTmp,0,4)) $monthLst[] = $monthTmp;

		}

		sort($monthLst);

		$result = array();

		if($chg_day == 1){
			$last_day	= "t";
		}else{
			$last_day = "14";
		}

		$keep_term  = $keep_term - 1;

		foreach($monthLst as $dt){

			$change_ts					= mktime(0,0,0,substr($dt,4,2),substr($dt,6,2),$this_year);		//기준일
			$cal_ts						= strtotime('+'.($chg_term-1).' month',$change_ts);

			## 다음 등급 조정일
			if(!$next_grade_date && mktime(0,0,0,date("m"),date("d"),date("Y")) < $change_ts){
				$next_grade_date = date('Y년 m월 d일',$change_ts);
			}

			## 등급 조정일
			$result['chg_dt'][]			= date('Ymd',$change_ts);
			$result['chg_text'][]		= date('Y년 m월 d일',$change_ts);

			## 산출기간(갱신 기준 직전 0개월)
			$chk_dt_e_ts				= strtotime('-1 month',$change_ts);
			$chk_term_ts				= strtotime('-'.($chk_term).' month',$change_ts);
			$result['chk_dt_s'][]		= date('Ym01',$chk_term_ts);
			$result['chk_dt_e'][]		= date('Ymt',$chk_dt_e_ts);
			$result['chk_text'][]		= date('Y년 m월 01일',$chk_term_ts)." ~ ".date('Y년 m월 t일',$chk_dt_e_ts);

			## 등급 유지기간
			$keep_term_ts				= strtotime('+'.$keep_term.' month',$change_ts);
			$result['keep_dt_s'][]		= date('Ym'.$chg_day,$change_ts);
			$result['keep_dt_e'][]		= date('Ym'.$last_day,$keep_term_ts);
			$result['keep_text'][]		= date('Y년 m월 '.$chg_day.'일',$change_ts)." ~ ".date('Y년 m월 '.$last_day.'일',$keep_term_ts);

		}

		$result['next_grade_date'] = $next_grade_date;

		return $result;
	}


	public function find_group_provider_cnt($pgroup_seq){
		$qry	= "select count(provider_seq) as count from fm_provider where pgroup_seq = '".$pgroup_seq."' and provider_status = 'Y' and manager_yn = 'Y'";
		$querys = $this->db->query($qry);
		$data	= $querys->result_array();
		return $data[0]['count'];
	}

	public function upload_minishop_tempimage($filename,$folder){
		$tmp						= getimagesize($_FILES['Filedata']['tmp_name']);
		$_FILES['Filedata']['type']	= $tmp['mime'];
		$config['upload_path']		= $folder;
		$config['allowed_types']	= 'jpeg|jpg|png|gif';
		$config['max_size']			= 10240;//10MB
		$config['file_name']		= $filename;
		$this->load->library('upload', $config);
		if ( ! $this->upload->do_upload('Filedata')){
			$result = array('status' => '0','error' => $this->upload->display_errors());
		}else{
			$result = array('status' => 1,'fileInfo'=>$this->upload->data());
		}


		return $result;
	}

	public function upload_minishop_image($provider_id, $file, $org_main_visual = null){
		if	(file_exists(ROOTPATH.$file)){
			$new_dir	= '/data/provider';
			if	(!is_dir(ROOTPATH.$new_dir)){
				@mkdir(ROOTPATH.$new_dir);
				@chmod(ROOTPATH.$new_dir,0707);
			}
			preg_match('/\.[a-zA-Z]*$/', $file, $matchs);
			$ext		= $matchs[0];
			$new_file	= $new_dir . '/' . $provider_id . '_visual' . $ext;
			$this->delete_minishop_image($org_main_visual);//이전원본삭제
			rename('.'.$file,'.'.$new_file);

			$org_main_visual = end(explode('/', $new_file));
			$org_mobile_visual = str_replace('/'.$org_main_visual,'/_mobile_'.$org_main_visual,$new_file);
			$new_mobile_file	= $new_dir . '/_mobile_' . $provider_id . '_visual' . $ext;

			$this->load->helper('board');
			board_image_thumb(ROOTPATH.$new_file,ROOTPATH.$new_mobile_file,'320','320');

			return $new_file;
		}
	}

	public function delete_minishop_image($imgpath){
		if	(file_exists(ROOTPATH.$imgpath)){
			@unlink(ROOTPATH.$imgpath);
		}

		$org_main_visual = end(explode('/', $imgpath));
		$org_mobile_visual = str_replace('/'.$org_main_visual,'/_mobile_'.$org_main_visual,$imgpath);

		if	(file_exists(ROOTPATH.$org_mobile_visual)){
			@unlink(ROOTPATH.$org_mobile_visual);
		}
	}

	public function provider_list_for_account_period($period) {

		if($period=='week'){
			$where[] = "A.account_period_type=?";
			$bind[] = "week_account";
		}elseif($period=='all'){
			$where[] = "A.account_period_type=?";
			$bind[] = "mon_account";
		}else{

			$where[] = "A.account_period_type=?";
			$bind[] = "mon_account";

			if($period == 1)  $where[] = "(A.calcu_count=? OR A.calcu_count is null)";
			else $where[] = "A.calcu_count=?";
			$bind[] = $period;
		}

		$where_str = implode(' and ',$where);

		$sql = "select
				A.provider_seq as no, A.*, B.*
			from
				fm_provider A left join fm_provider_charge B on A.provider_seq = B.provider_seq and B.link = 1
			where A.provider_id != 'base' and A.manager_yn = 'Y' and ".$where_str."
			order by A.provider_seq desc";

		$query = $this->db->query($sql,$bind);
		foreach ($query->result_array() as $row){
			if(!$row['calcu_count']) $row['calcu_count'] = 1;
			$row['mgb'] = $this->arr_provider_gb[$row['provider_gb']];
			$row['provider_seq'] = $row['no'];

			$provider[] = $row;
		}
		return $provider;
	}

	public function provider_list_for_account_period_sort($period) {

		if($period=='week'){
			$where[] = "A.account_period_type=?";
			$bind[] = "week_account";
		}elseif($period=='all'){
			$where[] = "A.account_period_type=?";
			$bind[] = "mon_account";
		}else{
			$where[] = "A.account_period_type=?";
			$bind[] = "mon_account";

			if($period == 1)  $where[] = "(A.calcu_count=? OR A.calcu_count is null)";
			else $where[] = "A.calcu_count=?";
			$bind[] = $period;
		}

		$where_str = implode(' and ',$where);

		$sql = "select
				A.provider_seq as no, A.*, B.*,
				if( ASCII( SUBSTRING( A.provider_name, 1 ) ) <128, 2, 1 ) pm
			from
				fm_provider A left join fm_provider_charge B on A.provider_seq = B.provider_seq and B.link = 1
			where A.provider_id != 'base' and A.manager_yn = 'Y' and ".$where_str."
			order by pm, A.provider_name";

		$query = $this->db->query($sql,$bind);
		foreach ($query->result_array() as $row){
			if(!$row['calcu_count']) $row['calcu_count'] = 1;
			$row['mgb'] = $this->arr_provider_gb[$row['provider_gb']];
			$row['provider_seq'] = $row['no'];

			$provider[] = $row;
		}
		return $provider;
	}

	public function get_account_order($sc){

		if	($sc['provider_seq']){
			$addWhere	.= " and oitem.provider_seq = '".$sc['provider_seq']."' ";
		}

		if	($sc['year']){
			$addWhere	.= " and exp.shipping_date like '".$sc['year']."%' ";
		}

		// 정산 쿼리로 맞춤.
		$sql	= "SELECT
					substring( shipping_date, 6, 2 ) export,
					sum(item.ea) export_ea,
					sum(opt.ori_price*opt.ea) opt_price,
					sum(sub.price*sub.ea) sub_price,
					(sum(ifnull(cast(opt.commission_price*opt.ea as signed),0)) + sum(ifnull(cast(sub.commission_price*sub.ea as signed),0))) as commission_price,
					sum(opt.promotion_code_sale) as wcode,
					sum(ifnull(opt.ea,0) + ifnull(sub.ea,0)) as ea,
					oitem.provider_seq,
					sum(ord.emoney) as emoney,
					sum(ord.cash) as cash,
					oitem.provider_seq,
					sum( if(oitem.account_date is null,ifnull(oitem.goods_shipping_cost,0),0) ) as goods_shipping_cost
				FROM
					fm_goods_export_item item
					LEFT JOIN fm_order_item_option opt ON opt.item_option_seq = item.option_seq and item.option_seq
					LEFT JOIN fm_order_item_suboption sub ON sub.item_suboption_seq = item.suboption_seq and item.suboption_seq
					LEFT JOIN fm_goods_export exp ON exp.export_code=item.export_code
					LEFT JOIN fm_order ord ON ord.order_seq=exp.order_seq
					LEFT JOIN fm_order_item oitem ON oitem.item_seq=item.item_seq
					LEFT JOIN fm_provider pvd ON pvd.provider_seq = oitem.provider_seq
				WHERE exp.status = '75'
					and ord.orign_order_seq is null
					and oitem.provider_seq!=1
					and exp.account_date is null
					".$addWhere."
				GROUP BY export ";

		$query = $this->db->query($sql);
		return $query->result_array();
	}

	public function get_account_shipping($sc){

		if	($sc['provider_seq']){
			$addWhere	.= " and exp.shipping_provider_seq = '".$sc['provider_seq']."' ";
			$addWhere2	.= " and provider_seq = '".$sc['provider_seq']."' ";
		}

		if	($sc['date']){
			$addWhere	.= " and exp.shipping_date like '".$sc['date']."%' ";
			$addWhere2	.= " and ( account_date is null or account_date like '".$sc['date']."%' ) ";
		}

		// 정산 쿼리로 맞춤.
		$sql	= "select sum(shipping_cost) as shipping_cost
					from fm_order_shipping
					where
						order_seq = any(
							select exp.order_seq
							from fm_goods_export_item expi,
								fm_order_item item,
								fm_goods_export exp
							where exp.export_code=expi.export_code
								and expi.item_seq=item.item_seq
								and exp.status = '75'
								".$addWhere."
						)
					and provider_seq!=1 ".$addWhere2;

		$query	= $this->db->query($sql);
		return $query->row_array();
	}

	public function get_account_refund($sc){

		if	($sc['provider_seq']){
			$addWhere	.= " and C.provider_seq = '".$sc['provider_seq']."' ";
		}

		if	($sc['date']){
			$addWhere	.= " and A.refund_date like '".$sc['date']."%' ";
		}

		// 정산 쿼리로 맞춤.
		$sql = "
			SELECT
				sum(B.ea) refund_ea,
				sum(ifnull(opt.ori_price*B.ea,0)+ifnull(sub.price*B.ea,0)) as refund_price,
				sum(ifnull(opt.commission_price*B.ea,0)+ifnull(sub.commission_price*B.ea,0)) as refund_commission_price,
				sum(ifnull((opt.ori_price-opt.commission_price)*B.ea,0)+ifnull((sub.price-sub.commission_price)*B.ea,0)) as refund_fee

			FROM
				fm_order_refund A
				LEFT JOIN fm_order_refund_item B ON A.refund_code = B.refund_code
				LEFT JOIN fm_order_item_option opt ON opt.item_option_seq = B.option_seq and B.option_seq
				LEFT JOIN fm_order_item_suboption sub ON sub.item_suboption_seq = B.suboption_seq and B.suboption_seq
				,fm_order_item C
				,fm_order ord
			WHERE
				A.refund_type = 'return'
				AND A.status = 'complete'
				AND C.item_seq = B.item_seq
				AND ord.order_seq=A.order_seq".$addWhere;

		$query	= $this->db->query($sql);
		return $query->row_array();
	}

	public function get_account_return($sc){

		if	($sc['provider_seq']){
			$addWhere2	.= " and item.provider_seq = '".$sc['provider_seq']."' ";
		}

		if	($sc['date']){
			$addWhere1	.= " and return_date like '".$sc['date']."%' ";
			$addWhere3	.= " and exp.shipping_date like '".$sc['date']."%' ";
		}

		// 정산 쿼리로 맞춤.
		$sql	= "select sum(return_shipping_price) as return_shipping_price
					from fm_order_return
					where return_shipping_price > 0
						".$addWhere1."
						and status='complete'
						and return_code = any(
							select reti.return_code
							from fm_order_return_item reti
							where reti.export_code = any(
								select exp.export_code
									from fm_goods_export exp
									where exp.export_code=any(
										select expi.export_code
										from fm_goods_export_item expi,fm_order_item item
										where expi.item_seq=item.item_seq
										".$addWhere2."
									)
								and exp.status = '75' ".$addWhere3."))";

		$query	= $this->db->query($sql);
		return $query->row_array();
	}

	public function get_account_mshop($sc){

		if	($sc['provider_seq']){
			$addWhere	.= " and provider_seq = '".$sc['provider_seq']."' ";
		}

		if	($sc['year']){
			$addWhere	.= " and regist_date like '".$sc['year']."%' ";
		}

		$sql	= "select
					substring( regist_date, 6, 2 ) date, count(*) cnt
					from fm_member_minishop where member_seq > 0
					". $addWhere."
					group by date";

		$query = $this->db->query($sql);
		return $query->result_array();
	}

	public function get_minishop_count($provider_seq){
		$sql	= "select count(*) cnt from fm_member_minishop where provider_seq = '".$provider_seq."' ";

		$query = $this->db->query($sql);
		return $query->row_array();
	}

	// 확인코드 유효성 체크 (@return bloom)
	public function check_certify_code($code){
		// 6자리 이상, 16자리 이하, 영문, 숫자로만 가능
		if	((strlen($code) < 6 || strlen($code) > 16) || (preg_match('/[^0-9a-zA-Z]/', $code))) {
			return false;
		}else{
			return true;
		}
	}

	// 확인자 추출
	public function get_certify_manager($param){
		if	($param['certify_code'])
			$addWhere	 .= " and certify_code = '".$param['certify_code']."' ";
		if	($param['out_seq'])
			$addWhere	 .= " and seq != '".$param['out_seq']."' ";
		if	($param['provider_seq'])
			$addWhere	 .= " and provider_seq = '".$param['provider_seq']."' ";
		if	($param['manager_id'])
			$addWhere	 .= " and manager_id = '".$param['manager_id']."' ";

		if	($param['not_manager_id'])
			$addWhere	 .= " and manager_id != '".$param['not_manager_id']."' ";

		$sql	= "select * from fm_certify_user where seq > 0 ".$addWhere;
		$query	= $this->db->query($sql);

		return $query->result_array();
	}

	// 확인자 정보 추가
	public function insert_certify($param){
		$param['regist_date']		= date('Y-m-d H:i:s');
		$result = $this->db->insert('fm_certify_user', $param);
		return $result;
	}

	// 확인자 정보 삭제
	public function delete_certify($param){
		if	($param['certify_code'])
			$addWhere	 .= " and certify_code = '".$param['certify_code']."' ";
		if	($param['out_seq'])
			$addWhere	 .= " and seq != '".$param['out_seq']."' ";
		if	($param['provider_seq'])
			$addWhere	 .= " and provider_seq = '".$param['provider_seq']."' ";
		if	($param['manager_id'])
			$addWhere	 .= " and manager_id = '".$param['manager_id']."' ";

		if	($addWhere){
			$sql	= "delete from fm_certify_user where seq > 0 ".$addWhere;
			$this->db->query($sql);
		}
	}

	public function get_person($provider_seq){
		$sql	= "select * from fm_provider_person where provider_seq = ? ";
		$query	= $this->db->query($sql, $provider_seq);

		return $query->result_array();
	}

	/* 입점사 등급 설정 정보 */
	public function get_pgroup_data($pgroup_seq){
		$sql	= "select * from fm_provider_group where pgroup_seq='".$pgroup_seq."'";
		$query	= $this->db->query($sql);
		$result = $query->result_array();

		return $result[0];
	}

	public function provider_group_name(){

		### 등급리스트(등급 낮은순으로)
		$grplist_tmp	= $this->find_group_cnt_list('asc');
		$grplist		= array();
		foreach($grplist_tmp as $grpitem) $grplist[$grpitem['pgroup_seq']] = $grpitem['pgroup_name'];

		return $grplist;
	}

	/* 입점사별 판매금액, 판매수량, 판매횟수 구하기 */
	public function provider_sale_stat($provider_seq,$chkdt_s,$chkdt_e){

		$sale_stat		= array();

		## 판매금액, 판매개수 (옵션+서브옵션)
		## 사은품건수(gift) 제외
		$sql = "
			select
				sum(exi.ea) as tot_ea
				,sum((case when ifnull(oio.price,0) > 0 then oio.price else oiso.price end)*exi.ea) as tot_price
			from
				fm_goods_export_item as exi
				left join fm_goods_export as ex on ex.export_code=exi.export_code
				left outer join fm_order_item_option as oio on oio.item_seq=exi.item_seq and oio.item_option_seq=exi.option_seq
				left outer join fm_order_item_suboption as oiso on oiso.item_seq=exi.item_seq
						and oiso.item_suboption_seq=exi.suboption_seq
				left join fm_order_item as oi on oi.order_seq=ex.order_seq and oi.item_seq=exi.item_seq
					and oi.provider_seq=ex.shipping_provider_seq
			where
				ex.shipping_provider_seq = '".$provider_seq."'
				and ex.status = '75'
				and oi.goods_type = 'goods'
				and ex.shipping_date  between date_format('".$chkdt_s."000000','%Y-%m-%d %H:%i:%s') and date_format('".$chkdt_e."235959','%Y-%m-%d %H:%i:%s')
			";
		$query	= $this->db->query($sql);
		$result2 = $query->result_array();
		$sale_stat['total_cnt']		= 0;	//기본
		$sale_stat['total_price']	= $result2[0]['tot_price'];
		$sale_stat['total_ea']		= $result2[0]['tot_ea'];

		$sale_default_stat	= array();
		$sale_sub_stat		= array();
		## 판매건수 : 모든 주문건이 배송완료여야 1건으로 간주. (산출기간 적용)
		## 필수옵션 : 주문번호별 총 판매건수 = 배송완료건수 리스트
		$sql = "
			select
				oi.order_seq
				,count(oi.order_seq) as ord_cnt
				,sum(case when oio.step='75'
						and ex.shipping_date between date_format('".$chkdt_s."000000','%Y-%m-%d %H:%i:%s') and date_format('".$chkdt_e."235959','%Y-%m-%d %H:%i:%s')
					 then 1 else 0 end
				) as deliv_cnt
			from
				fm_order_item_option as oio
				left join fm_order_item as oi on oio.order_seq=oi.order_seq and oio.item_seq=oi.item_seq
				left join fm_goods_export_item as exi on exi.item_seq=oi.item_seq and exi.option_seq=oio.item_option_seq
				left join fm_goods_export as ex on ex.order_seq=oi.order_seq
							and ex.shipping_provider_seq=oi.provider_seq and ex.export_code=exi.export_code
			where
				oi.goods_type='goods'
				and oi.provider_seq='".$provider_seq."'
			group by
				oi.order_seq
			having ord_cnt = deliv_cnt
		";
		$query	= $this->db->query($sql);
		$sale_res = $query->result_array();
		foreach($sale_res as $item) $sale_default_stat[] = $item['order_seq'];

		## 서브옵션 : 주무번호별 총 판매건수 != 배송완료건수 리스트
		$sql = "
			select
				oi.order_seq
				,count(oi.order_seq) as ord_cnt
				,sum(case when oio.step='75'
						and ex.shipping_date between date_format('".$chkdt_s."000000','%Y-%m-%d %H:%i:%s') and date_format('".$chkdt_e."235959','%Y-%m-%d %H:%i:%s')
					 then 1 else 0 end
				) as deliv_cnt
			from
				fm_order_item_suboption as oio
				left join fm_order_item as oi on oio.order_seq=oi.order_seq and oio.item_seq=oi.item_seq
				left join fm_goods_export_item as exi on exi.item_seq=oi.item_seq and exi.suboption_seq=oio.item_suboption_seq
				left join fm_goods_export as ex on ex.order_seq=oi.order_seq
							and ex.shipping_provider_seq=oi.provider_seq and ex.export_code=exi.export_code
			where
				oi.goods_type='goods'
				and oi.provider_seq='".$provider_seq."'
			group by
				oi.order_seq
			having ord_cnt != deliv_cnt
		";
		$query	= $this->db->query($sql);
		$sale_res = $query->result_array();
		foreach($sale_res as $item) $sale_sub_stat[] = $item['order_seq'];

		//debug($sale_default_stat);
		//debug($sale_sub_stat);
		## 최종 판매횟수 : 옵션별 판매 주문건 - 서브옵션별 판매주문건
		## 정상적으로 카운팅 된 판매주문건(총판매건수=배송완료건수) 중 서브옵션 판매분이 있고
		## , 배송완료가 안된 서브옵션 주문건(총판매건수!=배송완료건수)이 있다면 제외.
		$sale_stat_tmp			= 0;
		$sale_stat_tmp			= array_diff($sale_default_stat,$sale_sub_stat);

		$sale_stat['total_cnt'] = count($sale_stat_tmp);	//기본

		return $sale_stat;
	}

	/* 입점사 등급 갱신 */
	public function provider_group_update($mode=null)
	{
		### 자동 등급 조정 설정 불러오기
		$grade_clone = config_load('provider_grade_clone');

		### 등급리스트(등급 낮은순으로)
		$grplist_tmp	= $this->find_group_cnt_list('asc');

		### 수동 등급 번호
		$auto_seq	= array();
		$grplist = $grplist2 = $manual_seq = array();
		foreach($grplist_tmp as $grpitem){
			if(!in_array($grpitem['use_type'],array("auto1","auto2"))){
				$manual_seq[] = $grpitem['pgroup_seq'];
			}else{
				$grplist[] = $grpitem;	//수동등급 제외
			}
		}
		$grplist2 = $this->provider_group_name();


		$today			= date("Ymd");
		$keep_term		= $grade_clone['keep_term'];		//등급 유지 기간.
		$autodt_res		= $this->calculate_date($grade_clone['start_month'],$grade_clone);

		$arr_provider	= array();
		$auto_update	= false;

		## 등급 갱신일인지 확인.
		foreach($autodt_res['chg_dt'] as $chk_k=>$chg_dt){

			$change_ts = mktime(0,0,0,substr($chg_dt,4,2),substr($chg_dt,6,2),substr($chg_dt,0,4));		//기준일

			if($mode == "upt"){
				## 다음 등급 조정일
				if(mktime(0,0,0,date("m"),date("d"),date("Y")) < $change_ts){ $date_use = true; }else{ $date_use = false; }

			}else{
				if($chg_dt == $today){ $date_use = true; }else{ $date_use = false; }
			}

			if(!$chkdt_s && $date_use){
				$auto_update	= true;
				$chkdt_s		= $autodt_res['chk_dt_s'][$chk_k];	//산출기간 시작
				$chkdt_e		= $autodt_res['chk_dt_e'][$chk_k];	//산출기간 종료
			}

		}

		## 오늘이 자동갱신일자 일 경우.
		if($auto_update && $chkdt_s && $chkdt_e) {

			## 수동 등급 제외 검색
			if(count($manual_seq)>0){
				$where = " and p.pgroup_seq not in(".implode(",",$manual_seq).")";
			}
			## 입점사별 기초 데이터 추출 산출 : 배송완료기준, 산출기간 적용, 기본 공급사(1)은 제외
			$sql = "
				select
					p.provider_seq,p.pgroup_seq
					,ifnull(p.pgroup_date,'0000-00-00 00:00:00') pgroup_date
					,(case when ifnull(p.pgroup_date,'0000-00-00 00:00:00') = '0000-00-00 00:00:00' then 0 else 1 end) pgroup_date_use
					,p.regdate
				from
					fm_provider as p
				where
					p.provider_seq > 1 and p.manager_yn = 'Y' ".$where."
			";
			$query	= $this->db->query($sql);
			$result = $query->result_array();
			foreach($result as $proitem){

				$provider_seq	= $proitem['provider_seq'];

				$provider_sale	= $this->provider_sale_stat($provider_seq,$chkdt_s,$chkdt_e);

				if(!$proitem['pgroup_date_use']){
					$provider_sale['pgroup_date_use']	= false;
					$proitem['pgroup_date']				= $proitem['regdate'];
				}else{
					$provider_sale['pgroup_date_use']	= true;
				}

				$provider_sale['pgroup_seq']	= $proitem['pgroup_seq'];
				$provider_sale['pgroup_date']	= $proitem['pgroup_date'];

				$arr_provider[$provider_seq] = $provider_sale;

			}

			$today			= date("Y-m-d",mktime());
			$upt_provider	= array();
			## 입점사 판매금액,판매개수,판매건수
			## 등급 조건 비교.
			foreach($arr_provider as $provider_seq=>$proitem){

				$group_use		= false;
				$provider_price = $proitem['total_price'];
				$provider_ea	= $proitem['total_ea'];
				$provider_cnt	= $proitem['total_cnt'];

				if(!$provider_price)	$provider_price = 0;
				if(!$provider_ea)		$provider_ea	= 0;
				if(!$provider_cnt)		$provider_cnt	= 0;

				$next_sort		= 0;
				$old_sort		= 0;
				$upt_grp_list = array();	//조건에 부합하는 등급을 배열로 저장
				foreach($grplist as $grp_sort=>$grpitem){

					$order_use_tmp = $grpitem['order_sum_use'];

					$order_sum_chk['price']	= false;
					$order_sum_chk['ea']	= false;
					$order_sum_chk['cnt']	= false;

					//기준 판매금액
					if(in_array("price1",$order_use_tmp) || in_array("price2",$order_use_tmp)){
						$order_sum_price		= $grpitem['order_sum_price'];
						$order_sum_chk['price']	= true;
					}else{ $order_sum_price = 0; }
					//기준 판매갯수
					if(in_array("ea1",$order_use_tmp) || in_array("ea2",$order_use_tmp)){
						$order_sum_ea		= $grpitem['order_sum_ea'];
						$order_sum_chk['ea']= true;
					}else{ $order_sum_ea = 0; }
					//기준 판매건수
					if(in_array("cnt1",$order_use_tmp) || in_array("cnt2",$order_use_tmp)){
						$order_sum_cnt		= $grpitem['order_sum_cnt'];
						$order_sum_chk['cnt']= true;
					}else{ $order_sum_cnt = 0; }

					$pgroup_seq					= $grpitem['pgroup_seq'];
					$upt_grp_list[$grp_sort]	= '';

					## 사용(판매금액/수량/판매횟수)중인 모든 조건에 만족
					$chk_ok = false;
					if($grpitem['use_type'] == "auto1"){
						$chk_ok1 = false;
						$chk_ok2 = false;
						$chk_ok3 = false;
						foreach($order_sum_chk as $chk_gubun=>$chkuse){
							switch($chk_gubun){
								case "price":
									if ($chkuse){
										if($provider_price >= $order_sum_price) $chk_ok1 = true;
									}else{  $chk_ok1 = true; }
								break;
								case "ea":
									if ($chkuse){
										if($provider_ea >= $order_sum_ea) $chk_ok2 = true;
									}else{  $chk_ok2 = true; }
								break;
								case "cnt":
									if ($chkuse){
										if( $provider_cnt >= $order_sum_cnt) $chk_ok3 = true;
									}else{  $chk_ok3 = true; }
								break;
							}
						}

						if($chk_ok1 && $chk_ok2 && $chk_ok3) $chk_ok = true;
					}
					## 사용(판매금액/수량/판매횟수)중인 1가지 조건이라도 만족
					if($grpitem['use_type'] == "auto2"){
						foreach($order_sum_chk as $chk_gubun=>$chkuse){
							switch($chk_gubun){
								case "price":
									if ($chkuse && $provider_price >= $order_sum_price) $chk_ok = true;
								break;
								case "ea":
									if ($chkuse && $provider_ea >= $order_sum_ea) $chk_ok = true;
								break;
								case "cnt":
									if ($chkuse && $provider_cnt >= $order_sum_cnt) $chk_ok = true;
								break;
							}
						}
					}

					if($chk_ok){
						$upt_grp_list[$grp_sort]	= $pgroup_seq;
						if($next_sort < $grp_sort || $grp_sort == 0){ $next_sort = $grp_sort; }	// 기존보다 높은 등급일경우
					}

					if($grpitem['pgroup_seq'] == $proitem['pgroup_seq']){ $old_sort = $grp_sort; }	//현재등급 순번
				}

				## 등급 유지 종료일 확인(등급갱신일+유지개월수-1일)
				$keep_dt_e_tmp	= date("Y-m-d H:i:s",strtotime($proitem['pgroup_date'].' +'.$keep_term.' month'));
				$keep_dt_e		= date("Y-m-d",strtotime($keep_dt_e_tmp.' -1 days'));

				## 등업시
				$pro_group_item = array();
				$pro_group_item['pgroup_seq']	= $proitem['pgroup_seq'];

				## 등업조건에 해당하면 : 무조건 등업, 등업일 갱신
				if($next_sort > $old_sort){
					$pro_group_item['next_pgroup_seq']	= $upt_grp_list[$next_sort];
					$pro_group_item['next_pgroup_txt']	= "등업";
					$pro_group_item['date_update']		= true;
				}else{
				## 등업을 못할 시
					## 등급 유지기간이 종료되지 않았다면 원래 등급 그대로 유지 : 날짜 갱신 안함.
					if($today < $keep_dt_e && $proitem['pgroup_seq'] > 0){
						$pro_group_item['next_pgroup_seq']	= $proitem['pgroup_seq'];
						## 현재등급과 동일하면 날짜 갱신
						if($next_sort == $old_sort){
							$pro_group_item['date_update']		= true;
							$pro_group_item['next_pgroup_txt']	= "유지_등급유지";
						}else{
						## 현재등급보다 떨어지면 날짜 갱신 안함
							if(!$proitem['pgroup_date_use']){
								$pro_group_item['date_update']		= true;		//최초등록시 등급 갱신일이 입력되지 않았따면.
							}else{
								$pro_group_item['date_update']		= false;
							}
							$pro_group_item['next_pgroup_txt']	= "유지_등급하향(종료:".$keep_dt_e.")";
						}
					}else{
					## 등급 유지기간이 종료됨, 등업일 갱신
						$pro_group_item['next_pgroup_seq']	= $upt_grp_list[$next_sort];
						$pro_group_item['next_pgroup_txt']	= "재조정";
						$pro_group_item['date_update']		= true;
					}
				}
				$pro_group_item['pgroup_date']	= $proitem['pgroup_date'];
				$pro_group_item['keep_dt_e']	= $keep_dt_e;
				$pro_group_item['keep_term']	= $keep_term;
				$pro_group_item['price']		= $provider_price;
				$pro_group_item['ea']			= $provider_ea;
				$pro_group_item['cnt']			= $provider_cnt;
				$upt_provider[$provider_seq]	= $pro_group_item;

			}

			## 입점사 등급 업데이트
			foreach($upt_provider as $provider_seq=>$pgroup_item){

				$pgroup_seq = $pgroup_item['next_pgroup_seq'];

				$fdata = array('pgroup_seq'=>$pgroup_seq);

				if($pgroup_item['date_update']){
					$fdata['pgroup_date'] = date("Y-m-d H:i:s",mktime());
				}

				/* 수정 로그 */
					$pro_info		= $this->providermodel->get_provider($provider_seq);
					$provider_log	= $pro_info['provider_log'];
					$change_use		= false;

					if(!$pro_info['pgroup_seq'] || $pro_info['pgroup_seq'] != $pgroup_seq){
						$value1 = $pro_info['pgroup_seq'] ? $grplist2[$pro_info['pgroup_seq']]."(".$pro_info['pgroup_seq'].")" : '없음';
						$value2 = $pgroup_seq ? $grplist2[$pgroup_seq]."(".$pgroup_seq.")" : '없음';
						$provider_log .= "<div>[자동] ".date("Y-m-d H:i:s")." ".$value1." -> ".$value2." (".$pgroup_item['keep_term']."개월간 등급 유지) - 상세 :".$pgroup_item['next_pgroup_txt']."</div>";
						$change_use = true;
					}

					if($change_use) $fdata['provider_log'] = $provider_log;
				/* 수정 로그 */

				$this->db->where('provider_seq', $provider_seq);
				$result		= $this->db->update('fm_provider', $fdata);
			}

			if($mode == "upt"){
				echo date("Y-m-d H:i:s",mktime())." :: 적용완료되었습니다.";
			}

		}

	}


	public function get_provider_return_address($provider_seq){

		$sql = "select * from fm_provider where provider_seq = '".$provider_seq."'";
		$query = $this->db->query($sql);
		$provider_info = $query->row_array();

		if($provider_info['deli_group'] == "company"){
			$sql = "select * from fm_provider_shipping where provider_seq = '1'";
			$query = $this->db->query($sql);
			$result = $query->row_array();

			if($result['return_zipcode']){
				$return_address = $result['return_zipcode']." ";
				if($result['return_address_street']){
					$return_address .= $result['return_address_street'];
				}else{
					$return_address .= $result['return_address'];
				}
				$return_address .= " ".$result['return_address_detail'];
			}else{
				$sql = "select * from fm_provider where provider_seq = '1'";
				$query = $this->db->query($sql);
				$provider_info = $query->row_array();

				$return_address = $provider_info['info_zipcode']." ";
				if($provider_info['info_address1_street']){
					$return_address .= $provider_info['info_address1_street'];
				}else{
					$return_address .= $provider_info['info_address1'];
				}
				$return_address .= " ".$provider_info['info_address2'];
			}
		}else{
			$sql = "select * from fm_provider_shipping where provider_seq = '".$provider_seq."'";
			$query = $this->db->query($sql);
			$result = $query->row_array();

			if($result['return_zipcode']){
				$return_address = $result['return_zipcode']." ";
				if($result['return_address_street']){
					$return_address .= $result['return_address_street'];
				}else{
					$return_address .= $result['return_address'];
				}
				$return_address .= " ".$result['return_address_detail'];
			}else{
				$return_address = $provider_info['info_zipcode']." ";
				if($provider_info['info_address1_street']){
					$return_address .= $provider_info['info_address1_street'];
				}else{
					$return_address .= $provider_info['info_address1'];
				}
				$return_address .= " ".$provider_info['info_address2'];
			}

		}

		return $return_address;

	}

	public function set_default_stock_check($inputs)
	{
		$update_params[] = $inputs['default_export_stock_check'];
		$update_params[] = $inputs['default_export_stock_step'];
		$update_params[]  = $inputs['default_export_ticket_stock_check'];
		$update_params[] = $inputs['default_export_ticket_stock_step'];
		$update_params[] = $inputs['provider_seq'];
		$query = "update fm_provider set default_export_stock_check=?,default_export_stock_step=?, default_export_ticket_stock_check=?,default_export_ticket_stock_step=? where provider_seq=?";
		$this->db->query($query,$update_params);
	}

	public function logout(){
		$this->session->unset_userdata('provider');
	}

	public function get_manager($provider_seq){
		$bind[] = $provider_seq;
		$query = "select * from fm_provider where provider_seq=?";
		$query = $this->db->query($query,$bind);
		$row = $query->row_array();
		return $row;
	}

	public function update_passwd($provider_seq,$passwd){

		$log = "<div>".date("Y-m-d H:i:s")." 비밀번호가 변경되었습니다. (".$_SERVER['REMOTE_ADDR'].")</div>";

		$queryBinds = [
			Password::encrypt($passwd),
			$log,
			$provider_seq
		];
		$query = "update fm_provider set provider_passwd=?,provider_log=concat(?,provider_log),passwordUpdateTime=now() where provider_seq=?";
		$query = $this->db->query($query, $queryBinds);
	}

	public function update_date($provider_seq)
	{
		$log = "<div>".date("Y-m-d H:i:s")." 90일 이후 비밀번호 변경으로 설정하였습니다. (".$_SERVER['REMOTE_ADDR'].")</div>";
		$bind[] = $log;
		$bind[] = $provider_seq;
		$query = "update fm_provider set passwordUpdateTime=now(),provider_log=concat(?,provider_log) where provider_seq=?";
		$query = $this->db->query($query,$bind);
	}

	// 마지막 비밀번호변경 경과일수 반환
	public function chk_change_pass_day($provider_seq){
		$data_manager = $this->get_manager($provider_seq);

		if( $data_manager['passwordUpdateTime'] == '0000-00-00 00:00:00' || !$data_manager['passwordUpdateTime'] ) $data_manager['passwordUpdateTime'] = $data_manager['regdate'];

		$change_pass_day = (time()-strtotime($data_manager['passwordUpdateTime'])) / (24*3600);

		return (int)$change_pass_day;
	}

	public function get_provider_charge($provider_seq, $category_code, $location_code='',$field='charge'){

		$this->db->select($field);
		$this->db->from("fm_provider AS p");
		$this->db->join("fm_provider_charge AS c", "p.provider_seq = c.provider_seq");
		$this->db->where("p.provider_seq",$provider_seq);
		if($category_code){
			$this->db->where("c.category_code",$category_code);
		}else if($location_code){
			$this->db->where("c.location_code",$location_code);
		}
		$query = $this->db->get();

		return $query;
	}

	public function get_provider_group($provider_seq = null){
		if	( $provider_seq ) $where = " and (provider_group in ({$provider_seq}) or provider_seq in ({$provider_seq})) ";
		$sql = "select
				A.provider_seq
			from
				fm_provider A
			where provider_id!='base' and provider_status = 'Y' {$where} group by provider_seq";
		return $this->db->query($sql)->result_array();
	}

	// 입점사 목록 오름차순
	public function provider_list_sort($_PARAM = array()) {
		// 초기화
		$str_where_order = "";
		$where_order[] = "A.provider_id != 'base'";

		if($_PARAM['provider_seq'] && $_PARAM['provider_seq']!='all' ){
			$where_order[] = "A.provider_seq = '".$_PARAM['provider_seq']."'";
		}

		if($_PARAM['pay_period'] && $_PARAM['pay_period']!='all'){
			$where_order[] = "A.calcu_count = '".$_PARAM['pay_period']."'";
		}

		if($where_order){
			$str_where_order .= " AND " . implode(' AND ',$where_order);
		}

		$sql = "select
				A.provider_seq as no,
				A.provider_id,
				A.provider_name,
				A.calcu_count
			from
				fm_provider A
			where A.manager_yn = 'Y'
			".$str_where_order."
			order by A.provider_seq asc";
		$query = $this->db->query($sql);

		// 본사도 포함될 수 있도록 수정
		$provider = array();
		if($_PARAM['include_base'] == '1'){
			$include_base_sql = "select
					A.provider_seq as no,
					A.provider_seq,
					A.provider_id,
					A.provider_name,
					A.calcu_count
				from
					fm_provider A
				where A.provider_seq = '1'
			";
			$include_base_query = $this->db->query($include_base_sql);
			$base_provider = $include_base_query->row_array();
			if($base_provider){
				$provider[] = $base_provider;
			}
		}

		foreach ($query->result_array() as $row){
			$row['provider_seq'] = $row['no'];

			$provider[] = $row;
		}
		return $provider;
	}

	//선택된 입점사 리스트에 정산정보 포함하여 리턴
	public function get_provider_select_list($provider_list){
		if(is_array($provider_list)){
			$provider_tmp	= $provider_list;
		}else{
			$provider_tmp	= explode('|', $provider_list);
		}
		if(is_array($provider_tmp)) $provider_arr	= array_filter($provider_tmp); else $provider_tmp = '';

		$provider_select_list	= $this->provider_charge_list($provider_arr);
		$tmp_proivder_select	= array();
		foreach($provider_select_list as $data){
			list($data['charge_text'],$data['commission_text']) = $this->get_commission_type($data['charge'],$data['commission_type']);
			unset($data['charge_seq'],$data['link'],$data['title'],$data['category_code'],$data['brand_name']);
			$tmp_proivder_select[] = $data;
		}

		$provider_list	= $tmp_proivder_select;

		return $provider_list;
	}

	public function get_provider_goods_cnt($provider_seq=null){

		if(!$provider_seq) return null;

		$this->db->select("C.goods_kind, C.package_yn,COUNT(DISTINCT(C.goods_seq)) as cnt");
		$this->db->from('fm_goods AS C');
		$this->db->join('fm_goods_option AS OP','C.goods_seq = OP.goods_seq');
		$this->db->where(array('OP.default_option'=>'y','C.provider_seq'=>$provider_seq,'C.goods_type'=>'goods'));
		$this->db->group_by('C.goods_kind,C.package_yn');
		$query = $this->db->get();

		$goodsCount = array('goods_default' => 0,'goods_social' => 0, 'goods_package' => 0);
		foreach($query->result_array() as $_row){
			if($_row['goods_kind'] == 'goods' &&  $_row['package_yn'] == 'n') {
				$goodsCount['goods_default'] = $_row['cnt'];
			}
			if($_row['goods_kind'] == 'coupon' &&  $_row['package_yn'] == 'n') {
				$goodsCount['goods_social'] = $_row['cnt'];
			}
			if($_row['goods_kind'] == 'goods' &&  $_row['package_yn'] == 'y') {
				$goodsCount['goods_package'] = $_row['cnt'];
			}
		}

		return $goodsCount;
	}

	public function get_commission_type($commission_charge,$commission_type=''){
		switch($commission_type){
			case "SACO":
				$commission_text	= '수수료정산';
				$commission_charge	= $commission_charge."%";
			break;
			case "SUPR":
				$commission_text	= '공급가정산';
				$commission_charge	= get_currency_price($commission_charge,2);
			break;
			case "SUCO":
				$commission_text	= '공급률정산';
				$commission_charge	= $commission_charge."%";
			break;
			default:
				$commission_text	= '수수료정산';
				$commission_charge	= $commission_charge."%";
			break;
		}

		return array($commission_charge,$commission_text);
	}

	public function provider_id_check($provider_id) {
		if(!$provider_id) return false;

		###
		$count = get_rows('fm_provider',array('provider_id'=>$provider_id));

		$text = "사용할 수 있는 아이디 입니다.";
		$return = true;
		if(strlen($provider_id)<4 || strlen($provider_id)>16){
			$text = "아이디는 최소 4자 이상, 최대 16자 이하로 입력해주세요.";
			$return = false;
		}
		if( $return && preg_match("/[^a-z0-9\-_]/i", $provider_id)) {
			$text = "아이디는 영문,숫자, -, _외에는 사용할 수 없습니다.";
			$return = false;
		}
		if( $return &&  $count > 0){
			$text = "이미 사용중인 아이디 입니다.";
			$return = false;
		}

		$result = array("return_result" => $text, "provider_id" => $provider_id, "return" => $return);

		return $result;
	}

	public function check_provider_pwd_history($provider_seq,$passwd) {
		$str_md5 = md5($passwd);
		$str_sha256_md5 = hash('sha256',$str_md5);

		$queryBinds = [
			$provider_seq,
			$str_md5,
			$str_sha256_md5,
			Password::encrypt($passwd)
		];

		$sql = "select count(*) as cnt from (select * from fm_provider_pwd_history where provider_seq=? order by regist_date desc limit 2) a where a.pwd=? or a.pwd=? or a.pwd=?;";
		$query = $this->db->query($sql, $queryBinds);
		$row = $query->row_array();
		return $row;
	}

	public function insert_provider_pwd_history($provider_seq,$passwd) {
		$sql = "insert into fm_provider_pwd_history set provider_seq=?, pwd=?, regist_date=now()";
		$queryBinds = [
			$provider_seq,
			Password::encrypt($passwd)
		];
		$this->db->query($sql, $queryBinds);
	}

	/** 
	 * 입점사> 주문 미처리 현황(미출고 상품) 
	 */
	public function remind_export_list($sc){
	
		/* SubQuery */
		$this->db->select("fp.provider_status ,
							fp.provider_id ,
							fp.provider_name ,
							fp.provider_seq ,
							fp.pgroup_seq ,
							fp.info_type ,
							(select sum(case when foio.step >= 25 AND foio.step < 75 then foio.ea-(foio.step35+foio.step45+foio.step55+foio.step65+foio.step75+foio.step85) end) from fm_order_item_option foio where foio.item_seq = foi.item_seq) as step_25_count,
							(select sum(case when fois.step >= 25 AND fois.step < 75 then fois.ea-(fois.step35+fois.step45+fois.step55+fois.step65+fois.step75+fois.step85) end) from fm_order_item_suboption fois where fois.item_seq = foi.item_seq) as step_25_count_1,
							(select sum(foio.step35) from fm_order_item_option foio where foio.item_seq = foi.item_seq and foio.step35 > 0) as step_35_count,
							(select sum(fois.step35) from fm_order_item_suboption fois where fois.item_seq = foi.item_seq and fois.step35 > 0) as step_35_count_1,
							(select sum(foio.step45) from fm_order_item_option foio where foio.item_seq = foi.item_seq and foio.step45 > 0) as step_45_count,
							(select sum(fois.step45) from fm_order_item_suboption fois where fois.item_seq = foi.item_seq and fois.step45 > 0) as step_45_count_1,
							(select mobile from fm_provider_person fpp where fpp.provider_seq=fp.provider_seq and gb='ds1') as person_mobile1,
							(select mobile from fm_provider_person fpp where fpp.provider_seq=fp.provider_seq and gb='ds2') as person_mobile2"
						);

		$this->db->from('fm_provider fp');
		
		$this->db->join('fm_order_item foi', 'foi.provider_seq=fp.provider_seq', 'inner');
		$this->db->join('fm_order_shipping fos', 'fos.shipping_seq = foi.shipping_seq', 'inner');
		$this->db->join('fm_order fo', 'fo.order_seq = foi.order_seq', 'inner');
		
		$_bind = array();
		$_bind['fp.provider_seq !='] = 1; // 본사 주문 제외
		$_bind['fos.provider_seq !='] = 1; // 본사 위탁 배송 제외
		$_bind['fo.step >='] = 25; // 결제확인 상태 이상
		$_bind['fo.step <'] = 75; // 배송완료 상태 미만
		if(!empty($sc['regdate'][0]) && !empty($sc['regdate'][1])){
			$_bind['fo.deposit_date >='] = $sc['regdate'][0]." 00:00:00"; // 결제확인 날짜 기준(시작일)
			$_bind['fo.deposit_date <='] = $sc['regdate'][1]." 59:59:59"; // 결제확인 날짜 기준(마침일)
		}
		$this->db->where($_bind);

		$subQuery = $this->db->get_compiled_select();

		# SELECT
		$this->db->select("SQL_CALC_FOUND_ROWS
				t.provider_status,
				t.provider_id ,
				t.provider_name ,
				t.provider_seq,
				t.info_type,
				(sum(case when t.step_25_count is not null then t.step_25_count else 0 end) + sum(case when t.step_25_count_1 is not null then t.step_25_count_1 else 0 end)) as step_25_count,
				(sum(case when t.step_35_count is not null then t.step_35_count else 0 end) + sum(case when t.step_35_count_1 is not null then t.step_35_count_1 else 0 end)) as step_35_count,
				(sum(case when t.step_45_count is not null then t.step_45_count else 0 end) + sum(case when t.step_45_count_1 is not null then t.step_45_count_1 else 0 end)) as step_45_count,
				(case when REPLACE(t.person_mobile1, ' ', '') = '' then null else t.person_mobile1 end ) as person_mobile1,
				(case when REPLACE(t.person_mobile2, ' ', '') = '' then null else t.person_mobile2 end ) as person_mobile2"
		, false);

		$this->db->from("({$subQuery}) t");

		# WHERE (search)
		if( $sc['provider_seq']){
			$sc['provider_seq'] = preg_replace('/[^0-9]/i', '', $sc['provider_seq']);
			$this->db->where('t.provider_seq', $sc['provider_seq']);
		}

		if( count($sc['found_info_mobile']) == 1){
			if($sc['found_info_mobile'][0] == 'Y'){ // 물류담당자 연락처 있음
				$this->db->where("((person_mobile1 is not null AND person_mobile1 != '') OR (person_mobile2 is not null AND person_mobile2 != ''))");
			}else if($sc['found_info_mobile'][0] == 'N'){ // 물류담당자 연락처 없음
				$this->db->where("((person_mobile1 is null OR person_mobile1 = '' ) AND  (person_mobile2 is null OR person_mobile2 = '' ))");
			}
		}

		// 결제확인/상품준비/출고준비 값이 1건 이상인 경우만 출력
		$this->db->where("(step_25_count>0 OR step_35_count>0 OR step_45_count>0)");
		
		# GROUP
		$this->db->group_by('t.provider_seq');

		// LIMIT
		if ($sc['perpage']) {
			$sc['page'] = preg_replace('/[^0-9]/i', '', $sc['page']);
			$sc['perpage'] = preg_replace('/[^0-9]/i', '', $sc['perpage']);
			$this->db->limit($sc['perpage'], $sc['page']);
		}

		// ORDER BY
		if($sc['display_sort']){
			$this->db->order_by($sc['display_sort']);
		}

		$query = $this->db->get();
		return  $query->result_array();
	}

	/**
	 * 검색한 result의 총 count
	 */
	public function get_remind_export_count()
	{
		$query = $this->db->select('FOUND_ROWS() as COUNT', false)->get()->row_array();

		return $query['COUNT'];
	}

}