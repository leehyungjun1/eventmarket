<?php
class Membermodel extends CI_Model {

	var $group_benifit;

	public function get_member_group_list($sc){

		$orderby			= "ORDER BY {$sc['orderby']} {$sc['sort']}";
		$limitStr			= " LIMIT {$sc['page']}, {$sc['perpage']} ";

		$sql				= array();
		$sql['field']		= "*,(SELECT count(member_seq) FROM fm_member WHERE group_seq = grp.group_seq AND status != 'withdrawal') AS count";
		$sql['table']		= "fm_member_group AS grp";
		$sql['wheres']		= "";
		$sql['orderby']		= $orderby;
		$sql['limit']		= $limitStr;

		$result				= pagingNumbering($sql,$sc);

		return $result;
	}

	/* ADMIN > MEMBER */
	public function find_group_list(){

		//$this->db->order_by("group_seq","asc");
		$this->db->order_by("order_sum_price","desc");
		$this->db->order_by("order_sum_ea","desc");
		$this->db->order_by("order_sum_cnt","desc");
		$this->db->order_by("use_type","asc");
		$query = $this->db->get("fm_member_group");
		foreach ($query->result_array() as $row){
			$returnArr[] = $row;
		}
		return $returnArr;
	}

	/* ADMIN > SETTING > GROUP */
	public function find_group_cnt_list(){

		//$this->db->order_by("group_seq","asc");
		$this->db->order_by("order_sum_price","desc");
		$this->db->order_by("order_sum_ea","desc");
		$this->db->order_by("order_sum_cnt","desc");
		$this->db->order_by("use_type","asc");
		$query = $this->db->get("fm_member_group");
		foreach ($query->result_array() as $row){
			$qry = "select count(member_seq) as count from fm_member where group_seq = '{$row['group_seq']}' and status != 'withdrawal'";
			$querys = $this->db->query($qry);
			$data = $querys->result_array();
			$row['count'] = $data[0]['count'];

			//사용여부 구문추가 @2016-08-01 ysm
			if(preg_match('/a:/',$row['order_sum_use']))
				$row['order_sum_arr'] = unserialize($row['order_sum_use']);

			$returnArr[] = $row;
		}
		return $returnArr;
	}

	/**
	 * 회원 번호 배열을 통해 회원 연락처를 가져온다.
	 * @param array $seqs
	 * @param array $fields
	 */
	public function get_member_by_seqs($seqs, $fields = array())
	{
	    // 비밀번호 필드는 가져오지 않는다.
	    $hasPassword = array_search("password", $fields);
	    if($hasPassword !== false) {
	        unset($fields[$hasPassword]);
	    }

	    if($fields < 1) {
	        return array();
	    }

	    // 암호화 필드 치환
        foreach(array('email', 'phone', 'cellphone') as $encField) {
            if(!isset($key)) {
                $key = get_shop_key();
            }
            $encKey = array_search($encField, $fields);
            if($encKey !== false) {
                $fields[$encKey] = "AES_DECRYPT(UNHEX({$fields[$encKey]}), '{$key}') as {$fields[$encKey]}";
            }
        }
        $fieldsStr = implode(",", $fields);
        $query = $this->db->select($fieldsStr, false)
	    ->from("fm_member")
	    ->where_in("member_seq", $seqs)
	    ->get();
	    return $query->result_array();
	}

