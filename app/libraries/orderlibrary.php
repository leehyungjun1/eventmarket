<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 주문과 관련된 소스들이 컨트롤러와 모델에 산재되어 있어 향후 병합을 위한 라이브러리 구조 
 * 2018-08-06
 * by hed 
 */
class OrderLibrary
{
	public $allow_exit = true;
	
	function __construct() {
		$this->CI =& get_instance();
		$this->CI->load->model('ordermodel');
		$this->CI->arr_step = config_load('step');
		$this->CI->load->helper('order');
	}
	/**
	 * 주문 조회 by hed
	 * @param type $order_seq
	 * @param type $wheres
	 * @return type
	 */
	function get_order($wheres =array()){
		$order_seq = null;
		$get_all = false;
		if($wheres['order_seq']){
			$order_seq = $wheres['order_seq'];
		}
		unset($wheres['order_seq']);
		
		if(empty($order_seq)){
			$get_all = true;
		}
		$orders = $this->CI->ordermodel->get_order($order_seq, $wheres, $get_all);
		return $orders;
	}
	/**
	 * 하위 주문건중 가장 최신 주문건 추출
	 * @param type $order_seq
	 * @param type $wheres
	 * @return type
	 */
	function get_last_order_by_top_orign($wheres =array()){
		$order_seq = null;
		$get_all = false;
		if($wheres['order_seq']){
			$order_seq = $wheres['order_seq'];
		}
		unset($wheres['order_seq']);
		
		if(empty($order_seq)){
			$get_all = true;
		}
		$orders = $this->CI->ordermodel->get_last_order_by_top_orign($order_seq, $wheres, $get_all);
		return $orders;
	}
	/**
	 * 주문 마스터 저장 구성 by hed
	 * params 이외에 post, get, cookie에서 데이터를 추출한다.
	 * 향후 ordermodel->insert_order의 수정이 필요
	 * @param $params					입력 데이터
	 * $_POST							post 데이터
	 * $_GET							get 데이터
	 * $_COOKIE							cookie 데이터
	 * 
	 * @return $order_seq			리턴 데이터
	 */
	function save_order($params){
		$order_seq = $this->CI->ordermodel->insert_order($params);
		
		// 주문 통계 저장
		$this->CI->load->model('statsmodel');
		$this->CI->statsmodel->insert_order_stats($order_seq);
		
		return $order_seq;
	}

	/**
	 * 주문 정보 변경 by hyem
	 */
	function set_order($order_seq, $params){
		$params = filter_keys($params, $this->CI->db->list_fields('fm_order'));
		$where_params	= array(
			'order_seq'	=> $order_seq
		);		
		$order_seq = $this->CI->ordermodel->set_order($params, $where_params);
		return $order_seq;
	}

	/**
	 * 주문 로그 추가 by hyem
	 */
	function set_log($order_seq, $params){
		$this->CI->ordermodel->set_log($order_seq, $params["type"], $params["actor"], $params["title"], $params["detail"], $params["caccel_arr"], $params["export_code"], $params["add_info"], $params["refund_code"], $params["return_code"]);
	}

