<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

use App\Libraries\TalkbuyInterface;

class talkbuylibrary implements TalkbuyInterface
{
	var $baseLogDetail = "Kakao가";

	var $baseLogParams= array(
		"actor" 	=> "Kakao Pay",
		"add_info"	=> "talkbuy"
	);

	function __construct() {
		$this->CI =& get_instance();

		if(!$this->CI->partnerlib) $this->CI->load->library("partnerlib");
		if(!$this->CI->kakaoclaim) $this->CI->load->library("kakaoclaim");
		$this->CI->partnerlib->baseLogDetail = $this->baseLogDetail;
		$this->CI->partnerlib->baseLogParams = $this->baseLogParams;
	}

	public function check_talkbuy_order($order) {
		/**
		 * fmOrderSeq 값 있으면 unset
		 * suboption 이 있는경우는 suboption의 fmOrderSeq 도 확인 필요
		 */
		foreach($order["orderProduct"] as $key => $orderProduct) {
			if(empty($orderProduct["fmOrderSeq"])) {
				if(count($orderProduct["suboption"]) > 0) {
					foreach($orderProduct["suboption"] as $subkey =>$suboption) {
						if($suboption["fmOrderSeq"]) {
							unset($orderProduct["suboption"][$subkey]);
						}
					}
					// suboption 개수 다시 체크해서 없으면 옵션 삭제함
					if(count($orderProduct["suboption"]) == 0) {
						unset($order["orderProduct"][$key]);
					}
				}
			}
		}
		if(count($order["orderProduct"]) == 0) {
			unset($order);
		}
		return $order;
	}

	/**
	 * 톡체크아웃 중계서버에서 넘어온 데이터로 주문 등록
	 */
	public function set_talkbuy_order($order) {
		if(!$this->CI->orderlibrary)		$this->CI->load->library("orderlibrary");
		if(!$this->CI->shipping)			$this->CI->load->library('shipping');
		
		// 0. 트랜잭션 시작
		$this->CI->db->trans_begin();
	
		/**
		 * 쇼핑몰에서 구매하면 partner_order_detail 데이터 생성
		 * 톡채널에서 구매 시 partner_order_detail 이 없으므로 카카오톡에서 제공해준 데이터로만 insert 해야함
		 */
		$partnerParams = array();
		$partnerParams = array(
			'session_tmp' => $order['customData1'],
			'partner_id' => 'talkbuy',
		);
		$partnerOrder = $this->CI->partnerlib->getPartnerOrder($partnerParams);

		if(!empty($partnerOrder)) {
			$partnerOrder = $partnerOrder["0"];	// 주문에 필요한 정보는 모두 동일하므로 1개만 필요
		}	
		/**
		 * fm_order 데이터 조회 및 생성
		 */
		$fmOrder = $this->CI->partnerlib->getOrder($order["orderId"], "talkbuy");
		if(!empty($fmOrder)) {
			// 이미 등록된 주문이 있다면 order_seq 리턴
			$order_seq = $fmOrder["order_seq"];
		} else {
			// 없다면 주문 데이터 생성
			$params = $this->set_fm_order($order,$partnerOrder);
			$order_seq = $this->CI->orderlibrary->save_order($params);
		}
		/**
		* 정산개선 배열초기화
		* @ accountallmodel
		**/
		$account_ins_shipping	= array();
		$account_ins_opt		= array();
		$account_ins_subopt		= array();

		/**
		 * 배송그룹 생성
		 * 기존 shipping->make_shipping_group_list 은 현재 데이터를 이용해서 활용하지 않음.
		 * 톡구매는 구매 당시의 배송그룹을 활용해야함
		 * 카카오에서 넘어온 주문 그대로 넘겨서 shippingFeeGroupId 별로 생성하여 리턴함
		*/
		unset($in);
		$in['order_seq']					= $order_seq;
		$in['order']						= $order;				// 카카오에서 넘긴 주문 파라미터
		$this->save_order_shipping($in, $out);
		$order_shipping_list 		= $out["order_shipping_list"];
		$account_ins_shipping 		= $out["account_ins_shipping"];
		/**
		 *  주문 아이템 저장
		 * orderlibrary->save_order_item 사용안함 / talkbuylibrary->save_order_item
		 * option row 별로 insert/update 하기 때문에 한번에 처리 안함
		 */
		unset($in);
		$in['order_seq']					= $order_seq;
		$in['order_shipping_list']			= $order_shipping_list;
		$in['order']						= $order;				// 카카오에서 넘긴 주문 파라미터
		$orderData = $this->save_order_item($in);

		if($orderData["order_register"] === true && count($orderData["save_order_list"]) >0 ) {
			// ===========================================================================
			// 주문 후 정산처리 시작
			// ===========================================================================
			unset($in);
			$in['orderSeq']						= $orderData["order_seq"];				// talkbuylibrary->save_order 에서 반환
			$in['account_ins_opt']				= $orderData["account_ins_opt"];		// talkbuylibrary->save_order_item 에서 반환
			$in['account_ins_subopt']			= $orderData["account_ins_subopt"];		// talkbuylibrary->save_order_item 에서 반환
			$in['account_ins_shipping']			= $account_ins_shipping;				// shipping->save_shipping 에서 반환
			unset($out);
			$out = array();

			$this->CI->orderlibrary->proc_order_after_init($in, $out);					// 주문생성 후 처리 - 정산			
			if ($this->CI->db->trans_status() === false)
			{
				writeCsLog("trans_status IS FALSE", "order" , "talkbuy");
				$this->CI->db->trans_rollback();
				return false;
			}
		}

		$this->CI->db->trans_commit();

		return $orderData["save_order_list"];
	}

	/**
	 * 배송그룹 생성
	 */
	function save_order_shipping($in,&$out) {
		if(!$this->CI->shipping)			$this->CI->load->library('shipping');
		$order			= $in["order"];
		$order_seq		= $in["order_seq"];
		
		$order_shipping_list = array();
		foreach($order['orderProduct'] as $product) {
			// 새로운 shippingFeeGroupId 만 insert 함
			if (isset($order_shipping_list[$product['shippingFeeGroupId']]) !== true) {
				//shippingFeeGroupId 로 등록된 shipping 데이터가 있으면 insert 안함
				$fmOrderShipping = $this->CI->partnerlib->getOrderShipping($in, $product['shippingFeeGroupId'], "talkbuy");
				if(!empty($fmOrderShipping)) {
					// 이미 등록된 배송그룹 있다면 order_seq 리턴
					$fmOrderShipping = array_pop($fmOrderShipping);
					$shipping_seq = $fmOrderShipping["shipping_seq"];
				} else {
					$partnerParams = array();
					$partnerParams = array(
						"session_tmp" => $order["customData1"],
						"partner_id" => "talkbuy",
						"goods_seq" => $product["productId"],
					);
					$partnerOrder = $this->CI->partnerlib->getPartnerOrder($partnerParams);

					$in["product"] 			= $product;
					$in["partnerOrder"] 	= $partnerOrder["0"];
					$shipping_param = $this->set_fm_order_shipping($in);

					$shipping_in = array();
					$shipping_in["orderSeq"]			= $order_seq;
					$shipping_in["shippingInfoList"]	= $shipping_param["shippingInfoList"];
					$shipping_in["shippingGroupList"]	= $shipping_param["shippingGroupList"];
					$this->CI->shipping->save_shipping($shipping_in, $out);

					$shipping_seq 			= $out["shippingSeqList"][$product['shippingFeeGroupId']];
					$account_ins_shipping 	= $out["account_ins_shipping"];
				}
				$order_shipping_list[$product['shippingFeeGroupId']]	= $shipping_seq;
			}
		}
		
		$out["account_ins_shipping"] = $account_ins_shipping;
		$out["order_shipping_list"]	 = $order_shipping_list;
	}

