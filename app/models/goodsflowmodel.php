<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class goodsflowmodel extends CI_Model {
	var $config_goodsflow;	// 굿스플로 설정정보
	var $api_url;			// 통신 api url
	var $api_key;			// 통신 api key
	var $detail_url;		// 통신 상세url
	var $deliver_pop_url;	// 출력서비스 호출 url
	var $siteCode;			// 고유사이트 코드
	var $gfm_provider_seq;	// 입점사 고유값
	var $gfm_requestKey;	// 통신전송 고유값
	var $gfm_exportlog_seq; // 출고 로그 고유값
	var $timeout;			// readurl 타임아웃

	public function __construct() {
		parent::__construct();
		$this->load->helper('readurl');

		if(!$this->api_url || !$this->api_url || !$this->deliver_pop_url){
			$this->set_api();
		}
	}

	protected function set_api(){

		// 실주소
		$this->api_url	= "https://ds.goodsflow.com/delivery/api/v2/";
		$this->siteCode	= 'gabia';
		$this->api_key	= '43a492bd-a569-42b7-b426-96db351f54ab';
		$this->deliver_pop_url = 'https://ds.goodsflow.com/print/dlvmgr.aspx';

		$this->timeout	= '30';

		$this->detail_url = array(
			'requestService'		=> 'contracts/',						// 서비스이용신청
			'getServiceResult'		=> 'contracts/[:requestKey:]/',			// 서비스이용조회
			'updateService'			=> 'contracts/[:requestKey:]/update/',	// 서비스이용수정
			'cancelService'			=> 'contracts/[:requestKey:]/cancel/',	// 서비스이용취소
			'cancelServiceMulti'	=> 'contracts/cancel-contracts/',		// 서비스이용다중취소
			'sendOrderInformation'	=> 'orders/partner/[:partnerCode:]/',	// 주문등록
			'cancelDelivery'		=> 'orders/[:transUniqueCd:]/cancel/',	// 주문등록취소
			'getOtp'				=> 'otps/partner/[:partnerCode:]/',		// OTP발급
			'getDelivery'			=> 'codes/boxsizes/'					// 택배사조회
		);
	}

	################ API 연동 데이터 보내기 ####################
	public function apiSender($call_type,$params=array()){
		/*
		echo "apiSender Start<br/>";
		echo "call_type : " . $call_type . " <br/> ";
		print_r($params);
		//exit;
		*/

		// setter
		$this->set_provider_seq($params['provider_seq']);

		if($params['requestKey'])
			$this->set_requestKey($params['requestKey']);

		$function	= 'set_'.$call_type;
		$data		= $this->$function($call_type);

		return $data;
	}
	############################################################


	/* ##### 글로별 변수 정의 :: START ###### */
	public function set_provider_seq($provider_seq=1){
		$this->gfm_provider_seq	= $provider_seq;
	}
	public function set_requestKey($requestKey){
		$this->gfm_requestKey	= $requestKey;
	}
	public function set_exportlog_seq($exportlog_seq){
		$this->gfm_exportlog_seq	= $exportlog_seq;
	}
	/* ##### 글로별 변수 정의 :: END ###### */



	/* ##### SETTER :: START ###### */
	// 서비스 이용신청 송수신 API 연동
	protected function set_requestService($apiType){
		/*
		굿스플로 쪽은 partnerCode / centerCode / deliverCode 조합 중복을 체크
		*/

		// API 연동 데이터 가공
		$headers	= $this->get_header();			// Header
		$function	= 'get_body_'.$apiType;
		$body_data	= $this->$function();			// Body
		$call_url	= $this->get_url($apiType);		// url 추출

		// API 요청
		$read_data	= readurl($call_url,$body_data,false,$this->timeout,$headers,false,false);

		// 결과 추출
		$respons	= json_decode($read_data);
		if	($respons->success){
			if	($respons->data->verifiedResult == 'N'){
				$result['verified']		= false;
				$result['msg']			= $respons->data->verifiedMsg;
			}else{
				$result['verified']		= $respons->data->verifiedResult;
				$result['requestKey']	= $respons->data->requestKey;
			}
		}else{
			$result['verified']		= false;
			$errObj = $respons->error;
			if($errObj->detail){
				foreach($errObj->detail->items as $key => $msgObj){
					$errMsg[] = $msgObj->message;
				}
				$result['msg'] = implode(", ", $errMsg);
			}else{
				$result['msg'] = $errObj->message;
			}
		}

		return $result;
	}

	// 서비스 이용신청 수정 송수신
	protected function set_updateService($apiType){

		// API 연동 데이터 가공
		$headers	= $this->get_header();			// Header
		$function	= 'get_body_'.$apiType;
		$body_data	= $this->$function();			// Body
		$call_url	= $this->get_url($apiType);		// url 추출
		$call_url	= str_replace('[:requestKey:]', $this->gfm_requestKey, $call_url);

		// API 요청
		$read_data	= readurl($call_url,$body_data,false,$this->timeout,$headers,false,false);

		// 결과 추출
		$respons	= json_decode($read_data);
		if($respons->success){
			if($respons->error->status == '200'){ // 승인시
				$result['verified']		= true;
				$result['msg']			= $respons->error->message;
			}else{ // 수정실패
				$result['verified']		= false;
				$result['msg']			= $respons->error->message;
			}
		}else{ // 서버에러
			$result['verified']		= false;
			$result['msg']			= $respons->error->message;
		}

		return $result;
	}

	// 서비스 이용취소 송수신 API 연동
	protected function set_cancelService($apiType){

		// API 연동 데이터 가공
		$headers	= $this->get_header();			// Header
		$call_url	= $this->get_url($apiType);		// url 추출
		$call_url	= str_replace('[:requestKey:]', $this->gfm_requestKey, $call_url);
		$body_data	= json_encode('');

		// API 요청
		$read_data	= readurl($call_url,$body_data,false,$this->timeout,$headers,false,false);

		// 결과 추출
		$respons	= json_decode($read_data);
		if($respons->success){
			if($respons->error->status == '200'){ // 승인시
				$result['result']		= true;
				$result['msg']			= '취소되었습니다.';
			}else{ // 수정실패
				$result['result']		= false;
				$result['msg']			= $respons->error->message;
			}
		}else{ // 서버에러
			$result['result']		= false;
			$result['msg']			= $respons->error->message;
		}

		return $result;
	}

	// 서비스 이용신청 결과 확인 API 연동
	protected function set_getServiceResult($apiType){

		// API 연동 데이터 가공
		$headers	= $this->get_header();			// Header
		$call_url	= $this->get_url($apiType);		// url 추출
		$call_url	= str_replace('[:requestKey:]', $this->gfm_requestKey, $call_url);
		//$body_data	= json_encode('');

		// API 요청
		$read_data	= readurl($call_url,$body_data,false,$this->timeout,$headers,false,false);

		// 결과 추출
		$respons	= json_decode($read_data);
		if($respons->success){
			$result['result']		= ($respons->data->verifiedResult=='C') ? false : true;
			$result['result_code']	= $respons->data->verifiedResult;
			$result['cancel_msg']	= $respons->data->verifiedResultCode;
			$result['msg']			= '';

			if($respons->data->verifiedResult == 'Y'){ // 승인시
				$result['goodsflow_step']	= '1';
				$result['goodsflow_msg']	= '이용중';
			}else if($respons->data->verifiedResult == 'N'){ // 거절시
				$result['goodsflow_step']	= '3';
				$result['goodsflow_msg']	= '이용중지 - 연동불가 ('.$respons->data->verifiedMsg.')';
				$result['goodsflow_err']	= $respons->data->verifiedMsg;
			}else{
				// $respons->verifiedResult == 'C' // 확인중
				// 아무짓도 안함.
			}
		}else{
			$result['result']		= false;
			$result['goodsflow_err']= $respons->error->message;
		}

		return $result;
	}

	// 배송주문정보 송수신 API 연동
	protected function set_sendOrderInformation($apiType){

		if(!$_POST['gf_export_code']){
			openDialogAlert( '잘못된 접근입니다.' ,400,150,'parent','');
			exit;
		}

		// 굿스플로 설정 정보 재 추출
		$config_gf = $this->goodsflowmodel->get_goodsflow_setting($this->gfm_provider_seq);

		// 해당 설정 최종 사용가능 체크
		if($config_gf['goodsflow_step']=='1' && $config_gf['gf_use']=='Y'){
			if($this->config_system['goodsflow_use']=='1' || $this->gfm_provider_seq == '1'){
				$chk_flag = false;
			}else{
				$chk_flag = true;
			}
		}else{
			$chk_flag = true;
		}

		if($chk_flag){
			$result['result']		= false;
			$result['msg']			= '사용 불가능 상태입니다.';
			return $result;
			exit;
		}

		// API 연동 데이터 가공
		$headers	= $this->get_header();			// Header
		$function	= 'get_body_'.$apiType;
		$body_data	= $this->$function($config_gf);	// Body
		$partnerCode= $this->config_system['shopSno'].'_'.$this->gfm_provider_seq;
		$call_url	= $this->get_url($apiType);		// url 추출
		$call_url	= str_replace('[:partnerCode:]', $partnerCode, $call_url);

		// API 요청
		$read_data	= readurl($call_url,$body_data,false,$this->timeout,$headers,false,false);

		// 결과 추출
		$respons	= json_decode($read_data);
		if($respons->success){
			if($respons->error->status == '200'){ // 성공
				// 성공 연동 로그 등록
				$gf_log_param['gf_seq']			= $this->gfm_exportlog_seq;
				$gf_log_param['send_param']		= $call_url;
				$gf_log_param['send_xml']		= serialize($body_data);
				$gf_log_param['respon_param']	= serialize($respons);
				$gf_log_param['respon_xml']		= $respons->id;
				$this->set_goodsflow_log($gf_log_param);

				// 처리 프로세스 :: START

				// OTP 정보 받아오기
				unset($client);
				$otp_result		= $this->get_OTP($partnerCode);

				if($otp_result['result']){
					// 출고 로그 업데이트
					$export_log_param['gf_seq']			= $this->gfm_exportlog_seq;
					$export_log_param['sessionKey']		= $respons->id;
					$export_log_param['otp']			= $otp_result['otp'];
					$export_log_param['otp_expireTime']	= $otp_result['expireTime'];
					$this->set_export_log($export_log_param);
					unset($export_log_param);

					// 결과 정보 전송
					$result['result']			= true;
					$result['pop_url']			= $this->deliver_pop_url;
					$result['exportlog_seq']	= $this->gfm_exportlog_seq;
					$result['requestKey']		= $respons->id;
					$result['sessionKey']		= session_id();
					$result['otp']				= $otp_result['otp'];
					$result['siteCode']			= $this->siteCode;

					return $result;
				}
				// 처리 프로세스 :: END

			}else{ // 실패
				$result['result']		= false;
				$result['msg']			= $respons->error->message;
			}
		}else{ // 서버에러
			if($respons->error->detail->items){
				unset($errmsg);
				foreach($respons->error->detail->items as $k => $errObj){
					$errmsg	.= $errObj->detailErrors[0]->message.'<br/>';
				}
				if(!$errmsg){
					$errmsg = "기타에러 : " . $respons->error->detail->items[0]->message;
				}

				$result['result']		= false;
				$result['msg']			= $errmsg;
			}else{
				$result['result']		= false;
				$result['msg']			= "SYSTEM ERR : " . $respons->error->message;
			}
		}

		if($result['result'] === false){
			// 출고 로그 실패처리
			$export_log_param['complete_respons'] = 'N';
			$export_log_param['gf_seq'] = $this->gfm_exportlog_seq;
			$this->set_export_log($export_log_param);

			// 실패 연동 로그 등록
			$gf_log_param['gf_seq']			= $this->gfm_exportlog_seq;
			$gf_log_param['send_param']		= $call_url;
			$gf_log_param['send_xml']		= serialize($body_data);
			$gf_log_param['respon_param']	= serialize($respons);
			$gf_log_param['respon_xml']		= '';
			$this->set_goodsflow_log($gf_log_param);
		}

		return $result;
	}

	/* ##### SETTER :: END ###### */


	/* ##### GETTER :: START ###### */

	// API URL 구해오기
	protected function get_url($apiType){
		if	(!$apiType) return false;

		if	($this->api_url && $this->detail_url[$apiType]){
			return $this->api_url.$this->detail_url[$apiType];
		}else{
			$this->set_api();
			$this->get_url($apiType);
		}
	}

	// 공통 헤더 - 모든 Goodsflow API 통신에 사용 필!
	protected function get_header($context=''){
		$header['Accept']				= "application/json;charset=utf-8";
		$header['Content-Type']			= "application/json;charset=utf-8";
		$header['goodsFLOW-Api-Key']	= $this->api_key;

		if	($context){
			$header['context'] = $context;
		}

		return $header;
	}

	// OTP 정보 받아오기
	protected function get_OTP($partnerCode){

		// API 연동 데이터 가공
		$headers	= $this->get_header();			// Header
		$call_url	= $this->get_url('getOtp');		// url 추출
		$call_url	= str_replace('[:partnerCode:]', $partnerCode, $call_url);
		$body_data	= json_encode('');

		// API 요청
		$read_data	= readurl($call_url,$body_data,false,$this->timeout,$headers,false,false);

		// 결과 추출
		$respons	= json_decode($read_data);
		if($respons->success){
			if($respons->error->status == '200'){ // 승인시
				$result['result']		= true;
				$result['otp']			= $respons->data;
				$result['expireTime']	= date("YmdHis",strtotime("+1 day"));
			}else{ // 실패
				$result['result']		= false;
				$result['msg']			= $respons->error->message;
			}
		}else{
			$result['result']		= false;
			$result['msg']			= $respons->error->message;
		}

		// 실패 로그 처리
		if(!$result['result']){
			$export_log_param['complete_respons'] = 'N';
			$export_log_param['gf_seq'] = $this->gfm_exportlog_seq;
			$this->set_export_log($export_log_param);
		}

		// 연동 로그 등록
		$gf_log_param['gf_seq']			= $this->gfm_exportlog_seq;
		$gf_log_param['send_param']		= $call_url;
		$gf_log_param['send_xml']		= '';
		$gf_log_param['respon_param']	= serialize($respons);
		$gf_log_param['respon_xml']		= '';
		$this->set_goodsflow_log($gf_log_param);

		return $result;
	}

	/* ##### GETTER :: END ###### */


	/* ##### API 호출 함수 :: START ###### */

	// 배송취소 요청 API 연동 - 굿스플로 기존 신청 정보 삭제
	protected function update_goodsflow_del($export_code){
		if($export_code){
			$sql	= "SELECT * FROM fm_goodsflow_export_log WHERE export_code = '".$export_code."' and complete_respons = 'Y'";
			$query	= $this->db->query($sql);
			$result	= $query->result_array();
			if($result[0]['export_code']){

				// 송장번호 유효값 체크 :: 2017-10-18
				$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';
				$this->db->where($export_field,$export_code);
				$query = $this->db->get('fm_goods_export');
				$result	= $query->result_array();	
				if($result[0]['export_code']){
					//배송번호 + shopno = 유니크키를 위함 몰에서 사용할 땐 출고번호만 사용 2016-09-09 jhr
					$tmp_export = $export_code.'|'.$this->config_system['shopSno'];

					// API 연동 데이터 가공
					$headers	= $this->get_header();				// Header
					$call_url	= $this->get_url('cancelDelivery');	// url 추출
					$call_url	= str_replace('[:transUniqueCd:]', $tmp_export, $call_url);
					$body_data	= json_encode('');

					// API 요청
					$read_data	= readurl($call_url,$body_data,false,$this->timeout,$headers,false,false);

					// 결과 추출
					$respons	= json_decode($read_data);

					// 로그 등록
					$gf_log_param['gf_seq']			= '';
					$gf_log_param['send_param']		= $call_url;
					$gf_log_param['send_xml']		= null;
					$gf_log_param['respon_param']	= serialize($respons);
					$gf_log_param['respon_xml']		= '';
					$this->set_goodsflow_log($gf_log_param);

					if($respons->success){
						if($respons->error->status == '200'){ // 삭제완료
							// 굿스플로 취소시 기존 운송장 번호 삭제 :: 2015-10-07 lwh
							$data['delivery_number'] = '';
							$export_field	= (preg_match('/^B/', $export_code)) ? 'bundle_export_code' : 'export_code';
							$this->db->where($export_field,$export_code);
							$this->db->update('fm_goods_export',$data);
						}
					}else{ // 서버에러
						if($respons->error->status != '400'){ // 이미 취소가 된 경우를 제외  :: 2017-12-20 lkh
							openDialogAlert( '다음과 같은 사유로 실패되었습니다.<br/>['.$respons->error->message.']' ,400,170,'parent','');
							exit;
						}
					} // end if - success
				} // end if - real export_code
			} // end if - export_code
		} // end if - export_code params
	}

	// 서비스 이용신청 body 추출
	protected function get_body_requestService(){
		$json_data	= array();
		$body_data	= $_POST;

		$json_data['requestKey']		= $this->get_requestKey('requestService');
		$json_data['partnerCode']		= $this->config_system['shopSno'].'_'.$this->gfm_provider_seq;
		$json_data['centerCode']		= $this->config_system['shopSno'].'_'.$this->gfm_provider_seq;
		$json_data['deliverCode']		= $body_data['deliveryCode'];
		$json_data['verificationType']	= 'M'; // M 일반승인 | F 강제승인
		$json_data['bizNo']				= $body_data['bizNo'];
		$json_data['contractNo']		= $body_data['contractNo'];
		if($body_data['contractCustNo']){
			$json_data['contractCustNo']= $body_data['contractCustNo'];
		}
		$json_data['mallId']			= $body_data['mallId'];
		$json_data['mallName']			= $body_data['mallName'];
		$json_data['mallUserName']		= $body_data['mallUserName'];
		$json_data['mallUserTel1']		= $body_data['centerTel1'];
		if($body_data['centerTel2']){
			$json_data['mallUserTel2']	= $body_data['centerTel2'];
		}
		$json_data['mallUserEmail']		= '';
		$json_data['centerName']		= $body_data['centerName'];
		$json_data['centerZipCode']		= $body_data['goodsflowZipcode'][0].$body_data['goodsflowZipcode'][1];
		$json_data['centerAddr1']		= $body_data['goodsflowAddress'];
		$json_data['centerAddr2']		= $body_data['goodsflowAddressDetail'];
		$json_data['centerTel1']		= $body_data['centerTel1'];
		if($body_data['centerTel2']){
			$json_data['centerTel2']	= $body_data['centerTel2'];
		}
		$json_data['shippingFee']		= 0;
		$json_data['flightFee']			= 0;
		foreach($body_data['boxSize'] as $key => $val){
			if( $body_data['shFare'][$key] >= 0 && $body_data['scFare'][$key] >= 0 && $body_data['bhFare'][$key] >= 0 && $body_data['rtFare'][$key] >= 0) {
				$contractRates[$key]['boxSize']	= $body_data['boxSize'][$key];
				$contractRates[$key]['shFare']	= (int)$body_data['shFare'][$key];
				$contractRates[$key]['scFare']	= (int)$body_data['scFare'][$key];
				$contractRates[$key]['bhFare']	= (int)$body_data['bhFare'][$key];
				$contractRates[$key]['rtFare']	= (int)$body_data['rtFare'][$key];
			}
		}
		$json_data['contractRates']		= $contractRates;

		$res_data['data']				= $json_data;
		return json_encode($res_data);
	}

	// 서비스 이용신청 body 추출
	protected function get_body_updateService(){
		$json_data	= array();
		$body_data	= $_POST;

		$json_data['mallName']			= $body_data['mallName'];
		$json_data['mallUserName']		= $body_data['mallUserName'];
		$json_data['mallUserTel1']		= $body_data['centerTel1'];
		if($body_data['centerTel2']){
			$json_data['mallUserTel2']	= $body_data['centerTel2'];
		}
		$json_data['mallUserEmail']		= '';
		$json_data['centerName']		= $body_data['centerName'];
		$json_data['centerZipCode']		= $body_data['goodsflowZipcode'][0].$body_data['goodsflowZipcode'][1];
		$json_data['centerAddr1']		= $body_data['goodsflowAddress'];
		$json_data['centerAddr2']		= $body_data['goodsflowAddressDetail'];
		$json_data['centerTel1']		= $body_data['centerTel1'];
		if($body_data['centerTel2']){
			$json_data['centerTel2']	= $body_data['centerTel2'];
		}
		$json_data['shippingFee']		= 0;
		$json_data['flightFee']			= 0;
		foreach($body_data['boxSize'] as $key => $val){
			if( $body_data['shFare'][$key] >= 0 && $body_data['scFare'][$key] >= 0 && $body_data['bhFare'][$key] >= 0 && $body_data['rtFare'][$key] >= 0) {
				$contractRates[$key]['boxSize']	= $body_data['boxSize'][$key];
				$contractRates[$key]['shFare']	= (int)$body_data['shFare'][$key];
				$contractRates[$key]['scFare']	= (int)$body_data['scFare'][$key];
				$contractRates[$key]['bhFare']	= (int)$body_data['bhFare'][$key];
				$contractRates[$key]['rtFare']	= (int)$body_data['rtFare'][$key];
			}
		}
		$json_data['contractRates']		= $contractRates;

		$res_data['data']				= $json_data;
		return json_encode($res_data);
	}

	// 주문정보 body 추출
	protected function get_body_sendOrderInformation($config_goodsflow){
		$json_data	= array();
		$body_data	= $_POST;

		$this->load->model('exportmodel');
		$this->load->model('ordermodel');
		$this->load->model('goodsflowmodel');

		// 전송ID 추출 및 데이터 추출
		if($body_data['gf_mode'] == 'all'){
			$export_arr		= $body_data['gf_export_code'];
		}else{
			$export_arr[]	= $body_data['gf_export_code'];
		}

		foreach($export_arr as $k => $gf_export_code){
			unset($send_param);

			// 기존 신청정보 추출
			$this->update_goodsflow_del($gf_export_code);

	        // 배송정보 추출
			$data_export = $this->exportmodel->get_export($gf_export_code);
			$exportOrderList	= array();
			if(preg_match('/^B/', $gf_export_code)){
				$export_list	= $this->exportmodel->get_export_bundle($gf_export_code);
				$data_export['export_code']			= $data_export['bundle_export_code'];
				$exportOrderList					= $export_list['bundle_order_info'];
			} else {
				$exportOrderList[$gf_export_code]	= $data_export['order_seq'];
			}


			$data_export_item = $this->exportmodel->get_export_item($gf_export_code);
			$order	= $this->ordermodel->get_order($data_export['order_seq']);

			// fm_order_shipping.shipping_type 으로 선/착불 정보 가져오기(default-선불/선결제, postpaid-착불/결제안함) 2020-04-03 
			$shipping_type			= $data_export_item['0']['shipping_type'];
			$paymentTypeCode		= "SH";		// 선불
			$preShippingPriceYN		= "Y";		// 선결제
			if($shipping_type == "postpaid") {
				$paymentTypeCode	= "BH";		// 착불
				$preShippingPriceYN = "N";		// 결제안함
			}

			// 배송그룹 정보 추출 :: 2017-01-18 lwh
			$grp_arr = explode('_',$data_export['shipping_group']);
			if(count($grp_arr) >= 3){ // 개선 배송그룹
				$this->load->model('shippingmodel');
				$grp_sql = "SELECT refund_address_seq,refund_scm_type FROM fm_shipping_grouping WHERE shipping_provider_seq = {$data_export['shipping_provider_seq']} AND default_yn = 'Y' LIMIT 1";
				$grp_info = $this->db->query($grp_sql);
				$grp_info = $grp_info->row_array();
				$add_info = $this->shippingmodel->get_shipping_address($grp_info['refund_address_seq'], $grp_info['refund_scm_type']);

				// 주소 있는경우 기존 정보를 교체함.
				if($add_info['address_type'] && $add_info['address_zipcode']){
					$config_goodsflow['goodsflowZipcode']		= $add_info['address_zipcode'];
					$config_goodsflow['goodsflowAddress']		= $add_info['address'];
					$config_goodsflow['goodsflowAddressDetail']	= $add_info['address_detail'];
				}else{ // 정상정보가 아닐 경우 굿스플로 발송지로 처리 :: 2018-01-05 lkh
					if(is_array($config_goodsflow['goodsflowZipcode']))
						$config_goodsflow['goodsflowZipcode'] = implode('',$config_goodsflow['goodsflowZipcode']);
				}
			}else{
				if(is_array($config_goodsflow['goodsflowZipcode']))
					$config_goodsflow['goodsflowZipcode'] = implode('',$config_goodsflow['goodsflowZipcode']);
			}

			// XML 데이터 추출 :: START
			$send_param['mallId']	= $config_goodsflow['mallId'];
			$send_param['ordName']	= $order['order_user_name']; // 주문자명
			$send_param['ordTel1']	= str_replace("-","",$order['order_phone']); // 주문자전화1
			$send_param['ordTel2']	= str_replace("-","",$order['order_cellphone']);//주문자전화2
			$send_param['sndName']	= $config_goodsflow['mallName']; // 송화인명

			// 배송그룹에서 불러와서 넣도록 수정 :: 2017-01-18 lwh
			$send_param['sndZipCode']	= $config_goodsflow['goodsflowZipcode']; // 송화인 우편번호
			$send_param['sndAddr1']		= $config_goodsflow['goodsflowAddress']; // 송화인기본주소
			$send_param['sndAddr2']		= $config_goodsflow['goodsflowAddressDetail']; // 송화인상세주소

			$send_param['sndTel1']	= $config_goodsflow['centerTel1']; // 송화인전화1
			$send_param['sndTel2']	= $config_goodsflow['centerTel2']; // 송화인전화2
			$send_param['centerCode']= $config_goodsflow['mallId']; // 발송지코드
			$send_param['rcvName']	= $order['recipient_user_name']; // 수신자명
			$send_param['rcvZipCode']= str_replace("-","",$order['recipient_zipcode']); // 수신지 우편번호
			// 수신자 주소 default:도로명주소
			$send_param['rcvAddr1']	= ($order['recipient_address_street']) ? $order['recipient_address_street'] : $order['recipient_address_street_gf'];
			if(!$send_param['rcvAddr1'])
				$send_param['rcvAddr1']	= $order['recipient_address'];
			$send_param['rcvAddr2']	= str_replace("<","[",$order['recipient_address_detail']);
			$send_param['rcvAddr2']	= str_replace(">","]",$send_param['rcvAddr2']);
															// 수신지 상세주소
			// 수신지전화 체크 :: 2015-10-15 lwh
			// 숫자이외에 문자 삭제 :: 2018-03-22 lkh
			$o_recipient_phone		= preg_replace("/[^0-9]/s","",$order['recipient_phone']);
			$o_recipient_cellphone	= preg_replace("/[^0-9]/s","",$order['recipient_cellphone']);
			if(!$o_recipient_cellphone) $o_recipient_cellphone = $o_recipient_phone;
			$send_param['rcvTel1']	= $o_recipient_cellphone; // 수신지전화1 필수
			$send_param['rcvTel2']	= $o_recipient_phone; // 수신지전화2
			$send_param['deliverCode'] = $config_goodsflow['deliveryCode']; // 택배사 코드
			$send_param['PaymentTypeCode'] = $paymentTypeCode; // 기본 운임지불방법
			$send_param['preShippingPriceYN'] = $preShippingPriceYN; // 배송비 선결제여부
			$send_param['boxSize']		= ''; // 기본 박스규격
			$send_param['msgToTrans']	= getstrcut(str_replace('&','&amp;',$order['memo']),98,".."); // 배송메시지
			$send_param['status']		= 'N'; // 상태구분 : 송장출력필수 - 신규 N / 미발송 O
			$send_param['sheetNo']		= ''; // 원송장번호 - 반품시
			//배송번호 + shopno = 유니크키를 위함 몰에서 사용할 땐 출고번호만 사용 2016-09-09 jhr
			$send_param['transUniqueCd']	= $data_export['export_code'].'|'.$this->config_system['shopSno'];
			// 상품정보
			foreach($data_export_item as $k => $item){
				$key = $k + 1;

				unset($options);
				unset($send_item);
				unset($options_str);

				// 옵션재조합
				if($item['option1']) $options[] = $item['title1'] . ':' . $item['option1'];
				if($item['option2']) $options[] = $item['title2'] . ':' . $item['option2'];
				if($item['option3']) $options[] = $item['title3'] . ':' . $item['option3'];
				if($item['option4']) $options[] = $item['title4'] . ':' . $item['option4'];
				if($item['option5']) $options[] = $item['title5'] . ':' . $item['option5'];
				if($options){ $options_str	= implode(', ',$options); }

				// 고객사용번호 (배송상품에 대한 고유번호) - 추후 배송완료처리에 쓰임
				$uniqueCd = $this->config_system['shopSno'].'_'.$data_export['export_code'].'_'.$item['opt_type'].'_'.$item['option_seq'];
				$send_item['uniqueCd']	= $uniqueCd;
				$send_item['ordNo']		= $exportOrderList[$item['export_code']]; // 주문번호
				$send_item['ordLineNo']	= (int)$key; // 주문행번호
				$send_item['itemCode']	= $item['goods_seq']; // 상품코드
				$send_item['itemName']	= htmlspecialchars($item['goods_name']); // 상품명

				$send_item['itemOption']= htmlspecialchars($options_str); // 상품옵션
				$send_item['itemQty']	= (int)$item['ea']; // 상품수량
				$send_item['itemPrice']	= (int)$item['price']; // 상품단가
				$send_item['ordDate']	= date('YmdHis', strtotime($order['regist_date'])); // 주문일시
				$send_item['paymentDate']= date('YmdHis', strtotime($order['deposit_date'])); // 입금일시
				$send_item['receiptDate']= date('YmdHis', strtotime($order['deposit_date'])); // 결제일시

				$send_param['orderItems'][] = $send_item;
			}
			// XML 데이터 추출 :: END

			$json_data['items'][] = $send_param;

			// 출고 로그 처리 등록
			$export_log_param['order_seq'] = $data_export['order_seq'];
			$export_log_param['export_code'] = $data_export['export_code'];
			$export_log_param['delivery_company_code'] = $config_goodsflow['deliveryCode'];
			$export_log_param['shipping_provider_seq'] = $data_export['shipping_provider_seq'];
			$export_log_param['provider_seq'] = $data_export_item[0]['provider_seq'];
			$export_log_param['order_user_name'] = $order['order_user_name'];
			$export_log_param['recipient_user_name'] = $order['recipient_user_name'];
			$export_log_param['requestKey'] = 'firstmall';
			$this->set_export_log($export_log_param);
			unset($export_log_param);
		}

		$res_data['data']				= $json_data;
		return json_encode($res_data);
	}

	//배송결과 수신 - mall cronjob 호출 (절대 변경 금지)
	public function get_receiveTrackingResults(){
		$this->load->model('exportmodel');
		$this->load->model('ordermodel');

		### 새벽에 문자 발송 방지
		$now_time = date("H");
		if( (int) $now_time >= 23){
			$this->sms_reserve = date('Y-m-d', strtotime('+1 days'))." 09:00:00";
		}else if( (int)$now_time >= 0 && $now_time < 9 ){
			$this->sms_reserve = date("Y-m-d")." 09:00:00";
		}else{
			$this->sms_reserve = 0;
		}

		$this->managerInfo['mname'] = '굿스플로 택배자동화서비스';
		$curl_url					= 'http://goodsflow.firstmall.kr';

		$tracking_url		= $curl_url.'/resultTracking.php';
		$params['shopno']	= $this->config_system['shopSno'];
		$xml	= $this->curl_service($params,$tracking_url);

		if($xml == 'err'){
			echo "통신 정보 오류 - " . $tracking_url;
			echo "<pre>";
			print_r($params);
			echo "</pre>";
		}else{
			$result	= xml2array($xml);

			$loop = $result['trackingList']['tracking'];

			if ($loop) {

				if(!$loop[0]) $loop = array($loop);

				foreach($loop as $tracking){
					// 출고 정보 추출
					$export_code = $tracking['exportCode'];
					$data_export = $this->exportmodel->get_export($export_code);

					//네이버페이 주문건이 아닐 때에만 처리
					if(trim($data_export['npay_order_id']) == ""){
						// 출고 완료 이후 건만 배송완료 작업
						// 출고 준비도 배송완료 작업 (출고준비만 하고 완료하지 않는) :: 2016-04-28 lwh
						if($data_export['status'] >= '45' && $data_export['status'] < '75'){
							//goods cronjob 무조건 배송완료처리 구문개선 .@2016-07-25 ysm
							$return = $this->exportmodel->exec_complete_delivery($export_code,'goodsflow_cronjob');

							$succParam[]			= $tracking['trackingSeq'];
							$del_code['succ'][]		= $export_code;

						}else if($data_export['status'] == '75'){ // 기존 배송완료 처리건은 성공으로 간주
							$succParam[]			= $tracking['trackingSeq'];
							$del_code['already'][]	= $export_code;
						}else if(!$data_export['status']){ // 기존 출고건이 취소된경우
							$succParam[]			= $tracking['trackingSeq'];
							$del_code['fail'][]		= $export_code;
						}else{ // 출고완료 이전 상태
							$failParam[]	= $tracking['trackingSeq'];
						}
					}
				}

				// SMS(카카오톡발송처리) :: 2020-11-06
				if(count($this->exportSmsData) > 0){
					commonSendSMS($this->exportSmsData);
				}

				// 성공 건수 굿스플로 DB 삭제 요청
				if(count($succParam) > 0){
					$respons_url		= $curl_url.'/responsTracking.php';
					$params['succ']		= implode('|',$succParam);
					$params['del_code'] = $del_code;
					$this->curl_service($params,$respons_url);
				}
			}

			echo "OK";
		}
	}

	// 가능택배사 조회
	public function delivery_set(){
		$deli_code = array(
		    'CJGLS'=>'code0', // CJ 대한통운
		    'EPOST'	=>'code7', // 우체국택배
		    'HANJIN'=>'code9', // 한진택배
		    'HYUNDAI'=>'code10', // 롯데택배
		    'KGB'=>'code6', // 로젠택배
		    'ILYANG'=>'auto_ILYANG', // 일양로지스
		    'KGBPS'=>'auto_KGBPS', // KGB택배
		);
		
		// API 연동 데이터 가공
		$headers	= $this->get_header();				// Header
		$call_url	= $this->get_url('getDelivery');	// url 추출
		$body_data	= null;

		// API 요청
		$read_data	= readurl($call_url,$body_data,false,$this->timeout,$headers,false,false);
		
		// 결과 추출
		$respons	= json_decode($read_data);

		if($respons->success){
			if($respons->error->status == '200'){ // 추출완료
				$tmp_deli = array();
				foreach($respons->data->items as $k => $info){
				    if($deli_code[$info->deliverCode]) {
				        if(!$tmp_deli[$info->deliverCode]){
				            $tmp_deli[$info->deliverCode]['name'] = $info->deliverName;
				            $tmp_deli[$info->deliverCode]['code'] = $deli_code[$info->deliverCode];
				        }
				        $tmp_deli[$info->deliverCode]['boxtype'][] = $info->boxSize;
				        $tmp_deli[$info->deliverCode]['boxname'][] = $info->boxName;
				    }
				}
			}
		} // end if - success
		
		$return_deli = array();
		foreach(array_keys($deli_code) as $code) {
		    $return_deli[$code] = $tmp_deli[$code];
		}
		
		return $return_deli;
	}

	/* ##### API 호출 함수 :: END ###### */



	/* ##### 공용 함수 :: START ###### */

	// api requestKey 추출 (로그테이블에서 추출)
	protected function get_requestKey($apiType){
		$requestKey = $this->usedmodel->used_get_requestKey($apiType,$this->gfm_provider_seq);

		return $requestKey;
	}

	// 서비스 충전건수 정보 호출 및 서비스
	public function get_service_info($type='view'){
		$service_cnt = $this->usedmodel->used_get_service_info($type);

		return $service_cnt;
	}

	// api 연동 로그
	public function set_goodsflow_log($param){
		$param['regist_date'] = date('Y-m-d H:i:s');
		$this->db->insert('fm_goodsflow_report_log',$param);
	}

	// 출고 로그 등록
	protected function set_export_log($param){
		if($param['gf_seq']){
			if($param['export_code'])
				$whereParam['export_code']	= $param['export_code'];
			if($param['gf_seq']){
				$whereParam['gf_seq']		= $param['gf_seq'];
			}else{
				$whereParam['gf_seq']		= $this->gfm_exportlog_seq;
			}
			unset($param['gf_seq']);
			unset($param['export_code']);
			$this->db->where($whereParam);
			$this->db->update('fm_goodsflow_export_log',$param);
		}else{
			$param['complete_date'] = date('Y-m-d H:i:s');
			$this->db->insert('fm_goodsflow_export_log',$param);
			$exportlog_seq = $this->db->insert_id();
			$this->set_exportlog_seq($exportlog_seq);

			// 출고에 로그 값 연결
			$export_field	= (preg_match('/^B/', $param['export_code'])) ? 'bundle_export_code' : 'export_code';
			$this->db->where(array($export_field=>$param['export_code']));
			$this->db->update('fm_goods_export',array('gf_seq'=>$exportlog_seq));

			return $exportlog_seq;
		}
	}

	// curl 함수 (보낼값, 통신주소)
	public function curl_service($params,$curl_url){
		$user_agent = 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; .NET CLR 1.0.3705; .NET CLR 1.1.4322; Media Center PC 4.0)';
		$ch = curl_init(); // cURL 초기화
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSLVERSION,1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_URL, $curl_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION,1);
		curl_setopt($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
		curl_setopt($ch, CURLOPT_COOKIEFILE, 'cookie.txt');

		$result = curl_exec($ch);	// cURL 실행

		if(!curl_error($ch)){
			$result_info = curl_getinfo($ch);
		}else{
			echo "err ->".curl_errno($ch)." : " .curl_error($ch); // 에러시 $err 값 추출
			echo "<br/>url:".$curl_url."<pre>";
			print_r($params);
			echo "</pre>";
		}

		curl_close($ch); // curl 종료

		return $result;
	}
	/* ##### 공용 함수 :: END ###### */

	// 굿스플로 설정 (계약정보)
	public function set_goodsflow_setting($data){
		// 데이터 재가공
		unset($data['goodsflow_notuse']);
		$data['goodsflowZipcode']	= implode('-',$data['goodsflowZipcode']);
		$data['boxSize']			= implode('|',$data['boxSize']);
		$data['shFare']				= implode('|',$data['shFare']);
		$data['scFare']				= implode('|',$data['scFare']);
		$data['bhFare']				= implode('|',$data['bhFare']);
		$data['rtFare']				= implode('|',$data['rtFare']);
		$data['regist_date']		= date('Y-m-d H:i:s');

		$sql	= "select * from fm_goodsflow where provider_seq = ? limit 1";
		$query	= $this->db->query($sql,array($data['provider_seq']));
		$result	= $query->row_array();

		if($result['provider_seq']){
			$this->db->where('provider_seq',$result['provider_seq']);
			$this->db->update('fm_goodsflow',$data);
		}else{
			$this->db->insert('fm_goodsflow',$data);
		}
	}

	// 굿스플로 상태값 셋팅
	public function set_goodsflow_step($provider_seq='1',$data){
		if($data['goodsflow_step'])
			$param['goodsflow_step']	= $data['goodsflow_step'];
		if($data['goodsflow_msg'])
			$param['goodsflow_msg']		= $data['goodsflow_msg'];
		if($data['goodsflow_err'])
			$param['goodsflow_err']		= $data['goodsflow_err'];

		if(count($param) > 0){
			$param['modify_date'] = date('Y-m-d H:i:s');
			$this->db->where('provider_seq',$provider_seq);
			$this->db->update('fm_goodsflow',$param);
		}
	}

	// 굿스플로 설정 가져오기
	public function get_goodsflow_setting($provider_seq='1')
	{
		$sql = 'select * from fm_goodsflow where provider_seq = ? ';
		$query = $this->db->query($sql, [$provider_seq]);
		$result	= $query->row_array();

		if($result){
			$result['goodsflowZipcode']	= explode('-',$result['goodsflowZipcode']);
			$result['boxSize']			= explode('|',$result['boxSize']);
			$result['shFare']			= explode('|',$result['shFare']);
			$result['scFare']			= explode('|',$result['scFare']);
			$result['bhFare']			= explode('|',$result['bhFare']);
			$result['rtFare']			= explode('|',$result['rtFare']);
		}

		return $result;
	}
	
	/**
	 * 입점사들의 굿스플로 설정 정보를 가져온다.
	 * @param array|int $provider_seqs
	 */
	public function get_goodsflow_setting_list($provider_seqs, $selectFields = '*')
	{
	    if(is_array($provider_seqs) === false) {
	        $provider_seqs = array($provider_seqs);
	    }
	    
	    $query = $this->db->select($selectFields)
	    ->from("fm_goodsflow")
	    ->where_in('provider_seq', $provider_seqs)
	    ->get();
	    return $query->result_array();
	}

	// 굿스플로 설정 삭제
	public function del_goodsflow_setting($provider_seq='1'){
		$table		= 'fm_goodsflow';
		$no_del		= array('gf_seq','gf_use','provider_seq','regist_date');
		$tb_fields	= $this->db->list_fields($table);
		foreach($tb_fields as $k => $field){
			if(in_array($field, $no_del)){
				continue;
			}else{
				$fields[$field] = null;
			}

			if($field == 'modify_date'){
				$fields[$field] = date('Y-m-d H:i:s');
			}
		}
		$this->db->where(array('provider_seq'=>$provider_seq));
		$this->db->update($table, $fields);
	}

	// 굿스플로 로그 검색
	public function get_goodsflow_log($gf_seq){
		$sql	= "SELECT * FROM fm_goodsflow_export_log WHERE gf_seq = '".$gf_seq."'";
		$query	= $this->db->query($sql);
		$result	= $query->result_array();

		return $result[0];
	}

	// 굿스플로 로그 리스트
	public function goodsflow_log_list($param){

		// 날짜검색
		if( $param['sdate'] && $param['edate'] ) {
			$addWhere[] = " gf.complete_date BETWEEN '{$param['sdate']} 00:00:00' AND '{$param['edate']} 23:59:59' ";
		} else if( $param['sdate'] ) {
			$addWhere[] = " gf.complete_date >= '{$param['sdate']}' ";
		} else if( $param['edate'] ) {
			$addWhere[] = " gf.complete_date <= '{$param['edate']}' ";
		}

		// 주문번호,출고번호,주문자,수신인명 검색
		if($param['search_txt']){
			$addWhere[]	= " (gf.order_seq = '".$param['search_txt']."' or gf.export_code = '".$param['search_txt']."' or gf.order_user_name like '%".$param['search_txt']."%' or gf.recipient_user_name like '%".$param['search_txt']."%' ) ";
		}
		// 디폴트 입점사 검색
		if($param['no']){
			$addWhere[]	= " gf.provider_seq = '".$param['no']."' ";
		}
		if($param['provider_seq']){
			$addWhere[]	= " gf.provider_seq = '".$param['provider_seq']."' ";
		}
		// 입점사 검색
		if($param['shipping_provider_seq']=='Y'){
			$addWhere[]	= " gf.provider_seq = '1' ";
		}else if($param['shipping_provider_seq']=='N'){
			if($param['provider_seq_selector'] == '999999999999'){
				$addWhere[]	= " gf.provider_seq <> '1' ";
			}else{
				$addWhere[]	= " gf.provider_seq = '".$param['provider_seq_selector']."' ";
			}
		}
		// 본사배송그룹 검색
		if($param['admin_ship']=='1'){
			$addWhere[]	= " gf.shipping_provider_seq = '1' ";
		}
		// 결과 검색
		if($param['complete_respons']){
			foreach($param['complete_respons'] as $k => $val){
				$complete_res[] = " gf.complete_respons = '".$val."' ";
			}
			$addWhere[] = " ( ".implode(' or ', $complete_res)." ) ";
		}

		if(count($addWhere) > 0) {
			$addWhere = "and ".implode(' and ',$addWhere);
		}

		// 전체 게시글 갯수
		$cnt_query	= " SELECT count(*) cnt
				FROM fm_goodsflow_export_log as gf LEFT JOIN fm_provider as p
				ON gf.provider_seq = p.provider_seq
				WHERE gf.complete_respons is not NULL ".$addWhere;
		$cnt_query	= $this->db->query($cnt_query);
		$cnt_res	= $cnt_query->row_array();
		$return['total'] = $cnt_res['cnt'];

		// 페이징
		if	($param['nolimit'] != 'y'){
			$nperpage			= 10;
			$param['limit_s']	= (trim($param['page'])) ? trim($param['page']) : 0;
			$param['limit_e']	= $nperpage;
			$param['total_page']= ceil($return['total'] / $nperpage);
			$limit	= " limit ".$param['limit_s'].", ".$param['limit_e'];

			$paginlay = pagingtag($return['total'],$nperpage,'?', getLinkFilter('',array_keys($param)) );
			$return['paginlay']	= $paginlay;
		}else{
			$limit	= "";
		}

		$query = "
			SELECT
				gf.*, p.provider_id, p.provider_name
			FROM
				fm_goodsflow_export_log as gf LEFT JOIN fm_provider as p
				ON gf.provider_seq = p.provider_seq
			WHERE gf.complete_respons is not NULL ".$addWhere." ORDER BY gf.complete_date desc ".$limit;

		$query			= $this->db->query($query);
		$return['list']	= $query->result_array();

		return $return;
	}

	/**
	 * 굿스플로 택배사코드 목록을 반환한다.
	 * @return array
	 */
	public function get_goodsflow_delivery_codes()
	{
	    $query = $this->db->select('DISTINCT deliveryCode as deliveryCode', false)
	    ->from("fm_goodsflow")
	    ->where("deliveryCode <> ''", null, false)
	    ->where("deliveryCode IS NOT NULL", null, false)
	    ->get();
	    $result = $query->result_array();
	    $deliveryCodes = array();
	    if(count($result)>0){
	        foreach($result as $row) {
	            $deliveryCodes[] = $row['deliveryCode'];
	        }
	    }
	    return $deliveryCodes;
	}
}
?>
