<?php
require_once(APPPATH ."models/scmbasicmodel".EXT);
class scmmodel extends scmbasicmodel {

	########## ↑↑ START ↑↑ ##### ↓↓ 담당자 ↓↓ ##########

	// 담당자 검색
	public function get_manager($sc){
		$selectSql	= "select * ";
		$fromSql	= "from fm_scm_manager ";
		$whereSql	= "where manager_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by parent_seq desc, manager_seq desc";

		// 부모테이블 검색
		if	($sc['parent_table']){
			$whereSql	.= " and parent_table = ? ";
			$addBind[]	= $sc['parent_table'];
		}

		// 부모번호 검색
		if	($sc['parent_seq']){
			$whereSql	.= " and parent_seq = ? ";
			$addBind[]	= $sc['parent_seq'];
		}

		$sql		= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		$query		= $this->db->query($sql, $addBind);
		$result		= $query->result_array();

		return $result;
	}

	// 담당자 정보 등록/수정
	public function save_manager($parent_table, $parent_seq, $params){
		if	($this->chkScmConfig()){
			if	($parent_table && $parent_seq > 0 && is_array($params) && count($params) > 0){
				foreach($params as $m => $mng){
					if	($mng['manager_seq'] > 0){
						$whereParam		= array('parent_table'	=> $parent_table,
												'parent_seq'	=> $parent_seq,
												'manager_seq'	=> $mng['manager_seq']);
						unset($mng['manager_seq']);
						$this->db->where($whereParam);
						$this->db->update('fm_scm_manager', $mng);
					}else{
						unset($mng['manager_seq']);
						$mng['parent_table']	= $parent_table;
						$mng['parent_seq']		= $parent_seq;
						$this->db->insert('fm_scm_manager', $mng);
					}
				}
			}
		}
	}

	########## ↑↑ 담당자 ↑↑ ##### ↓↓ 거래처 ↓↓ ##########

	// 거래처 분류 추출
	public function get_trader_group(){
		$selectSql	= "select trader_group ";
		$fromSql	= "from fm_scm_trader ";
		$whereSql	= "where length(trader_group) > 0 ";
		$groupBy	= "group by trader_group ";
		$orderBy	= "";

		$sql		= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		$query		= $this->db->query($sql);
		$group		= $query->result_array();
		if	($group) foreach($group as $k => $data){
			$result[$k]		= $data['trader_group'];
		}

		return $result;
	}

	// 거래처 검색
	public function get_trader($sc){

		$selectSql			= "select * ";
		$fromSql			= "from fm_scm_trader ";
		$whereSql			= "where trader_seq > 0 ";
		$groupBy			= "";
		$orderBy			= "order by regist_date asc ";

		// 거래처 코드 검색
		if		($sc['trader_seq']){
			$whereSql		.= " and trader_seq = ? ";
			$addBind[]		= $sc['trader_seq'];
		}elseif	($sc['sc_trader']){
			$whereSql		.= " and trader_seq like '" . addslashes(trim($sc['sc_trader'])) . "%' ";
		}elseif	($sc['trader_seq_arr']){
			if	(!is_array($sc['trader_seq_arr']))	$sc['trader_seq_arr']	= array($sc['trader_seq_arr']);
			$whereSql		.= " and trader_seq in ('" . implode("', '", $sc['trader_seq_arr']) . "') ";
		}
		// 거래처 아이디 검색
		if		($sc['trader_id']){
			$whereSql		.= " and trader_id = ? ";
			$addBind[]		= $sc['trader_id'];
		}elseif	($sc['sc_trader_id']){
			$whereSql		.= " and trader_id like '%" . addslashes(trim($sc['sc_trader_id'])) . "%' ";
		}
		// 거래처명 검색
		if		($sc['trader_name']){
			$whereSql		.= " and trader_name = ? ";
			$addBind[]		= $sc['trader_name'];
		}elseif	($sc['sc_trader_name']){
			$whereSql		.= " and trader_name like '%" . addslashes(trim($sc['sc_trader_name'])) . "%' ";
		}
		// 사업자등록번호 검색
		if		($sc['regist_number']){
			$whereSql		.= " and regist_number = ? ";
			$addBind[]		= $sc['regist_number'];
		}elseif	($sc['sc_regist_number']){
			$whereSql		.= " and regist_number like '%" . addslashes(trim($sc['sc_regist_number'])) . "%' ";
		}
		// 대표자 검색
		if		($sc['company_owner']){
			$whereSql		.= " and company_owner = ? ";
			$addBind[]		= $sc['company_owner'];
		}elseif	($sc['sc_company_owner']){
			$whereSql		.= " and company_owner like '%" . addslashes(trim($sc['sc_company_owner'])) . "%' ";
		}
		// 사용여부 검색
		if		($sc['trader_use']){
			$whereSql		.= " and trader_use = ? ";
			$addBind[]		= $sc['trader_use'];
		}
		// 거래처구분 검색
		if		($sc['trader_type']){
			$whereSql		.= " and trader_type = ? ";
			$addBind[]		= $sc['trader_type'];
		}
		// 국내/해외 검색
		if		($sc['trader_location']){
			$whereSql		.= " and trader_location = ? ";
			$addBind[]		= $sc['trader_location'];
		}
		// 거래/정산 통화 검색
		if		($sc['currency_unit']){
			$whereSql		.= " and currency_unit = ? ";
			$addBind[]		= $sc['currency_unit'];
		}
		// 거래처분류 검색
		if		($sc['trader_group']){
			if	(!is_array($sc['trader_group']))	$sc['trader_group']	= array($sc['trader_group']);
			$whereSql		.= " and trader_group in ('" . implode("', '", $sc['trader_group']) . "') ";
		}
		// 중요표시 검색
		if		($sc['favorite_chk']){
			$whereSql		.= " and favorite_chk = ? ";
			$addBind[]		= ($sc['favorite_chk'] == 'y') ? '1' : '0';
		}
		// 키워드 검색
		if		($sc['keyword']){
			$sc['keyword']	= addslashes(trim($sc['keyword']));
			$whereSql		.= " and ( "
							. "trader_id like '%" . $sc['keyword'] . "%' or "
							. "trader_seq like '" . $sc['keyword'] . "%' or "
							. "trader_name like '%" . $sc['keyword'] . "%' or "
							. "regist_number like '%" . $sc['keyword'] . "%' or "
							. "company_owner like '%" . $sc['keyword'] . "%' ) ";
		}
		// 정렬 변경
		if	($sc['orderby']){
			$orderBy	= "order by " . $sc['orderby'] . " ";
		}

		$sqlQuery['select']		= $selectSql;
		$sqlQuery['fromSql']	= $fromSql;
		$sqlQuery['whereSql']	= $whereSql;
		$sqlQuery['groupBy']	= $groupBy;
		$sqlQuery['orderBy']	= $orderBy;
		$sqlQuery['addBind']	= $addBind;
		$sqlQuery['countSql']	= $countSql;

		if	($sc['get_sql']){
			$result = $sqlQuery;
		}else{
			if	($sc['page'] > 0 && $sc['perpage'] > 0){
				$result				= pagingScmNumbering($sqlQuery,$sc);
			}else{
				$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
				$query		= $this->db->query($sql, $addBind);
				$result		= $query->result_array();
			}
		}

		return $result;
	}

	// 거래처 저장 시 parameter 체크
	public function chk_trader_params($params){

		$trader['chg_log']		= '<div>' . date('Y-m-d H:i:s') . ' '
								. $this->managerInfo['mname']
								. '(' . $this->managerInfo['manager_id'] . ')가 '
								. '거래처의 정보를 [:MODE:]하였습니다. '
								. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';

		if	($params['mode'] == 'regist'){
			if	(!$params['trader_id']){
				openDialogAlert('아이디는 필수입니다.', 400, 150, 'parent', '');
				exit;
			}
			$sc['trader_id']	= $params['trader_id'];
			$tmp				= $this->get_trader($sc);
			if	($tmp[0]['trader_seq'] > 0){
				openDialogAlert('중복된 아이디입니다.', 400, 150, 'parent', '');
				exit;
			}
			if	(!$params['currency_unit']){
				openDialogAlert('거래/정산 통화는 필수입니다.', 400, 150, 'parent', '');
				exit;
			}
			$trader['trader_id']		= $params['trader_id'];
			$trader['currency_unit']	= $params['currency_unit'];
			$trader['regist_date']		= date('Y-m-d H:i:s');
			$trader['chg_log']			= str_replace('[:MODE:]', '등록', $trader['chg_log']);
		}
		$trader['chg_log']				= str_replace('[:MODE:]', '수정', $trader['chg_log']);

		if	($params['mode'] == 'regist'){
			$trader['trader_pass']		= hash('sha256', 'firstmall1234');
		}

		// 우편번호 합치기
		if	(is_array($params['Zipcode']) && count($params['Zipcode']) > 1){
			$params['Zipcode']	= implode('', $params['Zipcode']);
		}

		$trader['trader_name']			= strip_tags($params['trader_name']);
		$trader['trader_use']			= strip_tags($params['trader_use']);
		$trader['trader_group']			= strip_tags($params['trader_group']);
		$trader['trader_type']			= strip_tags($params['trader_type']);
		$trader['trader_location']		= strip_tags($params['trader_location']);
		$trader['regist_number']		= strip_tags($params['regist_number']);
		$trader['company_owner']		= strip_tags($params['company_owner']);
		$trader['business_type']		= strip_tags($params['business_type']);
		$trader['business_category']	= strip_tags($params['business_category']);
		$trader['bank_name']			= strip_tags($params['bank_name']);
		$trader['bank_owner']			= strip_tags($params['bank_owner']);
		$trader['bank_number']			= strip_tags($params['bank_number']);
		$trader['company_url']			= strip_tags($params['company_url']);
		$trader['phone_number']			= strip_tags($params['phone_number']);
		$trader['fax_number']			= strip_tags($params['fax_number']);
		$trader['address_type']			= strip_tags($params['Address_type']);
		$trader['zipcode']				= $params['Zipcode'];
		$trader['address']				= strip_tags($params['Address']);
		$trader['address_street']		= strip_tags($params['Address_street']);
		$trader['address_detail']		= strip_tags($params['address_detail']);
		$trader['admin_memo']			= addslashes($params['admin_memo']);
		$trader['modify_date']			= date('Y-m-d H:i:s');

		if	($params['manager']){
			foreach($params['manager'] as $m => $mng){
				$manager[$m]['manager_seq']			= $mng['seq'];
				$manager[$m]['manager_name']		= $mng['name'];
				$manager[$m]['manager_partname']	= $mng['partname'];
				$manager[$m]['manager_charge']		= $mng['charge'];
				$manager[$m]['phone_number']		= $mng['phone_number'];
				$manager[$m]['extension_number']	= $mng['extension_number'];
				$manager[$m]['cellphone_number']	= $mng['cellphone_number'];
				$manager[$m]['email']				= $mng['email'];
			}
		}

		return array('trader' => $trader, 'manager' => $manager);
	}

	// 거래처 정보 등록/수정
	public function save_trader($params, $trader_seq = 0){
		if	($this->chkScmConfig()){
			if	($params){
				if	(is_array($params['zipcode']))	$params['zipcode']	= implode('', $params['zipcode']);
				if	($trader_seq > 0){
					foreach($params as $filed => $value) {
						if ($filed === 'chg_log') {
							$this->db->set('chg_log', "concat(IFNULL(chg_log, ''), '{$params['chg_log']}')", false);
						} else {
							$this->db->set($filed, $value);
						}
					}

					$this->db->where('trader_seq', $trader_seq);
					$this->db->update('fm_scm_trader');

					$result	= $trader_seq;
				}else{
					$this->db->insert('fm_scm_trader', $params);
					$result	= $this->db->insert_id();
				}
			}
		}

		return $result;
	}

	// 거래처 삭제 가능여부 체크
	public function chk_remove_trader($trader_seq){
		$bind[]				= $trader_seq;
		$result['status']	= true;

		// 거래처 상세 정보 추출
		$sql	= "select * from fm_scm_trader where trader_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$trader	= $query->row_array();
		$result['trader_name']	= $trader['trader_name'];
		$result['msg']			= $trader['trader_name'] . '의 ';

		// 주거래처
		$sql	= "select * from fm_scm_order_defaultinfo where main_trade_type = 'Y' and trader_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '주거래처인 상품이 ' . count($data) . '개 있습니다.';
			return $result;
		}

		// 발주
		$sql	= "select * from fm_scm_order where sorder_status = '1' and trader_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '완료된 발주가 ' . count($data) . '건 있습니다.';
			return $result;
		}

		// 입고
		$sql	= "select * from fm_scm_warehousing where whs_status = '1' and trader_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '완료된 입고가 ' . count($data) . '건 있습니다.';
			return $result;
		}

		// 반출
		$sql	= "select * from fm_scm_carryingout where cro_status = '1' and trader_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '완료된 반출이 ' . count($data) . '건 있습니다.';
			return $result;
		}

		// 거래처 정산
		$sql	= "select * from fm_scm_trader_account where trader_seq = ? order by act_seq desc limit 1";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->row_array();
		if	($data['act_seq'] > 0){
			$result['status']	= false;
			$result['msg']		.= '정산 내역이 있습니다.';
			return $result;
		}

		return $result;
	}

	// 거래처 삭제
	public function remove_trader($trader_seq){
		$bind[]				= $trader_seq;

		// 주 거래처가 아닌 매입정보 삭제
		$sql	= "delete from fm_scm_order_defaultinfo where main_trade_type != 'Y' and trader_seq = ? ";
		$this->db->query($sql, $bind);

		// 완료가 아닌 발주 정보 삭제
		$sql	= "delete from fm_scm_order where sorder_status != '1' and trader_seq = ? ";
		$this->db->query($sql, $bind);

		// 완료가 아닌 입고 정보 삭제
		$sql	= "delete from fm_scm_warehousing where whs_status != '1' and trader_seq = ? ";
		$this->db->query($sql, $bind);

		// 완료가 아닌 반출 정보 삭제
		$sql	= "delete from fm_scm_carryingout where cro_status != '1' and trader_seq = ? ";
		$this->db->query($sql, $bind);

		// 담당자 정보 삭제
		$sql	= "delete from fm_scm_manager where parent_table = 'trader' and parent_seq = ? ";
		$this->db->query($sql, $bind);

		// 거래처 정보 삭제
		$sql	= "delete from fm_scm_trader where trader_seq = ? ";
		$this->db->query($sql, $bind);
	}

	########## ↑↑ 거래처 ↑↑ ##### ↓↓ 창고 ↓↓ ##########

	// 창고 리스트 추출
	public function get_warehouse($sc = array()){

		$selectSql	= "select * ";
		$fromSql	= "from fm_scm_warehouse fsw ";
		$whereSql	= "where wh_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by wh_seq desc";

		// 창고 고유번호 검색
		if		($sc['wh_seq']){
			$whereSql	.= " and wh_seq = ? ";
			$addBind[]	= $sc['wh_seq'];
		}elseif	($sc['sc_wh_seq']){
			$whereSql		.= " and wh_seq like '" . addslashes(trim($sc['sc_wh_seq'])) . "%' ";
		}
		// 이동창고 포함 검색
		if		($sc['inclusion_move'] != 'Y'){
			$whereSql		.= " and wh_move < 1 ";
		}
		// 이동창고 정보 추출
		if		($sc['sc_wh_move']){
			$whereSql		.= " and wh_move = 1 ";
		}
		// 창고명 검색
		if		($sc['wh_name']){
			$whereSql	.= " and wh_name = ? ";
			$addBind[]	= $sc['wh_name'];
		}elseif	($sc['sc_wh_name']){
			$whereSql		.= " and wh_name like '%" . addslashes(trim($sc['sc_wh_name'])) . "%' ";
		}
		// 창고 분류 검색
		if		($sc['wh_group']){
			$whereSql	.= " and wh_group = ? ";
			$addBind[]	= $sc['wh_group'];
		}
		// 기본 창고 검색
		if		($sc['wh_default']){
			$whereSql	.= " and wh_default = 1 ";
		}
		// 중요표시 검색
		if		($sc['favorite_chk']){
			$whereSql		.= " and favorite_chk = ? ";
			$addBind[]		= ($sc['favorite_chk'] == 'y') ? '1' : '0';
		}
		// 등록일 검색
		if		($sc['regist_sdate'] && $sc['regist_edate']){
			$whereSql	.= " and wh_regist_date >= ? and wh_regist_date <= ? ";
			$addBind[]	= $sc['regist_sdate'] . ' 00:00:00';
			$addBind[]	= $sc['regist_edate'] . ' 23:59:59';
		}elseif	($sc['regist_sdate']){
			$whereSql	.= " and wh_regist_date >= ? ";
			$addBind[]	= $sc['regist_sdate'] . ' 00:00:00';
		}elseif	($sc['regist_edate']){
			$whereSql	.= " and wh_regist_date <= ? ";
			$addBind[]	= $sc['regist_edate'] . ' 23:59:59';
		}
		// 키워드 검색
		if		($sc['keyword']){
			$sc['keyword']	= addslashes(trim($sc['keyword']));
			$whereSql		.= " and ( "
							. "wh_name like '%" . $sc['keyword'] . "%' or "
							. "wh_seq like '" . $sc['keyword'] . "%' ) ";
		}
		// 창고내 총 재고
		if		($sc['get_totalStock']){
			$selectSql	.= ", (select SUM(IFNULL(ea, 0)) from fm_scm_location_link "
						. "where wh_seq = fsw.wh_seq group by wh_seq limit 1 ) as totalStock ";
		}

		// 정렬변경
		if		($sc['orderby']){
			$orderBy	= "order by " . $sc['orderby'];
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		$sqlQuery['select']		= $selectSql;
		$sqlQuery['fromSql']	= $fromSql;
		$sqlQuery['whereSql']	= $whereSql;
		$sqlQuery['groupBy']	= $groupBy;
		$sqlQuery['orderBy']	= $orderBy;
		$sqlQuery['addBind']	= $addBind;
		$sqlQuery['countSql']	= "wh_move != '1'";

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= pagingScmNumbering($sqlQuery,$sc);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 창고 분류 추출
	public function get_warehouse_group(){
		$sql	= "select wh_group from fm_scm_warehouse where length(wh_group) > 0 and wh_move < 1 group by wh_group";
		$query	= $this->db->query($sql);
		$group	= $query->result_array();
		if	($group) foreach($group as $k => $data){
			$result[$k]		= $data['wh_group'];
		}

		return $result;
	}

	// 창고 저장 시 parameter 체크
	public function chk_warehouse_params($params){

		if	(!strip_tags($params['wh_name'])){
			openDialogAlert('창고명은 필수입니다.', 400, 150, 'parent', '');
			exit;
		}
		if	( !($params['location_width'] > 0 && $params['location_length'] > 0 && $params['location_height'] > 0) ){
			openDialogAlert('로케이션을 지정해 주세요.', 400, 150, 'parent', '');
			exit;
		}

		if	($params['wh_seq'] > 0){
			$warehouse['wh_log']			= '[' . date('Y-m-d H:i:s') . '] 창고 정보수정 (' . $_SERVER['REMOTE_ADDR'] . ')';

			$wh		= $this->get_warehouse(array('wh_seq' => $params['wh_seq']));
			$wh		= $wh[0];
			if	($wh['location_width'] > $params['location_width'] ||
				$wh['location_length'] > $params['location_length'] ||
				$wh['location_height'] > $params['location_height']){
				// 현재 location 보다 작아지는 경우 재고가 있는지 체크
				$sql	= "select * from fm_scm_location_link where wh_seq = ? and ea > 0 ";
				$query	= $this->db->query($sql, array($params['wh_seq']));
				$result	= $query->row_array();
				if	($result['wh_seq'] > 0){
					openDialogAlert('현재 창고에 재고가 있습니다<br/>현재 로케이션 보다 협소하게는 변경이 불가능합니다.', 400, 200, 'parent', '');
					exit;
				}
			}
		}else{
			$warehouse['wh_log']			= '[' . date('Y-m-d H:i:s') . '] 창고 신규등록 (' . $_SERVER['REMOTE_ADDR'] . ')';
			$warehouse['wh_regist_date']	= date('Y-m-d H:i:s');
		}

		$warehouse['wh_name']				= strip_tags($params['wh_name']);
		$warehouse['wh_group']				= strip_tags($params['wh_group']);
		$warehouse['wh_address_type']		= strip_tags($params['Address_type']);
		$warehouse['wh_zipcode']			= implode('', $params['Zipcode']);
		$warehouse['wh_address']			= strip_tags($params['Address']);
		$warehouse['wh_address_street']		= strip_tags($params['Address_street']);
		$warehouse['wh_address_detail']		= strip_tags($params['address_detail']);
		$warehouse['wh_admin_memo']			= strip_tags($params['wh_admin_memo']);
		$warehouse['wh_modify_date']		= date('Y-m-d H:i:s');

		if	($params['location_width'] > 0 && $params['location_length'] > 0 && $params['location_height'] > 0){



			$warehouse['location_width']		= $params['location_width'];
			$warehouse['location_length']		= $params['location_length'];
			$warehouse['location_height']		= $params['location_height'];
			$warehouse['location_width_type']	= $params['location_width_type'];
			$warehouse['location_length_type']	= $params['location_length_type'];
			$warehouse['location_height_type']	= $params['location_height_type'];

			$z	= 0;
			for ( $w = 1; $w <= $params['location_width']; $w++){
				for ( $l = 1; $l <= $params['location_length']; $l++){
					for ( $h = 1; $h <= $params['location_height']; $h++){

						if		($params['location_width_type'] == 'A')		$ws	= strtoupper($this->getAlpharToNum($w));
						elseif	($params['location_width_type'] == 'a')		$ws	= $this->getAlpharToNum($w);
						else												$ws	= $w;

						if		($params['location_length_type'] == 'A')	$ls	= strtoupper($this->getAlpharToNum($l));
						elseif	($params['location_length_type'] == 'a')	$ls	= $this->getAlpharToNum($l);
						else												$ls	= $l;

						if		($params['location_height_type'] == 'A')	$hs	= strtoupper($this->getAlpharToNum($h));
						elseif	($params['location_height_type'] == 'a')	$hs	= $this->getAlpharToNum($h);
						else												$hs	= $h;

						$location[$z]['location_position']	= $w . '-' . $l . '-' . $h;
						$location[$z]['location_x']			= $w;
						$location[$z]['location_y']			= $l;
						$location[$z]['location_z']			= $h;
						$location[$z]['location_code']		= $ws . '-' . $ls . '-' . $hs;
						$location[$z]['location_w']			= $ws;
						$location[$z]['location_l']			= $ls;
						$location[$z]['location_h']			= $hs;

						$z++;
					}
				}
			}
		}

		// 담당자 정보
		if	($params['manager']){
			foreach($params['manager'] as $m => $mng){
				$manager[$m]['manager_seq']			= strip_tags($mng['seq']);
				$manager[$m]['manager_name']		= strip_tags($mng['name']);
				$manager[$m]['manager_partname']	= strip_tags($mng['partname']);
				$manager[$m]['manager_charge']		= strip_tags($mng['charge']);
				$manager[$m]['phone_number']		= strip_tags($mng['phone_number']);
				$manager[$m]['extension_number']	= strip_tags($mng['extension_number']);
				$manager[$m]['cellphone_number']	= strip_tags($mng['cellphone_number']);
				$manager[$m]['email']				= strip_tags($mng['email']);
			}
		}

		return array('warehouse' => $warehouse, 'location' => $location, 'manager' => $manager);
	}

	// 창고 정보 등록/수정
	public function save_warehouse($params, $wh_seq = 0){
		if	($this->chkScmConfig()){
			if	($wh_seq > 0){
				// log 데이터 기존 데이터와 합치기
				$warehouse			= $this->get_warehouse(array('wh_seq'=>$wh_seq));
				$params['wh_log']	= $warehouse[0]['wh_log'] . '<div>' . addslashes($params['wh_log']) . '</div>' . "\n";

				$this->db->where(array('wh_seq' => $wh_seq));
				$this->db->update('fm_scm_warehouse', $params);
				$result	= $wh_seq;
			}else{
				$this->db->insert('fm_scm_warehouse', $params);
				$result	= $this->db->insert_id();
			}
		}

		return $result;
	}

	// 창고 삭제 가능여부 체크
	public function chk_remove_warehouse($wh_seq){
		$bind[]				= $wh_seq;
		$result['status']	= true;

		// 창고 상세 정보 추출
		$sql	= "select * from fm_scm_warehouse where wh_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$wh		= $query->row_array();
		$result['wh_name']		= $wh['wh_name'];
		$result['msg']			= $wh['wh_name'] . '의 ';

		// 쇼핑몰에서 사용중인 창고여부 체크
		$sql	= "select * from fm_scm_store_warehouse where wh_seq = ? group by admin_env_seq";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '사용창고 쇼핑몰이 있습니다.';
			return $result;
		}

		// 재고 존재여부 체크
		$sql	= "select * from fm_scm_location_link where ea > 0 and wh_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '재고가 남아 있습니다.';
			return $result;
		}

		// 발주 완료 존재여부 체크
		$sql	= "select * from fm_scm_order where sorder_status > 0 and in_wh_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '완료된 발주가 ' . count($data) . '건 있습니다.';
			return $result;
		}

		// 입고 완료 존재여부 체크
		$sql	= "select * from fm_scm_warehousing where whs_status > 0 and wh_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '완료된 입고가 ' . count($data) . '건 있습니다.';
			return $result;
		}

		// 출고 존재여부 체크
		$sql	= "select * from fm_goods_export where wh_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '출고가 ' . count($data) . '건 있습니다.';
			return $result;
		}

		// 반출 완료 존재여부 체크
		$sql	= "select * from fm_scm_carryingout where cro_status > 0 and wh_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '완료된 반출이 ' . count($data) . '건 있습니다.';
			return $result;
		}

		// 재고조정 완료 존재여부 체크
		$sql	= "select * from fm_scm_stock_revision where revision_status > 0 and wh_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '완료된 재고조정이 ' . count($data) . '건 있습니다.';
			return $result;
		}

		// 재고이동 완료 존재여부 체크
		$sql	= "select * from fm_scm_stock_move where move_status > 0  and (in_wh_seq = ? or out_wh_seq = ? ) ";
		$query	= $this->db->query($sql,array($wh_seq,$wh_seq));
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '완료된 재고이동이 ' . count($data) . '건 있습니다.';
			return $result;
		}

		// 수불부 존재여부 체크
		$sql	= "select * from fm_scm_ledger_detail where wh_seq = ? ";
		$query	= $this->db->query($sql, $bind);
		$data	= $query->result_array();
		if	(count($data) > 0){
			$result['status']	= false;
			$result['msg']		.= '입출고내역이 ' . count($data) . '건 있습니다.';
			return $result;
		}

		return $result;
	}

	// 창고 정보 삭제
	public function remove_warehouse($wh_seq){
		$bind[]				= $wh_seq;

		// 재고조정 대기 삭제
		$sql	= "delete from fm_scm_stock_revision where revision_status != '1' and wh_seq = ? ";
		$this->db->query($sql, $bind);

		// 재고이동 대기 삭제
		$sql	= "delete from fm_scm_stock_move where move_status != '1' and (in_wh_seq = ? or out_wh_seq = ? )";
		$this->db->query($sql, array($wh_seq,$wh_seq));

		// 입고 대기 삭제
		$sql	= "delete from fm_scm_warehousing where whs_status != '1' and wh_seq = ? ";
		$this->db->query($sql, $bind);

		// 반출 대기 삭제
		$sql	= "delete from fm_scm_carryingout where cro_status != '1' and wh_seq = ? ";
		$this->db->query($sql, $bind);

		// 로케이션 정보 삭제
		$sql	= "delete from fm_scm_location where wh_seq = ? ";
		$this->db->query($sql, $bind);

		// 재고 없는 로케이션 재고 정보 삭제
		$sql	= "delete from fm_scm_location_link where (ea = 0 or ea is null ) and wh_seq = ? ";
		$this->db->query($sql, $bind);

		// 담당자 정보 삭제
		$sql	= "delete from fm_scm_manager where parent_table = 'warehouse' and parent_seq = ? ";
		$this->db->query($sql, $bind);

		// 창고 정보 삭제
		$sql	= "delete from fm_scm_warehouse where wh_seq = ? ";
		$this->db->query($sql, $bind);
	}

	// 해당 창고 상품별 재고 추출
	public function get_warehouse_stock($wh_seq, $getType = '', $returnType = '', $optioninfo = array()){
		if	($this->chkScmConfig()){
			$sc['wh_seq']				= $wh_seq;
			$whinfo						= $this->get_warehouse($sc);
			$whinfo						= $whinfo[0];
			if	(is_array($optioninfo) && count($optioninfo) > 0)
				$sc['concat_option']	= $optioninfo;
			$locationGoods				= $this->get_location_goods($sc);
			if	($locationGoods) foreach($locationGoods as $k => $data){
				if	( !( is_array($sc['concat_option']) && count($sc['concat_option']) > 0 ) ){
					$optioninfo[]		= $data['goods_seq'].$data['option_type'].$data['option_seq'];
				}
				$info[$data['goods_seq'].$data['option_type'].$data['option_seq']]	= $data;
			}

			foreach($optioninfo as $k => $optStr){
				$data = $info[$optStr];
				if	($info[$optStr]){
					$resultParams['goods_seq']			= $data['goods_seq'];
					$resultParams['auto_warehousing']	= $data['scm_auto_warehousing'];
					$resultParams['option_type']		= $data['option_type'];
					$resultParams['option_seq']			= $data['option_seq'];
					$resultParams['optioninfo']			= $optStr;
					$resultParams['wh_seq']				= $data['wh_seq'];
					$resultParams['wh_name']			= $data['wh_name'];
					$resultParams['location_code']		= $data['location_code'];
					$resultParams['location_position']	= $data['location_position'];
					$resultParams['ea']					= $data['ea'];
					$resultParams['badea']				= $data['bad_ea'];
					$resultParams['supply_price']		= $data['supply_price'];
				}else{
					unset($infoArr);
					if	(preg_match('/suboption/', $optStr)){
						$infoArr		= explode('suboption', $optStr);
						$goods_seq		= $infoArr[0];
						$option_type	= 'suboption';
						$option_seq		= $infoArr[1];
					}else{
						$infoArr		= explode('option', $optStr);
						$goods_seq		= $infoArr[0];
						$option_type	= 'option';
						$option_seq		= $infoArr[1];
					}

					$auto_warehousing		= 0;
					if ($goods_seq > 0){
						// 상품 기본 정보 추출
						$sql				= "select * from fm_goods where goods_seq = ? limit 1";
						$query				= $this->db->query($sql, array($goods_seq));
						$goodsData			= $query->row_array();
						$auto_warehousing	= $goodsData['scm_auto_warehousing'];
					}

					$resultParams['goods_seq']			= $goods_seq;
					$resultParams['auto_warehousing']	= $auto_warehousing;
					$resultParams['option_type']		= $option_type;
					$resultParams['option_seq']			= $option_seq;
					$resultParams['optioninfo']			= $optStr;
					$resultParams['wh_seq']				= $whinfo['wh_seq'];
					$resultParams['wh_name']			= $whinfo['wh_name'];
					$resultParams['location_code']		= '';
					$resultParams['location_position']	= '';
					$resultParams['ea']					= '0';
					$resultParams['badea']				= '0';
					$resultParams['supply_price']		= '0';
				}

				if	($getType == 'optioninfo'){
					$result[$optStr]	= $resultParams;
				}else{
					$result[]			= $resultParams;
				}
			}
		}else{
			$this->load->helper('readurl');

			$url					= $this->scm_cfg['scm_location'];
			$params['whSeq']		= $wh_seq;
			$params['getType']		= $getType;
			$params['optioninfo']	= base64_encode(serialize($optioninfo));
			$result					= readurl(get_connet_protocol(). $url . '/scm/response_getstock', $params);
			if	($result)	$result	= json_decode($result, true);
		}

		if	($returnType == 'json')	echo json_encode($result);
		else						return $result;
	}

	########## ↑↑ 창고 ↑↑ ##### ↓↓ 로케이션 ↓↓ ##########

	// 로케이션 정보 등록/수정
	public function save_location($params, $wh_seq){
		if	($this->chkScmConfig()){
			if	(is_array($params) && count($params) > 0 && $wh_seq > 0){
				$this->db->delete('fm_scm_location', array('wh_seq' => $wh_seq));
				$cnt	= count($params);
				for ( $l = 0; $l < $cnt; $l++){
					unset($location);
					$location			= $params[$l];
					$location['wh_seq']	= $wh_seq;
					$this->db->insert('fm_scm_location', $location);

					// 상품 연결 정보들의 코드값 반영
					unset($whParams, $upParams);
					$whParams['wh_seq']				= $wh_seq;
					$whParams['location_position']	= $params[$l]['location_position'];
					$upParams['location_code']		= $params[$l]['location_code'];
					$this->db->where($whParams);
					$this->db->update('fm_scm_location_link', $upParams);
				}

				// 잔여 로케이션 제거
				$sql	= "delete from fm_scm_location_link where wh_seq = ? "
						. "and ( ea = 0 or ea is null or ea = '' ) "
						. "and location_position not in ( "
						. "select location_position from fm_scm_location where wh_seq = ? ) ";
				$this->db->query($sql, array($wh_seq, $wh_seq));
			}
		}
	}

	// 로케이션 정보 추출
	public function get_location($sc){

		$selectSql	= "select * ";
		$fromSql	= "from fm_scm_location ";
		$whereSql	= "where wh_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by location_x, location_y, location_z ";

		// 창고 고유번호 검색
		if		($sc['wh_seq']){
			$whereSql	.= " and wh_seq = ? ";
			$addBind[]	= $sc['wh_seq'];
		}
		// 전체좌표 검색
		if		($sc['location_position']){
			$whereSql	.= " and location_position like '" . addslashes(trim($sc['location_position'])) . "%' ";
		}
		// x좌표 검색
		if		($sc['location_x']){
			$whereSql	.= " and location_x = ? ";
			$addBind[]	= $sc['location_x'];
		}
		// y좌표 검색
		if		($sc['location_y']){
			$whereSql	.= " and location_y = ? ";
			$addBind[]	= $sc['location_y'];
		}
		// z좌표 검색
		if		($sc['location_z']){
			$whereSql	.= " and location_z = ? ";
			$addBind[]	= $sc['location_z'];
		}
		// 전체코드 검색
		if		($sc['location_code']){
			$whereSql	.= " and location_code like '%" . addslashes(trim($sc['location_code'])) . "%' ";
		}
		// 가로코드 검색
		if		($sc['location_w']){
			$whereSql	.= " and location_w = ? ";
			$addBind[]	= $sc['location_w'];
		}
		// 세로코드 검색
		if		($sc['location_l']){
			$whereSql	.= " and location_l = ? ";
			$addBind[]	= $sc['location_l'];
		}
		// 높이코드 검색
		if		($sc['location_h']){
			$whereSql	.= " and location_h = ? ";
			$addBind[]	= $sc['location_h'];
		}
		// 전체 좌표, 코드 검색
		if		($sc['location_keyword']){
			$sc['location_keyword']	= addslashes(trim($sc['location_keyword']));
			$whereSql				.= " and ( location_position like '" . $sc['location_keyword'] . "%' "
									. " or location_code like '" . $sc['location_keyword'] . "%' ) ";
		}
		// 추출 개수 제한
		if		($sc['limit']){
			$addLimit	= " limit " . $sc['limit'] . " ";
		}


		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . $addLimit;
		$query		= $this->db->query($sql, $addBind);
		$result		= $query->result_array();
		if	($result) foreach ($result as $p => $loc){
			if		($sc['grid_type'])
				$location[$loc['location_y']][$loc['location_x']][$loc['location_z']]	= $loc;
			elseif	($sc['list_type'])
				$location[]	= $loc;
			else
				$location[$loc['location_x']][$loc['location_y']][$loc['location_z']]	= $loc;
		}

		return $location;
	}

	// 상품(옵션)별 또는 창고별 로케이션 재고 및 평균 매입가 추출
	public function get_location_stock($sc){
		$selectSql	= "select goods_seq, option_type, option_seq, "
					. "count(*) cnt, SUM(IFNULL(ea, 0)) as ea, SUM(IFNULL(bad_ea, 0)) as bad_ea, "
					. "SUM(IFNULL(ea, 0) * IFNULL(supply_price, 0))	as total_supply_price, "
					. "ROUND(SUM(IFNULL(ea, 0) * IFNULL(supply_price, 0)) / SUM(IFNULL(ea, 0))) as supply_price, "
					. "SUM(IFNULL(supply_price, 0)) as tot_supply_price ";
		$fromSql	= "from fm_scm_location_link	as fsll ";
		$whereSql	= "where wh_seq > 0 and ea >= 0 ";
		$groupBy	= "group by goods_seq, option_type, option_seq ";
		$orderBy	= "order by goods_seq, option_type, option_seq ";

		// 창고별
		if	($sc['get_type'] == 'wh'){
			$selectSql	= "select wh_seq, location_position, location_code, "
						. "count(*) cnt, SUM(IFNULL(ea, 0)) as ea, SUM(IFNULL(bad_ea, 0)) as bad_ea, "
						. "ROUND(SUM(IFNULL(ea, 0) * IFNULL(supply_price, 0)) / SUM(IFNULL(ea, 0))) as supply_price, "
						. "SUM(IFNULL(ea, 0) * IFNULL(supply_price, 0))	as total_supply_price, "
						. "SUM(IFNULL(supply_price, 0)) as tot_supply_price ";
			$groupBy	= "group by wh_seq ";
			$orderBy	= "order by wh_seq desc ";
		}

		// 창고 검색
		if		($sc['wh_seq'] > 0){
			$whereSql	.= " and fsll.wh_seq = ? ";
			$addBind[]	= $sc['wh_seq'];
		}
		// 상품 검색
		if		($sc['goods_seq'] > 0){
			$whereSql	.= " and fsll.goods_seq = ? ";
			$addBind[]	= $sc['goods_seq'];
		}
		// 상품쪽 검색
		if		($sc['sc_goods_name'] && $sc['sc_goods_kind']){
			$fromSql	.= "inner join fm_goods as g on fsll.goods_seq = g.goods_seq ";
			// 상품명 검색
			if	($sc['sc_goods_name']){
				$whereSql	.= " and g.goods_name like '%" . $sc['sc_goods_name'] . "%' ";
			}
			// 상품 구분 검색
			if	($sc['sc_goods_kind']){
				$whereSql	.= " and g.goods_kind like '%" . $sc['sc_goods_kind'] . "%' ";
			}
		}
		// 옵션 종류 검색
		if		(!$this->scm_use_suboption_mode){
			$whereSql	.= " and fsll.option_type = 'option' ";
		}elseif	($sc['option_type']){
			$whereSql	.= " and fsll.option_type = ? ";
			$addBind[]	= $sc['option_type'];
		}
		// 옵션 번호 검색
		if		($sc['option_seq'] > 0){
			$whereSql	.= " and fsll.option_seq = ? ";
			$addBind[]	= $sc['option_seq'];
		}
		// 창고 일괄 검색
		if		($sc['src_wh_seq']){
			if	(!is_array($sc['src_wh_seq']))	$sc['src_wh_seq']		= array($sc['src_wh_seq']);
			$whereSql	.= " and fsll.wh_seq in ('" . implode("', '", $sc['src_wh_seq']) . "') ";
		}
		// 상품 일괄 검색
		if		($sc['concat_goods']){
			if	(!is_array($sc['concat_goods']))	$sc['concat_goods']		= array($sc['concat_goods']);
			$whereSql	.= " and concat(fsll.goods_seq, fsll.option_type, fsll.option_seq) in ('" . implode("', '", $sc['concat_goods']) . "') ";
		}
		// 상품 지정형인 경우 location_code와 location_position을 추출한다.
		if		($sc['get_type'] != 'wh' && $sc['wh_seq'] > 0 && ($sc['concat_goods'] || ($sc['goods_seq'] && $sc['option_seq']))){
			$selectSql	.= ", fsll.location_position, fsll.location_code ";
		}

		$sql	= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 로케이션에 연결된 상품 검색
	public function get_location_goods($sc){
		$selectSql	= "select fsw.*, fsl.*, fg.*, fsll.location_position, fsll.location_code,
						fsll.option_type, fsll.option_seq,
						IFNULL(fsll.ea, 0) as ea, IFNULL(fsll.bad_ea, 0) as bad_ea,
						IFNULL(fsll.supply_price, 0) as supply_price ";
		$fromSql	= "from fm_scm_warehouse				as fsw
							left join fm_scm_location		as fsl
								on fsw.wh_seq = fsl.wh_seq
							left join fm_scm_location_link	as fsll
								on ( fsl.wh_seq = fsll.wh_seq and ( fsw.wh_move = '1'
									or fsl.location_position = fsll.location_position ) )
							left join fm_goods				as fg
								on ( fsll.goods_seq = fg.goods_seq and fg.provider_seq = 1 and fg.package_yn = 'n' ) ";
		$whereSql	= "where fsw.wh_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by fsw.wh_seq, fsl.location_x, fsl.location_y, fsl.location_z ";

		// 창고 고유번호 검색
		if		($sc['wh_seq']){
			$whereSql	.= " and fsll.wh_seq = ? ";
			$addBind[]	= $sc['wh_seq'];
		}
		// 이동창고 포함 검색
		if		($sc['inclusion_move'] != 'Y'){
			$whereSql		.= " and fsw.wh_move < 1 ";
		}
		if		($sc['chk_linclusion_seq']){
			$whereSql	.= " and fsll.location_position = ? ";
			$addBind[]	= $sc['chk_linclusion_seq'];
		}
		// 이동창고 정보 추출
		if		($sc['sc_wh_move']){
			$whereSql		.= " and fsw.wh_move = 1 ";
		}
		// 상품 고유번호 검색
		if		($sc['goods_seq']){
			$whereSql	.= " and fsll.goods_seq = ? ";
			$addBind[]	= $sc['goods_seq'];
		}
		// 옵션 종류 검색
		if		(!$this->scm_use_suboption_mode){
			$whereSql	.= " and fsll.option_type = 'option' ";
		}elseif	($sc['option_type']){
			$whereSql	.= " and fsll.option_type = ? ";
			$addBind[]	= $sc['option_type'];
		}
		// 옵션 고유번호 검색
		if		($sc['option_seq']){
			$whereSql	.= " and fsll.option_seq = ? ";
			$addBind[]	= $sc['option_seq'];
		}
		// 옵션 일괄 검색
		if		($sc['concat_option']){
			if	(!is_array($sc['concat_option']))	$sc['concat_option']	= array($sc['concat_option']);
			$whereSql	.= " and concat(fsll.goods_seq, fsll.option_type, fsll.option_seq) in ('" . implode("', '", $sc['concat_option']) . "') ";
		}
		// 창고명 검색
		if		(trim($sc['wh_name'])){
			$whereSql	.= " and fsw.wh_name like '%" . addslashes(trim($sc['wh_name'])) . "%' ";
		}
		// 전체좌표 검색
		if		(trim($sc['location_position'])){
			$whereSql	.= " and fsll.location_position like '" . addslashes(trim($sc['location_position'])) . "%' ";
		}
		// x좌표 검색
		if		($sc['location_x']){
			$whereSql	.= " and fsl.location_x = ? ";
			$addBind[]	= $sc['location_x'];
		}
		// y좌표 검색
		if		($sc['location_y']){
			$whereSql	.= " and fsl.location_y = ? ";
			$addBind[]	= $sc['location_y'];
		}
		// z좌표 검색
		if		($sc['location_z']){
			$whereSql	.= " and fsl.location_z = ? ";
			$addBind[]	= $sc['location_z'];
		}
		// 전체코드 검색
		if		(trim($sc['location_code'])){
			$whereSql	.= " and fsl.location_code like '%" . addslashes(trim($sc['location_code'])) . "%' ";
		}
		// 가로코드 검색
		if		($sc['location_w']){
			$whereSql	.= " and fsl.location_w = ? ";
			$addBind[]	= $sc['location_w'];
		}
		// 세로코드 검색
		if		($sc['location_l']){
			$whereSql	.= " and fsl.location_l = ? ";
			$addBind[]	= $sc['location_l'];
		}
		// 높이코드 검색
		if		($sc['location_h']){
			$whereSql	.= " and fsl.location_h = ? ";
			$addBind[]	= $sc['location_h'];
		}
		// 코드+좌표에 대한 키워드 검색
		if		($sc['location_keyword']){
			$sc['location_position']	= addslashes(trim($sc['location_position']));
			$whereSql					.= " and ( fsll.location_position like '" . $sc['location_position'] . "%' "
										. "	or fsl.location_code like '%" . $sc['location_code'] . "%' ) ";
		}
		// 상품명 검색
		if		(trim($sc['goods_name'])){
			$whereSql	.= " and fg.goods_name like '%" . addslashes(trim($sc['goods_name'])) . "%' ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}
		return $result;
	}

	// 로케이션에 연결된 상품 옵션 검색 ( 상품 기준 )
	public function get_location_goods_for_option($sc, $option_type = '', $list_type = 'warehouse'){
		// 추가옵션 미사용 모드 처리
		if	(!$this->scm_use_suboption_mode && $option_type == 'suboption'){
			return array();
		}

		// 상품번호 검색
		if	($sc['goods_seq'] > 0){
			$option_addWhere	.= " and fg.goods_seq = '" . $sc['goods_seq'] . "' ";
			$location_addWhere	.= " and fsll.goods_seq = '" . $sc['goods_seq'] . "' ";
		}else if ($sc['sc_goods_seq'] > 0){
			$option_addWhere	.= " and fg.goods_seq = '" . $sc['sc_goods_seq'] . "' ";
			$location_addWhere	.= " and fsll.goods_seq = '" . $sc['sc_goods_seq'] . "' ";
		}

		// 상품코드 검색
		if($sc['sc_goods_code'] > 0){
			$option_addWhere	.= " and fg.goods_code = '" . $sc['sc_goods_code'] . "' ";
			$location_addWhere	.= " and fsll.goods_code = '" . $sc['sc_goods_code'] . "' ";
		}
		// 상품명 검색
		if($sc['sc_goods_name'] > 0){
			$option_addWhere	.= " and fg.goods_name like '%" . $sc['sc_goods_name'] . "%' ";
			$location_addWhere	.= " and fsll.goods_name = '%" . $sc['sc_goods_name'] . "%' ";
		}

		// 키워드 검색
		if($sc['keyword']){
			$option_addWhere	.= " and (
									fg.goods_seq = '" . $sc['keyword'] . "' or 
									fg.goods_name like '%" . $sc['keyword'] . "%' or 
									concat(IFNULL(fg.goods_code,''), IFNULL(fgo.optioncode1,''), IFNULL(fgo.optioncode2,''), IFNULL(fgo.optioncode3, ''), IFNULL(fgo.optioncode4,''), IFNULL(fgo.optioncode5,''))	 like '%" . $sc['keyword'] . "%' ) ";
		}

		// 상품번호 일괄 검색
		if	($sc['goods_seq_arr']){
			if	(!is_array($sc['goods_seq_arr']))	$sc['goods_seq_arr']	= array($sc['goods_seq_arr']);
			$option_addWhere	.= " and fg.goods_seq in ('" . implode("', '", $sc['goods_seq_arr']) . "') ";
			$location_addWhere	.= " and fsll.goods_seq in ('" . implode("', '", $sc['goods_seq_arr']) . "') ";
		}
		// 옵션 정보 일괄 검색
		if	($sc['option_info_arr']){
			if	(!is_array($sc['option_info_arr']))	$sc['option_info_arr']	= array($sc['option_info_arr']);
			foreach($sc['option_info_arr'] as $k => $optionstr){
				if	(preg_match('/suboption/', $optionstr)){
					$tmp				= explode('suboption', $optionstr);
					$goodsSeq[]			= $tmp[0];
					$suboptionSeq[]		= $tmp[1];
				}else{
					$tmp				= explode('option', $optionstr);
					$gooddSeq[]			= $tmp[0];
					$optionSeq[]		= $tmp[1];
				}
			}
			// 상품번호 검색
			if		(count($gooddSeq) > 0){
				$option_addWhere	.= " and fg.goods_seq in ('" . implode("', '", $gooddSeq) . "') ";
			}
			// 필수옵션 검색
			if		(count($optionSeq) > 0){
				$optAddWhere	.= " and fgo.option_seq in ('" . implode("', '", $optionSeq) . "') ";
			}
			// 추가옵션 검색
			if		(count($suboptionSeq) > 0 && $this->scm_use_suboption_mode){
				$subAddWhere	.= " and fgs.option_seq in ('" . implode("', '", $optionSeq) . "') ";
			}
		}

		// 창고번호 검색
		if	($sc['wh_seq'] > 0){
			$location_addWhere	.= " and fsll.wh_seq = '" . $sc['wh_seq'] . "' ";
		}
		// 옵션번호 검색
		if	($option_type && $sc['option_seq'] > 0){
			if		($option_type == 'suboption'){
				$option_addWhere	.= " and fgs.suboption_seq = '" . $sc['option_seq'] . "' ";
				$location_addWhere	.= " and fsll.option_type = 'suboption' ";
				$location_addWhere	.= " and fsll.option_seq = '" . $sc['option_seq'] . "' ";
			}elseif	($option_type == 'option'){
				$option_addWhere	.= " and fgo.option_seq = '" . $sc['option_seq'] . "' ";
				$location_addWhere	.= " and fsll.option_type = 'option' ";
				$location_addWhere	.= " and fsll.option_seq = '" . $sc['option_seq'] . "' ";
			}
		}
		// 이동창고 포함 검색
		if		($sc['inclusion_move'] != 'Y'){
			$location_addWhere		.= " and fsw.wh_move < 1 ";
		}

		if($list_type != 'request_total'){
			$option_addWhere	.= " and fg.package_yn = 'n'";
		}

		$option_sql			= "select
									'option'				as option_type,
									fg.goods_seq			as goods_seq,
									fg.goods_name			as goods_name,
									fg.option_use			as option_use,
									fg.goods_code			as goods_code,
									concat(IFNULL(fg.goods_code, ''), IFNULL(fgo.optioncode1, ''), IFNULL(fgo.optioncode2, ''), IFNULL(fgo.optioncode3, ''), IFNULL(fgo.optioncode4, ''), IFNULL(fgo.optioncode5, ''))	as goods_option_code,
									concat(IFNULL(fgo.optioncode1, ''), IFNULL(fgo.optioncode2, ''), IFNULL(fgo.optioncode3, ''), IFNULL(fgo.optioncode4, ''), IFNULL(fgo.optioncode5, ''))	as option_code,
									fg.package_yn,
									fg.scm_category,
									fgo.option_seq			as option_seq,
									fgo.option_title		as option_title,
									fgo.option1				as option1,
									fgo.option2				as option2,
									fgo.option3				as option3,
									fgo.option4				as option4,
									fgo.option5				as option5,
									fgo.optioncode1			as optioncode1,
									fgo.optioncode2			as optioncode2,
									fgo.optioncode3			as optioncode3,
									fgo.optioncode4			as optioncode4,
									fgo.optioncode5			as optioncode5,
									fgo.consumer_price		as consumer_price,
									fgo.price				as price,
									fgp.supply_price		as supply_price,
									fgo.reserve				as reserve,
									fgp.stock				as stock,
									fgp.badstock			as badstock,
									fgp.total_supply_price	as total_supply_price,
									fgp.total_stock			as total_stock,
									fgp.total_badstock		as total_badstock,
									fgp.safe_stock			as safe_stock,
									fgp.reservation15		as reservation15,
									fgp.reservation25		as reservation25,
									fgo.weight				as weight,
									fgo.option_view			as option_view
								from fm_goods					as fg
									INNER JOIN fm_goods_option	as fgo
										ON fg.goods_seq = fgo.goods_seq
									INNER JOIN fm_goods_supply	as fgp
										ON fgo.goods_seq = fgp.goods_seq and fgo.option_seq = fgp.option_seq
								where fg.provider_seq = 1 and fg.goods_seq > 0 " . $option_addWhere . $optAddWhere;
		$suboption_sql		= "	select
									'suboption'				as option_type,
									fg.goods_seq			as goods_seq,
									fg.goods_name			as goods_name,
									fg.option_use			as option_use,
									concat(IFNULL(fg.goods_code, ''), IFNULL(fgs.suboption_code, ''))	as goods_code,
									fg.package_yn,
									fgs.suboption_seq		as option_seq,
									fgs.suboption_title		as option_title,
									fgs.suboption			as option1,
									''						as option2,
									''						as option3,
									''						as option4,
									''						as option5,
									fgs.consumer_price		as consumer_price,
									fgs.price				as price,
									fgp.supply_price		as supply_price,
									fgp.stock				as stock,
									fgp.badstock			as badstock,
									fgp.total_supply_price	as total_supply_price,
									fgp.total_stock			as total_stock,
									fgp.total_badstock		as total_badstock,
									fgp.safe_stock			as safe_stock,
									fgp.reservation15		as reservation15,
									fgp.reservation25		as reservation25,
									fgp.weight				as weight,
									fgp.option_view			as option_view
								from fm_goods						as fg
									INNER JOIN fm_goods_suboption	as fgs
										ON fg.goods_seq = fgs.goods_seq
									INNER JOIN fm_goods_supply		as fgp
										ON fgs.goods_seq = fgp.goods_seq and fgs.suboption_seq = fgp.suboption_seq
								where fg.provider_seq = 1 and fg.goods_seq > 0 " . $option_addWhere . $subAddWhere;

		if	($list_type == 'location'){
			$loction_sql	= "select
									fsw.wh_name,
									fsw.wh_seq,
									fsll.goods_seq,
									fsll.option_type,
									fsll.option_seq,
									fsll.location_position,
									fsll.location_code,
									fsll.ea,
									fsll.bad_ea,
									fsll.supply_price
								from
									fm_scm_warehouse	as fsw,
									fm_scm_location_link	as fsll
								where fsw.wh_seq = fsll.wh_seq " . $location_addWhere;
		}else{
			$loction_sql	= "select
									fsw.wh_name,
									fsw.wh_seq,
									fsll.goods_seq,
									fsll.option_type,
									fsll.option_seq,
									fsll.location_position,
									fsll.location_code,
									SUM(IFNULL(fsll.ea, 0))			as ea,
									SUM(IFNULL(fsll.bad_ea, 0))		as bad_ea,
									fsll.supply_price		as wh_supply_price
								from
									fm_scm_warehouse	as fsw,
									fm_scm_location_link	as fsll
								where fsw.wh_seq = fsll.wh_seq " . $location_addWhere . "
								group by fsll.goods_seq, fsll.option_seq,
									fsll.option_type, fsll.wh_seq";
		}


		$selectSql		= "select "
						. "g.option_type			as option_type, "
						. "g.goods_seq				as goods_seq, "
						. "g.goods_name				as goods_name, "
						. "g.option_use				as option_use, "
						. "g.goods_code				as goods_code, "
						. "g.goods_option_code		as goods_option_code, "
						. "g.package_yn				as package_yn, "
						. "g.scm_category			as scm_category, "
						. "g.option_seq				as option_seq, "
						. "g.option_title			as option_title, "
						. "g.option_code			as option_code, "
						. "g.option1				as option1, "
						. "g.option2				as option2, "
						. "g.option3				as option3, "
						. "g.option4				as option4, "
						. "g.option5				as option5, "
						. "concat(IFNULL(g.option1,''), IFNULL(g.option2,''), IFNULL(g.option3, ''),
							IFNULL(g.option4,''), IFNULL(g.option5,''))	as option_name, "
						. "g.optioncode1			as optioncode1, "
						. "g.optioncode2			as optioncode2, "
						. "g.optioncode3			as optioncode3, "
						. "g.optioncode4			as optioncode4, "
						. "g.optioncode5			as optioncode5, "
						. "g.consumer_price			as consumer_price, "
						. "g.price					as price, "
						. "g.reserve				as reserve, "
						. "g.supply_price			as supply_price, "
						. "g.stock					as stock, "
						. "g.badstock				as badstock, "
						. "g.total_supply_price		as total_supply_price, "
						. "g.total_stock			as total_stock, "
						. "g.total_badstock			as total_badstock, "
						. "g.safe_stock				as safe_stock, "
						. "g.reservation15			as reservation15, "
						. "g.reservation25			as reservation25, "
						. "g.weight					as weight, "
						. "g.option_view			as option_view, "
						. "scm.wh_name				as wh_name, "
						. "scm.wh_seq				as wh_seq, "
						. "scm.location_position	as location_position, "
						. "scm.location_code		as location_code, "
						. "scm.ea					as ea, "
						. "scm.bad_ea				as bad_ea, "
						. "scm.wh_supply_price		as wh_supply_price, "
						. "scm.wh_name				as wh_name ";
		
		if		($option_type == 'suboption' && $this->scm_use_suboption_mode) {
			$fromSql	= "from (" . $suboption_sql . ") as g ";
		}elseif	($option_type == 'option' || !$this->scm_use_suboption_mode){
			$fromSql	= "from (" . $option_sql . ") as g ";
		}elseif	($this->scm_use_suboption_mode){
			$fromSql	= "from ( (" . $option_sql . ") UNION (" . $suboption_sql . ") ) as g ";
		}else{
			return array();
		}

		$fromSql	.= "LEFT JOIN (" . $loction_sql . ") as scm
							ON ( g.goods_seq = scm.goods_seq and
								g.option_type = scm.option_type and
								g.option_seq = scm.option_seq )";
		$groupBy	= "";
		$orderBy	= "order by g.goods_seq, g.option_type, g.option_seq ";
		if	($sc['orderby']){
			
			preg_match('/(asc|desc)$/i', $sc['orderby'],$match);
			$sc['sort'] 				= $match['0'];
			$sc['orderby'] 				= str_replace(array("_desc","_asc"), array("",""), $sc['orderby']);
				
			$orderBy	= "order by " . $sc['orderby'] ." ".$sc['sort'];
		}

		$sql		= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			if($sc['is_excel'] === true){
				$sc['page'] = $sc['page']-1;
				$sql = $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . " limit ". $sc['page']. ", " . $sc['perpage'];
				
				$query	= $this->db->query($sql, $addBind);
				$result = $query->result_array();
				$result['record'] = $result;
			} else {
				$result		= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
			}
			
			if($sc['total'] <= 0){
				$totalQuery = "select COUNT(*) as totalCnt " . $fromSql . $whereSql;
				$query		= $this->db->query($totalQuery, $addBind);
				$totalresult = $query->result_array();
				$result['total'] = $totalresult[0]['totalCnt'];
			}
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 로케이션 연결 수정
	public function save_location_link($params, $whrParam = array()){
		if	($this->chkScmConfig()){

			// POSITION 오류 방지용 (position에 숫자가 아닌 좌표값이 있을 경우 오동작함.)
			if	($params['location_position'] && preg_match('/[^0-9\-]/', $params['location_position'])){
				if	(!preg_match('/[^0-9\-]/', $params['location_code'])){
					$location_position				= $params['location_code'];
					$params['location_code']		= $params['location_position'];
					$params['location_position']	= $location_position;
				}else{
					$params['location_position']	= '1-1-1';
				}
			}

			// 수정
			if	(count($whrParam) > 0){
				if	($whrParam['wh_seq'] > 0 && $whrParam['goods_seq'] > 0 && $whrParam['option_type'] && $whrParam['option_seq'] > 0 ){
					$wh_seq		= $whrParam['wh_seq'];
					$this->db->where($whrParam);
					$this->db->update('fm_scm_location_link', $params);
				}
			// 추가
			}else{
				$this->db->insert('fm_scm_location_link', $params);
				$wh_seq		= $this->db->insert_id();
			}
		}

		return $wh_seq;
	}

	// 로케이션 연결을 제거
	public function delete_location_link($wh_seq, $goods_seq, $option_seq, $option_type = 'option'){
		if	($this->chkScmConfig()){
			if ($wh_seq > 0 && $goods_seq > 0 && $option_seq > 0){
				$option_type = ($option_type == 'suboption') ? 'suboption' : 'option';

				unset($params);
				$params['wh_seq'] = $wh_seq;
				$params['goods_seq'] = $goods_seq;
				$params['option_type'] = $option_type;
				$params['option_seq'] = $option_seq;
				$this->db->delete('fm_scm_location_link', $params);
			}
		}
	}

	// 해당 옵션의 창고별 재고 추출
	public function get_stock_warehouse_list($goods_seq, $option_type, $option_seq){

		// 현재 모드와 다른 option_type은 pass
		if		(!$this->scm_use_suboption_mode && $option_type == 'suboption'){
			return array();
		}

		if	($goods_seq > 0 && in_array($option_type, array('option', 'suboption')) ){
			if	($this->chkScmConfig()){
				$sc['orderby']	= 'wh_seq asc';
				$whlist			= $this->get_warehouse($sc);
				if	($whlist) foreach($whlist as $k => $data){
					$result[$data['wh_seq']]['wh_seq']			= $data['wh_seq'];
					$result[$data['wh_seq']]['wh_name']			= $data['wh_name'];
					$result[$data['wh_seq']]['ea']				= '0';
					$result[$data['wh_seq']]['bad_ea']			= '0';
					$result[$data['wh_seq']]['supply_price']	= '0';
				}

				unset($sc);
				$sc['goods_seq']	= $goods_seq;
				$sc['option_type']	= $option_type;
				if($option_seq != 'all'){
					$sc['option_seq']	= $option_seq;
				}

				if($option_seq == 'all'){
					$result = $this->get_location_goods_for_option($sc);
					return $result;
				}
				$location			= $this->get_location_goods($sc);
				if($option_seq == 'all'){
					return $location;
				}
				if	( $location ) foreach($location as $k => $data){
					$result[$data['wh_seq']]	= $data;
				}
				$result				= array_values($result);
			}else{
				$this->load->helper('readurl');
				$url					= get_connet_protocol() . $this->scm_cfg['scm_location'] . '/scm/request_getstock_all';
				$params['goods_seq']	= $goods_seq;
				$params['option_type']	= $option_type;
				$params['option_seq']	= $option_seq;
				$result					= readurl($url, $params, 10);
				$result					= json_decode($result, true);
			}
		}

		return $result;
	}

	########## ↑↑ 로케이션 ↑↑ ##### ↓↓ 상품관리 ↓↓ ##########

	// 주거래처 기준의 상품 및 옵션 목록 추출
	public function get_goods_default_order_data($sc){
		$optSelectSql	= "select
							g.*,
							'option'				as option_type,
							opt.option_seq			as option_seq,
							opt.default_option		as default_option,
							concat(IFNULL(opt.option1,''), IFNULL(opt.option2,''), IFNULL(opt.option3, ''),
									IFNULL(opt.option4,''), IFNULL(opt.option5,''))	as option_name,
							concat(IFNULL(opt.optioncode1,''), IFNULL(opt.optioncode2,''),
									IFNULL(opt.optioncode3, ''), IFNULL(opt.optioncode4,''),
									IFNULL(opt.optioncode5,''))	as option_code,
							opt.consumer_price		as consumer_price,
							opt.price				as price,
							sup.supply_price		as store_supply_price,
							sup.stock				as stock,
							sup.badstock			as badstock,
							sup.safe_stock			as safe_stock,
							sup.total_supply_price	as total_supply_price,
							sup.total_stock			as total_stock,
							sup.total_badstock		as total_badstock,
							sod.default_seq			as default_seq,
							sod.trader_seq			as trader_seq,
							sod.supply_goods_name	as supply_goods_name,
							sod.supply_price		as supply_price,
							sod.use_supply_tax		as use_supply_tax ";
		$optFromSql		= "from fm_goods				as g
							inner join fm_goods_option	as opt
								on opt.goods_seq = g.goods_seq
							left join fm_scm_order_defaultinfo	as sod
								on ( opt.goods_seq = sod.goods_seq and opt.option_seq = sod.option_seq
									and sod.option_type = 'option' and sod.main_trade_type = 'Y'
									and sod.use_status = 'Y' )
							inner join fm_goods_supply	as sup
								on ( opt.option_seq = sup.option_seq )
							";
		
		$traderInfo = array();

		if($sc['sc_traderGroup'] || $sc['sc_trader']){
			$optSelectSql	.= ", st.trader_name			as trader_name,
							st.currency_unit		as currency_unit ";
			
			$optFromSql		.= " inner join fm_scm_trader		as st
								on sod.trader_seq = st.trader_seq ";
		} else {
			//속도 문제로 trader 정보 분리
			$traderSql	= "select trader_seq, trader_name, currency_unit from fm_scm_trader";
			$trader		= $this->db->query($traderSql, $traderwhere);
			$trader		= $trader->result_array();

			foreach($trader as $v){
				$traderInfo[$v['trader_seq']]['trader_name']	= $v['trader_name'];
				$traderInfo[$v['trader_seq']]['currency_unit']	= $v['currency_unit'];
			}
		}
		
		$optWhereSql	= "where g.goods_seq > 0 and g.provider_seq = 1
							and ( opt.package_count = 0 or opt.package_count is NULL ) ";
		$subSelectSql	= "select
							g.*,
							'suboption'				as option_type,
							sub.suboption_seq		as option_seq,
							'n'						as default_option,
							sub.suboption			as option_name,
							sub.suboption_code		as option_code,
							sub.consumer_price		as consumer_price,
							sub.price				as price,
							sup.supply_price		as store_supply_price,
							sup.stock				as stock,
							sup.badstock			as badstock,
							sup.safe_stock			as safe_stock,
							sup.total_supply_price	as total_supply_price,
							sup.total_stock			as total_stock,
							sup.total_badstock		as total_badstock,
							sod.default_seq			as default_seq,
							sod.trader_seq			as trader_seq,
							sod.supply_goods_name	as supply_goods_name,
							sod.supply_price		as supply_price,
							sod.use_supply_tax		as use_supply_tax,
							st.trader_name			as trader_name,
							st.currency_unit		as currency_unit ";
		$subFromSql		= "from fm_goods					as g
							inner join fm_goods_suboption	as sub
								on ( g.goods_seq = sub.goods_seq )
							left join fm_scm_order_defaultinfo	as sod
								on ( sub.goods_seq = sod.goods_seq and sub.suboption_seq = sod.option_seq
									and sod.option_type = 'suboption' and sod.main_trade_type = 'Y'
									and sod.use_status = 'Y' )
							left join fm_scm_trader		as st
								on sod.trader_seq = st.trader_seq
							inner join fm_goods_supply		as sup
								on (sub.suboption_seq = sup.suboption_seq) ";
		$subWhereSql	= "where g.goods_seq > 0 and g.provider_seq = 1
							and g.goods_type in ( 'goods', 'gift' )
							and ( sub.package_count = 0 or sub.package_count is NULL ) ";

		// 실물/티켓 검색
		if	($sc['sc_goods_kind']){
			if	(in_array('gift', $sc['sc_goods_kind'])){
				$optWhereSql	.= " and g.goods_type = '{$sc['sc_goods_kind']}' ";
				$subWhereSql	.= " and g.goods_type = '{$sc['sc_goods_kind']}' ";
			} else {
				$optWhereSql	.= " and g.goods_kind = '{$sc['sc_goods_kind']}' ";
				$subWhereSql	.= " and g.goods_kind = '{$sc['sc_goods_kind']}' ";
			}
		}

		// 공급처 배송
		if	($sc['sc_auto_wh'] =='y'){
			$optWhereSql	.= " and g.scm_auto_warehousing = '1' ";
			$subWhereSql	.= " and g.scm_auto_warehousing = '1' ";
		}

		// 상품번호 조건
		if		($sc['goods_seq']){
			$optWhereSql	.= " and g.goods_seq = '" . $sc['goods_seq'] . "' ";
			$subWhereSql	.= " and g.goods_seq = '" . $sc['goods_seq'] . "' ";
		}else if($sc['sc_goods_seq']){
			$optWhereSql	.= " and g.goods_seq = '" . $sc['sc_goods_seq'] . "' ";
			$subWhereSql	.= " and g.goods_seq = '" . $sc['sc_goods_seq'] . "' ";
		}
		// 상품명 조건
		if		($sc['goods_name']){
			$optWhereSql	.= " and g.goods_name like '%" . $sc['goods_name'] . "%' ";
			$subWhereSql	.= " and g.goods_name like '%" . $sc['goods_name'] . "%' ";
		}else if		($sc['sc_goods_name']){
			$optWhereSql	.= " and g.goods_name like '%" . $sc['sc_goods_name'] . "%' ";
			$subWhereSql	.= " and g.goods_name like '%" . $sc['sc_goods_name'] . "%' ";
		}
		// 상품코드 조건
		if		($sc['goods_code']){
			$optWhereSql	.= " and concat(IFNULL(g.goods_code,''), IFNULL(opt.optioncode1,''),
									IFNULL(opt.optioncode2,''), IFNULL(opt.optioncode3, ''), IFNULL(opt.optioncode4,''), IFNULL(opt.optioncode5,''))	 like '%" . $sc['goods_code'] . "%' ";
			$subWhereSql	.= " and concat(IFNULL(g.goods_code,''), IFNULL(sub.suboption_code,''))
									like '%" . $sc['goods_code'] . "%' ";
		}else if ($sc['sc_goods_code']){
			$optWhereSql	.= " and concat(IFNULL(g.goods_code,''), IFNULL(opt.optioncode1,''),
									IFNULL(opt.optioncode2,''), IFNULL(opt.optioncode3, ''), IFNULL(opt.optioncode4,''), IFNULL(opt.optioncode5,''))	 like '%" . $sc['sc_goods_code'] . "%' ";
			$subWhereSql	.= " and concat(IFNULL(g.goods_code,''), IFNULL(sub.suboption_code,''))
									like '%" . $sc['sc_goods_code'] . "%' ";
		}

		if		($sc['keyword']){
			$optWhereSql	.= " and (
									g.goods_seq = '" . $sc['keyword'] . "' or
									g.goods_name like '%" . $sc['keyword'] . "%' or
									concat(IFNULL(g.goods_code,''), IFNULL(opt.optioncode1,''), IFNULL(opt.optioncode2,''), IFNULL(opt.optioncode3, ''), IFNULL(opt.optioncode4,''), IFNULL(opt.optioncode5,''))	 like '%" . $sc['keyword'] . "%' ) ";
			$subWhereSql	.= " and (
									g.goods_seq = '" . $sc['keyword'] . "' or
									g.goods_name like '%" . $sc['keyword'] . "%' or
									concat(IFNULL(g.goods_code,''), IFNULL(sub.suboption_code,''))
									like '%" . $sc['keyword'] . "%' ) ";
		}

		// 상품구분 검색
		if	($sc['goods_kind']){
			if	(!is_array($sc['goods_kind']))	$sc['goods_kind']	= array($sc['goods_kind']);
			if	(in_array('gift', $sc['goods_kind'])){
				$optWhereSql	.= " and ( g.goods_kind in ('" . implode("', '", $sc['goods_kind']) . "') "
								. " or g.goods_type = 'gift' ) ";
				$subWhereSql	.= " and ( g.goods_kind in ('" . implode("', '", $sc['goods_kind']) . "') "
								. " or g.goods_type = 'gift' ) ";
			}else{
				$optWhereSql	.= " and g.goods_kind in ('" . implode("', '", $sc['goods_kind']) . "') ";
				$subWhereSql	.= " and g.goods_kind in ('" . implode("', '", $sc['goods_kind']) . "') ";
			}
		}
		// 주거래처 검색
		if ($sc['trader_seq']){
			$optWhereSql	.= " and sod.trader_seq = '" . $sc['trader_seq'] . "' ";
			$subWhereSql	.= " and sod.trader_seq = '" . $sc['trader_seq'] . "' ";
		}else if ($sc['sc_trader']){
			$optWhereSql	.= " and sod.trader_seq = '" . $sc['sc_trader'] . "' ";
			$subWhereSql	.= " and sod.trader_seq = '" . $sc['sc_trader'] . "' ";
		}else if($sc['sc_traderGroup']){
			$optWhereSql	.= " and st.trader_group = '" . $sc['sc_traderGroup'] . "' ";
			$subWhereSql	.= " and st.trader_group = '" . $sc['sc_traderGroup'] . "' ";
		}
		// 전체 재고 검색
		if		($sc['sc_stock_type'] == 'chk_stock'){
			if		($sc['wh_seq']){
			    if ($sc['sc_stock_status'] == 'bad') {
			        $stockField = 'bad_ea';
			    } else {
			        $stockField = 'ea';
			    }

			    if	(isset($sc['sStock']) && $sc['sStock'] >= '0'){
					$optWhereSql	.= " and IFNULL(sll.{$stockField}, 0) >= '" . $sc['sStock'] . "' ";
					$subWhereSql	.= " and IFNULL(sll.{$stockField}, 0) >= '" . $sc['sStock'] . "' ";
				}
				if	(isset($sc['eStock']) && $sc['eStock'] >= '0'){
					$optWhereSql	.= " and IFNULL(sll.{$stockField}, 0) <= '" . $sc['eStock'] . "' ";
					$subWhereSql	.= " and IFNULL(sll.{$stockField}, 0) <= '" . $sc['eStock'] . "' ";
				}
			}else{
			    if ($sc['sc_stock_status'] == 'bad') {
			        $stockField = 'total_badstock';
			    } else {
			        $stockField = 'total_stock';
			    }
			    
			    if	(isset($sc['sStock']) && $sc['sStock'] >= '0'){
					$optWhereSql	.= " and sup.{$stockField} >= '" . $sc['sStock'] . "' ";
					$subWhereSql	.= " and sup.{$stockField} >= '" . $sc['sStock'] . "' ";
				}
				if	(isset($sc['eStock']) && $sc['eStock'] >= '0'){
					$optWhereSql	.= " and sup.{$stockField} <= '" . $sc['eStock'] . "' ";
					$subWhereSql	.= " and sup.{$stockField} <= '" . $sc['eStock'] . "' ";
				}
			}
		}elseif	($sc['sc_stock_type'] == 'chk_safe_stock'){
			if		($sc['sc_safestock_checktype'] == '2'){
				if	(!$sc['sc_safestock_sstock'])	$sc['sc_safestock_sstock']	= '0';
				$optWhereSql	.= " and sup.stock = (sup.safe_stock + ".$sc['sc_safestock_sstock'].") ";
				$subWhereSql	.= " and sup.stock = (sup.safe_stock + ".$sc['sc_safestock_sstock'].") ";
			}elseif	($sc['sc_safestock_checktype'] == '3'){
				if	($sc['sc_safestock_sstock'] > 0){
					$optWhereSql	.= " and sup.stock >= '".$sc['sc_safestock_sstock']."' ";
					$subWhereSql	.= " and sup.stock >= '".$sc['sc_safestock_sstock']."' ";
				}
				if	($sc['sc_safestock_estock'] > 0){
					$optWhereSql	.= " and sup.stock <= '".$sc['sc_safestock_estock']."' ";
					$subWhereSql	.= " and sup.stock <= '".$sc['sc_safestock_estock']."' ";
				}
			}elseif	($sc['sc_safestock_checktype'] == '4'){
				if	($sc['sc_safestock_sstock'] > 0){
					$optWhereSql	.= " and sup.safe_stock >= '".$sc['sc_safestock_sstock']."' ";
					$subWhereSql	.= " and sup.safe_stock >= '".$sc['sc_safestock_sstock']."' ";
				}
				if	($sc['sc_safestock_estock'] > 0){
					$optWhereSql	.= " and sup.safe_stock <= '".$sc['sc_safestock_estock']."' ";
					$subWhereSql	.= " and sup.safe_stock <= '".$sc['sc_safestock_estock']."' ";
				}
			}else{
				$optWhereSql	.= " and sup.stock < sup.safe_stock ";
				$subWhereSql	.= " and sup.stock < sup.safe_stock  ";
			}
		}

		// 창고별 검색
		if ($sc['wh_seq']){
			$optSelectSql	.= ", sll.location_position, sll.location_code "
							. ", sll.ea as location_stock, sll.bad_ea as location_badstock "
							. ", sll.supply_price as location_supply_price ";
			$subSelectSql	.= ", sll.location_position, sll.location_code "
							. ", sll.ea as location_stock, sll.bad_ea as location_badstock "
							. ", sll.supply_price as location_supply_price ";
			$optFromSql		.= "left join fm_scm_location_link	as sll
							on ( sll.goods_seq = opt.goods_seq
							and sll.option_seq = opt.option_seq
							and sll.option_type = 'option'
							and sll.wh_seq = '" . $sc['wh_seq'] . "' ) ";
			$subFromSql		.= "left join fm_scm_location_link	as sll
							on ( sll.goods_seq = sub.goods_seq
							and sll.option_seq = sub.suboption_seq
							and sll.option_type = 'suboption'
							and sll.wh_seq = '" . $sc['wh_seq'] . "' ) ";
		}

		// 분류 조건
		if($sc['scm_category']){
			foreach($sc['scm_category'] as $val){
				if($val != '') $category_code = $val;
			}

			if($category_code != ''){
				$optWhereSql	.= " and g.scm_category like '" . $category_code . "%' ";
				$subWhereSql	.= " and g.scm_category like '" . $category_code . "%' ";
			}

		}

		if	(!$this->scm_use_suboption_mode && $sc['option_type'] == 'suboption')	return array();
		// 통합쿼리로 쿼리 생성
		if ($sc['option_type'] != 'option' && $this->scm_use_suboption_mode){

			//정렬 조건
			if($sc['orderby'] && $sc['orderby'] != 'goods_seq asc' && $sc['orderby'] != 'goods_seq desc'){
				$optOrderSql .= " order by ".$sc['orderby']. " " . $sc['sort'];
			}else{
				$optOrderSql .= " order by opt.goods_seq desc ";
			}

			$selectSql	= "select * ";
			$fromSql	= "from ((".$optSelectSql . $optFromSql . $optWhereSql.") union
							(".$subSelectSql . $subFromSql . $subWhereSql."))	as tmp ";
			$whereSql	= "";
			$groupBy		= "";
			$orderBy		= $optOrderSql." , option_type, option_seq ";
		}else{
			//속도문제로 sql 개선 @2016-08-04 ysm
			//정렬 조건
			if($sc['orderby'] && $sc['orderby'] != 'goods_seq asc' && $sc['orderby'] != 'goods_seq desc'){
				//속도문제로 sql 개선 @2016-08-04 ysm
				$gdsc = array('goods_seq','goods_name','goods_code');
				$optOrderSql .= ( in_array($sc['orderby'], $gdsc) )?" order by g.".$sc['orderby']. " " . $sc['sort']:" order by ".$sc['orderby']. " " . $sc['sort'];
			}else{
				$optOrderSql .= " order by opt.goods_seq desc ";
			}
			$selectSql	= $optSelectSql;//"select * ";
			$fromSql	= $optFromSql;//"from (".$optSelectSql . $optFromSql . $optWhereSql.") as tmp ";

			// 상품번호, 옵션구분, 옵션번호 조합 검색
			if	($sc['option_info_arr']){
				if	(!is_array($sc['option_info_arr']))	$sc['option_info_arr']	= array($sc['option_info_arr']);
				$optWhereSql	.= " and concat(g.goods_seq, 'option', opt.option_seq) in
								('" . implode("', '", $sc['option_info_arr']) . "') ";
			}
			$whereSql		= $optWhereSql;
			$groupBy		= "";
			$orderBy		= $optOrderSql;
		}

		// 쿼리 실행
		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		$sqlQuery['select']		= $selectSql;
		$sqlQuery['fromSql']	= $fromSql;
		$sqlQuery['whereSql']	= $whereSql;
		$sqlQuery['groupBy']	= $groupBy;
		$sqlQuery['orderBy']	= $orderBy;
		$sqlQuery['addBind']	= $addBind;
		$sqlQuery['countSql']	= "g.goods_seq > 0 and g.provider_seq = 1 and ( opt.package_count = 0 or opt.package_count is NULL )";

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$_result		= pagingScmNumbering($sqlQuery,$sc);
			$pages			= $_result['page'];
			$result 		= $_result['record'];
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
			$pages		= null;
		}

		if($traderInfo){
			foreach($result as $k => $v){
				if($v['trader_seq'] > 0){
					$result[$k]['trader_name']		= $traderInfo[$v['trader_seq']]['trader_name'];
					$result[$k]['currency_unit']	= $traderInfo[$v['trader_seq']]['currency_unit'];
				}
			}
		}

		return array($result,$pages);
	}

	// 해당 상품의 기본옵션의 주거래처 자동매입 정보 추출
	public function get_default_supply_goods_info($goods_seq){
		if	($this->chkScmConfig()){
			$sql	= "select st.trader_name, sod.*
						from fm_scm_order_defaultinfo	as sod
							inner join fm_goods_option	as go
								on ( sod.goods_seq = go.goods_seq and sod.option_seq = go.option_seq )
							left join fm_scm_trader		as st
								on sod.trader_seq = st.trader_seq
						where sod.goods_seq = ? and sod.option_type = 'option'
						and sod.main_trade_type = 'Y' and go.default_option = 'y' ";
			$query	= $this->db->query($sql, array($goods_seq));
			//echo end($this->db->queries);
			$result	= $query->row_array();
		}

		return $result;
	}

	// 해당 상품의 거래처 자동매입 정보 추출 ( 주거래처 > 매입가가 가장 작은 순 )
	public function get_default_sorder_info($goods_seq, $option_seq, $option_type = 'option', $trader_seq = ''){
		if	($this->chkScmConfig()){
			$selectSql	= "select * ";
			$fromSql	= "from fm_scm_order_defaultinfo	as fsod ";
			$whereSql	= "where fsod.default_seq > 0 and fsod.use_status = 'Y' "
						. "and goods_seq = ? and option_type = ? and option_seq = ? ";
			$groupBy	= "";
			$orderBy	= "order by fsod.main_trade_type desc";
			$limitSql	= "";
			$addBind	= array($goods_seq, $option_type, $option_seq);
			if	($trader_seq > 0){
				$whereSql	.= "and trader_seq = ? ";
				$addBind[]	= $trader_seq;
			}

			// 쿼리 실행
			$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . $limitSql;
			$query			= $this->db->query($sql, $addBind);
			$defaultinfo	= $query->result_array();
			if	($defaultinfo) foreach($defaultinfo as $k => $data){
				if	($data['auto_type'] == 'Y'){
					if	(!$consumer_price){
						if	($option_type == 'suboption')
							$sql		= "select * from fm_goods_suboption where suboption_seq = ? ";
						else
							$sql		= "select * from fm_goods_option where option_seq = ? ";
						$query			= $this->db->query($sql, array($option_seq));
						$optData		= $query->row_array();
						$consumer_price	= $optData['consumer_price'];
					}
					$supply_price	= ROUND($consumer_price * 10 / 11) * ($data['supply_price'] * 0.01);
					$data['supply_price']	= $supply_price;
				}

				if	($data['main_trade_type'] == 'Y'){
					$result	= $data;
					break;
				}else{
					if	($data['supply_price'] < $result['supply_price']){
						$result					= $data;
					}
				}
			}
		}

		return $result;
	}

	// 상품관리 추출
	public function get_order_defaultinfo($sc){

		$selectSql	= "select fsod.*, fst.trader_name, fst.trader_seq, fst.currency_unit ";
		$fromSql	= "from fm_scm_order_defaultinfo	as fsod
						LEFT JOIN fm_scm_trader	as fst
							ON fsod.trader_seq = fst.trader_seq ";
		$whereSql	= "where fsod.default_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by fsod.goods_seq, fsod.option_type, fsod.option_seq, fsod.default_seq desc";

		// 거래처가 연결된 매입정보만 추출
		if	($sc['exists_trader']){
			$fromSql	= "from fm_scm_order_defaultinfo	as fsod
							INNER JOIN fm_scm_trader	as fst
								ON fsod.trader_seq = fst.trader_seq ";
		}
		// 상품검색이 있을 시 상품 테이블 JOIN 추가
		if	($sc['goods_name'] || $sc['src_goods_name'] || $sc['goods_code'] || $sc['src_goods_code'] || $sc['goods_kind'] || $sc['get_goods_info']){
			$fromSql	.= "INNER JOIN fm_goods as fg
								on ( fsod.goods_seq = fg.goods_seq and fg.package_yn = 'n' and fg.provider_seq = 1 ) ";
		}
		// 창고 검색이 있을 시 로케이션 연결 테이블 JOIN 추가
		if	($sc['wh_seq']){
			$fromSql	.= "INNER JOIN fm_scm_location_link as fsll
								on ( fsod.goods_seq = fsll.goods_seq and fsod.option_type = fsll.option_type and fsod.option_seq = fsll.option_seq ) ";
		}

		// 상품 정보 요청에 따른 추가 정보 추출
		if		($sc['get_goods_info']){
			$selectSql	.= ", fg.goods_name ";
			if		(!$this->scm_use_suboption_mode){
				$selectSql	.= ", concat(IFNULL(fgo.option1, ''), IFNULL(fgo.option2, ''),
										IFNULL(fgo.option3, ''), IFNULL(fgo.option4, ''),
										IFNULL(fgo.option5, '') ) as option_name ";
				$selectSql	.= ", concat(fg.goods_code, IFNULL(fgo.optioncode1, ''), IFNULL(fgo.optioncode2, ''),
										IFNULL(fgo.optioncode3, ''), IFNULL(fgo.optioncode4, ''),
										IFNULL(fgo.optioncode5, '') ) as goods_code ";
				$fromSql	.= "LEFT JOIN fm_goods_option	as fgo
									on ( fsod.goods_seq = fgo.goods_seq and fsod.option_type = 'option' and fsod.option_seq = fgo.option_seq ) ";
			}else{
				$selectSql	.= " IF(fsod.option_type='suboption',fgs.suboption,
									concat(
										IFNULL(fgo.option1, ''),
										IFNULL(fgo.option2, ''),
										IFNULL(fgo.option3, ''),
										IFNULL(fgo.option4, ''),
										IFNULL(fgo.option5, '') ) ) as option_name ";
				$fromSql	.= "LEFT JOIN fm_goods_option	as fgo
									on ( fsod.goods_seq = fgo.goods_seq and fsod.option_type = 'option' and fsod.option_seq = fgo.option_seq )
								LEFT JOIN fm_goods_suboption as fgs
									on ( fsod.goods_seq = fgs.goods_seq and fsod.option_type = 'suboption' and fsod.option_seq = fgs.suboption_seq ) ";
			}
		}

		// 거래처 검색
		if		($sc['trader_seq']){
			$whereSql	.= " and fsod.trader_seq = ? ";
			$addBind[]	= $sc['trader_seq'];
		}
		// 상품번호 검색
		if		($sc['goods_seq'] > 0){
			$whereSql	.= " and fsod.goods_seq = ? ";
			$addBind[]	= $sc['goods_seq'];
		}elseif	($sc['src_goods_seq'] > 0){
			$whereSql	.= " and fsod.goods_seq like '" . $sc['src_goods_seq'] . "%' ";
		}elseif	($sc['goods_seq_arr']){
			if	(!is_array($sc['goods_seq_arr']))	$sc['goods_seq_arr']	= array($sc['goods_seq_arr']);
			$whereSql	.= " and fsod.goods_seq in ('" . implode("', '", $sc['goods_seq_arr']) . "') ";
		}
		// 상품번호, 옵션구분, 옵션번호 조합 검색
		if	($sc['option_info_arr']){
			if	(!is_array($sc['option_info_arr']))	$sc['option_info_arr']	= array($sc['option_info_arr']);
			$whereSql	.= " and concat(fsod.goods_seq, fsod.option_type, fsod.option_seq) in
							('" . implode("', '", $sc['option_info_arr']) . "') ";
		}
		// 상품명 검색
		if		($sc['goods_name']){
			$whereSql	.= " and fg.goods_name = ? ";
			$addBind[]	= $sc['goods_name'];
		}elseif	($sc['src_goods_name'] > 0){
			$whereSql	.= " and fg.goods_name like '%" . $sc['src_goods_name'] . "%' ";
		}
		// 상품코드 검색
		if		($sc['goods_code']){
			$whereSql	.= " and fg.goods_code = ? ";
			$addBind[]	= $sc['goods_code'];
		}elseif	($sc['src_goods_code'] > 0){
			$whereSql	.= " and fg.goods_code like '" . $sc['src_goods_code'] . "%' ";
		}
		// 상품구분 검색
		if		($sc['goods_kind']){
			$whereSql	.= " and fg.goods_kind = ? ";
			$addBind[]	= $sc['goods_kind'];
		}
		// 필수옵션 검색
		if		($sc['option_seq'] > 0){
			$whereSql	.= " and fsod.option_type = 'option' and fsod.option_seq = ? ";
			$addBind[]	= $sc['option_seq'];
		}
		// 추가옵션 검색
		if		($sc['suboption_seq'] > 0){
			$whereSql	.= " and fsod.option_type = 'suboption' and fsod.option_seq = ? ";
			$addBind[]	= $sc['suboption_seq'];
		}
		// 옵션정보 조합 검색
		if		($sc['option_iinfo_arr']){
			if	(!is_array($sc['option_iinfo_arr']))	$sc['option_iinfo_arr']	= array($sc['option_iinfo_arr']);
			$whereSql	.= " and concat(fsod.goods_seq, fsod.option_type, fsod.option_seq) in ('" . implode("', '", $sc['option_iinfo_arr']) . "') ";
		}
		// 주거래처 여부 검색
		if		($sc['main_trade_type']){
			if	($sc['main_trade_type'] == 'Y')	$sc['main_trade_type']	= 'Y';
			else								$sc['main_trade_type']	= 'N';
			$whereSql	.= " and fsod.main_trade_type = '" . $sc['main_trade_type'] . "' ";
		}
		// 창고 검색
		if		($sc['wh_seq']){
			$whereSql	.= " and fsll.wh_seq = ? ";
			$addBind[]	= $sc['wh_seq'];
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 상품관리 POST값 체크
	public function chk_order_defaultinfo($params){
		$goodsSeq				= trim($params['goods_seq']);
		$existOption			= $params['exist_option'];
		$defaultSeq				= $params['default_seq'];
		$optionType				= $params['option_type'];
		$optionSeq				= $params['option_seq'];
		$useStatus				= $params['use_status'];
		$mainTradeType			= $params['main_trade_type'];
		$traderSeq				= $params['trader_seq'];
		$supplyName				= $params['supply_goods_name'];
		$currency				= $params['currency'];
		$supplyPrice			= $params['supply_price'];
		$useSupplyTax			= $params['use_supply_tax'];
		$delDefaultSeq			= $params['del_default_seq'];
		$scm_auto_warehousing	= $params['scm_auto_warehousing'];
		$k						= 0;

		if	(!$goodsSeq){
			openDialogAlert('상품정보가 없습니다.', 400, 150, 'parent', '');
			exit;
		}

		if	($optionSeq) foreach($optionSeq as $k => $seq){
			if	($traderSeq[$k] > 0){
				if ($scm_auto_warehousing){
					$chkKey						= array_search($optionType[$k] . '_' . $optionSeq[$k], $existOption);
					if ($chkKey !== false){
						unset($existOption[$chkKey]);
					}
				}
				$info[$k]['default_seq']		= $defaultSeq[$k];
				$info[$k]['goods_seq']			= $goodsSeq;
				$info[$k]['option_type']		= $optionType[$k];
				$info[$k]['option_seq']			= $optionSeq[$k];
				$info[$k]['use_status']			= $useStatus[$k];
				$info[$k]['main_trade_type']	= $mainTradeType[$k];
				$info[$k]['trader_seq']			= $traderSeq[$k];
				$info[$k]['supply_goods_name']	= $supplyName[$k];
				$info[$k]['supply_price']		= $supplyPrice[$k];
				if	($currency[$k] == 'KRW')	$info[$k]['use_supply_tax']		= $useSupplyTax[$k];
				else							$info[$k]['use_supply_tax']		= 'N';
				$info[$k]['regist_date']		= date('Y-m-d H:i:s');
				$k++;
			}else{
				openDialogAlert('거래처가 없는 발주 정보가 존재합니다.<br/>거래처를 선택해 주세요.', 400, 200, 'parent', '');
				exit;
			}
		}

		if ($scm_auto_warehousing && count($existOption) > 0){
			openDialogAlert('공급처 배송 상품은 발주정보를 필수로 등록하여 주셔야 합니다.', 400, 150, 'parent', '');
			exit;
		}

		if	(count($info) > 0){
			$return		= array('goods_seq' => $goodsSeq, 'defaultinfo' => $info,
								'delDefaultSeq' => $delDefaultSeq, 'scm_auto_warehousing' => $scm_auto_warehousing);
		}else{
			// 분류코드만 설정되어 있는 경우 분류코드만 저장 :: 2019-08-29 pjw
			if(!empty($params['scm_category'])) {
				$this->scmmodel->save_scm_category($goodsSeq, $params['scm_category']);
				openDialogAlert('분류코드가 저장되었습니다.', 400, 150, 'parent', '');
				exit;
			}else{
				openDialogAlert('저장할 옵션이 없습니다.', 400, 150, 'parent', '');
				exit;
			}		
		}

		return $return;
	}

	// 상품관리 저장
	public function save_defaultinfo($defaultinfo){
		if	($this->chkScmConfig()){
			if	($defaultinfo) foreach($defaultinfo as $k => $data){
				unset($param);
				$param['goods_seq']			= $data['goods_seq'];
				$param['option_type']		= $data['option_type'];
				$param['option_seq']		= $data['option_seq'];
				$param['use_status']		= $data['use_status'];
				$param['main_trade_type']	= $data['main_trade_type'];
				$param['trader_seq']		= $data['trader_seq'];
				$param['supply_goods_name']	= $data['supply_goods_name'];
				$param['supply_price']		= $data['supply_price'];
				$param['use_supply_tax']	= $data['use_supply_tax'];

				if	($data['default_seq'] > 0){
					$this->db->where(array('default_seq' => $data['default_seq']));
					$this->db->update('fm_scm_order_defaultinfo', $param);
					$default_seq	= $data['default_seq'];
				}else{
					$param['regist_date']	= $data['regist_date'];
					$data['default_seq']	= $this->db->insert('fm_scm_order_defaultinfo', $param);
					$default_seq			= $this->db->insert_id();
				}
			}

			return $default_seq;
		}
	}

	// 저장한 매입정보 외에는 삭제
	public function delete_defaultinfo($goods_seq, $delSeq){
		if	($this->chkScmConfig()){
			$sql	= "delete from fm_scm_order_defaultinfo "
					. "where goods_seq = '" . $goods_seq . "' "
					. "and default_seq in ('" . implode("', '", $delSeq) . "') ";
			$this->db->query($sql);
		}
	}

	// 상품관리 log 저장
	public function save_defaultinfo_log($params){
		$goodsSeq	= trim($params['goods_seq']);
		$admin_memo	= addslashes($params['admin_memo']);
		$admin		= $this->managerInfo;
		$log		= '<div>[' . date('Y-m-d H:i:s') . '] '
					. $admin['mname'] . '(' . $admin['manager_id'] . ')가 '
					. '매입정보를 수정하였습니다. (' . $_SERVER['REMOTE_ADDR'] . ')</div>';

		$sql		= "select * from fm_scm_order_defaultinfo_log where goods_seq = ? ";
		$query		= $this->db->query($sql, array($goodsSeq));
		$data		= $query->row_array();
		if	($data['goods_seq'] > 0){
			$sql	= "update fm_scm_order_defaultinfo_log set "
					. "admin_memo = ? , chg_log = concat(chg_log, '" . $log . "') "
					. "where goods_seq = ? ";
			$this->db->query($sql, array($admin_memo, $goodsSeq));
		}else{
			$sql	= "insert into fm_scm_order_defaultinfo_log (goods_seq, admin_memo, chg_log)"
					. "values('" . $goodsSeq . "', '" . $admin_memo . "', '" . $log . "')";
			$this->db->query($sql);
		}
	}

	// 상품관리 log 추출
	public function get_defaultinfo_log($goodsSeq){
		$sql	= "select * from fm_scm_order_defaultinfo_log where goods_seq = ? ";
		$query	= $this->db->query($sql, array($goodsSeq));
		$result	= $query->row_array();

		return $result;
	}

	// 상품관리 무재고 여부 저장
	public function save_scm_auto_warehousing($goods_seq, $scm_auto_warehousing){
		$sql	= "update fm_goods set scm_auto_warehousing = ? where goods_seq = ? ";
		$this->db->query($sql, array($scm_auto_warehousing, $goods_seq));
	}

	// 상품관리 분류 정보 저장
	public function save_scm_category($goods_seq, $scm_category){
		$sql	= "update fm_goods set scm_category = ? where goods_seq = ? ";
		$this->db->query($sql, array($scm_category, $goods_seq));
	}

	// 옵션단위 기준 매입정보 추출
	public function get_latest_defaultinfo($sc){
		$selectSql	= "select fsod.*, fst.trader_name, fst.currency_unit ";
		$fromSql	= "from fm_scm_order_defaultinfo	as fsod "
					. "inner join fm_scm_trader			as fst "
					. "on fsod.trader_seq = fst.trader_seq ";
		$whereSql	= "where fsod.use_status = 'Y' ";
		$groupBy	= "";
		$orderBy	= "";
		$limit		= "";

		if	($sc['goods_seq']){
			$whereSql	.= "and fsod.goods_seq = ? ";
			$addBind[]	= $sc['goods_seq'];
		}
		if	($sc['option_seq']){
			$whereSql	.= "and fsod.option_seq = ? ";
			$addBind[]	= $sc['option_seq'];
		}
		if	($sc['trader_group']){
			$whereSql	.= "and fst.trader_group = ? ";
			$addBind[]	= $sc['trader_group'];
		}
		if	($sc['trader_seq']){
			$whereSql	.= "and fsod.trader_seq = ? ";
			$addBind[]	= $sc['trader_seq'];
		}
		if	($sc['limit']){
			$limit		= "limit " . $sc['limit'];
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . $limit;
		$query			= $this->db->query($sql, $addBind);
		$result			= $query->result_array();

		return $result;
	}

	// 외화 거래처의 부가세 사용 여부를 일괄 미사용으로 변경
	public function chg_taxuse_to_currency(){
		$sql	= "update fm_scm_order_defaultinfo fsod, fm_scm_trader fst set "
				. "fsod.use_supply_tax = 'N' "
				. "where fsod.trader_seq = fst.trader_seq and fst.currency_unit != 'KRW' ";
		$this->db->query($sql);
	}

	########## ↑↑ 상품관리 ↑↑ ##### ↓↓ 자동발주 ↓↓ ##########

	// 자동발주 상품 체크 및 추가 ( 주문단위 )
	public function auto_order_to_order($order_seq){

		if	($order_seq && $this->chkScmConfig()){
			// 필수옵션
			$sql		= "select opt.*, item.goods_seq as goods_seq "
						. "from fm_order_item as item, fm_order_item_option as opt "
						. "where item.order_seq = ? and item.item_seq = opt.item_seq ";
			$query		= $this->db->query($sql, array($order_seq));
			$options	= $query->result_array();
			if	($options) foreach( $options as $o => $opt){
				if	($opt['item_option_seq'] > 0 && $opt['goods_seq'] > 0)
					$this->auto_order_goods('option', $opt['item_option_seq']);
			}

			if	($this->scm_use_suboption_mode){
				// 추가옵션
				$sql		= "select sub.*, item.goods_seq as goods_seq "
							. "from fm_order_item as item, fm_order_item_suboption as sub "
							. "where item.order_seq = ? and item.item_seq = sub.item_seq ";
				$query		= $this->db->query($sql, array($order_seq));
				$options	= $query->result_array();
				if	($options) foreach( $options as $o => $opt){
					if	($opt['item_option_seq'] > 0 && $opt['goods_seq'] > 0)
						$this->auto_order_goods('suboption', $opt['item_suboption_seq']);
				}
			}
		}
	}

	// 자동발주 상품 체크 및 추가 ( 옵션단위 )
	public function auto_order_goods($option_type, $order_option_seq){

		if	($option_type && $order_option_seq > 0 && $this->chkScmConfig()){

			// 주문 옵션 정보 추출
			if	($option_type == 'suboption'){
				$sql			= "select *, (IFNULL(ea, 0) - IFNULL(refund_ea,0)) as order_ea "
								. "from fm_order_item_suboption where item_suboption_seq = ? "
								. "and step >= 25 and step <= 75";
				$query			= $this->db->query($sql, array($order_option_seq));
				$order_option	= $query->row_array();
			}else{
				$sql			= "select *, (IFNULL(ea, 0) - IFNULL(refund_ea,0)) as order_ea "
								. "from fm_order_item_option where item_option_seq = ? "
								. "and step >= 25 and step <= 75";
				$query			= $this->db->query($sql, array($order_option_seq));
				$order_option	= $query->row_array();
			}
			if	($order_option['order_seq'] > 0 && $order_option['order_ea'] > 0){

				// 주문 상품 정보 추출
				$sql				= "select * from fm_order_item where item_seq = ? limit 1";
				$query				= $this->db->query($sql, array($order_option['item_seq']));
				$order_item			= $query->row_array();
				$goods_seq			= $order_item['goods_seq'];

				// 주문 상품 공급처 배송 체크
				$sql				= "select goods_seq, scm_auto_warehousing from fm_goods where goods_seq = ?";
				$query				= $this->db->query($sql, array($goods_seq));
				$goods_item			= $query->row_array();

				// 입점사 상품이거나, 공급처 배송상품이면 자동발주 안함
				if ($order_item['provider_seq'] != '1' || $goods_item['scm_auto_warehousing'] == '1'){
					return false;
				}

				// 상품 옵션 정보 추출
				$optBind[]		= $goods_seq;
				if	($option_type == 'suboption'){
					$addWhere[]		= " and sub.suboption = ? ";
					$optBind[]		= $order_option['suboption'];
					$addWhere[]		= " and sub.suboption_title = ? ";
					$optBind[]		= $order_option['title'];
					$sql			= "select *, "
									. "sub.suboption_seq as option_seq "
									. "from fm_goods_suboption as sub, fm_goods_supply as sup "
									. "where sub.goods_seq = ? and sub.suboption_seq = sup.suboption_seq "
									. implode(' ', $addWhere) . " limit 1 ";
					$query			= $this->db->query($sql, $optBind);
					$goods_option	= $query->row_array();
				}else{
					for ($fo = 1; $fo <= 5; $fo++){
						$fld		= 'option' . $fo;
						$codeFld	= 'optioncode' . $fo;
						if	($order_option[$fld]){
							$addWhere[]		= " and " . $fld . " = ? ";
							$optBind[]		= $order_option[$fld];
						}
					}
					$sql			= "select *, "
									. "opt.option_seq as option_seq "
									. "from fm_goods_option as opt, fm_goods_supply as sup "
									. "where opt.goods_seq = ? and opt.option_seq = sup.option_seq "
									. implode(' ', $addWhere) . " limit 1 ";
					$query			= $this->db->query($sql, $optBind);
					$goods_option	= $query->row_array();
				}
				$goods_option['option_type']	= $option_type;

				// 패키지 상품 처리
				if	($goods_option['package_count'] > 0){
					$order_ea			= $order_option['order_ea'];
					$package_count		= $goods_option['package_count'];
					for	($p = 1; $p <= $package_count; $p++){
						$option_seq					= $goods_option['package_option_seq' . $p];
						$order_option['order_ea']	= $order_ea * $goods_option['package_unit_ea' . $p];
						// 원 상품 옵션 정보 추출
						$sql					= "select *, "
												. "opt.option_seq as option_seq, opt.price, "
												. "opt.consumer_price, sup.stock, sup.badstock, "
												. "sup.safe_stock, sup.reservation25 "
												. "from fm_goods_option as opt, fm_goods_supply as sup "
												. "where opt.option_seq = ? "
												. "and opt.option_seq = sup.option_seq "
												. "limit 1 ";
						$query					= $this->db->query($sql, array($option_seq));
						$package_goods_option	= $query->row_array();
						$package_goods_option['option_type']	= 'option';

						// 원 상품 상품 정보 추출
						$goods_seq				= $package_goods_option['goods_seq'];
						$sql					= "select * from fm_goods where goods_seq = ? ";
						$query					= $this->db->query($sql, array($goods_seq));
						$package_goods_info		= $query->row_array();

						$this->save_autosorder_goods($package_goods_info, $order_option, $package_goods_option);
					}
				}else{
					$this->save_autosorder_goods($order_item, $order_option, $goods_option);
				}
			}
		}
	}

	// 자동발주 상품 저장
	public function save_autosorder_goods($goodsinfo, $orderoption, $goodsoption, $compulsion = false){
		$goods_seq					= $goodsinfo['goods_seq'];
		$goods_name					= $goodsinfo['goods_name'];
		$goods_code					= $goodsinfo['goods_code'];
		$order_seq					= $orderoption['order_seq'];
		$order_ea					= $orderoption['order_ea'];
		$option_seq					= $goodsoption['option_seq'];
		$option_type				= $goodsoption['option_type'];
		$consumer_price				= $goodsoption['consumer_price'];
		$price						= $goodsoption['price'];
		$stock						= $goodsoption['stock'];
		$bad_stock					= $goodsoption['badstock'];
		$safe_stock					= $goodsoption['safe_stock'];
		$reservation25				= $goodsoption['reservation25'];
		$option_name				= '';

		if	($option_type == 'suboption'){
			$goods_code				.= $goodsoption['suboption_code'];
			$option_name			= $goodsoption['suboption'];
		}else{
			for ($fo = 1; $fo <= 5; $fo++){
				$fld				= 'option' . $fo;
				$codeFld			= 'optioncode' . $fo;
				if	($goodsoption[$fld]){
					$option_name	.= $goodsoption[$fld];
					$goods_code		.= $goodsoption[$codeFld];
				}
			}
		}

		// 자동 발주조건
		$auto_order_condition	= $this->scm_cfg['auto_order_condition'];
		if	($compulsion)	$auto_order_condition	= '3';
		if			($auto_order_condition == '1'){ // 재고 - 불량 - 주문수량 < 안전재고
			$auto_conf_flag		= ($stock - $bad_stock - $order_ea) < $safe_stock;
		}else if	($auto_order_condition == '2'){ // 가용재고 - 주문수량 < 안전재고
			$auto_conf_flag		= (($stock - $reservation25 - $bad_stock) - $order_ea) < $safe_stock;
		}else if	($auto_order_condition == '3'){ // 주문수량을 무조건 자동발주
			$auto_conf_flag		= true;
		}else{	// 조건 값이 없는 경우 (재고 - 주문수량 < 안전재고 )
			$auto_conf_flag		= ($stock - $order_ea) < $safe_stock;
		}

		if	( $auto_conf_flag && $option_seq > 0){

			// 현재 주거래처 정보 추출
			unset($sc, $default_info);
			$sc['goods_seq']					= $goods_seq;
			$sc['main_trade_type']				= 'Y';
			$sc['exists_trader']				= 'Y';
			if	($option_type == 'suboption')	$sc['suboption_seq']	= $option_seq;
			else								$sc['option_seq']		= $option_seq;
			$default_info						= $this->get_order_defaultinfo($sc);
			$default_info						= $default_info[0];
			if	(!$default_info['trader_seq'])		$default_info['trader_seq']		= '0';
			if	(!$default_info['supply_price'])	$default_info['supply_price']	= '0';
			if	(!$default_info['use_supply_tax'])	$default_info['use_supply_tax']	= 'N';

			// 자동발주 상품 추가
			unset($insertParam);
			$insertParam['order_seq']			= $order_seq;
			$insertParam['goods_seq']			= $goods_seq;
			$insertParam['goods_name']			= $goods_name;
			$insertParam['goods_code']			= $goods_code;
			$insertParam['option_type']			= $option_type;
			$insertParam['option_seq']			= $option_seq;
			$insertParam['option_name']			= $option_name;
			$insertParam['ea']					= $order_ea;
			$insertParam['safe_stock']			= $safe_stock;
			$insertParam['trader_seq']			= $default_info['trader_seq'];
			$insertParam['supply_goods_name']	= $default_info['supply_goods_name'];
			$insertParam['use_tax']				= $default_info['use_supply_tax'];
			$insertParam['supply_price']		= $default_info['supply_price'];
			$insertParam['regist_date']			= date('Y-m-d H:i:s');
			$this->db->insert('fm_scm_autoorder_order', $insertParam);
		}
	}

	// 자동 발주 상품 목록
	public function get_auto_order_goods($sc){
		$selectSql	= "select SUM(IFNULL(sao.ea, 0)) as sum_ea, count(*) as sum_cnt, "
					. "GROUP_CONCAT(IF(order_seq=0,'',order_seq)) as order_list, "
					. "GROUP_CONCAT(aoo_seq) as aoo_seq_list, "
					. "fst.trader_name, "
					. "fst.trader_group, "
					. "fst.currency_unit, "
					. "sao.* ";
		$fromSql	= "from fm_scm_autoorder_order	as sao "
					. "LEFT JOIN fm_scm_trader		as fst "
					. "ON sao.trader_seq = fst.trader_seq ";
		$whereSql	= "where sao.aoo_seq > 0 ";
		$groupBy	= "group by DATE_FORMAT(sao.regist_date, '%Y-%m-%d'), "
					. "sao.goods_seq, sao.option_type, sao.option_seq ";
		$orderBy	= "order by sao.aoo_seq asc";

		// 자동 발주 고유번호 검색
		if		($sc['aoo_seq']){
			$whereSql	.= " and sao.aoo_seq = ? ";
			$addBind[]	= $sc['aoo_seq'];
		}elseif	($sc['sc_aoo_seq']){
			if	(!is_array($sc['sc_aoo_seq']))	$sc['sc_aoo_seq']	= array($sc['sc_aoo_seq']);
			$whereSql	.= " and sao.aoo_seq in ('" . implode("', '", $sc['sc_aoo_seq']) . "') ";
		}
		// 상품 고유번호 검색
		if		($sc['goods_seq']){
			$whereSql	.= " and sao.goods_seq = ? ";
			$addBind[]	= $sc['goods_seq'];
		}
		// 옵션 구분 검색
		if		(!$this->scm_use_suboption_mode){
			$whereSql	.= " and sao.option_type = 'option' ";
		}elseif	($sc['option_type']){
			$whereSql	.= " and sao.option_type = ? ";
			$addBind[]	= $sc['option_type'];
		}
		// 옵션 번호 검색
		if		($sc['option_seq']){
			$whereSql	.= " and sao.option_seq = ? ";
			$addBind[]	= $sc['option_seq'];
		}
		// 거래처 검색
		if ($sc['sc_exists_info'] == '1'){
			$whereSql	.= " and sao.trader_seq = 0 ";
		}else if ($sc['trader_seq']){
			$whereSql	.= " and sao.trader_seq = ? ";
			$addBind[]	= $sc['trader_seq'];
		}else if		($sc['sc_trader']){
			$whereSql	.= " and sao.trader_seq = ? ";
			$addBind[]	= $sc['sc_trader'];
		}
		// 매입 상품명 검색
		if		($sc['supply_goods_name']){
			$whereSql	.= " and sao.supply_goods_name = ? ";
			$addBind[]	= addslashes(trim($sc['supply_goods_name']));
		}elseif	($sc['sc_supply_goods_name']){
			$whereSql	.= " and sao.supply_goods_name like '%" . addslashes(trim($sc['sc_supply_goods_name'])) . "%' ";
		}
		// 상품명 검색
		if		($sc['goods_name']){
			$whereSql	.= " and sao.goods_name = ? ";
			$addBind[]	= addslashes(trim($sc['goods_name']));
		}elseif	($sc['sc_goods_name']){
			$whereSql	.= " and sao.goods_name like '%" . addslashes(trim($sc['sc_goods_name'])) . "%' ";
		}
		// 상품 코드 검색
		if		($sc['goods_code']){
			$whereSql	.= " and sao.goods_code = ? ";
			$addBind[]	= addslashes(trim($sc['goods_code']));
		}elseif	($sc['sc_goods_code']){
			$whereSql	.= " and sao.goods_code like '%" . addslashes(trim($sc['sc_goods_code'])) . "%' ";
		}
		// 상품 번호 검색
		if		($sc['goods_seq']){
			$whereSql	.= " and sao.goods_seq = ? ";
			$addBind[]	= addslashes(trim($sc['goods_seq']));
		}elseif	($sc['sc_goods_seq']){
			$whereSql	.= " and sao.goods_seq = ? ";
			$addBind[]	= addslashes(trim($sc['sc_goods_seq']));
		}
		// 조정 일자 검색
		if		($sc['sc_sdate'] && $sc['sc_edate']){
			$whereSql	.= " and sao.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00'
							 and sao.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
		}elseif	($sc['sc_sdate']){
			$whereSql	.= " and sao.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
		}elseif	($sc['sc_edate']){
			$whereSql	.= " and sao.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
		}

		// 검색어 전체 검색
		if		($sc['keyword']){
			$whereSql	.= " and ( "
						. "sao.supply_goods_name like '%" . addslashes(trim($sc['keyword'])) . "%' or "
						. "sao.goods_name like '%" . addslashes(trim($sc['keyword'])) . "%' or "
						. "sao.goods_code like '%" . addslashes(trim($sc['keyword'])) . "%' ) ";
		}
		// option mode에 따른 쿼리 변경
		if		(!$this->scm_use_suboption_mode){
			$addOptionSql	= " and (sao.option_type = 'option' and sao.option_seq = gs.option_seq) ";
		}else{
			$addOptionSql	= " and ( (sao.option_type = 'option' and sao.option_seq = gs.option_seq) or "
							. "(sao.option_type = 'suboption' and sao.option_seq = gs.suboption_seq) ) ";
		}

		// 창고별 검색 시 fm_scm_location_link 에서 검색되도록 수정 2019-07-02 by hyem
		if( !empty($sc['sc_warehouse']) ) {
			$stockField = "ea";
			$selectSql	.= ", gs.ea as gsea, gs.bad_ea as gsbad_ea ";
			$fromSql	.= "left join fm_scm_location_link as gs "
				. "on ( sao.goods_seq = gs.goods_seq " . $addOptionSql . " and gs.wh_seq = '".$sc['sc_warehouse']."' ) ";
		} else {
			$stockField = "stock";
			$fromSql	.= "inner join fm_goods_supply as gs "
				. "on ( sao.goods_seq = gs.goods_seq " . $addOptionSql . " ) ";
		}

		// 현재 재고 검색
		if		(!empty($sc['sc_sstock']) && !empty($sc['sc_estock'])){
			$whereSql	.= " and gs.".$stockField." >= '" . $sc['sc_sstock'] . "' "
						. " and gs.".$stockField." <= '" . $sc['sc_estock'] . "' ";
		}elseif	(!empty($sc['sc_sstock'])){
			$whereSql	.= " and gs.".$stockField." >= '" . $sc['sc_sstock'] . "' ";
		}elseif	(!empty($sc['sc_estock'])){
			$whereSql	.= " and (gs.".$stockField." <= '" . $sc['sc_estock'] . "' or gs.".$stockField." is NULL )";
		}

		// 옵션별 그룹
		if		($sc['groupby'] == 'options'){
			$groupBy	= "group by sao.goods_seq, sao.option_type, sao.option_seq ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		$sqlQuery['select']		= $selectSql;
		$sqlQuery['fromSql']	= $fromSql;
		$sqlQuery['whereSql']	= $whereSql;
		$sqlQuery['groupBy']	= $groupBy;
		$sqlQuery['orderBy']	= $orderBy;
		$sqlQuery['addBind']	= $addBind;
		$sqlQuery['countSql']	= "sao.aoo_seq > 0 ";

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= pagingScmNumbering($sqlQuery,$sc);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 자동 발주 상품 발주 후 삭제
	public function auto_order_complete($aooSeq){
		if	($aooSeq){
			if	(!is_array($aooSeq))	$aooSeq	= array($aooSeq);
			if	($aooSeq) foreach($aooSeq as $k => $seq){
				if		(preg_match('/\,/', $seq))	$seq	= explode(',', $seq);
				elseif	(!is_array($seq))			$seq	= array($seq);

 				$sql	= "delete from fm_scm_autoorder_order "
						. "where aoo_seq in ('" . implode("', '", $seq) . "') ";
				$this->db->query($sql);
			}
		}
	}

	########## ↑↑ 자동발주 ↑↑ ##### ↓↓ 재고조정 ↓↓ ##########

	// 재고조정 목록
	public function get_revision_list($sc){
		$selectSql	= "select ssr.* ";
		$fromSql	= "from fm_scm_stock_revision	as ssr ";
		$whereSql	= "where ssr.revision_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by ssr.revision_status, ssr.revision_seq desc ";

		// 조정 고유번호 검색
		if		($sc['revision_seq']){
			$whereSql	.= " and ssr.revision_seq = ? ";
			$addBind[]	= $sc['revision_seq'];
		}elseif	($sc['sc_revision_seq']){
			$whereSql	.= " and ssr.revision_seq like '" . addslashes(trim($sc['sc_revision_seq'])) . "%' ";
		}
		// 조정 상태 검색
		if		(isset($sc['revision_status']) && trim($sc['revision_status']!='')){
			$whereSql	.= " and ssr.revision_status = ? ";
			$addBind[]	= $sc['revision_status'];
		}
		// 거래처 검색
		if		($sc['sc_trader']){
			$whereSql	.= " and ssr.trader_seq = ? ";
			$addBind[]	= $sc['sc_trader'];
		}
		// 창고 검색
		if		($sc['sc_warehouse']){
			$whereSql	.= " and ssr.wh_seq = ? ";
			$addBind[]	= $sc['sc_warehouse'];
		}
		// 조정코드 검색
		if		($sc['sc_revision_code']){
			$whereSql	.= " and ssr.revision_code like '%" . addslashes($sc['sc_revision_code']) . "%' ";
		}
		// 조정 상태 검색
		if		($sc['sc_revision_status']){
			if	(!is_array($sc['sc_revision_status']))	$sc['sc_revision_status']	= array($sc['sc_revision_status']);
			$whereSql	.= " and ssr.revision_status in ('" . implode("', '", $sc['sc_revision_status']) . "') ";
		}
		// 조정 종류 검색
		if		($sc['sc_revision_type']){
			if	(!is_array($sc['sc_revision_type']))	$sc['sc_revision_type']	= array($sc['sc_revision_type']);
			$whereSql	.= " and ssr.revision_type in ('" . implode("', '", $sc['sc_revision_type']) . "') ";
		}
		// 조정 일자 검색
		if		($sc['sc_date_fld'] == 'regist'){
			if		($sc['sc_sdate'] && $sc['sc_edate']){
				$whereSql	.= " and ssr.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' "
							. " and ssr.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_sdate']){
				$whereSql	.= " and ssr.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_edate']){
				$whereSql	.= " and ssr.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}
		}elseif	($sc['sc_date_fld'] == 'complete'){
			if		($sc['sc_sdate'] && $sc['sc_edate']){
				$whereSql	.= " and ssr.revision_status = '1' "
							. " and ssr.complete_date >= '" . $sc['sc_sdate'] . " 00:00:00' "
							. " and ssr.complete_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_sdate']){
				$whereSql	.= " and ssr.revision_status = '1' "
							. " and ssr.complete_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_edate']){
				$whereSql	.= " and ssr.revision_status = '1' "
							. " and ssr.complete_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}
		}
		// 상품번호 검색
		if		($sc['sc_goods_seq']){
			$fromSql		.= " INNER JOIN fm_scm_stock_revision_goods	as ssrg "
							. " ON ssr.revision_seq = ssrg.revision_seq ";
			$whereSql		.= " and ssrg.goods_seq like '" . addslashes(trim($sc['sc_goods_seq'])) . "%' ";
			$groupBy		= " group by ssr.revision_seq ";
		}
		// 상품코드 검색
		if		($sc['sc_goods_code']){
			$fromSql		.= " INNER JOIN fm_scm_stock_revision_goods	as ssrg "
							. " ON ssr.revision_seq = ssrg.revision_seq ";
			$whereSql		.= " and LOWER(ssrg.goods_code) like '%" . addslashes(strtolower(trim($sc['sc_goods_code']))) . "%' ";
			$groupBy		= " group by ssr.revision_seq ";
		}
		// 상품명 검색
		if		($sc['sc_goods_name']){
			$fromSql		.= " INNER JOIN fm_scm_stock_revision_goods	as ssrg "
							. " ON ssr.revision_seq = ssrg.revision_seq ";
			$whereSql		.= " and ssrg.goods_name like '%" . addslashes(trim($sc['sc_goods_name'])) . "%' ";
			$groupBy		= " group by ssr.revision_seq ";
		}
		// 키워드 검색
		if		($sc['keyword']){
			$sc['keyword']	= addslashes(trim($sc['keyword']));
			$fromSql		.= " INNER JOIN fm_scm_stock_revision_goods	as ssrg "
							. " ON ssr.revision_seq = ssrg.revision_seq ";
			$whereSql		.= " and ( ssrg.goods_seq like '" . $sc['keyword'] . "%' "
							. " or LOWER(ssrg.goods_code) like '%" . strtolower($sc['keyword']) . "%' "
							. " or ssrg.goods_name like '%" . addslashes($sc['keyword']) . "%' "
							. " or ssr.revision_code like '%" . addslashes($sc['keyword']) . "%') ";
			$groupBy		= " group by ssr.revision_seq ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		
		$sqlQuery['select']		= $selectSql;
		$sqlQuery['fromSql']	= $fromSql;
		$sqlQuery['whereSql']	= $whereSql;
		$sqlQuery['groupBy']	= $groupBy;
		$sqlQuery['orderBy']	= $orderBy;
		$sqlQuery['addBind']	= $addBind;
		$sqlQuery['countSql']	= "ssr.revision_seq > 0 ";

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result				= pagingScmNumbering($sqlQuery,$sc);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 재고조정 상품
	public function get_revision_goods($sc){
		$selectSql	= "select fssrg.* ";
		$fromSql	= "from fm_scm_stock_revision_goods	as fssrg ";
		$whereSql	= "where fssrg.revision_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by fssrg.revision_seq, fssrg.goods_seq, fssrg.option_type, fssrg.option_seq ";

		// 상품 요약 정보
		if	($sc['get_type'] == 'summary'){
			$selectSql	= "select *, count(*) as cnt, SUM(IFNULL(fssrg.ea, 0)) as tea ";
			$groupBy	= "group by fssrg.revision_seq ";
		}
		// 조정 고유번호 검색
		if	($sc['revision_seq']){
			$whereSql	.= " and fssrg.revision_seq = ? ";
			$addBind[]	= $sc['revision_seq'];
		}
		// 창고정보 포함 검색
		if	($sc['wh_seq']){
			$selectSql	.= ", fsll.ea as location_ea, fsll.bad_ea "
						. ", fsll.location_position, fsll.location_code ";
			$fromSql	.= "inner join fm_scm_location_link	as fsll "
						. "on (fssrg.goods_seq = fsll.goods_seq and fssrg.option_type = fsll.option_type "
						. "and fssrg.option_seq = fsll.option_seq and wh_seq = '" . $sc['wh_seq'] . "' ) ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 재고조정 Parameter 체크 및 재배열
	public function chk_revision_params($params){

		$revision_seq				= trim($params['revision_seq']);
		$revision_status			= trim($params['revision_status']);
		$revision_type				= trim($params['revision_type']);
		$wh_seq						= trim($params['wh_seq']);
		$freight					= trim($params['inclusion_freight']);
		$insurance					= trim($params['inclusion_insurance']);
		$admin_memo					= addslashes(trim($params['admin_memo']));
		$option						= $params['option_seq'];
		$currency					= 'KRW';
		$goods_exchange				= '1';
		$fi_exchange				= '1';
		$total_ea					= 0;
		$total_supply_price			= 0;
		$total_freight_price		= 0;
		$total_insurance_price		= 0;
		$total_cif_price			= 0;
		$total_duty_price			= 0;
		$total_accessorial_price	= 0;
		$krw_total_supply_price		= 0;
		$krw_total_supply_tax		= 0;
		$krw_total_price			= 0;
		$exec						= false;

		// 기존 입고 정보 추출
		if		($revision_seq > 0){
			unset($sc);
			$sc['revision_seq']	= $revision_seq;
			$old_revision		= $this->get_revision_list($sc);
			$old_revision		= $old_revision[0];
			if		($old_revision['revision_status'])	$revision_status	= 1;
			elseif	($revision_status)					$exec				= true;
		}elseif	($revision_status)						$exec				= true;

		if	(!$old_revision['revision_status']){
			if	(!$revision_type){
				openDialogAlert('재고조정 구분을 선택해 주세요.', 400, 170, 'parent', '');
				exit;
			}
			if	(!$wh_seq){
				openDialogAlert('창고를 선택해 주세요.', 400, 170, 'parent', '');
				exit;
			}

			if	(count($option) > 0){
				foreach($option as $idx => $opt){
					$goods_seq				= trim($params['goods_seq'][$idx]);
					$tmp					= explode('|', $opt);
					$option_type			= $tmp[0];
					$option_seq				= $tmp[1];
					if	($option_seq > 0){
						// 옵션 존재여부 체크
						if	(!$this->chk_option_exists($goods_seq, $option_type, $option_seq)){
							$msg	= $params['goods_name'][$idx] . ' ' . $params['option_name'][$idx]
									. '은 존재하지 않는 상품입니다.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}

						unset($supplyParams, $supplyInfo);
						$supplyParams['currency']			= $currency;
						$supplyParams['ea']					= trim($params['ea'][$idx]);
						$supplyParams['supply_price']		= trim($params['supply_price'][$idx]);
						$supplyParams['goods_exchange']		= '0';
						$supplyParams['fi_exchange']		= '0';
						$supplyParams['freight_price']		= '0';
						$supplyParams['insurance_price']	= '0';
						$supplyParams['duty_price']			= '0';
						$supplyParams['accessorial_price']	= '0';
						$supplyParams['supply_tax']			= '0';
						$supplyParams['use_tax']			= 'N';
						$supplyInfo							= $this->calculate_supply_info($supplyParams);

						// 상품정보
						$data['goods_seq']					= $goods_seq;
						$data['option_type']				= $option_type;
						$data['option_seq']					= $option_seq;
						$data['goods_code']					= trim($params['goods_code'][$idx]);
						$data['goods_name']					= trim($params['goods_name'][$idx]);
						$data['option_name']				= trim($params['option_name'][$idx]);
						$data['location_code']				= trim($params['location_code'][$idx]);
						$data['location_position']			= trim($params['location_position'][$idx]);
						$data['weight']						= trim($params['weight'][$idx]);
						$data['use_tax']					= $supplyInfo['use_tax'];
						$data['ea']							= $supplyInfo['ea'];
						$data['supply_price']				= $supplyInfo['supply_price'];
						$data['freight_price']				= $supplyInfo['freight_price'];
						$data['insurance_price']			= $supplyInfo['insurance_price'];
						$data['cif_price']					= $supplyInfo['cif_price'];
						$data['duty_price']					= $supplyInfo['duty_price'];
						$data['accessorial_price']			= $supplyInfo['accessorial_price'];
						$data['krw_supply_price']			= $supplyInfo['krw_supply_price'];
						$data['supply_tax']					= $supplyInfo['supply_tax'];

						// 입고 수량 체크
						if	(!$data['ea'] || $data['ea'] < 0){
							$msg	= $data['goods_name'] . ' ' . $data['option_name'] . '의 '
									. '수량을 입력해 주세요.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}
						// 해당 창고 정보 추출 ( 출고/폐기 수량 유효성 체크를 위해 )
						if	($revision_type != 'in'){
							// 해당 창고의 현재 정보추출
							unset($sc);
							$sc['wh_seq']						= $wh_seq;
							$sc['goods_seq']					= $goods_seq;
							$sc['option_type']					= $option_type;
							$sc['option_seq']					= $option_seq;
							$whData								= $this->get_location_goods($sc);
							$whData								= $whData[0];
							if	($whData['ea'] < $data['ea']){
								$msg	= $data['goods_name'] . ' ' . $data['option_name'] . '의 '
										. '수량이 현재 창고의 재고보다 높게 입력되었습니다.';
								openDialogAlert($msg, 600, 170, 'parent', '');
								exit;
							}
						}
						$goodsData[]					= $data;

						// 합계정보
						$total_ea						+= $supplyInfo['ea'];
						$total_goods_price				+= $supplyInfo['goods_price'];
						$total_freight_price			+= $supplyInfo['freight_price'];
						$total_insurance_price			+= $supplyInfo['insurance_price'];
						$total_cif_price				+= $supplyInfo['cif_price'];
						$total_duty_price				+= $supplyInfo['duty_price'];
						$total_accessorial_price		+= $supplyInfo['accessorial_price'];
						$krw_total_supply_price			+= $supplyInfo['krw_supply_price'];
						$krw_total_supply_tax			+= $supplyInfo['supply_tax'];
					}
				}
			}
			$krw_total_price	= $krw_total_supply_price + $krw_total_supply_tax;

			// 조정 상품 체크
			if	(!(count($goodsData) > 0)){
				openDialogAlert('조정 상품을 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}
			// 조정 수량 체크
			if	(!($total_ea > 0)){
				openDialogAlert('조정 수량이 없습니다.', 400, 150, 'parent', '');
				exit;
			}

			// 기본정보
			$revision['revision_type']					= $revision_type;
			$revision['revision_status']				= $revision_status;
			$revision['wh_seq']							= $wh_seq;
			$revision['currency']						= $currency;
			$revision['goods_exchange']					= $goods_exchange;
			$revision['fi_exchange']					= $fi_exchange;
			$revision['total_ea']						= $total_ea;
			$revision['total_goods_price']				= $total_goods_price;
			$revision['total_freight_price']			= $total_freight_price;
			$revision['total_insurance_price']			= $total_insurance_price;
			$revision['total_cif_price']				= $total_cif_price;
			$revision['total_duty_price']				= $total_duty_price;
			$revision['total_accessorial_price']		= $total_accessorial_price;
			$revision['krw_total_supply_price']			= $krw_total_supply_price;
			$revision['krw_total_supply_tax']			= $krw_total_supply_tax;
			$revision['krw_total_price']				= $krw_total_price;
		}
		if	($exec){
			$revision['chg_log']			= '<div>' . date('Y-m-d H:i:s') . ' '
											. $this->managerInfo['mname']
											. '(' . $this->managerInfo['manager_id'] . ')가 '
											. '재고조정 완료하였습니다. '
											. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
			$revision['regist_date']		= date('Y-m-d H:i:s');
		}else{
			$revision['chg_log']			= '<div>' . date('Y-m-d H:i:s') . ' '
											. $this->managerInfo['mname']
											. '(' . $this->managerInfo['manager_id'] . ')가 '
											. '재고조정을 저장하였습니다. '
											. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		}
		$revision['admin_memo']				= $admin_memo;

		return array(	'revision_seq'		=> $revision_seq,
						'revision_status'	=> $revision_status,
						'revision'			=> $revision,
						'revisionGoods'		=> $goodsData,
						'exec'				=> $exec			);
	}

	// 재고조정 기본값 저장
	public function save_stock_revision($params, $revision_seq = 0){
		if	($this->chkScmConfig()){
			if	($revision_seq > 0){
					unset($setSql);
					$sql	= "update fm_scm_stock_revision set ";
					foreach($params as $fld => $val){
						if	(in_array($fld, array('chg_log')))
							$setSql[]	= $fld . " = concat(IFNULL(" . $fld . ", ''), '" . $val . "') ";
						else
							$setSql[]	= $fld . " = '" . $val . "' ";
					}
					$sql	.= implode(", ", $setSql) . " ";
					$sql	.= "where revision_seq = '" . $revision_seq . "' ";
					$this->db->query($sql);
					$result['revision_seq']	= $revision_seq;
			}else{
				$params['regist_date']		= date('Y-m-d H:i:s');
				$params['complete_date']	= date('Y-m-d H:i:s');
				$this->db->insert('fm_scm_stock_revision', $params);
				$result['revision_seq']	= $this->db->insert_id();

				// 조정 코드 가 없을 시 임의 코드 생성
				if	(!$params['revision_code']){
					unset($params);
					$params['revision_code']	= 'RS' . date('YmdHis') . $result['revision_seq'];
					$this->db->where(array('revision_seq' => $result['revision_seq']));
					$this->db->update('fm_scm_stock_revision', $params);
					$result['revision_code']	= $params['revision_code'];
				}
			}
		}

		return $result;
	}

	// 재고조정 상품 저장
	public function save_stock_revision_goods($revision, $goodsData, $exec){
		if	($this->chkScmConfig()){
			if	($revision['revision_seq'] > 0 && is_array($goodsData) && count($goodsData) > 0){
				// 입고 상품 데이터 저장
				if	($revision['revision_type'] != 'def'){
					$this->db->delete('fm_scm_stock_revision_goods', array('revision_seq' => $revision['revision_seq']));
				}
				foreach($goodsData as $g => $data){
					// 완료 처리 시 입고 처리
					if	($exec){
						$kind	= ($revision['revision_type'] == 'bad') ? 'revision_bad' : 'revision';
						if	(in_array($revision['revision_type'], array('out', 'bad')))	$type	= 'out';
						else															$type	= 'in';

						unset($params);
						$params['kind']							= $kind;
						$params['type']							= $type;
						$params['export_code']					= '';
						$params['return_code']					= '';
						$params['wh_seq']						= $revision['wh_seq'];
						$params['seq']							= $revision['revision_seq'];
						$params['trader_seq']					= '';
						$params['location_position']			= $data['location_position'];
						$params['location_code']				= $data['location_code'];
						$params['goods_seq']					= $data['goods_seq'];
						$params['goods_code']					= $data['goods_code'];
						$params['goods_name']					= $data['goods_name'];
						$params['option_type']					= $data['option_type'];
						$params['option_seq']					= $data['option_seq'];
						$params['option_name']					= $data['option_name'];
						$params['ea']							= $data['ea'];
						$params['sum_krw_supply_price']			= $data['krw_supply_price'];
						$chgResult	= $this->change_scm_stock($params);
						$data['location_position']				= $chgResult['location_position'];
						$data['location_code']					= $chgResult['location_code'];

						// 해당 상품 불량 재고 초기화 ( 해당 창고 불량재고만 초기화함 )
						if	($revision['revision_type'] == 'bad'){
							unset($whrParam, $upParams);
							$whrParam['wh_seq']				= $revision['wh_seq'];
							$whrParam['goods_seq']			= $data['goods_seq'];
							$whrParam['option_type']		= $data['option_type'];
							$whrParam['option_seq']			= $data['option_seq'];
							$upParams['bad_ea']				= '0';
							$this->save_location_link($upParams, $whrParam);
						}
					}

					unset($inParams);
					$inParams['revision_seq']					= $revision['revision_seq'];
					$inParams['goods_seq']						= $data['goods_seq'];
					$inParams['goods_code']						= $data['goods_code'];
					$inParams['goods_name']						= $data['goods_name'];
					$inParams['option_type']					= $data['option_type'];
					$inParams['option_seq']						= $data['option_seq'];
					$inParams['option_name']					= $data['option_name'];
					$inParams['use_tax']						= $data['use_tax'];
					$inParams['location_code']					= $data['location_code'];
					$inParams['location_position']				= $data['location_position'];
					$inParams['weight']							= $data['weight'];
					$inParams['ea']								= $data['ea'];
					$inParams['supply_price']					= $data['supply_price'];
					$inParams['freight_price']					= $data['freight_price'];
					$inParams['insurance_price']				= $data['insurance_price'];
					$inParams['cif_price']						= $data['cif_price'];
					$inParams['duty_price']						= $data['duty_price'];
					$inParams['accessorial_price']				= $data['accessorial_price'];
					$inParams['krw_supply_price']				= $data['krw_supply_price'];
					$inParams['supply_tax']						= $data['supply_tax'];
					$this->db->insert('fm_scm_stock_revision_goods', $inParams);

					$chgGoodsTarget[]	= array('goods_seq'		=> $data['goods_seq'],
												'option_type'	=> $data['option_type'],
												'option_seq'	=> $data['option_seq']);
				}

				if	( $exec && count($chgGoodsTarget) > 0){
					$this->save_ledger_today($revision['wh_seq'], $chgGoodsTarget);
					return array('target' => $chgGoodsTarget, 'wh' => array($revision['wh_seq']));
				}
			}
		}
	}

	// 재고조정 삭제 가능여부 체크
	public function chk_remove_revision($revision_seq){
		$bind[]				= $revision_seq;
		$result['status']	= true;

		$sql		= "select * from fm_scm_stock_revision where revision_seq = ? ";
		$query		= $this->db->query($sql, $bind);
		$revision	= $query->row_array();
		$result['revision_code']	= $revision['revision_code'];
		$result['msg']				= $revision['revision_code'] . '의 ';

		if	($data['revision_status']){
			$result['status']	= false;
			$result['msg']		.= '완료된 재고조정은 삭제가 불가능합니다.';
			return $result;
		}

		return $result;
	}

	// 재고조정 삭제
	public function remove_revision($revision_seq){
		$bind[]				= $revision_seq;

		// 재고조정 상품 제거
		$sql	= "delete from fm_scm_stock_revision_goods where revision_seq = ? ";
		$this->db->query($sql, $bind);

		// 재고조정 내역 제거
		$sql	= "delete from fm_scm_stock_revision where revision_seq = ? ";
		$this->db->query($sql, $bind);
	}

	// 임시 조정 정보 추출
	public function get_tmp_revision($tmp_seq, $goods_seq, $option_seq){
		$sql	= "select * from fm_tmp_scm_revision "
				. "where tmp_seq = ? and goods_seq = ? and option_seq = ? ";
		$query	= $this->db->query($sql, array($tmp_seq, $goods_seq, $option_seq));
		$result	= $query->result_array();

		return $result;
	}

	// 임시 조정 정보 생성
	public function create_tmp_revision($tmp_seq, $goods_seq, $option_seq, $data = array()){
		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');

		unset($insParams, $data['revision_seq']);
		if	($data)	$insParams		= $data;
		$insParams['tmp_seq']		= $tmp_seq;
		$insParams['goods_seq']		= $goods_seq;
		$insParams['option_seq']	= $option_seq;
		if	($insParams['wh_seq']){
			if	($insParams['wh_seq'] == 'none')			$insParams['wh_seq']			= '';
			if	($insParams['location_position'] == 'none')	$insParams['location_position']	= '';
			if	(!$insParams['stock'])						$insParams['stock']				= '1';
			if	(!$insParams['badstock'])					$insParams['badstock']			= '0';
			if	(!$insParams['supply_price'])				$insParams['supply_price']		= '0';
			$this->db->insert('fm_tmp_scm_revision', $insParams);
			$revision_seq				= $this->db->insert_id();
			$return						= $insParams;
			$return['revision_seq']		= $revision_seq;
		}

		return $return;
	}

	// 임시 조정 정보 삭제
	public function delete_tmp_revision($tmp_seq, $goods_seq, $option_seq = '', $revision_seq = ''){
		$where['tmp_seq']	= $tmp_seq;
		if	($goods_seq > 0)	$where['goods_seq']		= $goods_seq;
		if	($option_seq > 0)	$where['option_seq']	= $option_seq;
		if	($revision_seq > 0)	$where['revision_seq']	= $revision_seq;
		$this->db->where($where);
		$this->db->delete('fm_tmp_scm_revision');
	}

	// cell 단위 임시 저장 데이터 update
	public function tmp_save_cell_data($revision_seq, $updParams){
		// 창고가 변경되면 로케이션을 초기화 한다.
		if	($updParams['wh_seq'] > 0){
			if	(!$updParams['location_position'])	$updParams['location_position']	= '1-1-1';
		}

		$this->db->where(array('revision_seq' => $revision_seq));
		$this->db->update('fm_tmp_scm_revision', $updParams);
	}

	// 임시 데이터 컬럼 단위 일괄 변경
	public function tmp_save_all_revision($post){
		if	($post) foreach($post as $k => $v){
			if		($k == 'warehouse')	$k	= 'wh_seq';
			if	(in_array($k, array('location_w', 'location_l', 'location_h'))){
				$updParams['location_position']		= $post['location_w'] . '-' . $post['location_l']
													. '-' . $post['location_h'];
			}else{
				if	(in_array($k, array('tmp_seq', 'wh_seq', 'goods_seq')))		$whrParams[$k]	= $v;
				else															$updParams[$k]	= $v;
			}
		}

		$this->db->where($whrParams);
		$this->db->update('fm_tmp_scm_revision', $updParams);
	}

	// 임시 데이터로 기초재고 저장
	public function save_batch_revision($tmp_seq, $savedData){
		if	($this->scm_cfg['set_default_date']){
			$cDate	= $this->scm_cfg['set_default_date'];
			if	($savedData) foreach($savedData as $k => $data){
				$use_tax	= '비과세';
				if	($data['tax'] == 'tax')	$use_tax	= '과세';

				$this->db->from('fm_tmp_scm_revision');
				$this->db->where(array(	'tmp_seq'		=> $tmp_seq,
										'goods_seq'		=> $data['tmp_goods_seq'],
										'option_seq'	=> $data['tmp_option_seq']	));
				$revisionQuery	= $this->db->get();
				while ($revision = $revisionQuery->unbuffered_row('array')){
					// 로케이션 정보가 없을 시 추출
					unset($locationData);
					if	($location[$revision['wh_seq']]){
						$locationData	= $location[$revision['wh_seq']];
					}else{
						unset($locSc);
						$locSc['wh_seq']				= $revision['wh_seq'];
						$locationData					= $this->get_location($locSc);
						$location[$revision['wh_seq']]	= $locationData;
					}
					if	(!$revision['location_position'])	$revision['location_position']	= '1-1-1';
					$tmpArr			= explode('-', $revision['location_position']);
					$location_code	= $locationData[$tmpArr[0]][$tmpArr[1]][$tmpArr[2]]['location_code'];
					if	(!$data['option_name'])	$data['option_name']	= '';

					// 재고조정 내역 추가
					unset($revisionParams);
					$revisionParams['goods_seq']			= $data['goods_seq'];
					$revisionParams['goods_code']			= $data['goods_code'];
					$revisionParams['goods_name']			= $data['goods_name'];
					$revisionParams['option_type']			= $data['option_type'];
					$revisionParams['option_seq']			= $data['option_seq'];
					$revisionParams['option_name']			= $data['option_name'];
					$revisionParams['location_position']	= $revision['location_position'];
					$revisionParams['location_code']		= $location_code;
					$revisionParams['use_tax']				= $use_tax;
					$revisionParams['supply_price_type']	= 'KRW';
					$revisionParams['ea']					= $revision['stock'];
					$revisionParams['supply_price']			= $revision['supply_price'];
					$revisionParams['bad_ea']				= $revision['badstock'];
					if	($revisionData[$revision['wh_seq']]){
						$revisionData[$revision['wh_seq']]		= $this->save_default_revision($revision['wh_seq'], $revisionParams, $revisionData[$revision['wh_seq']]['revision_seq'], $revisionData[$revision['wh_seq']]);
					}else{
						$revisionData[$revision['wh_seq']]		= $this->save_default_revision($revision['wh_seq'], $revisionParams);
					}
					$targetGoods					= array('goods_seq'		=> $data['goods_seq'],
															'option_type'	=> $data['option_type'],
															'option_seq'	=> $data['option_seq']);
					$chgGoodsTarget[$revision['wh_seq']]['complete_date']	= $cDate;
					$chgGoodsTarget[$revision['wh_seq']]['goods'][]			= $targetGoods;
				}
			}
			$this->after_save_for_default_revision($chgGoodsTarget);

			return $chgGoodsTarget;
		}

		return false;
	}

	########## ↑↑ 재고조정 ↑↑ ##### ↓↓ 재고이동 ↓↓ ##########

	// 재고이동 상품
	public function get_stock_move_goods($sc){
		$selectSql	= "select fcs.* ";
		$fromSql	= "from fm_scm_stock_move_goods as fcs ";
		$whereSql	= "where fcs.move_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by fcs.move_seq, fcs.goods_seq, fcs.option_type, fcs.option_seq ";

		// 상품 요약 정보
		if	($sc['get_type'] == 'summary'){
			$selectSql	= "select *, count(*) as cnt, SUM(IFNULL(fcs.ea, 0)) as tea ";
			$groupBy	= "group by fcs.move_seq ";
		}
		// 조정 고유번호 검색
		if	($sc['move_seq']){
			$whereSql	.= " and fcs.move_seq = ? ";
			$addBind[]	= $sc['move_seq'];
		}

		// 이전 창고에 해당상품의 수량 정보
		if($sc['scm_move']){
			$selectSql	.= ", sll.ea as location_ea, sll.bad_ea as location_badea, "
						. "sll.supply_price as location_supply_price ";
			$fromSql	.= " left join fm_scm_location_link as sll
						    on fcs.goods_seq = sll.goods_seq
							and fcs.option_seq = sll.option_seq
							and fcs.option_type = sll.option_type
							and wh_seq = '".$sc['out_whs_seq']."' ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 재고이동 목록
	public function get_stock_move($sc){
		$selectSql	= "select ssm.* ";
		$fromSql	= "from fm_scm_stock_move	as ssm ";
		$whereSql	= "where ssm.move_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by ssm.move_seq desc ";

		// 이동 고유번호 검색
		if		($sc['move_seq']){
			$whereSql	.= " and ssm.move_seq = ? ";
			$addBind[]	= $sc['move_seq'];
		}	
		if		(isset($sc['sc_status']) && trim($sc['sc_status']) != '' ){
			if	(!is_array($sc['sc_status']))	$sc['sc_status']	= array($sc['sc_status']);
			$whereSql	.= " and ssm.move_status in ('" . implode("', '", $sc['sc_status']) . "')";
		}
		// 출고 창고 검색
		if		($sc['sc_out_wh_seq']){
			$whereSql	.= " and ssm.out_wh_seq = ? ";
			$addBind[]	= $sc['sc_out_wh_seq'];
		}
		// 입고 창고 검색
		if		($sc['sc_in_wh_seq']){
			$whereSql	.= " and ssm.in_wh_seq = ? ";
			$addBind[]	= $sc['sc_in_wh_seq'];
		}
		// 이동 일자 검색
		if		($sc['sc_date_fld'] == 'regist'){
			if		($sc['sc_sdate'] && $sc['sc_edate']){
				$whereSql	.= " and ssm.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' "
							. " and ssm.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_sdate']){
				$whereSql	.= " and ssm.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_edate']){
				$whereSql	.= " and ssm.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}
		}elseif	($sc['sc_date_fld'] == 'complete'){
			if		($sc['sc_sdate'] && $sc['sc_edate']){
				$whereSql	.= " and ssm.move_status = '1' "
							. " and ssm.complete_date >= '" . $sc['sc_sdate'] . " 00:00:00' "
							. " and ssm.complete_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_sdate']){
				$whereSql	.= " and ssm.move_status = '1' "
							. " and ssm.complete_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_edate']){
				$whereSql	.= " and ssm.move_status = '1' "
							. " and ssm.complete_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}
		}
		// 상품번호 검색
		if	($sc['sc_goods_seq']){
			$fromSql		.= "INNER JOIN fm_scm_stock_move_goods	as ssmg "
							. "ON ssm.move_seq = ssmg.move_seq ";
			$whereSql		.= " and ssmg.goods_seq like '" . addslashes(trim($sc['sc_goods_seq'])) . "%' ";
			$groupBy		= "group by ssm.move_seq ";
		}
		// 상품명 검색
		if	($sc['sc_goods_name']){
			$fromSql		.= "INNER JOIN fm_scm_stock_move_goods	as ssmg "
							. "ON ssm.move_seq = ssmg.move_seq ";
			$whereSql		.= " and ssmg.goods_name like '%" . addslashes(trim($sc['sc_goods_name'])) . "%' ";
			$groupBy		= "group by ssm.move_seq ";
		}
		// 상품코드 검색
		if	($sc['sc_goods_code']){
			$fromSql		.= "INNER JOIN fm_scm_stock_move_goods	as ssmg "
							. "ON ssm.move_seq = ssmg.move_seq ";
			$whereSql		.= " and LOWER(ssmg.goods_code) like '%" . addslashes(strtolower(trim($sc['sc_goods_code']))) . "%' ";
			$groupBy		= "group by ssm.move_seq ";
		}
		// 키워드 검색
		if	($sc['keyword']){
			$sc['keyword']	= addslashes(trim($sc['keyword']));
			$fromSql		.= "INNER JOIN fm_scm_stock_move_goods	as ssmg "
							. "ON ssm.move_seq = ssmg.move_seq ";
			$whereSql		.= " and ( ssmg.goods_seq like '" . $sc['keyword'] . "%' "
							. " or ssmg.goods_name like '%" . $sc['keyword'] . "%' "
							. " or LOWER(ssmg.goods_code) like '%" . strtolower($sc['keyword']) . "%' ) ";
			$groupBy		= "group by ssm.move_seq ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		$sqlQuery['select']		= $selectSql;
		$sqlQuery['fromSql']	= $fromSql;
		$sqlQuery['whereSql']	= $whereSql;
		$sqlQuery['groupBy']	= $groupBy;
		$sqlQuery['orderBy']	= $orderBy;
		$sqlQuery['addBind']	= $addBind;
		$sqlQuery['countSql']	= "ssm.move_seq > 0 ";

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= pagingScmNumbering($sqlQuery,$sc);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 재고이동 parameter 체크
	public function chk_stockmove_param($params){
		$move_seq					= trim($params['move_seq']);
		$move_code					= trim($params['move_code']);
		$move_status				= trim($params['move_status']);
		$out_wh_seq					= trim($params['out_wh_seq']);
		$in_wh_seq					= trim($params['in_wh_seq']);
		$admin_memo					= addslashes(trim($params['admin_memo']));
		$option						= $params['option_seq'];
		$currency					= 'KRW';
		$exec						= '0';

		// 기존 이동 정보 추출
		if		($move_seq > 0){
			unset($sc);
			$sc['move_seq']		= $move_seq;
			$old_move			= $this->get_stock_move($sc);
			$old_move			= $old_move[0];
			if		($old_move['move_status'] != $move_status){
				if	($old_move['move_status'] == 1)	$exec	= '3';
				else								$exec	= $move_status;
			}
			if	($old_move['move_status'] == 1){
				// 기존 상품 정보 추출
				unset($sc);
				$sc['move_seq']		= $move_seq;
				$goodsData			= $this->get_stock_move_goods($sc);
			}
		}else{
			$exec				= $move_status;
		}

		if	(!$old_move['move_status']){

			// 출고창고 체크
			if	(!$out_wh_seq){
				openDialogAlert('출고창고를 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}
			// 입고창고 체크
			if	(!$in_wh_seq){
				openDialogAlert('입고창고를 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}

			// 상품 parameter 배열 생성
			if	(count($option) > 0){
				foreach($option as $idx => $opt){
					$goods_seq				= trim($params['goods_seq'][$idx]);
					$order_krw_supply_price	= trim($params['order_krw_supply_price'][$idx]);
					$tmp					= explode('|', $opt);
					$option_type			= $tmp[0];
					$option_seq				= $tmp[1];
					if	($option_seq > 0){
						if	(!trim($params['ea'][$idx]) || trim($params['ea'][$idx]) < 0){
							openDialogAlert('이동 수량은 1개 이상이어야 합니다.', 400, 150, 'parent', '');
							exit;
						}
						// 옵션 존재여부 체크
						if	(!$this->chk_option_exists($goods_seq, $option_type, $option_seq)){
							$msg	= $params['goods_name'][$idx] . ' ' . $params['option_name'][$idx]
									. '은 존재하지 않는 상품입니다.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}

						unset($supplyParams, $supplyInfo);
						$supplyParams['currency']		= $currency;
						$supplyParams['ea']				= trim($params['ea'][$idx]);
						$supplyParams['supply_price']	= trim($params['supply_price'][$idx]);
						$supplyInfo						= $this->calculate_supply_info($supplyParams);

						// 출고창고 정보 추출
						unset($sc);
						$sc['wh_seq']				= $out_wh_seq;
						$sc['goods_seq']			= $goods_seq;
						$sc['option_type']			= $option_type;
						$sc['option_seq']			= $option_seq;
						$out_wh_goods_info			= $this->get_location_goods($sc);
						$out_wh_goods_info			= $out_wh_goods_info[0];
						if	($out_wh_goods_info['ea'] < $supplyInfo['ea']){
							openDialogAlert('이동 수량은 출고창고의 재고를 초과할 수 없습니다.', 400, 150, 'parent', '');
							exit;
						}
						$out_location_code			= $out_wh_goods_info['location_code'];
						$out_location_position		= $out_wh_goods_info['location_position'];
						$out_bf_stock				= $out_wh_goods_info['ea'];
						$out_bf_badstock			= $out_wh_goods_info['bad_ea'];
						$out_af_stock				= $out_bf_stock - $supplyInfo['ea'];
						$out_af_badstock			= $out_bf_badstock;

						$in_location_code			= trim($params['in_location_code'][$idx]);
						$in_location_position		= trim($params['in_location_position'][$idx]);
						$in_bf_stock				= '0';
						$in_bf_badstock				= '0';

						// 입고창고 정보 추출
						unset($sc);
						$sc['wh_seq']				= $in_wh_seq;
						$sc['goods_seq']			= $goods_seq;
						$sc['option_type']			= $option_type;
						$sc['option_seq']			= $option_seq;
						$in_wh_goods_info			= $this->get_location_goods($sc);
						$in_wh_goods_info			= $in_wh_goods_info[0];

						// 입출고 창고 동일하지 않는 경우에만 in_location_position 다시 정의
						if ($in_wh_seq !== $out_wh_seq) {
							if ($in_wh_goods_info['goods_seq'] > 0) {
								$in_location_code = $in_wh_goods_info['location_code'];
								$in_location_position = $in_wh_goods_info['location_position'];
								$in_bf_stock = $in_wh_goods_info['ea'];
								$in_bf_badstock = $in_wh_goods_info['bad_ea'];
							}
						}

						$in_af_stock				= $in_bf_stock + $supplyInfo['ea'];
						$in_af_badstock				= $in_bf_badstock;

						// 입고창고 로케이션 정보 체크
						if	(!$in_location_code || !$in_location_position){
							openDialogAlert('입고창고 로케이션을 지정해 주세요.', 400, 150, 'parent', '');
							exit;
						}

						// 입고창고 출고창고 동일할 시 전체 수량 신청체크
						if ($in_wh_seq == $out_wh_seq) {
							if ($params['ea'][$idx] != $out_wh_goods_info['ea']) {
								openDialogAlert('출고창고와 입고창고가 동일한 경우 총 수량만 이동 가능합니다.', 400, 150, 'parent', '');
								exit;
							}
							if ($in_location_position == $out_location_position) {
								openDialogAlert('출고창고와 입고창고가 동일한 경우 다른 로케이션으로 이동 가능합니다.', 400, 150, 'parent', '');
								exit;
							}
						}

  						// 상품정보
						unset($data);
						$data['goods_seq']					= $goods_seq;
						$data['option_type']				= $option_type;
						$data['option_seq']					= $option_seq;
						$data['goods_code']					= trim($params['goods_code'][$idx]);
						$data['goods_name']					= trim($params['goods_name'][$idx]);
						$data['option_name']				= trim($params['option_name'][$idx]);
						$data['out_location_code']			= $out_location_code;
						$data['out_location_position']		= $out_location_position;
						$data['in_location_code']			= $in_location_code;
						$data['in_location_position']		= $in_location_position;
						$data['out_bf_stock']				= $out_bf_stock;
						$data['out_bf_badstock']			= $out_bf_badstock;
						$data['in_bf_stock']				= $in_bf_stock;
						$data['in_bf_badstock']				= $in_bf_badstock;
						$data['out_af_stock']				= $out_af_stock;
						$data['out_af_badstock']			= $out_af_badstock;
						$data['in_af_stock']				= $in_af_stock;
						$data['in_af_badstock']				= $in_af_badstock;
						$data['ea']							= $supplyInfo['ea'];
						$data['supply_price']				= $supplyInfo['supply_price'];
						$data['krw_supply_price']			= $supplyInfo['krw_supply_price'];
						$goodsData[]						= $data;

						// 합계정보
						$total_ea							+= $supplyInfo['ea'];
						$krw_total_price					+= $supplyInfo['krw_supply_price'];
					}
				}
			}

			// 입고창고 체크
			if	(!(count($goodsData) > 0)){
				openDialogAlert('이동할 상품이 없습니다.<br/>아래 조건을 만족하지 않는 상품은 이동 상품에서 제외됩니다.<br/><br/>상품,옵션,입고로케이션,수량 중 한가지라도 없는 상품<br/>수량이 출고창고수량 보다 많은 상품', 450, 230, 'parent', '');
				exit;
			}

			// 기본정보
			$stockmove['move_code']					= $move_code;
			$stockmove['move_status']				= $move_status;
			$stockmove['in_wh_seq']					= $in_wh_seq;
			$stockmove['out_wh_seq']				= $out_wh_seq;
			$stockmove['total_ea']					= $total_ea;
			$stockmove['krw_total_price']			= $krw_total_price;
			$stockmove['complete_date']				= date('Y-m-d H:i:s');
		}
		if	($exec == '3'){
			// 기본정보
			$stockmove['move_code']					= $old_move['move_code'];
			$stockmove['move_status']				= '2';
			$stockmove['in_wh_seq']					= $old_move['in_wh_seq'];
			$stockmove['out_wh_seq']				= $old_move['out_wh_seq'];
			$stockmove['total_ea']					= $old_move['total_ea'];
			$stockmove['krw_total_price']			= $old_move['krw_total_price'];
		}
		if	($exec == '2' || $exec == '3'){
			$stockmove['complete_date']				= date('Y-m-d H:i:s');
		}
		$stockmove['admin_memo']					= $admin_memo;

		if		($exec == '2' || $exec == '3'){
			$stockmove['chg_log']		= '<div>' . date('Y-m-d H:i:s') . ' '
										. $this->managerInfo['mname']
										. '(' . $this->managerInfo['manager_id'] . ')가 '
										. '재고를 이동하였습니다. '
										. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		}elseif	($exec == '1'){
			$stockmove['chg_log']		= '<div>' . date('Y-m-d H:i:s') . ' '
										. $this->managerInfo['mname']
										. '(' . $this->managerInfo['manager_id'] . ')가 '
										. '재고를 이동중 처리하였습니다. '
										. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		}else{
			if	($move_seq){
				$stockmove['chg_log']	= '<div>' . date('Y-m-d H:i:s') . ' '
										. $this->managerInfo['mname']
										. '(' . $this->managerInfo['manager_id'] . ')가 '
										. '재고이동내역을 수정하였습니다. '
										. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
			}else{
				$stockmove['chg_log']	= '<div>' . date('Y-m-d H:i:s') . ' '
										. $this->managerInfo['mname']
										. '(' . $this->managerInfo['manager_id'] . ')가 '
										. '재고이동내역을 등록하였습니다. '
										. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
			}
		}

		return array('move_seq'		=> $move_seq,	'exec'		=> $exec,
					'stockmove'		=> $stockmove,	'goodsData'	=> $goodsData);
	}

	// 재고이동 기본정보 저장
	public function save_stockmove($params, $move_seq = ''){
		if	($this->chkScmConfig()){
			if	($move_seq > 0){
				$sql	= "update fm_scm_stock_move set ";
				foreach($params as $fld => $val){
					if	(in_array($fld, array('chg_log')))
						$setSql[]	= $fld . " = concat(IFNULL(" . $fld . ", ''), '" . $val . "') ";
					else
						$setSql[]	= $fld . " = '" . $val . "' ";
				}
				$sql	.= implode(", ", $setSql) . " ";
				$sql	.= "where move_seq = '" . $move_seq . "' ";
				$this->db->query($sql);
			}else{
				$params['regist_date']		= date('Y-m-d H:i:s');
				$this->db->insert('fm_scm_stock_move', $params);
				$move_seq	= $this->db->insert_id();

				// 이동코드 가 없을 시 임의 코드 생성
				if	(!$params['move_code']){
					unset($params);
					$params['move_code']			= 'MC' . date('YmdHis') . $move_seq;
					$this->db->where(array('move_seq' => $move_seq));
					$this->db->update('fm_scm_stock_move', $params);
				}
			}
		}

		return $move_seq;
	}

	// 재고이동 상품 이동 처리
	public function save_stockmove_goods($move_seq, $moveData, $goodsData, $exec = ''){

		if	($this->chkScmConfig()){

			if	($move_seq > 0 && is_array($goodsData) && count($goodsData) > 0){
				$in_wh_seq		= $moveData['in_wh_seq'];
				$out_wh_seq		= $moveData['out_wh_seq'];
				// 이동중 처리 시 이동창고 정보 추출
				if	($exec == '1' || $exec == '3'){
					unset($sc);
					$sc['inclusion_move']				= 'Y';
					$sc['sc_wh_move']					= 'Y';
					$move_wh							= $this->get_warehouse($sc);
					$move_wh							= $move_wh[0];

					if(!$move_wh) {
						// 이동 창고 없으면 생성 후 다시 조회
						$this->save_move_warehouse();
						$move_wh						= $this->get_warehouse($sc);
						$move_wh						= $move_wh[0];
					}
				}

				$this->db->delete('fm_scm_stock_move_goods', array('move_seq' => $move_seq));
				foreach($goodsData as $k => $data){
					// 출고창고 출고처리
					if		($exec == '1' || $exec == '2'){
						unset($params);
						$params['kind']							= 'stockmove';
						$params['type']							= 'out';
						$params['export_code']					= '';
						$params['return_code']					= '';
						$params['seq']							= $move_seq;
						$params['wh_seq']						= $out_wh_seq;
						$params['trader_seq']					= '';
						$params['location_position']			= $data['out_location_position'];
						$params['location_code']				= $data['out_location_code'];
						$params['goods_seq']					= $data['goods_seq'];
						$params['goods_code']					= $data['goods_code'];
						$params['goods_name']					= $data['goods_name'];
						$params['option_type']					= $data['option_type'];
						$params['option_seq']					= $data['option_seq'];
						$params['option_name']					= $data['option_name'];
						$params['ea']							= $data['ea'];
						$params['sum_krw_supply_price']			= $data['krw_supply_price'];
						$chgResult	= $this->change_scm_stock($params);
					}
					// 이동창고 입고처리
					if		($exec == '1'){
						unset($params);
						$params['kind']							= 'stockmove';
						$params['type']							= 'in';
						$params['export_code']					= '';
						$params['return_code']					= '';
						$params['seq']							= $move_seq;
						$params['wh_seq']						= $move_wh['wh_seq'];
						$params['trader_seq']					= '';
						$params['location_position']			= $move_seq;
						$params['location_code']				= $move_seq;
						$params['goods_seq']					= $data['goods_seq'];
						$params['goods_code']					= $data['goods_code'];
						$params['goods_name']					= $data['goods_name'];
						$params['option_type']					= $data['option_type'];
						$params['option_seq']					= $data['option_seq'];
						$params['option_name']					= $data['option_name'];
						$params['ea']							= $data['ea'];
						$params['sum_krw_supply_price']			= $data['krw_supply_price'];
						$chgResult	= $this->change_scm_stock($params);
					}
					// 이동창고 출고처리
					if		($exec == '3'){
						unset($params);
						$params['kind']							= 'stockmove';
						$params['type']							= 'out';
						$params['export_code']					= '';
						$params['return_code']					= '';
						$params['seq']							= $move_seq;
						$params['wh_seq']						= $move_wh['wh_seq'];
						$params['trader_seq']					= '';
						$params['location_position']			= $move_seq;
						$params['location_code']				= $move_seq;
						$params['goods_seq']					= $data['goods_seq'];
						$params['goods_code']					= $data['goods_code'];
						$params['goods_name']					= $data['goods_name'];
						$params['option_type']					= $data['option_type'];
						$params['option_seq']					= $data['option_seq'];
						$params['option_name']					= $data['option_name'];
						$params['ea']							= $data['ea'];
						$params['sum_krw_supply_price']			= $data['krw_supply_price'];
						$params['chk_linclusion_seq']			= $move_seq;
						$chgResult	= $this->change_scm_stock($params);
					}
					// 입고창고 입고처리
					if		($exec == '2' || $exec == '3'){
						unset($params);
						$params['kind']							= 'stockmove';
						$params['type']							= 'in';
						$params['export_code']					= '';
						$params['return_code']					= '';
						$params['seq']							= $move_seq;
						$params['wh_seq']						= $in_wh_seq;
						$params['trader_seq']					= '';
						$params['location_position']			= $data['in_location_position'];
						$params['location_code']				= $data['in_location_code'];
						$params['goods_seq']					= $data['goods_seq'];
						$params['goods_code']					= $data['goods_code'];
						$params['goods_name']					= $data['goods_name'];
						$params['option_type']					= $data['option_type'];
						$params['option_seq']					= $data['option_seq'];
						$params['option_name']					= $data['option_name'];
						$params['ea']							= $data['ea'];
						$params['sum_krw_supply_price']			= $data['krw_supply_price'];
						$chgResult	= $this->change_scm_stock($params);
					}

					// 이동 상품 저장
					unset($insParams);
					$insParams['move_seq']				= $move_seq;
					$insParams['goods_seq']				= $data['goods_seq'];
					$insParams['goods_name']			= $data['goods_name'];
					$insParams['goods_code']			= $data['goods_code'];
					$insParams['option_type']			= $data['option_type'];
					$insParams['option_seq']			= $data['option_seq'];
					$insParams['option_name']			= $data['option_name'];
					$insParams['out_location_code']		= $data['out_location_code'];
					$insParams['out_location_position']	= $data['out_location_position'];
					$insParams['in_location_position']	= $data['in_location_position'];
					$insParams['in_location_code']		= $data['in_location_code'];
					$insParams['out_bf_stock']			= $data['out_bf_stock'];
					$insParams['out_bf_badstock']		= $data['out_bf_badstock'];
					$insParams['out_af_stock']			= $data['out_af_stock'];
					$insParams['out_af_badstock']		= $data['out_af_badstock'];
					$insParams['in_bf_stock']			= $data['in_bf_stock'];
					$insParams['in_bf_badstock']		= $data['in_bf_badstock'];
					$insParams['in_af_stock']			= $data['in_af_stock'];
					$insParams['in_af_badstock']		= $data['in_af_badstock'];
					$insParams['ea']					= $data['ea'];
					$insParams['supply_price']			= $data['supply_price'];
					$insParams['krw_supply_price']		= $data['krw_supply_price'];
					$this->db->insert('fm_scm_stock_move_goods', $insParams);

					$chgGoodsTarget[]	= array('goods_seq'		=> $data['goods_seq'],
												'option_type'	=> $data['option_type'],
												'option_seq'	=> $data['option_seq']);
				}


				if	($exec && count($chgGoodsTarget) > 0){
					$this->save_ledger_today($out_wh_seq, $chgGoodsTarget);
					$this->save_ledger_today($in_wh_seq, $chgGoodsTarget);
					return array('target' => $chgGoodsTarget, 'wh' => array($out_wh_seq, $in_wh_seq));
				}
			}
		}
	}

	// 재고이동 임시 창고 생성
	public function save_move_warehouse() {
		// 이동창고 생성
		$whParams['wh_default']				= '0';
		$whParams['wh_move']				= '1';
		$whParams['wh_name']				= '이동창고';
		$mv_wh_seq							= $this->save_warehouse($whParams);

		$lcParams[0]['location_position']	= '1-1-1';
		$lcParams[0]['location_x']			= '1';
		$lcParams[0]['location_y']			= '1';
		$lcParams[0]['location_z']			= '1';
		$lcParams[0]['location_code']		= '1-1-1';
		$lcParams[0]['location_w']			= '1';
		$lcParams[0]['location_l']			= '1';
		$lcParams[0]['location_h']			= '1';

		// 이동창고 로케이션 생성
		$this->save_location($lcParams, $mv_wh_seq);
	}

	// 재고이동 삭제 가능 여부 체크
	public function chk_remove_stockmove($move_seq){
		$bind[]				= $move_seq;
		$result['status']	= true;

		$sql		= "select * from fm_scm_stock_move where move_seq = ? ";
		$query		= $this->db->query($sql, $bind);
		$revision	= $query->row_array();
		$result['revision_code']	= $revision['revision_code'];
		$result['msg']				= $revision['revision_code'] . '의 ';

		if	($data['move_status']){
			$result['status']	= false;
			$result['msg']		.= '완료된 재고이동은 삭제가 불가능합니다.';
			return $result;
		}

		return $result;
	}

	// 재고이동 삭제
	public function remove_stockmove($move_seq){
		$bind[]				= $move_seq;

		// 재고이동 상품 제거
		$sql	= "delete from fm_scm_stock_move_goods where move_seq = ? ";
		$this->db->query($sql, $bind);

		// 재고이동 내역 제거
		$sql	= "delete from fm_scm_stock_move where move_seq = ? ";
		$this->db->query($sql, $bind);
	}

	########## ↑↑ 재고이동 ↑↑ ##### ↓↓ 발주 ↓↓ ##########

	// 발주 목록
	public function get_sorder_list($sc){
		$selectSql	= "select so.* ";
		$fromSql	= "from fm_scm_order	as so ";
		$whereSql	= "where so.sorder_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by so.sorder_seq desc ";

		// 발주 고유번호 검색
		if		($sc['sorder_seq']){
			$whereSql	.= " and so.sorder_seq = ? ";
			$addBind[]	= $sc['sorder_seq'];
		}elseif	($sc['sorder_seq_arr']){
			if	(!is_array($sc['sorder_seq_arr']))	$sc['sorder_seq_arr']	= array($sc['sorder_seq_arr']);
			$whereSql	.= " and so.sorder_seq in ('" . implode("', '", $sc['sorder_seq_arr']) ."') ";
		}elseif	($sc['sc_sorder_seq']){
			$whereSql	.= " and so.sorder_seq like '" . $sc['sc_sorder_seq'] ."%' ";
		}
		// 발주코드 검색
		if		($sc['sorder_code']){
			$whereSql	.= " and LOWER(so.sorder_code) = ? ";
			$addBind[]	= $sc['sorder_code'];
		}elseif	($sc['sc_sorder_code']){
			$whereSql	.= " and LOWER(so.sorder_code) like '%" . addslashes(strtolower(trim($sc['sc_sorder_code']))) . "%' ";
		}
		// 상태 검색
		if		($sc['sc_sorder_status']!=""){
			if	($sc['sc_sorder_status'] == 'modify'){
				$whereSql	.= " and so.modified_num > 0 ";
			} else if($sc['sc_sorder_status']!="") {
				if	(!is_array($sc['sc_sorder_status']))	$sc['sc_sorder_status']	= array($sc['sc_sorder_status']);
				$whereSql	.= " and so.sorder_status in ('" . implode("', '", $sc['sc_sorder_status']) . "') ";
			}
		}

		if	($sc['remain_whs'] == 'Y'){
			$whereSql	.= " and so.sorder_status > 0 ";
		}
		// 구분 검색
		if		($sc['sc_sorder_type']){
			if	(!is_array($sc['sc_sorder_type']))	$sc['sc_sorder_type']	= array($sc['sc_sorder_type']);
			$whereSql	.= " and so.sorder_type in ('" . implode("', '", $sc['sc_sorder_type']) . "') ";
		}
		// 거래처 검색
		if		($sc['sc_trader']){
			$whereSql	.= " and so.trader_seq = ? ";
			$addBind[]	= $sc['sc_trader'];
		}
		// 발주 일자 검색
		if		($sc['sc_date_fld'] == 'regist'){
			if		($sc['sc_sdate'] && $sc['sc_edate']){
				$whereSql	.= " and so.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' "
							. " and so.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_sdate']){
				$whereSql	.= " and so.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_edate']){
				$whereSql	.= " and so.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}
		}elseif	($sc['sc_date_fld'] == 'complete'){
			if		($sc['sc_sdate'] && $sc['sc_edate']){
				$whereSql	.= " and so.sorder_status = '1' "
							. " and so.complete_date >= '" . $sc['sc_sdate'] . " 00:00:00' "
							. " and so.complete_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_sdate']){
				$whereSql	.= " and so.sorder_status = '1' "
							. " and so.complete_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_edate']){
				$whereSql	.= " and so.sorder_status = '1' "
							. " and so.complete_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}
		}
		// 수정발주 검색
		if		(!$sc['sc_sorder_status'] && $sc['sc_modified'] == 'Y'){
			$whereSql	.= " and so.modified_num > 0 ";
		}
		// JOIN 테이블 중복 문제로 테이블을 Unique 하게 Join 처리
		if	($sc['sc_goods_seq'] || $sc['sc_goods_name'] || $sc['sc_goods_code'] || $sc['keyword'] || $sc['remain_whs'] == 'Y'){
			$fromSql		.= " INNER JOIN fm_scm_order_goods	as sog "
							. " ON (so.sorder_seq = sog.sorder_seq ";
			if	($sc['remain_whs'] == 'Y'){
				//$fromSql	.= "  AND (sog.ea - sog.whs_ea) > 0 ";
				if	(!$sc['sc_sorder_status']){
					$whereSql	.= " and so.sorder_status > 0 ";
				}
			}
			$fromSql		.= " )";
		}
		// 상품번호 검색
		if	($sc['sc_goods_seq']){
			$whereSql		.= " and sog.goods_seq like '" . addslashes(trim($sc['sc_goods_seq'])) . "%' ";
			$groupBy		= "group by so.sorder_seq ";
		}
		// 상품명 검색
		if	($sc['sc_goods_name']){
			$whereSql		.= " and sog.goods_name like '%" . addslashes(trim($sc['sc_goods_name'])) . "%' ";
			$groupBy		= "group by so.sorder_seq ";
		}
		// 상품코드 검색
		if	($sc['sc_goods_code']){
			$whereSql		.= " and LOWER(sog.goods_code) like '%" . addslashes(strtolower(trim($sc['sc_goods_code']))) . "%' ";
			$groupBy		= "group by so.sorder_seq ";
		}
		
		// 키워드 검색
		if	($sc['keyword']){
			$sc['keyword']	= addslashes(trim($sc['keyword']));
			$whereSql		.= " and ( sog.goods_seq like '" . $sc['keyword'] . "%' "
							. " or sog.goods_name like '%" . $sc['keyword'] . "%' "
							. " or LOWER(so.sorder_code) like '%" . $sc['keyword'] . "%' "
							. " or LOWER(sog.goods_code) like '%" . strtolower($sc['keyword']) . "%' ) ";
			$groupBy		= "group by so.sorder_seq ";
		}
		// 미입고 발주건 조회 sorder_status 
		if	($sc['remain_whs'] == 'Y'){
			$selectSql		.= ", sog.goods_name as goods_name "
							. ", count(sog.sorder_seq)		as cnt "
							. ", SUM(IFNULL(sog.ea, 0))		as oea "
							. ", SUM(IFNULL(sog.whs_ea, 0)) as wea ";
			$groupBy		= "group by so.sorder_seq having (oea - wea) > 0 ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		$sqlQuery['select']		= $selectSql;
		$sqlQuery['fromSql']	= $fromSql;
		$sqlQuery['whereSql']	= $whereSql;
		$sqlQuery['groupBy']	= $groupBy;
		$sqlQuery['orderBy']	= $orderBy;
		$sqlQuery['addBind']	= $addBind;
		$sqlQuery['countSql']	= "so.sorder_seq > 0";

		//2016.07.20 페이징 기능 추가 pjw (script 페이징)
		if	($sc['page'] > 0 && $sc['perpage'] > 0 && $sc['pagingType'] == 'script'){
			$result		= select_script_page($sc['perpage'], $sc['page'], 10, $sql, $addBind, $sc['script']);
		}else if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= pagingScmNumbering($sqlQuery,$sc);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 발주 상품
	public function get_sorder_goods($sc){
		$selectSql	= "select sog.* ";
		$fromSql	= "from fm_scm_order_goods	as sog ";
		$whereSql	= "where sog.sorder_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by sog.sorder_seq";

		// 상품 요약 정보
		if	($sc['get_type'] == 'summary'){
			$selectSql	= "select *, count(*) as cnt, SUM(IFNULL(ea, 0)) as tea, "
						. "SUM(IFNULL(whs_ea, 0)) as wtea ";
			$groupBy	= "group by sog.sorder_seq ";
		}
		// 조정 고유번호 검색
		if	($sc['sorder_seq']){
			$whereSql	.= " and sog.sorder_seq = ? ";
			$addBind[]	= $sc['sorder_seq'];
		}
		// 상품번호 검색
		if	($sc['goods_seq']){
			$whereSql	.= " and sog.goods_seq = ? ";
			$addBind[]	= $sc['goods_seq'];
		}
		// 옵션 종류 검색
		if	($sc['option_type']){
			$whereSql	.= " and sog.option_type = ? ";
			$addBind[]	= $sc['option_type'];
		}
		// 옵션 번호 검색
		if	($sc['option_seq']){
			$whereSql	.= " and sog.option_seq = ? ";
			$addBind[]	= $sc['option_seq'];
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 발주 parameter 체크
	public function chk_sorder_param($params){

		$sorder_seq					= trim($params['sorder_seq']);
		$sorder_status				= trim($params['sorder_status']);
		$sorder_type				= trim($params['sorder_type']);
		$trader_seq					= trim($params['trader_seq']);
		$freight					= trim($params['inclusion_freight']);
		$insurance					= trim($params['inclusion_insurance']);
		$in_wh_seq					= trim($params['in_wh_seq']);
		$goods_exchange				= trim($params['goods_exchange']);
		$fi_exchange				= trim($params['fi_exchange']);
		$shipping_date				= trim($params['shipping_date']);
		$payment_date				= trim($params['payment_date']);
		$admin_memo					= addslashes(trim($params['admin_memo']));
		$aooSeq						= $params['aooSeq'];
		$option						= $params['option_seq'];
		$trade_terms				= $this->get_trade_terms($freight, $insurance);
		$total_ea					= 0;
		$total_supply_price			= 0;
		$total_freight_price		= 0;
		$total_insurance_price		= 0;
		$total_cif_price			= 0;
		$total_duty_price			= 0;
		$total_accessorial_price	= 0;
		$krw_total_supply_price		= 0;
		$krw_total_supply_tax		= 0;
		$krw_total_price			= 0;
		$aooSeqArr					= array();
		$exec						= false;

		// 거래처 체크
		if	(!$trader_seq){
			openDialogAlert('거래처를 선택해 주세요.', 400, 150, 'parent', '');
			exit;
		}

		// 기존 발주 정보 추출
		if		($sorder_seq > 0){
			unset($sc);
			$sc['sorder_seq']	= $sorder_seq;
			$old_sorder			= $this->get_sorder_list($sc);
			$old_sorder			= $old_sorder[0];
			$currency			= $old_sorder['currency'];	// 거래처의 화폐 종류는 변경될 수 없음.
			if		($old_sorder['sorder_status'])	$sorder_status	= 1;
			elseif	($sorder_status)				$exec			= true;
		}else{
			if	($sorder_status)					$exec			= true;

			$traderinfo			= $this->get_trader(array('trader_seq' => $trader_seq));
			$traderinfo			= $traderinfo[0];
			$currency			= $traderinfo['currency_unit'];
		}

		if	(!$old_sorder['sorder_status']){
			if	(count($option) > 0){
				foreach($option as $idx => $opt){
					$goods_seq		= trim($params['goods_seq'][$idx]);
					$tmp			= explode('|', $opt);
					$option_type	= $tmp[0];
					$option_seq		= $tmp[1];
					if	($option_seq > 0){
						// 옵션 존재여부 체크
						if	(!$this->chk_option_exists($goods_seq, $option_type, $option_seq)){
							$msg	= $params['goods_name'][$idx] . ' ' . $params['option_name'][$idx]
									. '은 존재하지 않는 상품입니다.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}

						if	(trim($aooSeq[$idx])){
							if	(preg_match('/\,/', trim($aooSeq[$idx]))){
								$aoo_seq	= explode(',', trim($aooSeq[$idx]));
							}else{
								$aoo_seq	= array(trim($aooSeq[$idx]));
							}
							$add_reason	= '자동';
							$aoo_seq	= base64_encode(serialize($aoo_seq));
						}else{
							$add_reason	= '수동';
							$aoo_seq	= '';
						}

						unset($supplyParams, $supplyInfo);
						$supplyParams['currency']			= $currency;
						$supplyParams['goods_exchange']		= $goods_exchange;
						$supplyParams['fi_exchange']		= $fi_exchange;
						$supplyParams['ea']					= trim($params['ea'][$idx]);
						$supplyParams['supply_price']		= trim($params['supply_price'][$idx]);
						$supplyParams['freight_price']		= trim($params['freight_price'][$idx]);
						$supplyParams['insurance_price']	= trim($params['insurance_price'][$idx]);
						$supplyParams['duty_price']			= trim($params['duty_price'][$idx]);
						$supplyParams['accessorial_price']	= trim($params['accessorial_price'][$idx]);
						$supplyParams['supply_tax']			= trim($params['supply_tax'][$idx]);
						$supplyParams['use_tax']			= trim($params['hide_tax'][$idx]);
						$supplyInfo							= $this->calculate_supply_info($supplyParams);

						// 상품정보
						$data['goods_seq']					= $goods_seq;
						$data['option_type']				= $option_type;
						$data['option_seq']					= $option_seq;
						$data['goods_code']					= trim($params['goods_code'][$idx]);
						$data['goods_name']					= trim($params['goods_name'][$idx]);
						$data['option_name']				= trim($params['option_name'][$idx]);
						$data['supply_goods_name']			= trim($params['supply_goods_name'][$idx]);
						$data['weight']						= trim($params['weight'][$idx]);
						$data['use_tax']					= $supplyInfo['use_tax'];
						$data['ea']							= $supplyInfo['ea'];
						$data['supply_price']				= $supplyInfo['supply_price'];
						$data['freight_price']				= $supplyInfo['freight_price'];
						$data['insurance_price']			= $supplyInfo['insurance_price'];
						$data['cif_price']					= $supplyInfo['cif_price'];
						$data['duty_price']					= $supplyInfo['duty_price'];
						$data['accessorial_price']			= $supplyInfo['accessorial_price'];
						$data['krw_supply_price']			= $supplyInfo['krw_supply_price'];
						$data['supply_tax']					= $supplyInfo['supply_tax'];
						$data['add_reason']					= $add_reason;
						$data['aoo_seq']					= $aoo_seq;
						$goodsData[]						= $data;

						// 발주 수량 체크
						if	(!$data['ea'] || $data['ea'] < 0){
							$msg	= $data['goods_name'] . ' ' . $data['option_name'] . '의 '
									. '발주 수량을 입력해 주세요.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}

						// 합계정보
						$total_ea					+= $supplyInfo['ea'];
						$total_goods_price			+= $supplyInfo['goods_price'];
						$total_freight_price		+= $supplyInfo['freight_price'];
						$total_insurance_price		+= $supplyInfo['insurance_price'];
						$total_cif_price			+= $supplyInfo['cif_price'];
						$total_duty_price			+= $supplyInfo['duty_price'];
						$total_accessorial_price	+= $supplyInfo['accessorial_price'];
						$krw_total_supply_price		+= $supplyInfo['krw_supply_price'];
						$krw_total_supply_tax		+= $supplyInfo['supply_tax'];
					}
				}
			}
			$krw_total_price	= $krw_total_supply_price + $krw_total_supply_tax;

			// 발주 상품 체크
			if	(!(count($goodsData) > 0)){
				openDialogAlert('발주 상품을 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}

			// 발주 수량 체크
			if	(!($total_ea > 0)){
				openDialogAlert('발주 수량이 없습니다.', 400, 150, 'parent', '');
				exit;
			}

			// 기본정보
			$sorder['sorder_type']					= $sorder_type;
			$sorder['sorder_status']				= $sorder_status;
			$sorder['trader_seq']					= $trader_seq;
			$sorder['currency']						= $currency;
			$sorder['goods_exchange']				= $goods_exchange;
			$sorder['fi_exchange']					= $fi_exchange;
			$sorder['in_wh_seq']					= $in_wh_seq;
			$sorder['trade_terms']					= $trade_terms;
			$sorder['shipping_date']				= $shipping_date;
			$sorder['payment_date']					= $payment_date;
			$sorder['total_ea']						= $total_ea;
			$sorder['total_goods_price']			= $total_goods_price;
			$sorder['total_freight_price']			= $total_freight_price;
			$sorder['total_insurance_price']		= $total_insurance_price;
			$sorder['total_cif_price']				= $total_cif_price;
			$sorder['total_duty_price']				= $total_duty_price;
			$sorder['total_accessorial_price']		= $total_accessorial_price;
			$sorder['krw_total_supply_price']		= $krw_total_supply_price;
			$sorder['krw_total_supply_tax']			= $krw_total_supply_tax;
			$sorder['krw_total_price']				= $krw_total_price;

			// 창고 주소 추출
			unset($sc);
			$sc['wh_seq']					= $in_wh_seq;
			$whinfo							= $this->get_warehouse($sc);
			$sorder['wh_address']			= ($whinfo[0]['wh_zipcode']) ? $whinfo[0]['wh_zipcode'] . ' ' : '';
			$sorder['wh_address_street']	= ($whinfo[0]['wh_zipcode']) ? $whinfo[0]['wh_zipcode'] . ' ' : '';
			if		($whinfo[0]['wh_address']){
				$sorder['wh_address']			= trim($whinfo[0]['wh_zipcode']
												. ' ' . $whinfo[0]['wh_address']
												. ' ' . $whinfo[0]['wh_address_detail']);
			}
			if		($whinfo[0]['wh_address_street']){
				$sorder['wh_address_street']	= trim($whinfo[0]['wh_zipcode']
												. ' ' . $whinfo[0]['wh_address_street']
												. ' ' . $whinfo[0]['wh_address_detail']);
			}
		}
		$sorder['admin_memo']			= $admin_memo;
		if		($exec){
			$sorder['complete_date']	= date('Y-m-d H:i:s');
			$sorder['chg_log']			= '<div>' . date('Y-m-d H:i:s') . ' '
										. $this->managerInfo['mname']
										. '(' . $this->managerInfo['manager_id'] . ')가 '
										. '발주완료하였습니다. '
										. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		}else{
			$sorder['chg_log']			= '<div>' . date('Y-m-d H:i:s') . ' '
										. $this->managerInfo['mname']
										. '(' . $this->managerInfo['manager_id'] . ')가 '
										. '발주저장하였습니다. '
										. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		}

		return array('sorder_seq' => $sorder_seq, 'sorder' => $sorder, 'goodsData' => $goodsData, 'aooSeq' => $aooSeqArr, 'exec' => $exec);
	}

	// 발주 기본정보 저장
	public function save_sorder($params, $sorder_seq = ''){
		if	($this->chkScmConfig()){
			if	($sorder_seq > 0){
				$sql	= "update fm_scm_order set ";
				foreach($params as $fld => $val){
					if	(in_array($fld, array('chg_log')))
						$setSql[]	= $fld . " = concat(IFNULL(" . $fld . ", ''), '" . $val . "') ";
					else
						$setSql[]	= $fld . " = '" . $val . "' ";
				}
				$sql	.= implode(", ", $setSql) . " ";
				$sql	.= "where sorder_seq = '" . $sorder_seq . "' ";
				$this->db->query($sql);
			}else{
				$params['regist_date']			= date('Y-m-d H:i:s');
				$this->db->insert('fm_scm_order', $params);
				$sorder_seq		= $this->db->insert_id();

				// 발주 코드 가 없을 시 임의 코드 생성
				if	(!$params['sorder_code']){
					$sorder_code	= 'OC';
					if	($params['sorder_type'] == 'A')	$sorder_code	= 'RC';
					$sorder_code	.= date('YmdHis') . $sorder_seq;

					unset($params);
					$params['sorder_code']	= $sorder_code;
					$this->db->where(array('sorder_seq' => $sorder_seq));
					$this->db->update('fm_scm_order', $params);
				}
			}
		}

		return $sorder_seq;
	}

	// 발주 상품정보 저장
	public function save_sorder_goods($sorder_seq, $goodsData){
		if	($this->chkScmConfig()){
			if	($sorder_seq > 0 && is_array($goodsData) && count($goodsData) > 0){
				$this->db->delete('fm_scm_order_goods', array('sorder_seq' => $sorder_seq));
				foreach($goodsData as $k => $data){
					if	($data['goods_seq'] > 0 && $data['option_type'] && $data['option_seq'] > 0 && $data['ea'] > 0){
						$data['sorder_seq']	= $sorder_seq;
						$this->goods_public_null_exception($data);
						$this->db->insert('fm_scm_order_goods', $data);
					}
				}
			}
		}
	}

	// 발주 복사 ( 상품의 발주 사유는 모두 수동발주 )
	public function copy_sorder($sorder_seq){
		if	($this->chkScmConfig()){
			if	($sorder_seq > 0){
				$sql	= "select * from fm_scm_order where sorder_seq = ? ";
				$query	= $this->db->query($sql, array($sorder_seq));
				$sorder	= $query->row_array();
				if	($sorder['sorder_seq'] > 0){
					$sorder['sorder_code']		= 'CC' . date('YmdHis') . $sorder['sorder_seq'];
					$sorder['sorder_status']	= '0';
					unset($sorder['sorder_seq'], $sorder['regist_date']);
					$insParams					= $sorder;
					$insParams['regist_date']	= date('Y-m-d H:i:s');
					$this->db->insert('fm_scm_order', $insParams);
					$new_sorder_seq				= $this->db->insert_id();
					if	($new_sorder_seq > 0){
						$sql		= "select * from fm_scm_order_goods where sorder_seq = ? limit 1 ";
						$query		= $this->db->query($sql, array($sorder_seq));
						$goods		= $query->row_array();
						$flds		= array_keys($goods);
						foreach($flds as $fld){
							if		($fld == 'sorder_seq'){
								$sel[]	= "'" . $new_sorder_seq . "' as sorder_seq ";
							}elseif	($fld == 'add_reason'){
								$sel[]	= "'수동발주' as add_reason ";
							}else{
								$sel[]	= $fld;
							}
						}
						$sql	= "insert into fm_scm_order_goods "
								. "select "
								. implode(', ', $sel)
								. " from fm_scm_order_goods where sorder_seq = ? ";
						$this->db->query($sql, array($sorder_seq));
					}else{
						openDialogAlert('발주서 저장에 실패하였습니다.', 400, 150, 'parent', '');
						exit;
					}
				}else{
					openDialogAlert('해당 발주를 찾을 수 없습니다.', 400, 150, 'parent', '');
					exit;
				}
			}else{
				openDialogAlert('복사할 발주가 없습니다.', 400, 150, 'parent', '');
				exit;
			}
		}
	}

	// 발주서 입고완료 처리
	public function complete_sorder($sorder_seq){
		if	($this->chkScmConfig()){
			$sql	= "select * from fm_scm_order_goods where sorder_seq = ? and ea != whs_ea limit 1";
			$query	= $this->db->query($sql, array($sorder_seq));
			$remain	= $query->row_array();

			if	($remain['sorder_seq'] > 0){
				return false;
			}else{
				$whrParams['sorder_seq']	= $sorder_seq;
				$upParams['sorder_status']	= '2';
				$this->db->where($whrParams);
				$this->db->update('fm_scm_order', $upParams);

				return true;
			}
		}
	}

	// 비정규 입고 시 수동 임의 발주 저장
	public function save_except_sorder($whs, $goodsData){
		// 임의 발주서 생성
		unset($params);
		$sorder['sorder_type']					= 'T';
		$sorder['sorder_status']				= '1';
		$params['sorder_code']					= '';
		$sorder['shipping_date']				= '';
		$sorder['payment_date']					= '';
		$sorder['trader_seq']					= $whs['trader_seq'];
		$sorder['currency']						= $whs['currency'];
		$sorder['goods_exchange']				= $whs['goods_exchange'];
		$sorder['fi_exchange']					= $whs['fi_exchange'];
		$sorder['in_wh_seq']					= $whs['wh_seq'];
		$sorder['trade_terms']					= $whs['trade_terms'];
		$sorder['total_ea']						= $whs['total_ea'];
		$sorder['total_goods_price']			= $whs['total_goods_price'];
		$sorder['total_freight_price']			= $whs['total_freight_price'];
		$sorder['total_insurance_price']		= $whs['total_insurance_price'];
		$sorder['total_cif_price']				= $whs['total_cif_price'];
		$sorder['total_duty_price']				= $whs['total_duty_price'];
		$sorder['total_accessorial_price']		= $whs['total_accessorial_price'];
		$sorder['krw_total_supply_price']		= $whs['krw_total_supply_price'];
		$sorder['krw_total_supply_tax']			= $whs['krw_total_supply_tax'];
		$sorder['krw_total_price']				= $whs['krw_total_price'];
		$sorder['regist_date']					= date('Y-m-d H:i:s');
		$sorder['complete_date']				= date('Y-m-d H:i:s');
		$sorder['admin_memo']					= '';
		$sorder['chg_log']						= '<div>' . date('Y-m-d H:i:s') . ' '
												. $this->managerInfo['mname']
												. '(' . $this->managerInfo['manager_id'] . ')의 '
												. $whs['whs_code']
												. ' 입고에 의해 수동임의발주가 등록되었습니다. '
												. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		$ex_sorder_seq							= $this->save_sorder($sorder);

		// 발주코드 update
		unset($params);
		$params['sorder_code']				= 'EC' . date('YmdHis') . $ex_sorder_seq;
		$this->save_sorder($params, $ex_sorder_seq);

		// 임의 발주 상품
		foreach($goodsData as $e => $eGoodsData){
			$exceptData['goods_seq']			= $eGoodsData['goods_seq'];
			$exceptData['option_type']			= $eGoodsData['option_type'];
			$exceptData['option_seq']			= $eGoodsData['option_seq'];
			$exceptData['goods_code']			= $eGoodsData['goods_code'];
			$exceptData['goods_name']			= $eGoodsData['goods_name'];
			$exceptData['option_name']			= $eGoodsData['option_name'];
			$exceptData['supply_goods_name']	= $eGoodsData['supply_goods_name'];
			$exceptData['weight']				= $eGoodsData['weight'];
			$exceptData['use_tax']				= $eGoodsData['use_tax'];
			$exceptData['ea']					= $eGoodsData['ea'];
			$exceptData['whs_ea']				= $eGoodsData['ea'];
			$exceptData['supply_price']			= $eGoodsData['supply_price'];
			$exceptData['freight_price']		= $eGoodsData['freight_price'];
			$exceptData['insurance_price']		= $eGoodsData['insurance_price'];
			$exceptData['cif_price']			= $eGoodsData['cif_price'];
			$exceptData['duty_price']			= $eGoodsData['duty_price'];
			$exceptData['accessorial_price']	= $eGoodsData['accessorial_price'];
			$exceptData['krw_supply_price']		= $eGoodsData['krw_supply_price'];
			$exceptData['supply_tax']			= $eGoodsData['supply_tax'];
			$exceptData['add_reason']			= '수동';
			$exceptData['aoo_seq']				= '';
			$exceptGoods[]						= $exceptData;
		}
		$this->save_sorder_goods($ex_sorder_seq, $exceptGoods);

		return array('sorder_seq' => $ex_sorder_seq, 'sorder_code' => $ex_sorder_code);
	}

	//발주서 정보
	public function get_sorder_draft_info($srarch_sono){

		$basicinfo		= ($this->config_basic)	? $this->config_basic	: config_load('basic');

		unset($sc);
		$sc['sorder_seq_arr']	= $srarch_sono;
		$sorder					= $this->get_sorder_list($sc);
		if	($sorder) foreach($sorder as $k => $data){

			$data['basic']			= $basicinfo;

			// 거래처 정보
			unset($sc);
			$sc['trader_seq']		= $data['trader_seq'];
			$traders				= $this->get_trader($sc);
			// 거래처 담당자 정보
			unset($sc);
			$sc['parent_seq']		= $traders[0]['trader_seq'];
			$sc['sorder_seq']		= $data['sorder_seq'];
			$sc['parent_table']		= 'trader';
			$trader_manager			= $this->get_manager($sc);
			$traders[0]['manager']	= $trader_manager[0];
			$data['trader']			= $traders[0];

			// 발주상품정보
			unset($sc);
			$sc['sorder_seq']		= $data['sorder_seq'];
			$goodsData				= $this->get_sorder_goods($sc);
			$data['goodsData']		= $goodsData;

			$data['replace_values']['shopName']			= $basicinfo['shopName'];
			$data['replace_values']['shopDomain']		= $basicinfo['domain'];
			$data['replace_values']['trader_name']		= $data['trader']['trader_name'];
			$data['replace_values']['sorder_code']		= $data['sorder_code'];
			$data['replace_values']['sorder_time']		= $data['complete_date'];
			$data['replace_values']['cancel_date']		= $data['cancel_date'];
			$data['replace_values']['sorder_item_cnt']	= count($goodsData);
			$data['replace_values']['total_ea']			= $data['total_ea'];
			$data['replace_values']['sorder_url']		= 'http://'.$basicinfo['domain'].'/scm/get_sorder_draft_view?sono='.$data['sorder_seq'];

			$sorder[$k]				= $data;
		}

		return $sorder;
	}

	// 발주 삭제
	public function sorder_remove_complete($sorder_seq){
		if	($sorder_seq){

			$sql	= "select sorder_seq from fm_scm_order "
					. "where sorder_seq in ('" . implode("', '", $sorder_seq) . "')
						and sorder_status != 1 ";
			$query		= $this->db->query($sql);
			$result		= $query->result_array();

			foreach($result as $seq){
				$sql	= "delete from fm_scm_order "
						. "where sorder_seq = ".$seq['sorder_seq'];
				$this->db->query($sql);

				$sql	= "delete from fm_scm_order_goods "
						. "where sorder_seq = ".$seq['sorder_seq'];
				$this->db->query($sql);
			}
		}
	}

	// 최근 발주 상품 상품 추출
	public function get_latest_sorder($sc){
		$selectSql	= "select fsog.*, fso.complete_date, fst.trader_name, fst.currency_unit ";
		$fromSql	= "from fm_scm_order				as fso "
					. "inner join fm_scm_order_goods	as fsog "
					. "on fso.sorder_seq = fsog.sorder_seq "
					. "inner join fm_scm_trader			as fst "
					. "on fso.trader_seq = fst.trader_seq ";
		$whereSql	= "where fso.sorder_status = '1' ";
		$groupBy	= "";
		$orderBy	= "";
		$limit		= "";

		if	($sc['goods_seq']){
			$whereSql	.= "and fsog.goods_seq = ? ";
			$addBind[]	= $sc['goods_seq'];
		}
		if	($sc['option_seq']){
			$whereSql	.= "and fsog.option_seq = ? ";
			$addBind[]	= $sc['option_seq'];
		}
		if	($sc['trader_group']){
			$whereSql	.= "and fst.trader_group = ? ";
			$addBind[]	= $sc['trader_group'];
		}
		if	($sc['trader_seq']){
			$whereSql	.= "and fso.trader_seq = ? ";
			$addBind[]	= $sc['trader_seq'];
		}
		if	($sc['limit']){
			$limit		= "limit " . $sc['limit'];
		}
		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . $limit;
		$query			= $this->db->query($sql, $addBind);
		$result			= $query->result_array();

		return $result;
	}

	// 발주 취소 ( 발주완료에서 입고가 없는 경우 가능 or 입고가 완료되지 않고 수정발주하는 경우 가능 )
	public function sorder_cancel($sorder_seq, $mode = 'cancel'){
		$sorder = $this->get_sorder_list(array('sorder_seq' => $sorder_seq));
		$sorder = $sorder[0];
		if ($sorder['sorder_seq'] > 0){
			// 입고 체크
			$whs_ea = 0;
			$del_whs = array();
			$params = array();
			$params['sc_sorder_seq'] = $sorder['sorder_seq'];
			$whs = $this->get_warehousing_list($params);
			if ($whs){
				foreach($whs as $data){
					if ($data['whs_status'] == '1'){
						$whs_ea += $data['total_ea'];
					}else{
						$del_whs[] = $data['whs_seq'];
					}
				}
			}
			if		($whs_ea >= $sorder['total_ea']){
				return array('result' => 0, 'error' => '입고가 완료된 발주서입니다.');
				exit;
			}elseif	($mode == 'cancel' && $whs_ea > 0){
				return array('result' => 0, 'error' => '입고된 내역이 있는 발주서입니다.');
				exit;
			}

			// 대기 상태 입고 제거
			$this->remove_warehousing($del_whs);

			// 발주서 취소 처리
			$params = array();
			$params['sorder_status']	= '2';
			$params['sorder_code']		= $sorder['sorder_code'] . 'C';
			$params['cancel_date']		= date('Y-m-d H:i:s');
			$params['chg_log']			= '<div>' . date('Y-m-d H:i:s') . ' '
										. $this->managerInfo['mname']
										. '(' . $this->managerInfo['manager_id'] . ')에 의해 '
										. '발주취소되었습니다.'
										. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
			$this->save_sorder($params, $sorder['sorder_seq']);

		}else{
			return array('result' => 0, 'error' => '발주서 정보를 찾을 수 없습니다.');
			exit;
		}

		return array('result' => 1, 'error' => null);
		exit;
	}

	// 수정발주 parameter 체크
	public function chk_modify_sorder_param($params){
		$sorder_seq					= trim($params['sorder_seq']);
		$freight					= trim($params['inclusion_freight']);
		$insurance					= trim($params['inclusion_insurance']);
		$in_wh_seq					= trim($params['in_wh_seq']);
		$goods_exchange				= trim($params['goods_exchange']);
		$fi_exchange				= trim($params['fi_exchange']);
		$option						= $params['option_seq'];
		$trade_terms				= $this->get_trade_terms($freight, $insurance);
		$total_ea					= 0;
		$total_supply_price			= 0;
		$total_freight_price		= 0;
		$total_insurance_price		= 0;
		$total_cif_price			= 0;
		$total_duty_price			= 0;
		$total_accessorial_price	= 0;
		$krw_total_supply_price		= 0;
		$krw_total_supply_tax		= 0;
		$krw_total_price			= 0;
		$aooSeqArr					= array();
		$exec						= false;

		// 기본 발주 고유번호 및 기존 발주 정보 체크
		if ($sorder_seq > 0){
			$sc		= array('sorder_seq' => $sorder_seq);
			$sorder	= $this->get_sorder_list($sc);
			$sorder	= $sorder[0];
			if (!$sorder){
				openDialogAlert('기존 발주 정보를 찾을 수 없습니다.', 400, 150, 'parent', '');
				exit;
			}
		}else{
			openDialogAlert('잘못된 접근입니다.', 400, 150, 'parent', '');
			exit;
		}
		$currency		= $sorder['currency'];
		$modified_num	= $sorder['modified_num'] + 1;

		// 기입고된 상품 정보 추출
		$sc		= array('sc_sorder_seq' => $sorder_seq, 'sc_whs_status' => '1');
		$whs	= $this->get_warehousing_list($sc);
		if ($whs){
			foreach($whs as $data){
				$sc			= array('whs_seq' => $data['whs_seq']);
				$whs_goods	= $this->get_warehousing_goods($sc);
				if ($whs_goods){
					foreach($whs_goods as $goods){
						$goodscode				= $goods['goods_seq'] . $goods['option_type'] . $goods['option_seq'];
						$whsGoods[$goodscode]	+= $goods['ea'];
					}
				}
			}
		}

		if	(count($option) > 0){
			foreach($option as $idx => $opt){
				$goods_seq		= trim($params['goods_seq'][$idx]);
				$tmp			= explode('|', $opt);
				$option_type	= $tmp[0];
				$option_seq		= $tmp[1];
				$goodscode		= $goods_seq . $option_type . $option_seq;
				if	($option_seq > 0){
					// 옵션 존재여부 체크
					if	(!$this->chk_option_exists($goods_seq, $option_type, $option_seq)){
						$msg	= $params['goods_name'][$idx] . ' ' . $params['option_name'][$idx]
								. '은 존재하지 않는 상품입니다.';
						openDialogAlert($msg, 400, 150, 'parent', '');
						exit;
					}

					unset($supplyParams, $supplyInfo);
					$supplyParams['currency']			= $currency;
					$supplyParams['goods_exchange']		= $goods_exchange;
					$supplyParams['fi_exchange']		= $fi_exchange;
					$supplyParams['ea']					= trim($params['ea'][$idx]);
					$supplyParams['supply_price']		= trim($params['supply_price'][$idx]);
					$supplyParams['freight_price']		= trim($params['freight_price'][$idx]);
					$supplyParams['insurance_price']	= trim($params['insurance_price'][$idx]);
					$supplyParams['duty_price']			= trim($params['duty_price'][$idx]);
					$supplyParams['accessorial_price']	= trim($params['accessorial_price'][$idx]);
					$supplyParams['supply_tax']			= trim($params['supply_tax'][$idx]);
					$supplyParams['use_tax']			= trim($params['hide_tax'][$idx]);
					$supplyInfo							= $this->calculate_supply_info($supplyParams);

					// 상품정보
					$data								= array();
					$data['goods_seq']					= $goods_seq;
					$data['option_type']				= $option_type;
					$data['option_seq']					= $option_seq;
					$data['goods_code']					= trim($params['goods_code'][$idx]);
					$data['goods_name']					= trim($params['goods_name'][$idx]);
					$data['option_name']				= trim($params['option_name'][$idx]);
					$data['supply_goods_name']			= trim($params['supply_goods_name'][$idx]);
					$data['weight']						= trim($params['weight'][$idx]);
					$data['use_tax']					= $supplyInfo['use_tax'];
					$data['ea']							= $supplyInfo['ea'];
					$data['supply_price']				= $supplyInfo['supply_price'];
					$data['freight_price']				= $supplyInfo['freight_price'];
					$data['insurance_price']			= $supplyInfo['insurance_price'];
					$data['cif_price']					= $supplyInfo['cif_price'];
					$data['duty_price']					= $supplyInfo['duty_price'];
					$data['accessorial_price']			= $supplyInfo['accessorial_price'];
					$data['krw_supply_price']			= $supplyInfo['krw_supply_price'];
					$data['supply_tax']					= $supplyInfo['supply_tax'];
					$data['add_reason']					= $add_reason;
					$data['aoo_seq']					= $aoo_seq;
					// 기입고수량 차감 및 입고 수량 복사
					if ($whsGoods[$goodscode] > 0){
						$data['whs_ea']					= $whsGoods[$goodscode];
						$whsGoods[$goodscode]			-= $supplyInfo['ea'];
					}
					$goodsData[]						= $data;

					// 발주 수량 체크
					if	(!$data['ea'] || $data['ea'] < 0){
						$msg	= $data['goods_name'] . ' ' . $data['option_name'] . '의 '
								. '발주 수량을 입력해 주세요.';
						openDialogAlert($msg, 400, 150, 'parent', '');
						exit;
					}

					// 합계정보
					$total_ea					+= $supplyInfo['ea'];
					$total_goods_price			+= $supplyInfo['goods_price'];
					$total_freight_price		+= $supplyInfo['freight_price'];
					$total_insurance_price		+= $supplyInfo['insurance_price'];
					$total_cif_price			+= $supplyInfo['cif_price'];
					$total_duty_price			+= $supplyInfo['duty_price'];
					$total_accessorial_price	+= $supplyInfo['accessorial_price'];
					$krw_total_supply_price		+= $supplyInfo['krw_supply_price'];
					$krw_total_supply_tax		+= $supplyInfo['supply_tax'];
				}
			}
		}
		$krw_total_price	= $krw_total_supply_price + $krw_total_supply_tax;

		// 발주 상품 체크
		if	(!(count($goodsData) > 0)){
			openDialogAlert('발주 상품을 선택해 주세요.', 400, 150, 'parent', '');
			exit;
		}

		// 발주 수량 체크
		if	(!($total_ea > 0)){
			openDialogAlert('발주 수량이 없습니다.', 400, 150, 'parent', '');
			exit;
		}

		// 기입고수량 체크
		if (array_sum($whsGoods) > 0){
			openDialogAlert('해당 발주서로 입고완료된 상품에 대한 수량이 부족합니다.', 400, 150, 'parent', '');
			exit;
		}

		// 기본정보
		unset($sorder['sorder_seq']);
		$sorder['goods_exchange']				= $goods_exchange;
		$sorder['fi_exchange']					= $fi_exchange;
		$sorder['trade_terms']					= $trade_terms;
		$sorder['total_ea']						= $total_ea;
		$sorder['total_goods_price']			= $total_goods_price;
		$sorder['total_freight_price']			= $total_freight_price;
		$sorder['total_insurance_price']		= $total_insurance_price;
		$sorder['total_cif_price']				= $total_cif_price;
		$sorder['total_duty_price']				= $total_duty_price;
		$sorder['total_accessorial_price']		= $total_accessorial_price;
		$sorder['krw_total_supply_price']		= $krw_total_supply_price;
		$sorder['krw_total_supply_tax']			= $krw_total_supply_tax;
		$sorder['krw_total_price']				= $krw_total_price;
		$sorder['modified_num']					= $modified_num;
		$sorder['admin_memo']					= $admin_memo;
		$sorder['chg_log']						= '<div>' . date('Y-m-d H:i:s') . ' '
												. $this->managerInfo['mname']
												. '(' . $this->managerInfo['manager_id'] . ')가 '
												. '수정발주완료하였습니다. '
												. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';

		return array('sorder_seq' => $sorder_seq, 'sorder' => $sorder, 'goodsData' => $goodsData);
	}

	// 수정발주된 발주로 기존 입고완료 내역 연결 변경
	public function chg_warehousing_target_sorder($old_sorder_seq, $new_sorder_seq){
		$whrParams['sorder_seq']	= $old_sorder_seq;
		$whrParams['whs_status']	= '1';
		$upParams['sorder_seq']		= $new_sorder_seq;
		$this->db->where($whrParams);
		$this->db->update('fm_scm_warehousing', $upParams);
	}

	########## ↑↑ 발주 ↑↑ ##### ↓↓ 입고 ↓↓ ##########

	// 입고 목록 추출
	public function get_warehousing_list($sc){
		$selectSql	= "select swhs.* ";
		$fromSql	= "from fm_scm_warehousing	as swhs ";
		$whereSql	= "where swhs.whs_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by swhs.whs_seq desc ";

		// 입고 고유번호 검색
		if		($sc['whs_seq']){
			$whereSql	.= " and swhs.whs_seq = ? ";
			$addBind[]	= $sc['whs_seq'];
		}elseif	($sc['sc_whs_seq']){
			$whereSql	.= " and swhs.whs_seq like '" . $sc['sc_whs_seq'] . "%' ";
		}
		elseif	($sc['sc_whs_seq_list']){
			$whereSql	.= " and swhs.whs_seq in ('" . implode("','", $sc['sc_whs_seq_list'] ) . "') ";
		}
		// 입고코드 검색
		if		($sc['whs_code']){
			$whereSql	.= " and LOWER(swhs.whs_code) = ? ";
			$addBind[]	= $sc['whs_code'];
		}elseif	($sc['sc_whs_code']){
			$whereSql	.= " and LOWER(swhs.whs_code) like '%" . addslashes(strtolower(trim($sc['sc_whs_code']))) . "%' ";
		}
		// 발주번호 검색
		if		($sc['sc_sorder_seq']){
			$whereSql .= " and swhs.sorder_seq = ? ";
			$addBind[]	= $sc['sc_sorder_seq'];
		}
		// 발주코드 검색
		if	($sc['sc_sorder_code']){
			$whereSql .= " and swhs.sorder_code = ? ";
			$addBind[]	= $sc['sc_sorder_code'];
		}
		// 상태 검색
		if	($sc['sc_whs_status']!=""){
			if	(!is_array($sc['sc_whs_status']))	$sc['sc_whs_status']	= array($sc['sc_whs_status']);
			$whereSql	.= " and swhs.whs_status in ('" . implode("', '", $sc['sc_whs_status']) . "') ";
		}
		// 구분 검색
		if		($sc['sc_whs_type']){
			if	(!is_array($sc['sc_whs_type']))	$sc['sc_whs_type']	= array($sc['sc_whs_type']);
			$whereSql	.= " and swhs.whs_type in ('" . implode("', '", $sc['sc_whs_type']) . "') ";
		}
		// 거래처 검색
		if		($sc['sc_trader_seq']){
			$whereSql	.= " and swhs.trader_seq = ? ";
			$addBind[]	= $sc['sc_trader_seq'];
		} else if($sc['sc_trader']) {
			$whereSql	.= " and swhs.trader_seq = ? ";
			$addBind[]	= $sc['sc_trader'];
		}
		// 입고 일자 검색
		if		($sc['sc_date_fld'] == 'regist'){
			if		($sc['sc_sdate'] && $sc['sc_edate']){
				$whereSql	.= " and swhs.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' "
							. " and swhs.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_sdate']){
				$whereSql	.= " and swhs.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_edate']){
				$whereSql	.= " and swhs.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}
		}elseif	($sc['sc_date_fld'] == 'complete'){
			if		($sc['sc_comp_sdate'] && $sc['sc_comp_edate']){
				$whereSql	.= " and swhs.whs_status = '1' "
							. " and swhs.complete_date >= '" . $sc['sc_comp_sdate'] . " 00:00:00' "
							. " and swhs.complete_date <= '" . $sc['sc_comp_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_comp_sdate']){
				$whereSql	.= " and swhs.whs_status = '1' "
							. " and swhs.complete_date >= '" . $sc['sc_comp_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_comp_edate']){
				$whereSql	.= " and swhs.whs_status = '1' "
							. " and swhs.complete_date <= '" . $sc['sc_comp_edate'] . " 23:59:59' ";
			}
		}
		// 상품번호 검색
		if	($sc['sc_goods_seq']){
			$fromSql		.= "INNER JOIN fm_scm_warehousing_goods	as swhsg "
							. "ON swhs.whs_seq = swhsg.whs_seq ";
			$whereSql		.= " and swhsg.goods_seq like '" . addslashes(trim($sc['sc_goods_seq'])) . "%' ";
			$groupBy		= "group by swhs.whs_seq ";
		}
		// 상품명 검색
		if	($sc['sc_goods_name']){
			$fromSql		.= "INNER JOIN fm_scm_warehousing_goods	as swhsg "
							. "ON swhs.whs_seq = swhsg.whs_seq ";
			$whereSql		.= " and swhsg.goods_name like '%" . addslashes(trim($sc['sc_goods_name'])) . "%' ";
			$groupBy		= "group by swhs.whs_seq ";
		}
		// 상품코드 검색
		if	($sc['sc_goods_code']){
			$fromSql		.= "INNER JOIN fm_scm_warehousing_goods	as swhsg "
							. "ON swhs.whs_seq = swhsg.whs_seq ";
			$whereSql		.= " and LOWER(swhsg.goods_code) like '%" . addslashes(strtolower(trim($sc['sc_goods_code']))) . "%' ";
			$groupBy		= "group by swhs.whs_seq ";
		}
		// 창고 검색
		if	($sc['sc_warehouse']){
			$sc['sc_warehouse']	= addslashes(trim($sc['sc_warehouse']));
			$whereSql		.= " and swhs.wh_seq = " . $sc['sc_warehouse'] . " ";
		}
		// 키워드 검색
		if	($sc['keyword']){
			$sc['keyword']	= addslashes(trim($sc['keyword']));
			$fromSql		.= "INNER JOIN fm_scm_warehousing_goods	as swhsg "
							. "ON swhs.whs_seq = swhsg.whs_seq ";
			$whereSql		.= " and ( swhsg.goods_seq like '" . $sc['keyword'] . "%' "
							. " or swhsg.goods_name like '%" . $sc['keyword'] . "%' "
							. " or LOWER(swhsg.goods_code) like '%" . strtolower($sc['keyword']) . "%' ) ";
			$groupBy		= "group by swhs.whs_seq ";
		}

		// 프린트 용 (창고명, 거래처 명 추가)
		if( $sc['sc_summery'] ){
			$selectSql .= ' , fct.trader_name, fcw.wh_group, fct.currency_unit, fcw.wh_name ';
			$fromSql		.= " LEFT JOIN fm_scm_trader	as fct "
							. "		ON fct.trader_seq = swhs.trader_seq "
							. "	 LEFT JOIN fm_scm_warehouse as fcw"
							. "		ON fcw.wh_seq = swhs.wh_seq ";
		}

		// 입고 시 발주서 선택할 때 이미 있는 발주 건인지 검색
		if($sc['sorder_seq']){
			$whereSql .= " AND swhs.sorder_seq = '".$sc['sorder_seq']."' AND swhs.whs_status = '0' ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		$sqlQuery['select']		= $selectSql;
		$sqlQuery['fromSql']	= $fromSql;
		$sqlQuery['whereSql']	= $whereSql;
		$sqlQuery['groupBy']	= $groupBy;
		$sqlQuery['orderBy']	= $orderBy;
		$sqlQuery['addBind']	= $addBind;
		$sqlQuery['countSql']	= "swhs.whs_seq >0";

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= pagingScmNumbering($sqlQuery,$sc);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 입고 상품 검색
	public function get_warehousing_goods($sc){
		$selectSql	= "select *, (supply_price * ea) as goods_price ";
		$fromSql	= "from fm_scm_warehousing_goods ";
		$whereSql	= "where whs_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by whs_seq";

		// 상품 요약 정보
		if	($sc['get_type'] == 'summary'){
			$selectSql	= "select *, count(*) as cnt, SUM(IFNULL(ea, 0)) as tea ";
			$groupBy	= "group by whs_seq ";
		}
		// 조정 고유번호 검색
		if	($sc['whs_seq']){
			$whereSql	.= " and whs_seq = ? ";
			$addBind[]	= $sc['whs_seq'];
		}elseif	($sc['sc_whs_seq_list']){
			$whereSql	.= " and whs_seq in ('" . implode("','", $sc['sc_whs_seq_list'] ) . "') ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		// 자릿수 치환 필드 목록
		$replace_cut_number_field = array('supply_price', 'goods_price', 'supply_tax');
		// 금액 자릿수 변경
		if	($result) foreach($result as $k => &$goods){
			foreach($replace_cut_number_field as $field){
				if($goods[$field]){
					$goods[$field] = $this->cut_exchange_price('KRW', $goods[$field]);
				}
			}
		}
		
		return $result;
	}

	// 입고 parameter 재정의
	public function chk_warehousing_param($params){
		$whs_seq					= trim($params['whs_seq']);
		$whs_code					= trim($params['whs_code']);
		$whs_status					= trim($params['status']);
		$whs_type					= trim($params['whs_type']);
		$trader_seq					= trim($params['trader_seq']);
		$wh_seq						= trim($params['in_wh_seq']);
		$freight					= trim($params['inclusion_freight']);
		$insurance					= trim($params['inclusion_insurance']);
		$goods_exchange				= trim($params['goods_exchange']);
		$fi_exchange				= trim($params['fi_exchange']);
		$sorder_seq					= trim($params['sorder_seq']);
		$sorder_code				= trim($params['sorder_code']);
		$admin_memo					= addslashes(trim($params['admin_memo']));
		$option						= $params['option_seq'];
		$trade_terms				= $this->get_trade_terms($freight, $insurance);
		$total_ea					= 0;
		$total_supply_price			= 0;
		$total_freight_price		= 0;
		$total_insurance_price		= 0;
		$total_cif_price			= 0;
		$total_duty_price			= 0;
		$total_accessorial_price	= 0;
		$krw_total_supply_price		= 0;
		$krw_total_supply_tax		= 0;
		$krw_total_price			= 0;
		$exec						= false;

		// 기존 입고 정보 추출
		if		($whs_seq > 0){
			unset($sc);
			$sc['whs_seq']		= $whs_seq;
			$old_whs			= $this->get_warehousing_list($sc);
			$old_whs			= $old_whs[0];
			$currency			= $old_whs['currency'];	// 거래처의 화폐 종류는 변경될 수 없음.
			if		($old_whs['whs_status'])	$whs_status	= 1;
			elseif	($whs_status)				$exec		= true;
		}else{
			if	($whs_status)					$exec		= true;

			$traderinfo			= $this->get_trader(array('trader_seq' => $trader_seq));
			$traderinfo			= $traderinfo[0];
			$currency			= $traderinfo['currency_unit'];
		}

		if	(!$old_whs['whs_status']){
			// 거래처 체크
			if	(!$trader_seq){
				openDialogAlert('거래처를 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}

			// 입고창고 체크
			if	(!$wh_seq){
				openDialogAlert('입고창고를 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}

			// 정규입고 발주서 체크
			if	($whs_type != 'E' && !$sorder_seq){
				openDialogAlert('발주서를 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}

			// 정규 입고 시 발주 목록 추출
			if	($whs_type != 'E'){
				unset($sc);
				$sc['sorder_seq']			= $sorder_seq;
				$sorderGoodsList			= $this->get_sorder_goods($sc);
				if	($sorderGoodsList) foreach($sorderGoodsList as $k => $sordGoods){
					$tmpKey		= $sordGoods['goods_seq'] . $sordGoods['option_type'] . $sordGoods['option_seq'];
					$sorderGoods[$tmpKey]	= $sordGoods;
				}
			}

			if	(count($option) > 0){
				foreach($option as $idx => $opt){
					$goods_seq				= trim($params['goods_seq'][$idx]);
					$order_krw_supply_price	= trim($params['order_krw_supply_price'][$idx]);
					$tmp					= explode('|', $opt);
					$option_type			= $tmp[0];
					$option_seq				= $tmp[1];
					if	($option_seq > 0){
						// 옵션 존재여부 체크
						if	(!$this->chk_option_exists($goods_seq, $option_type, $option_seq)){
							$msg	= $params['goods_name'][$idx] . ' ' . $params['option_name'][$idx]
									. '은 존재하지 않는 상품입니다.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}

						unset($supplyParams, $supplyInfo);
						$supplyParams['currency']			= $currency;
						$supplyParams['goods_exchange']		= $goods_exchange;
						$supplyParams['fi_exchange']		= $fi_exchange;
						$supplyParams['ea']					= trim($params['ea'][$idx]);
						$supplyParams['supply_price']		= trim($params['supply_price'][$idx]);
						$supplyParams['freight_price']		= trim($params['freight_price'][$idx]);
						$supplyParams['insurance_price']	= trim($params['insurance_price'][$idx]);
						$supplyParams['duty_price']			= trim($params['duty_price'][$idx]);
						$supplyParams['accessorial_price']	= trim($params['accessorial_price'][$idx]);
						$supplyParams['supply_tax']			= trim($params['supply_tax'][$idx]);
						$supplyParams['use_tax']			= trim($params['hide_tax'][$idx]);
						$supplyInfo							= $this->calculate_supply_info($supplyParams);

						// 상품정보
						$data['goods_seq']					= $goods_seq;
						$data['option_type']				= $option_type;
						$data['option_seq']					= $option_seq;
						$data['goods_code']					= trim($params['goods_code'][$idx]);
						$data['goods_name']					= trim($params['goods_name'][$idx]);
						$data['option_name']				= trim($params['option_name'][$idx]);
						$data['supply_goods_name']			= trim($params['supply_goods_name'][$idx]);
						$data['order_ea']					= trim($params['order_ea'][$idx]);
						$data['location_code']				= trim($params['location_code'][$idx]);
						$data['location_position']			= trim($params['location_position'][$idx]);
						$data['weight']						= trim($params['weight'][$idx]);
						$data['order_krw_supply_price']		= $order_krw_supply_price;
						$data['use_tax']					= $supplyInfo['use_tax'];
						$data['ea']							= $supplyInfo['ea'];
						$data['supply_price']				= $supplyInfo['supply_price'];
						$data['freight_price']				= $supplyInfo['freight_price'];
						$data['insurance_price']			= $supplyInfo['insurance_price'];
						$data['cif_price']					= $supplyInfo['cif_price'];
						$data['duty_price']					= $supplyInfo['duty_price'];
						$data['accessorial_price']			= $supplyInfo['accessorial_price'];
						$data['krw_supply_price']			= $supplyInfo['krw_supply_price'];
						$data['supply_tax']					= $supplyInfo['supply_tax'];

						// 입고 로케이션 체크
						if	(!$data['location_position'] || !$data['location_code']){
							$msg	= $data['goods_name'] . ' ' . $data['option_name'] . '의 '
									. '입고 로케이션을 지정해 주세요.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}
						// 입고 수량 체크
						if	(!$data['ea'] || $data['ea'] < 0){
							$msg	= $data['goods_name'] . ' ' . $data['option_name'] . '의 '
									. '입고 수량을 입력해 주세요.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}
						$goodsData[]					= $data;

						// 합계정보
						$total_ea						+= $supplyInfo['ea'];
						$total_goods_price				+= $supplyInfo['goods_price'];
						$total_freight_price			+= $supplyInfo['freight_price'];
						$total_insurance_price			+= $supplyInfo['insurance_price'];
						$total_cif_price				+= $supplyInfo['cif_price'];
						$total_duty_price				+= $supplyInfo['duty_price'];
						$total_accessorial_price		+= $supplyInfo['accessorial_price'];
						$krw_total_supply_price			+= $supplyInfo['krw_supply_price'];
						$krw_total_supply_tax			+= $supplyInfo['supply_tax'];

						// 발주서 상품 정보
						if	($whs_type != 'E' && $sorder_seq > 0){
							$tmpKey						= $goods_seq . $option_type . $option_seq;
							$sordGoods					= $sorderGoods[$tmpKey];
							$order_ea					= $sordGoods['ea'];
							$whs_ea						= $sordGoods['whs_ea'] + $data['ea'];
							$remain_ea					= $order_ea - $sordGoods['whs_ea'];
							$sordGoods['whs_ea']		= $whs_ea;
							$sorderGoods[$tmpKey]		= $sordGoods;

							// 정규입고
							if	($whs_ea > $order_ea){
								openDialogAlert($data['goods_name'] . ' ' . $data['option_name'] . '의 '
								. '입고 수량이 남은 발주 수량을 초과했습니다.<br/>'
								. '(남은 발주수량 : ' . number_format($remain_ea) . ')',
								600, 150, 'parent', '');
								exit;
							}
						}
					}
				}
			}
			$krw_total_price	= $krw_total_supply_price + $krw_total_supply_tax;

			// 입고 상품 체크
			if	(!(count($goodsData) > 0)){
				openDialogAlert('입고 상품을 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}
			// 입고 수량 체크
			if	(!($total_ea > 0)){
				openDialogAlert('입고 수량이 없습니다.', 400, 150, 'parent', '');
				exit;
			}

			// 기본정보
			$whs['whs_type']							= $whs_type;
			$whs['whs_code']							= $whs_code;
			$whs['whs_status']							= $whs_status;
			$whs['trader_seq']							= $trader_seq;
			$whs['currency']							= $currency;
			$whs['goods_exchange']						= $goods_exchange;
			$whs['fi_exchange']							= $fi_exchange;
			$whs['trade_terms']							= $trade_terms;
			$whs['wh_seq']								= $wh_seq;
			$whs['sorder_seq']							= $sorder_seq;
			$whs['sorder_code']							= $sorder_code;
			$whs['total_ea']							= $total_ea;
			$whs['total_goods_price']					= $total_goods_price;
			$whs['total_freight_price']					= $total_freight_price;
			$whs['total_insurance_price']				= $total_insurance_price;
			$whs['total_cif_price']						= $total_cif_price;
			$whs['total_duty_price']					= $total_duty_price;
			$whs['total_accessorial_price']				= $total_accessorial_price;
			$whs['krw_total_supply_price']				= $krw_total_supply_price;
			$whs['krw_total_supply_tax']				= $krw_total_supply_tax;
			$whs['krw_total_price']						= $krw_total_price;
		}
		$whs['admin_memo']								= $admin_memo;
		if		($exec){
			$whs['complete_date']						= date('Y-m-d H:i:s');
			$whs['chg_log']								= '<div>' . date('Y-m-d H:i:s') . ' '
														. $this->managerInfo['mname']
														. '(' . $this->managerInfo['manager_id'] . ')가 '
														. '입고완료하였습니다. '
														. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		}else{
			$whs['chg_log']								= '<div>' . date('Y-m-d H:i:s') . ' '
														. $this->managerInfo['mname']
														. '(' . $this->managerInfo['manager_id'] . ')가 '
														. '입고저장하였습니다. '
														. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		}

		return array('whs_seq' => $whs_seq, 'whs' => $whs, 'goodsData' => $goodsData, 'sorderGoods' => $sorderGoods, 'exec' => $exec);
	}

	// 입고 기본 정보 저장
	public function save_warehousing($params, $whs_seq = ''){
		if	($this->chkScmConfig()){
			if	($whs_seq > 0){
				$sql	= "update fm_scm_warehousing set ";
				foreach($params as $fld => $val){
					if	(in_array($fld, array('chg_log')))
						$setSql[]	= $fld . " = concat(IFNULL(" . $fld . ", ''), '" . $val . "') ";
					else
						$setSql[]	= $fld . " = '" . $val . "' ";
				}
				$sql	.= implode(", ", $setSql) . " ";
				$sql	.= "where whs_seq = '" . $whs_seq . "' ";
				$this->db->query($sql);
			}else{
				$params['regist_date']		= date('Y-m-d H:i:s');
				$params['complete_date']	= date('Y-m-d H:i:s');
				$this->db->insert('fm_scm_warehousing', $params);
				$whs_seq		= $this->db->insert_id();

				// 입고 코드 가 없을 시 임의 코드 생성
				if	(!$params['whs_code']){
					unset($params);
					$params['whs_code']		= 'WHS' . $params['whs_type'] . date('YmdHis') . $whs_seq;
					$this->db->where(array('whs_seq' => $whs_seq));
					$this->db->update('fm_scm_warehousing', $params);
				}
			}
		}

		return array('whs_seq' => $whs_seq, 'whs_code' => $params['whs_code']);
	}

	// 입고 상품 정보 저장
	public function save_warehousing_goods($whs, $goodsData, $exec){

 		if	($this->chkScmConfig()){

			if	($whs['whs_seq'] > 0 && is_array($goodsData) && count($goodsData) > 0){
				// 입고 상품 데이터 저장
				$this->db->delete('fm_scm_warehousing_goods', array('whs_seq' => $whs['whs_seq']));
				foreach($goodsData as $g => $data){

					// 완료 처리 시 입고 처리
					if	($exec){
						unset($params);
						$params['kind']							= 'warehousing';
						$params['type']							= 'in';
						$params['export_code']					= '';
						$params['return_code']					= '';
						$params['seq']							= $whs['whs_seq'];
						$params['wh_seq']						= $whs['wh_seq'];
						$params['trader_seq']					= $whs['trader_seq'];
						$params['location_position']			= $data['location_position'];
						$params['location_code']				= $data['location_code'];
						$params['goods_seq']					= $data['goods_seq'];
						$params['goods_code']					= $data['goods_code'];
						$params['goods_name']					= $data['goods_name'];
						$params['option_type']					= $data['option_type'];
						$params['option_seq']					= $data['option_seq'];
						$params['option_name']					= $data['option_name'];
						$params['ea']							= $data['ea'];
						$params['sum_krw_supply_price']			= $data['krw_supply_price'];
						$chgResult	= $this->change_scm_stock($params);
						$data['location_position']				= $chgResult['location_position'];
						$data['location_code']					= $chgResult['location_code'];
					}

					unset($inParams);
					$inParams['whs_seq']						= $whs['whs_seq'];
					$inParams['goods_seq']						= $data['goods_seq'];
					$inParams['goods_code']						= $data['goods_code'];
					$inParams['goods_name']						= $data['goods_name'];
					$inParams['option_type']					= $data['option_type'];
					$inParams['option_seq']						= $data['option_seq'];
					$inParams['option_name']					= $data['option_name'];
					$inParams['supply_goods_name']				= $data['supply_goods_name'];
					$inParams['use_tax']						= $data['use_tax'];
					$inParams['order_ea']						= $data['order_ea'];
					$inParams['order_krw_supply_price']			= $data['order_krw_supply_price'];
					$inParams['location_code']					= $data['location_code'];
					$inParams['location_position']				= $data['location_position'];
					$inParams['weight']							= $data['weight'];
					$inParams['ea']								= $data['ea'];
					$inParams['supply_price']					= $data['supply_price'];
					$inParams['freight_price']					= $data['freight_price'];
					$inParams['insurance_price']				= $data['insurance_price'];
					$inParams['cif_price']						= $data['cif_price'];
					$inParams['duty_price']						= $data['duty_price'];
					$inParams['accessorial_price']				= $data['accessorial_price'];
					$inParams['krw_supply_price']				= $data['krw_supply_price'];
					$inParams['supply_tax']						= $data['supply_tax'];
					$this->db->insert('fm_scm_warehousing_goods', $inParams);

					$chgGoodsTarget[]	= array('goods_seq'		=> $data['goods_seq'],
												'option_type'	=> $data['option_type'],
												'option_seq'	=> $data['option_seq']);
				}

				// 완료 처리 시 거래처 정산 추가
				if	($exec){
					$act_price		= $this->calculate_account_price($whs['currency'], $whs['trade_terms'], $whs['total_goods_price'], $whs['total_freight_price'], $whs['total_insurance_price'], $whs['krw_total_price']);

					// 거래처 정산 내역 추가
					$actParams['trader_seq']					= $whs['trader_seq'];
					$actParams['act_type']						= 'warehousing';
					$actParams['act_fkey']						= $whs['whs_seq'];
					$actParams['act_code']						= $whs['whs_code'];
					$actParams['currency']						= $whs['currency'];
					$actParams['act_price']						= $act_price;
					$actParams['act_memo']						= '';
					$this->save_traderaccount($actParams);

					if	(count($chgGoodsTarget) > 0){
						$this->save_ledger_today($whs['wh_seq'], $chgGoodsTarget);
						return array('target' => $chgGoodsTarget, 'wh' => array($whs['wh_seq']));
					}
				}
			}
		}
	}

	// 최근 입고내역 추출
	public function get_last_warehousing($sc){
		$selectSql	= "select *, "
					. "(select trader_name from fm_scm_trader where trader_seq = whs.trader_seq) "
					. "as trader_name, "
					. "(select wh_name from fm_scm_warehouse where wh_seq = whs.wh_seq) "
					. "as wh_name ";
		$fromSql	= "from fm_scm_warehousing				as whs "
					. "inner join fm_scm_warehousing_goods	as whsg "
					. "on whs.whs_seq = whsg.whs_seq ";
		$whereSql	= "where whs.whs_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by whs.complete_date desc ";

		// 상품번호 검색
		if	($sc['goods_seq']){
			$whereSql	= " and whsg.goods_seq = ? ";
			$addBind[]	= $sc['goods_seq'];
		}
		// 옵션구분 검색
		if	($sc['option_type']){
			$whereSql	= " and whsg.option_type = ? ";
			$addBind[]	= $sc['option_type'];
		}
		// 옵션번호 검색
		if	($sc['option_seq']){
			$whereSql	= " and whsg.option_seq = ? ";
			$addBind[]	= $sc['option_seq'];
		}
		// 키조합 검색
		if	($sc['optioninfo']){
			$whereSql	= " and concat(whsg.goods_seq, whsg.option_type, whsg.option_seq) = ? ";
			$addBind[]	= $sc['optioninfo'];
		}

		$sql	= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		if	($sc['limit'] > 0)	$sql	.= " LIMIT " . $sc['limit'] . " ";
		$query	= $this->db->query($sql, $addBind);
		$result	= $query->result_array();

		return $result;
	}

	// 입고내역 삭제
	public function remove_warehousing($whsSeq){
		if	($whsSeq){
			if	(!is_array($whsSeq))	$whsSeq	= array($whsSeq);

			$sql	= "select whs_seq from fm_scm_warehousing "
					. "where whs_seq in ('" . implode("', '", $whsSeq) . "')
						and whs_status != 1 ";
			$query		= $this->db->query($sql);
			$result		= $query->result_array();

			foreach($result as $seq){
				$sql	= "delete from fm_scm_warehousing "
						. "where whs_seq = ".$seq['whs_seq'];
				$this->db->query($sql);

				$sql	= "delete from fm_scm_warehousing_goods "
						. "where whs_seq = ".$seq['whs_seq'];
				$this->db->query($sql);
			}

		}
	}

	// 최근 발주 상품 상품 추출
	public function get_latest_warehousing($sc){
		$selectSql	= "select fswg.*, fsw.complete_date, fswa.wh_name, "
					. "fst.trader_name, fst.currency_unit ";
		$fromSql	= "from fm_scm_warehousing				as fsw "
					. "inner join fm_scm_warehousing_goods	as fswg "
					. "on fsw.whs_seq = fswg.whs_seq "
					. "inner join fm_scm_trader				as fst "
					. "on fsw.trader_seq = fst.trader_seq "
					. "inner join fm_scm_warehouse			as fswa "
					. "on fsw.wh_seq = fswa.wh_seq ";
		$whereSql	= "where fsw.whs_status = '1' ";
		$groupBy	= "";
		$orderBy	= "";
		$limit		= "";

		if	($sc['goods_seq']){
			$whereSql	.= "and fswg.goods_seq = ? ";
			$addBind[]	= $sc['goods_seq'];
		}
		if	($sc['option_seq']){
			$whereSql	.= "and fswg.option_seq = ? ";
			$addBind[]	= $sc['option_seq'];
		}
		if	($sc['trader_group']){
			$whereSql	.= "and fst.trader_group = ? ";
			$addBind[]	= $sc['trader_group'];
		}
		if	($sc['trader_seq']){
			$whereSql	.= "and fsw.trader_seq = ? ";
			$addBind[]	= $sc['trader_seq'];
		}
		if	($sc['limit']){
			$limit		= "limit " . $sc['limit'];
		}
		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . $limit;
		$query			= $this->db->query($sql, $addBind);
		$result			= $query->result_array();

		return $result;
	}

	########## ↑↑ 입고 ↑↑ ##### ↓↓ 반출 ↓↓ ##########

	// 반출 목록 추출
	public function get_carryingout_list($sc){
		$selectSql	= "select scro.* ";
		$fromSql	= "from fm_scm_carryingout	as scro ";
		$whereSql	= "where scro.cro_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by scro.cro_seq desc ";

		// 입고 고유번호 검색
		if		($sc['cro_seq']){
			$whereSql	.= " and scro.cro_seq = ? ";
			$addBind[]	= $sc['cro_seq'];
		}elseif	($sc['sc_cro_seq']){
			$whereSql	.= " and scro.cro_seq like '" . $sc['sc_cro_seq'] . "%' ";
		}elseif	($sc['cro_seq_arr']){
			$whereSql	.= " and scro.cro_seq in ('" . implode("','", $sc['cro_seq_arr']) . "') ";
		}
		// 입고코드 검색
		if		($sc['cro_code']){
			$whereSql	.= " and LOWER(scro.cro_code) = ? ";
			$addBind[]	= $sc['cro_code'];
		}elseif	($sc['sc_cro_code']){
			$whereSql	.= " and LOWER(scro.cro_code) like '%" . addslashes(strtolower(trim($sc['sc_cro_code']))) . "%' ";
		}
		// 상태 검색
		if		($sc['sc_cro_status']!=""){
			if	(!is_array($sc['sc_cro_status']))	$sc['sc_cro_status']	= array($sc['sc_cro_status']);
			$whereSql	.= " and scro.cro_status in ('" . implode("', '", $sc['sc_cro_status']) . "') ";
		}
		// 구분 검색
		if		($sc['sc_cro_type']){
			if	(!is_array($sc['sc_cro_type']))	$sc['sc_cro_type']	= array($sc['sc_cro_type']);
			$whereSql	.= " and scro.cro_type in ('" . implode("', '", $sc['sc_cro_type']) . "') ";
		}
		// 거래처 검색
		if		($sc['sc_trader']){
			$whereSql	.= " and scro.trader_seq = ? ";
			$addBind[]	= $sc['sc_trader'];
		}
		// 반출 창고 검색
		if		($sc['sc_wh_seq']){
			$whereSql	.= " and scro.wh_seq = ? ";
			$addBind[]	= $sc['sc_wh_seq'];
		}
		// 반출 일자 검색
		if		($sc['sc_date_fld'] == 'regist'){
			if		($sc['sc_sdate'] && $sc['sc_edate']){
				$whereSql	.= " and scro.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' "
							. " and scro.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_sdate']){
				$whereSql	.= " and scro.regist_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_edate']){
				$whereSql	.= " and scro.regist_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}
		}elseif	($sc['sc_date_fld'] == 'complete'){
			if		($sc['sc_sdate'] && $sc['sc_edate']){
				$whereSql	.= " and scro.cro_status = '1' "
							. " and scro.complete_date >= '" . $sc['sc_sdate'] . " 00:00:00' "
							. " and scro.complete_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}elseif	($sc['sc_sdate']){
				$whereSql	.= " and scro.cro_status = '1' "
							. " and scro.complete_date >= '" . $sc['sc_sdate'] . " 00:00:00' ";
			}elseif	($sc['sc_edate']){
				$whereSql	.= " and scro.cro_status = '1' "
							. " and scro.complete_date <= '" . $sc['sc_edate'] . " 23:59:59' ";
			}
		}
		// 반출번호 검색
		if	($sc['sc_cro_code']){
			$fromSql		.= "INNER JOIN fm_scm_carryingout_goods	as scrog "
							. "ON scro.cro_seq = scrog.cro_seq ";
			$whereSql		.= " and scrog.cro_seq = '" . addslashes(trim($sc['sc_goods_seq'])) . "' ";
			$groupBy		= "group by scro.cro_seq ";
		}

		// 상품번호 검색
		if	($sc['sc_goods_seq']){
			$fromSql		.= "INNER JOIN fm_scm_carryingout_goods	as scrog "
							. "ON scro.cro_seq = scrog.cro_seq ";
			$whereSql		.= " and scrog.goods_seq like '" . addslashes(trim($sc['sc_goods_seq'])) . "%' ";
			$groupBy		= "group by scro.cro_seq ";
		}
		// 상품명 검색
		if	($sc['sc_goods_name']){
			$fromSql		.= "INNER JOIN fm_scm_carryingout_goods	as scrog "
							. "ON scro.cro_seq = scrog.cro_seq ";
			$whereSql		.= " and scrog.goods_name like '%" . addslashes(trim($sc['sc_goods_name'])) . "%' ";
			$groupBy		= "group by scro.cro_seq ";
		}
		// 상품코드 검색
		if	($sc['sc_goods_code']){
			$fromSql		.= "INNER JOIN fm_scm_carryingout_goods	as scrog "
							. "ON scro.cro_seq = scrog.cro_seq ";
			$whereSql		.= " and LOWER(scrog.goods_code) like '%" . addslashes(strtolower(trim($sc['sc_goods_code']))) . "%' ";
			$groupBy		= "group by scro.cro_seq ";
		}
		// 키워드 검색
		if	($sc['keyword']){
			$sc['keyword']	= addslashes(trim($sc['keyword']));
			$fromSql		.= "INNER JOIN fm_scm_carryingout_goods	as scrog "
							. "ON scro.cro_seq = scrog.cro_seq ";
			$whereSql		.= " and ( scro.cro_code like '%" . $sc['keyword'] . "%' "
							. " or scrog.goods_seq like '" . $sc['keyword'] . "%' "
							. " or scrog.goods_name like '%" . $sc['keyword'] . "%' "
							. " or LOWER(scrog.goods_code) like '%" . strtolower($sc['keyword']) . "%' ) ";
			$groupBy		= "group by scro.cro_seq ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;

		$sqlQuery['select']		= $selectSql;
		$sqlQuery['fromSql']	= $fromSql;
		$sqlQuery['whereSql']	= $whereSql;
		$sqlQuery['groupBy']	= $groupBy;
		$sqlQuery['orderBy']	= $orderBy;
		$sqlQuery['addBind']	= $addBind;
		$sqlQuery['countSql']	= "scro.cro_seq > 0";

		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= pagingScmNumbering($sqlQuery,$sc);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}
		return $result;
	}

	// 반출 상품 검색
	public function get_carryingout_goods($sc){
		$selectSql	= "select *, (supply_price * ea) as goods_price ";
		$fromSql	= "from fm_scm_carryingout_goods as fscg ";
		$whereSql	= "where fscg.cro_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by fscg.cro_seq";

		// 상품 요약 정보
		if	($sc['get_type'] == 'summary'){
			$selectSql	= "select *, count(*) as cnt, SUM(IFNULL(ea, 0)) as tea ";
			$groupBy	= "group by cro_seq ";
		}
		// 조정 고유번호 검색
		if	($sc['cro_seq']){
			$whereSql	.= " and fscg.cro_seq = ? ";
			$addBind[]	= $sc['cro_seq'];
		}

		$sql			= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy;
		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result		= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
		}else{
			$query		= $this->db->query($sql, $addBind);
			$result		= $query->result_array();
		}

		return $result;
	}

	// 반출 parameter 재정의
	public function chk_carryingout_param($params){
		$cro_seq					= trim($params['cro_seq']);
		$cro_code					= trim($params['cro_code']);
		$cro_status					= trim($params['status']);
		$cro_type					= trim($params['cro_type']);
		$trader_seq					= trim($params['trader_seq']);
		$wh_seq						= trim($params['wh_seq']);
		$freight					= trim($params['inclusion_freight']);
		$insurance					= trim($params['inclusion_insurance']);
		$goods_exchange				= trim($params['goods_exchange']);
		$fi_exchange				= trim($params['fi_exchange']);
		$admin_memo					= addslashes(trim($params['admin_memo']));
		$option						= $params['option_seq'];
		$trade_terms				= $this->get_trade_terms($freight, $insurance);
		$total_ea					= 0;
		$total_supply_price			= 0;
		$total_freight_price		= 0;
		$total_insurance_price		= 0;
		$total_cif_price			= 0;
		$total_duty_price			= 0;
		$total_accessorial_price	= 0;
		$krw_total_supply_price		= 0;
		$krw_total_supply_tax		= 0;
		$krw_total_price			= 0;
		$exec						= false;

		// 기존 반출 정보 추출
		if		($cro_seq > 0){
			unset($sc);
			$sc['cro_seq']		= $cro_seq;
			$old_cro			= $this->get_carryingout_list($sc);
			$old_cro			= $old_cro[0];
			$currency			= $old_cro['currency'];	// 거래처의 화폐 종류는 변경될 수 없음.
			if		($old_cro['cro_status'])	$cro_status	= 1;
			elseif	($cro_status)				$exec		= true;
		}else{
			if	($cro_status)					$exec		= true;

			$traderinfo			= $this->get_trader(array('trader_seq' => $trader_seq));
			$traderinfo			= $traderinfo[0];
			$currency			= $traderinfo['currency_unit'];
		}

		if	(!$old_cro['cro_status']){
			// 거래처 체크
			if	(!$trader_seq){
				openDialogAlert('거래처를 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}

			// 반출창고 체크
			if	(!$wh_seq){
				openDialogAlert('반출창고를 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}

			if	(count($option) > 0){
				foreach($option as $idx => $opt){
					$goods_seq				= trim($params['goods_seq'][$idx]);
					$tmp					= explode('|', $opt);
					$option_type			= $tmp[0];
					$option_seq				= $tmp[1];
					if	($option_seq > 0){
						// 옵션 존재여부 체크
						if	(!$this->chk_option_exists($goods_seq, $option_type, $option_seq)){
							$msg	= $params['goods_name'][$idx] . ' ' . $params['option_name'][$idx]
									. '은 존재하지 않는 상품입니다.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}

						unset($supplyParams, $supplyInfo);
						$supplyParams['currency']			= $currency;
						$supplyParams['goods_exchange']		= $goods_exchange;
						$supplyParams['fi_exchange']		= $fi_exchange;
						$supplyParams['ea']					= trim($params['ea'][$idx]);
						$supplyParams['supply_price']		= trim($params['supply_price'][$idx]);
						$supplyParams['freight_price']		= trim($params['freight_price'][$idx]);
						$supplyParams['insurance_price']	= trim($params['insurance_price'][$idx]);
						$supplyParams['duty_price']			= trim($params['duty_price'][$idx]);
						$supplyParams['accessorial_price']	= trim($params['accessorial_price'][$idx]);
						$supplyParams['supply_tax']			= trim($params['supply_tax'][$idx]);
						$supplyParams['use_tax']			= trim($params['hide_tax'][$idx]);
						$supplyInfo							= $this->calculate_supply_info($supplyParams);

						// 상품정보
						$data['goods_seq']					= $goods_seq;
						$data['option_type']				= $option_type;
						$data['option_seq']					= $option_seq;
						$data['goods_code']					= trim($params['goods_code'][$idx]);
						$data['goods_name']					= trim($params['goods_name'][$idx]);
						$data['option_name']				= trim($params['option_name'][$idx]);
						$data['supply_goods_name']			= trim($params['supply_goods_name'][$idx]);
						$data['order_ea']					= trim($params['order_ea'][$idx]);
						$data['location_code']				= trim($params['location_code'][$idx]);
						$data['location_position']			= trim($params['location_position'][$idx]);
						$data['weight']						= trim($params['weight'][$idx]);
						$data['use_tax']					= $supplyInfo['use_tax'];
						$data['ea']							= $supplyInfo['ea'];
						$data['supply_price']				= $supplyInfo['supply_price'];
						$data['freight_price']				= $supplyInfo['freight_price'];
						$data['insurance_price']			= $supplyInfo['insurance_price'];
						$data['cif_price']					= $supplyInfo['cif_price'];
						$data['duty_price']					= $supplyInfo['duty_price'];
						$data['accessorial_price']			= $supplyInfo['accessorial_price'];
						$data['krw_supply_price']			= $supplyInfo['krw_supply_price'];
						$data['supply_tax']					= $supplyInfo['supply_tax'];

						// 반출 수량 체크
						if	(!$data['ea'] || $data['ea'] < 0){
							$msg	= $data['goods_name'] . ' ' . $data['option_name'] . '의 '
									. '반출 수량을 입력해 주세요.';
							openDialogAlert($msg, 400, 150, 'parent', '');
							exit;
						}
						$goodsData[]				= $data;

						// 합계정보
						$total_ea						+= $supplyInfo['ea'];
						$total_goods_price				+= $supplyInfo['goods_price'];
						$total_freight_price			+= $supplyInfo['freight_price'];
						$total_insurance_price			+= $supplyInfo['insurance_price'];
						$total_cif_price				+= $supplyInfo['cif_price'];
						$total_duty_price				+= $supplyInfo['duty_price'];
						$total_accessorial_price		+= $supplyInfo['accessorial_price'];
						$krw_total_supply_price			+= $supplyInfo['krw_supply_price'];
						$krw_total_supply_tax			+= $supplyInfo['supply_tax'];
					}
				}
			}
			$krw_total_price	= $krw_total_supply_price + $krw_total_supply_tax;

			// 반출 상품 체크
			if	(!(count($goodsData) > 0)){
				openDialogAlert('반출 상품을 선택해 주세요.', 400, 150, 'parent', '');
				exit;
			}
			// 입고 수량 체크
			if	(!($total_ea > 0)){
				openDialogAlert('반출 수량이 없습니다.', 400, 150, 'parent', '');
				exit;
			}

			// 기본정보
			$cro['cro_type']							= $cro_type;
			$cro['cro_code']							= $cro_code;
			$cro['cro_status']							= $cro_status;
			$cro['trader_seq']							= $trader_seq;
			$cro['currency']							= $currency;
			$cro['goods_exchange']						= $goods_exchange;
			$cro['fi_exchange']							= $fi_exchange;
			$cro['trade_terms']							= $trade_terms;
			$cro['wh_seq']								= $wh_seq;
			$cro['total_ea']							= $total_ea;
			$cro['total_goods_price']					= $total_goods_price;
			$cro['total_freight_price']					= $total_freight_price;
			$cro['total_insurance_price']				= $total_insurance_price;
			$cro['total_cif_price']						= $total_cif_price;
			$cro['total_duty_price']					= $total_duty_price;
			$cro['total_accessorial_price']				= $total_accessorial_price;
			$cro['krw_total_supply_price']				= $krw_total_supply_price;
			$cro['krw_total_supply_tax']				= $krw_total_supply_tax;
			$cro['krw_total_price']						= $krw_total_price;
		}
		$cro['admin_memo']								= $admin_memo;
		if		($exec){
			$cro['complete_date']						= date('Y-m-d H:i:s');
			$cro['chg_log']								= '<div>' . date('Y-m-d H:i:s') . ' '
														. $this->managerInfo['mname']
														. '(' . $this->managerInfo['manager_id'] . ')가 '
														. '반출완료하였습니다. '
														. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		}else{
			$cro['chg_log']								= '<div>' . date('Y-m-d H:i:s') . ' '
														. $this->managerInfo['mname']
														. '(' . $this->managerInfo['manager_id'] . ')가 '
														. '반출저장하였습니다. '
														. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
		}



		return array('cro_seq' => $cro_seq, 'cro' => $cro, 'goodsData' => $goodsData, 'exec' => $exec);
	}

	public function save_carryingout($params, $cro_seq){
		if	($this->chkScmConfig()){
			if	($cro_seq > 0){
				$sql	= "update fm_scm_carryingout set ";
				foreach($params as $fld => $val){
					if	(in_array($fld, array('chg_log')))
						$setSql[]	= $fld . " = concat(IFNULL(" . $fld . ", ''), '" . $val . "') ";
					else
						$setSql[]	= $fld . " = '" . $val . "' ";
				}
				$sql	.= implode(", ", $setSql) . " ";
				$sql	.= "where cro_seq = '" . $cro_seq . "' ";
				$this->db->query($sql);
			}else{
				$params['regist_date']			= date('Y-m-d H:i:s');
				$this->db->insert('fm_scm_carryingout', $params);
				$cro_seq		= $this->db->insert_id();

				// 입고 코드 가 없을 시 임의 코드 생성
				if	(!$params['cro_code']){
					unset($params);
					$params['cro_code']		= 'CRO' . $params['cro_type'] . date('YmdHis') . $cro_seq;
					$this->db->where(array('cro_seq' => $cro_seq));
					$this->db->update('fm_scm_carryingout', $params);
				}
			}
		}

		return array('cro_seq' => $cro_seq, 'cro_code' => $params['cro_code']);
	}

	// 반출 상품 정보 저장
	public function save_carryingout_goods($cro_seq, $exec, $goodsData, $cro){

 		if	($this->chkScmConfig()){
			if	($cro_seq > 0 && is_array($goodsData) && count($goodsData) > 0){
				$this->db->delete('fm_scm_carryingout_goods', array('cro_seq' => $cro_seq));

				$wh_seq			= $cro['wh_seq'];
				$trader_seq		= $cro['trader_seq'];
				foreach($goodsData as $g => $data){
					// 완료 처리 시 반출 처리
					if	($exec){
						unset($params);
						$params['kind']							= 'carryingout';
						$params['type']							= 'in';
						$params['export_code']					= '';
						$params['return_code']					= '';
						$params['wh_seq']						= $wh_seq;
						$params['seq']							= $cro_seq;
						$params['trader_seq']					= $trader_seq;
						$params['location_position']			= $data['location_position'];
						$params['location_code']				= $data['location_code'];
						$params['goods_seq']					= $data['goods_seq'];
						$params['goods_code']					= $data['goods_code'];
						$params['goods_name']					= $data['goods_name'];
						$params['option_type']					= $data['option_type'];
						$params['option_seq']					= $data['option_seq'];
						$params['option_name']					= $data['option_name'];
						$params['ea']							= ($data['ea'] * -1);
						$params['sum_krw_supply_price']			= $data['krw_supply_price'];
						$chgResult	= $this->change_scm_stock($params);
						$data['location_position']				= $chgResult['location_position'];
						$data['location_code']					= $chgResult['location_code'];
					}

					// 반출 상품 데이터 추가
					unset($inParams);
					$inParams['cro_seq']						= $cro_seq;
					$inParams['goods_seq']						= $data['goods_seq'];
					$inParams['goods_code']						= $data['goods_code'];
					$inParams['goods_name']						= $data['goods_name'];
					$inParams['option_type']					= $data['option_type'];
					$inParams['option_seq']						= $data['option_seq'];
					$inParams['option_name']					= $data['option_name'];
					$inParams['use_tax']						= $data['use_tax'];
					$inParams['location_code']					= $data['location_code'];
					$inParams['location_position']				= $data['location_position'];
					$inParams['weight']							= $data['weight'];
					$inParams['ea']								= $data['ea'];
					$inParams['supply_price']					= $data['supply_price'];
					$inParams['freight_price']					= $data['freight_price'];
					$inParams['insurance_price']				= $data['insurance_price'];
					$inParams['cif_price']						= $data['cif_price'];
					$inParams['duty_price']						= $data['duty_price'];
					$inParams['accessorial_price']				= $data['accessorial_price'];
					$inParams['krw_supply_price']				= $data['krw_supply_price'];
					$inParams['supply_tax']						= $data['supply_tax'];
					$this->db->insert('fm_scm_carryingout_goods', $inParams);

					$chgGoodsTarget[]	= array('goods_seq'		=> $data['goods_seq'],
												'option_type'	=> $data['option_type'],
												'option_seq'	=> $data['option_seq']);
				}

				if	($exec){
					$act_price									= $this->calculate_account_price($cro['currency'], $cro['trade_terms'], $cro['total_goods_price'], $cro['total_freight_price'], $cro['total_insurance_price'], $cro['krw_total_price']);

					// 거래처 정산 내역 추가
					$actParams['trader_seq']					= $trader_seq;
					$actParams['act_type']						= 'carryingout';
					$actParams['act_fkey']						= $cro_seq;
					$actParams['act_code']						= $cro['cro_code'];
					$actParams['currency']						= $cro['currency'];
					$actParams['act_price']						= $act_price;
					$actParams['act_memo']						= '';
					$this->save_traderaccount($actParams);

					if	(count($chgGoodsTarget) > 0){
						$this->save_ledger_today($wh_seq, $chgGoodsTarget);
						return array('target' => $chgGoodsTarget, 'wh' => $wh_seq);
					}
				}
			}
		}
	}

	//반출명세서 정보
	public function get_carryingout_draft_info($crono_list){

		if(is_array($crono_list)){
			$crono_list		= $crono_list;
		}else{
			$crono_list[]	= $crono_list;
		}

		$basicinfo		= ($this->config_basic)	? $this->config_basic	: config_load('basic');
		$crono_draft	= array();
		$sc = array();

		foreach((array)$crono_list as $key => $crono){
			unset($sc);
			if((int)$crono > 0){
				$sc['cro_seq'] = $crono;

				//반출데이터
				unset($carrying_data);
				$carrying_data	= $this->get_carryingout_list($sc);
				$carrying_data	= $carrying_data[0];
				//반출 상품데이터
				$carrying_goods = $this->get_carryingout_goods($sc);

				//창고명
				if($carrying_data['wh_seq']){
					unset($sc);
					$sc['wh_seq']				  = $carrying_data['wh_seq'];
					$tmp_warehouse				  = $this->get_warehouse($sc);
					$carrying_data['wh_name']	  = $tmp_warehouse[0]['wh_name'];
				}

				//거래처명
				if($carrying_data['trader_seq']){
					unset($sc);
					$sc['trader_seq']			  = $carrying_data['trader_seq'];
					$tmp_trader					  = $this->get_trader($sc);
					$carrying_data['trader_name'] = $tmp_trader[0]['trader_name'];
				}

				//데이터 가공
				//반출 데이터 초기화
				$carrying_data['total_ea']			= 0;
				$carrying_data['total_sum_price']	= 0;
				$carrying_data['total_tax_price']	= 0;
				$carrying_data['total_price']		= 0;

				//반출상품데이터
				foreach($carrying_goods as $key=>$data){
					$supply_price		 = $data['supply_price'];
					if	(strtoupper($carrying_data['currency']) != 'KRW'){
						$supply_price	 = $this->krw_exchange($carrying_data['currency'], $data['supply_price']);
					}
					$ea					 = $data['ea'];
					$tax				 = $data['tax'];
					$tax_price			 = $data['use_tax'] == 'Y' ? $supply_price*0.1 : 0;
					$sum_price			 = $supply_price * $ea;
					$total_price		 = $sum_price + $tax_price;

					$carrying_goods[$key]['supply_price']	= number_format($supply_price);
					$carrying_goods[$key]['tax_price']		= number_format($tax_price);
					$carrying_goods[$key]['sum_price']		= number_format($sum_price);
					$carrying_goods[$key]['total_price']	= number_format($total_price);

					$carrying_data['total_ea']			+= $ea;
					$carrying_data['total_sum_price']	+= $sum_price;
					$carrying_data['total_tax_price']	+= $tax_price;
					$carrying_data['total_price']		+= $total_price;
				}

				$carrying_data['goodsData'] = $carrying_goods;
				$crono_draft[] = $carrying_data;
			}
		}

		return $crono_draft;
	}

	########## ↑↑ 반출 ↑↑ ##### ↓↓ 출고/반품(-출고) ↓↓ ##########

	// 출고처리
	public function apply_export_wh($wh_seq, $goodsData){

 		if	($this->chkScmConfig()){
			if	($wh_seq > 0 && is_array($goodsData) && count($goodsData) > 0){
				// 무재고 자동 입고 처리
				$auto_wh_exec	= false;
				foreach($goodsData as $k => $data){
					if ($data['auto_wh']){
						if ($autowh_params[$data['optioninfo']]){
							$autowh_params[$data['optioninfo']]['export_code'][]	= $data['export_code'];
							$autowh_params[$data['optioninfo']]['ea']				+= $data['ea'];
						}else{
							$autowh_params[$data['optioninfo']]	= array(
												'option'		=> $data['optioninfo'], 
												'export_code'	=> array($data['export_code']), 
												'ea'			=> $data['ea'] 
											);
						}
						$auto_wh_exec	= true;
					}
				}

				if ($auto_wh_exec){
					$this->auto_warehousing($wh_seq, $autowh_params);
				}

				foreach($goodsData as $k => $data){
					if	(preg_match('/suboption/', $data['optioninfo'])){
						$tmp			= explode('suboption', $data['optioninfo']);
						$goods_seq		= $tmp[0];
						$option_type	= 'suboption';
						$option_seq		= $tmp[1];
					}else{
						$tmp			= explode('option', $data['optioninfo']);
						$goods_seq		= $tmp[0];
						$option_type	= 'option';
						$option_seq		= $tmp[1];
					}

					// supply_price 별도 추출  ( 앞단에서 추출을 pass한 경우 )
					if ($data['auto_wh'] && $data['supplyprice'] == 'X'){
						$tmpBinds				= array($wh_seq, $goods_seq, $option_type, $option_seq);
						$sql					= "select * from fm_scm_location_link "
												. "where wh_seq = ? and goods_seq = ? "
												. "and option_type = ? and option_seq = ? ";
						$query					= $this->db->query($sql, $tmpBinds);
						$tmp					= $query->row_array();
						$data['supplyprice']	= $tmp['supply_price'];
					}

					if	($goods_seq > 0 && $option_seq > 0 && $data['ea'] > 0){
						// 출고 처리
						unset($params);
						$params['kind']					= 'export';
						$params['type']					= 'out';
						$params['export_code']			= $data['export_code'];
						$params['return_code']			= '';
						$params['wh_seq']				= $wh_seq;
						$params['seq']					= '';
						$params['trader_seq']			= '';
						$params['goods_seq']			= $goods_seq;
						$params['goods_code']			= $data['goodscode'];
						$params['goods_name']			= $data['goodsname'];
						$params['option_type']			= $option_type;
						$params['option_seq']			= $option_seq;
						$params['option_name']			= $data['optionname'];
						$params['ea']					= $data['ea'];
						$params['krw_supply_price']		= $data['supplyprice'];
						$chgResult	= $this->change_scm_stock($params);

						// 변경 상품 배열
						$chgGoodsTarget[]	= array('goods_seq'		=> $goods_seq,
													'option_type'	=> $option_type,
													'option_seq'	=> $option_seq);
					}
				}

				// 수술부 집계 반영
				if	(is_array($chgGoodsTarget) && count($chgGoodsTarget) > 0){
					// 타 controllers에서 사용하기 위한 전역변수
					$this->tmp_scm	= array('wh_seq'	=> $wh_seq,
											'goods'		=> $chgGoodsTarget);
					$this->save_ledger_today($wh_seq, $chgGoodsTarget);
				}
			}
		}
	}

	// 반품(-출고)처리
	public function apply_return_wh($wh_seq, $return_code, $goodsData){

 		if	($this->chkScmConfig()){
			if	($wh_seq > 0 && is_array($goodsData) && count($goodsData) > 0){
				foreach($goodsData as $k => $data){
					if	(preg_match('/suboption/', $data['optioninfo'])){
						$tmp			= explode('suboption', $data['optioninfo']);
						$goods_seq		= $tmp[0];
						$option_type	= 'suboption';
						$option_seq		= $tmp[1];
					}else{
						$tmp			= explode('option', $data['optioninfo']);
						$goods_seq		= $tmp[0];
						$option_type	= 'option';
						$option_seq		= $tmp[1];
					}

					if	($goods_seq > 0 && $option_seq > 0 && $data['ea'] > 0){
						if	(!$data['location_position'])	$data['location_position']	= '1-1-1';

						unset($params);
						$params['kind']					= 'return';
						$params['type']					= 'out';
						$params['export_code']			= '';
						$params['return_code']			= $return_code;
						$params['wh_seq']				= $wh_seq;
						$params['seq']					= '';
						$params['trader_seq']			= '';
						$params['location_position']	= $data['location_position'];
						$params['goods_seq']			= $goods_seq;
						$params['goods_code']			= $data['goods_code'];
						$params['goods_name']			= $data['goods_name'];
						$params['option_type']			= $option_type;
						$params['option_seq']			= $option_seq;
						$params['option_name']			= $data['option_name'];
						$params['ea']					= ($data['ea'] * -1);
						$params['badea']				= ($data['bad_ea'] * -1);
						$params['krw_supply_price']		= $data['supply_price'];
						$chgResult	= $this->change_scm_stock($params);

						// 변경 상품 배열
						$chgGoodsTarget[]	= array('goods_seq'		=> $goods_seq,
													'option_type'	=> $option_type,
													'option_seq'	=> $option_seq);

					}
				}

				// 수술부 집계 반영
				if	(is_array($chgGoodsTarget) && count($chgGoodsTarget) > 0){
					// 타 controllers에서 사용하기 위한 전역변수
					$this->tmp_scm	= array('wh_seq'	=> $wh_seq,
											'goods'		=> $chgGoodsTarget);

					$this->save_ledger_today($wh_seq, $chgGoodsTarget);
				}
			}
		}
	}

	// 자동입고 상품 입고 처리
	public function auto_warehousing($wh_seq, $params){
		if ($params) foreach($params as $data){
			if	(preg_match('/suboption/', $data['option'])){
				$tmp						= explode('suboption', $data['option']);
				$goods_seq					= $tmp[0];
				$option_type				= 'suboption';
				$option_seq					= $tmp[1];
				$option_table				= 'fm_goods_suboption';
				$option_fld					= 'suboption_seq';
			}else{
				$tmp						= explode('option', $data['option']);
				$goods_seq					= $tmp[0];
				$option_type				= 'option';
				$option_seq					= $tmp[1];
				$option_table				= 'fm_goods_option';
				$option_fld					= 'option_seq';
			}
			if (is_array($data['export_code'])){
				$export_code				= implode(', ', $data['export_code']);
			}else{
				$export_code				= $data['export_code'];
			}
			$ea								= $data['ea'];

			// 상품정보 추출
			if (!isset($goods[$goods_seq])){
				
				$sql						= "select goods_code, goods_name, purchase_goods_name, scm_auto_warehousing from fm_goods where goods_seq = ? limit 1";
				$query						= $this->db->query($sql, array($goods_seq));
				$goods[$goods_seq]			= $query->row_array();
				$supply_goods_name			= ($goods[$goods_seq]['purchase_goods_name']) ?: $goods[$goods_seq]['goods_name'];
			}

			// 옵션정보 추출
			if (!isset($options[$data['option']])){
				$sql						= "select * from " . $option_table . " where " . $option_fld . " = ? limit 1";
				$query						= $this->db->query($sql, array($option_seq));
				$options[$data['option']]	= $query->row_array();
				if ($option_type == 'suboption'){
					$option_name				= $options[$data['option']]['suboption'];
				}else{
					$option_name				= $options[$data['option']]['option1']
												. $options[$data['option']]['option2']
												. $options[$data['option']]['option3']
												. $options[$data['option']]['option4']
												. $options[$data['option']]['option5'];
				}
			}

			// 로케이션 정보 추출
			if (!isset($location_code)){
				$location					= $this->get_location(array('wh_seq' => $wh_seq, 'location_position' => '1-1-1'));
				$location_code				= $location[1][1][1]['location_code'];
			}

			// 주 거래처 자동발주 정보 추출
			if (!isset($default[$data['option']])){
				$default[$data['option']]	= $this->get_default_sorder_info($goods_seq, $option_seq, $option_type);
			}
			$trader_seq						= $default[$data['option']]['trader_seq'];

			if ($trader_seq > 0){
				// 거래처 정보 추출
				if (!isset($traders[$trader_seq])){
					$tmp					= $this->get_trader(array('trader_seq' => $trader_seq));
					$traders[$trader_seq]	= $tmp[0];
				}

				$currency					= $traders[$trader_seq]['currency_unit'];
				$exchange					= $this->exchange_cfg[strtolower($currency) . '_currency_exchange'];
				$supply_price				= $default[$data['option']]['supply_price'];
				$use_tax					= $default[$data['option']]['use_supply_tax'];
				if (!isset($supplys[$data['option']])){
					unset($supplyParams);
					$supplyParams['currency']			= $currency;
					$supplyParams['goods_exchange']		= $exchange;
					$supplyParams['fi_exchange']		= $exchange;
					$supplyParams['ea']					= $ea;
					$supplyParams['supply_price']		= $supply_price;
					$supplyParams['freight_price']		= 0;
					$supplyParams['insurance_price']	= 0;
					$supplyParams['duty_price']			= 0;
					$supplyParams['accessorial_price']	= 0;
					$supplyParams['supply_tax']			= 0;
					$supplyParams['use_tax']			= $use_tax;
					$supplys[$data['option']]			= $this->calculate_supply_info($supplyParams);
				}

				unset($whs);
				if ($warehousings[$trader_seq]['whs']){
					$whs								= $warehousings[$trader_seq]['whs'];
					$whs['total_ea']					+= $ea;
					$whs['total_goods_price']			+= $supplys[$data['option']]['goods_price'];
					$whs['total_cif_price']				+= $supplys[$data['option']]['cif_price'];
					$whs['krw_total_supply_price']		+= $supplys[$data['option']]['krw_supply_price'];
					$whs['krw_total_supply_tax']		+= $supplys[$data['option']]['supply_tax'];
					$whs['krw_total_price']				+= $supplys[$data['option']]['krw_total_price'];
				}else{
					// 기본정보
					$whs['whs_type']					= 'E';
					$whs['whs_status']					= '1';
					$whs['trader_seq']					= $trader_seq;
					$whs['currency']					= $currency;
					$whs['goods_exchange']				= $exchange;
					$whs['fi_exchange']					= $exchange;
					$whs['trade_terms']					= 'CIF';
					$whs['wh_seq']						= $wh_seq;
					$whs['sorder_seq']					= '';
					$whs['sorder_code']					= '';
					$whs['total_ea']					= $ea;
					$whs['total_goods_price']			= $supplys[$data['option']]['goods_price'];
					$whs['total_freight_price']			= '0';
					$whs['total_insurance_price']		= '0';
					$whs['total_cif_price']				= $supplys[$data['option']]['cif_price'];
					$whs['total_duty_price']			= '0';
					$whs['total_accessorial_price']		= '0';
					$whs['krw_total_supply_price']		= $supplys[$data['option']]['krw_supply_price'];
					$whs['krw_total_supply_tax']		= $supplys[$data['option']]['supply_tax'];
					$whs['krw_total_price']				= $supplys[$data['option']]['krw_total_price'];
					$whs['chg_log']						= '<div>' . date('Y-m-d H:i:s') . ' '
														. $this->managerInfo['mname']
														. '(' . $this->managerInfo['manager_id'] . ')의 '
														. '출고(' . $export_code . ')로 인해 '
														. '자동입고완료되었습니다. '
														. '(' . $_SERVER['REMOTE_ADDR'] . ')</div>';
				}
				$warehousings[$trader_seq]['whs']		= $whs;

				// 상품정보
				unset($whs_goods);
				$whs_goods['goods_seq']					= $goods_seq;
				$whs_goods['option_type']				= $option_type;
				$whs_goods['option_seq']				= $option_seq;
				$whs_goods['goods_code']				= $goods[$goods_seq]['goods_code'];
				$whs_goods['goods_name']				= $goods[$goods_seq]['goods_name'];
				$whs_goods['option_name']				= $option_name;
				$whs_goods['supply_goods_name']			= $supply_goods_name;
				$whs_goods['location_code']				= $location_code;
				$whs_goods['location_position']			= '1-1-1';
				$whs_goods['weight']					= '0';
				$whs_goods['use_tax']					= $supplys[$data['option']]['use_tax'];
				$whs_goods['ea']						= $supplys[$data['option']]['ea'];
				$whs_goods['supply_price']				= $supplys[$data['option']]['supply_price'];
				$whs_goods['freight_price']				= $supplys[$data['option']]['freight_price'];
				$whs_goods['insurance_price']			= $supplys[$data['option']]['insurance_price'];
				$whs_goods['cif_price']					= $supplys[$data['option']]['cif_price'];
				$whs_goods['duty_price']				= $supplys[$data['option']]['duty_price'];
				$whs_goods['accessorial_price']			= $supplys[$data['option']]['accessorial_price'];
				$whs_goods['krw_supply_price']			= $supplys[$data['option']]['krw_supply_price'];
				$whs_goods['supply_tax']				= $supplys[$data['option']]['supply_tax'];

				$warehousings[$trader_seq]['goods'][]	= $whs_goods;
			}
		}

		// 발주/입고 생성
		if ($warehousings) foreach($warehousings as $trader_seq => $data){
			// 수동[임의] 발주서 추가
			$exResult					= $this->save_except_sorder($data['whs'], $data['goods']);
			$data['whs']['sorder_seq']	= $exResult['sorder_seq'];
			$data['whs']['sorder_code']	= $exResult['sorder_code'];

			// 입고 내역서 생성
			$saveData					= $this->save_warehousing($data['whs']);
			$data['whs']['whs_seq']		= $saveData['whs_seq'];
			$data['whs']['whs_code']	= $saveData['whs_code'];
			$result						= $this->save_warehousing_goods($data['whs'], $data['goods'], '1');

			$this->change_store_stock($result['target'], $result['wh'], '');
		}
	}

	########## ↑↑ 출고/반품(-출고) ↑↑ ##### ↓↓ 수불부 ↓↓ ##########

	// 수불부 상세 데이터
	public function save_ledger_detail($params, $prevParams = array(), $ldg_date = ''){

		if	($this->chkScmConfig()){

			if	(!$ldg_date)	$ldg_date	= date('Y-m-d');

			// 창고 + 상품 정보가 없는 수불부는 있을 수 없음.
			if	($params['wh_seq'] && $params['goods_seq'] && $params['option_type'] && $params['option_seq']){

				// 환율 정보
				if	(!$this->exchange_cfg)	$this->exchange_cfg	= config_load('exchange');

				// 변경자
				if	(!$this->managerInfo)			$this->managerInfo	= $this->session->userdata('manager');
				if	(!isset($params['changer']))	$params['changer']	= $this->managerInfo['manager_seq'];
				if	(!$params['changer'])			$params['changer']	= '0';	// 0 = system

				// 해당 상품의 마지막 수불부 데이터를 추출
				$sql					= "select * from fm_scm_ledger_detail
										where goods_seq = ? and option_type = ? and option_seq = ?
										order by ldg_detail_seq desc limit 1";
				$query					= $this->db->query($sql, array($params['goods_seq'],
																		$params['option_type'],
																		$params['option_seq']));
				$pre_goods_data			= $query->row_array();
				$prev_ea				= $pre_goods_data['cur_ea'];
				$prev_supply_price		= $pre_goods_data['cur_supply_price'];

				// 해당 상품 해당 창고의 마지막 수불부 데이터를 추출
				$sql					= "select * from fm_scm_ledger_detail
										where wh_seq = ? and goods_seq = ? and option_type = ? and
										option_seq = ? order by ldg_detail_seq desc limit 1";
				$query					= $this->db->query($sql, array($params['wh_seq'],
																		$params['goods_seq'],
																		$params['option_type'],
																		$params['option_seq']));
				$pre_wh_data			= $query->row_array();
				$wh_prev_ea				= $pre_wh_data['wh_cur_ea'];
				$wh_prev_supply_price	= $pre_wh_data['wh_cur_supply_price'];

				// 한화로 환전
				$krw_supply_price		= $params['supply_price'];
				if	(strtoupper($params['supply_price_type']) != 'KRW'){
					$krw_supply_price	= $this->krw_exchange($params['supply_price_type'], $params['supply_price']);
				}

				// 상품 현재 평균 매입가
				if ($params['change_kind'] == 'return' || ($params['change_kind'] != 'carryingout' && $params['change_type'] == 'in')){
					unset($tmpArr);
					$tmpArr[]				= array('ea' => $params['ea'], 'supply_price' => $krw_supply_price);
					$tmpArr[]				= array('ea' => $prev_ea, 'supply_price' => $prev_supply_price);
					$cur_supply_price		= $this->calculate_supply_price_average($tmpArr);
				}else{
					$cur_supply_price		= $prev_supply_price;
				}

				// 창고별 상품 현재 평균 매입가
				if ($params['change_kind'] == 'return' || ($params['change_kind'] != 'carryingout' && $params['change_type'] == 'in')){
					unset($tmpArr);
					$tmpArr[]				= array('ea' => $params['ea'], 'supply_price' => $krw_supply_price);
					$tmpArr[]				= array('ea' => $wh_prev_ea, 'supply_price' => $wh_prev_supply_price);
					$wh_cur_supply_price	= $this->calculate_supply_price_average($tmpArr);
				}else{
					$wh_cur_supply_price	= $wh_prev_supply_price;
				}

				// 현재 재고 계산
				$wh_cur_ea				= $wh_prev_ea + $params['ea'];
				if	($params['change_type'] == 'out')	$wh_cur_ea	= $wh_prev_ea - $params['ea'];
				$cur_ea					= $prev_ea + $params['ea'];
				if	($params['change_type'] == 'out')	$cur_ea	= $prev_ea - $params['ea'];

				unset($insParams);
				$insParams['ldg_date']				= $ldg_date;
				$insParams['change_kind']			= $params['change_kind'];
				$insParams['change_type']			= $params['change_type'];
				$insParams['change_seq']			= $params['change_seq'];
				$insParams['export_code']			= $params['export_code'];
				$insParams['return_code']			= $params['return_code'];
				$insParams['trader_seq']			= $params['trader_seq'];
				$insParams['wh_seq']				= $params['wh_seq'];
				$insParams['location_position']		= $params['location_position'];
				$insParams['goods_seq']				= $params['goods_seq'];
				$insParams['goods_code']			= $params['goods_code'];
				$insParams['goods_name']			= $params['goods_name'];
				$insParams['option_type']			= $params['option_type'];
				$insParams['option_seq']			= $params['option_seq'];
				$insParams['option_name']			= $params['option_name'];
				$insParams['ea']					= $this->ifnull($params['ea'], '0');
				$insParams['supply_price_type']		= $params['supply_price_type'];
				$insParams['exchange_price']		= $this->ifnull($params['exchange_price'], '0');
				$insParams['supply_price']			= $this->ifnull($params['supply_price'], '0');
				$insParams['krw_supply_price']		= $this->ifnull($krw_supply_price, '0');
				$insParams['wh_pre_ea']				= $this->ifnull($wh_prev_ea, '0');
				$insParams['wh_cur_ea']				= $this->ifnull($wh_cur_ea, '0');
				$insParams['wh_pre_supply_price']	= $this->ifnull($wh_prev_supply_price, '0');
				$insParams['wh_cur_supply_price']	= $this->ifnull($wh_cur_supply_price, '0');
				$insParams['pre_ea']				= $this->ifnull($prev_ea, '0');
				$insParams['cur_ea']				= $this->ifnull($cur_ea, '0');
				$insParams['pre_supply_price']		= $this->ifnull($prev_supply_price, '0');
				$insParams['cur_supply_price']		= $this->ifnull($cur_supply_price, '0');
				$insParams['changer']				= $params['changer'];
				$insParams['change_date']			= date('Y-m-d H:i:s');
				$this->db->insert('fm_scm_ledger_detail', $insParams);
				$ldg_detail_seq	= $this->db->insert_id();
			}
		}

		return $ldg_detail_seq;
	}

	// 오늘날짜 수불부 집계 재 생성
	public function save_ledger_today($wh_seq = '', $goodsData = array(), $absolute_date = ''){

		if	($this->chkScmConfig()){
			$yesterday		= date('Y-m-d', strtotime('-1 day'));
			$today			= date('Y-m-d');
			if	($absolute_date){
				$yesterday	= date('Y-m-d', strtotime('-1 day', strtotime($absolute_date)));
				$today		= $absolute_date;
			}

			// 창고 검색
			if	($wh_seq > 0){
				$addWhere	.= " and wh_seq = '" . $wh_seq . "' ";
			}
			// 상품 검색
			if	(is_array($goodsData) && count($goodsData) > 0){
				foreach($goodsData as $k => $goods){
					$goodsWhere[]	= " ( goods_seq = '" . $goods['goods_seq'] . "' "
									. " and option_type = '" . $goods['option_type'] . "' "
									. " and option_seq = '" . $goods['option_seq'] . "' ) ";
				}
				$addWhere			.= " and ( " . implode(" or ", $goodsWhere) . " ) ";
			}

			// 오늘 수불부 상세 목록 추출 ( 창고+상품 단위 )
			$sql	= "select goods_seq, goods_code, goods_name,
							option_type, option_seq, option_name, wh_seq,
							SUM(IF(change_type='in',IFNULL(ea, 0),0))						as in_ea,
							SUM(IF(change_type='out',IFNULL(ea, 0),0))						as out_ea,
							SUM(IF(change_type='in',ABS(IFNULL(krw_supply_price, 0))*IFNULL(ea, 0),0))		as in_supply_price,
							SUM(IF(change_type='out',ABS(IFNULL(krw_supply_price, 0))*IFNULL(ea, 0),0))	as out_supply_price
						from fm_scm_ledger_detail
						where ldg_date = ? " . $addWhere . "
						group by goods_seq, option_type, option_seq, wh_seq";
			$query	= $this->db->query($sql, array($today));
			$result	= $query->result_array();
			if	($result) foreach($result as $k => $detail){

				// 상품 전기 데이터 추출 ( 가장 최근 마지막 데이터 1개 )
				unset($whrParams);
				$whrParams[]	= $detail['goods_seq'];
				$whrParams[]	= $detail['option_type'];
				$whrParams[]	= $detail['option_seq'];
				$whrParams[]	= $today;
				$sql			= "select * from fm_scm_ledger
									where goods_seq = ? and option_type = ?
									and option_seq = ? and ldg_date < ?
									order by ldg_seq desc limit 1 ";
				$query			= $this->db->query($sql, $whrParams);
				$pre_data		= $query->row_array();

				$sql			= "select * from fm_scm_ledger_detail
									where goods_seq = ? and option_type = ?
									and option_seq = ? and ldg_date = ?
									order by ldg_detail_seq desc limit 1 ";
				$query			= $this->db->query($sql, $whrParams);
				$lst_data		= $query->row_array();

				// 창고 전기 데이터 추출 ( 가장 최근 마지막 데이터 1개 )
				unset($whrParams);
				$whrParams[]	= $detail['goods_seq'];
				$whrParams[]	= $detail['option_type'];
				$whrParams[]	= $detail['option_seq'];
				$whrParams[]	= $detail['wh_seq'];
				$sql			= "select * from fm_scm_ledger
									where goods_seq = ? and option_type = ?
									and option_seq = ? and wh_seq = ?
									order by ldg_seq desc limit 1 ";
				$query			= $this->db->query($sql, $whrParams);
				$wh_pre_data	= $query->row_array();

				// 창고 금일 마지막 수불부 상세 데이터
				$whrParams[]	= $today;
				$sql			= "select * from fm_scm_ledger_detail
									where goods_seq = ? and option_type = ?
									and option_seq = ? and wh_seq = ?
									and ldg_date = ?
									order by ldg_detail_seq desc limit 1 ";
				$query			= $this->db->query($sql, $whrParams);
				$wh_lst_data	= $query->row_array();

				// 수량상세 집계 추가
				unset($eaParams, $eainfo);
				$eaParams['ldg_date']				= $today;
				$eaParams['goods_seq']				= $detail['goods_seq'];
				$eaParams['option_type']			= $detail['option_type'];
				$eaParams['option_seq']				= $detail['option_seq'];
				$eaParams['wh_seq']					= $detail['wh_seq'];
				$eainfo								= $this->get_eainfo_to_ledger_detail($eaParams);
				$eainfo								= $eainfo[0];
				$eainfo['in_total']					= $eainfo['warehousing_ea'] + $eainfo['carryingout_ea']
													+ $eainfo['movein_ea'] + $eainfo['revisionin_ea'];
				$eainfo['out_total']				= $eainfo['export_ea'] + $eainfo['return_ea']
													+ $eainfo['moveout_ea'] + $eainfo['revisionout_ea']
													+ $eainfo['revisionbad_ea'];

				unset($insParams, $upParams, $whrParams);
				if	($wh_pre_data['ldg_date'] == $today){
					// update
					unset($whrParams);
					$whrParams['ldg_date']				= $today;
					$whrParams['goods_seq']				= $detail['goods_seq'];
					$whrParams['option_type']			= $detail['option_type'];
					$whrParams['option_seq']			= $detail['option_seq'];
					$whrParams['wh_seq']				= $detail['wh_seq'];

					unset($upParams);
					$upParams['in_ea']					= $this->ifnull($detail['in_ea'], '0');
					$upParams['out_ea']					= $this->ifnull($detail['out_ea'], '0');
					$upParams['in_supply_price']		= $this->ifnull($detail['in_supply_price'], '0');
					$upParams['out_supply_price']		= $this->ifnull($detail['out_supply_price'], '0');
					$upParams['wh_cur_ea']				= $this->ifnull($wh_lst_data['wh_cur_ea'], '0');
					$upParams['wh_cur_supply_price']	= $this->ifnull($wh_lst_data['wh_cur_supply_price'], '0');
					$upParams['cur_ea']					= $this->ifnull($lst_data['cur_ea'], '0');
					$upParams['cur_supply_price']		= $this->ifnull($lst_data['cur_supply_price'], '0');
					$this->db->where($whrParams);
					$this->db->update('fm_scm_ledger', $upParams);

					unset($upParams);
					$upParams['warehousing_ea']			= $this->ifnull($eainfo['warehousing_ea'], '0');
					$upParams['carryingout_ea']			= $this->ifnull($eainfo['carryingout_ea'], '0');
					$upParams['movein_ea']				= $this->ifnull($eainfo['movein_ea'], '0');
					$upParams['revisionin_ea']			= $this->ifnull($eainfo['revisionin_ea'], '0');
					$upParams['export_ea']				= $this->ifnull($eainfo['export_ea'], '0');
					$upParams['return_ea']				= $this->ifnull($eainfo['return_ea'], '0');
					$upParams['moveout_ea']				= $this->ifnull($eainfo['moveout_ea'], '0');
					$upParams['revisionout_ea']			= $this->ifnull($eainfo['revisionout_ea'], '0');
					$upParams['revisionbad_ea']			= $this->ifnull($eainfo['revisionbad_ea'], '0');
					$upParams['in_total']				= $this->ifnull($eainfo['in_total'], '0');
					$upParams['out_total']				= $this->ifnull($eainfo['out_total'], '0');
					$upParams['wh_cur_ea']				= $this->ifnull($wh_lst_data['wh_cur_ea'], '0');
					$upParams['cur_ea']					= $this->ifnull($lst_data['cur_ea'], '0');
					$this->db->where($whrParams);
					$this->db->update('fm_scm_ledger_ea', $upParams);
				}else{
					// insert
					unset($insParams);
					$insParams['ldg_date']				= $today;
					$insParams['ldg_year']				= date('Y', strtotime($today));
					$insParams['ldg_month']				= date('m', strtotime($today));
					$insParams['goods_seq']				= $detail['goods_seq'];
					$insParams['goods_code']			= $detail['goods_code'];
					$insParams['goods_name']			= $detail['goods_name'];
					$insParams['option_type']			= $detail['option_type'];
					$insParams['option_seq']			= $detail['option_seq'];
					$insParams['option_name']			= $detail['option_name'];
					$insParams['wh_seq']				= $detail['wh_seq'];
					$insParams['in_ea']					= $detail['in_ea'];
					$insParams['out_ea']				= $detail['out_ea'];
					$insParams['in_supply_price']		= $detail['in_supply_price'];
					$insParams['out_supply_price']		= $detail['out_supply_price'];
					$insParams['wh_pre_ea']				= $this->ifnull($wh_pre_data['wh_cur_ea'], '0');
					$insParams['wh_cur_ea']				= $this->ifnull($wh_lst_data['wh_cur_ea'], '0');
					$insParams['wh_pre_supply_price']	= $this->ifnull($wh_pre_data['wh_cur_supply_price'], '0');
					$insParams['wh_cur_supply_price']	= $this->ifnull($wh_lst_data['wh_cur_supply_price'], '0');
					$insParams['pre_ea']				= $this->ifnull($pre_data['cur_ea'], '0');
					$insParams['cur_ea']				= $this->ifnull($lst_data['cur_ea'], '0');
					$insParams['pre_supply_price']		= $this->ifnull($pre_data['cur_supply_price'], '0');
					$insParams['cur_supply_price']		= $this->ifnull($lst_data['cur_supply_price'], '0');
					$insParams['regist_date']			= date('Y-m-d H:i:s');
					$this->db->insert('fm_scm_ledger', $insParams);

					unset($insParams);
					$insParams['ldg_date']				= $today;
					$insParams['ldg_year']				= date('Y', strtotime($today));
					$insParams['ldg_month']				= date('m', strtotime($today));
					$insParams['goods_seq']				= $detail['goods_seq'];
					$insParams['goods_code']			= $detail['goods_code'];
					$insParams['goods_name']			= $detail['goods_name'];
					$insParams['option_type']			= $detail['option_type'];
					$insParams['option_seq']			= $detail['option_seq'];
					$insParams['option_name']			= $detail['option_name'];
					$insParams['wh_seq']				= $detail['wh_seq'];
					$insParams['warehousing_ea']		= $this->ifnull($eainfo['warehousing_ea'], '0');
					$insParams['carryingout_ea']		= $this->ifnull($eainfo['carryingout_ea'], '0');
					$insParams['movein_ea']				= $this->ifnull($eainfo['movein_ea'], '0');
					$insParams['revisionin_ea']			= $this->ifnull($eainfo['revisionin_ea'], '0');
					$insParams['export_ea']				= $this->ifnull($eainfo['export_ea'], '0');
					$insParams['return_ea']				= $this->ifnull($eainfo['return_ea'], '0');
					$insParams['moveout_ea']			= $this->ifnull($eainfo['moveout_ea'], '0');
					$insParams['revisionout_ea']		= $this->ifnull($eainfo['revisionout_ea'], '0');
					$insParams['revisionbad_ea']		= $this->ifnull($eainfo['revisionbad_ea'], '0');
					$insParams['in_total']				= $this->ifnull($eainfo['in_total'], '0');
					$insParams['out_total']				= $this->ifnull($eainfo['out_total'], '0');
					$insParams['wh_pre_ea']				= $this->ifnull($wh_pre_data['wh_cur_ea'], '0');
					$insParams['wh_cur_ea']				= $this->ifnull($wh_lst_data['wh_cur_ea'], '0');
					$insParams['pre_ea']				= $this->ifnull($pre_data['cur_ea'], '0');
					$insParams['cur_ea']				= $this->ifnull($lst_data['cur_ea'], '0');
					$this->db->insert('fm_scm_ledger_ea', $insParams);
				}

				// 동일 일자, 동일 상품의 총재고에 대한 현재 재고 및 현재 평균 매입가를 맞춤
				unset($upParams, $whrParams);
				$upParams['cur_ea']					= $this->ifnull($lst_data['cur_ea'], '0');
				$upParams['cur_supply_price']		= $this->ifnull($lst_data['cur_supply_price'], '0');
				$whrParams['ldg_date']				= $today;
				$whrParams['goods_seq']				= $detail['goods_seq'];
				$whrParams['option_type']			= $detail['option_type'];
				$whrParams['option_seq']			= $detail['option_seq'];
				$this->db->where($whrParams);
				$this->db->update('fm_scm_ledger', $upParams);
			}
		}
	}

	// 월별 수불부 집계 요약 데이터 추출
	public function get_ledger_month_cronstatus($year = '', $month = ''){
		if	(!$year || !$month){
			$year	= date('Y');
			if	(date('n') == 1){
				$month	= 12;
				$year	= $year - 1;
			}else{
				$month	= date('n') - 1;
			}
		}

		$sql	= "select * from fm_scm_ledger_month_cronstatus "
				. "where ldg_year = ? and ldg_month = ? limit 1";
		$query	= $this->db->query($sql, array($year, $month));
		$result	= $query->row_array();

		return $result;
	}

	// 월별 수불부 집계 요약 전체 저장
	public function save_ledger_month_cronstatus($year = '', $month = '', $cron_status = 0){
		if	(!$year || !$month){
			$year	= date('Y');
			if	(date('n') == 1){
				$month	= 12;
				$year	= $year - 1;
			}else{
				$month	= date('n') - 1;
			}
		}

		// 기존 상태값 삭제
		$params['ldg_year']			= $year;
		$params['ldg_month']		= $month;
		$this->db->where($params);
		$this->db->delete('fm_scm_ledger_month_cronstatus');

		// 상태값 신규 추가
		$params['ldg_year']			= $year;
		$params['ldg_month']		= $month;
		$params['cron_status']		= '1';
		$params['complete_date']	= date('Y-m-d H:i:s');
		$this->db->insert('fm_scm_ledger_month_cronstatus', $params);
	}

	// dummy 수불 제거용
	public function delete_ledger_month($year = '', $month = ''){
		if	(!$year || !$month){
			$year	= date('Y');
			if	(date('n') == 1){
				$month	= 12;
				$year	= $year - 1;
			}else{
				$month	= date('n') - 1;
			}
		}

		unset($whrParams);
		$whrParams['ldg_year']	= $year;
		$whrParams['ldg_month']	= $month;
		$this->db->where($whrParams);
		$this->db->delete('fm_scm_ledger_month');
		$this->db->where($whrParams);
		$this->db->delete('fm_scm_ledger_ea_month');
	}

	// 월별 수불부 집계 테이블
	public function save_ledger_month($year = '', $month = '', $sc = array()){
		if	(!$year || !$month){
			$year	= date('Y');
			if	(date('n') == 1){
				$month	= 12;
				$year	= $year - 1;
			}else{
				$month	= date('n') - 1;
			}
		}

		// 전체 창고목록 추출
		$warehouses			= $this->get_warehouse(array('inclusion_move' => 'Y'));
		$warehousesCnt		= count($warehouses);
		if	($warehousesCnt > 0){

			// 특정 조건에 따른 추출 ( 특정 상품에 대해서만 쌓을때 )
			if	($sc['goods_seq']){
				$optAddWhere	.= " and g.goods_seq = '" . $sc['goods_seq'] . "' ";
				$subAddWhere	.= " and g.goods_seq = '" . $sc['goods_seq'] . "' ";
			}
			if	($sc['option_seq']){
				$optAddWhere	.= " and o.option_seq = '" . $sc['option_seq'] . "' ";
			}
			if	($sc['suboption_seq']){
				$subAddWhere	.= " and o.suboption_seq = '" . $sc['suboption_seq'] . "' ";
			}

			// 모든 필수옵션 정보 추출
			$sql	= "select g.goods_seq, g.goods_name, "
					. "o.option_seq, 'option' as option_type, "
					. "concat(IFNULL(g.goods_code, ''), IFNULL(o.optioncode1, ''), "
					. "IFNULL(o.optioncode2, ''), IFNULL(o.optioncode3, ''), IFNULL(o.optioncode4, ''), "
					. "IFNULL(o.optioncode5, '')) as goods_code, "
					. "concat(IFNULL(o.option1, ''), IFNULL(o.option2, ''), IFNULL(o.option3, ''), "
					. "IFNULL(o.option4, ''), IFNULL(o.option5, '')) as option_name "
					. "from fm_goods as g, fm_goods_option as o "
					. "where g.goods_seq = o.goods_seq " . $optAddWhere;
			$res	= $this->direct_sql_query($sql);
			while ($goods = $this->direct_fetch_assoc($res)){
				for	($w = 0; $w < $warehousesCnt; $w++){
					$wh_seq		= $warehouses[$w]['wh_seq'];
					$this->save_ledger_month_wh_goods($year, $month, $wh_seq, $goods);
				}
				$this->save_ledger_month_goods($year, $month, $goods);
			}

			if	($this->scm_use_suboption_mode){
				// 모든 추가옵션 정보 추출
				$sql	= "select g.goods_seq, g.goods_name, "
						. "o.suboption_seq as option_seq, 'suboption' as option_type, "
						. "concat(IFNULL(g.goods_code, ''), IFNULL(o.suboption_code, '')) as goods_code, "
						. "o.suboption as option_name "
						. "from fm_goods as g, fm_goods_suboption as o "
						. "where g.goods_seq = o.goods_seq " . $subAddWhere;
				$res	= $this->direct_sql_query($sql);
				while ($goods = $this->direct_fetch_assoc($res)){
					for	($w = 0; $w < $warehousesCnt; $w++){
						$wh_seq		= $warehouses[$w]['wh_seq'];
						$this->save_ledger_month_wh_goods($year, $month, $wh_seq, $goods);
					}
					$this->save_ledger_month_goods($year, $month, $goods);
				}
			}
		}
	}

	// 상품별 해당월 수불데이터 update
	public function save_ledger_month_goods($year, $month, $goods){
		// 전기 년월 추출
		if	($month == 1){
			$pre_year	= $year - 1;
			$pre_month	= 12;
		}else{
			$pre_year	= $year;
			$pre_month	= $month - 1;
		}

		// 전체 전기 재고 및 매입가 추출
		unset($binds);
		$binds[]	= $pre_year;
		$binds[]	= $pre_month;
		$binds[]	= $goods['goods_seq'];
		$binds[]	= $goods['option_type'];
		$binds[]	= $goods['option_seq'];
		$sql		= "select "
					. "cur_ea, cur_supply_price "
					. "from fm_scm_ledger_month "
					. "where ldg_year = ? and ldg_month = ? "
					. "and goods_seq = ? and option_type = ? and option_seq = ? ";
		$query		= $this->db->query($sql, $binds);
		$preData	= $query->row_array();
		if	(!$preData['cur_ea']){
			$preData['cur_ea']				= 0;
			$preData['cur_supply_price']	= 0;
		}

		// 일별 수불부에서 월별 수불 집계함
		unset($binds);
		$binds[]	= $year;
		$binds[]	= str_pad($month, 2, "0", STR_PAD_LEFT);
		$binds[]	= $goods['goods_seq'];
		$binds[]	= $goods['option_type'];
		$binds[]	= $goods['option_seq'];
		$sql		= "select "
					. "SUM(IFNULL(in_ea, 0))			as in_ea, "
					. "SUM(IFNULL(out_ea, 0))			as out_ea, "
					. "SUM(IFNULL(in_supply_price, 0))	as in_supply_price, "
					. "SUM(IFNULL(out_supply_price, 0))	as out_supply_price "
					. "from fm_scm_ledger "
					. "where ldg_year = ? and ldg_month = ? "
					. "and goods_seq = ? and option_type = ? and option_seq = ? ";
		$query		= $this->db->query($sql, $binds);
		$inoutData	= $query->row_array();
		if	(!$inoutData['in_ea']){
			$inoutData['in_ea']				= 0;
			$inoutData['in_supply_price']	= 0;
		}
		if	(!$inoutData['out_ea']){
			$inoutData['out_ea']			= 0;
			$inoutData['out_supply_price']	= 0;
		}

		// 당기 재고/매입가 계산
		$cur_ea						= $preData['cur_ea'] + $inoutData['in_ea'] - $inoutData['out_ea'];
		$cur_supply_price			= 0;
		$tmp_supply_price			= ($preData['cur_ea'] * $preData['cur_supply_price']) + $inoutData['in_supply_price'] - $inoutData['out_supply_price'];
		if	($tmp_supply_price > 0 && $cur_ea > 0){
			unset($tmpArr);
			$tmpArr[]				= array('ea' => $cur_ea, 'tot_supply_price' => $tmp_supply_price);
			$cur_supply_price		= $this->calculate_supply_price_average($tmpArr);
		}

		unset($whParams);
		$whParams['ldg_year']			= $year;
		$whParams['ldg_month']			= str_pad($month, 2, "0", STR_PAD_LEFT);
		$whParams['goods_seq']			= $goods['goods_seq'];
		$whParams['option_type']		= $goods['option_type'];
		$whParams['option_seq']			= $goods['option_seq'];

		unset($upParams);
		$upParams['pre_ea']				= $preData['cur_ea'];
		$upParams['pre_supply_price']	= $preData['cur_supply_price'];
		$upParams['cur_ea']				= $cur_ea;
		$upParams['cur_supply_price']	= $cur_supply_price;
		$this->db->update('fm_scm_ledger_month', $upParams, $whParams);

		unset($upParams);
		$upParams['pre_ea']				= $preData['cur_ea'];
		$upParams['cur_ea']				= $cur_ea;
		$this->db->update('fm_scm_ledger_ea_month', $upParams, $whParams);
	}

	// 창고별 + 상품별 해당월 수불 데이터 저장
	public function save_ledger_month_wh_goods($year, $month, $wh_seq, $goods){
		// 전기 년월 추출
		if	($month == 1){
			$pre_year	= $year - 1;
			$pre_month	= 12;
		}else{
			$pre_year	= $year;
			$pre_month	= $month - 1;
		}

		// 전체 전기 재고 및 매입가 추출
		unset($binds);
		$binds[]	= $pre_year;
		$binds[]	= $pre_month;
		$binds[]	= $wh_seq;
		$binds[]	= $goods['goods_seq'];
		$binds[]	= $goods['option_type'];
		$binds[]	= $goods['option_seq'];
		$sql		= "select "
					. "wh_cur_ea, wh_cur_supply_price "
					. "from fm_scm_ledger_month "
					. "where ldg_year = ? and ldg_month = ? and wh_seq = ? "
					. "and goods_seq = ? and option_type = ? and option_seq = ? ";
		$query		= $this->db->query($sql, $binds);
		$preData	= $query->row_array();
		if	(!$preData['wh_cur_ea']){
			$preData['wh_cur_ea']			= 0;
			$preData['wh_cur_supply_price']	= 0;
		}

		// 일별 수불부에서 월별 수불 집계함
		unset($binds);
		$binds[]	= $year;
		$binds[]	= str_pad($month, 2, "0", STR_PAD_LEFT);
		$binds[]	= $wh_seq;
		$binds[]	= $goods['goods_seq'];
		$binds[]	= $goods['option_type'];
		$binds[]	= $goods['option_seq'];
		$sql		= "select "
					. "SUM(IFNULL(in_ea, 0))			as in_ea, "
					. "SUM(IFNULL(out_ea, 0))			as out_ea, "
					. "SUM(IFNULL(in_supply_price, 0))	as in_supply_price, "
					. "SUM(IFNULL(out_supply_price, 0))	as out_supply_price "
					. "from fm_scm_ledger "
					. "where ldg_year = ? and ldg_month = ? and wh_seq = ? "
					. "and goods_seq = ? and option_type = ? and option_seq = ? ";
		$query		= $this->db->query($sql, $binds);
		$inoutData	= $query->row_array();
		if	(!$inoutData['in_ea']){
			$inoutData['in_ea']				= 0;
			$inoutData['in_supply_price']	= 0;
		}
		if	(!$inoutData['out_ea']){
			$inoutData['out_ea']			= 0;
			$inoutData['out_supply_price']	= 0;
		}

		// 당기 재고/매입가 계산
		$wh_cur_ea					= $preData['wh_cur_ea'] + $inoutData['in_ea'] - $inoutData['out_ea'];
		$wh_cur_supply_price		= 0;
		$tmp_supply_price			= ($preData['wh_cur_ea'] * $preData['wh_cur_supply_price']) + $inoutData['in_supply_price'] - $inoutData['out_supply_price'];
		if	($tmp_supply_price > 0 && $wh_cur_ea > 0){
			unset($tmpArr);
			$tmpArr[]				= array('ea' => $wh_cur_ea, 'tot_supply_price' => $tmp_supply_price);
			$wh_cur_supply_price	= $this->calculate_supply_price_average($tmpArr);
		}


		unset($insParams);
		$insParams['ldg_year']				= $year;
		$insParams['ldg_month']				= $month;
		$insParams['goods_seq']				= $goods['goods_seq'];
		$insParams['option_type']			= $goods['option_type'];
		$insParams['option_seq']			= $goods['option_seq'];
		$insParams['wh_seq']				= $wh_seq;
		$insParams['in_ea']					= $inoutData['in_ea'];
		$insParams['in_supply_price']		= $inoutData['in_supply_price'];
		$insParams['out_ea']				= $inoutData['out_ea'];
		$insParams['out_supply_price']		= $inoutData['out_supply_price'];
		$insParams['wh_pre_ea']				= $preData['wh_cur_ea'];
		$insParams['wh_pre_supply_price']	= $preData['wh_cur_supply_price'];
		$insParams['wh_cur_ea']				= $wh_cur_ea;
		$insParams['wh_cur_supply_price']	= $wh_cur_supply_price;
		$insParams['goods_code']			= $goods['goods_code'];
		$insParams['goods_name']			= $goods['goods_name'];
		$insParams['option_name']			= $goods['option_name'];
		$this->db->insert('fm_scm_ledger_month', $insParams);

		// 일별 수량상세 수불부에서 월별 수량상세 수불 집계함
		unset($binds);
		$binds[]	= $year;
		$binds[]	= str_pad($month, 2, "0", STR_PAD_LEFT);
		$binds[]	= $wh_seq;
		$binds[]	= $goods['goods_seq'];
		$binds[]	= $goods['option_type'];
		$binds[]	= $goods['option_seq'];
		$sql		= "select "
					. "SUM(IFNULL(warehousing_ea, 0))			as warehousing_ea, "
					. "SUM(IFNULL(carryingout_ea, 0))			as carryingout_ea, "
					. "SUM(IFNULL(movein_ea, 0))				as movein_ea, "
					. "SUM(IFNULL(revisionin_ea, 0))			as revisionin_ea, "
					. "SUM(IFNULL(in_total, 0))					as in_total, "
					. "SUM(IFNULL(export_ea, 0))				as export_ea, "
					. "SUM(IFNULL(return_ea, 0))				as return_ea, "
					. "SUM(IFNULL(moveout_ea, 0))				as moveout_ea, "
					. "SUM(IFNULL(revisionout_ea, 0))			as revisionout_ea, "
					. "SUM(IFNULL(revisionbad_ea, 0))			as revisionbad_ea, "
					. "SUM(IFNULL(out_total, 0))				as out_total "
					. "from  fm_scm_ledger_ea "
					. "where ldg_year = ? and ldg_month = ? and wh_seq = ? "
					. "and goods_seq = ? and option_type = ? and option_seq = ? ";
		$query		= $this->db->query($sql, $binds);
		$eaData		= $query->row_array();

		unset($insParams);
		$insParams['ldg_year']				= $year;
		$insParams['ldg_month']				= $month;
		$insParams['goods_seq']				= $goods['goods_seq'];
		$insParams['goods_code']			= $goods['goods_code'];
		$insParams['goods_name']			= $goods['goods_name'];
		$insParams['option_type']			= $goods['option_type'];
		$insParams['option_seq']			= $goods['option_seq'];
		$insParams['option_name']			= $goods['option_name'];
		$insParams['wh_seq']				= $wh_seq;
		$insParams['wh_pre_ea']				= $preData['wh_cur_ea'];
		$insParams['warehousing_ea']		= $eaData['warehousing_ea'];
		$insParams['carryingout_ea']		= $eaData['carryingout_ea'];
		$insParams['movein_ea']				= $eaData['movein_ea'];
		$insParams['revisionin_ea']			= $eaData['revisionin_ea'];
		$insParams['in_total']				= $eaData['in_total'];
		$insParams['export_ea']				= $eaData['export_ea'];
		$insParams['return_ea']				= $eaData['return_ea'];
		$insParams['moveout_ea']			= $eaData['moveout_ea'];
		$insParams['revisionout_ea']		= $eaData['revisionout_ea'];
		$insParams['revisionbad_ea']		= $eaData['revisionbad_ea'];
		$insParams['out_total']				= $eaData['out_total'];
		$insParams['wh_cur_ea']				= $wh_cur_ea;
		$this->db->insert('fm_scm_ledger_ea_month', $insParams);
	}

	// 월별 수불 데이터 추출
	public function get_ledger_month($sc){
		$fromTable				= 'fm_scm_ledger_month';
		if	($sc['sc_display_type'] == 'ea'){
			$fromTable			= 'fm_scm_ledger_ea_month';
		}

		$selectSql				= "select "
								. "ldg_year, "
								. "ldg_month, "
								. "fsl.goods_seq, "
								. "option_type, "
								. "option_seq, "
								. "wh_seq, "
								. "fsl.goods_code, "
								. "fsl.goods_name, "
								. "option_name, "
								. "MIN(ldg_month)					as min_ldg_month, "
								. "MAX(ldg_month)					as max_ldg_month, "
								. "SUM(IFNULL(in_ea, 0))			as in_ea, "
								. "SUM(IFNULL(in_supply_price, 0))	as in_supply_price, "
								. "SUM(IFNULL(out_ea, 0))			as out_ea, "
								. "SUM(IFNULL(out_supply_price, 0))	as out_supply_price ";
		$fromSql				= "from " . $fromTable . " fsl ";
		$whereSql				= "where ldg_year > 0 and ldg_month > 0 and fsl.goods_seq > 0 "
								. "and option_type in ('option', 'suboption') and option_seq > 0 "
								. "and wh_seq > 0 ";
		$gropuSql				= "group by fsl.goods_seq, option_type, option_seq ";
		$orderSql				= "order by fsl.goods_seq ASC";
		$limitSql				= "";
		$pre_ea_fld				= "pre_ea";
		$pre_supply_price_fld	= "pre_supply_price";
		$cur_ea_fld				= "cur_ea";
		$cur_supply_price_fld	= "cur_supply_price";

		if	($sc['sc_display_type'] == 'ea'){
			$selectSql			= "select ldg_year, ldg_month, wh_seq, "
								. "fsl.goods_seq, fsl.goods_code, fsl.goods_name, "
								. "option_type, option_seq, option_name, "
								. "SUM( IFNULL(warehousing_ea , 0))		AS warehousing_ea, "
								. "SUM( IFNULL(carryingout_ea , 0))		AS carryingout_ea, "
								. "SUM( IFNULL(movein_ea , 0))			AS movein_ea, "
								. "SUM( IFNULL(revisionin_ea , 0))		AS revisionin_ea, "
								. "SUM( IFNULL(in_total , 0))			AS in_total, "
								. "SUM( IFNULL(export_ea , 0))			AS export_ea, "
								. "SUM( IFNULL(return_ea , 0))			AS return_ea, "
								. "SUM( IFNULL(moveout_ea , 0))			AS moveout_ea, "
								. "SUM( IFNULL(revisionout_ea , 0))		AS revisionout_ea, "
								. "SUM( IFNULL(revisionbad_ea , 0))		AS revisionbad_ea, "
								. "SUM( IFNULL(out_total , 0))			AS out_total, "
								. "MIN(ldg_month)						AS min_ldg_month, "
								. "MAX(ldg_month)						AS max_ldg_month ";
		}

		// 년도 검색
		if	($sc['sc_year']){
			$whereSql	.= " and ldg_year = ? ";
			$bind[]		= $sc['sc_year'];
		}
		// 월 검색
		if	($sc['sc_month']){
			$whereSql	.= " and ldg_month = ? ";
			$bind[]		= (int)$sc['sc_month'];
		}
		// 월 복수 검색
		if	($sc['sc_month_arr']){
			if	(!is_array($sc['sc_month_arr']))	$sc['sc_month_arr']	= array($sc['sc_month_arr']);
			$whereSql	.= " and ldg_month in ('" . implode("', '", $sc['sc_month_arr']) . "') ";
		}
		// 창고 검색
		if	($sc['sc_wh_seq']){
			$pre_ea_fld				= "wh_pre_ea";
			$pre_supply_price_fld	= "wh_pre_supply_price";
			$cur_ea_fld				= "wh_cur_ea";
			$cur_supply_price_fld	= "wh_cur_supply_price";
			$whereSql				.= " and wh_seq = ? ";
			$bind[]					= $sc['sc_wh_seq'];
		}
		// 분류 검색
		if($sc['scm_category']){
			$fromSql		.= " INNER JOIN fm_goods	as ig on fsl.goods_seq = ig.goods_seq ";
			$whereSql		.= " and ig.scm_category like '".$sc['scm_category']."%' ";
		}
		
		// 상품명, 상품코드, 상품번호
		if($sc['src_key'] && $sc['keyword']){
			$whereSql	.= " and " . $sc['src_key'] . " like '%" . $sc['keyword'] . "%' ";
		}else if($sc['keyword']){
			$whereSql	.= " and (fsl.goods_seq like '" . $sc['keyword'] . "%' or goods_name like '%" . $sc['keyword'] . "%' or goods_code like '%" . $sc['keyword'] . "%') ";
		}
		
		if	(!$sc['sc_all_goods']){
			if	($sc['sc_display_type'] == 'ea'){
				$whereSql	.= " and ( in_total > 0 or out_total > 0 ) ";
			}else{
				$whereSql	.= " and ( in_ea > 0 or out_ea > 0 ) ";
			}
		}
		if	($sc['sc_display_type'] == 'ea'){
			$selectSql	.= ", " . $cur_ea_fld . "				as cur_ea ";
			$sql		= "select "
						. "pre." . $pre_ea_fld . "				as pre_ea, "
						. "fslm.* ";
		}else{
			$sql		= "select "
						. "pre." . $pre_ea_fld . "				as pre_ea, "
						. "pre." . $pre_supply_price_fld . "	as pre_supply_price, "
						. "fslm.* ";
		}

		if($sc['orderby']){
			$orderSql	= "ORDER BY fsl." . $sc['orderby'] . ' ' .$sc['sort'];
		}

		$subSql		= $selectSql . $fromSql . $whereSql . $gropuSql . $orderSql . $limitSql;
		$sql		.= "from (" . $subSql . ")			as fslm "
					. "inner join " . $fromTable . "	as pre "
					. "on ( fslm.ldg_year = pre.ldg_year "
					. "and fslm.min_ldg_month = pre.ldg_month "
					. "and fslm.goods_seq = pre.goods_seq "
					. "and fslm.option_type = pre.option_type "
					. "and fslm.option_seq = pre.option_seq "
					. "and fslm.wh_seq = pre.wh_seq ) ";
		if	($sc['page'] > 0 && $sc['perpage'] > 0){
			$result	= select_page($sc['perpage'], $sc['page'], 10, $sql, $bind);
		}else{
			$query	= $this->db->query($sql, $bind);
			$result	= $query->result_array();
		}

		return $result;
	}

	// 일별 수불 데이터 추출
	public function get_ledger($sc){

		// 창고 검색
		$pre_ea_fld				= 'pre_ea';
		$pre_supply_price_fld	= 'pre_supply_price';
		$cur_ea_fld				= 'cur_ea';
		$cur_supply_price_fld	= 'cur_supply_price';
		if($sc['sc_wh_seq']){
			$pre_ea_fld				= 'wh_pre_ea';
			$pre_supply_price_fld	= 'wh_pre_supply_price';
			$cur_ea_fld				= 'wh_cur_ea';
			$cur_supply_price_fld	= 'wh_cur_supply_price';
			$addSubQueryWhere		= " and wh_seq = '".$sc['sc_wh_seq']."' ";
		}

		// 전기 재고 수량 추출
		$preeaSql1		= "select " . $pre_ea_fld . " as pre_ea
							from fm_scm_ledger
							where
								goods_seq = sld.goods_seq AND option_type = sld.option_type AND
								option_seq = sld.option_seq AND ldg_date = sld.min_ldg_date
								" . $addSubQueryWhere . "
							order by ldg_seq asc
							limit 1";
		$preeaSql2		= "select " . $cur_ea_fld . " as cur_ea
							from fm_scm_ledger
							where
								goods_seq = sld.goods_seq AND option_type = sld.option_type AND
								option_seq = sld.option_seq and ldg_date < '" . $sc['sc_sdate'] . "'
								" . $addSubQueryWhere . "
							order by ldg_seq desc
							limit 1";
		// 전기 평균매입가 추출
		$prepriceSql1	= "select " . $pre_supply_price_fld . " as pre_supply_price
							from fm_scm_ledger
							where
								goods_seq = sld.goods_seq AND option_type = sld.option_type AND
								option_seq = sld.option_seq AND ldg_date = sld.min_ldg_date AND
								sld.ldgStr = 'ledger' " . $addSubQueryWhere . "
							order by ldg_seq asc
							limit 1";
		$prepriceSql2		= "select " . $cur_supply_price_fld . " as cur_supply_price
							from fm_scm_ledger
							where
								goods_seq = sld.goods_seq AND option_type = sld.option_type AND
								option_seq = sld.option_seq and ldg_date < '" . $sc['sc_sdate'] . "'
								" . $addSubQueryWhere . "
							order by ldg_seq desc
							limit 1";
		// 당기 재고 수량 추출
		$cureaSql1		= "select " . $cur_ea_fld . " as cur_ea
							from fm_scm_ledger
							where
								goods_seq = sld.goods_seq AND option_type = sld.option_type AND
								option_seq = sld.option_seq AND ldg_date = sld.max_ldg_date AND
								sld.ldgStr = 'ledger' " . $addSubQueryWhere . "
							order by ldg_seq desc
							limit 1";
		$cureaSql2		= "select " . $cur_ea_fld . " as cur_ea
							from fm_scm_ledger
							where
								goods_seq = sld.goods_seq AND option_type = sld.option_type AND
								option_seq = sld.option_seq and ldg_date < '" . $sc['sc_edate'] . "'
								" . $addSubQueryWhere . "
							order by ldg_seq desc
							limit 1";
		// 당기 평균매입가 추출
		$curpriceSql1	= "select " . $cur_supply_price_fld . " as cur_supply_price
							from fm_scm_ledger
							where
								goods_seq = sld.goods_seq AND option_type = sld.option_type AND
								option_seq = sld.option_seq AND ldg_date = sld.max_ldg_date AND
								sld.ldgStr = 'ledger' " . $addSubQueryWhere . "
							order by ldg_seq desc
							limit 1";
		$curpriceSql2	= "select " . $cur_supply_price_fld . " as cur_supply_price
							from fm_scm_ledger
							where
								goods_seq = sld.goods_seq AND option_type = sld.option_type AND
								option_seq = sld.option_seq and ldg_date < '" . $sc['sc_edate'] . "'
								" . $addSubQueryWhere . "
							order by ldg_seq desc
							limit 1";
		// 가공 From Table
		if	($sc['sc_display_type'] == 'ea'){
			$ldgSelectSql	= "SELECT
								'ledger'				AS ldgStr,
								fsl.goods_seq, fsl.goods_code, fsl.goods_name,
								fsl.option_type, fsl.option_seq, fsl.option_name,
								SUM( IFNULL(fsl.warehousing_ea , 0))	AS warehousing_ea,
								SUM( IFNULL(fsl.carryingout_ea , 0))	AS carryingout_ea,
								SUM( IFNULL(fsl.movein_ea , 0))			AS movein_ea,
								SUM( IFNULL(fsl.revisionin_ea , 0))		AS revisionin_ea,
								SUM( IFNULL(fsl.in_total , 0))			AS in_total,
								SUM( IFNULL(fsl.export_ea , 0))			AS export_ea,
								SUM( IFNULL(fsl.return_ea , 0))			AS return_ea,
								SUM( IFNULL(fsl.moveout_ea , 0))		AS moveout_ea,
								SUM( IFNULL(fsl.revisionout_ea , 0))	AS revisionout_ea,
								SUM( IFNULL(fsl.revisionbad_ea , 0))	AS revisionbad_ea,
								SUM( IFNULL(fsl.out_total , 0))			AS out_total,
								MIN( fsl.ldg_date )						AS min_ldg_date,
								MAX( fsl.ldg_date )						AS max_ldg_date ";
			$ldgFromSql		= "FROM fm_scm_ledger_ea	fsl ";
		}else{
			$ldgSelectSql	= "SELECT
								'ledger'				AS ldgStr,
								fsl.goods_seq, fsl.goods_code, fsl.goods_name,
								fsl.option_type, fsl.option_seq, fsl.option_name, fsl.wh_seq,
								SUM( IFNULL(fsl.in_ea , 0))				AS in_ea,
								SUM( IFNULL(fsl.in_supply_price , 0))	AS in_supply_price,
								SUM( IFNULL(fsl.out_ea , 0))			AS out_ea,
								MIN( fsl.ldg_date )						AS min_ldg_date,
								MAX( fsl.ldg_date )						AS max_ldg_date ";
			$ldgFromSql		= "FROM fm_scm_ledger	fsl ";
		}
		$ldgWhereSql	= "WHERE fsl.ldg_seq > 0 ";
		$ldgGroupSql	= "GROUP BY fsl.goods_seq, fsl.option_type, fsl.option_seq ";
		if($sc['orderby']){
			$ldgOrderSql	= "ORDER BY fsl." . $sc['orderby'] . ' ' .$sc['sort'];
		}else{
			$ldgOrderSql	= "ORDER BY fsl.goods_seq ASC,  ";
		}

		// 기준일
		if($sc['sc_sdate'] && $sc['sc_edate']){
			$ldgWhereSql	.= " and fsl.ldg_date BETWEEN '".$sc['sc_sdate']."' AND '".$sc['sc_edate']."' ";
		}
		// 상품명, 상품코드, 상품번호
		if($sc['src_key'] && $sc['keyword']){
			$ldgWhereSql	.= " and fsl." . $sc['src_key'] . " like '%" . $sc['keyword'] . "%' ";
		}else if($sc['keyword']){
			$ldgWhereSql	.= " and (fsl.goods_seq like '" . $sc['keyword'] . "%' or fsl.goods_name like '%" . $sc['keyword'] . "%' or fsl.goods_code like '%" . $sc['keyword'] . "%') ";
		}
		// 창고 검색
		if($sc['sc_wh_seq']){
			$ldgWhereSql	.= " and fsl.wh_seq = '".$sc['sc_wh_seq']."' ";
		}
		// 분류 검색
		if($sc['scm_category']){
			$ldgFromSql		.= " INNER JOIN fm_goods	as ig on fsl.goods_seq = ig.goods_seq ";
			$ldgWhereSql	.= " and ig.scm_category like '".$sc['scm_category']."%' ";
		}
		if($sc['optioninfo']){
			if	(!is_array($sc['optioninfo']))	$sc['optioninfo']	= array($sc['optioninfo']);
			$ldgWhereSql	.= " and concat(fsl.goods_seq, fsl.option_type, fsl.option_seq) "
							. "in ('" . implode("', '", $sc['optioninfo']) . "') ";
			$optAddWhere	= " and concat(g.goods_seq, 'option', o.option_seq) in "
							. "('" . implode("', '", $sc['optioninfo']) . "') ";
			$subAddWhere	= " and concat(g.goods_seq, 'suboption', s.suboption_seq) in "
							. "('" . implode("', '", $sc['optioninfo']) . "') ";
		}else{
			$optAddWhere	= "and concat(g.goods_seq, 'option', o.option_seq) not in "
							. "(select concat(fsl.goods_seq, 'option', fsl.option_seq) as optioninfo "
							. $ldgFromSql . $ldgWhereSql . " and fsl.option_type = 'option' ) ";
			$subAddWhere	= "and concat(g.goods_seq, 'suboption', s.suboption_seq) not in "
							. "(select concat(fsl.goods_seq, 'suboption', fsl.option_seq) as optioninfo "
							. $ldgFromSql . $ldgWhereSql . " and fsl.option_type = 'suboption' ) ";
		}

		// 정렬
		if($sc['orderby']){
			$subOrderSql	= "ORDER BY fsl." . $sc['orderby'] . ' ' .$sc['sort'];
		}

		// 현재 필수옵션 추출 쿼리
		if	($sc['sc_display_type'] == 'ea'){
			$optSelectSql	= "SELECT
								'goods'					AS ldgStr,
								g.goods_seq,
								concat(IFNULL(g.goods_code, ''), IFNULL(o.optioncode1, ''),
								IFNULL(o.optioncode2, ''), IFNULL(o.optioncode3, ''),
								IFNULL(o.optioncode4, ''), IFNULL(o.optioncode5, ''))	AS goods_code,
								g.goods_name				AS goods_name,
								'option'					AS option_type,
								o.option_seq				AS option_seq,
								concat(IFNULL(o.option1, ''), IFNULL(o.option2, ''),
								IFNULL(o.option3, ''), IFNULL(o.option4, ''),
								IFNULL(o.option5, ''))		AS option_name,
								'0'							AS warehousing_ea,
								'0'							AS carryingout_ea,
								'0'							AS movein_ea,
								'0'							AS revisionin_ea,
								'0'							AS in_total,
								'0'							AS export_ea,
								'0'							AS return_ea,
								'0'							AS moveout_ea,
								'0'							AS revisionout_ea,
								'0'							AS revisionbad_ea,
								'0'							AS out_total,
								'9999-99-99'				AS min_ldg_date,
								'0000-00-00'				AS max_ldg_date ";
		}else{
			$optSelectSql	= "SELECT
								'goods'					AS ldgStr,
								g.goods_seq,
								concat(IFNULL(g.goods_code, ''), IFNULL(o.optioncode1, ''),
								IFNULL(o.optioncode2, ''), IFNULL(o.optioncode3, ''),
								IFNULL(o.optioncode4, ''), IFNULL(o.optioncode5, ''))	AS goods_code,
								g.goods_name				AS goods_name,
								'option'					AS option_type,
								o.option_seq				AS option_seq,
								concat(IFNULL(o.option1, ''), IFNULL(o.option2, ''),
								IFNULL(o.option3, ''), IFNULL(o.option4, ''),
								IFNULL(o.option5, ''))	AS option_name,
								'0'							AS wh_seq,
								'0'							AS in_ea,
								'0'							AS in_supply_price,
								'0'							AS out_ea,
								'9999-99-99'				AS min_ldg_date,
								'0000-00-00'				AS max_ldg_date ";
		}
		$optFromSql		= "FROM fm_goods				AS g "
						. "INNER JOIN fm_goods_option	AS o on g.goods_seq = o.goods_seq ";
		$optWhereSql	= "WHERE g.goods_seq > 0 and g.package_yn = 'n' and g.provider_seq = 1 "
						. "and o.option_seq > 0 and (o.package_count = 0 or o.package_count is NULL) "
						. $optAddWhere;
		$optGroupSql	= "";
		if($sc['orderby']){
			$optOrderSql	= "ORDER BY g." . $sc['orderby'] . ' ' .$sc['sort'];
		}else{
			$optOrderSql	= "ORDER BY g.goods_seq DESC, o.option_seq ";
		}
		if($sc['scm_category']){
			$optWhereSql	.= " and g.scm_category like '" . $sc['scm_category'] . "%' ";
		}

		if	($this->scm_use_suboption_mode){
			// 현재 추가옵션 추출 쿼리
			if	($sc['sc_display_type'] == 'ea'){
				$subSelectSql	= "SELECT
									'goods'					AS ldgStr,
									g.goods_seq					AS goods_seq,
									concat(IFNULL(g.goods_code, ''), IFNULL(s.suboption_code, ''))	AS goods_code,
									g.goods_name				AS goods_name,
									'suboption'					AS option_type,
									s.suboption_seq				AS option_seq,
									s.suboption					AS option_name,
									'0'							AS warehousing_ea,
									'0'							AS carryingout_ea,
									'0'							AS movein_ea,
									'0'							AS revisionin_ea,
									'0'							AS in_total,
									'0'							AS export_ea,
									'0'							AS return_ea,
									'0'							AS moveout_ea,
									'0'							AS revisionout_ea,
									'0'							AS revisionbad_ea,
									'0'							AS out_total,
									'9999-99-99'				AS min_ldg_date,
									'0000-00-00'				AS max_ldg_date ";
			}else{
				$subSelectSql	= "SELECT
									'goods'					AS ldgStr,
									g.goods_seq					AS goods_seq,
									concat(IFNULL(g.goods_code, ''), IFNULL(s.suboption_code, ''))	AS goods_code,
									g.goods_name				AS goods_name,
									'suboption'					AS option_type,
									s.suboption_seq				AS option_seq,
									s.suboption					AS option_name,
									'0'							AS wh_seq,
									'0'							AS in_ea,
									'0'							AS in_supply_price,
									'0'							AS out_ea,
									'9999-99-99'				AS min_ldg_date,
									'0000-00-00'				AS max_ldg_date ";
			}
			$subFromSql		= "FROM fm_goods					AS g "
							. "INNER JOIN fm_goods_suboption	AS s on g.goods_seq = s.goods_seq ";
			$subWhereSql	= "WHERE g.goods_seq > 0 and g.package_yn = 'n' and g.provider_seq = 1 and "
							. "s.suboption_seq > 0 and (s.package_count = 0 or s.package_count is NULL) "
							. $subAddWhere;
			$subGroupSql	= "";

			// 정렬
			if($sc['orderby']){
				$subOrderSql	= "ORDER BY g." . $sc['orderby'] . ' ' .$sc['sort'];
			}else{
				$subOrderSql	= "ORDER BY g.goods_seq DESC, o.option_seq ";
			}

			if($sc['scm_category']){
				$subWhereSql	.= " and g.scm_category like '" . $sc['scm_category'] . "%' ";
			}
		}

		// 수불부 데이터
		if	($sc['sc_display_type'] == 'ea'){
			$selectSql		= "select cal.*
								from (
									SELECT sld.* ";
			if	($sc['sc_all_goods']){
				$selectSql		.= ", IF((" . $preeaSql1 . ") >= 0,(" . $preeaSql1 . "),(" . $preeaSql2 . ")) AS pre_ea ";
				$selectSql		.= ", IF((" . $cureaSql1 . ") >= 0,(" . $cureaSql1 . "),(" . $cureaSql2 . ")) AS cur_ea ";
			}else{
				$selectSql		.= ", (" . $preeaSql1 . ") AS pre_ea ";
				$selectSql		.= ", (" . $cureaSql1 . ") AS cur_ea ";
			}
		}else{
			$selectSql		= "select cal.*,
									((cal.pre_supply_price * cal.pre_ea)+cal.in_supply_price ) / (cal.pre_ea + cal.in_ea) as out_supply_price_unit,
									((cal.pre_supply_price * cal.pre_ea)+cal.in_supply_price ) / (cal.pre_ea + cal.in_ea) * out_ea as out_price
								from (
									SELECT sld.* ";
			if	($sc['sc_all_goods']){
				$selectSql		.= ", IF((" . $preeaSql1 . ") >= 0,(" . $preeaSql1 . "),(" . $preeaSql2 . ")) AS pre_ea ";
				$selectSql		.= ", IF((" . $prepriceSql1 . ") >= 0,(" . $prepriceSql1 . "),(" . $prepriceSql2 . ")) AS pre_supply_price ";
				$selectSql		.= ", IF((" . $cureaSql1 . ") >= 0,(" . $cureaSql1 . "),(" . $cureaSql2 . ")) AS cur_ea ";
				$selectSql		.= ", IF((" . $curpriceSql1 . ") >= 0,(" . $curpriceSql1 . "),(" . $curpriceSql2 . ")) AS cur_supply_price ";
			}else{
				$selectSql		.= ", (" . $preeaSql1 . ") AS pre_ea ";
				$selectSql		.= ", (" . $prepriceSql1 . ") AS pre_supply_price ";
				$selectSql		.= ", (" . $cureaSql1 . ") AS cur_ea ";
				$selectSql		.= ", (" . $curpriceSql1 . ") AS cur_supply_price ";
			}
		}

		if	($sc['sc_all_goods']){
			$ldgSql			= $ldgSelectSql . $ldgFromSql . $ldgWhereSql . $ldgGroupSql . $ldgOrderSql;
			$optSql			= $optSelectSql . $optFromSql . $optWhereSql . $optGroupSql . $optOrderSql;
			$subSql			= $subSelectSql . $subFromSql . $subWhereSql . $subGroupSql . $subOrderSql;
			if	($this->scm_use_suboption_mode){
				$fromSql		= "FROM (
									(" . $ldgSql . ") union (" . $optSql . ") union (" . $subSql . ")
									) AS sld ) as cal ";
			}else{
				$fromSql		= "FROM (
									(" . $ldgSql . ") union (" . $optSql . ")
									) AS sld ) as cal ";
			}
		}else{
			$fromSql		= "FROM (
								" . $ldgSelectSql . "
								" . $ldgFromSql . "
								" . $ldgWhereSql . "
								" . $ldgGroupSql . "
								" . $ldgOrderSql . "
								) AS sld ) as cal ";
		}
		$whereSql		= "";
		$groupSql		= "";
		$orderSql		= "";
		if($sc['orderby']){
			$gropuSql	= "ORDER BY " . $sc['orderby'] . ' ' .$sc['sort']
						. ", option_name ";

		}else{
			$gropuSql	= "ORDER BY goods_name, option_name ";
		}

		$sql			= $selectSql . $fromSql . $whereSql . $gropuSql . $orderSql;
		if	($sc['sql_type'] == 'total'){
			if	($sc['sc_display_type'] == 'ea'){
				$sql	= "SELECT * ,
								SUM(IFNULL(pre_ea, 0))			as pre_ea,
								SUM(IFNULL(warehousing_ea, 0))	as warehousing_ea,
								SUM(IFNULL(carryingout_ea, 0))	as carryingout_ea,
								SUM(IFNULL(movein_ea, 0))		as movein_ea,
								SUM(IFNULL(revisionin_ea, 0))	as revisionin_ea,
								SUM(IFNULL(in_total, 0))		as in_total,
								SUM(IFNULL(export_ea, 0))		as export_ea,
								SUM(IFNULL(return_ea, 0))		as return_ea,
								SUM(IFNULL(moveout_ea, 0))		as moveout_ea,
								SUM(IFNULL(revisionout_ea, 0))	as revisionout_ea,
								SUM(IFNULL(revisionbad_ea, 0))	as revisionbad_ea,
								SUM(IFNULL(out_total, 0))		as out_total,
								SUM(IFNULL(cur_ea, 0))			as cur_ea
							FROM
							(
								" . $sql . "
							) as t
						";
			}else{
				$sql	= "SELECT * ,
								SUM(IFNULL(pre_ea, 0)) as pre_ea,
								SUM(IFNULL(pre_supply_price, 0) * IFNULL(pre_ea, 0)) as pre_supply_price,
								SUM(IFNULL(in_ea, 0)) as in_ea,
								SUM(IF(IFNULL(in_ea,0) > 0,IFNULL(in_supply_price,0),IFNULL(in_supply_price,0)*-1)) as in_supply_price,
								SUM(IFNULL(out_supply_price_unit, 0)) as out_supply_price_unit,
								SUM(IFNULL(out_price, 0)) as out_price,
								SUM(IFNULL(out_ea, 0)) as out_ea,
								SUM(IFNULL(cur_ea, 0)) as cur_ea,
								SUM(IFNULL(cur_supply_price, 0) * IFNULL(cur_ea, 0)) as cur_supply_price
							FROM
							(
								" . $sql . "
							) as t
						";
			}
			$query	= $this->db->query($sql);
			$result	= $query->row_array();
		}else{
			if	($sc['page'] > 0 && $sc['perpage'] > 0){
				$result	= select_page($sc['perpage'], $sc['page'], 10, $sql, '');
			}else{
				$query	= $this->db->query($sql);
				$result	= $query->result_array();
			}
		}

		return $result;
	}

	// 재고 수불수 상품당 Detail
	public function get_ledger_detail($sc){

		$selectSql	= "SELECT * ";
		$fromSql	= "FROM fm_scm_ledger_detail	AS fsld ";
		$whereSql	= "WHERE fsld.ldg_detail_seq > 0 ";
		$gropuSql	= "";
		$orderSql	= "ORDER BY fsld.ldg_date asc, fsld.ldg_detail_seq asc ";

		// 상품번호 검색
		if	($sc['goods_seq']){
			$whereSql	.= " AND fsld.goods_seq = ? ";
			$bind[]		= $sc['goods_seq'];
		}
		// 옵션구분 검색
		if	($sc['option_type']){
			$whereSql	.= " AND fsld.option_type = ? ";
			$bind[]		= $sc['option_type'];
		}
		// 옵션번호 검색
		if	($sc['option_seq']){
			$whereSql	.= " AND fsld.option_seq = ? ";
			$bind[]		= $sc['option_seq'];
		}
		// 옵션번호 검색
		if	($sc['sc_wh_seq']){
			$whereSql	.= " AND fsld.wh_seq = ? ";
			$bind[]		= $sc['sc_wh_seq'];
		}
		// 마지막 수불부를 추출
		if	($sc['sc_all_goods']){
			if	($sc['sc_edate']){
				$whereSql	.= " AND fsld.ldg_date <= '" . $sc['sc_edate'] . "' ";
			}
			$orderSql	= "ORDER BY fsld.ldg_date desc, fsld.ldg_detail_seq desc LIMIT 1 ";
		}else{
			// 날짜 검색
			if	($sc['sc_sdate']){
				$whereSql	.= " AND fsld.ldg_date >= '" . $sc['sc_sdate'] . "' ";
			}
			if	($sc['sc_edate']){
				$whereSql	.= " AND fsld.ldg_date <= '" . $sc['sc_edate'] . "' ";
			}
		}

		$sql			= $selectSql . $fromSql . $whereSql . $gropuSql . $orderSql;
		$query	= $this->db->query($sql, $bind);
		$result	= $query->result_array();

		return $result;
	}

	// 수량별 상세 데이터
	public function get_eainfo_to_ledger_detail($sc){

		// 수불 일자 검색
		if	($sc['ldg_date']){
			$addWhere	.= " and ldg_date = ? ";
			$addBinds[]	= $sc['ldg_date'];
		}
		if	($sc['goods_seq']){
			$addWhere	.= " and goods_seq = ? ";
			$addBinds[]	= $sc['goods_seq'];
		}
		if	($sc['option_type']){
			$addWhere	.= " and option_type = ? ";
			$addBinds[]	= $sc['option_type'];
		}
		if	($sc['option_seq']){
			$addWhere	.= " and option_seq = ? ";
			$addBinds[]	= $sc['option_seq'];
		}
		if	($sc['wh_seq']){
			$addWhere	.= " and wh_seq = ? ";
			$addBinds[]	= $sc['wh_seq'];
		}

		$sql	= "select ldg_date, goods_seq, option_type, option_seq,
						SUM(IF(change_kind='warehousing',IFNULL(ea, 0),0)) as warehousing_ea,
						SUM(IF(change_kind='carryingout',IFNULL(ea, 0),0)) as carryingout_ea,
						SUM(IF(change_kind='stockmove' and change_type='in',IFNULL(ea, 0),0)) as movein_ea,
						SUM(IF(change_kind='revision' and change_type='in',IFNULL(ea, 0),0)) as revisionin_ea,
						SUM(IF(change_kind='export',IFNULL(ea, 0),0)) as export_ea,
						SUM(IF(change_kind='return',IFNULL(ea, 0),0)) as return_ea,
						SUM(IF(change_kind='stockmove' and change_type='out',IFNULL(ea, 0),0)) as moveout_ea,
						SUM(IF(change_kind='revision' and change_type='out',IFNULL(ea, 0),0)) as revisionout_ea,
						SUM(IF(change_kind='revision_bad',IFNULL(ea, 0),0)) as revisionbad_ea
					from (
						select
							ldg_date, goods_seq, option_type, option_seq, change_kind, change_type, wh_seq,
							sum(IFNULL(ea, 0))	as ea
						from
							fm_scm_ledger_detail
						where ldg_detail_seq > 0 " . $addWhere . "
						group by
							ldg_date, goods_seq, option_type, option_seq, change_kind, change_type, wh_seq
						) as tmp
					group by
						ldg_date, goods_seq, option_type, option_seq, wh_seq
					limit 1";
		$query		= $this->db->query($sql, $addBinds);
		$result		= $query->result_array();

		return $result;
	}

	// 공통된 controller에 대한 묶음 처리
	public function ledger_controllers($page = 'ledger', $pageParam = array()){
		$keyword_type	= array(
			'goods_name'	=> '상품명',
			'goods_code'	=> '상품코드',
			'goods_seq'		=> '상품번호'
		);
		$defaultSearchPath		= 'admin/scm_manage/ledger';
		if	($page == 'inven'){
			$defaultSearchPath	= 'admin/scm_manage/inven';
		}
		
		$get = $this->input->get();

		if	(!$get['sc_date_type'])	$get['sc_date_type']	= 'month';
		if	(!$get['sc_month_type'])$get['sc_month_type']	= 'month';
		if	(!$get['sc_year'])		$get['sc_year']		= date('Y');
		if	(!$get['sc_month'])		$get['sc_month']		= str_pad(date('m') - 1, 2, "0", STR_PAD_LEFT);
		if	(!$get['sc_quater'])	$get['sc_quater']		= 'year';
		if	(!$get['sc_sdate'])		$get['sc_sdate']		= date('Y-m-d');
		if	(!$get['sc_edate'])		$get['sc_edate']		= date('Y-m-d');

		// 분류 정보 추출
		$sc['depth']		= 1;
		$categoryinfo[1]	= $this->scmmodel->getScmCategory($sc);
		if	($get['sc_scm_category']) foreach($get['sc_scm_category'] as $k => $code){
			if	($code){
				$category_code	= $code;
				$depth			= $k+ 2;
				if	($depth <= 4){
					unset($sc);
					$sc['depth']			= $depth;
					$sc['sc_category_code']	= $code;
					$categoryinfo[$depth]	= $this->scmmodel->getScmCategory($sc);
				}
			}
		}

		// 미마감 상태
		$unfinished				= false;
		if ($get['sc_date_type'] != 'day'){
			$unfinished			= $this->scmmodel->chkLedgerUnfinishedStatus($get['sc_month_type'], $get['sc_quater'], $get['sc_year'], (int)$get['sc_month']);			
		}

		unset($sc);
		$sc					= $get;
		if	($pageParam['page'] > 0){
			$sc['page']		= $pageParam['page'];
			$sc['perpage']	= $pageParam['perpage'];
		}
		if	($get['orderby']){
			if	(preg_match('/^asc\_/', $get['orderby'])){
				$orderby			= preg_replace('/^asc\_/', '', $get['orderby']);
				$sort				= 'asc';
			}else{
				$orderby			= preg_replace('/^desc\_/', '', $get['orderby']);
				$sort				= 'desc';
			}
		}else{
			$orderby = 'goods_seq';
			$sort	 = 'desc';
			$get['orderby'] = 'desc_goods_seq';
		}
		$sc['sc_all_goods']	= $get['sc_all_goods'];
		$sc['scm_category']	= $category_code;
		$sc['orderby']		= $orderby;
		$sc['sort']			= $sort;
		$sc['sc_month']		= str_pad($get['sc_month'], 2, "0", STR_PAD_LEFT);

		$this->template->assign(array('sc'=>$get,'scObj'=>json_encode($get)));

		$sType							= array_search(trim($get['keyword_sType']), array_flip($keyword_type));
		if($sType){
			$sc['src_key']	= $get['keyword_sType'];
			$sc['keyword']	= trim($get['keyword']);
		}else{
			$sc['keyword']	= trim($get['keyword']);
		}
		// 분기 검색에 따른 월검색 처리
		if	($get['sc_date_type'] == 'month' && $get['sc_month_type'] == 'quater'){
			if		($get['sc_quater'] == 'year'){
				$sMonth	= 1;	$eMonth	= 12;
			}elseif	($get['sc_quater'] == 'first_half'){
				$sMonth	= 1;	$eMonth	= 6;
			}elseif	($get['sc_quater'] == 'second_half'){
				$sMonth	= 7;	$eMonth	= 12;
			}else{
				$sMonth	= 1 + (3 * ($get['sc_quater'] - 1));
				$eMonth	= 3 + (3 * ($get['sc_quater'] - 1));
			}
			for($m = $sMonth; $m <= $eMonth; $m++){
				$sc['sc_month_arr'][]	= $m;
			}
			unset($sc['sc_month']);
		}

		// 창고리스트 추출
		$warehouses	= $this->scmmodel->get_warehouse(array('inclusion_move' => 'Y'));
		if	($get['sc_wh_seq'] > 0 && $warehouses){
			foreach($warehouses as $k => $whData){
				if	($get['sc_wh_seq'] == $whData['wh_seq']){
					$get['sc_wh_name']	= $whData['wh_name'];
				}
			}
		}

		// 재고수불부
		if	($get['sc_date_type'] == 'day'){
			$ledger					= $this->scmmodel->get_ledger($sc);
		}else{
			// 미마감 시 현재 데이터 추출 (수량만 노출)
			if	($unfinished){
				$sc['sc_sdate']		= '2016-01-01';
				$sc['sc_edate']		= date('Y-m-d');
				$ledger				= $this->scmmodel->get_ledger($sc);
			}else{
				$ledger				= $this->scmmodel->get_ledger_month($sc);
			}
		}
		
		// 목록 조회에 사용된 쿼리를 ajax로 재호출하기 위해 처리
		$this->template->assign(array('param_count'=>urlencode(base64_encode($this->db->last_query()))));

		$ledger_loop	= $ledger;
		if	($sc['page'] > 0){
			$ledger_loop	= $ledger['record'];
			$totalcount		= $ledger['page']['total'];
		}else{
			$totalcount		= count($ledger);
		}
		if	($ledger_loop){
			foreach($ledger_loop as $k => $ldgData){
				unset($params);
				$params							= $ldgData;

				$ldgData['goods_name_title']	= strip_tags($ldgData['goods_name']);
				$ldgData['goods_name']			= htmlspecialchars($ldgData['goods_name']);

				if	($sc['sc_display_type'] == 'ea'){
					$total['pre_ea']				+= $ldgData['pre_ea'];
					$total['warehousing_ea']		+= $ldgData['warehousing_ea'];
					$total['carryingout_ea']		+= $ldgData['carryingout_ea'];
					$total['movein_ea']				+= $ldgData['movein_ea'];
					$total['revisionin_ea']			+= $ldgData['revisionin_ea'];
					$total['in_total']				+= $ldgData['in_total'];
					$total['export_ea']				+= $ldgData['export_ea'];
					$total['return_ea']				+= $ldgData['return_ea'];
					$total['moveout_ea']			+= $ldgData['moveout_ea'];
					$total['revisionout_ea']		+= $ldgData['revisionout_ea'];
					$total['revisionbad_ea']		+= $ldgData['revisionbad_ea'];
					$total['out_total']				+= $ldgData['out_total'];
					$total['cur_ea']				+= $ldgData['cur_ea'];
				}else{
					$params['pre_price']			= $ldgData['pre_supply_price'] * $ldgData['pre_ea'];
					$params['in_price']				= $ldgData['in_supply_price'];
					$cal_data						= $this->scmmodel->get_calculate_ledger($params, false);
					$ldgData['pre_ea']				= $cal_data['pre_ea'];
					$ldgData['pre_supply_price']	= $cal_data['pre_supply_price'];
					$ldgData['pre_price']			= $cal_data['pre_price'];
					$ldgData['in_ea']				= $cal_data['in_ea'];
					$ldgData['in_supply_price']		= $cal_data['in_supply_price'];
					$ldgData['in_price']			= $cal_data['in_price'];
					$ldgData['out_ea']				= $cal_data['out_ea'];
					$ldgData['out_supply_price']	= $cal_data['out_supply_price'];
					$ldgData['out_price']			= $cal_data['out_price'];
					$ldgData['cur_ea']				= $cal_data['cur_ea'];
					$ldgData['cur_supply_price']	= $cal_data['cur_supply_price'];
					$ldgData['cur_price']			= $cal_data['cur_price'];
					$ledger_loop[$k]				= $ldgData;

					$total['pre_ea']				+= $ldgData['pre_ea'];
					$total['pre_price']				+= $ldgData['pre_price'];
					$total['in_ea']					+= $ldgData['in_ea'];
					$total['in_price']				+= $ldgData['in_price'];
					$total['out_ea']				+= $ldgData['out_ea'];
					$total['out_price']				+= $ldgData['out_price'];
					$total['cur_ea']				+= $ldgData['cur_ea'];
					$total['cur_price']				+= $ldgData['cur_price'];
				}

				if	($page == 'inven') {
					unset($location_sc);
					$location_sc['wh_seq']			= $ldgData['wh_seq'];
					$location_sc['goods_seq']		= $ldgData['goods_seq'];
					$location_sc['option_type']		= $ldgData['option_type'];
					$location_sc['option_seq']		= $ldgData['option_seq'];
					$location_arr					= $this->get_location_goods($location_sc);
					$ledger_loop[$k]['location_code'] = $location_arr[0]['location_code'];
				}
			}

			if	($sc['sc_display_type'] != 'ea'){
				if	($total['pre_ea'] != 0){
					unset($tmpArr);
					$tmpArr[]					= array('ea' => $total['pre_ea'], 'tot_supply_price' => $total['pre_price']);
					$total['pre_supply_price']	= $this->calculate_supply_price_average($tmpArr);
				}
				if	($total['in_ea'] != 0){
					unset($tmpArr);
					$tmpArr[]					= array('ea' => $total['in_ea'], 'tot_supply_price' => $total['in_price']);
					$total['in_supply_price']	= $this->calculate_supply_price_average($tmpArr);
				}
				if	($total['out_ea'] != 0){
					unset($tmpArr);
					$tmpArr[]					= array('ea' => $total['out_ea'], 'tot_supply_price' => $total['out_price']);
					$total['out_supply_price']	= $this->calculate_supply_price_average($tmpArr);
				}
				if	($total['cur_ea'] != 0){
					unset($tmpArr);
					$tmpArr[]					= array('ea' => $total['cur_ea'], 'tot_supply_price' => $total['cur_price']);
					$total['cur_supply_price']	= $this->calculate_supply_price_average($tmpArr);
				}
			}
		}

		// 검색 년도 목록 ( 2016년 오픈 )
		$eYear	= date('Y');
		for($y = 2016; $y <= $eYear; $y++){
			$yearArr[]	= $y;
		}

		for($i=12;$i>0;$i--){
			$temp = strlen($i)>1 ? $i : "0".$i;
			$monthArr[] = $temp;
		}

		return array(
			'keyword_type'	=> $keyword_type,
			'warehouses'	=> $warehouses,
			'loop'			=> $ledger_loop,
			'totalcount'	=> $totalcount,
			'pages'			=> $ledger['page'],
			'total'			=> $total,
			'perpage'		=> $perpage,
			'categoryinfo'	=> $categoryinfo,
			'yearArr'		=> $yearArr,
			'monthArr'		=> $monthArr,
			'unfinished'	=> $unfinished,
		);
	}

	########## ↑↑ 수불부 ↑↑ ##### ↓↓ 발주입고 ↓↓ ##########

	// 발주 입고 현황
	public function get_sorder($sc){

		// 공통 Select절
		$commonTotalSelect	= "SUM(IFNULL([:ALIACE2:]ea, 0))					as ea, "
							. "SUM(IFNULL([:ALIACE2:]supply_price, 0))			as supply_price, "
							. "SUM(IFNULL([:ALIACE2:]freight_price, 0))			as freight_price, "
							. "SUM(IFNULL([:ALIACE2:]insurance_price, 0))		as insurance_price, "
							. "SUM(IFNULL([:ALIACE2:]cif_price, 0))				as cif_price, "
							. "SUM(IFNULL([:ALIACE2:]duty_price, 0))			as duty_price, "
							. "SUM(IFNULL([:ALIACE2:]accessorial_price, 0))		as accessorial_price, "
							. "SUM(IFNULL([:ALIACE2:]krw_supply_price, 0))		as krw_supply_price, "
							. "SUM(IFNULL([:ALIACE2:]supply_tax, 0))			as supply_tax ";

		// 날짜검색 ( 기본 검색 조건 )
		if	(!$sc['sc_year'])	$sc['sc_year']	= date('Y');
		if	(!$sc['sc_month'])	$sc['sc_month']	= date('m');
		$sc['sc_month']							= str_pad($sc['sc_month'], 2, '0', STR_PAD_LEFT);
		$sc_date								= $sc['sc_year'] . '-' . $sc['sc_month'];
		$addWhere		= " AND [:ALIACE1:]complete_date like '" . $sc_date . "%' ";

		// 상품번호 검색
		if	($sc['goods_seq']){
			$addWhere	.= " AND [:ALIACE2:]goods_seq = '" . $sc['goods_seq'] . "' ";
		}
		// 옵션타입 검색
		if	($sc['option_type']){
			$addWhere	.= " AND [:ALIACE2:]option_type = '" . $sc['option_type'] . "' ";
		}
		// 옵션번호 검색
		if	($sc['option_seq']){
			$addWhere	.= " AND [:ALIACE2:]option_seq = '" . $sc['option_seq'] . "' ";
		}
		if	($sc['sc_currency']){
			$addWhere	.= " AND [:ALIACE1:]currency = '" . $sc['sc_currency'] . "' ";
		}
		// 상품검색
		if	($sc['keyword']){
			if	($sc['src_key']){
				$addWhere	.= " AND [:ALIACE2:]" . $sc['src_key'];
				if	($sc['src_key'] == 'goods_seq')	$addWhere	.= " = '" . $sc['keyword'] . "'";
				else								$addWhere	.= " like '%" . $sc['keyword'] . "%'";
			}else{
				$addWhere	.= " AND ( [:ALIACE2:]goods_seq like '" . $sc['keyword'] . "%' or "
							. "[:ALIACE2:]goods_code like '%" . $sc['keyword'] . "%' or "
							. "[:ALIACE2:]goods_name like '%" . $sc['keyword'] . "%' or "
							. "[:ALIACE2:]option_name like '%" . $sc['keyword'] . "%' ) ";
			}
		}

		// 정렬
		$orderby				= " ORDER BY [:ALIACE2:]goods_seq DESC ";
		if	($sc['orderby']){
			if		(preg_match('/\s(asc|desc)$/i', $sc['orderby'])){
				$orderby		= " ORDER BY [:ALIACE2:]" . $sc['orderby'] . " ";
			}else{
				$orderTmp		= explode("_", $sc['orderby']);
				$tmpKey			= 0;
				if	(preg_match('/(\_asc|\_desc)$/i', $sc['orderby']))	$tmpKey	= count($orderTmp) - 1;
				$orderSort		= $orderTmp[$tmpKey];
				unset($orderTmp[$tmpKey]);
				$orderFld		= implode('_', $orderTmp);

				$orderby		= " ORDER BY [:ALIACE2:]" . $orderFld . " " . $orderSort;
			}
		}

		if($sc['sql_type'] == 'total'){
			// 발주 내역 검색
			$sql1				= "SELECT sog.goods_seq, sog.option_type, sog.option_seq, sog.use_tax, "
								. "SUM(IFNULL(sog.supply_price, 0) * IFNULL(sog.ea, 0))	as goods_price, "
								. "so.complete_date, so.currency, "
								. str_replace('[:ALIACE2:]', 'sog.', $commonTotalSelect)
								. "FROM fm_scm_order as so, fm_scm_order_goods as sog "
								. "WHERE so.sorder_seq = sog.sorder_seq "
								. "AND so.sorder_status = '1' "
								. str_replace('[:ALIACE2:]', 'sog.', str_replace('[:ALIACE1:]', 'so.', $addWhere))
								. " GROUP BY sog.goods_seq, sog.option_type, sog.option_seq ";
			$query1				= $this->db->query($sql1);
			$result1			= $query1->result_array();
			$src_goods			= array();
			foreach($result1 as $val){
				$src_goods[]	= $val['goods_seq'].$val['option_type'].$val['option_seq'];

				$result['order']['ea']					+= $val['ea'];
				$result['order']['goods_price']			+= $val['goods_price'];
				$result['order']['supply_price']		+= $val['supply_price'];
				$result['order']['freight_price']		+= $val['freight_price'];
				$result['order']['insurance_price']		+= $val['insurance_price'];
				$result['order']['cif_price']			+= $val['cif_price'];
				$result['order']['duty_price']			+= $val['duty_price'];
				$result['order']['accessorial_price']	+= $val['accessorial_price'];
				$result['order']['krw_supply_price']	+= $val['krw_supply_price'];
				$result['order']['supply_tax']			+= $val['supply_tax'];
			}

			if(count($src_goods) > 0){
				$addWhere		.= "AND concat([:ALIACE2:]goods_seq, "
								. "[:ALIACE2:]option_type, "
								. "[:ALIACE2:]option_seq) IN ('" . implode("', '", $src_goods) . "')";

				$sql2			= "SELECT "
								. "complete_date, currency, "
								. "SUM(IFNULL(goods_price, 0))		as goods_price, "
								. str_replace('[:ALIACE2:]', '', $commonTotalSelect)
								. "FROM ( ( SELECT "
								. "sr.complete_date, sr.currency, "
								. "SUM(IFNULL(srg.supply_price, 0) * IFNULL(srg.ea, 0))	as goods_price, "
								. str_replace('[:ALIACE2:]', 'srg.', $commonTotalSelect)
								. "FROM fm_scm_stock_revision		as sr, "
								. "fm_scm_stock_revision_goods		as srg "
								. "WHERE sr.revision_seq = srg.revision_seq "
								. "AND sr.revision_status = '1' "
								. "AND sr.revision_type in ('def', 'in') "
								. str_replace('[:ALIACE2:]', 'srg.', str_replace('[:ALIACE1:]', 'sr.', $addWhere))
								. "GROUP BY sr.revision_status "
								. ") UNION ( SELECT "
								. "sw.complete_date, sw.currency, "
								. "SUM(IFNULL(swg.supply_price, 0) * IFNULL(swg.ea, 0))	as goods_price, "
								. str_replace('[:ALIACE2:]', 'swg.', $commonTotalSelect)
								. "FROM fm_scm_warehousing			as sw, "
								. "fm_scm_warehousing_goods			as swg "
								. "WHERE sw.whs_seq = swg.whs_seq "
								. "AND sw.whs_status = '1' "
								. str_replace('[:ALIACE2:]', 'swg.', str_replace('[:ALIACE1:]', 'sw.', $addWhere))
								. "GROUP BY sw.whs_status "
								. ") ) as whs";
				$query2			= $this->db->query($sql2);
				$result['whs']	= end($query2->result_array());
			}
		}else if($sc['sql_type'] == 'order'){
			$sql				= "SELECT sog.goods_seq, sog.option_type, sog.option_seq, "
								. "sog.goods_name, sog.goods_code, sog.option_name, sog.use_tax, "
								. "so.complete_date, so.currency, "
								. "SUM(IFNULL(sog.supply_price, 0) * IFNULL(sog.ea, 0))	as goods_price, "
								. str_replace('[:ALIACE2:]', 'sog.', $commonTotalSelect)
								. "FROM fm_scm_order		as so, "
								. "fm_scm_order_goods		as sog "
								. "WHERE so.sorder_seq = sog.sorder_seq "
								. "AND so.sorder_status = '1' "
								. str_replace('[:ALIACE2:]', 'sog.', str_replace('[:ALIACE1:]', 'so.', $addWhere))
								. " GROUP BY sog.goods_seq, sog.option_type, sog.option_seq "
								. str_replace('[:ALIACE2:]', 'sog.', $orderby);
			if	($sc['page'] > 0 && $sc['perpage'] > 0){
				$result				= select_page($sc['perpage'], $sc['page'], 10, $sql, '');
			}else{
				$query							= $this->db->query($sql);
				$result['record']				= $query->result_array();
				$result['page']['totalcount']	= count($result['record']);
			}
		}else if($sc['sql_type'] == 'whs'){
			$sql				= "SELECT goods_seq, option_type, option_seq, "
								. "complete_date, currency, "
								. "SUM(IFNULL(goods_price, 0))	as goods_price, "
								. str_replace('[:ALIACE2:]', '', $commonTotalSelect)
								. "FROM ( ( SELECT srg.goods_seq,srg.goods_code, srg.goods_name,srg.option_name,srg.option_type, srg.option_seq, "
								. "sr.complete_date, sr.currency, "
								. "SUM(IFNULL(srg.supply_price, 0) * IFNULL(srg.ea, 0))	as goods_price, "
								. str_replace('[:ALIACE2:]', 'srg.', $commonTotalSelect)
								. "FROM fm_scm_stock_revision		as sr, "
								. "fm_scm_stock_revision_goods		as srg "
								. "WHERE sr.revision_seq = srg.revision_seq "
								. "AND sr.revision_type in ('def', 'in') "
								. "AND sr.revision_status = '1' "
								. str_replace('[:ALIACE2:]', 'srg.', str_replace('[:ALIACE1:]', 'sr.', $addWhere))
								. "GROUP BY srg.goods_seq, srg.option_type,srg.option_seq "
								. ") UNION ( SELECT swg.goods_seq,swg.goods_code,swg.goods_name,swg.option_name,swg.option_type, swg.option_seq, "
								. "sw.complete_date, sw.currency, "
								. "SUM(IFNULL(swg.supply_price, 0) * IFNULL(swg.ea, 0))	as goods_price, "
								. str_replace('[:ALIACE2:]', 'swg.', $commonTotalSelect)
								. "FROM fm_scm_warehousing			as sw, "
								. "fm_scm_warehousing_goods			as swg "
								. "WHERE sw.whs_seq = swg.whs_seq "
								. "AND sw.whs_status = '1' "
								. str_replace('[:ALIACE2:]', 'swg.', str_replace('[:ALIACE1:]', 'sw.', $addWhere))
								. "GROUP BY swg.goods_seq, swg.option_type, swg.option_seq "
								. ") ) as whs "
								. "WHERE goods_seq > 0 AND option_seq > 0 "
								. str_replace('[:ALIACE2:]', '', str_replace('[:ALIACE1:]', '', $addWhere))
								. "GROUP BY goods_seq, option_type, option_seq ";
			$query				= $this->db->query($sql);
			$result				= $query->result_array();
		}

		return $result;
	}

	########## ↑↑ 발주입고 ↑↑ ##### ↓↓ 발주대비입고 ↓↓ ##########

	// 발주 대비 입고 현황 - 발주 상품
	public function get_sorder_statgoods($sc){

		$selectSql	= "SELECT so.sorder_seq, so.sorder_code, so.complete_date, sog.use_tax, "
					. "sog.goods_seq, sog.goods_name, sog.goods_code, "
					. "sog.option_type, sog.option_seq, sog.option_name, "
					. "fst.trader_seq, fst.trader_name, fst.currency_unit			as currency, "
					. "SUM(IFNULL(sog.ea, 0))										as ea, "
					. "SUM(IFNULL(sog.supply_price, 0))								as supply_price, "
					. "SUM(IFNULL(sog.supply_price, 0) * IFNULL(sog.ea, 0))			as goods_price, "
					. "SUM(IFNULL(sog.freight_price, 0))							as freight_price, "
					. "SUM(IFNULL(sog.insurance_price, 0))							as insurance_price, "
					. "SUM(IFNULL(sog.cif_price, 0))								as cif_price, "
					. "SUM(IFNULL(sog.duty_price, 0))								as duty_price, "
					. "SUM(IFNULL(sog.accessorial_price, 0))						as accessorial_price, "
					. "SUM(IFNULL(sog.krw_supply_price, 0))							as krw_supply_price, "
					. "SUM(IFNULL(sog.supply_tax, 0))								as supply_tax ";
		$fromSql	= "FROM fm_scm_order as so, fm_scm_order_goods as sog, fm_scm_trader as fst ";
		$whereSql	= "WHERE so.sorder_seq = sog.sorder_seq "
					. "AND so.trader_seq = fst.trader_seq "
					. "AND so.sorder_status = '1' ";
		$groupBy	= "GROUP BY so.sorder_seq, sog.goods_seq, sog.option_type, sog.option_seq ";
		$orderBy	= "ORDER BY so.sorder_seq DESC ";
		$limitSql	= "";

		// 날짜검색
		if		($sc['sc_year'] && $sc['sc_month']){
			$whereSql	.= " AND so.complete_date like "
						. "'" . $sc['sc_year'].'-'.str_pad($sc['sc_month'],2,'0',STR_PAD_LEFT) . "%' ";
		}else{
			$whereSql	.= " AND so.complete_date like '" . date('Y').'-'.date('m') . "%'";
		}
		// 발주서검색
		if		($sc['sorder_seq']){
			$whereSql	.= " AND so.sorder_seq = '" . $sc['sorder_seq'] . "' ";
		}
		// 상품검색
		if		($sc['keyword']){
			if	($sc['src_key']){
				$whereSql	.= " AND " . $sc['src_key'] . " = '" . $sc['keyword'] . "' ";
			}else{
				$whereSql	.= " AND ( so.sorder_code like '%" . $sc['keyword'] . "%' "
						. " OR sog.goods_seq like '" . $sc['keyword'] . "%'"
						. " OR sog.goods_code like '%" . $sc['keyword'] . "%'"
						. " OR sog.goods_name like '%" . $sc['keyword'] . "%'"
						. " OR sog.option_name like '%" . $sc['keyword'] . "%' ) ";
			}
		}
		// 거래처 그룹 검색
		if		($sc['sc_trader_group']){
			$whereSql	.= " AND fst.trader_group = '" . $sc['sc_trader_group'] . "' ";
		}
		// 거래처 검색
		if		($sc['sc_trader']){
			$whereSql	.= " AND fst.trader_seq = '" . $sc['sc_trader'] . "' ";
		}
		// 거래처 화폐 검색
		if		($sc['sc_currency']){
			$whereSql	.= " AND fst.currency_unit = '" . $sc['sc_currency'] . "' ";
		}
		// 정렬
		if	($sc['orderby']){
			if		(preg_match('/\s(asc|desc)$/i', $sc['orderby'])){
				$orderSql		= " ORDER BY " . $sc['orderby'] . " ";
			}else{
				$orderTmp		= explode("_", $sc['orderby']);
				$tmpKey			= 0;
				if	(preg_match('/(\_asc|\_desc)$/i', $sc['orderby']))	$tmpKey	= count($orderTmp) - 1;
				$orderSort		= $orderTmp[$tmpKey];
				unset($orderTmp[$tmpKey]);
				$orderFld		= implode('_', $orderTmp);

				$orderSql		= " ORDER BY " . $orderFld . " " . $orderSort;
			}
		}

		$sql		= $selectSql . $fromSql . $whereSql . $groupBy . $orderSql . $limitSql;
		if($sc['sql_type'] == 'total'){
			$query	= $this->db->query($sql);
			$result	= $query->result_array();
		}else{
			$result	= select_page($sc['perpage'], $sc['page'], 10, $sql, '');
		}

		return $result;
	}

	// 발주 대비 입고 현황 - 입고수량 및 금액
	public function get_sorder_goods_whs($sc){

		$selectSql		= "SELECT swg.goods_seq, swg.option_type, swg.option_seq, "
						. "fst.trader_seq, fst.trader_name, fst.currency_unit		as currency, "
						. "SUM(IFNULL(swg.ea, 0))									as ea, "
						. "SUM(IFNULL(swg.supply_price, 0))							as supply_price, "
						. "SUM(IFNULL(swg.supply_price, 0) * IFNULL(swg.ea, 0))		as goods_price, "
						. "SUM(IFNULL(swg.freight_price, 0))						as freight_price, "
						. "SUM(IFNULL(swg.insurance_price, 0))						as insurance_price, "
						. "SUM(IFNULL(swg.cif_price, 0))							as cif_price, "
						. "SUM(IFNULL(swg.duty_price, 0))							as duty_price, "
						. "SUM(IFNULL(swg.accessorial_price, 0))					as accessorial_price, "
						. "SUM(IFNULL(swg.krw_supply_price, 0))						as krw_supply_price, "
						. "SUM(IFNULL(swg.supply_tax, 0))							as supply_tax ";
		$fromSql		= "FROM fm_scm_warehousing				as sw "
						. "INNER JOIN fm_scm_warehousing_goods	as swg "
						. "ON sw.whs_seq = swg.whs_seq "
						. "INNER JOIN fm_scm_trader				as fst "
						. "ON sw.trader_seq = fst.trader_seq ";
		$whereSql		= "WHERE sw.whs_status = '1' ";
		$groupBy		= "GROUP BY swg.goods_seq, swg.option_type, swg.option_seq ";
		$orderBy		= "";
		$limitSql		= "";

		// 발주번호 검색
		if	($sc['sorder_seq']){
			$whereSql	.= " AND sw.sorder_seq	= '".$sc['sorder_seq']."' ";
		}
		// 상품번호 검색
		if	($sc['goods_seq']){
			$whereSql	.= " AND swg.goods_seq	= '".$sc['goods_seq']."' ";
		}
		// 옵션구분 검색
		if	($sc['option_type']){
			$whereSql	.= " AND swg.option_type	= '".$sc['option_type']."' ";
		}
		// 옵션번호 검색
		if	($sc['option_seq']){
			$whereSql	.= " AND swg.option_seq	= '".$sc['option_seq']."' ";
		}
		// 화폐 검색
		if	($sc['sc_currency']){
			$whereSql	.= " AND fst.currency_unit	= '".$sc['sc_currency']."' ";
		}
		// 날짜검색
		if	($sc['sc_year'] && $sc['sc_month']){
			$src_date	= $sc['sc_year'] . '-' . str_pad($sc['sc_month'],2,'0',STR_PAD_LEFT);
			if	($sc['whs_type'] == 'Y'){
				$whereSql	.= " AND sw.complete_date like '" . $src_date . "%' ";
			}else{
				$whereSql	.= " AND sw.complete_date between "
							. "'" . $src_date . "-01' AND '" . date('Y-m-t') . "' ";
			}
		}

		$sql		= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . $limitSql;
		$query		= $this->db->query($sql);
		$result		= $query->result_array();

		return $result;
	}

	// 발주대비 입고현황 소계 - 발주서별
	public function sorder_forwhs_total($param,$sorder_seq){

		// 발주 내역 추출
		$sc									= $param;
		$sc['sql_type']						= 'total';
		$sc['sorder_seq']					= $sorder_seq;
		$res = $this->get_sorder_statgoods($sc);
		foreach($res as $data){
			// 거래처명
			unset($param);
			$param['trader_seq']			= $data['trader_seq'];
			$trader							= $this->get_trader($param);
			$data['trader_name']			= $trader[0]['trader_name'];

			// 입고수량 추출
			unset($sc);
			$sc['whs_type']					= ($_GET['whs_type'])	? $_GET['whs_type']	: 'Y';
			$sc['sc_month']					= (!$_GET['sc_month'])	? $_GET['sc_month']	: date('n');
			$sc['sorder_seq']				= $data['sorder_seq'];
			$sc['goods_seq']				= $data['goods_seq'];
			$sc['option_type']				= $data['option_type'];
			$sc['option_seq']				= $data['option_seq'];
			$whsRes							= $this->get_sorder_goods_whs($sc);

			// 단가 계산
			$data['krw_supply_tax']			= ($data['use_tax'] == '비과세') ? '0' : $data['krw_supply_tax'];
			$data['price']					= (float)($data['krw_supply_price'] + $data['krw_supply_tax']);
			$data['whs_ea']					= (float)$whsRes[0]['ea'];
			$data['whs_krw_supply_price']	= (float)$whsRes[0]['krw_supply_price'];
			$data['whs_krw_supply_tax']		= (float)$whsRes[0]['krw_supply_tax'];
			$data['whs_price']				= (float)($data['whs_krw_supply_price'] + $data['whs_krw_supply_tax']);

			// 미입고 계산
			$data['no_whs_ea']				= $data['ea'] - $data['whs_ea'];
			$data['no_supply_price']		= (float)($data['no_whs_ea'] * $data['krw_supply_price_ea']);
			$data['no_supply_tax']			= (float)($data['no_whs_ea'] * ($data['krw_supply_price_ea'] * 0.1));
			$data['no_whs_price']			= (float)($data['no_supply_price'] + $data['no_supply_tax']);

			$total_loop[$data['sorder_seq']][]	= $data;
		}

		// 소계 금액 계산
		foreach($total_loop as $key => $list){
			$res_total['cnt']				= count($list);
			foreach($list as $k => $data){
				$res_total['ea']			+= $data['ea'];
				$res_total['price']			+= $data['price'];
				$res_total['whs_ea']		+= $data['whs_ea'];
				$res_total['whs_price']		+= $data['whs_price'];
				$res_total['no_whs_ea']		+= $data['no_whs_ea'];
				$res_total['no_whs_price']	+= $data['no_whs_price'];
			}
		}

		return $res_total;
	}

	// 발주대비 입고 소계 [발주고유키 기준]
	public function get_total_soder($sorder_seq,$sc){

		// 날짜검색
		$regist = " '" . date('Y').'-'.date('m') . "%'";
		if($sc['whs_type'] == 'Y'){
			if($sc['sc_year'] && $sc['sc_month'])
				 $regist = " '" . $sc['sc_year'].'-'.str_pad($sc['sc_month'],2,'0',STR_PAD_LEFT) . "%'";
			$date_where	 = "sw.complete_date like " . $regist;
		}else{
			$date_where	 = "sw.complete_date between " . $sc['sc_year'].'-'.str_pad($sc['sc_month'],2,'0',STR_PAD_LEFT).'-01 AND ' .date('Y').'-'.date('m').'-'.date('t');
		}

		$total_sql1 = "
			SELECT
				sog.goods_seq, sog.option_type, sog.option_seq,
				SUM(IFNULL(sog.ea, 0))									as ea,
				SUM(IFNULL(sog.supply_price, 0))						as supply_price,
				SUM(IFNULL(sog.supply_price, 0) * IFNULL(sog.ea, 0))	as goods_price,
				SUM(IFNULL(sog.freight_price, 0))						as freight_price,
				SUM(IFNULL(sog.insurance_price, 0))						as insurance_price,
				SUM(IFNULL(sog.cif_price, 0))							as cif_price,
				SUM(IFNULL(sog.duty_price, 0))							as duty_price,
				SUM(IFNULL(sog.accessorial_price, 0))					as accessorial_price,
				SUM(IFNULL(sog.krw_supply_price, 0))					as krw_supply_price,
				SUM(IFNULL(sog.supply_tax, 0))							as supply_tax
			FROM
				fm_scm_order AS so, fm_scm_order_goods AS sog
			WHERE
				so.sorder_seq = sog.sorder_seq AND
				so.sorder_status = '1' AND
				so.complete_date like " . $regist . " AND
				so.sorder_seq = '" . $sorder_seq . "'
			GROUP BY sog.goods_seq, sog.option_type, sog.option_seq ";
		$query1		= $this->db->query($total_sql1);
		$result1	= $query1->result_array();

		$src_goods	= array();
		foreach($result1 as $val){
			$src_goods[] = $val['goods_seq'].$val['option_type'].$val['option_seq'];
			$total_data1['ea']					+= $val['ea'];
			$total_data1['krw_supply_price']	+= $val['krw_supply_price'];
			$total_data1['krw_supply_tax']		+= $val['krw_supply_tax'];
			$total_data1['krw_price']			+= $val['krw_price'];
		}

		if(count($src_goods) > 0){
			$tot_where = " AND concat(`goods_seq`,`option_type`,`option_seq`) IN ('".implode("','",$src_goods)."')";

			$total_sql2 = "
					SELECT
						SUM(IFNULL(swg.ea, 0))									as ea,
						SUM(IFNULL(swg.supply_price, 0))						as supply_price,
						SUM(IFNULL(swg.supply_price, 0) * IFNULL(swg.ea, 0))	as goods_price,
						SUM(IFNULL(swg.freight_price, 0))						as freight_price,
						SUM(IFNULL(swg.insurance_price, 0))						as insurance_price,
						SUM(IFNULL(swg.cif_price, 0))							as cif_price,
						SUM(IFNULL(swg.duty_price, 0))							as duty_price,
						SUM(IFNULL(swg.accessorial_price, 0))					as accessorial_price,
						SUM(IFNULL(swg.krw_supply_price, 0))					as krw_supply_price,
						SUM(IFNULL(swg.supply_tax, 0))							as supply_tax
					FROM
						fm_scm_warehousing			AS sw,
						fm_scm_warehousing_goods	AS swg
					WHERE
						sw.whs_seq = swg.whs_seq AND
						sw.whs_status = '1' AND
						sw.sorder_seq = '" . $sorder_seq . "' AND
						" . $date_where . "
						" . $tot_where;
			$query2			= $this->db->query($total_sql2);
			$total_data2	= $query2->result_array();
		}

		$result['order']	= $total_data1;
		$result['whs']		= $total_data2[0];

		return $result;
	}

	########## ↑↑ 발주대비입고 ↑↑ ##### ↓↓ 재고자산명세서 ↓↓ ##########

	// 재고자산명세서 리스트
	public function get_inven($sc,$warehouses){

		$curea_fld		= 'cur_ea';
		$cursupply_fld	= 'cur_supply_price';

		// 기준일
		if($sc['sc_date']){
			$regist = " ldg_date <= '".$sc['sc_date']."'";
		}else{
			$regist = " ldg_date <= '".date('Y-m-d')."'";
		}

		// 상품명, 상품코드, 상품번호
		if($sc['src_key'] && $sc['keyword']){
			$where[] = "sl.".$sc['src_key'] . " = '" . $sc['keyword'] . "'";
		}else if($sc['keyword']){
			$where[] = "(sl.goods_seq like '" . $sc['keyword'] . "%' or sl.goods_name like '%" . $sc['keyword'] . "%')";
		}

		// 창고 검색
		if($sc['sc_wh_seq']){
			$where[] = "sl.wh_seq = '".$sc['sc_wh_seq']."'";
			$regist	.= " and wh_seq = '".$sc['sc_wh_seq']."' ";

			$curea_fld		= 'wh_cur_ea';
			$cursupply_fld	= 'wh_cur_supply_price';
		}

		// 창고 재고 검색
		if($sc['sc_sstock']){
			$where[] = "sl.wh_cur_ea >= '".$sc['sc_sstock']."'";
		}
		if($sc['sc_estock']){
			$where[] = "sl.wh_cur_ea <= '".$sc['sc_estock']."'";
		}

		// 재고유무
		if($sc['stock_yn'] == 'Y'){
			$where[] = "sl.cur_ea > 0";
		}else if($sc['stock_yn'] == 'N'){
			$where[] = "sl.cur_ea = 0";
		}

		// 검색 조건 조합
		if($where){
			$whereSql = " AND " . implode(" AND ", $where);
		}

		// 정렬
		$ordby	= 'sl.goods_seq DESC';
		if($sc['orderby']){
			$orderTmp	= explode("_",$sc['orderby']);
			if(count($orderTmp) > 2)
				$ordby	= 'sl.'.$orderTmp[1] .'_'.$orderTmp[2] . ' ' . $orderTmp[0];
		}

		if($sc['sql_type'] == 'total'){
			$select = "SUM(IFNULL(sl." . $curea_fld . ", 0)) as cur_ea, "
					. "SUM(IFNULL(sl." . $curea_fld . ", 0) * IFNULL(sl." . $cursupply_fld . ", 0)) as total_price";
		}else{
			$select = "
				sl.goods_seq, sl.goods_code, sl.goods_name,
				sl.option_type, sl.option_seq, sl.option_name,
				sl.wh_seq, sl." . $curea_fld . " as cur_ea,
				(sl." . $curea_fld . " * sl." . $cursupply_fld . ") AS total_price,
				sl.ldg_date, sl.regist_date, sl.ldg_seq
			";
		}

		// 재고 수량
		$sql = "
			SELECT
				".$select."
			FROM fm_scm_ledger AS sl INNER JOIN (
					select goods_seq, option_type, option_seq, max(ldg_seq) as ldg_seq
					from fm_scm_ledger
					where ".$regist."
					group by goods_seq, option_type, option_seq
				) AS tmp
				ON ( sl.goods_seq		= tmp.goods_seq
				AND sl.option_type	= tmp.option_type
				AND sl.option_seq	= tmp.option_seq
				AND sl.ldg_seq		= tmp.ldg_seq )
			WHERE sl.goods_seq > 0 ".$whereSql."
			ORDER BY ".$ordby;
		if($sc['sql_type'] == 'total'){
			$query	= $this->db->query($sql);
			$result	= $query->result_array();
		}else{
			if(!$sc['perpage']) $sc['perpage'] = 200;
			$result	= select_page($sc['perpage'], $sc['page'], 10, $sql, '');
		}

		// 창고별 수량
		if($result['page']['totalcount'] > 0){

			foreach($result['record'] as $key => $goods){
				$goods['cur_supply_price'] = $goods['total_price'] / $goods['cur_ea'];

				$wh_sql = "
					SELECT sl.wh_seq, sl.wh_cur_ea, (sl.wh_cur_supply_price * sl.wh_cur_ea) AS wh_cur_price
					FROM fm_scm_ledger AS sl INNER JOIN (
							select goods_seq, option_type, option_seq, max(ldg_seq) as ldg_seq
							from fm_scm_ledger
							where  ".$regist."
								and goods_seq = '".$goods['goods_seq']."'
								and option_type = '".$goods['option_type']."'
								and option_seq = '".$goods['option_seq']."'
							group by goods_seq, option_type, option_seq, wh_seq
						) AS tmp
						ON (sl.goods_seq		= tmp.goods_seq
						AND sl.option_type	= tmp.option_type
						AND sl.option_seq	= tmp.option_seq
						AND sl.ldg_seq		= tmp.ldg_seq )
					WHERE sl.goods_seq > 0 ".$whereSql."
				";
				$wh_query	= $this->db->query($wh_sql);
				$wh_result	= $wh_query->result_array();

				unset($whdata);
				foreach($wh_result as $wh){
					$whdata[$wh['wh_seq']]['wh_cur_ea'] = $wh['wh_cur_ea'];
					$whdata[$wh['wh_seq']]['wh_cur_supply_price'] = $wh['wh_cur_price'] / $wh['wh_cur_ea'];
					$whdata[$wh['wh_seq']]['wh_cur_price'] = $wh['wh_cur_price'];
				}
				foreach($warehouses as $wh2){
					$whdata2[$wh2['wh_seq']] = $whdata[$wh2['wh_seq']];
				}

				// 창고별 수량 더하기
				$tmp[$goods['goods_seq'].$goods['option_type'].$goods['option_seq']] = $goods;
				$tmp[$goods['goods_seq'].$goods['option_type'].$goods['option_seq']]['wh'] = $whdata2;
			}

			$loop['record'] = $tmp;
			$loop['page'] = $result['page'];

		}else if($sc['sql_type'] == 'total'){
			$loop = $result[0];
			$wh_sql = "
				SELECT
					sl.wh_seq,
					SUM(IFNULL(sl.wh_cur_ea, 0)) AS wh_cur_ea,
					SUM(IFNULL(sl.wh_cur_supply_price, 0) * IFNULL(sl.wh_cur_ea, 0)) AS wh_cur_price
				FROM fm_scm_ledger AS sl INNER JOIN (
						select goods_seq, option_type, option_seq, wh_seq, max(ldg_seq) as ldg_seq
						from fm_scm_ledger
						where ".$regist."
						group by goods_seq, option_type, option_seq, wh_seq
					) AS tmp
					ON sl.goods_seq		= tmp.goods_seq
					AND sl.option_type	= tmp.option_type
					AND sl.option_seq	= tmp.option_seq
					AND sl.wh_seq		= tmp.wh_seq
					AND sl.ldg_seq		= tmp.ldg_seq
				WHERE sl.goods_seq > 0 ".$whereSql."
				GROUP BY sl.wh_seq";

			$wh_query	= $this->db->query($wh_sql);
			$wh_result	= $wh_query->result_array();

			unset($whdata);
			foreach($wh_result as $wh){
				$whdata[$wh['wh_seq']]['wh_cur_ea'] = $wh['wh_cur_ea'];
				$whdata[$wh['wh_seq']]['wh_cur_supply_price'] = $wh['wh_cur_price'] / $wh['wh_cur_ea'];
				$whdata[$wh['wh_seq']]['wh_cur_price'] = $wh['wh_cur_price'];
			}
			foreach($warehouses as $wh2){
				$whdata2[$wh2['wh_seq']] = $whdata[$wh2['wh_seq']];
			}

			$loop['wh'] = $whdata2;
		}

		return $loop;
	}

	########## ↑↑ 재고자산명세서 ↑↑ ##### ↓↓ 거래처 정산 ↓↓ ##########

	// 거래처 정산 데이터 추출
	public function get_traderaccount($sc){
		$selectSql	= "select * ";
		$fromSql	= "from fm_scm_trader_account	as sta ";
		$whereSql	= "where sta.act_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "";
		$limitSql	= "";

		// 거래처 검색
		if	($sc['trader_seq']){
			$whereSql	.= " and sta.trader_seq = ? ";
			$addBind[]	= $sc['trader_seq'];
		}elseif	($sc['sc_trader']){
			$whereSql	.= " and sta.trader_seq = ? ";
			$addBind[]	= $sc['sc_trader'];
		}
		// 날짜 검색
		if	($sc['set_date']){
			$whereSql	.= " and sta.act_date  = '" . $sc['set_date'] . "' ";
		}
		if	($sc['sc_sdate']){
			$whereSql	.= " and sta.act_date  >= '" . $sc['sc_sdate'] . "' ";
		}
		if	($sc['sc_edate']){
			$whereSql	.= " and sta.act_date  <= '" . $sc['sc_edate'] . "' ";
		}

		// 그룹조건 추가
		if	($sc['groupby']){
			if	(!is_array($sc['groupby']))	$sc['groupby']	= array($sc['groupby']);
			$groupBy	= "group by " . implode(", ", $sc['groupby']) . " ";
		}
		// 정렬조건 추가
		if	($sc['orderby']){
			if	(!is_array($sc['orderby']))	$sc['orderby']	= array($sc['orderby']);
			$orderBy	= "order by " . implode(", ", $sc['orderby']) . " ";
		}
		// 추출 row 제한 추가
		if	($sc['limit']){
			$limitSql	= "limit " . $sc['limit'] . " ";
		}
		$sql		= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . $limitSql;
		$query		= $this->db->query($sql, $addBind);
		$result		= $query->result_array();

		return $result;

	}

	// 거래처 정산 목록 추출
	public function get_traderaccount_list($sc){

		// 날짜 검색
		if	($sc['set_date']){
			$srcDate	.= "and act_date = '" . $sc['set_date'] . "' ";
		}
		if	($sc['sc_sdate']){
			$srcDate	.= "and act_date >= '" . $sc['sc_sdate'] . "' ";
		}
		if	($sc['sc_edate']){
			$srcDate	.= "and act_date <= '" . $sc['sc_edate'] . "' ";
			$srcEndDate	=  "and act_date <= '" . $sc['sc_edate'] . "' ";
		}

		// subquery1 : 마지막 조회일 기준 정산금액이 남은 거래처목록
		$fromSql1	= "SELECT trader_seq, act_balance AS balance "
					. "FROM fm_scm_trader_account "
					. "WHERE act_seq in ( "
					. "	SELECT MAX(act_seq) as seq "
					. "FROM fm_scm_trader_account "
					. "WHERE act_seq >0 " . $srcEndDate 
					. "GROUP BY trader_seq ) ";
		// subquery2 : 기간내 입/출금 내역 합계 추출
		$fromSql2	= "SELECT trader_seq, "
					. "SUM( IFNULL(act_in_price, 0) ) AS act_in_price, "
					. "SUM( IFNULL(act_out_price, 0) ) AS act_out_price, "
					. "MIN( act_date ) AS min_act_date, "
					. "MAX( act_date ) AS max_act_date "
					. "FROM fm_scm_trader_account "
					. "WHERE act_seq > 0 " . $srcDate
					. "GROUP BY trader_seq ";

		// 전체 쿼리
		$selectSql	= "SELECT fst.trader_seq, "
					. "fst.trader_group, "
					. "fst.trader_id, "
					. "fst.trader_name, "
					. "fst.currency_unit, "
					. "IFNULL(fsta2.act_in_price, 0)	AS act_in_price, "
					. "IFNULL(fsta2.act_out_price, 0)	AS act_out_price, "
					. "fsta1.balance ";
		$fromSql	= "FROM fm_scm_trader				AS fst "
					. "LEFT JOIN (" . $fromSql1 . ")	AS fsta1 "
					. "ON fst.trader_seq = fsta1.trader_seq "
					. "LEFT JOIN (" . $fromSql2 . ")	AS fsta2 "
					. "ON fst.trader_seq = fsta2.trader_seq ";
		$whereSql	= "WHERE fst.trader_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "ORDER BY fst.trader_name ";
		$limitSql	= "";

		// 거래처 검색
		if	($sc['trader_seq']){
			$whereSql	.= " and fst.trader_seq = ? ";
			$addBind[]	= $sc['trader_seq'];
		}elseif	($sc['sc_trader']){
			$whereSql	.= " and fst.trader_seq = ? ";
			$addBind[]	= $sc['sc_trader'];
		}
		// 정렬조건 추가
		if	($sc['orderby']){
			if	(!is_array($sc['orderby']))	$sc['orderby']	= array($sc['orderby']);
			$orderBy	= "order by " . implode(", ", $sc['orderby']) . " ";
		}
		// 추출 row 제한 추가
		if	($sc['limit']){
			$limitSql	= "limit " . $sc['limit'] . " ";
		}

		$sql		= "SELECT (balance + act_out_price - act_in_price)	as carriedover, tmp.* "
					. "FROM ( "
					. $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . $limitSql
					. " )	as tmp ";
		$query		= $this->db->query($sql, $addBind);
		$result		= $query->result_array();

		return $result;
	}

	// 거래처 정산 상세 내역 추출
	public function get_traderaccount_detail($sc){
		$selectSql	= "select * ";
		$fromSql	= "from fm_scm_trader_account_detail	as stad ";
		$whereSql	= "where stad.acdt_seq > 0 ";
		$groupBy	= "";
		$orderBy	= "order by regist_date asc ";
		$limitSql	= "";

		// 거래처 검색
		if	($sc['sc_trader']){
			$whereSql	.= "and stad.trader_seq = ? ";
			$addBind[]	= $sc['sc_trader'];
		}
		// 다중 거래처 검색
		if	($sc['arr_trader_seq']){
			if	(!is_array($sc['arr_trader_seq']))	$sc['arr_trader_seq']	= array($sc['arr_trader_seq']);
			$whereSql	.= "and stad.trader_seq in ('" . implode("', '", $sc['arr_trader_seq']) . "') ";
		}
		// 날짜 검색
		if	($sc['set_date']){
			$whereSql	.= "and act_date = '" . $sc['set_date'] . "' ";
		}
		if	($sc['sc_sdate']){
			$whereSql	.= "and act_date >= '" . $sc['sc_sdate'] . "' ";
		}
		if	($sc['sc_edate']){
			$whereSql	.= "and act_date <= '" . $sc['sc_edate'] . "' ";
		}
		// 그룹조건 추가
		if	($sc['groupby']){
			if	(!is_array($sc['groupby']))	$sc['groupby']	= array($sc['groupby']);
			$groupBy	= "group by " . implode(", ", $sc['groupby']) . " ";
		}
		// 정렬조건 추가
		if	($sc['orderby']){
			if	(!is_array($sc['orderby']))	$sc['orderby']	= array($sc['orderby']);
			$orderBy	= "order by " . implode(", ", $sc['orderby']) . " ";
		}
		// 추출 row 제한 추가
		if	($sc['limit']){
			$limitSql	= "limit " . $sc['limit'] . " ";
		}

		$sql	= $selectSql . $fromSql . $whereSql . $groupBy . $orderBy . $limitSql;
		if	($sc['page'] > 0 && $sc['perpage']){
			$result	= select_page($sc['perpage'], $sc['page'], 10, $sql, $addBind);
		}else{
			$query	= $this->db->query($sql, $addBind);
			$result	= $query->result_array();
		}

		return $result;
	}

	// 거래처별 정산 저장
	public function save_traderaccount($params, $usesum = 'y'){
		$trader_seq	= $params['trader_seq'];
		if	($trader_seq > 0 && $params['act_type']){
			if	(!$params['act_carriedover']){
				// 마지막 정산 상세 추출
				unset($sc);
				$sc['sc_trader']			= $trader_seq;
				$sc['limit']				= 1;
				$sc['orderby']				= 'acdt_seq desc';
				$actd						= $this->get_traderaccount_detail($sc);
				$params['act_carriedover']	= $actd[0]['act_balance'] ? $actd[0]['act_balance'] : 0;
			}
			if	(!$params['act_balance']){
				if	($params['act_type'] == 'pay' || $params['act_type'] == 'carryingout'){
					$params['act_balance']		= $params['act_carriedover'] - $params['act_price'];
				}else{
					$params['act_balance']		= $params['act_carriedover'] + $params['act_price'];
				}
			}
			$params['act_date']				= ($params['act_date'])?$params['act_date']:date('Y-m-d');
			$params['regist_date']			= date('Y-m-d H:i:s');
			$this->db->insert('fm_scm_trader_account_detail', $params);
			$acdt_seq	= $this->db->insert_id();

			// 지급일 시 지급코드 부여
			if	($params['act_type'] == 'pay' || $params['act_type'] == 'def'){
				$act_code	= strtoupper($params['act_type']) . date('YmdHis') . $acdt_seq;
				$this->db->where(array('acdt_seq' => $acdt_seq));
				$this->db->update('fm_scm_trader_account_detail', array('act_code' => $act_code));
			}

			if	($usesum != 'n'){
				$this->calculate_traderaccount(array($trader_seq), $params['act_date']);
			}
		}
	}

	// 당일 거래처 정산 집계 계산
	public function calculate_traderaccount($traders = array(), $act_date = ''){
		if	(!$act_date)	$act_date	= date('Y-m-d');
		if	(is_array($traders) && count($traders) > 0){
			$addWhere				= " and trader_seq in ('" . implode("', '", $traders). "') ";
		}
		$sql	= "select trader_seq, act_date, currency, "
				. "SUM(IF(act_type='def',IFNULL(act_carriedover, 0),0))	as def_act_carriedover, "
				. "SUM(IF(act_type='pay',0,IF(act_type='carryingout',"
				. "(IFNULL(act_price, 0)*-1),IFNULL(act_price, 0))))		as act_in_price, "
				. "SUM(IF(act_type='pay',IFNULL(act_price, 0),0))			as act_out_price "
				. "from fm_scm_trader_account_detail "
				. "where act_date = ? "
				. $addWhere
				. "group by trader_seq ";
		$query	= $this->db->query($sql, array($act_date));
		$result	= $query->result_array();
		if	($result) foreach($result as $k => $data){
			// 마지막 거래처 정산 집계 추출
			unset($sc, $lst_act);
			$sc['trader_seq']	= $data['trader_seq'];
			$sc['orderby']		= 'act_seq desc';
			$sc['limit']		= 1;
			$lst_act			= $this->get_traderaccount($sc);
			$lst_act			= $lst_act[0];

			// 오늘날짜 집계가 이미 있는 경우
			if	($lst_act['act_date'] == $data['act_date']){
				unset($upParams, $whParams);
				$upParams['act_in_price']	= $data['act_in_price'];
				$upParams['act_out_price']	= $data['act_out_price'];
				$upParams['act_balance']	= $lst_act['act_carriedover'] + $data['act_in_price'] - $data['act_out_price'];
				$whParams['trader_seq']		= $data['trader_seq'];
				$whParams['act_date']		= $data['act_date'];
				$this->db->where($whParams);
				$this->db->update('fm_scm_trader_account', $upParams);
 			// 집계가 없거나 오늘 날짜 집계가 없는 경우
			}else{
				if	(!$lst_act['act_balance'] && $data['def_act_carriedover'])
					$lst_act['act_balance']	= $data['def_act_carriedover'];
				if	(!$lst_act['act_balance'])	$lst_act['act_balance']	= '0';

				unset($inParams);
				$inParams['act_date']			= $act_date;
				$inParams['trader_seq']			= $data['trader_seq'];
				$inParams['act_carriedover']	= $lst_act['act_balance'];
				$inParams['act_in_price']		= $data['act_in_price'];
				$inParams['act_out_price']		= $data['act_out_price'];
				$inParams['act_balance']		= $lst_act['act_balance'] + $data['act_in_price'] - $data['act_out_price'];
				$inParams['currency']			= $data['currency'];
				$this->db->insert('fm_scm_trader_account', $inParams);
			}
		}
	}

	########## ↑↑ 거래처 정산 ↑↑ ##### ↓↓ END ↓↓ ##########
}

/* End of file scmmodel.php */
/* Location: ./app/models/scmmodel.php */
