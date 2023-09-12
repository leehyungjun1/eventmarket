<?php
/**
 * 정산개선
 * @since 
 */
class accountAllmodel extends CI_Model {
	var $tb_act_tmp				= 'fm_account_tmp';									//1 임시매출 테이블
	var $tb_act_cal_sal			= 'fm_account_calculate_sales';						//2 미정산(전월/보류) 테이블
	var $tb_act_cal_ym			= 'fm_account_calculate_sample';					//3 월별통합정산 테이블
	var $tb_act_fee				= 'fm_account_payment_fee';							//4 결제방식별 정산수수료 테이블
	var $tb_act_log				= 'fm_account_log';									//5 정산처리 로그 테이블
	var $tb_seller_stats		= 'fm_account_seller_stats';						//6 입점사별 정산통계 테이블

	var $tb_market_orders		= 'fm_market_orders';								// 연동 마켓 주문 테이블
	
	var $sTbScapSalesMonthly	= array(											
		'order'			=> 'fm_scrap_sales_monthly_order',							// 수집 월별 매출 통계 테이블
		'refund'		=> 'fm_scrap_sales_monthly_refund',							// 수집 월별 환불 통계 테이블
		'commission'	=> 'fm_scrap_sales_monthly_commission',						// 수집 월별 정산 통계 테이블
	);
	
	var $iOnTimeStamp			= '';											// 기준 정산일 타임 스템프
	var $_debug					= '';
	var $_tmp_emoney_rest		= array();											// 이머니,예치금,에누리 짜투리 계산용

	/**
	* 수수료구분
	* 'goods'	=> '상품별 수수료', 'pg'	=> 'PG사별 수수료'
	* PG수수료 방식적용 차후 입점사별 적용시 추가개발 필요
	* @
	**/
	//var $account_fee_ar	 = array('goods'	=> false,'pg'	=> true);
	var $account_fee_ar	 = array('goods'	=> true,'pg'	=> false);

	/**
	* 정산구분 정보
	* 주문/반품한불 정산대상 complete/carryover -> 미정산데이타 처리
	* 결제취소/본사주문/본사위탁주문 정산대상 아니고 매출에만 적용 overdraw -> 미정산데이타 미처리 
	* @
	**/
	var $account_type_ar	 = array('order'	=> '구매확정',
										'refund'	=> '환불완료',
										'return'	=> '반품완료',//반품배송비
										'rollback'	=> '취소완료',//되돌리기
										'exchange'    => '구매확정', // 교환에 의한 재주문건도 주문처럼 구매확정으로 노출
										'after_refund'	=> '환불완료',
										'deductible'=> '환불완료',
								);	
	//주문구분 정보
	var $order_type_ar		 = array(	'option'	=> '상품',
										'suboption'	=> '상품',
										'shipping'	=> '배송비',
										'returnshipping'	=> '반품배송비',
										'penalty'	=> '환불위약금');

	// 주문경로 배열 : 'shoplinker' -> 기획 정산제외
	var $order_referer_ar	 = array(	'shop'		=> '내사이트', //무통장
										'pg'		=> 'PG', 
										'kakaopay'	=> '카카오페이', 
										'talkbuy'	=> '카카오페이 구매', 
										'npay'		=> '네이버페이', 
										'npg'		=> '네이버PG');

	// 주문경로 오픈마켓 배열 : naverstorfarm->naverstorefarm
	var $order_referer_om_ar = array(	'storefarm' => '스마트스토어',
										'open11st'	=> '11번가', 
										'coupang'	=> '쿠팡'
										);//네이버스토어팜->스마트스토어 변경

	//검증용 필수항목(UNIQUE KEY)	'deposit_date',
	var $account_tmp_requireds = array('order_seq',
								'item_seq',
								'order_form_seq',
								'shipping_group_seq',
								'order_goods_seq',
								'account_type',
								'order_type',
								'deposit_date',
								'ea');
	var $acc_requireds = array('order_seq',
								'item_seq',
								'order_form_seq',
								'shipping_group_seq',
								'account_type',
								'order_type',
								'refund_code',
								'return_code');

	// 검산 비교용 기초 데이터
	var $arr_base_info = array(
		'order_seq', 
		'item_seq', 
		'order_form_seq', 
		'shipping_provider_seq',
		'shipping_group_seq',
		'refund_code', 
		'return_code', 
		'refund_type', 
		'account_type',
		'order_type', 
		'deposit_date', 
	);
	
	// 검산 비교 필드 - 특정 필드만 비교
	var $arr_diff_info = array(
		'ea'						// 주문수량
		, 'exp_ea'					// 정산수량
		, 'ac_ea'					// 남은 정산 대상 수량
	);
	
	// 검산 비교 예외 필드 - 향후 모든 필드를 검산 가능할때 예외로 처리
	var $arr_exception_info = array(
		'seq'						// 고유키는 생성 안되므로 예외
		, 'regist_date'				// 등록일자는 검산툴을 실행한 일자이므로 예외
		, 'up_date'					// 수정일자는 검산툴을 실행한 일자이므로 예외
		, 'step'					// 주문상태는 항시 변하게 처리되므로 예외
		, 'acc_date'				// 정산확정일자는 실제 정산이 완료된 일자이므로 추적이 불가함.
	);
	
	var $arr_checker_equation = array(
		'basic' => array(
				'title' => '기본'
				, 'equation' => '!(\'$origin_sales[$check_fields]\' == \'$checker_sales[$check_fields]\')'
				, 'desc' => '값이 다를때'
		)
		, 'not_zero' => array(
				'title' => '0이 아닐때'
				, 'equation' => '!(\'$checker_sales[$check_fields]\' == \'\' && \'$origin_sales[$check_fields]\' == \'0.00\' || \'$checker_sales[$check_fields]\' == \'0\' && \'$origin_sales[$check_fields]\' == \'\')'
				, 'desc' => '0이 아닐때'
		)
		,'coupon_value_type_db_default' => array(
				'title' => 'coupon_value_type DB 기본 값'
				, 'equation' => '!(\'$check_fields\' == \'coupon_value_type\' && \'$checker_sales[$check_fields]\' == \'\' && \'$origin_sales[$check_fields]\' == \'pass\')'
				, 'desc' => 'coupon_value_type의 경우 DB 테이블의 기본 값이 \'pass\''
		)
		, 'commission_text_modified' => array(
				'title' => 'commission_text 데이터 호환'
				, 'equation' => '!(\'$check_fields\' == \'commission_text\' && \'$checker_sales[$check_fields]\' == \'0-0-0=0  \' && \'$origin_sales[$check_fields]\' == \'0--0=0  \')'
				, 'desc' => 'commission_text 경우 $sales_unit_feeprice 의 기본 값을 0으로 추가 했으므로 기존 데이터 호환을 위해'
		)
		, 'ignore_time_confirm_date' => array(
				'title' => '확정일자 시분초 무시'
				, 'equation' => '!(\'$check_fields\' == \'confirm_date\' && (substr(\'$checker_sales[$check_fields]\', 0, 10) == substr(\'$origin_sales[$check_fields]\', 0, 10)))'
				, 'desc' => 'confirm_date 경우 일자가 동일하면 동일한 것으로 판단, 이하 단위는 검산툴에서 구현 불가<br/>'
					. '검산툴의 confirm_date 는 구매확정일, 취소완료일, 환불완료일 중 가장 늦은 것으로 판단하나<br/>'
					. '이중 구매확정일은 data("Y-m-d") 형식이라 시분초 데이터가 없으므로<br/>'
					. '정산데이터의 confirm_date 실제 정산이 발생할 때 당시 data("Y-m-d H:i:s")로 넣으므로 갭 발생.'
		)
		, 'ignore_exchange_order_confirm_date' => array(
				'title' => '맞교환주문 확정일자 무시'
				, 'equation' => '!(\'$check_fields\' == \'confirm_date\' && (\'$checker_sales[account_type]\' == \'exchange\'))'
				, 'desc' => 'confirm_date 경우 맞교환 주문은 체크하지 않음.<br/>'
						.'맞교환 주문의 정산은 없기 때문에 무의미하며<br/>'
						.'만약 맞교환주문의 confirm_date도 정석적으로 추적하기 위해서는 <br/>'
						.'calculate_account_ea 에서 원주문의 구매확정일을 추가로 얻어 비교해야함.'
		)
		, 'ignore_exchange_order_ea' => array(
				'title' => '맞교환주문 정산 수량 무시'
				, 'equation' => '!(in_array(\'$check_fields\', array(\'exp_ea\',\'ac_ea\')) && (\'$checker_sales[account_type]\' == \'exchange\'))'
				, 'desc' => 'exp_ea, ac_ea 경우 맞교환 주문은 체크하지 않음.<br/>'
						.'맞교환 주문의 정산은 없기 때문에 무의미하며<br/>'
						.'만약 맞교환주문의 정산수량도 정석적으로 추적하기 위해서는 <br/>'
						.'calculate_account_ea 에서 원주문의 상태가 완료되었는지 추적하여 반환해야함'
		)
		, 'ignore_exchange_order_status' => array(
				'title' => '맞교환주문 정산 상태 무시'
				, 'equation' => '!(in_array(\'$check_fields\', array(\'status\')) && (\'$checker_sales[account_type]\' == \'exchange\'))'
				, 'desc' => 'status 경우 맞교환 주문은 체크하지 않음.<br/>'
						.'맞교환 주문의 정산은 없기 때문에 무의미하며<br/>'
						.'만약 맞교환주문의 상태도 정석적으로 추적하기 위해서는 <br/>'
						.'calculate_account_ea 에서 원주문의 상태가 완료되었는지 추적하여 반환해야함'
		)
		, 'ignore_return_shipping' => array(
				'title' => '반품배송비 복사 필드 무시'
				, 'equation' => '!(in_array(\'$check_fields\', array(\'payment_type\', \'sales_basic\', \'api_pg_price\')) '
								. ' && (\'$checker_sales[account_type]\' == \'return\') '
								. ' && (\'$checker_sales[order_type]\' == \'shipping\')) '
				, 'desc' => '반품배송비의 경우 특정 필드를 제외하고 대부분 기존 테이블에서 복사하여.<br/>'
						.'생성하므로 비교가 무의미함'
		)
		, 'ignore_cancel_pay_confirm_date' => array(
				'title' => '결제취소 시 완료일 필드 무시'
				, 'equation' => '!(in_array(\'$check_fields\', array(\'confirm_date\')) '
								. ' && (in_array(\'$checker_sales[refund_type]\', array(\'cancel_payment\', \'return\'))) '
								. ' && (\'$checker_sales[return_code]\' == \'\')'
								. ' && (\'$checker_sales[refund_code]\')'
								. ')'
				, 'desc' => '반품배송비의 경우 특정 필드를 제외하고 대부분 기존 테이블에서 복사하여.<br/>'
						.'생성하므로 비교가 무의미함'
		)
		, 'ignore_confirm_date' => array(
				'title' => '완료일 필드 무시'
				, 'equation' => '!(in_array(\'$check_fields\', array(\'confirm_date\')) '
								. ')'
				, 'desc' => '완료일필드를 중구난방 케이스에 따라서 추출하여 추적이 불가능하므로<br/>'
						.'비교가 무의미함'
		)
	);
	var $tool_mode = false;	// 정산툴모드여부
	
	var $perpage = '100';	// 페이지당 노출 수 
	
	var $jsonRequestScrapSalesMonthly = array();	// 통계 데이터 수집 요청 변수
	
	function __construct() {
		parent::__construct();
		
		
		if(empty($this->accountall_helper)){
			$this->load->helper('accountall');
		}
		
		$this->iOnTimeStamp = time();
		$this->iOnTimeStampOrigin = $this->iOnTimeStamp;	// 날짜가 변경됬는지 확인용
		// mktime 숫자형으로 입력
		// $this->iOnTimeStamp = mktime($hour, $min, $sec, $month, $day, $year);
		// $this->iOnTimeStamp = mktime(3, 0, 0, 6, 11, 2019);		// 2019년 6월 11일 3시
		//강원마트(sub1) 에서만 적용 그외 입점몰에서는 소스는 동일하나 기능사용안함합니다.@2018-03-02 || $_SERVER['SERVER_ADDR'] !='10.13.24.13' 
		if(!strstr($this->uri->config->config['base_url'],"gwmart-sub1") || !strstr($this->uri->config->config['base_url'],"gwmart.kr") ) {
			return;
		}
		$this->account_table_finish = config_load('account_table_finish');//정산확정버튼추가 @2018-03-15
		//$this->get_table_ym_ck('calculate');//월별 정산테이블 생성
		$this->set_cutting_sale();
	}
	
	## 절사설정
	public function set_cutting_sale(){
		// 절사 설정 저장
		if	($this->ci->config_system)	$cfg	= $this->ci->config_system;
		else							$cfg	= config_load('system');

		if	($cfg['cutting_sale_use'] != 'none'){
			$this->cfg_cutting_sale	= array('action'	=> $cfg['cutting_sale_action'],
											'price'		=> $cfg['cutting_sale_price']);
		}
	}

	## 최종할인 금액 절사 처리
	public function cut_sale_price($price){
		$action		= $this->cfg_cutting_sale['action'];
		$unit		= $this->cfg_cutting_sale['price'];

		if	($action && $unit > 0){
			switch($action){
				case 'dscending':
					$price = floor( (string) ($price / $unit) ) * $unit;
				break;
				case 'rounding':
					$price = round($price / $unit) * $unit;
				break;
				case 'ascending':
					$price = ceil($price / $unit) * $unit;
				break;
			}
		}

		return $price;
	}

	/*
	 * 월별 테이블 생성여부
	 * @param type 정산(cal) 테이블유형
	 * @param tb_act_ym 월별지정으로 당월로 지정
	*/
	public function get_table_ym_ck($type='calculate', $tb_act_ym=null) {
		if(!$tb_act_ym) $tb_act_ym	=	date('Ym', $this->iOnTimeStamp);
		
		$this->tb_act_cal_ym_new		= str_replace("sample",$tb_act_ym,$this->tb_act_cal_ym);
		//$this->tb_act_sales_ym_new		= str_replace("sample",$tb_act_ym,$this->tb_act_sales_ym);
		$account_id = 'fm_account_calculate_'.$tb_act_ym;

		/* 솔루션의 전체적인 세션의 사용처 남발을 줄이기 위해 삭제 by lgs 2019-03-28
		//속도를 위해 세션으로 담기1
		$session_account_ym_use = ( $this->session->userdata($account_id) )?$this->session->userdata($account_id):$_SESSION[$account_id];
		if($session_account_ym_use['use']) {
			return $account_id;
		}
		*/
		
		//속도를 위해 config 체크2
		$this->account_table_use = config_load('account_table_use');
		$account_ym_use = $this->account_table_use[$account_id];
		if($account_ym_use['use']) {
			$this->session->set_userdata(array($account_id=>'use'));
			return $account_id;
		}
		
		if(!$tb_act_ym_new) $tb_act_ym_new = $this->tb_act_cal_ym_new;
		$query = "CREATE TABLE IF NOT EXISTS {$tb_act_ym_new} like {$this->tb_act_cal_ym}";
		$result = $this->db->query($query);//debug_var($query);
		if($result) {
			config_save('account_table_use',array($account_id=>'use'));
			$this->session->set_userdata(array($account_id=>'use'));
		}
		return $tb_act_ym_new;
	}

	/*
	 * 테이블 최적화
	 * @param tb_act 미정산(전월/보류)테이블 
	*/
	public function get_table_optimize($tb_act=null) {
		if(!$tb_act) return;
		$sql = "optimize table ".$tb_act;
		$this->db->query($sql);
	}

	/*
	 * 임시매출 주문서 정보 가져오기(필수!!)
	 * @
	*/
	public function get_act_tmp_total($order_seq, $wheres =array()){
		$sql = "select count(*) as cnt
		from {$this->tb_act_tmp}
		where order_seq=?
		";

		$binds[]	= $order_seq;
		if($wheres) {
			foreach($wheres as $k=>$v){
				$sql .= " and {$k}=?";
				$binds[] = $v;
			}
		}

		$query = $this->db->query($sql,$binds);
		if($query) $act_tmp = $query->row_array();
		return $act_tmp['cnt'];
	}

	/*
	 * 기존 주문서 정보 가져오기(필수!!)
	 * @
	*/
	public function get_act_sales_cal_total($tbl, $order_seq, $wheres =array()){
		$sql = "select count(*) as cnt
		from {$tbl}
		where order_seq=?
		";

		$binds[]	= $order_seq;
		if($wheres) {
			foreach($wheres as $k=>$v){
				$sql .= " and {$k}=?";
				$binds[] = $v;
			}
		}

		$query = $this->db->query($sql,$binds);
		if($query) $act_tmp = $query->row_array();
		return $act_tmp['cnt'];
	}

	/*
	 * 정산수수료테이블 저장
	 * 강원마트는 PG사별수수료를 따름
	 * 퍼스트몰에서는 상품별/업체별/PG사별 확장석이 용이하도록 구조개선
	 * @
	*/
	public function insert_payment_fee($params) {
		  $data = filter_keys($params, $this->db->list_fields($this->tb_act_fee));
		  $this->db->insert($this->tb_act_fee, $data);
		  $result = $this->db->insert_id();
		return $result;
	}

	/*
	 * 정산수수료테이블 저장
	 * 강원마트는 PG사별수수료를 따름
	 * 퍼스트몰에서는 상품별/업체별/PG사별 확장석이 용이하도록 구조개선
	 * @
	*/
	public function get_fee_info($pg=null,$params = '') {
		$pg = ($pg)?$pg:'pg';
		$addWhere	=" where fee_type = '{$pg}' ";
		if	($params['payment'])$addWhere	.= ($addWhere)?" and payment = '{$params['payment']}' ":" where payment = '{$params['payment']}' ";

		$unit_arr	= array('P'=>'%', 'W'=>'원');
		$query		= "select * from {$this->tb_act_fee}". $addWhere . " order by seq ";
		$result		= $this->db->query($query);
		if($result){
			$data		= $result->result_array();
			foreach($data as $k => $list){
				$resultdata['data']	= $list;
				$resultdata['text']	= $list['payment_name'].':'.$list['commission_rate'].$unit_arr[$list['commission_rate_unit']];
				if($list['max_commission_rate'] > 0) $resultdata['text']	.= '(최대 '.$list['max_commission_rate']."원)";
			}
		}
		return $resultdata;
	}
	
	
	/**
	* 정산 처리로그
	* @
	**/
	public function set_log($order_seq,$type,$actor,$title='',$detail='',$caccel_arr='',$export_code='',$add_info=''){
		$tmp = array(
			'HTTP_HOST'=>$_SERVER['HTTP_HOST'],
			'REMOTE_ADDR'=>$_SERVER['REMOTE_ADDR'],
			'REQUEST_URI'=>$_SERVER['REQUEST_URI'],
			'HTTP_REFERER'=>$_SERVER['HTTP_REFERER'],
			'GET'=>$_GET,
			'POST'=>$_POST
		);

		$sys_memo = serialize($tmp);
		
		if(!$title) $title = "";

		$uristring = explode('/',$_SERVER['REQUEST_URI']);
		if( defined('__ADMIN__') === true ) {//관리자
			$sMtype	= 'm';//maneger
			$sMseq	= $this->managerInfo['manager_seq'];
		}elseif( defined('__SELLERADMIN__') === true ) {//입점관리자
			$sMtype	= 'p';//provider
			$sMseq	= $this->providerInfo['provider_seq'];
		}elseif( $_SERVER['SHELL'] || $uristring[1]== '_gabia' || $uristring[1]== '_batch' || php_sapi_name() == 'cli' ) {//자동
			$sMtype	= 's';//system
			$sMseq	= '';
		}else{
			if( $this->userInfo['member_seq'] ) {//회원
				$sMtype	= 'u';//user
				$sMseq	= $this->userInfo['member_seq'];
			}else{//비회원
				$sMtype	= 'n';//none
				$sMseq	= '';
			}
		}

		$detail .= chr(10).$sys_memo;
		$data = array();
		$data['order_seq']		= $order_seq;
		$data['type']			= $type;
		$data['actor']			= $actor;
		$data['mtype']			= $sMtype;
		$data['mseq']			= $sMseq;
		$data['title']			= $title;
		$data['detail']			= $detail;
		$data['add_info']		= $add_info;
		$data['regist_date']	= date('Y-m-d H:i:s', $this->iOnTimeStamp);
		if($export_code){
			$data['export_code'] = $export_code;
		}
		if($caccel_arr) $data 	= array_merge($data,$caccel_arr);
		$this->db->insert($this->tb_act_log, $data);
	}
	
	/*
	 * 종합 임시매출테이블 추가
	 * @param
	*/
	public function insert_tmp_query($params,$tb_act_tmp) {
		  $data = filter_keys($params, $this->db->list_fields($tb_act_tmp));
		  
		  $this->db->insert($tb_act_tmp, $data);//debug_var($this->db->last_query());
		  $result = $this->db->insert_id();
		return $result;
	}
	
	/*
	 * 종합 미정산 매출테이블 추가
	 * @param
	*/
	public function create_act_cal_query($params) {
		  $data = filter_keys($params, $this->db->list_fields($this->tb_act_cal_sal));
		  //debug_var("accountallmodel->insert_tmp_query() ".__LINE__);debug_var($data);
		  $this->db->insert($this->tb_act_cal_sal, $data);//debug_var($this->db->last_query());
		  $result = $this->db->insert_id();
		return $result;
	}
	
	/*
	 * 구매확정시 임시 매출데이타 삭제
	 * @param order_seq			주문번호
	 * @param seq				고유번호
	*/
	public function del_calculate_sal_tmp($order_seq, $seq=null)
	{
		if(!$order_seq) return false;
		$bind[0] = $order_seq;
		if($seq) $addsqll = " and seq = {$seq}";
		$query = "delete from {$this->tb_act_tmp} where order_seq=? ".$addsqll;
		$this->db->query($query,$bind);
	}

	/*
	 * 되돌리기 또는 구매확정 인한 미정산(전월/보류)테이블 삭제
	 * @param account_type		되돌리기가 아닌 경우 배송그룹별로 제거
	 * @param accountdata		주문정보
	*/
	public function del_calculate_sal_finish($order_seq,$account_type='order', $accountdata=null)
	{
		if(!$order_seq) return false;
		$bind[0] = $order_seq;
		if( $account_type != 'rollback' ) 
		{
			if($accountdata['goods_seq']) $addsqll .= " and order_goods_seq = '{$accountdata[goods_seq]}'";
			if($accountdata['order_form_seq']) $addsqll .= " and order_form_seq = '{$accountdata[order_form_seq]}'";
			$addsqll .= " and item_seq = '{$accountdata[item_seq]}'";
			$addsqll .= " and order_type = '{$accountdata[order_type]}'";
			$addsqll .= " and shipping_group_seq = '{$accountdata[shipping_group_seq]}'";//동일배송그룹 제거
		}

		$selquery = "select seq from {$this->tb_act_cal_sal} where order_seq=? ".$addsqll." limit 1";
		$selquery = $this->db->query($selquery,$bind);//debug_var($this->db->last_query());
		if($selquery) $requireddata = $selquery->row_array();
		if($requireddata['seq']) {
			$query = "delete from {$this->tb_act_cal_sal} where order_seq=? ".$addsqll;
			$this->db->query($query,$bind);
		}
	}

	/*
	 * 되돌리기 또는 구매확정 인한 미정산(전월/보류)테이블 삭제
	 * @param account_type		되돌리기가 아닌 경우 배송그룹별로 제거
	 * @param accountdata		주문정보
	*/
	public function del_calculate_div_sal_finish($order_seq,$account_type='order', $accountdata=null)
	{
		if(!$order_seq) return false;
		$bind[0] = $order_seq;
		if( $account_type != 'rollback' ) 
		{
			if($accountdata['goods_seq']) $addsqll .= " and order_goods_seq = '{$accountdata[goods_seq]}'";
			if($accountdata['order_form_seq']) $addsqll .= " and order_form_seq = '{$accountdata[order_form_seq]}'";
			$addsqll .= " and item_seq = '{$accountdata[item_seq]}'";
			$addsqll .= " and order_type = '{$accountdata[order_type]}'";
			$addsqll .= " and shipping_group_seq = '{$accountdata[shipping_group_seq]}'";//동일배송그룹 제거
			$addsqll .= " and exp_ea = '0'";
		}

		$selquery = "select seq from {$this->tb_act_cal_sal} where order_seq=? ".$addsqll." limit 1";
		$selquery = $this->db->query($selquery,$bind);//2019-04-03_var($this->db->last_query());
		if($selquery) $requireddata = $selquery->row_array();
		if($requireddata['seq']) {
			$query = "delete from {$this->tb_act_cal_sal} where order_seq=? ".$addsqll;
			$this->db->query($query,$bind);
		}
	}

	//테스트주문 정산/매출/출고별정산/미정산/임시매출 5개테이블 제거

	public function del_all_test($order_seq)
	{
		$bind[0] = $order_seq;
		
		//1. 임시매출
		$query = "delete from {$this->tb_act_tmp} where order_seq=? ".$addquery;
		//debug_var("del_tb_act_tmp_cal_sal=>".$query);
		$this->db->query($query,$bind);
		
		//2. 미정산
		$query = "delete from {$this->tb_act_cal_sal} where order_seq=? ".$addquery;
		//debug_var("del_tb_act_cal_sal=>".$query);
		$this->db->query($query,$bind);
		
		if(!$tb_act_ym) $tb_act_ym	=	date('Ym', $this->iOnTimeStamp);
		if($accountdata['acc_table'] && $accountdata['acc_table']>0) $tb_act_ym	=	$accountdata['acc_table'];
		
		//정산데이타
		$tableck = $this->get_table_ym_ck('calculate',$tb_act_ym);
		//debug_var("tableck->".$tableck);
		$query = "delete from {$tableck} where order_seq=? ".$addquery;
		//debug_var("del_acc_table_cal=>".$query);
		$this->db->query($query,$bind);
	}
	
	/*
	 * 월별정산테이블 데이타추가
	 * @param 
	*/
	public function insert_calculate_ym($accountdata, $tableck) {
		//debug_var("insert_calculate_ym");
		//debug_var($accountdata);
		if(empty($accountdata['order_seq']) || empty($accountdata['shipping_group_seq']) || empty($accountdata['order_form_seq'])) return;
		$data = filter_keys($accountdata, $this->db->list_fields($tableck));
		//debug_var($data);
		//return;//exit;
		$this->db->insert($tableck, $data);
		$result = $this->db->insert_id();//debug_var($result);
		return $result;
	}

	/*
	 * 월별정산테이블 데이타검증
	 * @param
	*/
	public function insert_calculate_ym_check($params, $tb_act_ym=null) {
		//if(empty($params['order_seq']) || empty($params['shipping_seq']) || empty($params['order_form_seq'])) return;
		$accountdata = ins_calculate_ck($params,'cal');//순서변경불가(변수정의)
		if(empty($accountdata['order_seq']) || empty($accountdata['shipping_group_seq']) || empty($accountdata['order_form_seq'])) return;

		if(!$tb_act_ym) $tb_act_ym	=	date('Ym', $this->iOnTimeStamp);
		if($accountdata['acc_table']) $tb_act_ym	=	$accountdata['acc_table'];
		$tableck = $this->get_table_ym_ck('calculate',$tb_act_ym);
		//debug_var("insert_calculate_ym_check tableck->".$tableck);//return;exit;
		if($tableck) {
			unset($wherequery,$bind);
			foreach($this->acc_requireds as $val){
				$wherequery[]	= $val."=?";
				$bind[]			= $accountdata[$val];
			}
			$query = "select seq from {$tableck} where ".implode(" AND ",$wherequery)." limit 1";
			$query = $this->db->query($query,$bind);
			$requireddata = $query->row_array();//debug_var($requireddata);//return;//exit;
			if(!$requireddata['seq']) {
				$result = $this->insert_calculate_ym($accountdata,$tableck);
			}
		}
		return $result;
	}

	/*
	 * 월별매출테이블 추가
	 * @param
	*/
	public function insert_sales_ym($accountdata, $tableck) {
		if(empty($accountdata['order_seq']) || empty($accountdata['shipping_group_seq']) || empty($accountdata['order_form_seq'])) return;
		$data = filter_keys($accountdata, $this->db->list_fields($tableck));
		//debug_var($data);
		//return;//exit;
		$this->db->insert($tableck, $data);//debug_var($this->db->last_query());
		$result = $this->db->insert_id();//debug_var($result);
		return $result;
	}

	/*
	 * 임시매츨테이블 데이타검증
	 * @param
	*/
	public function insert_tmp_check($params) {
		$accountdata = ins_calculate_ck($params);//순서변경불가(변수정의)
		
		//임시매출데이타 필요데이타 추가
		$accountdata['commission_type'] = $params['commission_type'];
		$accountdata['commission_rate']	= $params['commission_rate'];
		$accountdata['sale_ratio']		= $params['sale_ratio'];

		if(!$accountdata['refund_code']) $accountdata['refund_code'] = "";
		if(!$accountdata['return_code']) $accountdata['return_code'] = "";
		if(!$accountdata['refund_type']) $accountdata['refund_type'] = "";

		if(empty($accountdata['order_seq']) || empty($accountdata['shipping_group_seq']) || empty($accountdata['order_form_seq'])) return;
		foreach($this->account_tmp_requireds as $val){if($val == 'deposit_date') continue;
			$wherequery[] = $val."=?";
			$bind[] = $accountdata[$val];
		}

		$query = "select seq from {$this->tb_act_tmp} where ".implode(" AND ",$wherequery)." limit 1";
		$query = $this->db->query($query,$bind);
		if($query) $requireddata = $query->row_array();//debug_var($requireddata);//return;//exit;
		if(!$requireddata['seq']) {
			$result = $this->insert_tmp_query($accountdata,$this->tb_act_tmp);
		}
		return $result;
	}

	// 주문서 정보 가져오기
	public function get_accountall_order($order_seq, $wheres =array()){
		$sql = "select * from fm_order where order_seq=? ";

		$binds[]	= $order_seq;
		if($wheres) {
			foreach($wheres as $k=>$v){
				$sql .= " and {$k}=?";
				$binds[] = $v;
			}
		}
		$query = $this->db->query($sql,$binds);
		list($orders) = $query->result_array($query);
		return $orders;
	}

	/*
	 * 임시매출테이블 에누리 변경으로 인한 정산금액 갱신
	*/
	public function calculate_sales_update_tmp_check($params, $seq = null) {
		if(empty($params['order_seq']) || empty($params['order_form_seq'])) return;

		$order_field = array("commission_price","commission_price_rest","commission_text","sales_unit_feeprice","sales_unit_payprice","sales_feeprice_rest","total_payprice");
		foreach($order_field as $field){
			if($params[$field]<0) continue;
			$arr_set_query[]	= $field."=?";
			$bind[]				= $params[$field];
		}
		if( $seq ) {
			$bind[]    = $seq;
			$updateCol = implode(',',$arr_set_query);
			$query = "update {$this->tb_act_tmp} set {$updateCol} where seq=?";
		}else{
			$bind[]    = $data['order_seq'];
			$bind[]    = $data['shipping_group_seq'];
			$bind[]    = $data['account_type'];
			$bind[]    = $data['order_type'];
			$bind[]    = $data['order_form_seq'];
			$updateCol = implode(',',$arr_set_query);
			$query = "update {$this->tb_act_tmp} set {$updateCol} where order_seq=? and shipping_group_seq=? and account_type=? and order_type=? and order_form_seq=?";
		}
		
		$this->db->query($query,$bind);
		//debug_var("accountallmodel->calculate_sales_update_tmp_check() ".__LINE__);
		//debug_var($this->db->last_query());
	}
	//
	
	/**
	* 정산대상 수량업데이트
	* 임시매출테이블/미정산(전월/보류)/월별매출
	*@ tot_confirm_ea 구매확정건수
	*@ social_ticket_goods : 티켓상품 여부
	**/
	public function update_calculate_sales_ac_ea($order_seq, $export_refund_code, $account_type='order', $data_order_item=null, $data_order=null, $data_return=null)
	{
		// 정산대상 수량을 업데이트하는 로직에서 
		// 정산확정 가능 여부를 체크하는 로직으로 변경하므로 아래 로직은 전부 폐기처리
		// 정산예정처리수량이 없어질 경우 업데이트 처리함.
		// 기존 소스는 update_calculate_sales_ac_ea_not_use 에 존재함
		//  by hed
	}
	/**
	* 정산대상 수량업데이트
	* 임시매출테이블/미정산(전월/보류)/월별매출
	*@ tot_confirm_ea 구매확정건수
	*@ social_ticket_goods : 티켓상품 여부
	**/
	public function update_calculate_sales_ac_ea_not_use($order_seq, $export_refund_code, $account_type='order', $data_order_item=null, $data_order=null, $data_return=null)
	{
		if($data_order){
			$tb_act_ym	= str_replace("-","",substr($data_order['deposit_date'],0,7));
			$calculatetableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
		}else{
			$calculatetableck = $this->tb_act_cal_sal;
		}

		$data_tmp_order			= $this->get_act_sales_cal_total($calculatetableck, $order_seq);

		if($data_tmp_order<=0) return;
		$data_item		= array();
		if($account_type == 'refund' ){
			if(!$data_order_item) {
				$data_refund_item 	= $this->refundmodel->get_refund_item($export_refund_code);
			}else{
				$data_refund_item 	= $data_order_item;
			}
			foreach($data_refund_item as $k => $item)
			{
				$tmp = array();
				$tmp['goods_seq']			= $item['goods_seq']; 
				$tmp['item_seq']			= $item['item_seq'];
				$tmp['option_seq']			= $item['option_seq'];
				$tmp['shipping_seq']		= $item['shipping_seq'];
				$data_item[]				= $tmp;
			}
		}else{
			$data_export_item		= $this->get_accountall_export_item($export_refund_code);
			foreach($data_export_item as $k => $item)
			{
				$tmp = array();
				$tmp['goods_seq']			= $item['goods_seq']; 
				$tmp['item_seq']			= $item['item_seq'];
				$tmp['option_seq']			= $item['option_seq'];
				$tmp['shipping_seq']		= $item['shipping_seq'];
				$data_item[]				= $tmp;
			}
		}
		////debug_var("accountallmodel -> update_calculate_sales_ac_ea() ".__LINE__);

		foreach($data_item as $k => $item)
		{
			$goods_seq				= $item['goods_seq']; 
			$item_seq				= $item['item_seq'];
			$option_seq				= $item['option_seq'];
			$shipping_seq			= $item['shipping_seq'];

			$que = "
				select sum(ifnull(ea,0)) as ea, sum(ifnull(refund_ea,0)) as refund_ea, sum(ifnull(deliv_ea,0)) as deliv_ea, sum(ifnull(cancel_ea,0)) as cancel_ea  from 
					(
						(
						select 
							opt.ea,
							opt.refund_ea as refund_ea,
							(opt.step75) as deliv_ea,
							(opt.step85) as cancel_ea
						from 
							fm_order_item as item, fm_order_item_option as opt 
						where 
							item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
							and opt.item_seq=?
							and opt.item_option_seq=?
							and item.shipping_seq=?
						)
						union all
						(
						select 
							opt.ea,
							opt.refund_ea as refund_ea,
							(opt.step75) as deliv_ea,
							(opt.step85) as cancel_ea
						from 
							fm_order_item as item, fm_order_item_suboption as opt 
						where 
							item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
							and opt.item_seq=?
							and opt.item_suboption_seq=?
							and item.shipping_seq=?
						)
					) as k
			";//opt.step45+opt.step55+opt.step65+
			$query	= $this->db->query($que, array($item_seq,$option_seq,$shipping_seq,$item_seq,$option_seq,$shipping_seq));
			if($query){
				$rest_ea_data	= $query->row_array();
				## 배송그룹-동일한상품 내 남은 상품 수량 - 출고수량 - 취소수량
				$rest_ea = $rest_ea_data['ea'] - $rest_ea_data['deliv_ea'] - $rest_ea_data['cancel_ea'];
				//debug_var("rest_ea:".$rest_ea." = ea: ".$rest_ea_data['ea']." - deliv_ea: ".$rest_ea_data['deliv_ea']." - refund_ea: ".$rest_ea_data['refund_ea']);
				$exp_ea = $rest_ea_data['ea'] - $rest_ea_data['refund_ea'];//정산수량=결제취소 차감
				//debug_var("exp_ea:".$exp_ea." = ea: ".$rest_ea_data['ea']." - cancel_ea: ".$rest_ea_data['cancel_ea']);
			}

			$addWhere	= " account_type in ('order', 'exchange') and order_form_seq = '{$option_seq}' ";
			$addWhere	.= " and item_seq = '{$item_seq}' ";
			$addWhere	.= " and shipping_group_seq = '{$shipping_seq}' ";
			
			//미정산테이블
			if( $exp_ea >=0 || $rest_ea>=0 ) {
				$tb_act_cal_sal_sql = "update {$this->tb_act_cal_sal} set exp_ea='{$exp_ea}', ac_ea='{$rest_ea}' where {$addWhere} and ac_ea > 0 and status != 'complete'";
				$this->db->query($tb_act_cal_sal_sql);
				if($calculatetableck != $this->tb_act_cal_sal){
					$tb_act_cal_sql = "update {$calculatetableck} set exp_ea='{$exp_ea}', ac_ea='{$rest_ea}' where {$addWhere} and ac_ea > 0 and status != 'complete'";
					$this->db->query($tb_act_cal_sql);
				}
			}



			$que = "
				select sum(ifnull(ea,0)) as ea, sum(ifnull(refund_ea,0)) as refund_ea, sum(ifnull(deliv_ea,0)) as deliv_ea, sum(ifnull(cancel_ea,0)) as cancel_ea from 
					(
						(
						select 
							opt.ea,
							opt.refund_ea as refund_ea,
							(opt.step75) as deliv_ea,
							(opt.step85) as cancel_ea
						from 
							fm_order_item as item, fm_order_item_option as opt 
						where 
							item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
							and item.shipping_seq=?
						)
						union all
						(
						select 
							opt.ea,
							opt.refund_ea as refund_ea,
							(opt.step75) as deliv_ea,
							(opt.step85) as cancel_ea
						from 
							fm_order_item as item, fm_order_item_suboption as opt 
						where 
							item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
							and item.shipping_seq=?
						)
					) as k
			";//opt.step45+opt.step55+opt.step65+
			$query	= $this->db->query($que, array($shipping_seq,$shipping_seq));
			if($query){
				$rest_ea_data	= $query->row_array();
				## 배송그룹내 남은 상품 수량 - 출고수량 - 취소수량
				$rest_delivery_ea = $rest_ea_data['ea'] - $rest_ea_data['deliv_ea'] - $rest_ea_data['cancel_ea'];
				//debug_var("rest_ea:".$rest_ea." = ea: ".$rest_ea_data['ea']." - deliv_ea: ".$rest_ea_data['deliv_ea']." - refund_ea: ".$rest_ea_data['refund_ea']);
				$exp_delivery_ea = $rest_ea_data['ea'] - $rest_ea_data['refund_ea'];//정산수량=결제취소 차감
				//debug_var("exp_ea:".$exp_ea." = ea: ".$rest_ea_data['ea']." - cancel_ea: ".$rest_ea_data['cancel_ea']);
			}
			
			$addShippingWhere	= " account_type in ('order', 'exchange') and order_form_seq = '{$shipping_seq}' ";
			$addShippingWhere	.= " and shipping_group_seq = '{$shipping_seq}' ";


			if( $exp_delivery_ea>=0 || $rest_delivery_ea>=0 ) {
				if($rest_delivery_ea <= 0 && $exp_delivery_ea > 0) {
					$tb_act_cal_sal_shipping_sql = "update {$this->tb_act_cal_sal} set exp_ea=ea, ac_ea='{$rest_delivery_ea}' where exp_ea > 0 and ac_ea > 0 and status != 'complete' and {$addShippingWhere}";
					$this->db->query($tb_act_cal_sal_shipping_sql);
				}else{
					if($data_return && $data_return['refund_ship_duty'] == "buyer"){
						$tb_act_cal_sal_shipping_sql = "update {$this->tb_act_cal_sal} set exp_ea=ea, ac_ea='{$rest_delivery_ea}' where exp_ea > 0 and ac_ea > 0 and status != 'complete' and {$addShippingWhere}";
						$this->db->query($tb_act_cal_sal_shipping_sql);
					}else{
						$tb_act_cal_sal_shipping_sql = "update {$this->tb_act_cal_sal} set exp_ea='{$exp_delivery_ea}', ac_ea='{$rest_delivery_ea}' where exp_ea > 0 and ac_ea > 0 and status != 'complete' and {$addShippingWhere}";
						$this->db->query($tb_act_cal_sal_shipping_sql);
					}
				}
				if($calculatetableck != $this->tb_act_cal_sal){
					if($rest_delivery_ea <= 0 && $exp_delivery_ea > 0) {
						$tb_act_cal_shipping_sql = "update {$calculatetableck} set exp_ea=ea, ac_ea='{$rest_delivery_ea}' where exp_ea > 0 and ac_ea > 0 and status != 'complete' and {$addShippingWhere}";
						$this->db->query($tb_act_cal_shipping_sql);
					}else{
						if($data_return && $data_return['refund_ship_duty'] == "buyer"){
							$tb_act_cal_shipping_sql = "update {$calculatetableck} set exp_ea=ea, ac_ea='{$rest_delivery_ea}' where exp_ea > 0 and ac_ea > 0 and status != 'complete' and {$addShippingWhere}";
							$this->db->query($tb_act_cal_shipping_sql);
						}else{
							$tb_act_cal_shipping_sql = "update {$calculatetableck} set exp_ea='{$exp_delivery_ea}', ac_ea='{$rest_delivery_ea}' where exp_ea > 0 and ac_ea > 0 and status != 'complete' and {$addShippingWhere}";
							$this->db->query($tb_act_cal_shipping_sql);
						}
					}
				}
			}
		}//endfor
	}
	

	/**
	* 정산대상 수량업데이트 (티켓상품)
	* 임시매출테이블/미정산(전월/보류)/월별매출
	*@ tot_confirm_ea 구매확정건수
	*@ social_ticket_goods : 티켓상품 여부
	**/
	public function update_calculate_sales_coupon_ac_ea($order_seq, $export_return_code, $account_type='order', $data_order_item=null, $data_order=null, $data_return=null, $data_return_item=null)
	{
		// 정산대상 수량을 업데이트하는 로직에서 
		// 정산확정 가능 여부를 체크하는 로직으로 변경하므로 아래 로직은 전부 폐기처리
		// 정산예정처리수량이 없어질 경우 업데이트 처리함.
		// 기존 소스는 update_calculate_sales_coupon_ac_ea_not_use 에 존재함
		//  by hed
	}
	public function update_calculate_sales_coupon_ac_ea_not_use($order_seq, $export_return_code, $account_type='order', $data_order_item=null, $data_order=null, $data_return=null, $data_return_item=null)
	{
		if($data_order){
			$tb_act_ym	= str_replace("-","",substr($data_order['deposit_date'],0,7));
			$calculatetableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
		}else{
			$calculatetableck = $this->tb_act_cal_sal;
		}
		$data_tmp_order			= $this->get_act_sales_cal_total($calculatetableck, $order_seq);
		if($data_tmp_order<=0) return;
		$data_item		= array();
		if($account_type == 'return' ){
			if(!$data_order_item) {
				$data_return_item 	= $this->returnmodel->get_return_item($export_return_code);
			}else{
				$data_return_item 	= $data_order_item;
			}
			foreach($data_return_item as $k => $item)
			{
				$tmp = array();
				$tmp['goods_seq']			= $item['goods_seq']; 
				$tmp['item_seq']			= $item['item_seq'];
				$tmp['option_seq']			= $item['option_seq'];
				$tmp['shipping_seq']		= $item['shipping_seq'];
				$tmp['export_code']			= $item['export_code'];
				$data_item[]				= $tmp;
			}
		}else{
			$data_export_item		= $this->get_accountall_export_item($export_return_code);
			foreach($data_export_item as $k => $item)
			{
				$tmp = array();
				$tmp = $item;
				$tmp['goods_seq']			= $item['goods_seq']; 
				$tmp['item_seq']			= $item['item_seq'];
				$tmp['option_seq']			= $item['option_seq'];
				$tmp['shipping_seq']		= $item['shipping_seq'];
				$tmp['export_code']			= $item['export_code'];
				$data_item[]				= $tmp;
			}
		}
		////debug_var("accountallmodel -> update_calculate_sales_ac_ea() ".__LINE__);
		//debug_var($data_item);

		foreach($data_item as $k => $item)
		{
			$exp_ea = 0;
			$cp_status = false;
			$goods_seq				= $item['goods_seq']; 
			$item_seq				= $item['item_seq'];
			$option_seq				= $item['option_seq'];
			$shipping_seq			= $item['shipping_seq'];
			$export_code			= $item['export_code'];
/*
			$que = "
				select sum(ifnull(ea,0)) as ea, sum(ifnull(refund_ea,0)) as refund_ea, sum(ifnull(deliv_ea,0)) as deliv_ea  from 
					(
						(
						select 
							opt.ea,
							opt.refund_ea as refund_ea,
							(opt.step75) as deliv_ea
						from 
							fm_order_item as item, fm_order_item_option as opt 
						where 
							item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
							and opt.item_seq=?
							and opt.item_option_seq=?
							and item.shipping_seq=?
						)
						union all
						(
						select 
							opt.ea,
							opt.refund_ea as refund_ea,
							(opt.step75) as deliv_ea
						from 
							fm_order_item as item, fm_order_item_suboption as opt 
						where 
							item.item_seq=opt.item_seq and item.order_seq=opt.order_seq
							and opt.item_seq=?
							and opt.item_suboption_seq=?
							and item.shipping_seq=?
						)
					) as k
			";//opt.step45+opt.step55+opt.step65+
			$query	= $this->db->query($que, array($item_seq,$option_seq,$shipping_seq,$item_seq,$option_seq,$shipping_seq));
			if($query){
				$rest_ea_data	= $query->row_array();
				## 배송그룹내 남은 상품 수량 - 출고수량 - 취소수량
				//$rest_ea = $rest_ea_data['ea'] - $rest_ea_data['deliv_ea'] - $rest_ea_data['refund_ea'];
				//debug_var("rest_ea:".$rest_ea." = ea: ".$rest_ea_data['ea']." - deliv_ea: ".$rest_ea_data['deliv_ea']." - refund_ea: ".$rest_ea_data['refund_ea']);
				$ac_ea = $rest_ea_data['ea'] - $rest_ea_data['refund_ea'];//정산수량=결제취소 차감
				//debug_var("exp_ea:".$exp_ea." = ea: ".$rest_ea_data['ea']." - cancel_ea: ".$rest_ea_data['cancel_ea']);
			}
*/
			// 티켓상품 사용 금액 확인을 위해서 쿼리 추가
			$exQue = "select fgei.export_code, fge.socialcp_status, fgei.coupon_value, fgei.coupon_remain_value
					from fm_goods_export_item fgei left join fm_goods_export fge on(fge.export_code=fgei.export_code)
					where fgei.export_code=? and fgei.item_seq=? and fgei.option_seq=?
			";//export_item_data
			$exQuery	= $this->db->query($exQue, array($export_code,$item_seq,$option_seq));
			if($exQuery){
				$exportData	= $exQuery->row_array();
				if(in_array($exportData['socialcp_status'], array(6,8))){
					$cp_status = true;
				}
				$usePrice = $exportData['coupon_value'] - $exportData['coupon_remain_value'];
				if($usePrice > 0){
					$exp_ea++;
				}
			}

			$addWhere	= " account_type = 'order' and order_form_seq = '{$option_seq}' ";
			$addWhere	.= " and item_seq = '{$item_seq}' ";
			$addWhere	.= " and shipping_group_seq = '{$shipping_seq}' ";
			
			$addShippingWhere	= " account_type = 'order' and order_form_seq = '{$shipping_seq}' ";
			$addShippingWhere	.= " and item_seq = '{$item_seq}' ";
			$addShippingWhere	.= " and shipping_group_seq = '{$shipping_seq}' ";
			
			//미정산테이블
			if( $exp_ea==0 && $cp_status ) {
				$tb_act_cal_sal_sql = "update {$this->tb_act_cal_sal} set exp_ea=exp_ea-1, ac_ea=ac_ea-1 where {$addWhere}";
				$this->db->query($tb_act_cal_sal_sql);
				if($calculatetableck != $this->tb_act_cal_sal){
					$tb_act_cal_sql = "update {$calculatetableck} set exp_ea=exp_ea-1, ac_ea=ac_ea-1 where {$addWhere}";
					$this->db->query($tb_act_cal_sql);
				}
			}else{
				$tb_act_cal_sal_sql = "update {$this->tb_act_cal_sal} set ac_ea=ac_ea-1 where {$addWhere}";
				$this->db->query($tb_act_cal_sal_sql);
				if($calculatetableck != $this->tb_act_cal_sal){
					$tb_act_cal_sql = "update {$calculatetableck} set ac_ea=ac_ea-1 where {$addWhere}";
					$this->db->query($tb_act_cal_sql);
				}
			}
		}//endfor
	}
	
	/**
	* 구매확정시 정산 생성!!
	* 미정산 매출테이블의 정산가능 건수 체크후 정산처리여부
	*@ tot_confirm_ea 구매확정건수
	*@ social_ticket_goods : 티켓상품 여부
	**/
	public function insert_calculate_sales_buyconfirm($order_seq, $export_code, $tot_confirm_ea=null, $social_ticket_goods=null) {
		
		try {
			// 본주문의 정산확정 체크
			$this->calculate_sales_buyconfirm($order_seq, $export_code);

			// 원주문의 정산확정 체크 진행
			$this->check_confirm_account_origin_order($order_seq);
		} catch (Exception $exc) {
			debug('accountmodel->insert_calculate_sales_buyconfirm'.chr(10).$exc->getTraceAsString());
		}
	}
	
	/**
	 * 특정 주문의 출고에 대해 정산확정이 가능한지 체크하여 정산확정 처리
	**/
	public function calculate_sales_buyconfirm($order_seq, $export_code, $tot_confirm_ea, $social_ticket_goods=null, $overwrite_item=array()) {
		
		try {
			$data_tmp_order			= $this->get_act_tmp_total($order_seq);
			if($data_tmp_order<=0) return;

			// 출고상세, 구매확정일 가져오기
			$data_export_item	= $this->get_accountall_export_item($export_code);
			if($overwrite_item){
				$data_export_item = $overwrite_item;
			}
			$confirm_date		= ($data_export_item[0]['confirm_date'])?$data_export_item[0]['confirm_date']:date('Y-m-d H:i:s', $this->iOnTimeStamp);
			// 주문데이터, 결제일 가져오기 이동 :: 2018-07-09 lkh
			$data_order			= $this->get_accountall_order($order_seq);
			$deposit_date_ck	= str_replace("-","",substr($data_order['deposit_date'],0,7));

			// 정산 마감일 가져오기 :: 2018-07-09 lkh
			$settingArr						= array();
			$settingArr['year'] = date("Y", $this->iOnTimeStamp);
			$settingArr['month'] = date("m", $this->iOnTimeStamp);
			$accountallConfirmSettingTmp = $this->get_account_setting("month",$settingArr);
			$accountConfirmDate = $accountallConfirmSettingTmp['accountall_confirm'];


			$acloop	= array();
			foreach($data_export_item as $k => $item)
			{
				// 정산 가능 여부 확인
				$confirm_account_params = $this->check_account_confirm($order_seq, $item, $deposit_date_ck, $accountConfirmDate, $confirm_date);
				// 정산 가능 시 정산 처리
				$confirm_done = $this->confirm_account($confirm_account_params);
				$arr_confirm_done[] = $confirm_done;
				if(!$confirm_done){ // 정산해당사항 아닌경우 그냥 구매확정일만 업데이트				
					// 정산 마감일이 각각 다른 문제로 추가 지난달 데이터만 :: 2018-07-09 lkh
					if( ($deposit_date_ck == date('Ym',strtotime("-1 month", $this->iOnTimeStamp))) && ($accountConfirmDate > date('d', $this->iOnTimeStamp)) ){
						$status = "complete";
						$tb_act_ym	=	$deposit_date_ck;
					}else{
						$status = ($deposit_date_ck==date("Ym", $this->iOnTimeStamp))?"complete":"carryover";//확정-당월/확정-전월
					}

					$calculatetableck = $this->get_table_ym_ck('calculate',$tb_act_ym);

					$wheres = array();
					$wheres[] = "order_seq='{$order_seq}'";
					$wheres[] = "shipping_group_seq='".$item['shipping_seq']."'";
					$wheres[] = "item_seq='".$item['item_seq']."'";
					$wheres[] = "order_form_seq='".$item['option_seq']."'";


					// 매출테이블 구매확정일 업데이트
					$sql = "update {$this->tb_act_cal_sal} set confirm_date='{$confirm_date}' where ".implode(" and ",$wheres)." and status in ('overdraw', 'carryover') ";
					$this->db->query($sql);			

					// 정산테이블 구매확정일 업데이트
					$sql = "update {$calculatetableck} set confirm_date='{$confirm_date}' where ".implode(" and ",$wheres)." and status = 'overdraw' ";
					$this->db->query($sql);
				}//endif
			}//foreach
		} catch (Exception $exc) {
			debug('accountmodel->calculate_sales_buyconfirm'.chr(10).$exc->getTraceAsString());
		}
		return $arr_confirm_done;
	}

	/**
	* 당월 구매확정시 통합매출데이타의 상태도 미정산-당월데이타 업데이트
	 * @param status		정산상태
	 * @param accountdata		주문정보
	*@
	**/
	public function update_calculate_sales_buyconfirm($calculatetableck,  $status, $confirm_date, $accountdata){
		//if($accountdata['target_table_seq']) $target_table_where .= " and seq='{$accountdata[target_table_seq]}' ";

		$wheres = array();
		$wheres2 = array();
		$target_table_up = "";
		
		// 정산 완료된 건은 수정하지 않음 by hed
		$wheres[] = $wheres2[] = " status != 'complete' ";
		
		if($accountdata['account_type']){
			$wheres[] = $wheres2[] = "account_type = '{$accountdata['account_type']}' ";
		}
		if($accountdata['order_type']){
			$wheres[] = "order_type = '{$accountdata['order_type']}' ";
		}
		if($accountdata['item_seq']){
			$wheres[] = $wheres2[] = "item_seq='{$accountdata[item_seq]}' ";
		}
		if($accountdata['order_goods_seq']){
			$wheres[] = $wheres2[] = "order_goods_seq='{$accountdata[order_goods_seq]}' ";
		}
		if($accountdata['order_form_seq']){
			$wheres[] = "order_form_seq='{$accountdata[order_form_seq]}' ";
		}
		//if($accountdata['shipping_provider_seq'])	$wheres[] = "shipping_provider_seq='{$accountdata[shipping_provider_seq]}' ";
		//if($accountdata['shipping_group_seq'])		$wheres = "shipping_group_seq='{$accountdata[shipping_group_seq]}' ";
		
		if($accountdata['ac_ea']>=0)			$target_table_up .= " , ac_ea='{$accountdata[ac_ea]}' ";
		if($accountdata['exp_ea']>=0)				$target_table_up .= " , exp_ea='{$accountdata[exp_ea]}' ";
		if($accountdata['up_date'])				$target_table_up .= " , up_date='{$accountdata[up_date]}' ";

		// 당월 정산테이블에 들어온 이월 데이터 확정 처리
		$carryover_complete = ($status == 'carryover' && $calculatetableck != $this->tb_act_cal_sal && $accountdata['ac_ea']==0);

		// 정산확정일자 업데이트 추가
		if(
			$accountdata['acc_date'] 
			&& (
				$status == 'complete'
				|| ($carryover_complete) // 이월 데이터 확정 처리
			)
		){
			$target_table_up .= " , acc_date='{$accountdata['acc_date']}' ";
		}

		
		// 반품배송비 정산확정 처리를 위해 추가 by hed
		if($accountdata['account_type'] == 'return'  && $accountdata['order_type'] == 'shipping'){
			$wheres[] = $wheres2[] = " refund_type = 'return' ";
		}elseif($accountdata['account_type'] == 'return'){
			$wheres[] = $wheres2[] = " refund_type = 'cancel_payment' ";
		}else{
			$wheres[] = $wheres2[] = " refund_type = '' ";
		}
		
		// 매출테이블을 변경하는 경우 정산테이블과 다르게 'overdraw', 'carryover'가 혼재되어 있으므로 범용 처리 by hed
		if($calculatetableck == $this->tb_act_cal_sal || $carryover_complete){
			$wheres[] = $wheres2[] = " status in ('overdraw', 'carryover') ";
		}else{
			$wheres[] = $wheres2[] = " status = 'overdraw' ";
		}

		if(!empty($accountdata['total_commission_price']) && $accountdata['total_commission_price'] != '0.00'){
			$target_table_up .= ",total_commission_price='{$accountdata['total_commission_price']}'";
		}
		if(!empty($accountdata['total_feeprice']) && $accountdata['total_feeprice'] != '0.00'){
			$target_table_up .= ",total_feeprice='{$accountdata['total_feeprice']}'";
		}

		if($wheres){
			$target_table_where  = " and ".implode(" and ",$wheres);
			$target_table_where2 = " and ".implode(" and ",$wheres2)."";
		}
		
		$sql = "update {$calculatetableck} set status='{$status}', confirm_date='{$confirm_date}' {$target_table_up} where order_seq='{$accountdata[order_seq]}' and account_target = 'calculate' {$target_table_where}";
		$this->db->query($sql);
		
		# 본사 위탁배송인 경우 배송비(매출)의 구매확정일자 노출 @2019-04-16 pjm
		if($accountdata['item_seq']){
			$sql = "update {$calculatetableck} set status='{$status}', confirm_date='{$confirm_date}' where order_seq='{$accountdata[order_seq]}' and account_target = 'sales' and order_type = 'shipping' {$target_table_where2}";
			$this->db->query($sql);
		}
	}


	/**
	* 환불시 남은 건에 대해서 구매확정이 이미 된경우 구매확정처리 업데이트
	 * @param status		정산상태
	 * @param accountdata		주문정보
	*@
	**/
	public function update_calculate_refund_sales_buyconfirm($order_seq, $refund_code, $data_order){
		$deposit_date_ck 	= $tb_act_ym	= str_replace("-","",substr($data_order['deposit_date'],0,7));
		$calculatetableck	= $this->get_table_ym_ck('calculate', $tb_act_ym);
		$data_tmp_order		= $this->get_act_sales_cal_total($calculatetableck, $order_seq);
		if($data_tmp_order<=0) return;

		// 정산 마감일 가져오기 :: 2018-07-09 lkh
		$settingArr['year'] = date("Y", $this->iOnTimeStamp);
		$settingArr['month'] = date("m", $this->iOnTimeStamp);
		$accountallConfirmSettingTmp = $this->get_account_setting("month",$settingArr);
		$accountConfirmDate = $accountallConfirmSettingTmp['accountall_confirm'];

		$data_refund		= $this->refundmodel->get_refund($refund_code);
		$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
		$confirm_date		= $data_refund['refund_date'];
		foreach($data_refund_item as $k => $item)
		{
			// 정산 가능 여부 확인
			$confirm_account_params = $this->check_account_confirm($order_seq, $item, $deposit_date_ck, $accountConfirmDate, $confirm_date);
			// 정산 가능 시 정산 처리
			$confirm_done = $this->confirm_account($confirm_account_params);
		}
				
		// 원주문의 정산확정 체크 진행
		$this->check_confirm_account_origin_order($order_seq, 'order_export_code_list');
	}


	/**
	* 되돌리기시 미정산-당월 매출데이타의 상태도 업데이트
	*@
	**/
	public function update_sales_status_rollback($tableck, $accountdata, $carryover=''){
		if($accountdata['target_table_seq']) $target_table_where .= " and  seq='{$accountdata[target_table_seq]}' ";
		
		if($accountdata['account_type'])			$target_table_where	.= " and account_type = '{$account_type}' ";
		if($accountdata['order_type'])				$target_table_where	.= " and order_type = '{$order_type}' ";
		if($accountdata['item_seq'])				$target_table_where .= " and item_seq='{$accountdata[item_seq]}' ";
		if($accountdata['order_form_seq'])			$target_table_where .= " and order_form_seq='{$accountdata[order_form_seq]}' ";
		if($accountdata['shipping_provider_seq'])	$target_table_where .= " and shipping_provider_seq='{$accountdata[shipping_provider_seq]}' ";
		if($accountdata['shipping_group_seq'])		$target_table_where .= " and shipping_group_seq='{$accountdata[shipping_group_seq]}' ";
		if($accountdata['status'])					$target_table_where .= " and status='{$accountdata['status']}' ";
		
		if($carryover=='carryover'){
			$update_status = " ,status='carryover' ";
		}else{
			$update_status = " ,status='complete' ";
		}
		
		$sql = "update {$tableck} set refund_type='{$accountdata[refund_type]}',refund_code='{$accountdata[refund_code]}',exp_ea=0,ac_ea=0 {$update_status} where status!='complete' and order_seq='{$accountdata[order_seq]}' and refund_code = '' and refund_type = '' {$target_table_where}";
		$this->db->query($sql);
		//debug_var($this->db->last_query());
	}


	/**
	* 2-1 주문접수 -> 입금확인시 처리
	* PG 결제정보/상태 갱신 후 미정산매출/통합정산 데이타생성
	*@
	**/
	public function insert_calculate_sales_order_deposit($order_seq) {
		$data_tmp_order			= $this->get_act_tmp_total($order_seq);
		if($data_tmp_order<=0) return;

		$data_order				= $this->get_accountall_order($order_seq);

		//임시매출 결제시 관련 정보 업데이트
		$arr_set_query[]	= "step=?";
		$arr_set_query[]	= "deposit_date=?";
		$arr_set_query[]	= "pg=?";
		$arr_set_query[]	= "payment=?";
		$arr_set_query[]	= "pg_ordernum=?";
		$arr_set_query[]	= "pg_ordernum_approval=?";
		$arr_set_query[]	= "order_referer=?";
		$bind[]				= $data_order['step'];
		$bind[]				= $data_order['deposit_date'];//date('Y-m-d H:i:s', $this->iOnTimeStamp);
		$bind[]				= ($data_order['pg'])?$data_order['pg']:'';
		$bind[]				= ($data_order['payment'])?$data_order['payment']:'';
		$bind[]				= ($data_order['pg_transaction_number'])?$data_order['pg_transaction_number']:'';
		$bind[]				= ($data_order['pg_approval_number'])?$data_order['pg_approval_number']:'';
		$bind[]				= account_order_referer($data_order['pg'], $data_order);
	
		$bind[]    = $data_order['order_seq'];
		$updateCol = implode(',',$arr_set_query);
		$query = "update {$this->tb_act_tmp} set {$updateCol} where order_seq=?";
		$result = $this->db->query($query,$bind);

		if( $result ) {
			if(($data_order['linkage_mall_code'] && $data_order['linkage_mall_order_id'])
				|| (
					$data_order['linkage_id']=='pos' 
					&& $data_order['linkage_mall_code'] 
					&& $data_order['linkage_order_id']
					)// O2O매장 매출 수집 추가
				){
				$deposit_date	= str_replace("-","",substr($data_order['deposit_date'],0,7));
				$tb_act_ym		= $deposit_date;
			}
			if(!$tb_act_ym) $tb_act_ym	=	date('Ym', $this->iOnTimeStamp);
			$caltableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
			//debug_var("copy_update_calculate_sales_order_check_step2 tableck->".$salestableck);//return;exit;
			if($caltableck) {
				$query = "select seq from {$caltableck}  where order_seq=? and refund_type  = '' limit 1";
				$query = $this->db->query($query,array($order_seq));//debug_var($this->db->last_query());
				$requireddata = $query->row_array();//debug_var($requireddata);//return;//exit;
				if(!$requireddata['seq']) {
					//통합정산데이타
					$status = "overdraw";//미정산-당월
					$this->insert_tmp_calculate_sales_order_copy($caltableck, $this->tb_act_tmp, $order_seq, $status);
				}

				$query = "select seq from {$this->tb_act_cal_sal}  where order_seq=? and refund_type  = '' limit 1";
				$query = $this->db->query($query,array($order_seq));//debug_var($this->db->last_query());
				$requireddata = $query->row_array();//debug_var($requireddata);//return;//exit;
				if(!$requireddata['seq']) {
					//미정산매출데이터 생성
					$status = "carryover";
					$whereis = " and account_target ='calculate' ";//해당주문의 정산대상만 미정상데이타 생성
					$this->insert_tmp_calculate_sales_order_copy($this->tb_act_cal_sal, $this->tb_act_tmp, $order_seq, $status, $whereis);
				}
			}
		}
	}

	/*
	* 종합 미정산(전월/보류) 매출테이블 저장
	* 상태 : 입금확인시 처리
	* $copy_table 복사테이블
	* $target_table 원본테이블
	* @ 
	*/
	public function insert_tmp_calculate_sales_order_copy($copy_table, $target_table, $order_seq, $status, $whereis=null) {
		$not_filds = array("seq","sale_ratio");//예외필드(임시매출에만 존재

		$copy_fields = $this->db->list_fields($copy_table);
		$target_fields = $this->db->list_fields($target_table);
		
		unset($target_record,$copy_record);
		foreach($copy_fields as $k=>$copy_field) {
			if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크
			foreach($target_fields as $target_field) {
			if( in_array($target_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				if ($target_field == $copy_field ) {
					$copy_record[$k]		= $copy_field;
					$target_record[$k]	= $copy_field;
				}else{
					if( $copy_field == "regist_date" || $copy_field == "up_date" ) {
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= date('Y-m-d H:i:s', $this->iOnTimeStamp);
					}elseif($copy_field == "status"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $status;
					}
				}
			}
		}

		if( count($copy_record) == count($target_record) ) {
			$sql = "INSERT INTO `{$copy_table}` ";//INSERT  REPLACE
			$copy_bind_sql = implode(", ",$copy_record);
			$sql .= " ({$copy_bind_sql}) ";
			$sql .= " select ";
			foreach($copy_record as $k=>$copy_field) {
				if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				if( $copy_field == "regist_date" || $copy_field == "up_date"  || $copy_field == "status" ) {
					$target_bind_sql .= ($target_bind_sql)?", '".$target_record[$k]."'":" '".$target_record[$k]."'";
				}else{
					$target_bind_sql .= ($target_bind_sql)?", ".$target_record[$k]:$target_record[$k];
				}
			}
			$sql .= " {$target_bind_sql} ";
			$sql .= " from {$target_table} where order_seq='{$order_seq}' {$whereis} order by seq asc";
			$this->db->query($sql);
		}
	}


	/**
	* 2-2 결제확인 후 되돌리기
    * 정산개선 - 통합정산데이타 마이너스매출 생성
	* 결제취소와 동일한 효과(월단위 달라지는 경우로 인하여)
	* @ 
	**/
	public function insert_calculate_sales_order_rollback($order_seq, $data_order_old) {
		$data_tmp_order			= $this->get_act_tmp_total($order_seq);
		if($data_tmp_order<=0) return;

		//결제확인 초기화
		$arr_set_query[]	= "step=?";
		$arr_set_query[]	= "deposit_date=?";
		$bind[]				= '15';
		$bind[]				= '';
		$bind[]				= $order_seq;
		$updateCol = implode(',',$arr_set_query);
		$query = "update {$this->tb_act_tmp} set {$updateCol} where order_seq=?";
		$result = $this->db->query($query,$bind);

		if( $result ) {
			if(!$tb_act_ym) $tb_act_ym	=	date('Ym', $this->iOnTimeStamp);			
			$caltableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
			
			if($caltableck) {
				
				// 되돌리기 대상의 원주문 달 체크
				$deposit_date_ck	= str_replace("-","",substr($data_order_old['deposit_date'],0,7));
				$calculatetableck = $this->get_table_ym_ck('calculate', $deposit_date_ck);
				
				if($deposit_date_ck == date('Ym', $this->iOnTimeStamp) ) {//당월에 되돌리기시
					$status = "overdraw";//롤백은 정산대상아님(매출대상)@2018-03-14
					$rollback_cal_table = $caltableck;
				}else{
					$rollback_cal_table = $this->tb_act_cal_sal;		
					$status = 'carryover';
				}
				
				$accountall_seq_query = $this->db->query("select max(seq) as maxseq from {$rollback_cal_table} order by seq desc");
				$accountall_seq		= $accountall_seq_query->row_array();
				$refund_code = 'R'.date('ymdHis', $this->iOnTimeStamp). rand(0,9999).($accountall_seq['maxseq']+1);//임의환불코드 생성
				$accountdata['refund_code'] = $refund_code;
				$accountdata['refund_type']	= 'cancel_payment';//결제취소단계(정산없음)
				$accountdata['order_seq']	= $order_seq;
				$accountdata['status'] = $status;
				
				// 기존 정산데이터 미정산데이터로 변경 by hed
				$this->update_sales_status_rollback($rollback_cal_table, $accountdata);
				
				// 이월된 데이터가 되돌리기 될 경우 이월데이터를 정산테이블로 완료 처리
				if($deposit_date_ck == date('Ym', $this->iOnTimeStamp) ) {//당월에 되돌리기시
					// 당월은 이미 정산데이터가 있으므로 정산데이터 생성 생략
					// 대신 매출테이블에 들어있는 이월예정 데이터 완료 처리
					$tmp = $accountdata['status'];
					$accountdata['status'] = 'carryover';					
					$this->update_sales_status_rollback($this->tb_act_cal_sal, $accountdata);
					$accountdata['status'] = $tmp;
				}else{
					// 이월데이터를 되돌리기 했을 경우 매출테이블에서 정산데이터 생성
					$this->create_calculate_sales_account($caltableck, $rollback_cal_table, $order_seq, $status, $accountdata);
					
					$tmp = $accountdata['status'];
					$accountdata['status'] = 'carryover';					
					// 생성된 정산데이터 완료처리(매출) by hed
					$this->update_sales_status_rollback($caltableck, $accountdata, 'carryover');
					$accountdata['status'] = $tmp;
				}

				unset($accountdata['status']);
				$status = "overdraw";//롤백은 정산대상아님(매출대상)@2018-03-14

				/*
				* 이월된 주문의 되돌리기 copy 테이블은 해당 월의 결제확인 데이터가 필요함
				* 정산 대상이 아닌 주문은 tb_act_cal_sal 에 없어서 copy 를 못하고 있었음
				*/ 
				if($deposit_date_ck != date('Ym', $this->iOnTimeStamp) ) {
					$rollback_cal_table = $calculatetableck;
					unset($accountdata);
				}
				// 마이너스 매출 데이터 생성
				$this->create_tmp_calculate_sales_order_rollback($caltableck, $rollback_cal_table, $order_seq, $status, $accountdata, 'rollback');//마이너스 환불
				// debug($calculatetableck);
				// debug($this->db->last_query());
			}
			
			// 매출테이블 정보 삭제 기능 제거 by hed
			// $this->del_calculate_sal_finish($order_seq,'rollback');//미정산매출 데이타 삭제
		}
	}

	/*
	* 종합정산(당월/전월)테이블
	* 상태 : 구매확정/전체환불/정산대상 조건 만족 시 생성
	* $copy_table 복사테이블
	* $target_table 원본테이블
	* @ 
	*/
	public function create_calculate_sales_account($copy_table, $target_table, $order_seq, $status, $accountdata, $account_type=null) {
		$not_filds = array("seq");//예외필드

		$copy_fields = $this->db->list_fields($copy_table);
		$target_fields = $this->db->list_fields($target_table);
		
		unset($target_record,$copy_record);
		foreach($copy_fields as $k=>$copy_field) {
			if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크
			foreach($target_fields as $target_field) {
			if( in_array($target_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				// 기본값 설정 by hed
				$copy_record[$k]		= $copy_field;
				$target_record[$k]		= $copy_field;
				
				// 각 복사 조건에 따른 추가 설정
				if($accountdata['account_type'] == 'refund' || $accountdata['account_type'] == 'after_refund') {
					if($copy_field == "account_type"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata[$copy_field];
					}elseif($copy_field == "refund_code"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= ($accountdata[$copy_field])?$accountdata[$copy_field]:'';
					}elseif($copy_field == "return_code"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= ($accountdata[$copy_field])?$accountdata[$copy_field]:'';
					}elseif($copy_field == "refund_type"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= ($accountdata[$copy_field])?$accountdata[$copy_field]:'';
					}elseif( $copy_field == "regist_date"  || $copy_field == "up_date") {
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= date('Y-m-d H:i:s', $this->iOnTimeStamp);
					}elseif($copy_field == "status"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $status;
					}elseif( $accountdata[$copy_field] || $accountdata[$copy_field]>=0 ) {
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata[$copy_field];
					}
				}elseif($accountdata['account_type'] == 'return') {
					if($copy_field == "refund_type"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata['refund_type'];
					}elseif($copy_field == "refund_code"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= ($accountdata[$copy_field])?$accountdata[$copy_field]:'';
					}elseif($copy_field == "return_code"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= ($accountdata[$copy_field])?$accountdata[$copy_field]:'';
					}elseif($copy_field == "account_type"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata[$copy_field];
					}elseif( $copy_field == "regist_date"  || $copy_field == "up_date" || $copy_field == "confirm_date" || $copy_field == "acc_date") {
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= date('Y-m-d H:i:s', $this->iOnTimeStamp);
					}elseif($copy_field == "deposit_date" && $accountdata['deposit_date_change'] == '1'){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata['deposit_date'];
					}elseif($copy_field == "status"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $status;
					}elseif( isset($accountdata[$copy_field]) ) {
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata[$copy_field];
					}
					if(
						$accountdata['order_type'] == 'shipping'							// 반품배송비의 경우
						&& in_array($copy_field, array("api_pg_price", "total_payprice"))	// 복사 값이 PG결재금액과 결재금액인 경우
					){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata[$copy_field];
					}
				}elseif($accountdata['account_type'] == 'deductible') {
					if($copy_field == "refund_type"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata['refund_type'];
					}elseif($copy_field == "refund_code"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= ($accountdata[$copy_field])?$accountdata[$copy_field]:'';
					}elseif($copy_field == "return_code"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= ($accountdata[$copy_field])?$accountdata[$copy_field]:'';
					}elseif($copy_field == "account_type"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata[$copy_field];
					}elseif( $copy_field == "regist_date"  || $copy_field == "up_date" || $copy_field == "confirm_date" || $copy_field == "acc_date") {
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= date('Y-m-d H:i:s');
					}elseif($copy_field == "status"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $status;
					}elseif($copy_field == "confirm_date") {
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= date('Y-m-d H:i:s');
					}elseif( isset($accountdata[$copy_field]) ) {
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata[$copy_field];
					}
				}else{
					if( $copy_field == "regist_date"  || $copy_field == "up_date" || $copy_field == "confirm_date" || $copy_field == "acc_date" ) 
					{
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= date('Y-m-d H:i:s', $this->iOnTimeStamp);
					}elseif($copy_field == "status"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $status;
					}
				}
			}
		}

		$_escape_str = array("goods_name","option1","option2","option3","option4","option5","title1","title2","title3","title4","title5");
		if( count($copy_record) == count($target_record) ) {
			$sql = "INSERT INTO `{$copy_table}` ";//INSERT  REPLACE
			$copy_bind_sql = implode(", ",$copy_record);
			$sql .= " ({$copy_bind_sql}) ";
			$sql .= " select ";
			foreach($copy_record as $k=>$copy_field) {

				$copy_field = trim($copy_field);
				if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				
				if( (in_array($accountdata['account_type'],array('refund', 'after_refund','deductible'))) && (
					( $accountdata[$copy_field] || $accountdata[$copy_field]>=0 ) || in_array($copy_field,array("account_type","refund_code","return_code","refund_type","regist_date","up_date","confirm_date","acc_date","status"))) 
				) {
					if(in_array($copy_field,$_escape_str)) {
						$target_bind_sql .= ($target_bind_sql)?", '".$this->db->escape_str($target_record[$k])."'":" '".$this->db->escape_str($target_record[$k])."'";
					}else{
						if(in_array($copy_field, array('coupon_remain_value', 'total_payprice')) && !empty($target_record[$k])){	// coupon_remain_value 인 경우 문자열 취급이 아닌 필드명 입력
							$target_bind_sql .= ($target_bind_sql)?", ".$target_record[$k]." ":" ".$target_record[$k]." ";
						}else{
							$target_bind_sql .= ($target_bind_sql)?", '".$target_record[$k]."'":" '".$target_record[$k]."'";
						}
					}
				}elseif( $accountdata['account_type'] == 'return' && (
					isset($accountdata[$copy_field]) || in_array($copy_field,array("account_type","refund_code","return_code","refund_type","regist_date","up_date","confirm_date","acc_date","status")) )
				) {
					$target_bind_sql .= ($target_bind_sql)?", '".$target_record[$k]."' ":"'".$target_record[$k]."'";
				}elseif(in_array($copy_field,array("regist_date","up_date","confirm_date","acc_date","status")) ) {
					$target_bind_sql .= ($target_bind_sql)?", '".$target_record[$k]."'":" '".$target_record[$k]."'";
				}else{
					$target_bind_sql .= ($target_bind_sql)?", ".$target_record[$k]:$target_record[$k];
				}

			}
			$sql .= " {$target_bind_sql} ";
			if($accountdata['target_table_seq']) $target_table_where .= " and  seq='{$accountdata[target_table_seq]}' ";
			// 주문 되돌리기 시 이월데이터일 경우 이전월에 여러번 되돌리기 됬을 수 있으므로 동일건만 복사.
			if($accountdata['refund_type'] == 'cancel_payment' && $accountdata['status'] == 'carryover' && $accountdata['return_code'] == '') $target_table_where .= " and  refund_code='{$accountdata[refund_code]}' ";
			$sql .= " from {$target_table}  where order_seq='{$order_seq}' {$target_table_where} order by seq asc";

			$this->db->query($sql);
		}
		return $sql;
	}

	/*
	 * 결제취소(환불완료) > 미정산매출테이블/통합정산테이블 처리
	 * @param
	 * @
	*/
	public function insert_calculate_sales_order_refund($order_seq, $refund_code, $cancel_type, $data_order, $data_refund=null, $data_refund_item=null)
	{
		$deposit_date	= str_replace("-","",substr($data_order['deposit_date'],0,7));
		$calculatetableck_origin = $this->get_table_ym_ck('calculate', $deposit_date);//입금일기준
		$status = "overdraw";//결제취소/반품환불 모두 미정산-당월처리@2018-03-14

		$data_sales_cal_order			= $this->get_act_sales_cal_total($calculatetableck_origin, $order_seq);
		if($data_sales_cal_order<=0 && $this->tool_mode == false) return;

		if(!$data_refund) $data_refund	= $this->get_accountall_refund($refund_code);
		
		if(!$cancel_type) $cancel_type = $data_refund['cancel_type'];
		
		$data_refund['cancel_type'] = $cancel_type;
		$data_refund['return_code'] = '';
		if( $data_refund['refund_type'] == 'return' ) {
			$query = "select return_code, refund_ship_duty from fm_order_return where refund_code=? limit 1";
			$query = $this->db->query($query,array($refund_code));
			list($data_return) = $query -> result_array();
			$data_refund['return_code'] = $data_return['return_code'];
			$data_refund['refund_ship_duty'] = $data_return['refund_ship_duty'];
		}
		if($data_refund){
			/**
			if($cancel_type == 'full'){//전체 환불시
				$get_account_all	= $this->get_account_calculate_sales($calculatetableck_origin, $order_seq, '', 'order');
				if($get_account_all) {
					foreach($get_account_all as $k => $orderdata) {
						if($orderdata['order_type'] == 'shipping') {
							$get_refund_item_data = $this->get_account_refund_item_data($refund_code,$orderdata['item_seq'],$refunddata['shipping_seq'],'shipping');
							$orderdata['order_form_seq'] = $data_refund['refund_seq'];
						}elseif($orderdata['order_type'] == 'suboption') {
							$get_refund_item_data = $this->get_account_refund_item_data($refund_code,$orderdata['item_seq'], $orderdata['order_form_seq'],'suboption_seq');
							$orderdata['order_form_seq'] = $get_refund_item_data['refund_item_seq'];
						}else{
							$get_refund_item_data = $this->get_account_refund_item_data($refund_code,$orderdata['item_seq'], $orderdata['order_form_seq'],'option_seq');
							$orderdata['order_form_seq'] = $get_refund_item_data['refund_item_seq'];
						}
						$orderdata['target_table_seq']	= $orderdata['seq'];//본래 매출정보가져오기
						$orderdata['account_type']		= "refund";
						if($orderdata) $loop[] = $orderdata;
					}
				}
			}else{
			}
			**/
				$account_confirm_ac_ea = 0;	// 0일 경우 모든 데이터가 정산확정.
				
				if(!$data_refund_item)	$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
				unset($provider_all,$comm_all,$feeprice_all,$refund_ea,$total_settleprice);
				foreach($data_refund_item as $refunddata){
					// 배송비 환불 문제로 별도 처리 :: 2018-08-06 lkh
					//if($refunddata['ea']<=0) continue;//환불수량이 있을때에만 적용
					$refund_params['item_seq']				= $refunddata['item_seq'];
					$refund_params['order_form_seq']		= $refunddata['option_seq'];
					$refund_params['shipping_group_seq']	= $refunddata['shipping_seq'];
					if($refunddata['opt_type'] == 'sub') {
						$get_account_goods	= $this->get_account_calculate_sales($calculatetableck_origin, $order_seq, $refund_params, 'order', 'suboption');
					}else{
						$get_account_goods	= $this->get_account_calculate_sales($calculatetableck_origin, $order_seq, $refund_params, 'order', 'option');
					}
					/* 해당 정산데이터가 정산확정되었는지 확인
					 * 정산확정되었다면 즉시 정산확정으로 처리하고
					 * 정산확정되지 않았다면 일반 반품과 동일하게 처리
					 */
					foreach($get_account_goods as $row_account_opt){
						$account_confirm_ac_ea += $row_account_opt['ac_ea'];
					}
					// 배송비 환불 문제로 별도 처리 :: 2018-08-06 lkh
					if($get_account_goods && $refunddata['ea']>0){
						foreach($get_account_goods as $optk => $optiondata) {
							$optiondata['ea'] = $refunddata['ea'];
							$optiondata['ac_ea'] = $refunddata['ea'];
							$optiondata['exp_ea'] = $refunddata['ea'];
							$optiondata['target_table_seq'] = $optiondata['seq'];
							$optiondata['refund_goods_price'] = $refunddata['refund_goods_price']+$refunddata['refund_cash_sale_unit'];
							$optiondata['emoney_sale_unit'] = $refunddata['refund_emoney_sale_unit'];
							$optiondata['emoney_sale_rest'] = $refunddata['refund_emoney_sale_rest'];
							$optiondata['cash_sale_unit'] = $refunddata['refund_cash_sale_unit'];
							$optiondata['cash_sale_rest'] = $refunddata['refund_cash_sale_rest'];
							$optiondata['enuri_sale_unit'] = 0;
							$optiondata['enuri_sale_rest'] = 0;
							$optiondata['coupon_remain_real_percent'] = $refunddata['coupon_remain_real_percent'];
							$optiondata['coupon_real_value'] = $refunddata['coupon_real_value'];
							$optiondata['coupon_remain_real_value'] = $refunddata['coupon_remain_real_value'];
							$refund_ea += $optiondata['ea'];
							$optiondata_exp_ea += $optiondata['exp_ea'];
							$optiondata_ac_ea += $optiondata['ac_ea'];
							/*
							 * 현재 환불할 상품이 제휴사 할인 금액이 있는지
							 * (fm_account_calculate_ym / api_pg_sale_price, api_pg_support_price)
							 * 환불 처리 할 주문에 완료된 기환불 건 존재하고  주문 정산 데이터 와 환불 완료 데이터가 동일하면 0원 처리
							 * $optiondata: 정산 주문 데이터 
							 * $bApiPgFlag 제휴사 할인 금액 업데이트 여부
							 * - true: 제휴 할인 금액 없음.
							 * - false: 제휴 할인 금액 있음.
							 */
							$bApiPgFlag = true;
							$aAccountallOrder = array('order_seq'=>$optiondata['order_seq'], 'item_seq'=>$optiondata['item_seq']);
							$aTmpRefundAcc = $this->refundmodel->refund_data_for_accountall($refund_code, $aAccountallOrder);
							// 환불 처리 할 주문에 완료된 기환불 건 존재하고  주문 정산 데이터 와 환불 완료 데이터가 동일하면 0원 처리
							if($aTmpRefundAcc === true){
								$bApiPgFlag = false;
							}

							$optiondata['api_pg_sale_price'] = $bApiPgFlag!=false?$refunddata['api_pg_sale_price']:0;
							$optiondata['api_pg_support_price'] = $bApiPgFlag!=false?$refunddata['api_pg_support_price']:0;

							$refunddataopt = account_ins_refund_ck($refunddata,$optiondata);
							$refunddataopt['option_seq'] = $refunddata['option_seq'];

							/**
							 * 정산 계산식 수동 처리
							**/
							$provider_all[]		= $refunddataopt['salescost_provider'];
							$feeprice_all[]		= ($refunddataopt['sales_unit_feeprice']*$refunddataopt['ea'])+$refunddataopt['sales_unit_minfee'];//짜투리용
							$comm_all[]			= $refunddataopt['commission_price']*$refunddataopt['ea'];
							$set_ratio_array[] = array(
								'seq'			=> $refunddataopt['seq'],
								'order_type'	=> $refunddataopt['order_type'],
								'order_seq'		=> $refunddataopt['order_seq'],
								'order_form_seq'=> $refunddataopt['order_form_seq'],
								'sale_ratio'	=> ($refunddataopt['price']*$refunddataopt['ea'])
							);

							$loop[] = $refunddataopt;
						}
					}
					//후정산방식으로 정산가능 대상이거나 모두환불완료 일때 정산처리
					$refundDeliveryPrice = ($refunddata['refund_delivery_price'] + $refunddata['refund_delivery_emoney'] + $refunddata['refund_delivery_cash']);

					if($refundDeliveryPrice || $cancel_type == 'full' ) {
						$refund_params['order_form_seq']		= $refunddata['shipping_seq'];

						unset($refund_params['item_seq']);	//동일그룹 상품 2개 이상 주문일 경우도 있기 때문에 배송비검색시 item_seq 제외.

						$get_account_shipping					= $this->get_account_calculate_sales($calculatetableck_origin, $order_seq, $refund_params, 'order', 'shipping');
						if($get_account_shipping) foreach($get_account_shipping as $key=>$shipping){

							// 배송비의 경우 쿠폰이나 프로모션, 예치금에 의해 0원 결제가 가능해지며
							// 해당 내용중 본사부담이 아닌 비율이 있을 수 있어
							// 배송비 0원 환불이라 하더라도 환불 정산을 진행한다. by hed
							// 단 배송비 귀책사유가 구매자부담일 경우는 환불처리되지 않았으므로 환불정산 제외.
							$refundDeliveryPriceAccount = false;
							$shipping_enuri_sale 	= $shipping['enuri_sale_unit'] + $shipping['enuri_sale_rest'];
							$shipping_coupon_sale 	= $shipping['coupon_sale_unit'] + $shipping['coupon_sale_rest'];
							$shipping_code_sale 	= $shipping['code_sale_unit'] + $shipping['code_sale_rest'];

							if(
								($shipping['price'] - $shipping_enuri_sale - $shipping_coupon_sale - $shipping_code_sale == '0')
								&& $refundDeliveryPrice == 0 
								&& $data_refund['refund_ship_duty'] != 'buyer'
							){
								$refundDeliveryPriceAccount = true;
							}
							if($refundDeliveryPrice > 0 || $refundDeliveryPriceAccount){

								$shipping['refund_delivery_price']	= $refunddata['refund_delivery_price'] + $refunddata['refund_delivery_cash'];
								$shipping['ea']						= $shipping['ea'];
								$shipping['ac_ea']					= $shipping['ea'];
								$shipping['exp_ea']					= $shipping['ea'];

								// 임시처리 시작
								$shipping['emoney_sale_unit']		= $refunddata['refund_delivery_emoney'];
								$shipping['emoney_sale_rest']		= 0;
								$shipping['cash_sale_unit']			= $refunddata['refund_delivery_cash'];
								$shipping['cash_sale_rest']			= 0;
								$shipping['enuri_sale_unit']		= $shipping['enuri_sale_unit'];
								$shipping['enuri_sale_rest']		= $shipping['enuri_sale_rest'];

								$refundshipping						= account_ins_refundshipping_ck($data_refund,$shipping);
								$refundshipping['target_table_seq']	= $refundshipping['seq'];
								$refundshipping['shipping_seq']		= $refunddata['shipping_seq'];

								/**
								정산계산식 수동처리
								**/
								$provider_all[]		= $refundshipping['salescost_provider'];
								$feeprice_all[]		= ($refundshipping['sales_unit_feeprice'])+$refundshipping['sales_unit_minfee'];//짜투리용
								$comm_all[]			= $refundshipping['commission_price'];

								$set_ratio_array[] = array(
									'seq'			=> $refundshipping['seq'],
									'order_type'	=> $refundshipping['order_type'],
									'order_seq'		=> $refundshipping['order_seq'],
									'order_form_seq'=> $refundshipping['order_form_seq'],
									'sale_ratio'	=> $refundshipping['price']
								);
								$loop[] = $refundshipping;
							}
						}

						$total_settleprice += $refunddata['refund_delivery_price']+$refunddata['refund_goods_price'];
					}else{
						$total_settleprice += $refunddata['refund_goods_price'];
					}
				}
				
				unset($get_account_opt,$get_account_shipping);
			
			@usort($set_ratio_array, 'account_order_sale_desc');//할인금액내림차순
			unset($commission_price_rest,$sales_feeprice_rest);

			/*
			* 결제수수료 > 정산금액 검증 : 수수료 짜투리 검수
			*/
			if($cancel_type != 'full' && $this->account_fee_ar['pg']) {
				$order_referer		= account_order_referer($data_order['pg'], $data_order);
				$charge	= $this->get_fee_info($order_referer, $data_order);
				$paycharge = $charge['data'];

				$chargeprice			= array_sum($feeprice_all);
				$allchargeprice			= acc_string_floor(($total_settleprice * $paycharge['commission_rate']/100), $data_order['pg']) + $paycharge['min_fee'];
				$total_commission		= ($total_settleprice - $allchargeprice ) ;// - array_sum($provider_all)
				$comm_all_sum			= array_sum($comm_all);
				$sales_feeprice_rest	= acc_string_floor($allchargeprice - $chargeprice,$data_order['pg']);
				$sum_settleprice		= ($comm_all_sum + $chargeprice);
				$commission_price_rest	= acc_string_floor($total_commission-$comm_all_sum,$data_order['pg']);
				/**
				**/
			}

			if( $deposit_date == date('Ym', $this->iOnTimeStamp) ) {
				$calculatetableck = $calculatetableck_origin;
			}else{
				$tb_act_ym	=	date('Ym', $this->iOnTimeStamp);
				$calculatetableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
			}
			
			foreach($loop as $accountdata) {
				if(!$accountdata['refund_code']) $accountdata['refund_code'] = $data_refund['refund_code'];
				if(!$accountdata['return_code']) $accountdata['return_code'] = $data_refund['return_code'];
				if(!$accountdata['refund_type']) $accountdata['refund_type'] = $data_refund['refund_type'];

				//짜투리 주문금액이 가장 큰 상품에 처리
				if($cancel_type != 'full'  && $this->account_fee_ar['pg'] && $set_ratio_array[0]['seq'] == $accountdata['order_form_seq'] && $set_ratio_array[0]['order_type'] == $accountdata['order_type'] && ($commission_price_rest || $sales_feeprice_rest) ) {
					$commission_text = " rest: ".$commission_price_rest." = (".$total_settleprice." - ".$allchargeprice." ) - ".$comm_all_sum." ";
					$accountdata['commission_price_rest']	= $commission_price_rest;
					$accountdata['sales_feeprice_rest']		= $sales_feeprice_rest;
					$accountdata['commission_text']			= $accountdata['commission_text']."||".$commission_text;
				}
				
				unset($wherequery,$bind);
				foreach($this->acc_requireds as $val){
					$wherequery[]	= $val."=?";
					$bind[]			= $accountdata[$val];
				}
				$query = "select seq from {$calculatetableck} where ".implode(" AND ",$wherequery)." limit 1";
				$query = $this->db->query($query,$bind);//debug_var($this->db->last_query());
				if($query) $requireddata = $query->row_array();

				// 이미 정산확정된 데이터에 환불데이터가 추가될 경우 정산확정된 상태로 정산데이터 입력
				if(
					(
						($account_confirm_ac_ea==0 && $accountdata['refund_type'] != 'cancel_payment')
						|| ($accountdata['refund_type'] != 'cancel_payment' && $data_refund['after_refund'] == '1')
					)
					&& ($this->tool_mode == false || $this->tool_mode == true && $data_refund['after_refund'] == '1')
				){
					$accountdata['account_type'] = 'after_refund';
					$accountdata['ac_ea'] = 0;
					$accountdata['status'] = $status = 'complete';
					$accountdata['acc_date'] = date('Y-m-d H:i:s', $this->iOnTimeStamp);
				}
					
				//총 정산금액, 총 수수료 업데이트 @20190529 pjm
				$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
				$tmp_accountdata = $accountdata;
				$tmp_accountdata['status'] = $status;
				accountalllist('list2',$tmp_accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

				list($total_feeprice,$total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);

				$accountdata['total_payprice'] = $total_payprice;
				
				// 정산확정 후 반품의 경우 수수료와 정산 금액을 재계산
				if($accountdata['account_type'] == 'after_refund'){
					$accountdata['total_feeprice'] = $total_feeprice;
					$accountdata['total_commission_price'] = $total_commission_price;
				}

				if(!$this->tool_mode){

					if(!$requireddata['seq']) {
						$accountdata['target_table_seq'] = $accountdata['seq'];
						$this->create_calculate_sales_account($calculatetableck, $calculatetableck_origin, $order_seq, $status, $accountdata);
					}
					// 환불정보는 이월하지 않음.
					
					// 기존 정산에서 반품배송비에 대한 매출데이터 생성이 누락되어 있어 추가 by hed
					// 매출데이터가 없을 경우 이월처리가 불가능하므로 필수적으로 데이터를 생성해준다. by hed
					//if($status != 'complete'){
					//	$sale_status = 'carryover';
					//}else{
					//	$sale_status = $status;
					//}
					// 매출테이블에도 환불처리 데이터 생성
					//$this->create_calculate_sales_account($this->tb_act_cal_sal, $calculatetableck_origin, $order_seq, $sale_status, $accountdata);
					
					// 환불 데이터는 매출데이터의 생성과 동시에 완료처리 -> 다음달로 이월되는 데이터는 정산데이터에 한함.
					// 기존 정산데이터 미정산데이터로 변경 by hed
					//$this->update_sales_status_rollback($this->tb_act_cal_sal, $accountdata);
					
				}else{
					$tmp_status = $accountdata['status'];
					$accountdata['status'] = $status;
					$return_loop[] = $accountdata;
					$accountdata['status'] = $tmp_status;
				}
				//전체환불시 정산대상이었다면 미정산매출 제거
				if(!$this->account_fee_ar['pg'] && $cancel_type == 'full' && $accountdata['account_target'] == 'calculate' ) {
					if($accountdata['order_goods_kind'] == 'shipping' && $accountdata['shipping_seq']){
						$accountdata['order_form_seq']	= $accountdata['shipping_seq'];
					}else{
						$accountdata['order_form_seq']	= $accountdata['option_seq'];
					}
					// 매출테이블 정보 삭제 기능 제거 by hed
					// $this->del_calculate_sal_finish($order_seq,'order', $accountdata);
				}else{ // exp_ea 갯수가 없을 경우 삭제되도록 처리 :: 2018-08-01 lkh
					if($accountdata['order_goods_kind'] == 'shipping' && $accountdata['shipping_seq']){
						$accountdata['order_form_seq']	= $accountdata['shipping_seq'];
					}else{
						$accountdata['order_form_seq']	= $accountdata['option_seq'];
					}
					// 매출테이블 정보 삭제 기능 제거 by hed
					// $this->del_calculate_div_sal_finish($order_seq,'order', $accountdata);
				}
			}//endfor

			//부분취소 여러건이 모여 실제 전체 취소일 경우 미정산매출제거추가필요
		}//endif	
		
		
		// 정산 남은 수량 확인 및 정산확정 처리
		$this->update_calculate_refund_sales_buyconfirm($order_seq, $refund_code, $data_order);

		return $return_loop;
	}
	
	/*
	 * 환불완료 > 조정금액 매출/정산 확정-당월 처리
	 * @param
	 * @
	*/
	public function insert_calculate_sales_order_deductible($order_seq, $refund_code, $cancel_type, $data_order, $data_refund=null, $data_refund_item=null)
	{
		$deposit_date	= str_replace("-","",substr($data_order['deposit_date'],0,7));
		$calculatetableck_origin = $this->get_table_ym_ck('calculate', $deposit_date);//입금일기준
		$status = "complete";//결제취소/반품환불 모두 미정산-당월처리@2018-03-14

		$data_sales_cal_order			= $this->get_act_sales_cal_total($calculatetableck_origin, $order_seq);
		if($data_sales_cal_order<=0) return;

		if(!$data_refund) $data_refund	= $this->get_accountall_refund($refund_code);
		
		$data_refund['cancel_type'] = $cancel_type;
		$data_refund['return_code'] = '';
		if( $data_refund['refund_type'] == 'return' ) {
			$query = "select return_code from fm_order_return where refund_code=? limit 1";
			$query = $this->db->query($query,array($refund_code));
			list($result) = $query -> result_array();
			$data_refund['return_code'] = $data_return['return_code'];
		}
		if($data_refund){
			if(!$data_refund_item)	$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
			unset($provider_all,$comm_all,$feeprice_all,$refund_ea,$total_settleprice);
			$refund_params['item_seq']			= $data_refund_item[0]['item_seq'];
			$refund_params['order_form_seq']	= $data_refund_item[0]['option_seq'];
			$refund_params['shipping_group_seq']= $data_refund_item[0]['shipping_seq'];
			$get_account_goods	= $this->get_account_calculate_sales($calculatetableck_origin, $order_seq, $refund_params, 'order', 'option');
			if(!$get_account_goods){
				$get_account_goods	= $this->get_account_calculate_sales($calculatetableck_origin, $order_seq, $refund_params, 'order','suboption');
			}
			if(!$get_account_goods) return;//매출데이타가 없으면 제외

			// 배송그룹이 여러개일 경우 첫번째 배송그룹에 배송비가 없을 수 있으므로 모든 배송그룹에 대해 조회
			// 단 조정금액은 첫번째 정산데이터를 기준으로 처리되므로 1개 이상 조회면 무방함
			$get_account_shipping_tmp = array();
			$get_account_shipping = array();
			foreach($data_refund_item as $data_refund_item_row){
				$refund_shipping_params = array();
				$refund_shipping_params['item_seq']				= $data_refund_item_row['item_seq'];
				$refund_shipping_params['order_form_seq']		= $data_refund_item_row['option_seq'];
				$refund_shipping_params['shipping_group_seq']	= $data_refund_item_row['shipping_seq'];
				$refund_shipping_params['order_form_seq']		= $data_refund_item_row['shipping_seq'];
				$get_account_shipping_tmp				= $this->get_account_calculate_sales($calculatetableck_origin, $order_seq, $refund_shipping_params, 'order', 'shipping');
				foreach($get_account_shipping_tmp as $get_account_shipping_tmp_row){
					if($get_account_shipping_tmp_row){
						$get_account_shipping[] = $get_account_shipping_tmp_row;
					}
				}
			}
			
			// 입점사 배송그룹일 경우 배송비 수수료, 반품배송비 수수료 추출
			if	($get_account_goods[0]['provider_seq'] > 1){
				$provider	= $this->providermodel->get_provider($get_account_goods[0]['provider_seq']);
			}else{
				$provider['commission_type'] 		= 0;
				$provider['commission_rate'] 		= 0;
				$provider['shipping_charge'] 		= 0;
				$provider['return_shipping_charge']	= 0;
				$provider['coupon_penalty_charge']	= 0;
			}

			if($data_refund['refund_deductible_price'] && $data_refund['refund_deductible_price']>0){
				$goodsBaseData = array();
				$refundDeductibleData = array();
				$goodsBaseData['target_table_seq']		= $get_account_goods[0]['seq'];
				$goodsBaseData['order_seq']				= $get_account_goods[0]['order_seq'];
				$goodsBaseData['item_seq']				= $get_account_goods[0]['item_seq'];
				$goodsBaseData['shipping_group_seq']	= $get_account_goods[0]['shipping_group_seq'];
				$goodsBaseData['order_goods_seq']		= $get_account_goods[0]['order_goods_seq'];
				$goodsBaseData['order_member_seq']		= $get_account_goods[0]['order_member_seq'];
				$goodsBaseData['order_user_name']		= $get_account_goods[0]['order_user_name'];
				$goodsBaseData['order_regist_date']		= $get_account_goods[0]['order_regist_date'];
				$goodsBaseData['deposit_date']			= $get_account_goods[0]['deposit_date'];
				$goodsBaseData['provider_seq']			= $get_account_goods[0]['provider_seq'];
				$goodsBaseData['order_type']			= $get_account_goods[0]['order_type'];
				$goodsBaseData['order_goods_kind']		= $get_account_goods[0]['order_goods_kind'];
				$goodsBaseData['refund_seq']			= $data_refund['refund_seq'];
				$goodsBaseData['return_code']			= $data_refund['return_code'];
				$goodsBaseData['refund_code']			= $data_refund['refund_code'];
				$goodsBaseData['deductible_price']		= $data_refund['refund_deductible_price'];
				$goodsBaseData['status']				= $status;//상태
				$goodsBaseData['account_target']		= 'calculate';//정산대상
				$goodsBaseData['order_goods_name']		= '조정금액(상품)';//이름
				$goodsBaseData['commission_type']		= $provider['commission_type']; // 수수료 방식
				$goodsBaseData['commission_rate']		= $provider['charge']; // 수수료율
				$refundDeductibleData = account_ins_deductible_ck($goodsBaseData);
				$loop[] = $refundDeductibleData;
			}
			if($data_refund['refund_delivery_deductible_price'] && $data_refund['refund_delivery_deductible_price']>0){
				// 조건부 배송비로 인해 배송비 관련 기준정산데이터가 없으나 조정배송비는 발생할 수 있음.
				// 이때 정산데이터를 발생시킬 기준 데이터는 상품을 기준으로 발생시킴
				if(empty($get_account_shipping)){
					$get_account_shipping = $get_account_goods;
					$get_account_shipping[0]['order_type'] = 'shipping';
					$get_account_shipping[0]['order_goods_kind'] = 'shipping';
				}
				$goodsBaseData = array();
				$refundDeductibleData = array();
				$goodsBaseData['target_table_seq']		= $get_account_shipping[0]['seq'];
				$goodsBaseData['order_seq']				= $get_account_goods[0]['order_seq'];
				$goodsBaseData['item_seq']				= $get_account_shipping[0]['item_seq'];
				$goodsBaseData['shipping_group_seq']	= $get_account_shipping[0]['shipping_group_seq'];
				$goodsBaseData['order_goods_seq']		= $get_account_shipping[0]['order_goods_seq'];
				$goodsBaseData['order_member_seq']		= $get_account_shipping[0]['order_member_seq'];
				$goodsBaseData['order_user_name']		= $get_account_shipping[0]['order_user_name'];
				$goodsBaseData['order_regist_date']		= $get_account_shipping[0]['order_regist_date'];
				$goodsBaseData['deposit_date']			= $get_account_shipping[0]['deposit_date'];
				$goodsBaseData['provider_seq']			= $get_account_shipping[0]['provider_seq'];
				$goodsBaseData['order_type']			= $get_account_shipping[0]['order_type'];
				$goodsBaseData['order_goods_kind']		= $get_account_shipping[0]['order_goods_kind'];
				$goodsBaseData['refund_seq']			= $data_refund['refund_seq'];
				$goodsBaseData['return_code']			= $data_refund['return_code'];
				$goodsBaseData['refund_code']			= $data_refund['refund_code'];
				$goodsBaseData['deductible_price']		= $data_refund['refund_delivery_deductible_price'];
				$goodsBaseData['status']				= $status;//상태
				$goodsBaseData['account_target']		= "calculate";//정산대상
				$goodsBaseData['order_goods_name']		= "조정금액(배송비)";//이름
				$goodsBaseData['commission_type']		= "SACO"; // 수수료 방식
				$goodsBaseData['commission_rate']		= $provider['shipping_charge']; // 수수료율
				$refundDeductibleData = account_ins_deductible_ck($goodsBaseData);
				$loop[] = $refundDeductibleData;
			}
			if($data_refund['refund_penalty_deductible_price'] && $data_refund['refund_penalty_deductible_price']>0){
				$goodsBaseData = array();
				$refundDeductibleData = array();
				$goodsBaseData['target_table_seq']		= $get_account_goods[0]['seq'];
				$goodsBaseData['order_seq']				= $get_account_goods[0]['order_seq'];
				$goodsBaseData['item_seq']				= $get_account_goods[0]['item_seq'];
				$goodsBaseData['shipping_group_seq']	= $get_account_goods[0]['shipping_group_seq'];
				$goodsBaseData['order_goods_seq']		= $get_account_goods[0]['order_goods_seq'];
				$goodsBaseData['order_member_seq']		= $get_account_goods[0]['order_member_seq'];
				$goodsBaseData['order_user_name']		= $get_account_goods[0]['order_user_name'];
				$goodsBaseData['order_regist_date']		= $get_account_goods[0]['order_regist_date'];
				$goodsBaseData['deposit_date']			= $get_account_goods[0]['deposit_date'];
				$goodsBaseData['provider_seq']			= $get_account_goods[0]['provider_seq'];
				$goodsBaseData['order_type']			= 'penalty';
				$goodsBaseData['order_goods_kind']		= 'penalty';
				$goodsBaseData['refund_seq']			= $data_refund['refund_seq'];
				$goodsBaseData['return_code']			= $data_refund['return_code'];
				$goodsBaseData['refund_code']			= $data_refund['refund_code'];
				$goodsBaseData['deductible_price']		= $data_refund['refund_penalty_deductible_price'];
				$goodsBaseData['status']				= $status;//상태
				$goodsBaseData['account_target']		= "calculate";//정산대상
				$goodsBaseData['order_goods_name']		= "조정금액(환불위약금)";//이름
				$goodsBaseData['commission_type']		= "SACO"; // 수수료 방식
				$goodsBaseData['commission_rate']		= $provider['coupon_penalty_charge']; // 수수료율
				$refundDeductibleData = account_ins_deductible_ck($goodsBaseData);
				$loop[] = $refundDeductibleData;
			}
			unset($get_account_goods,$get_account_shipping);
			
			if( $deposit_date == date('Ym') ) {
				$calculatetableck = $calculatetableck_origin;
			}else{
				$tb_act_ym	=	date('Ym');
				$calculatetableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
			}

			foreach($loop as $accountdata) {
				if(!$accountdata['refund_code']) $accountdata['refund_code'] = $data_refund['refund_code'];
				if(!$accountdata['return_code']) $accountdata['return_code'] = $data_refund['return_code'];
				if(!$accountdata['refund_type']) $accountdata['refund_type'] = $data_refund['refund_type'];
				
				unset($wherequery,$bind);
				foreach($this->acc_requireds as $val){
					$wherequery[]	= $val."=?";
					$bind[]			= $accountdata[$val];
				}
				$query = "select seq from {$calculatetableck} where ".implode(" AND ",$wherequery)." limit 1";
				$query = $this->db->query($query,$bind);

				if($query) $requireddata = $query->row_array();

				if(!$requireddata['seq']) {       
					
                    //총 정산금액, 총 수수료 업데이트 @20190529 pjm
                    $tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
                    $tmp_accountdata = $accountdata;
                    $tmp_accountdata['status'] = $status;
                    accountalllist('list2',$tmp_accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

                    list($total_feeprice,$total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);

                    $accountdata['total_payprice'] = $total_payprice;
                    $accountdata['total_feeprice'] = $total_feeprice;
                    $accountdata['total_commission_price'] = $total_commission_price;                    

					$this->create_calculate_sales_account($calculatetableck, $calculatetableck_origin, $order_seq, $status, $accountdata);
				}
			}//endforeach
		}//endif
	}

	/*
	 * 2-2 반품시 입점사에게 반품배송비 설정시 > 매출/정산 처리
	 * @param 반품완료시 매출/정산 확정-당월 처리
	 * @ 
	*/
	public function insert_calculate_sales_order_returnshipping($order_seq, $return_code)
	{
		$data_order = $this->get_accountall_order($order_seq);

		$deposit_date	= str_replace("-","",substr($data_order['deposit_date'],0,7));
		$calculatetableck_origin = $this->get_table_ym_ck('calculate', $deposit_date);
		
		$data_sales_cal_order			= $this->get_act_sales_cal_total($calculatetableck_origin, $order_seq);
		if($data_sales_cal_order<=0 && !$this->tool_mode) return;
		
		$data_return		= $this->returnmodel->get_return($return_code);
		if( $data_return['return_shipping_gubun'] != 'company' || $data_return['return_shipping_price'] <=0) return;
		$data_return_item 	= $this->returnmodel->check_return_item($return_code);
		$params['item_seq'] = $data_return_item[0]['item_seq'];
		$get_account_opt	= $this->get_account_calculate_sales($calculatetableck_origin, $order_seq, $params, 'order','option'); 
		if(!$get_account_opt){
			$get_account_opt	= $this->get_account_calculate_sales($calculatetableck_origin, $order_seq, $params, 'order','suboption');
		}
		if(!$get_account_opt) return;//매출데이타가 없으면 제외
		
		// 해당 정산데이터가 정산확정되었는지 확인
		// 정산확정되었다면 즉시 정산확정으로 처리하고 
		// 정산확정되지 않았다면 일반 반품과 동일하게 처리
		$account_confirm_ac_ea = 0;	// 0일 경우 모든 데이터가 정산확정.
		foreach($get_account_opt as $row_account_opt){
			$account_confirm_ac_ea += $row_account_opt['ac_ea'];
		}
		
		// 반품배송비 정산 주체
		$provider_seq = $get_account_opt[0]['provider_seq'];
		
		// 입점사 배송그룹일 경우 배송비 수수료, 반품배송비 수수료 추출
		if	($get_account_opt[0]['provider_seq'] > 1){
			if(!$this->providermodel) {
				$this->load->model("providermodel");
			}
			$provider	= $this->providermodel->get_provider_one($get_account_opt[0]['provider_seq']);
			$return_shipping_charge	= $provider['return_shipping_charge'];

			// 반품배송비가 발생하였을 시 본사 위탁배송일 경우 반품배송비의 주체를 본사로 변경
			if($data_return['shipping_provider_seq'] == '1'){
				$provider_seq = $data_return['shipping_provider_seq'];
			}
		}

		//입점사 반품배송비처리
		$data_return['target_table_seq']		= $get_account_opt[0]['seq'];
		$data_return['item_seq']				= $get_account_opt[0]['item_seq'];
		$data_return['shipping_group_seq']		= $get_account_opt[0]['shipping_group_seq'];
		$data_return['order_goods_seq']			= $get_account_opt[0]['order_goods_seq'];
		$data_return['order_member_seq']		= $get_account_opt[0]['order_member_seq'];
		$data_return['order_user_name']			= $get_account_opt[0]['order_user_name'];
		$data_return['order_regist_date']		= $get_account_opt[0]['order_regist_date'];
		$data_return['deposit_date']			= $get_account_opt[0]['deposit_date'];
		$data_return['provider_seq']			= $provider_seq;	// 반품배송비 정산 주체
		$data_return['return_shipping_charge']	= $return_shipping_charge; // 반품배송비 수수료
		$accountdata = account_ins_returnshipping_ck($data_return);
		//$accountdata = ins_calculate_ck($accountdata);

		// 공제대상 반품배송비일 경우 이름을 직접 입력
		if($data_return['refund_ship_duty'] == 'buyer' && $data_return['refund_ship_type'] == 'M'){
			$accountdata['order_goods_name'] = '조정금액(반품배송비)';
		}

		$accountdata['status']					= 'overdraw';//확정-당월
		$accountdata['account_target']			= 'calculate';//정산대상
		
		if( $deposit_date == date('Ym', $this->iOnTimeStamp) ) {
			$calculatetableck = $calculatetableck_origin;
		}else{
			$tb_act_ym	=	date('Ym', $this->iOnTimeStamp);
			$calculatetableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
		}
		unset($wherequery,$bind);
		foreach($this->acc_requireds as $val){
			if($val == 'deposit_date') continue;
			$wherequery[] = $val."=?";
			$bind[] = $accountdata[$val];
		}
		$query = "select seq from {$calculatetableck} where ".implode(" AND ",$wherequery)." limit 1";
		$query = $this->db->query($query,$bind);//debug_var($this->db->last_query());
		if($query) $requireddata = $query->row_array();
		
		if(!$accountdata['refund_code']) $accountdata['refund_code'] = "";
		if(!$accountdata['return_code']) $accountdata['return_code'] = "";
		if(!$accountdata['refund_type']) $accountdata['refund_type'] = "";
		
			
		$status = "overdraw";//반품배송비는 확정-당월 처리
		// 이미 정산확정된 데이터에 반품배송비가 추가될 경우 정산확정된 상태로 정산데이터 입력
		if($account_confirm_ac_ea==0){
			$accountdata['account_type'] = 'return';		// account_ins_returnshipping_ck 와 동일하게 반품에 의한 데이터 생성
			$accountdata['ac_ea'] = 0;
			$accountdata['status'] = $status = 'complete';
			$accountdata['acc_date'] = date('Y-m-d H:i:s', $this->iOnTimeStamp);
		}

		// 반품배송비의 경우 입금일이 주문일 기준이 아닌 반품배송비가 완료된 반품일을 기준.
		$accountdata['deposit_date'] = $data_return['return_date'];
		$accountdata['deposit_date_change'] = '1';	// 정산데이터 생성 시 입금일을 변경하기 위한 변수
		
		//총 정산금액, 총 수수료 업데이트 @20190529 pjm
		$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
		accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

		list($total_feeprice, $total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);

		$accountdata['total_feeprice'] = $total_feeprice;
		$accountdata['total_commission_price'] = $total_commission_price;
		
		if(!$this->tool_mode){
			if(!$requireddata['seq']) { 
				// 기존 정산에서 반품배송비에 대한 매출데이터 생성이 누락되어 있어 추가 by hed
				// 매출데이터가 없을 경우 이월처리가 불가능하므로 필수적으로 데이터를 생성해준다. by hed
				if($status != 'complete'){
					$sale_status = 'carryover';
				}else{
					$sale_status = $status;
				}
				
				$result = $this->create_calculate_sales_account($this->tb_act_cal_sal, $calculatetableck_origin, $order_seq, $sale_status, $accountdata, 'return');//통합정산데이타 생성(당월마이너스)

				$result = $this->create_calculate_sales_account($calculatetableck, $calculatetableck_origin, $order_seq, $status, $accountdata, 'return');//통합정산데이타 생성(당월마이너스)
			}
		}else{
			return array($accountdata);
		}
		
		return $result;
	}

	/*
	 * 2-2 반품시 입점사에게 반품배송비 설정시 > 매출/정산 처리
	 * @param 반품완료시 매출/정산 확정-당월 처리
	 * @ 
	*/
	public function update_calculate_sales_order_returnshipping($order_seq, $return_code, $complete_date)
	{
		// 환불완료 시 반품배송비 정산에서 제외하도록 변경 by hed
	}

	/*
	 * 2-2 반품시 입점사에게 반품배송비 설정시 > 매출/정산 처리
	 * @param 반품완료시 매출/정산 확정-당월 처리
	 * @ 
	*/
	public function update_calculate_sales_order_returnshipping_not_use($order_seq, $return_code, $complete_date)
	{
		// 환불완료 시 반품배송비 정산에서 제외하도록 변경 by hed
		$data_order = $this->get_accountall_order($order_seq);

		$deposit_date	= str_replace("-","",substr($data_order['deposit_date'],0,7));
		$calculatetableck_origin = $this->get_table_ym_ck('calculate', $deposit_date);
		
		$data_sales_cal_order			= $this->get_act_sales_cal_total($calculatetableck_origin, $order_seq);
		if($data_sales_cal_order<=0) return;
		
		$data_return		= $this->returnmodel->get_return($return_code);
		if( $data_return['return_shipping_gubun'] != 'company' || $data_return['return_shipping_price'] <=0) return;
		$data_return_item 	= $this->returnmodel->check_return_item($return_code);

		if( $deposit_date == date('Ym', $this->iOnTimeStamp) ) {
			$calculatetableck = $calculatetableck_origin;
		}else{
			$tb_act_ym	=	date('Ym', $this->iOnTimeStamp);
			$calculatetableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
		}

		$bind = array($order_seq,$return_code);
		$query = "select seq from {$calculatetableck} where order_seq = ? and return_code = ? limit 1";
		$query = $this->db->query($query,$bind);//debug_var($this->db->last_query());
		if($query) $requireddata = $query->row_array();
		if($requireddata['seq']) { 
			if(!$accountdata['refund_code']) $accountdata['refund_code'] = "";
			if(!$accountdata['return_code']) $accountdata['return_code'] = "";
			if(!$accountdata['refund_type']) $accountdata['refund_type'] = "";

			$status = 'complete';//당월
			$up_bind = array($status, $complete_date, $order_seq, $return_code);
			$up_query = "update	{$calculatetableck} set status = ?, confirm_date = ? where order_seq = ? and return_code = ? and status != 'complete' ";
			$this->db->query($up_query, $up_bind);
		}
	}

	/*
	* 종합 미정산(전월/보류) 매출테이블, 매출테이블 저장
	* $copy_table 복사테이블
	* $target_table 원본테이블
	* 상품고유번호
	* 상품고유번호 필드명 //'option_seq'suboption_seq
	*/
	public function create_tmp_calculate_sales_order_rollback($copy_table, $target_table, $order_seq, $status='complete', $accountdata, $account_type=null) 
	{
		$not_filds = array("seq");//예외필드		

		$copy_fields = $this->db->list_fields($copy_table);
		$target_fields = $this->db->list_fields($target_table);

		unset($target_record,$copy_record);
		foreach($copy_fields as $k=>$copy_field) {
			if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크

			foreach($target_fields as $target_field) {
			if( in_array($target_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				if ($target_field == $copy_field ) {
					$copy_record[$k]	= $copy_field;
					$target_record[$k]	= $copy_field;
				}else{
					if( $copy_field == "regist_date"  || $copy_field == "up_date" || $copy_field == "confirm_date" || $copy_field == "acc_date" ) 
					{
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= date('Y-m-d H:i:s', $this->iOnTimeStamp);
					}elseif($copy_field == "status"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $status;
					}elseif($copy_field == "refund_code"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata['refund_code'];
					}elseif($copy_field == "refund_type"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $accountdata['refund_type'];
					}elseif($copy_field == "account_type" && $account_type == "rollback" ){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= "rollback";
					}elseif($copy_field == "exp_ea" || $copy_field == "ac_ea" ){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= "ea";
					}
				}
			}
		}

		if( count($copy_record) == count($target_record) ) {
			$arr_target_table_where = array();
			
			$sql = "INSERT INTO `{$copy_table}` ";//INSERT  REPLACE
			$copy_bind_sql = implode(", ",$copy_record);
			$sql .= " ({$copy_bind_sql}) ";
			$sql .= " select ";
			foreach($copy_record as $k=>$copy_field) {
				if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				if( $copy_field == "regist_date" || $copy_field == "up_date"  || $copy_field == "confirm_date" || $copy_field == "acc_date" || $copy_field == "status" || $copy_field == "account_type"   || $copy_field == "refund_code"  || $copy_field == "refund_type" ) {
					$target_bind_sql .= ($target_bind_sql)?", '".$target_record[$k]."'":" '".$target_record[$k]."'";
				}else{
					$target_bind_sql .= ($target_bind_sql)?", ".$target_record[$k]:$target_record[$k];
				}
			}
			$sql .= " {$target_bind_sql} ";
			if($account_type == "rollback"){
				$arr_target_table_where[] = " refund_code = '".$accountdata['refund_code']."'";
				$arr_target_table_where[] = " refund_type = '".$accountdata['refund_type']."'";
			}
			if($arr_target_table_where){
				$target_table_where = ' AND '.implode(' AND ', $arr_target_table_where);
			}
			
			$sql .= " from {$target_table}  where order_seq='{$order_seq}' {$target_table_where} order by seq asc";
			$this->db->query($sql);
		}
	}

	/*
	 * 최초 주문접수시 > 임시매출 생성
	 * 금액/정산정보 처리
	 * @param $account_ins_opt_tmp/$account_ins_subopt_tmp/$account_ins_shipping_tmp 사용자단의 실주문시에 적용(할인분담금)
	 * @네이버페이/오픈마켓 등에서는 할인항목 처리불가
	 * @
	*/
	public function insert_calculate_sales_order_tmp($order_seq, $set_order_price_ratio, $account_ins_opt_tmp=null, $account_ins_subopt_tmp=null, $account_ins_shipping_tmp=null)
	{
		$data_tmp_order			= $this->get_act_tmp_total($order_seq);
		if($data_tmp_order>0 && $this->tool_mode == false) return;

		$total					= $set_order_price_ratio['total'];
		$set_ratio_array		= $set_order_price_ratio['set_ratio_array'];
		$data_order				= $this->get_accountall_order($order_seq);
		
		$account_ins_opt		= $this->ordermodel->get_item_option($order_seq);
		$account_ins_subopt		= $this->ordermodel->get_item_suboption($order_seq);
		$account_ins_shipping	= $this->ordermodel->get_order_shipping($order_seq);

		//주문 공통정보 정의
		$data_order['order_regist_date']	= $data_order['regist_date'];
		$data_order['order_member_seq']		= $data_order['member_seq'];
		$data_order['order_step']			= $data_order['step'];	
		$data_order['pg']					= (!$data_order['pg'] || $data_order['payment']== 'bank')?'bank':$data_order['pg'];
		if($data_order['npay_order_id'])	{
			$data_order['order_referer_npay'] = acc_npay_referer($data_order);
			$data_order['pg'] = $data_order['order_referer_npay'];
		}
		$data_order['order_referer']		= account_order_referer($data_order['pg'], $data_order);
		$data_order['pg_ordernum']			= $data_order['pg_transaction_number'];//PG고유번호
		$data_order['pg_ordernum_approval']	= $data_order['pg_approval_number'];//PG승인번호
		if($this->account_fee_ar['pg']){//강원마트전용 결제수수료
			$charge	= $this->get_fee_info($data_order['order_referer'], $data_order);
			$paycharge = $charge['data'];
			if( $data_order['npay_point']>0) {
				$npay_point_charge	= $this->get_fee_info('npay', array("payment"=>"point"));
				$this->npay_point_paycharge = $npay_point_charge['data'];
			}
		}

		unset($provider_all,$comm_all,$feeprice_all);
		if($account_ins_opt) foreach($account_ins_opt as $optk => $optdata){
			$optdata			= @array_merge($data_order,$optdata);
			if( $account_ins_opt_tmp && $account_ins_opt_tmp[$optdata['item_option_seq']] ) {
				$optdata			= @array_merge($account_ins_opt_tmp[$optdata['item_option_seq']],$optdata);
			}
			
			if($this->order_referer_om_ar[$data_order['linkage_mall_code']]){//오픈마켓인 경우
				//판매마켓 주문상품번호 정보 가져오기
				$orderRow['fm_order_seq']			= $order_seq;
				$orderRow['fm_item_seq']			= $optdata['item_seq'];
				$orderRow['fm_item_option_seq']		= $optdata['item_option_seq'];
				$marekt_order_info = $this->get_marekt_order_info($data_order['linkage_mall_code'], $orderRow);
				if($marekt_order_info){
					$optdata['market_order_seq']	= $marekt_order_info['market_order_seq'];
					$optdata['market_option_code']	= $marekt_order_info['market_option_code'];
					
					//"판매자추가할인"			=>"api_pg_support_price",
					//"11번가할인"				=>"api_pg_sale_price"
					if( $marekt_order_info['market_discount_amount'] ){//11번가 제휴사할인
						$optdata['api_pg_sale_price']	= $marekt_order_info['market_discount_amount'];
					}
					if( $marekt_order_info['seller_discount_amount'] ){//마켓할인금액
						$optdata['api_pg_support_price']	= $marekt_order_info['seller_discount_amount'];
					}
				}
			}

			$optdata			= account_ins_option_ck($data_order,$optdata,$paycharge);

			$optdata			= ins_calculate_ck($optdata);//변수 재정의

			$provider_all[]		= $optdata['salescost_provider'];
			$feeprice_all[]		= ($optdata['sales_unit_feeprice']*$optdata['ea'])+$optdata['sales_unit_minfee'];//짜투리용
			$comm_all[]			= $optdata['commission_price']*$optdata['ea'];

			if($account_ins_shipping) foreach($account_ins_shipping as $key=>$shipping){
				if($optdata['shipping_group_seq'] == $shipping['shipping_seq'] || $optdata['shipping_seq'] == $shipping['shipping_seq'] ) {
					$account_ins_shipping[$key]['order_goods_seq']			= $optdata['order_goods_seq'];
					$account_ins_shipping[$key]['goods_code']				= $optdata['goods_code'];
					$account_ins_shipping[$key]['item_seq']					= $optdata['item_seq'];
					$account_ins_shipping[$key]['goods_shipping_cost']		+= $optdata['goods_shipping_cost'];

					$account_ins_shipping[$key]['shipping_provider_seq']	= $shipping['provider_seq'];
					$optdata['shipping_provider_seq']						= $shipping['provider_seq'];
					break;
				}
			}
			$loop[] = $optdata;
		}//end foreach

		if($account_ins_subopt) foreach($account_ins_subopt as $suboptk => $subdata){
			$subdata			= @array_merge($data_order,$subdata);
			if( $account_ins_subopt_tmp && $account_ins_subopt_tmp[$subdata['item_suboption_seq']] ) {
				$subdata			= @array_merge($account_ins_subopt_tmp[$subdata['item_suboption_seq']],$subdata);
			}
			if($this->order_referer_om_ar[$data_order['linkage_mall_code']]){//오픈마켓인 경우
				//판매마켓 주문상품번호 정보 가져오기
				$orderRow['fm_order_seq']			= $order_seq;
				$orderRow['fm_item_seq']			= $subdata['item_seq'];
				$orderRow['fm_item_option_seq']		= $subdata['item_option_seq'];
				$orderRow['fm_item_suboption_seq']	= $subdata['item_suboption_seq'];
				$marekt_order_info = $this->get_marekt_order_info($data_order['linkage_mall_code'], $orderRow);
				if($marekt_order_info){
					$subdata['market_order_seq']	= $marekt_order_info['market_order_seq'];
					$subdata['market_option_code']	= $marekt_order_info['market_option_code'];
				}
			}
			$subdata			= account_ins_suboption_ck($data_order,$subdata,$paycharge);
			$subdata			= ins_calculate_ck($subdata);//변수 재정의
			$provider_all[]		= $subdata['salescost_provider'];
			$feeprice_all[]		= ($subdata['sales_unit_feeprice']*$subdata['ea'])+$subdata['sales_unit_minfee'];//짜투리용
			$comm_all[]			= $subdata['commission_price']*$subdata['ea'];
			
			if($account_ins_shipping) foreach($account_ins_shipping as $key=>$shipping){
				if($subdata['shipping_group_seq'] == $shipping['shipping_seq'] || $subdata['shipping_seq'] == $shipping['shipping_seq'] ) {
					$subdata['shipping_provider_seq'] = $shipping['provider_seq'];
					break;
				}
			}
			$loop[] = $subdata;

		}//end foreach

		if($account_ins_shipping) foreach($account_ins_shipping as $key=>$shipping){

			$shipping			= @array_merge($data_order,$shipping);
			if( $account_ins_shipping_tmp && $account_ins_shipping_tmp[$shipping['shipping_seq']] ) {
				$shipping			= @array_merge($account_ins_shipping_tmp[$shipping['shipping_seq']],$shipping);
			}
			$shipping['ea']		= 1;
			$shipping['ac_ea']		= 1;
			$shipping['exp_ea']		= 1;
			$shippingquery		= $this->db->query("select a.*, b.provider_name
											from fm_order_shipping a
											inner join fm_provider b on a.provider_seq = b.provider_seq
											where a.shipping_seq=?",$shipping['shipping_seq']);
			$upshipping		= $shippingquery->row_array();
			$shipping		= @array_merge($shipping,$upshipping);
			$shipping		= account_ins_shipping_ck($data_order,$shipping,$paycharge);
			$shipping		= ins_calculate_ck($shipping);//변수 재정의

			$provider_all[]		= $shipping['salescost_provider'];
			$feeprice_all[]		= ($shipping['sales_unit_feeprice']*$shipping['ea'])+$shipping['sales_unit_minfee'];//짜투리용
			$comm_all[]			= $shipping['commission_price'];
			$shipping['shipping_provider_seq'] = $shipping['provider_seq'];
			if($shipping['sales_price']) $loop[] = $shipping;
		}//end foreach

		unset($account_ins_opt,$account_ins_subopt,$account_ins_shipping);
		
		/*
		* 결제수수료 > 정산금액 검증 : 수수료 짜투리 검수
		* 상품/배송비마다 수수료와 전체 수수료 차액 1~2원차이 발생
		*/
		if($this->account_fee_ar['pg']) {
			//$emoney_cash_npay_total	= $data_order['emoney']+$data_order['cash']+$data_order['enuri']+$data_order['npay_point'];
			$total_settleprice		= $data_order['settleprice']-$data_order['npay_point'];
			$chargeprice			= array_sum($feeprice_all);
			$allchargeprice			= acc_string_floor(($total_settleprice * $paycharge['commission_rate']/100), $data_order['pg']) + $paycharge['min_fee'];
			$total_commission		= ($total_settleprice - $allchargeprice );// - array_sum($provider_all)
			$comm_all_sum			= array_sum($comm_all);
			$sales_feeprice_rest	= acc_string_floor($allchargeprice - $chargeprice,$data_order['pg']);
			$sum_settleprice		= ($comm_all_sum + $chargeprice);
			$commission_price_rest	= acc_string_floor($total_commission-$comm_all_sum,$data_order['pg']);
			
		}
		/**
		//debug_var($total_settleprice."=".$total_commission."+".$allchargeprice." :: ".$sum_settleprice."=".$comm_all_sum."+".$chargeprice);
		//debug_var($total_commission."=".$total_settleprice."-".$allchargeprice." :: ".$sales_feeprice_rest."=".$allchargeprice."-".$chargeprice." :: ".$commission_price_rest."=".$total_commission."-".$comm_all_sum);
		//debug_var("sales_feeprice_rest:".$sales_feeprice_rest."/commission_price_rest:".$commission_price_rest);
		**/

		$aResultLoop = array();
		foreach($loop as $accountdata) {
			if(!$accountdata['refund_code']) $accountdata['refund_code'] = "";
			if(!$accountdata['return_code']) $accountdata['return_code'] = "";
			if(!$accountdata['refund_type']) $accountdata['refund_type'] = "";

			//수수료 짜투리 주문금액이 가장 큰 상품에 처리
			if(($commission_price_rest || $sales_feeprice_rest)  && $set_ratio_array[0]['seq'] == $accountdata['order_form_seq'] && $set_ratio_array[0]['type'] == $accountdata['order_type'] ) {
				$commission_text = " rest: ".$commission_price_rest." = (".($data_order['settleprice'])." - ".$allchargeprice." ) - ".$comm_all_sum." ";//. " -" . array_sum($provider_all)
				$accountdata['commission_price_rest']	= $commission_price_rest;
				$accountdata['sales_feeprice_rest']		= $sales_feeprice_rest;
				$accountdata['commission_text']			= $accountdata['commission_text']."||".$commission_text;
			}
			

			//총 정산금액, 총 수수료 업데이트 @20190529 pjm
			$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
			accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

			list($total_feeprice, $total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);
			
			// 임시매출데이터를 생성할 때에는 최종 정산금액과 수수료는 업데이트할 필요 없음 by hed
			// $accountdata['total_feeprice'] = $total_feeprice;
			// $accountdata['total_commission_price'] = $total_commission_price;
			unset($accountdata['total_feeprice']);
			unset($accountdata['total_commission_price']);
			
			// 임시매출 데이터가 생성될 때 총 결재금액을 생성
			$accountdata['total_payprice'] = $total_payprice;		
			
			if(!$this->tool_mode){
				if (is_nan($accountdata['event_sale_unit'])) {
					$accountdata['event_sale_unit'] = 0;
				}
				$this->insert_tmp_check($accountdata);//임시매출 테이블처리
			}
			$aResultLoop[] = $accountdata;
		}
		return $aResultLoop;
	}

	/*
	 * 임시매출테이블의 정보가져오기
	 * @
	*/
	public function get_account_tmp($order_seq, $params=null, $account_type=null, $order_type=null)
	{
		$addWhere	=" where order_seq = '{$order_seq}' ";
		if	($account_type)						$addWhere	.= " and account_type = '{$account_type}' ";
		if	($order_type)						$addWhere	.= " and order_type = '{$order_type}' ";
		if	($params['goods_seq'])				$addWhere	.= " and order_goods_seq = '{$params['goods_seq']}' ";
		if	($params['item_seq'])				$addWhere	.= " and item_seq = '{$params['item_seq']}' ";
		if	($params['shipping_provider_seq'])	$addWhere	.= " and shipping_provider_seq = '{$params['shipping_provider_seq']}' ";
		if	($params['order_form_seq'])			$addWhere	.= " and order_form_seq = '{$params['order_form_seq']}' ";
		if	($params['shipping_group_seq'])		$addWhere	.= " and shipping_group_seq = '{$params['shipping_group_seq']}' ";
		if	($params['step'])					$addWhere	.= " and step = '{$params['step']}' ";
		$query		= "select * from {$this->tb_act_tmp}". $addWhere. " order by item_seq desc ";
		$query		= $this->db->query($query);
		foreach($query -> result_array() as $data) $result[] = $data;
		return $result;
	}

	/*
	 * 미정산/정산매출테이블의 정보가져오기
	 * @
	*/
	public function get_account_calculate_sales($tableck, $order_seq, $params=null, $account_type=null, $order_type=null)
	{
		$addWhere	=" where order_seq = '{$order_seq}' ";
		
		if	($order_type)						$addWhere	.= " and order_type = '{$order_type}' ";
		if	($params['account_target'])			$addWhere	.= " and account_target = '{$params['account_target']}' ";
		if	($params['goods_seq'])				$addWhere	.= " and order_goods_seq = '{$params['goods_seq']}' ";
		if	($params['item_seq'])				$addWhere	.= " and item_seq = '{$params['item_seq']}' ";
		if	($params['order_form_seq'])			$addWhere	.= " and order_form_seq = '{$params['order_form_seq']}' ";
		if	($params['shipping_provider_seq'])	$addWhere	.= " and shipping_provider_seq = '{$params['shipping_provider_seq']}' ";
		if	($params['shipping_group_seq'])		$addWhere	.= " and shipping_group_seq = '{$params['shipping_group_seq']}' ";
		if	($params['step'])					$addWhere	.= " and step = '{$params['step']}' ";
		
		// 주문유형, 맞교환주문타입 생성으로 기본 조회조건 수정 
		if ($account_type == 'order'){
			$addWhere	.= " and account_type in ('order', 'exchange') ";
		}elseif($account_type){
			$addWhere	.= " and account_type = '{$account_type}' ";
		}
		// 반품배송비일 경우
		if	($params['exist_return_shippingfee']){
			$addWhere	.= " and refund_type  = 'return' AND order_goods_kind = 'shipping' ";
		}elseif	($params['all_refund_type']){
			$addWhere	.= " ";
		}else{
			$addWhere	.= " and refund_type  = ''  ";//환불 또는 되돌리기 건제외
		}
		
		if	($params['not_order_type'])			$addWhere	.= " and order_type not in ('".implode($params['not_order_type'], "', '")."') ";
				
		$query		= "select * from {$tableck}". $addWhere;// . "  order by item_seq desc ";{$this->tb_act_cal_sal}
		$query		= $this->db->query($query);
		if($query) foreach($query -> result_array() as $data) $result[] = $data;
		return $result;
	}

	/*
	 * 1-2 에누리 추가로 인한 임시매출데이타 업데이트
	 * @param
	 * @
	*/
	public function enuri_update_calculate_sales_order_tmp($order_seq)
	{
		$data_tmp_order			= $this->get_act_tmp_total($order_seq);
		if($data_tmp_order<=0) return;
		$data_order				= $this->get_accountall_order($order_seq);
		$get_account_opt		= $this->get_account_tmp($order_seq, $params, 'order','option');
		$get_account_subopt		= $this->get_account_tmp($order_seq, $params, 'order','suboption');
		$get_account_shipping	= $this->get_account_tmp($order_seq, $params, 'order','shipping');

		$order_referer		= account_order_referer($data_order['pg'], $data_order);
		if($this->account_fee_ar['pg']){//결제수수료방식에서 수수료율 가져오기
			$charge	= $this->get_fee_info($order_referer, $data_order);
			$paycharge = $charge['data'];
		}
		unset($set_ratio_array, $provider_all, $comm_all,$feeprice_all);
		if($get_account_opt) foreach($get_account_opt as $optk => $optdata) {
			$set_ratio_array[] = array(
				'seq'			=> $optdata['seq'],
				'order_type'	=> $optdata['order_type'],
				'order_seq'		=> $optdata['order_seq'],
				'order_form_seq'=> $optdata['order_form_seq'],
				'sale_ratio'	=> ($optdata['price']*$optdata['ea'])
			);
			if($optdata['multi_sale'] && !$optdata['multi_sale_unit'] )		$optdata['multi_sale_unit']	= $optdata['multi_sale'];
			if($optdata['event_sale'] && !$optdata['event_sale_unit'] )		$optdata['event_sale_unit']	= $optdata['event_sale'];
			if($optdata['member_sale'] && !$optdata['member_sale_unit'] )	$optdata['member_sale_unit']	= $optdata['member_sale'];
/* 이미 계산된 값인데 재계산으로 데이터 누락이 발생하여 삭제 :: 2018-07-13 lkh
			acc_promotion_sales_unit('emoney',	$optdata['emoney_sale_unit'],	$optdata['emoney_sale_provider'],		$optdata);
			acc_promotion_sales_unit('cash',	$optdata['cash_sale_unit'],		$optdata['cash_sale_provider'],			$optdata);
			acc_promotion_sales_unit('enuri',	$optdata['enuri_sale_unit'],	$optdata['enuri_sale_provider'],		$optdata);
			acc_promotion_sales_unit('npay_point',$optdata['npay_point_sale_unit'],$optdata['npay_point_sale_provider'],$optdata);

			acc_promotion_sales_unit('multi',	$optdata['multi_sale_unit'],	$optdata['multi_sale_provider'],	$optdata);
			acc_promotion_sales_unit('event',	$optdata['event_sale_unit'],	$optdata['event_sale_provider'],	$optdata);
			acc_promotion_sales_unit('member',	$optdata['member_sale_unit'],	$optdata['member_sale_provider'],	$optdata);
			acc_promotion_sales_unit('coupon',	$optdata['coupon_sale_unit'],	$optdata['coupon_sale_provider'],	$optdata);
			acc_promotion_sales_unit('fblike',	$optdata['fblike_sale_unit'],	$optdata['fblike_sale_provider'],	$optdata);
			acc_promotion_sales_unit('mobile',	$optdata['mobile_sale_unit'],	$optdata['mobile_sale_provider'],	$optdata);
			acc_promotion_sales_unit('code',	$optdata['code_sale_unit'],		$optdata['code_sale_provider'],		$optdata);
			acc_promotion_sales_unit('referer',	$optdata['referer_sale_unit'],	$optdata['referer_sale_provider'],	$optdata);
*/
			/**
			** 할인부담금 시작
			$optdata['salescost_admin_sales'] = $optdata['salescost_event'] + $optdata['salescost_event_rest']
			   + $optdata['salescost_multi'] + $optdata['salescost_multi_rest']
			   + 
			$optdata['salescost_provider_sales'] =    $optdata['salescost_event_provider'] + $optdata['salescost_event_provider_rest']
			   + $optdata['salescost_multi_provider'] + $optdata['salescost_multi_provider_rest']
			   + 
			$salescost_sales =   $optdata['multi_sale'] + $optdata['multi_sale_rest']
														   + $optdata['event_sale'] + $optdata['event_sale_rest']
														   + 
			**/
			## 본사 부담금
			$optdata['salescost_admin_promotion']	= $optdata['salescost_emoney'] + $optdata['salescost_emoney_rest']
													   + $optdata['salescost_cash'] + $optdata['salescost_cash_rest']
													   + $optdata['salescost_enuri'] + $optdata['salescost_enuri_rest']
													   + $optdata['salescost_npay_point'] + $optdata['salescost_npay_point_rest'];
			$optdata['salescost_admin_sales']		= $optdata['salescost_event'] + $optdata['salescost_event_rest']
													   + $optdata['salescost_multi'] + $optdata['salescost_multi_rest']
													   + $optdata['salescost_member'] + $optdata['salescost_member_rest']
													   + $optdata['salescost_coupon'] + $optdata['salescost_coupon_rest']
													   + $optdata['salescost_fblike'] + $optdata['salescost_fblike_rest']
													   + $optdata['salescost_mobile'] + $optdata['salescost_mobile_rest']
													   + $optdata['salescost_code'] + $optdata['salescost_code_rest']
													   + $optdata['salescost_referer'] + $optdata['salescost_referer_rest'];
			$optdata['salescost_admin']				= $optdata['salescost_admin_promotion'] + $optdata['salescost_admin_sales'];

			//입점사 부담금
			$optdata['salescost_provider_promotion']= $optdata['salescost_emoney_provider'] + $optdata['salescost_emoney_provider_rest']
													   + $optdata['salescost_cash_provider'] + $optdata['salescost_cash_provider_rest']
													   + $optdata['salescost_enuri_provider'] + $optdata['salescost_enuri_provider_rest']
													   + $optdata['salescost_npay_point_provider'] + $optdata['salescost_npay_point_provider_rest'];
			$optdata['salescost_provider_sales']	= $optdata['salescost_event_provider'] + $optdata['salescost_event_provider_rest']
													   + $optdata['salescost_multi_provider'] + $optdata['salescost_multi_provider_rest']
													   + $optdata['salescost_member_provider'] + $optdata['salescost_member_provider_rest']
													   + $optdata['salescost_coupon_provider'] + $optdata['salescost_coupon_provider_rest']
													   + $optdata['salescost_fblike_provider'] + $optdata['salescost_fblike_provider_rest']
													   + $optdata['salescost_mobile_provider'] + $optdata['salescost_mobile_provider_rest']
													   + $optdata['salescost_code_provider'] + $optdata['salescost_code_provider_rest']
													   + $optdata['salescost_referer_provider'] + $optdata['salescost_referer_provider_rest'];
			$optdata['salescost_provider']			= $optdata['salescost_provider_promotion'] + $optdata['salescost_provider_sales'];

			$optdata['acc_promotion_total']			= ($optdata['salescost_admin_promotion']) + ($optdata['salescost_provider_promotion']); 
			$optdata['acc_sale_total']				=  $optdata['salescost_admin_sales'] + $optdata['salescost_provider_sales'];
			$optdata['salescost_total']				= $optdata['acc_promotion_total'] + $optdata['acc_sale_total'];

			/**
			정산계산식 수동처리
			에누리용으로 재계산 되도록 수정 :: 2018-07-13 lkh
			**/
			if( $this->account_fee_ar['goods'] ) {//상품별 수수료 적용시
				$acc_unit_payprice		= (int)$optdata['price'] - $optdata['salescost_total'];//실결제금액(단가)
				$opt_price_tmp			= (int)$optdata['price'] - $optdata['salescost_provider'];//정산대상금액

				$acc_charge_str = "";
				if($optdata['commission_type'] == 'SACO'){
					$opt_price = $opt_price_tmp;
					$acc_charge_str			= $optdata['commission_rate']."%";
					if($optdata['commission_rate']) {
						$commission_price_tmp	= $opt_price*(100-$optdata['commission_rate'])/100;
						$sales_unit_feeprice	= $opt_price*($optdata['commission_rate'])/100;//수수료(단가)
					}else{
						$commission_price_tmp	= $optdata['price'];
						$sales_unit_feeprice	= 0;
					}
					/*$sales_unit_feeprice		= (int)$optdata['sales_unit_feeprice'] - $out_enuri_use;
					$acc_unit_payprice			= $sales_unit_feeprice*(100-$optdata['commission_rate'])/100;*/
				}else{
					$opt_price = $acc_unit_payprice;
					//공급가 방식 정산
					if($optdata['commission_type'] == 'SUPR'){
						$commission_price_tmp	= $optdata['commission_rate'] - $optdata['salescost_provider'];
						$sales_unit_feeprice	= $opt_price - $commission_price_tmp;//수수료단가
					}else{
						$commission_price_tmp	= ((int)$optdata['consumer_price'] * $optdata['commission_rate'] /100) - $optdata['salescost_provider'];
						$sales_unit_feeprice	= $opt_price - $commission_price_tmp;//수수료단가
					}
				}

				$acc_unit_payprice					= floor($acc_unit_payprice);
				$commission_price					= floor($commission_price_tmp);//단품으로 계산
				if($commission_price < 0) $commission_price = 0;
				$optdata['commission_price']		= $commission_price;									//개당 정산금액
				$optdata['sales_unit_feeprice']		= $sales_unit_feeprice;									//정산 수수료금액-개당
				$optdata['sales_unit_payprice']		= $acc_unit_payprice;									//실 결제액 (개당)
			
				$optdata['commission_text']			= $optdata['commission_text']."||".$opt_price.
														"=".$commission_price." ".$acc_charge_str." ";			//정산계산식 상세설명

			}elseif($this->account_fee_ar['pg']){//강원마트전용 결제수수료


				$acc_unit_payprice			= $optdata['price'] - $optdata['salescost_total'];
				$sales_unit_feeprice		= acc_string_floor($acc_unit_payprice * $optdata['commission_rate'] / 100,$optdata['pg']); //■ C 수수료
				//debug_var($sales_unit_feeprice."=acc_unit_payprice:".$optdata['price']."-".$optdata['salescost_total'].")*".$optdata['commission_rate']); sale_ratio
				$tot_commission_unit				= $sales_unit_feeprice + $optdata['sales_unit_minfee'];// + $optdata['salescost_provider'];//개당
				$commission_price					= $acc_unit_payprice - $tot_commission_unit;	//개당 임시정산 - 수수료합계
				$commission_price					= floor($commission_price);// 정산가 소수점 버림 처리
				if($commission_price < 0)			$commission_price = 0;
				$optdata['commission_price']		= $commission_price;							//개당 정산금액
				$optdata['sales_unit_feeprice']		= $sales_unit_feeprice;							//정산 수수료금액-개당
				$optdata['sales_unit_payprice']		= $acc_unit_payprice;							//실 결제액 (개당)
				$optdata['commission_text']			= $optdata['commission_text']."||".$optdata['price']." - ".$optdata['salescost_total'].
														"-".$sales_unit_feeprice.
														"-".$optdata['sales_unit_minfee'].
														"=".$commission_price." ".$acc_charge_str." ";			//정산계산식 상세설명 
														//"-".$optdata['salescost_provider'].
			}

			/**
			정산계산식 수동처리
			**/
			$provider_all[]		= $optdata['salescost_provider'];
			$feeprice_all[]		= ($optdata['sales_unit_feeprice']*$optdata['ea'])+$optdata['sales_unit_minfee'];//짜투리용
			$comm_all[]			= $optdata['commission_price']*$optdata['ea'];
			$loop[] = $optdata;
		}//end foreach
		
		if($get_account_subopt) foreach($get_account_subopt as $suboptk => $subdata){

			$set_ratio_array[] = array(
				'seq'			=> $subdata['seq'],
				'order_type'	=> $subdata['order_type'],
				'order_seq'		=> $subdata['order_seq'],
				'order_form_seq'=> $subdata['order_form_seq'],
				'sale_ratio'	=> ($subdata['price']*$subdata['ea'])
			);

			if($subdata['member_sale'] && !$subdata['member_sale_unit'] )	$subdata['member_sale_unit']	= $subdata['member_sale'];
/* 이미 계산된 값인데 재계산으로 데이터 누락이 발생하여 삭제 :: 2018-07-13 lkh
			acc_promotion_sales_unit('emoney',		$subdata['emoney_sale_unit'],	$subdata['emoney_sale_provider'],		$subdata);
			acc_promotion_sales_unit('cash',		$subdata['cash_sale_unit'],		$subdata['cash_sale_provider'],			$subdata);
			acc_promotion_sales_unit('enuri',		$subdata['enuri_sale_unit'],	$subdata['enuri_sale_provider'],		$subdata);
			acc_promotion_sales_unit('npay_point',$subdata['npay_point_sale_unit'],$subdata['npay_point_sale_provider'],	$subdata);

			acc_promotion_sales_unit('multi',	$subdata['multi_sale_unit'],	$subdata['multi_sale_provider'],	$subdata);
			acc_promotion_sales_unit('event',	$subdata['event_sale_unit'],	$subdata['event_sale_provider'],	$subdata);
			acc_promotion_sales_unit('member',	$subdata['member_sale_unit'],	$subdata['member_sale_provider'],	$subdata);
			acc_promotion_sales_unit('coupon',	$subdata['coupon_sale_unit'],	$subdata['coupon_sale_provider'],	$subdata);
			acc_promotion_sales_unit('fblike',	$subdata['fblike_sale_unit'],	$subdata['fblike_sale_provider'],	$subdata);
			acc_promotion_sales_unit('mobile',	$subdata['mobile_sale_unit'],	$subdata['mobile_sale_provider'],	$subdata);
			acc_promotion_sales_unit('code',	$subdata['code_sale_unit'],		$subdata['code_sale_provider'],		$subdata);
			acc_promotion_sales_unit('referer',	$subdata['referer_sale_unit'],	$subdata['referer_sale_provider'],	$subdata);
*/
			/**
			** 할인부담금 시작
			$subdata['salescost_admin_sales']		= $subdata['salescost_event'] + $subdata['salescost_event_rest']
											   + $subdata['salescost_multi'] + $subdata['salescost_multi_rest']
											   + 
			$subdata['salescost_provider_sales']		= $subdata['salescost_event_provider'] + $subdata['salescost_event_provider_rest']
														   + $subdata['salescost_multi_provider'] + $subdata['salescost_multi_provider_rest']
														   + 
			$salescost_sales		= $subdata['multi_sale'] + $subdata['multi_sale_rest']
															   + $subdata['event_sale'] + $subdata['event_sale_rest']
															   + 
			**/
			## 본사 부담금
			$subdata['salescost_admin_promotion']	= $subdata['salescost_emoney'] + $subdata['salescost_emoney_rest']
													   + $subdata['salescost_cash'] + $subdata['salescost_cash_rest']
													   + $subdata['salescost_enuri'] + $subdata['salescost_enuri_rest']
													   + $subdata['salescost_npay_point'] + $subdata['salescost_npay_point_rest'];
			$subdata['salescost_admin_sales']		= $subdata['salescost_event'] + $subdata['salescost_event_rest']
													   + $subdata['salescost_multi'] + $subdata['salescost_multi_rest']
													   + $subdata['salescost_member'] + $subdata['salescost_member_rest']
													   + $subdata['salescost_coupon'] + $subdata['salescost_coupon_rest']
													   + $subdata['salescost_fblike'] + $subdata['salescost_fblike_rest']
													   + $subdata['salescost_mobile'] + $subdata['salescost_mobile_rest']
													   + $subdata['salescost_code'] + $subdata['salescost_code_rest']
													   + $subdata['salescost_referer'] + $subdata['salescost_referer_rest'];
			$subdata['salescost_admin']				= $subdata['salescost_admin_promotion'] + $subdata['salescost_admin_sales'];

			//입점사 부담금
			$subdata['salescost_provider_promotion']= $subdata['salescost_emoney_provider'] + $subdata['salescost_emoney_provider_rest']
													   + $subdata['salescost_cash_provider'] + $subdata['salescost_cash_provider_rest']
													   + $subdata['salescost_enuri_provider'] + $subdata['salescost_enuri_provider_rest']
													   + $subdata['salescost_npay_point_provider'] + $subdata['salescost_npay_point_provider_rest'];
			$subdata['salescost_provider_sales']	= $subdata['salescost_event_provider'] + $subdata['salescost_event_provider_rest']
													   + $subdata['salescost_multi_provider'] + $subdata['salescost_multi_provider_rest']
													   + $subdata['salescost_member_provider'] + $subdata['salescost_member_provider_rest']
													   + $subdata['salescost_coupon_provider'] + $subdata['salescost_coupon_provider_rest']
													   + $subdata['salescost_fblike_provider'] + $subdata['salescost_fblike_provider_rest']
													   + $subdata['salescost_mobile_provider'] + $subdata['salescost_mobile_provider_rest']
													   + $subdata['salescost_code_provider'] + $subdata['salescost_code_provider_rest']
													   + $subdata['salescost_referer_provider'] + $subdata['salescost_referer_provider_rest'];
			$subdata['salescost_provider']			= $subdata['salescost_provider_promotion'] + $subdata['salescost_provider_sales'];

			$subdata['acc_promotion_total']			= ($subdata['salescost_admin_promotion']) + ($subdata['salescost_provider_promotion']); 
			$subdata['acc_sale_total']				=  $subdata['salescost_admin_sales'] + $subdata['salescost_provider_sales'];
			$subdata['salescost_total']				= $subdata['acc_promotion_total'] + $subdata['acc_sale_total'];
			/**
			** 할인부담금 끝
			**/

			/**
			정산계산식 수동처리
			**/
			if( $this->account_fee_ar['goods'] ) {//상품별 수수료 적용시
				$acc_unit_payprice		= (int)$subdata['price'] - $subdata['salescost_total'];//실결제금액(단가)
				$subopt_price_tmp		= (int)$subdata['price'] - $subdata['salescost_provider'];
				
				$acc_charge_str = "";
				if($subdata['commission_type'] == 'SACO'){
					$sub_price = $subopt_price_tmp;
					$acc_charge_str			= $subdata['commission_rate']."%";
				
					$commission_price_tmp	= $sub_price*(100-$subdata['commission_rate'])/100;
					$sales_unit_feeprice	= $sub_price - $commission_price_tmp;//수수료(단가)
				}else{
					$sub_price = $acc_unit_payprice;
					//공급가 방식 정산
					if($subdata['commission_type'] == 'SUPR'){
						$commission_price_tmp	= $subdata['commission_rate'] - $subdata['salescost_provider'];
						$sales_unit_feeprice	= $sub_price - $commission_price_tmp;//수수료단가
					}else{
						$commission_price_tmp	= ((int)$subdata['consumer_price'] * $subdata['commission_rate'] /100) - $subdata['salescost_provider'];
						$sales_unit_feeprice	= $sub_price - $commission_price_tmp;//수수료단가
					}
				}

				$acc_unit_payprice					= floor($acc_unit_payprice);
				$commission_price					= floor($commission_price_tmp);
				if($commission_price < 0) $commission_price = 0;
				$subdata['commission_price']		= $commission_price;									//개당 정산금액
				$subdata['sales_unit_feeprice']		= $sales_unit_feeprice;									//정산 수수료금액-개당
				$subdata['sales_unit_payprice']		= $acc_unit_payprice;									//실 결제액 (개당)

				$subdata['commission_text']			= $subdata['commission_text']."||".$acc_unit_payprice.
														"=".$commission_price." ".$acc_charge_str." ";			//정산계산식 상세설명

			}elseif($this->account_fee_ar['pg']){//강원마트전용 결제수수료

				$acc_unit_payprice					= $subdata['price'] - $subdata['salescost_total'];
				$sales_unit_feeprice				= acc_string_floor($acc_unit_payprice * $subdata['commission_rate'] / 100,$subdata['pg']); //■ C 수수료

				$tot_commission_unit				= $sales_unit_feeprice + $subdata['sales_unit_minfee'];// + $subdata['salescost_provider'];//개당
				$commission_price					= $acc_unit_payprice - $tot_commission_unit;	//개당 임시정산 - 수수료합계
				$commission_price					= floor($commission_price);// 정산가 소수점 버림 처리
				if($commission_price < 0)			$commission_price = 0;
				$subdata['commission_price']		= $commission_price;							//개당 정산금액
				$subdata['sales_unit_feeprice']		= $sales_unit_feeprice;							//정산 수수료금액-개당
				$subdata['sales_unit_payprice']		= $acc_unit_payprice;							//실 결제액 (개당)

				$subdata['commission_text']			= $subdata['commission_text']."||".$subdata['price']." - ".$subdata['salescost_total'].
														"-".$sales_unit_feeprice.
														"-".$subdata['sales_unit_minfee'].
														"=".$commission_price." ".$acc_charge_str." ";				//정산계산식 상세설명
			}
			/**
			정산계산식 수동처리
			**/
			$provider_all[]		= $subdata['salescost_provider'];
			$feeprice_all[]		= ($subdata['sales_unit_feeprice']*$subdata['ea'])+$subdata['sales_unit_minfee'];//짜투리용
			$comm_all[]			= $subdata['commission_price']*$subdata['ea'];
			$loop[] = $subdata;
		}//end foreach

		if($get_account_shipping) foreach($get_account_shipping as $key=>$shipping){
			
			$set_ratio_array[] = array(
				'seq'			=> $shipping['seq'],
				'order_type'	=> $shipping['order_type'],
				'order_seq'		=> $shipping['order_seq'],
				'order_form_seq'=> $shipping['order_form_seq'],
				'sale_ratio'	=> ($subdata['price'])
			);

			/**
			할인항목별 처리
			**/
/* 이미 계산된 값인데 재계산으로 데이터 누락이 발생하여 삭제 :: 2018-07-13 lkh
			acc_promotion_sales_unit('emoney',		$shipping['emoney_sale_unit'],	$shipping['emoney_sale_provider'],		$shipping);
			acc_promotion_sales_unit('cash',		$shipping['cash_sale_unit'],	$shipping['cash_sale_provider'],		$shipping);
			acc_promotion_sales_unit('enuri',		$shipping['enuri_sale_unit'],	$shipping['enuri_sale_provider'],		$shipping);
			acc_promotion_sales_unit('npay_point',	$shipping['npay_point_sale_unit'],$shipping['npay_point_sale_provider'],$shipping);

			acc_promotion_sales_unit('coupon',		$shipping['shipping_coupon_sale'],			$shipping['coupon_sale_provider'],		$shipping);
			acc_promotion_sales_unit('code',		$shipping['shipping_promotion_code_sale'],	$shipping['code_sale_provider'],		$shipping);
*/
			/**
			할인항목별 처리끝
			**/
			/**
			** 할인부담금 시작
			**/
			## 본사 부담금
			$shipping['salescost_admin_promotion']	= $shipping['salescost_emoney'] + $shipping['salescost_emoney_rest']
													   + $shipping['salescost_cash'] + $shipping['salescost_cash_rest']
													   + $shipping['salescost_enuri'] + $shipping['salescost_enuri_rest'];
			$shipping['salescost_admin_sales']		= $shipping['salescost_coupon'] + $shipping['salescost_coupon_rest']
													   + $shipping['salescost_code'] + $shipping['salescost_code_rest'];
			$shipping['salescost_admin']			= $shipping['salescost_admin_promotion'] + $shipping['salescost_admin_sales'];

			//입점사 부담금
			$shipping['salescost_provider_promotion']	= $shipping['salescost_emoney_provider'] + $shipping['salescost_emoney_provider_rest']
														   + $shipping['salescost_cash_provider'] + $shipping['salescost_cash_provider_rest']
														   + $shipping['salescost_enuri_provider'] + $shipping['salescost_enuri_provider_rest'];
			$shipping['salescost_provider_sales']		= $shipping['salescost_coupon_provider'] + $shipping['salescost_coupon_provider_rest']
														   + $shipping['salescost_code_provider'] + $shipping['salescost_code_provider_rest'];
			$shipping['salescost_provider']				= $shipping['salescost_provider_promotion'] + $shipping['salescost_provider_sales'];
			$shipping['acc_promotion_total']			= ($shipping['salescost_admin_promotion']) + ($shipping['salescost_provider_promotion']);
			$shipping['acc_sale_total']					= $shipping['salescost_admin_sales'] + $shipping['salescost_provider_sales'];
			$shipping['salescost_total']				= $shipping['acc_promotion_total'] + $shipping['acc_sale_total'];
			/**
			** 할인부담금 끝
			**/

			/**
			정산계산식 수동처리
			**/
			if( $this->account_fee_ar['goods'] ) {//상품별 수수료 적용시
				// 배송비 수수료 추가
				$shipping_price_tmp					= (int)$shipping['sales_price'] - $shipping['salescost_provider'];//정산대상금액

				$acc_charge_str						= $shipping['commission_rate']."%";

				$commission_price_tmp				= $shipping_price_tmp*(100-$shipping['commission_rate'])/100;
				$sales_unit_feeprice				= $shipping_price_tmp*($shipping['commission_rate'])/100;//수수료(단가)

				$acc_unit_payprice					= $shipping['sales_price'] - $shipping['salescost_total']; // 실결제금액
				$sales_unit_minfee					= 0;

				$commission_price					= floor($commission_price_tmp);//단품으로 계산 sales_unit_feeprice

				$shipping['commission_price']		= $commission_price;							//개당 정산금액
				$shipping['sales_unit_feeprice']	= $sales_unit_feeprice;							//정산 수수료금액-개당
				$shipping['sales_unit_minfee']		= $sales_unit_minfee;							//정산 추가수수료 개당(+)
				$shipping['sales_unit_payprice']	= $acc_unit_payprice;							//실 결제액 (개당)
				$shipping['commission_text']		= $shipping['sales_price'].
													"-".$shipping['salescost_provider'].
													"-".$sales_unit_minfee.
													"=".$commission_price." ".$acc_charge_str."";			//정산계산식 상세설명

			}elseif($this->account_fee_ar['pg']){//강원마트전용 결제수수료
				$acc_unit_payprice				=  $shipping['price'] - $shipping['salescost_total'];
				//$acc_unit_payprice			= $acc_unit_payprice; //■ B 실 결제액 (개당) 
				$sales_unit_feeprice			= acc_string_floor($acc_unit_payprice * $shipping['commission_rate'] / 100,$data_order['pg']); //■ C 수수료
				$tot_commission_unit			= $sales_unit_feeprice + $shipping['sales_unit_minfee'];// + $shipping['salescost_provider'];//단
				$commission_price				= $acc_unit_payprice - $tot_commission_unit; //개당  임시정산 - 수수료합계
				$commission_price				= floor($commission_price);
				$shipping['commission_price']		= $commission_price;							//개당 정산금액
				$shipping['sales_unit_feeprice']	= $sales_unit_feeprice;							//정산 수수료금액-개당
				$shipping['sales_unit_payprice']	= $acc_unit_payprice;							//실 결제액 (개당)
				$shipping['commission_text']		= $shipping['commission_text']."||".$shipping['sales_price']." - ".$shipping['salescost_total'].
														"-".$sales_unit_feeprice.
														"-".$shipping['sales_unit_minfee'].
														"=".$commission_price."".$acc_charge_str."";			//정산계산식 상세설명
														//"-".$shipping['salescost_provider'].
			}
			/**
			정산계산식 수동처리
			**/
			$provider_all[]		= $shipping['salescost_provider'];
			$feeprice_all[]		= ($shipping['sales_unit_feeprice']*$shipping['ea'])+$shipping['sales_unit_minfee'];//짜투리용
			$comm_all[]			= $shipping['commission_price'];
			$loop[] = $shipping;
		}//end foreach

		@usort($set_ratio_array, 'account_order_sale_desc');//할인금액내림차순
		
		unset($account_ins_opt,$account_ins_subopt,$account_ins_shipping);

		//정산금액 검증 : 수수료 짜투리 검수
		if($this->account_fee_ar['pg']) {//강원바트 PG 수수료방식
			//$emoney_cash_npay_total	= $data_order['emoney']+$data_order['cash']+$data_order['enuri']+$data_order['npay_point'];
			$total_settleprice	= $data_order['settleprice']-$data_order['npay_point'];//실제 총결제금액

			$chargeprice			= array_sum($feeprice_all);//
			$allchargeprice			= acc_string_floor($total_settleprice * $paycharge['commission_rate']/100,$data_order['pg']) + $paycharge['min_fee'];
			$total_commission		= ($total_settleprice - $allchargeprice) ;// - array_sum($provider_all) 
			$comm_all_sum			= array_sum($comm_all);
			$sales_feeprice_rest	= acc_string_floor($allchargeprice - $chargeprice,$data_order['pg']);
			$sum_settleprice		= ($comm_all_sum + $chargeprice);
			$commission_price_rest	= acc_string_floor($total_commission-$comm_all_sum,$data_order['pg']);
			
		}
		foreach($loop as $accountdata) {
			if(($commission_price_rest || $sales_feeprice_rest)  && $set_ratio_array[0]['seq'] == $accountdata['seq'] && $set_ratio_array[0]['order_type'] == $accountdata['order_type'] ) {//짜투리는 주문금액이 가장 큰 상품에 추가
				$commission_text = " rest:".$commission_price_rest." =(".($total_settleprice)." - ".$sales_feeprice_rest." ) - ".$comm_all_sum." ";
				//. " -" . array_sum($provider_all)
				$accountdata['commission_price_rest']	= $commission_price_rest;
				$accountdata['sales_feeprice_rest']		= $sales_feeprice_rest;
				$accountdata['commission_text']			= $accountdata['commission_text']."||".$commission_text;
			}else{
				$accountdata['commission_price_rest']	= 0;
				$accountdata['sales_feeprice_rest']		= 0;
			}
			
			//총 정산금액, 총 수수료 업데이트 @20190529 pjm
			$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
			accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

			list($total_feeprice, $total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);
			
			// 임시매출데이터를 생성할 때에는 최종 정산금액과 수수료는 업데이트할 필요 없음 by hed
			// $accountdata['total_feeprice'] = $total_feeprice;
			// $accountdata['total_commission_price'] = $total_commission_price;
			unset($accountdata['total_feeprice']);
			unset($accountdata['total_commission_price']);
			
			// 임시매출 데이터가 생성될 때 총 결재금액을 생성
			$accountdata['total_payprice'] = $total_payprice;
			
			$this->calculate_sales_update_tmp_check($accountdata, $accountdata['seq']);
		}
	}

	/*
	에누리, 마일리지, 예치금을 비율로 나눈 자투리 금액을 재계산
	1. 각 상품종별 결제금액 짜투리
	2. 각 할인,결제수단(PG,에누리,마일리지,예치금)별 짜투리
	*/
	public function update_ratio_add_rest($set_ratio_array = array(),$sale_use_price=array()){

		//debug($sale_use_price);
		//debug($this->_tmp_emoney_rest);
		$settle_price			= $sale_use_price['settleprice'];		//PG,무통장 결제금액(마일리지할인,예치금결제 제외금액)
		unset($sale_use_price['settleprice']);

		$sum_enuri = $sum_emoney = $sum_cash = $sum_goods = 0;
		$goods_remain = $plus_remain = 0;
		foreach($set_ratio_array as $_key =>$_val){

			$sale_price		= $_val['unit_price'] * $_val['ea'];
			$enuri_sale		= $_val['enuri_sale_unit'] * $_val['ea'] + $_val['enuri_sale_rest'];
			$emoney_sale	= $_val['emoney_sale_unit'] * $_val['ea'] + $_val['emoney_sale_rest'];
			$cash_sale		= $_val['cash_sale_unit'] * $_val['ea'] + $_val['cash_sale_rest'];
			$goods_sale		= $sale_price - ($enuri_sale + $emoney_sale + $cash_sale);

			$sum_enuri		+= $enuri_sale;
			$sum_emoney		+= $emoney_sale;
			$sum_cash		+= $cash_sale;
			$sum_goods		+= $goods_sale; //3510 - 2101 = 1409

			//debug(" --------------------------------------------------------------------");
			$goods_remain	= $sum_goods - $settle_price - $plus_remain;	// PG결제금액 - (상품금액 - (에누리+마일리지+예치금)
			//debug("goods_remain : ".$goods_remain ." = sum_goods : ".$sum_goods ." - settle_price : ".$settle_price ." + plus_remain : ".$plus_remain);
			if($goods_remain < 0) $goods_remain = 0;

			//상품별 합계  3510 - (2010 + 0 + (2101 * 1 + 0) + ( 0 ))
			$goods_sale_sum = ($goods_sale + $enuri_sale + $emoney_sale + $cash_sale) - $goods_remain;

			//debug("sale_price : ".$sale_price." - goods_sale_sum: ".$goods_sale_sum);
 			$remain_price	= $sale_price - $goods_sale_sum;	//3510 - 4111
			//debug("remain_price : ".$remain_price);
			if($remain_price <= 0) continue;

			// -------------------------------------------------------------------------------------------------
			// enuri 자투리 추가계산
			//debug("에누리 체크 > ".$sum_enuri);
			//debug("(".$sale_use_price['enuri']." <= ".$sum_enuri." && ".$sale_use_price['emoney']." <= ".$sum_emoney." && ".$sale_use_price['cash']." <= ".$sum_cash.")");

			// 사용한 에누리 보다 환불해준 에누리가 크고
			// 사용한 마일리지 보다 환불해준 마일리지가 크고
			// 사용한 예치금 보다 환불해준 예치금이 크면 건너뛰기
			$_tmp_remain_enuri	= ($sale_use_price['enuri'] > 0)? $sale_use_price['enuri'] - $sum_enuri : 0;
			$_tmp_remain_emoney = ($sale_use_price['emoney'] > 0)? $sale_use_price['emoney'] - $sum_emoney : 0;
			$_tmp_remain_cash	= ($sale_use_price['cash'] > 0)? $sale_use_price['cash'] - $sum_cash : 0;

			if($_tmp_remain_enuri <= 0 && $_tmp_remain_emoney <= 0 && $_tmp_remain_cash <= 0) continue;

			if($_tmp_remain_emoney > 0 && $remain_price > $_tmp_remain_emoney) $remain_price -= $_tmp_remain_emoney;

			if($this->_tmp_emoney_rest['emoney'] > 0){
				//debug("emoney 짜투리 + " .$remain_price);
				$set_ratio_array[$_key]['emoney_sale_rest'] += $remain_price;
				$sum_emoney									+= $remain_price;
				$goods_sale_sum								+= $remain_price;
				$this->_tmp_emoney_rest['emoney']			-= $remain_price;
				$plus_remain								+= $remain_price;
				$remain_price								= $sale_price - $goods_sale_sum;
				//debug("remain_price : ".$remain_price);
			}

			// -------------------------------------------------------------------------------------------------
			//debug("cash 체크 > ");
			// cash 자투리 추가계산
			if($remain_price <= 0) continue;
			if($this->_tmp_emoney_rest['cash'] > 0){
				//debug("cash 짜투리 + " .$remain_price);
				$set_ratio_array[$_key]['cash_sale_rest']	+= $remain_price;
				$sum_cash									+= $remain_price;
				$goods_sale_sum								+= $remain_price;
				$this->_tmp_emoney_rest['cash']				-= $remain_price;
				$plus_remain								+= $remain_price;
				$remain_price								= $sale_price - $goods_sale_sum;
			}

			if($remain_price <= 0) continue;
			if($this->_tmp_emoney_rest['enuri'] > 0){
				//debug("에누리 짜투리 + " .$remain_price);
				$set_ratio_array[$_key]['enuri_sale_rest'] 	+= $remain_price;
				$sum_enuri									+= $remain_price;
				$goods_sale_sum								+= $remain_price;
				$this->_tmp_emoney_rest['enuri']			-= $remain_price;
				$plus_remain								+= $remain_price;
	 			$remain_price								= $sale_price - $goods_sale_sum;
				//debug("remain_price : ".$remain_price);
			}
			
		}

		return $set_ratio_array;
	}

	/*
	 * 주문 적립금,에누리,이머니,네이버페이(포인트) 사용액 상품옵션/추가옵션/배송비 비율별로 나눔
	 * @param
	 * @
	*/
	public function update_ratio_emoney_cash_enuri_npoint($order_seq, $set_order_price_ratio, $enurin_paypoint=null)
	{
		$total				= $set_order_price_ratio['total'];
		$set_ratio_array	= $set_order_price_ratio['set_ratio_array'];
		$data_order = $this->get_accountall_order($order_seq);
		
		$this->_tmp_emoney_rest = array();
		if( ($enurin_paypoint=='all' || $enurin_paypoint=='enuri') && $data_order['enuri']>=0) {
			$upquery++;
			$set_ratio_array = $this->calculate_promotion_unit($data_order['enuri'],$total,$set_ratio_array,'enuri');
		}
		if( ($enurin_paypoint=='all' || $enurin_paypoint=='npay_point') && $data_order['npay_point']>0) {
			$upquery++;
			$set_ratio_array = $this->calculate_promotion_unit($data_order['npay_point'],$total,$set_ratio_array,'npay_point');
		}
		// 에누리 적용 시에도 기존 마일리지나 예치금이 존재할 수 있으며
		// 마일리지/예치금/에누리의 총 할인액을 기준으로 분배해야함.
		// 단 실제 DB 업데이트에는 에누리만 변경
		if( $enurin_paypoint!='npay_point') {
			if($data_order['emoney']>0){
				$upquery++;
				$set_ratio_array = $this->calculate_promotion_unit($data_order['emoney'],$total,$set_ratio_array,'emoney');
			}

			if($data_order['cash']>0){
				$upquery++;
				$set_ratio_array = $this->calculate_promotion_unit($data_order['cash'],$total,$set_ratio_array,'cash');
			}
		}

		// 에누리, 마일리지, 예치금 자투리 추가계산 2019.11.21 pjm
		$_params = array("settleprice"=>$data_order['settleprice']
							,"enuri" =>$data_order['enuri']
							,"emoney" =>$data_order['emoney']
							,"cash" =>$data_order['cash']);
		$set_ratio_array = $this->update_ratio_add_rest($set_ratio_array,$_params);

		if($upquery) {
			foreach($set_ratio_array as $data){
				$arr_set_query = $bind = array();
				if( $enurin_paypoint!='enuri' && $enurin_paypoint!='npay_point' && $data_order['emoney']>0){
					$arr_set_query[]	= "emoney_sale_unit=?";
					$arr_set_query[]	= "emoney_sale_rest=?";
					$bind[]				= (int)$data['emoney_sale_unit'];
					$bind[]				= (int)$data['emoney_sale_rest'];
				}
				if($enurin_paypoint!='enuri' && $enurin_paypoint!='npay_point' && $data_order['cash']>0){
					$arr_set_query[]	= "cash_sale_unit=?";
					$arr_set_query[]	= "cash_sale_rest=?";
					$bind[]				= (int)$data['cash_sale_unit'];
					$bind[]				= (int)$data['cash_sale_rest'];
				}
				if( ($enurin_paypoint=='all' || $enurin_paypoint=='enuri') && $data_order['enuri']>=0){
					$arr_set_query[]	= "enuri_sale_unit=?";
					$arr_set_query[]	= "enuri_sale_rest=?";
					$bind[]				= (int)$data['enuri_sale_unit'];
					$bind[]				= (int)$data['enuri_sale_rest'];
				}
				if( ($enurin_paypoint=='all' || $enurin_paypoint=='npay_point') && $data_order['npay_point']>0){
					$arr_set_query[]	= "npay_point_sale_unit=?";
					$arr_set_query[]	= "npay_point_sale_rest=?";
					$bind[]				= (int)$data['npay_point_sale_unit'];
					$bind[]				= (int)$data['npay_point_sale_rest'];
				}
				
				$bind[]    = $data['seq'];
				$updateCol = implode(',',$arr_set_query);
				if($data['type']=='option'){
					$query = "update fm_order_item_option set {$updateCol} where item_option_seq=?";
				}else if($data['type']=='suboption'){
					$query = "update fm_order_item_suboption set {$updateCol} where item_suboption_seq=?";
				}else if($data['type']=='shipping'){
					$query = "update fm_order_shipping set {$updateCol} where shipping_seq=?";
				}
				$this->db->query($query,$bind);

				//임시매출데이타 에누리 업데이트
				if( ($enurin_paypoint=='enuri') && $data_order['enuri']>=0){
					$data_tmp_order			= $this->get_act_tmp_total($order_seq);
					if($data_tmp_order) {
						$ac_arr_set_query = $ac_bind = array();
						$ac_arr_set_query[]		= "enuri_sale_unit=?";
						$ac_arr_set_query[]		= "enuri_sale_rest=?";
						$ac_arr_set_query[]		= "salescost_enuri=?";
						$ac_arr_set_query[]		= "salescost_enuri_rest=?";
						$ac_arr_set_query[]		= "salescost_enuri_provider=?";
						$ac_arr_set_query[]		= "salescost_enuri_provider_rest=?";
						$ac_bind[]				= $data['enuri_sale_unit'];
						$ac_bind[]				= $data['enuri_sale_rest'];
						$ac_bind[]				= $data['enuri_sale_unit'];
						$ac_bind[]				= $data['enuri_sale_rest'];
						$ac_bind[]				= 0;
						$ac_bind[]				= 0;
						$ac_bind[]				 = $data['seq'];
						$ac_bind[]				 = $data['type'];
						$ac_bind[]				 = $data['order_seq'];
						$ac_updateCol = implode(',',$ac_arr_set_query);
						$ac_query = "update {$this->tb_act_tmp} set {$ac_updateCol} where order_form_seq=? and order_type=? and order_seq=?";
						$this->db->query($ac_query,$ac_bind);
					}
				}

				if( ($enurin_paypoint=='npay_point') && $data_order['npay_point']>0){
					if(!$data_tmp_order) $data_tmp_order= $this->get_act_tmp_total($order_seq);
					if($data_tmp_order) {
						$ac_arr_set_query = $ac_bind = array();
						$ac_arr_set_query[]		= "npay_point_sale_unit=?";
						$ac_arr_set_query[]		= "npay_point_sale_rest=?";
						$ac_arr_set_query[]		= "salescost_npay_point=?";
						$ac_arr_set_query[]		= "salescost_npay_point_rest=?";
						$ac_arr_set_query[]		= "salescost_npay_point_provider=?";
						$ac_arr_set_query[]		= "salescost_npay_point_provider_rest=?";
						$ac_bind[]				= $data['npay_point_sale_unit'];
						$ac_bind[]				= $data['npay_point_sale_rest'];
						$ac_bind[]				= $data['npay_point_sale_unit'];
						$ac_bind[]				= $data['npay_point_sale_rest'];
						$ac_bind[]				= 0;
						$ac_bind[]				= 0;
						$ac_bind[]				 = $data['seq'];
						$ac_bind[]				 = $data['type'];
						$ac_bind[]				 = $data['order_seq'];
						$ac_updateCol = implode(',',$ac_arr_set_query);
						$ac_query = "update {$this->tb_act_tmp} set {$ac_updateCol} where order_form_seq=? and order_type=? and order_seq=?";
						$this->db->query($ac_query,$ac_bind);
					}
				}

			}
		}

	}

	//주문금액별정의/비율/단가계산 후 정렬
	public function set_order_price_ratio($order_seq, &$account_ins_opt=null, &$account_ins_subopt=null, &$account_ins_shipping=null){
		$total = 0;
		if(!$account_ins_opt)		$account_ins_opt		= $this->get_account_item_option($order_seq);
		if(!$account_ins_subopt)	$account_ins_subopt		= $this->get_account_item_suboption($order_seq);
		if(!$account_ins_shipping)	$account_ins_shipping	= $this->get_account_order_shipping($order_seq);

		// 마일리지/예치금/포인트를 제외한 할인에 sale_price가 0원이 될 경우 마일리지/예치금/포인트 분배에 의해 할인가가 음수가 되므로 분배 기준이 되는 unit_price를 0원으로 고정함 by hed
		$arr_sum_sale_list = array(
			'event_sale_unit', 
			'multi_sale_unit', 
			'member_sale_unit', 
			'code_sale_unit', 
			'coupon_sale_unit', 
			'code_sale_unit', 
			'fblike_sale_unit', 
			'mobile_sale_unit', 
			'referer_sale_unit'
		);
		
		// step1-1 필수옵션 영역
		// 세일 된 가격으로 넣어야 정상 비율이 나오므로 수정 :: 2018-07-17 pjw		
		if($account_ins_opt) foreach($account_ins_opt as $k => $data_option){
			$price = $data_option['sale_price'] != '' && $data_option['sale_price'] > 0 ? $data_option['sale_price'] : $data_option['price'];
			// 마일리지/예치금/포인트를 제외한 할인에 sale_price가 0원이 될 경우 마일리지/예치금/포인트 분배에 의해 할인가가 음수가 되므로 분배 기준이 되는 unit_price를 0원으로 고정함 by hed
			$sum_sales = 0;
			foreach($arr_sum_sale_list as $sum_sale_list){
				$sum_sales += $data_option[$sum_sale_list];
			}
			// 상품 판매가에서 할인 금액(마일리지/예치금/포인트제외)를 감산했을 때 0과 같거나 작다면 이는 배송비를 제외한 상품의 금액이 0원으로 
			// 결제되었다는 뜻이며 마일리지/예치금/포인트의 분배는 상품에 분배되지 않고 배송비에 부담되어야함
			if($data_option['price'] - $sum_sales <= '0'){
				$price = 0;
			}

			$total += $price * (int) $data_option['ea'];
			
			$param[] = array(
				'type'			=> 'option',
				'key'			=> $k,
				'order_seq'		=> $data_option['order_seq'],
				'seq'			=> $data_option['item_option_seq'],
				'unit_price'	=> $price,
				'ea'			=> $data_option['ea']
			);
		}
		
		// step1-2 추가옵션 영역
		// 세일 된 가격으로 넣어야 정상 비율이 나오므로 수정 :: 2018-07-17 pjw		
		if($account_ins_subopt) foreach($account_ins_subopt as $k => $data_suboption){
			$price = $data_suboption['sale_price'] != '' && $data_suboption['sale_price'] > 0 ? $data_suboption['sale_price'] : $data_suboption['price'] - $data_suboption['member_sale'];
			// 마일리지/예치금/포인트를 제외한 할인에 sale_price가 0원이 될 경우 마일리지/예치금/포인트 분배에 의해 할인가가 음수가 되므로 분배 기준이 되는 unit_price를 0원으로 고정함 by hed
			$sum_sales = 0;
			foreach($arr_sum_sale_list as $sum_sale_list){
				$sum_sales += $data_suboption[$sum_sale_list];
			}
			// 상품 판매가에서 할인 금액(마일리지/예치금/포인트제외)를 감산했을 때 0과 같거나 작다면 이는 배송비를 제외한 상품의 금액이 0원으로 
			// 결제되었다는 뜻이며 마일리지/예치금/포인트의 분배는 상품에 분배되지 않고 배송비에 부담되어야함
			if($data_suboption['price'] - $sum_sales <= '0'){
				$price = 0;
			}
			$total += $price * (int) $data_suboption['ea'];
			$param[] = array(
				'type'			=> 'suboption',
				'key'			=> $k,
				'order_seq'		=> $data_suboption['order_seq'],
				'seq'			=> $data_suboption['item_suboption_seq'],
				'unit_price'	=> $price,
				'ea'			=> $data_suboption['ea']
			);
		}

		//step1-3 배송비 영역추가 (지역/추가/개별)
		if($account_ins_shipping) foreach($account_ins_shipping as $k => $shipping){
			unset($shipping_total,$row_shipping_sum);
			// 착불배송비는 할인금액 비율 고려하지 않음 2019-04-23 by hyem
			if($shipping['shipping_type'] == 'postpaid') continue;

			$shipping_total['basic_cost']				= $shipping['delivery_cost'];
			$shipping_total['add_shipping_cost']		= $shipping['add_delivery_cost'];
			//$shipping_total['shipping_cost']			= $shipping['shipping_cost'] + $shipping['add_delivery_cost'];

			if(preg_match( '/each_delivery/',$shipping['shipping_method'])){
				$shipping_total['goods_cost']				= $shipping['delivery_cost'];
				$shipping_total['add_shipping_cost']		= $shipping['add_delivery_cost'];
				//$shipping_total['goods_shipping_cost']	= $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
			}

			// 할인 추가 :: 2018-08-01 pjw
			$shipping_total['shipping_promotion_code_sale'] = $shipping['shipping_promotion_code_sale'] != '' ? $shipping['shipping_promotion_code_sale'] : 0;
			$shipping_total['shipping_coupon_sale']			= $shipping['shipping_coupon_sale'] != '' ? $shipping['shipping_coupon_sale'] : 0;
			$shipping_total['sales_cost']					= $shipping_total['shipping_promotion_code_sale'] + $shipping_total['shipping_coupon_sale'];

			//지역추가배송비
			// 할인 추가 :: 2018-08-01 pjw
			$row_shipping_sum = $shipping_total['basic_cost'] - $shipping_total['sales_cost'] + $shipping_total['goods_cost'] + $shipping_total['add_shipping_cost'];
			if( $row_shipping_sum ) {
				$total += $row_shipping_sum;
				$param[] = array(
					'type'			=> 'shipping',
					'key'			=> $k,
					'order_seq'		=> $shipping['order_seq'],
					'seq'			=> $shipping['shipping_seq'],
					'unit_price'	=> $row_shipping_sum,
					'ea'			=> 1
				);
			}
		}

		foreach($param as $k => $data){
			$tmp_unit_price				= (($data['unit_price'] * $data['ea']) / $total) * 100;
			$data['sale_ratio']			= floor( (string) ($tmp_unit_price*100))/100;
			$data['sale_ratio_unit']	= ($tmp_unit_price / $data['ea']);
			if($data['type'] == 'shipping') {
				$account_ins_shipping[$data['key']]['sale_ratio']		= $data['sale_ratio'];
				$account_ins_shipping[$data['key']]['sale_ratio_unit']	= $data['sale_ratio_unit'];
			}elseif($data['type'] == 'suboption') {
				$account_ins_subopt[$data['key']]['sale_ratio']			= $data['sale_ratio'];
				$account_ins_subopt[$data['key']]['sale_ratio_unit']	= $data['sale_ratio_unit'];
			}else{
				$account_ins_opt[$data['key']]['sale_ratio']			= $data['sale_ratio'];
				$account_ins_opt[$data['key']]['sale_ratio_unit']		= $data['sale_ratio_unit'];
			}
			$param[$k] = $data;
		}
		@usort($param, 'account_order_sale_desc');//할인금액내림차순
		return array('total'=>$total, 'set_ratio_array'=>$param);
	}

	// 에누리,캐쉬,적립금,NpayPoint 사용액 상품별 계산
	public function calculate_promotion_unit($emoney, $total, $set_ratio_array, $field){
		$checkPrice	= 0; // 누적 금액
		$allChk		= false; // 전액일 경우의 변수
		foreach($set_ratio_array as $k => $data){
			if($data['enuri_sale_total']){
				$checkPrice += ($data['enuri_sale_total'] + $data['enuri_sale_rest']);
			}elseif($data['emoney_sale_total']){
				$checkPrice += ($data['emoney_sale_total'] + $data['emoney_sale_rest']);
			}
		}
		if($total == ($checkPrice + $emoney)){
			$allChk = true;
		}

		$_tmp_data = array();
		foreach($set_ratio_array as $k => $data){
			if( ($emoney == $total) || $allChk ){
				$totalSalePrice = 0;
				if($data['enuri_sale_total']){
					$totalSalePrice += ($data['enuri_sale_total'] + $data['enuri_sale_rest']);
				}elseif($data['emoney_sale_total']){
					$totalSalePrice += ($data['emoney_sale_total'] + $data['emoney_sale_rest']);
				}
			}

			// 부동소숫점 연산 후 int 나 floor 등의 형변환이나 소수점 절삭 처리를 할 경우 소수점 데이터 누락에 의해 정상적인 계산 값이 나오지 않음.
			$tmp_emoney = (int) ((($emoney * $data['sale_ratio_unit']) / 100).'');

			$_tmp_data[$data['type']]['price']	= (int)(($emoney * ($data['sale_ratio_unit'] * $data['ea']) / 100).'');
			$_tmp_data[$data['type']]['unit']	= (int)(($_tmp_data[$data['type']]['price'] / $data['ea']).'');
			$_tmp_data[$data['type']]['ea']		= $data['ea'];
			$_tmp_data[$data['type']]['rest']	= $_tmp_data[$data['type']]['price'] % $data['ea'];		//짜투리
			$_tmp_data['sum']					+= $_tmp_data[$data['type']]['price'];					//부담율에 따른 부담금

			$tmp_emoney_total += (int) $tmp_emoney*$data['ea'];
			$data[$field.'_sale_unit_real']		= $tmp_emoney;
			$data[$field.'_sale_unit']			= (int) $tmp_emoney;
			$data[$field.'_sale_total']			= (int) $tmp_emoney*$data['ea'];
			$data[$field.'_sale_rest']			= $_tmp_data[$data['type']]['rest'];	//짜투리
			$set_ratio_array[$k] = $data;
		}
		//step3 짜투리는 첫번째(큰금액)에 몰아주기
		//$tmp_emoney_rest =  $emoney-$tmp_emoney_total;//총사용금액-개당합친금액=짜투리

		// 전체에 대한 짜투리
		$this->_tmp_emoney_rest[$field] = $emoney - $_tmp_data['sum'];

		return $set_ratio_array;
	}

	/*
	 * 주문환불 적립금,이머니 사용시 상품옵션/추가옵션/배송비 비율별로 나눔
	 * @param
	 * @
	*/
	public function update_ratio_emoney_cash_refund($order_seq, $refund_code, $data_order, $data_refund, $data_refund_item)
	{
		$data_tmp_order			= $this->get_act_tmp_total($order_seq);
		if($data_tmp_order<=0) return;

		/**if(!$data_refund)		$data_refund 		= $this->get_accountall_refund($refund_code);
		if(!$data_refund_item)	$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);
		if(!$data_order)		$data_order			= $this->get_accountall_order($order_seq);
		**/

		//주문금액별/비율 및 단가 계산
		$total = 0;
		$refund_set_order_price_ratio_ar = $this->refund_set_order_price_ratio($data_order, $data_refund, $data_refund_item);
		//debug_var($refund_set_order_price_ratio_ar);
		$total	= $refund_set_order_price_ratio_ar['total'];
		$set_ratio_array	= $refund_set_order_price_ratio_ar['set_ratio_array'];

		if($data_refund['refund_emoney']>0){
			$set_ratio_array = $this->refund_calculate_promotion_unit($data_refund['refund_emoney'],$total,$set_ratio_array,'emoney');
		}

		if($data_refund['refund_cash']>0){
			$set_ratio_array = $this->refund_calculate_promotion_unit($data_refund['refund_cash'],$total,$set_ratio_array,'cash');
		}
		
		//debug_var($set_ratio_array);

		foreach($set_ratio_array as $data){
			$arr_set_query = $bind = array();
			if($data['type']=='option' || $data['type']=='suboption'){
				if($data_order['emoney']>0){
					$arr_set_query[]	= "emoney_sale_unit=?";
					$arr_set_query[]	= "emoney_sale_rest=?";
					$bind[]				= (int)$data['emoney_sale_unit'];
					$bind[]				= (int)$data['emoney_sale_rest'];
				}
				if($data_order['cash']>0){
					$arr_set_query[]	= "cash_sale_unit=?";
					$arr_set_query[]	= "cash_sale_rest=?";
					$bind[]				= (int)$data['cash_sale_unit'];
					$bind[]				= (int)$data['cash_sale_rest'];
				}
				$bind[]    = $data['seq'];
				$updateCol = implode(',',$arr_set_query);
				if($updateCol) $query = "update `fm_order_refund_item` set {$updateCol} where refund_item_seq=?";
			}else if($data['type']=='shipping'){
				if($data_order['emoney']>0){
					$arr_set_query[]	= "delivery_emoney_sale_unit=?";
					$arr_set_query[]	= "delivery_emoney_sale_rest=?";
					$bind[]				= (int)$data['emoney_sale_unit'];
					$bind[]				= (int)$data['emoney_sale_rest'];
				}
				if($data_order['cash']>0){
					$arr_set_query[]	= "delivery_cash_sale_unit=?";
					$arr_set_query[]	= "delivery_cash_sale_rest=?";
					$bind[]				= (int)$data['cash_sale_unit'];
					$bind[]				= (int)$data['cash_sale_rest'];
				}
				$bind[]    = $data['seq'];
				$updateCol = implode(',',$arr_set_query);
				if($updateCol) $query = "update fm_order_refund set {$updateCol} where refund_seq=?";
			}
			if($query) $this->db->query($query,$bind);
		}
	}

	//주문환불시 주문금액별/비율 및 단가 계산
	public function refund_set_order_price_ratio($data_order, $data_refund, $data_refund_item){
		$total = 0;
		## 하위 주문번호 조회(원주문의 맞교환(재주문)건)
		$all_order_seq = array($data_order['order_seq']);

		if($data_order['top_orign_order_seq']){
			$orign_order_seq			= $data_order['top_orign_order_seq'];
			$new_order_seq				= $data_order['order_seq'];
		}else{
			$orign_order_seq			= $data_order['order_seq'];
			$new_order_seq				= '';
		}

		//step1-1 필수옵/추가옵션 영역
		foreach($data_refund_item as $refund_item){
			$total += (int) $refund_item['refund_goods_price'];
			if($refund_item['opt_type'] == "sub"){
				$param[] = array(
					'type' => 'suboption',
					'order_seq' => $data_order['order_seq'],
					'seq' => $refund_item['refund_item_seq'],
					'unit_price' => $refund_item['refund_goods_price'],
					'ea' => $refund_item['ea']
				);
			}else{
				$param[] = array(
					'type' => 'option',
					'order_seq' => $data_order['order_seq'],
					'seq' => $refund_item['refund_item_seq'],
					'unit_price' => $refund_item['refund_goods_price'],
					'ea' => $refund_item['ea']
				);
			}
		}
		
		$refund_delivery = $data_refund['refund_delivery_price'];
		if($refund_delivery) {
			$total += (int) $refund_delivery;
			$param[] = array(
				'type'			=> 'shipping',
				'order_seq'		=> $data_order['order_seq'],
				'seq'			=> $data_refund['refund_seq'],
				'unit_price'	=> $refund_delivery,
				'ea' => 1
			);
		}

		foreach($param as $k => $data){
			$tmp_unit_price			= (($data['unit_price'] * $data['ea']) / $total) * 100;
			$data['sale_ratio']			= floor( (string) ($tmp_unit_price*100))/100;
			$data['sale_ratio_unit']	= ($tmp_unit_price / $data['ea']);
			$param[$k] = $data;
		}
		@usort($param, 'account_order_sale_desc');//할인금액내림차순

		return array('total'=>$total, 'set_ratio_array'=>$param);
	}


	//주문환불시 캐쉬,적립금 사용액 상품별 계산
	public function refund_calculate_promotion_unit($emoney,$total,$param,$field){
		foreach($param as $k => $data){
			$tmp_emoney = (int) (($emoney * $data['sale_ratio_unit']) / 100);
			$tmp_emoney_total += (int) $tmp_emoney*$data['ea'];
			$data[$field.'_sale_unit_real']		= $tmp_emoney;
			$data[$field.'_sale_unit']			= (int) $tmp_emoney;
			$data[$field.'_sale_total']			= $tmp_emoney*$data['ea'];
			$data[$field.'_sale_rest']		= 0;//짜투리용
			$param[$k] = $data;
		}

		//step3 짜투리는 첫번째(큰금액)에 몰아주기
		$tmp_emoney_rest =  $emoney-$tmp_emoney_total;//총사용금액-개당합친금액=짜투리
		if($tmp_emoney_rest!=0) $param[0][$field.'_sale_rest']		= $tmp_emoney_rest;

		return $param;
	}
	
	
	/**
	**입점사별 전월정산통계테이블 저장
	** cronjob
	**/
	public function account_seller_stats_insert_cronjob($tb_act_ym =null) {
		if(!$tb_act_ym) { // 이전달
			$tb_act_ym	= date("Ym",strtotime('-1 month', $this->iOnTimeStamp));
		}

		// 정산주기별 날짜 기본값 생성 - start
		$accountAllDate = getAccountAllDate(array("s_year"=>substr($tb_act_ym,0,4),"s_month"=>substr($tb_act_ym,4,2)));
		// 정산주기별 날짜 기본값 생성 - end

		$_log= array();
		// 입점사 목록, 오름차순정렬
		$providerList = $this->get_provider_calcu_list('cron');
		$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);
		for($i=0;$i < count($providerList);$i++){
			$providerSeq	= $providerList[$i]['no'];
			$providerId		= $providerList[$i]['provider_id'];
			$providerName	= $providerList[$i]['provider_name'];
			$calcuCount		= $providerList[$i]['calcu_count'];
			$whereOrder		= array();
			$insertItemTmp	= array();
			$strWhereOrder	= "";
			$whereOrder[]	= "caltb.account_type != 'refund'";
			if($providerSeq){
				$whereOrder[] = "caltb.provider_seq = '".$providerSeq."'";
			}

			if($whereOrder){
				$strWhereOrder .= " AND " . implode(' AND ',$whereOrder) ;
			}

			$CalSelectSql	= "SELECT 
								caltb.order_seq,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
										(caltb.exp_ea)
									,0)
								,0) 
								AS refund_ea,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
									(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
									,0)
								,0)
								AS refund_price,
								IFNULL(
									if( (caltb.account_type in ('refund','rollback','after_refund')), 
									( 
									 (IFNULL(caltb.salescost_event,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_rest,0))
									 +
									 (IFNULL(caltb.salescost_multi,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_rest,0))
									 +
									 (IFNULL(caltb.salescost_coupon,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_coupon_rest,0))
									 +
									 (IFNULL(caltb.salescost_code,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_rest,0))
									 +
									 (IFNULL(caltb.salescost_member,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_rest,0))
									)
									,0)
								,0) 
								AS refund_sales_admin_total,
								IFNULL(
									if( (caltb.account_type in ('refund','rollback','after_refund')), 
									( 
									 (IFNULL(caltb.salescost_event_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_multi_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_coupon_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_coupon_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_code_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_member_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_provider_rest,0))
									)
									,0)
								,0)
								AS refund_sales_provider_total,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(
											case caltb.order_referer
												when 'storefarm' then IFNULL(((caltb.api_pg_sale_price - caltb.api_pg_support_price) + caltb.api_pg_support_price),0)
												when 'open11st' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
												when 'coupang' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
											else 0
											end
										)
									,0)
								,0)
								AS refund_pg_sale_price,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.cash_sale_unit,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.cash_sale_rest,0))
									,0)
								,0)
								AS refund_cash_use,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.commission_price_rest,0))
									,0)
								,0)
								AS refund_commission_price,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
									,0)
								,0)
								AS refund_feeprice,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
										(caltb.exp_ea)
									,0)
								,0)
								AS ea,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund'), 
									(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
									,0)
								,0)
								AS price,
								IFNULL(
									if( (caltb.account_type not in ('refund','rollback','after_refund')), 
									( 
									 (IFNULL(caltb.salescost_event,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_rest,0))
									 +
									 (IFNULL(caltb.salescost_multi,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_rest,0))
									 +
									 (IFNULL(caltb.salescost_coupon,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_coupon_rest,0))
									 +
									 (IFNULL(caltb.salescost_code,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_rest,0))
									 +
									 (IFNULL(caltb.salescost_member,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_rest,0))
									)
									,0)
								,0)
								AS sales_admin_total,
								IFNULL(
									if( (caltb.account_type not in ('refund','rollback','after_refund')), 
									( 
									 (IFNULL(caltb.salescost_event_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_multi_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_coupon_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_coupon_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_code_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_member_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_provider_rest,0))
									)
									,0)
								,0)
								AS sales_provider_total,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund'), 
										(
											case caltb.order_referer
												when 'storefarm' then IFNULL(((caltb.api_pg_sale_price - caltb.api_pg_support_price) + caltb.api_pg_support_price),0)
												when 'open11st' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
												when 'coupang' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
											else 0
											end
										)
									,0)
								,0)
								AS pg_sale_price,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.cash_sale_unit,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.cash_sale_rest,0))
									,0)
								,0)
								AS cash_use,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.commission_price_rest,0))
									,0)
								,0)
								AS commission_price,
								IFNULL(
									if( caltb.account_type not  in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
									,0)
								,0)
								AS feeprice,
								commission_type,
								confirm_date
								";
			$CalFromSql		= " FROM  {$caltableck} caltb ";
			$CalWhereSql	= "WHERE caltb.status in ('complete','carryover') and caltb.exp_ea > 0 and (provider_seq!=1 or shipping_provider_seq !=1) ".$strWhereOrder;
			$CalGroupSql	= " ";
			$CalOrderSql	= " ORDER BY caltb.deposit_date asc,caltb.status desc ";

			$CalSql			= $CalSelectSql . $CalFromSql . $CalWhereSql . $CalGroupSql . $CalOrderSql;

			$query = $this->db->query($CalSql);
			if($query && $query->num_rows()) {
				// 초기화

				$insertItemTmp = array();
				$insertItemTmp['acount1'][0]['sum_ea']					= 0;
				$insertItemTmp['acount1'][0]['sum_price']				= 0;
				$insertItemTmp['acount1'][0]['sum_commission_price']	= 0;
				$insertItemTmp['acount1'][0]['sum_feeprice']			= 0;
				$insertItemTmp['acount1'][0]['sum_sales_price']		    = 0;

				$insertItemTmp['acount2'][0]['sum_ea']					= 0;
				$insertItemTmp['acount2'][0]['sum_price']				= 0;
				$insertItemTmp['acount2'][0]['sum_commission_price']	= 0;
				$insertItemTmp['acount2'][0]['sum_feeprice']			= 0;
				$insertItemTmp['acount2'][0]['sum_sales_price']		    = 0;
				$insertItemTmp['acount2'][1]['sum_ea']					= 0;
				$insertItemTmp['acount2'][1]['sum_price']				= 0;
				$insertItemTmp['acount2'][1]['sum_commission_price']	= 0;
				$insertItemTmp['acount2'][1]['sum_feeprice']			= 0;
				$insertItemTmp['acount2'][1]['sum_sales_price']		    = 0;

				$insertItemTmp['acount4'][0]['sum_ea']					= 0;
				$insertItemTmp['acount4'][0]['sum_price']				= 0;
				$insertItemTmp['acount4'][0]['sum_commission_price']	= 0;
				$insertItemTmp['acount4'][0]['sum_feeprice']			= 0;
				$insertItemTmp['acount4'][0]['sum_sales_price']		    = 0;
				$insertItemTmp['acount4'][1]['sum_ea']					= 0;
				$insertItemTmp['acount4'][1]['sum_price']				= 0;
				$insertItemTmp['acount4'][1]['sum_commission_price']	= 0;
				$insertItemTmp['acount4'][1]['sum_feeprice']			= 0;
				$insertItemTmp['acount4'][1]['sum_sales_price']		    = 0;
				$insertItemTmp['acount4'][2]['sum_ea']					= 0;
				$insertItemTmp['acount4'][2]['sum_price']				= 0;
				$insertItemTmp['acount4'][2]['sum_commission_price']	= 0;
				$insertItemTmp['acount4'][2]['sum_feeprice']			= 0;
				$insertItemTmp['acount4'][2]['sum_sales_price']		    = 0;
				$insertItemTmp['acount4'][3]['sum_ea']					= 0;
				$insertItemTmp['acount4'][3]['sum_price']				= 0;
				$insertItemTmp['acount4'][3]['sum_commission_price']	= 0;
				$insertItemTmp['acount4'][3]['sum_feeprice']			= 0;
				$insertItemTmp['acount4'][3]['sum_sales_price']		    = 0;

				foreach($query->result_array() as $data){
					if($data['ea'] == 0 && $data['price'] > 0){
						$data['ea'] = 1;
					}
					if($data['refund_ea'] == 0 && $data['refund_price'] > 0){
						$data['refund_ea'] = 1;
					}

					//sales_admin_total 본사할인
					//pg_sale_price 제휴사할인
					//sales_provider_total 입점사할인

					// 정산 : 수수료 방식
					if($data['commission_type'] == "SACO" || $data['commission_type'] == ""){
						$data['refund_price']	= $data['refund_price'] - $data['refund_sales_provider_total'] - $data['refund_pg_sale_price'];
						$data['price']			= $data['price'] - $data['sales_provider_total'] - $data['pg_sale_price'];	// 정산대상금액(할인판매가-입점사 할인-제휴사할인)

						//2019430 pjm
						$data['feeprice']			= round($data['feeprice']);					//수수료액(소숫점 반올림)
						$data['commission_price']	= $data['price'] - $data['feeprice'];		//정산금액 : 정산대상금액 - 수수료액	
					}else{
						$data['refund_price']	= $data['refund_price'] - $data['refund_sales_admin_total'] - $data['refund_sales_provider_total'] - $data['refund_pg_sale_price'] + $data['refund_cash_use'];
						$data['price']			= $data['price'] - $data['sales_admin_total'] - $data['sales_provider_total'] - $data['pg_sale_price'] + $data['cash_use'];
					}


					// 정산 주기별 금액 누적
					switch($calcuCount){
						case 2:
							if( ($data['confirm_date'] >= $accountAllDate['cal2'][0]['start']) && ($data['confirm_date'] <= $accountAllDate['cal2'][0]['end']) ){
								$insertItemTmp['acount2'][0]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount2'][0]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount2'][0]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount2'][0]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount2'][0]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}elseif( ($data['confirm_date'] >= $accountAllDate['cal2'][1]['start']) && ($data['confirm_date'] <= $accountAllDate['cal2'][1]['end']) ){
								$insertItemTmp['acount2'][1]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount2'][1]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount2'][1]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount2'][1]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount2'][1]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}
						break;
						case 4:
							if( ($data['confirm_date'] >= $accountAllDate['cal4'][0]['start']) && ($data['confirm_date'] <= $accountAllDate['cal4'][0]['end']) ){
								$insertItemTmp['acount4'][0]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount4'][0]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount4'][0]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount4'][0]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount4'][0]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}elseif( ($data['confirm_date'] >= $accountAllDate['cal4'][1]['start']) && ($data['confirm_date'] <= $accountAllDate['cal4'][1]['end']) ){
								$insertItemTmp['acount4'][1]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount4'][1]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount4'][1]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount4'][1]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount4'][1]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}elseif( ($data['confirm_date'] >= $accountAllDate['cal4'][2]['start']) && ($data['confirm_date'] <= $accountAllDate['cal4'][2]['end']) ){
								$insertItemTmp['acount4'][2]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount4'][2]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount4'][2]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount4'][2]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount4'][2]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}elseif( ($data['confirm_date'] >= $accountAllDate['cal4'][3]['start']) && ($data['confirm_date'] <= $accountAllDate['cal4'][3]['end']) ){
								$insertItemTmp['acount4'][3]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount4'][3]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount4'][3]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount4'][3]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount4'][3]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}
						break;
						default:
							$insertItemTmp['acount1'][0]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
							$insertItemTmp['acount1'][0]['sum_price']				+= ($data['price']				- $data['refund_price']);
							$insertItemTmp['acount1'][0]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
							$insertItemTmp['acount1'][0]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
							$insertItemTmp['acount1'][0]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
						break;
					}
				}

				foreach($insertItemTmp['acount'.$calcuCount] as $key => $val){

					$insertItem							= array();
					$insertItem['provider_seq']			= $providerSeq;
					$insertItem['acc_date']				= $tb_act_ym;
					$insertItem['sum_ea']				= $val['sum_ea'];
					$insertItem['sum_price']			= $val['sum_price'];
					$insertItem['sum_commission_price']	= $val['sum_commission_price'];
					$insertItem['sum_feeprice']			= $val['sum_feeprice'];
					$insertItem['sum_sales_price']		= $val['sum_sales_price'];
					$insertItem['period_type']			= $calcuCount;
					$insertItem['period_count']			= $key;
					$insertItem['regist_date']			= date('Y-m-d H:i:s', $this->iOnTimeStamp);

					$result = $this->db->insert($this->tb_seller_stats, $insertItem);
				}
			}
		}

		/*
		echo "<prE>";
		print_r($_log);
		echo "</prE>";
		*/

	}
/* 구버전 백업
	public function account_seller_stats_insert_cronjob($tb_act_ym =null) {
		if(!$tb_act_ym) {
			$tb_act_ym	= date("Ym",strtotime('-1 month', $this->iOnTimeStamp));  
		}
		$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);
		$CalSelectSql	= "SELECT 
							p.provider_seq as provider_seq, p.provider_id as provider_id, p.provider_name as provider_name, 
							sum( 
								IFNULL(
									if( caltb.account_type in ('refund','rollback') and caltb.order_goods_kind!='shipping',
										(caltb.ea)
									,0)
								,0) 
							)
								AS refund_sum_ea,
							sum( 
								IFNULL(
									if( caltb.account_type in ('refund','rollback'), 
									(IFNULL(caltb.price,0) * IFNULL(caltb.ea,0))
									,0)
								,0)
							) 
								AS refund_sum_price,
							sum( 
								IFNULL(
									if( (caltb.account_type in ('refund','rollback')), 
									( 
									 (IFNULL(caltb.salescost_event,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_event_rest,0))
									 +
									 (IFNULL(caltb.salescost_code,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_code_rest,0))
									 +
									 (IFNULL(caltb.salescost_member,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_member_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_fblike_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_mobile_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_referer_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_enuri_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_emoney_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_cash_rest,0))
									)
									,0)
								,0)
							) 
								AS refund_sum_sales_admin_total,
							sum( 
								IFNULL(
									if( caltb.account_type in ('refund','rollback'), 
										(IFNULL(caltb.commission_price,0) * IFNULL(caltb.ea,0) + IFNULL(caltb.commission_price_rest,0))
									,0)
								,0)
							)
								AS refund_sum_commission_price,
								
							sum( 
								IFNULL(
									if( caltb.account_type in ('refund','rollback'), 
										(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
									,0)
								,0)
							)
								AS refund_sum_feeprice,
							sum( 
								IFNULL(
									if( caltb.account_type not in ('refund','rollback') and caltb.order_goods_kind!='shipping',
										(caltb.ea)
									,0)
								,0) 
							)
								AS sum_ea,
							sum( 
								IFNULL(
									if( caltb.account_type not in ('refund','rollback'), 
									(IFNULL(caltb.price,0) * IFNULL(caltb.ea,0))
									,0)
								,0)
							) 
								AS sum_price,
							sum( 
								IFNULL(
									if( (caltb.account_type not in ('refund','rollback')), 
									( 
									 (IFNULL(caltb.salescost_event,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_event_rest,0))
									 +
									 (IFNULL(caltb.salescost_code,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_code_rest,0))
									 +
									 (IFNULL(caltb.salescost_member,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_member_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_fblike_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_mobile_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_referer_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_enuri_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_emoney_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash,0)*IFNULL(caltb.ea,0)+IFNULL(caltb.salescost_cash_rest,0))
									)
									,0)
								,0)
							) 
								AS sum_sales_admin_total,
							sum( 
								IFNULL(
									if( caltb.account_type not in ('refund','rollback'), 
										(IFNULL(caltb.commission_price,0) * IFNULL(caltb.ea,0) + IFNULL(caltb.commission_price_rest,0))
									,0)
								,0)
							)
								AS sum_commission_price,
							sum( 
								IFNULL(
									if( caltb.account_type not  in ('refund','rollback'), 
										(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
									,0)
								,0)
							)
								AS sum_feeprice
							";
		$CalFromSql		= " FROM  {$caltableck} caltb LEFT JOIN fm_provider as p on caltb.shipping_provider_seq = p.provider_seq ";
		$CalWhereSql	= "WHERE caltb.status in ('complete','carryover') ".$str_where_order;
		$CalGroupSql	= " group by caltb.provider_seq ";
		$CalOrderSql	= " ORDER BY caltb.deposit_date asc,caltb.status desc ";
		
		$CalSql			= $CalSelectSql . $CalFromSql . $CalWhereSql . $CalGroupSql . $CalOrderSql;
		$query = $this->db->query($CalSql);
		if($query) {
			foreach($query->result_array() as $data){
				$insertItem['provider_seq']			= $data['provider_seq'];
				$insertItem['acc_date']				= $tb_act_ym;
				$insertItem['sum_ea']				= ($data['sum_ea']				- $data['refund_sum_ea']);
				$insertItem['sum_price']			= ($data['sum_price']			- $data['refund_sum_price']);
				$insertItem['sum_commission_price']	= ($data['sum_commission_price']+ $data['sum_sales_admin_total']	- $data['refund_sum_commission_price']- $data['refund_sum_sales_admin_total']);
				$insertItem['sum_feeprice']			= ($data['sum_feeprice']			- $data['refund_sum_feeprice']);
				$insertItem['sum_sales_price']		= ($data['sum_sales_price']		- $data['refund_sum_sales_price']);
				$insertItem['period_type']			= 1;
				$insertItem['regist_date']			= date('Y-m-d H:i:s', $this->iOnTimeStamp);

				$result = $this->db->insert($this->tb_seller_stats, $insertItem);
			}
		}
	}
*/

	/**
	* 미정산데이타전체
	* 매월 1일 전월통합정산데이타에 미정산-전월 생성
	**/
	public function create_carryover_account_all_cronjob($copy_table, $target_table, $status, $tb_act_y_m) {
		$not_filds	= array("seq");//예외필드

		$copy_fields = $this->db->list_fields($copy_table);
		$target_fields = $this->db->list_fields($target_table);
		
		unset($target_record,$copy_record);
		foreach($copy_fields as $k=>$copy_field) {
			if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크
			foreach($target_fields as $target_field) {
			if( in_array($target_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				if ($target_field == $copy_field ) {
					$copy_record[$k]		= $copy_field;
					$target_record[$k]		= $copy_field;
				}else{
					if($copy_field == "status"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $status;
					}
				}
			}
		}

		if( count($copy_record) == count($target_record) ) {
			$sql = "INSERT IGNORE INTO `{$copy_table}` ";//INSERT  REPLACE
			$copy_bind_sql = implode(", ",$copy_record);
			$sql .= " ({$copy_bind_sql}) ";
			$sql .= " select ";
			foreach($copy_record as $k=>$copy_field) {
				if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				
				if( $copy_field == "status" ) {
					$target_bind_sql .= ($target_bind_sql)?", '".$target_record[$k]."'":" '".$target_record[$k]."'";
				}else{
					$target_bind_sql .= ($target_bind_sql)?", ".$target_record[$k]:$target_record[$k];
				}
			}
			$sql .= " {$target_bind_sql} ";
			$sql .= " from {$target_table} where status = 'carryover' and SUBSTRING(deposit_date,1,7) <= '".$tb_act_y_m."'  order by seq asc";//전달은제외(차월노출됨)
			
			$this->db->db_debug = false;//쿼리 구문 오류문제로 처리
			$this->db->query($sql);
		}
	}
	
	public function account_seller_stats_del($date,$provider_seq=''){
		$query = "delete from {$this->tb_seller_stats} where acc_date = '{$date}'";
		if($provider_seq) $query .= " and provider_seq='".$provider_seq."'";
		$this->db->query($query);
	}

	
	/**
	**전월 정산데이타 생성
	* 1. 미정산데이타 정산데이타로 생성 : not-carryover 미정산-전월
	* 2. 매출데이타중 차월데이타를 정산데이타로 생성 : overdraw 미정산-당월
	** cronjob
	**/
	public function account_carryover_overdraw_insert_cronjob($tb_act_ym =null,$tb_act_y_m=null) {
		if(!$tb_act_ym) {
			$tb_act_ym	= date("Ym",strtotime('-1 month', $this->iOnTimeStamp));
		}
		if(!$tb_act_y_m) {
			$tb_act_y_m	= date("Y-m",strtotime('-1 month', $this->iOnTimeStamp));
		}

		//현재 미정산데이타전체를 정산데이타 '미정산-전월' 생성
		$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);
		$this->create_carryover_account_all_cronjob($caltableck, $this->tb_act_cal_sal, "not-carryover", $tb_act_y_m);

	}
	
	/**
	**전월 정산 미대상 삭제
	* 1. 전월 미정산 데이터 삭제
	** cronjob
	**/
	public function account_migration_sales_del($date) {
		$query = "delete from {$this->tb_act_cal_sal} where regist_date < '{$date}';";
		$this->db->query($query);
	}
	
	/**
	**전월 정산 미대상 삭제
	* 1. 전월 미정산 데이터 삭제
	** cronjob
	**/
	public function account_carryover_del_sales() {
		// 매출 데이터를 삭제하지 않음 by hed
		// $query = "delete from {$this->tb_act_cal_sal} where exp_ea = 0 and ac_ea = 0;";
		// $this->db->query($query);
	}
	
	/**
	* 판매마켓 주문정보 가져오기
	* coupang market : 
	* open11st market : 
	* naverstorefarm market :  
	**/
	public function get_marekt_order_info($market, $orderRow){

		$countSql	= "
			SELECT	market_order_no,market_delivery_no,market_order_seq,market_product_code,market_option_code,
					market_discount_amount,seller_discount_amount,shipping_cost,other_info
			FROM	{$this->tb_market_orders}
			WHERE	fm_order_seq			= '{$orderRow['fm_order_seq']}'
				AND	fm_item_seq				= '{$orderRow['fm_item_seq']}'
				AND	market					= '{$market}'";
		if($orderRow['fm_item_suboption_seq']){
			$countSql	.= "AND	fm_item_suboption_seq	= '{$orderRow['fm_item_suboption_seq']}'";
		}else{
			$countSql	.= "AND	fm_item_option_seq		= '{$orderRow['fm_item_option_seq']}'";
		}
		$countSql	.= " limit 1";
		$result		= $this->db->query($countSql);
		if($result) $resultdata	= $result->row_array();
		return $resultdata;
	}
	
	// 출고완료 정보 반환
	public function get_items_export_complete_code_group($order_seq,$order_item_seq,$order_item_option_seq='',$order_item_suboption_seq='',$export_code=''){

		if(preg_match('/^B/', $export_code)){
			$join_on = "(a.bundle_export_code=b.bundle_export_code)";
		}else{
			$join_on = "(a.export_code=b.export_code)";
		}
		if($order_item_option_seq) $addwhere = " and b.option_seq={$order_item_option_seq}";
		if($order_item_suboption_seq) $addwhere = " and b.suboption_seq={$order_item_option_seq}";

		$query = $this->db->query("
			select a.*
			from fm_goods_export as a
			inner join fm_goods_export_item b on ".$join_on."
			where a.order_seq=? and b.item_seq=? ".$addwhere."
			group by a.export_code
		",array($order_seq,$order_item_seq));
		$result = $query->result_array();
		return $result;
	}
	
	public function get_account_refund_item_data($refund_code,$item_seq,$item_option_seq, $seq_type='option_seq'){
		$query = "select refund_item_seq from fm_order_refund_item where refund_code=? and item_seq=? and {$seq_type}=?";
		$values = array($refund_code,$item_seq,$item_option_seq);
		$query = $this->db->query($query,$values);
		$result = $query->row_array();

		return $result;
	}

	public function get_accountall_refund($refund_code)
	{
		$query = "select * from fm_order_refund where refund_code=? limit 1";
		$query = $this->db->query($query,array($refund_code));
		list($result) = $query -> result_array();
		return $result;
	}
	
	public function get_accountall_export_item($export_code)
	{

		$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';

			$query1 = "
			SELECT
			exp.confirm_date,
			exp.socialcp_confirm_date,
			expitm.export_code,
			'opt' opt_type,
			opt.item_seq item_seq,
			opt.item_option_seq option_seq,
			opt.item_option_seq item_option_seq,
			item.order_seq,
			item.shipping_seq,
			item.goods_seq
			FROM
			fm_goods_export exp,fm_goods_export_item expitm,fm_order_item_option opt,fm_order_item item
			WHERE
			expitm.option_seq is not null
			AND exp.export_code = expitm.export_code
			AND expitm.option_seq = opt.item_option_seq
			AND opt.item_seq = item.item_seq
			AND expitm.{$export_field} = ?
			";
			$query2 = "
			SELECT
			exp.confirm_date,
			exp.socialcp_confirm_date,
			expitm.export_code,
			'sub' opt_type,
			sub.item_seq item_seq,
			sub.item_suboption_seq option_seq,
			sub.item_option_seq item_option_seq,
			item.order_seq,
			item.shipping_seq,
			item.goods_seq
			FROM
			fm_goods_export exp,fm_goods_export_item expitm,fm_order_item_suboption sub,fm_order_item item
			WHERE
			expitm.suboption_seq is not null
			AND exp.export_code = expitm.export_code
			AND expitm.suboption_seq = sub.item_suboption_seq
			AND sub.item_seq = item.item_seq
			AND expitm.{$export_field} = ?
			";

		$query = "(".$query1.") union (".$query2.") order by export_code, shipping_seq, item_seq asc,item_option_seq,opt_type='opt' desc";

		$query = $this->db->query($query,array($export_code,$export_code));
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	// 주문 옵션 가져오기
	public function get_account_item_option($order_seq){
		$query = "select o.* from fm_order_item_option o where  o.order_seq=?";
		$query = $this->db->query($query,array($order_seq));
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}
	// 주문 서브옵션 가져오기
	public function get_account_item_suboption($order_seq){
		$query = "select o.* from fm_order_item_suboption o where  o.order_seq=?";
		$query = $this->db->query($query,array($order_seq));
		foreach($query->result_array() as $data){
			$result[] = $data;
		}
		return $result;
	}

	/* 입점사별 배송비 가져오기 */
	public function get_account_order_shipping($order_seq,$provider_seq=null,$shipping_seq=null){
		$query = "select s.*  from fm_order_shipping s  where  s.order_seq=?";
		if($provider_seq) $query .= " and s.provider_seq = '{$provider_seq}'";
		if($shipping_seq) $query .= " and s.shipping_seq = '{$shipping_seq}'";
		$query = $this->db->query($query,array($order_seq));
		foreach($query->result_array() as $data){
			$result[$data['shipping_group']] = $data;
		}
		return $result;
	}


	/**
	* 정산리스트 > 매출/정산 한꺼번에 보기 최종화면
	**/
	public function get_account_all_catalog_query( $_PARAM ) {

		if($_PARAM['acc_table']) $tb_act_ym	=	$_PARAM['acc_table'];
		if(!$tb_act_ym) $tb_act_ym	=	date('Ym', $this->iOnTimeStamp);

		// 정산 마감일 가져오기 :: 2018-07-09 lkh
		$settingArr['year'] = $_PARAM['s_year'];
		$settingArr['month'] = $_PARAM['s_month'];
		$accountallConfirmSettingTmp = $this->get_account_setting("month",$settingArr);
		$accountConfirmDate = $accountallConfirmSettingTmp['accountall_confirm'];

        // 정산 마감일 구하기 by hed
        $account_onfirm_end_date = date('Ymd', $this->iOnTimeStamp);
        $yearmonth = $settingArr['year']."-".$settingArr['month'];
        $accountConfirm = $this->accountallmodel->get_account_confirm(trim($settingArr['year']."-".$settingArr['month']));
        if($accountConfirm['confirm_end_date']){
            $tmp = explode(' ',$accountConfirm['confirm_end_date']);
            $account_onfirm_end_date = str_replace('-','', $tmp[0]);
        }else{
            $account_onfirm_end_date = date("Ym", strtotime($yearmonth."+1 month")).sprintf("%02d",$accountallConfirmSettingTmp['accountall_confirm']);
        }
        
		$CalSalestableck= $this->tb_act_cal_sal;

		$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);

		// 주문일
		$date_field = $_PARAM['date_field'] ? $_PARAM['date_field'] : 'regist_date';
		if($_PARAM['regist_date'][0]){
			$where_order[] = " ( (account_type in ('order','exchange') and ".$date_field." >= '".$_PARAM['regist_date'][0]." 00:00:00') or (account_type not in ('order','exchange') and regist_date >= '".$_PARAM['regist_date'][0]." 00:00:00') ) ";
		}
		if($_PARAM['regist_date'][1]){
			$where_order[] = " ( (account_type in ('order','exchange') and ".$date_field." <= '".$_PARAM['regist_date'][1]." 24:00:00') or (account_type not in ('order','exchange')  and regist_date <= '".$_PARAM['regist_date'][1]." 24:00:00') ) ";
		}

		if($_PARAM['search_seq'] && !$_PARAM['ajaxCall']){
			if(strstr($_PARAM['search_seq'],",")){
				$order_seq_ar = explode(",",$_PARAM['search_seq']);
				
				foreach($order_seq_ar as $key => $data){
					$seq_arr[] = " ( k.order_seq = '".$data."' OR ifnull(k.linkage_order_id,'') = '" . $data . "' OR ifnull(k.linkage_mall_order_id,'') = '" . $data . "' OR ifnull(k.pg_ordernum,'') = '" . $data . "' OR k.refund_code = '".$data."' )";
				}
				$where_order[] = "(".implode(' OR ',$seq_arr).")";
			}else{
				$where_order[] = " ( k.order_seq = '".$_PARAM['search_seq']."' OR ifnull(k.linkage_order_id,'') = '" . $_PARAM['search_seq'] . "'  OR ifnull(k.linkage_mall_order_id,'') = '" . $_PARAM['search_seq'] . "'  OR ifnull(k.pg_ordernum,'') = '" . $_PARAM['search_seq'] . "' OR k.refund_code = '".$_PARAM['search_seq']."' ) ";
			}
		}
		//입점사검색
		if($_PARAM['provider_seq'] && $_PARAM['provider_seq'] != 'all' ){
			if($_PARAM['provider_seq'] == 'provider_all'){
				$where_order[] = "k.provider_seq != '1'";
			}else{
				$where_order[] = "k.provider_seq = '".$this->db->escape_str($_PARAM['provider_seq'])."'";
			}
		}
		
		//입점사 리스트 검색
		if (isset($_PARAM['provider_seq_list']) && count($_PARAM['provider_seq_list']) > 0) {
			$where_order[] = 'k.provider_seq IN (' . implode(',', $_PARAM['provider_seq_list']) . ')';
		}

		//주문구분 
		if($_PARAM['account_type']  && $_PARAM['account_type'] != 'all' ){
			if( $_PARAM['account_type'] == "not_refund"){
				$where_order[] = "k.account_type != 'refund'";
			}else{
				$where_order[] = "k.account_type = '".$_PARAM['account_type']."'";
			}
		}

		// 이월/당월 @201904 pjm 추가
		if($_PARAM['s_month_gubun']  && $_PARAM['s_month_gubun'] != 'all' ){
			if($_PARAM['s_month_gubun'] == "before"){
				$where_order[] = "k.deposit_date < '".substr($tb_act_ym,0,4)."-".substr($tb_act_ym,4,2)."-01 00:00:00'";
			}else{
				$where_order[] = "k.deposit_date like '".substr($tb_act_ym,0,4)."-".substr($tb_act_ym,4,2)."%'";
			}
		}
		//주문건만(매출)
		if($_PARAM['s_month_order'] == 'y'){
			$where_order[] = "k.account_type not in ('refund','rollback','after_refund') AND k.status in ('overdraw','complete') ";
		}
		//구매확정건
		if($_PARAM['s_confirm_order'] == 'y'){
			$where_order[] = "(ifnull(k.confirm_date,'') != '' && k.confirm_date != '0000-00-00 00:00:00') and k.ac_ea=0 and k.account_type != 'refund'";
		}
		//환불건
		if($_PARAM['s_refund_order'] == 'y'){
			$where_order[] = "ifnull(k.refund_code,'') != '' and k.account_type='refund'";
		}
		//배송비만
		if($_PARAM['s_shipping_order'] == 'y'){
			$where_order[] = "k.order_type='shipping'";
		}

		//배송비만
		if($_GET['debug']){
			$_field = "k.*,(case when k.account_type='order' then exp.buy_confirm else '' end) as buy_confirm,exp.confirm_date as exp_confirm_date,ref_item.refund_code as refitem_refund_code";
			if($_PARAM['s_confirm_err'] == 'y'){
				$where_order[] = "(exp.buy_confirm != '' and ifnull(k.confirm_date,'') = '' and exp.confirm_date like '".substr($tb_act_ym,0,4)."-".substr($tb_act_ym,4,2)."%')";
			}
		}else{
			$_field = "k.*";
		}

		if($_PARAM['order_referer'] ){
			foreach($_PARAM['order_referer'] as $order_referer){
				if($order_referer == "all") break;
				if($order_referer == "pg"){
					$pgArr = available_pg(['nation' => 'all']);
					$where_referer[] = "order_referer in ('".implode("','",$pgArr)."')";
				}elseif($order_referer == "shoplinker"){	// 샵링커의 경우 referer 정보(샵구분키)가 모두 API로 시작함
					$where_referer[] = "order_referer like 'API%' ";
				}else{
					$where_referer[] = "order_referer = '".$order_referer."'";
				}
			}
		}

		//$str_where_order = " WHERE 1=1 ";
		if($_GET['debug']){
			$str_where_order = " left join fm_goods_export_item as exp_item on exp_item.item_seq=k.item_seq 
				and (
					(k.order_type='option' and exp_item.option_seq=k.order_form_seq)
					or (k.order_type='suboption' and exp_item.suboption_seq=k.order_form_seq)
				)
			 left join fm_goods_export as exp on exp.order_seq=k.order_seq and exp.export_code=exp_item.export_code and exp.buy_confirm!='none'
						 and exp.confirm_date like '".substr($tb_act_ym,0,4)."-".substr($tb_act_ym,4,2)."%'
			 left join fm_order_refund_item as ref_item on ref_item.item_seq=exp_item.item_seq 
				and 
					(k.order_type='option' and ref_item.option_seq=k.order_form_seq)
					or (k.order_type='suboption' and ref_item.suboption_seq=k.order_form_seq)
			";
		}

		if($where_order){
			$str_where_order .= " WHERE " . implode(' AND ',$where_order) ;
		}
		
		if($where_referer){
			if($str_where_order){
				$str_where_order .= " AND (".implode(' OR ',$where_referer).")";
			}else{
				$str_where_order .= " WHERE (".implode(' OR ',$where_referer).")";
			}
		}

		/**
		 * 동일한 쿼리를 위한 필드정의
		 * 차후 필드추가시 미정산테이블/월별정산테이블 전체테이블 주의 
				
		**/

		/**
		 * 미정산/통합매출테이블 
		 * 당월에만 노출
		**/
		if(!$this->account_fee_ar['pg']){//결제수수료방식에서는 제외
			// 기존 구성에서 마감전 조회 쿼리와 마감후 조회 쿼리로 변경 by hed
			if($account_onfirm_end_date > date('Ymd', $this->iOnTimeStamp)) {

				//$CalSalesSelectSql	= "SELECT 'cal_sales' as ac_type, ".str_replace("[tbname].","calsalestb.",$selectfild)." ";
				$CalSalesSelectSql	= "SELECT 'cal_sales' as ac_type, calsalestb.* ";
				$CalSalesFromSql		= "FROM  {$CalSalestableck} calsalestb ";
				$CalSalesWhereSql	= "WHERE SUBSTRING(deposit_date,1,7) < '".$yearmonth."' 
					AND status not in ('complete', '')
					";//당월데이타는 통합정산데이타 중복
					// status 가 공백('')으로 입력되는 매출데이터가 있어 제외함
				$CalSalesGroupSql	= "";
				if($_PARAM['orderby']){
					$CalSalesOrderSql	= "ORDER BY calsalestb." . $_PARAM['orderby'] . ' ' .$_PARAM['sort'];
				}else{
					$CalSalesOrderSql	= "ORDER BY calsalestb.deposit_date asc,calsalestb.status desc";
				}
				$CalSalesSql			= $CalSalesSelectSql . $CalSalesFromSql . $CalSalesWhereSql . $CalSalesGroupSql . $CalSalesOrderSql;
			}else{
				//미정산SQL
				//$CalSalesSelectSql	= "SELECT 'cal_sales' as ac_type, ".str_replace("[tbname].","caltb.",$selectfild)." ";
				$CalSalesSelectSql	= "SELECT 'cal_sales' as ac_type, caltb.* ";
				$CalSalesFromSql		= "FROM  {$caltableck} caltb ";
				$CalSalesWhereSql	= "WHERE status ='not-carryover'  ";
				$CalSalesGroupSql	= "";
				if($_PARAM['orderby']){
					$CalSalesOrderSql	= "ORDER BY caltb." . $_PARAM['orderby'] . ' ' .$_PARAM['sort'];
				}else{
					$CalSalesOrderSql	= "ORDER BY caltb.deposit_date asc,caltb.status desc";// limit 20
				}
				$CalSalesSql			= $CalSalesSelectSql . $CalSalesFromSql . $CalSalesWhereSql . $CalSalesGroupSql . $CalSalesOrderSql;
			}
		}

		$sort			= " order by deposit_date asc, seq asc";//order_seq asc, 
		
		$limit_carry		= "";
		$limit_current		= "";
		$carryover_view = true;
		$current_view = true;
		if($_PARAM['total_view'] == '0'){
			$limit_start	= (($_PARAM[$_PARAM['targetmode'].'_page'] - 1) * $_PARAM['perpage']);
			$limit_end		= $_PARAM['perpage'];
			
			if($_PARAM['targetmode'] == 'carry'){
				$limit_carry	= ' limit '.$limit_start.' , '.$limit_end.' ';
				$current_view = false;
			}elseif($_PARAM['targetmode'] == 'current'){
				$limit_current	= ' limit '.$limit_start.' , '.$limit_end.' ';
				$carryover_view = false;
			}
		}
		
		$result_list = array();
		$carryover_list = array();
		$current_list = array();
		$CI->db->queries = array();
		$CI->db->query_times = array();

		if($this->account_fee_ar['pg']){//결제수수료방식에서는 제외 ({$CalSql}) union all ({$SalesSql})
			$query	= "SELECT if( caltb.status='overdraw', 'sales','cal') as ac_type, caltb.* 
						FROM {$caltableck} caltb
						{$str_where_order} 
						ORDER BY caltb.deposit_date asc,caltb.status desc
					";
			$current_result = mysqli_query($this->db->conn_id,$query);
		}else{
			// 이월 데이터와 당월 데이터를 별도로 구하여 합산 by hed
			
			// 이월데이터
			$carryover_query	= "
				SELECT 
					".$_field."
				from 
				(
					(
						{$CalSalesSql}
					) union all (
						SELECT if( caltb.status in ('overdraw'), 'sales','cal') as ac_type, caltb.* 
						FROM {$caltableck} caltb
						WHERE caltb.status in ('carryover')
					)
				)  as k
				{$str_where_order} 
				{$sort}
				{$limit_carry}
			";
			if($carryover_view){
				$carryover_result = mysqli_query($this->db->conn_id,$carryover_query);
			}
			
			// 당월데이터
			$current_query	= "
				SELECT 
					".$_field."
				from 
				(
					SELECT if( caltb.status in ('overdraw'), 'sales','cal') as ac_type, caltb.* 
					FROM {$caltableck} caltb
					WHERE caltb.status not in ('not-carryover', 'carryover')
					ORDER BY caltb.deposit_date asc,caltb.status desc
				)  as k
				{$str_where_order} 
				{$sort}
				{$limit_current}
			";
			if($current_view){
				$current_result = mysqli_query($this->db->conn_id,$current_query);
			}
		}
		
		return array($carryover_result, $current_result);
	}

	/**
	* 정산리스트 > 입점사별 총 정산금액 계산
	**/
	public function get_account_all_seller($provider_seq=1, $acinsdata=array(), $data_provider=array(), $accountAllDate , &$accountAllCount) {
		
		// 비교용 날짜 정리
		$diff_accountAllDate['cal2'][0]['start']		= substr($accountAllDate['cal2'][0]['start'],0,10);
		$diff_accountAllDate['cal2'][0]['end']			= substr($accountAllDate['cal2'][0]['end'],0,10);
		$diff_accountAllDate['cal2'][1]['start']		= substr($accountAllDate['cal2'][1]['start'],0,10);
		$diff_accountAllDate['cal2'][1]['end']			= substr($accountAllDate['cal2'][1]['end'],0,10);
		$diff_accountAllDate['cal4'][0]['start']		= substr($accountAllDate['cal4'][0]['start'],0,10);
		$diff_accountAllDate['cal4'][0]['end']			= substr($accountAllDate['cal4'][0]['end'],0,10);
		$diff_accountAllDate['cal4'][1]['start']		= substr($accountAllDate['cal4'][1]['start'],0,10);
		$diff_accountAllDate['cal4'][1]['end']			= substr($accountAllDate['cal4'][1]['end'],0,10);
		$diff_accountAllDate['cal4'][2]['start']		= substr($accountAllDate['cal4'][2]['start'],0,10);
		$diff_accountAllDate['cal4'][2]['end']			= substr($accountAllDate['cal4'][2]['end'],0,10);
		$diff_accountAllDate['cal4'][3]['start']		= substr($accountAllDate['cal4'][3]['start'],0,10);
		$diff_accountAllDate['cal4'][3]['end']			= substr($accountAllDate['cal4'][3]['end'],0,10);

		if($acinsdata){
			$this->sum_provider_account($provider_seq, $acinsdata, $data_provider, $diff_accountAllDate, $accountAllCount);
		}
	}

	public function sum_provider_account($provider_seq, $_data, $data_provider, $diff_accountAllDate, &$accountAllCount){

		if($_data['provider_seq'] != $provider_seq || !empty($_data['out_ac_acc_status'])) return;
		
		// 금액 감산 여부
		$minus_sale = 1;
		if($_data['minus_sale']=='1'){
			$minus_sale = -1;
		}

		// 날짜 정리
		$out_confirm_date								= '20'.$_data['out_confirm_date'];
		$out_confirm_date								= substr($out_confirm_date, 0 ,10);

		// 정산 주기별 정산금액
		switch($data_provider['calcu_count']){
			case 2:
				if( ($out_confirm_date >= $diff_accountAllDate['cal2'][0]['start']) && ($out_confirm_date <= $diff_accountAllDate['cal2'][0]['end']) ){
					$accountAllCount['account2'][0]							+= $_data['out_commission_price'] * $minus_sale;
					$accountAllCount['account2_sum_total_ac_price'][0]		+= $_data['out_total_ac_price'] * $minus_sale;
					$accountAllCount['account2_sum_feeprice'][0]			+= $_data['out_sales_unit_feeprice'] * $minus_sale;
				}elseif( ($out_confirm_date >= $diff_accountAllDate['cal2'][1]['start']) && ($out_confirm_date <= $diff_accountAllDate['cal2'][1]['end']) ){
					$accountAllCount['account2'][1]							+= $_data['out_commission_price'] * $minus_sale;
					$accountAllCount['account2_sum_total_ac_price'][1]		+= $_data['out_total_ac_price'] * $minus_sale;
					$accountAllCount['account2_sum_feeprice'][1]			+= $_data['out_sales_unit_feeprice'] * $minus_sale;
				}
			break;
			case 4:
				if( ($out_confirm_date >= $diff_accountAllDate['cal4'][0]['start']) && ($out_confirm_date <= $diff_accountAllDate['cal4'][0]['end']) ){
					$accountAllCount['account4'][0]							+= $_data['out_commission_price'] * $minus_sale;
					$accountAllCount['account4_sum_total_ac_price'][0]		+= $_data['out_total_ac_price'] * $minus_sale;
					$accountAllCount['account4_sum_feeprice'][0]			+= $_data['out_sales_unit_feeprice'] * $minus_sale;
				}elseif( ($out_confirm_date >= $diff_accountAllDate['cal4'][1]['start']) && ($out_confirm_date <= $diff_accountAllDate['cal4'][1]['end']) ){
					$accountAllCount['account4'][1]							+= $_data['out_commission_price'] * $minus_sale;
					$accountAllCount['account4_sum_total_ac_price'][1]		+= $_data['out_total_ac_price'] * $minus_sale;
					$accountAllCount['account4_sum_feeprice'][1]			+= $_data['out_sales_unit_feeprice'] * $minus_sale;
				}elseif( ($out_confirm_date >= $diff_accountAllDate['cal4'][2]['start']) && ($out_confirm_date <= $diff_accountAllDate['cal4'][2]['end']) ){
					$accountAllCount['account4'][2]							+= $_data['out_commission_price'] * $minus_sale;
					$accountAllCount['account4_sum_total_ac_price'][2]		+= $_data['out_total_ac_price'] * $minus_sale;
					$accountAllCount['account4_sum_feeprice'][2]			+= $_data['out_sales_unit_feeprice'] * $minus_sale;
				}elseif( ($out_confirm_date >= $diff_accountAllDate['cal4'][3]['start']) && ($out_confirm_date <= $diff_accountAllDate['cal4'][3]['end']) ){
					$accountAllCount['account4'][3]							+= $_data['out_commission_price'] * $minus_sale;
					$accountAllCount['account4_sum_total_ac_price'][3]		+= $_data['out_total_ac_price'] * $minus_sale;
					$accountAllCount['account4_sum_feeprice'][3]			+= $_data['out_sales_unit_feeprice'] * $minus_sale;
				}
			break;
			default:
				$accountAllCount['account1'][0]							+= $_data['out_commission_price'] * $minus_sale;
				$accountAllCount['account1_sum_total_ac_price'][0]		+= $_data['out_total_ac_price'] * $minus_sale;
				$accountAllCount['account1_sum_feeprice'][0]			+= $_data['out_sales_unit_feeprice'] * $minus_sale;
			break;
		}
		
		ksort($accountAllCount['account1']);
		ksort($accountAllCount['account1_sum_total_ac_price']);
		ksort($accountAllCount['account1_sum_feeprice']);
		ksort($accountAllCount['account2']);
		ksort($accountAllCount['account2_sum_total_ac_price']);
		ksort($accountAllCount['account2_sum_feeprice']);
		ksort($accountAllCount['account4']);
		ksort($accountAllCount['account4_sum_total_ac_price']);
		ksort($accountAllCount['account4_sum_feeprice']);
	}


	public function get_account_all_seller_old( $_PARAM, $accountAllDate ) {
		if($_PARAM['acc_table']) $tb_act_ym	=	$_PARAM['acc_table'];
		if(!$tb_act_ym) $tb_act_ym	=	date('Ym', $this->iOnTimeStamp);

		$accountAllCount = array();
		$accountAllCount['account1'][0] = 0;
		$accountAllCount['account2'][0] = 0;
		$accountAllCount['account2'][1] = 0;
		$accountAllCount['account4'][0] = 0;
		$accountAllCount['account4'][1] = 0;
		$accountAllCount['account4'][2] = 0;
		$accountAllCount['account4'][3] = 0;

		/**
		* 입점사정보 한번만 가져오기
		**/
		if($_PARAM['provider_seq']) {
			$data_provider = $this->providermodel->get_provider_one($_PARAM['provider_seq']);
			if(!$data_provider['calcu_count']) $data_provider['calcu_count'] = 1;
			$accountAllCount[$data_provider['provider_id']]['calcu_count'] = $data_provider['calcu_count'];
		}else{
			return $accountAllCount;
		}

		$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);

		$where_order[] = "status in ('complete','carryover')";
		$where_order[] = "account_type not in ('refund','rollback','after_refund')";
		$where_order[] = "exp_ea > 0";
		//입점사검색
		if($_PARAM['provider_seq'] && $_PARAM['provider_seq'] != 'all' ){
			$where_order[] = "(provider_seq = '".$_PARAM['provider_seq']."' OR shipping_provider_seq = '".$_PARAM['provider_seq']."')";
		}

		//$str_where_order = " WHERE 1=1 ";
		if($where_order){
			$str_where_order .= " WHERE " . implode(' AND ',$where_order) ;
		}

		/**
		 * 동일한 쿼리를 위한 필드정의
		 * 차후 필드추가시 미정산테이블/월별정산테이블 전체테이블 주의 
				
		**/

		$query	= "
				SELECT confirm_date, commission_price, exp_ea, commission_price_rest, order_seq
				FROM {$caltableck}
				{$str_where_order} 
				ORDER BY deposit_date asc, seq asc
				";
		$result = $this->db->query($query);
		if($result) foreach($result->result_array() as $acinsdata){
			$acinsdata['out_commission_price']	= $acinsdata['commission_price']*$acinsdata['exp_ea']+($acinsdata['commission_price_rest']);
			// 정산 주기별 정산금액
			switch($data_provider['calcu_count']){
				case 2:
					if( ($acinsdata['confirm_date'] >= $accountAllDate['cal2'][0]['start']) && ($acinsdata['confirm_date'] <= $accountAllDate['cal2'][0]['end']) ){
						$accountAllCount['account2'][0] += $acinsdata['out_commission_price'];
					}elseif( ($acinsdata['confirm_date'] >= $accountAllDate['cal2'][1]['start']) && ($acinsdata['confirm_date'] <= $accountAllDate['cal2'][1]['end']) ){
						$accountAllCount['account2'][1] += $acinsdata['out_commission_price'];
					}
				break;
				case 4:
					if( ($acinsdata['confirm_date'] >= $accountAllDate['cal4'][0]['start']) && ($acinsdata['confirm_date'] <= $accountAllDate['cal4'][0]['end']) ){
						$accountAllCount['account4'][0] += $acinsdata['out_commission_price'];
					}elseif( ($acinsdata['confirm_date'] >= $accountAllDate['cal4'][1]['start']) && ($acinsdata['confirm_date'] <= $accountAllDate['cal4'][1]['end']) ){
						$accountAllCount['account4'][1] += $acinsdata['out_commission_price'];
					}elseif( ($acinsdata['confirm_date'] >= $accountAllDate['cal4'][2]['start']) && ($acinsdata['confirm_date'] <= $accountAllDate['cal4'][2]['end']) ){
						$accountAllCount['account4'][2] += $acinsdata['out_commission_price'];
					}elseif( ($acinsdata['confirm_date'] >= $accountAllDate['cal4'][3]['start']) && ($acinsdata['confirm_date'] <= $accountAllDate['cal4'][3]['end']) ){
						$accountAllCount['account4'][3] += $acinsdata['out_commission_price'];
					}
				break;
				default:
					$accountAllCount['account1'][0] += $acinsdata['out_commission_price'];
				break;
			}
		}
		return $accountAllCount;
	}


	/**
	* 통계 > 구매 통계 > 매출 > 월별
	**/
	public function get_sales_sales_monthly_stats( $_PARAM ) {
		//매출/정산 테이블 목록 가져오기
		$accountTableUse = config_load('account_table_use');
		// 마이그레이션 날짜에맞춰서 통계가 노출되어야하기 때문에 추가 :: 2019-01-21 lkh
		$accountAllMiDate			= getAccountSetting();
		$checkDate					= date("Y-m");
		$accountAllStatsV2			= $accountAllMiDate['accountall_stats_v2'];		// 통계 개선 2019-06-19 by hed
		$accountAllMigrationDate	= $accountAllMiDate['migration_date'];
		$migrationYear				= $accountAllMiDate['migrationYear'];
		$migrationMonth				= $accountAllMiDate['migrationMonth'];
		$migrationCheckDate			= $accountAllMiDate['migrationCheckDate'];

		$where_order = array();
		$where_order = $this->make_where_order_sales_sales_monthly_stats($_PARAM);
		
		//$str_where_order = " WHERE 1=1 ";
		if($where_order){
			$str_where_order .= " WHERE " . implode(' AND ',$where_order) ;
		}
		
		$result = array();
		// 현재 월까지만 데이터 가져오도록 처리
		$nowMonth = date('m', $this->iOnTimeStamp);
		// 년도가 바뀔때에는 전체 데이터를 가져와야하기 때문에 추가 :: 2019-01-21 lkh
		if($_PARAM['year'] < date('Y', $this->iOnTimeStamp)){
			$nowMonth = 12;
		}
		for($i = 1; $i <= $nowMonth; $i++){
			$month = sprintf("%02d",$i);
			$tb_act_ym	=	$_PARAM['year'].$month;
			$accountTable = 'fm_account_calculate_'.$tb_act_ym;
			$accountYmUse = $accountTableUse[$accountTable];
			$checkDateYm  = trim($_PARAM['year']."-".$month);
			if(!$accountYmUse['use']){
				continue;
			}
			// 마이그레이션 되었을경우 마이그레이션한 다음달 데이터부터 통계노출되도록 추가 :: 2019-01-21 lkh
			if($accountAllMigrationDate != "0000-00-00" && $checkDateYm < $migrationCheckDate){
				continue;
			}
			$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);

			// ==========================================================================
			// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 시작 by hed 2019-06-19 #34379
			// ==========================================================================
			if($accountAllStatsV2 && date("Ym",$accountAllStatsV2) <= $tb_act_ym){
				$params_stats_v2 = array();
				$params_stats_v2						= $_PARAM;
				$params_stats_v2['caltableck']			= $caltableck;
				$params_stats_v2['where_order']			= $where_order;
				$params_stats_v2['month']				= $month;
				$statsData = $this->get_sales_sales_monthly_stats_v2($params_stats_v2);
				$result[] = $statsData;
				continue;
			}
			// ==========================================================================
			// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 종료 by hed 2019-06-19 #34379
			// ==========================================================================

			/**
			* 매출테이블 
			**/
			if	($_PARAM['q_type'] == 'order'){
				//매출
				$CalSalesSelectSqlK = "
							CASE 
								WHEN k.account_type = 'return' THEN YEAR(k.regist_date)
								ELSE YEAR(k.deposit_date)
							END AS stats_year,
							CASE 
								WHEN k.account_type = 'return' THEN MONTH(k.regist_date)
								ELSE MONTH(k.deposit_date)
							END AS stats_month,
							SUM(k.month_settleprice-k.sales_total-k.month_api_pg_sale)	AS month_settleprice_sum,
							SUM(k.month_settleprice)	AS month_month_settleprice_tmp_sum,
							SUM(k.sales_total)			AS month_sales_total_sum,
							SUM(k.month_api_pg_sale)	AS month_api_pg_sale_sum,
							SUM(IF(k.m_settleprice > 0,(k.m_settleprice-k.sales_total-k.month_api_pg_sale),0))		AS m_settleprice_sum,
							SUM(IF(k.p_settleprice > 0,(k.p_settleprice-k.sales_total-k.month_api_pg_sale),0))		AS p_settleprice_sum,
							SUM(IF(k.m_shipping_cost > 0,(k.m_shipping_cost-k.sales_total-k.month_api_pg_sale),0))		AS m_shipping_cost_sum,
							SUM(IF(k.p_shipping_cost > 0,(k.p_shipping_cost-k.sales_total-k.month_api_pg_sale),0))		AS p_shipping_cost_sum,
							SUM(k.month_enuri)			AS month_enuri_sum,
							SUM(k.m_enuri)				AS m_enuri_sum,
							SUM(k.p_enuri)				AS p_enuri_sum,
							SUM(k.month_emoney_use)		AS month_emoney_use_sum,
							SUM(k.m_emoney)				AS m_emoney_sum,
							SUM(k.p_emoney)				AS p_emoney_sum,
							SUM(k.month_cash_use)		AS month_cash_use_sum,
							SUM(k.m_cash)				AS m_cash_sum,
							SUM(k.p_cash)				AS p_cash_sum,
							SUM(k.month_npay_point_use)	AS month_npay_point_use_sum,
							COUNT(DISTINCT k.order_seq)	AS month_count_sum,
							SUM(IF(k.shipping_cost > 0,(k.shipping_cost-k.sales_total-k.month_api_pg_sale),0))		AS shipping_cost_sum,
							SUM(IF(k.shipping_cash_use > 0,(k.shipping_cash_use),0))			AS shipping_cash_use_sum,
							SUM(IF(k.shipping_m_cash > 0,(k.shipping_m_cash),0))				AS shipping_m_cash_sum,
							SUM(IF(k.shipping_p_cash > 0,(k.shipping_p_cash),0))				AS shipping_p_cash_sum,
							SUM(k.goods_shipping_cost)		AS goods_shipping_cost_sum,
							SUM(k.m_goods_shipping_cost)	AS m_goods_shipping_cost_sum,
							SUM(k.p_goods_shipping_cost)	AS p_goods_shipping_cost_sum,
							SUM(k.month_supply_price)		AS month_supply_price_sum,
							SUM(k.month_commission_price)	AS month_commission_price_sum,
							SUM(k.month_commission_price)	AS month_commission_price_sum_krw,
							SUM(IF(month_shipping_cost > 0,(k.month_shipping_cost-k.sales_total-k.month_api_pg_sale),0))			AS month_shipping_cost_sum,
							SUM(k.month_coupon_sale)			AS month_coupon_sale_sum,
							SUM(k.month_promotion_code_sale)	AS month_promotion_code_sale_sum,
							SUM(k.month_fblike_sale)		AS month_fblike_sale_sum,
							SUM(k.month_mobile_sale)		AS month_mobile_sale_sum,
							SUM(k.month_member_sale)		AS month_member_sale_sum,
							SUM(k.month_referer_sale)		AS month_referer_sale_sum,
							SUM(k.month_npay_sale_seller)	AS month_npay_sale_seller_sum,
							SUM(k.month_npay_sale_npay)		AS month_npay_sale_npay_sum,
							SUM(k.month_event_sale)			AS month_event_sale_sum,
							SUM(k.month_multi_sale)			AS month_multi_sale_sum,
							SUM(k.month_api_pg_sale)		AS month_api_pg_sale_sum,
							SUM(IFNULL(k.month_settleprice-k.sales_total-k.month_api_pg_sale,0)-IFNULL(IF(shipping_cost > 0,(k.shipping_cost-k.sales_total-k.month_api_pg_sale),0),0)-IFNULL(k.goods_shipping_cost,0))
													AS month_goods_price_sum
							";

			}elseif	($_PARAM['q_type'] == 'commission'){
				//정산
				$CalSalesSelectSqlK = "
							CASE 
								WHEN k.account_type = 'refund' THEN YEAR(k.regist_date)
								ELSE YEAR(k.confirm_date)
							END AS stats_year,
							CASE 
								WHEN k.account_type = 'refund' THEN MONTH(k.regist_date)
								ELSE MONTH(k.confirm_date)
							END AS stats_month,
							SUM(IF(k.account_type='order' or (k.account_type='return' and k.order_type='shipping'),IFNULL(k.month_commission_price,0),0))		AS month_commission_price_sum,
							SUM(IF(k.account_type='order' or (k.account_type='return' and k.order_type='shipping'),IFNULL(k.month_commission_price,0),0))		AS month_commission_price_sum_krw,
							SUM(IF(k.account_type='refund',IFNULL(k.month_commission_price,0),0))	AS month_refund_commission_price_sum,
							SUM(IF(k.account_type='refund',IFNULL(k.month_commission_price,0),0))	AS month_refund_commission_price_sum_krw,
							SUM(IF(k.account_type='rollback',IFNULL(k.month_commission_price,0),0))	AS refund_rollback_commission_price_sum
							";
			}else{
				//환불
				$CalSalesSelectSqlK = "
							YEAR(k.regist_date)			AS stats_year,
							MONTH(k.regist_date)		AS stats_month,
							SUM(k.month_settleprice-k.sales_total-k.month_api_pg_sale) AS refund_price_total_sum,
							SUM(k.month_settleprice-k.sales_total-k.month_api_pg_sale) AS month_refund_price_total_sum,
							SUM(if(k.provider_seq = 1,(k.month_settleprice-k.sales_total-k.month_api_pg_sale),0))		AS month_m_refund_price_total_sum,
							SUM(if(k.provider_seq > 1,(k.month_settleprice-k.sales_total-k.month_api_pg_sale),0))		AS month_p_refund_price_total_sum,
							SUM((k.month_settleprice-k.sales_total-k.month_api_pg_sale))	AS month_refund_settle_sum,
							SUM(k.month_emoney_use)		AS month_refund_emoney_sum,
							SUM(k.month_cash_use)		AS month_refund_cash_sum,

							SUM(IF(k.account_type in ('refund','after_refund'),(k.month_settleprice-k.sales_total-k.month_api_pg_sale),0))		AS month_refund_price_sum_A,
							SUM(IF(k.account_type in ('refund','after_refund'),(k.month_settleprice-k.sales_total-k.month_api_pg_sale),0))		AS month_refund_price_sum,
							SUM(IF(k.account_type in ('refund','after_refund'),(IF(k.m_settleprice > 0,(k.m_settleprice-k.sales_total-k.month_api_pg_sale),0)+IF(k.m_shipping_cost > 0,(k.m_shipping_cost-k.sales_total-k.month_api_pg_sale),0)),0))		AS month_m_refund_price_sum,
							SUM(IF(k.account_type in ('refund','after_refund'),(IF(k.p_settleprice > 0,(k.p_settleprice-k.sales_total-k.month_api_pg_sale),0)+IF(k.p_shipping_cost > 0,(k.p_shipping_cost-k.sales_total-k.month_api_pg_sale),0)),0))		AS month_p_refund_price_sum,

							SUM(IF(k.account_type='rollback',(k.month_settleprice-k.sales_total-k.month_api_pg_sale),0))	AS month_rollback_price_sum,
							SUM(IF(k.account_type='rollback',(IF(k.m_settleprice > 0,(k.m_settleprice-k.sales_total-k.month_api_pg_sale),0)+IF(k.m_shipping_cost > 0,(k.m_shipping_cost-k.sales_total-k.month_api_pg_sale),0)),0))		AS month_m_rollback_price_sum,
							SUM(IF(k.account_type='rollback',(IF(k.p_settleprice > 0,(k.p_settleprice-k.sales_total-k.month_api_pg_sale),0)+IF(k.p_shipping_cost > 0,(k.p_shipping_cost-k.sales_total-k.month_api_pg_sale),0)),0))		AS month_p_rollback_price_sum,

							SUM(IF(k.account_type in ('refund','after_refund'),k.month_count,0))	AS month_refund_count_sum,
							SUM(IF(k.account_type='rollback',k.month_count,0))		AS month_rollback_count_sum,

							SUM(IF(k.account_type in ('refund','after_refund'),IFNULL(k.month_supply_price,0),0))	AS month_refund_supply_price_sum,
							SUM(IF(k.account_type='rollback',IFNULL(k.month_supply_price,0),0))	AS refund_rollback_supply_price_sum,

							SUM(IF(k.account_type in ('refund','after_refund'),IFNULL(k.month_commission_price,0),0))	AS month_refund_commission_price_sum,
							SUM(IF(k.account_type in ('refund','after_refund'),IFNULL(k.month_commission_price,0),0))	AS month_refund_commission_price_sum_krw,
							SUM(IF(k.account_type='rollback',IFNULL(k.month_commission_price,0),0))	AS refund_rollback_commission_price_sum,
							SUM(k.month_enuri)					AS month_refund_enuri_sum,
							SUM(k.month_emoney_use)				AS month_refund_emoney_use_sum,
							SUM(k.month_coupon_sale)			AS month_refund_coupon_sale_sum,
							SUM(k.month_promotion_code_sale)	AS month_refund_promotion_code_sale_sum,
							SUM(k.month_fblike_sale)			AS month_refund_fblike_sale_sum,
							SUM(k.month_mobile_sale)			AS month_refund_mobile_sale_sum,
							SUM(k.month_member_sale)			AS month_refund_member_sale_sum,
							SUM(k.month_referer_sale)			AS month_refund_referer_sale_sum,
							SUM(k.month_npay_sale_seller)		AS month_refund_npay_sale_seller_sum,
							SUM(k.month_npay_sale_npay)			AS month_refund_npay_sale_npay_sum,
							SUM(k.month_event_sale)				AS month_refund_event_sale_sum,
							SUM(k.month_multi_sale)				AS month_refund_multi_sale_sum,
							SUM(k.month_api_pg_sale)			AS month_refund_api_pg_sale_sum
							";
			}
			
			if	($_PARAM['q_type'] == 'commission'){
				//정산 ( SACO : 판매금액-round(수수료) , 그 외는 수수료금액을 round 해서 합 구함 - accountalllist() 참조)
				$CalSalesSelectSql = "SELECT
									caltb.confirm_date,caltb.deposit_date,caltb.account_type,caltb.order_type,caltb.regist_date,
									IF( caltb.status IN ( 'complete',  'carryover'), 
										IF( caltb.commission_type =  'SACO', 
											(caltb.price * caltb.ea) - ROUND( caltb.sales_unit_feeprice * caltb.exp_ea ) , 
											ROUND( (IFNULL( caltb.commission_price, 0 ) * IFNULL( caltb.exp_ea, 0 ) ) + IFNULL( caltb.commission_price_rest, 0 ) )
										),
									IF(caltb.account_type='refund' AND caltb.status='overdraw',
										IF( caltb.commission_type =  'SACO', 
											(caltb.price * caltb.ea) - ROUND( caltb.sales_unit_feeprice * caltb.exp_ea ) , 
											ROUND( (IFNULL( caltb.commission_price, 0 ) * IFNULL( caltb.exp_ea, 0 ) ) + IFNULL( caltb.commission_price_rest, 0 ) )
										),
									0)) AS month_commission_price
								";
			}else{
					//매출,환불
				$CalSalesSelectSql = "SELECT
										caltb.seq,
										caltb.order_seq,
										caltb.regist_date,
										caltb.deposit_date,
										caltb.account_type,
										(caltb.price*caltb.ea) AS month_settleprice,
										caltb.provider_seq,
										IFNULL(
											(caltb.event_sale_unit*caltb.ea+caltb.event_sale_rest)
											+
											(caltb.multi_sale_unit*caltb.ea+caltb.multi_sale_rest)
											+
											(caltb.code_sale_unit*caltb.ea+caltb.code_sale_rest)
											+
											(caltb.member_sale_unit*caltb.ea+caltb.member_sale_rest)
											+
											(caltb.coupon_sale_unit*caltb.ea+caltb.coupon_sale_rest)
											+
											(caltb.fblike_sale_unit*caltb.ea+caltb.fblike_sale_rest)
											+
											(caltb.mobile_sale_unit*caltb.ea+caltb.mobile_sale_rest)
											+
											(caltb.referer_sale_unit*caltb.ea+caltb.referer_sale_rest)
											+
											(caltb.enuri_sale_unit*caltb.ea+caltb.enuri_sale_rest)
											+
											(caltb.emoney_sale_unit*caltb.ea+caltb.emoney_sale_rest)
										,0)
										AS sales_total,
										CASE caltb.order_referer
											WHEN 'storefarm' THEN ( (caltb.api_pg_sale_price-caltb.api_pg_support_price) + caltb.api_pg_support_price )
											WHEN 'open11st' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
											WHEN 'coupang' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
											ELSE 0
										end AS month_api_pg_sale,
										if(caltb.order_type <> 'shipping' and caltb.provider_seq = 1,(caltb.price*caltb.ea),0) AS m_settleprice,
										if(caltb.order_type <> 'shipping' and caltb.provider_seq > 1,(caltb.price*caltb.ea),0) AS p_settleprice,
										if(caltb.order_type = 'shipping' and caltb.shipping_provider_seq = 1,(caltb.price),0) AS m_shipping_cost,
										if(caltb.order_type = 'shipping' and caltb.shipping_provider_seq > 1,(caltb.price),0) AS p_shipping_cost,
										( (caltb.enuri_sale_unit*caltb.ea) + caltb.enuri_sale_rest ) AS month_enuri,
										if(caltb.provider_seq = 1,( (caltb.enuri_sale_unit*caltb.ea) + caltb.enuri_sale_rest ),0) AS m_enuri,
										if(caltb.provider_seq > 1,( (caltb.enuri_sale_unit*caltb.ea) + caltb.enuri_sale_rest ),0) AS p_enuri,
										( (caltb.emoney_sale_unit*caltb.ea) + caltb.emoney_sale_rest ) AS month_emoney_use,
										if(caltb.provider_seq = 1,( (caltb.emoney_sale_unit*caltb.ea) + caltb.emoney_sale_rest ),0) AS m_emoney,
										if(caltb.provider_seq > 1,( (caltb.emoney_sale_unit*caltb.ea) + caltb.emoney_sale_rest ),0) AS p_emoney,
										( (caltb.cash_sale_unit*caltb.ea) + caltb.cash_sale_rest ) AS month_cash_use,
										if(caltb.provider_seq = 1,( (caltb.cash_sale_unit*caltb.ea) + caltb.cash_sale_rest ),0) AS m_cash,
										if(caltb.provider_seq > 1,( (caltb.cash_sale_unit*caltb.ea) + caltb.cash_sale_rest ),0) AS p_cash,
										if(caltb.order_type = 'shipping',0,caltb.ea) AS month_count,
										if(caltb.order_type = 'shipping',caltb.price,0) AS shipping_cost,
										if(caltb.order_type = 'shipping',( (caltb.cash_sale_unit*caltb.ea) + caltb.cash_sale_rest ), 0) AS shipping_cash_use,
										if(caltb.order_type = 'shipping',if(caltb.provider_seq = 1,( (caltb.cash_sale_unit*caltb.ea) + caltb.cash_sale_rest ),0),0) AS shipping_m_cash,
										if(caltb.order_type = 'shipping',if(caltb.provider_seq > 1,( (caltb.cash_sale_unit*caltb.ea) + caltb.cash_sale_rest ),0),0) AS shipping_p_cash,
										0 AS goods_shipping_cost,
										0 AS m_goods_shipping_cost,
										0 AS p_goods_shipping_cost,
										if(caltb.order_type = 'shipping',caltb.price,0) AS month_shipping_cost,
										( (caltb.coupon_sale_unit*caltb.ea) + caltb.coupon_sale_rest ) AS month_coupon_sale,
										( (caltb.code_sale_unit*caltb.ea) + caltb.code_sale_rest ) AS month_promotion_code_sale,
										( (caltb.fblike_sale_unit*caltb.ea) + caltb.fblike_sale_rest ) AS month_fblike_sale,
										( (caltb.mobile_sale_unit*caltb.ea) + caltb.mobile_sale_rest ) AS month_mobile_sale,
										( (caltb.member_sale_unit*caltb.ea) + caltb.member_sale_rest ) AS month_member_sale,
										( (caltb.referer_sale_unit*caltb.ea) + caltb.referer_sale_rest ) AS month_referer_sale,
										caltb.npay_point		AS month_npay_point_use,
										caltb.npay_sale_seller	AS month_npay_sale_seller,
										caltb.npay_sale_npay	AS month_npay_sale_npay,
										if(caltb.order_type = 'order',0,(caltb.supply_price*caltb.exp_ea)) AS month_supply_price,
										caltb.status,
										if(caltb.status in ('complete','carryover'),((IFNULL(caltb.commission_price,0)*IFNULL(caltb.exp_ea,0)) + IFNULL(caltb.commission_price_rest,0)),0 )	AS month_commission_price,
										( (caltb.event_sale_unit*caltb.ea) + caltb.event_sale_rest ) AS month_event_sale,
										( (caltb.multi_sale_unit*caltb.ea) + caltb.multi_sale_rest ) AS month_multi_sale
									";
			}
			$CalSalesFromSql	= "FROM  {$caltableck} caltb inner join fm_order o on (o.order_seq = caltb.order_seq) ";
			$CalSalesWhereSql	= $str_where_order;
			$CalSalesGroupSql	= " ";
			$CalSalesOrderSql	= "ORDER BY caltb.deposit_date asc,caltb.status desc";// limit 20
			$CalSalesSql			= $CalSalesSelectSql . $CalSalesFromSql . $CalSalesWhereSql . $CalSalesGroupSql . $CalSalesOrderSql;
			$sql	= "
					SELECT 
						{$CalSalesSelectSqlK}
					FROM 
					(
						{$CalSalesSql}
					) AS k
					ORDER BY k.deposit_date asc
					";
			$query = $this->db->query($sql);
			$result_array = $query->result_array();
			$result[] = $result_array[0];
		}
		return $result;
	}


	/**
	* 통계 > 구매 통계 > 매출 > 일별
	**/
	public function get_sales_sales_daily_stats( $_PARAM ) {
		//매출/정산 테이블 목록 가져오기
		$accountTableUse = config_load('account_table_use');

		// 마이그레이션 날짜에맞춰서 통계가 노출되어야하기 때문에 추가 :: 2019-01-21 lkh
		$accountAllMiDate			= getAccountSetting();
		$accountAllStatsV2			= $accountAllMiDate['accountall_stats_v2'];		// 통계 개선 2019-06-19 by hed
		
		if($_PARAM['month']){
			$_PARAM['month'] = sprintf("%02d",$_PARAM['month']);
		}
		if($_PARAM['year'] && $_PARAM['month']) $tb_act_ym	=	$_PARAM['year'].$_PARAM['month'];
		if(!$tb_act_ym) $tb_act_ym	=	date('Ym', $this->iOnTimeStamp);

		//매출/정산 테이블이 있는지 확인
		$accountTable = 'fm_account_calculate_'.$tb_act_ym;
		$accountYmUse = $accountTableUse[$accountTable];
		if(!$accountYmUse['use']){
			return false;
		}
		$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);

		$where_order[] = "caltb.status in ('overdraw','complete') ";
		if	($_PARAM['q_type'] == 'order'){
			$where_order[]	= "caltb.account_type not in ('refund','rollback','after_refund') ";
		}else{
			$where_order[]	= "caltb.account_type in ('refund','rollback','after_refund') ";
		}
		// 입점사
		if (!empty($_PARAM['provider_seq'])){
			$where_order[]	= "caltb.provider_seq = '".$_PARAM['provider_seq']."' ";
		}
		// 주문 타입
		if (is_array($_PARAM['sitetype']) && count($_PARAM['sitetype']) > 0){
			$table_chr = 'o.';
			if($accountAllStatsV2 && date("Ymd",$accountAllStatsV2) <= date("Ymd")){
				$table_chr = 'caltb.';
				if(empty($_PARAM['without_null_sitetype'])){
					$_PARAM['sitetype'][] = "";
				}
			}			
			$where_order[]	= $table_chr."sitetype in ('".implode("','",$_PARAM['sitetype'])."') ";
		}

		// 일자 검색
		if($_PARAM['day']){
			$target_date = $_PARAM['year']."-".$_PARAM['month']."-".$_PARAM['day'];
			$target_date_start = "".$target_date." 00:00:00";
			$target_date_end = "".$target_date." 23:59:59";
			$bind = array();

			$where_order[]	= "
				(
				caltb.deposit_date BETWEEN ? AND ?
				OR (
					caltb.account_type IN ('refund', 'rollback', 'after_refund', 'deductible') 
					AND caltb.regist_date BETWEEN ? AND ?
				)
				OR (
					caltb.account_type NOT IN ('refund', 'rollback', 'after_refund', 'deductible') 
					AND caltb.exp_ea > 0 
					AND caltb.ac_ea = 0
					AND caltb.confirm_date BETWEEN ? AND ?
				)
			)";
			$bind[]	= $target_date_start;
			$bind[]	= $target_date_end;
			$bind[]	= $target_date_start;
			$bind[]	= $target_date_end;
			$bind[]	= $target_date_start;
			$bind[]	= $target_date_end;
		}

		//$str_where_order = " WHERE 1=1 ";
		if($where_order){
			$str_where_order .= " WHERE " . implode(' AND ',$where_order) ;
		}

		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 시작 by hed 2019-06-19 #34379
		// ==========================================================================
		if($accountAllStatsV2 && date("Ym",$accountAllStatsV2) <= $tb_act_ym){
			$params_stats_v2 = array();
			$params_stats_v2['q_type']				= $_PARAM['q_type'];
			$params_stats_v2['caltableck']			= $caltableck;
			$params_stats_v2['where_order']			= $where_order;
			$params_stats_v2['year']				= $_PARAM['year'];
			$params_stats_v2['month']				= $_PARAM['month'];
			$params_stats_v2['bind']				= $bind;
			$statsData = $this->get_sales_sales_daily_stats_v2($params_stats_v2);
			return $statsData;
		}
		// ==========================================================================
		// 통계 개선 패치가 이루어졌을 경우 통계개선 기능으로 변경 종료 by hed 2019-06-19 #34379
		// ==========================================================================

		/**
		 * 매출테이블 
		**/
		if	($_PARAM['q_type'] == 'order'){
			$CalSalesSelectSqlK = "
						CASE 
							WHEN k.account_type = 'return' THEN YEAR(k.regist_date)
							ELSE YEAR(k.deposit_date)
						END AS stats_year,
						CASE 
							WHEN k.account_type = 'return' THEN MONTH(k.regist_date)
							ELSE MONTH(k.deposit_date)
						END AS stats_month,
						CASE 
							WHEN k.account_type = 'return' THEN DAY(k.regist_date)
							ELSE DAY(k.deposit_date)
						END AS stats_day,
						SUM(k.day_settleprice)			AS day_settleprice_sum,
						SUM(k.day_enuri)				AS day_enuri_sum,
						SUM(k.day_emoney_use)			AS day_emoney_use_sum,
						SUM(k.day_cash_use)				AS day_cash_use_sum,
						SUM(k.day_count)				AS day_count_sum,
						SUM(k.day_shipping_cost)		AS day_shipping_cost_sum,
						SUM(k.day_ori_price)			AS day_ori_price_sum,
						SUM(k.day_supply_price)			AS day_supply_price_sum,
						SUM(k.day_coupon_sale)			AS day_coupon_sale_sum,
						SUM(k.day_promotion_code_sale)	AS day_promotion_code_sale_sum,
						SUM(k.day_fblike_sale)			AS day_fblike_sale_sum,
						SUM(k.day_mobile_sale)			AS day_mobile_sale_sum,
						SUM(k.day_member_sale)			AS day_member_sale_sum,
						SUM(k.day_referer_sale)			AS day_referer_sale_sum,
						SUM(k.day_event_sale)			AS day_event_sale_sum,
						SUM(k.day_multi_sale)			AS day_multi_sale_sum,
						SUM(k.api_pg_sale)				AS day_api_pg_sale_sum
						";
		}else{
			$CalSalesSelectSqlK = "
						YEAR(k.regist_date)		AS stats_year,
						MONTH(k.regist_date)	AS stats_month,
						DAY(k.regist_date)		AS stats_day,
						SUM(k.day_settleprice+k.day_cash_use)					AS day_refund_price_sum,
						IF(k.account_type in ('refund','after_refund'),SUM(k.day_count),0)			AS day_refund_count_sum_A,
						IF(k.account_type='rollback',SUM(k.day_count),0)		AS day_refund_count_sum_R,
						SUM(k.day_settleprice) 									AS refund_price_sum,
						SUM(k.day_cash_use)										AS refund_cash_sum,
						SUM(k.day_emoney_use)									AS refund_emoney_sum,
						SUM(IFNULL(k.day_supply_price,0))						AS day_refund_supply_price_sum,
						SUM(k.day_settleprice+k.day_cash_use)					AS day_refund_price_sum_total,
						IF(k.account_type='rollback',SUM(k.day_settleprice+k.day_cash_use),0)	AS day_rollback_price_sum,
						SUM(k.day_enuri)				AS day_refund_enuri_sum,
						SUM(k.day_emoney_use)			AS day_refund_emoney_use_sum,
						SUM(k.day_cash_use)				AS day_refund_cash_use_sum,
						SUM(k.day_coupon_sale)			AS day_refund_coupon_sale_sum,
						SUM(k.day_promotion_code_sale)	AS day_refund_promotion_code_sale_sum,
						SUM(k.day_fblike_sale)			AS day_refund_fblike_sale_sum,
						SUM(k.day_mobile_sale)			AS day_refund_mobile_sale_sum,
						SUM(k.day_member_sale)			AS day_refund_member_sale_sum,
						SUM(k.day_referer_sale)			AS day_refund_referer_sale_sum,
						SUM(k.day_event_sale)			AS day_refund_event_sale_sum,
						SUM(k.day_multi_sale)			AS day_refund_multi_sale_sum,
						SUM(k.api_pg_sale)				AS day_refund_api_pg_sale_sum
						";
		}
		
		$CalSalesSelectSql = "SELECT
								caltb.seq,
								caltb.order_seq,
								caltb.regist_date,
								caltb.deposit_date,
								caltb.account_type,
								sum(((caltb.price*caltb.ea)-
									IFNULL(
										(caltb.event_sale_unit*caltb.ea+caltb.event_sale_rest)
										+
										(caltb.multi_sale_unit*caltb.ea+caltb.multi_sale_rest)
										+
										(caltb.code_sale_unit*caltb.ea+caltb.code_sale_rest)
										+
										(caltb.member_sale_unit*caltb.ea+caltb.member_sale_rest)
										+
										(caltb.coupon_sale_unit*caltb.ea+caltb.coupon_sale_rest)
										+
										(caltb.fblike_sale_unit*caltb.ea+caltb.fblike_sale_rest)
										+
										(caltb.mobile_sale_unit*caltb.ea+caltb.mobile_sale_rest)
										+
										(caltb.referer_sale_unit*caltb.ea+caltb.referer_sale_rest)
										+
										(caltb.enuri_sale_unit*caltb.ea+caltb.enuri_sale_rest)
										+
										(caltb.emoney_sale_unit*caltb.ea+caltb.emoney_sale_rest)
										+
										(caltb.cash_sale_unit*caltb.ea+caltb.cash_sale_rest)
									,0)-
									if(caltb.order_referer = 'storefarm',(api_pg_sale_price-api_pg_support_price) + api_pg_support_price,
									if(caltb.order_referer = 'open11st',api_pg_sale_price + api_pg_support_price,
									if(caltb.order_referer = 'coupang',api_pg_sale_price + api_pg_support_price,0)
								)))) AS day_settleprice,
								sum(( (caltb.enuri_sale_unit*caltb.ea) + caltb.enuri_sale_rest )) AS day_enuri,
								sum(( (caltb.emoney_sale_unit*caltb.ea) + caltb.emoney_sale_rest )) AS day_emoney_use,
								sum(( (caltb.cash_sale_unit*caltb.ea) + caltb.cash_sale_rest )) AS day_cash_use,
								1 AS day_count,
								sum(if(caltb.order_type = 'shipping',caltb.price,0)) AS day_shipping_cost,
								sum(if(caltb.order_type = 'shipping',0,caltb.price)) AS day_ori_price,
								sum((caltb.supply_price*caltb.ea)) AS day_supply_price,
								sum( (caltb.coupon_sale_unit*caltb.ea) + caltb.coupon_sale_rest ) AS day_coupon_sale,
								sum( (caltb.code_sale_unit*caltb.ea) + caltb.code_sale_rest ) AS day_promotion_code_sale,
								sum( (caltb.fblike_sale_unit*caltb.ea) + caltb.fblike_sale_rest ) AS day_fblike_sale,
								sum( (caltb.mobile_sale_unit*caltb.ea) + caltb.mobile_sale_rest ) AS day_mobile_sale,
								sum( (caltb.member_sale_unit*caltb.ea) + caltb.member_sale_rest ) AS day_member_sale,
								sum( (caltb.referer_sale_unit*caltb.ea) + caltb.referer_sale_rest ) AS day_referer_sale,
								sum( (caltb.event_sale_unit*caltb.ea) + caltb.event_sale_rest ) AS day_event_sale,
								sum( (caltb.multi_sale_unit*caltb.ea) + caltb.multi_sale_rest ) AS day_multi_sale,
								CASE caltb.order_referer
									WHEN 'storefarm' THEN sum( (api_pg_sale_price-api_pg_support_price) + api_pg_support_price )
									WHEN 'open11st' THEN sum( api_pg_sale_price + api_pg_support_price )
									WHEN 'coupang' THEN sum( api_pg_sale_price + api_pg_support_price )
									ELSE 0
								end AS api_pg_sale
							";
		$CalSalesFromSql	= "FROM  {$caltableck} caltb inner join fm_order o on (o.order_seq = caltb.order_seq) ";
		$CalSalesWhereSql	= $str_where_order;
		$CalSalesGroupSql	= "GROUP BY order_seq ";
		$CalSalesOrderSql	= "ORDER BY caltb.deposit_date asc,caltb.status desc";// limit 20
		$CalSalesSql			= $CalSalesSelectSql . $CalSalesFromSql . $CalSalesWhereSql . $CalSalesGroupSql . $CalSalesOrderSql;
		$query	= "
				SELECT 
					{$CalSalesSelectSqlK}
				FROM 
				(
					{$CalSalesSql}
				) AS k
				GROUP BY stats_day
				ORDER BY k.deposit_date asc
				";
		$result = $this->db->query($query, $bind);
		return $result;
	}

	/**
	* 통계 > 구매 통계 > 매출 > 시간별
	* 입점사 페이지
	**/
	public function get_goods_sales_hour_stats($params){
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
		
		
		$wheres = array();
		$wheres[] = "a.deposit_yn='y'";
		$wheres[] = "year(a.deposit_date)='".$year."'";
		if(!empty($month)) $wheres[] = "month(a.deposit_date)='".$month."'";
		if(is_array($sitetype) && count($sitetype) > 0) $wheres[] = "a.sitetype in ('".implode("','",$sitetype)."')";
		if(!empty($provider_seq)) $wheres[] = "c.provider_seq = '".$provider_seq."'";
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
			left join fm_order_item as c on a.order_seq = c.order_seq
			where ".implode(" and ", $wheres)."
			group by stats_hour
		";
		return $this->db->query($query);
	}

		
	//정산리스트 - 업체별 리스트 
	public function get_seller_stats_query( $_PARAM ) {
		if($_PARAM['acc_table']) $tb_act_ym	=	$_PARAM['acc_table'];
		if(!$tb_act_ym) $tb_act_ym		=	date('Ym', $this->iOnTimeStamp);
		$settingArr						= array();
		$settingArr['year'] 			= date("Y", $this->iOnTimeStamp);
		$settingArr['month'] 			= date("m", $this->iOnTimeStamp);
		$accountallConfirmSettingTmp 	= $this->get_account_setting("month",$settingArr);
		$accountConfirmDate				= $accountallConfirmSettingTmp['accountall_confirm'];

		$data = array();
		//통합정산SQL
		//당일이거나 전월이고 정산마감일 전에는 통합정산 당월데이타 기준으로 추출
		if( $tb_act_ym >= date('Ym', $this->iOnTimeStamp) || date('Ym', strtotime("-1 month", $this->iOnTimeStamp)) && ($accountConfirmDate > date('d', $this->iOnTimeStamp))) {
			// 정산주기별 날짜 기본값 생성 - start
			$accountAllDate = getAccountAllDate(array("s_year"=>substr($tb_act_ym,0,4),"s_month"=>substr($tb_act_ym,4,2)));
			// 정산주기별 날짜 기본값 생성 - end

			$providerList = $this->providermodel->provider_list_sort($_PARAM);
			$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);
			for($i=0;$i < count($providerList);$i++){
				$providerSeq 		= $providerList[$i]['no'];
				$providerId 		= $providerList[$i]['provider_id'];
				$providerName 		= $providerList[$i]['provider_name'];
				$calcuCount 		= $providerList[$i]['calcu_count'];
				$whereOrder 		= array();
				$returnItemTmp 		= array();
				$strWhereOrder 		= "";
				if($providerSeq){
					$whereOrder[] = "caltb.provider_seq = '".$providerSeq."'";
				}
	
				if($whereOrder){
					$strWhereOrder .= " AND " . implode(' AND ',$whereOrder) ;
				}
				$CalSelectSql	= acc_seller_stats_query_str();//PG수수료구문과 소스분리
				$CalFromSql		= " FROM {$caltableck} caltb ";
				$CalWhereSql	= "WHERE caltb.status in ('complete','carryover') and (caltb.provider_seq!=1 or caltb.shipping_provider_seq !=1 ) ".$strWhereOrder;
									//기본입점사별 통계 확정-전월(carryover)/확정-당월(complete)만 수집
									// or (caltb.provider_seq !=1 and  shipping_provider_seq = 1) 본사추가시사용하세요
				$CalGroupSql	= " ";
				$CalOrderSql	= "ORDER BY caltb.deposit_date asc,caltb.status desc";
				$CalSql			= $CalSelectSql . $CalFromSql . $CalWhereSql . $CalGroupSql . $CalOrderSql;

				$query = $this->db->query($CalSql);
				if($query && $query->num_rows()) {
					// 초기화
					switch($calcuCount){
						case 2:
							$returnItemTmp['acount2'][0]['sum_ea']					= 0;
							$returnItemTmp['acount2'][0]['sum_price']				= 0;
							$returnItemTmp['acount2'][0]['sum_commission_price']	= 0;
							$returnItemTmp['acount2'][0]['sum_feeprice']			= 0;
							$returnItemTmp['acount2'][0]['sum_sales_price']		    = 0;
							$returnItemTmp['acount2'][1]['sum_ea']					= 0;
							$returnItemTmp['acount2'][1]['sum_price']				= 0;
							$returnItemTmp['acount2'][1]['sum_commission_price']	= 0;
							$returnItemTmp['acount2'][1]['sum_feeprice']			= 0;
							$returnItemTmp['acount2'][1]['sum_sales_price']		    = 0;
						break;
						case 4:
							$returnItemTmp['acount4'][0]['sum_ea']					= 0;
							$returnItemTmp['acount4'][0]['sum_price']				= 0;
							$returnItemTmp['acount4'][0]['sum_commission_price']	= 0;
							$returnItemTmp['acount4'][0]['sum_feeprice']			= 0;
							$returnItemTmp['acount4'][0]['sum_sales_price']		    = 0;
							$returnItemTmp['acount4'][1]['sum_ea']					= 0;
							$returnItemTmp['acount4'][1]['sum_price']				= 0;
							$returnItemTmp['acount4'][1]['sum_commission_price']	= 0;
							$returnItemTmp['acount4'][1]['sum_feeprice']			= 0;
							$returnItemTmp['acount4'][1]['sum_sales_price']		    = 0;
							$returnItemTmp['acount4'][2]['sum_ea']					= 0;
							$returnItemTmp['acount4'][2]['sum_price']				= 0;
							$returnItemTmp['acount4'][2]['sum_commission_price']	= 0;
							$returnItemTmp['acount4'][2]['sum_feeprice']			= 0;
							$returnItemTmp['acount4'][2]['sum_sales_price']		    = 0;
							$returnItemTmp['acount4'][3]['sum_ea']					= 0;
							$returnItemTmp['acount4'][3]['sum_price']				= 0;
							$returnItemTmp['acount4'][3]['sum_commission_price']	= 0;
							$returnItemTmp['acount4'][3]['sum_feeprice']			= 0;
							$returnItemTmp['acount4'][3]['sum_sales_price']		    = 0;
						break;
						default:
							$returnItemTmp['acount1'][0]['sum_ea']					= 0;
							$returnItemTmp['acount1'][0]['sum_price']				= 0;
							$returnItemTmp['acount1'][0]['sum_commission_price']	= 0;
							$returnItemTmp['acount1'][0]['sum_feeprice']			= 0;
							$returnItemTmp['acount1'][0]['sum_sales_price']		    = 0;
						break;
					}
					foreach($query->result_array() as $resData){
						if($resData['ea'] == 0 && $resData['price'] > 0){
							$resData['ea'] = 1;
						}
						if($resData['refund_ea'] == 0 && $resData['refund_price'] > 0){
							$resData['refund_ea'] = 1;
						}

						if($resData['commission_type'] == "SACO" || $resData['commission_type'] == ""){
							$resData['refund_price']	= $resData['refund_price'] - $resData['refund_sales_provider_total'] - $resData['refund_pg_sale_price'];
							$resData['price']			= $resData['price'] - $resData['sales_provider_total'] - $resData['pg_sale_price'];
							$resData['commission_price']= $resData['price'] - $resData['feeprice'];	//정산금액 = 정산대상금액 - 수수료
						}else{
							$resData['refund_price']	= $resData['refund_price'] - $resData['refund_sales_admin_total'] - $resData['refund_sales_provider_total'] - $resData['refund_pg_sale_price'] + $resData['refund_cash_use'];
							$resData['price']			= $resData['price'] - $resData['sales_admin_total'] - $resData['sales_provider_total'] - $resData['pg_sale_price'] + $resData['cash_use'];
							$resData['feeprice']= $resData['price'] - $resData['commission_price']; //수수료 = 정산대상금액 - 정산금액
						}

						// 정산 주기별 금액 누적
						switch($calcuCount){
							case 2:
								if( ($resData['confirm_date'] >= $accountAllDate['cal2'][0]['start']) && ($resData['confirm_date'] <= $accountAllDate['cal2'][0]['end']) ){
									$returnItemTmp['acount2'][0]['sum_ea']					+= ($resData['ea']					- $resData['refund_ea']);
									$returnItemTmp['acount2'][0]['sum_price']				+= ($resData['price']				- $resData['refund_price']);
									$returnItemTmp['acount2'][0]['sum_commission_price']	+= ($resData['commission_price']	- $resData['refund_commission_price']);
									$returnItemTmp['acount2'][0]['sum_feeprice']			+= ($resData['feeprice']			- $resData['refund_feeprice']);
									$returnItemTmp['acount2'][0]['sum_sales_price']			+= ($resData['sales_price']			- $resData['refund_sales_price']);
								}elseif( ($resData['confirm_date'] >= $accountAllDate['cal2'][1]['start']) && ($resData['confirm_date'] <= $accountAllDate['cal2'][1]['end']) ){
									$returnItemTmp['acount2'][1]['sum_ea']					+= ($resData['ea']					- $resData['refund_ea']);
									$returnItemTmp['acount2'][1]['sum_price']				+= ($resData['price']				- $resData['refund_price']);
									$returnItemTmp['acount2'][1]['sum_commission_price']	+= ($resData['commission_price']	- $resData['refund_commission_price']);
									$returnItemTmp['acount2'][1]['sum_feeprice']			+= ($resData['feeprice']			- $resData['refund_feeprice']);
									$returnItemTmp['acount2'][1]['sum_sales_price']			+= ($resData['sales_price']			- $resData['refund_sales_price']);
								}
							break;
							case 4:
								if( ($resData['confirm_date'] >= $accountAllDate['cal4'][0]['start']) && ($resData['confirm_date'] <= $accountAllDate['cal4'][0]['end']) ){
									$returnItemTmp['acount4'][0]['sum_ea']					+= ($resData['ea']					- $resData['refund_ea']);
									$returnItemTmp['acount4'][0]['sum_price']				+= ($resData['price']				- $resData['refund_price']);
									$returnItemTmp['acount4'][0]['sum_commission_price']	+= ($resData['commission_price']	- $resData['refund_commission_price']);
									$returnItemTmp['acount4'][0]['sum_feeprice']			+= ($resData['feeprice']			- $resData['refund_feeprice']);
									$returnItemTmp['acount4'][0]['sum_sales_price']			+= ($resData['sales_price']			- $resData['refund_sales_price']);
								}elseif( ($resData['confirm_date'] >= $accountAllDate['cal4'][1]['start']) && ($resData['confirm_date'] <= $accountAllDate['cal4'][1]['end']) ){
									$returnItemTmp['acount4'][1]['sum_ea']					+= ($resData['ea']					- $resData['refund_ea']);
									$returnItemTmp['acount4'][1]['sum_price']				+= ($resData['price']				- $resData['refund_price']);
									$returnItemTmp['acount4'][1]['sum_commission_price']	+= ($resData['commission_price']	- $resData['refund_commission_price']);
									$returnItemTmp['acount4'][1]['sum_feeprice']			+= ($resData['feeprice']			- $resData['refund_feeprice']);
									$returnItemTmp['acount4'][1]['sum_sales_price']			+= ($resData['sales_price']			- $resData['refund_sales_price']);
								}elseif( ($resData['confirm_date'] >= $accountAllDate['cal4'][2]['start']) && ($resData['confirm_date'] <= $accountAllDate['cal4'][2]['end']) ){
									$returnItemTmp['acount4'][2]['sum_ea']					+= ($resData['ea']					- $resData['refund_ea']);
									$returnItemTmp['acount4'][2]['sum_price']				+= ($resData['price']				- $resData['refund_price']);
									$returnItemTmp['acount4'][2]['sum_commission_price']	+= ($resData['commission_price']	- $resData['refund_commission_price']);
									$returnItemTmp['acount4'][2]['sum_feeprice']			+= ($resData['feeprice']			- $resData['refund_feeprice']);
									$returnItemTmp['acount4'][2]['sum_sales_price']			+= ($resData['sales_price']			- $resData['refund_sales_price']);
								}elseif( ($resData['confirm_date'] >= $accountAllDate['cal4'][3]['start']) && ($resData['confirm_date'] <= $accountAllDate['cal4'][3]['end']) ){
									$returnItemTmp['acount4'][3]['sum_ea']					+= ($resData['ea']					- $resData['refund_ea']);
									$returnItemTmp['acount4'][3]['sum_price']				+= ($resData['price']				- $resData['refund_price']);
									$returnItemTmp['acount4'][3]['sum_commission_price']	+= ($resData['commission_price']	- $resData['refund_commission_price']);
									$returnItemTmp['acount4'][3]['sum_feeprice']			+= ($resData['feeprice']			- $resData['refund_feeprice']);
									$returnItemTmp['acount4'][3]['sum_sales_price']			+= ($resData['sales_price']			- $resData['refund_sales_price']);
								}
							break;
							default:
								$returnItemTmp['acount1'][0]['sum_ea']					+= ($resData['ea']					- $resData['refund_ea']);
								$returnItemTmp['acount1'][0]['sum_price']				+= ($resData['price']				- $resData['refund_price']);
								$returnItemTmp['acount1'][0]['sum_commission_price']	+= ($resData['commission_price']	- $resData['refund_commission_price']);
								$returnItemTmp['acount1'][0]['sum_feeprice']			+= ($resData['feeprice']			- $resData['refund_feeprice']);
								$returnItemTmp['acount1'][0]['sum_sales_price']			+= ($resData['sales_price']			- $resData['refund_sales_price']);
							break;
						}
					}
					$dataTmp['provider_seq']	= $providerSeq;
					$dataTmp['provider_id']		= $providerId;
					$dataTmp['provider_name']	= $providerName;
					$dataTmp['period_type']		= $calcuCount;
					foreach($returnItemTmp['acount'.$calcuCount] as $key => $val){
						$dataTmp['period_count']		= $key;
						$dataTmp['sum_ea']				= $val['sum_ea'];
						$dataTmp['sum_price']			= $val['sum_price'];
						$dataTmp['sum_commission_price']= $val['sum_commission_price'];
						$dataTmp['sum_feeprice']		= $val['sum_feeprice'];
						$dataTmp['sum_sales_price']		= $val['sum_sales_price'];
						$data[] = $dataTmp;
					}
				}
			}
		}else{//이전달이면 해당통계정산데이타 기준으로 처리
			if($_PARAM['provider_seq'] && $_PARAM['provider_seq']!='all' ){
				$where_order[] = "caltb.provider_seq = '".$_PARAM['provider_seq']."'";
			}
	
			if($_PARAM['pay_period'] && $_PARAM['pay_period']!='all'){
				$where_order[] = "p.calcu_count = '".$_PARAM['pay_period']."'";
			}
	
			if($where_order){
				$str_where_order .= " AND " . implode(' AND ',$where_order) ;
			}
	
			if($where){
				$str_where_order .= " AND " .implode(' AND ',$where);
			}
			$CalSelectSql	= "SELECT
								p.provider_seq,
								p.provider_id,
								p.provider_name,
								caltb.seq,
								caltb.acc_date,
								caltb.acc_tax_type,
								caltb.acc_tax_date,
								caltb.acc_pay_type,
								caltb.acc_pay_date,
								caltb.sum_ea,
								caltb.sum_price,
								caltb.sum_feeprice,
								caltb.sum_sales_price,
								caltb.sum_commission_price,
								caltb.period_type,
								caltb.period_count,
								caltb.regist_date,
								caltb.up_date
								";
			$CalFromSql		= " FROM {$this->tb_seller_stats} caltb LEFT JOIN fm_provider as p on caltb.provider_seq = p.provider_seq ";
			$CalWhereSql	= " WHERE caltb.provider_seq!=1 and caltb.acc_date = '{$tb_act_ym}' ".$str_where_order;
			$CalGroupSql	= "";
			$CalOrderSql	= " ORDER BY caltb.provider_seq asc, period_count asc ";
	
			$CalSql			= $CalSelectSql . $CalFromSql . $CalWhereSql . $CalGroupSql . $CalOrderSql;
			$result = $this->db->query($CalSql);
			if($result) $data	= $result->result_array();
		}
		return $data;
	}
	
	/**
	* 결제수수료 임시용 실시간 정산데이타
	**/
	public function get_order_catalog_query( $_PARAM = array('list') ){

		$page		= (trim($_PARAM['page'])) ? trim($_PARAM['page']) : 1;
		$nperpage	= (trim($_PARAM['nperpage'])) ? trim($_PARAM['nperpage']) : 20;
		$limit_s	= ($page - 1) * $nperpage;
		$limit_e	= $nperpage;

		$record = "";

		//유입매체
		$sitemarketplaceloop = sitemarketplace($_PARAM['sitemarketplace'], 'image', 'array');

		if($_PARAM['header_search_keyword']) {
			$_PARAM['keyword'] = $_PARAM['header_search_keyword'];
			/*
			$_PARAM['regist_date'][0] = date('Y-m-d',strtotime("-1 month", $this->iOnTimeStamp));
			$_PARAM['regist_date'][1] = date('Y-m-d', $this->iOnTimeStamp);
			*/
		}

		### 2012-08-10
		if($_PARAM['mode']=='bank'){
			$_PARAM['regist_date'][0] = date("Y-m-d", mktime(0,0,0,date("m", $this->iOnTimeStamp)-1, date("d", $this->iOnTimeStamp), date("Y", $this->iOnTimeStamp)));
			$_PARAM['regist_date'][1] = date('Y-m-d', $this->iOnTimeStamp);
			$_PARAM['chk_step'][15] = 1;
			$where_order[] = " ord.settleprice >= '".$_PARAM['sprice']."' ";
			$where_order[] = " ord.settleprice <= '".$_PARAM['eprice']."' ";
		}
		
		if( $_PARAM['conf_regist_date'] ){
			if($_PARAM['conf_regist_date'][0]){
				$where_order[] = " exp.confirm_date >= '".$_PARAM['conf_regist_date'][0]." 00:00:00'";
			}
			if($_PARAM['conf_regist_date'][1]){
				$where_order[] = " exp.confirm_date <= '".$_PARAM['conf_regist_date'][1]." 23:59:59'";
			}
			$confirmjoin = " LEFT JOIN fm_goods_export exp ON exp.order_seq=ord.order_seq
							LEFT JOIN fm_goods_export_item item on exp.export_code = item.export_code
							";
			$confirmselect = " if(exp.bundle_export_code REGEXP '^B', exp.bundle_export_code, exp.export_code) as group_export_code,";
			$confirmgroupby = " group by group_export_code";
		}

		// 검색어
		if( $_PARAM['keyword'] ){
			if($_PARAM['body_order_search_type']) $_PARAM['keyword_type'] = $_PARAM['body_order_search_type'];
			$keyword_type = preg_replace("/[^a-z_]/i","",trim($_PARAM['keyword_type']));
			$keyword = str_replace("'","\'",trim($_PARAM['keyword']));
			if	($keyword_type == 'all')	$keyword_type	= '';

			if($keyword_type){
				$arr_field = array(
					'order_seq' => 'ord.order_seq',
					'npay_order_id' => 'ord.npay_order_id',
					'order_user_name' => 'ord.order_user_name',
					'depositor' => 'ord.depositor',
					'userid' => 'mem.userid',
					'order_cellphone' => 'ord.order_cellphone',
					'order_email' => 'ord.order_email'
				);

				if($keyword_type == 'recipient_user_name'){
					$where[] = "
						EXISTS (
							SELECT order_seq FROM fm_order_shipping WHERE order_seq = ord.order_seq and (recipient_user_name LIKE '%" . $keyword . "%')
						)					
					";
				}else{
					$where[] = $arr_field[$keyword_type]." = '" . $keyword . "'";
				}
			// 검색어가 주문번호 일 경우
			}/*else if( preg_match('/^([0-9]{13,19})$/',$keyword)  && (strlen($keyword) == 19 || strlen($keyword) == 13)  ){
				$where[] = "ord.order_seq = '" . $keyword . "'";

			// 검색어가 출고번호 일 경우
			}*/else if(preg_match('/^D([0-9]{14})$/',$keyword)){
				$where[] = "ord.order_seq = (SELECT order_seq FROM fm_goods_export WHERE export_code = '" . $keyword . "')";
			// 검색어가 반품번호 일 경우
			}else if(preg_match('/^R([0-9]{12})$/',$keyword)){
				$where[] = "ord.order_seq = (SELECT order_seq FROM fm_order_return WHERE return_code = '" . $keyword . "')";
			// 검색어가 환불번호 일 경우
			}else if(preg_match('/^C([0-9]{12})$/',$keyword)){
				$where[] = "ord.order_seq = (SELECT order_seq FROM fm_order_refund WHERE refund_code = '" . $keyword . "')";
			}else{

				if(preg_match('/^([0-9]+)$/',$keyword)){
					$add_goodsseq_where = " OR goods_seq = '" . $keyword . "' ";
				}else{
					$add_goodsseq_where = "";
				}

				$where[] = "
				(
					ord.order_seq = '" . $keyword . "' OR
					ord.npay_order_id like '%" . $keyword . "%' OR
					mem.user_name like '%" . $keyword . "%' OR
					bus.bname like '%" . $keyword . "%' OR
					order_user_name  like '%" . $keyword . "%' OR
					depositor like '%" . $keyword . "%' OR
					order_email like '%" . $keyword . "%' OR
					order_phone like '%" . $keyword . "%' OR
					order_cellphone like '%" . $keyword . "%' OR
					userid like '%" . $keyword . "%' OR
					recipient_phone LIKE '%" . $keyword . "%' OR
					recipient_cellphone LIKE '%" . $keyword . "%' OR
					recipient_user_name LIKE '%" . $keyword . "%' OR
					ifnull(linkage_mall_order_id,'') LIKE '%" . $keyword . "%' OR
					ifnull(npay_order_id,'') LIKE '%" . $keyword . "%' OR
					EXISTS (
						SELECT
							order_seq
						FROM fm_order_item WHERE order_seq = ord.order_seq and (
							goods_name LIKE '%" . $keyword . "%' OR
							goods_code LIKE '%" . $keyword . "%'
							" . $add_goodsseq_where . "
							)
					) OR
					EXISTS (
						SELECT order_seq FROM fm_goods_export WHERE order_seq = ord.order_seq and (
							delivery_number LIKE '%" . $keyword . "%' OR
							international_delivery_no LIKE '%" . $keyword . "%' OR
							recipient_user_name LIKE '%" . $keyword . "%')
					)
				)
				";
			}

		}


		// 주문일 
		$date_field = $_PARAM['date_field'] ? $_PARAM['date_field'] : 'regist_date';
		if( $date_field == 'confirm_date' ) {//구매확정
			if($_PARAM['regist_date'][0]){
				$where_order[] = "exp.confirm_date >= '".$_PARAM['regist_date'][0]." 00:00:00'";
			}
			if($_PARAM['regist_date'][1]){
				$where_order[] = "exp.confirm_date <= '".$_PARAM['regist_date'][1]." 24:00:00'";
			}
		}else{
			if($_PARAM['regist_date'][0]){
				$where_order[] = "ord.".$date_field." >= '".$_PARAM['regist_date'][0]." 00:00:00'";
			}
			if($_PARAM['regist_date'][1]){
				$where_order[] = "ord.".$date_field." <= '".$_PARAM['regist_date'][1]." 24:00:00'";
			}
		}

		// 주문상태
		if( defined('__SELLERADMIN__') === true ){//입점사
			
			// 주문일
			if($_PARAM['regist_date'][0]){
				$addFroms_where_ands = " and opt.order_seq >= '".str_replace("-","",$_PARAM['regist_date'][0])."00000000000'";
			}
			if($_PARAM['regist_date'][1]){
				$addFroms_where_ande = " and opt.order_seq <= '".str_replace("-","",$_PARAM['regist_date'][1])."24000000000'";
			}
			$addFroms			= "(
					SELECT
						IF( opt.step > 35 &&
							 opt.step < 85 &&
							 opt.step <> sub.step
							 ,opt.step - 5
							 ,opt.step
						) as step,
						opt.order_seq	AS order_seq,
						opt.item_seq	AS item_seq 
					FROM
						fm_order_item_option opt
					LEFT JOIN
						 fm_order_item_suboption sub
					ON
						opt.item_option_seq = sub.item_option_seq and sub.step > 15
					WHERE
						opt.step > 15 AND opt.provider_seq = '{$this->session->userdata['provider']['provider_seq']}'
					GROUP BY
						opt.order_seq, opt.step
				) as sord, ";

			$addSelects			= " sord.step as step, ";
			$where_order[]		= " sord.order_seq = ord.order_seq ";
			$where_order[]		= " ord.step >= 25 ";
			$where_order[]		= " ord.step <= 85 ";
			if( $_PARAM['chk_step'] ){
				$step_arr		= array_keys($_PARAM['chk_step']);
				$step_arr_join = implode("', '", $step_arr);

				// $where_order[]		= " ( sord.step in ('".$step_arr_join."') OR sord.item_seq in (select item_seq from fm_order_item_suboption where step in ('".$step_arr_join."') and order_seq = ord.order_seq ) )";

				// $where_order[]	= "( ord.order_seq in (select order_seq from fm_order_item_option where provider_seq = '".$this->session->userdata['provider']['provider_seq']."' and step in ('".$step_arr_join."') ) OR ord.order_seq in (select order_seq from fm_order_item_suboption where step in ('".$step_arr_join."') ) )";

				$where_order[]	= "
				(
					ord.order_seq in
					(
						select order_seq from fm_order_item_option where step in ('".implode("', '", $step_arr)."') and item_seq in (select item_seq from fm_order_item where provider_seq = '".$this->session->userdata['provider']['provider_seq']."')
					)
					OR ord.order_seq in
					(
						select order_seq from fm_order_item_suboption where step in ('".implode("', '", $step_arr)."')
						 and item_seq in (select item_seq from fm_order_item where provider_seq = '".$this->session->userdata['provider']['provider_seq']."')
					)
				)";
			}
		}else{
			if( $_PARAM['chk_step'] ){
				unset($arr);
				foreach($_PARAM['chk_step'] as $key => $data){
					$arr[] = "ord.step = '".$key."'";
					if( $key == 25 ) $settle_yn = 'y';
					//if( $key == 99 ) $arr[] = "ord.step = '0'";
				}
				$where_order[] = "(".implode(' OR ',$arr).")";
			}
		}

		if($_PARAM['member_seq']){
			$where_order[] = "ord.member_seq = '".$_PARAM['member_seq']."'";
		}


		if($_PARAM['order_seq'] && !$_PARAM['ajaxCall']){
			if(strstr($_PARAM['order_seq'],",")){
				$order_seq_ar = explode(",",$_PARAM['order_seq']);
				$order_seq_ar = @array_unique($order_seq_ar);
				
				foreach($order_seq_ar as $key => $data){
					$seq_arr[] = " ( ord.order_seq = '".$data."' OR ifnull(ord.linkage_mall_order_id,'') LIKE '%" . $data . "%' OR ifnull(ord.npay_order_id,'') LIKE '%" . $data . "%' OR ifnull(ord.pg_transaction_number,'') LIKE '%" . $data . "%' )";
				}
				$where_order[] = "(".implode(' OR ',$seq_arr).")";
			}else{
				$where_order[] = " ( ord.order_seq = '".$_PARAM['order_seq']."' OR ifnull(ord.linkage_mall_order_id,'') LIKE '%" . $_PARAM['order_seq'] . "%' OR ifnull(ord.npay_order_id,'') LIKE '%" . $_PARAM['order_seq'] . "%'  OR ifnull(ord.pg_transaction_number,'') LIKE '%" . $_PARAM['order_seq'] . "%' ) ";
			}
		}

		//상품에서 조회
		if($_PARAM['goods_seq']){
			$goods_seq = str_replace("'","\'",$_PARAM['goods_seq']);
			$_PARAM['regist_date'][0] = date('Y-m-d',strtotime("-1 month", $this->iOnTimeStamp));
			$_PARAM['regist_date'][1] = date('Y-m-d', $this->iOnTimeStamp);
			$_PARAM['chk_step'][75] = 1;
			$arr[] = "ord.step = '75'";
			$where_order[] = "(".implode(' OR ',$arr).")";
			$goods_seq_field = "";
			$goodsviewjoin = " LEFT JOIN fm_order_item orditm ON orditm.order_seq=ord.order_seq ";
			$where_order[]  = " orditm.goods_seq = '".$goods_seq."' ";

		}else{
			$goodsviewjoin = "";
			$goods_seq_field = "";
		}

		// 결제수단
		if( $_PARAM['payment'] ){
			unset($arr);
			foreach($_PARAM['payment'] as $key => $data){

				if(strstr($key,"npay")){
					$payment_tmp	= explode("_",$key);
					$key			= $payment_tmp[1];
					$arr[] = "(ord.pg = 'npay' and ord.payment = '".$key."')";
				}elseif( strstr($key,"naverstorefarm") || strstr($key,"open11st") || strstr($key,"coupang") ){
					$payment_tmp	= explode("_",$key);
					$linkage_mall_code	= $payment_tmp[0];
					$key				= $payment_tmp[1];
					$arr[] = "(ord.linkage_mall_code like '%".$linkage_mall_code."%')";// and ord.payment = '".$key."'
				}else{
					// 카카오페이 검색방식 변경 :: 2015-02-26 lwh
					if( $key == 'naverstorefarm' || $key == 'open11st' || $key == 'coupang' ){
						$arr[] = "ord.linkage_mall_code like '%".$key."%'";
					}elseif( $key == 'kakaopay' ){
						$arr[] = "ord.pg = '".$key."'";
					} else if ( $key == 'card' ){	//카카오 카드결제와 구분
						$arr[] = "(ord.payment = '".$key."' and ord.pg is NULL)";
					} else{
						$arr[] = "(ord.pg is NULL && ord.payment = '".$key."')";
					}
					if( in_array($key,array('virtual','account')) ){
						$arr[] = "(ord.pg is NULL && ord.payment = 'escrow_".$key."')";
					}
				}

			}

			$where_order[] = "(".implode(' OR ',$arr).")";

		}

		// 주문유형
		if( $_PARAM['ordertype'] ){
			unset($arr);
			foreach($_PARAM['ordertype'] as $key => $data){

				if($key == "personal"){
					$arr[] = " (person_seq is not null and person_seq <> '') ";
				}
				if($key == "admin"){
					$arr[] = " (admin_order is not null and admin_order <> '') ";
				}
				if($key == "change"){
					$arr[] = " (orign_order_seq is not null and orign_order_seq <> '') ";
				}
			}
			$where[] = "(".implode(' OR ',$arr).")";
		}

		// 주문상품
		if( $_PARAM['goodstype'] ){
			unset($arr);
			foreach($_PARAM['goodstype'] as $key => $data){
				if($key == "gift"){
					$arr[] = " EXISTS (select 'o' from fm_order_item where order_seq = ord.order_seq and goods_type = 'gift' limit 1) ";
				}

				if($key == "package"){
					$arr[] = " (EXISTS (select 'o' from fm_order_item_option where order_seq = ord.order_seq and package_yn = 'y' limit 1) OR EXISTS (select 'o' from fm_order_item_suboption where order_seq = ord.order_seq and package_yn = 'y' limit 1)) ";
				}

				if($key == "international_shipping"){
					$arr[] = " EXISTS (select 'o' from fm_order_item where order_seq = ord.order_seq and option_international_shipping_status = 'y' limit 1) ";
				}
			}
			$where[] = "(".implode(' OR ',$arr).")";
		}

		// 배송방법
		if( $_PARAM['search_shipping_method'] ){
			unset($arr);
			foreach($_PARAM['search_shipping_method'] as $key => $data){
				if( defined('__SELLERADMIN__') === true ){//입점사
					if($key == "delivery"){
						$arr[] = " EXISTS (select os.shipping_seq from fm_order_shipping os,fm_order_item oi where os.shipping_seq=oi.shipping_seq and os.order_seq = ord.order_seq and oi.provider_seq = '".$this->session->userdata['provider']['provider_seq']."' and os.shipping_method in ('delivery','each_delivery')) ";
					}
					if($key == "postpaid"){
						$arr[] = " EXISTS (select os.shipping_seq from fm_order_shipping os,fm_order_item oi where os.shipping_seq=oi.shipping_seq and os.order_seq = ord.order_seq and oi.provider_seq = '".$this->session->userdata['provider']['provider_seq']."' and os.shipping_method in ('postpaid','each_postpaid')) ";
					}
					if($key == "quick"){
						$arr[] = " EXISTS (select os.shipping_seq from fm_order_shipping os,fm_order_item oi where os.shipping_seq=oi.shipping_seq and os.order_seq = ord.order_seq and oi.provider_seq = '".$this->session->userdata['provider']['provider_seq']."' and os.shipping_method = 'quick') ";
					}
					if($key == "direct"){
						$arr[] = " EXISTS (select os.shipping_seq from fm_order_shipping os,fm_order_item oi where os.shipping_seq=oi.shipping_seq and os.order_seq = ord.order_seq and oi.provider_seq = '".$this->session->userdata['provider']['provider_seq']."' and os.shipping_method = 'direct') ";
					}
				}else{
					if($key == "delivery"){
						$arr[] = " EXISTS (select shipping_seq from fm_order_shipping where order_seq = ord.order_seq and shipping_method in ('delivery','each_delivery')) ";
					}
					if($key == "postpaid"){
						$arr[] = " EXISTS (select shipping_seq from fm_order_shipping where order_seq = ord.order_seq and shipping_method in ('postpaid','each_postpaid')) ";
					}
					if($key == "quick"){
						$arr[] = " EXISTS (select shipping_seq from fm_order_shipping where order_seq = ord.order_seq and shipping_method='quick') ";
					}
					if($key == "direct"){
						$arr[] = " EXISTS (select shipping_seq from fm_order_shipping where order_seq = ord.order_seq and shipping_method='direct') ";
					}

				}
			}
			$where[] = "(".implode(' OR ',$arr).")";
		}

		###
		$where[] = "hidden = 'N'";

		// 검색시간 검색 후 들어온 데이터를 무시 :: 2015-08-05 lwh
		if( $_PARAM['searchTime'] ){
			$where[] = "ord.regist_date <= '" . $_PARAM['searchTime'] . "'";
		}

		// 판매환경
		if( $_PARAM['sitetype'] ){
			unset($arr);
			foreach($_PARAM['sitetype'] as $key => $data){
				$arr[] = "ord.sitetype = '".$key."'";
			}
			$where_order[] = "(".implode(' OR ',$arr).")";
		}

		// 유입매체
		if( $_PARAM['marketplace'] ){
			unset($arr);
			foreach($_PARAM['marketplace'] as $key => $data){
				if($key == 'etc'){
					foreach($sitemarketplaceloop as $marketplace => $tmp){
						if($marketplace != 'etc') $where_marketplace[] = "ord.marketplace != '$marketplace'";
					}
					if($where_marketplace){
						$arr[] = "(".implode(' AND ',$where_marketplace).")";
					}
					$arr[] = "ord.marketplace is null";
				}else{
					$arr[] = "ord.marketplace = '".$key."'";
				}
			}
			$where_order[] = "(".implode(' OR ',$arr).")";
		}

		// 오더함
		if( $_PARAM['search_ordered'] ){
			$where_order[] = "(order_seq in (select order_seq from fm_order_item_option where ordered=1) OR order_seq in (select order_seq from fm_order_item_suboption where ordered=1))";
		}

		// 품절
		if( $_PARAM['search_runout'] ){
			$where_order[] = "(order_seq in (select order_seq from fm_order_item_option where runout=1) OR order_seq in (select order_seq from fm_order_item_suboption where runout=1))";
		}

		// 맞교환
		if( $_PARAM['search_change'] ){
			$where_order[] = "orign_order_seq !=''";
		}

		### referer
		if	($_PARAM['referer']){
			if($_PARAM['referer'] == "네이버페이"){
			$where_order[]	= " (IF(rg.referer_group_no>0, rg.referer_group_name, IF(LENGTH(ord.referer)>0,'기타','직접입력'))) in('네이버페이','체크아웃') ";
			}else{
			$where_order[]	= " (IF(rg.referer_group_no>0, rg.referer_group_name, IF(LENGTH(ord.referer)>0,'기타','직접입력'))) = '" . $_PARAM['referer'] . "' ";
			}
		}

		if( defined('__SELLERADMIN__') === true ){
			$_PARAM['provider_seq']	= $this->providerInfo['provider_seq'];
		}

		### 입점사 검색
		if( !empty($_PARAM['provider_seq']) ){
			if( $_PARAM['provider_seq'] == 999999999999 ){
				$where_provider[]	= "EXISTS (select provider_seq from fm_order_item where order_seq=ord.order_seq and provider_seq!='1')";
			}else{
				$where_provider[]	= "EXISTS (select provider_seq from fm_order_item where order_seq=ord.order_seq and provider_seq='".$_PARAM['provider_seq']."')";
			}
		}

		### 본사상품 주문
		if( !empty($_PARAM['base_inclusion']) ){
			$where_provider[] = "EXISTS (select provider_seq from fm_order_item where order_seq=ord.order_seq and provider_seq='1')";
		}

		### 공급사 검색
		if($where_provider){
			$where[] = "(".implode(" OR ",$where_provider).")";
		}

		### 2014-05-29
		if($_PARAM['linkage_mall_code'] || $_PARAM['not_linkage_order'] || $_PARAM['etc_linkage_order']){
			$arr = array();

			if($_PARAM['not_linkage_order']){
				$arr[] = "(linkage_mall_code is null or linkage_mall_code = '')";
			}
			if($_PARAM['linkage_mall_code']){
				$arr[] = "linkage_mall_code in ('".implode("','",$_PARAM['linkage_mall_code'])."')";
			}
			if($_PARAM['etc_linkage_order']){
				$this->load->model('openmarketmodel');
				$linkage_malldata		= $this->openmarketmodel->get_linkage_mall();
				$linkage_malldata		= $this->openmarketmodel->sort_linkage_mall($linkage_malldata);
				$search_mall_code = array();
				foreach($linkage_malldata as $k => $data){
					if	($data['default_yn'] == 'Y'){
						$search_mall_code[] = $data['mall_code'];
					}
				}
				for($i=0;$i<count($search_mall_code);$i++){
					if($i<10) unset($search_mall_code[$i]);
				}
				$arr[] = "linkage_mall_code in ('".implode("','",$search_mall_code)."')";
			}

			$where_order[] = "(".implode(' OR ',$arr).")";
		}
		if($_PARAM['linkage_mall_order_id']){
			$where[] = "linkage_mall_order_id in ('".implode("','",$_PARAM['linkage_mall_order_id'])."')";
		}

		if($_PARAM['chk_bundle_yn']){
			$where[] = "bundle_yn = 'y'";
		}

		if($where_order){
			$str_where_order = " WHERE " . implode(' AND ',$where_order) ;
		}
		
		//무통장 구매확정엑셀다운 $_PARAM['date_field'] == 'deposit_date' || $_GET['account_excel'] == 'buy_confirm'
		if( $_GET['account_excel'] == 'buy_confirm' ){
			$in_where_buy_confirm[] = " ord.payment = 'bank' ";
			$in_where_buy_confirm[] = " ord.npay_order_id is null ";
			$in_where_buy_confirm[] = " ord.linkage_id is null ";
			//$in_where_buy_confirm[] = " left(exp.confirm_date,7) = left(ord.deposit_date,7) ";

			if($in_where_buy_confirm){
				$where[] = "(".implode(' and ',$in_where_buy_confirm).")";
			}
		}

		if($where){
			$str_where_order = ($str_where_order)?$str_where_order." and ":" WHERE ";
			$str_where_order .= " " .implode(' AND ',$where);
		}
		
		if( $_PARAM['query_type'] == 'summary' ){
			$str_where_order = ($str_where_order)?$str_where_order." and ":" WHERE ";
			$str_where_order .= (defined('__SELLERADMIN__') === true)?" sord.step={$_PARAM['end_step']}":" ord.step={$_PARAM['end_step']} ";
		}

		if( $_GET['account_excel'] == 'account_deposit_excel' ) {//결제확인엑셀용
			//결제일 -> npay 결제일 오류
			if( $_PARAM['date_field'] == 'deposit_date' && $_GET['account_excel'] != 'buy_confirm') {
				$confirmgroupby .= " or ( ord.npay_order_id != '' ";
				$confirmgroupby .= " and ord.npay_order_pay_date >= '".$_PARAM['regist_date'][0]." 00:00:00'";
				$confirmgroupby .= " and ord.npay_order_pay_date <= '".$_PARAM['regist_date'][1]." 24:00:00'";
				$confirmgroupby .= " ) ";
			}
			$confirmgroupby .= " group by order_seq";
		}elseif( $_GET['account_excel'] == 'buy_confirm' ) {//결제확인엑셀용
			$confirmgroupby .= " group by order_seq";
		}

		// 입점사일 경우(lgs수정)
		if( defined('__SELLERADMIN__') === true )	$sort = "ORDER BY sord.step ASC, ord.regist_date DESC";
		else										$sort = "ORDER BY ord.step ASC, ord.regist_date DESC";

		// 엑셀다운용...-->
	  if	($_PARAM['nolimit'] != 'y')
			$addLimit	= " LIMIT {$limit_s}, {$limit_e} ";

		if	($_PARAM['byOption'] == 'y'){
			$joinOption	= " INNER JOIN fm_order_item ord_item ON ord.order_seq = ord_item.order_seq"
						. " INNER JOIN fm_order_item_option ord_option ON ord_item.item_seq = ord_option.item_seq ";
			$sort .= ", order_seq ";
		}
		//결제확인일 검색
		if($_GET['account_excel'] == 'buy_confirm' || $date_field == 'confirm_date' ){
			$goodsviewjoin	.= " LEFT JOIN fm_order_item ord_item ON ord.order_seq = ord_item.order_seq"
						. " LEFT JOIN fm_goods_export exp ON ord_item.order_seq = exp.order_seq ";
			$confirmselect .= "left(exp.confirm_date,7) as confirm_date_as,
				left(ord.deposit_date,7) as deposit_date_as,";
		}

		// <--- 엑셀다운용...

		if( $where_order || $where ){
			$key = get_shop_key();
 
			if	($_PARAM['query_type'] == 'summary'){
				$query	= "
				SELECT
				count(*) as cnt,
				sum(ord.settleprice) as total_settleprice
				FROM
				fm_order ord
				".$goodsviewjoin."
				LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
				LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
				LEFT JOIN fm_referer_group rg ON ord.referer_domain = rg.referer_group_url
				{$str_where_order}
				";
			}elseif	($_PARAM['query_type'] == 'total_record'){
				$query	= "
				SELECT
				count(*) as cnt,
				sum(ord.settleprice) as total_settleprice
				FROM
				fm_order ord
				".$goodsviewjoin."
				LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
				LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
				LEFT JOIN fm_referer_group rg ON ord.referer_domain = rg.referer_group_url
				{$str_where_order}
				";
			}else{
				$sort = "ORDER BY newstep ASC, ord.regist_date DESC";
				$query	= "
				SELECT
				ord.*,
				ord.recipient_user_name shipping_recipient_user_name,
				if(ord.step = 0 , 991, ord.step) as newstep,
				(select count(item_seq) from fm_order_item where order_seq = ord.order_seq and goods_type = 'gift') gift_cnt,
				(SELECT goods_name FROM fm_order_item WHERE order_seq=ord.order_seq ORDER BY item_seq LIMIT 1) goods_name,
				(SELECT count(item_seq) FROM fm_order_item WHERE order_seq=ord.order_seq) item_cnt,
				(SELECT sum(ea) FROM fm_order_item_option WHERE order_seq=ord.order_seq) opt_ea,
				(SELECT sum(ea) FROM fm_order_item_suboption WHERE order_seq=ord.order_seq) sub_ea,
				(
					SELECT userid FROM fm_member WHERE member_seq=ord.member_seq
				) userid,
				(
					SELECT AES_DECRYPT(UNHEX(email), '{$key}') as email FROM fm_member WHERE member_seq=ord.member_seq
				) mbinfo_email,
				(
					SELECT group_name FROM fm_member m,fm_member_group g WHERE m.group_seq=g.group_seq and m.member_seq=ord.member_seq
				) group_name,
				mem.rute as mbinfo_rute,
				mem.user_name as mbinfo_user_name,
				mem.blacklist as blacklist,
				ord.blacklist as ordblacklist,
				bus.business_seq as mbinfo_business_seq,
				bus.bname as mbinfo_bname,
				ord.referer, ord.referer_domain,
				IF(rg.referer_group_no>0, rg.referer_group_name, IF(LENGTH(ord.referer)>0,'기타','직접입력')) as referer_name,
				(SELECT count(package_yn) FROM fm_order_item_option WHERE order_seq=ord.order_seq and package_yn = 'y') package_yn,
				".$confirmselect."
				(SELECT count(package_yn) FROM fm_order_item_suboption WHERE order_seq=ord.order_seq and package_yn = 'y') sub_package_yn
				FROM
				fm_order ord 
				".$goodsviewjoin."
				".$confirmjoin."
				LEFT JOIN fm_member mem ON mem.member_seq=ord.member_seq
				LEFT JOIN fm_member_business bus ON bus.member_seq=mem.member_seq
				LEFT JOIN fm_referer_group rg ON (ord.referer_domain != '' and ord.referer_domain = rg.referer_group_url)
				{$str_where_order}
				".$confirmgroupby."
				{$sort}".$addLimit;
			}
			/**
			
				(
					select count(file_path) as cnt from fm_order_addfile where order_seq = ord.order_seq
				) multi_deli,
				**/ 

			return $this->db->query($query,$bind);

		}
	}

	/**
	* 정산마감일 가져오기
	* fm_account_setting
	**/
	public function get_account_setting($type='last',$sc=array()) {
		if( $type == 'last' ){
			$month	= date("Y-m-d H:i:s", $this->iOnTimeStamp);
		}elseif( $type == 'pre' ){
			$month = date("Y-m-d H:i:s", mktime(0, 0, 0, intval(date('m', $this->iOnTimeStamp)), 1, intval(date('Y', $this->iOnTimeStamp))) );
		}elseif( $type == 'cron' ){
			$month = date('Y-m-01 00:00:00', strtotime('-1 month', $this->iOnTimeStamp));
		}elseif( $type == 'month' ){
			$month = date("Y-m-d H:i:s", mktime(0, 0, 0, intval($sc['month']), 1, intval($sc['year'])) );
		}
		$sql = "select * from fm_account_setting where regist_date < '{$month}' order by seq desc limit 1";
		$result = $this->db->query($sql);
		if($result) $data	= $result->result_array();
		// mktime과 규격을 맞추기 위해 강제 2자리 출력
		$data[0]['accountall_confirm'] = sprintf("%02d",$data[0]['accountall_confirm']);
		return $data[0];
	}

	/**
	* 정산마감일 변경이력
	* fm_account_setting_list
	**/
	public function get_account_setting_list($sc=array()) {
		$sqlSelectClause	= "select seq, manager_seq, accountall_period_same, accountall_confirm, regist_date ";
		$sqlFromClause		= "from fm_account_setting ";
		$sqlWhereClause		= "";
		$sqlOrderClause		="order by seq ";
		if	($sc['orderby'] && $sc['sort']){
			$sqlOrderClause		="order by {$sc['orderby']} {$sc['sort']} ";
		}

		if	($sc['nolimit'] != 'y')
			$limit ="limit {$sc['page']}, {$sc['perpage']}";

		$sql = "
			{$sqlSelectClause}
			{$sqlFromClause}
			{$sqlWhereClause}
			{$sqlOrderClause}
		";

		$result = $this->db->query($sql.$limit);
		$data['result']	= $result->result_array();
		
		$cnt_query = 'select count(*) as cnt '. $sqlFromClause . $sqlWhereClause;
		$cntquery = $this->db->query($cnt_query);
		$cntrow = $cntquery->result_array();
		$data['count'] = $cntrow[0]['cnt'];
		return $data;
	}

	/**
	* 정산마감일 저장
	* fm_account_setting
	**/
	public function insert_account_setting($manager_seq,$accountall_period_same,$accountall_confirm){
		if(!$manager_seq || !$accountall_period_same || !$accountall_confirm){
			return false;
		}
		$regist_date = date("Y-m-d H:i:s", $this->iOnTimeStamp);
		/*
		manager_seq	관리자번호
		accountall_period_same	정산주기
		accountall_confirm	정산마감일
		*/
		$bind = array(
			$manager_seq,
			$accountall_period_same,
			$accountall_confirm,
			$regist_date
		);
		$sql = "insert into fm_account_setting set
		manager_seq = ?,
		accountall_period_same = ?,
		accountall_confirm = ?,
		regist_date = ?
		";
		return $this->db->query($sql,$bind);
	}

	/**
	* 정산크론 실행일자 가져오기
	* fm_account_confirm
	**/
	public function get_account_confirm($cofirmYearMonth){
		$sql = "select * from fm_account_confirm where confirm_year_month=? order by seq desc limit 1";

		$query = $this->db->query($sql,array($cofirmYearMonth));
		$result = $query->result_array($query);
		return $result[0];
	}

	/**
	* 정산크론 실행일자 저장
	* fm_account_confirm
	**/
	public function insert_account_confirm($confirmArr){
		if(!$confirmArr){
			return false;
		}
		/*
		confirm_year_month	정산대상월
		confirm_day			정산확정일
		confirm_start_date	정산확정시작
		confirm_end_date	정산확정끝
		*/
		$bind = array(
			$confirmArr['confirm_year_month'],
			$confirmArr['confirm_day'],
			$confirmArr['confirm_start_date'],
			$confirmArr['confirm_end_date']
		);
		$sql = "insert into fm_account_confirm set
		confirm_year_month = ?,
		confirm_day = ?,
		confirm_start_date = ?,
		confirm_end_date = ?,
		regist_date = now()
		";
		return $this->db->query($sql,$bind);
	}

	/**
	* 입점사별 정산주기 저장
	* fm_account_provider_period
	**/
	public function insert_account_provider_period($provider_seq,$accountall_period_count,$regist_date = ""){
		if(!$provider_seq || !$accountall_period_count){
			return false;
		}
		if(!$regist_date){
			$regist_date = date("Y-m-d H:i:s", $this->iOnTimeStamp);
		}
		/*
		provider_seq 입점사번호
		accountall_period_count	정산주기
		regist_date	등록일
		*/
		$bind = array(
			$provider_seq,
			$accountall_period_count,
			$regist_date
		);
		$sql = "insert into fm_account_provider_period set
		provider_seq = ?,
		accountall_period_count = ?,
		regist_date = ?
		";
		return $this->db->query($sql,$bind);
	}

	/**
	* 입점사 정산주기 가져오기
	* fm_account_provider_period
	**/
	public function get_account_provider_period($type='last',$provider_seq) {
		// 초기화
		$str_where_order = "";
		$where_order = array();

		if( $type == 'last' ){
			$month	= date("Y-m-d H:i:s", $this->iOnTimeStamp);
		}elseif( $type == 'pre' ){
			$month = date("Y-m-d H:i:s", mktime(0, 0, 0, intval(date('m', $this->iOnTimeStamp)), 1, intval(date('Y', $this->iOnTimeStamp))) );
		}elseif( $type == 'cron' ){
			$month = date('Y-m-01 00:00:00', strtotime('-1 month', $this->iOnTimeStamp));
		}

		$sql = "select
				*
				from fm_account_provider_period
				where regist_date < '{$month}'
				and provider_seq = '{$provider_seq}'
				order by regist_date desc limit 1";
		$result = $this->db->query($sql);
		if($result) $data	= $result->result_array();
		return $data[0];
	}

	/**
	* 입점사 정산주기 체크
	* fm_provider
	**/
	public function get_provider_calcu_list($type="last") {
		$return = array();
		$result = $this->providermodel->provider_list_sort();
		foreach($result as $data){
			$periodResult = $this->get_account_provider_period($type,$data['provider_seq']);
			if($periodResult)
				$data['calcu_count'] = $periodResult['accountall_period_count'];
			$return[] = $data;
		}
		return $return;
	}

	/**
	* 입점사 정산주기 체크
	* fm_provider
	**/
	public function get_provider_calcu_cnt($type="last") {
		$cnt = array();
		$cnt[1] = 0;
		$cnt[2] = 0;
		$cnt[4] = 0;
		$result = $this->providermodel->provider_list_sort();
		foreach($result as $data){
			$periodResult = $this->get_account_provider_period($type,$data['provider_seq']);
			$cnt[$periodResult['accountall_period_count']]++;
		}
		return $cnt;
	}

	/**
	* 입점사 정산주기 조건별 가져오기
	* fm_provider
	**/
	public function get_provider_search($type='last',$_PARAM = array()) {
		// 초기화
		$str_where_order = "";
		$where_order[] = "A.provider_id != 'base'";

		if( $type == 'last' ){
			$month	= date("Y-m-d H:i:s", $this->iOnTimeStamp);
		}elseif( $type == 'pre' ){
			$month = date("Y-m-d H:i:s", mktime(0, 0, 0, intval(date('m', $this->iOnTimeStamp)), 1, intval(date('Y', $this->iOnTimeStamp))) );
		}elseif( $type == 'cron' ){
			$month = date('Y-m-01 00:00:00', strtotime('-1 month', $this->iOnTimeStamp));
		}

		if($_PARAM['provider_seq'] && $_PARAM['provider_seq']!='all' ){
			$where_order[] = "A.provider_seq = '".$_PARAM['provider_seq']."'";
		}

		if($_PARAM['provider_period'] && $_PARAM['provider_period']!='all'){
			$where_order[] = "B.accountall_period_count = '".$_PARAM['provider_period']."'";
		}
		if($_PARAM['provider_name'] ){
			$where_order[] = "A.provider_name like '%".$_PARAM['provider_name']."%'";
		}

		if($where_order){
			$str_where_order .= " AND " . implode(' AND ',$where_order);
		}
		
		$sql = "select
					A.provider_seq,
					A.provider_id,
					A.provider_name,
					B.accountall_period_count as calcu_count
				from
					fm_provider A left join fm_account_provider_period B
					on( B.seq = 
						(select B1.seq from fm_account_provider_period B1
						where B1.provider_seq = A.provider_seq and B1.regist_date < '{$month}' order by regist_date desc limit 1)
					)
				where A.manager_yn = 'Y'
				".$str_where_order."
				order by A.provider_seq asc";
		$query = $this->db->query($sql);
		$provider = $query->result_array();
		return $provider;
	}

	/**
	* 입점사 테이블에 입점사별 정산주기 저장
	* fm_provider
	**/
	public function update_provider_acccount_period($provider_seq,$calcu_count){
		if(!$provider_seq || !$calcu_count){
			return false;
		}
		/*
		calcu_count	정산주기
		provider_seq 입점사번호
		*/
		$bind = array(
			$calcu_count,
			$provider_seq
		);
		$sql = "update fm_provider set
		calcu_count = ?
		where provider_seq = ?
		";
		return $this->db->query($sql,$bind);
	}

	/**
	* 3차 환불 개선으로 정산 테이블 티켓사용량 저장 처리 추가 :: 2018-11- lkh
	* 
	**/
	public function update_calculate_sales_coupon_remain($order_seq){
		if(!$order_seq){
			return false;
		}
		$data_order = $this->get_accountall_order($order_seq);

		$deposit_date	= str_replace("-","",substr($data_order['deposit_date'],0,7));
		$calculatetableck_origin = $this->get_table_ym_ck('calculate', $deposit_date);
		$data_sales_cal_order			= $this->get_act_sales_cal_total($calculatetableck_origin, $order_seq);
		if($data_sales_cal_order<=0) return;
		if( $deposit_date == date('Ym') ) {
			$calculatetableck = $calculatetableck_origin;
		}else{
			$calculatetableck = $this->tb_act_cal_sal;
		}
		/*
		order_seq 주문번호
		*/
		$selectBind = array(
			$order_seq
		);
		$resultArr = array();
		$selectSql = "select
						fge.order_seq,
						fge.export_code,
						fge.socialcp_status,
						fgei.item_seq,
						fgei.option_seq,
						fgei.coupon_value,	
						fgei.coupon_remain_value
					from fm_goods_export fge left join fm_goods_export_item fgei on (fgei.export_code = fge.export_code)
					where fge.order_seq=? and domestic_shipping_method='coupon'";
		$selectResult = $this->db->query($selectSql,array($order_seq));
		foreach($selectResult->result_array() as $selectData){
			if($resultArr[$selectData['item_seq']][$selectData['option_seq']]['coupon_value']){
				$resultArr[$selectData['item_seq']][$selectData['option_seq']]['socialcp_status'] .= "|".$selectData['socialcp_status']."(".$selectData['export_code'].")";
				$resultArr[$selectData['item_seq']][$selectData['option_seq']]['coupon_value'] .= "|".$selectData['coupon_value'];
				$resultArr[$selectData['item_seq']][$selectData['option_seq']]['coupon_remain_value'] .= "|".$selectData['coupon_remain_value'];
			}else{
				$resultArr[$selectData['item_seq']][$selectData['option_seq']]['socialcp_status'] = $selectData['socialcp_status']."(".$selectData['export_code'].")";
				$resultArr[$selectData['item_seq']][$selectData['option_seq']]['coupon_value'] = $selectData['coupon_value'];
				$resultArr[$selectData['item_seq']][$selectData['option_seq']]['coupon_remain_value'] = $selectData['coupon_remain_value'];
			}
		}
		foreach($resultArr as $key => $value){
			foreach($value as $k => $v){				
				$updateBind = array(
					$v['socialcp_status'],
					$v['coupon_value'],
					$v['coupon_remain_value'],
					$order_seq,
					$key,
					$k
				);
				$updateSql = "update {$calculatetableck} set
						socialcp_status = ?,
						coupon_value = ?,
						coupon_remain_value = ?
						where order_seq = ?
						and item_seq = ?
						and order_form_seq = ?
						and order_goods_kind = 'coupon'
						and account_type = 'order'
						";
				$this->db->query($updateSql,$updateBind);
				if($calculatetableck != $this->tb_act_cal_sal){
					$updateSalesSql = "update {$this->tb_act_cal_sal} set
							socialcp_status = ?,
							coupon_value = ?,
							coupon_remain_value = ?
							where order_seq = ?
							and item_seq = ?
							and order_form_seq = ?
							and order_goods_kind = 'coupon'
							and account_type = 'order'
							";
					$this->db->query($updateSalesSql,$updateBind);	
				}
			}
		}
	}

	/** 테스트용 lkh
	**입점사별 전월정산통계테이블 저장
	** cronjob
	**/
	public function account_seller_stats_insert_cronjob_test($tb_act_ym =null) {

		if(!$tb_act_ym) {	
			$tb_act_ym	= date("Ym",strtotime('-1 month', $this->iOnTimeStamp));  
		}

		// 정산주기별 날짜 기본값 생성 - start
		$accountAllDate = getAccountAllDate(array("s_year"=>substr($tb_act_ym,0,4),"s_month"=>substr($tb_act_ym,4,2)));
		// 정산주기별 날짜 기본값 생성 - end

		// 입점사 목록, 오름차순정렬
		$providerList = $this->providermodel->provider_list_sort();
		$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);
		for($i=0;$i < count($providerList);$i++){
			$providerSeq = $providerList[$i]['no'];
			$providerId = $providerList[$i]['provider_id'];
			$providerName = $providerList[$i]['provider_name'];
			$calcuCount = $providerList[$i]['calcu_count'];
			$whereOrder = array();
			$insertItemTmp = array();
			$strWhereOrder = "";
			if($providerSeq){
				$whereOrder[] = "caltb.provider_seq = '".$providerSeq."'";
			}

			if($whereOrder){
				$strWhereOrder .= " AND " . implode(' AND ',$whereOrder) ;
			}

			$CalSelectSql	= "SELECT 
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
										(caltb.exp_ea)
									,0)
								,0) 
								AS refund_ea,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
									(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
									,0)
								,0)
								AS refund_price,
								IFNULL(
									if( (caltb.account_type in ('refund','rollback','after_refund')), 
									( 
									 (IFNULL(caltb.salescost_event_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_multi_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_code_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_member_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_provider_rest,0))
									)
									,0)
								,0)
								AS refund_sales_provider_total,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(
											case caltb.order_referer
												when 'storefarm' then IFNULL(((caltb.api_pg_sale_price - caltb.api_pg_support_price) + caltb.api_pg_support_price),0)
												when 'open11st' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
												when 'coupang' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
											else 0
											end
										)
									,0)
								,0)
								AS refund_pg_sale_price,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.cash_sale_unit,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.cash_sale_rest,0))
									,0)
								,0)
								AS refund_cash_use,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.commission_price_rest,0))
									,0)
								,0)
								AS refund_commission_price,
								IFNULL(
									if( caltb.account_type in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
									,0)
								,0)
								AS refund_feeprice,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund') and caltb.order_goods_kind!='shipping',
										(caltb.exp_ea)
									,0)
								,0)
								AS ea,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund'), 
									(IFNULL(caltb.price,0) * IFNULL(caltb.exp_ea,0))
									,0)
								,0)
								AS price,
								IFNULL(
									if( (caltb.account_type not in ('refund','rollback','after_refund')), 
									( 
									 (IFNULL(caltb.salescost_event_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_event_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_multi_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_multi_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_code_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_code_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_member_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_member_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_fblike_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_fblike_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_mobile_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_mobile_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_referer_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_referer_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_enuri_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_enuri_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_emoney_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_emoney_provider_rest,0))
									 +
									 (IFNULL(caltb.salescost_cash_provider,0)*IFNULL(caltb.exp_ea,0)+IFNULL(caltb.salescost_cash_provider_rest,0))
									)
									,0)
								,0)
								AS sales_provider_total,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund'), 
										(
											case caltb.order_referer
												when 'storefarm' then IFNULL(((caltb.api_pg_sale_price - caltb.api_pg_support_price) + caltb.api_pg_support_price),0)
												when 'open11st' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
												when 'coupang' then IFNULL((caltb.api_pg_sale_price + caltb.api_pg_support_price),0)
											else 0
											end
										)
									,0)
								,0)
								AS pg_sale_price,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.cash_sale_unit,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.cash_sale_rest,0))
									,0)
								,0)
								AS cash_use,
								IFNULL(
									if( caltb.account_type not in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.commission_price,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.commission_price_rest,0))
									,0)
								,0)
								AS commission_price,
								IFNULL(
									if( caltb.account_type not  in ('refund','rollback','after_refund'), 
										(IFNULL(caltb.sales_unit_feeprice,0) * IFNULL(caltb.exp_ea,0) + IFNULL(caltb.sales_feeprice_rest,0))
									,0)
								,0)
								AS feeprice,
								confirm_date
								";
			$CalFromSql		= " FROM  {$caltableck} caltb ";
			$CalWhereSql	= "WHERE caltb.status in ('complete','carryover') and (provider_seq!=1 and shipping_provider_seq !=1) ".$strWhereOrder;
			$CalGroupSql	= " ";
			$CalOrderSql	= " ORDER BY caltb.deposit_date asc,caltb.status desc ";

			$CalSql			= $CalSelectSql . $CalFromSql . $CalWhereSql . $CalGroupSql . $CalOrderSql;

			$query = $this->db->query($CalSql);
			if($query && $query->num_rows()) {
				// 초기화
				$insertItemTmp['acount1'][0]['sum_ea']					= 0;
				$insertItemTmp['acount1'][0]['sum_price']				= 0;
				$insertItemTmp['acount1'][0]['sum_commission_price']	= 0;
				$insertItemTmp['acount1'][0]['sum_feeprice']			= 0;
				$insertItemTmp['acount1'][0]['sum_sales_price']		    = 0;

				$insertItemTmp['acount2'][0]['sum_ea']					= 0;
				$insertItemTmp['acount2'][0]['sum_price']				= 0;
				$insertItemTmp['acount2'][0]['sum_commission_price']	= 0;
				$insertItemTmp['acount2'][0]['sum_feeprice']			= 0;
				$insertItemTmp['acount2'][0]['sum_sales_price']		    = 0;
				$insertItemTmp['acount2'][1]['sum_ea']					= 0;
				$insertItemTmp['acount2'][1]['sum_price']				= 0;
				$insertItemTmp['acount2'][1]['sum_commission_price']	= 0;
				$insertItemTmp['acount2'][1]['sum_feeprice']			= 0;
				$insertItemTmp['acount2'][1]['sum_sales_price']		    = 0;

				$insertItemTmp['acount4'][0]['sum_ea']					= 0;
				$insertItemTmp['acount4'][0]['sum_price']				= 0;
				$insertItemTmp['acount4'][0]['sum_commission_price']	= 0;
				$insertItemTmp['acount4'][0]['sum_feeprice']			= 0;
				$insertItemTmp['acount4'][0]['sum_sales_price']		    = 0;
				$insertItemTmp['acount4'][1]['sum_ea']					= 0;
				$insertItemTmp['acount4'][1]['sum_price']				= 0;
				$insertItemTmp['acount4'][1]['sum_commission_price']	= 0;
				$insertItemTmp['acount4'][1]['sum_feeprice']			= 0;
				$insertItemTmp['acount4'][1]['sum_sales_price']		    = 0;
				$insertItemTmp['acount4'][2]['sum_ea']					= 0;
				$insertItemTmp['acount4'][2]['sum_price']				= 0;
				$insertItemTmp['acount4'][2]['sum_commission_price']	= 0;
				$insertItemTmp['acount4'][2]['sum_feeprice']			= 0;
				$insertItemTmp['acount4'][2]['sum_sales_price']		    = 0;
				$insertItemTmp['acount4'][3]['sum_ea']					= 0;
				$insertItemTmp['acount4'][3]['sum_price']				= 0;
				$insertItemTmp['acount4'][3]['sum_commission_price']	= 0;
				$insertItemTmp['acount4'][3]['sum_feeprice']			= 0;
				$insertItemTmp['acount4'][3]['sum_sales_price']		    = 0;

				foreach($query->result_array() as $data){
					if($data['ea'] == 0 && $data['price'] > 0){
						$data['ea'] = 1;
					}
					if($data['refund_ea'] == 0 && $data['refund_price'] > 0){
						$data['refund_ea'] = 1;
					}

					$data['refund_price']	= $data['refund_price'] - $data['refund_sales_provider_total'] - $data['refund_pg_sale_price'] - $data['refund_cash_use'];
					$data['price']			= $data['price'] - $data['sales_provider_total'] - $data['pg_sale_price'] - $data['cash_use'];

					// 정산 주기별 금액 누적
					switch($calcuCount){
						case 2:
							if( ($data['confirm_date'] >= $accountAllDate['cal2'][0]['start']) && ($data['confirm_date'] <= $accountAllDate['cal2'][0]['end']) ){
								$insertItemTmp['acount2'][0]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount2'][0]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount2'][0]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount2'][0]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount2'][0]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}elseif( ($data['confirm_date'] >= $accountAllDate['cal2'][1]['start']) && ($data['confirm_date'] <= $accountAllDate['cal2'][1]['end']) ){
								$insertItemTmp['acount2'][1]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount2'][1]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount2'][1]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount2'][1]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount2'][1]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}
						break;
						case 4:
							if( ($data['confirm_date'] >= $accountAllDate['cal4'][0]['start']) && ($data['confirm_date'] <= $accountAllDate['cal4'][0]['end']) ){
								$insertItemTmp['acount4'][0]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount4'][0]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount4'][0]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount4'][0]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount4'][0]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}elseif( ($data['confirm_date'] >= $accountAllDate['cal4'][1]['start']) && ($data['confirm_date'] <= $accountAllDate['cal4'][1]['end']) ){
								$insertItemTmp['acount4'][1]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount4'][1]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount4'][1]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount4'][1]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount4'][1]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}elseif( ($data['confirm_date'] >= $accountAllDate['cal4'][2]['start']) && ($data['confirm_date'] <= $accountAllDate['cal4'][2]['end']) ){
								$insertItemTmp['acount4'][2]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount4'][2]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount4'][2]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount4'][2]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount4'][2]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}elseif( ($data['confirm_date'] >= $accountAllDate['cal4'][3]['start']) && ($data['confirm_date'] <= $accountAllDate['cal4'][3]['end']) ){
								$insertItemTmp['acount4'][3]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
								$insertItemTmp['acount4'][3]['sum_price']				+= ($data['price']				- $data['refund_price']);
								$insertItemTmp['acount4'][3]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
								$insertItemTmp['acount4'][3]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
								$insertItemTmp['acount4'][3]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
							}
						break;
						default:
							$insertItemTmp['acount1'][0]['sum_ea']					+= ($data['ea']					- $data['refund_ea']);
							$insertItemTmp['acount1'][0]['sum_price']				+= ($data['price']				- $data['refund_price']);
							$insertItemTmp['acount1'][0]['sum_commission_price']	+= ($data['commission_price']	- $data['refund_commission_price']);
							$insertItemTmp['acount1'][0]['sum_feeprice']			+= ($data['feeprice']			- $data['refund_feeprice']);
							$insertItemTmp['acount1'][0]['sum_sales_price']			+= ($data['sales_price']		- $data['refund_sales_price']);
						break;
					}
				}
				foreach($insertItemTmp['acount'.$calcuCount] as $key => $val){
					$insertItem['provider_seq']			= $providerSeq;
					$insertItem['acc_date']				= $tb_act_ym;
					$insertItem['sum_ea']				= $val['sum_ea'];
					$insertItem['sum_price']			= $val['sum_price'];
					$insertItem['sum_commission_price']	= $val['sum_commission_price'];
					$insertItem['sum_feeprice']			= $val['sum_feeprice'];
					$insertItem['sum_sales_price']		= $val['sum_sales_price'];
					$insertItem['period_type']			= $calcuCount;
					$insertItem['period_count']			= $key;
					$insertItem['regist_date']			= date('Y-m-d H:i:s', $this->iOnTimeStamp);

					$result = $this->db->insert($this->tb_seller_stats, $insertItem);
				}
			}
		}
	}
	
	/**
	** 테스트용 lkh
	**전월 정산데이타 생성
	* 1. 미정산데이타 정산데이타로 생성 : not-carryover 미정산-전월
	* 2. 매출데이타중 차월데이타를 정산데이타로 생성 : overdraw 미정산-당월
	** cronjob
	**/
	public function account_carryover_overdraw_insert_cronjob_test($tb_act_ym =null,$tb_act_y_m=null) {
		if(!$tb_act_ym) {
			$tb_act_ym	= date("Ym",strtotime('-1 month', $this->iOnTimeStamp));
		}
		if(!$tb_act_y_m) {
			$tb_act_y_m	= date("Y-m",strtotime('-1 month', $this->iOnTimeStamp));
		}

		//현재 미정산데이타전체를 정산데이타 '미정산-전월' 생성
		$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);
		$this->create_carryover_account_all_cronjob_test($caltableck, $this->tb_act_cal_sal, "not-carryover", $tb_act_y_m);

	}

	/**
	* 테스트용 lkh
	* 미정산데이타전체
	* 매월 1일 전월통합정산데이타에 미정산-전월 생성
	**/
	public function create_carryover_account_all_cronjob_test($copy_table, $target_table, $status, $tb_act_y_m) {
		$not_filds	= array("seq");//예외필드

		$copy_fields = $this->db->list_fields($copy_table);
		$target_fields = $this->db->list_fields($target_table);
		
		unset($target_record,$copy_record);
		foreach($copy_fields as $k=>$copy_field) {
			if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크
			foreach($target_fields as $target_field) {
			if( in_array($target_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				if ($target_field == $copy_field ) {
					$copy_record[$k]		= $copy_field;
					$target_record[$k]		= $copy_field;
				}else{
					if($copy_field == "status"){
						$copy_record[$k]	= $copy_field;
						$target_record[$k]	= $status;
					}
				}
			}
		}

		if( count($copy_record) == count($target_record) ) {
			$sql = "INSERT INTO `{$copy_table}` ";//INSERT  REPLACE
			$copy_bind_sql = implode(", ",$copy_record);
			$sql .= " ({$copy_bind_sql}) ";
			$sql .= " select ";
			foreach($copy_record as $k=>$copy_field) {
				if( in_array($copy_field,$not_filds) ) continue;//복사 예외 또는 필드체크
				
				if( $copy_field == "status" ) {
					$target_bind_sql .= ($target_bind_sql)?", '".$target_record[$k]."'":" '".$target_record[$k]."'";
				}else{
					$target_bind_sql .= ($target_bind_sql)?", ".$target_record[$k]:$target_record[$k];
				}
			}
			$sql .= " {$target_bind_sql} ";
			$sql .= " from {$target_table} where status = 'carryover' and SUBSTRING(deposit_date,1,7) <= '".$tb_act_y_m."'  order by seq asc";//전달은제외(차월노출됨)
			
			$this->db->db_debug = false;//쿼리 구문 오류문제로 처리
			//$this->db->query($sql);
		}
	}
	
	/**
	 * 정산대상별 총 구매확정수량, 출고수량, 반품수량 얻기 from 출고데이터	
			$search_params['order_seq']		= $order_seq;
			$search_params['item_seq']		= $item_seq;
			$search_params['option_seq']	= $option_seq;
			$search_params['shipping_seq']	= $shipping_seq;
	 */
	function get_ac_target_ea_from_export($select_type = 'buyconfirm', $option_type = 'option', $search_params){

		try {
			$option_type_field = 'option_seq';
			if($option_type == 'suboption'){
				$option_type_field = 'suboption_seq';
			}

			unset($sql_params);
			$sql_params = array();

			$select_sql = '';
			$from_join_sql = '';
			$where_sql = '';
			if($select_type == 'buyconfirm'){
				$select_sql = "
					SUM(IFNULL(exp_i.reserve_buyconfirm_ea,0) + IFNULL(exp_i.reserve_destroy_ea,0)) AS reserve_buyconfirm_ea
					, SUM(IFNULL(exp_i.ea,0)) AS deliv_ea
					, SUM(IFNULL(exp_i.reserve_return_ea,0)) AS reserve_return_ea
					, MAX(exp.confirm_date) AS confirm_date
				";

				$where_sql = "
					AND exp.buy_confirm != 'none'
					AND ord.order_seq = ?
					AND exp_i.item_seq = ?
					AND exp_i.".$option_type_field." = ?
				";

				$sql_params[] = $search_params['order_seq'];
				$sql_params[] = $search_params['item_seq'];
				$sql_params[] = $search_params['option_seq'];
			}elseif($select_type == 'top_order_export_code_list'){
				$select_sql = "
					ord.order_seq AS order_seq
					, exp.export_code AS export_code
				";

				$from_join_sql = "
					INNER JOIN fm_order AS child_ord ON child_ord.top_orign_order_seq = ord.order_seq
				";

				$where_sql = "
					AND child_ord.order_seq = ?
				";
				$sql_params[] = $search_params['order_seq'];
			}elseif($select_type == 'order_export_code_list'){
				$select_sql = "
					ord.order_seq AS order_seq
					, exp.export_code AS export_code
				";

				$where_sql = "
					AND ord.order_seq = ?
				";
				$sql_params[] = $search_params['order_seq'];
			}elseif($select_type == 'coupon'){
				$select_sql = "
					exp.export_code
					, exp.socialcp_status
					, exp.socialcp_confirm_date
					, exp_i.coupon_value
					, exp_i.coupon_remain_value
				";

				$where_sql = "
					AND ord.order_seq = ?
					AND exp_i.item_seq = ?
					AND exp_i.".$option_type_field." = ?
				";

				$sql_params[] = $search_params['order_seq'];
				$sql_params[] = $search_params['item_seq'];
				$sql_params[] = $search_params['option_seq'];
			}

			$sql = "
				SELECT
					".$select_sql."
				FROM
					fm_order ord
					INNER JOIN fm_goods_export AS exp 
						ON ord.order_seq = exp.order_seq
					INNER JOIN fm_goods_export_item AS exp_i
						ON exp.export_code = exp_i.export_code
					".$from_join_sql."
				WHERE 1=1
				".$where_sql."
			";

			$query	= $this->db->query($sql, $sql_params);

			if($select_type == 'buyconfirm'){
				$result = $query->row_array();
			}elseif($select_type == 'top_order_export_code_list'){
				$result = $query->result_array();
			}elseif($select_type == 'order_export_code_list'){
				$result = $query->result_array();
			}elseif($select_type == 'coupon'){
				$cp_status = false;
				$cp_expired = '0';
				$reserve_buyconfirm_coupon_ea = 0;
				$tmp_coupon_exp_ea = 0;
				$socialcp_confirm_date = '';
				
				if($query){
					$exportDataList	= $query->result_array();
					foreach($exportDataList as $exportData){
						if(in_array($exportData['socialcp_status'], array(6,8))){
							$cp_status = true;
						}
						if(in_array($exportData['socialcp_status'], array(4))){	// 전체낙장일 경우 정산수량을 강제로 조정하여 처리
							$cp_expired = '1';							
						}
						$usePrice = $exportData['coupon_value'] - $exportData['coupon_remain_value'];
						if($usePrice > 0){	// 쿠폰 값어치를 모두 사용했을 때
							$tmp_coupon_exp_ea++;
						}
						if($socialcp_confirm_date < $exportData['socialcp_confirm_date']){
							$socialcp_confirm_date = $exportData['socialcp_confirm_date'];
						}
					}
				}

				//미정산테이블
				if( $tmp_coupon_exp_ea==0 && $cp_status ) {
					$reserve_buyconfirm_coupon_ea = 0;
				}else{
					$reserve_buyconfirm_coupon_ea = $tmp_coupon_exp_ea;
				}
				$result = array(
					'reserve_buyconfirm_coupon_ea' => $reserve_buyconfirm_coupon_ea,
					'cp_expired' => $cp_expired,
					'socialcp_confirm_date' => $socialcp_confirm_date,
				);
			}

		} catch (Exception $exc) {
			debug('accountmodel->insert_calculate_sales_buyconfirm'.chr(10).$exc->getTraceAsString());
		}
		return $result;
	}
	/**
	 * 정산대상별 총 주문수량 얻기 from 주문정보
			$search_params['order_seq']		= $order_seq;
			$search_params['item_seq']		= $item_seq;
			$search_params['option_seq']	= $option_seq;
			$search_params['shipping_seq']	= $shipping_seq;
	 */
	function get_ac_target_ea_from_order($select_type = 'order', $option_type = 'option', $search_params){
		try {
			$option_type_field = 'option_seq';
			$option_type_table = 'fm_order_item_option';
			if($option_type == 'suboption'){
				$option_type_field = 'suboption_seq';
				$option_type_table = 'fm_order_item_suboption';
			}
			unset($sql_params);
			$sql_params = array();


			$select_sql = '';
			$from_join_sql = '';
			$where_sql = '';
			if($select_type == 'order'){	// 주문건수
				$select_sql = " 
					SUM(IFNULL(opt.ea,0)) AS ea
					, SUM(IFNULL(opt.step85,0)) AS step85
				 ";
				$from_join_sql = "";
				$where_sql = "
					AND ord.order_seq = ?
					AND ord_i.item_seq = ?
					AND ord_i.shipping_seq = ?
					AND opt.item_".$option_type_field." = ?
				";
				$sql_params[] = $search_params['order_seq'];
				$sql_params[] = $search_params['item_seq'];
				$sql_params[] = $search_params['shipping_seq'];
				$sql_params[] = $search_params['option_seq'];
			}elseif($select_type == 'order_info'){	// 주문정보
				$select_sql = " 
					ord.order_seq as order_seq
					, ord_i.item_seq as item_seq
					, ord_i.goods_seq as goods_seq
					, ord_i.shipping_seq as shipping_seq
					, opt.item_".$option_type_field." as option_seq
					, '".$option_type."' AS option_type
					, top_item_".$option_type_field." AS top_option_seq
				 ";
				$from_join_sql = "";
				$where_sql = "
					AND ord.order_seq = ?
				";
				$sql_params[] = $search_params['order_seq'];
			}elseif($select_type == 'child_order_list'){	// 동일 상품 종의 자식주문 목록
				$select_sql = "
					ord.order_seq as order_seq
					, ord_i.item_seq as item_seq
					, ord_i.shipping_seq as shipping_seq
					, opt.item_".$option_type_field." as option_seq
					, '".$option_type."' AS option_type
					, top_item_".$option_type_field." AS top_option_seq
				";
				$from_join_sql = "
				";
				$where_sql = "
					AND ord.top_orign_order_seq = ?
					AND opt.top_item_".$option_type_field." = ?
				";
				$sql_params[] = $search_params['order_seq'];
				$sql_params[] = $search_params['option_seq'];
			}elseif($select_type == 'child_order_all'){	// 전체 자식 주문 목록
				$select_sql = "
					ord.order_seq as order_seq
				";
				$from_join_sql = "
				";
				$where_sql = "
					AND ord.top_orign_order_seq = ?
				";
				$sql_params[] = $search_params['order_seq'];
			}

			$sql = "
				SELECT
					".$select_sql."
				FROM
					fm_order AS ord
					INNER JOIN fm_order_item AS ord_i
						ON ord.order_seq = ord_i.order_seq
					INNER JOIN ".$option_type_table." AS opt 
						ON ord_i.order_seq = opt.order_seq
							AND ord_i.item_seq = opt.item_seq
					".$from_join_sql."
				WHERE ord.step <> '99'
					".$where_sql."
			";

			$query	= $this->db->query($sql, $sql_params);

			if($select_type == 'order'){	// 주문건수
				$result = $query->row_array();
			}elseif($select_type == 'order_info'){	// 주문정보
				$result = $query->result_array();
			}elseif($select_type == 'child_order_list'){	// 동일 상품 종의 자식주문 목록
				$result = $query->result_array();
				// 동일 상품 종이 출고 후 교환에 의해 여러 상품 종인것처럼 분리될 수 있음.
				// 이로인해 자식주문의 환불수량을 체크할 때 해당 동일 상품종의 추적이 불가능함.
				//  = 자식주문 아이템번호((sub)option_seq)는 다수로 생성될 수 있으나
				//    환불아이템번호에 사용되는 부모주문아이템번호는 유일함(top_(sub)option_seq)을 이용하여 해결
				// 때문에 부모주문아이템번호를 이용하여 유니크한 상품종 데이터를 반환. 
				// 추가옵션 테이블에 top_item_seq 저장하거나 환불아이템정보를 수정할 경우 로직 변경 가능
				// by hed
				$arr_tmp = $result;
				unset($result);
				$result = array();
				foreach($arr_tmp as $dumy_row){
					$same_top_option_seq = false;
					foreach($result as $uniq_row){
						if($dumy_row['top_option_seq'] == $uniq_row['top_option_seq']
							&& $dumy_row['order_seq'] == $uniq_row['order_seq']){
							$same_top_option_seq = true;
						}
					}
					if(!$same_top_option_seq){
						$result[] = $dumy_row;
					}
				}
			}elseif($select_type == 'child_order_all'){	// 전체 자식 목록
				$result = $query->result_array();
				$arr_tmp = $result;
				unset($result);
				$result = array();
				foreach($arr_tmp as $dumy_row){
					$same_top_option_seq = false;
					foreach($result as $uniq_row){
						if($dumy_row['order_seq'] == $uniq_row['order_seq']){
							$same_top_option_seq = true;
						}
					}
					if(!$same_top_option_seq){
						$result[] = $dumy_row;
					}
				}
			}

		} catch (Exception $exc) {
			debug('accountmodel->get_ac_target_ea_from_order'.chr(10).$exc->getTraceAsString());
		}
		return $result;
	}
	/**
	 * 정산대상별 총 환불수량 얻기 from 환불정보
			$search_params['order_seq']		= $order_seq;
			$search_params['item_seq']		= $item_seq;
			$search_params['option_seq']	= $option_seq;
			$search_params['shipping_seq']	= $shipping_seq;
	 */
	function get_ac_target_ea_from_refund($select_type = 'refund', $option_type = 'option', $search_params){
		
		try {
			$option_type_field = 'option_seq';
			$option_type_table = 'fm_order_item_option';
			if($option_type == 'suboption'){
				$option_type_field = 'suboption_seq';
				$option_type_table = 'fm_order_item_suboption';
			}
			unset($sql_params);
			$sql_params = array();


			$select_sql = '';
			$from_join_sql = '';
			$where_sql = '';
			$orderby_sql = '';
			$limit_sql = '';
			if($select_type == 'refund'){	// 환불건수
				$select_sql = "
					IFNULL(ord_refund_i.ea,0) AS refund_ea 
					, ord_refund.refund_date as refund_date
					, CASE 
						WHEN ord_return.status = 'complete' AND ord_refund.status = 'complete'
						THEN IFNULL(ord_refund_i.ea,0)
						ELSE 0
					END AS refund_complete_ea
					, ord_return.refund_ship_duty AS refund_ship_duty
				";
				$from_join_sql = "
					INNER JOIN fm_order_return AS ord_return
						ON ord.order_seq = ord_return.order_seq

					INNER JOIN fm_order_refund AS ord_refund
						ON ord.order_seq = ord_refund.order_seq
							AND ord_return.refund_code = ord_refund.refund_code
					INNER JOIN fm_order_refund_item AS ord_refund_i
						ON ord_refund.refund_code = ord_refund_i.refund_code
				";

				$where_sql = "
					AND ord_refund_i.item_seq = ?
					AND ord_refund_i.".$option_type_field." = ?
					AND (
						ord_refund.after_refund is null
						or ord_refund.after_refund = ''
					)
				";
				// 추가옵션의 경우 필수옵션의 option_seq를 동일하게 가지고 있어 중복 row 제거
				if($option_type == 'option'){
					$where_sql .= "
						AND ord_refund_i.suboption_seq = '0'
					";
				}

				$orderby_sql = ' ORDER BY ord_return.return_code ASC, ord_refund.refund_code ASC ';	// 맨 마지막 반품건이 제일 마지막에 오도록 정렬
				
				$sql_params[] = $search_params['item_seq'];
				$sql_params[] = $search_params['option_seq'];

			}elseif($select_type == 'child_refund'){	// 자식주문 환불건수
				$select_sql = "
					SUM(IFNULL(ord_refund_i.ea,0)) AS refund_ea 
					, MAX(ord_refund.refund_date) as refund_date
					, SUM(CASE 
						WHEN ord_return.status = 'complete' AND ord_refund.status = 'complete'
						THEN IFNULL(ord_refund_i.ea,0)
						ELSE 0
					END) AS refund_complete_ea
					, ord_return.refund_ship_duty AS refund_ship_duty
				";
				$from_join_sql = "
					INNER JOIN fm_order_return AS ord_return
						ON ord.order_seq = ord_return.order_seq					
					INNER JOIN fm_order_return_item AS ord_return_i
						ON ord_return.return_code = ord_return_i.return_code

					INNER JOIN fm_order_refund AS ord_refund
						ON ord.top_orign_order_seq = ord_refund.order_seq
							AND ord_return.refund_code = ord_refund.refund_code
					INNER JOIN fm_order_refund_item AS ord_refund_i
						ON ord_refund.refund_code = ord_refund_i.refund_code
				";

				$where_sql = "
					AND ord_return_i.item_seq = ?
					AND ord_return_i.".$option_type_field." = ?
					AND ord_refund_i.item_seq = ?
					AND ord_refund_i.".$option_type_field." = ?
					AND (
						ord_refund.after_refund is null
						or ord_refund.after_refund = ''
					)
				";
				$sql_params[] = $search_params['item_seq'];
				$sql_params[] = $search_params['option_seq'];
				// 추가옵션의 경우 환불 테이블의 item_seq를 top_item_seq가 아닌 자기 자신의 item_seq를 입력함 by hed
				if($option_type == 'suboption'){
					$sql_params[] = $search_params['item_seq'];
				}else{
					$sql_params[] = $search_params['top_item_seq'];
				}
				$sql_params[] = $search_params['top_option_seq'];
			}elseif($select_type == 'cancel'){	// 취소건수
				$select_sql = "
					SUM(IFNULL(ord_refund_i.ea,0)) AS refund_ea 
					, MAX(ord_refund.refund_date) as refund_date
					, SUM(CASE 
						WHEN ord_refund.status = 'complete'
						THEN IFNULL(ord_refund_i.ea,0)
						ELSE 0
					END) AS refund_complete_ea
				";
				$from_join_sql = "
					INNER JOIN fm_order_refund AS ord_refund
						ON ord.order_seq = ord_refund.order_seq
							AND refund_type = 'cancel_payment'
					INNER JOIN fm_order_refund_item AS ord_refund_i
						ON ord_refund.refund_code = ord_refund_i.refund_code
				";

				$where_sql = "
					AND ord_refund_i.item_seq = ?
					AND ord_refund_i.".$option_type_field." = ?
					AND (
						ord_refund.after_refund is null
						or ord_refund.after_refund = ''
					)
				";
				// 추가옵션의 경우 필수옵션의 option_seq를 동일하게 가지고 있어 중복 row 제거
				if($option_type == 'option'){
					$where_sql .= "
						AND ord_refund_i.suboption_seq = '0'
					";
				}
				$sql_params[] = $search_params['item_seq'];
				$sql_params[] = $search_params['option_seq'];

			}

			$sql = "
				SELECT
					".$select_sql."
				FROM
					fm_order AS ord
					".$from_join_sql."
				WHERE 1=1
					".$where_sql."
					AND ord.order_seq = ?
				".$orderby_sql."
				".$limit_sql."
			";
			$sql_params[] = $search_params['order_seq'];

			$query	= $this->db->query($sql, $sql_params);
			if($select_type == 'refund'){
				$tmp_result = $query->result_array();
				$result = array();
				foreach($tmp_result as $row_result){
					$result['refund_ea']				+= $row_result['refund_ea'];
					if($result['refund_date'] < $row_result['refund_date']){
						$result['refund_date']				= $row_result['refund_date'];
					}
					$result['refund_complete_ea']		+= $row_result['refund_complete_ea'];
					$result['refund_ship_duty']			= $row_result['refund_ship_duty'];
				}
			}else{
				$result = $query->row_array();
			}

		} catch (Exception $exc) {
			debug('accountmodel->get_ac_target_ea_from_refund'.chr(10).$exc->getTraceAsString());
		}
		return $result;
	}
	
	/**
	 * 정상대상의 정산 관련 수량 계산
	 * exp_ea가 0 보다 크고 ac_ea가 0일때 정산 가능
	 * [주문정보] - 주문수량
	 * order_seq, item_seq, order_form_seq, order_type, shipping_group_seq
	 *  : 위 기준으로 fm_order, fm_order_item, fm_order_item_(sub)option의 정보를 조회한다.
	 * [출고정보] - 구매확정수량
	 * order_seq, item_seq, order_form_seq, order_type, shipping_group_seq
	 *  : 위 기준으로 fm_goods_export, fm_goods_export_item의 정보를 조회한다.
	 * [환불정보] - 환불수량
	 * order_seq, item_seq, order_form_seq, order_type, shipping_group_seq
	 *  : 위 기준으로 fm_order_return, fm_order_return_item, fm_order_refund, fm_order_refund_item의 정보를 조회한다.
	 * [자식주문정보] - 교환주문의 구매확정수량
	 * order_seq, item_seq, order_form_seq, order_type, shipping_group_seq
	 *  : 위 기준으로 fm_order의 top_orign_order_seq를 참조하고
	 *    fm_order_return, fm_order_return_item, fm_order_refund, fm_order_refund_item의 정보를 조회한다.
	 * [결과수량]
	 *  exp_ea(정산수량) = 주문수량 - 환불수량
	 *  ac_ea(남은수량) = 주문수량 - 구매확정수량 - 환불수량
	 * @param type $orderdata
	 * @return int
	 */
	function calculate_account_ea($orderdata){
		$result = array();
		$result['exp_ea'] = 0 ;
		$result['ac_ea'] = 0 ;
		$base_time = ' 00:00:00';
		$base_timestamp = '0000-00-00'.$base_time;

		try {
			// 검색 파라미터 정리
			unset($search_params);
			$search_params					= array();
			$search_params['order_seq']		= $orderdata['order_seq'];
			$search_params['item_seq']		= $orderdata['item_seq'];
			$search_params['option_seq']	= $orderdata['order_form_seq'];
			$search_params['shipping_seq']	= $orderdata['shipping_group_seq'];

			// 배송비의 경우 동일 출고그룹(shipping_group_seq)의 모든 정산이 완료되었을 때 정산확정 가능하므로
			// 동일 출고그룹의 총 정산수량을 계산
			if($orderdata['order_type'] == 'shipping'){

				// 정산 조회용 테이블
				$tb_act_ym	=	$orderdata['acc_table'];
				if(empty($tb_act_ym)){
					$tb_act_ym	= str_replace("-","",substr($orderdata['deposit_date'],0,7));
				}
				$caltableck		= $this->accountallmodel->get_table_ym_ck('calculate',$tb_act_ym);

				$order_seq								= $orderdata['order_seq'];
				unset($search_params);
				$search_params							= array();
				$search_params['order_seq']				= $orderdata['order_seq'];
				$search_params['shipping_group_seq']	= $orderdata['shipping_group_seq'];
				$search_params['account_target']		= 'calculate';//정산대상만
				$search_params['not_order_type']		= array('shipping');//배송비 제외
				$get_account_all	= $this->accountallmodel->get_account_calculate_sales($caltableck, $order_seq, $search_params,'order');//미정산데이타의 동일배송그룹 주문데이타

				// 배송비를 제외한 동일 출고그룹의 총 정산수량을 계산
				foreach($get_account_all as $k => $orderdata_shipping) {
					$arr_account_confirm_ea[] = $this->accountallmodel->calculate_account_ea($orderdata_shipping);
				}
				$result['exp_ea'] = 1;	// 배송비의 정산수량은 언제나 1로 고정
				$result['ac_ea'] = 1;	// 배송비 정산 완료 여부 0이 될때 정산 완료

				if($arr_account_confirm_ea){
					$tmp_ea = array();
					foreach($arr_account_confirm_ea as $account_confirm_ea){
						$tmp_ea['ea'] += $account_confirm_ea['ea'];
						$tmp_ea['exp_ea'] += $account_confirm_ea['exp_ea'];
						$tmp_ea['ac_ea'] += $account_confirm_ea['ac_ea'];
						$tmp_ea['cancel_refund_ea'] += $account_confirm_ea['cancel_refund_ea']; // 출고전 취소건수
						// 동일 배송그룹 중 가장 마지막에 처리된 완료일자를 기준으로 처리
						if(empty($tmp_ea['confirm_date']) || $tmp_ea['confirm_date'] < $account_confirm_ea['confirm_date']){
							$tmp_ea['confirm_date'] = $account_confirm_ea['confirm_date'];
						}
						
						// 판매자 귀책사유일 경우 배송비 정산 안 함
						$tmp_ea['refund_ship_duty'] = $account_confirm_ea['refund_ship_duty'];
					}
					if($tmp_ea['ac_ea'] == 0){								// 동일출고그룹의 남은 처리 수량이 0개일때 배송비 정산
						$result['ac_ea'] = 0;
						$result['confirm_date'] = $tmp_ea['confirm_date'];
					}

					// 되돌리기 or 결제취소로 인해 모두 취소 되었을 경우 배송비 정산하지 않음.
					if($tmp_ea['cancel_refund_ea'] == $tmp_ea['ea'] && $tmp_ea['ac_ea'] == 0){
						$result['exp_ea'] = 0;
					}

					// 판매자 귀책사유일 경우 배송비 정산 안 함.
					if($tmp_ea['refund_ship_duty'] == 'seller' && $tmp_ea['exp_ea'] == 0){
						$result['exp_ea'] = 0;
						
						// 네이버페이의 경우 초도배송비를 환불해주는 판매자귀책사유지만 반품배송비는 발생하는 케이스가 있음
						// 이는 솔루션에서 발생/지원하지 않는 케이스이며 반품배송비 정산인데 귀책사유가 판매자케이스의 경우 강제로 정산한다.
						if($orderdata['account_type'] == 'return'){
							$result['exp_ea'] = 1;
						}
					}

					if($orderdata['shipping_provider_seq'] == '1'){			// 본사배송인 경우 정산하지 않음
						$result['exp_ea'] = 1;	
						$result['ac_ea'] = 1;
					}
				}
				return $result;
			}

			// [주문정보] - 주문수량, 출고전 취소 수량
			$order_ea = $this->get_ac_target_ea_from_order('order', $orderdata['order_type'], $search_params);

			// [출고정보] - 구매확정수량
			$buyconfirm_ea = $this->get_ac_target_ea_from_export('buyconfirm', $orderdata['order_type'], $search_params);
			if($buyconfirm_ea['confirm_date']){
				$buyconfirm_ea['confirm_date'] = $buyconfirm_ea['confirm_date'].$base_time;
			}else{
				$buyconfirm_ea['confirm_date'] = $base_timestamp;
			}

			// [출고정보] - 티켓 정산 확정수량
			$buyconfirm_coupon_ea = $this->get_ac_target_ea_from_export('coupon', $orderdata['order_type'], $search_params);

			// [환불정보] - 환불수량
			$refund_ea = $this->get_ac_target_ea_from_refund('refund', $orderdata['order_type'], $search_params);

			// [환불정보] - 취소수량
			$cancel_ea = array();
			$tmp_cancel_ea = $this->get_ac_target_ea_from_refund('cancel', $orderdata['order_type'], $search_params);
			foreach($tmp_cancel_ea as $k=>$v){
				// 구매확정일 예외 처리
				if($k == 'refund_date'){
					if($v){
						if(empty($cancel_ea['cancel_'.$k]) || $cancel_ea['cancel_'.$k] < $v) {
							$cancel_ea['cancel_'.$k] = $v;
						}
					}else{
						$cancel_ea['cancel_'.$k] = $base_timestamp;
					}
				}else{
					$cancel_ea['cancel_'.$k] = $cancel_ea['cancel_'.$k] + $v;
				}
			}

			// [자식주문정보] - 교환주문의 구매확정수량과 환불수량
			$child_order_list 				= $this->get_ac_target_ea_from_order('child_order_list', $orderdata['order_type'], $search_params);
			$arr_child_buyconfirm_ea 		= array();
			$arr_child_buyconfirm_coupon_ea = array();
			$arr_child_refund_ea 			= array();
			$child_buyconfirm_ea 			= array();
			$child_buyconfirm_coupon_ea 	= array();
			$child_child_refund_ea 			= array();
			foreach($child_order_list as $child_order){
				$child_order['top_item_seq'] 		= $orderdata['item_seq'];
				$child_order['top_option_seq'] 		= $orderdata['order_form_seq'];
				$arr_child_buyconfirm_ea[] 			= $this->get_ac_target_ea_from_export('buyconfirm', $child_order['option_type'], $child_order);
				$arr_child_buyconfirm_coupon_ea[] 	= $this->get_ac_target_ea_from_export('coupon', $child_order['option_type'], $child_order);
				$arr_child_refund_ea[] 				= $this->get_ac_target_ea_from_refund('child_refund', $child_order['option_type'], $child_order);
			}
			foreach($arr_child_buyconfirm_ea as $child_ea){
				foreach($child_ea as $k=>$v){
					// 구매확정일 예외 처리
					if($k == 'confirm_date'){
						if($v){
							if(empty($child_buyconfirm_ea['child_'.$k]) || $child_buyconfirm_ea['child_'.$k] < $v) {
								$child_buyconfirm_ea['child_'.$k] = $v.$base_time;
							}
						}else{
							$child_buyconfirm_ea['child_'.$k] = $base_timestamp;
						}
					}else{
						$child_buyconfirm_ea['child_'.$k] = $child_buyconfirm_ea['child_'.$k] + $v;
					}
				}
			}
			foreach($arr_child_buyconfirm_coupon_ea as $child_ea){
				foreach($child_ea as $k=>$v){
					// 구매확정일 예외 처리
					if($k == 'confirm_date'){
						if($v){
							if(empty($child_buyconfirm_coupon_ea['child_'.$k]) || $child_buyconfirm_coupon_ea['child_'.$k] < $v) {
								$child_buyconfirm_coupon_ea['child_'.$k] = $v.$base_time;
							}
						}else{
							$child_buyconfirm_coupon_ea['child_'.$k] = $base_timestamp;
						}
					}else{
						$child_buyconfirm_coupon_ea['child_'.$k] = $child_buyconfirm_coupon_ea['child_'.$k] + $v;
					}
				}
			}
			foreach($arr_child_refund_ea as $child_ea){
				foreach($child_ea as $k=>$v){
					// 환불 완료일 예외 처리
					if($k == 'refund_date'){
						if(empty($child_buyconfirm_ea['child_'.$k]) || $child_buyconfirm_ea['child_'.$k] < $v) {
							$child_buyconfirm_ea['child_'.$k] = $v;
						}
					}else{
						$child_child_refund_ea['child_'.$k] = $child_child_refund_ea['child_'.$k] + $v;
					}
				}
			}

			// 티켓상품인경우 구매확정 수량이 있다면 정산되야 하므로 환불과 취소 수량은 제거함
			if($orderdata['order_goods_kind'] == 'coupon'){
				$total_coupon_ea = $buyconfirm_coupon_ea['reserve_buyconfirm_coupon_ea'] + $child_buyconfirm_coupon_ea['child_reserve_buyconfirm_coupon_ea'];
				$cancel_ea['cancel_refund_ea']							= ($cancel_ea['cancel_refund_ea'] >= $total_coupon_ea) ? $cancel_ea['cancel_refund_ea'] - $total_coupon_ea : $cancel_ea['cancel_refund_ea'];
				$refund_ea['refund_ea']									= ($refund_ea['refund_ea'] >= $total_coupon_ea) ? $refund_ea['refund_ea'] - $total_coupon_ea : $refund_ea['refund_ea'];
				$child_child_refund_ea['child_refund_ea']				= ($child_child_refund_ea['child_refund_ea'] >= $total_coupon_ea) ? $child_child_refund_ea['child_refund_ea'] - $total_coupon_ea : $child_child_refund_ea['child_refund_ea'];
				$refund_ea['refund_complete_ea']						= ($refund_ea['refund_complete_ea'] >= $total_coupon_ea) ? $refund_ea['refund_complete_ea'] - $total_coupon_ea : $refund_ea['refund_complete_ea'];
				$child_child_refund_ea['child_refund_complete_ea']		= ($child_child_refund_ea['child_refund_complete_ea'] >= $total_coupon_ea) ? $child_child_refund_ea['child_refund_complete_ea'] - $total_coupon_ea : $child_child_refund_ea['child_refund_complete_ea'];
			}

			$result['exp_ea'] = $order_ea['ea']												// 주문수량
								- $cancel_ea['cancel_refund_ea']							// 출고전취소신청수량
								- $refund_ea['refund_ea']									// 원주문 환불신청수량
								- $child_child_refund_ea['child_refund_ea']					// 자식주문 환불신청수량
			;
			$result['ac_ea'] = $order_ea['ea']												// 주문수량
								- $cancel_ea['cancel_refund_complete_ea']					// 출고전취소수량
								- $buyconfirm_ea['reserve_buyconfirm_ea']					// 원주문 구매확정수량
								- $buyconfirm_coupon_ea['reserve_buyconfirm_coupon_ea']					// 원주문 구매확정수량
								- $refund_ea['refund_complete_ea']							// 원주문 환불완료수량
								- $child_buyconfirm_ea['child_reserve_buyconfirm_ea']		// 자식주문 구매확정수량
								- $child_buyconfirm_coupon_ea['child_reserve_buyconfirm_coupon_ea']		// 자식주문 구매확정수량
								- $child_child_refund_ea['child_refund_complete_ea']		// 자식주문 환불완료수량
			;

			// 티켓상품인경우 전체낙장되었을 경우 강제로 정산확정 처리
			if($orderdata['order_goods_kind'] == 'coupon' && $buyconfirm_coupon_ea['cp_expired'] == '1'){
				$result['ac_ea'] = 0;
			}

			$result = array_merge($result, $order_ea, $buyconfirm_ea, $buyconfirm_coupon_ea, $refund_ea, $child_buyconfirm_ea, $child_buyconfirm_coupon_ea, $child_child_refund_ea, $cancel_ea);

			// 구매확정일/반품일/환불일 
			if($result['confirm_date'] < $result['refund_date']){$result['confirm_date'] = $result['refund_date'];}
			if($result['confirm_date'] < $result['child_confirm_date']){$result['confirm_date'] = $result['child_confirm_date'];}
			if($result['confirm_date'] < $result['child_refund_date']){$result['confirm_date'] = $result['child_refund_date'];}
			if($result['confirm_date'] < $cancel_ea['cancel_refund_date']){$result['confirm_date'] = $cancel_ea['cancel_refund_date'];}
			if($result['confirm_date'] < $buyconfirm_coupon_ea['socialcp_confirm_date']){$result['confirm_date'] = $buyconfirm_coupon_ea['socialcp_confirm_date'];}


			// 맞교환에 의해 발생한 주문의 경우 정산확정대상이 아니므로 정산수량 및 처리 수량을 0으로 고정한다.
			if($orderdata['account_type'] == 'exchange' ){
				$result['exp_ea'] = 0;
				$result['ac_ea'] = 0;
				return $result;
			}
		} catch (Exception $exc) {
			debug('accountmodel->calculate_account_ea'.chr(10).$exc->getTraceAsString());
		}
		return $result;
	}
	
	/**
	 * 정산확정처리
	 * 기존 구매확정 시와 환불완료시에 나눠져있던 정산확정처리 로직을 하나로 통합
	 */
	function confirm_account($confirm_account_params){

		try {
			$ac_target				= $confirm_account_params['ac_target'];
			$acloop					= $confirm_account_params['acloop'];
			$deposit_date_ck		= $confirm_account_params['deposit_date_ck'];
			$accountConfirmDate  	= $confirm_account_params['accountConfirmDate'];
			$buyconfirm_ea			= $confirm_account_params['buyconfirm_ea'];
			$buyconfirm_ea_ok		= $confirm_account_params['buyconfirm_ea_ok'];
			$confirm_date			= $confirm_account_params['confirm_date'];
			$order_seq				= $confirm_account_params['order_seq'];
			$order_params			= $confirm_account_params['order_params'];

			$confirm_done = $ac_target;
			if( $ac_target && $acloop ) {//전체 미정산 매출테이블
				// 정산 마감일이 각각 다른 문제로 추가 지난달 데이터만 :: 2018-07-09 lkh
				if( ($deposit_date_ck == date('Ym',strtotime("-1 month", $this->iOnTimeStamp))) && ($accountConfirmDate > date('d', $this->iOnTimeStamp)) ){
					$tb_act_ym	=	$deposit_date_ck;
				}
				if(!$tb_act_ym) $tb_act_ym	=	date('Ym', $this->iOnTimeStamp);
				$calculatetableck = $this->get_table_ym_ck('calculate',$tb_act_ym);
				//debug_var("insert_calculate_sales_check tableck->".$calculatetableck);//return;//exit;
				if($calculatetableck) {//동일배송그룹내 적립금지급수량과 전체구매수량이 동일하면 정산처리
					//debug_var("accountallmodel -> insert_calculate_sales_buyconfirm () ".__LINE__);
					//debug_var($get_account_all);
					//debug_var($acloop);
					foreach($acloop as $accountdata) {
						if( $accountdata['account_target'] != 'calculate' ) continue;
						if(!$accountdata['refund_code']) $accountdata['refund_code'] = "";
						if(!$accountdata['return_code']) $accountdata['return_code'] = "";
						if(!$accountdata['refund_type']) $accountdata['refund_type'] = "";
						unset($wherequery,$bind);
						foreach($this->acc_requireds as $val){//if($val == 'deposit_date') continue;
							$acrealdata = ($accountdata[$val]) ? $accountdata[$val]:'';
							$wherequery[]	= $val."=?";
							$bind[]			= $acrealdata;
						}
						$query = "select seq,status,account_target from {$calculatetableck} where ".implode(" AND ",$wherequery)." limit 1";
						$query = $this->db->query($query,$bind);//debug_var($this->db->last_query()); //status ='overdraw' and 
						if($query) $requireddata = $query->row_array();
						// 정산 마감일이 각각 다른 문제로 추가 지난달 데이터만 :: 2018-07-09 lkh
						if( ($deposit_date_ck  == date('Ym',strtotime("-1 month", $this->iOnTimeStamp))) && ($accountConfirmDate > date('d', $this->iOnTimeStamp)) ){
							$status = "complete";
						}else{	
							$status = ($deposit_date_ck==date("Ym", $this->iOnTimeStamp))?"complete":"carryover";//확정-당월/확정-전월
						}
						//통합정산데이타 미정산-당월(overdraw)에서 확정-당월(complete) 또는 확정-전월(carryover) 확정으로 처리

						if( $accountdata['order_goods_kind'] == 'shipping' && $buyconfirm_ea != $buyconfirm_ea_ok) continue;

						//총 정산금액, 총 수수료 업데이트 @20190529 pjm
						$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
						$tmp_accountdata = $accountdata;
						$tmp_accountdata['status'] = $status;
						accountalllist('list2',$tmp_accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

						list($total_feeprice,$total_commission_price) = get_buyconfirm_commission($tmp_loop[0]);
						
						$accountdata['total_feeprice'] = $total_feeprice;
						$accountdata['total_commission_price'] = $total_commission_price;
						
						if($requireddata['seq']) {//미정산-당월데이타 검증
							if( $requireddata['account_target'] == 'calculate' && $requireddata['status'] =='overdraw' ) {//미정산-당월 업데이트

								$tmp_status = $status;
								// 반품배송비의 경우 주문의 결제일과 다르게 반품완료일을 결제일로 삼음으로 이월 기준 일자가 차이남.
								if(
									(
										$accountdata['account_target'] == 'calculate'
										&& $accountdata['account_type'] == 'return'
										&& $accountdata['order_type'] == 'shipping'
										&& $accountdata['refund_type'] == 'return'
									)
									&& str_replace("-","",substr($accountdata['deposit_date'],0,7)) == date("Ym", $this->iOnTimeStamp)
								){
									$status = 'complete';	// 반품배송비가 결제일과 다르게 이월되서 반품됬을 경우 반품배송비는 이월처리가 아닌 당월 처리
								}
								$this->update_calculate_sales_buyconfirm($calculatetableck, $status, $confirm_date, $accountdata);

								$status = $tmp_status;
							}
						}else{//미정산데이타 검증
							//미정산-전월 데이타 정산-전월로 확정처리
							$accountdata['target_table_seq'] = $accountdata['seq'];
							$acresult = $this->create_calculate_sales_account($calculatetableck, $this->tb_act_cal_sal, $order_seq, $status, $accountdata);

							// 생성된 이월 정산 상태 업데이트 by hed
							$this->update_calculate_sales_buyconfirm($calculatetableck, $status, $confirm_date, $accountdata);
						}

						// 매출데이터의 정산 상태 업데이트 by hed
						// 매출데이터의 정상확정처리가 완료됬을 시 데이터를 삭제하는 것이 아닌 정산확정상태로 변경
						$this->update_calculate_sales_buyconfirm($this->tb_act_cal_sal, 'complete', $confirm_date, $accountdata);
					}//endforeach
				}
			}//endif
		} catch (Exception $exc) {
			debug('accountmodel->confirm_account'.chr(10).$exc->getTraceAsString());
		}
		return $confirm_done;
	}
	/**
	 * 정산확정체크
	 * 정산단위 별 정산확정이 가능한지 체크
	 */
	function check_account_confirm($order_seq, $item = array(), $deposit_date_ck, $accountConfirmDate, $confirm_date){

		try {
			$tb_act_ym 	= $deposit_date_ck;
			$calculatetableck	= $this->get_table_ym_ck('calculate', $tb_act_ym);

			unset($order_params);
			$refundDeliveryPrice = ($item['refund_delivery_price'] + $item['refund_delivery_emoney'] + $item['refund_delivery_cash']);
			$order_params['goods_seq']			= $item['goods_seq'];
			$order_params['item_seq']			= $item['item_seq'];
			$order_params['shipping_group_seq']	= $item['shipping_seq'];
			$order_params['account_target']		= 'calculate';//정산대상만
			//$order_params['option_seq']			= $item['option_seq'];

			$get_account_all	= $this->get_account_calculate_sales($this->tb_act_cal_sal, $order_seq,$order_params);//미정산데이타의 동일배송그룹 주문데이타

			// 반품배송비도 모두 처리될 수 있도록 배송그룹으로 조회
			$order_params['exist_return_shippingfee'] = '1';
			$get_account_return_shippingfee	= $this->get_account_calculate_sales($this->tb_act_cal_sal, $order_seq,$order_params);//미정산데이타의 동일배송그룹 주문데이타
			if($get_account_return_shippingfee){
				foreach($get_account_return_shippingfee as $get_account_return_shippingfee_row){
					$get_account_all[] = $get_account_return_shippingfee_row;
				}
			}
			unset($order_params['exist_return_shippingfee']);

			if($get_account_all) {
				$ac_target = false;
				foreach($get_account_all as $k => $orderdata) {

					if( !$refundDeliveryPrice && $orderdata['order_type'] == 'shipping' && $orderdata['exp_ea'] > 0 ) {

						// 반품배송비의 경우 원주문의 결제일이 아닌 반품완료일을 이월을 위해 저장하므로 원주문의 결제일을 기준(acc_table)으로 원주문의 정산확정 여부를 검토
						if(
							$orderdata['account_target'] == 'calculate'
							&& $orderdata['account_type'] == 'return'
							&& $orderdata['order_type'] == 'shipping'
							&& $orderdata['refund_type'] == 'return'
						){
							$tmp_data_order = $this->ordermodel->get_order($orderdata['order_seq']);
							$orderdata['acc_table'] = str_replace("-","",substr($tmp_data_order['deposit_date'],0,7));
						}

						// 배송비일 경우 동일 출고그룹의 건수가 모두 처리되었는지 확인
						$shipping_confirm_ea = $this->calculate_account_ea($orderdata);
						if($shipping_confirm_ea['ac_ea'] == '0'){		// 동일그룹의 정산데이터가 모두 처리 됬을 경우
							$orderdata['target_table_seq']	= $orderdata['seq'];//복사할테이블(미정산 매출테이블)의 고유번호
							$orderdata['acc_date']			= date('Y-m-d', $this->iOnTimeStamp);
							$orderdata['regist_date']		= date('Y-m-d H:i:s', $this->iOnTimeStamp);
							$orderdata['up_date']			= date('Y-m-d H:i:s', $this->iOnTimeStamp);
							$orderdata['ac_ea']				= $shipping_confirm_ea['ac_ea'];	// 배송비의 남은 수량 처리
							$orderdata['exp_ea']			= $shipping_confirm_ea['exp_ea'];	// 배송비 정산 여부
							if($confirm_date) $orderdata['confirm_date']		= $confirm_date;
							// 배송비에 대한 모든 정산을 처리하기 위해서 정산고유번호로 처리,
							// 주문번호에 1개의 배송비만 존재하는 것이 아닌 반품배송비에 의한 다수의 배송비 존재 가능 by hed
							$ship_goods_orderdata[$orderdata['seq']] = $orderdata;	
						}
						continue;
					}elseif($refundDeliveryPrice){ // 배송비 환불시 정산 예외 처리 :: 2018-07-17 lkh
						// 환불 배송비의 처리는 원주문의 모든 출고에 대해 처리할 때 동시에 처리되므로 아래 소스가 필요 없음 by hed
						//$addShippingWhere	= " account_type = 'order' and order_form_seq = '{$item['shipping_seq']}' ";
						//$addShippingWhere	.= " and item_seq = '{$item['item_seq']}' ";
						//$addShippingWhere	.= " and shipping_group_seq = '{$item['shipping_seq']}' ";
						//$tb_act_cal_sal_shipping_sql = "update {$this->tb_act_cal_sal} set exp_ea=0, ac_ea=0 where {$addShippingWhere}";
						//$this->db->query($tb_act_cal_sal_shipping_sql);
						//$tb_act_cal_shipping_sql = "update {$calculatetableck} set exp_ea=0, ac_ea=0 where {$addShippingWhere}";
						//$this->db->query($tb_act_cal_shipping_sql);
					}

					// 정산확정 가능 여부 확인 정산확정이 가능해질 경우 
					// ac_ea = 0, exp_ea = 정산수량 으로 회신. by hed
					$account_confirm_ea = $this->calculate_account_ea($orderdata);
					$arr_account_confirm_ea[] = $account_confirm_ea;
					$orderdata['ac_ea']  = $account_confirm_ea['ac_ea'];
					$orderdata['exp_ea']  = $account_confirm_ea['exp_ea'];

					$order_total_ea +=$orderdata['ea'];
					$buyconfirm_ea	+=$orderdata['exp_ea'];

					if( $orderdata['ac_ea']==0 ) {
						$order_total_ea_ok	+=$orderdata['ea'];
						$buyconfirm_ea_ok	+=$orderdata['exp_ea'];
					}

					if( ($orderdata['order_form_seq'] == $item['option_seq'] && $orderdata['ac_ea']==0 ) && ($orderdata['order_type'] == 'option' || $orderdata['order_type'] == 'suboption')) {
						$ac_target = true;
						$orderdata['target_table_seq']	= $orderdata['seq'];//복사할테이블(미정산 매출테이블)의 고유번호
						$orderdata['acc_date']			= date('Y-m-d', $this->iOnTimeStamp);
						$orderdata['regist_date']		= date('Y-m-d H:i:s', $this->iOnTimeStamp);
						$orderdata['up_date']			= date('Y-m-d H:i:s', $this->iOnTimeStamp);
						if($confirm_date) $orderdata['confirm_date']		= $confirm_date;
						
						// 이미 완료된 내역은 정산완료처리 하지 않음 by hed
						// 이월되어 정산처리 될 때 기존 매출테이블(fm_account_calculate_sales)의 데이터가 중복되어 정산되므로 제외함
						if($orderdata && $orderdata['status'] != 'complete') {
							$acloop[] = $orderdata;
						}
					}else{
						continue;
					}
				}

				if($buyconfirm_ea == $buyconfirm_ea_ok) {//전체배송그룹 정산대상시 배송시 후정산
					if($ship_goods_orderdata && !$ac_target){
						$ac_target = true;
					}
					foreach($ship_goods_orderdata as $ship_key => $ship_val){
						// 이미 완료된 내역은 정산완료처리 하지 않음 by hed
						// 이월되어 정산처리 될 때 기존 매출테이블(fm_account_calculate_sales)의 데이터가 중복되어 정산되므로 제외함
						if($ship_val['status'] != 'complete') {
							$acloop[] = $ship_val;
						}
					}
				}

				// 정산확정을 위한 변수 정리
				unset($confirm_account_params);
				$confirm_account_params = array();
				$confirm_account_params['ac_target']			= $ac_target;
				$confirm_account_params['acloop']				= $acloop;
				$confirm_account_params['deposit_date_ck']		= $deposit_date_ck;
				$confirm_account_params['accountConfirmDate']  	= $accountConfirmDate;
				$confirm_account_params['buyconfirm_ea']		= $buyconfirm_ea;
				$confirm_account_params['buyconfirm_ea_ok']		= $buyconfirm_ea_ok;
				$confirm_account_params['confirm_date']			= $confirm_date;
				$confirm_account_params['order_seq']			= $order_seq;
				$confirm_account_params['order_params']			= $order_params;
			}
		} catch (Exception $exc) {
			debug('accountmodel->check_account_confirm'.chr(10).$exc->getTraceAsString());
		}
		// debug($confirm_account_params);
		return $confirm_account_params;
	}
	
	/**
	 * 본 주문이 자식주문인 경우 원주문이 정산처리되지 않기 때문에
	 * 원주문의 정산확정체크 프로세스를 재호출
	 * 어느 출고에서 본 주문이 생성되었는지 알 수 없기에 원주문의 전체 출고에 대해 정산확정체크 진행
	 */
	function check_confirm_account_origin_order($order_seq, $orign_order_type = 'top_order_export_code_list'){
		
		try {
			$confirm_done_all = false;
			$aConfirmDone = array();

			unset($search_params);
			$search_params = array();
			$search_params['order_seq'] = $order_seq;
			$top_orign_order_export = $this->get_ac_target_ea_from_export($orign_order_type, null, $search_params);
			if($top_orign_order_export){
				foreach($top_orign_order_export as $order_export){
					$tmpConfirmDone = $this->calculate_sales_buyconfirm($order_export['order_seq'], $order_export['export_code']);
					foreach($tmpConfirmDone as $confirmDone){
						$aConfirmDone[] = $confirmDone;
					}
				}
			}
			
			// 출고정보와 상관 없이 부분취소에 의해 출고정보가 없는 정산건이 생기므로 모든 주문내역에 대해서 
			$top_orign_order_option = $this->get_ac_target_ea_from_order('order_info', 'option', $search_params);
			$top_orign_order_suboption = $this->get_ac_target_ea_from_order('order_info', 'suboption', $search_params);
			$top_orign_order_info = array_merge($top_orign_order_option, $top_orign_order_suboption);
			if($top_orign_order_info){
				$tmpConfirmDone = $this->calculate_sales_buyconfirm($order_seq, '', null, null, $top_orign_order_info);
				foreach($tmpConfirmDone as $confirmDone){
					$aConfirmDone[] = $confirmDone;
				}
			}

			// 원주문의 모든 출고가 정산확정되었는지 체크
			foreach($aConfirmDone as $k=>$confirmDone){
				if($k==0){		// 비교 시작
					$confirm_done_all = $confirmDone;
				}
				if($confirmDone && $confirm_done_all){
					$confirm_done_all = true;
				}else{
					$confirm_done_all = false;
				}
			}
			// 원주문이 모두 정산확정 되었을 경우 
			if($confirm_done_all){
				// 모든 자식주문의 정산확정 체크 처리
				$this->check_confirm_account_child_order($order_export['order_seq']);
			}
		} catch (Exception $exc) {
			debug('accountmodel->check_confirm_account_origin_order'.chr(10).$exc->getTraceAsString());
		}
    }
	/**
	 * 모든 자식주문의 정산확정 체크 처리
	 * 모든 자식주문 중 맞교환주문의 경우 정산확정체크 진행 시 정산예외이므로 exp_ea, ac_ea가 0으로 정산완료처리 된다.
	 * by hed
	 */
	function check_confirm_account_child_order($order_seq){
		try {
			unset($search_params);
			$search_params = array();
			$search_params['order_seq'] = $order_seq;
			$child_order_all = $this->get_ac_target_ea_from_order('child_order_all', null, $search_params);
			foreach($child_order_all as $child_order){
				unset($search_params);
				$search_params = array();
				$search_params['order_seq'] = $child_order['order_seq'];
				$child_order_export = $this->get_ac_target_ea_from_export('order_export_code_list', null, $search_params);
				foreach($child_order_export as $order_export){
					$this->calculate_sales_buyconfirm($order_export['order_seq'], $order_export['export_code']);
				}
			}
		} catch (Exception $exc) {
			debug('accountmodel->check_confirm_account_child_order'.chr(10).$exc->getTraceAsString());
		}
	}
	/**
	 * 검산툴 호출 - 정산데이터 주문별 별
	 *	주문번호 기준으로 검산데이터를 생성하고 입력받은 정산데이터와 비교하여 결과 회신	 * 
	 */
	function checker_tool_for_order($order_seq, $caltableck, $carryover=''){
		$this->load->helper('accountall');
		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
		$this->load->model('providermodel');
		$this->load->model('refundmodel');
		$this->load->model('returnmodel');
		
		try {
			$this->db->trans_start(TRUE);   // 테스트 트랜젝션 시작

			$arr_calculate_sales_order_tmp_list = array();

			// 이벤트로 인해 할인정책이 변경될 경우 추가 작업이 필요함 by hed
			$account_ins_opt_tmp		= $this->ordermodel->get_item_option($order_seq);
			foreach($account_ins_opt_tmp as &$optdata){
				$this->get_commission($caltableck, $order_seq, $optdata, 'option');
				$optdata['coupon_value_type']            = $optdata['socialcp_input_type'];
				$optdata['coupon_value']                = ($optdata['coupon_input']*$optdata['ea']);
				$optdata['coupon_remain_value']            = ($optdata['coupon_input']*$optdata['ea']);
				$account_ins_opt[$optdata['item_option_seq']] = $optdata;
			}
			$account_ins_subopt_tmp		= $this->ordermodel->get_item_suboption($order_seq);
			foreach($account_ins_subopt_tmp as &$optdata){
				$this->get_commission($caltableck, $order_seq, $optdata, 'suboption');
				$account_ins_subopt[$optdata['item_suboption_seq']] = $optdata;
			}
			$account_ins_shipping_tmp	= $this->ordermodel->get_order_shipping($order_seq);
			foreach($account_ins_shipping_tmp as &$optdata){
				$this->get_commission($caltableck, $order_seq, $optdata, 'shipping');
				$account_ins_shipping[$optdata['shipping_seq']] = $optdata;
			}

			$set_order_price_ratio = $this->accountallmodel->set_order_price_ratio($order_seq);

			// 주문 발생으로 인한 정산 데이터 생성
			$arr_calculate_sales_order_tmp_list = $this->accountallmodel->insert_calculate_sales_order_tmp($order_seq, $set_order_price_ratio, $account_ins_opt, $account_ins_subopt, $account_ins_shipping);

			foreach($arr_calculate_sales_order_tmp_list as &$calculate_sales_order_tmp){
				// 임시매출 데이터는 입금확인 될 때 주문정보를 그대로 덮어쓰게 되므로
				// 주문정보에서 최신 값을 덮어씀.
				$data_order				= $this->accountallmodel->get_accountall_order($order_seq);
				$calculate_sales_order_tmp['step']						= $data_order['step'];
				$calculate_sales_order_tmp['deposit_date']				= $data_order['deposit_date'];
				$calculate_sales_order_tmp['pg']						= $data_order['pg'];
				$calculate_sales_order_tmp['payment']					= $data_order['payment'];
				$calculate_sales_order_tmp['pg']						= $data_order['pg'];
				$calculate_sales_order_tmp['pg_ordernum']				= $data_order['pg_transaction_number'];
				$calculate_sales_order_tmp['pg_ordernum_approval']		= $data_order['pg_approval_number'];
				$calculate_sales_order_tmp['order_referer']				= account_order_referer($data_order['pg'], $data_order);

				// 맞교환 주문의 경우 수수료 정보가 없음
				if($calculate_sales_order_tmp['account_type'] == 'exchange'){
					$calculate_sales_order_tmp['commission_type'] = '';
					$calculate_sales_order_tmp['commission_rate'] = '0';
				}

				// 현재 정산 수량에 대해 업데이트 
				$account_confirm_ea = $this->accountallmodel->calculate_account_ea($calculate_sales_order_tmp);
				$calculate_sales_order_tmp['account_ea'] = $account_confirm_ea;
				$calculate_sales_order_tmp['ac_ea'] = $account_confirm_ea['ac_ea'];
				$calculate_sales_order_tmp['exp_ea'] = $account_confirm_ea['exp_ea'];

				if(
					($account_confirm_ea['ac_ea'] == '0')								// 정산이 완료됬거나
					|| (
						$calculate_sales_order_tmp['shipping_provider_seq'] == '1'
						&& $calculate_sales_order_tmp['order_type'] == 'shipping'		// 본사배송이거나
					)		
				){
					$calculate_sales_order_tmp['confirm_date'] = $account_confirm_ea['confirm_date'];
				}

				if(
					($account_confirm_ea['ac_ea'] == '0')								// 정산이 완료됬거나
					|| (
						$calculate_sales_order_tmp['shipping_provider_seq'] == '1'
						&& $calculate_sales_order_tmp['order_type'] == 'shipping'		// 본사배송이거나
						&& $calculate_sales_order_tmp['confirm_date']					// 본사배송인데 확정일자가 있을 경우 완료로 분류
					)
				){								
					$calculate_sales_order_tmp['status'] = 'complete';
				}

				if($account_confirm_ea['ac_ea'] != '0'){								// 정산완료되지 않았을 경우
					$calculate_sales_order_tmp['exp_ea'] = $calculate_sales_order_tmp['ea'];			// 정산수량은 주문수량과 동일
				}

				// 이월정산데이터를 조회중인 경우 
				if($carryover == 'carryover'){
					// 당월 정산이 아닌 이월 정산이므로 상태값 강제 변경
					if(
						$calculate_sales_order_tmp['status'] == 'complete'
						|| $calculate_sales_order_tmp['status'] == 'overdraw'
					){
						$calculate_sales_order_tmp['status'] = 'carryover';
					}

				}
			}


			// 환불/취소/반품의 경우 주문이 발생한 이후에 데이터가 생성되므로 임시매출테이블이 아닌 매출테이블or정산테이블을 추적해야한다.
			// by hed
			// 환불/취소 발생으로 인한 정산 데이터 생성
			// 해당 주문이 갖는 모든 환불 데이터 조회
			$top_orign_order_seq = $order_seq;
			$data_order			= $this->ordermodel->get_order($top_orign_order_seq);
			if($data_order['top_orign_order_seq']){		// 자식주문의 환불의 경우 원주문에 생성됨
				$data_order			= $this->ordermodel->get_order($data_order['top_orign_order_seq']);
				$top_orign_order_seq = $data_order['top_orign_order_seq'];
			}
			$arr_data_refund		= $this->refundmodel->get_refund_for_order($top_orign_order_seq);

			$arr_calculate_sales_refund_tmp_list = array();
			foreach($arr_data_refund as $data_refund){
				$refund_code = $data_refund['refund_code'];
				$data_refund_item 	= $this->refundmodel->get_refund_item($refund_code);

				$arr_calculate_sales_refund_tmp_list[] = $this->accountallmodel->insert_calculate_sales_order_refund($data_order['order_seq'],$refund_code, $data_refund['cancel_type'], $data_order, $data_refund, $data_refund_item);
			}
			foreach($arr_calculate_sales_refund_tmp_list as $calculate_sales_refund_tmp_list){
				foreach($calculate_sales_refund_tmp_list as $calculate_sales_refund_tmp){
					// 환불데이터의 경우 정산하지 않으므로 강제로 exp_ea를 0으로 고정, 단 구매확정후 환불인 경우 정산하므로 예외
					if($calculate_sales_refund_tmp['account_type'] != 'after_refund'){
						$calculate_sales_refund_tmp['exp_ea'] = '0';
					}
					$arr_calculate_sales_order_tmp_list[] = $calculate_sales_refund_tmp;
				}
			}

			// 반품/맞교환 발생으로 인한 반품배송비 정산 데이터 생성
			$tmp_data_return = $this->returnmodel->get_return_for_order($order_seq, "return");
			$tmp_data_exchange = $this->returnmodel->get_return_for_order($order_seq,"exchange");
			unset($arr_data_return);
			$arr_data_return = array();
			foreach($tmp_data_return as $data_return){ $arr_data_return[] = $data_return; }
			foreach($tmp_data_exchange as $data_exchange){ $arr_data_return[] = $data_exchange; }

			foreach($arr_data_return as $data_return){
				$return_code = $data_return['return_code'];
				$tmp = $this->accountallmodel->insert_calculate_sales_order_returnshipping($order_seq, $return_code);
				$arr_calculate_sales_retun_shipping_tmp_list[] = $tmp;
			}

			foreach($arr_calculate_sales_retun_shipping_tmp_list as $calculate_sales_retun_shipping_tmp_list){
				foreach($calculate_sales_retun_shipping_tmp_list as &$calculate_sales_order_tmp){

					// 반품배송비 데이터는 생성될때 기존 정산테이블 정보를 그대로 덮어쓰게 되므로
					// 주문정보에서 최신 값을 덮어씀.
					$data_order				= $this->accountallmodel->get_accountall_order($order_seq);
					$calculate_sales_order_tmp['step']						= $data_order['step'];
					// $calculate_sales_order_tmp['deposit_date']				= $data_order['deposit_date'];
					$calculate_sales_order_tmp['pg']						= $data_order['pg'];
					$calculate_sales_order_tmp['payment']					= $data_order['payment'];
					$calculate_sales_order_tmp['pg']						= $data_order['pg'];
					$calculate_sales_order_tmp['pg_ordernum']				= $data_order['pg_transaction_number'];
					$calculate_sales_order_tmp['pg_ordernum_approval']		= $data_order['pg_approval_number'];
					$calculate_sales_order_tmp['order_referer']				= account_order_referer($data_order['pg'], $data_order);

					$calculate_sales_order_tmp['api_pg_add1']				= $data_order['top_orign_order_seq'];
					$calculate_sales_order_tmp['api_pg_add2']				= $data_order['orign_order_seq'];

					// 완료일자는 반품일자와 동일, 단 정산이 완료되었다면 최종 완료일자를 재갱신
					$calculate_sales_order_tmp['confirm_date'] = $calculate_sales_order_tmp['deposit_date'];

					// 현재 정산 수량에 대해 업데이트 
					$account_confirm_ea = $this->accountallmodel->calculate_account_ea($calculate_sales_order_tmp);
					$calculate_sales_order_tmp['account_ea'] = $account_confirm_ea;
					$calculate_sales_order_tmp['ac_ea'] = $account_confirm_ea['ac_ea'];
					$calculate_sales_order_tmp['exp_ea'] = $account_confirm_ea['exp_ea'];

					if($account_confirm_ea['ac_ea'] == '0'){									// 정산이 완료됬거나		
						$calculate_sales_order_tmp['status'] = 'complete';
						$calculate_sales_order_tmp['confirm_date'] = $account_confirm_ea['confirm_date'];
					}

					// 이월정산데이터를 조회중인 경우 
					if($carryover == 'carryover'){
						// 당월 정산이 아닌 이월 정산이므로 상태값 강제 변경
						if(
							$calculate_sales_order_tmp['status'] == 'complete'
							|| $calculate_sales_order_tmp['status'] == 'overdraw'
						){
							$calculate_sales_order_tmp['status'] = 'carryover';
						}

					}


					$arr_calculate_sales_order_tmp_list[] = $calculate_sales_order_tmp;
				}
			}
			
			
			// 정산이 진행중인 경우 정산남은 수량이 주문수량과 같음
			foreach($arr_calculate_sales_order_tmp_list as &$row_calculate_sales_order_tmp_list){
				if($row_calculate_sales_order_tmp_list['ac_ea'] != '0' && $row_calculate_sales_order_tmp_list['ea'] != $row_calculate_sales_order_tmp_list['ac_ea']){
					$row_calculate_sales_order_tmp_list['ac_ea'] = $row_calculate_sales_order_tmp_list['ea'];
				}
			}

			$this->db->trans_rollback();    // 트랜젝션 종료
		} catch (Exception $exc) {
			debug('accountmodel->checker_tool_for_order'.chr(10).$exc->getTraceAsString());
		}
		return $arr_calculate_sales_order_tmp_list;
	}
	/**
	 * 검산툴 호출 - 정산데이터 row 별
	 *	주문번호의 검산데이터와 입력받은 정산데이터와 비교하여 결과 회신
	 */
	function checker_tool_for_row($accountData, $carryover = null){
		try {
			$deposit_date	= str_replace("-","",substr($accountData['deposit_date'],0,7));

			if($carryover == 'carryover'){
				$caltableck		= $this->tb_act_cal_sal;
			}else{
				$caltableck		= $this->accountallmodel->get_table_ym_ck('calculate',$deposit_date);
			}

			$arr_calculate_sales_order_tmp_list = $this->checker_tool_for_order($accountData['order_seq'], $caltableck, $carryover);
			// debug($this->arr_base_info);
			// debug($arr_calculate_sales_order_tmp_list);
			// debug($accountData);

			$checker_row = array();

			// 검산 row 확정
			foreach($arr_calculate_sales_order_tmp_list as $calculate_sales_order_tmp){
				// 비교 검출용 기초 데이터 
				$arr_checker_same = array();
				$checker_same = false;
				foreach($this->arr_base_info as $base_info){
					// 네이버페이의 경우 주문수집시마다 입금일이 변경되므로 
					// 정산데이터의 입금일과 주문데이터의 입금일이 맞지 않음.
					// 또한 네이버페이는 1주문 - 1정산이 유지되므로 입금일을 체크하지 않아도 무방함.
					$check_continue = true;
					if($accountData['pg'] == 'npay' && $calculate_sales_order_tmp['pg'] == 'npay' && $base_info == 'deposit_date'){
						$check_continue = false;
					}
					if($check_continue){
						$arr_checker_same[$base_info] = ($accountData[$base_info] == $calculate_sales_order_tmp[$base_info]);
					}
				}
				foreach($arr_checker_same as $k=>$tmp_checker_same){
					if($k == $this->arr_base_info[0]){
						$checker_same = $tmp_checker_same;
					}elseif(!$tmp_checker_same || !$checker_same){
						$checker_same = false;
					}
				}
				if($checker_same){
					$checker_row = $calculate_sales_order_tmp;
				}
			}

			if($checker_row){
				$checker_sales = $checker_row;
				$origin_sales = $accountData;
				$checker_diff = $this->accountallmodel->checker_diff($checker_sales, $origin_sales);
			}

		} catch (Exception $exc) {
			debug('accountmodel->checker_tool_for_row'.chr(10).$exc->getTraceAsString());
		}
		return $checker_diff;
	}
	/**
	 * 
	 * 
	 */
	function checker_diff($checker_sales, $origin_sales){
		try {
			// 비교를 위한 매출 테이블 필드 확인
			$base_result = array();
			$checker_result = array();
			$origin_result = array();
			$account_ea_result = array();
			$success_result = array();

			// 비교용 기초 데이터
			$arr_base_info = $this->accountallmodel->arr_base_info;
			$arr_check_fields = $this->db->list_fields($this->accountallmodel->tb_act_cal_sal);
			
			// 비교 필드
			$arr_diff_info = $this->accountallmodel->arr_diff_info;
			foreach($arr_check_fields as $check_key=>$check_fields){
				if(!in_array($check_fields, $arr_diff_info) && !in_array($check_fields, $arr_base_info)){
					unset($arr_check_fields[$check_key]);
				}
			}
			
			// 비교 예외 필드
			$arr_exception_info = $this->accountallmodel->arr_exception_info;
			// 네이버페이의 경우 주문수집시마다 입금일이 변경되므로 
			// 정산데이터의 입금일과 주문데이터의 입금일이 맞지 않음.
			// 또한 네이버페이는 1주문 - 1정산이 유지되므로 입금일을 체크하지 않아도 무방함.
			if($checker_sales['pg'] == 'npay' && $origin_sales['pg'] == 'npay'){
				$arr_exception_info[] = 'deposit_date';
			}
			foreach($arr_exception_info as $exception_key){
				foreach($arr_check_fields as $check_key=>$check_fields){
					if($check_fields == $exception_key){
						unset($arr_check_fields[$check_key]);	
					}
				}
			}

			foreach($arr_check_fields as $check_fields){
				// 비교 검출용 기초 데이터 
				foreach($arr_base_info as $base_info){
					$base_result[$base_info] = $checker_sales[$base_info];
				}
				$account_ea_result = $checker_sales['account_ea'];

				$result_checker_equation = '';
				unset($tmp_checker_equation);
				$tmp_checker_equation = array();
				foreach($this->arr_checker_equation as $checker_equation_k=>$checker_equation_v){
					$tmp_checker_equation[] = $checker_equation_v['equation'];
				}
				$checker_equation = '('.implode($tmp_checker_equation, ' && ').')';
				try {
					eval("\$ttt = \"$checker_equation\";");
					$result_checker_equation = eval("return $ttt;");
				} catch (Throwable $t) {
					$result_checker_equation = null;
				}
				if($result_checker_equation){
					$checker_result[$check_fields] = $checker_sales[$check_fields];
					$origin_result[$check_fields] = $origin_sales[$check_fields];
				}else{
					// 값이 모두 같을 때
					$success_result[$check_fields] = $origin_sales[$check_fields];
				}
			}

			$result = array(
				'base_result'			=> $base_result,
				'checker_result'		=> $checker_result,
				'origin_result'			=> $origin_result,
				'account_ea_result'		=> $account_ea_result,
				'success_result'		=> $success_result,
				'origin_info'			=> $origin_sales,
			);
		} catch (Exception $exc) {
			debug('accountmodel->checker_diff'.chr(10).$exc->getTraceAsString());
		}
		return $result;
	}
	/**
	 * 수수료 정보 가져오기
	 * 
	 * @param type $caltableck
	 * @param type $order_seq
	 * @param type $optdata
	 * @param type $order_type
	 */
	function get_commission($caltableck, $order_seq, &$optdata, $order_type='option'){
		$this->load->model('goodsmodel');

		try {
			// commission_type 의 경우 주문서 생성 시 값이 저장되고 있으므로 
			// 1차적으로 기존 정산 테이블을 참조하고
			// 2차적으로 현재 정보를 참조한다.
			if(empty($optdata['commission_type']) || empty($optdata['commission_rate'])){
				unset($commission_type);
				unset($commission_rate);
				unset($account_tmp_params);
				$account_tmp_params = array();
				$account_tmp_params['goods_seq'] = $optdata['goods_seq'];
				$account_tmp_params['item_seq'] = $optdata['item_seq'];
				$account_tmp_params['shipping_provider_seq'] = $optdata['provider_seq'];
				$account_tmp_params['shipping_group_seq'] = $optdata['shipping_seq'];
				// 1차적으로 기존 정산 테이블을 참조하고
				$account_tmp = $this->accountallmodel->get_account_calculate_sales($caltableck, $order_seq, $account_tmp_params, 'order', $order_type);

				$commission_type = $account_tmp[0]['commission_type'];
				$commission_rate = $account_tmp[0]['commission_rate'];

				// 2차적으로 현재 정보를 참조한다.
				if(empty($commission_type) && empty($commission_rate)){
					if(in_array($order_type, array('option', 'suboption'))){
						$order_item_option_seq = 'item_option_seq';
						$get_opt_func_name = 'get_option';
						if($order_type == 'option'){
							$order_item_option_seq = 'item_option_seq';
							$get_opt_func_name = 'get_option';
						}elseif($order_type == 'suboption'){
							$order_item_option_seq = 'item_suboption_seq';
							$get_opt_func_name = 'get_suboption';
						}

						unset($goods_opt_params);
						$goods_opt_params = array();
						$goods_opt_params['goods_seq'] = $optdata['goods_seq'];
						// 상품 옵션 고유 정보 조회 option1, option2, option3, option4, option5
						if($order_type == 'option'){
							for($i=1;$i<6;$i++){
								$goods_opt_params['option'.$i] = ($optdata['option'.$i])?$optdata['option'.$i]:'';
							}
						}elseif($order_type == 'suboption'){
							$goods_opt_params['suboption'] = ($optdata['suboption'])?$optdata['suboption']:'';
						}
						//$get_opt_func_name : get_option, get_suboption 
						$query_goods_opt = $this->goodsmodel->{$get_opt_func_name}($goods_opt_params);
						$goods_opt = $query_goods_opt->row_array();

						if($goods_opt['commission_type']){
							$commission_type = $goods_opt['commission_type'];
							$commission_rate = $goods_opt['commission_rate'];
						}
					}elseif(in_array($order_type, array('shipping'))){
						// 입점사 정보
						$sql = "select * from fm_provider A left join fm_provider_charge B on A.provider_seq = B.provider_seq and B.link = 1 where A.provider_seq = '{$optdata['provider_seq']}'";
						$query = $this->db->query($sql);
						$data = $query->result_array();
						$data = $data[0];

						// 배송비의 경우 설정된 배송비 수수료 정보를 가져온다.
						$optdata['shipping_charge']			= $data['shipping_charge'];
						$optdata['return_shipping_charge']	= $data['return_shipping_charge'];
					}
				}
				if($commission_type || $commission_rate){
					if(in_array($order_type, array('option', 'suboption'))){
						$optdata['commission_type'] = $commission_type;
						$optdata['commission_rate'] = $commission_rate;
					}elseif(in_array($order_type, array('shipping'))){
						$optdata['shipping_charge']	= $commission_rate;
					}
				}
			}
		} catch (Exception $exc) {
			debug('accountmodel->get_commission'.chr(10).$exc->getTraceAsString());
		}
	}
	
	/**
	 * 정산데이터 수정 처리
	 */
	function proc_modify_account($mode, $base_info, $modify_column, $view_Ym){
		
		try {
			$deposit_date	= str_replace("-","",substr($base_info['deposit_date'],0,7));
			$tb_act_ym		= $deposit_date;
			if(!$tb_act_ym) $tb_act_ym	= date('Ym', $this->iOnTimeStamp);
			$caltableck = $this->get_table_ym_ck('calculate', $tb_act_ym);

			if($view_Ym >= date('Ym', $this->iOnTimeStamp)				// 조회년월이 현재년월보다 같거나 미래고
				&& $tb_act_ym < date('Ym', $this->iOnTimeStamp)			// 입금년월이 현재년월보다 과거일 경우
			){
				$caltableck = $this->tb_act_cal_sal;					// 수정 테이블은 정산테이블이 아닌 매출테이블
			}

			$arr_update_column_sql = array();
			$arr_select_column_sql = array();
			foreach($modify_column as $k=>$v){
				$arr_update_column_sql[] = " ".$k." = ".(($mode=='exec')?"?":"'".$v."'")." ";
				$arr_select_column_sql[] = $k;
				$bind[]	= $v;
			}
			$update_column_sql = implode($arr_update_column_sql, " , ");
			// $select_column_sql = implode($arr_select_column_sql, " , ");
			$select_column_sql = " * ";

			$arr_where_sql = array();
			foreach($base_info as $k=>$v){
				$arr_where_sql[] = " ".$k." = ".(($mode=='exec')?"?":"'".$v."'")." ";
				$bind[]	= $v;
			}
			if($arr_where_sql){
				$where_sql = " WHERE ".implode($arr_where_sql, " AND ");
			}

			$update_sql = "
				UPDATE ".$caltableck." SET 
				".$update_column_sql."
				".$where_sql."
			";

			$select_sql = "
				SELECT 
					".$select_column_sql."
				FROM 
					".$caltableck."
				".$where_sql."
			";

			$sql['select'] = $select_sql;
			$sql['update'] = $update_sql;

			// $where_sql 구성이 안 됬을 경우 진행 불가
			if(empty($where_sql)){
				$sql['result'] = '80';	// 에러
				return $sql;
			}

			if(
				$mode=='exec'
				&& (
					count($arr_update_column_sql)+count($arr_where_sql) == count($bind)	// 쿼리 바인딩 갯수 확인
				)
			){
				$this->db->query($update_sql, $bind);
				$sql['exec'] = $this->db->last_query();
				$sql['result'] = '00';	// 정상
			}elseif($mode=='select_sql'){
				$sql['result'] = '00';	// 정상
			}else{
				$sql['result'] = '90';	// 에러
			}
		} catch (Exception $exc) {
			debug('accountmodel->proc_modify_account'.chr(10).$exc->getTraceAsString());
		}
		return $sql;
	}
	
	// 메인 통계 화면 결재 금액 계산
	function get_main_count_bar_total_price($target_date){
		$main_count_bar = array();
		$main_count_bar['total_price'] = 0;
		
		$target_date = str_replace('-', '', $target_date);
		if(strlen($target_date) != 8){
			$target_date = date("Ymd");
		}
		$params['year']			= substr($target_date, 0, 4);
		$params['month']		= substr($target_date, 4, 2);
		$params['day']			= substr($target_date, 6, 2);
		$day					= (int) $params['day'];
		$key					= $day - 1;
		$params['sitetype']		= array('P', 'M', 'F', 'APP_ANDROID', 'APP_IOS', 'POS');

		unset($statsData);
		$statsData = array();
		$statsData = $this->get_stats_data_sales_daily($params, $statsData);
		
		/* 매출액, 매입금액평균, 순이익 계산 */		
		$this->make_view_sales_daily($statsData);
		
		$main_count_bar['total_price'] = $statsData[$key]['day_sales_benefit'];

		return $main_count_bar;
	}
	
	function get_sales_sales_monthly_stats_v2($params){
		$q_type				= $params['q_type'];
		$caltableck			= $params['caltableck'];
		$where_order		= $params['where_order'];
		$year				= $params['year'];
		$month				= $params['month'];
		if($where_order){
			$str_where_order = " WHERE " . implode(' AND ',$where_order) ;
		}
		
		// 모든 정산데이터를 조회한 후 정산 view 형식으로 가공, 그 후 통계에 사용할 sum을 계산
		$statsData = array();
		$statsData['stats_year']				= $year;
		$statsData['stats_month']				= $month;
		
		$sql = "
			SELECT 
				if( caltb.status in ('overdraw'), 'sales','cal') as ac_type
				, caltb.*
				, CASE caltb.order_referer
					WHEN 'storefarm' THEN ( (caltb.api_pg_sale_price-caltb.api_pg_support_price) + caltb.api_pg_support_price )
					WHEN 'open11st' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					WHEN 'coupang' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					ELSE 0
				end AS month_api_pg_sale
			FROM {$caltableck} caltb
			".$str_where_order."
		";
		/*
		$query = $this->db->query($sql);
		$list_caltable = $query->result_array();
		*/

		// 통계 정보가 수집된 통계데이터 반환
		// 수집된 이력이 없을 경우 빈데이터로 수집 처리 요청
		$bAlreadyScraped = false;
		$list_caltable = $this->get_scrap_sales_monthly($sql, $params, $bAlreadyScraped);
		// 이미 스크랩된 데이터가 있다면 스크랩된 데이터를 가공하지 않고 반환
		if($bAlreadyScraped){
			return $list_caltable;
		}

		if ($q_type == 'order'){
			$sql = "
				SELECT COUNT(DISTINCT caltb.order_seq)	AS month_count_sum
				FROM {$caltableck} caltb
				".$str_where_order."
			";
			$query = $this->db->query($sql);
			$month_count_sum = $query->row_array();
			$statsData['month_count_sum']			= $month_count_sum['month_count_sum'];					// 결제금액 - 합		- 계		- 주문건수
		}
		
		foreach($list_caltable as $accountdata){		    
			//총 정산금액, 총 수수료 업데이트 @20190529 pjm
			$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
			accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

			list($total_feeprice,$total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);
			$tmp_loop[0]['total_feeprice'] = $total_feeprice;
			$tmp_loop[0]['total_commission_price'] = $total_commission_price;
			$tmp_loop[0]['total_payprice'] = $total_payprice;
			$acViewData = $tmp_loop[0];
			$acViewDataCarryover = $carryoverloop[0];		// 이월 정산 데이터

			// 통계 view 형식에 따라 가공 
			// 통계 view 의 구조에 맞춰 순서 작성
			if ($q_type == 'order'){
				// $statsData['month_m_order_price']				+= $acViewData['total_payprice'];					// 결제금액	- 합			- 본사				- 컨트롤러에서 계산
				// $statsData['month_p_order_price']				+= $acViewData['total_payprice'];					// 결제금액	- 합			- 입점사				- 컨트롤러에서 계산		
				$statsData['month_settleprice_sum']					+= $acViewData['total_payprice'];					// 결제금액	- 합			- 계						
				$statsData['month_cash_use_sum']					+= $acViewData['out_cash_use'];						// 결제금액	- 합			- 계		- 예치금

				if($acViewData['order_goods_kind'] != 'shipping'){
					if($acViewData['provider_seq'] == '1'){
						$statsData['m_settleprice_sum']				+= $acViewData['total_payprice'];					// 결제금액	- 상품		- 본사
					}else{	
						$statsData['p_settleprice_sum']				+= $acViewData['total_payprice'];					// 결제금액	- 상품		- 입점사
					}	
					$statsData['month_goods_price_sum']				+= $acViewData['total_payprice'];					// 결제금액	- 상품		- 계		
					// $statsData['goods_cash_use_sum']				+= $acViewData['out_cash_use'];						// 결제금액	- 상품		- 계		- 예치금		- View 영역에서 계산
				}else{	
					if($acViewData['provider_seq'] == '1'){	
						$statsData['m_shipping_cost_sum']			+= $acViewData['total_payprice'];					// 결제금액	- 배송비		- 본사	
						$statsData['shipping_m_cash_sum']			+= $acViewData['out_cash_use'];						// 결제금액	- 배송비		- 본사	- 예치금
					}else{	
						$statsData['p_shipping_cost_sum']			+= $acViewData['total_payprice'];					// 결제금액	- 배송비		- 입점사
						$statsData['shipping_p_cash_sum']			+= $acViewData['out_cash_use'];						// 결제금액	- 배송비		- 입점사	- 예치금
					}
					$statsData['shipping_cost_sum']					+= $acViewData['total_payprice'];					// 결제금액	- 배송비		- 계					- View 영역에서 서로 다른 변수로 처리함
					$statsData['month_shipping_cost_sum']			+= $acViewData['total_payprice'];					// 결제금액	- 배송비		- 계					- View 영역에서 서로 다른 변수로 처리함
					$statsData['shipping_cash_use_sum']				+= $acViewData['out_cash_use'];						// 결제금액	- 배송비		- 계		- 예치금
				}

				$supply_price = 0;
				// 반품/조정배송비에는 원가가 없으나 실제 데이터 생성 시 기존 데이터를 복사하여 잘못된 원가가 생성되어 제외함.
				if($acViewData['account_type'] != 'return' && $acViewData['order_type'] != 'shipping' && $acViewData['provider_seq'] == '1'){
					$supply_price = $acViewData['supply_price'] * $acViewData['ea'];
				}

				$statsData['month_supply_price_sum']				+= $supply_price;								// 원가		- 매입/정산	- 본사				- 정산 화면에서는 출력되지 않으므로 개당 매입가 * 정산 수량으로 계산
				$statsData['month_commission_price_sum']			= 0;												// 원가		- 매입/정산	- 입점사				- 정산 내역에서 가산 처리함
				$statsData['month_supply_price']					+= $statsData['month_supply_price_sum']
																	+ $statsData['month_commission_price_sum'];			// 원가		- 매입/정산	- 계					- 본사와 입점사의 원가를 가산

				// 할인 정보 가공
				set_stat_discount_sale('month_', $acViewData, $statsData);
			}elseif ($q_type == 'refund'){
				if($acViewData['provider_seq'] == '1'){
					$statsData['month_m_refund_price_total_sum']	+= $acViewData['total_payprice'];					// 환불		- 합			- 본사	
				}else{
					$statsData['month_p_refund_price_total_sum']	+= $acViewData['total_payprice'];					// 환불		- 합			- 입점사	
				}
				$statsData['month_refund_price_total_sum']			+= $acViewData['total_payprice'];					// 환불		- 합			- 계		
				$statsData['month_refund_cash_sum']					+= $acViewData['out_cash_use'];						// 환불		- 합			- 계		- 예치금

				if(in_array($acViewData['account_type'], array('refund', 'after_refund'))){
					if($acViewData['provider_seq'] == '1'){
						$statsData['month_m_refund_price_sum']		+= $acViewData['total_payprice'];					// 환불		- 취소/반품	- 본사	
					}else{
						$statsData['month_p_refund_price_sum']		+= $acViewData['total_payprice'];					// 환불		- 취소/반품	- 입점사	
					}
					$statsData['month_refund_price_sum']			+= $acViewData['total_payprice'];					// 환불		- 취소/반품	- 계		

					if($acViewData['provider_seq'] == '1'){
						$statsData['month_refund_supply_price_sum']			+= $acViewData['supply_price'] 
																			* $acViewData['ac_ea'];							// 원가		- 취소/반품	- 본사				
					}
					$statsData['month_refund_commission_price_sum']	+= 0;													// 원가		- 취소/반품	- 입점사		- 정산 내역에서 가산 처리함		
					$statsData['month_refund_supply']				+= $statsData['month_refund_supply_price_sum']
																	+$statsData['month_refund_commission_price_sum'];	// 원가		- 취소/반품	- 계					

				}elseif(in_array($acViewData['account_type'], array('rollback'))){
					if($acViewData['provider_seq'] == '1'){
						$statsData['month_m_rollback_price_sum']	+= $acViewData['total_payprice'];					// 환불		- 되돌리기	- 본사	
					}else{
						$statsData['month_p_rollback_price_sum']	+= $acViewData['total_payprice'];					// 환불		- 되돌리기	- 입점사	
					}
					$statsData['month_rollback_price_sum']			+= $acViewData['total_payprice'];					// 환불		- 되돌리기	- 계		

					if($acViewData['provider_seq'] == '1'){
						$statsData['refund_rollback_supply_price_sum']	+= $acViewData['supply_price']
																			* $acViewData['ac_ea'];								// 원가		- 되돌리기	- 본사
					}
					$statsData['refund_rollback_commission_price_sum'] += 0;											// 원가		- 되돌리기	- 입점사			- 정산 내역에서 가산 처리함		
					$statsData['month_rollback_supply']				+= $statsData['refund_rollback_supply_price_sum']
																	+$statsData['refund_rollback_commission_price_sum'];	// 원가		- 되돌리기	- 계					
				}

				// 할인 정보 가공
				set_stat_discount_sale('month_refund_', $acViewData, $statsData);
			}elseif ($q_type == 'sales'){
				// $statsData['month_m_sales_price']				+= $acViewData['total_payprice'];					// 매출액	- 합			- 본사				- 컨트롤러에서 계산
				// $statsData['month_p_sales_price']				+= $acViewData['total_payprice'];					// 매출액	- 합			- 입점사				- 컨트롤러에서 계산
				// $statsData['month_sales_price']					+= $acViewData['total_payprice'];					// 매출액	- 합			- 계					- 컨트롤러에서 계산
			}elseif ($q_type == 'commission'){
				// $statsData['month_supply_total']					+= $acViewData['total_payprice'];					// 원가		- 합			- 본사				- 컨트롤러에서 계산
				// $statsData['month_commission_total']				+= $acViewData['total_payprice'];					// 원가		- 합			- 입점사				- 컨트롤러에서 계산
				// $statsData['month_supply_commission_sum']		+= $acViewData['total_payprice'];					// 원가		- 합			- 계					- 컨트롤러에서 계산

				if(
					in_array($acViewData['account_type'], array('refund', 'after_refund'))
					|| in_array($acViewDataCarryover['account_type'], array('refund', 'after_refund'))
				){
					$statsData['month_refund_supply_price_sum']		+= 0;											// 원가		- 취소/반품	- 본사							- 본사매출은 정산되지 않으므로 매	
					$statsData['month_refund_commission_price_sum']	+= $acViewData['out_commission_price']
																	+ $acViewDataCarryover['out_commission_price'];		// 원가		- 취소/반품	- 입점사				
					$statsData['month_refund_supply']				+= $statsData['month_refund_supply_price_sum']
																		+$statsData['month_refund_commission_price_sum'];	// 원가		- 취소/반품	- 계					
				}elseif(
					in_array($acViewData['account_type'], array('rollback'))
					|| in_array($acViewDataCarryover['account_type'], array('rollback'))
				){
					$statsData['refund_rollback_supply_price_sum']	+= 0;								// 원가		- 되돌리기	- 본사				
					$statsData['refund_rollback_commission_price_sum'] += $acViewData['out_commission_price']
																	+ $acViewDataCarryover['out_commission_price'];		// 원가		- 되돌리기	- 입점사				
					$statsData['month_rollback_supply']				+= $statsData['refund_rollback_supply_price_sum']
																	+$statsData['refund_rollback_commission_price_sum'];	// 원가		- 되돌리기	- 계					
				}else{

					$statsData['month_supply_price_sum']				+= 0;												// 원가		- 매입/정산	- 본사					- 본사매출은 정산되지 않으므로 매출에서 집계
					$statsData['month_commission_price_sum']			+= $acViewData['out_commission_price']
																		+ $acViewDataCarryover['out_commission_price'];		// 원가		- 매입/정산	- 입점사				- 이월 정산 금액도 가산

					$statsData['month_supply_price']					+= $statsData['month_supply_price_sum']
																		+ $statsData['month_commission_price_sum'];			// 원가		- 매입/정산	- 계					- 본사와 입점사의 원가를 가산
				}
			}elseif ($q_type == 'benefit'){
				// $statsData['month_m_sales_benefit']					+= $acViewData['total_payprice'];				// 매출이익	- 합			- 본사				- 컨트롤러에서 계산
				// $statsData['month_m_sales_benefit_percent']			+= $acViewData['total_payprice'];				// 매출이익	- 합			- 본사	- %			- 컨트롤러에서 계산
				// $statsData['month_p_sales_benefit']					+= $acViewData['total_payprice'];				// 매출이익	- 합			- 입점사				- 컨트롤러에서 계산
				// $statsData['month_p_sales_benefit_percent']			+= $acViewData['total_payprice'];				// 매출이익	- 합			- 입점사	- %			- 컨트롤러에서 계산
				// $statsData['month_sales_benefit']					+= $acViewData['total_payprice'];				// 매출이익	- 합			- 계					- 컨트롤러에서 계산
				// $statsData['month_sales_benefit_percent']			+= $acViewData['total_payprice'];				// 매출이익	- 합			- 계		- %			- 컨트롤러에서 계산
			}
		}
		return $statsData;
	}
	
	/**
	 * 통계 형식에 맞춰 데이터를 반환하기 위해
	 * 정산 row를 가공하고 일자별로 합산하여 반환
	 * @param type $params
	 * @return type
	 */
	function get_sales_sales_daily_stats_v2($params){
		$object = new class($this, $params){
			private $CI;
			private $params;
			function __construct($CI, $params){
				$this->CI = $CI;
				$this->params = $params;
			}
			public function result_array(){
				$params = $this->params;
				$CI = $this->CI;
				
				$q_type				= $params['q_type'];
				$caltableck			= $params['caltableck'];
				$where_order		= $params['where_order'];
				$year				= $params['year'];
				$month				= $params['month'];
				$bind				= $params['bind'];
				if($where_order){
					$str_where_order = " WHERE " . implode(' AND ',$where_order) ;
				}
		
				if($q_type == 'order'){
					$stats_day_base = 'out_deposit_date';
				}elseif($q_type == 'refund'){
					$stats_day_base = 'out_confirm_date';
				}
				
				// 모든 정산데이터를 조회한 후 정산 view 형식으로 가공, 그 후 통계에 사용할 sum을 계산
				$statsData = array();
				$statsDataCount = array();

				$sql = "
					SELECT 
						if( caltb.status in ('overdraw'), 'sales','cal') as ac_type
						, caltb.*
						, CASE caltb.order_referer
							WHEN 'storefarm' THEN ( (caltb.api_pg_sale_price-caltb.api_pg_support_price) + caltb.api_pg_support_price )
							WHEN 'open11st' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
							WHEN 'coupang' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
							ELSE 0
						end AS month_api_pg_sale
					FROM {$caltableck} caltb
					".$str_where_order."
				";
				$query = $CI->db->query($sql, $bind);
				$list_caltable = $query->result_array();
				
				foreach($list_caltable as $accountdata){
					//총 정산금액, 총 수수료 업데이트 @20190529 pjm
					$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
					accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

					list($total_feeprice,$total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);
					$tmp_loop[0]['total_feeprice'] = $total_feeprice;
					$tmp_loop[0]['total_commission_price'] = $total_commission_price;
					$tmp_loop[0]['total_payprice'] = $total_payprice;
					$acViewData = $tmp_loop[0];
					$acViewDataCarryover = $carryoverloop[0];		// 이월 정산 데이터
					
					$key = substr($acViewData[$stats_day_base],6,2);
					
					// 조정금액의 발생일, 반품배송비의 발생일은 입금일이 아닌 완료일
					if( ($acViewData['account_type'] == 'deductible' || $acViewData['account_type'] == 'return') && $acViewData['out_confirm_date'] ){
						$key = substr($acViewData['out_confirm_date'],6,2);
					}
					
					$statsData[$key]['stats_year']				= $year;
					$statsData[$key]['stats_month']				= $month;
					$statsData[$key]['stats_day']				= $day = $key;
				
					if($q_type == 'order'){
						$statsDataCount[$key]['tmp_day_count_sum'][$acViewData['order_seq']] = '1';
						$statsData[$key]['day_settleprice_sum']		+= $acViewData['out_sale_price'];							// 결제금액
						$statsData[$key]['day_cash_use_sum']		+= $acViewData['out_cash_use'];								// 예치금 할인과 유사한 구조라 할인 가산에서 처리
						$statsData[$key]['day_supply_price_sum']	+= $acViewData['supply_price']
																	* $acViewData['exp_ea'];									// 매입원가
						
						// 할인 정보 가공
						set_stat_discount_sale('day_', $acViewData, $statsData[$key]);
						
					}elseif($q_type == 'refund'){
						$statsData[$key]['day_refund_price_sum_total']			+= $acViewData['total_payprice'];
						$statsData[$key]['refund_price_sum']					+= $acViewData['out_sale_price'];
						$statsData[$key]['refund_cash_sum']						+= $acViewData['out_cash_use'];
						
						if(in_array($acViewData['account_type'], array('rollback'))){
							$statsData[$key]['day_rollback_price_sum']				+= $acViewData['total_payprice'];		
							$statsDataCount[$key]['tmp_day_refund_count_sum_R'][$acViewData['refund_code']] = '1';
						}else{
							$statsDataCount[$key]['tmp_day_refund_count_sum_A'][$acViewData['refund_code']] = '1';
						}

						// 할인 정보 가공
						set_stat_discount_sale('day_refund_', $acViewData, $statsData[$key]);
						
					}
				}
				$result = array();
				foreach($statsData as $key => $statsDataDay){
					// 일자별 결제건수
					if($q_type == 'order'){
						$statsDataDay['day_count_sum'] = count($statsDataCount[$key]['tmp_day_count_sum']);
					}elseif($q_type == 'refund'){
						// 환불은 등록일 기준 
						$statsDataDay['day_refund_count_sum_A'] = count($statsDataCount[$key]['tmp_day_refund_count_sum_A']);
						$statsDataDay['day_refund_count_sum_R'] = count($statsDataCount[$key]['tmp_day_refund_count_sum_R']);
					}
					
					$result[] = $statsDataDay;
				}
				return $result;
			}
		};
		return $object;
	}
		
	function get_sales_sales_hour_stats_v2($params){
		$object = new class($this, $params){
			private $CI;
			private $params;
			function __construct($CI, $params){
				$this->CI = $CI;
				$this->params = $params;
			}
			public function result_array(){
				$params = $this->params;
				$CI = $this->CI;
				
				$year				= $params['year'];
				$month				= $params['month'];
				$conv_month			= $params['conv_month'];

				if(!$tb_act_ym) $tb_act_ym	= $year.$conv_month;
				$caltableck = $CI->accountallmodel->get_table_ym_ck('calculate', $tb_act_ym);
				
				$where_order[] = "caltb.status in ('overdraw','complete') ";
				$where_order[]	= "caltb.account_type not in ('refund','rollback','after_refund') ";
				// 입점사
				if (!empty($params['provider_seq'])){
					$where_order[]	= "caltb.provider_seq = '".$params['provider_seq']."' ";
				}
				// 주문 타입
				if (is_array($params['sitetype']) && count($params['sitetype']) > 0){
					$where_order[]	= "caltb.sitetype in ('".implode("','",$params['sitetype'])."') ";
				}
				//$str_where_order = " WHERE 1=1 ";
				if($where_order){
					$str_where_order .= " WHERE " . implode(' AND ',$where_order) ;
				}
				
				
				// 모든 정산데이터를 조회한 후 정산 view 형식으로 가공, 그 후 통계에 사용할 sum을 계산
				$statsData = array();
				$statsDataCount = array();

				$sql = "
					SELECT 
						if( caltb.status in ('overdraw'), 'sales','cal') as ac_type
						, caltb.*
						, CASE caltb.order_referer
							WHEN 'storefarm' THEN ( (caltb.api_pg_sale_price-caltb.api_pg_support_price) + caltb.api_pg_support_price )
							WHEN 'open11st' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
							WHEN 'coupang' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
							ELSE 0
						end AS month_api_pg_sale
					FROM {$caltableck} caltb
					".$str_where_order."
				";
				$query = $CI->db->query($sql);
				$list_caltable = $query->result_array();
				
				foreach($list_caltable as $accountdata){
					//총 정산금액, 총 수수료 업데이트 @20190529 pjm
					$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
					accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

					list($total_feeprice,$total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);
					$tmp_loop[0]['total_feeprice'] = $total_feeprice;
					$tmp_loop[0]['total_commission_price'] = $total_commission_price;
					$tmp_loop[0]['total_payprice'] = $total_payprice;
					$acViewData = $tmp_loop[0];
					$acViewDataCarryover = $carryoverloop[0];		// 이월 정산 데이터
					
					$day = substr($acViewData['out_deposit_date'],6,2);
					$key = substr($acViewData['deposit_date'],11,2);
					$key = (int) $key;
					
					$statsData[$key]['stats_year']				= $year;
					$statsData[$key]['stats_month']				= $month;
					$statsData[$key]['stats_day']				= $day;
					$statsData[$key]['stats_hour']				= $hour = $key;
				
					$statsDataCount[$key]['tmp_month_settleprice_sum'][$acViewData['order_seq']] = '1';
					$statsData[$key]['month_settleprice_sum']		+= $acViewData['total_payprice'];	
				}
				$result = array();
				foreach($statsData as $key => $statsDataDay){
					// 일자별 결제건수
					$statsDataDay['month_count_sum'] = count($statsDataCount[$key]['tmp_month_settleprice_sum']);
					
					$result[] = $statsDataDay;
				}
				return $result;
			}
		};
		return $object;
	}
	
	function get_sales_stat_v2($params){
		
		$base_key			= 'provider_seq';	// 병합을 처리할 기준 필드
		if(!empty($params['base_key'])) $base_key = $params['base_key'];

		$sdate				= $params['sdate'];

		if(!$tb_act_ym) $tb_act_ym	= str_replace("-","",substr($sdate,0,7));
		$caltableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
		
		// 매출 데이터의 기본 조건 
		$where_order[] = "caltb.status in ('overdraw','complete') ";
		$where_order[]	= "caltb.account_type not in ('refund','rollback','after_refund') ";
		
		// 주문경로
		if (!empty($params['order_referer'])){
			$where_order[]	= "caltb.order_referer = '".$params['order_referer']."' ";
		}
		// 입점사
		if (!empty($params['provider_seq'])){
			$where_order[]	= "caltb.provider_seq = '".$params['provider_seq']."' ";
		}
		// 주문 타입
		if (is_array($params['sitetype']) && count($params['sitetype']) > 0){
			$where_order[]	= "caltb.sitetype in ('".implode("','",$params['sitetype'])."') ";
		}
		if($where_order){
			$str_where_order = " WHERE " . implode(' AND ',$where_order) ;
		}

		// 모든 정산데이터를 조회한 후 정산 view 형식으로 가공, 그 후 통계에 사용할 sum을 계산
		$statsData = array();
		$statsDataCount = array();

		$sql = "
			SELECT 
				if( caltb.status in ('overdraw'), 'sales','cal') as ac_type
				, caltb.*
				, CASE caltb.order_referer
					WHEN 'storefarm' THEN ( (caltb.api_pg_sale_price-caltb.api_pg_support_price) + caltb.api_pg_support_price )
					WHEN 'open11st' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					WHEN 'coupang' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					ELSE 0
				end AS month_api_pg_sale
			FROM {$caltableck} caltb
			".$str_where_order."
		";
		$query = $this->db->query($sql);
		$list_caltable = $query->result_array();

		foreach($list_caltable as $accountdata){
			//총 정산금액, 총 수수료 업데이트 @20190529 pjm
			$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
			accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

			list($total_feeprice,$total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);
			$tmp_loop[0]['total_feeprice'] = $total_feeprice;
			$tmp_loop[0]['total_commission_price'] = $total_commission_price;
			$tmp_loop[0]['total_payprice'] = $total_payprice;
			$acViewData = $tmp_loop[0];
			$acViewDataCarryover = $carryoverloop[0];		// 이월 정산 데이터

			$key = $acViewData[$base_key];
			$statsData[$key][$base_key]										= $key;			
			
			$statsData[$key]['provider_name']								= $acViewData['out_provider_name'];
			if($acViewData['order_goods_kind'] != 'shipping'){
				$statsData[$key]['total_ea']									+= $acViewData['out_ea'];
				$statsData[$key]['goods_ea'][$acViewData['order_goods_seq']]	+= $acViewData['out_ea'];
			}
			$statsData[$key]['price_sum']									+= $acViewData['out_price'];
			$statsData[$key]['sale_sum']									+= $acViewData['out_salescost_admin']
																			+ $acViewData['out_pg_sale_price']
																			+ $acViewData['out_salescost_provider'];
		}
		$result = array();
		foreach($statsData as $key => $statsDataDay){
			$result[] = $statsDataDay;
		}
		
		// 변경된 기획에 의해 환불 정보는 포함하지 않음 #34379  2109-06-21 hed
		return $result;
	}
	
	function get_goods_stat_v2($params){
		$sdate				= $params['sdate'];

		if(!$tb_act_ym) $tb_act_ym	= str_replace("-","",substr($sdate,0,7));
		$caltableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
		
		// 매출 데이터의 기본 조건 
		$where_order[] = "caltb.status in ('overdraw','complete') ";
		$where_order[]	= "caltb.account_type not in ('refund','rollback','after_refund') ";
		
		// 주문경로
		if (!empty($params['order_referer'])){
			$where_order[]	= "caltb.order_referer = '".$params['order_referer']."' ";
		}
		// 입점사
		if (!empty($params['provider_seq'])){
			$where_order[]	= "caltb.provider_seq = '".$params['provider_seq']."' ";
		}
		// 주문 타입
		if (is_array($params['sitetype']) && count($params['sitetype']) > 0){
			$where_order[]	= "caltb.sitetype in ('".implode("','",$params['sitetype'])."') ";
		}
		if($where_order){
			$str_where_order = " WHERE " . implode(' AND ',$where_order) ;
		}

		// 모든 정산데이터를 조회한 후 정산 view 형식으로 가공, 그 후 통계에 사용할 sum을 계산
		$statsData = array();
		$statsDataCount = array();

		$sql = "
			SELECT 
				if( caltb.status in ('overdraw'), 'sales','cal') as ac_type
				, caltb.*
				, CASE caltb.order_referer
					WHEN 'storefarm' THEN ( (caltb.api_pg_sale_price-caltb.api_pg_support_price) + caltb.api_pg_support_price )
					WHEN 'open11st' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					WHEN 'coupang' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					ELSE 0
				end AS month_api_pg_sale
			FROM {$caltableck} caltb
			".$str_where_order."
		";
		$query = $this->db->query($sql);
		$list_caltable = $query->result_array();
		
		foreach($list_caltable as $accountdata){
			//총 정산금액, 총 수수료 업데이트 @20190529 pjm
			$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
			accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

			list($total_feeprice,$total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);
			$tmp_loop[0]['total_feeprice'] = $total_feeprice;
			$tmp_loop[0]['total_commission_price'] = $total_commission_price;
			$tmp_loop[0]['total_payprice'] = $total_payprice;
			$acViewData = $tmp_loop[0];
			$acViewDataCarryover = $carryoverloop[0];		// 이월 정산 데이터

			$key = $acViewData['order_goods_seq'];
			
			if($acViewData['order_goods_kind'] != 'shipping'){
				$statsData[$key]['goods_name']								= ($acViewData['order_goods_seq']=='0')?'미매칭 상품':$acViewData['out_order_goods_name'];
				$statsData[$key]['goods_seq']								= $acViewData['order_goods_seq'];
				$statsData[$key]['ea_sum']									+= $acViewData['out_ea'];
				$statsData[$key]['price_sum']								+= $acViewData['out_price'];
				$statsData[$key]['sale_sum']								+= $acViewData['out_salescost_admin']
																			+ $acViewData['out_pg_sale_price']
																			+ $acViewData['out_salescost_provider'];
			}
		}
		$result = array();
		foreach($statsData as $key => $statsDataDay){
			$result[] = $statsDataDay;
		}
		
		return $result;
	}
	
	function get_goods_shipping_code_v2($params){
		$sdate				= $params['sdate'];

		if(!$tb_act_ym) $tb_act_ym	= str_replace("-","",substr($sdate,0,7));
		$caltableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
		
		// 배송비 데이터의 기본 조건 
		$where_order[] = "caltb.status in ('overdraw', 'carryover', 'complete') ";
		$where_order[]	= "caltb.account_type not in ('refund','rollback','after_refund') ";
		$where_order[]	= "caltb.order_goods_kind in ('shipping') ";
		$where_order[]	= "caltb.return_code = '' ";
		
		// 주문경로
		if (!empty($params['order_referer'])){
			$where_order[]	= "caltb.order_referer = '".$params['order_referer']."' ";
		}
		// 입점사
		if (!empty($params['provider_seq'])){
			$where_order[]	= "caltb.provider_seq = '".$params['provider_seq']."' ";
		}
		// 주문 타입
		if (is_array($params['sitetype']) && count($params['sitetype']) > 0){
			$where_order[]	= "caltb.sitetype in ('".implode("','",$params['sitetype'])."') ";
		}
		if($where_order){
			$str_where_order = " WHERE " . implode(' AND ',$where_order) ;
		}

		// 모든 정산데이터를 조회한 후 정산 view 형식으로 가공, 그 후 통계에 사용할 sum을 계산
		$statsData = array();
		$statsDataCount = array();

		$sql = "
			SELECT 
				if( caltb.status in ('overdraw'), 'sales','cal') as ac_type
				, caltb.*
				, CASE caltb.order_referer
					WHEN 'storefarm' THEN ( (caltb.api_pg_sale_price-caltb.api_pg_support_price) + caltb.api_pg_support_price )
					WHEN 'open11st' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					WHEN 'coupang' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					ELSE 0
				end AS month_api_pg_sale
			FROM {$caltableck} caltb
			".$str_where_order."
		";
		$query = $this->db->query($sql);
		$list_caltable = $query->result_array();
		
		foreach($list_caltable as $accountdata){
			//총 정산금액, 총 수수료 업데이트 @20190529 pjm
			$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
			accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

			list($total_feeprice,$total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);
			$tmp_loop[0]['total_feeprice'] = $total_feeprice;
			$tmp_loop[0]['total_commission_price'] = $total_commission_price;
			$tmp_loop[0]['total_payprice'] = $total_payprice;
			$acViewData = $tmp_loop[0];
			$acViewDataCarryover = $carryoverloop[0];		// 이월 정산 데이터

			$key = '1';
			
			$statsData['shipping_sum']							+= $acViewData['total_payprice'];
	
		}
		$result = array();
		$result = $statsData['shipping_sum'];
		
		// 변경된 기획에 의해 환불 정보는 포함하지 않음 #34379  2109-06-21 hed
		return $result;
		
	}
	
	function refund_stat_v2($params){
		$sdate				= $params['sdate'];

		if(!$tb_act_ym) $tb_act_ym	= str_replace("-","",substr($sdate,0,7));
		$caltableck = $this->get_table_ym_ck('calculate', $tb_act_ym);
		
		// 반품 데이터의 기본 조건 
		$where_order[] = "caltb.status in ('overdraw', 'complete') ";
		$where_order[]	= "
			(
				caltb.account_type in ('refund','rollback','after_refund')
				or ( 
					caltb.account_type in ('return') 
					and caltb.order_goods_kind = 'shipping'
				)
			)
		";
		
		// 입점사
		if (!empty($params['provider_seq'])){
			$where_order[]	= "caltb.provider_seq = '".$params['provider_seq']."' ";
		}
		// 주문 타입
		if (is_array($params['sitetype']) && count($params['sitetype']) > 0){
			$where_order[]	= "caltb.sitetype in ('".implode("','",$params['sitetype'])."') ";
		}
		if($where_order){
			$str_where_order = " WHERE " . implode(' AND ',$where_order) ;
		}

		// 모든 정산데이터를 조회한 후 정산 view 형식으로 가공, 그 후 통계에 사용할 sum을 계산
		$statsData = array();
		$statsDataCount = array();

		$sql = "
			SELECT 
				if( caltb.status in ('overdraw'), 'sales','cal') as ac_type
				, caltb.*
				, CASE caltb.order_referer
					WHEN 'storefarm' THEN ( (caltb.api_pg_sale_price-caltb.api_pg_support_price) + caltb.api_pg_support_price )
					WHEN 'open11st' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					WHEN 'coupang' THEN ( caltb.api_pg_sale_price + caltb.api_pg_support_price )
					ELSE 0
				end AS month_api_pg_sale
			FROM {$caltableck} caltb
			".$str_where_order."
		";
		$query = $this->db->query($sql);
		$list_caltable = $query->result_array();
		
		foreach($list_caltable as $accountdata){
			//총 정산금액, 총 수수료 업데이트 @20190529 pjm
			$tmp_loop = $tot = $total3 = $carryoverloop = $carryovertot = $overdrawloop = $overdrawtot = $accountAllCount =array();
			accountalllist('list2',$accountdata, $tmp_loop, $tot, $carryoverloop, $carryovertot, $overdrawloop, $overdrawtot);

			list($total_feeprice,$total_commission_price, $total_payprice) = get_buyconfirm_commission($tmp_loop[0]);
			$tmp_loop[0]['total_feeprice'] = $total_feeprice;
			$tmp_loop[0]['total_commission_price'] = $total_commission_price;
			$tmp_loop[0]['total_payprice'] = $total_payprice;
			$acViewData = $tmp_loop[0];
			$acViewDataCarryover = $carryoverloop[0];		// 이월 정산 데이터

			$key = '1';
			
			
			if(in_array($acViewData['account_type'], array('refund', 'rollback', 'after_refund'))){
				// 반품데이터
				$statsData['refund']['provider_seq']				= $acViewData['provider_seq'];
				$statsData['refund']['refund_price']				+= $acViewData['total_payprice'];
			}elseif(in_array($acViewData['account_type'], array('return')) && ($acViewData['order_goods_kind'] == 'shipping')){
				// 반품 배송비
				$statsData['refund']['provider_seq']				= $acViewData['provider_seq'];
				$statsData['return']['return_shipping_price']		+= $acViewData['total_payprice'];
			}
		}
		$result = array();
		$result['refund'][] = $statsData['refund'];
		$result['return'][] = $statsData['return'];
		
		// 변경된 기획에 의해 환불 정보는 포함하지 않음 #34379  2109-06-21 hed
		return $result;
	}

	//정산리스트 검색 기간. 년/월 Selectbox
	public function get_search_year_month(){

		/**
		* 년/월 추출 시작
		**/
		$sql = "select regist_date from fm_order order by regist_date limit 1";//tb_act_tmp  fm_order
		$query = $this->db->query($sql);
		$order = $query->result_array();
		if($order[0]['regist_date']){
			$start = substr($order[0]['regist_date'],0,4);
		}else{
			$start = date("Y");
		}
	
		$year	= $month = array();
		$_year	= $start;
		while( $_year <= date("Y")){
			$year[] = $_year;
			$_year	= date("Y",strtotime(" +1 year",mktime(0,0,0,1,1,$_year)));
		}
		rsort($year);
		for($i=12;$i>0;$i--){
			$temp = strlen($i)>1 ? $i : "0".$i;
			$month[] = $temp;
		}

		return array($year,$month);

	}

	/**
	 * 요청받은 쿼리를 페이징으로 조합하여 결과물을 반환
	 * @param type $sQuery
	 */
	function get_sales_sales_monthly_stats_paging_query($sQuery, $iPage = 1, $aResult = array()){
		// 통계용 페이징 변수
		// 한번에 가져올 데이터량을 페이징 변수로 컨트롤
		// $iPerpage * $iPageNumber
		$iPerpage		= 1;			// 페이지에 노출될 목록 수
		$iPageNumber	= 300;			// 목록에 노출될 row 수
		
		$CI =& get_instance();
		if(empty($CI->blockpage)){
			$this->load->library('blockpage');
		}
		$iBackupPerpage			= $CI->blockpage->perpage;
		$iBackupPage			= $CI->blockpage->page;
		$iBackupPageNumber		= $CI->blockpage->page_number;
		$iBackupLimitAdd		= $CI->blockpage->limit_add;
		
		$CI->blockpage->perpage		= $iPerpage;
		$CI->blockpage->page		= $iPage;
		$CI->blockpage->page_number	= $iPageNumber;
		$CI->blockpage->limit_add	= 0;				// 목록에서 다음 페이지의 목록이 있는지 확인하기 위해 1개 더 가져오던 변수를 0으로 지정하여 중복가산 오차제거
		$CI->blockpage->set();
		
		$aLimitResult = $CI->blockpage->page_query(array('query'=>$sQuery));
		if(count($aLimitResult['record']) > 0){
			foreach($aLimitResult['record'] as $row){
				$aResult[] = $row;
			}
			$iPage++;
			$aResult = $this->get_sales_sales_monthly_stats_paging_query($sQuery, $iPage, $aResult);
		}
		
		$CI->blockpage->perpage			= $iBackupPerpage;
		$CI->blockpage->page			= $iBackupPage;
		$CI->blockpage->page_number		= $iBackupPageNumber;
		$CI->blockpage->limit_add		= $iBackupLimitAdd;
		return $aResult;
	}
	
	/*
	 * 통계 정보가 수집된 통계데이터 반환
	 * 수집된 이력이 없을 경우 빈데이터로 수집 처리 요청
	 */
	function get_scrap_sales_monthly($sql, $aPrams, &$bAlreadyScraped){
		$aResult = array();
		$bRunScrap = $aPrams['bRunScrap'];
		
		if(empty($aPrams['save_provider_seq'])){
			$aPrams['save_provider_seq'] = '1';
		}
		
		if($bRunScrap){
			$query = $this->db->query($sql);
			$aResult = $query->result_array();
		}else{
			// 통계 정보 수집 여부 확인
			$aResult = $this->check_scrap_sales_monthly($aPrams, $bAlreadyScraped);
			
			// 수집된 정보가 있다면 이미 가공되어 있으므로 재가공 필요 없이 바로 반환
			if($bAlreadyScraped){
				
				// 당월 데이터인 경우 수정일이 1시간 지났다면 재갱신 데이터 생성
				// 당년&당월 체크
				$sNowYear = date('Y', $this->iOnTimeStamp);
				$sNowMonth = date('m', $this->iOnTimeStamp);
				
				if($aPrams['year'] == $sNowYear && $aPrams['month'] == $sNowMonth){
					$differenceFormat = '%h';
					
					$sNowDate		= date('Y-m-d H:i:s', $this->iOnTimeStamp);
					$dNowDate		= date_create($sNowDate);
					$dUpdateDate	= date_create($aResult['update_date']);
					
					$interval = date_diff($dNowDate, $dUpdateDate);
					$iDiffHour = $interval->format($differenceFormat);
					
					// 1시간이 지났다면 갱신
					if($iDiffHour >= 1){
						// 수집 데이터 추가
						$this->add_scrap_sales_monthly($aPrams);
					}
				}else {
					// 전월 데이터인 경우 수정일이 마지막날 23:59:59 보다 이전이면 재갱신 데이터 생성
					// 전월 마지막 수정일 체크
					$lastDay = date('Y-m-t 23:59:59', mktime(0, 0, 1, $aPrams['month'], 1, $aPrams['year']));

					if($lastDay > $aResult['update_date']){
						// 수집 데이터 추가
						$this->add_scrap_sales_monthly($aPrams);
					}
				}
				
				// 불필요한 데이터 제거 및 기준 키 가공
				$aResult['stats_year']			= $aResult['year'];
				$aResult['stats_month']			= $aResult['month'];
				unset($aResult['year']);
				unset($aResult['month']);
				unset($aResult['only_o2o_stats']);
				unset($aResult['regist_date']);
				unset($aResult['update_date']);
			}
		}
		
		// 수집된 통계 정보 반환
		return $aResult;
	}
	
	/*
	 * 수집 데이터 추가
	 */
	function add_scrap_sales_monthly($aPrams){
		// 수집에 불필요한 데이터 제거
		unset($aPrams['caltableck']);
		unset($aPrams['where_order']);
		unset($aPrams['sitetype']);
		unset($aPrams['not_sitetype']);
		unset($aPrams['without_null_sitetype']);
		unset($aPrams['provider_seq']);
		
		if(empty($aPrams['save_provider_seq'])){
			$aPrams['save_provider_seq'] = '1';
		}

		// view에서 노출할 문구 조합
		$sScrapInfoQType = '';
		if($aPrams['q_type']== 'commission'){
			$sScrapInfoQType = '정산';
		}elseif($aPrams['q_type']== 'order'){
			$sScrapInfoQType = '매출';
		}elseif($aPrams['q_type']== 'refund'){
			$sScrapInfoQType = '환불';
		}
		$sScrapInfoO2O = '';
		if($aPrams['only_o2o_stats']== '1'){
			$sScrapInfoO2O = '(매장)';
		}

		$sBr = '<br/>';
		$sScrapInfoText = ''.
			'통계 정보를 수집중입니다.'.$sBr.$sBr
			.'진행 : <span class=\'progress_cnt\'></span>/<span class=\'progress_cnt_total\'></span>'.$sBr
			.'타입 : '.$sScrapInfoQType.$sScrapInfoO2O.$sBr
			.'년 : '.$aPrams['year'].$sBr
			.'월 : '.$aPrams['month']
		;
		$aPrams['sScrapInfoText'] = $sScrapInfoText;
		$this->jsonRequestScrapSalesMonthly[] = $aPrams;
		
	}
	
	/*
	 * 수집 이력 확인
	 * 수집 이력이 없을 시 view 영역에서 수집 요청이 처리 될 수 있도록 수집 요청 변수 추가
	 */
	function check_scrap_sales_monthly($aPrams, &$bAlreadyScraped){
		
		//  수집 이력 확인
		$sTable = $this->sTbScapSalesMonthly[$aPrams['q_type']];
		$this->db->where('year',			$aPrams['year']);
		$this->db->where('month',			$aPrams['month']);
		$this->db->where('provider_seq',	$aPrams['save_provider_seq']);
		$this->db->where('only_o2o_stats',	($aPrams['only_o2o_stats']=='1'?'Y':'N'));
		$oQuery	= $this->db->get($sTable);
		$aRow	= $oQuery->row_array();
		if($aRow && empty($aPrams['tryScrap'])){
			$bAlreadyScraped = true;
		}
		
		if(!$bAlreadyScraped){
			// 수집 데이터 추가
			$this->add_scrap_sales_monthly($aPrams);
		}
		
		// 수집된 통계 정보 반환
		return $aRow;
	}
	
	/*
	 * 통계정보 수집 및 저장
	 */
	function create_scrap_sales_monthly($aPrams){
		
		$where_order = array();
		$where_order = $this->make_where_order_sales_sales_monthly_stats($aPrams);
		
		$tb_act_ym	=	$aPrams['year'].$aPrams['month'];
		$caltableck		= $this->get_table_ym_ck('calculate',$tb_act_ym);
		if(empty($aPrams['save_provider_seq'])){
			$aPrams['save_provider_seq'] = '1';
		}
		
		$params_stats_v2 = array();
		$params_stats_v2						= $aPrams;
		$params_stats_v2['caltableck']			= $caltableck;
		$params_stats_v2['where_order']			= $where_order;
		$params_stats_v2['month']				= $aPrams['month'];
		$params_stats_v2['provider_seq']		= $aPrams['provider_seq'];
		$params_stats_v2['bRunScrap']			= true;						// 수집 요청
		$statsData = $this->get_sales_sales_monthly_stats_v2($params_stats_v2);
		
		// 저장용 데이터 가공
		$statsData['year']				= $aPrams['year'];
		$statsData['month']				= $aPrams['month'];
		$statsData['provider_seq']		= $aPrams['save_provider_seq'];
		$statsData['only_o2o_stats'] 	= ($aPrams['only_o2o_stats'] == '1')?'Y':'N';
		$statsData['regist_date']		= date('Y-m-d H:i:s');
		$statsData['update_date']		= date('Y-m-d H:i:s');
		
		// 수집된 데이터 저장
		$sTable = $this->sTbScapSalesMonthly[$aPrams['q_type']];
		
		$sTableInsertParams = filter_keys($statsData, $this->db->list_fields($sTable));
		
		// 데이터가 있을 시 수정 없을 시 입력
		$result = $this->db->replace($sTable, $sTableInsertParams);
		return $result;
	}
	
	/*
	 * 매출정보 수집 및 통계 표기를 위한 쿼리 조건절 조합
	 */
	function make_where_order_sales_sales_monthly_stats($_PARAM){
		
		if($_PARAM['only_o2o_stats'] == '1'){
			$_PARAM['sitetype']					= array('POS');
			$_PARAM['without_null_sitetype']	= '1';
		}else{
			$_PARAM['sitetype']					= array('P', 'M', 'F', 'APP_ANDROID', 'APP_IOS');
			$_PARAM['not_sitetype']				= array('POS');
		}
		
		// 마이그레이션 날짜에맞춰서 통계가 노출되어야하기 때문에 추가 :: 2019-01-21 lkh
		$accountAllMiDate			= getAccountSetting();
		$checkDate					= date("Y-m");
		$accountAllStatsV2			= $accountAllMiDate['accountall_stats_v2'];		// 통계 개선 2019-06-19 by hed
		$accountAllMigrationDate	= $accountAllMiDate['migration_date'];
		$migrationYear				= $accountAllMiDate['migrationYear'];
		$migrationMonth				= $accountAllMiDate['migrationMonth'];
		
		if($_PARAM['q_type'] == 'commission'){
			$where_order[] = "caltb.status in ('carryover','complete') ";
		}else{
			$where_order[] = "caltb.status in ('overdraw','complete') ";
		}
		if	($_PARAM['q_type'] == 'order'){
			$where_order[]	= "caltb.account_type not in ('refund','rollback','after_refund') ";
		}elseif($_PARAM['q_type'] == 'commission'){	//정산은 전체다 불러오기
		}else{
			$where_order[]	= "caltb.account_type in ('refund','rollback','after_refund') ";
		}
		// 입점사
		if (!empty($_PARAM['provider_seq'])){
			$where_order[]	= "caltb.provider_seq = '".$_PARAM['provider_seq']."' ";
		}
		// 주문 타입
		$table_chr = 'o.';
		if (is_array($_PARAM['sitetype']) && count($_PARAM['sitetype']) > 0){
			if($accountAllStatsV2 && date("Ymd",$accountAllStatsV2) <= date("Ymd")){
				$table_chr = 'caltb.';
				if(empty($_PARAM['without_null_sitetype'])){
					$_PARAM['sitetype'][] = "";
				}
			}	
			$where_order[]	= $table_chr."sitetype in ('".implode("','",$_PARAM['sitetype'])."') ";
		}
		if	(is_array($_PARAM['not_sitetype']) && count($_PARAM['not_sitetype']) > 0){
			$where_order[]	= " ".$table_chr."sitetype not in ('".implode("','",$_PARAM['not_sitetype'])."') ";
		}
		
		return $where_order;
	}
	
	function get_time_range(){
		$this_year = date("Y");
		
		$year = array();
		$month = array();
		
		$this->db->select('regist_date');
		$this->db->where('regist_date > ', '0000-00-00');
		$this->db->order_by('regist_date', 'ASC');
		$this->db->limit(1);
		$query = $this->db->get('fm_order');
		
		$order = $query->result_array();
		if($order[0]['regist_date']){
			$start = substr($order[0]['regist_date'],0,4);
		}else{
			$start = $this_year;
		}

		$cnt = $this_year - $start;
		if($cnt<1){
			$year[] = $start;
		}else{
			for($i=$this_year;$i>=$start;$i--){
				$year[] = $i;
			}
		}
		for($i=1;$i<13;$i++){
			$temp = ($i > 9) ? $i : "0".$i;
			$month[] = $temp;
		}
		return array($year,$month);
	}


	
	function get_stats_data_sales_daily($params, &$statsData){
		$params['q_type']	= 'order';
		$query	= $this->get_sales_sales_daily_stats($params);
		if($query){
			foreach($query->result_array() as $row){
				if($migrationYear < $params['year'] || ($migrationYear == $params['year'] && $migrationMonth <= $row['stats_month']))
					$statsData[$row['stats_day']-1] = is_array($statsData[$row['stats_day']-1]) ? array_merge($statsData[$row['stats_day']-1],$row) : $row;
			}
		}

		$params['q_type']	= 'refund';
		$query	= $this->get_sales_sales_daily_stats($params);
		if($query){
			foreach($query->result_array() as $row){
				if($migrationYear < $params['year'] || ($migrationYear == $params['year'] && $migrationMonth <= $row['stats_month']))
					$statsData[$row['stats_day']-1] = is_array($statsData[$row['stats_day']-1]) ? array_merge($statsData[$row['stats_day']-1],$row) : $row;
			}
		}
		return $statsData;
	}
	
	
	/* 매출액, 매입금액평균, 순이익 계산 */
	function make_view_sales_daily(&$statsData){
		foreach($statsData as $i => $row){
			// 매출
			$statsData[$i]['order_price'] = $row['day_settleprice_sum']+$row['day_cash_use_sum'];
			// 할인합계
			$statsData[$i]['discount_price_sum'] = $row['day_enuri_sum']+$row['day_emoney_use_sum']+$row['day_coupon_sale_sum']+$row['day_fblike_sale_sum']+$row['day_mobile_sale_sum']+$row['day_promotion_code_sale_sum']+$row['day_member_sale_sum']+$row['day_referer_sale_sum']+$row['day_event_sale_sum']+$row['day_multi_sale_sum']+$row['day_api_pg_sale_sum'];
			// 환불,롤백할인합계
			$statsData[$i]['refund_discount_price_sum'] = $row['day_refund_enuri_sum']+$row['day_refund_emoney_use_sum']+$row['day_refund_coupon_sale_sum']+$row['day_refund_fblike_sale_sum']+$row['day_refund_mobile_sale_sum']+$row['day_refund_promotion_code_sale_sum']+$row['day_refund_member_sale_sum']+$row['day_refund_referer_sale_sum']+$row['day_refund_event_sale_sum']+$row['day_refund_multi_sale_sum']+$row['day_refund_api_pg_sale_sum'];
			// 할인합계 - 환불,롤백할인합계
			$statsData[$i]['discount_price'] = $statsData[$i]['discount_price_sum']-$statsData[$i]['refund_discount_price_sum'];
			// 매출액
			$statsData[$i]['sales_price'] = $row['day_settleprice_sum']+$row['day_cash_use_sum']-$row['day_refund_price_sum'];
			// 매입원가
			$statsData[$i]['day_supply_price']	= $statsData[$i]['day_supply_price_sum']-$row['day_refund_supply_price_sum'];
			// 순이익
			$statsData[$i]['interests'] = $statsData[$i]['sales_price']-$statsData[$i]['day_supply_price'];

			// 매출이익
			$statsData[$i]['day_sales_benefit'] = $statsData[$i]['order_price']-$statsData[$i]['day_refund_price_sum_total'];

			// 매출이익 %
			$statsData[$i]['day_sales_benefit_percent'] = ($statsData[$i]['day_sales_benefit']>0) ? round(($statsData[$i]['day_sales_benefit'] / $statsData[$i]['order_price']) * 100,2) : 0;
		}
	}
}

/* End of file accountallmodel.php */
/* Location: ./app/models/accountallmodel.php */