	/**
	 * 주문 아이템 생성
	 * orderlibrary->save_row_order_item save_row_order_suboption save_row_order_input
	 */
	function save_order_item($in) {
		$orderRegister 			= true;
		$order_seq 				= $in["order_seq"];
		$order_shipping_list 	= $in["order_shipping_list"];
		$order 					= $in["order"];				// 카카오톡 주문 정보

		$partnerParams = array(
			"session_tmp" => $order["customData1"],
			"partner_id" => "talkbuy",
			"option_type" => "option",
		);
		$partnerOrders = $this->CI->partnerlib->getPartnerOrder($partnerParams);
		foreach($partnerOrders as $row) {
			$partnerOrderOption[$row['cart_option_seq']][$row["goods_seq"]][$row["option_seq"]] = $row;
		}

		$account_ins_opt = $account_ins_subopt = array();		// 정산 데이터 리턴

		$orderItemList = array();
		foreach($order['orderProduct'] as $product) {
			/**
			 * fm_order_item 데이터 저장 및 기존 데이터 가져오기
			 */
			//productId 로 등록된 fm_order_item 데이터가 있으면 insert 안함
			$item_in = array();
			$item_in['order_seq'] 		= $order_seq;
			$item_in["shipping_seq"] 	= $order_shipping_list[$product["shippingFeeGroupId"]];
			$item_in['goods_seq'] 		= $product["productId"];
			$item_in['orderId'] 		= $order["orderId"];
			$fmOrderItem = $this->CI->partnerlib->getOrderItem($item_in, "talkbuy");
			if(!empty($fmOrderItem)) {
				// 이미 등록된 order_item 있다면 item_seq 리턴
				$item_seq = $fmOrderItem["item_seq"];
			} else {
				$goods_option_seq			= $product["selectId"];
				$product["shipping_seq"] 	= $order_shipping_list[$product["shippingFeeGroupId"]];
				$product["partner"] 		= $partnerOrderOption[$product["cartOptionSeq"]][$product["productId"]][$goods_option_seq];
				$product["order_seq"]		= $order_seq;
				
				$option_in = array();
				$option_in["order"]			= $order;
				$option_in["product"] 		= $product;
				$params = $this->set_order_item($option_in);

				$item_seq = $this->CI->orderlibrary->save_row_order_item($params);
				if($item_seq == 0) {
					$orderRegister = false;
					writeCsLog("item_seq NOT INSERT", "item" , "talkbuy");
					writeCsLog($this->CI->db->last_query(), "item" , "talkbuy");
					continue;
				}
			}

			/**
			 * fm_order_item_option 데이터 저장 및 기존 데이터 가져오기
			 */
			$option_in = array();
			$option_in["order_seq"] 		= $order_seq;
			$option_in["shipping_seq"] 		= $order_shipping_list[$product["shippingFeeGroupId"]];
			$option_in["item_seq"] 			= $item_seq;
			$option_in["id"] 				= $product["id"];
			$option_in["orderId"] 			= $order["orderId"];
			$option_in["packageNumber"] 	= $product["shippingFeeGroupId"];
			$fmOrderItemOption = $this->CI->partnerlib->getOrderItemOption($option_in, "talkbuy");

			if(!empty($fmOrderItemOption)) {
				// 이미 등록된 order_item 있다면 item_seq 리턴
				$option_seq = $fmOrderItemOption["item_option_seq"];
			} else {
				$talk_option_seq			= $product["selectId"];
				$product["shipping_seq"] 	= $order_shipping_list[$product["shippingFeeGroupId"]];
				$product["partner"] 		= $partnerOrderOption[$product["cartOptionSeq"]][$product["productId"]][$talk_option_seq];
				$product["order_seq"]		= $order_seq;
				$product["item_seq"]		= $item_seq;

				$option_in = array();
				$option_in["order"]			= $order;
				$option_in["product"] 		= $product;
				$params = $this->set_order_item_option($option_in);

				$option_out = $this->CI->orderlibrary->save_row_order_item_option($params);

				$option_seq = $option_out["nowOptionSeq"];
				if($option_seq == 0) {
					$orderRegister = false;
					writeCsLog("option_seq NOT INSERT", "option" , "talkbuy");
					writeCsLog($this->CI->db->last_query(), "option" , "talkbuy");
					continue;
				}

				// order_item_option 저장 후 리턴 데이터 가공
				$account_ins_opt = $option_out["account_ins_opt"] + $account_ins_opt;
				$save_order_list[$product["id"]] = $order_seq;
				$this->CI->partnerlib->setPartnerOrder($product["partner"]["partner_order_seq"], $product["id"]);
			}

			// suboption
			/**
			 * fm_order_item_suboption 데이터 저장 및 기존 데이터 가져오기
			 */
			if($product["suboption"]) {
				$partnerParams = array(
					"session_tmp" => $order["customData1"],
					"partner_id" => "talkbuy",
					"option_type" => "suboption",
				);
				$partnerOrderSubs = $this->CI->partnerlib->getPartnerOrder($partnerParams);
				foreach($partnerOrderSubs as $row) {
					$partnerOrderSub[$row['cart_option_seq']][$row["goods_seq"]][$row["option_seq"]] = $row;
				}
				foreach($product["suboption"] as $suboption) {	
					$suboption_in = array();
					$suboption_in["order_seq"] 			= $order_seq;
					$suboption_in["shipping_seq"] 		= $order_shipping_list[$product["shippingFeeGroupId"]];
					$suboption_in["item_seq"] 			= $item_seq;
					$suboption_in["id"] 				= $suboption["id"];
					$suboption_in["orderId"] 			= $order["orderId"];
					$suboption_in["packageNumber"] 		= $product["shippingFeeGroupId"];
					$suboption_in["option_seq"] 		= $option_seq;
					$fmOrderItemSubOption = $this->CI->partnerlib->getOrderItemSubOption($suboption_in, "talkbuy");

					if(!empty($fmOrderItemSubOption)) {
						// 이미 등록된 order_item 있다면 item_seq 리턴
						$item_suboption_seq = $fmOrderItemSubOption["item_suboption_seq"];
					} else {
						$talk_item_suboption_seq	= $suboption["selectId"];
						$suboption["shipping_seq"] 	= $order_shipping_list[$product["shippingFeeGroupId"]];
						$suboption["partner"] 		= $partnerOrderSub[$suboption["cartOptionSeq"]][$product["productId"]][$talk_item_suboption_seq];
						$suboption["order_seq"]		= $order_seq;
						$suboption["item_seq"]		= $item_seq;
						$suboption["item_option_seq"]	= $option_seq;
						$suboption["shippingFeeGroupId"]	= $product["shippingFeeGroupId"];
		
						$suboption_in = array();
						$suboption_in["order"]			= $order;
						$suboption_in["product"] 		= $suboption;

						$params = $this->set_order_item_suboption($suboption_in);

						$suboption_out = $this->CI->orderlibrary->save_row_order_item_suboption($params);
						$suboption_seq = $suboption_out["nowSubOptionSeq"];
						if($suboption_seq == 0) {
							$orderRegister = false;
							writeCsLog("suboption_seq NOT INSERT", "suboption" , "option");
							writeCsLog($this->CI->db->last_query(), "suboption" , "option");
							continue;
						}
						// order_item_suboption 처리 후 리턴 데이터 가공
						$account_ins_subopt = $suboption_out["account_ins_subopt"] + $account_ins_subopt;
						$save_order_list[$suboption["id"]] = $order_seq;
						$this->CI->partnerlib->setPartnerOrder($suboption["partner"]["partner_order_seq"], $suboption["id"]);
					}
				}
			}

			/**
			 * fm_order_item_input 데이터 저장 및 기존 데이터 가져오기
			 */
			if($product["inputoption"]) {
				foreach($product["inputoption"] as $input) {
					$input_in = array();
					$input_in["order_seq"] 			= $order_seq;
					$input_in["item_seq"] 			= $item_seq;
					$input_in["option_seq"] 		= $option_seq;
					$input_in["title"] 				= $input["inputName"];
					$input_in["value"] 				= $input["inputText"];
					$fmOrderItemInput = $this->CI->partnerlib->getOrderItemInput($input_in, "talkbuy");
					
					if(!empty($fmOrderItemInput)) {
						// 이미 등록된 order_item 있다면 item_seq 리턴
						$item_input_seq = $fmOrderItemInput["item_input_seq"];
					} else {
						$product["order_seq"]		= $order_seq;
						$product["item_seq"]		= $item_seq;
						$product["item_option_seq"]	= $option_seq;
		
						$input_in = array();
						$input_in["order"]			= $order;
						$input_in["product"] 		= $product;
						$input_in["input"] 			= $input;
						$params = $this->set_order_item_input($input_in);

						$input_out = $this->CI->orderlibrary->save_row_order_item_input($params);
					}
				}
			}

		}

		$orderData["order_seq"] 			= $order_seq;
		$orderData["account_ins_opt"] 		= $account_ins_opt;
		$orderData["account_ins_subopt"] 	= $account_ins_subopt;
		$orderData["save_order_list"] 		= $save_order_list;
		$orderData["order_register"] 		= $orderRegister;

		return $orderData;
	}

	/**
	 * 주문 아이템 파라미터 생성
	 */
	function set_order_item($in) {
		if(!$this->CI->goodsmodel)			$this->CI->load->model("goodsmodel");
		$order		= $in["order"];			// 주문 정보
		$product	= $in["product"];		// 주문 상품 정보
		$partner = $product["partner"];

		$params = array();
		$params["shipping_seq"] 							= $product["shipping_seq"];		// shipping->save_shipping 에서 생성
		$params["goods_seq"]								= $product["productId"];		// 카카오에서 넘어오는 값
		$params["order_seq"]								= $product["order_seq"];		// orderlibrary->save_order 에서 생성
		$params["goods_name"]								= $product["productName"];		// 카카오에서 넘어오는 값
		$params["talkbuy_order_id"]							= $order["orderId"];			// 카카오 주문 id

		/**
		 * 구버전에서 사용하던 파라미터 사용안함
		 * goods_shipping_cost , shipping_policy, shipping_unit, basic_shipping_cost, add_shipping_cost, provider_name
		 */
		$params["multi_discount_ea"]						= 0;						// 대량 구매 할인
		$params["tax"]										= $partner["tax"] ? $partner["tax"] : "tax";		// partner 의 tax 없으면 기본 'tax'
		$params["goods_type"]								= "goods";
		$params["goods_kind"]								= "goods";

		$params["hscode"]									= $product["personalClearanceCode"] ? $product["personalClearanceCode"] : "";
		$params["option_international_shipping_status"]		= $product["internationalShipping"] ? "y" : "n";

		// partner 정보 
		$params["referer_domain"]							= $partner["referer_domain"];
		$params["bs_seq"]									= $partner["bs_seq"];
		$params["bs_type"]									= $partner["bs_type"];
		
		// 입점사 정보
		if(isset($partner["provider_seq"])) {
			$params["provider_seq"] 						= $partner["provider_seq"];		// partner의 provider_seq 없으면 shipping_group으로select 필요
		} else {
			$goods_data = $this->CI->goodsmodel->get_goods_only(array('goods_seq'=>$params["goods_seq"]));
			$goods_data = $goods_data->row_array();
			$params["provider_seq"] 						= $goods_data["provider_seq"];
		}

		// 상품 이미지
		$_images = $this->CI->goodsmodel->get_goods_image($params["goods_seq"],array('cut_number'=>1,'image_type'=>'thumbCart'));
		$params["image"] = $_images['1']['thumbCart']['image'];

		return $params;
	}

	/**
	 * 주문 아이템 옵션 생성
	 */
	function set_order_item_option($in) {
		if(!$this->CI->goodsmodel)			$this->CI->load->model("goodsmodel");

		$order		= $in["order"];			// 주문 정보
		$product	= $in["product"];		// 주문 상품 정보
		$partner	= $product["partner"];		// 주문 당시의 상품 정보

		$goods_data = $this->CI->goodsmodel->get_goods_only(array('goods_seq'=>$product["productId"]));			// 현재 상품정보
		$goods_data = $goods_data->row_array();

		$params 	= array();
		$params["order_seq"] 			= $product["order_seq"];			// orderlibrary->save_order 에서 생성
		$params["item_seq"]				= $product["item_seq"];				// orderlibrary->save_row_order_item 에서 생성
		$params["shipping_seq"]			= $product["shipping_seq"];			// shipping->save_shipping 에서 생성
		$params["provider_seq"] 		= $partner["provider_seq"];			// partner의 provider_seq 없으면 shipping_group으로select 필요

		
		$price 							= $product["price"];			// 실결제금액
		$sale_price						= $partner['event_sale'] + $partner['multi_sale'] + $partner['member_sale'] + ($partner['like_sale']/$partner['ea']) + ($partner['referer_sale']/$partner['ea'])	+ ($partner['mobile_sale']/$partner['ea']);// 할인금액
		$discount_price 				= $price - $sale_price;
		
		$params["price"]				= $partner["price"];
		$params["ori_price"]			= $partner["price"];
		$params['sale_price'] 			= $discount_price;
		$params["ea"]					= $product["quantity"];
		$params["ship_message"]			= $product["deliveryMessage"];

		/**
		 * 옵션명, 옵션값은 kakao 그대로 사용
		 * 옵션사용안함 > 옵션명='옵션선택', 옵션값=%상품명%
		 * 옵션 있는 상품만 title1~5, option1~5 넣어줌
		 */
		if($product["selectName"] != "옵션선택" && $product["selectText"] != $product["productName"]) {			
			$selectNames = explode(",", $product["selectName"]);
			$selectTexts = explode(",", $product["selectText"]);
			for($i=0; $i < count($selectNames); $i++) {
				$key = $i+1;
				$params["title".$key] = $selectNames[$i];
				$params["option".$key] = $selectTexts[$i];
			}
		}

		/**
		 * partner 데이터가 없으면 수집 당시의 상품정보로 insert 해야함
		 * 이 때에 kakao 에 있는 정보를 최우선으로 하고
		 * kakao에 없는 정보는 partner 데이터
		 * partner 에 없는 데이터면 select 후 insert
		 */
		if(!empty($partner)) {
			$params['optioncode1']		= $partner['optioncode1'];
			$params['optioncode2']		= $partner['optioncode2'];
			$params['optioncode3']		= $partner['optioncode3'];
			$params['optioncode4']		= $partner['optioncode4'];
			$params['optioncode5']		= $partner['optioncode5'];
		} else {
			/**
			 * 카카오채널에서 구매 시에는 partner 정보가 없기 때문에 현재 정보로 insert
			 */
			list($params['optioncode1'],$params['optioncode2'],$params['optioncode3'],$params['optioncode4'],$params['optioncode5']) = $this->CI->goodsmodel->get_goods_option_code(
				$params['goods_seq'],
				$params['option1'],
				$params['option2'],
				$params['option3'],
				$params['option4'],
				$params['option5']
			);
		}
		
		// 물류관리 버전이고 과세상품이면 평균매입가에 부가세를 포함시키고 매입처상품명을 주매입처정보에서 가져옴.
		if( $this->CI->scm_cfg['use'] == 'Y' ){
			if(!$this->CI->scmmodel)			$this->CI->load->model("scmmodel");
			$sc = array();
			$sc['option_seq'] = $product['selectId'];
			$sc['goods_seq'] = $product['productId'];
			list($data_defaultinfo) = $this->CI->scmmodel->get_order_defaultinfo($sc);
			$params['purchase_goods_name']	= $data_defaultinfo['supply_goods_name'];
			if	($data['tax']){
				$partner['supply_price']	= $partner['supply_price'] + round($partner['supply_price'] * 0.1);
				$partner['supply_price']	= $this->CI->scmmodel->cut_exchange_price($this->CI->config_system['basic_currency'], $partner['supply_price']);
			}
		}
		$params["consumer_price"]				= $partner["consumer_price"];
		$params["supply_price"]					= $partner["supply_price"];
		$params['commission_type']				= $partner['commission_type'];
		$params['commission_rate']				= $partner['commission_rate'];
		$params['commission_price']				= $partner['commission_price'];
		
		$params['tax']							= $partner['tax'];
		$params['basic_sale']					= $partner['basic_sale'];
		$params['package_yn'] 					= $goods_data['package_yn'];

		/**
		 * 좋아요 할인, 유입경로 할인, 이벤트 할인, 복수구매 할인, 멤버할인(비회원기준) 적용 
		 */
		$params["member_sale"]					= $partner["member_sale"];
		$params["fblike_sale"]					= $partner["like_sale"];
		$params["referer_sale"]					= $partner["referer_sale"];
		$params['salescost_provider_referer']	= $partner['salescost_provider_referer'];	// 입점사 유입경로 할인 부담"액"
		$params['event_sale_target']			= $partner['event_sale_target'];
		$params['event_sale']					= $partner['event_sale'];
		$params['multi_sale']					= $partner['multi_sale'];
		$params['referer_sale_unit']			= $partner['referer_sale_unit'];
		$params['mobile_sale_unit']				= $partner['mobile_sale_unit'];
		$params['original_price']				= $partner['original_price'];
		$params['goods_price']					= $partner['goods_price'];
		$params['sale_price']					= $partner['sale_price'];
		
		/**
		 * 톡체크아웃 관련 필드 저장
		 */
		$params['talkbuy_order_id'] 			= $order['orderId'];
		$params['talkbuy_product_order_id'] 	= $product['id'];
		$params['talkbuy_packagenumber'] 		= $product['shippingFeeGroupId'];
		
		// 옵션코드
		$params['goods_code'] = $this->getOrderItemCode($partner);

		/** 
		 * 사용안함 
		 * org_price, supply_price_krw, commission_price_krw, unit_emoney, unit_cash, unit_enuri, provider_name
		 * 외부 주문에서는 사용안함
		 * reserve, reserve_log, point, point_log, download_seq, coupon_sale, coupon_sale_krw, promotion_code_sale, promotion_code_sale_krw, promotion_code_seq, download_seq
		 * 티켓 주문에서만 사용하는 필드는 제외
		 * newtype, color, zipcode, address_type, address, address_street, addressdetail, biztel, codedate, sdayinput, fdayinput, dayauto_type, sdayauto, fdayauto
		 * social_start_date, social_end_date, coupon_input, coupon_input_one, coupon_inputuse, address_commission
		 */
	
		return $params;
	}

