<?php
/**
 * 매출증빙 서류 : 현금영수증/매출증빙 내역
 * @author gabia
 * @since version 1.0 - 2012.06.29
 */
class Salesmodel extends CI_Model {

	function __construct() {
		parent::__construct();
		$this->table_sales			= 'fm_sales';
		$this->table_order			= 'fm_order';
		$this->table_member		= 'fm_member';
		$this->table_member_group		= 'fm_member_group';

		if ($this->config_system['pgCompany']) {
			$this->pg = config_load($this->config_system['pgCompany']);
		}
	}

	/*
	 * 매출증빙관리
	 * @param
	*/
	public function sales_list($sc) {

		$sql = "select SQL_CALC_FOUND_ROWS sales.*, ord.payment,
		(
			SELECT userid FROM ".$this->table_member." WHERE member_seq=sales.member_seq
		) userid,
		(
			SELECT group_name FROM ".$this->table_member." m, ".$this->table_member_group." g WHERE m.group_seq=g.group_seq and m.member_seq=sales.member_seq
		) group_name,
		ifnull(ord.regist_date,sales.order_date) as order_date
		from ".$this->table_sales." sales
		LEFT JOIN ".$this->table_order." ord ON ( (sales.order_seq = ord.order_seq)  )
		where (pg_kind is null or pg_kind not in ('paypal', 'eximbay')) ";//결제이후상태만노출

		if($sc['chk_excel_down'] == 1){
			$sql.=" and sales.seq in (".$sc['sales_seq'].")";
			$sql.=" order by sales.seq desc ";
		}else{

			//if($sc['isAll'] != 'y'){
				$sql	.= " and (ord.step not in ('0','95','99') or sales.type=1) ";
			//}

			if(!empty($sc['keyword']))
			{
				$sql.= " and (sales.order_seq like '%".$sc['keyword']."%' or sales.person like '%".$sc['keyword']."%' or sales.order_name like '%".$sc['keyword']."%') ";
			}

			// 신청일/주문일 둘다 검색가능하게 개선 @2016-07-26 ysm
			if( $sc['date_gb'] == 'all' ) {
				if(!empty($sc['sdate'])) $sql.= " AND ( sales.order_date >= '{$sc['sdate']} 00:00:00' OR sales.regdate >= '{$sc['sdate']} 00:00:00' ) ";
				if(!empty($sc['edate'])) $sql.= " AND ( sales.order_date<= '{$sc['edate']} 24:00:00' OR sales.regdate<= '{$sc['edate']} 24:00:00' )  ";
			}else{
				if(!empty($sc['sdate'])) $sql.= " AND sales.{$sc['date_gb']} >= '{$sc['sdate']} 00:00:00' ";
				if(!empty($sc['edate'])) $sql.= " AND sales.{$sc['date_gb']}<= '{$sc['edate']} 24:00:00' ";
			}

			if( !empty($sc['admin_type']) ) { // 신청구분
				$sql.= " and sales.type in (".implode($sc['admin_type'],',').") ";
			}

			if( !empty($sc['ostep']) ) {//수동/자동
				$sql.= " and (";
				foreach($sc['ostep'] as $type){
					if($type == "1"){
						$str_sql.= " ord.step > '15' AND ord.step < '95'";
					}else if($type == "2"){
						if($str_sql == ""){
							$str_sql = " ord.step <= '15'";
						}else{
							$str_sql = " ord.step <= '15' or ord.step > '15'";
						}
					}
				}
				$sql.= " ".$str_sql.") ";
			}

			if( !empty($sc['tstep']) ) {//수동/자동
				$sql.= " and (";
				foreach($sc['tstep'] as $tstep){
					if($tstep == "1"){
						//$tstep_sql = " sales.tstep = '1' and type <> '0'";
						$tstep_sql = " sales.tstep = '1' and sales.typereceipt <> '0' ";
					}else if($tstep == "2"){
						if($tstep_sql == ""){
							$tstep_sql = " ((sales.tstep = '2' and (sales.approach <> 'unlink' or approach IS NULL)) ";
							$tstep_sql .= " or sales.typereceipt = 0)";
						}else{
							$tstep_sql = $tstep_sql." or (sales.tstep = '2' and (sales.approach <> 'unlink' or approach IS NULL)) or sales.typereceipt = 0";
						}
					}else if($tstep == "5"){
						if($tstep_sql == ""){
							$tstep_sql = " (sales.tstep = '2' and sales.approach = 'unlink') ";
						}else{
							$tstep_sql = $tstep_sql." or (sales.tstep = '2' and sales.approach = 'unlink') ";
						}
					}else if($tstep == "4"){
						if($tstep_sql == ""){
							$tstep_sql = " sales.tstep = '4' ";
						}else{
							$tstep_sql = $tstep_sql." or sales.tstep = '4' ";
						}
					}else if($tstep == "3"){
						if($tstep_sql == ""){
							$tstep_sql = " sales.tstep = '3' ";
						}else{
							$tstep_sql = $tstep_sql." or sales.tstep = '3' ";
						}
					}
				}
				$sql.= " ".$tstep_sql.") ";
			}
			if(count($sc['orefund']) == 1){
				if($sc['orefund'][0] == "1"){
					$sql .= " and EXISTS (select refund_code from fm_order_refund where order_seq = ord.order_seq) ";
				}else if($sc['orefund'][0] == "2"){
					$sql .= " and NOT EXISTS (select refund_code from fm_order_refund where order_seq = ord.order_seq) ";
				}
			}

			###
			if( !empty($sc['typereceipt'])) {
				$typereceipt = "'".join("','", $sc['typereceipt'])."'";
				$sql.= " and sales.typereceipt in ({$typereceipt}) ";
			}

			// 정렬
			if( $sc['orderby'] && $sc['action_mode'] != 'count' ) {
				$sql.=" order by sales.{$sc['orderby']} {$sc['sort']} ";
			} else if( $sc['action_mode'] != 'count' ) {
				$sql.=" order by sales.seq desc ";
			}

			if( ($sc['no_limit'] != 1 || !$sc['no_limit']) && $sc['action_mode'] != 'count')
				$sql .=" limit {$sc['page']}, {$sc['perpage']} ";
		}

		if($sc['action_mode'] == 'count'){ // 메인 카운트
			$tmp_sql = explode('from fm_sales sales', $sql);
			return $this->db->query('select count(*) cnt from fm_sales sales ' . $tmp_sql[1]);
		}

		$query = $this->db->query($sql);
		$data['result'] = $query->result_array();

		//총건수
		$sql = "SELECT FOUND_ROWS() as COUNT";
		$query_count = $this->db->query($sql);
		$res_count= $query_count->result_array();
		$data['count'] = $res_count[0]['COUNT'];

		/**
		 * count setting
		**/
		$sc['total_page']	 = @ceil($data['count']	 / $sc['perpage']);

		$idx = 0;
		foreach($data['result'] as $datarow){$idx++;

			$order 			= $this->ordermodel->get_order($datarow['order_seq']);

			if($order){
				$datarow['settleprice'] = $order['settleprice'];
				$datarow['payment'] = $order['payment'];
				$datarow['order_user_name'] = $order['order_user_name'];
				$datarow['mstep']= $this->arr_step[$order['step']];
				$datarow['mpayment'] = $this->arr_payment[$order['payment']];
				$datarow['pg_transaction_number'] = $order['pg_transaction_number'];
				$datarow['cash_receipts_no'] = ($datarow['cash_no'])?$datarow['cash_no']:$order['cash_receipts_no'];
			}else{
				if($datarow['type'] == 1){//현금영수증 수동발급시
					$datarow['cash_receipts_no'] = $datarow['cash_no'];
				}
			}

			$sql = "select refund_code from fm_order_refund where order_seq = '".$datarow['order_seq']."'";
			$query = $this->db->query($sql);
			$refund = $query->result_array();
			$datarow["refund"] = $refund;

			### TAX
			$datarow['tax_msg'] = "";
			if($datarow['typereceipt'] == 1 && $datarow['hiworks_no']){
				$datarow['tax_msg'] = $this->hiworks_status_msg($datarow['hiworks_status']);
			}


			$datarow['tstep'] = $datarow['tstep'];
			if($datarow['tstep']=='1')
			{
				$datarow['cash_msg'] = "발급신청";
			}
			else if($datarow['tstep']=='2')
			{
				$datarow['cash_msg'] = "발급완료";
			} else if($datarow['tstep']=='3')
			{
				$datarow['cash_msg'] = "발급취소";
			} else if($datarow['tstep']=='4')
			{
				$datarow['cash_msg'] = "발급실패";
			}

			if(!($order['payment'] =='card' && $order['payment'] =='cellphone') && $order['typereceipt']>0 ) {

				if($order['typereceipt'] == 1 ) {//세금계산서
					$datarow['tax_seq'] = $datarow['seq'];
				}elseif($order['typereceipt'] == 2) {//현금영수증
					$datarow['cashreceipt_seq'] = $datarow['seq'];
					if(!$datarow['cash_receipts_no']) {
						$datarow['cash_msg'] = "발급실패";
					}
				}
			}

			if( $this->config_system['pgCompany'] == 'lg' && $order['pg_transaction_number']) {
				$datarow['authdata'] = md5($this->pg['mallCode'] . $order['pg_transaction_number'] . $this->pg['merchantKey']);
			}else{
				$datarow['authdata'] = '';
			}

			$datarow['pgPayStatus']	= false;
			if	(($datarow['payment'] =='card' || $datarow['payment'] =='cellphone') &&
					$datarow['pg_transaction_number'])
				$datarow['pgPayStatus']	= true;

			$datarow['compTaxStatus']	= false;
			if	($datarow['tstep'] == 2 && $datarow['typereceipt'] != 2)
				$datarow['compTaxStatus']	= true;

			$datarow['grayStatus']		= false;
			if	($datarow['compTaxStatus'] || $datarow['pgPayStatus'])
				$datarow['grayStatus']	= true;

			$datarow['number'] =  $data['count']	 - ( ($sc['page'] -1 ) * 1 + $idx + 1) + 1;

			if(filter_var($datarow['email'], FILTER_VALIDATE_EMAIL) == ""){
				$datarow['email_chk'] = 1;
			}else{
				$datarow['email_chk'] = 0;
			}

			if($before_order_seq == $datarow['order_seq']."_".$datarow['order_user_name']){
				if($arr_row_number[$datarow['order_seq']]){
					$arr_row_number[$datarow['order_seq']]++;
				}else{
					$arr_row_number[$datarow['order_seq']] = 2;
				}
				$datarow['row_number'] = $arr_row_number[$datarow['order_seq']];
			}
			$before_order_seq = $datarow['order_seq'].$datarow['order_user_name'];
			$salesloop[] = $datarow;
		}
		unset($data['result']);
		$data['record']	= $salesloop;

		return $data;
	}


