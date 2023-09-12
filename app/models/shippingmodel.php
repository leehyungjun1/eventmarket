<?php
class shippingmodel extends CI_Model {

	var $shipgroupTable		= 'fm_shipping_grouping';		// 배송그룹 테이블
	var $shipsummaryTable	= 'fm_shipping_group_summary';	// 배송그룹 요약 테이블
	var $shipsetTable		= 'fm_shipping_set';			// 배송설정 테이블
	var $shipoptTable		= 'fm_shipping_option';			// 배송방법 테이블
	var $shipcostTable		= 'fm_shipping_cost';			// 배송금액 테이블
	var $shipzoneTable		= 'fm_shipping_area_detail';	// 배송지역 상세 테이블
	var $shipstoreTable		= 'fm_shipping_store';			// 수령매장 테이블
	var $addressTable		= 'fm_shipping_address';		// 배송지 테이블
	var $nationTable		= 'fm_shipping_nation';			// 해외배송국가 테이블
	var $shiplogTable		= 'fm_order_shipping_log';		// 배송로그 테이블
	var $shipoptlogTable	= 'fm_order_shipping_log_opt';	// 배송구간로그 테이블
	var $shipcostlogTable	= 'fm_order_shipping_log_cost';	// 배송금액로그 테이블

	var $address_icon		= array(						// 매장 아이콘 종류
								'default_store' => array(
									'key' => 'default_store'
									, 'text' => '대표매장'
									, 'selected' => ''
								)
								, 'refund_address' => array(
									'key' => 'refund_address'
									, 'text' => '반송지'
									, 'selected' => ''
								)
								, 'direct_store' => array(
									'key' => 'direct_store'
									, 'text' => '매장수령'
									, 'selected' => ''
								)
								, 'shipping_address' => array(
									'key' => 'shipping_address'
									, 'text' => '매장안내'
									, 'selected' => ''
								)
								, 'o2o_store' => array(
									'key' => 'o2o_store'
									, 'text' => 'POS 연동'
									, 'selected' => ''
								)
							);
	var $store_term_week		= array(					// 매장 영업시간 요일
								'everyday'		=> '매일',
								'weekday'		=> '평일',
								'weekend'		=> '주말',
								'holiday'		=> '공휴일',
								'mon'			=> '월',
								'tue'			=> '화',
								'wed'			=> '수',
								'thu'			=> '목',
								'fri'			=> '금',
								'sat'			=> '토',
								'sun'			=> '일',
							);
	var $store_term_time		= array(					// 매장 영업시간 시간
								'input'			=> '시간 입력',
								'closed'		=> '휴무',
							);
	var $shipping_address_max	= 5; // 입력장소 최대 가능 수

	public function __construct(){

		if(!$_SESSION)
		{
			$_SESSION = $this->session->userdata;
		}
		$this->set_init();
	}

	public function set_init(){

		// 배송설정 코드 정의
		$this->ship_set_code		= array(
			'delivery'			=>	'택배',
			'direct_delivery'	=>	'직접배송',
			'quick'				=>	'퀵서비스',
			'freight'			=>	'화물배송',
			'direct_store'		=>	'매장수령',
			'custom'			=>	'직접입력'
		);

		// 구) 배송설정 코드 정의
		$this->shipping_method_arr	= array(
			'delivery'			=>	'택배',
			'postpaid'			=>	'택배(착불)',
			'direct'			=>	'직접수령',
			'direct_delivery'	=>	'직접배송',
			'quick'				=>	'퀵서비스',
			'freight'			=>	'화물배송',
			'direct_store'		=>	'매장수령',
			'custom'			=>	'직접입력',
			'coupon'			=>	'티켓'
		);

		// 배송설정 타입정의
		$this->shipping_type_arr	= array(
			'std'				=>	'기본',
			'add'				=>	'추가',
			'hop'				=>	'희망배송일',
			'store'				=>	'수령매장'
		);

		// 배송방법 타입 정의
		$this->shipping_otp_type	= array(
			'free'				=>	'무료',
			'fixed'				=>	'고정',
			'amount'			=>	'금액(구간입력)',
			'amount_rep'		=>	'금액(구간반복)',
			'cnt'				=>	'수량(구간입력)',
			'cnt_rep'			=>	'수량(구간반복)',
			'weight'			=>	'무게(구간입력)',
			'weight_rep'		=>	'무게(구간반복)'
		);

		$this->shipping_otp_type_txt = array(
			'free'				=>	'무료',
			'fixed'				=>	'고정',
			'amount'			=>	'금액(입력)',
			'amount_rep'		=>	'금액(반복)',
			'cnt'				=>	'수량(입력)',
			'cnt_rep'			=>	'수량(반복)',
			'weight'			=>	'무게(입력)',
			'weight_rep'		=>	'무게(반복)'
		);

		// 날짜 정의
		$this->weekday			= array(
			0					=>	'Sunday',
			1					=>	'Monday',
			2					=>	'Tuesday',
			3					=>	'Wednesday',
			4					=>	'Thursday',
			5					=>	'Friday',
			6					=>	'Saturday',
		);

		// 배송비 계산 기준
		$this->calcul_type		= array(
			'bundle'			=> '묶음',
			'each'				=> '개별',
			'free'				=> '무료'
		);

		// 배송비 계산 기준
		$this->calcul_type_txt	= array(
			'bundle'			=> '묶음계산(묶음배송)',
			'each'				=> '개별계산(개별배송)',
			'free'				=> '무료계산(묶음배송)'
		);

		// 배송비 선/착불 정보
		$this->prepay_info_txt	= array(
			'all'				=> '<span class="prepay_info_area prepay_info_delivery">'.getAlert('sy002').'</span>/<span class="prepay_info_area prepay_info_postpaid">'.getAlert('sy003').'</span>', //주문 시 결제/착불
			'postpaid'			=> getAlert('sy003'), //착불
			'delivery'			=> getAlert('sy002') //주문 시 결제
		);

		// 배송그룹 요약 타입 정보
		$this->default_type		= array(
			'free'				=> '무료배송',
			'fixed'				=> '배송비',
			'iffree'			=> '조건부 무료배송',
			'ifpay'				=> '조건부 차등배송비'
		);

		// EP데이터 전달용 타입
		$this->ep_ship_type		= array(
			'E'					=> '개별설정',
			'S'					=> '통합설정',
			'G'					=> '설정된 배송그룹'
		);

		// 배송그룹 타입별 알림메시지 코드
		$this->default_type_code_flag = false;

		// 배송그룹 타입별 알림메시지
		$this->default_type_code= array(
			'free'				=> 'dv001',
			'fixed'				=> 'dv002',
			'iffree'			=> 'dv003',
			'ifpay'				=> 'dv004',
			'overseas'			=> 'dv005'
		);
	}

	// ------------- ### 그룹별 리스트별 추출 :: START ### ------------- //

	// 배송설정 그룹 리스트 추출용
	public function shipping_group_list($sc){
		$sc['page'] = ($sc['page']) ? $sc['page'] : '1';

		// 입점사 검색시
		if($sc['provider_seq']){
			$provider_where = "AND grp.shipping_provider_seq = '".$sc['provider_seq']."'";
		}

		// 키워드 검색
		if($sc['keyword']){
			if($sc['search_type'] == 'all' || !$sc['search_type']){
				$keyword = " AND (grp.shipping_group_name like '%" . $sc['keyword'] . "%' OR grp.shipping_group_seq like '%" . $sc['keyword'] . "%')";
			}else{
				$keyword = " AND " . $sc['search_type'] . " = '" . $sc['keyword'] . "'";
			}
		}

		// 배송비 계산 타입 검색
		if($sc['shipping_calcul_type']){
			foreach($sc['shipping_calcul_type'] as $k => $cal_type){
				$freeYN = '';
				if($sc['shipping_calcul_free_yn'][$k] == 'Y'){
					$freeYN = " AND grp.shipping_calcul_free_yn = 'Y'";
				}
				$calcul_type[] = "grp.shipping_calcul_type = '" . $cal_type . "'" . $freeYN;
			}
			$wheres[] = "(" . implode(" OR ", $calcul_type) . ")";
		}

		// 배송가능 국가 검색
		if($sc['delivery_nation']){
			if($sc['delivery_nation'][1])
				$wheres[] = "sur.kr_shipping_yn = 'Y'";

			if($sc['delivery_nation'][2])
				$wheres[] = "sur.gl_shipping_yn = 'Y'";
		}

		// 배송방법 검색
		$nation = array('kr', 'gl');
		foreach($nation as $key => $n){
			if($sc[$n.'_method'] == 'default'){
				if($sc[$n.'_set_code']){
					unset($search_set_code);
					foreach($sc[$n.'_set_code'] as $k => $code){
						$search_set_code[] = "sur.default_set_code = '" . $code . "'";
					}
					$wheres[] = "(" . implode(" OR ", $search_set_code) . ")";
				}
			}else{
				if($sc[$n.'_set_code']){
					unset($search_set_code);
					foreach($sc[$n.'_set_code'] as $k => $code){
						$search_set_code[] = "sur." . $n ."_" . $code . "_yn = 'Y'";
					}
					$wheres[] = "(" . implode(" OR ", $search_set_code) . ")";
				}
			}
		}

		// 기본 배송비 타입 검색
		if($sc['default_type']){
			foreach($sc['default_type'] as $k => $default_type){
				$search_default_type[] = "sur.default_type = '" . $default_type . "'";
			}
			$wheres[] = "(" . implode(" OR ", $search_default_type) . ")";
		}

		// 추가 배송비 사용 검색
		if(count($sc['add_opt_type']) == 1){
			if($sc['add_opt_type'][0] == 'Y' ){
				$search_add_use = "sur.add_opt_type is not null";
			}else{
				$search_add_use = "sur.add_opt_type is null";
			}
			if($search_add_use)		$wheres[] = $search_add_use;
		}

		// 티켓배송그룹 검색
		if($sc['coupon']){
			$wheres[] = "grp.hidden_grp = '" . $sc['coupon'] . "'";
		}else{
			$wheres[] = "grp.hidden_grp = 'N'";
		}

		// 기타 검색
		if($sc['shipping_etc_search']){
			// 상품연결갯수 0개 검색
			if(in_array('goods',$sc['shipping_etc_search'])){
				$wheres[] = "grp.target_goods_cnt = '0'";
			}
			// 본사 위탁배송 연결 그룹 검색
			if(in_array('trust_ship',$sc['shipping_etc_search'])){
				$wheres[] = "grp.trust_goods_cnt > 0";
			}
		}

		if($wheres)		$where = " AND " . implode(" AND ", $wheres);

		// 정렬
		if($sc['orderby']){
			$orderbyTmp = explode("_", $sc['orderby']);
			if(in_array($orderbyTmp[0],array("asc","desc"))){
				foreach($orderbyTmp as $orderK=>$orderV)
					if($orderK > 0) $orderbyTmp2[] = $orderV;

				$orderby	= implode("_",$orderbyTmp2);
				$sort		= $orderbyTmp[0];
			}
		}

		$grp_sql = "
			SELECT
				grp.shipping_group_seq, grp.shipping_group_name,
				grp.default_yn,	grp.provider_shipping_use,
				grp.shipping_calcul_type, grp.shipping_calcul_free_yn,
				grp.target_goods_cnt, grp.target_package_cnt,
				grp.trust_goods_cnt, grp.total_rel_cnt,
				sur.free_shipping_use,
				grp.shipping_provider_seq
			FROM
				" . $this->shipgroupTable . " AS grp LEFT JOIN
				" . $this->shipsummaryTable . " AS sur
				ON grp.shipping_group_seq = sur.shipping_group_seq
			WHERE
				grp.shipping_group_type = 'Y'
				AND grp.shipping_calcul_type != 'dummy'
				" . $keyword . $where . "
				" . $provider_where . "
		";

		if($orderby && $sort){
			$grp_sql .= "ORDER BY " . $orderby . " " .$sort;
		}else{
			$grp_sql .= "ORDER BY grp.default_yn ASC, grp.shipping_group_seq DESC";
		}

		$grpRes = select_page(5,$sc['page'],10,$grp_sql);
		$grpArr	= $grpRes['record'];

		foreach($grpArr as $k => $grp){
			$grpRes['record'][$k]['calcul_type_txt'] = $this->calcul_type[$grp['shipping_calcul_type']];

			// 적용 상품 갯수
			$grpRes['record'][$k]['package_cnt']	= $grp['target_package_cnt'];
			$grpRes['record'][$k]['goods_cnt']		= $grp['target_goods_cnt'];

			$set_sql = "
				SELECT
					st.delivery_nation, st.shipping_set_code, st.default_yn,
					st.shipping_set_name, st.prepay_info, st.delivery_limit,
					st.shipping_set_seq,
					(SELECT opt.shipping_opt_type FROM " . $this->shipoptTable . " AS opt  WHERE st.shipping_group_seq = opt.shipping_group_seq AND st.shipping_set_seq = opt.shipping_set_seq AND opt.shipping_set_type = 'std' LIMIT 1) as stdval,
					(SELECT opt.shipping_opt_type FROM " . $this->shipoptTable . " AS opt  WHERE st.shipping_group_seq = opt.shipping_group_seq AND st.shipping_set_seq = opt.shipping_set_seq AND opt.shipping_set_type = 'add' LIMIT 1) as addval,
					(SELECT opt.shipping_opt_type FROM " . $this->shipoptTable . " AS opt  WHERE st.shipping_group_seq = opt.shipping_group_seq AND st.shipping_set_seq = opt.shipping_set_seq AND opt.shipping_set_type = 'hop' LIMIT 1) as hopval,
					(SELECT str.shipping_store_seq FROM " . $this->shipstoreTable . " AS str  WHERE st.shipping_group_seq = str.shipping_group_seq_tmp AND st.shipping_set_seq = str.shipping_set_seq LIMIT 1) as store,
					reserve_use, reserve_sdate
				FROM " . $this->shipsetTable . " AS st
				WHERE
					st.shipping_group_seq = '" . $grp['shipping_group_seq'] . "'
			";

			$query	= $this->db->query($set_sql);
			$setRes	= $query->result_array();

			if(count($setRes) > 1)	$grpRes['record'][$k]['setting_cnt'] = count($setRes);
			foreach($setRes as $z => $set){
				// 설정명을 입력하지 않고 자동 설정명을 따라갔을경우
				if($this->ship_set_code[$set['shipping_set_code']] != $set['shipping_set_name']){
					$set['set_code_txt'] = $this->ship_set_code[$set['shipping_set_code']];
				}

				// 기본 배송방법이 무료배송인지 판단
				if($set['default_yn'] == 'Y' && $grpRes['record'][$k]['free_shipping_use'] == 'Y'){
					$set['freeship'] = '무료배송';
				}

				// 배송비 타입 텍스트화
				$nouseTxt = '<span class="gray fx11">미사용</span>';
				$set['stdtxt']	= ($set['stdval']) ?	$this->shipping_otp_type_txt[$set['stdval']] : $nouseTxt;
				$set['addtxt']	= ($set['addval']) ?	$this->shipping_otp_type_txt[$set['addval']] : $nouseTxt;
				$set['hoptxt']	= ($set['hopval']) ?	$this->shipping_otp_type_txt[$set['hopval']] : $nouseTxt;
				$set['storetxt']= ($set['store']) ? '무료' : $nouseTxt;
				$set['reservetxt']= ($set['reserve_use'] == 'Y' && $set['reserve_sdate']) ? '설정' : $nouseTxt;

				// 배송지불 타입 텍스트화
				if		($set['prepay_info'] == 'delivery'){
					$set['prepay_info_txt'] = '선불';
				}else if($set['prepay_info'] == 'postpaid'){
					$set['prepay_info_txt'] = '착불';
				}else{
					$set['prepay_info_txt'] = '선불,착불';
				}

				// 기존 배열에 추가
				$grpRes['record'][$k]['setting'][$set['delivery_nation']][] = $set;
			}
		}

		return $grpRes;
	}

	// 배송 기본그룹리스트 검색
	public function get_shipping_base($provider_seq = 1){
		$sql = "SELECT * FROM fm_shipping_grouping WHERE shipping_provider_seq = '" . $provider_seq . "' AND default_yn = 'Y'";
		$query	= $this->db->query($sql);
		$res	= $query->row_array();

		return $res;
	}