	/**
	 * 주문 아이템 저장 구성 by hed
	 * OrderService 오픈 마켓에서 사용하는 소스가 기반이며 현재는 O2O 주문에 특화되어 있음, 
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $in				인풋 데이터
	 *		$orderSeq;									// 주문번호
	 *		$shippingSeqList;							// 주문 배송 고유키 목록
	 *		$shippingInfoList;							// 배송 정보 목록
	 *		$productShippingCodeList;					// 상품별 배송코드 목록
	 *		$nowOrderList;								// 주문 상품 목록 | array() 
	 *			$orderRow['market_product_code'];			// 외부 판매처 상품 고유키
	 *			$orderRow['provider_seq'];					// 상품의 입점사
	 *			$orderRow['seq'];							// 외부 판매 주문 고유키
	 *			$orderRow['fm_goods_seq'];					// 연결 상품 고유키
	 *			$orderRow['fm_goods_code'];					// 연결 상품 코드
	 *			$orderRow['fm_goods_image'];				// 연결 상품 이미지
	 *			$orderRow['fm_goods_name'];					// 연결 상품명
	 *			$orderRow['tax'];							// 연결 상품 부가세
	 *			$orderRow['adult_goods'];					// 연결 상품 성인 여부 | N : 일방상품, Y : 성인상품
	 *			$orderRow['goods_type'];					// 연결 상품 타입 | goods : 일반상품, gift : 사은품
	 *			$orderRow['individual_refund'];				// 개별취소 가능 여부
	 *			$orderRow['individual_refund_inherit'];		// 본상품 취소시 함께 취소 여부
	 *			$orderRow['individual_export'];				// 개별출고 가능 여부
	 *			$orderRow['individual_return'];				// 개별반품 교환 가능 여부
	 *			$orderRow['global_shipping_yn'];			// 개별 출고 설정 사용 여부
	 *			$orderRow['shipping_group_seq'];			// 배송그룹 고유키
	 *			$orderRow['product_price'];					// 외부 상품 판매 금액
	 *			$orderRow['order_product_price'];			// 외부 상품 판매 금액 - 할인전
	 *			$orderRow['order_qty'];						// 외부 상품 판매 수량
	 * 
	 * @param array &$out				리턴 데이터
	 *		$orderItemList;								// 주문 상품 목록
	 *		
	 */
	function save_order_item($in, &$out){	// 결과 반환
		
		$this->CI->load->model('categorymodel');
		$this->CI->load->model('goodsmodel');
		
		$orderSeq						= $in['orderSeq'];
		$shippingSeqList				= $in['shippingSeqList'];
		$shippingInfoList				= $in['shippingInfoList'];
		$productShippingCodeList		= $in['productShippingCodeList'];
		$nowOrderList					= $in['nowOrderList'];
		
		$orderItemList					= array();
		
		foreach((array)$nowOrderList as $key => $orderRow) {

			// 상품정보 등록 - 중복 입력 방지
			if (isset($orderItemList[$orderRow['market_product_code']]) !== true) {

				$itemParams									= array();
				$itemParams['provider_seq']					= ($orderRow['provider_seq'])?$orderRow['provider_seq']:1;
				$itemParams['shipping_seq']					= $shippingSeqList[$orderRow['seq']];
				$itemParams['order_seq']					= $orderSeq;

				$itemParams['goods_seq']					= ($orderRow['fm_goods_seq'])?$orderRow['fm_goods_seq']:0;
				$itemParams['goods_code']					= $orderRow['fm_goods_code'];
				$itemParams['image']						= $orderRow['fm_goods_image'];
				$itemParams['goods_name']					= ($orderRow['fm_goods_name'])?$orderRow['fm_goods_name']:$orderRow['purchase_goods_name'];
				$itemParams['tax']							= ($orderRow['tax'])?$orderRow['tax']:'none';
				$itemParams['adult_goods']					= $orderRow['adult_goods'];
				$itemParams['goods_type']					= ($orderRow['goods_type'])?$orderRow['goods_type']:'goods';
				$itemParams['reservation_ship']				= 'n';
				$itemParams['multi_discount_ea']			= 0;
				$itemParams['goods_kind']					= 'goods';

				$itemParams['individual_refund']			= ($orderRow['individual_refund']) ? $orderRow['individual_refund'] : '0';
				$itemParams['individual_refund_inherit']	= ($orderRow['individual_refund_inherit']) ? $orderRow['individual_refund_inherit'] : '0';
				$itemParams['individual_export']			= ($orderRow['individual_export']) ? $orderRow['individual_export'] : '0';
				$itemParams['individual_return']			= ($orderRow['individual_return']) ? $orderRow['individual_return'] : '0';

				if ($orderRow['global_shipping_yn'] != 'Y'){
					$itemParams['shipping_policy']			= ($shippingInfoList[$orderRow['shipping_group_seq']]['shipping_calcul_type'] == 'each') ? 'goods' : 'shop';
					$itemParams['basic_shipping_cost']		= $productShippingCodeList[$orderRow['market_product_code']]['basic_shipping_cost'];
					$itemParams['add_shipping_cost']		= $productShippingCodeList[$orderRow['market_product_code']]['add_shipping_cost'];
				} else {
					$itemParams['shipping_policy']			= 'shop';
				}
				

				$this->CI->db->insert('fm_order_item', $itemParams);
				$nowItemSeq	= $this->CI->db->insert_id();
				$orderItemList[$orderRow['market_product_code']]	= $nowItemSeq;

				

				// 필수 옵션 저장
				foreach($orderRow['optionInfo'] as $optionInfo){
					$optionParams									= $optionInfo;

					// 옵션코드
					$optionParams['goods_code'] = $orderRow['fm_goods_code'].$optionInfo['optioncode1'].$optionInfo['optioncode2'].$optionInfo['optioncode3'].$optionInfo['optioncode4'].$optionInfo['optioncode5'];//조합된상품코드

					$optionParams['package_yn']						= $optionInfo['package_yn'];
					$optionParams['basic_sale']						= 0;
					$optionParams['event_sale_target']				= 0;
					
					// 마일리지와 예치금
					$optionParams['reserve']						= $orderRow['reserve_one'];
					$optionParams['point']							= $orderRow['point_one'];
					

					// 입력받은 할인 내역 저장
					foreach($this->CI->arr_sale_list as $sale_name){
						// 할인 내역 저장
						$optionParams[$sale_name.'_sale']			= ($orderRow[$sale_name.'_sale_unit'] * $orderRow['order_qty']) + $orderRow[$sale_name.'_sale_rest'];	
						// 할인 내역 저장 - 상품별
						$optionParams[$sale_name.'_sale_unit']		= $orderRow[$sale_name.'_sale_unit'];
						// 할인 내역 저장 - 나머지
						$optionParams[$sale_name.'_sale_rest']		= $orderRow[$sale_name.'_sale_rest'];
						
						$optionParams[$sale_name.'_sale_krw']		= 0;
						if($optionParams[$sale_name.'_sale'] > 0){
							$optionParams[$sale_name.'_sale_krw'] = get_currency_exchange($optionParams[$sale_name.'_sale'],"KRW",$this->CI->config_system['basic_currency']);
						}else{
							$optionParams[$sale_name.'_sale_krw'] = '0';
						}
					}
					// 원인을 분석하진 못 했으나 회원혜택의 경우 개별 상품의 가격을 저장함
					$optionParams['member_sale']			= $orderRow['member_sale_unit'];
						
					
					$optionParams['download_seq']					= $orderRow['download_seq'];
					$optionParams['coupon_input']					= 0;
					$optionParams['coupon_input_one']				= 0;
					$optionParams['promotion_code_seq']				= 0;
					$optionParams['referersale_seq']				= 0;
					$optionParams['salescost_provider_coupon']		= 0;
					$optionParams['salescost_provider_promotion']	= 0;
					$optionParams['salescost_provider_referer']		= 0;

					$optionParams['purchase_goods_name']			= $orderRow['purchase_goods_name'];
					$optionParams['order_seq']						= $orderSeq;
					$optionParams['item_seq']						= $orderItemList[$orderRow['market_product_code']];
					$optionParams['provider_seq']					= $orderRow['provider_seq'];
					$optionParams['shipping_seq']					= $shippingSeqList[$orderRow['seq']];
					$optionParams['step']							= "25";
					$optionParams['price']							= $orderRow['product_price'];
					$optionParams['ori_price']						= $orderRow['order_product_price'];
					$optionParams['org_price']						= $orderRow['order_product_price'];
					$optionParams['consumer_price']					= $orderRow['order_product_price'];
					$optionParams['sale_price']						= $orderRow['sale_price'];
					
					// 매입가
					$supply_price = 0;
					if(!empty($orderRow['supply_price'])){
						$supply_price = $orderRow['supply_price'];
					}elseif(!empty($optionInfo['supply_price'])){
						$supply_price = $optionInfo['supply_price'];

						if( $this->CI->scm_cfg['use'] == 'Y' ){
							if	($optionInfo['tax']){
								$this->CI->load->model('scmmodel');
								$supply_price	= $supply_price + round($supply_price * 0.1);
								$supply_price	= $this->CI->scmmodel->cut_exchange_price($this->CI->config_system['basic_currency'], $supply_price);
							}
						}
					}
					$optionParams['supply_price']					= $supply_price;

					$optionParams['ea']								= $orderRow['order_qty'];
					$optionParams['reserve_log']					= $orderRow['reserve_log'];
					$optionParams['point_log']						= $orderRow['point_log'];

					if($optionInfo['commission_type'] == 'SACO' || $optionInfo['commission_type'] == ''){
						//수수료 방식 정산
						$optionParams['commission_price']			= $optionParams['price'] * (100 - $optionInfo['commission_rate']) / 100;
					}else{
						//공급가 방식 정산
						if($optionInfo['commission_type'] == 'SUPR')
							$optionParams['commission_price']		= $optionInfo['commission_rate'];
						else
							$optionParams['commission_price']		= (int)$optionParams['consumer_price'] * $optionInfo['commission_rate'] / 100;
					}
					$optionParams['commission_price_krw']			= $optionParams['commission_price'];
					
					

					// 패키지여부
					$insert_params['package_yn'] = $orderRow['package_yn'];

					// 개별 배송메세지 저장 :: 2016-09-02 lwh
					if($orderRow['each_msg'] == 'Y')
						$insert_params['ship_message'] = $orderRow['each_memo'][$k];

					// 주문서쿠폰할인 추가
					$insert_params['unit_ordersheet'] = $orderRow['unit_ordersheet'];
					
					$filter_optionParams		= filter_keys($optionParams, $this->CI->db->list_fields('fm_order_item_option'));
					$this->CI->db->insert('fm_order_item_option', $filter_optionParams);
					
					$nowItemOptionSeq		= $this->CI->db->insert_id();
					$lastItemOptionSeq		= $nowItemOptionSeq;
					

					/**
					* 정산개선 - 옵션처리 시작
					* data : 주문정보
					* insert_params : 필수옵션정보
					* @ accountallmodel
					**/
					$optionParams['order_goods_seq']			= ($optionInfo['goods_seq'])?$optionInfo['goods_seq']:0;
					$optionParams['order_goods_name']			= ($optionInfo['goods_name'])?$optionInfo['goods_name']:$orderRow['purchase_goods_name'];;
					$optionParams['order_goods_kind']			= ($optionInfo['goods_kind'])?$optionInfo['goods_kind']:'goods';
					$optionParams['commission_rate']			= $optionInfo['commission_rate'];
					$optionParams['commission_type']			= $optionInfo['commission_type'];
					$optionParams['item_option_seq']			= $nowItemOptionSeq;
					$optionParams['order_form_seq']				= $nowItemOptionSeq;
					//$optionParams['shipping_seq']				= $shippingSeqList[$orderRow['seq']];
					//$optionParams['multi_sale_provider']		= ($orderRow['provider_seq'] != 1)?100:0;//해당상품이 입점사상품이면 입점사부담율 100%/본사라면 0
					$optionParams['accountallmodeltest']		= "accountallmodeltest_opt";
					$account_ins_opt[$nowItemOptionSeq] = array_merge($optionParams,$optionInfo);
					/**
					* 정산개선 - 옵션처리 끝
					* data : 주문정보
					* insert_params : 필수옵션정보
					* @
					**/
				
					// 추가 옵션 저장
					foreach($orderRow['subOptionInfo'.'_'.$optionInfo['goods_seq']] as $subOptionInfo){

						$subptionParams							= array();
						$subptionParams['order_seq'] 			= $orderSeq;
						$subptionParams['item_seq'] 			= $orderItemList[$orderRow['market_product_code']];
						$subptionParams['item_option_seq']		= $lastItemOptionSeq;
						$subptionParams['step'] 				= "25";
						$subptionParams['price'] 				= $orderRow['product_price'];
						$subptionParams['reserve']				= $orderRow['reserve_one'];
						$subptionParams['point']				= $orderRow['point_one'];
						$subptionParams['consumer_price']		= $orderRow['order_product_price'];
						
						// 매입가
						$supply_price = 0;
						if(!empty($orderRow['supply_price'])){
							$supply_price = $orderRow['supply_price'];
						}elseif(!empty($subOptionInfo['supply_price'])){
							$supply_price = $subOptionInfo['supply_price'];

							if( $this->CI->scm_cfg['use'] == 'Y' ){
								if	($optionInfo['tax']){
									$this->CI->load->model('scmmodel');
									$supply_price	= $supply_price + round($supply_price * 0.1);
									$supply_price	= $this->CI->scmmodel->cut_exchange_price($this->CI->config_system['basic_currency'], $supply_price);
								}
							}
						}
						$subptionParams['supply_price']					= $supply_price;
						
						$subptionParams['ea']					= $orderRow['order_qty'];
						$subptionParams['title']				= $subOptionInfo['suboption_title'];
						$subptionParams['suboption']			= $subOptionInfo['suboption'];
						$subptionParams['goods_code'] 			= $subOptionInfo['goods_seq'];
						$subptionParams['suboption_code']		= $subOptionInfo['suboption_seq'];
						$subptionParams['reserve_log']			= $orderRow['reserve_one_log'];
						$subptionParams['point_log']			= $orderRow['point_one_log'];
						$subptionParams['sale_price']			= $orderRow['sale_price'];


						// 입력받은 할인 내역 저장
						foreach($this->CI->arr_sale_list as $sale_name){
							// 할인 내역 저장
							$subptionParams[$sale_name.'_sale']			= ($orderRow[$sale_name.'_sale_unit'] * $orderRow['order_qty']) + $orderRow[$sale_name.'_sale_rest'];	
							// 할인 내역 저장 - 상품별
							$subptionParams[$sale_name.'_sale_unit']		= $orderRow[$sale_name.'_sale_unit'];
							// 할인 내역 저장 - 나머지
							$subptionParams[$sale_name.'_sale_rest']		= $orderRow[$sale_name.'_sale_rest'];

							$subptionParams[$sale_name.'_sale_krw']		= 0;
							if($subptionParams[$sale_name.'_sale'] > 0){
								$subptionParams[$sale_name.'_sale_krw'] = get_currency_exchange($subptionParams[$sale_name.'_sale'],"KRW",$this->CI->config_system['basic_currency']);
							}else{
								$subptionParams[$sale_name.'_sale_krw'] = '0';
							}
						}
						// 원인을 분석하진 못 했으나 회원혜택의 경우 개별 상품의 가격을 저장함
						$subptionParams['member_sale']			= $orderRow['member_sale_unit'];

						if ((int)$subOptionInfo['package_count'] > 0) {
							$subptionParams['package_yn']		= 'y';
							$packageYn							= 'Y';
						}


						if($subOptionInfo['commission_type'] == 'SACO' || $subOptionInfo['commission_type'] == ''){
							//수수료 방식 정산
							$subptionParams['commission_price']		= $subptionParams['price'] * (100 - $subOptionInfo['commission_rate']) / 100;
						}else{
							//공급가 방식 정산
							if($subOptionInfo['commission_type'] == 'SUPR')
								$subptionParams['commission_price']	= $subOptionInfo['commission_rate'];
							else
								$subptionParams['commission_price']	= (int)$subptionParams['consumer_price'] * $subOptionInfo['commission_rate'] / 100;
						}

						$subptionParams['commission_price_krw']		= $subptionParams['consumer_price'];

						$filter_suboptionParams		= filter_keys($subptionParams, $this->CI->db->list_fields('fm_order_item_suboption'));
						$this->CI->db->insert('fm_order_item_suboption', $filter_suboptionParams);
						$nowSubOptionSeq		= $this->CI->db->insert_id();

						/**
						* 정산개선 - 추가옵션처리 시작
						* data : 주문정보
						* insert_params : 추가옵션정보
						* @ accountallmodel
						**/
						$subptionParams['order_goods_seq']			= ($optionInfo['goods_seq'])?$optionInfo['goods_seq']:0;
						$subptionParams['order_goods_name']			= ($optionInfo['goods_name'])?$optionInfo['goods_name']:$orderRow['purchase_goods_name'];;
						$subptionParams['order_goods_kind']			= ($optionInfo['goods_kind'])?$optionInfo['goods_kind']:'goods';
						$subptionParams['item_suboption_seq']		= $nowSubOptionSeq;
						$subptionParams['order_form_seq']			= $nowSubOptionSeq;
						$subptionParams['provider_seq'] 			= $orderRow['provider_seq'];
						$subptionParams['shipping_seq']				= $shippingSeqList[$orderRow['seq']];
						$subptionParams['accountallmodeltest']		= "accountallmodeltest_sub";
						$account_ins_subopt[$item_suboption_seq] = array_merge($subptionParams,$subOptionInfo);
						/**
						* 정산개선 - 추가옵션처리 끝
						* data : 주문정보
						* insert_params : 추가옵션정보
						* @accountallmodel
						**/
					}
				}
			}
		}

		// 결과 반환
		$out['orderItemList']				= $orderItemList;
		$out['account_ins_opt']				= $account_ins_opt;
		$out['account_ins_subopt']				= $account_ins_subopt;
	}