	/*
	 * 매출증빙관리
	 * @param
	 */
	public function sales_tax_list($sc)
	{
		# 조회 데이터 설정
		$this->db->select("SQL_CALC_FOUND_ROWS `ord`.*", false);
		$this->db->select([
			"sales.tstep",
			"sales.seq as tax_seq"
		]);

		# 테이블 설정
		$this->db->from("{$this->table_sales} sales");
		$this->db->join("{$this->table_order} ord", 'sales.order_seq = ord.order_seq', 'right');
		$this->db->join("{$this->table_member} mb", 'ord.member_seq = mb.member_seq', 'left');

		# 조건 설정
		$this->db->where('ord.member_seq', $sc['member_seq']);
		$this->db->where('ord.typereceipt !=', '2');
		$this->db->where_not_in("ord.payment", ['card', 'cellphone']);
		$this->db->where_not_in("ord.step", ['0','85','95','99']);

		if (!empty($sc['keyword'])) {
			$this->db->group_start();
			$this->db->like('sales.order_seq', $sc['keyword'], 'both');
			$this->db->like('sales.person', $sc['keyword'], 'both');
			$this->db->group_end();
		}
		
		# 정렬 설정
		$this->db->order_by('ord.order_seq', 'DESC');

		# 페이징 설정
		$this->db->limit($sc['page'], $sc['perpage']);

		$data['result'] = $this->db->get()->result_array();

		//검색총건수
		$sql = "SELECT FOUND_ROWS() as COUNT";
		$query_count = $this->db->query($sql);
		$res_count= $query_count->result_array();
		$data['count'] = $res_count[0]['COUNT'];

		return $data;
	}