	// 전체 배송그룹 리스트 검색
	public function get_shipping_group_list($provider_seq = 0, $params = null) {
		$addWhere		= '';
		$provider_seq	= (int) $provider_seq;
		if ($provider_seq > 0)
			$addWhere	= "(G.shipping_provider_seq = {$provider_seq} or (G.shipping_provider_seq=1 and G.default_yn='Y')) AND";

		if	($params){
			if($params['order_by']){
				$orderby = "ORDER BY G." . $params['order_by'] . " ASC";
			}
		}else{
			$orderby = "ORDER BY G.shipping_group_seq DESC";
		}

		$sql	= "	SELECT *
					FROM
						fm_shipping_grouping G
					INNER JOIN
						fm_shipping_set O
					ON
						G.shipping_group_seq = O.shipping_group_seq AND shipping_calcul_type != 'dummy'
					WHERE
						{$addWhere} hidden_grp = 'N'
					{$orderby}
				";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		$shippingGroupList	= array();
		foreach ($result as $row) {
			if (!isset($shippingGroupList[$row['shipping_group_seq']])) {
				$nowShippingGroup	= array();

				if ($provider_seq > 0 && $row['shipping_provider_seq'] == 1){
					$row['shipping_group_name'] = "[위탁배송]".$row['shipping_group_name'];
				}

				$nowShippingGroup['shipping_group_seq']				= $row['shipping_group_seq'];
				$nowShippingGroup['shipping_group_name']			= $row['shipping_group_name'];
				$nowShippingGroup['shipping_group_type']			= $row['shipping_group_type'];
				$nowShippingGroup['shipping_calcul_type']			= $row['shipping_calcul_type'];
				$nowShippingGroup['shipping_calcul_free_yn']		= $row['shipping_calcul_free_yn'];
				$nowShippingGroup['shipping_std_free_yn']			= $row['shipping_std_free_yn'];
				$nowShippingGroup['shipping_add_free_yn']			= $row['shipping_add_free_yn'];
				$nowShippingGroup['shipping_hop_free_yn']			= $row['shipping_hop_free_yn'];
				$nowShippingGroup['sendding_scm_type']				= $row['sendding_scm_type'];
				$nowShippingGroup['sendding_address_seq']			= $row['sendding_address_seq'];
				$nowShippingGroup['refund_scm_type']				= $row['refund_scm_type'];
				$nowShippingGroup['refund_address_seq']				= $row['refund_address_seq'];
				$nowShippingGroup['provider_shipping_use']			= $row['provider_shipping_use'];
				$nowShippingGroup['shipping_provider_seq']			= $row['shipping_provider_seq'];
				$nowShippingGroup['default_yn']						= $row['default_yn'];

				$nowShippingGroup['target_goods_cnt']				= $row['target_goods_cnt'];
				$nowShippingGroup['target_package_cnt']				= $row['target_package_cnt'];
				$nowShippingGroup['trust_goods_cnt']				= $row['trust_goods_cnt'];
				$nowShippingGroup['total_rel_cnt']					= $row['total_rel_cnt'];

				$nowShippingGroup['shipping_method_list']['korea']	= array();
				$nowShippingGroup['shipping_method_list']['global']	= array();

				$shippingGroupList[$row['shipping_group_seq']]		= $nowShippingGroup;
			}

			$shippingMethodList										= array();
			$shippingMethodList['shipping_set_code']				= $row['shipping_set_code'];
			$shippingMethodList['shipping_set_name']				= $row['shipping_set_name'];
			$shippingMethodList['prepay_info']						= $row['prepay_info'];
			$shippingMethodList['delivery_type']					= $row['delivery_type'];
			$shippingMethodList['delivery_limit']					= $row['delivery_limit'];
			$shippingMethodList['add_use']							= $row['add_use'];
			$shippingMethodList['hop_use']							= $row['hop_use'];
			$shippingMethodList['reserve_use']						= $row['reserve_use'];
			$shippingMethodList['store_use']						= $row['store_use'];
			$shippingMethodList['delivery_std_type']				= $row['delivery_std_type'];
			$shippingMethodList['delivery_std_input']				= $row['delivery_std_input'];

			array_push($shippingGroupList[$row['shipping_group_seq']]['shipping_method_list'][$row['delivery_nation']], $shippingMethodList);

		}

		return $shippingGroupList;
	}

	// 배송그룹 연결 상품 추출
	public function get_shipping_group_rel_goods($grp_seq){
		$sql = "
			select
				g.*, gi.image
			from
				fm_goods as g left join fm_goods_image as gi
				on g.goods_seq = gi.goods_seq and g.relation_image_size = gi.image_type and gi.cut_number = '1'
			where
				g.shipping_group_seq = '" . $grp_seq . "' AND
				g.goods_kind = 'goods' AND
				g.goods_type = 'goods'
		";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		return $result;
	}

	// 기본 배송그룹 생성하기 + 티켓 ##
	public function set_base_shipping_group($provider_seq=1, $type='delivery'){

		// 기본 배송그룹 체크
		if($type == 'delivery'){
			$this->db->select("*");
			$this->db->where(array('default_yn'=>'Y','shipping_provider_seq'=>$provider_seq));
			$query		= $this->db->get('fm_shipping_grouping');
			$result		= $query->result_array();
			if(count($result) > 0){
				return false;
			}
		}elseif($type == 'o2o'){
			$this->db->select("*");
			$this->db->where(array('hidden_grp'=>'O','shipping_provider_seq'=>$provider_seq));
			$query		= $this->db->get('fm_shipping_grouping');
			$result		= $query->result_array();
			if(count($result) > 0){
				return false;
			}
		}else{
			$this->db->select("*");
			$this->db->where(array('hidden_grp'=>'Y','shipping_provider_seq'=>$provider_seq));
			$query		= $this->db->get('fm_shipping_grouping');
			$result		= $query->result_array();
			if(count($result) > 0){
				return false;
			}
		}

		$grp_data['shipping_group_name']	= ($type=='delivery') ? '기본배송그룹' : (($type=='o2o') ? '오프라인매장배송그룹' : '티켓배송그룹');
		$grp_data['shipping_calcul_type']	= 'free';
		$grp_data['shipping_provider_seq']	= $provider_seq;
		$grp_data['system_memo']			= '기본그룹 자동생성';
		$grp_data['default_yn']				= ($type=='delivery') ? 'Y' : 'N';
		$grp_data['hidden_grp']				= ($type=='delivery') ? 'N' : (($type=='o2o') ? 'O' : 'Y');
		$grp_data['regist_date']			= date('Y-m-d H:i:s');
		$shipping_group_seq	= $this->set_shipping_group($grp_data);

		$set_data['shipping_group_seq']		= $shipping_group_seq;
		$set_data['default_yn']				= 'Y';
		$set_data['shipping_set_code']		= ($type=='o2o') ? 'direct_store' : $type;
		$set_data['shipping_set_name']		= ($type=='delivery') ? '택배' : (($type=='o2o') ? '매장' : '티켓');
		$set_data['prepay_info']			= 'all';
		$set_data['delivery_nation']		= 'korea';
		$set_data['delivery_type']			= 'basic';
		$set_data['delivery_limit']			= 'unlimit';
		$set_data['delivery_std_input']		= '무료';
		$shipping_set_seq	= $this->set_shipping_set($set_data);

		$opt_data['shipping_group_seq']		= $shipping_group_seq;
		$opt_data['shipping_set_seq']		= $shipping_set_seq;
		$opt_data['shipping_set_code']		= $set_data['shipping_set_code'];
		$opt_data['shipping_set_name']		= $set_data['shipping_set_name'];
		$opt_data['shipping_set_type']		= 'std';
		$opt_data['shipping_opt_type']		= 'free';
		$opt_data['shipping_provider_seq']	= $provider_seq;
		$opt_data['delivery_limit']			= $set_data['delivery_limit'];
		$opt_data['shipping_calcul_type']	= $grp_data['shipping_calcul_type'];
		$opt_data['default_yn']				= 'Y';
		$opt_data['section_st']				= 0;
		$opt_data['section_ed']				= 0;
		$shipping_opt_seq	= $this->set_shipping_opt($opt_data);

		$cost_data['shipping_group_seq_tmp']= $shipping_group_seq;
		$cost_data['shipping_opt_seq']		= $shipping_opt_seq;
		$cost_data['shipping_area_name']	= '대한민국';
		$cost_data['shipping_cost']			= '0';
		$shipping_cost_seq	= $this->set_shipping_cost($cost_data);

		$summ_data['shipping_group_seq']	= $shipping_group_seq;
		$summ_data['free_shipping_use']		= 'Y';
		$summ_data['kr_shipping_yn']		= ($type=='delivery') ? 'Y' : 'N';
		$summ_data['kr_delivery_yn']		= ($type=='delivery') ? 'Y' : 'N';
		$area_detail_seq = $this->set_shipping_group_summary($summ_data);

		// O2O 일경우 매장수령정보 강제 추가
		if($type == 'o2o'){
			// o2o 배송지 매장수령 강제추가
			$this->load->library('o2o/o2oinitlibrary');
			$this->o2oinitlibrary->init_shipping_store();
		}

	}

	// ------------- ### 그룹별 리스트별 추출 :: END ### ------------- //

	// ------------- ### 그룹별 데이터 추출 :: START ### ------------- //

	// 프론트용 그룹 정보 전체 추출
	public function get_goods_shipping($grp_seq, $set_seq){

		$sql = "
			SELECT
				MAX(ship_cost.shipping_cost) AS max_cost,
				MIN(ship_cost.shipping_cost) AS min_cost,
				if(ship_cost.shipping_today_yn='Y',MAX(ship_cost.shipping_cost_today),0) AS max_today_cost,
				if(ship_cost.shipping_today_yn='Y',MIN(ship_cost.shipping_cost_today),0) AS min_today_cost,
				ship_opt.shipping_set_type,
				ship_opt.shipping_opt_type,
				ship_set.*
			FROM
				" . $this->shipsetTable . " AS ship_set,
				" . $this->shipoptTable . " AS ship_opt,
				" . $this->shipcostTable . " AS ship_cost
			WHERE
				ship_set.shipping_set_seq = ship_opt.shipping_set_seq AND
				ship_opt.shipping_opt_seq = ship_cost.shipping_opt_seq AND
				ship_set.shipping_group_seq = '" . $grp_seq . "'
			GROUP BY
				ship_set.shipping_set_seq, ship_opt.shipping_set_type
		";

		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		foreach($result as $key => $ship){
			$ship['otp_type_txt'] = $this->shipping_otp_type_txt[$ship['shipping_opt_type']];

			//금액정의
			if($ship['max_cost'] == $ship['min_cost'] && $ship['min_cost'] > 0){
				$cost_txt = Number_format($ship['min_cost']) . '원';
			}

			//문구정의
			if($ship['shipping_set_type'] == 'std' && $ship['delivery_limit'] == 'limit'){
				$zone_txt = '배송가능지역';
			}else if($ship['shipping_set_type'] != 'std'){
				$zone_txt = '지역';
			}
			$return[$ship['shipping_set_seq']][$ship['shipping_set_type']] = $ship;
		}

		return $return;
	}

	// ------------- ### 배송그룹요약 :: fm_shipping_group_summary :: START ### ---------- //

	// 배송그룹요약 데이터 추출
	public function get_shipping_group_summary($group_seq){
		$this->db->select("*");
		$this->db->where(array('shipping_group_seq'=>$group_seq));
		$query		= $this->db->get($this->shipsummaryTable);
		$result		= $query->row_array();

		/*
		$result['default_type_txt'] = $this->default_type[$result['default_type']];
		if($result['default_type'] == 'fixed'){
			$result['default_type_txt'] = $result['default_type_txt'] . ' ' . get_currency_price($result['fixed_cost'],2);
		}
		*/

		if			($result['default_type'] == 'fixed'){ // 배송비
			$result['default_type_txt'] = getAlert('dv002',get_currency_price($result['fixed_cost']));
		}else if	($result['default_type'] == 'ifpay'){ // 조건부 차등배송비
			$result['default_type_txt'] = getAlert('dv004');
		}else if	($result['default_type'] == 'iffree'){ // 조건부 무료배송
			$result['default_type_txt'] = getAlert('dv003');
		}else{ // 무료배송
			$result['default_type_txt'] = getAlert('dv001');
		}

		return $result;
	}

	/**
	 * 배송그룹 요약 데이터를 group_seq 배열을 통해 가져온다.
	 * @param array $group_seq
	 */
	public function get_shipping_group_summary_list($group_seqs)
	{
	    $this->db->select("*");
	    $this->db->where_in('shipping_group_seq', $group_seqs);
	    $query		= $this->db->get($this->shipsummaryTable);
	    $result		= $query->result_array();
	    if(count($result)>0) {
	        foreach($result as $key => $row) {
                if ($result[$key]['default_type'] == 'fixed'){ // 배송비
                    $result[$key]['default_type_txt'] = getAlert('dv002',get_currency_price($result[$key]['fixed_cost']));
                }else if	($result[$key]['default_type'] == 'ifpay'){ // 조건부 차등배송비
                    $result[$key]['default_type_txt'] = getAlert('dv004');
                }else if	($result[$key]['default_type'] == 'iffree'){ // 조건부 무료배송
                    $result[$key]['default_type_txt'] = getAlert('dv003');
	            }else{ // 무료배송
	                $result[$key]['default_type_txt'] = getAlert('dv001');
	            }
	        }
	    }
	    return $result;
	}

	// 배송그룹 요약 저장
	public function set_shipping_group_summary($data){
		$summary = $this->get_shipping_group_summary($data['shipping_group_seq']);
		if($summary['shipping_summary_seq']){
			$summury_seq	= $summary['shipping_summary_seq'];
			foreach($data as $k => $col){
				$this->db->set($k, $col);
			}
			$this->db->where('shipping_summary_seq',$summury_seq);
			$this->db->update($this->shipsummaryTable);
		}else{
			$result			= $this->db->insert($this->shipsummaryTable, $data);
			$summury_seq	= $this->db->insert_id();
		}

		return $summury_seq;
	}

	// 배송그룹 요약 삭제
	public function del_shipping_group_summary($group_seq){
		$this->db->delete($this->shipsummaryTable, array('shipping_group_seq'=>$group_seq));
	}

	// ------------- ### 배송그룹 :: fm_shipping_grouping :: START ### ---------- //

	// 배송그룹 데이터 추출 - 1개만 추출
	public function get_shipping_group($group_seq){
		$this->db->select("*");
		$this->db->where(array('shipping_group_seq'=>$group_seq));
		$query		= $this->db->get($this->shipgroupTable);
		$result		= $query->row_array();

		return $result;
	}