	/**
	 * 주문 아이템 - fm_order_item by hyem
	 * save_order_item 이 기반이며 save_order_item 은 한번에 item/option/suboption/input 을 저장하여 각각 저장하도록 분리함
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $params				인풋 데이터
	 *		$params;									// 주문 상품 정보
	 * 
	 * @param $nowItemSeq				리턴 데이터
	 *		
	 */
	function save_row_order_item($params) {
		$filter_params		= filter_keys($params, $this->CI->db->list_fields('fm_order_item'));
		$this->CI->db->insert('fm_order_item', $filter_params);
		$nowItemSeq	= $this->CI->db->insert_id();

		$this->order_item_category($params['goods_seq'], $nowItemSeq);

		return $nowItemSeq;
	}

	/**
	 * 주문 아이템 옵션 - fm_order_item_option by hyem
	 * save_order_item 이 기반이며 save_order_item 은 한번에 item/option/suboption/input 을 저장하여 각각 저장하도록 분리함
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $params				인풋 데이터
	 *		$params;									// 주문 상품 정보
	 * 
	 * @param array 
	 *		$nowOptionSeq;									// 주문 상품 옵션seq
	 *		$account_ins_opt;								// 주문 상품 정산 데이터
	 *		
	 */
	function save_row_order_item_option($params) {
		$this->CI->load->helper('accountall');

		$filter_params		= filter_keys($params, $this->CI->db->list_fields('fm_order_item_option'));
		$this->CI->db->insert('fm_order_item_option', $filter_params);
		$nowOptionSeq	= $this->CI->db->insert_id();
		/**
		* 정산개선 - 옵션처리 시작
		* data : 주문정보
		* insert_params : 필수옵션정보
		* @ accountallmodel
		**/
		$opt_params['commission_rate']			= $params['commission_rate'];
		$opt_params['commission_type']			= $params['commission_type'];
		$opt_params['item_option_seq']			= $nowOptionSeq;
		$opt_params['order_form_seq']			= $nowOptionSeq;
		$opt_params['shipping_seq']				= $params["shipping_seq"];
		$opt_params['multi_sale_provider']		= ($params["provider_seq"] != 1)?100:0;//해당상품이 입점사상품이면 입점사부담율 100%/본사라면 0
		$opt_params['event_sale_provider']		= $params['salescost_provider'];			// 입점사 이벤트 할인 부담"율"
		$opt_params['coupon_sale_provider']		= $params['salescost_provider_coupon'];	// 입점사 쿠폰 할인 부담"액"
		$opt_params['referer_sale_provider']	= $params['salescost_provider_referer'];	// 입점사 유입경로 할인 부담"액"

		$opt_params['multi_sale_unit']			= $params['multi_sale'] / $params["ea"];		// 정산 대량구매할인 개당 할인금액 2020-01-09 

		$opt_params['accountallmodeltest']		= "accountallmodeltest_opt";

		$_commission_info					= array();
		foreach(get_commission_info_field() as $_field) $_commission_info[$_field] = $opt_params[$_field];
		$_commission_info['target_price']		= $params['price'];
		$_commission_info['commission_rate']	= $params['commission_rate'];
		$_commission_info['commission_type']	= $params['commission_type'];
		$_commission_info['pay_price']			= $params['sale_price'];
		$_commission_info['salescost_provider']	= 0;
		$_return_commission 					= get_commission($_commission_info);

		$opt_params['commission_price'] 		= $_return_commission['old_commission_unit_price'];		// (구)정산금액 : 기존처럼 option에 저장됨.
		$opt_params['commission_price_krw']		= $_return_commission['old_commission_unit_price_krw'];	// (구)정산금액 : 기존처럼 option에 저장됨.
		
		$account_ins_opt[$nowOptionSeq] = array_merge($opt_params,$params);

		/**
		 * 패키지 상품 추가 처리
		 */
		if( $params["package_yn"] == 'y' ){
			if(!$this->CI->ordermodel)					$this->CI->load->model("ordermodel");
			if(!$this->CI->orderpackagemodel)			$this->CI->load->model("orderpackagemodel");

			$this->CI->orderpackagemodel->package_order($params["order_seq"]);

			// 연결상품 정산금액 업데이트
			$aOrderItemOption = $this->CI->ordermodel->get_item_option($params["order_seq"]);
			if($aOrderItemOption){
				foreach($aOrderItemOption as $row){
					foreach($account_ins_opt as $seq => &$opt){
						if($row['item_option_seq'] == $seq){
							$opt['supply_price'] = $row['supply_price'];
						}
					}
				}
			}
		}

		$result["nowOptionSeq"] 	= $nowOptionSeq;
		$result["account_ins_opt"]	= $account_ins_opt;

		return $result;
	}

	/**
	 * 주문 아이템 추가옵션 - fm_order_item_suboption by hyem
	 * save_order_item 이 기반이며 save_order_item 은 한번에 item/option/suboption/input 을 저장하여 각각 저장하도록 분리함
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $params				인풋 데이터
	 *		$params;									// 주문 상품 옵션 정보
	 * 
	 * @param array 
	 *		$nowSubOptionSeq;									// 주문 상품 추가옵션seq
	 *		$account_ins_opt;								// 주문 상품 정산 데이터
	 *		
	 */
	function save_row_order_item_suboption($params) {
		$filter_params		= filter_keys($params, $this->CI->db->list_fields('fm_order_item_suboption'));
		$this->CI->db->insert('fm_order_item_suboption', $filter_params);
		$nowSubOptionSeq	= $this->CI->db->insert_id();
		/**
		* 정산개선 - 추가옵션처리 시작
		* data : 주문정보
		* opt_params : 추가옵션정보
		* @ accountallmodel
		**/
		$opt_params['order_goods_seq']			= $params['goods_seq'];
		$opt_params['order_goods_name']			= $params['goods_name'];
		$opt_params['order_goods_kind']			= $params['goods_kind'];
		$opt_params['commission_price'] 		= $params['commission_unit_price'];			//(신)정산금액
		$opt_params['commission_price_krw']		= $params['commission_unit_price_krw'];		//(신)정산금액 원화기준 정산가
		$opt_params['item_suboption_seq']		= $nowSubOptionSeq;
		$opt_params['order_form_seq']			= $nowSubOptionSeq;
		$opt_params['provider_seq'] 			= $params['provider_seq'];
		$opt_params['shipping_seq']				= $params["shipping_seq"];
		$opt_params['accountallmodeltest']		= "accountallmodeltest_sub";
		$account_ins_subopt[$nowSubOptionSeq] = array_merge($opt_params,$params);

		/**
		 * 패키지 상품 추가 처리
		 */
		if( $params["package_yn"] == 'y' ){
			if(!$this->CI->ordermodel)					$this->CI->load->model("ordermodel");
			if(!$this->CI->orderpackagemodel)			$this->CI->load->model("orderpackagemodel");

			$this->CI->orderpackagemodel->package_order($params["order_seq"]);
			// 연결상품 정산금액 업데이트
			$aOrderItemSubOption = $this->CI->ordermodel->get_item_suboption($params["order_seq"]);
			if($aOrderItemSubOption){
				foreach($aOrderItemSubOption as $row){
					foreach($account_ins_subopt as $seq => &$opt){
						if($row['item_suboption_seq'] == $seq){
							$opt['supply_price'] = $row['supply_price'];
						}
					}
				}
			}
		}

		$result["nowSubOptionSeq"] 		= $nowSubOptionSeq;
		$result["account_ins_subopt"]	= $account_ins_subopt;

		return $result;
	}

