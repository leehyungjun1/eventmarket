<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/batch/dailyGoods".EXT);
class dailyMember extends dailyGoods {
	public function __construct() {
		parent::__construct();
	}
	### 회원정보 다운로드 로그 기록 삭제(1년전)
	public function log_member_excel_delete() {		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$limit_date = date('Y-m-d H:i:s', strtotime("-1 year"));
			$query	= "delete from fm_log_member_download where reg_date < '".$limit_date."' ";
			$this->db->query($query);			
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	## 만료된 마일리지, 예치금 차감
	protected function _exec_member_emoney_deduction($type='emoney',$days_ago='1')
	{
		$table			= "fm_".$type;
		$field			= $type;
		$limitCheckDate = $type."_limitDate";
		$flag			= false;

		$limit_date = date('Y-m-d',strtotime("-".$days_ago." day"));
		$query = "select
					e.member_seq
					,sum(e.remain) as remain
				from
					{$table} as e
					left join fm_member as m on m.member_seq=e.member_seq
				where
					e.limit_date = '{$limit_date}'
					and e.gb='plus'
					and (m.{$limitCheckDate} != e.limit_date or m.{$limitCheckDate} is null)
				group by e.member_seq";
		$query = mysqli_query($this->db->conn_id,$query);

		if($query) {
			while($data_emoney = mysqli_fetch_array($query)) {
				$query_update = "update
									fm_member
								set
									{$field} = {$field} - {$data_emoney['remain']},
									{$limitCheckDate} = '{$limit_date}'
								where
									member_seq = '{$data_emoney['member_seq']}'
									and ({$limitCheckDate} != '{$limit_date}' or {$limitCheckDate} is null)";
				 mysqli_query($this->db->conn_id,$query_update);
			}
		}	
	}	
	public function member_emoney_deduction()
	{
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			// emoney 마이그레이션 날짜 체크
			$cfg_member = config_load('member','emoney_deduction');
			$emoney_deduction = $cfg_member['emoney_deduction'] ? $cfg_member['emoney_deduction'] : date('Y-m-d',strtotime("-2 day"));

			// 현재 날짜 비교 일수 체크
			$deduction_date	= new DateTime(date('Y-m-d',strtotime($emoney_deduction)));
			$yesterday_date	= new DateTime(date('Y-m-d',strtotime("-1 day")));
			$date_gap		= date_diff($deduction_date,$yesterday_date);
			/**
			 * date_gap 만큼 실행
			 * 오늘 2021-06-11
			 * ex1) 초기 값없음 > 어제  : 2021-06-10
			 * date_gap = 1
			 * 		for -1day 로 한번 실행 리턴
			 * ex2) emoney_deduction : 2021-06-08
			 * date_gap = 3
			 * 		for -3day 로 '2021-06-08'
			 * 		for -2day 로 '2021-06-09'
			 * 		for -1day 로 '2021-06-10'
			 */
			for($i=$date_gap->days; $i > 0; $i-- ) {
				$this->_exec_member_emoney_deduction('emoney', $i);
				// 처리한 날짜까지 저장
				$migration_date = date('Y-m-d',strtotime("-".$i." day"));
				config_save('member',array('emoney_deduction'=> $migration_date));
			}

		} catch (Exception $e) {	
			if( $aFunc )	$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aNextFunc['sMsg'] . '(' . $aNextFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}	
	public function member_point_deduction()
	{		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);		
		try{
			// point 마이그레이션 날짜 체크
			$cfg_member = config_load('member','point_deduction');
			$point_deduction = $cfg_member['point_deduction'] ? $cfg_member['point_deduction'] : date('Y-m-d',strtotime("-2 day"));

			// 현재 날짜 비교 일수 체크
			$deduction_date	= new DateTime(date('Y-m-d',strtotime($point_deduction)));
			$yesterday_date	= new DateTime(date('Y-m-d',strtotime("-1 day")));
			$date_gap		= date_diff($deduction_date,$yesterday_date);

			for($i=$date_gap->days; $i > 0; $i-- ) {
				$this->_exec_member_emoney_deduction('point', $i);
				// 처리한 날짜까지 저장
				$migration_date = date('Y-m-d',strtotime("-".$i." day"));
				config_save('member',array('point_deduction'=> $migration_date));
			}
		} catch (Exception $e) {						
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	
	// 2014-05-30 입점사 등급 자동 갱신
	public function exec_update_provider_group()
	{
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model('providermodel');
			$result	= $this->providermodel->provider_group_update();	
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	// 2014-03-03 회원등급 업그레이드 : 기존로직 주석(member_grade_setting)
	public function update_member_grade()
	{
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);		
		try{
			$sToday = date("d");
			if($sToday=='01' || $sToday=='15'){
				
				$this->load->model("membermodel");
				$cfg_grade = config_load('grade_clone');

				if(!$cfg_grade['start_month'] || !$cfg_grade['chg_day'] || !$cfg_grade['chg_term'] || !$cfg_grade['chk_term'] || !$cfg_grade['keep_term']){					
					throw new Exception('update_member_grade unused');
				}

				$data_grade_date = $this->membermodel->calculate_date($cfg_grade['start_month'],$cfg_grade['chg_day'],$cfg_grade['chg_term'],$cfg_grade['chk_term'],$cfg_grade['keep_term']);

				// 등급 변경
				$today = date("Y-m-d");
				// $today = "2014-04-15";

				if( isset( $chg_key ) ) unset($chg_key);
				foreach($data_grade_date['chg_text'] as $k=>$v)
				{
					if($v == $today) $chg_key = $k;
				}
				if( !isset( $chg_key ) ) throw new Exception('update_member_grade no chg_text');

				if(	$data_grade_date['chk_text_start'][$chg_key] && $data_grade_date['chk_text_end'][$chg_key] && $data_grade_date['keep_text_end'][$chg_key] )
				{
					$chk_start_month = substr(str_replace('-','',$data_grade_date['chk_text_start'][$chg_key]),0,6);
					$chk_end_month = substr(str_replace('-','',$data_grade_date['chk_text_end'][$chg_key]),0,6);
					$keep_term_date = $data_grade_date['keep_text_end'][$chg_key];

					// 회원등급 변경
					$this->_exec_update_member_grade($chk_start_month,$chk_end_month,$keep_term_date,$cfg_grade);
				}
			}			
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
	// 2014-03-03 회원등급 업그레이드 실행 : 기존로직 주석(member_grade_setting)
	protected function _exec_update_member_grade($chk_month,$chk_month2,$keep_term_date,$cfg_grade)
	{
		$where = array();
		$group_manual = "";
		$group_name = array();
		$yesterday = date("Y-m-d",time()-24*3600);
		// $yesterday = "2014-04-14";
		$sort_num = 0;
		### FM_MEMBER_GROUP
		$sql = "select * from fm_member_group order by order_sum_price asc, order_sum_cnt asc, sale_price asc";
		$query = $this->db->query($sql);
		if($query->num_rows() < 1) return;
		foreach ($query->result_array() as $row){
			$sort_num++;
			if($row['use_type']=='AUTO' || $row['use_type']=='AUTOPART') $group[] = $row;
			else $group_manual[] = $row['group_seq'];

			$group_name[$row['group_seq']] = $row['group_name'];
			$group_sort[$row['group_seq']] = $sort_num;
		}
		//$where[] = "A.group_set_date <= '".$yesterday." 23:59:59'";
		$where[] = "A.status IN ('done', 'hold')";
		if($group_manual){
			$where[] = "A.group_seq not in (".implode(',',$group_manual).")";
		}
		if($where[0]){
			$where_str = 'WHERE '.implode(" AND ",$where);
		}

		### FM_MEMBER_ORDER
		$sql = "
		select
			A.member_seq, A.group_seq, A.group_set_date,
			ifnull(sum(step75_count),0) 75cnt,
			ifnull(sum(refund_count),0) as r_cnt,
			ifnull(sum(step75_ea),0) 75ea,
			ifnull(sum(refund_ea),0) as r_ea,
			ifnull(sum(step75_price),0) 75price,
			ifnull(sum(refund_price),0) as r_price
		from
			fm_member A
			left join fm_member_order B ON A.member_seq = B.member_seq
			and B.month between '{$chk_month}' and '{$chk_month2}'
		".$where_str."
		group by A.member_seq";
		$res = mysqli_query($this->db->conn_id,$sql);
		while ($v = mysqli_fetch_array($res)){
			$this->db->queries = array();
			$this->db->query_times = array();
			
			$v['count'] = $v['75cnt']-$v['r_cnt'];
			$v['ea'] = $v['75ea']-$v['r_ea'];
			$v['price'] = $v['75price']-$v['r_price'];
			### FM_MEMBER_GROUP
			$chg_group_seq = 0;
			foreach ($group as $row){
				$use_chk_cnt = 0;
				$stat_chk_cnt= 0;
				$row['order_sum_arr'] = unserialize($row['order_sum_use']);

				if(in_array('price',$row['order_sum_arr'])){
					$use_chk_cnt++;
					if($row['order_sum_price']<=$v['price']) $stat_chk_cnt++;
				}
				if(in_array('ea',$row['order_sum_arr'])){
					$use_chk_cnt++;
					if($row['order_sum_ea']<=$v['ea']) $stat_chk_cnt++;
				}
				if(in_array('cnt',$row['order_sum_arr'])){
					$use_chk_cnt++;
					if($row['order_sum_cnt']<=$v['count']) $stat_chk_cnt++;
				}
				if($row['use_type']=='AUTO'){
					if($use_chk_cnt==$stat_chk_cnt) $chg_group_seq = $row['group_seq'];
				}else if($row['use_type']=='AUTOPART'){
					if($stat_chk_cnt>=1) $chg_group_seq = $row['group_seq'];
				}
			}
			### 등급이 하향조정됟때 등급유지기간 체크
			$to_datetime = $yesterday.' 23:59:59';
			$change_group = false;
			if( $chg_group_seq!=0 ) {
				if($v['group_seq']!=$chg_group_seq ){
					$change_group = true;
					if( $group_sort[$v['group_seq']] > $group_sort[$chg_group_seq] && $v['group_set_date'] > $to_datetime ) {
						$change_group = false;
					}
					 //변경 등급과 동일하며 등급유지기간이 지났다면 갱신
				}elseif($v['group_seq'] == $chg_group_seq && $v['group_set_date'] != '0000-00-00 00:00:00' && $v['group_set_date'] < $to_datetime) {
						$change_group = true;
				}
			}
			### CHECK
			if($change_group)
			{
				### LOG
				$i_qry = "insert into fm_member_group_log set member_seq = ?, prev_group_seq = ?, chg_group_seq = ?, regist_date=now()";
				$this->db->query($i_qry,array($v['member_seq'],$v['group_seq'],$chg_group_seq));
				### admin LOG
				if( $cfg_grade['keep_term'] )
					$grade_msg = "(".$cfg_grade['keep_term']."개월간 등급 유지)";
				$admin_log = "<div>[자동] ".date("Y-m-d H:i:s")." ".$group_name[$v['group_seq']]." → ".$group_name[$chg_group_seq].$grade_msg."</div>";
				### UPDATE
				$u_qry = "update fm_member set group_seq =?, admin_log =concat(?,ifnull(admin_log,'')), group_set_date = '".$keep_term_date." 23:59:59', grade_update_date = now()  where member_seq = ?";
				$this->db->query($u_qry,array($chg_group_seq,$admin_log,$v['member_seq']));
			}
		}			
	}	
	public function dormancy_request(){	
	
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model("membermodel");
			$this->load->model("kakaotalkmodel");
			$sms		= config_load('sms');
			$kakao		= $this->kakaotalkmodel->get_msg_code(array("msg_code"=>"dormancy_user"));

			//오늘 기준으로 1년동안 미접속자 휴면처리
			$this->membermodel->dormancy_on();

			//휴면처리 대상고지
			if($sms['dormancy_user_yn'] == "Y" || $kakao['dormancy_user']['msg_yn'] == "Y"){
				$dormancy_du_date		= date("Y-m-d",strtotime("+1 month"));
				$dormancy_notify_date	= date("Y-m-d",strtotime("-11 month"));

				$result = $this->membermodel->dormancy_notify_list($dormancy_notify_date);
				$dormancy_count = 0;
				foreach($result as $dormancy){
					if($dormancy['cellphone']){
						$params['shopName']				= $this->config_basic['shopName'];
						$params['member_seq']			= $dormancy['member_seq'];
						$params['user_name']			= $dormancy['user_name'];
						$params['userid']				= $dormancy['userid'];
						$params['dormancy_du_date']		= $dormancy_du_date;
						$arr_params[$dormancy_count]	= $params;
						$dr_cellphones[$dormancy_count] = $dormancy['cellphone'];
						$dormancy_count					= $dormancy_count+1;
					}
				}

				//휴면처리 대상고지 SMS 데이터 생성
				if(count($dr_cellphones) > 0){
					$commonSmsData['dormancy']['phone'] = $dr_cellphones;
					$commonSmsData['dormancy']['params'] = $arr_params;
				}

				if(count($commonSmsData) > 0){
					commonSendSMS($commonSmsData);
				}
			}
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}	
	
	### 마케팅 수신 동의 회원 수집
	public function send_marketing_agree() {
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->_exec_send_marketing_agree();
		} catch (Exception $e) {
			if( $aFunc ) {
				$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
			}
		}
		if( $aNextFunc ) {
			$this->{$aNextFunc['sFunctionName']}();
		}
		
		if( $aFunc ) {
			$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
		}
	}
	
	protected function _exec_send_marketing_agree()
	{
		//조건
		//1.메일이나 sms 수신 동의
		//2.최종 marketing_agree_send_date가 현재 기준으로 한달 전
		//4.2년 이내 발송 이력이 없는 회원
		//3.휴면 회원의 경우 발송은 하지 않음
		//cron 첫번째
		
		$this->load->model('membermodel');
		
		$params = array();
		$params['limit']	= "1000";
		$params['count']	= TRUE;
		
		$countRes	= $this->membermodel->get_member_marketing_agree($params);
		$totalCount	= $countRes[0]['cnt'];
		
		if ($totalCount > 0) {
			$loopCount		= floor($totalCount/$params['limit']);
			$params['count']= FALSE;
			
			for($i=0; $i<=$loopCount; $i++){
				$params['offset']	= $i*$params['limit'];
				$res				= $this->membermodel->get_member_marketing_agree($params);
				
				$dataBatch = array();
				foreach($res as $k => $v){
					$diffRes = $this->membermodel->get_member_marketing_send_log($v['member_seq'], 'batch', $v['marketing_agree_send_date']);
					
					$isInsert = false;
					if (count($diffRes) <= 0) {
						if ($v['mailing'] == 'y' && $v['sms'] == 'y') {
							$dataBatch[$k]['type'] = 'a';
						} else if ($v['mailing'] == 'n' && $v['sms'] == 'y') {
							$dataBatch[$k]['type'] = 's';
						} else if ($v['mailing'] == 'y' && $v['sms'] == 'n') {
							$dataBatch[$k]['type'] = 'm';
						}
						
						$dataBatch[$k]['member_seq']	= $v['member_seq'];
						$dataBatch[$k]['receive_addr']	= $v['email'];
						$dataBatch[$k]['reg_date']		= date('Y-m-d H:i:s');
					}
				}
				$this->db->insert_batch("fm_marketing_send_log ", $dataBatch, NULL);
			}
			unset($dataBatch);
		}
	}
	
	### 마케팅 수신 동의 회원 날짜 업데이트
	public function update_marketing_agree_date() {
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->_exec_update_marketing_agree_date();
		} catch (Exception $e) {
			if( $aFunc ) {
				$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
			}
		}
		if( $aNextFunc ) {
			$this->{$aNextFunc['sFunctionName']}();
		}
		
		if( $aFunc ) {
			$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
		}
	}
	
	protected function _exec_update_marketing_agree_date()
	{
		//조건
		//1.초기 수신동의 관련은 update_date 기준으로 한다
		//2.2014/11/29일 이전 수신동의일 인 경우 일괄 2018/11/29일로 업데이트
		//3.2014/11/29일 이후 수신동의일 인 경우 24개월 업데이트
		//4.24개월 업데이트가 현재 일보다 이전일 경우 다시 24개월 업데이트
		//cron 두번째
		
		$this->load->model('membermodel');
		
		$params = array();
		$params['limit'] = "1000";
		$params['count'] = TRUE;
		
		$countRes	= $this->membermodel->get_member_marketing_update($params);
		$totalCount	= $countRes[0]['cnt'];
		//$totalCount = 1; //for test
		
		if ($totalCount > 0) {
			$loopCount		= floor($totalCount/$params['limit']);
			$params['count']= FALSE;
			
			for($i=0; $i<=$loopCount; $i++){
				$params['offset'] = $i*$params['limit'];
				$res = $this->membermodel->get_member_marketing_update($params);
				
				$updateDatas = array();
				
				//$res[0]['update_date'] = '2019-03-22 00:00:00'; //for test
				
				foreach ($res as $k => $v) {
					$updateDatas[$k]['member_seq'] = $v['member_seq'];
					$limit_date = date("Y-m-d H:i:s", strtotime("-2 year", time()));
					
					if ($v['update_date'] < '2014-11-29 00:00:00') { //법령 시행 이전 날짜는 고정값
						$updateDatas[$k]['marketing_agree_send_date'] = '2020-11-28 00:00:00';
					} else if ($v['update_date'] >= '2014-11-29 00:00:00' && $v['update_date'] <= $limit_date) { //법령 시행 이후 && 오늘 기준 2년 이전 수정일
						$diffDate = strtotime(date('Y-m-d H:i:s')) - strtotime($v['update_date']);
						$diffYear = floor(($diffDate/86400)/365);
						
						$sendDate = $v['update_date'];
						for($j=1; $j<=$diffYear; $j++){
							if($j%2 == 0){
								$sendDate = date('Y-m-d H:i:s', strtotime("+2 year", strtotime($sendDate)));
								
								if ($sendDate > date('Y-m-d H:i:s')) {
									continue;
								}
							}
						}
						
						if ($sendDate <= date('Y-m-d H:i:s')) {
							$sendDate = date('Y-m-d H:i:s', strtotime("+2 year -1 day", strtotime($sendDate)));
						} else {
							$sendDate = date('Y-m-d H:i:s', strtotime("-1 day", strtotime($sendDate)));
						}
						
						$updateDatas[$k]['marketing_agree_send_date'] = $sendDate;
					} else {
						$updateDatas[$k]['marketing_agree_send_date'] = date("Y-m-d 00:00:00", strtotime("+2 year -1 day", strtotime($v['update_date'])));
					}
				}
				$this->db->update_batch("fm_member", $updateDatas, 'member_seq', true); //마지막 param은 escape 여부
			}
			
			unset($updateDatas);
		}
	}

	// 휴면/탈퇴회원 개인정보 분리 마이그레이션
	public function migration_member_separate() {
		//조건
		//1.패치후 신규 솔루션은 실행안함
		//2.기존 솔루션 개인정보 분리 설정 사용함 설정 시 익일 처리
		//3.휴면/탈퇴회원 기준 주문데이터를 조회하여 최초 1회 분리 처리

		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model('membermodel');
			$this->load->library('personalinfolibrary');

			$order = config_load('order');

			// 개인정보 분리
			if ($order['separating_personal_info'] == 'y' && !$order['migration_member_separate']) {
				$members = $this->membermodel->getSeparationMember();
				$result = $this->personalinfolibrary->separatePersonalInfo($members);

				if ($result || empty($members)) {
					$migration_date = date('Y-m-d H:i:s');
					config_save('order',array('migration_member_separate'=> $migration_date));
				} else {
					throw new Exception('Migration Member Separate Fail');
				}
			}
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
}
