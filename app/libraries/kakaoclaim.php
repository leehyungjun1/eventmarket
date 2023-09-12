<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
use App\Libraries\TalkbuyInterface;

// TODO(kjw) : DB Model성을 가진 코드들은 전부 model 쪽으로 분리 작업
class Kakaoclaim implements TalkbuyInterface
{
	private $maxReflectionCount = 3;
	public $baseLogParams = [
		"actor" => "Kakao Pay",
		"add_info" => self::KAKAO_PG,
	];

	function __construct() {
		$this->CI =& get_instance();
		if(!$this->CI->partnerlib) $this->CI->load->library("partnerlib");
		if(!$this->CI->orderlibrary) $this->CI->load->library("orderlibrary");
	}

	// 환불 클레임건 찾기 (교환건에 대한 환불은 원주문건에 해당됨)
	private function _claimRefundFinder(array $claim) {
		$this->CI->load->model('ordermodel');
		$claimData = $claim;
		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];
		$orderId = $claimInfo["claimDetail"]["orderId"];

		// 교환 주문건이 있을 경우
		if ($claim["fmOrder"]["top_orign_order_seq"]) {
			$originOrder = $this->CI->ordermodel->get_order($claim["fmOrder"]["top_orign_order_seq"]);
			$originOrderSeq = $originOrder["order_seq"];
			$fmOrderShipping = $this->CI->partnerlib->getOrderShipping(array("order_seq"=>$originOrderSeq), $productInfo["shippingFeeGroupId"], "talkbuy");
			$fmOrderShipping = array_pop($fmOrderShipping);
			
			$itemWhere = [
				"order_seq" => $originOrderSeq,
				"shipping_seq" => $fmOrderShipping["shipping_seq"],
				"goods_seq" => $productInfo["productId"],
				"orderId" => $orderId,
			];
			
			$orderItem = $this->CI->partnerlib->getOrderItem($itemWhere, "talkbuy");

			// 본상품의 productId를 가져와야한다.
			$topItemOptionSeq = null;
			if ($claim["claimProductType" === "main"]) {
				$topItemOptionSeq = $claim["option"]["top_item_option_seq"];
			} else {
				// 현재주문건에 대한 top item option 조회
				$query = "select * from fm_order_item_option where item_option_seq= ?";
				$query = $this->CI->db->query($query, array($claim["option"]["item_option_seq"]));
				$presentOption = $query->row_array();

				$topItemOptionSeq = $presentOption["top_item_option_seq"];
			}

			// 원주문 item 조회
			$query = "select * from fm_order_item_option where order_seq=? AND item_option_seq= ? AND item_seq = ? AND talkbuy_order_id=? AND talkbuy_packagenumber=?";
			$query = $this->CI->db->query($query, array($originOrderSeq, $topItemOptionSeq, $orderItem["item_seq"], $orderId, $productInfo["shippingFeeGroupId"]));
			$originOption = $query->row_array();

			$itemOptionWhere = [
				"order_seq" => $originOrderSeq,
				"shipping_seq" => $fmOrderShipping["shipping_seq"],
				"item_seq" => $orderItem["item_seq"],
				"id" => $originOption["talkbuy_product_order_id"],
				"orderId" => $orderId,
				"packageNumber" => $productInfo["shippingFeeGroupId"],
			];

			$itemOptionData = $this->CI->partnerlib->getOrderItemOption($itemOptionWhere, "talkbuy");
			
			$claimData["fmOrder"] = $originOrder;
			$claimData["fmOrderItem"] = $orderItem;
			$claimData["option"] = $itemOptionData;

			if ($claim["claimProductType"] === "sub") {
				$itemSubOptionWhere = [
					"order_seq" => $originOrderSeq,
					"shipping_seq" => $fmOrderShipping["shipping_seq"],
					"item_seq" => $orderItem["item_seq"],
					"id" => $productInfo["id"],
					"orderId" => $orderId,
					"option_seq" => $itemOptionData["item_option_seq"],
					"packageNumber" => $productInfo["shippingFeeGroupId"],
				];

				$itemSubOptionData = $this->CI->partnerlib->getOrderItemSubOption($itemSubOptionWhere, "talkbuy");

				//$claimData["option"]["price"] = $itemSubOptionData["price"];
				$claimData["option"] = $itemSubOptionData;
			}
		}
		
