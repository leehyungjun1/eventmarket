<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/batch/dailyEp".EXT);
class dailyOrder extends dailyEp {
	public function __construct() {
		parent::__construct();
	}	
	// 자동주문 취소시 실행
	public function order_cancel(){		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->helper('basic');
			$this->load->model('membermodel');
			$this->load->model('ordermodel');
			$this->load->model('couponmodel');
			$this->load->model('promotionmodel');
			$this->load->model('goodsmodel');

			$r_reservation_goods_seq = array();

			// 주문설정 로드
			$cfg = config_load('order');

			if ($cfg['autocancel'] == 'n') {
				throw new Exception('NO AUTOCANCEL');
			}

			if (!$cfg['cancelDuration']) {
				throw new Exception('NO SETTING');
			}

			// 주문 무효시킬 주문의 주문일자조건
			$end = date('Y-m-d 00:00:00', strtotime('-' . $cfg['cancelDuration'] . ' day'));

			$step95 = [];
			$query = 'SELECT order_seq FROM `fm_order` WHERE `regist_date` < ? AND `step`=15 AND npay_order_id is null AND talkbuy_order_id is null';
			$query = $this->db->query($query, [$end]);
			foreach ($query->result_array() as $orders) {
				$step95[] = $orders['order_seq'];
			}

			/* 마일리지 환원 */
			$query = "SELECT * FROM `fm_order` WHERE `regist_date` < ? AND `step`=15 AND npay_order_id is null AND talkbuy_order_id is null AND (emoney_use='use' or cash_use='use')";
			$query = $this->db->query($query, [$end]);
			foreach ($query->result_array() as $orders) {
				if ($orders['emoney_use'] == 'use') {
					$params = [
						'gb' => 'plus',
						'type' => 'cancel',
						'emoney' => $orders['emoney'],
						'ordno' => $orders['order_seq'],
						'memo' => '[복원]자동 주문무효(' . $orders['order_seq'] . ')에 의한 마일리지 환원',
						'limit_date' => get_emoney_limitdate('order'),
						'memo_lang' => $this->membermodel->make_json_for_getAlert('mp250', $orders['order_seq']),  // [복원]자동 주문무효(%s)에 의한 마일리지 환원
					];
					$this->membermodel->emoney_insert($params, $orders['member_seq']);
					$this->ordermodel->set_emoney_use($orders['order_seq'], 'return');
				}

				/* 예치금 환원 */
				if ($orders['cash_use'] == 'use') {
					$params = [
						'gb' => 'plus',
						'type' => 'cancel',
						'cash' => $orders['cash'],
						'ordno' => $orders['order_seq'],
						'memo' => '[복원]자동 주문무효(' . $orders['order_seq'] . ')에 의한 예치금 환원',
						'memo_lang' => $this->membermodel->make_json_for_getAlert('mp251', $orders['order_seq']),  // [복원]자동 주문무효(%s)에 의한 예치금 환원
					];
					$this->membermodel->cash_insert($params, $orders['member_seq']);
					$this->ordermodel->set_cash_use($orders['order_seq'], 'return');
				}
			}


			/* 할인쿠폰 환원 */
			$query = 'SELECT * FROM `fm_order` WHERE `regist_date` < ? AND `step`=15 AND npay_order_id is null';
			$query = $this->db->query($query, [$end]);
			foreach ($query->result_array() as $orders) {
				$options = $this->ordermodel->get_item_option($orders['order_seq']);
				$suboptions = $this->ordermodel->get_item_suboption($orders['order_seq']);

				/* 배송비쿠폰 복원*/
				if ($orders['download_seq']) {
					$shippingcoupon = $this->couponmodel->restore_used_coupon($orders['download_seq']);
				}

				// 주문서쿠폰 복원
				if ($orders['ordersheet_seq']) {
					$ordersheetcoupon = $this->couponmodel->restore_used_coupon($orders['ordersheet_seq']);
				}

				/* 배송비프로모션코드 복원 개별코드만 */
				if ($orders['shipping_promotion_code_seq']) {
					$shippingpromotioncode = $this->promotionmodel->restore_used_promotion($orders['shipping_promotion_code_seq']);
				}

				//상품별 할인쿠폰/프로모션코드 복원
				foreach ($options as $data_option) {
					if ($data_option['download_seq']) {
						$this->couponmodel->restore_used_coupon($data_option['download_seq']);
					}
					if ($data_option['promotion_code_seq']) {
						$this->promotionmodel->restore_used_promotion($data_option['promotion_code_seq']);
					}

					// 출고량 업데이트를 위한 변수정의
					if (!in_array($data_option['goods_seq'], $r_reservation_goods_seq)) {
						$r_reservation_goods_seq[] = $data_option['goods_seq'];
					}
				}
			}

			// 주문서 상품의 상태를 변경 합니다.
			$query = "
			UPDATE `fm_order_item_option` SET `step`='95'
			WHERE `step`='15'
			AND `order_seq` IN
			(
				SELECT `order_seq` FROM `fm_order` WHERE `regist_date` < ? AND `step`=15 AND npay_order_id is null
			)";
			$this->db->query($query,array($end));

			$query = "
			UPDATE `fm_order_item_suboption` SET `step`='95'
			WHERE `step`='15'
			AND `order_seq` IN
			(
				SELECT `order_seq` FROM `fm_order` WHERE `regist_date` < ? AND `step`=15 AND npay_order_id is null
			)";
			$this->db->query($query, [$end]);

			// 주문서의 상태를 변경 합니다.
			$query = 'UPDATE `fm_order` SET `step`=95 WHERE `step`=15 AND npay_order_id is null AND `regist_date` < ?';
			$this->db->query($query, [$end]);

			// 출고예약량 업데이트
			foreach ($r_reservation_goods_seq as $goods_seq) {
				$this->goodsmodel->modify_reservation_real($goods_seq);
			}

			foreach ($step95 as $order_seq) {
				// 주문로그
				$this->ordermodel->set_log($order_seq, 'cancel', '시스템', '주문무효');
			}

		} catch (Exception $e) {
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}

	
	/*
	자동구매확정 대상 출고건 가져오기
	*/
	public function _get_buy_confirm_target($cfg_order,$mode='',$confirm_time=''){

		if(!$confirm_time) $confirm_time = mktime();
		$_standard_date_field		= "complete_date";			// 검색 필드 기준(출고완료일 complete_date, 배송완료일 shipping_date)
		$_standard_date_type		= "from";					// from(기본설정) : ~로 부터 00일 ,  range : 0000-00-00부터 ~ 0000-00-00일까지
		if($_standard_date_field == "shipping_date"){
			$_standard_edate		= date('Y-m-d',$confirm_time);
		}else{
			$_standard_edate		= date('Y-m-d',strtotime("-".$cfg_order['save_term']." day",$confirm_time));		// from 일 때 : 출고완료일 or 배송완료일로부터 00일이 지난 출고건 구매확정 처리
		}
		//$_standard_date_range_s		= "2019-03-01";				// range 일 때 : 검색 시작일
		//$_standard_date_range_e		= "2019-04-23";				// range 일 때 : 검색 종료일

		if($mode == 'view'){
			return $_standard_edate;
			exit;
		}

		echo "\n\r_standard_date_field : ".$_standard_date_field."\n\r";
		echo "_standard_date_type : ".$_standard_date_type."\n\r";
		echo "_standard_edate : ".$_standard_edate."\n\r";

		// 신정산 마이그레이션 일자 체크
		$_accountall_setting		= config_load("accountall_setting");
		$accountall_migration_date	= "";
		if($_accountall_setting['accountall_migration_date'] != "0000-00-00"){
			$accountall_migration_date = $_accountall_setting['accountall_migration_date'];
		}

		$_query ="SELECT
					exp.*
				FROM
					fm_goods_export AS exp
					LEFT JOIN fm_goods_export_item AS exp_item ON exp_item.export_code=exp.export_code 
					,fm_order as ord
				";

		$_wheres[] = "ord.order_seq=exp.order_seq";
		$_wheres[] = "exp.status >= '55'";
		$_wheres[] = "exp.npay_order_id IS NULL";
		$_wheres[] = "exp.talkbuy_order_id IS NULL";		// 카카오페이 자동 구매확정 예외
		$_wheres[] = "exp.buy_confirm='none'";
		//$_wheres[] = "exp.order_seq like '2018%'";

		if($_standard_date_type == "range"){										
			$_wheres[] = "exp.".$_standard_date_field." BETWEEN '".$_standard_date_range_s."' AND '".$_standard_date_range_e."'";
		}else{
			//신정산 마이그레이션 일자 이후 주문건 부터 구매확정 처리
			if($accountall_migration_date){
				$_wheres[] = "exp.".$_standard_date_field." >= '".$accountall_migration_date."'";
			}
			$_wheres[] = "exp.".$_standard_date_field." < '".$_standard_edate."'";
		}

		$_orderby = " GROUP BY exp.export_code HAVING sum(exp_item.reserve_ea) > 0 ORDER BY export_seq
		";

		$sql = $_query. " WHERE " .implode(" AND ",$_wheres). $_orderby;
		//echo $sql."\r\n";		

		$loop = array();

		$query = $this->db->query($sql);
		foreach($query->result_array() as $data){

			// 구매확정 중 반품 신청이 있는지 확인
			$params_check_buyconfirm = array();
			$params_check_buyconfirm['order_seq']		= $data['order_seq'];
			$params_check_buyconfirm['export_code']		= $data['export_code'];
			
			$this->load->model('buyconfirmmodel');
			$check_buyconfirm = $this->buyconfirmmodel->check_ing_return_for_buyconfirm($params_check_buyconfirm);
			if(!$check_buyconfirm){
				continue;
			}

			$loop[] = $data;

		}

		return array($_standard_edate,$loop);

	}