	/* ADMIN > MEMBER */
	public function get_member_data($seq){
		if (!$seq || !is_numeric(($seq))) return;

		$key = get_shop_key();
		$sql = $this->db->select("
			A.*, B.*,
			A.member_order_cnt as  order_cnt,
			A.member_order_price as  order_sum,
			A.member_recommend_cnt ,
			A.member_invite_cnt,
			A.member_seq as member_seq,
			AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
			AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
			AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone,
			CASE WHEN A.status = 'done' THEN '승인'
				WHEN A.status = 'hold' THEN '미승인'
				WHEN A.status = 'withdrawal' THEN '탈퇴'
				WHEN A.status = 'dormancy' THEN '휴면'
			ELSE '' END AS status_nm,
			C.group_name,
			C.group_seq,
			C.icon,
			D.withdrawal_seq, D.reason, D.memo, D.regist_ip,
			D.regist_date as withdrawal_date,
			A.referer, A.referer_domain, E.referer_group_cd,
			IF(E.referer_group_no>0, E.referer_group_name, IF(LENGTH(A.referer)>0,'기타','직접입력')) as referer_name,
			A.rute,
			A.sns_change,
			CASE WHEN length(A.sns_n) >= '10'
				THEN concat(left(A.sns_n, 10 - 1),'*n')
				ELSE concat(left(A.sns_n, length(A.sns_n) - 1),'*n')
			END AS conv_sns_n
		")
		->from('fm_member A')
		->join('fm_member_business B', 'A.member_seq = B.member_seq', 'left')
		->join('fm_member_group C', 'A.group_seq = C.group_seq', 'left')
		->join('fm_member_withdrawal D', 'A.member_seq = D.member_seq', 'left')
		->join('fm_referer_group E', 'A.referer_domain = E.referer_group_url', 'left')
		->where('A.member_seq', $seq);

		//프론트인경우
		if (defined('__ADMIN__') != true && defined('__SELLERADMIN__') != true) {
			//승인회원만
			$sql->where("A.status", 'done');
		}

		$query = $sql->get();
		foreach ($query->result_array() as $row){
			$data[] = $row;
		}

		// 사업자 회원일 경우 업체명->이름, 사업장주소->주소, 담당자전화번호->전화번호, 핸드폰->핸드폰
		if($data[0]['business_seq']){
			$data[0]['user_name'] = $data[0]['bname'];
			$data[0]['address_type'] = $data[0]['baddress_type'];
			$data[0]['address'] = $data[0]['baddress'];
			$data[0]['address_detail'] = $data[0]['baddress_detail'];
			$data[0]['address_street'] = $data[0]['baddress_street'];
			$data[0]['zipcode'] = $data[0]['bzipcode'];
			$data[0]['phone'] = $data[0]['bphone'];
			$data[0]['cellphone'] = $data[0]['bcellphone'];

			$tmp = explode('-',$data[0]['bphone']);
			foreach($tmp as $k => $datas){
				$bkey = 'phone'.($k+1);
				$data[0][$bkey] = $datas;
			}

			$tmp = explode('-',$data[0]['bcellphone']);
			foreach($tmp as $k => $datas){
				$bkey = 'cellphone'.($k+1);
				$data[0][$bkey] = $datas;
			}

			$tmp = explode('-',$data[0]['bzipcode']);
			foreach($tmp as $k => $datas){
				$bkey = 'zipcode'.($k+1);
				$data[0][$bkey] = $datas;
			}
		}

		// 기본배송지 추출 :: 2017-04-10 lwh
		$this->db->select("*, AES_DECRYPT(UNHEX(recipient_phone), '{$key}') as recipient_phone, AES_DECRYPT(UNHEX(recipient_cellphone), '{$key}') as recipient_cellphone");
		$query = $this->db->get_where('fm_delivery_address', array('member_seq'=>$seq, 'default'=>'Y'));
		$result = $query->row_array();
		if($result){

			if($result['nation'] == 'KOREA' || $result['international'] == 'domestic'){
				$member_address['zipcode']				= $result['recipient_zipcode'];
				$member_address['address_type']			= $result['recipient_address_type'];
				$member_address['address']				= $result['recipient_address'];
				$member_address['address_street']		= $result['recipient_address_street'];
				$member_address['address_detail']		= $result['recipient_address_detail'];
			}else{
				$member_address['international_address']= $result['international_address'];
				$member_address['international_town_city']= $result['international_town_city'];
				$member_address['international_county']	= $result['international_county'];
				$member_address['international_postcode']= $result['international_postcode'];
				$member_address['international_country']= $result['international_country'];
			}
			$member_address['user_name']			= $result['recipient_user_name'];
			$member_address['phone']				= $result['recipient_phone'];
			$member_address['cellphone']			= $result['recipient_cellphone'];
			$member_address['nation']				= $result['nation'];
			$member_address['international']		= $result['international'];
			$member_address['address_seq']			= $result['address_seq'];

		}else{
			$member_address['user_name']			= $data[0]['user_name'];
			$member_address['phone']				= $data[0]['phone'];
			$member_address['cellphone']			= $data[0]['cellphone'];
			$member_address['zipcode']				= $data[0]['zipcode'];
			$member_address['address_type']			= $data[0]['address_type'];
			$member_address['address']				= $data[0]['address'];
			$member_address['address_street']		= $data[0]['address_street'];
			$member_address['address_detail']		= $data[0]['address_detail'];
			$member_address['nation']				= 'KOREA';
			$member_address['international']		= 'domestic';
		}

		if($member_address['phone'] == '--')		$member_address['phone'] = '';
		if($member_address['cellphone'] == '--')	$member_address['cellphone'] = '';
		$phone		= $this->chkPhoneDash($member_address['phone']);
		$tmp = explode('-',$phone);
		foreach($tmp as $k => $v){ $key = 'phone'.($k+1); $member_address[$key] = $v; }
		$cellphone	= $this->chkPhoneDash($member_address['cellphone']);
		$tmp = explode('-',$cellphone);
		foreach($tmp as $k => $v){ $key = 'cellphone'.($k+1); $member_address[$key] = $v; }

		// QA: #53295 첫주문 시, 주문자 란에 입력기능이 없어지는 문제
		// !$member_address['zipcode'] -> !$data[0]['email'] 변경
		if(!$member_address['user_name']  || !$member_address['cellphone'] || !$data[0]['email']){
			$member_address['err_ship_addr'] = '1';
		}
		$data[0]['default_address'] = $member_address;


		//예치금, 마일리지 차감될 금액(Cron 돌기 전)
		$limit_date		= date('Y-m-d',strtotime("-1 day"));
		$limit_date_exp	= explode('-', $limit_date);
		$limit_time		= mktime(0,0,0,$limit_date_exp[1],$limit_date_exp[2],$limit_date_exp[0]);

		$done_date		= $data[0]['emoney_limitDate'];
		$done_date_exp	= explode('-', $done_date);
		$done_time		= mktime(0,0,0,$done_date_exp[1],$done_date_exp[2],$done_date_exp[0]);

		if($limit_time > $done_time){
			$deduction_sql		= "SELECT sum(remain) AS be_deduction_emoney FROM fm_emoney WHERE member_seq = '{$seq}' AND limit_date = '{$limit_date}' AND gb = 'plus'";
			$deduction_query	= $this->db->query($deduction_sql);
			$deduction_info		= $deduction_query->result_array();
			if($deduction_info[0]['be_deduction_emoney'] > 0)	$data[0]['emoney']	-= $deduction_info[0]['be_deduction_emoney'];
		}

		return (isset($data[0]))?$data[0]:'';
	}


	/* ADMIN > MEMBER */
	public function get_member_data_id($userid,$status=''){

		$key = get_shop_key();
		$sql = "SELECT
					A.*, B.*,
					A.member_seq as member_seq,
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone
				FROM
					fm_member A LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq
				WHERE
					A.userid = ?";
		$bind[] = $userid;
		if(trim($status)){
			$sql .= " AND A.status=?";
			$bind[] = $status;
		}
		$query = $this->db->query($sql,$bind);
		foreach ($query->result_array() as $row){
			$data[] = $row;
		}
		return (isset($data[0]))?$data[0]:'';
	}

	public function get_member_data_only($userid){
		$key = get_shop_key();
		$sql = "SELECT
					A.member_seq as member_seq,C.group_name,
					A.user_name as user_name, A.nickname as nickname,
					A.birthday as birthday, A.anniversary as anniversary,
					A.emoney as emoney, A.point as point, A.cash as cash,
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone
				FROM
					fm_member A
						LEFT JOIN fm_member_group C ON A.group_seq = C.group_seq
				WHERE
					A.userid = '{$userid}' limit 0, 1";
		$query = $this->db->query($sql);
		$data = $query->result_array();
		return (isset($data[0]))?$data[0]:'';
	}

	//userid -> member_seq 속도향상
	public function get_member_seq_only($seq){
		$key = get_shop_key();
		$sql = "SELECT
					A.member_seq as member_seq,C.group_name,
					A.user_name as user_name, A.nickname as nickname,
					A.rute,
					A.userid,
					A.status,
					A.birthday as birthday, A.anniversary as anniversary,
					A.emoney as emoney, A.point as point, A.cash as cash,
					A.sns_n,
					CASE WHEN length(A.sns_n) >= '10'
						THEN concat(left(A.sns_n, 10 - 1),'*n')
						ELSE concat(left(A.sns_n, length(A.sns_n) - 1),'*n')
					END AS conv_sns_n,
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone
				FROM
					fm_member A
						LEFT JOIN fm_member_group C ON A.group_seq = C.group_seq
				WHERE
					A.member_seq = '{$seq}' limit 0, 1";
		$query = $this->db->query($sql);
		$data = $query->result_array();
		return (isset($data[0]))?$data[0]:'';
	}

	## sms userid 노출 : 2014-06-23
	public function get_member_userid($seq){
		$sql		= "select userid from fm_member where member_seq='".$seq."'";
		$query		= $this->db->query($sql);
		$member_info= $query->result_array();
		return $member_info[0]['userid'];
	}

	# 특정고객 이번달 소멸 마일리지
	public function get_member_extinction_emoney($param){

		$startdt		= $param['startdt'];
		$enddt			= $param['enddt'];
		$member_seq		= $param['member_seq'];

		$where		= array();
		$where[]	= "limit_date BETWEEN '".$startdt."' and '".$enddt."' ";
		$where[]	= "member_seq='".$param['member_seq']."'";

		$sql = "select
					 ifnull(sum(remain),0) as emoney
				from
					fm_emoney
				where 1
					and  ".implode(" and ",$where)."
					and remain > 0
				";
		$query		= $this->db->query($sql);
		$res		= $query->row_array();

		return $res;

	}

	## 고객리마인드서비스 : 이번주 만료 할인쿠폰, 메일/SMS 수신동의 회원 2014-07-22
	## 1일 1회 발송(발송로그 추적)
	public function get_member_receive_coupon($param){

		$startdt	= $param['startdt'];
		$enddt		= $param['enddt'];
		$key		= get_shop_key();
		$sql		= "
					select
						 m.member_seq,
						 m.userid, m.user_name,
						 m.mailing,m.sms,
						 AES_DECRYPT(UNHEX(m.email), '".$key."') as email,
						 AES_DECRYPT(UNHEX(m.cellphone), '".$key."') as cellphone,
						 ( select count(*) from fm_download where use_status='unused' and issue_enddate BETWEEN '".$startdt."' and '".$enddt."' and member_seq = m.member_seq ) as coupon_count
					from
						fm_member m
						inner join fm_download d on m.member_seq = d.member_seq and d.use_status='unused' and d.issue_enddate BETWEEN '".$startdt."' and '".$enddt."'
					where m.user_name != ''
						and (m.sms='y' and ifnull(m.sms,'') != '' and m.cellphone !='')
						and (select count(*) from fm_log_curation_sms where member_seq = m.member_seq and kind='coupon' and sendres='y' and reserve_date between date_format(now(),'%Y-%m-%d 00:00:00') and date_format(now(),'%Y-%m-%d 23:59:59'))=0
					group by m.member_seq
					";
		$query		= $this->db->query($sql);
		$member_list= $query->result_array();
		return $member_list;

	}

	## 고객리마인드서비스 : 다음달 소멸 마일리지, 메일/SMS 수신동의 회원 2014-07-23
	public function get_member_receive_emoney($param){

		$startdt	= $param['startdt'];
		$enddt		= $param['enddt'];
		$key		= get_shop_key();

		$sql = "
				select
					 m.member_seq,
					 m.userid, m.user_name,
					 m.mailing,m.sms,
					 AES_DECRYPT(UNHEX(m.email), '".$key."') as email,
					 AES_DECRYPT(UNHEX(m.cellphone), '".$key."') as cellphone,
					 sum(e.remain) as mileage_rest,
					 date_format(e.limit_date,'%Y년 %m월') as limit_date
				from
					fm_member as m
					left join fm_emoney as e on m.member_seq = e.member_seq
				where 1
					and e.limit_date BETWEEN '".$startdt."' and '".$enddt."'
					and m.user_name != ''
					and (m.sms='y' and ifnull(m.sms,'') != '' and m.cellphone !='')
					and (select count(*) from fm_log_curation_sms where member_seq=m.member_seq and kind='emoney' and sendres='y' and reserve_date between date_format(now(),'%Y-%m-%d 00:00:00') and date_format(now(),'%Y-%m-%d 23:59:59'))=0
					and e.remain > 0
				group by
					m.member_seq,m.user_name
				order by
					m.member_seq asc
				";
		$query		= $this->db->query($sql);
		$member_list= $query->result_array();

		return $member_list;

	}

	## 고객리마인드서비스 : 멤버쉽 서비스 메일/SMS 수신동의 회원 2014-07-23
	public function get_member_receive_membership($after_day){

		$key = get_shop_key();
		$sql = "
				select
					 m.member_seq,
					 m.userid, m.user_name,
					 m.mailing,m.sms,
					 AES_DECRYPT(UNHEX(m.email), '".$key."') as email,
					 AES_DECRYPT(UNHEX(m.cellphone), '".$key."') as cellphone,
					 m.group_seq,
					 g.group_name,
					 g.myicon
				from
					fm_member as m
					left join fm_member_group as g on g.group_seq=m.group_seq
				where 1
					and m.user_name != ''
					and g.use_type in('AUTO','AUTOPART')
					and (select count(*) from fm_log_curation_sms where member_seq=m.member_seq and kind='membership' and sendres='y' and reserve_date between date_format(now(),'%Y-%m-%d 00:00:00') and date_format(now(),'%Y-%m-%d 23:59:59'))=0
					and datediff(now(),m.grade_update_date)='".$after_day."'
					and (m.sms='y' and ifnull(m.sms,'') != '' and m.cellphone !='')
				group by
					m.member_seq,m.user_name
				order by
					m.member_seq asc
				";
		$query		= $this->db->query($sql);
		$member_list= $query->result_array();

		return $member_list;

	}

	## 고객리마인드서비스 : 장바구니/위시리스트에 담긴 상품 중 가장 마지막 날짜 기준 +O일 2014-07-24
	public function get_member_receive_cart($after_day){

		$key = get_shop_key();
		$sql = "
				select
					 m.member_seq,
					 m.userid, m.user_name,
					 m.mailing,m.sms,
					 AES_DECRYPT(UNHEX(m.email), '".$key."') as email,
					 AES_DECRYPT(UNHEX(m.cellphone), '".$key."') as cellphone,
					 count(*) as cart_cnt,
					 max(c.regist_date) as regdt
				from
					fm_member as m , fm_cart as c
				where
					c.member_seq=m.member_seq
					and m.user_name != ''
					and (m.sms='y' and ifnull(m.sms,'') != '' and AES_DECRYPT(UNHEX(m.cellphone), '".$key."') !='')
					and c.member_seq > 0
					and c.distribution='cart'
					and (select count(*) from fm_log_curation_sms where member_seq=m.member_seq and kind='cart' and sendres='y' and reserve_date between date_format(now(),'%Y-%m-%d 00:00:00') and date_format(now(),'%Y-%m-%d 23:59:59'))=0
				group by
					m.member_seq,m.user_name
				having
					datediff(now(),max(c.regist_date))='".$after_day."'
				order by
					m.member_seq asc
				";
		$query		= $this->db->query($sql);
		$member['cart'] = $query->result_array();
		$loop = array();
		foreach($member['cart'] as $item){
			$loop[$item['member_seq']] = $item;
			$loop[$item['member_seq']]['wish_cnt'] = 0;
		}

		$sql = "
				select
					 m.member_seq,
					 m.userid, m.user_name,
					 m.mailing,m.sms,
					 AES_DECRYPT(UNHEX(m.email), '".$key."') as email,
					 AES_DECRYPT(UNHEX(m.cellphone), '".$key."') as cellphone,
					 count(*) as wish_cnt,
					 max(w.regist_date) as regdt
				from
					fm_member as m , fm_goods_wish as w
				where
					w.member_seq=m.member_seq
					and m.user_name != ''
					and (m.sms='y' and ifnull(m.sms,'') != '' and AES_DECRYPT(UNHEX(m.cellphone), '".$key."') !='')
					and (select count(*) from fm_log_curation_sms where member_seq=m.member_seq and kind='cart' and sendres='y' and reserve_date between date_format(now(),'%Y-%m-%d 00:00:00') and date_format(now(),'%Y-%m-%d 23:59:59'))=0
				group by
					m.member_seq,m.user_name
				having
					datediff(now(),max(w.regist_date))='".$after_day."'
				order by
					m.member_seq asc
				";
		$query		= $this->db->query($sql);
		$member['wish'] = $query->result_array();
		foreach($member['wish'] as $item){
			if(array_key_exists($item['member_seq'],$loop)){
				$loop[$item['member_seq']]['wish_cnt'] = $item['wish_cnt'];
			}else{
				$loop[$item['member_seq']] = $item;
			}
		}
		return $loop;

	}

	## 고객리마인드서비스 : 장바구니/위시리스트 타임세일 메일/SMS 수신동의 회원 2014-07-24
	public function get_member_receive_timesale($cartdt){

		$key	= get_shop_key();
		$sql	= "
				select
					 m.member_seq,
					 m.userid, m.user_name,
					 m.mailing,m.sms,
					 AES_DECRYPT(UNHEX(m.email), '".$key."') as email,
					 AES_DECRYPT(UNHEX(m.cellphone), '".$key."') as cellphone,
					 count(*) as cart_cnt
				from
					fm_cart as c, fm_event as e, fm_member as m
				where
					c.goods_seq=e.goods_seq
					and c.member_seq=m.member_seq
					and e.event_type='solo'
					and c.distribution='cart'
					and (case when  (e.app_week = '' or e.app_week = '0' or  e.app_week is null) and date_format(e.end_date,'%Y%m%d') = date_format('".$cartdt['lastday']." 00:00:00','%Y%m%d') then
							1
						else
							(case when e.start_date <= '".$cartdt['lastday']." 23:59:59' and e.end_date >= '".$cartdt['lastday']." 00:00:00' and app_week like '%".$cartdt['appweek']."%' then 1 else 0 end)
						end ) = 1
					and m.user_name != ''
					and (m.sms='y' and ifnull(m.sms,'') != '' and AES_DECRYPT(UNHEX(m.cellphone), '".$key."') !='')
					and c.member_seq > 0
					and (select count(*) from fm_log_curation_sms where member_seq=m.member_seq and kind='timesale' and sendres='y' and reserve_date between date_format(now(),'%Y-%m-%d 00:00:00') and date_format(now(),'%Y-%m-%d 23:59:59'))=0
				group by
					m.member_seq,m.user_name
				order by
					m.member_seq asc
				";
		$query		= $this->db->query($sql);
		$member['cart'] = $query->result_array();
		$loop = array();
		foreach($member['cart'] as $item){
			$loop[$item['member_seq']] = $item;
			$loop[$item['member_seq']]['wish_cnt'] = 0;
		}

		$sql = "
				select
					 m.member_seq,
					 m.userid, m.user_name,
					 m.mailing,m.sms,
					 AES_DECRYPT(UNHEX(m.email), '".$key."') as email,
					 AES_DECRYPT(UNHEX(m.cellphone), '".$key."') as cellphone,
					 count(*) as wish_cnt
				from
					fm_goods_wish as w, fm_event as e, fm_member as m
				where
					w.goods_seq=e.goods_seq
					and w.member_seq=m.member_seq
					and e.event_type='solo'
					and (case when  (e.app_week = '' or e.app_week = '0' or  e.app_week is null) and date_format(e.end_date,'%Y%m%d') = date_format('".$cartdt['lastday']." 00:00:00','%Y%m%d') then
							1
						else
							(case when e.start_date <= '".$cartdt['lastday']." 23:59:59' and e.end_date >= '".$cartdt['lastday']." 00:00:00' and app_week like '%".$cartdt['appweek']."%' then 1 else 0 end)
						end ) = 1
					and m.user_name != ''
					and (m.sms='y' and ifnull(m.sms,'') != '' and AES_DECRYPT(UNHEX(m.cellphone), '".$key."') !='')
					and m.user_name != ''
					and (select count(*) from fm_log_curation_sms where member_seq=m.member_seq and kind='timesale' and sendres='y' and reserve_date between date_format(now(),'%Y-%m-%d 00:00:00') and date_format(now(),'%Y-%m-%d 23:59:59'))=0
				group by
					m.member_seq,m.user_name
				order by
					m.member_seq asc
				";
		$query		= $this->db->query($sql);
		$member['wish'] = $query->result_array();
		foreach($member['wish'] as $item){
			if(array_key_exists($item['member_seq'],$loop)){
				$loop[$item['member_seq']]['wish_cnt'] = $item['wish_cnt'];
			}else{
				$loop[$item['member_seq']] = $item;
			}
		}
		return $loop;

	}

	## 고객리마인드서비스 : 상품리뷰 대상자 2014-07-28
	public function get_member_receive_review($after_day){

		$key = get_shop_key();
		$sql = "
				select
					 m.member_seq,
					 m.userid, m.user_name,
					 m.mailing,m.sms,
					 AES_DECRYPT(UNHEX(m.email), '".$key."') as email,
					 AES_DECRYPT(UNHEX(m.cellphone), '".$key."') as cellphone
				from
					fm_member as m
					left join fm_order as o on o.member_seq=m.member_seq
					left join fm_order_item as oi on oi.order_seq=o.order_seq
					left join fm_goods_export as ge on ge.order_seq=o.order_seq
					left join fm_goods_export_item as gei on gei.export_code=ge.export_seq and gei.item_seq=oi.item_seq
				where 1
					and m.user_name != ''
					and (m.sms='y' and ifnull(m.sms,'') != '' and AES_DECRYPT(UNHEX(m.cellphone), '".$key."') !='')
					and ge.status='75'
					and datediff(now(),ge.shipping_date)=".$after_day."
					and (select count(*) from fm_goods_review where order_seq=o.order_seq and goods_seq=oi.goods_seq and mid=m.userid)=0
					and (select count(*) from fm_log_curation_sms where member_seq=m.member_seq and kind='review' and sendres='y' and reserve_date between date_format(now(),'%Y-%m-%d 00:00:00') and date_format(now(),'%Y-%m-%d 23:59:59'))=0
				group by
					m.member_seq,m.user_name
				order by
					m.member_seq asc
				";
		$query		= $this->db->query($sql);
		$member_list= $query->result_array();

		return $member_list;

	}

	## 고객리마인드서비스 : 유입통계
	public function curation_stat($sc){

		//$where = array();
		if( !empty($sc['start_date']) && !empty($sc['end_date']) ){
			$where = "send_date>='{$sc['start_date']}' and send_date<='{$sc['end_date']}' ";
		}

		if($where) $wheresub = " where ".$where;
		$sql = "select
						inflow_kind
						,ifnull(sum(inflow_sms_total),0) as inflow_sms_total
						,ifnull(sum(inflow_email_total),0) as inflow_email_total
						,ifnull(sum(send_sms_total),0) as send_sms_total
						,ifnull(sum(send_email_total),0) as send_email_total
					from
						fm_log_curation_summary
					".$wheresub."
					group by inflow_kind
				";
		$query	= $this->db->query($sql);
		$data = array();
		if($where) $wheresub = " and ".$where;
		foreach($query->result_array() as $item){
			$sql2	= "select
						ifnull(sum(login_cnt),0) as login_cnt
						,ifnull(sum(goodsview_cnt),0) as goodsview_cnt
						,ifnull(sum(cart_cnt),0) as cart_cnt
						,ifnull(sum(wish_cnt),0) as wish_cnt
						,ifnull(sum(order_cnt),0) as order_cnt
					from
						fm_log_curation_info_summary
					where
						curation_kind='".$item['inflow_kind']."' ".$wheresub."
				";
			$query2	= $this->db->query($sql2);
			$item2	= $query2->result_array();
			$data[] = array_merge($item,$item2[0]);
		}

		return $data;
	}


	## 고객리마인드서비스 : 유입통계상세
	public function curation_stat_detail($sc){

		$params = array();
		$params[] = "c.inflow_type";
		$params[] = "c.inflow_kind";
		$params[] = "c.curation_seq";
		$params[] = "c.member_seq";
		$params[] = "c.userid";
		$params[] = "c.access_type";
		$params[] = "c.regist_date as inflow_date";
		$params[] = "c.to_reception";
		$params[] = "c.to_msg";
		$params[] = "c.send_date";
		$params[] = "ifnull(cis.login_cnt,0) as login_cnt";
		$params[] = "ifnull(cis.goodsview_cnt,0) as goodsview_cnt";
		$params[] = "ifnull(cis.cart_cnt,0) as cart_cnt";
		$params[] = "ifnull(cis.wish_cnt,0) as wish_cnt";
		$params[] = "ifnull(cis.order_cnt,0) as order_cnt";

		$dbtables[] = "fm_log_curation as c";
		$dbtables[] = " left join fm_log_curation_info_summary as cis on c.curation_seq=cis.curation_seq";

		$where = array();
		if(!empty($sc['sc_kind'])){
			$where[] = "c.inflow_kind='".$sc['sc_kind']."'";
		}
		if(!empty($sc['sc_type'])){
			$where[] = "c.inflow_type='".$sc['sc_type']."'";
		}
		if(!empty($sc['sc_keyword'])){
			$where[] = "(c.to_msg like '%".$sc['sc_keyword']."%' or c.to_reception like '%".$sc['sc_keyword']."%' or c.userid like '%".$sc['sc_keyword']."%')";
		}
		if( !empty($sc['start_date2']) && !empty($sc['end_date2']) ){
			$where[] = "cis.send_date between '{$sc['start_date2']}' and '{$sc['end_date2']}' ";
		}

		$sqlFieldClause = implode("\n,",$params);
		$sqlFromClause	= implode("\n ",$dbtables);
		if(count($where)>0){
			$sqlWhereClause = " where ".implode("\n and ",$where);
		}
		$sql			= "select ".$sqlFieldClause." from ".$sqlFromClause." ".$sqlWhereClause;

		if	($sc['nolimit'] != 'y')
			$limit =" limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();

		$cnt_query = 'select count(*) as cnt from '. $sqlFromClause . ' '. $sqlWhereClause;
		$cntquery = $this->db->query($cnt_query);
		$cntrow = $cntquery->result_array();
		$data['count'] = $cntrow[0]['cnt'];

		return $data;

	}

	## 고객리마인드서비스 : email 발송내역
	public function curtion_history_email($sc){

		$sql = "select * from fm_log_curation_email";
		$sqltotal = "select  count(*) as cnt from fm_log_curation_email ";

		$where = array();
		$where[] = " sendres='y'";

		$query2 = $this->db->query($sqltotal." where ".implode(" and ",$where) );
		$cntrow = $query2->result_array();
		$data['totalcount'] = $cntrow[0]['cnt'];

		if( !empty($sc['start_date']) && !empty($sc['end_date']) ){
			$where[] = " regist_date between '{$sc['start_date']} 00:00:00' and '{$sc['end_date']} 23:59:59' ";
		}

		if( !empty($sc['sc_kind']) ){
			$where[] = " kind='".$sc['sc_kind']."' ";
		}
		if( !empty($sc['sc_subject']) ){
			$where[] = " subject like '%{$sc['sc_subject']}%' ";
		}

		if($where) { $sql .= " where ".implode(" and ",$where); }
		$sql .= ($sc['orderby'])? " order by {$sc['orderby']} {$sc['sort']}":"";
		$limit =" limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();

		if($where) { $sqltotal .= " where ".implode(" and ",$where); }
		$query2 = $this->db->query($sqltotal);
		$cntrow = $query2->result_array();
		$data['count'] = $cntrow[0]['cnt'];

		return $data;
	}

	## 고객리마인드서비스 sms 발송내역
	public function curtion_history_sms($sc){

		$sql = "select * from fm_log_curation_sms";
		$sqltotal = "select  count(*) as cnt from fm_log_curation_sms ";

		$where = array();
		$where[] = " sendres='y'";

		$query2 = $this->db->query($sqltotal." where ".implode(" and ",$where) );
		$cntrow = $query2->result_array();
		$data['totalcount'] = $cntrow[0]['cnt'];

		if( !empty($sc['start_date']) && !empty($sc['end_date']) ){
			$where[] = " regist_date between '{$sc['start_date']} 00:00:00' and '{$sc['end_date']} 23:59:59' ";
		}

		if( !empty($sc['sc_kind']) ){
			$where[] = " kind='".$sc['sc_kind']."' ";
		}
		if( !empty($sc['sc_subject']) ){
			$where[] = " sms_msg like '%{$sc['sc_subject']}%' ";
		}

		if($where) { $sql .= " where ".implode(" and ",$where); }
		$sql .= ($sc['orderby'])? " order by {$sc['orderby']} {$sc['sort']}":"";
		$limit =" limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();

		if($where) { $sqltotal .= " where ".implode(" and ",$where); }
		$query2 = $this->db->query($sqltotal);
		$cntrow = $query2->result_array();
		$data['count'] = $cntrow[0]['cnt'];

		return $data;
	}

	//게시판에서 최소의 회원정보용
	public function get_member_data_only_seq($seq,$newmbinfo=null){
		$key = get_shop_key();
		$sql = "SELECT
					A.member_seq,A.userid,A.rute,C.group_name,C.icon,A.user_icon,{$newmbinfo}
					A.user_name as user_name, A.nickname as nickname,
					A.birthday as birthday, A.anniversary as anniversary,
					A.emoney as emoney, A.point as point, A.cash as cash,
					B.bname as bname,
					B.business_seq as mbinfo_business_seq,
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone
				FROM
					fm_member A
						LEFT JOIN fm_member_group C ON A.group_seq = C.group_seq
						LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq
				WHERE
					A.member_seq = '{$seq}' limit 0, 1";
		$query = $this->db->query($sql);
		$data = $query->result_array();
		return (isset($data[0]))?$data[0]:'';
	}

	/* ADMIN > MEMBER */
	public function admin_member_list($sc) {

		$key = get_shop_key();

		if($sc['sms_member'] == "y"){
			$sqlSelectClause = "select AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone, A.user_name, AES_DECRYPT(UNHEX(A.email), '{$key}') as email, A.member_seq, B.bceo, B.business_seq, B.bcellphone, A.blacklist";
		}elseif($sc['status_member'] == "y"){
			$sqlSelectClause = "select A.user_name, AES_DECRYPT(UNHEX(A.email), '{$key}') as email, A.member_seq, B.bceo, B.business_seq,A.status";
		}elseif($sc['grade_member'] == "y"){
			$sqlSelectClause = "select A.user_name, AES_DECRYPT(UNHEX(A.email), '{$key}') as email, A.member_seq, B.bceo, B.business_seq, D.group_name";
		}else{

			$sqlSelectClause = "
				select
					A.member_seq,A.userid,A.user_name,A.nickname,A.mailing,A.sms,A.emoney,A.point,A.cash,A.regist_date,A.lastlogin_date,A.review_cnt,A.login_cnt,A.birthday,A.zipcode,A.address_street,A.address_type,A.address,A.address_detail,A.sns_f,A.anniversary,A.recommend,A.sex,A.mtype,
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone,
					CASE WHEN A.status = 'done' THEN '승인'
						 WHEN A.status = 'hold' THEN '미승인'
						 WHEN A.status = 'withdrawal' THEN '탈퇴'
						 WHEN A.status = 'dormancy' THEN '휴면'
					ELSE '' END AS status_nm, A.mall_t_check,
					B.bname, B.bphone, B.bcellphone, B.business_seq, B.baddress_type, B.baddress, B.baddress_detail,
					B.bzipcode, B.bceo, B.bno, B.bitem,
					B.bstatus, B.bperson, B.bpart,
					A.member_order_cnt,A.member_order_price,A.member_recommend_cnt ,A.member_invite_cnt,
					A.referer, A.referer_domain,
					IF(C.referer_group_no>0, C.referer_group_name, IF(LENGTH(A.referer)>0,'기타','직접입력')) as referer_name,
					A.group_seq,D.group_name,
					A.rute,
					A.platform,
					A.sns_change,
					A.blacklist,
					CASE WHEN length(A.sns_n) >= '10'
						THEN concat(left(A.sns_n, 10 - 1),'*n')
						ELSE concat(left(A.sns_n, length(A.sns_n) - 1),'*n')
					END AS conv_sns_n
			";
		}

		$sqlFromClause = "
			from
				fm_member A
				LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq
				LEFT JOIN fm_referer_group C ON A.referer_domain = C.referer_group_url
				LEFT JOIN fm_member_group D ON A.group_seq = D.group_seq
		";
		$sqlWhereClause = "
			where A.status in ('done','hold','dormancy')
		";

		###
		if( !empty($sc['provider_seq']) ){
			$sqlFromClause .= "INNER JOIN fm_member_minishop mshop
								on ( A.member_seq = mshop.member_seq
									and mshop.provider_seq = '".$sc['provider_seq']."' )";
		}

		###
		$sc['keyword'] = addslashes($sc['keyword']);
		if( !empty($sc['keyword']) || strlen($sc['keyword'])){
			if($sc['body_search_type']){

				switch($sc['body_search_type']){
					case "user_name":
						$sqlWhereClause .= " and (A.user_name like '%".$sc['keyword']."%' or B.bname like '%".$sc['keyword']."%')";
					break;
					case "phone":
						$sqlWhereClause .= " and (AES_DECRYPT(UNHEX(A.phone), '{$key}') like '%".$sc['keyword']."%' or B.bphone like '%".$sc['keyword']."%')";
					break;
					case "userid":
						$sqlWhereClause .= " and A.userid like '%".$sc['keyword']."%'";
					break;
					case "userid":
						$sqlWhereClause .= " and A.userid like '%".$sc['keyword']."%'";
					break;
					case "cellphone":
						$sqlWhereClause .= " and (AES_DECRYPT(UNHEX(A.cellphone), '{$key}') like '%".$sc['keyword']."%' or B.bcellphone like '%".$sc['keyword']."%')";
					break;
					case "email":
						$sqlWhereClause .= " and AES_DECRYPT(UNHEX(A.email), '{$key}') like '%".$sc['keyword']."%'";
					break;
					case "all":
						$sqlWhereClause .= " and ( A.userid like '%".$sc['keyword']."%' or A.user_name like '%".$sc['keyword']."%' or AES_DECRYPT(UNHEX(A.email), '{$key}') like '%".$sc['keyword']."%' or AES_DECRYPT(UNHEX(A.phone), '{$key}') like '%".$sc['keyword']."%' or AES_DECRYPT(UNHEX(A.cellphone), '{$key}') like '%".$sc['keyword']."%' or A.address like '%".$sc['keyword']."%' or A.address_detail like '%".$sc['keyword']."%' or A.nickname like '%".$sc['keyword']."%' or B.bname like '%".$sc['keyword']."%'  or B.baddress  like '%".$sc['keyword']."%' or B.bphone  like '%".$sc['keyword']."%' or B.bcellphone like '%".$sc['keyword']."%' or B.baddress_detail like '%".$sc['keyword']."%'  or B.bceo like '%".$sc['keyword']."%' or A.sns_n like '".substr($sc['keyword'],0,-2)."%') ";
					break;


				}

			}else{
				$sqlWhereClause .= " and ( A.userid like '%".$sc['keyword']."%' or A.user_name like '%".$sc['keyword']."%' or AES_DECRYPT(UNHEX(A.email), '{$key}') like '%".$sc['keyword']."%' or AES_DECRYPT(UNHEX(A.phone), '{$key}') like '%".$sc['keyword']."%' or AES_DECRYPT(UNHEX(A.cellphone), '{$key}') like '%".$sc['keyword']."%' or A.address like '%".$sc['keyword']."%' or A.address_detail like '%".$sc['keyword']."%' or A.nickname like '%".$sc['keyword']."%' or B.bname like '%".$sc['keyword']."%'  or B.baddress  like '%".$sc['keyword']."%' or B.bphone  like '%".$sc['keyword']."%' or B.bcellphone like '%".$sc['keyword']."%' or B.baddress_detail like '%".$sc['keyword']."%'  or B.bceo like '%".$sc['keyword']."%' or A.sns_n like '".substr($sc['keyword'],0,-2)."%' )";
			}
		}

		$sqlWhereClauseTmp = $this->admin_member_list_params($sc, $bind);
		$sqlWhereClause .= $sqlWhereClauseTmp;

		###테스트 아이디 검색조건 추가
		if($sc['mall_t_check'] == 'Y') {
			$sqlWhereClause .= " AND mall_t_check = 'Y'";
		}

		### birthday date
		$birthday_fld	= "A.birthday";
		if	($sc['birthday_year_except'] == 'Y'){
			$birthday_fld	= "RIGHT(REPLACE(A.birthday, '-', ''), 4)";
			if	(!empty($sc['birthday_sdate']))
				$sc['birthday_sdate']	= str_replace('-', '', substr($sc['birthday_sdate'], 5));
			if	(!empty($sc['birthday_edate']))
				$sc['birthday_edate']	= str_replace('-', '', substr($sc['birthday_edate'], 5));
		}
		if( !empty($sc['birthday_sdate']) && !empty($sc['birthday_edate'])){
			$sqlWhereClause .= " AND ".$birthday_fld." between '{$sc['birthday_sdate']}' and '{$sc['birthday_edate']}' ";
		}else if( !empty($sc['birthday_sdate']) && empty($sc['birthday_edate']) ){
			$sqlWhereClause .= " AND ".$birthday_fld." >= '{$sc['birthday_sdate']}'";
		}else if( empty($sc['birthday_sdate']) && !empty($sc['birthday_edate']) ){
			$sqlWhereClause .= " AND ".$birthday_fld." <= '{$sc['birthday_edate']}' ";
		}

		### anniversary date
		if(!empty($sc['anniversary_sdate'][0]) && !empty($sc['anniversary_sdate'][1]))
				$sc['anniversary_sdate'] = implode("-",$sc['anniversary_sdate']);
		else	$sc['anniversary_sdate'] = null;
		if(!empty($sc['anniversary_edate'][0]) && !empty($sc['anniversary_edate'][1]))
				$sc['anniversary_edate'] = implode("-",$sc['anniversary_edate']);
		else	$sc['anniversary_edate'] = null;
		if( !empty($sc['anniversary_sdate']))
			$sc['anniversary_sdate']	= date('md', strtotime(date('Y-') . $sc['anniversary_sdate']));
		if( !empty($sc['anniversary_edate']))
			$sc['anniversary_edate']	= date('md', strtotime(date('Y-') . $sc['anniversary_edate']));
		if( !empty($sc['anniversary_sdate']) && !empty($sc['anniversary_edate'])){
			$sqlWhereClause .= " AND REPLACE(A.anniversary, '-', '') between '{$sc['anniversary_sdate']}' and '{$sc['anniversary_edate']}' ";
		}else if( !empty($sc['anniversary_sdate']) && empty($sc['anniversary_edate']) ){
			$sqlWhereClause .= " AND LENGTH(A.anniversary) > 0 AND REPLACE(A.anniversary, '-', '') >= '{$sc['anniversary_sdate']}' ";
		}else if( empty($sc['anniversary_sdate']) && !empty($sc['anniversary_edate']) ){
			$sqlWhereClause .= " AND LENGTH(A.anniversary) > 0 AND REPLACE(A.anniversary, '-', '') <= '{$sc['anniversary_edate']}' ";
		}

		### DATE promotion > coupon 발급시 회원검색
		if( !empty($sc['date_gb']) && !empty($sc['sdate']) && !empty($sc['edate']) ){
			$sqlWhereClause .= " AND A.{$sc['date_gb']} between '{$sc['sdate']}{$add_stime}' and '{$sc['edate']}{$add_etime}' ";
		}else if( !empty($sc['sdate']) && empty($sc['edate']) ){
			$sqlWhereClause .= " AND A.{$sc['date_gb']} >= '{$sc['sdate']}{$add_stime}' ";
		}else if( empty($sc['sdate']) && !empty($sc['edate']) ){
			$sqlWhereClause .= " AND A.{$sc['date_gb']} <= '{$sc['edate']}{$add_etime}' ";
		}

		### sms
		if( !empty($sc['sms']) ){
			$sqlWhereClause .= " AND A.sms = '{$sc[sms]}' ";
		}

		### sex
		if( !empty($sc['sex']) ){
			$sqlWhereClause .= " AND A.sex = '{$sc[sex]}' ";
		}

		### mailing
		if( !empty($sc['mailing']) ){
			$sqlWhereClause .= " AND A.mailing = '{$sc[mailing]}' ";
		}
		### business_seq
		if( !empty($sc['business_seq']) ){
			$sqlWhereClause .= $sc['business_seq']=='n' ? " AND B.business_seq is null " : " AND B.business_seq != '' ";
		}
		### status
		if( !empty($sc['status']) ){
			$sqlWhereClause .= " AND A.status = '{$sc[status]}' ";
		}

		if($sc['batchProcess'] == 'y'){
			$sqlWhereClause .= " AND A.status != 'dormancy' ";
		}

		### grade
		if( !empty($sc['grade']) ){
			$sqlWhereClause .= " AND A.group_seq = '{$sc[grade]}' ";
		}

		### referer
		if( !empty($sc['referer']) ){
			$sqlWhereClause .= " AND (IF(C.referer_group_no>0, C.referer_group_name, IF(LENGTH(A.referer)>0,'기타','직접입력'))) = '" . $sc['referer'] . "' ";
		}

		### sitetype
		if( !empty($sc['sitetype']) ){
			$sqlWhereClause .= " AND A.platform in ('{$sc[sitetype]}') ";
		}

		### 가입양식start
		$ruteloop = memberrute('', '', 'array');
		foreach($ruteloop as $sns=>$snsv){
			if( $sns == 'none' && !empty($sc[$sns])) {
				$snssqlWhereClause[] = " (A.rute = 'none') ";
			}elseif( !empty($sc[$sns]) ){
				if($sns == 'sns_f'){ //기본 앱 검색조건 추가 18.02.27 kmj
					if($sc['sns_f_type']) {
						$snssqlWhereClause[] = " (A.sns_f is not null  AND A.sns_f  <> '' )";
					} else {
						$snssqlWhereClause[] = " (A.sns_f is not null  AND A.sns_f  <> '' AND A.sns_f_type = 1)";
					}
				} elseif($sns == 'sns_t'){ //기본 앱 검색조건 추가 #19795 2018-06-27 hed
					if($sc['sns_t_type']) {
						$snssqlWhereClause[] = " (A.sns_t is not null  AND A.sns_t  <> '' )";
					} else {
						$snssqlWhereClause[] = " (A.sns_t is not null  AND A.sns_t  <> '' AND A.sns_t_type = 1)";
					}
				} else {
					$snssqlWhereClause[] = " (A.".$sns." is not null  AND A.".$sns."  <> '' )";
				}

			}
		}

		//기본 앱 검색조건 추가 18.02.27 kmj
		if(!$sc['sns_f'] && $sc['sns_f_type']) {
			$snssqlWhereClause[] = " (A.sns_f is not null AND A.sns_f  <> '' AND A.sns_f_type != 1)";
		}
		//기본 앱 검색조건 추가 #19795 2018-06-27 hed
		if(!$sc['sns_t'] && $sc['sns_t_type']) {
			$snssqlWhereClause[] = " (A.sns_t is not null AND A.sns_t  <> '' AND A.sns_t_type != 1)";
		}

		// o2o 검색 설정 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_member_rute_list($sc, $snssqlWhereClause);

		if($snssqlWhereClause) $sqlWhereClause .= " AND (".implode(" OR ", $snssqlWhereClause)." ) ";
		### 가입양식end

		### order_sum
		if(strlen($sc['sorder_sum']) && strlen($sc['eorder_sum']) && $sc['sorder_sum'] === '0' && $sc['eorder_sum'] === '0'){
			$sqlWhereClause .= " AND A.member_order_price = 0 ";
		}elseif( !empty($sc['sorder_sum']) && !empty($sc['eorder_sum']) ){
			$sqlWhereClause .= " AND A.member_order_price between '{$sc['sorder_sum']}' and '{$sc['eorder_sum']}' ";
		}else if( !empty($sc['sorder_sum']) && empty($sc['eorder_sum']) ){
			$sqlWhereClause .= " AND A.member_order_price >= '{$sc['sorder_sum']}' ";
		}else if( empty($sc['sorder_sum']) && !empty($sc['eorder_sum']) ){
			$sqlWhereClause .= " AND A.member_order_price <= '{$sc['eorder_sum']}' ";
		}

		### emoney
		if(strlen($sc['semoney']) && strlen($sc['eemoney']) && $sc['semoney'] === '0' && $sc['eemoney'] === '0'){
			$sqlWhereClause .= " AND A.emoney = 0 ";
		}elseif( !empty($sc['semoney']) && !empty($sc['eemoney']) ){
			$sqlWhereClause .= " AND A.emoney between '{$sc['semoney']}' and '{$sc['eemoney']}' ";
		}else if( !empty($sc['semoney']) && empty($sc['eemoney']) ){
			$sqlWhereClause .= " AND A.emoney >= '{$sc['semoney']}' ";
		}else if( empty($sc['semoney']) && !empty($sc['eemoney']) ){
			$sqlWhereClause .= " AND A.emoney <= '{$sc['eemoney']}' ";
		}

		if(strlen($sc['spoint']) && strlen($sc['epoint']) && $sc['spoint'] === '0' && $sc['epoint'] === '0'){
			$sqlWhereClause .= " AND A.point = 0 ";
		}elseif( !empty($sc['spoint']) && !empty($sc['epoint']) ){
			$sqlWhereClause .= " AND A.point between '{$sc['spoint']}' and '{$sc['epoint']}' ";
		}else if( !empty($sc['spoint']) && empty($sc['epoint']) ){
			$sqlWhereClause .= " AND A.point >= '{$sc['spoint']}' ";
		}else if( empty($sc['spoint']) && !empty($sc['epoint']) ){
			$sqlWhereClause .= " AND A.point <= '{$sc['epoint']}' ";
		}

		if(strlen($sc['scash']) && strlen($sc['ecash']) && $sc['scash'] === '0' && $sc['ecash'] === '0'){
			$sqlWhereClause .= " AND A.cash = 0 ";
		}elseif( !empty($sc['scash']) && !empty($sc['ecash']) ){
			$sqlWhereClause .= " AND A.cash between '{$sc['scash']}' and '{$sc['ecash']}' ";
		}else if( !empty($sc['scash']) && empty($sc['ecash']) ){
			$sqlWhereClause .= " AND A.cash >= '{$sc['scash']}' ";
		}else if( empty($sc['scash']) && !empty($sc['ecash']) ){
			$sqlWhereClause .= " AND A.cash <= '{$sc['ecash']}' ";
		}

		### order_cnt
		if(strlen($sc['sorder_cnt']) && strlen($sc['eorder_cnt']) && $sc['sorder_cnt'] === '0' && $sc['eorder_cnt'] === '0'){
			$sqlWhereClause .= " AND A.member_order_cnt = 0 ";
		}elseif( !empty($sc['sorder_cnt']) && !empty($sc['eorder_cnt'])){
			$sqlWhereClause .= " AND A.member_order_cnt between '{$sc['sorder_cnt']}' and '{$sc['eorder_cnt']}' ";
		}else if( !empty($sc['sorder_cnt']) && empty($sc['eorder_cnt']) ){
			$sqlWhereClause .= " AND A.member_order_cnt >= '{$sc['sorder_cnt']}' ";
		}else if( empty($sc['sorder_cnt']) && !empty($sc['eorder_cnt']) ){
			$sqlWhereClause .= " AND A.member_order_cnt <= '{$sc['eorder_cnt']}' ";
		}

		### login_cnt / review_cnt
		$sc_count_type = $sc['sc_count_type'];
		if(strlen($sc['slogin_cnt']) && strlen($sc['elogin_cnt']) && $sc['slogin_cnt'] === '0' && $sc['elogin_cnt'] === '0'){
			$sqlWhereClause .= " AND A.{$sc_count_type} = 0 ";
		}elseif( !empty($sc['slogin_cnt']) && !empty($sc['elogin_cnt'])){
			$sqlWhereClause .= " AND A.{$sc_count_type} between '{$sc['slogin_cnt']}' and '{$sc['elogin_cnt']}' ";
		}else if( !empty($sc['slogin_cnt']) && empty($sc['elogin_cnt']) ){
			$sqlWhereClause .= " AND A.{$sc_count_type} >= '{$sc['slogin_cnt']}' ";
		}else if( empty($sc['slogin_cnt']) && !empty($sc['elogin_cnt']) ){
			$sqlWhereClause .= " AND A.{$sc_count_type} <= '{$sc['elogin_cnt']}' ";
		}

		if(!empty($sc['member_seq'])){
			$sqlWhereClause .= " AND A.member_seq in (".$sc['member_seq'].") ";
		}

		## 휴면대상자 검색
		if( $sc['dormancy'] && $sc['status'] != 'dormancy' ) $sqlWhereClause .= " AND (A.dormancy_seq is null or A.dormancy_seq = '') ";

		## 2018-07-11 jhr SMS대량발송 시 유효한 핸드폰이 있는 대상자만 가져온다
		if	( $sc['callPage'] == 'batch_sms' )
			$sqlWhereClause .= " AND A.cellphone != '' and A.cellphone is not null and A.cellphone != 'AC2A821D311CA8A6E3FE7A5CF462DD66' ";

		if( !empty($sc['goods_seq']) && !empty($sc['goods_seq_cond'])){
			switch($sc['goods_seq_cond']){
				case "fblike":
					$sqlFromClause .= "
						inner join fm_goods_fblike on A.member_seq = fm_goods_fblike.member_seq and fm_goods_fblike.goods_seq = '{$sc['goods_seq']}'
					";
					$sqlWhereClause .=" group by fm_goods_fblike.member_seq ";
				break;
				case "cart":
					$sqlFromClause .= "
						inner join fm_cart on A.member_seq = fm_cart.member_seq and fm_cart.goods_seq = '{$sc['goods_seq']}'
					";
					$sqlWhereClause .=" group by fm_cart.member_seq ";
				break;
				case "wish":
					$sqlFromClause .= "
						inner join fm_goods_wish on A.member_seq = fm_goods_wish.member_seq and fm_goods_wish.goods_seq = '{$sc['goods_seq']}'
					";
					$sqlWhereClause .=" group by fm_goods_wish.member_seq ";
				break;
			}
		}

		/**
		 * admin_member_list 는 정렬이 필요없음
		 * 정렬 사용 시 membermodel->admin_member_list_spout() 사용하도록
		 */
		$sqlOrderClause = ' order by A.member_seq DESC';

		if ($sc['nolimit'] != 'y') {
			$sc['page'] = preg_replace('/[^0-9]/i', '', $sc['page']);
			$sc['perpage'] = preg_replace('/[^0-9]/i', '', $sc['perpage']);
			$limit = " limit {$sc['page']}, {$sc['perpage']} ";
		}

		// o2o 검색 설정 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_member_list($sc, $sqlSelectClause, $sqlFromClause, $sqlWhereClause, $sqlOrderClause);

		$sql = "
			{$sqlSelectClause}
			{$sqlFromClause}
			{$sqlWhereClause}
			{$sqlOrderClause}
		";

		//echo $sql;

		$query = $this->db->query($sql.$limit, $bind);
		$data['result'] = $query->result_array();

		if($sc['pageType'] == "search"){
			if( $sc['status'] != "dormancy" ){
				$whereDormancy = " AND status = 'dormancy' ";
			}

			$dormancy_query = 'select count(*) as cnt '. $sqlFromClause . $sqlWhereClause.$whereDormancy;
			$dormancyquery = $this->db->query($dormancy_query, $bind);
			$dormancyrow = $dormancyquery->result_array();
			$data['dormancy_count'] = $dormancyrow[0]['cnt'];
		}

		$cnt_query = 'select count(*) as cnt '. $sqlFromClause . $sqlWhereClause;
		$cntquery = $this->db->query($cnt_query, $bind);
		$cntrow = $cntquery->result_array();
		$data['count'] = $cntrow[0]['cnt'];

		# 회원등급별 회원수
		$data['grade_cnt'] = $this->get_grade_member_cnt($sqlFromClause, $sqlWhereClause, $bind);

		return $data;
	}

	//대용량 다운로드 용 kmj
	public function admin_member_list_spout($sc) {

		$key = get_shop_key();
		$sqlSelectClause = "
			select
				 A.member_seq,A.userid,A.user_name,A.nickname,A.mailing,A.sms,A.emoney,A.point,A.cash,A.regist_date,A.lastlogin_date,A.review_cnt,A.login_cnt,A.birthday,A.zipcode,A.address_street,A.address_type,A.address,A.address_detail,A.sns_f,A.anniversary,A.recommend,A.sex,A.mtype,
				AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
				AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
				AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone,
				CASE WHEN A.status = 'done' THEN '승인'
					 WHEN A.status = 'hold' THEN '미승인'
					 WHEN A.status = 'withdrawal' THEN '탈퇴'
					 WHEN A.status = 'dormancy' THEN '휴면'
				ELSE '' END AS status_nm, A.mall_t_check,
				A.member_order_cnt,A.member_order_price,A.member_recommend_cnt ,A.member_invite_cnt,
				A.referer, A.referer_domain,
				A.group_seq,
				A.rute,
				A.sns_change,
				A.blacklist,
				CASE WHEN length(A.sns_n) >= '10'
					THEN concat(left(A.sns_n, 10 - 1),'*n')
					ELSE concat(left(A.sns_n, length(A.sns_n) - 1),'*n')
				END AS conv_sns_n
				, A.platform
		";

		$sqlFromClause = "FROM fm_member A";
		$sqlWhereClause = '';

		### 가입승인
		if( !empty($sc['status']) ){
			$sqlWhereClause = " WHERE A.status = ?";
			$bind[] = $sc['status'];

			if( $sc['dormancy'] && $sc['status'] != 'dormancy' ){
				$sqlWhereClause .= " AND (A.dormancy_seq is null or A.dormancy_seq = '') ";
			}
		} else {
			if($sc['batchProcess'] == 'y'){
				$sqlWhereClause .= " WHERE A.status in ('done', 'hold') ";
			} else {
				$sqlWhereClause = " WHERE A.status in ('done','hold','dormancy')";
			}
		}
		
		//18.02.28 kmj 선택 다운로드 일때만 적용 되도록 수정
		if($sc['excel_type'] == "select" && $sc['excel_spout_query']){
			$sqlWhereClause .= " AND A.member_seq IN (?)";
			$bind[] = $sc['member_chk'];
		}

		### 검색 키워드
		if( !empty($sc['keyword']) && strlen($sc['keyword']) > 0 ){
			// keyword 공백제거 후 검색하도록 수정
			$sc['keyword'] 	= str_replace(' ','',$sc['keyword']);
			$keyword_like = '%' . $sc['keyword'] . '%';
			$search_type_arr = array(
				'user_name',
				'userid',
				'email',
				'phone',
				'cellphone',
				'address',
				'nickname',
			);

			// 21.05.11 lsh 검색어 타입 설정
			if($sc['search_type']) {
				$search_type = 'A.user_name';
				if (in_array($sc['search_type'], $search_type_arr)) {
					$search_type = 'A.'.$sc['search_type'];
				}

				if(in_array($sc['search_type'], array('email','phone','cellphone'))){
					$search_type = "AES_DECRYPT(UNHEX(A.{$sc['search_type']}), '{$key}')";
				}

				$sqlWhereClause .= " AND {$search_type} LIKE ?";
				$bind[] = $keyword_like;
			} else {
				$sqlWhereClause .= "
				AND (
					A.userid LIKE ?
					OR A.user_name LIKE ?
					OR AES_DECRYPT(UNHEX(A.email), '{$key}') LIKE ?
					OR AES_DECRYPT(UNHEX(A.phone), '{$key}') LIKE ?
					OR AES_DECRYPT(UNHEX(A.cellphone), '{$key}') LIKE ?
					OR A.address LIKE ?
					OR A.address_detail LIKE ?
					OR A.nickname LIKE ?
					OR A.sns_n LIKE ?
				)";
				$search_type_arr[] = 'address_detail';
				$search_type_arr[] = 'sns_n';
				for ($i = 0; $i < count($search_type_arr); $i++) {
					$bind[] = $keyword_like;
				}
			}

		}

		$sqlWhereClauseTmp = $this->admin_member_list_params($sc, $bind);
		$sqlWhereClause .= $sqlWhereClauseTmp;

		### 생일
		$specialDayWhereClause = "";
		$birthday_fld	= "A.birthday";
		if	($sc['birthday_year_except'] == 'Y'){
			$birthday_fld	= "RIGHT(REPLACE(A.birthday, '-', ''), 4)";
			if	(!empty($sc['birthday_sdate']))
				$sc['birthday_sdate']	= str_replace('-', '', substr($sc['birthday_sdate'], 5));
			if	(!empty($sc['birthday_edate']))
				$sc['birthday_edate']	= str_replace('-', '', substr($sc['birthday_edate'], 5));
		}

		if( !empty($sc['birthday_sdate']) && !empty($sc['birthday_edate'])){
			$specialDayWhereClause = " AND ".$birthday_fld." between ? and ? ";
			$bind[] = $sc['birthday_sdate'];
			$bind[] = $sc['birthday_edate'];
		}else if( !empty($sc['birthday_sdate']) && empty($sc['birthday_edate']) ){
			$specialDayWhereClause = " AND ".$birthday_fld." >= ? ";
			$bind[] = $sc['birthday_sdate'];
		}else if( empty($sc['birthday_sdate']) && !empty($sc['birthday_edate']) ){
			$specialDayWhereClause = " AND ".$birthday_fld." <= ? ";
			$bind[] = $sc['birthday_edate'];
		}
		if($sc['sc_specialDay_type'] == 'birth') $sqlWhereClause.= $specialDayWhereClause;

		### 기념일
		$specialDayWhereClause = "";
		$monthWhereClause = " REPLACE( A.anniversary, '-', '') ";
		if(!empty($sc['anniversary_sdate'][0]) && !empty($sc['anniversary_sdate'][1]))
				$sc['anniversary_sdate'] = implode("-",$sc['anniversary_sdate']);
		else	$sc['anniversary_sdate'] = null;
		if(!empty($sc['anniversary_edate'][0]) && !empty($sc['anniversary_edate'][1]))
				$sc['anniversary_edate'] = implode("-",$sc['anniversary_edate']);
		else	$sc['anniversary_edate'] = null;
		if( !empty($sc['anniversary_sdate']))
			$sc['anniversary_sdate']	= date('md', strtotime(date('Y-') . $sc['anniversary_sdate']));
		if( !empty($sc['anniversary_edate']))
			$sc['anniversary_edate']	= date('md', strtotime(date('Y-') . $sc['anniversary_edate']));
		if( !empty($sc['anniversary_sdate']) && !empty($sc['anniversary_edate'])){
			$sc['anniversary_month_sdate'] = substr($sc['anniversary_sdate'], 0, 2);
			$sc['anniversary_month_edate'] = substr($sc['anniversary_edate'], 0, 2);
			$bind[] = $sc['anniversary_sdate'];
			$bind[] = $sc['anniversary_edate'];
			// 년도 증가시 기념일 월 비교
			if ($sc['anniversary_month_sdate'] > $sc['anniversary_month_edate']) {
				$specialDayWhereClause .= " AND ( " . $monthWhereClause . " >= ? " . " OR " . $monthWhereClause . "<= ? )";
			} else {
				$specialDayWhereClause .= " AND ". $monthWhereClause ." between ? and ? ";
			}
		}else if( !empty($sc['anniversary_sdate']) && empty($sc['anniversary_edate']) ){
			$specialDayWhereClause .= " AND ". $monthWhereClause ." >= ? ";
			$bind[] = $sc['anniversary_sdate'];
		}else if( empty($sc['anniversary_sdate']) && !empty($sc['anniversary_edate']) ){
			$specialDayWhereClause .= " AND ". $monthWhereClause ." <= ? ";
			$bind[] = $sc['anniversary_edate'];
		}
		if($sc['sc_specialDay_type'] == 'anniversary') $sqlWhereClause.= $specialDayWhereClause;


		###테스트 아이디 검색조건 추가
		if($sc['mall_t_check'] == 'Y') {
		    $sqlWhereClause .= " AND mall_t_check = 'Y'";
		}

		### sms
		if( !empty($sc['sms']) ){
			$sqlWhereClause .= " AND A.sms = ? ";
			$bind[] = $sc['sms'];
		}

		### 이메일
		if( !empty($sc['mailing']) ){
			$sqlWhereClause .= " AND A.mailing = ? ";
			$bind[] = $sc['mailing'];
		}

		### 가입유형
		if( !empty($sc['business_seq']) ){
			if( $sc['business_seq'] == 'n' ){ //개인회원
				$sqlWhereClause .= " AND A.mtype != 'business'";
			} else { //기업회원
				$sqlWhereClause .= " AND A.mtype = 'business'";
			}
		}

		### 등급
		if( !empty($sc['grade']) ){
			$sqlWhereClause .= " AND A.group_seq = ? ";
			$bind[] = $sc['grade'];
		}

		### 주문횟수
		if(strlen($sc['sorder_cnt']) && strlen($sc['eorder_cnt']) && $sc['sorder_cnt'] === '0' && $sc['eorder_cnt'] === '0'){
			$sqlWhereClause .= " AND A.member_order_cnt = 0 ";
		}elseif( !empty($sc['sorder_cnt']) && !empty($sc['eorder_cnt'])){
			$sqlWhereClause .= " AND A.member_order_cnt between ? and ? ";
			$bind[] = $sc['sorder_cnt'];
			$bind[] = $sc['eorder_cnt'];
		}else if( !empty($sc['sorder_cnt']) && empty($sc['eorder_cnt']) ){
			$sqlWhereClause .= " AND A.member_order_cnt >= ? ";
			$bind[] = $sc['sorder_cnt'];
		}else if( empty($sc['sorder_cnt']) && !empty($sc['eorder_cnt']) ){
			$sqlWhereClause .= " AND A.member_order_cnt <= ? ";
			$bind[] = $sc['eorder_cnt'];
		}

		### 실결제금액
		if(strlen($sc['sorder_sum']) && strlen($sc['eorder_sum']) && $sc['sorder_sum'] === '0' && $sc['eorder_sum'] === '0'){
			$sqlWhereClause .= " AND A.member_order_price = 0 ";
		}elseif( !empty($sc['sorder_sum']) && !empty($sc['eorder_sum']) ){
			$sqlWhereClause .= " AND A.member_order_price between ? and ? ";
			$bind[] = $sc['sorder_sum'];
			$bind[] = $sc['eorder_sum'];
		}else if( !empty($sc['sorder_sum']) && empty($sc['eorder_sum']) ){
			$sqlWhereClause .= " AND A.member_order_price >= ? ";
			$bind[] = $sc['sorder_sum'];
		}else if( empty($sc['sorder_sum']) && !empty($sc['eorder_sum']) ){
			$sqlWhereClause .= " AND A.member_order_price <= ? ";
			$bind[] = $sc['eorder_sum'];
		}

		### 마일리지 예치금 포인트
		$sc_money_type = 'emoney';
		$sc_money_type_arr = [
			'point',
			'cash',
		];
		if (in_array($sc['sc_money_type'], $sc_money_type_arr)) {
			$sc_money_type = $sc['sc_money_type'];
		}
		if(strlen($sc['semoney']) && strlen($sc['eemoney']) && $sc['semoney'] === '0' && $sc['eemoney'] === '0'){
			$sqlWhereClause .= " AND A.{$sc_money_type} = 0 ";
		}elseif( !empty($sc['semoney']) && !empty($sc['eemoney']) ){
			$sqlWhereClause .= " AND A.{$sc_money_type} between ? and ? ";
			$bind[] = $sc['semoney'];
			$bind[] = $sc['eemoney'];
		}else if( !empty($sc['semoney']) && empty($sc['eemoney']) ){
			$sqlWhereClause .= " AND A.{$sc_money_type} >= ? ";
			$bind[] = $sc['semoney'];
		}else if( empty($sc['semoney']) && !empty($sc['eemoney']) ){
			$sqlWhereClause .= " AND A.{$sc_money_type} <= ? ";
			$bind[] = $sc['eemoney'];
		}

		### 리뷰횟수 방문횟수
		$sc_count_type = 'login_cnt';
		$sc_count_type_arr = [
			'review_cnt',
		];
		if (in_array($sc['sc_count_type'], $sc_count_type_arr)) {
			$sc_count_type = $sc['sc_count_type'];
		}
		if(strlen($sc['slogin_cnt']) && strlen($sc['elogin_cnt']) && $sc['slogin_cnt'] === '0' && $sc['elogin_cnt'] === '0'){
			$sqlWhereClause .= " AND A.{$sc_count_type} = 0 ";
		}elseif( !empty($sc['slogin_cnt']) && !empty($sc['elogin_cnt'])){
			$sqlWhereClause .= " AND A.{$sc_count_type} between ? and ? ";
			$bind[] = $sc['slogin_cnt'];
			$bind[] = $sc['elogin_cnt'];
		}else if( !empty($sc['slogin_cnt']) && empty($sc['elogin_cnt']) ){
			$sqlWhereClause .= " AND A.{$sc_count_type} >= ? ";
			$bind[] = $sc['slogin_cnt'];
		}else if( empty($sc['slogin_cnt']) && !empty($sc['elogin_cnt']) ){
			$sqlWhereClause .= " AND A.{$sc_count_type} <= ? ";
			$bind[] = $sc['elogin_cnt'];
		}

		### 가입방법
		if( !empty($sc['referer']) ){
			$sqlFromClause .= " LEFT JOIN fm_referer_group C ON A.referer_domain = C.referer_group_url";

			$sqlWhereClause .= " AND (IF(C.referer_group_no>0, C.referer_group_name, IF(LENGTH(A.referer)>0,'기타','직접입력'))) = ?";
			$bind[] = $sc['referer'];
		}

		### 가입양식start
		$ruteloop = memberrute('', '', 'array' , 'search');
		if($sc['snsrute']) {
			if($sc['snsrute'] == 'sns_f') {
				$snsruteWhereClause = " AND (A.sns_f is not null  AND A.sns_f  <> '' )";
			} else if($sc['snsrute'] == 'sns_etc') {
				foreach(explode('|', $ruteloop['sns_etc']['etc']) as $sns_etc) {
					$snsruteWhereClause[] = "(A.".$sns_etc." is not null  AND A.".$sns_etc."  <> '' )";
				}
				$snsruteWhereClause = "AND (".implode(' OR ', $snsruteWhereClause).")";
			} else if($sc['snsrute'] == 'none') {
				$snsruteWhereClause = " AND (A.rute = 'none')";
			} else {
				if ($sc['snsrute'] == 'sns_n' || $sc['snsrute'] == 'sns_k' || $sc['snsrute'] == 'sns_a') {
					$snsruteWhereClause = " AND (A.".$sc['snsrute']." is not null  AND A.".$sc['snsrute']."  <> '' )";
				}
			}
			$sqlWhereClause .= $snsruteWhereClause;
		}

		### 미니샵 kmj
		if( !empty($sc['provider_seq']) ){
			$sqlFromClause .= " INNER JOIN fm_member_minishop mshop
								on ( A.member_seq = mshop.member_seq
									and mshop.provider_seq = ? )";
			$bind[] = $sc['provider_seq'];
		}

		### 가입환경
		if( !empty($sc['sitetype']) ){
			$sqlWhereClause .= " AND A.platform = ? ";
			$bind[] = $sc['sitetype'];
		}

		### 성별
		if( !empty($sc['sex']) ){
			$sqlWhereClause .= " AND A.sex = ? ";
			$bind[] = $sc['sex'];
		}

		### 특정 회원 선택 시
		if(!empty($sc['member_seq'])){
			$sqlWhereClause .= " AND A.member_seq in (".$sc['member_seq'].") ";
		}

		//2020-07-15 전체 다운로드는 탈퇴회원만 빼고 다운로드 되어야함
		if($sc['excel_type'] == "all" && $sc['excel_spout_query']){
			$sqlWhereClause = " WHERE A.status in ('done','hold','dormancy')";
			$bind = [];
		}

		$listBind = $bind;
		if($sc['nolimit'] != 'y') {
			$limit =" limit ?, ? ";
			$listBind[] = (int)$sc['page'];
			$listBind[] = (int)$sc['perpage'];
		}

		$orderby_arr = [
			'A.regist_date',
			'A.emoney',
			'A.emoney',
			'member_order_price',
			'member_order_price',
			'member_order_cnt',
			'member_order_cnt',
			'A.review_cnt',
			'A.review_cnt',
			'A.login_cnt',
			'A.login_cnt',
			'member_recommend_cnt',
			'member_recommend_cnt',
		];
		$sort_arr = [
			'desc',
			'asc',
		];
		$orderby = 'A.regist_date';
		$sort = 'desc';

		if (in_array($sc['orderby'], $orderby_arr)) {
			$orderby = $sc['orderby'];
		}

		if (in_array($sc['sort'], $sort_arr)) {
			$sort = $sc['sort'];
		}

		if( !$sc['excel_spout'] ){
			$sqlOrderClause = " ORDER BY " . $orderby;
			if($sc['sort']){
				$sqlOrderClause .= " " . $sort;
			} else {
				$sqlOrderClause .= " ASC";
			}
		}

		if($sc['is_member']){
			$sqlSelectClause = "SELECT A.member_seq";
		}

		$sql = "
			{$sqlSelectClause}
			{$sqlFromClause}
			{$sqlWhereClause}
			{$sqlOrderClause}
		";

		if($sc['excel_spout_query'] ){


			if($bind){
				$sql = $this->db->compile_binds($sql, $bind);
				//return $this->db->query($sql, $bind);
			}
			return $sql;
		}
		$query = $this->db->query($sql.$limit, $listBind);

		$data['result'] = $query->result_array();

		if($sc['is_member']){
			$res = array();
			foreach($data['result'] as $v){
				$res[] = $v['member_seq'];
			}
			return $res;
		}

		if( $sc['excel_spout'] ){
			return $data['result'];
		}

		if($sc['pageType'] == "search") {
			// 20210531 (kjw) : status 가 전체, 휴면 검색일 경우 휴면회원을 제외처리 해야하고 나머지는 고려하지 않아도 되도록 수정
			if (!$sc['status'] || $sc['status'] == "dormancy") {
				$whereDormancy = " WHERE A.status = 'dormancy' ";
				$whereClause = str_replace("WHERE", "AND", $sqlWhereClause);
				$whereClause = str_replace("AND A.status = 'dormancy'", "", $whereClause);
				$whereClause = str_replace("AND A.status in ('done','hold','dormancy')", "", $whereClause);

				$dormancy_query = 'select count(A.member_seq) as cnt '. $sqlFromClause . $whereDormancy . $whereClause;
				$dormancyquery = $this->db->query($dormancy_query, $bind);
				$dormancyrow = $dormancyquery->result_array();
				$data['dormancy_count'] = $dormancyrow[0]['cnt'];
			} else {
				$data['dormancy_count'] = 0;
			}
		}

		$cnt_query = 'select count(A.member_seq) as cnt '. $sqlFromClause . $sqlWhereClause;
		$cntquery = $this->db->query($cnt_query, $bind);
		$cntrow = $cntquery->result_array();
		$data['count'] = $cntrow[0]['cnt'];

		return $data;
	}

	// 회원리스트 공통 필드 추출
	public function admin_member_list_params($sc, &$bind) {
		### 가입일 / 최종방문일
		$search_date_type = 'regist_date'; // 21.05.10 lsh 날짜 검색 타입 설정 기본값:가입일
		if ($sc['sc_day_type'] == "lastlogin" || $sc['lastlogin_sdate'] || $sc['lastlogin_edate']) {
			$search_date_type = 'lastlogin_date';
		}

		$sc['regist_sdate'] =  $sc['lastlogin_sdate'] ? $sc['lastlogin_sdate'] : $sc['regist_sdate'];
		$sc['regist_edate'] =  $sc['lastlogin_edate'] ? $sc['lastlogin_edate'] : $sc['regist_edate'];

		if($sc['regist_sdate'] ) $regist_sdate = $sc['regist_sdate']." 00:00:00";
		if($sc['regist_edate'] ) $regist_edate = $sc['regist_edate']." 23:59:59";

		if( !empty($regist_sdate) && !empty($regist_edate) ){
			if($sc['dormancy'] || $sc['status'] == 'dormancy') {
				if($sc['lastlogin_search_type'] == 'out') {
					$registDateWhereClause = " AND ((A.{$search_date_type} < ? or A.{$search_date_type} > ?)
					or ( A.lastlogin_date = '0000-00-00 00:00:00' and (A.regist_date < ? or A.regist_date > ?))) ";
					$bind[] = $regist_sdate;
					$bind[] = $regist_edate;
					$bind[] = $regist_sdate;
					$bind[] = $regist_edate;
				} else {
					$registDateWhereClause = " AND ((A.{$search_date_type} between ? and ?)
					or ( A.lastlogin_date = '0000-00-00 00:00:00' and (A.regist_date between ? and ?))) ";
					$bind[] = $regist_sdate;
					$bind[] = $regist_edate;
					$bind[] = $regist_sdate;
					$bind[] = $regist_edate;
				}
			} else {
				if($sc['lastlogin_search_type'] == 'out') {
					$registDateWhereClause = " AND A.{$search_date_type} < ? or A.{$search_date_type} > ? ";
					$bind[] = $regist_sdate;
					$bind[] = $regist_edate;
				} else {
					$registDateWhereClause = " AND A.{$search_date_type} between ? and ? ";
					$bind[] = $regist_sdate;
					$bind[] = $regist_edate;
				}
			}
		} else if( !empty($regist_sdate) && empty($regist_edate)) {
			if($sc['dormancy'] || $sc['status'] == 'dormancy') {
				if($sc['lastlogin_search_type'] == 'out') {
					$registDateWhereClause = " AND ((A.{$search_date_type} < ?) or (A.{$search_date_type} = '0000-00-00 00:00:00' && A.regist_date < ?)) ";
					$bind[] = $regist_sdate;
					$bind[] = $regist_sdate;
				} else {
					$registDateWhereClause = " AND ((A.{$search_date_type} >= ?) or (A.{$search_date_type} = '0000-00-00 00:00:00' && A.regist_date >= ?)) ";
					$bind[] = $regist_sdate;
					$bind[] = $regist_sdate;
				}
			} else {
				if($sc['lastlogin_search_type'] == 'out') {
					$registDateWhereClause = " AND A.{$search_date_type} < ? ";
					$bind[] = $regist_sdate;
				} else {
					$registDateWhereClause = " AND A.{$search_date_type} >= ?";
					$bind[] = $regist_sdate;
				}
			}
		} else if( empty($regist_sdate) && !empty($regist_edate)) {
			if($sc['dormancy'] || $sc['status'] == 'dormancy') {
				if($sc['lastlogin_search_type'] == 'out') {
					$registDateWhereClause = " AND ((A.{$search_date_type} > ?) or (A.{$search_date_type} = '0000-00-00 00:00:00' && A.regist_date > ?)) ";
					$bind[] = $regist_edate;
					$bind[] = $regist_edate;
				} else {
					$registDateWhereClause = " AND ((A.{$search_date_type} <= ?) or (A.{$search_date_type} = '0000-00-00 00:00:00' && A.regist_date <= ?)) ";
					$bind[] = $regist_edate;
					$bind[] = $regist_edate;
				}
			} else {
				if($sc['lastlogin_search_type'] == 'out') {
					$registDateWhereClause = " AND A.{$search_date_type} > ? ";
					$bind[] = $regist_edate;
				} else {
					$registDateWhereClause = " AND A.{$search_date_type} <= ?";
					$bind[] = $regist_edate;
				}
			}
		}

