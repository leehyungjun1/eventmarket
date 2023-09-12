<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/batch/dailyMember".EXT);
class exportMsg extends dailyMember {
	public function __construct() {
		parent::__construct();
		$this->iOnTimeStamp		= time();
	}
	// 결제확인 메일 및 sms 전송
	public function cron_deposit_mail_sms()
	{
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);

		try {
			$this->load->model('ordermodel');
			$this->load->library('orderlibrary');
			/**
			 * 결제확인, 1시간 이내 주문, sms 미발송, 외부주문(오픈마켓,네이버페이,톡구매) 아닐때
			 */
			$params = array(
				'step' => '25',
				'deposit_date >=' => date("Y-m-d H:i:s", strtotime("-1 hours")),
				// 입금확인(뱅크다) 프로세스와 동시성 문제로 sms 2번 발송되기 때문에 (현재시간 - 5분) 쿼리를 추가함
				'deposit_date <' => date("Y-m-d H:i:s", strtotime("-5 minutes")),
				'sms_25_YN' => 'N',
				'linkage_id' => null,
				'npay_order_id' => null,
				'talkbuy_order_id' => null,
			);
			$order_list = $this->ordermodel->get_order_basic($params);
			foreach($order_list as $order) {
				$this->orderlibrary->send_step25_mail_sms($order);
			}
		} catch (Exception $e) {
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
	}