	// 품목코드(상품코드) 구하기
	private function getOrderItemCode($partner)
	{
		$goods_code = '';
		
		$goods_code = $partner['goods_code'];

		// 옵션코드 전부 붙여준다
		for ($i = 1; $i < 6; $i++) {
			if (
				isset($partner['optioncode' . $i]) === true
				&& strlen($partner['optioncode' . $i]) > 0
			) {
				$goods_code .= $partner['optioncode' . $i];	
			}
		}

		return $goods_code;
	}

	/**
	 * 주문 추가옵션 데이터 생성
	 */
	function set_order_item_suboption($in) {
		if(!$this->CI->goodsmodel)			$this->CI->load->model("goodsmodel");

		$order		= $in["order"];				// 퍼스트몰 주문 정보
		$product	= $in["product"];			// 주문 상품 정보
		$partner	= $product["partner"];		// 주문 당시의 상품 정보
		
		$goods_data = $this->CI->goodsmodel->get_goods_only(array("goods_seq"=>$product["productId"]));
		$goods_data = $goods_data->row_array();
		$suboption_data = $this->CI->goodsmodel->get_suboption(
			array("goods_seq"=>$product["productId"],"suboption_seq"=>$product["selectId"])
		);			// 현재 옵션상품정보
		$suboption_data = $suboption_data->row_array();

		$params = array();
		$params["item_option_seq"] 		= $product["item_option_seq"];
		$params["order_seq"]			= $product["order_seq"];
		$params["item_seq"]				= $product["item_seq"];

		$price 							= $product["price"];			// 실결제금액
		$sale_price						= $partner['event_sale'] + $partner['multi_sale'] + $partner['member_sale'] + ($partner['like_sale']/$partner['ea']) + ($partner['referer_sale']/$partner['ea'])	+ ($partner['mobile_sale']/$partner['ea']);// 할인금액
		$discount_price 				= $price - $sale_price;
		
		$params["price"]				= $partner["price"];
		$params["ori_price"]			= $partner["price"];
		$params['sale_price'] 			= $discount_price;
		$params["ea"]					= $product["quantity"];
		
		
		/**
		 * 좋아요 할인, 유입경로 할인, 이벤트 할인, 복수구매 할인, 멤버할인(비회원기준) 적용 
		 */
		$params["member_sale"]					= $partner["member_sale"];
		$params["fblike_sale"]					= $partner["like_sale"];
		$params["referer_sale"]					= $partner["referer_sale"];
		$params['salescost_provider_referer']	= $partner['salescost_provider_referer'];	// 입점사 유입경로 할인 부담"액"
		$params['event_sale_target']			= $partner['event_sale_target'];
		$params['event_sale']					= $partner['event_sale'];
		$params['multi_sale']					= $partner['multi_sale'];
		$params['referer_sale_unit']			= $partner['referer_sale_unit'];
		$params['mobile_sale_unit']				= $partner['mobile_sale_unit'];
		$params['original_price']				= $partner['original_price'];
		$params['goods_price']					= $partner['goods_price'];
		$params['sale_price']					= $partner['sale_price'];
		$params['basic_sale']					= $partner['basic_sale'];


		$params["title"] 				= $product["selectName"];
		$params["suboption"] 			= $product["selectText"];
		$params["commission_price"] 	= $partner["commission_price"];
		$params["goods_code"] 			= $goods_data['goods_code'].$suboption_data['suboption_code'];//조합된상품코드
		$params["suboption_code"] 		= $suboption_data['suboption_code'];
		$package_count_sub = 'n';
		if($suboption_data['package_count']){
			$package_count_sub = 'y';
		}
		$params['package_yn'] 			= $package_count_sub;


		// 물류관리 버전이고 과세상품이면 평균매입가에 부가세를 포함시키고 매입처상품명을 주매입처정보에서 가져옴.
		if( $this->CI->scm_cfg['use'] == 'Y' ){
			if(!$this->CI->scmmodel)			$this->CI->load->model("scmmodel");
			$sc['suboption_seq'] 	= $suboption_data['suboption_seq'];
			$sc['goods_seq'] 		= $data_goods['goods_seq'];
			list($data_defaultinfo) = $this->CI->scmmodel->get_order_defaultinfo($sc);
			$params['purchase_goods_name']	= $data_defaultinfo['supply_goods_name'];
			if	($data['tax']){
				$partner['supply_price']	= $suboption_data['supply_price'] + round($suboption_data['supply_price'] * 0.1);
				$partner['supply_price']	= $this->CI->scmmodel->cut_exchange_price($this->CI->config_system['basic_currency'], $suboption_data['supply_price']);
			}
		}
		$params["consumer_price"]				= $partner["consumer_price"];
		$params["supply_price"]					= $partner["supply_price"];

		/**
		 * 톡체크아웃 관련 필드 저장
		 */
		$params['talkbuy_order_id'] 			= $order['orderId'];
		$params['talkbuy_product_order_id'] 	= $product['id'];
		$params['talkbuy_packagenumber'] 		= $product['shippingFeeGroupId'];

		/** 
		 * 사용안함 
		 * org_price, supply_price_krw, commission_price_krw, unit_emoney, unit_cash, unit_enuri,
		 * 외부 주문에서는 사용안함
		 * reserve, reserve_log, point, point_log, 
 		 * 티켓 주문에서만 사용하는 필드는 제외
		 * newtype, color, zipcode, address_type, address, address_street, addressdetail, biztel, codedate, sdayinput, fdayinput, dayauto_type, sdayauto, fdayauto
		 * social_start_date, social_end_date, coupon_input, coupon_input_one, coupon_inputuse, address_commission
		 */
		return $params;
	}

	/**
	 * 주문 입력옵션 데이터 생성
	 */
	function set_order_item_input($in) {
		$input			= $in["input"];			// 주문 입력옵션 정보
		$product		= $in["product"];		// 주문 상품 옵션 정보

		$params = array();
		$params["item_option_seq"] 	= $product["item_option_seq"];
		$params["order_seq"] 		= $product["order_seq"];
		$params["item_seq"] 		= $product["item_seq"];
		$params["type"] 			= "text";
		$params["title"] 			= $input["inputName"];
		$params["value"] 			= $input["inputText"];

		return $params;
	}

	/**
	 * 주문 데이터 생성
	 */
	function set_fm_order($order,$partnerOrder) {
		// order_seq : insert_order 생성
		// person_seq : 없음
		/**
		 * 결제금액은 original_settleprice, settleprice, payment_price, shipping_cost, memo 주문 등록 후 update HYEMCHECK
		 */
		$_GET['mode'] = 'direct'; // 주문 방식 | 기본 cart | choice : 선택구매, cart : 장바구니구매, admin : 관리자구매, direct : 바로구매
		$_POST['payment'] = $order["payment"];	// 결제수단
		//$_POST['depositor'] = $order['purchaserNickname'];
		// bank_account, emoney_use, emoney, cash_use, cash, enuri, member_seq 필요 없음.

		// 주문자&수신자
		$_POST['order_user_name'] 		= $order['purchaserNickname'] ? $order['receiverName'] : $order['receiverName'];
		$_POST['order_phone']			= explode("-",$order['purchaserPhoneNumber']); // 000-0000-0000 가공 필요
		$_POST['order_cellphone'] 		= array();
		$_POST['order_email'] 			= '';		// 메일 없으므로 공란으로 
		$_POST['recipient_user_name'] 	= $order['receiverName'];
		$_POST['recipient_phone'] 		= explode("-",$order['receiverMobileNumber']);
		$_POST['recipient_cellphone'] 	= explode("-",$order['receiverMobileNumber']);

		// 결제수단
		$params['pgCompany'] = "talkbuy";
		$params['krw_exchange_rate'] = get_exchange_rate("KRW");

		// 배송지
		$_POST['address_nation_key'] = 'KOR';
		$_POST['international'] = 0;			// 국내
		$_POST['shipping_method'] = $order['shipping_method']; 
		$_POST['recipient_new_zipcode'] = $order['zipCode'];
		$_POST['recipient_address_type'] = $order['isRoadNameAddress'] == "1" ? "street" : "zibun";
		$_POST['recipient_address'] = $order['baseAddress'];
		$_POST['recipient_address_street'] = $order['baseAddress'];
		$_POST['recipient_address_detail'] = $order['detailAddress'];
		$params['shipping_cost'] = "0";		// 개별 배송비 체크 필요
		$_POST['memo'] =  $order['deliveryMessage'];

		//download_seq , coupon_sale, typereceipt 처리 안함.
		$_POST['overwrite_sitetype'] = "P";  // 카카오에서 전달해주지 않음. HYEMCHECK
		$_COOKIE['marketplace'] = $partnerOrder['marketplace']; // partner_order_detail.referer HYEMCHECK
		$_COOKIE['refererDomain'] = $partnerOrder['referer_domain']; // partner_order_detail.referer HYEMCHECK
		$_COOKIE['shopReferer'] = $partnerOrder['referer']; // partner_order_detail.referer HYEMCHECK

		$_POST['clearance_unique_personal_code'] = (strlen($order['personalClearanceCode']) > 0) ? $order['personalClearanceCode'] : '';

		$params['talkbuy_order_id'] = $order["orderId"];
		$params['talkbuy_order_date'] = $order["orderDateTime"];
		$params['talkbuy_paid_date'] = $order["paidDateTime"];
		$params["settle_price"]		= $order['settlePrice'];		// 주문 결제 금액 

		return $params;
	}

	
	/**
	 * 주문 배송 데이터 생성
	 */
	function set_fm_order_shipping($in) {
		$order_seq 		= $in["order_seq"];
		$order 			= $in["order"];
		$product 		= $in["product"];
		$partnerOrder 	= $in["partnerOrder"];

		// shipping_group 가져와서 provider_seq 넣어주기
		$partnerShippingData = unserialize($partnerOrder["shipping_cfg"]);

		$params = array();
		$params["order_seq"] = $order_seq;
		$params["provider_seq"] = $partnerShippingData["baserule"]["shipping_provider_seq"] ? $partnerShippingData["baserule"]["shipping_provider_seq"] : "1";

		if($product["shipping_type"] == "prepay") {
			$params["shipping_cost"] = $product['shippingFee'];
			$params["shipping_cost_krw"] = get_currency_exchange($params["shipping_cost"],"KRW",$this->CI->config_system["basic_currency"]);
			$params["postpaid"] = 0;
		} else if($product["shipping_type"] == "postpaid") {
			$params["shipping_cost"] = 0;
			$params["shipping_cost_krw"] = 0;
			$params["postpaid"] = $product['shippingFee'];
		} else if($product["shipping_type"] == "free") {
			$params["shipping_cost"] = 0;
			$params["shipping_cost_krw"] = 0;
			$params["postpaid"] = 0;
		}

		$params['delivery_if'] = $partnerShippingData["std"][1]["section_st"] ? $partnerShippingData["std"][1]["section_st"] : "0";	//무료배송 조건금액

		$params["delivery_cost"] = $product["baseShippingFee"];
		$params["international_cost"] = 0;

		// 추가 배송비
		if((int)$product["surchargeArea"] > 0) {
			$params['add_delivery_cost'] = $product["surchargeArea"];
			$params['add_delivery_area'] = implode(" ",array_slice(explode(" ",$order["baseAddress"]), 0, 3));
		}

		// 매장 방문
		if($product["deliveryMethod"] == "VISIT") {
			$params['store_scm_type'] = $partnerShippingData['store_info']['store_scm_type'];
			$params['shipping_address_seq'] = $partnerShippingData['store_info']['shipping_address_seq'];
		}
		
		$params['shipping_group'] = $product['groupId'];

		$params['shipping_method'] = $product["shipping_method"];
		$params['shipping_type'] = $product["shipping_type"];
		$params['shipping_set_name'] = $partnerShippingData['baserule']['shipping_set_name'];

		$params["talkbuy_packagenumber"] 	= $product['shippingFeeGroupId'];

		// shipping->save_shipping 용 파라미터 생성
		$params["shipping_group_seq"] 		= $partnerShippingData['baserule']["shipping_group_seq"];
		$params["seq_list"]					= $product['shippingFeeGroupId'];


		$result["shippingInfoList"] = array($params["shipping_group_seq"] => $partnerShippingData['baserule']);
		$result["shippingGroupList"] = array($params);


		return $result;
	}