		$sage_date = $sc['eage']!=0 ? date("Y-m-d", strtotime("- " . $sc['sage'] . " years")) : date("Y-m-d", strtotime("Now"));
        $eage_date = $sc['eage']!=0 ? date("Y-m-d", strtotime("- " . $sc['eage'] . " years")) : date("Y-m-d", strtotime("Now"));

        if(!empty($sc['sage']) && !empty($sc['eage'])){
            $ageWhereClause .= " AND A.birthday between ? and ?";
            $bind[] = $eage_date;
            $bind[] = $sage_date;
        }else if(!empty($sc['sage']) && empty($sc['eage'])){
            $ageWhereClause .= " AND A.birthday >= ?";
            $bind[] = $eage_date;
        }else if(empty($sc['sage']) && !empty($sc['eage'])){
            $ageWhereClause .= " AND A.birthday <= ?";
            $bind[] = $sage_date;
        }

		if($registDateWhereClause) {
			$sqlWhereClause.= $registDateWhereClause;
		}
		if($ageWhereClause) {
			$sqlWhereClause.= $ageWhereClause;
		}		
		return $sqlWhereClause;
	}

	//쿠폰 대량 지급 관련 회원 검색 kmj
	public function coupon_member_list($sc){
		$key = get_shop_key();

		$sqlSelectClause = "SELECT
			A.member_seq,
			A.userid,
			A.user_name,
			A.nickname,
			A.mailing,
			A.sms,
			AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
			AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
			AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone";

		$sqlFromClause = <<<SQL
FROM fm_member A
LEFT JOIN fm_member_business business ON A.member_seq = business.member_seq
SQL;
		//$sqlWhereClause = " WHERE (A.status != 'dormancy' and A.status != 'withdrawal')";
		//done: 승인, hold: 미승인, withdrawal: 탈퇴, dormancy: 휴면
		$sqlWhereClause = " WHERE A.status = 'done'"; //승인만 준다

		$sqlOrderClause = " ORDER BY " . $sc['orderby'];
		if($sc['sort']){
			$sqlOrderClause .= " " . $sc['sort'];
		} else {
			$sqlOrderClause .= " ASC";
		}

		### 검색 키워드
		if( !empty($sc['keyword']) || strlen($sc['keyword'])){

            $keyword_like = $this->db->escape('%'.$sc['keyword'].'%');

			if($sc['search_field'] && $sc['search_field'] != 'all'){
				$sqlWhereClauseTmp = array();
				switch($sc['search_field']){
					case "A.address":
						$sqlWhereClauseTmp[] = "A.address like {$keyword_like}";
						$sqlWhereClauseTmp[] = "A.address_detail like {$keyword_like}";
					break;
					case "A.email":
					case "A.phone":
					case "A.cellphone":
						$sqlWhereClauseTmp[] = "AES_DECRYPT(UNHEX(".$sc['search_field']."), '{$key}') like {$keyword_like}";
					break;
					default:
						$sqlWhereClauseTmp[] = $sc['search_field']." like {$keyword_like} ";
					break;
				}
				$sqlWhereClause .= " AND (".implode(" or ",$sqlWhereClauseTmp).")";

			}else{

            $sqlWhereClause .= <<<SQL
 AND (
    A.userid LIKE {$keyword_like}
    OR A.user_name LIKE {$keyword_like}
    OR AES_DECRYPT(UNHEX(A.email), "{$key}") LIKE {$keyword_like}
    OR AES_DECRYPT(UNHEX(A.phone), "{$key}") LIKE {$keyword_like}
    OR AES_DECRYPT(UNHEX(A.cellphone), "{$key}") LIKE {$keyword_like}
    OR A.address LIKE {$keyword_like}
    OR A.address_detail LIKE {$keyword_like}
    OR A.nickname LIKE {$keyword_like}
    OR A.sns_n LIKE {$keyword_like}
	OR business.bname LIKE {$keyword_like}
)
SQL;
		}
		}

		$add_stime	= ' 00:00:00';
		$add_etime	= ' 23:59:59';

		### 가입일/최종로그인
		if( !empty($sc['date_gb']) && !empty($sc['sdate']) && !empty($sc['edate']) ){
			$sqlWhereClause .= " AND A.{$sc['date_gb']} BETWEEN '{$sc['sdate']}{$add_stime}' and '{$sc['edate']}{$add_etime}'";
		}else if( !empty($sc['sdate']) && empty($sc['edate']) ){
			$sqlWhereClause .= " AND A.{$sc['date_gb']} >= '{$sc['sdate']}{$add_stime}'";
		}else if( empty($sc['sdate']) && !empty($sc['edate']) ){
			$sqlWhereClause .= " AND A.{$sc['date_gb']} <= '{$sc['edate']}{$add_etime}'";
		}

		### sms
		if( !empty($sc['sms']) ){
			$sqlWhereClause .= " AND A.sms = '{$sc[sms]}'";
		}

		### mailing
		if( !empty($sc['mailing']) ){
			$sqlWhereClause .= " AND A.mailing = '{$sc[mailing]}'";
		}

		### 회원유형
		if( !empty($sc['business_seq']) ){
			if($sc['business_seq']=='y'){ //기업회원 검색
				//기업회원 목록
				$busMemberSql	= "SELECT member_seq FROM fm_member_business";
				$busMemberDB	= $this->db->query($busMemberSql);
				$busMemberRes	= $busMemberDB->result_array();

				$busMember		= array();
				foreach($busMemberRes as $v){
					$busMember[] = $v['member_seq'];
				}

				if(count($busMember) > 0){
					$sqlWhereClause .= " AND A.member_seq IN ('" . join("','",$busMember) . "')";
				}
			} else { //개인회원 검색
				$sqlFromClause .= " LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq";
				$sqlWhereClause .=  " AND B.business_seq is null";
			}
		}

		### 가입승인
		/*
		if( !empty($sc['status']) ){
			$sqlWhereClause .= " AND A.status = '{$sc[status]}'";
		}
		*/

		### 등급
		if( !empty($sc['grade']) ){
			if( is_array($sc['grade']) ){
				$groups = join("','", $sc['grade']);
				$sqlWhereClause .= " AND A.group_seq IN ('{$groups}')";
			} else {
				$sqlWhereClause .= " AND A.group_seq = '{$sc[grade]}'";
			}
		}

		### 구매금액
		if(strlen($sc['sorder_sum']) && strlen($sc['eorder_sum']) && $sc['sorder_sum'] === '0' && $sc['eorder_sum'] === '0'){
			$sqlWhereClause .= " AND A.member_order_price = 0";
		}elseif( !empty($sc['sorder_sum']) && !empty($sc['eorder_sum']) ){
			$sqlWhereClause .= " AND A.member_order_price BETWEEN '{$sc['sorder_sum']}' and '{$sc['eorder_sum']}'";
		}else if( !empty($sc['sorder_sum']) && empty($sc['eorder_sum']) ){
			$sqlWhereClause .= " AND A.member_order_price >= '{$sc['sorder_sum']}'";
		}else if( empty($sc['sorder_sum']) && !empty($sc['eorder_sum']) ){
			$sqlWhereClause .= " AND A.member_order_price <= '{$sc['eorder_sum']}'";
		}

		### 마일리지
		if(strlen($sc['semoney']) && strlen($sc['eemoney']) && $sc['semoney'] === '0' && $sc['eemoney'] === '0'){
			$sqlWhereClause .= " AND A.emoney = 0";
		}elseif( !empty($sc['semoney']) && !empty($sc['eemoney']) ){
			$sqlWhereClause .= " AND A.emoney BETWEEN '{$sc['semoney']}' and '{$sc['eemoney']}'";
		}else if( !empty($sc['semoney']) && empty($sc['eemoney']) ){
			$sqlWhereClause .= " AND A.emoney >= '{$sc['semoney']}'";
		}else if( empty($sc['semoney']) && !empty($sc['eemoney']) ){
			$sqlWhereClause .= " AND A.emoney <= '{$sc['eemoney']}'";
		}

		### 주문횟수
		if(strlen($sc['sorder_cnt']) && strlen($sc['eorder_cnt']) && $sc['sorder_cnt'] === '0' && $sc['eorder_cnt'] === '0'){
			$sqlWhereClause .= " AND A.member_order_cnt = 0";
		}elseif( !empty($sc['sorder_cnt']) && !empty($sc['eorder_cnt'])){
			$sqlWhereClause .= " AND A.member_order_cnt BETWEEN '{$sc['sorder_cnt']}' and '{$sc['eorder_cnt']}'";
		}else if( !empty($sc['sorder_cnt']) && empty($sc['eorder_cnt']) ){
			$sqlWhereClause .= " AND A.member_order_cnt >= '{$sc['sorder_cnt']}'";
		}else if( empty($sc['sorder_cnt']) && !empty($sc['eorder_cnt']) ){
			$sqlWhereClause .= " AND A.member_order_cnt <= '{$sc['eorder_cnt']}'";
		}

		### 리뷰횟수
		if(strlen($sc['sreview_cnt']) && strlen($sc['ereview_cnt']) && $sc['sreview_cnt'] === '0' && $sc['ereview_cnt'] === '0'){
			$sqlWhereClause .= " AND A.review_cnt = 0";
		}elseif( !empty($sc['sreview_cnt']) && !empty($sc['ereview_cnt'])){
			$sqlWhereClause .= " AND A.review_cnt BETWEEN '{$sc['sreview_cnt']}' and '{$sc['ereview_cnt']}'";
		}else if( !empty($sc['sreview_cnt']) && empty($sc['ereview_cnt']) ){
			$sqlWhereClause .= " AND A.review_cnt >= '{$sc['sreview_cnt']}'";
		}else if( empty($sc['sreview_cnt']) && !empty($sc['ereview_cnt']) ){
			$sqlWhereClause .= " AND A.review_cnt <= '{$sc['ereview_cnt']}'";
		}

		### 방문횟수
		if(strlen($sc['slogin_cnt']) && strlen($sc['elogin_cnt']) && $sc['slogin_cnt'] === '0' && $sc['elogin_cnt'] === '0'){
			$sqlWhereClause .= " AND A.login_cnt = 0";
		}elseif( !empty($sc['slogin_cnt']) && !empty($sc['elogin_cnt'])){
			$sqlWhereClause .= " AND A.login_cnt BETWEEN '{$sc['slogin_cnt']}' and '{$sc['elogin_cnt']}'";
		}else if( !empty($sc['slogin_cnt']) && empty($sc['elogin_cnt']) ){
			$sqlWhereClause .= " AND A.login_cnt >= '{$sc['slogin_cnt']}'";
		}else if( empty($sc['slogin_cnt']) && !empty($sc['elogin_cnt']) ){
			$sqlWhereClause .= " AND A.login_cnt <= '{$sc['elogin_cnt']}'";
		}

		### 회원번호
		if($sc['member_seq']){
			$sqlWhereClause .= " AND A.member_seq IN('".join("','", $sc['member_seq'])."')";
		}

		if($sc['is_member']){
			$sqlSelectClause = "SELECT A.member_seq";
		}

		if($sc['is_member'] || $sc['is_count']){
			$sqlOrderClause	 = "";
		}

		$sql = "{$sqlSelectClause}
			{$sqlFromClause}
			{$sqlWhereClause}
			{$sqlOrderClause}";


		$page_s = $sc['page'];
		if(!$sc['is_count']){
			if($sc['nolimit'] != 'y') {
				$limit =" limit {$page_s}, {$sc['perpage']} ";
			}

			if($sc['is_query']){
				return $sql.$limit;
			}

			$query = $this->db->query($sql.$limit);
			$data['result'] = $query->result_array();

			if($sc['is_data']){
				return $data['result'];
			}

			if($sc['is_member']){
				$res = array();
				foreach($data['result'] as $v){
					$res[] = $v['member_seq'];
				}
				return $res;
			}
		}

		$cnt_query = 'select count(A.member_seq) as cnt '. $sqlFromClause . $sqlWhereClause;
		$cntquery = $this->db->query($cnt_query);
		$cntrow = $cntquery->result_array();
		$data['count'] = $cntrow[0]['cnt'];

		if($sc['is_count']){
			return $data['count'];
		}

		return $data;
	}

	/* ADMIN > SETTING */
	public function admin_manager_list($sc,$all=null) {
		$key = get_shop_key();
		$sql = "select
				A.*
			from
				fm_manager A
			where manager_id != ?";
		$bind[] = 'gabia';
		###
		if( !empty($sc['search_text'])){
			$sql .= ' and ( manager_id like ? or mname like ? ) ';
			$bind[] = '%'.$sc['search_text'].'%';
			$bind[] = '%'.$sc['search_text'].'%';
		}

		$orderby	= 'manager_seq';
		$sort			= 'desc';
		if($sc['orderby']){
			$orderby = mysqli_real_escape_string($this->db->conn_id, $sc['orderby']);
		}
		if($sc['sort']){
			$sort = mysqli_real_escape_string($this->db->conn_id, $sc['sort']);
		}

		$page = (int) $sc['page'];
		$perpage = (int) $sc['perpage'];


		$sql .=" order by ".$orderby." ".$sort;
		if(!$all) $limit =" limit ".$page.", ".$perpage." ";

		//echo $sql;
		$query = $this->db->query($sql.$limit,$bind);
		$data['result'] = $query->result_array();

		foreach ($data['result'] as $key => $rs) {
			$wheres						= array();
			$auth						= array();
			$wheres['shopSno']			= $this->config_system['shopSno'];
			$wheres['manager_seq']		= $rs['manager_seq'];
			$wheres['codecd not like']	= '%_priod_%';
			$orderbys['idx'] 			= 'asc';
			$query_auth					= $this->authmodel->select('*',$wheres,$orderbys);
			$pprs						= $query_auth->result_array();
			foreach($pprs as $data_auth){
				if	($data_auth['codecd'] == 'manager_yn')
					$manager_yn			= $data_auth['value'];
				$auth[]	= $data_auth['codecd'].'='.$data_auth['value'];
			}
			$data['result'][$key]['manager_auth']	= implode('||', $auth);
			$data['result'][$key]['manager_yn']		= $manager_yn;
		}

		if(!$all) {
			$query = $this->db->query($sql,$bind);
			$data['count'] = $query->num_rows();
		}

		return $data;
	}

	/* SELLERADMIN > SETTING */
	public function seller_manager_list($sc,$all=null) {
		$key = get_shop_key();
		$provider_seq = $this->providerInfo['provider_seq'];
		$sql = "select
				A.*
			from
				fm_provider A
			where provider_id!='base' and provider_group = {$provider_seq} or provider_seq = {$provider_seq} ";

		###
		if( !empty($sc['search_text'])){
			$sql .= ' and ( provider_id like "%'.$sc['search_text'].'%" or provider_name like "%'.$sc['search_text'].'%" ) ';
		}

		$sc['orderby']	= (isset($sc['orderby'])) ?	$sc['orderby']:'provider_seq';
		$sc['sort']			= (isset($sc['sort'])) ?		$sc['sort']:'desc';
		$sql .=" order by {$sc['orderby']} {$sc['sort']}";
		if(!$all) $limit =" limit {$sc['page']}, {$sc['perpage']} ";

		//echo $sql;
		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();

		if(!$all) {
			$query = $this->db->query($sql);
			$data['count'] = $query->num_rows();
		}

		return $data;
	}

	public function seller_manager_auth_list(){
		$auth_list = config_load("seller_auth");
		foreach($auth_list as $k=>$v){
			$auth_arr[] = $k;
		}

		$auth_text = "";
		foreach($auth_arr as $k){
			if($k=='setting_manager_view'){
				$value = 'Y';
			}else{
				$value = if_empty($_POST, $k, 'N');
			}
			$auth_text .= $k."=".$value."||";
		}
		return $auth_text;
	}

	/* ADMIN > MEMBER */
	public function popup_member_list($sc) {


		$key = get_shop_key();

		$sql = "select SQL_CALC_FOUND_ROWS *,
				A.*,
				AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
				AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
				AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone,
				CASE WHEN A.status = 'done' THEN '승인'
					 WHEN A.status = 'hold' THEN '미승인'
					 WHEN A.status = 'withdrawal' THEN '탈퇴'
					 WHEN A.status = 'dormancy' THEN '휴면'
				ELSE '' END AS status_nm,
				B.business_seq,
				B.bname,
				C.group_name
			from
				fm_member A
				LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq
				LEFT JOIN fm_member_group C ON A.group_seq = C.group_seq
			where 1";

		###
		$sql .= " AND A.status NOT IN('withdrawal', 'dormancy') ";
		###
		if( !empty($sc['search_text'])){

			$sql .= " and ( A.userid like '%".$sc['search_text']."%' or A.user_name like '%".$sc['search_text']."%' or AES_DECRYPT(UNHEX(A.email), '{$key}') like '%".$sc['search_text']."%' or AES_DECRYPT(UNHEX(A.phone), '{$key}') like '%".$sc['search_text']."%' or AES_DECRYPT(UNHEX(A.cellphone), '{$key}') like '%".$sc['search_text']."%' or A.address like '%".$sc['search_text']."%'  or A.address_detail like '%".$sc['search_text']."%' or B.bname like '%".$sc['search_text']."%'  or B.baddress  like '%".$sc['search_text']."%' or B.bphone  like '%".$sc['search_text']."%' or B.bcellphone like '%".$sc['search_text']."%'  or B.baddress_detail like '%".$sc['search_text']."%' ) ";

		}
		### DATE
		if( !empty($sc['sdate']) && !empty($sc['edate'])){
			$sql .= " AND A.{$sc['date_gb']} between '{$sc['sdate']}' and '{$sc['edate']}' ";
		}
		### sms
		if( !empty($sc['sms']) ){
			$sql .= " AND A.sms = '{$sc[sms]}' ";
		}
		### mailing
		if( !empty($sc['mailing']) ){
			$sql .= " AND A.mailing = '{$sc[mailing]}' ";
		}
		### business_seq
		if( !empty($sc['business_seq']) ){
			$sql .= $sc['business_seq']=='n' ? " AND B.business_seq is null " : " AND B.business_seq != '' ";
		}
		### status
		if( !empty($sc['status']) ){
			$sql .= " AND A.status = '{$sc[status]}' ";
		}
		### grade
		if( !empty($sc['grade']) ){
			$sql .= " AND A.group_seq = '{$sc[grade]}' ";
		}

		### groups array()
		if( !empty($sc['groupsar']) ){
			$groups = implode("','",$sc['groupsar']);
			$sql .= " AND A.group_seq in ('".$groups."')";
		}

		### order_sum
		if( !empty($sc['sorder_sum']) && !empty($sc['eorder_sum']) ){
			$sql .= " AND A.member_order_price between '{$sc['sorder_sum']}' and '{$sc['eorder_sum']}' ";
		}else if( !empty($sc['sorder_sum']) && empty($sc['eorder_sum']) ){
			$sql .= " AND A.member_order_price >= '{$sc['sorder_sum']}' ";
		}else if( empty($sc['sorder_sum']) && !empty($sc['eorder_sum']) ){
			$sql .= " AND A.member_order_price <= '{$sc['eorder_sum']}' ";
		}

		### emoney
		if( !empty($sc['semoney']) && !empty($sc['eemoney']) ){
			$sql .= " AND A.emoney between '{$sc['semoney']}' and '{$sc['eemoney']}' ";
		}else if( !empty($sc['semoney']) && empty($sc['eemoney']) ){
			$sql .= " AND A.emoney >= '{$sc['semoney']}' ";
		}else if( empty($sc['semoney']) && !empty($sc['eemoney']) ){
			$sql .= " AND A.emoney <= '{$sc['eemoney']}' ";
		}
		if( !empty($sc['spoint']) && !empty($sc['epoint']) ){
			$sql .= " AND A.point between '{$sc['spoint']}' and '{$sc['epoint']}' ";
		}else if( !empty($sc['spoint']) && empty($sc['epoint']) ){
			$sql .= " AND A.point >= '{$sc['spoint']}' ";
		}else if( empty($sc['spoint']) && !empty($sc['epoint']) ){
			$sql .= " AND A.point <= '{$sc['epoint']}' ";
		}
		if( !empty($sc['scash']) && !empty($sc['ecash']) ){
			$sql .= " AND A.cash between '{$sc['scash']}' and '{$sc['ecash']}' ";
		}else if( !empty($sc['scash']) && empty($sc['ecash']) ){
			$sql .= " AND A.cash >= '{$sc['scash']}' ";
		}else if( empty($sc['scash']) && !empty($sc['ecash']) ){
			$sql .= " AND A.cash <= '{$sc['ecash']}' ";
		}

		### order_cnt
		if( !empty($sc['sorder_cnt']) && !empty($sc['eorder_cnt'])){
			$sql .= " AND A.member_order_cnt between '{$sc['sorder_cnt']}' and '{$sc['eorder_cnt']}' ";
		}else if( !empty($sc['sorder_cnt']) && empty($sc['eorder_cnt']) ){
			$sql .= " AND A.member_order_cnt >= '{$sc['sorder_cnt']}' ";
		}else if( empty($sc['sorder_cnt']) && !empty($sc['eorder_cnt']) ){
			$sql .= " AND A.member_order_cnt <= '{$sc['eorder_cnt']}' ";
		}

		### review_cnt
		if( !empty($sc['sreview_cnt']) && !empty($sc['ereview_cnt'])){
			$sql .= " AND A.review_cnt between '{$sc['sreview_cnt']}' and '{$sc['ereview_cnt']}' ";
		}else if( !empty($sc['sreview_cnt']) && empty($sc['ereview_cnt']) ){
			$sql .= " AND A.review_cnt >= '{$sc['sreview_cnt']}' ";
		}else if( empty($sc['sreview_cnt']) && !empty($sc['ereview_cnt']) ){
			$sql .= " AND A.review_cnt <= '{$sc['ereview_cnt']}' ";
		}

		### login_cnt
		if( !empty($sc['slogin_cnt']) && !empty($sc['elogin_cnt'])){
			$sql .= " AND A.login_cnt between '{$sc['slogin_cnt']}' and '{$sc['elogin_cnt']}' ";
		}else if( !empty($sc['slogin_cnt']) && empty($sc['elogin_cnt']) ){
			$sql .= " AND A.login_cnt >= '{$sc['slogin_cnt']}' ";
		}else if( empty($sc['slogin_cnt']) && !empty($sc['elogin_cnt']) ){
			$sql .= " AND A.login_cnt <= '{$sc['elogin_cnt']}' ";
		}

		if($sc['orderby'] && $sc['sort']) {
			$sql .=" order by {$sc['orderby']} {$sc['sort']}";
		}

		if(isset($sc['page']) && isset($sc['perpage'])) {
			$limit =" limit {$sc['page']}, {$sc['perpage']} ";
		}

		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();


		//총건수
		$sql = "SELECT FOUND_ROWS() as COUNT";
		$query_count = $this->db->query($sql);
		$res_count= $query_count->result_array();
		$data['count'] = $res_count[0]['COUNT'];

		return $data;
	}

	/* ADMIN > MEMBER */
	public function admin_withdrawal_list($sc) {


		$key = get_shop_key();

		$sql = "select
				A.*,
				B.userid,
				B.user_name,
				B.order_cnt,
				B.order_sum,
				B.review_cnt,
				B.login_cnt,
				B.emoney
			from
				fm_member_withdrawal A
				LEFT JOIN fm_member B ON A.member_seq = B.member_seq
			where 1";

		###
		$sql .= " AND B.status = 'withdrawal' ";

		$query = $this->db->query($sql);
		$data['totalcount'] = $query->num_rows();

		###
		if( !empty($sc['keyword']))
		{
			$sql .= ' and userid like "%'.$sc['keyword'].'%" ';
		}

		### DATE
		/* SQL INJECTION 방지를 위한 데이터 바인딩 추가 */
		$bindData = [];
		if( !empty($sc['sdate']) && !empty($sc['edate']))
		{
			$sql .= " AND date_format(A.regist_date, '%Y-%m-%d') >= ? AND date_format(A.regist_date, '%Y-%m-%d') <= ? ";
			$bindData[] = $sc['sdate'];
			$bindData[] = $sc['edate'];
		}

		$sql .=" order by {$sc['orderby']} {$sc['sort']}";
		$limit =" limit {$sc['page']}, {$sc['perpage']} ";
		$query = $this->db->query($sql.$limit, $bindData);
		$data['result'] = $query->result_array();

		$query = $this->db->query($sql, $bindData);
		$data['searchcount'] = $query->num_rows();
		return $data;
	}



	/* ADMIN > MEMBER */
	public function email_history_list($sc) {


		$sql = "select * from fm_log_email";
		$sqltotal = "select  count(*) as cnt from fm_log_email ";

		$where = array();
		if( !empty($sc['start_date']) && !empty($sc['end_date']) ){
			$where[] = " regdate between '{$sc['start_date']} 00:00:00' and '{$sc['end_date']} 23:59:59' ";
		}

		if( !empty($sc['sc_subject']) ){
			$where[] = " subject like '%{$sc['sc_subject']}%' ";
		}

		if( !empty($sc['sc_gb']) ){
			$totalwhere = $where[] = " gb='".$sc['sc_gb']."'";
		}else{
			$totalwhere = $where[] = " gb != 'PERSONAL'";
		}

		if (!empty($sc['order_seq'])) {
			$where[] = sprintf("order_seq = '%s' ", $sc['order_seq']);
		}

		$query = $this->db->query($sqltotal." WHERE ".$totalwhere);
		$email_total = $query->result_array();
		$data['totalcount'] = $email_total[0]['cnt'];

		if($where) { $sql .= " where ".implode(" and ",$where); }
		$sql	.= ($sc['orderby'])? " order by {$sc['orderby']} {$sc['sort']}":"";
		$limit	 =" limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();

		if($where) { $sqltotal .= " where ".implode(" and ",$where); }
		$query2 = $this->db->query($sqltotal);
		$cntrow = $query2->result_array();
		$data['count'] = $cntrow[0]['cnt'];

		return $data;
	}


	/* ADMIN > MEMBER */
	public function admin_member_url($file_path) {
		$file_nm = end(explode("/",$file_path));
		$file_arr = explode(".",$file_nm);
		return $file_arr[0];
	}


	/* ADMIN > MEMBER */
	public function sms_form_list($sc) {
		$sql = "select * from fm_sms_album where 1";

		if( !empty($sc['category']) )
		{
			$sql .= " AND category = '{$sc['category']}' ";
		}

		if( !empty($sc['sms_search']) )
		{
			$sql .= " AND msg like '%{$sc['sms_search']}%'";
		}

		$sql			.=" order by seq desc";
		$limit			=" limit {$sc['page']}, {$sc['perpage']} ";
		$query			= $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();

		$query			= $this->db->query($sql);
		$data['count']	= $query->num_rows();
		$data['sql']	= $sql.$limit;

		return $data;
	}

	/* 당월 이메일 발송 횟수 */
	public function get_send_history_email_month()
	{
		$toMonth = date("Y-m");
		$query = $this->db->select_sum('total', 'count')
		->from('fm_log_email')
		->where('gb', 'MANUAL')
		->like('regdate', $toMonth, 'after')
		->get();

		return $query->row_array();
	}

	/* 회원 그룹 추가 마일리지 */
	public function get_group_benifit($group_seq,$sale_seq)
	{
		$CI =& get_instance();
		if (isset($CI->group_benifit[$group_seq][$sale_seq])) {
			$data = $CI->group_benifit[$group_seq][$sale_seq];
		} else {
			$query = "select a.*,ifnull((select group_name from fm_member_group where group_seq=a.group_seq),'비회원') group_name from fm_member_group_sale_detail a where a.group_seq = ? and a.sale_seq = ?";
			$query = $this->db->query($query, array((int)$group_seq,(int)$sale_seq));
			list($data) = $query->result_array($query);
			$CI->group_benifit[$group_seq][$sale_seq] = $data;
		}
		return $data;
	}

	public function get_goods_group_benifits($sale_seq)
	{
		$CI =& get_instance();
		if (isset($CI->goods_group_benifits[$sale_seq])) {
			$data = $CI->goods_group_benifits[$sale_seq];
		} else {
			$query = "select a.*,ifnull((select group_name from fm_member_group where group_seq=a.group_seq),'비회원') group_name, (select use_type from fm_member_group where group_seq = a.group_seq) as use_type from fm_member_group_sale_detail a where a.sale_seq = ?";
			$query = $this->db->query($query, array((int)$sale_seq));
			$data = $query->result_array($query);
			$CI->goods_group_benifits[$sale_seq] = $data;
		}
		return $data;
	}

	public function get_group_except_category($group_seq, $sale_seq, $category, $type)
	{
		$CI =& get_instance();
		$sCategory = $category;
		if (strpos($category,',')) {
			$category	= explode(',',$category);
		}
		$aWhere = array('type' => $type, 'sale_seq' => (int) $sale_seq);
		$sKey = implode('/', $aWhere);
		if ( ! is_array($category)) {
			$aWhere['category_code'] = $category;
		} else {
			$sKey = implode('/', $aWhere) . '/' . implode(',', $category);
		}
		if (isset($CI->group_except_category[$sKey])) {
			$cnt = $CI->group_except_category[$sKey];
		} else {
			$this->db->select('count(*) cnt', false);
			$this->db->from('fm_member_group_issuecategory');
			$this->db->where($aWhere);
			if (is_array($category) && $category[0]) {
				$this->db->where_in('category_code', $category);
			}
			$query = $this->db->get();
			$cnt = $query->row_array();
			$CI->group_except_category[$sKey] = $cnt;
		}
		return $cnt['cnt'];
	}

	public function get_group_except_goods_seq($group_seq,$sale_seq,$goods_seq,$type)
	{
		$query = "select count(*) cnt from fm_member_group_issuegoods where type=? and sale_seq=? and goods_seq=?";
		$query = $this->db->query($query,array($type,(int)$sale_seq,(int)$goods_seq));
		$cnt = $query->row_array();
		return $cnt['cnt'];
	}

	// $category array 카테고리 코드
	public function get_group_addreseve($member_seq,$goods_price,$order_price,$goods_seq='',$category='', $sale_seq='', $group_seq='', $benifit_type='reserve'){
		$reserve = 0;

		if(!$group_seq){
			$data_member = $this->get_member_data($member_seq);
			$group_seq = $data_member['group_seq'];
		}
		if(!$sale_seq){
			$sql	= "select sale_seq from fm_goods where goods_seq = ?";
			$query	= $this->db->query($sql,array($goods_seq));
			$result	= $query->row_array();
			$sale_seq = $result['sale_seq'];
		}
		$data = $this->get_group_benifit($group_seq,$sale_seq);

		if( $category ){
			$category_in = implode("','",$category);
			$cnt = $this->get_group_except_category($data['group_seq'],$sale_seq,$category_in,'emoney');
			if( $cnt > 0 ){
				return 0;
			}
		}

		if( $goods_seq ){
			$cnt = $this->get_group_except_goods_seq($data['group_seq'],$sale_seq,$goods_seq,'emoney');
			if( $cnt > 0 ){
				return 0;
			}
		}

		if($benifit_type == 'reserve')
		{
			if( $data['reserve_price_type'] == 'PER' && $data['reserve_price'] && $goods_price ){
				$reserve = ($goods_price * $data['reserve_price'])/100;
			}
			if($data['point_use'] == 'Y' && $order_price && $order_price < $data['point_limit_price']){
				return 0;
			}
		}else{
			if( $data['point_price_type'] == 'PER' && $data['point_price'] && $goods_price ){
				$reserve = ($goods_price * $data['point_price'])/100;
			}
			if($data['point_use'] == 'Y' && $order_price && $order_price < $data['point_limit_price']){
				return 0;
			}
		}



		return get_cutting_price($reserve);

	}

	/* 회원 그룹 할인계산 */
	public function get_member_group($group_seq, $goods_seq, $category, $goods_price, $tot_price = 0, $sale_seq = '', $benifit_type = 'option'){
		$member_sale = 0;
		if( ! $this->config_system['cutting_price'] ) $this->config_system['cutting_price'] = 10;
		$data = $this->group_benifit = $this->get_group_benifit($group_seq,$sale_seq);

		$category_in = "";
		if( $category ){
			if(is_array($category)){
				if(count($category) == 1){
					$category_in = "'".$category[0]."'";
				}else{
					$category_in = implode("','",$category);
					$category_in = "'".$category_in."'";
				}
			}else{
				if(strlen($category) >= 4){
					for($i=1; $i<=(strlen($category)/4); $i++){
						if($category_in == ""){
							$category_in = "'".substr($category, 0, $i*4)."'";
						}else{
							$category_in .= ",'".substr($category, 0, $i*4)."'";
						}
					}
				}
			}

			$cnt = $this->get_group_except_category($group_seq, $sale_seq, $category, 'sale');
			if( $cnt > 0 ){
				return 0;
			}
		}

		if( $goods_seq ){
			$cnt = $this->get_group_except_goods_seq($group_seq, $sale_seq, $goods_seq, 'sale');
			if ($cnt > 0){
				return 0;
			}
		}

		$sale_type_field	= 'sale_price_type';
		$sale_price_field	= 'sale_price';
		if($benifit_type == 'suboption'){
			$sale_type_field	= 'sale_option_price_type';
			$sale_price_field	= 'sale_option_price';
		}

		if( $data[$sale_type_field] == 'PER' && $data[$sale_price_field] && $goods_price ){
			$member_sale = ( $goods_price * $data[$sale_price_field] );
			$is_calculate = true;
			if ($this->config_system['cutting_sale_use']=='none' || !empty($this->config_system['cutting_sale_price'])) $is_calculate = false;

			if( $is_calculate && $this->config_system['cutting_price'] != 'none' ){
				$member_sale = $member_sale / ( $this->config_system['cutting_price'] * 100);
				$member_sale = get_cutting_price($member_sale);
				$member_sale = $member_sale * $this->config_system['cutting_price'];
			}else{
				$member_sale = $member_sale / 100;
				$member_sale = get_cutting_price($member_sale);
			}
		}else if( $data[$sale_type_field] != 'PER' && $data[$sale_price_field] && $goods_price ){
			$member_sale = $data[$sale_price_field];
		}

		if($data['sale_use'] == 'Y' && $tot_price){
			if( $data['sale_limit_price'] > $tot_price  ){
				$member_sale = 0;
			}
		}

		// 등급할인가 절삭
		$member_sale = get_price_point($member_sale);

		return $member_sale;
	}

	/* 회원 마일리지 차감 */
	public function set_member_emoney($emoney,$member_seq){
		$this->db->query('update fm_member set emoney=emoney-? where member_seq=?',array($emoney,$member_seq));
	}

	public function set_member_cash($emoney,$member_seq){
		$this->db->query('update fm_member set cash=cash-? where member_seq=?',array($emoney,$member_seq));
	}

	/* 회원 휴대폰인증 횟수저장 :: 2016-04-19 lwh */
	public function set_member_authphone($cnt,$member_seq){
		$this->db->query("update fm_member set phone_auth='".$cnt."|".date('Ymd')."' where member_seq=?",array($member_seq));
	}

	public function set_withdrawal_admin($params){
		$mdata = $this->membermodel->get_member_data($params['member_seq']);

		if($mdata['status'] && $mdata['status'] != 'withdrawal') {
			$data = filter_keys($params, $this->db->list_fields('fm_member_withdrawal'));
			$result = $this->db->insert('fm_member_withdrawal', $data);

			### member_update
			$member['password']			= "";
			$member['user_name']		= "";
			$member['group_seq']		= "1";		//회원탈퇴시 일반그룹으로 변경. #11649
			$member['email']			= "";
			$member['phone']			= "";
			$member['cellphone']		= "";
			$member['zipcode']			= "";
			$member['address_type']		= "";
			$member['address']			= "";
			$member['address_street']	= "";
			$member['address_detail']	= "";
			$member['birthday']			= "";
			$member['auth_code']		= "";
			$member['auth_vno']			= "";
			$member['status']			= "withdrawal";
			$member['auth_vno']			= "";
			$member['auth_code']		= "";
			$member['auth_type']		= "";

			$mbdata = $this->get_member_seq_only($params['member_seq']);
			if($mbdata['rute'] != 'none'){//SNS회원인 경우
				// 네이버일 경우 고유키를 아이디로 사용중이라면 치환
				if(isset($mbdata['sns_n']) && $mbdata['sns_n'] == $mbdata['userid']){
					$mbdata['userid'] = $mbdata['conv_sns_n'];
				}
				$member['userid']			= "withdrawal_".$mbdata['userid'];
			}

			$ruteloop = memberrute('', '', 'array');
			foreach($ruteloop as $sns=>$snsv){
				if( $sns != 'none' ) $member[$sns]		= "";
			}

			$result = $this->db->update('fm_member', $member, array('member_seq'=>$params['member_seq']));

			$this->db->delete('fm_member_business', array('member_seq'=>$params['member_seq']));//기업회원 삭제
			$this->db->delete('fm_membersns', array('member_seq'=>$params['member_seq']));//SNS회원 삭제
			$this->db->delete('fm_delivery_address', array('member_seq'=>$params['member_seq']));//배송지 삭제

			$this->db->delete('fm_member_dr', array('member_seq'=>$params['member_seq']));//휴면백업삭제
			$this->db->delete('fm_member_business_dr', array('member_seq'=>$params['member_seq']));//기업회원삭제
			$this->db->delete('fm_membersns_dr', array('member_seq'=>$params['member_seq']));//SNS회원 삭제
			$this->db->delete('fm_delivery_address_dr', array('member_seq'=>$params['member_seq']));//배송지 삭제

			// o2o 정보 삭제
			$this->load->library('o2o/o2oservicelibrary');
			$this->o2oservicelibrary->del_member_o2o(array('member_seq'=>$params['member_seq']));

			// 개인정보 보호 주문 분리
			$this->load->library('personalinfolibrary');
			if($this->personalinfolibrary->isPersonalInfoUse()) {
				$mbdata = $this->get_member_seq_only($params['member_seq']);
				$this->personalinfolibrary->separatePersonalInfo([$mbdata]);
			}
		}

		return $result;
	}


	public function admin_search_list($sc){
		$sc['nolimit']='y';
		return $this->admin_member_list($sc);
	}


	public function emoney_insert($params, $member_seq){


		$data = filter_keys($params, $this->db->list_fields('fm_emoney'));
		$data['member_seq']		= !empty($member_seq)?$member_seq:'';
		$data['regist_date']	= date("Y-m-d H:i:s");
		$data['emoney']			= get_cutting_price($data['emoney']);
		if($params['gb']=='plus'){
			$data['remain'] = $data['emoney'];
		}
		$result = $this->db->insert('fm_emoney', $data);
		$data['emoney_seq'] = $this->db->insert_id();

		$sql = "select emoney from fm_member where member_seq = '{$member_seq}'";
		$query = $this->db->query($sql);
		$info = $query->result_array();

		$emoney = ($params['gb']=='plus') ? $info[0]['emoney']+$params['emoney'] : $info[0]['emoney']-$params['emoney'];
		if($emoney<0) $emoney = 0;

		###
		if($params['gb']=='minus'){
			$this->minus_pocess('emoney', $data);
		}

		$this->db->query('update fm_member set emoney=? where member_seq=?',array($emoney,$member_seq));

	}

	public function cash_insert($params, $member_seq){


		$reserve = config_load('reserve');
		if($reserve['cash_use']=='N' && $params['gb']=='plus'){
			return;
		}

		$data = filter_keys($params, $this->db->list_fields('fm_cash'));
		$data['member_seq']		= !empty($member_seq)?$member_seq:'';
		$data['regist_date']	= date("Y-m-d H:i:s");
		if($params['gb']=='plus'){
			$data['remain'] = $data['cash'];
		}
		$result = $this->db->insert('fm_cash', $data);
		$data['cash_seq'] = $this->db->insert_id();

		$sql = "select cash from fm_member where member_seq = '{$member_seq}'";
		$query = $this->db->query($sql);
		$info = $query->result_array();

		$cash = ($params['gb']=='plus') ? $info[0]['cash']+$params['cash'] : $info[0]['cash']-$params['cash'];
		if($cash<0) $cash = 0;

		###
		/* 유효기간이 없으므로 사용 안함
 		if($params['gb']=='minus'){
			$this->minus_pocess('cash', $data);
		}
		*/
		$this->db->query('update fm_member set cash=? where member_seq=?',array($cash,$member_seq));
	}


	public function minus_pocess($type='emoney', $params){


		### DEFAULT
		$today		= date("Y-m-d");
		$money		= $params[$type];
		$table	= "fm_".$type;
		$seq	= $type."_seq";

		### INSERT DATA
		$used['used']		= $type;
		$used['parent_seq'] = $params[$seq];
		$used['type']		= $params['type'];
		$used['ordno']		= $params['ordno'];
		$used['memo']		= $params['memo'];
		$used['regist_date'] = date("Y-m-d H:i:s");

		### LIMITED EMONEY
		$sql = "select * from {$table} where member_seq = '{$params['member_seq']}' and gb = 'plus' and limit_date >= '{$today}' and remain > 0 order by limit_date asc";
		$query = $this->db->query($sql);
		foreach($query->result_array() as $v){
			if($money > 0){
				if($money <= $v['remain']){
					$v['remain']	= $v['remain'] - $money;
					$used['remain']		= $v['remain'];
					$used['used_amt']	= $money;
					$money			= 0;
				}else{
					$money			= $money - $v['remain'];
					$used['remain']		= 0;
					$used['used_amt']	= $v['remain'];
				}
				$used['used_seq']	= $v[$seq];
				$result = $this->db->insert("fm_used_log", $used);

				$this->db->query("update {$table} set remain = '{$used['remain']}' where ".$seq." = '".$v[$seq]."' ");
			}
		}

		if($money){
			$sql = "select * from {$table} where member_seq = '{$params['member_seq']}' and gb = 'plus' and (limit_date = '' OR limit_date is null) and remain > 0 order by {$seq} asc";
			$query = $this->db->query($sql);
			foreach($query->result_array() as $v){
				if($money > 0){
					if($money <= $v['remain']){
						$v['remain']		= $v['remain'] - $money;
						$used['remain']		= $v['remain'];
						$used['used_amt']	= $money;
						$money				= 0;
					}else{
						$money				= $money - $v['remain'];
						$used['remain']		= 0;
						$used['used_amt']	= $v['remain'];
					}
					$used['used_seq']	= $v[$seq];
					$result = $this->db->insert("fm_used_log", $used);

					$this->db->query("update {$table} set remain = '{$used['remain']}' where ".$seq." = '".$v[$seq]."' ");
				}
			}
		}
	}

	public function point_insert($params, $member_seq){


		$reserve = config_load('reserve');
		if($reserve['point_use']=='N'){
			return;
		}

		$data = filter_keys($params, $this->db->list_fields('fm_point'));
		$data['member_seq']		= !empty($member_seq)?$member_seq:'';
		$data['regist_date']	= date("Y-m-d H:i:s");
		$data['point']			= get_cutting_price($data['point']);
		if($params['gb']=='plus'){
			$data['remain'] = $data['point'];
		}
		$result = $this->db->insert('fm_point', $data);
		$data['point_seq'] = $this->db->insert_id();

		$sql = "select point from fm_member where member_seq = '{$member_seq}'";
		$query = $this->db->query($sql);
		$info = $query->result_array();

		$emoney = ($params['gb']=='plus') ? $info[0]['point']+$params['point'] : $info[0]['point']-$params['point'];
		if($emoney<0) $emoney = 0;

		###
		if($params['gb']=='minus'){
			$this->minus_pocess('point', $data);
		}
		$this->db->query('update fm_member set point=? where member_seq=?',array($emoney,$member_seq));
	}



	public function emoney_list($sc) {


		$key = get_shop_key();

		### DATE
		if( !empty($sc['sdate']) && !empty($sc['edate'])){
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$sqlWhereClause.=" and regist_date BETWEEN '{$start_date}' and '{$end_date}' ";

			$subWhere[] = "limit_date BETWEEN '{$start_date}' and '{$end_date}'";
		}
		## 유효기간 2014-07-23 (개인맞춤형알림에서 사용)
		if( !empty($sc['limit_sdate']) && !empty($sc['limit_edate'])){
			$start_date = $sc['limit_sdate'].' 00:00:00';
			$end_date	= $sc['limit_edate'].' 23:59:59';
			$sqlWhereClause.=" and limit_date BETWEEN '{$start_date}' and '{$end_date}' ";
		}

		if( !empty($sc['gb']) ) {
			$sqlWhereClause .= " AND gb in ('".implode("','",$sc['gb'])."') ";
		}

		$sqlWhereClause .= " AND type != 'limitDate_minus'";
		$subWhere[] = "`type` != 'limitDate_minus' AND member_seq = '{$sc[member_seq]}'";

		$sql = "select
				emoney_seq,
				member_seq,
				type,
				ordno,
				gb,
				emoney,
				memo,
				memo_lang,
				regist_date,
				goods_review,
				manager_seq,
				limit_date,
				remain,
				goods_review_parent,
				emoney_use,
				'' as contents
			from
				fm_emoney
			where 1 ".$sqlWhereClause;
		$sql .= " AND member_seq = '{$sc[member_seq]}' ";

		if( in_array('minus',$sc['gb']) || empty($sc['gb']) ) {
			$today = date('Y-m-d');
		$sql2 = "
		select
			'' as emoney_seq,
			member_seq,
			'limitDate_minus' as `type`,
			'' as ordno,
			'minus' as gb,
			remain as emoney,
			'기간만료로 마일리지 차감' as memo,
			'' as memo_lang,
			concat(limit_date,' 00:00:00')  as regist_date,
			'' as goods_review,
			'' as manager_seq,
			'' as limit_date,
			0 as remain,
			'' as goods_review_parent,
			'none' as emoney_use,
			'' as contents
			from
			(
				select
					member_seq,
					limit_date,
					sum(remain) remain
				from fm_emoney where remain>0 and limit_date<'$today' and limit_date AND ".implode(' AND ',$subWhere)."
				group by limit_date
			) B";
			$sql = $sql." UNION ".$sql2;
		}

		$query = "select * from (".$sql.") A";
		$query .= " order by A.regist_date desc";
		$limit = " limit {$sc['page']}, {$sc['perpage']} ";

		$res = $this->db->query($query.$limit);
		$data['result'] = $res->result_array();

		// 다국어 문자열 구성을 위해 memo & contents 재정의
		foreach($data['result'] as &$datarow){
			$datarow['memo'] = $this->make_str_for_getAlert($datarow['memo'],$datarow['memo_lang']);

			// contents 재조합
			if($datarow['type']=="order"){
				$datarow['contents'] = getAlert("mp223")." ".$datarow['ordno'];     // 주문번호
			}elseif($datarow['type']=="cancel"){
				$datarow['contents'] = getAlert("mp224")." ".$datarow['ordno'];     // 복원
			}elseif($datarow['type']=="refund"){
				$datarow['contents'] = getAlert("mp225")." ".$datarow['ordno'];     // 환불
			}elseif($datarow['type']=="join"){
				$datarow['contents'] = getAlert("mp227");       // 회원가입
			}elseif($datarow['type']=="bookmark"){
				$datarow['contents'] = getAlert("mp231");       // 즐겨찾기
			}elseif($datarow['type']=="joincheck"){
				$datarow['contents'] = getAlert("mp226");       // 출석체크
			}elseif($datarow['type']=="goods_review" || strpos($datarow['type'], "goods_review_") !== false){
				$datarow['contents'] = getAlert("mp228");		// 상품후기
			}elseif(strpos($datarow['type'], "recommend_") !== false){
				$datarow['contents'] = getAlert("mp229");		// 추천하기
			}elseif(strpos($datarow['type'], "invite_") !== false){
				$datarow['contents'] = getAlert("mp230");		// 초대하기
			}elseif($datarow['type']=="limitDate_minus"){
				$datarow['contents'] = getAlert("mp290");		// 기간만료
			}
		}

		$res = $this->db->query($query);
		$data['count'] = $res->num_rows();

		return $data;
	}



	public function point_list($sc) {


		$key = get_shop_key();

		### DATE
		if( !empty($sc['sdate']) && !empty($sc['edate'])){
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$sqlWhereClause.=" and regist_date BETWEEN '{$start_date}' and '{$end_date}' ";

			$subWhere[] = "limit_date BETWEEN '{$start_date}' and '{$end_date}'";
		}
		## 유효기간 2014-07-23 (개인맞춤형알림에서 사용)
		if( !empty($sc['limit_sdate']) && !empty($sc['limit_edate'])){
			$start_date = $sc['limit_sdate'].' 00:00:00';
			$end_date	= $sc['limit_edate'].' 23:59:59';
			$sqlWhereClause.=" and limit_date BETWEEN '{$start_date}' and '{$end_date}' ";
		}

		if( !empty($sc['gb']) ) {
			$sqlWhereClause .= " AND gb in ('".implode("','",$sc['gb'])."') ";
		}

		$sqlWhereClause .= " AND type != 'limitDate_minus'";
		$subWhere[] = "`type` != 'limitDate_minus' AND member_seq = '{$sc[member_seq]}'";

		$sql = "select
				point_seq,
				member_seq,
				type,
				ordno,
				gb,
				point,
				memo,
				memo_lang,
				regist_date,
				goods_review,
				manager_seq,
				limit_date,
				remain,
				goods_review_parent,
				point_use,
				'' as contents
			from
				fm_point
			where 1 ".$sqlWhereClause;

		###
		$sql .= " AND member_seq = '{$sc[member_seq]}' ";


		if( in_array('minus',$sc['gb']) || empty($sc['gb']) ) {
			$today = date('Y-m-d');
			$sql2 = "
			select
				'' as point_seq,
				member_seq,
				'limitDate_minus' as `type`,
				'' as ordno,
				'minus' as gb,
				remain as point,
				'기간만료로 포인트 차감' as memo,
				'' as memo_lang,
				concat(limit_date,' 00:00:00')  as regist_date,
				'' as goods_review,
				'' as manager_seq,
				'' as limit_date,
				0 as remain,
				'' as goods_review_parent,
				'none' as point_use,
				'' as contents
				from
				(
					select
						member_seq,
						limit_date,
						sum(remain) remain
					from fm_point where remain>0 and limit_date<'$today' and limit_date AND ".implode(' AND ',$subWhere)."
					group by limit_date
				) B";
				$sql = $sql." UNION ".$sql2;
		}

		$query = "select * from (".$sql.") A";
		$query .= " order by A.regist_date desc";
		$limit = " limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($query.$limit);
		$data['result'] = $query->result_array();

		// 다국어 문자열 구성을 위해 필드 재정의
		foreach($data['result'] as &$datarow){
			$datarow['memo'] = $this->make_str_for_getAlert($datarow['memo'],$datarow['memo_lang']);

			// contents 재조합
			if($datarow['type']=="order"){
				$datarow['contents'] = getAlert("mp223")." ".$datarow['ordno'];     // 주문번호
			}elseif($datarow['type']=="cancel"){
				$datarow['contents'] = getAlert("mp224")." ".$datarow['ordno'];     // 복원
			}elseif($datarow['type']=="refund"){
				$datarow['contents'] = getAlert("mp225")." ".$datarow['ordno'];     //  환불
			}elseif($datarow['type']=="join"){
				$datarow['contents'] = getAlert("mp227");       // 회원가입
			}elseif($datarow['type']=="goods_review" || strpos($datarow['type'], "goods_review_") !== false){
				$datarow['contents'] = getAlert("mp228");		// 상품후기
			}elseif($datarow['type']=="joincheck"){
				$datarow['contents'] = getAlert("mp226");       // 출석체크
			}elseif(strpos($datarow['type'], "recommend_") !== false){
				$datarow['contents'] = getAlert("mp229");		// 추천하기
			}elseif(strpos($datarow['type'], "invite_") !== false){
				$datarow['contents'] = getAlert("mp230");		// 초대하기
			}elseif($datarow['type']=="bookmark"){
				$datarow['contents'] = getAlert("mp231");       // 즐겨찾기
			}elseif($datarow['type']=="promotioncode"){
				$datarow['contents'] = getAlert("mp232")." ".$datarow['promotioncode'];     // 프로모션코드
			}
		}

		$query = $this->db->query($sql);
		$data['count'] = $query->num_rows();

		return $data;
	}

	public function cash_list($sc) {


		$key = get_shop_key();

		### DATE
		if( !empty($sc['sdate']) && !empty($sc['edate'])){
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$sqlWhereClause.=" and A.regist_date BETWEEN '{$start_date}' and '{$end_date}' ";
		}

		if( !empty($sc['gb']) ) {
			$sqlWhereClause .= " AND A.gb in ('".implode("','",$sc['gb'])."') ";
		}

		$sql = "select
				A.*,'' AS contents
			from
				fm_cash A
			where 1 ".$sqlWhereClause;

		###
		$sql .= " AND A.member_seq = '{$sc[member_seq]}' ";
		$sql .=" order by A.cash_seq desc";
		$limit =" limit {$sc['page']}, {$sc['perpage']} ";

		$query = $this->db->query($sql.$limit);
		$data['result'] = $query->result_array();


		// 다국어 문자열 구성을 위해 필드 재정의
		foreach($data['result'] as &$datarow){

			// 인출인 경우 memo 다국어처리 건너뜀 :: 2019-08-23 pjw
			if($datarow['type'] != "withdraw") $datarow['memo'] = $this->make_str_for_getAlert($datarow['memo'],$datarow['memo_lang']);

			// contents 재조합
			// withdraw (인출) 추가 :: 2019-08-23 pjw
			if($datarow['type']=="order"){
				$datarow['contents'] = getAlert("mp223")." ".$datarow['ordno'];     // 주문번호
			}elseif($datarow['type']=="cancel"){
				$datarow['contents'] = getAlert("mp224")." ".$datarow['ordno'];     // 복원
			}elseif($datarow['type']=="refund"){
				$datarow['contents'] = getAlert("mp225")." ".$datarow['ordno'];     // 환불
			}elseif($datarow['type']=="join"){
				$datarow['contents'] = getAlert("mp227");       // 회원가입
			}elseif($datarow['type']=="bookmark"){
				$datarow['contents'] = getAlert("mp231");       // 즐겨찾기
			}elseif($datarow['type']=="withdraw"){
				// 인출 계좌 정보 가공


				$account_info			= json_decode($datarow['account_info']);
				$bank_info				= code_load('bankCode');
				foreach($bank_info as $bank) {
					if($bank['codecd'] == $account_info->bank) $bank_name = $bank['value'];
				}

				$account_info_text		= '인출<br/>'.$bank_name.'<br/>'.$account_info->account.'<br/>'.$account_info->depositor;
				$datarow['contents']	= $account_info_text;
			}
		}

		$query = $this->db->query($sql);
		$data['count'] = $query->num_rows();

		return $data;
	}

	//초대내역
	public function recommend_list($sc) {

		$sql = "select SQL_CALC_FOUND_ROWS * from fm_member where 1";

		### DATE
		if( !empty($sc['sdate']) && !empty($sc['edate'])){
			$start_date = $sc['sdate'].' 00:00:00';
			$end_date = $sc['edate'].' 23:59:59';
			$sql.=" and regist_date BETWEEN '{$start_date}' and '{$end_date}' ";
		}

		if ( $sc['recommend'] ) $sql .= " and recommend = '".$sc['recommend']."' ";
		//if ( $sc['member_seq'] ) $sql .= " and member_seq = '".$sc['member_seq']."' ";

		$sql .=" order by member_seq desc ";
		$sql .=" limit {$sc['page']}, {$sc['perpage']} ";
		$query = $this->db->query($sql);
		$data['result'] = $query->result_array();

		//총건수
		$sql = "SELECT FOUND_ROWS() as COUNT";
		$query_count = $this->db->query($sql);
		$res_count= $query_count->result_array();
		$data['count'] = $res_count[0]['COUNT'];

		return $data;
	}

	//추천하기 총건수
	public function recommend_total_count($sc)
	{
		$sql = "select member_seq from fm_member where 1  and recommend = '".$sc['recommend']."' ";
		$query = $this->db->query($sql);
		return $query->num_rows();
	}




	// 총건수 탈퇴회원제외
	public function get_item_total_count($sc = null)
	{
		$bind = array();
		$sql = 'select  SQL_CALC_FOUND_ROWS member_seq from fm_member  where status != "withdrawal" ';
		if($sc){
			### groups array()
			if( !empty($sc['groupsar']) ){
				$groups = implode("','",$sc['groupsar']);
				$sql .= " and group_seq in ('".$groups."')";
			}
			// 검색 조건 추가
			if(!empty($sc['auth_code'])){
				$sql .= " and auth_code = ? ";
				$bind[] = $sc['auth_code'];
			}
		}
		 $query = $this->db->query($sql, $bind);
		//return mysqli_affected_rows();
		return $query->num_rows();
	}

	// 총 건수 휴면 탈퇴 회원 제외 kmj
	public function get_member_total_count($sc = null)
	{
		$sql = "select count(member_seq) as cnt from fm_member  where (status != 'withdrawal' and status != 'dormancy')";
		if($sc){
			### groups array()
			if( !empty($sc['groupsar']) ){
				$groups = implode("','",$sc['groupsar']);
				$sql .= " and group_seq in ('".$groups."')";
			}
		}
 		$res = $this->db->query($sql)->result_array();
		return $res[0]['cnt'];
	}

	public function get_group_for_goods($price=0,$goods_seq,$r_category_code,$group_seq='')
	{
		if(!$price) $price = 0;
		// 이 상품의 최고 할인율을 가진 회원등급
		$where_category = "'".implode("','",$r_category_code)."'";
		$sale_query = "
		select
			if(sale_price_type='PER',floor({$price}*sale_price/100),sale_price) sale, if(sale_price_type='PER',sale_price,0) sale_rate,group_seq
		from fm_member_group
		where
			( select count(*) from fm_member_group_issuecategory where group_seq = fm_member_group.group_seq and type='emoney' and type='sale' and category_code in($where_category) )=0
			and ( select count(*) from fm_member_group_issuegoods where group_seq = fm_member_group.group_seq and type='emoney' and type='sale' and goods_seq = '$goods_seq' )=0";

		$reserve_query = "
		select
		if(point_price_type='PER',floor({$price}*point_price/100),point_price) reserve, if(point_price_type='PER',point_price,0) reserve_rate,group_seq
		from fm_member_group
		where
			( select count(*) from fm_member_group_issuecategory where group_seq = fm_member_group.group_seq and type='emoney' and category_code in($where_category) )=0
			and ( select count(*) from fm_member_group_issuegoods where group_seq = fm_member_group.group_seq and type='emoney' and type='emoney' and goods_seq = '$goods_seq' )=0";

		if($group_seq){
			$sale_query .=  " and group_seq='$group_seq'";
			$reserve_query .=  " and group_seq='$group_seq'";
		}

		$query = "
		select sale,sale_rate,reserve,reserve_rate,g.* from fm_member_group g
		left join ($sale_query) s on g.group_seq=s.group_seq
		left join ($reserve_query) r on g.group_seq=r.group_seq
		order by sale desc,reserve desc,group_seq desc limit 1";
		$query = $this->db->query($query);
		$data_member_group = $query->row_array();
		return $data_member_group;
	}
	//가입형식 추가 타입별 속성값 가져오기
	public function get_labelitem_type($data, $msdata){

		switch($data['label_type'])
			{

				case "text" :

					for ($j=0; $j<$data['label_value']; $j++) {
						if ($j > 0) $inputBox .= "<br/>";
						$label_value = ($msdata[$j]) ? $msdata[$j]['label_value'] : '';
						$size = ( $this->mobileMode || $this->storemobileMode )?" ":"size='70' ";
						$inputBox .= '<input type="text" name="label['.$data['joinform_seq'].'][value][]" class="text_'.$data['joinform_seq'].'" value="'.$label_value.'" '.$size.' style="width:100%;border:1px solid #dbdbdb; margin:1px 0; padding:2px;">';
					}
				break;

				case "select" :
					$inputBox .= "<table class='selectLabelSet'><tr><td style='padding:0px;min-height:0;'>";
					$labelArray = explode("|", $data['label_value']);
					$labelCount = count($labelArray)-1;
					$labelindexBox = '';
					$label_value = ($msdata[0]) ? $msdata[0]['label_value'] : '';
					$labelindexBox .= '<option value=""   childs="">선택해주세요.</option>';
					for ($j=0; $j<$labelCount; $j++)
					{
						$labelsubArray = explode(";", $labelArray[$j]);
						$selected = ($labelsubArray[0] == $label_value) ? "selected" : "";
						$labelindexBox .= '<option value="'. $labelsubArray[0] .'" '. $selected .' childs="'.implode(";",array_slice($labelsubArray,1)).'">'. $labelsubArray[0] .'</option>';
					}
					if($msdata[0]){
						$labelsubBox = '<input type="hidden" name="subselect['.$data['joinform_seq'].'] id="subselect_'.$data['joinform_seq'].'" value="'.$msdata[0]['label_sub_value'].'" joinform_seq="'.$data['joinform_seq'].'" class="hiddenLabelDepth">';
					}

					$inputBox .= '<select name="label['.$data['joinform_seq'].'][value][]" id="label_'.$data['joinform_seq'].'" joinform_seq="'.$data['joinform_seq'].'" style="height:30px; line-height:16px;" class="selectLabelDepth1">';
					$inputBox .= $labelindexBox;
					$inputBox .= '</select>';
					$inputBox .= '</td><td style="padding:0px;min-height:0;"><div class="selectsubDepth hide">';
					$inputBox .= '<select name="labelsub['.$data['joinform_seq'].'][value][]" id="labelsub_'.$data['joinform_seq'].'" joinform_seq="'.$data['joinform_seq'].'" style="height:30px; line-height:16px;" class="selectLabelDepth2">';
					$inputBox .= '</select></div></td></tr></table>';
					$inputBox .= $labelsubBox;

				break;

				case "textarea" :

						switch($data['label_value'])
						{
							case "large" :		$height = "300px";	break;
							case "medium" :		$height = "200px";	break;
							case "small" :		$height = "100px";	break;
						}
						$label_value = ($msdata[0]) ? $msdata[0]['label_value'] : '';
						$inputBox .= '<textarea name="label['.$data['joinform_seq'].'][value][]" id="label_'.$data['joinform_seq'].'" style="width:90%; height:'. $height .';">'.$label_value.'</textarea>';

				break;

				case "checkbox" :
					$labelArray = explode("|", $data['label_value']);
					$labelCount = count($labelArray)-1;

					if($msdata[0])$cmsdata=count($msdata);
					for ($k=0; $k<$cmsdata; $k++) {
						$ckdata[] = $msdata[$k]['label_value'];
					}

					for ($j=0; $j<$labelCount; $j++) {
						if (is_array($msdata)) {
							$checked = (in_array($labelArray[$j], $ckdata )) ? "checked" : "";
						}
						if ($j > 0) $inputBox .= " ";
						$inputBox .= '<label style="margin-right:5px"><input type="checkbox" name="label['.$data['joinform_seq'].'][value][]" class="null labelCheckbox_'.$data['joinform_seq'].'" value="'. $labelArray[$j] .'" '. $checked .'> '. $labelArray[$j].'</label>';
					}
				break;

				case "radio" :
					$labelArray = explode("|", $data['label_value']);
					$labelCount = count($labelArray)-1;

					for ($j=0; $j<$labelCount; $j++) {

						if (is_array($msdata[0])) {
							$checked = ($labelArray[$j] == $msdata[0]['label_value']) ? "checked" : "";
						}
						if ($j > 0) $inputBox .= " ";

						$inputBox .= '<label style="margin-right:5px"><input type="radio" name="label['.$data['joinform_seq'].'][value][]" class="null" value="'. $labelArray[$j] .'" '. $checked .'> '. $labelArray[$j].'</label>';
					}
				break;
			}

		return $inputBox;
	}

	//회원 추가가입정보가져오기
	public function get_subinfo($mseq,$form_seq){

		$query = $this->db->get_where('fm_member_subinfo', array('member_seq'=>$mseq, 'joinform_seq'=>$form_seq));
		$result = $query -> result_array();
		return $result;
	}

	###
	public function get_order_count($member_seq){
		$sql = "SELECT count(order_seq) as cnt, sum(settleprice) as sums FROM fm_order WHERE step>=25 and step<=75 and member_seq={$member_seq}";
		$query = $this->db->query($sql);
		$order = $query->result_array();

		$data['cnt'] =  $order[0]['cnt'];
		$data['sum'] =  $order[0]['sums'];
		return $data;
	}

	public function get_emoney($member_seq,$type='emoney')
	{
		$today = date('Y-m-d');
		$table = 'fm_'.$type;
		$query = "select sum(ifnull(remain,0)) emoney from {$table} where member_seq=? and gb='plus' and (limit_date >= ? OR limit_date is null OR limit_date='')";
		$query = $this->db->query($query,array($member_seq,$today));
		$row = $query->row_array();
		// 이머니 리턴되도록 수정 :: 2018-03-22 lkh
		if( !isset($row['emoney']) ){
			$emoney = 0; // 데이터 없는 경우 null로 반환되어 0으로 변경 2018-03-08
		}else{
			$emoney = $row['emoney'];
		}
		return $emoney;
	}

	// 회원 예치금 가져오기 추가 :: 2019-08-23 pjw
	public function get_cash($member_seq, $type='cash')
	{
		$today = date('Y-m-d');
		$table = 'fm_member';
		$query = "select ".$type." from {$table} where member_seq=? ";
		$query = $this->db->query($query,array($member_seq));
		$row = $query->row_array();

		if( !isset($row[$type]) ){
			$amount_result = 0; // 데이터 없는 경우 null로 반환되어 0으로 변경 2018-03-08
		}else{
			$amount_result = $row[$type];
		}

		return $amount_result;
	}

	public function get_replacetext($m=null)
	{
		$arr_replace1 = parse_ini_file(APPPATH."config/_replace_text.ini", true);
		if($m == "curation"){
			$arr_replace2	= parse_ini_file(APPPATH."config/_replace_curation_text.ini", true);
			$arr_replace	= array_merge($arr_replace1,$arr_replace2);
		}else{
			$arr_replace	= $arr_replace1;
		}

		if($m == "curation"){
			if($_GET['id'] == "personal_coupon"){
				unset($arr_replace['{userday}']);
				unset($arr_replace['{userbirthday}']);
				unset($arr_replace['{anniversary}']);
				unset($arr_replace['{go_item}']);
				unset($arr_replace['{mileage_rest}']);

			}else if($_GET['id'] == "personal_cart"){
				unset($arr_replace['{userday}']);
				unset($arr_replace['{userbirthday}']);
				unset($arr_replace['{anniversary}']);
				unset($arr_replace['{coupon_count}']);
				unset($arr_replace['{mileage_rest}']);

			}else if($_GET['id'] == "personal_membership"){
				unset($arr_replace['{userday}']);
				unset($arr_replace['{userbirthday}']);
				unset($arr_replace['{anniversary}']);
				unset($arr_replace['{go_item}']);
				unset($arr_replace['{coupon_count}']);
				unset($arr_replace['{mileage_rest}']);

			}else if($_GET['id'] == "personal_emoney"){
				unset($arr_replace['{userday}']);
				unset($arr_replace['{userbirthday}']);
				unset($arr_replace['{anniversary}']);
				unset($arr_replace['{go_item}']);
				unset($arr_replace['{coupon_count}']);
			}else if($_GET['id'] == "personal_timesale"){
				unset($arr_replace['{userday}']);
				unset($arr_replace['{userbirthday}']);
				unset($arr_replace['{anniversary}']);
				unset($arr_replace['{coupon_count}']);
				unset($arr_replace['{mileage_rest}']);
			}else if($_GET['id'] == "personal_deliveryconfirm"){
				unset($arr_replace['{userday}']);
				unset($arr_replace['{userbirthday}']);
				unset($arr_replace['{anniversary}']);
				unset($arr_replace['{go_item}']);
				unset($arr_replace['{coupon_count}']);
				unset($arr_replace['{mileage_rest}']);
			}else if($_GET['id'] == "personal_birthday"){
				unset($arr_replace['{userday}']);
				unset($arr_replace['{anniversary}']);
				unset($arr_replace['{go_item}']);
				unset($arr_replace['{coupon_count}']);
				unset($arr_replace['{mileage_rest}']);
			}else if($_GET['id'] == "personal_anniversary"){
				unset($arr_replace['{userday}']);
				unset($arr_replace['{userbirthday}']);
				unset($arr_replace['{go_item}']);
				unset($arr_replace['{coupon_count}']);
				unset($arr_replace['{mileage_rest}']);
			}
		}

		return $arr_replace;
	}

	public function get_replacetext_other($mode){

		switch($mode){
			case	'sorder_draft' :
				case	'sorder_edraft' :
				case	'sorder_cancel_draft' :
				case	'sorder_cancel_edraft' :
				case	'sorder_modify_draft' :
				case	'sorder_modify_edraft' :
				$arr_replace['{shopName}']			= array("val" => "base", "key" => "shopName", "type" => "text", "memo" => "쇼핑몰 이름(설정 > 일반정보)");
				$arr_replace['{shopDomain}']		= array("val" => "base", "key" => "shopDomain", "type" => "text", "memo" => "쇼핑몰 도메인(설정 > 일반정보)");
				$arr_replace['{trader_name}']		= array("val" => "", "key" => "trader_name", "type" => "text", "memo" => "발주 거래처명");
				$arr_replace['{sorder_code}']		= array("val" => "", "key" => "sorder_code", "type" => "text", "memo" => "발주번호");
				$arr_replace['{sorder_time}']		= array("val" => "", "key" => "sorder_time", "type" => "text", "memo" => "발주일시");
				$arr_replace['{sorder_item_cnt}']	= array("val" => "", "key" => "sorder_item_cnt", "type" => "text", "memo" => "발주종수");
				$arr_replace['{total_ea}']			= array("val" => "", "key" => "total_ea", "type" => "text", "memo" => "발주수량");
				$arr_replace['{sorder_url}']		= array("val" => "", "key" => "sorder_url", "type" => "text", "memo" => "발주서상세URL");
				break;
		}

		return $arr_replace;

	}

	public function get_member_sale($where="", $field="*"){

		if($where){
			$where = " WHERE ".$where;
		}
		$sql = "SELECT ".$field." FROM fm_member_group_sale ".$where." order by sale_seq desc";

		$query = $this->db->query($sql);
		$row = $query->result_array();
		return $row;
	}

	public function member_sale_group_list(){
		//$this->db->order_by("group_seq","asc");
		$this->db->order_by("order_sum_price","asc");
		$this->db->order_by("order_sum_ea","asc");
		$this->db->order_by("order_sum_cnt","asc");
		$this->db->order_by("use_type","desc");

		$query = $this->db->get("fm_member_group");

		$returnArr[] = array('group_seq'=>"0", "group_name"=>"비회원");
		foreach ($query->result_array() as $row){
			$qry = "select count(member_seq) as count from fm_member where group_seq = '{$row['group_seq']}' and status != 'withdrawal'";
			$querys = $this->db->query($qry);
			$data = $querys->result_array();
			$row['count'] = $data[0]['count'];
			$returnArr[] = $row;
		}
		return $returnArr;
	}

	public function member_group_max(){
		$query = "select max(group_seq) max from fm_member_group";
		$query = $this->db->query($query);
		$data = $query->result_array();
		return (int) $data[0]['max'];
	}

	/* 주문건수/주문금액 일괄 업데이트 리스트 */
	public function member_cnt_batch_list($sc) {
		if(!isset($_GET['page']))$_GET['page'] = 1;
		$sql = "select member_seq from fm_member where rute != 'withdrawal' ";
		$sql .=" order by member_seq desc ";
		$result = select_page($sc['limitnum'],$_GET['page'],10,$sql,'');
		$result['page']['querystring'] = get_args_list();
		return $result;
	}



	/* 주문건수/주문금액/추천받은건수/초대한건수 일괄 업데이트 업데이트 */
	public function member_cnt_batch($member_seq) {
		if(!$member_seq) return;

		$query = "select
		( select sum( CONVERT(step75_count * 1, SIGNED)) from fm_member_order where member_seq=A.member_seq ) member_order_cnt,
		( select sum( CONVERT(step75_ea * 1, SIGNED) - CONVERT(refund_ea * 1, SIGNED) ) from fm_member_order where member_seq=A.member_seq ) member_order_goods_cnt,
		( select sum( CONVERT(step75_price * 1, SIGNED) - CONVERT(refund_price * 1, SIGNED) ) from fm_member_order where member_seq=A.member_seq ) member_order_price,
		( select count(member_seq) from fm_member where recommend=A.userid ) member_recommend_cnt,
		( select count(member_seq) from fm_memberinvite where member_seq=A.member_seq ) member_invite_cnt
		from fm_member A
		where A.member_seq =?";
		$query = $this->db->query($query,array($member_seq));
		$member_cnt = $query->row_array();

		$member_cnt['member_order_cnt']				= ($member_cnt['member_order_cnt']>0)?$member_cnt['member_order_cnt']:0;
		$member_cnt['member_order_goods_cnt']	= ($member_cnt['member_order_goods_cnt']>0)?$member_cnt['member_order_goods_cnt']:0;

		$member_cnt['member_order_price']			= ($member_cnt['member_order_price']>0)?$member_cnt['member_order_price']:0;
		$member_cnt['member_recommend_cnt']	= ($member_cnt['member_recommend_cnt']>0)?$member_cnt['member_recommend_cnt']:0;
		$member_cnt['member_invite_cnt']				= ($member_cnt['member_invite_cnt']>0)?$member_cnt['member_invite_cnt']:0;

			$this->db->where('member_seq', $member_seq);
		$result = $this->db->update('fm_member', array('member_order_cnt'=>$member_cnt['member_order_cnt'],'member_order_goods_cnt'=>$member_cnt['member_order_goods_cnt'],'member_order_price'=>$member_cnt['member_order_price'],'member_recommend_cnt'=>$member_cnt['member_recommend_cnt'],'member_invite_cnt'=>$member_cnt['member_invite_cnt']));

		return $result;
	}

	/* 주문건수/주문금액 일괄 업데이트 업데이트 @2013-06-19 */
	public function member_order_batch($member_seq) {
		if(!$member_seq) return;

		$mbupquery = "select
		( select sum( CONVERT(step75_count * 1, SIGNED)) from fm_member_order where member_seq=A.member_seq ) member_order_cnt,
		( select sum( CONVERT(step75_ea * 1, SIGNED) - CONVERT(refund_ea * 1, SIGNED) ) from fm_member_order where member_seq=A.member_seq ) member_order_goods_cnt,
		( select sum( CONVERT(step75_price * 1, SIGNED) - CONVERT(refund_price * 1, SIGNED) ) from fm_member_order where member_seq=A.member_seq ) member_order_price
		from fm_member A
		where A.member_seq =?";
		$mbup = $this->db->query($mbupquery,array($member_seq));
		$member_cnt = $mbup->row_array();

		$member_cnt['member_order_cnt']				= ($member_cnt['member_order_cnt']>0)?$member_cnt['member_order_cnt']:0;
		$member_cnt['member_order_goods_cnt']	= ($member_cnt['member_order_goods_cnt']>0)?$member_cnt['member_order_goods_cnt']:0;

		$member_cnt['member_order_price']			= ($member_cnt['member_order_price']>0)?$member_cnt['member_order_price']:0;

		$this->db->where('member_seq', $member_seq);
		$result = $this->db->update('fm_member', array('member_order_cnt'=>$member_cnt['member_order_cnt'],'member_order_goods_cnt'=>$member_cnt['member_order_goods_cnt'],'member_order_price'=>$member_cnt['member_order_price']));

		return $result;
	}


	/*  추천받은건수 업데이트 업데이트 */
	public function member_recommend_cnt($member_seq) {
		if(!$member_seq) return;
		$query = "select
			( select count(member_seq) from fm_member where recommend=A.userid ) member_recommend_cnt
			from fm_member A
			where A.member_seq =?";
			$query = $this->db->query($query,array($member_seq));
			$member_cnt = $query->row_array();
			$member_cnt['member_recommend_cnt']	= ($member_cnt['member_recommend_cnt']>0)?$member_cnt['member_recommend_cnt']:0;
			$this->db->where('member_seq', $member_seq);
			$result = $this->db->update('fm_member', array('member_recommend_cnt'=>$member_cnt['member_recommend_cnt']));

		return $result;
	}


	/* 초대한건수 업데이트 업데이트 */
	public function member_invite_cnt($member_seq) {
		if(!$member_seq) return;
		$query = "select
		( select count(member_seq) from fm_memberinvite where member_seq=A.member_seq ) member_invite_cnt
		from fm_member A
		where A.member_seq =?";
		$query = $this->db->query($query,array($member_seq));
		$member_cnt = $query->row_array();
		$member_cnt['member_invite_cnt']				= ($member_cnt['member_invite_cnt']>0)?$member_cnt['member_invite_cnt']:0;
		$this->db->where('member_seq', $member_seq);
		$result = $this->db->update('fm_member', array('member_invite_cnt'=>$member_cnt['member_invite_cnt']));

		return $result;
	}

	public function member_order($member_seq)
	{
		$query = "select sum(settleprice) step75_price,count(order_seq) step75_count,sum(opt_ea) opt_ea,
					sum(sub_ea) sub_ea,mon,
					sum(refund_price) refund_price,sum(refund_count) refund_count,sum(refund_ea) refund_ea
				from (
					select
					order_seq,
					settleprice,
					(select sum(step75) from fm_order_item_option so left join fm_order_item si on so.item_seq=si.item_seq where so.order_seq = o.order_seq and si.goods_type!='gift') opt_ea,
					ifnull((select sum(step75) from fm_order_item_suboption where order_seq = o.order_seq),0) sub_ea,
					substring((select shipping_date from fm_goods_export where order_seq=o.order_seq and shipping_date!='0000-00-00' and shipping_date is not null order by export_seq asc limit 1),1,7) mon,
					ifnull((select sum(refund_pg_price) from fm_order_refund where order_seq = o.order_seq),0) refund_price,
					if((select order_seq from fm_order_refund where order_seq = o.order_seq group by order_seq),1,0) refund_count,
					ifnull((select sum(ea) from fm_order_refund_item a,fm_order_refund b where a.refund_code=b.refund_code and b.order_seq = o.order_seq),0) refund_ea
					from fm_order o
					where step='75' and member_seq=? and (select shipping_date from fm_goods_export where order_seq=o.order_seq and shipping_date!='0000-00-00' and shipping_date is not null order by export_seq asc limit 1)>=?
				) t
				group by t.mon";
		$start = date('Y-m', strtotime('-1 month'))."-01 00:00:00";
		$query = $this->db->query($query,array($member_seq,$start));
		foreach($query->result_array() as $row){

			$row['mon'] = str_replace("-","",$row['mon']);
			$param = array();
			$query = "delete from fm_member_order where member_seq=? and month=?";
			$query = $this->db->query($query,array($member_seq,$row['mon']));
			$query = "insert into fm_member_order set step75_count=?,step75_price=?,step75_ea=?,
			refund_count=?,refund_price=?,refund_ea=?,
			member_seq=?,month=?";
			$param[] = $row['step75_count'];
			$param[] = $row['step75_price'];
			$param[] = $row['opt_ea']+$row['sub_ea'];
			$param[] = $row['refund_count'];
			$param[] = $row['refund_price'];
			$param[] = $row['refund_ea'];
			$param[] = $member_seq;
			$param[] = $row['mon'];
			$query = $this->db->query($query,$param);
		}
	}

	//2개월이전 주문건을 오늘일자로 배송완료처리시 주문건/주문금액이 업데이트 수동처리
	public function member_order_old_gabia($member_seq,$yearmonth)
	{
		if( strlen($yearmonth) != 7) return;
		$query = "select sum(settleprice) step75_price,count(order_seq) step75_count,sum(opt_ea) opt_ea,
					sum(sub_ea) sub_ea,mon,
					sum(refund_price) refund_price,sum(refund_count) refund_count,sum(refund_ea) refund_ea
				from (
					select
					order_seq,
					settleprice,
					(select sum(step75) from fm_order_item_option where order_seq = o.order_seq) opt_ea,
					ifnull((select sum(step75) from fm_order_item_suboption where order_seq = o.order_seq),0) sub_ea,
					substring(regist_date,1,7) mon,
					ifnull((select sum(refund_price+refund_emoney) from fm_order_refund where order_seq = o.order_seq),0) refund_price,
					if((select order_seq from fm_order_refund where order_seq = o.order_seq group by order_seq),1,0) refund_count,
					ifnull((select sum(ea) from fm_order_refund_item a,fm_order_refund b where a.refund_code=b.refund_code and b.order_seq = o.order_seq),0) refund_ea
					from fm_order o
					where step='75' and member_seq=? and regist_date>=?  and regist_date<=?
				) t
				group by t.mon";

		$start = date('Y-m', strtotime('-1 month '.  $yearmonth))."-01 00:00:00";
		$end = $yearmonth."-31 24:00:00";

		$query = $this->db->query($query,array($member_seq,$start,$end));
		//debug_var($this->db->last_query());
		foreach($query->result_array() as $row){
			$row['mon'] = str_replace("-","",$row['mon']);
			$param = array();
			$query = "delete from fm_member_order where member_seq=? and month=?";
			$query = $this->db->query($query,array($member_seq,$row['mon']));
			$query = "insert into fm_member_order set step75_count=?,step75_price=?,step75_ea=?,
			refund_count=?,refund_price=?,refund_ea=?,
			member_seq=?,month=?";
			$param[] = $row['step75_count'];
			$param[] = $row['step75_price'];
			$param[] = $row['opt_ea']+$row['sub_ea'];
			$param[] = $row['refund_count'];
			$param[] = $row['refund_price'];
			$param[] = $row['refund_ea'];
			$param[] = $member_seq;
			$param[] = $row['mon'];
			$query = $this->db->query($query,$param);
			//debug_var($this->db->last_query());
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

		$sql	= "select * from fm_certify_user where seq > 0 ".$addWhere;
		$query	= $this->db->query($sql);

		return $query->result_array();
	}

	// 자동 등급조정일 계산
	public function calculate_date($start_month,$chg_day,$chg_term,$chk_term,$keep_term,$setting=null){

		$this_year = ($setting && date('m') == 12 )? date('Y',strtotime('+1 years')):date('Y');
		$now_st_time = time();
		if( $chg_term == 1 ){
			for($i=1;$i<=12;$i+=$chg_term) $tmp_arr_period[] = $i;
		}else if( $chg_term < 6 ){
			$to_month = $start_month+12;
			for($i=$start_month;$i<$to_month;$i+=$chg_term) $tmp_arr_period[] = $i%12;
			sort($tmp_arr_period);
		}else if( $chg_term >= 6 ){
			$to_month = $start_month + ($chg_term * 4);
			for($i=$start_month;$i<$to_month;$i+=$chg_term){
				$tmp_arr_period[] = $i%12;
				$tmp_arr_year_period[] = (int) ($i/12);
			}
		}

		foreach($tmp_arr_period as $k=>$i){
			$cal_year = $this_year;
			if( $chg_term >= 6 ) $cal_year = $this_year + $tmp_arr_year_period[$k];

			$change_ts = mktime(0,0,0,$i,$chg_day,$cal_year);
			$change_date = date('Y-m-d',$change_ts);
			$result['chg_text'][] = $change_date;


			$cal_ts = strtotime('-1 month',$change_ts);
			$cal_start_ts = strtotime('-'.($chk_term-1).' month',$cal_ts);
			$cal_start_date = date('Y-m-01',$cal_start_ts);
			$result['chk_text_start'][] = $cal_start_date;
			$result['chk_text_end'][] = date('Y-m-t',$cal_ts);

			$cal_end_ts = strtotime('+'.$keep_term.' month',$change_ts);
			$result['keep_text_start'][] = $change_date;
			$result['keep_text_end'][] = date('Y-m-d',$cal_end_ts-24*3600);

			// 등급 말료일
			$result['keep_end'][] = date('Y-m-d',$cal_end_ts);

			if(!$next_grade_date && $now_st_time < $change_ts ){
				$next_grade_date = $change_date;
			}
		}

		$result['next_grade_date'] = $next_grade_date;
		return $result;
	}

	// 상위 등급 추출
	public function get_member_group_flow($sc){

		if	((empty($sc['order_sum_price']) || empty($sc['use_type']) ) && !empty($sc['group_seq'])){
			$sql					= "select * from fm_member_group "
									. "where group_seq = '".$sc['group_seq']."' ";
			$query					= $this->db->query($sql);
			$currentGroup			= $query->row_array();
			$result['currentGroup']	= $currentGroup;
			$sc["use_type"]			= 'NORMAL';
			if(in_array($currentGroup["use_type"], array('AUTO', 'AUTOPART'))){
				$sc["use_type"]			= $currentGroup["use_type"];
				$sc['order_sum_price']	= $currentGroup['order_sum_price'];
			}
		}

		if	(in_array($sc["use_type"], array('AUTO', 'AUTOPART'))){

			//상위등급 추출시 본인등급제외
			if	(!empty($sc['group_seq'])){
				$addWhere	.= " and group_seq != '".$sc['group_seq']."' ";
			}

			// 구매금액 조건
			if	(!empty($sc['order_sum_price'])){
				$addWhere	.= " and order_sum_price > '".$sc["order_sum_price"]."' ";
			}

			$sql					= "select * from fm_member_group "
									. "where use_type in ('AUTO', 'AUTOPART') "
									. $addWhere
									. "order by order_sum_price, order_sum_ea, order_sum_cnt "
									. "limit 1 ";
			$query					= $this->db->query($sql);
			$result['nextGroup']	= $query->row_array();
		}

		return $result;
	}

	// 소멸 예정 혜택
	public function get_extinction($sc){

		if (!$sc['member_seq']) {
			return;
		}

		// 회원검색
		$addWhere .= " and member_seq = '".$sc["member_seq"]."' ";

		// 소멸 예정 할인쿠폰 ( 금주내 월(0) ~ 일(6) )
		if (!$sc['extinction_type'] || in_array('coupon', $sc['extinction_type'])) {
			$week				= (!date('w')) ? 6 : date('w') - 1;
			$sDate				= date('Y-m-d');
			$eDate				= date('Y-m-d', strtotime('+'.(6-$week).' day'));

			$sql				= "select count(*) cnt from fm_download "
								. "where issue_enddate >= '".$sDate."' "
								. "and issue_enddate <= '".$eDate."' "
								. $addWhere;
			$query				= $this->db->query($sql);
			$coupon				= $query->row_array();
			$result['coupon']	= $coupon['cnt'];
		}

		// 소멸 예정 마일리지 (익월 1일 ~ 익월 말일 )
		if (!$sc['extinction_type'] || in_array('reserve', $sc['extinction_type'])) {
			$sDate				= date('Y-m-d');
			$eDate				= date('Y-m') . '-' . date('t');

			$sql				= "select ifnull(sum(remain),0) emoney from fm_emoney "
								. "where limit_date >= '".$sDate."' "
								. "and limit_date <= '".$eDate."' "
								. $addWhere;
			$query				= $this->db->query($sql);
			$emoney				= $query->row_array();
			$result['reserve']	= $emoney['emoney'];
		}

		// 소멸 예정 포인트 (익월 1일 ~ 익월 말일 )
		if (!$sc['extinction_type'] || in_array('reserve_point', $sc['extinction_type'])) {
			$sDate				= date('Y-m-d');
			$eDate				= date('Y-m') . '-' . date('t');

			$sql				= "select ifnull(sum(remain),0) point from fm_point "
								. "where limit_date >= '".$sDate."' "
								. "and limit_date <= '".$eDate."' "
								. $addWhere;
			$query				= $this->db->query($sql);
			$emoney				= $query->row_array();
			$result['reserve_point']	= $emoney['point'];
		}

		// 구매확정 대기 건수
		if (!$sc['extinction_type'] || in_array('buyconfirm', $sc['extinction_type'])) {
			$config	= config_load('order');
			if ($config['buy_confirm_use']) {
				$sql					= "select count(*) as cnt from fm_goods_export "
										. "where (status = '55' or status = '65' or status = '75') and buy_confirm = 'none' "
										. "and order_seq in ( "
										. "	select order_seq from fm_order where order_seq > 0 "
										. $addWhere . " ) ";
				$query					= $this->db->query($sql);
				$buyconfirm				= $query->row_array();
				$result['buyconfirm']	= $buyconfirm['cnt'];
			}
		}

		return $result;
 	}

	// 주문시 주소록 배송지 가져오기
	public function get_delivery_address($member_seq,$type,$idx=0,$limit=1){
		$key = get_shop_key();

		if ($type=='lately') {
			$sql = "SELECT d.*,
			AES_DECRYPT(UNHEX(d.recipient_phone), '{$key}') as recipient_phone,
			AES_DECRYPT(UNHEX(d.recipient_cellphone), '{$key}') as recipient_cellphone
			FROM fm_delivery_address d
				INNER JOIN (
				SELECT recipient_address, MAX( address_seq ) AS address_seq
				FROM fm_delivery_address
				WHERE member_seq=? AND lately='Y' AND recipient_address <> ''
				GROUP BY recipient_address
				ORDER BY address_seq desc limit ?,?
			) a ON d.recipient_address = a.recipient_address AND d.address_seq = a.address_seq
			";
		}else{
			$sql = "select *,
			AES_DECRYPT(UNHEX(recipient_phone), '{$key}') as recipient_phone,
			AES_DECRYPT(UNHEX(recipient_cellphone), '{$key}') as recipient_cellphone
			from fm_delivery_address where member_seq=? ";

			if($type=='often') $sql .= " and often='Y' and `default`='Y' ";
			$sql .= " order by address_seq desc limit ?,?";
		}

		$query = $this->db->query($sql,array($member_seq,$idx,$limit));
		$result = $query->result_array();
		return $result;
	}

	//회원정보엑셀 기타 정보 가져오기
	public function get_member_only_excel($seq){
		$key = get_shop_key();
		$sql = "SELECT
					A.member_seq as member_seq,C.group_name,
					A.user_name as user_name, A.nickname as nickname,
					A.userid, A.sex, A.sns_f, A.recommend, A.zipcode, A.address, A.address_street, A.address_detail,
					B.business_seq, B.baddress_type, B.baddress, B.baddress_street, B.baddress_detail,
					B.bzipcode, B.bceo, B.bno, B.bitem,
					B.bstatus, B.bperson, B.bpart,
					A.birthday as birthday, A.anniversary as anniversary,
					A.emoney as emoney, A.point as point, A.cash as cash,
					AES_DECRYPT(UNHEX(A.email), '{$key}') as email,
					AES_DECRYPT(UNHEX(A.phone), '{$key}') as phone,
					AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone
				FROM
					fm_member A
						LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq
						LEFT JOIN fm_member_group C ON A.group_seq = C.group_seq
				WHERE
					A.member_seq = '{$seq}' limit 0, 1";
		$query = $this->db->query($sql);

		$data = array();
		foreach ($query->result_array() as $row){
			$data[] = $row;
		}

		// 사업자 회원일 경우 사업장주소->주소
		if($data[0]['business_seq']){
			$data[0]['baddress_type'] = $data[0]['baddress_type'];
			$data[0]['address'] = $data[0]['baddress'];
			$data[0]['address_street'] = $data[0]['baddress_street'];
			$data[0]['address_detail'] = $data[0]['baddress_detail'];

			$tmp = explode('-',$data[0]['bzipcode']);
			foreach($tmp as $k => $datas){
				$key = 'zipcode'.($k+1);
				$data[0][$key] = $datas;
			}
		}

		return (isset($data[0]))?$data[0]:'';
	}

	## 회원등급 혜택 defualt_yn = 'y' 2014-07-24
	public function get_member_sale_default(){

		$qry		= "select sale_seq from fm_member_group_sale where defualt_yn = 'y'";
		$query		= $this->db->query($qry);
		$sale_res	= $query -> result_array();
		$sale_seq	= $sale_res[0]['sale_seq'];

		$qry = "select * from fm_member_group_sale_detail where sale_seq = '".$sale_seq."' order by sale_limit_price desc";
		$query = $this->db->query($qry);
		$detail_tmp = $query -> result_array();
		foreach($detail_tmp as $detail){
			$detail_list[$detail['group_seq']] = $detail;
		}

		return $detail_list;
	}

	public function get_member_sale_array($sale_seq){

		$list = $this->member_sale_group_list();
		$select_sale_list = $this->get_member_sale();


		if($sale_seq){
			//일반가입 정보
			$qry = "select * from fm_member_group_sale where sale_seq = '".$sale_seq."'";
			$query = $this->db->query($qry);
			$sale_list = $query -> result_array();

			foreach ($sale_list as $datarow){

				foreach($list as $group){

					$qry = "select * from fm_member_group_sale_detail where sale_seq = '".$datarow["sale_seq"]."' and group_seq = '".$group["group_seq"]."'";
					$query = $this->db->query($qry);
					$detail_list = $query -> result_array();

					foreach($detail_list as $subdatarow){


						$subdata[$group["group_seq"]]["sale_use"]				= $subdatarow["sale_use"];
						$subdata[$group["group_seq"]]["sale_limit_price"]		= $subdatarow["sale_limit_price"];
						$subdata[$group["group_seq"]]["sale_price"]				= $subdatarow["sale_price"];

						$subdata[$group["group_seq"]]["sale_price_type"]		= $subdatarow["sale_price_type"];
						$subdata[$group["group_seq"]]["sale_option_price"] 		= $subdatarow["sale_option_price"];

						$subdata[$group["group_seq"]]["sale_option_price_type"]	= $subdatarow["sale_option_price_type"];
						$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_use"];

						$subdata[$group["group_seq"]]["point_use"]				= $subdatarow["point_use"];
						$subdata[$group["group_seq"]]["point_limit_price"]		= $subdatarow["point_limit_price"];
						$subdata[$group["group_seq"]]["point_price"]			= $subdatarow["point_price"];

						$subdata[$group["group_seq"]]["point_price_type"]		= $subdatarow["point_price_type"];

						$subdata[$group["group_seq"]]["reserve_price"]			= $subdatarow["reserve_price"];

						$subdata[$group["group_seq"]]["reserve_price_type"]		= $subdatarow["reserve_price_type"];
						$subdata[$group["group_seq"]]["reserve_select"]			= $subdatarow["reserve_select"];
						$subdata[$group["group_seq"]]["reserve_year"]			= $subdatarow["reserve_year"];
						$subdata[$group["group_seq"]]["reserve_direct"]			= $subdatarow["reserve_direct"];
						$subdata[$group["group_seq"]]["point_select"]			= $subdatarow["point_select"];
						$subdata[$group["group_seq"]]["point_year"]				= $subdatarow["point_year"];
						$subdata[$group["group_seq"]]["point_direct"]			= $subdatarow["point_direct"];
					}


				}

				$data[$datarow["sale_seq"]] = $subdata;
				$data[$datarow["sale_seq"]]["sale_seq"] = $datarow["sale_seq"];
				$data[$datarow["sale_seq"]]["sale_title"] = $datarow["sale_title"];
			}

		}

		$data[$datarow["sale_seq"]]["sale_list"] = $select_sale_list;
		$data[$datarow["sale_seq"]]["loop"] = $list;
		$data[$datarow["sale_seq"]]["gcount"] = count($list);

		return array('sale_list'=>$select_sale_list,'data'=>$data,'loop'=>$list,'gcount'=>count($list));


	}

	## SMS 발송제한 설정 타이틀
	public function get_sms_restriction(){

		### 발송시간 제한
		$restriction_title = [
			'comm' => '주문 메시지',
			'goods' => '배송 메시지',
			'present' => '선물하기 메시지',
			'coupon' => '티켓<br />상품',
			'join' => '회원가입',
			'findid' => '아이디 찾기',
			'withdrawal' => '회원탈퇴',
			'findpwd' => '비밀번호 찾기',
			'order' => '주문접수',
			'settle' => '결제확인',
			'released' => '출고완료 (주문자)',
			'released2' => '출고완료 (받는분)',
			'delivery' => '배송완료 (주문자)',
			'delivery2' => '배송완료 (받는분)',
			'cancel' => '환불완료 (취소)',
			'refund' => '환불완료 (반품)',
			'coupon_released' => '티켓발송 (주문자)',
			'coupon_released2' => '티켓발송 (받는분)',
			'coupon_delivery' => '티켓사용 (주문자)',
			'coupon_delivery2' => '티켓사용 (받는분)',
			'coupon_cancel' => '티켓 환불 완료 (취소)',
			'coupon_refund' => '티켓 환불 완료 (반품)',
			'present_receive' => '선물수신',
			'present_cancel_order' => '선물취소(주문자)',
			'present_cancel_receive' => '선물취소(받는분)',
		];



		### 발송시간 제한
		$restriction_item = $rest_comm = array();

		$rest_comm['order']				= array('ac_customer'	=> "접수 시 발송"
											,'ac_admin'		=> "접수 시 발송"
											,'ac_system'	=> ""
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> ''
											,'use'		=> 'y'
										);
		$rest_comm['settle']			= array('ac_customer'	=> "확인 시 발송"
											,'ac_admin'		=> "확인 시 발송"
											,'ac_system'	=> "자동 확인 시 발송"
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> '○'
											,'use'		=> 'y'
										);
		$rest_goods['released']			= array('ac_customer'	=> ""
											,'ac_admin'		=> "완료시 발송"
											,'ac_system'	=> ""
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> ''
											,'use'		=> 'y'
										);
		$rest_goods['released2']			= array('ac_customer'	=> ""
											,'ac_admin'		=> "완료시 발송"
											,'ac_system'	=> ""
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> ''
											,'use'		=> 'y'
										);
		$rest_goods['delivery']			= array('ac_customer'	=> "구매확정 시 발송"
											,'ac_admin'		=> "완료시 발송"
											,'ac_system'	=> "자동 완료시 발송"
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> '○'
											,'use'		=> 'y'
										);
		$rest_goods['delivery2']		= array('ac_customer'	=> "구매확정 시 발송"
											,'ac_admin'		=> "완료시 발송"
											,'ac_system'	=> "자동 완료시 발송"
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> '○'
											,'use'		=> 'y'
										);
		$rest_comm['cancel']			= array('ac_customer'	=> "취소 시 발송"
											,'ac_admin'		=> "완료시 발송"
											,'ac_system'	=> ""
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> ''
											,'use'		=> 'y'
										);
		$rest_comm['refund']			= array('ac_customer'	=> ""
											,'ac_admin'		=> "완료시 발송"
											,'ac_system'	=> ""
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> ''
											,'use'		=> 'y'
										);
		$rest_coupon['coupon_released2']			= array('ac_customer'	=> ""
											,'ac_admin'		=> "결제 시 발송"
											,'ac_system'	=> ""
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> ''
											,'use'		=> 'n'
										);
		$rest_coupon['coupon_released']			= array('ac_customer'	=> ""
											,'ac_admin'		=> "결제 시 발송"
											,'ac_system'	=> ""
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> ''
											,'use'		=> 'n'
										);
		$rest_goods['coupon_delivery2']		= array('ac_customer'	=> ""
											,'ac_admin'		=> "사용확인 시 발송"
											,'ac_system'	=> ""
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> '○'
											,'use'		=> 'n'
										);
		$rest_goods['coupon_delivery']		= array('ac_customer'	=> ""
											,'ac_admin'		=> "사용확인 시 발송"
											,'ac_system'	=> ""
											,'tg_customer'	=> '○'
											,'tg_admin'		=> '○'
											,'tg_seller'	=> '○'
											,'use'		=> 'n'
										);
		$rest_comm['coupon_cancel']			= array('ac_customer'	=> ""
										,'ac_admin'		=> "완료시 발송"
										,'ac_system'	=> ""
										,'tg_customer'	=> '○'
										,'tg_admin'		=> '○'
										,'tg_seller'	=> ''
										,'use'		=> 'y'
										);
		$rest_comm['coupon_refund']			= array('ac_customer'	=> ""
										,'ac_admin'		=> "완료시 발송"
										,'ac_system'	=> ""
										,'tg_customer'	=> '○'
										,'tg_admin'		=> '○'
										,'tg_seller'	=> ''
										,'use'		=> 'y'
										);


		$rest_present['present_receive'] = ['ac_admin' => '완료시 발송', 'use' => 'y'];
		$rest_present['present_cancel_order'] = ['ac_admin' => '완료시 발송', 'use' => 'y'];
		$rest_present['present_cancel_receive'] = ['ac_admin' => '완료시 발송', 'use' => 'y'];


		foreach($rest_comm as $key => $item){
			if( preg_match('/coupon_/',$key) && !$this->isplusfreenot ){ //무료몰은 티켓 패스
				unset($rest_comm[$key]);
				continue;
			}
			if($item['use'] == 'y') $commusecnt++;
		}
		foreach($rest_goods as $item){ if($item['use'] == 'y') $goodsusecnt++; }
		foreach($rest_coupon as $item){ if($item['use'] == 'y') $couponusecnt++; }
		foreach($rest_present as $item){ if($item['use'] == 'y') $presentusecnt++; }
		$rest_comm['usecnt']	= $commusecnt;
		$rest_goods['usecnt']	= $goodsusecnt;
		$rest_coupon['usecnt']	= $couponusecnt;
		$rest_present['usecnt']	= $presentusecnt;

		$restriction_item['comm']	= $rest_comm;
		$restriction_item['goods']	= $rest_goods;
		$restriction_item['coupon']	= $rest_coupon;
		$restriction_item['present']	= $rest_present;

		return array($restriction_title,$restriction_item);
	}

	// 등급할인 목록 추출
	public function get_group_sale_list(){
		$sql	= "select * from fm_member_group_sale ";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		return $result;
	}

	// 등급할인 상세 목록 추출
	public function get_group_sale_detail($sale_seq = '', $group_seq = ''){
		if	($sale_seq > 0)				$addWhere[]	= " sale_seq = '".$sale_seq."' ";
		if	(is_numeric($group_seq))	$addWhere[]	= " group_seq = '".$group_seq."' ";
		if	(is_array($addWhere) && count($addWhere) > 0){
			$where	= "where ".implode(' and ', $addWhere);
		}
		$sql	= "select * from fm_member_group_sale_detail ".$where;
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		return $result;
	}

	// 등급할인 제외 카테고리 목록 추출
	public function get_group_sale_issuecategory($sale_seq = ''){
		$sql	= "select * from fm_member_group_issuecategory ";
		if	($sale_seq > 0)	$sql	.= " where sale_seq = '".$sale_seq."' ";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		return $result;
	}

	// 등급할인 제외 카테고리 목록 추출
	public function get_group_sale_issuegoods($sale_seq = ''){
		$sql	= "select * from fm_member_group_issuegoods ";
		if	($sale_seq > 0)	$sql	.= " where sale_seq = '".$sale_seq."' ";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		return $result;
	}

	// 등급할인 상세 목록 추출
	public function get_group_sale_detail_sales($sale_seqs){
		$sql	= "select * from fm_member_group_sale_detail where sale_seq in ('".implode("','", $sale_seqs)."')";
		return $this->db->query($sql);
	}

	// 등급할인 제외 카테고리 목록 추출
	public function get_group_sale_issuecategory_sales($sale_seqs){
		$sql	= "select * from fm_member_group_issuecategory where sale_seq in ('".implode("','", $sale_seqs)."') ";
		return $this->db->query($sql);
	}

	// 등급할인 제외 카테고리 목록 추출
	public function get_group_sale_issuegoods_sales($sale_seqs){
		$sql	= "select * from fm_member_group_issuegoods where sale_seq in ('".implode("','", $sale_seqs)."') ";
		return $this->db->query($sql);
	}

	public function email_unsubscribe($email = '', $testMode = false){

		if($testMode == true) {
			$msg = "수신거부 처리되었습니다. - [정상 여부 확인용 메세지 입니다]";
		}else{
			// 대량메일 예외처리 :: 2018-04-03 lwh
			$tmp_email = base64_decode($email);
			$tmp_email = explode('@', $tmp_email);

			if(strpos($tmp_email[0], 'EMAIL_ID=') === false){
				$email = base64_decode(urldecode($email));
			}else{
				$tmp_email[0] = str_replace('EMAIL_ID=','',$tmp_email[0]);
				$email = implode('@', $tmp_email);
			}

			$key = get_shop_key();
			//select member_seq from fm_member where AES_DECRYPT(UNHEX(email), '{$key}')=?";
			$this->db->where('AES_DECRYPT(UNHEX(email), "'.$key.'") = ', $email);
			$this->db->from('fm_member');
			$this->db->select('member_seq');
			$query = $this->db->get();
			$result = $query->result_array();

			if(empty($result) !== true){
				foreach ($result as $data) {
					$this->db->where('member_seq', $data['member_seq']);
					$this->db->set('mailing', 'n');
					$this->db->update('fm_member');
				}
				$msg = "수신거부 처리되었습니다.";
			}else{
				$msg = "일치하는 정보가 없습니다.";
			}
		}

		return $msg;
	}

	public function dormancy_notify_list($dr_date){
		$result		= '';
		$start_date	= $dr_date.' 00:00:00';
		$end_date	= $dr_date.' 23:59:59';
		$key		= get_shop_key();
		$sql		= "SELECT
							member_seq,
							user_name,
							userid,
							AES_DECRYPT(UNHEX(email), '{$key}') as email,
							AES_DECRYPT(UNHEX(phone), '{$key}') as phone,
							AES_DECRYPT(UNHEX(cellphone), '{$key}') as cellphone
						FROM
							fm_member
						WHERE
							status != 'withdrawal' and
							((lastlogin_date between '{$start_date}' and '{$end_date}') or (lastlogin_date = '0000-00-00 00:00:00' and (regist_date between '{$start_date}' and '{$end_date}'))) and
							(dormancy_seq = '' or dormancy_seq is null)";
		$query		= $this->db->query($sql);
		$result		= $query->result_array();
		return $result;
	}

	public function dormancy_on($dr_date = null){

		$now_date			= date("Y-m-d H:i:s");
		$dr_date			= $dr_date ? $dr_date : date("Y-m-d",strtotime("-12 month"))." 23:59:59";
		$mem_fields			= $this->db->list_fields('fm_member');
		$mem_dr_fields		= $this->db->list_fields('fm_member_dr');
		$memsns_fields		= $this->db->list_fields('fm_membersns');
		$memsns_dr_fields	= $this->db->list_fields('fm_membersns_dr');
		$mem_biz_fields		= $this->db->list_fields('fm_member_business');
		$mem_biz_dr_fields	= $this->db->list_fields('fm_member_business_dr');
		$deli_fields		= $this->db->list_fields('fm_delivery_address');
		$deli_dr_fields		= $this->db->list_fields('fm_delivery_address_dr');

		$mem_select			= $mem_fields;
		$sns_select			= $memsns_fields;
		$biz_select			= $mem_biz_fields;
		$del_select			= $deli_fields;

		$memsns_exception	= array('seq','member_seq','sns_f','rute');
		$mem_biz_exception	= array('business_seq','member_seq');
		$deli_exception		= array('address_seq','member_seq');

		//현재 테이블과 백업 테이블을 비교하여 넣지 않을 값 unset 처리
		foreach($mem_fields as $mem_key => $mem_val){
			if(!in_array($mem_val,$mem_dr_fields) || $mem_val == 'dormancy_seq') unset($mem_select[$mem_key]);
		}

		foreach($memsns_fields as $memsns_key => $memsns){
			if(!in_array($memsns,$memsns_dr_fields)) unset($sns_select[$memsns_key]);
			if(!in_array($memsns,$memsns_dr_fields) || in_array($memsns,$memsns_exception)) unset($memsns_fields[$memsns_key]);
		}

		foreach($mem_biz_fields as $mem_biz_key => $mem_biz){
			if(!in_array($mem_biz,$mem_biz_dr_fields)) unset($biz_select[$mem_biz_key]);
			if(!in_array($mem_biz,$mem_biz_dr_fields) || in_array($mem_biz,$mem_biz_exception)) unset($mem_biz_fields[$mem_biz_key]);
		}

		foreach($deli_fields as $del_key => $del_val){
			if(!in_array($del_val,$deli_dr_fields)) unset($del_select[$del_key]);
			if(!in_array($del_val,$deli_dr_fields) || in_array($del_val,$deli_exception)) unset($deli_fields[$del_key]);
		}

		$mem_select = implode(',',$mem_select);
		$sns_select = implode(',',$sns_select);
		$biz_select = implode(',',$biz_select);
		$del_select = implode(',',$del_select);

		//회원가입후 한번도 로그인 하지 않는 회원은 가입일 기준으로 휴면 처리한다
		$sql			= "select ".$mem_select." from fm_member where status != 'withdrawal' and ((regist_date <= '{$dr_date}' and lastlogin_date <= '{$dr_date}') or (lastlogin_date = '0000-00-00 00:00:00' and regist_date <= '{$dr_date}')) and (dormancy_seq = '' or dormancy_seq is null)";
		$query			= mysqli_query($this->db->conn_id,$sql);
		while ($member = mysqli_fetch_assoc($query)){
			//휴면처리 대상 로그 추가
			$dormancy_seq			= $this->dormancy_log('on',$member['member_seq']);
			if($dormancy_seq){
				//휴면처리 대상 데이터 백업
				$param = $member;
				$param['dormancy_date']	= $now_date;
				$this->db->trans_begin();
				$rollback				= false;
				$member_check = $this->db->insert('fm_member_dr',$param);

				if($member_check){
					//회원정보 공백처리
					$paramMemberUpdate['dormancy_seq']		= $dormancy_seq;
					$paramMemberUpdate['status']			= 'dormancy';
					$paramMemberUpdate['group_seq']			= '1';	//휴면처리시 일반그룹으로 변경. #2646
					$paramMemberUpdate['user_name']			= '';
					$paramMemberUpdate['email']				= '';
					$paramMemberUpdate['phone']				= '';
					$paramMemberUpdate['cellphone']			= '';
					$paramMemberUpdate['zipcode']			= '';
					$paramMemberUpdate['address_type']		= '';
					$paramMemberUpdate['address']			= '';
					$paramMemberUpdate['address_street']	= '';
					$paramMemberUpdate['address_detail']	= '';
					$paramMemberUpdate['birthday']			= '';
					$paramMemberUpdate['auth_code']			= '';
					$paramMemberUpdate['auth_vno']			= '';
					$paramMemberUpdate['auth_type']			= '';

					$this->db->where(array('member_seq'=>$member['member_seq']));
					$this->db->update('fm_member',$paramMemberUpdate);

					//sns 데이터 이전
					$this->db->where(array('member_seq'=>$member['member_seq']));
					$this->db->select($sns_select);
					$rs			= $this->db->get('fm_membersns');
					$memsns_rs	= $rs->result_array();
					foreach($memsns_rs as $sns){
						$membersns_check = $this->db->insert('fm_membersns_dr',$sns);
						if($membersns_check){
							foreach($memsns_fields as $sns_fields){
								$paramSnsUpdate[$sns_fields] = '';
							}
							$this->db->where(array('seq'=>$sns['seq']));
							$this->db->update('fm_membersns',$paramSnsUpdate);
						}
					}

					//기업회원 데이터 이전
					$this->db->where(array('member_seq'=>$member['member_seq']));
					$this->db->select($biz_select);
					$rs2		= $this->db->get('fm_member_business');
					$biz_rs		= $rs2->result_array();
					foreach($biz_rs as $biz){
						$biz_check = $this->db->insert('fm_member_business_dr',$biz);
						if($biz_check){
							foreach($mem_biz_fields as $biz_fields){
								$paramBizUpdate[$biz_fields] = '';
							}
							$this->db->where(array('business_seq'=>$biz['business_seq']));
							$this->db->update('fm_member_business',$paramBizUpdate);
						}
					}

					//배송지 데이터 이전
					$this->db->where(array('member_seq'=>$member['member_seq']));
					$this->db->select($del_select);
					$rs3		= $this->db->get('fm_delivery_address');
					$deli_rs	= $rs3->result_array();
					foreach($deli_rs as $delivery){
						$delivery_check = $this->db->insert('fm_delivery_address_dr',$delivery);
						if($delivery_check){
							foreach($deli_fields as $d_fields){
								$paramDeliUpdate[$d_fields] = '';
							}
							$this->db->where(array('address_seq'=>$delivery['address_seq']));
							$this->db->update('fm_delivery_address',$paramDeliUpdate);
						}
					}

					// o2o 휴면 회원 데이터 이전
					$this->load->library('o2o/o2oservicelibrary');
					$this->o2oservicelibrary->dormancy_on_member_o2o(array('member_seq'=>$member['member_seq']));

					// 개인정보 보호 주문 분리
					$this->load->library('personalinfolibrary');
					if($this->personalinfolibrary->isPersonalInfoUse()) {
						$mdata = $this->get_member_seq_only($member['member_seq']);
						$this->personalinfolibrary->separatePersonalInfo([$mdata]);
					}
				}else{
					$rollback = true;
				}
				if($this->db->trans_status() === FALSE || $rollback == true){
					$this->db->trans_rollback();
					continue;
				}else{
					$this->db->trans_commit();
				}
			}
		}
	}

	public function dormancy_off($member_seq = null){
		if(!$member_seq) return false;

		//휴면처리 대상 로그 추가
		$dormancy_seq	= $this->dormancy_log('off',$member_seq);
		if($dormancy_seq){
			$memsns_fields		= $this->db->list_fields('fm_membersns');
			$memsns_dr_fields	= $this->db->list_fields('fm_membersns_dr');
			$mem_biz_fields		= $this->db->list_fields('fm_member_business');
			$mem_biz_dr_fields	= $this->db->list_fields('fm_member_business_dr');
			$deli_fields		= $this->db->list_fields('fm_delivery_address');
			$deli_dr_fields		= $this->db->list_fields('fm_delivery_address_dr');

			$memsns_exception	= array('member_seq','sns_f','dormancy_seq','rute');
			$mem_biz_exception	= array('member_seq','dormancy_seq');
			$deli_exception		= array('member_seq','dormancy_seq');

			$mem_select = '';
			$sns_select = '';
			$biz_select = '';
			$del_select = '';

			//현재 테이블과 백업 테이블을 비교하여 넣지 않을 값 unset 처리
			foreach($memsns_dr_fields as $sns_key => $sns_val){
				if(!in_array($sns_val,$memsns_fields) || in_array($sns_val,$memsns_exception)) unset($memsns_dr_fields[$sns_key]);
			}

			foreach($mem_biz_dr_fields as $biz_key => $biz_val){
				if(!in_array($biz_val,$mem_biz_fields) || in_array($biz_val,$mem_biz_exception)) unset($mem_biz_dr_fields[$biz_key]);
			}

			foreach($deli_dr_fields as $del_key => $del_val){
				if(!in_array($del_val,$deli_fields) || in_array($del_val,$deli_exception)) unset($deli_dr_fields[$del_key]);
			}

			//휴면 회원 복구시 등급은 복구 안함. #2646
			$mem_select = 'user_name,email,phone,cellphone,zipcode,address_type,address,address_street,address_detail,birthday,auth_code,auth_vno,auth_type,status,today_cnt,today_view,api_key';

			$sns_select = implode(',',$memsns_dr_fields);
			$biz_select = implode(',',$mem_biz_dr_fields);
			$del_select = implode(',',$deli_dr_fields);

			//회원데이터 복구
			$this->db->select($mem_select);
			$this->db->where(array('member_seq'=>$member_seq));
			$rs					= $this->db->get('fm_member_dr');
			$member_rs			= $rs->row_array();
			$member_param		= $member_rs;

			if($member_param){
				$member_param['dormancy_seq']	= null;
				$member_param['lastlogin_date']	= date("Y-m-d H:i:s");

				$this->db->trans_begin();

				$this->db->where(array('member_seq'=>$member_seq));
				$this->db->update('fm_member',$member_param);

				$this->db->where(array('member_seq'=>$member_seq));
				$this->db->delete('fm_member_dr');

				//sns 복구
				$this->db->select($sns_select);
				$this->db->where(array('member_seq'=>$member_seq));
				$pprs	= $this->db->get('fm_membersns_dr');
				$sns_rs	= $pprs->result_array();

				if($sns_rs){
					foreach($sns_rs as $sns){
						$sns_param = $sns;
						unset($sns_param['seq']);
						$this->db->where(array('seq'=>$sns['seq']));
						$sns_check = $this->db->update('fm_membersns',$sns_param);
						if($sns_check){
							$this->db->where(array('seq'=>$sns['seq']));
							$this->db->delete('fm_membersns_dr');
						}
					}
				}

				//기업회원 복구
				$this->db->select($biz_select);
				$this->db->where(array('member_seq'=>$member_seq));
				$pprs	= $this->db->get('fm_member_business_dr');
				$biz_rs	= $pprs->result_array();

				if($biz_rs){
					foreach($biz_rs as $biz){
						$biz_param = $biz;
						unset($biz_param['business_seq']);
						$this->db->where(array('business_seq'=>$biz['business_seq']));
						$biz_check = $this->db->update('fm_member_business',$biz_param);
						if($biz_check){
							$this->db->where(array('business_seq'=>$biz['business_seq']));
							$this->db->delete('fm_member_business_dr');
						}
					}
				}

				//회원배송지데이터 복구
				$this->db->select($del_select);
				$this->db->where(array('member_seq'=>$member_seq));
				$pprs		= $this->db->get('fm_delivery_address_dr');
				$deli_rs	= $pprs->result_array();

				if($deli_rs){
					foreach($deli_rs as $delivery){
						$deli_param = $delivery;
						unset($deli_param['address_seq']);
						$this->db->where(array('address_seq'=>$delivery['address_seq']));
						$deli_check = $this->db->update('fm_delivery_address',$deli_param);
						if($deli_check){
							$this->db->where(array('address_seq'=>$delivery['address_seq']));
							$this->db->delete('fm_delivery_address_dr');
						}
					}
				}

				// o2o 휴면 회원 데이터 복구
				$this->load->library('o2o/o2oservicelibrary');
				$this->o2oservicelibrary->dormancy_off_member_o2o(array('member_seq'=>$member['member_seq']));

				// 개인정보 보호 분리 주문 복원
				$this->load->library('personalinfolibrary');
				if($this->personalinfolibrary->isPersonalInfoUse()) {
					$this->personalinfolibrary->restorePersonalInfo($member_seq);
				}

				if($this->db->trans_status() === FALSE){
					$this->db->trans_rollback();
					return false;
				}else{
					$this->db->trans_commit();
				}
			}
		}
	}

	public function dormancy_log($log_type = 'on',$member_seq){
		$dormancy_seq = '';
		if($member_seq){
			$paramLog['member_seq']	= $member_seq;
			$paramLog['log_date']	= date("Y-m-d H:i:s");
			$paramLog['log_type']	= $log_type;
			$this->db->insert('fm_dormancy_log',$paramLog);
			$dormancy_seq			= $this->db->insert_id();

			$log_act = '<div>'.$paramLog['log_date'].' 휴면처리</div>';
			if($log_type == 'off') $log_act = '<div>'.$paramLog['log_date'].' 휴면해제</div>';
			$sql = "update fm_member set admin_log = concat('{$log_act}',ifnull(admin_log,'')) where member_seq = {$member_seq}";
			$this->db->query($sql);
		}
		return $dormancy_seq;
	}

	public function get_dormancy($member_seq){
		$ret = '';
		if($member_seq){
			$key = get_shop_key();
			$sql = "select *,AES_DECRYPT(UNHEX(email), '{$key}') as email_real from fm_member_dr where member_seq = '{$member_seq}'";
			$ret = $this->db->query($sql);
			$ret = $ret->row_array();
		}
		return $ret;
	}

	public function admin_member_dr_list($sc){

		$sqlSelectClause = "
			select
				*
		";
		$sqlFromClause = "
			from
				fm_dormancy_log d
				left join fm_member m on m.member_seq = d.member_seq
		";

		$sqlWhereClause = " where 1=1 ";

		if( !empty($sc['keyword'])){
			$sqlWhereClause .= " and ( m.userid like '%".$sc['keyword']."%' ) ";
		}
		### add start, end time for search date
		$add_stime	= ' 00:00:00';
		$add_etime	= ' 23:59:59';
		### regist date
		if( !empty($sc['regist_sdate']) && !empty($sc['regist_edate']) ){
			$sqlWhereClause .= " AND log_date between '{$sc['regist_sdate']}{$add_stime}' and '{$sc['regist_edate']}{$add_etime}' ";
		}else if( !empty($sc['regist_sdate']) && empty($sc['regist_edate']) ){
			$sqlWhereClause .= " AND log_date >= '{$sc['regist_sdate']}{$add_stime}' ";
		}else if( empty($sc['regist_sdate']) && !empty($sc['regist_edate']) ){
			$sqlWhereClause .= " AND log_date <= '{$sc['regist_edate']}{$add_etime}' ";
		}

		### sms
		if( !empty($sc['status']) ){
			$sqlWhereClause .= " AND d.log_type = '{$sc[status]}' ";
		}

		$sqlOrderClause .=" order by {$sc['orderby']} {$sc['sort']}";

		$limit =" limit {$sc['page']}, {$sc['perpage']} ";

		$sql = "
			{$sqlSelectClause}
			{$sqlFromClause}
			{$sqlWhereClause}
			{$sqlOrderClause}
		";

		$query = $this->db->query($sql.$limit);

		$data['result'] = $query->result_array();

		$cnt_query = 'select count(*) as cnt '. $sqlFromClause . $sqlWhereClause;
		$cntquery = $this->db->query($cnt_query);
		$cntrow = $cntquery->result_array();
		$data['count'] = $cntrow[0]['cnt'];

		return $data;

	}

	public function get_issue_count($startDate,$endDate){
		$sql = "
		SELECT count(*) as cnt
		FROM fm_member
		WHERE regist_date between '".$startDate."' and '".$endDate."'
		";
		return $this->db->query($sql);
	}
	# 그룹별 회원수
	public function get_grade_member_cnt($sqlFromClause, $sqlWhereClause, $bind){

		$sql = "select
					A.group_seq,D.group_name,count(*) cnt
				{$sqlFromClause}
				{$sqlWhereClause}
				group by A.group_seq,D.group_name
				";
		$query	= $this->db->query($sql, $bind);
		$result	= $query->result_array();
		return $result;

	}

	# 회원 승인 일괄 변경
	public function set_confirm_update($memberArr){

		if(is_array($memberArr)){
			$admin_memo = "<div>".$this->managerInfo['manager_id']."에 의해 회원 승인여부가 ".date("Y년m월d일 H시i분s초")."에 변경됨 [미승인] → [승인] (".$_SERVER['REMOTE_ADDR'].")</div>";
			$sql = "update fm_member set status='done', admin_log=concat('".$admin_memo."',ifnull(admin_log,'')) where member_seq in(".implode(",",$memberArr).")";
			$this->db->query($sql);
		}

	}
	# 회원 등급 일괄 변경
	public function set_grade_update($memberArr,$old_grade,$new_grade){

		if(is_array($memberArr)){
			$cfg_grade = config_load('grade_clone');

			### 자동등급으로 변경시 등급유지기간 설정
			$oldSql = "select * from fm_member_group where group_seq = '".$old_grade."'";
			$oldQuery = $this->db->query($oldSql);
			$oldMbgpData = $oldQuery->row_array();
			$oldGroupName = $oldMbgpData['group_name'];

			$newSql = "select * from fm_member_group where group_seq = '".$new_grade."'";
			$newQuery = $this->db->query($newSql);
			$mbgpdata = $newQuery->row_array();
			$newGroupName = $mbgpdata['group_name'];

			$change_group = false;
			if( $cfg_grade['keep_term'] && ( $mbgpdata['use_type']=='AUTO' || $mbgpdata['use_type']=='AUTOPART' ) ) {
				$keep_term			= $cfg_grade['keep_term'];
				$keep_term_date	= date('Y-m-d',strtotime('+'.$keep_term.' month'));
				$change_group = true;
			}
			if($change_group)
			{
				$grade_msg		= "(".$keep_term."개월간 등급 유지, 즉 ". $keep_term_date."까지)";
				$group_set_date	= $keep_term_date;
			}else{//초기화
				$grade_msg		= '';
				$group_set_date	= '';
			}
			foreach($memberArr as $memberSeq){
				### LOG
				$i_qry = "insert into fm_member_group_log set member_seq = ?, prev_group_seq = ?, chg_group_seq = ?, regist_date=now()";
				$this->db->query($i_qry,array($memberSeq,$old_grade,$new_grade));

				$adminLog = "<div>[수동] ".date('Y-m-d H:i:s')." ".$oldGroupName." → ".$newGroupName.$grade_msg." (".$this->managerInfo['manager_id'].", ".$_SERVER['REMOTE_ADDR'].")</div>";

				$bind = array();
				$bind[] = $new_grade;		//변경할등급
				$bind[] = $group_set_date;	//등급유지기간
				$bind[] = $adminLog;		//처리로그
				$bind[] = $memberSeq;		//회원고유번호
				$bind[] = $old_grade;		//현재등급
				$sql = "update fm_member set
							group_seq=?,
							group_set_date=?,
							admin_log=concat(?,IFNULL(admin_log,'')),
							grade_update_date=now()
						where member_seq=? and group_seq=?";
				$this->db->query($sql,$bind);
			}
		}

	}

	public function chkPhoneDash($phone) {
		if(strpos($phone,'-')===FALSE) { // add dash
			return preg_replace("/(^02.{0}|^01.{1}|[0-9]{3})([0-9]+)([0-9]{4})/", "$1-$2-$3", $phone);
		}
		else {
			return $phone;
		}
	}

	// 마일리지, 예치금, 포인트의 다국어 메모 삽입을 위해 json 형식으로 변환
	public function make_json_for_getAlert($code = null, $args = null){
		$str = array(
			"type"=>"getAlert"
			, "code"=>$code
			, "arg"=>$args
		);
		$str = json_encode($str);
		return $str;
	}
	// 마일리지, 예치금, 포인트의 json 형식의 메모를 getAlert로 변환
	public function make_str_for_getAlert($memo = null, $memo_lang = null){
		$return_str = $memo;
		if(!is_null($memo_lang)){
			if(strpos($memo_lang,"\"type\":\"getAlert\"")>-1){
				$arr = json_decode($memo_lang,TRUE);
				$return_str = getAlert($arr['code'],$arr['arg']);
			}
		}
		return $return_str;
	}


	// 회원 가입
	public function insert_member($params){
		$memberseq = false;
		$data = filter_keys($params, $this->db->list_fields('fm_member'));
		$result = $this->db->insert('fm_member', $data);
		$memberseq = $this->db->insert_id();
		return $memberseq;
	}

	// 기업 회원 가입
	public function insert_member_business($params){
		$bdata = filter_keys($params, $this->db->list_fields('fm_member_business'));
		$result = $this->db->insert('fm_member_business', $bdata);
		$business_seq = $this->db->insert_id();
		return $business_seq;
	}

	// 추가 정보 입력
	public function insert_member_subinfo($params){
		$subinfo_seq = false;

		// 추가 설정 얻기
		unset($param_joinform);
		$param_joinform['joinform_seq'] = $params['joinform_seq'];
		$form_result = $this->select_joinform($param_joinform);

		$params['label_title'] = $form_result['label_title'];
		$params['regist_date'] = date('Y-m-d H:i:s');
		$result = $this->db->insert('fm_member_subinfo', $params);
		$subinfo_seq = $this->db->insert_id();

		return $subinfo_seq;
	}

	// 회원가입폼 추가 정보 얻기
	public function select_joinform($params){
		$query = $this->db->get_where('fm_joinform',$params);
		$form_result = $query -> row_array();
		return $form_result;
	}

	// 개인정보 암호화
	public function update_private_encrypt($memberseq, $params = []){
		// 개인정보 암호화 프로세스 개선 2021-05-18 by kjw(#55658)
		if(!empty($memberseq)){
			// 관리자에서 업데이트 한 후 사용자가 업데이트 할 경우 중복 암호화되지 않도록 함
			$sql = "select email,cellphone,phone from fm_member where member_seq=? for update";
			$query = $this->db->query($sql,array($memberseq));
			$minfo = $query->row_array();

			if (!$minfo) return;

			$update_qry = [];

			if ($params['email']) {
				$update_qry[] = get_encrypt_qry('email');
			}

			if ($params['phone']) {
				$update_qry[] = get_encrypt_qry('phone');
			}

			if ($params['cellphone']) {
				$update_qry[] = get_encrypt_qry('cellphone');
			}

			$update_qry[] = "update_date = now()";

			$update_qry = implode(',', $update_qry);

			$sql = "update fm_member set {$update_qry} where member_seq = ?";
			$this->db->query($sql,array($memberseq));
		}
	}


	// 회원 정지
	public function update_status_hold($memberseq){
		if(!empty($memberseq)){
			$this->db->where('member_seq', $memberseq);
			$this->db->update('fm_member', array("status"=>'hold'));
		}
	}
	// 회원 정보 업데이트
	public function update_member($params){
		if(!empty($params['member_seq'])){
			$this->db->where('member_seq', $params['member_seq']);
			unset($params['member_seq']);
			$this->db->update('fm_member', $params);
		}
	}


	# 사업자번호 체크 model 로 이동 2019-01-09 hyem
	public function bizno_check($bno){
		$return = true;
		if(!preg_match("/^[0-9]{10}$/", $bno)) {
			$return = false;
		}
		//규칙에 올바른지 체크
		$weight = '137137135';
		$sum = 0;
		for ($i = 0; $i < 9; $i++) {
			$sum += (substr($bno,$i,1) * substr($weight , $i , 1)) %10;
		}

		$sum += (substr($bno,8,1)*5)/10 + substr($bno,9,1);

		if ($sum %10 !=0) {
			$return = false;
		}

		return $return;
	}


	public function get_member_marketing_agree($params)
	{
	    $bind      = array();
	    $where     = "";
	    $fields    = "";

	    if ($params['count'] === TRUE) {
	        $fields = "COUNT(member_seq) AS cnt";
	    } else {
	        $fields = "*";
	    }

	    $where = "status IN ('done',  'hold')
                    AND (mailing =  'y' OR sms =  'y')
                    AND (
                            (marketing_agree_send_date <= DATE_SUB(NOW() , INTERVAL 1 MONTH) && marketing_agree_send_date != '0000-00-00 00:00:00')
                            OR
                            (update_date <= DATE_SUB(NOW() , INTERVAL 24 MONTH) && marketing_agree_send_date = '0000-00-00 00:00:00')
                        )
                ORDER BY
                    marketing_agree_send_date ASC";

	    $query = "seLECT {$fields} FROM `fm_member` WHERE {$where}";

	    if ($params['count'] !== TRUE && $params['limit'] > 0 && $params['offset'] > 0) {
	        $query .= " limit {$params['offset']}, {$params['limit']}";
	    }

	    $query = $this->db->query($query, $bind);
	    $row = $query->result_array();
	    return $row;
	}

	public function get_member_marketing_send()
	{
	    $fields    = "";
	    $where     = "";
	    $bind      = array();
	    $key       = get_shop_key();

	    $fields    = "seq, member_seq, AES_DECRYPT(UNHEX(receive_addr), '{$key}') as receive_addr";
	    $where     = "res = 'n'";
	    $query     = "seLECT {$fields} FROM `fm_marketing_send_log` WHERE {$where} ORDER BY seq ASC";

	    $query = $this->db->query($query, $bind);
	    $row = $query->result_array();
	    return $row;
	}

	public function get_member_marketing_update($params)
	{
	    if ($params['count'] === TRUE) {
	        $fields = "COUNT(member_seq) AS cnt";
	    } else {
	        $fields = "member_seq, update_date";
	    }

	    $query = "seLECT
                    {$fields}
                FROM
                    `fm_member`
                WHERE
                    status IN ('done',  'hold',  'dormancy')
                    AND (mailing =  'y' OR sms =  'y')
                    AND marketing_agree_send_date <= NOW()";

                    if ($params['count'] !== TRUE && $params['limit'] > 0 && $params['offset'] > 0) {
                        $query .= " limit {$params['offset']}, {$params['limit']}";
                    }

                    $query = $this->db->query($query);
                    $row = $query->result_array();
                    return $row;
	}

	public function get_member_data_all($seq){
	    $sql		= "select * from fm_member where member_seq='".$seq."'";
	    $query		= $this->db->query($sql);
	    $member_info= $query->result_array();
	    return $member_info[0];
	}

	public function get_member_marketing_send_log($member_seq, $type='list', $limit_date)
	{
	    if ($member_seq <= 0) {
	        return false;
	    }

	    $key = get_shop_key();

	    $fields = "";
	    if ($type == 'batch') {
	        $fields = "*";
	    } else {
	        $fields = "type, send_addr, AES_DECRYPT(UNHEX(receive_addr), '{$key}') as receive_addr, send_date, res";
	    }

	    $sql = "sELECT {$fields} FROM fm_marketing_send_log WHERE member_seq='".$member_seq."'";

	    if ($type == 'batch') {
	        $sql .= " AND send_date <= DATE_SUB('".$limit_date."', INTERVAL 24 MONTH)";
	    } else {
	        $sql .= " AND res != 'n' ORDER BY seq DESC";
	    }

	    $query = $this->db->query($sql);
	    $res   = $query->result_array();
	    return $res;
	}

	// 계정에 연결 된 sns 계정 연결여부 가져오기 :: 2020-03-11 pjw
	public function get_joined_sns_list($member_seq = ''){

		if(empty($member_seq)) return false;
		$joined_list		= array();
		$new_joined_list	= array();

		// fm_membersns 에서 같은 member_seq로 등록 된 sns 계정목록 가져옴
		$query				= $this->db->get_where('fm_membersns', array('member_seq'=>$member_seq));
		$joined_list		= $query->result_array();

		// rute (가입방법)을 key로 배열 재정의
		foreach($joined_list as $joined_sns){
			$new_joined_list[] = $joined_sns['rute'];
			if($joined_sns['rute'] == 'kakao') {
				$new_joined_list[] = 'kakaosync';
			}
		}

		return $new_joined_list;

    }

	public function get_member_group_info($group_seq)
	{
		$query = $this->db
			->select('*')
			->from('fm_member_group')
            ->where('group_seq', $group_seq)
            ->get()
			->row_array();

		return $query;
	}

	// 가입된 회원정보 검색 :: 2020-06-15 sms
	public function get_member_info($mtype, $member_seq = ''){

		if(empty($member_seq)) return false;

        // 이메일, 휴대폰번호 디코딩을 위해 추가 2021-07-16
        $key = get_shop_key();

        if($mtype == 'member'){
			$query = "select A.*,C.group_name, AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone, AES_DECRYPT(UNHEX(A.email), '{$key}') as email from fm_member A LEFT JOIN fm_member_group C on C.group_seq=A.group_seq where A.member_seq = '".$member_seq."'";
		}else{
			$query = "select A.*,B.business_seq,B.bname,C.group_name, AES_DECRYPT(UNHEX(A.cellphone), '{$key}') as cellphone, AES_DECRYPT(UNHEX(A.email), '{$key}') as email from fm_member A LEFT JOIN fm_member_business B ON A.member_seq = B.member_seq left join fm_member_group C on C.group_seq=A.group_seq where A.member_seq = '".$member_seq."'";
		}
		$query			= $this->db->query($query);
		$member_data	= $query->result_array();

		return $member_data;
	}

	// 회원 이메일 검색
	public function get_member_email($member_seq)
	{

		if(!$member_seq) {
			return false;
		}

		$key = get_shop_key();
		$aSelect = "mtype, AES_DECRYPT(UNHEX(email), '{$key}') as email";
		$query = $this->db->select($aSelect)
		->from('fm_member')
		->where('member_seq', $member_seq)
		->get();

		return $query->row_array();
	}

	// userid 중복 여부를 위한 count 쿼리
	public function countMemberByUserId($userId) {
		$query = $this->db->select('count(*) as cnt')
						->from('fm_member')
						->where('userid', $userId)
						->get()
						->row_array();
		return $query['cnt'];
	}

	// SNS 회원 검색
	public function getMemberBySns($snsType, $snsIds)
	{
		// sns 연동 ID 값 유효성 검증
		if (!$snsIds || empty($snsIds)) {
			return;
		}

		// 암호화 필드 복호화
		$key = get_shop_key();
		$decryptField = "
			, AES_DECRYPT(UNHEX(m.cellphone), '{$key}') as cellphone
			, AES_DECRYPT(UNHEX(m.email), '{$key}')  as email
			, AES_DECRYPT(UNHEX(m.phone), '{$key}') as phone
		";

		// 기본 쿼리
		$this->db->select("m.*, mg.group_name, {$decryptField}") 
			->from('fm_member AS m')
			->join('fm_member_group AS mg', 'm.group_seq = mg.group_seq', 'LEFT')
			// 탈퇴 상태가 아닌 모든 상태
			->where(['m.status != ' => 'withdrawal']);
		
		// sns 연동 ID 값이 배열인 경우 다중 일치 쿼리 설정
		if (is_array($snsIds)) {
			$this->db->where_in('m.' . $snsType, $snsIds);
		
		// sns 연동 ID 값이 배열 아닌 단일 일치 쿼리 설정
		} else {
			$this->db->where('m.' . $snsType, $snsIds);
		}

		$result = $this->db->get()->row_array();

		return $result;
	}

	/**
	 * 기본 SNS 앱 타입 업데이트
	 * 페이스북, 트위터에서 기존에 사용하던 기본앱 기능 지원 종료로 해당 SNS 로그인 시 기본앱으로 되어있는 경우 전용앱으로 값을 치환
	 */
	public function setSocialAppType($memberSeq, $snsTypeColumn) {
		return $this->db->set($snsTypeColumn, '1')
				->where('member_seq', $memberSeq)
				->update('fm_member');
	}

	/**
	 * 로그인 횟수 증가 처리
	 */
	public function addLoginCount($memberSeq) {
		return $this->db->set('login_cnt', 'login_cnt + 1')
						->set('lastlogin_date', 'now()')
						->set('login_addr', $_SERVER['REMOTE_ADDR'])
						->where('member_seq', $memberSeq)
						->update('fm_member');
	}

	// 실명인증 가입 여부 조회
	public function countMemberAuthChecked($authCode) {
		return $this->db->select('count(*) AS cnt')
						->from('fm_member')
						->where('auth_code', $authCode)
						->get()
						->row_array();
	}

	// 개인정보 보호 대상 회원 조회 (휴면/탈퇴회원)
	public function getSeparationMember() {
		return $this->db->select('member_seq, userid, status')
						->from('fm_member')
						->where_in('status', ['dormancy', 'withdrawal'])
						->get()
						->result_array();
	}
}

/* End of file membermodel.php */
/* Location: ./app/models/membermodel */