	//자동구매확정
	public function batch_buy_confirm()
	{
		
		// 구매확정 및 정산 처리일자 설정
		$_standard_acc_date			= time();
		//$_standard_acc_date			= mktime(1,0,0,5,31,2019);			//mktime(20,0,0,1,31,2019)
		$confirm_date				= date("Y-m-d H:i:s",$_standard_acc_date);		//구매확정일

		echo "\n\rbatch_buy_confirm standard_acc_date : ".$_standard_acc_date."\n\r";
		echo "batch_buy_confirm confirm_date : ".$confirm_date."\n\r";


		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
            $cfg_reserve = ($this->reserves)?$this->reserves:config_load('reserve');
            $cfg_order = config_load('order');
            $this->load->model('exportmodel');
            $this->load->model('ordermodel');
            $this->load->model('returnmodel');
			$this->load->model('buyconfirmmodel');

            ##발송시간 제한(예약)
            $this->config_sms_rest	= config_load('sms_restriction');	//SMS 발송시간제한
            $config_time_s		= $this->config_sms_rest['config_time_s'];
            $config_time_e	= $this->config_sms_rest['config_time_e'];
            $reserve_time		= $this->config_sms_rest['reserve_time'];
            $sms_use_chk		= $this->config_sms_rest['delivery'];//결제완료
            if($sms_use_chk == "checked"){

                //발송제한 시작 시간이 더 크면, 발송제한 종료시간은 익일로 계산.
                $rest_stime	= date("Y-m-d ".$config_time_s.":00:00",mktime());
                if($config_time_s > $config_time_e){
                    $rest_etime	= date("Y-m-d ".$config_time_e.":59:59",mktime()+(60*60*24));
                }else{
                    $rest_etime	= date("Y-m-d ".$config_time_e.":59:59",mktime());
                }
                //SMS발송시각이 발송제한 시간에 해당하면 지정된 예약시간에 발송
                if($rest_stime <= date("Y-m-d H:i:s",mktime()) && $rest_etime >= date("Y-m-d H:i:s",mktime())){
                    $rest_etime_tmp = date("Y-m-d 08:00:00",strtotime($rest_stime)+(60*60*24));	//익일 08시
                    $rest_etime_tmp = strtotime($rest_etime_tmp) + (60*$reserve_time); //익일 08시+예약time
                    $this->sms_reserve = date("Y-m-d H:i:s",$rest_etime_tmp);
                }
            }

            if( !$this->sms_reserve) {
                ### 새벽에 문자 발송 방지
                $now_time = date("H");
                if( (int) $now_time >= 23){
                    $this->sms_reserve = date('Y-m-d', strtotime('+1 days'))." 09:00:00";
                }else if( (int)$now_time >= 0 && $now_time < 9 ){
                    $this->sms_reserve = date("Y-m-d")." 09:00:00";
                }else{
                    $this->sms_reserve = 0;
                }
            }

            if( $cfg_order['buy_confirm_use'] && $cfg_order['save_term'] ){

				$AccOrderList = array();

				# 구매확정 대상 출고건 가져오기 : 반품처리중인 건이 1개라도 있다면 구매확정 대상에서 조회. 반품완료 처리 시 또는 반품완료 후 구매확정 처리
				list($_standard_edate,$loop) = $this->_get_buy_confirm_target($cfg_order,'',$_standard_acc_date);

				foreach($loop as $data){

					//지정된 구매확정일이 있는데 배송완료일보다 이후 날짜이면..
					if($confirm_date && $data['complete_date'] && $data['complete_date'] > $confirm_date){
						echo $data['export_code'] ." :: 구매확정일(".$confirm_date.")이 배송완료일(".$data['complete_date'].") 보다 이전 입니다.\n\r";
						continue;
					}

                    $export_code		= $data['export_code'];
                    $tot_confirm_ea		= 0;

                    $data_export		= $data;
                    $data_export_item	= $this->exportmodel->get_export_item($export_code);

                    if ($data_export_item[0]['goods_kind'] == 'coupon') continue;//티켓상품 구매확정 제외

                    $export_items		= array();

                    foreach($data_export_item as $k => $item)
                    {
                        $tmp = array();
                        $tmp['opt_type']				= $item['opt_type'];
                        $tmp['reserve_ea']				= $item['reserve_ea'];
                        $tmp['reserve_buyconfirm_ea']	= $item['reserve_buyconfirm_ea'];
                        $tmp['reserve_destroy_ea']		= $item['reserve_destroy_ea'];
                        $tmp['option_seq']				= $item['option_seq'];
                        $tmp['export_item_seq']			= $item['export_item_seq'];
                        $tot_confirm_ea					+= $item['reserve_ea'];

                        $export_items[] = $tmp;
                    }

                    $mode = "buyconfirm";

                    ## 구매확정 기간이 지난 출고건(출고일(고정) 기준)
                    if($data_export['complete_date'] < $_standard_edate && $tot_confirm_ea > 0)
                    {

                        $tot_reserve	= 0;
                        $tot_point		= 0;
                        $tot_confirm_ea	= 0;
                        $chg_reserve	= array();

                        if($cfg_order['save_type'] == 'give'){

                            $data_order = $this->ordermodel->get_order($data_export['order_seq']);
                            foreach($export_items as $k => $item)
                            {
                                // 마일리지 지급예정수량 2015-03-31 pjm
                                $confirm_ea		= $item['reserve_ea'];
                                $tot_confirm_ea += $item['reserve_ea'];

                                if($confirm_ea){

                                    $reserve = 0;
                                    if($item['opt_type'] == 'opt') $reserve = $this->ordermodel->get_option_reserve($item['option_seq']);
                                    else $reserve = $this->ordermodel->get_suboption_reserve($item['option_seq']);
                                    $tot_reserve += $reserve * $confirm_ea;

                                    $point = 0;
                                    if($item['opt_type'] == 'opt') $point = $this->ordermodel->get_option_reserve($item['option_seq'],'point');
                                    else $point = $this->ordermodel->get_suboption_reserve($item['option_seq'],'point');
                                    $tot_point += $point * $confirm_ea;


                                    #지급예정수량 = 지급예정수량 - (반품수량 + 소멸수량)
                                    #지급완료수량 = 지급예정수량
                                    $tmp = array();
                                    $tmp['export_item_seq']			= $item['export_item_seq'];
                                    $tmp['reserve_ea']				= 0;
                                    $tmp['reserve_buyconfirm_ea']	= $item['reserve_ea']+$item['reserve_buyconfirm_ea'];
                                    $chg_reserve[]					= $tmp;
                                }
                            }

                            if( $data_order['member_seq'] && $tot_confirm_ea > 0){
                                $this->load->model('membermodel');
                                if($tot_reserve){
                                    $params_reserve['gb']           = "plus";
                                    $params_reserve['emoney'] 	= $tot_reserve;
                                    $params_reserve['memo'] 	= "[".$export_code."] 구매확정";
                                    $params_reserve['memo_lang'] 	= $this->membermodel->make_json_for_getAlert("mp238",$export_code);   // [%s] 구매확정
                                    $params_reserve['ordno']	= $data_order['order_seq'];
                                    $params_reserve['type'] 	= "order";
                                    $params_reserve['limit_date'] 	= get_emoney_limitdate('order');
                                    $this->membermodel -> emoney_insert($params_reserve, $data_order['member_seq']);
                                }

                                if($tot_point){
                                    $params_point['gb']		= "plus";
                                    $params_point['point']          = $tot_point;
                                    $params_point['memo']           = "[".$export_code."] 구매확정";
                                    $params_point['memo_lang'] 	= $this->membermodel->make_json_for_getAlert("mp238",$export_code);   // [%s] 구매확정
                                    $params_point['ordno']          = $data_order['order_seq'];
                                    $params_point['type']           = "order";
                                    $params_point['limit_date'] 	= get_point_limitdate('order');
                                    $this->membermodel->point_insert($params_point, $data_order['member_seq']);
                                }

                                $query = "update fm_goods_export set reserve_save = 'save' where export_code = ?";
                                $this->db->query($query,array($export_code));

                            }

                        ## 지급예정 수량 소멸 처리
                        }elseif($cfg_order['save_type'] == 'exist'){

                            $mode = "destroy";
                            foreach($data_export_item as $k => $item)
                            {
                                $tot_confirm_ea += $item['reserve_ea'];
                                if($item['reserve_ea']){
                                    #지급예정수량 : 0, 소멸수량 : 지급예정수량
                                    $tmp = array();
                                    $tmp['export_item_seq']			= $item['export_item_seq'];
                                    $tmp['reserve_ea']				= 0;
                                    $tmp['reserve_destroy_ea']		= $item['reserve_ea']+$item['reserve_destroy_ea'];
                                    $chg_reserve[]					= $tmp;
                                }
                            }
                        }
                    }
                    ## 출고아이템에 마일리지 지급예정수량, 지급완료(또는 소멸) 수량 업데이트 2015-03-31 pjm
                    if($tot_confirm_ea) $this->exportmodel->exec_export_reserve_ea($chg_reserve,$mode);

                    if($mode == "buyconfirm") $mode = "pay";

					if($data_export['buy_confirm'] == "none"){

                    $data_buy_confirm['order_seq']		= $data_export['order_seq'];
                    $data_buy_confirm['export_seq']		= $data_export['export_seq'];
                    $data_buy_confirm['doer']			= '자동';
                    $data_buy_confirm['ea']				= $tot_confirm_ea;
                    $data_buy_confirm['emoney_status']	= $mode;
                    $data_buy_confirm['actor_id']		= 'system';
						$data_buy_confirm['regdate']		= ($confirm_date)? $confirm_date:date("Y-m-d H:i:s",mktime());

						$this->buyconfirmmodel -> buy_confirm('system',$export_code,$confirm_date);
                    $this->buyconfirmmodel -> log_buy_confirm($data_buy_confirm);

                    // 배송완료 처리
                    if( $data_export['status'] < 75 ){
                        $this->exportmodel->exec_complete_delivery($export_code,'system');
                    }

                    // 주문로그
                    $order_log_title	= '구매확정('.$export_code.':'.$tot_confirm_ea.")";
		                $this->ordermodel->set_log($data_export['order_seq'],'buyconfirm','시스템',$order_log_title,'','','','',$confirm_date);

					}

					//지정된 구매확정일자가 있을 때
					if($confirm_date){
						$_update_field = array();
						if(!$data_export['shipping_date'] || $data_export['shipping_date'] == "0000-00-00"){
							$_update_field[] = "shipping_date='".$confirm_date."'";
						}
						$_update_field[] = "confirm_date='".$confirm_date."'";
						$this->db->query("UPDATE fm_goods_export set ".implode(",",$_update_field)." where export_code='".$export_code."'");
					}

					//정산처리대상 배열화
					$AccOrderList[]		= array("order_seq"=>$data_export['order_seq'],"export_code"=>$export_code,"tot_confirm_ea"=>$tot_confirm_ea);
                }

                if(count($this->exportSmsData) > 0){
                    commonSendSMS($this->exportSmsData);
                }

			/**
			* 4-1 임시매출데이타를 이용한 통합정산테이블
			* 정산개선 - 통합정산데이타 생성
			* @ 
			**/

				if(!$this->accountall)			$this->load->helper('accountall');
				if(!$this->accountallmodel)		$this->load->model('accountallmodel');
				if(!$this->providermodel)		$this->load->model('providermodel');

				/* 정산처리일자 설정 */
				$this->accountallmodel->iOnTimeStamp = $_standard_acc_date;	

				foreach($AccOrderList as $AccOrder){
				//정산대상 수량업데이트
				$this->accountallmodel->update_calculate_sales_ac_ea($AccOrder['order_seq'],$AccOrder['export_code']);
				//정산확정 처리
				$this->accountallmodel->insert_calculate_sales_buyconfirm($AccOrder['order_seq'], $AccOrder['export_code'], $AccOrder['tot_confirm_ea']);
				//debug_var($this->db->queries);
				//debug_var($this->db->query_times);
			}
			/**
			* 4-1 임시매출데이타를 이용한 통합정산테이블
			* 정산개선 - 통합정산데이타 생성
			* @
			**/

            }

		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}	