	// 매출증빙총건수
	public function get_item_total_count($sc)
	{
		$sql = "select SQL_CALC_FOUND_ROWS sales.seq
		from ".$this->table_sales." sales
		LEFT JOIN ".$this->table_order." ord ON ( (sales.order_seq = ord.order_seq)  )
		where 1 and ( if(sales.typereceipt<>0,15,ord.step) > 0 ) and ord.step > 0";
		$query = $this->db->query($sql);
		return $query->num_rows();
	}

	/*
	 * 매출증빙정보
	 * @param
	*/
	public function get_data($sc) {
		$sql = "select ".$sc['select']." from  ".$this->table_sales."  where 1 ". $sc['whereis'];
		$sql .=" order by seq desc";
		$query = $this->db->query($sql);
		$data = $query->row_array();
		return $data;
	}

	/*
	 * 매출증빙정보
	 * @param
	*/
	public function get_data_numrow($sc) {
		$sql = "select ".$sc['select']." from  ".$this->table_sales."  where 1 ". $sc['whereis'];
		$sql .=" order by seq asc ";
		$query = $this->db->query($sql);
		return $query->num_rows();
	}

	/*
	 * 매출증빙생성
	 * @param
	*/
	public function sales_write($params) {
		$data = filter_keys($params, $this->db->list_fields($this->table_sales));
		$result = $this->db->insert($this->table_sales, $data);
		return $this->db->insert_id();
	}


