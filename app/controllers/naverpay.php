<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class naverpay extends front_base {

	# NPay V2.1
	public function __construct()
	{
		parent::__construct();
		error_reporting(E_ERROR|E_PARSE);

		$this->load->helper('order');
		$this->load->helper('shipping');
		$this->load->library('naverpaylib');
		$this->load->library('partnerlib');
		$this->load->model('naverpaymodel');
		$this->load->model('goodsmodel');
		$this->load->model('cartmodel');
		$this->load->model('categorymodel');
		$this->load->model('Goodsfblike');
		$this->load->model('configsalemodel');
		if($_COOKIE['shopReferer']){
			$this->load->model('referermodel');
		}
	}

	# NPay V2.1
	public function buy()
	{
		$this->load->library("sale");
		$this->load->library('shipping');
		$this->load->model('shippingmodel');
		$this->load->model('eventmodel');
		$this->load->helper('accountall');

		$skin_version	= $this->input->get('skin_version');
		$mode			= $this->input->get('mode');
		$goodsSeq		= $this->input->get('goodsSeq');
		$cart_option_seq= $this->input->post('cart_option_seq');
		$nation			= $this->input->post('nation');

		$this->naverpaylib->__construct('buy');
		// npay, talkbuy 설정 정보 가져오기
		$navercheckout	= $this->naverpaymodel->cfg_naverpay();

		// session id, mktime 세팅
		$this->partnerlib->init('npay');

		$cart_sort			= array();
		$cart_seq_list		= array();
		$total_sale_price	= array();
		$goods_sales		= array();
		$goods_ea			= array();
		$goods_shipping		= 0;
		$select_goods_cnt	= 0;

		//-------------------------------------------------------------------------------
		// 할인정책 적용 관련(cart_seq)
		$return_cart_seq	= array();

        // 장바구니 체크 및 주문 상품 가져오기.
        $cart       = $this->partnerlib->getPartnerOrderCart($goodsSeq, $cart_option_seq);
		$cart_list  = $cart['list'];

		// 재고, 최소/최대 주문 수량 체크
		$this->partnerlib->partnerOrderStockCheck($cart);

		// 주문 가능 상태 체크
		$this->partnerlib->partnerOrderStatusCheck($navercheckout, $cart_list);
		$this->naverpaylib->culture = $this->partnerlib->culture;

		// mode(direct/cart)에 따라 return url 지정 :: 2022-04-27 nsg
		$domain = $_SERVER['HTTP_HOST'];
		if (!strstr('http', $domain)) {
			$domain = get_connet_protocol() . $domain;
		}
		if ($mode == 'cart') {
			$this->naverpaylib->return_url = $domain . '/order/cart';
		} else {
			$this->naverpaylib->return_url = $domain . '/goods/view?no=' . $cart_list[0]['goods_seq'];
		}

		if($nation)	$ship_ini['nation']	= $nation;
		$ship_ini	= array('nation' => "KOREA");
		$cart_sort 	= $this->goodsmodel->partnerOrderProducts($ship_ini, $cart);
		//-------------------------------------------------------------------------------
		# step2. cart_sort to XML
		$goods_xml			= $goods_info = array();
		$return_cart_seq	= array_unique($return_cart_seq);
		$return_custom_code = urlencode(implode(",",$return_cart_seq));

		# CPA, 지식쇼핑유입 등의 광고 관련 코드
		$interface_info = array();
		$interface_info['merchantCustomCode1']	= base64_encode($this->partnerlib->session_id.'_'.$this->partnerlib->mktime);
		$interface_info['salesCode']			= $salesCode;
		// CPA 스크립트 가이드 설치업체는 해당 값 전달
		$interface_info['cpaInflowCode']		= urlencode($_COOKIE["CPAValidator"]);
		// 네이버마일리지 유입 경로 코드
		$interface_info['mileageInflowCode']	= "";
		// 네이버 서비스 유입 경로 코드
		$interface_info['naverInflowCode']		= urlencode($_COOKIE["NA_CO"]);
		//CTS 네이버검색광고 이용가맹점 중 전환데이터를 원할경우 SA URL파라미터중 NVADID를 입력
		$interface_info['saClickId']			= $_COOKIE['NVADID'];
		$this->naverpaylib->interface_info		= $interface_info;
		$idx = 0;
		foreach($cart_sort as $cart_seq=>$cart_data){

			foreach($cart_data as $goods_seq=>$goods_data){

				foreach($goods_data as $cart_option_seq=>$data){
					$cart_seq = $data['cart_seq'];

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


					foreach($data['options'] as $opt_k=>$option){
						$input_option = array();
						if($option['inputs']){
							foreach($option['inputs'] as $inp_k=>$inp){
								if(trim($inp['input_value']) == ''){
									$inp_value = "입력없음";
								}else{
									$inp_value = $inp['input_value'];
								}
								$data['options'][$opt_k]['inputs'][$inp_k]['input_value'] = $inp_value;
							}
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

						$this->naverpaymodel->partner_order_detail("option", $data['options'], $arr, 'npay');
						$this->naverpaymodel->partner_order_detail("suboption", $data['suboptions'], $arr, 'npay');

						list($goods_xml[],$goods_info_product[]) = $this->naverpaylib->product_xml_data($arr,$data,$total_sale_price);

						$idx++;

					} catch (\Exception $e) {
						openDialogAlert("ERROR :: ".$e->getMessage(), 450, 140, 'parent', '');
					}
				}
			}
		}

		# 배송그룹별 최종 배송비 확인. (배송비가 20만원 이상일 시 주문 불가)
		$return_shipping_price = array();
		foreach($goods_info_product as $k => $product){

			$return_ship_type = explode("@",$product['return_ship_type']);
			$return_shipping_price[$return_ship_type[0]] = $return_ship_type[2];

			unset($goods_info_product[$k]['return_ship_type']);
		}

		foreach($return_shipping_price as $shipp_price){
			if($shipp_price > 200000){
				//배송비 20만원 초과시 네이버페이 주문이 불가합니다.
				openDialogAlert(getAlert('os220'), 450, 140, 'parent', '');
				exit;
			}
		}

		// 장바구니에 네이버페이 마킹
		$this->load->library('cartlib');
		$this->cartlib->setCartMarking($cart_seq, "npay");

		//할인정보 검색용
		// session_id, referer_seq, event_seq
		$xml_data	= array();
		$xml_data[] = $this->naverpaylib->set_header();			// 인증정보 포함
		$xml_data[] = $this->naverpaylib->set_interface();		// 인터페이스정보
		$xml_data[] = implode("\n",$goods_xml);					// 주문상품
		$xml_data[] = $this->naverpaylib->set_footer();			// 인증정보
		$data = implode("\n",$xml_data);
		//-------------------------------------------------------------------------------
		//상품정보확인 URL 생성
		$goods_info_url = array();
		$goods_info_url['product'] = $goods_info_product;
		$goods_info_url['supplementSearch']		= "true";
		$goods_info_url['optionSearch']			= "true";
		$goods_info_url['merchantCustomCode1']	= base64_encode($this->partnerlib->session_id.'_'.$this->partnerlib->mktime);
		$goods_info = $this->naverpaylib->callback_goods_info($goods_info_url);
		//debug($goods_info);
		//-------------------------------------------------------------------------------
		# step3. navercheckout
		$this->naverpaylib->operation = "npay_buy";
		$param = $this->naverpaylib->send($data);

		if ($param[0] == "SUCCESS") { // 성공일 경우

			$requestParam = "/".$param[1]."/".$param[2];

		}else{

			if(!$param[1]) $message = "[NPay]503 Service Temporarily Unavailable"; else $message = $param[1];
			openDialogAlert($message,400,140,'parent');
			exit;
		}
		// 주문서 URL redirect
		$redirectUrl = $this->naverpaylib->targetLink.$requestParam;
		//-------------------------------------------------------------------------------
		//echo "<script type='text/javascript'>var naver = window.open(); naver.location.href='".$redirectUrl."';</script>";
		//exit;
		echo "<script type='text/javascript'>parent.location.href='".$redirectUrl."';</script>";
		exit;
	}

	# NPay V2.1
	public function npay_zzim_makeQueryString($data) {
		$ret .= 'ITEM_ID=' . urlencode($data['id']);
		$ret .= '&ITEM_NAME=' . urlencode($data['name']);
		$ret .= '&ITEM_DESC=' . urlencode($data['desc']);
		$ret .= '&ITEM_UPRICE=' . floor($data['uprice']);	// 네이버페이는 한화만 지원.
		$ret .= '&ITEM_IMAGE=' . urlencode($data['image']);
		$ret .= '&ITEM_THUMB=' . urlencode($data['thumb']);
		$ret .= '&ITEM_URL=' . urlencode($data['url']);
		return $ret;
	}

	# NPay V2.1
	# 네이버페이 찜 : 가이드상 상품 2개이상 가능하다 되어있으나 실제 1개 밖에 안됨.
	# 상품상세 : 사용
	# 장바구니 : 사용안함
	public function zzim()
	{

		$navercheckout	= config_load('navercheckout');
		$shopId			= $navercheckout['shop_id'];
		$certiKey		= $navercheckout['certi_key'];
		$queryString	= 'SHOP_ID='.urlencode($shopId);
		$queryString	.= '&CERTI_KEY='.urlencode($certiKey);
		$queryString	.= '&RESERVE1=&RESERVE2=&RESERVE3=&RESERVE4=&RESERVE5=';

		$goods = array();
		if($_POST['cart_option_seq']){

			$cart = $this->cartmodel->catalog('','','order');	//세번째인자값 : 주문 sale list 받아오기 위함.
			foreach($cart['list'] as $data){
				if(in_array($data['cart_option_seq'],$_POST['cart_option_seq'])){
					$goods[] = $data['goods_seq'];
				}
			}
		}else{
			$goods[]		= $_POST['goodsSeq'];
		}

		$domain = preg_replace("/^m\./","",$_SERVER['HTTP_HOST']);

		foreach($goods as $goods_seq){

			$data_goods		= $this->goodsmodel->get_goods($goods_seq);
			$data_images	= $this->goodsmodel->get_goods_image($goods_seq);
			$data_options	= $this->goodsmodel->get_goods_option($goods_seq);
			if($data_options)foreach($data_options as $k => $opt){
				if($k == 0) $uprice = $opt['price'];
				if($opt['default_option'] == 'y'){
					$data_goods['price'] = $opt['price'];
				}
			}

			$zzimgoods			= array();
			$zzimgoods['id']	= $data_goods["goods_seq"];
			$zzimgoods['name']	= strip_tags($data_goods['goods_name']);
			if($data_goods['summary']) $zzimgoods['desc']	= strip_tags($data_goods['summary']);
			if($data_goods['price'])	$zzimgoods['uprice'] = $data_goods['price'];
			//
			//$data_goods['goods_name']	= strip_tags($data_goods['goods_name']);
			//$name = $data_goods["goods_name"];

			if(strstr($data_images[1]['view']['image'],"http")){
				$zzimgoods['image']	= $data_images[1]['view']['image'];
			}else{
				$zzimgoods['image']	= "http://".$domain.$data_images[1]['view']['image'];
			}

			if(strstr($data_images[1]['list1']['image'],"http")){
				$zzimgoods['thumb']	= $data_images[1]['list1']['image'];
			}else{
				$zzimgoods['thumb']	= "http://".$domain.$data_images[1]['list1']['image'];
			}

			if(strstr($zzimgoods['image'],'https://')) $zzimgoods['image'] = str_replace("https://","http://",$zzimgoods['image']);
			if(strstr($zzimgoods['thumb'],'https://')) $zzimgoods['thumb'] = str_replace("https://","http://",$zzimgoods['thumb']);

			$zzimgoods['url'] = 'http://'.$domain."/goods/view?no=".$goods_seq;

			$queryString = $queryString.'&'.$this->npay_zzim_makeQueryString($zzimgoods);

			if($navercheckout['use']=='test'){
				$zzimUrl = 'https://test-pay.naver.com/customer/api/wishlist.nhn';
			}else{
				$zzimUrl = 'https://pay.naver.com/customer/api/wishlist.nhn';
			}

		}

		$cu = curl_init();
		curl_setopt($cu, CURLOPT_URL,$zzimUrl); // 데이터를 보낼 URL 설정
		curl_setopt($cu, CURLOPT_HEADER, FALSE);
		curl_setopt($cu, CURLOPT_FAILONERROR, TRUE);
		curl_setopt($cu, CURLOPT_HTTPHEADER, array('Content-type: application/x-www-form-urlencoded;charset=UTF-8'));
		curl_setopt($cu, CURLOPT_POST, 1); // 데이터를 get/post 로 보낼지 설정.
		curl_setopt($cu, CURLOPT_POSTFIELDS, $queryString); // 보낼 데이터를 설정. 형식은 GET 방식으로설정
		curl_setopt($cu, CURLOPT_RETURNTRANSFER, 1); // REQUEST 에 대한 결과값을 받을 것인지 체크.#Resource ID 형태로 넘어옴 :: 내장 함수 curl_errno 로 체크
		curl_setopt($cu, CURLOPT_TIMEOUT,60); // REQUEST 에 대한 결과값을 받는 시간 설정.
		curl_setopt($cu, CURLOPT_SSL_VERIFYPEER, 0); //
		curl_setopt($cu, CURLOPT_SSL_VERIFYHOST, 1); //
		$itemId = curl_exec($cu); // 실행

		//exit;
		if (curl_getinfo($cu, CURLINFO_HTTP_CODE) == 200) {
			$resultCode = 200;
			curl_close($cu);
		} else {
			echo('Response = '.curl_error($cu)."\n");
			curl_close($cu);
			exit(-1);
		}

		if($navercheckout['use']=='test'){
			if($this->_is_mobile_agent)
				$wishlistPopupUrl = "https://test-m.pay.naver.com/mobile/customer/wishList.nhn";
			else
				$wishlistPopupUrl = "https://test-pay.naver.com/customer/wishlistPopup.nhn";
		}else{
			if($this->_is_mobile_agent)
				$wishlistPopupUrl = "https://m.pay.naver.com/mobile/customer/wishList.nhn";
			else
				$wishlistPopupUrl = "https://pay.naver.com/customer/wishlistPopup.nhn";
		}

		$itemList = explode(",",$itemId);
		echo("<html>
		<body>
		<form name='frm' method='get' action='".$wishlistPopupUrl."'>
		<input type='hidden' name='SHOP_ID' value='".$shopId."'>
		");

		foreach($itemList as $itemId){
			echo "<input type='hidden' name='ITEM_ID' value='".$itemId."'>";
		}
		echo "
		</form>
		</body>
		";
		if ($resultCode == 200) {
		echo("<script>document.frm.target = '_top'; document.frm.submit();</script>
			");
		}
		echo("</html>");

	}

	## 중계서버로부터 주문 받기
	public function set_npay_order(){

		$aPostParams = $this->input->post();
		$contents = unserialize(base64_decode($aPostParams['npay_cont']), ['allowed_classes' => false]);

		$orderdata = $contents['ProductOrderResponse'];
		$order_etc = $contents['OrderEtcInfo'];

		$this->naverpaylib->result_message_log("npay_get_order",$aPostParams);
		$this->naverpaylib->result_message_log("npay_get_order",$contents);

		//대용량처리 이슈로 DB 재연결 추가 @2017-03-09
		//$this->db->close();
		//$this->db->reconnect();
		$result = $this->naverpaymodel->set_order('',$orderdata,$order_etc);

		if($result){
			print_r(serialize($result));
		}else{
			print_r('error\n');
			print_r($this->db->last_query());
		}
	}

	## 중계서버로부터 상품리뷰 받기
	public function set_npay_goodsreview(){

		$contents = unserialize(base64_decode($this->input->post('npay_cont')), ['allowed_classes' => false]);
		$result = $this->naverpaymodel->set_goods_review($contents);

		$this->naverpaylib->result_message_log("set_npay_goodsreview",$contents);

		if($result){
			$result = serialize($result);
		}

		echo $result;

	}

	## 중계서버로부터 판매자 QNA 받기
	public function set_npay_inquiry(){

		$contents = unserialize(base64_decode($this->input->post('npay_cont')), ['allowed_classes' => false]);
		$this->naverpaylib->result_message_log("set_npay_inquiry",$contents);
		$result = $this->naverpaymodel->set_customer_inquiry($contents);
		if($result){ $result = serialize($result); }
		echo $result;
	}


	//NPay 상품주문조회(특정 주문건)
	public function get_product_order_info(){

		if($_GET['product_order_id']){
			$product_order_id = explode(",",$_GET['product_order_id']);

			if(!is_array($product_order_id)){
				$product_order_id = array($_GET['product_order_id']);
			}

		}
		$sc = array(
				'operation'			=>'GetProductOrderInfoList',
				'product_order_id'	=> $product_order_id,
		);
		//주문상세 XML Request
		if($product_order_id){
			$xml_data	= $this->naverpaylib->get_product_orderinfo($sc);
			//주문상세 조회
			$inforesult	= $this->naverpaylib->send($xml_data['request'],'order');
			if($inforesult['ResponseType']){
				// 조회 실패 주문
				if($inforesult['FailedProductOrderIDList']){
					$this->naverpaylib->result_message_log('GetProductOrderInfoList_Fail',$inforesult['FailedProductOrderIDList']);
				//조회 성공 주문
				}elseif($inforesult['ProductOrderInfoList']){
					$result = $this->naverpaymodel->set_order($xml_data['timestamp'],$inforesult,$order_list);
					debug($result);
				}
			}else{
				debug("nothing");
			}
		}

		debug("order update end");
	}

	# NPay 주문조회(전체 - 수동)
	public function exec_order_receive($status='',$startdt='',$extradata=''){

		$status			= $_GET['TYPE'];
		$search_date	= $_GET['search_date'];
		$search_type	= $_GET['search_type'];

		if(!$search_date) $search_date = date("Y-m-d");

		$search_timestamp = mktime();

		$startdt	= $this->naverpaymodel->getLocaltimeToGMT(date("Y-m-d H:i:s",$search_timestamp-(60*60*3)));
		$enddt		= $this->naverpaymodel->getLocaltimeToGMT();

		if(!$status) $status = "";
		$sc = array(
				'startdt'		=>$startdt,
				'enddt'			=>$enddt,
				'extradata'		=>$extradata,
				'operation'		=>'GetChangedProductOrderList',
				'status'		=>$status,
		);

		// 주문리스트 XML Request 생성
		$xml_data = $this->naverpaylib->get_product_orderlist($sc);

		//Request 원문보기
		if($_GET){if($_GET['xml']){
			$this->naverpaylib->print_xml($xml_data);
			exit;
		}}

		// soap 통신 결과수신
		$listresult = $this->naverpaylib->send($xml_data,'order');

		if($listresult){

			// 전체 건수가 1000건이 넘을때.
			$returned_data_count	= $listresult['ReturnedDataCount'];		//한번에 조회한 건수(최대 1000건)
			$hasmoredata			= $listresult['HasMoreData'];			//조회건수가 1000건 이상일때 true
			$more_data_timefrom		= $listresult['MoreDataTimeFrom'];		//다음 데이터 주문시각
			$InquiryExtraData		= $listresult['InquiryExtraData'];		//다음 데이터 주문번호

			if($listresult['ResponseType'] == "SUCCESS"){

				//주문상세
				$orderlist = $product_order_id = array();
				if($listresult['ChangedProductOrderInfoList']['LastChangedDate']){
					$orderlist[] = $listresult['ChangedProductOrderInfoList'];
				}else{
					$orderlist	= $listresult['ChangedProductOrderInfoList'];
				}

				if($orderlist){

					$order_desc = array();

					foreach($orderlist as $data){

						//해당 주문 정보 update 여부(shop, npay 주문 상태 배교)
						$update_order	= false;
						$update_order	= $this->naverpaymodel->get_product_update_use($data,'','update_order');

						//주문(배송,취소,반품,교환 포함)상태가 다르거나, 배송지가 변경되었을때 주문 정보 업데이트
						if($data['OrderID'] == "2016022411144410"){
							//$data['IsReceiverAddressChanged'] = "true";
						}

						$update_use = false;
						if($update_order['order'] != 'non') $update_use = true;
						if($update_order['delivery'] != 'non') $update_use = true;
						if($update_order['cancel'] != 'non') $update_use = true;
						if($update_order['return'] != 'non') $update_use = true;
						if($update_order['refund'] != 'non') $update_use = true;
						if($update_order['exchange'] != 'non') $update_use = true;

						if($update_use || $data['IsReceiverAddressChanged'] == 'true'){
							$order_desc[$data['ProductOrderID']]['is_receiver_changed'] = $data['IsReceiverAddressChanged'];
							$order_desc[$data['ProductOrderID']]['last_change_date'] = substr(str_replace("T"," ",$data['LastChangedDate']),0,19);;
							$product_order_id[] = $data['ProductOrderID'];
						}
					}

					//주문상세 XML Request
					if($product_order_id){

						$sc = array(
								'operation'			=>'GetProductOrderInfoList',
								'product_order_id'	=> $product_order_id,
						);
						$xml_data	= $this->naverpaylib->get_product_orderinfo($sc);
						//주문상세 조회
						$inforesult	= $this->naverpaylib->send($xml_data['request'],'order');

						if($inforesult['ResponseType']){
							// 조회 실패 주문
							if($inforesult['FailedProductOrderIDList']){
								$this->naverpaylib->result_message_log('GetProductOrderInfoList_Fail',$inforesult['FailedProductOrderIDList']);
							//조회 성공 주문
							}elseif($inforesult['ProductOrderInfoList']){
								$res = $this->naverpaymodel->set_order($xml_data['timestamp'],$inforesult,$order_desc);
								debug($res);
							}
						}
					}

				}

			}

			//주문데이터가 1000건 이상 더 존재한다면 추가 주문건 재귀호출
			if($hasmoredata == "true" && $InquiryExtraData){
				echo "추가호출 ";
				$this->exec_order_receive($more_data_timefrom,$InquiryExtraData);
			}
			//echo $result;
		}
	}

	# 주문수집 : 중계서버로부터 수동 수집
	public function get_order_receive(){

		# 중계서버에서 주문 가져오기 시작 -----
		if($this->naverpaylib->cfg_naverpay['use'] == "y"){
			$server_info = "ec";
		}elseif($this->naverpaylib->cfg_naverpay['use'] == "test"){
			$server_info = "sendbox";
		}else{
			exit;
		}

		$storeid = $this->naverpaylib->cfg_naverpay['shop_id'];

		$this->naverpaylib->operation = "manual_order_receive";

		$params = array("mode"			=> "order_receive",
						"server_info"	=> $server_info,
						"storeid"		=> $storeid
						);
		$result		= $this->naverpaylib->send($params,"get_order");
		$contents	= unserialize($result);
		$return_orderdata = $result;
		# 중계서버에서 주문 가져오기 종료 -----

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