	function proc_talkbuy_order($order) {
		if(!$this->CI->orderlibrary)		$this->CI->load->library("orderlibrary");
		if(!$this->CI->shipping)			$this->CI->load->library('shipping');
		if(!$this->CI->ordermodel)			$this->CI->load->model('ordermodel');
		if(!$this->CI->talkbuy_sendlibrary)	$this->CI->load->library('talkbuy_sendlibrary');

		// 0. 트랜잭션 시작
		$this->CI->db->trans_begin();

		$order_export_code["export"] = $order_export_code["buyconfirm"] = array();
		$buyconfirm_export_code = $delivery_export_code = array();

		/**
		 * 주문 정보 조회
		 */
		$fmOrder = $this->CI->partnerlib->getOrder($order["orderId"], "talkbuy");
		foreach($order['orderProduct'] as $key => $product) {
			$product["step"] = (int)$product["step"];
			// 카카오톡 주문이 주문접수이고, 솔루션은 주문접수 전 단계일 때
			if($product["step"] == 15 && $fmOrder["step"] < 15 ) {
				// 첫번째 주문 상품 수집 시에만 주문접수 처리
				if(!in_array($fmOrder["order_seq"], $wait_order_seq)) {
					unset($in);
					$in["orderSeq"] 				= $fmOrder["order_seq"];
					$in["addLogParams"] 			= $this->baseLogParams;
					$in["addLogParams"]["title"] 	= "주문접수(API)(".$this->CI->ordermodel->arr_payment[$order["payment"]].")";
					$in["addLogParams"]["detail"] 	= $this->baseLogDetail.$in["addLogParams"]["title"]." 하였습니다.";
					// 주문접수 처리 후 프로세스 실행
					$this->CI->orderlibrary->proc_order_after_success($in);
					$wait_order_seq[] = $fmOrder["order_seq"];
				} 
			}

			// 카카오톡주문이 결제확인 ~ 결제취소 인 경우
			if($product["step"] > 15 && $product["step"] < 95) {
				// 솔루션 주문 결제확인 이전 일 때 결제확인 처리
				if($fmOrder["step"] < 25) {
					// 첫번째 주문 상품 시에만 결제확인 처리
					if(!in_array($fmOrder["order_seq"], $deposit_order_seq)) {
						$mpayment = $this->CI->ordermodel->arr_payment[$order["payment"]];
						if($order["payment"] == "point") $mpayment = "카카오머니";
						unset($in);
						$in["orderSeq"] 				= $fmOrder["order_seq"];
						$in["addLogParams"] 			= $this->baseLogParams;
						$in["addLogParams"]["title"] 	= "결제확인(API)(".$mpayment.")";
						$in["addLogParams"]["detail"] 	= $this->baseLogDetail.$in["addLogParams"]["title"]." 하였습니다.";
						// 결제확인 처리한 주문번호 저장
						$this->CI->orderlibrary->proc_order_after_payment($in);
						$deposit_order_seq[] = $fmOrder["order_seq"];
					} 
					/**
					 * 발주확인용 싱픔주문번호 저장, 카카오 주문상품 단위로 모두 발주확인 처리
					 */
					$confirm_order_product_id[] = $product["id"];
					$save_order_list[$product["id"]] = $fmOrder["order_seq"];
					if($product["suboption"]) {		// 추가옵션 있는 경우 별도 처리
						foreach($product["suboption"] as $suboption) {	
							$confirm_order_product_id[] = $suboption["id"];
							$save_order_list[$suboption["id"]] = $fmOrder["order_seq"];
						}
					}
				}
			}

			// 카카오주문이 배송중 이후인 경우 처리
			if($product["step"] >= 65 && $product["step"] <= 75) {
				
				// 주문상품번호로 등록된 출고건 있는지 확인
				$item_in = [];
				$item_in['order_seq'] 		= $fmOrder["order_seq"];
				// 상품확인
				if ($product['orderStatusProcessType'] === 'product') {
					$item_in["option_type"] 	= "opt";
					$item_in['id'] 				= $product["id"];
				// 추가옵션 상품 확인
				} elseif($product['orderStatusProcessType'] === 'suboption') {
					$item_in["option_type"] = 'sub';
					$item_in['id'] = $product['suboption'][0]['id'];
				}	
				$fmExportItem = $this->CI->partnerlib->getExportItem($item_in, "talkbuy");

				if(!isset($fmExportItem)) {
					// 출고건 없는 경우 출고 만들 데이터 생성 $export_order_product	>> proc_order_export
					if($product["step"] == 75 && $product["status"] == "PURCHASE_DECISION") {
						// 카카오 주문이 구매 결정인 경우 구매확정 주문건으로 별도 처리
						$export_order_product['buyconfirm'][] = $product;
					} else {
						// 카카오 주문이 배송중인 경우
						$export_order_product['export'][] = $product;
					}

					// 프로세스 변경한 주문건은 return
					$save_order_list[$product["id"]] = $fmOrder["order_seq"];
					if($product["suboption"]) {
						foreach($product["suboption"] as $suboption) {	
							$save_order_list[$suboption["id"]] = $fmOrder["order_seq"];
						}
					}
				} else {
					// 출고상태가 배송중 이전이면 배송중 처리
					if($fmExportItem["step"] < 65) {
						$delivery_export_code[] = $fmExportItem["export_code"];
						$save_order_list[$product["id"]] = $fmOrder["order_seq"];
						if($product["suboption"]) {
							foreach($product["suboption"] as $suboption) {	
								$save_order_list[$suboption["id"]] = $fmOrder["order_seq"];
							}
						}
					}
					// 카카오 주문이 구매확정이면
					if($product["step"] == 75 && $product["status"] == "PURCHASE_DECISION" && $fmExportItem["step"] != "75") {
						/** 솔루션 출고 건 구매확정 여부 체크*/
						if($fmExportItem["reserve_ea"] > 0) {
							$buyconfirm_export_code[] = $fmExportItem["export_code"];
							/**
							 * 추가상품(서브옵션) 데이터만 전달 됬더라도 $product["id"] 데이터를 만들어 주기 때문에 본상품이 맞는지 orderStatusProcessType 값으로 확인 한다.
							 */
							if($product['orderStatusProcessType'] === 'product') {
								$save_order_list[$product["id"]] = $fmOrder["order_seq"];
							}
							if($product["suboption"]) {
								foreach($product["suboption"] as $suboption) {
									$save_order_list[$suboption["id"]] = $fmOrder["order_seq"];
								}
							}
						}
					}
				}
			}

			// 미입금 취소 95
			if($product["step"] == "95") {
				// 첫번째 주문 상품 시에만 미입금 취소 처리
				if($fmOrder["step"] != "95") {
					if(!in_array($fmOrder["order_seq"], $cancel_order_seq)) {
						$in["orderSeq"] 				= $fmOrder["order_seq"];
						$in["addLogParams"] 			= $this->baseLogParams;
						$in["addLogParams"]["title"] 	= "미입금취소(API)";
						$in["addLogParams"]["detail"] 	= $this->baseLogDetail." 미입금취소(API) 하였습니다.";
						// 주문 무효 처리
						$this->CI->orderlibrary->proc_order_cancel($in);
						$cancel_order_seq[] = $fmOrder["order_seq"];
					}
				}
				$save_order_list[$product["id"]] = $fmOrder["order_seq"];
				foreach($product["suboption"] as $suboption) {	
					$save_order_list[$suboption["id"]] = $fmOrder["order_seq"];
				}
			}

			if(isset($product["claim"]) === true && count($product["claim"]) > 0) {
				$claim_order_list['order'] = $order;
				$claim_order_list['fmOrder'] = $fmOrder;
				$claim_order_list['products'] = $order['orderProduct'];
			}
			
			// 본상품의 클레임건이 존재하지않으면서 추가상품만 클레임처리할 경우 클레임데이터 처리 배열 추가
			if(isset($product["claim"]) === false && isset($product["suboption"][0]["claim"]) === true && count($product["suboption"][0]["claim"]) > 0) {
				$claim_order_list['order'] = $order;
				$claim_order_list['fmOrder'] = $fmOrder;
				$claim_order_list['products'] = $order['orderProduct'];
			}
		}
		
		/**
		 * 출고 생성 시작
		 * $export_order_product["export"] : 배송중만 하고 종료함
		 * $export_order_product["buyconfirm"] : 배송중, 구매확정 실행
		 */
		if (count($export_order_product["export"]) > 0) {
			$order_export_code["export"] = $this->proc_order_export($fmOrder["order_seq"], $export_order_product["export"], $order);
		}
		if(count($export_order_product["buyconfirm"]) > 0) {
			$order_export_code["buyconfirm"] = $this->proc_order_export($fmOrder["order_seq"], $export_order_product["buyconfirm"], $order);
		}
		/**
		 * 출고 생성 종료
		 */

		// 배송중 처리 -- 이미 출고완료(delivery_export_code)인건과 출고생성(order_export_code)한건 합쳐서 
		$delivery_export_code = $order_export_code["export"] + $order_export_code["buyconfirm"] + $delivery_export_code;
		if(count($delivery_export_code) > 0) {
			$this->proc_order_going_delivery($delivery_export_code);
		}

		// 구매확정 처리
		$buyconfirm_export_code = $order_export_code["buyconfirm"] + $buyconfirm_export_code;
		if(count($buyconfirm_export_code) > 0) {
			$this->proc_order_buy_confirm($buyconfirm_export_code);
		}

		if ($this->CI->db->trans_status() === false)
		{
			writeCsLog("proc_talkbuy_order trans_status IS FALSE", "process" , "talkbuy");
			writeCsLog($this->CI->error, "process" , "talkbuy");
			$this->CI->db->trans_rollback();
			return false;
		}

		$this->CI->db->trans_commit();

		/**
		 * 실제 카카오에 발주확인 처리
		 */
		foreach($confirm_order_product_id as $order_product_id) {
			$params = array("orderProductId" => $order_product_id);
			$result = $this->CI->talkbuy_sendlibrary->sendMethod("orderConfirm",$params);

			$log_params 			= $this->baseLogParams;
			$log_params["type"] 	= 'process';			
			// 실패 시 message 리턴됨
			if(is_array($result) && $result["message"]) {
				$log_params["title"] 	= "발주처리실패(API)";
				$log_params["detail"] 	= "[".$order_product_id."] 발주처리(API) 실패 하였습니다.(".$result["message"].")";
			} else {
				$log_params["title"] 	= "발주처리성공(API)";
				$log_params["detail"] 	= "[".$order_product_id."] 발주처리(API) 성공 하였습니다.";
			}
			$this->CI->orderlibrary->set_log($fmOrder["order_seq"],$log_params);
		}

		// 클레임 처리
		if(count($claim_order_list) > 0) {
			$this->proc_order_claim($claim_order_list);
		}

		return $save_order_list;
	}