	/**
	 * 주문 아이템 입력옵션 - fm_order_item_input by hyem
	 * save_order_item 이 기반이며 save_order_item 은 한번에 item/option/suboption/input 을 저장하여 각각 저장하도록 분리함
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $params				인풋 데이터
	 *		$params;									// 주문 상품 옵션 정보
	 * 
	 * @param array 
	 *		$nowInputSeq;									주문 상품 입력옵션seq
	 *		
	 */
	function save_row_order_item_input($params) {
		$filter_params		= filter_keys($params, $this->CI->db->list_fields('fm_order_item_input'));
		$this->CI->db->insert('fm_order_item_input', $filter_params);
		$nowInputSeq	= $this->CI->db->insert_id();

		$result["nowInputSeq"] 		= $nowInputSeq;
		return $result;
	}
	
	
	/**
	 * 주문 정산 저장 by hed
	 * OrderService 오픈 마켓에서 사용하는 소스가 기반이며 현재는 O2O 주문에 특화되어 있음, 
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $in				인풋 데이터
	 *		$orderSeq;									// 주문번호
	 *		$account_ins_opt;							// 정산 필수옵션 정보
	 *		$account_ins_subopt;						// 정산 추가옵션 정보
	 *		$account_ins_shipping;						// 정산 배송 정보
	 * 
	 * @param array &$out				리턴 데이터
	 *		
	 */
	function save_order_account($in, &$out){	
		$orderSeq							= $in['orderSeq'];
		$order_seq							= $orderSeq;
		$account_ins_opt					= $in['account_ins_opt'];
		$account_ins_subopt					= $in['account_ins_subopt'];
		$account_ins_shipping				= $in['account_ins_shipping'];
		
		$all_order_list		= $this->CI->ordermodel->get_order($order_seq);
		
		/**
		* 1-1 주문데이타를 이용한 임시매출데이타 생성 시작
		* step1->step2->step3 순차로 진행되어야 합니다.
		* @
		**/
		if(!$this->CI->accountall)			$this->CI->load->helper('accountall');
		if(!$this->CI->accountallmodel)		$this->CI->load->model('accountallmodel');
		//step1 주문금액별 정의/비율/단가계산 후 정렬
		$set_order_price_ratio = $this->CI->accountallmodel->set_order_price_ratio($orderSeq, $account_ins_opt, $account_ins_subopt, $account_ins_shipping);
		//step2 적립금/이머니/에누리(관리자주문) update
		if( $all_order_list['emoney']>0 || $all_order_list['cash']>0  || $all_order_list['enuri']>0 ) {
			$this->CI->accountallmodel->update_ratio_emoney_cash_enuri_npoint($order_seq, $set_order_price_ratio, 'all');
		}
		//step3 임시 매출/정산 저장
		$this->CI->accountallmodel->insert_calculate_sales_order_tmp($orderSeq, $set_order_price_ratio, $account_ins_opt, $account_ins_subopt, $account_ins_shipping);
		//debug_var($this->CI->db->queries);
		//debug_var($this->CI->db->query_times);
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
		//$this->CI->accountallmodel->insert_calculate_sales_order_deposit($order_seq);		// ordermodel->set_step 25 에서 무조건 실행함
		//debug_var($this->CI->db->queries);
		//debug_var($this->CI->db->query_times);
		/**
		* 2-1 결제확인시 임시매출데이타를 이용한 미정산매출데이타 끝
		* 정산개선 - 미정산매출데이타 처리
		* @ 
		**/
	}
	/**
	 * 주문 생성 후 업데이트  by hyem
	 * 주문 생성 후 필수 업데이트 function
	 * 
	 * @param array $in				인풋 데이터
	 *		$orderSeq;									// 주문번호
	 *		$account_ins_opt;							// 정산 필수옵션 정보
	 *		$account_ins_subopt;						// 정산 추가옵션 정보
	 *		$account_ins_shipping;						// 정산 배송 정보
	 * 
	 * @param array &$out				리턴 데이터
	 *		
	 */
	function proc_order_after_init($in, &$out){	
		$orderSeq						= $in['orderSeq'];
		$order_seq						= $orderSeq;
		
		// 주문 총주문수량 / 총상품종류 업데이트 leewh 2014-08-01
		$this->CI->ordermodel->update_order_total_info($order_seq);
		// 마일리지/에누리/예치금 사용 상품옵션,추가옵션 별로 나누기
		$this->CI->ordermodel->update_unit_emoney_cash_enuri($order_seq);//상품별통계로 그대로 둠
		
		// ===========================================================================
		// 정산 처리 시작
		// ===========================================================================
		$this->save_order_account($in, $out);	
		// ===========================================================================
		// 정산 처리 종료
		// ===========================================================================

	}
	/**
	 * 주문 출고예약량 업데이트  by hed
	 * OrderService 오픈 마켓에서 사용하는 소스가 기반이며 현재는 O2O 주문에 특화되어 있음, 
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param $orderSeq;									// 주문번호
	 *		
	 */
	function proc_order_reservation($orderSeq){	
		$this->CI->load->model('goodsmodel');
		
		$result_option	= $this->CI->ordermodel->get_item_option($orderSeq);
		$result_suboption = $this->CI->ordermodel->get_item_suboption($orderSeq);

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
			$this->CI->goodsmodel->modify_reservation_real($goods_seq);
		}

	}
	
	/**
	 * 주문 성공 후 처리 by hed
	 * OrderService 오픈 마켓에서 사용하는 소스가 기반이며 현재는 O2O 주문에 특화되어 있음, 
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $in				인풋 데이터
	 *		$orderSeq;									// 주문번호
	 *		$orderAddParams;							// 주문 추가 수정 정보
	 * 
	 * @param array &$out				리턴 데이터
	 */
	function proc_order_after_success($in, &$out){	
		$orderSeq						= $in['orderSeq'];
		$orderAddParams					= $in['orderAddParams'];
		$addLogParams					= $in['addLogParams'];
		// 주문 성공과 결제 성공의 단계가 나눠져있으므로 별도로 처리한다.

		// ===========================================================================
		// 주문 상태 업데이트 시작 | 주문접수
		// ===========================================================================
		$this->CI->ordermodel->set_step($orderSeq,15,$orderAddParams);
		// ===========================================================================
		// 주문 상태 업데이트 종료
		// ===========================================================================

		// ===========================================================================
		// 출고 예약량 업데이트 시작
		// ===========================================================================
		$this->proc_order_reservation($orderSeq);	
		// ===========================================================================
		// 출고 예약량 업데이트 종료
		// ===========================================================================


		// 주문 처리, 주문서 정보 가져오기
		$orders			= $this->CI->ordermodel->get_order($orderSeq);

		// ===========================================================================
		// 주문 로그 생성 시작
		// ===========================================================================
		$add_log = "";
		$etc_log = "";
		if($orders['orign_order_seq']) $add_log = "[재주문]";
		if($orders['admin_order']) $add_log = "[관리자".$this->CI->managerInfo['manager_id']."주문]";
		if($orders['person_seq']) $add_log = "[개인결제]";
		if($orders['settleprice'] == 0 ){
			if($orders['settleprice']) $etc_log = "[전액할인]";
		}
		$log_title =  $add_log."주문접수"."(".$orders['mpayment'].")".$etc_log;
		// 로그 생성
		if(isset($addLogParams)) {
			$this->CI->ordermodel->set_log($orderSeq,'pay',$addLogParams["actor"],$addLogParams["title"],$addLogParams["detail"], $addLogParams["caccel_arr"], $addLogParams["export_code"], $addLogParams["add_info"]);
		} else {
			$this->CI->ordermodel->set_log($orderSeq,'pay',$orders['order_user_name'],$log_title,'');
		}
		// ===========================================================================
		// 주문 로그 생성 종료
		// ===========================================================================
	}
	
	/**
	 * 결제 성공 후 처리 by hed
	 * OrderService 오픈 마켓에서 사용하는 소스가 기반이며 현재는 O2O 주문에 특화되어 있음, 
	 * 향후 다른 주문 입력용으로 변경 시 추가 입력 변수 할당 고려 필수
	 * 
	 * @param array $in				인풋 데이터
	 *		$orderSeq;									// 주문번호
	 *		$account_ins_opt;							// 정산 필수옵션 정보
	 *		$account_ins_subopt;						// 정산 추가옵션 정보
	 *		$account_ins_shipping;						// 정산 배송 정보
	 *		$orderAddParams;							// 주문 추가 수정 정보
	 * 
	 * @param array &$out				리턴 데이터
	 */
	function proc_order_after_payment($in, &$out){	
		$orderSeq						= $in['orderSeq'];
		$order_seq						= $orderSeq;
		$orderAddParams					= $in['orderAddParams'];
		$addLogParams					= $in['addLogParams'];

		// ===========================================================================
		// 주문 상태 업데이트 시작 | 결제확인
		// ===========================================================================
		$this->CI->ordermodel->set_step($order_seq,25,$orderAddParams);
		// ===========================================================================
		// 주문 상태 업데이트 종료
		// ===========================================================================
		
		// 주문 처리, 주문서 정보 가져오기
		$orders			= $this->CI->ordermodel->get_order($orderSeq);

		// ===========================================================================
		// 출고 예약량 업데이트 시작
		// ===========================================================================
		$this->proc_order_reservation($orderSeq);	
		// ===========================================================================
		// 출고 예약량 업데이트 종료
		// ===========================================================================

		$result = $this->CI->ordermodel->get_item_option($orderSeq);
		$data_item_option = $result;
		$result_option = $result;
		$result = $this->CI->ordermodel->get_item_suboption($orderSeq);
		$result_suboption = $result;

		// 주문서 정보 가져오기
		$data_shipping	= $this->CI->ordermodel->get_order_shipping($orderSeq);

		// 회원 마일리지 차감
		if( $orders['emoney']>0 && $orders['member_seq'] && $orders['emoney_use']=='none')
		{
			$params = array(
					'gb'		=> 'minus',
					'type'		=> 'order',
					'emoney'	=> $orders['emoney'],
					'ordno'		=> $orderSeq,
					'memo'		=> "[차감]주문 ({$orderSeq})에 의한 마일리지 차감",
					'memo_lang'	=> $this->CI->membermodel->make_json_for_getAlert("mp260",$orderSeq),   // [차감]주문 (%s)에 의한 마일리지 차감
			);
			$this->CI->membermodel->emoney_insert($params, $orders['member_seq']);
			$this->CI->ordermodel->set_emoney_use($orderSeq,'use');
		}

		// 회원 예치금 차감
		if( $orders['cash']>0 && $orders['member_seq'] && $orders['cash_use']=='none')
		{
			$params = array(
					'gb'		=> 'minus',
					'type'		=> 'order',
					'cash'		=> $orders['cash'],
					'ordno'		=> $orderSeq,
					'memo'		=> "[차감]주문 ({$orderSeq})에 의한 예치금 차감",
					'memo_lang'	=> $this->CI->membermodel->make_json_for_getAlert("mp261",$orderSeq),   // [차감]주문 (%s)에 의한 예치금 차감
			);
			$this->CI->membermodel->cash_insert($params, $orders['member_seq']);
			$this->CI->ordermodel->set_cash_use($orderSeq,'use');
		}

		//상품쿠폰사용
		if($data_item_option) foreach($data_item_option as $item_option){
			if($item_option['download_seq']) $this->CI->couponmodel->set_download_use_status($item_option['download_seq'],'used');
		}
		//배송비쿠폰사용 @2015-06-22 pjm
		if($data_shipping) foreach($data_shipping as $shipping){
			if($shipping['shipping_coupon_down_seq']) $this->CI->couponmodel->set_download_use_status($shipping['shipping_coupon_down_seq'],'used');
		}
		//배송비쿠폰사용(사용안함)
		if($orders['download_seq']) $this->CI->couponmodel->set_download_use_status($orders['download_seq'],'used');

		//주문서쿠폰 사용 처리 by hed
		if($orders['ordersheet_seq']) $this->CI->couponmodel->set_download_use_status($orders['ordersheet_seq'],'used');

		//프로모션코드 상품/배송비 할인 사용처리
		$this->CI->load->model('promotionmodel');
		$this->CI->promotionmodel->setPromotionpayment($orders);
		
		
		// ===========================================================================
		// 주문 로그 생성 시작
		// ===========================================================================
		$add_log = "";
		$etc_log = "";
		if($orders['orign_order_seq']) $add_log = "[재주문]";
		if($orders['admin_order']) $add_log = "[관리자".$this->CI->managerInfo['manager_id']."주문]";
		if($orders['person_seq']) $add_log = "[개인결제]";
		if($orders['settleprice'] == 0 ){
			if($orders['settleprice']) $etc_log = "[전액할인]";
		}
		$log_title =  $add_log."결제확인"."(".$orders['mpayment'].")".$etc_log;
		// 로그 생성
		if(isset($addLogParams)) {
			$this->CI->ordermodel->set_log($orderSeq,'pay',$addLogParams["actor"],$addLogParams["title"],$addLogParams["detail"], $addLogParams["caccel_arr"], $addLogParams["export_code"], $addLogParams["add_info"]);
		} else {
			$this->CI->ordermodel->set_log($orderSeq,'pay',$orders['order_user_name'],$log_title,'');
		}
		// ===========================================================================
		// 주문 로그 생성 종료
		// ===========================================================================

	}

	/**
	 * 주문 무효 처리 by hyem
	 * dailyOrder->order_cancel 참고
	 * order_process->cancel_order 참고
	 * 
	 * @param array $in				인풋 데이터
	 *		$orderSeq;									// 주문번호
	 *		$orderAddParams;							// 주문 추가 수정 정보
	 * 
	 * @param array &$out				리턴 데이터
	 */
	function proc_order_cancel($in){
		$orderSeq						= $in['orderSeq'];
		$order_seq						= $orderSeq;
		$orderAddParams					= $in['orderAddParams'];
		$addLogParams					= $in['addLogParams'];

		// 주문 처리, 주문서 정보 가져오기
		$orders			= $this->CI->ordermodel->get_order($orderSeq);

		// 주문 무효 처리
		$this->CI->ordermodel->set_step($orderSeq,95);

		// ===========================================================================
		// 출고 예약량 업데이트 시작
		// ===========================================================================
		$this->proc_order_reservation($orderSeq);	
		// ===========================================================================
		// 출고 예약량 업데이트 종료
		// ===========================================================================
		
		$log_title =  "주문무효";
		if(isset($addLogParams)) {
			$this->CI->ordermodel->set_log($orderSeq,'cancel',$addLogParams["actor"],$addLogParams["title"],$addLogParams["detail"], $addLogParams["caccel_arr"], $addLogParams["export_code"], $addLogParams["add_info"]);
		} else {
			$this->CI->ordermodel->set_log($orderSeq,'cancel',"시스템",$log_title,'');
		}
	}

	/**
	 * 배송그룹별 상품 합계 구하기
	 */
	public function get_shipping_goods_price($order_seq, $shipping_seq) {
		$params = array(
			"item.order_seq" 	=> $order_seq,
			"item.shipping_seq" => $shipping_seq,
		);
		$option 	= $this->CI->ordermodel->get_item_join_option($params,"option");
		$suboption 	= $this->CI->ordermodel->get_item_join_option($params,"suboption");

		$goods_price = 0;
		foreach($option as $data){
			if($data["step"] != "85") {
				$goods_price += $data["price"];
			}
		}
		foreach($suboption as $data){
			if($data["step"] != "85") {
				$goods_price += $data["price"];
			}
		}
		return $goods_price;
	}
	
	public function call_exit(){
		if($this->allow_exit){
			exit;
		}
	}

	// 외부주문 linkage_mallname_text 반환 
	public function get_order_market_name(&$orders) {
		//마켓연동 연동 서비스 사용유무
		$this->CI->load->library('Connector');
		$this->CI->load->model('connectormodel');
		$this->CI->load->library("o2o/o2oservicelibrary");

		$connector	= $this->CI->connector::getInstance();	

		// 오픈마켓 주문
		if ($connector->isConnectorUse() && empty($this->CI->marketList)){
			$this->CI->marketList		= $connector->getAllMarkets(true);
			
			//샵링커 추가 2017-09-27 jhs
			$this->CI->load->model('connectormodel');
			$this->CI->shopLinkermarketList = array();
			$shopLinkerUseMarketList = $this->CI->connectormodel->getLinkageMarketGroup();
			foreach($shopLinkerUseMarketList as $marketInfo){
				$this->CI->shopLinkermarketList[$marketInfo['marketCode']] = array('name'=>$marketInfo['marketName'],'productLink'=>'');
			}
			
			unset($this->CI->marketList['shoplinker']);
		}

		// POS 주문 매장 정보 추출
		if(empty($this->CI->o2oStoreList)) {
			$this->CI->o2oStoreList = $this->CI->o2oservicelibrary->get_o2o_config(array(),999);
		}

		if($orders['linkage_id'] == 'connector') {
			if(substr($orders['linkage_mall_code'],0,3) == "API"){
				$orders['linkage_mallname_text']	= $this->CI->shopLinkermarketList[$orders['linkage_mall_code']]['name'];
			}else{
				$orders['linkage_mallname_text']	= $this->CI->marketList[$orders['linkage_mall_code']]['name'];
			}
		}elseif ($orders['linkage_id'] == 'pos' && $o2oStoreList) {
			foreach($o2oStoreList as $o2oStore){
				if($o2oStore['o2o_store_seq'] == $orders['linkage_mall_code']){
					$orders['linkage_mallname_text']	= $o2oStore['pos_name'];
				}
			}
		}
	}

	/**
	 * order (fm_order row)
	 * 	결제확인 mail 및 sms 발송
	 */
	public function send_step25_mail_sms($order) {
		// 주문번호 없으면 return
		if (!$order['order_seq']) {
			return;
		}
		// 이미 발송했으면 return
		if ($order['sms_25_YN'] == 'Y') {
			return;
		}
		$order_seq = $order['order_seq'];

		// 결제확인 메일 발송
		$arr = [];
		if (is_order_present($order)) {
			$arr = ['label'=>'present', 'regist_address' => false];
		}
		send_mail_step25($order_seq, $arr);

		// 휴대폰 번호 없으면 sms 발송 안함
		if (!$order['order_cellphone']) {
			return;
		}

		// 결제확인 sms 발송
		// 아이템 정보
		$items = $this->CI->ordermodel->get_item($order_seq);

		// 입점사 정보
		$providerList = array();
		$providerArr = array();
		foreach ($items as $item) {
			if (!$item['provider_seq']) {
				continue;
			}
			$providerList[$item['provider_seq']] = 1;
			$providerArr[] = $item['provider_seq'];
		}

		// sms 발송 데이터 생성
		if ($order['payment'] === 'bank') {
			$bank_arr = explode(' ', $order['bank_account']);
			$settle_kind = $bank_arr[0];
		} else {
			$settle_kind = $this->CI->ordermodel->arr_payment[$order['payment']];
		}
		if ($order['member_seq']) {
			// member_seq 이용하여 userid추출
			$this->CI->load->model('membermodel');
			$userid = $this->CI->membermodel->get_member_userid($order['member_seq']);
		}

		$params['settle_kind'] = $settle_kind . ' 입금확인';
		$params['shopName'] = $this->CI->config_basic['shopName'];
		$params['ordno'] = $order_seq;
		$params['order_user'] = $order['order_user_name'];
		if ($order['member_seq']) {
			$params['userid'] = $userid;
		}

		// sms 데이터 생성
		$commonSmsData = array();
		$commonSmsData['settle']['phone'][] = $order['order_cellphone'];
		$commonSmsData['settle']['params'][] = $params;
		$commonSmsData['settle']['order_seq'][] = $order_seq;

		sendSMS_for_provider('settle', $providerList, $params);
		unset($providerList);

		//입점관리자 SMS 데이터
		$send_for_provider = $this->CI->send_for_provider;
		if (count($send_for_provider['order_cellphone']) > 0) {
			foreach ($send_for_provider['order_cellphone'] as $key => $value) {
				$provider_msg[$key] = $send_for_provider['msg'][$key];
				$provider_order_cellphones[$key] = $send_for_provider['order_cellphone'][$key];
			}
			$commonSmsData['provider']['msg'] = $provider_msg;
			$commonSmsData['provider']['phone'] = $provider_order_cellphones;
		}

		// 선물하기 주문 시 commonSmsData 변경
		if (is_order_present($order)) {
			$commonSmsData = $this->settlePresentSMS(['order' => $order, 'commonSmsData' => $commonSmsData, 'params' => $params]);
		}
		
		if (count($commonSmsData) > 0) {
			commonSendSMS($commonSmsData);
		}

		$order_params = array(
			'sms_25_YN' => 'Y',
		);
		$this->CI->orderlibrary->set_order($order_seq, $order_params);

		if (!is_order_present($order)) {
			// 관리자 푸시알림 발송 2018-01-02 jhr
			push_for_admin([
				'kind' => 'order_deposit',
				'unique' => $order_seq,
				'ordno' => $order_seq,
				'member_seq' => $order['member_seq'],
				'provider_list' => $providerArr,
				'user_name' => $order['order_user_name'],
			]);
		}
	}

	/**
	 * 선물하기 주문인 경우 sms data 가공
	 * @param
	 *  $order : fm_order data
	 *  $params : params data
	 *  $commonSmsData : sms 데이터
	 * @return
	 *  $commonSmsData : sms 데이터
	 */
	protected function settlePresentSMS($data) {
		// array param 정의
		$order = $data['order'];
		$params = $data['params'];
		$commonSmsData = $data['commonSmsData'];

		// 입점관리자 unset
		unset($commonSmsData['provider']);
		// label => present
		$commonSmsData['settle']['label'][] = 'present';

		// 선물받는분 추가
		$commonSmsData['present_receive']['phone'][] = $order['recipient_cellphone'];
		$commonSmsData['present_receive']['params'][] = $params;
		$commonSmsData['present_receive']['order_seq'][] = $order['order_seq'];
		return $commonSmsData;
	}

	/**
	 * 최초 배송지 등록 시 (입점)관리자 mail,sms,push 발송
	 */
	public function first_regist_recipient_zipcode ($orders) {
		$order_seq = $orders['order_seq'];
		// 이메일
		send_mail_step25($order_seq, ['label'=>'present','regist_address'=>true]);

		// sms 데이터 생성
		$params['settle_kind'] = $this->CI->ordermodel->arr_payment[$orders['payment']] . ' 입금확인';
		$params['shopName'] = $this->CI->config_basic['shopName'];
		$params['ordno'] = $order_seq;
		$params['order_user'] = $orders['order_user_name'];

		$commonSmsData = array();
		$commonSmsData['settle']['params'][] = $params;
		$commonSmsData['settle']['order_seq'][] = $order_seq;

		$items = $this->CI->ordermodel->get_item($order_seq);

		// 입점사 정보
		$providerList = array();
		$providerArr = array();
		foreach ($items as $item) {
			if (!$item['provider_seq']) {
				continue;
			}
			$providerList[$item['provider_seq']] = 1;
			$providerArr[] = $item['provider_seq'];
		}
		sendSMS_for_provider('settle', $providerList, $params);
		unset($providerList);
		
		$send_for_provider = $this->CI->send_for_provider;
		if (count($send_for_provider['order_cellphone']) > 0) {
			foreach ($send_for_provider['order_cellphone'] as $key => $value) {
				$provider_msg[$key] = $send_for_provider['msg'][$key];
				$provider_order_cellphones[$key] = $send_for_provider['order_cellphone'][$key];
			}
			$commonSmsData['provider']['msg'] = $provider_msg;
			$commonSmsData['provider']['phone'] = $provider_order_cellphones;
		}
		if (count($commonSmsData) > 0) {
			commonSendSMS($commonSmsData);
		}

		// push
		push_for_admin(array(
			'kind' => 'order_deposit',
			'unique' => $order_seq,
			'ordno' => $order_seq,
			'member_seq' => $orders['member_seq'],
			'provider_list' => $providerArr,
			'user_name' => $orders['order_user_name'],
		));
	}


	/**
	 * 선물하기 배송지미등록으로 자동 환불
	 * 환불 데이터 생성 > PG환불 > sms발송 > 환불완료처리
	 * @param $order : fm_order fulll data
	 * @return boolean
	 */
	public function present_cancel($order) {
		if(!$this->CI->ordermodel) $this->CI->load->model('ordermodel');
		if(!$this->CI->refundmodel) $this->CI->load->model('refundmodel');
		if(!$this->CI->couponmodel) $this->CI->load->model('couponmodel');
		if(!$this->CI->promotionmodel) $this->CI->load->model('promotionmodel');
		if(!$this->CI->refundmodel) $this->CI->load->library('refundlibrary');

		// 환불 데이터 생성
		/**
		 * [
			'tmp' => $tmp,
			'result_option' => $result_option,
			'result_suboption' => $result_suboption,
			'refund_data' => $refund_data,
			];
		 */
		$data = $this->CI->refundlibrary->insert_full_refund($order['order_seq']);
		if(!$data['refund_data']['refund_code']) {
			return ['result'=>'fail','msg'=>'환불데이터 생성 오류'];
		}

		// pg 결제 했을 경우에만 (전액할인 제외) PG환불
		if($order['payment'] != "bank") {
			$cancelFunction = $order['pg']."_cancel";
			$cancelResult = $this->CI->refundmodel->$cancelFunction($order,$data['refund_data']);
			if(!$cancelResult['success']) {
				return ['result'=>'fail','msg'=>'PG 환불 처리 오류'];
			}
		}

		// sms발송
		$commonSmsData = [];
		// 주문자
		$commonSmsData['present_cancel_order']['phone'][] = $order['order_cellphone'];
		$commonSmsData['present_cancel_order']['order_no'][] = $order['order_seq'];
		// 수신자
		$commonSmsData['present_cancel_receive']['phone'][] = $order['recipient_cellphone'];
		$commonSmsData['present_cancel_receive']['order_no'][] = $order['order_seq'];

		if (count($commonSmsData) > 0) {
			commonSendSMS($commonSmsData);
		}

		// 환불완료처리
		$this->CI->refundlibrary->process_refund_complete([
			'order' => $order,
			'tmp' => $data['tmp'],
			'result_option' => $data['result_option'],
			'result_suboption' => $data['result_suboption'],
			'refund_data' => $data['refund_data'],
		]);

		return ['result'=>'success'];
	}

	/**
	 * 주문상세 - 사용자스킨
	 */
	public function order_view_front($orders) {
		$this->CI->load->model('shippingmodel');
		$this->CI->load->model('goodsmodel');
		$this->CI->load->model('returnmodel');

		$cfg_order 	= ($CI->cfg_order) ? $CI->cfg_order : config_load('order');

		$items  = $this->CI->ordermodel->get_item($orders['order_seq']);
		foreach($items as $key=> $item){

			if($item['goods_kind'] == 'goods') {
				// 실물상품
				$is_goods	= true;
				$tot['goodstotal']++;
			} else {
				// 티켓상품
				$is_coupon	= true;
				$tot['coupontotal']++;
			}

			## 사은품
			$item['gift_title'] = "";
			if($item['goods_type'] == "gift"){
				$this->CI->load->model('giftmodel');
				$giftlog = $this->CI->giftmodel->get_gift_title($orders['order_seq'],$item['item_seq']);
				$item['gift_title'] = $giftlog['gift_title'];
			}

			$options 	= $this->CI->ordermodel->get_option_for_item($item['item_seq']);

			$data_shipping = $this->CI->ordermodel->get_order_shipping($orders['order_seq'],null,$item['shipping_seq']);
			foreach($data_shipping as $group_key => $row_shipping){
				$item['shipping_method_name'] = $row_shipping['shipping_set_name'];

				// 배송정보 추출 :: 2016-11-01 lwh
				if($row_shipping['shipping_method'] == 'coupon'){
					$row_shipping['shipping_method_name'] = getAlert("sy077");	// '쿠폰';
					$row_shipping['calcul_type'] = 'bundle';
				}else{
					$ship_info = $this->CI->shippingmodel->get_ship_info($row_shipping['shipping_group'],$row_shipping['shipping_method']);
					if($ship_info['cart_opt_seq']){
						$row_shipping['shipping_method'] .= '_each';
						$row_shipping['calcul_type'] = 'each';
					}else{
						$row_shipping['calcul_type'] = 'bundle';
					}
				}

				$shipping_info_arr[$row_shipping['shipping_group']] = $row_shipping;
			}

			if($options) foreach($options as $k => $data){
				$item['shipping_cnt']++;
				$real_stock = $this->CI->goodsmodel -> get_goods_option_stock(
					$item['goods_seq'],
					$data['option1'],
					$data['option2'],
					$data['option3'],
					$data['option4'],
					$data['option5']
				);

				$rstock = $this->CI->ordermodel -> get_option_reservation(
					$this->CI->cfg_order['ableStockStep'],
					$item['goods_seq'],
					$data['option1'],
					$data['option2'],
					$data['option3'],
					$data['option4'],
					$data['option5']
				);

				$stock = (int) $real_stock - (int) $rstock;
				$data['mstep']		= $this->CI->arr_step[$data['step']];
				$data['real_stock'] = $real_stock;
				$data['stock'] = $stock;

				$data['out_supply_price'] = $data['supply_price']*$data['ea'];
				$data['out_consumer_price'] = $data['consumer_price']*$data['ea'];
				$data['out_price'] = $data['price']*$data['ea'];

				//sale 6가지
				// 이벤트,복수구매할인 항목 추가 2018-07-13 pjw
				$data['out_event_sale']				= $data['event_sale'];
				$data['out_multi_sale']				= $data['multi_sale'];
				$data['out_member_sale']			= $data['member_sale']*$data['ea'];//1
				$data['out_coupon_sale']			= ($data['download_seq'])?$data['coupon_sale']:0;//2
				$data['out_coupon_sale']			+= $data['unit_ordersheet'];
				$data['out_fblike_sale']			= $data['fblike_sale'];//3
				$data['out_mobile_sale']			= $data['mobile_sale'];//4
				$data['out_promotion_code_sale']	= $data['promotion_code_sale'];//5
				$data['out_referer_sale']			= $data['referer_sale'];//6
				$data['out_tot_sale']				= $data['out_event_sale'] + $data['out_multi_sale'] + $data['out_member_sale']+$data['out_coupon_sale']+$data['out_fblike_sale']+$data['out_mobile_sale']+$data['out_promotion_code_sale']+$data['out_referer_sale'];

				$data['out_reserve'] = $data['reserve']*$data['ea'];
				$data['out_point'] = $data['point']*$data['ea'];
				$data['step_complete'] = $data['step45']+$data['step55']+$data['step65']+$data['step75'];

				// 개별 배송메시지 별도 정의 :: 2016-09-02 lwh
				if($orders['each_msg_yn'] == 'Y'){
					$goods_info_name = $item['goods_name'];
					$item_option_seq = $data['item_option_seq'];
					$ship_message['ship_message'][$item_option_seq]	= $data['ship_message'];
					$ship_message['goods_info'][$item_option_seq]	= $goods_info_name;
					$ship_message['goods_option'][$item_option_seq]	= $this->CI->goodsmodel->optionToStr($data);
				}

				###
				$data['inputs']	= $this->CI->ordermodel->get_input_for_option($data['item_seq'], $data['item_option_seq']);

				$tot['ea'] += $data['ea'];
				$tot['supply_price'] += $data['out_supply_price'];
				$tot['consumer_price'] += $data['out_consumer_price'];
				$tot['price'] += $data['out_price'];

				//sale 6가지
				$tot['event_sale']	+= $data['out_event_sale'];
				$tot['multi_sale']	+= $data['out_multi_sale'];
				$tot['member_sale'] += $data['out_member_sale'];
				$tot['coupon_sale'] += $data['out_coupon_sale'];
				$tot['fblike_sale'] += $data['out_fblike_sale'];
				$tot['mobile_sale'] += $data['out_mobile_sale'];
				$tot['promotion_code_sale'] += $data['out_promotion_code_sale'];
				$tot['referer_sale'] += $data['out_referer_sale'];

				$tot['reserve'] += $data['out_reserve'];
				$tot['point'] += $data['out_point'];

				$tot['real_stock'] += $real_stock;
				$tot['stock'] += $stock;

				$return_item = $this->CI->returnmodel->get_return_item_ea($data['item_seq'],$data['item_option_seq']);
				$able_return_ea += ($data['step55']+$data['step65']+$data['step75']) - (int) $return_item['ea'];

				$suboptions = $this->CI->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);

				if($suboptions) foreach($suboptions as $z => $data_sub){
					$item['shipping_cnt']++;
					$real_stock = $this->CI->goodsmodel -> get_goods_suboption_stock(
							$data_sub['goods_seq'],
							$title,
							$suboption
					);
					$rstock = $this->CI->ordermodel -> get_suboption_reservation(
							$this->CI->cfg_order['ableStockStep'],
							$item['goods_seq'],
							$data_sub['title'],
							$data_sub['suboption']
					);

					$stock = (int) $real_stock - (int) $rstock;
					$data_sub['real_stock'] = (int) $real_stock;
					$data_sub['stock'] = (int) $stock;

					$data_sub['out_supply_price'] = $data_sub['supply_price']*$data_sub['ea'];
					$data_sub['out_consumer_price'] = $data_sub['consumer_price']*$data_sub['ea'];
					$data_sub['out_price'] = $data_sub['price']*$data_sub['ea'];

					$data_sub['out_member_sale']	= $data_sub['member_sale']*$data_sub['ea'];
					$data_sub['out_tot_sale']		= $data_sub['out_member_sale'];

					$data_sub['out_reserve'] = $data_sub['reserve']*$data_sub['ea'];
					$data_sub['out_point'] = $data_sub['point']*$data_sub['ea'];

					$data_sub['mstep']	= $this->CI->arr_step[$data_sub['step']];
					$data_sub['step_complete'] = $data_sub['step45']+$data_sub['step55']+$data_sub['step65']+$data_sub['step75'];

					$suboptions[$z] = $data_sub;

					$tot['ea'] += $data_sub['ea'];
					$tot['supply_price'] 	+= $data_sub['out_supply_price'];
					$tot['consumer_price'] 	+= $data_sub['out_consumer_price'];
					$tot['price'] 			+= $data_sub['out_price'];

					$tot['member_sale'] += $data_sub['out_member_sale'];

					$tot['reserve'] += $data_sub['out_reserve'];
					$tot['point'] += $data_sub['out_point'];

					$tot['real_stock'] 		+= $real_stock;
					$tot['stock'] 			+= $stock;

					$return_item = $this->CI->returnmodel->get_return_item_ea($data_sub['item_seq'],$data_sub['item_suboption_seq']);
					$able_return_ea += ($data_sub['step55']+$data_sub['step65']+$data_sub['step75']) - (int) $return_item['ea'];
				}

				$data['suboptions']	= $suboptions;
				$options[$k] = $data;

				$item['totaloptitems']		+= count($suboptions) + 1;
			}
			$item['options']			= $options;
			$items[$key] 				= $item;
			$tot['goods_shipping_cost']	+= $item['goods_shipping_cost'];

		}

		//총 할인 금액
		$tot['total_sale'] = $tot['event_sale']
							+ $tot['multi_sale']
							+ $tot['member_sale']
							+ $tot['coupon_sale']
							+ $tot['fblike_sale']
							+ $tot['mobile_sale']
							+ $tot['promotion_code_sale']
							+ $tot['referer_sale']
							+ $shipping_tot['coupon_sale']
							+ $shipping_tot['code_sale']
							+ $orders['enuri'];

		/* 주문상품을 배송그룹별로 분할 */
		$shipping_group_items=array();
		foreach($items as $item){

			$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost_sum']+= $item['goods_shipping_cost'];
			$shipping_group_items[$item['shipping_seq']]['rowspan'] += count($item['options']) + count($item['suboptions']);
			$shipping_group_items[$item['shipping_seq']]['items'][] = $item;
			$shipping_group_items[$item['shipping_seq']]['totalitems'] += $item['totaloptitems'];
		}

		$this->CI->load->helper('shipping');
		$arr_shipping_method = get_shipping_method('all');
		foreach($shipping_group_items as $shipping_seq=>$row){
			$query = $this->CI->db->query("select a.*, b.provider_name
			from fm_order_shipping a
			inner join fm_provider b on a.provider_seq = b.provider_seq
			where a.shipping_seq=?",$shipping_seq);
			$shipping = $query->row_array();
			if	(!serviceLimit('H_AD'))	unset($shipping['provider_name']);
			$shipping['shipping_method_name'] = $arr_shipping_method[$shipping['shipping_method']];
			$shipping['shipping_set_code'] = $shipping_info_arr[$shipping['shipping_group']]['shipping_set_code'];
			$shipping['shipping_set_name'] = $shipping_info_arr[$shipping['shipping_group']]['shipping_set_name'];
			$shipping['shipping_store_name'] = $shipping_info_arr[$shipping['shipping_group']]['shipping_store_name'];
			$shipping['shipping_exists'] = $shipping_info_arr[$shipping['shipping_group']]['shipping_exists'];

			// 배송사명이 본사일 경우
			if($shipping['provider_name']=="본사"){$shipping['provider_name'] = getAlert("sy009");} // "본사";
			//
			// 배송정보 :: 2016-08-10 lwh
			$shipping_group_items[$shipping_seq]['shipping'] = $shipping;

			//모바일 예약 표시(희망배송일, 예약배송일 표시)
			if($shipping['shipping_hop_date'] != '' || $shipping['reserve_sdate'] != '') {
				$this->CI->template->assign('hop_reserve_date','예약'); //view페이지에서는 언어별로 수동 입력
			}

			// 구스킨 배송비 정보 :: 2016-08-10 lwh
			if($shipping['shipping_method']=='delivery'){
				$shipping_tot['basic_cost']				+= $shipping['delivery_cost'];
				$shipping_tot['add_shipping_cost']		+= $shipping['add_delivery_cost'];
				$shipping_tot['shipping_cost']			+= $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
			}

			if($shipping['shipping_method']=='each_delivery'){
				$shipping_tot['goods_cost']				+= $shipping['delivery_cost'];
				$shipping_tot['add_shipping_cost']		+= $shipping['add_delivery_cost'];
				$shipping_tot['goods_shipping_cost']	+= $shipping['shipping_cost'] + $shipping['add_delivery_cost'];
			}

			// 배송방법: 매장수령 시
			if($shipping['shipping_method'] == 'direct_store') {
				$is_direct_store = true;
			}

			// 신스킨용 배송비 정보 :: 2016-08-10 lwh
			if($shipping['shipping_type'] == 'prepay'){
				$shipping_tot['std_cost'] += $shipping['delivery_cost'];
				$shipping_tot['add_cost'] += $shipping['add_delivery_cost'];
				$shipping_tot['hop_cost'] += $shipping['hop_delivery_cost'];
			}

			$shipping_tot['coupon_sale']		+= $shipping['shipping_coupon_sale'];
			$shipping_tot['code_sale']			+= $shipping['shipping_promotion_code_sale'];
		}

		// 구스킨용 배송비계산 :: 2016-08-10 lwh
		$shipping_tot['total_shipping_cost']		= $shipping_tot['basic_cost'] + $shipping_tot['goods_cost'] + $shipping_tot['add_shipping_cost'];

		// 신스킨용 배송비계산 :: 2016-08-10 lwh
		$shipping_tot['tot_origin_shipping_cost']	= $shipping_tot['std_cost'] + $shipping_tot['add_cost'] + $shipping_tot['hop_cost'];		

		return [
			'is_goods' => $is_goods,
			'is_coupon' => $is_coupon,
			'items_tot' => $tot,
			'items' => $items,
			'is_direct_store' => $is_direct_store,
			'shipping_tot' => $shipping_tot,
			'able_return_ea' => $able_return_ea,
			'shipping_group_items' => $shipping_group_items,
		];
	}

	/**
	 * 주소 변경
	 * @param orders:fm_order, data:수정내역(post)
	 */
	public function change_order_recipient($orders, $data) {
		$international = $orders['international'];

		foreach($data as $k => $row) if($orders[$k]!=$data) $change = 1;
		if(!$change) {
			return;
		}

		$set_params['recipient_phone'] = is_array($data['recipient_phone']) ? implode('-',$data['recipient_phone']) : $data['recipient_phone'];
		$set_params['recipient_cellphone'] = is_array($data['recipient_cellphone']) ? implode('-',$data['recipient_cellphone']) : $data['recipient_cellphone'];
		$set_params['recipient_user_name'] = $data['recipient_user_name'];
		$set_params['memo'] = $data['memo'];

		if($international == 'international'){
			// 해외
			$set_params['nation_key'] = $this->CI->shippingmodel->get_gl_nation_key(preg_replace('/^[가-힣 ]+?\(([a-z.]+)\)/Ui', '$1', $data['international_country']));
			$set_params['international_address'] 		= $data['international_address'];
			$set_params['international_town_city'] 	= $data['international_town_city'];
			$set_params['international_county'] 		= $data['international_county'];
			$set_params['international_postcode'] 	= $data['international_postcode'];
			$set_params['international_country'] 		= $data['international_country'];	
		} else{
			// 국내
			if($data['recipient_new_zipcode']){
				$set_params['recipient_zipcode'] 				= $data['recipient_new_zipcode'];
			}else{
				$set_params['recipient_zipcode'] 				= $data['recipient_zipcode'][0].$data['recipient_zipcode'][1];
			}
			$set_params['recipient_address_type'] 	= (($data['recipient_address_type']))?$data['recipient_address_type']:"zibun";
			$set_params['recipient_address'] 			= $data['recipient_address'];
			$set_params['recipient_address_street'] 	= $data['recipient_address_street'];
			$set_params['recipient_address_detail'] 	= $data['recipient_address_detail'];
		}

		$data['recipient_email'] && ($set_params['recipient_email'] = $data['recipient_email']);
		$data['clearance_unique_personal_code'] && ($set_params['clearance_unique_personal_code'] = $data['clearance_unique_personal_code']);


		$where_params	= array(
			'order_seq'	=> $orders['order_seq'],
		);
		$result = $this->CI->ordermodel->set_order($set_params,$where_params);
		if($result) {
			$log = "배송지 정보 변경";
			$actor = $data['actor'] == 'present' ? '수령자' : '구매자';
			$this->CI->ordermodel->set_log($orders['order_seq'],'process',$actor,$log,serialize($data));
		}
		return $result;
	}

	/**
	 * 주소 변경 시 validation
	 */
	public function validation_change_order_recipient($orders) {
		$this->CI->load->library('shipping');
		
		$order_seq = $orders['order_seq'];
		$international = $orders['international'];
		$post = $this->CI->input->post();

		// 주문 당시 배송정책 존재여부
		$order_shipping_row = $this->CI->ordermodel->get_shipping($order_seq);
		list($shipping_group_seq, $shipping_set_seq, $shipping_set_code) = explode('_', $order_shipping_row[0]['shipping_group']);

		if(!$this->CI->shippingmodel->shipping_group_exists($shipping_group_seq)) { // 배송그룹 존재 유무
			if ($mode == 'json') {
				echo json_encode(['state' => '999', 'error_message' => getAlert('mo147')]);
			} else {
				openDialogAlert(getAlert('mo147'), 400, 160, 'parent', '');
			}
			exit;
		}

		if (!$this->CI->shippingmodel->shipping_group_set_exists($shipping_group_seq, $shipping_set_seq, $shipping_set_code)) { // 배송그룹셋(이하 배송방법)의 존재 유무
			if ($mode == 'json') {
				echo json_encode(['state' => '999', 'error_message' => getAlert('mo148')]);
			} else {
				openDialogAlert(getAlert('mo148'), 400, 160, 'parent', '');
			}
			exit;
		}

		// 배송지변경시 국내->해외, 해외->국내 변경 처리불가 메세지 표출
		if ($international != $post['international']) {
			if ($mode == 'json') {
				echo json_encode(['state' => '999', 'error_message' => getAlert('mo146')]);
			} else {
				openDialogAlert(getAlert('mo146'), 400, 140, 'parent', '');
			}
			exit;
		}

		if( !in_array($orders['step'],$this->CI->ordermodel->able_step_action['shipping_region']) ){
			//$this->CI->arr_step[$orders['step']]."에서는 배송정보 변경을 하실 수 없습니다."
			if($mode == 'json') {
				echo json_encode(array("state"=>"999", "error_message" => getAlert('mo008',$this->CI->arr_step[$orders['step']])));
			} else {
				openDialogAlert(getAlert('mo008',$this->CI->arr_step[$orders['step']]),400,140,'parent',"");
			}
			exit;
		}

		//받는이
		$this->CI->validation->set_rules('recipient_user_name',getAlert('mo009'),'trim|required|xss_clean');
		//휴대폰
		if(is_array($post['recipient_cellphone'])) {
			$this->CI->validation->set_rules('recipient_cellphone[]',getAlert('mo011'),'trim|numeric|required|xss_clean');
		} else {
			$this->CI->validation->set_rules('recipient_cellphone',getAlert('mo011'),'trim|required|xss_clean');
		}
		//요청사항
		$this->CI->validation->set_rules('memo',getAlert('mo012'),'trim|xss_clean');

		if($shipping_set_code != 'coupon') { // 쿠폰상품 제외한 상품만 주소 체크
			if($international == 'domestic'){
				if($post['recipient_new_zipcode']){
					//우편번호
					$this->CI->validation->set_rules('recipient_new_zipcode',getAlert('mo017'),'trim|required|xss_clean');
				}else{
					$recipient_zipcode1			= $post['recipient_zipcode'][0];
					$recipient_zipcode2			= $post['recipient_zipcode'][1];
					$recipient_address_street	= $post['recipient_address_street'];
					//구스킨오류로 첫번째 5자리와 두번째값이 없으면 도로명주소로 간주하여 체크하지 않습니다. @2016-10-21
					if( $recipient_zipcode1 && $recipient_zipcode2 && strlen($recipient_zipcode1) != 5 ) {
						//우편번호
						if(is_array($post['recipient_zipcode'])) {
							$this->CI->validation->set_rules('recipient_zipcode[]',getAlert('mo017'),'trim|required|xss_clean');
						} else {
							$this->CI->validation->set_rules('recipient_zipcode',getAlert('mo017'),'trim|required|xss_clean');
						}
					}
				}
				//주소
				$this->CI->validation->set_rules('recipient_address',getAlert('mo013'),'trim|required|xss_clean');
				//주소
				$this->CI->validation->set_rules('recipient_address_detail',getAlert('mo013'),'trim|required|xss_clean');
			}

			if($international == 'international'){
				//주소
				$this->CI->validation->set_rules('international_address',getAlert('mo013'),'trim|required|xss_clean');
				//시도
				$this->CI->validation->set_rules('international_town_city',getAlert('mo015'),'trim|required|xss_clean');
				//주
				$this->CI->validation->set_rules('international_county',getAlert('mo016'),'trim|required|xss_clean');
				//우편번호
				$this->CI->validation->set_rules('international_postcode',getAlert('mo017'),'trim|required|xss_clean');
				//국가
				$this->CI->validation->set_rules('international_country',getAlert('mo018'),'trim|required|xss_clean');
			}

			// 국가, 주소 배송가능여부 체크

			switch ($international) {
				case 'domestic': 
					$ship_possible = $this->CI->shipping->check_shipping_possible($order_seq, $international, $orders['international_country'], $post['recipient_address_street'], $post['recipient_address']);
				break;
				case 'international': 
					$ship_possible = $this->CI->shipping->check_shipping_possible($order_seq, $international, $post['international_country'], '', '');
				break;
			}

			if($ship_possible != 'Y') {
				if($mode == 'json') {
					echo json_encode(array("state"=>"999", "error_message" => getAlert('mp222')));
				} else {
					openDialogAlert(getAlert('mp222'),400,140,'parent'); 
				}
				exit;
			}
		}

		// 개인통관고유부호 체크
		if($post['clearance_unique_personal_code']){
			$this->CI->validation->set_rules('clearance_unique_personal_code', getAlert('os067'),'trim|required|alpha_numeric|min_length[13]|xss_clean');
		}

		if($this->CI->validation->exec()===false){
			$err = $this->CI->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();";

			if($mode == 'json') {
				echo json_encode(array("state"=>"999", "error_message" => $err['value']));
			} else {
				openDialogAlert($err['value'],400,140,'parent',$callback);
			}
			exit;
		}
	}

	// 주문 상태에 따라 되돌리기 버튼 title 지정
	public function get_order_revert($orders=array())
	{

		$return  = array();
		if($orders['step'] == 25 && $orders['payment'] == 'bank' && !$orders['linkage_id'] )
		{
			$return['title'] 			= "주문접수";
			$return['description'] 		= "취소, 반품, 환불이 없는 무통장 주문건을 주문접수(미입금)로 되돌릴 수 있습니다.";
		}
		else if($orders['step'] == '35')
		{
			$return['title'] 			= "결제확인";
			$return['description'] 		= "상품준비된 주문을 결제확인으로 되돌릴 수 있습니다.";
		} 
		else if($orders['step'] == '95' && !$orders['linkage_id'])
		{
			$return['title'] 			= "주문접수";
			$return['description'] 		= "주문무효건을 다시 주문접수로 되돌릴 수 있습니다.";
		}

		return $return;
	}

}
?>