	//자동구매확정
	public function batch_buy_confirm_old()
	{		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$cfg_reserve = ($this->reserves) ? $this->reserves:config_load('reserve');
            $cfg_order = config_load('order');
            $this->load->model('exportmodel');
            $this->load->model('ordermodel');
            $this->load->model('returnmodel');

            ##발송시간 제한(예약)
            $this->config_sms_rest	= config_load('sms_restriction');	//SMS 발송시간제한
            $config_time_s		= $this->config_sms_rest['config_time_s'];
            $config_time_e	= $this->config_sms_rest['config_time_e'];
            $reserve_time		= $this->config_sms_rest['reserve_time'];
            $sms_use_chk		= $this->config_sms_rest['delivery'];//결제완료
            if($sms_use_chk == "checked"){

                //발송제한 시작 시간이 더 크면, 발송제한 종료시간은 익일로 계산.
                $rest_stime	= date("Y-m-d ".$config_time_s.":00:00",mktime());
                if($config_time_s > $config_time_e){
                    $rest_etime	= date("Y-m-d ".$config_time_e.":59:59",mktime()+(60*60*24));
                }else{
                    $rest_etime	= date("Y-m-d ".$config_time_e.":59:59",mktime());
            }
                //SMS발송시각이 발송제한 시간에 해당하면 지정된 예약시간에 발송
                if($rest_stime <= date("Y-m-d H:i:s",mktime()) && $rest_etime >= date("Y-m-d H:i:s",mktime())){
                    $rest_etime_tmp = date("Y-m-d 08:00:00",strtotime($rest_stime)+(60*60*24));	//익일 08시
                    $rest_etime_tmp = strtotime($rest_etime_tmp) + (60*$reserve_time); //익일 08시+예약time
                    $this->sms_reserve = date("Y-m-d H:i:s",$rest_etime_tmp);
                }
            }

            if( !$this->sms_reserve) {
                ### 새벽에 문자 발송 방지
                $now_time = date("H");
                if( (int) $now_time >= 23){
                    $this->sms_reserve = date('Y-m-d', strtotime('+1 days'))." 09:00:00";
                }else if( (int)$now_time >= 0 && $now_time < 9 ){
                    $this->sms_reserve = date("Y-m-d")." 09:00:00";
                }else{
                    $this->sms_reserve = 0;
                }
            }

            if( $cfg_order['buy_confirm_use'] && $cfg_order['save_term'] ){

                $edate = date('Y-m-d',strtotime("-".$cfg_order['save_term']." day"));

                //$query ="select * from fm_goods_export where buy_confirm='none' and complete_date < '".$edate."' and `status` >= '55' order by export_seq";
                $query ="select
                            ext.*
                        from
                            fm_goods_export as ext
                            left join fm_goods_export_item as ext_item on ext_item.export_code=ext.export_code
                        where
                            ext.complete_date < '".$edate."'
                            and ext.status >= '55'
                            and ext.npay_order_id is null
                        group by ext.export_code
                        having sum(ext_item.reserve_ea) > 0
                        order by export_seq";
                $res = mysqli_query($this->db->conn_id,$query);

                while($data = mysqli_fetch_assoc($res)){

                    $export_code		= $data['export_code'];
                    $tot_confirm_ea		= 0;

                    $data_export		= $this->exportmodel->get_export($export_code);
                $data_export_item = $this->exportmodel->get_export_item($export_code);

                    if ($data_export_item[0]['goods_kind'] == 'coupon') continue;//티켓상품 구매확정 제외

                    $export_items		= array();

                foreach($data_export_item as $k => $item)
                {
                        $tmp = array();
                        $tmp['opt_type']				= $item['opt_type'];
                        $tmp['reserve_ea']				= $item['reserve_ea'];
                        $tmp['reserve_buyconfirm_ea']	= $item['reserve_buyconfirm_ea'];
                        $tmp['reserve_destroy_ea']		= $item['reserve_destroy_ea'];
                        $tmp['option_seq']				= $item['option_seq'];
                        $tmp['export_item_seq']			= $item['export_item_seq'];
                        $tot_confirm_ea					+= $item['reserve_ea'];

                        $export_items[] = $tmp;
                }

                    $mode = "buyconfirm";

                    ## 구매확정 기간이 지난 출고건(출고일(고정) 기준)
                    if($data_export['complete_date'] < $edate && $tot_confirm_ea > 0)
                    {

                        $tot_reserve	= 0;
                        $tot_point		= 0;
                        $tot_confirm_ea	= 0;
                        $chg_reserve	= array();

                        if($cfg_order['save_type'] == 'give'){

                            $data_order = $this->ordermodel->get_order($data_export['order_seq']);
                            foreach($export_items as $k => $item)
                            {
                                // 마일리지 지급예정수량 2015-03-31 pjm
                                $confirm_ea		= $item['reserve_ea'];
                                $tot_confirm_ea += $item['reserve_ea'];

                                if($confirm_ea){

                                    $reserve = 0;
                                    if($item['opt_type'] == 'opt') $reserve = $this->ordermodel->get_option_reserve($item['option_seq']);
                                    else $reserve = $this->ordermodel->get_suboption_reserve($item['option_seq']);
                                    $tot_reserve += $reserve * $confirm_ea;

                                    $point = 0;
                                    if($item['opt_type'] == 'opt') $point = $this->ordermodel->get_option_reserve($item['option_seq'],'point');
                                    else $point = $this->ordermodel->get_suboption_reserve($item['option_seq'],'point');
                                    $tot_point += $point * $confirm_ea;


                                    #지급예정수량 = 지급예정수량 - (반품수량 + 소멸수량)
                                    #지급완료수량 = 지급예정수량
                                    $tmp = array();
                                    $tmp['export_item_seq']			= $item['export_item_seq'];
                                    $tmp['reserve_ea']				= 0;
                                    $tmp['reserve_buyconfirm_ea']	= $item['reserve_ea']+$item['reserve_buyconfirm_ea'];
                                    $chg_reserve[]					= $tmp;
                        }
                    }

                            if( $data_order['member_seq'] && $tot_confirm_ea > 0){
                                $this->load->model('membermodel');
                                if($tot_reserve){
                                    $params_reserve['gb']           = "plus";
                                    $params_reserve['emoney'] 	= $tot_reserve;
                                    $params_reserve['memo'] 	= "[".$export_code."] 구매확정";
                                    $params_reserve['memo_lang'] 	= $this->membermodel->make_json_for_getAlert("mp238",$export_code);   // [%s] 구매확정
                                    $params_reserve['ordno']	= $data_order['order_seq'];
                                    $params_reserve['type'] 	= "order";
                                    $params_reserve['limit_date'] 	= get_emoney_limitdate('order');
                                    $this->membermodel -> emoney_insert($params_reserve, $data_order['member_seq']);
                    }

                                if($tot_point){
                                    $params_point['gb']		= "plus";
                                    $params_point['point']          = $tot_point;
                                    $params_point['memo']           = "[".$export_code."] 구매확정";
                                    $params_point['memo_lang'] 	= $this->membermodel->make_json_for_getAlert("mp238",$export_code);   // [%s] 구매확정
                                    $params_point['ordno']          = $data_order['order_seq'];
                                    $params_point['type']           = "order";
                                    $params_point['limit_date'] 	= get_point_limitdate('order');
                                    $this->membermodel->point_insert($params_point, $data_order['member_seq']);
                            }

                                $query = "update fm_goods_export set reserve_save = 'save' where export_code = ?";
                                $this->db->query($query,array($export_code));

                        }

                        ## 지급예정 수량 소멸 처리
                        }elseif($cfg_order['save_type'] == 'exist'){

                            $mode = "destroy";
                            foreach($data_export_item as $k => $item)
                            {
                                $tot_confirm_ea += $item['reserve_ea'];
                                if($item['reserve_ea']){
                                    #지급예정수량 : 0, 소멸수량 : 지급예정수량
                                    $tmp = array();
                                    $tmp['export_item_seq']			= $item['export_item_seq'];
                                    $tmp['reserve_ea']				= 0;
                                    $tmp['reserve_destroy_ea']		= $item['reserve_ea']+$item['reserve_destroy_ea'];
                                    $chg_reserve[]					= $tmp;
                            }
                        }
                    }
                }
                    ## 출고아이템에 마일리지 지급예정수량, 지급완료(또는 소멸) 수량 업데이트 2015-03-31 pjm
                    if($tot_confirm_ea) $this->exportmodel->exec_export_reserve_ea($chg_reserve,$mode);

                    if($mode == "buyconfirm") $mode = "pay";

                    $data_buy_confirm['order_seq']		= $data_export['order_seq'];
                    $data_buy_confirm['export_seq']		= $data_export['export_seq'];
                    $data_buy_confirm['doer']			= '자동';
                    $data_buy_confirm['ea']				= $tot_confirm_ea;
                    $data_buy_confirm['emoney_status']	= $mode;
                    $data_buy_confirm['actor_id']		= 'system';
                    $this->load->model('buyconfirmmodel');
                    $this->buyconfirmmodel -> buy_confirm('system',$export_code);
                    $this->buyconfirmmodel -> log_buy_confirm($data_buy_confirm);

                    // 배송완료 처리
                    if( $data_export['status'] < 75 ){
                        $this->exportmodel->exec_complete_delivery($export_code,'system');
                    }

                    // 주문로그
                    $order_log_title	= '구매확정('.$export_code.':'.$tot_confirm_ea.")";
                    $this->ordermodel->set_log($data_export['order_seq'],'buyconfirm','시스템',$order_log_title);

					$AccOrderList[]		= array("order_seq"=>$data_export['order_seq'],"export_code"=>$export_code,"tot_confirm_ea"=>$tot_confirm_ea);
                }

                if(count($this->exportSmsData) > 0){
                    commonSendSMS($this->exportSmsData);
                }
            }

			/**
			* 4-1 임시매출데이타를 이용한 통합정산테이블
			* 정산개선 - 통합정산데이타 생성
			* @ 
			**/
			foreach($AccOrderList as $AccOrder){
				if(!$this->accountall)			$this->load->helper('accountall');
				if(!$this->accountallmodel)		$this->load->model('accountallmodel');
				if(!$this->providermodel)		$this->load->model('providermodel');
				//정산대상 수량업데이트
				$this->accountallmodel->update_calculate_sales_ac_ea($AccOrder['order_seq'],$AccOrder['export_code']);
				//정산확정 처리
				$this->accountallmodel->insert_calculate_sales_buyconfirm($AccOrder['order_seq'], $AccOrder['export_code'], $AccOrder['tot_confirm_ea']);
				//debug_var($this->db->queries);
				//debug_var($this->db->query_times);
			}
			/**
			* 4-1 임시매출데이타를 이용한 통합정산테이블
			* 정산개선 - 통합정산데이타 생성
			* @
			**/

		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}	
	//자동티켓상품>배송완료 @ 미사용티켓상품환불불가완료와 미사용
	public function batch_social_goods_confirm()
	{		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$cfg_reserve = ($this->reserves) ? $this->reserves:config_load('reserve');
            $cfg_order = config_load('order');
            $this->load->model('exportmodel');
            $this->load->model('ordermodel');
            $this->load->model('returnmodel');
            $this->load->model('socialcpconfirmmodel');

            $edate = date('Ymd');

            $qry = "select exp.*
                 from
                  fm_goods_export as exp
                  LEFT JOIN fm_goods_export_item as exp_item ON exp.export_code = exp_item.export_code
                  LEFT JOIN fm_order_item as item ON exp_item.item_seq = item.item_seq
                 where exp.socialcp_refund_day < '$edate'  and
                  exp.status >= '50' and item.goods_kind = 'coupon'  and
                  (exp.socialcp_status is null or exp.socialcp_status ='1' or exp.socialcp_status ='2')
                group by exp.export_code
                order by exp.status,exp.export_code";
                //$qry .= " limit 0,10";//(exp.socialcp_refund_day is null or
            $query = $this->db->query($qry);
            if( $query->num_rows() == 0 ) {
                throw new Exception('batch_social_goods_confirm  no data');
            }
            foreach ($query->result_array() as $data_export){$num++;
                $export_code= $data_export['export_code'];
                $data_export_item = $this->exportmodel->get_export_item($export_code);
                if ($data_export_item[0]['goods_kind'] != 'coupon')continue;//티켓상품만

                //$data_export		= $this->exportmodel->get_export($export_code);
                $data_order		= $this->ordermodel->get_order($data_export['order_seq']);
                $data_returns_item	= $this->returnmodel->get_return_item_return_code($data_export_item[0]['item_seq'], $data_export_item[0]['option_seq'], $export_code);

                $tot_coupon_value = 0;
                $tot_coupon_remain_value = 0;
                foreach($data_export_item as $k => $item)
                {
                    $tot_coupon_value += $item['coupon_value'];
                    $tot_coupon_remain_value += $item['coupon_remain_value'];
                }

                $socialcp_remain_status = ($tot_coupon_remain_value == $tot_coupon_value )?true:false;//모두미사용 true, 일부사용 false
                $pointsave = false;
                if( !$data_export['socialcp_refund_day'] || $data_export['socialcp_refund_day'] < 1 ) {//환불기간이 없다면 계산
                    $select_refund_day = "select if( ord_itm.socialcp_use_return ='1', DATE_ADD(ord_itm_opt.social_end_date, INTERVAL ord_itm.socialcp_use_emoney_day DAY), ord_itm_opt.social_end_date) FROM fm_order_item_option ord_itm_opt left join fm_order_item ord_itm ON ord_itm_opt.item_seq = ord_itm.item_seq left join fm_goods_export_item exp_itm ON ord_itm_opt.item_seq = exp_itm.item_seq WHERE exp_itm.export_code='".$export_code."' and exp_itm.coupon_serial is not null GROUP BY exp_itm.export_code";
                    $res_refund_day  = mysqli_query($this->db->conn_id,$select_refund_day);
                    $row_refund_day = mysqli_fetch_row($res_refund_day);
                    $data_export['socialcp_refund_day'] = date("Ymd",strtotime($row_refund_day[0]));

                    $refunddayupquery = "update fm_goods_export set socialcp_refund_day = '".$data_export['socialcp_refund_day']."' where export_code = ?";
                    $this->db->query($refunddayupquery,array($export_code));

                    if( $data_export['socialcp_refund_day'] < $edate ) {//가치종료
                        if( $data_returns_item['ea'] > 0 ) {//취소(환불)
                            $data_returns	= $this->returnmodel->get_return($data_returns_item['return_code']);
                            if(  $data_export['socialcp_refund_day'] >= $data_returns['regist_date']  ) {//유효기간 시작 전 취소시
                                $socialcp_status = ($socialcp_remain_status)?'6':'7';//모두미사용 6, 일부사용 7
                            }else{//유효기간 종료 후 취소시
                                $socialcp_status = ($socialcp_remain_status)?'8':'9';//모두미사용 8, 일부사용 9
                            }
                            $statustype = 'cancel';//환불
                        }else{
                            $socialcp_status = ($socialcp_remain_status)?'4':'5';//모두미사용 4, 일부사용 5
                            $statustype = 'expired';//낙장
                            $pointsave = true;
                        }
                    }else{//유효기간시작 전
                        if($data_returns_item['ea'] > 0) {//취소(환불)
                            $data_returns	= $this->returnmodel->get_return($data_returns_item['return_code']);
                            if(  $data_export['socialcp_refund_day'] >= $data_returns['regist_date']  ) {//유효기간 시작 전 취소시
                                $socialcp_status = ($socialcp_remain_status)?'6':'7';//모두미사용 6, 일부사용 7
                            }else{//유효기간 종료 후 취소시
                                $socialcp_status = ($socialcp_remain_status)?'8':'9';//모두미사용 8, 일부사용 9
                            }
                            $statustype = 'cancel';//환불
                        }else{
                            $socialcp_status = ($socialcp_remain_status)?'1':'2';//모두미사용 1, 일부사용 2
                            $statustype = 'migration';//마이그레이션
                        }
                    }
                }else{//환불기간이 있다면
                    if(strstr($data_export['socialcp_refund_day'],'-')) {
                        $data_export['socialcp_refund_day'] = date("Ymd",strtotime($data_export['socialcp_refund_day']));
                        $refunddayupquery = "update fm_goods_export set socialcp_refund_day = '".$data_export['socialcp_refund_day']."' where export_code = ?";
                        $this->db->query($refunddayupquery,array($export_code));
                    }
                    if( $data_export['socialcp_refund_day'] < $edate ) {//가치종료
                        if( $data_returns_item['ea'] > 0 ) {//취소(환불)
                            $data_returns	= $this->returnmodel->get_return($data_returns_item['return_code']);
                            if(  $data_export['socialcp_refund_day'] >= $data_returns['regist_date']  ) {//유효기간 시작 전 취소시
                                $socialcp_status = ($socialcp_remain_status)?'6':'7';//모두미사용 6, 일부사용 7
                            }else{//유효기간 종료 후 취소시
                                $socialcp_status = ($socialcp_remain_status)?'8':'9';//모두미사용 8, 일부사용 9
                            }
                            $statustype = 'cancel';//환불
                        }else{
                            $socialcp_status = ($socialcp_remain_status)?'4':'5';//모두미사용 4, 일부사용 5
                            $statustype = 'expired';//낙장
                            $pointsave = true;
                        }
                    }else{//유효기간시작 전
                        if($data_returns_item['ea'] > 0) {//취소(환불)
                            $data_returns	= $this->returnmodel->get_return($data_returns_item['return_code']);
                            if(  $data_export['socialcp_refund_day'] >= $data_returns['regist_date']  ) {//유효기간 시작 전 취소시
                                $socialcp_status = ($socialcp_remain_status)?'6':'7';//모두미사용 6, 일부사용 7
                            }else{//유효기간 종료 후 취소시
                                $socialcp_status = ($socialcp_remain_status)?'8':'9';//모두미사용 8, 일부사용 9
                            }
                            $statustype = 'cancel';//환불
                        }else{
                            $socialcp_status = ($socialcp_remain_status)?'4':'5';//모두미사용 4, 일부사용 5
                            $statustype = 'expired';//낙장
                            $pointsave = true;
                        }
                    }
                }

                if( $data_export['socialcp_status'] != $socialcp_status ) {
                    $data_socialcp_confirm['order_seq']		= $data_export['order_seq'];
                    $data_socialcp_confirm['export_seq']		= $data_export['export_seq'];
                    $data_socialcp_confirm['doer']				= '자동';
                    $this->socialcpconfirmmodel -> socialcp_confirm('system',$socialcp_status,$export_code);
                    $this->socialcpconfirmmodel -> log_socialcp_confirm($data_socialcp_confirm);
                    if( $statustype != 'migration' && $data_export['status'] != 75  ) $this->exportmodel->socialcp_exec_complete_delivery($export_code,$pointsave,'','system',$statustype);//미사용티켓상품

					/**
					* 2-1 임시매출데이타를 이용한 미정산데이타 또는 통합정산테이블 시작
					* 정산개선 - 통합정산데이타 생성
					* @ 
					**/
					if(!$this->accountall)			$this->load->helper('accountall');
					if(!$this->accountallmodel)		$this->load->model('accountallmodel');
					if(!$this->providermodel)		$this->load->model('providermodel');
					// 3차 환불 개선으로 티켓상품 사용 후 남은금액 함수 처리 추가 :: 2018-11- lkh
					$this->accountallmodel->update_calculate_sales_coupon_remain($data_export['order_seq']);
					//정산대상 수량업데이트
					$this->accountallmodel->update_calculate_sales_ac_ea($data_export['order_seq'],$export_code);
					//정산확정 처리
					$this->accountallmodel->insert_calculate_sales_buyconfirm($data_export['order_seq'], $export_code, $tot_coupon_value, true);
					//debug_var($this->db->queries);
					//debug_var($this->db->query_times);
					/**
					* 2-1 임시매출데이타를 이용한 미정산데이타 또는 통합정산테이블 끝
					* 정산개선 - 통합정산데이타 생성
					* @
					**/
                }
            }//endwhile
		} catch (Exception $e) {			
			if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ')');
	}
    
    //미입금 통보 확인
	public function deposit_request(){		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model("ordermodel");

            $sms		= config_load('sms');
            if($sms['deposit_user_yn'] == "Y"){
                $order_date = date("Y-m-d",strtotime("-".$sms['deposit_send_day']." day"));
                $sql = "select * from fm_order where step = '15' and payment in ('bank', 'virtual', 'escrow_virtual') and regist_date >= '".$order_date." 00:00:00' and regist_date <= '".$order_date." 23:59:59'";
                $query = $this->db->query($sql);
                $result = $query->result_array();

                $order_count=0;
                foreach($result as $order){
                    $orders	= $this->ordermodel->get_order($order['order_seq']);

                    if( $orders['order_cellphone'] ){
                        $params['shopName']				= $this->config_basic['shopName'];
                        $params['ordno']				= $order['order_seq'];
                        $params['member_seq']			= $orders['member_seq'];
                        $params['user_name']			= $orders['order_user_name'];
                        $params['bank_account']			= ($orders['payment'] == 'bank')? $orders['bank_account'] : $orders['virtual_account'];
                        $arr_params[$order_count]		= $params;
                        $order_no[$order_count]			= $order['order_seq'];
                        $order_cellphones[$order_count] = $orders['order_cellphone'];
                        $order_count					=$order_count+1;
                    }

                }

                //결제 확인 SMS 데이터 생성
                if(count($order_cellphones) > 0){
                    $commonSmsData['deposit']['phone'] = $order_cellphones;
                    $commonSmsData['deposit']['params'] = $arr_params;
                    $commonSmsData['deposit']['order_no'] = $order_no;
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
    ### 굿스플로 배송정보 처리 2015-07-13
	public function receiveTrackingResults(){		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
            ob_start();
			$this->load->model('goodsflowmodel');
            $this->goodsflowmodel->get_receiveTrackingResults();
            $out = ob_get_contents();
            ob_end_clean();
		} catch (Exception $e) {			
			if( $aFunc )    $this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}
        if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
        if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $out);
	}

	/**
	 * 선물하기 주문 배송지 미등록 시 SMS 발송
	 */
	public function present_receipt_request() {
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);

		try{
			// 해당하는 주문 조회
			$this->load->model('ordermodel');
			$where['step'] = '25';
			$where['deposit_date <='] = date("Y-m-d 23:59:59", strtotime("-4 Day"));
			$where['deposit_date >='] = date("Y-m-d 23:59:59", strtotime("-5 Day"));
			$where['recipient_zipcode'] = null;
			$where['label'] = 'present';
			$order_list = $this->ordermodel->get_order_basic($where);
			
			$commonSmsData = array();
			foreach($order_list as $row) {
				$commonSmsData['present_receive']['phone'][] = $row['recipient_cellphone'];
				$commonSmsData['present_receive']['params'][] = $row;
				$commonSmsData['present_receive']['order_no'][] = $row['order_seq'];
			}

			if (count($commonSmsData) > 0) {
				commonSendSMS($commonSmsData);
			}

		} catch (Exception $e) {
			if( $aFunc )    $this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}

		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $out);
	}

	/**
	 * 선물하기 주문 배송지 미등록 시 환불
	 */
	public function present_cancel() {
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);

		try{
			$this->load->library('orderlibrary');
			$this->load->model('ordermodel');
			$this->load->model('refundmodel');
			$this->load->model('couponmodel');
			$this->load->model('promotionmodel');
			$this->load->library('orderlibrary');
			$this->load->library('refundlibrary');
			
			// 5일 경과되었으나 주소 미등록 시 
			$where['step'] = '25';
			$where['deposit_date <='] = date("Y-m-d 23:59:59", strtotime("-5 Day"));
			$where['recipient_zipcode'] = null;
			$where['label'] = 'present';
			$order_list = $this->ordermodel->get_order_basic($where);
			
			// 1건 씩 환불완료 처리 및 SMS 발송
			foreach($order_list as $row) {
				writeCsLog($row['order_seq'],"present_cancel","order");
				$result = $this->orderlibrary->present_cancel($row);
				writeCsLog($result,"present_cancel","order");
			}

		} catch (Exception $e) {
			if( $aFunc )    $this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $e->getMessage());
		}

		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
		if( $aFunc )		$this->batchlib->_cronFileLog($aFunc['sLogFile'], $aFunc['sMsg'] . '(' . $aFunc['sFunctionName'] . ') - ' . $out);
	}	
}
