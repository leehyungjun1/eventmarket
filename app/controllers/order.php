<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH ."controllers/base/front_base".EXT);
class order extends front_base {

	public function __construct(){
		parent::__construct();
		$this->load->helper('order');
		$this->load->helper('shipping');
		$this->load->library('snssocial');
		$this->load->library('sale');

		$this->load->model('promotionmodel');
		$this->cfg_order = config_load('order');
		$this->load->helper('goods');
	}

	public function index()
	{
		redirect("/order/cart");
	}

	//장바구니 개선 (상품가격 재계산)
	public function cart_price(){

	    // 상품가격 재계산 에서는 오류 alert 노출하지 않음 (output buffer 시작)
		ob_start();

		$this->load->model('cartmodel');
		$this->load->model('goodsmodel');
		$this->load->model('shippingmodel');
		$this->load->model('Providershipping');
		$this->load->helper('order');
		// 19mark 이미지
		$this->load->library('goodsList');

		$params = $this->input->post();
		$arrayGoodsSeq = explode('||',$params['goodsSeq']);
		$arrayCartSeqs = explode('||',$params['checkCartSeqs']);

		// 선택 상품을 장바구니에 합쳐줌 - 마지막에 들어온 cart 정보로 같은 상품군을 묶는다.
		$this->cartmodel->merge_for_choice();

		$applypage			= 'cart';
		$total_ea			= 0;
		$goodscancellation	= false;
		$is_coupon			= false;
		$is_goods			= false;
		$expectGoodsChk		= false;
		$cart				= $this->cartmodel->catalog();
		$cartList			= array();
		$i					= 0;

		//post로 요청되어진 상품의 데이터만 가지고 배열을 재구성
		foreach($cart['list'] as $listTmp){
			if(in_array($listTmp['goods_seq'],$arrayGoodsSeq) && in_array($listTmp['cart_option_seq'],$arrayCartSeqs)){
				$cartList[$i] = $listTmp;
				$i++;
			}
		}

		foreach($cart['data_goods'] as $key => $val){
			if(in_array($key,$arrayGoodsSeq)){
				$cartDataGoods[$key] = $cart['data_goods'][$key];
				$i++;
			}
		}

		unset($cart['list']);
		unset($cart['data_goods']);
		$cart['list'] = $cartList;
		$cart['data_goods'] = $cartDataGoods;
		//post로 요청되어진 상품의 데이터만 가지고 배열을 재구성


		$cfg['order']		= ($this->cfg_order) ? $this->cfg_order : config_load('order');
		$cfg_reserve		= ($this->reserves) ? $this->reserves : config_load('reserve');

		if($person_seq == ""){
			$this->template->assign('firstmallcartid',session_id());
		}

		//----> sale library 적용
		$param['cal_type']				= 'list';
		$param['total_price']			= $cart['total'];
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		// 장바구니에서 구한 데이터 초기화
		$cart['total']						= 0;
		$cart['total_ea']					= 0;
		$cart['total_sale']					= 0;
		$cart['total_price']				= 0;
		$cart['total_reserve']				= 0;
		$cart['total_point']				= 0;

		# 비교통화 계산 함수 include
		$this->template->include_('showCompareCurrency');

		// 장바구니 목록
		foreach($cart['list'] as $key => $data){

			// 19mark 이미지
			$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($data);
			if ($markingAdultImg) {
				$data['image']	= $this->goodslist->adultImg;
			}

			// 비과세 상품일 경우 체크아웃 체크
			if($data['tax'] == 'exempt') $expectGoodsChk = true;

			if	($data['goods_kind'] == 'coupon')	$is_coupon	= true;
			else									$is_goods	= true;

			// 청약철회상품
			$goodscancellation	= false;
			if($data['cancel_type'] == 1)	$goodscancellation	= true;

			// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
			if	( $data['event']['event_goodsStatus'] === true ){
				// 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
				$err_msg	= getAlert('oc001');
				$err_msg	.= addslashes($data['goods_name']);
				alert($err_msg);

				$this->cartmodel->delete_option($data['cart_option_seq'],'');
				continue;
			}

			if	($data['goods_kind'] == 'coupon') {
				if	($data['cart_option_seq']){
					// 티켓상품 기간체크
					$chkcouponexpire	= check_coupon_date_option($data['goods_seq'], $data['option1'], $data['option2'], $data['option3'], $data['option4'], $data['option5']);
					if	( $chkcouponexpire['couponexpire'] === false ){
						// 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
						$err_msg = getAlert('oc036');
						$err_msg	.= addslashes($data['goods_name']);
						alert($err_msg);
						// 해당상품의 옵션제거
						$this->cartmodel->delete_option($data['cart_option_seq'],'');
						$check_ea++;
						continue;	// check_ea가 있는 경우 어차피 페이지 리로드함
					}
				}

				if	($data['cart_suboption_seq']){
					// 티켓상품 기간체크
					$chkcouponexpire	= check_coupon_date_suboption($data['goods_seq'], $data['suboption_title'], $data['suboption']);
					if	( $chkcouponexpire['couponexpire'] === false ){
						// 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
						$err_msg = getAlert('oc036');
						$err_msg	.= addslashes($data['goods_name']);
						alert($err_msg);
						// 해당상품의 옵션제거
						$this->cartmodel->delete_option($data['cart_option_seq'],'');
						continue;
					}
				}
			}

			// 옵션 노출/미노출 체크
			$view_chk	= check_view_option($data['goods_seq'], $data['option1'],
					$data['option2'], $data['option3'],
					$data['option4'], $data['option5']);

			if	($view_chk){
				//옵션미노출 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
				$err_msg	= getAlert('oc042');
				alert($err_msg);

				$this->cartmodel->delete_option($data['cart_option_seq'],'');
				continue;
			}

			// 재고 체크
			$chk		= check_stock_option($data['goods_seq'], $data['option1'],
					$data['option2'], $data['option3'],
					$data['option4'], $data['option5'],
					$data['ea'], $cfg['order'], 'view_stock' );

			if	( $chk['stock'] < 0 ){
				if(!$chk['sale_able_stock']){
					// 품절일 경우
					$this->cartmodel->delete_option($data['cart_option_seq'],'');
					$err_msg	= getAlert('oc017');
				}else{
					// 재고가 부족할 경우
					$set_params										= array();
					$where_params									= array();
					$set_params['ea']								= $chk['sale_able_stock'];
					$where_params['cart_option_seq']		= $data['cart_option_seq'];
					$this->cartmodel->modify('option', $set_params, $where_params);
					// 재고부족 이유로 아래의 상품은 구매수량이 조정되었습니다.%b다시 확인해 주세요.%b -
					$err_msg	= getAlert('oc037');
				}
				$err_msg	.= addslashes($data['goods_name']);
				alert($err_msg);
				$check_ea++;
				continue;
			}

			// 추가옵션 재고 체크
			$cart_suboptions	= $data['cart_suboptions'];
			if	($cart_suboptions){
				foreach($cart_suboptions as $k => $cart_suboption){

					// 추가옵션 노출/미노출 체크
					$view_chk	= check_view_suboption($data['goods_seq'],
							$cart_suboption['suboption_title'],$cart_suboption['suboption']);

					if	($view_chk){
						//옵션미노출 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
						$err_msg	= getAlert('oc042');
						alert($err_msg);

						$this->cartmodel->delete_option('',$cart_suboption['cart_suboption_seq']);
						continue;
					}

					// 재고 체크
					$chk	= check_stock_suboption($data['goods_seq'], $cart_suboption['suboption_title'], $cart_suboption['suboption'], $cart_suboption['ea'], $cfg['order'], 'view_stock' );
					if	( $chk['stock'] < 0 ){
						if(!$chk['sale_able_stock']){
							// 품절일 경우
							$this->cartmodel->delete_option('',$cart_suboption['cart_suboption_seq']);
							$err_msg	= getAlert('oc019');
						}else{
							// 재고가 부족할 경우
							$set_params										= array();
							$where_params									= array();
							$set_params['ea']								= $cart_suboption['ea'];
							$where_params['cart_suboption_seq']		= $cart_suboption['cart_suboption_seq'];
							$this->cartmodel->modify('suboption', $set_params, $where_params);
							// 재고부족 이유로 아래의 상품은 구매수량이 조정되었습니다.%b다시 확인해 주세요.%b -
							$err_msg	= getAlert('oc020');
						}
						$err_msg	.= addslashes($data['goods_name']);
						alert($err_msg);
						$check_ea++;
						$stock_sub_status	= true;
						continue;
					}
				}
				if	($stock_sub_status)	continue;
			}

			if($this->mobileMode) $compare_class= array("layClass"=>"wx140 mlminus100");
			else $compare_class = "";

			// 해당 상품의 전체 카테고리
			$category	= array();
			$tmp		= $this->goodsmodel->get_goods_category($data['goods_seq']);
			foreach($tmp as $row) $category[] = $row['category_code'];

			//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
			unset($param, $sales);
			$param['option_type']				= 'option';
			$param['consumer_price']			= $data['consumer_price'];
			$param['price']						= $data['org_price'];
			$param['sale_price']				= $data['price'];
			$param['ea']						= $data['ea'];
			$param['goods_ea']					= $cart['data_goods'][$data['goods_seq']]['ea'];
			$param['option_ea']					= $cart['data_goods'][$data['goods_seq']]['option_ea'];
			$param['category_code']				= $category;
			$param['goods_seq']					= $data['goods_seq'];
			$param['goods']						= $data;
			$this->sale->set_init($param);
			$sales								= $this->sale->calculate_sale_price($applypage);

			# 비교통화 노출
			$sales['result_price_compare']		= showCompareCurrency('',$sales['result_price'],'return',$compare_class);
			$opt_total_sale_price				= $sales['result_price'];

			$data['org_price']					= ($data['consumer_price']) ? $data['consumer_price'] : $data['org_price'];
			$data['sale_price']					= $sales['one_result_price'];			// calculate와 동일하게 변경 2017-11-17
			$data['sales']						= $sales;
			$data['tot_org_price']				= $data['org_price'] * $data['ea'];
			$data['tot_sale_price']				= $sales['total_sale_price'];
			$data['tot_result_price']			= $sales['result_price'];


			// sale_price가 없을 시 여기서 event까지 계산함
			if	(!$data['event'] && $this->sale->cfgs['event']){
				$data['event']					= $this->sale->cfgs['event'];
				$data['eventEnd']				= $sales['eventEnd'];
			}

			// 마일리지 / 포인트 ( 실결제 금액 기준이기에 여기서 계산 )
			// 상품의 마일리지 / 포인트 계산
			// 원단위 마일리지 구매갯수 만큼 계산 안되는 오류로 $sales['result_price'] => $sales['one_result_price'] 변경 2015-03-30
			$data['reserve']	= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'],
					$sales['one_result_price'], $cfg_reserve['default_reserve_percent'],
					$data['reserve_rate'], $data['reserve_unit'], 0) * $data['ea'];
			$data['point']		= $this->goodsmodel->get_point_with_policy($sales['one_result_price']) * $data['ea'];
			// 이벤트 마일리지 / 포인트 계산 ( 장바구니에서 혜택적용할인가를 받아서 쓸 경우만 )
			if	($param['sale_price']){
				$this->sale->cfgs['event']		= $data['event'];
				$data['reserve']				+= $this->sale->event_sale_reserve($sales['result_price']);
				$data['point']					+= $this->sale->event_sale_point($sales['result_price']);
			}
			$data['reserve']					= $data['reserve'] + $sales['tot_reserve'];
			$data['point']						= $data['point'] + $sales['tot_point'];

			// 총 합계
			$cart['total']						+= $data['price']*$data['ea'];
			$cart['total_ea']					+= $data['ea'];
			$cart['total_sale']					+= $sales['total_sale_price'];
			$cart['total_price']				+= $sales['result_price'];
			$cart['total_reserve']				+= $data['reserve'];
			$cart['total_point']				+= $data['point'];

			// 총 할인 목록 노출을 위한 배열 생성
			if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $sale_title){
				$data['tsales']['sale_list'][$sale_type]		= $sales['sale_list'][$sale_type];
				$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
				$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
			}
			$this->sale->reset_init();
			//<---- sale library 적용

			// 추가구성 옵션
			if	($data['cart_suboptions']){
				foreach($data['cart_suboptions'] as $k => $subdata){
					//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
					unset($param, $sales);
					$param['option_type']				= 'suboption';
					$param['sub_sale']					= $subdata['sub_sale'];
					$param['consumer_price']			= $subdata['consumer_price'];
					$param['price']						= $subdata['price'];
					$param['sale_price']				= $subdata['price'];
					$param['ea']						= $subdata['ea'];
					$param['goods_ea']					= $cart['data_goods'][$data['goods_seq']]['ea'];
					$param['category_code']				= $category;
					$param['goods_seq']					= $data['goods_seq'];
					$param['goods']						= $data;
					$this->sale->set_init($param);
					$sales								= $this->sale->calculate_sale_price($applypage);

					# 비교통화 노출
					$sales['result_price_compare']		= showCompareCurrency('',$sales['result_price'],'return',$compare_class);
					$opt_total_sale_price					+= $sales['result_price'];

					$subdata['sale_price']					= $sales['one_result_price'];
					$subdata['sales']					= $sales;
					$subdata['org_price']				= ($subdata['consumer_price']) ? $subdata['consumer_price'] : $subdata['price'];

					// 마일리지 / 포인트
					$subdata['reserve']					= $this->goodsmodel->get_reserve_with_policy($data['sub_reserve_policy'], $sales['one_result_price'], $cfg_reserve['default_reserve_percent'], $subdata['reserve_rate'], $subdata['reserve_unit'], $subdata['reserve']) * $subdata['ea'];
					$subdata['point']					= $this->goodsmodel->get_point_with_policy($sales['one_result_price']) * $subdata['ea'];
					$subdata['reserve']					= $subdata['reserve'] + $sales['tot_reserve'];
					$subdata['point']					= $subdata['point'] + $sales['tot_point'];

					$data['tot_org_price']				+= $subdata['org_price'] * $subdata['ea'];
					$data['tot_sale_price']				+= $sales['total_sale_price'];
					$data['tot_result_price']			+= $sales['result_price'];

					$cart['total']						+= $subdata['price']*$subdata['ea'];
					$cart['total_ea']					+= $subdata['ea'];
					$cart['total_sale']					+= $sales['total_sale_price'];
					$cart['total_price']				+= $sales['result_price'];
					$cart['total_reserve']				+= $subdata['reserve'];
					$cart['total_point']				+= $subdata['point'];
					if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $sale_title){
						$data['tsales']['sale_list'][$sale_type]		+= $sales['sale_list'][$sale_type];
						$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
						$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
					}

					$this->sale->reset_init();
					//<---- sale library 적용

					$data['cart_suboptions'][$k]	= $subdata;
				}
			}

			# 필수옵션 + 추가옵션 할인가에 대한 비교통화
			$data['tot_result_price_compare']		= showCompareCurrency('',$opt_total_sale_price,'return',$compare_class);

			$cart['list'][$key]		= $data;
			if	($data['shipping_policy'] == 'goods') {
				$data['shipping_policy']	.= '_'.$data['cart_seq'];
				$group_key				= $data['goods_type'].'|'.$data['shipping_policy'];
				$shipping_cart_list[$group_key][]				= $data;
				$shipping_cart_list[$group_key][0]['rowspan']	+= count($data['cart_suboptions']) + 1;
			}
		}

		// 상품별 주문배송방법 선택
		$this->displaymode = 'cart';
		$cart_calculate = $this->calculate('', $person_seq);
		$cart['shipping_price']				= $this->shipping_price;//@2016-08-01 ysm
		$cart['shipping_group_price']		= $this->shipping_group_cost;
		$cart['shipping_group_policy']		= $this->shipping_group_policy;
		$cart['shipping_company_cnt']		= $cart_calculate['shipping_company_cnt'];
		$cart['provider_shipping_policy']	= $cart_calculate['provider_shipping_policy'];
		$cart['provider_shipping_price']	= $cart_calculate['provider_shipping_price'];
		$cart['shop_shipping_policy']		= $cart_calculate['shop_shipping_policy'];
		$cart['total_price']	+= get_cutting_price(array_sum($cart['shipping_price']));	# 총 결제금액

		// 삭제된 장바구니 상품이 있을시 페이지 새로고침
		if	( $check_ea >0	 ){	pageRedirect('/order/cart');	}

		if	($_SESSION["refer_adress"] != "")	$cart_history	= $_SESSION["refer_adress"];
		else									$cart_history	= "../main/index";

		//좋아요 할인 혜택구분 $cfg['order']['fblike_ordertype'] 0 : 회원/비회원, 1 : 회원만
		$session_arr	= ( $this->session->userdata('user') ) ? $this->session->userdata('user') : $_SESSION['user'];
		// 설정값을 적용여부 값으로 변경
		$cfg['order']['fblike_ordertype']	= ( ($cfg['order']['fblike_ordertype'] == 1 && $session_arr['member_seq'] ) || ($cfg['order']['fblike_ordertype'] != 1) ) ? 1 : 0;

		$cart_promotioncode = $this->session->userdata('cart_promotioncode_'.session_id());

		// 20210408 (kjw) : 413 ~ 424 누락으로 인한 nation value 오류 수정
		$nation = $params['nation'];
		$nation == 'KOREA' ? 'KOREA' : $this->shippingmodel->get_gl_nation($nation); // 지정안된 경우 기본 국내 :: 2017-01-31 lwh
		if($params['nation']){
			$ship_ini['nation'] = $nation;
		}
		

		// ### NEW 배송 그룹 정보 추출 :: START ### -> shipping library 계산
		$this->load->library('shipping');
		$ini_info				= $this->shipping->set_ini($ship_ini);
		$shipping_group_list	= $this->shipping->get_shipping_groupping($cart['list']);

		// 상세 배송비 금액 추출
		$shipping_cost_detail	= $shipping_group_list['shipping_cost_detail'];

		// 전체 배송비 금액 추출
		$total_shipping_price	= $shipping_group_list['total_shipping_price'];

		// 최종 결제 금액 재계산
		$cart['total_price'] = $cart['total_price'] + $total_shipping_price;

		# 총 결제금액 비교통화 노출
		$cart['total_price_compare'] = showCompareCurrency('',$cart['total_price'],'return',array('layClass'=>'fx14 wx140 black mlminus100'));

		// 네이버페이 장바구니 버튼 노출여부
		$returnArray['npay_display'] = '';
		if($cart['list'] && $cart['total_price']){
			// 네이버 체크아웃
			$navercheckout = config_load('navercheckout');
			$marketing_admin = $this->session->userdata('marketing');

			if(
					$navercheckout['use'] == 'y'											// "사용모드"일때
				||	($navercheckout['use'] == 'test' && $this->managerInfo)					// "테스트모드"이고 관리자아이디일때
				||	($navercheckout['use'] == 'test' && $marketing_admin == 'nbp' ) // "테스트모드"이고 회원아이디 gabia일때
			){

				// 예외카테고리 체크, 예외상품 체크
				$expectCategoryChk	= false;
				$expectGoodsChk		= false;
				$shipping_policys	= array();
				foreach($cart['list'] as $key => $data){
					$categorys = $this->goodsmodel->get_goods_category($data['goods_seq']);
					foreach($navercheckout['except_category_code'] as $v1){
						foreach($categorys as $v2){
							if($v1['category_code']==$v2['category_code'] || preg_match("/^".$v1['category_code']."/",$v2['category_code'])){
								$expectCategoryChk = true;
							}
						}
					}

					foreach($navercheckout['except_goods'] as $v1){
						if($v1['goods_seq']==$data['goods_seq']){
							$expectGoodsChk = true;
						}
					}

					// 네이버체크아웃 착불배송비 사용정보
					$tmp_shipping = $this->Providershipping->get_provider_shipping($data['provider_seq']);
					$able_shipping_method = array_keys($tmp_shipping['shipping_method']);
					if(in_array('postpaid',$able_shipping_method)){
						$navercheckout_postpaid = true;
					}
				}

				if( !($this->fammerceMode || $this->storefammerceMode) ){
					if($expectGoodsChk || $expectCategoryChk) {
						$returnArray['npay_display'] = 'hide';
					}

				}
			}

		}

		$returnArray['cart'] = $cart;
		$returnArray['shippingGroupList'] = $shipping_group_list;
		$returnArray['shippingCostDetail'] = $shipping_cost_detail;
		$returnArray['totalSaleList'] = $cart['total_sale_list'];

		// 상품가격 재계산 에서는 오류 alert 노출하지 않음 (output buffer 종료)
		ob_end_clean();
		echo json_encode($returnArray);
	}

	public function cart()
	{
		$this->load->model('cartmodel');
		$this->load->model('goodsmodel');
		$this->load->model('shippingmodel');
		$this->load->model('Providershipping');
		$this->load->helper('order');
		// 19mark 이미지
		$this->load->library('goodsList');

		// 장바구니에서 선물하기 주문 초기화
		$this->session->unset_userdata('order_label');

		// 선택 상품을 장바구니에 합쳐줌 - 마지막에 들어온 cart 정보로 같은 상품군을 묶는다.
		$this->cartmodel->merge_for_choice();

		$applypage			= 'cart';
		$total_ea			= 0;
		$goodscancellation	= false;
		$is_coupon			= false;
		$is_goods			= false;
		$expectGoodsChk		= false;
		$cart				= $this->cartmodel->catalog();
		$cfg['order']		= ($this->cfg_order) ? $this->cfg_order : config_load('order');
		$cfg_reserve		= ($this->reserves) ? $this->reserves : config_load('reserve');
		$this->template->assign('cfg_reserve',$cfg_reserve);

		if($person_seq == ""){
			$this->template->assign('firstmallcartid',session_id());
		}

		//----> sale library 적용
		$param['cal_type']				= 'list';
		$param['total_price']			= $cart['total'];
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		// 장바구니에서 구한 데이터 초기화
		$cart['total']						= 0;
		$cart['total_ea']					= 0;
		$cart['total_sale']					= 0;
		$cart['total_price']				= 0;
		$cart['total_reserve']				= 0;
		$cart['total_point']				= 0;

		# 비교통화 계산 함수 include
		$this->template->include_('showCompareCurrency');

		// 기본 언어 설정에 따라 기본 배송 국가 지정
		$nationPost = $this->input->post('nation');
		if ( ! $nationPost && $this->config_system['default_nation']) {
			$_POST['nation'] = $this->config_system['default_nation'];
		}

		// 장바구니 목록
		foreach($cart['list'] as $key => $data){

			// 19mark 이미지
			$markingAdultImg = $this->goodslist->checkingMarkingAdultImg($data);
			if ($markingAdultImg) {
				$data['image']	= $this->goodslist->adultImg;
			}

			// 비과세 상품일 경우 체크아웃 체크
			if($data['tax'] == 'exempt') $expectGoodsChk = true;

			if	($data['goods_kind'] == 'coupon')	$is_coupon	= true;
			else									$is_goods	= true;

			// 청약철회상품
			$goodscancellation	= false;
			if($data['cancel_type'] == 1)	$goodscancellation	= true;

			// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
			if	( $data['event']['event_goodsStatus'] === true ){
				// 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
				$err_msg	= getAlert('oc001');
				$err_msg	.= addslashes($data['goods_name']);
				$this->cart_alerts[] = $err_msg;

				$this->cartmodel->delete_option($data['cart_option_seq'],'');
				continue;
			}

			if	($data['goods_kind'] == 'coupon') {
				if	($data['cart_option_seq']){
					// 티켓상품 기간체크
					$chkcouponexpire	= check_coupon_date_option($data['goods_seq'], $data['option1'], $data['option2'], $data['option3'], $data['option4'], $data['option5']);
					if	( $chkcouponexpire['couponexpire'] === false ){
						// 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
						$err_msg = getAlert('oc036');
						$err_msg	.= addslashes($data['goods_name']);
						$this->cart_alerts[] = $err_msg;
						// 해당상품의 옵션제거
						$this->cartmodel->delete_option($data['cart_option_seq'],'');
						$check_ea++;
						continue;	// check_ea가 있는 경우 어차피 페이지 리로드함
					}
				}

				if	($data['cart_suboption_seq']){
					// 티켓상품 기간체크
					$chkcouponexpire	= check_coupon_date_suboption($data['goods_seq'], $data['suboption_title'], $data['suboption']);
					if	( $chkcouponexpire['couponexpire'] === false ){
						// 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
						$err_msg = getAlert('oc036');
						$err_msg	.= addslashes($data['goods_name']);
						$this->cart_alerts[] = $err_msg;
						// 해당상품의 옵션제거
						$this->cartmodel->delete_option($data['cart_option_seq'],'');
						continue;
					}
				}
			}

			// 옵션 노출/미노출 체크
			$view_chk	= check_view_option($data['goods_seq'], $data['option1'],
											$data['option2'], $data['option3'],
											$data['option4'], $data['option5']);

			if	($view_chk){
				//옵션미노출 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
				$err_msg	= getAlert('oc042');
				$this->cart_alerts[] = $err_msg;

				$this->cartmodel->delete_option($data['cart_option_seq'],'');
				continue;
			}

			// 재고 체크
			$chk		= check_stock_option($data['goods_seq'], $data['option1'],
											$data['option2'], $data['option3'],
											$data['option4'], $data['option5'],
											$data['ea'], $cfg['order'], 'view_stock' );

			if	( $chk['stock'] < 0 ){
				if(!$chk['sale_able_stock']){
					// 품절일 경우
					$this->cartmodel->delete_option($data['cart_option_seq'],'');
					$err_msg	= getAlert('oc017');
				}else{
					// 재고가 부족할 경우
					$set_params										= array();
					$where_params									= array();
					$set_params['ea']								= $chk['sale_able_stock'];
					$where_params['cart_option_seq']		= $data['cart_option_seq'];
					$this->cartmodel->modify('option', $set_params, $where_params);
					// 재고부족 이유로 아래의 상품은 구매수량이 조정되었습니다.%b다시 확인해 주세요.%b -
					$err_msg	= getAlert('oc037');
				}
				$err_msg	.= addslashes($data['goods_name']);
				$this->cart_alerts[] = $err_msg;
				$check_ea++;
				continue;
			}

			// 추가옵션 재고 체크
			$cart_suboptions	= $data['cart_suboptions'];
			if	($cart_suboptions){
				foreach($cart_suboptions as $k => $cart_suboption){

					// 추가옵션 노출/미노출 체크
					$view_chk	= check_view_suboption($data['goods_seq'],
													   $cart_suboption['suboption_title'],$cart_suboption['suboption']);

					if	($view_chk){
						//옵션미노출 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
						$err_msg	= getAlert('oc042');
						$this->cart_alerts[] = $err_msg;

						$this->cartmodel->delete_option('',$cart_suboption['cart_suboption_seq']);
						continue;
					}

					// 재고 체크
					$chk	= check_stock_suboption($data['goods_seq'], $cart_suboption['suboption_title'], $cart_suboption['suboption'], $cart_suboption['ea'], $cfg['order'], 'view_stock' );
					if	( $chk['stock'] < 0 ){
						if(!$chk['sale_able_stock']){
							// 품절일 경우
							$this->cartmodel->delete_option('',$cart_suboption['cart_suboption_seq']);
							$err_msg	= getAlert('oc019');
						}else{
							// 재고가 부족할 경우
							$set_params										= array();
							$where_params									= array();
							$set_params['ea']								= $chk['sale_able_stock'];
							$where_params['cart_suboption_seq']		= $cart_suboption['cart_suboption_seq'];
							$this->cartmodel->modify('suboption', $set_params, $where_params);
							// 재고부족 이유로 아래의 상품은 구매수량이 조정되었습니다.%b다시 확인해 주세요.%b -
							$err_msg	= getAlert('oc020');
						}
						$err_msg	.= addslashes($data['goods_name']);
						$this->cart_alerts[] = $err_msg;
						$check_ea++;
						$stock_sub_status	= true;
						continue;
					}
				}
				if	($stock_sub_status)	continue;
			}

			if($this->mobileMode) $compare_class= array("layClass"=>"wx140 mlminus100");
			else $compare_class = "";

			// 해당 상품의 전체 카테고리
			$category	= array();
			$tmp		= $this->goodsmodel->get_goods_category($data['goods_seq']);
			foreach($tmp as $row) $category[] = $row['category_code'];

			//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
			unset($param, $sales);
			$param['option_type']				= 'option';
			$param['consumer_price']			= $data['consumer_price'];
			$param['price']						= $data['org_price'];
			$param['sale_price']				= $data['price'];
			$param['ea']						= $data['ea'];
			$param['goods_ea']					= $cart['data_goods'][$data['goods_seq']]['ea'];
			$param['option_ea']					= $cart['data_goods'][$data['goods_seq']]['option_ea'];
			$param['category_code']				= $category;
			$param['goods_seq']					= $data['goods_seq'];
			$param['goods']						= $data;
			$this->sale->set_init($param);
			$sales								= $this->sale->calculate_sale_price($applypage);

			# 비교통화 노출
			$sales['result_price_compare']		= showCompareCurrency('',$sales['result_price'],'return',$compare_class);
			$opt_total_sale_price				= $sales['result_price'];

			$data['org_price']					= ($data['consumer_price']) ? $data['consumer_price'] : $data['org_price'];
			$data['sale_price']					= $sales['one_result_price'];			// calculate와 동일하게 변경 2017-11-17
			$data['sales']						= $sales;
			$data['tot_org_price']				= $data['org_price'] * $data['ea'];
			$data['tot_sale_price']				= $sales['total_sale_price'];
			$data['tot_result_price']			= $sales['result_price'];


			// sale_price가 없을 시 여기서 event까지 계산함
			if	(!$data['event'] && $this->sale->cfgs['event']){
				$data['event']					= $this->sale->cfgs['event'];
				$data['eventEnd']				= $sales['eventEnd'];
			}

			// 마일리지 / 포인트 ( 실결제 금액 기준이기에 여기서 계산 )
			// 상품의 마일리지 / 포인트 계산
			// 원단위 마일리지 구매갯수 만큼 계산 안되는 오류로 $sales['result_price'] => $sales['one_result_price'] 변경 2015-03-30
			$data['reserve']	= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'],
									$sales['one_result_price'], $cfg_reserve['default_reserve_percent'],
									$data['reserve_rate'], $data['reserve_unit'], 0) * $data['ea'];
			$data['point']		= $this->goodsmodel->get_point_with_policy($sales['one_result_price']) * $data['ea'];
			// 이벤트 마일리지 / 포인트 계산 ( 장바구니에서 혜택적용할인가를 받아서 쓸 경우만 )
			if	($param['sale_price']){
				$this->sale->cfgs['event']		= $data['event'];
				$data['reserve']				+= $this->sale->event_sale_reserve($sales['result_price']);
				$data['point']					+= $this->sale->event_sale_point($sales['result_price']);
			}
			$data['reserve']					= $data['reserve'] + $sales['tot_reserve'];
			$data['point']						= $data['point'] + $sales['tot_point'];

			// 총 합계
			$cart['total']						+= $data['price']*$data['ea'];
			$cart['total_ea']					+= $data['ea'];
			$cart['total_sale']					+= $sales['total_sale_price'];
			$cart['total_price']				+= $sales['result_price'];
			$cart['total_reserve']				+= $data['reserve'];
			$cart['total_point']				+= $data['point'];

			// 총 할인 목록 노출을 위한 배열 생성
			if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $sale_title){
				$data['tsales']['sale_list'][$sale_type]		= $sales['sale_list'][$sale_type];
				$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
				$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
			}
			$this->sale->reset_init();
			//<---- sale library 적용

			// 추가구성 옵션
			if	($data['cart_suboptions']){
				foreach($data['cart_suboptions'] as $k => $subdata){
					//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
					unset($param, $sales);
					$param['option_type']				= 'suboption';
					$param['sub_sale']					= $subdata['sub_sale'];
					$param['consumer_price']			= $subdata['consumer_price'];
					$param['price']						= $subdata['price'];
					$param['sale_price']				= $subdata['price'];
					$param['ea']						= $subdata['ea'];
					$param['goods_ea']					= $cart['data_goods'][$data['goods_seq']]['ea'];
					$param['category_code']				= $category;
					$param['goods_seq']					= $data['goods_seq'];
					$param['goods']						= $data;
					$this->sale->set_init($param);
					$sales								= $this->sale->calculate_sale_price($applypage);

					# 비교통화 노출
					$sales['result_price_compare']		= showCompareCurrency('',$sales['result_price'],'return',$compare_class);
					$opt_total_sale_price					+= $sales['result_price'];

					$subdata['sale_price']					= $sales['one_result_price'];
					$subdata['sales']					= $sales;
					$subdata['org_price']				= ($subdata['consumer_price']) ? $subdata['consumer_price'] : $subdata['price'];

					// 마일리지 / 포인트
					$subdata['reserve']					= $this->goodsmodel->get_reserve_with_policy($data['sub_reserve_policy'], $sales['one_result_price'], $cfg_reserve['default_reserve_percent'], $subdata['reserve_rate'], $subdata['reserve_unit'], $subdata['reserve']) * $subdata['ea'];
					$subdata['point']					= $this->goodsmodel->get_point_with_policy($sales['one_result_price']) * $subdata['ea'];
					$subdata['reserve']					= $subdata['reserve'] + $sales['tot_reserve'];
					$subdata['point']					= $subdata['point'] + $sales['tot_point'];

					$data['tot_org_price']				+= $subdata['org_price'] * $subdata['ea'];
					$data['tot_sale_price']				+= $sales['total_sale_price'];
					$data['tot_result_price']			+= $sales['result_price'];

					$cart['total']						+= $subdata['price']*$subdata['ea'];
					$cart['total_ea']					+= $subdata['ea'];
					$cart['total_sale']					+= $sales['total_sale_price'];
					$cart['total_price']				+= $sales['result_price'];
					$cart['total_reserve']				+= $subdata['reserve'];
					$cart['total_point']				+= $subdata['point'];
					if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $sale_title){
						$data['tsales']['sale_list'][$sale_type]		+= $sales['sale_list'][$sale_type];
						$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
						$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
					}

					$this->sale->reset_init();
					//<---- sale library 적용

					$data['cart_suboptions'][$k]	= $subdata;
				}
			}

			# 필수옵션 + 추가옵션 할인가에 대한 비교통화
			$data['tot_result_price_compare']		= showCompareCurrency('',$opt_total_sale_price,'return',$compare_class);

			$cart['list'][$key]		= $data;
			if	($data['shipping_policy'] == 'goods')
				$data['shipping_policy']	.= '_'.$data['cart_seq'];
			$group_key				= $data['goods_type'].'|'.$data['shipping_policy'];
			$shipping_cart_list[$group_key][]				= $data;
			$shipping_cart_list[$group_key][0]['rowspan']	+= count($data['cart_suboptions']) + 1;
		}

		// 상품별 주문배송방법 선택
		$this->displaymode = 'cart';
		$cart_calculate = $this->calculate('', $person_seq);
		$cart['shipping_price']				= $this->shipping_price;//@2016-08-01 ysm
		$cart['shipping_group_price']		= $this->shipping_group_cost;
		$cart['shipping_group_policy']		= $this->shipping_group_policy;
		$cart['shipping_company_cnt']		= $cart_calculate['shipping_company_cnt'];
		$cart['provider_shipping_policy']	= $cart_calculate['provider_shipping_policy'];
		$cart['provider_shipping_price']	= $cart_calculate['provider_shipping_price'];
		$cart['shop_shipping_policy']		= $cart_calculate['shop_shipping_policy'];
		$cart['total_price']	+= get_cutting_price(array_sum($cart['shipping_price']));	# 총 결제금액

		// 삭제된 장바구니 상품이 있을시 페이지 새로고침
		if	( $check_ea >0	 ){	pageRedirect('/order/cart');	}

		if	($_SESSION["refer_adress"] != "")	$cart_history	= $_SESSION["refer_adress"];
		else									$cart_history	= "../main/index";

		//좋아요 할인 혜택구분 $cfg['order']['fblike_ordertype'] 0 : 회원/비회원, 1 : 회원만
		$session_arr	= ( $this->session->userdata('user') ) ? $this->session->userdata('user') : $_SESSION['user'];
		// 설정값을 적용여부 값으로 변경
		$cfg['order']['fblike_ordertype']	= ( ($cfg['order']['fblike_ordertype'] == 1 && $session_arr['member_seq'] ) || ($cfg['order']['fblike_ordertype'] != 1) ) ? 1 : 0;

		$cart_promotioncode = $this->session->userdata('cart_promotioncode_'.session_id());

		// ### NEW 배송 그룹 정보 추출 :: START ### -> shipping library 계산
		$this->load->library('shipping');
		if($_POST['nation'])    $ship_ini['nation']     = $_POST['nation'];
		$ini_info				= $this->shipping->set_ini($ship_ini);
		$shipping_group_list	= $this->shipping->get_shipping_groupping($cart['list']);

		// 상세 배송비 금액 추출
		$shipping_cost_detail	= $shipping_group_list['shipping_cost_detail'];

		// 전체 배송비 금액 추출
		$total_shipping_price	= $shipping_group_list['total_shipping_price'];

		// 최종 결제 금액 재계산
		$cart['total_price'] = $cart['total_price'] + $total_shipping_price;

		# 총 결제금액 비교통화 노출
		$cart['total_price_compare'] = showCompareCurrency('',$cart['total_price'],'return',array('layClass'=>'fx14 wx140 black mlminus100'));

		// 배송가능 해외국가 추출
		$ship_gl_arr	= $this->shippingmodel->get_gl_shipping();
		$ship_gl_list	= $this->shippingmodel->split_nation_str($ship_gl_arr);
		// ### NEW 배송 그룹 정보 추출 및 계산 :: END ###

		// ### 구 배송 그룹 정보 매칭 :: START ###
		$cart = $this->shipping->get_old_shipping_groupping($cart, $shipping_group_list);
		// ### 구 배송 그룹 정보 매칭 :: END ###

		// 전체 배송 목록 중 배송불가 상품 포함여부
		$impossible_shipping_flag = false;

		if($shipping_group_list) foreach($shipping_group_list as $k => $deli_info){
			$goods_cnt += count($deli_info['goods']);
			if($deli_info["ship_possible"] === "N") {
				$impossible_shipping_flag = true;
			}
		}

		unset($shipping_group_list['shipping_cost_detail']);
		unset($shipping_group_list['total_shipping_price']);

		//빅데이터를 위해 최근 상품을 기준으로 한다
		if	($cart['list'])
			$this->bigdataGoodsSeq = $cart['list'][0]['goods_seq'];

		$template_dir	= $this->template->template_dir;
		$compile_dir	= $this->template->compile_dir;

		// 신)배송비 관련 변수 정의
		$this->template->assign(array(
			'ship_gl_arr'=>$ship_gl_arr,
			'ship_gl_list'=>$ship_gl_list
		)); // 국가목록

		$this->template->assign('ini_info',$ini_info); // 배송ini 설정정보
		$this->template->assign('goods_cnt',$goods_cnt); // 상품 종 수
		$this->template->assign('shipping_group_list',$shipping_group_list); // 배송그룹LIST
		$this->template->assign('shipping_cost_detail',$shipping_cost_detail); // 배송비 상세
		$this->template->assign('total_shipping_price',$total_shipping_price); // 전체 배송비
		$this->template->assign('impossible_shipping_flag', $impossible_shipping_flag); // 전체 배송 목록 중 배송불가 상품 포함여부
		// 신)배송비 관련 assign END

		//skin_version @2016-10-13 pjm
		$skin_configuration = skin_configuration($this->skin);
		$this->template->assign(array('skin_version'=>$skin_configuration['skin_version']));

		$this->template->assign('firstmallcartid',session_id());
		$this->template->assign('list',$cart['list']);
		//$this->template->assign('list',$cart['newlist']);
		$this->template->assign('data_goods',$cart['data_goods']);
		$this->template->assign('is_coupon',$is_coupon);
		$this->template->assign('is_goods',$is_goods);
		$this->template->assign('cfg',$cfg);
		$this->template->assign('promocodeSale',$cart['promocodeSale']);
		$this->template->assign('total',$cart['total']);
		$this->template->assign('total_ea',$cart['total_ea']);
		$this->template->assign('total_reserve',$cart['total_reserve']);
		$this->template->assign('total_point',$cart['total_point']);
		$this->template->assign('total_sale',$cart['total_sale']);
		$this->template->assign('total_sale_list',$cart['total_sale_list']);
		$this->template->assign('total_price',$cart['total_price']);
		$this->template->assign('total_price_compare',$cart['total_price_compare']);
		$this->template->assign('shipping_price',$cart['shipping_price']);
		$this->template->assign('shipping_company_cnt',$cart['shipping_company_cnt']);
		$this->template->assign('provider_shipping_policy',$cart['provider_shipping_policy']);
		$this->template->assign('provider_shipping_price',$cart['provider_shipping_price']);
		$this->template->assign('shop_shipping_policy',$cart['shop_shipping_policy']);
		$this->template->assign('cart_history',$cart_history);
		$this->template->assign('member_seq',$this->userInfo['member_seq']);
		$this->template->assign('cart_promotioncode' , $cart_promotioncode);
		$this->template->assign('cartpage',true);//현재페이지정보 넘겨줌

		$international_shipping_info_path	= str_replace('cart.html', '../goods/_international_shipping_info.html', $this->template_path());
		$this->template->define('INTERNATIONAL_SHIPPING_INFO', $international_shipping_info_path);

		// 상품별 주문배송방법 선택
		$this->template->assign('shipping_group_policy',$cart['shipping_group_policy']);
		$this->template->assign('shipping_group_price',$cart['shipping_group_price']);

		if($cart['list'] && $cart['total_price']){
			$marketing_admin	= $this->session->userdata('marketing');
			$this->load->library('partnerlib');

			$navercheckout = $this->partnerlib->getPartnerSettingInfo('navercheckout', [], $marketing_admin, "cart");
			$navercheckoutConfig = $navercheckout['setConfig'];
			// 네이버 체크아웃
			if($navercheckout['use']) {
				// 예외카테고리 체크, 예외상품 체크
				$expectCategoryChk	= false;
				$expectGoodsChk		= false;
				
				foreach($cart['list'] as $key => $data){
					$categorys = $this->goodsmodel->get_goods_category($data['goods_seq']);
					foreach($navercheckoutConfig['except_category_code'] as $v1){
						foreach($categorys as $v2){
							if($v1['category_code']==$v2['category_code'] || preg_match("/^".$v1['category_code']."/",$v2['category_code'])){
								$expectCategoryChk = true;
							}
						}
					}

					foreach($navercheckoutConfig['except_goods'] as $v1){
						if($v1['goods_seq']==$data['goods_seq']){
							$expectGoodsChk = true;
						}
					}
				}
				if($expectGoodsChk || $expectCategoryChk) {
					$this->template->assign(array('npay_init'=>'hide'));
				}

				$this->template->assign(array('use_postpaid'=>$navercheckout['use_postpaid']));
				$this->template->assign(array('navercheckout'=>$navercheckoutConfig));
				// 장바구니에서는 찜버튼 노출하지 않는다.
				$navercheckout['btn'][2] = 1;
				$this->template->assign(array('npay_btn'=>$navercheckout['btn']));
				$this->template->assign(array('not_buy_npay'=>$navercheckout['not_buy_chk']['not_buy_npay'],'not_buy_msg'=>$navercheckout['not_buy_chk']['not_buy_msg']));
				$this->template->define(array('navercheckout'=>'naverpay2.1.html'));
				$tmptpl = $this->template->fetch('navercheckout');
				$this->template->assign(array('navercheckout_tpl'=>$tmptpl));
			}

			$talkbuy = $this->partnerlib->getPartnerSettingInfo('talkbuy', [], $marketing_admin, "cart");
			$filePath	= $this->template_path();
			$browsers = getBrowser();
			// IE는 지원안함
			if($talkbuy['use'] && $browsers['nickname'] !== 'MSIE') {
				$this->template->assign(array('talkbuy'=>$talkbuy['setConfig']));
				$this->template->assign(array('talkbuy_btn'=>$talkbuy['btn']));
				// 카카오페이 사용유무 판단
				$this->template->assign(array('not_use_daumkakaopay'=>$this->config_system['not_use_daumkakaopay'] == 'n' ? true : false));
				$this->template->define('talkbuyorder', 'talkbuy_order.html');
				$tmptpl = $this->template->fetch('talkbuyorder');
				$this->template->assign(array('talkbuyorder_tpl'=>$tmptpl));
			}
		}

		//2016.03.31 견적서 버튼 추가 pjw
		if($this->config_basic['useestimate'] == 'Y'){
			$this->template->assign(array('btn_estimateyn'=>'y'));
		}

		$this->template->template_dir = $template_dir;
		$this->template->compile_dir = $compile_dir;

		// ifdo 연동
		$this->load->library('ifdolibrary');
		$ifdo_tags		= $this->ifdolibrary->cart_view($shipping_group_list);

        // 채널톡 연동
        $this->load->library('channeltalklibrary');
        $channeltalk = $this->channeltalklibrary->wish_in();

		$this->print_layout($this->template_path());
		foreach($this->cart_alerts as $msg){
			alert($msg);
		}
	}

	// 장바구니 담기
	public function add(){
		$this->load->model('cartmodel');

		$goodsSeq 				= $this->input->post('goodsSeq');
		$optionEa 				= $this->input->post('optionEa');
		$gl_option_select_ver 	= $this->input->post('gl_option_select_ver');
		$order_mode				= $this->input->get('order_mode');
		$fix_payment			= $this->input->post("fix_payment");
		$order_label			= $this->input->post("label");

		// 구매 최소/최대수량 체크
		if($goodsSeq && $optionEa){
			$goods_seq			= $goodsSeq;
			$goods				= $this->goodsmodel->get_goods($goods_seq);
			$opt_ea				= array_sum($optionEa);
			$goods_name_strlen	= mb_strlen($goods['goods_name']);
			$error				= false;

			if($goods_name_strlen > 15) $alert_h = 160;
			elseif($goods_name_strlen > 50) $alert_h = 175;
			elseif($goods_name_strlen > 100) $alert_h = 195;
			else $alert_h = 140;

			if($goods['min_purchase_ea'] && $goods['min_purchase_ea'] >  $opt_ea){
				$error = true;
				$error_message = getAlert('oc022',array(addslashes($goods['goods_name']),$goods['min_purchase_ea']));
			}
			if($goods['max_purchase_ea'] && $goods['max_purchase_ea'] < $opt_ea){
				$error = true;
				$error_message = getAlert('oc023',array(addslashes($goods['goods_name']),($goods['max_purchase_ea']+1)));
			}

			if($error) {
				if($order_mode == "talkbuy") {
					echo json_encode(["result" => "error_min_max_purchase_ea", "message" => $error_message]);
					exit;
				}else{
					openDialogAlert($error_message,400,140,'parent',"");
					exit;
				}
			}
		}

		// 옵션 선택 ver 0.1일 경우
		if	($gl_option_select_ver == '0.1'){
			$chk_result	= $this->cartmodel->chk_cart_ver_0_1();
			if	(!$chk_result['status']){
				if($order_mode == "talkbuy") {
					echo json_encode(["result" => "error_cart_option", "message" => $chk_result['errorMsg']]);
					exit;
				}else{
					openDialogAlert($chk_result['errorMsg'], 400, 200, "parent");
					js("$('#openDialogLayerMsg','parent').attr('align','left')"); // dialog alert left 정렬
					exit;
				}
			}else{
				$result			= $this->cartmodel->add_cart_ver_0_1();
				if ($result['goods_status'] != 'normal') {
					openDialogConfirm(getAlert('gv012'),400,160,'parent','','history.go(-1);');
					exit;
				}
				$mode			= $result['mode'];
				$member_seq		= $result['member_seq'];
				$goods_seq		= $result['goods_seq'];
				$cart_seq		= $result['cart_seq'];
			}
		}else{ // 옵션선택 구ver 일 경우
			$result			= $this->cartmodel->add_cart();
			$mode			= $result['mode'];
			$member_seq		= $result['member_seq'];
			$goods_seq		= $result['goods_seq'];
			$cart_seq		= $result['cart_seq'];
		}

		if($mode == "cart" || $order_mode){

			if($mode == "cart"){
				// 배송방법체크 -> 구버전 배송방법
				$data_cart = $this->cartmodel->catalog();
				list($reload_cart, $arr_check_delivery) = $this->cartmodel->check_shipping_method($data_cart['list']);
				if($reload_cart){
					$this->cartmodel->update_shipping_method_for_delivery($arr_check_delivery);
				}

				// 상품분석 수집
				$this->load->model('goodslog');
				$this->goodslog->add('cart',$goods_seq);
			}

			if($order_mode != "npay" && $order_mode != "talkbuy"){
				// 고객리마인드서비스 알림 상세유입로그
				$this->load->helper('reservation');
				$curation = array("action_kind"=>"cart","cart_seq"=>$cart_seq,"goods_seq"=>$goods_seq);
				curation_log($curation);

				// gtag 연동
				$this->load->library('googleGtag');
				$sGlobalTags	= $this->googlegtag->globalTag();
				if ($sGlobalTags)	{
					echo "<script>parent.gtag_report_cart();</script>";
				}

				//상품이 장바구니에 담겼습니다.<br/><strong>지금 확인하시겠습니까?</strong>
				if(!$msg) $msg = getAlert('gv011');

				if( $this->fammerceMode  || $this->storefammerceMode ) {
					$yescallback = "parent.location.href='../order/cart';";
					openDialogConfirm($msg,400,160,'parent',$yescallback,'history.go(-1);');
				}
				else{
					$yescallback = "top.location.href='../order/cart';";
					$nocallback = "top.location.reload();";
					openDialogConfirm($msg,400,160,'parent',$yescallback, $nocallback);
				}

				// 우측 퀵메뉴 장바구니 카운트 증가 추가 leewh 2014-06-19
				echo("<script>top.getRightItemTotal('right_item_cart');</script>");

				//GA통계
				if($this->ga_auth_commerce_plus){
					$params['item'] = array('goods_seq'=>$goods_seq,'cart_seq'=>$cart_seq);
					$params['action'] = "add";
					echo google_analytics($params,"cart_add");
				}


			}else{

				$goodsSeq 				= $this->input->get('no');
				$skin_version			= $this->input->get('skin_version');
				# 네이버페이/카카오톡구매 주문시
				if($order_mode == "talkbuy") {
					//pageRedirect("../talkbuy/buy?goodsSeq=".$goodsSeq."&mode=".$mode."&skin_version=".$skin_version."&market=".$order_mode,'','self');
					echo json_encode(["result" => "success", "message" => "장바구니 담기 성공"]);
					exit;
				}else{
					pageRedirect("../naverpay/buy?goodsSeq=".$goodsSeq."&mode=".$mode."&skin_version=".$skin_version."&market=".$order_mode,'','self');
				}
			}
		}else{
			//GA통계 이전페이지 기록으로 세션사용
			if($this->ga_auth_commerce_plus){
				$unsetuserdata = array('ga_referer' => '', 'ga_goods_seq' => '');
				$this->session->unset_userdata($unsetuserdata);
				$_SESSION['ga_referer']	= '';
				$_SESSION['ga_goods_seq']	= '';
				if($_POST['referer_page_ga']){
					$this->session->set_userdata('ga_referer',$_POST['referer_page_ga']);
					$this->session->set_userdata('ga_goods_seq',$goods_seq);
					$_SESSION['ga_referer'] = $_POST['referer_page_ga'];
					$_SESSION['ga_goods_seq'] = $goods_seq;
				}
			}

			$url	= "/order/settle?mode=".$mode;
			if(!isset($_GET['guest']) && !$member_seq){
				$url	= "/member/login?return_url=" . urlencode($url);
			}

			$this->session->set_userdata(['fix_payment' => $fix_payment, 'order_label' => $order_label]);
			if ($fix_payment) {
				$url .= '&fix_payment=' . $fix_payment;
				$this->session->set_userdata(['fix_payment' => $fix_payment]);
			}
			if ($order_label) {
				$this->session->set_userdata(['order_label' => $order_label]);
			}

			if	($msg){
				$yescallback = "top.location.href='".$url."';";
				openDialogConfirm($msg,400,160,'top',$yescallback,'');
			}else{
				pageLocation($url,'','top');
				echo("<script>top.location.href='".$url."';</script>");
			}
		}
	}


	public function addcart()
	{

		if( isset($_GET['mode']) ) $mode = $_GET['mode'];
		else $mode = "cart";

		if(!$this->userInfo['member_seq']){
			$return_url = "/order/cart";
			$url = "/member/login?return_url=" . urlencode($return_url);
			if( $this->fammerceMode  || $this->storefammerceMode ) {
				pageRedirect($url,'','parent');
			}else{
				pageRedirect($url,'','top');
			}

			exit;
		}else{
			if( $this->fammerceMode  || $this->storefammerceMode ) {
				echo("<script>parent.location.replace('../order/cart?mode=".$mode."');</script>");
			}else{
				echo("<script>top.location.replace('../order/cart?mode=".$mode."');</script>");
			}
		}
	}

	public function addsettle()
	{
		// 국가 선택값 넘어온 경우
		$nation = $this->input->post("nation");
		if ($nation) {
			$urlParameter = '&nation=' . $nation;
		}

		// 결제수단 고정
		$fixPayment = $this->input->post("fix_payment");
		if ($fixPayment) {
			$urlParameter .= '&fix_payment=' . $fixPayment;
		}

		// 장바구니 주문 방식 {cart: 전체 주문하기, choice: 선택상품 주문하기}
		$mode = "cart";
		if (isset($_GET['mode'])) {
			$mode = $_GET['mode'];
		}

		$moveTarget = "top";
		if ($this->fammerceMode || $this->storefammerceMode) {
			$moveTarget = "parent";
		}

		// 배송불가 내역 체크 :: 2016-08-01 ㅣlwh
		$shipPossible = $this->input->post('ship_possible');
		if ($mode == 'cart' && $shipPossible) {
			foreach ($shipPossible as $k => $pos) {
				if ($pos != 'Y') {
					// 주문이 불가능한 상품이 있습니다.
					openDialogAlert(getAlert('os142'), 400, 140, 'parent', "");
					exit;
				}
			}
		}

		// 장바구니 옵션 번호 리스트
		$binding_cart_option_seq_list = $this->input->post('cart_option_seq');

		// 장바구니 옵션 번호 유효성 검사
		foreach ($binding_cart_option_seq_list as $cart_option_seq) {
			if (!is_numeric($cart_option_seq)) {
				show_error("장바구니 옵션 일련번호 항목은 숫자 이외에는 입력할 수 없습니다.");
			}
		}

		// 장바구니 선택 주문 시
		if ($mode == 'choice' && count($binding_cart_option_seq_list) > 0) {
			// 장바구니 옵션 번호의 장바구니 번호 추출
			$cart_seq_list = $this->db->select("cart_seq")
			->from("fm_cart_option")
			->where_in('cart_option_seq', $binding_cart_option_seq_list)
			->get()
			->result_array();

			// 장바구니 번호 쿼리 바인딩 준비 작업
			$binding_cart_seq_list = [];
			foreach ($cart_seq_list as $cart_seq) {
				$binding_cart_seq_list[] = $cart_seq['cart_seq'];
			}

			// 장바구니 관련 업데이트 처리
			if (count($binding_cart_seq_list) > 0) {
				// 장바구니 구분 변경
				$this->db->set('distribution', 'choice')->where_in('cart_seq', $binding_cart_seq_list)->update("fm_cart");
				// 장바구니 옵션 선택주문구분 변경
				$this->db->set('choice', 'n')->where_in('cart_seq', $binding_cart_seq_list)->update("fm_cart_option");
				$this->db->set('choice', 'y')->where_in('cart_option_seq', $binding_cart_option_seq_list)->update("fm_cart_option");
			}
		}

		$this->load->model("cartmodel");
		$cart = $this->cartmodel->catalog();

		// 구매 최소/최대수량 체크
		foreach ($cart['data_goods'] as $cart_goods_seq => $data) {
			$goods = $this->goodsmodel->get_goods($cart_goods_seq);

			// 구매수량 체크
			if ($goods['min_purchase_ea'] && $goods['min_purchase_ea'] > $data['option_ea']) {
				pageBack(addslashes(getAlert('oc022',array(addslashes($goods['goods_name']),$goods['min_purchase_ea']))));
				exit;
			}
			if ($goods['max_purchase_ea'] && $goods['max_purchase_ea'] < $data['option_ea']) {
				pageBack(addslashes(getAlert('oc023',array(addslashes($goods['goods_name']),($goods['max_purchase_ea']+1)))));
				exit;
			}
		}

		// 주문/결제 페이지 주소
		$url = '../order/settle?mode=' . $mode;

		// 현재 접속자가 비회원인 경우
		if (!$this->userInfo['member_seq']) {
			// 로그인 페이지 주소 설정
			$url = "/member/login?return_url=" . urlencode($url);
		}

		// 이동 주소 파라미터 추가
		$url = $url . $urlParameter;

		// 이동 처리
		pageLocation($url, '', $moveTarget);
		exit;
	}


	public function modify()
	{
		$seq = (int) $_GET['seq'];
		$where[] = "cart_seq=?";
		$where_val[] = $seq;
		if(!($_POST['ea'][$seq]>=1)) $_POST['ea'][$seq] = 1;
		$query = "update fm_cart_option set ea='".$_POST['ea'][$seq]."' where ".implode(' and ',$where);
		$this->db->query($query,$where_val);
		pageReload('','parent');
	}

	public function del()
	{
		$this->load->model('cartmodel');
		$this->load->model('goodsmodel');
		if( !isset($_POST['cart_option_seq']) ){
			//삭제할 상품이 없습니다.
			openDialogAlert(getAlert('oc007'),400,140,'parent',"");
			exit;
		}

		// 카트 데이터 검증 :: 2017-08-16 lwh
		foreach($_POST['cart_option_seq'] as $cart_option_seq){
			$cart_data = $this->cartmodel->get_cart_by_cart_option($cart_option_seq);
			if($this->userInfo){ // 회원
				if($cart_data['member_seq'] != $this->userInfo['member_seq']){
					//잘못된 접근입니다.
					$callback = "parent.location.reload();";
					openDialogAlert(getAlert('et018'),400,140,'parent',$callback);
					exit;
				}
			}else{ // 비회원
				$session_id	= session_id();
				if($cart_data['session_id'] != $session_id){
					//잘못된 접근입니다.
					$callback = "parent.location.reload();";
					openDialogAlert(getAlert('et018'),400,140,'parent',$callback);
					exit;
				}
			}
		}

		//GA통계
		if($this->ga_auth_commerce_plus){
			$this->load->model('goodsmodel');
			foreach($_POST['cart_option_seq'] as $cart_option_seq){
				$ga_cart_option = $this->cartmodel->get_cart_option_by_cart_option($cart_option_seq);
				$goods = $this->goodsmodel->get_goods($ga_cart_option["goods_seq"]);

				for($i=1;$i<5;$i++){
					$temp["option".$i] = $ga_cart_option['option'.$i];
				}

				$temp["ea"] = $ga_cart_option["ea"];
				$temp["goods_name"] = $goods["goods_name"];
				$temp["goods_seq"] = $goods["goods_seq"];

				$ga_params["item"][] = $temp;
			}

			$ga_params['action'] = "remove";
			echo google_analytics($ga_params,"cart_remove");
		}

		foreach($_POST['cart_option_seq'] as $cart_option_seq){
			$this->cartmodel->delete_cart_option($cart_option_seq,'del');
		}

		//장바구니 상품을 삭제하였습니다.
		openDialogAlert(getAlert('oc008'),400,140,'parent',"parent.location.reload();");
	}

	/* 우측퀵메뉴 장바구니 삭제 추가 leewh 2014-06-09 */
	public function quickCartDel() {
		$this->load->model('cartmodel');
		$cart_option_seq = $_POST['cart_option_seq'];
		$msg="fail";

		if ($cart_option_seq) {
			$this->cartmodel->delete_cart_option($cart_option_seq,'del');
			$msg="ok";
		}

		echo $msg;
	}

	public function optional_view(){
		$this->load->model('cartmodel');
		$cart_seq = (int) $_GET['no'];

		$cart = $this->cartmodel->cart_list();
		foreach($cart['list'] as $k => $data){
			if($data['cart_seq'] == $cart_seq){
				$cart_options = $data['cart_options'];
			}

			if($data['cart_seq'] == $cart_seq){
				$cart_suboptions = $data['cart_suboptions'];
			}
		}

		$file = str_replace('optional_view','_optional_view',$this->template_path());
		$this->template->assign(array('cart_options'=>$cart_options));
		$this->template->assign(array('cart_suboptions'=>$cart_suboptions));
		$this->template->define(array('LAYOUT'=>$file));
		$this->template->print_('LAYOUT');
	}

	public function optional_changes(){

		$this->load->model('goodsmodel');
		$this->load->model('categorymodel');
		$this->load->model('brandmodel');
		$this->load->model('membermodel');
		$this->load->helper('order');

		secure_vulnerability('goods', 'no', $_GET['goods_seq']);
		$_GET['goods_seq']		= (int) $_GET['goods_seq'];
		$applypage					= 'view';
		$cart_table					= trim($_GET['cart_table']);
		$cart_option_seq			= (int) $_GET['no'];
		$member_seq					= (int) $_GET['member_seq'];
		$goods_seq					= $_GET['goods_seq'];
		$admin						= false;

		// 회원정보 가져오기
		$data_member['group_seq']	= 0;
		if	($cart_table){
			$admin					= true;
			if($member_seq > 0){
				$data_member				= $this->membermodel->get_member_data($member_seq);
				$data_member['group_seq']	= (int) $data_member['group_seq'];
				$admin_member_seq			= (int) $data_member['member_seq'];
			}
			echo '<link rel="stylesheet" type="text/css" href="/admin/skin/default/css/admin_cart.css">';
		}else{
			if($this->userInfo){
				$data_member				= $this->membermodel->get_member_data($this->userInfo['member_seq']);
				$data_member['group_seq']	= (int) $data_member['group_seq'];
			}
		}

		# 임시장바구니에 담긴 순번 가져오기.
		if($_GET['goods'] && $_GET['tmp_cart']){
			foreach($_GET['goods'][$goods_seq]['option'] as $k=>$opt){
				if(!$tmp_num) $tmp_num = $k; else continue;
			}
		}

		// 장바구니 추출
		if($_GET['goods_seq'] && !$cart_option_seq){

			//임시장바구니 용(개인결제, 관리자 주문 공용) #2015-08-20 pjm
			if($_GET['goods'][$goods_seq]){
				$cart_options		= $this->goodsmodel->get_cart_tmp_option($_GET['goods'][$goods_seq],$goods_seq,$tmp_num);
			}

		}else{
			// 장바구니 추출
			if($cart_table == "person"){ // 개인결제
				$this->load->model('personcartmodel');
				$cart				= $this->personcartmodel->get_cart_by_cart_option($cart_option_seq);
				$cart_options		= $this->personcartmodel->get_cart_option_by_cart_option($cart_option_seq,$goods_seq);
				$cart_list			= $this->personcartmodel->catalog($admin);
			}else{ // 일반
				$this->load->model('cartmodel');
				$cart				= $this->cartmodel->get_cart_by_cart_option($cart_option_seq);
				$cart_options		= $this->cartmodel->get_cart_option_by_cart_option($cart_option_seq);
				$cart_list			= $this->cartmodel->catalog($admin);
			}
		}

		if(!$goods_seq) $goods_seq = $cart['goods_seq'];
		// 상품정보
		$goods					= $this->goodsmodel->get_goods($goods_seq);
		// 상품옵션
		$options				= $this->goodsmodel->get_goods_option($goods_seq,array('option_view'=>'Y'));
		// 상품추가옵션
		$suboptions				= $this->goodsmodel->get_goods_suboption($goods_seq,array('option_view'=>'Y'));
		// 카테고리정보
		$categorys				= $this->goodsmodel->get_goods_category($goods_seq);
		if($categorys) foreach($categorys as $key => $data_category){
			if( $data_category['link'] == 1 ){
				$category_code = $this->categorymodel->split_category($data_category['category_code']);
			}
		}
		// 브랜드 정보
		$brands				= $this->goodsmodel->get_goods_brand($goods_seq);
		if($brands) foreach($brands as $key => $data){
			if( $data['link'] == 1 ){
				$brand_code		= $this->brandmodel->split_brand($data['category_code']);
			}
		}
		// 상품이미지
		$images					= $this->goodsmodel->get_goods_image($goods_seq);
		$goods['image']			= $images[1]['thumbView']['image'];
		// 상품추가입력사항
		$inputs					= $this->goodsmodel->get_goods_input($goods_seq);

		// 각 설정값 추출
		if	(!$this->config_system)	$this->config_system	= config_load('system');
		if	(!$this->reserves)		$this->reserves			= config_load('reserve');
		$cfg_reserve		= $this->reserves;
		$cfg_order = ($this->cfg_order) ? $this->cfg_order : config_load('order');

		//----> sale library 적용
		$param['cal_type']				= 'list';
		$param['total_price']			= 0;
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $data_member['member_seq'];
		$param['group_seq']				= $data_member['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		// 옵션 목록
		if($cart_options) $cart_options = array($cart_options);

		foreach($cart_options as $k => $cart_opt){

			if($cart_opt){
				//----> sale library 적용
				unset($param, $sales);
				$param['option_type']				= 'option';
				$param['consumer_price']			= $cart_opt['consumer_price'];
				$param['price']						= $cart_opt['price'];
				$param['ea']						= 1;
				$param['category_code']				= $category_code;
				$param['brand_code']				= $brand_code;
				$param['goods_seq']					= $goods['goods_seq'];
				$param['goods']						= $goods;
				$this->sale->set_init($param);
				$sales								= $this->sale->calculate_sale_price($applypage);

				$cart_opt['price']					= $sales['result_price'];
				$this->sale->reset_init();
				//<---- sale library 적용

				$cart_opt['cart_suboptions']		= '';
				$cart_opt['cart_inputs']			= '';
				// 장바구니 추가입력사항
				if($_GET['goods_seq'] && !$cart_option_seq){

					//임시장바구니 용(개인결제, 관리자 주문 공용) #2015-08-20 pjm
					$etc_ptions			= $this->goodsmodel->get_cart_tmp_etc_option($tmp_num,$_GET['goods'][$goods_seq],$goods_seq);
					$cart_opt['cart_suboptions']	= $etc_ptions['suboption'];
					$cart_opt['cart_inputs']		= $etc_ptions['inputoption'];
				}else{
					if($cart_table == "person"){
						$cart_opt['cart_suboptions']	= $this->personcartmodel->get_cart_suboption_by_cart_option($cart_opt['cart_option_seq']);
						$cart_opt['cart_inputs']		= $this->personcartmodel->get_cart_input_by_cart_option($cart_opt['cart_option_seq']);
					}else{
						$cart_opt['cart_suboptions']	= $this->cartmodel->get_cart_suboption_by_cart_option($cart_opt['cart_option_seq']);
						$cart_opt['cart_inputs']		= $this->cartmodel->get_cart_input_by_cart_option($cart_opt['cart_option_seq']);
					}
				}

				foreach($cart_opt['cart_suboptions'] as $s => &$subdata){
					//----> sale library 적용 ( 정가기준 목록에서는 sale_price를 넘기지 않음 )
					unset($param, $sales);
					$param['option_type']				= 'suboption';
					$param['sub_sale']					= $subdata['sub_sale'];
					$param['consumer_price']			= $subdata['consumer_price'];
					$param['price']						= $subdata['price'];
					$param['sale_price']				= $subdata['price'];
					$param['ea']						= $subdata['ea'];
					$param['goods_seq']					= $goods['goods_seq'];
					$param['goods']						= $goods;
					$this->sale->set_init($param);
					$sales								= $this->sale->calculate_sale_price($applypage);

					$subdata['price'] = $sales['result_price'];
				}

				$cart_options[$k] = $cart_opt;
			}
		}

		foreach($options as $k => $opt){
			//----> sale library 적용
			unset($param, $sales);

			if($goods['package_yn'] == 'y'){
				for($i_chk=1;$i_chk<=5;$i_chk++){
					if($opt['package_option_seq'.$i_chk]){
						$params_chk = array(
							'mode'=>'option',
							'goods_seq'=>$opt['goods_seq'],
							'option_seq'=>$opt['option_seq'],
							'package_option_seq'=>$opt['package_option_seq'.$i_chk],
							'package_option'=>$opt['package_option'.$i_chk],
							'no'=>$i_chk,
						);
						if( !check_package_option($params_chk) ){
							$opt['package_error_type']	=  'option';
							$opt['package_error']	=  true;
						}
					}
				}
			}
			if($opt['package_error']){
				//현재 구매할 수 없는 상품입니다.
				echo(getAlert('os143'));
				exit;
			}

			$param['option_type']				= 'option';
			$param['consumer_price']			= $opt['consumer_price'];
			if($suboptions)$param['sale_price']	= 0; // 추가옵션이 있는 경우, sale->sale_price 초기화
			$param['price']						= $opt['price'];
			$param['ea']						= 1;
			$param['category_code']				= $category_code;
			$param['brand_code']				= $brand_code;
			$param['goods_seq']					= $goods['goods_seq'];
			$param['goods']						= $goods;
			$this->sale->set_init($param);
			$sales								= $this->sale->calculate_sale_price($applypage);
			$opt['price']						= $sales['result_price'];
			$options[$k]						= $opt;
			$this->sale->reset_init();
			//<---- sale library 적용

			/* 대표가격 */
			if($opt['default_option'] == 'y'){
				$goods['price'] 			= $opt['price'];
				$goods['sale_price'] 		= $opt['price'];
				$goods['consumer_price'] 	= $opt['consumer_price'];
				$goods['reserve'] 			= $opt['reserve'];
				if( $opt['option_title'] ) $goods['option_divide_title'] = explode(',',$opt['option_title']);
				if( $opt['newtype'] ) $goods['divide_newtype'] = explode(',',$opt['newtype']);
			}
			$options[$k]['opt_join'] = implode('/',$optJoin);

			// 재고 체크
			$opt['chk_stock']	= check_stock_option_list($goods, $cfg_order, $opt['stock'], $opt['reserve15'], $opt['reserve25'], $opt['ea'], 'view');
			if( $opt['chk_stock'] ) $runout = false;
			$options[$k]['chk_stock'] = $opt['chk_stock'];
		}

		if($suboptions) foreach($suboptions as $key => $tmp){
			foreach($tmp as $k => $opt){
				## 연결상품 체크
				if($goods['package_yn_suboption'] == 'y'){
					if($opt['package_option_seq1']){
						$params_chk = array(
							'mode'					=> 'suboption',
							'goods_seq'				=> $opt['goods_seq'],
							'option_seq'			=> $opt['suboption_seq'],
							'package_option_seq'	=> $opt['package_option_seq1'],
							'package_option'		=> $opt['package_option1'],
							'no'=>1
						);
						if( !check_package_option($params_chk) ){
							$opt['package_error_type']	= 'suboption';
							$opt['package_error']		= true;
						}
					}
				}
				if( $opt['package_error'] ){
					//현재 구매할 수 없는 상품입니다.
					echo(getAlert('os143'));
					exit;
				}

				$opt['chk_stock'] = check_stock_suboption($goods['goods_seq'],$opt['suboption_title'],$opt['suboption'],$ea,$cfg_order);
				$opt['color'] = $opt['color'] ? $opt['color'] : '#fff';

				if( $opt['chk_stock'] ){
					$sub_runout = true;
				}

				//----> sale library 적용
				unset($param, $sales);
				$param['option_type']				= 'suboption';
				$param['sub_sale']					= $opt['sub_sale'];
				$param['consumer_price']			= $opt['consumer_price'];
				$param['price']						= $opt['price'];
				$param['ea']						= 1;
				$param['category_code']				= $category_code;
				$param['brand_code']				= $brand_code;
				$param['goods_seq']					= $goods['goods_seq'];
				$param['goods']						= $goods;
				$this->sale->set_init($param);
				$sales								= $this->sale->calculate_sale_price($applypage);

				$opt['price']						= $sales['result_price'];
				$this->sale->reset_init();
				//<---- sale library 적용

				$suboptions[$key][$k] = $opt;
			}
		}

		// 입력옵션 매칭 ( 100% 매칭 불가능 )
		if	($cart_inputs && $inputs)foreach($inputs as $k => $inpt){
			foreach($cart_inputs as $c => $cinpt){
				if	( $cinpt['input_title'] == $inpt['input_name'] &&
					  $cinpt['type'] == $inpt['input_form'] ){
					$cinpt['input_limit']	= $inpt['input_limit'];
					$cinpt['input_require']	= $inpt['input_require'];
				}
				$cart_inputs[$c]	= $cinpt;
			}
		}

		// 옵션 분리형
		if($goods['option_view_type']=='divide' && $options){
			$options_n0 = $this->goodsmodel->option($goods['goods_seq']);
			$this->template->assign(array('options_n0'	=> $options_n0));
		}

		// 옵션 조합형
		if($goods['option_view_type']=='join' && $options){
			$options_join = $this->goodsmodel->option_join($goods['goods_seq']);
			$this->template->assign(array('options_join'	=> $options_join));
		}

		//$foption		= $this->goodsmodel->get_first_options($goods, $options); 옵션변경 시에는 할인 적용안하게끔 설정 2017-12-11 jhs
		$foption		= $this->goodsmodel->get_first_options($goods, $options,'none');

		if	($goods['option_view_type'] == 'join'){
			$option_data[0]['title']		= $options[0]['option_title'];
			$option_data[0]['newtype']		= $goods['divide_newtype'][0];
			$option_data[0]['options']		= $foption;
			$option_depth					= 1;
		}else{
			if	($goods['option_divide_title'])foreach($goods['option_divide_title'] as $k => $tit){
				$option_data[$k]['title']		= $tit;
				$option_data[$k]['newtype']		= $goods['divide_newtype'][$k];
				if	($k == 0)	$option_data[$k]['options']	= $foption;
				$option_depth++;
			}
		}
		$this->template->assign(array('option_depth'		=> $option_depth));
		$this->template->assign(array('option_data'			=> $option_data));
		$this->template->assign(array('select_option_mode'	=> 'optional_change'));

		$template_path	= $this->template_path();
		if	(!$this->config_system)	$this->config_system	= config_load('system');
		// PC 스킨으로 변환
		if	($cart_table){
			$template_path	= preg_replace('/^[^\/]*\//', $this->config_system['skin'] . '/', $template_path);
			$this->template->assign(array('skin'=>$this->config_system['skin']));
		}

		// 가격대체 문구 공통 처리
		$goods['string_price']		= get_string_price($goods, $data_member);
		$goods['string_price_use']	= 0;
		if	($goods['string_price'] != '')	$goods['string_price_use']	= 1;
		if	($_GET['cart_table'] == 'admin' || $_GET['cart_table'] == 'person')
			$goods['string_price_use']	= 0;
		// 버튼노출 제어 공통 처리
		$goods['string_button']		= get_string_button($goods, $data_member);
		$goods['string_button_use']	= 0;
		if	($goods['string_button']!='') $goods['string_button_use'] = 1;
		if	($_GET['cart_table'] == 'admin' || $_GET['cart_table'] == 'person')
			$goods['string_button_use']	= 0;

		/**
		 * 2021-05-24 : kjw
		 * 필수옵션에 옵션 사용 여부를 사용안함으로 둘 경우, org_basic_price 에 값이 할당되어야 하는데,
		 * org_basic_price 에 값이 할당되지 않아 해당 경우에 org_basic_price 에 할당할 수 있도록 추가
		 * 할당하는 코드가 없어 해당 부분 추가 (#55750)
		 */
		if (!$goods['org_basic_price'] && isset($foption[0]['org_basic_price']) && !$option_data[0]['options']) {
			$goods['org_basic_price'] = $foption[0]['org_basic_price'];
		}

		// 옵션 선택 박스
		$option_select_path	= str_replace('order/optional_changes.html', 'goods/_select_options.html', $template_path);
		$this->template->define('OPTION_SELECT', $option_select_path);

		## 관리자일때만 PC 설정 스킨으로 지정.
		if($admin){
			$this->template->assign("skin",$this->config_system['skin']);
		}

		//예약 상품의 경우 문구를 넣어준다 2016-11-07
		$goods['goods_name']	= get_goods_pre_name($goods,true);

		$this->template->assign(array('admin_member_seq'=>$admin_member_seq,'origin_order_seq'=>$origin_order_seq));
		$this->template->assign(array('sessionMember'=>$data_member));
		$this->template->assign(array('options'=>$options));
		$file = str_replace('optional_changes','_optional_changes',$template_path);
		$this->template->assign(array('cart'=>$cart));
		$this->template->assign(array('goods'=>$goods));
		$this->template->assign(array('suboptions'=>$suboptions));
		$this->template->assign(array('inputs'=>$inputs));
		$this->template->assign(array('cart_options'=>$cart_options));
		$this->template->assign(array('cart_suboptions'=>$cart_suboptions));
		$this->template->assign(array('cart_inputs'=>$cart_inputs));
		$this->template->assign(array('cart_table'=>$cart_table));
		$this->template->define(array('LAYOUT'=>$file));
		$this->template->print_('LAYOUT');
	}

	// 신스킨용 배송방법 변경 처리 :: 2016-07-29 lwh
	public function modify_shipping_changes(){
		$this->load->model("shippingmodel");

		$post = $this->input->post();
		$cart_seq = $post['cart_seq'];
		$ship_grp_seq = $post['ship_grp_seq'];
		$ship_set_seq = $post['ship_set_seq'];
		$ship_set_code = $post['ship_set_code'];
		$prepay_info = $post['prepay_info'];
		$hop_date = $post['hop_select_date'];
		$store_seq = $post['store_seq'];
		$cart_table = $post['cart_table'];
		$admin_mode = $post['admin_mode'];
		$nation = $post['nation'];

		if ($this->session->userdata('order_label') === 'present') {
			if ($ship_set_code != 'delivery') {
				//선물하기 상품은 택배수령만 가능합니다
				openDialogAlert(getAlert('gv113'), 400, 140, 'parent', '');
				exit;
			}
			if ($nation == 'global') {
				//선물하기 상품은 국내배송만 가능합니다
				openDialogAlert(getAlert('gv114'), 400, 140, 'parent', '');
				exit;
			}
		}

		if (!$cart_seq) {
			//변경할 장바구니정보가 없습니다.
			openDialogAlert(getAlert('os144'),400,140,'parent',"");
			exit;
		}

		$session_id	= session_id();
		$member_seq	= (int) $this->userInfo['member_seq'];

		// 기본 Bind Params.
		$addBind[]	= $ship_set_seq;
		$addBind[]	= $ship_set_code;

		if	($prepay_info){ // 선/착불 정보
			$addSet[]	= 'shipping_prepay_info = ?';
			$addBind[]	= $prepay_info;
		}

		// 매장수령 정보
		$addSet[]	= 'shipping_store_seq = ?';
		$addBind[]	= $store_seq;

		// 희망배송일 정보
		$addSet[]	= 'shipping_hop_date = ?';
		$addBind[]	= $hop_date;

		// 장바구니 정보 변경
		if($cart_table == "person"){
			$table			= "fm_person_cart";
			$table_option	= "fm_person_cart_option";
		}else{
			$table			= "fm_cart";
			$table_option	= "fm_cart_option";
		}
		$sql = "
			UPDATE ".$table."
			SET
				shipping_set_seq = ?,
				shipping_set_code = ?,
				update_date = '" . date('Y-m-d H:i:s') . "',
				" . implode(', ',$addSet) . "
			WHERE cart_seq = ?
		";
		$addBind[]	= $cart_seq;
		$this->db->query($sql, $addBind);

		// 장바구니 상품 옵션 배송변경
		$opt_sql = "UPDATE ".$table_option." SET shipping_method=? WHERE cart_seq=?";
		$this->db->query($opt_sql,array($ship_set_seq,$cart_seq));

		// 묶음배송일 경우 기존 같은 배송그룹 merge :: 2016-10-28 lwh
		$grp_info = $this->shippingmodel->get_shipping_group($ship_grp_seq);
		if($grp_info['shipping_calcul_type'] != 'each'){
			// 기존 카트 정보 추출
			if($member_seq) $this->db->where('member_seq', $member_seq);
			else			$this->db->where('session_id', $session_id);
			$this->db->where('shipping_group_seq', $ship_grp_seq);
			$this->db->where('cart_seq !=',$cart_seq);
			$this->db->select('cart_seq');
			$query		= $this->db->get($table);
			$cart_info	= $query->result_array();
			foreach($cart_info as $val)		$cart_arr[] = $val['cart_seq'];

			$set_params = array(
				'shipping_set_seq'		=> $ship_set_seq,
				'shipping_set_code'		=> $ship_set_code,
				'shipping_hop_date'		=> $hop_date,
				'shipping_store_seq'	=> $store_seq,
				'shipping_prepay_info'	=> $prepay_info
			);

			if($member_seq) $this->db->where('member_seq', $member_seq);
			else			$this->db->where('session_id', $session_id);
			// 개인결제에서 배송방법 변경 시에는 person_seq 생성안된 상품만 변경되도록 수정 2018-09-10
			if( $table == "fm_person_cart" ) $this->db->where('person_seq',"0");
			$this->db->where('cart_seq !=',$cart_seq);
			$this->db->where('shipping_group_seq', $ship_grp_seq);
			$this->db->update($table, $set_params);

			if(count($cart_arr)>0){
				$opt_up_sql = "UPDATE ? SET shipping_method = ? WHERE cart_seq IN ? ";
				$this->db->query($opt_up_sql, [$table_option, $ship_set_seq, implode(',',$cart_arr)]);
			}
		}

		//배송방법이 변경되었습니다.
		if(in_array($admin_mode,array("cart","settle"))){
			openDialogAlert(getAlert('os145'),400,140,'parent',"parent.chg_delivery_info('".$admin_mode."');");
		}else{
			openDialogAlert(getAlert('os145'),400,140,'parent',"parent.location.reload();");
		}
	}

	// 구스킨용 배송방법 변경 :: 2016-07-28 lwh
	// -신스킨용 배송방법 변경스킨은 goods/shipping_detail_info 에 있음.
	public function goods_shipping_changes(){

		$this->load->model('cartmodel');
		if( $_GET['vmode'] )
			$_GET['mode'] = $_GET['vmode'];

		$data_cart = $this->cartmodel->catalog();
		foreach($data_cart['list'] as $k => $data){
			$shipping_policy = $this->goodsmodel->get_shipping_policy($data);
			$data_cart['list'][$k]['select_shipping_method'] = $shipping_policy['shipping_method'];

			// 구 스킨내용으로 인해 provider_seq 를 변경한다. :: 2016-10-27 lwh
			$data_cart['list'][$k]['provider_seq'] = $data['shipping_group_seq'].'_'.$data['shipping_set_seq'].'_'.$data['shipping_set_code'];

			if($data_cart['list'][$k]['shipping_calcul_type'] == 'each'){
				$data_cart['list'][$k]['provider_seq'] .= '_' . $data_cart['list'][$k]['cart_option_seq'];
			}

			// 구 스킨 변경 불가로 인해 cart_option_seq 를 변경한다. :: 2016-10-28 lwh
			$data_cart['list'][$k]['cart_option_seq'] = $data_cart['list'][$k]['cart_seq'];
		}

		$file = str_replace('goods_shipping_changes','_goods_shipping_changes',$this->template_path());
		$this->template->assign($data_cart);
		$this->template->define(array('LAYOUT'=>$file));
		$this->template->print_('LAYOUT');
	}

	public function goods_shipping_modify(){
		$this->load->model('cartmodel');
		$this->load->model('shippingmodel');
		if (!isset($_POST['shipping_method'])) {
			//변경할 상품이 없습니다.
			openDialogAlert(getAlert('os003'),400,140,'parent',"");
			exit;
		}

		foreach($_POST['shipping_method'] as $cart_seq => $ship_set_seq){
			// 구스킨으로 인해 기존 cart 정보를 호출한다. :: 2016-10-27 lwh
			$set_info = $this->shippingmodel->get_shipping_set($ship_set_seq, 'shipping_set_seq');

			if($set_info['hop_use'] == 'Y' && $set_info['hopeday_required'] == 'Y'){
				$hop_date = $this->shippingmodel->get_hop_date($set_info);
				$set_params['shipping_hop_date'] = $hop_date;
			}
			unset($set_params);
			$set_params['shipping_set_seq'] = $set_info['shipping_set_seq'];
			$set_params['shipping_set_code'] = $set_info['shipping_set_code'];
			$this->db->where('cart_seq',$cart_seq);
			$this->db->update('fm_cart', $set_params);

			unset($set_params);
			$set_params['shipping_method'] = $set_info['shipping_set_seq'];
			$this->db->where('cart_seq',$cart_seq);
			$this->db->update('fm_cart_option', $set_params);
		}

		//배송방법이 변경되었습니다.
		openDialogAlert(getAlert('os002'),400,140,'parent',"parent.location.reload();");
	}

	// 옵션 변경 - 수량 변경
	public function optional_modify(){

		$aPostParams = $this->input->post();
		$aGetParams = $this->input->get();

		$this->load->model('goodsmodel');
		$this->load->model('cartmodel');
		$this->load->model('membermodel');
		$cart_table	= (trim($aPostParams['cart_table'])) ? trim($aPostParams['cart_table']) : '';
		if($cart_table == "person"){
			$this->load->model('personcartmodel');
		}
		if	($cart_table)	$this->is_adminOrder	= 'admin';

		// 장바구니 수량 변경시  최소/최대 구매 수량 체크 시작 ------------------------------------------
		if($cart_table == 'person'){
			$cart	= $this->personcartmodel->catalog($members['member_seq']);
		}else{
			$this->load->model('cartmodel');
			$cart	= $this->cartmodel->catalog($cart_table);
		}

		$goods_data = array();
		foreach($cart['list'] as $data){

			if($cart_table == "admin"){
				$optionEa			= $aPostParams['goods'][$data['goods_seq']]['optionEa'];
				$cart_option_seq	= $aPostParams['cartOptionSeq'][$data['cart_seq']][0];
			}else{
				$optionEa			= $aPostParams['optionEa'];
				$cart_option_seq	= $aPostParams['cart_option_seq'];
			}

			if($cart_table != "admin" && (!$cart_table && $data['goods_seq'] != $aPostParams['option_select_goods_seq'])) continue;
			if($data['cart_option_seq'] == $cart_option_seq) $goods_ea = array_sum($optionEa);
					else $goods_ea = $data['ea'];
			$goods_data[$data['goods_seq']]['goods_ea']		+= $goods_ea;
		}

		# 최소/최대 구매수량 체크
		foreach($goods_data as $goods_seq => $data){

		    $goods				= $this->goodsmodel->get_goods($goods_seq);
		    $goods_name_strlen	= mb_strlen($goods['goods_name']);

		    if($goods_name_strlen > 15) $alert_h = 160;
		    elseif($goods_name_strlen > 50) $alert_h = 175;
		    elseif($goods_name_strlen > 100) $alert_h = 195;
		    else $alert_h = 140;

		    // 구매수량 체크
		    if($goods['min_purchase_ea'] && $goods['min_purchase_ea'] >  $data['goods_ea']){
		        openDialogAlert(getAlert('oc022',array(addslashes($goods['goods_name']),$goods['min_purchase_ea'])),400,140,'parent',$pg_cancel_script);
		        exit;
		    }
		    if($goods['max_purchase_ea'] && $goods['max_purchase_ea'] < $data['goods_ea']){
		        openDialogAlert(getAlert('oc023',array(addslashes($goods['goods_name']),($goods['max_purchase_ea']+1))),400,140,'parent',$pg_cancel_script);
		        exit;
		    }
		}
		// 최소/최대 구매 수량 체크 종료 ------------------------------------------

		# 장바구니 비우고 새로 넣기
		if($cart_table){
			if($cart_table == "person")
				$this->personcartmodel->delete_dummy_cart();
			else
				$this->cartmodel->delete_mode($cart_table,'admin');
		}

		/**
		// 장바구니 정보 추출
		// 회원정보 추출
		**/
		if		($cart_table == "admin" && $aGetParams['member_seq'] && $this->displaymode = 'coupon'){
			$aGetParams['member_seq']		= (int) $aGetParams['member_seq'];
			$_GET['member_seq']				= $aGetParams['member_seq'];
			$members	= $this->membermodel->get_member_data($aGetParams['member_seq']);
		}elseif	($cart_table && $aPostParams['member_seq']){
			$aPostParams['member_seq']		= (int) $aPostParams['member_seq'];
			$_POST['member_seq']				= $aPostParams['member_seq'];
			$members	= $this->membermodel->get_member_data($aPostParams['member_seq']);
		}elseif	($this->userInfo['member_seq']){
			$members	= $this->membermodel->get_member_data($this->userInfo['member_seq']);
		}

		// 옵션 선택 ver 0.1일 경우
		if	($aPostParams['gl_option_select_ver'] == '0.1'){

			$aPostParams['option_select_goods_seq']	= (int) $aPostParams['option_select_goods_seq'];
			$_POST['option_select_goods_seq']			= $aPostParams['option_select_goods_seq'];

			$loop_goods			= array();
			if($aPostParams['goods']) $loop_goods = $aPostParams['goods'];
			else $loop_goods[$aPostParams['option_select_goods_seq']] = $aPostParams;

			if(!$aPostParams['goods'] && !$aPostParams['option_select_goods_seq']){
				//상품을 선택해 주세요.
				openDialogAlert(getAlert('oc009'),400,140,'parent',"");
				exit;
			}

			foreach($loop_goods as $goods_seq=>$goodsData){

				$data_option = $this->cartmodel->get_cart_option_by_cart_option($goodsData['cart_option_seq']);

				$goodsData['member_seq']				= (int) $aPostParams['member_seq'];
				$goodsData['option_select_goods_seq']	= (int) $goods_seq;
				$goodsData['shipping_method']			= $data_option['shipping_method'];
				$goodsData['old_cart_seq']				= $data_option['cart_seq'];
				$goodsData['hop_select_date']			= $aPostParams['hop_select_date'];
				$goodsData['shipping_prepay_info']		= ($aPostParams['shipping_prepay_info'])? $aPostParams['shipping_prepay_info']:"delivery";
				$goodsData['shipping_store_seq']		= $aPostParams['shipping_store_seq'];

				$goods									= $this->goodsmodel->get_goods($goods_seq);

				$tmp_num = '';
				foreach($goodsData['option'] as $k=>$opt) if(!$tmp_num) $tmp_num = $k; else continue;

				## 변경 옵션 정보
				$new_option				= $goodsData['option'][$tmp_num];
				$new_optionTitle		= $goodsData['optionTitle'][$tmp_num];
				$new_optionEa			= $goodsData['optionEa'][$tmp_num];
				$new_suboption			= $goodsData['suboption'][$tmp_num];
				if($new_suboption){
					$new_suboptionTitle	= $goodsData['suboptionTitle'][$tmp_num];
					$new_suboptionEa	= $goodsData['suboptionEa'][$tmp_num];
				}
				$new_inputValue			= $goodsData['inputsValue'][$tmp_num];
				if($new_inputValue){
					$new_inputTitle		= $goodsData['inputsTitle'][$tmp_num];
					$new_inputType		= $goodsData['inputsType'][$tmp_num];
				}

				// 상품상태 체크
				if($goods['goods_status'] != 'normal'){
					$err_msg  = '';
					if($goods['goods_name']){
						//"은(는) "
						$err_msg .= $goods['goods_name'].getAlert('oc010')." ";
					}
					if		($goods['goods_status'] == 'unsold')	$err_msg	.= getAlert('oc011'); //판매중지
					else											$err_msg	.= getAlert('oc012'); //품절된
					$err_msg .= " ".getAlert('oc013'); //상품입니다.
					openDialogAlert( $err_msg, 400,140,'parent');
					exit;
				}
				// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
				if( $goods['event']['event_goodsStatus'] === true ){
					// 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b -
					$err_msg	= getAlert('oc014');
					$err_msg	.= addslashes($goods['goods_name']);
					openDialogAlert($err_msg, 400,140,'parent');
					exit;
				}


				# 필수옵션 및 재고 체크
				$chk_result	= $this->cartmodel->chk_cart_ver_0_1($goodsData);
				if	(!$chk_result['status']){
					openDialogAlert($chk_result['errorMsg'], 400, 140, 'parent', '');
					exit;
				}else{

					# 재매칭일때 주문 수량 만큼만 변경 @2015-08-05 pjm
					if($cart_table == "rematch" || $cart_table == "reorder"){

						$seloptprefix			= trim($aPostParams['select_option_prefix']);
						$seloptsuffix			= trim($aPostParams['select_option_suffix']);
						$optionEa				= $aPostParams[$seloptprefix.'optionEa'.$seloptsuffix];
						$suboptionEa			= $aPostParams[$seloptprefix.'suboptionEa'.$seloptsuffix];

						foreach($optionEa as $k=>$opt){
							foreach($suboptionEa[$k] as $sub) $subopt[] = $sub;
						}
						$this->cartmodel->rematch_ea_check($optionEa,$subopt,$goodsData);
					}

					if($cart_table == "person")	$result	= $this->personcartmodel->add_cart_ver_0_1($goodsData);
					else						$result	= $this->cartmodel->add_cart_ver_0_1($goodsData);

					$mode			= $result['mode'];
					$member_seq		= $result['member_seq'];
					$goods_seq		= $result['goods_seq'];
					$cart_seq		= $result['cart_seq'];
				}

			}

		}else{

			if(!$aPostParams['suboptionTitle']) $aPostParams['suboptionTitle'] = array();
			foreach($aPostParams['suboption_title_required'] as $required_title){
				if( !in_array($required_title,$aPostParams['suboptionTitle']) ){
					//옵션은 필수입니다.
					openDialogAlert($required_title . " ".getAlert('oc015'),400,140,'parent',"");
					exit;
				}
			}

			$cart_option_seq = (int) $aPostParams['cart_option_seq'];

			if($cart_table == "person")
				$data_cart = $this->personcartmodel->get_cart_by_cart_option($cart_option_seq);
			else
				$data_cart = $this->cartmodel->get_cart_by_cart_option($cart_option_seq);

			$goods_seq	= (int) $data_cart['goods_seq'];
			$inputs		= $this->goodsmodel->get_goods_input($goods_seq);

		/* 선택상품 상태, 재고 체크 시작 @2015-08-25 pjm */
			$goods				= $this->goodsmodel->get_goods($goods_seq);

			## 변경 옵션 정보
			$tmp_num				= 0;
			$new_option				= $aPostParams['option'][$tmp_num];
			$new_optionTitle		= $aPostParams['optionTitle'][$tmp_num];
			$new_optionEa			= $aPostParams['optionEa'][$tmp_num];
			$new_suboption			= $aPostParams['suboption'][$tmp_num];
			if($new_suboption){
				$new_suboptionTitle	= $aPostParams['suboptionTitle'][$tmp_num];
				$new_suboptionEa	= $aPostParams['suboptionEa'][$tmp_num];
			}
			$new_inputValue		= $aPostParams['inputsValue'][$tmp_num];
			if($new_inputValue){
				$new_inputTitle		= $aPostParams['inputsTitle'][$tmp_num];
				$new_inputType		= $aPostParams['inputsType'][$tmp_num];
			}

			// 상품상태 체크
			if($goods['goods_status'] != 'normal'){
				// 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b - %s
				if	($goods['goods_status'] == 'unsold'){
					$err_msg	= addslashes($goods['goods_name']). getAlert('oc016'); //은 판매중지 상품입니다.
				}else{
					$err_msg = getAlert('oc017');
					$err_msg	.= addslashes($goods['goods_name']);
				}
				openDialogAlert( $err_msg, 400,140,'parent');
				exit;
			}
			// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
			if( $goods['event']['event_goodsStatus'] === true ){
				// 재고품절 이유로 구매불가한 아래의 상품은 제외되었습니다.%b다시 확인해 주세요.%b - %s
				$err_msg	= getAlert('oc018');
				$err_msg	.= addslashes($goods['goods_name']);
				openDialogAlert( $err_msg, 400,140,'parent');
				exit;
			}

			// 필수 옵션 재고 체크
			$chk	= check_stock_option($goods_seq, $new_option[0], $new_option[1],
											$new_option[2], $new_option[3],$new_option[4],
											$new_optionEa, $cfg['order'], 'view_stock');

			if	( $chk['stock'] < 0 ){
				//구매 가능한 필수옵션(재고부족)이 없습니다.
				$err_msg	= getAlert('oc019');
				$err_msg	.= addslashes($goods['goods_name']);
				openDialogAlert( $err_msg, 400,140,'parent');
				exit;
			}

			// 필수 추가구성옵션 재고 체크
			if	($new_suboption){
				foreach($new_suboption as $k=>$suboption){

					$chk	= false;
					$chk	= check_stock_suboption($goods_seq, $new_suboptionTitle[$k],
													$suboption, $new_suboptionEa[$k],
													$cfg['order'], 'view_stock');
					if	(!$chk || $chk['stock'] < 0 ){
						//필수 추가구성옵션 " . $new_suboptionTitle[$k] . "을(를) 구매할(재고부족) 수 없습니다.
						openDialogAlert( getAlert('oc020',$new_suboptionTitle[$k]), 400,140,'parent');
						exit;
					}
				}
			}
		/* 선택상품 상태, 재고 체크 종료 @2015-08-25 pjm */

			if( isset($aPostParams['inputsValue']) && is_array($aPostParams['inputsValue'][0])){
				// 2014-12-18 옵션 개편 후 (ocw)
				foreach($inputs as $key_input => $data_input){
					foreach($aPostParams['inputsValue'][0] as $k=>$v){
						if($data_input['input_require'] == 1 && !$aPostParams['inputsValue'][$key_input][$k]){
							//옵션은 필수입니다.
							openDialogAlert(addslashes($aPostParams['inputsTitle'][$key_input][$k]) . " ".getAlert('oc021'),400,140,'parent',"");
							exit;
						}elseif($data_input['input_require'] == 1){
							$inputs_required = true;
						}
					}
				}
			}else{
				// 2014-12-18 옵션 개편 전 (ocw)
				$file_num	= 0;
				foreach($inputs as $key_input => $data_input){

					$aPostParams['inputsValue'][$key_input] = trim( $aPostParams['inputsValue'][$key_input] );
					if( $data_input['input_require'] == 1 && !$aPostParams['inputsValue'][$key_input] && $data_input['input_form'] != 'file' ){
						openDialogAlert(addslashes($data_input['input_name']) . " ".getAlert('oc021'),400,140,'parent',"");
						exit;
					}else if( $data_input['input_require'] == 1 && $data_input['input_form'] == 'file' && !$_FILES['inputsValue']['tmp_name'][$file_num] && !$aPostParams['inputsValue'][$key_input]){
						openDialogAlert(addslashes($data_input['input_name']) . " ".getAlert('oc021'),400,140,'parent',"");
						exit;
					}elseif($data_input['input_require'] == 1){
						$inputs_required = true;
					}

					if( $data_input['input_form'] == 'file' ){
						$file_num++;
					}
				}
			}

			if($cart_table == "person")
				$data_cart_option = $this->personcartmodel->get_cart_option($data_cart['cart_seq']);
			else
				$data_cart_option = $this->cartmodel->get_cart_option($data_cart['cart_seq']);

			foreach($data_cart_option as $row){
				if( $row['cart_option_seq']==$cart_option_seq) $shipping_method = $row['shipping_method'];
			}

			if($cart_table == "person"){
				$this->personcartmodel->delete_cart_option($cart_option_seq,'modify');
				$this->personcartmodel->insert_cart_alloption($data_cart['cart_seq'],$inputs,$shipping_method);
			}else{
				$this->cartmodel->delete_cart_option($cart_option_seq,'modify');
				$this->cartmodel->insert_cart_alloption($data_cart['cart_seq'],$inputs,$shipping_method);
			}
		}

		if	($this->is_adminOrder){
			$callback = "";

			if($aPostParams['mode'] == "tmp"){
				$callback .= "parent.goods_select_close();parent.cart();";
			}else{
				$callback .= "parent.cart('".$cart_table."');";
			}
			//상품을 변경하였습니다.
			openDialogAlert(getAlert('oc024'),400,140,'parent',$callback);
		}else{
			//장바구니 상품을 변경하였습니다.
			openDialogAlert(getAlert('oc025'),400,140,'parent',"parent.location.reload();");
		}
	}

	// 주문 결제 :: 2017-05-30 lwh 최종 수정
	public function settle(){
		$settle_alerts = array();

		// 기본값 세팅
		$applypage		= 'order';
		$gift_categorys	= array();
		$gift_goods		= array();
		$members		= "";
		$mode			= "cart";
		$person_seq		= "";
		$mem_seq		= (int) $_GET['member_seq'];

		// 기본 언어 설정에 따라 기본 배송 국가 지정
		$nationGet = $this->input->get('nation');
		if ( ! $nationGet && $this->config_system['default_nation']) {
			$aDefaultNation = explode("(", $this->config_system['default_nation']);
			$sDefaultNation = str_replace(')', '', $aDefaultNation[1]);
			$_GET['nation'] = $sDefaultNation;
		}

		if	( isset($_GET['mode']) )	$mode	= $_GET['mode'];
		if	( isset($_GET['person_seq']) )	$person_seq	= $_GET['person_seq'];
		// 크롬 80 브라우저 예외처리 :: 2020-02-03 lwh
		if	( !check_ssl_protocol() && strpos($_SERVER['HTTP_USER_AGENT'],'Chrome/80') !== false ){
		    $settle_alerts[] = '크롬 브라우저(버전 80이상)에서 결제시 오류가 발생할 수 있습니다.\n정상적인 결제를 위해 다른 브라우저를 이용해주세요.';
		}

		// 기본 로드
		$this->load->model('couponmodel');
		$this->load->model('membermodel');
		$this->load->helper('member');
		if	($person_seq != "")	$this->load->model('personcartmodel');
		else					$this->load->model('cartmodel');
		$cfg['order'] = ($this->cfg_order) ? $this->cfg_order : config_load('order');
		$cfg_reserve	= ($this->reserves) ? $this->reserves : config_load('reserve');
		// 마일리지 포인트 최대 금액 추출
		$cfg_reserve['autoemoney_review'] = max([$cfg_reserve['autoemoney_video'],$cfg_reserve['autoemoney_photo'],$cfg_reserve['autoemoney_review']]);
		$cfg_reserve['autopoint_review'] = max([$cfg_reserve['autopoint_video'],$cfg_reserve['autopoint_photo'],$cfg_reserve['autopoint_review']]);
		$this->template->assign('cfg_reserve',$cfg_reserve);

		// 장바구니 옵션 번호 리스트
		$binding_cart_option_seq_list = $this->input->post('cart_option_seq');
		
		// 장바구니 옵션 번호 유효성 검사
		foreach ($binding_cart_option_seq_list as $cart_option_seq) {
			if (!is_numeric($cart_option_seq)) {
				show_error("장바구니 옵션 일련번호 항목은 숫자 이외에는 입력할 수 없습니다.");
			}
		}

		// 장바구니 선택 주문 시
		if ($mode == 'choice' && count($binding_cart_option_seq_list) > 0) {
			// 장바구니 옵션 번호의 장바구니 번호 추출
			$cart_seq_list = $this->db->select("cart_seq")
			->from("fm_cart_option")
			->where_in('cart_option_seq', $binding_cart_option_seq_list)
			->get()
			->result_array();

			// 장바구니 번호 쿼리 바인딩 준비 작업
			$binding_cart_seq_list = [];
			foreach ($cart_seq_list as $cart_seq) {
				$binding_cart_seq_list[] = $cart_seq['cart_seq'];
			}

			// 장바구니 관련 업데이트 처리
			if (count($binding_cart_seq_list) > 0) {
				// 장바구니 구분 변경
				$this->db->set('distribution', 'choice')->where_in('cart_seq', $binding_cart_seq_list)->update("fm_cart");
				// 장바구니 옵션 선택주문구분 변경
				$this->db->set('choice', 'n')->where_in('cart_seq', $binding_cart_seq_list)->update("fm_cart_option");
				$this->db->set('choice', 'y')->where_in('cart_option_seq', $binding_cart_option_seq_list)->update("fm_cart_option");
			}
		}

		// 바로구매 시 장바구니 정리
		if($mode == "direct")	$this->cartmodel->delete_for_settle();

		if($person_seq == ""){
			$this->template->assign('firstmallcartid',session_id());
		}

		$cart_promotioncode = $this->session->userdata('cart_promotioncode_'.session_id());
		$this->template->assign('cart_promotioncode' , $cart_promotioncode);

		// 회원 정보 추출 :: 2016-08-01 lwh
		if($this->userInfo['member_seq']){
			$members = $this->membermodel->get_member_data($this->userInfo['member_seq']);
			if($members['user_name'] && $members['email'] && $members['cellphone']){
				$members['order_info_full'] = true;
			}else if(!$members['user_name'] && !$members['email'] && !$members['cellphone']){
				$members['order_info_none'] = true;
			}

			// 회원 이름명 OR 업체명 20자 제한
			$members['user_name'] = check_member_name($members['user_name']);

			$phone = $this->chkPhoneDash($members['phone']);
			$tmp = explode('-',$phone);
			foreach($tmp as $k => $data){
				$key = 'phone'.($k+1);
				$members[$key] = $data;
			}

			$cellphone = $this->chkPhoneDash($members['cellphone']);
			$tmp = explode('-',$cellphone);
			foreach($tmp as $k => $data){
				$key = 'cellphone'.($k+1);
				$members[$key] = $data;
			}

			$tmp = explode('-',$members['zipcode']);
			foreach($tmp as $k => $data){
				$key = 'zipcode'.($k+1);
				$members[$key] = $data;
			}

			// 회원인 경우 최근 배송 메세지 추출 :: 2016-11-03 lwh
			$this->load->model("ordermodel");
			$lately_msg = $this->ordermodel->get_ship_message($this->userInfo['member_seq'],'3');
			if($lately_msg) $this->template->assign('lately_msg', $lately_msg);
		}

		if	($person_seq > 0){
			$cart	= $this->personcartmodel->catalog($mem_seq, $person_seq);
			if	($cart['person']['use_reserve']){
				$person_use_reserve						= 1;
				$cfg_reserve['default_reserve_limit']	= $cart['person']['reserve_limit'];
			}
		}else{
			$cart	= $this->cartmodel->catalog();
		}

		//#0000 2018-09-10 ycg 유효기간이 지난 개인결제 수단 접근 차단
		$dead_line = date('Y-m-d',strtotime($cart['person']['regist_date']."+7 day"))." 00:00:00'";
		$today = date('Y-m-d',strtotime("Now"));
		if($today>$dead_line){
			//구매할 상품이 없습니다.
			pageLocation('/',getAlert('os111'));
			exit;
		}

		if	( !$cart['list'] ){
			//구매할 상품이 없습니다.
			pageLocation('/',getAlert('os111'));
			exit;
		}

		// KICC 휴대폰 결제 복합 과세 미지원 처리
		if($cart['taxtype'] == 'mix' && $this->config_system['pgCompany'] == 'kicc'){
			$this->template->assign('cellphonePayDisabled', 'y');
		}else{
			$this->template->assign('cellphonePayDisabled', 'n');
		}

		# 비교통화 계산 함수 include
		$this->template->include_('showCompareCurrency');

		// 상품별 주문배송방법 선택
		$this->displaymode = 'cart';
		$cart_calculate = $this->calculate('', $person_seq);
		$cart['shipping_group_price'] = $this->shipping_group_cost;
		$cart['shipping_group_policy'] = $this->shipping_group_policy;
		$cart['shipping_price']					= $this->shipping_price;//@2016-08-01 ysm

		//----> sale library 적용
		$param['cal_type']				= 'list';
		$param['total_price']			= $cart['total_sale_price'];
		$param['reserve_cfg']			= $cfg_reserve;
		$param['member_seq']			= $this->userInfo['member_seq'];
		$param['group_seq']				= $this->userInfo['group_seq'];
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		$cart['total']			= 0;
		$cart['total_ea']		= 0;
		$cart['total_sale']		= 0;
		$cart['total_price']	= 0;
		$cart['total_reserve']	= 0;
		$cart['total_point']	= 0;
		$total_ea				= 0;
		$category				= array();
		$goodscancellation		= false;
		$goodsadmin				= true;
		$possible_pay			= array();
		$is_coupon				= false;
		$is_goods				= false;
		$is_direct_store		= false;
		$is_international_shipping	= false; // 해외배송여부
        
		foreach($cart['list'] as $key => $data){
			$cart_seq = $data['cart_seq'];
			## 연결상품 검증
			if($data['package_error'] && $data['package_yn'] == 'y'){
				//$err_msg	= '↓아래 상품은 현재 구매할 수 없는 상품입니다.';
				//$err_msg	.= '\\n'.addslashes($data['goods_name']);
				$err_msg = getAlert('os123',addslashes($data['goods_name']));

				pageBack($err_msg);
				exit;
			}

			// 성인상품 인증처리 :: 2015-03-13 lwh
			$adult_auth	= $this->session->userdata('auth_intro');
			if($adult_auth['auth_intro_yn'] == '' && $data['adult_goods'] == 'Y' && (!$this->managerInfo && !$this->providerInfo)){
				$return_url	= "/member/adult_auth?return_url=".urldecode($_SERVER['REQUEST_URI']);
				//성인인증이 필요한 상품이 포함되어 있습니다.\n성인인증페이지로 이동합니다.
				alert(getAlert('os112'));
				pageRedirect($return_url,'');
				exit;
			}

			// 해외배송여부
			if($data['option_international_shipping_status'] == 'y'){
				$is_international_shipping	= true;
			}

			// 청약철회상품
			if	($data['cancel_type'] == 1)			$goodscancellation	= true;

			// 본사상품
			if	($data['provider_seq'] != 1)		$goodsadmin = false;

			// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
			if( $data['event']['event_goodsStatus'] === true ){
				//↓아래 상품은 단독이벤트 기간에만 구매가 가능합니다.
				$err_msg	= getAlert('os113');
				$err_msg	.= '\\n'.addslashes($goods['goods_name']);
				pageBack($err_msg);
				exit;
			}

			// 티켓상품 구매가능 여부 체크
			if	($data['goods_kind'] == 'coupon') {
				if($data['cart_option_seq']){
					// 티켓상품 기간체크
					$chkcouponexpire	= check_coupon_date_option($data['goods_seq'], $data['option1'], $data['option2'], $data['option3'], $data['option4'], $data['option5']);
					if( $chkcouponexpire['couponexpire'] === false ){
						$this->cartmodel->delete_option($data['cart_option_seq'],'');//해당상품의 옵션제거
						$err_msg = getAlert('os114',array(
							$chkcouponexpire['social_start_date'],
							$chkcouponexpire['social_end_date'],
							addslashes($goods['goods_name'])
						));

						if($opttitle) $err_msg .= "(".$opttitle.")";
						pageReload($err_msg);
					}
				}

				if($data['cart_suboption_seq']){
					// 티켓상품 기간체크
					$chkcouponexpire = check_coupon_date_suboption($data['goods_seq'], $data['suboption_title'], $data['suboption']);
					if( $chkcouponexpire['couponexpire'] === false ){
						$this->cartmodel->delete_option('',$data['cart_suboption_seq']);//해당상품의 옵션제거

						$err_msg = getAlert('os114',array(
							$chkcouponexpire['social_start_date'],
							$chkcouponexpire['social_end_date'],
							addslashes($goods['goods_name'])
						));

						if($opttitle) $err_msg .= "(".$opttitle.")";
						pageReload($err_msg);
					}
				}
			}

			// 입점사별 구매금액 합계
			$provider_price[$data['provider_seq']]			+= $data['price']*$data['ea'];

			// 배송그룹별 구매금액 합계 :: 2016-11-11 lwh
			$ship_grp_price[$data['shipping_group_seq']]	+= $data['price']*$data['ea'];

			// 입점사명 추출 :: 2017-05-19 lwh
			if ( $data['provider_seq'] != 1 ) $arr_provider_name[$data['provider_seq']]		= $data['provider_name'];

			list($price,$data['reserve'])	= $this->goodsmodel->get_goods_option_price($data['goods_seq'], $data['option1'], $data['option2'], $data['option3'], $data['option4'], $data['option5']);

			// 옵션 노출/미노출 체크
			$view_chk	= check_view_option($data['goods_seq'], $data['option1'],
											$data['option2'], $data['option3'],
											$data['option4'], $data['option5']);

			if	($view_chk){
				$this->cartmodel->delete_option($data['cart_option_seq'],'');
				pageReload();
			}

			// 재고 체크
			$chk	= check_stock_option($data['goods_seq'], $data['option1'], $data['option2'], $data['option3'], $data['option4'], $data['option5'], $data['ea'], $cfg['order'], 'view_stock');
			if( $chk['stock'] < 0 ){
				$opttitle	= '';
				if($data['option1']) $opttitle .= $data['option1'];
				if($data['option2']) $opttitle .= ' '.$data['option2'];
				if($data['option3']) $opttitle .= ' '.$data['option3'];
				if($data['option4']) $opttitle .= ' '.$data['option4'];
				if($data['option5']) $opttitle .= ' '.$data['option5'];

				$this->cartmodel->delete_option($data['cart_option_seq'],'');//해당상품의 옵션제거
				pageReload();
			}

			// 추가옵션 재고 체크
			if($data['cart_suboptions']){
				foreach($data['cart_suboptions'] as $k => $cart_suboption){
					## 연결상품 체크
					if($cart_suboption['package_error'] && $data['package_yn_suboption'] == 'y'){
						$err_msg = getAlert('os123',addslashes($data['goods_name']));
						pageReload($err_msg);
					}

					// 추가옵션 노출/미노출 체크
					$view_chk	= check_view_suboption($data['goods_seq'],
													   $cart_suboption['suboption_title'],$cart_suboption['suboption']);

					if	($view_chk){
						$this->cartmodel->delete_option('',$cart_suboption['cart_suboption_seq']);
						pageReload();
					}

					// 입점사별 구매금액 합계
					$provider_price[$data['provider_seq']]	+= $cart_suboption['price']*$cart_suboption['ea'];

					// 배송그룹별 구매금액 합계 :: 2016-11-11 lwh
					$ship_grp_price[$data['shipping_group_seq']] += $cart_suboption['price']*$cart_suboption['ea'];

					// 재고 체크
					$chk = check_stock_suboption($data['goods_seq'], $cart_suboption['suboption_title'], $cart_suboption['suboption'], $cart_suboption['ea'], $cfg['order'], 'view_stock' );
					if( $chk['stock'] < 0 ){
						$opttitle = '';
						$this->cartmodel->delete_option('',$cart_suboption['cart_suboption_seq']);//해당상품의 옵션제거
						pageReload();
					}
				}
			}

			if($this->_is_mobile_agent) {// $this->mobileMode  ||
				if($data["possible_mobile_pay"]){
					$possible_pay[] = explode(",", $data["possible_mobile_pay"]);
				}
			}else{
				if($data["possible_pay"]){
					$possible_pay[] = explode(",", $data["possible_pay"]);
				}
			}

			// 해당 상품의 전체 카테고리
			$category	= array();
			$tmp		= $this->goodsmodel->get_goods_category($data['goods_seq']);
			foreach($tmp as $row)	$category[]		= $row['category_code'];

			//----> sale library 적용
			unset($param, $sales);
			$param['option_type']				= 'option';
			$param['consumer_price']			= $data['consumer_price'];
			$param['price']						= $data['org_price'];
			$param['sale_price']				= $data['price'];
			$param['ea']						= $data['ea'];
			$param['goods_ea']					= $cart['data_goods'][$data['goods_seq']]['ea'];
			$param['option_ea']					= $cart['data_goods'][$data['goods_seq']]['option_ea'];
			$param['category_code']				= $category;
			$param['goods_seq']					= $data['goods_seq'];
			$param['goods']						= $data;
			$this->sale->set_init($param);
			$sales								= $this->sale->calculate_sale_price($applypage);

			//이벤트 쿠폰/코드 사용제한1 @2015-08-13
			if	( $this->sale->goods['event'] ) {
				if( $this->sale->goods['event']['use_coupon'] == 'n' && !in_array($data['goods_seq'],$this->ordernosales_cp) ) {
					$this->ordernosales_cp[$data['goods_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_coupon_shipping'] == 'n' && !in_array($data['shipping']['provider_seq'],$this->ordernosales_cp_sh)  ) {
					$this->ordernosales_cp_sh[$data['shipping']['provider_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_coupon_ordersheet'] == 'n' && !in_array($data['goods_seq'],$this->ordernosales_cp_os)  ) {
					$this->ordernosales_cp_os[$data['goods_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_code'] == 'n' && !in_array($data['goods_seq'],$this->ordernosales_cd)  ) {
					$this->ordernosales_cd[$data['goods_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_code_shipping'] == 'n' && !in_array($data['shipping']['provider_seq'],$this->ordernosales_cd_sh)  ) {
					$this->ordernosales_cd_sh[$data['shipping']['provider_seq']] = $this->sale->goods['event']['event_seq'];
				}
			}

			$data['org_price']					= ($data['consumer_price']) ? $data['consumer_price'] : $data['org_price'];
			$data['sales']						= $sales;
			$data['event_order_cnt']			= $data['event']['event_order_cnt'];
			$data['tot_org_price']				= $data['org_price'] * $data['ea'];
			$data['tot_sale_price']				= $sales['total_sale_price'];
			$data['tot_result_price']			= $sales['result_price'];

			if($this->mobileMode){
				$compare_class = array("layClass"=>"wx140 mlminus100");
			}
			# 비교통화 노출
			$data['tot_result_compare']		= showCompareCurrency('',$sales['result_price'],'return',$compare_class);

			// sale_price가 없을 시 여기서 event까지 계산함
			if	(!$data['event'] && $this->sale->cfgs['event']){
				$data['event']					= $this->sale->cfgs['event'];
				$data['eventEnd']				= $sales['eventEnd'];
			}

			// 마일리지 / 포인트 ( 실결제 금액 기준이기에 여기서 계산 )
			// 상품의 마일리지 / 포인트 계산
			$data['reserve']	= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'],
									$sales['result_price'], $cfg_reserve['default_reserve_percent'],
									$data['reserve_rate'], $data['reserve_unit'], 0);
			$data['point']		= $this->goodsmodel->get_point_with_policy($sales['result_price']);
			// 이벤트 마일리지 / 포인트 계산 ( 장바구니에서 혜택적용할인가를 받아서 쓸 경우만 )
			if	($param['sale_price']){
				$this->sale->cfgs['event']		= $data['event'];
				$data['reserve']				+= $this->sale->event_sale_reserve($sales['result_price']);
				$data['point']					+= $this->sale->event_sale_point($sales['result_price']);
			}
			$data['reserve']					= $data['reserve'] + $sales['tot_reserve'];
			$data['point']						= $data['point'] + $sales['tot_point'];

			// 총 합계
			$cart['total']						+= $data['price']*$data['ea'];
			$cart['total_ea']					+= $data['ea'];
			$cart['total_price']				+= $sales['result_price'];
			$cart['total_reserve']				+= $data['reserve'];
			$cart['total_point']				+= $data['point'];
			$cart['total_sale']					+= $sales['total_sale_price'];

			// 총 할인 목록 노출을 위한 배열 생성
			if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $sale_title){
				$data['tsales']['sale_list'][$sale_type]		= $sales['sale_list'][$sale_type];
				$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
				$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
			}
			$cart['total_sale_list']['shippingcoupon']['title']	= '배송비쿠폰';
			$cart['total_sale_list']['shippingcoupon']['price']	= 0;
			$cart['total_sale_list']['shippingcode']['title']	= '배송비코드';
			$cart['total_sale_list']['shippingcode']['price']	= 0;
			$cart['total_sale_list']['ordersheet']['title']	= '주문서쿠폰';
			$cart['total_sale_list']['ordersheet']['price']	= 0;

			$this->sale->reset_init();
			//<---- sale library 적용


			// 추가구성 옵션
			if	($data['cart_suboptions']){
				foreach($data['cart_suboptions'] as $k => $subdata){
					//----> sale library 적용
					unset($param, $sales);
					$param['option_type']				= 'suboption';
					$param['sub_sale']					= $subdata['sub_sale'];
					$param['consumer_price']			= $subdata['consumer_price'];
					$param['price']						= $subdata['price'];
					$param['sale_price']				= $subdata['price'];
					$param['ea']						= $subdata['ea'];
					$param['goods_ea']					= $cart['data_goods'][$data['goods_seq']]['ea'];
					$param['category_code']				= $category;
					$param['goods_seq']					= $data['goods_seq'];
					$param['goods']						= $data;
					$this->sale->set_init($param);
					$sales								= $this->sale->calculate_sale_price($applypage);

					$subdata['sales']					= $sales;
					$subdata['org_price']				= ($subdata['consumer_price']) ? $subdata['consumer_price'] : $subdata['price'];

					// 마일리지 / 포인트
					$subdata['reserve']					= $this->goodsmodel->get_reserve_with_policy($data['sub_reserve_policy'], $sales['result_price'], $cfg_reserve['default_reserve_percent'], $subdata['reserve_rate'], $subdata['reserve_unit'], $subdata['reserve']);
					$subdata['point']					= $this->goodsmodel->get_point_with_policy($sales['result_price']);
					$subdata['reserve']					= $subdata['reserve'] + $sales['tot_reserve'];
					$subdata['point']					= $subdata['point'] + $sales['tot_point'];

					$data['tot_org_price']				+= $subdata['org_price'] * $subdata['ea'];
					$data['tot_sale_price']				+= $sales['total_sale_price'];
					$data['tot_result_price']			+= $sales['result_price'];

					# 비교통화 노출
					$data['tot_result_compare']			= showCompareCurrency('',$sales['result_price'],'return',array("layClass"=>"wx140 mlminus100"));

					$cart['total']						+= $subdata['price']*$subdata['ea'];
					$cart['total_ea']					+= $subdata['ea'];
					$cart['total_price']				+= $sales['result_price'];
					$cart['total_reserve']				+= $subdata['reserve'];
					$cart['total_point']				+= $subdata['point'];
					$cart['total_sale']					+= $sales['total_sale_price'];
					if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $sale_title){
						$data['tsales']['sale_list'][$sale_type]		+= $sales['sale_list'][$sale_type];
						$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
						$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
					}
					$this->sale->reset_init();
					//<---- sale library 적용

					$data['cart_suboptions'][$k]	= $subdata;
				}
			}

			$cart['list'][$key]		= $data;
		}

		// 홈페이지 내에서 구매 cart 의 partner_id 초기화
		$this->load->library('cartlib');
		$this->cartlib->setCartMarking($cart_seq, null);

		if($this->mobileMode) $compare_class= array('layClass'=>'fx14 wx140 black mlminus100');
		$cart['total_price']			+= get_cutting_price(array_sum($cart['shipping_price']));
		$cart['total_price_compare']	= showCompareCurrency('',$cart['total_price'],'return',$compare_class);	# 총 결제금액 비교통화 노출

		### GIFT
		foreach($cart['data_goods'] as $goods_seq => $data){
			$gift_goods[] 			= $goods_seq;
			$gift_provider[]		= $data['provider_seq'];
			$gift_shipping[]		= $data['shipping_group_seq'];
			$gift['goods_seq']		= $goods_seq;
			$gift['provider_seq']	= $data['provider_seq'];
			$gift['ea']				= $data['ea'];
			$gift['tot_price']		= $data['price'];
			$gift_loop[]			= $gift;
			foreach($data['r_category'] as $category_code){
				$gift_categorys[] = $category_code;
				$category[] = $category_code;
			}
		}

		$shipping = use_shipping_method();

		// 해외배송불가카테고리 체크
		foreach($shipping[1] as $i=>$row){
			foreach($row['exceptCategory'] as $exceptCategory){
				if(in_array($exceptCategory,$category)){
					unset($shipping[1][$i]);
				}
			}
		}
		if(!count($shipping[1])) unset($shipping[1]);

		if( is_array($shipping) ){
			foreach($shipping as $key => $data){
				if($data) $shipping_cnt[$key] = count($data);
			}
		}

		$shipping_policy['policy'] 	= $shipping;
		$shipping_policy['count'] 	= $shipping_cnt;

		// 총 배송비
		if	($cart['shipping_group_price'])foreach($cart['shipping_group_price'] as $key => $shipping){
			if	($shipping['goods'] > 0){
				$cart['shipping_group_price']['goods_delivery']	+= $shipping['goods'];
			}
			if	($shipping['shop'] > 0){
				$cart['shipping_group_price']['basic_delivery']	+= $shipping['shop'];
			}
		}

		## pg설정 load
		$this->load->helper('payment');
		$cfg_payment = get_payment($this->_is_mobile_agent, $possible_pay, $cart['total_price']);
		$bank				= $cfg_payment['bank'];
		$payment			= $cfg_payment['payment'];
		$payment_gateway	= $cfg_payment['payment_gateway'];
		$payment_count		= $cfg_payment['payment_count'];
		$escrow				= $cfg_payment['escrow'];
		if( $cfg_payment['escrow_count'] > 0 ){
			$escrow_view = true;
		}

		// 카카오페이 추가 :: 2015-02-11 lwh
		if( $payment['kakaopay'] ){

			if($this->config_system['not_use_kakao'] == 'n'){ // 카카오페이 구버전 스크립트 오출 에러로 처리 :: 2019-01-25 lkh
				// 추후 삭제 예정 - 구버전 config START :: 2017-12-11 lwh
				require("./pg/kakaopay/conf_inc.php");

				$kakaopay_config['CNSPAY_WEB_SERVER_URL']	= $CNSPAY_WEB_SERVER_URL;
				$kakaopay_config['targetUrl']				= $targetUrl;
				$kakaopay_config['msgName']					= $msgName;
				$kakaopay_config['CnsPayDealRequestUrl']	= $CnsPayDealRequestUrl;

				$this->template->assign($kakaopay_config);
				$this->template->assign('not_use_kakao',$this->config_system['not_use_kakao']);
				// 추후 삭제 예정 - 구버전 config END :: 2017-12-11 lwh
			}

			// 자주 변경 될수 있으므로 스킨에서 제외 요청 :: 2015-03-11 lwh
			$kakaopay_html	= "<div class='kakaopay_text' style='border:1px solid #ccc; padding:8px 10px;'>카카오톡 앱에서 카카오페이 가입(최초 1회 본인명의휴대폰에서 본의명의 카드등록) 후 비밀번호 입력만으로 간편하고 안전하게 결제하실 수 있는 모바일 결제수단입니다.</div>";

			$this->template->assign('kakaopay_html',$kakaopay_html);
		}

		// 페이코 추가 :: 2018-08-23 lwh
		if( $payment['payco'] ){
			$payco_config	= config_load('payco');
			if($payco_config['use_set'] == 'test'){
				if(!$this->managerInfo['manager_seq'])		unset($payment['payco']);
			}

			$payco_html	= "<div style='border:1px solid #CBCBCB; padding:5px; width:95%;'><b>PAYCO 간편결제 안내</b><br/>PAYCO는 온/오프라인 쇼핑은 물론 송금, 멤버십 적립까지 가능한 통합 서비스입니다.<br/>휴대폰과 카드 명의자가 동일해야 결제 가능하며, 결제금액 제한은 없습니다.<br/>&nbsp;- 지원카드: 모든 국내 신용/체크카드</div>";
			$this->template->assign('payco_html',$payco_html);
		}

		if($payment_count==0){
			//결제방법이 존재하지 않습니다.\\n\\n쇼핑몰 고객센터에 문의 해 주세요.
			pageBack(getAlert('os116'));
			exit;
		}

		if($this->_is_mobile_agent) {// $this->mobileMode  ||
			$this->template->assign('mobile',1);
		}

		// 사업자 회원일 경우 업체명->이름, 사업장주소->주소, 담당자전화번호->전화번호, 핸드폰->핸드폰
		if($members['business_seq']){
			//$members['user_name'] = $members['bname'];
			$members['address_type']		= $members['baddress_type'];
			$members['address'] = $members['baddress'];
			$members['address_street']	= $members['baddress_street'];
			$members['address_detail'] = $members['baddress_detail'];

			$bphone = $this->chkPhoneDash($members['bphone']);
			$tmp = explode('-',$bphone);
			foreach($tmp as $k => $data){
				$key = 'phone'.($k+1);
				$members[$key] = $data;
			}

			$bcellphone = $this->chkPhoneDash($members['bcellphone']);
			$tmp = explode('-',$bcellphone);
			foreach($tmp as $k => $data){
				$key = 'cellphone'.($k+1);
				$members[$key] = $data;
			}

			$tmp = explode('-',$members['bzipcode']);
			foreach($tmp as $k => $data){
				$key = 'zipcode'.($k+1);
				$members[$key] = $data;
			}
		}

		// 통합약관 적용. 기본 치환코드 처리 포함
		$member = chkPolicyInfo();

		$arrBasic = ($this->config_basic)?$this->config_basic:config_load('basic');
		if($person_seq == ""){
			if( defined('__ISUSER__') != true ) {
				$privacy['policy_agreement'] 	= $member['policy_agreement'];
				$privacy['policy_privacy'] 		= $member['policy_privacy'];
				$privacy['agreement'] 			= $member['agreement'];
				$privacy['privacy'] 			= $member['privacy'];
				$privacy['policy'] 				= $member['policy'];
				//20170920 shopName -> companyName 으로 변경(db쪽에 shopName 치환코드가 있는 관계로 소스에서만 설정) ldb
				// 서비스 이용약관 동의 :: 2017-05-19 lwh
				/*
				$privacy['policy_agreement'] = str_replace("{shopName}",$arrBasic['companyName'],$member['policy_agreement']);

				//개인정보 관련 문구개선 @2016-09-06 ysm
				$member['policy_privacy'] = str_replace("{책임자명}",$arrBasic['member_info_manager'],$member['policy_privacy']);
				$member['policy_privacy'] = str_replace("{책임자담당부서}",$arrBasic['member_info_part'],$member['policy_privacy']);
				$member['policy_privacy'] = str_replace("{책임자직급}",$arrBasic['member_info_rank'],$member['policy_privacy']);
				$member['policy_privacy'] = str_replace("{책임자연락처}",$arrBasic['member_info_tel'],$member['policy_privacy']);
				$member['policy_privacy'] = str_replace("{책임자이메일}",$arrBasic['member_info_email'],$member['policy_privacy']);
				
				$privacy['policy_privacy'] = str_replace("{domain}",$arrBasic['domain'],str_replace("{shopName}",$arrBasic['companyName'],$member['policy_privacy']));

				//스킨 패치 안한 고객 용
				$privacy['agreement'] = str_replace("{shopName}",$arrBasic['companyName'],$member['agreement']);

				$member['privacy'] = str_replace("{책임자명}",$arrBasic['member_info_manager'],$member['privacy']);
				$member['privacy'] = str_replace("{책임자담당부서}",$arrBasic['member_info_part'],$member['privacy']);
				$member['privacy'] = str_replace("{책임자직급}",$arrBasic['member_info_rank'],$member['privacy']);
				$member['privacy'] = str_replace("{책임자연락처}",$arrBasic['member_info_tel'],$member['privacy']);
				$member['privacy'] = str_replace("{책임자이메일}",$arrBasic['member_info_email'],$member['privacy']);
				$privacy['privacy'] = str_replace("{domain}",$arrBasic['domain'],str_replace("{shopName}",$arrBasic['companyName'],$member['privacy']));

				$privacy['policy'] = str_replace("{domain}",$arrBasic['domain'],str_replace("{shopName}",$arrBasic['companyName'],$member['policy']));
				*/
			}
		}

		if( $goodscancellation  === true ) { //청약철회상품이 있는경우
			//$privacy['policy_cancellation'] = str_replace("{domain}",$arrBasic['domain'],str_replace("{shopName}",$arrBasic['companyName'],$member['policy_cancellation']));
			$privacy['policy_cancellation']	= $member['policy_cancellation'];
			$privacy['goodscancellation'] 	= $goodscancellation;
			
			//스킨 패치 안한 고객 용
			//$arrOrder = ($this->cfg_order) ? $this->cfg_order : config_load('order');
			//$privacy['cancellation'] = str_replace("{domain}",$arrBasic['domain'],str_replace("{shopName}",$arrBasic['companyName'],$arrOrder['cancellation']));
			$privacy['cancellation'] = $member['cancellation'];
		}

		// 개인정보 취급위탁에 대한 동의 
		if( (serviceLimit('H_AD') && count($arr_provider_name)) > 0 || !serviceLimit('H_AD')) { //입점 상품이 있을 경우
			if($member['delegationYN'] == 'Y'){
				//$privacy['policy_delegation'] = str_replace("{shopName}",$arrBasic['companyName'],$member['policy_delegation']);
				$privacy['policy_delegation'] = $member['policy_delegation'];
				$privacy['policy_delegation'] = str_replace("{sellerName}",implode(', ',$arr_provider_name),$privacy['policy_delegation']);
			}
		}

		/**
		 * [주문]개인정보 제3자 제공에 대한 동의
		 * 입점사는 policy_third_party / 일반몰은 policy_third_party_normal
		 * 입점사는 무조건 사용 , 일반몰은 사용 안함이면 unset
		 */
		$policy_third_party = serviceLimit('H_AD') === true ? $member['policy_third_party'] : $member['policy_third_party_normal'];
		$privacy['policy_third_party'] = str_replace('{sellerName}', implode(', ', $arr_provider_name), $policy_third_party);
		if (!serviceLimit('H_AD') && $member['thirdPartyYN'] == 'N') {
			unset($privacy['policy_third_party']);
		}

		//$privacy['policy_order'] = str_replace("{domain}",$arrBasic['domain'],str_replace("{shopName}",$arrBasic['companyName'],$member['policy_order']));
		$privacy['policy_order'] = $member['policy_order'];
		$this->template->assign($privacy);

		//2017-05-30 jhs 크로스 브라우징 결제모듈 추가
		$payConfig = config_load($this->config_system['pgCompany']);
		$naxCheck = $payConfig["nonActiveXUse"];
		$this->template->assign('naxCheck',$naxCheck);

		//개인결제시
		if($person_seq != ""){

			$query = $this->db->query("select * from fm_person where person_seq='".$person_seq."'");
			$res = $query->row_array();

			//#20069 2019-02-08 ycg 개인 결제 기한 표시
			$date_format = array("year","month","day");
			$deadline['start_date'] = explode('-',date("Y-m-d", strtotime($res['regist_date'])));
			$deadline['end_date']  = explode('-',date("Y-m-d", mktime(0, 0, 0, date("m",strtotime($res['regist_date'])), date("d",strtotime($res['regist_date']))+7, date("Y",strtotime($res['regist_date'])))));
			foreach($deadline as $key => $val){
					$res[$key] = array_combine($date_format, $val);
			}

			if($this->userInfo['member_seq'] != $res['member_seq']){
				//결제 권한이 없습니다.
				echo "<script>alert('".getAlert('os117')."'); history.back();</script>";
				exit;
			}

			$members['user_name'] = $res['order_user_name'];
			$members['email'] = $res['order_email'];
			$tmp = explode('-',$res['order_phone']);
			foreach($tmp as $k => $data){
				$key = 'phone'.($k+1);
				$members[$key] = $data;
			}

			$tmp = explode('-',$res['order_cellphone']);
			foreach($tmp as $k => $data){
				$key = 'cellphone'.($k+1);
				$members[$key] = $data;
			}

			if(strpos($res["pay_type"], 'bank') === false){
				$payment["bank"] = '';
			}else{
				$payment["bank"] = 'bank';
			}

			if(strpos($res["pay_type"], 'card') === false){
				$payment["card"] = '';
			}else{
				$payment["card"] = 'card';
			}

			// 카카오 페이로 인한 추가 :: 2015-02-11 lwh
			if(strpos($res["pay_type"], 'kakaopay') === false) $payment["kakaopay"] = ''; else 	$payment["kakaopay"] = 'kakaopay';
			if(strpos($res["pay_type"], 'account') === false) 	$payment["account"] = '';	else 	$payment["account"] = 'account';
			if(strpos($res["pay_type"], 'cellphone') === false) $payment["cellphone"] = '';else $payment["cellphone"] = 'cellphone';
			if(strpos($res["pay_type"], 'virtual') === false) 		$payment["virtual"] = '';		else $payment["virtual"] = 'virtual';
			if(strpos($res["pay_type"], 'payco') === false)		$payment["payco"] = '';		else 	$payment["payco"] = 'payco';
			if(strpos($res["pay_type"], 'paypal') === false)		$payment["paypal"] = '';		else 	$payment["paypal"] = 'paypal';
			if(strpos($res["pay_type"], 'eximbay') === false) 	$payment["eximbay"] = '';	else 	$payment["eximbay"] = 'eximbay';

			$cart['total_sale']		+= get_cutting_price($res['enuri']);

			// 개인결제 시 escrow는 제거
			$escrow			= array();
			$escrow_view	= false;

			$international_shipping_info_path	= str_replace('settle.html', '../goods/_international_shipping_info.html', $this->template_path());
			$this->template->define('INTERNATIONAL_SHIPPING_INFO', $international_shipping_info_path);

			$this->template->assign('person_use_reserve',$person_use_reserve);
			$this->template->assign('personData',$res);
			$this->template->assign('cfg',$cfg);
			$this->template->assign('pg_company',$this->config_system['pgCompany']);
			$this->template->assign('mode',$mode);
			$this->template->assign('members',$members);
			$this->template->assign('enuri',$res['enuri']);
			$this->template->assign('shipping_policy',$shipping_policy);
			$this->template->assign('bank',$bank);
			$this->template->assign('payment',$payment);
			$this->template->assign('escrow',$escrow);
			$this->template->assign('escrow_view',$escrow_view);
			$this->template->assign('settle',true);//현재페이지정보 넘겨줌

		// 일반 결제 시..
		}else{

			//ordermodel 로 함수 정리 2015-05-13 pjm
			$gift = $this->ordermodel->get_gift_event($gift_categorys,$gift_goods,$gift_shipping, $ship_grp_price, $gift_loop,$cart['total']);

			$this->template->assign(array('gift_cnt'=>$gift['gift_cnt'],'gloop'=>$gift['gloop'],'gift_goods_cnt'=>$gift['gift_goods_cnt']));

			if($members["bzipcode"]){
				$business_info["co_new_zipcode"]	= str_replace('-','',$members["bzipcode"]);
				$business_info["co_zipcode"][]		= substr($business_info["co_new_zipcode"],0,3);
				$business_info["co_zipcode"][]		= substr($business_info["co_new_zipcode"],3,3);
			}

			$business_info["bname"] = $members["bname"];
			$business_info["bno"] = $members["bno"];
			$business_info["bCEO"] = $members["bceo"];
			// 거꿀로 저장되어 업태/업종 변경
			$business_info["bstatus"] = $members["bitem"];
			$business_info["bitem"] = $members["bstatus"];

			$business_info["bperson"] = $members["bperson"];
			$business_info["email"] = $members["email"];
			$business_info["bphone"] = ($members["bphone"])? str_replace("-","",$members["bphone"]) : "";
			$business_info["baddress1"] = ($members["baddress_type"] == 'street')?$members["baddress_street"]:$members["baddress"];
			$business_info["baddress2"] = $members["baddress_detail"];
			$business_info["baddress_type"] = $members["baddress_type"];
			$business_info["baddress_street"]			= $members["baddress_street"];
			$cfg['order'] = ($this->cfg_order) ? $this->cfg_order : config_load('order');

			if($cfg['order']['biztype'] == 'tax_exempt'){
				$cfg['order']['taxuse'] = "0";
			}else{
				$cfg['order']['taxuse'] = 1;
			}

			if(!$this->config_system['pgCompany']){
				$cfg['order']['cashreceiptuse'] = 0;
			}

			//좋아요 할인 혜택구분 $cfg['order']['fblike_ordertype'] ->0 회원/비회원, 1 회원만 할인제공
			$session_arr = ( $this->session->userdata('user') )?$this->session->userdata('user'):$_SESSION['user'];

			$cfg['order']['fblike_ordertype'] = ( ($cfg['order']['fblike_ordertype'] == 1 && $session_arr['member_seq'] ) || ($cfg['order']['fblike_ordertype'] != 1) ) ?1:0;

			$this->template->assign('cfg',$cfg);
			$this->template->assign('pg_company',$this->config_system['pgCompany']);
			$this->template->assign('mode',$mode);
			$this->template->assign('members',$members);
			$this->template->assign('business_info',$business_info);
			$this->template->assign('shipping_policy',$shipping_policy);
			$this->template->assign('bank',$bank);
			$this->template->assign('payment',$payment);
			$this->template->assign('escrow',$escrow);
			$this->template->assign('escrow_view',$escrow_view);
			$this->template->assign('settle',true);//현재페이지정보 넘겨줌
			$this->template->assign('settlepage',true);//현재페이지정보 넘겨줌

			// 상품상태별 아이콘
			$tmp = code_load('goodsStatusImage');
			$goodsStatusImage = array();
			foreach($tmp as $row){
				$goodsStatusImage[$row['codecd']] = $row['value'];
			}
			$this->template->assign(array('goodsStatusImage'=>$goodsStatusImage));
		}

		// ### NEW 배송 그룹 정보 추출 ### :: START -> shipping library 계산
		$this->load->library('shipping');
		if	($_GET['nation']){
			if	($_GET['nation'] == 'KOREA'){
				$nation = $_GET['nation'];
			}else{
				$nation = $this->shippingmodel->get_gl_nation($_GET['nation']);
			}
			$ship_ini['nation']	= $nation;
		}else{ // 지정안된 경우 기본 국내 :: 2017-01-31 lwh
			$nation				= 'KOREA';
		}
		$ini_info				= $this->shipping->set_ini($ship_ini);
		$shipping_group_list	= $this->shipping->get_shipping_groupping($cart['list']);

		// 상세 배송비 금액 추출
		$shipping_cost_detail	= $shipping_group_list['shipping_cost_detail'];
		unset($shipping_group_list['shipping_cost_detail']);

		// 전체 배송비 금액 추출
		$total_shipping_price	= $shipping_group_list['total_shipping_price'];
		unset($shipping_group_list['total_shipping_price']);

		// 배송 불가 항목 검사 및 상품별 입력 여부 결정
		$total_ship_ea = 0;
		if($shipping_group_list) foreach($shipping_group_list as $k => $deli_info){
			$total_ship_ea += $deli_info['shipping_ea'];
			$goods_cnt += count($deli_info['goods']);
			if($deli_info['ship_possible'] != 'Y'){
				$ship_possible[$k] = $deli_info;
			}

			// 배송 타입 체크 :: 2017-05-15 lwh
			if($deli_info['cfg']['baserule']['shipping_set_code'] == 'direct_store'){
				$is_direct_store	= true;
			}else if($deli_info['cfg']['baserule']['shipping_set_code'] == 'coupon'){
				$is_coupon			= true;
			}else{
				$is_goods			= true;
			}
		}

		// 사은품이 있는경우에는 상품으로 인식 :: 2015-04-03 lwh
		if($gift['gift_cnt'] > 0){
			if($is_direct_store && !$is_goods){
				// 매장수령만 있는경우 사은품은 매장에서 수령한다.
			}else{
				$is_goods = true;
			}
		}

		// 최종 결제 금액 재계산
		$cart['total_price'] = $cart['total_price'] + $total_shipping_price;

		// 배송가능 해외국가 추출
		$ship_gl_arr	= $this->shippingmodel->get_gl_shipping();
		$ship_gl_list	= $this->shippingmodel->split_nation_str($ship_gl_arr);
		// ### NEW 배송 그룹 정보 추출 및 계산 :: END ###

		// ### 구 배송 그룹 정보 매칭 :: START ###
		$cart = $this->shipping->get_old_shipping_groupping($cart, $shipping_group_list);
		// ### 구 배송 그룹 정보 매칭 :: END ###

		// 신)배송비 관련 변수 정의
		$this->template->assign(array(
			'ship_gl_arr'=>$ship_gl_arr,
			'ship_gl_list'=>$ship_gl_list
		)); // 국가목록

		$this->template->assign('nation',$nation);
		$this->template->assign('ini_info',$ini_info); // 배송ini 설정정보
		$this->template->assign('goods_cnt',$goods_cnt); // 상품 종 수
		$this->template->assign('total_ship_ea',$total_ship_ea); // 배송 총 갯수
		$this->template->assign('ship_possible',$ship_possible); // 배송불가 항목 리스트
		$this->template->assign('shipping_group_list',$shipping_group_list); // 배송그룹LIST
		$this->template->assign('shipping_cost_detail',$shipping_cost_detail); // 배송비 상세
		$this->template->assign('total_shipping_price',$total_shipping_price); // 전체 배송비
		$this->template->assign('total_price',$cart['total_price']); // 최종 결제금액
		// 신)배송비 관련 assign END

		// 구)배송비 관련 변수 정의
		$this->template->assign('promocodeSale',$cart['promocodeSale']);
		$this->template->assign('shipping_price',$cart['shipping_price']);
		$this->template->assign('shipping_company_cnt',$cart['shipping_company_cnt']);
		$this->template->assign('provider_shipping_policy',$cart['provider_shipping_policy']);
		$this->template->assign('provider_shipping_price',$cart['provider_shipping_price']);
		//$this->template->assign('shop_shipping_policy',$cart['shop_shipping_policy']);
		$this->template->assign('total',$cart['total']);
		$this->template->assign('total_ea',$cart['total_ea']);
		$this->template->assign('total_reserve',$cart['total_reserve']);
		$this->template->assign('total_point',$cart['total_point']);
		$this->template->assign('total_sale',$cart['total_sale']);
		$this->template->assign('total_sale_list',$cart['total_sale_list']);
		$this->template->assign('total_price',$cart['total_price']);
		$this->template->assign('total_price_compare',$cart['total_price_compare']); //총 결제금액 비교통화
		$this->template->assign('data_goods',$cart['data_goods']);
		$this->template->assign('cart_list',$cart['list']);
		// 구)배송비 관련 assign END

		// 배송지 주소 추출 :: 2016-08-02 lwh
		$add_sql = "
			SELECT address_group
			FROM fm_delivery_address
			WHERE
				member_seq=? AND
				address_group is not null AND
				address_group !=''
			GROUP BY address_group
			ORDER BY address_group ASC
		";
		$query = $this->db->query($add_sql,$member_seq);
		$arr_address_group = $query->result_array();
		if(!$arr_address_group){
			$arr_address_group[]['address_group'] = getAlert("dv007");
		}
		$this->template->assign('arr_address_group',$arr_address_group); // 배송지 주소정보

		$this->template->assign('is_coupon',$is_coupon);
		$this->template->assign('is_goods',$is_goods);
		$this->template->assign('is_direct_store',$is_direct_store);
		$this->template->assign('is_international_shipping',$is_international_shipping);

		// 상품별 주문배송방법 선택
		$this->template->assign('shipping_group_policy',$cart['shipping_group_policy']);
		$this->template->assign('shipping_group_price',$cart['shipping_group_price']);

		if($this->userInfo['member_seq']){

			// 최근배송지 2개 로딩
			$lately_delivery_address = $this->membermodel->get_delivery_address($this->userInfo['member_seq'],'lately',0,2);
			$this->template->assign('lately_delivery_address',$lately_delivery_address);

			//쿠폰보유건
			unset($sc);
			$sc['today']			= date('Y-m-d',time());
			$dsc['whereis'] = " and member_seq=".$this->userInfo['member_seq']." and use_status='unused' AND ( (issue_startdate is null  AND issue_enddate is null ) OR (issue_startdate <='".$sc['today']."' AND issue_enddate >='".$sc['today']."') )";//사용가능한
			$member_usable_coupons = $this->couponmodel->get_download_total_count($dsc);
			$this->template->assign('member_usable_coupons',$member_usable_coupons);
		}

		// 해외배송
		$international_shipping_info_path	= str_replace('settle.html', '../goods/_international_shipping_info.html', $this->template_path());
		$this->template->define('INTERNATIONAL_SHIPPING_INFO', $international_shipping_info_path);
		$this->template->assign('is_international_shipping',$is_international_shipping);
		$international_shipping_path = dirname($this->template_path()).'/_international_shipping.html';
		$this->template->define(array('international_shipping'=>$international_shipping_path));
		$international_shipping_path = dirname($this->template_path()).'/_international_shipping_tax.html';
		$this->template->define(array('international_shipping_tax'=>$international_shipping_path));

		//2016.03.31 견적서 버튼 추가 pjw
		if($this->config_basic['useestimate'] == 'Y'){
			$btn_script = '
				<script>
					$(document).ready(function(){
						//견적서 출력
						$(".btn_estimate").bind("click", function(){
							var f = $("form#orderFrm");
							f.attr("action",gl_ssl_estimate_action);
							f.attr("target", "_order_estimate");

							window.open("", "_order_estimate", "width=960,height=640,scrollbars=yes");

							f.submit();
						});
					});
				</script>
			';

			$btn_style	= 'margin-left:5px;';
			$btn_tag	= $btn_script.'<span class="btn_estimate btn_gray" style="'.$btn_style.'">'.getAlert('os251').'</span>';

			$this->template->assign(array('btn_estimate_script'=>$btn_script));
			$this->template->assign(array('btn_estimate'=>$btn_tag));
			$this->template->assign(array('btn_estimateyn'=>'y'));
		}

		// 배송지 스킨 정의
		if($this->session->userdata('order_label') === 'present') {
			$this->template->assign('is_order_present',true);
		}
		$shipping_address_path = dirname($this->template_path()).'/_shipping_address.html';
		$shipping_present_address_path = dirname($this->template_path()).'/_shipping_present_address.html';
		$this->template->define(array('shipping_address'=>$shipping_address_path));
		$this->template->define(array('shipping_present_address'=>$shipping_present_address_path));

		$this->print_layout($this->template_path());

		if(count($settle_alerts) >0 ) {
		    foreach($settle_alerts as $msg){
		        alert($msg);
		    }
		}

		##NON-ActiveX 일경우 패스 (2017-07-19 jhs 변경)
		if($this->config_system['not_use_pg'] != "y"){
			if($naxCheck != "Y"){
				## IE가 아닐때 결제모듈 설치 확인
				if($this->config_system['pgCompany'] && !$this->_is_mobile_agent){

					## 접속 브라우저 확인 IE/기타.
					$userAgenr = getBrowser();
					if( $userAgenr['nickname'] != "MSIE"){
						$this->pg_install_check($this->config_system['pgCompany']);
					}
				}
			}
		}

		//GA통계 이전페이지 넘기기 위해
		if($this->ga_auth_commerce_plus){
			echo "<script>
			$(\"form[name='orderFrm']\").append(\"<input type='hidden' name='referer_page_ga' value='{$_POST['referer_page_ga']}'>\");
			</script>";
		}

		if ($this->config_system['facebook_pixel_use'] == 'Y') {
		    //현재통화
		    $this->load->model('adminenvmodel');
		    $query       = $this->adminenvmodel->get(array('use_yn'=>'y'));
		    $res         = $query->result_array();
		    $currency    = $res[0]['currency'];

		    $fbq = "";
		    $fbq .= "<script>";
		    foreach ($cart['data_goods'] as $k => $v) {
		        $fbq .= "fbq('track', 'InitiateCheckout', {";
		        $fbq .= "    content_name: '".$v['goods_name']."',";
		        $fbq .= "    content_ids: '".$k."',";
		        $fbq .= "    content_type: 'product',";
		        $fbq .= "    value: ".$v['price'].",";
		        $fbq .= "    currency: '".$currency."'";
		        $fbq .= "});";
		    }
		    $fbq .= "</script>";

		    echo $fbq;
		}

		// gtag 연동
		$this->load->library('googleGtag');
		$sEventTags		= $this->googlegtag->eventTagCheckout($cart['total_price'], $this->config_system['basic_currency']);
		if($sEventTags)		echo $sEventTags;

	}

	## pg사별 크로스브라우징 플러그인 설치여부 확인.
	public function pg_install_check($pgCompany){

		$pg			= config_load($pgCompany);
		$CST_PLATFORM = "service";
		$CST_MID	  = $pg['mallCode'];
		$LGD_MID	  = $pg['mallCode'];
		$LGD_OID	  = $pg['mallCode'];

		switch($pgCompany){
			case "lg":
				$url = "http";
				if($_SERVER['HTTPS'] == 'on') $url .= "s";
				$url .= "://xpay.uplus.co.kr/xpay/js/xpay_install_utf-8.js";
				echo '<script type="text/javascript" src="'.$url.'" type="text/javascript"></script>';
				echo '
				<script language = "javascript" type="text/javascript">
				function doPay_ActiveX(){
					https_flag = true;
					if(hasXpayObject() == false) { xpayShowInstall(); }
				}
				doPay_ActiveX();
				</script>';
			break;
			case "kcp":
				$g_conf_js_url 	 = $pg['mallCode']=='T0007' ? "//pay.kcp.co.kr/plugin/payplus_test_un.js" : "//pay.kcp.co.kr/plugin/payplus_un.js";
				echo '<script type="text/javascript" src="'.$g_conf_js_url.'" charset="euc-kr"></script>';
				echo '<script language = "javascript" type="text/javascript">StartSmartUpdate();</script> ';

			break;
			case "inicis":
				echo '<script type="text/javascript" src="//plugin.inicis.com/pay61_secuni_cross.js?dummy=20131015"></script>';
				echo '<script type="text/javascript">StartSmartUpdate();</script>';
			break;
		}
	}

	public function settle_coupon()
	{
		if( isset($_GET['mode']) ) $mode = $_GET['mode'];
		else $mode = "cart";
		$this->displaymode = 'coupon';
		$this->calculate();
	}

	// 각종 할인 할인 금액 계산, 배송배 계산 및 주문금액 계산
	public function calculate($adminOrder="", $person_seq=''){

        $this->load->model('ssl');
        $this->ssl->decode();

		$this->load->helper('coupon');
		$this->load->model('cartmodel');
		$this->load->model('couponmodel');
		$this->load->model('ordermodel');
		$this->load->model('membermodel');

		// 기본값 정의
		$applypage						= 'order';
		$members						= "";
		$err_reserve					= "";
		$total_price					= 0;
		$total_reserve					= 0;
		$total_point					= 0;
		$goods_weight					= 0;
		$sum_goods_price				= 0;
		$total_coupon_sale				= 0;
		$total_fblike_sale				= 0;
		$total_mobile_sale				= 0;
		$total_goods_price				= 0;
		$total_member_sale				= 0;
		$total_real_sale_price			= 0;
		$international_shipping_price	= 0;
		$scripts						= array();
		if	(!$person_seq && $_POST['person_seq'])	$person_seq	= $_POST['person_seq'];
		// 모바일결제 : 체크값 오류시 callback으로 결제창 layer 숨김 처리
		$pg_cancel_script				= ($_POST['mobilenew'] == "y") ? $this->pg_cancel_script() : '';

		if($_POST) $_GET = $_POST;

		// 관리자 주문 예외처리 값
		if(!$adminOrder)	$adminOrder	= ($_GET["adminOrder"]) ? $_GET["adminOrder"] : '';
		if(!$person_seq)	$person_seq	= ($_GET["person_seq"]) ? $_GET["person_seq"] : '';
		$adminOrderType					= ($_GET['adminOrderType'] == 'person') ? 'person' : '';

		$cfg['order'] = ($this->cfg_order) ? $this->cfg_order : config_load('order');
		$cfg_reserve					= ($this->reserves)?$this->reserves:config_load('reserve');
		$pg								= config_load($this->config_system['pgCompany']);
		$shipping						= use_shipping_method();
		$this->shipping_order							= $shipping;

		// 입점사별 상품구매금액 합계
		$this->provider_sum_goods_price					= array();
		// 입점사별 상품 무게 합계
		$this->provider_goods_weight					= array();
		// 입점사별 해외배송비 합계
		$this->provider_international_shipping_price	= array();
		// 입점사별 기본배송비
		$this->provider_shipping_cost					= array();

		if	(is_array($shipping) )
			$international_shipping	= $shipping[1][$_POST['shipping_method_international']];

		// 회원정보 추출
		if		($adminOrder == "admin" && $_GET['member_seq'] && $this->displaymode == 'coupon'){
			$_GET['member_seq']		= (int) $_GET['member_seq'];
			$members	= $this->membermodel->get_member_data($_GET['member_seq']);
		}elseif	($adminOrder == "admin" && $_POST['member_seq']){
			$_POST['member_seq']		= (int) $_POST['member_seq'];
			$members	= $this->membermodel->get_member_data($_POST['member_seq']);
		}elseif	($adminOrder != "admin" && $this->userInfo['member_seq']){
			$members	= $this->membermodel->get_member_data($this->userInfo['member_seq']);
		}
		$member_seq		= $members['member_seq'];
		$member_group	= $members['group_seq'];
		$total_emoney	= $members['emoney'];
		$total_cash		= $members['cash'];
		if( $member_seq != '' ){
			$members['emoney']	= $this->membermodel->get_emoney($member_seq);

			// O2O 조회로 인해 블럭되어 있는지 확인
			$this->load->library("o2o/o2oservicelibrary");
			$this->o2oservicelibrary->check_o2o_benefit(true, $member_seq);
		}

		// 마일리지 전체 사용일때 회원 총 마일리지 불러오기
		if($_POST['emoney_all'] == "y"){
			$_POST['emoney']	= $members['emoney'];
		}

		// 장바구니 정보 추출
		if		($adminOrder == 'admin' && $adminOrderType == 'person'){
			$this->load->model('personcartmodel');
			$cart	= $this->personcartmodel->catalog($member_seq, $person_seq);
		}elseif	($person_seq > 0){
			$this->load->model('personcartmodel');
			$cart	= $this->personcartmodel->catalog($this->userInfo['member_seq'], $person_seq);
			if	($cart['person']['use_reserve']){
				$this->person_use_reserve				= 1;
				$cfg_reserve['default_reserve_limit']	= $cart['person']['reserve_limit'];
			}
		}else{
			$this->load->model('cartmodel');
			$cart	= $this->cartmodel->catalog($adminOrder);
		}
		$this->cart	= $cart;
		if	( $adminOrder != 'admin' && !$cart['list'] && $this->displaymode != 'cart' ){
			pageLocation('../main/index',getAlert('os111'), 'parent');
			exit;
		}

		/* 구매 시 마일리지액 제한 조건 추가 leewh 2014-06-25 */
		if ($cfg_reserve['default_reserve_limit']>=2){
			if (isset($_POST['appointed_reserve'])) {
				$appointed_reserve = $_POST['appointed_reserve'];
			}

			if ($cfg_reserve['default_reserve_limit']==3) {
				unset($cal_total_real_sale_price);
				if (isset($_POST['total_real_sale_price'])) {
					$cal_total_real_sale_price = $_POST['total_real_sale_price'];
				}

				$tot_using_reserve = 0; // 상품 사용마일리지

				if ($_POST['emoney'] > 0) {
					// 총 사용 마일리지 재정의 총 상품실결제금액보다 총 결제금액이 클 경우
					if ($cal_total_real_sale_price < $_POST['emoney']) {
						$tot_using_reserve = $cal_total_real_sale_price;
					} else {
						$tot_using_reserve = $_POST['emoney'];
					}
				}
			}
		}

		$is_international_shipping = false; // 해외배송상품
		/**** 재고 체크 및 최대/최소 구매수량 체크 ****/
		foreach($cart['data_goods'] as $goods_seq => $data){
			if($data['option_international_shipping_status'] == 'y'){
				$is_international_shipping = true; // 해외배송상품
			}

			if($data['option_ea']){
				$optEa = $data['option_ea'];
			}else{
				$optEa = $data['ea'];
			}

			// 구매수량 체크
			if($data['min_purchase_ea'] && $data['min_purchase_ea'] >  $optEa){
				//addslashes($data['goods_name']).'은 '.$data['min_purchase_ea'].'개 이상 구매하셔야 합니다.'
				$err_msg = getAlert('os006',array(addslashes($data['goods_name']),$data['min_purchase_ea']));
				if( $this->displaymode == 'cart' ) {
					alert($err_msg);
				}else{
					openDialogAlert($err_msg,400,140,'parent',$pg_cancel_script);
					exit;
				}
			}
			if($data['max_purchase_ea'] && $data['max_purchase_ea'] < $optEa){
				//addslashes($data['goods_name']).'은 '.$data['max_purchase_ea'].'개 이상 구매하실 수 없습니다.'
				$err_msg = getAlert('os007',array(addslashes($data['goods_name']),$data['max_purchase_ea']+1));
				if( $this->displaymode == 'cart' ) {
					alert($err_msg);
				}else{
					openDialogAlert($err_msg,400,140,'parent',$pg_cancel_script);
					exit;
				}
			}

			// 단독이벤트만 판매시 이벤트기간이 아니면 판매중지 @2013-11-29
			if( $data['event']['event_goodsStatus'] === true ){
				//↓아래 상품은 단독이벤트 기간에만 구매가 가능합니다.
				$err_msg = getAlert('os008');
				$err_msg .= "\\n".addslashes($data['goods_name']);
				if( $this->displaymode == 'cart' ) {
					alert($err_msg);
				}else{
					openDialogAlert($err_msg,400,140,'parent',$pg_cancel_script);
					exit;
				}
			}

			//배송비쿠폰 개별배송상품 체크
			if($data['shipping_policy'] == 'shop'){
				$this->arr_shop_shipping_cnt++;
			}else{
				$this->arr_goods_shipping_price++;
			}

			if($data['ea_for_option'])foreach($data['ea_for_option'] as $option_key => $option_ea){

				$option_r = explode(' ^^ ',$option_key);
				$option_t = explode(' ^^ ',$data['option_title']);

				// 재고 체크
				$chk = check_stock_option(
					$goods_seq,
					$option_r[0],
					$option_r[1],
					$option_r[2],
					$option_r[3],
					$option_r[4],
					$option_ea,
					$cfg['order'],
					'view_stock'
				);

				if( $chk['stock'] < 0 ){
					$opttitle = '';
					if($option_r[0]) $opttitle .= $option_r[0];
					if($option_r[1]) $opttitle .= ' '.$option_r[1];
					if($option_r[2]) $opttitle .= ' '.$option_r[2];
					if($option_r[3]) $opttitle .= ' '.$option_r[3];
					if($option_r[4]) $opttitle .= ' '.$option_r[4];

					$opttitle = '';
					foreach($option_r as $optKey => $optVal){
						if($optVal && $option_t[$optKey]) $opttmp = $option_t[$optKey] . ':';
						if($optVal)	$tmpopttitle[] = $opttmp . $optVal;
					}
					if(count($tmpopttitle) > 0)
						$opttitle			= implode(', ', $tmpopttitle);
					$goodsName	= addslashes($data['goods_name']);
					if	($opttitle)	$goodsName	.= ' (' . $opttitle . ')';

					//구매가능재고를 초과한 상품을 알려 드립니다.<br/> $goodsName | $ea개 → 구매가능재고는 $chk['sale_able_stock']개입니다.
					$err_msg = getAlert('os009', array($goodsName, $option_ea, $chk['sale_able_stock']));
					if( $this->displaymode == 'cart' ) {
						$this->cart_alerts[] = $err_msg;
					}else{
						openDialogAlert($err_msg,400,140,'parent',$pg_cancel_script);
						exit;
					}
				}
			}

			if($data['ea_for_suboption']) foreach($data['ea_for_suboption'] as $option_key => $option_ea){
				$option_r = explode(' ^^ ',$option_key);
				// 재고 체크
				$chk = check_stock_suboption(
					$goods_seq,
					$option_r[0],
					$option_r[1],
					$option_ea,
					$cfg['order'],
					'view_stock'
				);

				if( $chk['stock'] < 0 ){
					$opttitle = '';
					if($option_r[1]) $opttitle = $option_r[0] . ':' . $option_r[1];
					$goodsName = addslashes($data['goods_name']);
					if	($opttitle)	$goodsName	.= ' (' . $opttitle . ')';

					//구매가능재고를 초과한 상품을 알려 드립니다.<br/> $goodsName | $ea개 → 구매가능재고는 $chk['sale_able_stock']개입니다.
					$err_msg = getAlert('os009', array($goodsName, $option_ea, $chk['sale_able_stock']));
					if( $this->displaymode == 'cart' ) {
						$this->cart_alerts[] = $err_msg;
					}else{
						openDialogAlert($err_msg,400,140,'parent',$pg_cancel_script);
						exit;
					}
				}
			}
		}
		/* **************************************************** */

		// 주문서 쿠폰 할인 초기화
		// 장바구니에 담긴 각 상품별 쿠폰 할인을 계산하기 전 미리 설정
		$ordersheet_coupon_download_seq = $_POST['ordersheet_coupon_download_seq'];
		$cart['ordersheet_coupon_download_seq'] = $ordersheet_coupon_download_seq;

		// 주문서 쿠폰에서 총액 계산 용, 쿠폰은 필수옵션금액에서만 할인된다.
		foreach($cart['list'] as $key => $data) {
			$sum_option_total_price				+= ($data['price'] * $data['ea']);
		}

		//----> sale library 적용
		$cart['total']						= $cart['total_sale_price'];
		$param['cal_type']					= 'list';
		$param['total_price']				= $cart['total_sale_price'];
		$param['sum_option_total_price']	= $sum_option_total_price;
		$param['reserve_cfg']				= $cfg_reserve;
		$param['tot_use_emoney']			= $tot_using_reserve;
		$param['member_seq']				= $member_seq;
		$param['group_seq']					= $member_group;
		// 주문서 쿠폰 할인 계산을 위한 파라미터 추가
		if($ordersheet_coupon_download_seq)
			$param['ordersheet_coupon_seq'] = $ordersheet_coupon_download_seq;
		$this->sale->set_init($param);
		$this->sale->preload_set_config($applypage);
		//<---- sale library 적용

		if(!$this->displaymode)
			echo "<script type='text/javascript' src='/app/javascript/jquery/jquery.min.js'></script>";
		$scripts[]	= "<script type='text/javascript'>";
		$scripts[]	= "$(function() {";


		foreach($cart['list'] as $key => $data) {

			// 초기값
			$category				= ($data['r_category']) ? $data['r_category'] : array();
			$data['ori_price']		= $data['price'];
			$cart_suboptions		= $data['cart_suboptions'];
			$cart_inputs			= $data['cart_inputs'];
			$coupon_download_seq	= $_POST['coupon_download'][$data['cart_seq']][$data['cart_option_seq']];
			$coupon_ordersheet_download_seq	= $_POST['ordersheet_coupon_download_seq'];		// 주문서 쿠폰 다운번호

			//----> sale library 적용
			unset($param, $sales, $optsalelist);
			$param['option_type']					= 'option';
			$param['consumer_price']				= $data['consumer_price'];
			$param['price']							= $data['org_price'];
			$param['sale_price']					= $data['price'];
			$param['ea']							= $data['ea'];
			$param['goods_ea']						= $cart['data_goods'][$data['goods_seq']]['ea'];
			$param['option_ea']						= $cart['data_goods'][$data['goods_seq']]['option_ea'];
			$param['category_code']					= $category;
			$param['goods_seq']						= $data['goods_seq'];
			$param['goods']							= $data;
			$param['sum_option_total_price']		= $sum_option_total_price;

			if	($coupon_download_seq)
				$param['coupon_download_seq']		= $coupon_download_seq;

			$this->sale->set_init($param);
			$sales	= $this->sale->calculate_sale_price($applypage);

			//이벤트 쿠폰/코드 사용제한2 @2015-08-13
			if	( $this->sale->goods['event'] ) {
				if( $this->sale->goods['event']['use_coupon'] == 'n' && !in_array($data['goods_seq'],$this->ordernosales_cp) ) {
					$this->ordernosales_cp[$data['goods_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_coupon_shipping'] == 'n' && !in_array($data['shipping']['provider_seq'],$this->ordernosales_cp_sh)  ) {
					$this->ordernosales_cp_sh[$data['shipping']['provider_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_coupon_ordersheet'] == 'n' && !in_array($data['goods_seq'],$this->ordernosales_cp_os)  ) {
					$this->ordernosales_cp_os[$data['goods_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_code'] == 'n' && !in_array($data['goods_seq'],$this->ordernosales_cd)  ) {
					$this->ordernosales_cd[$data['goods_seq']] = $this->sale->goods['event']['event_seq'];
				}
				if( $this->sale->goods['event']['use_code_shipping'] == 'n' && !in_array($data['shipping']['provider_seq'],$this->ordernosales_cd_sh)  ) {
					$this->ordernosales_cd_sh[$data['shipping']['provider_seq']] = $this->sale->goods['event']['event_seq'];
				}
			}
			// 기본 정보
			$data['org_price']							= ($data['consumer_price']) ? $data['consumer_price'] : $data['price'];
			$opt_price									= $sales['one_result_price'];
			$data['sale_price']							= $sales['one_result_price'];
			if	(!$param['sale_price']){
				$data['original_price']					= $sales['one_sale_list']['original'];//정가
				$data['basic_sale']						= $sales['one_sale_list']['basic'];
			}

			// 이벤트, 복수구매 할인 정보 :: 2018-07-10 lkh
			$data['event_sale_target']					= ($sales['target_list']['event'] == 1) ? 'consumer_price' : 'price';
			$data['event_sale']							= $sales['sale_list']['event'];
			$data['event_reserve']						= $sales['one_reserve_list']['event'];
			$data['event_point']						= $sales['one_point_list']['event'];
			$data['multi_sale']							= $sales['sale_list']['multi'];

			$data['event_sale_unit']					= $sales['one_sale_list']['event'];//이벤트할인(개당)
			$data['multi_sale_unit']					= $sales['one_sale_list']['multi'];//복수구매할인(개당)

			// 쿠폰할인 정보
			$data['coupon_sale']						= $sales['sale_list']['coupon'];
			$data['coupon_sale_unit']					= $sales['one_sale_list']['coupon'];//쿠폰할인(개당)
			$data['coupon_sale_rest']					= $data['coupon_sale']-($data['coupon_sale_unit']*$data['ea']);//쿠폰할인-짜투리
			$data['coupon']['salescost_admin']			= $this->sale->coupon_salescost['admin'];
			$data['coupon']['salescost_provider']		= $this->sale->coupon_salescost['provider'];
			$data['coupon']['provider_list']			= $this->sale->coupon_salescost['list'];
			$data['download_seq']						= $coupon_download_seq;
			$data['coupon_select_duplication_use']		= false;
			if	($coupon_download_seq || $coupon_ordersheet_download_seq){
				$coupon_same_time_n						= $this->sale->coupon_same_time_n;
				$coupon_same_time_n_duplication_n		= $this->sale->coupon_duplication_n;
				$coupon_same_time_y						= $this->sale->coupon_same_time_y;
				$coupon_sale_payment_b					= $this->sale->coupon_sale_payment_b;
				$coupon_sale_agent_m					= $this->sale->coupon_sale_agent_m;
			}

			// 주문서쿠폰 할인정보
			$data['unit_ordersheet']					= $sales['sale_list']['ordersheet'];
			// 정산용 쿠폰 세일 금액에 주문서 쿠폰 할인 내역을 추가
			$data['coupon_sale_unit']					+= (int)($sales['sale_list']['ordersheet']/$data['ea']);
			$data['coupon_sale_rest']					+= $sales['sale_list']['ordersheet'] - (((int)($sales['sale_list']['ordersheet']/$data['ea']))*$data['ea']);

			// 쿠폰 사용 팝업에서 전체 쿠폰 추출
			if	( $members && $person_seq == "" && $this->displaymode == 'coupon'){
				if( !$this->ordernosales_cp[$data['goods_seq']] ) {//이벤트 쿠폰 사용제한 @2015-08-13
					$coupons			= $this->couponmodel->get_able_use_list($members['member_seq'],$data['goods_seq'],$category, $sum_option_total_price, $data['price'], $data['ea']);
					$data['coupons']	= $coupons;
					# 선택한 쿠폰의 중복할인 적용 여부
					if($coupon_download_seq){
						foreach($coupons as $coupon_data){
							if($coupon_data['coupon_seq'] == $sales['seq_list']['coupon'] && $coupon_download_seq == $coupon_data['download_seq']){
								$data['coupon_select_duplication_use']	= $coupon_data['duplication_use'];
							}
						}
					}
				}
			}else{
				$data['coupons']						= $this->sale->couponSales;
			}

			// 회원할인 정보
			$member_sale								+= $data['member_sale'];
			$data['member_sale']						= $sales['sale_list']['member'];
			$data['member_sale_unit']					= $sales['one_sale_list']['member'];//등급할인(개당)
			$data['member_sale_rest']					= $data['member_sale']-($data['member_sale_unit']*$data['ea']);//등급할인-짜투리
			// 코드할인 정보
			$data['promotion_code_seq']					= $this->sale->code_seq;
			$data['promotion_code_sale']				= $sales['sale_list']['code'];
			$data['code_sale_unit']						= $sales['one_sale_list']['code'];//코드할인(개당)
			$data['code_sale_rest']						= $data['promotion_code_sale']-($data['code_sale_unit']*$data['ea']);//코드할인-짜투리
			$data['promotion']['salescost_admin']		= $this->sale->code_salescost['admin'];
			$data['promotion']['salescost_provider']	= $this->sale->code_salescost['provider'];
			$data['promotion']['provider_list']			= $this->sale->code_salescost['list'];

			// 좋아요 할인 정보
			$data['fblike_sale']						= $sales['sale_list']['like'];
			$data['fblike_sale_unit']					= $sales['one_sale_list']['like'];//좋아요할인(개당)
			$data['fblike_sale_rest']					= $data['fblike_sale']-($data['fblike_sale_unit']*$data['ea']);//좋아요-짜투리

			// 모바일할인 정보
			$data['mobile_sale']						= $sales['sale_list']['mobile'];
			$data['mobile_sale_unit']					= $sales['one_sale_list']['mobile'];//모바일할인(개당)
			$data['mobile_sale_rest']					= $data['mobile_sale']-($data['mobile_sale_unit']*$data['ea']);//모바일할인-짜투리

			// 유입경로 할인 정보
			$data['referersale_seq']					= $this->sale->referer_seq;
			$data['referer_sale']						= $sales['sale_list']['referer'];
			$data['referer_sale_unit']					= $sales['one_sale_list']['referer'];
			$data['referer_sale_rest']					= $data['referer_sale']-($data['referer_sale_unit']*$data['ea']);//유입경로할인-짜투리
			$data['referersale']['salescost_provider']	= $this->sale->referer_salecode['provider'];
			$data['referersale']['provider_list']		= $this->sale->referer_salecode['list'];


			// sale_price가 없을 시 여기서 event까지 계산함
			if	(!$data['event'] && $this->sale->cfgs['event']){
				$data['event']					= $this->sale->cfgs['event'];
				$data['eventEnd']				= $sales['eventEnd'];
			}

			// 마일리지 / 포인트 ( 실결제 금액 기준이기에 여기서 계산 )
			// 이벤트 마일리지 / 포인트 계산 ( 장바구니에서 혜택적용할인가를 받아서 쓸 경우만 )
			if	($param['sale_price']){
				$this->sale->cfgs['event']		= $data['event'];
				$data['event_reserve']			= $this->sale->event_sale_reserve($sales['one_result_price']);
				$data['event_point']			= $this->sale->event_sale_point($sales['one_result_price']);
			}
			$data['member_reserve']						= $sales['one_reserve_list']['member'];
			$data['member_point']						= $sales['one_point_list']['member'];
			$data['fb_reserve']							= $sales['one_reserve_list']['like'];
			$data['fb_point']							= $sales['one_point_list']['like'];
			$data['mobile_reserve']						= $sales['one_reserve_list']['mobile'];
			$data['mobile_point']						= $sales['one_point_list']['mobile'];

			$total_real_sale_price						+= $sales['result_price'];
			$data['tot_org_price']						= $data['org_price'] * $data['ea'];
			$data['tot_sale_price']						= $sales['total_sale_price'];
			$data['tot_result_price']					= $sales['result_price'];
			if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $tmptitle){
				$optsalelist[$sale_type]						= $sales['sale_list'][$sale_type];
				$moptsalelist[$sale_type]						= $sales['sale_list'][$sale_type];
				$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
				$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
				if($sale_type=="ordersheet"){	// 주문서쿠폰의 경우 쿠폰에 강제 합산
					$cart['total_sale_list']['coupon']['price']	+= $sales['sale_list'][$sale_type];
				}
			}

			$this->sale->reset_init();
			//<---- sale library 적용


			// 구매적립(마일리지 제한 조건 설정에 따른 분기)
			$reserve_policy_log = '';
			$new_opt_price = 0; // 마일리지 계산용 변수
			if ($cfg_reserve['default_reserve_limit']==3 && $_POST['emoney'] > 0) {

				/* 적립 제한 조건 B설정 추가 leewh 2014-07-04 */
				$each_using_reserve = 0;

				// 필수 옵션 1개 사용마일리지 계산
				$each_using_reserve = $this->goodsmodel->get_reserve_standard_pay($opt_price, $data['ea'], $cal_total_real_sale_price, $tot_using_reserve);

				$new_opt_price = $opt_price - $each_using_reserve;
				$reserve_policy_log	.= sprintf('[제한조건B (실결제금액-사용마일리지 : %s)]', get_currency_price($new_opt_price));
			} else {
				// 마일리지 계산용 가격 분리 leewh 2014-07-09
				$new_opt_price = $opt_price;
			}

			// 포인트
			$data['point']		= $this->goodsmodel->get_point_with_policy($opt_price);
			$data['reserve']	= $this->goodsmodel->get_reserve_with_policy($data['reserve_policy'], $new_opt_price, $cfg_reserve['default_reserve_percent'], $data['reserve_rate'], $data['reserve_unit'], $data['reserve']);

			// 마일리지 설정 B일때 마일리지을 사용한 경우 상품마일리지 won(원) 단위 환산하여 지급 2015-04-02
			if ($cfg_reserve['default_reserve_limit'] == 3 && $_POST['emoney'] > 0) {
				if ($new_opt_price < 1) { // 결제금액 0원 일경우 마일리지 0원 처리
					$data['reserve'] = 0;
				} else if($data['reserve_unit'] != 'percent') {
					$data['reserve'] = get_currency_price(($data['reserve']/$data['price'])*$new_opt_price);
				}
			}

			// 비회원 마일리지/포인트 제거
			if	(!($member_seq > 0)){
				$data['fb_reserve']			= 0;
				$data['fb_point']			= 0;
				$data['mobile_reserve']		= 0;
				$data['mobile_point']		= 0;
				$data['member_point']		= 0;
				$data['member_reserve']		= 0;
				$data['point_one']			= 0;
				$data['reserve_one']		= 0;
				$data['point']				= 0;
				$data['reserve']			= 0;
			}

			// 마일리지,포인트 로그
			$log = '';
			if	( $reserve_policy_log )	$log	.= $reserve_policy_log;
			if( $data['reserve'] > 0 ) $log .= ($log?' / ':'').'구매  : '.(is_numeric($data['reserve'])?get_currency_price($data['reserve']):$data['reserve']);
			if( $data['event_reserve'] > 0 ) $log .= ($log?' / ':'').'이벤트  : '.(is_numeric($data['event_reserve'])?get_currency_price($data['event_reserve']):$data['event_reserve']);
			if( $data['member_reserve'] > 0 ) $log .= ($log?' / ':'').'회원  : '.(is_numeric($data['member_reserve'])?get_currency_price($data['member_reserve']):$data['member_reserve']);
			if( $data['fb_reserve'] > 0 ) $log .= ($log?' / ':'').'좋아요  : '.(is_numeric($data['fb_reserve'])?get_currency_price($data['fb_reserve']):$data['fb_reserve']);
			if( $data['mobile_reserve'] > 0 ) $log .= ($log?' / ':'').'모바일  : '.(is_numeric($data['mobile_reserve'])?get_currency_price($data['mobile_reserve']):$data['mobile_reserve']);
			$data['reserve_log'] = $log;
			$log = '';
			if( $data['point'] > 0 ) $log .= ($log?' / ':'').'구매  : '.(is_numeric($data['point'])?get_currency_price($data['point']):$data['point']);
			if( $data['event_point'] > 0 ) $log .= ($log?' / ':'').'이벤트  : '.(is_numeric($data['event_point'])?get_currency_price($data['event_point']):$data['event_point']);
			if( $data['member_point'] > 0 ) $log .= ($log?' / ':'').'회원  : '.(is_numeric($data['member_point'])?get_currency_price($data['member_point']):$data['member_point']);
			if( $data['fb_point'] > 0 ) $log .= ($log?' / ':'').'좋아요  : '.(is_numeric($data['fb_point'])?get_currency_price($data['fb_point']):$data['fb_point']);
			if( $data['mobile_point'] > 0 ) $log .= ($log?' / ':'').'모바일  : '.(is_numeric($data['mobile_point'])?get_currency_price($data['mobile_point']):$data['mobile_point']);
			$data['point_log'] = $log;

			// 옵션의 마일리지 포인트
			$data['reserve_one']	= get_cutting_price($data['reserve'])
									+ get_cutting_price($data['event_reserve'])
									+ get_cutting_price($data['member_reserve'])
									+ get_cutting_price($data['fb_reserve'])
									+ get_cutting_price($data['mobile_reserve']);
			$data['point_one']		= get_cutting_price($data['point'])
									+ get_cutting_price($data['event_point'])
									+ get_cutting_price($data['member_point'])
									+ get_cutting_price($data['fb_point'])
									+ get_cutting_price($data['mobile_point']);

			/* 구매 시 마일리지액 제한 조건 추가 leewh 2014-06-25 */
			$reserve_policy_log = '';
			if ($cfg_reserve['default_reserve_limit']==1 && $_POST['emoney'] > 0) {
				$data['reserve_one'] = 0;
				$reserve_policy_log	.= '[제한조건D 지급 마일리지 : 0]';
			} else if ($cfg_reserve['default_reserve_limit']==2 && $_POST['emoney'] > 0) {
				$minus_reserve = 0;
				$reserve_subtract = $appointed_reserve - $_POST['emoney'];

				if ($reserve_subtract > 0) {
					/* 필수 옵션 차감할 1개 사용 마일리지 계산 */
					$minus_reserve = $this->goodsmodel->get_reserve_limit($data['reserve_one']*$data['ea'], $data['ea'], $appointed_reserve, $_POST['emoney']);
					$data['reserve_one'] = $data['reserve_one'] - $minus_reserve;
				} else {
					$minus_reserve = $_POST['emoney'];
					$data['reserve_one'] = 0;  //전액 사용으로 지급안함.
				}
				$reserve_policy_log .= sprintf("[제한조건C 지급 마일리지 : %s]", get_currency_price($data['reserve_one']));
			}

			// 마일리지 정책 A 가 아닐경우 정책명을 제일 앞에 표시
			if ($data['reserve_log'] && $reserve_policy_log) $data['reserve_log'] = $reserve_policy_log." / ".$data['reserve_log'];

			$data['tot_reserve']						= $data['reserve_one'] * $data['ea'];
			$data['tot_point']							= $data['point_one'] * $data['ea'];
			$data['option_suboption_price_sum']			= $data['sale_price'] * $data['ea'];
			$data['option_suboption_price_sum_origin']	= $data['price'] * $data['ea'];

			// 추가구성옵션 계산
			if($data['cart_suboptions']){
				foreach($data['cart_suboptions'] as $k => $cart_suboption){

					//----> sale library 적용
					unset($param, $sales, $subsalelist);
					$param['option_type']			= 'suboption';
					$param['sub_sale']				= $cart_suboption['sub_sale'];
					$param['consumer_price']		= $cart_suboption['consumer_price'];
					$param['price']					= $cart_suboption['price'];
					$param['sale_price']			= $cart_suboption['price'];
					$param['ea']					= $cart_suboption['ea'];
					$param['category_code']			= $category;
					$param['goods_seq']				= $data['goods_seq'];
					$param['goods']					= $data;
					$this->sale->set_init($param);
					$sales	= $this->sale->calculate_sale_price($applypage);
					$cart_suboption['org_price']				= ($cart_suboption['consumer_price']) ? $cart_suboption['consumer_price'] : $cart_suboption['price'];
					if	(!$param['sale_price']){
						$cart_suboption['original_price']		= $sales['one_sale_list']['original'];
						$cart_suboption['basic_sale']			= $sales['one_sale_list']['basic'];
					}
					$cart_suboption['event_sale_target']	= ($sales['target_list']['event'] == 1) ? 'consumer_price' : 'price';
					$cart_suboption['event_sale']			= $sales['sale_list']['event'];
					$cart_suboption['multi_sale']			= $sales['sale_list']['multi'];

					$cart_suboption['event_sale_unit']		= $sales['one_sale_list']['event'];//이벤트할인(개당)
					$cart_suboption['multi_sale_unit']		= $sales['one_sale_list']['multi'];//복수구매할인(개당)

					$cart_suboption['member_sale']		= $sales['sale_list']['member'];
					$cart_suboption['member_sale_unit']	= $sales['one_sale_list']['member'];
					$cart_suboption['member_sale_rest']	= $cart_suboption['member_sale']-($cart_suboption['member_sale_unit']*$cart_suboption['ea']);//등급할인-짜투리
					$member_sale						+= $cart_suboption['member_sale'];
					$cart_suboption['member_reserve']	= $sales['one_reserve_list']['member'];
					$cart_suboption['member_point']		= $sales['one_point_list']['member'];
					$sale_suboption_price				= $sales['one_result_price'];
					$cart_suboption['sale_price']		= $sales['one_result_price'];
					$total_sale_suboption				+= $cart_suboption['member_sale'];
					$subsaletotalprice					= $sales['total_sale_price'];
					$data['tot_org_price']				+= $cart_suboption['org_price'] * $cart_suboption['ea'];
					$data['tot_sale_price']				+= $sales['total_sale_price'];
					$data['tot_result_price']			+= $sales['result_price'];

					if	($sales['title_list'])foreach($sales['title_list'] as $sale_type => $tmptitle){
						$subsalelist[$sale_type]						= $sales['sale_list'][$sale_type];
						$moptsalelist[$sale_type]						+= $sales['sale_list'][$sale_type];
						$cart['total_sale_list'][$sale_type]['title']	= $sale_title;
						$cart['total_sale_list'][$sale_type]['price']	+= $sales['sale_list'][$sale_type];
						if($sale_type=="ordersheet"){	// 주문서쿠폰의 경우 쿠폰에 강제 합산
							$cart['total_sale_list']['coupon']['price']	+= $sales['sale_list'][$sale_type];
						}
					}
					$this->sale->reset_init();
					//<---- sale library 적용

					/* $cart_suboption['reserve'] 초기 값이 구매수량이 곱해진 총마일리지가 전달됨.
					추가옵션 상품 1개 기준으로 상품 마일리지 재계산 2015-03-27 leewh */
					$cart_suboption['reserve']	= $this->goodsmodel->get_reserve_with_policy($data['sub_reserve_policy'],$sale_suboption_price,$cfg_reserve['default_reserve_percent'],$cart_suboption['reserve_rate'],$cart_suboption['reserve_unit'],$cart_suboption['reserve']);

					$cart_suboption['reserve'] = get_currency_price($cart_suboption['reserve']);
					// 구매마일리지(마일리지 제한 조건 설정에 따른 분기)
					$reserve_policy_log = '';
					$new_sale_suboption_price = 0; //추가옵션 마일리지 계산용 변수
					if ($cfg_reserve['default_reserve_limit']==3 && $_POST['emoney'] > 0) {

						/* 적립 제한 조건 B설정 추가 leewh 2014-07-04 */
						$each_sub_using_reserve = 0;

						// 서브옵션 1개 사용마일리지 계산
						$each_sub_using_reserve = $this->goodsmodel->get_reserve_standard_pay($sale_suboption_price, $cart_suboption['ea'], $cal_total_real_sale_price, $tot_using_reserve);

						$new_sale_suboption_price = $sale_suboption_price - $each_sub_using_reserve;
						$reserve_policy_log .= sprintf('[제한조건B (실결제금액-사용마일리지 : %s)]', get_currency_price($new_sale_suboption_price));
					} else {
						// 마일리지 계산용 가격 분리 leewh 2014-07-09
						$new_sale_suboption_price = $sale_suboption_price;
					}

					// 서브옵션 마일리지 및 포인트
					$cart_suboption['point']	= $this->goodsmodel->get_point_with_policy($sale_suboption_price);
					$cart_suboption['reserve']	= $this->goodsmodel->get_reserve_with_policy($data['sub_reserve_policy'], $new_sale_suboption_price, $cfg_reserve['default_reserve_percent'], $cart_suboption['reserve_rate'], $cart_suboption['reserve_unit'], $cart_suboption['reserve']);

					// 마일리지 설정 B일때 마일리지을 사용한 경우 상품마일리지 won(원) 단위 환산하여 지급 2015-04-02
					if ($cfg_reserve['default_reserve_limit'] == 3 && $_POST['emoney'] > 0) {
						if	($new_sale_suboption_price < 1) { // 결제금액 0원 일경우 마일리지 0원 처리
							$cart_suboption['reserve']	= 0;
						} else if($cart_suboption['reserve_unit'] != "percent") {
							$cart_suboption['reserve'] = get_cutting_price(($cart_suboption['reserve'] / $cart_suboption['price']) * $new_sale_suboption_price);
						}
					}

					// 비회원 마일리지/포인트 제거
					if	(!($member_seq > 0)){
						$cart_suboption['member_point']		= 0;
						$cart_suboption['member_reserve']	= 0;
						$cart_suboption['point_one']		= 0;
						$cart_suboption['reserve_one']		= 0;
						$cart_suboption['point']			= 0;
						$cart_suboption['reserve']			= 0;
					}

					// 추가옵션 마일리지, 포인트 로그
					$log = '';
					if ($reserve_policy_log)	$log .= $reserve_policy_log;
					if ($cart_suboption['reserve'] > 0)	$log .= sprintf("%s구매 : %s", ($log?' / ':''), get_currency_price($cart_suboption['reserve']));
					if ($cart_suboption['member_reserve'] > 0) $log .= sprintf("%s회원 : %s", ($log?' / ':''), get_currency_price($cart_suboption['member_reserve']));
					$cart_suboption['reserve_log'] = $log;

					$log = '';
					if ($cart_suboption['point'] > 0)	$log .= sprintf("%s구매 : %s", ($log?' / ':''), get_currency_price($cart_suboption['point']));
					if ($cart_suboption['member_point'] > 0) $log .= sprintf("%s회원 : %s", ($log?' / ':''), get_currency_price($cart_suboption['member_point']));
					$cart_suboption['point_log'] = $log;

					// 서브 옵션용 마일리지, 포인트 개별 합계 2015-03-27
					$cart_suboption['reserve_one']	= get_cutting_price($cart_suboption['reserve']) +  get_cutting_price($cart_suboption['member_reserve']);
					$cart_suboption['point_one']	=  get_cutting_price($cart_suboption['point']) +  get_cutting_price($cart_suboption['member_point']);

					/* 구매 시 마일리지액 제한 조건 추가 leewh 2014-06-25 */
					$reserve_policy_log = '';
					if ($cfg_reserve['default_reserve_limit']==1 && $_POST['emoney'] > 0) {
						$cart_suboption['reserve_one'] = 0;
						$reserve_policy_log	.= '[제한조건D 지급 마일리지 : 0]';
					} else if ($cfg_reserve['default_reserve_limit']==2 && $_POST['emoney'] > 0) {
						$minus_sub_reserve = 0;
						$reserve_sub_subtract = $appointed_reserve - $_POST['emoney'];

						if ($reserve_sub_subtract > 0) {
							/* 서브옵션 차감할 1개 사용 마일리지 계산 */
							$tmp_tot_reserve = $cart_suboption['reserve_one'] * $cart_suboption['ea'];
							$minus_sub_reserve = $this->goodsmodel->get_reserve_limit($tmp_tot_reserve, $cart_suboption['ea'], $appointed_reserve, $_POST['emoney']);
							$cart_suboption['reserve_one'] = $cart_suboption['reserve_one'] - $minus_sub_reserve;
						} else {
							$minus_sub_reserve = $_POST['emoney'];
							$cart_suboption['reserve_one'] = 0; //전액 사용으로 지급안함.
						}
						$reserve_policy_log .= sprintf("[제한조건C 지급 마일리지 : %s]", get_currency_price($cart_suboption['reserve_one']));
					}

					// 마일리지 정책 A 가 아닐경우 정책명을 제일 앞에 표시
					if ($cart_suboption['reserve_log'] && $reserve_policy_log) $cart_suboption['reserve_log'] = $reserve_policy_log." / ".$cart_suboption['reserve_log'];

					$cart_suboption['reserve']	= $cart_suboption['reserve_one']*$cart_suboption['ea'];
					$total_reserve				+= $cart_suboption['reserve'];
					$cart_suboption['point']	= $cart_suboption['point_one'] * $cart_suboption['ea'];
					$total_point				+= $cart_suboption['point'];
					$total_real_sale_price		+= $cart_suboption['sale_price'] * $cart_suboption['ea'];

					$scripts[] = '$("span#suboption_reserve_'.$cart_suboption['cart_suboption_seq'].'",parent.document).html("'.get_currency_price($cart_suboption['reserve']).'");';
					$scripts[] = '$("span#suboption_point_'.$cart_suboption['cart_suboption_seq'].'",parent.document).html("'.get_currency_price($cart_suboption['point']).'");';
					$scripts[] = '$("span#member_sale_suboption_'.$cart_suboption['cart_suboption_seq'].'",parent.document).html("'.get_currency_price($cart_suboption['member_sale']).'");';
					$scripts[] = '$("span#cart_suboption_price_'.$cart_suboption['cart_suboption_seq'].'",parent.document).html("'.get_currency_price($sale_suboption_price*$cart_suboption['ea']).'");';

					if($cart_suboption['member_sale']){
						$scripts[] = '$("#suboption_member_sale_tr_'.$cart_suboption['cart_suboption_seq'].'",parent.document).show();';
						$scripts[] = '$("#sale_suboption_'.$cart_suboption['cart_suboption_seq'].'",parent.document).hide();';
					}else{
						$scripts[] = '$("#suboption_member_sale_tr_'.$cart_suboption['cart_suboption_seq'].'",parent.document).hide();';
						$scripts[] = '$("#sale_suboption_'.$cart_suboption['cart_suboption_seq'].'",parent.document).show();';
					}

					################# 2014-10-31 변경된 장바구니 모양으로 인해 추가
					// 추가구성옵션 전체 할인금액
					if	($subsaletotalprice > 0){
						$scripts[] = '$("#cart_suboption_sale_total_'.$cart_suboption['cart_suboption_seq'].'",parent.document).html("<span class=\"desc\">(-)</span> '.get_currency_price($subsaletotalprice,2).'");';
						$scripts[] = '$("#cart_suboption_sale_detail_'.$cart_suboption['cart_suboption_seq'].'",parent.document).show();';
					}else{
						$scripts[] = '$("#cart_suboption_sale_total_'.$cart_suboption['cart_suboption_seq'].'",parent.document).html("-");';
						$scripts[] = '$("#cart_suboption_sale_detail_'.$cart_suboption['cart_suboption_seq'].'",parent.document).hide();';
					}
					// 추가구성옵션 할인내역
					if	($subsalelist)foreach($subsalelist as $tmp_type => $tmp_price){
						if	($tmp_price > 0){
							$scripts[] = '$("#cart_suboption_'.$tmp_type.'_saletr_'.$cart_suboption['cart_suboption_seq'].'",parent.document).show();';
							$scripts[] = '$("span#cart_suboption_'.$tmp_type.'_saleprice_'.$cart_suboption['cart_suboption_seq'].'",parent.document).html("'.get_currency_price($tmp_price).'");';
						}else{
							$scripts[] = '$("#cart_suboption_'.$tmp_type.'_saletr_'.$cart_suboption['cart_suboption_seq'].'",parent.document).hide();';
							$scripts[] = '$("span#cart_suboption_'.$tmp_type.'_saleprice_'.$cart_suboption['cart_suboption_seq'].'",parent.document).html(0);';
						}
					}
					################# 2014-10-31 변경된 장바구니 모양으로 인해 추가

					$data['cart_suboptions'][$k] = $cart_suboption;
					$data['option_suboption_price_sum'] += $cart_suboption['sale_price']*$cart_suboption['ea'];
					$data['option_suboption_price_sum_origin'] += $cart_suboption['price']*$cart_suboption['ea'];

					# 과세/비과세별 상품 판매액
					$tax_price[$data['tax']]['price'] += $cart_suboption['sale_price'];
				}
			}

			$data['cart_sale'] = $data['event_sale'] + $data['multi_sale'] + $data['member_sale'] + $data['mobile_sale'] + $data['fblike_sale'] + $data['promotion_code_sale'] + $data['coupon_sale'] + $data['referer_sale'] + $data['unit_ordersheet'];

			################# 2014-10-31 변경된 장바구니 모양으로 인해 추가

			// 필수옵션 할인내역
			if	($optsalelist){
				$tmp_price_sum	= 0;
				foreach($optsalelist as $tmp_type => $tmp_price){
					if	($tmp_price > 0){
						$tmp_price_sum	+= $tmp_price;
						$scripts[]		= '$("#cart_option_'.$tmp_type.'_saletr_'.$data['cart_option_seq'].'",parent.document).show();';
						$scripts[]		= '$("span#cart_option_'.$tmp_type.'_saleprice_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($tmp_price).'");';
					}else{
						$scripts[]		= '$("#cart_option_'.$tmp_type.'_saletr_'.$data['cart_option_seq'].'",parent.document).hide();';
						$scripts[]		= '$("span#cart_option_'.$tmp_type.'_saleprice_'.$data['cart_option_seq'].'",parent.document).html(0);';
					}
				}
				if	($tmp_price_sum > 0){
					$scripts[]		= '$("#cart_option_sale_detail_'.$data['cart_option_seq'].'", parent.document).show();';
					$scripts[]		= '$("#cart_option_sale_total_'.$data['cart_option_seq'].'_2", parent.document).html(" '.get_currency_price($tmp_price_sum,2).'");';
				}else{
					$scripts[]		= '$("#cart_option_sale_detail_'.$data['cart_option_seq'].'", parent.document).hide();';
					$scripts[]		= '$("#cart_option_sale_total_'.$data['cart_option_seq'].'", parent.document).html("-");';
					$scripts[]		= '$("#cart_option_sale_total_'.$data['cart_option_seq'].'_2", parent.document).html("-");';
				}
			}
			// 필수옵션+추가구성옵션 할인내역
			if	($moptsalelist){
				$mtmp_price_sum	= 0;
				foreach($moptsalelist as $tmp_type => $tmp_price){
					if	($tmp_price > 0){
						$mtmp_price_sum	+= $tmp_price;
						$scripts[]		= '$("#cart_sum_'.$tmp_type.'_saletr_'.$data['cart_option_seq'].'",parent.document).show();';
						$scripts[]		= '$("span#cart_sum_'.$tmp_type.'_saleprice_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($tmp_price).'");';
					}else{
						$scripts[]		 = '$("#cart_sum_'.$tmp_type.'_saletr_'.$data['cart_option_seq'].'",parent.document).hide();';
						$scripts[]		= '$("span#cart_sum_'.$tmp_type.'_saleprice_'.$data['cart_option_seq'].'",parent.document).html(0);';
					}
				}
				if	($mtmp_price_sum > 0){
					$scripts[]		= '$("#cart_sum_sale_tr_'.$data['cart_option_seq'].'", parent.document).show();';
					$scripts[]		= '$("span#cart_sum_sale_'.$data['cart_option_seq'].'", parent.document).html("'.get_currency_price($mtmp_price_sum).'");';
				}else{
					$scripts[]		= '$("#cart_sum_sale_tr_'.$data['cart_option_seq'].'", parent.document).hide();';
					$scripts[]		= '$("span#cart_sum_sale_'.$data['cart_option_seq'].'", parent.document).html("0");';
				}
			}

			// 반응형 스킨일 경우 필수옵션+추가구성옵션 할인금액으로 적용
			if($this->operation_type === 'light') {
			    if	($mtmp_price_sum > 0){
			        $scripts[]		= '$("#cart_option_sale_total_'.$data['cart_option_seq'].'", parent.document).html("<span class=\"desc\">(-)</span> '.get_currency_price($mtmp_price_sum,2).'");';
			    }
			} else { // 전용 스킨일 경우 필수옵션 할인금액으로 적용
			    if	($tmp_price_sum > 0){
			        $scripts[]		= '$("#cart_option_sale_total_'.$data['cart_option_seq'].'", parent.document).html("<span class=\"desc\">(-)</span> '.get_currency_price($tmp_price_sum,2).'");';
			    }
			}

			$scripts[] = '$("span.cart_option_price_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['sale_price']*$data['ea']).'");';

			################# 2014-10-31 변경된 장바구니 모양으로 인해 추가



			if( $this->_is_mobile_agent) {//mobile 인 경우에만적용 $this->mobileMode ||
				$scripts[] = '$("#mobile_sale_tr_'.$data['cart_option_seq'].'",parent.document).show();';
				$scripts[] = '$("span#mobile_sale_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['mobile_sale']).'");';
			}
			$scripts[] = '$("span#cart_option_price_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['sale_price']*$data['ea']).'");';

			$scripts[] = '$("span#cart_origin_price_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['price']).'");';

			if($data['fblike_sale']){
				$scripts[] = '$("#fblike_sale_tr_'.$data['cart_option_seq'].'",parent.document).show();';
				$scripts[] = '$("span#fblike_sale_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['fblike_sale']).'");';
			}else{
				$scripts[] = '$("#fblike_sale_tr_'.$data['cart_option_seq'].'",parent.document).hide();';
			}

			if($data['promotion_code_sale']){
				$scripts[] = '$("#promotioncode_sale_tr_'.$data['cart_option_seq'].'",parent.document).show();';
				$scripts[] = '$("span#promotioncode_sale_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['promotion_code_sale']).'");';
			}else{
				$scripts[] = '$("#promotioncode_sale_tr_'.$data['cart_option_seq'].'",parent.document).hide();';
			}
			if($data['coupon_sale']){
				$scripts[] = '$("#coupon_sale_tr_'.$data['cart_option_seq'].'",parent.document).show();';
				$scripts[] = '$("span#coupon_sale_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['coupon_sale']).'");';
			}else{
				$scripts[] = '$("#coupon_sale_tr_'.$data['cart_option_seq'].'",parent.document).hide();';
			}
			if($data['member_sale']){
				$scripts[] = '$("#option_member_sale_tr_'.$data['cart_option_seq'].'",parent.document).show();';
				$scripts[] = '$("span#member_sale_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['member_sale']).'");';
			}else{
				$scripts[] = '$("#option_member_sale_tr_'.$data['cart_option_seq'].'",parent.document).hide();';
			}
			if($data['referer_sale']){
				$scripts[] = '$("#referer_sale_tr_'.$data['cart_option_seq'].'",parent.document).show();';
				$scripts[] = '$("span#referer_sale_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['referer_sale']).'");';
			}else{
				$scripts[] = '$("#referer_sale_tr_'.$data['cart_option_seq'].'",parent.document).hide();';
			}

			if($data['cart_sale']) $scripts[] = '$("#cart_sale_tr_'.$data['cart_option_seq'].'",parent.document).show();';
			$scripts[] = '$("span#cart_sale_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['cart_sale']).'");';

			$scripts[] = '$("span#option_reserve_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['tot_reserve']).'");';
			$scripts[] = '$("span#option_point_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['tot_point']).'");';

			if($data['cart_sale']){
				$scripts[] = '$("#sale_option_'.$data['cart_option_seq'].'",parent.document).hide();';
			}else{
				$scripts[] = '$("#sale_option_'.$data['cart_option_seq'].'",parent.document).show();';
			}

			$scripts[] = '$("span#option_suboption_price_sum_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['option_suboption_price_sum']).'");';

			$scripts[] = '$("span#option_suboption_price_sum_origin_'.$data['cart_option_seq'].'",parent.document).html("'.get_currency_price($data['option_suboption_price_sum_origin']).'");';

			// 상품 무게 계산
			if( $data['shipping_weight_policy'] == "shop" ){
				$goods_weight = $data['goods_weight'] + $international_shipping['defaultGoodsWeight'];
			}else{
				$goods_weight = $data['goods_weight'];
			}

			//$data['goods_weight']	= $goods_weight * $data['ea'];
			$data['goods_weight']	= $goods_weight;
			$cart['list'][$key]		= $data;
			// 입점사별 상품 무게 합산
			$this->provider_goods_weight[$data['provider_seq']] += $data['goods_weight'];

			$total_sales_price			+= get_cutting_price($data['tot_sale_price']);
			$total_mobile_sale			+= get_cutting_price($data['mobile_sale']);
			$total_fblike_sale			+= get_cutting_price($data['fblike_sale']);
			$total_promotion_code_sale	+= get_cutting_price($data['promotion_code_sale']);
			$total_coupon_sale			+= get_cutting_price($data['coupon_sale']);
			$total_member_sale			+= get_cutting_price($data['member_sale']);
			$total_referer_sale			+= get_cutting_price($data['referer_sale']);
			$total_reserve				+= $data['tot_reserve'];
			$total_point				+= $data['tot_point'];
			$total_sale_price			+= $data['cart_sale'];
			$total_goods_weight			+= $data['goods_weight'];

			# 과세/비과세별 상품 판매액/배송비
			$tax_price[$data['tax']]['price']	+= $data['sale_price'];

			$provider_cart[$data['shipping_group']]['provider_price']	+= $data['tot_price'];
			$provider_cart[$data['shipping_group']]['cart_list'][]	= $data;

			// 배송그룹별 상품할인가 합
			$shipping_group_sum_goods_price[$data['shipping_group']] += $data['tot_price'] - $data['cart_sale'];
		}

		// 관리자 주문일 경우 해외배송여부처리
		if( $adminOrder == 'admin' && $is_international_shipping ){
			$scripts[] = '$(".clearance_unique_personal_code",parent.document).show();';
		}elseif( $adminOrder == 'admin' && !$is_international_shipping ){
			$scripts[] = '$(".clearance_unique_personal_code",parent.document).hide();';
		}
		if( $adminOrderType == 'person' && !$is_international_shipping ){
			$scripts[] = '$(".clearance_unique_personal_code",parent.document).hide();';
		}

		if( is_array($coupon_same_time_n) && count($coupon_same_time_n) > 0 ){//단독쿠폰체크
			if( count($coupon_same_time_n) != 1 || ( count($coupon_same_time_n) > 0 && count($coupon_same_time_y) > 0) ) {
				//단독쿠폰은 다른쿠폰과 동시에 사용하실 수 없습니다. <br/>쿠폰을 다시한번 선택해 주세요.
				$err_coupon = getAlert('os011');
				$err_coupon_callback = 'parent.sametime_coupon_dialog();'.$pg_cancel_script;
				openDialogAlert($err_coupon,400,140,'parent',$err_coupon_callback);
				exit;
			}elseif( count($coupon_same_time_n) == 1 && $coupon_same_time_n_duplication_n && $coupon_same_time_n_duplication_n[$coupon_same_time_n[0]] > 1 ) {//단독이면서 중복쿠폰이 아닌경우
				//해당 단독쿠폰은 중복으로 사용하실 수 없습니다. <br/>다시한번 선택해 주세요.
				$err_coupon = getAlert('os012');
				$err_coupon_callback = 'parent.sametime_coupon_dialog();'.$pg_cancel_script;
				openDialogAlert($err_coupon,400,140,'parent',$err_coupon_callback);
				exit;
			}
		}

		$total_sale_price					+= $total_sale_suboption;
		$cart['total_mobile_sale']			= $total_mobile_sale;
		$cart['total_fblike_sale']			= $total_fblike_sale;
		$cart['total_promotion_code_sale']	= $total_promotion_code_sale;
		$cart['total_coupon_sale']			= $total_coupon_sale;
		$cart['total_member_sale']			= $total_member_sale;
		$cart['total_referer_sale']			= $total_referer_sale;
		$cart['total_reserve']				= $total_reserve;
		$cart['total_real_sale_price']		= $total_real_sale_price; //총실결제금액합계@2014-07-04
		$cart['total_point']				= $total_point;
		$cart['total_sale_price']			= $total_sale_price;
		$cart['total_goods_weight']			= $total_goods_weight;

		// 할인적용가 기준 배송비 계산
		$total_goods_price = $cart['total'] - $cart['total_sale_price'];
		if($cart['shop_shipping_policy']['free']){
			$cart['shipping_price']['shop'] = get_cutting_price($cart['shop_shipping_policy']['price']);
			if($cart['shop_shipping_policy']['free'] <= $total_goods_price){
				$cart['shipping_price']['shop'] = 0;
			}
		}

		### WONY ------- 배송비 계산 및 this 저장--------- ### shipping library 계산 - calculator

		// ### Mobile 예외처리 :: 2017-08-10 lwh
		if($this->mobileMode){
			$_POST['recipient_address_street']	= ($_POST['recipient_input_address_street']) ? $_POST['recipient_input_address_street'] : $_POST['recipient_address_street'];
			$_POST['recipient_address']			= ($_POST['recipient_input_address']) ? $_POST['recipient_input_address'] : $_POST['recipient_address'];
			$_POST['recipient_address_detail']	= ($_POST['recipient_input_address_detail']) ? $_POST['recipient_input_address_detail'] : $_POST['recipient_address_detail'];
		}

		// ### NEW 배송 그룹 정보 추출 ### :: START ### ----------- 여기서부터
		$this->load->library('shipping');
		if		($_POST['address_nation'])	$ship_ini['nation']	= $_POST['address_nation'];
		if		($_POST['recipient_address_street']) // 주소값 지정
			$ship_ini['street_address']	= $_POST['recipient_address_street'];
		if		($_POST['recipient_address'])
			$ship_ini['zibun_address']	= $_POST['recipient_address'];
		$ini_info				= $this->shipping->set_ini($ship_ini);
		$shipping_group_list	= $this->shipping->get_shipping_groupping($cart['list']);

		// 상세 배송비 금액 추출
		$shipping_cost_detail	= $shipping_group_list['shipping_cost_detail'];
		unset($shipping_group_list['shipping_cost_detail']);

		// 전체 배송비 금액 추출
		$total_shipping_price	= $shipping_group_list['total_shipping_price'];
		unset($shipping_group_list['total_shipping_price']);

		// 최종 결제 금액 재계산
		$cart['total_price'] = $cart['total_price'] + $total_shipping_price;

		// 배송가능 해외국가 추출
		$ship_gl_arr	= $this->shippingmodel->get_gl_shipping();
		$ship_gl_list	= $this->shippingmodel->split_nation_str($ship_gl_arr);

		// 배송결과 Parent 에 Return :: START
		$ship_possible			= true;
		$ship_region_possible	= true;

		foreach($shipping_group_list as $shipKey => $shipinfo){
			$scripts[] = '$(".grp_shipping_'.$shipKey.'",parent.document).find(".shipper_name").html("'.$shipinfo['shipper_name'].'");';
			$scripts[] = '$(".grp_shipping_'.$shipKey.'",parent.document).find(".shipping_set_name").html("'.$shipinfo['cfg']['baserule']['shipping_set_name'].'");';
			if	($shipinfo['grp_shipping_price'] > 0 && $shipinfo['ship_possible'] == 'Y'){
				$scripts[] = '$(".grp_shipping_price_'.$shipKey.'",parent.document).removeClass("red");';
				$scripts[] = '$(".grp_shipping_price_'.$shipKey.'",parent.document).html("'.get_currency_price($shipinfo['grp_shipping_price'],2).'");';
				$altInfo = ''.getAlert("os239").': ' . get_currency_price($shipinfo['shipping_std_cost'],2);    // 기본배송비
				$altInfo .= '<br/>'.getAlert("os240").': ' . get_currency_price($shipinfo['shipping_add_cost'],2);  // 추가배송비
				$altInfo .= '<br/>'.getAlert("os247").': ' . get_currency_price($shipinfo['shipping_hop_cost'],2);  // 희망배송비

				$scripts[] = '$(".prepay_info",parent.document).show();';

				// 총 배송비 계산 :: 2017-09-28 lwh
				$ship_price[$shipinfo['shipping_prepay_info']]['total'] += $shipinfo['grp_shipping_price'];
				$ship_price[$shipinfo['shipping_prepay_info']]['std'] += $shipinfo['shipping_std_cost'];
				$ship_price[$shipinfo['shipping_prepay_info']]['add'] += $shipinfo['shipping_add_cost'];
				$ship_price[$shipinfo['shipping_prepay_info']]['hop'] += $shipinfo['shipping_hop_cost'];
			}else{
				if			($shipinfo['ship_possible'] == 'Y') {
					$scripts[] = '$(".grp_shipping_price_'.$shipKey.'",parent.document).removeClass("red");';
					if($shipinfo['cfg']['baserule']['shipping_set_code'] == 'coupon'){
						$scripts[] = '$(".grp_shipping_price_'.$shipKey.'",parent.document).html("'.getAlert("os241").'");';    // 티켓발송
						$altInfo = ''.getAlert("os241").'';    // 티켓발송
					}else{
						$scripts[] = '$(".grp_shipping_price_'.$shipKey.'",parent.document).html("'.getAlert("os242").'");';    // 무료
						$altInfo = ''.getAlert("os243").'';    // 무료배송
					}
					$scripts[] = '$(".prepay_info",parent.document).hide();';
				}else if	($shipinfo['ship_possible'] == 'H') {
					$scripts[] = '$(".grp_shipping_price_'.$shipKey.'",parent.document).addClass("red");';
					$scripts[] = '$(".grp_shipping_price_'.$shipKey.'",parent.document).html("'.getAlert("os242").'");';    // 배송불가
					$altInfo = ''.getAlert("os243").''; // 배송 불가지역
					$ship_possible = false;
					$this->ship_possible = "N";	//비회원 결제시 배송 제한 주소 확인 20170605 ldb
					$scripts[] = '$(".prepay_info",parent.document).hide();';
				}else{
					$scripts[] = '$(".grp_shipping_price_'.$shipKey.'",parent.document).addClass("red");';
					$scripts[] = '$(".grp_shipping_price_'.$shipKey.'",parent.document).html("'.getAlert("os242").'");';    // 배송불가
					$altInfo = ''.getAlert("os244").'';  // 희망배송일 배송 불가지역
					$ship_possible = false;
					$ship_region_possible	= false;
					$this->ship_possible = "N";	//비회원 결제시 배송 제한 주소 확인 20170605 ldb
					$scripts[] = '$(".prepay_info",parent.document).hide();';
				}
			}

			// class의 손실로 인한 중복 변경 :: 2017-01-02 lwh
			$scripts[] = '$(".priceInfo_'.$shipKey.'",parent.document).html("'.$altInfo.'");';
			$scripts[] = '$(".grp_shipping_'.$shipKey.'",parent.document).find("div.layer_inner").html("'.$altInfo.'");';

			// 배송비 관련 Global 변수 재 가공 :: 2016-08-08 lwh
			$shipping_provider_seq = $shipinfo['cfg']['baserule']['shipping_provider_seq'];
			$this->shipping_group_cost[$shipKey]['shop'] = $shipinfo['grp_shipping_price'];
			$this->shipping_group_policy[$shipKey]['policy'] = 'shop';
			$this->shipping_group_policy[$shipKey]['provider_seq'] = $shipping_provider_seq;
			$this->shipping_group_policy[$shipKey]['shipping_cfg'] = $shipinfo;
			$this->shipping_group_policy[$shipKey]['shipping_ini'] = $ship_ini;
			$this->shipping_group_policy[$shipKey]['prepay_info'] = $shipinfo['shipping_prepay_info'];
			$this->shipping_group_policy[$shipKey]['price'] = $shipinfo['grp_shipping_price'];

			$this->provider_shipping_cost[$shipping_provider_seq] += (int) $shipinfo['grp_shipping_price'];

			// 쿠폰 관련 배송비 계산 추가 :: 2017-05-17 lwh
			$provider_cart[$shipKey]['grp_shipping_price'] = ($shipinfo['shipping_prepay_info'] == 'delivery') ? $shipinfo['grp_shipping_price'] : '0';
			$provider_cart[$shipKey]['shipping_provider_seq'] = $shipping_provider_seq;

			// 구) 배송비 수정관련 추가사항 :: START 2016-10-31 lwh
			$scripts[] = '$("#price_'.$shipKey.'",parent.document).html("' . get_currency_price($shipinfo['grp_shipping_price'],2) . '");';

			if($shipinfo['cfg']['baserule']['shipping_calcul_type'] == 'each'){
				$goods_delivery += $shipinfo['grp_shipping_price']; // 개별배송비
			}else{
				$basic_delivery += $shipinfo['grp_shipping_price']; // 기본배송비
			}
			// 구) 배송비 수정관련 추가사항 :: END
		}

		// 구) 배송비 수정관련 추가사항 :: START 2016-10-31 lwh
		$scripts[] = '$(".total_org_shipping_price",parent.document).html("' . get_currency_price($total_shipping_price) . '");';
		$scripts[]		= '$("span.goods_delivery", parent.document).html("'.get_currency_price($goods_delivery).'");';
		$scripts[]		= '$("span.basic_delivery", parent.document).html("'.get_currency_price($basic_delivery).'");';
		if($total_shipping_price < 1){
			$scripts[] = '$(".total_org_shipping_price_btn",parent.document).hide();';
		}
		// 구) 배송비 수정관련 추가사항 :: END

		// 배송 불가 항목 체크.
		if($ship_possible){
			$scripts[] = '$("#ship_possible",parent.document).val("Y");';
			$scripts[] = '$(".ship_possible",parent.document).addClass("hide");';
		}else{
			$scripts[] = '$("#ship_possible",parent.document).val("N");';
			$scripts[] = '$(".ship_possible",parent.document).removeClass("hide");';
			$total_shipping_price = 0;
			if(!$ship_region_possible){
				$scripts[] = '$(".kr_nation_region",parent.document).html(" - ' . getAlert('dv006') . '");';
			}
		}

		// 쿠폰 사용 후 배송비 변경 확인 :: 2017-07-11 lwh
		$scripts[] = 'var download_chk = 0;';
		$scripts[] = '$(".shippingcoupon_download_input",parent.document).each(function(){ download_chk += $(this).val();});';
		$scripts[] = 'download_chk += $("input[name=\'emoney_view\']",parent.document).val();';
		$scripts[] = 'download_chk += $("input[name=\'cash_view\']",parent.document).val();';
		$scripts[] = 'if(download_chk > 0 ){';
		$scripts[] = "var new_ship_price = '".number_format($total_shipping_price)."';";
		$scripts[] = 'var old_ship_price = $(".total_delivery_shipping_price",parent.document).html()||0;';
		$scripts[] = 'if(new_ship_price != old_ship_price) parent.reset_coupon();';
		$scripts[] = '}';

		// 상세 배송비 재정의 :: 2017-09-28 lwh
		foreach($ship_price as $prepay_info => $ship_detail){
			$scripts[] = '$(".'.$prepay_info.'_tot_price",parent.document).html("'.get_currency_price($ship_detail['total']).'");';
			$scripts[] = '$(".std_'.$prepay_info.'_price",parent.document).html("'.get_currency_price($ship_detail['std']).'");';
			$scripts[] = '$(".add_'.$prepay_info.'_price",parent.document).html("'.get_currency_price($ship_detail['add']).'");';
			$scripts[] = '$(".hop_'.$prepay_info.'_price",parent.document).html("'.get_currency_price($ship_detail['hop']).'");';
		}

		// 총 배송비
		$scripts[] = '$(".total_delivery_shipping_price",parent.document).html("'.get_currency_price($total_shipping_price).'");';
		$this->shipping_cost = $total_shipping_price;

		// 배송국가 선택된 국기 img 변경
		$nation_img = "/admin/skin/default/images/common/icon/nation/".$ini_info['nation'].".png";
		$scripts[] = '$("#nation_img",parent.document).attr("src","'.$nation_img.'");';
		if($ini_info['nation'] == 'KOREA'){
			$scripts[] = '$("#nation_gl_type",parent.document).addClass("hide");';
		}else{
			$scripts[] = '$("#nation_gl_type",parent.document).removeClass("hide");';
		}
		// 배송결과 Parent 에 Return :: END
		// ### NEW 배송 그룹 정보 추출 및 계산 :: END ### ----------- 여기까지

		//프로모션코드 배송비할인2
		if($this->session->userdata('cart_promotioncode_'.session_id())) {
			$shipping_promotions = $this->promotionmodel->get_able_download_saleprice($this->session->userdata('cart_promotioncodeseq_'.session_id()),$this->session->userdata('cart_promotioncode_'.session_id()), $cart['total'], '','');
		}

		//프로모션코드 본사배송상품 배송비할인
		$this->shipping_promotion_code_sale	= array();
		if($total_shipping_price > 0 && $shipping_promotions) {//본사배송상품
			foreach($this->provider_shipping_cost as $provider_seq => $shipping_cost){

				//이벤트 배송비코드 사용제한 @2015-08-13
				if( $this->ordernosales_cd_sh[$provider_seq] ) continue;

				$codesales_use		= 'N';
				$shippingcode_sale	= 0;
				if		(!$shipping_promotions['provider_list'] > 0 && $provider_seq == 1)
					$codesales_use	= 'A';
				elseif	($shipping_promotions['provider_list'] && strstr($shipping_promotions['provider_list'], '|'.$provider_seq.'|'))
					$codesales_use	= 'P';

				if		($codesales_use == 'A' || $codesales_use == 'P'){
					if($shipping_promotions['sale_type'] == 'shipping_free' &&  $shipping_cost > 0)		{
						$shippingcode_sale	= ($shipping_cost < $shipping_promotions['promotioncode_shipping_sale_max'])	? $shipping_cost : $shipping_promotions['promotioncode_shipping_sale_max'];
					}elseif($shipping_promotions['sale_type'] == 'shipping_won' && $shipping_cost > 0 && $shipping_cost >= $shipping_promotions['promotioncode_shipping_sale'])	{
						$shippingcode_sale	= $shipping_promotions['promotioncode_shipping_sale'];
					}

					if	($shippingcode_sale > 0){
						$this->shipping_promotion_code_sale[$provider_seq]	= $shippingcode_sale;
						$this->shipping_promotion_code_sale_provider[$provider_seq]	= $shipping_promotions['salescost_provider'];
						$this->shipping_promotion_code_seq[$provider_seq]	= $shipping_promotions['promotion_seq'];
						$this->shipping_cost								-= $shippingcode_sale;
						$total_shipping_price								-= $shippingcode_sale;

						$this->shipping_promotion_code_salecost[$provider_seq]	= ($codesales_use == 'A')	? 0 : get_cutting_price($shippingcode_sale * ($shipping_promotions['salescost_provider']/100));
					}
				}
			}
		}

		//배송비쿠폰 할인
		if( $_POST['shippingcoupon_download'] && $total_shipping_price > 0 ) {
			$this->shipping_coupon_payment_b = false;
			$this->shippingcoupon_download_ck = false;
			foreach($_POST['shippingcoupon_download'] as $shipKey => $download_seq) {

				$provider_seq = $this->shipping_group_policy[$shipKey]['provider_seq'];

				//이벤트 배송비쿠폰 사용제한 @2015-08-13
				if( $this->ordernosales_cp_sh[$provider_seq] ) continue;

				$shippingcoupons = $this->couponmodel->get_download_coupon($download_seq);
				if	($shippingcoupons){
					if	($shippingcoupons['shipping_type'] == 'free'){
						if	($shippingcoupons['max_percent_shipping_sale'] <= $this->shipping_group_cost[$shipKey]['shop']){
							$shippingcoupon_sale	= $shippingcoupons['max_percent_shipping_sale'];
						}else{
							$shippingcoupon_sale	= $this->shipping_group_cost[$shipKey]['shop'];
						}
					}else{
						if	($shippingcoupons['won_shipping_sale'] <= $this->shipping_group_cost[$shipKey]['shop']){
							$shippingcoupon_sale	= $shippingcoupons['won_shipping_sale'];
						}else{
							$shippingcoupon_sale	= $this->shipping_group_cost[$shipKey]['shop'];
						}
					}

					if($this->shippingcoupon_download_ck === false && $this->arr_goods_shipping_price >0 ) $this->shippingcoupon_download_ck = true;

					if	($shippingcoupon_sale > 0) {

						//무통장만 사용가능
						if($shippingcoupons['sale_payment'] == 'b' && $this->shipping_coupon_payment_b != true )
							$this->shipping_coupon_payment_b = true;

						if( ( $shippingcoupons['type'] == 'memberGroup_shipping' || $shippingcoupons['type'] == 'member_shipping' || $shippingcoupons['type'] == 'memberlogin_shipping' || $shippingcoupons['type'] == 'membermonths_shipping' ) && $provider_seq == 1 ) {//배송그룹이 본사인경우 0
							$shippingcoupons['salescost_provider'] = 0;
						}
						$salescost										= get_cutting_price($shippingcoupon_sale * ($shippingcoupons['salescost_provider']/100));
						$total_shipping_price							-= $shippingcoupon_sale;
						$this->shipping_cost							-= $shippingcoupon_sale;
						$this->shipping_coupon_salecost[$shipKey]	= $salescost;
						$this->shipping_coupon_sale[$shipKey]		= $shippingcoupon_sale;
						$this->shipping_coupon_sale_provider[$shipKey]	= $shippingcoupons['salescost_provider'];
						$this->shipping_coupon_down_seq[$shipKey]	= $download_seq;
					}
				}
			}
		}

		//배송비쿠폰은 개별배송비 상품이 있을 경우 제외안내
		if( !$this->displaymode &&  $this->shippingcoupon_download_ck === true && $_POST['shippingcoupon_download'] ) {
			//배송비 쿠폰은 개별배송 상품에는 반영되지 않습니다.
			openDialogAlert(getAlert('os013'),400,140,'parent',$pg_cancel_script);
		}


		//쿠폰>사용제한>무통장만가능
		if( is_array($coupon_sale_payment_b) || $this->shipping_coupon_payment_b ){
			$cart['coupon_sale_payment_b'] = count($coupon_sale_payment_b);
			if( $this->shipping_coupon_payment_b === true ) $cart['coupon_sale_payment_b'] = (int) ($cart['coupon_sale_payment_b'] + 1);
		}

		if($cart['coupon_sale_payment_b']  && $_POST['payment'] != 'bank' && !$this->displaymode ) {
			//현재 무통장 전용 쿠폰을 사용하셨습니다.<br />결제수단을 무통장으로 변경해 주세요!
			openDialogAlert(getAlert('os014'),400,140,'parent',$pg_cancel_script);
			exit;
		}

		//쿠폰>사용제한>모바일/테블릿기기만가능
		if( is_array($coupon_sale_agent_m)){
			$cart['coupon_sale_agent_m'] = count($coupon_sale_agent_m);
		}

		// 에누리
		if($person_seq != ""){
			$query	= $this->db->query("select * from fm_person where person_seq='".$person_seq."'");
			$res	= $query->row_array();
			$enuri	= $res['enuri'];
		}elseif($adminOrder == "admin" && isset($_POST['enuri']) && $_POST['enuri'] > 0) {
			//#20282 2018-07-27 ycg 개인 결제 생성시 에누리 소수점 적용 안되는 문제 수정
			$enuri = get_cutting_price($_POST['enuri']);
		}else{
			$enuri	= 0;
		}

		// enuri가 더 큰 경우 최대값으로 변경
		if	(($cart['total'] - $cart['total_sale_price'] + $total_shipping_price) < $enuri){
			$enuri	= $cart['total'] - $cart['total_sale_price'] + $total_shipping_price;
			echo '<script>
					$("input[name=\'enuri\']",parent.document).val("'.$enuri.'");
				</script>';
		}

		/* 총 결제금액 */
		$settle_price = $cart['total'] - $cart['total_sale_price'] + $total_shipping_price - $enuri;

		if($settle_price<0)$settle_price=0;


		/* 주문금액 */
		$this->order_price = $cart['total'] + $total_shipping_price;

		/* 캐쉬 사용할 수 있는 금액 계산*/
		if( $members && ($_POST['cash'] > 0 || $_POST['cash_all']) ){
			$reserve_use = true;

			// 마일리지 전액사용
			if($_POST['cash_all']){
				$_POST['cash'] = $this->ordermodel->get_usable_cash($cart['total'],$settle_price-$_POST['emoney'],$members['cash']);
				if($_POST['cash']){
					echo '<script>
					$("input[name=\'cash\']",parent.document).val("'.$_POST['cash'].'");
					$("input[name=\'cash_view\']",parent.document).val("'.$_POST['cash'].'");
					</script>';
				}else{
					$reserve_use = false;
					//예치금를 사용하실 수 없습니다.
					$err_reserve = getAlert('os015');
				}
			}

			if( $_POST['cash'] > $settle_price ){
				$reserve_use = false;
				//"최대 ".number_format($settle_price)."원까지 사용가능 합니다."
				$err_reserve = getAlert('os016',get_currency_price($settle_price,2));
			}

			if( $_POST['cash'] > $members['cash'] ){
				$reserve_use = false;
				//number_format( $members['cash'] )."원 이상 사용하실 수 없습니다."
				$err_reserve = getAlert('os017',get_currency_price( $members['cash'] ,2));
			}

			if(!$this->displaymode){
				if($err_reserve){
					echo '<script>
					$("input[name=\'cash\']",parent.document).val(0);
					$("input[name=\'cash_view\']",parent.document).val(0);
					$("input[name=\'cash_all\']",parent.document).val("");
					$(".cash_cancel_button",parent.document).hide();
					$(".cash_input_button",parent.document).show();
					$(".cash_all_input_button",parent.document).show();
					</script>';
					openDialogAlert($err_reserve,400,140,'parent',$pg_cancel_script);
					exit;
				}else{
					echo '<script>
					$("input[name=\'cash_all\']",parent.document).val("");
					</script>';
				}
				if($_POST['cash'] > 0){
					echo '<script>
					$("#priceCashTd").show();
					$("#total_cash").html("'.get_currency_price($_POST['cash']).'");
					</script>';
				}
			}
			$cart['cash'] = get_cutting_price($_POST['cash']);
			$settle_price -= get_cutting_price($cart['cash']);
		}

		$err_reserve = '';
		if( $members && ($_POST['emoney'] > 0 || $_POST['emoney_all']) ){

			$reserve_use = true;
			$reserves = ($this->reserves)?$this->reserves:config_load('reserve');
			/**if(!$reserves) {//마일리지 미설정시 @2017-02-09
				$reserve_use = false;
				//마일리지을 사용하실 수 없습니다.
				$err_reserve = getAlert('os018');
			}**/

			// 마일리지 전액사용
			if($_POST['emoney_all'] && $reserve_use===true){

				/* 마일리지 전액사용 클릭시 사용금액을 0원 처리할 경우 에러메세지를 받기 위해 리턴값을 배열로 받음 leewh 2014-11-12 */
				// 총금액이 아닌 할인적용 금액으로 제한 체크 함 2019-02-14 hyem
				$returnInfo = $this->ordermodel->get_usable_emoney($cart['total']-$cart['total_sale_price'],$settle_price,$members['emoney']);
				$_POST['emoney'] = $returnInfo['emoney'];

				if($_POST['emoney']){
					echo '<script>
					$("input[name=\'emoney\']",parent.document).val("'.$_POST['emoney'].'");
					$("input[name=\'emoney_view\']",parent.document).val("'.get_currency_price($_POST['emoney']).'");
					</script>';
				}else{
					$reserve_use = false;
					if ($returnInfo['err_reserve']) {
						$err_reserve = $returnInfo['err_reserve'];
					} else {
						//마일리지을 사용하실 수 없습니다.
						$err_reserve = getAlert('os018');
					}
				}
			}

			/* 마일리지 사용 단위 ordermodel로 옮김 @2016-06-08 pjm */
			list($reserve_use,$err_reserve,$cutting_using_unit) = $this->ordermodel->get_cutting_emoney($_POST['emoney'],$members['emoney'],$settle_price,$cart['total']-$cart['total_sale_price'],$reserve_use,$err_reserve);

			if($err_reserve && $cutting_using_unit == 0){
				echo '<script>
						$("input[name=\'emoney\']",parent.document).val(0);
						$("input[name=\'emoney_view\']",parent.document).val(0);
						$("input[name=\'emoney_all\']",parent.document).val("");
						$(".emoney_cancel_button",parent.document).hide();
						$(".emoney_input_button",parent.document).show();
						$(".emoney_all_input_button",parent.document).show();
						$("#priceEmoneyTd",parent.document).hide();
					</script>';
				openDialogAlert($err_reserve,400,140,'parent',$pg_cancel_script);
				exit;
			}else{
				if	($err_reserve) {
					echo '<script>
							$("input[name=\'emoney\']",parent.document).val("'.$cutting_using_unit.'");
							$("input[name=\'emoney_view\']",parent.document).val("'.get_currency_price($cutting_using_unit).'");
						</script>';
					openDialogAlert($err_reserve,400,140,'parent',$pg_cancel_script);
					$_POST['emoney'] = $cutting_using_unit;
				}

				if	(!$this->displaymode) {
					echo '<script>
					$("input[name=\'emoney_all\']",parent.document).val("");
					</script>';
				}
			}

			$cart['emoney']		= $_POST['emoney'];
			$settle_price		-= $cart['emoney'];
		}

		$this->amount = $settle_price;
/*
		// 네이버 마일리지
		$this->load->model('navermileagemodel');
		$settle_price = $this->navermileagemodel->check_mileage($settle_price);
*/

		# 사용한 마일리지/예치금
		$use_emonay = $cart['emoney'];
		$use_cash	= $cart['cash'];

		/* 상품결제가합 */
		$this->sum_goods_price			= get_cutting_price($cart['total'],'basic');
		$this->settle_price				= get_cutting_price($settle_price,'basic');
		$this->shipping_price			= $shipping_price;//@2016-08-16 ysm
		$cart['total_price']			= $settle_price;

		$this->cart = $cart;

		// 마일리지 합계 출력
		if($tot_reserve){
			$scripts[] = '$("#tot_reserve",parent.document).html("'.get_currency_price($tot_reserve).'");';
		}

		/* 구매 시 마일리지액 제한 조건 추가 leewh 2014-06-25 */
		if ($cfg_reserve['default_reserve_limit']>0) {
			$scripts[] = 'if (!$("#default_reserve_limit", parent.document).length) $("form#orderFrm", parent.document).append("<input type=\'hidden\' name=\'default_reserve_limit\' id=\'default_reserve_limit\' value=\''.$cfg_reserve['default_reserve_limit'].'\' />");';

			/* 마일리지액 제한 조건 C설정 */
			if ($cfg_reserve['default_reserve_limit']==2) {
				$scripts[] = 'if (!$("#appointed_reserve", parent.document).length) $("form#orderFrm", parent.document).append("<input type=\'hidden\' name=\'appointed_reserve\' id=\'appointed_reserve\' value=\''.$cart['total_reserve'].'\' />");';
			} else if ($cfg_reserve['default_reserve_limit']==3) {
				/* 마일리지액 제한 조건 B설정 */
				$scripts[] = 'if (!$("#total_real_sale_price", parent.document).length) {$("form#orderFrm", parent.document).append("<input type=\'hidden\' name=\'total_real_sale_price\' id=\'total_real_sale_price\' value=\''.$cart['total_real_sale_price'].'\' />");} else {$("form#orderFrm #total_real_sale_price", parent.document).val('.$cart['total_real_sale_price'].');}';
			}
		}

		/* 쿠폰이 무통장만 사용가능함 @2014-07-09 */
		if( $cart['coupon_sale_payment_b'] ) {
			$scripts[] = 'if (!$("#coupon_sale_payment_b", parent.document).length) {$("form#orderFrm", parent.document).append("<input type=\'hidden\' name=\'coupon_sale_payment_b\' id=\'coupon_sale_payment_b\' value=\''.$cart['coupon_sale_payment_b'].'\' />");} else {$("form#orderFrm #coupon_sale_payment_b", parent.document).val('.$cart['coupon_sale_payment_b'].');}';
		}

		$scripts[] = '$("#total_sale, .total_sale",parent.document).html("'.get_currency_price($cart['total_sale_price']).'");';
		$scripts[] = '$(".settle_price",parent.document).html("'.get_currency_price($cart['total_price']).'");';
		// 총 결제액의 비교통화 변경 @2016-07-14 pjm
		$this->template->include_('showCompareCurrency');
		if($this->mobileMode){
			$total_price_compare = addcslashes(str_replace("\r\n","",showCompareCurrency('',$cart['total_price'],'return')));
			$scripts[] = "$('.settle_price_compare .currency_compare_lay .currency_open',parent.document).html('".$total_price_compare."');";
		}else{
			$total_price_compare = (str_replace("\r\n","",showCompareCurrency('',$cart['total_price'],'return_array')));
			$compare_cnt	= count($total_price_compare);
			$compare_list	= array();
			foreach($total_price_compare as $k=>$compare_data){
				if($k == 0){
					if($compare_cnt > 1)	$open_yn = ' .currency_open';
					$scripts[] = "$('.settle_price_compare .currency_compare_lay".$open_yn."',parent.document).html('".$compare_data."');";
				}else{
					$compare_list[] = "<li>".$compare_data."</li>";
				}
			}
			$scripts[] = "$('.settle_price_compare .currency_compare_lay .currency_list',parent.document).html('".implode("",$compare_list)."');";
		}

		$scripts[] = '$(".totalprice",parent.document).html("'.get_currency_price($cart['total_price']).'");';
		if( $adminOrder == 'admin' ) {//관리자주문
			$scripts[] = '$(".total_price_temp",parent.document).val("'.get_currency_price($cart['total_price']).'");';
		}
		$scripts[] = '$("#total_reserve,.total_reserve",parent.document).html("'.get_currency_price($cart['total_reserve']).'");';
		$scripts[] = '$("#total_point,.total_point",parent.document).html("'.get_currency_price($cart['total_point']).'");';
		$scripts[] = '$("#total_goods_price, span.total_goods_price",parent.document).html("'.get_currency_price($cart['total']).'");';
		$scripts[] = '$("#total_coupon_sale_tr",parent.document).'.($cart['total_coupon_sale']?'show()':'hide()').';';
		$scripts[] = '$("#total_coupon_sale, .total_coupon_sale",parent.document).html("'.get_currency_price($cart['total_coupon_sale']).'");';

		// 마이너스 처리 추가 :: 2017-06-01 lwh
		if($cart['total_coupon_sale'] > 0){
			$scripts[] = '$("#total_coupon_sale",parent.document).closest("div").find(".minus").show();';
		}else{
			$scripts[] = '$("#total_coupon_sale",parent.document).closest("div").find(".minus").hide();';
		}
		if($cart['total_code_sale'] > 0){
			$scripts[] = '$("#total_code_sale",parent.document).closest("div").find(".minus").show();';
		}else{
			$scripts[] = '$("#total_code_sale",parent.document).closest("div").find(".minus").hide();';
		}

		$session_arr = ( $this->session->userdata('user') )?$this->session->userdata('user'):$_SESSION['user'];
		if( count($this->systemfblike['result'])) {//할인혜택이 있으면 '좋아요 혜택' 문구노출
			if( ($cfg['order']['fblike_ordertype'] == 1 && $session_arr['member_seq'] ) || ($cfg['order']['fblike_ordertype'] != 1) ) {//비회원혜택여부
				$scripts[] = '$(".fblikelay",parent.document).show();';
			}
		}
		$scripts[] = '$("#total_fblike_sale_tr",parent.document).'.($cart['total_fblike_sale']?'show()':'hide()').';';

		$scripts[] = '$("#total_fblike_sale",parent.document).html("'.get_currency_price($cart['total_fblike_sale']).'");';
		$scripts[] = '$("#total_mobile_sale_tr",parent.document).'.($cart['total_mobile_sale']?'show()':'hide()').';';
		$scripts[] = '$("#total_mobile_sale",parent.document).html("'.get_currency_price($cart['total_mobile_sale']).'");';
		$scripts[] = '$("#total_member_sale_tr",parent.document).'.($cart['total_member_sale']?'show()':'hide()').';';
		$scripts[] = '$("#total_member_sale",parent.document).html("'.get_currency_price($cart['total_member_sale']).'");';
		$scripts[] = '$("#total_referer_sale_tr",parent.document).'.($cart['total_referer_sale']?'show()':'hide()').';';
		$scripts[] = '$("#total_referer_sale",parent.document).html("'.get_currency_price($cart['total_referer_sale']).'");';
		$scripts[] = '$("#total_shipping_price, .total_shipping_price",parent.document).html("'.get_currency_price($total_shipping_price).'");';

		$scripts[] = '$("#use_emoney, .use_emoney",parent.document).html("'.get_currency_price($cart['emoney']).'");';
		if($cart['emoney']>0){
			$scripts[] = '$("#use_emoney, .use_emoney",parent.document).closest("div").find(".minus").show();';
		}else{
			$scripts[] = '$("#use_emoney, .use_emoney",parent.document).closest("div").find(".minus").hide();';
		}
		$scripts[] = '$("#use_cash, .use_cash",parent.document).html("'.get_currency_price($cart['cash']).'");';
		if($cart['emoney']>0){
			$scripts[] = '$("#use_cash, .use_cash",parent.document).closest("div").find(".minus").show();';
		}else{
			$scripts[] = '$("#use_cash, .use_cash",parent.document).closest("div").find(".minus").hide();';
		}

		$scripts[] = '$("#tmp_emoney_set",parent.document).html("'.get_currency_price($total_emoney - $_POST['emoney']).'");';
		$scripts[] = '$("#tmp_cash_set",parent.document).html("'.get_currency_price($total_cash - $_POST['cash']).'");';


		################# 2014-10-31 변경된 장바구니 모양으로 인해 추가
		$cart['total_sale_list']['shippingcoupon']['title']	= '배송비쿠폰';
		$cart['total_sale_list']['shippingcoupon']['price']	= 0;
		$cart['total_sale_list']['shippingcode']['title']	= '배송비코드';
		$cart['total_sale_list']['shippingcode']['price']	= 0;

		$shipping_coupon_sale_price			= array_sum($this->shipping_coupon_sale);
		if	($shipping_coupon_sale_price){
			$total_sales_price	+= $shipping_coupon_sale_price;
			$cart['total_sale_list']['shippingcoupon']['title']	= '배송비쿠폰';
			$cart['total_sale_list']['shippingcoupon']['price']	= $shipping_coupon_sale_price;
			$cart['total_sale_list']['coupon']['price']			+= $shipping_coupon_sale_price;
		}
		$shipping_promotion_code_sale_price	= array_sum($this->shipping_promotion_code_sale);
		if	($shipping_promotion_code_sale_price > 0){
			$total_sales_price	+= $shipping_promotion_code_sale_price;
			$cart['total_sale_list']['shippingcode']['title']	= '배송비코드';
			$cart['total_sale_list']['shippingcode']['price']	= $shipping_promotion_code_sale_price;
		}
		// 에누리
		if	($enuri > 0)	$total_sales_price	+= get_cutting_price($enuri);

		// 총할인 정보
		if	($total_sales_price > 0){
			$scripts[] = '$(".total_sale_price_btn",parent.document).show();';
			$scripts[] = '$(".total_sales_price",parent.document).html("'.get_currency_price($total_sales_price).'");';
			$scripts[] = '$(".total_sales_price",parent.document).closest("div").find(".minus").show();';

			if	($cart['total_sale_list'])foreach($cart['total_sale_list'] as $sale_type => $saleArr){
				if	($saleArr['price'] > 0){
					$scripts[] = '$("#total_'.$sale_type.'_sale",parent.document).html("'.get_currency_price($saleArr['price']).'");';
					$scripts[] = '$("#total_'.$sale_type.'_sale_tr, " ,parent.document).show();';
				}else{
					$scripts[] = '$("#total_'.$sale_type.'_sale",parent.document).html("0");';
					$scripts[] = '$("#total_'.$sale_type.'_sale_tr, " ,parent.document).hide();';
				}
			}

			// 배송비 쿠폰
			if(isset($shipping_coupon_sale_price)){//배송비쿠폰선택시
				$scripts[]	= '$("span#shipping_coupon_sale",parent.document).html("<span style=\"padding-left:20px;\">배송비쿠폰할인 : (-)'.get_currency_price($shipping_coupon_sale_price,3).'</span>");';
				$scripts[]	= '$("#shipping_coupon_sale_tr",parent.document).show();';
			}else{
				$scripts[]	= '$("span#shipping_coupon_sale",parent.document).html("");';
				$scripts[]	= '$("#shipping_coupon_sale_tr",parent.document).hide();';
			}

			// 배송비 코드
			if($shipping_promotion_code_sale_price > 0){
				$cart['total_promotion_code_sale'] += $shipping_promotion_code_sale_price;
				$scripts[]	= '$("span#shipping_code_sale",parent.document).html("<span style=\"padding-left:20px;\">배송비코드할인 : (-)'.get_currency_price($shipping_promotion_code_sale_price).'</span>");';
				$scripts[]	= '$("#shipping_code_sale_tr",parent.document).show();';
			}else{
				$scripts[]	= '$("span#shipping_code_sale",parent.document).html("");';
				$scripts[]	= '$("#shipping_code_sale_tr",parent.document).hide();';
			}

			// 에누리 할인 추가
			if	($enuri > 0){
				$scripts[] = '$("#enuri_tr",parent.document).show();';
				$scripts[] = '$("#enuri",parent.document).html("'.get_currency_price($enuri).'");';
				$scripts[] = '$("input[name=\'enuri\']",parent.document).val("'.$enuri.'");';
			}else{
				$scripts[] = '$("#enuri_tr",parent.document).hide();';
				$scripts[] = '$("#enuri",parent.document).html("0");';
				$scripts[] = '$("input[name=\'enuri\']",parent.document).val("");';
			}
		}else{
			$scripts[] = '$(".total_sale_price_btn",parent.document).hide();';
			$scripts[] = '$(".total_sales_price",parent.document).html("0");';
			$scripts[] = '$(".total_sales_price",parent.document).closest("div").find(".minus").hide();';
			foreach($cart['total_sale_list'] as $sale_type => $saleArr){
				$scripts[] = '$("#total_'.$sale_type.'_sale",parent.document).html("0");';
				$scripts[] = '$("#total_'.$sale_type.'_sale_tr, " ,parent.document).hide();';
			}
		}

		################# 2014-10-31 변경된 장바구니 모양으로 인해 추가

		// 추가배송비 처리
		$tot_add_delivery = 0;
		$tot_basic_delivery = 0;
		foreach($shipping_group_policy as $shipping_group=>$row){
			if	($row['add_delivery_cost'] > 0){
				$scripts[] = '$("#add_delivery_'.$shipping_group.'",parent.document).html("+ '.get_currency_price($row['add_delivery_cost'],2).'");';
			}else{
				$scripts[] = '$("#add_delivery_'.$shipping_group.'",parent.document).html("");';
			}

			$tot_add_delivery += get_cutting_price($row['add_delivery_cost']);
		}

		// 모바일 스킨 기본배송비 추가배송비 영역 출력
		if( $total_shipping_price ){
			$tot_basic_delivery = get_cutting_price($total_shipping_price) - get_cutting_price($tot_add_delivery);
		}
		$scripts[] = '$("#basic_delivery",parent.document).html("'.get_currency_price($tot_basic_delivery).'");';
		$scripts[] = '$("#add_basic_delivery",parent.document).html("'.get_currency_price($tot_add_delivery).'");';

		//@프로모션코드
		$scripts[] = '$("#cartpromotioncode",parent.document).val("'.$this->session->userdata('cart_promotioncode_'.session_id()).'");';
		if($this->session->userdata('cart_promotioncode_'.session_id())){
			$scripts[] = '$(".cartpromotioncodeinputlay",parent.document).hide();';
			$scripts[] = '$(".cartpromotioncodedellay",parent.document).show();';
		}else{
			$scripts[] = '$(".cartpromotioncodeinputlay",parent.document).show();';
			$scripts[] = '$(".cartpromotioncodedellay",parent.document).hide();';
		}
		$scripts[] = '$("#total_promotion_goods_sale_tr",parent.document).'.($cart['total_promotion_code_sale']?'show()':'hide()').';';
		$scripts[] = '$("#total_promotion_goods_sale, .total_promotion_goods_sale",parent.document).html("'.get_currency_price($cart['total_promotion_code_sale']).'");';

		if(is_array($this->shipping_promotion_code_sale) && count($this->shipping_promotion_code_sale) > 0) {//배송비프로모션선택시
			foreach($this->shipping_promotion_code_sale as $provider_seq => $shipping_sale){
				$scripts[]	= '$("div#shippingcode_sale_'.$provider_seq.'", parent.document).html("<img src=\"/admin/skin/default/images/common/icon/icon_ord_code.gif\" /> '.get_currency_price($shipping_sale,2).'");';
			}
		}else{
			$scripts[] = '$(".shippingcode_sale",parent.document).html("");';
		}

		if(isset($this->shipping_coupon_sale)){//배송비쿠폰선택시
			foreach($this->shipping_coupon_sale as $provider_seq => $shipping_sale){
				$scripts[] = '$("div#shippingcoupon_sale_'.$provider_seq.'",parent.document).html("<span class=\"desc\">- '.get_currency_price($shipping_sale,2).' 쿠폰</span>");';
			}
		}else{
			$scripts[] = '$("div.shippingcoupon_sale",parent.document).html("");';
		}

		/* 결제금액이 0원일 경우 결제수단을 무통장만 활성화 */
		if($settle_price==0){
			$scripts[] = '$("input[name=\'payment\'][value=\'bank\']",parent.document).attr("checked","checked").click();';
			$scripts[] = '$("input[name=\'payment\'][value!=\'bank\']",parent.document).attr("disabled","disabled");';

			$scripts[] = '$("#payment_type",parent.document).hide();';
			$scripts[] = '$("#payment_type_zero",parent.document).show();';
			$scripts[] = '$(".bank",parent.document).hide();';
			$scripts[] = 'if($("input[name=\'depositor\']",parent.document).val() == ""){';
			$scripts[] = '$("input[name=\'depositor\']",parent.document).val("전액할인");}';
			$scripts[] = 'if(!$("select[name=\'bank\']",parent.document).find("option:selected").val()){';
			$scripts[] = '$("select[name=\'bank\']",parent.document).find("option").eq(1).attr("selected",true);}';

			$scripts[] = '$(".cach_voucherchk", parent.document).hide();';
			$scripts[] = '$("select[name=\'typereceipt\']",parent.document).find("[value=0]").attr("selected",true);';
			$scripts[] = '$("#cash_container", parent.document).hide();';
			$scripts[] = '$("select[name=\'typereceipt\']",parent.document).find("[value=2]").attr("disabled","disabled");';

			# @2015-12-07 pjm 결제금액은 0원이나 마일리지 또는 예치금 사용이면 계산서 발급선택 노출
			if($cart['emoney'] > 0 || $cart['cash']){
				$scripts[] = '$("#typereceiptlay", parent.document).show();';
				//$scripts[] = '$(".typereceiptlay", parent.document).hide();';
				if($cart['emoney'] > 0 && $cfg['order']['sale_reserve_yn'] == 'Y') {
					$scripts[] = '$(".tax_voucherchk", parent.document).show();';
					$scripts[] = '$("select[name=\'typereceipt\']",parent.document).find("[value=1]").removeAttr("disabled");';
				} else if($cart['cash'] > 0 && $cfg['order']['sale_emoney_yn'] == 'Y')  {
					$scripts[] = '$(".tax_voucherchk", parent.document).show();';
					$scripts[] = '$("select[name=\'typereceipt\']",parent.document).find("[value=1]").removeAttr("disabled");';
				} else {
					$scripts[] = '$(".tax_voucherchk", parent.document).hide();';
					$scripts[] = '$("select[name=\'typereceipt\']",parent.document).find("[value=1]").attr("disabled","disabled");';
					$scripts[] = '$("#tax_container", parent.document).hide();';
					$scripts[] = '$(".typereceiptlay", parent.document).hide();';
					$scripts[] = '$("#typereceiptlay", parent.document).hide();';
				}
			}else{
				$scripts[] = '$(".tax_voucherchk", parent.document).hide();';
				$scripts[] = '$(".typereceiptlay", parent.document).hide();';
				$scripts[] = '$("#typereceiptlay", parent.document).hide();';
			}

			$scripts[] = '$("#escrow",parent.document).hide();';
		}else{
			$scripts[] = '$("select[name=\'typereceipt\']",parent.document).find("[value=2]").removeAttr("disabled");';
			$scripts[] = '$("select[name=\'typereceipt\']",parent.document).find("[value=1]").removeAttr("disabled");';
			$scripts[] = '$("input[name=\'payment\'][value!=\'bank\']",parent.document).removeAttr("disabled");';
			$scripts[] = '$("#payment_type",parent.document).show();';
			$scripts[] = '$("#payment_type_zero",parent.document).hide();';
			$scripts[] = 'if($("input[name=\'payment\']:checked",parent.document).val()=="bank"){';
			$scripts[] = '$(".bank",parent.document).show();}';
			$scripts[] = 'if($("input[name=\'depositor\']",parent.document).val() == "전액할인"){';
			$scripts[] = '$("input[name=\'depositor\']",parent.document).val("");}';
			if		($this->_is_mobile_agent){//$this->mobileMode  ||
				if	(is_array($pg['mobileEscrow']) && count($pg['mobileEscrow']) > 0){
					if		(in_array('account', $pg['mobileEscrow']) && $pg['mobileEscrowAccountLimit'] <= $settle_price) {
						$scripts[] = '$("#escrow",parent.document).show();';
					}elseif	(in_array('virtual', $pg['mobileEscrow']) && $pg['mobileEscrowVirtualLimit'] <= $settle_price) {
						$scripts[] = '$("#escrow",parent.document).show();';
					}else{
						$scripts[] = '$("#escrow",parent.document).hide();';
					}
				}else{
					$scripts[] = '$("#escrow",parent.document).hide();';
				}
			}else{
				if	(is_array($pg['escrow']) && count($pg['escrow']) > 0){
					if		(in_array('account', $pg['escrow']) && $pg['escrowAccountLimit'] <= $settle_price) {
						$scripts[] = '$("#escrow",parent.document).show();';
					}elseif	(in_array('virtual', $pg['escrow']) && $pg['escrowVirtualLimit'] <= $settle_price) {
						$scripts[] = '$("#escrow",parent.document).show();';
					}else{
						$scripts[] = '$("#escrow",parent.document).hide();';
					}
				}else{
					$scripts[] = '$("#escrow",parent.document).hide();';
				}
			}
			$scripts[] = 'if($("input[name=\'payment\']:checked",parent.document).val()=="bank"){';
			$scripts[] = '$("#typereceiptlay", parent.document).show();}';
			$scripts[] = '$(".tax_voucherchk", parent.document).show();';
			$scripts[] = '$(".cash_voucherchk", parent.document).show();';
		}

		// 배송방법 선택 없앰
		$scripts[] = '$(".shipping_tr", parent.document).hide();';
		// 다른국가선택 레이어 숨기기
		if($_POST['address_nation']){
			$scripts[] = '$(".detailDescriptionLayer", parent.document).hide();';
		}

		$scripts[] = "});";
		$scripts[] = "</script>";

		if($this->displaymode == 'coupon'){
			$checkcoupons			= 0;
			$checkshippingcoupons	= 0;
			$html					= '';
			$shippinghtml			= '';
			$r_template_file_path		= explode('/',$this->template_path());
			$r_template_file_path[2]	= "_coupon.html";
			$template_file_path			= implode('/',$r_template_file_path);

			$this->template->assign(array(
				'shipping_group_policy'	=> $cart['shipping_group_policy'],
				'shipping_group_price'	=> $cart['shipping_group_price']
			));
			// 상품 관련 쿠폰 :: 2017-05-17 lwh
			if	( $members && $person_seq == "") {
				foreach($provider_cart as $provider_seq => $data){
					$_cart_list = array();
					foreach($data['cart_list'] as $cart_key => $cart_data) {
						//이벤트적용 상품일 때 쿠폰사용제한 체크
						if(!$cart_data['event'] || $cart_data['event']['use_coupon'] == "y"){
							$_cart_list[] = $cart_data;
							if	($cart_data['coupons'])	$checkcoupons	+= count($cart_data['coupons']);
						}
					}
					$data['cart_list'] = $_cart_list;
					$tmp_provider_cart[]	= $data;
				}
			}
			$this->template->assign(array(
				'provider_cart'			=> $tmp_provider_cart,
				'checkcoupons'			=> $checkcoupons
			));
			$this->template->define('*', $template_file_path."?cartlist");
			$html	= $this->template->fetch('*');

			// 배송쿠폰 :: 2017-05-17 lwh
			unset($tmp_provider_cart_shipping);
			if	( $members && $person_seq == "") {
				foreach($provider_cart as $shipKey => $data){
					$provider_seq = $data['shipping_provider_seq'];

					unset($cart_list);
					foreach($data['cart_list'] as $cart_key => $cart_data) {
						if	($cart_data['goods_kind'] == 'goods') $cart_list[] = $cart_data;
					}
					$data['cart_list']		= $cart_list;
					$total_shipping_price	+= $data['grp_shipping_price'];
					if	($data['grp_shipping_price'] > 0) {
						$shipping_group = $data['cart_list'][0]['shipping_group'];
						$shippingcoupons	= $this->couponmodel->get_shippingcoupon_provider($provider_seq, $this->userInfo['member_seq'], $data['provider_price'], $data['grp_shipping_price']);

						$data['cart_list'][0]['shipping_coupon']	= $shippingcoupons;
						$data['cart_list'][0]['shipping_cost']		= $data['grp_shipping_price'];
						if	($shippingcoupons)	$checkshippingcoupons	+= count($shippingcoupons);

						if	($this->shipping_coupon_down_seq[$shipping_group])
							$data['cart_list'][0]['shipping_coupon_down_seq']	= $this->shipping_coupon_down_seq[$shipping_group];
						if	($this->shipping_coupon_sale[$shipping_group])
							$data['cart_list'][0]['shipping_coupon_sale']		= $this->shipping_coupon_sale[$shipping_group];
						$tmp_provider_cart_shipping[]	= $data;
					}
				}
			}

			$this->template->assign(array(
				'provider_cart'				=> $tmp_provider_cart_shipping,
				'checkshippingcoupons'		=> $checkshippingcoupons,
				'total_shipping_price'		=> $total_shipping_price
			));

			$this->template->define('*', $template_file_path."?shippincoupon");
			$shippinghtml = $this->template->fetch('*');

			//이벤트 주문서 쿠폰 사용제한
			$able_ordersheetcoupons = true;
			if	( $members && $person_seq == "") {
				foreach($provider_cart as $provider_seq => $data){
					foreach($data['cart_list'] as $cart_key => $cart_data) {
						//이벤트적용 상품일 때 쿠폰사용제한 체크
						if($cart_data['event']['use_coupon_ordersheet'] == "n"){
							$able_ordersheetcoupons = false;
						}
					}
				}
			}

			// 주문서쿠폰 :: 2018-09-10 hed
			unset($checkordersheetcoupons);
			if	( $members && $person_seq == "" && $able_ordersheetcoupons) {
				$ordersheetcoupons = $this->couponmodel->get_able_use_ordersheet_coupon_list($this->userInfo['member_seq'], $sum_option_total_price);
				$checkordersheetcoupons	= count($ordersheetcoupons);
			}

			$this->template->assign(array(
				'ordersheetcoupons'				=> $ordersheetcoupons,
				'checkordersheetcoupons'		=> $checkordersheetcoupons,
				'ordersheet_coupon_download_seq'	=> $ordersheet_coupon_download_seq
			));

			// 스킨 패치에 따라 영향을 받을 수 있어 별도 파일로 처리
			$this->load->library("o2o/o2oservicelibrary");
			if($this->o2oservicelibrary->isCheckedActvieO2OEnv === true) {
			    $r_template_file_path[2]		= "_coupon_ordersheet.html";
			    $ordersheet_template_file_path	= implode('/',$r_template_file_path);
			    $this->template->define('coupon_ordersheet', $ordersheet_template_file_path);
			    $coupon_ordersheet_html = $this->template->fetch('coupon_ordersheet');
			}

			if	( $checkcoupons < 1 && $checkshippingcoupons < 1 && $checkordersheetcoupons < 1) {
				$return		= array('coupon_error'				=> true,
									'checkcoupons'				=> $checkcoupons,
									'checkshippingcoupons'		=> $checkshippingcoupons,
									'checkordersheetcoupons'	=> $checkordersheetcoupons,
									'coupongoods'				=> '',
									'couponshipping'			=> '',
									'couponordersheet'			=> '');
			}else{
				$return		= array('coupon_error'				=> false,
									'checkcoupons'				=> $checkcoupons,
									'checkshippingcoupons'		=> $checkshippingcoupons,
									'checkordersheetcoupons'	=> $checkordersheetcoupons,
									'coupongoods'				=> $html,
									'couponshipping'			=> $shippinghtml,
									'couponordersheet'			=> $coupon_ordersheet_html);
			}
			echo json_encode($return);
		}else if($this->displaymode == 'cart') {
			return $cart;
		}else if(!$this->displaymode){
			foreach($scripts as $script){
				echo $script."\n";
			}
		}
	}

	public function pay(){
		$this->load->model('ssl');
		$this->ssl->decode();
		$this->load->model('goodsmodel');
		$this->load->model('ordermodel');
		$this->load->model('shippingmodel');
		$this->load->library('validation');
		$this->load->model('statsmodel');
		/**
		* 정산개선 시작
		* step1->step2->step3 순차로 진행되어야 합니다.
		* @
		**/
		$this->load->helper('accountall');
		if(!$this->accountallmodel)$this->load->model('accountallmodel');

		$adminOrder=$_POST['adminOrder'];
		$member_seq=$_POST['member_seq'];
		$person_seq=$_POST['person_seq'];

		if($adminOrder == 'admin'){
			$this->userInfo = array();
			$this->userInfo['member_seq'] = $member_seq;
		}

		$this->calculate($adminOrder, $person_seq);

		if	(!$this->scm_cfg)	$this->scm_cfg	= config_load('scm');
		if	($this->scm_cfg['use'] == 'Y') $this->load->model('scmmodel');

		## 모바일결제 : 체크값 오류시 callback으로 결제창 layer 숨김 처리
		if($_POST['mobilenew'] == "y"){
			$pg_cancel_script = $this->pg_cancel_script();
		}else{
			$pg_cancel_script = "";
		}
		// 엑심베이 pc 에이전트는 opener 제어하도록 수정
		$popupKind = "parent";
		if(!$this->_is_mobile_agent && $this->input->post('payment') == 'eximbay' ) {
			$popupKind = "opener";
		}

		// 개인정보 동의 체크
		if( defined('__ISUSER__') != true ){
			if($_POST['order_version'] >= 2){
				if($_POST['agree1'] != 'Y'){
					//서비스 이용 약관에 동의하셔야 합니다.
					openDialogAlert(getAlert('os236'),400,140,$popupKind,$pg_cancel_script);
					if($popupKind == 'opener') {
						echo "<script>window.close();</script>";
					}
					exit;
				}
				if($_POST['agree2'] != 'Y'){
					//개인정보 수집 및 이용에 동의하셔야 합니다.
					openDialogAlert(getAlert('os146'),400,140,$popupKind,$pg_cancel_script);
					if($popupKind == 'opener') {
						echo "<script>window.close();</script>";
					}
					exit;
				}
			}else{
				if($_POST['agree'] != 'Y'){
					//서비스 이용 약관에 동의하셔야 합니다.
					openDialogAlert(getAlert('os236'),400,140,$popupKind,$pg_cancel_script);
					if($popupKind == 'opener') {
						echo "<script>window.close();</script>";
					}
					exit;
				}
			}
		}

		/**
		 * 쿠폰 중복 사용 체크
		 * 상품쿠폰/배송비쿠폰
		 */
		$couponDuplChk = array();
		foreach(array('download', 'shipping') as $postKey) {
		    ${'coupon_' . $postKey} = $this->input->post("coupon_" . $postKey);
		    if(!empty(${'coupon_' . $postKey})) {
		        $couponDuplChk[$postKey] = ${'coupon_' . $postKey};
		    }
		}
		if(count($couponDuplChk)>0) {
		    $this->load->library("coupon_dupl");
		    foreach($couponDuplChk as $couponDuplType => $couponDuplData) {
		        if(!empty($couponDuplData)) {
		            $this->coupon_dupl->__construct($couponDuplData, $couponDuplType);
		            $duplSeqList = $this->coupon_dupl->getDuplSeqByForm();
		            $duplData = $this->coupon_dupl->getDuplData($this->db, $duplSeqList);

		            // 중복 데이터가 존재하는 경우
		            if(!empty($duplData)) {
		                // [쿠폰번호] 쿠폰이름 은 중복할인이 불가합니다.
		                $err_msg = getAlert('os252',array(key($duplData), reset($duplData)));
	                    openDialogAlert($err_msg,400,140, $popupKind, $pg_cancel_script);
	                    if($popupKind == 'opener') {
	                        echo "<script>window.close();</script>";
	                    }
	                    exit;
		            }
		        }
		    }
		}

		// 배송 불가 항목 체크 :: 2016-08-04 lwh , 비회원 배송불가 확인 조건문 수정 20170605 ldb
		if($_POST['order_version'] >= 2 && ($_POST['ship_possible'] != 'Y' || $this->ship_possible == 'N')){
			//배송이 불가능한 상품이 존재합니다.
			openDialogAlert(getAlert('os148'),400,140,$popupKind,$pg_cancel_script);
			if($popupKind == 'opener') {
				echo "<script>window.close();</script>";
			}
			exit;
		}

		$package_error = false;
		foreach($this->cart['list'] as $data){
			$provider_price[$data['goods_shipping_group_seq']] += $data['price'] * $data['ea'];
			if($data['cart_suboptions']){
				foreach($data['cart_suboptions'] as $subdata){
					$provider_price[$data['goods_shipping_group_seq']] += $subdata['price'] * $subdata['ea'];
				}
			}
			if($data['package_error']){
				//연결된 상품에 오류가 있습니다.
				openDialogAlert(getAlert('os134'),400,140,$popupKind,$pg_cancel_script);
				if($popupKind == 'opener') {
					echo "<script>window.close();</script>";
				}
				exit;
			}
		}

		### GIFT
		if($_POST['gift_use'] == "Y"){
			$gift_categorys = array();
			$gift_goods		= array();
			$gift_provider	= array();		//상품별 입점사
			foreach($this->cart['data_goods'] as $goods_seq => $data){
				$gift_goods[] 			= $goods_seq;
				$gift_provider[]		= $data['provider_seq'];		//상품별 입점사
				$ship_arr				= explode('_',$data['shipping_group']);
				$gift_shipping[]		= $ship_arr[0];
				$gift['goods_seq']		= $goods_seq;
				$gift['provider_seq']	= $data['provider_seq'];
				$gift['ea']				= $data['ea'];
				$gift['tot_price']		= $data['price'];
				$gift_loop[]			= $gift;
				foreach($data['r_category'] as $category_code){
					$gift_categorys[]	= $category_code;
					$category[]			= $category_code;
				}
			}

			$gift_categorys = array_unique($gift_categorys);
			$gift = $this->ordermodel->get_gift_event($gift_categorys,$gift_goods,$gift_shipping, $provider_price,
			$gift_loop,$this->cart['total']);
			$gifts_provider = array();
			foreach($gift['gloop'] as $v){

				$gifts_provider[$v['gift_seq']] = $v['provider_seq'];
				if(count($_POST['gift_'.$v['gift_seq']]) > $_POST['gift_'.$v['gift_seq'].'_limit']){
					/*$callback = "";
					$callback = $pg_cancel_script;*/
					$callback = ($_POST['mobilenew'] == "y") ? $pg_cancel_script : "";
					//$v['title']."이벤트의 사은품은 최대 ".$_POST['gift_'.$v['gift_seq'].'_limit']."개까지 선택하실 수 있습니다."
					openDialogAlert(getAlert('os045',array($v['title'],$_POST['gift_'.$v['gift_seq'].'_limit'])),400,140,$popupKind,$callback);
					if($popupKind == 'opener') {
					echo "<script>window.close();</script>";
				}
					exit;
				}
			}
		}

		/* 결제금액이 0원인 경우 무통장으로 처리 */
		if( $this->settle_price==0 ){
			$_POST['payment'] = "bank";
		}

		//쿠폰>사용제한>무통장만가능
		if( $this->cart['coupon_sale_payment_b'] > 0 && $_POST['payment'] != 'bank'){
			//현재 무통장 전용 쿠폰을 사용하셨습니다.<br />결제수단을 무통장으로 변경해 주세요!
			openDialogAlert(getAlert('os046'),400,140,$popupKind,$pg_cancel_script);
			if($popupKind == 'opener') {
				echo "<script>window.close();</script>";
			}
			exit;
		}

		//쿠폰>사용제한>모바일 화면에서만 가능
		if( $this->cart['coupon_sale_agent_m'] > 0 && !$this->_is_mobile_agent ){
			//$callback = "";
			//openDialogAlert("현재 모바일/태블릿 전용 쿠폰을 사용하셨습니다.<br />모바일/태블릿기기에서 이용해 주세요!",400,140,'parent');
			//exit;
		}

		// 카드, 휴대폰일경우 매출증빙 관련 초기화 // 카카오페이 추가 :: 2015-02-17 lwh
		if(in_array($_POST['payment'],array('card','cellphone','kakaopay','payco','paypal'))) $_POST['typereceipt'] = 0;


		// ------- validation check START :: 2017-05-19 lwh -------------------
		$is_coupon					= false; // 티켓상품
		$is_goods					= false; // 실물상품
		$is_direct_store			= false; // 매장수령상품
		$is_international_shipping	= false; // 해외배송상품
		foreach($this->cart['list']  as $key => $data){
			if( $data['goods_kind'] == 'coupon' )				$is_coupon			= true;
			if( $data['shipping_set_code'] == 'direct_store' )	$is_direct_store	= true;
			else if( $data['goods_kind'] == 'goods' )			$is_goods			= true;
			if( $data['option_international_shipping_status'] == 'y' )
														$is_international_shipping	= true;
		}
		// 사은품이 있는경우 상품에 속하게 변경 :: 2015-04-03 lwh
		if(is_array($_POST['gifts'])){
			// 매장수령만 있는 경우 사은품은 매장에서 수령하도록 :: 2017-07-07 lwh
			if($is_goods || !$is_direct_store){
				$is_goods = true;
			}
		}

		// ------- 주문자 정보 :: START ------- //

		// 주문자 이름
		$this->validation->set_rules('order_user_name', getAlert('os047'),'trim|required|max_length[20]|xss_clean');
		// 주문자 휴대폰
		$this->validation->set_rules('order_cellphone[]', getAlert('os049'),'trim|required|max_length[4]|xss_clean');
		// 주문자 유선전화
		if($_POST['order_phone'][0]||$_POST['order_phone'][1]||$_POST['order_phone'][2]){
			$this->validation->set_rules('order_phone[]', getAlert('os048'),'trim|numeric|max_length[4]|xss_clean');
		}
		// 이메일
		$this->validation->set_rules('order_email', getAlert('os050'),'trim|required|valid_email|max_length[100]|xss_clean');

		/* 영문, 중문 스킨 문제로 주석처리, 이후 영문, 중문 연락처 필드 개선시 추가 예정 :: 2018-04-20 lkh
		//휴대폰 번호 자리수 체크 2018-04-05 jhs
		$i = 0;
		foreach($_POST['order_cellphone'] as $cpData){

			if($i == 0 && (strlen($cpData) < 3 || strlen($cpData) > 4)){
				openDialogAlert(getAlert('os049'),400,140,$popupKind,$pg_cancel_script);
				exit;
			}else if($i == 1 && (strlen($cpData) < 3 || strlen($cpData) > 4)){
				openDialogAlert(getAlert('os049'),400,140,$popupKind,$pg_cancel_script);
				exit;
			}else if($i == 2 && strlen($cpData) != 4){
				openDialogAlert(getAlert('os049'),400,140,$popupKind,$pg_cancel_script);
				exit;
			}

			$i++;
		}

		$j = 0;
		foreach($_POST['recipient_cellphone'] as $cpData){

			if($j == 0 && (strlen($cpData) < 3 || strlen($cpData) > 4)){
				openDialogAlert(getAlert('os049'),400,140,$popupKind,$pg_cancel_script);
				exit;
			}else if($j == 1 && (strlen($cpData) < 3 || strlen($cpData) > 4)){
				openDialogAlert(getAlert('os049'),400,140,$popupKind,$pg_cancel_script);
				exit;
			}else if($j == 2 && strlen($cpData) != 4){
				openDialogAlert(getAlert('os049'),400,140,$popupKind,$pg_cancel_script);
				exit;
			}

			$j++;
		}
		*/
		// ------- 주문자 정보 :: END ------- //

		// ------- 배송지 정보 :: START ------- //

		// < 실물상품일때 >
		if($is_goods){
			// 신 배송비 국내 해외 구분 :: 2016-08-05 lwh - 구버전과 동일하게 비교하기 위해..
			if	($_POST['address_nation']){
				if($_POST['address_nation'] == 'KOREA')	$_POST['international'] = 0;
				else									$_POST['international'] = 1;
			}else{
				openDialogAlert(getAlert('os082'),400,140,$popupKind,$pg_cancel_script);
				if($popupKind == 'opener') {
					echo "<script>window.close();</script>";
				}
				exit;
			}
			$recipient_required = 'required';
			if($this->session->userdata('order_label') === 'present') {
				$recipient_required = '';
			}
			// 국내 배송일 경우
			if($_POST['international'] == 0){
				//배송방법
				//$this->validation->set_rules('shipping_method', getAlert('os051'),'trim|required|xss_clean');
				if(!isset($_POST['recipient_new_zipcode']) && $_POST['recipient_zipcode']){
					$_POST['recipient_new_zipcode']	= implode('',$_POST['recipient_zipcode']);
					unset($_POST['recipient_zipcode']);
				}
				if($_POST['recipient_new_zipcode']){
					//우편번호
					$this->validation->set_rules('recipient_new_zipcode', getAlert('os052'),'trim|'.$recipient_required.'|max_length[7]|xss_clean');
				}else{
					//우편번호
					$this->validation->set_rules('recipient_zipcode[]', getAlert('os052'),'trim|'.$recipient_required.'|max_length[7]|xss_clean');
				}
				//주소
				$this->validation->set_rules('recipient_address', getAlert('os053'),'trim|max_length[255]|'.$recipient_required.'|xss_clean');
				//나머지주소
				$this->validation->set_rules('recipient_address_detail', getAlert('os054'),'trim|max_length[255]|xss_clean');

			}else if($_POST['international'] == 1){
				//주소
				$this->validation->set_rules('international_address', getAlert('os053'),'trim|max_length[255]|required|xss_clean');
				//시도
				$this->validation->set_rules('international_town_city', getAlert('os059'),'trim|max_length[45]|required|xss_clean');
				//주
				$this->validation->set_rules('international_county', getAlert('os060'),'trim|max_length[20]|required|xss_clean');
				//우편번호
				$this->validation->set_rules('international_postcode', getAlert('os052'),'trim|max_length[20]|required|xss_clean');

				$_POST['international_recipient_cellphone']	&& $_POST['recipient_cellphone'] = $_POST['international_recipient_cellphone'];
				$_POST['international_recipient_phone']		&& $_POST['recipient_phone'] = $_POST['international_recipient_phone'];
			}
		}

		// < 매장수령일때 >
		//if( $is_direct_store ){	} // 별도 안내 없음.

		// < 티켓상품일때 >
		if( $is_coupon ){
			$_POST["recipient_email"] = ($_POST["recipient_email"]) ? $_POST["recipient_email"] : '';
			//받는분 이메일
			$this->validation->set_rules('recipient_email', getAlert('os050'),'trim|required|valid_email|max_length[100]|xss_clean');
		}

		// < 공통정보 >
		// 받는분
		$this->validation->set_rules('recipient_user_name', getAlert('os055'),'trim|required|max_length[20]|xss_clean');
		//받는분 휴대폰
		$this->validation->set_rules('recipient_cellphone[]', getAlert('os057'),'trim|numeric|max_length[4]|required|xss_clean');
		//받는분 추가연락처
		$this->validation->set_rules('recipient_phone[]', getAlert('os056'),'trim|max_length[4]|xss_clean');

		// ------- 배송지 정보 :: END ------- //

		//결제방법
		$this->validation->set_rules('payment', getAlert('os062'),'trim|required|xss_clean');
		if($_POST['payment'] == 'bank' && $this->settle_price>0 ){
			//입금자명
			$this->validation->set_rules('depositor', getAlert('os063'),'trim|required|xss_clean');
			//입금은행
			$this->validation->set_rules('bank', getAlert('os064'),'trim|required|xss_clean');
		}
		//마일리지
		$this->validation->set_rules('emoney', getAlert('os065'),'trim|numeric|xss_clean');
		//예치금
		$this->validation->set_rules('cash', getAlert('os066'),'trim|numeric|xss_clean');

		// 해외배송상품
		if( $is_international_shipping ){
			// 개인통관고유부호 체크
			$this->validation->set_rules('clearance_unique_personal_code', getAlert('os067'),'trim|required|alpha_numeric|min_length[13]|xss_clean');
			if( $_POST['clearance_unique_personal_code'] && !preg_match('/^p([0-9]{12,12})/i', $_POST['clearance_unique_personal_code'] ) ){
				//개인통관부호가 올바르지 않습니다.
				openDialogAlert(getAlert('os068'),400,150,$popupKind,$pg_cancel_script);
				if($popupKind == 'opener') {
					echo "<script>window.close();</script>";
				}
				exit;
			}

			if( $_POST['agree_international_shipping1'] != 'y' ){
				//개인통관부호 수집에 동의를 해주십시요.
				openDialogAlert(getAlert('os069'),400,150,$popupKind,$pg_cancel_script);
				if($popupKind == 'opener') {
					echo "<script>window.close();</script>";
				}
				exit;
			}

			if( $_POST['agree_international_shipping2'] != 'y' && serviceLimit('H_AD') === true ){
				//개인통관부호 판매자 제공에 동의를 해주십시요.
				openDialogAlert(getAlert('os070'),400,150,$popupKind,$pg_cancel_script);
				if($popupKind == 'opener') {
					echo "<script>window.close();</script>";
				}
				exit;
			}
		}

		if($_POST["email"] == ""){
			$_POST["email"] = $_POST["order_email"];
		}

		if($_POST["person"] == ""){
			$_POST["person"] = $_POST["order_user_name"];
		}

		if($_POST["phone"] == ""){
			$_POST["phone"] = join("", $_POST["order_cellphone"]);
		}

		if ($_POST['typereceiptuse'] == '0' && $_POST["typereceipt"]) {
			$_POST["typereceipt"] = "";
		}

		if($_POST["typereceipt"] == "2"){
			if($_POST["cuse"] == "0"){
				//인증번호
				$this->validation->set_rules('creceipt_number[0]', getAlert('os072'),'trim|numeric|required|xss_clean');
			}else{
				//사업자번호
				$this->validation->set_rules('creceipt_number[1]', getAlert('os073'),'trim|numeric|required|xss_clean');
			}
			if(isset($_POST["sales_email"])){
				//매출증빙 이메일
				$this->validation->set_rules('sales_email', getAlert('os074'),'trim|required|valid_email|xss_clean');
			}
		}else if($_POST["typereceipt"] == "1"){
			$_POST["validation_biz_no"] = str_replace("-", "", $_POST["busi_no"]);
			//상호명
			$this->validation->set_rules('co_name', getAlert('os075'),'trim|required|xss_clean');
			//사업자번호
			$this->validation->set_rules('validation_biz_no', getAlert('os076'),'trim|required|numeric|xss_clean');
			//대표자명
			$this->validation->set_rules('co_ceo', getAlert('os077'),'trim|required|xss_clean');
			//업태
			$this->validation->set_rules('co_status', getAlert('os078'),'trim|required|xss_clean');
			//업종
			$this->validation->set_rules('co_type', getAlert('os079'),'trim|required|xss_clean');

			$_POST['co_new_zipcode']	= str_replace('-','',$_POST['co_new_zipcode']);

			if(isset($_POST['co_new_zipcode'])){
				//우편번호
				//$this->validation->set_rules('co_new_zipcode', getAlert('os052'),'trim|numeric|xss_clean');
			}else{
				//우편번호
				//$this->validation->set_rules('co_zipcode[]', getAlert('os052'),'trim|numeric|xss_clean');
			}
			//주소
			if($_POST['co_address_type'] == 'street') {
				$this->validation->set_rules('co_address_street', getAlert('os053'),'trim|xss_clean');
			} else {
				$this->validation->set_rules('co_address', getAlert('os053'),'trim|xss_clean');
			}
			$this->validation->set_rules('person', getAlert('or_managersName'),'trim|required|xss_clean');	// 담당자명
			$this->validation->set_rules('email', getAlert('os074'),'trim|required|valid_email|xss_clean');	// 담당자 이메일
			$this->validation->set_rules('phone', getAlert('os080'),'trim|required|numeric|xss_clean');		// 담당자 연락처

			$this->load->model('membermodel');
			$bcheck = $this->membermodel->bizno_check($_POST["validation_biz_no"]);
			if( $bcheck === false ) {
				//올바르지 않은 사업자등록번호 입니다.
				openDialogAlert(getAlert('mb064'),400,150,$popupKind,$pg_cancel_script);
				if($popupKind == 'opener') {
					echo "<script>window.close();</script>";
				}
				exit;
			}
		}

		if($this->validation->exec()===false){
			$err = $this->validation->error_array;
			$callback = "if(parent.document.getElementsByName('{$err['key']}')[0]) parent.document.getElementsByName('{$err['key']}')[0].focus();".$pg_cancel_script;
			openDialogAlert($err['value'],400,140,$popupKind,$callback);
			if($popupKind == 'opener') {
				echo "<script>window.close();</script>";
			}
			exit;
		}
		// ------- validation check END :: 2017-05-19 lwh -------------------

		//프로모션코드3 -> 구매시
		if($this->session->userdata('cart_promotioncode_'.session_id())) {
			$promotioncode = $this->promotionmodel->get_able_download_saleprice_pay($this->session->userdata('cart_promotioncodeseq_'.session_id()),$this->session->userdata('cart_promotioncode_'.session_id()));
		}

		// 스킨의 버전 구분 :: 2016-08-05 lwh
		if($_POST['order_version'] >= 1.1){
			// 기존과 동일하게 본사의 배송비를 체크한다.
			$this->load->model('shippingmodel');
			$this->shipping_order = $this->shippingmodel->get_shipping_base('1');
		}else if(!$this->shipping_order){
			/* 배송비 정책 체크 :: 사용하지 않음. 삭제 예정 :: 2016-08-05 lwh */

			//택배/배송비 정책이 설정되지 않았습니다.
			openDialogAlert(getAlert('os081'),400,100,$popupKind,$pg_cancel_script);
			if($popupKind == 'opener') {
				echo "<script>window.close();</script>";
			}
			exit;
		}

		if($adminOrder != "admin"){
			/* 결제중 버튼으로 변경*/
			echo '<script>
			$("div.pay_layer",parent.document).eq(0).hide();
			$("div.pay_layer",parent.document).eq(1).show();
			</script>';
		}

		if($person_seq != ""){
			$query = $this->db->query("select * from fm_person where person_seq='".$person_seq."'");
			$res = $query->row_array();
			$_POST['enuri'] = $res['enuri'];
			$_POST['admin_memo'] = $res['admin_memo'];
		}

		## 결제수단에 따른 PG모듈 선택 :: 2015-02-23 lwh
		if(in_array($_POST['payment'],array('kakaopay','paypal','payco','eximbay'))){
			$pgCompany	= $_POST['payment'];
		}else{
			$pgCompany	= $this->config_system['pgCompany'];
		}

		$checkPayment = $this->input->post('payment');
		// KICC 휴대폰 결제 복합 과세 미지원 처리
		if($this->cart['taxtype'] == 'mix' && $pgCompany == 'kicc' && $checkPayment == 'cellphone'){
			if($this->mobileMode!=true){
				// PC 전용 스킨 콜백 함수
				$callbackFun = '$("div.pay_layer",parent.document).eq(0).show();$("div.pay_layer",parent.document).eq(1).hide();';
			}else{
				// 반응형/모바일 전용 스킨 콜백 함수
				$callbackFun = 'window.parent.reverse_pay_layer();';
			}
			openDialogAlert(getAlert('os256'),400,140,'parent',$callbackFun);
			exit;
		}
		/* 주문 저장 */
		$this->db->trans_begin();
		$rollback	= false;

		$order_params['settle_price']			= $this->settle_price;
		$order_params['shipping_cost']			= $this->shipping_cost;
		$order_params['shipping_order']			= $this->shipping_order;
		$order_params['pgCompany']				= $pgCompany;
		$order_params['krw_exchange_rate']		= get_exchange_rate("KRW");		//원화(KRW) 환율정보

		// 주문서쿠폰 정보
		$order_params['ordersheet_seq']			= $this->cart['ordersheet_coupon_download_seq'];
		$order_params['ordersheet_sale']		= $this->cart['total_sale_list']['ordersheet']['price'];
		if($order_params['ordersheet_sale'] > 0){
			$order_params['ordersheet_sale_krw'] = get_currency_exchange($order_params['ordersheet_sale'],"KRW",$this->config_system['basic_currency']);
		}else{
			$order_params['ordersheet_sale_krw'] = '0';
		}

		$order_seq	= $this->ordermodel->insert_order($order_params,$this->shipping_group_policy);

		// 주문 통계 저장
		$this->statsmodel->insert_order_stats($order_seq);

		//배송지 저장 (로그인한 경우만)
		if($_POST['save_delivery_address']){
			$this->ordermodel->insert_delivery_address('order');
		}elseif($_POST['save_delivery_address_often']){
			$this->ordermodel->insert_delivery_address('insert');
		}else{
			$this->ordermodel->insert_delivery_address();
		}

		// 결제 배송정책 저장 ### :: START
		$sum_postpaid = 0;
		$this->load->model('providermodel');


		/**
		* 정산개선 배열초기화
		* @ accountallmodel
		**/
		$account_ins_shipping	= array();
		$account_ins_opt		= array();
		$account_ins_subopt		= array();

		foreach($this->shipping_group_policy as $shipping_group => $data_policy){
			$insert_params = array();
			$provider_seq						= $data_policy['provider_seq'];
			$insert_params['shipping_group']	= $shipping_group;
			$ship_set_code = $data_policy['shipping_cfg']['cfg']['baserule']['shipping_set_code'];
			$insert_params['shipping_method']	= $ship_set_code;

			if($data_policy['prepay_info'] == 'delivery')	$shipping_type = 'prepay';
			else											$shipping_type = 'postpaid';
			$insert_params['shipping_type']		= ($data_policy['shipping_cfg']['grp_shipping_price'] > 0) ? $shipping_type : 'free';
			$insert_params['order_seq']			= $order_seq;
			$insert_params['provider_seq']		= $provider_seq;

			if($data_policy['policy']=='goods'){
				$real_shipping_cost = 0;
			}else{
				$real_shipping_cost = $this->shipping_group_cost[$shipping_group]['shop'];
			}

			// 선착불 정보 재 정의 :: 2016-08-08 lwh
			$shipping_method = $insert_params['shipping_type'];
			if(preg_match('/prepay/',$shipping_method)|| preg_match('/postpaid/',$shipping_method)){
				if(preg_match('/prepay/',$shipping_method)){
					$insert_params['shipping_cost'] = $real_shipping_cost;
					$insert_params['postpaid'] = 0;
				}

				if(preg_match('/postpaid/',$shipping_method)){
					$insert_params['shipping_cost'] = 0;
					$insert_params['postpaid'] = $real_shipping_cost;
				}

				$insert_params['delivery_if'] = $data_policy['free'];
				$insert_params['international_cost'] = 0;

				//배송비할인쿠폰
				if( $shipping_method == 'prepay' && $this->shipping_coupon_sale[$shipping_group] && $this->shipping_coupon_down_seq[$shipping_group] ) {
					$insert_params['shipping_coupon_sale']		= $this->shipping_coupon_sale[$shipping_group];
					$insert_params['shipping_coupon_down_seq']		= $this->shipping_coupon_down_seq[$shipping_group];
					$insert_params['salescost_provider_coupon']		= $this->shipping_coupon_salecost[$shipping_group];

					//$this->load->model('couponmodel');
					//$this->couponmodel->set_download_use_status($this->shipping_coupon_down_seq[$provider_seq],'used');
				}

				//배송비할인프로모션코드
				if( $shipping_method == 'prepay' && $this->shipping_promotion_code_sale[$provider_seq] && $this->shipping_promotion_code_sale[$provider_seq] ) {
					$insert_params['shipping_promotion_code_sale']		= $this->shipping_promotion_code_sale[$provider_seq];
					$insert_params['shipping_promotion_code_seq']		= $this->session->userdata('cart_promotioncodeseq_'.session_id());
					$insert_params['salescost_provider_promotion']		= $this->shipping_promotion_code_salecost[$provider_seq];
				}
			}

			$shipping_cfg = '';
			// ### NEW 배송비 계산 :: 2016-08-09 lwh
			$shipping_cfg = $data_policy['shipping_cfg'];
			$insert_params['delivery_cost']		= $shipping_cfg['shipping_std_cost'];
			$insert_params['add_delivery_cost'] = $shipping_cfg['shipping_add_cost'];
			$insert_params['hop_delivery_cost'] = $shipping_cfg['shipping_hop_cost'];
			if($shipping_cfg['shipping_hop_date'])		$insert_params['shipping_hop_date'] = $shipping_cfg['shipping_hop_date'];
			if($shipping_cfg['cfg']['baserule']['shipping_set_code'] == 'direct_store'){
				$insert_params['store_scm_type'] = $shipping_cfg['store_info']['store_scm_type'];
				$insert_params['shipping_address_seq'] = $shipping_cfg['store_info']['shipping_address_seq'];
			}

			// 예약상품배송일 :: 2016-11-16 lwh
			if($shipping_cfg['reserve_sdate']) {
				$insert_params['reserve_sdate']		= $shipping_cfg['reserve_sdate'];
			}

			if	($insert_params['add_delivery_cost'])
			{
				if	($data_policy['shipping_ini']['nation'] == 'KOREA'){
					$address_tmp = ($data_policy['shipping_ini']['zibun_address']) ? $data_policy['shipping_ini']['zibun_address'] : $data_policy['shipping_ini']['street_address'];
					$add_arr_tmp = explode(' ',$address_tmp);
					$insert_params['add_delivery_area'] = $add_arr_tmp[0] . ' ' . $add_arr_tmp[1] . ' ' . $add_arr_tmp[2];
				}else{
					$insert_params['add_delivery_area'] = $data_policy['shipping_ini']['nation'];
				}
			}

			# 원화기준 실배송비  @2016-11-01
			if($insert_params['shipping_cost'] > 0){
				$insert_params['shipping_cost_krw'] = get_currency_exchange($insert_params['shipping_cost'],"KRW",$this->config_system['basic_currency']);
			}else{
				$insert_params['shipping_cost_krw'] = '0';
			}

			// 주문당시 배송 설정명 저장 :: 2016-09-23 lwh
			$insert_params['shipping_set_name']	= $shipping_cfg['cfg']['baserule']['shipping_set_name'];

			// 주문당시 반품/교환 배송비 저장 :: 2018-05-15 lwh
			$insert_params['refund_shiping_cost']	= $data_policy['shipping_cfg']['refund_shiping_cost'];
			$insert_params['swap_shiping_cost']		= $data_policy['shipping_cfg']['swap_shiping_cost'];
			$insert_params['shiping_free_yn']		= $data_policy['shipping_cfg']['shiping_free_yn'];

			// shipping 로그 등록
			$log_seq = $this->shippingmodel->set_shipping_log($order_seq,$shipping_cfg);

			$this->db->insert('fm_order_shipping', $insert_params);
			$shipping_seq = $this->db->insert_id();

			/**
			* 정산개선 - 배송처리 : 순서변경주의 시작
			* data : 주문정보
			* insert_params : 배송정보
			* @ accountallmodel
			**/
			$insert_params['order_form_seq']			= $shipping_seq;
			$insert_params['shipping_seq']				= $shipping_seq;//배송비할인쿠폰
			if( $insert_params['shipping_coupon_sale'] && $insert_params['shipping_coupon_down_seq'] ) {
				$insert_params['coupon_sale_provider']	= $this->shipping_coupon_sale_provider[$shipping_group];
			}
			if( $insert_params['shipping_promotion_code_sale'] && $insert_params['shipping_promotion_code_seq'] ) {
				$insert_params['code_sale_provider']	= $this->shipping_promotion_code_sale_provider[$provider_seq];
			}
			$insert_params['shipping_charge']			= $shipping_cfg['shipping_charge'];
			$insert_params['return_shipping_charge']	= $shipping_cfg['return_shipping_charge'];
			$insert_params['accountallmodeltest']		= "accountallmodeltest_ship";
			$account_ins_shipping[$shipping_seq] = array_merge($data_policy,$insert_params);
			/**
			* 정산개선 - 배송처리 : 순서변경주의 끝
			* data : 주문정보
			* insert_params : 배송정보
			* @
			**/
			unset($insert_params);

			$data_policy['shipping_seq'] = $shipping_seq;
			$this->shipping_group_policy[$shipping_group] = $data_policy;

			$shipping_seq_array[$provider_seq] = $shipping_seq;
		}
		// 결제 배송정책 저장 ### :: END

		// 장바구니 수량 체크
		if( count($this->cart['list'])==0 || !$this->cart['list']) $rollback = true;

		$r_except_item	= array();
		$tax["exempt"]	= 0;
		$tax["tax"]		= 0;
		$total_ea		= 0;
		$goods_info		= array();
		$package_yn		= 'n';

		foreach($this->cart['list'] as $k => $data){
			if($data['package_yn'] == 'y')				$package_yn = 'y';

			/**
			 * 20210416(kjw)
			 * shipping_seq 누락 이슈에 대한 예외 처리 및 log 삽입
			 */
			if(!$this->shipping_group_policy[$data['shipping_group']]['shipping_seq']) {
				$this->db->trans_rollback();

				$write_log = [
					"key" => "not_exist_shipping_seq",
					"shipping_seq" => $this->shipping_group_policy[$data['shipping_group']]['shipping_seq'],
					"shipping_group" => $data['shipping_group'],
					"shipping_group_policy" => $this->shipping_group_policy,
					"insert_params" => $insert_params,
				];
				writeCsLog($write_log, "order_error_log", "order");

				## 주문서 생성중 오류가 발생했습니다.
				openDialogAlert(getAlert('os082'),400,140,$popupKind,$pg_cancel_script);
				if($popupKind == 'opener') {
					echo "<script>window.close();</script>";
				}
				exit;
			}

			$key_item = $data['shipping_group'].$data['goods_seq'];

			// 배송그룹 별로 fm_order_item row 생성
			if( ! $r_except_item[$key_item] ){

	  			$insert_params = array();
 				$insert_params['provider_seq'] 	= $data['provider_seq'];
				$insert_params['shipping_seq'] 	= $this->shipping_group_policy[$data['shipping_group']]['shipping_seq'];

				// 예약 배송일 item 추가 :: 2016-11-16 lwh
				if	($data['display_terms'] == 'AUTO' && ($data['display_terms_begin'] <=  date('Y-m-d') && $data['display_terms_end'] >= date('Y-m-d')) && $data['display_terms_type'] == 'LAYAWAY'){
					$insert_params['reservation_ship']		= 'y';
					$insert_params['reservation_ship_date'] = $data['possible_shipping_date'];
				}else{
					$insert_params['reservation_ship']		= 'n';
				}

				$insert_params['order_seq'] 	= $order_seq;
				$insert_params['goods_seq'] 	= $data['goods_seq'];
				$insert_params['goods_code'] 	= $data['goods_code'];
				$insert_params['image'] 		= $data['image'];
				$insert_params['goods_name'] 	= $data['goods_name'];
				$insert_params['multi_discount_ea']	= $data['multi_discount_ea'];
				$insert_params['tax']				= $data['tax'];
				$insert_params['goods_type']		= $data['goods_type'];

				$insert_params['individual_refund']	= $data['individual_refund']?$data['individual_refund']:'0';
				$insert_params['individual_refund_inherit']	= $data['individual_refund_inherit']?$data['individual_refund_inherit']:'0';
				$insert_params['individual_export']	= $data['individual_export']?$data['individual_export']:'0';
				$insert_params['individual_return']	= $data['individual_return']?$data['individual_return']:'0';

				// 개별배송비 계산
				if($_POST['international'] == 0){
					$insert_params['goods_shipping_cost'] 	= get_cutting_price( $this->cart['data_goods'][$data['goods_seq']]['goods_shipping']);
					$insert_params['shipping_policy'] 		= $this->cart['data_goods'][$data['goods_seq']]['shipping_policy']; // 배송정책
					$insert_params['shipping_unit'] 		= $this->cart['data_goods'][$data['goods_seq']]['limit_shipping_ea']; // 합포장단위
					$insert_params['basic_shipping_cost'] 	=  $this->cart['data_goods'][$data['goods_seq']]['limit_shipping_price']; // 기본포장 배송비
					$insert_params['add_shipping_cost'] 	=  $this->cart['data_goods'][$data['goods_seq']]['limit_shipping_subprice']; // 추가포장배송비
				}else if($_POST['international'] == 1){
					$insert_params['shipping_policy'] = 'shop';
				}

				//티켓상품저장@2013-10-22
				$insert_params['goods_kind']		= ($data['goods_kind'])?$data['goods_kind']:'goods';
				$insert_params['socialcp_input_type']= $this->cart['data_goods'][$data['goods_seq']]['socialcp_input_type'];
				$insert_params['socialcp_use_return']= $this->cart['data_goods'][$data['goods_seq']]['socialcp_use_return'];
				$insert_params['socialcp_use_emoney_day']= $this->cart['data_goods'][$data['goods_seq']]['socialcp_use_emoney_day'];
				$insert_params['socialcp_use_emoney_percent']= $this->cart['data_goods'][$data['goods_seq']]['socialcp_use_emoney_percent'];

				$insert_params['social_goods_group'] 	= $this->cart['data_goods'][$data['goods_seq']]['social_goods_group'];//티켓상품그룹

				$insert_params['socialcp_cancel_use_refund'] 				= $this->cart['data_goods'][$data['goods_seq']]['socialcp_cancel_use_refund'];
				$insert_params['socialcp_cancel_payoption'] 				= $this->cart['data_goods'][$data['goods_seq']]['socialcp_cancel_payoption'];
				$insert_params['socialcp_cancel_payoption_percent'] 	= $this->cart['data_goods'][$data['goods_seq']]['socialcp_cancel_payoption_percent'];

				// 기존 이벤트 고유번호를 data_goods에서 가져왔지만 데이터가 없어 list에서 가져오도록 수정 2018-07-13 pjw
				if( $data['event']['event_seq']) {//이벤트 고유번호 추가
					$insert_params['event_seq'] = $data['event']['event_seq'];
				}

				// 성인인증상품 주문시 데이터 저장 :: 2015-03-19 lwh
				$insert_params['adult_goods'] = $data['adult_goods'];

				// 해외배송상품
				$insert_params['hscode']								= $data['hscode'];
				$insert_params['option_international_shipping_status']	= $data['option_international_shipping_status'];

				if(!$insert_params['goods_shipping_cost'])
					$insert_params['goods_shipping_cost'] = '0';

				// [판매지수 EP] 주문 아이템에 유입경로 추가 :: 2018-09-14 pjw
				if(!$this->visitorlog) $this->load->model('visitorlog');
				$insert_params['referer_domain'] = $this->visitorlog->get_sales_ep_referer($data['goods_seq']);

				// [퍼스트몰 라이브] broadcast_seq 추가 :: 2020-11-12 hyem
				// bs_type live 일때는 방송 중인지 체크 vod는 상관없음
				if($data['bs_type'] && $data['bs_seq']) {
					if($data['bs_type'] == 'live') {
						$this->load->model("broadcastmodel");
						$sch = $this->broadcastmodel->getSchEach($data['bs_seq']);
						if($sch['status'] != 'live') {
							unset($data['bs_seq'],$data['bs_type']);
						}
					}
					if($data['bs_type'] && $data['bs_seq']) {
						$insert_params['bs_seq'] = $data['bs_seq'];
						$insert_params['bs_type'] = $data['bs_type'];
					}
				}

				$this->db->insert('fm_order_item', $insert_params);
				$item_seq = $this->db->insert_id();

				if( $data['goods_kind'] == 'coupon' ) {
					//티켓상품 주문취소설정 @2013-10-22
					$this->ordermodel->order_insert_socialcp_cancel($data['goods_seq'], $order_seq, $item_seq);
				}

				$r_except_item[$key_item] = $item_seq;

			}

			if($goods_name == "") {
				$goods_name = $data['goods_name'];
				$pg_goods_seq = $data['goods_seq'];
				$pg_goods_image = $data['image'];
			}

			if	($r_except_item[$key_item])
				$item_seq	= $r_except_item[$key_item];

			$insert_params = array();
			$insert_params['order_seq'] 	= $order_seq;
			$insert_params['item_seq'] 		= $item_seq;
			$insert_params['provider_seq'] 	= $data['provider_seq'];
			$insert_params['shipping_seq'] 	= $this->shipping_group_policy[$data['shipping_group']]['shipping_seq'];
			$insert_params['step'] 			= "0";
			$insert_params['price'] 		= $data['price'];
			$insert_params['ori_price'] 	= $data['ori_price'];
			$insert_params['org_price'] 	= $data['org_price'];
			$insert_params['goods_price'] 	= $data['goods_price'];//할인미적용 판매가(ori_price / ori_price 변질)
			$insert_params['sale_price'] 	= $data['sale_price'];//할인가격(개당)
			// 기본할인 내역
			$insert_params['original_price']	= get_cutting_price($data['original_price']);//0 정가
			$insert_params['basic_sale']		= get_cutting_price($data['basic_sale']);//0 기본 할인(개당)
			$insert_params['event_sale_target']	= $data['event_sale_target'];
			$insert_params['event_sale']		= $data['event_sale'];//1 이벤트할인(개당) , get_cutting_price() 사용하지 말것. sale libraries 에서 할인계산식 전용 절삭기준 적용
			$insert_params['multi_sale']		= $data['multi_sale'];//2 복수구매할인(개당)
			$insert_params['member_sale'] 		= $data['member_sale_unit'];//5 회원등급(개당)

			###
			$insert_params['reserve']		= get_cutting_price($data['reserve_one']);//지급적립금(개당)
			$insert_params['reserve_log'] 	= $data['reserve_log'];
			$insert_params['point']			= get_cutting_price($data['point_one']);//지급포인트(개당)
			$insert_params['point_log'] 	= $data['point_log'];

			//상품할인쿠폰
			$insert_params['download_seq']	= $data['download_seq'];
			$insert_params['coupon_sale']		= $data['coupon_sale'];

			# 원화기준 상품할인쿠폰 : @2016-11-01
			if($insert_params['coupon_sale'] > 0){
				$insert_params['coupon_sale_krw'] = get_currency_exchange($insert_params['coupon_sale'],"KRW",$this->config_system['basic_currency']);
			}else{
				$insert_params['coupon_sale_krw'] = 0;
			}

			//상품할인프로모션코드
			$insert_params['promotion_code_seq']	= $data['promotion_code_seq'];
			$insert_params['promotion_code_sale']	= $data['promotion_code_sale'];

			# 원화기준 상품할인프로모션코드 : @2016-11-01
			if($insert_params['promotion_code_sale'] > 0){
				$insert_params['promotion_code_sale_krw'] = get_currency_exchange($insert_params['promotion_code_sale'],"KRW",$this->config_system['basic_currency']);
			}else{
				$insert_params['promotion_code_sale_krw'] = 0;
			}

			$insert_params['fblike_sale']				= $data['fblike_sale'];
			$insert_params['mobile_sale']				= $data['mobile_sale'];

			// 유입경로 할인
			$insert_params['referersale_seq']	= $data['referersale_seq'];
			$insert_params['referer_sale']				= $data['referer_sale'];
			$insert_params['purchase_goods_name']	= $data['purchase_goods_name'];

			//프로모션할인 내역
			$insert_params['coupon_sale_unit']			= $data['coupon_sale_unit'];//3 쿠폰할인(개당)
			$insert_params['code_sale_unit']			= $data['code_sale_unit'];//4 코드할인(개당)
			$insert_params['fblike_sale_unit']			= $data['fblike_sale_unit'];//6 좋아요할인(개당)
			$insert_params['mobile_sale_unit']			= $data['mobile_sale_unit'];//7 모바일할인(개당)
			$insert_params['referer_sale_unit']			= $data['referer_sale_unit'];//8 유입경로할인(개당)

			$insert_params['coupon_sale_rest']			= $data['coupon_sale_rest'];//3 쿠폰할인-짜투리
			$insert_params['code_sale_rest']			= $data['code_sale_rest'];//4 코드할인-짜투리
			$insert_params['fblike_sale_rest']			= $data['fblike_sale_rest'];//6 좋아요할인-짜투리
			$insert_params['mobile_sale_rest']			= $data['mobile_sale_rest'];//7 모바일할인-짜투리
			$insert_params['referer_sale_rest']			= $data['referer_sale_rest'];//8 유입경로할인-짜투리

			// 물류관리 버전이고 과세상품이면 평균매입가에 부가세를 포함시키고 매입처상품명을 주매입처정보에서 가져옴.
			if( $this->scm_cfg['use'] == 'Y' ){
				$sc['option_seq'] = $data['option_seq'];
				$sc['goods_seq'] = $data['goods_seq'];
				list($data_defaultinfo) = $this->scmmodel->get_order_defaultinfo($sc);
				$insert_params['purchase_goods_name']	= $data_defaultinfo['supply_goods_name'];
				if	($data['tax']){
					$data['supply_price']	= $data['supply_price'] + round($data['supply_price'] * 0.1);
					$data['supply_price']	= $this->scmmodel->cut_exchange_price($this->config_system['basic_currency'], $data['supply_price']);
				}
			}

			$insert_params['consumer_price']	= get_cutting_price($data['consumer_price']);
			$insert_params['supply_price'] 		= get_cutting_price($data['supply_price']);
			$insert_params['ea'] 			= $data['ea'];
			$insert_params['title1'] 		= $data['title1'];
			$insert_params['option1'] 		= $data['option1'];
			$insert_params['title2'] 		= $data['title2'];
			$insert_params['option2'] 		= $data['option2'];
			$insert_params['title3'] 		= $data['title3'];
			$insert_params['option3'] 		= $data['option3'];
			$insert_params['title4'] 		= $data['title4'];
			$insert_params['option4'] 		= $data['option4'];
			$insert_params['title5'] 		= $data['title5'];
			$insert_params['option5'] 		= $data['option5'];
			$insert_params['reserve_log'] 	= $data['reserve_log'];

			//특수정보 관련 추가@2013-10-22
			list($data['optioncode1'],$data['optioncode2'],$data['optioncode3'],$data['optioncode4'],$data['optioncode5'],$data['color'],$data['zipcode'],$data['address_type'],$data['address'],$data['address_street'],$data['addressdetail'],$data['biztel'],$data['coupon_input'],$data['codedate'],$data['sdayinput'],$data['fdayinput'],$data['dayauto_type'],$data['sdayauto'],$data['fdayauto'],$data['dayauto_day'],$data['newtype'],$data['address_commission']) = $this->goodsmodel->get_goods_option_code(
				$data['goods_seq'],
				$data['option1'],
				$data['option2'],
				$data['option3'],
				$data['option4'],
				$data['option5']
			);

			$insert_params['newtype']								= $data['newtype'];
			$insert_params['color']									= $data['color'];
			$insert_params['zipcode']								= $data['zipcode'];
			$insert_params['address_type']						= $data['address_type'];
			$insert_params['address']								= $data['address'];
			$insert_params['address_street']						= $data['address_street'];
			$insert_params['addressdetail']						= $data['addressdetail'];
			$insert_params['biztel']									= $data['biztel'];
			$insert_params['address_commission']			= $data['address_commission'];

			//티켓상품의 1장값어치와 유효기간 계산하기
			//if( $data['goods_kind'] == 'coupon' ) {//}
			$insert_params['coupon_input']			= $data['coupon_input'];//티켓상품의 1장값어치 횟수-금액
			 //티켓상품의 1회 금액
			if($this->cart['data_goods'][$data['goods_seq']]['socialcp_input_type'] == 'pass'){
				if($data['coupon_input'] > 0 && $data['price'] > 0){
					$insert_params['coupon_input_one'] = $data['price'] / $data['coupon_input'];
				}
			}else{
				$insert_params['coupon_input_one'] = $data['coupon_input'];
			}
			$insert_params['codedate']			= $data['codedate'];
			$insert_params['sdayinput']		= $data['sdayinput'];
			$insert_params['fdayinput']			= $data['fdayinput'];
			$insert_params['dayauto_type']	= $data['dayauto_type'];
			$insert_params['sdayauto']			= $data['sdayauto'];
			$insert_params['fdayauto']			= $data['fdayauto'];
			$insert_params['dayauto_day']	= $data['dayauto_day'];

			$insert_params['optioncode1'] = $data['optioncode1'];
			$insert_params['optioncode2'] = $data['optioncode2'];
			$insert_params['optioncode3'] = $data['optioncode3'];
			$insert_params['optioncode4'] = $data['optioncode4'];
			$insert_params['optioncode5'] = $data['optioncode5'];
			$insert_params['goods_code'] = $data['goods_code'].$data['optioncode1'].$data['optioncode2'].$data['optioncode3'].$data['optioncode4'].$data['optioncode5'];//조합된상품코드

			/*
			1. 할인이벤트로 인한 정산수수료 재계산 accountallmodel @20190516 pjm
			2. 옵션/추가옵션의 정산관련 데이터는 미노출로 변경됨에 따라 저장안함. (모든 정산데이터는 정산테이블에서만 관리)
			*/
			$_commission_info					= array();
			foreach(get_commission_info_field() as $_field) $_commission_info[$_field] = $data[$_field];
			$data['commission_rate']			= reset_commission_rate($_commission_info,$data['event']);

			// 할인이벤트: 입점사부담금 확인(개당)
			$provider_event_sales	= $this->eventmodel->get_salecost_provider($data);

			## 쿠폰할인 할인부담금 적용
			$this->load->model('couponmodel');
			$salescost_provider_coupon	= $this->couponmodel->get_salecost_provider($data);
			$insert_params['salescost_provider_coupon']	= $salescost_provider_coupon;
			//$insert_params['commission_price']				= $insert_params['commission_price'] - $salescost_provider_coupon;

			## 프로모션 할인부담금 적용
			$this->load->model('promotionmodel');
			$salescost_provider_promotion	= $this->promotionmodel->get_salecost_provider($data);
			$insert_params['salescost_provider_promotion']	= $salescost_provider_promotion;
			//$insert_params['commission_price']				= $insert_params['commission_price'] - $salescost_provider_promotion;

			## 유입경로 할인부담금 적용
			$this->load->model('referermodel');
			$salescost_provider_referer	= $this->referermodel->get_salecost_provider($data);
			$insert_params['salescost_provider_referer']	= $salescost_provider_referer;
			//$insert_params['commission_price']				= $insert_params['commission_price'] - $salescost_provider_referer;

			$salescost_provider				= array();
			$salescost_provider['event']	= $provider_event_sales;
			$salescost_provider['coupon']	= $salescost_provider_coupon;
			$salescost_provider['promotion']= $salescost_provider_promotion;
			$salescost_provider['referer']	= $salescost_provider_referer;

			$_commission_info['price']				= $data['price'];
			$_commission_info['target_price']		= $data['price'] - array_sum($salescost_provider);
			$_commission_info['commission_rate']	= $data['commission_rate'];
			$_commission_info['pay_price']			= $data['sale_price'];
			$_commission_info['salescost_provider']	= $salescost_provider;
			$_return_commission 					= get_commission($_commission_info);
			$insert_params['commission_price'] 		= $_return_commission['old_commission_unit_price'];		// (구)정산금액 : 기존처럼 option에 저장됨.

			# 원화기준 정산가 : @2016-11-01
			$insert_params['commission_price_krw']	= $_return_commission['old_commission_unit_price_krw'];	// (구)정산금액 : 기존처럼 option에 저장됨.

			// 패키지여부
			$insert_params['package_yn'] = $data['package_yn'];

			// 개별 배송메세지 저장 :: 2016-09-02 lwh
			if($_POST['each_msg'] == 'Y')
				$insert_params['ship_message'] = $_POST['each_memo'][$k];

			// 주문서쿠폰할인 추가
			$insert_params['unit_ordersheet'] = $data['unit_ordersheet'];

			$this->db->insert('fm_order_item_option', $insert_params);
			$item_option_seq = $this->db->insert_id();

			/**
			* 정산개선 - 옵션처리 시작
			* data : 주문정보
			* insert_params : 필수옵션정보
			* @ accountallmodel
			**/
			$insert_params['order_goods_seq']			= $data['goods_seq'];
			$insert_params['order_goods_name']			= $data['goods_name'];
			$insert_params['order_goods_kind']			= $data['goods_kind'];
			$insert_params['commission_rate']			= $data['commission_rate'];
			$insert_params['commission_type']			= $data['commission_type'];
			$insert_params['commission_price'] 			= $_return_commission['commission_unit_price'];			//(신)정산금액
			$insert_params['commission_price_krw']		= $_return_commission['commission_unit_price_krw'];		//(신)정산금액 원화기준 정산가
			$insert_params['item_option_seq']			= $item_option_seq;
			$insert_params['order_form_seq']			= $item_option_seq;
			$insert_params['shipping_seq']				= $this->shipping_group_policy[$data['shipping_group']]['shipping_seq'];
			$insert_params['multi_sale_provider']		= ($data['provider_seq'] != 1)?100:0;//해당상품이 입점사상품이면 입점사부담율 100%/본사라면 0
			$insert_params['event_sale_provider']		= $data['event']['salescost_provider']; // 입점사 할인 분담금(총 금액)
			$insert_params['coupon_sale_provider']		= $data['coupon']['salescost_provider'];
			$insert_params['code_sale_provider']		= $data['promotion']['salescost_provider'];
			$insert_params['referer_sale_provider']		= $data['referersale']['salescost_provider'];
			$insert_params['accountallmodeltest']		= "accountallmodeltest_opt";
			$insert_params['socialcp_status']			= $data['ea'];
			$insert_params['coupon_value_type']			= $data['socialcp_input_type'];
			$insert_params['coupon_value']				= ($data['coupon_input']*$data['ea']);
			$insert_params['coupon_remain_value']		= ($data['coupon_input']*$data['ea']);
			$insert_params['bs_seq']					= $data['bs_seq'];			// 라이브 방송 seq
			$insert_params['bs_type']					= $data['bs_type'];			// 라이브 방송 type
			$account_ins_opt[$item_option_seq] = array_merge($insert_params,$data);

			/**
			* 정산개선 - 옵션처리 끝
			* data : 주문정보
			* insert_params : 필수옵션정보
			* @
			**/

  			if($data['cart_suboptions']){
				foreach($data['cart_suboptions'] as $data_suboptions){
					if($data_suboptions['suboption_title'] && $data_suboptions['suboption']){
						$insert_params = array();
						$insert_params['order_seq']			= $order_seq;
						$insert_params['item_seq']			= $item_seq;
						$insert_params['item_option_seq'] 	= $item_option_seq;
						$insert_params['step'] 				= "0";
						$insert_params['price'] 			= get_cutting_price($data_suboptions['price']);
						$insert_params['org_price']			= get_cutting_price($data_suboptions['org_price']);
						$insert_params['member_sale'] 		= get_cutting_price($data_suboptions['member_sale_unit']);

						$insert_params['point']				= get_cutting_price($data_suboptions['point']/$data_suboptions['ea']);
						$insert_params['reserve']			= get_cutting_price($data_suboptions['reserve']/$data_suboptions['ea']);

						// 기본할인 내역
						$insert_params['original_price']	= get_cutting_price($data_suboptions['original_price']);//0 정가
						$insert_params['basic_sale']		= $data_suboptions['basic_sale'];
						$insert_params['event_sale_target']	= $data_suboptions['event_sale_target'];
						$insert_params['event_sale']		= $data_suboptions['event_sale'];
						$insert_params['multi_sale']		= $data_suboptions['multi_sale'];

						/* 구매 시 마일리지액 제한 조건 추가 leewh 2014-06-25 */
						if ($data_suboptions['reserve_log']) {
							$insert_params['reserve_log'] = $data_suboptions['reserve_log'];
						}

						// 추가옵션 포인트 로그 추가 2015-03-30
						if ($data_suboptions['point_log']) $insert_params['point_log'] = $data_suboptions['point_log'];

						// 물류관리 버전이고 과세상품이면 평균매입가에 부가세를 포함시키고 매입처상품명을 주매입처정보에서 가져옴.
						if( $this->scm_cfg['use'] == 'Y' ){
							$sc['suboption_seq'] = $data_suboptions['suboption_seq'];
							$sc['goods_seq'] = $data['goods_seq'];
							list($data_defaultinfo) = $this->scmmodel->get_order_defaultinfo($sc);
							$insert_params['purchase_goods_name']	= $data_defaultinfo['supply_goods_name'];
							if	($data['tax']){
								$data_suboptions['supply_price']	= $data_suboptions['supply_price'] + round($data_suboptions['supply_price'] * 0.1);
								$data_suboptions['supply_price']	= $this->scmmodel->cut_exchange_price($this->config_system['basic_currency'], $data_suboptions['supply_price']);
							}
						}

						$insert_params['consumer_price']	= get_cutting_price($data_suboptions['consumer_price']);
						$insert_params['supply_price'] 		= get_cutting_price($data_suboptions['supply_price']);
						$insert_params['ea'] 		= $data_suboptions['ea'];
						$insert_params['title'] 	= $data_suboptions['suboption_title'];
						$insert_params['suboption'] = $data_suboptions['suboption'];

						//특수정보 관련 추가@2013-10-22
						list($data_suboptions['suboption_code'],$data_suboptions['color'],$data_suboptions['zipcode'],$data_suboptions['address_type'],$data_suboptions['address'],$data_suboptions['address_street'],$data_suboptions['addressdetail'],$data_suboptions['biztel'],$data_suboptions['coupon_input'],$data_suboptions['codedate'],$data_suboptions['sdayinput'],$data_suboptions['fdayinput'],$data_suboptions['dayauto_type'],$data_suboptions['sdayauto'],$data_suboptions['fdayauto'],$data_suboptions['dayauto_day'],$data_suboptions['newtype']) = $this->goodsmodel->get_goods_suboption_code($data['goods_seq'],$data_suboptions['suboption_title'],$data_suboptions['suboption']);

						$insert_params['newtype']			= $data_suboptions['newtype'];
						$insert_params['color']				= $data_suboptions['color'];
						$insert_params['zipcode']			= $data_suboptions['zipcode'];
						$insert_params['address_type']		= $data_suboptions['address_type'];
						$insert_params['address']			= $data_suboptions['address'];
						$insert_params['address_street']	= $data_suboptions['address_street'];
						$insert_params['addressdetail']		= $data_suboptions['addressdetail'];
						$insert_params['biztel']			= $data_suboptions['biztel'];
						$insert_params['coupon_input']		= $data_suboptions['coupon_input'];//티켓상품의 1장값어치 횟수-금액

						if($data['coupon_input']) {
							$insert_params['coupon_input_one']	= ( $this->cart['data_goods'][$data['goods_seq']]['socialcp_input_type'] == 'pass' )?($data['price']/$data['coupon_input']):$data['coupon_input'];//티켓상품의 1회 금액
						} else {
							$insert_params['coupon_input_one']	= 0;
						}

						$insert_params['codedate']			= $data_suboptions['codedate'];
						$insert_params['sdayinput']			= $data_suboptions['sdayinput'];
						$insert_params['fdayinput']			= $data_suboptions['fdayinput'];
						$insert_params['dayauto_type']		= $data_suboptions['dayauto_type'];
						$insert_params['sdayauto']			= $data_suboptions['sdayauto'];
						$insert_params['fdayauto']			= $data_suboptions['fdayauto'];
						$insert_params['dayauto_day']		= $data_suboptions['dayauto_day'];

						$insert_params['suboption_code']	= $data_suboptions['suboption_code'];
						$insert_params['goods_code'] 		= $data['goods_code'].$data_suboptions['suboption_code'];//조합된상품코드

						## 정산수수료 재계산. accountallmodel 이동 @20190516 pjm
						$subopt_data					= $data_suboptions;
						$subopt_data['provider_seq']	= $data['provider_seq'];
						$_commission_info				= array();
						$_commission_info['price']				= $subopt_data['price'];
						$_commission_info['target_price']		= $subopt_data['price'];
						$_commission_info['pay_price']			= $subopt_data['price'];
						$_commission_info['salescost_provider']	= array();
						foreach(get_commission_info_field() as $_field)	$_commission_info[$_field] = $subopt_data[$_field];
						$data_suboptions['commission_rate'] = reset_commission_rate($_commission_info,'');

						$_return_commission 					= get_commission($_commission_info);
						$insert_params['commission_price'] 		= $_return_commission['old_commission_unit_price'];		// (구)정산금액 : 기존처럼 option에 저장됨.

						# 원화기준 정산가 : @2016-11-01
						$insert_params['commission_price_krw']	= $_return_commission['old_commission_unit_price_krw'];	// (구)정산금액 : 기존처럼 option에 저장됨.

						// 연결여부
						$package_count_sub = 'n';
						if($data_suboptions['package_count_sub']){
							$package_count_sub = 'y';
							$package_yn = 'y';
						}
						$insert_params['package_yn'] = $package_count_sub;

						$this->db->insert('fm_order_item_suboption', $insert_params);
						$item_suboption_seq = $this->db->insert_id();

						/**
						* 정산개선 - 추가옵션처리 시작
						* data : 주문정보
						* insert_params : 추가옵션정보
						* @ accountallmodel
						**/
						$insert_params['order_goods_seq']			= $data['goods_seq'];
						$insert_params['order_goods_name']			= $data['goods_name'];
						$insert_params['order_goods_kind']			= $data['goods_kind'];
						$insert_params['commission_price'] 			= $_return_commission['commission_unit_price'];			//(신)정산금액
						$insert_params['commission_price_krw']		= $_return_commission['commission_unit_price_krw'];		//(신)정산금액 원화기준 정산가
						$insert_params['item_suboption_seq']		= $item_suboption_seq;
						$insert_params['order_form_seq']			= $item_suboption_seq;
						$insert_params['provider_seq'] 				= $data['provider_seq'];
						$insert_params['shipping_seq']				= $this->shipping_group_policy[$data['shipping_group']]['shipping_seq'];
						$insert_params['accountallmodeltest']		= "accountallmodeltest_sub";
						$account_ins_subopt[$item_suboption_seq] = array_merge($insert_params,$data_suboptions);
						/**
						* 정산개선 - 추가옵션처리 끝
						* data : 주문정보
						* insert_params : 추가옵션정보
						* @accountallmodel
						**/

						if($item_suboption_seq){
							$cart_suboption_real_seq[$data_suboptions['cart_suboption_seq']] = array(
								'order_item_seq' => $item_seq,
								'order_item_suboption_seq' => $item_suboption_seq
							);
						}
					}
				}
			}

			/* 추가입력옵션 */
			if($data['cart_inputs']){
				foreach($data['cart_inputs'] as $data_inputs){
					$insert_params = array();
					if( $data_inputs['input_value'] ){
						$insert_params['order_seq'] = $order_seq;
						$insert_params['item_seq'] 	= $item_seq;
						$insert_params['item_option_seq'] 	= $item_option_seq;
						$insert_params['type'] 		= $data_inputs['type'];
						$insert_params['title'] 	= $data_inputs['input_title'];
						$insert_params['value'] 	= $data_inputs['input_value'];
						if($data_inputs['type'] !== 'file'){
							$insert_params['value'] = emojiFilter($insert_params['value']);
						}
						$this->db->insert('fm_order_item_input', $insert_params);
					}

				}
			}

			## kcp escrow시 사용
			$goods_infos['seq']			= $item_option_seq;
			$goods_infos['ordr_numb']	= $order_seq;
			$goods_infos['good_name']	= preg_replace("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "",$data['goods_name']);
			$goods_infos['good_cntx']	= $data['ea'];
			$goods_infos['good_amtx']	= $data['price'];
			$goods_info[] = $goods_infos;

		}

		$total_shipping_cost = array_sum($this->cart['shipping_price']);

		### GIFT 2015-05-13 pjm
		if($_POST['gift_use']=='Y'){
			$this->load->model('giftmodel');
			$gifts = array();
			foreach($_POST['gifts'] as $key => $gift_seq){

				$nm = "gift_".$gift_seq;
				$ship_grp[$gift_seq][] = $_POST['ship_grp_seq'][$key];

				if(is_array($_POST[$nm])){
					foreach($_POST[$nm] as $data) $gifts[$gift_seq][] = $data;
				}else{
					$gifts[$gift_seq][] = $_POST[$nm];
				}
			}

			//사은품 item 저장
			foreach($gifts as $gift_seq=>$gift_items){
				$target_goods = '';
				foreach($gift['gloop'] as $gift_loop){
					if($gift_loop['gift_seq'] == $gift_seq && !$target_goods){
						$target_goods = $gift_loop[$ship_grp_seq['ship_grp_seq']]['real_target_goods'];
						continue;
					}
				}

				//사은품 item
				foreach($gift_items as $gift_goods){
					//사은품 이벤트 입점사
					$gift_real_provider_seq	= $gifts_provider[$gift_seq];

					//사은품 지급 조건이 되는 상품의 배송그룹과 동일한 배송그룹으로 설정
					if($target_goods){
						$where_target_goods = "and item.goods_seq in(".implode(",",$target_goods).")";
					}
					$query = "select
								ship.shipping_seq,ship.shipping_group,
								CASE ship.shipping_method
									WHEN 'delivery' then '1'
									WHEN 'postpaid' then '2'
									WHEN 'each_delivery' then '3'
									WHEN 'each_postpaid' then '4'
									WHEN 'quick' then '5'
									WHEN 'direct' then '6'
									ELSE '7'
								END AS shipping_method_case
							from
								fm_order_item as item left join fm_order_shipping as ship on item.shipping_seq=ship.shipping_seq
							 where
								item.order_seq=?
								".$where_target_goods."
								and item.provider_seq=?
							order by shipping_method_case asc
							limit 1";
					$bind					= array($order_seq,$gift_real_provider_seq);
					$query					= $this->db->query($query,$bind);
					$target_goods_shipping	= $query->result_array();
					$shipping_seq			= $target_goods_shipping[0]['shipping_seq'];
					$gift_shipping_group	= $target_goods_shipping[0]['shipping_group'];

					if($gift_goods && $shipping_seq){

						unset($gift_params);
						$gift_params['order_seq'] 	= $order_seq;
						$gift_params['shipping_seq']= $shipping_seq;
						$gift_params['provider_seq']= $gift_real_provider_seq;	//사은품 입점사
						$gift_params['goods_seq']	= $gift_goods;
						$gift_params['image']		= get_gift_image($gift_goods,'thumbCart');
						$gift_params['goods_name']	= get_gift_name($gift_goods);
						$gift_params['goods_type ']	= 'gift';
						$this->db->insert('fm_order_item', $gift_params);
						$item_seq = $this->db->insert_id();

						unset($gift_params);
						$gift_params['order_seq'] 	= $order_seq;
						$gift_params['provider_seq']= $gift_real_provider_seq;	//사은품 입점사
						$gift_params['item_seq'] 	= $item_seq;
						$gift_params['shipping_seq']= $shipping_seq;
						$gift_params['step'] 		= "0";
						$gift_params['price'] 		= "0";
						$gift_params['ori_price'] 	= "0";
						$gift_params['ea'] 			= "1";
						$gift_params['supply_price'] 	= $this->giftmodel->get_gift_supply_price($gift_goods);
						$gift_params['goods_code'] = get_gift_name($gift_goods, 'goods_code');
						$this->db->insert('fm_order_item_option', $gift_params);
						/* log_order_gift */
						foreach($gift['gloop'] as $giftdata){
							if($giftdata['gift_seq'] == $gift_seq){
								$this->giftmodel->save_log($gift_seq,$giftdata,$order_seq,$item_seq);
							}
						}

					}
				}
			}
		}

		# 세금계산서 관련
		$sales_config = ($this->cfg_order) ? $this->cfg_order : config_load('order');
		$this->load->model('salesmodel');

		// 카드, 휴대폰일경우 매출전표만 출력가능하게 수정
		// 카카오페이 추가 :: 2015-02-27 lwh
		// paypal 추가 :: 2016-07-21 pjm
		if(in_array($_POST['payment'],array('card','cellphone','kakaopay','payco','paypal'))) { $_POST['typereceipt'] = 0; }
		$total_goods_shipping_price = $this->cart['shipping_price']['goods'];

		if(!$_POST['shipping_method'] || $_POST['shipping_method'] == 'delivery'){
			$shipping_price = $this->shipping_cost + $total_goods_shipping_price; // 배송비
		}

		# 주문 데이터를 토대로 과세상품액, 비과세액, 과세 배송비금액 구해오기
		$tax_invoice_type	= ($_POST['typereceipt'] == 1) ? true : false;		//세금 계산서 신청여부
		$all_order_list		= $this->ordermodel->get_order($order_seq);
		$order_tax_prices	= $this->ordermodel->get_order_prices_for_tax($order_seq,$all_order_list,$tax_invoice_type);

		# 사용한 마일리지/예치금 포함 금액
		$exempt_in_price = (!$order_tax_prices['exempt_in_price'])? '0':$order_tax_prices['exempt_in_price'];

		# 절사처리 포함
		$data_tax = $this->salesmodel->tax_calulate(
										$order_tax_prices["tax"],
										$order_tax_prices["exempt"],
										$order_tax_prices["shipping_cost"],
										$order_tax_prices["sale"],
										$order_tax_prices["tax_sale"],
										'SETTLE',
										$order_tax_prices["tax_goods_cnt"]);

		$tax['tax']		= $order_tax_prices["tax"];
		$tax['exempt']	= $order_tax_prices["exempt"];

		$supply			= $data_tax['supply'];
		$surtax			= $data_tax['surtax'];
		$taxprice		= $data_tax['supply'] + $data_tax['surtax'];

		if( $data_tax['supply_free'] > 0 ){
			$this->freeprice		= $data_tax['supply_free'];
			$this->comm_tax_mny		= $data_tax['supply'];
			$this->comm_vat_mny		= $data_tax['surtax'];
			if(!$supply || $surtax){
				$supply		= $data_tax['supply'] + $data_tax['supply_free'];
				$surtax		= $data_tax['surtax'];
				$taxprice	= $data_tax['supply']
							+ $data_tax['surtax']
							+ $data_tax['supply_free'];
			}
		}else{
			$this->comm_tax_mny		= $data_tax['supply'];
			$this->comm_vat_mny		= $data_tax['surtax'];
		}

		$typereceipt = $_POST['typereceipt'];
		// 무통장, 계좌이체(에스크로), 가상계좌(에스크로) 일 때 typereceipt = 0 이면 강제 공백 처리
		if(in_array($_POST['payment'],array('bank','account','escrow_account','virtual','escrow_virtual')) && $typereceipt =="0") {
			$typereceipt = "";
		}
		// 현금영수증 의무발급 체크 (의무발급 사용, 결제금액>=설정금액, 고객이 현금영수증 신청 안함)
		if ($sales_config['cashreceiptauto'] == 1 && $taxprice >= $sales_config['cashreceiptautoprice'] && trim($typereceipt) == '') {
			$typereceipt = 2;
			$cashreceiptauto = 1;
		}

		// 현금영수증이고, taxprice == 0 경우에는 증빙 선택 초기화 함
		if ($typereceipt == 2 && $taxprice == 0) {
			$typereceipt = '';
		}

		// 세금계산서 신청일 경우
		if($typereceipt > 0) {
			if($typereceipt == 1) {

				foreach($tax as $k => $v){
					if($v != 0){
						$taxparams['typereceipt'	]	= $typereceipt;
						$taxparams['type']				= 0;
						$taxparams['order_seq']		= $order_seq;
						$taxparams['member_seq']	= $this->userInfo['member_seq'];
						if($_POST['adminOrder'] == 'admin'){
							$taxparams['member_seq']=$_POST['member_seq'];
						}

						if(!$_POST['co_new_zipcode']) $_POST['co_new_zipcode'] = join("", $_POST['co_zipcode']);

						###
						if($k == 'exempt'){
							$taxparams['price'		]		= $data_tax['supply_free'];
							$taxparams['supply'		]		= $data_tax['supply_free'];
							$taxparams['surtax'		]		= 0;
							$taxparams['emoney_in_price']	= $exempt_in_price;
							$taxparams['vat_type']			= 2;						//비과세(일반계산서)
						}else{
							$taxparams['price'		]		= $data_tax['supply'] + $data_tax['surtax'];
							$taxparams['supply'		]		= $data_tax['supply'];
							$taxparams['surtax'		]		= $data_tax['surtax'];
							$taxparams['vat_type']			= 1;						//과세(세금계산서)
						}

						$taxparams['co_name']			= $_POST['co_name'];
						$taxparams['co_ceo']			= $_POST['co_ceo'];
						$taxparams['co_status']			= $_POST['co_status'];
						$taxparams['co_type']			= $_POST['co_type'];
						$taxparams['busi_no']			= $_POST['busi_no'];
						$taxparams['order_name']		= $_POST['order_user_name'];
						$taxparams['person']			= $_POST['person'];
						$taxparams['order_name']		= $_POST['order_user_name'];
						$taxparams['zipcode']			= $_POST['co_new_zipcode'];
						$taxparams['address_type']		= ($_POST['co_address_type'])?$_POST['co_address_type']:"zibun";
						$taxparams['address']			= $_POST['co_address'];
						$taxparams['address_street']	= $_POST['co_address_street'];
						$taxparams['address_detail']	= $_POST['co_address_detail'];
						$taxparams['email']				= $_POST['email'];
						$taxparams['phone']				= $_POST['phone'];
						$taxparams['order_date']		= date('Y-m-d H:i:s');
						$taxparams['regdate']			= date('Y-m-d H:i:s');

						if ( $taxparams['price'] > 0 ) $this->salesmodel->sales_write($taxparams);
					}
				}
			}
			// 현금영수증 신청일 경우 -- pg 사용중일때만 현금영수증 신청 되도록
			else if($typereceipt == 2  && $this->config_system['not_use_pg'] =='n') {
				$this->load->library('cashtax');

				if(( count($this->cart['list']) - 1) > 0) $item_name = $goods_name." 외 " . ( count($this->cart['list']) - 1) . "건";//상품명 생성
				$creceipt_number					= str_replace("-", "", $_POST['creceipt_number'][$_POST['cuse']]);
				$creceipt_type						= '0'; // 초기값
				// 의무발급인 경우 발급번호 고정
				if( $cashreceiptauto == 1 ) {
					$creceipt_number	= '0100001234';
					$creceipt_type		= "3";			// 자진발급
				}
				$cashparams['creceipt_number'	]	= $creceipt_number;
				$cashparams['typereceipt'		]	= $typereceipt;
				$cashparams['type']					= $creceipt_type;
				$cashparams['order_seq'			]	= $order_seq;
				$cashparams['order_date'		]	= date("Y-m-d H:i:s");
				$cashparams['member_seq'		]	= $this->userInfo['member_seq'];
				if($_POST['adminOrder'] == 'admin'){
					$cashparams['member_seq']=$_POST['member_seq'];
				}

				###
				$cashparams['price'				]	= $taxprice;
				$cashparams['supply'			]	= $supply;
				$cashparams['surtax'			]	= $surtax;

				$cashparams['person'			]	= $_POST['order_user_name'];
				$cashparams['order_name'		]	= $_POST['order_user_name'];

				$cashparams['cuse'				]	= $_POST['cuse'];
				$cashparams['goodsname'			]	= ($item_name)?$item_name:$goods_name;
				$cashparams['tstep']				= 1;
				$cashparams['email']			= $_POST['sales_email'] ? $_POST['sales_email'] : $_POST['order_email'];
				$cashparams['regdate']			= date('Y-m-d H:i:s');

				$result_id = $this->salesmodel->sales_write($cashparams);
				$cashparams['paydt']= $cashparams['regdate'];
			}
		}else{
			if( $_POST['payment'] == 'card' ||  $_POST['payment'] == 'cellphone' ||  $_POST['payment'] == 'kakaopay' ||  $_POST['payment'] == 'payco' ) {//매출전표를위해
				$salesparams['typereceipt'	]	= $typereceipt;
				$salesparams['type'			]	= 0;
				$salesparams['order_seq'	]	= $order_seq;
				$salesparams['member_seq'	]	= $this->userInfo['member_seq'];
				$salesparams['order_date'	]	= date("Y-m-d H:i:s");
				$salesparams['up_date']			= date("Y-m-d H:i:s");
				$cashparams['regdate']			= date('Y-m-d H:i:s');

				// 카카오 페이인 경우 매출증빙 PG사 저장 :: 2015-02-25 lwh
				if($_POST['payment'] == 'kakaopay' ||  $_POST['payment'] == 'payco')
					$salesparams['pg_kind']		= $_POST['payment'];

				###
				$salesparams['price'		]		= $taxprice;
				$salesparams['supply'		]		= $supply;
				$salesparams['surtax'		]		= $surtax;

				$salesparams['order_name']				= $_POST['order_user_name'];
				$salesparams['person']					= $_POST['order_user_name'];
				$salesparams['goodsname'			]	= ($item_name)?$item_name:$goods_name;
				$this->salesmodel->sales_write($salesparams);
			}
		}

		## 주문 착불배송비 저장.비과세 금액 주문에 저장(PG결제시 사용)
		$set_params		= array(
			'postpaid'		=> $sum_postpaid,
			'freeprice'		=> $this->freeprice,
			'pg'			=> $pgCompany,
			'typereceipt'	=> $typereceipt
		);
		$where_params	= array(
			'order_seq'	=> $order_seq
		);
		$this->ordermodel->set_order($set_params,$where_params);


		// 주문 총주문수량 / 총상품종류 업데이트 leewh 2014-08-01
		$this->ordermodel->update_order_total_info($order_seq);
		// 마일리지/에누리/예치금 사용 상품옵션,추가옵션 별로 나누기
		$this->ordermodel->update_unit_emoney_cash_enuri($order_seq);//상품별통계로 그대로 둠

		// 연결상품이 있을경우
		if( $package_yn == 'y' ){
			$this->load->model('orderpackagemodel');
			$this->orderpackagemodel->package_order($order_seq);

			$aOrderItemOption = $this->ordermodel->get_item_option($order_seq);
			$aOrderItemSubOption = $this->ordermodel->get_item_suboption($order_seq);

			if($aOrderItemOption){
				foreach($aOrderItemOption as $row){
					foreach($account_ins_opt as $seq => &$opt){
						if($row['item_option_seq'] == $seq){
							$opt['supply_price'] = $row['supply_price'];
						}
					}
				}
			}
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

		/**
		* 1-1 주문데이타를 이용한 임시매출데이타 생성 시작
		* step1->step2->step3 순차로 진행되어야 합니다.
		* @
		**/
		if(!$this->accountall)$this->load->helper('accountall');
		if(!$this->accountallmodel)$this->load->model('accountallmodel');

		//step1 주문금액별 정의/비율/단가계산 후 정렬
		$set_order_price_ratio = $this->accountallmodel->set_order_price_ratio($order_seq, $account_ins_opt, $account_ins_subopt, $account_ins_shipping);

		//step2 적립금/이머니/에누리(관리자주문) update
		if( $all_order_list['emoney']>0 || $all_order_list['cash']>0  || $all_order_list['enuri']>0 ) {
			$this->accountallmodel->update_ratio_emoney_cash_enuri_npoint($order_seq, $set_order_price_ratio, 'all');
		}
		//step3 임시 매출/정산 저장
		$this->accountallmodel->insert_calculate_sales_order_tmp($order_seq, $set_order_price_ratio, $account_ins_opt, $account_ins_subopt, $account_ins_shipping);

		//debug_var($account_ins_opt);
		//debug_var($account_ins_shipping);
		//debug_var($this->db->queries);
		//debug_var($this->db->query_times);
		/**
		* 1-1 주문데이타를 이용한 임시매출데이타 생성 시작
		* step1->step2->step3 순차로 진행되어야 합니다.
		* @
		**/
		if ($this->db->trans_status() === FALSE || $rollback == true)
		{
			$this->db->trans_rollback();

			## 주문서 생성중 오류가 발생했습니다.
			openDialogAlert(getAlert('os082'),400,140,$popupKind,$pg_cancel_script);
			if($popupKind == 'opener') {
				echo "<script>window.close();</script>";
			}
			exit;
		}
		else
		{
			$this->db->trans_commit();
		}
		//  회원정보 가져오기(최초저장)
		if( $this->userInfo['member_seq'] ){
			$this->load->model('membermodel');
			$data_member			= $this->membermodel->get_member_data($this->userInfo['member_seq']);
			$member_phone			= str_replace('-','',$data_member['phone']);
			$member_cellphone		= str_replace('-','',$data_member['cellphone']);
			$member_zipcode			= str_replace('-','',$data_member['zipcode']);
			$member_address_type	= str_replace('-','',$data_member['address_type']);
			$member_address			= str_replace('-','',$data_member['address']);
			$member_address_street	= str_replace('-','',$data_member['address_street']);
			$member_address_detail	= str_replace('-','',$data_member['address_detail']);
			//회원이 최초주문시 회원정보 저장되는 구문제거 (일반몰매칭) @2016-06-20
		}

		//GA통계
		if($this->ga_auth_commerce_plus){
			$ga_flag = false;
			$ga_referer = "";
			foreach($this->cart['list'] as $item_arr){
				for($i=1;$i<5;$i++){
					$temp["option".$i] = $item_arr['option'.$i];
				}
				$temp["ea"] = $item_arr["ea"];
				$temp["goods_name"] = $item_arr["goods_name"];
				$temp["goods_seq"] = $item_arr["goods_seq"];
				$temp["price"] = $item_arr["price"];
				$temp["payment"] = $_POST['payment'];

				$ga_params["item"][] = $temp;
				if($_SESSION['ga_goods_seq'] == $item_arr["goods_seq"]) $ga_flag = true;
			}

			if($ga_flag) $ga_referer = $_SESSION['ga_referer'];

			$unsetuserdata = array('ga_referer' => '', 'ga_goods_seq' => '');
			$this->session->unset_userdata($unsetuserdata);
			$_SESSION['ga_referer']	= '';
			$_SESSION['ga_goods_seq']	= '';

			$ga_params['action'] = "checkout";
			$ga_params["page"] = $ga_referer;

			echo google_analytics($ga_params,"payment");
		}

		// 채널톡 연동
        $this->load->library('channeltalklibrary');
        $channeltalk_cart		= $this->channeltalklibrary->begin_checkout($order_seq, $order_params, $this->cart['list']);
        if($channeltalk_cart) echo $channeltalk_cart;
        
        // GA4 연동
        if  ($this->ga4_auth_commerce)  {
            $this->load->library('ga4library');
            $ga4_begin_checkout =   $this->ga4library->begin_checkout($order_params, $this->cart['list']);
            echo $ga4_begin_checkout;
        }
        

		//관리자 주문시 구매 요청자에게 알림문자
		if( $adminOrder == "admin" && $_POST['send_sms']=='Y'){
			if	(preg_replace('/[^0-9]/', '', $_POST['cellphone']))
			{
				$adminorder_msgstr = trim($_POST["msg"]);
				$adminorder_msgstr = str_replace("[주문자]", $_POST["order_user_name"], $adminorder_msgstr);
				$adminorder_params['msg'] = $adminorder_msgstr;
				$adminorder_commonSmsData['member']['phone'] = $_POST['cellphone'];
				$adminorder_commonSmsData['member']['params'] = $adminorder_params;

				commonSendSMS($adminorder_commonSmsData);
			}
		}

		// pg 모듈 로드
		if($pgCompany && $_POST['payment'] != "bank"){
			// pg사로 전달할 상품명 생성
			$cart_cnt = count($this->cart_lists) - 1;
			if($cart_cnt > 0) $goods_name .= " 외 " . $cart_cnt . "건";

			$this->pg_param['payment'] = $_POST['payment'];
			$this->pg_param['order_seq'] = $order_seq;
			$goods_name = preg_replace("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "", $goods_name);
			$this->pg_param['goods_name'] = $goods_name;
			$this->pg_param['goods_info']	= serialize($goods_info);		//장바구니 상세정보(에스크로시 사용) 2014.09-22
			$this->pg_param['goods_seq'] = $pg_goods_seq;
			$this->pg_param['goods_image'] = $pg_goods_image;
			$this->pg_param['settle_price'] = $this->settle_price;
			$this->pg_param['freeprice'] 	= $this->freeprice;
			$this->pg_param['comm_tax_mny'] = $this->comm_tax_mny;
			$this->pg_param['comm_vat_mny'] = $this->comm_vat_mny;
			$this->pg_param['order_user_name'] = $_POST['order_user_name'];
			$this->pg_param['order_email'] = $_POST['order_email'];
			$this->pg_param['order_phone'] = implode('-',$_POST['order_phone']);
			$this->pg_param['order_cellphone'] = implode('-',$_POST['order_cellphone']);
			if($_POST['mobilenew']) $this->pg_param['mobilenew'] = $_POST['mobilenew'];

			// 카카오페이 사용 시 예외처리 - 구버전와 동시사용X :: 2017-12-11 lwh
			if(($pgCompany == 'kakaopay' || $pgCompany == 'daumkakaopay') && $this->config_system['not_use_daumkakaopay'] == 'n'){
				$payConfig = config_load('daumkakaopay');
			}else{
				//2017-05-24 jhs 크로스 브라우징 결제모듈 추가
				$payConfig = config_load($pgCompany);
			}

			$naxCheck = $payConfig["nonActiveXUse"];

			// 자동 주문 무효 사용시 - 가상계좌 만료일 사용 2022.02.15 추가
			if ($sales_config['autocancel'] == 'y') {
				$VanExpirationDate	= date('Ymd235959',strtotime("+".$sales_config['cancelDuration']." day"));
				$this->pg_param['VanExpirationDate'] = $VanExpirationDate;
			}

			if(!$this->_is_mobile_agent) {
				if($naxCheck == "Y"){
					$jsonParam = base64_encode(json_encode($this->pg_param));
					echo("<form name='".$pgCompany."_settle_form' method='post' action='../".$pgCompany."/request'>");
					echo("<input type='hidden' name='jsonParam' value='".$jsonParam."' />");
					echo("</form>");
					echo("<script>document.".$pgCompany."_settle_form.submit();</script>");
					exit;
				}else{
					$this -> {$pgCompany}();
				}
			}else{
				if((($pgCompany == 'kakaopay' || $pgCompany == 'daumkakaopay') && $this->config_system['not_use_daumkakaopay'] == 'n') // 카카오 페이 사용 시 예외처리 :: 2017-12-21 lwh
					|| ($pgCompany == 'kicc')// kicc 예외처리 by hed
					){
					$jsonParam = base64_encode(json_encode($this->pg_param));
					echo("<form name='".$pgCompany."_settle_form' method='post' action='../".$pgCompany."/request'>");
					echo("<input type='hidden' name='jsonParam' value='".$jsonParam."' />");
					echo("</form>");
					echo("<script>document.".$pgCompany."_settle_form.submit();</script>");
					exit;
				}else if($pgCompany == 'payco' && $this->config_system['not_use_payco'] == 'n'){
					$jsonParam = base64_encode(json_encode($this->pg_param));
					echo("<form name='".$pgCompany."_settle_form' method='post' action='../".$pgCompany."/request'>");
					echo("<input type='hidden' name='jsonParam' value='".$jsonParam."' />");
					echo("</form>");
					echo("<script>document.".$pgCompany."_settle_form.submit();</script>");
					exit;
				}else{
					$this -> {$pgCompany}();
				}
			}

		}else{
			$this->order_seq = $order_seq;
			$this->bank($adminOrder);
		}
	}

	public function bank($adminOrder){
		$this->template->assign(array('order_seq'=>$this->order_seq));
		$this->template->assign(array('member_seq'=>$this->userInfo['member_seq']));
		$this->template->assign(array('adminOrder'=>$adminOrder));
		$this->template->template_dir	= BASEPATH."../order";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_bank.html'));
		$this->template->print_('tpl');
	}

	public function paypal(){

		$paypal_config = config_load("paypal");

		if(!$paypal_config['paypal_currency'] || !$paypal_config['paypal_currency'] || !$paypal_config['paypal_currency'] || !$paypal_config['paypal_currency']){
		    openDialogAlert(getAlert('os142',array('Paypal')),400,140,'parent');
			exit;
		}


		# 결제통화
		if(!$paypal_config['paypal_currency']) $paypal_config['paypal_currency'] = "USD";

		$order_seq	= $this->pg_param['order_seq'];
		$data_order	= $this->ordermodel->get_order($order_seq);

		$this->template->assign($this->pg_param);
		$this->template->assign(array('order_seq'=>$order_seq));
		$this->template->assign(array('mode'=>$_GET['mode']));
		$this->template->assign(array('basic_currency'=>$paypal_config['paypal_currency']));
		$this->template->assign(array('settle_price'=>$data_order['payment_price']));
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_paypal.html'));
		$this->template->print_('tpl');

	}

	public function paypal_order(){

		//$paymode = "test";
		if($this->mobileMode){
			$paymode = "mobile";
		}else{
			$paymode = "pc";
		}
		//error_reporting(E_ALL);
		require_once dirname(__FILE__)."/../../pg/paypal/API_Key.php";
		require_once dirname(__FILE__)."/../../pg/paypal/SendRequest.php";

		$currencyCodeType=isset($_REQUEST["currencyCodeType"]) ? ($_REQUEST["currencyCodeType"]) : "";

		$order_seq	= $_POST['order_seq'];
		$mode		= $_POST['mode'];

		$serverName = $_SERVER["SERVER_NAME"];
		$serverPort = $_SERVER["SERVER_PORT"];
		$nvpstr		= array();

		$itemnum = 0;
		$itemamt = 0.00;
		for($i=0;$i<count($_REQUEST['L_NAME']);$i++) {
			$L_NAME	= $_REQUEST["L_NAME"][$i];
			$L_AMT	= $_REQUEST["L_AMT"][$i];
			$L_QTY	= $_REQUEST["L_QTY"][$i];
			if($L_NAME != "" && is_numeric($L_AMT) && is_numeric($L_QTY)&&$L_AMT!=0&&$L_QTY!=0)
			{
			$nvpstr[] = "L_PAYMENTREQUEST_0_NAME".$itemnum."=".urlencode($L_NAME) ;
			$nvpstr[] = "L_PAYMENTREQUEST_0_AMT".$itemnum."=".$L_AMT ;
			$nvpstr[] = "L_PAYMENTREQUEST_0_QTY".$itemnum."=".$L_QTY ;
			$itemnum = $itemnum + 1;
			$itemamt = $itemamt + ($L_AMT*$L_QTY);
			}
		}

		$nvpstr[] = "PAYMENTREQUEST_0_ITEMAMT=".(string)$itemamt ;

		$currencyCodeType=urlencode($currencyCodeType);
		$paymentType="Sale";

		$amt		= $itemamt;

		$nvpstr[]	= "PAYMENTREQUEST_0_AMT=".(string)$amt ;
		$nvpstr[]	= "PAYMENTREQUEST_0_CURRENCYCODE=".$currencyCodeType ;
		$nvpstr[]	= "PAYMENTREQUEST_0_PAYMENTACTION=".$paymentType ;

		$returnURL	= $url_success."?order_seq=".$_POST['order_seq'];
		$returnURL	.= "&currencyCodeType=".$currencyCodeType."&paymentType=".$paymentType;
		$returnURL	.= "&amt=".$amt."&L_NAME=".$L_NAME."&L_AMT=".$L_AMT."&L_QTY=".$L_QTY;
		$returnURL = urlencode($returnURL);

		$url_cancel .= "?mode=".$mode;

		$cancelURL	= urlencode($url_cancel);
		$nvpstr[]	= "ReturnUrl=".$returnURL ;
		$nvpstr[]	= "CANCELURL=".$cancelURL ;

		$nvpstring	= $nvpHeader."&".implode("&",$nvpstr);

		# Express Checkout 거래 설정(판매자 사이트 리다이렉션 URL, 취소URL, 주문총액 등)
		$resArray				= hash_call("SetExpressCheckout",$nvpstring);

		$_SESSION["reshash"]	= $resArray;
		$ack					= strtoupper($resArray["ACK"]);

		//exit;
		if($ack=="SUCCESS"){

			# 실제 결제를 위해 Paypal 로 이동
			$token		= urldecode($resArray["TOKEN"]);
			$payPalURL	= PAYPAL_URL.$token;

			echo "<br />";
			echo "token : ".$token;

			$sql = "update fm_order set paypal_token='".$token."' where order_seq='".$order_seq."'";
			$this->db->query($sql);

			echo '<script type="text/javascript">parent.location.href="'.$payPalURL.'";</script>';
			exit;

			//header("Location: ".$payPalURL);


		  } else  {

			echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
			echo '<script type="text/javascript">'.$this->pg_cancel_script().'</script>';
			alert("Paypal Order Error! [".$resArray["L_ERRORCODE0"]."]",400,140,'parent');
			exit;

			//Redirecting to APIError.php to display errors.
			//$location = "APIError.php?flag=SetExpressCheckout";
			//header("Location: $location");
			//echo $resArray["L_ERRORCODE0"];

		}

	}

	public function kcp(){

		$pg = config_load($this->config_system['pgCompany']);

		if( $pg['nonInterestTerms'] == 'manual' && isset($pg['pcCardCompanyCode']) ){
			foreach($pg['pcCardCompanyCode'] as $key => $code){
				$arr = explode(',',$pg['pcCardCompanyTerms'][$key]);
				$terms = array();
				foreach($arr as $term){
					$terms[] = sprintf('%02d',$term);
				}
				$codes[] = $code . '-' . implode(':',$terms);
			}
			$pg_param['kcp_noint_quota'] = implode(',',$codes);
		}
		$pg_param['quotaopt']  = $pg['interestTerms'];
		/* bin 디렉토리 전까지의 경로를 입력,절대경로 입력 */
		$pg_param['g_conf_home_dir']  = dirname(__FILE__)."/../../pg/kcp/";
		/* 테스트  : testpaygw.kcp.co.kr
		 * 실결제  : paygw.kcp.co.kr */
		$pg_param['g_conf_gw_url']    = $pg['mallCode']=='T0007' ? "testpaygw.kcp.co.kr" : "paygw.kcp.co.kr";
		/* 테스트  : https://pay.kcp.co.kr/plugin/payplus_test.js
		 * 실결제  : https://pay.kcp.co.kr/plugin/payplus.js */
		$pg_param['g_conf_js_url']	  = $pg['mallCode']=='T0007' ? "https://pay.kcp.co.kr/plugin/payplus_test_un.js" : "https://pay.kcp.co.kr/plugin/payplus_un.js";
		/* 테스트 T0000 */
		$pg_param['g_conf_site_cd']   = $pg['mallCode'];
		/* 테스트 3grptw1.zW0GSo4PQdaGvsF__ */
		$pg_param['g_conf_site_key']  = $pg['merchantKey'];
		$pg_param['g_conf_site_name'] = $this->config_basic['shopName'];
		$pg_param['g_conf_log_level'] = "3";           // 변경불가
		$pg_param['g_conf_gw_port']   = "8090";        // 포트번호(변경불가)

		###
		$pg_param['kcp_logo_type']		= $pg['kcp_logo_type'];
		$pg_param['kcp_skin_color']		= $pg['kcp_skin_color'];
		if		($pg_param['kcp_logo_type'] == 'img' && !is_null($pg['kcp_logo_val_img'])){
			$pg_param['kcp_logo_val_img']	= $pg['kcp_logo_val_img'];
			$pg_param['kcp_logo_img']		= get_connet_protocol().$_SERVER['HTTP_HOST'].str_replace(ROOTPATH, '/', $pg['kcp_logo_val_img']);
		}elseif	($pg_param['kcp_logo_type'] == 'text' && !is_null($pg['kcp_logo_val_text'])){
			$pg_param['g_conf_site_name']	= $pg['kcp_logo_val_text'];
		}

		###
		$pg_param['comm_free_mny']	= $this->freeprice;
		$pg_param['comm_tax_mny']	= $this->comm_tax_mny;
		$pg_param['comm_vat_mny']	= $this->comm_vat_mny;

		$pg_param = array_merge($pg_param,$this->pg_param);
		$pg_param['goods_name'] = str_replace(array("(",")","<",">","[","]","{","}","-","'","\""), "", $pg_param['goods_name']);
		$pg_param['goods_name'] = preg_replace("/[ #\&\+\-%@=\/\\\:;,\.'\"\^`~\_|\!\?\*$#<>()\[\]\{\}]/i", "", $pg_param['goods_name']);

		$data_order = $this->ordermodel->get_order($this->pg_param['order_seq']);

		## 장바구니 상품정보
		$pg_param['good_info'] = "seq=1" . chr(31) . "ordr_numb=0001".chr(31)."good_name=".$pg_param['goods_name'].chr(31)."good_cntx=1".chr(31)."good_amtx=".$data_order['settleprice'];

		$payment = str_replace('escrow_','',$pg_param['payment']);
		if($payment != $pg_param['payment']){
			$pg_param['escorw'] = 1;
			$pg_param['payment'] = $payment;
		}

		// 주문 무효일자 추출 :: 2015-08-10 lwh
		$order_cfg = ($this->cfg_order) ? $this->cfg_order : config_load('order');
		if($order_cfg['autocancel'] == 'y'){
			$pg_param['ipgm_date'] = date("Ymd",time()+24*3600*$order_cfg['cancelDuration']);
		}else{
			$pg_param['ipgm_date'] = date("Ymd",time()+24*3600*3);
		}
		$pg_param['cancelDuration'] = $order_cfg['cancelDuration'];

		// 모바일 일경우 모바일 결제창
		if( $this->_is_mobile_agent)
		{
			if($this->pg_param['mobilenew'] == 'y') $this->pg_open_script();
			echo("<form name='kcp_settle_form' method='post' target='tar_opener' action='../kcp_mobile/auth'>");
			echo("<input type='hidden' name='order_seq' value='".$this->pg_param['order_seq']."' />");
			echo("<input type='hidden' name='goods_name' value='".$this->pg_param['goods_name']."' />");
			echo("<input type='hidden' name='goods_seq' value='".$this->pg_param['goods_seq']."' />");
			echo("<input type='hidden' name='payment' value='".$this->pg_param['payment']."' />");
			echo("<input type='hidden' name='mobilenew' value='".$this->pg_param['mobilenew']."' />");
			echo("<input type='hidden' name='goods_info' value='".$this->pg_param['goods_info']."' />");
			echo("<input type='hidden' name='comm_free_mny' value='" . $pg_param['comm_free_mny'] . "' />");
			echo("<input type='hidden' name='comm_tax_mny' value='" . $pg_param['comm_tax_mny'] . "' />");
			echo("<input type='hidden' name='comm_vat_mny' value='" . $pg_param['comm_vat_mny'] . "' />");
			echo("</form>");
			echo("<script>document.kcp_settle_form.submit();</script>");
			exit;
		}
		$this->template->assign($pg_param);
		$this->template->assign(array('data_order'=>$data_order));
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_kcp.html'));
		$this->template->print_('tpl');
	}

	public function lg()
	{
		 /*
	     * [결제 인증요청 페이지(STEP2-1)]
	     *
	     * 샘플페이지에서는 기본 파라미터만 예시되어 있으며, 별도로 필요하신 파라미터는 연동메뉴얼을 참고하시어 추가 하시기 바랍니다.
	     */

	    /*
	     * 1. 기본결제 인증요청 정보 변경
	     *
	     * 기본정보를 변경하여 주시기 바랍니다.(파라미터 전달시 POST를 사용하세요)	     *
	     */
		global $pg;
		$pg = config_load($this->config_system['pgCompany']);

		if( $pg['nonInterestTerms'] == 'manual' && isset($pg['pcCardCompanyCode']) ){
			foreach($pg['pcCardCompanyCode'] as $key => $code){
				$arr = explode(',',$pg['pcCardCompanyTerms'][$key]);
				$terms = array();
				foreach($arr as $term){
					$terms[] = sprintf('%02d',$term);
				}
				$codes[] = $code . '-' . implode(':',$terms);
			}
			$pg_param['lg_noint_quota'] = implode(',',$codes);
		}
		$pg_param['quotaopt']  = $pg['interestTerms'];
		$pg_param = array_merge($pg_param,$this->pg_param);
		$payment = str_replace('escrow_','',$pg_param['payment']);
		if($payment != $pg_param['payment']){
			$pg_param['escorw'] = 1;
			$pg_param['payment'] = $payment;
		}

		//$LGD_INSTALLRANGE = "0:2:3:4:5:6:7:8:9:10:11:12";        // LG유플러스 할부 기본값 (일시불 ~ 12개월)  2019-08-23 sms
        $quotaopt = (int)$pg_param['quotaopt'];
        if(1 < $quotaopt){
            $installrange = "0";
            for($i=2; $i<=$quotaopt; $i++){
                $installrange = $installrange.":".$i;
            }
            $LGD_INSTALLRANGE = $installrange;
        }else{
            $LGD_INSTALLRANGE = "0:2:3:4:5:6:7:8:9:10:11:12";
        }

		$pg['platform'] = "service";
		if( $pg['mallCode'] == 'gb_gabiatest01' )	$pg['mallCode'] = "gabiatest01";	//gabia test
		if( $pg['mallCode'] == 'gabiatest01' )		$pg['platform'] = "test"; //LG유플러스 결제 서비스 선택(test:테스트, service:서비스)

	    $param['CST_PLATFORM'] = $CST_PLATFORM = $pg['platform'];		//LG유플러스 결제 서비스 선택(test:테스트, service:서비스)
	    $param['CST_MID'] = $CST_MID = $pg['mallCode'];					//상점아이디(LG유플러스으로 부터 발급받으신 상점아이디를 입력하세요)

	                                                                        //테스트 아이디는 't'를 반드시 제외하고 입력하세요.
	    $param['LGD_MID'] = $LGD_MID = (("test" == $CST_PLATFORM)?"t":"").$CST_MID;  //상점아이디(자동생성)
	    $param['LGD_OID'] = $LGD_OID = $this->pg_param['order_seq'];           //주문번호(상점정의 유니크한 주문번호를 입력하세요)
	    $param['LGD_AMOUNT'] = $LGD_AMOUNT = $this->pg_param['settle_price'];        //결제금액("," 를 제외한 결제금액을 입력하세요)
	    $param['LGD_BUYER'] = $LGD_BUYER = $this->pg_param['order_user_name'];         //구매자명
	    $param['LGD_PRODUCTINFO'] = $LGD_PRODUCTINFO = $this->pg_param['goods_name'];   //상품명
	    $param['LGD_BUYEREMAIL'] = $LGD_BUYEREMAIL = $this->pg_param['order_email'];    //구매자 이메일
	    $param['LGD_TIMESTAMP'] = $LGD_TIMESTAMP = date(YmdHms);                         //타임스탬프
	    $param['LGD_CUSTOM_SKIN'] = $LGD_CUSTOM_SKIN = "blue";                               //상점정의 결제창 스킨 (red, blue, cyan, green, yellow)
	    $param['LGD_MERTKEY'] = $LGD_MERTKEY = $pg['merchantKey'];									//상점MertKey(mertkey는 상점관리자 -> 계약정보 -> 상점정보관리에서 확인하실수 있습니다)
		$configPath = dirname(__FILE__)."/../../pg/lgdacom/"; //LG유플러스에서 제공한 환경파일("/conf/lgdacom.conf") 위치 지정.
	    $param['LGD_BUYERID'] = $LGD_BUYERID = $this->userInfo['userid'];       //구매자 아이디
	    $param['LGD_BUYERIP'] = $LGD_BUYERIP = $_SERVER['REMOTE_ADDR'];       //구매자IP
		$param['LGD_INSTALLRANGE'] = $LGD_INSTALLRANGE;		//할부기간

		###
		$param['LGD_TAXFREEAMOUNT'] = $this->freeprice;

	    /*
	     * 가상계좌(무통장) 결제 연동을 하시는 경우 아래 LGD_CASNOTEURL 을 설정하여 주시기 바랍니다.
	     */
	    $param['LGD_CASNOTEURL'] =  $LGD_CASNOTEURL	= get_connet_protocol().$_SERVER['HTTP_HOST']."/payment/lg_return";
	    $param['LGD_KVPMISPAUTOAPPYN'] = "";

		// 가상 계좌인 경우 만료일시 추가
		$param['LGD_CLOSEDATE'] 				= $pg_param['VanExpirationDate'];; // 2022.02.15 가상계좌 만료일시추가 YYYYMMDDHHiiss

	    /*
	     *************************************************
	     * 2. MD5 해쉬암호화 (수정하지 마세요) - BEGIN
	     *
	     * MD5 해쉬암호화는 거래 위변조를 막기위한 방법입니다.
	     *************************************************
	     *
	     * 해쉬 암호화 적용( LGD_MID + LGD_OID + LGD_AMOUNT + LGD_TIMESTAMP + LGD_MERTKEY )
	     * LGD_MID          : 상점아이디
	     * LGD_OID          : 주문번호
	     * LGD_AMOUNT       : 금액
	     * LGD_TIMESTAMP    : 타임스탬프
	     * LGD_MERTKEY      : 상점MertKey (mertkey는 상점관리자 -> 계약정보 -> 상점정보관리에서 확인하실수 있습니다)
	     *
	     * MD5 해쉬데이터 암호화 검증을 위해
	     * LG유플러스에서 발급한 상점키(MertKey)를 환경설정 파일(lgdacom/conf/mall.conf)에 반드시 입력하여 주시기 바랍니다.
	     */
	    require_once($configPath."XPayClient.php");
	    $xpay = new XPayClient($configPath, $CST_PLATFORM);
	   	$xpay->Init_TX($LGD_MID);

	    $param['LGD_HASHDATA'] = $LGD_HASHDATA = md5($LGD_MID.$LGD_OID.$LGD_AMOUNT.$LGD_TIMESTAMP.$xpay->config[$LGD_MID]);
	    $param['LGD_CUSTOM_PROCESSTYPE'] = $LGD_CUSTOM_PROCESSTYPE = "TWOTR";
	    /*
	     *************************************************
	     * 2. MD5 해쉬암호화 (수정하지 마세요) - END
	     *************************************************
	     */

		## 접속 브라우저 확인 IE/기타.
		$userAgenr = getBrowser();
		if($userAgenr['nickname'] == "MSIE"){
			$browser = "IE";
		}else{
			$browser = "etc";
		}

		// 모바일 일경우 모바일 결제창
		if( $this->_is_mobile_agent)
		{
			if($this->pg_param['mobilenew'] == 'y') $this->pg_open_script();
			echo("<form name='lg_settle_form' method='post' action='../lg_mobile/auth'>");
			echo("<input type='hidden' name='order_seq' value='".$this->pg_param['order_seq']."' />");
			echo("<input type='hidden' name='goods_name' value='".$this->pg_param['goods_name']."' />");
			echo("<input type='hidden' name='goods_seq' value='".$this->pg_param['goods_seq']."' />");
			echo("<input type='hidden' name='mobilenew' value='".$this->pg_param['mobilenew']."' />");
			echo("<input type='hidden' name='VanExpirationDate' value='".$this->pg_param['VanExpirationDate']."' />");
			echo("</form>");
			echo("<script>document.lg_settle_form.submit();</script>");
			exit;
		}


	    $this->template->assign("browser",$browser);
	    $this->template->assign($param);
	    $this->template->assign($pg_param);
	    $this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir = BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_lg.html'));
		$this->template->print_('tpl');
	}

	public function allat()
	{
		$this->load->model('ordermodel');
		$pg = config_load($this->config_system['pgCompany']);
		if( isset($pg['pcCardCompanyCode']) ){
			foreach($pg['pcCardCompanyCode'] as $key => $code){
				$arr = explode(',',$pg['pcCardCompanyTerms'][$key]);
				$terms = array();
				foreach($arr as $term){
					$terms[] = sprintf('%02d',$term);
				}
				$codes[] = $code . '-' . implode(':',$terms);
			}
			$pg_param['allat_noint_quota'] = implode(',',$codes);
		}
		$pg_param['quotaopt']  = $pg['interestTerms'];
		$pg_param = array_merge($pg_param,$this->pg_param);
		$payment = str_replace('escrow_','',$pg_param['payment']);
		if($payment != $pg_param['payment']){
			$pg_param['escorw'] = 1;
			$pg_param['payment'] = $payment;
		}

		$data_order = $this->ordermodel -> get_order($this->pg_param['order_seq']);
		if($pg['mallCode'] == "FM_allat_test01") $pg['mallCode'] = "allat_test01";
		$param['allat_shop_id'] = $pg['mallCode'];
		$param['allat_order_no'] = $pg_param['order_seq'];
		$param['allat_amt'] = $pg_param['settle_price'];
		$param['allat_pmember_id'] = "GUEST";
		if( $this->userInfo['userid'] && strlen($this->userInfo['userid']) < 20 ) $param['allat_pmember_id'] = $this->userInfo['userid'];

		$param['allat_product_cd'] = $pg_param['goods_seq'];
		$param['allat_product_nm'] = $pg_param['goods_name'];
		$param['allat_buyer_nm'] = $pg_param['order_user_name'];
		$param['allat_recp_nm'] = $data_order['recipient_user_name'];
		$param['allat_recp_addr'] = $data_order['recipient_address']." ".$data_order['recipient_address_detail'];

		if(trim($param['allat_recp_addr'])=="") {
			$param['allat_recp_addr']	= $data_order['order_user_name'];
		}

		$param['allat_card_yn'] = 'N';
		$param['allat_bank_yn'] = 'N';
		$param['allat_vbank_yn'] = 'N';
		$param['allat_hp_yn'] = 'N';
		$param['allat_ticket_yn']  = 'N';
		if($pg_param['payment'] == 'card') $param['allat_card_yn'] = 'Y';
		if($pg_param['payment'] == 'account') $param['allat_bank_yn'] = 'Y';
		if($pg_param['payment'] == 'virtual') $param['allat_vbank_yn'] = 'Y';
		if($pg_param['payment'] == 'cellphone') $param['allat_hp_yn'] = 'Y';

		$param['allat_zerofee_yn'] = 'Y';
		$param['allat_cash_yn'] = 'N';
		$param['allat_email_addr'] = $data_order['order_email'];
		$param['allat_product_img'] = $pg_param['goods_image'];
		$param['allat_real_yn'] = 'Y';
		$param['allat_bankes_yn'] = 'N';
		$param['allat_vbankes_yn'] = 'N';
		if($pg_param['payment'] == 'account' && $pg_param['escorw']) $param['allat_bankes_yn'] = 'Y';
		if($pg_param['payment'] == 'virtual' && $pg_param['escorw']) $param['allat_vbankes_yn'] = 'Y';
		$param['allat_test_yn']  = 'N';
		if( $pg['mallCode'] == 'FM_pgfreete2' ) $param['allat_test_yn']  = 'Y';

		###
		$param['comm_free_mny']		= $this->freeprice;
		$param['comm_tax_mny']		= $this->comm_tax_mny;
		$param['comm_vat_mny']		= $this->comm_vat_mny;
		$param['allat_tax_yn']		= $this->comm_tax_mny ? 'Y':'N';

		// 복합과세 처리
		if($param['comm_tax_mny'] && $param['comm_free_mny']){
			$param['allat_multi_amt'] = $param['comm_tax_mny']."|".$param['comm_vat_mny']."|".(($param['comm_free_mny'])?$param['comm_free_mny']:"0");
		}

		// 모바일 일경우 모바일 결제창
		if( $this->_is_mobile_agent)
		{
			if($this->pg_param['mobilenew'] == 'y') $this->pg_open_script();
			echo("<form name='all_settle_form' method='post' target='tar_opener' action='../allat_mobile/allat'>");
			echo("<input type='hidden' name='order_seq' value='".$this->pg_param['order_seq']."' />");
			echo("<input type='hidden' name='goods_name' value='".$this->pg_param['goods_name']."' />");
			echo("<input type='hidden' name='goods_seq' value='".$this->pg_param['goods_seq']."' />");
			echo("<input type='hidden' name='mobilenew' value='".$this->pg_param['mobilenew']."' />");
			if($param['comm_free_mny']){
				echo("<input type='hidden' name='allat_multi_amt' value='".$param['allat_multi_amt']."' />");
			}
			echo("<input type='hidden' name='comm_free_mny' value='".$param['comm_free_mny']."' />");
			echo("<input type='hidden' name='comm_tax_mny' value='".$param['comm_tax_mny']."' />");
			echo("<input type='hidden' name='comm_vat_mny' value='".$param['comm_vat_mny']."' />");
			echo("</form>");
			echo("<script>document.all_settle_form.submit();</script>");
			exit;
		}

		$this->template->assign($param);
	    $this->template->assign($pg_param);
	    $this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir = BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_allat.html'));
		$this->template->print_('tpl');
	}

	public function inicis()
	{
		session_start();
		$pg_param = array();

		$this->load->model('ordermodel');
		$pg = config_load($this->config_system['pgCompany']);
		if( $pg['nonInterestTerms'] == 'manual' &&  isset($pg['pcCardCompanyCode']) ){
			foreach($pg['pcCardCompanyCode'] as $key => $code){
				$arr = explode(',',$pg['pcCardCompanyTerms'][$key]);
				$terms = array();
				foreach($arr as $term){
					$terms[] = sprintf('%02d',$term);
				}
				$codes[] = $code . '-' . implode(':',$terms);
			}
			$pg_param['inicis_noint_quota'] = implode(',',$codes);
		}

		$pg_param = array_merge($pg_param,$this->pg_param);
		$data_order = $this->ordermodel -> get_order($pg_param['order_seq']);
		$param['buyername'] = $data_order['order_user_name'];
		$param['buyeremail'] = $data_order['order_email'];
		$param['buyertel'] = $data_order['order_cellphone'];
		$pg_param['quotaopt']  = $pg['interestTerms'];

		$payment = str_replace('escrow_','',$pg_param['payment']);
		if($payment != $pg_param['payment']){
			$pg_param['escorw']		= 1;
			$pg_param['payment']	= $payment;
			$pg['mallCode']			= $pg['escrowMallCode'];
			$pg['merchantKey']		=  $pg['escrowMerchantKey'];
		}

		if($pg['mallCode'] == "GBFINIpayTest") $pg['mallCode'] = "INIpayTest";
		if($pg['mallCode'] == "GBF_INIpayTest") $pg['mallCode'] = "INIpayTest";

		$param['mallCode'] = $pg['mallCode'];

		/****************	**********
	     * 1. 라이브러리 인클루드 *
	     **************************/
	    require("./pg/inicis/libs/INILib.php");

	    /***************************************
	     * 2. INIpay50 클래스의 인스턴스 생성  *
	     ***************************************/
	    $inipay = new INIpay50;

	    /**************************
	     * 3. 암호화 대상/값 설정 *
	     **************************/
	    $inipay->SetField("inipayhome", dirname(__FILE__)."/../../pg/inicis");       // 이니페이 홈디렉터리(상점수정 필요)
	    $inipay->SetField("type", "chkfake");      // 고정 (절대 수정 불가)
	    $inipay->SetField("debug", "true");        // 로그모드("true"로 설정하면 상세로그가 생성됨.)
	    $inipay->SetField("enctype","asym"); 			//asym:비대칭, symm:대칭(현재 asym으로 고정)
	    /**************************************************************************************************
	     * admin 은 키패스워드 변수명입니다. 수정하시면 안됩니다. 1111의 부분만 수정해서 사용하시기 바랍니다.
	     * 키패스워드는 상점관리자 페이지(https://iniweb.inicis.com)의 비밀번호가 아닙니다. 주의해 주시기 바랍니다.
	     * 키패스워드는 숫자 4자리로만 구성됩니다. 이 값은 키파일 발급시 결정됩니다.
	     * 키패스워드 값을 확인하시려면 상점측에 발급된 키파일 안의 readme.txt 파일을 참조해 주십시오.
	     **************************************************************************************************/
		$inipay->SetField("admin", $pg['merchantKey']); 				// 키패스워드(키발급시 생성, 상점관리자 패스워드와 상관없음)
	    $inipay->SetField("checkopt", "false"); 		//base64함:false, base64안함:true(현재 false로 고정)

		//필수항목 : mid, price, nointerest, quotabase
		//추가가능 : INIregno, oid
		//*주의* : 	추가가능한 항목중 암호화 대상항목에 추가한 필드는 반드시 hidden 필드에선 제거하고
		//          SESSION이나 DB를 이용해 다음페이지(INIsecureresult.php)로 전달/셋팅되어야 합니다.
	    $inipay->SetField("mid", $pg['mallCode']);            // 상점아이디
	    $inipay->SetField("price", $pg_param['settle_price']);                // 가격

	    $quotabase	= "선택:일시불";
	    $terms		= "";

	    if($pg['interestTerms']){
	    	for($inter_i=2;$inter_i <= $pg['interestTerms'];$inter_i++){
	    		$arr_terms[] = $inter_i;
	    	}
	    	$terms = implode('개월:',$arr_terms)."개월";
	    }
	    $quotabase .= ":".$terms;//할부기간

	 	if($pg['nonInterestTerms'] == 'manual'){
	    	$inipay->SetField("nointerest", "yes");             //무이자여부(no:일반, yes:무이자)
	    	if($pg['pcCardCompanyCode']){
		    	foreach($pg['pcCardCompanyCode'] as $k_cardCompanyCode => $data_cardCompanyCode){
		    		if($data_cardCompanyCode && $pg['pcCardCompanyTerms'][$k_cardCompanyCode]){
		    			$r_cardCompanyCode[] = $data_cardCompanyCode."-".str_replace(",",":",$pg['pcCardCompanyTerms'][$k_cardCompanyCode]);
		    		}
		    	}
		    	if($r_cardCompanyCode){
		    		$quotabase .= "(".implode(',',$r_cardCompanyCode).")";
		    	}
	    	}
	    }else{
	    	$inipay->SetField("nointerest", "automatic");
	    }

		$param['quotabase'] = $quotabase;
		$quotabase = mb_convert_encoding($quotabase, "EUC-KR", "UTF-8");
	    $inipay->SetField("quotabase", $quotabase);

	    /********************************
	     * 4. 암호화 대상/값을 암호화함 *
	     ********************************/
	    $inipay->startAction();

	    /*********************
	     * 5. 암호화 결과  *
	     *********************/
 		if( $inipay->GetResult("ResultCode") != "00" )
		{
			echo $inipay->GetResult("ResultMsg");
			exit(0);
		}

	    /*********************
	     * 6. 세션정보 저장  *
	     *********************/
		$_SESSION['INI_MID']		= $pg['mallCode'];	//상점ID
		$_SESSION['INI_ADMIN']		= $pg['merchantKey'];			// 키패스워드(키발급시 생성, 상점관리자 패스워드와 상관없음)
		$_SESSION['INI_PRICE']		= $pg_param['settle_price'];     //가격
		$_SESSION['INI_RN']			= $inipay->GetResult("rn"); //고정 (절대 수정 불가)
		$_SESSION['INI_ENCTYPE']	= $inipay->GetResult("enctype"); //고정 (절대 수정 불가)

		###
		$param['comm_free_mny']		= $this->freeprice;
		$param['comm_tax_mny']		= $this->comm_tax_mny;
		$param['comm_vat_mny']		= $this->comm_vat_mny;

		$param['encfield'] = $inipay->GetResult("encfield");
		$param['certid'] = $inipay->GetResult("certid");

		/* ################ 16.12.29 gcs 장다혜 : 가상계좌 사용시 입금기한 고정 s */
		$order_config = ($this->cfg_order) ? $this->cfg_order : config_load('order');
		if($this->cfg_order['autocancel'] == 'y')
			$param['Vcard_date'] = date('Ymd',strtotime("+".$this->cfg_order['cancelDuration']." day", time()));
		/* ################ 16.12.29 gcs 장다혜 : 가상계좌 사용시 입금기한 고정 e */

		// 모바일 일경우 모바일 결제창
		if( $this->_is_mobile_agent)
		{
			//if($this->pg_param['mobilenew'] == 'y') $this->pg_open_script();
			echo("<form name='mobile_settle_form' method='post' action='../inicis_mobile/inicis'>");
			echo("<input type='hidden' name='order_seq' value='".$this->pg_param['order_seq']."' />");
			echo("<input type='hidden' name='goods_name' value='".$this->pg_param['goods_name']."' />");
			echo("<input type='hidden' name='goods_seq' value='".$this->pg_param['goods_seq']."' />");
			//echo("<input type='hidden' name='mobilenew' value='".$this->pg_param['mobilenew']."' />");
			echo("</form>");
			echo("<script>document.mobile_settle_form.submit();</script>");
			exit;
		}

		$this->template->assign($param);
	    $this->template->assign($pg_param);
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir = BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_inicis.html'));
		$this->template->print_('tpl');
	}

	public function kspay(){

		$this->load->model('ordermodel');
		$pg							= config_load($this->config_system['pgCompany']);
		$pg_param					= array_merge($pg, $this->pg_param);

		// 위 변조 체크 다른 로직과 동일하게
		$orders			= $this->ordermodel->get_order($pg_param['order_seq']);
		if	($orders['pg_currency'] == 'KRW') {
			$orders['settleprice']	= floor($orders['settleprice']);
		}
		if($orders['settleprice'] != $pg_param['settle_price']){
			echo("<script>alert('결제 금액이 일치하지 않습니다. 다시 한 번 시도해 주세요.');</script>");
			exit;
		}

		// 결제수단별 코드
		$pgCodeArr					= array('card'=>'1000000000', 'virtual'=>'0100000000',
											'account'=>'0010000000','cellphone'=>'0000010000',
											'escrow_virtual'=>'0100000000');
		$pg_param['paymentCode']	= $pgCodeArr[$this->pg_param['payment']];
		$pg_param['shopName']		= $this->config_basic['shopName'];
		$pg_param['escrow']			= '0';
		if	($this->pg_param['payment'] == 'escrow_virtual')
			$pg_param['escrow']			= '1';

		$tplpath					= '_kspay.html';
		$interestTerms				= $pg_param['interestTerms'];
		$nonInterestTerms				= $pg_param['nonInterestTerms'];
		$cardCompanyCode			= $pg_param['pcCardCompanyCode'];
		$cardCompanyTerms			= $pg_param['pcCardCompanyTerms'];
		if( $this->_is_mobile_agent){
			$tplpath			= '_kspay_mobile.html';
			$interestTerms		= $pg_param['mobileInterestTerms'];
			$nonInterestTerms	= $pg_param['mobileNonInterestTerms'];
			$cardCompanyCode	= $pg_param['mobileCardCompanyCode'];
			$cardCompanyTerms	= $pg_param['mobileCardCompanyTerms'];
		}

		// 할부개월수 설정
		for ($i = 0; $i <= $interestTerms; $i++){
			if	($i == 1)	continue;
			if	($i > 0)	$interestTermsStr	.= ':';
			$interestTermsStr	.= $i;
		}

		// 무이자 할부 설정
		$pg_param['kspay_noint_quota']	= 'NONE';
		if( $nonInterestTerms == 'manual' && is_array($cardCompanyCode) && count($cardCompanyCode) > 0 ){
			foreach($cardCompanyCode as $key => $code){
				$arr	= explode(',',$cardCompanyTerms[$key]);
				$terms	= array();
				foreach($arr as $term){
					$terms[]	= $term;
				}
				$codes[]	= $code . '(' . implode(':',$terms) . ')';
			}
			$pg_param['kspay_noint_quota'] = implode(',',$codes);
		}

		// 가상계좌 마감일자 설정 부분이 없어 기본 3일로 처리
		$vcard_date = date('Ymd',strtotime("+3 day", time()));
		$vcard_time = "235959";
		$pg_param['vcard_date']			= $vcard_date;
		$pg_param['vcard_time']			= $vcard_time;

		$pg_param['interestTermsStr']	= $interestTermsStr;
		$pg_param['order_cellphone']	= preg_replace('/[^0-9]/', '', $pg_param['order_cellphone']);
		$pg_param['goods_name']			= preg_replace('/[\'\"\`]/', '', $pg_param['goods_name']);
		$pg_param['domain']				= $this->config_system['domain'];

		$pg_param['comm_free_mny']		= $this->freeprice ? $this->freeprice : '0';
		$pg_param['comm_tax_mny']		= $this->comm_tax_mny ? $this->comm_tax_mny : '0';
		$pg_param['comm_vat_mny']		= $this->comm_vat_mny ? $this->comm_vat_mny : '0';

		$this->template->assign($pg_param);
		$this->template->template_dir	= BASEPATH."../order";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>$tplpath));
		$this->template->print_('tpl');
	}

	public function kspay_wh_rcv(){

		$rcid       = $_POST["reCommConId"];
		$rctype     = $_POST["reCommType"];
		$rhash      = $_POST["reHash"];

		$p_protocol	= "http";
		if (strlen($_SERVER['SERVER_PROTOCOL'])>4 && "https" == substr($_SERVER['SERVER_PROTOCOL'],0,5) )
		{
			$p_protocol = "https";
		}

		if (!empty($rcid) && 10 > strlen($rcid))	$script_display = "y";
		else										$script_display = "n";
		if( $this->_is_mobile_agent)				$tplpath		= '_kspay_wh_rcv_mobile.html';
		else										$tplpath		= '_kspay_wh_rcv.html';

		$this->template->assign(array('script_display'=>$script_display));
		$this->template->assign(array('rcid'=>$rcid));
		$this->template->assign(array('rctype'=>$rctype));
		$this->template->assign(array('rhash'=>$rhash));
		$this->template->assign(array('p_protocol'=>$p_protocol));
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>$tplpath));
		$this->template->print_('tpl');
	}

	/* 구버전 카카오 페이 :: 2015-01-20 lwh */
	public function kakaopay(){
		echo '<script type="text/javascript">function kakao_cancel_script(){'.$this->pg_cancel_script().'}</script>';
		// 모바일 일경우 플래그값 전달
		if( $this->_is_mobile_agent){
			$prType = "MPM";
		}else{
			$prType = "WPM";
		}

		// 인증 관련 TXN_ID 호출 전용 값 호출
		$pg_param = config_load('kakaopay');

		if($pg_param['mid'] || $pg_param['merchantKey'] || $pg_param['merchantEncKey'] || $pg_param['merchantHashKey']){

			$this->load->model('ordermodel');
			$data_order = $this->ordermodel->get_order($this->pg_param['order_seq']);
			## 가격 검증
			if	($data_order['pg_currency'] == 'KRW')
				$data_order['settleprice']	= floor($data_order['settleprice']);

			$pg_param['merchantTxnNumIn']= $this->pg_param['order_seq'];// 주문번호
			$pg_param['ediDate']		= date("YmdHis");				// 전문생성일시
			$pg_param['settleprice']	= $data_order['settleprice'];	// 총 금액
			$pg_param['free_mny']		= get_cutting_price($this->freeprice);		// 비과세 합계
			$pg_param['tax_mny']		= get_cutting_price($this->comm_tax_mny);		// 공급가액
			$pg_param['vat_mny']		= get_cutting_price($this->comm_vat_mny);		// 부가가치세
			$pg_param['total_tax_mny']	= $pg_param['free_mny'] + $pg_param['tax_mny'];
			$pg_param['goods_name']		= $this->pg_param['goods_name'];// 상품명

			//## 위변조 처리 - 결제요청용 키값
			$md_src = $pg_param['ediDate']
				.$pg_param['mid']
				.$pg_param['settleprice']
				.$pg_param['merchantKey'];
			$hash_String = base64_encode(hash("sha256", $md_src, false));

			//############### getTxnId START #############//
			//## 1. 라이브러리 인클루드
			require("./pg/kakaopay/conf_inc.php");
			require("./pg/kakaopay/libs/lgcns_KMpay.php");

			//## 인증,결제 및 웹 경로
			$pg_param['CNSPAY_WEB_SERVER_URL']	= $CNSPAY_WEB_SERVER_URL;
			$pg_param['targetUrl']				= $targetUrl;
			$pg_param['msgName']				= $msgName;
			$pg_param['CnsPayDealRequestUrl']	= $CnsPayDealRequestUrl;

			//## 로그 경로
			$pg_param['LogDir'] = $LogDir; //"C:/KMPay/Log";

			//## 2. TxnID 얻기
			$kmFunc = new kmpayFunc($pg_param['LogDir']);
			$kmFunc->setPhpVersion($phpVersion);

			// TXN_ID를 요청하기 위한 PARAMETERR
			$pgVal['REQUESTDEALAPPROVEURL'] = "https://".$targetUrl.$msgName; //인증 요청 경로
			$pgVal['PR_TYPE'] = $prType;	//결제 요청 타입 (함수 상단에 위치)
			$pgVal['MERCHANT_ID'] = $pg_param['mid'];		//가맹점 ID
			$pgVal['MERCHANT_TXN_NUM'] = $pg_param['merchantTxnNumIn'];	//가맹점 거래번호
			$pgVal['channelType'] = '4'; // 모바일웹결제 : 2 || TMS 방식 : 4  -> 기본 4
			$pgVal['PRODUCT_NAME'] = $pg_param['goods_name'];	//상품명 (샘플 외 몇건)
			$pgVal['AMOUNT'] = $pg_param['settleprice'];	//상품금액(총거래금액) (총거래금액 = 공급가액 + 부가세 + 봉사료)

			$pgVal['SUPPLY_AMT'] = $this->freeprice+$this->comm_tax_mny;			// 공급가액(과세+비과세)
			$pgVal['GOODS_VAT'] = (int)$this->comm_vat_mny;	// 부가세
			$pgVal['SERVICE_AMT'] = 0;			// 봉사료

			$pgVal['CURRENCY'] = 'KRW';	//거래통화(KRW/USD/JPY 등) ->html도 같이 수정요망
			$pgVal['RETURN_URL'] = "";	//결제승인결과전송URL --#확인
			$pgVal['RETURN_URL2'] = ""; // --#확인
			$pgVal['CERTIFIED_FLAG'] = "CN";// CN : 웹결제, N : 인앱결제 ->html도 같이 수정요망
			$pgVal['requestorName'] = ""; // --#확인
			$pgVal['requestorTel'] = ""; // --#확인
			//무이자옵션
			$pgVal['NOINTYN'] = ($pg_param['nonInterestTerms']=='manual') ? 'Y' : ''; // 무이자 설정 Y || N --#확인
			if($pg_param['nonInterestTerms'] == 'manual'){
				$nointopt_str	= "";
				foreach($pg_param['CardCompanyCode'] as $k => $val){
					if($k > 0)	$nointopt_str .= ",";
					$terms_str	= "";
					$terms_arr	= explode(",",$pg_param['CardCompanyTerms'][$k]);
					foreach($terms_arr as $z => $mon){
						if($z > 0)	$terms_str .= ":";
						$terms_str	.= str_pad($mon,2,"0",STR_PAD_LEFT);
					}
					$nointopt_str	.= "CC".$val."-".$terms_str;
				}
			}else{
				$nointopt_str	= "";
			}
			$pgVal['NOINTOPT'] = $nointopt_str;	// 무이자 옵션 --#확인
			$pgVal['MAX_INT'] = str_pad($pg_param['interestTerms'],2,"0",STR_PAD_LEFT);	// 최대할부개월 --#확인
			$pgVal['FIXEDINT'] = "";	// 고정할부개월 (할부개월 고정시 사용 비우면 나중 00은 일시불) --#확인
			$pgVal['POINT_USE_YN'] = "N"; // 카드사포인트사용여부 Y || N
			$pgVal['POSSICARD'] = ""; // 결제가능카드설정 (비우면 나중선택)
			$pgVal['BLOCK_CARD'] = ""; // 금지카드설정

			// ENC KEY와 HASH KEY는 가맹점에서 생성한 KEY 로 SETTING 한다.
			$pgVal['merchantEncKey'] = $pg_param['merchantEncKey'];
			$pgVal['merchantHashKey'] = $pg_param['merchantHashKey'];
			$pgVal['hashTarget'] = $pgVal['MERCHANT_ID'].$pgVal['MERCHANT_TXN_NUM'].str_pad($pgVal['AMOUNT'],7,"0",STR_PAD_LEFT);

			// payHash 생성
			$pgVal['payHash'] = strtoupper(hash("sha256", $pgVal['hashTarget'].$pgVal['merchantHashKey'], false));

			//json string 생성
			$strJsonString = new JsonString($pg_param['LogDir']);

			$strJsonString->setValue("PR_TYPE", $pgVal['PR_TYPE']);
			$strJsonString->setValue("channelType", $pgVal['channelType']);
			$strJsonString->setValue("requestorName", $pgVal['requestorName']);
			$strJsonString->setValue("requestorTel", $pgVal['requestorTel']);
			$strJsonString->setValue("MERCHANT_ID", $pgVal['MERCHANT_ID']);
			$strJsonString->setValue("MERCHANT_TXN_NUM", $pgVal['MERCHANT_TXN_NUM']);
			$strJsonString->setValue("PRODUCT_NAME", $pgVal['PRODUCT_NAME']);

			$strJsonString->setValue("AMOUNT", $pgVal['AMOUNT']);

			$strJsonString->setValue("SUPPLY_AMT", $pgVal['SUPPLY_AMT']);
			$strJsonString->setValue("GOODS_VAT", $pgVal['GOODS_VAT']);
			$strJsonString->setValue("SERVICE_AMT", $pgVal['SERVICE_AMT']);

			$strJsonString->setValue("CURRENCY", $pgVal['CURRENCY']);
			$strJsonString->setValue("CERTIFIED_FLAG", $pgVal['CERTIFIED_FLAG']);
			$strJsonString->setValue("RETURN_URL", $pgVal['RETURN_URL']);
			$strJsonString->setValue("RETURN_URL2", $pgVal['RETURN_URL2']);


			$strJsonString->setValue("NO_INT_YN", $pgVal['NOINTYN']);
			$strJsonString->setValue("NO_INT_OPT", $pgVal['NOINTOPT']);
			$strJsonString->setValue("MAX_INT", $pgVal['MAX_INT']);
			$strJsonString->setValue("FIXED_INT", $pgVal['FIXEDINT']);

			$strJsonString->setValue("POINT_USE_YN", $pgVal['POINT_USE_YN']);
			$strJsonString->setValue("POSSI_CARD", $pgVal['POSSICARD']);
			$strJsonString->setValue("BLOCK_CARD", $pgVal['BLOCK_CARD']);

			$strJsonString->setValue("PAYMENT_HASH", $pgVal['payHash']);

			// 결과값을 담는 부분
			$resultCode = "";
			$resultMsg = "";
			$txnId = "";
			$merchantTxnNum = "";
			$prDt = "";
			$strValid = "";

			// Data 검증
			$dataValidator = new KMPayDataValidator($strJsonString->getArrayValue());
			$strValid = $dataValidator->resultValid;
			if (strlen($strValid) > 0) {
				$arrVal = explode(",", $strValid);
				if (count($arrVal) == 3) {
					$resultCode = $arrVal[1];
					$resultMsg = $arrVal[2];
				} else {
					$resultCode = $strValid;
					$resultMsg = $strValid;
				}
			}

			// Data에 이상 없는 경우
			if (strlen($strValid) == 0) {
				// CBC 암호화
				$paramStr = $strJsonString->getJsonString();

				$kmFunc->writeLog("Request");
				$kmFunc->writeLog($paramStr);
				$kmFunc->writeLog($strJsonString->getArrayValue());

				$encryptStr = $kmFunc->parameterEncrypt($pgVal['merchantEncKey'], $paramStr);
				$payReqResult = $kmFunc->connMPayDLP($pgVal['REQUESTDEALAPPROVEURL'], $pgVal['MERCHANT_ID'], $encryptStr);
				$resultString = $kmFunc->parameterDecrypt($pgVal['merchantEncKey'], $payReqResult);
				$resultJSONObject = new JsonString($pg_param['LogDir']);
				if (substr($resultString, 0, 1) == "{") {
					$resultJSONObject->setJsonString($resultString);
					$resultCode = $resultJSONObject->getValue("RESULT_CODE");
					$resultMsg = $resultJSONObject->getValue("RESULT_MSG");
					if ($resultCode == "00") {
						$txnId = $resultJSONObject->getValue("TXN_ID");
						$merchantTxnNum = $resultJSONObject->getValue("MERCHANT_TXN_NUM");
						$prDt = $resultJSONObject->getValue("PR_DT");
					}
				}else{
					// 정보오류 시 경고창 띄우기
					echo '<script type="text/javascript">'.$this->pg_cancel_script().'alert("카카오페이 계약정보 오류입니다.\\n고객센터로 문의하여 주세요.");</script>';
					exit;
				}
				$kmFunc->writeLog("Result");
				$kmFunc->writeLog($resultString);
				$kmFunc->writeLog($resultJSONObject->getArrayValue());


				// 성공시....
				$txnResult['resultCode']		= $resultCode;
				$txnResult['resultMsg']			= $resultMsg;
				$txnResult['txnId']				= $txnId;
				$txnResult['merchantTxnNum']	= $merchantTxnNum;
				$txnResult['prDt']				= $prDt;
			}else{
				// 오류 시 경고창 띄우기
				echo '<script type="text/javascript">'.$this->pg_cancel_script().'alert("결제 인증 모듈오류\\n관리자에게 문의해주세요.");</script>';
				exit;
				//$res_data	= explode(",",$strValid);
				//echo "<script>alert('".$res_data[0]." : [".$res_data[1]."] \\n".$res_data[2]."');</script>";
			}

			//############### getTxnId END #############//
			$this->template->assign($pgVal);
			$this->template->assign($pg_param);
			$this->template->assign($txnResult);
			$this->template->assign(array(
				'pgVal'=>$pgVal,
				'data_order'=>$data_order,
				'hash_String'=>$hash_String
			));

			$this->template->template_dir = BASEPATH."../order";
			$this->template->compile_dir = BASEPATH."../_compile/";
			$this->template->define(array('tpl'=>'_kakaopay.html'));
			$this->template->print_('tpl');
		}else{
			// 필수값 부족일때..
			echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
			echo '<script type="text/javascript">'.$this->pg_cancel_script().'</script>';
			exit;
		}
	}

	public function pg_cancel_script(){
		return '$("#wrap",parent.document).show();$("div.pay_layer",parent.document).eq(0).show();$("div.pay_layer",parent.document).eq(1).hide();$("#layer_pay",parent.document).hide();';
	}

	public function pg_open_script(){
		echo '<script type="text/javascript">';
		echo '$("#wrap",parent.document).css("display","none");';
		echo '$("#payprocessing",parent.document).css("display","block");';
		echo '</script>';
	}

	/* 결재 취소 및 실패시 iframe 부모창 제어를 위해 */
	public function complete_replace()
	{
		if( trim($_GET['res_cd']) == "" || in_array(trim($_GET['res_cd']),array("3001","9562"))){  //사용자 취소
			echo '<script type="text/javascript" src="/app/javascript/jquery/jquery.min.js"></script>';
			echo '<script type="text/javascript">'.$this->pg_cancel_script().'</script>';
		}else{
			echo '<script type="text/javascript">';
			echo 'parent.location.href="../order/complete?no='.$_GET['no'].'";';
			echo '</script>';
		}
	}

	public function complete()
	{
		$this->load->model('ordermodel');
		$this->load->model('membermodel');
		$this->load->model('shippingmodel');

		$order_seq			= (int) $_GET['no'];
		$session_id			= session_id();
		$orders				= $this->ordermodel->get_order($order_seq);

		$is_direct_store	= false;
		$is_coupon			= false;
		$is_goods			= false;

		// 연결 session 검증
		if($orders['session_id'] && $session_id != $orders['session_id']){
			$msg = '올바른 연결이 아닙니다.';
			// 크롬 80 체크 :: 2020-02-03 lwh
			if	( !check_ssl_protocol() && strpos($_SERVER['HTTP_USER_AGENT'],'Chrome/80') !== false ){
				if( $orders['order_seq'] && ($orders['step'] == 25 || $orders['step'] == 15) ){
					$msg = '크롬 브라우저(버전 80이상) 결제로 인한 페이지 오류 발생.\n주문 상세내역을 확인하시기 바랍니다.';
					$return_url	= '/main/index';
					pageRedirect($return_url,$msg);
					exit;
				}
			}else{
				pageBack($msg);
				exit;
			}
		}

		$items					= $this->ordermodel->get_item($order_seq);
		$shipping				= $this->ordermodel->get_order_shipping($order_seq);
		$arr_shipping_method	= get_shipping_method('all');
		$tmp					= config_load('payment',$orders['payment']);
		$orders['mpayment']		= $orders['payment'];
		$orders['payment']		= $tmp[$orders['payment']];
		$order_config			= ($this->cfg_order) ? $this->cfg_order : config_load('order');
		$addShippings			= array();

		# 카드결제 정보 추출 :: 2017-05-26 lwh
		if($orders['mpayment'] == 'card'){
			$card_info = $this->ordermodel->get_pg_log($order_seq);
			$orders['card_name']	= $card_info[0]['card_name'];
			$orders['card_quota']	= (int)$card_info[0]['quota'];
			$orders['payment_cd']	= $card_info[0]['payment_cd'];
		}

		# 세금계산서 정보 추출 :: 2017-05-26 lwh
		if($orders['typereceipt'] > 0){
			$this->load->model('salesmodel');
			$sc['select']	= "typereceipt, cuse, tstep";
			$sc['whereis']	= " and order_seq = '" . $orders['order_seq'] . "'";
			$order_sales	= $this->salesmodel->get_data($sc);
			$orders['cuse'] = $order_sales['cuse'];
		}

		# 자동 주문 무효 사용시 :: 2017-05-26 lwh
		if($order_config['autocancel'] == 'y'){
			$order_config['autocancel_txt'] = getAlert("sy005", date('Y'.getAlert("sy006").' m'.getAlert("sy007").' d'.getAlert("sy008").'', strtotime(date('Y-m-d')."+".$order_config['cancelDuration']." days"))); // %s까지 (이후 입금되지 않았을 경우 자동으로 주문무효 처리)
		}

		# 비교통화 계산 함수 include
		$this->template->include_('showCompareCurrency');

		if($orders["person_seq"]){
			$sql	= "update fm_person set order_seq = '".$order_seq."' where person_seq = '".$orders["person_seq"]."'";
			$query = $this->db->query($sql);
		}

		$sale_list['basic']['title']			= getAlert("gv086"); // '기본할인';
		$sale_list['event']['title']			= getAlert("gv087"); // '이벤트';
		$sale_list['multi']['title']			= getAlert("gv088"); // '대량구매';
		$sale_list['member']['title']			= getAlert("gv089"); // '등급할인';
		$sale_list['mobile']['title']			= getAlert("gv090"); // '모바일';
		$sale_list['like']['title']				= getAlert("gv091"); // '좋아요';
		$sale_list['coupon']['title']			= getAlert("gv092"); // '쿠폰할인';
		$sale_list['code']['title']				= getAlert("gv093"); // '할인코드';
		$sale_list['referer']['title']			= getAlert("gv094"); // '유입경로';
		$sale_list['shippingcoupon']['title']	= getAlert("gv106"); // '배송비쿠폰';
		$sale_list['shippingcode']['title']		= getAlert("gv107"); // '배송비코드';
		$sale_list['enuri']['title']			= getAlert("gv108"); // '에누리';

		$sale_list['shippingcoupon']['price']	= $shipping_coupon_sale;

		// 주문서쿠폰 할인 정보 추가
		$sale_list['ordersheet']['title']	= '주문서쿠폰'; // '주문서쿠폰';

		if($items){
			foreach($items as $key=>$item){
				$result_shipping = '';
				foreach($shipping as $group_key => $data_shipping){
					if( $data_shipping['shipping_seq'] == $item['shipping_seq'] ){
						$data_shipping['shipping_method_name'] = $data_shipping['shipping_set_name'];

						// 배송정보 추출 :: 2016-11-01 lwh
						if($data_shipping['shipping_method'] == 'coupon'){
							$data_shipping['shipping_method_name'] = '쿠폰';
							$data_shipping['calcul_type'] = 'bundle';
						}else{
							$ship_info = $this->shippingmodel->get_ship_info($data_shipping['shipping_group'],$data_shipping['shipping_method']);
							if($ship_info['cart_opt_seq']){
								$data_shipping['shipping_method'] .= '_each';
								$data_shipping['calcul_type'] = 'each';
							}else{
								$data_shipping['calcul_type'] = 'bundle';
							}
						}

						if($data_shipping['add_delivery_cost'] <= 0)	$data_shipping['add_delivery_cost'] = null;

						$result_shipping = $data_shipping;
					}
				}

				$item['data_shipping']		= $result_shipping;

				// 배송비 합산 :: 2016-11-01 lwh
				if	(!in_array($result_shipping['shipping_seq'], $addShippings)){

					// 구스킨용 배송비 계산 :: 2016-11-01 lwh
					if	($item['shipping_policy'] == 'goods'){
						$orders['goods_delivery']		+= get_cutting_price( $item['goods_shipping_cost']);
						$orders['tot_shipping_cost']	+= get_cutting_price($item['goods_shipping_cost'])
														+ get_cutting_price($result_shipping['add_delivery_cost']);
					}else{
						$orders['basic_delivery']		+= get_cutting_price($result_shipping['delivery_cost']);
						$orders['tot_shipping_cost']	+= get_cutting_price($result_shipping['shipping_cost']);
					}
					$item['add_delivery_cost']			= get_cutting_price($result_shipping['add_delivery_cost']);
					if	(preg_match('/delivery/', $result_shipping['shipping_method']))
						$orders['add_delivery_cost']	+= get_cutting_price($result_shipping['add_delivery_cost']);


					// 신스킨용 배송비 계산 :: 2016-08-10 lwh
					if(!preg_match('/direct_store/', $result_shipping['shipping_method'])){
						if($result_shipping['shipping_type'] != 'postpaid'){
							$orders['std_cost'] += $result_shipping['delivery_cost'];
						}
						$orders['add_cost'] += $result_shipping['add_delivery_cost'];
						$orders['hop_cost'] += $result_shipping['hop_delivery_cost'];
					}

					$addShippings[]	= $result_shipping['shipping_seq'];
				}

				$item['tot_ea'] = 0;
				$item['tot_price'] = 0;

				$rowspan			= 0;
				$reOptions			= array();
				$item['tot_ea']		= 0;
				$item['tot_price']	= 0;
				$options 			= $this->ordermodel->get_option_for_item($item['item_seq']);
				if($options) foreach($options as $data){
					$item['tot_ea'] 			+= $data['ea'];
					$item['tot_price'] 			+= $data['ea'] * $data['price'];
					$data['tot_price']			= $data['ea'] * $data['price'];
					$data['tot_ori_price']		= $data['ea'] * $data['ori_price'];
					$data['tot_reserve']		= $data['ea'] * $data['reserve'];
					$data['tot_point']			= $data['ea'] * $data['point'];
					$data['tot_member_sale']	= $data['ea'] * $data['member_sale'];
					$data['tot_event_sale']		= $data['event_sale'];
					$data['tot_multi_sale']		= $data['multi_sale'];
					$data['tot_sale_price']		= $data['tot_price'] - $data['tot_event_sale'] - $data['tot_multi_sale'] - $data['tot_member_sale'] - $data['coupon_sale'] - $data['promotion_code_sale'] - $data['fblike_sale'] - $data['mobile_sale'] - $data['referer_sale'] - $data['unit_ordersheet'];
					$tot_goods_price += $data['tot_sale_price'];

					// 주문 상품명 정의 :: 2017-08-01 lwh
					$stmp['goods_name'] = $item['goods_name'];
					$goods_option = $this->goodsmodel->optionToStr($data);
					if($goods_option) $stmp['goods_name'] .= ' - ' . $goods_option;

					// 개별 배송메시지 별도 정의 :: 2017-05-24 lwh
					if($data['ship_message']){
						$stmp['ship_message'] = $data['ship_message'];
						$each_memo[] = $stmp;
					}else{
						$orders['each_goods'][] = $stmp['goods_name'];
					}

					if($data['ship_message'])	$ship_message[] = $data['ship_message'];

					// 새로 추가 @2014-11-06
					$total_reserve					+= $data['tot_reserve'];
					$total_point					+= $data['tot_point'];
					$total_sale_price				+= $data['tot_member_sale'] + $data['coupon_sale'] + $data['promotion_code_sale'] + $data['fblike_sale'] + $data['mobile_sale'] + $data['referer_sale'] + $data['unit_ordersheet'];
					$data['org_price']				= ($data['consumer_price']) ? $data['consumer_price'] : $data['org_price'];
					$data['tot_org_price']			= $data['org_price'] * $data['ea'];
					$data['tot_basic_sale']			= $data['ea'] * $data['basic_sale'];
					$data['tot_event_sale']			= $data['event_sale'];
					$data['tot_multi_sale']			= $data['multi_sale'];
					// 정가 기준 시 기본할인들 추가
					$total_sale_price				+= $data['tot_event_sale'] + $data['tot_multi_sale'];
					//$sale_list['basic']['price']	+= $data['tot_basic_sale'];
					$sale_list['event']['price']	+= $data['tot_event_sale'];
					$sale_list['multi']['price']	+= $data['tot_multi_sale'];
					$sale_list['member']['price']	+= $data['tot_member_sale'];
					$sale_list['mobile']['price']	+= $data['mobile_sale'];
					$sale_list['like']['price']		+= $data['fblike_sale'];
					$sale_list['coupon']['price']	+= $data['coupon_sale'];
					$sale_list['code']['price']		+= $data['promotion_code_sale'];
					$sale_list['referer']['price']	+= $data['referer_sale'];
					// 주문서쿠폰 할인 정보 추가
					$sale_list['ordersheet']['price'] += $data['unit_ordersheet'];

					$inputs	= $this->ordermodel->get_input_for_option($item['item_seq'], $data['item_option_seq']);
					$suboptions = $this->ordermodel->get_suboption_for_option($item['item_seq'], $data['item_option_seq']);
					foreach($suboptions as $k => $data_suboption){
						$item['tot_ea'] 			+= $data_suboption['ea'];
						$item['tot_price'] 			+= $data_suboption['ea'] * $data_suboption['price'];

						$data_suboption['tot_price'] = $data_suboption['ea'] * $data_suboption['price'];
						$data_suboption['tot_member_sale'] = $data_suboption['ea'] * $data_suboption['member_sale'];
						$data_suboption['tot_sale_price'] = $data_suboption['tot_price'] - $data_suboption['tot_member_sale'];
						$data_suboption['tot_reserve'] = $data_suboption['ea'] * $data_suboption['reserve'];
						$data_suboption['tot_point'] = $data_suboption['ea'] * $data_suboption['point'];

						// 새로 추가 @2014-11-06
						$total_reserve						+= $data_suboption['tot_reserve'];
						$total_point						+= $data_suboption['tot_point'];
						$total_sale_price					+= $data_suboption['tot_member_sale'];
						$data_suboption['org_price']		= ($data_suboption['consumer_price']) ? $data_suboption['consumer_price'] : $data_suboption['org_price'];
						$data_suboption['tot_org_price']	= $data_suboption['org_price'] * $data_suboption['ea'];
						$data_suboption['tot_basic_sale']	= $data_suboption['ea'] * $data_suboption['basic_sale'];
						$data_suboption['tot_event_sale']	= $data_suboption['event_sale'];
						$data_suboption['tot_multi_sale']	= $data_suboption['multi_sale'];
						// 정가 기준 시 기본할인들 추가
						$total_sale_price				+= $data_suboption['tot_event_sale'] + $data_suboption['tot_multi_sale'];
						//$sale_list['basic']['price']	+= $data_suboption['tot_basic_sale'];
						$sale_list['event']['price']	+= $data_suboption['tot_event_sale'];
						$sale_list['multi']['price']	+= $data_suboption['tot_multi_sale'];
						$sale_list['member']['price']	+= $data_suboption['tot_member_sale'];
						$sale_list['mobile']['price']	+= $data_suboption['mobile_sale'];
						$sale_list['like']['price']		+= $data_suboption['fblike_sale'];
						$sale_list['coupon']['price']	+= $data_suboption['coupon_sale'];
						$sale_list['code']['price']		+= $data_suboption['promotion_code_sale'];
						$sale_list['referer']['price']	+= $data_suboption['referer_sale'];

						$tot_goods_price += $data_suboption['tot_sale_price'];
						$suboptions[$k] = $data_suboption;
					}

					$rowspan				+= count($suboptions);
					$data['inputs']			= $inputs;
					$data['suboptions']		= $suboptions;
					$reOptions[]			= $data;

					$item['tot_goods_cnt']		+= count($suboptions) + 1;
				}
				$tot_price					+= $item['tot_price'];
				$tot_ea						+= $item['tot_ea'];

				$rowspan					+= count($reOptions);
				$item['rowspan']			= $rowspan;
				$item['options'] 			= $reOptions;
				$items[$key] 				= $item;
			}
		}

		/* 주문상품을 배송그룹별로 분할 */
		$shipping_group_items	= array();
		$tmp_shipping_seq		= array();
		foreach($items as $item){

			if	($item['data_shipping']['shipping_method'] == 'coupon')
				$is_coupon			= true;
			else if	($item['data_shipping']['shipping_method'] == 'direct_store')
				$is_direct_store	= true;
			else
				$is_goods			= true;

			$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost_sum']+= $item['goods_shipping_cost'];
			$shipping_group_items[$item['shipping_seq']]['shipping'] = $item['data_shipping'];
			$shipping_group_items[$item['shipping_seq']]['goods_shipping_cost']+= $item['goods_shipping_cost'];
			$shipping_group_items[$item['shipping_seq']]['rowspan'] += $item['rowspan'];
			$shipping_group_items[$item['shipping_seq']]['items'][] = $item;
			$shipping_group_items[$item['shipping_seq']]['items'][0]['options'][0]['shipping_division']	= 1;
			$shipping_group_items[$item['shipping_seq']]['totalitems'] += count($item['options'])+count($item['suboptions']);

			if	(!in_array($item['shipping_seq'], $tmp_shipping_seq)){
				$tmp_shipping_seq[]		= $item['shipping_seq'];
				$shipping_coupon_sale	+= $item['data_shipping']['shipping_coupon_sale'];
				$shipping_code_sale		+= $item['data_shipping']['shipping_promotion_code_sale'];
			}

			$goods_delivery_cost	+= $item['goods_shipping_cost'];
		}
		$this->template->assign(array('shipping_group_items'=> $shipping_group_items));

		// 에누리 추가 :: 2015-08-27 lwh
		$sale_list['enuri']['price']	= $orders['enuri'];
		$total_sale_price				= $total_sale_price + $orders['enuri'];

		// 새로 추가 @2014-11-06
		$orders['add_delivery']					= get_cutting_price($orders['add_delivery_cost']);
		$orders['tot_org_shipping_cost']		= $orders['basic_delivery'] + $orders['goods_delivery'] + $orders['add_delivery'];
		$orders['tot_origin_shipping_cost']		= $orders['std_cost'] + $orders['add_cost'] + $orders['hop_cost']; // 신스킨용 배송비계산 :: 2016-08-10 lwh
		$orders['shipping_coupon_sale']			= $shipping_coupon_sale;
		$orders['shipping_code_sale']			= $shipping_code_sale;
		$sale_list['shippingcoupon']['price']	= $shipping_coupon_sale;
		$sale_list['shippingcode']['price']		= $shipping_code_sale;
		$orders['sale_list']					= $sale_list;
		$orders['total_sale_price']				= $total_sale_price + $orders['coupon_sale'] + $orders['shipping_promotion_code_sale'] + $shipping_coupon_sale + $shipping_code_sale;
		$orders['total_reserve']				= $total_reserve;
		$orders['total_point']					= $total_point;
		$orders['tot_price']					= $tot_price;
		$orders['tot_ea']						= $tot_ea;
		$orders['tot_goods_price']				= $tot_goods_price;
		$orders['mshipping']					= $this->ordermodel->get_delivery_method($orders);

		$orders['settleprice_compare']			= showCompareCurrency('',$orders['settleprice'],'return',array("layClass"=>"gray"));	# 총 결제금액 비교통화 노출

		## 결제실패로그 상세뿌리기
		$logs = $this->ordermodel->get_log($order_seq,'pay');
		$pg_logs = $this->ordermodel->get_pg_log($order_seq,'pay');
		if( (preg_match('/virtual/',$orders['mpayment']) || $orders['mpayment']=="card") && $pg_logs[0]['res_msg']){
			$logs[0]['title'] .= "(".$pg_logs[0]['res_msg'].")";
		}
		if(!$logs[0]['title']) $logs[0]['title'] = "결제실패";

		/*virtual account*/
		switch($orders['mpayment']){
			case "card":
				if($pg_logs[0]['card_name']) $orders['payment'] .= "(".$pg_logs[0]['card_name'].")";
			break;
			case "account":
				if($pg_logs[0]['bank_name']) $orders['payment'] .= "(".$pg_logs[0]['bank_name'].")";
			break;
		}

		// 간편결제 표시 추가 :: 2015-02-26 lwh
		if		($orders['pg'] == 'kakaopay'){
			if($orders['payment_cd'] == 'MONEY'){
				$orders['payment'] = '카카오페이 - 카카오머니';
			}else{
				$orders['payment'] = '카카오페이 - ' . $orders['payment'];
			}
		}else if($orders['pg'] == "payco"){
			if($orders['mpayment'] == 'payco_coupon')			$payment_payco = '쿠폰결제';
			else if($orders['mpayment'] == 'escrow_account')	$payment_payco = '계좌이체';
			else												$payment_payco = $orders['payment'];

			$orders['receipt_view'] = true;
			$orders['payment'] = '페이코 - ' . $payment_payco;
		}else if($orders['pg'] == "paypal"){
			$orders['payment'] = 'Paypal - ' . $orders['payment'];
		}

		if($this->userInfo['member_seq']){
			$member_seq = $this->userInfo['member_seq'];
			$sql = "select address_seq from fm_delivery_address where member_seq=".$member_seq." order by address_seq desc";
			$query = $this->db->query($sql);
			$delivery_address = $query -> row_array();
			$address_seq = $delivery_address['address_seq'];
		}else{//비회원 주문보기 또는 거래명세서 확인가능하도록
			$_SESSION['sess_order'] = $order_seq;
			$this->session->set_userdata(array('sess_order'=>$order_seq));
		}

		//2016.03.31 거래명세서 버튼 추가 pjw
		if($this->config_basic['usetradeinfo'] == 'Y' && $orders['step'] != 85 && $orders['step'] != 95){
			$btn_script = "window.open('/prints/form_print_trade?no=".$orders['order_seq']."', '_trade', 'width=960,height=640,scrollbars=yes');";
			$btn_style  = '/*font-size:11px;display: inline-block; background: #000; color: #fff; line-height: 20px; padding: 0px 7px; cursor: pointer;*/';
			$btn_tag	= '<span class="btn_trade" style="'.$btn_style.'" onclick="'.$btn_script.'">거래명세서</span>';
			$this->template->assign(array('btn_tradeinfo'=>$btn_tag));
		}

		// 개별 메세지 처리 :: 2016-09-02 lwh
		if($orders['each_msg_yn'] == "Y"){
			$orders['each_memo']	= $each_memo;
			$orders['memo']			= $ship_message;
		}

		$pg = config_load($this->config_system['pgCompany']);
		// 카드매출 관련 처리 :: 2017-05-24 lwh
		if($orders['mpayment'] == 'card'){
			$this->pg = config_load($this->config_system['pgCompany']);
			if( $this->config_system['pgCompany'] == 'lg' && $orders['pg_transaction_number']) {
				$orders['authdata'] = md5($this->pg['mallCode'] . $orders['pg_transaction_number'] . $this->pg['merchantKey']);
			}else{
				$orders['authdata'] = '';
			}
			if	( ($pg['mallCode'] && $pg['merchantKey']) || ($pg['mallId'] && $pg['mallPass']) )
			$pg['pgSet']	= 'ok';
			$this->template->assign('pg',$pg);
		}

		// [판매지수 EP] 쿠키로 ep 등록 처리된 주문건인지 확인 후 EP 수집 :: 2018-09-18 pjw
		if(!$this->statsmodel) $this->load->model('statsmodel');
		$this->statsmodel->set_order_sale_ep($order_seq);

		//현재통화 kmj
		$this->load->model('adminenvmodel');
		$query = $this->adminenvmodel->get(array('use_yn'=>'y'));
		$res = $query->result_array();
		$currency = $res[0]['currency'];
		$this->template->assign(array('currency'=>$currency));

		// ifdo 연동
		$this->load->library('ifdolibrary');
		$ifdo_tags		= $this->ifdolibrary->purchase($shipping_group_items, $orders);

        // 채널톡 연동
        $this->load->library('channeltalklibrary');
		$channeltalk		= $this->channeltalklibrary->purchase($shipping_group_items, $orders);

        // GA4 연동
        if ($this->ga4_auth_commerce) {
            $this->load->library('ga4library');
            $ga4_purchase = $this->ga4library->purchase($shipping_group_items, $orders);
        }

		$this->template->assign(array('members'=>$members));
		$this->template->assign(array('is_coupon'=>$is_coupon));
		$this->template->assign(array('is_goods'=>$is_goods));
		$this->template->assign(array('is_direct_store'=>$is_direct_store));
		$this->template->assign(array('order_config'=>$order_config));
		$this->template->assign(array('address_seq'=>$address_seq));
		$this->template->assign(array('orders'=>$orders));
		$this->template->assign(array('items'=>$items));
		$this->template->assign(array('logs'=>$logs));
		$this->print_layout($this->template_path());

		// 네이버 지식쇼핑 CPA 스크립트
		if($this->config_basic['naver_wcs_use']=='y'){
			foreach($items as $item){
				$r_naver_cpa[]= '{"oid":'.$orders['order_seq'].', "poid":'.$item['item_seq'].', "pid":'.$item['goods_seq'].', "parpid":'.$item['goods_seq'].', "name":"'.$item['goods_name'].'", "cnt":'.$item['tot_ea'].', "price":'.$item['tot_price'].'}';
			}
			echo "
			<script type=\"text/javascript\">
			var _nao={};
			_nao[\"chn\"] = \"AD\";
			 _nao[\"order\"]=[".implode(',',$r_naver_cpa)."];
			wcs.CPAOrder(_nao);
			</script>";
		}

		//GA통계
		if($this->ga_auth_commerce && !$this->ga_auth_commerce_plus){
			//일반 전자상거래 스크립트
			getTransactionJs($orders,$items);
		}else if($this->ga_auth_commerce_plus){
			if($orders['step']==15 || $orders['step']==25){
				//향상된 전자상거래 스크립트
				$params['orders'] = $orders;
				$params['item'] = $items;
				$params['page'] = uri_string();
				echo google_analytics($params,"order_complete");
			}
		}

		// 주문메일 sms발송
		echo "<script type='text/javascript'>$.get('mail_sms?order_seq=".$order_seq."', function(data) {});</script>";

		// 고객리마인드 로그
		echo "<script type='text/javascript'>$.get('log_curation?order_seq=".$order_seq."', function(data) {});</script>";

		// 통계데이터(order) 전송
		/* 사용안함
		foreach($items as $item){
			$arr_goods_seq[] = $item['goods_seq'];
		}
		$str_goods_seq = implode('|',$arr_goods_seq);
		echo "<script type='text/javascript'>statistics_firstmall('order','".$str_goods_seq."','".$order_seq."','');</script>";
		*/

		if ($this->config_system['facebook_pixel_use'] == 'Y') {
		    //현재통화
		    $this->load->model('adminenvmodel');
		    $query       = $this->adminenvmodel->get(array('use_yn'=>'y'));
		    $res         = $query->result_array();
		    $currency    = $res[0]['currency'];

		    $fbq = "";
		    $fbq .= "<script>";

		    foreach ($items as $k => $v) {
		        $fbq .= "fbq('track', 'Purchase', {";
		        $fbq .= "    content_name: '".$v['goods_name']."',";
		        $fbq .= "    contents: [{id: '".$v['goods_seq']."', quantity: ".$v['tot_ea'].", item_price: ".$v['options'][0]['tot_sale_price']."}],";
		        $fbq .= "    content_type: 'product',";
		        $fbq .= "    value: ".$v['tot_price'].",";
		        $fbq .= "    currency: '".$currency."'";
		        $fbq .= "});";
		    }

		    $fbq .= "</script>";

		    echo $fbq;
		}

		// gtag 연동
		$this->load->library('googleGtag');
		$sEventTags		= $this->googlegtag->eventTagPurchase($orders['settleprice'], $this->config_system['basic_currency'], $orders['order_seq']);
		if($sEventTags)		echo $sEventTags;
	}

	function log_curation(){
		$this->load->helper('reservation');
		$order_seq = (int) $_GET['order_seq'];
		$curation  = array("action_kind"=>"order","order_seq"=>$order_seq);
		curation_log($curation);
	}

	function mail_sms()
	{
		$this->send_for_provider = array();
		$this->load->model('ordermodel');
		$order_seq = (int) $_GET['order_seq'];
		$orders = $this->ordermodel->get_order($order_seq);
		$items	= $this->ordermodel->get_item($order_seq);
		$params['goods_name']	= $items[0]['goods_name'];
		if	(count($items) > 1)
			$params['goods_name']	.= '외 '.(count($items) - 1).'건';

		$complete_id = $this->session->userdata('complete');
		$sess_user	 = $this->session->userdata("user");

		$tot['coupontotal'] = 0;//티켓상품
		$tot['goodstotal'] = 0;//실물상품
		if($complete_id != $order_seq){

			$providerArr = array();

			foreach($items as $key=>$item){
				if ( $item['goods_kind'] == 'coupon' ) {
					$tot['coupontotal']++;
				}else{
					$tot['goodstotal']++;
				}

				if( $item['provider_seq'] ) $providerArr[] = $item['provider_seq'];
			}

			if($orders['step'] == 15 && $orders['sms_15_YN'] != 'Y') {
				// 주문접수 sms발송
				if( $orders['order_cellphone'] ){
					$params['shopName']		= $this->config_basic['shopName'];
					$params['ordno']		= $order_seq;
					if($sess_user['userid']) $params['userid'] = $sess_user['userid'];
					$params['order_user']	 = $orders['order_user_name'];
					$params['recipient_user']	 = $orders['recipient_user_name'];
					$params['bank_account']		= ($orders['payment'] == 'bank')? $orders['bank_account'] : $orders['virtual_account'];


					$commonSmsData = array();
					$commonSmsData['order']['phone'][] = $orders['order_cellphone'];
					$commonSmsData['order']['params'][] = $params;
					$commonSmsData['order']['order_seq'][] = $order_seq;
					commonSendSMS($commonSmsData);


					$this->db->where('order_seq', $orders['order_seq']);
					$this->db->update('fm_order', array('sms_15_YN'=>'Y'));
				}

				// 주문접수메일발송
				send_mail_step15($order_seq);

				// 관리자 푸시 알림발송 2018-01-02 jhr
				push_for_admin(array(
					'kind'			=> 'order_view',
					'unique'		=> $order_seq,
					'ord_item'		=> $params['goods_name'],
					'user_name'		=> $orders['order_user_name']
				));
			}

			//티켓상품 결제확인시 출고처리구문 추가
			if( ($orders['step'] == 25 || ( ( $orders['step'] == 50 || $orders['step'] == 55) && $tot['coupontotal']>0)  ) && $orders['sms_25_YN'] != 'Y') {
				$this->load->library('orderlibrary');
				$this->orderlibrary->send_step25_mail_sms($orders);
			}
			$this->session->set_userdata('complete',$order_seq);
		}
	}

	//장바구니 프로모션코드입력
	public function promotion()
	{
		$mode = !empty($_GET['mode']) ? $_GET['mode'] : 'normal';
		$cart_promotioncode = $this->session->userdata('cart_promotioncode_'.session_id());
		$this->template->assign('cart_promotioncode' , $cart_promotioncode);
		if($mode=='layer'){
			$this->template->define(array('tpl'=>$this->skin.'/order/_promotion.html'));
			$this->template->print_('tpl');
		}else{
			$this->print_layout($this->template_path());
		}
	}


	/**
	* 결제페이지 결제하기/주문하기 아이콘
	**/
	public function settle_order_images()
	{
		// 상품상태별 아이콘
		$tmp = code_load('goodsStatusImage');
		$goodsStatusImage = array();
		foreach($tmp as $row){
			$goodsStatusImage[$row['codecd']] = $row['value'];
		}
		$return = '';
		if( $goodsStatusImage['btn_order_pay1'] ){//결제하기 아이콘
			$btn_order_pay1 = '/data/icon/goods_status/'.$goodsStatusImage['btn_order_pay1'];
		}

		if( $goodsStatusImage['btn_order_pay2'] ){//무통장입금 주문하기 아이콘
			$btn_order_pay2 = '/data/icon/goods_status/'.$goodsStatusImage['btn_order_pay2'];
		}

		$result = array('btn_order_pay1'=>$btn_order_pay1, 'btn_order_pay2'=>$btn_order_pay2);
		echo json_encode($result);
		exit;
	}

	public function ajax_get_delivery_address(){

		$member_seq	= (int) $_GET['member_seq'];
		if	(!$member_seq)	$member_seq	= $this->userInfo['member_seq'];
		if($member_seq){
			$this->load->model('membermodel');
			$type = $_GET['type'];

			//주문시 배송지 노출 순서: 기본배송지 > 회원주소 > 최근주소
			switch($type){
				case "often":
					$result = $this->membermodel->get_delivery_address($member_seq,'often');
					if($result) {
						$result[0]['recipient_new_zipcode'] = str_replace("-", "", $result[0]['recipient_zipcode']);
					}
					$return = $result[0];
					if(!$result){ // 없으면 회원 기본 주소
						$result = $this->membermodel->get_member_data($member_seq);
						if(!$result['zipcode']){
							$return = false;
						}else{
							$result['recipient_zipcode']	= $result['zipcode'];
							$phone = $this->chkPhoneDash($result['phone']);
							$cellphone = $this->chkPhoneDash($result['cellphone']);
							$return = array(
								'recipient_zipcode' => $result['zipcode'],
								'recipient_new_zipcode' => str_replace("-", "", $result['zipcode']),
								'recipient_address_type' => $result['address_type'],
								'recipient_address' => $result['address'],
								'recipient_address_street' => $result['address_street'],
								'recipient_address_detail' => $result['address_detail'],
								'recipient_user_name' => $result['user_name'],
								'recipient_phone' => $phone ,
								'recipient_cellphone' => $cellphone,
								'recipient_email' => $result['email'],
							);
						}
					}
					if(!$result){ // 없으면 최근 주소
						$result = $this->membermodel->get_delivery_address($member_seq,'lately');

						if($result){
							$result[0]['recipient_new_zipcode'] = str_replace("-", "", $result[0]['recipient_zipcode']);
							if(strlen($result[0]['recipient_new_zipcode']) < 7){
								$result[0]['recipient_zipcode'] = substr($result[0]['recipient_new_zipcode'],0,3)."-".substr($result[0]['recipient_new_zipcode'],3,3);
							}

							$return = $result[0];
						}
					}
				break;
				case "lately":
					$idx = is_numeric($_GET['idx']) ? (int)$_GET['idx'] : 0;
					$result = $this->membermodel->get_delivery_address($member_seq,'lately',$idx);

					$result[0]['recipient_new_zipcode'] = str_replace("-", "", $result[0]['recipient_zipcode']);
					if(strlen($result[0]['recipient_new_zipcode']) < 7){
						$result[0]['recipient_zipcode'] = substr($result[0]['recipient_new_zipcode'],0,3)."-".substr($result[0]['recipient_new_zipcode'],3,3);
					}

					$return = $result[0];
				break;
				case "member":
					$result = $this->membermodel->get_member_data($member_seq);
					$phone = $this->chkPhoneDash($result['phone']);
					$cellphone = $this->chkPhoneDash($result['cellphone']);
					$return = array(
						'recipient_zipcode' => $result['zipcode'],
						'recipient_new_zipcode' => str_replace("-", "", $result['zipcode']),
						'recipient_address_type' => $result['address_type'],
						'recipient_address' => $result['address'],
						'recipient_address_street' => $result['address_street'],
						'recipient_address_detail' => $result['address_detail'],
						'recipient_user_name' => $result['user_name'],
						'recipient_phone' => $phone ,
						'recipient_cellphone' => $cellphone,
						'recipient_email' => $result['email'],
					);
				break;
			}

			// 국가정보가 없으면 기본은 KOREA
			if(!$return['nation'] && $return)	$return['nation'] = 'KOREA';

			if($return) echo json_encode($return);

		}
	}

	public function chkPhoneDash($phone) {
		if(strpos($phone,'-')===FALSE) { // add dash
			return preg_replace("/(^02.{0}|^01.{1}|[0-9]{3})([0-9]+)([0-9]{4})/", "$1-$2-$3", $phone);
		}
		else {
			return $phone;
		}
	}

	// 굿스플로 송장번호 업데이트 :: 2015-07-06 lwh
	public function gf_export_get(){
		$flag	= false;

		//데이터를 받지 못함.
		$msg	= getAlert('os149');
		$this->load->model('goodsflowmodel');
		$this->load->model('usedmodel');

		$data	= file_get_contents('php://input');
		$result	= json_decode($data);
		if($result){
			$gf_log_param['gf_seq']			= '';
			$gf_log_param['send_param']		= 'gf_export_get';
			$gf_log_param['send_xml']		= serialize($_SERVER);
			$gf_log_param['respon_param']	= serialize($result);
			$gf_log_param['respon_xml']		= null;
			$this->goodsflowmodel->set_goodsflow_log($gf_log_param);

			unset($arr_sheetNo);
			$this->db->trans_begin();
			foreach($result->data->items as $k => $order){
				if(!$arr_sheetNo[$order->sheetNo]){
					$arr_sheetNo[$order->sheetNo] = $order->transUniqueCd;

					$return = $this->usedmodel->update_goods_export($order->transUniqueCd,$order->sheetNo,$order->deliverCode);
					if($return){
						$flag	= true;
						$msg	= '';
					}else{
						$flag	= false;
						$msg	= '서버에서 요청을 처리할 수 없음.';
						break;
					}
				}
			}
			if($flag)	$this->db->trans_commit();
			else		$this->db->trans_rollback();
		}

		$res_data['success'] = $flag;
		$res_data['message'] = $msg;

		$echo_res = json_encode($res_data);

		echo $echo_res;
	}

	// 중계서버에서 추출 우체국택배 상품정보 추출 :: 2016-04-06 lwh
	public function epost_export_get(){
		$flag	= false;
		//데이터를 받지 못함.
		$msg	= getAlert('os149');
		$this->load->model('epostmodel');

		$export_code	= $_POST['export_code'];
		$delivery_code	= $_POST['delivery_code'];

		// 출고번호만 있어도 송장정보 가져가도록 개선 2019-07-11 hyem
		if(!empty($export_code)){
			$return = $this->epostmodel->get_send_xml($export_code,$delivery_code);
			echo $return;
		}
	}
	// 우체국택배 배송정보 업데이트 :: 2016-04-07 lwh
	public function epost_delivery_set(){
		$this->load->model('epostmodel');
		$param	= $_POST;
		$res	= $this->epostmodel->get_respon_tracking($param);
		echo $res;
	}

	public function fblike_opengraph_firstmallplus()
	{
		$this->snssocial->facebooklogin();
		$referer = parse_url($_SERVER['HTTP_REFERER']);
		if(strstr($referer['host'], $this->config_system['domain']) ) {
			$this->fblike_opengraph('firstmallplus');
			$this->fbopengraph('firstmallplus');
			if( $_GET['files']=='settle') {
				if($this->session->userdata('fbuser') ) {
					echo '$("#fbloginlay").hide();';//앱동의창 숨김
				}else{
					echo '$("#fbloginlay").show();';//앱동의창 숨김//
				}
			}
		}
	}

	public function fblike_opengraph($f=null)
	{
		$this->load->helper('cookie');
		$fbuserprofile = $this->snssocial->facebookuserid();
		if ( !$fbuserprofile ) {
			$this->facebook = new Facebook(array(
			  'appId'  => $this->__APP_ID__,
			  'secret' => $this->__APP_SECRET__,
			  "cookie" => true
			));
			// Get User ID
			$fbuserprofile = $this->facebook->getUser();
			if($fbuserprofile && !$this->session->userdata('fbuser')){
				$this->session->set_userdata('fbuser', $fbuserprofile);
			}else{
				$fbuserprofile = $this->snssocial->facebooklogin();
				if( $fbuserprofile && !$this->session->userdata('fbuser') ) {
					$this->session->set_userdata('fbuser', $fbuserprofile);
				}
			}
		}else{
			if( !$this->session->userdata('fbuser') ) {
				$this->session->set_userdata('fbuser', $fbuserprofile);
			}
		}

		/**
		* facebook like 체크
		**/
		$referer = parse_url($_SERVER['HTTP_REFERER']);
		if( strstr($referer['query'], 'mode=direct') ) $_GET['mode'] = "direct";

		$this->load->model('cartmodel');
		$this->load->model('goodsfblike');

		$ss_fblike_name = 'goods_fblike';
		$goodsfblikess = $this->session->userdata($ss_fblike_name);
		$session_id = session_id();


		if( $this->session->userdata('fbuser') ) {
			$sns_id = $this->session->userdata('fbuser');
		}elseif(get_cookie('fbuser')){
			$sns_id = get_cookie('fbuser');
		}

		if($this->userInfo['member_seq']){
			$this->load->model('membermodel');
			$this->mdata = $this->membermodel->get_member_data($this->userInfo['member_seq']);//회원정보
			$sns_id = $this->mdata['sns_f'];
			if($sns_id){
				$addwhereis = " and (session_id='".$session_id."' or sns_id = '".$sns_id."'  or member_seq = '".$this->userInfo['member_seq']."' ) ";
			}else{
				$addwhereis = " and (session_id='".$session_id."' or member_seq = '".$this->userInfo['member_seq']."' ) ";
			}
		}else{
			if($sns_id){
				$addwhereis = " and (session_id='".$session_id."' or sns_id = '".$sns_id."' ) ";
			}else{
				$addwhereis = " and (session_id='".$session_id."' ) ";
			}
		}

		$cfg['order'] = ($this->cfg_order) ? $this->cfg_order : config_load('order');

		$cart = $this->cartmodel->cart_list();
		foreach($cart['list'] as $data){

			if($this->systemfblike['result']) {//할인설정이된경우
				$cart_seq = $data['cart_seq'];
				$fblike = 'N';
				$goodslikeurl = $this->likeurl.'&no='.$data['goods_seq'];

				$sc['select']  = " like_seq ";
				$whereis = " and goods_seq='".$data['goods_seq']."' ".$addwhereis;
				$sc['whereis'] = $whereis;
				$ckfblike = $this->goodsfblike->get_data($sc);//like 한경우 DB 화처리
				if($cfg['order']['fblike_ordertype'] == 1 ){//회원만 할인제공
					if($this->userInfo['member_seq']){
						if ( (strstr($goodsfblikess,'['.$goodslikeurl.']') && $goodsfblikess) || $ckfblike ) {//좋아요세션이 있거나 로그에 남은경우
							$fblike = 'Y';
							if($ckfblike) {
								$insdata = array(
								'like_seq' => $ckfblike['like_seq'],
								'goods_seq' => $data['goods_seq'],
								'member_seq' => $this->userInfo['member_seq'],
								'sns_id' => $sns_id,
								'session_id' => $session_id,
								'date' => date('Y-m-d H:i:s'),
								'ip' => $this->input->ip_address(),
								'agent' => $_SERVER['HTTP_USER_AGENT']
								);
								$this->goodsfblike->fblike_modify($insdata);
							}
						}else{
							if($this->session->userdata('fbuser')) {//페이스북 로그인한 경우 실시간체크함
								if ( $this->snssocial->facebook_goodsLike($goodslikeurl) ) {

									$fblike = 'Y';

									$insdata = array(
									'goods_seq' => $data['goods_seq'],
									'member_seq' => $this->userInfo['member_seq'],
									'sns_id' => $sns_id,
									'session_id' => $session_id,
									'date' => date('Y-m-d H:i:s'),
									'ip' => $this->input->ip_address(),
									'agent' => $_SERVER['HTTP_USER_AGENT']
									);
									$this->goodsfblike->fblike_write($insdata);

								}
							}
						}
					}
				}else{//회원/비회원 모두 할인제공
					if ( (strstr($goodsfblikess,'['.$goodslikeurl.']') && $goodsfblikess) || $ckfblike ) {//좋아요세션이 있거나 로그에 남은경우
						$fblike = 'Y';
						if($ckfblike) {
							$insdata = array(
							'like_seq' => $ckfblike['like_seq'],
							'goods_seq' => $data['goods_seq'],
							'member_seq' => $this->userInfo['member_seq'],
							'sns_id' => $sns_id,
							'session_id' => $session_id,
							'date' => date('Y-m-d H:i:s'),
							'ip' => $this->input->ip_address(),
							'agent' => $_SERVER['HTTP_USER_AGENT']
							);
							$this->goodsfblike->fblike_modify($insdata);
						}
					}else{
						if($this->session->userdata('fbuser')) {//페이스북 로그인한 경우 실시간체크함

							if ( $this->snssocial->facebook_goodsLike($goodslikeurl) ) {
								$fblike = 'Y';

								$insdata = array(
								'goods_seq' => $data['goods_seq'],
								'member_seq' => $this->userInfo['member_seq'],
								'sns_id' => $sns_id,
								'session_id' => $session_id,
								'date' => date('Y-m-d H:i:s'),
								'ip' => $this->input->ip_address(),
								'agent' => $_SERVER['HTTP_USER_AGENT']
								);

								$this->goodsfblike->fblike_write($insdata);

							}
						}
					}
				}

				$this->db->where('cart_seq', $cart_seq);
				$this->db->update('fm_cart', array('fblike'=>$fblike));
			}//endif
		}//endforeach
		echo '$("#facebook_mgs").html("");';
		if($_GET['files']=='settle' && !$f)echo 'getfblikeopengraph();';
	}

	public function fbopengraph($f=null)
	{
		/**
		* facebook opengraph > love item
		**/
		$referer = parse_url($_SERVER['HTTP_REFERER']);

		$this->load->model('cartmodel');
		$this->load->model('goodsfblike');
		$session_id = session_id();

		$cart = $this->cartmodel->cart_list();
		foreach($cart['list'] as $data){
			if($this->session->userdata('fbuser')) {//회원로그인한 경우
				$this->snssocial->publishCustomAction($this->domainurl.'/goods/view?no='.$data['goods_seq'],'buy');//.'&buy=1'
			}
		}//endforeach
	}

	// 배송주소록 호출 팝업 :: 2016-08-01 lwh
	public function pop_delivery_address(){
		// reset
		$append_where = '';

		if(!$_GET['member_seq'] && !$this->session->userdata['manager']){
			login_check();
		}

		if($_GET['page'] == 'reload')				$_GET['page'] = '1';

		if(strpos($_GET['page'],"&") === false)		$page = $_GET['page'];
		else										parse_str($_GET['page']);

		$page		= ($page) && is_numeric($page) ? $page		: '1';
		$perpage	= ($_GET['perpage']) && is_numeric($_GET['perpage']) ? $_GET['perpage']	: '4';
		// #28841 injection 처리 19.02.12 kmj
		//$order_by	= ($_GET['orderby'])	? $_GET['orderby']	: 'address_seq DESC';
		//$type		= ($_GET['type'])		? $_GET['type']		: 'often';
		if($this->_is_mobile_agent)	{	$pagenavi	= '3';	$perpage	= '3';	}
		else						{	$pagenavi	= '5';						}
		$member_seq = $this->userInfo['member_seq'];

		$is_admin	= 0;	// 관리자여부 기본값 추가, false일 경우 공백이 출력되기에 0
		if($_GET['member_seq'] && $this->session->userdata['manager']){
			$member_seq = $_GET['member_seq'];
			$is_admin	= true;
		}else{
			$member_seq = $this->userInfo['member_seq'];
		}

		// 해외, 국내 주소만 조회
		// #28841 injection 처리 19.02.12 kmj
		if( $_GET['type'] == "lately" ){
			$append_where = " AND `lately` = 'Y'";
		} else if( $_GET['type'] == "default" ){
			$append_where = " AND `default` = 'Y'";
		} else {
			$append_where = " AND `often` = 'Y'";
		}

		if( preg_match('/^(international)/Ui', $_GET['international']) ){
			$append_where .= " AND `international` = 'international'";
		} else if( preg_match('/^(domestic)/Ui', $_GET['international']) ){
			$append_where .= " AND `international` = 'domestic'";
		}

		// 배송 주소록 목록 추출
		$key = get_shop_key();
		$sql="
			SELECT *,
				AES_DECRYPT(UNHEX(recipient_phone), '{$key}') AS recipient_phone,
				AES_DECRYPT(UNHEX(recipient_cellphone), '{$key}') AS recipient_cellphone
			FROM
				fm_delivery_address
			WHERE
				member_seq = ?".$append_where."
			ORDER BY
				address_seq DESC";

		$result = select_script_page($perpage, $page, $pagenavi, $sql, array($member_seq), 'popDeliverypage');
		$this->template->assign(array('add_loop'=>$result['record']));
		$this->template->assign(array('page'=>$result['page']));
		$this->template->assign(array('is_admin'=>$is_admin));
		if($is_admin){
			/**
			 * 20210527 : kjw
			 * 신규신청 시, 사용자 전용스킨을 설치하지 않으면 문제가 발생하여 admin 스킨으로 분리
			 */
			$this->template->template_dir = BASEPATH . "../admin/skin";
			$this->template->compile_dir = BASEPATH . "../_compile/admin";
			$this->template->define(array('tpl'=>'default/order/pop_delivery_address.html'));
		} else {
			$this->tempate_modules();
			$file_path	= $this->template_path();
			$this->template->define(array('tpl'=>$file_path));
		}
		$this->template->print_("tpl");
	}

	public function eximbay(){

		$payment_config = config_load("eximbay");
		$basic_cur		= $this->config_system['basic_currency'];
		$loop_eximbay_paymethod = code_load('eximbay_paymethod');
		foreach($loop_eximbay_paymethod as $eximbay_paymethod){

			$name =  $eximbay_paymethod['value']['name'];
			$codecd = $eximbay_paymethod['codecd'];
			if(
				preg_match('/P0/',$codecd)
				&& $payment_config['eximbay_payment'][$codecd] == 'y'
			){
				$payment_config['paymethod'][] = array(
					'name'=>$name,
					'code'=>$codecd
				);
			}
		}

		if( !$payment_config['eximbay_mid'] || !$payment_config['eximbay_secretkey'] || !$payment_config['paymethod'] ){
			alert(getAlert('os142',array('Eximbay')),400,140,'parent');
			exit;
		}

		$order_seq	= $this->pg_param['order_seq'];
		$data_order	= $this->ordermodel->get_order($order_seq);

		# 주문 데이터를 토대로 과세상품액, 비과세액, 과세 배송비금액 구해오기
		$tax_invoice_type	= ($_POST['typereceipt'] == 1) ? true : false;		//세금 계산서 신청여부
		$order_tax_prices	= $this->ordermodel->get_order_prices_for_tax($order_seq,$data_order,$tax_invoice_type);

		# 사용한 마일리지/예치금 포함 금액
		$exempt_in_price = (!$order_tax_prices['exempt_in_price'])? '0':$order_tax_prices['exempt_in_price'];

		$data_tax = $this->salesmodel->tax_calulate(
										$order_tax_prices["tax"],
										$order_tax_prices["exempt"],
										$order_tax_prices["shipping_cost"],
										$order_tax_prices["sale"],
										$order_tax_prices["tax_sale"],'SETTLE');
		$supply		= get_cutting_price($data_tax['supply']);
		$surtax			= get_cutting_price($data_tax['surtax']);
		$taxprice		= get_cutting_price($data_tax['supply']) + get_cutting_price($data_tax['surtax']);

		$data_order['supplyvalue'] = $data_order['settleprice'] - $surtax ;
		$data_order['taxamount'] = $surtax	;

		$data_order['ostype'] = 'P';
		if($this->mobileMode){
			$data_order['ostype'] = 'M';
		}


		if( $data_order['international'] == 'international' ){
			if($data_order['recipient_user_name']){
				$arr_user_name = explode(' ',$data_order['recipient_user_name']);
			}
			$nation_key_2_code 					= $this->shippingmodel->get_gl_nation_key_2_code($data_order['international_country']);
			$data_order['shipTo_country']		= $nation_key_2_code ? $nation_key_2_code : $data_order['international_country'];
			$data_order['shipTo_city']			= $data_order['international_town_city'];
			$data_order['shipTo_state']			= $data_order['international_county'];
			$data_order['shipTo_street1']		= $data_order['international_address'];
			$data_order['shipTo_postalCode']	= $data_order['international_postcode'];
			$data_order['shipTo_phoneNumber']	= $data_order['recipient_phone'];
			$data_order['shipTo_firstName']		= $arr_user_name[0];
			$data_order['shipTo_lastName']		= $arr_user_name[1];
		}
		$result_option	= $this->ordermodel->get_data_item_option(array('order_seq'=>$order_seq));
		foreach($result_option->result_array() as $data_option){
			$result_item	= $this->ordermodel->get_data_item(array('item_seq'=>$data_option['item_seq']));
			$data_item = $result_item->row_array();

			$suboptions = '';
			$result_suboption	= $this->ordermodel->get_data_item_suboption($data_option['item_option_seq']);
			foreach($result_suboption->result_array() as $data_suboption){
				$suboptions[] = $data_suboption;
			}
			if($suboptions){
				$data_option['suboptions']	= $suboptions;
			}
			$data_option['item']	= $data_item;
			if($data_item['goods_type'] != 'gift'){
				$options[] = $data_option;
			}
		}

		$this->template->assign(array('pg_param'=>$this->pg_param));
		$this->template->assign(array('payment_config'=>$payment_config));
		$this->template->assign(array('data_order'=>$data_order));
		$this->template->assign(array('data_option'=>$options));
		$this->template->assign(array('order_seq'=>$order_seq));
		$this->template->assign(array('basic_currency'=>$basic_cur));
		$this->template->assign(array('settle_price'=>$this->pg_param['settle_price']));
		$this->template->template_dir = BASEPATH."../order";
		$this->template->compile_dir	= BASEPATH."../_compile/";
		$this->template->define(array('tpl'=>'_eximbay.html'));
		$this->template->print_('tpl');

	}
}