	/*
	 * 매출증빙 개별수정
	 * @param
	*/
	public function sales_modify($params) {
		if(empty($params['seq']))return false;
		$data = filter_keys($params, $this->db->list_fields($this->table_sales));
		$result = $this->db->update($this->table_sales, $data,array('seq'=>$params['seq']));
		return $result;
	}


	/*
	 * 매출증빙 개별 삭제
	 * @param
	*/
	public function sales_delete($seq) {
		if(empty($seq))return false;
		$result = $this->db->delete($this->table_sales, array('seq' => $seq));
		return $result;
	}


	/*
	 * 매출증빙 회원삭제
	 * @param
	*/
	public function sales_delete_ord($ordno_seq) {
		if(empty($ordno_seq))return false;
		$result = $this->db->delete($this->table_sales, array('ordno_seq' => $ordno_seq));
		return $result;
	}



	public function hiworks_bill_send($data){
		require_once ROOTPATH."/app/libraries/cfg.php";
		$domain			= $this->config_system['webmail_domain'];
		$license_no		= $this->config_system['webmail_key'];
		$license_id		= $this->config_system['webmail_admin_id'];
		$partner_id		= 'A0001';

		# 공급받는 자의 주소 체크(도로명주소 > 지번(구지번주소) @2015-09-24 pjm
		# 우편번호(5자리 > 6자리) - 계산서 발행시 우편번호 사용 안하는듯.
		$address				= $data['address_street'];
		if(!$address) $address	= $data['address'];
		if(!$address){
			return array('result' => false, 'message' => "공급받는 자의 정보 중 주소가 없습니다.");
			exit;
		}
		$address.= $data['address_detail'];//상세주소추가 @2017-04-12

		if($license_id != ""){
			$HB = new Hiworks_Bill($domain, $license_id, $license_no, $partner_id);
			if($data['surtax'] == 0){
				$HB->set_type( "B" , "B", "S" );
				$doc_type = "bill";
			}else{
				$HB->set_type( HB_DOCUMENTTYPE_TAX , HB_TAXTYPE_TAX, HB_SENDTYPE_SEND );
				$doc_type = "taxbill";
			}

			//  기본정보 입력
			$tax_info['person'] = $data['person'] ? $data['person'] : $data['co_ceo'];
			$HB->set_basic_info( $tax_info['person'], $data['email'], $data['phone'], '', '', '');

			 // 세액
			$taxval = (int)$data['surtax'];

			if($taxval <= 0 && $doc_type == 'taxbill'){
				$result = array('result' => false, 'message' => '세금계산서 발행이 불가 - 부가세 금액오류');
				return $result;
			}

			// 공급가
			$supplyprice = get_cutting_price($data['price']) - $taxval;

			$set_paydt = substr($data['order_date'],0,10);
			$set_paydt = $set_paydt ? $set_paydt : date('Y-m-d');

			$supplyprice_total	= (int) get_cutting_price($supplyprice);
			$taxval_total		= (int) $taxval;

			//echo $supplyprice_total." : ".$taxval_total."<br>";
			$HB->set_document_info($set_paydt, $supplyprice_total, $taxval_total, HB_PTYPE_RECEIPT, '', '', '', '', '');

			$basic = config_load('basic');

			# 공급하는 자의 우편번호(5자리 > 6자리), 주소 체크(도로명주소 > 지번(구지번주소) @2015-09-24 pjm
			$companyAddress = $basic['companyAddress_street'];
			if(!$companyAddress) $companyAddress = $basic['companyAddress'];
			if(!$companyAddress){
				return array('result' => false, 'message' => "공급하는 자의 정보 중 주소가 없습니다.");
				unset($HB, $rs, $status);
				exit;
			}
			$companyAddress.=$basic['companyAddressDetail'];//상세주소추가 @2017-04-12

			$HB->set_company_info( $basic['businessLicense'], $basic['companyName'], $basic['ceo'], $companyAddress, $basic['businessConditions'], $basic['businessLine'], HB_COMPANYPREFIX_SUPPLIER );

			$HB->set_company_info( $data['busi_no'], $data['co_name'], $data['co_ceo'], $address, $data['co_status'], $data['co_type'], HB_COMPANYPREFIX_CONSUMER );

			###
			$tax_info[paydt] = explode("-",substr($data['order_date'],0,10));
			$tax_info[paydt][1] = $tax_info[paydt][1] ? $tax_info[paydt][1] : date('m');
			$tax_info[paydt][2] = $tax_info[paydt][2] ? $tax_info[paydt][2] : date('d');

			if($data["goodsname"] == ""){
				$goods_name = "물품구매대금";
			}else{
				$goods_name = $data["goodsname"];
			}

			$tax_info[ea] = 1;
			$tax_info[r_price]	= $supplyprice_total;
			$price				= $supplyprice_total;
			$tax_row			= $taxval_total;
			$sum				= (int) $data['price'];

			$HB->set_work_info( $tax_info[paydt][1], $tax_info[paydt][2], $goods_name, 'EA', $tax_info[ea], $tax_info[r_price], $price, $tax_row, '', $sum );

			$rs = $HB->send_document( HB_SOAPSERVER_URL );
			if (!$rs) {
				$msg = iconv("EUC-KR", "UTF-8", $HB->showError());
				$result = array('result' => false, 'message' => $msg);
			}else{
				$hiworks_no = $HB->get_document_id();
				$HB->set_document_id($hiworks_no);
				$status = $HB->check_document( HB_SOAPSERVER_URL );
				$add_qry = "";
				if($status[0][now_state] == 'S') {
					$add_qry = '2';
				}
				$state = explode("|",$status[0][now_state]);
				$sql = "UPDATE fm_sales SET hiworks_no = '{$hiworks_no}', tstep = '5', hiworks_status = '{$state[0]}', issue_date = now() WHERE seq = '{$data[seq]}'";
				$this->db->query($sql);
				$result = array('result' => true, 'message' => $state[0]);
			}
		}else{
			$result = array('result' => true, 'message' => "신청완료");
		}
		return $result;
		unset($HB, $rs, $status);
	}