	// 실물상품 출고준비 시 성능개선을 위해 오픈마켓배송정보전송,롯데택배송정보전송 분리
	public function cron_export_ready()
	{
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model('exportmodel');
			$this->load->model('ordermodel');
			$this->load->model('batchmodel');
			$this->load->model('invoiceapimodel');
			$this->load->model('openmarketmodel');
			$this->load->helper('order');
			
			$aParams['action_code']	= 'export_ready';
			$aParams['status']		= 'none';
			$aParams['start_date']	= date('Y-m-d 00:00:00', strtotime("-3 day", $this->iOnTimeStamp)); // 3일 전까지만 메시지 처리 행함
			$aParams['end_date']	= date('Y-m-d 23:59:59', $this->iOnTimeStamp);
			$aParams['limit']		= 3000;
			$sQuery	= $this->batchmodel->get_data_sql_params($aParams);
			$rQuery	= mysqli_query($this->db->conn_id, $sQuery);
			$this->batchmodel->update_status_ing_sql($sQuery);
			while($data = mysqli_fetch_array($rQuery)){
				$arr_params = unserialize($data['params']);
				$export_code = $arr_params['export_code'];
				$order_seq = $arr_params['order_seq'];
				$query_export = $this->exportmodel->get_exports(array($export_code));
				$data_export = $query_export->row_array();
				if($data_export['export_code']){
					// 출고자동화 전송
					$result_invoice = $this->invoiceapimodel->new_export(array($export_code));
					// 오픈마켓 자동화
					$this->openmarketmodel->request_send_export($export_code);
				}				
				$this->batchmodel->del($data['batch_seq']);
			}
		} catch (Exception $e) {
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
	}
	// 실물상품 출고완료 시 성능개선을 위해 SMS,EMAIL,오픈마켓배송정보전송,롯데택배송정보전송 분리
	public function cron_export_complete()
	{
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model('exportmodel');
			$this->load->model('ordermodel');
			$this->load->model('batchmodel');
			$this->load->model('openmarketmodel');
			$this->load->model('invoiceapimodel');
			$this->load->helper('order');
			$this->load->helper('shipping');
			$order_count = 0;
			$recipient_count = 0;
			unset($params);
			
			$aParams['action_code']	= 'export_complete';
			$aParams['status']		= 'none';
			$aParams['start_date']	= date('Y-m-d 00:00:00', strtotime("-3 day", $this->iOnTimeStamp)); // 3일 전까지만 메시지 처리 행함
			$aParams['end_date']	= date('Y-m-d 23:59:59', $this->iOnTimeStamp);
			$aParams['limit']		= 3000;
			$sQuery	= $this->batchmodel->get_data_sql_params($aParams);
			$rQuery	= mysqli_query($this->db->conn_id, $sQuery);
			$this->batchmodel->update_status_ing_sql($sQuery);
			while($data = mysqli_fetch_array($rQuery)){
				$arr_params = unserialize($data['params']);
				$order_count			= 0;
				$recipient_count		= 0;
				$commonSmsData			= '';
				$order_cellphones		= '';
				$order_no				= '';
				$recipient_order_no		= '';
				$recipient_cellphones	= '';
				$recipient_arr_params	= '';
				$export_code = $arr_params['export_code'];
				$order_seq = $arr_params['order_seq'];
				if( !$arr_orders[$order_seq] ){
					$orders	= $this->ordermodel->get_order($order_seq);
					$arr_orders[$order_seq] = $orders;
				}else{
					$orders = $arr_orders[$order_seq];
				}
				$query_export = $this->exportmodel->get_exports(array($export_code));
				$data_export = $query_export->row_array();
				if($data_export['export_code']){
					// 출고자동화 전송
					$result_invoice = $this->invoiceapimodel->new_export(array($export_code));
					$exports = $this->exportmodel->get_export($export_code);
					// 네이버페이, 톡체크아웃 SMS&메일 미발송
					if($exports['npay_order_id'] || $exports['talkbuy_order_id']) {
						continue;
					}
					send_mail_step55($export_code,$exports,$orders);
					// 출고완료시 sms
					if( $orders['order_cellphone'] ){
						if(is_array($exports)){
							// 택배사 정보 가져오기 get_export에서 가공된 데이터 사용
							$params['delivery_number'] = $exports['mdelivery_number'];
							$params['delivery_company'] = $exports['mdelivery'];
						}
						$params['shopName']		= $this->config_basic['shopName'];
						$params['ordno']		= $order_seq;
						$params['export_code']	= $export_code;
						$params['member_seq']	= $orders['member_seq'];
						$params['user_name']	= $orders['order_user_name'];
						$params['recipient_user']		= $order['recipient_user_name'];
						$arr_params[$order_count]		= $params;
						$order_no[$order_count]			= $order_seq;
						$order_cellphones[$order_count]	= $orders['order_cellphone'];
						/**
						- 수동일괄처리시 관리자/입점사 1통 : {설정된 컨텐츠} 외 00건으로 개선
						- @2017-08-17
						**/
						$data_export_item = $this->exportmodel->get_export_item($export_code);
						foreach($data_export_item as $item){
							if($item['shipping_provider_seq']) $providerList[$item['shipping_provider_seq']]	= 1;
						}
						sendSMS_for_provider('released', $providerList, $params);
						if($this->send_for_provider['order_cellphone']) {
							$params['provider_mobile']	=  $this->send_for_provider['order_cellphone'];
						}
						if(count($order_cellphones) > 0){
							$commonSmsData['released']['phone'] = $order_cellphones;
							$commonSmsData['released']['params'] = $arr_params;
							$commonSmsData['released']['order_no'] = $order_no;
						}
						# 주문자와 받는분이 다를때 받는분에게도 문자 전송
						if( $orders['recipient_cellphone'] && (preg_replace('/[^0-9]/', '', $orders['order_cellphone']) !=  preg_replace('/[^0-9]/', '', $orders['recipient_cellphone']))) {
							$recipient_cellphones[$recipient_count]	= $orders['recipient_cellphone'];	//받는분
							$recipient_arr_params[$recipient_count] = $params;
							$recipient_order_no[$recipient_count] = $order_seq;
							$recipient_count = $recipient_count+1;

							$commonSmsData['released2']['phone'] = $recipient_cellphones;
							$commonSmsData['released2']['params'] = $recipient_arr_params;
							$commonSmsData['released2']['order_no'] = $recipient_order_no;
						}
						$order_count = $order_count + 1;
					}
				}
				if(count($commonSmsData) > 0){
					commonSendSMS($commonSmsData);
				}
				$this->batchmodel->del($data['batch_seq']);
			}
		} catch (Exception $e) {			
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
	}
	// 실물상품 출고준비 시 성능개선을 위해 오픈마켓배송정보전송,롯데택배송정보전송 분리
	public function cron_complete_ticket()
	{
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->model('exportmodel');
			$this->load->model('batchmodel');
			$this->load->helper('order');
			
			$aParams['action_code']	= 'complete_ticket';
			$aParams['status']		= 'none';
			$aParams['start_date']	= date('Y-m-d 00:00:00', strtotime("-3 day", $this->iOnTimeStamp)); // 3일 전까지만 메시지 처리 행함
			$aParams['end_date']	= date('Y-m-d 23:59:59', $this->iOnTimeStamp);
			$aParams['limit']		= 3000;
			$sQuery	= $this->batchmodel->get_data_sql_params($aParams);
			$rQuery	= mysqli_query($this->db->conn_id, $sQuery);
			$this->batchmodel->update_status_ing_sql($sQuery);
			while($data = mysqli_fetch_array($rQuery)){
				$arr_params = unserialize($data['params']);
				$result_export_code = $arr_params['result_export_code'];
				$sendType = $arr_params['sendType'];
				$email = $arr_params['email'];
				$sms = $arr_params['sms'];
				$this->exportmodel->coupon_export_send_for_option($result_export_code, $sendType, $email, $sms);
				$this->batchmodel->del($data['batch_seq']);				
			}
		} catch (Exception $e) {			
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
	}
	public function batch_send_email(){		
		list($aFunc, $aNextFunc)	= $this->batchlib->_getNextFunc(__FUNCTION__);
		try{
			$this->load->library('email');
			$start_date		= date('Y-m-d 00:00:00', strtotime('-1 day'));
			$sql = "select * from fm_email where regist_date >= ?";
			$query = $this->db->query($sql, array($start_date));
			$result = $query->result_array();

			foreach($result as $data){
				if($_GET['debug']){
					echo "<table border=1><tr>";
					echo "<td>".$data['subject']."</td>";
					echo "<td>".$data['from_name']."(".$data['from_email'].")"."</td>";
					echo "<td>".$data['to_email']."</td>";
					echo "</tr></table>";
				}else{
					$this->email->mailtype='html';
					$this->email->from($data['from_email'], $data['from_name']);
					$this->email->to($data['to_email']);
					$this->email->subject($data['subject']);
					$body = str_replace('\\','',http_src($data['contents']));
					$this->email->message($body);
					$this->email->send();
					$this->email->clear();
					$delSql = "delete from fm_email where email_seq = '".$data['email_seq']."'";
					$this->db->query($delSql);
				}
			}
		} catch (Exception $e) {
		}
		if( $aNextFunc )	$this->{$aNextFunc['sFunctionName']}();
	}
}