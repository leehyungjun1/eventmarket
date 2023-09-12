<?php

Class OrderService  extends ServiceBase
{

	protected $_ServiceName		= 'order';
	public $orderStatus;
	
	public function __construct($params = array())
	{
		parent::__construct($params);
		$this->_CI->load->model('connectormodel');
		$this->orderStatus	= $this->getStatusCode('order');
		
		/*2017-12-06 샵링커 마켓 설정 추가*/
		$find			= $this->_CI->db->query("SELECT * FROM fm_market_account WHERE market LIKE 'API%' and delete_yn = 'N' and account_use = 'Y' group by market");
		$findRow		= $find->result();
		
		$this->_CI->load->model('connectormodel');
		$connectorModel		=& $this->_CI->connectormodel;
		foreach ($findRow as $val){
			$getParam['searchMarket'] = $val->market;
			$rtn = $connectorModel->getLinkageMarket($getParam);
			$this->_supportMarkets[$val->market]['name'] = $rtn[0]['marketName'];
			$this->_supportMarkets[$val->market]['productLink'] = '';
		}
		
		/*2017-12-06 샵링커 마켓 설정 추가*/
		
		// 주문 로그 생성을 위한 마켓 정보 얻기
		$_connectorBase	= $this->_CI->connector::getInstance();
		$this->_supportMarketNames	= $_connectorBase->getAllMarkets();
	}

	public function getServiceName(){ return $this->_ServiceName; }


	/* 마켓 주문등록 */
	public function marketOrderInseart($market, $sellerId, $orderList)
	{

		$savedCnt		= 0;
		$orderCnt		= 0;
		$skipCnt		= 0;
		$insertSeqList	= array();

		//결제확인 주문 처리
		foreach((array)$orderList as $orderRow) {
			if($orderRow['marketDeliveryNo'] == ''){
				$marketDeliveryNoQuery = "AND	(market_order_seq	= '{$orderRow['marketOrderSeq']}' OR isnull(market_order_seq))";
			}else{
				$marketDeliveryNoQuery = "AND	market_order_seq	= '{$orderRow['marketOrderSeq']}' AND market_delivery_no	= '{$orderRow['marketDeliveryNo']}' ";
			}
			
			$countSql	= "
				SELECT	market_order_seq
				FROM	fm_market_orders
				WHERE	market_order_no		= '{$orderRow['marketOrderNo']}'
					{$marketDeliveryNoQuery}
					AND	market_order_seq	= '{$orderRow['marketOrderSeq']}'
					AND	market				= '{$market}'
					AND	seller_id			= '{$sellerId}'";
			
			$result		= $this->_CI->db->query($countSql);

			if ($result->num_rows() > 0) {
				$skipCnt ++;
				continue;
			}


			$savedCnt++;

			unset($setParams);

			$setParams['market']					= $market;
			$setParams['seller_id']					= $sellerId;
			$setParams['market_order_no']			= $orderRow['marketOrderNo'];
			$setParams['market_order_seq']			= $orderRow['marketOrderSeq'];
			$setParams['market_delivery_no']		= $orderRow['marketDeliveryNo'];
			$setParams['bundle_shipping_yn']		= ($orderRow['bundleShippingYn'] == 'Y') ? 'Y' : 'N';
			$setParams['bundle_shipping_no']		= $orderRow['bundleShippingNo'];
			$setParams['seller_product_code']		= $orderRow['sellerProductCode'];
			$setParams['market_product_code']		= $orderRow['marketProductCode'];
			$setParams['order_product_name']		= $orderRow['orderProductName'];
			$setParams['market_option_code']		= $orderRow['marketOptionCode'];
			$setParams['order_option_name']			= $orderRow['orderOptionName'];
			$setParams['add_product_yn']			= ($orderRow['addProductYn'] == 'Y') ? 'Y' : 'N';
			$setParams['add_product_code']			= $orderRow['addProductCode'];
			$setParams['market_order_status']		= $orderRow['marketOrderStatus'];
			$setParams['order_qty']					= $orderRow['orderQty'];
			$setParams['order_product_price']		= $orderRow['orderProductPrice'];
			$setParams['order_amount']				= $orderRow['orderAmount'];
			$setParams['paid_amount']				= $orderRow['paidAmount'];
			$setParams['seller_discount_amount']	= $orderRow['sellerDiscountAmount'];
			$setParams['market_discount_amount']	= $orderRow['marketDiscountAmount'];
			$setParams['global_shipping_yn']		= ($orderRow['globalShippingYn'] == 'Y') ? 'Y' : 'N';
			$setParams['shipping_method']			= $orderRow['shippingMethod'];
			$setParams['shipping_type']				= $orderRow['shippingType'];
			$setParams['shipping_cost']				= $orderRow['shippingCost'];
			$setParams['extra_shipping_cost']		= $orderRow['extraShippingCost'];
			$setParams['delivery_message']			= $orderRow['deliveryMessage'];
			$setParams['orderer_name']				= $orderRow['ordererName'];
			$setParams['orderer_zipcode']			= $orderRow['ordererZipcode'];
			$setParams['orderer_address']			= $orderRow['ordererAddress'];
			$setParams['orderer_address_detail']	= $orderRow['ordererAddressDetail'];
			$setParams['orderer_cellphone']			= $orderRow['ordererCellphone'];
			$setParams['orderer_tel']				= $orderRow['ordererTel'];
			$setParams['recipient_name']			= $orderRow['recipientName'];
			$setParams['recipient_zipcode']			= $orderRow['recipientZipcode'];
			$setParams['recipient_address']			= $orderRow['recipientAddress'];
			$setParams['recipient_address_detail']	= $orderRow['recipientAddressDetail'];
			$setParams['recipient_cellphone']		= $orderRow['recipientCellphone'];
			$setParams['recipient_tel']				= $orderRow['recipientTel'];
			$setParams['order_time']				= trim($orderRow['orderTime'])? $orderRow['orderTime']:$orderRow['settleTime'];
			$setParams['settle_time']				= $orderRow['settleTime'];
			$setParams['other_info']				= json_encode($orderRow['otherInfo']);
			$setParams['registered_time']			= date('Y-m-d H:i:s');
			$setParams['renewed_time']				= $setParams['registered_time'];
			$setParams['fm_order_save_time']		= '0000-00-00 00:00:00';
			$setParams['openmarket_order_id']       = (!empty($orderRow['otherInfo']['openmarketOrderId']) ? $orderRow['otherInfo']['openmarketOrderId'] : null);

			$setData	= filter_keys($setParams, $this->_CI->db->list_fields('fm_market_orders'));


			//주문서 등록
			$this->_CI->db->insert('fm_market_orders', $setData);
			$insertSeqList[]	= $this->_CI->db->insert_id();
		
		}

		$return['success']	= 'Y';
		$return['message']	= '총 수집주문 '.number_format($orderCnt).' 개 / ';
		$return['message']	.= '이전 수집된 주문 '.number_format($skipCnt).' 개 / ';
		$return['message']	.= '저장된 주문 '.number_format($savedCnt).' 개';
		$return['seqList']	= $insertSeqList;

		return $return;
	}


	/* 마켓 주문 퍼스트몰등록 */
	public function orderMoveToFmOrder($params) {
		$this->_CI->load->model('ordermodel');
		$this->_CI->load->model('goodsmodel');
		$this->_CI->load->model('categorymodel');
		$this->_CI->load->model('shippingmodel');
		$this->_CI->load->model('providermodel');
		$this->_CI->load->helper('order');
		$this->_CI->load->helper('accountall');
		$this->_CI->load->library('shipping');

		$orderModel		=& $this->_CI->ordermodel;
		$goodsModel		=& $this->_CI->goodsmodel;
		$categoryModel	=& $this->_CI->categorymodel;
		$connectorModel	=& $this->_CI->connectormodel;
		$shippingModel	=& $this->_CI->shippingmodel;
		$providerModel	=& $this->_CI->providermodel;

		if (count($params['seqList']) < 1)
			return;

		$ableRegistStatus				= array('ORD10', 'ORD20');
		$orderParams['seqList']			= $params['seqList'];
		$orderParams['hasFmOrderSeq']	= 'N';

		$marketDeliveryList				= $connectorModel->getMarketOrderList($orderParams, 'delivery');


		$shippingMethodList['delivery']	= '택배';
		$shippingMethodList['direct']	= '직접배송';
		$shippingMethodList['quick']	= '퀵서비스';
		$shippingMethodList['postpaid']	= '착불';
		
		$_GET['mode']					= 'market';
		
		$fmGoodsOption			= array();
		$fmGoodsSuboption		= array();
		$fmGoodsInputOption		= array();
		$errorOrderList			= array();
		$marketOrderStatusList	= array();
		$shippingInfoList		= array();

		foreach((array)$marketDeliveryList as $row) {

			if (isset($marketOrderStatusList[$row['market_order_no']][$row['market_delivery_no']]) !== true) {
				$this->setMarketInfo($row['market'], $row['seller_id']);
				
				if(stripos($row['market'],"API") !== false){
					$MarketLinkage	= config_load('MarketLinkage');
					$postVal['request']['shoplinkerId'] = $MarketLinkage['shoplinkerId'];
					
					if($row['market_delivery_no'] == '')
						$row['market_delivery_no'] = 'null';
				}else{
					$postVal =  false;
				}
				

				$marketOrderStatusList[$row['market_order_no']][$row['market_delivery_no']]		= $this->callConnector("Order/getOrderStatus/{$row['market_order_no']}/{$row['market_delivery_no']}",$postVal);
			}
			
			$marketOrderStatus			= $marketOrderStatusList[$row['market_order_no']][$row['market_delivery_no']];

			if ($marketOrderStatus['success'] != 'Y') {
				$errorOrderList['order']	= "{$row['market_order_no']} 주문조회 실패";
				continue;
			}


			//주소확인 및 갱신
			if(stripos($row['market'],"API") !== false){
				foreach ($marketOrderStatus['resultData']['orderRecipientInfo'] as $nowDeliveryInfo) {
					if ($nowDeliveryInfo['marketOrderNo'] == $row['market_order_no']) {
						$newlyRecipient		= $nowDeliveryInfo;
						continue;
					}
				}
			}else{
				foreach ($marketOrderStatus['resultData']['orderRecipientInfo'] as $nowDeliveryInfo) {
					if ($nowDeliveryInfo['marketDeliveryNo'] == $row['market_delivery_no']) {
						$newlyRecipient		= $nowDeliveryInfo;
						continue;
					}
				}
			}
			

			$checkNewlyList		= array('recipientZipcode', 'recipientAddressDetail', 'recipientName', 'recipientCellphone', 'recipientTel');
			$recipientChanged	= false;

			for($cnt = count($checkNewlyList), $i = 0; $i < $cnt; $i++) {
				
				$targetKey		= $checkNewlyList[$i];
				$originalKey	= strtolower(preg_replace('/([A-Z]+)/', "_$1", $targetKey));
				
				$newlyVal		= trim(preg_replace('/[\s+|\-+|]/', '', $newlyRecipient[$targetKey]));
				$nolVal			= trim(preg_replace('/[\s+|\-+|]/', '', $row[$originalKey]));
				
				if ($newlyVal != $nolVal) {
					$recipientChanged	= true;
					break;
				}
			}
			

			// 배송지 변경된경우 수정
			if ($recipientChanged === true) {
				
				$row['recipient_name']						= $newlyRecipient['recipientName'];
				$row['recipient_zipcode']					= $newlyRecipient['recipientZipcode'];
				$row['recipient_address']					= $newlyRecipient['recipientAddress'];
				$row['recipient_address_detail']			= $newlyRecipient['recipientAddressDetail'];
				$row['recipient_cellphone']					= $newlyRecipient['recipientCellphone'];
				$row['recipient_tel']						= $newlyRecipient['recipientTel'];

				$renewRecipient								= array();
				$renewRecipient['recipient_name']			= $newlyRecipient['recipientName'];
				$renewRecipient['recipient_zipcode']		= $newlyRecipient['recipientZipcode'];
				$renewRecipient['recipient_address']		= $newlyRecipient['recipientAddress'];
				$renewRecipient['recipient_address_detail']	= $newlyRecipient['recipientAddressDetail'];
				$renewRecipient['recipient_cellphone']		= $newlyRecipient['recipientCellphone'];
				$renewRecipient['recipient_tel']			= $newlyRecipient['recipientTel'];
				$renewRecipient['renewed_time']				= date('Y-m-d H:i:s');
				$renewRecipient['last_message']				= '주소가 변경되었습니다.';
				
				$seqListArr			= explode(',', $row['seq_list']);
				foreach ((array)$seqListArr as $moSeq)
					$this->_CI->db->update('fm_market_orders', $renewRecipient, array('seq'=>$moSeq));
			}

			//주문상태 확인
			$orderItemStatus	= $marketOrderStatus['resultData']['orderItemStatus'];
			$renewedTime		= date('Y-m-d H:i:s');
			foreach((array)$orderItemStatus as $newlyItemVal) {

				$renewParams['order_cancel_qty']		= 0;

				
				$renewParams['order_cancel_qty']		= $newlyItemVal['cancelQty'];
				$renewParams['order_qty']				= $newlyItemVal['orderQty'];
				$renewParams['market_order_status']		= $newlyItemVal['marketOrderStatus'];
				$renewParams['order_amount']			= $newlyItemVal['orderAmount'];
				$renewParams['paid_amount']				= $newlyItemVal['paidAmount'];
				$renewParams['seller_discount_amount']	= $newlyItemVal['sellerDiscountAmount'];
				$renewParams['market_discount_amount']	= $newlyItemVal['marketDiscountAmount'];
				$renewParams['shipping_cost']			= $newlyItemVal['shippingCost'];
				$renewParams['extra_shipping_cost']		= $newlyItemVal['extraShippingCost'];
				$renewParams['renewed_time']			= $renewedTime;

				if ($newlyItemVal['marketOrderStatus'] == 'CAN10')
					$renewParams['last_message']		= '취소된 주문입니다.';
				
				$updateWhere['fm_order_seq']			= $row['fm_order_seq'];
				$updateWhere['market_delivery_no']		= $newlyItemVal['marketDeliveryNo'];
				$updateWhere['market_product_code']		= $newlyItemVal['marketProductCode'];
				$updateWhere['market_order_seq']		= $newlyItemVal['marketOrderSeq'];

				$this->_CI->db->update('fm_market_orders', $renewParams, $updateWhere);
			}

			unset($_POST);
			(trim($row['orderer_tel']) == '')			? $row['orderer_tel'] = '--' : '';
			(trim($row['order_cellphone']) == '')		? $row['order_cellphone'] = '--' : '';
			(trim($row['recipient_tel']) == '')			? $row['recipient_tel'] = '--' : '';
			(trim($row['recipient_cellphone']) == '')	? $row['recipient_cellphone'] = '--' : '';
			(trim($row['orderer_email']) == '')			? $row['orderer_email'] = '' : '';

			$_POST['payment']				= 'bank';
			$_POST['depositor']				= $row['orderer_name'];
			$_POST['order_user_name']		= $row['orderer_name'];
			$_POST['order_phone']			= explode('-', $row['orderer_tel']);
			$_POST['order_cellphone']		= explode('-', $row['orderer_cellphone']);
			$_POST['order_email']			= $row['orderer_email'];
			$_POST['recipient_user_name']	= $row['recipient_name'];
			$_POST['recipient_phone']		= explode('-', $row['recipient_tel']);
			$_POST['recipient_cellphone']	= explode('-', $row['recipient_cellphone']);
			$_POST['memo']					= $row['delivery_message'];


			//@20190729 샵링커, 오픈마켓(쿠팡/스마트스토어/11번가) API 상 배송타입 정의값 없음.
			//중계서버에서도 모두 '택배'로 전달함
			$shipping_method	= 'delivery';

			if($row['global_shipping_yn'] != 'Y') {
				//$shippingMethodText					= ($row['shipping_type'] == 'postpaid') ? '착불' : $row['shipping_type'];
				//$shippingMethod						= array_search($shippingMethodText, $shippingMethodList);
				$shippingArea						= 'domestic';

				$_POST['international']				= '0';
				$_POST['shipping_method']			= $shipping_method;
				$_POST['recipient_new_zipcode']		= $row['recipient_zipcode'];
				$_POST['recipient_address_type']	= 'street';
				$_POST['recipient_address']			= $row['recipient_address'];
				$_POST['recipient_address_street']	= $row['recipient_address'];
				$_POST['recipient_address_detail']	= $row['recipient_address_detail'];
				$_POST['shipping_cost']				= $row['shipping_cost'];
			} else {
				//$shippingArea						= 'international';
				// 국제 배송은 추가 확인 필요
				$_POST['international']					= '1';
				$_POST['shipping_method_international']	= $row[''];
				$_POST['region']						= $row[''];
				$_POST['international_address']			= $row[''];
				$_POST['international_town_city']		= $row[''];
				$_POST['international_county']			= $row[''];
				$_POST['international_postcode']		= $row['']; 
				$_POST['international_country']			= $row[''];
				$_POST['international_cost']			= $row[''];
				$_POST['delivery_cost']					= $row[''];
				$errorOrderList['order']	= "{$row['market_order_no']} 해외 배송은 지원되지 않습니다.";
				continue;
			}
			
			// 스마트스토어 개인통관부호 처리 :: 2018-04-10 lkh
			$otherInfo									= json_decode($row['other_info'],true);
			if($otherInfo['ProductOrder']['IndividualCustomUniqueCode']){
				$_POST['clearance_unique_personal_code']		= $otherInfo['ProductOrder']['IndividualCustomUniqueCode'];
			}

			///////////// 주문 번호 생성 /////////////////////////////////////////////////
			/* fm_order_insert 부터 transaction 추가 */ 
			$this->_CI->db->trans_begin();

			$orderParams['settle_price']		= 0;
			$orderParams['shipping_cost']		= 0;
			$orderParams['shipping_order']		= $this->shipping_order;
			$orderParams['pgCompany']			= $pgCompany;
			$orderParams['krw_exchange_rate']	= get_exchange_rate("KRW");		//원화(KRW) 환율정보
			$orderSeq							= $orderModel->insert_order($orderParams);
			$packageYn							= 'N';
			//////////////////////////////////////////////////////////////////////////////

			/**
			* 정산개선 배열초기화
			* @ accountallmodel
			**/
			$account_ins_shipping	= array();
			$account_ins_opt		= array();
			$account_ins_subopt		= array();

			// 배송비 처리용
			$orderSeqList				= explode(',', $row['seq_list']);
			$shippingGroupSeqArr		= explode(',', $row['shipping_group_seq_list']);
			$shippingCostArr			= explode(',', $row['shipping_cost_list']);
			$shippingTypeArr			= explode(',', $row['shipping_type_list']);
			$extraShippingCostArr		= explode(',', $row['extra_shipping_cost_list']);
			$marketProductArr			= explode(',', $row['market_product_list']);
			$shippingProviderArr		= explode(',', $row['provider_seq_list']);
			$trustShippingArr			= explode(',', $row['trust_shipping_list']);
			$shippingGroupList			= array();
			$productShippingCodeList	= array();


			for( $i = 0; $i < count($orderSeqList) ; $i++ ) {

				// 마켓명_주문번호
				//if($row['market_delivery_no'] == 'null') $row['market_delivery_no'] = '';
				$shipping_group = $shippingProviderArr[$i].'_'.$row['market'].'_'.$row['market_order_no'].'_'.$row['market_delivery_no'];

				if (isset($shippingGroupList[$shipping_group]) === true) {
					$shippingSeqList[$orderSeqList[$i]]				= $shippingGroupList[$shipping_group];
					continue;
				}

				$insert_params = array();
				$provider_seq						= $shippingProviderArr[$i];
				$insert_params['shipping_group']	= $shipping_group;
				$insert_params['shipping_method']	= $shipping_method;

				/* 묶음배송일 경우 제일 높은 배송비 금액으로 재 정의 */
				if($row['max_shipping_cost'] > 0) {
					$row['shipping_cost'] = $row['max_shipping_cost'];
				}

				/*
				 * shipping_type : 선불(prepay), 착불(postpaid), 무료(free)
				 * shipping_method : 택배(delivery), 직접배송(direct), 퀵서비스(quick)
				 */
				if($row['shipping_type'] == '착불'){
					$shipping_type = "postpaid";
				}elseif($row['shipping_type'] == '무료'){
					$shipping_type = "free";
				}else{
					$shipping_type = "prepay";
				}
				/* 배송비에 따라 배송 방식 한번 더 체크(단,착불은 제외) */
				if($row['shipping_cost'] <= 0 && $shipping_type != "postpaid"){
					$insert_params['shipping_type'] = 'free';
				}else{
					$insert_params['shipping_type'] = $shipping_type;
				}

				$insert_params['order_seq']			= $orderSeq;
				$insert_params['provider_seq']		= $provider_seq;
				
				// 입점사상품이 위탁배송인경우에는 배송주체는 본사 2020-06-11
				if($provider_seq > 1 && $trustShippingArr[$i]=="Y") {
					$insert_params['provider_seq'] = '1';
				}

				$real_shipping_cost					= $row['shipping_cost'];
				$insert_params['shipping_cost']		= $real_shipping_cost;
				$insert_params['postpaid']			= 0;

				// 선착불 정보 재 정의 :: 2016-08-08 lwh
				if(preg_match('/postpaid/',$shipping_type)){
					$insert_params['shipping_cost'] = 0;
					$insert_params['postpaid']		= $real_shipping_cost;
				}

				$insert_params['delivery_cost']		= $row['shipping_cost'];
				$insert_params['add_delivery_cost'] = $row['extra_shipping_cost'];
				$insert_params['hop_delivery_cost'] = 0;

				# 원화기준 실배송비  @2016-11-01
				if($insert_params['shipping_cost'] > 0){
					$insert_params['shipping_cost_krw'] = get_currency_exchange($insert_params['shipping_cost'],"KRW",$this->config_system['basic_currency']);
				}else{
					$insert_params['shipping_cost_krw'] = '0';
				}

				// 주문당시 배송 설정명 저장 :: 2016-09-23 lwh
				$insert_params['shipping_set_name']	= '오픈마켓 배송';

				// 주문당시 반품/교환 배송비 저장 :: 2018-05-15 lwh
				$insert_params['refund_shiping_cost']	= 0;
				$insert_params['swap_shiping_cost']		= 0;
				$insert_params['shiping_free_yn']		= 0;

				$this->_CI->db->insert('fm_order_shipping', $insert_params);
				$shippingSeq = $this->_CI->db->insert_id();

				/**
				* 정산개선 - 배송처리 : 순서변경주의 시작
				* data : 주문정보
				* insert_params : 배송정보
				* @ accountallmodel
				**/
				$shipping_charge = "";
				$return_shipping_charge = "";
				if	($provider_seq > 1){
					$provider	= $providerModel->get_provider_one($provider_seq);
					$shipping_charge		= $provider['shipping_charge'];
					$return_shipping_charge	= $provider['return_shipping_charge'];
				}
				$shippingInfo['order_form_seq']			= $shippingSeq;
				$shippingInfo['shipping_seq']			= $shippingSeq;
				$shippingInfo['shipping_charge']		= $shipping_charge;
				$shippingInfo['return_shipping_charge']	= $return_shipping_charge;
				$shippingInfo['accountallmodeltest']	= "accountallmodeltest_ship";
				$account_ins_shipping[$shippingSeq] = $shippingInfo;
				/**
				* 정산개선 - 배송처리 : 순서변경주의 끝
				* data : 주문정보
				* insert_params : 배송정보
				* @
				**/

				$shippingGroupList[$shipping_group]		= $shippingSeq;
				$shippingSeqList[$orderSeqList[$i]]		= $shippingSeq;
			}

			$nowParams['seqList']	= $orderSeqList;
			$nowOrderList			= $connectorModel->getMarketOrderList($nowParams, 'withGoodsFull');

			$allMachedProduct		= true;
			$orderItemList			= array();
			$orderItemOptionList	= array();
			$totalTaxfreeAmount		= 0;
			$orderItemCnt			= 0;
			$settleAmount			= 0;
			$shippingCost			= 0;
			
			foreach((array)$nowOrderList as $key => $orderRow) {
				$nowSubOptionSeq	= 0;
				if ($orderRow['package_yn'] == 'y')
					$packageYn		= 'Y';

				//매칭 옵션명이 있을경우 매칭 옵션명으로 처리
				if (trim($orderRow['matched_option_name']) != '')
					$orderRow['order_option_name']	= $orderRow['matched_option_name'];

				//단일옵션명 '' 처리 
				$orderRow['order_option_name'] = $this->optionNameException($orderRow['market'],$orderRow['order_option_name']);

				$productType		= ($orderRow['add_product_yn'] == 'Y') ? '추가옵션' : '상품';

				//매칭 안된 상품이 있는지 확인
				if (!$orderRow['fm_goods_seq'] || !$orderRow['goods_seq']) {
					$errorOrderList[$orderRow['seq']]	= "\"[{$orderRow['market_order_no']}] {$orderRow['order_product_name']}\" {$productType}은 미매칭 상태입니다.";
					$allMachedProduct	= false;
					continue;
				}

				//취소 수량 확인(추후 부분취소를 위해 코드가 아닌 수량으로 확인
				$orderRow['order_qty']	= $orderRow['order_qty'] - $orderRow['order_cancel_qty'];
				
				if ($orderRow['order_qty'] < 1) {
					$errorOrderList[$orderRow['seq']]	= "\"[{$orderRow['market_order_no']}] {$orderRow['order_product_name']}\" {$productType}은 취소된 상품입니다.";
					continue;
				}

				
				if (array_search($orderRow['market_order_status'], $ableRegistStatus) === false) {
					//결제완료 / 배송준비중 상태만 주문 저장 가능
					$nowStatusText	= $this->orderStatus[$orderRow['market_order_status']];
					$errorOrderList[$orderRow['seq']]	= "\"[{$orderRow['market_order_no']}] 결제완료, 배송준비중 상태의 주문만 등록이 가능합니다. (상태 - {$nowStatusText})";
					continue;
				}
				
				// 상품정보 등록
				if (isset($orderItemList[$orderRow['market_product_code']]) !== true) {

					$itemParams									= array();
					$itemParams['provider_seq']					= $orderRow['provider_seq'];
					$itemParams['shipping_seq']					= $shippingSeqList[$orderRow['seq']];
					$itemParams['order_seq']					= $orderSeq;

					$itemParams['goods_seq']					= $orderRow['fm_goods_seq'];
					$itemParams['goods_code']					= $orderRow['fm_goods_code'];
					$itemParams['image']						= $orderRow['fm_goods_image'];
					$itemParams['goods_name']					= $orderRow['fm_goods_name'];
					$itemParams['tax']							= $orderRow['tax'];
					$itemParams['adult_goods']					= $orderRow['adult_goods'];
					$itemParams['goods_type']					= $orderRow['goods_type'];
					$itemParams['reservation_ship']				= 'n';
					$itemParams['multi_discount_ea']			= 0;
					$itemParams['goods_kind']					= 'goods';

					$itemParams['individual_refund']			= ($orderRow['individual_refund']) ? $orderRow['individual_refund'] : '0';
					$itemParams['individual_refund_inherit']	= ($orderRow['individual_refund_inherit']) ? $orderRow['individual_refund_inherit'] : '0';
					$itemParams['individual_export']			= ($orderRow['individual_export']) ? $orderRow['individual_export'] : '0';
					$itemParams['individual_return']			= ($orderRow['individual_return']) ? $orderRow['individual_return'] : '0';

					if ($orderRow['global_shipping_yn'] != 'Y'){
						$itemParams['shipping_policy']			= 'shop';
						$itemParams['basic_shipping_cost']		= $orderRow['shipping_cost'];
						$itemParams['add_shipping_cost']		= $orderRow['extra_shipping_cost'];
					} else {
						$itemParams['shipping_policy']			= 'shop';
					}


					$this->_CI->db->insert('fm_order_item', $itemParams);

					$nowItemSeq	= $this->_CI->db->insert_id();
					$orderItemList[$orderRow['market_product_code']]	= $nowItemSeq;

				}
				

				if ($orderItemList[$orderRow['market_product_code']] < 1)
					continue;

				if ($orderRow['add_product_yn'] != 'Y') {
					
					// 상품별로 singleOption 체크 하도록 수정 2018-08-09
					if(!array_key_exists($orderRow['fm_goods_seq'],$singleOption)) {
						$singleOption[$orderRow['fm_goods_seq']] = false;
					}

					if (isset($fmGoodsOption[$orderRow['fm_goods_seq']]) === false) {
						$fmOptionInfo		= $goodsModel->get_goods_option($orderRow['fm_goods_seq']);
						$fmInputInfo		= $goodsModel->get_goods_input($orderRow['fm_goods_seq']);

						// 옵션이 없는 상품 확인
						if (count($fmOptionInfo) == 1 && trim($fmOptionInfo[0]['option_title']) == '')
							$singleOption[$orderRow['fm_goods_seq']]	= true;


						foreach ((array)$fmInputInfo as $inputVal) {
							$tmpMatchText	= $inputVal['input_name'];
							if (strlen($tmpMatchText) < 1)
								continue;

							$inputKey		= md5($this->forMatchingOptionText($tmpMatchText));
							$nowFmInput['input_seq']	= $inputVal['input_seq'];
							$nowFmInput['input_name']	= $inputVal['input_name'];
							$nowFmInput['input_form']	= $inputVal['input_form'];
							
							$fmGoodsInputOption[$orderRow['fm_goods_seq']][$inputKey]	= $nowFmInput;
						}

						$fmGoodsOption[$orderRow['fm_goods_seq']]['optionisnull'] = false;
						
						foreach($fmOptionInfo AS $optVal) {

							$tmpMatchText	= '';

							$tmpMatchText	.= implode('', (array)$optVal['opts']);
							$matchText		= $this->forMatchingOptionText($tmpMatchText);

							//필수옵션이 없는 상품 체크
							if(!$tmpMatchText) $fmGoodsOption[$orderRow['fm_goods_seq']]['optionisnull'] = true;

							//필수옵션이 없는 상품 체크
							if(!$tmpMatchText) $optionisnull = true;
							$nowTtitleExp	= explode(',', $optVal['option_title']);
							$optionInfo		= array();
							for ($tit_i = 0; $tit_i < 5; $tit_i ++) {
								$titleIdx	= $tit_i + 1;
								if(isset($nowTtitleExp[$tit_i]) == true)
									$optionInfo["title{$titleIdx}"]	= trim($nowTtitleExp[$tit_i]);
								else
									$optionInfo["title{$titleIdx}"]	= '';
							}

							$optionInfo['option1']			= $optVal['option1'] ? $optVal['option1'] : '';
							$optionInfo['option2']			= $optVal['option2'] ? $optVal['option2'] : '';
							$optionInfo['option3']			= $optVal['option3'] ? $optVal['option3'] : '';
							$optionInfo['option4']			= $optVal['option4'] ? $optVal['option4'] : '';
							$optionInfo['option5']			= $optVal['option5'] ? $optVal['option5'] : '';
							$optionInfo['optioncode1']		= $optVal['optioncode1'];
							$optionInfo['optioncode2']		= $optVal['optioncode2'];
							$optionInfo['optioncode3']		= $optVal['optioncode3'];
							$optionInfo['optioncode4']		= $optVal['optioncode4'];
							$optionInfo['optioncode5']		= $optVal['optioncode5'];
							// 옵션 코드 가져오는 부분 생성 :: 2018-02-26 lkh
							$optionInfo['goods_code']		= $orderRow['fm_goods_code'].$optVal['optioncode1'].$optVal['optioncode2'].$optVal['optioncode3'].$optVal['optioncode4'].$optVal['optioncode5'];//조합된상품코드

							$nowFmOption					= array();
							$nowFmOption['option_params']	= $optionInfo;
							$nowFmOption['option_seq']		= $optVal['option_seq'];
							$nowFmOption['commission_rate']	= $optVal['commission_rate'];
							$nowFmOption['commission_type']	= $optVal['commission_type'];
							$optionInfo['matchText']		= $matchText;
							$optionKey						= md5($matchText);

							$fmGoodsOption[$orderRow['fm_goods_seq']][$optionKey]	= $nowFmOption;
						}

					}

					$nowOptValue	= array();
					$nowInputValue	= array();
					//쇼핑몰에 옵션없는 상품은 마켓에서 넘어온 옵션명을 무시하고 등록
					if($fmGoodsOption[$orderRow['fm_goods_seq']]['optionisnull'] == false){
	
						$tmpOptionText	= explode(',', $orderRow['order_option_name']);
	
						$optCnt			= 1;
						foreach((array)$tmpOptionText as $val) {
							if ($optCnt > 5)
								break;
	
							$tmpOptionDesc		= explode(':', $val);
	
							if (count($tmpOptionDesc) > 1) {
								$tmpOptionTitle		= trim($tmpOptionDesc[0]);
								$tmpOptionValue		= trim($tmpOptionDesc[1]);
							} else {
								$tmpOptionValue		= trim($tmpOptionDesc[0]);
							}
	
							$temInputKey		= md5($this->forMatchingOptionText($tmpOptionTitle));
							
							if (isset($fmGoodsInputOption[$orderRow['fm_goods_seq']][$temInputKey]) === true) {
								//입력옵션 처리
								$nowInputValue['order_seq']		= $orderSeq;
								$nowInputValue['item_seq'] 		= $orderItemList[$orderRow['market_product_code']];
								$nowInputValue['type'] 			= $fmGoodsInputOption[$orderRow['fm_goods_seq']][$temInputKey]['input_form'];
								$nowInputValue['title']			= $tmpOptionTitle;
								$nowInputValue['value']			= $tmpOptionValue;
							} else {
								$nowOptValue[]					= trim($tmpOptionValue);
								$optCnt++;
							}
						}
					}

					$tmpMarketMatchText	= implode('', $nowOptValue);
					$marketMatchText	= $this->forMatchingOptionText($tmpMarketMatchText);
					$marketMatchKey		= md5($marketMatchText);
					$nowMatchedOption	= $fmGoodsOption[$orderRow['fm_goods_seq']][$marketMatchKey];

					if (is_array($nowMatchedOption) !== true && $singleOption[$orderRow['fm_goods_seq']] === false) {
						$errorOrderList[$orderRow['seq']]	= "[{$row['market_order_no']}] \"{$orderRow['order_option_name']}\" 옵션은 \"[{$orderRow['fm_goods_seq']}] {$orderRow['fm_goods_name']}\"상품에 존재하지 않습니다.";
						$allMachedProduct	= false;
						continue;
					}

					$optionParams									= $nowMatchedOption['option_params'];
					$optionParams['package_yn']						= $orderRow['package_yn'];
					$optionParams['member_sale']					= 0;
					$optionParams['basic_sale']						= 0;
					$optionParams['event_sale_target']				= 0;
					$optionParams['event_sale']						= 0;
					$optionParams['multi_sale']						= 0;
					$optionParams['reserve']						= 0;
					$optionParams['point']							= 0;
					$optionParams['download_seq']					= 0;
					$optionParams['coupon_sale']					= 0;
					$optionParams['coupon_sale_krw']				= 0;
					$optionParams['coupon_input']					= 0;
					$optionParams['coupon_input_one']				= 0;
					$optionParams['promotion_code_seq']				= 0;
					$optionParams['promotion_code_sale']			= 0;
					$optionParams['promotion_code_sale_krw']		= 0;
					$optionParams['fblike_sale']					= 0;
					$optionParams['mobile_sale']					= 0;
					$optionParams['referersale_seq']				= 0;
					$optionParams['referer_sale']					= 0;
					$optionParams['salescost_provider_coupon']		= 0;
					$optionParams['salescost_provider_promotion']	= 0;
					$optionParams['salescost_provider_referer']		= 0;

					$optionParams['purchase_goods_name']			= $orderRow['purchase_goods_name'];
					$optionParams['order_seq']						= $orderSeq;
					$optionParams['item_seq']						= $orderItemList[$orderRow['market_product_code']];
					$optionParams['provider_seq']					= $orderRow['provider_seq'];
					$optionParams['shipping_seq']					= $shippingSeqList[$orderRow['seq']];
					$optionParams['step']							= "25";
					$optionParams['price']							= $orderRow['order_amount'] / $orderRow['order_qty'];
					$optionParams['sale_price']						= $orderRow['order_amount'] / $orderRow['order_qty'];
					$optionParams['ori_price']						= $orderRow['order_product_price'];
					$optionParams['org_price']						= $orderRow['order_product_price'];
					$optionParams['consumer_price']					= $orderRow['order_product_price'];
					
					//2018-04-12 샵링커 매입가 인서트
					if(stripos($row['market'],"API") !== false){
						$otherInfo								= json_decode($row['other_info'],true);
						if($otherInfo['orderSupply'] != ''){
							$optionParams['supply_price']			= $otherInfo['orderSupply'];
						}else{
							$optionParams['supply_price']			= 0;
						}
					}else{
						$optionParams['supply_price']					= 0;
					}
					
					$optionParams['ea']								= $orderRow['order_qty'];
					$optionParams['reserve_log']					= '';
				    $optionParams['point_log']						= '';

					$_commission_info					= array();
					foreach(get_commission_info_field() as $_field) $_commission_info[$_field] = $optionParams[$_field];
					$_commission_info['target_price']		= $optionParams['price'];
					$_commission_info['commission_rate']	= $nowMatchedOption['commission_rate'];
					$_commission_info['commission_type']	= $nowMatchedOption['commission_type'];
					$_commission_info['pay_price']			= $optionParams['sale_price'];
					$_commission_info['salescost_provider']	= 0;
					$_return_commission 					= get_commission($_commission_info);

					$optionParams['commission_price'] 		= $_return_commission['old_commission_unit_price'];		// (구)정산금액 : 기존처럼 option에 저장됨.
					$optionParams['commission_price_krw']	= $_return_commission['old_commission_unit_price_krw'];	// (구)정산금액 : 기존처럼 option에 저장됨.

					$this->_CI->db->insert('fm_order_item_option', $optionParams);

					$nowItemOptionSeq		= $this->_CI->db->insert_id();
					$lastItemOptionSeq		= $nowItemOptionSeq;
					$orderItemOptionList[$orderRow['market_product_code']]	= $nowItemOptionSeq;
					
					/**
					* 정산개선 - 옵션처리 시작
					* data : 주문정보
					* insert_params : 필수옵션정보
					* @ accountallmodel
					**/
					$optionParams['order_goods_seq']			= $nowMatchedOption['goods_seq'];
					$optionParams['order_goods_name']			= $nowMatchedOption['goods_name'];
					$optionParams['order_goods_kind']			= $nowMatchedOption['goods_kind'];
					$optionParams['commission_price'] 			= $_return_commission['commission_unit_price'];			//(신)정산금액
					$optionParams['commission_price_krw']		= $_return_commission['commission_unit_price_krw'];		//(신)정산금액 원화기준 정산가
					$optionParams['item_option_seq']			= $nowItemOptionSeq;
					$optionParams['order_form_seq']				= $nowItemOptionSeq;
					//$optionParams['shipping_seq']				= $shippingSeqList[$orderRow['seq']];
					//$optionParams['multi_sale_provider']		= ($orderRow['provider_seq'] != 1)?100:0;//해당상품이 입점사상품이면 입점사부담율 100%/본사라면 0
					$optionParams['accountallmodeltest']		= "accountallmodeltest_opt";
					$account_ins_opt[$nowItemOptionSeq] = array_merge($optionParams,$nowMatchedOption);
					/**
					* 정산개선 - 옵션처리 끝
					* data : 주문정보
					* insert_params : 필수옵션정보
					* @
					**/
					
					if (isset($nowInputValue['type'])) {
						$nowInputValue['item_option_seq']		= $nowItemOptionSeq;
						$this->_CI->db->insert('fm_order_item_input', $nowInputValue);
					}
				}

				if ($lastItemOptionSeq < 1)
					continue;

				// 추가옵션 처리
				if ($orderRow['add_product_yn'] == 'Y') {
					if (isset($fmGoodsSuboption[$orderRow['fm_goods_seq']]) === false) {
						$fmSuboptionInfo	= $goodsModel->get_goods_suboption($orderRow['fm_goods_seq']);
						
						foreach($fmSuboptionInfo AS $subOptArr) {
							foreach($subOptArr AS $subOptVal) {
								$tmpMatchText	= $subOptVal['suboption'];
								$matchText		= $this->forMatchingOptionText($tmpMatchText);

								$nowFmSuboption['suboption_seq']	= $subOptVal['suboption_seq'];
								$nowFmSuboption['package_count']	= $subOptVal['package_count'];
								$nowFmSuboption['suboption_title']	= $subOptVal['suboption_title'];
								$nowFmSuboption['commission_rate']	= $subOptVal['commission_rate'];
								$nowFmSuboption['commission_type']	= $subOptVal['commission_type'];
								$nowFmSuboption['sub_goods_code']	= "{$subOptVal['goods_seq']}{$subOptVal['suboption_seq']}";
								$nowFmSuboption['matchText']		= $matchText;
								$suboptionKey						= md5($matchText);

								$fmGoodsSuboption[$orderRow['fm_goods_seq']][$suboptionKey]	= $nowFmSuboption;		
							}
						}
					}
					
					$tmpMarketMatchText	= $orderRow['order_product_name'];
					$marketMatchText	= $this->forMatchingOptionText($tmpMarketMatchText);
					$marketMatchKey		= md5($marketMatchText);
					

					$nowMatchedSubption	= $fmGoodsSuboption[$orderRow['fm_goods_seq']][$marketMatchKey];
					if (is_array($nowMatchedSubption) !== true) {
						$errorOrderList[$orderRow['seq']]	= "[{$row['market_order_no']}] 추가옵션 - \"{$orderRow['order_product_name']}\"은 \"[{$orderRow['fm_goods_seq']}] {$orderRow['fm_goods_name']}\"상품에 존재하지 않습니다.";
						$allMachedProduct	= false;
						continue;
					}
					
					$subptionParams							= array();
					$subptionParams['order_seq'] 			= $orderSeq;
					$subptionParams['item_seq'] 			= $orderItemList[$orderRow['market_product_code']];
					$subptionParams['item_option_seq']		= $orderItemOptionList[$orderRow['market_product_code']];
					$subptionParams['step'] 				= "25";
					$subptionParams['price'] 				= $orderRow['order_amount'] / $orderRow['order_qty'];
					//$subptionParams['sale_price']			= $orderRow['order_amount'] / $orderRow['order_qty'];
					$subptionParams['member_sale'] 			= 0;
					$subptionParams['point']				= 0;
					$subptionParams['reserve']				= 0;
					$subptionParams['consumer_price']		= $orderRow['order_product_price'];
					$subptionParams['supply_price']			= 0;
					$subptionParams['ea']					= $orderRow['order_qty'];
					$subptionParams['title']				= $nowMatchedSubption['suboption_title'];
					$subptionParams['suboption']			= $orderRow['order_product_name'];
					$subptionParams['goods_code'] 			= $nowMatchedSubption['sub_goods_code'];
					$subptionParams['suboption_code']		= $nowMatchedSubption['suboption_seq'];

					if ((int)$nowMatchedSubption['package_count'] > 0) {
						$subptionParams['package_yn']		= 'y';
						$packageYn							= 'Y';
					}
					
					$_commission_info					= array();
					foreach(get_commission_info_field() as $_field) $_commission_info[$_field] = $subptionParams[$_field];
					$_commission_info['target_price']			= $subptionParams['price'];
					$_commission_info['commission_rate']		= $nowMatchedSubption['commission_rate'];
					$_commission_info['commission_type']		= $nowMatchedSubption['commission_type'];
					$_commission_info['pay_price']				= $subptionParams['price'];
					$_commission_info['provider_seq']			= $orderRow['provider_seq'];					
					$_commission_info['salescost_provider']		= 0;
					$_return_commission 						= get_commission($_commission_info);

					$subptionParams['commission_price'] 		= $_return_commission['old_commission_unit_price'];
					$subptionParams['commission_price_krw']		= $_return_commission['old_commission_unit_price_krw'];	// (구)정산금액 : 기존처럼 option에 저장됨.

					$this->_CI->db->insert('fm_order_item_suboption', $subptionParams);
					$nowSubOptionSeq		= $this->_CI->db->insert_id();

					/**
					* 정산개선 - 추가옵션처리 시작
					* data : 주문정보
					* insert_params : 추가옵션정보
					* @ accountallmodel
					**/
					$subptionParams['sale_price']				= $subptionParams['price'];		//2021.01.21 위치변경 주의! suboption에는 sale_price 필드 없음.
					$subptionParams['order_goods_seq']			= $orderRow['goods_seq'];
					$subptionParams['order_goods_name']			= $orderRow['goods_name'];
					$subptionParams['order_goods_kind']			= $orderRow['goods_kind'];
					$subptionParams['commission_price'] 		= $_return_commission['commission_unit_price'];			//(신)정산금액
					$subptionParams['commission_price_krw']		= $_return_commission['commission_unit_price_krw'];		//(신)정산금액 원화기준 정산가
					$subptionParams['item_suboption_seq']		= $nowSubOptionSeq;
					$subptionParams['order_form_seq']			= $nowSubOptionSeq;
					$subptionParams['provider_seq'] 			= $orderRow['provider_seq'];
					$subptionParams['shipping_seq']				= $shippingSeqList[$orderRow['seq']];
					$subptionParams['accountallmodeltest']		= "accountallmodeltest_sub";
					$account_ins_subopt[$nowSubOptionSeq]		= array_merge($subptionParams,$nowMatchedSubption);

					/**
					* 정산개선 - 추가옵션처리 끝
					* data : 주문정보
					* insert_params : 추가옵션정보
					* @accountallmodel
					**/

					if ($nowSubOptionSeq < 1)
						continue;
				}
				

				//주문 등록 전에 이미 등록된 주문건이 있는지 체크해서 중복등록 안되도록 처리. 2019-03-06
				$countSql	= "
					SELECT	fm_order_seq
					FROM	fm_market_orders
					WHERE	market_order_no		= '{$row['market_order_no']}'
						AND	market				= '{$row['market']}'
						AND seq					= '{$orderRow['seq']}'
						AND fm_order_seq		!= 0";
				
				$result		= $this->_CI->db->query($countSql);
				if ($result->num_rows() > 0) {
					$allMachedProduct	= false;
					continue;
				}

				// 오픈마켓 주문정보 저장
				$fmOrderInfo							= array();
				$updateWhere							= array();
				$fmOrderInfo['fm_order_seq']			= $orderSeq;
				$fmOrderInfo['fm_item_seq']				= $orderItemList[$orderRow['market_product_code']];
				$fmOrderInfo['fm_item_option_seq']		= $lastItemOptionSeq;
				$fmOrderInfo['fm_item_suboption_seq']	= $nowSubOptionSeq;
				$fmOrderInfo['market_order_status']		= 'ORD20';
				$fmOrderInfo['renewed_time']			= date('Y-m-d H:i:s');
				$fmOrderInfo['last_message']			= "주문 저장완료 - 주문번호 : {$orderSeq}";
				$fmOrderInfo['fm_order_save_time']		= $fmOrderInfo['renewed_time'];
				
				$this->_CI->db->update('fm_market_orders', $fmOrderInfo, array('seq' => $orderRow['seq']));
				
				//금액계산
				$settleAmount	+= $orderRow['paid_amount'];
				$shippingCost	+= $orderRow['shipping_cost'];
				// 착불인 경우 결제금액에서 배송비 차감 2019-05-09
				if( $shipping_type == 'postpaid') {
					$settleAmount -= $orderRow['shipping_cost'];
				}
				//면세상품 처리
				if ($orderRow['tax'] == 'exempt')
					$totalTaxfreeAmount	+= $orderRow['order_amount'];

				$orderItemCnt++;
			}

			//1개의 등록된 상품이 있어야 하고 모든 상품이 매칭되어야 주문등록 가능.
			$orderRegister			= false;

			if($orderItemCnt > 0 && $allMachedProduct === true ) {

				$orderConfirmInfo	= $this->callConnector("Order/doOrderConfirm/{$row['market_order_no']}/{$row['market_delivery_no']}");

				if ($orderConfirmInfo['success'] == 'Y') {

					$orderAddParams['mode']						= 'direct';
					$orderAddParams['step'] 					= '25';
					$orderAddParams['deposit_yn'] 				= "y";
					$orderAddParams['emoney_use'] 				= 'none';
					$orderAddParams['cash_use'] 				= 'none';
					$orderAddParams['freeprice'] 				= $totalTaxfreeAmount;
					$orderAddParams['settleprice'] 				= $settleAmount;
					$orderAddParams['original_settleprice'] 	= $settleAmount;
					$orderAddParams['shipping_cost']			= $shippingCost;
					$orderAddParams['international']			= $shippingArea;
					$orderAddParams['regist_date'] 				= date('Y-m-d H:i:s',time());
					$orderAddParams['regist_date']				= $row['order_time'];
					$orderAddParams['deposit_date']				= $row['settle_time'];
					$orderAddParams['linkage_id']				= 'connector';
					$orderAddParams['linkage_order_id']			= '';	// 추후 샵링커등 연동시 주문번호로 사용
					$orderAddParams['linkage_mall_order_id']	= $row['market_order_no'];
					
					
					// 스마트스토어 개인통관부호 처리 :: 2018-04-10 lkh
					$otherInfo									= json_decode($row['other_info'],true);
					if(stripos($row['market'],"API") !== false){
						$orderAddParams['linkage_mall_code']		= $otherInfo['openmarketId'];
					}else{
						$orderAddParams['linkage_mall_code']		= $row['market'];
					}
					
					// 스마트스토어 개인통관부호 처리 :: 2018-04-10 lkh
					if($otherInfo['ProductOrder']['IndividualCustomUniqueCode']){
						$orderAddParams['clearance_unique_personal_code']		= $otherInfo['ProductOrder']['IndividualCustomUniqueCode'];
					}
					
					$orderAddParams['linkage_order_reg_date']	= date('Y-m-d H:i:s');
				
					$this->_CI->db->update('fm_order', $orderAddParams, array('order_seq'=>$orderSeq));
					
					$successOrderList[$orderRow['market_order_no']]			= "[{$orderRow['market_order_no']}] {$orderSeq} 주문이 정상적으로 등록되었습니다. ";
					$orderRegister		= true;

					// 주문 로그 생성
					$title = "[".$this->_supportMarketNames[$orderAddParams['linkage_mall_code']]['name']."] 결제확인 (API)";
					$log = "market_order_no[".$row['market_order_no']."]".chr(10)."market_delivery_no[".$row['market_delivery_no']."]".chr(10)."linkage_mall_code[".$orderAddParams['linkage_mall_code']."]";
					$orderModel->set_log($orderSeq, 'process', ($this->_CI->managerInfo['mname'])?$this->_CI->managerInfo['mname']:"시스템", $title, $log);
				} else {

					$errSeqList		= explode(',', $row['seq_list']);
					foreach ($errSeqList as $moKey)
						$errorOrderList[$moKey]	= $orderConfirmInfo['message'];
				}
			}

			// 연결상품이 있을경우
			if( $packageYn == 'Y' ){
				$this->_CI->load->model('orderpackagemodel');
				$this->_CI->orderpackagemodel->package_order($orderSeq);
			}

			$result_option	= $orderModel->get_item_option($orderSeq);
			$result_suboption = $orderModel->get_item_suboption($orderSeq);

			// 출고량 업데이트를 위한 변수선언
			$r_reservation_goods_seq = array();

			// 해당 주문 상품의 출고예약량 업데이트
			if($result_option){
				foreach($result_option as $data_option){
					// 출고량 업데이트를 위한 변수정의
					if(!in_array($data_option['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $data_option['goods_seq'];
					}
				}
			}
			if($result_suboption){
				foreach($result_suboption as $data_suboption){
					// 출고량 업데이트를 위한 변수정의
					if(!in_array($data_suboption['goods_seq'],$r_reservation_goods_seq)){
						$r_reservation_goods_seq[] = $data_suboption['goods_seq'];
					}
				}
			}

			// 출고예약량 업데이트
			foreach($r_reservation_goods_seq as $goods_seq){
				$goodsModel->modify_reservation_real($goods_seq);
			}

			/**
			* 1-1 주문데이타를 이용한 임시매출데이타 생성 시작
			* step1->step2->step3 순차로 진행되어야 합니다.
			* @
			**/
			if(!$this->_CI->accountall)			$this->_CI->load->helper('accountall');
			if(!$this->_CI->accountallmodel)		$this->_CI->load->model('accountallmodel');
			//step1 주문금액별 정의/비율/단가계산 후 정렬
			$set_order_price_ratio = $this->_CI->accountallmodel->set_order_price_ratio($orderSeq, $account_ins_opt, $account_ins_subopt, $account_ins_shipping);
			//step2 적립금/이머니/에누리(관리자주문) update 제외
			//step3 임시 매출/정산 저장
			$this->_CI->accountallmodel->insert_calculate_sales_order_tmp($orderSeq, $set_order_price_ratio, $account_ins_opt, $account_ins_subopt, $account_ins_shipping);
			//debug_var($this->_CI->db->queries);
			//debug_var($this->_CI->db->query_times);
			/**
			* 1-1 주문데이타를 이용한 임시매출데이타 생성 끝
			* step1->step2->step3 순차로 진행되어야 합니다.
			* @
			**/

			/**
			* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 시작
			* 정산개선 - 미정산매출데이타 처리
			* @ 
			**/
			$this->_CI->accountallmodel->insert_calculate_sales_order_deposit($orderSeq);
			//debug_var($this->_CI->db->queries);
			//debug_var($this->_CI->db->query_times);
			/**
			* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 끝
			* 정산개선 - 미정산매출데이타 처리
			* @ 
			**/

			if ($this->_CI->db->trans_status() === FALSE || $orderRegister == false) 
			{
				$this->_CI->db->trans_rollback();
				
				// 실패한 사유 저장.
				$lastMessageParams['renewed_time']			= date('Y-m-d H:i:s');
				foreach ($errorOrderList as $moSeq => $message) {
					$lastMessageParams['last_message']		= $message;
					$this->_CI->db->update('fm_market_orders', $lastMessageParams, array('seq' => $moSeq));
				}
				continue;
			} 
			else 
			{
				$this->_CI->db->trans_commit();
			}
		
			// 주문 총주문수량 / 총상품종류 업데이트 2020-05-26
			$orderModel->update_order_total_info($orderSeq);	

			//SMS 발송
			$this->send_for_provider	= array();

			$orders		= $orderModel->get_order($orderSeq);
			$items		= $orderModel->get_item($orderSeq);

			$params['goods_name']	= $items[0]['goods_name'];
			if (count($items) > 1)
				$params['goods_name']	.= '외 '.(count($items) - 1).'건';
			

			// 결제확인메일/sms 발송
			//send_mail_step25($orders['order_seq']);

			$providerList = array();
			foreach($items as $item)
				$providerList[$item['provider_seq']]	= 1;

			$params['settle_kind']	= '결제완료';
			$params['shopName']		= $this->config_basic['shopName'];
			$params['ordno']		= $orders['order_seq'];
			$params['order_user']	= $orders['order_user_name'];
			$params['openmarket']	= 'openmarketOrder';
			$params['marketName']	= $this->_supportMarkets[$row['market']]['name'];


			$commonSmsData = array();
			$commonSmsData['settle']['phone'][]		= $orders['order_cellphone'];
			$commonSmsData['settle']['params'][]	= $params;
			$commonSmsData['settle']['order_seq'][]	= $orderSeq;

			sendSMS_for_provider('settle', $providerList, $params);
			unset($providerList);

			//입점관리자 SMS 데이터
			if(count($this->send_for_provider['order_cellphone']) > 0){
				$provider_count = 0;
				foreach($this->send_for_provider['order_cellphone'] as $key=>$value){
					$provider_msg[$provider_count]				= $this->send_for_provider['msg'][$key];
					$provider_order_cellphones[$provider_count] = $this->send_for_provider['order_cellphone'][$key];
					$provider_count		=$provider_count+1;
				}

				$commonSmsData['provider']['phone'] = $provider_order_cellphones;
				$commonSmsData['provider']['msg'] = $provider_msg;
			}
			
			//구매자 SMS는 각 마켓 
			unset($commonSmsData['settle']);
			if(count($commonSmsData) > 0){
				commonSendSMS($commonSmsData);
			}

			$this->_CI->db->where('order_seq', $fmOrderSeq);
			$this->_CI->db->update('fm_order', array('sms_25_YN'=>'Y'));
		}

		$return['successList']	= $successOrderList;
		$return['failList']		= $errorOrderList;
		
		return $return;
	}

	/* 마켓 송장등록 로그 생성을 위해 추가 */
	public function marketOrderDelivery($fmOrderSeq, $exportInfo, $exportItem) {
		$result = $this->marketOrderDeliveryBase($fmOrderSeq, $exportInfo, $exportItem);
		
		$this->_CI->load->model('ordermodel');
		$orderModel		=& $this->_CI->ordermodel;
		$orders		= $orderModel->get_order($fmOrderSeq);
		
		$title = "";
		if($result['success'] == 'Y'){
			$title = "[".$this->_supportMarketNames[$orders['linkage_mall_code']]['name']."] 송장전송성공 (API)";
		}else{
			$title = "[".$this->_supportMarketNames[$orders['linkage_mall_code']]['name']."] 송장전송실패 (API) - ".$result['message'];
		}
		
		// 주문 로그 생성
		$log = "delivery_company_code[".$exportInfo['delivery_company_code']."]".chr(10)."delivery_number[".$exportInfo['delivery_number']."]".chr(10)."linkage_mall_code[".$orders['linkage_mall_code']."]";
		$orderModel->set_log($fmOrderSeq, 'process', $this->_CI->managerInfo['mname'], $title, $log);
		
		return $result;
	}

	/* 마켓 송장등록 */
	public function marketOrderDeliveryBase($fmOrderSeq, $exportInfo, $exportItem) {
		$connectorModel	=& $this->_CI->connectormodel;
		$_connectorBase	= $this->_CI->connector::getInstance();

		//연동마켓 주문인지 확인
		$marketOrder	= $connectorModel->checkIsMarketOrder($fmOrderSeq);

		/*20180322 linkage_mall_order_id가 ex_ 로 붙은 경우 퍼스트몰 내에서만 처리 (교환, 맞교환으로 파악) ldb*/
		$select = $this->_CI->db->query("SELECT linkage_mall_order_id FROM fm_order WHERE order_seq = $fmOrderSeq");
		$findRow		= $select->row_array();

		if(strpos($findRow['linkage_mall_order_id'],'ex-') !== false) {
			return array('success' => 'Y', 'message' => '"연동 마켓" 주문이지만 교환상품입니다.');
		}
		/*end*/


		if ($marketOrder === false)
			return array('success' => 'Y', 'message' => '"연동 마켓" 주문이 아닙니다.');

		if (trim($exportInfo['delivery_number']) == '')
			return array('success' => 'N', 'message' => '송장번호가 없습니다.');

		$searchParams['fmOrderSeq']		= $fmOrderSeq;

		$marketOrder	= $connectorModel->getMarketOrderList($searchParams, 'fmItemList', false);
		$marketItemList	= $marketOrder['itemList'];

		$this->setMarketInfo($marketOrder['orderInfo']['market'], $marketOrder['orderInfo']['seller_id']);

		foreach ((array) $exportItem as $fmItem) {
			$itemSeq		= $fmItem['item_seq'];
			$optionSeq		= $fmItem['item_option_seq'];
			
			$suboptionSeq	= ($fmItem['item_option_seq'] == $fmItem['option_seq']) ? 0 : $fmItem['option_seq'];
			$nowItem[]		= $marketItemList[$itemSeq][$optionSeq][$suboptionSeq];
		}
		
		$MarketLinkage	= config_load('MarketLinkage');

		foreach($nowItem as $nowItemRow) {
		
			$this->setMarketInfo($nowItemRow['market'], $nowItemRow['seller_id']);
		
			if(stripos($nowItemRow['market'],"API") !== false){
				$postVal['request']['shoplinkerId'] = $MarketLinkage['shoplinkerId'];

				if($nowItemRow['market_delivery_no'] == '') {
					$nowItemRow['market_delivery_no'] = 'null';
				}
			}else{
				$postVal =  false;
			}
		
			$market_order_no = $nowItemRow['market_order_no'];
			if(count($nowItem) > 1) $market_order_no .= "**".($nowItemRow['market_order_seq']-1);
			$rowStatus	= $this->callConnector("Order/getOrderStatus/{$market_order_no}/{$nowItemRow['market_delivery_no']}",$postVal);

			if ($rowStatus['success'] != 'Y')
				return array('success' => 'N', 'message' => '"연동 마켓" 주문을 확인할 수 없습니다.');
			$marketStatus[] = $rowStatus;
		}

		// 총 상품종류개수
		foreach($marketStatus as $status) {
			$targetMkSeq += count($status['resultData']['orderItemStatus']);
		}

		$targetFmSeq	= array();
		$fmMarketSeList	= array();

		foreach ((array)$exportItem as $key => $fmItem) {

			$subOpt			= ($fmItem['opt_type'] == 'sub') ? $fmItem['option_seq'] : 0;
			$item			= $marketItemList[$fmItem['item_seq']][$fmItem['item_option_seq']][$subOpt];
			
			if(stripos($item['market'],"API") !== false){
				if($item['market_delivery_no'] == '')
					$item['market_delivery_no'] = 'null';
			}
			
			$marketOrderStatus = $marketStatus[$key];

			//마켓 옵션 코드가 없을경우 순번으로 확인
			if (strlen($marketOrderStatus['resultData']['orderItemStatus'][0]['marketOptionCode']) > 0) {
				$itemCheckKey	= 'marketOptionCode';
				$itemCheckFm	= 'market_option_code';
			} else {
				$itemCheckKey	= 'marketOrderSeq';
				$itemCheckFm	= 'market_order_seq';
			}

			$mkOriginStatus		= $marketOrderStatus['resultData']['orderItemStatus'];
			
			foreach($mkOriginStatus as $k => $v) {
				if( $item['market_order_seq'] == $v['marketOrderSeq']) {
					$mkStatus = $v;
					continue;
				}
			}
			//샵링커 주문번호로 변경해서 등록
			if(stripos($item['market'],"API") !== false){
				$mallOrderNo = $mkStatus['marketOrderNo'];
				$otherInfo = json_decode($item['other_info'], true);
				$mkStatus['marketOrderNo'] = $otherInfo['openmarketOrderId'];
				$mkStatus['mallOrderNo'] = $mallOrderNo;
			}
			$mkOrderQty		= $mkStatus['orderQty'] - $mkStatus['cancelQty'];

			if ($mkOrderQty < 1) {

				if ($mkStatus['cancelQty'] != abs($fmItem['step35']))
					return array('success' => 'N', 'message' => '취소된 상품이 있습니다.');
				else
					return array('success' => 'N', 'message' => '취소된 주문입니다.');

			} else if ($fmItem['ea'] != $mkOrderQty) {
				return array('success' => 'N', 'message' => '마켓 주문상태를 확인해주세요');				
			} else {
					$targetFmSeq[]		= $mkStatus;
				$fmMarketSeList[]	= $item['seq'];
			}
		}

		$companyCode = $_connectorBase->getDeliveryCompanyCode($exportInfo['delivery_company_code']);
		$deliveryMethod = 'M01';

		$params['request']['deliveryMethod']	= $deliveryMethod;
		
		if(stripos($marketOrder['orderInfo']['market'],"API") !== false){		
			$params['request']['deliveryCompany']	= $exportInfo['delivery_company_code'];
		}else{
			$params['request']['deliveryCompany']	= $companyCode;
		}
		
		$params['request']['deliveryTime']		= date('Y-m-d H:i:s');
		$params['request']['deliveryNumber']	= str_replace('-', '', $exportInfo['delivery_number']);
		$params['request']['deliveryList']		= $targetFmSeq;
		
		if($targetMkSeq == count($targetFmSeq)){
			$url		= "Order/doRequestDelivery/full";
		}else{
			$url		= "Order/doRequestDelivery/partial";
			if(stripos($marketOrder['orderInfo']['market'],"API") !== false){
				return array('sruccess' => 'N', 'message' => '샵링커는 부분 출고 기능을 지원하지 않습니다.');
			}
		}

		$response		= $this->callConnector($url, $params);

		$updateParams	= array();
		if ($response['success'] == 'Y') {
			$updateParams['market_order_status']	= 'ORD40';
			$updateParams['invoice_num']			= $exportInfo['delivery_number'];
			$updateParams['renewed_time']			= $params['request']['deliveryTime'];
			$updateParams['last_message']			= '송장전송완료';
		} else {
			$updateParams['renewed_time']			= $params['request']['deliveryTime'];
			$updateParams['last_message']			= $response['message'];
		}

		$this->_CI->db->where_in('seq', $fmMarketSeList);
		$this->_CI->db->update('fm_market_orders', $updateParams);

		return $response;
	}


	public function doOrderOptionNameMatch(int $seqList, $matchedOptionName)
	{

		$marketOrderList	= $this->_CI->connectormodel->getMarketOrderList(['seqList' => $seqList]);

		if (count($marketOrderList) < 1) {
			$return['success']	= 'N';
			$return['message']	= '선택된 주문이 없습니다.';

			return $return;
		}

		$matchedOptionName		= trim($matchedOptionName);
		
		$updateParams['matched_option_name']	= ($matchedOptionName == '') ? Null : $matchedOptionName;
		$this->_CI->db->where_in('seq', $seqList);
		$this->_CI->db->update('fm_market_orders', $updateParams);
		
		$return['success']	= 'Y';
		$return['message']	= '옵션명 매칭 완료';

		return $return;

	}
	
	//단일 옵션명 '' 처리
	public function optionNameException($market='',$order_option_name=''){

		//쿠팡 단일옵션명은 '' 처리	 2019-01-10
		if($market == 'APISHOP_0184' && $order_option_name == '단일상품')
			$order_option_name = '';

		//티몬 단일옵션명은 '' 처리	 2019-08-13
		if($market == 'APISHOP_0182' && preg_replace('/\s+/', '', $order_option_name) == '단일상품')
			$order_option_name= '';

		return $order_option_name;

	}
}