	/**
	 * 클레임 처리
	 * 클레임 데이터는 하나의 주문건별 필수옵션, 추가옵션별 데이터를 특정 format에 맞게 컨버팅하여 serialize 하여 처리할 수 있도록 한다.
	 */
	function proc_order_claim($orderInfo) {
		$order = $orderInfo["order"];
		$originFmOrder = $orderInfo["fmOrder"];
		

		$procClaimData = [];
		// 각 본상품, 추가상품의 데이터를 array 일렬화 fommatting 처리하도록 함
		foreach($orderInfo["products"] as $key => $product) {
			// product["id"] 가 없으면 본 상품 클레임 처리 하지 않음
			if ($product["orderStatusProcessType"] === "product") {
				// 본상품 클레임처리 데이터
				// 메인상품(필수옵션) 클레임처리건은 1건만 존재한다. (추가상품만 클레임처리할 수 있으므로 해당 데이터가 비어있을 수 있음)
				// 교환건에 의한 각 상품별 처리 fm_order 선택
				$orderId = $product["claim"]["claimDetail"]["orderId"];
				$fmOrder = $this->_fmOrderSelector($originFmOrder, $orderId, $product, "option");
				$orderSeq = $fmOrder["order_seq"];

				$fmOrderShipping = $this->CI->partnerlib->getOrderShipping(array("order_seq"=>$orderSeq), $product['shippingFeeGroupId'], "talkbuy");
				$fmOrderShipping = array_pop($fmOrderShipping);
				
				// getItemData
				$item_in = array();
				$item_in['order_seq'] = $orderSeq;
				$item_in["shipping_seq"] = $fmOrderShipping["shipping_seq"];
				$item_in['goods_seq'] = $product["productId"];
				$item_in['orderId'] = $orderId;
				
				$orderItem = $this->CI->partnerlib->getOrderItem($item_in, "talkbuy");

				// 옵션 params
				$option_in = array();
				$option_in["order_seq"] 		= $orderSeq;
				$option_in["shipping_seq"] 		= $fmOrderShipping["shipping_seq"];
				$option_in["item_seq"] 			= $orderItem["item_seq"];
				$option_in["id"] 				= $product["id"];
				$option_in["orderId"] 			= $order["orderId"];
				$option_in["packageNumber"] 	= $product["shippingFeeGroupId"];

				$itemOptionData = $this->CI->partnerlib->getOrderItemOption($option_in, "talkbuy");

				// 필수옵션, 추가옵션은 관계없이, 필수옵션 데이터를 serialize 배열에 담을 때, suboption 은 배제하고 enqueue 한다.
				$exceptSuboptionProduct = $product;
				unset($exceptSuboptionProduct["suboption"]);

				// 출고 params
				$export_in = array();
				$export_in['order_seq'] = $orderSeq;
				$export_in['id'] = $product["id"];
				

				$mainTempArr = [
					"claimProductType" => "main",
					"fmOrder" => $fmOrder,
					"fmOrderItem" => $orderItem,
					"fmOrderShipping" => $fmOrderShipping,
					"fmExportItem" => $this->CI->partnerlib->getExportItem($export_in, "talkbuy"),
					"productInfo" => $exceptSuboptionProduct,
					"option" => $itemOptionData,
				];

				array_push($procClaimData, $mainTempArr);
				unset($mainTempArr);
			}

			if ($product["suboption"] && count($product["suboption"]) > 0) {
				// 추가상품 클레임처리 데이터                                      
				foreach($product["suboption"] as $data) {
					// 추가상품 클레임처리건은 다수 존재할 수 있다.

					// 교환건에 의한 각 상품별 처리 fm_order 선택
					$orderId = $data["claim"]["claimDetail"]["orderId"];
					$fmOrder = $this->_fmOrderSelector($originFmOrder, $orderId, $data, "suboption");
					$orderSeq = $fmOrder["order_seq"];

					$fmOrderShipping = $this->CI->partnerlib->getOrderShipping(array("order_seq"=>$orderSeq), $product['shippingFeeGroupId'], "talkbuy");
					$fmOrderShipping = array_pop($fmOrderShipping);
					
					// getItemData
					$item_in = array();
					$item_in['order_seq'] = $orderSeq;
					$item_in["shipping_seq"] = $fmOrderShipping["shipping_seq"];
					$item_in['goods_seq'] = $product["productId"];
					$item_in['orderId'] = $orderId;

					$orderItem = $this->CI->partnerlib->getOrderItem($item_in, "talkbuy");

					// 옵션 params
					// TODO (kjw) : 하나의 option 데이터 조회로 같이 사용할 수 있도록 수정
					$option_in = array();
					$option_in["order_seq"] 		= $orderSeq;
					$option_in["shipping_seq"] 		= $fmOrderShipping["shipping_seq"];
					$option_in["item_seq"] 			= $orderItem["item_seq"];
					$option_in["id"] 				= $product["id"];
					$option_in["orderId"] 			= $orderId;
					$option_in["packageNumber"] 	= $product["shippingFeeGroupId"];
					$tempItemOptionData = $this->CI->partnerlib->getOrderItemOption($option_in, "talkbuy");

					// SUB 옵션 params
					$suboption_in = array();
					$suboption_in["order_seq"] = $orderSeq;
					$suboption_in["shipping_seq"] = $fmOrderShipping["shipping_seq"];
					$suboption_in["item_seq"] = $orderItem["item_seq"];
					$suboption_in["id"] = $data["id"];
					$suboption_in["orderId"] = $orderId;
					$suboption_in["option_seq"] = $tempItemOptionData["item_option_seq"];
					$suboption_in["packageNumber"] = $product["shippingFeeGroupId"];
					// 출고 params
					$export_in = array();
					$export_in['order_seq'] = $orderSeq;
					$export_in['id'] = $data["id"];
					
					$subTempArr = [
						"claimProductType" => "sub",
						"fmOrder" => $fmOrder,
						"fmOrderItem" => $orderItem,
						"fmOrderShipping" => $fmOrderShipping,
						"fmExportItem" => $this->CI->partnerlib->getExportItem($export_in, "talkbuy"),
						"productInfo" => $data,
						"option" => $this->CI->partnerlib->getOrderItemSubOption($suboption_in, "talkbuy"),
					];

					array_push($procClaimData, $subTempArr);
					unset($subTempArr);
				}
			}
		}

		foreach ($procClaimData as $key => $claimData) {
			writeCsLog(["data" => json_encode($claimData)], "request_claim_data", "talkbuy");
			$claimType = $claimData["productInfo"]["claim"]["claimType"];
			if (!isset($claimData["productInfo"]["claim"]["claimType"])) {
				writeCsLog(["msg" => "not exist claim_type", "data" => $claimData["productInfo"]["claim"]], "claim-err" , "talkbuy");
				continue;
			}
			if ($claimType === self::CT_CANCEL) {
				$this->proc_refund_process($claimData);
			} else if ($claimType === self::CT_RETURN) {
				$this->proc_return_process($claimData);
			} else if ($claimType === self::CT_EXCHANGE) {
				$this->proc_exchange_process($claimData);
			} else {
				writeCsLog("proc_order_claim claim_type", "claim-err" , "talkbuy");
				return false;
			}
		}
	}