	// 배송그룹 간단 추출 (입점사 번호로 추출)
	public function get_shipping_group_simple($provider_seq, $type='N'){
		$this->db->select("*");
		$this->db->where(array('shipping_provider_seq'=>$provider_seq,'hidden_grp'=>$type, 'shipping_calcul_type != ' => 'dummy'));
		$query		= $this->db->get($this->shipgroupTable);
		$result		= $query->result_array();

		if(!$result){ // 기본 배송그룹 없을경우 강제로 생성
			if($type == 'Y')	$this->set_base_shipping_group($provider_seq, 'coupon');
			elseif($type == 'O')	$this->set_base_shipping_group($provider_seq, 'o2o');
			else				$this->set_base_shipping_group($provider_seq, 'delivery');

			// 재 검색
			$this->db->select("*");
			$this->db->where(
				array('shipping_provider_seq'=>$provider_seq,'hidden_grp'=>$type , 'shipping_calcul_type != ' => 'dummy')
			);
			$query		= $this->db->get($this->shipgroupTable);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 배송 그룹 저장
	public function set_shipping_group($data){
		if($_POST['shipping_group_seq'] > 0){
			$data['shipping_group_seq'] = $_POST['shipping_group_seq'];
		} else if ($_POST['shipping_group_dummy_seq'] > 0){
			$data['shipping_group_seq'] = $_POST['shipping_group_dummy_seq'];
		}
		// 수정 시
		if($data['shipping_group_seq']){
			$upsql = "
				UPDATE
					" . $this->shipgroupTable . "
				SET
					shipping_group_name = '" . $data['shipping_group_name'] . "',
					shipping_group_type = '" . $data['shipping_group_type'] . "',
					shipping_calcul_type = '" . $data['shipping_calcul_type'] . "',
					shipping_calcul_free_yn = '" . $data['shipping_calcul_free_yn'] . "',
					shipping_std_free_yn = '" . $data['shipping_std_free_yn'] . "',
					shipping_add_free_yn = '" . $data['shipping_add_free_yn'] . "',
					shipping_hop_free_yn = '" . $data['shipping_hop_free_yn'] . "',
					sendding_scm_type = '" . $data['sendding_scm_type'] . "',
					sendding_address_seq = '" . $data['sendding_address_seq'] . "',
					refund_scm_type = '" . $data['refund_scm_type'] . "',
					refund_address_seq = '" . $data['refund_address_seq'] . "',
					provider_shipping_use = '" . $data['provider_shipping_use'] . "',
					shipping_provider_seq = '" . $data['shipping_provider_seq'] . "',
					admin_memo = '" . $data['admin_memo'] . "',
					system_memo = concat('" . $data['system_memo'] . "<br/>',ifnull(system_memo,'')),
					temporary_seq = '" . $data['temporary_seq'] . "',
					default_yn = '" . $data['default_yn'] . "',
					update_date = '" . $data['update_date'] . "'
				WHERE
					shipping_group_seq = '" . $data['shipping_group_seq'] . "'
			";
			$this->db->query($upsql);
			$group_seq = $data['shipping_group_seq'];
		}else{ // 저장 시
			$result		= $this->db->insert($this->shipgroupTable, $data);
			$group_seq	= $this->db->insert_id();
		}

		return $group_seq;
	}

	// 배송 그룹 삭제
	public function del_shipping_group($group_seq){

		$provider_seq	= $this->providerInfo['provider_seq'];
		if(!$provider_seq) $provider_seq = 1;

		// 기본 그룹 추출
		$base_sql = "SELECT shipping_group_seq FROM fm_shipping_grouping WHERE shipping_provider_seq = ? AND default_yn = 'Y' LIMIT 1";
		$query	= $this->db->query($base_sql,$provider_seq);
		$res	= $query->row_array();
		if(!$res['shipping_group_seq']){
			return false;
		}else{
			$base_grp_seq = $res['shipping_group_seq'];
		}

		// 삭제시 해당된 상품을 기본 그룹으로 이동
		$sql = "UPDATE fm_goods SET shipping_group_seq = '" . $base_grp_seq . "' WHERE shipping_group_seq = '" . $group_seq . "'";
		$this->db->query($sql);

		// 배송그룹 연결 상품 재조정
		$this->group_cnt_adjust();

		// 배송그룹, 배송요약, 배송설정, 배송방법, 배송금액, 배송지역상세, 수령매장
		$this->db->delete($this->shipgroupTable, array('shipping_group_seq'=>$group_seq));
		$this->db->delete($this->shipsummaryTable, array('shipping_group_seq'=>$group_seq));
		$this->db->delete($this->shipsetTable, array('shipping_group_seq'=>$group_seq));
		$this->db->delete($this->shipoptTable, array('shipping_group_seq'=>$group_seq));
		$this->db->delete($this->shipcostTable, array('shipping_group_seq_tmp'=>$group_seq));
		$this->db->delete($this->shipzoneTable, array('shipping_group_seq_tmp'=>$group_seq));
		$this->db->delete($this->shipstoreTable, array('shipping_group_seq_tmp'=>$group_seq));

		return true;
	}

	// 배송그룹 리셋 -> 수정시 사용 (set 제외)
	public function reset_shipping_group($group_seq){
		$this->db->delete($this->shipsummaryTable, array('shipping_group_seq'=>$group_seq));
		//$this->db->delete($this->shipoptTable, array('shipping_group_seq'=>$group_seq));
		//$this->db->delete($this->shipcostTable, array('shipping_group_seq_tmp'=>$group_seq));
		//$this->db->delete($this->shipzoneTable, array('shipping_group_seq_tmp'=>$group_seq));
		$this->db->delete($this->shipstoreTable, array('shipping_group_seq_tmp'=>$group_seq));
	}

	// ------------- ### 배송설정 :: fm_shipping_set :: START ### ------------- //

	// 배송 설정 로드 - 수정용
	public function load_shipping_set($grp_seq, $limit = 0){
		$sql = "
			SELECT *
			FROM " . $this->shipsetTable . "
			WHERE shipping_group_seq = '" . $grp_seq . "'
			ORDER BY shipping_set_seq ASC
			LIMIT " . $limit . ",1
		";
		$query	= $this->db->query($sql);
		$setRes	= $query->row_array();

		return $setRes;
	}

	// 배송그룹당 하위 목록 추출
	public function load_shipping_set_list($grp_seq, $params){

		// params에 따른 where절 조건 생성
		if	($params['area_detail_address_txt']){
			$addWhere	.= "AND ship_zone.area_detail_address_txt = ?";
			$addBind[]	= $params['area_detail_address_txt'];
			$nation_type = 'global';
		}
		if	($params['delivery_nation']){
			$addWhere	.= "AND ship_set.delivery_nation = ?";
			$addBind[]	= $params['delivery_nation'];
			$nation_type = $params['delivery_nation'];
		}
		if	($params['default_yn']){
			$addWhere	.= " AND ship_set.default_yn = ?";
			$addBind[]	= $params['default_yn'];
			$default_yn	= $params['default_yn'];
		}
		if	($params['npay_order_possible']){
			$addWhere	.= " AND ship_set.npay_order_possible = ?";
			$addBind[]				= $params['npay_order_possible'];
			$npay_order_possible	= $params['npay_order_possible'];
		}
		if	($params['talkbuy_order_possible']){
			$addWhere	.= " AND ship_set.talkbuy_order_possible = ?";
			$addBind[]				= $params['talkbuy_order_possible'];
			$talkbuy_order_possible	= $params['talkbuy_order_possible'];
		}
		//검색 조건 추가 sjp - 2017-03-20
		if	($params['shipping_set_code']){
			$addWhere	.= " AND ship_set.shipping_set_code = ?";
			$addBind[]				= $params['shipping_set_code'];
		}

		//선불/착불 검색조건 추가(오픈마켓 주문수집용)
		if ($params['prepay_info']){
		   $addWhere .= " AND ship_set.prepay_info in('all', ?)";
		   $addBind[] = $params['prepay_info'];
		}

		// 정렬 추가 sjp - 2017-03-20
		$orderByType		= '';
		if	($params['default_first'] === true)
			$orderByType	= 'defaultYnDesc';


		switch($orderByType) {
			case	'defaultYnDesc' :
				$orderBy	= "ORDER BY ship_set.default_yn DESC";
				break;
			default :
				$orderBy	= "";

		}

		if ($params['prepay_info']){
		   $orderBy .= " LIMIT 1";
		}


		if	($params['direct_store'] != 'Y'){
			// set_info 목록 추출
			$sql = "
				SELECT ship_set.*
				FROM   " . $this->shipsetTable . " AS ship_set
					   LEFT JOIN " . $this->shipoptTable . " AS ship_opt
							  ON ship_set.shipping_set_seq = ship_opt.shipping_set_seq
					   LEFT JOIN " . $this->shipcostTable . " AS ship_cost
							  ON ship_opt.shipping_opt_seq = ship_cost.shipping_opt_seq
					   LEFT JOIN " . $this->shipzoneTable . " AS ship_zone
							  ON ship_cost.shipping_cost_seq = ship_zone.shipping_cost_seq
				WHERE  ship_set.shipping_group_seq = '" . $grp_seq . "'
					   " . $addWhere . "
				GROUP  BY ship_set.shipping_set_seq
				{$orderBy}
			";
			$query		= $this->db->query($sql, $addBind);
			$set_info	= $query->result_array();

			// 가공함수 호출 및 결과 재배열
			if	($set_info) foreach($set_info as $k => $info){
				$result[$info['shipping_set_seq']]	= $this->shipping_set_detail_proc($info);
			}
		}

		// 매장수령 목록 추출
		$store_sql = "
			SELECT ship_set.*
			FROM   " . $this->shipsetTable . " AS ship_set,
				   " . $this->shipstoreTable . " AS ship_store
			WHERE  ship_set.shipping_set_seq = ship_store.shipping_set_seq
				   AND shipping_group_seq = '" . $grp_seq . "' ";
		if($nation_type)		$store_sql .= "AND ship_set.delivery_nation = '" . $nation_type . "'";
		if($default_yn)			$store_sql .= "AND ship_set.default_yn = '" . $default_yn . "'";
		if($npay_order_possible) $store_sql .= "AND ship_set.npay_order_possible = '" . $npay_order_possible . "'";
		if($talkbuy_order_possible) $store_sql .= "AND ship_set.talkbuy_order_possible = '" . $talkbuy_order_possible . "'";
		$store_sql .= "GROUP  BY ship_set.shipping_set_seq";
		$query		= $this->db->query($store_sql);
		$store_info	= $query->result_array();

		// 매장수령 데이터 추출
		if	($store_info) foreach($store_info as $k => $info){
			$result[$info['shipping_set_seq']]	= $this->shipping_set_detail_proc($info);
		}

		return $result;
	}

	// 배송설정 set당 하위 목록 추출
	public function load_shipping_set_detail($set_seq){
		$set_info = $this->get_shipping_set($set_seq, 'shipping_set_seq');

		return $this->shipping_set_detail_proc($set_info);
	}

	// 배송설정 set당 하위 목록 추출
	public function shipping_set_detail_proc($set_info){

		$params['shipping_group_seq']			= $set_info['shipping_group_seq'];
		$params['shipping_set_seq']				= $set_info['shipping_set_seq'];
		$params['default_yn']					= $set_info['default_yn'];
		$params['delivery_nation']				= $set_info['delivery_nation'];
		$params['delivery_type']				= $set_info['delivery_type'];
		$params['shipping_set_code']			= $set_info['shipping_set_code'];
		$params['custom_set_use']				= ($this->ship_set_code[$set_info['shipping_set_code']] == $set_info['shipping_set_name']) ? 'N' : 'Y';
		$params['shipping_set_name']			= $set_info['shipping_set_name'];
		$params['prepay_info']					= $set_info['prepay_info'];
		$params['std_use']						= ($set_info['store_use']=='Y') ? 'N' : 'Y';
		$params['add_use']						= $set_info['add_use'];
		$params['hop_use']						= $set_info['hop_use'];
		$params['reserve_use']					= $set_info['reserve_use'];
		$params['store_use']					= $set_info['store_use'];
		$params['reserve_sdate']				= $set_info['reserve_sdate'];
		$params['npay_order_possible']			= $set_info['npay_order_possible'];
		$params['npay_order_impossible_msg']	= $set_info['npay_order_impossible_msg'];
		$params['talkbuy_order_possible']		= $set_info['talkbuy_order_possible'];
		$params['talkbuy_order_impossible_msg']	= $set_info['talkbuy_order_impossible_msg'];
		$params['delivery_limit']				= $set_info['delivery_limit'];
		$params['hopeday_required']				= $set_info['hopeday_required'];
		$params['hopeday_limit_set']			= $set_info['hopeday_limit_set'];
		$params['refund_shiping_cost']			= $set_info['refund_shiping_cost'];
		$params['swap_shiping_cost']			= $set_info['swap_shiping_cost'];
		$params['shiping_free_yn']				= $set_info['shiping_free_yn'];
		$params['hopeday_limit_val_'.$set_info['hopeday_limit_set']] = $set_info['hopeday_limit_val'];
		for($i=0;$i<=6;$i++){
			if(substr($set_info['hopeday_limit_week'],$i,1) == '1')
				$tmp_week[] = $i;
		}
		$params['hopeday_limit_week'] = $tmp_week;
		$params['hopeday_limit_repeat_day'] = $set_info['hopeday_limit_repeat_day'];

		$tmp_limit_day = unserialize($set_info['limit_day_serialize']);
		$idx = 0;
		foreach($tmp_limit_day as $year => $days){
			$year_arr[$idx] = $year;
			unset($tmp_day);
			foreach($days as $dayKey => $day){
				$tmp_day[] = $day;
			}
			$day_arr[$idx] = implode(', ',$tmp_day);
			$idx++;
		}
		$params['hope_year']				= $year_arr;
		$params['hopeday_limit_day']		= $day_arr;
		$params['delivery_std_type']		= $set_info['delivery_std_type'];
		$params['delivery_std_input']		= $set_info['delivery_std_input'];
		$params['delivery_add_type']		= $set_info['delivery_add_type'];
		$params['delivery_add_input']		= $set_info['delivery_add_input'];
		$params['delivery_hop_type']		= $set_info['delivery_hop_type'];
		$params['delivery_hop_input']		= $set_info['delivery_hop_input'];
		$params['delivery_reserve_type']	= $set_info['delivery_reserve_type'];
		$params['delivery_reserve_input']	= $set_info['delivery_reserve_input'];
		$params['delivery_store_type']		= $set_info['delivery_store_type'];
		$params['delivery_store_input']		= $set_info['delivery_store_input'];

		// 수령매장 추출
		if($params['store_use']=='Y'){
			$store_info = $this->get_shipping_join_store($set_info['shipping_set_seq']);
			foreach($store_info as $s => $store){
				// o2o 수령매장 추가
				$this->load->library('o2o/o2oinitlibrary');
				$this->o2oinitlibrary->init_get_shipping_join_store($store);

				// 창고 연결의 경우 정보 추출
				$params['shipping_store_use'][$s] = 'Y';
				if($store['store_type'] == 'scm'){

					$tmp_sc['wh_seq'] = $store['shipping_address_seq'];
					$scm_return = $this->shipping_warehouse_list($tmp_sc,'limit');
					$store	= array_merge($store, $scm_return);
					$params['shipping_store_name'][$s] = $store['address_name'];

					// 창고 사용여부 검색
					if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
					$use_wh_seqs = array_keys($this->scm_cfg['use_warehouse']);
					// 노출여부 결정
					if(array_search($tmp_sc['wh_seq'],$use_wh_seqs) === false)
						$params['shipping_store_use'][$s] = 'N';
				}else{
					$params['shipping_store_name'][$s] = $store['shipping_store_name'];
				}
				$params['shipping_address_seq'][$s] = $store['shipping_address_seq'];
				$params['shipping_address_category'][$s] = $store['address_category'];
				$params['store_phone'][$s] = $store['shipping_phone'];
				$params['shipping_address_nation'][$s] = ($store['address_nation']=='korea') ? 'N' : 'Y';
				if($store['address_zipcode']){
					if($store['address_nation']=='korea'){
						$tmpAddress = ($store['address_type']=='street') ? $store['address_street'] : $store['address'];
						$params['shipping_address_full'][$s] = '(' . $store['address_zipcode'] . ') ' . $tmpAddress . ' ' . $store['address_detail'];
					}else{
						$params['shipping_address_full'][$s] = '(' . $store['address_zipcode'] . ') ' . $store['international_country'] . ' ' . $store['international_town_city'] . ' ' . $store['international_county'] . ' ' . $store['international_address'];
					}
				}
				$params['store_supply_set'][$s] = $store['store_supply_set'];
				$params['store_supply_set_view'][$s] = $store['store_supply_set_view'];
				$params['store_supply_set_order'][$s] = $store['store_supply_set_order'];
				$params['store_scm_type'][$s] = $store['store_scm_type'];
				$params['store_type'][$s] = $store['store_type'];
				$params['store_scm_seq'][$s] = $store['store_scm_seq'];

				// 상품 정보가 있을경우 재고 추출
				if($_GET['goods_seq']){
					if($store['store_supply_set'] == 'Y'){
						$this->load->model('scmmodel');
						if	($this->scmmodel->chkScmConfig(true)){
							$sc['wh_seq']		= $store['store_scm_seq'];
							$sc['goods_seq']	= $_GET['goods_seq'];
							$sc['get_type']		= 'wh';
							$wh_res			= $this->scmmodel->get_location_stock($sc);
							$wh_stock		= $wh_res[0];
							$store_stock	= $wh_stock['ea'];
						}
					}else{
						$sql			= "select stock from fm_goods_supply where goods_seq = '".$_GET['goods_seq']."'";
						$query			= $this->db->query($sql);
						$goods_stock	= $query->row_array();
						$store_stock	= $goods_stock['stock'];
					}

					$params['store_stock'][$s] = $store_stock;
				}
			}
		}

		// 배송방법 추출
		$opt_info = $this->get_shipping_opt($set_info['shipping_set_seq'],'shipping_set_seq');

		// 배송방법 loop
		foreach($opt_info as $k => $opt){
			$set_type = $opt['shipping_set_type'];

			$params['shipping_opt_seq_list'][$set_type][]	= $opt['shipping_opt_seq'];

			$params['shipping_opt_type'][$set_type] = $opt['shipping_opt_type'];
			$params['shipping_opt_type_txt'][$set_type] = $this->shipping_otp_type_txt[$opt['shipping_opt_type']];

			// 단위 지정
			if($params['shipping_opt_type'][$set_type] == 'cnt' || $params['shipping_opt_type'][$set_type] == 'cnt_rep'){
				$params['shipping_opt_unit'][$set_type] = '개';
			}else if($params['shipping_opt_type'][$set_type] == 'weight' || $params['shipping_opt_type'][$set_type] == 'weight_rep'){
				$params['shipping_opt_unit'][$set_type] = 'Kg';
			}else{
				$params['shipping_opt_unit'][$set_type] = null;
			}

			// 금액 관련 처리
			if($opt['shipping_opt_type'] == 'amount' || $opt['shipping_opt_type'] == 'amount_rep' || $opt['shipping_opt_type'] == 'fixed'){
				$params['section_st'][$set_type][] = $opt['section_st'];
				$params['section_ed'][$set_type][] = $opt['section_ed'];
			}else{
				$params['section_st'][$set_type][] = $opt['section_st'];
				$params['section_ed'][$set_type][] = $opt['section_ed'];
			}

			//배송금액 추출
			$cost_info = $this->get_shipping_cost($opt['shipping_opt_seq'],'shipping_opt_seq');

			foreach($cost_info as $c => $cost){

				$params['shipping_cost_seq_list'][$opt['shipping_opt_seq']][]	= $cost['shipping_cost_seq'];

				$params['shipping_cost'][$set_type][] = $cost['shipping_cost'];
				$params['shipping_cost_seq'][$set_type][] = $cost['shipping_cost_seq'];
				$params['shipping_area_name'][$set_type][$c] = $cost['shipping_area_name'];
				if($set_type == 'hop'){
					$params['today_yn'][$c] = $cost['shipping_today_yn'];
					$params['shipping_today_yn'][$c] = $cost['shipping_today_yn'];
					if($cost['shipping_today_yn'] == 'Y'){
						$params['shipping_cost_today'][$set_type][] = $cost['shipping_cost_today'];
						$params['shipping_cost_today_front'][$set_type][] = $cost['shipping_cost_today'];
					}else{
						$params['shipping_cost_today_front'][$set_type][] = $cost['shipping_cost_today'];
					}
				}

				// 배송지역 상세 추출 //for delete
				if($set_info['delivery_nation'] != 'korea'){
					$zone_info = $this->get_shipping_zone($cost['shipping_cost_seq'],'shipping_cost_seq');
					foreach($zone_info as $z => $zone){
						$params['sel_address_join'][$set_type][$c][$z] = $zone['area_detail_address_join'];
						$params['sel_address_txt'][$set_type][$c][$z] = $zone['area_detail_address_txt'];
						$params['sel_address_zibun'][$set_type][$c][$z] = $zone['area_detail_address_zibun'];
						$params['sel_address_street'][$set_type][$c][$z] = $zone['area_detail_address_street'];
					}
				}

				$zone_count = $this->get_shipping_zone_count($cost['shipping_cost_seq'],'shipping_cost_seq');
				$params['zone_count'][$set_type][$c] = $zone_count[0]['shipping_zone_count'];
				$params['zone_cost_seq'][$set_type][$c] = $cost['shipping_cost_seq'];
			}
		}

		return $params;
	}

	// 배송 설정 추출
	public function get_shipping_set($seq, $type='shipping_group_seq', $params='')
	{
		$this->db->select("*");
		$this->db->where(array($type=>$seq));
		if($params){
			unset($wheres);
			foreach($params as $key => $val){
				$wheres[$key] = $val;
			}
			$this->db->where($wheres);
		}
		$this->db->order_by("shipping_set_seq", "asc");
		$query		= $this->db->get($this->shipsetTable);
		if($type == 'shipping_set_seq'){
			$result		= $query->row_array();
		}else{
			$result		= $query->result_array();
		}
		return $result;
	}

	// 배송 설정 저장
	public function set_shipping_set($data){
		// 수정 시
		if($data['shipping_set_seq']){
			$set_seq = $data['shipping_set_seq'];
			unset($data['shipping_set_seq']);
			$this->db->where('shipping_set_seq',$set_seq);
			$this->db->update($this->shipsetTable, $data);
		}else{ // 저장 시
			$result		= $this->db->insert($this->shipsetTable, $data);
			$set_seq	= $this->db->insert_id();
		}

		return $set_seq;
	}

	// 배송 설정 삭제
	public function del_shipping_set($set_seq){
		$this->db->delete($this->shipsetTable, array('shipping_set_seq'=>$set_seq));
	}

	// ------------- ### 배송방법 :: fm_shipping_opt :: START ### ------------- //

	// 배송 방법 추출
	public function get_shipping_opt($seq, $type='shipping_group_seq'){
		$this->db->select("*");
		$this->db->where(array($type=>$seq));
		$this->db->order_by("shipping_opt_seq","asc");
		$query		= $this->db->get($this->shipoptTable);
		$result		= $query->result_array();

		return $result;
	}

	// 배송 방법 저장
	public function set_shipping_opt($data){
		// 수정 시
		if($data['shipping_opt_seq']){
			$opt_seq = $data['shipping_opt_seq'];
			unset($data['shipping_opt_seq']);
			$this->db->where('shipping_opt_seq', $opt_seq);
			$this->db->update('fm_shipping_option', $data);
		}else{ // 저장 시
			$result		= $this->db->insert($this->shipoptTable, $data);
			$opt_seq	= $this->db->insert_id();
		}

		return $opt_seq;
	}

	// 배송 방법 삭제
	public function del_shipping_opt($opt_seq){
		$this->db->delete($this->shipoptTable, array('shipping_opt_seq'=>$opt_seq));
	}

	// ------------- ### 배송금액 :: fm_shipping_cost :: START ### ------------- //

	// 배송금액 추출
	public function get_shipping_cost($seq, $type='shipping_group_seq_tmp'){
		$this->db->select("*");
		$this->db->where(array($type=>$seq));
		$this->db->order_by("shipping_cost_seq","asc");
		$query		= $this->db->get($this->shipcostTable);
		$result		= $query->result_array();

		return $result;
	}

	// 배송 금액 저장
	public function set_shipping_cost($data){
		// 수정 시
		if($data['shipping_cost_seq']){
			$cost_seq = $data['shipping_cost_seq'];
			unset($data['shipping_cost_seq']);
			$this->db->where('shipping_cost_seq', $cost_seq);
			$this->db->update('fm_shipping_cost', $data);
		}else{ // 저장 시
			$result		= $this->db->insert($this->shipcostTable, $data);
			$cost_seq	= $this->db->insert_id();
		}

		return $cost_seq;
	}

	// 배송 금액 삭제
	public function del_shipping_cost($cost_seq){
		$this->db->delete($this->shipcostTable, array('shipping_group_seq'=>$group_seq));
	}

	// ------------- ### 배송지역 :: fm_shipping_area_detail :: START ### ------------- //

	// 배송지역 추출
	public function get_shipping_zone($seq, $type='shipping_group_seq_tmp'){
		$this->db->select("*");
		$this->db->where(array($type=>$seq));
		$this->db->order_by("area_detail_seq","asc");
		$query	= $this->db->get($this->shipzoneTable);
		$result		= $query->result_array();

		return $result;
	}

	public function get_shipping_zone_list($seq, $limit = 0, $offset = 0, $keyword = null)
	{
		$this->db->select("area_detail_seq, area_detail_address_txt");
		$this->db->where(array('shipping_cost_seq'=>$seq));
		if($keyword){
			$this->db->like('area_detail_address_street', $keyword, 'after');
		}
		$this->db->order_by("area_detail_seq", "asc");
		$query	= $this->db->get($this->shipzoneTable, $offset, $limit);
		$result	= $query->result_array();

		return $result;
	}

	public function get_shipping_zone_count($seq, $type='shipping_group_seq_tmp', $keyword = null){
		$this->db->select("count(*) as shipping_zone_count");
		$this->db->where(array($type=>$seq));
		if($keyword){
			$this->db->like('area_detail_address_street', $keyword, 'after');
		}
		$this->db->order_by("area_detail_seq","asc");
		$query		= $this->db->get($this->shipzoneTable);
		$result		= $query->result_array();

		return $result;
	}

	// 배송 지역 저장
	public function set_shipping_zone($data){
		// 수정 시
		if($data['area_detail_seq']){
		}else{ // 저장 시
			$result		= $this->db->insert($this->shipzoneTable, $data);
			$zone_seq	= $this->db->insert_id();
		}

		return $zone_seq;
	}

	// 배송 지역 삭제
	public function del_shipping_zone($cost_seq){
		$this->db->delete($this->shipzoneTable, array('shipping_group_seq'=>$group_seq));
	}

	// ------------- ### 수령매장 :: fm_shipping_store :: START ### ------------- //

	// 수령매장 추출
	public function get_shipping_store($seq, $type='shipping_group_seq_tmp', $params=''){
		$whereArr = array($type => $seq);
		if($params['store_scm_type']){
			$whereArr['store_scm_type'] = $params['store_scm_type'];
		}
		if($params['shipping_address_seq']){
			$whereArr['shipping_address_seq'] = $params['shipping_address_seq'];
		}
		$this->db->select("*");
		$this->db->where($whereArr);
		$query		= $this->db->get($this->shipstoreTable);
		$result		= $query->result_array();

		return $result;
	}

	// 수령매장 배송지정보 조합 추출
	public function get_shipping_join_store($seq){
		$sql = "
			SELECT
				st.shipping_address_seq, st.shipping_store_name, st.store_scm_type,
				st.store_supply_set, st.store_supply_set_view,
				st.store_supply_set_order, st.store_scm_type,
				ad.address_category, ad.address_nation, ad.address_zipcode,
				ad.address_street, ad.address, ad.address_detail, ad.shipping_phone,
				ad.international_country, ad.international_town_city,
				ad.international_county, ad.international_address, ad.address_type
				, st.store_type, st.store_scm_seq
			FROM
				" . $this->shipstoreTable . " AS st LEFT JOIN
				" . $this->addressTable . " AS ad
				ON st.shipping_address_seq = ad.shipping_address_seq
			WHERE
				st.shipping_set_seq = '" . $seq . "'
		";

		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		return $result;
	}

	// 매장수령 저장
	public function set_shipping_store($data){
		// 수정 시
		if($data['shipping_store_seq']){
		}else{ // 저장 시
			$result		= $this->db->insert($this->shipstoreTable, $data);
			$store_seq	= $this->db->insert_id();
		}

		return $store_seq;
	}

	// 매장수령 삭제
	public function del_shipping_store($group_seq, $mode='shipping_group_seq'){
		$this->db->delete($this->shipstoreTable, array($mode=>$group_seq));
	}


	// ------------- ### 수령매장 :: fm_shipping_store :: END ### ------------- //


	// ------------- ### 주문 관련 데이터 추출 :: START ### ------------- //

	// 배송정보 전체 추출 - 주문번호
	public function get_shipping_log($order_seq){
	}

	// ------------- ### 주문 관련 데이터 추출 :: END ### ------------- //


	// ------------- ### EP배송비 데이터 추출 :: START ### ------------- //

	# EP 타입 받아서 분기 처리
	public function get_ep_data($ship_type = 'G', $ship_grp_seq = null, $feed_data = null, $data_goods = null){
		switch($ship_type){
			case 'G' : // 그룹 설정 가져오기
			if(true){
				$feed_info = $this->get_shipping_ep_data($ship_grp_seq, $data_goods);
				if(!$feed_info['std']) $feed_info['std'] = 0; // 기본값 전달
			}
			break;
			case 'S' : // 통합 설정 가져오기
			if(true){
				$feed_info = $this->get_shop_ep_data();
				if(!$feed_info['std']) $feed_info['std'] = 0; // 기본값 전달
			}
			break;
			case 'E' : // 개별 설정 파라미터로 가져와서 가공
			if(true){
				// 기본배송비 추출
				if			($feed_data['feed_pay_type'] == 'postpay'){
					$feed_info['std'] = -1;
				}else if	($feed_data['feed_pay_type'] == 'free'){
					$feed_info['std'] = '0';
				}else if	($feed_data['feed_pay_type'] == 'fixed'){
					$feed_info['std'] = $feed_data['feed_std_fixed'];
				}

				// 추가배송 문구 추출
				if			($feed_data['feed_add_txt']){
					$feed_info['add'] = $feed_data['feed_add_txt'];
				}
			}
			break;
		}



		$data['feed_ship_type_txt'] = $this->ep_ship_type[$data['feed_ship_type']];
		if	(!is_null($feed_info['std'])){
			if		($feed_info['std'] > 0){
				$feed_info['std_txt'] = get_currency_price($feed_info['std'],2);
			}else if($feed_info['std'] == -1){
				$feed_info['std_txt'] = '착불';
			}else{
				$feed_info['std_txt'] = '무료';
			}
		}
		if($feed_info['add']){
			$feed_info['add_txt'] = $feed_info['add'];
		}

		return $feed_info;
	}

	# EP 데이터 추출하기 :: 2017-02-22 lwh
	public function get_shipping_ep_data($ship_grp_seq, $data_goods = null){
		$ship_sum = $this->shippingmodel->get_shipping_group_summary($ship_grp_seq);

		// 착불시 착불로 리턴
		if($ship_sum['prepay_info'] == 'postpaid'){
			$return['std'] = -1;
			$return['fixed_cost'] = $ship_sum['fixed_cost'];
			return $return;
		}

		// 기본배송비 추출
		if			($ship_sum['std_opt_type'] == 'free'){
			$return['std'] = 0;
		}else if	($ship_sum['std_opt_type'] == 'fixed'){
			if			($ship_sum['fixed_cost']){ // 일반 고정
				$return['std'] = $ship_sum['fixed_cost'];
			}else if	($ship_sum['min_cost'] > 0){ // 최소값 추출
				$return['std'] = $ship_sum['min_cost'];
			}else if	($ship_sum['min_cost'] == 0){
				$return['std'] = 0;
			}else{
				$return['std'] = 'err';
			}
		}else{

			// 기준 배송비 타입
			$opt_type = ($ship_sum['std_opt_type'] == 'amount') ? 'price' : $ship_sum['std_opt_type'];
			// 상품 정보가 일치하는 경우 금액기준으로 계산 :: 2017-12-29 lwh
			if($opt_type && $data_goods[$opt_type]){
				$this->load->library('shipping');
				$return['std'] = $this->shipping->get_goods_for_shipprice($ship_grp_seq, $data_goods[$opt_type], $ship_sum['std_opt_type']);
			}else{
				if	($ship_sum['min_cost'] > 0){ // 최소값 추출
					$return['std'] = $ship_sum['min_cost'];
				}else if	($ship_sum['min_cost'] == 0){
					$return['std'] = 0;
				}
			}
		}

		// 추가배송 문구 추출
		if($ship_sum['add_opt_type']){
			$add_max_cost	= $ship_sum['add_max_cost'];
			$return['add']	= '지역별 배송비 최대 ' . get_currency_price($add_max_cost,2) . ' 추가';
			if	($ship_sum['add_opt_type'] != 'fixed'){
				$add_opt_type	= $this->shipping_otp_type_txt[$ship_sum['add_opt_type']];
				$add_opt_txt	= preg_replace('/\([\W\w]+\)/', '', $add_opt_type);
				$return['add'] = '구매' . $add_opt_txt . '/' . $return['add'];
			}
		}

		return $return;
	}

	# EP 데이터 추출하기 :: 2017-02-22 lwh
	public function get_shop_ep_data(){
		$feed_data = config_load('marketing_feed');

		// 기본배송비 추출
		if			($feed_data['feed_pay_type'] == 'postpay'){
			$return['std'] = -1;
			return $return;
		}else if	($feed_data['feed_pay_type'] == 'free'){
			$return['std'] = '0';
		}else if	($feed_data['feed_pay_type'] == 'fixed'){
			$return['std'] = $feed_data['feed_std_fixed'];
		}

		// 추가배송 문구 추출
		if			($feed_data['feed_add_txt']){
			$return['add'] = $feed_data['feed_add_txt'];
		}

		return $return;
	}

	// ------------- ### EP배송비 데이터 추출 :: END ### ------------- //


	// ------------- ### 기타 데이터 추출 :: START ### ------------- //

	// 안내문구 자동생성
	public function shipping_info_str($params){
		$punit	= $this->config_system['basic_currency']; // krw
		// Front에서 쓰는 통화표기로 재 변환
		//$punit	= get_currency_symbol($punit);
		//$punit	= $punit['symbol'];
		$punit	= get_currency_info($punit);
		$punit	= $punit['currency_symbol'];

		foreach($params as $k => $v)	$$k	= $v;

		if($nation == 'korea')	$nation_txt = '지역';
		else					$nation_txt = '국가';

		switch($kind){
			case 'store':
			// 매장수령
			if	(true){
				$result = '무료배송';
			}
			break;
			case 'hop':
			// 희망배송일
			if	(false){ // 일시 사용 안함.. FRONT 에서 직접 구함
				// 당일 여부 검색
				if(array_search('Y',$tcost_yn) === false){
					$n_cost	= $cost;
					$n_key	= 0;
					$t_day	= '';

				}else{
					$n_cost	= $tcost;
					$n_key	= array_search('Y',$tcost_yn);
					$t_day	= '당일 ';
				}

				// 무료
				if		($type == 'free'){
					$result	= '무료배송 <span class="desc">(배송가능지역별)</span>';
				// 구간 반복
				}elseif	(preg_match('/\_rep$/', $type)){
					$first_cost		= $n_cost[$n_key];
					$second_cost	= $n_cost[$n_key + (count($n_cost) / 2)];
					$pattern		= array('_rep', 'amount', 'cnt', 'weight');
					$replacestr		= array('', $punit, '개', 'kg');
					$unit			= str_replace($pattern, $replacestr, $type);
					if	(($kind == 'std' && $limitd == 'limit') || count($area) > 1){
						$utype = '/배송가능'.$nation_txt.'별';
					}
					$result	= $t_day . get_currency_price($first_cost,2)
							. ' <span class="desc">('
							. $st[1] . ' ' . $unit . ' 이상 ' . $ed[1] . ' ' . $unit . '당 '
							. get_currency_price($second_cost,2) . '씩 추가'
							. $utype . ')</span>';
				// 구간 입력
				}else{
					$pattern		= array('free', 'fixed', 'amount', 'cnt', 'weight');
					$replacestr		= array('', '', '금액', '수량', '무게');
					$name			= str_replace($pattern, $replacestr, $type);

					if(count($st) > 2){ // 슬라이딩패턴
						$min_cost		= min(array_filter($n_cost));
						$max_cost		= max($n_cost);
						$result	= $t_day;
						if($min_cost == $max_cost) $max_cost = 0;
						$result	= ($min_cost < 1) ? '무료' : get_currency_price($min_cost,2);
						if	($max_cost > 0){
							$result	.= '~' . get_currency_price($max_cost,2);
						}
						if	(($kind == 'std' && $limitd == 'limit') || count($area) > 1){
							$utype = ($name) ? $name . '/배송가능'.$nation_txt.'별' : '배송가능'.$nation_txt.'별';
							$result	.= ' <span class="desc">(' . $utype . ')</span>';
						}else if	($name){
							$result	.= ' <span class="desc">(' . $name . '별)</span>';
						}
					}else{ // 단순패턴
						$first_cost	= ($cost[0] < 1) ? '무료' : get_currency_price($cost[0],2);
						$s_cost		= $cost[(count($cost) / 2)];
						$second_cost= ($s_cost < 1) ? '무료' : get_currency_price($s_cost,2);
						$pattern	= array('_rep', 'amount', 'cnt', 'weight');
						$replacestr	= array('', $punit, '개', 'kg');
						$unit		= str_replace($pattern, $replacestr, $type);
						$result		= $first_cost;
						if	($st[1])	$over_txt = $st[1] . $unit . ' 이상 ' . $second_cost;
						if	(($kind == 'std' && $limitd == 'limit') || count($area) > 1){
							$utype	= ($over_txt) ? '/배송가능'.$nation_txt.'별' : '배송가능'.$nation_txt.'별';
						}
						if	($over_txt || $utype){
							$result	.= ' <span class="desc">(' . $over_txt . $utype . ')</span>';
						}
					}
				}

				// 당일시간
				if($limit == 'time'){
					if($times == '1330'){
						$result .= '<br/>13시 30분 이전 주문 시 당일배송';
					}else{
						$result .= '<br/>12시 이전 주문 시 당일배송';
					}
				}
			}
			break;
			case 'add':
			// 추가배송비
			if	(true){
				$pattern		= array('free', 'fixed', 'amount', 'cnt', 'weight');
				$replacestr		= array('', '', '구매금액', '구매수량', '무게');
				$non_type		= preg_replace('/\_rep$/', '', $type);
				$name			= str_replace($pattern, $replacestr, $non_type);
				$min_cost		= min($cost);
				$max_cost		= max($cost);
				if($min_cost == $max_cost) $max_cost = 0;
				$result	= ($min_cost < 1) ? '무료' : get_currency_price($min_cost,2);
				if	($max_cost > 0)	$result	.= '~' . get_currency_price($max_cost,2);
				$utype = ($name) ? $name . '/'.$nation_txt.'별' : $nation_txt.'별';
				$result	.= ' <span class="desc">(' . $utype . ')</span>';
			}
			break;
			case 'std':
			// 기본배송비
			if	(true){
				// 무료
				if		($type == 'free'){
					$result	= '무료배송';
					if	($limitd == 'limit')	$result	.= ' (배송가능지역별)';
				}
				// 구간 반복
				elseif	(preg_match('/\_rep$/', $type)){
					$real_cost = $cost[0];
					if($ed[0]==1 && $type == 'cnt_rep')		$real_cost = $cost[1];
					$first_cost		= $real_cost;
					$second_cost	= $cost[(count($cost) / 2)];
					$pattern		= array('_rep', 'amount', 'cnt', 'weight');
					$replacestr		= array('', $punit, '개', 'kg');
					$unit			= str_replace($pattern, $replacestr, $type);
					if	($limitd == 'limit')	$utype = ', 배송가능'.$nation_txt.'별';
					$result	= get_currency_price($first_cost,2)
							. ' <span class="desc">('
							. number_format($st[1]) . ' ' . $unit . ' 이상 ' . number_format($ed[1]) . ' ' . $unit . '당 '
							. get_currency_price($second_cost,2) . '씩 추가'
							. $utype . ')</span>';
				// 구간 입력
				}else{
					$pattern		= array('free', 'fixed', 'amount', 'cnt', 'weight');
					$replacestr		= array('', '', '구매금액', '구매수량', '무게');
					$name			= str_replace($pattern, $replacestr, $type);

					if(count($st) == 2){ // 단순패턴
						$over_txt	= '';
						$first_cost	= ($cost[0] < 1) ? '무료' : get_currency_price($cost[0],2);
						$s_cost		= $cost[(count($cost) / 2)];
						$second_cost= ($s_cost < 1) ? '무료' : get_currency_price($s_cost,2);
						$pattern	= array('_rep', 'amount', 'cnt', 'weight');
						$replacestr	= array('', $punit, '개', 'kg');
						$unit		= str_replace($pattern, $replacestr, $type);
						$result		= $first_cost;
						// 금액 구간입력인 경우 unit는 관리자설정에 맞게 노출되도록 수정 2018-06-11
						if	($st[1]) {
							if($type == 'amount') {
								$over_txt = get_currency_price($st[1],2);
							} else {
								$over_txt = number_format($st[1]) . ' ' . $unit;
							}
							$over_txt .= ' 이상 ' . $second_cost;
						}
						if( $limitd == 'limit'){
							$utype	= ($over_txt) ? ', 배송가능'.$nation_txt.'별' : '배송가능'.$nation_txt.'별';
						}
						if	($over_txt || $utype){
							$result	.= ' <span class="desc">(' . $over_txt . $utype . ')</span>';
						}
					}else{ // 슬라이딩패턴
						$min_cost		= min($cost);
						$max_cost		= max($cost);
						if($min_cost == $max_cost) $max_cost = 0;
						$result	= ($min_cost < 1) ? '무료' : get_currency_price($min_cost,2);
						if	($max_cost > 0)	$result	.= '~' . get_currency_price($max_cost,2);
						if( $limitd == 'limit'){
							$utype = ($name) ? $name . '/배송가능'.$nation_txt.'별' : '배송가능'.$nation_txt.'별';
							$result	.= ' <span class="desc">(' . $utype . ')</span>';
						}else if	($name){
							$result	.= ' <span class="desc">(' . $name . '별)</span>';
						}
					}
				}
			}
			break;
		}

		// 언어별 치환 문구 정의
		$us_arr = array(
			'무료배송'			=> 'Free Shipping',
			'/배송가능지역별'	=> '/available shipping regional',
			'배송가능지역별'		=> 'By shipping available regional',
			'/배송가능국가별'	=> '/available shipping country',
			'배송가능국가별'		=> 'By shipping available country',
			'/지역별'			=> '/regional',
			'지역별'			=> 'By regional',
			'/국가별'			=> '/country',
			'국가별'			=> 'By country',
			'무료'			=> 'Free Shipping',
			'이상'			=> 'and more',
			'구매금액별'		=> 'By purchase price',
			'구매금액'			=> 'by purchase price',
			'구매수량별'		=> 'by quantity',
			'구매수량'			=> 'by quantity',
			'무게별'		=> 'by weight',
			'무게'			=> 'by weight',
			'당'				=> 'per',
			'추가'			=> 'plus',
			'씩'				=> 'each'
		);

		$cn_arr = array(
			'무료배송'			=> '免费配送',
			'/배송가능지역별'	=> '/可按地区分类的地区l',
			'배송가능지역별'		=> '可按地区分类的地区l',
			'/배송가능국가별'	=> '/按配送国家分类的各国',
			'배송가능국가별'		=> '按配送国家分类的各国',
			'/지역별'			=> '/按地域划分的地区',
			'지역별'			=> '按地域划分的地区',
			'/국가별'			=> '/国别',
			'국가별'			=> '国别',
			'무료'			=> '免费',
			'이상'			=> '更多',
			'구매금액별'		=> '按购买金额计算',
			'구매금액'			=> '购买金额',
			'구매수량별'		=> '按购买数量',
			'구매수량'			=> '购买数量',
			'무게별'		=> '按购买力分类',
			'무게'			=> '购买重量',
			'당'				=> '每',
			'추가'			=> '添加',
			'씩'				=> '每'
		);

		$jp_arr = array(
			'무료배송'			=> 'Free Shipping',
			'/배송가능지역별'	=> '/available shipping regional',
			'배송가능지역별'		=> 'By shipping available regional',
			'/배송가능국가별'	=> '/available shipping country',
			'배송가능국가별'		=> 'By shipping available country',
			'/지역별'			=> '/regional',
			'지역별'			=> 'By regional',
			'/국가별'			=> '/country',
			'국가별'			=> 'By country',
			'무료'			=> 'Free Shipping',
			'이상'			=> 'and more',
			'구매금액별'		=> 'By purchase price',
			'구매금액'			=> 'by purchase price',
			'구매수량별'		=> 'by quantity',
			'구매수량'			=> 'by quantity',
			'무게별'		=> 'by weight',
			'무게'			=> 'by weight',
			'당'				=> 'per',
			'추가'			=> 'plus',
			'씩'				=> 'each'
		);

		// 언어별 치환
		$language_res['kr']	= $result;
		$language_res['us']	= str_replace(array_keys($us_arr),array_values($us_arr),$result);
		$language_res['cn']	= str_replace(array_keys($cn_arr),array_values($cn_arr),$result);
		$language_res['jp']	= str_replace(array_keys($jp_arr),array_values($jp_arr),$result);

		return $language_res;
	}

	// 희망배송일 가장 빠른날짜 추출
	public function get_hop_date($set_info){
		$hop_date = '';

		// 희망배송일 사용여부 검사
		if	($set_info['hop_use'] != 'Y')	return false;

		// 당일여부 가공
		$set_info['today_use'] = ($set_info['hopeday_limit_set'] == 'time') ? 'Y' : 'N';
		// 요일선택 여부 가공
		$hopeday_limit_week = array(0,0,0,0,0,0,0);

		if	(is_array($set_info['hopeday_limit_week'])){
			$hopeday_limit_week = $set_info['hopeday_limit_week'];
		}else{
			if	($set_info['hopeday_limit_week']) for($i=0; $i<7; $i++){
				$hopeday_limit_week[$i] = substr($set_info['hopeday_limit_week'],$i,1);
			}
		}

		// 달력 기본값 설정
		$nowdate	= date('Y-m-d');
		$nowtime	= date('Hi');

		// 최대 100일 후까지만 검사
		$i = 0;
		while($i <= 100){

			$selYMD	= date('Y-m-d', strtotime($nowdate . '+'.$i.' day'));
			$selMD	= date('m-d', strtotime($selYMD));
			$week	= date('w', strtotime($selYMD));

			$i++;

			// 배송일자 설정 체크
			if($set_info['today_use'] == 'N'){
				$day_sel_date = date('Y-m-d', strtotime($nowdate . '+'.$set_info['hopeday_limit_val'].' day'));
				if($day_sel_date > $selYMD){
					continue;
				}
			}

			// 요일 체크
			if($hopeday_limit_week[$week]=='1'){
				continue;
			}

			// 반복 불가 일자 체크
			if(strpos($set_info['hopeday_limit_repeat_day'], $selMD)===false){
				$hop_date = $selYMD;
			}else{
				$hop_date = '';
				continue;
			}

			// 지정 불가 일자 체크
			if(strpos($set_info['hopeday_limit_day'], $selYMD)===false){
				$hop_date = $selYMD;
			}else{
				$hop_date = '';
				continue;
			}

			// 당일 여부 체크
			if($nowdate == $selYMD && $set_info['today_use'] == 'Y'){
				if($set_info['hopeday_limit_val'] > $nowtime)
						$hop_date = $selYMD;
				else	$hop_date = '';
			}

			if($hop_date)	break;
		}

		return $hop_date;
	}

	// 희망배송일 가능일자 체크 함수
	public function chk_hop_date($set_seq, $chkdate=''){
		if	(!$chkdate){ // 체크 날짜가 없는경우 오늘을 기준으로 체크
			$chkdate = date('Y-m-d');
		}

		// 배송 설정 정보 추출
		$set_info = $this->get_shipping_set($set_seq, 'shipping_set_seq');

		// 희망배송일 사용여부 검사
		if	($set_info['hop_use'] != 'Y')	return false;

		// 당일여부 가공
		$set_info['today_use'] = ($set_info['hopeday_limit_set'] == 'time') ? 'Y' : 'N';
		// 요일선택 여부 가공
		$hopeday_limit_week = array(0,0,0,0,0,0,0);
		if	($set_info['hopeday_limit_week']) for($i=0; $i<7; $i++){
			$hopeday_limit_week[$i] = substr($set_info['hopeday_limit_week'],$i,1);
		}

		// 달력 기본값 설정
		$nowdate	= date('Y-m-d');
		$nowtime	= date('Hi');
		$selYMD		= date('Y-m-d',strtotime($chkdate));
		$selMD		= date('m-d', strtotime($selYMD));
		$week		= date('w', strtotime($selYMD));

		// 배송일자 설정 체크
		if($set_info['today_use'] == 'N'){
			$day_sel_date = date('Y-m-d', strtotime($nowdate . '+'.$set_info['hopeday_limit_val'].' day'));
			if($day_sel_date > $chkdate){
				return false;
			}
		}

		// 요일 체크
		if($hopeday_limit_week[$week]=='1'){
			return false;
		}

		// 반복 불가 일자 체크
		if(strpos($set_info['hopeday_limit_repeat_day'], $selMD)===false){
			$hop_date = $selYMD;
		}else{
			return false;
		}

		// 지정 불가 일자 체크
		if(strpos($set_info['hopeday_limit_day'], $selYMD)===false){
			$hop_date = $selYMD;
		}else{
			return false;
		}

		// 당일 여부 체크
		if($nowdate == $selYmd && $set_info['today_use'] == 'Y'){
			if($set_info['hopeday_limit_val'] > $nowtime)
					$hop_date = $selYMD;
			else	return false;
		}

		return true;
	}

	// 장소 리스트 추출
	public function shipping_address_list($sc){
		$page = ($sc['page']) ? $sc['page'] : 1;

		$selectSql = "";
		$joinSql = "";

		// 정렬 순서
		$order_by = " ORDER BY s_addr.regist_date DESC ";
		if($sc['orderby']){
			$order_by = $sc['orderby'];
			unset($sc['orderby']);
		}

		// 반송지 & 매장수령 정보 추가
		$selectSql = " , s_group.refund_address_seq, if(s_store.shipping_store_seq > 0,1,0) as shipping_store_seq ";
		$joinSql = "
			LEFT JOIN fm_shipping_grouping AS s_group
				ON s_addr.shipping_address_seq = s_group.refund_address_seq
					AND s_group.refund_scm_type = 'N'
					AND s_group.default_yn = 'Y'

			LEFT JOIN fm_shipping_store AS s_store
				ON s_addr.shipping_address_seq = s_store.shipping_address_seq
					AND s_store.store_type = 'input'
			LEFT JOIN fm_shipping_set AS s_set
				ON s_store.shipping_set_seq = s_set.shipping_group_seq
					AND s_set.shipping_set_code = 'direct_store'
			LEFT JOIN fm_shipping_grouping AS s_group_for_store
				ON s_store.shipping_group_seq_tmp = s_group_for_store.shipping_group_seq
					AND s_set.shipping_group_seq = s_group_for_store.shipping_group_seq
		";

		// 반송지 & 매장수령 검색 조건 추가
		if($sc['address_icon']){
			if(in_array('default_store', $sc['address_icon'])){
				$wheresOr[] = " s_addr.default_yn = 'Y' ";
			}
			if(in_array('refund_address', $sc['address_icon'])){
				$wheresOr[] = " s_group.refund_address_seq is not null ";
			}
			if(in_array('direct_store', $sc['address_icon'])){
				$wheresOr[] = " s_store.shipping_store_seq is not null ";
			}
			if(in_array('shipping_address', $sc['address_icon'])){
				$wheresOr[] = " s_addr.store_info_display_yn = 'Y' ";
			}
			if(in_array('o2o_store', $sc['address_icon'])){
				$wheresOr[] = " s_addr.store_seq > 0 ";
			}
			unset($sc['address_icon']);
		}

		if($wheresOr)	$wheres[] = " (".implode(" OR ", $wheresOr).") ";

		if($sc){
			unset($sc['page']);
			foreach($sc as $column => $val){
				if($val){
					if(is_array($val)){
						$wheres[] = "s_addr.".$column . " in ('". implode("', '", $this->db->escape_str($val)) ."')";
					}else{
						if($column == 'address_name'){
							$wheres[] = "s_addr.".$column . " like '". trim($this->db->escape_like_str($val)) ."%'";
						}else{
							$wheres[] = "s_addr.".$column . " = '". trim($this->db->escape_str($val)) ."'";
						}
					}
					$_GET[$column] = $val;
				}
			}
			if($wheres)		$where = " WHERE ".implode(" AND ", $wheres);
		}


		$query	= "SELECT distinct s_addr.*, 'input' AS add_type ".$selectSql." FROM " . $this->addressTable. " AS s_addr " . $joinSql . $where . " " . $order_by;

		$result	= select_script_page(10,$page,10,$query,'','searchPaging');

		// 요일정보 & 주소 구성
		foreach($result['record'] as &$record){
			$store_term = "";
			if($record['store_term']){
				$store_term = json_decode($record['store_term'], true);
				foreach($store_term as &$row){
					$store_term_hour_text = $row['store_term_hour1'].':'.$row['store_term_min1'].' ~ '.$row['store_term_hour2'].':'.$row['store_term_min2'];
					$store_term_time_text = (($row['store_term_time'] == 'closed') ? $this->store_term_time[$row['store_term_time']] : $store_term_hour_text);
					$row['text'] = $this->store_term_week[$row['store_term_week']].' '.$store_term_time_text.' ';
				}
				$record['store_term_list'] = $store_term;
			}
			$record['full_address'] = '';
			if($record['address_nation'] == 'korea'){
				$record['full_address'] = $record['address_street'].' '.$record['address_detail'];
			}else{
				$record['full_address'] = $record['international_country'].' '.$record['international_town_city'].' '.$record['international_county'].' '.$record['international_address'];
			}
		}
		return $result;
	}

	// 창고 리스트 추출
	public function shipping_warehouse_list($sc,$select=null){
		$page = ($sc['page']) ? $sc['page'] : 1;

		if($sc){
			// 창고 그룹 검색 == 분류
			if($sc['address_category'])
				$wheres[] = "WH.wh_group = '". $sc['address_category'] ."'";

			// 창고명 == 명칭
			if($sc['address_name'])
				$wheres[] = "WH.wh_name = '". $sc['address_name'] ."'";

			// 창고번호 검색
			if($sc['wh_seq'])
				$wheres[] = "WH.wh_seq = '". $sc['wh_seq'] ."'";

			// 매칭없음 == 국내/해외 korea 고정
			if($sc['address_nation'] && 1==2)
				$wheres[] = "address_nation = '". $sc['address_nation'] ."'";

			if($wheres)		$where = " WHERE ".implode(" AND ", $wheres);
		}

		$query	= "
			SELECT
				WH.wh_seq AS shipping_address_seq, WH.wh_group AS address_category,
				WH.wh_name AS address_name,	WH.wh_address_type AS address_type,
				WH.wh_zipcode AS address_zipcode, WH.wh_address AS address,
				WH.wh_address_street AS address_street,
				WH.wh_address_detail AS address_detail,
				WH.wh_regist_date AS regist_date,
				WH.wh_modify_date AS update_date,
				SM.phone_number AS shipping_phone,
				'korea' AS address_nation,
				'scm' AS add_type
				, WH.wh_seq AS store_scm_seq
			FROM
				fm_scm_warehouse as WH LEFT JOIN fm_scm_manager as SM
				ON SM.parent_table = 'warehouse' AND WH.wh_seq = SM.parent_seq
				" . $where . "
				ORDER BY WH.wh_regist_date DESC
			";

		if($select == 'limit'){
			$query	= $this->db->query($query);
			$result	= $query->row_array();
		}else{
			$result	= select_script_page(10,$page,10,$query,'','searchPaging');
		}

		return $result;
	}

	// 장소 분류 추출
	public function get_shipping_category($type='input',$provider_seq = 1){
		if($type == 'input'){
			$sql = "
				SELECT address_category
				FROM " . $this->addressTable . "
				WHERE address_category != '' AND address_provider_seq = '" . $provider_seq . "'
				GROUP BY address_category
			";
		}else{
			$sql = "
				SELECT wh_group AS address_category
				FROM fm_scm_warehouse
				WHERE wh_group != ''
				GROUP BY wh_group
			";
		}
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		// o2o 검색 분류 추가
		$this->load->library('o2o/o2oinitlibrary');
		$this->o2oinitlibrary->init_admin_get_shipping_category($result, $type);

		return $result;
	}

	// 장소 추출
	public function get_shipping_address($seq, $scm_type='N'){

		// 올인원인 경우 창고매장 / 아니면 오프라인 매장에서 검색하도록 개선 2020-04-21
		if($scm_type == 'Y'){
			$sc['wh_seq'] = $seq;
			$result = $this->shipping_warehouse_list($sc,'limit');
		} else {
			$sql = "
				SELECT *
				FROM " . $this->addressTable . "
				WHERE shipping_address_seq = '" . $seq . "'
			";
			$query	= $this->db->query($sql);
			$result = $query->row_array();
		}
		if($result){
			$result['zoneZipcode'] = (($result['address_nation'] == 'korea') ? $result['address_zipcode'] : $result['international_postcode']);
		}

		// 요일정보 구성
		$store_term = "";
		if($result['store_term']){
			$store_term = json_decode($result['store_term'], true);
			foreach($store_term as &$row){
				$store_term_hour_text = $row['store_term_hour1'].':'.$row['store_term_min1'].' ~ '.$row['store_term_hour2'].':'.$row['store_term_min2'];
				$store_term_time_text = (($row['store_term_time'] == 'closed') ? $this->store_term_time[$row['store_term_time']] : $store_term_hour_text);
				$row['text'] = $this->store_term_week[$row['store_term_week']].' '.$store_term_time_text.' ';
			}
			$result['store_term_list'] = $store_term;

			$result['full_address'] = '';
			if($result['address_nation'] == 'korea'){
				$result['full_address'] = $result['address_street'].' '.$result['address_detail'];
			}else{
				$result['full_address'] = $record['international_country'].' '.$result['international_town_city'].' '.$result['international_county'].' '.$result['international_address'];
			}
		}
		return $result;
	}

	// 기본 그룹 장소 추출 :: 2017-05-08 lwh
	public function get_default_address($provider_seq=1){

		// 해당 입점사 기본배송그룹 정보 추출
		$base_sql = "SELECT shipping_group_seq, refund_address_seq, refund_scm_type FROM fm_shipping_grouping WHERE shipping_provider_seq = ? AND default_yn = 'Y' LIMIT 1";
		$query	= $this->db->query($base_sql,$provider_seq);
		$res	= $query->row_array();

		if($res['shipping_group_seq']){
			$seq		= $res['refund_address_seq'];
			$scm_type	= $res['refund_scm_type'];
			if(!$seq)	return false;
		}else{
			$this->set_base_shipping_group($provider_seq);
		}

		$result = $this->get_shipping_address($seq, $scm_type);

		return $result;
	}

	// 장소 리스트 삭제
	public function del_shipping_address($data){
		$this->db->where_in('shipping_address_seq', $data['shipping_address_seq']);
		$result = $this->db->delete($this->addressTable);

		// $this->db->delete($this->shipstoreTable, array('shipping_address_seq'=>$data['shipping_address_seq'],'store_type'=>'input'));

		$cnt = 0;
		if($result){
			$cnt = count($data['shipping_address_seq']);
		}
		return $cnt;
	}

	// 장소 리스트 등록
	public function set_shipping_address($data){
		$nowdate						= date('Y-m-d H:i:s');

		$param['address_provider_seq']	= $data['address_provider_seq'];
		$param['address_category']		= ($data['address_category'] != 'direct_input') ? $data['address_category'] : $data['address_category_direct'];
		$param['address_nation']		= $data['address_nation'];
		$param['address_name']			= $data['address_name'];
		$param['address_type']			= $data['zoneAddress_type'];
		$param['address_zipcode']		= (is_array($data['zoneZipcode'])) ? implode('',$data['zoneZipcode']) : $data['zoneZipcode'];
		$param['address']				= $data['zoneAddress'];
		$param['address_street']		= $data['zoneAddress_street'];
		$param['address_detail']		= $data['zoneAddressDetail'];
		if($data['address_nation'] == 'global'){
			$param['international_postcode']	= $param['address_zipcode'];
			$param['international_country']		= $data['international_country'];
			$param['international_town_city']	= $data['international_town_city'];
			$param['international_county']		= $data['international_county'];
			$param['international_address']		= $data['international_address'];
		}
		$param['shipping_phone']		= $data['shipping_phone'];
		$param['update_date']			= $nowdate;

		// 매장 안내 속성 및 포스 연동 속성 추가
		$param['store_info_display_yn']		= ($data['store_info_display_yn'])?$data['store_info_display_yn']:'N';
		$param['store_term']				= $data['store_term'];
		$param['store_description']			= $data['store_description'];
		$param['store_o2o_use_yn']			= ($data['store_o2o_use_yn'])?$data['store_o2o_use_yn']:'N';
		$param['store_seq']					= $data['store_seq'];

		if($data['shipping_address_seq']){
			$this->db->where(array("shipping_address_seq"=>$data['shipping_address_seq']));
			$this->db->update($this->addressTable, $param);
			$result = $data['shipping_address_seq'];
		}else{
			$param['regist_date']		= $nowdate;
			$this->db->insert($this->addressTable, $param);
			$result = $this->db->insert_id();
		}

		return $result;
	}

	// 대표매장 장소 리스트 등록
	public function set_shipping_address_default_yn($data){
		$set_default_yn = "IF(shipping_address_seq='".$data['shipping_address_seq']."','Y','N')";
		// 모든 대표매장 해제
		if($data['default_yn'] == 'N'){
			$set_default_yn = "'N'";
		}
		$this->db->set('default_yn', $set_default_yn, false);
		$this->db->where(array("address_provider_seq"=>$data['address_provider_seq']));
		$this->db->update($this->addressTable);
		$result = $data['shipping_address_seq'];
		return $result;
	}

	// 배송그룹 연결 상품 재조정하기
	public function group_cnt_adjust($grp_seq_arr = array()){

		if(count($grp_seq_arr) > 0){
			$whereis = " AND shipping_group_seq IN ('" . implode("', '" , $grp_seq_arr) . "')";
		}

		// 전체 갯수 추출
		$sql = "
			SELECT shipping_group_seq, package_yn, trust_shipping, COUNT(goods_seq) AS cnt
			FROM
				fm_goods
			WHERE
				goods_kind = 'goods' AND goods_type = 'goods'
			" . $whereis . "
			GROUP BY shipping_group_seq, package_yn, trust_shipping
			ORDER BY shipping_group_seq, package_yn, trust_shipping
		";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		// 배송그룹 전체 카운트
		foreach($result as $k => $ship){
			$grp_seq = $ship['shipping_group_seq'];
			$ship_cnt[$grp_seq] += $ship['cnt'];
			$goods_cnt[$grp_seq][$ship['package_yn']] += $ship['cnt'];
			if($ship['trust_shipping'] == 'Y') $trust_cnt[$grp_seq] += $ship['cnt'];
		}

		// 배송그룹 업데이트
		$zero_grp	= array();
		foreach($ship_cnt as $grp_seq => $cnt){
			unset($sets);

			if	($goods_cnt[$grp_seq]['y'])
					$sets[] = "target_package_cnt = '" . $goods_cnt[$grp_seq]['y'] . "'";
			else	$sets[] = "target_package_cnt = '0'";

			if	($goods_cnt[$grp_seq]['n'])
					$sets[] = "target_goods_cnt = '" . $goods_cnt[$grp_seq]['n'] . "'";
			else	$sets[] = "target_goods_cnt = '0'";

			if	($trust_cnt[$grp_seq])
					$sets[] = "trust_goods_cnt = '" . $trust_cnt[$grp_seq] . "'";
			else	$sets[] = "trust_goods_cnt = '0'";

			if(count($sets) > 0){
				$up_sql = "
				UPDATE
					" . $this->shipgroupTable . "
				SET
					" . implode(", ", $sets) . "
				WHERE
					shipping_group_seq = '" . $grp_seq . "'";
				$this->db->query($up_sql);

				$zero_grp[] = $grp_seq;
			}
		}

		if(count($zero_grp) > 0 && empty($grp_seq_arr)){
			$zero_sql = "UPDATE	" . $this->shipgroupTable . " SET target_package_cnt = '0', target_goods_cnt = '0', trust_goods_cnt = '0' WHERE shipping_group_seq NOT IN ('" . implode("', '", $zero_grp) . "')";
			$this->db->query($zero_sql);
		}
	}

	// 상품에 배송그룹 연결하기
	public function set_shipping_group_rel_goods($grp_seq, $goods_seq){
		$this->db->set('trust_shipping', 'N');
		$this->db->set('shipping_group_seq', $grp_seq);
		$this->db->where('goods_seq',$goods_seq);
		$this->db->update('fm_goods');
	}

	// 배송그룹 해외 국가 추출 - grp_seq 가 없으면 전체 국가를 구함
	public function get_gl_shipping($grp_seq=''){
		$this->db->reset_query();
		if($grp_seq){
			$this->db->select("area_detail_address_txt as nation_str, area_detail_address_txt");
			$this->db->where(array('shipping_group_seq_tmp'=>$grp_seq,'area_nation_type'=>'global'));
			$this->db->group_by("area_detail_address_txt");
			$this->db->order_by("area_detail_address_txt asc");
			$query		= $this->db->get($this->shipzoneTable);
		}else{
			$this->db->select("nation_name as nation_str,nation_key");
			$this->db->where(array('nation_exception'=>'Y'));
			$this->db->order_by("nation_name asc");
			$query		= $this->db->get($this->nationTable);
		}
		$result		= $query->result_array();

		return $result;
	}

	// 해외국가 영문명으로 Full 국가명 추출
	public function get_gl_nation($nation){
		if	($nation){
			$sql = "
				SELECT nation_name ,nation_key
				FROM " . $this->nationTable . "
				WHERE nation_name LIKE '%(" . $this->db->escape_like_str($nation) . ")%'
			";
			$query	= $this->db->query($sql);
			$result	= $query->row_array();

			return $result['nation_name'];
		}
	}

	public function get_gl_nation_key($nation){
		if	($nation){
			$sql = "
				SELECT nation_key
				FROM " . $this->nationTable . "
				WHERE nation_name LIKE '%(" . $nation . ")%'
			";
			$query	= $this->db->query($sql);
			$result	= $query->row_array();

			return $result['nation_key'];
		}
	}

	public function get_gl_nation_key_2_code($nation){
		if	($nation){
			$sql = "
				SELECT nation_key_2_code
				FROM " . $this->nationTable . "
				WHERE nation_name LIKE '%" . $nation . "%'
			";
			$query	= $this->db->query($sql);
			$result	= $query->row_array();

			return $result['nation_key_2_code'];
		}
	}

	// 해외국가 한글명 영문명 분리
	public function split_nation_str($nation_str_arr){
		foreach($nation_str_arr as $k => $val){
			preg_match("/\([^가-힣]*\)/",$val['nation_str'],$matches);
			$kr_str = trim(str_replace($matches,"",$val['nation_str']));
			$gl_str = trim(preg_replace("/[\(,\)]/","",$matches[0]));
			$ship_gl_list[$k]['kr_nation'] = $kr_str;
			$ship_gl_list[$k]['gl_nation'] = $gl_str;
		}

		return $ship_gl_list;
	}

	// 수정 시 파라미터 제작용 함수
	public function ship_set_modify_params($grp_seq, $limit=0){
		// 배송 설정 정보 추출
		$set_info = $this->load_shipping_set($grp_seq, $limit);

		// 데이터 추출
		if($set_info['shipping_set_seq']){
			$params = $this->load_shipping_set_detail($set_info['shipping_set_seq']);
		}else{ // 더이상 데이터 없을때 종료
			return false;
		}

		return $params;
	}

	// 배송정보 로그 등록
	public function set_shipping_log($order_seq, $shipping_cfg){

		$baseRule = $shipping_cfg['cfg']['baserule'];
		$grp_info = $this->get_shipping_group($baseRule['shipping_group_seq']);
		$set_info = $this->get_shipping_set($baseRule['shipping_set_seq'], 'shipping_set_seq');

		$ship_log_data['order_seq'] = $order_seq;
		$ship_log_data['shipping_group_seq'] = $baseRule['shipping_group_seq'];
		$ship_log_data['shipping_group_type'] = 'Y'; // 그룹/개별기능 추후 기능추가
		$ship_log_data['shipping_calcul_type'] = $baseRule['shipping_calcul_type'];
		$ship_log_data['shipping_calcul_free_yn'] = $baseRule['shipping_calcul_free_yn'];
		$ship_log_data['shipping_std_free_yn'] = $baseRule['shipping_std_free_yn'];
		$ship_log_data['shipping_add_free_yn'] = $baseRule['shipping_add_free_yn'];
		$ship_log_data['shipping_hop_free_yn'] = $baseRule['shipping_hop_free_yn'];
		$ship_log_data['international_use'] = ($baseRule['delivery_nation'] == 'korea') ? 'N' : 'Y';
		$ship_log_data['store_pickup_use'] = ($baseRule['shipping_set_code'] == 'direct_store') ? 'Y' : 'N';
		$ship_log_data['free_shipping_use'] = ($shipping_cfg['grp_shipping_price'] > 0) ? 'N' : 'Y';
		$ship_log_data['delivery_type'] = 'basic'; // 구매타입 추후 기능추가
		$ship_log_data['delivery_limit'] = $baseRule['delivery_limit'];
		$ship_log_data['addcost_use'] = $set_info['add_use'];
		$ship_log_data['hopeday_use'] = $set_info['hop_use'];
		$ship_log_data['reserve_use'] = $set_info['reserve_use'];
		$ship_log_data['store_use'] = $set_info['store_use'];
		if($set_info['store_use'] == 'Y' && $shipping_cfg['store_info']){
		$ship_log_data['store_supply_set'] = $shipping_cfg['store_info']['store_supply_set'];
		$ship_log_data['store_supply_set_view'] = $shipping_cfg['store_info']['store_supply_set_view'];
		$ship_log_data['store_supply_set_order'] = $shipping_cfg['store_info']['store_supply_set_order'];
		}
		$ship_log_data['hopeday_required'] = $set_info['hopeday_required'];
		$ship_log_data['goods_sale_sdate'] = $set_info['goods_sale_sdate'];
		$ship_log_data['goods_sale_edate'] = $set_info['goods_sale_edate'];
		$ship_log_data['reserve_sdate'] = $set_info['reserve_sdate'];
		$ship_log_data['prepay_info'] = $set_info['prepay_info'];
		$ship_log_data['provider_shipping_use'] = $grp_info['provider_shipping_use'];
		$ship_log_data['shipping_provider_seq'] = $grp_info['shipping_provider_seq'];
		$ship_log_data['sendding_scm_type']		= $grp_info['sendding_scm_type'];
		$ship_log_data['sendding_address_seq']	= $grp_info['sendding_address_seq'];
		$ship_log_data['refund_scm_type']		= $grp_info['refund_scm_type'];
		$ship_log_data['refund_address_seq']	= $grp_info['refund_address_seq'];
		$ship_log_data['shipping_set_seq'] = $baseRule['shipping_set_seq'];
		$ship_log_data['shipping_set_code'] = $baseRule['shipping_set_code'];
		$ship_log_data['shipping_set_name'] = $baseRule['shipping_set_name'];
		$ship_log_data['delivery_nation'] = $baseRule['delivery_nation'];

		$result		= $this->db->insert($this->shiplogTable, $ship_log_data);
		$log_seq	= $this->db->insert_id();


		if($baseRule['shipping_set_code'] != 'direct_store'){
			foreach($this->shipping_type_arr as $type => $kr_name){
				unset($opt_log);
				unset($ship_opt_log_data);

				// 배송구간 로그 등록
				$opt_log = $shipping_cfg['cfg'][$type];
				foreach($opt_log as $key => $opt_info){
					$ship_opt_log_data['log_seq'] = $log_seq;
					$ship_opt_log_data['shipping_opt_seq'] = $opt_info['shipping_opt_seq'];
					$ship_opt_log_data['shipping_set_type'] = $opt_info['shipping_set_type'];
					$ship_opt_log_data['shipping_opt_type'] = $opt_info['shipping_opt_type'];
					$ship_opt_log_data['delivery_limit'] = $opt_info['delivery_limit'];
					$ship_opt_log_data['section_st'] = $opt_info['section_st'];
					$ship_opt_log_data['section_ed'] = $opt_info['section_ed'];
					$this->db->insert($this->shipoptlogTable, $ship_opt_log_data);

					// 배송금액 로그 등록
					$cost_info_arr = $this->get_shipping_cost($opt_info['shipping_opt_seq'], 'shipping_opt_seq');
					foreach($cost_info_arr as $key => $cost_info){
						$ship_cost_log_data['log_seq'] = $log_seq;
						$ship_cost_log_data['shipping_cost_seq'] = $cost_info['shipping_cost_seq'];
						$ship_cost_log_data['shipping_opt_seq'] = $cost_info['shipping_opt_seq'];
						$ship_cost_log_data['shipping_area_name'] = $cost_info['shipping_area_name'];
						$ship_cost_log_data['shipping_today_yn'] = $cost_info['shipping_today_yn'];
						$ship_cost_log_data['shipping_cost'] = $cost_info['shipping_cost'];
						$ship_cost_log_data['shipping_cost_today'] = $cost_info['shipping_cost_today'];
						$this->db->insert($this->shipcostlogTable, $ship_cost_log_data);
					}
				} //if end - 배송 구간 log insert
			}
		}

		return $log_seq;
	}

	// 배송정보 그룹으로 추출하기
	public function get_ship_info($shipping_group, $shipping_method=''){
		if(!$shipping_method){
			$shipKey		= explode('_',$shipping_group);
			foreach($shipKey as $idx => $val){
				if($idx === 0)		$ship_grp_seq		= $val;
				else if($idx === 1)	$ship_set_seq		= $val;
				else{
					$val = preg_replace('/[0-9]/','',$val);
					if($val)		$tmp_code_arr[]		= $val;
				}
			}
			$shipping_method	= implode('_',$tmp_code_arr);
		}

		$method_pos		= strpos($shipping_group, '_'.$shipping_method);
		$ship_info		= substr($shipping_group, 0, $method_pos);
		$tmp_method		= substr($shipping_group, $method_pos + 1, strlen($shipping_group));
		$shipKey		= explode('_',$ship_info);
		$tmp_each		= str_replace($shipping_method,'',$tmp_method);

		$return['cart_opt_seq']		= str_replace('_','',$tmp_each);
		$return['ship_grp_seq']		= $shipKey[0];
		$return['ship_set_seq']		= $shipKey[1];
		$return['shipping_code']	= $shipping_method;

		return $return;
	}

	// 배송그룹 존재여부
	public function shipping_group_exists($shipping_group_seq=''){
		$shipping_group = $this->get_shipping_group($shipping_group_seq);
		return is_array($shipping_group);
	}

	// 배송그룹셋 존재여부
	public function shipping_group_set_exists($shipping_group_seq='', $shipping_set_seq='', $shipping_set_code=''){
		$this->db->select("*");
		$this->db->where(array(
			'shipping_set_seq'=>$shipping_set_seq,
			'shipping_group_seq'=>$shipping_group_seq,
			'shipping_set_code'=>$shipping_set_code
		));
		$query		= $this->db->get($this->shipsetTable);
		$result		= $query->row_array();

		return is_array($result);
	}

	// 변경된 배송지에 대해 추가 배송비 확인
	public function change_address_add_shipping_cost($shipping_group_seq, $address)
	{
		$this->load->library('shipping');

		$query = "SELECT Cost.shipping_cost
					FROM ".$this->shipzoneTable." AS area
				   INNER
				    JOIN ".$this->shipcostTable." AS Cost
					  ON area.shipping_cost_seq = Cost.shipping_cost_seq
				   WHERE area.shipping_group_seq_tmp =".$shipping_group_seq;

		$like_query = $this->shipping->get_add_shipping_search_wheres($address,'zibun');

		$query .= " AND (".$like_query. ")";

		$query = $this->db->query($query);

		$result	= $query->row_array();

		return (int) $result['shipping_cost'];
	}

	// ------------- ### 기타 데이터 추출 :: END ### ------------- //


	# 간편결제API 주문불가 배송정책 체크 @2016-10-17
	public function add_shipping_partner_possible_check($params, $partner="npay"){

		//간편결제API 주문시 해당 배송정책 사용 가능 여부
		$partner_possible				= true;
		$partner_impossible_message	= array();

		# 기본배송비 체크 시작 ----------------------------------------------------------------------------------
		if($params['shipping_set_code'] != "direct_store" && $params['std_use'] != "Y"){
			$partner_possible				= false;
			$partner_impossible_message[]	= "기본배송정책 사용안함";
		}

		if($params['delivery_nation'] != 'korea'){
			$partner_possible				= false;
			$partner_impossible_message[]	= "해외배송 불가";
		}
		if($params['delivery_limit'] == 'limit'){
			$partner_possible				= false;
			$partner_impossible_message[]	= "기본배송비-지정 지역 배송 불가";
		}
		if($partner == "npay") {
			if(in_array($params['shipping_opt_type']['std'],array("amount_rep","cnt","weight","weight_rep"))){
				$partner_possible				= false;
				$partner_impossible_message[]	= "금액(구간반복), 수량(구간입력), 무게(구간입력), 무게(구간반복) 사용불가";
			}
		} else if($partner == "talkbuy") {
			/**
			 * 카카오페이 - 수량별 배송비 부과 방식. 배송비 유형이 수량별 부과인 경우 필수값.
			 * - REPEAT : 일정 수량별 반복 부과
			 * - RANGE : 수량 구간별 부과
			 */
			if(in_array($params['shipping_opt_type']['std'],array("amount_rep","weight","weight_rep"))){
				$partner_possible				= false;
				$partner_impossible_message[]	= "금액(구간반복), 무게(구간입력), 무게(구간반복) 사용불가";
			}

			// RANGE 수량(구간입력) 인 경우 3구간 제한만 가능함.
			if($params['shipping_opt_type']['std'] == "cnt"){
				if(count($params['shipping_cost']['std']) > 3){
					$partner_possible				= false;
					$partner_impossible_message[]	= "수량(구간입력)-3단계까지 사용 가능";
				}
			}

			if($params['shipping_set_code'] == 'quick' ) {
				if($params['prepay_info'] != 'postpaid' || $params['shipping_opt_type']['std'] != 'free') {
					$partner_possible				= false;
					$partner_impossible_message[]	= "퀵서비스는 착불-무료만 가능";
				}
				if($params['zone_count']['std'][0] != 0) {
					$partner_possible				= false;
					$partner_impossible_message[]	= "퀵서비스는 전국 배송만 가능함";
				}
			}

			/**
			 * 카카오페이 기본배송비는 20만원 이하만 가능합니다.
			 */
			if((int)$params['shipping_cost']['std'][0] > 200000) {
				$partner_possible				= false;
				$partner_impossible_message[]	= "기본배송비는 20만원 이하 가능";
			}
		}

		if($params['shipping_opt_type']['std'] == "amount"){
			if(count($params['shipping_cost']['std']) > 2){
				$partner_possible				= false;
				$partner_impossible_message[]	= "조건부 무료배송정책에 위배-금액(구간입력) 2단계 이상 입력 불가";
			}
			if($params['shipping_cost']['std'][1] > 0){
				$partner_possible				= false;
				$partner_impossible_message[]	= "조건부 무료배송정책에 위배-금액(구간입력) 2단계 배송비 오류";
			}
		}

		if($params['shipping_opt_type']['std'] == "cnt_rep"){
			// 네이버페이 첫번째 섹션 끝나는값이 1개 미만, 0원일때 사용 가능 2018-05-23
			if($params['section_ed']['std'][0] != 1 || $params['shipping_cost']['std'][0] != 0.00){
				$partner_possible				= false;
				$partner_impossible_message[]	= "수량(구간반복)-일정수량별 반복부과만 사용가능(ex:3개당 2,500원)";
			}
		}
		# 기본배송비 체크 종료 ----------------------------------------------------------------------------------

		# 추가배송비 체크 시작 ----------------------------------------------------------------------------------
		/**
		 * 카카오페이 는 추가배송비 별도로 사용함
		 */
		if($partner == "npay") {
			if($params['add_use'] == "Y"){
				if($params['shipping_opt_type']['add'] != "fixed"){
					$partner_possible				= false;
					$partner_impossible_message[]	= "추가배송비-고정배송비만 사용가능";
				}
			}
		}
		# 추가배송비 체크 종료 ----------------------------------------------------------------------------------

		# 희망배송일 체크 시작 ----------------------------------------------------------------------------------
		if($params['hop_use'] == "Y"){
			$partner_possible				= false;
			$partner_impossible_message[]	= "희망배송일 사용불가";
		}
		# 희망배송일 체크 종료 ----------------------------------------------------------------------------------

		# 매장수령 체크 시작 ----------------------------------------------------------------------------------
		if($params['store_use'] == "Y"){
		}
		# 매장수령 체크 종료 ----------------------------------------------------------------------------------


		return array($partner_possible,$partner_impossible_message);

	}

	// 배송그룹 옵션 리스트 가져오기
	public function get_shipping_option_list($shipping_group_seq = ''){
		$sql = "SELECT * FROM `fm_shipping_option` WHERE `shipping_group_seq` = ?";
		$query	= $this->db->query($sql, $shipping_group_seq);
		$res	= $query->result_array();

		return $res;
	}

	public function get_shipping_type_txt(){
		if	(!$this->default_type_code_flag) {
			$lang = $this->config_system['language'];
			foreach($this->default_type_code as $key => $val){
				$ret = getAlert($val);
				$this->default_type_code[$key] = $ret;
			}
			$this->default_type_code_flag = true;
		}
	}

	// 전체 배송그룹명 리스트(주문엑셀다운용)
	public function get_shipping_group_name_list($provider_seq = 0, $params = null) {
		if(defined('__SELLERADMIN__')){
			$provider_seq = $this->providerInfo['provider_seq'];
		}
		$addWhere		= '';
		$provider_seq	= (int) $provider_seq;
		if ($provider_seq > 0)
			$addWhere	= "(G.shipping_provider_seq = {$provider_seq} or (G.shipping_provider_seq=1 and G.default_yn='Y')) AND";

		$sql	= "	SELECT *
					FROM
						fm_shipping_grouping G
					INNER JOIN
						fm_shipping_set O
					ON
						G.shipping_group_seq = O.shipping_group_seq
					WHERE
						{$addWhere} hidden_grp = 'N'
				";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		$shippingGroupList	= array();
		foreach ($result as $row) {
			if (!isset($shippingGroupList[$row['shipping_group_seq']])) {
				$nowShippingGroup	= array();
				if ($provider_seq > 0 && $row['shipping_provider_seq'] == 1){
					$row['shipping_group_name'] = "[위탁배송]".$row['shipping_group_name'];
				}
				$shippingGroupList[$row['shipping_group_seq']]		= $row['shipping_group_name'];
			}
		}
		return $shippingGroupList;
	}

	// 전체 배송그룹 리스트 검색
	public function get_shipping_group_list_all() {
		$sql	= "SELECT
					G.shipping_group_seq,
					G.shipping_provider_seq,
					G.shipping_group_name
				FROM
					fm_shipping_grouping G
				WHERE
					G.hidden_grp = 'N'
					AND G.shipping_calcul_type != 'dummy'
				ORDER BY G.shipping_provider_seq ASC, G.shipping_group_seq ASC";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		$shippingGroupList	= array();
		foreach ($result as $row) {
			$shippingGroupList[$row['shipping_provider_seq']][$row['shipping_group_seq']] = $row['shipping_group_name'];
		}

		return $shippingGroupList;
	}

	public function get_shipping_for_feed()
	{
		$sql	= "SELECT * FROM fm_shipping_cost a
					INNER JOIN fm_shipping_option b ON b.shipping_opt_seq = a.shipping_opt_seq
					INNER JOIN fm_shipping_set c ON c.shipping_group_seq = b.shipping_group_seq
					INNER JOIN fm_shipping_grouping d ON d.shipping_group_seq = c.shipping_group_seq
				WHERE
					d.shipping_provider_seq = 1
					AND d.shipping_calcul_type != 'dummy'
					AND b.default_yn =  'Y'
					AND c.default_yn =  'Y'
					AND d.default_yn =  'Y'";

		$query			= $this->db->query($sql);
		$result			= $query->result_array();

		$return			= array();
		$datas			= array();

		$listNames		= array();
		$setDatas		= array();
		$basicNames		= array();
		$addNames		= array();

		$basicKeys		= 0;
		$addKeys		= 0;
		$opt_seq_old	= 0;

		$i = 0;
		$j = 0;

		$zoneLists = array();
		foreach($result as $k => $v){
			if($k == 0){
				$datas['shipping_group_seq']	= $v['shipping_group_seq'];
				$datas['shipping_group_name']	= $v['shipping_group_name'];
				$datas['shipping_set_seq']		= $v['shipping_set_seq'];
				$datas['shipping_set_name']		= $v['shipping_set_name'];
			}

			if($v['shipping_set_type'] == 'std'){
				if($v['shipping_opt_seq'] != $opt_seq_old){
					$basicKeys = 0;
				}

				$basicNames[$v['shipping_opt_seq']][$basicKeys]['shipping_area_name']	= $v['shipping_area_name'];
				$basicNames[$v['shipping_opt_seq']][$basicKeys]['shipping_opt_type']	= $v['shipping_opt_type'];
				$basicNames[$v['shipping_opt_seq']][$basicKeys]['section_st']			= $v['section_st'];
				$basicNames[$v['shipping_opt_seq']][$basicKeys]['section_ed']			= $v['section_ed'];
				$basicNames[$v['shipping_opt_seq']][$basicKeys]['shipping_cost_seq']	= $v['shipping_cost_seq'];
				$basicNames[$v['shipping_opt_seq']][$basicKeys]['shipping_cost']		= $v['shipping_cost'];
				$basicKeys++;

				if($v['section_st'] == 0){
					$listNames[] = 'basic_'.$i;

					//get shipping zone
					$sql	= "SELECT * FROM fm_shipping_area_detail WHERE shipping_cost_seq = ?";
					$query	= $this->db->query($sql, $v['shipping_cost_seq']);
					$zones  = $query->result_array();

					if($zones){
						$zoneLists['basic_'.$i] = $zones;
					}

					$i++;
				}
			} else {
				if($v['shipping_opt_seq'] != $opt_seq_old){
					$addKeys = 0;
				}

				/*
				$addNames[$v['shipping_opt_seq']][$addKeys]['shipping_area_name']	= $v['shipping_area_name'];
				$addNames[$v['shipping_opt_seq']][$addKeys]['shipping_opt_type']	= $v['shipping_opt_type'];
				$addNames[$v['shipping_opt_seq']][$addKeys]['section_st']			= $v['section_st'];
				$addNames[$v['shipping_opt_seq']][$addKeys]['section_ed']			= $v['section_ed'];
				$addNames[$v['shipping_opt_seq']][$addKeys]['shipping_cost_seq']	= $v['shipping_cost_seq'];
				$addNames[$v['shipping_opt_seq']][$addKeys]['shipping_cost']		= $v['shipping_cost'];
				$addKeys++;

				if($v['section_st'] == 0){
					$listNames[] = 'add_'.$j;

					//get shipping zone
					$sql	= "SELECT * FROM fm_shipping_area_detail WHERE shipping_cost_seq = ?";
					$query	= $this->db->query($sql, $v['shipping_cost_seq']);
					$zones  = $query->result_array();

					if($zones){
						$zoneLists['add_'.$j] = $zones;
					}

					$j++;
				}
				*/
			}

			$opt_seq_old = $v['shipping_opt_seq'];
		}

		$basicInfos = array();
		$addInfos = array();

		for($i=0; $i<$basicKeys; $i++){
			foreach($basicNames as $basic){
				$basicInfos[$i][] = $basic[$i];
			}
		}

		for($i=0; $i<$addKeys; $i++){
			foreach($addNames as $add){
				$addInfos[$i][] = $add[$i];
			}
		}

		$return['info']			= $datas;
		$return['listNames']	= $listNames;
		$return['basicInfos']	= $basicInfos;
		$return['addInfos']		= $addInfos;
		$return['zoneLists']	= $zoneLists;

		return $return;
	}


	public function get_ship_zone($shipping_group_seq, $shipping_opt_seq, $zone_idx = null)
	{
		if(!$zone_idx){
			$zone_idx = 0;
		}

		$sqlLimit = " LIMIT ".$zone_idx.", 1";

		$sql = "seLECT * FROM fm_shipping_area_detail as de
					INNER JOIN (SELECT shipping_cost_seq FROM fm_shipping_cost WHERE shipping_group_seq_tmp = ? AND shipping_opt_seq = ? ORDER BY shipping_cost_seq ASC".$sqlLimit.") as co WHERE de.shipping_cost_seq = co.shipping_cost_seq;";

		$query	= $this->db->query($sql, array($shipping_group_seq, $shipping_opt_seq));
		$setRes	= $query->result_array();

		return $setRes;
	}

	//shipping_otp_delete
	public function get_option_seqs($params)
	{
		if($params['p_type']){
			$params['shipping_set_type'] = $params['p_type'];
		}

		if($params['idx'] == 'limit' || $params['idx'] == 'unlimit'){
			$where = array(
				'shipping_set_seq'		=> $params['shipping_set_seq'],
				'shipping_group_seq'	=> $params['shipping_group_seq'],
				'shipping_set_type'		=> $params['shipping_set_type']
			);

			$this->db->select('*');
			$this->db->where($where);
			$options = $this->db->get("fm_shipping_option");

			$optionSeqs = array();
			foreach($options->result_array() as $val){
				$optionSeqs[] = $val['shipping_opt_seq'];
			}
		} else {
			$where = array(
				'shipping_set_seq' => $params['shipping_set_seq'],
				'shipping_group_seq' => $params['shipping_group_seq'],
				'shipping_set_type' => $params['shipping_set_type']
			);

			$this->db->select('*');
			$this->db->where($where);
			$options = $this->db->get("fm_shipping_option");

			$optionSeqs = array();
			foreach($options->result_array() as $val){
				$optionSeqs[] = $val['shipping_opt_seq'];
			}
		}

		return $optionSeqs;
	}

	//shipping_otp_delete
	public function get_cost_seqs($optionSeqs, $params)
	{
		$this->db->select('*');
		$this->db->where_in('shipping_opt_seq', $optionSeqs);
		$costs = $this->db->get("fm_shipping_cost");

		$costsData	= array();
		$costSeqs	= array();
		$j			= 0;

		foreach($costs->result_array() as $k => $v){
			if($v['shipping_opt_seq'] != $before && $k > 0){
				$j++;
			}
			$costsData[$j][] = $v['shipping_cost_seq'];
			$before = $v['shipping_opt_seq'];
		}

		if($params['delivery_limit'] != 'limit' && $params['delivery_limit'] != 'unlimit'){
			foreach($costsData as $v){
				if($v[$params['idx']] > 0){
					$costSeqs[] = $v[$params['idx']];
				}
			}
		} else {
			$costSeqs = $costsData;
		}

		return $costSeqs;
	}

	//for add_national_pop
	public function get_cost_list($shipping_group_seq, $shipping_set_seq)
	{
		$return = array();

		$this->db->select('*');
		$this->db->where('shipping_group_seq', $shipping_group_seq);
		$this->db->where('shipping_set_seq', $shipping_set_seq);
		$this->db->order_by('shipping_set_seq ASC', 'shipping_opt_seq ASC');
		$shipping_opt = $this->db->get("fm_shipping_option");

		$otps = array();
		$sec_st = array();
		$sec_ed = array();
		foreach($shipping_opt->result_array() as $k => $v){
			$otps[$v['shipping_set_type']][] = $v['shipping_opt_seq'];
			$sec_st[$v['shipping_set_type']][] = $v['section_st'];
			$sec_ed[$v['shipping_set_type']][] = $v['section_ed'];

			$return['shipping_opt_type'][$v['shipping_set_type']]	= $v['shipping_opt_type'];
			$return['delivery_limit'][$v['shipping_set_type']]		= $v['delivery_limit'];
		}

		$return['section_st']	= $sec_st;
		$return['section_ed']	= $sec_ed;

		$zoneCostSeqs = array();
		$zoneAreas = array();

		foreach($otps as $type => $otp){
			$this->db->select('*');
			$this->db->where_in('shipping_opt_seq', $otp);
			$this->db->order_by('shipping_opt_seq, shipping_cost_seq ASC');
			$shipping_cost	= $this->db->get("fm_shipping_cost");
			$shipping_cost	= $shipping_cost->result_array();

			foreach($shipping_cost as $idx => $costSeqs){
				$zoneAreas[$type][$costSeqs['shipping_opt_seq']][]						= $costSeqs['shipping_area_name'];
				$zoneCostSeqs[$type][$costSeqs['shipping_opt_seq']][]					= $costSeqs['shipping_cost_seq'];
				$return['shipping_cost'][$type][$costSeqs['shipping_opt_seq']][]		= $costSeqs['shipping_cost'];
				$return['shipping_cost_seq'][$type][$costSeqs['shipping_opt_seq']][]	= $costSeqs['shipping_cost_seq'];
			}

			$zoneAreas[$type]					= array_values($zoneAreas[$type]);
			$zoneCostSeqs[$type]				= array_values($zoneCostSeqs[$type]);
			$return['shipping_cost'][$type]		= array_values($return['shipping_cost'][$type]);
			$return['shipping_cost_seq'][$type] = array_values($return['shipping_cost_seq'][$type]);
		}

		if(count($zoneAreas) <= 0){
			return false;
		}

		foreach($zoneCostSeqs as $type => $datas){
			$datas = end($datas);
			$zoneDatas = end($zoneAreas[$type]);
			foreach($datas as $k => $v){
				$return['zone_cost_seq'][$type][] = $v;
				$return['area_name'][$type][] = $zoneDatas[$k];

				$this->db->select('area_detail_address_street, area_detail_address_zibun, area_detail_address_join, area_detail_address_txt');
				$this->db->where('shipping_cost_seq', $v);
				$streetInfo = $this->db->get("fm_shipping_area_detail");
				foreach($streetInfo->result_array() as $street){
					$return['sel_address_street'][$type][$k][] 	= $street['area_detail_address_street'];
					$return['sel_address_zibun'][$type][$k][] 	= $street['area_detail_address_zibun'];
					$return['sel_address_join'][$type][$k][] 	= $street['area_detail_address_join'];
					$return['sel_address_txt'][$type][$k][] 	= $street['area_detail_address_txt'];
				}

				$this->db->select('shipping_cost_seq');
				$this->db->where('shipping_cost_seq', $v);
				$zoneCount = $this->db->count_all_results("fm_shipping_area_detail");
				$return['zone_count'][$type][] = $zoneCount;
			}
		}

		$return['shipping_opt_seq']	= $otps;

		$this->db->select('add_use, hop_use, reserve_use, store_use');
		$this->db->where('shipping_group_seq', $shipping_group_seq);
		$this->db->where('shipping_set_seq', $shipping_set_seq);
		$useInfo = $this->db->get("fm_shipping_set");

		foreach($useInfo->result_array() as $v){
			foreach($v as $k => $vv){
				$return[$k] = $vv;
			}
		}

		return $return;
	}

	//for insert zones
	public function get_seqs($params)
	{
		//$this->db->trans_begin();

		$return = array();

		$this->db->select('*');
		$this->db->where('shipping_group_seq', $params['shipping_group_seq']);
		$this->db->where('shipping_set_seq', $params['shipping_set_seq']);
		$this->db->order_by('shipping_set_seq ASC', 'shipping_opt_seq ASC');
		$shipping_opt = $this->db->get("fm_shipping_option");

		$otps = array();
		foreach($shipping_opt->result_array() as $k => $v){
			$otps[$v['shipping_set_type']][] = $v['shipping_opt_seq'];
		}

		$sec_st = array();
		$sec_ed = array();
		$sec_cost = array();
		if($params['shipping_opt_type'] == 'fixed' || $params['shipping_opt_type'] == 'free'){
			$sec_st[]	= 0;
			$sec_ed[]	= 0;
			if( $params['shipping_opt_type'] == 'free'){
				$sec_cost[]	= 0;
			} else {
				$sec_cost[]	= $params['shipping_opt_sec_cost'][0];
			}
		} else {
			$sec_st		= $params['shipping_opt_sec_st'];
			$sec_ed		= $params['shipping_opt_sec_ed'];
			$sec_cost	= $params['shipping_opt_sec_cost'];
		}

		$costSeqs = array();

		//option insert, cost insert, area_delete update 재설정
		if(count($otps[$params['p_type']]) != count($sec_cost)){
			if(count($otps[$params['p_type']]) > 0){
				$this->db->where_in('shipping_opt_seq', $otps[$params['p_type']]);
				$this->db->delete('fm_shipping_option');

				$this->db->select('shipping_cost_seq');
				$this->db->where_in('shipping_opt_seq', $otps[$params['p_type']]);
				$costData = $this->db->get('fm_shipping_cost');
				foreach($costData->result_array() as $v){
					$oldCostSeqs[] = $v['shipping_cost_seq'];
				}

				$this->db->where_in('shipping_opt_seq', $otps[$params['p_type']]);
				$this->db->delete('fm_shipping_cost');
			}

			$otps = array();
			foreach($sec_cost as $k => $cost){
				$datas = array();
				$datas['shipping_group_seq']	= $params['shipping_group_seq'];
				$datas['shipping_set_seq']		= $params['shipping_set_seq'];
				$datas['shipping_set_code']		= 'delivery';
				$datas['shipping_set_name']		= '택배';
				$datas['shipping_set_type']		= $params['p_type'];
				$datas['shipping_opt_type']		= $params['shipping_opt_type'];
				$datas['shipping_provider_seq']	= 1;
				$datas['delivery_limit']		= 'unlimit';
				$datas['default_yn']			= 'N';
				$datas['section_st']			= $sec_st[$k];
				$datas['section_ed']			= $sec_ed[$k];

				$this->db->insert("fm_shipping_option", $datas);
				$optSeq = $this->db->insert_id();
				$opts[] = $optSeq;

				if(!$params['zone_name']){
					if($params['nation'] == 'korea'){
						$params['zone_name'] = '대한민국';
					} else {
						$params['zone_name'] = '해외국가';
					}
				}
				$datas = array();
				$datas['shipping_opt_seq']		= $optSeq;
				$datas['shipping_group_seq_tmp']= $params['shipping_group_seq'];
				$datas['shipping_area_name']	= $params['zone_name'];
				$datas['shipping_cost']			= $cost;

				$this->db->insert("fm_shipping_cost", $datas);
				$costSeqs[$optSeq][] = $this->db->insert_id();
				unset($datas);
			}

			$costSeq = end($costSeqs);
			$oldCostSeq = end($oldCostSeqs);

			if($oldCostSeq > 0){
				$this->db->where('shipping_cost_seq', $oldCostSeq);
				$this->db->update('fm_shipping_area_detail', array('shipping_cost_seq' => $costSeq));
			}
		} else if ($params['shipping_cost_seq'] <= 0) {
			//cost insert
			foreach($sec_cost as $k => $cost){
				$datas = array();
				$datas['shipping_opt_seq']		= $otps[$params['p_type']][$k];
				$datas['shipping_group_seq_tmp']= $params['shipping_group_seq'];
				$datas['shipping_area_name']	= $params['zone_name'];
				$datas['shipping_cost']			= $cost;

				$this->db->insert("fm_shipping_cost", $datas);
				$costSeqs[$otps[$params['p_type']][$k]][] = $this->db->insert_id();
				unset($datas);
			}
		} else {
			$this->db->select('shipping_cost_seq, shipping_opt_seq');
			$this->db->where_in('shipping_opt_seq', $otps[$params['p_type']]);
			$shipping_cost = $this->db->get("fm_shipping_cost");

			foreach($shipping_cost->result_array() as $k => $v){
				$costSeqs[$v['shipping_opt_seq']][] = $v['shipping_cost_seq'];
			}
		}

		/*
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
		} else {
			debug_var($this->db);
			exit;
			//$this->db->trans_commit();
		}
		*/


		return $costSeqs;
	}

	public function del_shipping_dummy($shipping_group_seq, $set_seqs, $cost_seqs){
		$this->db->where('shipping_group_seq', $shipping_group_seq);
		$this->db->where_not_in('shipping_set_seq', $set_seqs);
		$this->db->delete('fm_shipping_set');

		$this->db->where('shipping_group_seq', $shipping_group_seq);
		$this->db->where_not_in('shipping_set_seq', $set_seqs);
		$this->db->delete('fm_shipping_option');

		if(count($cost_seqs) > 0){
			$this->db->where('shipping_group_seq_tmp', $shipping_group_seq);
			$this->db->where_not_in('shipping_cost_seq', $cost_seqs);
			$this->db->delete('fm_shipping_cost');

			$this->db->where('shipping_group_seq_tmp', $shipping_group_seq);
			$this->db->where_not_in('shipping_cost_seq', $cost_seqs);
			$this->db->delete('fm_shipping_area_detail');
		}
	}

	public function reset_shipping($shipping_group_seq, $shipping_group_dummy_seq, $calcul_type){
		$this->del_shipping_dummy_all($shipping_group_seq, 'origin');

		$this->db->update('fm_shipping_grouping', array('shipping_group_seq' => $shipping_group_seq, 'shipping_calcul_type' => $calcul_type, 'admin_memo' => ''), array('shipping_group_seq' => $shipping_group_dummy_seq));
		$this->db->update('fm_shipping_set', array('shipping_group_seq' => $shipping_group_seq), array('shipping_group_seq' => $shipping_group_dummy_seq));
		$this->db->update('fm_shipping_option', array('shipping_group_seq' => $shipping_group_seq), array('shipping_group_seq' => $shipping_group_dummy_seq));
		$this->db->update('fm_shipping_cost', array('shipping_group_seq_tmp' => $shipping_group_seq), array('shipping_group_seq_tmp' => $shipping_group_dummy_seq));
		$this->db->update('fm_shipping_area_detail', array('shipping_group_seq_tmp' => $shipping_group_seq), array('shipping_group_seq_tmp' => $shipping_group_dummy_seq));
		$this->db->update('fm_shipping_store', array('shipping_group_seq_tmp' => $shipping_group_seq), array('shipping_group_seq_tmp' => $shipping_group_dummy_seq));
	}

	public function del_shipping_dummy_all($shipping_group_seq, $type='dummy'){
		if(defined('__SELLERADMIN__')){
			$manager_id = $_SESSION['provider']['provider_id'];
		} else {
			$manager_id = $_SESSION['manager']['manager_id'];
		}

		$this->db->select('shipping_group_seq');
		if( $type=='dummy' ){
			$this->db->where('shipping_calcul_type', 'dummy');
			$this->db->like('admin_memo', $manager_id);
			$this->db->where('temporary_seq', $shipping_group_seq);
		} else {
			$this->db->where('shipping_group_seq', $shipping_group_seq);
		}
		$sqls = $this->db->get("fm_shipping_grouping");

		foreach($sqls->result_array() as $seq){
			$this->db->where('shipping_group_seq', $seq['shipping_group_seq']);
			$this->db->delete('fm_shipping_grouping');

			$this->db->where('shipping_group_seq', $seq['shipping_group_seq']);
			$this->db->delete('fm_shipping_set');

			$this->db->where('shipping_group_seq', $seq['shipping_group_seq']);
			$this->db->delete('fm_shipping_option');

			$this->db->where('shipping_group_seq_tmp', $seq['shipping_group_seq']);
			$this->db->delete('fm_shipping_cost');

			$this->db->where('shipping_group_seq_tmp', $seq['shipping_group_seq']);
			$this->db->delete('fm_shipping_area_detail');

			$this->db->where('shipping_group_seq_tmp', $seq['shipping_group_seq']);
			$this->db->delete('fm_shipping_store');
		}
	}

	public function set_shipping_dummy($shipping_group_real_seq)
	{
		if(defined('__SELLERADMIN__')){
			$manager_id = $_SESSION['provider']['provider_id'];
		} else {
			$manager_id = $_SESSION['manager']['manager_id'];
		}

		$shipping_group_dummy_seq = 0;
		//기 관리자가 사용 했으나 기한이 지난 이전 데이터 삭제
		$this->del_shipping_dummy_all($shipping_group_real_seq, 'dummy');

		$data = array(
			'shipping_group_name'	=> $manager_id,
			'shipping_calcul_type'	=> 'dummy',
			'admin_memo'			=> $manager_id,
			'regist_date'			=> date("Y-m-d H:i:s")
		);
		$result = $this->db->insert('fm_shipping_grouping', $data);
		$shipping_group_dummy_seq = $this->db->insert_id();
		$shipping_calcul_type_ori = '';
		if($shipping_group_real_seq > 0){
			//기존 데이터 이전
			$this->db->select("*");
			$this->db->where('shipping_group_seq', $shipping_group_real_seq);
			$query	= $this->db->get('fm_shipping_grouping');
			$result	= $query->result_array();
			foreach($result as $k => $v){
				$shipping_calcul_type_ori = $v['shipping_calcul_type'];
				unset($v['shipping_group_seq']);
				$v['shipping_calcul_type']	= 'dummy';
				$v['admin_memo']			= $manager_id."||".$v['admin_memo'];
				$v['temporary_seq']			= $shipping_group_real_seq;
				$this->db->set($v)->where('shipping_group_seq', $shipping_group_dummy_seq)->update('fm_shipping_grouping');
			}

			$this->db->select("*");
			$this->db->where('shipping_group_seq', $shipping_group_real_seq);
			$query	= $this->db->get('fm_shipping_set');
			$result	= $query->result_array();
			$datas = array();
			$shipping_set_seq = array();
			foreach($result as $k => $v){
				$seq = $v['shipping_set_seq'];
				unset($v['shipping_set_seq']);
				$v['shipping_group_seq'] = $shipping_group_dummy_seq;
				$this->db->insert('fm_shipping_set', $v);
				$shipping_set_seq[$seq] = $this->db->insert_id();

				//매장 데이터
				$this->db->select("*");
				$this->db->where('shipping_set_seq', $seq);
				$query	= $this->db->get('fm_shipping_store');
				$result	= $query->result_array();
				foreach($result as $k => $v){
					unset($v['shipping_store_seq']);
					$v['shipping_set_seq'] = $shipping_set_seq[$seq];
					$v['shipping_group_seq_tmp'] = $shipping_group_dummy_seq;
					$this->db->insert('fm_shipping_store', $v);
				}
			}

			$this->db->select("*");
			$this->db->where('shipping_group_seq', $shipping_group_real_seq);
			$query	= $this->db->get('fm_shipping_option');
			$result	= $query->result_array();
			$datas = array();
			$shipping_opt_seq = array();
			foreach($result as $k => $v){
				$seq = $v['shipping_opt_seq'];
				unset($v['shipping_opt_seq']);
				$v['shipping_group_seq']	= $shipping_group_dummy_seq;
				$v['shipping_set_seq']		= $shipping_set_seq[$v['shipping_set_seq']];

				if($v['shipping_set_seq'] > 0){
					$this->db->insert('fm_shipping_option', $v);
					$shipping_opt_seq[$seq] = $this->db->insert_id();
				}
			}

			$this->db->select("*");
			$this->db->where('shipping_group_seq_tmp', $shipping_group_real_seq);
			$query	= $this->db->get('fm_shipping_cost');
			$result	= $query->result_array();
			$datas = array();
			$shipping_cost_seq = array();
			foreach($result as $k => $v){
				$seq = $v['shipping_cost_seq'];
				unset($v['shipping_cost_seq']);
				$v['shipping_group_seq_tmp'] = $shipping_group_dummy_seq;
				$v['shipping_opt_seq']		= $shipping_opt_seq[$v['shipping_opt_seq']];

				if($v['shipping_opt_seq'] > 0){
					$this->db->insert('fm_shipping_cost', $v);
					$shipping_cost_seq[$seq] = $this->db->insert_id();
				}
			}

			$this->db->select("*");
			$this->db->where('shipping_group_seq_tmp', $shipping_group_real_seq);
			$query	= $this->db->get('fm_shipping_area_detail');
			$result	= $query->result_array();
			$datas = array();
			foreach($result as $k => $v){
				unset($v['area_detail_seq']);
				$v['shipping_group_seq_tmp'] = $shipping_group_dummy_seq;
				$v['shipping_cost_seq']		= $shipping_cost_seq[$v['shipping_cost_seq']];
				if($v['shipping_cost_seq'] > 0){
					$this->db->insert('fm_shipping_area_detail', $v);
				}
			}
		}

		$return = array();
		$return['shipping_group_dummy_seq'] = $shipping_group_dummy_seq;
		$return['shipping_calcul_type']		= $shipping_calcul_type_ori;

		return $return;
	}

	public function del_shipping_addr_global($shipping_group_seq)
	{
		$this->db->where('shipping_group_seq_tmp', $shipping_group_seq);
		$this->db->where_not_in('area_nation_type', 'korea');
		$this->db->delete('fm_shipping_area_detail');
	}

	// 배송지 제한 구역 삭제
	public function del_shipping_area_detail($params = array())
	{
		if($params){
			if($params['shipping_group_seq_tmp']){
				$this->db->where('shipping_group_seq_tmp', $params['shipping_group_seq_tmp']);
			}
			if($params['shipping_cost_seq']){
				$this->db->where('shipping_cost_seq', $params['shipping_cost_seq']);
			}
			$this->db->delete('fm_shipping_area_detail');
		}
	}

	/**
	 * 배송그룹 수정 시 최종 데이터 점검
	 */
	public function inspect_shipping_data($params) {
		$shipping_group_seq = $params['shipping_group_seq'];
		$set_info_groups = $this->get_shipping_set($shipping_group_seq);

		foreach($set_info_groups as $set_info) {
			$set_info = $this->load_shipping_set_detail($set_info['shipping_set_seq']);

			// 매장수령 예외
			if($set_info['shipping_set_code'] == 'direct_store') {
				// 1. shipping_cost 테이블 데이터 삭제
				$shippping_opt_seq = array_keys($set_info['shipping_cost_seq_list']);
				if(is_array($shippping_opt_seq)) {
					$this->db->where_in('shipping_opt_seq', $shippping_opt_seq);
					$this->db->delete($this->shipcostTable);
				}
			}
		}
	}
}