	public function hiworks_bill_check($seq){
		$sql	= "SELECT * FROM fm_sales WHERE seq = '{$seq}'";
		$query	= $this->db->query($sql);
		$data	= $query->result_array();

		if($data[0]){
			require_once ROOTPATH."/app/libraries/cfg.php";
			$domain			= $this->config_system['webmail_domain'];
			$license_no		= $this->config_system['webmail_key'];
			$license_id		= $this->config_system['webmail_admin_id'];
			$partner_id		= 'A0001';
			$HB = new Hiworks_Bill($domain, $license_id, $license_no, $partner_id);
			$HB->set_document_id($data[0]['hiworks_no']);

			$documet_result_array = $HB->check_document( HB_SOAPSERVER_URL );
			if (!$documet_result_array) {
				$msg = iconv("EUC-KR", "UTF-8", $HB->showError());
				$result = array('result' => false, 'message' => $msg);
			}else{
				$status = explode("|",$documet_result_array[0]['now_state']);
				//$HB->view('Result :', $documet_result_array);
				if($data[0]['hiworks_status']==$status[0]){
					$result = array('result' => true, 'message' => $this->hiworks_status_msg($data[0]['hiworks_status']));
				}else{
					$add_qry = "";
					if($status[0]=="S"){
						$add_qry = ", tstep = 2 ";
					}
					$sql = "UPDATE fm_sales SET hiworks_status = '{$status[0]}', issue_date = now() {$add_qry} WHERE seq = '{$data[0]['seq']}'";
					$this->db->query($sql);
					$result = array('result' => true, 'message' => $this->hiworks_status_msg($status[0]));
				}
			}
			return $result;
			unset($HB, $rs);
		}
	}