		return $claimData;
	}

	// 환불요청
    public function setOrderRefund(array $claim) {
		$this->CI->load->model("refundmodel");
		$this->CI->load->model("ordermodel");
		$this->CI->load->model("goodsmodel");
		$this->CI->load->model("exportmodel");
		
		// 교환건에 대한 환불은 원주문건에 처리
		$claim = $this->_claimRefundFinder($claim);

		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];
		$claimDetail = $claimInfo["claimDetail"];

		if ($this->_claimContextValidator($claim) === false) {
			// TODO Throw Exception
			$reponseError = [
				"success" => false,
				"data" => ["msg" => "ClaimContextValidator"],
				"function" => __FUNCTION__,
				"message" => "ClaimContextValidator Failed",
			];

			return $this->_responseContext($reponseError);
		} 

		// 우선 모든 요청은 단건으로 처리한다.
		$cancelType = "partial";

		$optionSeq = null;
		$optionType = null;
		if ($claim["claimProductType"] === "main") {
			$optionType = "option";
			$optionSeq = $claim["option"]["item_option_seq"];
		} else {
			$optionType = "suboption";
			$optionSeq = $claim["option"]["item_suboption_seq"];
		}

		$setStep = 0;
		if ($claimInfo["claimType"] === self::CT_RETURN || $claimInfo["claimType"] === self::CT_EXCHANGE) {
			// set_step_ea 는 반품쪽에서 처리하도록 함
			$refundType = "return";
			$setStep = 75;
		} else {
			$refundType = "cancel_payment";
			$setStep = 85;
			// 기존 step 의 개수를 초기화한다.
			if($claim["option"]["step"] > 25) {
				$this->CI->db->set('step'.$claim["option"]["step"], 0);
				$this->CI->db->where('item_'.$optionType.'_seq', $optionSeq);
				$this->CI->db->update('fm_order_item_'.$optionType);
			}
			$this->CI->ordermodel->set_step_ea($setStep, $productInfo["quantity"], $optionSeq, $optionType);
		}

		// 같은 배송 그룹에 의한 환불배송비 계산
		// order_refund 의 개수를 조회하고, 조회된 개수 + 환불할 상품 해서 주문수량이 맞으면 배송비 환불
		$refundDeliveryCost = 0;

		$totalOrderEa = $claim["fmOrder"]["total_ea"];
		$alreadyRefundEa = $this->CI->refundmodel->get_refund_ea_by_item($claim["fmOrderItem"]["item_seq"], $productInfo["shippingFeeGroupId"]);
		$predictRefundEa = $alreadyRefundEa + $productInfo["quantity"];
		writeCsLog(["total_ea" => $totalOrderEa, "alreadyRefundEa" => $alreadyRefundEa, "predictRefundEa" => $predictRefundEa], "refundEa", "talkbuy");

		if ($totalOrderEa == $predictRefundEa) {
			$refundDeliveryCost = abs($claimDetail["claimShippingFee"]);
		}


		// 카카오에서 결제취소하는 건 option 데이터는 개수 부분 취소가 안되므로 개수비교 없이 그냥 바로 취소
		if ($claim["option"]["step"] < 75) {
			$this->CI->db->set('refund_ea','refund_ea+'.$productInfo["quantity"], false);
			$this->CI->db->set('step', $setStep);
			$this->CI->db->where('item_'.$optionType.'_seq', $optionSeq);
			$this->CI->db->update('fm_order_item_'.$optionType);
			// 주문 option 상태 변경
			$this->CI->ordermodel->set_option_step($optionSeq, $optionType);
	
			// 출고예약량 업데이트
			$this->CI->goodsmodel->modify_reservation_real($productInfo["productId"]);
	
			$this->CI->ordermodel->set_order_step($claim["fmOrder"]["order_seq"]);
		}
		
		// 취소건에 대한 출고건 삭제 처리
		if ($claimInfo["claimType"] === self::CT_CANCEL) {
			//취소건에 대한 환불에서 exportData 가 존재하면 삭제처리
			$this->CI->exportmodel->delete_export_ready_by_partner($claim["fmOrder"]["order_seq"], $productInfo["id"], self::KAKAO_PG);
		}

		// 입점사확인

		$data = array(
			'order_seq'			=> $claim["fmOrder"]["order_seq"],
			'bank_name'			=> "KakaoTalkPay",
			'bank_depositor'	=> "",
			'bank_account'		=> "",
			'refund_reason'		=> ($claimInfo["claimDetail"]["requestReason"])?:"system cancel",
			'refund_type'		=> $refundType,
			'cancel_type'		=> $cancelType,
			'regist_date'		=> date('Y-m-d H:i:s'),
			'talkbuy_order_id' => $claim["fmOrder"]["talkbuy_order_id"],
			'talkbuy_refund_request_date' => date('Y-m-d H:i:s'),
			'refund_price' => ($claim["option"]["price"] - $memberSale) * $productInfo["quantity"],
			'refund_method' => $claim["fmOrder"]["payment"],
			'manager_seq'		=> 0,
		);

		$memberSale = 0;
		if (!$claim["option"]["member_sale"]) {
			$memberSale = (int) $claim["option"]["member_sale"];
		}

		// 반품배송비 관련 처리
		if ($claimDetail && $claimInfo["personInChargeType"] === self::PERSON_ICT_PURCHASER && $claimDetail["cancelShippingFeeAmount"] > 0) {
			$data["refund_delivery_deductible_price"] = $claimDetail["cancelShippingFeeAmount"];
		}

		// 기존 insert_refund 함수가 item 을 복수로 받아지고 있어서, $item 을 형태만 맞춤
		$items[0] = [
			"item_seq" => $claim["fmOrderItem"]["item_seq"],
			"option_seq" => ($claim["claimProductType"] === "main") ? $optionSeq : 0,
			"suboption_seq" => ($claim["claimProductType"] === "sub") ? $optionSeq : 0,
			"ea" => $productInfo["quantity"],
			"refund_goods_price" => ($claim["option"]["price"] - $memberSale) * $productInfo["quantity"],
			"refund_goods_pg_price" => ($claim["option"]["price"] - $memberSale) * $productInfo["quantity"],
			"refund_delivery_price" => $refundDeliveryCost,
			"refund_delivery_pg_price" => $refundDeliveryCost,
			"talkbuy_product_order_id" => $productInfo["id"],
			"talkbuy_shipping_fee_group_id" => $productInfo["shippingFeeGroupId"],
		];

		$refundCode = $this->CI->refundmodel->insert_refund($data,$items);
		$data["refund_code"] = $refundCode;

		
		$logParams = [
			"type" => 'process',
			"title" => "환불요청 (".$refundCode.")",
			"detail" => "[" . $productInfo["id"] . "] Kakao로 부터 환불요청 되었습니다.",
			"refund_code" => $refundCode,
		];
		$this->CI->orderlibrary->set_log($claim["fmOrder"]['order_seq'], array_merge($this->baseLogParams, $logParams));

		$responseData = [
			"success" => true,
			"data" => $data,
		];
		
		return $this->_responseContext($responseData);
    } 
	
	// 환불완료 (장애대응-faliover 처리)
	public function setOrderRefundComplete(array $claim) {
		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];
		
		// 교환건에 대한 환불은 원주문건에 처리
		$claim = $this->_claimRefundFinder($claim);
		// 취소요청건이 존재하지 않을 경우 완료처리 불가 완료 후 재귀처리
		// FailOver : 환불요청 재요청 처리
		$existRefundData = $this->alreadyExistRefund($claimInfo, $claim["fmOrderItem"]["item_seq"]);
		if (!$existRefundData["refund_item"]["refund_item_seq"] || !$existRefundData["refund"]["refund_seq"]) {
			$setRefundFlag = false;

			// maxReflectionCount 만큼 환불요청 재귀처리
			for($i = 1; $i <= $this->maxReflectionCount; $i++) {
				$setRefund = $this->setOrderRefund($claim);
				if($setRefund["success"] === true) {
					$setRefundFlag = true;
					break;
				}
			}
			if ($setRefundFlag === false) {
				// TODO Throw Exception
				$reponseError = [
					"success" => false,
					"data" => ["msg" => "환불 요청 reflection 실패"],
					"function" => __FUNCTION__,
					"message" => "setOrderRefund maxReflection Err",
				];

				return $this->_responseContext($reponseError);
			}
		}

		return $this->_execOrderRefundComplete($claim);
	}

	// 환불완료 (실제 환불완료 처리)
	private function _execOrderRefundComplete(array $claim) {
		$this->CI->load->model('accountallmodel');
		$this->CI->load->model("refundmodel");
		$this->CI->load->model("exportmodel");
		$this->CI->load->model("returnmodel");
		
		// 교환건에 대한 환불은 원주문건에 처리
		$claim = $this->_claimRefundFinder($claim);

		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];
		$claimDetail = $claimInfo["claimDetail"];

		$existRefundData = $this->alreadyExistRefund($claimInfo, $claim["fmOrderItem"]["item_seq"]);

		// 우선 모든 요청은 단건으로 처리한다.
		$cancelType = "partial";

		$params = [
			"talkbuy_order_id" => $claimDetail["orderId"],
			"talkbuy_request_group_id" => $claimDetail["requestGroupId"],
			"status" => "complete",
		];
		$query = $this->CI->returnmodel->get_data_return($params);
		$alreadyCompleteReturnData = $query->result_array();
		
		// 하나의 request에 부과된 반품배송비건이 존재하면 중복 부과하지 않음
		$alreadyChargeReturnDeliveryPrice = 0;
		foreach($alreadyCompleteReturnData as $value) {
			// 현재건인 아닌 다른 환불건에서 처리된 경우
			if ($value["return_shipping_price"] > 0 && $value["refund_code"] !== $existRefundData["refund"]["refund_code"]) {
				$alreadyChargeReturnDeliveryPrice = $alreadyChargeReturnDeliveryPrice + $value["return_shipping_price"];
			}
		}

		// 이미 부과된 refund_delivery_deductible_price 가 있으면 부과하지 않음
		$params = [
			"talkbuy_order_id" => $claimDetail["orderId"],
			"status" => "complete",
		];

		$query = $this->CI->refundmodel->get_data_refund($params);
		$alreadyCompleteRefundData = $query->result_array();

		$alreadyChargedeductiblePrice = 0;
		if ($claimInfo["personInChargeType"] === self::PERSON_ICT_PURCHASER && $claimDetail["cancelShippingFeeAmount"] > 0) {
			foreach($alreadyCompleteRefundData as $value) {
				// 현재건인 아닌 다른 환불건에서 처리된 경우
				if ($value["refund_delivery_deductible_price"] > 0 && $value["refund_code"] !== $existRefundData["refund"]["refund_code"]) {
					$alreadyChargedeductiblePrice = $alreadyChargedeductiblePrice + $value["refund_delivery_deductible_price"];
				}
			}
		}
		
		if ($claimInfo["personInChargeType"] === self::PERSON_ICT_SELLER && $claimDetail["cancelShippingFeeAmount"] < 0) {
			$alreadyChargedeductiblePrice = abs($claimDetail["cancelShippingFeeAmount"]);
		}

		// 환급할 여지가 있는 데이터 조회
		$refundDeliveryPrice = ($existRefundData["refund_item"]["refund_delivery_price"])?:0;

		$refundShippingPrice = $claimDetail["claimShippingFee"] - $alreadyChargeReturnDeliveryPrice;

		$refundShippingPrice = $refundShippingPrice - $alreadyChargedeductiblePrice;

		if ($refundDeliveryPrice == 0 && $claimDetail["cancelShippingFeeAmount"] < 0) {
			$refundShippingPrice = $refundShippingPrice - $claimDetail["cancelShippingFeeAmount"];
		}

		writeCsLog(["refundDeliveryPrice" => $refundDeliveryPrice, "alreadyChargedeductiblePrice" => $alreadyChargedeductiblePrice, "refundShippingPrice" => $refundShippingPrice, "alreadyChargeReturnDeliveryPrice" => $alreadyChargeReturnDeliveryPrice], "refundPrice", "talkbuy");

		$completeRefundParams = array(
			'refund_method' => $claim["fmOrder"]["payment"],
			'refund_price' => (($claim["option"]["price"] - $memberSale) * $productInfo["quantity"]) - $refundShippingPrice, //TODO(kjw) : 좀 더 안전한 코드로 구현
			'status' => 'complete',
			'cancel_type' => $cancelType,
			'refund_date' => date("Y-m-d H:i:s"),
			'talkbuy_refund_request_date' => date("Y-m-d H:i:s", strtotime($claimInfo["requestedDateTime"])),
		);

		// 공제 배송비 처리
		if ($claimInfo["personInChargeType"] === self::PERSON_ICT_PURCHASER && $claimDetail["cancelShippingFeeAmount"] > 0) {
			$completeRefundParams["refund_delivery_deductible_price"] = $claimDetail["cancelShippingFeeAmount"];
		}

		//status 환불완료처리
		$this->CI->db->where('refund_seq', $existRefundData["refund"]["refund_seq"]);
		$this->CI->db->update("fm_order_refund",$completeRefundParams);

		$refundDeliveryPriceParams = [
			"refund_delivery_pg_price" => ($refundShippingPrice < 0)?abs($refundShippingPrice):0,
			"refund_delivery_price" => ($refundShippingPrice < 0)?abs($refundShippingPrice):0,
		];
		$this->CI->db->where('refund_item_seq', $existRefundData["refund_item"]["refund_item_seq"]);
		$this->CI->db->update("fm_order_refund_item", $refundDeliveryPriceParams);

		//정산대상 수량업데이트
		//$this->CI->accountallmodel->update_calculate_sales_ac_ea($claim["fmOrder"]["order_seq"], $existRefundData["refund"]["refund_code"], 'refund');
		//정산확정 처리
		$this->CI->accountallmodel->insert_calculate_sales_order_refund($claim["fmOrder"]["order_seq"], $existRefundData["refund"]["refund_code"], $existRefundData["refund"]["cancel_type"], $claim["fmOrder"]);

		$this->CI->accountallmodel->insert_calculate_sales_order_deductible($claim["fmOrder"]["order_seq"], $existRefundData["refund"]["refund_code"], $existRefundData["refund"]["cancel_type"], $claim["fmOrder"]);
		
		$logParams = [
			"type" => 'process',
			"title" => "환불완료 (".$existRefundData["refund"]["refund_code"].")",
			"detail" => "[" .$productInfo["id"] . "] Kakao로 부터 환불완료 되었습니다.",
			"refund_code" => $existRefundData["refund"]["refund_code"],
		];
		$this->CI->orderlibrary->set_log($claim["fmOrder"]['order_seq'], array_merge($this->baseLogParams, $logParams));


		$responseData = [
			"success" => true,
			"data" => [
				"refund_code" => $existRefundData["refund"]["refund_code"],
			],
		];
		
		return $this->_responseContext($responseData);
	}

	// 환불철회
	public function setRejectionRefund(array $claim) {
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('ordermodel');
		$this->CI->load->model('refundmodel');
		$this->CI->load->model('returnmodel');
		$this->CI->load->model('exportmodel');
		$this->CI->load->helper('order');
		
		// 교환건에 대한 환불은 원주문건에 처리
		$claim = $this->_claimRefundFinder($claim);

		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];

		$refund = $this->alreadyExistRefund($claimInfo, $claim["fmOrderItem"]["item_seq"]);

		$dataRefund = $refund["refund"];
		$refundItem = $refund["refund_item"];
		$dataOrder = $claim["fmOrder"];
		$refundCode = $dataRefund["refund_code"];

		// 환불데이터가 없으면 취소철회 프로세스를 종료한다.
		if (!$dataRefund["refund_seq"] || !$refundItem["refund_item_seq"]) {
			$reponseError = [
				"success" => false,
				"data" => ["msg" => "환불데이터가 존재하지 않습니다."],
				"function" => __FUNCTION__,
				"message" => "Not Exist Refund Data Failed",
			];

			return $this->_responseContext($reponseError);
		}

		// 출고량 업데이트를 위한 변수선언
		$reservationGoodsSeq = array();
		
		$optionType = null;
		// sub, main 의 option 값 top 에 따른 치환처리
		if ($claim["claimProductType"] === "main") {
			$optionType = "option";
			if ($claim["option"]["top_item_option_seq"]) {
				$refundItem['option_seq'] = $claim["option"]["top_item_option_seq"];
			}
			if ($claim["option"]["top_item_seq"]) {
				$refundItem['item_seq'] = $claim["option"]["top_item_seq"];
			}
		} else {
			$optionType = "suboption";
			$refundItem["option_seq"] = $claim["option"]["item_suboption_seq"];
			if ($claim["option"]["top_item_suboption_seq"]) {
				$refundItem['option_seq'] = $claim["option"]["top_item_suboption_seq"];
			}
		}
		
		$optionSeq = $refundItem['option_seq'];
		if($claim["option"]["step"] == 85) {
			// fmExportItem 이 존재하지 않으면, 사용자(송장번호 입력 X) -> 취소 -> 철회 프로세스
			// fmExportItem 이 존재, 관리자(송장번호 입력 O) -> 취소 -> 철회 프로세스
			if ($claimInfo["claimType"] === self::CT_CANCEL) {
				// 기존 step 의 개수를 초기화한다.
				$this->CI->db->set('step'.$claim["option"]["step"], 0);
				$this->CI->db->set('step35', 0);
				$this->CI->db->where('item_'.$optionType.'_seq', $optionSeq);
				$this->CI->db->update('fm_order_item_'.$optionType);

				if ($claim["fmExportItem"]) {
					$this->CI->db->set('step', "65");
				} else {
					// 출고 데이터가 존재하지 않으면 무조건 step은 35로 해당 처리(상품준비는 배제)
					$this->CI->db->set('step35', $productInfo["quantity"]);
					$this->CI->db->where('item_'.$optionType.'_seq', $optionSeq);
					$this->CI->db->update('fm_order_item_'.$optionType);
					
					$this->CI->db->set('step', "35");
				}
			}
		}

		// refund_ea 는 모두 철회이므로 0개
		$this->CI->db->set('refund_ea', 0, false);
		$this->CI->db->where('item_'.$optionType.'_seq', $optionSeq);
		$this->CI->db->update('fm_order_item_'.$optionType);

		if (($dataRefund['refund_type'] != 'shipping_price') && in_array($dataOrder['step'], array('25','35','45','55','65','75','85'))) {
			$this->CI->ordermodel->set_order_step($dataOrder['order_seq']);
		}
		
		if ($refund["refund_item"]["refund_item_seq"]) {
			$sql = "delete from fm_order_refund_item where refund_code=?";
			$this->CI->db->query($sql, $refundCode);
		}
		
		if ($refund["refund"]["refund_seq"]) {
			$sql = "delete from fm_order_refund where refund_code=?";
			$this->CI->db->query($sql, $refundCode);
		}

		// 출고예약량 업데이트
		if($dataRefund['refund_type'] !== self::CT_RETURN){
			$this->CI->goodsmodel->modify_reservation_real($claim['fmOrderItem']["goods_seq"]);
		}
		
		$logParams = [
			"type" => 'process',
			"title" => "환불철회 (".$refundCode.")",
			"detail" => "[" . $productInfo["id"] . "] Kakao로 부터 환불철회 되었습니다.",
		];

		$this->CI->orderlibrary->set_log($dataOrder['order_seq'], array_merge($this->baseLogParams, $logParams));

		$response = [
			"success" => true,
			"data" => ["성공"]
		];
		return $this->_responseContext($response);
	}

	// 반품 & 교환 요청
    public function setOrderReturnOrExchange(array $claim) {
		$this->CI->load->model("returnmodel");
		$this->CI->load->model("ordermodel");
		$this->CI->load->model("exportmodel");

		// 반품이나 환불건은 전부 건 by 건으로 진행하도록 한다.
		$productInfo = $claim["productInfo"];
		$order = $claim["fmOrder"];
		$claimInfo = $productInfo["claim"];
		$claimType = $claimInfo["claimType"];
		$claimDetail = $claimInfo["claimDetail"];
		$claimCollectAddress = $claimDetail["collectAddress"];

		// 환불데이터 찾기
		$existRefundData = $this->alreadyExistRefund($claimInfo, $claim["fmOrderItem"]["item_seq"]);

		$refundCode = $existRefundData["refund"]["refund_code"];
        
        // 교환일 경우에 step 을 변경해야함 ( 반품일 경우 환불프로세스에서 처리가 됌 )
		$optionSeq = null;
		$optionType = null;
		if ($claim["claimProductType"] === "main") {
			$optionType = "option";
			$optionSeq = $claim["option"]["item_option_seq"];
		} else {
			$optionType = "suboption";
			$optionSeq = $claim["option"]["item_suboption_seq"];
		}

		$setStep = 75;
		
		if ($claimType === self::CT_EXCHANGE) {
			$refundCode = "0";
		}

		// 교환건에 대한 반품요청은 환불데이터가 없다. (원주문건에 환불요청이 들어가므로)
		
		if ($claimType === self::CT_RETURN && (!$existRefundData['refund_item']['refund_item_seq'] || !$existRefundData['refund']['refund_seq'])) {
			$refundCode = '0';
			// 원주문건에 대한 환불데이터가 존재하면 넣어준다
			if (isset($order['top_orign_order_seq'])) {
				$originalItemClaimData = $this->_claimRefundFinder($claim);

				$originalExistRefundData = $this->alreadyExistRefund($originalItemClaimData['productInfo']['claim'], $originalItemClaimData['option']['item_seq']);

				$refundCode = $originalExistRefundData['refund']['refund_code'];
			}
		}

		// 교환완료가 된 건에 대한 반품은 set_step_ea 를 증가하지 않도록 한다.(이미 reorder 시 증가되어서 들어가있음)
		if(!$claim["fmOrder"]["orign_order_seq"] && $claim["option"]["step"] < 75) {
			// 기존 step 의 개수를 초기화한다.
			$this->CI->db->set('step'.$claim["option"]["step"], 0);
			$this->CI->db->where('item_'.$optionType.'_seq', $optionSeq);
			$this->CI->db->update('fm_order_item_'.$optionType);
			$this->CI->ordermodel->set_step_ea($setStep, $productInfo["quantity"], $optionSeq, $optionType);

			// 출고건 배송완료처리
			$this->CI->exportmodel->set_status($claim["fmExportItem"]["export_code"], "75");
		}
		
		// step 데이터 검증 후 처리
		
		if ($claim["option"]["step"] < 75) {
			$this->CI->db->set('step', $setStep);
			$this->CI->db->where('item_'.$optionType.'_seq', $optionSeq);
			$this->CI->db->update('fm_order_item_'.$optionType);
			// 주문 option 상태 변경
			$this->CI->ordermodel->set_option_step($optionSeq, $optionType);
			$this->CI->ordermodel->set_order_step($claim["fmOrder"]["order_seq"]);
		}

		// 수거방법
		$returnMethod = "user";
		if (in_array($claimDetail["collectMethodType"], [self::CMT_DELIVERY_BY_CHECKOUT, self::CMT_DELIVERY_INDIVIDUAL])) {
			$returnMethod = "shop";
		}

		$reasonCode = "120";
		
		// 클레임 원인 매칭
		if (in_array($claimDetail['requestType'], ['BROKEN', 'INCORRECT_INFO', 'SOLD_OUT'])) {
			$reasonCode = '310';
		} elseif (in_array($claimDetail['requestType'], ['WRONG_DELIVERY'])) {
			$reasonCode = '210';
		}

		$insertData = [
			"status" => "request",
			"order_seq" => $order["order_seq"],
			"refund_code" => $refundCode,
			"return_type" => strtolower($claimInfo["claimType"]),
			"return_reason" => $claimInfo["requestReason"],
			"cellphone" => $order["recipient_cellphone"],
			"phone" => $order["recipient_phone"],
			"return_method" => $returnMethod,
			"sender_zipcode" => $claimCollectAddress["zipCode"],
			"sender_address_type" => 'street',
			"return_reason" => $claimInfo["requestReason"],
			"reason_code" => $reasonCode,
			"reason_desc" => $claimInfo["claimDetail"]["requestReason"]?:"",
			"sender_address_street" => $claimCollectAddress["baseAddress"]?:"",
			"sender_address_detail" => $claimCollectAddress["detailAddress"]?:"",
			"regist_date" => date("Y-m-d H:i:s"),
			"important" => 0,
			"shipping_price_depositor" => "", // 쓰는 필드인지 물어봐야함
			"shipping_price_bank_account" => "", // 쓰는 필드인지 물어봐야함
			"talkbuy_order_id" => $claim["fmOrder"]["talkbuy_order_id"],
			"talkbuy_return_request_date" => date("Y-m-d H:i:s"),
			"talkbuy_request_group_id" => $claimDetail["requestGroupId"],
			"refund_ship_duty" => ($claimInfo["personInChargeType"] === self::PERSON_ICT_SELLER)?"seller":"buyer",
		];
		
		//배송비 지불 타입 설정
		//if ($claimType === self::CT_RETURN) {
		if ($claimDetail["claimShippingFeePayMethodType"]) {
			$methodType = "D";
			if ($claimDetail["claimShippingFeePayMethodType"] === self::SHIP_FEE_DEDUCTED) {
				$methodType = "M";
				if ($claimDetail["personInChargeType"] === self::PERSON_ICT_PURCHASER) {
					$insertData["return_shipping_gubun"] = "company";
				}
			} else if ($claimDetail["claimShippingFeePayMethodType"] === self::SHIP_FEE_DELEGATED_TO_SELLER) {
				$methodType = "A";
			} else if ($claimDetail["claimShippingFeePayMethodType"] === self::SHIP_FEE_INCLUDED_ON_RETURN) {
				$methodType = "D";
			}
			$insertData["refund_ship_type"] = $methodType;
		}

		$items = [];

		$items[] = [
			"ea" => $productInfo['quantity'],
			"reason_desc" => "",
			"item_seq" => $claim["fmOrderItem"]["item_seq"],
			"export_code" => $claim["fmExportItem"]["export_code"],
			"option_seq" => ($claim["claimProductType"] === "main") ? $claim["option"]["item_option_seq"] : 0,
			"suboption_seq" => ($claim["claimProductType"] === "sub") ? $claim["option"]["item_suboption_seq"] : 0,
			"give_reserve_ea" => 0,
			"give_reserve" => 0,
			"give_point" => 0,
			"talkbuy_product_order_id" => $productInfo['id'],
		];

		$returnCode = $this->CI->returnmodel->insert_return($insertData,$items);

		$title = null;
		if ($claimType === self::CT_EXCHANGE) {
			$title = "교환요청";
		} else {
			$title = "반품요청";
		}

		$logParams = [
			"type" => 'process',
			"title" => $title." (".$returnCode.")",
			"detail" => "[" . $productInfo["id"] . "] Kakao로 부터 ".$title." 되었습니다.",
			"return_code" => $returnCode,
		];
		$this->CI->orderlibrary->set_log($order['order_seq'], array_merge($this->baseLogParams, $logParams));

		$response = [
			"success" => true,
			"data" => [
				"returnCode" => $returnCode
			]
		];
		return $this->_responseContext($response);
	}
	
	// 반품완료 (장애대응-faliover 처리)
	public function setOrderReturnComplete(array $claim) {
		$this->CI->load->model('accountmodel');
		$this->CI->load->model('stockmodel');
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('accountallmodel');

		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];
		$itemData = $claim["fmOrderItem"];
		$option = $claim["option"];

		$existReturnData = $this->alreadyExistReturn($claimInfo, $itemData["item_seq"]);
		if (!$existReturnData["return_item"]["return_item_seq"] || !$existReturnData["return"]["return_seq"]) {
			$setReturnFlag = false;

			// maxReflectionCount 만큼 환불요청 재귀처리
			for($i = 1; $i <= $this->maxReflectionCount; $i++) {
				$setReturn = $this->setOrderReturnOrExchange($claim);
				if($setReturn["success"] === true) {
					$setReturnFlag = true;
					break;
				}
			}
			if ($setReturnFlag === false) {
				// TODO Throw Exception
				$reponseError = [
					"success" => false,
					"data" => ["msg" => "반품&교환 요청 reflection 실패"],
				];

				return $this->_responseContext($reponseError);
			}
		}
		return $this->_execOrderReturnComplete($claim);
	}

	// 반품완료 처리 (실제 반품완료 처리)
    private function _execOrderReturnComplete(array $claim) {
		$this->CI->load->model('accountmodel');
		$this->CI->load->model('stockmodel');
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('accountallmodel');
		$this->CI->load->model('returnmodel');

		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];
		$claimDetail = $claimInfo["claimDetail"];
		$itemData = $claim["fmOrderItem"];
		$option = $claim["option"];
		// 반품요청건이 존재하지 않을 경우 완료처리 불가 완료 후 재귀처리
		// FailOver : 반품 재요청 처리

		$existReturnData = $this->alreadyExistReturn($claimInfo, $itemData["item_seq"]);

		$returnCompleteDate = date("Y-m-d H:i:s");

		if (trim($existReturnData["return"]["return_date"])) {
			$returnCompleteDate = $existReturnData["return"]["return_date"];
		}

		$returnHistoryData = [
			"reason" => "input",
			"supplier_seq" => "",
			"reason_detail" => "반품",
			"stock_date" => date("Y-m-d H:i:s"),
		];

		$stockCode = $this->CI->stockmodel->insert_stock_history($returnHistoryData);

		$optionSeq = null;
		$optionType = null;
		if ($claim["claimProductType"] === "main") {
			$optionType = "option";
			$optionSeq = $option["item_option_seq"];
		} else {
			$optionType = "suboption";
			$optionSeq = $option["item_suboption_seq"];
		}
		
		if ($option) {
			$returnEa = $productInfo["quantity"];
			
			$historyData = [
				'goods_name' => $productInfo['productName'],
				'option_type' => $optionType,
				'stock_code'=> $stockCode,
				'prev_supply_price' => $option['supply_price'],
				'supply_price' => $option['supply_price'],
				'ea' => $returnEa,
			];

			if ($optionType === "option") {
				$this->CI->goodsmodel->stock_option(
					"+",
					$returnEa,
					$itemData["goods_seq"],
					$option["option1"],
					$option["option2"],
					$option["option3"],
					$option["option4"],
					$option["option5"]
				);

				for($i=1;$i<=5;$i++){
					if(!empty($option['title'.$i])){
						$historyData['title'.$i] = $option['title'.$i];
						$historyData['option'.$i] = $option['option'.$i];
					}
				}
			} else if ($optionType === "suboption") {
				$this->CI->goodsmodel->stock_suboption(
					"+",
					$returnEa,
					$itemData["goods_seq"],
					$option["title"],
					$option["suboption"]
				);
				
				if (!empty($option["title"])) {
					$historyData['title'] = $option['title'];
					$historyData['option'] = $option['suboption'];
				}
			}
			$updateParams =  [
				"stock_return_ea" => $returnEa
			];
			$this->CI->db->where($optionType.'_seq', $optionSeq);
			$this->CI->db->update('fm_order_return_item', $updateParams);
			
			// 재고 히스토리 등록
			$this->CI->stockmodel->insert_stock_history_item($historyData);

			$this->CI->goodsmodel->modify_reservation_real($itemData['goods_seq']);

			$res = $this->CI->accountmodel->set_return($existReturnData["return"]['return_code'], $returnCompleteDate);

			// return 테이블 update
			$updateReturnParams = [
				'status' => 'complete',
				'return_date' => $returnCompleteDate,
				'talkbuy_return_complete_date' => date('Y-m-d H:i:s'),
				"talkbuy_request_group_id" => $claimDetail["requestGroupId"],
			];

			// 같은 request 내 반품 배송비가 계산된 데이터 조회
			$params = [
				"talkbuy_order_id" => $claimDetail["orderId"],
				"talkbuy_request_group_id" => $claimDetail["requestGroupId"],
				"status" => "complete",
			];
			$query = $this->CI->returnmodel->get_data_return($params);
			$alreadyCompleteReturnData = $query->result_array();
			
			// 하나의 request에 부과된 반품배송비건이 존재하면 중복 부과하지 않음
			$chargeClaimShippingFeeFlag = true;
			foreach($alreadyCompleteReturnData as $value) {
				if ($value["return_shipping_price"] > 0) {
					$chargeClaimShippingFeeFlag = false;
				}
			}

			// 반품 배송비 계산
			if ($claimDetail["claimShippingFee"] && $claimDetail["claimShippingFee"] > 0 && $chargeClaimShippingFeeFlag === true) {
				$cancelShippingFeeAmount = $claimDetail["cancelShippingFeeAmount"]?:0;
				$updateReturnParams["return_shipping_price"] = $claimDetail["claimShippingFee"] - $cancelShippingFeeAmount;
			}

			$this->CI->db->where('return_code', $existReturnData["return"]['return_code']);
			$this->CI->db->update('fm_order_return', $updateReturnParams);

			// 반품배송비 관련 통합정산테이블 생성 시작
			if ($updateReturnParams["return_shipping_price"] && $updateReturnParams["return_shipping_price"] > 0 && $chargeClaimShippingFeeFlag === true) {
				$this->CI->accountallmodel->insert_calculate_sales_order_returnshipping($claim["fmOrder"]["order_seq"], $existReturnData["return"]['return_code']);
			}
		}
		
		$logParams = [
			"type" => 'process',
			"title" => "반품완료 (".$existReturnData["return"]['return_code'].")",
			"detail" => "[" . $productInfo["id"] . "] Kakao로 부터 반품완료 되었습니다.",
			"return_code" => $existReturnData["return"]['return_code'],
		];
		$this->CI->orderlibrary->set_log($claim["fmOrder"]["order_seq"], array_merge($this->baseLogParams, $logParams));

		$response = [
			"success" => true,
			"data" => [
				"returnCode" => $existReturnData["return"]['return_code']
			]
		];
		return $this->_responseContext($response);
	}

	// TODO(kjw) : 교환 수거 완료 (reorder 처리) : 현재 카카오쪽에서 이벤트를 발생시켜주지 않는 상태
	public function makeOrderExchangeReOrder(array $claim) {
	}

	// TODO(kjw) :  교환 재발송(배송중 상태 변경) : 현재 카카오쪽에서 이벤트를 발생시켜주지 않는 상태
	public function makeOrderExchangeReExport(array $claim) {
	}

	// 교환완료 (장애대응-faliover 처리)
	public function setOrderExchangeComplete(array $claim) {
		$this->CI->load->model('accountmodel');
		$this->CI->load->model('stockmodel');
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('ordermodel');

		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];
		$option = $claim["option"];
		// 취소요청건이 존재하지 않을 경우 완료처리 불가 완료 후 재귀처리
		// FailOver : 환불요청 재요청 처리
		$existReturnData = $this->alreadyExistReturn($claimInfo, $claim["fmOrderItem"]["item_seq"]);
		if (!$existReturnData["return_item"]["return_item_seq"] || !$existReturnData["return"]["return_seq"]) {
			$setReturnFlag = false;

			// maxReflectionCount 만큼 환불요청 재귀처리
			for($i = 1; $i <= $this->maxReflectionCount; $i++) {
				$setReturn = $this->setOrderReturnOrExchange($claim);
				if($setReturn["success"] === true) {
					$setReturnFlag = true;
					break;
				}
			}
			if ($setReturnFlag === false) {
				$reponseError = [
					"success" => false,
					"data" => ["msg" => "교환&반품 데이터 생성이 실패하였습니다."],
					"function" => __FUNCTION__,
					"message" => "Create Return MaxReflection Failed",
				];

				return $this->_responseContext($reponseError);
			}
		}
		// TODO complete failover 처리
		return $this->_execOrderExchangeComplete($claim);
	}

	// 반품 & 교환 철회
	public function setRejectionReturnOrExchange(array $claim) {
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('ordermodel');
		$this->CI->load->model('refundmodel');
		$this->CI->load->model('returnmodel');
		$this->CI->load->model('exportmodel');
		$this->CI->load->helper('order');

		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];

		$returnAndItem = $this->alreadyExistReturn($claimInfo, $claim["fmOrderItem"]["item_seq"]);

		$return = $returnAndItem["return"];
		$returnItem = $returnAndItem["return_item"];
		$dataOrder = $claim["fmOrder"];
		$returnCode = $return["return_code"];

		// 반품데이터가 없으면 취소철회 프로세스를 종료한다.
		if (!$return["return_seq"] || !$returnItem["return_item_seq"]) {
			$reponseError = [
				"success" => false,
				"data" => ["msg" => "반품&교환 데이터가 존재하지 않습니다."],
				"function" => __FUNCTION__,
				"message" => "Not Exist Return Data Failed",
			];

			return $this->_responseContext($reponseError);
		}

		if($return['status'] === 'complete'){
			return;
		}

		$sql = "delete from fm_order_return where return_code=?";
		$this->CI->db->query($sql, $returnCode);

		$sql = "delete from fm_order_return_item where return_code=?";
		$this->CI->db->query($sql, $returnCode);

		$title = null;
		if ($claimInfo["claimType"] === self::CT_EXCHANGE && $claimInfo["claimProcessType"] !== self::CPT_EXCHANGE_DONE) {
			$title = "교환철회";
		} else {
			$title = "반품철회";
		}
		
		$logParams = [
			"type" => 'process',
			"title" => $title." (".$returnCode.")",
			"detail" => "[" . $productInfo["id"] . "] Kakao로 부터 ".$title." 되었습니다.",
		];

		$this->CI->orderlibrary->set_log($dataOrder['order_seq'], array_merge($this->baseLogParams, $logParams));


		## 구매확정사용시 : 마일리지지급예정수량,반품수량 조절 2015-03-26 pjm
		//if($export_items_reserve) $this->exportmodel->exec_export_reserve_ea($export_items_reserve,'return_cancel');

		// // 해당 출고의 구매확정 처리
		// if($auto_buyconfirms){
		// 	$dataExportArr		= array();
		// 	if(preg_match('/^B/', $export_code)){
		// 		$data_export_tmp	= $this->exportmodel->get_export_bundle($export_code);
		// 		foreach($data_export_tmp['bundle_order_info'] as $bundle_key => $bundle_val){
		// 			$dataExportArr[] = $bundle_key;
		// 		}
		// 	}else{
		// 		$dataExportArr[]	= $export_code;
		// 	}

		// 	// 구매확정 관련 프로세스 통합 by hed
		// 	foreach($dataExportArr as $export_code){
		// 		$this->buyconfirmlib->exec_buyconfirm($export_code, $msg);
		// 	}
		// }

		$response = [
			"success" => true,
			"data" => [
				"returnCode" => $returnCode
			]
		];
		return $this->_responseContext($response);
	}

    // 교환완료 처리 (실제 교환완료 처리)
    private function _execOrderExchangeComplete(array $claim) {
		$this->CI->load->model('accountmodel');
		$this->CI->load->model('stockmodel');
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('ordermodel');
		$this->CI->load->model('order2exportmodel');
		$this->CI->load->library('buyconfirmlib');

		$productInfo = $claim["productInfo"];
		$claimInfo = $productInfo["claim"];
		$claimDetail = $claimInfo["claimDetail"];
		$option = $claim["option"];

		$existReturnData = $this->alreadyExistReturn($claimInfo, $claim["fmOrderItem"]["item_seq"]);
		
		$returnCompleteDate = date("Y-m-d H:i:s");

		if (trim($existReturnData["return"]["return_date"])) {
			$returnCompleteDate = $existReturnData["return_date"];
		}
		// 재주문 넣기
		$reOrderSeq = $this->CI->ordermodel->reorder($existReturnData['return']['order_seq'], $existReturnData["return"]["return_code"]);
		
		$res = $this->CI->accountmodel->set_return($existReturnData["return"]['return_code'], $returnCompleteDate);
		// return 테이블 update
		$updateReturnParams = [
			'status' => 'complete',
			'return_date' => $returnCompleteDate,
			'talkbuy_return_complete_date' => date('Y-m-d H:i:s'),
		];

		// 교환 배송비 계산
		if ($claimDetail["claimShippingFee"] && $claimDetail["claimShippingFee"] > 0) {
			$cancelShippingFeeAmount = $claimDetail["cancelShippingFeeAmount"]?:0;
			$updateReturnParams["return_shipping_price"] = $claimDetail["claimShippingFee"] - $cancelShippingFeeAmount;
		}

		$this->CI->db->where('return_code', $existReturnData["return"]['return_code']);
		$this->CI->db->update('fm_order_return', $updateReturnParams);

		// 재주문건에 대한 출고처리 -> 출고완료 -> 배송완료
		$getReOrderShippingParams = [
			"order_seq" => $reOrderSeq,
		];

		$reOrderShippingData = $this->CI->partnerlib->getOrderShipping($getReOrderShippingParams, $productInfo["shippingFeeGroupId"], "talkbuy");
		
		$getReOrderItemParams = [
			"order_seq" => $reOrderSeq,
			"shipping_seq" => $reOrderShippingData[$productInfo["groupId"]]["shipping_seq"],
			"goods_seq" => $claim["fmOrderItem"]["goods_seq"],
			"orderId" => $claimInfo["claimDetail"]["orderId"],
		];
		
		$reOrderItemData = $this->CI->partnerlib->getOrderItem($getReOrderItemParams, self::KAKAO_PG);

		if (!$reOrderItemData["item_seq"]) {
			$reponseError = [
				"success" => false,
				"data" => ["msg" => "교환완료시, OrderItem 조회 실패"],
				"function" => __FUNCTION__,
				"message" => "Get Order Item Failed",
			];

			return $this->_responseContext($reponseError);
		}
		
		$reOrderItemData = $reOrderItemData;

		$itemOptionData = [];

		$optionParameters = [
			"order_seq" => $reOrderSeq,
			"shipping_seq" => $reOrderItemData["shipping_seq"],
			"item_seq" => $reOrderItemData["item_seq"],
			"orderId" => $claimInfo["claimDetail"]["orderId"],
			"id" => $productInfo["id"],
			"packageNumber" => $productInfo["shippingFeeGroupId"],
		];
		if ($claim["claimProductType"] === "main") {
			$itemOptionData = $this->CI->partnerlib->getOrderItemOption($optionParameters, self::KAKAO_PG);
		} else {
			// getOrderItemOption productId 는 본상품의 productId 를 가져가야함
			// 현재 본상품의 productId 가져오기
			$query = "select * from fm_order_item_option where item_option_seq=?";
			$query = $this->CI->db->query($query, array($claim["option"]["item_option_seq"]));
			$optionData = $query->row_array();

			if (!$optionData["talkbuy_product_order_id"]) {
				$reponseError = [
					"success" => false,
					"data" => ["msg" => "톡체크아웃 상품 ID 가 존재하지 않습니다."],
					"function" => __FUNCTION__,
					"message" => "Get talkbuy_product_order_id Failed",
				];
	
				return $this->_responseContext($reponseError);
			}

			// fm_order_item_option.talkbuy_product_order_id reassignment
			$optionParameters["id"] = $optionData["talkbuy_product_order_id"];
			$tempOptionData = $this->CI->partnerlib->getOrderItemOption($optionParameters, self::KAKAO_PG);

			$suboptionParameters = [
				"order_seq" => $reOrderSeq,
				"item_seq" => $reOrderItemData["item_seq"],
				"option_seq" => $tempOptionData["item_option_seq"],
				"orderId" => $claimInfo["claimDetail"]["orderId"],
				"id" => $productInfo["id"],
				"packageNumber" => $productInfo["shippingFeeGroupId"],
			];
			$itemOptionData = $this->CI->partnerlib->getOrderItemSubOption($suboptionParameters, self::KAKAO_PG);
		}

		if (!$itemOptionData["order_seq"]) {
			$reponseError = [
				"success" => false,
				"data" => ["msg" => "교환완료시, itemOption 데이터가 존재하지 않습니다."],
				"function" => __FUNCTION__,
				"message" => "Get ItemOptionData Failed",
			];

			return $this->_responseContext($reponseError);
		}

		$reExportItem = $this->_getOrderExportData($reOrderSeq);
		
		
		if (!$reExportItem[$productInfo["id"]]["item_seq"]) {
			$reponseError = [
				"success" => false,
				"data" => ["msg" => "교환완료시, itemItem 데이터가 존재하지 않습니다."],
				"function" => __FUNCTION__,
				"message" => "Get itemItem Failed",
			];

			return $this->_responseContext($reponseError);
		}

		$exportItem = array();
		$exportItem['item_seq'][] = $itemOptionData["item_seq"];
		$exportItem['shipping_seq'][] = $itemOptionData["shipping_seq"];
		$exportItem['option_seq'][] = ($claim["claimProductType"] === "main") ? $itemOptionData["item_option_seq"] : 0;
		$exportItem['suboption_seq'][] = ($claim["claimProductType"] === "sub") ? $itemOptionData["item_suboption_seq"] : 0;
		$exportItem['ea'][] = $itemOptionData["ea"];
		$exportItem['export_item_seq'][] = $reExportItem["export_item_seq"];
		$exportItem['talkbuy_product_order_id'][] = $productInfo["id"];
		$exportItem['talkbuy_status'][] = 'y';

		$exportData = array(
			"goods_kind" => "goods",
			"status" => "55",
			"shipping_seq" => $itemOptionData["shipping_seq"],
			"order_seq" => $reOrderSeq,
			"talkbuy_order_id" => $claimInfo["claimDetail"]["orderId"],
			"shipping_method" => $productInfo["shipping_method"],
			"delivery_company_code" => $productInfo["delivery_company"],
			"delivery_number" => $productInfo["invoiceNo"],
			"export_date"	=> date("Y-m-d"),
			"regist_date"	=> date("Y-m-d H:i:s"),
			"complete_date"	=> date("Y-m-d H:i:s"),
			"shipping_provider_seq" => $reExportItem["shipping_provider_seq"],
			"items" => $exportItem,
		);
		$expResult = $this->CI->order2exportmodel->export_for_goods(array($exportData), [], 'order_api');
		$exportCode = [];
		foreach($expResult['55'] as $export){
			$exportCode[] = $export['export_code'];
		}
		
		foreach($exportCode as $code) {
			$addParams["partner"] 	= $this->baseLogParams["add_info"];
			$addParams["actor"] 	= $this->baseLogParams["add_info"];
			$addParams["doer"] 		= "Kakao Pay";
			$this->CI->buyconfirmlib->exec_buyconfirm($code, $msg, $addParams);
		}
		
		// 반품배송비 관련 통합정산테이블 생성 시작
		//$this->CI->accountallmodel->insert_calculate_sales_order_returnshipping($claim["fmOrder"]["order_seq"], $existReturnData["return"]['return_code']);
		
		// Set Log
		$logParams = [
			"type" => 'process',
			"title" => "교환완료 (".$existReturnData["return"]['return_code'].")",
			"detail" => "[" . $productInfo["id"] . "] Kakao로 부터 " . $claimInfo["claimProcessTypeKorean"]." 되었습니다.",
			"return_code" => $existReturnData["return"]['return_code'],
		];

		$this->CI->orderlibrary->set_log($existReturnData['return']['order_seq'], array_merge($this->baseLogParams, $logParams));
		
		$response = [
			"success" => true,
			"data" => [
				"returnCode" => $existReturnData["return"]['return_code']
			]
		];
		return $this->_responseContext($response);
    }


	// refund 데이터 확인
	public function alreadyExistRefund(array $claimInfo, $itemSeq): array {
		$refundItemIn = [
			"item_seq" => $itemSeq,
			"id" => $claimInfo["claimDetail"]["orderProductId"],
		];
		$refundItemData = $this->CI->partnerlib->getRefundItem($refundItemIn, self::KAKAO_PG);
		$refundIn = [
			"refund_code" => $refundItemData["refund_code"],
			"order_id" => $claimInfo["claimDetail"]["orderId"],
		];
		$refundData = $this->CI->partnerlib->getRefund($refundIn, self::KAKAO_PG);

		$result = [
			"refund" => $refundData,
			"refund_item" => $refundItemData,
		];

		return $result;
	}
	
	// return 데이터 확인
	public function alreadyExistReturn(array $claimInfo, $itemSeq): array {
		
		$returnItemIn = [
			"item_seq" => $itemSeq,
			"id" => $claimInfo["claimDetail"]["orderProductId"],
		];
		$returnItemData = $this->CI->partnerlib->getReturnItem($returnItemIn, self::KAKAO_PG);
		$returnIn = [
			"return_code" => $returnItemData["return_code"],
			"order_id" => $claimInfo["claimDetail"]["orderId"],
		];
		$returnData = $this->CI->partnerlib->getReturn($returnIn, self::KAKAO_PG);

		$result = [
			"return" => $returnData,
			"return_item" => $returnItemData,
		];

		return $result;
	}

	// claim 데이터 key context 검증
	private function _claimContextValidator(array $claim): bool {
		$essentialKey = [
			"claimProductType",
			"fmOrder",
			"fmOrderItem",
			"fmOrderShipping",
			"productInfo",
			"option",
		];

		$returnValue = true;

		foreach($essentialKey as $key => $value) {
			if (!array_key_exists($value, $claim)) {
				$returnValue = false;
				break;
			}

			if (count($claim["productInfo"]["claim"]) <= 0) {
				$returnValue = false;
				break;
			}
		}
		return $returnValue;
	}

	// response 데이터 context 검증
	private function _responseContext(array $response) {
		$essentialKey = [
			"success",
			"data",
		];

		$contextValid = true;
		foreach($essentialKey as $key => $value) {
			if (!array_key_exists($value, $response)) {
				$contextValid = false;
				break;
			}
		}

		if ($response["success"] === false) {
			// 에러로그 삽입
			writeCsLog($response, "kakaoClaimFailedCase", "talkbuy");
		}

		return $response;
	}

	// talklibrary->get_order_export_data 함수 copy (talklibrary 의 메소드를 가져다 쓰는 건 바람직하지 않음)
	private function _getOrderExportData(string $orderSeq) {
		// ===========================================================================
		// 출고 데이터 추출용 쿼리 생성 시작
		// ===========================================================================
		$this->CI->load->library('exportlibrary');
		$exportData = $this->CI->exportlibrary->get_order_export_data($orderSeq);
		// ===========================================================================
		// 출고 데이터 추출용 쿼리 생성 종료
		// ===========================================================================
		$result = [];
		foreach($exportData["ordershipping"] as $fKey => $fVal) {
			foreach($fVal as $sKey => $sVal) {
				$shipping_provider_seq = $s_val["provider_seq"];
				foreach($sVal["options"] as $option) {
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
}
?>