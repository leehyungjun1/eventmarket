
<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class talkbuy extends front_base {

	var $culture	= false;

	public function __construct()
	{
		parent::__construct();
		error_reporting(E_ERROR|E_PARSE);

		$this->load->helper('order');
		$this->load->helper('shipping');
		$this->load->library('naverpaylib');
		$this->load->library('partnerlib');	
		$this->load->model('goodsmodel');
		$this->load->model('cartmodel');
		$this->load->model('categorymodel');
		$this->load->model('Goodsfblike');
		$this->load->model('configsalemodel');
		if($_COOKIE['shopReferer']){
			$this->load->model('referermodel');
		}
	}

    public function talkbuyOrderValidation(){

		$mode			= $this->input->get('mode');
		$goodsSeq		= $this->input->get('goodsSeq');
		$cart_option_seq= $this->input->get('cart_option_seq');

		// session id, mktime 세팅
		$this->partnerlib->init('talkbuy');

		// talkbuy 설정 정보 가져오기
		$talkbuyCfg = getTalkbuyConfig();

        // 장바구니 체크 및 주문 상품 가져오기.
		// 재고, 최소/최대 주문 수량 체크
        $cart       = $this->partnerlib->getPartnerOrderCart($goodsSeq, $cart_option_seq);

		// 재고, 최소/최대 주문 수량 체크
		$this->partnerlib->partnerOrderStockCheck($cart);

		// 주문 가능 상태 체크
    	$this->partnerlib->partnerOrderStatusCheck($talkbuyCfg, $cart['list']);
		$this->culture = $this->partnerlib->culture;

        return json_encode(["result" => "success", "message" => "Validation Check Success"]);
    }

	public function test(){
		echo $shopkey = base64_encode(160008);;
	}

	public function buy()
	{
		$this->load->library("sale");
		$this->load->library('shipping');
		$this->load->library('talkbuylibrary');
		$this->load->model('shippingmodel');
		$this->load->model('eventmodel');
		$this->load->helper('accountall');

		$skin_version	= $this->input->post('skin_version');
		$mode			= $this->input->post('mode');
		$goodsSeq		= $this->input->post('goodsSeq');
		$cart_option_seq= $this->input->post('cart_option_seq');
		$nation			= $this->input->post('nation');

		// talkbuy 설정 정보 가져오기
		$talkbuyCfg = getTalkbuyConfig();

		// session id, mktime 세팅
		$this->partnerlib->init('talkbuy');

		$cart_sort			= array();
		$cart_seq_list		= array();
		$total_sale_price	= array();
		$goods_sales		= array();
		$goods_ea			= array();
		$goods_shipping		= 0;
		$select_goods_cnt	= 0;

		$domain		= $_SERVER['HTTP_HOST'];
		$domain = get_connet_protocol().$domain;
		//-------------------------------------------------------------------------------
		// 할인정책 적용 관련(cart_seq)
		$return_cart_seq	= array();

        // 장바구니 체크 및 주문 상품 가져오기.
		// 재고, 최소/최대 주문 수량 체크
        $cart       = $this->partnerlib->getPartnerOrderCart($goodsSeq, $cart_option_seq);
		$cart_list  = $cart['list'];

		// 도서 공연비 소득 공제 대상 상품
    	$this->partnerlib->partnerOrderStatusCheck($talkbuyCfg, $cart['list']);
		$this->culture = $this->partnerlib->culture;


		if($nation)	$ship_ini['nation']	= $nation;
		$ship_ini	= array('nation' => "KOREA");
		$cart_sort 	= $this->goodsmodel->partnerOrderProducts($ship_ini, $cart);
		//-------------------------------------------------------------------------------
		# step2. cart_sort to XML
		$talkbuyData = [];

		$idx = 0;
		foreach($cart_sort as $cart_seq=>$cart_data){

			foreach($cart_data as $goods_seq=>$goods_data){

				foreach($goods_data as $cart_option_seq=>$data){
					
					// 기본배송비 20만원 제한
					if((int)$data['shipping_set']['std']['0']['shipping_cost'] > 200000) {
						echo json_encode(['result' => 'error', 'message' => '톡체크아웃 최대 배송비는 20만원 이하입니다.']);
						exit;
					}

					// 상품 가격 할인정보
					$data['product_sales_price'] = $this->talkbuylibrary->set_talkbuy_goods_sale($goods_seq);
					
					$shipping_packageId	= $data['shipping_set']['shipping_group_id'];			//배송그룹 ID
					$shipping_paytype	= $data['shipping_prepay_info'];		//배송비결제방법(선불,착불)

					// fm_partner_order_detail insert (할인내역 저장)
					if($goodsSeq > 0){
						$shipping_method = $shipping_paytype;
					}else{
						$shipping_method = $data['shipping_method'];
					}

					$arr = array("goods_seq"			=> $goods_seq,
								"mode"					=> $mode,
								"session_id"			=> $this->partnerlib->session_id,
								"mktime"				=> $this->partnerlib->mktime,
								"shipping_method"		=> $shipping_method,
								"shipping_packageId"	=> $shipping_packageId,
								"shipping_group_policy" => $shipping_group_policy,
								"shipping_paytype"		=> $shipping_paytype,
								"provider_seq"			=> $data['provider_seq'],
								"idx"					=> $idx,
								"shipping_set"			=> $data['shipping_set'],
								"tax"					=> $data['tax']
					);

					// 이미지 주소 http 포함여부 판단해서 full url 전달
					if( !preg_match('/http[s]*:\/\//',$data['image'])) {
						$data['image_url'] = $domain.$data['image'];
					}
					// 상품상세 주소
					$data['information_url'] = $domain."/goods/view?no=".$goods_seq;

					foreach($data['options'] as $opt_k=> &$option){
						$input_option = array();
						if($option['inputs']){
							foreach($option['inputs'] as $inp_k=>$inp){
								// 톡체크아웃 입력옵션은 최대 50자 까지 입력 가능하다.
								if (mb_strlen(trim($inp['input_value'])) > 50) {
									echo json_encode(['result' => 'error', 'message' => '톡체크아웃 입력 글자수는 최대 50자 이내 입니다.']);
									exit;
								}
								if(trim($inp['input_value']) == ''){
									$inp_value = "입력없음";
								}else{
									$inp_value = $inp['input_value'];
								}
								$data['options'][$opt_k]['inputs'][$inp_k]['input_value'] = $inp_value;
							}
						}

						// 세일이 있는 경우 구매 제한
						$unavailable_sale = $option['like_sale'] + $option['referer_sale'] + $option['mobile_sale'] + $option['multi_sale'];
						if($unavailable_sale > 0) {
							if($option['like_sale'] > 0) $sale_txt = '좋아요';
							if($option['referer_sale'] > 0) $sale_txt = '유입경로';
							if($option['mobile_sale'] > 0) $sale_txt = '모바일';
							if($option['multi_sale'] > 0) $sale_txt = '대량구매';
							$_except_msg = $sale_txt.' 할인 정책이 포함된 상품은 톡체크아웃으로 주문하실 수 없습니다.';
							echo json_encode(['result' => 'error', 'message' => $_except_msg]);
							exit;
						}
					}
					
					/*
					 * naverpaylib\option_xml_data 함수의 단일옵션/다중옵션 체크하는 부분과 동일하게 개선
					 * 2020-01-07
					 */
					// 기본값은 단일옵션으로 지정
					$data['opttype'] = 'single';
					// 옵션 수가 1개일 경우
					if(count($data['options']) === 1) {
					    $opt_tmp = reset($data['options']);
					    $input_cnt = 0;
					    if($opt_tmp['inputs']){
					        foreach($opt_tmp['inputs'] as $inp){
					            if(trim($inp['input_value'])) $input_cnt++;
					        }
					    }
					    
					    // option1, option2, option3 .. 값이 있고, 추가입력옵션을 사용할 경우 다중옵션
					    if($opt_tmp['option1'] || $opt_tmp['option2'] || $opt_tmp['option3'] || $opt_tmp['option4'] || $opt_tmp['option5'] || $input_cnt > 0) {
					        $data['opttype'] = 'multi';
					    }
					    
					} // 옵션 수가 1개 이상일 경우 다중옵션
					else if(count($data['options']) > 1) {
					    $data['opttype'] = 'multi';
					}

					try {

						$this->naverpaymodel->partner_order_detail("option", $data['options'], $arr, "talkbuy");
						$this->naverpaymodel->partner_order_detail("suboption", $data['suboptions'], $arr, "talkbuy");

						$talkbuyData[] = [
								'arr' 				=> $arr,
								'data' 				=> $data,
								'total_sale_price'	=> $total_sale_price
						];
						$idx++;

					} catch (\Exception $e) {
						$this->partnerlib->order_except_msg("ERROR :: ".$e->getMessage());
						exit;
					}
				}
			}
		}

		// 상품 정보로 return url 지정
		$this->partnerlib->return_url = $domain . "/order/cart";
		if ($goodsSeq) {
			$this->partnerlib->return_url = $domain . "/goods/view?no=" . $goodsSeq;
		}

		$data = array(
			'orderData' => array(
				'continuousUrl' => $this->partnerlib->return_url,
				'customData1' => $this->partnerlib->session_id."_".$this->partnerlib->mktime,
				'customData2' => '',			// 추가 커스텀 필드
				'mcstCultureBenefit' => $this->culture,
			),
			'productData' => $talkbuyData
		);

		echo json_encode(["result" => "success", "data" => $data]);
		//echo "<script> parent.talkbuyObj.talkbuyOrder(".json_encode($talkbuyData).");</script>";
	}

	/**
	 * 솔루션에서 톡체크아웃 중계서버 주문 호출
	 * 아직 미완성
	 */
	public function get_order_receive(){

		$talkbuyCfg = config_load("talkbuy");
		if($talkbuyCfg["use"] != "Y") exit;

		$this->load->library('talkbuy_sendlibrary');

		$params = array(
					"startDate"			=> date("Y-m-d H:i:s", strtotime('-1 day')),
					"endDate"			=> date("Y-m-d H:i:s")
				);
		$result = $this->talkbuy_sendlibrary->sendMethod("getOrders",$params);

		# 주문 처리 시작 -----
		if(!$contents || $contents['result'] == "error"){

			$message = ($contents['message'])?$contents['message']:"네이버페이 주문 수집 오류입니다.";
			openDialogAlert("<span class=fx12>".$message.'</span>',400,140,'parent',"parent.npay_order_receive_undisabled()");
			exit;

		}else{

			$orderdata	= $contents['ProductOrderResponse'];
			$order_etc	= $contents['OrderEtcInfo'];

			$result		= $this->naverpaymodel->set_order('',$orderdata,$order_etc);
			# 주문 처리 종료 -----

			# 주문번호 기준 총 건수
			$order_logs = $order_new = array();
			$log = array();
			foreach($result as $key => $data){

				if($key === "order_shipping_cost") continue;

				$log[] = $key . " : ".$data['order_seq'] ." :: ".$data['order_insert'];
				if($data['order_insert']){ $order_new[$data['order_seq']] = $data['order_seq']; }

				$order_logs[$data['order_seq']] = $data['order_seq'];
			}

			$order_cnt		= count($order_logs);
			$order_new_cnt	= count($order_new);

			# 중계서버에 상태 변경 시작 -----
			$this->naverpaylib->operation = "manual_order_receive";

			if($result){
				$result = serialize($result);
			}else{
				$result	= "";
			}
			$params = array("mode"			=> "order_receive_result",
							"server_info"	=> $server_info,
							"storeid"		=> $storeid,
							"result"		=> $result,
							"orderdata"		=> $return_orderdata,
							);
			$result		= $this->naverpaylib->send($params,"get_order");
			if($result == "ok"){
				$message = "총 ".number_format($order_cnt)."건";
				if(number_format($order_new_cnt) > 0){
					$message .= "(신규:".number_format($order_new_cnt)."건)";
				}
				$message .= "의 주문이 수집되었습니다.";
				openDialogAlert("<span class=fx12>".$message."</span>",400,140,'parent',"parent.location.reload()");
			}else{
				openDialogAlert("<span class=fx12>주문수집 실패하였습니다.</span>",400,140,'parent',"parent.location.reload()");
			}
			# 중계서버에 상태 변경 종료 -----
		}
	}
}