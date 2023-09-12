<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 톡체크아웃 서버 통신
 * @author Hyemi Ryu
 * 2020-10-27
 */
class talkbuy_sendlibrary
{

	protected $apiUrl;
	protected $detailUrl;
	protected $timeout;
	protected $talkbuyCfg;
	protected $debug;
	var $talkbuyShopKey = '';

	function __construct() {
		$this->CI =& get_instance();

		$this->CI->load->helper('readurl');
		$this->timeout	= '10';


		$this->talkbuyCfg = config_load("talkbuy");
		
		$this->debug = false;
		if($this->talkbuyCfg["debug"] == "y") {
			$this->debug = true;
		}

		if(!$this->apiUrl){
			$this->setApi();
		}
	}

	/*
	* 톡체크아웃서버 통신 시 sendMethod 호출
	*/
	function sendMethod($method,$body=array(),$path=array()){
		// 받은 토큰을 header 로 합쳐서 전송
		// API 연동 주소 호출
		$call_info = $this->getUrl($method);

		// uri에 path 치환
		if(!empty($path)) {
			preg_match_all("/\{.*?\}/", $call_info["uri"],$uri_match);
			foreach($uri_match[0] as $match) {
				$key_match = str_replace(array("{","}"), "", $match);
				$call_info["uri"] = str_replace($match,$path[$key_match],$call_info["uri"]);
			}
		}
		$response = $this->send($call_info, $body);

		return (array)$response;
	}

	/**
	 * 실제 톡체크아웃 서버 curl 통신
	 */
	public function send($call_info, $data) {
		$call_url = $call_info['uri'];
		$call_method = $call_info['method'];
		$body_data = $data;

		$headers	= $this->getHeader();			// Header
		if(!in_array($call_method, array('post','get'))) {
			$method = $call_method;
		}

		$read_data	= readurl($this->apiUrl.$call_url,$body_data,false,$this->timeout,$headers,true,true,$method);

		// 200/201 외
		if(is_array($read_data)) {
			$response['httpCode'] = $read_data['httpCode'];
			$response['message'] = json_decode($read_data['result'])->message;
		} else {
			$response	= json_decode($read_data);
		}

		$debug['url'] 			= $this->apiUrl.$call_url;
		$debug['body'] 			= $body_data;
		$debug['method'] 		= $method;
		$debug['call_method'] 	= $call_method;
		$debug['headers'] 		= $headers;
		$debug['response'] 		= $response;

		writeCsLog($debug, "api" , "talkbuy", "hour");

		return $response;
	}

	/**
	 * header 생성 (auth 외에는 헤더에 Authorization 포함)
	 */
	protected function getHeader(){
		$header['shop-Key']			= $this->talkbuyCfg["shopKey"] ? $this->talkbuyCfg["shopKey"] : $this->talkbuyShopKey;
		$header['shopsno']			= get_shop_key();

		return $header;
	}

	/**
	 * url path SET
	 */
	protected function setApi() {
		$host = $this->talkbuyCfg["mallApiHost"];
		if($this->debug) $host = $this->talkbuyCfg["mallApiHostDebug"];
		$this->apiUrl	= $host."/firstmall/";
		
		$this->detailUrl = array(
			'orderConfirm'	=> array( 'method'=>'post', 'uri' =>'order-products/confirm'), 	//주문 확인
			'getOrders'	=> array( 'method'=>'get', 'uri' =>'orders'), 						//주문 수집
			'setDelivery' => array('method'=>'post', 'uri'=>'order-products/delivery'),		// 발송처리
			'getStatus'	=> array( 'method'=>'get', 'uri' =>'shops/status'), 				// 카카오 판매점 상태조회
			'setInfo' => array('method'=>'post', 'uri' => 'talkbuy-service/mapping'),		// 중계서버 키 등록
			'getServiceStatus' => array( 'method'=>'get', 'uri' =>'talkbuy-service'), 		// 중계서버 판매점 상태조회
			'setStatus' => array( 'method'=>'patch', 'uri' =>'talkbuy-service'), 			// 카카오 판매점 상태 업데이트
			'setQnaAnswer' => array( 'method'=>'post', 'uri' =>'questions/answer'), 		// 상품 문의 답변
		);
	}

	/**
	 * url path GET
	 */
	protected function getUrl($apiType){
		if	(!$apiType) return false;

		if	($this->detailUrl[$apiType]){
			return $this->detailUrl[$apiType];
		}else{
			$this->setApi();
			$this->getUrl($apiType);
		}
	}	
}