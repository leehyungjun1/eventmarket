<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class added_payment extends CI_Model
{
	public $cfg = array();

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('readurl');
		$this->load->library('email');
	}

	public function view($plaform, $pgCompany, $payment, $oid, $originalTid)
	{
		try{
			if (preg_match('/inicis/', $pgCompany)) {
				return $this->inicis($payment, $oid, $originalTid);
			}
			if (preg_match('/lg/', $pgCompany)) {
				return $this->lg($payment, $oid, $originalTid);
			}
			if (preg_match('/kcp/', $pgCompany)) {
				return $this->kcp($oid, $payment);
			}
			if (preg_match('/kakaopay/', $pgCompany)) {
				return $this->kakaopay($payment, $oid, $originalTid);
			}
			if (preg_match('/naverpayment/', $pgCompany)) {
				return $this->naverpayment($payment, $oid, $originalTid);
			}

			if (preg_match('/allat|kspay|kicc|payco/', $pgCompany)) { // 중복 체크 없음
				return array('success' => 'N');
			}
		} catch (Exception $e) {
			debug($e->getMessage());
		}
	}

	/*
	inicis or inicis mobile api
	*/
	protected function inicis($payment, $oid, $originalTid)
	{
		$aPgCfg = $this->cfg['inicis'];

		if ($aPgCfg['mallCode'] == 'INIpayTest') {
			$apiUrl = "https://stginiapi.inicis.com/api/v1/extra"; // test
		} else {
			$apiUrl = "https://iniapi.inicis.com/api/v1/extra"; // test
		}

		if ( ! $aPgCfg['iniapiKey']) {
			return false;
		}

		if ( ! $oid ) {
			return false;
		}

		$aRequestData['type'] = "Extra";
		$aRequestData['paymethod'] = "Inquiry";
		$aRequestData['timestamp'] = date("YmdHis");
		$aRequestData['clientIp'] = $_SERVER['SERVER_ADDR'];
		$aRequestData['mid'] = $aPgCfg['mallCode'];
		if (preg_match('/escrow/', $payment)) { // 에스크로 결제인 경우
			$aRequestData['mid'] = $aPgCfg['escrowMallCode'];
		}
		if ($originalTid) {
			$aRequestData['originalTid'] = $originalTid;
		}
		$aRequestData['oid'] = $oid;

		$sHashText = $aPgCfg['iniapiKey'] . $aRequestData['type'] . $aRequestData['paymethod'] . $aRequestData['timestamp'] . $aRequestData['clientIp'] . $aRequestData['mid'];
		$aRequestData['hashData'] = hash('sha512', $sHashText);
		$sResult = readurl($apiUrl, $aRequestData);
		$aResult = json_decode($sResult, true);

		$aResult['success'] = 'N';
		if($aResult['resultCode'] == '00') {
			$aResult['success'] = 'Y';
		}

		return $aResult;
	}

	/*
	lg or lg mobile api
	*/
	protected function lg($payment, $oid, $tid)
	{
		global $xpayCheck, $pg;

		if ( ! $xpayCheck) {
			return false;
		}

		if ( ! $pg['mallCode']) {
			return false;
		}

		if (empty($tid)) {
			return false;
		}

		if ( ! $xpayCheck->Init_TX($pg['mallCode'])) {
			return false;
		}

		$xpayCheck->Set("LGD_TXNAME", "Search");
		$xpayCheck->Set("LGD_TID", $tid);
		$xpayCheck->Set("LGD_STEP", "STEP2");

		if ( ! $xpayCheck->TX()) {
			return false;
		}
		if ($xpayCheck->response_array['LGD_RESPCODE'] !== '0000') {
			return false;
		}
		$transaction_info = $xpayCheck->response_array['LGD_RESPONSE'][0];
		$aResult['success'] = 'N';
		if($transaction_info['LGD_STATUSFLAG'] == '1') {
			$aResult['success'] = 'Y';
		}
		$aResult['tid'] = $transaction_info['LGD_TID'];

		return $aResult;
	}

	protected function kcp($order_seq, $payment)
	{
		global $pg, $c_PayPlus;

		$payment_type = array(
			'card' => '100000000000',
			'account' => '010000000000',
			'cellphone' => '000010000000',
			'virtual' => '001000000000',
			'point' => '000100000000'
		);
		$stsq_pay_type = array(
			'100000000000' => 'PACA',
			'010000000000' => 'PABK',
			'001000000000' => 'PAVC',
			'000010000000' => 'PAMC',
			'000000001000' => 'PATK'
		);

		$use_pay_method = $payment_type[str_replace('escrow_', '', $payment)];
		$g_conf_gw_url = $pg['mallCode']=='T0007' ? "testpaygw.kcp.co.kr" : "paygw.kcp.co.kr";
		$cust_ip = $_SERVER['REMOTE_ADDR'];

		if ( ! $order_seq) {
			return false;
		}
		if ( ! $use_pay_method) {
			return false;
		}
		if ( ! $pg['mallCode'] || ! $pg['merchantKey']) {
			return false;
		}
		if ( ! $c_PayPlus) {
			return false;
		}

		$c_PayPlus->mf_set_modx_data('mod_type', 'STSQ');
		$c_PayPlus->mf_set_modx_data('pay_type', $stsq_pay_type[$use_pay_method]);
		$c_PayPlus->mf_set_modx_data('tno', substr($order_seq, 0, 8).'000000');
		$c_PayPlus->mf_set_modx_data('mod_ordr_idxx', $order_seq);
		$c_PayPlus->mf_set_modx_data('mod_ip', $cust_ip);
		$c_PayPlus->mf_do_tx(
			null,
			ROOTPATH . 'pg/kcp/',
			$pg['mallCode'],
			$pg['merchantKey'],
			'00200000',
			null,
			$g_conf_gw_url,
			'8090',
			'payplus_cli_slib',
			null,
			$cust_ip,
			'3',
			0,
			null
		);
		$aResult['m_res_msg'] = $c_PayPlus->m_res_msg;
		$aResult['m_res_cd'] = $c_PayPlus->m_res_cd;
		$aResult['m_res_data'] = $c_PayPlus->m_res_data;
		$aResult['success'] = 'N';
		if($c_PayPlus->m_res_data['res_cd'] == '0000') {
			$aResult['success'] = 'Y';
		}

		return $aResult;
	}

	/*
	kakaopay or kakaopay mobile api
	*/
	protected function kakaopay($payment, $oid, $originalTid)
	{
		$params['tid'] = $originalTid;
		$result = $this->kakaopaylib->read_api('order ', $params);
		$aResult['success'] = 'N';
		if ($result['httpCode'] == '200' && $result['result']['status'] == 'SUCCESS_PAYMENT') {
			$aResult['success'] = 'Y';
		}
		return $aResult;
	}

	/*
	naverpayment api
	*/
	protected function naverpayment($payment, $oid, $originalTid)
	{
		$params = '{"paymentId": "'.$originalTid.'"}';
		$result = $this->naverpaymentlib->callApi('list', $params);

		if ($result['error'] != true && $result['code'] == 'Success') {
			$response = $result['body']['list'][0];

			if ($response['npointPayAmount'] > 0) { // 네이버페이 포인트 결제 금액
				$response['primaryPayMeans'] = 'point';
			}

			if ($response['primaryPayMeans'] == 'CARD') { // 카드 간편결제
				$response['primaryPayMeans'] = 'card';

				$cardCode = $response['cardCorpCode']; // 결제 수단 카드사
				$cardName = $this->naverpaymentlib->getCardName($response['cardCorpCode']); // 결제카드사명
				$enc = mb_detect_encoding($cardName);
				if($enc != 'UTF-8'){
					$cardName = iconv($enc, "UTF-8", $cardName);
				}
				$approved_id = $response['cardAuthNo']; // 카드사 승인번호
				$approved_at = $response['tradeConfirmYmdt']; // 결제승인시각
				$cardQuota = $response['cardInstCount']; // 할부개월수
			}

			if ($response['primaryPayMeans'] == 'BANK') { // 계좌 간편결제
				$response['primaryPayMeans'] = 'account';

				$bankCode = $response['bankCorpCode']; // 결제 수단 은행
				$bankName = $this->naverpaymentlib->getBankName($response['bankCorpCode']); // 결제은행명
				$enc = mb_detect_encoding($bankName);
				if($enc != 'UTF-8'){
					$bankName = iconv($enc, "UTF-8", $cardName);
				}
				$account = $response['bankAccountNo']; // 일부 마스킹 된 계좌 번호
			}

			// 로그 저장
			$this->set_pg_log(
				array(
					'pg' => 'naverpayment',
					'order_seq' => $oid,
					'tno' => $originalTid,
					'amount' => $response['totalPayAmount'],
					'app_time' => date('YmdHis', strtotime($approved_at)),
					'app_no' => $approved_id,
					'card_cd' => $cardCode,
					'card_name' => $cardName,
					'quota' => $cardQuota,
					'bank_name' => $bankCode,
					'bank_code' => $bankName,
					'account'	=> $account,
					'escw_yn' => 'N',
					'biller' => 'naverpayment',
					'payment_cd' => $response['primaryPayMeans'],
					'res_cd' => $result['code']
				)
			);

			$aResult['success'] = 'Y';
		} else {
			$aResult['success'] = 'N';
		}

		return $aResult;
	}

	/*
	plaform : P, M
	pgCompany : kicc, inicis, lg, allat, kcp, kspay
	log : kicc, inicis, lgdacom, allat, kcp, kspay : pg/[pg]/log
	actionCode : receive, noti, auth, request
	positionCode : process[0-9]
	*/
	public function write_log($orderSeq, $plaform, $pgCompany, $actionCode, $positionCode, $msg)
	{
		if ( ! $plaform) {
			return false;
		}

		if ( ! $pgCompany) {
			return false;
		}

		if ( ! $actionCode) {
			return false;
		}

		if ( ! $positionCode) {
			return false;
		}

		$logComp = $pgCompany;
		if ($pgCompany == 'lg') {
			$logComp = "lgdacom";
		}

		$logDir = "pg/" . $logComp . "/log";
		if ( ! is_dir($logDir)) {
			return false;
		}
		$logfile = implode('_', array($plaform, $actionCode, date("Ymd"))) . ".log";

		if ( ! ($fp = fopen($logDir . "/" . $logfile, 'a+'))) {
			return false;
		}

		ob_start();
		echo(chr(10) . "------[" . $orderSeq . "][" . $positionCode . "][" . date("Y-m-d H:i:s") . "][" . $_SERVER['REMOTE_ADDR'] . "]------" . chr(10));
		print_r($msg);
		$ob_msg = ob_get_contents();
		ob_clean();

		if (fwrite($fp, chr(10) . $ob_msg . chr(10)) === FALSE) {
			fclose($fp);
			return false;
		}
		fclose($fp);
	}

	// 퍼스트몰로 알림
	public function firstmall_noti($noti_order_seq, $pg, $config_system){
		$sUrl = 'https://redeposit.gabiacns.com/set';
		$paramsPost = array(
			'shopSno' => $config_system['shopSno'],
			'pg' => $pg,
			'subDomain' => $config_system['subDomain'],
			'noti_order_seqs' => implode(',', $noti_order_seq)
		);
		$sOut = readurl($sUrl, $paramsPost);
		if ($sOut === false) { // api 전송 실패 시 메일 알림
			$this->firsmall_mail($noti_order_seq, $pg, $config_system);
		}
	}

	// 퍼스트몰 알림 메일 발송
	protected function firsmall_mail($noti_order_seq, $pg, $config_system)
	{
		$noti_cnt = count($noti_order_seq);
		if ($noti_cnt > 0) {
			$this->email->mailtype = 'html';
			$this->email->from("cs@gabiacns.com", "퍼스트몰");
			$this->email->to("shop.dev@gabiacns.com");
			$this->email->subject("[" . $config_system['shopSno'] . "] 상점의 주문의 확인이 필요합니다.");
			$body = "<div>" . $noti_cnt . "건의 주문의 확인이 필요합니다.<br/>";
			$body .= "PG 결제 중 일시적 오류로 인해 정상적으로 쇼핑몰에 결제확인 처리가 진행 되지 않았습니다.<br/>";
			$body .= "PG(".$pg.")에 해당 주문의 정보를 전달 하여 결제 여부를 확인하여 주십시요.<br/>";
			$body .= "결제 처리가 정상으로 확인 되었을 경우, 쇼핑몰의 주문을 결제 확인 하여 주십시요.</div>";
			$body .= "<br/><div><b>업체정보</b></div>";
			$body .= "<li>임시도메인 : " . $config_system['subDomain'] . "</li>";
			$body .= "<li>샵일련번호 : " . $config_system['shopSno'] . "</li>";
			$body .= "<br/><div><b>주문번호</b></div>";
			$body .= "<li>" . implode('</li><li>', $noti_order_seq) . "</li>";
			$this->email->message($body);
			$this->email->send();
			$this->email->clear();
		}
	}
	// 로그 저장
	public function set_pg_log($resultMap)
	{
		$pg_log['pg'] = $resultMap['pg'];
		$pg_log['tno'] = $resultMap['tno'];
		$pg_log['order_seq'] = $resultMap['order_seq'];
		$pg_log['amount'] = $resultMap['amount'];
		$pg_log['app_time'] = $resultMap['app_time'];
		$pg_log['app_no'] = $resultMap['app_no'];
		$pg_log['card_cd'] = $resultMap['card_cd'];
		$pg_log['card_name'] = $resultMap['card_name'];
		$pg_log['noinf'] = $resultMap['noinf'];
		$pg_log['quota'] = $resultMap['quota'];
		$pg_log['bank_name'] = $resultMap['bank_name'];
		$pg_log['bank_code'] = $resultMap['bank_code'];
		$pg_log['depositor'] = $resultMap['depositor'];
		$pg_log['account'] = $resultMap['account'];
		$pg_log['biller'] = $resultMap['biller'];
		$pg_log['commid'] = $resultMap['commid'];
		$pg_log['va_date'] = $resultMap['va_date'];
		$pg_log['mobile_no'] = $resultMap['mobile_no'];
		$pg_log['payment_cd'] = $resultMap['payment_cd'];
		$pg_log['escw_yn'] = $resultMap['escw_yn'];
		$pg_log['res_cd'] = $resultMap['res_cd'];
		$pg_log['res_msg'] = $resultMap['res_msg'];
		$pg_log['regist_date'] = date('Y-m-d H:i:s');
		$this->db->insert('fm_order_pg_log', $pg_log);
	}
}
// end file