	public function hiworks_status_msg($hiworks_status){
		switch($hiworks_status){
			case "W"; $datarow['tax_msg'] = "승인요청전"; break;
			case "T"; $datarow['tax_msg'] = "승인요청"; break;
			case "R"; $datarow['tax_msg'] = "승인요청"; break;
			case "S"; $datarow['tax_msg'] = "발행"; break;
			case "B"; $datarow['tax_msg'] = "반려"; break;
			case "C"; $datarow['tax_msg'] = "승인취소요청"; break;
			case "A"; $datarow['tax_msg'] = "승인취소완료"; break;
			case "E"; $datarow['tax_msg'] = "에러"; break;
			case "1"; $datarow['tax_msg'] = "전송중"; break;
			case "2"; $datarow['tax_msg'] = "전송중"; break;
			case "3"; $datarow['tax_msg'] = "전송중"; break;
			case "4"; $datarow['tax_msg'] = "전송완료"; break;
			case "5"; $datarow['tax_msg'] = "전송실패"; break;
			default: $datarow['tax_msg'] = "전송"; break;
		}
		return $datarow['tax_msg'];
	}

	public function sales_log_wirte($seq, $log_msg){
		if	($seq && $log_msg){
			$log_msg	= addslashes($log_msg);
			$sql		= "insert into fm_sales_log (receipt_seq, reg_date, log_msg)"
						. "values(".$seq.", '".date('Y-m-d H:i:s')."','".$log_msg."')";
			$this->db->query($sql);
		}
	}

	public function get_sales_log($seq){
		$query = $this->db->from('fm_sales_log')->where('receipt_seq', $seq);
		$query = $query->get();

		$data = $query->result_array();
		$data['count'] = count($data);
		return $data;
	}

	public function tax_calulate($tax,$exempt,$shipping_cost,$exempt_sale,$tax_sale,$mode='PAY',$tax_goods_cnt=0)
	{

		$order_cfg = ($this->cfg_order) ? $this->cfg_order : config_load('order');

		$vat = $order_cfg['vat'] ? $order_cfg['vat'] : 10;

		# use_emoney_price : 사용한 마일리지/예치금(세금계산서 설정 적용된 금액)

		$tax			= get_cutting_price($tax);					// 과세 상품금액
		$exempt			= get_cutting_price($exempt);				// 비과세 상품금액
		$shipping_cost	= get_cutting_price($shipping_cost);		// 과세 배송비 금액
		$exempt_sale	= get_cutting_price($exempt_sale);			// 비과세 할인액(에누리)
		$tax_sale		= get_cutting_price($tax_sale);				// 과세 할인액
		$surtax			= 0;										// 총 부가세

		//결제단이 아닌 개별신청은 $tax값이 결제금액(할인이 차감된 금액)으로 입력됨
		if($mode == 'PAY')	$exempt_sale	+= $tax_sale;
		else				$exempt_sale	= 0;

		if($tax > 0 || $tax_goods_cnt > 0){
			$tax		= $tax + $shipping_cost;
		}else{
			$exempt		= $exempt + $shipping_cost;
		}

		$debug_log = array();
		$debug_log[] = "tax		: ".$tax;
		$debug_log[] = "exempt		: ".$exempt;
		$debug_log[] = "exempt_sale	: ".$exempt_sale;

		if($tax){ // 과세 상품가합
			if( $tax < $exempt_sale){
				$exempt_sale	= $exempt_sale - $tax;
				$supply			= 0;
			}else{
				$supply			= $tax- $exempt_sale;
				$exempt_sale	= 0;;
			}

			// 부가세 계산
			if($supply){
				$surtax = $supply - round($supply / (1 + ($vat / 100)));
				$supply = $supply - $surtax;
			}
			$debug_log[] = "supply(과세 공급가)	: ".$supply;
			$debug_log[] = "surtax(부가세)		: ".$surtax;
		}

		if($exempt){ // 비과세 상품가합
			$supply_free = $exempt - $exempt_sale;
		}

		$debug_log[] = "total supply(총 공급가)	: ".($supply + $supply_free);
		$debug_log[] = "total surtax(총 부가세)	: ".($surtax);
		$debug_log[] = "total (총 계산서 액)	: ".($supply + $supply_free + $surtax);

		# supply : 공급가액(과세액에 대한)
		# surtax : 부가세액
		# supply_free : 비과세액
		$result = array('supply'=>$supply,'surtax'=>$surtax,'supply_free'=>$supply_free);
		return $result;
	}

	public function get($params, $field_str='', $orderbys=''){
		$this->db->where($params);
		if( $orderbys ) foreach($orderbys as $orderby1=>$orderby2){
			$this->db->order_by($orderby1, $orderby2);
		}
		if( $field_str ) $this->db->select($field_str);
		return $this->db->get($this->table_sales);
	}
}
?>