	/**
	 * 환불(취소) 처리
	 */
	public function proc_refund_process(array $claim) {
		// 0. 트랜잭션 시작
		$this->CI->db->trans_begin();
		// 취소요청
		if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_CANCEL_REQUEST) {
			$response = $this->CI->kakaoclaim->setOrderRefund($claim);
		// 취소완료
		} else if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_CANCEL_DONE) {
			$response = $this->CI->kakaoclaim->setOrderRefundComplete($claim);
		//취소철회
		} else if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_CANCEL_REJECT) {
			$response = $this->CI->kakaoclaim->setRejectionRefund($claim);
		} else {
			writeCsLog(["msg" => "Do Not Support claimProcessType", "data" => $claim["productInfo"]["claim"]["claimProcessType"]], "claim-err" , "talkbuy");
			return false;
		}
		if ($this->CI->db->trans_status() === false)
		{
			writeCsLog("proc_refund_process trans_status IS FALSE", "claim-err" , "talkbuy");
			writeCsLog($this->CI->db, "claim-err" , "talkbuy");
			$this->CI->db->trans_rollback();
			return false;
		}
		$this->CI->db->trans_commit();
		return;
	}

	/**
	 * 반품 처리
	 */
	public function proc_return_process(array $claim) {
		// 0. 트랜잭션 시작
		$this->CI->db->trans_begin();
		if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_RETURN_REQUEST) {
			$refund = $this->CI->kakaoclaim->setOrderRefund($claim);
			if($refund["success"] !== true || !$refund["data"]["refund_code"]) {
				writeCsLog(["msg" => "refund sucess is false", "data" => $refund], "claim-err" , "talkbuy");
				return;
			}
			// 반품 & 교환 요청
			$return = $this->CI->kakaoclaim->setOrderReturnOrExchange($claim);
		} else if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_RETURN_DONE) {
			$existReturnData = $this->CI->kakaoclaim->alreadyExistReturn($claim["productInfo"]["claim"], $claim["fmOrderItem"]["item_seq"]);
			// 교환요청 -> 반품완료(전환) 시나리오 (반품완료에선 교환전환 프로세스는 없음)
			// 반품&교환 철회 -> 환불완료(환불요청포함) -> 반품완료(반품요청포함)
			if ( 
				$existReturnData["return"]["return_type"] === "exchange"
				&& !$existreturnData["return"]["return_date"]
			) {
				$this->CI->kakaoclaim->setRejectionReturnOrExchange($claim);
				// 반품 & 교환 완료
				$this->CI->kakaoclaim->setOrderReturnComplete($claim);
					
				$logParams = [
					"type" => 'process',
					"title" => "반품전환(교환요청)",
					"detail" => "Kakao로 부터 반품전환(교환요청) 되었습니다.",
				];
				$this->CI->orderlibrary->set_log($claim["fmOrder"]["order_seq"], array_merge($this->baseLogParams, $logParams));
			} else {
				// 반품 & 교환 완료
				$this->CI->kakaoclaim->setOrderReturnComplete($claim);
			}
			$this->CI->kakaoclaim->setOrderRefundComplete($claim);
		} else if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_RETURN_REJECT) {
			$this->CI->kakaoclaim->setRejectionRefund($claim);
			$this->CI->kakaoclaim->setRejectionReturnOrExchange($claim);
		} else {
			writeCsLog(["msg" => "Do Not Support claimProcessType", "data" => $claim["productInfo"]["claim"]["claimProcessType"]], "claim-err" , "talkbuy");
			return false;
		}
		if ($this->CI->db->trans_status() === false)
		{
			writeCsLog("proc_return_process trans_status IS FALSE", "claim-err" , "talkbuy");
			writeCsLog($this->CI->db, "claim-err" , "talkbuy");
			$this->CI->db->trans_rollback();
			return false;
		}
		$this->CI->db->trans_commit();
		return;
	}

	/**
	 * 교환 처리
	 */
	public function proc_exchange_process(array $claim) {
		// 0. 트랜잭션 시작
		$this->CI->db->trans_begin();
		if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_EXCHANGE_REQUEST) {
			// 반품 & 교환 요청
			$return = $this->CI->kakaoclaim->setOrderReturnOrExchange($claim);
		} else if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_EXCHANGE_BACKWARD_DELIVERY_DONE) {
			// 수거완료
			$this->CI->kakaoclaim->makeOrderExchangeReOrder($claim);
		} else if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_EXCHANGE_FORWARD_DELIVERY) {
			// 교환 재발송
			$this->CI->kakaoclaim->makeOrderExchangeReExport($claim);
		} else if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_EXCHANGE_DONE) {
			$existReturnData = $this->CI->kakaoclaim->alreadyExistReturn($claim["productInfo"]["claim"], $claim["fmOrderItem"]["item_seq"]);
			// 반품요청 -> 교환완료(전환) 시나리오	
			if ($existReturnData["return"] && ($existReturnData["return"]["return_type"] === "return" && !$existReturnData["return"]["talkbuy_return_complete_date"])) {
				$this->CI->kakaoclaim->setRejectionRefund($claim);
				$this->CI->kakaoclaim->setRejectionReturnOrExchange($claim);
					
				$logParams = [
					"type" => 'process',
					"title" => "교환전환(반품요청)",
					"detail" => "Kakao로 부터 교환전환(반품요청) 되었습니다.",
				];
				$this->CI->orderlibrary->set_log($claim["fmOrder"]["order_seq"], array_merge($this->baseLogParams, $logParams));
			}
			// 교환 완료
			$this->CI->kakaoclaim->setOrderExchangeComplete($claim);
		} else if ($claim["productInfo"]["claim"]["claimProcessType"] === self::CPT_EXCHANGE_REJECT) {
			$this->CI->kakaoclaim->setRejectionReturnOrExchange($claim);
		} else {
			writeCsLog(["msg" => "Do Not Support claimProcessType", "data" => $claim["productInfo"]["claim"]["claimProcessType"]], "claim-err" , "talkbuy");
			return false;
		}
		if ($this->CI->db->trans_status() === false)
		{
			writeCsLog("proc_exchange_process trans_status IS FALSE", "claim-err" , "talkbuy");
			writeCsLog($this->CI->db, "claim-err" , "talkbuy");
			$this->CI->db->trans_rollback();
			return false;
		}
		$this->CI->db->trans_commit();
		return;
	}

	/**
	 * fmOrderSelector
	 * 클레임처리는 카카오구매 중계서버 모든 요청에 대해 본상품(item_option), 추가상품(sub_option) 을 구분지어 모두 개별처리하도록 되어있다.
	 * 따라서, 각 option 들의 product_id 는 고유하고, 같은 product_id를 가지는데 동시 처리되는 order_seq 는 없다.
	 * serialize 되어있는 데이터이므로 각 option 의 최신 seq 들이 현재 상태라고 볼 수 있다.
	 * 따라서, 현재 처리되어야할 order_seq 는 option의 최신 seq 의 값으로 처리하도록 함.
	 */
	private function _fmOrderSelector(array $originFmOrder, string $kakaoOrderId, array $product, string $optionType = "option") {
		if(!$this->CI->ordermodel) $this->CI->load->model('ordermodel');
		$orderSelector = $originFmOrder;
		// 교환건데이터가 있는지 먼저확인
		$reFmOrder = $this->CI->partnerlib->getLastOrderByOrinOrderSeq($originFmOrder["order_seq"], $kakaoOrderId, "talkbuy");
		if ($reFmOrder) {
			$optionWheres = array();
			$optionWheres['orderId'] = $kakaoOrderId;
			$optionWheres["id"] = $product["id"];
			$optionWheres['packageNumber'] = $product["shippingFeeGroupId"];

			$optionData = null;

			if ($optionType === "option") {
				$optionData = $this->CI->partnerlib->getLastOrderItemOptionByProductId($optionWheres, "talkbuy");
			} else if ($optionType === "suboption") {
				$optionData = $this->CI->partnerlib->getLastOrderItemSubOptionByProductId($optionWheres, "talkbuy");
			}

			if ($optionData === null) {
				// error
				writeCsLog(["data" => $optionData, "wheres" => $optionWheres, "reFmOrder" => $reFmOrder], "claim-err", "claim");
				return;
			}

			$orderSelector = $this->CI->ordermodel->get_order($optionData["order_seq"]);
		}

		return $orderSelector;
	}
	
	/**
	 * 구매확정 처리
	 */
	public function proc_order_buy_confirm($buyconfirm_export_code) {
		if(!$this->CI->buyconfirmlib)	$this->CI->load->library('buyconfirmlib');
		$buyconfirm_export_code = array_unique($buyconfirm_export_code);
		$addParams["partner"] 	= $this->baseLogParams["add_info"];
		$addParams["actor"] 	= $this->baseLogParams["add_info"];
		$addParams["doer"] 		= "Kakao Pay";
		foreach($buyconfirm_export_code as $export_code) {
			$result = $this->CI->buyconfirmlib->exec_buyconfirm($export_code, $msg, $addParams);
			writeCsLog($export_code, "buyConfirm" , "talkbuy");
		}
	}

	/**
	 * 배송중 변경
	 */
	public function proc_order_going_delivery($delivery_export_code) {
		if(!$this->CI->exportmodel)	$this->CI->load->model('exportmodel');
		$delivery_export_code = array_unique($delivery_export_code);
		foreach($delivery_export_code as $export_code) {
			$this->CI->exportmodel->exec_going_delivery($export_code,$this->baseLogParams["add_info"]);
		}
	}

	/**
	 * 출고 생성
	 * 톡체크아웃으로 출고 진행되는건 상품별로 하나씩 출고 진행함
	 */
	public function proc_order_export($order_seq, $export_order_product, $order) {
		if(!$this->CI->order2exportmodel)	$this->CI->load->model('order2exportmodel');
		$order_export_data = $this->get_order_export_data($order_seq);	

		foreach($export_order_product as $product) {
			// 상품 낱개로 출고 하기위해서 데이터를 상품과 추가상품을 구분해서 나눈다
			$exportList = $this->productToSpliteSuboption($product);
			for ($i = 0; $i < count($exportList); $i++) {
				$product = $exportList[$i];
				// 주문서 처리방식이 "suboption"이면 '서브옵션상품 개별배송'이기 때문에 주문변경 처리를 건너뛴다.
				if ($product['orderStatusProcessType'] === 'suboption') {
					continue;
				}

				/**
				 * shipping_group, shipping_set_name : 네이버페이 NULL 로 저장되고있음.
				 * 검색이랑, 엑셀 다운로드에 관여하지 않음.
				 * scm 창고 정보는 현재 사용 안함
				 */
				$order_item = $order_export_data[$product["id"]];

				$export_item							= array();
				$export_item['item_seq'][]				= $order_item["item_seq"];
				$export_item['shipping_seq'][]			= $order_item["shipping_seq"];
				$export_item['option_seq'][]			= $order_item["option_seq"];
				$export_item['suboption_seq'][]			= $order_item["suboption_seq"];
				$export_item['ea'][]					= $order_item["ea"];
				$export_item['export_item_seq'][]		= $order_item["export_item_seq"];
				$export_item['talkbuy_product_order_id'][]	= $product["id"];
				$export_item['talkbuy_status'][]			= 'y';	// 톡체크아웃에서 인입되었기에 'y'

				$export_data = array(
					"goods_kind" => "goods",
					"status" => "55",
					"shipping_seq" => $shipping_seq,
					"order_seq" => $order_seq,
					"talkbuy_order_id" => $order["orderId"],
					"shipping_method" => $product["shipping_method"],
					"delivery_company_code" => $product["delivery_company"],
					"delivery_number" => $product["invoiceNo"],
					"export_date"	=> date("Y-m-d"),
					"regist_date"	=> date("Y-m-d H:i:s"),
					"complete_date"	=> date("Y-m-d H:i:s"),
					"shipping_provider_seq" => $order_item["shipping_provider_seq"],
					"items" => $export_item
				);

				$exp_res = $this->CI->order2exportmodel->export_for_goods(array($export_data),$cfg,'order_api');
				foreach($exp_res['55'] as $expcd => $exp_data_tmp){
					$export_code[] = $exp_data_tmp['export_code'];
				}
			}
			
		}

		return $export_code;
	}

	/**
	 * 카카오에서 배송중으로 상품과 추가상품들을 변경 하면 한번에 데이터가 넘어온다. (낱개로 넘어오는 경우도 있다)
	 * 상품과 추가상품을 각각 출고처리 위해 분할한다.
	 */
	private function productToSpliteSuboption($product)	{
		$list = [];

		$list[] = $product;

		// 서브옵션 없는경우
		if (isset($product['suboption']) === false) {
			return $list;
		}
		
		// 서브옵션들을 추가한다
		$subOptions = $product['suboption'];
		for ($i = 0; $i < count($subOptions); $i++) {
			$list[] = $subOptions[$i];
		}
		
		return $list;
	}

	/**
	 * 주문 데이터 이용하여 출고 데이터 생성
	 */
	public function get_order_export_data($order_seq) {
		// ===========================================================================
		// 출고 데이터 추출용 쿼리 생성 시작
		// ===========================================================================
		$this->CI->load->library('exportlibrary');
		$export_data = $this->CI->exportlibrary->get_order_export_data($order_seq);
		// ===========================================================================
		// 출고 데이터 추출용 쿼리 생성 종료
		// ===========================================================================
		foreach($export_data["ordershipping"] as $f_key => $f_val) {
			foreach($f_val as $s_key => $s_val) {
				$shipping_provider_seq = $s_val["provider_seq"];
				foreach($s_val["options"] as $option) {

					$result[$option["talkbuy_product_order_id"]] = array(
						'item_seq' 					=> $option["item_seq"],
						'shipping_seq' 				=> $option["shipping_seq"],
						'option_seq' 				=> $option["item_option_seq"],
						'suboption_seq' 			=> '',
						'ea' 						=> $option["ea"],
						'export_item_seq' 			=> $option["export_item_seq"],
						'talkbuy_product_order_id' 	=> $option["talkbuy_product_order_id"],
						'talkbuy_status' 			=> 'y',
						"shipping_provider_seq" 	=> $shipping_provider_seq,
					);
					foreach($option["suboptions"] as $suboption) {
						
						$result[$suboption["talkbuy_product_order_id"]] = array(
							'item_seq' 					=> $option["item_seq"],
							'shipping_seq' 				=> $option["shipping_seq"],
							'option_seq' 				=> $option["item_option_seq"],
							'suboption_seq' 			=> $suboption["item_suboption_seq"],
							'ea' 						=> $suboption["ea"],
							'export_item_seq'			=> $suboption["export_item_seq"],
							'talkbuy_product_order_id' 	=> $suboption["talkbuy_product_order_id"],
							'talkbuy_status' 			=> 'y',
							"shipping_provider_seq" 	=> $shipping_provider_seq,
						);
					}
				}
			}
		}
		return $result;
	}

	/**
	 * 배송지 정보 변경
	 */
	public function change_talkbuy_order($order) {
		// 0. 트랜잭션 시작
		$this->CI->db->trans_begin();

		$order_params['recipient_user_name']		= $order['receiverName'];
		$order_params['recipient_phone']			= $order['receiverMobileNumber'];
		$order_params['recipient_cellphone']		= $order['receiverMobileNumber'];
		$order_params['recipient_zipcode']			= $order['zipCode'];
		$order_params['recipient_address']			= $order['baseAddress'];
		$order_params['recipient_address_street']	= $order['baseAddress'];
		$order_params['recipient_address_detail']	= $order['detailAddress'];
		$order_params['recipient_address_type']		= $order['isRoadNameAddress'] == "1" ? "street" : "zibun";

		$fmOrder = $this->CI->partnerlib->getOrder($order["orderId"], "talkbuy");
		$this->CI->orderlibrary->set_order($fmOrder["order_seq"],$order_params);

		$log_params 			= $this->baseLogParams;
		$log_params["type"] 	= 'process';
		$log_params["title"] 	= "배송지변경";
		$log_params["detail"] 	= $this->baseLogDetail." 배송지변경 하였습니다.";
		$this->CI->orderlibrary->set_log($fmOrder["order_seq"],$log_params);

		if ($this->CI->db->trans_status() === false)
		{
			writeCsLog("change_talkbuy_order trans_status IS FALSE", "change" , "talkbuy");
			writeCsLog($this->CI->db, "change" , "talkbuy");
			$this->CI->db->trans_rollback();
			return false;
		}
		$this->CI->db->trans_commit();

		$save_order_list[$order["orderId"]] 		= $fmOrder["order_seq"];

		return $save_order_list;		
	}

	/**
	 * 주문 출고 카카오 중계서버에 요청
	 */
	public function order_export($export) {
		if(!$this->CI->talkbuy_sendlibrary)	$this->CI->load->library('talkbuy_sendlibrary');
		if(!$this->CI->orderlibrary)		$this->CI->load->library("orderlibrary");
		$export_result	= array();
		$success_cnt	= 0;
		foreach($export['items']['talkbuy_product_order_id'] as $item_k=>$talkbuy_product_order_id){
			$params = array(
				"orderProductId"	=> $talkbuy_product_order_id,
				"shippingMethod"	=> $export["domestic_shipping_method"],
				// 택배 이외는 배송코드가 없을시 0 전송 (api 문서참고)
				"logisticsCode"		=> (strlen($export["delivery_company_code"]) === 0) ? 0 : $export["delivery_company_code"],
				"invoiceNo"			=> $export["delivery_number"],
			);

			$result = $this->CI->talkbuy_sendlibrary->sendMethod("setDelivery",$params);

			$return = array();
			$return['result']		= isset($result['message']) ? "FAIL" : "SUCCESS";
			$return['message']		= $result['message'];

			$log_params 			= $this->baseLogParams;
			$log_params["type"] 	= 'process';
			// 실패 시 message 리턴됨
			if($result["message"]) {
				$log_params["title"] 	= "발송처리실패(API)";
				$log_params["detail"] 	= "[".$talkbuy_product_order_id."] 발송처리(API) 실패 하였습니다.(".$result["message"].")";
			} else {
				$success_cnt++;
				$log_params["title"] 	= "발송처리성공(API)";
				$log_params["detail"] 	= "[".$talkbuy_product_order_id."] 관리자가 발송처리(API) 성공 하였습니다.";
			}
			$this->CI->orderlibrary->set_log($export["order_seq"],$log_params);

			$export_result['success_cnt']								= $success_cnt;
			$export_result['export_items'][$talkbuy_product_order_id]	= $return;
		}
		return $export_result;
	}

	/*********************************************************
	 * 상품 문의 수집, 문의 답변, 후기 수집 시작
	 *********************************************************/

	/**
	 * 상품 후기 수집
	 */
	function set_talkbuy_review($data) {
		// reviewId 로 기존에 등록된 글인지 체크
		$sc["where"] = array(
			'talkbuy_review_id' => $data["reviewId"],
		);
		$board_data = $this->CI->boardlibrary->get_data($sc);

		// 새로 저장
		$params = array();

		// 주문 관련 데이터
		$orderProduct 	= $data['orderProduct'];
		// 첨부파일 관련 데이터
		$addOnFiles 	= $data['addOnFiles'];

		// 평가 점수
		$score						= $data['starPoint'];
		$score_avg					= (int)$score * 20;
		$params['reviewcategory'] 	= $params['score'] = $score;
		$params['score_avg']		=  $score_avg;

		// 기존에 등록된 글이면 업데이트 날짜 비교
		if($board_data) {
			if($data["talkbuy_update_date"] <= $board_data["updatedDateTime"]) {
				$result['success'] = false;
				$result['message'] = 'Not the Latest Board';
				return $result;
			} 

			// 평점 , talkbuy_update_date, 내용 업데이트
			$params['talkbuy_update_date']		= $data['updatedDateTime'];
			$params['contents']					= nl2br(stripcslashes($data['contents']));
			$params['subject']					= getstrcut(strip_tags($params['contents']), '20');

			$params['seq']						= $board_data['seq'];

			// 첨부파일
			$params['addFiles'] 	= $addOnFiles;
			$config['insert_image'] = 'bottom';

			$result = $this->CI->boardlibrary->data_modify($params, $config);
			if($result){
				$return['success'] 	= true;
				$return['seq'] 		= $result;
				return $return;
			} else {
				$debug['title'] = "set_talkbuy_review UPDATE FAIL";
				$debug['query'] = $this->CI->db->last_query();
				writeCsLog($debug, "review" , "talkbuy");
				return 'fail';
			}
		} else {
			$params['editor']		= 0;
			$params['name']			= 'talkbuy';
			$params['contents']		= nl2br(stripcslashes($data['contents']));
			$params['subject']		= getstrcut(strip_tags($params['contents']), '20');

			$params['r_date']		= $data['createdDateTime'];
			
			// 상품 정보
			$params['goods_seq']		= $orderProduct['productId'];

			// 톡체크아웃 정보
			$params['talkbuy_review_id'] 		= $data['reviewId'];
			$params['talkbuy_product_order_id'] = $orderProduct['orderProductId'];
			$params['talkbuy_update_date']		= $data['updatedDateTime'];

			// 첨부파일
			$params['addFiles'] 	= $addOnFiles;
			$config['insert_image'] = 'bottom';


			$result = $this->CI->boardlibrary->data_write($params, $config);
			if($result){
				$return['success'] 	= true;
				$return['seq'] 		= $result;
				return $return;
			} else {
				$debug['title'] = "set_talkbuy_review INSERT FAIL";
				$debug['query'] = $this->CI->db->last_query();
				writeCsLog($debug, "review" , "talkbuy");
				$return['success'] = false;
				$return['message'] = 'Insert Fail';
				return $return;
			}
		}
	}

	/**
	 * 상품 문의 수집
	 */
	function set_talkbuy_qna($data) {
		// reviewId 로 기존에 등록된 글인지 체크
		$sc["where"] = array(
			'talkbuy_inquiry_id' => $data["questionId"],
		);
		$board_data = $this->CI->boardlibrary->get_data($sc);

		// 새로 저장
		$params = array();

		// 주문 관련 데이터
		$orderProduct 	= $data['orderProducts'][0];
		// 첨부파일 관련 데이터
		$addOnFiles 	= $data['addOnFiles'];

		// 기존에 등록된 글이면 업데이트 날짜 비교
		if($board_data) {
			if($data["talkbuy_update_date"] <= $board_data["updatedDateTime"]) {
				$result['success'] = false;
				$result['message'] = 'Not the Latest Board';
				return $result;
			} 

			// 평점 , talkbuy_update_date, 내용 업데이트
			$params['talkbuy_update_date']		= $data['updatedDateTime'];
			$params['contents']					= nl2br(stripcslashes($data['contents']));
			$params['subject']					= getstrcut(strip_tags($params['contents']), '20');

			$params['seq']						= $board_data['seq'];

			// 첨부파일
			$params['addFiles'] 	= $addOnFiles;
			$config['insert_image'] = 'bottom';

			$result = $this->CI->boardlibrary->data_modify($params, $config);
			if($result){
				$return['success'] 	= true;
				$return['seq'] 		= $result;
				return $return;
			} else {
				$debug['title'] = "set_talkbuy_review UPDATE FAIL";
				$debug['query'] = $this->CI->db->last_query();
				writeCsLog($debug, "qna" , "talkbuy");
				return 'fail';
			}
		} else {
			$params['editor']		= 0;
			$params['name']			= $data['questionId'];
			$params['contents']		= nl2br(stripcslashes($data['contents']));
			$params['subject']		= getstrcut(strip_tags($params['contents']), '20');

			$params['category']		=  $data['category'];

			$params['r_date']		= $data['createdDateTime'];
			
			// 상품 정보
			$params['goods_seq']		= $orderProduct['productId'];

			// 톡체크아웃 정보
			$params['talkbuy_inquiry_id'] 		= $data['questionId'];
			$params['talkbuy_product_order_id'] = $orderProduct['orderProductId'];
			$params['talkbuy_update_date']		= $data['updatedDateTime'];

			// 첨부파일
			$params['addFiles'] 	= $addOnFiles;
			$config['insert_image'] = 'bottom';

			$result = $this->CI->boardlibrary->data_write($params, $config);
			if($result){
				$return['success'] 	= true;
				$return['seq'] 		= $result;
				return $return;
			} else {
				$debug['title'] = "set_talkbuy_qna INSERT FAIL";
				$debug['query'] = $this->CI->db->last_query();
				writeCsLog($debug, "qna" , "talkbuy");
				$result['success'] = false;
				$result['message'] = 'Insert Fail';
				return $result;
			}
		}
	}

	/**
	 * 상품문의 답변
	 */
	function set_qna_answer($data) {
		if(!$this->CI->talkbuy_sendlibrary)	$this->CI->load->library('talkbuy_sendlibrary');

		$re_contents = strip_tags($data['re_contents'] );
		$re_contents = str_replace(array("&nbsp;","<br/>"), array(" ","\n"), $re_contents);
		$params		= array(
			"questionId" 		=> $data['talkbuy_inquiry_id'],
			"contents" 			=> $re_contents,
			"answerId"			=> $data['talkbuy_answer_id'],
		);
		$result = $this->CI->talkbuy_sendlibrary->sendMethod("setQnaAnswer",$params);
		if($result['result']) {
			$result['success'] 		= true;
			$result['answerId'] 	= $result['answerId'];
			// talkbuy_answer_id update
		} else {
			$result['success'] 		= false;
			$result['message'] 		= $result['message'];
		}
		return $result;
	}
	/*********************************************************
	 * 상품 문의 수집, 문의 답변, 후기 수집 종료
	 *********************************************************/

	/*********************************************************
	 * 톡체크아웃 중계서버와 통신하여 톡체크아웃 입점 상태 확인 신청 프로세스 시작
	 *********************************************************/
	/**
	 * 중계서버에 톡체크아웃 신청 > 중계서버에서 중복 검증 후 카카오 매핑 요청
	 */
	function set_marketing_info($info) {
		if(!$this->CI->talkbuy_sendlibrary)	$this->CI->load->library('talkbuy_sendlibrary');
		
		// 클라우드 서버는 db host 가 사설 아이피로 나와서 가져온다. db host 아이피를 구한다
		$databaseHost = $this->getDatabaseHost();

		$params = array(
			"shopSno" => $this->CI->config_system['shopSno'],
			"domain" => $this->CI->config_system["subDomain"],
			"webserverIp" => $_SERVER["SERVER_ADDR"],
			"dbHost" => (strlen($databaseHost) > 0) ? $databaseHost : $this->CI->db->hostname,
			"dbName" => $this->CI->db->database,
			"shopName" => $this->CI->config_system['admin_env_name'],
			"shopKey" => $info["shopKey"],
		);

		$result = $this->CI->talkbuy_sendlibrary->sendMethod("setInfo",$params);

		return $result;
	}

   	private function getDatabaseHost()
	{
		$systemConfigList= config_load("system");
		$systemServiceConfigList = $systemConfigList['service'];
		
		return (isset($systemServiceConfigList['ips']['db']) === true) ? $systemServiceConfigList['ips']['db'] : '';
	}

	/**
	 * 현재 톡체크아웃 상태 확인
	 */
	function get_marketing_info($shopKey) {
		if(!$this->CI->talkbuy_sendlibrary)	$this->CI->load->library('talkbuy_sendlibrary');
		$this->CI->talkbuy_sendlibrary->talkbuyShopKey = $shopKey;
		$result = $this->CI->talkbuy_sendlibrary->sendMethod("getServiceStatus");
		return $result;
	}

	/**
	 * 톡체크아웃 상태 변경함
	 * 정상 > 이용중지, 이용중지 > 정상 만 가능함
	 * 그 외는 카카오에 직접 문의해야함
	 */
	function set_marketing_status($info) {
		if(!$this->CI->talkbuy_sendlibrary)	$this->CI->load->library('talkbuy_sendlibrary');
		$params = array(
			"status" => strtoupper($info["use"]),
		);
		$result = $this->CI->talkbuy_sendlibrary->sendMethod("setStatus",$params);
		return $result;
	}
	/*********************************************************
	 * 톡체크아웃 중계서버와 통신하여 톡체크아웃 입점 상태 확인 신청 프로세스 종료
	*********************************************************/	

	
	/********************************************************
	 * 마케팅/전자결제 페이지에서 필요 정보 저장 및 노출 종료
	 ********************************************************/
	function load_talkbuy_config() {
		if(!$this->CI->categorymodel) $this->CI->load->model('categorymodel');
		if(!$this->CI->goodsmodel) $this->CI->load->model('goodsmodel');

		$talkbuy = config_load('talkbuy');

		if(!$talkbuy['use']){
			$talkbuy['use'] = 'n';
		}

		foreach((array)$talkbuy['except_category_code'] as $k=>$row){
			$talkbuy['except_category_code'][$k]['category_name']  = $this->CI->categorymodel->get_category_name($row['category_code']);
		}
		
		$goods_list = array('except_goods','culture_goods');
		foreach($goods_list as $key) {
			$talkbuy[$key] = $this->CI->goodsmodel->get_select_goods_list($talkbuy[$key]);
		}

		$talkbuy['culture_count'] = count($talkbuy['culture_goods']);

		$sel_talkbuy_arr = array("pc_goods","mobile_goods");
		foreach($sel_talkbuy_arr as $talkbuy_style){
			if($talkbuy_style == "mobile_goods") $style_text = "M";
			if($talkbuy['talkbuy_btn_'.$talkbuy_style]){
				$code		= explode("-",$talkbuy['talkbuy_btn_'.$talkbuy_style]);				
				$style_text .= $code[3]."-".($code[1]+1) . " 타입";
				$size		= explode("x",$code[0]);
				$h			= $size[1];			
			}else{
				$style_text = "";
				$h			= "88";
			}
			
			$sel_talkbuy_btn_text[$talkbuy_style."_h"]	= $h;
			$sel_talkbuy_btn_text[$talkbuy_style]  = $style_text;			
		}
	
		if($talkbuy["shopKey"]) {
			if(!$this->CI->talkbuy_sendlibrary) $this->CI->load->library('talkbuy_sendlibrary');
			$talkbuy_status = $this->CI->talkbuy_sendlibrary->sendMethod("getStatus");
			$config_talkbuy_status["talkbuy_service_status"] = $talkbuy_status["serviceStatus"];
			config_save('talkbuy',$config_talkbuy_status);
		}
		
		// 상점 연동상태 안내문구
		$talkbuy_status['serviceStatusKorDetail'] = $this->getStatusText([
			'shopKey' => (isset($talkbuy["shopKey"]) === true && strlen($talkbuy["shopKey"]) > 0) ? $talkbuy["shopKey"] : '',
			'use' => (isset($talkbuy['use']) === true && strlen($talkbuy['use']) > 0) ? $talkbuy['use'] : 'n',
			// 기본 미연동
			'talkbuyStatus' => (isset($talkbuy_status['serviceStatus']) === true) ? $talkbuy_status['serviceStatus'] : 'INACTIVE',
		]);

		$result = array(
			"talkbuy"				=>$talkbuy,
			"sel_talkbuy_btn_text"	=>$sel_talkbuy_btn_text,
			"talkbuy_status"		=>$talkbuy_status
		);

		return $result;
	}

	private function getStatusText($statusParams)
	{
		/**
		 * 상점키가 있으면 연동신청 상태
		 * 상점키가 없으면 미연동 상태
		 */
		$isShopKey = (strlen($statusParams['shopKey']) > 0) ? true : false;

		// 퍼스트몰 톡체크아웃(톡구매) 사용여부   y, n
		$firstmallStatus = $statusParams['use'];
		
		/**
		 * 카카오 판매점 상태 조회 값
		 * - ACTIVE : 정상
		 * - INACTIVE : 미연동
		 * - BLOCKED : 이용제한
		 * - PAUSE : 일시정지
		 * - UNAVAILABLE : 서비스 불가
		 */
		$kakaoStatus = $statusParams['talkbuyStatus'];

		// 퍼스트몰 톡체크아웃 : 사용안함
		if ($firstmallStatus === 'y' && $kakaoStatus === 'ACTIVE') {
			return '정상-연동중';
		}

		// 관리자에게 알려줄 상태값
		$statusText = '';

		// 퍼스트몰 톡체크아웃 : 사용안함
		if ($firstmallStatus === 'n') {
			switch ($kakaoStatus) {
				case 'INACTIVE' :
					$statusText = '미연동-연동신청전';

					if ($isShopKey === true) {
						$statusText = '미연동-연동신청중';
					}
					break;
				case 'BLOCKED' :
					$statusText = '이용제한-연동중지';
					break;
				case 'PAUSE' :
					$statusText = '일시정지-연동중';
					break;
				case 'UNAVAILABLE' :
					$statusText = '서비스불가-연동중지';
					break;
				default :
					$statusText = '미연동-연동신청전';
					break;
			}
		}
		
		return $statusText;
	}

	function save_talkbuy_config($params) {
		$old_talkbuy 			= config_load("talkbuy");

		$talkbuy["use"] 		= $params["talkbuy_use"];
		$talkbuy["shopKey"] 	= trim($params["talkbuy_shopKey"]);
		$talkbuy['culture'] 	= trim($params['talkbuy_culture']);
		$talkbuy['surchargeByArea2Price'] 	= (int)$params['talkbuy_surchargeByArea2Price'];
		$talkbuy['surchargeByArea3Price'] 	= (int)$params['talkbuy_surchargeByArea3Price'];
		$talkbuy['except_category_code'] = array();
		foreach($params['talkbuy_issueCategoryCode'] as $value){
			$talkbuy['except_category_code'][] = array('category_code'=>$value);
		}
		$talkbuy['except_goods'] = array();
		foreach($params['talkbuy_expect_goods'] as $value){
			$talkbuy['except_goods'][] = array('goods_seq'=>$value);
		}
		$talkbuy['culture_goods'] = array();
		if($talkbuy['culture']=='choice') {
			foreach($params['talkbuy_culture_goods'] as $value){
				$talkbuy['culture_goods'][] = array('goods_seq'=>$value);
			}
		}

		if( $talkbuy['use'] == 'y'){
			if( !$talkbuy['shopKey'] ){
				openDialogAlert("톡체크아웃 상점 인증키 필수 입니다.",400,140,'parent',$callback);
				exit;
			}
		}
		
		// 사용여부, 상점Key 달라지면 정보 전송
		if(
			(strlen($old_talkbuy["use"]) > 0 && $talkbuy["use"] != $old_talkbuy["use"]) || 
			$talkbuy["shopKey"] != $old_talkbuy["shopKey"]
		) {
			$get_status = $this->get_marketing_info($talkbuy['shopKey']);
			switch ($this->processTypeAfterSaveAction($get_status)) {
				// 상태 업데이트
				case 'update' :
					$set_result = $this->set_marketing_status($talkbuy);
					if(!$set_result["result"]) {
						$message = $set_result["message"] ? $set_result["message"] : "톡체크아웃-중계서버 상태 연동에 실패하였습니다.";
						openDialogAlert($message,400,140,'parent',$callback);
						exit;
					}		
					break;
				// 톡체크아웃 서비스 매핑
				case 'join' :
					$set_result = $this->set_marketing_info($talkbuy);
					if(!$set_result["result"]) {
						$message = $set_result["message"] ? $set_result["message"] : "톡체크아웃 상점 연동에 실패하였습니다.";
						openDialogAlert($message,400,140,'parent',$callback);
						exit;
					}
					break;
				case 'fail' :
					$message = $get_status["message"] ? $get_status["message"] : "톡체크아웃-중계서버 상태 연동에 실패하였습니다.";
					openDialogAlert($message,400,140,'parent',$callback);
					exit;
					break;
				default :
					$message = "톡체크아웃-중계서버 상태 연동에 실패하였습니다.";
					openDialogAlert($message,400,140,'parent',$callback);
					exit;
					break;
			}

			if($set_result["data"]) {
				$mapping_result 					= (array)$set_result["data"];
				// 버튼인증키 존재하는경우만 등록
				if (isset($mapping_result["authKey"]) === true) {
					$talkbuy["button_key"] 				= $mapping_result["authKey"];
				}
				$talkbuy["pixelTrackId"] 			= $mapping_result["pixelTrackId"];
				$talkbuy["talkbuy_service_status"] 	= $mapping_result["serviceStatus"];
			}
			
		}
		// 톡체크아웃 중계서버 정보 전송

		# 톡체크아웃 전용 문의게시판 생성
		if($talkbuy['use'] == "y"){
			$this->CI->load->model("Boardmanager");
			$params 	= array(
				'board_id'=>'talkbuy_qna',
				'board_name'=>'톡체크아웃 문의',
				'category'=>'상품,배송,반품,교환,취소,환불,기타',
			);
			$qna_res	= $this->CI->Boardmanager->set_partner_board_create($params);
		}

		// 정보 전송 성공 시 설정 업데이트
		config_save("talkbuy", $talkbuy);
	}
	/********************************************************
	 * 마케팅/전자결제 페이지에서 필요 정보 저장 및 노출 종료
	 ********************************************************/

	private function processTypeAfterSaveAction(array $response): string {
		if ($response['result'] === true) {
			return 'update';
		}

		$processType = 'fail';
		// 가입정보 없는경우
		if ($response['result'] === false && $response['code'] === 'RelayServer-USER-0001') {
			$processType = 'join';
		}

		return $processType;
	}


	 /********************************************************
	* 상품 금액 회원/이벤트 할인 적용 시작
	********************************************************/
	function set_talkbuy_goods_sale($goods_seq) {
		if(!$this->CI->goodsmodel) $this->CI->load->model('goodsmodel');

		$goods		= $this->CI->goodsmodel->get_goods($goods_seq);
		$options	= $this->CI->goodsmodel->get_goods_option($goods_seq,array('option_view'=>'Y'));
		$suboptions	= $this->CI->goodsmodel->get_goods_suboption($goods_seq,array('option_view'=>'Y'));

		/**
		 * goodsmodel->get_goods_view 에서 사용하는 함수 그대로 사용함
		 */
		$this->CI->sale->reset_init();
		if($options)foreach($options as $k => $opt){
			if($opt['price']){
				//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
				unset($param, $sales);
				$param['option_type']				= 'option';
				$param['consumer_price']			= $opt['consumer_price'];
				$param['price']						= $opt['price'];
				$param['ea']						= 1;
				$param['goods_ea']					= 1;
				$param['category_code']				= $goods['r_category'];
				$param['brand_code']				= $goods['brand_code'];
				$param['goods_seq']					= $goods['goods_seq'];
				$param['goods']						= $goods;
				$this->CI->sale->set_init($param);
				$sales								= $this->CI->sale->calculate_sale_price('option');

				// 기준옵션은 상품데이터에 별도로 제공함
				if($opt['default_option'] == 'y') {
					$goods['default_price'] = $sales['result_price'];
				}

				$goods['option'][] = array(
					'id' 	=> 	$opt['option_seq'],
					'price' => 	$sales['result_price'],
				);

				$this->CI->sale->reset_init();
				//<---- sale library 적용
			}
		}

		if($suboptions) foreach($suboptions as $key => $tmp){
			foreach($tmp as $k => $opt){
				unset($param, $sales);
				$param['option_type']			= 'suboption';
				$param['sub_sale']				= $opt['sub_sale'];
				$param['consumer_price']		= $opt['consumer_price'];
				$param['price']					= $opt['price'];
				$param['total_price']			= $opt['price'];
				$param['ea']					= 1;
				$param['category_code']			= $goods['r_category'];
				$param['brand_code']			= $goods['brand_code'];
				$param['goods_seq']				= $goods['goods_seq'];
				$param['goods']					= $goods;
				$this->CI->sale->set_init($param);
				$sales							= $this->CI->sale->calculate_sale_price('option');
				
				$goods['subOptions'][] = array(
					'id' 	=> 	$opt['suboption_seq'],
					'price' => 	$sales['result_price'],
				);
				$this->CI->sale->reset_init();
				unset($sales);
			}
		}

		$result = array(
			'goods_seq' 	=> $goods_seq,
			'goods_name'	=> $goods['goods_name'],
			'default_price'	=> $goods['default_price'],
			'options'		=> $goods['option'],
			'subOptions'	=> $goods['subOptions'],
		);
		
		return $result;
	}
	/********************************************************
	 * 상품 금액 회원/이벤트 할인 적용 종료
	 ********************************************************/
}